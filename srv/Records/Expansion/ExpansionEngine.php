<?php
/**
* ExpansionEngine.php - Set-based linked-record graph expansion
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

namespace Heurist\Records\Expansion;

use Heurist\Records\Query\QueryExecutor;
use Heurist\Records\Query\RecordSearchService;
use Heurist\Records\Query\SearchRequest;
use Heurist\Records\Query\QueryValidationException;
/** Expands rule branches one level at a time without per-parent queries. */
final class ExpansionEngine
{
    private const RESOURCE_FORWARD = array('lf','linked_from','linkedfrom');
    private const RESOURCE_REVERSE = array('lt','linked_to','linkedto');
    private const RELATION_FORWARD = array('rf','related_from','relatedfrom');
    private const RELATION_REVERSE = array('rt','related_to','relatedto');
    /** Undirected traversals: pointers, relationships, or both, in either direction. */
    private const UNDIRECTED = array('links', 'related', 'connected');

    /** @var RecordSearchService */
    private $search;
    /** @var QueryExecutor */
    private $executor;
    /** @var ExpansionRuleParser */
    private $parser;
    /** @var int */
    private $pathSequence = 0;

    public function __construct(
        QueryExecutor $executor,
        RecordSearchService $search,
        ?ExpansionRuleParser $parser = null
    ) {
        $this->executor = $executor;
        $this->search = $search;
        $this->parser = $parser ?? new ExpansionRuleParser();
    }

    /** Execute every rule branch and return records, edges, paths, and top associations. */
    public function expand(ExpansionRequest $request, array $context = array()): ExpansionResult
    {
        $this->pathSequence = 0;
        $result = new ExpansionResult();
        $origins = array();
        foreach($request->seedIds as $id){
            $result->addRecord($id);
            $result->associate($id, $id);
            $origins[$id] = array($id=>array(array($id)));
        }
        if(empty($origins)){ return $result; }

        $rules = $this->parser->parse($request->rules);
        $this->executeLevel($rules, $origins, $request, $result, $context, '');
        if($request->includeHeaders){
            $this->loadRecordHeaders($result, $request->batchSize);
        }
        return $result;
    }

    /** Execute sibling rules independently against the same parent set. */
    private function executeLevel(
        array $rules,
        array $parentOrigins,
        ExpansionRequest $request,
        ExpansionResult $result,
        array $context,
        string $parentPath
    ): void {
        foreach($rules as $rule){
            list($anchor, $additionalQuery) = $this->extractAnchor($rule['query']);
            $path = $this->appendPath($parentPath, $anchor, $additionalQuery);
            $pathId = 'p'.(++$this->pathSequence);
            $result->addPath($pathId, $path);

            $rows = $this->readEdges(array_keys($parentOrigins), $anchor, $request->batchSize);
            $candidateIds = array();
            foreach($rows as $row){ $candidateIds[intval($row[1])] = intval($row[1]); }
            $allowed = $this->filterCandidates(array_values($candidateIds), $additionalQuery, $context);
            $allowedSet = array_fill_keys($allowed, true);
            $childOrigins = array();

            foreach($rows as $row){
                $parentId = intval($row[0]);
                $childId = intval($row[1]);
                if(!isset($allowedSet[$childId])){ continue; }
                $sourceId = intval($row[2]);
                $targetId = intval($row[3]);
                $fieldId = intval($row[4]);
                $relationId = intval($row[5]);
                $result->addRecord($childId);
                $result->addEdge(array(
                    'source'=>$sourceId, 'target'=>$targetId,
                    'field'=>$fieldId, 'relationship'=>$relationId
                ));
                foreach($parentOrigins[$parentId] ?? array() as $topId=>$chains){
                    $result->associate($topId, $childId);
                    if($relationId > 0){
                        $result->addRecord($relationId);
                        $result->associate($topId, $relationId);
                    }
                    foreach($chains as $chain){
                        $nextChain = $chain;
                        if($relationId > 0){ $nextChain[] = $relationId; }
                        $nextChain[] = $childId;
                        $chainKey = implode(',', $nextChain);
                        $childOrigins[$childId][$topId][$chainKey] = $nextChain;
                        $result->addOccurrence($topId, $pathId, $nextChain);
                    }
                }
            }

            if(!empty($childOrigins) && !empty($rule['levels'])){
                $this->executeLevel(
                    $rule['levels'], $childOrigins, $request, $result, $context, $path
                );
            }
        }
    }

    /** Separate the rule's parent-link predicate from its optional endpoint query. */
    private function extractAnchor(array $query): array
    {
        $anchor = null;
        $additional = array();
        foreach($query as $predicate){
            $key = (string)array_keys($predicate)[0];
            list($base, $suffix) = $this->predicateParts($key);
            if($this->isTraversal($base)){
                if($anchor !== null){
                    throw new QueryValidationException(
                        'An expansion rule must contain exactly one parent-link predicate'
                    );
                }
                $anchor = array('base'=>$base, 'suffix'=>$suffix, 'parentQuery'=>$predicate[$key]);
            }else{
                $additional[] = $predicate;
            }
        }
        if($anchor === null){
            throw new QueryValidationException('Expansion rule has no parent-link predicate');
        }
        return array($anchor, $additional);
    }

    /** Read edge rows as parent, child, source, target, field, relationship. */
    private function readEdges(array $parentIds, array $anchor, int $batchSize): array
    {
        $base = $anchor['base'];
        if($base === 'connected' && $anchor['suffix'] !== ''){
            throw new QueryValidationException('connected does not accept a field or relationship type');
        }
        $relationBase = in_array($base, self::RELATION_FORWARD, true) ? 'rf'
            : (in_array($base, self::RELATION_REVERSE, true) ? 'rt'
            : (in_array($base, array('related', 'connected'), true) ? 'related' : null));
        // pointer reads: forward = the parent (seed) is the stored source
        $passes = array();
        if($relationBase === null || $base === 'connected'){
            $forwards = in_array($base, self::UNDIRECTED, true) ? array(true, false)
                : array(in_array($base, self::RESOURCE_FORWARD, true));
            foreach($forwards as $forward){
                $passes[] = array('relation'=>false, 'forward'=>$forward, 'types'=>null, 'parentTypes'=>null, 'childTypes'=>null);
            }
        }
        // relationship reads: the legs shared with search. The rule's outer record is the
        // child, the parent is the linked record: a leg whose outer record is the stored
        // source reads from the parent as target.
        if($relationBase !== null){
            $legs = $this->search->relationshipLegs($relationBase, $base === 'connected' ? '' : $anchor['suffix'],
                $this->relationshipTypes($anchor['parentQuery']));
            foreach($legs as $leg){
                $passes[] = array('relation'=>true, 'forward'=>$leg['direction'] === 'from',
                    'types'=>$leg['types'], 'parentTypes'=>$leg['linkedTypes'], 'childTypes'=>$leg['outerTypes']);
            }
        }
        $rows = array();
        foreach($passes as $pass){
            $rows = array_merge($rows, $this->readEdgePass($parentIds, $anchor, $pass, $batchSize));
        }
        return $rows;
    }

    /**
     * One edge read: pointers or relationships, forward (parent is the stored source) or
     * reverse, with optional relation types and parent/child record types.
     */
    private function readEdgePass(array $parentIds, array $anchor, array $pass, int $batchSize): array
    {
        $rows = array();
        $parentColumn = $pass['forward'] ? 'rl_SourceID' : 'rl_TargetID';
        $childColumn = $pass['forward'] ? 'rl_TargetID' : 'rl_SourceID';
        $inList = static function(string $column, array $ids, string &$types, array &$values): string {
            $types .= str_repeat('i', count($ids));
            $values = array_merge($values, array_map('intval', $ids));
            return $column.' IN ('.implode(',', array_fill(0, count($ids), '?')).')';
        };
        foreach(array_chunk($parentIds, $batchSize) as $chunk){
            $types = ''; $values = array(); $joins = '';
            $conditions = array($inList('rl.'.$parentColumn, $chunk, $types, $values));
            if($pass['relation']){
                $conditions[] = 'rl.rl_RelationID IS NOT NULL';
                if($pass['types'] !== null){ $conditions[] = $inList('rl.rl_RelationTypeID', $pass['types'], $types, $values); }
                if($pass['parentTypes'] !== null){
                    $joins .= ' INNER JOIN Records rp ON rp.rec_ID=rl.'.$parentColumn;
                    $conditions[] = $inList('rp.rec_RecTypeID', $pass['parentTypes'], $types, $values);
                }
                if($pass['childTypes'] !== null){
                    $joins .= ' INNER JOIN Records rc ON rc.rec_ID=rl.'.$childColumn;
                    $conditions[] = $inList('rc.rec_RecTypeID', $pass['childTypes'], $types, $values);
                }
            }else{
                $conditions[] = 'rl.rl_RelationID IS NULL';
                if($anchor['suffix'] !== '' && $anchor['base'] !== 'connected'){
                    if(!ctype_digit($anchor['suffix']) || intval($anchor['suffix']) < 1){
                        throw new QueryValidationException('Expansion link field must be a positive ID');
                    }
                    $conditions[] = 'rl.rl_DetailTypeID=?';
                    $types .= 'i';
                    $values[] = intval($anchor['suffix']);
                }
            }
            $sql = 'SELECT rl.'.$parentColumn.',rl.'.$childColumn
                .',rl.rl_SourceID,rl.rl_TargetID,COALESCE(rl.rl_DetailTypeID,0)'
                .',COALESCE(rl.rl_RelationID,0) FROM recLinks rl'.$joins.' WHERE '.implode(' AND ', $conditions);
            $rows = array_merge($rows, $this->executor->executeRows($sql, $types, $values));
        }
        return $rows;
    }

    /** Relation types given as "r" in the nested parent query; null when there are none. */
    private function relationshipTypes($parentQuery): ?array
    {
        if(!is_array($parentQuery)){ return null; }
        foreach($parentQuery as $predicate){
            if(!is_array($predicate) || count($predicate)!==1){ continue; }
            $key = (string)array_keys($predicate)[0];
            list($base, $suffix) = $this->predicateParts($key);
            if($base !== 'r' || $suffix !== ''){ continue; }
            $value = $predicate[$key];
            $values = is_array($value) ? $value : preg_split('/\s*,\s*/', (string)$value);
            return array_values(array_filter(array_map('intval', $values)));
        }
        return null;
    }

    /** Apply endpoint conditions and ordinary access rules in one IDs search. */
    private function filterCandidates(array $candidateIds, array $query, array $context): array
    {
        if(empty($candidateIds)){ return array(); }
        $query[] = array('ids'=>$candidateIds);
        $request = new SearchRequest($query, array('limit'=>count($candidateIds), 'offset'=>0));
        return $this->search->search($request, $context)->ids;
    }

    /** Load graph headers in bounded batches after traversal has completed. */
    private function loadRecordHeaders(ExpansionResult $result, int $batchSize): void
    {
        $graph = $result->toArray();
        $ids = array_column($graph['records'], 'rec_ID');
        foreach(array_chunk($ids, $batchSize) as $chunk){
            if(empty($chunk)){ continue; }
            $sql = 'SELECT rec_ID,rec_RecTypeID,rec_Title FROM Records WHERE rec_ID IN ('
                .implode(',', array_fill(0, count($chunk), '?')).')';
            foreach($this->executor->executeRows($sql, str_repeat('i', count($chunk)), $chunk) as $row){
                $result->addRecord(intval($row[0]), intval($row[1]), (string)$row[2]);
            }
        }
    }

    private function appendPath(string $parent, array $anchor, array $query): string
    {
        $parentType = '*';
        $parentQuery = $anchor['parentQuery'];
        if(is_array($parentQuery)){
            foreach($parentQuery as $predicate){
                if(!is_array($predicate) || count($predicate)!==1){ continue; }
                $key = (string)array_keys($predicate)[0];
                list($base) = $this->predicateParts($key);
                if(in_array($base, array('t','type','typeid','typename'), true)){
                    $value = $predicate[$key];
                    $parentType = is_array($value) ? implode(',', $value) : (string)$value;
                    break;
                }
            }
        }
        $type = '*';
        foreach($query as $predicate){
            $key = (string)array_keys($predicate)[0];
            list($base) = $this->predicateParts($key);
            if(in_array($base, array('t','type','typeid','typename'), true)){
                $value = $predicate[$key];
                $type = is_array($value) ? implode(',', $value) : (string)$value;
                break;
            }
        }
        $outward = array(
            'lf'=>'lt', 'linked_from'=>'lt', 'linkedfrom'=>'lt',
            'lt'=>'lf', 'linked_to'=>'lf', 'linkedto'=>'lf',
            'rf'=>'rt', 'related_from'=>'rt', 'relatedfrom'=>'rt',
            'rt'=>'rf', 'related_to'=>'rf', 'relatedto'=>'rf',
            'links'=>'links', 'related'=>'r', 'connected'=>'connected'
        )[$anchor['base']];
        $operator = $outward.($anchor['suffix'] === '' ? '' : $anchor['suffix']);
        $prefix = $parent === '' ? $parentType : $parent;
        return $prefix.':'.$operator.':'.$type;
    }

    private function isTraversal(string $base): bool
    {
        return in_array($base, array_merge(
            self::RESOURCE_FORWARD, self::RESOURCE_REVERSE,
            self::RELATION_FORWARD, self::RELATION_REVERSE, self::UNDIRECTED
        ), true);
    }

    private function predicateParts(string $key): array
    {
        $parts = explode(':', strtolower(trim($key)), 2);
        return array($parts[0], $parts[1] ?? '');
    }
}
