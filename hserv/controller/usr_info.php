<?php

    /**
    * Application interface. See hSystemMgr in hapi.js
    *    user/groups information/credentials
    *    saved searches
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */
    
    require_once dirname(__FILE__).'/../System.php';
    require_once dirname(__FILE__).'/../structure/dbsUsersGroups.php';
    require_once dirname(__FILE__).'/../structure/dbsSavedSearches.php';
    require_once dirname(__FILE__).'/../utilities/uFile.php';
    require_once dirname(__FILE__).'/../utilities/uImage.php';	
    require_once dirname(__FILE__).'/../utilities/uSanitize.php';
    

    $response = array(); //"status"=>"fatal", "message"=>"OBLOM");
    $res = false;

    $action = @$_REQUEST['a']; //$system->getError();
    
    $system = new System();
    $dbname = @$_REQUEST['db'];
    $error = System::dbname_check($dbname);

    $dbname = preg_replace('/[^a-zA-Z0-9_]/', "", $dbname); //for snyk    
    
    if($error){
        $system->addError(HEURIST_INVALID_REQUEST, $error);
        $res = false;
  
    }else
    if($action=='verify_credentials'){ //just check only if logged in (db connection not required)
        
        $res = $system->verify_credentials($dbname);
        
        if( $res>0 ){ //if logged id verify that session info (especially groups) is up to date
            //if exists file with userid it means need to reload system info
            $reload_user_from_db = (@$_SESSION[$system->dbname_full()]['need_refresh']==1);
            
            $const_toinit = true;
            if(!$reload_user_from_db){ //check for flag file to force update user (user rights can be changed by admin)
                $const_toinit = false;
                $system->initPathConstants($dbname);  
                $fname = HEURIST_FILESTORE_DIR.$res;
                $reload_user_from_db = file_exists($fname);
            }
            
            if($reload_user_from_db){
                $system->init($dbname, false, $const_toinit); //session and constant are defined already
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
        
        $system->initPathConstants($dbname);                                
        $system->user_LogActivity(@$_REQUEST['activity'], @$_REQUEST['suplementary'], @$_REQUEST['user']);
        $res = true;
        
        if(@$_REQUEST['activity']=='impEmails'){
            $msg = 'Click on "Harvest EMail" in menu. DATABASE: '.$dbname;
            $rv = sendEmail(HEURIST_MAIL_TO_ADMIN, $msg, $msg);
        }
        
        
    } else if (false && $action == "save_prefs"){ //NOT USED save preferences into session (without db)

        if($system->verify_credentials($dbname)>0){
            user_setPreferences($system, $_REQUEST);
            $res = true;
        }

    } else if ($action == "logout"){ //save preferences into session
    
        if($system->set_dbname_full($dbname)){
            
                $system->initPathConstants($dbname);
                $system->user_LogActivity('Logout');

                if($system->doLogout()){
                    $res = true;
                }
        }
        
    }else if($action == 'check_for_alpha'){ // check if an alpha version is available

        $is_alpha = (preg_match("/h\d+\-alpha|alpha\//", HEURIST_BASE_URL) === 1) ? true : false;
        $res = '';

        if(!$is_alpha){
            
            if(!defined('HEURIST_FILESTORE_ROOT')){
                if($system->set_dbname_full($dbname)){
                    $system->initPathConstants($dbname);
                }
            }

            if(defined('HEURIST_FILESTORE_ROOT')){
                $fname = HEURIST_FILESTORE_ROOT."lastAdviceSent.ini";
                $verison_numbers = array();
                array_push($verison_numbers, explode('.', HEURIST_VERSION)[0]); // Check using current major version

                if (file_exists($fname)){
                    list($date_last_check, $version_last_check, $release_last_check) = explode("|", file_get_contents($fname));
                    if($verison_numbers[0] < explode('.', $version_last_check)[0]){
                        array_unshift($verison_numbers, explode('.', $version_last_check)[0]); // Check using new major version, performed first
                    }
                }

                foreach ($verison_numbers as $number) {

                    $url = HEURIST_SERVER_URL . '/h' . $number . '-alpha/';
                    $http_response = get_headers($url)[0];
                    if(preg_match('/4\d{2}|5\d{2}/', $http_response) === 0){ // valid
                        $res = $url;
                        break;
                    }
                }

                if($res == ''){ // Finally, check last supported version
                    $url = HEURIST_SERVER_URL . '/alpha/';
                    $http_response = get_headers($url)[0];
                    if(preg_match('/4\d{2}|5\d{2}/', $http_response) === 0){ // valid
                        $res = $url;
                    }
                }
            }
        }

    }else if($action == 'get_time_diffs'){

        $data = $_REQUEST['data'];
        if(!is_array($data)){
            $data = json_decode($data);
        }

        $early_org = @$data->early_date;
        $latest_org = @$data->latest_date;

        if(empty($early_org) || empty($latest_org)){
            $err = empty($early_org) && empty($latest_org) ? 'Both earliest and latest are ' : (empty($early_org) ? 'Earliest is ' : 'Latest is ');
            $system->addError(HEURIST_ACTION_BLOCKED, $err . 'required');
        }else{

            $err_msg = array();
            $res = true;
            
            $res = Temporal::getPeriod($early_org, $latest_org);

            if($res === false){
                $system->addError(HEURIST_INVALID_REQUEST, 'Invalid earliest or latest date provided. Impossible to get difference');
            }
        }


    }else if( !$system->init( $dbname ) ){ 
        
    }else if($action == 'check_allow_cms'){ // check if CMS creation is allow on current server - $allowCMSCreation set in heuristConfigIni.php

        if(isset($allowCMSCreation) && $allowCMSCreation == -1){

            $msg = 'Due to security restrictions, website creation is blocked on this server.<br>Please ' . CONTACT_SYSADMIN . ' if you wish to create a website.';

            $system->addError(HEURIST_ACTION_BLOCKED, $msg);
            $res = false;
        }else{
            $res = 1;
        }
    }else if($action == 'check_for_databases'){ // check if the provided databases are available on the current server

        $mysqli = $system->get_mysqli();
        $data = $_REQUEST['data'];
        if(!is_array($data)){
            $data = json_decode($data, TRUE);
        }

        if(JSON_ERROR_NONE !== json_last_error() || !is_array($data)){
            $system->addError(HEURIST_INVALID_REQUEST, 'Invalid database names were provided<br>Please contact the Heurist team.');
            $res = false;
        }else{

            $res = array();
            foreach ($data as $rec_id => $db_name) {

                if(strpos($db_name, HEURIST_DB_PREFIX) === false){
                    $db_name = HEURIST_DB_PREFIX . $db_name;
                }

                $query = "SHOW DATABASES WHERE `database` = '" . $db_name . "'";
                $query_res = $mysqli->query($query);

                if($query_res){
                    $row = $query_res->fetch_row();
                    if($row && $row[0]){
                        $res[$rec_id] = 1;
                    }
                    $query_res->close();
                }
            }
        }
    }else if($action == 'get_user_notifications'){
        $res = user_getNotifications($system);
    }else if($action == 'get_tinymce_formats'){

        $settings = $system->getDatabaseSetting('TinyMCE formats');

        if(!is_array($settings) || array_key_exists('status', $settings)){
            $res = false;
        }else if(empty($settings) || empty($settings['formats'])){
            $res = array(
                'content_style' => '',
                'formats' => array(),
                'style_formats' => array(),
                'block_formats' => ''
            );
        }else{

            $res = array(
                'content_style' => '',
                'formats' => array(),
                'style_formats' => array(),
                'block_formats' => array()
            );

            $valid_formats = array();

            /*
            $settings['formats'] keys => format id, for $settings['style_formats'] and $settings['block_formats']
            $settings['formats'] values => array(
                inline|block => html tag
                classes => html class for selector
                styles => css for class selectors
            )
            */
            if(@$settings['webfonts']){
                $res['webfonts'] = array();
                foreach($settings['webfonts'] as $key => $font){
                    $font = str_replace("url('settings/", "url('".HEURIST_FILESTORE_URL.'settings/', $font);
                    $res['webfonts'][$key] = $font;
                }
            }
            
            foreach($settings['formats'] as $key => $format){

                $key = str_ireplace(' ', '_', $key); // replace spaces with underscore
                if(in_array($key, $valid_formats)){
                    continue; // already handle
                }

                $styles = $format['styles'];

                $classes = $format['classes'];

                if(empty($styles) || empty($classes)){
                    continue;
                }

                $css = "." . implode(", .", explode(" ", $classes)) . " { ";
                foreach($styles as $property => $value){
                    $css .= "$property: $value; ";
                }
                $css .= "} ";

                $res['content_style'] .= $css;

                unset($format['styles']); // avoid inserting css into style attribute of html
                $res['formats'][$key] = $format;

                $valid_formats[] = $key;
            }

            $has_styles = !empty($settings['style_formats']);
            $has_blocks = !empty($settings['block_formats']);

            // Setup style and block formats
            foreach($valid_formats as $key){

                if($has_styles){

                    foreach($settings['style_formats'] as $idx => $style){

                        $style['format'] = str_ireplace(' ', '_', $style['format']); // replace spaces with underscore
                        if($style['format'] == $key){

                            $res['style_formats'][] = $style;

                            unset($settings['style_formats'][$idx]); // remove
                            break;
                        }
                    }

                    $has_styles = !empty($settings['style_formats']);
                }

                if($has_blocks){

                    foreach($settings['block_formats'] as $idx => $block){

                        $block['format'] = str_ireplace(' ', '_', $block['format']); // replace spaces with underscore
                        if($block['format'] == $key){

                            $res['block_formats'][] = $block;

                            unset($settings['block_formats'][$idx]); // remove
                            break;
                        }
                    }

                    $has_blocks = !empty($settings['block_formats']);
                }
            }

            if(empty($res['style_formats']) && empty($res['block_formats'])){
                $res = array(); // invalid formatting
            }
        }
    }else if($action == "translate_string"){ // translate given string using Deepl's API, if able
        $res = getExternalTranslation($system, @$_REQUEST['string'], @$_REQUEST['target'], @$_REQUEST['source']);
    }else{
        
        $mysqli = $system->get_mysqli();

        //allowed actions for guest
        $quest_allowed = array('login','reset_password','svs_savetree','svs_gettree','usr_save','svs_get');
        
        if ($action=="sysinfo") { //it call once on hapi.init on client side - so it always need to reload sysinfo

            $res = $system->getCurrentUserAndSysInfo();
            
        }else if ($action == "save_prefs"){
           
            if($system->verify_credentials($dbname)>0){
                user_setPreferences($system, $_REQUEST);
                $res = true;
                //session_write_close();
            }

        }
        else if ( $system->get_user_id()<1 &&  !in_array($action,$quest_allowed)) {

            $response = $system->addError(HEURIST_REQUEST_DENIED);

        }
        else if ($action=="file_in_folder") { //get list of system images
        
              $exts = @$_REQUEST['exts'];
              if($exts){
                    $exts = explode(',',$exts);
              }
              if(!is_array($exts) || count($exts)<1){
                    $exts = array('png','svg');    
              }
              
              $source = @$_REQUEST['source'];
              
              if($source=='tilestacks'){
                  $lib_path = array(HEURIST_FILESTORE_DIR.'uploaded_tilestacks/');
              }else{ //assets
                  $lib_path = array('admin/setup/iconLibrary/'.(($source=='assets16')?'16':'64').'px/');
              }
              
              $res = folderContent($lib_path, $exts);
              
        }
        else if ($action=="foldercontent") { //get list of files for given folder
        
              //by default this are mbtiles in uploaded_tilestack  
        
              $source = @$_REQUEST['source'];
              if(@$_REQUEST['exts']){
                    $exts = explode(',',@$_REQUEST['exts']);    
              }
              if(!is_array($exts) || count($exts)<1){
                    $exts = array('png','svg');    
              }
              
              
              if($source=='uploaded_tilestacks'){
                  $lib_path = array(HEURIST_FILESTORE_DIR.'uploaded_tilestacks/');
              }else{
                  //default 64px
                  $lib_path = array('admin/setup/iconLibrary/'.(($source=='assets16')?'16':'64').'px/');
              }
              $res = folderContent($lib_path, $exts);
              
        }
        else if ($action=="folders") { //get list of system images

              $folders = $system->getArrayOfSystemFolders();
               
              $op = @$_REQUEST['operation'];
              if(!$op || $op=='list'){

                  $root_dir = null;
                  if(@$_REQUEST['root_dir']){
                      $root_dir = USanitize::sanitizePath(HEURIST_FILESTORE_DIR.@$_REQUEST['root_dir']);     
                  }

                  $res = folderTree($root_dir, 
                      array('systemFolders'=>$folders,'format'=>'fancy') ); //see utils_file
                  $res = $res['children'];
                  //$res = folderTreeToFancyTree($res, 0, $folders);
              }else{
                  
                  $res = false;
                  
                  $dir_name = USanitize::sanitizePath(@$_REQUEST['name']);
                  
                  if($dir_name==''){
                      $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder name is not defined or out of the root');
                  }else if(!is_dir(HEURIST_FILESTORE_DIR.$dir_name)){
                      $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder name is not a directory');
                  }else{

                  $f_name = htmlspecialchars($dir_name);
                  $folder_name = HEURIST_FILESTORE_DIR.$dir_name;
                  
                  if($folders[strtolower($dir_name)]){
                      $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Cannot modify system folder');
                  }else
                  if($op=='rename'){
                      
                      $new_name = USanitize::sanitizePath(@$_REQUEST['newname']);
                      if($new_name==''){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'New folder name is not defined or out of the root');
                      }else if($folders[strtolower($new_name)]){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Name "'.$new_name.'" is reserved for system folder');
                      }else if(file_exists(HEURIST_FILESTORE_DIR.$new_name)){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder with name "'.$new_name.'" already exists');
                      }else if(!file_exists($folder_name)){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder with name "'.$f_name.'" does not exist');
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
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder with name "'.$f_name.'" does not exist');             
                      }else if (count(scandir($folder_name))>2){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Non empty folder "'.$f_name.'" cannot be removed');          
                      }else{
                          $res = folderDelete2($folder_name, true);        
                          if(!$res){
                              $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder "'.$f_name.'" cannot be removed');
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
              }
        }
        else if($action == 'check_allow_estc'){ // check if the ESTC or LRC18C lookups are allowed for current server+database

            $msg = '';

            if(isset($ESTC_PermittedDBs) && strpos($ESTC_PermittedDBs, $dbname) !== false){
                if(@$_REQUEST['ver'] == 'ESTC'){ // is original LRC18C lookup, both ESTC and LRC18C DBs need to be on the same server

                    if($dbname == 'Libraries_Readers_Culture_18C_Atlantic'){ // check if current db is the LRC18C DB

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
            
        }
        else{

            $res = false;

            if ($action=="login") {

                //check request
                $username = @$_REQUEST['username'];
                $password = @$_REQUEST['password'];
                $session_type = @$_REQUEST['session_type'];
                $skip_pwd_check = false;
                $res = false;
                
                if(@$_REQUEST['saml_entity']){
                    
                    $sp = $_REQUEST['saml_entity'];
                    
                    //check saml session
                    require_once dirname(__FILE__).'/../utilities/utils_saml.php';
                    
                    $username = samlLogin($system, $sp, $system->dbname(), false);
                    
                    if($username>0){
                        $password= null;    
                        $session_type = 'remember';
                        $skip_pwd_check = true;
                    }else{
                        $username = null;
                    }
                }

                if($username && $system->doLogin($username, $password, $session_type, $skip_pwd_check)){
                    $res = $system->getCurrentUserAndSysInfo( true ); //including reccount and dashboard entries
                    
                    checkDatabaseFunctions($mysqli);
                }

            } else if ($action=="reset_password") {

                $system->user_LogActivity('ResetPassword');

                $password = array_key_exists('new_password', $_REQUEST) ? $_REQUEST['new_password'] : null;
                if(array_key_exists('new_password', $_REQUEST)) unset($_REQUEST['new_password']); // remove from REQUEST

                if($_REQUEST['pin'] && $_REQUEST['username'] && $password){ // update password w/ pin
                    $res = user_ResetPassword($system, $_REQUEST['username'], $password, $_REQUEST['pin']);
                }else if($_REQUEST['pin']){ // get/validate reset pin
                    $res = user_HandleResetPin($system, @$_REQUEST['username'], @$_REQUEST['pin'], @$_REQUEST['captcha']);
                }else{
                    $res = $system->addError(HEURIST_ERROR, 'An invalid request was made to the password reset system.<br>Please contact the Heurist team.');
                }

                /* original method - Lets random people reset passwords for random accounts
                if(user_ResetPasswordRandom($system, @$_REQUEST['username'])){
                    $res = true;
                }
                */

            } else  if ($action=="action_password") { //special passwords for some admin actions - defined in configIni.php

                $actions = array('DatabaseCreation', 'DatabaseDeletion', 'ReservedChanges', 'ServerFunctions');
                $action = @$_REQUEST['action'];
                $password = @$_REQUEST['password'];

                if($action && in_array($action, $actions) && !empty($password)){
                    $varname = 'passwordFor'.$action;
                    $res = (@$$varname==$password)?'ok':'wrong';
                }

            }
              else if ($action=="sys_info_count") { 
                
                $res = $system->getTotalRecordsAndDashboard();
                        
            } else if ($action=="usr_save") {
                
                USanitize::sanitizeRequest($_REQUEST);
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

                $res = user_getWorkgroupMembers($system->get_mysqli(), @$_REQUEST['UGrpID']);

            } else if ($action=="user_wss") {
                
                $res = user_WorkSet($system, $_REQUEST);

            } else if ($action=="svs_copy"){
                
                $res = svsCopy($system, $_REQUEST);

            } else if ($action=="svs_save"){
                
                USanitize::stripScriptTagInRequest($_REQUEST);
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

                USanitize::stripScriptTagInRequest($_REQUEST);
                $res = svsSaveTreeData($system, @$_REQUEST['data']);

            } else if ($action=="svs_gettree" ) { //save saved searches tree status

                $res = svsGetTreeData($system, @$_REQUEST['UGrpID']);

                
            }else if($action == 'get_url_content_type'){
                
                $url = @$_REQUEST['url'];
                
                $res = recognizeMimeTypeFromURL($mysqli, $url, false);
                
            }else if($action == 'upload_file_nakala'){ //@todo - move to separate controller

                // Prepare parameters
                $params = array();

                // File
                $params['file'] = array(
                    'path' => HEURIST_FILESTORE_DIR . '/scratch/' 
                            . USanitize::sanitizeFileName(USanitize::sanitizePath($_REQUEST['file'][0]['name'])),
                    'type' => htmlspecialchars($_REQUEST['file'][0]['type']),
                    'name' => htmlspecialchars($_REQUEST['file'][0]['original_name'])
                );

                // Metadata
                $params['meta']['title'] = array(
                    'value' => htmlspecialchars(@$_REQUEST['meta']['title']),
                    'lang' => null,
                    'typeUri' => 'http://www.w3.org/2001/XMLSchema#string',
                    'propertyUri' => 'http://nakala.fr/terms#title'
                );

                if(empty($_REQUEST['meta']['creator']['authorId'])){
                    $params['meta']['creator'] = array(
                        'value' => null,
                        'lang' => null,
                        'typeUri' => null,
                        'propertyUri' => 'http://nakala.fr/terms#creator'
                    );

                    if(array_key_exists('givenname', $_REQUEST['meta']['creator']) || array_key_exists('surname', $_REQUEST['meta']['creator'])){

                        $fullname = '';
                        if(array_key_exists('givenname', $_REQUEST['meta']['creator'])){
                            $fullname .= htmlspecialchars($_REQUEST['meta']['creator']['givenname']);
                        }
                        if(array_key_exists('surname', $_REQUEST['meta']['creator'])){
                            $fullname .= ' ' . htmlspecialchars($_REQUEST['meta']['creator']['surname']);
                        }
                        $fullname = trim($fullname);

                        $params['meta']['alt_creator'] = array(
                            'value' => $fullname,
                            'lang' => null,
                            'typeUri' => 'http://www.w3.org/2001/XMLSchema#string',
                            'propertyUri' => 'http://purl.org/dc/terms/creator'
                        );
                    }
                }else{
                    $params['meta']['creator'] = array(
                        'value' => @$_REQUEST['meta']['creator'],
                        'propertyUri' => 'http://nakala.fr/terms#creator'
                    );
                }

                if(array_key_exists('created', $_REQUEST['meta']) && !empty($_REQUEST['meta']['created'])){
                    $params['meta']['created'] = array(
                        'value' => @$_REQUEST['meta']['created'],
                        'lang' => null,
                        'typeUri' => 'http://www.w3.org/2001/XMLSchema#string',
                        'propertyUri' => 'http://nakala.fr/terms#created'
                    );
                }else{
                    $params['meta']['created'] = array(
                        'value' => null,
                        'lang' => null,
                        'typeUri' => null,
                        'propertyUri' => 'http://nakala.fr/terms#created'
                    );
                }

                $params['meta']['type'] = array(
                    'value' => @$_REQUEST['meta']['type'],
                    'lang' => null,
                    'typeUri' => 'http://www.w3.org/2001/XMLSchema#anyURI',
                    'propertyUri' => 'http://nakala.fr/terms#type'
                );

                $params['meta']['license'] = array(
                    'value' => @$_REQUEST['meta']['license'],
                    'lang' => null,
                    'typeUri' => 'http://www.w3.org/2001/XMLSchema#string',
                    'propertyUri' => 'http://nakala.fr/terms#license'
                );

                // User API Key
                if($system->get_system('sys_NakalaKey')){
                    $params['api_key'] = $system->get_system('sys_NakalaKey');
                }else{
                    $system->addError(HEURIST_INVALID_REQUEST, 'No Nakala API Key provided, please ensure you have entered your personal key into Design > Setup > Properties > Personal Nakala API Key');
                }

                $params['status'] = 'published'; // publish uploaded file, return url to newly uploaded file on Nakala

                // Upload file
                $res = uploadFileToNakala($system, $params); //from record edit - define file field

                if($res !== false){
                    // delete local file after upload
                    fileDelete(HEURIST_FILESTORE_DIR . '/scratch/' . basename($_REQUEST['file'][0]['name']));
                    fileDelete(HEURIST_FILESTORE_DIR . '/scratch/thumbnail/' . basename($_REQUEST['file'][0]['name']));
                }
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
        if(@$_REQUEST['context']){
            $response['context'] = filter_var($_REQUEST['context']);
        }
    }
    
$system->setResponseHeader();    
print json_encode($response);
?>