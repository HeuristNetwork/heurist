<?php
/**
* BookmarkRepository.php - Reads of bookmark rows (usrBookmarks)
*
* A bookmark is the personal data of a user on a record: rating, notes and a
* private note (plan 20, §10.9). One bookmark per record and user (the unique key
* of the table). The rows come with the title of the bookmarked record.
*
* @project     Heurist academic knowledge management system
* @package     Records\UserData
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\UserData;

use Heurist\Database\DatabaseInterface;
use Heurist\Records\Store\RecordSchema;

/** Bookmark rows. */
final class BookmarkRepository
{
    /** Columns of a bookmark row (the legacy table). */
    public const COLUMNS = array('bkm_ID', 'bkm_Added', 'bkm_Modified', 'bkm_UGrpID', 'bkm_PwdReminder', 'bkm_RecID',
        'bkm_Rating', 'bkm_AddedByImport', 'bkm_ZoteroID', 'bkm_Notes');

    private const BATCH_SIZE = 500;

    private DatabaseInterface $database;
    private RecordSchema $schema;

    public function __construct(DatabaseInterface $database, ?RecordSchema $schema = null)
    {
        $this->database = $database;
        $this->schema = $schema ?? new RecordSchema();
    }

    /** The table names (LegacyBookmarkTable writes the same table). */
    public function schema(): RecordSchema
    {
        return $this->schema;
    }

    /**
     * Bookmark rows with the title of the record (rec_Title).
     *
     * @return array<int,array<string,mixed>> bookmark id => row
     */
    public function rows(array $ids): array
    {
        $rows = array();
        foreach(array_chunk(TagRepository::ids($ids), self::BATCH_SIZE) as $chunk){
            foreach($this->database->fetchAll('SELECT b.'.implode(',b.', self::COLUMNS).',r.rec_Title FROM '
                .$this->schema->table('bookmarks').' b LEFT JOIN '.$this->schema->table('records')
                .' r ON r.rec_ID=b.bkm_RecID WHERE b.bkm_ID IN ('.implode(',', array_fill(0, count($chunk), '?')).')', $chunk) as $row){
                $rows[intval($row['bkm_ID'])] = $row;
            }
        }
        return $rows;
    }

    /** One bookmark row, or null. */
    public function row(int $id): ?array
    {
        return $this->rows(array($id))[$id] ?? null;
    }

    /** Id of the bookmark of a user on a record (0: none). */
    public function idOf(int $userId, int $recordId): int
    {
        return intval($this->database->fetchValue('SELECT bkm_ID FROM '.$this->schema->table('bookmarks')
            .' WHERE bkm_UGrpID=? AND bkm_RecID=? LIMIT 1', array($userId, $recordId), 0));
    }

    /** Whether the user has personal tags on the record (legacy: such a bookmark is not removed). */
    public function hasPersonalTags(int $userId, int $recordId): bool
    {
        return intval($this->database->fetchValue('SELECT COUNT(*) FROM '.$this->schema->table('tagLinks').' JOIN '
            .$this->schema->table('tags').' ON tag_ID=rtl_TagID WHERE rtl_RecID=? AND tag_UGrpID=?',
            array($recordId, $userId), 0)) > 0;
    }
}
