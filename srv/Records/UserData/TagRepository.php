<?php
/**
* TagRepository.php - Reads of tags (usrTags) and their record links (usrRecTagLinks)
*
* Tags stay in their legacy tables for good (plan 20, §10.1: there can be many
* more tags than records). The tables are named through RecordSchema, the
* registry of the record and user tables (plan 15). Writes are LegacyTagTable
* and TagLinkTable.
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

/** Tag rows and the records that carry a tag. */
final class TagRepository
{
    /** Columns of a tag row (the legacy table); tag_Usage is the number of tagged records. */
    public const COLUMNS = array('tag_ID', 'tag_UGrpID', 'tag_Text', 'tag_Description', 'tag_AddedByImport', 'tag_Modified');

    private const BATCH_SIZE = 500;

    private DatabaseInterface $database;
    private RecordSchema $schema;

    public function __construct(DatabaseInterface $database, ?RecordSchema $schema = null)
    {
        $this->database = $database;
        $this->schema = $schema ?? new RecordSchema();
    }

    /** The table names (LegacyTagTable and TagLinkTable write the same tables). */
    public function schema(): RecordSchema
    {
        return $this->schema;
    }

    /**
     * Tag rows with their usage (tag_Usage).
     *
     * @return array<int,array<string,mixed>> tag id => row
     */
    public function rows(array $ids): array
    {
        $rows = array();
        $tags = $this->schema->table('tags');
        $links = $this->schema->table('tagLinks');
        foreach(array_chunk(self::ids($ids), self::BATCH_SIZE) as $chunk){
            foreach($this->database->fetchAll('SELECT '.implode(',', self::COLUMNS).',(SELECT COUNT(*) FROM '.$links
                .' WHERE rtl_TagID=tag_ID) AS tag_Usage FROM '.$tags.' WHERE tag_ID IN ('.self::placeholders($chunk).')', $chunk) as $row){
                $rows[intval($row['tag_ID'])] = $row;
            }
        }
        return $rows;
    }

    /** One tag row, or null. */
    public function row(int $id): ?array
    {
        return $this->rows(array($id))[$id] ?? null;
    }

    /**
     * Id of the tag of an owner with this text (the table's unique key; its collation ignores
     * case), 0 when there is none. $exceptId leaves out the tag being renamed.
     */
    public function idByText(int $ownerId, string $text, int $exceptId = 0): int
    {
        return intval($this->database->fetchValue('SELECT tag_ID FROM '.$this->schema->table('tags')
            .' WHERE tag_UGrpID=? AND tag_Text=? AND tag_ID<>? LIMIT 1', array($ownerId, $text, $exceptId), 0));
    }

    /**
     * Records that carry one of the tags.
     *
     * @param int $limit At most this many (0: all).
     * @return array<int,int> record ids
     */
    public function recordIds(array $tagIds, int $limit = 0): array
    {
        $tagIds = self::ids($tagIds);
        if(empty($tagIds)){ return array(); }
        $sql = 'SELECT DISTINCT rtl_RecID FROM '.$this->schema->table('tagLinks').' WHERE rtl_TagID IN ('
            .self::placeholders($tagIds).') ORDER BY rtl_RecID';
        if($limit > 0){ $sql .= ' LIMIT '.intval($limit); }
        return array_map('intval', $this->database->fetchColumn($sql, $tagIds));
    }

    /** Number of records with the tag. */
    public function usage(int $tagId): int
    {
        return intval($this->database->fetchValue('SELECT COUNT(*) FROM '.$this->schema->table('tagLinks')
            .' WHERE rtl_TagID=?', array($tagId), 0));
    }

    /**
     * Owners of tags (the tags that exist).
     *
     * @return array<int,int> tag id => owner (user or group id)
     */
    public function owners(array $ids): array
    {
        $owners = array();
        foreach(array_chunk(self::ids($ids), self::BATCH_SIZE) as $chunk){
            foreach($this->database->fetchRows('SELECT tag_ID,tag_UGrpID FROM '.$this->schema->table('tags')
                .' WHERE tag_ID IN ('.self::placeholders($chunk).')', $chunk) as $row){
                $owners[intval($row[0])] = intval($row[1]);
            }
        }
        return $owners;
    }

    /**
     * Tags of the given owners on records.
     *
     * @return array<int,int> tag ids
     */
    public function tagsOfRecords(array $recordIds, array $ownerIds): array
    {
        $recordIds = self::ids($recordIds);
        $ownerIds = array_values(array_unique(array_map('intval', $ownerIds)));
        if(empty($recordIds) || empty($ownerIds)){ return array(); }
        $tags = array();
        foreach(array_chunk($recordIds, self::BATCH_SIZE) as $chunk){
            foreach($this->database->fetchColumn('SELECT DISTINCT rtl_TagID FROM '.$this->schema->table('tagLinks').' JOIN '
                .$this->schema->table('tags').' ON tag_ID=rtl_TagID WHERE rtl_RecID IN ('.self::placeholders($chunk)
                .') AND tag_UGrpID IN ('.self::placeholders($ownerIds).')', array_merge($chunk, $ownerIds)) as $id){
                $tags[intval($id)] = true;
            }
        }
        return array_keys($tags);
    }

    /** @return array<int,int> Positive integer ids, unique. */
    public static function ids(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static function($id){ return $id > 0; })));
    }

    private static function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }
}
