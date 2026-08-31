<?php
/**
* ExpansionEngine.php - Set-based linked-record graph expansion
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
        $rows = array();
        $forward = in_array($anchor['base'], array_merge(self::RESOURCE_FORWARD, self::RELATION_FORWARD), true);
        $relationship = in_array($anchor['base'], array_merge(self::RELATION_FORWARD, self::RELATION_REVERSE), true);
        $parentColumn = $forward ? 'rl_SourceID' : 'rl_TargetID';
        $childColumn = $forward ? 'rl_TargetID' : 'rl_SourceID';
        foreach(array_chunk($parentIds, $batchSize) as $chunk){
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $conditions = array('rl.'.$parentColumn.' IN ('.$placeholders.')');
            $values = array_values($chunk);
            $types = str_repeat('i', count($chunk));
            if($relationship){
                $conditions[] = 'rl.rl_RelationID IS NOT NULL';
                $relationTypes = $this->relationshipTypes($anchor['parentQuery']);
                if(!empty($relationTypes)){
                    $conditions[] = 'rl.rl_RelationTypeID IN ('
                        .implode(',', array_fill(0, count($relationTypes), '?')).')';
                    $types .= str_repeat('i', count($relationTypes));
                    $values = array_merge($values, $relationTypes);
                }
            }else{
                $conditions[] = 'rl.rl_RelationID IS NULL';
                if($anchor['suffix'] !== ''){
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
                .',COALESCE(rl.rl_RelationID,0) FROM recLinks rl WHERE '.implode(' AND ', $conditions);
            $rows = array_merge($rows, $this->executor->executeRows($sql, $types, $values));
        }
        return $rows;
    }

    /** Read a direct relationship-type constraint from the nested parent query. */
    private function relationshipTypes($parentQuery): array
    {
        if(!is_array($parentQuery)){ return array(); }
        foreach($parentQuery as $predicate){
            if(!is_array($predicate) || count($predicate)!==1){ continue; }
            $key = (string)array_keys($predicate)[0];
            list($base, $suffix) = $this->predicateParts($key);
            if($base !== 'r' || $suffix !== ''){ continue; }
            $value = $predicate[$key];
            $values = is_array($value) ? $value : preg_split('/\s*,\s*/', (string)$value);
            return array_values(array_filter(array_map('intval', $values)));
        }
        return array();
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
            'rt'=>'rf', 'related_to'=>'rf', 'relatedto'=>'rf'
        )[$anchor['base']];
        $operator = $outward.($anchor['suffix'] === '' ? '' : $anchor['suffix']);
        $prefix = $parent === '' ? $parentType : $parent;
        return $prefix.':'.$operator.':'.$type;
    }

    private function isTraversal(string $base): bool
    {
        return in_array($base, array_merge(
            self::RESOURCE_FORWARD, self::RESOURCE_REVERSE,
            self::RELATION_FORWARD, self::RELATION_REVERSE
        ), true);
    }

    private function predicateParts(string $key): array
    {
        $parts = explode(':', strtolower(trim($key)), 2);
        return array($parts[0], $parts[1] ?? '');
    }
}
