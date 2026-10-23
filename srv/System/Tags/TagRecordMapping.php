<?php
/**
* TagRecordMapping.php - usrTags rows as records of the Tag record type
*
* Plan 20, §10.3: tag_Text is DT_NAME 2-1 (the title), tag_Description is
* DT_SHORT_SUMMARY 2-3, the number of tagged records the read-only "Usage" field
* 2-1189, the owner (a user or a group) is rec_OwnerUGrpID and tag_Modified the
* concurrency token rec_Modified. The record type is Tag 2-1109 (Artem,
* 2026-10-22). In a database that does not have these definitions yet,
* rec_RecTypeID is 0 and the usage is the header rec_Usage (plan 20, §10.8).
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

use Heurist\System\Entity\EntityRecordMapping;

/** Converts rows of usrTags to tag records and back. */
final class TagRecordMapping extends EntityRecordMapping
{
    /** Concept code of the Tag record type (plan 20, §10.3). */
    public const RECTYPE = '2-1109';

    /** Concept code of the read-only "Usage" field: the number of tagged records. */
    public const USAGE_FIELD = '2-1189';

    /** Field concept code => usrTags column. */
    public const FIELDS = array(
        '2-1' => array('column' => 'tag_Text'),          // DT_NAME: the tag, required, at most 63 characters
        '2-3' => array('column' => 'tag_Description'),   // DT_SHORT_SUMMARY: at most 250 characters
        '2-1189' => array('column' => 'tag_Usage')       // Usage: read-only, counted (TagRepository)
    );

    /** Fields a client may write. */
    public const WRITABLE_FIELDS = array('2-1', '2-3');

    /** Lengths of the columns. */
    public const MAX_TEXT = 63;
    public const MAX_DESCRIPTION = 250;

    /** {@inheritdoc} */
    public function rectypeConcept(): string
    {
        return self::RECTYPE;
    }

    /** {@inheritdoc} */
    protected function fieldMappings(): array
    {
        return self::FIELDS;
    }

    /** {@inheritdoc} */
    protected function writableConcepts(): array
    {
        return self::WRITABLE_FIELDS;
    }

    /**
     * Record of a TagRepository row: headers and details (database form).
     *
     * @return array{headers:array<string,?string>,details:array<int,array<int,string>>,usage:int}
     */
    public function toRecord(array $row): array
    {
        return array(
            'headers' => array(
                'rec_ID' => (string)intval($row['tag_ID']),
                'rec_RecTypeID' => (string)$this->rectypeId(),
                'rec_Title' => (string)($row['tag_Text'] ?? ''),
                'rec_Modified' => isset($row['tag_Modified']) ? (string)$row['tag_Modified'] : null,
                'rec_OwnerUGrpID' => (string)intval($row['tag_UGrpID'] ?? 0),
                'rec_AddedByImport' => (string)intval($row['tag_AddedByImport'] ?? 0)
            ),
            'details' => $this->detailsOf($row),
            'usage' => intval($row['tag_Usage'] ?? 0)
        );
    }
}
