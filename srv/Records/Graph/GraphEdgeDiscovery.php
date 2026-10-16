<?php
/**
* GraphEdgeDiscovery.php - Internal-edge discovery for the initial graph
*
* Finds recLinks edges whose two endpoints both belong to the seed result set.
* It never introduces external records. Explicit link specs are matched by
* detail type (lt/lf) or relation type (rt/rf); "all" mode returns every
* internal edge. Both modes stop at the effective edge budget and report
* whether the result was truncated.
*
* @project     Heurist academic knowledge management system
* @package     Records\Graph
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);
namespace Heurist\Records\Graph;

use Heurist\Records\Query\QueryExecutor;

/** Set-bounded recLinks traversal for initial graph construction. */
final class GraphEdgeDiscovery
{
    private const CHUNK_SIZE = 500;

    /** @var QueryExecutor */
    private $executor;

    public function __construct(QueryExecutor $executor)
    {
        $this->executor = $executor;
    }

    /**
     * @param int[] $recordIds Seed result-set IDs. Both endpoints must be here.
     * @param array<int,array<string,mixed>>|null $linkDefs Parsed link specs, or null for "all".
     * @param int $edgeLimit Effective edge budget.
     * @return array{edges:array<int,array<string,mixed>>,truncated:bool}
     */
    public function discover(array $recordIds, ?array $linkDefs, int $edgeLimit): array
    {
        $recordIds = array_values(array_unique(array_filter(array_map('intval', $recordIds), static function($id){
            return $id > 0;
        })));
        if(empty($recordIds) || $edgeLimit < 1){
            return array('edges' => array(), 'truncated' => false);
        }
        $idSet = array_fill_keys($recordIds, true);
        $edges = array();
        $truncated = false;

        if($linkDefs === null){
            $truncated = $this->collect($edges, $recordIds, $idSet, null, null, $edgeLimit);
            return array('edges' => array_values($edges), 'truncated' => $truncated);
        }

        foreach($linkDefs as $def){
            if(count($edges) >= $edgeLimit){ $truncated = true; break; }
            $linkKey = isset($def['key']) ? (string)$def['key'] : null;
            if($this->collect($edges, $recordIds, $idSet, $def, $linkKey, $edgeLimit)){
                $truncated = true;
            }
        }
        return array('edges' => array_values($edges), 'truncated' => $truncated);
    }

    /**
     * Append internal edges for one link definition (or all links when null).
     *
     * @return bool True when the edge budget stopped collection early.
     */
    private function collect(
        array &$edges,
        array $recordIds,
        array $idSet,
        ?array $def,
        ?string $linkKey,
        int $edgeLimit
    ): bool {
        $options = array('side' => 'source');
        if($def !== null && !empty($def['relation'])){
            $options['kind'] = 'relationship';
            if(!empty($def['relationTypeId'])){ $options['relationTypes'] = array(intval($def['relationTypeId'])); }
        }elseif($def !== null){
            $options['kind'] = 'pointer';
            if(!empty($def['detailTypeId'])){ $options['fields'] = array(intval($def['detailTypeId'])); }
        }
        foreach(array_chunk($recordIds, self::CHUNK_SIZE) as $chunk){
            foreach($this->executor->records()->links($chunk, $options) as $row){
                $source = $row[2];
                $target = $row[3];
                if($source < 1 || $target < 1 || !isset($idSet[$target])){ continue; }
                $field = $row[4];
                $relationship = $row[5];
                $id = $source.':'.$target.':'.$field.':'.$relationship
                    .($linkKey !== null ? ':'.$linkKey : '');
                if(isset($edges[$id])){ continue; }
                if(count($edges) >= $edgeLimit){ return true; }
                $edges[$id] = array(
                    'id' => $id,
                    'source' => $source,
                    'target' => $target,
                    'field' => $field ?: null,
                    'relationship' => $relationship ?: null,
                    'link' => $linkKey,
                    'path' => null,
                );
            }
        }
        return false;
    }
}
