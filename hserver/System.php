<?php

/**
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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
                    
                    if(!defined('HEURIST_DBNAME')){
                        define('HEURIST_DBNAME', $this->dbname);
                        define('HEURIST_DBNAME_FULL', $this->dbname_full);
                    }

                    //@todo  - test upload and thumb folder exist and writeable
                    if(!$this->initPathConstants()){
                        $this->addError(HEURIST_SYSTEM_FATAL, "Cannot access filestore directory for this database: <b>". HEURIST_FILESTORE_DIR .
                            "</b><br/>Either the directory does not exist (check setting in heuristConfigIni.php file), or it is not writeable by PHP (check permissions).<br>".
                            "On a multi-tier service, the file server may not have restarted correctly or may not have been mounted on the web server.</p>");

                        return false;
                    }

                    $this->login_verify( false ); //load user info from session
                    if($this->get_user_id()>0){
                        //set current user for stored procedures (log purposes)
                        $this->mysqli->query('set @logged_in_user_id = '.$this->get_user_id());
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
        
        $res = mysql_connection(HEURIST_DBSERVER_NAME, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD);
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
    public function defineConstants() {

        // Record type constants
        global $rtDefines;
        foreach ($rtDefines as $str => $id)
        if(!defined($str)){
            $this->defineRTLocalMagic($str, $id[1], $id[0]);
        }

        // Data type constants
        global $dtDefines;
        foreach ($dtDefines as $str => $id)
        if(!defined($str)){
            $this->defineDTLocalMagic($str, $id[1], $id[0]);
        }
    }

    //
    // init the only constant
    //
    public function defineConstant($const_name) {
        if(defined($const_name)){
            return true;
        }else{
            global $rtDefines;
            global $dtDefines;
            if(@$rtDefines[$const_name]){
                $this->defineRTLocalMagic($const_name, $rtDefines[$const_name][1], $rtDefines[$const_name][0]);
            }else if(@$dtDefines[$const_name]){
                $this->defineDTLocalMagic($const_name, $dtDefines[$const_name][1], $dtDefines[$const_name][0]);
            }
            return defined($const_name);
        }
    }

    //
    // get constants as array to use on client side
    //
    private function getLocalConstants(){

        $this->defineConstants();
        
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
    private function defineRTLocalMagic($defString, $rtID, $dbID) {
        
        $id = $this->rectypeLocalIDLookup($rtID, $dbID);
        
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
    private function rectypeLocalIDLookup($rtID, $dbID = 2) {
        global $talkToSysAdmin;
        static $RTIDs;
        
        if($dbID==$this->get_system('sys_dbRegisteredID')){
            return $rtID;
        }else
        if (!$RTIDs) {
            $res = $this->mysqli->query('select rty_ID as localID,
            rty_OriginatingDBID as dbID,rty_IDInOriginatingDB as id from defRecTypes order by dbID');
            if (!$res) {
                echo "Unable to build internal record-type lookup table. ".$talkToSysAdmin." MySQL error: " . mysql_error();
                exit();
            }

            $RTIDs = array();
            while ($row = $res->fetch_assoc()) {
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
    private function defineDTLocalMagic($defString, $dtID, $dbID) {
        $id = $this->detailtypeLocalIDLookup($dtID, $dbID);
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
    private function detailtypeLocalIDLookup($dtID, $dbID = 2) {
        global $talkToSysAdmin;
        static $DTIDs;
        
        if($dbID==$this->get_system('sys_dbRegisteredID')){
            return $dtID;
        }else
        if (!$DTIDs) {
            $res = $this->mysqli->query('select dty_ID as localID,dty_OriginatingDBID as dbID,dty_IDInOriginatingDB as id from defDetailTypes order by dbID');
            if (!$res) {
                echo "Unable to build internal field-type lookup table. ".$talkToSysAdmin." MySQL error: " . mysql_error();
                exit();
            }

            $DTIDs = array();
            while ($row = $res->fetch_assoc()) {
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
    // $dbname - shortname (without prefix)
    //
    public function initPathConstants($dbname=null){

        global $defaultRootFileUploadPath, $defaultRootFileUploadURL;
        if(!$dbname) $dbname = HEURIST_DBNAME;

        //path is defined in configIni
        if (isset($defaultRootFileUploadPath) && $defaultRootFileUploadPath && $defaultRootFileUploadPath!="") {


            if ($defaultRootFileUploadPath != "/" && !preg_match("/[^\/]\/$/", $defaultRootFileUploadPath)) { //check for trailing /
                $defaultRootFileUploadPath.= "/"; // append trailing /
            }
            if ( !strpos($defaultRootFileUploadPath,":/") && $defaultRootFileUploadPath != "/" && !preg_match("/^\/[^\/]/", $defaultRootFileUploadPath)) {
                //check for leading /
                $defaultRootFileUploadPath = "/" . $defaultRootFileUploadPath; // prepend leading /
            }

            if(!defined('HEURIST_FILESTORE_ROOT')){
                define('HEURIST_FILESTORE_ROOT', $defaultRootFileUploadPath );
            }
            
            if(!defined('HEURIST_FILESTORE_DIR')){
                define('HEURIST_FILESTORE_DIR', $defaultRootFileUploadPath . $dbname . '/');
            }
            

            if(folderExists(HEURIST_FILESTORE_DIR, true)<0){
                return false;
            }

            define('HEURIST_FILESTORE_URL', $defaultRootFileUploadURL . $dbname . '/');



        }
        else{
            //path has defaul name
            
            $install_path = 'HEURIST/'; //$this->getInstallPath();
            $dir_Filestore = "HEURIST_FILESTORE/";

            $documentRoot = @$_SERVER['DOCUMENT_ROOT'];
            if( $documentRoot && substr($documentRoot, -1, 1) != '/' ) $documentRoot = $documentRoot.'/';
            
            
            if(!defined('HEURIST_FILESTORE_ROOT')){
                define('HEURIST_FILESTORE_ROOT', $documentRoot . $install_path . $dir_Filestore );
            }
            
            if(!defined('HEURIST_FILESTORE_DIR')){
                define('HEURIST_FILESTORE_DIR', $documentRoot . $install_path . $dir_Filestore . $dbname . '/');
            }
            if(folderExists(HEURIST_FILESTORE_DIR, true)<0){
                return false;
            }

            define('HEURIST_FILESTORE_URL', HEURIST_SERVER_URL . '/' . $install_path . $dir_Filestore . $dbname . '/');
        }

        if(!defined('HEURIST_THUMB_DIR')){
            define('HEURIST_THUMB_DIR', HEURIST_FILESTORE_DIR . 'filethumbs/');
        }
        define('HEURIST_THUMB_URL', HEURIST_FILESTORE_URL . 'filethumbs/');
        define('HEURIST_FILES_DIR', HEURIST_FILESTORE_DIR . 'file_uploads/');
        define('HEURIST_FILES_URL', HEURIST_FILESTORE_URL . 'file_uploads/');

        define('HEURIST_ICON_DIR', HEURIST_FILESTORE_DIR . 'rectype-icons/');
        define('HEURIST_ICON_URL', HEURIST_FILESTORE_URL . 'rectype-icons/');

        define('HEURIST_ICON_SCRIPT', HEURIST_BASE_URL.'hserver/dbaccess/rt_icon.php?db='.$dbname.'&id=');
        
        define('HEURIST_TERM_ICON_DIR', HEURIST_FILESTORE_DIR . 'term-icons/');
        define('HEURIST_TERM_ICON_URL', HEURIST_FILESTORE_URL . 'term-icons/');

        define('HEURIST_SCRATCH_DIR', HEURIST_FILESTORE_DIR . 'scratch/');
        folderCreate(HEURIST_SCRATCH_DIR, true);

        $folder = HEURIST_FILESTORE_DIR . 'settings/';
        if(folderCreate($folder, true)){
            define('HEURIST_SETTING_DIR', $folder);
        }
        
        return true;
    }

    //
    // NOT USED. It is assumed that it is in HEURIST folder
    //
    private function getInstallPath(){

        $documentRoot = @$_SERVER['DOCUMENT_ROOT'];
        if( $documentRoot && substr($documentRoot, -1, 1) != '/' ) $documentRoot = $documentRoot.'/';

        $topDirs = "admin|applications|common|context_help|export|hapi|hclient|hserver|import|records|redirects|search|viewers|help|ext|external"; // Upddate in 3 places if changed
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

            $install_dir = $installDir; //  /html/h4/
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
    public function is_inted(){
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

    /**
    * set dbname and dbname_full properties
    * 
    * @param mixed $db
    */
    public function set_dbname_full($db, $dbrequired=true){
        
        if($db){
            if(strpos($db, HEURIST_DB_PREFIX)===0){
                $this->dbname_full = $db;
                $this->dbname = substr($db,strlen(HEURIST_DB_PREFIX));
            }else{
                $this->dbname = $db;
                $db = HEURIST_DB_PREFIX.$db;
                $this->dbname_full = $db;
            }
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



    /**
    * keep error message (for further use with getError)
    */
    public function addError($status, $message='', $sysmsg=null) {

        if($status==HEURIST_REQUEST_DENIED){
            $sysmsg = $this->get_user_id();
        }else if($status==HEURIST_DB_ERROR){
            error_log('DATABASE ERROR :'.HEURIST_DBNAME.'  '.$message.($sysmsg?'. System message:'.$sysmsg:''));
            $message = 'Heurist was unable to process. '.$message;
            $sysmsg = 'reported in the server\'s PHP error log';
            //if(!$this->is_dbowner()){ //reset to null if not database owner
            //}
        }

        $this->errors = array("status"=>$status, "message"=>$message, "sysmsg"=>$sysmsg);
        return $this->errors;
    }



    /**
    * Returns all info for current user and some sys config parameters
    * see usage usr_info.sysinfo and usr_info.login
    * 
    * it always reload user info from database
    */
    public function getCurrentUserAndSysInfo(){
        
        //current user reset - reload actual info from database
        $this->login_verify( true );

        if($this->mysqli){

            $dbowner = user_getDbOwner($this->mysqli); //info about user #2

            //list of databases recently logged in
            $dbrecent = array();
            if($this->current_User && @$this->current_User['ugr_ID']>0){
                foreach ($_SESSION as $db=>$session){

                    $user_id = @$_SESSION[$db]['ugr_ID'] ?$_SESSION[$db]['ugr_ID'] :@$_SESSION[$db.'.heurist']['user_id'];
                    if($user_id == $this->current_User['ugr_ID']){
                        if(strpos($db, HEURIST_DB_PREFIX)===0){
                            $db = substr($db,strlen(HEURIST_DB_PREFIX));
                        }
                        array_push($dbrecent, $db);
                    }
                }
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
                    "version_new"=>$lastCode_VersionOnServer,
                    //db version
                    "db_version"=> $this->get_system('sys_dbVersion').'.'
                                        .$this->get_system('sys_dbSubVersion').'.'
                                        .$this->get_system('sys_dbSubSubVersion'),         
                    "db_version_req"=>HEURIST_MIN_DBVERSION,
                    
                    "dbowner_name"=>@$dbowner['ugr_FirstName'].' '.@$dbowner['ugr_LastName'],
                    "dbowner_email"=>@$dbowner['ugr_eMail'],
                    "sysadmin_email"=>HEURIST_MAIL_TO_ADMIN,
                    "db_total_records"=>$this->get_system('sys_RecordCount'),
                    "db_usergroups"=> user_getAllWorkgroups($this->mysqli), //all groups- to fast retrieve group name
                    "baseURL"=>HEURIST_BASE_URL,
                    "dbconst"=>$this->getLocalConstants(), //some record and detail types constants with local values specific for current db
                    "dbrecent"=>$dbrecent,  //!!!!!!! need to store in preferences
                    'max_post_size'=>$this->get_php_bytes('post_max_size'),
                    'max_file_size'=>$this->get_php_bytes('upload_max_filesize')
                    )
            );

        }else{

            $res = array(
                "currentUser"=>null,
                "sysinfo"=>array(
                    "help"=>HEURIST_HELP,
                    "version"=>HEURIST_VERSION,
                    "sysadmin_email"=>HEURIST_MAIL_TO_ADMIN,
                    "baseURL"=>HEURIST_BASE_URL)
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
    */
    public function get_user_group_ids(){
    
        $ugrID = $this->get_user_id();

        if($ugrID>0){
            $groups = @$this->current_User['ugr_Groups'];
            if(!is_array($groups)){
                $groups = $this->current_User['ugr_Groups'] = user_getWorkgroups($this->mysqli, $ugrID);
            }

            $groups = array_keys($groups);
            
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
    public function is_member($ug){
        return ($ug==0 || $ug==null || $this->get_user_id()==$ug ||  @$this->current_User['ugr_Groups'][$ug]);
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
        return ( $this->has_access( $this->get_system('sys_OwnerGroupID') ) );
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
            return ($ugrID>0); 
        }
        
        return ($requiredLevel==$ugrID ||   //iself 
                2==$ugrID ||   //db owner
                @$this->current_User['ugr_Groups'][$requiredLevel]=='admin'); //admin of given group
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
        

        //get session id from cookes    
        if (@$_COOKIE['heurist-sessionid']) {
            session_id($_COOKIE['heurist-sessionid']);
            session_cache_limiter('none');
            @session_start();
        } else {   //session does not exist - create new one
            //session_id(sha1(rand()));
            @session_start();
            $session_id = session_id();
            setcookie('heurist-sessionid', $session_id, 0, '/');//, HEURIST_SERVER_NAME);
        }

        /*
        if (@$_COOKIE['heurist-sessionid']) {
        session_id($_COOKIE['heurist-sessionid']);
        } else {
        session_id(sha1(rand()));
        setcookie('heurist-sessionid', session_id(), 0, '/', HEURIST_SERVER_NAME);
        }
        session_cache_limiter('none');
        session_start();
        */
        
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
        $h3session = $this->dbname_full.'.heurist';
        
        if( is_array($user) ){  //user info already found (see login) - need reset session
            $reload_user_from_db = true;            
            $userID = $user['ugr_ID'];
        }else{
        
            $reload_user_from_db = ($user==true);  //reload user unconditionally
            
            $userID = @$_SESSION[$this->dbname_full]['ugr_ID'];
            
            if(!$userID){ //in h4 session user not found
                // vsn 3 backward capability  - restore user id from old session
                $userID = @$_SESSION[$h3session]['user_id'];
                if($userID){
                    $_SESSION[$this->dbname_full]['keepalive'] = @$_SESSION[$h3session]['keepalive'];
                    $reload_user_from_db = true;
                }
            }
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
                
                if(!is_array($user)){
                    $user = user_getById($this->mysqli, $userID);
                }
                //user can be removed - check presence 
                if($user==null){
                    return false; //not logged in
                }
                
                $_SESSION[$this->dbname_full]['ugr_ID'] = $userID;
                $_SESSION[$this->dbname_full]['ugr_Groups']   = user_getWorkgroups( $this->mysqli, $userID );
                $_SESSION[$this->dbname_full]['ugr_Name']     = $user['ugr_Name'];
                $_SESSION[$this->dbname_full]['ugr_FullName'] = $user['ugr_FirstName'] . ' ' . $user['ugr_LastName'];
                
                
                //vsn 3 backward capability
                $_SESSION[$h3session]['cookie_version'] = 1;
                $_SESSION[$h3session]['user_id']       = $userID;
                $_SESSION[$h3session]['user_name']     = $_SESSION[$this->dbname_full]['ugr_Name'];
                $_SESSION[$h3session]['user_realname'] = $_SESSION[$this->dbname_full]['ugr_FullName'];
                $_SESSION[$h3session]['user_access']   = $_SESSION[$this->dbname_full]['ugr_Groups'];
                $_SESSION[$h3session]['keepalive']     = @$_SESSION[$this->dbname_full]['keepalive'];
                
                
                //remove semaphore file
                
            }//$reload_user_from_db from db
            
            if(!@$_SESSION[$this->dbname_full]['ugr_Preferences']){
                $_SESSION[$this->dbname_full]['ugr_Preferences'] = user_getDefaultPreferences();
            }

            $this->current_User = array('ugr_ID'=>intval($userID),
                            'ugr_Name'        => @$_SESSION[$this->dbname_full]['ugr_Name'],
                            'ugr_FullName'    => $_SESSION[$this->dbname_full]['ugr_FullName'],
                            'ugr_Groups'      => $_SESSION[$this->dbname_full]['ugr_Groups'],
                            'ugr_Preferences' => $_SESSION[$this->dbname_full]['ugr_Preferences']);
                
            if (@$_SESSION[$this->dbname_full]['keepalive']) {
                //update cookie - to keep it alive for next 30 days
                $cres = setcookie('heurist-sessionid', session_id(), time() + 30*24*60*60, '/');//, HEURIST_SERVER_NAME);
            }
            
            session_write_close();
        }
        return $islogged;
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
    public function login($username, $password, $session_type){

        if($username && $password){
            
            $superuser = false;
            if(false){
                $user = user_getById($this->mysqli, 2);
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

                    $time = 0;
                    if($session_type == 'public'){
                        $time = 0;
                    }else if($session_type == 'shared'){
                        $time = time() + 24*60*60;     //day
                    }else if ($session_type == 'remember') {
                        $time = time() + 30*24*60*60;  //30 days
                        $_SESSION[$this->dbname_full]['keepalive'] = true; //refresh time on next entry
                    }
                    $cres = setcookie('heurist-sessionid', session_id(), $time, '/'); //, HEURIST_SERVER_NAME);
                    if(!$cres){
                    }

                    $_SESSION[$this->dbname_full]['ugr_ID'] = $user['ugr_ID'];
                    //$this->login_verify( $user ); //save data into session
                    
                    //update login time in database
                    user_updateLoginTime($this->mysqli, $user['ugr_ID']);

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



    /**
    * Clears cookie and destroy session and current_User info
    */
    public function logout(){
        
        $this->start_my_session(false);

        $cres = setcookie('heurist-sessionid', "", time() - 3600);
        $this->current_User = null;
        //session_destroy();
        
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
        session_write_close();
        return true;
    }

    //
    //
    //
    public function user_GetPreference($property){

        $res = @$_SESSION[$this->dbname_full]["ugr_Preferences"][$property];

        // TODO: redundancy: this duplicates same in hapi.js
        if('search_detail_limit'==$property){
            if(!$res || $res<500 ) {$res = 500;}
            else if($res>5000 ) {$res = 5000;}
        }


        return $res;
    }

    //
    //
    //    
    public function user_LogActivity($action, $suplementary = ''){
        
         $now = new DateTime();
         $info =  array($this->get_user_id(), $action, $now->format('Y-m-d H:i:s'), $suplementary);
        
         file_put_contents ( HEURIST_FILESTORE_DIR.'userInteraction.log' , implode(',', $info)."\n", FILE_APPEND );
        
    }


    /**
    * Loads system settings (default values) from sysIdentification
    */
    public function get_system( $fieldname=null ){

        if(!$this->system_settings)
        {
            $mysqli = $this->mysqli;
            $this->system_settings = getSysValues($mysqli);
            if(!$this->system_settings){
                $this->addError(HEURIST_SYSTEM_FATAL, "Unable to read sysIdentification", $mysqli->error);
                return null;
            }
            //verify and add newest db changes
            if(!updateDatabseToLatest($this)){
                return null;    
            }

            // it is required for main page only - so call this request on index.php
            //$this->system_settings['sys_RecordCount'] = mysql__select_value($mysqli, 'select count(*) from Records');
        }
        return ($fieldname) ?@$this->system_settings[$fieldname] :$this->system_settings;
    }

    //
    //
    //
    public function get_php_bytes($php_var ){
        
        $value = ini_get($php_var);
        return $this->_get_config_bytes($value);
        
    }
    
    //
    // convert php.ini config value to valid integer
    //
    private function _get_config_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        //_fix_integer_overflow
        if ($val < 0) {
            $val += 2.0 * (PHP_INT_MAX + 1);
        }
        return $val;
    }
    
    //
    // check database version 
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
            //send request to main server at HEURIST_INDEX_BASE_URL
            // Heurist_Master_Index is the refernece standard for current database version
            // Maybe this should be changed to Heurist_Sandpit?. Note: sandpit no longer needed, or used, from late 2015
            $url = HEURIST_INDEX_BASE_URL . "admin/setup/dbproperties/getCurrentVersion.php?db=Heurist_Master_Index&check=1";

            $rawdata = loadRemoteURLContentSpecial($url); //it returns HEURIST_VERSION."|".HEURIST_DBVERSION
            
            
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

}
?>
