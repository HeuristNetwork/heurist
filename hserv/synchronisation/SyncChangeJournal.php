<?php

namespace hserv\synchronisation;

/**
 * Installs and queries the append-only change journal used by synchronisation.
 * Triggers are installed only for databases explicitly configured for sync.
 */
final class SyncChangeJournal
{
    private \hserv\System $system;
    private \mysqli $mysqli;
    private bool $installed = false;

    public function __construct(\hserv\System $system)
    {
        $this->system = $system;
        $this->mysqli = $system->getMysqli();
    }

    public function ensureInstalled(): bool
    {
        if ($this->installed) return true;
        $table = "CREATE TABLE IF NOT EXISTS sysSyncChanges (
            sch_ID bigint unsigned NOT NULL auto_increment,
            sch_Entity enum('record','detail','term') NOT NULL,
            sch_Action enum('create','update','delete') NOT NULL,
            sch_RecID int unsigned default NULL,
            sch_DetailID int unsigned default NULL,
            sch_DetailTypeID int unsigned default NULL,
            sch_TermID int unsigned default NULL,
            sch_ChangedAt timestamp(6) NOT NULL default CURRENT_TIMESTAMP(6),
            PRIMARY KEY (sch_ID),
            KEY sch_Record (sch_RecID,sch_ID),
            KEY sch_Term (sch_TermID,sch_ID),
            KEY sch_ChangedAt (sch_ChangedAt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Append-only semantic change journal for database synchronisation'";
        $tableExists = mysql__select_value(
            $this->mysqli,
            "SELECT TABLE_NAME FROM information_schema.TABLES "
                ."WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sysSyncChanges'"
        );
        if (!$tableExists && !$this->mysqli->query($table)) {
            $this->system->addError(
                HEURIST_DB_ERROR,
                'Unable to create synchronisation change journal.',
                $this->mysqli->error
            );
            return false;
        }

        $triggers = $this->triggerDefinitions();
        $triggerNames = array_keys($triggers);
        $quotedNames = "'".implode("','", array_map([$this->mysqli, 'real_escape_string'], $triggerNames))."'";
        $triggerCount = (int)mysql__select_value(
            $this->mysqli,
            "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() "
                ."AND TRIGGER_NAME IN ($quotedNames)"
        );
        if ($triggerCount === count($triggers)) {
            $this->installed = true;
            return true;
        }
        foreach ($triggers as $name => $sql) {
            $exists = mysql__select_value(
                $this->mysqli,
                'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME=?',
                ['s', $name]
            );
            if (!$exists && !$this->mysqli->query($sql)) {
                $this->system->addError(
                    HEURIST_DB_ERROR,
                    "Unable to install synchronisation trigger $name.",
                    $this->mysqli->error
                );
                return false;
            }
        }
        $this->installed = true;
        return true;
    }

    public function getNewRecordsBetween(int $afterChangeID, int $throughChangeID): array
    {
        if (!$this->ensureInstalled()) {
            return [];
        }
        $stmt = $this->mysqli->prepare(
            "SELECT c.sch_RecID,r.rec_RecTypeID,MIN(c.sch_ID) AS firstChangeID "
            ."FROM sysSyncChanges c JOIN Records r ON r.rec_ID=c.sch_RecID "
            ."LEFT JOIN sysSyncOutboundRecords o ON o.sor_CurrentRecID=r.rec_ID "
            ."WHERE c.sch_ID>? AND c.sch_ID<=? AND c.sch_Entity='record' AND c.sch_Action='create' "
            ."AND r.rec_FlagTemporary=0 AND o.sor_CurrentRecID IS NULL "
            ."GROUP BY c.sch_RecID,r.rec_RecTypeID ORDER BY firstChangeID"
        );
        $stmt->bind_param('ii', $afterChangeID, $throughChangeID);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'localRecordID' => (int)$row['sch_RecID'],
                'localRecordTypeID' => (int)$row['rec_RecTypeID'],
                'firstChangeID' => (int)$row['firstChangeID']
            ];
        }
        $result->close();
        $stmt->close();
        return $rows;
    }

    /** Returns already-uploaded satellite records changed since the last successful scan. */
    public function getUpdatedRecordIDsBetween(int $afterChangeID, int $throughChangeID): array
    {
        if (!$this->ensureInstalled()) return [];
        $stmt = $this->mysqli->prepare(
            "SELECT DISTINCT c.sch_RecID FROM sysSyncChanges c "
            ."JOIN sysSyncOutboundRecords o ON o.sor_CurrentRecID=c.sch_RecID AND o.sor_Status='UPLOADED' "
            ."JOIN Records r ON r.rec_ID=c.sch_RecID AND r.rec_FlagTemporary=0 "
            ."WHERE c.sch_ID>? AND c.sch_ID<=? AND (c.sch_Entity='detail' "
            ."OR (c.sch_Entity='record' AND c.sch_Action='update')) ORDER BY c.sch_RecID"
        );
        $stmt->bind_param('ii', $afterChangeID, $throughChangeID);
        $stmt->execute();
        $result = $stmt->get_result();
        $ids = [];
        while ($row = $result->fetch_row()) $ids[] = (int)$row[0];
        $result->close();
        $stmt->close();
        return $ids;
    }

    public function latestChangeID(): int
    {
        return (int)mysql__select_value($this->mysqli, 'SELECT COALESCE(MAX(sch_ID),0) FROM sysSyncChanges');
    }

    /**
     * On a satellite's first sync, journal records which pre-date trigger
     * installation. Existing create events and previously allocated records
     * are excluded, making this safe to repeat after an interruption.
     */
    public function seedExistingRecords()
    {
        if (!$this->ensureInstalled()) {
            return false;
        }
        $sql = "INSERT INTO sysSyncChanges(sch_Entity,sch_Action,sch_RecID) "
            ."SELECT 'record','create',r.rec_ID FROM Records r "
            ."LEFT JOIN sysSyncOutboundRecords o ON o.sor_CurrentRecID=r.rec_ID "
            ."LEFT JOIN sysSyncChanges c ON c.sch_RecID=r.rec_ID "
                ."AND c.sch_Entity='record' AND c.sch_Action='create' "
            ."WHERE r.rec_FlagTemporary=0 AND o.sor_CurrentRecID IS NULL AND c.sch_ID IS NULL";
        if (!$this->mysqli->query($sql)) {
            $this->system->addError(
                HEURIST_ACTION_BLOCKED,
                'Unable to inventory records which existed before satellite synchronisation was configured.',
                $this->mysqli->error
            );
            return false;
        }
        return $this->mysqli->affected_rows;
    }

    private function triggerDefinitions(): array
    {
        $guard = 'COALESCE(@HEURIST_SYNC_APPLY,0)=0';
        return [
            'heurist_sync_records_ai' => "CREATE TRIGGER heurist_sync_records_ai AFTER INSERT ON Records FOR EACH ROW "
                ."INSERT INTO sysSyncChanges(sch_Entity,sch_Action,sch_RecID) "
                ."SELECT 'record','create',NEW.rec_ID WHERE $guard AND NEW.rec_FlagTemporary=0",
            'heurist_sync_records_au' => "CREATE TRIGGER heurist_sync_records_au AFTER UPDATE ON Records FOR EACH ROW "
                ."INSERT INTO sysSyncChanges(sch_Entity,sch_Action,sch_RecID) "
                ."SELECT 'record',IF(OLD.rec_FlagTemporary=1,'create','update'),NEW.rec_ID "
                ."WHERE $guard AND NEW.rec_FlagTemporary=0",
            'heurist_sync_records_ad' => "CREATE TRIGGER heurist_sync_records_ad AFTER DELETE ON Records FOR EACH ROW "
                ."INSERT INTO sysSyncChanges(sch_Entity,sch_Action,sch_RecID) "
                ."SELECT 'record','delete',OLD.rec_ID WHERE $guard AND OLD.rec_FlagTemporary=0",
            'heurist_sync_details_ai' => "CREATE TRIGGER heurist_sync_details_ai AFTER INSERT ON recDetails FOR EACH ROW "
                ."INSERT INTO sysSyncChanges(sch_Entity,sch_Action,sch_RecID,sch_DetailID,sch_DetailTypeID) "
                ."SELECT 'detail','create',NEW.dtl_RecID,NEW.dtl_ID,NEW.dtl_DetailTypeID WHERE $guard",
            'heurist_sync_details_au' => "CREATE TRIGGER heurist_sync_details_au AFTER UPDATE ON recDetails FOR EACH ROW "
                ."INSERT INTO sysSyncChanges(sch_Entity,sch_Action,sch_RecID,sch_DetailID,sch_DetailTypeID) "
                ."SELECT 'detail','update',NEW.dtl_RecID,NEW.dtl_ID,NEW.dtl_DetailTypeID WHERE $guard",
            'heurist_sync_details_ad' => "CREATE TRIGGER heurist_sync_details_ad AFTER DELETE ON recDetails FOR EACH ROW "
                ."INSERT INTO sysSyncChanges(sch_Entity,sch_Action,sch_RecID,sch_DetailID,sch_DetailTypeID) "
                ."SELECT 'detail','delete',OLD.dtl_RecID,OLD.dtl_ID,OLD.dtl_DetailTypeID WHERE $guard",
            'heurist_sync_terms_ai' => "CREATE TRIGGER heurist_sync_terms_ai AFTER INSERT ON defTerms FOR EACH ROW "
                ."INSERT INTO sysSyncChanges(sch_Entity,sch_Action,sch_TermID) "
                ."SELECT 'term','create',NEW.trm_ID WHERE $guard",
            'heurist_sync_terms_au' => "CREATE TRIGGER heurist_sync_terms_au AFTER UPDATE ON defTerms FOR EACH ROW "
                ."INSERT INTO sysSyncChanges(sch_Entity,sch_Action,sch_TermID) "
                ."SELECT 'term','update',NEW.trm_ID WHERE $guard",
            'heurist_sync_terms_ad' => "CREATE TRIGGER heurist_sync_terms_ad AFTER DELETE ON defTerms FOR EACH ROW "
                ."INSERT INTO sysSyncChanges(sch_Entity,sch_Action,sch_TermID) "
                ."SELECT 'term','delete',OLD.trm_ID WHERE $guard"
        ];
    }
}
