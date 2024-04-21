<?php
/**
* databaseController.php
* Interface/Controller for manipulations with database(s)
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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

require_once dirname(__FILE__).'/../System.php';
require_once dirname(__FILE__).'/../structure/dbsUsersGroups.php';
require_once dirname(__FILE__).'/../utilities/dbUtils.php';
require_once dirname(__FILE__).'/../../admin/setup/dboperations/welcomeEmail.php';

$system = new System();

$action = @$_REQUEST['a'];
if($action==null){
    $action = @$_REQUEST['action'];
}

$session_id = intval(@$_REQUEST['session']);

if(!$system->init(@$_REQUEST['db'], ($action!='create'))){ //db required, except create
    //get error and response
    $response = $system->getError();
}else{

   $isNewUserRegistration = ($action=='create' && !$system->has_access()); 
    
   if(!($isNewUserRegistration || $system->is_dbowner()>0)){
        $response = $system->addError(HEURIST_REQUEST_DENIED, 'You must be an owner of database');
        // 'Administrator permissions are required');
   }else{
       
        $res = false;        
        
        DbUtils::setSessionId($session_id);
        
        $mysqli =  $system->get_mysqli();
        
        if($action=='list'){   
            //get list of available databases
            
            
        }
        else if($action=='create'){   
            
            //compose database name
            $database_name = __composeDbName();
            if($database_name!==false){
            
                if($isNewUserRegistration){
                    //@todo get user info from $_REQUEST
                }else{
                    $ugr_ID = $system->get_user_id(); //intval(@$_REQUEST["ugr_ID"]);
                    $usr_owner = user_getById($mysqli, $ugr_ID);
                }
            
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
        else if($action=='delete' || $action=='clear') //temp || $action=='rename' || $action=='clone'
        {
            $isRenameClone = ($action=='rename' || $action=='clone');
            $db_source = null;
            $database_names_valid = true;
            $allow_action = false;
            $is_template = false;   
            
            if($isRenameClone){
                //target database
                $dbname = __composeDbName();
                if(is_bool($dbname) && !$dbname){
                    $database_names_valid = false;    
                }else{
                    //source database
                    $is_template = (@$_REQUEST['templatedb']!=null);
                    $db_source = $is_template?$_REQUEST['templatedb']:@$_REQUEST['db'];
                    $is_current_db = (@$_REQUEST['db']==$db_source);
                    $sErrorMsg = DbUtils::databaseValidateName($db_source, false);
                    if ($sErrorMsg!=null) {
                        $system->addError(HEURIST_ACTION_BLOCKED, $sErrorMsg);
                        $database_names_valid = false;
                    }
                }
                
            }else{
                $dbname = @$_REQUEST['database']?$_REQUEST['database']:$_REQUEST['db'];    
                $is_current_db = (@$_REQUEST['db']==$dbname);
            }

            // check unique name for rename/clone
            if($database_names_valid){
                $sErrorMsg = DbUtils::databaseValidateName($dbname, $isRenameClone);
                if ($sErrorMsg!=null) {
                    $system->addError(HEURIST_ACTION_BLOCKED, $sErrorMsg);
                    $database_names_valid = false;
                }
            }
            
            if($database_names_valid){

                $pwd = @$_REQUEST['pwd'];
                                         
                if($is_current_db){
                    if($isRenameClone){
                        $challenge_word = null;
                    }else{
                        $challenge_word = ($action=='clear')?'CLEAR ALL RECORDS':'DELETE MY DATABASE';
                    }
                    
                    //validate ownership
                    if($challenge_word==null || !$system->verifyActionPassword($pwd, $challenge_word)){
                        if($system->is_dbowner()){  //|| $system->is_admin()){
                            $allow_action = true;
                        }else{
                            $system->addError(HEURIST_REQUEST_DENIED, 
                            'To perform this action you must be logged in as Database Owner');                        
    //   'To perform this action you must be logged in as Administrator of group \'Database Managers\' or as Database Owner');                
                        }
                    }
                    
                }else if($is_template || !$system->verifyActionPassword($pwd, $passwordForDatabaseDeletion, 14)){
                    //@todo 
                    //1. verify is current user is server manager (by IP and email)
                    //2. or by login credentials to database to be deleted
                    $allow_action = true;
                }
            }
            
            if($allow_action){ 

                $create_archive = !array_key_exists('noarchive', $_REQUEST); //for rename,delete
                $nodata = array_key_exists('nodata', $_REQUEST);    //for clone  
                
                list($database_name_full, $database_name ) = mysql__get_names($dbname);
                
                if($action=='rename'){
                 
                    $res = DbUtils::databaseRename($db_source, $database_name, $create_archive);

                }else
                if($action=='clone'){
                    //keep owner info to send email after deletion
                    $usr_owner = user_getByField($mysqli, 'ugr_ID', 2, $database_name_full);

                    $res = DbUtils::databaseCloneFull($db_source, $database_name, $nodata, $is_template);

                    if($res!==false){
                        sendEmail_NewDatabase($usr_owner, $database_name, 
                                ' from  '.($is_template?'template ':'').$db_source);
                    }
                    
                }else
                if($action=='clear'){
                 
                    $res = DbUtils::databaseEmpty($database_name, false);
                    
                }else{
                    //keep owner info to send email after deletion
                    $usr_owner = user_getByField($mysqli, 'ugr_ID', 2, $database_name_full);
                    
                    $res = DbUtils::databaseDrop(false, $database_name, $create_archive);    
                    
                    if($res!==false && !$is_current_db){
                        sendEmail_DatabaseDelete($usr_owner, $database_name, 1);
                    }
                }
            }
            
            DbUtils::setSessionVal('REMOVE');
        }else{
            $system->addError(HEURIST_INVALID_REQUEST, "Action parameter is missing or incorrect");                
            $res = false;
        }
        
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