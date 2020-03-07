<?php

    /**
    * db access to defRecStructure.php table
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2019 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

require_once (dirname(__FILE__).'/../System.php');
require_once (dirname(__FILE__).'/dbEntityBase.php');
require_once (dirname(__FILE__).'/dbEntitySearch.php');


class DbDefRecStructure extends DbEntityBase
{

    /**
    *  search user or/and groups
    * 
    *  sysUGrps.ugr_ID
    *  sysUGrps.ugr_Type
    *  sysUGrps.ugr_Name
    *  sysUGrps.ugr_Enabled
    *  sysUGrps.ugr_Modified
    *  sysUsrGrpLinks.ugl_UserID
    *  sysUsrGrpLinks.ugl_GroupID
    *  sysUsrGrpLinks.ugl_Role
    *  (omit table name)
    * 
    *  other parameters :
    *  details - id|name|list|all or list of table fields
    *  offset
    *  limit
    *  request_id
    * 
    *  @todo overwrite
    */
    public function search(){

        if(parent::search()===false){
            return false;   
        }

        //compose WHERE 
        $where = array(); 
        $from_table = array($this->config['tableName']);   

        $pred = $this->searchMgr->getPredicate('rst_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('rst_RecTypeID');
        if($pred!=null) array_push($where, $pred);


        $needCheck = false;

        if(@$this->data['details']==null) $this->data['details'] = 'full';

        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){

            $this->data['details'] = 'rst_ID';

        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'rst_ID,rst_DisplayName';

        }else if(@$this->data['details']=='list' || @$this->data['details']=='full'){
            //all fields from configuration json

            $this->data['details'] = implode(',', $this->fieldNames) ;
        }else{
            $needCheck = true;
        }

        if(!is_array($this->data['details'])){ //specific list of fields
            $this->data['details'] = explode(',', $this->data['details']);
        }

        //validate names of fields
        if($needCheck && !$this->_validateFieldsForSearch()){
            return false;
        }

        //----- order by ------------
        //compose ORDER BY
        $order = array('rst_DisplayOrder ASC');

        //ID field is mandatory and MUST be first in the list
        $idx = array_search('rst_ID', $this->data['details']);
        if($idx>0){
            unset($this->data['details'][$idx]);
            $idx = false;
        }
        if($idx===false){
            array_unshift($this->data['details'],'rst_ID');
        }

        $is_ids_only = (count($this->data['details'])==1);

        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS  '.implode(',', $this->data['details'])
        .' FROM '.implode(',', $from_table);

        if(count($where)>0){
            $query = $query.' WHERE '.implode(' AND ',$where);
        }
        if(count($order)>0){
            $query = $query.' ORDER BY '.implode(',',$order);
        }

        $query = $query.$this->searchMgr->getLimit().$this->searchMgr->getOffset();


        $calculatedFields = null;

        $result = $this->searchMgr->execute($query, $is_ids_only, $this->config['entityName'], $calculatedFields);

        return $result;

    }

    //
    // validate permission for edit record type
    // for delete and assign see appropriate methods
    //    
    protected function _validatePermission(){

        if(!$this->system->is_admin() && (count($this->recordIDs)>0 || count($this->records)>0)){ //there are records to update/delete

            $this->system->addError(HEURIST_REQUEST_DENIED, 
                'You are not admin and can\'t edit record type structure. Insufficient rights for this operation');
            return false;
        }

        return true;
    }     

    //
    //
    //    
    protected function prepareRecords(){

        $ret = parent::prepareRecords();

        //add specific field values
        foreach($this->records as $idx=>$record){

            //find real rst_ID 
            $mysqli = $this->system->get_mysqli();
                
            $row = mysql__select_row_assoc($mysqli,
                'SELECT rst_ID, rst_OriginatingDBID FROM '.$this->config['tableName']
                .' WHERE rst_DetailTypeID='.$mysqli->real_escape_string( $this->records[$idx]['rst_DetailTypeID'])
                .' AND rst_RecTypeID='.$mysqli->real_escape_string( $this->records[$idx]['rst_RecTypeID']) );

            $isInsert = !(@$row['rst_ID']>0);

            if($isInsert){
                $this->records[$idx]['rst_ID'] = -1;
                $this->records[$idx]['rst_LocallyModified'] = 0;
            }else{
                $this->records[$idx]['rst_ID'] = $row['rst_ID'];
                $this->records[$idx]['rst_LocallyModified'] = ($row['rst_OriginatingDBID']>0)?1:0;
            }

            $this->records[$idx]['rst_Modified'] = date('Y-m-d H:i:s'); //reset

            $this->records[$idx]['is_new'] = $isInsert;
        }

        return $ret;

    }    

    public function save(){
        
        $results = parent::save();
        if($results!==false){
            $results = array();
            foreach($this->records as $rec_idx => $record){
                $results[] = $this->records[$rec_idx]['rst_DetailTypeID'];
            }
        }
        return $results;    
    } 
    
}
?>
