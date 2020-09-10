<?php

    /**
    * db access to usrUGrps table for workgroups
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
require_once (dirname(__FILE__).'/../dbaccess/db_records.php'); //for recordDelete
require_once (dirname(__FILE__).'/../dbaccess/db_files.php');


class DbSysGroups extends DbEntityBase
{

    /**
    *  search groups
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
        
        $needCheck = false;
        $needRole = false;
        $needCount = false;  //find members count
        $is_ids_only = false;

        //compose WHERE 
        $where = array('ugr_Type="workgroup"');
        $from_table = array($this->config['tableName']);
        
        $pred = $this->searchMgr->getPredicate('ugr_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('ugr_Name');
        if($pred!=null) array_push($where, $pred);
        
        //find groups where this user is member or admin
        $pred = $this->searchMgr->getPredicate('ugl_UserID');
        if($pred!=null) {

                $needRole = true;
                $where2 = array();                                
                array_push($where2, $pred);
                $pred = $this->searchMgr->getPredicate('ugl_Role');
                if($pred!=null) {
                    array_push($where2, $pred);
                }
                array_push($where2, '(ugl_GroupID = ugr_ID)');
            
                if(@$this->data['ugl_Join']){ //always search for role
                    
                    $from_table[0] = $from_table[0].' LEFT JOIN sysUsrGrpLinks ON '.implode(' AND ',$where2);
                    
                }else{
                    $where = array_merge($where,$where2);
                    array_push($from_table, 'sysUsrGrpLinks');
                }
        }
        
        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'ugr_ID';
            $is_ids_only = true;
            
        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'ugr_ID,ugr_Name';
          
        }else if(@$this->data['details']=='count'){
            
            $this->data['details'] = 'ugr_ID';
            $needCount = true;

        }else if(@$this->data['details']=='list' || @$this->data['details']=='full'){

            $this->data['details'] = 'ugr_ID,ugr_Name,ugr_LongName,ugr_Description,ugr_Enabled';
            if($needRole) {
                $this->data['details'] .= ',ugl_Role';   
            }
            $needCount = true;
            
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
        
        $value = @$this->data['sort:ugr_Modified'];
        if($value!=null){
            array_push($order, 'ugr_Modified '.($value>0?'ASC':'DESC'));
        }else{
            $value = @$this->data['sort:ugr_Members'];
            if($value!=null){
                array_push($order, 'ugr_Members '.($value>0?'ASC':'DESC'));
                $needCount = true;
            }else{
                $value = @$this->data['sort:ugr_ID'];
                if($value!=null){
                    array_push($order, 'ugr_ID '.($value>0?'ASC':'DESC'));
                }else{
                    $value = @$this->data['sort:ugr_Name'];
                    if($value!=null){
                        array_push($order, 'ugr_Name ASC');
                    }
                }
            }
        }  
        
        //$is_ids_only = (count($this->data['details'])==1);

        if($needCount){    
            array_push($this->data['details'],
                '(select count(*) from sysUsrGrpLinks where (ugl_GroupID=ugr_ID)) as ugr_Members');
        }
            
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
    // validate permission for edit tag
    // for delete and assign see appropriate methods
    //    
    protected function _validatePermission(){
        
        if(!$this->system->is_dbowner() && count($this->recordIDs)>0){ //there are records to update/delete
            
            $ugrID = $this->system->get_user_id();
            
            $mysqli = $this->system->get_mysqli();
                                                           
            $recIDs_norights = mysql__select_list($mysqli, $this->config['tableName'].',sysUsrGrpLinks', 
                $this->primaryField, 
                    '( usr_ID in ('.implode(',', $this->recordIDs)
                    .') ) AND ( ugl_GroupID=ugr_ID ) AND ( ugl_Role=\'admin\' ) AND ugl_UserID!='.$ugrID);
            
            
            $cnt = count($recIDs_norights);       
                    
            if($cnt>0){
                $this->system->addError(HEURIST_REQUEST_DENIED, 
                    'You are not an admin of group. Insufficient rights for this operation');
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
            $this->records[$idx]['ugr_Type'] = 'workgroup';
            $this->records[$idx]['ugr_Modified'] = date('Y-m-d H:i:s'); //reset
            $this->records[$idx]['ugr_Password'] = 'PASSWORD NOT REQUIRED';
            $this->records[$idx]['ugr_eMail'] = 'EMAIL NOT SET FOR '.$this->records[$idx]['ugr_Name'];
            
            
            //validate duplication
            $mysqli = $this->system->get_mysqli();
            $res = mysql__select_value($mysqli,
                    "SELECT ugr_ID FROM sysUGrps  WHERE ugr_Name='"
                    .$mysqli->real_escape_string( $this->records[$idx]['ugr_Name'])."'");
            if($res>0 && $res!=@$this->records[$idx]['ugr_ID']){
                $this->system->addError(HEURIST_ACTION_BLOCKED, 
                        'Workgroup cannot be saved. The provided name already exists');
                return false;
            }
            
        }

        return $ret;
        
    }    
        
    //
    // add current user as admin for new group
    //
    public function save(){

        $ret = parent::save();
   
        if($ret!==false){

            //treat group image
            foreach($this->records as $record){
                $group_ID = @$record['ugr_ID'];
                if($group_ID && in_array($group_ID, $ret)){
                    $thumb_file_name = @$record['ugr_Thumb'];
            
                    //rename it to recID.png
                    if($thumb_file_name){
                        parent::renameEntityImage($thumb_file_name, $group_ID);
                    }
                    
                    if(!in_array($group_ID, $this->recordIDs )){ //add current user as admin for new group
                            
                        $admin_role = array();
                        $admin_role['ugl_GroupID'] = $group_ID;
                        $admin_role['ugl_UserID'] = $this->system->get_user_id();
                        $admin_role['ugl_Role'] = 'admin';
                        $res = mysql__insertupdate($this->system->get_mysqli(), 'sysUsrGrpLinks', 'ugl', $admin_role);
                        
                        //$fname = HEURIST_FILESTORE_DIR.$this->system->get_user_id();
                        //fileSave('X',$fname);  on save
                    }
                }
            }
        }
        
        return $ret;
        
    }     
    
    //
    // delete group
    //
    public function delete($disable_foreign_checks = false){
        
        $this->recordIDs = prepareIds($this->data[$this->primaryField]);
        if(in_array(1, $this->recordIDs)){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Cannot remove "Database Owners" group');
            return false;
        }
        $mysqli = $this->system->get_mysqli();
        
        //check for existing records
        $query = 'SELECT count(rec_ID) FROM Records WHERE rec_OwnerUGrpID in (' 
                        . implode(',', $this->recordIDs) . ') AND rec_FlagTemporary=0 limit 1';
        $res = mysql__select_value($mysqli, $query);
        if($res>0){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Deleting Group with existing Records not allowed');
            return false;
        }
        
        $keep_autocommit = mysql__begin_transaction($mysqli);
        
        //remove temporary records
        $query = 'SELECT rec_ID FROM Records WHERE rec_OwnerUGrpID in (' 
                        . implode(',', $this->recordIDs) . ') and rec_FlagTemporary=1';
        $rec_ids_to_delete = mysql__select_list2($mysqli, $query);
        if(count($rec_ids_to_delete)>0){
            $res = recordDelete($this->system, $rec_ids_to_delete, false);
            if(@$res['status']!=HEURIST_OK) return false;
        }

        $ret = true;
        
        //find affected users 
        $query = 'SELECT ugl_UserID FROM sysUsrGrpLinks'
            . ' WHERE ugl_GroupID in (' . implode(',', $this->recordIDs) . ')';
        $affectedUserIds = mysql__select_list2($mysqli, $query);            
        
        //remove from roles table
        $query = 'DELETE FROM sysUsrGrpLinks'
            . ' WHERE ugl_GroupID in (' . implode(',', $this->recordIDs) . ')';
        
        $res = $mysqli->query($query);
        if(!$res){
            $this->system->addError(HEURIST_DB_ERROR,
                            'Cannot remove entries from user/group links (sysUsrGrpLinks)',
                            $mysqli->error );
            $ret = false;
        }   
        $query = 'DELETE FROM usrSavedSearches  WHERE svs_UGrpID in (' . implode(',', $this->recordIDs) . ')';
        $res = $mysqli->query($query);
        $query = 'DELETE FROM usrTags  WHERE tag_UGrpID in (' . implode(',', $this->recordIDs) . ')';
        $res = $mysqli->query($query);
        $query = 'DELETE FROM usrRecPermissions  WHERE rcp_UGrpID in (' . implode(',', $this->recordIDs) . ')';
        $res = $mysqli->query($query);
        
        if($ret){
            $ret = parent::delete();
        }

        if($ret){
            $mysqli->commit();
            
            if(count(@$affectedUserIds)>0)
            foreach($affectedUserIds as $usrID)  //affected users
            if($usrID!=$this->system->get_user_id()){
                    $fname = $this->getEntityImagePath($usrID);
                    if(file_exists($fname)){
                        unlink($fname);
                    }
            }
            
            //update user groups for current user
            //$this->system->updateSessionForUser( $this->system->get_user_id() );
            //@todo   $groups = reloadUserGroups(get_user_id());
            //@todo   updateSessionForUser(get_user_id(), 'user_access', $groups);
        }else{
            $mysqli->rollback();
        }
        if($keep_autocommit===true) $mysqli->autocommit(TRUE);

        return $ret;
    }
    
    //
    // batch action for groups - add/remove users to/from group
    // parameters 
    // groupID  - affected group
    // userIDs  - user roles to be changed
    // role - remove admin member
    //
    public function batch_action(){
        
        if(!in_array(@$this->data['role'],array('remove','admin','member'))){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid parameter "role"');
            return false;
        }
        
        //group ids
        $this->recordIDs = prepareIds(@$this->data['groupID']);
        if(count($this->recordIDs)==0){             
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid workgroup identificator');
            return false;
        }
        
        //user ids
        $assignIDs = prepareIds(@$this->data['userIDs']);
        if(count($assignIDs)==0){             
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid user identificators');
            return false;
        }
        
        if(!$this->_validatePermission()){
            return false;
        }
        
        $mysqli = $this->system->get_mysqli();
        
        $ret = true;
        
        
        //group cannot be without admin. 
        if($this->data['role']=='remove' || $this->data['role']=='member'){
            
            //verification
            foreach ($this->recordIDs as $groupID){
                foreach ($assignIDs as $usrID){
                    
                    $query = 'SELECT count(g2.ugl_ID) FROM sysUsrGrpLinks AS g2 LEFT JOIN sysUsrGrpLinks AS g1 '
                                .'ON g1.ugl_GroupID=g2.ugl_GroupID AND g2.ugl_Role="admin" '                             //is it the only admin
                                .'WHERE g1.ugl_UserID='.$usrID.' AND g1.ugl_Role="admin" AND g1.ugl_GroupID='.$groupID;  //is this user admin
                                
                    //can't remove last admin
                    $cnt = mysql__select_value($mysqli, $query);
                    if($cnt==1){
                        $this->system->addError(HEURIST_ACTION_BLOCKED, 
                            'It is not possible to '.(($this->data['role']=='remove')?'remove':' change role to" member" for')
                            .' user #'.$usrID.' from group #'.$groupID.'. This user is the only admin of the workgroup');
                        return false;
                    }
                }
            }
            
        }
        
        
        $keep_autocommit = mysql__begin_transaction($mysqli);
            
        $query = 'DELETE FROM sysUsrGrpLinks'
            . ' WHERE ugl_GroupID in (' . implode(',', $this->recordIDs) . ')'
            . ' AND ugl_UserID in (' . implode(',', $assignIDs) . ')';
            
        $res = $mysqli->query($query);
        if(!$res){
            $this->system->addError(HEURIST_DB_ERROR, 'Can\'t remove users from workgroup', $mysqli->error );
            $ret = false;
        }
            
        if($this->data['role']!='remove'){
                
            foreach ($this->recordIDs as $groupID){
                $query = array();
                foreach ($assignIDs as $usrID){
                    array_push($query, ' ('. $groupID .' , '. $usrID .', "'.$this->data['role'].'")');
                }    
                $query = 'INSERT INTO sysUsrGrpLinks (ugl_GroupID, ugl_UserID, ugl_Role) VALUES '
                        .implode(',', $query);
                $res = $mysqli->query($query);
                if(!$res){
                    $ret = false;
                    $mysqli->rollback();
                    $this->system->addError(HEURIST_DB_ERROR, 'Can\'t set role in workgroup #'.$groupID, $mysqli->error );
                    break;
                }
            }//foreach      
            
        }
        if($ret){
            $mysqli->commit();
        
            //????    
            foreach ($assignIDs as $usrID)
            if($usrID!=$this->system->get_user_id()){
                $fname = HEURIST_FILESTORE_DIR.$usrID;
                fileSave('X',$fname); //change role
            }
        }
              
        if($keep_autocommit===true) $mysqli->autocommit(TRUE);
        
        return $ret;
    }
    
}
?>
