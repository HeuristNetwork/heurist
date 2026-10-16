<?php
/** Builds labeled term forests from database definitions without per-term queries. */
namespace hserv\entity;

final class TermLinkTree
{
    /** Validate a single ID, comma list or PHP query-array; deduplicate in request order. */
    public static function ids($value): array {
        $values = is_array($value) ? $value : explode(',', (string)$value);
        $ids = array();
        foreach($values as $id){
            if(!is_scalar($id) || !preg_match('/^[1-9][0-9]*$/D', trim((string)$id))
                    || filter_var(trim((string)$id), FILTER_VALIDATE_INT) === false){
                throw new \InvalidArgumentException('termId must contain positive integer IDs');
            }
            $ids[intval($id)] = intval($id);
        }
        if(count($ids)>1000){ throw new \InvalidArgumentException('At most 1000 term IDs are supported'); }
        return array_values($ids);
    }

    /**
     * termId mode retains requested terms and real ancestor paths only.
     * parentId mode follows all links, including references, below one parent.
     * Missing IDs are omitted; dangling parents produce a partial root.
     */
    public static function build(array $rows, array $ids, bool $descendants, array $links = array()): array {
        $terms = array();
        foreach($rows as $row){
            $id = intval($row['trm_ID']);
            $terms[$id] = array('id'=>$id, 'label'=>(string)$row['trm_Label'],
                'parentId'=>intval($row['trm_ParentTermID']) ?: null);
        }
        $children = array();
        $roots = array();
        if($descendants){
            foreach($terms as $id=>$term){
                if($term['parentId']){ $children[$term['parentId']][$id] = $id; }
            }
            foreach($links as $link){
                $children[intval($link['trl_ParentID'])][intval($link['trl_TermID'])] = intval($link['trl_TermID']);
            }
            foreach($ids as $id){ if(isset($terms[$id])){ $roots[$id] = $id; } }
        }else{
            $visited = array();
            foreach($ids as $id){
                $path = array();
                while(isset($terms[$id])){
                    if(isset($path[$id])){ throw new \RuntimeException('Cycle in real term parent hierarchy'); }
                    if(isset($visited[$id])){ break; }
                    $path[$id] = true;
                    $parent = $terms[$id]['parentId'];
                    if(!$parent || !isset($terms[$parent])){ $roots[$id] = $id; break; }
                    $children[$parent][$id] = $id;
                    $id = $parent;
                }
                $visited += $path;
            }
        }
        $sort = function($ids) use ($terms){
            $ids = array_values(array_filter($ids, function($id) use ($terms){ return isset($terms[$id]); }));
            usort($ids, function($a, $b) use ($terms){
                return strnatcasecmp($terms[$a]['label'], $terms[$b]['label']) ?: ($a <=> $b);
            });
            return $ids;
        };
        $count = 0;
        $walk = function($id, $path) use (&$walk, &$count, $terms, $children, $sort){
            if(count($path)>=128 || ++$count>100000){
                throw new \RuntimeException('Term hierarchy exceeds the tree response safety limit');
            }
            $path[$id] = true;
            $node = $terms[$id];
            $node['children'] = array();
            foreach($sort($children[$id] ?? array()) as $child){
                // References may lead back to an ancestor. Never follow cycles.
                if(!isset($path[$child])){ $node['children'][] = $walk($child, $path); }
            }
            return $node;
        };
        $forest = array();
        foreach($sort($roots) as $root){ $forest[] = $walk($root, array()); }
        return $forest;
    }
}
