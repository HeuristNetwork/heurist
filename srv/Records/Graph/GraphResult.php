<?php
/**
* GraphResult.php - Renderer-neutral graph document
*
* Self-contained result for one graph request: seed pagination, graph records
* with the fixed header set (rec_ID, rec_Title, rec_RecTypeID), provenance-
* tagged edges, the link and path namespaces, and the effective limit report.
* The client can treat the payload as a complete initial graph or merge it as
* an incremental fragment.
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

/** Mutable graph document assembled during one graph request. */
final class GraphResult
{
    private $query;
    private int $total;
    private int $offset;
    private int $limit;
    private array $records = array();
    private array $edges = array();
    private array $links = array();
    private array $paths = array();
    private array $limits = array();

    public function __construct($query, int $total, int $offset, int $limit)
    {
        $this->query = $query;
        $this->total = max(0, $total);
        $this->offset = max(0, $offset);
        $this->limit = max(1, $limit);
    }

    /** @param array<int,array{rec_ID:int,rec_Title:string,rec_RecTypeID:int}> $records */
    public function setRecords(array $records): void { $this->records = array_values($records); }

    /** @param array<int,array<string,mixed>> $edges */
    public function setEdges(array $edges): void { $this->edges = array_values($edges); }

    /** @param array<string,string> $links Link key => compact spec. */
    public function setLinks(array $links): void { $this->links = $links; }

    /** @param array<string,string> $paths Path key => compact traversal. */
    public function setPaths(array $paths): void { $this->paths = $paths; }

    /** @param array<string,mixed> $limits Effective budget and truncation report. */
    public function setLimits(array $limits): void { $this->limits = $limits; }

    /** Return the stable controller representation. */
    public function toArray(): array
    {
        return array(
            'query' => $this->query,
            'total' => $this->total,
            'offset' => $this->offset,
            'limit' => $this->limit,
            'graph' => array(
                'records' => $this->records,
                'edges' => $this->edges,
                // Always an object on the wire; an associative array already
                // encodes as a JSON object, an empty map needs the cast.
                'links' => empty($this->links) ? new \stdClass() : $this->links,
                'paths' => empty($this->paths) ? new \stdClass() : $this->paths,
                'limits' => $this->limits,
            ),
        );
    }
}
