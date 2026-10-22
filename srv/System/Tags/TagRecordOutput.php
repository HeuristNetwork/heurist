<?php
/**
* TagRecordOutput.php - Tags of /sys in the record format
*
* Plan 20, §10.1: /sys gives tags in the record format from 4a (EntityRecordOutput).
* The keywords of the former /sys output are accepted in `fields` too (owner,
* ownername, description, title, modified, id, usage). The number of tagged
* records is the field Usage 2-1189; a database without it gets the header
* rec_Usage instead (plan 20, §10.8).
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

use Heurist\Database\DatabaseInterface;
use Heurist\Definitions\DefinitionLookup;
use Heurist\Records\UserData\TagRepository;
use Heurist\Runtime\RuntimeContext;
use Heurist\System\Entity\EntityRecordOutput;

/** Tag records of the /sys domain. */
final class TagRecordOutput extends EntityRecordOutput
{
    /** Headers of a tag record (in this order). */
    public const HEADERS = array('rec_ID', 'rec_RecTypeID', 'rec_Title', 'rec_Modified', 'rec_OwnerUGrpID', 'rec_AddedByImport');

    private TagRepository $tags;
    private TagRecordMapping $mapping;

    public function __construct(DatabaseInterface $database, RuntimeContext $runtime, ?TagRepository $tags = null)
    {
        parent::__construct($database, $runtime);
        $this->tags = $tags ?? new TagRepository($database);
        $this->mapping = new TagRecordMapping(DefinitionLookup::shared($database));
    }

    /** {@inheritdoc} */
    public function mapping(): TagRecordMapping
    {
        return $this->mapping;
    }

    /** {@inheritdoc} */
    protected function headers(): array
    {
        return self::HEADERS;
    }

    /** {@inheritdoc} */
    protected function rows(array $ids): array
    {
        return $this->tags->rows($ids);
    }

    /** {@inheritdoc} */
    protected function toRecord(array $row): array
    {
        return $this->mapping->toRecord($row);
    }

    /** {@inheritdoc} */
    protected function ownerColumn(): string
    {
        return 'tag_UGrpID';
    }

    /** {@inheritdoc} */
    protected function keywords(): array
    {
        return array('description' => '2-3');
    }

    /** usage / rec_Usage: the Usage field, or (a database without it) the header rec_Usage. */
    protected function selectSpecial(string $name, array &$parsed, array &$selection): bool
    {
        if($name === '_all'){
            $selection['usage'] = true;
            return false;
        }
        if($name !== 'usage' && $name !== 'rec_usage'){ return false; }
        $usageId = $this->mapping->fieldId(TagRecordMapping::USAGE_FIELD);
        if($usageId > 0){ $parsed[] = (string)$usageId; }
        else{ $selection['usage'] = true; }
        return true;
    }

    /** {@inheritdoc} */
    protected function addSpecial(array &$record, array $row, array $mapped, array $selection): void
    {
        if($this->usageHeader($selection)){ $record['rec_Usage'] = $mapped['usage']; }
    }

    /** {@inheritdoc} */
    protected function specialHeaders(array $selection): array
    {
        return $this->usageHeader($selection) ? array('rec_Usage') : array();
    }

    /** rec_Usage is given only when the database has no Usage field (it is not a /records header). */
    private function usageHeader(array $selection): bool
    {
        return !empty($selection['special']['usage']) && $this->mapping->fieldId(TagRecordMapping::USAGE_FIELD) < 1;
    }
}
