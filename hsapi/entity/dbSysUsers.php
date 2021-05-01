<?php

    /**
    * db access to usrUGrps table for users
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
require_once (dirname(__FILE__).'/../dbaccess/db_files.php');
require_once (dirname(__FILE__).'/../dbaccess/db_records.php'); //for recordDelete
require_once (dirname(__FILE__).'/../dbaccess/db_users.php'); //send email methods


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

        $not_in_group = @$this->data['not:ugl_GroupID'];
                
        if(parent::search()===false){
              return false;   
        }
        
        $needCheck = false;
        $needRole = false;
        $needCount = false; //find cound where user is a member
        
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
        else if (@$this->data['needRole']){ //not used
                $needRole = true;
                array_push($where, '(ugl_UserID = ugr_ID)');
                array_push($from_table, 'sysUsrGrpLinks');
        }
        
        //special case - users must not be in given group
        
        if($not_in_group>0){
            array_push($where, '(ugr_ID NOT IN (SELECT ugr_ID FROM sysUGrps,sysUsrGrpLinks '
                .'WHERE (ugl_UserID = ugr_ID) AND (ugl_GroupID='.$not_in_group.') ))');
        }
        
        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'ugr_ID';
            
        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'ugr_ID,ugr_Name';

        }else if(@$this->data['details']=='count'){
            
            $this->data['details'] = 'ugr_ID';
            $needCount = true;
            
        }else if(@$this->data['details']=='list'){
            
            $this->data['details'] = 'ugr_ID,ugr_Name,ugr_FirstName,ugr_LastName,ugr_eMail,ugr_Organisation,ugr_Enabled';
            if($needRole) {
                $this->data['details'] .= ',ugl_Role';   
            }else{
                $needCount = true;  //need count only for all groups
            } 
            
        }else if(@$this->data['details']=='full'){

            $this->data['details'] = 'ugr_ID,ugr_Name,ugr_FirstName,ugr_LastName,ugr_eMail,ugr_Department,ugr_Organisation,'
            .'ugr_City,ugr_State,ugr_Postcode,ugr_Interests,ugr_Enabled';
            
            if($needRole){
                $this->data['details'] .= ',ugl_Role';  
            }else{
                $needCount = true;  //need count only for all groups
            } 
            
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
            array_push($order, 'ugr_Modified '.($value>0?'ASC':'DESC'));
        }else{
            $value = @$this->data['sort:ugr_LastName'];
            if($value!=null){
                array_push($order, 'ugr_LastName '.($value>0?'ASC':'DESC'));
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
         
        if($needCount){ //find count of groups where given user is a memmber   
            array_push($this->data['details'],
                '(select count(ugl_ID) from sysUsrGrpLinks where (ugl_UserID=ugr_ID)) as ugr_Member');
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
    // validate permission for edit tag
    // for delete and assign see appropriate methods
    //    
    protected function _validatePermission(){
        
        if(!$this->system->is_admin() && (count($this->recordIDs)>0 || count($this->records)>0)){ //there are records to update/delete
            
            $ugrID = $this->system->get_user_id();
            if($this->recordIDs[0]!=$ugrID || count($this->recordIDs)>1){
                
                $this->system->addError(HEURIST_REQUEST_DENIED, 
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

        //@todo captcha validation for registration
        
        //add specific field values
        foreach($this->records as $idx=>$record){
            $this->records[$idx]['ugr_Type'] = 'user';
            $this->records[$idx]['ugr_Modified'] = date('Y-m-d H:i:s'); //reset
            
            //add password by default
            if(@$this->records[$idx]['ugr_Password']==''){
                unset($this->records[$idx]['ugr_Password']);

            }else if(@$this->records[$idx]['ugr_Password']){

                $tmp_password = $this->records[$idx]['ugr_Password'];
                $s = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./';
                $salt = $s[rand(0, strlen($s)-1)] . $s[rand(0, strlen($s)-1)];
                $this->records[$idx]['ugr_Password'] = crypt($tmp_password, $salt);
                $this->records[$idx]['tmp_password'] = $tmp_password;

            }
            if(!@$this->records[$idx]['ugr_Name']){
                $this->records[$idx]['ugr_Name'] = $this->records[$idx]['ugr_eMail'];
            }
            if(!$this->system->is_admin() && (!@$this->records[$idx]['ugr_ID'] || $this->records[$idx]['ugr_Type']<0)){
                $this->records[$idx]['ugr_Enabled'] = 'n';
            }else if(!($this->records[$idx]['ugr_Enabled']=='n' || $this->records[$idx]['ugr_Enabled']=='y')){
                $this->records[$idx]['ugr_Enabled'] = 'n';    
            }

            //validate duplication
            $mysqli = $this->system->get_mysqli();
            $res = mysql__select_value($mysqli,
                    "SELECT ugr_ID FROM sysUGrps  WHERE ugr_Name='"
                    .$mysqli->real_escape_string( $this->records[$idx]['ugr_Name'])."'");
            if($res>0 && $res!=@$this->records[$idx]['ugr_ID']){
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'User cannot be saved. The provided name already exists');
                return false;
            }
            $res = mysql__select_value($mysqli,
                    "SELECT ugr_ID FROM sysUGrps  WHERE ugr_eMail='"
                    .$mysqli->real_escape_string( $this->records[$idx]['ugr_eMail'])."'");
            if($res>0 && $res!=@$this->records[$idx]['ugr_ID']){
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'User cannot be saved. The provided email already exists');
                return false;
            }
            
            //find records to be approved and new ones
            if($this->system->is_admin() && "y"==@$this->records[$idx]['ugr_Enabled'] && @$this->records[$idx]['ugr_ID']>0){
                $res = mysql__select_value($mysqli,
                         'SELECT ugr_Enabled FROM sysUGrps WHERE ugr_LoginCount=0 AND ugr_Type="user" AND ugr_ID='
                                .$this->records[$idx]['ugr_ID']);
                if($res=='n'){
                    $this->records[$idx]['is_approvement'] = true;
                }
            }
            $this->records[$idx]['is_new'] = (!(@$this->records[$idx]['ugr_ID']>0));
            
        }
        
        return $ret;
        
    }    

    //      
    //
    //
    public function save(){


        $ret = parent::save();

       
        if($ret!==false){
            
            foreach($this->records as $idx=>$record){
                $ugr_ID = @$record['ugr_ID'];
                if($ugr_ID>0 && in_array($ugr_ID, $ret)){
                    
                    //treat user image
                    $thumb_file_name = @$record['ugr_Thumb'];
                    //rename it to recID.png
                    if($thumb_file_name){
                        parent::renameEntityImage($thumb_file_name, $ugr_ID);
                    }
                    
                    //add user to specified group
                    if($record['ugl_GroupID']>0){
                        $group_role = array();
                        $group_role['ugl_GroupID'] = $record['ugl_GroupID'];
                        $group_role['ugl_UserID'] = $ugr_ID;
                        $group_role['ugl_Role'] = 'member';
                        
                        $res = mysql__insertupdate($this->system->get_mysqli(), 'sysUsrGrpLinks', 'ugl', $group_role);
                        
                        //$fname = HEURIST_FILESTORE_DIR.$ugr_ID;   //save special semaphore file to trigger user refresh for other users
                        //fileSave('X',$fname);  //add to group ???
                    }
                    
                    //send approvement or registration email
                    $rv = true;
                    $is_new = (@$this->records[$idx]['is_new']===true);
                    $is_approvement = (@$this->records[$idx]['is_approvement']===true);
                    if($is_new && $this->system->get_user_id()<1){ //this is independent registration of new user
                        $rv = user_EmailAboutNewUser($this->system, $ugr_ID);
                    }else if($is_new || $is_approvement){ //this is approvement or registration FOR user
                        $rv = user_EmailApproval($this->system, $ugr_ID, $this->records[$idx]['tmp_password'], $is_approvement);
                        
                        user_SyncCommonCredentials($this->system,  $ugr_ID, $is_approvement);
                    }
                    if(!$rv){
                        //cannot sent email 
                        //return false;
                    }
                    
                }
            }
        }        
        return $ret;
    }  
            
    //
    //
    //
    public function delete($disable_foreign_checks = false){
        
        $this->recordIDs = prepareIds($this->data[$this->primaryField]);
        if(in_array(2, $this->recordIDs)){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Cannot remove "Database Owner" user');
            return false;
        }
        
        $mysqli = $this->system->get_mysqli();

        //check for last admin        
        foreach($this->recordIDs as $usrID){
        
            $query = 'SELECT g2.ugl_GroupID, count(g2.ugl_ID) AS adm FROM sysUsrGrpLinks AS g2 LEFT JOIN sysUsrGrpLinks AS g1 '
                        .'ON g1.ugl_GroupID=g2.ugl_GroupID AND g2.ugl_Role="admin" '                        //is it the only admin
                        .'WHERE g1.ugl_UserID='.$usrID.' AND g1.ugl_Role="admin" GROUP BY g1.ugl_GroupID  HAVING adm=1';  //is this user admin
                        
            //can't remove last admin
            $res = mysql__select_row($mysqli, $query);
            if($res!=null){
                $this->system->addError(HEURIST_ACTION_BLOCKED, 
                    'It is not possible to remove  user #'.$usrID.'. This user is the only admin of the workgroup #'.$res[0]);
                return false;
            }
        
        }
        
        //check for existing records
        $query = 'SELECT count(rec_ID) FROM Records WHERE rec_OwnerUGrpID in (' 
                        . implode(',', $this->recordIDs) . ') AND rec_FlagTemporary=0 limit 1';
        $res = mysql__select_value($mysqli, $query);
        if($res>0){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Deleting user with existing Records not allowed');
            return false;
        }
        
        //---------------------------------------------------
        
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
        
        //remove from roles table
        $query = 'DELETE FROM sysUsrGrpLinks'
            . ' WHERE ugl_UserID in (' . implode(',', $this->recordIDs) . ')';
        
        $res = $mysqli->query($query);
        if(!$res){
            $this->system->addError(HEURIST_DB_ERROR,
                            'Cannot remove entries from user/group links (sysUsrGrpLinks)',
                            $mysqli->error );
            $ret = false;
        }        
        
        $query = 'DELETE FROM usrHyperlinkFilters  WHERE hyf_UGrpID in (' . implode(',', $this->recordIDs) . ')';
        $res = $mysqli->query($query);
        $query = 'DELETE FROM usrRemindersBlockList  WHERE rbl_UGrpID in (' . implode(',', $this->recordIDs) . ')';
        $res = $mysqli->query($query);
        $query = 'DELETE FROM usrSavedSearches  WHERE svs_UGrpID in (' . implode(',', $this->recordIDs) . ')';
        $res = $mysqli->query($query);
        $query = 'DELETE FROM usrTags  WHERE tag_UGrpID in (' . implode(',', $this->recordIDs) . ')';
        $res = $mysqli->query($query);
                                  
        if($ret){
            $ret = parent::delete();
        }
        
        //@todo - remove assosiated images

        if($ret){
            $mysqli->commit();
        }else{
            $mysqli->rollback();
        }
        if($keep_autocommit===true) $mysqli->autocommit(TRUE);

        return $ret;
    }
	
	/*
     * Transfer User ID 2 (DB Owner) to the selected User ID, provide the new DB Owner administrator rights to all workgroups
     */
    private function transferOwner($disable_foreign_checks = false){

        /* General Variables */
        $mysqli = $this->system->get_mysqli();  // MySQL connection
        $return = true; // Control variable
        $recID = $this->data['ugr_ID'];     // Selected User ID
        
        if(!is_numeric($recID)){    /* Check if ID is a number */
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Provided ID is Invalid');
            return false;
        }
        settype($recID, "integer");     /* For Comparison and Use */
        if($recID == 2){   /* Check if selected ID is alreayd the Owner */
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Cannot transfer Database Ownership to the current Database Owner');
            return false;
        }

        /* Retrieve an un-used value for MAXINT, a temporary value used for swapping the two IDs */
        $query = 'SELECT max(ugr_ID)+1 FROM sysUGrps';
        $res = $mysqli->query($query);
        $row = $res->fetch_row();
        if($row == null){
            $this->system->addError(HEURIST_DB_ERROR, 'Unable to set MAXINT for sysUGrps.ugr_ID');
            return false;
        }
        $MAXINT = $row[0];

        /* Start Transaction, allow for rollback incase of errors */
        $keep_autocommit = mysql__begin_transaction($mysqli);
        
        /* Remove all groups associated with the selected User's ID */
        $query = "DELETE FROM sysUsrGrpLinks WHERE ugl_UserID = " . $recID;
        $mysqli->query($query);
        if ($mysqli->affected_rows < 0){
            $this->system->addError(HEURIST_DB_ERROR, 'Cannot remove old workgroups from ID: ' . $recID);
            $return = false;
        }


        /* Swapping IDs between DB Owner and Selected User, as all of these values are primary keys we need to ensure that everything completes correctly */
        $query = "UPDATE sysUGrps SET ugr_ID = " . $MAXINT . " WHERE ugr_ID = 2";
        $mysqli->query($query);
        if($mysqli->affected_rows <= 0){
            $this->system->addError(HEURIST_DB_ERROR, 'Cannot set current Owner\'s ID to MAXINT');
            $return = false;
        }
        $query = "UPDATE sysUGrps SET ugr_ID = 2 WHERE ugr_ID = " . $recID;
        $mysqli->query($query);
        if($mysqli->affected_rows <= 0){
            $this->system->addError(HEURIST_DB_ERROR, 'Cannot set new Owner\'s ID to 2');
            $return = false;
        }
        $query = "UPDATE sysUGrps SET ugr_ID = " . $recID . " WHERE ugr_ID = " . $MAXINT;
        $mysqli->query($query);
        if($mysqli->affected_rows <= 0){
            $this->system->addError(HEURIST_DB_ERROR, 'Cannot set original Owner\'s ID to ' . $recID);
            $return = false;
        }


        /* Retrieve List of Workgroup IDs */
        $query = "SELECT ugr_ID FROM sysUGrps WHERE ugr_Type = 'workgroup'";
        $res = $mysqli->query($query);

        /* Add new DB Owner as admin to retrieved list of IDs */
        $query = "INSERT INTO sysUsrGrpLinks (ugl_UserID, ugl_Role, ugl_GroupID) VALUES ";
        if($res->num_rows > 0){
            while($row = $res->fetch_assoc()){
                if($row['ugr_ID'] == 0) continue;
                $query = $query . "(2, 'admin', " . $row['ugr_ID'] . "), ";
            }
        }else{
            $this->system->addError(HEURIST_DB_ERROR, 'Cannot retrieve group ids');
            $return = false;
        }
        $query = substr($query, 0, -2); /* Remove last characters, space and comma */
        $mysqli->query($query);
        if ($mysqli->affected_rows <= 0){
            $this->system->addError(HEURIST_DB_ERROR, 'Cannot assign new owner\'s administrator privilege for each group');
            $return = false;
        }

        /* Check if everything has been successful */
        if($return){
            $mysqli->commit();
        }else{
            $mysqli->rollback();
        }    

        if($keep_autocommit===true) $mysqli->autocommit(TRUE);

        return $return;
    }
    
    //
    // special and batch action for users
    // 1) import users from another db
    // 2) transfer database ownership to another user
    //
    public function batch_action($ignore_permissions=false){

        if(!$ignore_permissions && !$this->system->is_admin()){ 
            $this->system->addError(HEURIST_REQUEST_DENIED, 
                'You are not admin and can\'t add/edit other users. Insufficient rights for this operation');
            return false;
        }
        
        if($this->data['transferOwner']){
            return $this->transferOwner();
        }
        
        //import users from another db
        
        // validate that current user is admin in source database as well
        $sytem_source = new System();
        if(!$sytem_source->init($this->data['sourceDB'], true, false)){
            return false;
        }

        /* @todo
        if(!$sytem_source->is_admin()){ 
            $this->system->addError(HEURIST_REQUEST_DENIED, 
                'You are not admin in source database '.$sytem_source->dbname_full().'. Insufficient rights for this operation');
            return false;
        }
        */

        //user ids
        $userIDs = prepareIds(@$this->data['userIDs']);
        if(count($userIDs)==0){             
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid user identificators');
            return false;
        }
        //group roles 
        $roles = @$this->data['roles'];
        if(!$roles || count($roles)==0){             
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Group roles for import users are not defined');
            return false;
        }
        
        $exit_if_exists = (@$this->data['exit_if_exists']!=0); //proceed even user already exists
        
        $mysqli = $this->system->get_mysqli();
        
        //find users that are already in this database
        $query = 'SELECT distinct src.ugr_ID, dest.ugr_ID from '
            .$sytem_source->dbname_full().'.sysUGrps as src, sysUGrps as dest'
            .' where src.ugr_ID in ('.implode(',',$userIDs)
            .') and ((src.ugr_eMail = dest.ugr_eMail) OR (src.ugr_Name=dest.ugr_Name))';       
        
        $userIDs_already_exists = mysql__select_assoc2($mysqli, $query);
        
        if($exit_if_exists && count($userIDs_already_exists)==count($userIDs)){
            $this->system->addError(HEURIST_NOT_FOUND, 
                'It appears that all users selected to import exist in current database.');
            return false;
        }
        if(count($userIDs_already_exists)>0)
            $userIDs = array_diff($userIDs, array_keys($userIDs_already_exists));
        
        $keep_autocommit = mysql__begin_transaction($mysqli);

        //1. import users from another database
        $fields = "ugr_Type,ugr_Name,ugr_LongName,ugr_Description,ugr_Password,ugr_eMail,".
                    "ugr_FirstName,ugr_LastName,ugr_Department,ugr_Organisation,ugr_City,".
                    "ugr_State,ugr_Postcode,ugr_Interests,ugr_Enabled,ugr_LastLoginTime,".
                    "ugr_MinHyperlinkWords,ugr_LoginCount,ugr_IsModelUser,".
                    "ugr_IncomingEmailAddresses,ugr_TargetEmailAddresses,ugr_URLs,ugr_FlagJT";

        $query1 = "insert into sysUGrps ($fields) ".
                    "SELECT $fields ".
                    "FROM ".$sytem_source->dbname_full().".sysUGrps where ugr_ID=";

                    
        $newUserIDs = array();                    
                    
        $ret = true;            
        foreach($userIDs as $userID){
            $res = $mysqli->query($query1.$userID);
            if($res){
                 $userID_new = $mysqli->insert_id;    
                 $newUserIDs[] = $userID_new;

                //copy user image 
                $user_image = parent::getEntityImagePath($userID, null, $this->data['sourceDB']); //in source db
                if(file_exists($user_image)){
                    
                    $extension = pathinfo($user_image, PATHINFO_EXTENSION);
                    
                    $user_image_new = parent::getEntityImagePath($userID_new,null,null,$extension); //in this database
                    
                    fileCopy($user_image, $user_image_new);
                    
                    $user_thumb = parent::getEntityImagePath($userID, 'thumb', $this->data['sourceDB']); //in source db
                    $user_thumb_new = parent::getEntityImagePath($userID_new, 'thumb'); //in this db
                    if(file_exists($user_thumb)){
                        fileCopy($user_thumb, $user_thumb_new);    
                    }                        
                }
                
            }else{
                $this->system->addError(HEURIST_DB_ERROR,'Can\'t import users from '.$this->data['sourceDB'], $mysqli->error);
                $ret = false;
                break;
            }
        }
                    
        //2. apply roles 
        if($ret){

            foreach ($roles as $groupID=>$role){
                $values = array();
                $remove = null;
                foreach ($newUserIDs as $usrID){
                    array_push($values, ' ('. $groupID .' , '. $usrID .', "'.$role.'")');
                }    
                if(count($userIDs_already_exists)>0){ //apply for user that already exists
                    $remove = array_values($userIDs_already_exists);
                    foreach ($userIDs_already_exists as $srcID=>$usrID){
                        array_push($values, ' ('. $groupID .' , '. $usrID .', "'.$role.'")');
                    }    
                }
                
                if(count($remove)>0){
                    $query = 'DELETE FROM sysUsrGrpLinks WHERE ugl_GroupID='.$groupID.' AND ugl_UserID in ('
                            .implode(',',$remove).')';
                    $res = $mysqli->query($query);
                    if(!$res){
                        $ret = false;
                        $this->system->addError(HEURIST_DB_ERROR, 'Can\'t remove roles for existing users in workgroup #'.$groupID, $mysqli->error );
                        break;
                    }                            
                }
                
                $query = 'INSERT INTO sysUsrGrpLinks (ugl_GroupID, ugl_UserID, ugl_Role) VALUES '
                .implode(',', $values);

                $res = $mysqli->query($query);
                if(!$res){
                    $ret = false;
                    $this->system->addError(HEURIST_DB_ERROR, 'Can\'t set role in workgroup #'.$groupID, $mysqli->error );
                    break;
                }                            
                
            }
        }
        
        if($ret){
            $mysqli->commit();
            
            $ret = 'Users imported: '.count($newUserIDs);
            if (count($userIDs_already_exists)>0){
                $ret = $ret.'. Users already exists: '.count($userIDs_already_exists);
            }
        }else{
            $mysqli->rollback();
        }
        if($keep_autocommit===true) $mysqli->autocommit(TRUE);

        return $ret;
    }    
}
?>
