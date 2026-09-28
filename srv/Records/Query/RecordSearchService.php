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
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

namespace Heurist\Records\Query;

use Heurist\Database\DatabaseInterface;
use Heurist\Runtime\RuntimeContext;
use Heurist\Records\Query\Compiler\QueryBuilder;

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

    private RuntimeContext $runtime;
    /** @var QueryBuilder */
    private $builder;
    /** @var QueryExecutor */
    private $executor;
    /** @var array<int,array{types:array,recordTypes:array}> */
    private $relationMarkerCache = array();

    /** Initialise record search with explicit database and request context. */
    public function __construct(
        DatabaseInterface $database,
        RuntimeContext $runtime,
        ?QueryBuilder $builder = null,
        ?QueryExecutor $executor = null
    )
    {
        $this->runtime = $runtime;
        $this->executor = $executor ?? new QueryExecutor($database);
        $this->builder = $builder ?? new QueryBuilder($database);
    }

    /** Execute one graph traversal using the same access, marker and term
     * semantics as record search. Tree scheduling belongs to the graph client.
     * Candidate reads are capped as well as the final graph; report partial
     * results explicitly when a dense branch exhausts the candidate budget. */
    public function expandGraphStep(array $seeds, array $query, int $maxNodes, int $maxEdges): array
    {
        $anchor = null; $targetQuery = array();
        foreach($this->builder->normalize($query) as $predicate){
            $key = (string)array_keys($predicate)[0];
            list($base, $suffix) = $this->predicateParts($key);
            if(in_array($base, array('lt','lf','rt','rf','links','related','connected'), true)){
                if($anchor !== null){ throw new QueryValidationException('Exactly one traversal is required per expansion step'); }
                $anchor = array($base, $suffix, $predicate[$key]);
            }else{ $targetQuery[] = $predicate; }
        }
        if($anchor === null){ throw new QueryValidationException('Expansion rule has no traversal'); }
        list($base, $suffix, $value) = $anchor;
        $relation = in_array($base, array('rt','rf','related'), true);
        $directions = in_array($base, array('links','related','connected'), true) ? array('to','from')
            : array(in_array($base, array('lf','rf'), true) ? 'to' : 'from');
        // [relationship?, direction] edge reads; 'connected' reads pointers and relationships
        $passes = array();
        foreach($base === 'connected' ? array(false, true) : array($relation) as $isRelation){
            foreach($directions as $direction){ $passes[] = array($isRelation, $direction); }
        }
        $requestedTypes = null; $markerTypes = null; $relationshipQuery = array();
        if($relation){
            list($parentQuery, $relationshipQuery, $requestedTypes) = $this->splitRelationshipValue($value);
            if($requestedTypes !== null) $requestedTypes = $this->expandTermIds($requestedTypes);
            if($suffix !== ''){
                if($base === 'related'){
                    $types = $this->expandTermIds($this->normalizeIds($suffix, 'relationship type'));
                    $requestedTypes = $requestedTypes === null ? $types : array_values(array_intersect($types, $requestedTypes));
                }else{
                    $marker = $this->relationMarkerConstraints($this->positiveSuffix($suffix, 'Relation-marker field ID'));
                    $markerTypes = $marker['types'];
                    if(!empty($marker['recordTypes'])) $parentQuery[] = array('t'=>$marker['recordTypes']);
                }
            }
        }else{ $parentQuery = $this->normaliseLinkedValue($value); }
        $parentQuery[] = array('ids'=>$seeds);
        $parents = $this->search(new SearchRequest($parentQuery, array('limit'=>10000)))->ids;
        $rows = array(); $truncated = false;
        foreach($passes as list($isRelation, $direction)){
            $types = $requestedTypes;
            // 'related' is expressed from the target's perspective.
            if($base === 'related' && $direction === 'to' && $types !== null) $types = $this->inverseTermIds($types);
            if($markerTypes !== null) $types = $types === null ? $markerTypes : array_values(array_intersect($types, $markerTypes));
            if($isRelation && $types === array()) continue;
            foreach(array_chunk($parents, self::SQL_CHUNK_SIZE) as $chunk){
                $parentColumn = $direction === 'to' ? 'rl_SourceID' : 'rl_TargetID';
                $childColumn = $direction === 'to' ? 'rl_TargetID' : 'rl_SourceID';
                $values = $chunk;
                $conditions = array('rl.'.$parentColumn.' IN ('.implode(',', array_fill(0, count($chunk), '?')).')',
                    'rl.rl_RelationID IS '.($isRelation ? 'NOT NULL' : 'NULL'));
                if($isRelation && $types !== null){
                    $conditions[] = 'rl.rl_RelationTypeID IN ('.implode(',', array_fill(0, count($types), '?')).')';
                    $values = array_merge($values, $types);
                }elseif(!$isRelation && $suffix !== ''){
                    $conditions[] = 'rl.rl_DetailTypeID=?';
                    $values[] = $this->positiveSuffix($suffix, 'Resource-link field ID');
                }
                $remaining = 10001 - count($rows);
                if($remaining < 1){ $truncated = true; break 2; }
                $sql = 'SELECT DISTINCT rl.'.$parentColumn.',rl.'.$childColumn
                    .',rl.rl_SourceID,rl.rl_TargetID,COALESCE(rl.rl_DetailTypeID,0),'
                    .'COALESCE(rl.rl_RelationTypeID,0),COALESCE(rl.rl_RelationID,0) FROM recLinks rl WHERE '
                    .implode(' AND ', $conditions).' ORDER BY rl.'.$parentColumn.',rl.'.$childColumn.' LIMIT '.$remaining;
                $rows = array_merge($rows, $this->executor->executeRows($sql, str_repeat('i', count($values)), $values));
            }
        }
        if(count($rows)>10000){ $truncated = true; $rows = array_slice($rows, 0, 10000); }
        $candidateIds = $this->uniqueIds(array_column($rows, 1));
        if(empty($candidateIds)) return array('targetIds'=>array(), 'edges'=>array(), 'truncated'=>$truncated);
        $targetQuery[] = array('ids'=>$candidateIds);
        $targets = $this->search(new SearchRequest($targetQuery, array('limit'=>10000)))->ids;
        $allowed = array_fill_keys($targets, true);
        $relationships = array();
        $relationshipIds = $this->uniqueIds(array_column($rows, 6));
        if(!empty($relationshipIds)){
            // Relationship records are subject to the same access rules as endpoints
            $relationshipQuery[] = array('ids'=>$relationshipIds);
            $relationships = array_fill_keys($this->search(new SearchRequest($relationshipQuery, array('limit'=>10000)))->ids, true);
        }
        $nodes = array_fill_keys($seeds, true); $edges = array(); $returned = array();
        foreach($rows as $row){
            list($parent, $child, $source, $target, $field, $term, $relationId) = array_map('intval', $row);
            if(!isset($allowed[$child]) || ($relationId > 0 && !isset($relationships[$relationId]))) continue;
            $id = $source.':'.$target.':'.$field.':'.$term;
            if(isset($edges[$id])) continue;
            if((!isset($nodes[$child]) && count($nodes)>=$maxNodes) || count($edges)>=$maxEdges){ $truncated = true; continue; }
            $nodes[$child] = true; $returned[$child] = $child;
            $edges[$id] = array('id'=>$id, 'source'=>$source, 'target'=>$target, 'field'=>$field ?: null, 'relationship'=>$term ?: null,
                'link'=>null, 'path'=>null);
        }
        return array('targetIds'=>array_values($returned), 'edges'=>array_values($edges), 'truncated'=>$truncated);
    }

    /**
     * detail=ranges: record counts per range of one numeric or date field over
     * the query's records (see FieldValueBuckets).
     *
     * @return array{field:string,type:string,match:string,total:int,truncated:bool,buckets:array}
     */
    public function valueBuckets(SearchRequest $request): array
    {
        $buckets = new FieldValueBuckets($this->builder, $this->executor);
        $field = $buckets->describe($request->valueField, $request->valueRanges);
        $query = $this->builder->normalize($request->query);
        if($request->filter !== null && $request->filter !== '' && $request->filter !== array()){
            $query[] = array('all'=>$this->builder->normalize($request->filter));
        }
        $context = $this->searchContext($request, array());
        $candidateCache = array();
        $query = $this->resolveSelectiveAnyFields($query, $context, $candidateCache);
        $result = $this->builder->supportsSqlExecution($query)
            ? $buckets->bucketsForQuery($query, $context, $field)
            : $buckets->bucketsForIds($this->evaluateGroup($query, null, 'all', $context, 0), $context, $field);
        $grouping = $field['type'] === 'date' ? array('groupby'=>$field['groupby']) : array('ranges'=>$field['ranges']);
        return array_merge(array('field'=>$field['field'], 'type'=>$field['type']), $grouping,
            array('match'=>$field['match']), $result);
    }

    /**
     * detail=minmax: smallest and largest value of one numeric or date field
     * over the query's records (see FieldValueRange).
     *
     * @return array{field:string,type:string,min:mixed,max:mixed,count:int}
     */
    public function valueRange(SearchRequest $request): array
    {
        $range = new FieldValueRange($this->builder, $this->executor);
        $field = $range->describeField($request->valueField);
        $query = $this->builder->normalize($request->query);
        if($request->filter !== null && $request->filter !== '' && $request->filter !== array()){
            $query[] = array('all'=>$this->builder->normalize($request->filter));
        }
        $context = $this->searchContext($request, array());
        $candidateCache = array();
        $query = $this->resolveSelectiveAnyFields($query, $context, $candidateCache);
        $result = $this->builder->supportsSqlExecution($query)
            ? $range->rangeForQuery($query, $context, $field)
            : $range->rangeForIds($this->evaluateGroup($query, null, 'all', $context, 0), $context, $field);
        return array_merge(array('field'=>$field['field'], 'type'=>$field['type']), $result);
    }

    /**
     * detail=values: distinct values of one field over the query's records,
     * with record counts (see FieldValueCounter).
     *
     * @return array{field:string,total:int,values:array}
     */
    public function countValues(SearchRequest $request): array
    {
        $counter = new FieldValueCounter($this->builder, $this->executor);
        $field = $counter->describeField($request->valueField);
        $query = $this->builder->normalize($request->query);
        if($request->filter !== null && $request->filter !== '' && $request->filter !== array()){
            $query[] = array('all'=>$this->builder->normalize($request->filter));
        }
        $context = $this->searchContext($request, array());
        $candidateCache = array();
        $query = $this->resolveSelectiveAnyFields($query, $context, $candidateCache);
        $options = array(
            'text'=>$request->valueText,
            'limit'=>$request->limit,
            'offset'=>$request->offset,
            'sort'=>$request->valueSort
        );
        if(!empty($request->valueVia)){
            // the field is in linked records: count the main records that reach each value
            $roots = $this->evaluateGroup($query, null, 'all', $context, 0);
            $via = $this->builder->normalize($request->valueVia);
            $result = $counter->countThroughLinks($this->linkedPathTargets($roots, $via, $context), $context, $field, $options);
            return array('field'=>$field['field'], 'total'=>$result['total'], 'values'=>$result['values']);
        }
        $result = $this->builder->supportsSqlExecution($query)
            ? $counter->countForQuery($query, $context, $field, $options)
            : $counter->countForIds($this->evaluateGroup($query, null, 'all', $context, 0), $context, $field, $options);
        return array('field'=>$field['field'], 'total'=>$result['total'], 'values'=>$result['values']);
    }

    /** Execute a search and always return the requested page and full count. */
    public function search(SearchRequest $request, array $context = array()): SearchResult
    {
        $query = $this->builder->normalize($request->query);
        if($request->filter !== null && $request->filter !== '' && $request->filter !== array()){
            // A separate top-level group preserves: (base query) AND (filter).
            $query[] = array('all'=>$this->builder->normalize($request->filter));
        }
        $context = $this->searchContext($request, $context);
        $context['sortProvided'] = $request->sortProvided;
        $context['sort'] = $request->sort;
        $candidateCache = array();
        $query = $this->resolveSelectiveAnyFields($query, $context, $candidateCache);
        if(empty($context['forceChunked']) && $this->builder->supportsSqlExecution($query)){
            if($request->detail === 'count'){
                $total = intval($this->executor->executeScalar($this->builder->buildCount($query, $context)));
                return new SearchResult(array(), $total, 0, 1);
            }
            if($request->detail === 'rectypes'){
                $rectypes = $this->executor->executeRectypeCounts(
                    $this->builder->buildRectypeCounts($query, $context)
                );
                $total = array_sum(array_column($rectypes, 'count'));
                return new SearchResult(array(), $total, 0, 1, null, $rectypes);
            }
            $ids = $this->executor->executeIds($this->builder->buildIds($query, $context));
            $total = intval($this->executor->executeScalar($this->builder->buildCount($query, $context)));
            return new SearchResult($ids, $total, $request->offset, $request->limit);
        }
        $ids = $this->evaluateGroup($query, null, 'all', $context, 0);
        if($request->detail === 'count'){
            return new SearchResult(array(), count($ids), 0, 1);
        }
        if($request->detail === 'rectypes'){
            return new SearchResult(
                array(), count($ids), 0, 1, null,
                $this->executor->executeRectypeCountsForIds($ids)
            );
        }
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
            'rt','related_to','relatedto','rf','related_from','relatedfrom','related','links','connected'
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
        $seed = empty($flat) && $candidateIds === null
            ? $this->traversalParentCandidates($complex, $context, $depth) : null;
        if(!empty($flat)){
            $current = $this->executeFlatSet($flat, $candidateIds, $context);
        }elseif($seed !== null){
            // child-first: only records at the far end of a matching edge can match
            $current = empty($seed) ? array() : $this->executeFlatSet(array(array('ids'=>$seed)), null, $context);
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
            }elseif($base === 'links'){
                // resource links in either direction: lt OR lf
                $current = $this->edgeParents($current,
                    $this->matchingLinkEdges($current, $suffix, $value, $context, $depth+1));
            }elseif($base === 'connected'){
                // resource links or relationships, either direction
                $current = $this->edgeParents($current, array_merge(
                    $this->matchingLinkEdges($current, '', $value, $context, $depth+1),
                    $this->matchingRelationshipEdges($current, 'both', '', $value, $context, $depth+1)
                ));
            }else{
                throw new UnsupportedQueryException('Predicate is not supported by Phase 3: '.$key);
            }
        }
        return $current;
    }

    /**
     * Safety net for traversals that stay chunked: with no other constraint the parent
     * set would be every record. Read the edges from the child side instead and return
     * their parent ends - a superset the ordinary traversal then filters. Null when no
     * traversal can seed the set, it has an exists modifier, or the set is too large to
     * pass as one ID list (MAX_PRECOMPUTED_CANDIDATES).
     */
    private function traversalParentCandidates(array $complex, array $context, int $depth): ?array
    {
        $passes = array(
            'lt'=>array(array(false,'to')), 'linked_to'=>array(array(false,'to')), 'linkedto'=>array(array(false,'to')),
            'lf'=>array(array(false,'from')), 'linked_from'=>array(array(false,'from')), 'linkedfrom'=>array(array(false,'from')),
            'rt'=>array(array(true,'to')), 'related_to'=>array(array(true,'to')), 'relatedto'=>array(array(true,'to')),
            'rf'=>array(array(true,'from')), 'related_from'=>array(array(true,'from')), 'relatedfrom'=>array(array(true,'from')),
            'links'=>array(array(false,'to'), array(false,'from')),
            'related'=>array(array(true,'to'), array(true,'from')),
            'connected'=>array(array(false,'to'), array(false,'from'), array(true,'to'), array(true,'from'))
        );
        foreach($complex as $predicate){
            $key = (string)array_keys($predicate)[0];
            list($base, $suffix) = $this->predicateParts($key);
            if(!isset($passes[$base])){ continue; }
            $isRelation = in_array($base, array_merge(self::RELATION_TO, self::RELATION_FROM, array('related')), true);
            $childQuery = $isRelation
                ? $this->splitRelationshipValue($predicate[$key])[0]
                : $this->normaliseLinkedValue($predicate[$key]);
            $constrained = false;
            foreach($childQuery as $item){
                list($childBase) = $this->predicateParts((string)array_keys($item)[0]);
                if($childBase === 'exists'){ return null; }
                if($childBase !== '_all'){ $constrained = true; }
            }
            $children = null;
            if($constrained){
                array_unshift($childQuery, array('_all'=>true));
                $children = $this->evaluateGroup($childQuery, null, 'all', $context, $depth+1);
                if(empty($children)){ return array(); }
            }
            // a field suffix narrows pointer edges; relationship suffixes are left to the traversal
            $fieldId = (!$isRelation && $base !== 'connected') ? $this->positiveSuffix($suffix, 'Resource-link field ID') : null;
            $parents = array();
            foreach($passes[$base] as list($relation, $direction)){
                $parentColumn = $direction === 'to' ? 'rl_SourceID' : 'rl_TargetID';
                $childColumn = $direction === 'to' ? 'rl_TargetID' : 'rl_SourceID';
                $conditions = array('rl_RelationID IS '.($relation ? 'NOT NULL' : 'NULL'));
                $types = ''; $values = array();
                if($fieldId !== null && !$relation){
                    $conditions[] = 'rl_DetailTypeID=?'; $types .= 'i'; $values[] = $fieldId;
                }
                foreach($children === null ? array(null) : array_chunk($children, self::SQL_CHUNK_SIZE) as $chunk){
                    $where = $conditions; $chunkTypes = $types; $chunkValues = $values;
                    if($chunk !== null){
                        $where[] = $childColumn.' IN ('.implode(',', array_fill(0, count($chunk), '?')).')';
                        $chunkTypes .= str_repeat('i', count($chunk));
                        $chunkValues = array_merge($chunkValues, $chunk);
                    }
                    $sql = 'SELECT DISTINCT '.$parentColumn.' FROM recLinks WHERE '.implode(' AND ', $where);
                    foreach($this->executor->executeRows($sql, $chunkTypes, $chunkValues) as $row){
                        $parents[intval($row[0])] = true;
                        if(count($parents) > self::MAX_PRECOMPUTED_CANDIDATES){ return null; }
                    }
                }
            }
            unset($parents[0]);
            return array_keys($parents);
        }
        return null;
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
        return $this->edgeParents($parents,
            $this->matchingResourceEdges($parents, $direction, $fieldSuffix, $value, $context, $depth));
    }

    /**
     * [parent, child] resource-link edges whose child matches the linked sub-query
     * (the search's own semantics; also used to walk a facet's link path).
     */
    private function matchingResourceEdges(
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
        return $this->edgesToChildren($edges, $matchingChildren);
    }

    /**
     * [parent, child] resource-link edges in both directions (`links`): the parent points
     * to the child (lt) or the child points to the parent (lf), the child matching the sub-query.
     */
    private function matchingLinkEdges(
        array $parents,
        string $fieldSuffix,
        $value,
        array $context,
        int $depth
    ): array {
        return array_merge(
            $this->matchingResourceEdges($parents, 'to', $fieldSuffix, $value, $context, $depth),
            $this->matchingResourceEdges($parents, 'from', $fieldSuffix, $value, $context, $depth)
        );
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
        return $this->edgeParents($parents,
            $this->matchingRelationshipEdges($parents, $direction, $suffix, $value, $context, $depth));
    }

    /**
     * [parent, child, Relationship record, type] edges whose Relationship record and
     * endpoint match (the search's own semantics; also used to walk a facet's link path).
     */
    private function matchingRelationshipEdges(
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
        return $this->edgesToChildren($edges, $matchingChildren);
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

    /**
     * Resolve the terms a relationship reads as when seen from its target, plus
     * their descendants. A term's reverse is its trm_InverseTermID, or any term
     * naming it as inverse (one-sided definitions); a term with no inverse is
     * undirected and reads the same both ways, so it is its own reverse.
     */
    private function inverseTermIds(array $termIds): array
    {
        $inverse = array();
        foreach(array_chunk($this->uniqueIds($termIds), self::SQL_CHUNK_SIZE) as $chunk){
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sql = 'SELECT trm_ID, trm_InverseTermID FROM defTerms'
                .' WHERE trm_ID IN ('.$placeholders.') OR trm_InverseTermID IN ('.$placeholders.')';
            $found = array_fill_keys($chunk, false);
            foreach($this->executor->executeRows($sql, str_repeat('i', 2*count($chunk)), array_merge($chunk, $chunk)) as $row){
                $termId = intval($row[0]); $inverseId = intval($row[1]);
                if(isset($found[$termId]) && $inverseId>0){ $inverse[] = $inverseId; $found[$termId] = true; }
                if(isset($found[$inverseId]) && $termId>0){ $inverse[] = $termId; $found[$inverseId] = true; }
            }
            foreach($found as $termId=>$hasInverse){
                if(!$hasInverse){ $inverse[] = intval($termId); }
            }
        }
        return empty($inverse) ? array() : $this->expandTermIds($inverse);
    }

    private function normaliseLinkedValue($value): array
    {
        // an empty linked query means any record (in expansion rules: the parent result)
        if($value === null || $value === array()){ return array(); }
        if(is_array($value) && !$this->isIdList($value)){ return $this->builder->normalize($value); }
        return array(array('ids'=>$this->normalizeIds($value, 'linked record')));
    }

    private function parentsForMatchingEdges(array $parents, array $edges, array $matchingChildren): array
    {
        return $this->edgeParents($parents, $this->edgesToChildren($edges, $matchingChildren));
    }

    /** Edges whose child is one of the matching children. */
    private function edgesToChildren(array $edges, array $matchingChildren): array
    {
        $childSet = array_fill_keys($matchingChildren, true);
        return array_values(array_filter($edges, static function($edge) use ($childSet){
            return isset($childSet[$edge[1]]);
        }));
    }

    /** Parents (in their order) that have at least one of the edges. */
    private function edgeParents(array $parents, array $edges): array
    {
        $parentSet = array();
        foreach($edges as $edge){ $parentSet[$edge[0]] = true; }
        return $this->orderedSubset($parents, $parentSet);
    }

    /**
     * Follow a chain of link predicates from each root record: \`via\` lists, from the
     * outside in, the links of a linked branch (\`{"lt:240":[{"t":"48"}, …other
     * conditions of that branch]}\`), each step matched like the search matches it.
     *
     * @param int[] $rootIds Main records.
     * @param array $via Link predicates, outer first.
     * @param array $context Search context.
     * @return array<int,int[]> Root id → the records reached at the end of the path.
     */
    public function linkedPathTargets(array $rootIds, array $via, array $context): array
    {
        $reach = array();
        foreach($this->uniqueIds($rootIds) as $root){ $reach[$root] = array($root); }
        foreach($via as $step){
            if(!is_array($step) || count($step) !== 1){
                throw new QueryValidationException('Each via step must be one link predicate');
            }
            $key = (string)array_keys($step)[0];
            $value = $step[$key];
            list($base, $suffix) = $this->predicateParts($key);
            $parents = $this->uniqueIds(array_merge(array(), ...array_values($reach)));
            if(empty($parents)){ return array_fill_keys(array_keys($reach), array()); }
            if(in_array($base, self::RESOURCE_TO, true)){
                $edges = $this->matchingResourceEdges($parents, 'to', $suffix, $value, $context, 1);
            }elseif(in_array($base, self::RESOURCE_FROM, true)){
                $edges = $this->matchingResourceEdges($parents, 'from', $suffix, $value, $context, 1);
            }elseif(in_array($base, self::RELATION_TO, true)){
                $edges = $this->matchingRelationshipEdges($parents, 'to', $suffix, $value, $context, 1);
            }elseif(in_array($base, self::RELATION_FROM, true)){
                $edges = $this->matchingRelationshipEdges($parents, 'from', $suffix, $value, $context, 1);
            }elseif($base === 'related'){
                $edges = $this->matchingRelationshipEdges($parents, 'both', $suffix, $value, $context, 1);
            }elseif($base === 'links'){
                $edges = $this->matchingLinkEdges($parents, $suffix, $value, $context, 1);
            }else{
                throw new QueryValidationException('A via step must be a link predicate (lt, lf, rt, rf, related, links): '.$key);
            }
            $children = array();
            foreach($edges as $edge){ $children[$edge[0]][$edge[1]] = true; }
            foreach($reach as $root=>$ids){
                $next = array();
                foreach($ids as $id){ foreach($children[$id] ?? array() as $child=>$unused){ $next[$child] = true; } }
                $reach[$root] = array_keys($next);
            }
        }
        return $reach;
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
            array('all','any','not','related','r','relf','links','connected'),
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
            'userId' => $this->runtime->userId,
            'groupIds' => $this->runtime->groupIds,
            'isDbOwner' => $this->runtime->isDbOwner,
            'limit' => $request->limit,
            'offset' => $request->offset
        ), $context);
    }
}
