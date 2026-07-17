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
     * Only limit and offset are supported in addition to these two filters.
     *
     * @return array|false Standard internal entity-search result.
     */
    public function search(){

        if(parent::search()===false){
            return false;
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
