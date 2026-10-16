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
use Heurist\Records\Store\RecordStore;
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
        $records = $this->store->records();
        $links = $records->links(array($recordId), array('side'=>'target', 'kind'=>'pointer', 'order'=>'id'));
        $links = array_values(array_filter($links, static function(array $link) use ($recordId): bool {
            return $link[2] !== $recordId;
        }));
        $sources = $records->headers(array_column($links, 2));
        $pointers = array();
        foreach($links as $link){
            $source = $sources[$link[2]] ?? null;
            if($source === null){ continue; }
            $pointers[] = array(
                'rec_ID' => (string)$link[2],
                'rec_RecTypeID' => (string)$source['rec_RecTypeID'],
                'rec_Title' => (string)$source['rec_Title'],
                'dty_ID' => (string)$link[4]
            );
        }
        $relationships = array();
        foreach(array('source', 'target') as $side){
            foreach($records->links(array($recordId), array('side'=>$side, 'kind'=>'relationship')) as $link){
                $key = $link[6].':'.$link[2].':'.$link[3].':'.$link[5];
                if(isset($relationships[$key])){ continue; }
                $relationships[$key] = array(
                    'rec_ID' => (string)$link[6],
                    'source' => (string)$link[2],
                    'target' => (string)$link[3],
                    'relationType' => $link[5] > 0 ? (string)$link[5] : null
                );
            }
        }
        return array('pointers'=>$pointers, 'relationships'=>array_values($relationships));
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
        $header = $recordId > 0 ? $this->store->records()->header($recordId) : null;
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
