<?php                                                
/**
* createDB.php: create new heurist database along with all required folders
*  
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

require_once(dirname(__FILE__).'/../../../hsapi/System.php');
require_once(dirname(__FILE__).'/../../../hsapi/utilities/dbUtils.php');
require_once(dirname(__FILE__).'/../../../hsapi/utilities/utils_file.php');
require_once(dirname(__FILE__).'/../../../admin/structure/import/importDefintions.php');
require_once('welcomeEmail.php');

header('Content-type: text/javascript');

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
            exit();
        }
        
        $mysqli = $system->get_mysqli();
        
/*
        if($isSystemInited){
            $mysqli = $system->get_mysqli();
        }else{
            $dbcon = mysql__connection(HEURIST_DBSERVER_NAME, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD);
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
                exit();
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
                exit();
            }
            
            $user_record = $_REQUEST;
            
            $s = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./';
            $salt = $s[rand(0, strlen($s)-1)] . $s[rand(0, strlen($s)-1)];
            $user_record['ugr_Password'] = crypt($user_record['ugr_Password'], $salt);
            
        }else{ //get user parameters from logged user of current database
            
            $user_record = user_getById($mysqli, $system->get_user_id());
        }
        
        DbUtils::initialize($mysqli);
        
        //compose database name
        $uName = '';
        if(@$_REQUEST['uname']){
            $uName = trim(@$_REQUEST['uname']).'_';
            if ($uName == '_') {$uName='';}; // don't double up underscore if no user prefix
        }
        
        $database_name = $uName . trim($_REQUEST['dbname']);
        $database_name_full = HEURIST_DB_PREFIX . $database_name; // all databases have common prefix then user prefix
        
        //verify that database with such name already exists
        $dblist = mysql__select_list2($mysqli, 'show databases');
        if (array_search(strtolower($database_name_full), array_map('strtolower', $dblist)) !== false ){
            //$mysqli->query('drop database '.$database_name_full);
                $system->addError(HEURIST_ERROR, 
                        'Database with name '.$database_name_full.' aready exists. Try different name');
                print json_encode($system->getError());
                exit();
        }

        //get path to registered db template and download coreDefinitions.txt
        $reg_url = @$_REQUEST['url_template'];  //NOT USED
        $exemplar_db = @$_REQUEST['exemplar'];
        $dataInsertionSQLFile = null;
        
        $warnings = array();
    
        if( $exemplar_db ){ //create database from exemplar
            
        }else if($reg_url){ //load db structure from registered database - NOT USED
            
            
        }else if(true)  { //set to false to debug workflow without actual db creation
            
            $templateFileName = HEURIST_DIR."admin/setup/dbcreate/coreDefinitions.txt";
            $templateFoldersContent = 'NOT DEFINED'; //it is used for tempalate database only

            //check template
            if(!file_exists($templateFileName)){
                $system->addError(HEURIST_SYSTEM_CONFIG, 
                        'Template database structure file '.$templateFileName.' not found');
                print json_encode($system->getError());
                exit();
            }            

            //create empty database
            if(!DbUtils::databaseCreate($database_name_full)){
                print json_encode($system->getError());
                exit();
            }
            
            //switch to new database            
            mysql__usedatabase( $mysqli, $database_name_full);  

            $idef = new ImportDefinitions();
            $idef->initialize( $mysqli );

            if(!$idef->doImport( $templateFileName )) {
                
                $system->addError(HEURIST_SYSTEM_CONFIG, 
                    'Error importing core definitions from coreDefinitions.txt '
                    .' for database '.$database_name_full.'<br>'
                    .'Please check whether this file or database is valid; '.CONTACT_HEURIST_TEAM.' if needed');
                    
                mysql__drop_database( $mysqli, $database_name_full );
                
                print json_encode($system->getError());
                exit();
            }
            
            
            $warnings = DbUtils::databaseCreateFolders($database_name_full);
            
            if(!is_array($warnings)) $warnings = array();
            else if(count($warnings) > 0){ // Catch if db root directory or any sub directory couldn't be created

                mysql__drop_database($mysqli, $database_name_full);

                if(count($warnings) == 2 && $warnings['revert']){

                    sendEmail(HEURIST_MAIL_TO_BUG, 'Unable to create database root folder', $warnings['message'], null);
                    if(HEURIST_MAIL_TO_BUG != HEURIST_MAIL_TO_ADMIN){
                        sendEmail(HEURIST_MAIL_TO_ADMIN, 'Unable to create database root folder', $warnings['message'], null);
                    }
                }else{

                    sendEmail(HEURIST_MAIL_TO_BUG, 'Unable to create database sub directories', "Unable to create the sub directories within the database root directory,\nDatabase name: " . $database_name . ",\nServer url: " . HEURIST_BASE_URL . ",\nWarnings: " . implode(",\n", $warnings), null);
                    if(HEURIST_MAIL_TO_BUG != HEURIST_MAIL_TO_ADMIN){
                        sendEmail(HEURIST_MAIL_TO_ADMIN, "Unable to create the sub directories within the database root directory,\nDatabase name: " . $database_name . ",\nServer url: " . HEURIST_BASE_URL . ",\nWarnings:\n" . implode(",\n", $warnings), null);
                    }
                }

                print json_encode(array('status'=>HEURIST_SYSTEM_CONFIG, 'message'=>'Sorry, we were not able to create all file directories required by the database.<br>Please contact the system administrator (email: ' . HEURIST_MAIL_TO_ADMIN . ') for assistance.', 'sysmsg'=>'This error has been emailed to the Heurist team (for servers maintained by the project - may not be enabled on personal servers). We apologise for any inconvenience', 'error_title'=>null));

                exit();
            }

//@TODO                createElasticIndex($database_name_full); // All Elastic methods use the database prefix.

/*
        if ($warnings > 0) {
            echo "<h2>Please take note of $warnings above</h2>";
            echo "You must create the folders indicated or else navigation tree, uploads, icons, templates, publishing, term images etc. will not work<br>";
            echo "If upload folder is created but icons and template folders are not, look at file permissions on new folder creation";
        }
*/

            if(file_exists($templateFoldersContent) && filesize($templateFoldersContent)>0){ //override content of setting folders with template database files - rectype icons, dashboard icons, smarty templates etc
                $upload_root = $system->getFileStoreRootFolder();
                unzipArchive($templateFoldersContent, $upload_root.$database_name.'/');    
            }            
            
            //update owner in new database
            $user_record['ugr_ID'] = 2;
            $user_record['ugr_NavigationTree'] = '"bookmark":{"expanded":true,"key":"root_1","title":"root","children":[{"folder":false,"key":"_1","title":"Recent changes","data":{"url":"?w=bookmark&q=sortby:-m after:\"1 week ago\"&label=Recent changes"}},{"folder":false,"key":"_2","title":"All (date order)","data":{"url":"?w=bookmark&q=sortby:-m&label=All records"}}]},"all":{"expanded":true,"key":"root_2","title":"root","children":[{"folder":false,"key":"_3","title":"Recent changes","data":{"url":"?w=all&q=sortby:-m after:\"1 week ago\"&label=Recent changes"}},{"folder":false,"key":"_4","title":"All (date order)","data":{"url":"?w=all&q=sortby:-m&label=All records"}}]}';
//,{"folder":true,"key":"_5","title":"Rules","children":[{"folder":false,"key":"12","title":"Person > anything they created","data":{"isfaceted":false}},{"folder":false,"key":"13","title":"Organisation > Assoc. places","data":{"isfaceted":false}}]}
            $user_record['ugr_Preferences'] = '';
            
            $ret = mysql__insertupdate($mysqli, 'sysUGrps', 'ugr', $user_record);
            if($ret!=2){
                array_push($warnings, 'Cannot set owner user. '.$ret);                
            }
           
            //add default saved searches and tree
            $navTree = '{"expanded":true,"key":"root_3","title":"root","children":[{"expanded":true,"folder":true,"key":"_1","title":"Save some filters here ...","children":[]}]}';

//{"key":"28","title":"Organisations","data":{"isfaceted":false}},{"key":"29","title":"Persons","data":{"isfaceted":false}},{"key":"30","title":"Media items","data":{"isfaceted":false}}
                        
            $ret = mysql__insertupdate($mysqli, 'sysUGrps', 'ugr', array('ugr_ID'=>1, 'ugr_NavigationTree'=>$navTree ));
            if($ret!=1){
                array_push($warnings, 'Cannot set navigation tree for group 1. '.$ret);                
            }
            

/* register in cetral index
            $mysqli->query('insert into `Heurist_DBs_index`.`sysIdentifications` select "'
                    .$database_name_full.'" as dbName, s.* from `sysIdentification` as s');
            $mysqli->query("insert into `Heurist_DBs_index`.`sysUsers` (sus_Email, sus_Database, sus_Role) "
                    .'values("'.$user_record['ugr_eMail'].'","'.$database_name_full.'","owner")');
*/            
            sendEmail_NewDatabase($user_record, $database_name, null);
            
            //add sample data
            if($dataInsertionSQLFile!=null && file_exists($dataInsertionSQLFile)){
                if(!db_script($database_name_full, $dataInsertionSQLFile)){
                    array_push($warnings, 'Error importing sample data from '.$dataInsertionSQLFile);                
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
