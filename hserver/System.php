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
require_once (dirname(__FILE__).'/dbaccess/utils_file.php');


/**
*  Class that contains mysqli (dbconnection), current user and system settings
*
*  it performs system initialization:
*   a) establish connection to server
*   b) define sytems constans - paths
*   c) perform login and load current user info
*   d) load system infor (from sysIdentification)
*   e) keeps array of errors
*
* system constants:
*
* HEURIST_THUMB_DIR
* HEURIST_FILESTORE_DIR
*/

class System {
    /*
    const INVALID_REQUEST = "invalid";    // The Request provided was invalid.
    const NOT_FOUND = "notfound";         // The requested object not found.
    const OK = "ok";                      // The response contains a valid Result.
    const REQUEST_DENIED = "denied";      // The webpage is not allowed to use the service.
    const UNKNOWN_ERROR = "unknown";       // A request could not be processed due to a server error. The request may succeed if you try again.
    const SYSTEM_FATAL  = "system";        // System fatal configuration
    */

    private $mysqli = null;
    private $dbname_full = null;
    private $dbname = null;
    private $session_refix = null;

    private $errors = array();

    //???
    //private $guest_User = array('ugr_ID'=>0,'ugr_FullName'=>'Guest');
    private $current_User = null;
    private $system_settings = null;


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
    public function init($db, $dbrequired=true){

        if($db){
            $this->dbname = $db;
            if(!(strpos($db, HEURIST_DB_PREFIX)===0)){
                $db = HEURIST_DB_PREFIX.$db;
            }
            $this->dbname_full = $db;

        }else if($dbrequired){
            $this->addError(HEURIST_INVALID_REQUEST, "Database parameter not defined");
            $this->mysqli = null;
            return false;
        }

        //dbutils
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

                if(!$this->get_system()){
                    return false;
                }

                $this->start_my_session();

                define('HEURIST_DBNAME', $this->dbname);
                define('HEURIST_DBNAME_FULL', $this->dbname_full);
                //@todo  - test upload and thumb folder exist and writeable
                if(!$this->initPathConstants()){
                    $this->addError(HEURIST_SYSTEM_FATAL, "Cannot access filestore directory for this database: <b>". HEURIST_FILESTORE_DIR .
                        "</b><br/>Either the directory does not exist (check setting in heuristConfigIni.php file), or it is not writeable by PHP (check permissions).<br>".
                        "On a multi-tier service, the file server may not have restarted correctly or may not have been mounted on the web server.</p>");

                    return false;
                }

                $this->login_verify(); //load user info from session

                //consts
                $this->defineConstants();    //@todo - we do not need to init all constans for every system init - call it as separate method
            }



            return true;
        }

    }



    // TODO: JJ Placed here by mistake: there is no reason to resolve all these constants on every request

    /**
    * Defines all constants
    */
    function defineConstants() {
        // Record type constants
        global $rtDefines;
        foreach ($rtDefines as $str => $id) {
            $this->defineRTLocalMagic($str, $id[1], $id[0]);
        }

        // Data type constants
        global $dtDefines;
        foreach ($dtDefines as $str => $id) {
            $this->defineDTLocalMagic($str, $id[1], $id[0]);
        }
    }

    function getLocalConstants(){

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
    function defineRTLocalMagic($defString, $rtID, $dbID) {
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
    function rectypeLocalIDLookup($rtID, $dbID = 2) {
        global $talkToSysAdmin;
        static $RTIDs;
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
    function defineDTLocalMagic($defString, $dtID, $dbID) {
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
    function detailtypeLocalIDLookup($dtID, $dbID = 2) {
        global $talkToSysAdmin;
        static $DTIDs;
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



    public function initPathConstants($dbname=null){

        global $defaultRootFileUploadPath, $defaultRootFileUploadURL;
        if(!$dbname) $dbname = HEURIST_DBNAME;

        $install_path = 'HEURIST/'; //$this->getInstallPath();
        $dir_Filestore = "HEURIST_FILESTORE/";
        $documentRoot = @$_SERVER['DOCUMENT_ROOT'];
        if( $documentRoot && substr($documentRoot, -1, 1) != '/' ) $documentRoot = $documentRoot.'/';

        if (isset($defaultRootFileUploadPath) && $defaultRootFileUploadPath && $defaultRootFileUploadPath!="") {


            if ($defaultRootFileUploadPath != "/" && !preg_match("/[^\/]\/$/", $defaultRootFileUploadPath)) { //check for trailing /
                $defaultRootFileUploadPath.= "/"; // append trailing /
            }
            if ( !strpos($defaultRootFileUploadPath,":/") && $defaultRootFileUploadPath != "/" && !preg_match("/^\/[^\/]/", $defaultRootFileUploadPath)) {
                //check for leading /
                $defaultRootFileUploadPath = "/" . $defaultRootFileUploadPath; // prepend leading /
            }

            define('HEURIST_FILESTORE_DIR', $defaultRootFileUploadPath . $dbname . '/');

            if(folderExists(HEURIST_FILESTORE_DIR, true)<0){
                return false;
            }

            define('HEURIST_FILESTORE_URL', $defaultRootFileUploadURL . $dbname . '/');



        }else{

            define('HEURIST_FILESTORE_DIR', $documentRoot . $install_path . $dir_Filestore . $dbname . '/');
            if(folderExists(HEURIST_FILESTORE_DIR, true)<0){
                return false;
            }

            define('HEURIST_FILESTORE_URL', HEURIST_SERVER_URL . '/' . $install_path . $dir_Filestore . $dbname . '/');
        }

        define('HEURIST_THUMB_DIR', HEURIST_FILESTORE_DIR . '/filethumbs/');
        define('HEURIST_THUMB_URL', HEURIST_FILESTORE_URL . '/filethumbs/');
        define('HEURIST_FILES_DIR', HEURIST_FILESTORE_DIR . '/file_uploads/');
        define('HEURIST_FILES_URL', HEURIST_FILESTORE_URL . '/file_uploads/');

        define('HEURIST_ICON_DIR', HEURIST_FILESTORE_DIR . 'rectype-icons/');
        define('HEURIST_ICON_URL', HEURIST_FILESTORE_URL . 'rectype-icons/');

        define('HEURIST_TERM_ICON_DIR', HEURIST_FILESTORE_DIR . 'term-icons/');
        define('HEURIST_TERM_ICON_URL', HEURIST_FILESTORE_URL . 'term-icons/');


        $folder = HEURIST_FILESTORE_DIR . 'settings/';
        if(folderCreate($folder, true)){
            define('HEURIST_SETTING_DIR', $folder);
        }

        return true;
    }

    //
    //
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
    * return list of errors
    */
    public function getError(){
        return $this->errors;
    }



    /**
    * keep error message (for further use with getError)
    */
    public function addError($status, $message='', $sysmsg=null) {
        $this->errors = array("status"=>$status, "message"=>$message, "sysmsg"=>$sysmsg);
        return $this->errors;
    }



    /**
    * Returns all info for current user and some sys config parameters
    */
    public function getCurrentUserAndSysInfo(){

        $user = $this->current_User;
        if($user) {
            $user['ugr_Password'] = ''; // do not send password to client side
            $user['ugr_Admin'] = $this->is_admin();
            if(!@$user['ugr_Preferences']) $user['ugr_Preferences'] = user_getDefaultPreferences();
        }

        if($this->mysqli && defined('HEURIST_DBNAME')){
            $dbowner = user_getDbOwner($this->mysqli);

            $res = array(
                "currentUser"=>$user,
                "sysinfo"=>array(
                    "registration_allowed"=>$this->get_system('sys_AllowRegistration'),
                    "db_registeredid"=>$this->get_system('sys_dbRegisteredID'),
                    "help"=>HEURIST_HELP,
                    "version"=>HEURIST_VERSION,
                    "dbowner_name"=>@$dbowner['ugr_FirstName'] . ' ' . @$dbowner['ugr_LastName'],
                    "dbowner_email"=>@$dbowner['ugr_eMail'],
                    "sysadmin_email"=>HEURIST_MAIL_TO_ADMIN,
                    "db_total_records"=>$this->get_system('sys_RecordCount'),
                    "db_usergroups"=> user_getAllWorkgroups($this->mysqli),
                    "basePathV3"=>HEURIST_BASE_URL,
                    "dbconst"=>$this->getLocalConstants()) //some record and detail types constants with local values specific for current db
            );

        }else{

            $res = array(
                "currentUser"=>null,
                "sysinfo"=>array(
                    "help"=>HEURIST_HELP,
                    "version"=>HEURIST_VERSION,
                    "sysadmin_email"=>HEURIST_MAIL_TO_ADMIN,
                    "basePathV3"=>HEURIST_BASE_URL)
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

        if($this->current_User){
            $groups = @$this->current_User['ugr_Groups'];
            if($groups){
                $groups = array_keys($groups);
            }else{
                $groups = array();
            }
            //add user itself
            array_push($groups, intval($this->current_User['ugr_ID']));
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
        return ($ug==0 || $this->get_user_id()==$ug ||  @$this->current_User['ugr_Groups'][$ug]);
    }

    public function is_logged_in(){
        return ($this->get_user_id()>0);
    }

    public function is_dbowner(){
        return ($this->get_user_id()==2);
    }


    /**
    * Returns Workgroup ID if given id is id of database owner or admin of given group otehrwise it returns FALSE
    *
    * @param mixed $ugrID - Workgroup id, if it is omitted it takes current user ID
    */
    function is_admin2($ugrID){

        if(!$ugrID){
            $ugrID = $this->get_user_id();
        }
        if($this->is_admin() || $this->is_admin('group', $ugrID)){
            return $ugrID;
        }else{
            return false;
        }
    }


    /**
    * Returns true if current user is database owner admin or admin of given group (depends on context)
    *
    * @param mixed $contx - databasde or group
    * @param mixed $ug - group ID
    */
    public function is_admin($contx = 'database', $ug = 0){

        if($this->get_user_id()<1) return false;     //not logged in

        switch ($contx) {
            case 'group':
                if ($ug == 0 || $ug == $this->get_user_id()) return true; //itself
                if ($ug > 0){
                    return ( "admin" == @$this->current_User['ugr_Groups'][$ug] );
                }
                return false;
                break;
            case 'database':
            default:
                $sysvals = $this->get_system();
                return  ( "admin" ==  @$this->current_User['ugr_Groups'][$sysvals['sys_OwnerGroupID']] ); //admin in db owners group
        }
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
    * Restore session by cookie id, or start new session
    */
    private function start_my_session(){

        if (@$_COOKIE['heurist-sessionid']) {
            session_id($_COOKIE['heurist-sessionid']);
            session_cache_limiter('none');
            session_start();
        } else {   //session does not exist - create new one
            //session_id(sha1(rand()));
            session_start();
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
    }



    /**
    * Load user info from session
    */
    public function login_verify(){
        $userID = @$_SESSION[$this->dbname_full]['ugr_ID'];

        if(!$userID){
            // vsn 3 backward capability
            $h3session = $this->dbname_full.'.heurist';
            $userID = @$_SESSION[$h3session]['user_id'];
            if($userID){
                $_SESSION[$this->dbname_full]['ugr_ID']       = $_SESSION[$h3session]['user_id'];
                $_SESSION[$this->dbname_full]['ugr_Name']     = $_SESSION[$h3session]['user_name'];
                $_SESSION[$this->dbname_full]['ugr_FullName'] = $_SESSION[$h3session]['user_realname'];
                $_SESSION[$this->dbname_full]['keepalive']    = @$_SESSION[$h3session]['keepalive'];
            }
        }

        $islogged = ($userID != null);
        if($islogged){

            if(!@$_SESSION[$this->dbname_full]['ugr_Groups']){
                $_SESSION[$this->dbname_full]['ugr_Groups'] = user_getWorkgroups( $this->mysqli, $userID );
            }
            if(!@$_SESSION[$this->dbname_full]['ugr_Preferences']){
                $_SESSION[$this->dbname_full]['ugr_Preferences'] = user_getDefaultPreferences();
            }

            $this->current_User = array(
                'ugr_ID'=>intval($userID),
                'ugr_FullName'=>$_SESSION[$this->dbname_full]['ugr_FullName'],
                'ugr_Groups' => $_SESSION[$this->dbname_full]['ugr_Groups'],
                'ugr_Preferences' => $_SESSION[$this->dbname_full]['ugr_Preferences']);

            if (@$_SESSION[$this->dbname_full]['keepalive']) {
                //update cookie - to keep it alive for next 30 days
                setcookie('heurist-sessionid', session_id(), time() + 30*24*60*60, '/');//, HEURIST_SERVER_NAME);
            }

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

            //db_users
            $user = user_getByField($this->mysqli, 'ugr_Name', $username);

            if($user){

                if($user['ugr_Enabled'] != 'y'){

                    $this->addError(HEURIST_REQUEST_DENIED,  "Your user profile is not active. Please contact database owner");
                    return false;

                }else if ( crypt($password, $user['ugr_Password']) == $user['ugr_Password'] ) {

                    $_SESSION[$this->dbname_full]['ugr_ID'] = $user['ugr_ID'];
                    $_SESSION[$this->dbname_full]['ugr_Name'] = $user['ugr_Name'];
                    $_SESSION[$this->dbname_full]['ugr_FullName'] = $user['ugr_FirstName'] . ' ' . $user['ugr_LastName'];
                    //@todo $_SESSION[$this->dbname_full]['user_access'] = $groups;
                    //$_SESSION[$this->dbname_full]['cookie_version'] = COOKIE_VERSION;

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

                    //update login time in database
                    user_updateLoginTime($this->mysqli, $user['ugr_ID']);

                    //keep current user info
                    $user['ugr_FullName'] = $user['ugr_FirstName'] . ' ' . $user['ugr_LastName'];
                    $user['ugr_Password'] = '';
                    $user['ugr_Groups'] = user_getWorkgroups( $this->mysqli, $user['ugr_ID'] );
                    $user['ugr_Preferences'] = user_getDefaultPreferences();
                    $this->current_User = $user;
                    /*
                    $this->current_User = array(
                    'ugr_ID'=>$user['ugr_ID'],
                    'ugr_FullName'=>$user['ugr_FirstName'] . ' ' . $user['ugr_LastName'],
                    'ugr_Groups' => user_getWorkgroups( $this->mysqli, $user['ugr_ID'] ),
                    'ugr_Preferences' => user_getPreferences() );
                    */

                    //header('Location: http://localhost/h4/index.php?db='.$this->dbname);

                    //vsn 3 backward capability
                    $h3session = $this->dbname_full.'.heurist';
                    $_SESSION[$h3session]['cookie_version'] = 1;
                    $_SESSION[$h3session]['user_name']     = $user['ugr_Name'];
                    $_SESSION[$h3session]['user_realname'] = $user['ugr_FullName'];
                    $_SESSION[$h3session]['user_id']       = $user['ugr_ID'];
                    $_SESSION[$h3session]['user_access']   = $user['ugr_Groups'];
                    $_SESSION[$h3session]['keepalive']     = ($session_type == 'remember');

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
        $cres = setcookie('heurist-sessionid', "", time() - 3600);
        $this->current_User = null;
        session_destroy();
        /*
        unset($_SESSION[$this->dbname_full]['user_id']);
        unset($_SESSION[$this->dbname_full]['user_name']);
        unset($_SESSION[$this->dbname_full]['user_realname']);
        */
        return true;
    }

    //
    //
    //
    public function user_GetPreference($property){

        $res = @$_SESSION[$this->dbname_full]["ugr_Preferences"][$property];

        if('search_detail_limit'==$property){
            if(!$res && $res<500 ) {$res = 500;}
            else if($res>30000 ) {$res = 3000;}
        }

        return $res;
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

            // it is required for main page only - so call this request on index.php
            //$this->system_settings['sys_RecordCount'] = mysql__select_value($mysqli, 'select count(*) from Records');
        }
        return ($fieldname) ?@$this->system_settings[$fieldname] :$this->system_settings;
    }

}
?>
