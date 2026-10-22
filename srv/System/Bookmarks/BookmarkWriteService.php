<?php
/**
* BookmarkWriteService.php - Add, change and delete bookmarks (/sys/bookmark)
*
* The write side of bookmarks in the record format (plan 20, §10.9), with the
* rules of the legacy code (DbUsrBookmarks):
* - a bookmark belongs to its user: only the user adds, changes or deletes it
*   (also not an administrator; the owner is never given by the client);
* - one bookmark per record and user: a second one is refused (409 duplicate,
*   with the id of the existing one); the record must be visible to the user and
*   is not changed later;
* - the rating is 0-5 (0 or no value: not rated);
* - the private note (PrivateInfo 2-1192) is encrypted, at most 140 characters;
*   without a key of the installation it is kept as text, with a warning;
* - a bookmark of a record with personal tags of the user is not deleted
*   (409 has_personal_tags), as legacy.
*
* @project     Heurist academic knowledge management system
* @package     System\Bookmarks
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\System\Bookmarks;

use Heurist\Database\WritableDatabaseInterface;
use Heurist\Records\Edit\RecordEditException;
use Heurist\Records\Store\RecordAccess;
use Heurist\Records\Store\RecordRepository;
use Heurist\Records\UserData\BookmarkRepository;
use Heurist\Records\UserData\LegacyBookmarkTable;
use Heurist\Runtime\RuntimeContext;
use Heurist\System\Entity\EntityTransaction;
use Heurist\System\Entity\EntityWriteService;

/** Bookmark writes. */
final class BookmarkWriteService extends EntityWriteService
{
    private WritableDatabaseInterface $database;
    private BookmarkRepository $bookmarks;
    private LegacyBookmarkTable $table;
    private BookmarkRecordMapping $mapping;
    private RecordRepository $records;

    public function __construct(
        WritableDatabaseInterface $database,
        RuntimeContext $runtime,
        BookmarkRecordMapping $mapping,
        ?RecordRepository $records = null
    ) {
        parent::__construct($runtime);
        $this->database = $database;
        $this->mapping = $mapping;
        $this->bookmarks = new BookmarkRepository($database);
        $this->table = new LegacyBookmarkTable($database, $this->bookmarks->schema());
        $this->records = $records ?? RecordRepository::shared($database);
    }

    /** {@inheritdoc} */
    protected function noun(): string
    {
        return 'bookmark';
    }

    /** {@inheritdoc} */
    protected function route(): string
    {
        return 'sys/bookmark';
    }

    /**
     * Add (POST, id 0), replace (PUT) or change (PATCH) a bookmark of the user.
     *
     * @param bool $force Write over a newer rec_Modified.
     * @param bool $dryRun Only the checks (POST /sys/bookmark/{id}/validate).
     * @return array{bookmarkId:int,created:bool,issues:array}
     * @throws RecordEditException
     */
    public function save(string $method, int $bookmarkId, ?array $body, bool $force = false, bool $dryRun = false): array
    {
        $this->assertAccount('Log in to bookmark records');
        $input = $this->input($this->unwrap($body), $bookmarkId);
        $this->assertMethod($method, $bookmarkId);
        if($input->has('rec_OwnerUGrpID') && $input->intHeader('rec_OwnerUGrpID') !== $this->runtime->userId){
            throw RecordEditException::invalid('A bookmark belongs to the user who adds it');
        }
        $columns = $this->mapping->writableColumns($input->details());
        $row = null;
        if($bookmarkId > 0){
            $row = $this->existing($bookmarkId);
            $this->assertNotChanged($input, (string)$row['bkm_Modified'], $force);
            if($method === 'PUT'){ $columns += array_fill_keys($this->mapping->writableColumnNames(), null); }
        }
        $issues = array();

        // the record: required, visible, one bookmark per record and user, not changed later
        $recordId = intval(array_key_exists('bkm_RecID', $columns) ? $columns['bkm_RecID'] : ($row['bkm_RecID'] ?? 0));
        if($row !== null && $recordId !== intval($row['bkm_RecID'])){
            throw RecordEditException::invalid('The record of a bookmark cannot be changed');
        }
        if($row === null){
            if($recordId < 1){ throw RecordEditException::invalid('The bookmarked record (field 2-13) is required'); }
            if(empty($this->records->visibleIds(array($recordId), RecordAccess::fromRuntime($this->runtime)))){
                throw RecordEditException::notFound('Record '.$recordId.' not found');
            }
            $same = $this->bookmarks->idOf($this->runtime->userId, $recordId);
            if($same > 0){
                throw new RecordEditException(409, 'duplicate', 'The record is bookmarked already', array(), array('existing' => $same));
            }
            $columns['bkm_RecID'] = $recordId;
        }
        if(array_key_exists('bkm_Rating', $columns)){ $columns['bkm_Rating'] = $this->rating($columns['bkm_Rating']); }
        if(array_key_exists('bkm_Notes', $columns) && trim((string)$columns['bkm_Notes']) === ''){ $columns['bkm_Notes'] = null; }
        if(array_key_exists('bkm_PwdReminder', $columns)){
            $text = trim((string)$columns['bkm_PwdReminder']);
            if(mb_strlen($text) > PrivateInfoCipher::MAX_TEXT){
                throw RecordEditException::invalid('The private note has at most '.PrivateInfoCipher::MAX_TEXT.' characters');
            }
            $cipher = $this->mapping->cipher();
            if($text !== '' && !$cipher->hasKey()){
                $issues[] = $this->issue('not_encrypted', 'The private note is stored without encryption: the installation has no key');
            }
            try{
                $columns['bkm_PwdReminder'] = $text === '' ? null : $cipher->encrypt($text);
            }catch(\InvalidArgumentException $e){
                // non-Latin letters take more bytes: the encrypted note does not fit the column
                throw RecordEditException::invalid('The private note is too long to be stored encrypted; make it shorter');
            }
        }
        $now = date('Y-m-d H:i:s');
        $columns['bkm_Modified'] = $now;
        if($dryRun){ return array('bookmarkId' => $bookmarkId, 'created' => false, 'issues' => $issues); }

        if($bookmarkId < 1){
            $bookmarkId = EntityTransaction::run($this->database, function() use ($columns, $now){
                return $this->table->insert($columns + array('bkm_UGrpID' => $this->runtime->userId, 'bkm_Added' => $now,
                    'bkm_Rating' => 0, 'bkm_AddedByImport' => 0));
            });
            return array('bookmarkId' => $bookmarkId, 'created' => true, 'issues' => $issues);
        }
        EntityTransaction::run($this->database, function() use ($bookmarkId, $columns){ $this->table->update($bookmarkId, $columns); });
        return array('bookmarkId' => $bookmarkId, 'created' => false, 'issues' => $issues);
    }

    /**
     * Delete a bookmark of the user.
     *
     * @throws RecordEditException 404, 409 has_personal_tags
     */
    public function delete(int $bookmarkId): array
    {
        $this->assertAccount('Log in to bookmark records');
        $row = $this->existing($bookmarkId);
        if($this->bookmarks->hasPersonalTags($this->runtime->userId, intval($row['bkm_RecID']))){
            throw new RecordEditException(409, 'has_personal_tags',
                'The bookmark is not removed: the record has your personal tags. Remove them first');
        }
        EntityTransaction::run($this->database, function() use ($bookmarkId){ $this->table->delete($bookmarkId); });
        return array('deleted' => $bookmarkId);
    }

    /** A bookmark of the user (another user's bookmark is "not found", as in the queries). */
    private function existing(int $bookmarkId): array
    {
        $row = $this->bookmarks->row($bookmarkId);
        if($row === null || intval($row['bkm_UGrpID']) !== $this->runtime->userId){
            throw RecordEditException::notFound('Bookmark '.$bookmarkId.' not found');
        }
        return $row;
    }

    /** @throws RecordEditException 400 */
    private function rating(?string $value): int
    {
        $value = trim((string)$value);
        if($value === ''){ return 0; }
        if(!is_numeric($value) || floatval($value) != intval($value) || intval($value) < 0 || intval($value) > BookmarkRecordMapping::MAX_RATING){
            throw RecordEditException::invalid('The rating is a whole number from 0 to '.BookmarkRecordMapping::MAX_RATING);
        }
        return intval($value);
    }
}
