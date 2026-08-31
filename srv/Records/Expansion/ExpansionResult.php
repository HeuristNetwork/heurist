<?php
/**
* ExpansionResult.php - Linked-record expansion graph
*
* Stores expanded records, edges, path definitions and concrete traversal
* occurrences used by record and map presentation.
*
* @project Heurist academic knowledge management system
* @package Records\Expansion
* @link https://HeuristNetwork.org
* @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author Artem Osmakov <osmakov@gmail.com>
* @author Ian Johnson <ian.johnson.heurist@gmail.com>
* @since 7.0
*/
declare(strict_types=1);
namespace Heurist\Records\Expansion;

/** Mutable graph assembled during one expansion execution. */
final class ExpansionResult
{
    private array $records=array();
    private array $edges=array();
    private array $paths=array();
    private array $topNodes=array();
    private array $occurrences=array();

    /** Add or enrich one participating record. */
    public function addRecord(int $id, ?int $typeId=null, ?string $title=null): void
    {
        if($id<1){return;} $record=array('rec_ID'=>$id);
        if($typeId!==null){$record['rec_RecTypeID']=$typeId;} if($title!==null){$record['rec_Title']=$title;}
        $this->records[$id]=array_merge($this->records[$id]??array(),$record);
    }

    /** Add one distinct directed graph edge. */
    public function addEdge(array $edge): void
    {
        $parts=array(intval($edge['source']??0),intval($edge['target']??0),intval($edge['field']??0),intval($edge['relationship']??0));
        if($parts[0]<1||$parts[1]<1){return;}
        $this->edges[implode(':',$parts)]=array_filter(array('source'=>$parts[0],'target'=>$parts[1],
            'field'=>$parts[2]?:null,'relationship'=>$parts[3]?:null),static fn($value)=>$value!==null);
    }

    /** Register a compact path definition. */
    public function addPath(string $id,string $path): void {$this->paths[$id]=$path;}

    /** Associate an expanded node with its originating top record. */
    public function associate(int $topId,int $nodeId): void {$this->topNodes[$topId][$nodeId]=$nodeId;}

    /** Preserve one concrete record chain for linked value attachment. */
    public function addOccurrence(int $topId,string $pathId,array $recordIds): void
    {
        $recordIds=array_values(array_filter(array_map('intval',$recordIds)));
        $key=$topId.':'.$pathId.':'.implode(',',$recordIds);
        $this->occurrences[$key]=array('top'=>$topId,'path'=>$pathId,'recordIds'=>$recordIds);
    }

    /** Return traversal occurrences, optionally restricted to one path. */
    public function getOccurrences(?string $pathId=null): array
    {
        $items=array_values($this->occurrences); if($pathId===null){return $items;}
        return array_values(array_filter($items,static fn($item)=>($item['path']??null)===$pathId));
    }

    /** Return compact path definitions. */
    public function getPaths(): array {return $this->paths;}

    /** Return the stable controller/presenter graph representation. */
    public function toArray(): array
    {
        return array('records'=>array_values($this->records),'edges'=>array_values($this->edges),'paths'=>$this->paths);
    }
}
