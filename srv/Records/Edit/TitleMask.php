<?php
/**
* TitleMask.php - Record title from the title mask of its record type
*
* Port of TitleMask::fill (hserv/records/edit/recordTitleMask.php, mode 0) only, as
* agreed for plan 14 (2026-10-13): no check, no conversion between coded and human
* readable masks, no API or editor. The stored (coded) mask is filled with the
* values of the record:
*   - [field] by concept code, local id or name; the field is looked up in the record
*     type structure first (display name), then among all fields;
*   - [field.term|code|conceptid|internalid] for terms;
*   - [pointer.field], [pointer.{record type}.field] (any depth) for record pointers and
*     relationship markers; ".." is the separator when the mask uses it;
*   - [id], [rectitle], [rectypeid], [rectypename], [modified] (and "Record Title", ...);
*   - [[ and ]] for literal brackets; {..\..} conditional sections; the legacy clean-up
*     of stray punctuation and spaces.
* fill() returns null when the mask is not defined, has an error, or gives no text;
* the caller then uses the name field ("Record #NNN" without one).
*
* Reads go through DefinitionLookup, RecordRepository and FileRepository (plan 15).
*
* @project     Heurist academic knowledge management system
* @package     Records\Edit
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Stephen White
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Edit;

use Heurist\Definitions\DefinitionLookup;
use Heurist\Records\Files\FileRepository;
use Heurist\Records\Store\RecordRepository;
use Heurist\Utilities\LanguageCodes;
use Heurist\Utilities\Temporal;

/** Fills title masks with record values. */
final class TitleMask
{
    /** Maximum length of rec_Title (varchar 1023). */
    private const MAX_LENGTH = 1023;

    /** Original file name of a remote file (ULF_REMOTE). */
    private const REMOTE_FILE = '_remote';

    /** Concept code of the "Parent entity" field (DT_PARENT_ENTITY). */
    private const PARENT_ENTITY = '2-247';

    private DefinitionLookup $definitions;
    private RecordRepository $records;
    private FileRepository $files;
    private LanguageCodes $languages;
    /** @var array<int,array|null> record id => header and values (per fill) */
    private array $cache = array();

    public function __construct(DefinitionLookup $definitions, RecordRepository $records, FileRepository $files,
        LanguageCodes $languages)
    {
        $this->definitions = $definitions;
        $this->records = $records;
        $this->files = $files;
        $this->languages = $languages;
    }

    /**
     * Title of a record from the mask of its record type.
     *
     * @return string|null Null when there is no mask, it has an error or it gives no text.
     */
    public function fill(int $recordId): ?string
    {
        $this->cache = array();
        $record = $this->record($recordId);
        if($record === null){ return null; }
        $mask = (string)($this->definitions->rectype($record['rectype'])['titleMask'] ?? '');
        if(trim($mask) === ''){ return null; }
        $title = $this->execute($mask, $record['rectype'], $recordId);
        if($title === null){ return null; }
        $title = trim($title);
        return $title === '' ? null : mb_substr($title, 0, self::MAX_LENGTH);
    }

    /**
     * Fill a given mask with the values of a record (tests and previews); as fill().
     */
    public function fillMask(string $mask, int $recordId): ?string
    {
        $this->cache = array();
        $record = $this->record($recordId);
        if($record === null || trim($mask) === ''){ return null; }
        $title = $this->execute($mask, $record['rectype'], $recordId);
        $title = $title === null ? '' : trim($title);
        return $title === '' ? null : mb_substr($title, 0, self::MAX_LENGTH);
    }

    /** TitleMask::execute, mode 0, ERROR_REP_WARN: null on an error or when all fields are blank. */
    private function execute(string $mask, int $rectypeId, int $recordId): ?string
    {
        if(!preg_match_all('/\s*\[\[|\s*\]\]|(\s*(\[\s*([^]]+)\s*\]))/s', $mask, $matches)){
            return $mask; // no fields
        }
        $count = 0;
        $blank = 0;
        $replacements = array();
        foreach($matches[3] as $i => $fieldName){
            if(trim($fieldName) === ''){ continue; } // [[ or ]]
            $count++;
            $value = $this->fillField($fieldName, $rectypeId, $recordId);
            if($value === null){ return null; } // error in the mask
            if(trim($value) === ''){
                $replacements[$matches[1][$i]] = '';
                $blank++;
            }else{
                $replacements[$matches[2][$i]] = $value;
            }
        }
        $replacements['[['] = '[';
        $replacements[']]'] = ']';
        $mask = $this->conditionalSections($mask, $replacements);
        $title = self::replaceInOrder(array_keys($replacements), array_values($replacements), $mask);

        if($blank === $count){ return null; } // no data in the title fields
        return $this->cleanUp($title);
    }

    /** {\Label: [field] \else} and {[field] \if set\if not} sections (legacy, both formats). */
    private function conditionalSections(string $mask, array $replacements): string
    {
        if(!preg_match_all('/(?:\[[^\[\]]+?\])?\s?{\d*\s?(?:\\\\[^\\\\\}]*\s?)*}/', $mask, $conditions)){
            return $mask;
        }
        foreach($conditions[0] as $condition){
            $sectionMatch = array();
            preg_match('/{\d*\s?(?:\\\\[^\\\\]*\s?)*}/', $condition, $sectionMatch);
            $parts = mb_split('\\\\', trim($sectionMatch[0], ' {}'));
            $maxLength = 0;
            if(is_numeric(trim($parts[0])) || empty($parts[0])){
                $maxLength = intval($parts[0]);
                array_shift($parts);
            }
            $replace = null;
            if(!empty($parts) && strpos($parts[0], '[') !== false && strpos($parts[0], ']') !== false){
                // new format: the first section whose fields all have a value
                foreach($parts as $part){
                    preg_match_all('/(?:\[[^\[\]]+?\])+/', $part, $fields);
                    $valid = true;
                    foreach($fields[0] as $field){
                        if(!array_key_exists($field, $replacements)){ $valid = false; break; }
                    }
                    if($valid){ $replace = $part; break; }
                }
                if($replace === null){ $replace = ''; }
            }elseif(count($parts) === 1 || count($parts) === 2){
                // original format: the field before the section decides
                preg_match('/\[[^\[\]]+?\]/', $condition, $field);
                $value = isset($field[0]) && array_key_exists($field[0], $replacements) ? $replacements[$field[0]] : '';
                if(!empty($value)){
                    $replace = $parts[0].' '.self::shorten($value, $maxLength);
                }elseif(empty($parts[1])){
                    $replace = '';
                }else{
                    $replace = $parts[1];
                }
            }elseif(empty($parts) && $maxLength > 0){
                preg_match('/\[[^\[\]]+?\]/', $condition, $field);
                $value = isset($field[0]) && array_key_exists($field[0], $replacements) ? $replacements[$field[0]] : '';
                $replace = self::shorten($value, $maxLength);
            }
            if($replace !== null){
                $mask = (string)preg_replace_callback('/'.preg_quote($condition, '/').'/iu',
                    static function() use ($replace){ return $replace; }, $mask);
            }
        }
        return $mask;
    }

    /** Legacy clean-up of stray punctuation, empty brackets and double spaces. */
    private function cleanUp(string $title): string
    {
        if(!preg_match('/^\s*[0-9a-z]+:\S+\s*$/i', $title)){ // not a URI
            $puncts = '-:;,.@#|+=&(){}';
            $punctsNoFullstops = '-:;,@#|+=&(){}';
            $puncts2 = '-:;,@#|+=&';
            $inner = '/\s]*(.*?)[';
            $open = '!\([';
            $title = (string)preg_replace('!^['.$puncts.$inner.$puncts2.'/\s]*$!s', '\1', $title);
            $title = (string)preg_replace($open.$puncts.'/\s]+\)!s', '', $title);
            $title = (string)preg_replace($open.$puncts.$inner.$puncts2.'/\s]*\)!s', '(\1)', $title);
            $title = (string)preg_replace($open.$puncts.'/\s]*\)|\[['.$punctsNoFullstops.'/\s]*\]|\[[.]{,2}\]|\[[.]{4,}\]!s', '', $title);
            $title = (string)preg_replace('!^['.$puncts.$inner.$puncts2.'/\s]*$!s', '\1', $title);
            $title = (string)preg_replace('!,\s*,+!s', ',', $title);
            $title = (string)preg_replace('!\s+,!s', ',', $title);
        }
        return trim((string)preg_replace('!  +!s', ' ', $title));
    }

    /**
     * Value of one mask field (TitleMask::__fill_field, mode 0).
     *
     * @return string|null Null for an error in the mask (unknown field, bad path).
     */
    private function fillField(string $fieldName, int $rectypeId, int $recordId): ?string
    {
        $special = self::specialField($fieldName);
        if($special !== null){
            return $this->headerValue($special, $recordId);
        }
        $separator = mb_strpos($fieldName, '..') > 0 ? '..' : '.';
        if(mb_strpos($fieldName, $separator) === false && mb_strpos($fieldName, '{') !== 0){
            $fieldId = $this->fieldId($rectypeId, $fieldName);
            return $fieldId < 1 ? null : $this->fieldValue($fieldId, $rectypeId, $recordId);
        }

        // path: parent field, then a term part or the fields of linked records
        $parts = explode($separator, $fieldName);
        for($i = 1; $i < count($parts); $i++){
            while(mb_strpos($parts[$i], '.') === 0){ // "A...B": extra full stops belong to A
                $parts[$i - 1] .= '.';
                $parts[$i] = mb_substr($parts[$i], 1);
            }
        }
        if(count($parts) < 2){ return ''; }
        $fieldId = $this->fieldId($rectypeId, $parts[0]);
        if($fieldId < 1){ return null; }
        $inner = $parts[1];
        $type = $this->definitions->fieldType($fieldId);
        if($type === 'enum' || $type === 'relationtype'){
            $attribute = strtolower(trim($inner));
            if($attribute === '' || $attribute === 'label'){ $attribute = 'term'; }
            if($attribute === 'id'){ $attribute = 'internalid'; }
            if(!in_array($attribute, array('term', 'code', 'conceptid', 'internalid'), true)){ return null; }
            return $this->fieldValue($fieldId, $rectypeId, $recordId, $attribute);
        }
        if($type !== 'resource' && $type !== 'relmarker'){ return null; }

        if(count($parts) > 2){
            // [pointer.{record type}.field...] or [pointer.field.field...]
            $targetType = 0;
            $shift = 2;
            if(mb_strpos($inner, '{') === 0 && mb_strpos($inner, '}') === mb_strlen($inner) - 1){
                $targetType = $this->rectypeId(mb_substr($inner, 1, -1));
                if($targetType < 1){ return null; }
            }else{
                $targets = $this->targetTypes($rectypeId, $fieldId);
                if(count($targets) === 1 || $this->isParentEntity($inner)){
                    $targetType = $targets[0] ?? 0;
                    $shift = 1;
                }else{
                    $targetType = $this->rectypeId($inner);
                    if($targetType < 1){ return null; }
                }
            }
            $rest = implode($separator, array_slice($parts, $shift));
            $values = array();
            foreach($this->pointerIds($fieldId, $type, $recordId) as $targetId){
                $value = $this->fillField($rest, $targetType, $targetId);
                $values[] = $value ?? '';
            }
            return implode(', ', $values);
        }

        // [pointer.field] or [pointer.{record type}..field]
        $targetType = 0;
        $position = mb_strpos($inner, $separator);
        $closing = mb_strpos($inner, '}');
        if($position > 0 && $closing > 0 && $closing < $position){
            $targetType = $this->rectypeId(mb_substr($inner, 1, $closing - 1));
            $inner = mb_substr($inner, $position + mb_strlen($separator));
        }
        $values = array();
        foreach($this->pointerIds($fieldId, $type, $recordId) as $targetId){
            $target = $this->record($targetId);
            if($target === null){ continue; }
            if($targetType > 0 && $targetType !== $target['rectype']){ continue; }
            $value = $this->fillField($inner, $target['rectype'], $targetId);
            if($value === null){ return ''; } // the field may belong to another type of a multi-type pointer
            if($value !== ''){ $values[] = $value; }
        }
        return implode(', ', $values);
    }

    /** id, rectitle, rectypeid, rectypename, modified (and their long names), or null. */
    private static function specialField(string $fieldName): ?string
    {
        $names = array('record title' => 'rectitle', 'record id' => 'id', 'record typeid' => 'rectypeid',
            'record typename' => 'rectypename', 'record modified' => 'modified');
        $name = strtolower($fieldName);
        $name = $names[$name] ?? $name;
        return in_array($name, array('id', 'rectitle', 'rectypeid', 'rectypename', 'modified'), true) ? $name : null;
    }

    private function headerValue(string $name, int $recordId): string
    {
        $record = $this->record($recordId);
        if($record === null){ return ''; }
        switch($name){
            case 'id': return (string)$recordId;
            case 'rectitle': return $record['title'];
            case 'rectypeid': return (string)$record['rectype'];
            case 'rectypename': return $this->definitions->rectypeName($record['rectype']);
            default: return $record['modified'];
        }
    }

    /**
     * Values of a field of a record joined with ", " (TitleMask::__get_field_value, mode 0).
     *
     * @param string|null $termAttribute term, code, conceptid or internalid for terms.
     */
    private function fieldValue(int $fieldId, int $rectypeId, int $recordId, ?string $termAttribute = null): string
    {
        $type = $this->definitions->fieldType($fieldId);
        if($type === 'relmarker'){
            return implode(', ', $this->relatedIds($fieldId, $recordId));
        }
        $record = $this->record($recordId);
        if($record === null){ return ''; }
        $values = array();
        foreach($record['values'][$fieldId] ?? array() as $row){
            if($type === 'enum' || $type === 'relationtype'){
                $value = $this->termValue(intval($row['value']), $termAttribute);
            }elseif($type === 'date'){
                $value = (string)Temporal::toHumanReadable(trim((string)$row['value']), true);
            }elseif($type === 'file'){
                $value = $this->fileName((int)$row['file']);
            }elseif($type === 'freetext' || $type === 'blocktext'){
                $value = (string)$this->languages->splitPrefix((string)$row['value'])[1];
            }else{
                $value = (string)$row['value'];
            }
            if($value !== ''){ $values[] = $value; }
        }
        if(empty($values)){ return ''; }
        if($type === 'geo'){
            return count($values).' geographic object'.(count($values) > 1 ? 's' : '');
        }
        return implode(', ', $values);
    }

    /** Label (with parent labels), code, concept code or id of a term. */
    private function termValue(int $termId, ?string $attribute): string
    {
        $term = $this->definitions->term($termId);
        if($term === null){ return ''; }
        switch($attribute ?? 'term'){
            case 'code': return $term['code'];
            case 'conceptid': return $term['conceptid'];
            case 'internalid': return (string)$termId;
            default: return $this->definitions->termHierarchyLabel($termId);
        }
    }

    /** Original name of a file, or its URL for a remote file. */
    private function fileName(int $fileId): string
    {
        $file = $fileId > 0 ? ($this->files->files(array($fileId))[$fileId] ?? null) : null;
        if($file === null){ return ''; }
        return (string)$file['ulf_OrigFileName'] === self::REMOTE_FILE
            ? (string)$file['ulf_ExternalFileReference'] : (string)$file['ulf_OrigFileName'];
    }

    /** Linked record ids of a pointer or relationship marker field. */
    private function pointerIds(int $fieldId, string $type, int $recordId): array
    {
        if($type === 'relmarker'){ return $this->relatedIds($fieldId, $recordId); }
        $record = $this->record($recordId);
        $ids = array();
        foreach($record['values'][$fieldId] ?? array() as $row){
            $id = intval($row['value']);
            if($id > 0){ $ids[] = $id; }
        }
        return $ids;
    }

    /**
     * Records related through a relationship marker: its relation types (the field's
     * vocabulary) and target record types, both directions, not temporary.
     *
     * @return array<int,int>
     */
    private function relatedIds(int $fieldId, int $recordId): array
    {
        $field = $this->definitions->field($fieldId);
        $options = array('kind' => 'relationship');
        if(!empty($field['vocabulary'])){
            $options['relationTypes'] = $this->definitions->termDescendants($field['vocabulary']);
        }
        if(!empty($field['targetTypes'])){ $options['otherTypes'] = $field['targetTypes']; }
        $ids = array();
        foreach(array('source', 'target') as $side){
            foreach($this->records->links(array($recordId), $options + array('side' => $side)) as $link){
                $ids[$link[1]] = $link[1];
            }
        }
        $headers = $this->records->headers(array_values($ids), array('rec_FlagTemporary'));
        return array_values(array_filter($ids, static function($id) use ($headers){
            return isset($headers[$id]) && intval($headers[$id]['rec_FlagTemporary']) === 0;
        }));
    }

    /** Pointer target types of a field in a record type (structure filter, else the field's). */
    private function targetTypes(int $rectypeId, int $fieldId): array
    {
        $row = $this->definitions->structureRows($rectypeId)[$fieldId] ?? null;
        if($row !== null){ return $row['targetTypes']; }
        return $this->definitions->field($fieldId)['targetTypes'] ?? array();
    }

    /**
     * Local id of a mask field: in the structure of the record type by id, display name
     * or concept code, then among all fields by id, name or concept code; 0 when unknown.
     */
    private function fieldId(int $rectypeId, string $name): int
    {
        if($this->isParentEntity($name)){
            return $this->definitions->localId('dty', self::PARENT_ENTITY);
        }
        $search = mb_strtolower(trim($name), 'UTF-8');
        $structure = $rectypeId > 0 ? $this->definitions->structureRows($rectypeId) : array();
        foreach($structure as $fieldId => $row){
            if((string)$fieldId === $search
                || mb_strtolower((string)preg_replace('/\s{2,}/', ' ', $row['name']), 'UTF-8') === $search
                || mb_strtolower($this->definitions->field($fieldId)['concept'] ?? '', 'UTF-8') === $search){
                return $fieldId;
            }
        }
        if(preg_match('/^\d+(-\d+)?$/', $search)){
            $fieldId = $this->definitions->localId('dty', $search);
            if($fieldId > 0){ return $fieldId; }
        }
        foreach($this->definitions->fields() as $fieldId => $field){
            if(mb_strtolower($field['name'], 'UTF-8') === $search){ return intval($fieldId); }
        }
        return 0;
    }

    /** Local record type id by concept code, id or name; 0 when unknown. */
    private function rectypeId(string $search): int
    {
        $search = trim($search);
        if(preg_match('/^\d+(-\d+)?$/', $search)){
            return $this->definitions->localId('rty', $search);
        }
        return $this->definitions->rectypeIdByName($search);
    }

    private function isParentEntity(string $name): bool
    {
        $name = mb_strtolower(trim($name), 'UTF-8');
        $local = $this->definitions->localId('dty', self::PARENT_ENTITY);
        return mb_strpos($name, 'parent entity') === 0 || mb_strpos($name, 'record parent') === 0
            || $name === self::PARENT_ENTITY || ($local > 0 && $name === (string)$local);
    }

    /**
     * Header and values of a record (forbidden fields left out), read once per fill.
     *
     * @return array{rectype:int,title:string,modified:string,values:array<int,array<int,array>>}|null
     */
    private function record(int $recordId): ?array
    {
        if(!array_key_exists($recordId, $this->cache)){
            $header = $this->records->header($recordId, array('rec_RecTypeID', 'rec_Title', 'rec_Modified'));
            $record = null;
            if($header !== null){
                $rectypeId = intval($header['rec_RecTypeID']);
                $structure = $this->definitions->structureRows($rectypeId);
                $record = array('rectype' => $rectypeId, 'title' => (string)$header['rec_Title'],
                    'modified' => (string)$header['rec_Modified'], 'values' => array());
                foreach($this->records->details(array($recordId), null, array('order' => 'field')) as $row){
                    if(($structure[$row['field']]['requirement'] ?? '') === 'forbidden'){ continue; }
                    $record['values'][$row['field']][] = $row;
                }
            }
            $this->cache[$recordId] = $record;
        }
        return $this->cache[$recordId];
    }

    private static function shorten(string $value, int $maxLength): string
    {
        return $maxLength > 0 && mb_strlen($value) > $maxLength ? mb_substr($value, 0, $maxLength).'...' : $value;
    }

    /**
     * Replace non-overlapping occurrences from left to right, earlier search strings first
     * at the same position (legacy array_str_replace), so that values are not replaced again.
     */
    private static function replaceInOrder(array $search, array $replace, string $subject): string
    {
        $result = '';
        while($subject !== ''){
            $index = -1;
            $offset = -1;
            foreach($search as $i => $needle){
                if($needle === ''){ continue; }
                $found = mb_strpos($subject, $needle);
                if($found !== false && ($offset === -1 || $found < $offset)){
                    $index = $i;
                    $offset = $found;
                }
            }
            if($index === -1){ return $result.$subject; }
            $result .= mb_substr($subject, 0, $offset).$replace[$index];
            $subject = mb_substr($subject, $offset + mb_strlen($search[$index]));
        }
        return $result;
    }
}
