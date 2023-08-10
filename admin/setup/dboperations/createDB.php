<?php                                                
/**
* createDB.php: create new heurist database along with all required folders
*  
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
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

require_once(dirname(__FILE__).'/../../../hsapi/System.php');
require_once(dirname(__FILE__).'/../../../hsapi/utilities/dbUtils.php');
require_once(dirname(__FILE__).'/../../../hsapi/utilities/utils_file.php');
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
            
            $user_record = array();
            foreach($_REQUEST as $name=>$val){
                if(strpos($name,'urg_')===0){
                    $user_record = getUsrField($name);    
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
            $uName = trim(@$_REQUEST['uname']).'_';
            if ($uName == '_') {$uName='';}; // don't double up underscore if no user prefix
        }
        
        $database_name = $uName . trim($_REQUEST['dbname']);
        $database_name_full = HEURIST_DB_PREFIX . $database_name; // all databases have common prefix then user prefix
        
        if(strlen($database_name_full)>64){
                $system->addError(HEURIST_ACTION_BLOCKED, 
                        'Database name '.htmlspecialchars($database_name_full).' is too long. Max 64 characters allowed');
                print json_encode($system->getError());
                exit();
        }
        $invalidDbName = System::dbname_check($database_name_full);
        if ($invalidDbName) {
                $system->addError(HEURIST_ACTION_BLOCKED, 
                        'Database name '.htmlspecialchars($database_name_full)
                        .' is invalid. Only letters, numbers and underscores (_) are allowed in the database name');
                print json_encode($system->getError());
                exit();
        }
        
        
        //verify that database with such name already exists
        $dblist = mysql__select_list2($mysqli, 'show databases');
        if (array_search(strtolower($database_name_full), array_map('strtolower', $dblist)) !== false ){
            //$mysqli->query('drop database '.$database_name_full);
                $system->addError(HEURIST_ACTION_BLOCKED, 
                        'Database with name '.htmlspecialchars($database_name_full).' aready exists. Try different name');
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

            $res = DbUtils::databaseCreateFull($database_name_full, $user_record);

            if(is_bool($res) && $res===false){
                print json_encode($system->getError());
                exit();
            }else if(is_array($res) && count($res) > 0){
            
                // Catch if db root directory or any sub directory couldn't be created    
                $warnings = $res;
                
                mysql__drop_database($mysqli, $database_name_full);

                if(is_array($warnings) && count($warnings) == 2 && $warnings['revert']){

                    sendEmail(HEURIST_MAIL_TO_BUG, 'Unable to create database root folder', $warnings['message']);
                    if(HEURIST_MAIL_TO_BUG != HEURIST_MAIL_TO_ADMIN){
                        sendEmail(HEURIST_MAIL_TO_ADMIN, 'Unable to create database root folder', $warnings['message']);
                    }
                }else{

                    sendEmail(HEURIST_MAIL_TO_BUG, 'Unable to create database sub directories', "Unable to create the sub directories within the database root directory,\nDatabase name: " . $database_name . ",\nServer url: " . HEURIST_BASE_URL . ",\nWarnings: " . implode(",\n", $warnings));
                    if(HEURIST_MAIL_TO_BUG != HEURIST_MAIL_TO_ADMIN){
                        sendEmail(HEURIST_MAIL_TO_ADMIN, "Unable to create the sub directories within the database root directory,\nDatabase name: " . $database_name . ",\nServer url: " . HEURIST_BASE_URL . ",\nWarnings:\n" . implode(",\n", $warnings));
                    }
                }

                print json_encode(array('status'=>HEURIST_SYSTEM_CONFIG, 'message'=>'Sorry, we were not able to create all file directories required by the database.<br>Please contact the system administrator (email: ' . HEURIST_MAIL_TO_ADMIN . ') for assistance.', 'sysmsg'=>'This error has been emailed to the Heurist team (for servers maintained by the project - may not be enabled on personal servers). We apologise for any inconvenience', 'error_title'=>null));

                exit();
            }

//@TODO                createElasticIndex($database_name_full); // All Elastic methods use the database prefix.
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

            //ADD DEFAULT LOOKUPS
            $def_lookups = array();

            $to_replace = array('DB_ID', 'DTY_ID', 'RTY_ID');
            $dty_CCode = 'SELECT dty_ID FROM defDetailTypes INNER JOIN defRecStructure ON rst_DetailTypeID = dty_ID WHERE dty_OriginatingDBID = DB_ID AND dty_IDInOriginatingDB = DTY_ID AND rst_RecTypeID = RTY_ID';

            // GeoNames
            $rty_query = 'SELECT rty_ID FROM `'.$database_name_full.'`.defRecTypes WHERE rty_OriginatingDBID = 3 AND rty_IDInOriginatingDB = 1009';
            $rty_id = mysql__select_value($mysqli, $rty_query);
            if(!empty($rty_id)){

                $fld_name = mysql__select_value($mysqli, str_replace($to_replace, array('2', '1', $rty_id), $dty_CCode));
                $fld_name = (empty($fld_name)) ? '' : $fld_name;

                $fld_geo = mysql__select_value($mysqli, str_replace($to_replace, array('2', '28', $rty_id), $dty_CCode));
                $fld_geo = (empty($fld_geo)) ? '' : $fld_geo;

                $fld_cc = mysql__select_value($mysqli, str_replace($to_replace, array('2', '26', $rty_id), $dty_CCode));
                $fld_cc = (empty($fld_cc)) ? '' : $fld_cc;

                $fld_fname = mysql__select_value($mysqli, str_replace($to_replace, array('3', '1068', $rty_id), $dty_CCode));
                $fld_fname = (empty($fld_fname)) ? '' : $fld_fname;

                $fld_id = mysql__select_value($mysqli, str_replace($to_replace, array('2', '581', $rty_id), $dty_CCode));
                $fld_id = (empty($fld_id)) ? '' : $fld_id;

                $key = 'geoName_' . $rty_id;
                $def_lookups[$key] = array('service' => 'geoName', 'rty_ID' => $rty_id, 'label' => 'GeoName', 'dialog' => 'lookupGN', 'fields' => null);
                $def_lookups[$key]['fields'] = array('name' => $fld_name, 'lng' => $fld_geo, 'lat' => $fld_geo, 'countryCode' => $fld_cc, 'adminCode1' => "", 'fclName' => $fld_fname, 'fcodeName' => "", 'geonameId' => $fld_id, 'population' => "");
            }

            // Nakala
            $rty_query = 'SELECT rty_ID FROM `'. $database_name_full .'`.defRecTypes WHERE rty_OriginatingDBID = 2 AND rty_IDInOriginatingDB = 5';
            $rty_id = mysql__select_value($mysqli, $rty_query);
            if(!empty($rty_id)){

                $fld_url = mysql__select_value($mysqli, str_replace($to_replace, array('2', '38', $rty_id), $dty_CCode));
                $fld_url = (empty($fld_url)) ? '' : $fld_url;

                $fld_title = mysql__select_value($mysqli, str_replace($to_replace, array('2', '1', $rty_id), $dty_CCode));
                $fld_title = (empty($fld_title)) ? '' : $fld_title;

                $fld_aut = mysql__select_value($mysqli, str_replace($to_replace, array('2', '15', $rty_id), $dty_CCode));
                $fld_aut = (empty($fld_aut)) ? '' : $fld_aut;

                $fld_date = mysql__select_value($mysqli, str_replace($to_replace, array('2', '10', $rty_id), $dty_CCode));
                $fld_date = (empty($fld_date)) ? '' : $fld_date;

                $fld_lic = mysql__select_value($mysqli, str_replace($to_replace, array('1144', '318', $rty_id), $dty_CCode));
                $fld_lic = (empty($fld_lic)) ? '' : $fld_lic;

                $fld_type = mysql__select_value($mysqli, str_replace($to_replace, array('2', '41', $rty_id), $dty_CCode));
                $fld_type = (empty($fld_type)) ? '' : $fld_type;

                $fld_desc = mysql__select_value($mysqli, str_replace($to_replace, array('2', '3', $rty_id), $dty_CCode));
                $fld_desc = (empty($fld_desc)) ? '' : $fld_desc;

                $fld_name = mysql__select_value($mysqli, str_replace($to_replace, array('2', '62', $rty_id), $dty_CCode));
                $fld_name = (empty($fld_name)) ? '' : $fld_name;

                $key = 'nakala_' . $rty_id;
                $def_lookups[$key] = array('service' => 'nakala', 'rty_ID' => $rty_id, 'label' => 'Nakala Lookup', 'dialog' => 'lookupNakala', 'fields' => null);
                $def_lookups[$key]['fields'] = array('url' => $fld_url, 'title' => $fld_title, 'author' => $fld_aut, 'date' => $fld_date, 'license' => $fld_lic, 'mime_type' => $fld_type, 'abstract' => $fld_desc, 'rec_url' => '', 'filename' => $fld_name);
            }

            if(!empty($def_lookups)){

                $lookup_str = json_encode($def_lookups);
                $upd_query = "UPDATE `". $database_name_full ."`.sysIdentification SET sys_ExternalReferenceLookups = '" . $lookup_str . "' WHERE sys_ID = 1";
                $mysqli->query($upd_query);
            }else{
                //$warnings.push('Unable to setup default lookup services.');
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
