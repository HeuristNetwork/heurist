<?php
/**
* RecordDeleteService.php - Delete of one record (DELETE /records/{id})
*
* Port of recordDelete() / deleteOneRecord() of hserv/records/edit/recordModify.php
* for the writable system record types:
*   - without force a record with incoming references is not deleted: the result
*     (409) lists the records that point to it (recLinks) and the relationship
*     records it is part of;
*   - with force the pointer values in the source records are removed, the
*     relationship records are deleted, then the record;
*   - user references are removed with the record (reminders, forwarding, tags,
*     comments, bookmarks, permissions).
* Child records are not handled: none of the writable types has them (plan 14).
*
* @project     Heurist academic knowledge management system
* @package     Records\Edit
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Edit;

use Heurist\Definitions\DefinitionLookup;
use Heurist\Runtime\RuntimeContext;
use Throwable;

/** Deletes one record. */
final class RecordDeleteService
{
    private RecordStore $store;
    private RecordPermissionService $permissions;
    private DefinitionLookup $definitions;
    private WritableRecordTypes $writable;
    private RuntimeContext $runtime;

    public function __construct(
        RecordStore $store,
        RecordPermissionService $permissions,
        DefinitionLookup $definitions,
        WritableRecordTypes $writable,
        RuntimeContext $runtime
    ) {
        $this->store = $store;
        $this->permissions = $permissions;
        $this->definitions = $definitions;
        $this->writable = $writable;
        $this->runtime = $runtime;
    }

    /**
     * Records that point to a record and relationships it is part of.
     *
     * @return array{pointers:array<int,array>,relationships:array<int,array>}
     */
    public function references(int $recordId): array
    {
        $database = $this->store->database();
        $pointers = array();
        foreach($database->fetchAll(
            'SELECT l.rl_SourceID,l.rl_DetailTypeID,r.rec_RecTypeID,r.rec_Title FROM recLinks l '
            .'JOIN Records r ON r.rec_ID=l.rl_SourceID '
            .'WHERE l.rl_TargetID=? AND l.rl_RelationID IS NULL AND l.rl_SourceID<>? ORDER BY l.rl_ID',
            array($recordId, $recordId)
        ) as $row){
            $pointers[] = array(
                'rec_ID' => (string)$row['rl_SourceID'],
                'rec_RecTypeID' => (string)$row['rec_RecTypeID'],
                'rec_Title' => (string)$row['rec_Title'],
                'dty_ID' => (string)$row['rl_DetailTypeID']
            );
        }
        $relationships = array();
        foreach($database->fetchAll(
            'SELECT DISTINCT rl_RelationID,rl_SourceID,rl_TargetID,rl_RelationTypeID FROM recLinks '
            .'WHERE rl_RelationID IS NOT NULL AND (rl_SourceID=? OR rl_TargetID=?)',
            array($recordId, $recordId)
        ) as $row){
            $relationships[] = array(
                'rec_ID' => (string)$row['rl_RelationID'],
                'source' => (string)$row['rl_SourceID'],
                'target' => (string)$row['rl_TargetID'],
                'relationType' => $row['rl_RelationTypeID'] === null ? null : (string)$row['rl_RelationTypeID']
            );
        }
        return array('pointers'=>$pointers, 'relationships'=>$relationships);
    }

    /**
     * Delete a record.
     *
     * @param bool $force Remove incoming pointers and relationship records too.
     * @return array{deleted:array<int,string>,pointersRemoved:int,relationshipsDeleted:int,bookmarks:int}
     * @throws RecordEditException 403, 404, 409 (references without force)
     */
    public function delete(int $recordId, bool $force = false): array
    {
        $this->permissions->assertAccount('delete');
        $header = $recordId > 0 ? $this->store->header($recordId) : null;
        if($header === null){
            throw RecordEditException::notFound('Record '.$recordId.' does not exist');
        }
        $rectype = intval($header['rec_RecTypeID']);
        if(!$this->writable->contains($rectype)){
            throw RecordEditException::denied('Records of type "'.$this->definitions->rectypeName($rectype)
                .'" cannot be deleted with this API yet');
        }
        $owners = $this->permissions->owners($recordId);
        if(!$this->permissions->canEditOwned($owners['owner'], $owners['editGroups'])){
            throw RecordEditException::denied('You cannot delete record '.$recordId
                .'; you must be the database administrator or a member of a group that owns it');
        }

        $references = $this->references($recordId);
        $count = count($references['pointers']) + count($references['relationships']);
        if($count > 0 && !$force){
            throw new RecordEditException(409, 'references',
                'Record '.$recordId.' is referenced by '.$count.' record'.($count > 1 ? 's' : '')
                .'; delete with force=1 to remove the references', array(), array('references'=>$references));
        }

        $database = $this->store->database();
        $deleted = array();
        $pointersRemoved = 0;
        $bookmarks = 0;
        $database->beginTransaction();
        try{
            $this->store->setUser($this->runtime->userId);
            foreach($references['pointers'] as $pointer){
                $pointersRemoved += $this->store->removePointer(intval($pointer['rec_ID']), intval($pointer['dty_ID']), $recordId);
            }
            foreach($references['relationships'] as $relationship){
                $relationshipId = intval($relationship['rec_ID']);
                if($relationshipId === $recordId || in_array((string)$relationshipId, $deleted, true)){ continue; }
                $bookmarks += $this->store->deleteRecord($relationshipId);
                $deleted[] = (string)$relationshipId;
            }
            $bookmarks += $this->store->deleteRecord($recordId);
            array_unshift($deleted, (string)$recordId);
            $database->commit();
        }catch(Throwable $exception){
            $database->rollback();
            throw $exception;
        }
        return array(
            'deleted' => $deleted,
            'pointersRemoved' => $pointersRemoved,
            'relationshipsDeleted' => count($deleted) - 1,
            'bookmarks' => $bookmarks
        );
    }
}
