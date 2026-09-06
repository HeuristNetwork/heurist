<?php
/**
* DbDefTermsLinks.php - Read-only access to defTermsLinks
*
* @project     Heurist academic knowledge management system
* @package Entity
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.4
*/
namespace hserv\entity;

use hserv\entity\DbEntityBase;

/**
* Provides read-only API access to explicit and implicit term links.
*
* Supported filters:
* - parentId / trl_ParentID: links belonging to a parent term or vocabulary.
* - termId / trl_TermID: parents to which a term is linked.
*/
class DbDefTermsLinks extends DbEntityBase
{
    /**
     * Returns term-link pairs with optional parent and/or term filtering.
     * tree=true returns labeled ancestor forests or full parent subtrees;
     * otherwise the existing direct-link pairs and pagination are unchanged.
     *
     * @return array|false Standard internal entity-search result.
     */
    public function search(){

        if(parent::search()===false){
            return false;
        }

        if(isset($this->data['tree'])){
            $tree = filter_var($this->data['tree'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if($tree === null){
                $this->system->addError(HEURIST_INVALID_REQUEST, 'tree must be a boolean');
                return false;
            }
            if($tree){ return $this->searchTree(); }
        }

        $where = array();

        if(isset($this->data['trl_ParentID']) && $this->data['trl_ParentID']!==''){
            if(!isPositiveInt($this->data['trl_ParentID'])){
                $this->system->addError(HEURIST_INVALID_REQUEST, 'Wrong parameter parentId');
                return false;
            }
            $where[] = 'trl_ParentID='.intval($this->data['trl_ParentID']);
        }

        if(isset($this->data['trl_TermID']) && $this->data['trl_TermID']!==''){
            if(!isPositiveInt($this->data['trl_TermID'])){
                $this->system->addError(HEURIST_INVALID_REQUEST, 'Wrong parameter termId');
                return false;
            }
            $where[] = 'trl_TermID='.intval($this->data['trl_TermID']);
        }

        $query = 'SELECT SQL_CALC_FOUND_ROWS trl_ParentID, trl_TermID FROM defTermsLinks';
        if(!empty($where)){
            $query .= SQL_WHERE.implode(SQL_AND, $where);
        }
        $query .= ' ORDER BY trl_ParentID, trl_TermID';
        $query .= $this->searchMgr->getLimit().$this->searchMgr->getOffset();

        $mysqli = $this->system->getMysqli();
        $result = $mysqli->query($query);
        if(!$result){
            $this->system->addError(HEURIST_DB_ERROR, 'Search error', $mysqli->error);
            return false;
        }

        $records = array();
        $order = array();
        $rowKey = 0;

        while($row = $result->fetch_row()){
            $row = array(intval($row[0]), intval($row[1]));
            $records[$rowKey] = $row;
            $order[] = $rowKey;
            $rowKey++;
        }
        $result->close();

        return array(
            'queryid' => @$this->data['request_id'],
            'entityName' => $this->config['entityName'],
            'pageno' => @$this->data['pageno'],
            'offset' => @$this->data['offset'],
            'count' => mysql__found_rows($mysqli),
            'reccount' => count($records),
            'records' => $records,
            'order' => $order,
            'fields' => array('trl_ParentID', 'trl_TermID')
        );
    }

    /** Return complete trees, paginating vocabulary roots rather than their nodes. */
    private function searchTree(){
        require_once __DIR__.'/TermLinkTree.php';
        $parent = $this->data['trl_ParentID'] ?? null;
        $term = $this->data['trl_TermID'] ?? null;
        try{
            if(($parent === null) === ($term === null)){
                throw new \InvalidArgumentException('tree requires exactly one of termId or parentId');
            }
            $ids = TermLinkTree::ids($parent ?? $term);
            if($parent !== null && count($ids)!==1){
                throw new \InvalidArgumentException('parentId must be a single positive integer');
            }
        }catch(\InvalidArgumentException $e){
            $this->system->addError(HEURIST_INVALID_REQUEST, $e->getMessage());
            return false;
        }
        $mysqli = $this->system->getMysqli();
        $result = $mysqli->query('SELECT trm_ID, trm_Label, trm_ParentTermID FROM defTerms');
        if(!$result){
            $this->system->addError(HEURIST_DB_ERROR, 'Term tree search error', $mysqli->error);
            return false;
        }
        $terms = $result->fetch_all(MYSQLI_ASSOC);
        $result->close();
        $links = array();
        if($parent !== null){
            $result = $mysqli->query('SELECT trl_ParentID, trl_TermID FROM defTermsLinks');
            if(!$result){
                $this->system->addError(HEURIST_DB_ERROR, 'Term tree search error', $mysqli->error);
                return false;
            }
            $links = $result->fetch_all(MYSQLI_ASSOC);
            $result->close();
        }
        try{
            $trees = TermLinkTree::build($terms, $ids, $parent !== null, $links);
        }catch(\RuntimeException $e){
            $this->system->addError(HEURIST_DB_ERROR, $e->getMessage());
            return false;
        }
        $total = count($trees);
        $offset = max(0, intval($this->data['offset'] ?? 0));
        $limit = max(1, intval($this->data['limit'] ?? 1000));
        $records = array_map(function($tree){
            return array($tree['id'], $tree['label'], $tree['parentId'], $tree['children']);
        }, array_slice($trees, $offset, $limit));
        return array(
            'queryid'=>$this->data['request_id'] ?? null,
            'entityName'=>$this->config['entityName'],
            'offset'=>$offset, 'count'=>$total, 'reccount'=>count($records),
            'records'=>$records, 'order'=>array_keys($records),
            'fields'=>array('id', 'label', 'parentId', 'children')
        );
    }

    /** Term links are read-only through this entity. */
    public function save(){
        return false;
    }

    /** Term links are read-only through this entity. */
    public function delete($disable_foreign_checks=false){
        return false;
    }
}
?>
