<?php

    /**
    * db access to dbDefCalcFunctions table
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

require_once (dirname(__FILE__).'/../System.php');
require_once (dirname(__FILE__).'/dbEntityBase.php');
require_once (dirname(__FILE__).'/dbEntitySearch.php');


class DbDefCalcFunctions extends DbEntityBase 
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
        
        $pred = $this->searchMgr->getPredicate('cfn_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('cfn_Name');
        if($pred!=null) array_push($where, $pred);

        if(@$this->data['details']==null) $this->data['details'] = 'full';//default
               
        //compose SELECT it depends on param 'details' ------------------------
        //@todo - take it form fiels using some property
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'cfn_ID';
            
        //}else if(@$this->data['details']=='title'){
            
            //$this->data['details'] = 'cfn_Name';
            
        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'cfn_ID,cfn_Name';
            
        }else if(@$this->data['details']=='list' || @$this->data['details']=='full'){

            $this->data['details'] = 'cfn_ID,cfn_Name,cfn_FunctionSpecification,cfn_RecTypeIDs';
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
        $idx = array_search('cfn_ID', $this->data['details']);
        if($idx>0){
            unset($this->data['details'][$idx]);
            $idx = false;
        }
        if($idx===false){
            array_unshift($this->data['details'],'cfn_ID');
        }
        $is_ids_only = (count($this->data['details'])==1);
            
        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS  '.implode(',', $this->data['details'])
        .' FROM '.$this->config['tableName'];

        if(count($where)>0){
            $query = $query.' WHERE '.implode(' AND ',$where);
        }
        $query = $query.' ORDER BY cfn_Name '.$this->searchMgr->getLimit()
                        .$this->searchMgr->getOffset();
        

        $res = $this->searchMgr->execute($query, $is_ids_only, $this->config['entityName']);
        return $res;
    }
    
    //
    //
    //
    public function delete($disable_foreign_checks = false){

        $this->recordIDs = prepareIds($this->data[$this->primaryField]);

        if(count($this->recordIDs)==0){             
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of identificators');
            return false;
        }        
        
        $query = 'select count(rst_DetailTypeID) from defRecStructure where rst_CalcFunctionID in ('.implode(',', $this->recordIDs).')';
        $ret = mysql__select_value($this->system->get_mysqli(), $query);
        
        if($ret>0){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Cannot delete calculation that in use in '.$ret.' record types');
            return false;
        }

        return parent::delete();        
    }

    
    //
    // validate permission
    //    
    protected function _validatePermission(){
        
        if(!$this->system->is_admin() &&
            ((is_array($this->recordIDs) && count($this->recordIDs)>0) 
            || (is_array($this->records) && count($this->records)>0))){ //there are records to update/delete
            
            $this->system->addError(HEURIST_REQUEST_DENIED, 
                    'You are not admin and can\'t edit field calculations. Insufficient rights (logout/in to refresh) for this operation '
                        .$this->system->get_user_id().'  '.print_r($this->system->getCurrentUser(), true));
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

            //validate duplication
            if(@$this->records[$idx]['cfn_Name']){
                $mysqli = $this->system->get_mysqli();
                $res = mysql__select_value($mysqli,
                        "SELECT cfn_ID FROM ".$this->config['tableName']."  WHERE cfn_Name='"
                        .$mysqli->real_escape_string( $this->records[$idx]['cfn_Name'] )."'");
                if($res>0 && $res!=@$this->records[$idx]['cfn_ID']){
                    $this->system->addError(HEURIST_ACTION_BLOCKED, 'Field calculation cannot be saved. The provided name already exists');
                    return false;
                }
            }

            $this->records[$idx]['cfn_Modified'] = date('Y-m-d H:i:s'); //reset
            
            $this->records[$idx]['is_new'] = (!(@$this->records[$idx]['cfn_ID']>0));
        }
        
        return $ret;
        
    }       
}
?>
