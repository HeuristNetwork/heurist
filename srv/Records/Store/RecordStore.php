<?php
/**
* RecordStore.php - All SQL that writes records
*
* The one place with INSERT/UPDATE/DELETE of record headers, detail values and
* record permissions. Table names come from RecordSchema, so system records can
* later move to their own tables (sysRecords/sysRecDetails) without changing the
* services. Reads are RecordRepository on the same schema (records()).
* Links (recLinks), the date index and the archive (sysArchive) are kept by
* database triggers; setUser() gives the archive its user.
*
* @project     Heurist academic knowledge management system
* @package     Records\Store
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Store;

use Heurist\Database\WritableDatabaseInterface;

/** Record writes on one set of record tables. */
final class RecordStore
{
    private WritableDatabaseInterface $database;
    private RecordSchema $schema;
    private RecordRepository $repository;
    private string $records;
    private string $details;
    private string $permissions;

    /**
     * @param WritableDatabaseInterface $database
     * @param RecordSchema|null $schema Record tables (null = the current tables).
     */
    public function __construct(WritableDatabaseInterface $database, ?RecordSchema $schema = null)
    {
        $this->database = $database;
        $this->schema = $schema ?? new RecordSchema();
        $this->repository = $schema === null ? RecordRepository::shared($database) : new RecordRepository($database, $schema);
        $this->records = $this->schema->records();
        $this->details = $this->schema->details();
        $this->permissions = $this->schema->permissions();
    }

    public function database(): WritableDatabaseInterface
    {
        return $this->database;
    }

    /** Reads on the same tables. */
    public function records(): RecordRepository
    {
        return $this->repository;
    }

    /** User of the following changes for the archive triggers (@logged_in_user_id). */
    public function setUser(int $userId): void
    {
        $this->database->execute('SET @logged_in_user_id = '.max(0, $userId));
    }

    /**
     * Insert a record header and return its id.
     *
     * @param array{rectype:int,owner:int,visibility:string,url:?string,scratchPad:?string,addedBy:int} $header
     * @param int $recordId Given id (import), 0 = next auto-increment id.
     */
    public function insertRecord(array $header, int $recordId = 0): int
    {
        $columns = 'rec_AddedByUGrpID,rec_RecTypeID,rec_OwnerUGrpID,rec_NonOwnerVisibility,rec_URL,rec_ScratchPad,'
            .'rec_Added,rec_Modified,rec_AddedByImport,rec_FlagTemporary,rec_Title';
        $values = '?,?,?,?,?,?,NOW(),NOW(),0,0,""';
        $parameters = array($header['addedBy'], $header['rectype'], $header['owner'], $header['visibility'],
            $header['url'], $header['scratchPad']);
        if($recordId > 0){
            $columns = 'rec_ID,'.$columns;
            $values = '?,'.$values;
            array_unshift($parameters, $recordId);
        }
        $this->database->execute('INSERT INTO '.$this->records.' ('.$columns.') VALUES ('.$values.')', $parameters);
        return $recordId > 0 ? $recordId : $this->database->lastInsertId();
    }

    /**
     * Update a record header (rec_Modified becomes now).
     *
     * @param array{owner:int,visibility:string,url:?string,scratchPad:?string} $header
     */
    public function updateRecord(int $recordId, array $header): void
    {
        $this->database->execute(
            'UPDATE '.$this->records.' SET rec_OwnerUGrpID=?,rec_NonOwnerVisibility=?,rec_URL=?,rec_ScratchPad=?,'
            .'rec_FlagTemporary=0,rec_Modified=NOW() WHERE rec_ID=?',
            array($header['owner'], $header['visibility'], $header['url'], $header['scratchPad'], $recordId)
        );
    }

    public function setTitle(int $recordId, string $title): void
    {
        $this->database->execute('UPDATE '.$this->records.' SET rec_Title=? WHERE rec_ID=?', array($title, $recordId));
    }

    /**
     * Replace the edit and/or view groups of a record (null keeps them).
     *
     * @param array<int,int>|null $editGroups rcp_Level="edit"
     * @param array<int,int>|null $viewGroups rcp_Level="view"
     */
    public function setPermissions(int $recordId, ?array $editGroups, ?array $viewGroups): void
    {
        foreach(array('edit'=>$editGroups, 'view'=>$viewGroups) as $level => $groups){
            if($groups === null){ continue; }
            $this->database->execute(
                'DELETE FROM '.$this->permissions.' WHERE rcp_RecID=? AND rcp_Level=?', array($recordId, $level)
            );
            foreach(array_values(array_unique(array_map('intval', $groups))) as $groupId){
                if($groupId < 1){ continue; }
                $this->database->execute(
                    'INSERT INTO '.$this->permissions.' (rcp_UGrpID,rcp_RecID,rcp_Level) VALUES (?,?,?)',
                    array($groupId, $recordId, $level)
                );
            }
        }
    }

    /**
     * Delete the detail values of a record and insert the given rows.
     *
     * @param array<int,array{dty:int,value:?string,fileId:?int,geo:?string,hidden:?int}> $rows
     * @param array<int,int> $keepFieldIds Fields whose stored values stay (forbidden fields
     *        the editor does not see).
     */
    public function replaceDetails(int $recordId, array $rows, array $keepFieldIds = array()): void
    {
        $keepFieldIds = array_values(array_unique(array_map('intval', $keepFieldIds)));
        $sql = 'DELETE FROM '.$this->details.' WHERE dtl_RecID=?';
        if(!empty($keepFieldIds)){
            $sql .= ' AND dtl_DetailTypeID NOT IN ('.implode(',', array_fill(0, count($keepFieldIds), '?')).')';
        }
        $this->database->execute($sql, array_merge(array($recordId), $keepFieldIds));
        $this->insertDetails($recordId, $rows);
    }

    /** @param array<int,array{dty:int,value:?string,fileId:?int,geo:?string,hidden:?int}> $rows */
    public function insertDetails(int $recordId, array $rows): void
    {
        foreach($rows as $row){
            $this->database->execute(
                'INSERT INTO '.$this->details.' (dtl_RecID,dtl_DetailTypeID,dtl_Value,dtl_AddedByImport,'
                .'dtl_UploadedFileID,dtl_Geo,dtl_HideFromPublic) VALUES (?,?,?,0,?,ST_GeomFromText(?),?)',
                array($recordId, intval($row['dty']), $row['value'], $row['fileId'], $row['geo'], $row['hidden'])
            );
        }
    }

    /** Remove the pointer values of a source record to a target record in one field. */
    public function removePointer(int $sourceId, int $fieldId, int $targetId): int
    {
        return $this->database->execute(
            'DELETE FROM '.$this->details.' WHERE dtl_RecID=? AND dtl_DetailTypeID=? AND dtl_Value=?',
            array($sourceId, $fieldId, (string)$targetId)
        );
    }

    /**
     * Delete a record: user references (reminders, forwarding, tags, comments,
     * bookmarks), permissions, detail values and the header.
     *
     * @return int Number of bookmarks removed.
     */
    public function deleteRecord(int $recordId): int
    {
        $bookmarks = 0;
        $bookmarkTable = $this->schema->table('bookmarks');
        foreach($this->schema->references() as $table => $column){
            $count = $this->database->execute('DELETE FROM '.$table.' WHERE '.$column.'=?', array($recordId));
            if($table === $bookmarkTable){ $bookmarks = $count; }
        }
        $this->database->execute('DELETE FROM '.$this->permissions.' WHERE rcp_RecID=?', array($recordId));
        $this->database->execute('DELETE FROM '.$this->details.' WHERE dtl_RecID=?', array($recordId));
        $this->database->execute('DELETE FROM '.$this->records.' WHERE rec_ID=?', array($recordId));
        return $bookmarks;
    }

    /**
     * Copy the detail values and the permissions of a record to another record.
     */
    public function copyContent(int $sourceId, int $targetId): void
    {
        $this->database->execute(
            'INSERT INTO '.$this->details.' (dtl_RecID,dtl_DetailTypeID,dtl_Value,dtl_AddedByImport,dtl_UploadedFileID,'
            .'dtl_Geo,dtl_ValShortened,dtl_Certainty,dtl_Annotation,dtl_HideFromPublic) '
            .'SELECT ?,dtl_DetailTypeID,dtl_Value,0,dtl_UploadedFileID,dtl_Geo,dtl_ValShortened,dtl_Certainty,'
            .'dtl_Annotation,dtl_HideFromPublic FROM '.$this->details.' WHERE dtl_RecID=? ORDER BY dtl_ID',
            array($targetId, $sourceId)
        );
        $this->database->execute(
            'INSERT INTO '.$this->permissions.' (rcp_UGrpID,rcp_RecID,rcp_Level) '
            .'SELECT rcp_UGrpID,?,rcp_Level FROM '.$this->permissions.' WHERE rcp_RecID=? ORDER BY rcp_ID',
            array($targetId, $sourceId)
        );
    }

    /** Change one stored detail value (by record, field and old value). */
    public function updateValue(int $recordId, int $fieldId, string $oldValue, string $newValue): void
    {
        $this->database->execute(
            'UPDATE '.$this->details.' SET dtl_Value=? WHERE dtl_RecID=? AND dtl_DetailTypeID=? AND dtl_Value=?',
            array($newValue, $recordId, $fieldId, $oldValue)
        );
    }
}
