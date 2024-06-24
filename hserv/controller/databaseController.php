<?php
/**
* databaseController.php
* Interface/Controller for manipulations with database(s)
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
action

List

Clear
Clone
Create
Delete
Rename
Restore
  
*/
set_time_limit(0);

require_once dirname(__FILE__).'/../System.php';
require_once dirname(__FILE__).'/../structure/dbsUsersGroups.php';
require_once dirname(__FILE__).'/../utilities/dbUtils.php';
require_once dirname(__FILE__).'/../../admin/setup/dboperations/welcomeEmail.php';

$system = new System();

$action = @$_REQUEST['a'];
$locale = @$_REQUEST['locale'];

if($action==null){
    $action = @$_REQUEST['action'];
}

$session_id = intval(@$_REQUEST['session']);

if(!$system->init(@$_REQUEST['db'], ($action!='create'))){ //db required, except create
    //get error and response
    $response = $system->getError();
}else{

   $isNewUserRegistration = ($action=='create' && !$system->has_access()); 
    
   if(!($isNewUserRegistration || $system->has_access())){
        $response = $system->addError(HEURIST_REQUEST_DENIED, 'You must be logged in');
        //@todo !!!!!  check for $passwordForDatabaseCreation
   }else{
       
        $res = false;        
        
        DbUtils::initialize();
        if($session_id>0){
            DbUtils::setSessionId($session_id);  
        } 
        
        $mysqli =  $system->get_mysqli();
        
        if($action=='list'){   
            //get list of available databases
            
            
        }
        else if ($action=='check_newdefs'){   
            
            //check new definitions for current database
            $res = DbUtils::databaseCheckNewDefs(); 
            if($res===false){
                $res = ''; //there are not new defintions
            }

        }
        else if($action=='create'){  
        
            if( !isset($passwordForDatabaseCreation) 
                || $passwordForDatabaseCreation==''
                || !$system->verifyActionPassword(@$_REQUEST['create_pwd'], $passwordForDatabaseCreation, 6)){
            
            
                //compose database name
                $database_name = __composeDbName();
                if($database_name!==false){
                
                    $usr_owner = null;
                    
                    if($isNewUserRegistration)
                    {
                        //check capture
                        $captcha_code_ok = true;
                        $captcha_code = @$_REQUEST['ugr_Captcha'];
                        if (@$_SESSION["captcha_code"] && $_SESSION["captcha_code"] != $captcha_code) {
                            $system->addError(HEURIST_INVALID_REQUEST, 
                                'Are you a bot? Please enter the correct answer to the challenge question');
                            $captcha_code_ok = false;
                        }
                        if (@$_SESSION["captcha_code"]){
                            unset($_SESSION["captcha_code"]);
                        }
                        
                        if($captcha_code_ok){
                            unset($_REQUEST['ugr_Captcha']);
                            
                            //get registration form fields
                            $usr_owner = array();
                            foreach($_REQUEST as $name=>$val){
                                if(strpos($name,'ugr_')===0){
                                    $usr_owner[$name] = $mysqli->real_escape_string($_REQUEST[$name]);
                                }
                            }
                            
                            //mandatory fields
                            $fld_req = array('ugr_FirstName','ugr_LastName','ugr_eMail','ugr_Name','ugr_Password');
                            foreach($fld_req as $name){
                                if(@$usr_owner[$name]==null || $usr_owner[$name]==''){
                                    $system->addError(HEURIST_INVALID_REQUEST, 'Mandatory data for your registration profile '
                                        .'(first and last name, email, password) are not completed. Please fill out registration form');
                                    $usr_owner = null;
                                    break;
                                }
                            }
                            if($usr_owner!=null){
                                $usr_owner['ugr_Password'] = hash_it( $usr_owner['ugr_Password'] );
                            }
                        }
                        
                    }else{
                        $ugr_ID = $system->get_user_id();
                        $usr_owner = user_getById($mysqli, $ugr_ID);
                    }
                
                    if($usr_owner!=null){
                        //it returns false or array of warnings            
                        $res = DbUtils::databaseCreateFull($database_name, $usr_owner);
                        
                        if($res!==false){
                            sendEmail_NewDatabase($usr_owner, $database_name, null);
                            //add url to new database
                            $res = array(
                                'newdbname'  => $database_name, 
                                'newdblink'  => HEURIST_BASE_URL.'?db='.$database_name.'&welcome=1',
                                'newusername'=> $usr_owner['ugr_Name'],
                                'warnings'   => $res);
                        }
                    }
                }
            }

        }
        else if($action=='restore')  
        {
            //compose database name
            $database_name = __composeDbName();
            if($database_name!==false){
                
                $pwd = @$_REQUEST['pwd']; //sysadmin protection
                if($system->verifyActionPassword($pwd, $passwordForServerFunctions)){    
                    $allow_action = false;
                    $system->addErrorMsg('This action requires a special system administrator password<br>');                        
                    
                }else{
                
                    $archive_file = @$_REQUEST['file'];
                    $archive_folder = intval(@$_REQUEST['folder']);

                    $res = DbUtils::databaseRestoreFromArchive($database_name, $archive_file, $archive_folder);

                    if($res!==false){
                        sendEmail_Database($usr_owner, $database_name, $locale, 'restore');
                        
                        //add url to new database
                        $res = array(
                            'newdbname'  => $database_name, 
                            'newdblink'  => HEURIST_BASE_URL.'?db='.$database_name.'&welcome=1'
                        );
                    }
                }
            }
        }
        else if($action=='delete' || $action=='clear')  
        {
            
            $allow_action = false;
            $db_target = @$_REQUEST['database']?$_REQUEST['database']:$_REQUEST['db'];    
            $db_target = trim(preg_replace('/[^a-zA-Z0-9_]/', '', $db_target)); //for snyk
            
            $create_archive = !array_key_exists('noarchive', $_REQUEST); //for delete
            
            $sErrorMsg = DbUtils::databaseValidateName($db_target, 2); //exists
            if ($sErrorMsg!=null) {
                $system->addError(HEURIST_ACTION_BLOCKED, $sErrorMsg);
            }else{

                $pwd = @$_REQUEST['pwd']; //sysadmin protection
                $is_current_db = ($_REQUEST['db']==$db_target);
                
                //validate premissions
                if($pwd!=null){
                    $allow_action = !$system->verifyActionPassword($pwd, $passwordForDatabaseDeletion, 15);        
                }
                
                if($is_current_db) {
                    
                    if($system->is_dbowner() || $allow_action){ 
                        $challenge_pwd  = @$_REQUEST['chpwd'];
                        $challenge_word = ($action=='clear')?'CLEAR ALL RECORDS':'DELETE MY DATABASE';
                        $allow_action = !$system->verifyActionPassword($challenge_pwd, $challenge_word);
                    }else{
                        $system->addError(HEURIST_REQUEST_DENIED, 
                            'To perform this action you must be logged in as Database Owner');                        
                    }
                    
                }else if(!$allow_action && (!isset($passwordForDatabaseDeletion) || $passwordForDatabaseDeletion=='')) { 
                    $system->addError(HEURIST_REQUEST_DENIED, 
                        'This action is not allowed unless a password is provided - please consult system administrator');
                }
            }
            
            if($allow_action){
                if($action=='clear'){
                 
                    $res = DbUtils::databaseEmpty($db_target, false);
                    
                }else
                if($action=='delete'){
                    //keep owner info to send email after deletion
                    $usr_owner = user_getByField($mysqli, 'ugr_ID', 2, $db_target);
                    
                    $res = DbUtils::databaseDrop(false, $db_target, $create_archive);    
                    
                    if($res!==false && !$is_current_db){
                        sendEmail_DatabaseDelete($usr_owner, $db_target, 1);
                    }
                }
            }
            
            
        }
        else if($action=='rename' || $action=='clone')
        {
            $allow_action = false;
            $is_current_db = false;

            //source database
            $is_template = false; //(@$_REQUEST['templatedb']!=null); //not used anymore
            if($is_template){
                $db_source = filter_var(@$_REQUEST['templatedb'], FILTER_SANITIZE_STRING);
            }else if (@$_REQUEST['sourcedb']){ //by sysadmin from list of databases
                $db_source = filter_var(@$_REQUEST['sourcedb'], FILTER_SANITIZE_STRING);
            }else{
                $db_source = $_REQUEST['db'];    
                $is_current_db  = true;
            }
            
            $db_source = trim(preg_replace('/[^a-zA-Z0-9_]/', '', $db_source)); //for snyk
            
            $sErrorMsg = DbUtils::databaseValidateName($db_source, 2); //exists
            if ($sErrorMsg!=null) {
                if(strpos($sErrorMsg,'not exists')>0){
                   $sErrorMsg = 'Operation is possible when database to be cloned or renamed is on the same server. '
                                .$sErrorMsg;
                }
                $system->addError(HEURIST_ACTION_BLOCKED, $sErrorMsg);
            }else{
                
                //target database
                $db_target = __composeDbName();
                if($db_target!==false){//!is_bool($db_target)
                    $sErrorMsg = DbUtils::databaseValidateName($db_target, 1); //unique
                    if ($sErrorMsg!=null) {
                        $system->addError(HEURIST_ACTION_BLOCKED, $sErrorMsg);
                    }else{
                        
                        //validate permissions
                        //
                        //1. current database
                        //   must dbowner or dbadmin
                        //   must be registered or without new defs - otherwise sysadmin pwd 
                        //2. cloned by sysadmin (sourcedb=)
                        //   sysadmin pwd    
                        //3. template (templatedb=)
                        //   owner will be replaced with current owner
                        //   must be curated (regID<21) or <1000?
                
                        // sysadmin_protection
                        $pwd = @$_REQUEST['pwd']; //sysadmin protection

                        list($db_source_full, $db_source ) = mysql__get_names($db_source);
                        $sourceRegID = mysql__select_value($mysqli, 'select sys_dbRegisteredID from `'.$db_source_full.'`.sysIdentification where 1'); 
        
                    
                        if($is_current_db){
                            if(!$system->is_admin()){ 
                                $system->addError(HEURIST_REQUEST_DENIED, 
'To perform this action you must be logged in as Administrator of group \'Database Managers\' or as Database Owner');                                                    
                            }else{
                                $allow_action = true;
                                if($action=='clone' && !($sourceRegID>0)){
                                    //check for new definitions
                                    $hasWarning = DbUtils::databaseCheckNewDefs();
                                    if($hasWarning!=false && $system->verifyActionPassword($pwd, $passwordForServerFunctions)){    
                                        $allow_action = false;
                                        //add prefix 
                                        $system->addErrorMsg(
"Sorry, the database $db_source has new definitions.<br>It must be registered before cloning or provide special system administrator password.<br><br>");                        
                                    }
                                }
                            }
                        }else if($is_template){
                            
                            if($sourceRegID>0 && $sourceRegID<1000){
                                $allow_action = true;
                            }else{
$sErrorMsg = "Sorry, the database $db_source must be registered with an ID less than 1000, indicating a database curated or approved by the Heurist team, to allow cloning through this function. You may also clone any database that you can log into through the Advanced functions under Administration.";
                            }
                            
                        }else if (!$system->verifyActionPassword($pwd, $passwordForServerFunctions)){
                            //cloned by sysadmin (sourcedb=) from list of databases
                            $allow_action = true;
                        }
                    }
                }
            }

            if($allow_action){ 

                $create_archive = !array_key_exists('noarchive', $_REQUEST); //for rename
                $nodata = array_key_exists('nodata', $_REQUEST);    //for clone  
                
                
                if($action=='rename'){
                 
                    $res = DbUtils::databaseRename($db_source, $db_target, $create_archive);

                    if($res!==false){
                        $res = array(
                                'newdbname'  => $db_target, 
                                'newdblink'  => HEURIST_BASE_URL.'?db='.$db_target.'&welcome=1',
                                'warning'    => $system->getErrorMsg());
                    }
                    
                }else
                if($action=='clone'){

                    $res = DbUtils::databaseCloneFull($db_source, $db_target, $nodata, $is_template);

                    if($res!==false){
                        
                        DbUtils::databaseResetRegistration($db_target); //remove registration from sysIndetification
                        
                        //to send email after clone
                        $usr_owner = user_getByField($mysqli, 'ugr_ID', 2, $db_target);
                        sendEmail_NewDatabase($usr_owner, $db_target, 
                                ' from  '.($is_template?'':'template ').$db_source);
                                
                        $res = array(
                                'newdbname'  => $db_target, 
                                'newdblink'  => HEURIST_BASE_URL.'?db='.$db_target.'&welcome=1');
                                
                    }
                    
                }
            }
            
        }else{
            $system->addError(HEURIST_INVALID_REQUEST, "Action parameter is missing or incorrect");                
            $res = false;
        }

        DbUtils::setSessionVal('REMOVE');
        
        if(is_bool($res) && $res==false){
                $response = $system->getError();
        }else{
                $response = array("status"=>HEURIST_OK, "data"=> $res);
        }
   }
}

header('Content-type: text/javascript');
print json_encode($response);



//
//
//
function __composeDbName(){

    $uName = '';
    if(@$_REQUEST['uname']){
        $uName = trim(preg_replace('/[^a-zA-Z0-9_]/', '', @$_REQUEST['uname'])).'_'; //for snyk            
        if ($uName == '_') {$uName='';}; // don't double up underscore if no user prefix
    }
    $dbName = trim(preg_replace('/[^a-zA-Z0-9_]/', '', @$_REQUEST['dbname']));
    
    if($dbName==''){
        $system->addError(HEURIST_INVALID_REQUEST, "Database name parameter is missing or incorrect");                
        $res = false;
    }else{
        $res = $uName . $dbName;
    }
    
    return $res;
}
?>