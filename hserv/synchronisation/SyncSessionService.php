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
     * Identifies satellite-only record types, base fields and record-structure
     * fields so affected records can be held back without stopping other records.
     */
    public function validateStructureUsage(string $sessionID, int $satelliteDBID, array $usage): array
    {
        if (!$this->ensureSchema()) {
            return $this->system->getError();
        }
        if (!$this->getSession($sessionID, $satelliteDBID)) {
            return $this->system->addError(HEURIST_INVALID_REQUEST, 'Unknown synchronisation session.');
        }

        $recordTypes = is_array($usage['recordTypes'] ?? null) ? $usage['recordTypes'] : [];
        $fields = is_array($usage['fields'] ?? null) ? $usage['fields'] : [];
        if (count($recordTypes) > 1000 || count($fields) > 10000) {
            return $this->system->addError(HEURIST_INVALID_REQUEST, 'The synchronisation structure preflight exceeds its size limit.');
        }

        ConceptCode::setSystem($this->system);
        $resolvedRecordTypes = [];
        $unsupportedRecordTypes = [];
        $blockedRecordIDs = [];
        foreach ($recordTypes as $recordType) {
            $conceptID = trim((string)($recordType['conceptID'] ?? ''));
            $localID = (int)($recordType['localID'] ?? 0);
            $name = trim((string)($recordType['name'] ?? ''));
            $masterID = (int)ConceptCode::getRecTypeLocalID($conceptID);
            // Database clones can retain matching local structure IDs while their
            // origin metadata is absent. Preserve the existing safe name check.
            if ($masterID < 1 && $localID > 0 && $name !== '') {
                $candidateName = mysql__select_value(
                    $this->mysqli,
                    'SELECT rty_Name FROM defRecTypes WHERE rty_ID=?',
                    ['i', $localID]
                );
                if (is_string($candidateName) && hash_equals($candidateName, $name)) {
                    $masterID = $localID;
                }
            }
            $key = $conceptID !== '' ? $conceptID : 'local-'.$localID;
            $resolvedRecordTypes[$key] = $masterID;
            if ($masterID < 1) {
                $unsupportedRecordTypes[$key] = $name !== '' ? $name : ($conceptID !== '' ? $conceptID : 'Unnamed record type');
                $this->collectRecordIDs($blockedRecordIDs, $recordType['recordIDs'] ?? []);
            }
        }

        $unsupportedBaseFields = [];
        $unsupportedStructureFields = [];
        foreach ($fields as $field) {
            $conceptID = trim((string)($field['conceptID'] ?? ''));
            $localID = (int)($field['localID'] ?? 0);
            $name = trim((string)($field['name'] ?? ''));
            $type = trim((string)($field['type'] ?? ''));
            $recordTypeConceptID = trim((string)($field['recordTypeConceptID'] ?? ''));
            $recordTypeName = trim((string)($field['recordTypeName'] ?? ''));
            $masterFieldID = (int)ConceptCode::getDetailTypeLocalID($conceptID);
            if ($masterFieldID < 1 && $localID > 0 && $name !== '') {
                $candidate = mysql__select_row(
                    $this->mysqli,
                    'SELECT dty_Name,dty_Type FROM defDetailTypes WHERE dty_ID='.$localID
                );
                if (is_array($candidate) && is_string($candidate[0] ?? null)
                    && hash_equals((string)$candidate[0], $name)
                    && hash_equals((string)($candidate[1] ?? ''), $type)) {
                    $masterFieldID = $localID;
                }
            }
            $displayName = $name !== '' ? $name : ($conceptID !== '' ? $conceptID : 'Unnamed field');
            $context = $recordTypeName !== '' ? $recordTypeName : $recordTypeConceptID;
            $display = '"'.$displayName.'"'.($context !== '' ? ' (used in "'.$context.'")' : '');
            if ($masterFieldID < 1) {
                $fieldKey = $conceptID !== '' ? $conceptID : 'local-'.$localID;
                $unsupportedBaseFields[$recordTypeConceptID.'|'.$fieldKey] = $display;
                $this->collectRecordIDs($blockedRecordIDs, $field['recordIDs'] ?? []);
                continue;
            }
            $masterFieldType = (string)mysql__select_value(
                $this->mysqli,
                'SELECT dty_Type FROM defDetailTypes WHERE dty_ID=?',
                ['i', $masterFieldID]
            );
            if ($masterFieldType === '' || !hash_equals($masterFieldType, $type)) {
                $fieldKey = $conceptID !== '' ? $conceptID : 'local-'.$localID;
                $unsupportedBaseFields[$recordTypeConceptID.'|'.$fieldKey] = $display
                    .' (satellite field type "'.$type.'"; master field type "'.$masterFieldType.'")';
                $this->collectRecordIDs($blockedRecordIDs, $field['recordIDs'] ?? []);
                continue;
            }
            $masterRecordTypeID = (int)($resolvedRecordTypes[$recordTypeConceptID] ?? 0);
            if ($masterRecordTypeID < 1) {
                continue; // The containing record type is already reported above.
            }
            if (!mysql__select_value(
                $this->mysqli,
                'SELECT rst_ID FROM defRecStructure WHERE rst_RecTypeID=? AND rst_DetailTypeID=? LIMIT 1',
                ['ii', $masterRecordTypeID, $masterFieldID]
            )) {
                $key = $masterRecordTypeID.'-'.$masterFieldID;
                $unsupportedStructureFields[$key] = '"'.$displayName.'" added to record type "'.$context.'"';
                $this->collectRecordIDs($blockedRecordIDs, $field['recordIDs'] ?? []);
            }
        }

        if (!$unsupportedRecordTypes && !$unsupportedBaseFields && !$unsupportedStructureFields) {
            return ['status' => HEURIST_OK, 'data' => ['valid' => true, 'blockedRecordIDs' => []]];
        }

        $lines = [
            'The following record types and/or fields have been created and used on this satellite database. '
                .'To avoid polluting the master, they have not been imported automatically.',
            ''
        ];
        if ($unsupportedRecordTypes) {
            $lines[] = 'Record types:';
            foreach ($unsupportedRecordTypes as $label) $lines[] = '- '.$label;
            $lines[] = '';
        }
        if ($unsupportedBaseFields) {
            $lines[] = 'Base field types:';
            foreach ($unsupportedBaseFields as $label) $lines[] = '- '.$label;
            $lines[] = '';
        }
        if ($unsupportedStructureFields) {
            $lines[] = 'Fields added to record types:';
            foreach ($unsupportedStructureFields as $label) $lines[] = '- '.$label;
            $lines[] = '';
        }
        $lines[] = 'If you, or the owner of the master database, wish to add them to the master database, open the master database and use Design > Browse templates, then navigate to the satellite database to import the relevant record types and/or fields. Then rerun the synchronisation.';
        return [
            'status' => HEURIST_OK,
            'data' => [
                'valid' => false,
                'blockedRecordIDs' => array_values(array_map('intval', array_keys($blockedRecordIDs))),
                'notice' => implode("\n", $lines)
            ]
        ];
    }

    private function collectRecordIDs(array &$target, $recordIDs): void
    {
        if (!is_array($recordIDs)) return;
        foreach ($recordIDs as $recordID) {
            $recordID = (int)$recordID;
            if ($recordID > 0) $target[$recordID] = true;
        }
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

    /** Validates and applies a retry-safe portable record batch to its reserved master records. */
    public function uploadRecords(string $sessionID, int $satelliteDBID, array $payload): array
    {
        if (!$this->ensureSchema()) return $this->system->getError();
        $session = $this->getSession($sessionID, $satelliteDBID);
        if (!$session) return $this->system->addError(HEURIST_INVALID_REQUEST, 'Unknown synchronisation session.');
        if (($payload['format'] ?? '') !== 'heurist-hml-sync-1' || !is_array($payload['records'] ?? null)) {
            return $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid record-content payload.');
        }
        $records = $payload['records'];
        if (count($records) > 1000) {
            return $this->system->addError(HEURIST_INVALID_REQUEST, 'A single content batch is limited to 1,000 records.');
        }
        $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $prior = mysql__select_value($this->mysqli,
            "SELECT spl_State FROM sysSyncPayloads WHERE spl_Hash=? AND spl_SessionID=? AND spl_SatelliteDBID=?",
            ['ssi', $hash, $sessionID, $satelliteDBID]);
        if ($prior === 'APPLIED') {
            return ['status' => HEURIST_OK, 'data' => ['recordsApplied' => count($records), 'payloadHash' => $hash, 'replayed' => true]];
        }

        ConceptCode::setSystem($this->system);
        $resolved = [];
        foreach ($records as $record) {
            $recordID = (int)($record['rec_ID'] ?? 0);
            $mapped = mysql__select_value($this->mysqli,
                'SELECT srm_MasterRecID FROM sysSyncRecordMap WHERE srm_SatelliteDBID=? AND srm_MasterRecID=? AND srm_SessionID=?',
                ['iis', $satelliteDBID, $recordID, $sessionID]);
            if (!$mapped) return $this->system->addError(HEURIST_INVALID_REQUEST, "Record $recordID was not reserved by this session.");
            $masterTypeID = (int)ConceptCode::getRecTypeLocalID((string)($record['recordTypeConceptID'] ?? ''));
            $reservedTypeID = (int)mysql__select_value($this->mysqli, 'SELECT rec_RecTypeID FROM Records WHERE rec_ID=?', ['i', $recordID]);
            if ($masterTypeID < 1 && (int)($record['rec_RecTypeID'] ?? 0) > 0) {
                $candidateID = (int)$record['rec_RecTypeID'];
                $candidateName = mysql__select_value($this->mysqli, 'SELECT rty_Name FROM defRecTypes WHERE rty_ID=?', ['i', $candidateID]);
                if (is_string($candidateName) && hash_equals($candidateName, (string)($record['recordTypeName'] ?? ''))) $masterTypeID = $candidateID;
            }
            if ($masterTypeID < 1 || $masterTypeID !== $reservedTypeID) {
                return $this->system->addError(HEURIST_INVALID_REQUEST, "The structure for record $recordID does not match the master reservation.");
            }
            $details = [];
            foreach (($record['details'] ?? []) as $detail) {
                $dtyID = (int)ConceptCode::getDetailTypeLocalID((string)($detail['detailTypeConceptID'] ?? ''));
                if ($dtyID < 1 && (int)($detail['dtl_DetailTypeID'] ?? 0) > 0) {
                    $candidateID = (int)$detail['dtl_DetailTypeID'];
                    $candidateName = mysql__select_value($this->mysqli, 'SELECT dty_Name FROM defDetailTypes WHERE dty_ID=?', ['i', $candidateID]);
                    if (is_string($candidateName) && hash_equals($candidateName, (string)($detail['dty_Name'] ?? ''))) $dtyID = $candidateID;
                }
                $masterDtyType = $dtyID > 0 ? mysql__select_value($this->mysqli,
                    'SELECT dty_Type FROM defDetailTypes WHERE dty_ID=?', ['i', $dtyID]) : null;
                if (!$masterDtyType || $masterDtyType !== ($detail['dty_Type'] ?? '')) {
                    return $this->system->addError(HEURIST_INVALID_REQUEST,
                        'The master does not recognise field '.($detail['dty_Name'] ?? $detail['detailTypeConceptID'] ?? '')." for record $recordID.");
                }
                if (!mysql__select_value($this->mysqli,
                    'SELECT rst_ID FROM defRecStructure WHERE rst_RecTypeID=? AND rst_DetailTypeID=? LIMIT 1',
                    ['ii', $masterTypeID, $dtyID])) {
                    return $this->system->addError(HEURIST_INVALID_REQUEST,
                        'Field '.($detail['dty_Name'] ?? $dtyID)." is not present in the master structure for record $recordID.");
                }
                $value = $detail['dtl_Value'] ?? null;
                if (in_array($masterDtyType, ['enum', 'relationtype'], true) && !empty($detail['termConceptID'])) {
                    $termID = (int)ConceptCode::getTermLocalID((string)$detail['termConceptID']);
                    if ($termID < 1 && (int)($detail['dtl_Value'] ?? 0) > 0) {
                        $candidateID = (int)$detail['dtl_Value'];
                        $candidateLabel = mysql__select_value($this->mysqli, 'SELECT trm_Label FROM defTerms WHERE trm_ID=?', ['i', $candidateID]);
                        if (is_string($candidateLabel) && hash_equals($candidateLabel, (string)($detail['termLabel'] ?? ''))) $termID = $candidateID;
                    }
                    if ($termID < 1) return $this->system->addError(HEURIST_ACTION_BLOCKED,
                        'The master does not yet contain term '.($detail['termLabel'] ?? $detail['termConceptID']).'. Synchronise terms first.');
                    $value = (string)$termID;
                }
                if ($masterDtyType === 'resource' && (int)$value > 0
                    && !mysql__select_value($this->mysqli, 'SELECT rec_ID FROM Records WHERE rec_ID=?', ['i', (int)$value])) {
                    return $this->system->addError(HEURIST_INVALID_REQUEST, "Record $recordID points to missing master record $value.");
                }
                $masterFileID = null;
                if (!empty($detail['satelliteFileID'])) {
                    $masterFileID = (int)mysql__select_value($this->mysqli,
                        'SELECT sfm_MasterFileID FROM sysSyncFileMap WHERE sfm_SatelliteDBID=? AND sfm_SatelliteFileID=?',
                        ['ii', $satelliteDBID, (int)$detail['satelliteFileID']]);
                    if ($masterFileID < 1) return $this->system->addError(HEURIST_ACTION_BLOCKED,
                        "The file dependency for record $recordID has not been uploaded.");
                }
                $detail['_masterDtyID'] = $dtyID;
                $detail['_masterValue'] = $value;
                $detail['_masterFileID'] = $masterFileID;
                $details[] = $detail;
            }
            $record['_details'] = $details;
            $resolved[] = $record;
        }

        $this->mysqli->query("INSERT INTO sysSyncPayloads (spl_Hash,spl_SessionID,spl_SatelliteDBID,spl_RecordCount,spl_State) VALUES ('".
            $this->mysqli->real_escape_string($hash)."','".$this->mysqli->real_escape_string($sessionID)."',$satelliteDBID,".count($records).",'RECEIVED') "
            ."ON DUPLICATE KEY UPDATE spl_State='RECEIVED',spl_Error=NULL");
        $keepAutocommit = mysql__begin_transaction($this->mysqli);
        $ok = true;
        foreach ($resolved as $record) {
            $recordID = (int)$record['rec_ID'];
            $stmt = $this->mysqli->prepare('UPDATE Records SET rec_URL=?,rec_Added=?,rec_Title=?,rec_ScratchPad=?,rec_AddedByImport=?,'
                .'rec_NonOwnerVisibility=?,rec_FlagTemporary=0,rec_Modified=UTC_TIMESTAMP() WHERE rec_ID=?');
            $url = $record['rec_URL']; $added = $record['rec_Added']; $title = (string)$record['rec_Title'];
            $scratch = $record['rec_ScratchPad']; $byImport = (int)$record['rec_AddedByImport'];
            $visibility = (string)$record['rec_NonOwnerVisibility'];
            $stmt->bind_param('ssssisi', $url, $added, $title, $scratch, $byImport, $visibility, $recordID);
            $ok = $stmt->execute();
            if (!$ok) $this->system->addError(HEURIST_DB_ERROR, "Unable to update master record $recordID.", $stmt->error);
            $stmt->close();
            if (!$ok || !$this->mysqli->query('DELETE FROM recDetails WHERE dtl_RecID='.$recordID)) { $ok = false; break; }
            foreach ($record['_details'] as $detail) {
                $stmt = $this->mysqli->prepare('INSERT INTO recDetails '
                    .'(dtl_RecID,dtl_DetailTypeID,dtl_Value,dtl_UploadedFileID,dtl_Geo,dtl_Certainty,dtl_Annotation,dtl_HideFromPublic,dtl_AddedByImport) '
                    .'VALUES (?,?,?,?,IF(? IS NULL,NULL,ST_GeomFromText(?)),?,?,?,1)');
                $dtyID = (int)$detail['_masterDtyID']; $value = $detail['_masterValue']; $geo = $detail['dtl_Geo'] ?? null;
                $fileID = $detail['_masterFileID'];
                $certainty = (float)($detail['dtl_Certainty'] ?? 1); $annotation = $detail['dtl_Annotation'] ?? null;
                $hidden = isset($detail['dtl_HideFromPublic']) ? (int)$detail['dtl_HideFromPublic'] : null;
                $stmt->bind_param('iisissdsi', $recordID, $dtyID, $value, $fileID, $geo, $geo, $certainty, $annotation, $hidden);
                $ok = $stmt->execute();
                if (!$ok) $this->system->addError(HEURIST_DB_ERROR, "Unable to add a field to master record $recordID.", $stmt->error);
                $stmt->close();
                if (!$ok) break 2;
            }
        }
        if ($ok) {
            $stmt = $this->mysqli->prepare("UPDATE sysSyncPayloads SET spl_State='APPLIED',spl_Applied=UTC_TIMESTAMP() WHERE spl_Hash=?");
            $stmt->bind_param('s', $hash); $ok = $stmt->execute(); $stmt->close();
        }
        if ($ok) {
            $stmt = $this->mysqli->prepare("UPDATE sysSyncSessions SET ssy_State='RECORDS_UPLOADED',ssy_Error=NULL WHERE ssy_ID=?");
            $stmt->bind_param('s', $sessionID); $ok = $stmt->execute(); $stmt->close();
        }
        mysql__end_transaction($this->mysqli, $ok, $keepAutocommit);
        if (!$ok) return $this->system->getError();
        return ['status' => HEURIST_OK, 'data' => ['recordsApplied' => count($records), 'payloadHash' => $hash, 'replayed' => false]];
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
