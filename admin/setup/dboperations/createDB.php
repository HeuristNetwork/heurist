<?php                                                
/**
* createDB.php: create new heurist database along with all required folders
*  
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

require_once dirname(__FILE__).'/../../../hserv/System.php';
require_once dirname(__FILE__).'/../../../hserv/utilities/dbUtils.php';
require_once dirname(__FILE__).'/../../../hserv/utilities/uFile.php';
require_once 'welcomeEmail.php';

set_time_limit(0);

header('Content-type: text/javascript');

$_DEBUG_NOT_CREATE = false; //set to true to avoid db creation
$_DEBUG_NOT_EMAIL = false;  //set to true to avoid db creation

$res = false;

$system = new System();


//1. get user info from reg.form or current user        
//2  create new empty databse        
//3. copy core defintions or definitions from template database
//4. create folders, create elastic index
//5. copy content of folders from template database
//6. update owner user with registrtion info
//7. insert default searches


/** Password check */
if( isset($passwordForDatabaseCreation) && $passwordForDatabaseCreation!='' &&
    $passwordForDatabaseCreation!=@$_REQUEST['create_pwd'] )
{
    //password not defined
    $system->addError(HEURIST_ERROR, 'Invalid password');
    
}else{
    if(@$_REQUEST['dbname']){

        $isSystemInited = $system->init(@$_REQUEST['db'], false);
        
        if(!$isSystemInited){
            print json_encode($system->getError());
            exit;
        }
        
        $mysqli = $system->get_mysqli();
        
/*
        if($isSystemInited){
            $mysqli = $system->get_mysqli();
        }else{
            $dbcon = mysql__connection(HEURIST_DBSERVER_NAME, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD, HEURIST_DB_PORT);
            if ( is_array($dbcon) ){
                //connection to server failed
                $system->addError($dbcon[0], $dbcon[1]);
                $mysqli = null;
            }else{
                $mysqli = $dbcon;     
            }
        }
*/
        
        $isNewUserRegistration = !$system->has_access();
        
        if($isNewUserRegistration) { //this is creation+registration

            $captcha_code = getUsrField('ugr_Captcha');

            //check capture
            if (@$_SESSION["captcha_code"] && $_SESSION["captcha_code"] != $captcha_code) {
                
                $system->addError(HEURIST_ACTION_BLOCKED, 
                    'Are you a bot? Please enter the correct answer to the challenge question');
                print json_encode($system->getError());
                exit;
            }
            if (@$_SESSION["captcha_code"]){
                unset($_SESSION["captcha_code"]);
            }
            unset($_REQUEST['ugr_Captcha']);

            $firstName = getUsrField('ugr_FirstName');
            $lastName = getUsrField('ugr_LastName');
            $eMail = getUsrField('ugr_eMail');
            $name = getUsrField('ugr_Name');
            $password = getUsrField('ugr_Password');
            if($firstName=='' || $lastName=='' || $eMail=='' || $name=='' || $password==''){
                
                $system->addError(HEURIST_ACTION_BLOCKED, 'Mandatory data for your registration profile '
                    .'(first and last name, email, password) are not completed. Please fill out registration form');
                print json_encode($system->getError());
                exit;
            }
            
            $user_record = array();
            foreach($_REQUEST as $name=>$val){
                if(strpos($name,'ugr_')===0){
                    $user_record[$name] = getUsrField($name);    
                }
            }
            
            $user_record['ugr_Password'] = hash_it( $user_record['ugr_Password'] );
            
        }else{ //get user parameters from logged user of current database
            
            $user_record = user_getById($mysqli, $system->get_user_id());
        }
        
        DbUtils::initialize($mysqli);
        
        //compose database name
        $uName = '';
        if(@$_REQUEST['uname']){
            $uName = trim(preg_replace('/[^a-zA-Z0-9_]/', "", @$_REQUEST['uname'])).'_'; //for snyk            
            if ($uName == '_') {$uName='';}; // don't double up underscore if no user prefix
        }
        
        $database_name = $uName . trim(preg_replace('/[^a-zA-Z0-9_]/', "", $_REQUEST['dbname']));
        $database_name_full = HEURIST_DB_PREFIX . $database_name; // all databases have common prefix then user prefix
        
        //checks that database name is valid, correct length and unique
        $error_msg = DbUtils::databaseValidateName($database_name_full, 1);
        if ($error_msg!=null) {
            $system->addError(HEURIST_ACTION_BLOCKED, $error_msg);
            print json_encode($system->getError());
            exit;
        }

        //get path to registered db template and download coreDefinitions.txt
        $reg_url = @$_REQUEST['url_template'];  //NOT USED
        $exemplar_db = @$_REQUEST['exemplar'];  //NOT USED
        $dataInsertionSQLFile = null;
        
        $warnings = array();
    
        if( $exemplar_db ){ //create database from exemplar
            
        }else if($reg_url){ //load db structure from registered database - NOT USED
            
            
        }else if(!$_DEBUG)  { //set to false to debug workflow without actual db creation

            //it returns false or array of warnings
            $res = DbUtils::databaseCreateFull($database_name_full, $user_record);

            if(is_bool($res) && $res===false){
                //to do send email
                
                print json_encode($system->getError());
                exit;
            }else if(is_array($res) && count($res) > 0){
                /*                 
                // Catch if db root directory or any sub directory couldn't be created    
                $warnings = $res;
                //mysql__drop_database($mysqli, $database_name_full);
                if(!$_DEBUG_NOT_EMAIL){
                    
                    if(count($warnings) == 1 && strpos($warnings,'required database root folder')>0){  ///!!!!!!!! 
                        $msg_title = 'Unable to create database root folder';
                        $msg_body  = $warnings[0];
                    }else{
                        $msg_title = 'Unable to create database sub directories';
                        $msg_body  = implode(",\n", $warnings);
                    }
                    sendEmail(HEURIST_MAIL_TO_BUG, $msg_title, $msg_body);
                    if(HEURIST_MAIL_TO_BUG != HEURIST_MAIL_TO_ADMIN){
                        sendEmail(HEURIST_MAIL_TO_ADMIN, $msg_title, $msg_body);
                    }
                }

                print json_encode(array('status'=>HEURIST_SYSTEM_CONFIG, 
                    'message'=>'Sorry, we were not able to create all file directories required by the database.<br>Please contact the system administrator (email: ' . HEURIST_MAIL_TO_ADMIN . ') for assistance.', 'sysmsg'=>'This error has been emailed to the Heurist team (for servers maintained by the project - may not be enabled on personal servers). We apologise for any inconvenience', 'error_title'=>null));
                exit;
                */
            }

//@TODO                createElasticIndex($database_name_full); // All Elastic methods use the database prefix.
/* register in cetral index
            $mysqli->query('insert into `Heurist_DBs_index`.`sysIdentifications` select "'
                    .$database_name_full.'" as dbName, s.* from `sysIdentification` as s');
            $mysqli->query("insert into `Heurist_DBs_index`.`sysUsers` (sus_Email, sus_Database, sus_Role) "
                    .'values("'.$user_record['ugr_eMail'].'","'.$database_name_full.'","owner")');
*/            
            if(!$_DEBUG_NOT_EMAIL){
                sendEmail_NewDatabase($user_record, $database_name, null);
            }
            
            //add sample data  - NOT USED 
            if($dataInsertionSQLFile!=null && file_exists($dataInsertionSQLFile)){
                $res = mysql__script($database_name_full, $dataInsertionSQLFile);
                if($res!==true){
                    array_push($warnings, 'Error importing sample data '.$res[1]);                
                }
            }

        }

        
        $res = true;
    }else{
        $system->addError(HEURIST_INVALID_REQUEST, 'Name of new database not defined');
    }
}

if(is_bool($res) && !$res){
    $response = $system->getError();
}else{
    $response = array('status'=>HEURIST_OK, 
                'newdbname'=>$database_name, 
                'newdblink'=> HEURIST_BASE_URL.'?db='.$database_name.'&welcome=1',
                'newusername'=>$user_record['ugr_Name'],
                'warnings'=>$warnings);
}
    
print json_encode($response);

//
//
//
function getUsrField($name){
    global $mysqli;
    return @$_REQUEST[$name]?$mysqli->real_escape_string($_REQUEST[$name]):'';
}
?>
