<?php
/**
* EntityRecordMapping.php - Base of the mappings between a legacy table and the record format
*
* A system entity kept in its own legacy table (files, tags, later bookmarks,
* reminders...) is given and taken as a record of a real record type (plan 20,
* §3, §10): rec_* headers, and details keyed by the local field ids of concept
* codes. A mapping names the record type by concept code and the fields as
* concept code => column; a field the database lacks is left out. Subclasses add
* the headers, the title and the value conversions (hooks fieldValue and
* columnValue).
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

use Heurist\Definitions\DefinitionLookup;
use Heurist\Utilities\StoredText;

/** Concept-code fields of a legacy table, read and written as record details. */
abstract class EntityRecordMapping
{
    protected DefinitionLookup $definitions;

    /** @var array<int,array>|null Field id => mapping + concept, for the fields of this database. */
    private ?array $fields = null;

    /** @var array<int,array<string,int>> Field id => lower-case term label => term id (its vocabulary). */
    private array $termsByLabel = array();

    public function __construct(DefinitionLookup $definitions)
    {
        $this->definitions = $definitions;
    }

    /** Concept code of the record type ("2-1106"; '' while the core definitions do not have it yet). */
    abstract public function rectypeConcept(): string;

    /**
     * Mapped fields: concept code => array(column, convert?, admin?, readOnly?).
     *
     * @return array<string,array<string,mixed>>
     */
    abstract protected function fieldMappings(): array;

    /** Concept codes of the fields a client may write (the others are set by the server). */
    abstract protected function writableConcepts(): array;

    /** Local id of the record type (0 when the database does not have it yet). */
    public function rectypeId(): int
    {
        $concept = $this->rectypeConcept();
        return $concept === '' ? 0 : $this->definitions->localId('rty', $concept);
    }

    /**
     * Mapped fields that exist in this database.
     *
     * @return array<int,array{column:string,concept:string,convert?:string,admin?:bool}> field id => mapping
     */
    public function fields(): array
    {
        if($this->fields === null){
            $this->fields = array();
            foreach($this->fieldMappings() as $concept => $mapping){
                $id = $this->definitions->localId('dty', $concept);
                if($id > 0){ $this->fields[$id] = $mapping + array('concept' => $concept); }
            }
        }
        return $this->fields;
    }

    /** Field id of a concept code (0 when the database does not have it). */
    public function fieldId(string $concept): int
    {
        foreach($this->fields() as $id => $mapping){
            if($mapping['concept'] === $concept){ return $id; }
        }
        return 0;
    }

    /**
     * Columns of the writable fields of a request (RecordInput details by field id): a field in the
     * input sets its column (no value: empty), other fields are not returned. The first value that
     * is not a translation ("fre:...") is used; legacy tables keep one value per column.
     *
     * @param array<int,array<int,mixed>> $details field id => values as given
     * @return array<string,?string> column => value
     */
    public function writableColumns(array $details): array
    {
        $columns = array();
        foreach($this->fields() as $fieldId => $mapping){
            if(!in_array($mapping['concept'], $this->writableConcepts(), true) || !array_key_exists($fieldId, $details)){
                continue;
            }
            $value = null;
            foreach((array)$details[$fieldId] as $item){
                if(is_array($item)){ $item = $item['value'] ?? null; }
                $item = $item === null ? '' : trim((string)$item);
                if($item !== '' && !preg_match('/^[a-z]{3}:/', $item)){
                    $type = $this->definitions->fieldType((int)$fieldId);
                    $value = in_array($type, array('freetext', 'blocktext'), true) ? StoredText::clean($item, $type) : $item;
                    break;
                }
            }
            $columns[$mapping['column']] = $value;
        }
        return $columns;
    }

    /** Columns of the writable fields (PUT empties those that are not given). */
    public function writableColumnNames(): array
    {
        $names = array();
        foreach($this->fields() as $mapping){
            if(in_array($mapping['concept'], $this->writableConcepts(), true)){ $names[] = $mapping['column']; }
        }
        return $names;
    }

    /**
     * Details of a row: field id => [value] in database form (strings, term ids), empty
     * values left out.
     *
     * @param bool $admin Whether the caller may see the fields marked "admin".
     * @return array<int,array<int,string>>
     */
    protected function detailsOf(array $row, bool $admin = false): array
    {
        $details = array();
        foreach($this->fields() as $id => $mapping){
            if(!empty($mapping['admin']) && !$admin){ continue; }
            $value = $this->fieldValue($id, $mapping, $row);
            if($value !== null && $value !== ''){ $details[$id] = array($value); }
        }
        return $details;
    }

    /**
     * Columns of stored details (the reverse of detailsOf).
     *
     * @param array<int,array<int,string>> $details field id => values
     * @return array<string,?string>
     */
    protected function columnsOf(array $details): array
    {
        $columns = array();
        foreach($this->fields() as $fieldId => $mapping){
            $value = $details[$fieldId][0] ?? null;
            $columns[$mapping['column']] = $value === null ? null : $this->columnValue($fieldId, $mapping, (string)$value, $details);
        }
        return $columns;
    }

    /** Database form of one mapped field of a row; null when empty. Subclasses convert. */
    protected function fieldValue(int $fieldId, array $mapping, array $row): ?string
    {
        $value = $row[$mapping['column']] ?? null;
        return $value === null || trim((string)$value) === '' ? null : (string)$value;
    }

    /** Column value of one stored field value. Subclasses convert. */
    protected function columnValue(int $fieldId, array $mapping, string $value, array $details): ?string
    {
        return $value;
    }

    /** Term id of a label in the vocabulary of a field (null when it has no such term). */
    protected function termId(int $fieldId, string $label): ?string
    {
        $label = strtolower(trim($label));
        if($label === ''){ return null; }
        if(!isset($this->termsByLabel[$fieldId])){
            $this->termsByLabel[$fieldId] = array();
            $vocabulary = $this->definitions->field($fieldId)['vocabulary'] ?? array();
            if(!empty($vocabulary)){
                foreach($this->definitions->terms($this->definitions->termDescendants($vocabulary)) as $id => $term){
                    if(in_array($id, $vocabulary, true)){ continue; }
                    $key = strtolower(trim((string)$term['term']));
                    if(!isset($this->termsByLabel[$fieldId][$key])){ $this->termsByLabel[$fieldId][$key] = $id; }
                }
            }
        }
        $id = $this->termsByLabel[$fieldId][$label] ?? null;
        return $id === null ? null : (string)$id;
    }
}
