<?php

/*
* Copyright (C) 2005-2016 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* saveUsergrps.php
* This file accepts request to update sysUGrps and sys sysUsrGrpLinks tables
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.5
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
//require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../records/edit/deleteRecordInfo.php');
require_once(dirname(__FILE__)."/../../common/php/utilsMail.php");
//require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');

$legalMethods = array(
    "saveUser",
    "saveGroup",
    "deleteUser",
    "deleteGroup",
    "changeRole");

$sysUGrps_ColumnNames = array(
    "ugr_ID"=>"i",
    "ugr_Type"=>"s",
    "ugr_Name"=>"s",
    "ugr_LongName"=>"s",
    "ugr_Description"=>"s",
    "ugr_Password"=>"s",
    "ugr_eMail"=>"s",
    "ugr_FirstName"=>"s",
    "ugr_LastName"=>"s",
    "ugr_Department"=>"s",
    "ugr_Organisation"=>"s",
    "ugr_City"=>"s",
    "ugr_State"=>"s",
    "ugr_Postcode"=>"s",
    "ugr_Interests"=>"s",
    "ugr_Enabled"=>"s",
    "ugr_URLs"=>"s",
    "ugr_IncomingEmailAddresses"=>"s"
);

$sysUsrGrpLinks_ColumnNames = array(
    "ugl_ID"=>"i",
    "ugl_UserID"=>"i",
    "ugl_GroupID"=>"i",
    "ugl_Role"=>"s"
);


if (!@$_REQUEST['method']) {
    die("invalid call to saveUsergrps, method parameter is required");
}else if(!in_array($_REQUEST['method'], $legalMethods)) {
    die("unsupported method call to saveUsergrps");
}


if (!is_logged_in() && @$_REQUEST['method'] != "saveUser") {
    //ARTEM
    header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
    return;
}
header('Content-type: text/javascript');

$db = mysqli_connection_overwrite(DATABASE); //artem's

//decode and unpack data
$data  = json_decode(urldecode(@$_REQUEST['data']), true);
$recID  = @$_REQUEST['recID'];

switch (@$_REQUEST['method']) {

    case 'saveUser':
        if (!array_key_exists('user',$data) ||
        !array_key_exists('colNames',$data['user']) ||
        !array_key_exists('defs',$data['user'])) {
            die("invalid data structure sent with saveUser method call to saveUsergrps.php");
        }

        $colNames = $data['user']['colNames'];


        if(!is_logged_in()){
            //$groupID = 3; //WARNING! This is hardcode reference to "Other users" workgroup
            $groupID = null; //new user is not assigned to any group
        }else{
            $groupID = @$_REQUEST['groupID'];
            $key = -1;
        }


        $rv = array();
        $rv['result'] = array(); //result

        foreach ($data['user']['defs'] as $recID => $rt) {
            array_push($rv['result'], updateUserGroup('user', $colNames, $recID, $groupID, $rt));
        }

        break;

    case 'deleteUser':

        $rv = array();
        if (!$recID) {
            $rv['error'] = "invalid or not ID sent with deleteUser method call to saveUsergrps.php";
        }else if( intval($recID)==2 ){
            $rv['error'] = "Can't delete system user dbAdmin";
        }else{
            $rv = deleteUser($recID);
            if (!array_key_exists('error',$rv)) {
                //$rv['rectypes'] = getAllRectypeStructures();
            }
        }

        break;

    case 'saveGroup':
        if (!array_key_exists('group',$data) ||
        !array_key_exists('colNames',$data['group']) ||
        !array_key_exists('defs',$data['group'])) {
            die("invalid data structure sent with saveGroup method call to saveUsergrps.php");
        }
        $colNames = $data['group']['colNames'];

        $rv = array();
        $rv['result'] = array(); //result

        foreach ($data['group']['defs'] as $recID => $rt) {
            array_push($rv['result'], updateUserGroup('group', $colNames, $recID, null, $rt));
        }

        break;

    case 'deleteGroup':

        $rv = array();
        if (!$recID) {
            $rv['error'] = "invalid or not ID sent with deleteGroup method call to saveUsergrps.php";
        }else if( intval($recID)==2 ){
            $rv['error'] = "Can't delete system group 'Database Managers'";
        }else{
            $rv = deleteGroup($recID);
            if(@$_SESSION[DATABASE]['ugr_Groups']){
                unset($_SESSION[DATABASE]['ugr_Groups']);    
            }
            
        }
        break;

    case 'changeRole':

        $recIds	= @$_REQUEST['recIDs'];
        $newRole = @$_REQUEST['role'];
        $oldRole = @$_REQUEST['oldrole'];

        if (!$recID || !$recIds || !$newRole) {
            $rv = array();
            $rv['error'] = "invalid or not IDs sent with changeRole method call to saveUsergrps.php";
        }else{
            $rv = changeRole($recID, $recIds, $newRole, $oldRole, true, true);
            if (!array_key_exists('error',$rv)) {
                //$rv['rectypes'] = getAllRectypeStructures();
            }
        }
        break;

}//end of switch

if(@$rv && is_array(@$rv['error']) && count($rv['error'])==0){
    unset($rv['error']);
}

print json_format($rv);

if(@$db){
    $db->close();
}

exit();

/**
* put your comment there...
*
* @param mixed $type - group or user
* @param mixed $recID
*/
function checkPermission( $type, $recID ) {

    global $db;

    $ret = null;

    if(	!is_admin() ){
        if($type=='user'){
            if ( $recID != get_user_id() ){
                $ret = "You are not admin and can't edit another user";
            }
        }else if($type=='group'){
            //find admin
            $query = "select ugl_UserID from sysUsrGrpLinks where ugl_Role = 'admin' and ugl_GroupID=$recID
            and ugl_UserID=".get_user_id();
            $rows = execSQL($db, $query, null, true);

            if ($rows==0 || is_string($rows) ) {
                $ret = "Error checking rights Group $recID in updateUserGroup - ".$rows;
            }else if ($rows==0){
                $ret = "You are not admin for Group# $recID. Edit is not allowed";
            }
        }
    }

    return $ret;
}

/**
*  if user is not enabled and login count=0 - this is approvement operation
*/
function isApprovement( $type, $recID ) {

    $ret = false;

    if(is_admin() && $type=='user'){
        mysql_connection_overwrite(DATABASE);
        $query = "select ugr_Enabled, ugr_LoginCount from ".DATABASE.".sysUGrps where ugr_ID=$recID";
        $res = mysql_query($query);
        while ($row = mysql_fetch_array($res)) {
            $ret = ($row[0]=="n" && $row[1]==0);
        }
    }

    return $ret;
}


/**
* put your comment there...
*
*
* @param $type - "user" or "group"
* @param mixed $commonNames
* @param mixed $rt
*/
function updateUserGroup( $type, $colNames, $recID, $groupID, $values ) {

    global $db, $sysUGrps_ColumnNames;

    $ret = null;

    if (count($colNames) && count($values))
    {
        $isInsert = ($recID<0);

        //check rights for update
        if(!$isInsert){
            $ret = checkPermission($type, $recID);
            if($ret!=null) return $ret;
        }else{
            //remove ugr_ID
            $idx = array_search('ugr_ID', $colNames);
            if($idx!==false){
                unset($colNames[$idx]);
                unset($values[$idx]);
            }

            //add password by default
            if(array_search('ugr_Password', $colNames)===false){
                array_push($colNames, 'ugr_Password');
                array_push($values, '');
            }
            if(array_search('ugr_Name', $colNames)===false){
                array_push($colNames, 'ugr_Name');
                array_push($values, $values[array_search('ugr_eMail', $colNames)]);
            }
        }


        $query = "";
        $fieldNames = "";
        $parameters = array("");
        $fieldNames = join(",",$colNames);
        $tmp_password = "";

        foreach ($colNames as $colName) {

            $val = array_shift($values);

            if (array_key_exists($colName, $sysUGrps_ColumnNames))
            {


                if(($type=='user') && ($colName == "ugr_Password")){
                    $tmp_password = $val;
                    $s = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./';
                    $salt = $s[rand(0, strlen($s)-1)] . $s[rand(0, strlen($s)-1)];
                    $val = crypt($val, $salt);
                }else if($colName == "ugr_Enabled"){
                    if (!is_logged_in()){  //it is not possible to enable user - if not admin
                        $val = "n";
                    }
                    $ugr_Enabled = $val;
                }

                //array_push($ret['error'], "$colName is not a valid column name for defDetailTypes val= $val was not used");

                if($query!="") $query = $query.",";

                if($isInsert){
                    $query = $query."?";
                    //if($fieldNames!="") $fieldNames=$fieldNames.",";
                    //$fieldNames = $fieldNames.$colName;
                }else{
                    $query = $query."$colName = ?";
                }

                $parameters[0] = $parameters[0].$sysUGrps_ColumnNames[$colName]; //take datatype from array
                array_push($parameters, $val);

            }
        }//for columns

        $isApprovement = false;
        if(!$isInsert && isset($ugr_Enabled) && $ugr_Enabled=="y"){
            $isApprovement = isApprovement($type, $recID);
        }

        if (($type=='user' && $isInsert && !is_logged_in()) || $isApprovement) {

            if(!checkSmtp()){

                if(!is_logged_in()){
                    $ret = 'Your registration is not possible since registration ';
                }else{
                    $ret = 'Approval is not possible since ';
                }
                $ret = $ret.'email cannot be sent as the smtp mail system has not been properly installed on this server. Please ask your system administrator to correct the installation';
                return $ret;
            }
        }

        if($query!=""){
            if($isInsert){
                $query = "insert into sysUGrps (".$fieldNames.") values (".$query.")";
            }else{
                $query = "update sysUGrps set ".$query." where ugr_ID = $recID";
            }

            $rows = execSQL($db, $query, $parameters, true);

            if ($rows==0 || is_string($rows) ) {
                $oper = (($isInsert)?"inserting":"updating");

                if(strpos(" ".$rows, "Duplicate entry")>0){
                    $ret = "Error $oper $type. Either 'Login name' or 'Email' already exists in database.";
                }else{
                    $ret = "error $oper $type# $recID in updateUserGroup - ".$rows; //$msqli->error;
                }
            } else {
                if($isInsert){
                    $recID = $db->insert_id;

                    if($type=='user'){

                        if(!is_logged_in()){
                            sendNewUserInfoEmail($recID);
                        } else if (isset($ugr_Enabled) && $ugr_Enabled=="y") {
                            sendApprovalEmail($recID, $tmp_password);
                        }

                        if($groupID){
                            //add new user to specified group
                            changeRole($groupID, $recID, "member", null, false, false);
                        }


                    }else{
                        //this is addition of new group
                        //add current user as admin for new group
                        changeRole($recID, get_user_id(), "admin", null, false, true);
                    }
                    $ret = -$recID;


                }//if $isInsert
                else{

                    if($isApprovement){
                        sendApprovalEmail($recID, null);
                    }

                    $ret = $recID;
                }
            }
        }
    } //if column names

    if ($ret==null){
        $ret = "no data supplied for updating $type - $recID";
    }

    return $ret;
}

/**
* Send email to admin about new user
*
*/
function sendNewUserInfoEmail($recID){

    $dbowner_Email = get_dbowner_email();
    if($dbowner_Email)
    {

        //mysql_connection_overwrite(DATABASE);
        $query = "select * from ".DATABASE.".sysUGrps where ugr_ID=$recID";
        $res = mysql_query($query);
        while ($row = mysql_fetch_assoc($res)) {

            $ugr_Name = $row['ugr_Name'];
            $ugr_FullName = $row['ugr_FirstName'].' '.$row['ugr_LastName'];
            $ugr_Organisation = $row['ugr_Organisation'];
            $ugr_eMail = $row['ugr_eMail'];

            //create email text for admin
            $email_text =
            "There is a Heurist user registration awaiting approval.\n".
            "The user details submitted are:\n".
            "Database name: ".DATABASE."\n".
            "Full name:    ".$ugr_FullName."\n".
            "Email address: ".$ugr_eMail."\n".
            "Organisation:  ".$ugr_Organisation."\n".
            "Go to the address below to review further details and approve the registration:\n".
            HEURIST_BASE_URL."admin/adminMenuStandalone.php?db=".HEURIST_DBNAME."&recID=$recID&mode=users";

            $email_title = 'User Registration for '.$ugr_FullName.' '.$ugr_eMail;

            sendEmail($dbowner_Email, $email_title, $email_text, null);

        }
    }
}

/**
*   Send approval message to user
*/
function sendApprovalEmail($recID, $tmp_password){

    $dbowner_Email = get_dbowner_email();
    if($dbowner_Email)
    {
        //mysql_connection_overwrite(DATABASE);
        $query = "select * from ".DATABASE.".sysUGrps where ugr_ID=$recID";
        $res = mysql_query($query);
        while ($row = mysql_fetch_assoc($res)) {

            $ugr_Name = $row['ugr_Name'];
            $ugr_FullName = $row['ugr_FirstName'].' '.$row['ugr_LastName'];
            $ugr_Organisation = $row['ugr_Organisation'];
            $ugr_eMail = $row['ugr_eMail'];

            if($tmp_password!=null){
                $email_text = "Welcome! A new Heurist account has been created for you";
            }else{
                $email_text = "Welcome! Your Heurist account registration has been approved";
            }

            $email_text .= "\n\nPlease open an existing database with ".HEURIST_BASE_URL." or go to: ".HEURIST_BASE_URL."admin/setup/dbcreate/createNewDB.php to create a new database Your username: " . $ugr_Name;

            if($tmp_password!=null){
                $email_text = $email_text." and password: ".$tmp_password.
                "\n\nTo change your password go to Profile -> My User Info in the top right menu";
            }

            $email_text = $email_text."\n\nWe recommend visiting http://HeuristNetwork.org and particularly the Learn menu and the online Help ".
            "pages, which provide comprehensive overviews and step-by-step instructions for using Heurist.";

            $email_title = 'User Registration: '.$ugr_FullName.' ['.$ugr_eMail.']';

            if(@$dbowner_Email){
                sendEmail($ugr_eMail, $email_title, $email_text, "From: ".$dbowner_Email);
                sendEmail($dbowner_Email, $email_title, $email_text, "From: ".$dbowner_Email);
            }else{
            }

        }

    }
} // sendApprovalEmail



/**
* deleteUser - Helper function that delete a user and its group relations. It is not possible to delete user if it has record entries
* @author Artem Osmakov
* @param $recID user ID to delete
* @return $ret user id that was deleted or error message
**/
function deleteUser($recID) {
    global $db;

    $ret = array();

    $ret2 = checkPermission('user', $recID);
    if($ret2!=null) {
        $ret['error'] = $ret2;
        return $ret;
    }

    $query = "select rec_ID from Records where rec_OwnerUGrpID=$recID and rec_FlagTemporary=0 limit 1";

    $rows = execSQL($db, $query, null, true);

    if (is_string($rows) ) {
        $ret['error'] = "error finding Records for User $recID in deleteUser - ".$rows;
    }else if ($rows>0){
        $ret['error'] = "Error. Deleting User ($recID) with existing Records not allowed";
    } else { // no Records belong this User -  ok to delete this User.

        $checkLastAdmin = checkLastAdmin($recID, null);
        if($checkLastAdmin!=null){
            $ret['error'] = $checkLastAdmin;
            return;
        }

        //delete temporary records
        $query = "select rec_ID from Records where rec_OwnerUGrpID=$recID and rec_FlagTemporary=1";
        $res = mysql_query($query);
        while ($row = mysql_fetch_row($res)) {
            deleteRecord($row[0]);
        }

        //delete references from user-group link table
        $query = "delete from sysUsrGrpLinks where ugl_UserID=$recID";
        $rows = execSQL($db, $query, null, true);
        if (is_string($rows) ) {
            $ret['error'] = "db error deleting relations for User $recID from sysUsrGrpLinks - ".$rows;
        }else{

            $query = "delete from sysUGrps where ugr_ID=$recID";
            $rows = execSQL($db, $query, null, true);

            if ($rows==0 || is_string($rows) ) {
                $ret['error'] = "db error deleting of User $recID from sysUGrps - ".$rows;
            } else {
                $ret['result'] = $recID;
            }
        }
    }

    return $ret;
}



/**
* put your comment there...
*
* @param mixed $recID - group id to be deleted
*/
function deleteGroup($recID) {
    global $db;

    $ret = array();

    $ret2 = checkPermission('group', $recID);
    if($ret2!=null) {
        $ret['error'] = $ret2;
        return $ret;
    }

    $query = "select rec_ID from Records where rec_OwnerUGrpID=$recID  and rec_FlagTemporary=0 limit 1";
    $rows = execSQL($db, $query, null, true);

    if (is_string($rows) ) {
        $ret['error'] = "error finding Records for User $recID in deleteGroup - ".$rows;
    }else if ($rows>0){
        $ret['error'] = "Error. Deleting Group ($recID) with existing Records not allowed";
    } else { // no Records belong this User -  ok to delete this User.

        /*
        $query = "select ugl_UserID from sysUsrGrpLinks where ugl_GroupID=$recID limit 1";
        $rows = execSQL($db, $query, null, true);
        if (!is_numeric($rows)) {
        $ret['error'] = "error finding Users for Group $recID in deleteGroup - ".$rows;
        }else if ($rows>0){
        $ret['error'] = "Error. Deleting Group ($recID) with existing Users not allowed";
        }else{
        }*/

        //delete temporary records
        $query = "select rec_ID from Records where rec_OwnerUGrpID=$recID and rec_FlagTemporary=1";
        $res = mysql_query($query);
        while ($row = mysql_fetch_row($res)) {
            deleteRecord($row[0]);
        }

        $query = "delete from sysUsrGrpLinks where ugl_GroupID=$recID";
        $rows = execSQL($db, $query, null, true);
        if ($rows==0 || is_string($rows) ) {
            $ret['error'] = "db error deleting relations for Group $recID from sysUsrGrpLinks - ".$rows;
        }else{
            $query = "delete from sysUGrps where ugr_ID=$recID";
            $rows = execSQL($db, $query, null, true);

            if ($rows==0 || is_string($rows) ) {
                $ret['error'] = "db error deleting of Group $recID from sysUGrps - ".$rows;
            } else {
                $ret['result'] = $recID;

                $groups = reloadUserGroups(get_user_id());
                updateSessionForUser(get_user_id(), 'user_access', $groups);
            }
        }
    }

    return $ret;
}



/**
* check if given user is the last admin in group
*
* @param mixed $recID - user ID
*/
function checkLastAdmin($recID, $groupID){
    global $db;
    $query =
    "select g1.ugl_GroupID,
    (select count(*) from sysUsrGrpLinks as g2 where g1.ugl_GroupID=g2.ugl_GroupID and g2.ugl_Role='admin') as adm
    from sysUsrGrpLinks as g1 where g1.ugl_UserID=$recID and g1.ugl_Role='admin'";

    //, (select count(*) from sysUsrGrpLinks as g3 where g1.ugl_GroupID=g3.ugl_GroupID and g3.ugl_Role='member') as mem

    if($groupID){
        $query = $query." and g1.ugl_GroupID=$groupID";
    }

    $rows = execSQL($db, $query, null, false);

    if ( (is_numeric($rows) && $rows==0) || is_string($rows) ) {
        $ret = "DB error finding number of possible orphan groups for User $recID from sysUsrGrpLinks - ".$rows;
        return $ret;
    }

    while ($row = mysqli_fetch_row($rows)) {
        if($row[1]<2){ // && $row[2]>0){
            return "Error change role/deleteing for User #$recID since it is the only admin for Group #$row[0]";
        }
    }

    return null;
}



/**
* put your comment there...
*
* @param mixed $grpID - group ID
* @param mixed $recIds - comma separated list of affected user IDs
* @param mixed $newRole - new role
*/
function changeRole($grpID, $recIds, $newRole, $oldRole, $needCheck, $updateSession){
    global $db;

    $ret = array();


    if($needCheck){
        $ret2 = checkPermission('group', $grpID);
        if($ret2!=null) {
            $ret['error'] = $ret2;
            return $ret;
        }
    }

    if(is_numeric($recIds)){
        $arrUsers = array();
        $arrUsers[0] = $recIds;
    }else{
        $arrUsers = split(",", $recIds);
    }

    $is_myself_affected = false;
    $current_user_id = get_user_id();

    //remove from group
    if($newRole=="delete"){

        $ret['results'] = array();

        foreach ($arrUsers as $userID) {

            $is_myself_affected =  ($is_myself_affected || $userID == $current_user_id);
            if($userID==2){
                $error = "Not possible to delete database owner";
            }else{
                $error = checkLastAdmin($userID, $grpID);
            }
            if($error==null){

                $query = "delete from sysUsrGrpLinks where ugl_UserID=$userID and ugl_GroupID=$grpID";

                $rows = execSQL($db, $query, null, true);
                if ($rows==0 || is_string($rows) ) {
                    // error delete reference for this user
                    if(!@$ret['errors']) $ret['errors'] = array();
                    array_push($ret['errors'], "db error deleting relations for user# $userID ".$rows);
                }else{
                    array_push($ret['results'], $userID);
                }
            }else{
                if(!@$ret['errors']) $ret['errors'] = array();
                array_push($ret['errors'], $error);
            }
        }

    }else if($oldRole!=null){ //modification of role

        //$ret['errors'] = array();
        $ret['results'] = array();

        foreach ($arrUsers as $userID) {

            $is_myself_affected =  ($is_myself_affected || $userID == $current_user_id);

            $error = null;
            if($userID==2 && $grpID==1){
                $error = "Not possible to change role for database owner";
                if(!@$ret['errors']) $ret['errors'] = array();
                array_push($ret['errors'], $error);
            }else if($oldRole=="admin" && $newRole=="member"){
                $error = checkLastAdmin($userID, $grpID);
                if($error){
                    if(!@$ret['errors']) $ret['errors'] = array();
                    array_push($ret['errors'], $error);
                }
            }
            if($error==null){
                $query = "UPDATE sysUsrGrpLinks set ugl_Role='$newRole' where ugl_GroupID=$grpID and ugl_UserID=$userID";
                $rows = execSQL($db, $query, null, true);

                if ($rows==0 || is_string($rows) ) {
                    if(!@$ret['errors']) $ret['errors'] = array();
                    array_push($ret['errors'], "DB error changing roles in sysUsrGrpLinks for group $grpID, user $userID - ".$rows);
                } else {
                    array_push($ret['results'], $userID);
                }
            }
        }//for

    }else{

        //insert new roles for non-existing entries
        $query = "INSERT INTO sysUsrGrpLinks (ugl_GroupID, ugl_UserID, ugl_Role) VALUES ";
        $nofirst = false;

        $resIDs = "";

        foreach ($arrUsers as $userID) {

            $is_myself_affected =  ($is_myself_affected || $userID == $current_user_id);

            if($nofirst) {
                $query	= $query.", ";
                $resIDs = $resIDs.", ";
            }
            $query	= $query."($grpID, $userID, '$newRole')";
            $resIDs = $resIDs."$userID";
            $nofirst = true;
        }

        if($nofirst){

            $query	= $query." ON DUPLICATE KEY UPDATE ugl_Role='$newRole'";

            $rows = execSQL($db, $query, null, true);

            if ($rows==0 || is_string($rows) ) {
                $ret['error'] = "DB error setting role in sysUsrGrpLinks - ".$rows;
            } else {
                $ret['result'] = $resIDs;
            }
        }

    }

    //update group info for affected users
    if(@$_SESSION[DATABASE]['ugr_Groups']){
        unset($_SESSION[DATABASE]['ugr_Groups']);    
    }
    
    /*  TEMP - it does not work and affects on request (called several times)
    if($updateSession){
    foreach ($arrUsers as $userID) {
    $groups = reloadUserGroups($userID);
    updateSessionForUser($userID, 'user_access', $groups);
    }
    }
    */

    /* if($is_myself_affected){
    updateSessionInfo();
    }*/

    return $ret;
}
?>