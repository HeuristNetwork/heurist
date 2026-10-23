<?php
/**
* EntityRecordOutput.php - Base of the /sys output of a mapped type in the record format
*
* Plan 20, §10.1: /sys gives a mapped type (tags from 4a, bookmarks and reminders
* from 4b) as /records and /files do: rec_* headers, details by field id, `fields`
* as in /records (_all, header names, field ids, virtual headers such as
* rec_OwnerName). The keywords of the former /sys output are accepted in `fields`
* too (subclasses add their own). A subclass gives the rows and their records.
*
* @project     Heurist academic knowledge management system
* @package     System\Entity
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\System\Entity;

use Heurist\Database\DatabaseInterface;
use Heurist\Records\Data\RecordDataService;
use Heurist\Records\Data\RecordFieldSelector;
use Heurist\Runtime\RuntimeContext;
use Heurist\Users\UserGroupRepository;

/** Records of one mapped /sys type. */
abstract class EntityRecordOutput
{
    protected DatabaseInterface $database;
    protected RuntimeContext $runtime;

    public function __construct(DatabaseInterface $database, RuntimeContext $runtime)
    {
        $this->database = $database;
        $this->runtime = $runtime;
    }

    /** The mapping of the type. */
    abstract public function mapping(): EntityRecordMapping;

    /** Headers of a record of the type (in this order). */
    abstract protected function headers(): array;

    /**
     * Rows of the ids that the caller may see.
     *
     * @return array<int,array<string,mixed>> id => row
     */
    abstract protected function rows(array $ids): array;

    /**
     * Record of a row: headers and details (database form).
     *
     * @return array{headers:array<string,?string>,details:array<int,array<int,string>>}
     */
    abstract protected function toRecord(array $row): array;

    /** Column of the owner of a row (rec_OwnerName). */
    abstract protected function ownerColumn(): string;

    /**
     * Former /sys keywords of `fields` => what they mean now (a header, or a field concept code).
     * id, title, modified, owner, ownername and type are known to all types.
     */
    protected function keywords(): array
    {
        return array();
    }

    /** A keyword of `fields` the subclass handles itself (true: taken). */
    protected function selectSpecial(string $name, array &$parsed, array &$selection): bool
    {
        return false;
    }

    /** Values the subclass adds to a record (after the headers and details). */
    protected function addSpecial(array &$record, array $row, array $mapped, array $selection): void
    {
    }

    /** Headers the subclass adds to the selection metadata. */
    protected function specialHeaders(array $selection): array
    {
        return array();
    }

    /**
     * Records in the order of the ids, and the field metadata of the selection.
     *
     * @param mixed $fields `fields` of the request (null: rec_ID, rec_RecTypeID, rec_Title; an item: _all).
     * @return array{records:array<int,array>,headers:array<int,string>,fields:array<int,array>}
     */
    public function records(array $ids, $fields, bool $item = false): array
    {
        $selection = $this->selection($fields, $item);
        $rows = $this->rows($ids);
        $ownerColumn = $this->ownerColumn();
        $owners = in_array('rec_OwnerName', $selection['virtuals'], true)
            ? UserGroupRepository::shared($this->database)->names(array_values(array_unique(array_map(static function($row) use ($ownerColumn){
                return intval($row[$ownerColumn]);
            }, $rows)))) : array();
        $fieldIds = array();
        foreach($selection['details'] as $field){
            if(empty($field['traversal']) && empty($field['header'])){ $fieldIds[intval($field['fieldId'])] = true; }
        }
        $wantsDetails = !empty($selection['all']) || !empty($fieldIds);

        $records = array();
        foreach($ids as $id){
            if(!isset($rows[$id])){ continue; }
            $mapped = $this->toRecord($rows[$id]);
            $record = array();
            foreach($selection['headers'] as $header){ $record[$header] = $mapped['headers'][$header] ?? null; }
            if($wantsDetails){
                $record['details'] = array();
                foreach($mapped['details'] as $fieldId => $values){
                    if(empty($selection['all']) && !isset($fieldIds[$fieldId])){ continue; }
                    $record['details'][(string)$fieldId] = $values;
                }
                // an object in JSON, also without values (as /records)
                if(empty($record['details'])){ $record['details'] = new \stdClass(); }
            }
            if(in_array('rec_OwnerName', $selection['virtuals'], true)){
                $record['rec_OwnerName'] = $owners[intval($rows[$id][$ownerColumn])] ?? null;
            }
            $this->addSpecial($record, $rows[$id], $mapped, $selection);
            $records[] = $record;
        }
        return array(
            'records' => $records,
            'headers' => array_values(array_merge($selection['headers'], $selection['virtuals'], $this->specialHeaders($selection))),
            'fields' => $this->fieldMetadata($selection)
        );
    }

    /** The selection of `fields`: the former keywords translated, the rest parsed as in /records. */
    private function selection($fields, bool $item): array
    {
        if(($fields === null || $fields === '' || $fields === array()) && $item){ $fields = '_all'; }
        $values = is_array($fields) ? $fields : (($fields === null || $fields === '') ? array() : explode(',', (string)$fields));
        $keywords = array(
            'id' => 'rec_ID', 'title' => 'rec_Title', 'modified' => 'rec_Modified', 'owner' => 'rec_OwnerUGrpID',
            'ownername' => 'rec_OwnerName', 'type' => 'rec_RecTypeID', 't' => 'rec_RecTypeID'
        );
        foreach($this->keywords() as $keyword => $target){
            // a concept code becomes the local field id ('' when the database lacks it)
            if(preg_match('/^\d+-\d+$/', $target)){
                $fieldId = $this->mapping()->fieldId($target);
                $target = $fieldId > 0 ? (string)$fieldId : '';
            }
            $keywords[$keyword] = $target;
        }
        $special = array('all' => false);
        $parsed = array();
        foreach($values as $value){
            $name = strtolower(trim((string)$value));
            $name = preg_replace('/^f:/', '', $name);
            if($name === '' || $this->selectSpecial($name, $parsed, $special)){ continue; }
            if($name === '_all'){ $special['all'] = true; }
            $mapped = $keywords[$name] ?? trim((string)$value);
            if($mapped !== ''){ $parsed[] = $mapped; }
        }
        $selection = (new RecordFieldSelector())->parse($parsed);
        // a record of the type has only its own headers; _all: all of them
        $headers = $special['all'] ? $this->headers() : array_values(array_intersect($this->headers(), $selection['headers']));
        $selection['headers'] = array_values(array_unique(array_merge(array('rec_ID', 'rec_RecTypeID', 'rec_Title'), $headers)));
        $selection['virtuals'] = array_values(array_intersect($selection['virtuals'], array('rec_OwnerName')));
        return $selection + array('special' => $special);
    }

    /** Metadata of the selected fields (as /records). */
    private function fieldMetadata(array $selection): array
    {
        $fields = array();
        foreach($this->mapping()->fields() as $fieldId => $mapping){
            $selected = !empty($selection['all']);
            foreach($selection['details'] as $field){
                if(intval($field['fieldId']) === $fieldId){ $selected = true; }
            }
            if($selected){ $fields[] = array('key' => (string)$fieldId, 'fieldId' => $fieldId, 'pathCode' => null, 'traversal' => null); }
        }
        return empty($fields) ? array() : (new RecordDataService($this->database, $this->runtime))->loadFieldMetadata($fields);
    }
}
