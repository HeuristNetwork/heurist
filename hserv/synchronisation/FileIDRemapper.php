<?php

namespace hserv\synchronisation;

/**
 * Transactionally replaces Satellite ulf_ID values with IDs allocated by the
 * Master and updates every audited reference.
 *
 * Specification invariant: files are handled like records. After allocation,
 * the same file concept has the same primary ID on Master and Satellite. The
 * concept identity (origin DB + ID in origin DB) proves sameness; the numeric
 * primary ID is changed only after the Master returns its allocation.
 */
final class FileIDRemapper
{
    private \hserv\System $system;
    private \mysqli $mysqli;

    /** All schema locations which currently store an ulf_ID reference. */
    private const REFERENCES = [
        'recDetails' => ['dtl_UploadedFileID']
    ];

    public function __construct(\hserv\System $system)
    {
        $this->system = $system;
        $this->mysqli = $system->getMysqli();
    }

    /**
     * Apply old-ID => Master-ID mappings in one transaction.
     *
     * Every source is first moved into a disjoint staging range. This makes
     * swaps and overlapping ID ranges safe. If any target belongs to a file
     * concept outside the mapping, nothing is changed.
     */
    public function remap(array $input): array
    {
        $rows = $this->normalise($input);
        if ($rows === false) return $this->system->getError();
        if (!$rows) return ['status' => HEURIST_OK, 'data' => ['remapped' => 0]];
        if (!$this->auditReferenceColumns()) return $this->system->getError();

        $mapping=[];
        foreach($rows as $row) if($row['old']!==$row['new']) $mapping[$row['old']]=$row['new'];

        $transaction = new SyncTransaction($this->mysqli, true);
        try {
            if($mapping){
                $oldIDs = array_keys($mapping);$newIDs = array_values($mapping);
                $occupied = mysql__select_list2($this->mysqli,
                    'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_ID IN ('.implode(',', $newIDs).') '
                    .'AND ulf_ID NOT IN ('.implode(',', $oldIDs).')', 'intval') ?: [];
                if ($occupied) throw new \RuntimeException('Master file IDs are occupied by unrelated Satellite files: '.implode(', ', $occupied).'.');

                $max = max(array_merge($oldIDs,$newIDs,[(int)mysql__select_value($this->mysqli,'SELECT COALESCE(MAX(ulf_ID),0) FROM recUploadedFiles')]));
                if($max>16777215-count($mapping)-1)throw new \RuntimeException('There is no safe temporary ID range for file remapping.');
                $staging=[];$next=$max+1;foreach($mapping as $old=>$new)$staging[$old]=$next++;

                $ok=$this->apply($staging);
                if($ok){$second=[];foreach($staging as $old=>$stage)$second[$stage]=$mapping[$old];$ok=$this->apply($second);}
                if(!$ok)throw new \RuntimeException('Unable to update a file ID or its references.');
            }
            // A legacy clone match may also replace the newly inferred origin
            // with the Master's already-established concept identity.
            foreach($rows as $row){[$origin,$originID]=$row['masterConcept'];
                $stmt=$this->mysqli->prepare('UPDATE recUploadedFiles SET ulf_OriginatingDBID=?,ulf_IDInOriginatingDB=? WHERE ulf_ID=?');
                $stmt->bind_param('iii',$origin,$originID,$row['new']);
                if(!$stmt->execute())throw new \RuntimeException('Unable to apply the Master file concept identity: '.$stmt->error);$stmt->close();}
            $ok=$transaction->finish(true);
        } finally {
            $transaction->close();
        }
        if (!$ok) return $this->system->addError(HEURIST_DB_ERROR,
            'File-ID remapping failed and was rolled back.', $this->mysqli->error);
        return ['status' => HEURIST_OK, 'data' => ['remapped' => count($mapping)]];
    }

    private function normalise(array $input)
    {
        $rows=[];$oldSeen=[];$newSeen=[];
        foreach ($input as $row) {
            $old = (int)($row['satelliteFileID'] ?? 0);
            $new = (int)($row['masterFileID'] ?? 0);
            if ($old < 1 || $new < 1) {
                $this->system->addError(HEURIST_INVALID_REQUEST, 'File mappings require positive IDs.');
                return false;
            }
            if(isset($oldSeen[$old])||isset($newSeen[$new])){
                $this->system->addError(HEURIST_INVALID_REQUEST, 'File mappings must be one-to-one.');
                return false;
            }
            if(!preg_match('/^(\d+)-(\d+)$/',(string)($row['masterConceptID']??''),$match)){
                $this->system->addError(HEURIST_INVALID_REQUEST,'A file mapping has an invalid Master concept identity.');return false;}
            $oldSeen[$old]=true;$newSeen[$new]=true;$rows[]=['old'=>$old,'new'=>$new,'masterConcept'=>[(int)$match[1],(int)$match[2]]];
        }
        return $rows;
    }

    private function apply(array $mapping): bool
    {
        foreach ($mapping as $old => $new) {
            foreach (self::REFERENCES as $table => $columns) {
                foreach ($columns as $column) {
                    if (!$this->mysqli->query("UPDATE `$table` SET `$column`=$new WHERE `$column`=$old")) return false;
                }
            }
            if (!$this->mysqli->query("UPDATE recUploadedFiles SET ulf_ID=$new WHERE ulf_ID=$old")) return false;
        }
        return true;
    }

    /** Stop if a future schema adds another uploaded-file reference unknown here. */
    private function auditReferenceColumns(): bool
    {
        $result = $this->mysqli->query("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS "
            ."WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME LIKE '%UploadedFileID%'");
        if (!$result) return false;
        $unknown = [];
        while ($row = $result->fetch_row()) {
            if (!in_array($row[1], self::REFERENCES[$row[0]] ?? [], true)) $unknown[] = $row[0].'.'.$row[1];
        }
        $result->close();
        if ($unknown) {
            $this->system->addError(HEURIST_ACTION_BLOCKED,
                'File remapping stopped because unaudited reference columns exist: '.implode(', ', $unknown).'.');
            return false;
        }
        return true;
    }
}
