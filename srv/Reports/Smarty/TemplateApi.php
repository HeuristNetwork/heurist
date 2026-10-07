<?php
/**
* TemplateApi.php - The $heurist object of report templates
*
* Same methods and results as the legacy hserv\report\ReportRecord, so existing
* templates run unchanged: getRecord, getRelatedRecords, getLinkedRecords,
* getLinkedFromRecords, getRecords, getRecordsAggr, getTranslation, getFileField,
* getRecordStructure, getFieldLabel, getFieldType, prepareRecord, getSysInfo,
* rty_Name, rty_id, dty_id, trm_id, constant, baseURL, getRecordThumbnail,
* recordIsVisible, composeRecLink, composeFileLink.
*
* Differences from the legacy class (fixed on purpose, plan 12 Phase 6):
* - only records the current user may view are returned (getRecord gives null);
* - getRecord($id) after getRecord($id, false) returns the full record;
* - composeRecLink returns the link (the legacy method printed it);
* - records of the report are loaded in batches when the template asks for the
*   first one of them (one query per batch, not per record).
*
* Progress and Stop: every getRecord() of a record of the report calls the tick
* callback (JobContext::progress/check), at most every 0.5 s.
*
* @project     Heurist academic knowledge management system
* @package     Reports\Smarty
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Reports\Smarty;

use Heurist\Database\DatabaseInterface;
use Heurist\Records\Data\RecordDataService;
use Heurist\Utilities\Temporal;

/** Data provider of report templates. */
final class TemplateApi
{
    /** Records loaded at once when the template asks for a record of the report. */
    private const PREFETCH = 100;
    /** The record cache is emptied above this size. */
    private const CACHE_LIMIT = 2500;
    private const TICK_SECONDS = 0.5;

    private ReportRecordAssembler $assembler;
    private ReportDefinitions $definitions;
    private ReportEnvironment $environment;
    private LanguageCodes $languages;
    private DatabaseInterface $database;
    private RecordDataService $data;
    /** @var callable fn(string $query): array - visible record ids of a query */
    private $search;
    /** @var callable fn(array $ids): array - visible ids */
    private $visibleIds;
    /** @var callable|null fn(string $name): mixed - RT_/DT_ constants */
    private $constants;
    /** @var array<string,int> relationship codes */
    private array $codes;

    /** @var array<int,array> id => template record */
    private array $records = array();
    /** @var array<int,array> id => record without values */
    private array $headers = array();
    /** @var array<int,int> ids of the report => position */
    private array $mainSet = array();
    private array $mainIds = array();
    private int $done = 0;
    /** @var callable|null fn(int $done, int $total): void */
    private $tick = null;
    private float $lastTick = 0.0;
    private ?string $language = null;

    /**
     * @param array $services assembler, definitions, environment, languages, database, data,
     *        search (callable), visibleIds (callable), constants (callable|null), codes (array).
     */
    public function __construct(array $services)
    {
        $this->assembler = $services['assembler'];
        $this->definitions = $services['definitions'];
        $this->environment = $services['environment'];
        $this->languages = $services['languages'];
        $this->database = $services['database'];
        $this->data = $services['data'];
        $this->search = $services['search'];
        $this->visibleIds = $services['visibleIds'];
        $this->constants = $services['constants'] ?? null;
        $this->codes = $services['codes'] ?? array();
    }

    /**
     * Records of the report (batched loading, progress) and the progress callback.
     *
     * @param array $ids Record ids of the report.
     * @param callable|null $tick fn(int $done, int $total): void; may throw to stop the run.
     */
    public function startRun(array $ids, ?callable $tick = null): void
    {
        $this->mainIds = array_values(array_map('intval', $ids));
        $this->mainSet = array_flip($this->mainIds);
        $this->done = 0;
        $this->tick = $tick;
        $this->lastTick = 0.0;
        $this->callTick(true);
    }

    /** Value of a constant such as RT_PERSON or DT_NAME (null when not defined). */
    public function constant($name, $smarty = null)
    {
        return $this->constants === null ? null : call_user_func($this->constants, (string)$name);
    }

    /** HEURIST_BASE_URL. */
    public function baseURL()
    {
        return $this->environment->baseUrl;
    }

    /**
     * db_total_records, db_rty_counts, lang, dbname or user.
     *
     * @param string $param
     * @return mixed
     */
    public function getSysInfo($param)
    {
        switch($param){
            case 'db_total_records':
                return $this->database->fetchValue('SELECT count(*) FROM Records WHERE NOT rec_FlagTemporary');
            case 'db_rty_counts':
                $counts = array();
                foreach($this->database->fetchRows(
                    'SELECT rec_RecTypeID,count(*) FROM Records WHERE NOT rec_FlagTemporary GROUP BY rec_RecTypeID'
                ) as $row){
                    $counts[$row[0]] = $row[1];
                }
                return $counts;
            case 'lang':
                return $this->language();
            case 'dbname':
                return $this->environment->databaseName;
            case 'user':
                return $this->environment->currentUser;
        }
        return null;
    }

    /** Name of a record type. */
    public function rty_Name($rtyId)
    {
        return $this->definitions->rectypeName($rtyId);
    }

    /** Local record type id of a concept code (0 when not found). */
    public function rty_id($conceptCode, $smarty = null)
    {
        return $this->definitions->localId('rty', $conceptCode);
    }

    /** Local field id of a concept code (0 when not found). */
    public function dty_id($conceptCode, $smarty = null)
    {
        return $this->definitions->localId('dty', $conceptCode);
    }

    /** Local term id of a concept code (0 when not found). */
    public function trm_id($conceptCode, $smarty = null)
    {
        return $this->definitions->localId('trm', $conceptCode);
    }

    /**
     * A record as a template array (`f<id>`, `f<id>s`, `recTitle`, ...), or null
     * when it does not exist or the user may not view it.
     *
     * @param mixed $rec Record id or record array (recID).
     * @param bool $details With field values (false: headers only).
     * @return array|null
     */
    public function getRecord($rec, $details = true, $smarty = null)
    {
        $id = intval(is_array($rec) && !empty($rec['recID']) ? $rec['recID'] : $rec);
        if($id < 1){ return null; }
        if(isset($this->records[$id])){
            return $this->records[$id];
        }
        if(isset($this->mainSet[$id])){
            $this->done++;
            $this->callTick();
        }
        if($details === false){
            if(!array_key_exists($id, $this->headers)){
                $raw = $this->assembler->loadRaw(array($id), false);
                $this->headers[$id] = isset($raw[$id]) ? $this->assembler->toSmarty($raw[$id], $this->language()) : null;
            }
            return $this->headers[$id];
        }
        $this->load($this->batchFor($id));
        return $this->records[$id] ?? null;
    }

    /** URL of the thumbnail of a record (its first image), or null. */
    public function getRecordThumbnail($rec, $smarty = null)
    {
        $id = intval(is_array($rec) && !empty($rec['recID']) ? $rec['recID'] : $rec);
        if(!in_array($id, call_user_func($this->visibleIds, array($id)))){ return null; }
        $rows = $this->data->loadRecords(array($id), array(), array(), array('virtuals' => array('rec_ThumbnailURL')));
        $url = $rows[0]['rec_ThumbnailURL'] ?? null;
        return empty($url) ? null : $url;
    }

    /**
     * Whether the user may view a record. Records given by this class are visible;
     * for a raw record (rec_ID) the access rules are checked.
     *
     * @param mixed $rec
     * @return bool
     */
    public function recordIsVisible($rec)
    {
        $id = intval(is_array($rec) ? ($rec['rec_ID'] ?? $rec['recID'] ?? 0) : $rec);
        return $id > 0 && in_array($id, call_user_func($this->visibleIds, array($id)));
    }

    /**
     * Records related to a record by relationships, each with recRelationID,
     * recRelationType, recRelationNotes, recRelationStartDate, recRelationEndDate
     * and relationRecord.
     *
     * @param mixed $rec Record id or record array.
     * @return array
     */
    public function getRelatedRecords($rec, $smarty = null)
    {
        $id = intval(is_array($rec) ? ($rec['recID'] ?? 0) : $rec);
        if($id < 1){ return array(); }
        $links = array();
        foreach($this->database->fetchRows(
            'SELECT rl_RelationID,rl_TargetID,1 FROM recLinks WHERE rl_RelationID IS NOT NULL AND rl_SourceID=? '
            .'UNION ALL SELECT rl_RelationID,rl_SourceID,0 FROM recLinks WHERE rl_RelationID IS NOT NULL AND rl_TargetID=?',
            array($id, $id)
        ) as $row){
            $links[] = array(intval($row[0]), intval($row[1]), intval($row[2]) === 1);
        }
        if(empty($links)){ return array(); }

        $ids = array();
        foreach($links as $link){ $ids[] = $link[0]; $ids[] = $link[1]; }
        $this->load($ids);
        $result = array();
        foreach($links as list($relationId, $otherId, $isPrimary)){
            $relation = $this->records[$relationId] ?? null;
            $record = $this->records[$otherId] ?? null;
            $raw = $this->rawRelation($relationId);
            if($relation === null || $record === null || $raw === null){ continue; }
            $typeId = intval($this->firstValue($raw, 'DT_RELATION_TYPE'));
            if($typeId > 0 && !$isPrimary){
                $typeId = $this->definitions->inverseTerm($typeId);
            }
            $type = $typeId > 0 ? $this->definitions->term($typeId) : null;
            if($type === null){ continue; }
            $record['recRelationID'] = $relationId;
            $record['recRelationType'] = $type['term'];
            $record['recRelationNotes'] = $this->firstValue($raw, 'DT_SHORT_SUMMARY');
            $record['recRelationStartDate'] = Temporal::toHumanReadable($this->firstValue($raw, 'DT_START_DATE'));
            $record['recRelationEndDate'] = Temporal::toHumanReadable($this->firstValue($raw, 'DT_END_DATE'));
            $record['relationRecord'] = $relation;
            $result[] = $record;
        }
        return $result;
    }

    /**
     * Ids of records linked by pointer fields: ['linkedto' => [...], 'linkedfrom' => [...]].
     *
     * @param mixed $rec Record id or record array.
     * @param mixed $rtyId Only linked records of this type (or list of types).
     * @param string|null $direction linkedto, linkedfrom or null for both.
     * @param mixed $dtyId Only this pointer field.
     * @return array
     */
    public function getLinkedRecords($rec, $rtyId = null, $direction = null, $dtyId = null, $smarty = null)
    {
        $id = intval(is_array($rec) && !empty($rec['recID']) ? $rec['recID'] : $rec);
        $result = array('linkedto' => array(), 'linkedfrom' => array());
        if($id < 1){ return $result; }
        foreach(array('linkedto' => array('rl_TargetID', 'rl_SourceID'), 'linkedfrom' => array('rl_SourceID', 'rl_TargetID')) as $key => $columns){
            if($direction !== null && $direction !== $key){ continue; }
            $sql = 'SELECT '.$columns[0].' FROM recLinks';
            $parameters = array();
            $types = $this->idList($rtyId);
            if(!empty($types)){
                $sql .= ' JOIN Records ON rec_ID='.$columns[0].' AND rec_RecTypeID IN ('.implode(',', array_fill(0, count($types), '?')).')';
                $parameters = $types;
            }
            $sql .= ' WHERE rl_RelationID IS NULL AND '.$columns[1].'=?';
            $parameters[] = $id;
            if(intval($dtyId) > 0){
                $sql .= ' AND rl_DetailTypeID=?';
                $parameters[] = intval($dtyId);
            }
            $ids = array_map('intval', $this->database->fetchColumn($sql, $parameters));
            $visible = array_flip(call_user_func($this->visibleIds, $ids));
            foreach($ids as $linked){
                if(isset($visible[$linked])){ $result[$key][] = (string)$linked; }
            }
        }
        return $result;
    }

    /** Ids of records that point to a record (of a type, by a field). */
    public function getLinkedFromRecords($rec, $rtyId, $dtyId, $smarty = null)
    {
        return $this->getLinkedRecords($rec, $rtyId, 'linkedfrom', $dtyId)['linkedfrom'];
    }

    /**
     * Ids of the records of a query; "[ID]" is replaced by the current record.
     *
     * @param mixed $query Query text or JSON array.
     * @param mixed $currentRec Current record (id or array).
     * @return array|null
     */
    public function getRecords($query, $currentRec = null)
    {
        $id = intval(is_array($currentRec) && !empty($currentRec['recID']) ? $currentRec['recID'] : $currentRec);
        if(is_array($query)){
            $query = json_encode($query);
        }
        $query = (string)$query;
        if(strpos($query, '[ID]') !== false){
            if($id < 1){ return null; }
            $query = str_replace('[ID]', (string)$id, $query);
        }
        try{
            return array_map('strval', call_user_func($this->search, $query));
        }catch(\Throwable $exception){
            return null;
        }
    }

    /**
     * Count, sum or average of fields over records ([dty, 'avg'|'sum'|'count'], ...).
     *
     * @param array $functions One [dty, function] or a list of them (0 = count records).
     * @param mixed $queryOrIds Ids (array or "1,2,3") or a query.
     * @param mixed $currentRec Current record for "[ID]" in a query.
     * @return mixed One value for one function, else [[dty, function, value], ...]; null on error.
     */
    public function getRecordsAggr($functions, $queryOrIds, $currentRec = null)
    {
        $ids = $this->idList($queryOrIds);
        if(empty($ids)){
            $ids = array_map('intval', $this->getRecords($queryOrIds, $currentRec) ?? array());
        }else{
            $ids = array_map('intval', call_user_func($this->visibleIds, $ids));
        }
        if(is_array($functions) && count($functions) === 2 && !is_array($functions[0])){
            $functions = array($functions);
        }
        if(is_array($functions) && count($functions) === 1 && $functions[0] == 0){
            return count($ids);
        }
        $select = array();
        $joins = array();
        $result = array();
        $index = 0;
        foreach((array)$functions as $function){
            $dtyId = intval($function[0] ?? 0);
            $type = (string)($function[1] ?? '');
            if(!in_array($type, array('avg', 'sum', 'count'), true)){ continue; }
            if($dtyId > 0){
                $select[] = $type.'(d'.$index.'.dtl_Value)';
                $joins[] = 'JOIN recDetails d'.$index.' ON rec_ID=d'.$index.'.dtl_RecID AND d'.$index.'.dtl_DetailTypeID='.$dtyId;
            }else{
                $select[] = 'count(DISTINCT rec_ID)';
            }
            $result[] = array($dtyId, $type, 0);
            $index++;
        }
        if(empty($select) || empty($ids)){ return null; }
        $rows = $this->database->fetchRows(
            'SELECT '.implode(',', $select).' FROM Records '.implode(' ', $joins)
            .' WHERE rec_ID IN ('.implode(',', array_fill(0, count($ids), '?')).')', $ids
        );
        if(empty($rows)){ return null; }
        if(count($rows[0]) === 1){ return $rows[0][0]; }
        foreach($rows[0] as $position => $value){ $result[$position][2] = $value; }
        return $result;
    }

    /**
     * Translations of terms (trm), files (ulf), record types (rty) or fields (dty).
     *
     * @param string $entity
     * @param mixed $ids
     * @param string|null $field
     * @param string|null $languageCode
     * @return string|array
     */
    public function getTranslation($entity, $ids, $field = null, $languageCode = null)
    {
        $lang = $this->languages->code3($languageCode ?? $this->language()) ?? $this->language();
        return $this->definitions->translation((string)$entity, $ids, $field === null ? null : (string)$field, $lang);
    }

    /**
     * Field of uploaded files: desc, cap, rights, owner, ext or name (also as the |file_data modifier).
     *
     * @param mixed $files File info(s) or file URLs ("...&file=abc").
     * @param string $field
     * @return mixed
     */
    public function getFileField($files, $field = 'name')
    {
        $map = array(
            'desc' => 'ulf_Description', 'description' => 'ulf_Description', 'cap' => 'ulf_Caption', 'caption' => 'ulf_Caption',
            'rights' => 'ulf_Copyright', 'copyright' => 'ulf_Copyright', 'owner' => 'ulf_Copyowner', 'copyowner' => 'ulf_Copyowner',
            'type' => 'ulf_MimeExt', 'ext' => 'ulf_MimeExt', 'extension' => 'ulf_MimeExt',
            'filename' => 'ulf_OrigFileName', 'name' => 'ulf_OrigFileName'
        );
        $column = $map[$field] ?? '';
        if($column === ''){ return $files; }
        $results = array();
        if(is_array($files)){
            if(isset($files['ulf_ID']) || isset($files['ulf_ObfuscatedFileID'])){
                $files = array($files);
            }
            foreach($files as $file){
                $results[] = is_array($file) ? ($file[$column] ?? '') : '';
            }
            return count($results) === 1 ? $results[0] : $results;
        }
        foreach(explode(',', (string)$files) as $url){
            $query = array();
            parse_str((string)parse_url(trim($url), PHP_URL_QUERY), $query);
            $fileId = $query['file'] ?? null;
            if(is_string($fileId) && preg_match('/^[a-z0-9]+$/', $fileId)){
                $results[] = (string)$this->database->fetchValue(
                    'SELECT '.$column.' FROM recUploadedFiles WHERE ulf_ObfuscatedFileID=? LIMIT 1', array($fileId), ''
                );
            }
        }
        return count($results) === 1 ? $results[0] : $results;
    }

    /** Fields of the record's type in form order: [dty => display name], or null. */
    public function getRecordStructure($rec)
    {
        $rtyId = intval(is_array($rec) ? ($rec['recTypeID'] ?? 0) : 0);
        if($rtyId < 1){ return null; }
        $structure = $this->definitions->structure($rtyId);
        return empty($structure) ? null : $structure;
    }

    /** Display name of a field in the record's type (also as the |label modifier). */
    public function getFieldLabel($rec, $dtyId)
    {
        $structure = $this->getRecordStructure($rec);
        return $structure[intval($dtyId)] ?? 'Field '.$dtyId;
    }

    /** Base type of a field ('relmarker' for ids below 1). */
    public function getFieldType($dtyId)
    {
        return intval($dtyId) < 1 ? 'relmarker' : $this->definitions->fieldType($dtyId);
    }

    /**
     * Marks empty field groups: a separator whose fields are all empty gets
     * 'empty'; recGroupCount = number of groups with values.
     *
     * @param mixed $rec Template record.
     * @return mixed
     */
    public function prepareRecord($rec, $lang = null)
    {
        $structure = $this->getRecordStructure($rec);
        if(!is_array($rec) || $structure === null){ return $rec; }
        $separator = '';
        $groups = 0;
        $empty = false;
        foreach($structure as $dtyId => $label){
            $key = 'f'.$dtyId;
            if($this->getFieldType($dtyId) === 'separator'){
                if($separator !== ''){
                    if($empty){ $rec[$separator] = 'empty'; }else{ $groups++; }
                }
                $separator = $key;
                $empty = true;
                continue;
            }
            if(($rec[$key] ?? null) != null){
                $empty = false;
            }
        }
        if($separator !== ''){
            if($empty){ $rec[$separator] = 'empty'; }else{ $groups++; }
        }
        $rec['recGroupCount'] = $groups;
        return $rec;
    }

    /**
     * Link to a record shown by a template (popup), or its title when no template
     * is given; '' when the record is not visible.
     *
     * @param mixed $recId
     * @param string|null $templateName
     * @return string
     */
    public function composeRecLink($recId, $templateName)
    {
        $record = $this->getRecord($recId, false);
        if($record === null){ return ''; }
        $title = ReportText::sanitize($record['recTitle'] ?? '', ReportText::TITLE_TAGS);
        if($templateName == null){ return $title; }
        $url = $this->environment->baseUrl.'?db='.$this->environment->databaseName
            .'&template='.$templateName.'&q=ids:'.intval($recId);
        return '<a href="'.$url.'" target="_popup" onclick="open_link(this)">'.$title.'</a>';
    }

    /** HTML link of an uploaded file. */
    public function composeFileLink($fileinfo)
    {
        return is_array($fileinfo) ? $this->assembler->fileLink($fileinfo) : '';
    }

    /** Report language (3 letters): request "lang", else the user's language. */
    public function language(): string
    {
        if($this->language === null){
            $requested = isset($_REQUEST['lang']) && is_string($_REQUEST['lang']) ? $_REQUEST['lang'] : '';
            $this->language = $this->languages->code3($requested) ?? $this->languages->code3($this->environment->language) ?? 'ENG';
        }
        return $this->language;
    }

    /** Load records that are not cached yet. */
    private function load(array $ids): void
    {
        $ids = array_values(array_filter(array_unique(array_map('intval', $ids)), function($id){
            return $id > 0 && !isset($this->records[$id]);
        }));
        if(empty($ids)){ return; }
        if(count($this->records) + count($ids) > self::CACHE_LIMIT){
            $this->records = array();
        }
        $lang = $this->language();
        foreach($this->assembler->loadRaw($ids) as $id => $raw){
            $this->records[$id] = $this->assembler->toSmarty($raw, $lang);
            $this->raw[$id] = $raw;
        }
        if(count($this->raw) > self::CACHE_LIMIT){
            $this->raw = array();
        }
    }

    /** @var array<int,array> raw records of the cache (relationship details) */
    private array $raw = array();

    /** A record of the report and the next ones not loaded yet; any other record alone. */
    private function batchFor(int $id): array
    {
        if(!isset($this->mainSet[$id])){
            return array($id);
        }
        $batch = array();
        $count = count($this->mainIds);
        for($i = $this->mainSet[$id]; $i < $count && count($batch) < self::PREFETCH; $i++){
            $candidate = $this->mainIds[$i];
            if(!isset($this->records[$candidate])){ $batch[] = $candidate; }
        }
        return $batch;
    }

    /** Raw values of a relationship record. */
    private function rawRelation(int $relationId): ?array
    {
        if(!isset($this->raw[$relationId])){
            $loaded = $this->assembler->loadRaw(array($relationId));
            $this->raw[$relationId] = $loaded[$relationId] ?? null;
        }
        return $this->raw[$relationId];
    }

    /** First value of a field given by its constant name. */
    private function firstValue(array $raw, string $code)
    {
        $dtyId = $this->codes[$code] ?? 0;
        return $dtyId > 0 ? ($raw['details'][$dtyId][0] ?? null) : null;
    }

    /** Ids of an array or a comma-separated list (empty when it is not a list of ids). */
    private function idList($value): array
    {
        if(is_int($value) || (is_string($value) && preg_match('/^\s*\d+(\s*,\s*\d+)*\s*$/', $value))){
            $value = explode(',', (string)$value);
        }
        if(!is_array($value)){ return array(); }
        $ids = array();
        foreach($value as $item){
            if(is_int($item) || (is_string($item) && ctype_digit(trim($item)))){
                $ids[] = intval($item);
            }elseif(!is_numeric($item)){
                return array();
            }
        }
        return array_values(array_filter($ids, static function($id){ return $id > 0; }));
    }

    /** Progress callback, at most every TICK_SECONDS (always when forced). */
    private function callTick(bool $force = false): void
    {
        if($this->tick === null){ return; }
        $now = microtime(true);
        if(!$force && $this->lastTick > 0 && $now - $this->lastTick < self::TICK_SECONDS){ return; }
        $this->lastTick = $now;
        call_user_func($this->tick, $this->done, count($this->mainIds));
    }
}
