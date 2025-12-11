<?php
/**
* deleteDB.php - Deletes one or more Heurist databases.
*
* @fileOverview This script handles the deletion of specified Heurist databases.
*               It is typically called by `dbStatistics.php` and is intended for system administrator use.
*               Deletion of the *currently active* database by its owner might be handled differently
*               or have specific password requirements (e.g., 'DELETE MY DATABASE').
*               The script validates the request, checks permissions, and then proceeds to drop
*               the database and its associated file store directory. It can also create an archive
*               of the database before deletion if requested. An email notification is sent to the
*               database owner upon deletion by a system administrator.
*
* @project     Heurist academic knowledge management system
* @package Admin/dboperations
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4
*/

use hserv\utilities\USanitize;
use hserv\utilities\DbUtils;

require_once dirname(__FILE__).'/../../../autoload.php';

require_once 'welcomeEmail.php';

set_time_limit(0);

$res = false;

$system = new hserv\System();

$sysadmin_pwd = USanitize::getAdminPwd();

if($sysadmin_pwd==null){
    $system->addError(HEURIST_INVALID_REQUEST, errorWrongParam('Password'));
    header(CTYPE_JS);
    print json_encode($system->getError());    
    exit;
}

$database_to_delete = filter_var(@$_REQUEST['database'], FILTER_SANITIZE_STRING);

if(isset($envVersion)){
    $sErrorMsg = 'This action on remote database host is disabled.';
}else{
    $sErrorMsg = DbUtils::databaseValidateName($database_to_delete, 2);    
}

if ($sErrorMsg!=null) {
    $system->addError(HEURIST_ACTION_BLOCKED, $sErrorMsg);
    header(CTYPE_JS);
    print json_encode($system->getError());
    exit;
}        
        
        
        
    $database_to_delete = preg_replace(REGEX_ALPHANUM, "", $database_to_delete);//for snyk

    if(array_key_exists('create_archive', $_REQUEST)){
        $create_arc = $_REQUEST['create_archive'];
    }else{
        $create_arc = false;
    }

//if user deletes its own database
    $is_delete_current_db = (@$_REQUEST['db']==$database_to_delete && $sysadmin_pwd=='DELETE MY DATABASE');

// Password check for system administrator who can delete any database
    if($is_delete_current_db || !$system->verifyActionPassword($sysadmin_pwd, $passwordForDatabaseDeletion, 14))
    {
        if($database_to_delete){

            //if database to be deleted is not current - only system admin can do it
            $isSystemInited = $system->init(@$_REQUEST['db']);//need to verify credentials for current database

            /** Db check */
            if($isSystemInited){

                    $allow_deletion = true;


                    list($dbname_full, $dbname ) = mysql__get_names( $database_to_delete );

                    if($is_delete_current_db){

                        $user = user_getById($system->getMysqli(), $system->getUserId());//user in current db

                        $allow_deletion = false;
                        //find the same user in database to be deleted
                        //find user by email
                        $usr = user_getByField($system->getMysqli(), 'ugr_eMail', $user['ugr_eMail'], $dbname_full);
                        if(@$usr['ugr_ID']==2){ //database owner
                            $allow_deletion = true;
                        }else{
                            //allowed if user is database admnistrator
                            $groups = user_getWorkgroups($system->getMysqli(), $usr['ugr_ID'], false, $dbname_full);
                            $allow_deletion = (@$groups[1]=='admin');
                        }
                    }

                    if($allow_deletion)
                    {
                        //find owner of database
                        $usr_owner = user_getByField($system->getMysqli(), 'ugr_ID', 2, $dbname_full);

                        //not verbose
                        $res = DbUtils::databaseDrop(false, $database_to_delete, $create_arc);

                        // in case deletion by sysadmin - send email to onwer of deleted database
                        if($res && !$is_delete_current_db)
                        {
                            sendEmailDatabaseDelete($usr_owner, $dbname);
                        }

                    }else{
                        $system->addError(HEURIST_REQUEST_DENIED,
                            'You must be a database administrator or owner to delete database '.$database_to_delete,1);
                    }

            }
        }else{
            //database not defined - this is just authorization check
            $res = true; //authentification passed
        }
    }
    else{
        $system->addError(HEURIST_REQUEST_DENIED, 'Wrong password');
    }

if(is_bool($res) && !$res){
    $response = $system->getError();
}else{
    $response = array("status"=>HEURIST_OK, "data"=> $res);
}

header(CTYPE_JS);
print json_encode($response);
