<?php

    /**
    * db access to usrReminders table
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
require_once (dirname(__FILE__).'/db_files.php');


class DbUsrReminders extends DbEntityBase
{

    /**
    *  search usrReminders
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
                
        $this->searchMgr = new dbEntitySearch( $this->system, $this->fields);
        
        $res = $this->searchMgr->validateParams( $this->data );
        if(!is_bool($res)){
            $this->data = $res;
        }else{
            if(!$res) return false;        
        }        
        
        $needCheck = false;
        
        //compose WHERE 
        $where = array();
        $from_table = array($this->config['tableName']);
        
        $pred = $this->searchMgr->getPredicate('rem_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('rem_OwnerUGrpID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('rem_RecID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('rem_ToWorkgroupID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('rem_ToUserID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('rem_ToEmail');
        if($pred!=null) array_push($where, $pred);
        

        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'rem_ID';
            
        }else if(@$this->data['details']=='name' || @$this->data['details']=='list' || @$this->data['details']=='full'){

            $this->data['details'] = 'rem_ID,rem_RecID,rem_OwnerUGrpID,rem_ToWorkgroupID,rem_ToUserID,rem_ToEmail,rem_Message,rem_StartDate,rem_Freq';
            
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
        $order = array();
        array_push($order, 'rem_Modified DESC');
        
        
        $is_ids_only = (count($this->data['details'])==1);
            
        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT '.implode(',', $this->data['details'])
        .' FROM '.implode(',', $from_table);

         if(count($where)>0){
            $query = $query.' WHERE '.implode(' AND ',$where);
         }
         if(count($order)>0){
            $query = $query.' ORDER BY '.implode(',',$order);
         }
         
         $query = $query.$this->searchMgr->getOffset()
                        .$this->searchMgr->getLimit();

        $calculatedFields = null;
        
        $result = $this->searchMgr->execute($query, $is_ids_only, $this->config['tableName'], $calculatedFields);
        
        return $result;
    }
    
    //
    // validate permission for edit tag
    // for delete and assign see appropriate methods
    //    
    protected function _validatePermission(){
        
        if(!$this->system->is_dbowner() && count($this->recordIDs)>0){ //there are records to update/delete
            
            $ugrID = $this->system->get_user_id();
            
            $mysqli = $this->system->get_mysqli();
             
            $recIDs_norights = mysql__select_list($mysqli, $this->config['tableName'], $this->primaryField, 
                    'rem_ID in ('.implode(',', $this->recordIDs).') AND rem_OwnerUGrpID!='.$ugrID);
            
            $cnt = count($recIDs_norights);       
                    
            if($cnt>0){
                $this->system->addError(HEURIST_ACTION_BLOCKED, 
                (($cnt==1 && (!isset($this->records) || count($this->records)==1))
                    ? 'Reminder belongs'
                    : $cnt.' Reminders belong')
                    .' to other user. Insufficient rights for this operation');
                return false;
            }
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
            $rec_ID = intval(@$record[$this->primaryField]);
            $isinsert = ($rec_ID<1);
            if($isinsert && !($this->records[$idx]['tag_UGrpID']>0)){
                $this->records[$idx]['rem_OwnerUGrpID'] = $this->system->get_user_id();
            }
            $this->records[$idx]['rem_Modified'] = null; //reset
        }

        return $ret;
        
    }    
    
    //
    // batch action for reminders - email sending 
    //
    public function batch_action(){
        
        //tags ids
        $this->recordIDs = prepareIds($this->data['remIDs']);
        if(count($this->recordIDs)==0){             
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of remainder identificators');
            return false;
        }
        
        //record ids
        $recordIDs = prepareIds($this->data['recIDs']);
        if(count($assignIDs)==0){             
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of record identificators');
            return false;
        }
        
        
        return true;
    }
    
}
?>
