<?php

    /**
    * db access to defDetailTypeGroups table
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2015 University of Sydney
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


class DbDefDetailTypeGroups extends DbEntityBase 
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
        
        $this->searchMgr = new DbEntitySearch( $this->system, $this->fields );


        $res = $this->searchMgr->validateParams( $this->data );
        if(!is_bool($res)){
            $this->data = $res;
        }else{
            if(!$res) return false;        
        }        
        
        //compose WHERE 
        $where = array();    
        
        $pred = $this->searchMgr->getPredicate('dtg_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('dtg_Name');
        if($pred!=null) array_push($where, $pred);

       
        //compose SELECT it depends on param 'details' ------------------------
        //@todo - take it form fiels using some property
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'dtg_ID';
            
        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'dtg_ID,dtg_Name';
            
        }else if(@$this->data['details']=='list'){

            $this->data['details'] = 'dtg_ID,dtg_Name,dtg_Description,dtg_Order,'
            .'(select count(dty_ID) from defDetailTypes where dtg_ID=dty_DetailTypeGroupID) as dtg_FieldCount';
            
        }else if(@$this->data['details']=='full'){
            
            $fields2 = $this->fields;
            unset($fields2['dtg_FieldCount']);

            $this->data['details'] = implode(',', $fields2 )
             .'(select count(dty_ID) from defdetailtypes where dtg_ID=dty_DetailTypeGroupID) as dtg_FieldCount';
        }
        
        if(!is_array($this->data['details'])){ //specific list of fields
            $this->data['details'] = explode(',', $this->data['details']);
        }
        
        /*validate names of fields
        foreach($this->data['details'] as $fieldname){
            if(!@$this->fields[$fieldname]){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Invalid field name ".$fieldname);
                return false;
            }            
        }*/

        //ID field is mandatory and MUST be first in the list
        $idx = array_search('dtg_ID', $this->data['details']);
        if($idx>0){
            unset($this->data['details'][$idx]);
            $idx = false;
        }
        if($idx===false){
            array_unshift($this->data['details'],'dtg_ID');
        }
        $is_ids_only = (count($this->data['details'])==1);
            
        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT '.implode(',', $this->data['details'])
        .' FROM '.$this->config['tableName'];

         if(count($where)>0){
            $query = $query.' WHERE '.implode(' AND ',$where);
         }
         $query = $query.$this->searchMgr->getOffset()
                        .$this->searchMgr->getLimit();
        
//error_log($query);     

        $res = $this->searchMgr->execute($query, $is_ids_only, $this->config['entityName']);
        return $res;
    }
    
    //
    //
    //
    public function delete(){

        $this->recordIDs = prepareIds($this->data['recID']);

        if(count($this->recordIDs)==0){             
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of identificators');
            return false;
        }        
        
        $query = 'select count(dty_ID) from defDetailTypes where dtg_ID in ('.implode(',', $this->recordIDs).')';
        $ret = mysql__select_value($mysqli, $query);
        
        if($ret>0){
            $this->system->addError(HEURIST_ERROR, 'Cannot delete non empty group');
            return false;
        }

        return parent::delete();        
    }

}
?>
