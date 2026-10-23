<?php
/**
* TagWriteService.php - Add, change, delete, assign and merge tags (/sys/tag)
*
* The write side of tags in the record format (plan 20, §10.4), with the rules of
* the legacy code (DbUsrTags):
* - a tag belongs to a user or to a group; the user changes, assigns and deletes
*   the tags of their own and of their groups (the database owner all of them);
* - the text is required (at most 63 characters) and unique per owner: another
*   tag of the owner with the same text is refused (409 duplicate, with its id,
*   so that the client offers to merge);
* - a tag on records is not deleted (409 tag_in_use, with the records) unless
*   forced: then its links are removed first;
* - assign: add and remove tags on records the user can see; replace: the given
*   tags become the tags of the records among the tags the user may change; a
*   personal tag also bookmarks the record for its user;
* - merge: the records of a tag get another tag of the same owner, the first one
*   is deleted.
*
* @project     Heurist academic knowledge management system
* @package     System\Tags
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\System\Tags;

use Heurist\Database\WritableDatabaseInterface;
use Heurist\Records\Edit\RecordEditException;
use Heurist\Records\Store\RecordAccess;
use Heurist\Records\Store\RecordRepository;
use Heurist\Records\UserData\LegacyTagTable;
use Heurist\Records\UserData\TagLinkTable;
use Heurist\Records\UserData\TagRepository;
use Heurist\Runtime\RuntimeContext;
use Heurist\System\Entity\EntityTransaction;
use Heurist\System\Entity\EntityWriteService;

/** Tag writes. */
final class TagWriteService extends EntityWriteService
{
    /** At most this many records are named in the "tag_in_use" answer. */
    private const RECORDS_IN_ANSWER = 100;

    private WritableDatabaseInterface $database;
    private TagRepository $tags;
    private LegacyTagTable $table;
    private TagLinkTable $links;
    private TagRecordMapping $mapping;
    private RecordRepository $records;

    public function __construct(
        WritableDatabaseInterface $database,
        RuntimeContext $runtime,
        TagRecordMapping $mapping,
        ?TagRepository $tags = null,
        ?RecordRepository $records = null
    ) {
        parent::__construct($runtime);
        $this->database = $database;
        $this->mapping = $mapping;
        $this->tags = $tags ?? new TagRepository($database);
        $this->table = new LegacyTagTable($database, $this->tags->schema());
        $this->links = new TagLinkTable($database, $this->tags->schema());
        $this->records = $records ?? RecordRepository::shared($database);
    }

    /** {@inheritdoc} */
    protected function noun(): string
    {
        return 'tag';
    }

    /** {@inheritdoc} */
    protected function route(): string
    {
        return 'sys/tag';
    }

    /**
     * Add (POST, id 0), replace (PUT) or change (PATCH) a tag.
     *
     * @param bool $force Write over a newer rec_Modified.
     * @param bool $dryRun Only the checks (POST /sys/tag/{id}/validate).
     * @return array{tagId:int,created:bool,issues:array}
     * @throws RecordEditException
     */
    public function save(string $method, int $tagId, ?array $body, bool $force = false, bool $dryRun = false): array
    {
        $this->assertAccount('Log in to change tags');
        $input = $this->input($this->unwrap($body), $tagId);
        $this->assertMethod($method, $tagId);
        $columns = $this->mapping->writableColumns($input->details());
        $row = null;
        if($tagId > 0){
            $row = $this->existing($tagId);
            $this->assertCanChange(intval($row['tag_UGrpID']));
            $this->assertNotChanged($input, (string)$row['tag_Modified'], $force);
            if($method === 'PUT'){ $columns += array_fill_keys($this->mapping->writableColumnNames(), null); }
        }
        $owner = $input->has('rec_OwnerUGrpID') ? $input->intHeader('rec_OwnerUGrpID')
            : ($row === null ? $this->runtime->userId : intval($row['tag_UGrpID']));
        if($row === null || $owner !== intval($row['tag_UGrpID'])){
            $this->assertCanChange($owner);
            $columns['tag_UGrpID'] = $owner;
        }
        $text = array_key_exists('tag_Text', $columns) ? trim((string)$columns['tag_Text']) : trim((string)($row['tag_Text'] ?? ''));
        $this->assertText($text);
        if(array_key_exists('tag_Text', $columns)){ $columns['tag_Text'] = $text; }
        if(array_key_exists('tag_Description', $columns)){
            $description = trim((string)$columns['tag_Description']);
            if(mb_strlen($description) > TagRecordMapping::MAX_DESCRIPTION){
                throw RecordEditException::invalid('The description of a tag has at most '.TagRecordMapping::MAX_DESCRIPTION.' characters');
            }
            $columns['tag_Description'] = $description === '' ? null : $description;
        }
        $same = $this->tags->idByText($owner, $text, $tagId);
        if($same > 0){
            throw new RecordEditException(409, 'duplicate', 'The owner has a tag "'.$text.'" already', array(),
                array('existing' => $same));
        }
        $columns['tag_Modified'] = date('Y-m-d H:i:s');
        if($dryRun){ return array('tagId' => $tagId, 'created' => false, 'issues' => array()); }

        if($tagId < 1){
            $tagId = EntityTransaction::run($this->database, function() use ($columns){
                return $this->table->insert($columns + array('tag_AddedByImport' => 0));
            });
            return array('tagId' => $tagId, 'created' => true, 'issues' => array());
        }
        EntityTransaction::run($this->database, function() use ($tagId, $columns){ $this->table->update($tagId, $columns); });
        return array('tagId' => $tagId, 'created' => false, 'issues' => array());
    }

    /**
     * Delete a tag. A tag on records is refused unless forced.
     *
     * @throws RecordEditException 404, 403, 409 tag_in_use (with records and their number)
     */
    public function delete(int $tagId, bool $force = false): array
    {
        $this->assertAccount('Log in to change tags');
        $row = $this->existing($tagId);
        $this->assertCanChange(intval($row['tag_UGrpID']));
        $usage = intval($row['tag_Usage']);
        if($usage > 0 && !$force){
            throw new RecordEditException(409, 'tag_in_use', 'The tag is on '.$usage.' record(s)', array(),
                array('usage' => $usage, 'records' => $this->tags->recordIds(array($tagId), self::RECORDS_IN_ANSWER)));
        }
        $removed = EntityTransaction::run($this->database, function() use ($tagId){
            $removed = $this->links->removeTag($tagId);
            $this->table->delete($tagId);
            return $removed;
        });
        return array('deleted' => $tagId, 'removed' => $removed);
    }

    /**
     * Assign and remove tags on records (legacy batch): {"records": [ids], "add": [tag ids],
     * "remove": [tag ids], "replace": false}. With replace the tags of the records that the user
     * may change and that are not in "add" are removed.
     *
     * @return array{processed:int,added:int,removed:int,bookmarks:int}
     * @throws RecordEditException 400, 403
     */
    public function assign(?array $body): array
    {
        $this->assertAccount('Log in to change tags');
        $body = $body ?? array();
        $add = TagRepository::ids((array)($body['add'] ?? array()));
        $remove = TagRepository::ids((array)($body['remove'] ?? array()));
        $replace = filter_var($body['replace'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $records = TagRepository::ids((array)($body['records'] ?? array()));
        if(empty($records)){ throw RecordEditException::invalid('"records" gives the records to tag'); }
        if(empty($add) && empty($remove) && !$replace){ throw RecordEditException::invalid('Nothing to do: give "add" or "remove"'); }

        $owners = $this->tags->owners(array_merge($add, $remove));
        $missing = array_diff(array_merge($add, $remove), array_keys($owners));
        if(!empty($missing)){ throw RecordEditException::notFound('Tag '.implode(', ', $missing).' not found'); }
        foreach($owners as $owner){ $this->assertCanChange($owner); }
        // tags only on records the user can see (legacy did not check)
        $records = $this->records->visibleIds($records, RecordAccess::fromRuntime($this->runtime));
        if(empty($records)){ throw RecordEditException::notFound('No record to tag'); }
        if($replace){
            $remove = array_values(array_unique(array_merge($remove,
                array_diff($this->tags->tagsOfRecords($records, $this->memberOf()), $add))));
        }

        return EntityTransaction::run($this->database, function() use ($records, $add, $remove, $owners){
            $removed = $this->links->remove($records, $remove);
            $added = $this->links->assign($records, $add);
            $bookmarks = 0;
            $personal = array_filter($add, function($tagId) use ($owners){ return ($owners[$tagId] ?? 0) === $this->runtime->userId; });
            if(!empty($personal)){ $bookmarks = $this->links->bookmark($this->runtime->userId, $records); }
            return array('processed' => count($records), 'added' => $added, 'removed' => $removed, 'bookmarks' => $bookmarks);
        });
    }

    /**
     * Merge a tag into another tag of the same owner: its records get the other tag, it is deleted.
     *
     * @return array{tagId:int,merged:int,moved:int}
     * @throws RecordEditException 400, 403, 404
     */
    public function merge(int $tagId, ?array $body): array
    {
        $this->assertAccount('Log in to change tags');
        $into = intval(($body ?? array())['into'] ?? 0);
        if($into < 1 || $into === $tagId){ throw RecordEditException::invalid('"into" gives the tag to merge into'); }
        $row = $this->existing($tagId);
        $target = $this->existing($into);
        $this->assertCanChange(intval($row['tag_UGrpID']));
        if(intval($row['tag_UGrpID']) !== intval($target['tag_UGrpID'])){
            throw RecordEditException::invalid('Tags of different owners are not merged');
        }
        $moved = EntityTransaction::run($this->database, function() use ($tagId, $into){
            $moved = $this->links->move(array($tagId), $into);
            $this->table->delete($tagId);
            $this->table->update($into, array('tag_Modified' => date('Y-m-d H:i:s')));
            return $moved;
        });
        return array('tagId' => $into, 'merged' => $tagId, 'moved' => $moved);
    }

    private function existing(int $tagId): array
    {
        $row = $this->tags->row($tagId);
        if($row === null){ throw RecordEditException::notFound('Tag '.$tagId.' not found'); }
        return $row;
    }

    /** @throws RecordEditException 400 */
    private function assertText(string $text): void
    {
        if($text === ''){ throw RecordEditException::invalid('The text of a tag is required'); }
        if(mb_strlen($text) > TagRecordMapping::MAX_TEXT){
            throw RecordEditException::invalid('A tag has at most '.TagRecordMapping::MAX_TEXT.' characters');
        }
    }

    /** The user and the user's groups (owners of the tags the user may change). */
    private function memberOf(): array
    {
        return array_values(array_unique(array_merge(array($this->runtime->userId), array_map('intval', $this->runtime->groupIds))));
    }

    /**
     * The tags of the user and of the user's groups; all for the database owner (legacy
     * DbUsrTags::_validatePermission).
     *
     * @throws RecordEditException 403
     */
    private function assertCanChange(int $owner): void
    {
        if($owner < 1){ throw RecordEditException::invalid('A tag belongs to a user or a group'); }
        if($this->runtime->isDbOwner || in_array($owner, $this->memberOf(), true)){ return; }
        throw RecordEditException::denied('The tag belongs to another user or a group you are not a member of');
    }
}
