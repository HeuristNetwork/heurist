<?php

namespace hserv\synchronisation;

use hserv\structure\ConceptCode;

/** Exports and applies the master-controlled database definitions. */
final class SyncStructurePayload
{
    private static array $columnCache = [];
    private const TABLES = [
        'recordTypeGroups' => 'defRecTypeGroups',
        'detailTypeGroups' => 'defDetailTypeGroups',
        'vocabularyGroups' => 'defVocabularyGroups',
        'ontologies' => 'defOntologies',
        'recordTypes' => 'defRecTypes',
        'detailTypes' => 'defDetailTypes'
    ];

    public static function export(\hserv\System $system): array
    {
        $mysqli = $system->getMysqli();
        ConceptCode::setSystem($system);
        $data = ['format' => 'heurist-sync-structure-1'];
        foreach (self::TABLES as $key => $table) {
            $data[$key] = self::rows($mysqli, "SELECT * FROM $table");
        }
        foreach ($data['ontologies'] as &$row) {
            $row['conceptID'] = ConceptCode::getOntologyConceptID((int)$row['ont_ID']);
        }
        unset($row);
        foreach ($data['recordTypes'] as &$row) {
            $row['conceptID'] = ConceptCode::getRecTypeConceptID((int)$row['rty_ID']);
        }
        unset($row);
        foreach ($data['detailTypes'] as &$row) {
            $row['conceptID'] = ConceptCode::getDetailTypeConceptID((int)$row['dty_ID']);
        }
        unset($row);
        $data['recordStructure'] = [];
        foreach (self::rows($mysqli, 'SELECT * FROM defRecStructure') as $row) {
            $row['recordTypeConceptID'] = ConceptCode::getRecTypeConceptID((int)$row['rst_RecTypeID']);
            $row['detailTypeConceptID'] = ConceptCode::getDetailTypeConceptID((int)$row['rst_DetailTypeID']);
            $data['recordStructure'][] = $row;
        }
        $data['relationshipConstraints'] = self::rows($mysqli, 'SELECT * FROM defRelationshipConstraints');
        $data['termLinks'] = self::rows($mysqli, 'SELECT trl_ParentID,trl_TermID FROM defTermsLinks');
        $data['hash'] = hash('sha256', json_encode($data, JSON_UNESCAPED_SLASHES));
        return $data;
    }

    public static function applyBase(\hserv\System $system, array $payload): array
    {
        if (($payload['format'] ?? '') !== 'heurist-sync-structure-1') {
            return $system->addError(HEURIST_INVALID_REQUEST, 'The master returned an invalid structure snapshot.');
        }
        $expectedHash = (string)($payload['hash'] ?? '');
        $hashPayload = $payload;
        unset($hashPayload['hash']);
        if ($expectedHash === '' || !hash_equals($expectedHash,
            hash('sha256', json_encode($hashPayload, JSON_UNESCAPED_SLASHES)))) {
            return $system->addError(HEURIST_INVALID_REQUEST, 'The master structure snapshot failed its integrity check.');
        }
        $mysqli = $system->getMysqli();
        ConceptCode::setSystem($system);
        $maps = [];
        foreach ([
            ['recordTypeGroups', 'defRecTypeGroups', 'rtg_ID', 'rtg_Name'],
            ['detailTypeGroups', 'defDetailTypeGroups', 'dtg_ID', 'dtg_Name'],
            ['vocabularyGroups', 'defVocabularyGroups', 'vcg_ID', 'vcg_Name']
        ] as $definition) {
            [$key, $table, $idColumn, $nameColumn] = $definition;
            $maps[$key] = self::applyNamedRows($mysqli, $table, $idColumn, $nameColumn, $payload[$key] ?? []);
            if ($maps[$key] === false) return $system->addError(HEURIST_DB_ERROR, "Unable to synchronise $table.", $mysqli->error);
        }

        $maps['ontologies'] = self::applyConceptRows(
            $mysqli, 'defOntologies', 'ont_ID', 'ont_', $payload['ontologies'] ?? [],
            static fn($concept) => ConceptCode::getOntologyLocalID($concept)
        );
        $maps['recordTypes'] = self::applyConceptRows(
            $mysqli, 'defRecTypes', 'rty_ID', 'rty_', $payload['recordTypes'] ?? [],
            static fn($concept) => ConceptCode::getRecTypeLocalID($concept),
            ['rty_RecTypeGroupID' => $maps['recordTypeGroups']]
        );
        $maps['detailTypes'] = self::applyConceptRows(
            $mysqli, 'defDetailTypes', 'dty_ID', 'dty_', $payload['detailTypes'] ?? [],
            static fn($concept) => ConceptCode::getDetailTypeLocalID($concept),
            ['dty_DetailTypeGroupID' => $maps['detailTypeGroups']]
        );
        if ($maps['ontologies'] === false || $maps['recordTypes'] === false || $maps['detailTypes'] === false) {
            return $system->addError(HEURIST_DB_ERROR, 'Unable to synchronise master definitions.', $mysqli->error);
        }
        return ['status' => HEURIST_OK, 'data' => ['maps' => $maps]];
    }

    public static function applyStructure(\hserv\System $system, array $payload, array $maps): array
    {
        $mysqli = $system->getMysqli();
        self::applyReferenceMappings($mysqli, $payload, $maps);
        $applied = 0;
        foreach (($payload['recordStructure'] ?? []) as $row) {
            $rtyID = (int)ConceptCode::getRecTypeLocalID((string)($row['recordTypeConceptID'] ?? ''));
            $dtyID = (int)ConceptCode::getDetailTypeLocalID((string)($row['detailTypeConceptID'] ?? ''));
            if ($rtyID < 1 || $dtyID < 1) {
                return $system->addError(HEURIST_INVALID_REQUEST, 'A master record structure refers to an unresolved definition.');
            }
            $localID = (int)mysql__select_value($mysqli,
                'SELECT rst_ID FROM defRecStructure WHERE rst_RecTypeID=? AND rst_DetailTypeID=?',
                ['ii', $rtyID, $dtyID]);
            $row['rst_RecTypeID'] = $rtyID;
            $row['rst_DetailTypeID'] = $dtyID;
            if (!empty($row['rst_DisplayDetailTypeGroupID'])) {
                $row['rst_DisplayDetailTypeGroupID'] = $maps['detailTypeGroups'][(int)$row['rst_DisplayDetailTypeGroupID']] ?? null;
            }
            foreach (['rst_CalcFieldMask'] as $column) {
                if (isset($row[$column])) $row[$column] = self::remapNumbers($row[$column], $maps['detailTypes'] ?? []);
            }
            foreach (['rst_FilteredJsonTermIDTree', 'rst_TermIDTreeNonSelectableIDs'] as $column) {
                if (isset($row[$column])) $row[$column] = self::remapNumbers($row[$column], $maps['terms'] ?? []);
            }
            if (isset($row['rst_PtrFilteredIDs'])) {
                $row['rst_PtrFilteredIDs'] = self::remapNumbers($row['rst_PtrFilteredIDs'], $maps['recordTypes'] ?? []);
            }
            unset($row['recordTypeConceptID'], $row['detailTypeConceptID'], $row['rst_ID'], $row['rst_Modified']);
            if (!self::writeRow($mysqli, 'defRecStructure', 'rst_ID', $localID, $row)) {
                return $system->addError(HEURIST_DB_ERROR, 'Unable to synchronise a master record structure.', $mysqli->error);
            }
            $applied++;
        }
        foreach (($payload['relationshipConstraints'] ?? []) as $row) {
            $sourceID = !empty($row['rcs_SourceRectypeID'])
                ? ($maps['recordTypes'][(int)$row['rcs_SourceRectypeID']] ?? null) : null;
            $targetID = !empty($row['rcs_TargetRectypeID'])
                ? ($maps['recordTypes'][(int)$row['rcs_TargetRectypeID']] ?? null) : null;
            $termID = !empty($row['rcs_TermID']) ? ($maps['terms'][(int)$row['rcs_TermID']] ?? null) : null;
            $localID = (int)mysql__select_value($mysqli,
                'SELECT rcs_ID FROM defRelationshipConstraints WHERE rcs_SourceRectypeID<=>? '
                .'AND rcs_TargetRectypeID<=>? AND rcs_TermID<=>? LIMIT 1',
                ['iii', $sourceID, $targetID, $termID]);
            $row['rcs_SourceRectypeID'] = $sourceID;
            $row['rcs_TargetRectypeID'] = $targetID;
            $row['rcs_TermID'] = $termID;
            unset($row['rcs_ID'], $row['rcs_Modified']);
            if (!self::writeRow($mysqli, 'defRelationshipConstraints', 'rcs_ID', $localID, $row)) {
                return $system->addError(HEURIST_DB_ERROR, 'Unable to synchronise a relationship constraint.', $mysqli->error);
            }
            $applied++;
        }
        foreach (($payload['termLinks'] ?? []) as $row) {
            $parentID = $maps['terms'][(int)($row['trl_ParentID'] ?? 0)] ?? 0;
            $termID = $maps['terms'][(int)($row['trl_TermID'] ?? 0)] ?? 0;
            if ($parentID > 0 && $termID > 0) {
                $mysqli->query('INSERT IGNORE INTO defTermsLinks (trl_ParentID,trl_TermID) VALUES ('.
                    (int)$parentID.','.(int)$termID.')');
            }
        }
        return ['status' => HEURIST_OK, 'data' => ['definitionsApplied' => $applied]];
    }

    private static function applyReferenceMappings(\mysqli $mysqli, array $payload, array $maps): void
    {
        foreach (($payload['recordTypes'] ?? []) as $row) {
            $localID = $maps['recordTypes'][(int)($row['rty_ID'] ?? 0)] ?? 0;
            if ($localID < 1) continue;
            $updates = [];
            if (isset($row['rty_CanonicalTitleMask'])) {
                $updates['rty_CanonicalTitleMask'] = self::remapNumbers($row['rty_CanonicalTitleMask'], $maps['detailTypes'] ?? []);
            }
            if (isset($row['rty_RecTypeModelIDs'])) {
                $updates['rty_RecTypeModelIDs'] = self::remapNumbers($row['rty_RecTypeModelIDs'], $maps['recordTypes'] ?? []);
            }
            if ($updates) {
                $parts = [];
                foreach ($updates as $column => $value) $parts[] = '`'.$column.'`='.self::literal($mysqli, $value);
                $mysqli->query('UPDATE defRecTypes SET '.implode(',', $parts).' WHERE rty_ID='.(int)$localID);
            }
        }
        foreach (($payload['detailTypes'] ?? []) as $row) {
            $localID = $maps['detailTypes'][(int)($row['dty_ID'] ?? 0)] ?? 0;
            if ($localID < 1) continue;
            $updates = [];
            foreach (['dty_JsonTermIDTree', 'dty_TermIDTreeNonSelectableIDs'] as $column) {
                if (isset($row[$column])) $updates[$column] = self::remapNumbers($row[$column], $maps['terms'] ?? []);
            }
            if (isset($row['dty_PtrTargetRectypeIDs'])) {
                $updates['dty_PtrTargetRectypeIDs'] = self::remapNumbers($row['dty_PtrTargetRectypeIDs'], $maps['recordTypes'] ?? []);
            }
            if (!empty($row['dty_FieldSetRectypeID'])) {
                $updates['dty_FieldSetRectypeID'] = $maps['recordTypes'][(int)$row['dty_FieldSetRectypeID']] ?? null;
            }
            if ($updates) {
                $parts = [];
                foreach ($updates as $column => $value) $parts[] = '`'.$column.'`='.self::literal($mysqli, $value);
                $mysqli->query('UPDATE defDetailTypes SET '.implode(',', $parts).' WHERE dty_ID='.(int)$localID);
            }
        }
    }

    private static function remapNumbers($value, array $map)
    {
        if ($value === null || $value === '' || !$map) return $value;
        return preg_replace_callback('/(?<![A-Za-z0-9])([0-9]+)(?![A-Za-z0-9])/',
            static fn($match) => isset($map[(int)$match[1]]) ? (string)$map[(int)$match[1]] : $match[1],
            (string)$value);
    }

    private static function applyNamedRows(\mysqli $mysqli, string $table, string $idColumn,
        string $nameColumn, array $rows)
    {
        $map = [];
        foreach ($rows as $row) {
            $sourceID = (int)($row[$idColumn] ?? 0);
            $name = (string)($row[$nameColumn] ?? '');
            if ($sourceID < 1 || $name === '') return false;
            $localID = (int)mysql__select_value($mysqli,
                "SELECT `$idColumn` FROM `$table` WHERE `$nameColumn`=?", ['s', $name]);
            unset($row[$idColumn]);
            foreach (array_keys($row) as $column) if (substr($column, -9) === '_Modified') unset($row[$column]);
            if (!self::writeRow($mysqli, $table, $idColumn, $localID, $row)) return false;
            if ($localID < 1) $localID = (int)$mysqli->insert_id;
            $map[$sourceID] = $localID;
        }
        return $map;
    }

    private static function applyConceptRows(\mysqli $mysqli, string $table, string $idColumn,
        string $prefix, array $rows, callable $resolver, array $foreignMaps = [])
    {
        $map = [];
        foreach ($rows as $row) {
            $sourceID = (int)($row[$idColumn] ?? 0);
            $concept = (string)($row['conceptID'] ?? '');
            if ($sourceID < 1 || $concept === '') return false;
            $localID = (int)$resolver($concept);
            $resolvedByConcept = $localID > 0;
            if (!$resolvedByConcept) {
                $nameColumn = $prefix === 'ont_' ? 'ont_ShortName' : $prefix.'Name';
                $candidateName = mysql__select_value($mysqli,
                    "SELECT `$nameColumn` FROM `$table` WHERE `$idColumn`=?", ['i', $sourceID]);
                if (is_string($candidateName) && hash_equals($candidateName, (string)($row[$nameColumn] ?? ''))) {
                    $localID = $sourceID;
                }
            }
            foreach ($foreignMaps as $column => $foreignMap) {
                if (isset($row[$column])) $row[$column] = $foreignMap[(int)$row[$column]] ?? $row[$column];
            }
            unset($row[$idColumn], $row['conceptID'], $row[$prefix.'Modified']);
            if (!$resolvedByConcept) {
                $parts = explode('-', $concept, 2);
                if (count($parts) !== 2) return false;
                $row[$prefix.'OriginatingDBID'] = (int)$parts[0];
                $row[$prefix.'IDInOriginatingDB'] = (int)$parts[1];
                $nameColumn = $prefix === 'ont_' ? 'ont_ShortName' : $prefix.'Name';
                $row[$prefix.'NameInOriginatingDB'] = $row[$nameColumn] ?? null;
            }
            if (!self::writeRow($mysqli, $table, $idColumn, $localID, $row)) return false;
            if ($localID < 1) $localID = (int)$mysqli->insert_id;
            $map[$sourceID] = $localID;
        }
        return $map;
    }

    private static function writeRow(\mysqli $mysqli, string $table, string $idColumn, int $localID, array $row): bool
    {
        if (!isset(self::$columnCache[$table])) {
            self::$columnCache[$table] = array_flip(mysql__select_list2($mysqli, "SHOW COLUMNS FROM `$table`"));
        }
        $available = self::$columnCache[$table];
        $row = array_intersect_key($row, $available);
        if (!$row) return false;
        $assignments = [];
        foreach ($row as $column => $value) {
            $assignments[] = '`'.$column.'`='.self::literal($mysqli, $value);
        }
        if ($localID > 0) {
            return (bool)$mysqli->query("UPDATE `$table` SET ".implode(',', $assignments)." WHERE `$idColumn`=".$localID);
        }
        return (bool)$mysqli->query("INSERT INTO `$table` SET ".implode(',', $assignments));
    }

    private static function literal(\mysqli $mysqli, $value): string
    {
        return $value === null ? 'NULL' : "'".$mysqli->real_escape_string((string)$value)."'";
    }

    private static function rows(\mysqli $mysqli, string $sql): array
    {
        $result = $mysqli->query($sql);
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) $rows[] = $row;
        if ($result) $result->close();
        return $rows;
    }
}
