<?php

namespace hserv\synchronisation;

/** Exports master records changed since a satellite's last successful download. */
final class SyncMasterChangeService
{
    private \hserv\System $system;
    private \mysqli $mysqli;

    public function __construct(\hserv\System $system)
    {
        $this->system = $system;
        $this->mysqli = $system->getMysqli();
    }

    public function download(string $sessionID, int $satelliteDBID, int $afterChangeID,
        bool $includeStructure = false, string $satelliteStructureHash = ''): array
    {
        if (!SyncSchema::ensure($this->mysqli)) return $this->system->getError();
        if (!mysql__select_value(
            $this->mysqli,
            'SELECT ssy_ID FROM sysSyncSessions WHERE ssy_ID=? AND ssy_SatelliteDBID=?',
            ['si', $sessionID, $satelliteDBID]
        )) {
            return $this->system->addError(HEURIST_INVALID_REQUEST, 'Unknown synchronisation session.');
        }

        $journal = new SyncChangeJournal($this->system);
        if (!$journal->ensureInstalled()) return $this->system->getError();
        $snapshotChangeID = $journal->latestChangeID();
        $stmt = $this->mysqli->prepare(
            "SELECT c.sch_RecID,MIN(c.sch_ID) AS firstChangeID FROM sysSyncChanges c "
            ."JOIN Records r ON r.rec_ID=c.sch_RecID AND r.rec_FlagTemporary=0 "
            ."LEFT JOIN sysSyncRecordMap m ON m.srm_MasterRecID=c.sch_RecID "
            ."WHERE c.sch_ID>? AND c.sch_ID<=? AND c.sch_Entity IN ('record','detail') "
            ."AND c.sch_Action!='delete' AND (m.srm_SatelliteDBID IS NULL OR m.srm_SatelliteDBID<>?) "
            ."GROUP BY c.sch_RecID ORDER BY firstChangeID LIMIT 100"
        );
        $stmt->bind_param('iii', $afterChangeID, $snapshotChangeID, $satelliteDBID);
        $stmt->execute();
        $result = $stmt->get_result();
        $recordIDs = [];
        $nextChangeID = $afterChangeID;
        while ($row = $result->fetch_assoc()) {
            $recordIDs[] = (int)$row['sch_RecID'];
            $nextChangeID = max($nextChangeID, (int)$row['firstChangeID']);
        }
        $result->close();
        $stmt->close();

        if (!$recordIDs) {
            $nextChangeID = $snapshotChangeID;
            $dependencies = ['format' => 'heurist-sync-dependencies-1', 'recordIDs' => [],
                'recordShells' => [], 'terms' => [], 'files' => []];
            $structure = null;
            $structureHash = $satelliteStructureHash;
            if ($includeStructure) {
                $structure = SyncStructurePayload::export($this->system);
                $structureHash = (string)$structure['hash'];
                if ($satelliteStructureHash !== '' && hash_equals($structureHash, $satelliteStructureHash)) $structure = null;
                else $dependencies['terms'] = SyncDependencyPayload::exportAllTerms($this->system);
            }
            return ['status' => HEURIST_OK, 'data' => [
                'recordsAvailable' => 0,
                'nextChangeID' => $nextChangeID,
                'snapshotChangeID' => $snapshotChangeID,
                'hasMore' => false,
                'structure' => $structure,
                'structureHash' => $structureHash,
                'dependencies' => $dependencies,
                'payload' => ['format' => 'heurist-hml-sync-1', 'records' => []]
            ]];
        }

        try {
            $dependencies = SyncDependencyPayload::export($this->system, $recordIDs);
            $payload = SyncRecordPayload::export($this->system, $recordIDs);
            $structure = null;
            $structureHash = $satelliteStructureHash;
            if ($includeStructure) {
                $structure = SyncStructurePayload::export($this->system);
                $structureHash = (string)$structure['hash'];
                if ($satelliteStructureHash !== '' && hash_equals($structureHash, $satelliteStructureHash)) $structure = null;
                else $dependencies['terms'] = SyncDependencyPayload::exportAllTerms($this->system);
            }
        } catch (\RuntimeException $e) {
            return $this->system->addError(HEURIST_ACTION_BLOCKED, $e->getMessage());
        }
        $hasMore = (bool)mysql__select_value(
            $this->mysqli,
            "SELECT 1 FROM sysSyncChanges c JOIN Records r ON r.rec_ID=c.sch_RecID AND r.rec_FlagTemporary=0 "
            ."LEFT JOIN sysSyncRecordMap m ON m.srm_MasterRecID=c.sch_RecID "
            ."WHERE c.sch_ID>? AND c.sch_ID<=? AND c.sch_Entity IN ('record','detail') "
            ."AND c.sch_Action!='delete' AND (m.srm_SatelliteDBID IS NULL OR m.srm_SatelliteDBID<>?) LIMIT 1",
            ['iii', $nextChangeID, $snapshotChangeID, $satelliteDBID]
        );
        return ['status' => HEURIST_OK, 'data' => [
            'recordsAvailable' => count($recordIDs),
            'nextChangeID' => $nextChangeID,
            'snapshotChangeID' => $snapshotChangeID,
            'hasMore' => $hasMore,
            'structure' => $structure,
            'structureHash' => $structureHash,
            'dependencies' => $dependencies,
            'payload' => $payload
        ]];
    }
}
