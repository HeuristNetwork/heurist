<?php
/**
* ExportColumns.php - Output columns of table-like exports and their cell values
*
* One requested column becomes one or more output columns:
*   header field (rec_Title, ...)      one column ("H-ID" for rec_ID in CSV)
*   enum / relationtype                one column in the column's output (ext) or the
*                                      request's default (term|code|conceptid|desc|internalid)
*   pointer                            id; with pointer format "title" also "<field> title"
*   file                               URL or id; with file format "details" the
*                                      columns id, name, mime type, URL
*   other fields                       one column (dates in the request's date format)
* A linked path (10:lf134:12:1) is named after its steps: "Place of birth > Place name".
* Repeated values are joined with the multi-value separator.
*
* @project     Heurist academic knowledge management system
* @package     Records\Export
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Export;

use Heurist\Records\Data\RecordFieldSelector;

/** Expands requested columns and reads their cells from record objects. */
final class ExportColumns
{
    /** Captions of header fields. */
    private const HEADER_NAMES = array(
        'rec_ID' => 'rec_ID',
        'rec_RecTypeID' => 'Record type ID',
        'rec_Title' => 'Title',
        'rec_URL' => 'URL',
        'rec_ScratchPad' => 'Notes',
        'rec_OwnerUGrpID' => 'Owner',
        'rec_NonOwnerVisibility' => 'Visibility',
        'rec_Added' => 'Added',
        'rec_Modified' => 'Modified',
        'rec_AddedByUGrpID' => 'Added by',
        'rec_Hash' => 'Hash',
        'rec_Bookmarked' => 'Bookmarked',
        'rec_OwnerName' => 'Owner name',
        'rec_ThumbnailURL' => 'Thumbnail URL'
    );

    /** Suffixes of enum outputs in column captions (as the QSE column fields). */
    private const ENUM_SUFFIX = array(
        'term' => '',
        'code' => ' (Code)',
        'conceptid' => ' (Concept ID)',
        'desc' => ' (Description)',
        'internalid' => ' (Internal ID)'
    );

    private ExportDefinitions $definitions;
    private ValueFormatter $formatter;
    private RecordFieldSelector $selector;

    public function __construct(ExportDefinitions $definitions, ValueFormatter $formatter)
    {
        $this->definitions = $definitions;
        $this->formatter = $formatter;
        $this->selector = new RecordFieldSelector();
    }

    /**
     * Output columns of a record type.
     *
     * @param int $rectypeId Record type (names of its fields).
     * @param array<int,array{field:string,ext:?string}> $columns Requested columns.
     * @param bool $idFirst Put rec_ID first as "H-ID" (CSV); else rec_ID keeps its name where requested.
     * @return array<int,array{header:string,key:string,type:string,part:string,ext:?string}>
     */
    public function expand(int $rectypeId, array $columns, bool $idFirst): array
    {
        $result = array();
        if($idFirst){
            $result[] = array('header' => 'H-ID', 'key' => 'rec_ID', 'type' => 'header', 'part' => 'value', 'ext' => null);
        }
        foreach($columns as $column){
            $parsed = $this->selector->parse(array($column['field']));
            if(!empty($parsed['headers']) || !empty($parsed['virtuals'])){
                $key = $parsed['headers'][0] ?? $parsed['virtuals'][0];
                if($idFirst && $key === 'rec_ID'){ continue; }
                $result[] = array('header' => self::HEADER_NAMES[$key] ?? $key, 'key' => $key,
                    'type' => 'header', 'part' => 'value', 'ext' => null);
                continue;
            }
            if(empty($parsed['details'])){ continue; }
            $field = $parsed['details'][0];
            $fieldId = intval($field['fieldId']);
            $type = $this->definitions->fieldType($fieldId);
            $name = $this->caption($rectypeId, $field);
            $key = (string)$field['key'];
            $entry = array('header' => $name, 'key' => $key, 'type' => $type, 'part' => 'value', 'ext' => null);
            if($type === 'enum' || $type === 'relationtype'){
                $entry['ext'] = $column['ext'] ?? $this->formatter->format('enum');
                $entry['header'] = $name.(self::ENUM_SUFFIX[$entry['ext']] ?? '');
                $result[] = $entry;
            }elseif($type === 'resource'){
                $result[] = $entry;
                if($this->formatter->format('pointer') === 'title'){
                    $result[] = array_merge($entry, array('header' => $name.' title', 'part' => 'title'));
                }
            }elseif($type === 'file' && $this->formatter->format('file') === 'details'){
                foreach(array('file_id' => ' ID', 'file_name' => ' name', 'file_mime' => ' mime type', 'file_url' => ' URL')
                    as $part => $suffix){
                    $result[] = array_merge($entry, array('header' => $name.$suffix, 'part' => $part));
                }
            }else{
                $result[] = $entry;
            }
        }
        return $result;
    }

    /**
     * Field codes to load for these columns (RecordFieldSelector input).
     *
     * @param array<int,array{field:string,ext:?string}> $columns Requested columns.
     * @return string[]
     */
    public static function codes(array $columns): array
    {
        $codes = array('rec_ID', 'rec_RecTypeID', 'rec_Title');
        foreach($columns as $column){ $codes[] = (string)$column['field']; }
        return array_values(array_unique($codes));
    }

    /**
     * Cell texts of one record.
     *
     * @param array $record Record object (RecordPageAssembler).
     * @param array $expanded Output of expand().
     * @param string $separator Joins repeated values.
     * @return string[]
     */
    public function cells(array $record, array $expanded, string $separator): array
    {
        $cells = array();
        foreach($expanded as $column){
            if($column['type'] === 'header'){
                $value = $record[$column['key']] ?? '';
                if($column['key'] === 'rec_OwnerUGrpID' || $column['key'] === 'rec_AddedByUGrpID'){
                    $name = $this->definitions->groupName(intval($value));
                    $value = $name !== '' ? $name : $value;
                }
                $cells[] = is_array($value) ? (json_encode($value, JSON_UNESCAPED_UNICODE) ?: '') : (string)$value;
                continue;
            }
            $texts = array();
            foreach($record['details'][$column['key']] ?? array() as $value){
                $texts[] = $this->cellValue($column, $value);
            }
            $cells[] = implode($separator, $texts);
        }
        return $cells;
    }

    private function cellValue(array $column, $value): string
    {
        switch($column['part']){
            case 'title':
                return $this->formatter->pointerTitle($value);
            case 'file_id':
                return $this->formatter->fileDetails($value)[0];
            case 'file_name':
                return $this->formatter->fileDetails($value)[1];
            case 'file_mime':
                return $this->formatter->fileDetails($value)[2];
            case 'file_url':
                return $this->formatter->fileDetails($value)[3];
            default:
                return $this->formatter->text($column['type'], $value, $column['ext']);
        }
    }

    /** Caption of a field or linked path. */
    private function caption(int $rectypeId, array $field): string
    {
        $path = (string)($field['pathCode'] ?? '');
        if($path === ''){
            return $this->definitions->fieldName($rectypeId, intval($field['fieldId']));
        }
        // rty:op<dty>:rty:op<dty>:...:<dty> - name each pointer/relationship step, then the field
        $tokens = explode(':', $path);
        $names = array();
        $owner = intval($tokens[0]);
        for($index = 1; $index < count($tokens); $index += 2){
            $step = $tokens[$index];
            $next = isset($tokens[$index + 1]) ? intval($tokens[$index + 1]) : 0;
            if(preg_match('/^(lt|lf|rt|rf|r)?([0-9]*)$/i', $step, $match) !== 1){ continue; }
            $stepField = intval($match[2]);
            if($index === count($tokens) - 1){
                // terminal field of the owner record type (pointer or relationship field itself)
                $names[] = $stepField > 0 ? $this->definitions->fieldName($owner, $stepField) : $step;
                break;
            }
            $operator = strtolower($match[1]);
            $stepName = $stepField > 0
                ? $this->definitions->fieldName($operator === 'lt' || $operator === 'rt' ? $next : $owner, $stepField)
                : 'Related '.$this->definitions->rectypeName($next);
            if($stepField > 0 && ($operator === 'lt' || $operator === 'rt')){
                $stepName .= ' ('.$this->definitions->rectypeName($next).')';
            }
            $names[] = $stepName;
            $owner = $next;
        }
        return empty($names) ? $path : implode(' > ', $names);
    }
}
