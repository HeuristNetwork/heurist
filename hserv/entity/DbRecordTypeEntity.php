<?php
/**
* DbRecordTypeEntity.php - Abstract base class for record-type-backed entities.
*
* These entities are not stored in dedicated system tables such as defRecTypes or
* defTerms. They are user records of a known record type, stored in Records and
* recDetails, but exposed through a specialised server-side API class.
*/
namespace hserv\entity;

use hserv\entity\DbEntityBase;
use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/../records/edit/recordModify.php';
require_once dirname(__FILE__).'/../structure/import/dbsImport.php';
require_once dirname(__FILE__).'/../structure/dbsTerms.php';
require_once dirname(__FILE__).'/../utilities/ULocale.php';

/**
 * Base class for special user-defined record types such as IIIF Manifest,
 * IIIF Canvas and IIIF Annotation.
 */
abstract class DbRecordTypeEntity extends DbEntityBase
{
    /** @var string|null RT_* constant name for the record type handled by this class. */
    protected $recordTypeConst = null;

    /** @var string|null Concept code of the record type, used for optional definition import. */
    protected $recordTypeConceptCode = null;

    /** @var array Required RT_* and DT_* constants. */
    protected $requiredConstants = array();

    /** @var array Local TRM_* constants for this entity: const name => concept code. */
    protected $requiredTermConstants = array();

    /** @var \DbsTerms|null cached terms helper for optional label lookup. */
    private static $terms = null;
    
    protected $definitionsChecked = false;
    protected $definitionsReady = false;
    protected $definitionsCheckedWithAutoImport = false;
    

    public function __construct($system, $data=null)
    {
        $this->system = $system;
        $this->data = $data;
        $this->initRecordTypeEntity();
    }

    /** Initialise record-type-specific constants and metadata in subclass. */
    abstract protected function initRecordTypeEntity(): void;

    /** Record-type-backed entities do not use DbEntityBase JSON entity config. */
    public function isvalid()
    {
        return true;
    }

    /** Avoid DbEntityBase::setData(), which attempts to load a table-entity config. */
    public function setData($data)
    {
        $this->data = $data;
        $this->records = null;
    }

    /** Resolve numeric ID, RT_/DT_/TRM_ constant name or return null. */
    protected function constId($nameOrId): ?int
    {
        if(is_numeric($nameOrId) && intval($nameOrId)>0){
            return intval($nameOrId);
        }
        if(is_string($nameOrId) && defined($nameOrId)){
            $val = constant($nameOrId);
            return (is_numeric($val) && intval($val)>0) ? intval($val) : null;
        }
        return null;
    }

    /** Return local record type ID handled by this entity. */
    protected function recordTypeId(): int
    {
        return intval($this->constId($this->recordTypeConst));
    }

    /** Define all required RT_* and DT_* constants. */
    protected function defineRequiredConstants(bool $refresh=false): void
    {
        foreach($this->requiredConstants as $name){
            $this->system->defineConstant($name, $refresh);
        }
    }

    /** Define one local term constant from a concept code. */
    protected function defineTermConstant(string $constName, string $conceptCode, bool $refresh=false): bool
    {
        if(!$refresh && defined($constName) && intval(constant($constName))>0){
            return true;
        }

        $id = ConceptCode::getTermLocalID($conceptCode);
        if($id>0){
            if(!defined($constName)){
                define($constName, intval($id));
            }
            return true;
        }
        return false;
    }

    /** Define entity-local TRM_* constants after record/detail definitions are available. */
    protected function defineRequiredTermConstants(bool $refresh=false): void
    {
        foreach($this->requiredTermConstants as $constName=>$conceptCode){
            if(is_string($conceptCode) && preg_match('/^[1-9][0-9]*-[1-9][0-9]*$/', $conceptCode)){
                $this->defineTermConstant($constName, $conceptCode, $refresh);
            }
        }
    }

    protected function missingRequiredConstants(): array
    {
        $missing = array();
        foreach($this->requiredConstants as $name){
            if(!defined($name) || intval(constant($name))<1){
                $missing[] = $name;
            }
        }
        return $missing;
    }

    protected function missingRequiredTermConstants(): array
    {
        $missing = array();
        foreach($this->requiredTermConstants as $constName=>$conceptCode){
            // Empty concept codes are allowed as placeholders until terms are finalised.
            if(!is_string($conceptCode) || !preg_match('/^[1-9][0-9]*-[1-9][0-9]*$/', $conceptCode)){
                continue;
            }
            if(!defined($constName) || intval(constant($constName))<1){
                $missing[] = $constName.'='.$conceptCode;
            }
        }
        return $missing;
    }
    
    protected function ensureDefinitionsReady(bool $autoImport=false): bool
    {
        if($this->definitionsChecked){
            if($this->definitionsReady){
                return true;
            }

            // Previously checked without auto-import; now allow one retry with auto-import.
            if(!$autoImport || $this->definitionsCheckedWithAutoImport){
                return false;
            }
        }

        $this->definitionsChecked = true;
        $this->definitionsCheckedWithAutoImport = $autoImport;
        $this->definitionsReady = $this->checkRequiredDefinitions($autoImport);

        return $this->definitionsReady;
    }    

    /**
     * Check RT_ / DT_ / local TRM_ definitions. Optionally import the required record type.
     * Admin tools can pass true. Public/read routes and ordinary-user saves should pass false.
     */
    private function checkRequiredDefinitions(bool $autoImport=false): bool
    {
        $this->defineRequiredConstants(false);
        $this->defineRequiredTermConstants(false);

        $missing = array_merge($this->missingRequiredConstants(), $this->missingRequiredTermConstants());
        if(empty($missing)){
            return true;
        }

        if($autoImport && $this->recordTypeConceptCode){
            $importDef = new \DbsImport($this->system);
            if($importDef->checkAndImportRty($this->recordTypeConceptCode)){
                $this->defineRequiredConstants(true);
                $this->defineRequiredTermConstants(true);
                $missing = array_merge($this->missingRequiredConstants(), $this->missingRequiredTermConstants());
            }
        }

        if(!empty($missing)){
            $this->system->addError(
                HEURIST_ACTION_BLOCKED,
                'Required definitions are missing: '.implode(', ', $missing)
            );
            return false;
        }
        return true;
    }

    /** Load recDetails into recordSave-compatible detail array.
     *
     * @param int $recordId Record ID.
     * @param array|null $detailIds Optional list of DT_* constant names or numeric detail type IDs.
     */
    protected function loadRecordDetails(int $recordId, ?array $detailIds=null): array
    {
        $details = array();
        if($recordId<1){
            return $details;
        }

        $where = 'dtl_RecID='.intval($recordId);

        if($detailIds !== null){
            $ids = array();
            foreach($detailIds as $detailId){
                $id = $this->constId($detailId);
                if($id && !in_array($id, $ids, true)){
                    $ids[] = intval($id);
                }
            }

            if(empty($ids)){
                return $details;
            }

            $where .= ' AND dtl_DetailTypeID IN ('.implode(',', $ids).')';
        }

        $query = 'SELECT dtl_DetailTypeID, dtl_Value, ST_asWKT(dtl_Geo), dtl_UploadedFileID '
            .'FROM recDetails WHERE '.$where.' ORDER BY dtl_DetailTypeID, dtl_ID';
        $dets = mysql__select_all($this->system->getMysqli(), $query);
        if(!$dets){
            return $details;
        }

        foreach($dets as $row){
            $fieldType = intval($row[0]);
            if($row[3]){
                $value = $row[3];
            }elseif($row[2]){
                $value = $row[1].' '.$row[2];
            }else{
                $value = $row[1];
            }
            $details[$fieldType][] = $value;
        }
        return $details;
    }

    /** Directly update or insert a single recDetails value without invoking recordSave hooks. */
    protected function updateSingleDetailDirect(int $recID, $detailId, $value): bool
    {
        $dtID = $this->constId($detailId);
        if($recID<1 || !$dtID){
            return false;
        }

        $mysqli = $this->system->getMysqli();
        $recID = intval($recID);
        $dtID = intval($dtID);
        $valueSql = is_numeric($value)
            ? (string)$value
            : '"'.$mysqli->real_escape_string((string)$value).'"';

        $dtlID = mysql__select_value($mysqli,
            'SELECT dtl_ID FROM recDetails WHERE dtl_RecID='.$recID
            .' AND dtl_DetailTypeID='.$dtID.' LIMIT 1');

        if($dtlID>0){
            $query = 'UPDATE recDetails SET dtl_Value='.$valueSql.' WHERE dtl_ID='.intval($dtlID);
        }else{
            $query = 'INSERT INTO recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) VALUES ('
                .$recID.','.$dtID.','.$valueSql.')';
        }

        return $mysqli->query($query) !== false;
    }

    /** Replace all values for a field. */
    protected function setField(array &$details, $field, $value): bool
    {
        if($value===null || $value===''){
            return false;
        }
        $id = $this->constId($field);
        if(!$id){
            return false;
        }

        $new = is_array($value)
            ? array_values(array_filter($value, function($v){ return $v!==null && $v!==''; }))
            : array($value);
        if(empty($new)){
            return false;
        }

        $old = $details[$id] ?? null;
        $details[$id] = $new;
        return $old !== $new;
    }

    /** Append a value only if it is not already present. */
    protected function appendUniqueField(array &$details, $field, $value): bool
    {
        if($value===null || $value===''){
            return false;
        }
        $id = $this->constId($field);
        if(!$id){
            return false;
        }
        if(!isset($details[$id])){
            $details[$id] = array();
        }
        if(array_search($value, $details[$id])===false){
            $details[$id][] = $value;
            return true;
        }
        return false;
    }

    protected function removeField(array &$details, $field): bool
    {
        $id = $this->constId($field);
        if($id && isset($details[$id])){
            unset($details[$id]);
            return true;
        }
        return false;
    }

    protected function getFirstDetailValue(array $details, $field)
    {
        $id = $this->constId($field);
        return $id ? ($details[$id][0] ?? null) : null;
    }

    protected function getDetailValues(array $details, $field): array
    {
        $id = $this->constId($field);
        return $id ? ($details[$id] ?? array()) : array();
    }

    /**
     * Resolve term ID from numeric ID, TRM_* constant, concept code or optional label.
     * Labels are only a fallback; code should prefer numeric values, constants or concept codes.
     */
    protected function getTermId($value, $fallback=null): ?int
    {
        if($value===null || $value===''){
            return $fallback ? $this->getTermId($fallback) : null;
        }

        if(is_numeric($value) && intval($value)>0){
            return intval($value);
        }

        if(is_string($value) && defined($value)){
            return intval(constant($value));
        }

        if(is_string($value) && preg_match('/^[1-9][0-9]*-[1-9][0-9]*$/', $value)){
            $id = ConceptCode::getTermLocalID($value);
            return $id ? intval($id) : ($fallback ? $this->getTermId($fallback) : null);
        }

        $id = $this->getTermIdByLabel((string)$value);
        if($id){
            return $id;
        }

        return $fallback ? $this->getTermId($fallback) : null;
    }

    /** Optional label lookup for legacy/imported literal values. */
    protected function getTermIdByLabel(string $label): ?int
    {
        $label = trim($label);
        if($label===''){
            return null;
        }

        if(!self::$terms && function_exists('dbs_GetTerms') && class_exists('DbsTerms')){
            self::$terms = new \DbsTerms($this->system, dbs_GetTerms($this->system));
        }
        if(self::$terms){
            $id = self::$terms->getTermByLabel('enum', $label);
            return $id ? intval($id) : null;
        }
        return null;
    }

    protected function getTermIdByCode($vocabConstOrId, ?string $termCode): ?int
    {
        if($termCode === null || trim($termCode) === ''){
            return null;
        }

        $vocabId = $this->constId($vocabConstOrId);
        if(!$vocabId){
            return null;
        }

        if(!self::$terms && function_exists('dbs_GetTerms') && class_exists('DbsTerms')){
            self::$terms = new \DbsTerms($this->system, dbs_GetTerms($this->system));
        }

        if(!self::$terms){
            return null;
        }

        $id = self::$terms->getTermByCode($vocabId, $termCode);
        return $id ? intval($id) : null;
    } 
    
    protected function getLanguageTermId($lang): ?int
    {
        if($lang === null || $lang === ''){
            return null;
        }

        if(is_numeric($lang) && intval($lang) > 0){
            return intval($lang);
        }

        $lang3 = getLangCode3((string)$lang);
        if(!$lang3){
            return null;
        }

        return $this->getTermIdByCode('TRM_VOCAB_LANGUAGE', $lang3);
    }       
    
    protected function hasTermState(array $details, array $termConstNames, $stateField='DT_ANNOTATION_STATE'): bool
    {
        $state = intval($this->getFirstDetailValue($details, $stateField));
        if($state<1){
            return false;
        }
        foreach($termConstNames as $constName){
            $id = $this->getTermId($constName);
            if($id && $state===$id){
                return true;
            }
        }
        return false;
    }

    protected function findRecordByField($field, $value, array $extraDetailFilters=array()): int
    {
        $fieldId = $this->constId($field);
        $rtyId = $this->recordTypeId();
        if(!$fieldId || !$rtyId || $value===null || $value===''){
            return 0;
        }

        $mysqli = $this->system->getMysqli();
        $query = 'SELECT d.dtl_RecID FROM recDetails d, Records r ';
        $where = 'r.rec_ID=d.dtl_RecID AND r.rec_RecTypeID='.intval($rtyId)
            .' AND d.dtl_DetailTypeID='.intval($fieldId)
            .' AND d.dtl_Value="'.addslashes($value).'"';

        $i = 0;
        foreach($extraDetailFilters as $extraField=>$extraValue){
            $extraId = $this->constId($extraField);
            if(!$extraId || $extraValue===null || $extraValue===''){
                continue;
            }
            $alias = 'dx'.$i++;
            $query .= ', recDetails '.$alias.' ';
            $where .= ' AND '.$alias.'.dtl_RecID=d.dtl_RecID'
                .' AND '.$alias.'.dtl_DetailTypeID='.intval($extraId)
                .' AND '.$alias.'.dtl_Value="'.addslashes($extraValue).'"';
        }

        $recId = mysql__select_value($mysqli, $query.SQL_WHERE.$where.' LIMIT 1');
        return $recId ? intval($recId) : 0;
    }

    protected function makeRecord(array $details, int $recordId=0): array
    {
        return array(
            'ID' => $recordId,
            'RecTypeID' => $this->recordTypeId(),
            'no_validation' => 'ignore_all',
            'details' => $details
        );
    }

    protected function saveRecordDetails(int $recordId, array $details, int $updateMode=0)
    {
        return recordSave($this->system, $this->makeRecord($details, $recordId), false, true, $updateMode);
    }

    protected function jsonEncode($value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    }

    protected function getJsonId(array $json): ?string
    {
        return $json['id'] ?? ($json['@id'] ?? null);
    }

    /**
     * Convert a IIIF language-bearing value to one or more Heurist text values.
     *
     * Heurist stores language-qualified text as a simple prefix, for example:
     *   FRE:Chat
     * IIIF Presentation v3 stores the same value as a language map, for example:
     *   {"fr":["Chat"]}
     *
     * This helper accepts v3 language maps, v2 @value/@language objects, plain
     * strings and arrays of either form. Values with language code "none" are
     * stored without a prefix. Unknown language codes are left unprefixed rather
     * than blocked.
     *
     * @param mixed $value IIIF language map, v2 language object, string or list.
     * @return array<int,string> Heurist-style text values.
     */
    protected function normaliseLangValues($value): array
    {
        if($value===null || $value===''){
            return array();
        }

        if(is_string($value) || is_numeric($value)){
            $text = trim((string)$value);
            return $text!=='' ? array($text) : array();
        }

        if(!is_array($value)){
            return array();
        }

        // IIIF v2 language value: {"@value":"Chat", "@language":"fr"}
        // or similar shape: {"value":"Chat", "language":"fr"}.
        if(isset($value['@value']) || isset($value['value'])){
            $text = isset($value['@value']) ? $value['@value'] : $value['value'];
            $lang = $value['@language'] ?? ($value['language'] ?? ($value['lang'] ?? null));
            return $this->heuristLangTextValues($text, $lang);
        }

        // Sequential list of language objects/strings.
        if(array_keys($value)===range(0, count($value)-1)){
            $out = array();
            foreach($value as $item){
                foreach($this->normaliseLangValues($item) as $text){
                    if($text!=='' && array_search($text, $out, true)===false){
                        $out[] = $text;
                    }
                }
            }
            return $out;
        }

        // IIIF v3 language map: {"fr":["Chat"], "en":["Cat"]}.
        $out = array();
        foreach($value as $lang=>$texts){
            if($texts===null || $texts===''){
                continue;
            }
            $items = is_array($texts) ? $texts : array($texts);
            foreach($items as $text){
                foreach($this->heuristLangTextValues($text, (string)$lang) as $prefixed){
                    if($prefixed!=='' && array_search($prefixed, $out, true)===false){
                        $out[] = $prefixed;
                    }
                }
            }
        }
        return $out;
    }

    /** Backward-compatible single-value wrapper around normaliseLangValues(). */
    protected function normaliseLangValue($value): ?string
    {
        $values = $this->normaliseLangValues($value);
        return empty($values) ? null : $values[0];
    }

    /** Convert one or more raw text values to Heurist language-prefixed text. */
    private function heuristLangTextValues($text, $lang=null): array
    {
        if($text===null || $text===''){
            return array();
        }

        $items = is_array($text) ? $text : array($text);
        $lang3 = $this->iiifLangToHeuristLang3($lang);
        $out = array();

        foreach($items as $item){
            if($item===null || $item===''){
                continue;
            }
            $item = trim((string)$item);
            if($item===''){
                continue;
            }
            $out[] = $lang3 ? ($lang3.':'.$item) : $item;
        }
        return $out;
    }

    /** Return uppercase ISO-639-2 language code for Heurist prefixes, or null. */
    private function iiifLangToHeuristLang3($lang): ?string
    {
        $lang = trim((string)$lang);
        if($lang==='' || strtolower($lang)==='none' || $lang==='@none' || $lang==='*'){
            return null;
        }

        if(function_exists('getLangCode3')){
            $code = getLangCode3($lang);
            if($code){
                return strtoupper($code);
            }
        }

        // Small fallback table for common codes in case the language list is not
        // available in a standalone script/test context.
        $map = array(
            'en'=>'ENG', 'eng'=>'ENG',
            'fr'=>'FRE', 'fre'=>'FRE', 'fra'=>'FRE',
            'de'=>'GER', 'ger'=>'GER', 'deu'=>'GER',
            'es'=>'SPA', 'spa'=>'SPA',
            'it'=>'ITA', 'ita'=>'ITA',
            'la'=>'LAT', 'lat'=>'LAT'
        );
        $key = strtolower($lang);
        return $map[$key] ?? null;
    }

    /**
     * Convert Heurist text value(s) to a IIIF Presentation v3 language map.
     *
     * Examples:
     *   FRE:Chat  => {"fr":["Chat"]}
     *   Chat      => {"none":["Chat"]}
     *
     * @param mixed $value String or list of strings from recDetails.
     * @param string $fallbackText Text used if value is empty.
     */
    protected function toIiifLanguageMap($value, string $fallbackText='Untitled'): array
    {
        $values = is_array($value) ? $value : array($value);
        $map = array();

        foreach($values as $raw){
            if($raw===null || $raw===''){
                continue;
            }
            $raw = trim((string)$raw);
            if($raw===''){
                continue;
            }

            list($lang, $text) = $this->splitHeuristLangText($raw);
            if($text===''){
                continue;
            }
            if(!isset($map[$lang])){
                $map[$lang] = array();
            }
            if(array_search($text, $map[$lang], true)===false){
                $map[$lang][] = $text;
            }
        }

        if(empty($map)){
            $map['none'] = array($fallbackText);
        }
        return $map;
    }

    /** Split Heurist prefix text into IIIF v3 language-map key and value. */
    private function splitHeuristLangText(string $value): array
    {
        if(function_exists('extractLangPrefix')){
            list($lang3, $text) = extractLangPrefix($value);
            if($lang3){
                return array($this->heuristLang3ToIiifLang($lang3), trim((string)$text));
            }
        }

        if(preg_match('/^([A-Za-z]{2,3}|\*):(.*)$/s', $value, $m)){
            $lang = $m[1];
            $text = trim($m[2]);
            if($lang==='*'){
                return array('none', $text);
            }
            return array($this->heuristLang3ToIiifLang($lang), $text);
        }

        return array('none', $value);
    }

    /** Return lowercase IIIF v3 language-map key for a Heurist language code. */
    private function heuristLang3ToIiifLang(string $lang): string
    {
        $lang = trim($lang);
        if($lang==='' || strtoupper($lang)==='ALL'){
            return 'none';
        }

        if(function_exists('getLangCode2')){
            $code = getLangCode2($lang);
            if($code){
                return strtolower($code);
            }
        }

        $map = array(
            'ENG'=>'en',
            'FRE'=>'fr', 'FRA'=>'fr',
            'GER'=>'de', 'DEU'=>'de',
            'SPA'=>'es',
            'ITA'=>'it',
            'LAT'=>'la'
        );
        $key = strtoupper($lang);
        return $map[$key] ?? strtolower(substr($lang, 0, 2));
    }
}
