<?php

    /**
    * CRUD for User/Groups (sysUGrps) and User Preferences (from SESSION)
    *
    * user_ - prefix for functions
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

    require_once (dirname(__FILE__).'/utils_mail.php');

    /**
    * List of user preferences and their default values
    *
    * @var mixed
    */
    $prefs = array(
        "layout_language" => "en",
        "layout_theme" => "heurist",
        'search_limit' => 200,

        "edit_open_in_new_window" => "0",
        "edit_force_tags" => "1",
        "edit_pointer_recentsearch" => "1"
    );


    /**
    * Get user/group by field value
    *
    * @param mixed $ugr_Name - user name
    */
    function user_getByField($mysqli, $field, $value){

        $user = null;
        $ugr_Name = $mysqli->real_escape_string($value);
        $query = 'select * from sysUGrps where '.$field.' = "'.$value.'"';
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
    * put your comment there...
    *
    * @param mixed $system
    * @param mixed $ugr_Name
    */
    function user_ResetPassword($system, $username){
        if($username){
            $mysqli = $system->get_mysqli();
            $user = user_getByField($mysqli, 'ugr_Name', $username);
            if(null==$user) $user = user_getByField($system->get_mysqli(), 'ugr_Name', $username);
            if(null==$user) {
                $system->addError(HEURIST_REQUEST_DENIED,  "Incorrect username / email");

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

                    $email_title = 'Password reset';
                    $email_text = "Dear ".$user['ugr_FirstName'].",\n\n".
                    "Your Heurist password has been reset.\n\n".
                    "Your username is: ".$user['ugr_Name']."\n".
                    "Your new password is: ".$new_passwd."\n\n".
                    "To change your password go to My Profile -> My User Info in the top right menu.\nYou will first be asked to log in with the new password above.";

                    $dbowner_Email = user_getDbOwner($mysqli, 'ugr_eMail');

                    $rv = sendEmail($user['ugr_eMail'], $email_title, $email_text, "From: ".$dbowner_Email);
                    if($rv=="ok"){
                        return true;
                    }else{
                        $system->addError(HEURIST_SYSTEM_CONFIG, $rv);
                    }

                }else{
                    $system->addError(HEURIST_DB_ERROR, 'Can not update record in database', $res);
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
    function user_getWorkgroups($mysqli, $ugr_ID, $isfull=false){

        $result = array();

        if($mysqli && intval($ugr_ID))
        {

            $query = 'select ugl_GroupID, ugl_Role '
            .($isfull?', ugr_Name, ugr_Description ':'')
            .' from sysUsrGrpLinks left join sysUGrps grp on grp.ugr_ID=ugl_GroupID where '
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
    function user_getPreferences(){
        global $prefs;
        return $prefs;
    }

    /**
    * Get set of properties from SESSION
    */
    function user_setPreferences($dbname, $params){
        global $prefs;
        foreach ($params as $property => $value) {
            //if (array_key_exists($property, $prefs)) {
            @$_SESSION[$dbname]["ugr_Preferences"][$property] = $value;
            //}
        }
    }

    /**
    *  if user is not enabled and login count=0 - this is approvement operation
    */
    function user_isApprovement( $system, $recID ) {

        $ret = false;

        if($system->is_admin() && $recID>0){
            $res = mysql__select_array($system->get_mysqli(),
                "select ugr_Type, ugr_Enabled, ugr_LoginCount from sysUGrps  where ugr_ID=".$recID);
            $ret = ($row[0]=="user" && $row[1]=="n" && $row[2]==0);
        }

        return $ret;
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

            }else if ($is_registration || $system->is_admin2($recID)) {

                //do not allow registration if approvement mail can not be sent
                if($is_registration && (!checkSmtp())){
                    $system->addError(HEURIST_SYSTEM_CONFIG, 'Error_Mail_Registration');
                    return false;
                }
                
                $mysqli = $system->get_mysqli();

                $res = mysql__select_value($mysqli,
                    "select ugr_ID from sysUGrps  where ugr_Name='"
                    .$mysqli->real_escape_string( $record['ugr_Enabled'])."' or ugr_eMail='"
                    .$mysqli->real_escape_string($record['ugr_eMail'])."'");
                if($res!=$recID){
                    $system->addError(HEURIST_INVALID_REQUEST, 'The provided name or email already exists');
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
                            $rv = user_EmailAboutNewUser($mysqli, $new_recID);
                        }else if($recID<1 || $is_approvement){
                            $rv = user_EmailApproval($mysqli, $new_recID, $tmp_password, $is_approvement);
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
                    $system->addError(HEURIST_DB_ERROR, 'Can not update record in database', $res);
                }
            }else{
                $system->addError(HEURIST_REQUEST_DENIED, 'Operation denied. Not enough rights');
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

    // @todo - to be implemented
    function changeRole($grpID, $recIds, $newRole, $oldRole, $needCheck, $updateSession){
    }

    /**
    * Send email to admin about new user
    */
    function user_EmailAboutNewUser($mysqli, $recID){

        $dbowner_Email = user_getDbOwner($mysqli, 'ugr_eMail');
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
            "Go to the address below to review further details and approve the registration:\n".
            HEURIST_BASE_URL_V3."admin/adminMenu.php?db=".HEURIST_DBNAME."&recID=$recID&mode=users";

            $email_title = 'User Registration: '.$ugr_FullName.' ['.$ugr_eMail.']';

            $rv = sendEmail($dbowner_Email, $email_title, $email_text, null);
            if($rv != 'ok'){
                $system->addError(HEURIST_SYSTEM_CONFIG, 'Error_Mail_Registration');
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
    function user_EmailApproval($mysqli, $recID, $tmp_password, $is_approvement){

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

            // point them to the home page
            // This is confusing for the user who has a specific database, and the URL is wrong b/c it goes to an H3 address which doesn't exist ...
            //$email_text .= "\n\nPlease go to: ".HEURIST_BASE_URL."?db=".HEURIST_DBNAME." with the username: " . $ugr_Name;

            //give them a pointer to the search page for the database
            $email_text .= "\n\nLogin to the database: ".HEURIST_DBNAME." at ".
            HEURIST_BASE_URL."?db=".HEURIST_DBNAME. "\n"."\n\nwith the username: " . $ugr_Name;


            if($tmp_password!=null){
                $email_text = $email_text." and password: ".$tmp_password.
                "\n\nTo change your password go to My Profile -> My User Info in the top right menu";
            }

            $email_text = $email_text."\n\nWe recommend visiting http://HeuristNetwork.org and the online Help ".
            "pages, which provide comprehensive overviews and step-by-step instructions for using Heurist.";

            $email_title = 'User Registration: '.$ugr_FullName.' ['.$ugr_eMail.']';

            $rv = sendEmail($ugr_eMail, $email_title, $email_text, "From: ".$dbowner_Email);

            if($rv != 'ok'){
                $system->addError(HEURIST_SYSTEM_CONFIG, 'Error_Mail_Approvement');
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
