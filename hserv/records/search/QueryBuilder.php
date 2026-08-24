<?php
/**
* QueryBuilder.php - Modern Heurist record query normalizer and SQL builder
*
* Converts plain Heurist search text to the established JSON query language,
 * validates JSON queries, and builds parameterized IDs-only or count SQL.
 * Resource-link and ordinary directional relationship predicates are compiled
 * as bounded correlated EXISTS expressions so counting and pagination remain
 * database operations.
*
* @project     Heurist academic knowledge management system
* @package     Records\Search
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

namespace hserv\records\search;

require_once dirname(__FILE__).'/SearchTypes.php';
require_once dirname(__FILE__, 3).'/utilities/Temporal.php';

/**
 * Normalizes Heurist query syntax and compiles flat searches to parameterized SQL.
 */
final class QueryBuilder
{
    private const DEFAULT_LIMIT = 1000;
    private const MAX_LIMIT = 5000;
    private const MAX_SQL_LINK_DEPTH = 8;

    /** Canonical keyword aliases accepted by the plain-text converter. */
    private const KEYWORD_ALIASES = array(
        'type'=>'t', 'typeid'=>'t', 'typename'=>'t', 't'=>'t',
        'id'=>'ids', 'ids'=>'ids',
        'title'=>'title', 'url'=>'url', 'notes'=>'notes',
        'added'=>'added', 'modified'=>'modified', 'before'=>'before',
        'after'=>'after', 'since'=>'after', 'addedby'=>'addedby',
        'owner'=>'owner', 'workgroup'=>'owner', 'wg'=>'owner',
        'access'=>'access', 'user'=>'user', 'usr'=>'user',
        'ws'=>'ws', 'workset'=>'ws',
        'tag'=>'tag', 'keyword'=>'tag', 'kwd'=>'tag',
        'field'=>'f', 'f'=>'f', 'count'=>'fc', 'cnt'=>'fc', 'fc'=>'fc',
        'geo'=>'geo', 'file'=>'file',
        'linked_to'=>'lt', 'linkedto'=>'lt', 'linkto'=>'lt', 'link_to'=>'lt', 'lt'=>'lt',
        'linked_from'=>'lf', 'linkedfrom'=>'lf', 'linkfrom'=>'lf', 'link_from'=>'lf', 'lf'=>'lf',
        'related_to'=>'rt', 'relatedto'=>'rt', 'rt'=>'rt',
        'related_from'=>'rf', 'relatedfrom'=>'rf', 'rf'=>'rf',
        'related'=>'related', 'links'=>'links', 'relf'=>'relf', 'r'=>'r',
        'any'=>'any', 'all'=>'all', 'not'=>'not',
        'sortby'=>'sortby', 'sort'=>'sortby', 's'=>'sortby'
    );

    /** Link and relationship predicates recognized by the query language. */
    private const LINK_PREDICATES = array(
        'lt', 'linked_to', 'linkedto',
        'lf', 'linked_from', 'linkedfrom',
        'rt', 'related_to', 'relatedto',
        'rf', 'related_from', 'relatedfrom',
        'related', 'links', 'relf', 'r'
    );

    /** @var array<int,array> Subqueries collected while tokenizing plain text. */
    private $textSubqueries = array();

    /** @var \mysqli|null Active database connection for definition lookups. */
    private $mysqli;

    /** @var array<int,string|null> Field types read from defDetailTypes. */
    private $fieldTypeCache = array();

    /** @param \mysqli|null $mysqli Active Heurist database connection. */
    public function __construct($mysqli = null)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Convert JSON, a decoded query array, or plain text to canonical JSON-query arrays.
     *
     * @param mixed $query
     * @return array
     */
    public function normalize($query): array
    {
        if(is_array($query)){
            $normalized = $query;
        }else{
            $text = trim((string)$query);
            if($text === ''){
                throw new QueryValidationException('Query cannot be empty');
            }
            $decoded = json_decode($text, true);
            if(json_last_error() === JSON_ERROR_NONE && is_array($decoded)){
                $normalized = $decoded;
            }else{
                $normalized = $this->textToJson($text);
            }
        }

        $normalized = $this->normalizeQueryArray($normalized);
        $this->validate($normalized);
        return $normalized;
    }

    /**
     * Convert simplified Heurist plain text into JSON-query form.
     */
    public function textToJson(string $query): array
    {
        $this->textSubqueries = array();
        return $this->parsePlainText($query);
    }

    /** Parse plain text while sharing the current recursive-subquery registry. */
    private function parsePlainText(string $query): array
    {
        $query = trim(ltrim(trim($query), '*'));
        if($query === ''){
            throw new QueryValidationException('Query cannot be empty');
        }

        $query = $this->extractTextSubqueries($query);
        $tokens = $this->tokenize($query);
        $result = array();

        for($index=0; $index<count($tokens); $index++){
            $token = $tokens[$index];
            if($token === ''){ continue; }

            list($rawKeyword, $inlineValue, $hasInlineValue) = $this->splitKeywordValue($token);
            list($keyword, $suffix) = $this->plainKeyword($rawKeyword);

            if($keyword === null){
                $result[] = array('title'=>$this->unquote($token));
                continue;
            }

            if($suffix !== ''){ $keyword .= ':'.$suffix; }
            if($hasInlineValue){
                $value = $inlineValue;
            }else{
                if(in_array($keyword, array('user','ws'), true)
                    && (!isset($tokens[$index+1]) || $this->looksLikeKeywordToken($tokens[$index+1]))){
                    $value = 'current';
                }elseif(!isset($tokens[$index+1])){
                    throw new QueryValidationException('Missing value for query keyword '.$rawKeyword);
                }else{
                    $value = $tokens[++$index];
                }
            }
            $value = $this->resolveTextValue($value);
            $result[] = array($keyword=>$value);
        }

        if(empty($result)){
            throw new QueryValidationException('Query contains no searchable predicates');
        }
        return $result;
    }

    /** Validate the established Heurist JSON query structure. */
    public function validate(array $query): void
    {
        if(empty($query)){
            throw new QueryValidationException('Query contains no predicates');
        }
        $this->validateGroup($query, 0);
    }

    /**
     * Build the paginated IDs-only query for a flat query.
     *
     * Context keys: userId, groupIds, isDbOwner, limit, offset.
     */
    public function buildIds($query, array $context = array()): CompiledQuery
    {
        $normalized = $this->normalize($query);
        $this->assertSqlExecutable($normalized);

        $state = $this->newBuildState($context);
        $where = $this->compileGroup($normalized, 'AND', $state, 'r', 0);
        $this->appendAccessConditions($where, $state, $context, 'r');

        $sort = $this->compileSort($normalized, $state);
        $limit = intval($context['limit'] ?? self::DEFAULT_LIMIT);
        if($limit < 1){ $limit = self::DEFAULT_LIMIT; }
        $limit = min($limit, self::MAX_LIMIT);
        $offset = max(0, intval($context['offset'] ?? 0));

        $sql = 'SELECT DISTINCT r.rec_ID FROM Records r'
            .' WHERE '.implode(' AND ', $where)
            .$sort.' LIMIT ? OFFSET ?';
        $this->bind($state, $limit, 'i');
        $this->bind($state, $offset, 'i');
        return new CompiledQuery($sql, $state['types'], $state['values'], $normalized);
    }

    /** Build the matching-record count query without sort or pagination. */
    public function buildCount($query, array $context = array()): CompiledQuery
    {
        $normalized = $this->normalize($query);
        $this->assertSqlExecutable($normalized);
        $state = $this->newBuildState($context);
        $where = $this->compileGroup($normalized, 'AND', $state, 'r', 0);
        $this->appendAccessConditions($where, $state, $context, 'r');
        $sql = 'SELECT COUNT(DISTINCT r.rec_ID) FROM Records r WHERE '.implode(' AND ', $where);
        return new CompiledQuery($sql, $state['types'], $state['values'], $normalized);
    }

    /**
     * Build an unpaged IDs query used by set-based linked-query execution.
     *
     * This method is intentionally separate from buildIds(): expansion filters
     * need the complete intermediate ID set before the final page is selected.
     */
    public function buildIdSet($query, array $context = array()): CompiledQuery
    {
        $normalized = $this->normalize($query);
        $this->assertFlatExecutable($normalized);
        $state = $this->newBuildState($context);
        $where = $this->compileGroup($normalized, 'AND', $state, 'r', 0);
        $this->appendAccessConditions($where, $state, $context, 'r');
        $sql = 'SELECT DISTINCT r.rec_ID FROM Records r'
            .' WHERE '.implode(' AND ', $where)
            .$this->compileSort($normalized, $state);
        return new CompiledQuery($sql, $state['types'], $state['values'], $normalized);
    }

    /** Return true when Phase 1 can execute the query without decomposition. */
    public function supportsFlatExecution($query): bool
    {
        try{
            $normalized = $this->normalize($query);
            $this->assertFlatExecutable($normalized);
            return true;
        }catch(UnsupportedQueryException $e){
            return false;
        }
    }

    /** Return true when the complete filter can be delegated to SQL. */
    public function supportsSqlExecution($query): bool
    {
        try{
            $normalized = $this->normalize($query);
            $this->assertSqlExecutable($normalized);
            return true;
        }catch(UnsupportedQueryException $e){
            return false;
        }
    }

    private function normalizeQueryArray(array $query): array
    {
        if($this->isAssociative($query)){
            $result = array();
            foreach($query as $key=>$value){
                $result[] = array((string)$key=>$value);
            }
            return $result;
        }
        return array_values($query);
    }

    private function validateGroup(array $group, int $depth): void
    {
        if($depth > 20){
            throw new QueryValidationException('Query nesting is too deep');
        }
        foreach($group as $predicate){
            if(!is_array($predicate) || count($predicate)!==1){
                throw new QueryValidationException('Every query predicate must contain exactly one key');
            }
            $key = (string)array_keys($predicate)[0];
            $value = $predicate[$key];
            list($base, $suffix) = $this->predicateParts($key);
            if(!$this->isKnownPredicate($base)){
                throw new QueryValidationException('Unknown query predicate: '.$key);
            }
            if(in_array($base, array('fc','count','cnt'), true)
                && ($suffix === '' || !ctype_digit($suffix) || intval($suffix)<1)){
                throw new QueryValidationException('Predicate '.$key.' requires a numeric field ID');
            }
            if(in_array($base, array('f','field'), true)
                && $suffix !== '' && !$this->isValidFieldSuffix($suffix)){
                throw new QueryValidationException('Predicate '.$key.' has an invalid field ID');
            }
            if($base === 'relf' && ($suffix === '' || !ctype_digit($suffix) || intval($suffix)<1)){
                throw new QueryValidationException('Predicate '.$key.' requires a numeric Relationship field ID');
            }
            if($base === 'r' && $suffix !== '' && (!ctype_digit($suffix) || intval($suffix)<1)){
                throw new QueryValidationException('Predicate '.$key.' has an invalid Relationship field ID');
            }
            if(($base === 'any' || $base === 'all' || $base === 'not') && !is_array($value)){
                throw new QueryValidationException($base.' requires a query array');
            }
            if(is_array($value) && ($base === 'any' || $base === 'all' || $base === 'not'
                || (in_array($base, self::LINK_PREDICATES, true) && !$this->isScalarValueList($value)))){
                $nested = $this->normalizeQueryArray($value);
                $this->validateGroup($nested, $depth+1);
            }
        }
    }

    private function assertFlatExecutable(array $query): void
    {
        foreach($query as $predicate){
            $key = (string)array_keys($predicate)[0];
            $value = $predicate[$key];
            list($base) = $this->predicateParts($key);
            if(in_array($base, self::LINK_PREDICATES, true)){
                throw new UnsupportedQueryException(
                    'Linked and related predicates will be executed separately in Phase 2: '.$key
                );
            }
            if(($base === 'any' || $base === 'all' || $base === 'not') && is_array($value)){
                $this->assertFlatExecutable($this->normalizeQueryArray($value));
            }elseif(is_array($value) && !in_array($base, array(
                't','type','typeid','id','ids','tag','keyword','kwd','sortby','sort','s'
            ), true)){
                throw new UnsupportedQueryException('Nested query requires Phase 2 execution: '.$key);
            }
        }
    }

    /** Validate the bounded subset that can be expressed as correlated EXISTS. */
    private function assertSqlExecutable(array $query, int $depth = 0): void
    {
        if($depth > self::MAX_SQL_LINK_DEPTH){
            throw new UnsupportedQueryException('Linked query exceeds the SQL nesting limit');
        }
        foreach($query as $predicate){
            $key = (string)array_keys($predicate)[0];
            $value = $predicate[$key];
            list($base, $suffix) = $this->predicateParts($key);
            if(in_array($base, array('links','related','r','relf'), true)){
                throw new UnsupportedQueryException('Predicate requires chunked execution: '.$key);
            }
            if(in_array($base, array('lt','linked_to','linkedto','lf','linked_from','linkedfrom'), true)
                && $this->isLinkFieldPresenceTest($suffix, $value)){
                continue;
            }
            if(in_array($base, array('rt','related_to','relatedto','rf','related_from','relatedfrom'), true)
                && $suffix !== ''){
                throw new UnsupportedQueryException('Relation-marker predicates require chunked execution: '.$key);
            }
            if(in_array($base, array('any','all','not'), true)){
                $this->assertSqlExecutable($this->normalizeQueryArray($value), $depth);
            }elseif(in_array($base, self::LINK_PREDICATES, true)){
                $child = $this->linkedValueQuery($value);
                if(in_array($base, array('rt','related_to','relatedto','rf','related_from','relatedfrom'), true)){
                    list($child, $relationship) = $this->splitRelationshipQuery($child);
                    $this->assertSqlExecutable($relationship, $depth+1);
                }
                $this->assertSqlExecutable($child, $depth+1);
            }elseif(is_array($value)
                && !in_array($base, array(
                    't','type','typeid','id','ids','tag','keyword','kwd','sortby','sort','s','geo'
                ), true)){
                throw new UnsupportedQueryException('Nested query cannot be compiled to SQL: '.$key);
            }
        }
    }

    private function compileGroup(
        array $group,
        string $operator,
        array &$state,
        string $recordAlias = 'r',
        int $depth = 0
    ): array
    {
        $conditions = array();
        foreach($group as $predicate){
            $key = (string)array_keys($predicate)[0];
            $value = $predicate[$key];
            list($base, $suffix) = $this->predicateParts($key);
            if($base === 'sortby' || $base === 'sort' || $base === 's'){
                continue;
            }
            if($base === 'any' || $base === 'all' || $base === 'not'){
                $nested = $this->normalizeQueryArray($value);
                $nestedConditions = $this->compileGroup(
                    $nested,
                    $base === 'any' ? 'OR' : 'AND',
                    $state,
                    $recordAlias,
                    $depth
                );
                $expression = '('.implode($base === 'any' ? ' OR ' : ' AND ', $nestedConditions).')';
                $conditions[] = $base === 'not' ? 'NOT '.$expression : $expression;
                continue;
            }
            $conditions[] = $this->compilePredicate(
                $base, $suffix, $value, $state, $recordAlias, $depth
            );
        }
        if(empty($conditions)){ $conditions[] = '1=1'; }
        if($operator === 'OR' && count($conditions)>1){
            return array('('.implode(' OR ', $conditions).')');
        }
        return $conditions;
    }

    private function compilePredicate(
        string $base,
        string $suffix,
        $value,
        array &$state,
        string $recordAlias = 'r',
        int $depth = 0
    ): string {
        $r = $recordAlias;
        switch($base){
            case 't': case 'type': case 'typeid': case 'typename':
                return $this->recordTypeCondition($r.'.rec_RecTypeID', $value, $state);
            case 'id': case 'ids':
                if(is_array($value) && empty($value)){ return '0=1'; }
                return $this->recordIdCondition($r.'.rec_ID', $value, $state);
            case '_all':
                return '1=1';
            case 'title':
                return $this->textCondition($r.'.rec_Title', $value, $state);
            case 'url':
                return $this->textCondition($r.'.rec_URL', $value, $state);
            case 'notes':
                return $this->textCondition($r.'.rec_ScratchPad', $value, $state);
            case 'added':
                return $this->headerDateCondition($r.'.rec_Added', $value, $state);
            case 'modified':
                return $this->headerDateCondition($r.'.rec_Modified', $value, $state);
            case 'before':
                return $this->headerDateCondition($r.'.rec_Modified', '<='.(string)$value, $state);
            case 'after': case 'since':
                return $this->headerDateCondition($r.'.rec_Modified', '>'.(string)$value, $state);
            case 'addedby':
                return $this->userCondition($r.'.rec_AddedByUGrpID', $value, $state);
            case 'owner': case 'workgroup': case 'wg':
                return $this->userCondition($r.'.rec_OwnerUGrpID', $value, $state);
            case 'access':
                return $this->scalarCondition($r.'.rec_NonOwnerVisibility', $value, $state);
            case 'f': case 'field':
                if($suffix === ''){ return $this->anyFieldCondition($value, $state, $r); }
                list($fieldId, $termField) = $this->fieldSuffixParts($suffix);
                return $this->fieldCondition($fieldId, $value, $state, $r, $termField);
            case 'fc': case 'count': case 'cnt':
                return $this->fieldCountCondition(intval($suffix), $value, $state, $r);
            case 'geo':
                return $this->geoCondition($suffix, $value, $state, $r);
            case 'user': case 'usr':
                $this->bind($state, $this->resolveUserId($value, $state), 'i');
                return 'EXISTS (SELECT 1 FROM usrBookmarks ub WHERE ub.bkm_recID='.$r.'.rec_ID AND ub.bkm_UGrpID=?)';
            case 'ws': case 'workset':
                $this->bind($state, $this->resolveUserId($value, $state), 'i');
                return 'EXISTS (SELECT 1 FROM usrWorkingSubsets wss '
                    .'WHERE wss.wss_RecID='.$r.'.rec_ID AND wss.wss_OwnerUGrpID=?)';
            case 'tag': case 'keyword': case 'kwd':
                return $this->tagCondition($r, $value, $state);
            case 'lt': case 'linked_to': case 'linkedto':
                if($this->isLinkFieldPresenceTest($suffix, $value)){
                    return $this->fieldCondition(intval($suffix), $value, $state, $r);
                }
                return $this->compileResourceLink($r, 'to', $suffix, $value, $state, $depth+1);
            case 'lf': case 'linked_from': case 'linkedfrom':
                if($this->isLinkFieldPresenceTest($suffix, $value)){
                    return $this->fieldCondition(intval($suffix), $value, $state, $r);
                }
                return $this->compileResourceLink($r, 'from', $suffix, $value, $state, $depth+1);
            case 'rt': case 'related_to': case 'relatedto':
                return $this->compileRelationship($r, 'to', $value, $state, $depth+1);
            case 'rf': case 'related_from': case 'relatedfrom':
                return $this->compileRelationship($r, 'from', $value, $state, $depth+1);
            case 'file':
                return $this->fileCondition($suffix, $value, $state, $r);
            default:
                throw new UnsupportedQueryException('Predicate is not executable in Phase 1: '.$base);
        }
    }

    /** Compile a direct record-pointer edge as a correlated EXISTS expression. */
    private function compileResourceLink(
        string $parentAlias,
        string $direction,
        string $suffix,
        $value,
        array &$state,
        int $depth
    ): string {
        $linkAlias = $this->nextAlias($state, 'rl');
        $childAlias = $this->nextAlias($state, 'lr');
        $childQuery = $this->linkedValueQuery($value);
        $parentColumn = $direction === 'to' ? 'rl_SourceID' : 'rl_TargetID';
        $childColumn = $direction === 'to' ? 'rl_TargetID' : 'rl_SourceID';
        $edge = array(
            $linkAlias.'.'.$parentColumn.'='.$parentAlias.'.rec_ID',
            $linkAlias.'.rl_RelationID IS NULL'
        );
        if($suffix !== ''){
            if(!ctype_digit($suffix) || intval($suffix)<1){
                throw new QueryValidationException('Resource-link field ID must be positive');
            }
            $this->bind($state, intval($suffix), 'i');
            $edge[] = $linkAlias.'.rl_DetailTypeID=?';
        }else{
            $edge[] = $linkAlias.'.rl_DetailTypeID>0';
        }
        $childWhere = $this->compileGroup($childQuery, 'AND', $state, $childAlias, $depth);
        $this->appendAccessConditions($childWhere, $state, $state['context'], $childAlias);

        return 'EXISTS (SELECT 1 FROM recLinks '.$linkAlias
            .' INNER JOIN Records '.$childAlias.' ON '.$childAlias.'.rec_ID='
            .$linkAlias.'.'.$childColumn
            .' WHERE '.implode(' AND ', array_merge($edge, $childWhere)).')';
    }

    /** Compile a directional Relationship-record edge as correlated EXISTS. */
    private function compileRelationship(
        string $parentAlias,
        string $direction,
        $value,
        array &$state,
        int $depth
    ): string {
        $linkAlias = $this->nextAlias($state, 'rrl');
        $childAlias = $this->nextAlias($state, 'rr');
        $relationshipAlias = $this->nextAlias($state, 'rel');
        list($childQuery, $relationshipQuery, $relationTypes) =
            $this->splitRelationshipQuery($this->linkedValueQuery($value));

        $parentColumn = $direction === 'to' ? 'rl_SourceID' : 'rl_TargetID';
        $childColumn = $direction === 'to' ? 'rl_TargetID' : 'rl_SourceID';
        $edge = array(
            $linkAlias.'.'.$parentColumn.'='.$parentAlias.'.rec_ID',
            $linkAlias.'.rl_RelationID IS NOT NULL'
        );
        if($relationTypes !== null){
            $edge[] = $this->relationshipTypeCondition($linkAlias, $relationTypes, $state);
        }
        $childWhere = $this->compileGroup($childQuery, 'AND', $state, $childAlias, $depth);
        $this->appendAccessConditions($childWhere, $state, $state['context'], $childAlias);
        $relationshipWhere = $this->compileGroup(
            $relationshipQuery, 'AND', $state, $relationshipAlias, $depth
        );
        $this->appendAccessConditions(
            $relationshipWhere, $state, $state['context'], $relationshipAlias
        );

        return 'EXISTS (SELECT 1 FROM recLinks '.$linkAlias
            .' INNER JOIN Records '.$childAlias.' ON '.$childAlias.'.rec_ID='
            .$linkAlias.'.'.$childColumn
            .' INNER JOIN Records '.$relationshipAlias.' ON '.$relationshipAlias.'.rec_ID='
            .$linkAlias.'.rl_RelationID'
            .' WHERE '.implode(' AND ', array_merge($edge, $childWhere, $relationshipWhere)).')';
    }

    /** Separate endpoint predicates from Relationship-record predicates. */
    private function splitRelationshipQuery(array $query): array
    {
        $child = array();
        $relationship = array();
        $types = null;
        foreach($query as $predicate){
            $key = (string)array_keys($predicate)[0];
            $value = $predicate[$key];
            list($base, $suffix) = $this->predicateParts($key);
            if($base === 'r' && $suffix === ''){
                $current = $this->numericList($value, 'relationship type');
                $types = $types === null ? $current : array_values(array_intersect($types, $current));
            }elseif($base === 'relf' || ($base === 'r' && $suffix !== '')){
                if($suffix === '' || !ctype_digit($suffix) || intval($suffix)<1){
                    throw new QueryValidationException('Relationship-record field ID must be positive');
                }
                $relationship[] = array('f:'.intval($suffix)=>$value);
            }else{
                $child[] = $predicate;
            }
        }
        return array(
            empty($child) ? array(array('_all'=>true)) : $child,
            empty($relationship) ? array(array('_all'=>true)) : $relationship,
            $types
        );
    }

    /** Include requested relationship terms and descendants in the closure table. */
    private function relationshipTypeCondition(string $linkAlias, array $types, array &$state): string
    {
        if(empty($types)){ return '0=1'; }
        $direct = implode(',', array_fill(0, count($types), '?'));
        foreach($types as $type){ $this->bind($state, $type, 'i'); }
        $descendants = implode(',', array_fill(0, count($types), '?'));
        foreach($types as $type){ $this->bind($state, $type, 'i'); }
        return '('.$linkAlias.'.rl_RelationTypeID IN ('.$direct.') OR '
            .$linkAlias.'.rl_RelationTypeID IN (SELECT trl_TermID FROM defTermsLinks '
            .'WHERE trl_ParentID IN ('.$descendants.')))';
    }

    /** Normalize a scalar linked ID or an established nested JSON query. */
    private function linkedValueQuery($value): array
    {
        if(is_array($value) && !$this->isScalarValueList($value)){
            return $this->normalizeQueryArray($value);
        }
        return array(array('ids'=>$this->numericList($value, 'linked record')));
    }

    private function nextAlias(array &$state, string $prefix): string
    {
        $state['aliasCounter'] = intval($state['aliasCounter'] ?? 0) + 1;
        return $prefix.$state['aliasCounter'];
    }

    private function appendAccessConditions(
        array &$where,
        array &$state,
        array $context,
        string $recordAlias = 'r'
    ): void
    {
        $r = $recordAlias;
        $userId = max(0, intval($context['userId'] ?? 0));
        $groupIds = array_values(array_unique(array_filter(array_map(
            'intval',
            is_array($context['groupIds'] ?? null) ? $context['groupIds'] : array()
        ), static function($id){ return $id >= 0; })));
        if($userId > 0 && !in_array($userId, $groupIds, true)){ $groupIds[] = $userId; }
        if(!in_array(0, $groupIds, true)){ $groupIds[] = 0; }

        $isOwner = !empty($context['isDbOwner']);
        $where[] = $r.'.rec_FlagTemporary=0';

        if($userId < 1){
            $where[] = $r.'.rec_NonOwnerVisibility IN ("public","pending")';
        }elseif(!$isOwner){
            $visibility = array($r.'.rec_NonOwnerVisibility IN ("public","pending")');
            if(!empty($groupIds)){
                $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
                foreach($groupIds as $id){ $this->bind($state, $id, 'i'); }
                $visibility[] = $r.'.rec_OwnerUGrpID IN ('.$placeholders.')';

                foreach($groupIds as $id){ $this->bind($state, $id, 'i'); }
                $visibility[] = '('.$r.'.rec_NonOwnerVisibility="viewable" AND ('
                    .'NOT EXISTS (SELECT 1 FROM usrRecPermissions rp0 WHERE rp0.rcp_RecID='.$r.'.rec_ID) OR '
                    .'EXISTS (SELECT 1 FROM usrRecPermissions rp WHERE rp.rcp_RecID='.$r.'.rec_ID '
                    .'AND rp.rcp_UGrpID IN ('.$placeholders.'))))';
            }
            $where[] = '('.implode(' OR ', $visibility).')';
        }
    }

    private function compileSort(array $query, array &$state): string
    {
        $sortValues = array();
        foreach($query as $predicate){
            $key = (string)array_keys($predicate)[0];
            list($base) = $this->predicateParts($key);
            if(in_array($base, array('sortby','sort','s'), true)){
                $value = $predicate[$key];
                foreach(is_array($value) ? $value : explode(',', (string)$value) as $sort){
                    $sort = trim((string)$sort);
                    if($sort !== ''){ $sortValues[] = $sort; }
                }
            }
        }
        if(empty($sortValues)){ return ' ORDER BY r.rec_ID'; }

        $expressions = array();
        foreach($sortValues as $sort){
            $direction = 'ASC';
            if($sort[0] === '-'){
                $direction = 'DESC'; $sort = substr($sort, 1);
            }elseif($sort[0] === '+'){
                $sort = substr($sort, 1);
            }
            list($base, $suffix) = $this->predicateParts(strtolower($sort));
            switch($base){
                case 'id': case 'ids': $expressions[] = 'r.rec_ID '.$direction; break;
                case 't': case 'title': $expressions[] = 'r.rec_Title '.$direction; break;
                case 'rt': case 'type': $expressions[] = 'r.rec_RecTypeID '.$direction; break;
                case 'm': case 'modified': $expressions[] = 'r.rec_Modified '.$direction; break;
                case 'a': case 'added': $expressions[] = 'r.rec_Added '.$direction; break;
                case 'u': case 'url': $expressions[] = 'r.rec_URL '.$direction; break;
                case 'p': case 'popularity': $expressions[] = 'r.rec_Popularity '.$direction; break;
                case 'r': case 'rating':
                    $userId = intval($state['context']['userId'] ?? 0);
                    if($userId < 1){ throw new QueryValidationException('Rating sort requires authentication'); }
                    $this->bind($state, $userId, 'i');
                    $expressions[] = 'COALESCE((SELECT ub.bkm_Rating FROM usrBookmarks ub '
                        .'WHERE ub.bkm_recID=r.rec_ID AND ub.bkm_UGrpID=? LIMIT 1),0) '.$direction;
                    break;
                case 'f': case 'field':
                    if($suffix === '' || !ctype_digit($suffix)){
                        throw new QueryValidationException('Field sort requires a numeric field ID');
                    }
                    $fieldId = intval($suffix); $fieldType = $this->fieldType($fieldId);
                    $this->bind($state, $fieldId, 'i');
                    if($fieldType === 'date'){
                        $expressions[] = '(SELECT MIN(si.rdi_estMinDate) FROM recDetailsDateIndex si '
                            .'INNER JOIN recDetails sdate ON sdate.dtl_ID=si.rdi_DetailID '
                            .'WHERE si.rdi_RecID=r.rec_ID AND si.rdi_DetailTypeID=?'
                            .$this->detailVisibilityCondition('sdate', 'r', $state).') '.$direction;
                    }elseif($fieldType === 'enum'){
                        $expressions[] = '(SELECT MIN(CONCAT(LPAD(COALESCE(st.trm_OrderInBranch,999999),6,"0"),st.trm_Label)) '
                            .'FROM recDetails sd INNER JOIN defTerms st ON st.trm_ID=sd.dtl_Value '
                            .'WHERE sd.dtl_RecID=r.rec_ID AND sd.dtl_DetailTypeID=?'
                            .$this->detailVisibilityCondition('sd', 'r', $state).') '.$direction;
                    }elseif($fieldType === 'integer'){
                        $expressions[] = '(SELECT MIN(CAST(sd.dtl_Value AS SIGNED)) FROM recDetails sd '
                            .'WHERE sd.dtl_RecID=r.rec_ID AND sd.dtl_DetailTypeID=?'
                            .$this->detailVisibilityCondition('sd', 'r', $state).') '.$direction;
                    }elseif($fieldType === 'float'){
                        $expressions[] = '(SELECT MIN(CAST(sd.dtl_Value AS DECIMAL(65,20))) FROM recDetails sd '
                            .'WHERE sd.dtl_RecID=r.rec_ID AND sd.dtl_DetailTypeID=?'
                            .$this->detailVisibilityCondition('sd', 'r', $state).') '.$direction;
                    }else{
                        $expressions[] = '(SELECT MIN(sd.dtl_Value) FROM recDetails sd '
                            .'WHERE sd.dtl_RecID=r.rec_ID AND sd.dtl_DetailTypeID=?'
                            .$this->detailVisibilityCondition('sd', 'r', $state).') '.$direction;
                    }
                    $expressions[] = 'r.rec_Title ASC';
                    break;
                case 'set': case 'fixed':
                    $ids = $suffix === '' ? $this->fixedSortIds($query) : $this->numericList($suffix, 'fixed sort record');
                    if(empty($ids)){ throw new QueryValidationException('Fixed-set sort requires an IDs predicate'); }
                    $placeholders = array();
                    foreach($ids as $id){ $this->bind($state, $id, 'i'); $placeholders[] = '?'; }
                    $expressions[] = 'FIELD(r.rec_ID,'.implode(',', $placeholders).') ASC';
                    break;
                default:
                    throw new QueryValidationException('Unknown sort expression: '.$sort);
            }
        }
        $expressions[] = 'r.rec_ID ASC';
        return ' ORDER BY '.implode(', ', array_values(array_unique($expressions)));
    }

    private function fixedSortIds(array $query): array
    {
        foreach($query as $predicate){
            $key = (string)array_keys($predicate)[0];
            list($base) = $this->predicateParts($key);
            if($base === 'id' || $base === 'ids'){
                try{ return $this->numericList($predicate[$key], 'fixed sort record'); }
                catch(QueryValidationException $e){ return array(); }
            }
        }
        return array();
    }

    private function fieldCondition(
        int $fieldId,
        $value,
        array &$state,
        string $recordAlias = 'r',
        ?string $termField = null
    ): string
    {
        if($fieldId < 1){ throw new QueryValidationException('Field ID must be positive'); }
        $nullValue = $value === null ? 'NULL' : strtoupper(trim((string)$value));
        if($nullValue === 'NULL'){
            $this->bind($state, $fieldId, 'i');
            return 'NOT EXISTS (SELECT 1 FROM recDetails d WHERE d.dtl_RecID='.$recordAlias.'.rec_ID '
                .'AND d.dtl_DetailTypeID=?'.$this->detailVisibilityCondition('d', $recordAlias, $state).')';
        }
        if($nullValue === '-NULL'){
            $this->bind($state, $fieldId, 'i');
            return 'EXISTS (SELECT 1 FROM recDetails d WHERE d.dtl_RecID='.$recordAlias.'.rec_ID '
                .'AND d.dtl_DetailTypeID=?'.$this->detailVisibilityCondition('d', $recordAlias, $state).')';
        }
        $this->bind($state, $fieldId, 'i');
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
    private function languageTextCondition(string $column, $value, array &$state): string
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
        $this->bind($state, $language.':%', 's');
        return '('.$condition.' AND '.$column.' LIKE ?)';
    }

    /**
     * Search title, non-resource detail values, enum labels/codes and linked titles.
     * This is the modern equivalent of legacy f/field without a detail type ID.
     */
    private function anyFieldCondition($value, array &$state, string $recordAlias = 'r'): string
    {
        if(is_array($value)){ throw new QueryValidationException('Any-field search requires a scalar value'); }
        $text = (string)$value;
        $conditions = array();

        $conditions[] = $this->textCondition($recordAlias.'.rec_Title', $text, $state);

        $detailCondition = $this->textCondition('ad.dtl_Value', $text, $state);
        $conditions[] = 'EXISTS (SELECT 1 FROM recDetails ad '
            .'INNER JOIN defDetailTypes adt ON adt.dty_ID=ad.dtl_DetailTypeID '
            .'WHERE ad.dtl_RecID='.$recordAlias.'.rec_ID '
            .'AND adt.dty_Type<>"resource" AND adt.dty_Type<>"enum" '
            .'AND '.$detailCondition.$this->detailVisibilityCondition('ad', $recordAlias, $state).')';

        $termText = $text;
        if(strpos($termText, '@+') === 0 || strpos($termText, '@-') === 0){ $termText = substr($termText, 2); }
        elseif(strpos($termText, '@') === 0){ $termText = substr($termText, 1); }
        $termCondition = $this->termCondition('ae.dtl_Value', $termText, $state);
        $conditions[] = 'EXISTS (SELECT 1 FROM recDetails ae '
            .'INNER JOIN defDetailTypes aet ON aet.dty_ID=ae.dtl_DetailTypeID '
            .'WHERE ae.dtl_RecID='.$recordAlias.'.rec_ID AND aet.dty_Type="enum" '
            .'AND '.$termCondition.$this->detailVisibilityCondition('ae', $recordAlias, $state).')';

        $linkedTitleCondition = $this->textCondition('alr.rec_Title', $text, $state);
        $conditions[] = 'EXISTS (SELECT 1 FROM recLinks al '
            .'INNER JOIN Records alr ON alr.rec_ID=al.rl_TargetID '
            .'INNER JOIN recDetails ald ON ald.dtl_ID=al.rl_DetailID '
            .'WHERE al.rl_SourceID='.$recordAlias.'.rec_ID '
            .'AND al.rl_RelationID IS NULL AND al.rl_DetailTypeID>0 '
            .'AND '.$linkedTitleCondition.$this->detailVisibilityCondition('ald', $recordAlias, $state).')';

        return '('.implode(' OR ', $conditions).')';
    }

    /** Apply defRecStructure and per-detail visibility for non-owner searches. */
    private function detailVisibilityCondition(string $detailAlias, string $recordAlias, array $state): string
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
    private function isLinkFieldPresenceTest(string $suffix, $value): bool
    {
        if($suffix === '' || !ctype_digit($suffix) || is_array($value)){ return false; }
        $value = strtoupper(trim((string)$value));
        return $value === 'NULL' || $value === '-NULL';
    }

    private function isValidFieldSuffix(string $suffix): bool
    {
        return preg_match('/^[1-9]\d*(?::(?:term|label|concept|conceptid|desc|code))?$/', $suffix) === 1;
    }

    /** Return [detail type ID, optional enum metadata selector]. */
    private function fieldSuffixParts(string $suffix): array
    {
        $parts = explode(':', strtolower($suffix), 2);
        return array(intval($parts[0]), $parts[1] ?? null);
    }

    /** Registered-file predicate used by file or file:fieldID. */
    private function fileCondition(string $suffix, $value, array &$state, string $recordAlias): string
    {
        if($suffix !== '' && (!ctype_digit($suffix) || intval($suffix)<1)){
            throw new QueryValidationException('File predicate field ID must be positive');
        }
        $fieldSql = '';
        if($suffix !== ''){ $this->bind($state, intval($suffix), 'i'); $fieldSql = ' AND fd.dtl_DetailTypeID=?'; }
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
            $this->bind($state, substr($text, 1), 's');
            $fileWhere = 'fu.ulf_ObfuscatedFileID=?';
        }elseif(strpos($text, '^') === 0){
            list($operator, $number) = $this->comparison(substr($text, 1));
            if(!is_numeric($number)){ throw new QueryValidationException('File size predicate requires a numeric value'); }
            if($operator === 'LIKE'){ $operator = '='; }
            $this->bind($state, (float)$number, 'd');
            $fileWhere = 'fu.ulf_FileSizeKB '.$operator.' ?';
        }else{
            $pattern = $this->likePattern(ltrim($text, '='));
            $operator = strpos($text, '=') === 0 ? '=' : 'LIKE';
            if($operator === '='){ $pattern = substr($text, 1); }
            foreach(array(1,2,3) as $_){ $this->bind($state, $pattern, 's'); }
            $fileWhere = '(fu.ulf_OrigFileName '.$operator.' ? OR fu.ulf_ExternalFileReference '.$operator.' ? '
                .'OR fu.ulf_Description '.$operator.' ?)';
        }
        return 'EXISTS (SELECT 1 FROM recDetails fd INNER JOIN recUploadedFiles fu '
            .'ON fu.ulf_ID=fd.dtl_UploadedFileID WHERE fd.dtl_RecID='.$recordAlias.'.rec_ID'
            .$fieldSql.' AND '.$fileWhere.$this->detailVisibilityCondition('fd', $recordAlias, $state).')';
    }

    /** Owner/creator accepts IDs, current-user aliases, or login names. */
    private function userCondition(string $column, $value, array &$state): string
    {
        $items = is_array($value) ? $value : preg_split('/\s*,\s*/', trim((string)$value));
        $negate = false; $ids = array(); $names = array();
        foreach($items as $item){
            $item = trim((string)$item);
            if(strpos($item, '-') === 0){ $negate = true; $item = substr($item, 1); }
            if(ctype_digit($item)){ $ids[] = intval($item); continue; }
            if(in_array(strtolower($item), array('current','currentuser','current_user'), true)){
                $ids[] = $this->resolveUserId('current', $state); continue;
            }
            if($item !== ''){ $names[] = $item; }
        }
        $ids = array_merge($ids, $this->lookupUserIds($names));
        $ids = array_values(array_unique($ids));
        if(empty($ids)){ return $negate ? '1=1' : '0=1'; }
        $condition = $this->inCondition($column, $ids, $state);
        return $negate ? 'NOT ('.$condition.')' : $condition;
    }

    private function lookupUserIds(array $names): array
    {
        if(!$this->mysqli || empty($names)){ return array(); }
        $quoted = array();
        foreach(array_values(array_unique($names)) as $name){
            $quoted[] = '"'.$this->mysqli->real_escape_string($name).'"';
        }
        $result = $this->mysqli->query('SELECT ugr_ID FROM sysUGrps WHERE ugr_Name IN ('.implode(',', $quoted).')');
        if(!$result){ throw new SearchExecutionException('Unable to resolve user name: '.$this->mysqli->error); }
        $ids = array();
        while($row = $result->fetch_row()){ if(intval($row[0])>0){ $ids[] = intval($row[0]); } }
        $result->close();
        return $ids;
    }

    /** Complete numeric/text tag predicate including any/all and NULL forms. */
    private function tagCondition(string $recordAlias, $value, array &$state): string
    {
        $all = false;
        if(is_array($value) && $this->isAssociative($value)){
            $all = array_key_exists('all', $value);
            $value = $value[$all ? 'all' : 'any'] ?? array();
        }
        $values = is_array($value) ? $value : preg_split('/\s*,\s*/', trim((string)$value));
        $values = array_values(array_filter(array_map('strval', $values), static function($v){ return $v !== ''; }));
        if(count($values) === 1 && strtoupper($values[0]) === 'NULL'){
            return 'NOT EXISTS (SELECT 1 FROM usrRecTagLinks rtl WHERE rtl.rtl_RecID='.$recordAlias.'.rec_ID)';
        }
        if(empty($values) || (count($values) === 1 && strtoupper($values[0]) === '-NULL')){
            return 'EXISTS (SELECT 1 FROM usrRecTagLinks rtl WHERE rtl.rtl_RecID='.$recordAlias.'.rec_ID)';
        }
        $negate = false;
        foreach($values as &$tag){ if(strpos($tag, '-') === 0){ $negate = true; $tag = substr($tag, 1); } }
        unset($tag);
        $numeric = count(array_filter($values, 'ctype_digit')) === count($values);
        $column = $numeric ? 'rtl.rtl_TagID' : 'ut.tag_Text';
        $conditions = array();
        foreach($values as $tag){
            $this->bind($state, $numeric ? intval($tag) : $tag, $numeric ? 'i' : 's');
            $conditions[] = $column.'=?';
        }
        $sql = 'SELECT rtl.rtl_RecID FROM usrRecTagLinks rtl';
        if(!$numeric){ $sql .= ' INNER JOIN usrTags ut ON ut.tag_ID=rtl.rtl_TagID'; }
        $sql .= ' WHERE ('.implode(' OR ', $conditions).')';
        if($all && count($values)>1){ $sql .= ' GROUP BY rtl.rtl_RecID HAVING COUNT(DISTINCT '.$column.')='.count($values); }
        $condition = $recordAlias.'.rec_ID IN ('.$sql.')';
        return $negate ? 'NOT ('.$condition.')' : $condition;
    }

    private function fieldCountCondition(int $fieldId, $value, array &$state, string $recordAlias = 'r'): string
    {
        $this->bind($state, $fieldId, 'i');
        list($operator, $cleanValue) = $this->comparison((string)$value);
        if($operator === 'LIKE'){ $operator = '='; }
        if($operator === 'NOT LIKE'){ $operator = '<>'; }
        if(!is_numeric($cleanValue)){ throw new QueryValidationException('Field count requires a numeric value'); }
        $count = intval($cleanValue);
        $this->bind($state, $count, 'i');
        return '(SELECT COUNT(*) FROM recDetails dc WHERE dc.dtl_RecID='.$recordAlias.'.rec_ID '
            .'AND dc.dtl_DetailTypeID=?'.$this->detailVisibilityCondition('dc', $recordAlias, $state).') '
            .$operator.' ?';
    }

    private function geoCondition(string $suffix, $value, array &$state, string $recordAlias = 'r'): string
    {
        $fieldSql = '';
        if($suffix !== ''){
            if(!ctype_digit($suffix) || intval($suffix)<1){
                throw new QueryValidationException('Geo predicate field ID must be numeric');
            }
            $this->bind($state, intval($suffix), 'i');
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
                $this->bind($state, $wkt, 's');
                return 'EXISTS (SELECT 1 FROM recDetails gd WHERE gd.dtl_RecID='.$recordAlias.'.rec_ID '
                    .'AND gd.dtl_Geo IS NOT NULL'.$fieldSql.' '
                    .'AND ST_Intersects(ST_GeomFromText(?),gd.dtl_Geo)'
                    .$this->detailVisibilityCondition('gd', $recordAlias, $state).')';
            }
            return 'EXISTS (SELECT 1 FROM recDetails gd WHERE gd.dtl_RecID='.$recordAlias.'.rec_ID '
                .'AND gd.dtl_Geo IS NOT NULL'.$fieldSql
                .$this->detailVisibilityCondition('gd', $recordAlias, $state).')';
        }
        $this->bind($state, $text, 's');
        return 'EXISTS (SELECT 1 FROM recDetails gd WHERE gd.dtl_RecID='.$recordAlias.'.rec_ID '
            .'AND gd.dtl_Geo IS NOT NULL'.$fieldSql.' '
            .'AND ST_Contains(ST_GeomFromText(?),gd.dtl_Geo)'
            .$this->detailVisibilityCondition('gd', $recordAlias, $state).')';
    }

    private function textCondition(string $column, $value, array &$state): string
    {
        $text = (string)$value;
        if(strpos($text, '@') === 0){ return $this->fulltextCondition($column, $text, $state); }
        if(strpos($text, '==') === 0){
            $this->bind($state, substr($text, 2), 's');
            return 'BINARY '.$column.' = BINARY ?';
        }
        if(strpos($text, '=') === 0){
            return $this->forcedComparison($column, '=', substr($text, 1), $state);
        }
        if(strpos($text, '-') === 0){
            $this->bind($state, $this->likePattern(substr($text, 1)), 's');
            return $column.' NOT LIKE ? ESCAPE "\\\\"';
        }
        $this->bind($state, $this->likePattern($text), 's');
        return $column.' LIKE ? ESCAPE "\\\\"';
    }

    private function scalarCondition(string $column, $value, array &$state): string
    {
        $text = trim((string)$value);
        if(strpos($text, '@') === 0){ return $this->fulltextCondition($column, $text, $state); }
        if(strpos($text, '==') === 0){
            $this->bind($state, substr($text, 2), 's');
            return 'BINARY '.$column.' = BINARY ?';
        }
        if(($range = $this->splitRange($text, '<>')) !== null){
            $this->bind($state, $range[0], 's'); $this->bind($state, $range[1], 's');
            return $column.' BETWEEN ? AND ?';
        }
        list($operator, $cleanValue) = $this->comparison((string)$value);
        if($operator === 'LIKE' || $operator === 'NOT LIKE'){
            $this->bind($state, $this->likePattern($cleanValue), 's');
            return $column.' '.$operator.' ? ESCAPE "\\\\"';
        }
        return $this->forcedComparison($column, $operator, $cleanValue, $state);
    }

    private function integerCondition(string $column, $value, array &$state): string
    {
        list($operator, $cleanValue) = $this->comparison((string)$value);
        if(!is_numeric($cleanValue)){
            throw new QueryValidationException('Numeric value required for '.$column);
        }
        $this->bind($state, intval($cleanValue), 'i');
        return $column.' '.$operator.' ?';
    }

    private function forcedComparison(
        string $column,
        string $operator,
        $value,
        array &$state
    ): string {
        $type = is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
        $this->bind($state, $value, $type);
        return $column.' '.$operator.' ?';
    }

    private function comparison(string $value): array
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

    private function inCondition(string $column, array $values, array &$state): string
    {
        if(count($values) === 1){
            $this->bind($state, reset($values), 'i');
            return $column.' = ?';
        }
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        foreach($values as $value){ $this->bind($state, $value, 'i'); }
        return $column.' IN ('.$placeholders.')';
    }

    private function numericList($value, string $label): array
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

    private function recordIdCondition(string $column, $value, array &$state): string
    {
        $text = is_array($value) ? '' : trim((string)$value); $negate = false;
        if($text !== '' && strpos($text, '-') === 0){ $negate = true; $value = substr($text, 1); }
        $ids = $this->numericList($value, 'record');
        $ids = array_values(array_unique(array_merge($ids, $this->replacementRecordIds($ids))));
        $condition = $this->inCondition($column, $ids, $state);
        return $negate ? 'NOT ('.$condition.')' : $condition;
    }

    /** Follow recForwarding internally, retaining both requested and current IDs. */
    private function replacementRecordIds(array $recordIds): array
    {
        if(!$this->mysqli || empty($recordIds)){ return $recordIds; }
        $current = array_values(array_unique(array_map('intval', $recordIds)));
        $seen = array_fill_keys($current, true);
        for($level=0; $level<10; $level++){
            $result = $this->mysqli->query(
                'SELECT rfw_OldRecID,rfw_NewRecID FROM recForwarding WHERE rfw_OldRecID IN ('
                .implode(',', $current).')'
            );
            if(!$result){ throw new SearchExecutionException('Unable to resolve replacement record: '.$this->mysqli->error); }
            $next = array();
            while($row = $result->fetch_row()){
                $id = intval($row[1]);
                if($id > 0 && !isset($seen[$id])){ $seen[$id] = true; $next[] = $id; }
            }
            $result->close();
            if(empty($next)){ break; }
            $current = array_values(array_unique($next));
        }
        return array_map('intval', array_keys($seen));
    }

    /** Record types accept local IDs, names, and OriginDB-ID concept codes. */
    private function recordTypeCondition(string $column, $value, array &$state): string
    {
        if(is_array($value)){
            $ids = $this->numericList($value, 'record type');
            return $this->inCondition($column, $ids, $state);
        }
        $text = trim((string)$value); $negate = false;
        if(strpos($text, '-') === 0 && !preg_match('/^\d+-\d+$/', $text)){
            $negate = true; $text = substr($text, 1);
        }
        if(preg_match('/^\d+(?:\s*,\s*\d+)*$/', $text)){
            $condition = $this->inCondition($column, $this->numericList($text, 'record type'), $state);
        }elseif(preg_match('/^(\d+)-(\d+)$/', $text, $matches)){
            $this->bind($state, intval($matches[1]), 'i'); $this->bind($state, intval($matches[2]), 'i');
            $condition = $column.' = (SELECT rty_ID FROM defRecTypes '
                .'WHERE rty_OriginatingDBID=? AND rty_IDInOriginatingDB=? LIMIT 1)';
        }else{
            $this->bind($state, $text, 's');
            $condition = $column.' = (SELECT rty_ID FROM defRecTypes WHERE rty_Name=? LIMIT 1)';
        }
        return $negate ? 'NOT ('.$condition.')' : $condition;
    }

    private function predicateParts(string $key): array
    {
        $parts = explode(':', strtolower(trim($key)), 2);
        return array($parts[0], $parts[1] ?? '');
    }

    private function isKnownPredicate(string $base): bool
    {
        return in_array($base, array(
            '_all','plain','t','type','typeid','typename','id','ids','title','url','notes',
            'added','modified','before','after','since','addedby','owner','workgroup','wg',
            'access','user','usr','ws','workset','tag','keyword','kwd','f','field','fc','count','cnt',
            'geo','file','lt','linked_to','linkedto','lf','linked_from','linkedfrom',
            'rt','related_to','relatedto','rf','related_from','relatedfrom','related',
            'links','relf','r','any','all','not','sortby','sort','s'
        ), true);
    }

    private function newBuildState(array $context = array()): array
    {
        return array('types'=>'', 'values'=>array(), 'context'=>$context, 'aliasCounter'=>0);
    }

    private function bind(array &$state, $value, string $type): void
    {
        $state['types'] .= $type;
        $state['values'][] = $value;
    }

    private function resolveUserId($value, array $state): int
    {
        $text = strtolower(trim((string)$value));
        if($value === null || $text === '' || $text === 'current'){
            $userId = intval($state['context']['userId'] ?? 0);
            if($userId < 1){
                throw new QueryValidationException('Current-user query requires authentication');
            }
            return $userId;
        }
        if(!ctype_digit($text) || intval($text)<1){
            throw new QueryValidationException('User ID must be a positive integer or current');
        }
        return intval($text);
    }

    private function escapeLike(string $value): string
    {
        return str_replace('\\', '\\\\', $value);
    }

    /** Preserve legacy % and _ wildcards and add contains wildcards when absent. */
    private function likePattern(string $value): string
    {
        $value = $this->escapeLike($value);
        return strpos($value, '%') === false && strpos($value, '_') === false
            ? '%'.$value.'%'
            : $value;
    }

    /** Read the existing definition table directly; one lookup per field per request. */
    private function fieldType(int $fieldId): ?string
    {
        if(array_key_exists($fieldId, $this->fieldTypeCache)){ return $this->fieldTypeCache[$fieldId]; }
        if(!$this->mysqli){ return $this->fieldTypeCache[$fieldId] = null; }
        $result = $this->mysqli->query('SELECT dty_Type FROM defDetailTypes WHERE dty_ID='.$fieldId.' LIMIT 1');
        if(!$result){ throw new SearchExecutionException('Unable to read field type: '.$this->mysqli->error); }
        $row = $result->fetch_row();
        $result->close();
        return $this->fieldTypeCache[$fieldId] = ($row ? (string)$row[0] : null);
    }

    /** Numeric exact/comparison and legacy low<>high range. */
    private function numericCondition(string $column, $value, array &$state): string
    {
        $text = trim((string)$value);
        if(($range = $this->splitRange($text, '<>')) !== null){
            if(!is_numeric($range[0]) || !is_numeric($range[1])){
                throw new QueryValidationException('Numeric range requires two numeric values');
            }
            $this->bind($state, (float)$range[0], 'd'); $this->bind($state, (float)$range[1], 'd');
            return $column.' BETWEEN ? AND ?';
        }
        list($operator, $cleanValue) = $this->comparison($text);
        if(!is_numeric($cleanValue)){ throw new QueryValidationException('Numeric field requires a numeric value'); }
        if($operator === 'LIKE'){ $operator = '='; }
        if($operator === 'NOT LIKE'){ $operator = '<>'; }
        $this->bind($state, (float)$cleanValue, 'd');
        return $column.' '.$operator.' ?';
    }

    /** Enum IDs include descendants unless an exact (=) match was requested. */
    private function termCondition(string $column, $value, array &$state, ?string $termField = null): string
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
            $this->bind($state, $text, 's');
            $condition = $column.' IN (SELECT trm_ID FROM defTerms '
                .'WHERE CONCAT(trm_OriginatingDBID,"-",trm_IDInOriginatingDB)=?)';
            return $negate ? 'NOT ('.$condition.')' : $condition;
        }
        $termColumns = array('term'=>'trm_Label','label'=>'trm_Label','desc'=>'trm_Description','code'=>'trm_Code');
        $termColumn = $termColumns[$termField] ?? 'trm_Label';
        $this->bind($state, $exact ? $text : $this->likePattern($text), 's');
        $comparison = $caseSensitive ? 'BINARY '.$termColumn.' = BINARY ?'
            : ($exact ? $termColumn.'=?' : $termColumn.' LIKE ? ESCAPE "\\\\"');
        $subqueries = array('SELECT trm_ID FROM defTerms WHERE '.$comparison);
        if($termField === 'label' && $language !== null){
            $this->bind($state, 'trm_Label', 's');
            $translation = 'SELECT CAST(trn_Code AS UNSIGNED) FROM defTranslations WHERE trn_Source=?';
            if($language !== 'all'){
                $this->bind($state, $language, 's'); $translation .= ' AND trn_LanguageCode=?';
            }
            $this->bind($state, $exact ? $text : $this->likePattern($text), 's');
            $translation .= ' AND '.($exact ? 'trn_Translation=?' : 'trn_Translation LIKE ? ESCAPE "\\\\"');
            $subqueries[] = $translation;
        }elseif($termField === 'label'){
            $this->bind($state, $text, 's');
            $subqueries[0] .= ' OR trm_Code=?';
        }
        $condition = $column.' IN ('.implode(' UNION ', $subqueries).')';
        return $negate ? 'NOT ('.$condition.')' : $condition;
    }

    /** Load all descendant term IDs through the existing closure table. */
    private function termChildrenAll(array $parentIds): array
    {
        if(!$this->mysqli || empty($parentIds)){ return array(); }
        $found = array(); $pending = array_values(array_unique(array_map('intval', $parentIds)));
        while(!empty($pending)){
            $result = $this->mysqli->query(
                'SELECT trl_TermID FROM defTermsLinks WHERE trl_ParentID IN ('.implode(',', $pending).')'
            );
            if(!$result){ throw new SearchExecutionException('Unable to read term descendants: '.$this->mysqli->error); }
            $next = array();
            while($row = $result->fetch_row()){
                $id = intval($row[0]);
                if($id > 0 && !isset($found[$id])){ $found[$id] = true; $next[] = $id; }
            }
            $result->close(); $pending = $next;
        }
        foreach($parentIds as $id){ unset($found[intval($id)]); }
        return array_map('intval', array_keys($found));
    }

    /** Header dates use ISO values and legacy slash/<> BETWEEN syntax. */
    private function headerDateCondition(string $column, $value, array &$state): string
    {
        $text = trim((string)$value);
        $operator = null;
        foreach(array('>=','<=','>','<','=') as $candidate){
            if(strpos($text, $candidate) === 0){ $operator = $candidate; $text = trim(substr($text, strlen($candidate))); break; }
        }
        $range = $this->splitRange($text, '<>') ?? $this->splitRange($text, '/');
        if($range !== null){
            $from = \Temporal::dateToISO($range[0]); $to = \Temporal::dateToISO($range[1]);
            if($from === null || $to === null){ throw new QueryValidationException('Invalid date range'); }
            $this->bind($state, $from, 's'); $this->bind($state, $to, 's');
            return $column.' BETWEEN ? AND ?';
        }
        $iso = \Temporal::dateToISO($text);
        if($iso === null){ throw new QueryValidationException('Invalid date value: '.$text); }
        if($operator !== null){ $this->bind($state, $iso, 's'); return $column.' '.$operator.' ?'; }
        $pattern = preg_match('/^\d{4}[-\/]\d{2}$/', $text) ? str_replace('/', '-', $text).'%' : $iso.'%';
        $this->bind($state, $pattern, 's');
        return $column.' LIKE ?';
    }

    /** Detail dates are compared through recDetailsDateIndex estimated bounds. */
    private function detailDateCondition($value, array &$state): string
    {
        $text = trim((string)$value); $operator = null;
        foreach(array('>=','<=','>','<','=') as $candidate){
            if(strpos($text, $candidate) === 0){ $operator = $candidate; $text = trim(substr($text, strlen($candidate))); break; }
        }
        $within = strpos($text, '><') !== false;
        $temporal = new \Temporal($text, !$within);
        if(!$temporal->isValid()){ throw new QueryValidationException('Invalid temporal value: '.$text); }
        $timespan = $temporal->getMinMax();
        $min = (float)$timespan[0]; $max = (float)$timespan[1];
        if($within){
            $this->bind($state, $min, 'd'); $this->bind($state, $max, 'd');
            return '? <= di.rdi_estMinDate AND di.rdi_estMaxDate <= ?';
        }
        if($operator === '='){
            $this->bind($state, $min, 'd'); $this->bind($state, $max, 'd');
            return '(di.rdi_estMinDate = ? OR di.rdi_estMaxDate = ?)';
        }
        if($operator === '<' || $operator === '<='){
            $this->bind($state, $max, 'd'); return 'di.rdi_estMaxDate '.$operator.' ?';
        }
        if($operator === '>' || $operator === '>='){
            $this->bind($state, $min, 'd'); return 'di.rdi_estMinDate '.$operator.' ?';
        }
        $this->bind($state, $min, 'd'); $this->bind($state, $max, 'd');
        return 'di.rdi_estMaxDate >= ? AND di.rdi_estMinDate <= ?';
    }

    private function splitRange(string $value, string $separator): ?array
    {
        $position = strpos($value, $separator);
        if($position === false){ return null; }
        $left = trim(substr($value, 0, $position));
        $right = trim(substr($value, $position + strlen($separator)));
        return $left !== '' && $right !== '' ? array($left, $right) : null;
    }

    /** @, @+ and @- mean any word, all words and no words respectively. */
    private function fulltextCondition(string $column, string $value, array &$state): string
    {
        $mode = substr($value, 0, 2); $text = trim(substr($value, 1));
        if($mode === '@+' || $mode === '@-'){ $text = trim(substr($value, 2)); }
        if($text === ''){ throw new QueryValidationException('Full-text search value cannot be empty'); }
        if($mode === '@+'){
            $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
            $text = implode(' ', array_map(static function($word){ return '+'.$word; }, $words));
            $this->bind($state, $text, 's');
            return 'MATCH('.$column.') AGAINST (? IN BOOLEAN MODE)';
        }
        $this->bind($state, $text, 's');
        $condition = 'MATCH('.$column.') AGAINST (?)';
        return $mode === '@-' ? 'NOT ('.$condition.')' : $condition;
    }

    private function isAssociative(array $value): bool
    {
        if(empty($value)){ return false; }
        return array_keys($value) !== range(0, count($value)-1);
    }

    private function isScalarValueList(array $value): bool
    {
        if(!empty($value) && $this->isAssociative($value)){ return false; }
        foreach($value as $item){ if(is_array($item)){ return false; } }
        return true;
    }

    private function extractTextSubqueries(string $query): string
    {
        while(strpos($query, ')') !== false){
            $close = strpos($query, ')');
            $open = strrpos(substr($query, 0, $close), '(');
            if($open === false){
                throw new QueryValidationException('Unmatched closing parenthesis');
            }
            $inner = substr($query, $open+1, $close-$open-1);
            $parsedInner = $this->parsePlainText($inner);
            $index = count($this->textSubqueries);
            $this->textSubqueries[$index] = $parsedInner;
            $query = substr($query, 0, $open).' __SUBQUERY_'.$index.'__ '.substr($query, $close+1);
        }
        if(strpos($query, '(') !== false){
            throw new QueryValidationException('Unmatched opening parenthesis');
        }
        return $query;
    }

    private function tokenize(string $query): array
    {
        preg_match_all('/(?:[^\s"]+|"[^"]*")+/u', $query, $matches);
        return $matches[0] ?? array();
    }

    private function looksLikeKeywordToken(string $token): bool
    {
        list($rawKeyword) = $this->splitKeywordValue($token);
        list($keyword) = $this->plainKeyword($rawKeyword);
        return $keyword !== null;
    }

    private function splitKeywordValue(string $token): array
    {
        if(preg_match('/^([a-z_]+):([0-9]+):(.*)$/i', $token, $matches)){
            return array($matches[1].$matches[2], $matches[3], true);
        }
        $position = strpos($token, ':');
        if($position === false){ return array($token, null, false); }
        return array(substr($token, 0, $position), substr($token, $position+1), true);
    }

    private function plainKeyword(string $raw): array
    {
        $raw = strtolower(trim($raw));
        if(preg_match('/^([a-z_]+?)([0-9]+)$/', $raw, $matches)){
            $base = $matches[1]; $suffix = $matches[2];
        }else{
            $base = $raw; $suffix = '';
        }
        if(!isset(self::KEYWORD_ALIASES[$base])){ return array(null, ''); }
        return array(self::KEYWORD_ALIASES[$base], $suffix);
    }

    private function resolveTextValue($value)
    {
        $value = $this->unquote((string)$value);
        if(preg_match('/^__SUBQUERY_([0-9]+)__$/', $value, $matches)){
            $index = intval($matches[1]);
            if(!isset($this->textSubqueries[$index])){
                throw new QueryValidationException('Invalid subquery placeholder');
            }
            return $this->textSubqueries[$index];
        }
        return $value;
    }

    private function unquote(string $value): string
    {
        $value = trim($value);
        if(strlen($value)>=2 && $value[0] === '"' && $value[strlen($value)-1] === '"'){
            return substr($value, 1, -1);
        }
        return $value;
    }
}
