<?php
/**
* FieldPredicateCompiler.php - Typed detail-field predicate compiler
*
* @project     Heurist academic knowledge management system
* @package     Records\Search\Query
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

namespace Heurist\Records\Query\Compiler;



use Heurist\Database\DatabaseInterface;
use Heurist\Records\Query\QueryValidationException;
use Heurist\Records\Query\SearchExecutionException;
use Heurist\Utilities\Temporal;

/** Compiles typed detail, file, date, enum, geo, and text predicates. */
final class FieldPredicateCompiler
{
    private DatabaseInterface $database;
    private $fieldTypeCache = array();
    /** Initialise the field compiler with definition-table access. */
    public function __construct(DatabaseInterface $database){ $this->database = $database; }

    public function fieldCondition(
        int $fieldId,
        $value,
        SqlBuildContext $state,
        string $recordAlias = 'r',
        ?string $termField = null
    ): string
    {
        if($fieldId < 1){ throw new QueryValidationException('Field ID must be positive'); }
        $nullValue = $value === null ? 'NULL' : strtoupper(trim((string)$value));
        if($nullValue === 'NULL'){
            $state->bind($fieldId, 'i');
            return 'NOT EXISTS (SELECT 1 FROM recDetails d WHERE d.dtl_RecID='.$recordAlias.'.rec_ID '
                .'AND d.dtl_DetailTypeID=?'.$this->detailVisibilityCondition('d', $recordAlias, $state).')';
        }
        if($nullValue === '-NULL'){
            $state->bind($fieldId, 'i');
            return 'EXISTS (SELECT 1 FROM recDetails d WHERE d.dtl_RecID='.$recordAlias.'.rec_ID '
                .'AND d.dtl_DetailTypeID=?'.$this->detailVisibilityCondition('d', $recordAlias, $state).')';
        }
        $state->bind($fieldId, 'i');
        if((string)$value === ''){
            return 'EXISTS (SELECT 1 FROM recDetails d WHERE d.dtl_RecID='.$recordAlias.'.rec_ID '
                .'AND d.dtl_DetailTypeID=? AND (d.dtl_Value IS NOT NULL AND d.dtl_Value<>"")'
                .$this->detailVisibilityCondition('d', $recordAlias, $state).')';
        }

        $fieldType = $this->fieldType($fieldId);
        if($fieldType === 'date'){
            $condition = $this->detailDateCondition($value, $state);
            return 'EXISTS (SELECT 1 FROM recDetailsDateIndex di INNER JOIN recDetails d ON d.dtl_ID=di.rdi_DetailID '
                .'WHERE di.rdi_RecID='.$recordAlias.'.rec_ID AND di.rdi_DetailTypeID=? AND '.$condition
                .$this->detailVisibilityCondition('d', $recordAlias, $state).')';
        }
        if(in_array($fieldType, array('integer','float','resource'), true)){
            $condition = $this->numericCondition('CAST(d.dtl_Value AS DECIMAL(65,20))', $value, $state);
        }elseif(in_array($fieldType, array('enum','relationtype'), true)){
            $condition = $this->termCondition('d.dtl_Value', $value, $state, $termField);
        }elseif(in_array($fieldType, array('freetext','blocktext'), true)){
            $condition = $this->languageTextCondition('d.dtl_Value', $value, $state);
        }else{
            $condition = $this->scalarCondition('d.dtl_Value', $value, $state);
        }
        return 'EXISTS (SELECT 1 FROM recDetails d WHERE d.dtl_RecID='.$recordAlias.'.rec_ID '
            .'AND d.dtl_DetailTypeID=? AND '.$condition
            .$this->detailVisibilityCondition('d', $recordAlias, $state).')';
    }

    /** Apply Heurist's language-prefix rules for freetext and blocktext details. */
    public function languageTextCondition(string $column, $value, SqlBuildContext $state): string
    {
        $text = (string)$value; $prefix = '';
        foreach(array('==','>=','<=','!=','>','<','=','@+','@-','@','-') as $candidate){
            if(strpos($text, $candidate) === 0){ $prefix = $candidate; $text = substr($text, strlen($candidate)); break; }
        }
        $language = null;
        if(preg_match('/^([a-z]{2,3}|all):(.*)$/iu', $text, $matches)){
            $language = strtolower($matches[1]); $text = $matches[2];
        }
        $comparisonValue = $prefix.$text;
        if(($prefix === '=' || $prefix === '==') && $language !== null && $language !== 'all'){
            $comparisonValue = $prefix.$language.':'.$text;
        }
        $condition = $this->scalarCondition($column, $comparisonValue, $state);
        if($language === null){
            return '('.$condition.' AND '.$column.' NOT RLIKE "^[A-Za-z]{2,3}:")';
        }
        if($language === 'all'){ return $condition; }
        $state->bind($language.':%', 's');
        return '('.$condition.' AND '.$column.' LIKE ?)';
    }

    /**
     * Search title, non-resource detail values, enum labels/codes and linked titles.
     * This is the modern equivalent of legacy f/field without a detail type ID.
     */
    public function anyFieldCondition($value, SqlBuildContext $state, string $recordAlias = 'r'): string
    {
        if(is_array($value)){ throw new QueryValidationException('Any-field search requires a scalar value'); }
        $text = (string)$value;
        $exclude = strpos($text, '@-') === 0;
        $candidates = $this->anyFieldCandidateSource($text, $state);
        return $recordAlias.'.rec_ID '.($exclude ? 'NOT IN' : 'IN').' ('.$candidates.')';
    }

    /**
     * Build the positive, index-driven candidate-ID UNION for an any-field value.
     * For @- the caller applies negation to this positive result as a whole.
     */
    public function anyFieldCandidateSource($value, SqlBuildContext $state): string
    {
        if(is_array($value)){ throw new QueryValidationException('Any-field search requires a scalar value'); }
        $text = (string)$value;
        $positiveExclusion = strpos($text, '@-') === 0;
        $queries = array();
        $isDbOwner = !empty(($state['context'] ?? array())['isDbOwner']);
        $detailSourceJoin = $isDbOwner ? '' : 'INNER JOIN Records asrc ON asrc.rec_ID=ad.dtl_RecID ';
        $enumSourceJoin = $isDbOwner ? '' : 'INNER JOIN Records esrc ON esrc.rec_ID=ae.dtl_RecID ';
        $linkSourceJoin = $isDbOwner ? '' : 'INNER JOIN Records lsrc ON lsrc.rec_ID=al.rl_SourceID ';

        $detailCondition = $this->textCondition('ad.dtl_Value', $text, $state, $positiveExclusion);
        $queries[] = 'SELECT ad.dtl_RecID AS rec_ID FROM recDetails ad '
            .'INNER JOIN defDetailTypes adt ON adt.dty_ID=ad.dtl_DetailTypeID '
            .$detailSourceJoin
            .'WHERE adt.dty_Type<>"resource" AND adt.dty_Type<>"enum" '
            .'AND '.$detailCondition.$this->detailVisibilityCondition('ad', 'asrc', $state);

        $termText = $text;
        if(strpos($termText, '@+') === 0 || strpos($termText, '@-') === 0){ $termText = substr($termText, 2); }
        elseif(strpos($termText, '@') === 0){ $termText = substr($termText, 1); }
        $termCondition = $this->termCondition('ae.dtl_Value', $termText, $state);
        $queries[] = 'SELECT ae.dtl_RecID AS rec_ID FROM recDetails ae '
            .'INNER JOIN defDetailTypes aet ON aet.dty_ID=ae.dtl_DetailTypeID '
            .$enumSourceJoin
            .'WHERE aet.dty_Type="enum" '
            .'AND '.$termCondition.$this->detailVisibilityCondition('ae', 'esrc', $state);

        $linkedTitleCondition = $this->textCondition('alr.rec_Title', $text, $state, $positiveExclusion);
        $queries[] = 'SELECT al.rl_SourceID AS rec_ID FROM recLinks al '
            .'INNER JOIN Records alr ON alr.rec_ID=al.rl_TargetID '
            .$linkSourceJoin
            .'INNER JOIN recDetails ald ON ald.dtl_ID=al.rl_DetailID '
            .'WHERE al.rl_RelationID IS NULL AND al.rl_DetailTypeID>0 '
            .'AND '.$linkedTitleCondition.$this->detailVisibilityCondition('ald', 'lsrc', $state);

        return 'SELECT anyfield.rec_ID FROM ('.implode(' UNION ALL ', $queries).') anyfield';
    }

    /** Apply defRecStructure and per-detail visibility for non-owner searches. */
    public function detailVisibilityCondition(string $detailAlias, string $recordAlias, SqlBuildContext $state): string
    {
        $context = $state['context'] ?? array();
        if(!empty($context['isDbOwner'])){ return ''; }
        $userId = intval($context['userId'] ?? 0);
        $condition = ' AND EXISTS (SELECT 1 FROM defRecStructure dvs WHERE '
            .'dvs.rst_RecTypeID='.$recordAlias.'.rec_RecTypeID '
            .'AND dvs.rst_DetailTypeID='.$detailAlias.'.dtl_DetailTypeID '
            .'AND dvs.rst_RequirementType<>"forbidden"';
        if($userId < 1){
            $condition .= ' AND dvs.rst_NonOwnerVisibility IN ("public","pending")'
                .' AND IFNULL('.$detailAlias.'.dtl_HideFromPublic,0)<>1';
        }else{
            $groups = array_values(array_unique(array_filter(array_map(
                'intval', is_array($context['groupIds'] ?? null) ? $context['groupIds'] : array()
            ), static function($id){ return $id >= 0; })));
            $condition .= ' AND (dvs.rst_NonOwnerVisibility<>"hidden"';
            if(!empty($groups)){ $condition .= ' OR '.$recordAlias.'.rec_OwnerUGrpID IN ('.implode(',', $groups).')'; }
            $condition .= ')';
        }
        return $condition.')';
    }

    /** A suffixed link with NULL/-NULL is the corresponding field-presence query. */
    public function isLinkFieldPresenceTest(string $suffix, $value): bool
    {
        if($suffix === '' || !ctype_digit($suffix) || is_array($value)){ return false; }
        $value = strtoupper(trim((string)$value));
        return $value === 'NULL' || $value === '-NULL';
    }
    public function isValidFieldSuffix(string $suffix): bool
    {
        return preg_match('/^[1-9]\d*(?::(?:term|label|concept|conceptid|desc|code))?$/', $suffix) === 1;
    }

    /** Return [detail type ID, optional enum metadata selector]. */
    public function fieldSuffixParts(string $suffix): array
    {
        $parts = explode(':', strtolower($suffix), 2);
        return array(intval($parts[0]), $parts[1] ?? null);
    }

    /** Registered-file predicate used by file or file:fieldID. */
    public function fileCondition(string $suffix, $value, SqlBuildContext $state, string $recordAlias): string
    {
        if($suffix !== '' && (!ctype_digit($suffix) || intval($suffix)<1)){
            throw new QueryValidationException('File predicate field ID must be positive');
        }
        $fieldSql = '';
        if($suffix !== ''){ $state->bind(intval($suffix), 'i'); $fieldSql = ' AND fd.dtl_DetailTypeID=?'; }
        $text = trim((string)$value); $upper = strtoupper($text);
        if($value === null || $upper === 'NULL'){
            return 'NOT EXISTS (SELECT 1 FROM recDetails fd WHERE fd.dtl_RecID='.$recordAlias.'.rec_ID '
                .'AND fd.dtl_UploadedFileID IS NOT NULL'.$fieldSql
                .$this->detailVisibilityCondition('fd', $recordAlias, $state).')';
        }
        if($upper === '-NULL' || $text === ''){
            return 'EXISTS (SELECT 1 FROM recDetails fd WHERE fd.dtl_RecID='.$recordAlias.'.rec_ID '
                .'AND fd.dtl_UploadedFileID IS NOT NULL'.$fieldSql
                .$this->detailVisibilityCondition('fd', $recordAlias, $state).')';
        }
        if(strpos($text, '@') === 0){
            $state->bind(substr($text, 1), 's');
            $fileWhere = 'fu.ulf_ObfuscatedFileID=?';
        }elseif(strpos($text, '^') === 0){
            list($operator, $number) = $this->comparison(substr($text, 1));
            if(!is_numeric($number)){ throw new QueryValidationException('File size predicate requires a numeric value'); }
            if($operator === 'LIKE'){ $operator = '='; }
            $state->bind((float)$number, 'd');
            $fileWhere = 'fu.ulf_FileSizeKB '.$operator.' ?';
        }else{
            $pattern = $this->likePattern(ltrim($text, '='));
            $operator = strpos($text, '=') === 0 ? '=' : 'LIKE';
            if($operator === '='){ $pattern = substr($text, 1); }
            foreach(array(1,2,3) as $_){ $state->bind($pattern, 's'); }
            $fileWhere = '(fu.ulf_OrigFileName '.$operator.' ? OR fu.ulf_ExternalFileReference '.$operator.' ? '
                .'OR fu.ulf_Description '.$operator.' ?)';
        }
        return 'EXISTS (SELECT 1 FROM recDetails fd INNER JOIN recUploadedFiles fu '
            .'ON fu.ulf_ID=fd.dtl_UploadedFileID WHERE fd.dtl_RecID='.$recordAlias.'.rec_ID'
            .$fieldSql.' AND '.$fileWhere.$this->detailVisibilityCondition('fd', $recordAlias, $state).')';
    }

    /** Owner/creator accepts IDs, current-user aliases, or login names. */
    public function fieldCountCondition(int $fieldId, $value, SqlBuildContext $state, string $recordAlias = 'r'): string
    {
        $state->bind($fieldId, 'i');
        list($operator, $cleanValue) = $this->comparison((string)$value);
        if($operator === 'LIKE'){ $operator = '='; }
        if($operator === 'NOT LIKE'){ $operator = '<>'; }
        if(!is_numeric($cleanValue)){ throw new QueryValidationException('Field count requires a numeric value'); }
        $count = intval($cleanValue);
        $state->bind($count, 'i');
        return '(SELECT COUNT(*) FROM recDetails dc WHERE dc.dtl_RecID='.$recordAlias.'.rec_ID '
            .'AND dc.dtl_DetailTypeID=?'.$this->detailVisibilityCondition('dc', $recordAlias, $state).') '
            .$operator.' ?';
    }
    public function geoCondition(string $suffix, $value, SqlBuildContext $state, string $recordAlias = 'r'): string
    {
        $fieldSql = '';
        if($suffix !== ''){
            if(!ctype_digit($suffix) || intval($suffix)<1){
                throw new QueryValidationException('Geo predicate field ID must be numeric');
            }
            $state->bind(intval($suffix), 'i');
            $fieldSql = ' AND gd.dtl_DetailTypeID=?';
        }
        if($value === null || (!is_array($value) && strtoupper(trim((string)$value)) === 'NULL')){
            return 'NOT EXISTS (SELECT 1 FROM recDetails gd WHERE gd.dtl_RecID='.$recordAlias.'.rec_ID '
                .'AND gd.dtl_Geo IS NOT NULL'.$fieldSql
                .$this->detailVisibilityCondition('gd', $recordAlias, $state).')';
        }
        if(!is_array($value) && strtoupper(trim((string)$value)) === '-NULL'){
            return 'EXISTS (SELECT 1 FROM recDetails gd WHERE gd.dtl_RecID='.$recordAlias.'.rec_ID '
                .'AND gd.dtl_Geo IS NOT NULL'.$fieldSql
                .$this->detailVisibilityCondition('gd', $recordAlias, $state).')';
        }
        $text = is_array($value) ? '' : trim((string)$value);
        if($text === ''){
            if(is_array($value)){
                foreach(array('west','south','east','north') as $key){
                    if(!array_key_exists($key, $value) || !is_numeric($value[$key])){
                        throw new QueryValidationException('Invalid geo extent. Expected numeric west, south, east and north values');
                    }
                }
                $west = (float)$value['west']; $south = (float)$value['south'];
                $east = (float)$value['east']; $north = (float)$value['north'];
                if($west < -180 || $west > 180 || $east < -180 || $east > 180
                    || $south < -90 || $south > 90 || $north < -90 || $north > 90
                    || $south > $north){
                    throw new QueryValidationException('Invalid geo extent coordinates');
                }
                $wkt = "POLYGON (($west $south, $east $south, $east $north, $west $north, $west $south))";
                $state->bind($wkt, 's');
                return 'EXISTS (SELECT 1 FROM recDetails gd WHERE gd.dtl_RecID='.$recordAlias.'.rec_ID '
                    .'AND gd.dtl_Geo IS NOT NULL'.$fieldSql.' '
                    .'AND ST_Intersects(ST_GeomFromText(?),gd.dtl_Geo)'
                    .$this->detailVisibilityCondition('gd', $recordAlias, $state).')';
            }
            return 'EXISTS (SELECT 1 FROM recDetails gd WHERE gd.dtl_RecID='.$recordAlias.'.rec_ID '
                .'AND gd.dtl_Geo IS NOT NULL'.$fieldSql
                .$this->detailVisibilityCondition('gd', $recordAlias, $state).')';
        }
        $state->bind($text, 's');
        return 'EXISTS (SELECT 1 FROM recDetails gd WHERE gd.dtl_RecID='.$recordAlias.'.rec_ID '
            .'AND gd.dtl_Geo IS NOT NULL'.$fieldSql.' '
            .'AND ST_Contains(ST_GeomFromText(?),gd.dtl_Geo)'
            .$this->detailVisibilityCondition('gd', $recordAlias, $state).')';
    }
    public function textCondition(
        string $column,
        $value,
        SqlBuildContext $state,
        bool $positiveFulltext = false
    ): string
    {
        $text = (string)$value;
        if(strpos($text, '@') === 0){
            return $this->fulltextCondition($column, $text, $state, $positiveFulltext);
        }
        if(strpos($text, '==') === 0){
            $state->bind(substr($text, 2), 's');
            return 'BINARY '.$column.' = BINARY ?';
        }
        if(strpos($text, '=') === 0){
            return $this->forcedComparison($column, '=', substr($text, 1), $state);
        }
        if(strpos($text, '-') === 0){
            $state->bind($this->likePattern(substr($text, 1)), 's');
            return $column.' NOT LIKE ? ESCAPE "\\\\"';
        }
        $state->bind($this->likePattern($text), 's');
        return $column.' LIKE ? ESCAPE "\\\\"';
    }
    public function scalarCondition(string $column, $value, SqlBuildContext $state): string
    {
        $text = trim((string)$value);
        if(strpos($text, '@') === 0){ return $this->fulltextCondition($column, $text, $state); }
        if(strpos($text, '==') === 0){
            $state->bind(substr($text, 2), 's');
            return 'BINARY '.$column.' = BINARY ?';
        }
        if(($range = $this->splitRange($text, '<>')) !== null){
            $state->bind($range[0], 's'); $state->bind($range[1], 's');
            return $column.' BETWEEN ? AND ?';
        }
        list($operator, $cleanValue) = $this->comparison((string)$value);
        if($operator === 'LIKE' || $operator === 'NOT LIKE'){
            $state->bind($this->likePattern($cleanValue), 's');
            return $column.' '.$operator.' ? ESCAPE "\\\\"';
        }
        return $this->forcedComparison($column, $operator, $cleanValue, $state);
    }
    public function integerCondition(string $column, $value, SqlBuildContext $state): string
    {
        list($operator, $cleanValue) = $this->comparison((string)$value);
        if(!is_numeric($cleanValue)){
            throw new QueryValidationException('Numeric value required for '.$column);
        }
        $state->bind(intval($cleanValue), 'i');
        return $column.' '.$operator.' ?';
    }
    public function forcedComparison(
        string $column,
        string $operator,
        $value,
        SqlBuildContext $state
    ): string {
        $type = is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
        $state->bind($value, $type);
        return $column.' '.$operator.' ?';
    }
    public function comparison(string $value): array
    {
        $value = trim($value);
        foreach(array('>=','<=','<>','!=','>','<','=') as $operator){
            if(strpos($value, $operator) === 0){
                $sqlOperator = $operator === '!=' ? '<>' : $operator;
                return array($sqlOperator, substr($value, strlen($operator)));
            }
        }
        if(strpos($value, '-') === 0){ return array('NOT LIKE', substr($value, 1)); }
        return array('LIKE', $value);
    }
    public function inCondition(string $column, array $values, SqlBuildContext $state): string
    {
        if(count($values) === 1){
            $state->bind(reset($values), 'i');
            return $column.' = ?';
        }
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        foreach($values as $value){ $state->bind($value, 'i'); }
        return $column.' IN ('.$placeholders.')';
    }
    public function numericList($value, string $label): array
    {
        $items = is_array($value) ? $value : preg_split('/\s*,\s*/', trim((string)$value));
        $result = array();
        foreach($items as $item){
            if(!is_numeric($item) || intval($item)<1){
                throw new QueryValidationException('Invalid '.$label.' ID: '.(string)$item);
            }
            $result[] = intval($item);
        }
        $result = array_values(array_unique($result));
        if(empty($result)){ throw new QueryValidationException('Empty '.$label.' ID list'); }
        return $result;
    }
    public function escapeLike(string $value): string
    {
        return str_replace('\\', '\\\\', $value);
    }

    /** Preserve legacy % and _ wildcards and add contains wildcards when absent. */
    public function likePattern(string $value): string
    {
        $value = $this->escapeLike($value);
        return strpos($value, '%') === false && strpos($value, '_') === false
            ? '%'.$value.'%'
            : $value;
    }

    /** Read the existing definition table directly; one lookup per field per request. */
    public function fieldType(int $fieldId): ?string
    {
        if(array_key_exists($fieldId, $this->fieldTypeCache)){ return $this->fieldTypeCache[$fieldId]; }
        $value = $this->database->fetchValue(
            'SELECT dty_Type FROM defDetailTypes WHERE dty_ID=? LIMIT 1', array($fieldId)
        );
        return $this->fieldTypeCache[$fieldId] = ($value === null ? null : (string)$value);
    }

    /** Numeric exact/comparison and legacy low<>high range. */
    public function numericCondition(string $column, $value, SqlBuildContext $state): string
    {
        $text = trim((string)$value);
        $range = $this->numericRange($text);
        if($range !== null){
            if(!is_numeric($range[0]) || !is_numeric($range[1])){
                throw new QueryValidationException('Numeric range requires two numeric values');
            }
            $state->bind((float)$range[0], 'd'); $state->bind((float)$range[1], 'd');
            return $column.' BETWEEN ? AND ?';
        }
        list($operator, $cleanValue) = $this->comparison($text);
        if(!is_numeric($cleanValue)){ throw new QueryValidationException('Numeric field requires a numeric value'); }
        if($operator === 'LIKE'){ $operator = '='; }
        if($operator === 'NOT LIKE'){ $operator = '<>'; }
        $state->bind((float)$cleanValue, 'd');
        return $column.' '.$operator.' ?';
    }

    /** Build an index-driven candidate query for one integer or float field. */
    public function numericFieldCandidateSource(
        int $fieldId,
        $value,
        SqlBuildContext $state
    ): string {
        $fieldType = $this->fieldType($fieldId);
        if(!in_array($fieldType, array('integer','float'), true)){
            throw new QueryValidationException('Field '.$fieldId.' is not numeric');
        }
        $state->bind($fieldId, 'i');
        $isDbOwner = !empty(($state['context'] ?? array())['isDbOwner']);
        $sourceJoin = $isDbOwner ? '' : ' INNER JOIN Records nsrc ON nsrc.rec_ID=nd.dtl_RecID';
        $condition = $this->numericCondition(
            'CAST(nd.dtl_Value AS DECIMAL(65,20))',
            $value,
            $state
        );
        return 'SELECT nd.dtl_RecID AS rec_ID FROM recDetails nd'.$sourceJoin
            .' WHERE nd.dtl_DetailTypeID=? AND '.$condition
            .$this->detailVisibilityCondition('nd', 'nsrc', $state);
    }

    /** Whether the detail definition is an integer or float field. */
    public function isNumericField(int $fieldId): bool
    {
        return in_array($this->fieldType($fieldId), array('integer','float'), true);
    }

    /** Parse both low<>high and leading <>low/high or ><low/high forms. */
    private function numericRange(string $text): ?array
    {
        if(preg_match('/^\s*(?:<>|><)\s*([+-]?(?:\d+(?:\.\d*)?|\.\d+))\s*(?:\/|,|\bto\b)\s*([+-]?(?:\d+(?:\.\d*)?|\.\d+))\s*$/iu', $text, $matches)){
            return array($matches[1], $matches[2]);
        }
        foreach(array('<>','><') as $separator){
            if(($range = $this->splitRange($text, $separator)) !== null){ return $range; }
        }
        return null;
    }

    /** Enum IDs include descendants unless an exact (=) match was requested. */
    public function termCondition(string $column, $value, SqlBuildContext $state, ?string $termField = null): string
    {
        $text = trim((string)$value); $exact = false; $caseSensitive = false; $negate = false;
        if(strpos($text, '==') === 0){
            $exact = true; $caseSensitive = true; $text = trim(substr($text, 2));
        }elseif(strpos($text, '=') === 0){ $exact = true; $text = trim(substr($text, 1)); }
        elseif(strpos($text, '-') === 0){ $negate = true; $text = trim(substr($text, 1)); }

        $ids = preg_split('/\s*,\s*/', $text, -1, PREG_SPLIT_NO_EMPTY);
        if(!empty($ids) && count(array_filter($ids, static function($id){ return ctype_digit($id) && intval($id)>0; })) === count($ids)){
            $ids = array_values(array_unique(array_map('intval', $ids)));
            if(!$exact){ $ids = array_values(array_unique(array_merge($ids, $this->termChildrenAll($ids)))); }
            $condition = $this->inCondition($column, $ids, $state);
            return $negate ? 'NOT ('.$condition.')' : $condition;
        }

        $language = null;
        if(preg_match('/^([a-z]{2,3}|all):(.*)$/iu', $text, $matches)){
            $language = strtolower($matches[1]); $text = $matches[2];
        }
        $termField = $termField ?? 'label';
        if(in_array($termField, array('concept','conceptid'), true)){
            $state->bind($text, 's');
            $condition = $column.' IN (SELECT trm_ID FROM defTerms '
                .'WHERE CONCAT(trm_OriginatingDBID,"-",trm_IDInOriginatingDB)=?)';
            return $negate ? 'NOT ('.$condition.')' : $condition;
        }
        $termColumns = array('term'=>'trm_Label','label'=>'trm_Label','desc'=>'trm_Description','code'=>'trm_Code');
        $termColumn = $termColumns[$termField] ?? 'trm_Label';
        $state->bind($exact ? $text : $this->likePattern($text), 's');
        $comparison = $caseSensitive ? 'BINARY '.$termColumn.' = BINARY ?'
            : ($exact ? $termColumn.'=?' : $termColumn.' LIKE ? ESCAPE "\\\\"');
        $subqueries = array('SELECT trm_ID FROM defTerms WHERE '.$comparison);
        if($termField === 'label' && $language !== null){
            $state->bind('trm_Label', 's');
            $translation = 'SELECT CAST(trn_Code AS UNSIGNED) FROM defTranslations WHERE trn_Source=?';
            if($language !== 'all'){
                $state->bind($language, 's'); $translation .= ' AND trn_LanguageCode=?';
            }
            $state->bind($exact ? $text : $this->likePattern($text), 's');
            $translation .= ' AND '.($exact ? 'trn_Translation=?' : 'trn_Translation LIKE ? ESCAPE "\\\\"');
            $subqueries[] = $translation;
        }elseif($termField === 'label'){
            $state->bind($text, 's');
            $subqueries[0] .= ' OR trm_Code=?';
        }
        $condition = $column.' IN ('.implode(' UNION ', $subqueries).')';
        return $negate ? 'NOT ('.$condition.')' : $condition;
    }

    /** Load all descendant term IDs through the existing closure table. */
    public function termChildrenAll(array $parentIds): array
    {
        if(empty($parentIds)){ return array(); }
        $found = array(); $pending = array_values(array_unique(array_map('intval', $parentIds)));
        while(!empty($pending)){
            $placeholders = implode(',', array_fill(0, count($pending), '?'));
            $next = array();
            foreach($this->database->fetchColumn(
                'SELECT trl_TermID FROM defTermsLinks WHERE trl_ParentID IN ('.$placeholders.')', $pending
            ) as $termId){
                $id = intval($termId);
                if($id > 0 && !isset($found[$id])){ $found[$id] = true; $next[] = $id; }
            }
            $pending = $next;
        }
        foreach($parentIds as $id){ unset($found[intval($id)]); }
        return array_map('intval', array_keys($found));
    }

    /** Header dates use ISO values and legacy slash/<> BETWEEN syntax. */
    public function headerDateCondition(string $column, $value, SqlBuildContext $state): string
    {
        $text = $this->normalizeRelativeDate(trim((string)$value));
        $operator = null;
        foreach(array('>=','<=','>','<','=') as $candidate){
            if(strpos($text, $candidate) === 0){ $operator = $candidate; $text = trim(substr($text, strlen($candidate))); break; }
        }
        $range = $this->splitRange($text, '<>') ?? $this->splitRange($text, '/');
        if($range !== null){
            $from = Temporal::dateToISO($range[0]); $to = Temporal::dateToISO($range[1]);
            if($from === null || $to === null){ throw new QueryValidationException('Invalid date range'); }
            $state->bind($from, 's'); $state->bind($to, 's');
            return $column.' BETWEEN ? AND ?';
        }
        $iso = Temporal::dateToISO($text);
        if($iso === null){ throw new QueryValidationException('Invalid date value: '.$text); }
        if($operator !== null){ $state->bind($iso, 's'); return $column.' '.$operator.' ?'; }
        $pattern = preg_match('/^\d{4}[-\/]\d{2}$/', $text) ? str_replace('/', '-', $text).'%' : $iso.'%';
        $state->bind($pattern, 's');
        return $column.' LIKE ?';
    }

    /** Detail dates are compared through recDetailsDateIndex estimated bounds. */
    public function detailDateCondition($value, SqlBuildContext $state): string
    {
        $text = $this->normalizeRelativeDate(trim((string)$value)); $operator = null;
        $within = strpos($text, '><') !== false;
        $overlap = strpos($text, '<>') !== false;
        if(!$within && !$overlap){
            foreach(array('>=','<=','>','<','=') as $candidate){
                if(strpos($text, $candidate) === 0){ $operator = $candidate; $text = trim(substr($text, strlen($candidate))); break; }
            }
        }
        $temporal = new Temporal($text, !($within || $overlap));
        if(!$temporal->isValid()){ throw new QueryValidationException('Invalid temporal value: '.$text); }
        $timespan = $temporal->getMinMax();
        $min = (float)$timespan[0]; $max = (float)$timespan[1];
        if($within){
            $state->bind($min, 'd'); $state->bind($max, 'd');
            return '? <= di.rdi_estMinDate AND di.rdi_estMaxDate <= ?';
        }
        if($operator === '='){
            $state->bind($min, 'd'); $state->bind($max, 'd');
            return '(di.rdi_estMinDate = ? OR di.rdi_estMaxDate = ?)';
        }
        if($operator === '<' || $operator === '<='){
            $state->bind($max, 'd'); return 'di.rdi_estMaxDate '.$operator.' ?';
        }
        if($operator === '>' || $operator === '>='){
            $state->bind($min, 'd'); return 'di.rdi_estMinDate '.$operator.' ?';
        }
        $state->bind($min, 'd'); $state->bind($max, 'd');
        return 'di.rdi_estMaxDate >= ? AND di.rdi_estMinDate <= ?';
    }
    public function splitRange(string $value, string $separator): ?array
    {
        $position = strpos($value, $separator);
        if($position === false){ return null; }
        $left = trim(substr($value, 0, $position));
        $right = trim(substr($value, $position + strlen($separator)));
        return $left !== '' && $right !== '' ? array($left, $right) : null;
    }

    /** @, @+ and @- mean any word, all usable words and no words respectively. */
    public function fulltextCondition(
        string $column,
        string $value,
        SqlBuildContext $state,
        bool $positiveOnly = false
    ): string
    {
        $mode = strpos($value, '@+') === 0 ? '@+' : (strpos($value, '@-') === 0 ? '@-' : '@');
        $text = trim(substr($value, $mode === '@' ? 1 : 2));
        if($text === ''){ throw new QueryValidationException('Full-text search value cannot be empty'); }

        if($mode === '@+' || $mode === '@-'){
            $words = $this->indexableFulltextWords($text);
            if(empty($words)){
                $state->bind($this->likePattern($text), 's');
                $condition = $column.' LIKE ? ESCAPE "\\\\"';
                return $mode === '@-' && !$positiveOnly ? 'NOT ('.$condition.')' : $condition;
            }
            if($mode === '@+'){
                $text = implode(' ', array_map(static function($word){ return '+'.$word; }, $words));
                $state->bind($text, 's');
                return 'MATCH('.$column.') AGAINST (? IN BOOLEAN MODE)';
            }
            $text = implode(' ', $words);
        }
        $state->bind($text, 's');
        $boolean = $mode === '@' && preg_match('/(?:^|\s)[+-]\S/u', $text);
        $condition = 'MATCH('.$column.') AGAINST (?'.($boolean ? ' IN BOOLEAN MODE' : '').')';
        return $mode === '@-' && !$positiveOnly ? 'NOT ('.$condition.')' : $condition;
    }

    /** Apply the same indexability limits used by legacy InnoDB full-text search. */
    private function indexableFulltextWords(string $text): array
    {
        preg_match_all('/(\w+)/u', $text, $matches);
        $stopwords = array(
            'a','about','an','are','as','at','be','by','com','de','en','for','from','how','i',
            'in','is','it','la','of','on','or','that','the','this','to','und','was','what',
            'when','where','who','will','with','www'
        );
        $words = array();
        foreach($matches[0] ?? array() as $word){
            $length = strlen($word);
            if($length>2 && $length<85 && !in_array(strtolower($word), $stopwords, true)){
                $words[] = $word;
            }
        }
        return $words;
    }

    /** Convert convenient relative expressions to deterministic ISO values/ranges. */
    private function normalizeRelativeDate(string $value): string
    {
        $prefix = '';
        foreach(array('><','<>','>=','<=','>','<','=') as $operator){
            if(strpos($value, $operator) === 0){
                $prefix = $operator; $value = trim(substr($value, strlen($operator))); break;
            }
        }
        $phrase = strtolower(trim($value));
        $today = new \DateTimeImmutable('today');
        if($phrase === 'today'){ $value = $today->format('Y-m-d'); }
        elseif($phrase === 'now'){ $value = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'); }
        elseif($phrase === 'yesterday'){ $value = $today->modify('-1 day')->format('Y-m-d'); }
        elseif($phrase === 'tomorrow'){ $value = $today->modify('+1 day')->format('Y-m-d'); }
        elseif($phrase === 'month'){
            $value = $prefix === ''
                ? $today->format('Y-m-01').'/'.$today->format('Y-m-t')
                : $today->format('Y-m-01');
        }elseif($phrase === 'year'){
            $value = $prefix === ''
                ? $today->format('Y-01-01').'/'.$today->format('Y-12-31')
                : $today->format('Y-01-01');
        }elseif($phrase === 'last month'){
            $date = $today->modify('first day of last month');
            $value = $prefix === '' ? $date->format('Y-m-01').'/'.$date->format('Y-m-t') : $date->format('Y-m-01');
        }elseif($phrase === 'last year'){
            $date = $today->modify('-1 year');
            $value = $prefix === '' ? $date->format('Y-01-01').'/'.$date->format('Y-12-31') : $date->format('Y-01-01');
        }elseif(preg_match('/^(?:(\d+|a|one)\s+)?(day|week|month|year)s?\s+ago$/', $phrase, $match)){
            $amount = isset($match[1]) && ctype_digit($match[1]) ? intval($match[1]) : 1;
            $value = $today->modify('-'.$amount.' '.$match[2])->format('Y-m-d');
        }
        return $prefix.$value;
    }
    public function isAssociative(array $value): bool
    {
        if(empty($value)){ return false; }
        return array_keys($value) !== range(0, count($value)-1);
    }
}
