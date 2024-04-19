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
        $ugr_ID = $system->get_user_id(); //intval(@$_REQUEST["ugr_ID"]);
        
        DbUtils::setSessionId($session_id);
        
        if($action=='list'){   
            //get list of available databases
            
            
        }else if($action=='create'){   
            
            $user_record = user_getById($mysqli, $ugr_ID);
            
            //compose database name
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
            
                $database_name = $uName . $dbName;
                $database_name_full = HEURIST_DB_PREFIX . $database_name; // all databases have common prefix then user prefix
                
                //it returns false or array of warnings            
                $res = DbUtils::databaseCreateFull($database_name_full, $user_record);
                
                if($res!==false){
                    sendEmail_NewDatabase($user_record, $database_name, null);
                    //add url to new database
                    $res = array(
                        'newdbname'  => $database_name, 
                        'newdblink'  => HEURIST_BASE_URL.'?db='.$database_name.'&welcome=1',
                        'newusername'=> $user_record['ugr_Name'],
                        'warnings'   => $res);
                }

            }
            
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
?>