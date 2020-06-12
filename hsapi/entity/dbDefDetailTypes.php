<?php

    /**
    * db access to sysUGrpps table
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
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


class DbDefDetailTypes extends DbEntityBase
{
/*
    'dty_Documentation'=>5000,
    'dty_EntryMask'=>'text',
    'dty_OriginatingDBID'=>'int',
    'dty_NameInOriginatingDB'=>255,
    'dty_IDInOriginatingDB'=>'int',
  
    'dty_OrderInGroup'=>'int',
    'dty_TermIDTreeNonSelectableIDs'=>1000,
    'dty_FieldSetRectypeID'=>'int',
    'dty_LocallyModified'=>'bool2'
*/

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
        
        $pred = $this->searchMgr->getPredicate('dty_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('dty_Name');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('dty_Type');
        if($pred!=null) array_push($where, $pred);
        
        $pred = $this->searchMgr->getPredicate('dty_Status');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('dty_Modified');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('dty_DetailTypeGroupID');
        if($pred!=null) array_push($where, $pred);
        

        $needCheck = false;
        
        if(@$this->data['details']==null) $this->data['details'] = 'full';
       
        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'dty_ID';
            
        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'dty_ID,dty_Name';
            
        }else if(@$this->data['details']=='list'){

            $this->data['details'] = 'dty_ID,dty_Name,dty_ShowInLists,dty_HelpText,dty_Type,dty_Status,dty_DetailTypeGroupID';
            
        }else if(@$this->data['details']=='full'){

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
        $order = array();
        
        $value = @$this->data['sort:dty_Modified'];
        if($value!=null){
            array_push($order, 'dty_Modified '.($value>0?'ASC':'DESC'));
        }else{
            $value = @$this->data['sort:dty_Type'];
            if($value!=null){
                array_push($order, 'dty_Type '.($value>0?'ASC':'DESC'));
            }else{
                $value = @$this->data['sort:dty_Name'];
                if($value!=null){
                    array_push($order, 'dty_Name '.($value>0?'ASC':'DESC'));
                }else{
                    $value = @$this->data['sort:dty_ID'];
                    if($value!=null){
                        array_push($order, 'dty_ID '.($value>0?'ASC':'DESC'));
                    }
                }
            }
        }         

        //ID field is mandatory and MUST be first in the list
        $idx = array_search('dty_ID', $this->data['details']);
        if($idx>0){
            unset($this->data['details'][$idx]);
            $idx = false;
        }
        if($idx===false){
            array_unshift($this->data['details'],'dty_ID');
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
    //
    //
    public function delete(){

        $this->recordIDs = prepareIds($this->data[$this->primaryField]);

        if(count($this->recordIDs)==0){             
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid field type identificator');
            return false;
        }        
        if(count($this->recordIDs)>1){             
            $this->system->addError(HEURIST_INVALID_REQUEST, 'It is not possible to remove field types in batch');
            return false;
        }        
        
        $dtyID = $this->recordIDs[0];
        
        $query = 'select count(dtl_ID) from recDetails where dtl_DetailTypeID='.$dtyID;
        $dtCount = mysql__select_value($this->system->get_mysqli(), $query);
        
        if($dtCount>0){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 
                "You cannot delete field type $dtyID as it is used $dtCount times in the data");
            return false;
        }

        return parent::delete();        
    }
 
 
    //
    // validate permission for edit record type
    // for delete and assign see appropriate methods
    //    
    protected function _validatePermission(){
        
        if(!$this->system->is_admin() && (count($this->recordIDs)>0 || count($this->records)>0)){ //there are records to update/delete
            
            $this->system->addError(HEURIST_REQUEST_DENIED, 
                    'You are not admin and can\'t edit field types. Insufficient rights for this operation');
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
            $mysqli = $this->system->get_mysqli();
            $res = mysql__select_value($mysqli,
                    "SELECT dty_ID FROM ".$this->config['tableName']."  WHERE dty_Name='"
                    .$mysqli->real_escape_string( $this->records[$idx]['dty_Name'])."'");
            if($res>0 && $res!=@$this->records[$idx]['dty_ID']){
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'Field type cannot be saved. The provided name already exists');
                return false;
            }
            
            //$query = $query.", dty_LocallyModified=IF(dty_OriginatingDBID>0,1,0)"
            if(@$this->records[$idx]['dty_LocallyModified']==null){
                $this->records[$idx]['dty_LocallyModified'] = 0; //default value for new
            }
            if(@$this->records[$idx]['dty_IDInOriginatingDB']==''){
                $this->records[$idx]['dty_IDInOriginatingDB'] = 0;
            }

            $this->records[$idx]['dty_Modified'] = date('Y-m-d H:i:s'); //reset

            $this->records[$idx]['is_new'] = (!(@$this->records[$idx]['dty_ID']>0));
        }
        
        return $ret;
        
    }     
}
?>
