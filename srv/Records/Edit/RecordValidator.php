<?php
/**
* RecordValidator.php - Checks and prepares the detail values of one record
*
* Port of _prepareDetails() of hserv/records/edit/recordModify.php in the new
* write format. Generic: it knows nothing about HTTP, permissions or the list
* of writable record types, so batch actions and database checks can reuse it.
*
* Checks, in order:
*   1. the record type exists;
*   2. every field id exists (error), is in the record structure (warning
*      otherwise) and is not forbidden (error);
*   3. values per field type: text (trimmed, scripts removed, freetext tag
*      attributes limited), date (Temporal), integer/float/year/boolean,
*      enum/relationtype (term of the field vocabulary, or its label),
*      resource (record exists, pointer constraint), file (ulf_ID exists),
*      geo (WKT parsed by the database); separator and relmarker are ignored;
*   4. required fields have a value; the default value is used when there is one;
*   5. more values than rst_MaxValues: warning;
*   6. language prefixes ("fre:") on the multilingual fields: unknown code is a warning.
*
* Value forms: compact (term id, record id, ulf_ID, text, date string or
* temporal JSON) or the resolved object form of GET /records (value, trm_ID,
* rec_ID, ulf_ID or file.ulf_ID are read); geo {geo:{type, wkt}} or a WKT
* string; {..., hidden:true} hides the value from the public (dtl_HideFromPublic).
*
* @project     Heurist academic knowledge management system
* @package     Records\Edit
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Edit;

use Heurist\Database\DatabaseException;
use Heurist\Database\DatabaseInterface;
use Heurist\Definitions\DefinitionLookup;
use Heurist\Records\Query\Compiler\RelationTermResolver;
use Heurist\Utilities\LanguageCodes;
use Heurist\Utilities\Temporal;

/** Validates detail values against the record structure. */
final class RecordValidator
{
    /** Field types that never have stored values. */
    private const NO_VALUE_TYPES = array('separator', 'relmarker');

    private DatabaseInterface $database;
    private DefinitionLookup $definitions;
    private LanguageCodes $languages;
    /** @var array<string,int> name, shortSummary, extendedDescription, cmsPage, cmsExtFiles */
    private array $codes;
    private ?bool $newTemporalFormat;
    /** @var array<int,array<int,bool>> dty => allowed term ids */
    private array $vocabularies = array();
    /** @var array<int,?int> rec id => record type (null = missing) */
    private array $targets = array();
    /** @var array<int,bool> ulf_ID => exists */
    private array $files = array();

    /**
     * @param DatabaseInterface $database Database of the record.
     * @param DefinitionLookup $definitions Lazy definitions.
     * @param LanguageCodes $languages Language codes of value prefixes.
     * @param array<string,int> $codes Local ids: name (DT_NAME), shortSummary (DT_SHORT_SUMMARY),
     *        extendedDescription (DT_EXTENDED_DESCRIPTION), cmsPage (RT_CMS_PAGE),
     *        cmsExtFiles (DT_CMS_EXTFILES).
     * @param bool|null $newTemporalFormat Store complex dates as JSON; null reads the database version (>= 1.3.14).
     */
    public function __construct(
        DatabaseInterface $database,
        DefinitionLookup $definitions,
        LanguageCodes $languages,
        array $codes = array(),
        ?bool $newTemporalFormat = null
    ) {
        $this->database = $database;
        $this->definitions = $definitions;
        $this->languages = $languages;
        $this->codes = array_map('intval', $codes);
        $this->newTemporalFormat = $newTemporalFormat;
    }

    /**
     * Validate the details of one record and prepare its recDetails rows.
     *
     * @param int $rectypeId Record type.
     * @param array<int,array<int,mixed>> $details dty => values (RecordInput::details()).
     * @param array $options checkPointers (default true): target records must exist and
     *        match the constraint; checkVocabulary (default true): terms must be in the
     *        field vocabulary; required (default true): check required fields.
     */
    public function validate(int $rectypeId, array $details, array $options = array()): ValidationResult
    {
        $result = new ValidationResult();
        if($rectypeId < 1 || $this->definitions->rectypeName($rectypeId) === ''){
            $result->error('rec_RecTypeID', null, 'rectype', 'Record type '.$rectypeId.' does not exist');
            return $result;
        }
        $structure = $this->definitions->structureRows($rectypeId);
        $checkPointers = $options['checkPointers'] ?? true;
        $checkVocabulary = $options['checkVocabulary'] ?? true;
        $filled = array();

        foreach($details as $fieldId => $values){
            $fieldId = intval($fieldId);
            $key = (string)$fieldId;
            $field = $this->definitions->field($fieldId);
            if($field === null){
                $result->error($key, null, 'unknown_field', 'Field '.$fieldId.' does not exist');
                continue;
            }
            $type = $field['type'];
            $row = $structure[$fieldId] ?? null;
            $name = $row['name'] ?? $field['name'];
            if($row === null){
                $result->warning($key, null, 'not_in_structure',
                    'Field "'.$name.'" is not in the structure of this record type');
                $constraints = $this->definitions->fieldConstraints($fieldId);
            }else{
                if($row['requirement'] === 'forbidden'){
                    $result->error($key, null, 'forbidden', 'Field "'.$name.'" is forbidden for this record type');
                    continue;
                }
                $constraints = array('targetTypes'=>$row['targetTypes'], 'vocabulary'=>$row['vocabulary']);
            }
            if(in_array($type, self::NO_VALUE_TYPES, true)){ continue; }

            $multilang = $this->isMultilang($rectypeId, $fieldId);
            $count = 0;
            foreach(array_values((array)$values) as $index => $value){
                $prepared = $this->prepareValue($rectypeId, $fieldId, $type, $name, $value, $index, $constraints,
                    $checkPointers, $checkVocabulary, $result);
                if($prepared === null){ continue; }
                foreach($prepared as $part){ $result->addRow($part); }
                $filled[$fieldId] = true;
                if($multilang && is_string($prepared[0]['value'] ?? null)){
                    if($this->languageCheck($prepared[0]['value'], $key, $index, $result)){ continue; }
                }
                $count++;
            }
            if($row !== null && $row['max'] > 0 && $count > $row['max']){
                $result->warning($key, null, 'max',
                    'Field "'.$name.'" allows '.$row['max'].' value'.($row['max'] > 1 ? 's' : '').', '.$count.' given');
            }
        }

        if($options['required'] ?? true){
            foreach($structure as $fieldId => $row){
                if($row['requirement'] !== 'required' || isset($filled[$fieldId])
                    || in_array($row['type'], self::NO_VALUE_TYPES, true)){
                    continue;
                }
                if(trim($row['default']) !== ''){
                    $result->addRow(array('dty'=>$fieldId, 'value'=>$row['default'], 'fileId'=>null, 'geo'=>null, 'hidden'=>null));
                }else{
                    $result->error((string)$fieldId, null, 'required', 'Field "'.$row['name'].'" is required');
                }
            }
        }
        return $result;
    }

    /** True for a valid non-owner visibility value. */
    public static function isVisibility($value): bool
    {
        return in_array($value, array('viewable', 'hidden', 'public', 'pending'), true);
    }

    /**
     * True for the fields that may have values in several languages ("xx:" prefixes):
     * DT_NAME, DT_SHORT_SUMMARY and DT_EXTENDED_DESCRIPTION, except the content of a
     * website page (RT_CMS_PAGE / DT_EXTENDED_DESCRIPTION, one joined value).
     */
    public function isMultilang(int $rectypeId, int $fieldId): bool
    {
        if($fieldId < 1){ return false; }
        if($fieldId === ($this->codes['extendedDescription'] ?? 0)){
            return $rectypeId !== ($this->codes['cmsPage'] ?? 0);
        }
        return $fieldId === ($this->codes['name'] ?? 0) || $fieldId === ($this->codes['shortSummary'] ?? 0);
    }

    /**
     * Rows of one value, or null when the value is empty or invalid (an issue is added).
     *
     * @return array<int,array{dty:int,value:?string,fileId:?int,geo:?string,hidden:?int}>|null
     */
    private function prepareValue(
        int $rectypeId, int $fieldId, string $type, string $name, $value, int $index,
        array $constraints, bool $checkPointers, bool $checkVocabulary, ValidationResult $result
    ): ?array {
        $key = (string)$fieldId;
        $hidden = null;
        if(is_array($value)){
            $hidden = !empty($value['hidden']) ? 1 : null;
            $value = $this->unpack($type, $value);
        }
        if(is_bool($value)){ $value = $value ? 'true' : 'false'; }
        if($value === null || (is_string($value) && trim($value) === '') || (is_array($value) && empty($value))){
            return null; // empty values are skipped, as in legacy
        }
        if(is_array($value) && $type !== 'date' && $type !== 'geo'){
            $result->error($key, $index, 'type', 'Value of field "'.$name.'" has an unknown form');
            return null;
        }
        $row = array('dty'=>$fieldId, 'value'=>null, 'fileId'=>null, 'geo'=>null, 'hidden'=>$hidden);

        switch($type){
            case 'freetext':
            case 'blocktext':
                $text = $this->cleanText($fieldId, $type, (string)$value);
                if($text === ''){ return null; }
                $limit = self::lengthLimit($text);
                if($limit > 0){
                    if($rectypeId === ($this->codes['cmsPage'] ?? -1) && $fieldId === ($this->codes['extendedDescription'] ?? -1)){
                        // website page content: split into several values, joined again on read
                        $rows = array();
                        for($start = 0; $start < mb_strlen($text); $start += $limit){
                            $rows[] = array_merge($row, array('value'=>mb_substr($text, $start, $limit)));
                        }
                        return $rows;
                    }
                    $result->error($key, $index, 'length', 'Value of field "'.$name.'" exceeds the maximum size of '
                        .($limit > 32000 ? 64 : 32).' KB');
                    return null;
                }
                $row['value'] = $text;
                return array($row);

            case 'date':
                $original = $value;
                try{
                    $valid = (new Temporal($original))->isValid();
                    $value = Temporal::getValueForRecDetails($value, $this->newTemporalFormat());
                }catch(\Throwable $exception){
                    $valid = false;
                }
                if(is_array($value)){ $value = json_encode($value, JSON_UNESCAPED_UNICODE); }
                if(!$valid){
                    $result->warning($key, $index, 'date', 'Value of field "'.$name.'" is not a valid date; stored as given');
                }
                $row['value'] = trim((string)$value);
                return array($row);

            case 'integer':
                if(!preg_match('/^\s*-?\d+\s*$/', (string)$value)){
                    $result->error($key, $index, 'type', 'Value "'.$value.'" of field "'.$name.'" is not a valid integer');
                    return null;
                }
                $row['value'] = trim((string)$value);
                return array($row);

            case 'float':
                if(!preg_match('/^\s*-?(?:\d+[.]?|\d*[.]\d+(?:[eE]-?\d+)?)\s*$/', (string)$value)){
                    $result->error($key, $index, 'type', 'Value "'.$value.'" of field "'.$name.'" is not a valid number');
                    return null;
                }
                $row['value'] = trim((string)$value);
                return array($row);

            case 'year':
                if(!preg_match('/^\s*(?:(?:-|ad\s*)?\d+(?:\s*bce?)?|in\s+press)\s*$/i', (string)$value)){
                    $result->error($key, $index, 'type', 'Value "'.$value.'" of field "'.$name.'" is not a valid year');
                    return null;
                }
                $row['value'] = trim((string)$value);
                return array($row);

            case 'boolean':
                if(!preg_match('/^(?:yes|true|no|false|1|0|T|F|Y|N)$/', (string)$value)){
                    $result->error($key, $index, 'type', 'Value "'.$value.'" of field "'.$name.'" is not a valid boolean');
                    return null;
                }
                $row['value'] = in_array((string)$value, array('1', 'T', 'Y', 'yes', 'true'), true) ? 'true' : 'false';
                return array($row);

            case 'enum':
            case 'relationtype':
                $termId = $this->termId($fieldId, (string)$value, $constraints['vocabulary'], $checkVocabulary);
                if($termId === null){
                    $term = is_numeric($value) ? $this->definitions->term(intval($value)) : null;
                    $result->error($key, $index, 'vocabulary', $term === null
                        ? 'Term "'.$value.'" of field "'.$name.'" does not exist'
                        : 'Term "'.$term['term'].'" is not in the list of values of field "'.$name.'"');
                    return null;
                }
                $row['value'] = (string)$termId;
                return array($row);

            case 'resource':
                if(!preg_match('/^\d+$/', trim((string)$value)) || intval($value) < 1){
                    $result->error($key, $index, 'pointer', 'Value "'.$value.'" of field "'.$name.'" is not a record id');
                    return null;
                }
                $targetId = intval($value);
                if($checkPointers){
                    $targetType = $this->targetType($targetId);
                    if($targetType === null){
                        $result->error($key, $index, 'pointer', 'Record '.$targetId.' of field "'.$name.'" does not exist');
                        return null;
                    }
                    if(!empty($constraints['targetTypes']) && !in_array($targetType, $constraints['targetTypes'], true)){
                        $result->error($key, $index, 'pointer', 'Record '.$targetId.' is of type "'
                            .$this->definitions->rectypeName($targetType).'", not allowed in field "'.$name.'"');
                        return null;
                    }
                }
                $row['value'] = (string)$targetId;
                return array($row);

            case 'file':
                if(!preg_match('/^\d+$/', trim((string)$value)) || intval($value) < 1){
                    $result->error($key, $index, 'file', 'Value of field "'.$name.'" is not a file id (ulf_ID); upload is not supported yet');
                    return null;
                }
                if(!$this->fileExists(intval($value))){
                    $result->error($key, $index, 'file', 'File '.intval($value).' of field "'.$name.'" does not exist');
                    return null;
                }
                $row['fileId'] = intval($value);
                return array($row);

            case 'geo':
                $geo = $this->prepareGeo($value);
                if($geo === null){
                    $result->error($key, $index, 'geo', 'Value of field "'.$name.'" is not a valid geometry (WKT)');
                    return null;
                }
                $row['value'] = $geo[0];
                $row['geo'] = $geo[1];
                return array($row);

            default:
                $row['value'] = trim((string)$value);
                return array($row);
        }
    }

    /** Value of the object form by field type (null when it has none). */
    private function unpack(string $type, array $value)
    {
        switch($type){
            case 'geo':
                return $value['geo'] ?? $value['value'] ?? null;
            case 'resource':
                return $value['rec_ID'] ?? $value['value'] ?? null;
            case 'enum':
            case 'relationtype':
                return $value['trm_ID'] ?? $value['value'] ?? null;
            case 'file':
                return $value['ulf_ID'] ?? ($value['file']['ulf_ID'] ?? null) ?? $value['value'] ?? null;
            case 'date':
                if(array_key_exists('value', $value)){ return $value['value']; }
                unset($value['hidden']);
                return $value; // temporal JSON object
            default:
                return $value['value'] ?? null;
        }
    }

    /**
     * Trim, remove script elements and (freetext) tag attributes other than src,
     * class, style and href, as legacy _prepareDetails.
     */
    private function cleanText(int $fieldId, string $type, string $value): string
    {
        $value = self::trim($value);
        if($fieldId === ($this->codes['cmsExtFiles'] ?? -1)){ return $value; }
        $value = (string)preg_replace('#<script(.*?)>(.*?)</script>#is', '', $value);
        if($type === 'freetext'){
            $allowed = array('src', 'class', 'style', 'href');
            $allowed2 = implode('=|', $allowed).'=';
            $regex = ')[^>]))*((?:';
            $allowed = implode('|', $allowed);
            $value = (string)preg_replace('#<([A-Z][A-Z0-9]*)(\s*)(?:(?:(?:(?!'.$allowed2.$regex.$allowed
                .')=[\'"][^\'"]*[\'"]\s*)?)(?:(?:(?:(?!'.$allowed2.$regex.$allowed
                .')=[\'"][^\'"]*[\'"]\s*)?)(?:(?:(?:(?!'.$allowed2.$regex.$allowed
                .')=[\'"][^\'"]*[\'"]\s*)?)[^>]*>#si', '<$1$2$3$4$5>', $value);
        }
        return $value;
    }

    /** Trim including non-breaking spaces and BOM (legacy super_trim). */
    private static function trim(string $value): string
    {
        return (string)preg_replace('/^[\s\x{00A0}\x{FEFF}]+|[\s\x{00A0}\x{FEFF}]+$/u', '', $value);
    }

    /**
     * Maximum bytes of one stored text value, or 0 when the value fits (legacy
     * checkMaxLength2: 64000, or 32000 for mostly multi-byte text).
     */
    private static function lengthLimit(string $value): int
    {
        $bytes = strlen($value);
        $limit = ($bytes - mb_strlen($value) < 200) ? 64000 : 32000;
        return $bytes > $limit ? $limit : 0;
    }

    /**
     * Language prefix check of a multilingual value. Returns true when the value
     * is a translation (it does not count for rst_MaxValues).
     */
    private function languageCheck(string $value, string $key, int $index, ValidationResult $result): bool
    {
        if(!preg_match('/^([A-Za-z]{2,3}|\*):/', $value, $match)){ return false; }
        $code = $match[1] === '*' ? 'ALL' : $this->languages->code3($match[1]);
        if($code === null){
            $result->warning($key, $index, 'language', 'Unknown language code "'.$match[1].'"');
            return false;
        }
        return true;
    }

    /** Term id of a value (id or label) allowed in the field, or null. */
    private function termId(int $fieldId, string $value, array $vocabulary, bool $checkVocabulary): ?int
    {
        $value = trim($value);
        $allowed = ($checkVocabulary && !empty($vocabulary)) ? $this->vocabulary($fieldId, $vocabulary) : null;
        if(preg_match('/^\d+$/', $value)){
            $termId = intval($value);
            if($this->definitions->term($termId) === null){ return null; }
            return ($allowed === null || isset($allowed[$termId])) ? $termId : null;
        }
        // label within the vocabulary (legacy getTermByLabel)
        $label = mb_strtolower($value);
        foreach(array_keys($allowed ?? array()) as $termId){
            $term = $this->definitions->term($termId);
            if($term !== null && mb_strtolower($term['term']) === $label){ return $termId; }
        }
        return null;
    }

    /** @return array<int,bool> Terms of a field vocabulary and their descendants. */
    private function vocabulary(int $fieldId, array $roots): array
    {
        if(!isset($this->vocabularies[$fieldId])){
            $terms = (new RelationTermResolver($this->database))->expand($roots);
            $this->vocabularies[$fieldId] = array_fill_keys($terms, true);
        }
        return $this->vocabularies[$fieldId];
    }

    private function targetType(int $recordId): ?int
    {
        if(!array_key_exists($recordId, $this->targets)){
            $type = $this->database->fetchValue('SELECT rec_RecTypeID FROM Records WHERE rec_ID=?', array($recordId));
            $this->targets[$recordId] = $type === null ? null : intval($type);
        }
        return $this->targets[$recordId];
    }

    private function fileExists(int $fileId): bool
    {
        if(!isset($this->files[$fileId])){
            $this->files[$fileId] = $this->database->fetchValue(
                'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_ID=?', array($fileId)
            ) !== null;
        }
        return $this->files[$fileId];
    }

    /**
     * Geo type code and WKT of a geo value ({type, wkt}, "p POINT(..)" or WKT),
     * checked by the database, or null (legacy prepareGeoValue).
     *
     * @param mixed $value
     * @return array{0:string,1:string}|null
     */
    private function prepareGeo($value): ?array
    {
        $type = '';
        if(is_array($value)){
            $type = strtolower(trim((string)($value['type'] ?? '')));
            $wkt = trim((string)($value['wkt'] ?? ''));
        }else{
            $wkt = trim((string)$value);
            if(preg_match('/^(pl|p|l|c|r|m)\s+(.+)$/s', $wkt, $match)){
                $type = $match[1];
                $wkt = trim($match[2]);
            }
        }
        if(!in_array($type, array('p', 'l', 'pl', 'c', 'r', 'm'), true)){
            $upper = strtoupper($wkt);
            if(strpos($upper, 'GEOMETRYCOLLECTION') !== false || strpos($upper, 'MULTI') !== false){
                $type = 'm';
            }elseif(strpos($upper, 'POINT') !== false){
                $type = 'p';
            }elseif(strpos($upper, 'LINESTRING') !== false){
                $type = 'l';
            }elseif(strpos($upper, 'POLYGON') !== false){
                $type = 'pl';
            }else{
                return null;
            }
        }
        if(!preg_match('/\d/', $wkt)){ return null; }
        try{
            $valid = $this->database->fetchValue('SELECT ST_AsText(ST_GeomFromText(?))', array($wkt));
        }catch(DatabaseException $exception){
            return null;
        }
        return $valid === null ? null : array($type, $wkt);
    }

    /** Complex dates as JSON when the database version is 1.3.14 or later. */
    private function newTemporalFormat(): bool
    {
        if($this->newTemporalFormat === null){
            $rows = $this->database->fetchAll(
                'SELECT sys_dbVersion,sys_dbSubVersion,sys_dbSubSubVersion FROM sysIdentification LIMIT 1'
            );
            $row = $rows[0] ?? array();
            $version = intval($row['sys_dbVersion'] ?? 0).'.'.intval($row['sys_dbSubVersion'] ?? 0)
                .'.'.intval($row['sys_dbSubSubVersion'] ?? 0);
            $this->newTemporalFormat = version_compare($version, '1.3.14', '>=');
        }
        return $this->newTemporalFormat;
    }
}
