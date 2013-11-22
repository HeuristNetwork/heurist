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

/**
* List of user preferences and their default values
* 
* @var mixed
*/
$prefs = array(
 "layout_config" => "layout_defaults",
 "layout_language" => "ru",
 "layout_theme" => "base",

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
        if (array_key_exists($property, $prefs)) {
            @$_SESSION[$dbname]["ugr_Preferences"][$property] = $value;
        }
    }
}

?>
