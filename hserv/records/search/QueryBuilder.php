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

/**
 * Normalizes Heurist query syntax and compiles flat searches to parameterized SQL.
 */
final class QueryBuilder
{
    private const DEFAULT_LIMIT = 1000000;
    private const MAX_LIMIT = 100000;
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
            if(in_array($base, array('f','field','fc','count','cnt'), true)
                && ($suffix === '' || !ctype_digit($suffix) || intval($suffix)<1)){
                throw new QueryValidationException('Predicate '.$key.' requires a numeric field ID');
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
            }elseif(is_array($value) && !in_array($base, array('t','type','typeid','id','ids','sortby','sort','s'), true)){
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
                && !in_array($base, array('t','type','typeid','id','ids','sortby','sort','s'), true)){
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
                $ids = $this->numericList($value, 'record type');
                return $this->inCondition($r.'.rec_RecTypeID', $ids, $state);
            case 'id': case 'ids':
                if(is_array($value) && empty($value)){ return '0=1'; }
                $ids = $this->numericList($value, 'record');
                return $this->inCondition($r.'.rec_ID', $ids, $state);
            case '_all':
                return '1=1';
            case 'title':
                return $this->textCondition($r.'.rec_Title', $value, $state);
            case 'url':
                return $this->textCondition($r.'.rec_URL', $value, $state);
            case 'notes':
                return $this->textCondition($r.'.rec_ScratchPad', $value, $state);
            case 'added':
                return $this->scalarCondition($r.'.rec_Added', $value, $state);
            case 'modified':
                return $this->scalarCondition($r.'.rec_Modified', $value, $state);
            case 'before':
                return $this->forcedComparison($r.'.rec_Modified', '<', $value, $state);
            case 'after': case 'since':
                return $this->forcedComparison($r.'.rec_Modified', '>', $value, $state);
            case 'addedby':
                return $this->integerCondition($r.'.rec_AddedByUGrpID', $value, $state);
            case 'owner': case 'workgroup': case 'wg':
                return $this->integerCondition($r.'.rec_OwnerUGrpID', $value, $state);
            case 'access':
                return $this->scalarCondition($r.'.rec_NonOwnerVisibility', $value, $state);
            case 'f': case 'field':
                return $this->fieldCondition(intval($suffix), $value, $state, $r);
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
                $this->bind($state, '%'.$this->escapeLike((string)$value).'%', 's');
                return 'EXISTS (SELECT 1 FROM usrRecTagLinks rtl INNER JOIN usrTags ut ON ut.tag_ID=rtl.rtl_TagID '
                    .'WHERE rtl.rtl_RecID='.$r.'.rec_ID AND ut.tag_Text LIKE ? ESCAPE "\\\\")';
            case 'lt': case 'linked_to': case 'linkedto':
                return $this->compileResourceLink($r, 'to', $suffix, $value, $state, $depth+1);
            case 'lf': case 'linked_from': case 'linkedfrom':
                return $this->compileResourceLink($r, 'from', $suffix, $value, $state, $depth+1);
            case 'rt': case 'related_to': case 'relatedto':
                return $this->compileRelationship($r, 'to', $value, $state, $depth+1);
            case 'rf': case 'related_from': case 'relatedfrom':
                return $this->compileRelationship($r, 'from', $value, $state, $depth+1);
            case 'file':
                throw new UnsupportedQueryException('File predicates are deferred to a later compatibility step');
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
                case 'f': case 'field':
                    if($suffix === '' || !ctype_digit($suffix)){
                        throw new QueryValidationException('Field sort requires a numeric field ID');
                    }
                    $this->bind($state, intval($suffix), 'i');
                    $expressions[] = '(SELECT MIN(sd.dtl_Value) FROM recDetails sd '
                        .'WHERE sd.dtl_RecID=r.rec_ID AND sd.dtl_DetailTypeID=?) '.$direction;
                    break;
                case 'set': case 'fixed':
                    throw new UnsupportedQueryException('Fixed-set sorting is deferred to Phase 2');
                default:
                    throw new QueryValidationException('Unknown sort expression: '.$sort);
            }
        }
        $expressions[] = 'r.rec_ID ASC';
        return ' ORDER BY '.implode(', ', array_values(array_unique($expressions)));
    }

    private function fieldCondition(int $fieldId, $value, array &$state, string $recordAlias = 'r'): string
    {
        if($fieldId < 1){ throw new QueryValidationException('Field ID must be positive'); }
        if($value === null || strtoupper(trim((string)$value)) === 'NULL'){
            $this->bind($state, $fieldId, 'i');
            return 'NOT EXISTS (SELECT 1 FROM recDetails d WHERE d.dtl_RecID='.$recordAlias.'.rec_ID AND d.dtl_DetailTypeID=?)';
        }
        $this->bind($state, $fieldId, 'i');
        if((string)$value === ''){
            return 'EXISTS (SELECT 1 FROM recDetails d WHERE d.dtl_RecID='.$recordAlias.'.rec_ID AND d.dtl_DetailTypeID=?)';
        }
        $condition = $this->scalarCondition('d.dtl_Value', $value, $state);
        return 'EXISTS (SELECT 1 FROM recDetails d WHERE d.dtl_RecID='.$recordAlias.'.rec_ID '
            .'AND d.dtl_DetailTypeID=? AND '.$condition.')';
    }

    private function fieldCountCondition(int $fieldId, $value, array &$state, string $recordAlias = 'r'): string
    {
        $this->bind($state, $fieldId, 'i');
        list($operator, $cleanValue) = $this->comparison((string)$value);
        $count = intval($cleanValue);
        $this->bind($state, $count, 'i');
        return '(SELECT COUNT(*) FROM recDetails dc WHERE dc.dtl_RecID='.$recordAlias.'.rec_ID '
            .'AND dc.dtl_DetailTypeID=?) '.$operator.' ?';
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
        $text = trim((string)$value);
        if($text === ''){
            return 'EXISTS (SELECT 1 FROM recDetails gd WHERE gd.dtl_RecID='.$recordAlias.'.rec_ID '
                .'AND gd.dtl_Geo IS NOT NULL'.$fieldSql.')';
        }
        $this->bind($state, $text, 's');
        return 'EXISTS (SELECT 1 FROM recDetails gd WHERE gd.dtl_RecID='.$recordAlias.'.rec_ID '
            .'AND gd.dtl_Geo IS NOT NULL'.$fieldSql.' '
            .'AND MBRIntersects(gd.dtl_Geo,ST_GeomFromText(?)))';
    }

    private function textCondition(string $column, $value, array &$state): string
    {
        $text = (string)$value;
        if(strpos($text, '=') === 0){
            return $this->forcedComparison($column, '=', substr($text, 1), $state);
        }
        if(strpos($text, '-') === 0){
            $this->bind($state, '%'.$this->escapeLike(substr($text, 1)).'%', 's');
            return $column.' NOT LIKE ? ESCAPE "\\\\"';
        }
        $this->bind($state, '%'.$this->escapeLike($text).'%', 's');
        return $column.' LIKE ? ESCAPE "\\\\"';
    }

    private function scalarCondition(string $column, $value, array &$state): string
    {
        list($operator, $cleanValue) = $this->comparison((string)$value);
        if($operator === 'LIKE' || $operator === 'NOT LIKE'){
            $this->bind($state, '%'.$this->escapeLike($cleanValue).'%', 's');
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
        return str_replace(array('\\','%','_'), array('\\\\','\\%','\\_'), $value);
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
