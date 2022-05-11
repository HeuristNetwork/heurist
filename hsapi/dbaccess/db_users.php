<?php

    /**
    * CRUD for User/Groups (sysUGrps) and User Preferences (from SESSION)
    *
    * user_ - prefix for functions
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

    /*
    define('USERS_TABLE', 'sysUGrps');
    define('USERS_ID_FIELD', 'ugr_ID');
    define('USERS_USERNAME_FIELD', 'ugr_Name');
    define('USERS_PASSWORD_FIELD', 'ugr_Password');
    define('USERS_FIRSTNAME_FIELD', 'ugr_FirstName');
    define('USERS_LASTNAME_FIELD', 'ugr_LastName');
    define('USERS_ACTIVE_FIELD', 'ugr_Enabled');
    define('USERS_EMAIL_FIELD', 'ugr_eMail');
    define('GROUPS_TABLE', 'sysUGrps');
    define('GROUPS_ID_FIELD', 'ugr_ID');
    define('GROUPS_NAME_FIELD', 'ugr_Name');
    define('GROUPS_TYPE_FIELD', 'ugr_Type');
    define('USER_GROUPS_TABLE', 'sysUsrGrpLinks');
    define('USER_GROUPS_USER_ID_FIELD', 'ugl_UserID');
    define('USER_GROUPS_GROUP_ID_FIELD', 'ugl_GroupID');
    define('USER_GROUPS_ROLE_FIELD', 'ugl_Role');
    */

    require_once (dirname(__FILE__).'/../utilities/utils_mail.php');

    /**
    * Get user/group by field value
    *
    * @param mixed $ugr_Name - user name
    */
    function user_getByField($mysqli, $field, $value, $database=null){

        $user = null;
        $value = $mysqli->real_escape_string($value);
        $query = 'select * from '.($database!=null?('`'.$database.'`.'):'').'sysUGrps where '.$field.' = "'.$value.'"';
        $res = $mysqli->query($query);
        if($res){
            $user =$res->fetch_assoc();
            $res->close();
        }
        return $user;

    }

    /**
    * Get user/group by ID
    *
    * @param mixed $ugr_ID - user ID
    */
    function user_getById($mysqli, $ugr_ID){
        return user_getByField($mysqli, 'ugr_ID', $ugr_ID);
    }
    
    function user_getNamesByIds($system, $ugr_IDs){
        
        $ugr_IDs = prepareIds($ugr_IDs);
        if(count($ugr_IDs)>0){
            $mysqli = $system->get_mysqli();
            $query = 'SELECT ugr_ID, IF(ugr_Type=\'workgroup\',ugr_Name,concat(ugr_FirstName, \' \', ugr_LastName)) '
            .' FROM sysUGrps WHERE ugr_ID in ('.implode(',',$ugr_IDs).')';
            return mysql__select_assoc2($mysqli, $query);
        }else{
            $system->addError(HEURIST_INVALID_REQUEST,'User ids are not defined');
            return false;
        }
    }
    

    /**
    * get db owner user or specific field of this user
    *
    * @param mixed $field
    * @return mixed
    */
    function user_getDbOwner($mysqli, $field=null)
    {
        $user = user_getById($mysqli, 2);
        if($user){
            if($field){
                if(@$user[$field]){
                    return $user[$field];
                }
            }else{
                return $user;
            }
        }
        return null;
    }

    /**
    * Generate new passowrd and send it by email
    *
    * @param mixed $system
    * @param mixed $ugr_Name
    */
    function user_ResetPassword($system, $username){
        if($username){
            $mysqli = $system->get_mysqli();
            $user = user_getByField($mysqli, 'ugr_Name', $username);
            if(null==$user) {
                $user = user_getByField($system->get_mysqli(), 'ugr_eMail', $username);   
            }
            
            if(null==$user) {
                $system->addError(HEURIST_NOT_FOUND,  "It is not possible to recover password. Username / email, you specified, not found");

            }else{
                //do not update password if mail is not enabled
                if(!checkSmtp()){
                    $system->addError(HEURIST_SYSTEM_CONFIG, 'Error_Mail_Recovery');
                    return false;
                }

                $new_passwd = generate_passwd();
                $record = array("ugr_ID"=>$user['ugr_ID'], "ugr_Password"=>hash_it($new_passwd) );
                $res= mysql__insertupdate($mysqli, "sysUGrps", "ugr_", $record);
                if(is_numeric($res)>0){

                    //, "From: ".$dbowner_Email
                    $dbowner_Email = user_getDbOwner($mysqli, 'ugr_eMail');
                    
                    $email_title = 'Password reset';
                    $email_text = "Dear ".$user['ugr_FirstName'].",\n\n".
                    "Your Heurist password has been reset.\n\n".
                    "Your username is: ".$user['ugr_Name']."\n".
                    "Your new password is: ".$new_passwd."\n\n".
                    "To change your password go to Profile -> My User Info in the top right menu.\nYou will first be asked to log in with the new password above.\n\n"
                    ."Database Owner: ".$dbowner_Email;

                    
                    $rv = sendEmail($user['ugr_eMail'], $email_title, $email_text);
                    if($rv){
                        return true;
                    }else{
                        $msg = $system->getError();
                        $system->addError(HEURIST_SYSTEM_CONFIG, 'Error_Password_Reset', $msg?@$msg['message']:null);
                    }

                }else{
                    $system->addError(HEURIST_DB_ERROR, 'Cannot update record in database', $res);
                }
            }

        }else{
            $system->addError(HEURIST_INVALID_REQUEST, "Username / email not defined"); //INVALID_REQUEST
        }
        return false;
    }

    /**
    * Update the last login datetime
    *
    * @param mixed $mysqli
    * @param mixed $ugr_ID - user ID
    */
    function user_updateLoginTime($mysqli, $ugr_ID){
        $query = 'update sysUGrps set ugr_LastLoginTime=now(), ugr_LoginCount=ugr_LoginCount+1 where ugr_ID='.$ugr_ID;
        $mysqli->query($query);

        $cnt = $mysqli->affected_rows;
        return $cnt;
    }

    /**
    * Get list of groups for given user
    *
    * @param mixed $mysqli
    * @param mixed $ugr_ID
    * @param mixed $isfull  - if false returns only id and role, otherwise additional fields: name and description
    */
    function user_getWorkgroups($mysqli, $ugr_ID, $isfull=false, $database=null){

        $result = array();

        if($mysqli && intval($ugr_ID))
        {

            $query = 'select ugl_GroupID, ugl_Role '
            .($isfull?', ugr_Name, ugr_Description ':'')
            .' from '.($database!=null?('`'.$database.'`.'):'').'sysUsrGrpLinks '
            .' left join '.($database!=null?('`'.$database.'`.'):'').'sysUGrps grp '
            .' on grp.ugr_ID=ugl_GroupID where '
            .' ugl_UserID='.$ugr_ID
            .' and grp.ugr_Type != "user" order by ugl_GroupID';

            $res = $mysqli->query($query);
            if($res){
                while ($row = $res->fetch_row()) {
                    if($isfull){
                        $id = array_shift($row);
                        $result[$id] = $row;
                    }else{
                        $result[$row[0]] = $row[1];
                    }
                }
                $res->close();
            }
        }
        return $result;
    }

    //@todo verify why it returns db onwer
    function user_getAllWorkgroups($mysqli){
//OR (ugr_ID=2) 
        $query = 'SELECT ugr_ID, ugr_Name FROM sysUGrps WHERE (ugr_Type != "user") ORDER BY ugr_Name';
        $result = mysql__select_assoc2($mysqli, $query);
        
        if($result==null) $result = array();
        
        return $result;
    }
    
    /**
    * Get list of members for given group (this is non admin short info)
    *
    * @param mixed $mysqli
    * @param mixed $ugr_ID
    */
    function user_getWorkgroupMembers($mysqli, $ugr_ID){

        $result = array();

        if($mysqli && intval($ugr_ID)){

            $query = 'select ugl_UserID, ugl_Role, ugr_FirstName, ugr_LastName, ugr_Organisation '
            .' from sysUsrGrpLinks left join sysUGrps usr on usr.ugr_ID=ugl_UserID where '
            .' ugl_GroupID='.$ugr_ID
            .' and usr.ugr_Type = "user" and usr.ugr_Enabled="y" order by ugl_UserID';

            $res = $mysqli->query($query);
            if($res){
                while ($row = $res->fetch_row()) {
                    $id = array_shift($row);
                    $result[$id] = $row;
                }
                $res->close();
            }
        }
        return $result;
    }

    /**
    * Get default set of properties
    */
    function user_getDefaultPreferences(){
        return array(
        "layout_language" => "en",
        "layout_theme" => "heurist",
        'search_result_pagesize' => 100,
        'search_detail_limit' => 2000,
        'userCompetencyLevel' => 2, //'beginner'
        'userFontSize' => 12, //px

        'deriveMapLocation ' => true,

        "edit_open_in_new_window" => false,
        "edit_force_tags" => true,
        "edit_pointer_recentsearch" => true,
        
        'help_on' => true, 
        'optfields' => true,
        'mapcluster_on' => true,
        'searchQueryInBrowser' => true,
        'defaultSearch' => 'sortby:-m'
        );
    }
    
    //@$_SESSION[$system->dbname_full()]['ugr_Groups'] = user_getWorkgroups( $this->mysqli, $userID );

    /**
    * Get set of properties from SESSION and to database
    */
    function user_setPreferences($system, $params){
        
        $mysqli = $system->get_mysqli();
        $ugrID = $system->get_user_id();        
        $dbname = $system->dbname_full();
        
        $exclude = array('a','db','DBGSESSID');
        
        foreach ($params as $property => $value) {
            if(!in_array($property, $exclude)){
                @$_SESSION[$dbname]["ugr_Preferences"][$property] = $value;    
            }
        }
        if($ugrID>0)
        $res = mysql__insertupdate( $mysqli, 'sysUGrps', 'ugr', array(
                    'ugr_ID'=>$ugrID,
                    'ugr_Preferences'=>json_encode($_SESSION[$dbname]["ugr_Preferences"]) ));
    }

    //
    // restore preferences from database and put it into SESSION (see login_verify)
    // to get individual property use $system->user_GetPreference
    //
    function user_getPreferences( $system ){

        $mysqli = $system->get_mysqli();
        $ugrID = $system->get_user_id();        
        if($ugrID>0){
            $res = mysql__select_value( $mysqli, 'select ugr_Preferences from sysUGrps where ugr_ID='.$ugrID);
            if($res!=null && $res!=''){
                $res = json_decode($res, true);
                if($res && count($res)>0){
                    return $res;
                }
            }
        }
        $dbname = $system->dbname_full();
        return(@$_SESSION[$dbname]['ugr_Preferences'])
                    ?$_SESSION[$dbname]['ugr_Preferences']
                    :user_getDefaultPreferences();
    }
    
    
    /**
    *  if user is not enabled and login count=0 - this is approvement operation
    */
    function user_isApprovement( $system, $recID ) {

        $ret = false;

        if($system->is_admin() && $recID>0){
            $row = mysql__select_row($system->get_mysqli(),
                "select ugr_Type, ugr_Enabled, ugr_LoginCount from sysUGrps  where ugr_ID=".$recID);
            $ret = ($row[0]=="user" && $row[1]=="n" && $row[2]==0);
        }

        return $ret;
    }
    
    //
    //
    //
    function user_WorkSet( $system, $params ){

        $res = false;        
        $curr_user_id = $system->get_user_id();
        if($curr_user_id>0){
            
            $mysqli = $system->get_mysqli();
            
            $mysqli->query('DELETE FROM usrWorkingSubsets where wss_OwnerUGrpID='.$curr_user_id);
            if ($mysqli->error) {
                    $system->addError(HEURIST_DB_ERROR, 'Cannot reset user workset. SQL error: '.$mysqli->error);
            }else{
                    $res = true;
            }
                
            if(@$params['clear']!=1){
                
                $recids = @$params['ids'];
                $recids = prepareIds($recids);
                if(is_array($recids) && count($recids)>0){
                    
                    $filename = tempnam(HEURIST_SCRATCHSPACE_DIR, "data");
                    
                    if (!$handle_wr = fopen($filename, 'w')) {
                        $system->addError(HEURIST_ERROR, 'Cannot open file to save workset data: '.$filename);                
                        return false;
                    }
                    
                    foreach($recids as $recid){
                        if (fwrite($handle_wr, $recid.','.$curr_user_id."\n") === FALSE) {
                            $system->addError(HEURIST_ERROR, 'Cannot write workset data to file '.$filename);
                            fclose($handle_wr);
                            if(file_exists($filename)) unlink($filename);
                            return false;
                        }
                    }
                    fclose($handle_wr);
                    
                    if(strpos($filename,"\\")>0){
                        $filename = str_replace("\\","\\\\",$filename);
                    }
                    
                    $mysqli->query('SET GLOBAL local_infile = true');
                    //load file into table  LOCAL
                    $query = "LOAD DATA LOCAL INFILE '".$filename."' INTO TABLE usrWorkingSubsets "
                    //." CHARACTER SET utf8mb4"    
                    ." FIELDS TERMINATED BY ',' "  
                    ." OPTIONALLY ENCLOSED BY  '\"' " 
                    ." LINES TERMINATED BY '\n'"  
                    //." IGNORE 1 LINES
                    ." (wss_RecID ,wss_OwnerUGrpID)";
                    
                    if (!$mysqli->query($query)) {
                        $system->addError(HEURIST_DB_ERROR, 'Unable to import workset data. SQL error: '.$mysqli->error);
                    }else{
                        $res = count($recids);
                    }
                    
                    if(file_exists($filename)) unlink($filename);
                
                }else{
                    $system->addError(HEURIST_INVALID_REQUEST, 'Set of records to be added to user workset is not defined');
                }
            }
            
        }else{
            $system->addError(HEURIST_REQUEST_DENIED);
        }
        
        return $res; 
    }

    //CRUD methods
    function user_Delete($system, $recID){

        $response = array("status"=>HEURIST_UNKNOWN_ERROR, "data"=>"action to be implemented");
    }


    function user_Update($system, $record){

        if (user_Validate($system, $record))
        {
            $recID = intval(@$record['ugr_ID']);
            $rectype = $record['ugr_Type'];
            $is_registration = ($rectype=='user' && $recID<1);

            if($is_registration && $system->get_system('sys_AllowRegistration')==0){

                $system->addError(HEURIST_REQUEST_DENIED, 'Registration is not allowed for current database');

            }else if ($is_registration || $system->has_access($recID)) {

                //do not allow registration if approvement mail cannot be sent
                if($is_registration){
                    if(false && !checkSmtp()){
                        $system->addError(HEURIST_SYSTEM_CONFIG, 'Error_Mail_Registration');
                        return false;
                    }
                    //check capture
                    if (@$_SESSION["captcha_code"] && $_SESSION["captcha_code"] != @$record['ugr_Captcha']) {
                        $system->addError(HEURIST_UNKNOWN_ERROR, 'Are you a bot? Please enter the correct answer to the challenge question');
                        return false;
                    }
                    if (@$_SESSION["captcha_code"]){
                        unset($_SESSION["captcha_code"]);
                    }
                }
                if(@$record['ugr_Captcha']){
                    unset($record['ugr_Captcha']);
                }

                $mysqli = $system->get_mysqli();

                $res = mysql__select_value($mysqli,
                    "select ugr_ID from sysUGrps  where ugr_Name='"
                    .$mysqli->real_escape_string( $record['ugr_Name'])."' or ugr_eMail='"
                    .$mysqli->real_escape_string($record['ugr_eMail'])."'");
                if($res!=$recID){
                    $system->addError(HEURIST_ACTION_BLOCKED, 'The provided name or email already exists');
                    return false;
                }

                $is_approvement = false;
                //encrypt password
                $tmp_password = null;
                if($rectype=='user'){


                    if(@$record['ugr_Password'] && $record['ugr_Password']!=''){
                        $tmp_password = $record['ugr_Password'];
                        $record['ugr_Password'] = hash_it($tmp_password);
                    }else{
                        unset($record['ugr_Password']);
                    }
                    if($system->get_user_id()<1){ //not logged in - always disabled
                        $record['ugr_Enabled'] = "n";
                    }
                    if("y"==@$record['ugr_Enabled']){
                        $is_approvement = user_isApprovement($system, $recID);
                    }

                }

                $res = mysql__insertupdate($mysqli, "sysUGrps", "ugr", $record);
                if(is_numeric($res)>0){

                    $new_recID = $res;

                    //actions on complete
                    if($rectype=='user'){
                        $rv = true;
                        if($recID<1 && $system->get_user_id()<1){
                            $rv = user_EmailAboutNewUser($system, $new_recID);
                        }else if($recID<1 || $is_approvement){
                            $rv = user_EmailApproval($system, $new_recID, $tmp_password, $is_approvement);
                            
                            user_SyncCommonCredentials($system,  $new_recID, $is_approvement);
                        }
                        if(!$rv){
                            return false;
                        }

                    }else if($recID<1){
                        //this is addition of new group
                        //add current user as admin for new group
                        //changeRole($recID, get_user_id(), "admin", null, false, true);
                    }

                    return $res; //returns affected record id
                }else{
                    $system->addError(HEURIST_DB_ERROR, 'Cannot update record in database', $res);
                }
            }else{
                $system->addError(HEURIST_REQUEST_DENIED, 'Operation denied. Not enough rights (logout/in to refresh)');
            }

        }  else {
            //$system->addError(HEURIST_INVALID_REQUEST, "All required fields are not defined");
        }

        return false;
    }

    function user_Validate($system, $record){
        $res = false;

        if(@$record['ugr_Type']=='user'){
            //required fields for user
            $reqs = array('ugr_Name','ugr_eMail','ugr_FirstName','ugr_LastName','ugr_Organisation','ugr_Interests');
            if(intval(@$record['ugr_ID'])<1){
                array_push($reqs, 'ugr_Password');
            }

        }else if (@$record['ugr_Type']=='workgroup'){
            $reqs = array('ugr_Name','ugr_eMail');

        }else{
            $system->addError(HEURIST_INVALID_REQUEST, "Wrong type for usergroup: ".@$record['ugr_Type']);
            return false;
        }

        $missed = array();
        foreach ($reqs as $fld){
            if(!@$record[$fld]){
                array_push($missed, $fld);
            }
        }

        if(count($missed)>0){
            $system->addError(HEURIST_INVALID_REQUEST, "Some required fields are not defined: ".implode(",",$missed));
        }else{
            $res = true;
        }


        return $res;
    }

    //
    // sync (add) user into databases listed in sys_UGrpsDatabase 
    //
    function user_SyncCommonCredentials($system, $userID, $is_approvement){
        
        $dbname_full = $system->dbname_full();
        $mysqli = $system->get_mysqli();
        //1. find sys_UGrpsDatabase in this database
        $linked_dbs = mysql__select_value($mysqli, 'select sys_UGrpsDatabase from sysIdentification');
        if($linked_dbs)
        {
            
            $userEmail = mysql__select_value($mysqli, 'select ugr_eMail from sysUGrps where ugr_ID='.$userID);
            
            $linked_dbs = explode(',', $linked_dbs);
            foreach ($linked_dbs as $ldb){
                if(strpos($ldb, HEURIST_DB_PREFIX)!==0){
                    $ldb = HEURIST_DB_PREFIX.$ldb;
                }
                
                $dbname = mysql__select_value($mysqli, 
                    'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = \''
                        .$mysqli->real_escape_string($ldb).'\'');
                if(!$dbname) continue;
                
                //2. find sys_UGrpsDatabase in linked database - this database must be in list
                $linked_dbs2 = mysql__select_value($mysqli, 'select sys_UGrpsDatabase from '.$ldb.'.sysIdentification');
                if(!$linked_dbs2) continue; //this database is not mutually linked
                $linked_dbs2 = explode(',', $linked_dbs2);
                foreach ($linked_dbs2 as $ldb2){
                    if(strpos($ldb2, HEURIST_DB_PREFIX)!==0){
                        $ldb2 = HEURIST_DB_PREFIX.$ldb2;
                    }
                    if( strcasecmp($dbname_full, $ldb2)==0 ){
                        //yes database is mutually linked
                        //3. find user email in linked database
                        $userEmail_in_linkedDB = mysql__select_value($mysqli, 'select ugr_eMail from '
                                .$ldb.'.sysUGrps where ugr_eMail="'.$userEmail.'"');
                        if(!$userEmail_in_linkedDB){
                            //add new user to linked database

                            $fields = 'ugr_LongName,ugr_Description,ugr_Password,ugr_eMail,'.
                            'ugr_FirstName,ugr_LastName,ugr_Department,ugr_Organisation,ugr_City,'.
                            'ugr_State,ugr_Postcode,ugr_Interests,ugr_Enabled,ugr_LastLoginTime,'.
                            'ugr_MinHyperlinkWords,ugr_IsModelUser,'.  //ugr_LoginCount,
                            'ugr_IncomingEmailAddresses,ugr_TargetEmailAddresses,ugr_URLs,ugr_FlagJT';

                            $query1 = "insert into $ldb.sysUGrps (ugr_Type,ugr_Name,$fields) ".
                            "SELECT ugr_Type,ugr_eMail,$fields ".
                            "FROM sysUGrps where ugr_ID=".$userID;                            


                        }else if($is_approvement){
                            //enable user
                            $query1 = "update $ldb.sysUGrps set ugr_Enabled='y' where ugr_ID=".$userID;                            
                        }

                        $res = $mysqli->query($query1);
                        break;
                    }
                }
            }
        }
        
        
        
    }

    /**
    * Send emails to DB Onwer and  System admin about new user
    */
    function user_EmailAboutNewUser($system, $recID){

        $mysqli = $system->get_mysqli();
        
        $dbowner_Email = user_getDbOwner($mysqli, 'ugr_eMail');
        //$systemAdmin_Email = HEURIST_MAIL_TO_ADMIN;

        $user = user_getById($mysqli, $recID); //find user
        if($user)
        {
            $ugr_Name = $user['ugr_Name'];
            $ugr_FullName = $user['ugr_FirstName'].' '.$user['ugr_LastName'];
            $ugr_Organisation = $user['ugr_Organisation'];
            $ugr_eMail = $user['ugr_eMail'];

            //create email text for admin
            $email_text =
            "There is a Heurist user registration awaiting approval.\n".
            "The user details submitted are:\n".
            "Database name: ".HEURIST_DBNAME."\n".
            "Full name:    ".$ugr_FullName."\n".
            "Email address: ".$ugr_eMail."\n".
            "Organisation:  ".$ugr_Organisation."\n".
            "Go to the address below and navigate in menu Admin > Manage Users to review further details and approve the registration:\n".
            HEURIST_BASE_URL."?db=".HEURIST_DBNAME; //."&recID=$recID&mode=users";

            $email_title = 'User Registration: '.$ugr_FullName.' ['.$ugr_eMail.']';

            $rv = sendEmail($dbowner_Email, $email_title, $email_text);
            if(!$rv){
                $msg = $system->getError();
                $system->addError(HEURIST_SYSTEM_CONFIG, 'Error_Mail_Registration', $msg?@$msg['message']:null);
                return false;
            }
        }else{
                $system->addError(HEURIST_NOT_FOUND, 'User not found');
                return false;
        }
        return true;
    }

    /**
    *   Send approval message to user
    */
    function user_EmailApproval($system, $recID, $tmp_password, $is_approvement){

        $mysqli = $system->get_mysqli();
        
        $dbowner_Email = user_getDbOwner($mysqli, 'ugr_eMail');
        $user = user_getById($mysqli, $recID); //find user
        if($user)
        {

            $ugr_Name = $user['ugr_Name'];
            $ugr_FullName = $user['ugr_FirstName'].' '.$user['ugr_LastName'];
            $ugr_Organisation = $user['ugr_Organisation'];
            $ugr_eMail = $user['ugr_eMail'];

            if($is_approvement){
                $email_text = "Your Heurist account registration has been approved.";
            }else{
                $email_text = "A new Heurist account has been created for you.";
            }

            //give them a pointer to the search page for the database
            $email_text .= "\n\nLogin to the database: ".HEURIST_DBNAME." at ".
            HEURIST_BASE_URL."?db=".HEURIST_DBNAME. "\n"."\n\nwith the username: " . $ugr_Name;


            if($tmp_password!=null){
                $email_text = $email_text." and password: ".$tmp_password.
                "\n\nTo change your password go to Profile -> My User Info in the top right menu";
            }

            $email_text = $email_text."\n\nWe recommend visiting http://HeuristNetwork.org and the online Help ".
            "pages, which provide comprehensive overviews and step-by-step instructions for using Heurist.\n\n".
            "Database Owner: ".$dbowner_Email;

            $email_title = 'User Registration: '.$ugr_FullName.' ['.$ugr_eMail.']';

            $rv = sendEmail($ugr_eMail, $email_title, $email_text);

            if(!$rv){
                $msg = $system->getError();
                $system->addError(HEURIST_SYSTEM_CONFIG, 'Error_Mail_Approvement', $msg?@$msg['message']:null);
                return false;
            }
            
        }else{
                $system->addError(HEURIST_NOT_FOUND, 'User not found');
                return false;
        }

        return true;
    }  // sendApprovalEmail

    function generate_passwd ($length = 8) {
        $passwd = '';
        $possible = '023456789bcdfghjkmnpqrstvwxyz';
        while (strlen($passwd) < $length) {
            $char = substr($possible, mt_rand(0, strlen($possible)-1), 1);
            if (!strstr($passwd, $char)) $passwd .= $char;
        }
        return $passwd;
    }

    function hash_it ($passwd) {
        $s = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./';
        $salt = $s[rand(0, strlen($s)-1)] . $s[rand(0, strlen($s)-1)];
        return crypt($passwd, $salt);
    }

?>
