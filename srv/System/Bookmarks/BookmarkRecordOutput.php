<?php
/**
* BookmarkRecordOutput.php - Bookmarks of /sys in the record format
*
* Plan 20, §10.9: the bookmarks of the current user (the /sys query gives only
* those) as EntityRecordOutput gives them. The keywords of the legacy entity are
* accepted in `fields` too (record, rating, notes, privateinfo). The private note
* is decrypted for the owner; meta "privateInfo" tells whether the installation
* encrypts the notes and which notes could not be read.
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

use Heurist\Database\DatabaseInterface;
use Heurist\Definitions\DefinitionLookup;
use Heurist\Records\UserData\BookmarkRepository;
use Heurist\Runtime\RuntimeContext;
use Heurist\System\Entity\EntityRecordOutput;

/** Bookmark records of the /sys domain. */
final class BookmarkRecordOutput extends EntityRecordOutput
{
    /** Headers of a bookmark record (in this order). */
    public const HEADERS = array('rec_ID', 'rec_RecTypeID', 'rec_Title', 'rec_Added', 'rec_Modified', 'rec_OwnerUGrpID', 'rec_AddedByImport');

    private BookmarkRepository $bookmarks;
    private BookmarkRecordMapping $mapping;

    /** @var array<int,int> Bookmarks whose private note could not be decrypted. */
    private array $unreadable = array();

    public function __construct(DatabaseInterface $database, RuntimeContext $runtime, ?BookmarkRecordMapping $mapping = null)
    {
        parent::__construct($database, $runtime);
        $this->bookmarks = new BookmarkRepository($database);
        $this->mapping = $mapping ?? new BookmarkRecordMapping(DefinitionLookup::shared($database));
    }

    /** {@inheritdoc} */
    public function mapping(): BookmarkRecordMapping
    {
        return $this->mapping;
    }

    /** Meta of the private notes: encrypted (the installation has a key) and the unreadable ones. */
    public function privateInfoMeta(): array
    {
        return array('encrypted' => $this->mapping->cipher()->hasKey(), 'unreadable' => array_values($this->unreadable));
    }

    /** {@inheritdoc} */
    protected function headers(): array
    {
        return self::HEADERS;
    }

    /** {@inheritdoc} Only the bookmarks of the current user (also for an administrator). */
    protected function rows(array $ids): array
    {
        $userId = $this->runtime->userId;
        return array_filter($this->bookmarks->rows($ids), static function($row) use ($userId){
            return $userId > 0 && intval($row['bkm_UGrpID']) === $userId;
        });
    }

    /** {@inheritdoc} */
    protected function toRecord(array $row): array
    {
        $record = $this->mapping->toRecord($row, intval($row['bkm_UGrpID']) === $this->runtime->userId);
        if($record['unreadable']){ $this->unreadable[] = intval($row['bkm_ID']); }
        return $record;
    }

    /** {@inheritdoc} */
    protected function ownerColumn(): string
    {
        return 'bkm_UGrpID';
    }

    /** {@inheritdoc} */
    protected function keywords(): array
    {
        return array(
            'added' => 'rec_Added',
            'record' => BookmarkRecordMapping::RECORD_FIELD,
            'rating' => BookmarkRecordMapping::RATING_FIELD,
            'notes' => BookmarkRecordMapping::NOTES_FIELD,
            'privateinfo' => BookmarkRecordMapping::PRIVATE_FIELD,
            'pwdreminder' => BookmarkRecordMapping::PRIVATE_FIELD
        );
    }
}
