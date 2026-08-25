<?php
/**
* RecordSearchService.php - Modern IDs-only record search service
*
 * Coordinates QueryBuilder and QueryExecutor. SQL-compilable linked predicates
 * are delegated to MariaDB as correlated EXISTS expressions, preserving exact
 * count, sort, offset, and limit in SQL. The ordered set evaluator remains as a
 * compatibility fallback for metadata-dependent relationship predicates.
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

require_once dirname(__FILE__).'/query/QueryBuilder.php';
require_once dirname(__FILE__).'/QueryExecutor.php';

use hserv\records\search\query\QueryBuilder;

/** Executes flat, logical, resource-link, and relationship record searches. */
final class RecordSearchService
{
    private const SQL_CHUNK_SIZE = 500;
    private const MAX_PRECOMPUTED_CANDIDATES = 5000;
    private const MAX_QUERY_DEPTH = 20;
    private const RESOURCE_TO = array('lt','linked_to','linkedto');
    private const RESOURCE_FROM = array('lf','linked_from','linkedfrom');
    private const RELATION_TO = array('rt','related_to','relatedto');
    private const RELATION_FROM = array('rf','related_from','relatedfrom');

    /** @var \hserv\System */
    private $system;
    /** @var QueryBuilder */
    private $builder;
    /** @var QueryExecutor */
    private $executor;
    /** @var array<int,array{types:array,recordTypes:array}> */
    private $relationMarkerCache = array();

    /** @param \hserv\System $system Initialised Heurist system. */
    public function __construct($system, ?QueryBuilder $builder = null, ?QueryExecutor $executor = null)
    {
        $this->system = $system;
        $this->executor = $executor ?? new QueryExecutor($system->getMysqli());
        $this->builder = $builder ?? new QueryBuilder($system->getMysqli());
    }

    /** Execute a search and always return the requested page and full count. */
    public function search(SearchRequest $request, array $context = array()): SearchResult
    {
        $query = $this->builder->normalize($request->query);
        $context = $this->searchContext($request, $context);
        $candidateCache = array();
        $query = $this->resolveSelectiveAnyFields($query, $context, $candidateCache);
        if(empty($context['forceChunked']) && $this->builder->supportsSqlExecution($query)){
            $ids = $this->executor->executeIds($this->builder->buildIds($query, $context));
            $total = intval($this->executor->executeScalar($this->builder->buildCount($query, $context)));
            return new SearchResult($ids, $total, $request->offset, $request->limit);
        }
        $ids = $this->evaluateGroup($query, null, 'all', $context, 0);
        return new SearchResult(
            array_slice($ids, $request->offset, $request->limit),
            count($ids),
            $request->offset,
            $request->limit
        );
    }

    /**
     * Resolve selective any-field predicates before composing the main query.
     * Probes returning more than the threshold remain unchanged and use the
     * normal inline SQL path.
     */
    private function resolveSelectiveAnyFields(array $group, array $context, array &$cache): array
    {
        $group = $this->normalizeGroup($group);
        $result = array();
        foreach($group as $predicate){
            $key = (string)array_keys($predicate)[0];
            $value = $predicate[$key];
            list($base, $suffix) = $this->predicateParts($key);

            if(($base === 'f' || $base === 'field') && $suffix === '' && !is_array($value)){
                $cacheKey = gettype($value).':'.(string)$value;
                if(!array_key_exists($cacheKey, $cache)){
                    $probe = $this->builder->buildAnyFieldCandidates(
                        $value,
                        $context,
                        self::MAX_PRECOMPUTED_CANDIDATES+1
                    );
                    $ids = $this->executor->executeIds($probe);
                    $cache[$cacheKey] = count($ids)>self::MAX_PRECOMPUTED_CANDIDATES ? null : $ids;
                }
                if($cache[$cacheKey] !== null){
                    $idsPredicate = array('ids'=>$cache[$cacheKey]);
                    $result[] = strpos((string)$value, '@-') === 0
                        ? array('not'=>array($idsPredicate))
                        : $idsPredicate;
                    continue;
                }
            }elseif(($base === 'f' || $base === 'field')
                && ctype_digit($suffix) && intval($suffix)>0
                && !is_array($value) && !$this->isFieldPresenceValue($value)){
                $fieldId = intval($suffix);
                $cacheKey = 'numeric:'.$fieldId.':'.gettype($value).':'.(string)$value;
                if(!array_key_exists($cacheKey, $cache)){
                    $probe = $this->builder->buildNumericFieldCandidates(
                        $fieldId,
                        $value,
                        $context,
                        self::MAX_PRECOMPUTED_CANDIDATES+1
                    );
                    if($probe === null){
                        $cache[$cacheKey] = false;
                    }else{
                        $ids = $this->executor->executeIds($probe);
                        $cache[$cacheKey] = count($ids)>self::MAX_PRECOMPUTED_CANDIDATES ? null : $ids;
                    }
                }
                if(is_array($cache[$cacheKey])){
                    $result[] = array('ids'=>$cache[$cacheKey]);
                    continue;
                }
            }

            if(in_array($base, array('all','any','not'), true) && is_array($value)){
                $predicate[$key] = $this->resolveSelectiveAnyFields($value, $context, $cache);
            }elseif($this->isNestedQueryPredicate($base, $value)){
                $predicate[$key] = $this->resolveSelectiveAnyFields($value, $context, $cache);
            }
            $result[] = $predicate;
        }
        return $result;
    }

    /** NULL, -NULL, and empty values retain their established field semantics. */
    private function isFieldPresenceValue($value): bool
    {
        $text = strtoupper(trim((string)$value));
        return $text === '' || $text === 'NULL' || $text === '-NULL';
    }

    /** Whether a link/relationship value is a nested query rather than an ID list. */
    private function isNestedQueryPredicate(string $base, $value): bool
    {
        if(!is_array($value) || $this->isIdList($value)){ return false; }
        return in_array($base, array(
            'lt','linked_to','linkedto','lf','linked_from','linkedfrom',
            'rt','related_to','relatedto','rf','related_from','relatedfrom','related'
        ), true);
    }

    /** Evaluate a JSON query group as ordered set algebra. */
    private function evaluateGroup(
        array $group,
        ?array $candidateIds,
        string $logic,
        array $context,
        int $depth
    ): array {
        if($depth > self::MAX_QUERY_DEPTH){
            throw new QueryValidationException('Query nesting is too deep');
        }
        $group = $this->normalizeGroup($group);
        if($logic === 'any'){
            $universe = $candidateIds === null
                ? $this->executeFlatSet(array(array('_all'=>true)), null, $context)
                : $this->uniqueIds($candidateIds);
            $union = array();
            foreach($group as $predicate){
                if($this->isSortPredicate($predicate)){ continue; }
                foreach($this->evaluateGroup(array($predicate), $universe, 'all', $context, $depth+1) as $id){
                    $union[$id] = true;
                }
            }
            return $this->orderedSubset($universe, $union);
        }
        if($logic === 'not'){
            $universe = $candidateIds === null
                ? $this->executeFlatSet(array(array('_all'=>true)), null, $context)
                : $this->uniqueIds($candidateIds);
            $excluded = array_fill_keys(
                $this->evaluateGroup($group, $universe, 'all', $context, $depth+1),
                true
            );
            return array_values(array_filter($universe, static function($id) use ($excluded){
                return !isset($excluded[$id]);
            }));
        }

        $flat = array(); $complex = array();
        foreach($group as $predicate){
            if($this->isComplexPredicate($predicate)){ $complex[] = $predicate; }
            else{ $flat[] = $predicate; }
        }
        if(!empty($flat)){
            $current = $this->executeFlatSet($flat, $candidateIds, $context);
        }elseif($candidateIds === null){
            $current = $this->executeFlatSet(array(array('_all'=>true)), null, $context);
        }else{
            $current = $this->uniqueIds($candidateIds);
        }

        foreach($complex as $predicate){
            if(empty($current)){ break; }
            $key = (string)array_keys($predicate)[0];
            $value = $predicate[$key];
            list($base, $suffix) = $this->predicateParts($key);
            if($base === 'all' || $base === 'any' || $base === 'not'){
                $current = $this->evaluateGroup(
                    $this->normalizeGroup($value), $current, $base, $context, $depth+1
                );
            }elseif(in_array($base, self::RESOURCE_TO, true)){
                $current = $this->filterByResourceLink($current, 'to', $suffix, $value, $context, $depth+1);
            }elseif(in_array($base, self::RESOURCE_FROM, true)){
                $current = $this->filterByResourceLink($current, 'from', $suffix, $value, $context, $depth+1);
            }elseif(in_array($base, self::RELATION_TO, true)){
                $current = $this->filterByRelationship($current, 'to', $suffix, $value, $context, $depth+1);
            }elseif(in_array($base, self::RELATION_FROM, true)){
                $current = $this->filterByRelationship($current, 'from', $suffix, $value, $context, $depth+1);
            }elseif($base === 'related'){
                $current = $this->filterByRelationship($current, 'both', $suffix, $value, $context, $depth+1);
            }else{
                throw new UnsupportedQueryException('Predicate is not supported by Phase 3: '.$key);
            }
        }
        return $current;
    }

    /** Run a flat query, optionally restricted to an ordered candidate set. */
    private function executeFlatSet(array $query, ?array $candidateIds, array $context): array
    {
        if($candidateIds === null){
            return $this->executor->executeIds($this->builder->buildIdSet($query, $context));
        }
        $candidateIds = $this->uniqueIds($candidateIds);
        if(empty($candidateIds)){ return array(); }
        $matched = array();
        foreach(array_chunk($candidateIds, self::SQL_CHUNK_SIZE) as $chunk){
            $chunkQuery = $query;
            $chunkQuery[] = array('ids'=>$chunk);
            foreach($this->executor->executeIds($this->builder->buildIdSet($chunkQuery, $context)) as $id){
                $matched[$id] = true;
            }
        }
        return $this->orderedSubset($candidateIds, $matched);
    }

    /** Traverse direct resource links and retain parents with matching children. */
    private function filterByResourceLink(
        array $parents,
        string $direction,
        string $fieldSuffix,
        $value,
        array $context,
        int $depth
    ): array {
        $fieldId = $this->positiveSuffix($fieldSuffix, 'Resource-link field ID');
        $edges = $this->loadResourceEdges($parents, $direction, $fieldId);
        if(empty($edges)){ return array(); }
        $childQuery = $this->normaliseLinkedValue($value);
        array_unshift($childQuery, array('_all'=>true));
        $matchingChildren = $this->evaluateGroup(
            $childQuery,
            $this->uniqueIds(array_column($edges, 1)),
            'all', $context, $depth
        );
        return $this->parentsForMatchingEdges($parents, $edges, $matchingChildren);
    }

    /** Return [parent ID, child ID] direct-resource edges. */
    private function loadResourceEdges(array $parentIds, string $direction, ?int $fieldId): array
    {
        $edges = array();
        foreach(array_chunk($this->uniqueIds($parentIds), self::SQL_CHUNK_SIZE) as $chunk){
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $types = str_repeat('i', count($chunk)); $values = $chunk;
            $linkSql = ' AND rl.rl_RelationID IS NULL';
            if($fieldId !== null){
                $linkSql .= ' AND rl.rl_DetailTypeID=?';
                $types .= 'i'; $values[] = $fieldId;
            }else{
                $linkSql .= ' AND rl.rl_DetailTypeID>0';
            }
            if($direction === 'to'){
                $sql = 'SELECT DISTINCT rl.rl_SourceID,rl.rl_TargetID FROM recLinks rl'
                    .' WHERE rl.rl_SourceID IN ('.$placeholders.')'.$linkSql;
            }else{
                $sql = 'SELECT DISTINCT rl.rl_TargetID,rl.rl_SourceID FROM recLinks rl'
                    .' WHERE rl.rl_TargetID IN ('.$placeholders.')'.$linkSql;
            }
            foreach($this->executor->executeRows($sql, $types, $values) as $row){
                $parent = intval($row[0]); $child = intval($row[1]);
                if($parent>0 && $child>0){ $edges[] = array($parent, $child); }
            }
        }
        return $edges;
    }

    /** Traverse relationship edges, endpoint queries, type filters, and Relationship records. */
    private function filterByRelationship(
        array $parents,
        string $direction,
        string $suffix,
        $value,
        array $context,
        int $depth
    ): array {
        $markerFieldId = null; $suffixRelationTypes = null;
        if($direction === 'both'){
            if($suffix !== ''){ $suffixRelationTypes = $this->normalizeIds($suffix, 'relationship type'); }
        }else{
            $markerFieldId = $this->positiveSuffix($suffix, 'Relation-marker field ID');
        }
        list($childQuery, $relationshipQuery, $explicitTypes) = $this->splitRelationshipValue($value);
        $requestedTypes = $explicitTypes === null ? null : $this->expandTermIds($explicitTypes);
        if($suffixRelationTypes !== null){
            $suffixTypes = $this->expandTermIds($suffixRelationTypes);
            $requestedTypes = $requestedTypes === null
                ? $suffixTypes
                : array_values(array_intersect($requestedTypes, $suffixTypes));
        }

        $markerTypes = null; $markerRecordTypes = array();
        if($markerFieldId !== null){
            $marker = $this->relationMarkerConstraints($markerFieldId);
            $markerTypes = $marker['types'];
            $markerRecordTypes = $marker['recordTypes'];
        }
        $directTypes = $requestedTypes === null ? $markerTypes : $requestedTypes;
        if($requestedTypes !== null && $markerTypes !== null){
            $directTypes = array_values(array_intersect($directTypes, $markerTypes));
        }

        $edges = array();
        if($direction === 'to' || $direction === 'both'){
            $edges = $this->loadRelationshipEdges($parents, 'to', $directTypes);
        }
        if($direction === 'from'){
            $edges = $this->loadRelationshipEdges($parents, 'from', $directTypes);
        }elseif($direction === 'both'){
            $reverseTypes = $requestedTypes === null ? $markerTypes : $this->inverseTermIds($requestedTypes);
            if($requestedTypes === null || !empty($reverseTypes)){
                $edges = array_merge($edges, $this->loadRelationshipEdges($parents, 'from', $reverseTypes));
            }
        }
        $edges = $this->uniqueRelationshipEdges($edges);
        if(empty($edges)){ return array(); }

        $relationshipIds = $this->uniqueIds(array_column($edges, 2));
        array_unshift($relationshipQuery, array('_all'=>true));
        $matchingRelationships = $this->evaluateGroup(
            $relationshipQuery, $relationshipIds, 'all', $context, $depth
        );
        $relationshipSet = array_fill_keys($matchingRelationships, true);
        $edges = array_values(array_filter($edges, static function($edge) use ($relationshipSet){
            return isset($relationshipSet[$edge[2]]);
        }));
        if(empty($edges)){ return array(); }

        if(!empty($markerRecordTypes)){ $childQuery[] = array('t'=>$markerRecordTypes); }
        array_unshift($childQuery, array('_all'=>true));
        $matchingChildren = $this->evaluateGroup(
            $childQuery,
            $this->uniqueIds(array_column($edges, 1)),
            'all', $context, $depth
        );
        return $this->parentsForMatchingEdges($parents, $edges, $matchingChildren);
    }

    /** Return [parent, child, Relationship record, relationship type] edges. */
    private function loadRelationshipEdges(array $parentIds, string $direction, ?array $relationTypes): array
    {
        if(is_array($relationTypes) && empty($relationTypes)){ return array(); }
        $edges = array();
        foreach(array_chunk($this->uniqueIds($parentIds), self::SQL_CHUNK_SIZE) as $chunk){
            $parentPlaceholders = implode(',', array_fill(0, count($chunk), '?'));
            $types = str_repeat('i', count($chunk)); $values = $chunk; $typeSql = '';
            if(is_array($relationTypes)){
                $typeSql = ' AND rl.rl_RelationTypeID IN ('
                    .implode(',', array_fill(0, count($relationTypes), '?')).')';
                $types .= str_repeat('i', count($relationTypes));
                $values = array_merge($values, $relationTypes);
            }
            if($direction === 'to'){
                $sql = 'SELECT DISTINCT rl.rl_SourceID,rl.rl_TargetID,rl.rl_RelationID,rl.rl_RelationTypeID'
                    .' FROM recLinks rl WHERE rl.rl_SourceID IN ('.$parentPlaceholders.')'
                    .' AND rl.rl_RelationID IS NOT NULL'.$typeSql;
            }else{
                $sql = 'SELECT DISTINCT rl.rl_TargetID,rl.rl_SourceID,rl.rl_RelationID,rl.rl_RelationTypeID'
                    .' FROM recLinks rl WHERE rl.rl_TargetID IN ('.$parentPlaceholders.')'
                    .' AND rl.rl_RelationID IS NOT NULL'.$typeSql;
            }
            foreach($this->executor->executeRows($sql, $types, $values) as $row){
                $edge = array_map('intval', array_slice($row, 0, 4));
                if($edge[0]>0 && $edge[1]>0 && $edge[2]>0){ $edges[] = $edge; }
            }
        }
        return $edges;
    }

    /** Split endpoint predicates from r and relf predicates for Relationship records. */
    private function splitRelationshipValue($value): array
    {
        $query = $this->normaliseLinkedValue($value);
        $child = array(); $relationship = array(); $types = null;
        foreach($query as $predicate){
            $key = (string)array_keys($predicate)[0];
            $predicateValue = $predicate[$key];
            list($base, $suffix) = $this->predicateParts($key);
            if($base === 'r' && $suffix === ''){
                $currentTypes = $this->normalizeIds($predicateValue, 'relationship type');
                $types = $types === null ? $currentTypes : array_values(array_intersect($types, $currentTypes));
            }elseif($base === 'relf' || ($base === 'r' && $suffix !== '')){
                $fieldId = $this->positiveSuffix($suffix, 'Relationship-record field ID');
                $relationship[] = array('f:'.$fieldId=>$predicateValue);
            }else{
                if($this->containsRelationshipConstraint($predicateValue)){
                    throw new UnsupportedQueryException('r and relf must be top-level predicates inside a relationship query');
                }
                $child[] = $predicate;
            }
        }
        return array(
            empty($child) ? array(array('_all'=>true)) : $child,
            empty($relationship) ? array(array('_all'=>true)) : $relationship,
            $types
        );
    }

    /** Resolve relmarker vocabulary and endpoint record-type constraints. */
    private function relationMarkerConstraints(int $fieldId): array
    {
        if(isset($this->relationMarkerCache[$fieldId])){ return $this->relationMarkerCache[$fieldId]; }
        $rows = $this->executor->executeRows(
            'SELECT dty_JsonTermIDTree,dty_PtrTargetRectypeIDs FROM defDetailTypes WHERE dty_ID=?',
            'i', array($fieldId)
        );
        if(empty($rows)){ throw new QueryValidationException('Unknown relation-marker field ID: '.$fieldId); }
        $rootTypes = $this->idsFromText($rows[0][0] ?? '');
        $constraints = array(
            'types' => empty($rootTypes) ? null : $this->expandTermIds($rootTypes),
            'recordTypes' => $this->idsFromText($rows[0][1] ?? '')
        );
        $this->relationMarkerCache[$fieldId] = $constraints;
        return $constraints;
    }

    /** Include every descendant relationship term through defTermsLinks. */
    private function expandTermIds(array $termIds): array
    {
        $all = array_fill_keys($this->uniqueIds($termIds), true);
        $frontier = array_keys($all);
        while(!empty($frontier)){
            $next = array();
            foreach(array_chunk($frontier, self::SQL_CHUNK_SIZE) as $chunk){
                $sql = 'SELECT DISTINCT trl_TermID FROM defTermsLinks WHERE trl_ParentID IN ('
                    .implode(',', array_fill(0, count($chunk), '?')).')';
                foreach($this->executor->executeRows($sql, str_repeat('i', count($chunk)), $chunk) as $row){
                    $id = intval($row[0]);
                    if($id>0 && !isset($all[$id])){ $all[$id] = true; $next[] = $id; }
                }
            }
            $frontier = $next;
        }
        return array_map('intval', array_keys($all));
    }

    /** Resolve inverse relationship terms and all their descendants. */
    private function inverseTermIds(array $termIds): array
    {
        $inverse = array();
        foreach(array_chunk($this->uniqueIds($termIds), self::SQL_CHUNK_SIZE) as $chunk){
            $sql = 'SELECT DISTINCT trm_InverseTermID FROM defTerms WHERE trm_ID IN ('
                .implode(',', array_fill(0, count($chunk), '?')).')';
            foreach($this->executor->executeRows($sql, str_repeat('i', count($chunk)), $chunk) as $row){
                $id = intval($row[0]); if($id>0){ $inverse[] = $id; }
            }
        }
        return empty($inverse) ? array() : $this->expandTermIds($inverse);
    }

    private function normaliseLinkedValue($value): array
    {
        if(is_array($value) && !$this->isIdList($value)){ return $this->builder->normalize($value); }
        return array(array('ids'=>$this->normalizeIds($value, 'linked record')));
    }

    private function parentsForMatchingEdges(array $parents, array $edges, array $matchingChildren): array
    {
        $childSet = array_fill_keys($matchingChildren, true); $parentSet = array();
        foreach($edges as $edge){ if(isset($childSet[$edge[1]])){ $parentSet[$edge[0]] = true; } }
        return $this->orderedSubset($parents, $parentSet);
    }

    private function uniqueRelationshipEdges(array $edges): array
    {
        $result = array(); $seen = array();
        foreach($edges as $edge){
            $key = implode(':', $edge);
            if(!isset($seen[$key])){ $seen[$key] = true; $result[] = $edge; }
        }
        return $result;
    }

    private function isComplexPredicate(array $predicate): bool
    {
        $key = (string)array_keys($predicate)[0];
        list($base) = $this->predicateParts($key);
        return in_array($base, array_merge(
            array('all','any','not','related','r','relf','links'),
            self::RESOURCE_TO, self::RESOURCE_FROM, self::RELATION_TO, self::RELATION_FROM
        ), true);
    }

    private function isSortPredicate(array $predicate): bool
    {
        $key = (string)array_keys($predicate)[0];
        list($base) = $this->predicateParts($key);
        return in_array($base, array('sortby','sort','s'), true);
    }

    private function containsRelationshipConstraint($value): bool
    {
        if(!is_array($value)){ return false; }
        foreach($value as $key=>$item){
            $base = is_string($key) ? $this->predicateParts($key)[0] : '';
            if($base === 'r' || $base === 'relf' || $this->containsRelationshipConstraint($item)){ return true; }
        }
        return false;
    }

    private function normalizeGroup(array $group): array
    {
        if(empty($group)){ return array(); }
        if(array_keys($group) !== range(0, count($group)-1)){
            $result = array();
            foreach($group as $key=>$value){ $result[] = array((string)$key=>$value); }
            return $result;
        }
        return array_values($group);
    }

    private function predicateParts(string $key): array
    {
        $parts = explode(':', strtolower(trim($key)), 2);
        return array($parts[0], $parts[1] ?? '');
    }

    private function positiveSuffix(string $suffix, string $label): ?int
    {
        if($suffix === ''){ return null; }
        if(!ctype_digit($suffix) || intval($suffix)<1){
            throw new QueryValidationException($label.' must be a positive integer');
        }
        return intval($suffix);
    }

    private function normalizeIds($value, string $label): array
    {
        $values = is_array($value) ? $value : preg_split('/\s*,\s*/', trim((string)$value));
        foreach($values as $id){
            if(is_array($id) || !is_numeric($id) || intval($id)<1){
                throw new QueryValidationException('Invalid '.$label.' ID: '.(is_scalar($id) ? (string)$id : 'array'));
            }
        }
        return $this->uniqueIds($values);
    }

    private function idsFromText($value): array
    {
        preg_match_all('/(?<![0-9])[1-9][0-9]*(?![0-9])/', (string)$value, $matches);
        return $this->uniqueIds($matches[0] ?? array());
    }

    private function isIdList(array $values): bool
    {
        if(empty($values)){ return true; }
        if(array_keys($values) !== range(0, count($values)-1)){ return false; }
        foreach($values as $value){ if(is_array($value) || !is_numeric($value)){ return false; } }
        return true;
    }

    private function uniqueIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static function($id){
            return $id>0;
        })));
    }

    private function orderedSubset(array $orderedIds, array $set): array
    {
        return array_values(array_filter($orderedIds, static function($id) use ($set){
            return isset($set[$id]);
        }));
    }

    private function searchContext(SearchRequest $request, array $context): array
    {
        return array_merge(array(
            'userId' => $this->system->getUserId(),
            'groupIds' => $this->system->getUserGroupIds() ?? array(),
            'isDbOwner' => $this->system->isDbOwner(),
            'limit' => $request->limit,
            'offset' => $request->offset
        ), $context);
    }
}
