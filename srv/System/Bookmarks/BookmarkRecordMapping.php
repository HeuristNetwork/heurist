<?php
/**
* BookmarkRecordMapping.php - usrBookmarks rows as records of the Bookmark record type
*
* Plan 20, §10.3, §10.9: the bookmarked record is the pointer DT_RESOURCE 2-13
* (bkm_RecID), Rating 2-1190 (bkm_Rating, 0-5; 0 = not rated, no value), the notes
* DT_EXTENDED_DESCRIPTION 2-4 (bkm_Notes) and PrivateInfo 2-1192 (bkm_PwdReminder,
* encrypted, PrivateInfoCipher). The owner is the user (rec_OwnerUGrpID =
* bkm_UGrpID), the title is the title of the bookmarked record. bkm_ZoteroID is
* not mapped (Artem, 2026-10-22).
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

use Heurist\Definitions\DefinitionLookup;
use Heurist\System\Entity\EntityRecordMapping;

/** Converts rows of usrBookmarks to bookmark records and back. */
final class BookmarkRecordMapping extends EntityRecordMapping
{
    /** Concept code of the Bookmark record type. */
    public const RECTYPE = '2-1110';

    public const RECORD_FIELD = '2-13';
    public const RATING_FIELD = '2-1190';
    public const NOTES_FIELD = '2-4';
    public const PRIVATE_FIELD = '2-1192';

    /** Field concept code => usrBookmarks column. */
    public const FIELDS = array(
        '2-13' => array('column' => 'bkm_RecID'),          // DT_RESOURCE: the bookmarked record, required
        '2-1190' => array('column' => 'bkm_Rating'),       // Rating 0-5
        '2-4' => array('column' => 'bkm_Notes'),           // DT_EXTENDED_DESCRIPTION: personal notes
        '2-1192' => array('column' => 'bkm_PwdReminder')   // PrivateInfo: encrypted, the owner only
    );

    /** Fields a client may write. */
    public const WRITABLE_FIELDS = array('2-13', '2-1190', '2-4', '2-1192');

    public const MAX_RATING = 5;

    private PrivateInfoCipher $cipher;

    public function __construct(DefinitionLookup $definitions, ?PrivateInfoCipher $cipher = null)
    {
        parent::__construct($definitions);
        $this->cipher = $cipher ?? PrivateInfoCipher::fromConfiguration();
    }

    public function cipher(): PrivateInfoCipher
    {
        return $this->cipher;
    }

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
     * Record of a BookmarkRepository row. The private note is decrypted for the owner only;
     * a note that cannot be decrypted (another key) is left out and marked "unreadable".
     *
     * @param bool $owner Whether the caller owns the bookmark.
     * @return array{headers:array<string,?string>,details:array<int,array<int,string>>,unreadable:bool}
     */
    public function toRecord(array $row, bool $owner): array
    {
        $details = $this->detailsOf($row);
        $privateId = $this->fieldId(self::PRIVATE_FIELD);
        $unreadable = false;
        if($privateId > 0 && isset($details[$privateId])){
            $text = $owner ? $this->cipher->decrypt($details[$privateId][0]) : null;
            if($text === null || $text === ''){
                unset($details[$privateId]);
                $unreadable = $owner && $text === null;
            }else{
                $details[$privateId] = array($text);
            }
        }
        return array(
            'headers' => array(
                'rec_ID' => (string)intval($row['bkm_ID']),
                'rec_RecTypeID' => (string)$this->rectypeId(),
                'rec_Title' => (string)($row['rec_Title'] ?? ''),
                'rec_Added' => isset($row['bkm_Added']) ? (string)$row['bkm_Added'] : null,
                'rec_Modified' => isset($row['bkm_Modified']) ? (string)$row['bkm_Modified'] : null,
                'rec_OwnerUGrpID' => (string)intval($row['bkm_UGrpID'] ?? 0),
                'rec_AddedByImport' => (string)intval($row['bkm_AddedByImport'] ?? 0)
            ),
            'details' => $details,
            'unreadable' => $unreadable
        );
    }

    /** {@inheritdoc} 0 is "not rated": no value. */
    protected function fieldValue(int $fieldId, array $mapping, array $row): ?string
    {
        $value = parent::fieldValue($fieldId, $mapping, $row);
        if($mapping['column'] === 'bkm_Rating'){ return intval($value) > 0 ? (string)intval($value) : null; }
        return $value;
    }
}
