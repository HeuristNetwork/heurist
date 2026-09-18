<?php

namespace hserv\synchronisation;

use hserv\structure\ConceptCode;

/**
 * Runs the satellite-side ID-allocation stage as a resumable operation.
 */
final class SatelliteSyncService
{
    private \hserv\System $system;
    private \mysqli $mysqli;
    private SyncConfig $configService;
    private array $config;

    public function __construct(\hserv\System $system, SyncConfig $configService, array $config)
    {
        $this->system = $system;
        $this->mysqli = $system->getMysqli();
        $this->configService = $configService;
        $this->config = $config;
    }

    public function runAllocationStage(): array
    {
        if (!SyncSchema::ensure($this->mysqli) || !(new SyncChangeJournal($this->system))->ensureInstalled()) {
            return $this->system->getError();
        }
        $lockName = 'heurist-sync-'.$this->system->dbname();
        if ((int)mysql__select_value($this->mysqli, 'SELECT GET_LOCK(?,0)', ['s', $lockName]) !== 1) {
            return $this->system->addError(HEURIST_ACTION_BLOCKED, 'Another synchronisation is already running.');
        }

        try {
            $client = new SyncHttpClient($this->system, $this->config);
            $sessionResponse = $client->startSession();
            if (($sessionResponse['status'] ?? null) !== HEURIST_OK) {
                return $sessionResponse;
            }
            $sessionID = (string)$sessionResponse['data']['sessionID'];

            $resumedCount = $this->completeReservedMappings();
            if ($resumedCount === false) {
                return $this->system->getError();
            }

            $journal = new SyncChangeJournal($this->system);
            $after = (int)($this->config['lastNewRecordScanChangeID'] ?? 0);
            $through = $journal->latestChangeID();
            $newRecords = $journal->getNewRecordsBetween($after, $through);
            $allocatedCount = 0;

            if ($newRecords) {
                ConceptCode::setSystem($this->system);
                $request = [];
                foreach ($newRecords as $record) {
                    $recordTypeName = (string)mysql__select_value(
                        $this->mysqli,
                        'SELECT rty_Name FROM defRecTypes WHERE rty_ID=?',
                        ['i', $record['localRecordTypeID']]
                    );
                    $request[] = [
                        'localRecordID' => $record['localRecordID'],
                        'recordTypeConceptID' => ConceptCode::getRecTypeConceptID($record['localRecordTypeID']),
                        'localRecordTypeID' => $record['localRecordTypeID'],
                        'recordTypeName' => $recordTypeName
                    ];
                }
                $allocation = $client->allocateIDs($sessionID, $request);
                if (($allocation['status'] ?? null) !== HEURIST_OK) {
                    return $allocation;
                }
                if (!$this->saveReservations($sessionID, $newRecords, $allocation['data'] ?? [])) {
                    return $this->system->getError();
                }
                $completed = $this->completeReservedMappings();
                if ($completed === false) {
                    return $this->system->getError();
                }
                $allocatedCount = $completed;
            }

            if (!$this->configService->updateRuntime(['lastNewRecordScanChangeID' => $through])) {
                return $this->system->getError();
            }
            return [
                'status' => HEURIST_OK,
                'data' => [
                    'sessionID' => $sessionID,
                    'state' => $newRecords || $resumedCount ? 'IDS_ALLOCATED' : 'NO_NEW_RECORDS',
                    'resumedMappings' => (int)$resumedCount,
                    'newRecordsAllocated' => (int)$allocatedCount,
                    'journalScannedThrough' => $through,
                    'masterSessionResumed' => !empty($sessionResponse['resumed'])
                ]
            ];
        } finally {
            mysql__select_value($this->mysqli, 'SELECT RELEASE_LOCK(?)', ['s', $lockName]);
        }
    }

    private function completeReservedMappings()
    {
        $rows = mysql__select_assoc(
            $this->mysqli,
            "SELECT sor_OriginalLocalRecID,sor_CurrentRecID,sor_MasterRecID "
                ."FROM sysSyncOutboundRecords WHERE sor_Status='RESERVED'",
            0
        );
        if (!$rows) {
            return 0;
        }
        $mapping = [];
        foreach ($rows as $row) {
            $mapping[(int)$row['sor_CurrentRecID']] = (int)$row['sor_MasterRecID'];
        }
        $result = (new RecordIDRemapper($this->system))->remap($mapping);
        if (($result['status'] ?? null) !== HEURIST_OK) {
            return false;
        }
        if (!$this->mysqli->query("UPDATE sysSyncOutboundRecords SET sor_Status='ALLOCATED' WHERE sor_Status='RESERVED'")) {
            $this->system->addError(HEURIST_DB_ERROR, 'Unable to complete outbound allocation state.', $this->mysqli->error);
            return false;
        }
        return count($rows);
    }

    private function saveReservations(string $sessionID, array $records, array $mapping): bool
    {
        $recordByID = [];
        foreach ($records as $record) {
            $recordByID[(int)$record['localRecordID']] = $record;
        }
        if (count($mapping) !== count($records)) {
            $this->system->addError(HEURIST_ERROR, 'Master returned an incomplete record-ID allocation.');
            return false;
        }
        $keepAutocommit = mysql__begin_transaction($this->mysqli);
        $ok = true;
        $stmt = $this->mysqli->prepare(
            'INSERT INTO sysSyncOutboundRecords '
            .'(sor_OriginalLocalRecID,sor_CurrentRecID,sor_MasterRecID,sor_SessionID,sor_FirstChangeID,sor_Status) '
            ."VALUES (?,?,?,?,?,'RESERVED')"
        );
        foreach ($mapping as $item) {
            $localID = (int)($item['localRecordID'] ?? 0);
            $masterID = (int)($item['masterRecordID'] ?? 0);
            $changeID = (int)($recordByID[$localID]['firstChangeID'] ?? 0);
            if ($localID < 1 || $masterID < 1 || $changeID < 1) {
                $ok = false;
                $this->system->addError(HEURIST_ERROR, 'Master returned an invalid record-ID allocation.');
                break;
            }
            $stmt->bind_param('iiisi', $localID, $localID, $masterID, $sessionID, $changeID);
            if (!$stmt->execute()) {
                $ok = false;
                $this->system->addError(HEURIST_DB_ERROR, 'Unable to store outbound record reservation.', $stmt->error);
                break;
            }
        }
        $stmt->close();
        mysql__end_transaction($this->mysqli, $ok, $keepAutocommit);
        return $ok;
    }
}
