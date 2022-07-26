<?php

    /**
    * Application interface. See hSystemMgr in hapi.js
    *    user/groups information/credentials
    *    saved searches
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */
    
    require_once (dirname(__FILE__).'/../System.php');
    require_once (dirname(__FILE__).'/../dbaccess/db_users.php');
    require_once (dirname(__FILE__).'/../dbaccess/db_svs.php');
    require_once (dirname(__FILE__).'/../utilities/utils_file.php');
    require_once (dirname(__FILE__).'/../utilities/utils_image.php');	

detectLargeInputs('REQUEST user_info', $_REQUEST);
detectLargeInputs('COOKIE user_info', $_COOKIE);
    
    $response = array(); //"status"=>"fatal", "message"=>"OBLOM");
    $res = false;

    $action = @$_REQUEST['a']; //$system->getError();
    
    $system = new System();
    
    $error = $system->dbname_check(@$_REQUEST['db']);

    if($error){
        $system->addError(HEURIST_INVALID_REQUEST, $error);
        $res = false;
  
    }else
    if($action=='verify_credentials'){ //just check only if logged in (db connection not required)
        
        $res = $system->verify_credentials(@$_REQUEST['db']);
        
        if( $res>0 ){ //if logged id verify that session info (especially groups) is up to date
            //if exists file with userid it means need to reload system info
            $reload_user_from_db = (@$_SESSION[$system->dbname_full()]['need_refresh']==1);
            
            $const_toinit = true;
            if(!$reload_user_from_db){ //check for flag file to force update user (user rights can be changed by admin)
                $const_toinit = false;
                $system->initPathConstants(@$_REQUEST['db']);  
                $fname = HEURIST_FILESTORE_DIR.$res;
                $reload_user_from_db = file_exists($fname);
            }
            
            if($reload_user_from_db){
                $system->init(@$_REQUEST['db'], false, $const_toinit); //session and constant are defined already
                $res = $system->getCurrentUserAndSysInfo();
            }else{
                $res = true;
            }
        }else{
            //logged off
            $res = array("currentUser"=>array('ugr_ID'=>0,'ugr_FullName'=>'Guest'));
        }
    
    }
    else if($action=='usr_log'){
        
        $system->initPathConstants(@$_REQUEST['db']);                                
        $system->user_LogActivity(@$_REQUEST['activity'], @$_REQUEST['suplementary'], @$_REQUEST['user']);
        $res = true;
        
        if(@$_REQUEST['activity']=='impEmails'){
            $msg = 'Click on "Harvest EMail" in menu. DATABASE: '.@$_REQUEST['db'];
            $rv = sendEmail(HEURIST_MAIL_TO_ADMIN, $msg, $msg);
        }
        
        
    } else if (false && $action == "save_prefs"){ //NOT USED save preferences into session (without db)

        if($system->verify_credentials(@$_REQUEST['db'])>0){
            user_setPreferences($system, $_REQUEST);
            $res = true;
        }

    } else if ($action == "logout"){ //save preferences into session
    
        if($system->set_dbname_full(@$_REQUEST['db'])){
            
                $system->initPathConstants(@$_REQUEST['db']);
                $system->user_LogActivity('Logout');

                if($system->doLogout()){
                    $res = true;
                }
        }
        
    }else if( !$system->init( @$_REQUEST['db'] ) ){ 
        
//        error_log('FAILED INIT SYSTEM');        
        
    }else if($action == 'check_allow_cms'){ // check if CMS creation is allow on current server - $allowCMSCreation set in heuristConfigIni.php

        if(isset($allowCMSCreation) && $allowCMSCreation == -1){

            $msg = 'Due to security restrictions, website creation is blocked on this server.<br>Please ' . CONTACT_SYSADMIN . ' if you wish to create a website.';

            $system->addError(HEURIST_ACTION_BLOCKED, $msg);
            $res = false;
        }else{
            $res = 1;
        }
    }else{
        
        $mysqli = $system->get_mysqli();

        //allowed actions for guest
        $quest_allowed = array('login','reset_password','svs_savetree','svs_gettree','usr_save','svs_get');
        
        if ($action=="sysinfo") { //it call once on hapi.init on client side - so it always need to reload sysinfo

            $res = $system->getCurrentUserAndSysInfo();
            
        }else if ($action == "save_prefs"){
           
            if($system->verify_credentials(@$_REQUEST['db'])>0){
                user_setPreferences($system, $_REQUEST);
                $res = true;
            }

        }else if ($action=="sysimages") { //get list of system images
        
              $lib_path = @$_REQUEST['folders'];
              $exts = array('png','svg');
              
              if(!is_array($lib_path) || count($lib_path)<1){
                  $lib_path = array('admin/setup/iconLibrary/64px/'); //default
              }
              $res = folderContent($lib_path, $exts);
              
        }else if ($action=="folders") { //get list of system images

              $folders = $system->getArrayOfSystemFolders();
               
              $op = @$_REQUEST['operation'];
              if(!$op || $op=='list'){

                  $root_dir = null;
                  if(@$_REQUEST['root_dir']){
                      $root_dir = HEURIST_FILESTORE_DIR.@$_REQUEST['root_dir'];     
                  }

                  $res = folderTree($root_dir, 
                      array('systemFolders'=>$folders,'format'=>'fancy') ); //see utils_file
                  $res = $res['children'];
                  //$res = folderTreeToFancyTree($res, 0, $folders);
              }else{
                  
                  $res = false;
                  
                  $dir_name = @$_REQUEST['name'];
                  
                  $folder_name = HEURIST_FILESTORE_DIR.$dir_name;
                  
                  if($folders[strtolower($dir_name)]){
                      $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Cannot modify system folder');
                  }else
                  if($op=='rename'){
                      
                      $new_name = @$_REQUEST['newname'];
                      if($folders[strtolower($new_name)]){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Name "'.$new_name.'" is reserved for system folder');
                      }else if(file_exists(HEURIST_FILESTORE_DIR.$new_name)){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder with name "'.$new_name.'" already exists');
                      }else if(!file_exists($folder_name)){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder with name "'.$dir_name.'" does not exist');
                      }else{
                          $res = rename($folder_name, HEURIST_FILESTORE_DIR.$new_name);    
                          if(!$res){
                              $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Cannot rename folder "'
                                    .$dir_name.'" to name "'.$new_name.'"');
                          }
                      }
     
                  }else if($op=='delete'){
                      //if (is_dir($dir))
                      
                      if(!file_exists($folder_name)){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder with name "'.$dir_name.'" does not exist');                          }else if (count(scandir($folder_name))>2){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Non empty folder "'.$dir_name.'" cannot be removed');                      }else{
                          $res = folderDelete2($folder_name, true);        
                          if(!$res){
                              $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder "'.$dir_name.'" cannot be removed');
                          }
                      }
                      
                  }else if($op=='create'){
                      if(file_exists($folder_name)){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder with such name already exists');
                      }else{
                         $res = folderCreate($folder_name, true);
                         if(!$res){
                              $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder "'.$dir_name.'" cannot be created');
                         }
                      }
                  }
              }
        }else if($action == 'check_allow_estc'){ // check if the ESTC or LRC18C lookups are allowed for current server+database

            $msg = '';

            if(isset($ESTC_PermittedDBs) && strpos($ESTC_PermittedDBs, @$_REQUEST['db']) !== false){
                if(@$_REQUEST['ver'] == 'ESTC'){ // is original LRC18C lookup, both ESTC and LRC18C DBs need to be on the same server

                    if(@$_REQUEST['db'] == 'Libraries_Readers_Culture_18C_Atlantic'){ // check if current db is the LRC18C DB

                        $query = "SHOW DATABASES WHERE `database` = '". HEURIST_DB_PREFIX ."ESTC_Helsinki_Bibliographic_Metadata'";
                        $res = $mysqli->query($query);

                        if($res){
                            $row = $res->fetch_row();
                            if($row && $row[0]){
                                $res = 1;
                            }
                            $res->close();
                        }
                        if($res != 1){
                            $msg = 'This lookup requires the ESTC_Helsinki_Bibliographic_Metadata database to be on this server.<br>Please use the alternative ESTC_editions or ESTC_works lookups instead.';
                        }
                    }else{
                        $msg = 'This lookup is made for the Libraries_Readers_Culture_18C_Atlantic database only.';
                    }
                }
                if($msg == ''){
                    $res = 1;
                }else{
                    $system->addError(HEURIST_ACTION_BLOCKED, $msg);
                    $res = false;
                }
            }else{
                $msg = 'For licensing reasons this function is only accessible to authorised projects.<br>Please contact the Heurist team if you wish to use this.';
                $system->addError(HEURIST_ACTION_BLOCKED, $msg);
                $res = false;
            }
        }else        
        if ( $system->get_user_id()<1 &&  !in_array($action,$quest_allowed)) {

            $response = $system->addError(HEURIST_REQUEST_DENIED);

        }else{

            $res = false;

            if ($action=="login") {

                //check request
                $username = @$_REQUEST['username'];
                $password = @$_REQUEST['password'];
                $session_type = @$_REQUEST['session_type'];

                if($system->doLogin($username, $password, $session_type)){
                    $res = $system->getCurrentUserAndSysInfo( true ); //including reccount and dashboard entries
                    
                    checkDatabaseFunctions($mysqli);
                }

            } else if ($action=="reset_password") {

                $system->user_LogActivity('ResetPassword');
                
                if(user_ResetPassword($system, @$_REQUEST['username'])){
                    $res = true;
                }

            } else  if ($action=="action_password") { //special passwords for some admin actions - defined in configIni.php
            
                    $action = @$_REQUEST['action'];
                    $password = @$_REQUEST['password'];
                    if($action && $password){
                        $varname = 'passwordFor'.$action;
                        $res = (@$$varname==$password)?'ok':'wrong';
                    }

            }
              else if ($action=="sys_info_count") { 
                
                $res = $system->getTotalRecordsAndDashboard();
                        
            } else if ($action=="usr_save") {
                
                sanitizeRequest($_REQUEST);
                $res = user_Update($system, $_REQUEST);

            } else if ($action=="usr_get" && is_numeric(@$_REQUEST['UGrpID'])) {

                $ugrID = $_REQUEST['UGrpID'];

                if($system->has_access($ugrID)){  //allowed for itself only
                    $res = user_getById($system->get_mysqli(), $ugrID);
                    if(is_array($res)){
                        $res['ugr_Password'] = '';
                    }
                }else{
                    $system->addError(HEURIST_REQUEST_DENIED);
                }

            } else if ($action=="usr_names" && @$_REQUEST['UGrpID']) {
                
                $res = user_getNamesByIds($system, $_REQUEST['UGrpID']);
                
            } else if ($action=="groups") {

                $ugr_ID = @$_REQUEST['UGrpID']?$_REQUEST['UGrpID']:$system->get_user_id();

                $res = user_getWorkgroups($system->get_mysqli(), $ugr_ID, true);

            } else if ($action=="members" && @$_REQUEST['UGrpID']) {

                $res = user_getWorkgroupMemebers($system->get_mysqli(), @$_REQUEST['UGrpID']);

            } else if ($action=="user_wss") {
                
                $res = user_WorkSet($system, $_REQUEST);

            } else if ($action=="svs_copy"){
                
                $res = svsCopy($system, $_REQUEST);

            } else if ($action=="svs_save"){
                
                stripScriptTagInRequest($_REQUEST);
                $res = svsSave($system, $_REQUEST);

            } else if ($action=="svs_delete" && @$_REQUEST['ids']) {

                $res = svsDelete($system, $_REQUEST['ids'], @$_REQUEST['UGrpID']);

            } else if ($action=="svs_get" ) {

                if(@$_REQUEST['svsIDs']){
                    $res = svsGetByIds($system, $_REQUEST['svsIDs']);
                }else{
                    $res = svsGetByUser($system, @$_REQUEST['UGrpID'], @$_REQUEST['keep_order']);
                }

            } else if ($action=="svs_savetree" ) { //save saved searches tree status

                stripScriptTagInRequest($_REQUEST);
                $res = svsSaveTreeData($system, @$_REQUEST['data']);

            } else if ($action=="svs_gettree" ) { //save saved searches tree status

                $res = svsGetTreeData($system, @$_REQUEST['UGrpID']);

                
            }else if($action == 'get_url_content_type'){
                
                $url = @$_REQUEST['url'];
                
                $res = recognizeMimeTypeFromURL($mysqli, $url, false);
                
            } else {

                $system->addError(HEURIST_INVALID_REQUEST);
            }


        }
        
        $system->dbclose();
    }
    
    if(is_bool($res) && !$res){
        $response = $system->getError();
    }else{
        $response = array("status"=>HEURIST_OK, "data"=> $res);
        
    }
    
$system->setResponseHeader();    
print json_encode($response);
?>