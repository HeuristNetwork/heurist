<?php

namespace hserv\synchronisation;

use hserv\structure\ConceptCode;

/**
 * Master-side session and permanent record-ID allocation service.
 */
final class SyncSessionService
{
    private \hserv\System $system;
    private \mysqli $mysqli;

    public function __construct(\hserv\System $system)
    {
        $this->system = $system;
        $this->mysqli = $system->getMysqli();
    }

    public function ensureSchema(): bool
    {
        if (SyncSchema::ensure($this->mysqli)) {
            return true;
        }
        $this->system->addError(HEURIST_DB_ERROR, 'Unable to initialise synchronisation tables.', $this->mysqli->error);
        return false;
    }

    public function startSession(int $satelliteDBID, int $baseMasterChangeID = 0): array
    {
        if (!$this->ensureSchema()) {
            return $this->system->getError();
        }
        $existing = $this->selectRowAssoc(
            "SELECT * FROM sysSyncSessions WHERE ssy_SatelliteDBID=? AND ssy_State!='COMPLETED' "
                ."ORDER BY ssy_Created DESC LIMIT 1",
            ['i', $satelliteDBID]
        );
        if (is_array($existing) && !empty($existing['ssy_ID'])) {
            return ['status' => HEURIST_OK, 'data' => $this->normaliseSession($existing), 'resumed' => true];
        }

        $sessionID = self::uuidV4();
        $stmt = $this->mysqli->prepare(
            'INSERT INTO sysSyncSessions '
            .'(ssy_ID,ssy_SatelliteDBID,ssy_State,ssy_BaseMasterChangeID,ssy_Created) '
            ."VALUES (?,?,'STARTED',?,UTC_TIMESTAMP())"
        );
        $stmt->bind_param('sii', $sessionID, $satelliteDBID, $baseMasterChangeID);
        $ok = $stmt->execute();
        $error = $stmt->error;
        $stmt->close();
        if (!$ok) {
            return $this->system->addError(HEURIST_DB_ERROR, 'Unable to create synchronisation session.', $error);
        }
        return [
            'status' => HEURIST_OK,
            'data' => [
                'sessionID' => $sessionID,
                'satelliteDatabaseID' => $satelliteDBID,
                'state' => 'STARTED',
                'baseMasterChangeID' => $baseMasterChangeID
            ],
            'resumed' => false
        ];
    }

    /**
     * Allocates real temporary Records and permanently associates each one with
     * the originating satellite/local ID. Repeating a request returns the same mapping.
     */
    public function allocateRecordIDs(string $sessionID, int $satelliteDBID, array $records): array
    {
        if (!$this->ensureSchema()) {
            return $this->system->getError();
        }
        $session = $this->getSession($sessionID, $satelliteDBID);
        if (!$session) {
            return $this->system->addError(HEURIST_INVALID_REQUEST, 'Unknown synchronisation session.');
        }
        if (in_array($session['state'], ['MASTER_APPLIED', 'MASTER_CHANGES_PREPARED', 'SATELLITE_APPLIED', 'COMPLETED'], true)) {
            return $this->system->addError(HEURIST_ACTION_BLOCKED, 'This session has passed the ID-allocation stage.');
        }
        if (count($records) > 10000) {
            return $this->system->addError(HEURIST_INVALID_REQUEST, 'A single allocation request is limited to 10,000 records.');
        }

        $keepAutocommit = mysql__begin_transaction($this->mysqli);
        $mapping = [];
        $ok = true;
        foreach ($records as $item) {
            $localID = (int)($item['localRecordID'] ?? 0);
            $recordTypeID = (int)($item['masterRecordTypeID'] ?? 0);
            if ($recordTypeID < 1 && !empty($item['recordTypeConceptID'])) {
                ConceptCode::setSystem($this->system);
                $recordTypeID = (int)ConceptCode::getRecTypeLocalID((string)$item['recordTypeConceptID']);
            }
            // A physical database clone can retain NULL origin fields, causing
            // concept codes to be regenerated under the clone's registration ID.
            // Permit the shared local ID only when its type name also matches.
            if ($recordTypeID < 1 && (int)($item['localRecordTypeID'] ?? 0) > 0
                && trim((string)($item['recordTypeName'] ?? '')) !== '') {
                $candidateID = (int)$item['localRecordTypeID'];
                $candidateName = mysql__select_value(
                    $this->mysqli,
                    'SELECT rty_Name FROM defRecTypes WHERE rty_ID=?',
                    ['i', $candidateID]
                );
                if (is_string($candidateName)
                    && hash_equals($candidateName, (string)$item['recordTypeName'])) {
                    $recordTypeID = $candidateID;
                }
            }
            if ($localID < 1 || $recordTypeID < 1) {
                $ok = false;
                $this->system->addError(
                    HEURIST_INVALID_REQUEST,
                    'Every allocation item requires localRecordID and a record type recognised by the master.'
                );
                break;
            }

            $masterID = mysql__select_value(
                $this->mysqli,
                'SELECT srm_MasterRecID FROM sysSyncRecordMap WHERE srm_SatelliteDBID=? AND srm_SatelliteLocalRecID=?',
                ['ii', $satelliteDBID, $localID]
            );
            if (!$masterID) {
                $validType = mysql__select_value(
                    $this->mysqli,
                    'SELECT rty_ID FROM defRecTypes WHERE rty_ID=?',
                    ['i', $recordTypeID]
                );
                if (!$validType) {
                    $ok = false;
                    $this->system->addError(
                        HEURIST_INVALID_REQUEST,
                        "Master record type $recordTypeID does not exist. Structure must be synchronised first."
                    );
                    break;
                }
                $stmt = $this->mysqli->prepare(
                    'INSERT INTO Records '
                    .'(rec_AddedByUGrpID,rec_RecTypeID,rec_OwnerUGrpID,rec_NonOwnerVisibility,'
                    .'rec_URL,rec_ScratchPad,rec_Added,rec_Modified,rec_AddedByImport,rec_FlagTemporary,rec_Title) '
                    ."VALUES (1,?,1,'hidden',NULL,NULL,UTC_TIMESTAMP(),UTC_TIMESTAMP(),1,1,'Reserved for synchronisation')"
                );
                $stmt->bind_param('i', $recordTypeID);
                $ok = $stmt->execute();
                $masterID = (int)$stmt->insert_id;
                if (!$ok || $masterID < 1) {
                    $this->system->addError(HEURIST_DB_ERROR, 'Unable to create temporary record reservation.', $stmt->error);
                }
                $stmt->close();
                if (!$ok || $masterID < 1) {
                    $ok = false;
                    break;
                }
                $stmt = $this->mysqli->prepare(
                    'INSERT INTO sysSyncRecordMap '
                    .'(srm_SatelliteDBID,srm_SatelliteLocalRecID,srm_MasterRecID,srm_SessionID) VALUES (?,?,?,?)'
                );
                $stmt->bind_param('iiis', $satelliteDBID, $localID, $masterID, $sessionID);
                $ok = $stmt->execute();
                if (!$ok) {
                    $this->system->addError(HEURIST_DB_ERROR, 'Unable to save allocated record mapping.', $stmt->error);
                }
                $stmt->close();
                if (!$ok) {
                    break;
                }
            }
            $mapping[] = ['localRecordID' => $localID, 'masterRecordID' => (int)$masterID];
        }

        if ($ok) {
            $stmt = $this->mysqli->prepare("UPDATE sysSyncSessions SET ssy_State='IDS_RESERVED' WHERE ssy_ID=?");
            $stmt->bind_param('s', $sessionID);
            $ok = $stmt->execute();
            $stmt->close();
        }
        mysql__end_transaction($this->mysqli, $ok, $keepAutocommit);
        return $ok
            ? ['status' => HEURIST_OK, 'data' => $mapping, 'state' => 'IDS_RESERVED']
            : $this->system->getError();
    }

    public function listSessions(int $limit = 100): array
    {
        if (!$this->ensureSchema()) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $rows = [];
        $res = $this->mysqli->query('SELECT * FROM sysSyncSessions ORDER BY ssy_Created DESC LIMIT '.$limit);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $res->close();
        }
        return array_map([$this, 'normaliseSession'], is_array($rows) ? $rows : []);
    }

    private function getSession(string $sessionID, int $satelliteDBID): ?array
    {
        $row = $this->selectRowAssoc(
            'SELECT * FROM sysSyncSessions WHERE ssy_ID=? AND ssy_SatelliteDBID=?',
            ['si', $sessionID, $satelliteDBID]
        );
        return is_array($row) ? $this->normaliseSession($row) : null;
    }

    private function normaliseSession(array $row): array
    {
        return [
            'sessionID' => $row['ssy_ID'],
            'satelliteDatabaseID' => (int)$row['ssy_SatelliteDBID'],
            'state' => $row['ssy_State'],
            'baseMasterChangeID' => (int)$row['ssy_BaseMasterChangeID'],
            'resultMasterChangeID' => (int)$row['ssy_ResultMasterChangeID'],
            'created' => $row['ssy_Created'],
            'modified' => $row['ssy_Modified'],
            'completed' => $row['ssy_Completed'],
            'error' => $row['ssy_Error']
        ];
    }

    private function selectRowAssoc(string $query, array $params): ?array
    {
        $result = mysql__select_param_query($this->mysqli, $query, $params);
        $row = $result ? $result->fetch_assoc() : null;
        if ($result) $result->close();
        return is_array($row) ? $row : null;
    }

    private static function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
