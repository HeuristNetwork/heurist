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
        $journal = new SyncChangeJournal($this->system);
        if (!SyncSchema::ensure($this->mysqli) || !$journal->ensureInstalled()) {
            return $this->system->getError();
        }
        $lockName = 'heurist-sync-'.$this->system->dbname();
        if ((int)mysql__select_value($this->mysqli, 'SELECT GET_LOCK(?,0)', ['s', $lockName]) !== 1) {
            return $this->system->addError(HEURIST_ACTION_BLOCKED, 'Another synchronisation is already running.');
        }

        try {
            $progress = new SyncProgress($this->system);
            $initialRecordsSeeded = 0;
            $inventoryPerformed = false;
            if (empty($this->config['initialInventorySeeded'])) {
                $inventoryPerformed = true;
                $progress->update(
                    'INVENTORY',
                    'First synchronisation: inventorying existing local records before counting them…'
                );
                $initialRecordsSeeded = $journal->seedExistingRecords();
                if ($initialRecordsSeeded === false) {
                    return $this->system->getError();
                }
                if (!$this->configService->updateRuntime(['initialInventorySeeded' => true])) {
                    return $this->system->getError();
                }
                $this->config['initialInventorySeeded'] = true;
            }
            $after = (int)($this->config['lastNewRecordScanChangeID'] ?? 0);
            $through = $journal->latestChangeID();
            $newRecords = $journal->getNewRecordsBetween($after, $through);
            $updatedRecordIDs = $journal->getUpdatedRecordIDsBetween($after, $through);
            $updatedRecordsFound = count($updatedRecordIDs);
            // A failed earlier run may already have allocated/remapped new IDs.
            // They remain new records awaiting their first successful upload and
            // must therefore be included in the opening count.
            $pendingNewCount = (int)mysql__select_value(
                $this->mysqli,
                "SELECT COUNT(*) FROM sysSyncOutboundRecords WHERE sor_Status IN ('RESERVED','ALLOCATED')"
            );
            $newRecordsFound = count($newRecords) + $pendingNewCount;
            $progress->begin($newRecordsFound, $updatedRecordsFound);
            if ($inventoryPerformed) {
                $progress->update('INVENTORY_COMPLETE', $initialRecordsSeeded.' existing records inventoried.');
            }
            $progress->update('CONTACTING_MASTER', 'Contacting the master database…');

            $client = new SyncHttpClient($this->system, $this->config);
            $sessionResponse = $client->startSession();
            if (($sessionResponse['status'] ?? null) !== HEURIST_OK) {
                return $sessionResponse;
            }
            $sessionID = (string)$sessionResponse['data']['sessionID'];
            $progress->update('SESSION_READY', 'Master session established.');

            $awaitingUserNotice = '';
            $blockedRecordIDs = [];
            $pendingRecordIDs = array_values(array_unique(array_merge(
                array_map(static fn($record) => (int)$record['localRecordID'], $newRecords),
                array_map('intval', $updatedRecordIDs),
                array_map('intval', mysql__select_list2(
                    $this->mysqli,
                    "SELECT sor_CurrentRecID FROM sysSyncOutboundRecords WHERE sor_Status IN ('RESERVED','ALLOCATED')"
                ) ?: [])
            )));
            if ($pendingRecordIDs) {
                $progress->update('VALIDATING_STRUCTURE', 'Checking satellite-created record types and fields…');
                $usage = $this->buildStructureUsage($pendingRecordIDs);
                $validation = $client->validateStructureUsage($sessionID, $usage);
                if (($validation['status'] ?? null) !== HEURIST_OK) {
                    return $validation;
                }
                $validationData = is_array($validation['data'] ?? null) ? $validation['data'] : [];
                $blockedRecordIDs = array_values(array_unique(array_filter(array_map(
                    'intval',
                    is_array($validationData['blockedRecordIDs'] ?? null)
                        ? $validationData['blockedRecordIDs'] : []
                ))));
                $awaitingUserNotice = trim((string)($validationData['notice'] ?? ''));
                if ($blockedRecordIDs) {
                    $blockedRecordIDs = $this->expandBlockedRecordDependencies($blockedRecordIDs, $pendingRecordIDs);
                    $blockedLookup = array_fill_keys($blockedRecordIDs, true);
                    $newRecords = array_values(array_filter(
                        $newRecords,
                        static fn($record) => empty($blockedLookup[(int)$record['localRecordID']])
                    ));
                    $updatedRecordIDs = array_values(array_filter(
                        $updatedRecordIDs,
                        static fn($recordID) => empty($blockedLookup[(int)$recordID])
                    ));
                    $progress->update(
                        'STRUCTURE_AWAITING_USER',
                        count($blockedRecordIDs).' local record(s) require master structure changes; compatible records will continue.'
                    );
                } else {
                    $progress->update('STRUCTURE_VALID', 'Record types and fields are recognised by the master.');
                }
            }

            $resumedCount = $this->completeReservedMappings();
            if ($resumedCount === false) {
                return $this->system->getError();
            }
            $progress->update('MAPPINGS_READY', $resumedCount
                ? "$resumedCount interrupted record-ID mappings completed."
                : 'Record-ID mappings checked.');
            $allocatedCount = 0;

            if ($newRecords) {
                $progress->update('ALLOCATING_IDS', 'Allocating permanent master IDs for '.count($newRecords).' new records…');
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

            $newRecordRows = mysql__select_assoc(
                $this->mysqli,
                "SELECT sor_OriginalLocalRecID,sor_CurrentRecID,sor_MasterRecID FROM sysSyncOutboundRecords WHERE sor_SessionID='".
                    $this->mysqli->real_escape_string($sessionID)."' AND sor_Status='ALLOCATED' ORDER BY sor_CurrentRecID",
                0
            );
            $newRecordIDs = [];
            $blockedLookup = array_fill_keys($blockedRecordIDs, true);
            foreach ($newRecordRows as $row) {
                // A RESERVED row can be remapped between preflight and upload.
                // Compare all three identities so a held-back record stays held back.
                if (!empty($blockedLookup[(int)$row['sor_OriginalLocalRecID']])
                    || !empty($blockedLookup[(int)$row['sor_CurrentRecID']])
                    || !empty($blockedLookup[(int)$row['sor_MasterRecID']])) {
                    continue;
                }
                $newRecordIDs[] = (int)$row['sor_CurrentRecID'];
            }
            $newUpload = $this->uploadRecordIDs($client, $sessionID, $newRecordIDs, true, $progress);
            if ($newUpload === false) {
                return $this->system->getError();
            }
            $updatedUpload = $this->uploadRecordIDs($client, $sessionID, $updatedRecordIDs, false, $progress,
                (int)$newUpload['records']);
            if ($updatedUpload === false) return $this->system->getError();
            $uploadedCount = (int)$newUpload['records'];
            $updatedCount = (int)$updatedUpload['records'];
            // Do not advance beyond held-back local records: after their definitions
            // are imported to the master they must be rediscovered on the next run.
            if (!$blockedRecordIDs
                && !$this->configService->updateRuntime(['lastNewRecordScanChangeID' => $through])) {
                return $this->system->getError();
            }
            $masterCursor = (int)($this->config['lastMasterChangeReceived'] ?? 0);
            $masterRecordsDownloaded = 0;
            $masterTermsDownloaded = 0;
            $masterFilesDownloaded = 0;
            $masterDefinitionsApplied = 0;
            $inboundService = new SatelliteInboundService($this->system);
            $batchNumber = 0;
            do {
                $batchNumber++;
                if ($batchNumber > 10000) {
                    return $this->system->addError(HEURIST_ACTION_BLOCKED, 'Master download stopped after too many batches.');
                }
                $progress->update(
                    'DOWNLOADING_MASTER',
                    $masterRecordsDownloaded
                        ? "$masterRecordsDownloaded master records received; checking for more…"
                        : 'Checking the master for records contributed by other databases…',
                    $uploadedCount,
                    $updatedCount
                );
                $download = $client->downloadChanges(
                    $sessionID,
                    $masterCursor,
                    $batchNumber === 1,
                    (string)($this->config['lastMasterStructureHash'] ?? '')
                );
                if (($download['status'] ?? null) !== HEURIST_OK) return $download;
                $downloadData = is_array($download['data'] ?? null) ? $download['data'] : [];
                $available = (int)($downloadData['recordsAvailable'] ?? 0);
                $structure = is_array($downloadData['structure'] ?? null) ? $downloadData['structure'] : null;
                if ($available > 0 || $structure !== null) {
                    $progress->update(
                        'APPLYING_MASTER',
                        $structure !== null
                            ? 'Updating the satellite structure from the master before applying records…'
                            : 'Applying '.$available.' records and their terms/files from the master…',
                        $uploadedCount,
                        $updatedCount
                    );
                    $applied = $inboundService->apply(
                        is_array($downloadData['dependencies'] ?? null) ? $downloadData['dependencies'] : [],
                        is_array($downloadData['payload'] ?? null) ? $downloadData['payload'] : [],
                        $structure
                    );
                    if (($applied['status'] ?? null) !== HEURIST_OK) return $applied;
                    $masterRecordsDownloaded += (int)($applied['data']['recordsApplied'] ?? 0);
                    $masterTermsDownloaded += (int)($applied['data']['termsCreated'] ?? 0);
                    $masterFilesDownloaded += (int)($applied['data']['filesCreated'] ?? 0);
                    $masterDefinitionsApplied += (int)($applied['data']['definitionsApplied'] ?? 0);
                    $receivedStructureHash = (string)($downloadData['structureHash'] ?? '');
                    if ($structure !== null && $receivedStructureHash !== '') {
                        if (!$this->configService->updateRuntime(['lastMasterStructureHash' => $receivedStructureHash])) {
                            return $this->system->getError();
                        }
                        $this->config['lastMasterStructureHash'] = $receivedStructureHash;
                    }
                }
                $nextCursor = (int)($downloadData['nextChangeID'] ?? $masterCursor);
                if ($nextCursor < $masterCursor || ($available > 0 && $nextCursor === $masterCursor)) {
                    return $this->system->addError(HEURIST_ACTION_BLOCKED, 'The master returned an invalid change cursor.');
                }
                $masterCursor = $nextCursor;
                if (!$this->configService->updateRuntime(['lastMasterChangeReceived' => $masterCursor])) {
                    return $this->system->getError();
                }
                $hasMore = !empty($downloadData['hasMore']);
            } while ($hasMore);

            if ($awaitingUserNotice !== '') {
                $progress->awaitUserAction(
                    'Compatible records synchronised; satellite-only structure remains pending.',
                    $uploadedCount,
                    $updatedCount
                );
            } else {
                $progress->complete(
                    'Synchronisation completed successfully. Master structure synchronised; '.$masterRecordsDownloaded
                        .' master records applied locally.',
                    $uploadedCount,
                    $updatedCount
                );
            }
            return [
                'status' => HEURIST_OK,
                'data' => [
                    'sessionID' => $sessionID,
                    'state' => $awaitingUserNotice !== '' ? 'AWAITING_USER_ACTION'
                        : (($uploadedCount || $updatedCount || $masterRecordsDownloaded || $masterDefinitionsApplied)
                            ? 'RECORDS_TRANSFERRED' : 'NO_CHANGES'),
                    'userActionNotice' => $awaitingUserNotice,
                    'recordsAwaitingUserAction' => count($blockedRecordIDs),
                    'resumedMappings' => (int)$resumedCount,
                    'newRecordsFound' => $newRecordsFound,
                    'newRecordsAllocated' => (int)$allocatedCount,
                    'newRecordsUploaded' => (int)$uploadedCount,
                    'updatedRecordsFound' => $updatedRecordsFound,
                    'updatedRecordsUploaded' => $updatedCount,
                    'newTermsUploaded' => (int)$newUpload['terms'] + (int)$updatedUpload['terms'],
                    'newFilesUploaded' => (int)$newUpload['files'] + (int)$updatedUpload['files'],
                    'masterRecordsDownloaded' => $masterRecordsDownloaded,
                    'masterTermsDownloaded' => $masterTermsDownloaded,
                    'masterFilesDownloaded' => $masterFilesDownloaded,
                    'masterDefinitionsApplied' => $masterDefinitionsApplied,
                    'lastMasterChangeReceived' => $masterCursor,
                    'journalScannedThrough' => $through,
                    'initialRecordsInventoried' => (int)$initialRecordsSeeded,
                    'masterSessionResumed' => !empty($sessionResponse['resumed'])
                ]
            ];
        } finally {
            mysql__select_value($this->mysqli, 'SELECT RELEASE_LOCK(?)', ['s', $lockName]);
        }
    }

    private function uploadRecordIDs(
        SyncHttpClient $client,
        string $sessionID,
        array $recordIDs,
        bool $newRecords,
        SyncProgress $progress,
        int $newAlreadyCompleted = 0
    )
    {
        if (!$recordIDs) return ['records' => 0, 'terms' => 0, 'files' => 0];
        $uploaded = 0;
        $termsUploaded = 0;
        $filesUploaded = 0;
        foreach (array_chunk($recordIDs, 100) as $batchIDs) {
            $kind = $newRecords ? 'new' : 'updated';
            $progress->update('PREPARING_DEPENDENCIES',
                'Preparing terms and files for '.count($batchIDs)." $kind records…",
                $newRecords ? $uploaded : $newAlreadyCompleted,
                $newRecords ? 0 : $uploaded);
            try {
                $dependencies = SyncDependencyPayload::export($this->system, $batchIDs);
                $payload = SyncRecordPayload::export($this->system, $batchIDs);
            } catch (\RuntimeException $e) {
                $this->system->addError(HEURIST_ACTION_BLOCKED, $e->getMessage());
                return false;
            }
            $dependencyResponse = $client->uploadDependencies($sessionID, $dependencies);
            if (($dependencyResponse['status'] ?? null) !== HEURIST_OK) {
                if (!isset($dependencyResponse['message']) && isset($dependencyResponse['msg'])) {
                    $dependencyResponse['message'] = $dependencyResponse['msg'];
                }
                $this->system->addErrorArr($dependencyResponse);
                return false;
            }
            $termsUploaded += (int)($dependencyResponse['data']['termsCreated'] ?? 0);
            $filesUploaded += (int)($dependencyResponse['data']['filesCreated'] ?? 0);
            $progress->update('UPLOADING_RECORDS', 'Uploading '.count($batchIDs)." $kind records to the master…",
                $newRecords ? $uploaded : $newAlreadyCompleted,
                $newRecords ? 0 : $uploaded);
            $response = $client->uploadRecords($sessionID, $payload);
            if (($response['status'] ?? null) !== HEURIST_OK) {
                if (!isset($response['message']) && isset($response['msg'])) $response['message'] = $response['msg'];
                $this->system->addErrorArr($response);
                return false;
            }
            $idList = implode(',', array_map('intval', $batchIDs));
            if ($newRecords && !$this->mysqli->query("UPDATE sysSyncOutboundRecords SET sor_Status='UPLOADED' WHERE sor_CurrentRecID IN ($idList)")) {
                $this->system->addError(HEURIST_DB_ERROR, 'The records were uploaded but local completion state could not be saved.', $this->mysqli->error);
                return false;
            }
            $uploaded += count($batchIDs);
            $progress->update('BATCH_APPLIED', ucfirst($kind).' records transferred: '.$uploaded.' of '.count($recordIDs).'.',
                $newRecords ? $uploaded : $newAlreadyCompleted,
                $newRecords ? 0 : $uploaded);
        }
        return ['records' => $uploaded, 'terms' => $termsUploaded, 'files' => $filesUploaded];
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

    /** Builds a compact list of structure definitions actually used by pending records. */
    private function buildStructureUsage(array $recordIDs): array
    {
        $recordIDs = array_values(array_unique(array_filter(array_map('intval', $recordIDs))));
        if (!$recordIDs) return ['recordTypes' => [], 'fields' => []];
        ConceptCode::setSystem($this->system);
        $idList = implode(',', $recordIDs);
        $recordTypes = [];
        $fields = [];
        $rows = mysql__select_assoc(
            $this->mysqli,
            'SELECT r.rec_ID,r.rec_RecTypeID,rty.rty_Name FROM Records r '
                .'JOIN defRecTypes rty ON rty.rty_ID=r.rec_RecTypeID WHERE r.rec_ID IN ('.$idList.')',
            0
        ) ?: [];
        foreach ($rows as $row) {
            $localID = (int)$row['rec_RecTypeID'];
            $conceptID = ConceptCode::getRecTypeConceptID($localID);
            if (!isset($recordTypes[$conceptID])) {
                $recordTypes[$conceptID] = [
                    'conceptID' => $conceptID,
                    'localID' => $localID,
                    'name' => (string)$row['rty_Name'],
                    'recordIDs' => []
                ];
            }
            $recordTypes[$conceptID]['recordIDs'][] = (int)$row['rec_ID'];
        }
        $rows = mysql__select_assoc(
            $this->mysqli,
            'SELECT DISTINCT r.rec_ID,r.rec_RecTypeID,rty.rty_Name,d.dtl_DetailTypeID,dty.dty_Name,dty.dty_Type '
                .'FROM Records r JOIN defRecTypes rty ON rty.rty_ID=r.rec_RecTypeID '
                .'JOIN recDetails d ON d.dtl_RecID=r.rec_ID '
                .'JOIN defDetailTypes dty ON dty.dty_ID=d.dtl_DetailTypeID '
                .'WHERE r.rec_ID IN ('.$idList.')',
            0
        ) ?: [];
        foreach ($rows as $row) {
            $recordTypeID = (int)$row['rec_RecTypeID'];
            $fieldID = (int)$row['dtl_DetailTypeID'];
            $recordTypeConceptID = ConceptCode::getRecTypeConceptID($recordTypeID);
            $fieldConceptID = ConceptCode::getDetailTypeConceptID($fieldID);
            $key = $recordTypeConceptID.'|'.$fieldConceptID;
            if (!isset($fields[$key])) {
                $fields[$key] = [
                    'conceptID' => $fieldConceptID,
                    'localID' => $fieldID,
                    'name' => (string)$row['dty_Name'],
                    'type' => (string)$row['dty_Type'],
                    'recordTypeConceptID' => $recordTypeConceptID,
                    'recordTypeName' => (string)$row['rty_Name'],
                    'recordIDs' => []
                ];
            }
            $fields[$key]['recordIDs'][] = (int)$row['rec_ID'];
        }
        return ['recordTypes' => array_values($recordTypes), 'fields' => array_values($fields)];
    }

    /** Holds back records whose resource-pointer fields target another held-back record. */
    private function expandBlockedRecordDependencies(array $blockedRecordIDs, array $pendingRecordIDs): array
    {
        $blocked = array_fill_keys(array_map('intval', $blockedRecordIDs), true);
        $pending = array_values(array_unique(array_filter(array_map('intval', $pendingRecordIDs))));
        if (!$blocked || !$pending) return array_keys($blocked);
        $pendingList = implode(',', $pending);
        do {
            $before = count($blocked);
            $blockedList = implode(',', array_map('intval', array_keys($blocked)));
            $rows = mysql__select_list2(
                $this->mysqli,
                'SELECT DISTINCT d.dtl_RecID FROM recDetails d '
                    .'JOIN defDetailTypes t ON t.dty_ID=d.dtl_DetailTypeID AND t.dty_Type=\'resource\' '
                    .'WHERE d.dtl_RecID IN ('.$pendingList.') '
                    .'AND CAST(d.dtl_Value AS UNSIGNED) IN ('.$blockedList.')'
            );
            foreach ($rows as $recordID) $blocked[(int)$recordID] = true;
        } while (count($blocked) > $before);
        return array_values(array_map('intval', array_keys($blocked)));
    }
}
