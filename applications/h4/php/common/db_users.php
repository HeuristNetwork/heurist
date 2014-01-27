<?php
/**
* CRUD for User/Groups (sysUGrps)
* 
* and
* 
* User Preferences (from SESSION)
* 
* user_ - prefix for functions
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
 "layout_config" => "layout_defaults",
 "layout_language" => "en",
 "layout_theme" => "cupertino",
 'layout_style' => 'johnson', 
 'search_limit' => 200,

 "edit_open_in_new_window" => "0",
 "edit_force_tags" => "1",
 "edit_pointer_recentsearch" => "1"
);


/**
* Get user/group by name 
*
* @param mixed $ugr_Name - user name
*/
function user_getByName($mysqli, $ugr_Name){

    $user = null;
    $ugr_Name = $mysqli->real_escape_string($ugr_Name);
    $query = 'select * from sysUGrps where ugr_Name = "'.$ugr_Name.'"';
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

    $user = null;
    $query = 'select * from sysUGrps where ugr_ID ='.$ugr_ID;
    $res = $mysqli->query($query);
    if($res){
        $user =$res->fetch_assoc();
        $res->close();
    }
    return $user;
}

/**
* get db owner user or specific field of this user
* 
* @param mixed $field
* @return mixed
*/
function user_getDbOwner($mysqli, $field=null)
{
    $user = user_getById(2);
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

    if (user_Validate($record))
    {
        $recID = intval(@$record['ugr_ID']);
        $rectype = $record['ugr_Type'];
        $is_registration = ($rectype=='user' && $recID<1);
        
        if($is_registration && $system->get_system('sys_AllowRegistration')==0){
            
            $system->addError(HEURIST_REQUEST_DENIED, 'Registration is not allowed for current database');
            
        }else if ($is_registration || $system->is_admin2($recID)) {
            
            $mysqli = $system->get_mysqli();
            
            $res = mysql__select_value($mysqli, 
            "select ugr_ID from sysUGrps  where ugr_Name='"
                    .$mysqli->real_escape_string( $record['ugr_Enabled'])."' or ugr_Email='"
                    .$mysqli->real_escape_string($record['ugr_Email'])."'");
            if($res!=$recID){
                $system->addError(HEURIST_INVALID_REQUEST, 'The provided name or email already exists');
                return false;
            }

            //encrypt password            
            $tmp_password = null;
            if($rectype=='user'){
                if(@$record['ugr_Password']){ 
                    $tmp_password = $record['ugr_Password'];
                    $s = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./';
                    $salt = $s[rand(0, strlen($s)-1)] . $s[rand(0, strlen($s)-1)];
                    $record['ugr_Password'] = crypt($val, $salt);
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
                
                //actions on complete
                if($rectype=='user'){
                    if($recID<1 && $system->get_user_id()<1){
                        user_EmailAboutNewUser($mysqli, $recID);
                    }else if($is_approvement){
                        user_EmailApproval($mysqli, $recID, $tmp_password, $is_approvement);
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
            $system->addError(HEURIST_REQUEST_DENIED);
        }
        
    }  else {
        $system->addError(HEURIST_INVALID_REQUEST, "All required fields are not defined");
    }
    
    return false;
}

function user_Validate($record){
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
        $system->addError(HEURIST_INVALID_REQUEST, "Wrong type for usergroup");
        return false;
    }
    
    $missed = array();
    foreach ($reqs as $fld){
        if(!@$record[$fld]){
            array_push($missed, $fld);
        }
    }
    
    if(count($missed)>0){
        $system->addError(HEURIST_INVALID_REQUEST, "Some required fields are not defined");
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
        if($dbowner_Email && $user)
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
            HEURIST_BASE_URL."admin/adminMenu.php?db=".HEURIST_DBNAME."&recID=$recID&mode=users";

            $email_title = 'User Registration: '.$ugr_FullName.' ['.$ugr_eMail.']';
            
            sendEmail($dbowner_Email, $email_title, $email_text, null);

        }
}

/**
*   Send approval message to user
*/
function user_EmailApproval($mysqli, $recID, $tmp_password, $is_approvement){

        $dbowner_Email = user_getDbOwner($mysqli, 'ugr_eMail');
        $user = user_getById($mysqli, $recID); //find user
        if($dbowner_Email && $user)
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
            $email_text .= "\n\nPlease go to: ".HEURIST_BASE_URL."index.html with the username: " . $ugr_Name;
    
            //give them a pointer to the search page for the database
            $email_text .= "\n\nLogin to the database: ".HEURIST_DBNAME." at".
                HEURIST_BASE_URL."search/search.html?db=".HEURIST_DBNAME. "\n"."with the username: " . $ugr_Name;


            if($tmp_password!=null){
                $email_text = $email_text." and password: ".$tmp_password.
                "\n\nTo change your password go to My Profile -> My User Info in the top right menu";
            }

            $email_text = $email_text."\n\nWe recommend visiting the Help ".
            "pages, which provides comprehensive overviews and step-by-step instructions for using Heurist.";

            $email_title = 'User Registration: '.$ugr_FullName.' ['.$ugr_eMail.']';
            
            sendEmail($ugr_eMail, $email_title, $email_text, "From: ".$dbowner_Email);
          
        } 
}  // sendApprovalEmail    
    
       
?>
