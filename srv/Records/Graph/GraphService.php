<?php
/**
* GraphService.php - Graph document assembly
*
* Runs the top-level query for seed records, discovers internal edges for the
* requested links, loads the fixed graph header set, and reports the effective
* node/edge budget. Interactive single-rule expansion is added in a later
* stage; this service currently produces the initial graph only.
*
* @project     Heurist academic knowledge management system
* @package     Records\Graph
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);
namespace Heurist\Records\Graph;

use Heurist\Database\DatabaseInterface;
use Heurist\Runtime\RuntimeContext;
use Heurist\Records\Query\Compiler\QueryBuilder;
use Heurist\Records\Query\QueryExecutor;
use Heurist\Records\Query\RecordSearchService;
use Heurist\Records\Query\SearchRequest;
use Heurist\Records\Query\SearchResult;

/** Builds one graph document from a normalized GraphRequest. */
final class GraphService
{
    private const HEADER_CHUNK_SIZE = 500;

    /** @var QueryExecutor */
    private $executor;
    /** @var RecordSearchService|object */
    private $search;
    /** @var GraphEdgeDiscovery */
    private $edges;
    /** @var GraphLinkParser */
    private $linkParser;

    public function __construct(
        DatabaseInterface $database,
        RuntimeContext $runtime,
        $search = null,
        $executor = null,
        ?QueryBuilder $builder = null
    ) {
        $this->executor = $executor ?? new QueryExecutor($database);
        $builder = $builder ?? new QueryBuilder($database);
        $this->search = $search ?? new RecordSearchService($database, $runtime, $builder, $this->executor);
        $this->edges = new GraphEdgeDiscovery($this->executor);
        $this->linkParser = new GraphLinkParser();
    }

    /** Execute a graph request and return its renderer-neutral document. */
    public function build(GraphRequest $request): GraphResult
    {
        $seed = $this->search->search(new SearchRequest($request->query, array(
            'limit' => $request->limit,
            'offset' => $request->offset,
            'detail' => 'ids',
        )));
        if(!$seed instanceof SearchResult){
            $seedIds = array();
            $total = 0;
        }else{
            $seedIds = $seed->ids;
            $total = $seed->total;
        }

        if(count($seedIds) > $request->maxNodes){
            $seedIds = array_slice($seedIds, 0, $request->maxNodes);
        }
        // Nodes are truncated whenever the query matched more records than the
        // seed page returned - the node budget or ordinary pagination limit.
        $nodesTruncated = $total > count($seedIds);

        list($edges, $links, $edgesTruncated) = $this->discoverEdges($seedIds, $request);
        $records = $this->loadHeaders($seedIds);

        $result = new GraphResult($request->displayQuery, $total, $request->offset, $request->limit);
        $result->setRecords($records);
        $result->setEdges($edges);
        $result->setLinks($links);
        $result->setPaths(array());
        $result->setLimits(array(
            'maxNodes' => $request->maxNodes,
            'maxEdges' => $request->maxEdges,
            'maxDepth' => $request->maxDepth,
            'nodesReturned' => count($records),
            'edgesReturned' => count($edges),
            'truncated' => ($nodesTruncated || $edgesTruncated),
        ));
        return $result;
    }

    /**
     * @param int[] $seedIds
     * @return array{0:array<int,array<string,mixed>>,1:array<string,string>,2:bool}
     */
    private function discoverEdges(array $seedIds, GraphRequest $request): array
    {
        if(empty($seedIds) || $request->links === null){
            return array(array(), array(), false);
        }
        if($request->links === 'all'){
            $discovered = $this->edges->discover($seedIds, null, $request->maxEdges);
            return array($discovered['edges'], array(), $discovered['truncated']);
        }

        $links = array();
        $defs = array();
        $index = 0;
        foreach($this->linkParser->parseList($request->links) as $def){
            $key = 'l'.(++$index);
            $def['key'] = $key;
            $links[$key] = $def['spec'];
            $defs[] = $def;
        }
        $discovered = $this->edges->discover($seedIds, $defs, $request->maxEdges);
        return array($discovered['edges'], $links, $discovered['truncated']);
    }

    /**
     * Load the fixed graph header set in query order.
     *
     * @param int[] $ids
     * @return array<int,array{rec_ID:int,rec_Title:string,rec_RecTypeID:int}>
     */
    private function loadHeaders(array $ids): array
    {
        $headers = array();
        foreach(array_chunk($ids, self::HEADER_CHUNK_SIZE) as $chunk){
            if(empty($chunk)){ continue; }
            $sql = 'SELECT rec_ID,rec_RecTypeID,rec_Title FROM Records WHERE rec_ID IN ('
                .implode(',', array_fill(0, count($chunk), '?')).')';
            foreach($this->executor->executeRows($sql, str_repeat('i', count($chunk)), $chunk) as $row){
                $headers[intval($row[0])] = array(
                    'rec_ID' => intval($row[0]),
                    'rec_Title' => (string)$row[2],
                    'rec_RecTypeID' => intval($row[1]),
                );
            }
        }
        $ordered = array();
        foreach($ids as $id){
            $id = intval($id);
            $ordered[] = $headers[$id] ?? array('rec_ID' => $id, 'rec_Title' => '', 'rec_RecTypeID' => 0);
        }
        return $ordered;
    }
}
