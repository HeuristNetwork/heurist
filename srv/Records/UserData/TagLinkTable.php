<?php
/**
* TagLinkTable.php - Writes of the record links of tags (usrRecTagLinks)
*
* The batch of the legacy code (DbUsrTags::batch_action, replaceTags): assign and
* remove tags on records, move the links of tags to another tag (merge), and
* the bookmark a personal tag gives a record (as legacy: a record with a
* personal tag of a user is bookmarked by that user). The caller checks the
* permissions and gives existing, visible records.
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

use Heurist\Database\WritableDatabaseInterface;
use Heurist\Records\Store\RecordSchema;

/** Links between tags and records. */
final class TagLinkTable
{
    private const BATCH_SIZE = 500;

    private WritableDatabaseInterface $database;
    private RecordSchema $schema;

    public function __construct(WritableDatabaseInterface $database, ?RecordSchema $schema = null)
    {
        $this->database = $database;
        $this->schema = $schema ?? new RecordSchema();
    }

    /**
     * Put the tags on the records (links that exist stay).
     *
     * @return int Links added.
     */
    public function assign(array $recordIds, array $tagIds): int
    {
        $recordIds = TagRepository::ids($recordIds);
        $tagIds = TagRepository::ids($tagIds);
        if(empty($recordIds) || empty($tagIds)){ return 0; }
        $added = 0;
        foreach(array_chunk($recordIds, self::BATCH_SIZE) as $chunk){
            $values = array();
            $rows = array();
            foreach($chunk as $recordId){
                foreach($tagIds as $tagId){
                    $rows[] = '(?,?)';
                    $values[] = $recordId;
                    $values[] = $tagId;
                }
            }
            $added += $this->database->execute('INSERT IGNORE INTO '.$this->schema->table('tagLinks')
                .' (rtl_RecID,rtl_TagID) VALUES '.implode(',', $rows), $values);
        }
        return $added;
    }

    /**
     * Take the tags off the records.
     *
     * @return int Links removed.
     */
    public function remove(array $recordIds, array $tagIds): int
    {
        $recordIds = TagRepository::ids($recordIds);
        $tagIds = TagRepository::ids($tagIds);
        if(empty($recordIds) || empty($tagIds)){ return 0; }
        $removed = 0;
        foreach(array_chunk($recordIds, self::BATCH_SIZE) as $chunk){
            $removed += $this->database->execute('DELETE FROM '.$this->schema->table('tagLinks').' WHERE rtl_RecID IN ('
                .self::placeholders($chunk).') AND rtl_TagID IN ('.self::placeholders($tagIds).')', array_merge($chunk, $tagIds));
        }
        return $removed;
    }

    /** Remove every link of a tag (before the tag is deleted); the number removed. */
    public function removeTag(int $tagId): int
    {
        return $this->database->execute('DELETE FROM '.$this->schema->table('tagLinks').' WHERE rtl_TagID=?', array($tagId));
    }

    /**
     * Move the links of tags to another tag (merge, legacy replaceTags): a record that has both
     * keeps one link; the old links are gone afterwards.
     *
     * @return int Links moved.
     */
    public function move(array $fromTagIds, int $toTagId): int
    {
        $fromTagIds = array_values(array_diff(TagRepository::ids($fromTagIds), array($toTagId)));
        if(empty($fromTagIds) || $toTagId < 1){ return 0; }
        $links = $this->schema->table('tagLinks');
        $moved = $this->database->execute('UPDATE IGNORE '.$links.' SET rtl_TagID=? WHERE rtl_TagID IN ('
            .self::placeholders($fromTagIds).')', array_merge(array($toTagId), $fromTagIds));
        // links of records that had the target tag already were kept by IGNORE
        $this->database->execute('DELETE FROM '.$links.' WHERE rtl_TagID IN ('.self::placeholders($fromTagIds).')', $fromTagIds);
        return $moved;
    }

    /**
     * Bookmarks of a user for records that have none (a personal tag bookmarks a record, as legacy).
     *
     * @return int Bookmarks added.
     */
    public function bookmark(int $userId, array $recordIds): int
    {
        $recordIds = TagRepository::ids($recordIds);
        if($userId < 1 || empty($recordIds)){ return 0; }
        $bookmarks = $this->schema->table('bookmarks');
        $added = 0;
        $now = date('Y-m-d H:i:s');
        foreach(array_chunk($recordIds, self::BATCH_SIZE) as $chunk){
            $existing = array_map('intval', $this->database->fetchColumn('SELECT bkm_RecID FROM '.$bookmarks
                .' WHERE bkm_UGrpID=? AND bkm_RecID IN ('.self::placeholders($chunk).')', array_merge(array($userId), $chunk)));
            $missing = array_values(array_diff($chunk, $existing));
            if(empty($missing)){ continue; }
            $rows = array();
            $values = array();
            foreach($missing as $recordId){
                $rows[] = '(?,?,?,?)';
                array_push($values, $userId, $now, $now, $recordId);
            }
            $added += $this->database->execute('INSERT INTO '.$bookmarks.' (bkm_UGrpID,bkm_Added,bkm_Modified,bkm_RecID) VALUES '
                .implode(',', $rows), $values);
        }
        return $added;
    }

    private static function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }
}
