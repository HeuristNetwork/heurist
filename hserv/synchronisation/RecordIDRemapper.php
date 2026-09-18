<?php

namespace hserv\synchronisation;

/**
 * Transactionally replaces record IDs and every audited record-ID reference.
 *
 * A two-phase old -> staging -> master transformation safely handles mappings
 * whose old and new ID ranges overlap. Unknown ID-bearing columns cause the
 * operation to stop so new schema additions cannot silently corrupt a remap.
 */
final class RecordIDRemapper
{
    private \hserv\System $system;
    private \mysqli $mysqli;

    private const REFERENCE_COLUMNS = [
        'recDetails' => ['dtl_RecID'],
        'recForwarding' => ['rfw_OldRecID', 'rfw_NewRecID'],
        'recLinks' => ['rl_SourceID', 'rl_TargetID', 'rl_RelationID'],
        'recDetailsDateIndex' => ['rdi_RecID'],
        'recThreadedComments' => ['cmt_RecID'],
        'sysArchive' => ['arc_RecID'],
        'usrBookmarks' => ['bkm_RecID'],
        'usrRecTagLinks' => ['rtl_RecID'],
        'usrRecPermissions' => ['rcp_RecID'],
        'usrReminders' => ['rem_RecID'],
        'usrWorkingSubsets' => ['wss_RecID'],
        'sysSyncChanges' => ['sch_RecID'],
        'sysSyncOutboundRecords' => ['sor_CurrentRecID']
    ];

    public function __construct(\hserv\System $system)
    {
        $this->system = $system;
        $this->mysqli = $system->getMysqli();
    }

    public function remap(array $mapping): array
    {
        $mapping = $this->normaliseMapping($mapping);
        if ($mapping === false) {
            return $this->system->getError();
        }
        if (!$this->auditSchema()) {
            return $this->system->getError();
        }
        if (!$this->validateTargets($mapping)) {
            return $this->system->getError();
        }

        $maxID = max(array_merge(array_keys($mapping), array_values($mapping), [
            (int)mysql__select_value($this->mysqli, 'SELECT COALESCE(MAX(rec_ID),0) FROM Records')
        ]));
        if ($maxID > 4294967295 - count($mapping) - 1) {
            return $this->system->addError(HEURIST_ACTION_BLOCKED, 'There is no safe staging ID range for this remapping.');
        }
        $staging = [];
        $next = $maxID + 1;
        foreach ($mapping as $old => $new) {
            $staging[$old] = $next++;
        }

        $keepAutocommit = mysql__begin_transaction($this->mysqli);
        $this->mysqli->query('SET @HEURIST_SYNC_APPLY=1');
        $ok = $this->applyMapping($staging);
        if ($ok) {
            $second = [];
            foreach ($staging as $old => $stage) {
                $second[$stage] = $mapping[$old];
            }
            $ok = $this->applyMapping($second);
        }
        mysql__end_transaction($this->mysqli, $ok, $keepAutocommit);
        $this->mysqli->query('SET @HEURIST_SYNC_APPLY=NULL');

        if (!$ok) {
            if (!$this->system->getError()) {
                $this->system->addError(HEURIST_DB_ERROR, 'Record-ID remapping failed and was rolled back.', $this->mysqli->error);
            }
            return $this->system->getError();
        }
        return ['status' => HEURIST_OK, 'data' => ['remapped' => count($mapping), 'mapping' => $mapping]];
    }

    private function normaliseMapping(array $input)
    {
        $mapping = [];
        foreach ($input as $old => $new) {
            if (is_array($new)) {
                $old = $new['localRecordID'] ?? 0;
                $new = $new['masterRecordID'] ?? 0;
            }
            $old = (int)$old;
            $new = (int)$new;
            if ($old < 1 || $new < 1) {
                $this->system->addError(HEURIST_INVALID_REQUEST, 'Record-ID mappings must contain positive integer IDs.');
                return false;
            }
            if (isset($mapping[$old]) || in_array($new, $mapping, true)) {
                $this->system->addError(HEURIST_INVALID_REQUEST, 'Record-ID mappings must be one-to-one.');
                return false;
            }
            if ($old !== $new) {
                $mapping[$old] = $new;
            }
        }
        return $mapping;
    }

    private function validateTargets(array $mapping): bool
    {
        if (!$mapping) {
            return true;
        }
        $oldIDs = array_keys($mapping);
        $newIDs = array_values($mapping);
        $existingOld = mysql__select_list2(
            $this->mysqli,
            'SELECT rec_ID FROM Records WHERE rec_ID IN ('.implode(',', $oldIDs).')',
            'intval'
        );
        if (count($existingOld) !== count($oldIDs)) {
            $this->system->addError(HEURIST_INVALID_REQUEST, 'One or more local records to be remapped do not exist.');
            return false;
        }
        $occupied = mysql__select_list2(
            $this->mysqli,
            'SELECT rec_ID FROM Records WHERE rec_ID IN ('.implode(',', $newIDs).') '
                .'AND rec_ID NOT IN ('.implode(',', $oldIDs).')',
            'intval'
        );
        if ($occupied) {
            $this->system->addError(
                HEURIST_ACTION_BLOCKED,
                'Master record IDs already occupied by unrelated local records: '.implode(', ', $occupied)
            );
            return false;
        }
        return true;
    }

    private function auditSchema(): bool
    {
        $known = [];
        foreach (self::REFERENCE_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $known[strtolower($table.'.'.$column)] = true;
            }
        }
        $known['records.rec_id'] = true;
        // Synchronisation provenance columns intentionally retain the original
        // and canonical IDs; they are audited here but are not live references.
        $known['syssyncrecordmap.srm_satellitelocalrecid'] = true;
        $known['syssyncrecordmap.srm_masterrecid'] = true;
        $known['syssyncoutboundrecords.sor_originallocalrecid'] = true;
        $known['syssyncoutboundrecords.sor_masterrecid'] = true;

        $query = "SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS "
            ."WHERE TABLE_SCHEMA=DATABASE() AND ("
            ."LOWER(COLUMN_NAME)='rec_id' OR LOWER(COLUMN_NAME) LIKE '%recid' "
            ."OR LOWER(COLUMN_NAME) IN ('rl_sourceid','rl_targetid','rl_relationid'))";
        $res = $this->mysqli->query($query);
        $unknown = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $key = strtolower($row['TABLE_NAME'].'.'.$row['COLUMN_NAME']);
                if (!isset($known[$key])) {
                    $unknown[] = $row['TABLE_NAME'].'.'.$row['COLUMN_NAME'];
                }
            }
            $res->close();
        }
        if ($unknown) {
            $this->system->addError(
                HEURIST_ACTION_BLOCKED,
                'ID remapping stopped because unaudited record-ID columns exist: '.implode(', ', $unknown)
            );
            return false;
        }
        return true;
    }

    private function applyMapping(array $mapping): bool
    {
        foreach ($mapping as $old => $new) {
            // Record-pointer values are stored as text and must be changed before the primary key.
            $stmt = $this->mysqli->prepare(
                "UPDATE recDetails d JOIN defDetailTypes t ON t.dty_ID=d.dtl_DetailTypeID "
                ."SET d.dtl_Value=? WHERE t.dty_Type='resource' AND d.dtl_Value=?"
            );
            $newText = (string)$new;
            $oldText = (string)$old;
            $stmt->bind_param('ss', $newText, $oldText);
            if (!$stmt->execute()) {
                $stmt->close();
                return false;
            }
            $stmt->close();

            foreach (self::REFERENCE_COLUMNS as $table => $columns) {
                if (!$this->tableExists($table)) {
                    continue;
                }
                foreach ($columns as $column) {
                    $query = "UPDATE `$table` SET `$column`=$new WHERE `$column`=$old";
                    if (!$this->mysqli->query($query)) {
                        return false;
                    }
                }
            }
            if (!$this->mysqli->query("UPDATE Records SET rec_ID=$new WHERE rec_ID=$old")) {
                return false;
            }
        }
        return true;
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        if (!array_key_exists($table, $cache)) {
            $stmt = $this->mysqli->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
            );
            $stmt->bind_param('s', $table);
            $stmt->execute();
            $cache[$table] = (bool)$stmt->get_result()->fetch_row();
            $stmt->close();
        }
        return $cache[$table];
    }
}
