<?php

    /**
    * db access to usrUGrps table for users
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


class DbSysUsers extends DbEntityBase
{

    /**
    *  search users
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
        $needRole = false;
        
        //compose WHERE 
        $where = array('ugr_Type="user"');
        $from_table = array($this->config['tableName']);
        
        $pred = $this->searchMgr->getPredicate('ugr_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('ugr_Name');
        if($pred!=null) array_push($where, $pred);
        
        $pred = $this->searchMgr->getPredicate('ugr_Enabled');
        if($pred!=null) array_push($where, $pred);
        
        //find users belong to group
        $pred = $this->searchMgr->getPredicate('ugl_GroupID');
        if($pred!=null) {
            
                array_push($where, $pred);
                $needRole = true;
            
                $pred = $this->searchMgr->getPredicate('ugl_Role');
                if($pred!=null) {
                    array_push($where, $pred);
                }
            
                array_push($where, '(ugl_UserID = ugr_ID)');
                array_push($from_table, 'sysUsrGrpLinks');
        }
        
        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'ugr_ID';
            
        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'ugr_ID,ugr_Name';
            
        }else if(@$this->data['details']=='list'){
            
            $this->data['details'] = 'ugr_ID,ugr_Name,ugr_FirstName,ugr_LastName,ugr_eMail,ugr_Organisation,ugr_Enabled';
            if($needRole) $this->data['details'] .= ',ugl_Role';
            
        }else if(@$this->data['details']=='full'){

            $this->data['details'] = 'ugr_ID,ugr_Name,ugr_FirstName,ugr_LastName,ugr_eMail,ugr_Department,ugr_Organisation,'
            .'ugr_City,ugr_State,ugr_Postcode,ugr_Interests,ugr_Enabled';
            
            if($needRole) $this->data['details'] .= ',ugl_Role';
            
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
        
        //exclude ugr_Password form sending to clientr side
        $idx = array_search('ugr_Password', $this->data['details']);
        if($idx>0){
            unset($this->data['details'][$idx]);
        }

        //----- order by ------------
        //compose ORDER BY 
        $order = array();
        
        $value = @$this->data['sort:ugr_Modified'];
        if($value!=null){
            array_push($order, 'ugr_Modified '.($value>1?'ASC':'DESC'));
        }  
        
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
        
        if(!$this->system->is_admin() && count($this->recordIDs)>0){ //there are records to update/delete
            
            $ugrID = $this->system->get_user_id();
            if($this->recordIDs[0]!=$ugrID || count($this->recordIDs)>1){
                
                $this->system->addError(HEURIST_ACTION_BLOCKED, 
                    'You are not admin and can\'t edit another user. Insufficient rights for this operation');
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
            $this->records[$idx]['ugr_Type'] = 'user';
            $this->records[$idx]['ugr_Modified'] = null; //reset
            
            //add password by default
            if(@$this->records[$idx]['ugr_Password']){
                $tmp_password = $this->records[$idx]['ugr_Password'];
                $s = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./';
                $salt = $s[rand(0, strlen($s)-1)] . $s[rand(0, strlen($s)-1)];
                $this->records[$idx]['ugr_Password'] = crypt($tmp_password, $salt);
            }
            if(!@$this->records[$idx]['ugr_Name']){
                $this->records[$idx]['ugr_Name'] = $this->records[$idx]['ugr_eMail'];
            }
            if(!@$this->records[$idx]['ugr_ID'] || $this->records[$idx]['ugr_Type']<0){
                $this->records[$idx]['ugr_Enabled'] = 'n';
            }
        }

        return $ret;
        
    }    
      
    //
    // add current user as admin for new group
    //
    public function save(){

        $ret = parent::save();

        /*  @todo add user to group if ugl_GroupID is specified   
        if($ret!==false)
        foreach($ret as $usr_ID){  
            
            if(!in_array($usr_ID ,$this->recordIDs )){ //add admin for new group
                    
                $admin_role = array();
                $admin_role['ugl_GroupID'] = $usr_ID;
                $admin_role['ugl_UserID'] = $this->system->get_user_id();
                $admin_role['ugl_Role'] = 'admin';
                
                $res = mysql__insertupdate($this->system->get_mysqli(), 'sysUsrGrpLinks', 'ugl', $admin_role);
                
            }
        }//after save loop
        */
        return $ret;
    }  
            
    //
    //
    //
    public function delete(){
        
        $this->recordIDs = prepareIds($this->data['recID']);
        if(in_array(2, $this->recordIDs)){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Cannot remove "Database Owner" user');
            return false;
        }
        
        $mysqli = $this->system->get_mysqli();
        
        $keep_autocommit = mysql__begin_transaction($mysqli);
        
        $ret = parent::delete();
        
        
        if($ret){
            //remove from roles table
            $query = 'DELETE FROM sysUsrGrpLinks'
                . ' WHERE ugl_UserID in (' . implode(',', $this->recordIDs) . ')';
            
            $res = $mysqli->query($query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR,
                                'Cannot remove entries from user/group links (sysUsrGrpLinks)',
                                $mysqli->error );
                $ret = false;
            }            
        }

        if($ret){
            $mysqli->commit();
        }else{
            $mysqli->rollback();
        }
        if($keep_autocommit===true) $mysqli->autocommit(TRUE);

        return $ret;
    }
    

    
}
?>
