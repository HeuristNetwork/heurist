<?php
/**
* ExpansionTypes.php - Contracts for linked-record expansion
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

/** Immutable request for expanding an already paginated top-record set. */
final class ExpansionRequest
{
    /** @var array<int> */
    public $seedIds;
    /** @var mixed JSON ruleset, JSON string, or compact path. */
    public $rules;
    /** @var int Maximum parent IDs included in one edge query. */
    public $batchSize;
    /** @var bool Whether graph records require their standard headers. */
    public $includeHeaders;

    public function __construct(array $seedIds, $rules, array $options = array())
    {
        $this->seedIds = array_values(array_unique(array_filter(array_map('intval', $seedIds))));
        $this->rules = $rules;
        $this->batchSize = min(5000, max(1, intval($options['batchSize'] ?? 500)));
        $this->includeHeaders = !empty($options['includeHeaders']);
    }
}

/** Graph produced by linked-record expansion. */
final class ExpansionResult
{
    /** @var array<int,array> Records indexed internally by record ID. */
    private $records = array();
    /** @var array<string,array> Distinct graph edges. */
    private $edges = array();
    /** @var array<string,string> */
    private $paths = array();
    /** @var array<int,array<int>> Top-record to participating-record associations. */
    private $topNodes = array();
    /** @var array<string,array> Distinct path occurrences. */
    private $occurrences = array();

    public function addRecord(int $id, ?int $typeId = null, ?string $title = null): void
    {
        if($id < 1){ return; }
        $record = array('rec_ID'=>$id);
        if($typeId !== null){ $record['rec_RecTypeID'] = $typeId; }
        if($title !== null){ $record['rec_Title'] = $title; }
        $this->records[$id] = array_merge($this->records[$id] ?? array(), $record);
    }

    public function addEdge(array $edge): void
    {
        $parts = array(
            intval($edge['source'] ?? 0), intval($edge['target'] ?? 0),
            intval($edge['field'] ?? 0), intval($edge['relationship'] ?? 0)
        );
        if($parts[0] < 1 || $parts[1] < 1){ return; }
        $this->edges[implode(':', $parts)] = array_filter(array(
            'source'=>$parts[0], 'target'=>$parts[1],
            'field'=>$parts[2] ?: null,
            'relationship'=>$parts[3] ?: null
        ), static function($value){ return $value !== null; });
    }

    public function addPath(string $id, string $path): void
    {
        $this->paths[$id] = $path;
    }

    public function associate(int $topId, int $nodeId): void
    {
        $this->topNodes[$topId][$nodeId] = $nodeId;
    }

    /** Preserve a concrete record chain even when its endpoint is shared. */
    public function addOccurrence(int $topId, string $pathId, array $recordIds): void
    {
        $recordIds = array_values(array_filter(array_map('intval', $recordIds)));
        $key = $topId.':'.$pathId.':'.implode(',', $recordIds);
        $this->occurrences[$key] = array(
            'top'=>$topId, 'path'=>$pathId, 'recordIds'=>$recordIds
        );
    }

    /** Stable controller/presenter representation. */
    public function toArray(): array
    {
        $associations = array();
        foreach($this->topNodes as $topId=>$nodes){
            $associations[(string)$topId] = array_values($nodes);
        }
        return array(
            'records'=>array_values($this->records),
            'edges'=>array_values($this->edges),
            'paths'=>$this->paths,
            'topNodes'=>$associations,
            'occurrences'=>array_values($this->occurrences)
        );
    }
}
