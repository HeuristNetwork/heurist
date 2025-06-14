<?php
/**
* System.php - Class System
* 
* This file defines the System class, which is the core of the Heurist application.
* It handles system initialization, database connection, user authentication, session management,
* and provides access to system settings and constants.
*
* @package     Heurist academic knowledge management system
* @subpackage  hserv
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/
namespace hserv;

use hserv\structure\ConceptCode;
use hserv\utilities\USystem;
use hserv\utilities\USanitize;
use hserv\SystemSettings;


require_once dirname(__FILE__).'/structure/dbsUsersGroups.php';
require_once dirname(__FILE__).'/structure/import/dbsImport.php';

set_error_handler('bootErrorHandler');   //see const.php

/**
 * Class System
 * 
 * The System class is the central class in Heurist, responsible for managing the application's state and core functionalities.
 *
 * It handles:
 * - System initialization: Establishing database connection, defining system constants (paths), etc.
 * - User authentication and session management: Logging users in, verifying credentials, and managing session data.
 * - Access to system settings: Providing an interface to system-wide configuration parameters.
 * - Error handling: Collecting and reporting errors that occur during script execution.
 * - Management of system-level constants for record types, detail types, and terms.
 * - File system operations related to Heurist data storage.
 *
 * Key properties:
 * - `$mysqli`: The MySQLi database connection object.
 * - `$currentUser`: An array containing information about the currently logged-in user.
 * - `$settings`: An instance of the `SystemSettings` class, providing access to system configuration.
 * - `$errors`: An array storing any errors encountered.
 *
 * System constants initialized by this class include:
 * - `HEURIST_THUMB_DIR`: Path to the directory for storing thumbnails.
 * - `HEURIST_FILESTORE_DIR`: Path to the main filestore directory for the current database.
 */
class System {

    /**
     * The MySQLi database connection object.
     * @var \mysqli|null
     */
    private $mysqli = null;

    /**
     * The full name of the database, including any prefix.
     * @var string|null
     */
    private $dbnameFull = null;

    /**
     * The short name of the database, without any prefix.
     * @var string|null
     */
    private $dbname = null;

    /**
     * An array to store error messages. Each error is an array with keys like 'status', 'message', 'sysmsg', 'error_title'.
     * @var array
     */
    private $errors = array();

    /**
     * Flag indicating whether the system has been successfully initialized.
     * @var bool
     */
    private $isInited = false;

    /**
     * An array containing information about the currently logged-in user.
     * Null if no user is logged in.
     * Structure includes: 'ugr_ID', 'ugr_Name', 'ugr_FullName', 'ugr_eMail', 'ugr_Groups', 'ugr_Permissions', 'ugr_Preferences'.
     * @var array|null
     */
    private $currentUser = null;

    /**
     * Flag to determine if a full session check (including session folder writability) is needed.
     * If true, it loads only basic user information without checking the session folder.
     * @var bool
     */
    private $needFullSessionCheck = false;

    /**
     * Instance of the SystemSettings class, providing access to system configuration.
     * @var SystemSettings
     */
    public $settings;

    /*

    init
    setDbnameFull
    init_db_connection - connect to server and select database (move to db_utils?)
    initPathConstants  - set path constants
    loginVerify  - load user info from session or reloads from database

    login
    loginVerify



    */

    /**
     * System constructor.
     *
     * @param bool $full_check Optional. If true, performs a full session check, including session folder writability.
     *                         Defaults to false, which means only basic user info is loaded without checking the session folder.
     */
    public function __construct( $full_check=false ) {

        $this->needFullSessionCheck = $full_check;

        $this->settings = new SystemSettings($this);
    }

    /**
     * Initializes the system by reading configuration parameters, establishing a database connection,
     * and setting up the session and path constants.
     *
     * @param string $db The name of the database to connect to.
     * @param bool $dbrequired Optional. If false, only connects to the server without selecting a specific database (e.g., for listing databases). Defaults to true.
     * @param bool $init_session_and_constants Optional. If true, initializes the session and defines path constants. Defaults to true.
     * @return bool True on successful initialization, false otherwise.
     */
    public function init($db, $dbrequired=true, $init_session_and_constants=true){

        $this->isInited = false;
        
        if( !$this->setDbnameFull($db, $dbrequired) ){
            return false;
        }

        $res = mysql__init($this->dbnameFull);
        if (is_a($res, 'mysqli')){
            //connection OK
            $this->mysqli = $res;
        }else{
            //connection failed
            $this->addErrorArr($res);
            return false;
        }

        if($this->dbnameFull && !defined('HEURIST_DBNAME')){
            //init once for first system - preferable use methods
            define('HEURIST_DBNAME', $this->dbname);
            define('HEURIST_DBNAME_FULL', $this->dbnameFull);
        }
        
        if(!$this->dbnameFull && !$dbrequired){
            $this->isInited = true; 
        }elseif(!$init_session_and_constants){
            $this->isInited = true;
        }elseif($this->startMySession( $this->needFullSessionCheck )
            && $this->initPathConstants()){

            if($this->needFullSessionCheck){
                USystem::executeScriptOncePerDay();
            }

            $this->loginVerify( false );//load user info from session on system init
            if($this->getUserId()>0){
                //set current user for stored procedures (log purposes)
                $this->mysqli->query('set @logged_in_user_id = '.intval($this->getUserId()));
            }

            //ONLY_FULL_GROUP_BY,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO
            $this->mysqli->query('SET GLOBAL sql_mode = \'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION\'');

            $this->isInited = true;
        }

        return $this->isInited;

    }

    /**
     * Closes the database connection if it's open.
     *
     * @return void
     */
    public function dbclose(){

        if($this->mysqli && isset($this->mysqli->server_info)){
            $this->mysqli->close();
        }
        $this->mysqli = null;

    }


    //------------------------- RT DT CONSTANTS --------------------
    /**
     * Defines all record type (RT), data type (DT), and term (TRM) constants.
     * These constants map magic strings (e.g., 'RT_PERSON') to their local IDs in the current database.
     *
     * @param bool $reset Optional. If true, forces a refresh of the underlying lookup tables for RT and DT constants. Defaults to false.
     * @return void
     */
    public function defineConstants($reset=false) {

        // Record type constants
        global $rtDefines;
        foreach ($rtDefines as $str => $id){
            if(!defined($str)){
                $this->defineRTLocalMagic($str, $id[1], $id[0], $reset);
            }
        }

        // Data type constants
        global $dtDefines;
        foreach ($dtDefines as $str => $id){
            if(!defined($str)){
                $this->defineDTLocalMagic($str, $id[1], $id[0], $reset);
            }
        }

        // Term constants
        global $trmDefines;
        foreach ($trmDefines as $str => $id){
            if(!defined($str)){
                $this->defineTermLocalMagic($str, $id[1], $id[0]);
            }
        }

    }

    /**
     * Defines a constant with a given name and value if it's not already defined.
     *
     * @param string $const_name The name of the constant.
     * @param mixed $value The value of the constant.
     * @return void
     */
    public function defineConstant2($const_name, $value) {
        if(!defined($const_name)){
            define($const_name, $value);
        }
    }

    /**
     * Returns the value of a constant. If the constant is not defined, it attempts to define it
     * (for RT, DT, TRM constants) and then returns its value. If it still cannot be defined or found,
     * it returns the default value.
     *
     * @param string $const_name The name of the constant.
     * @param mixed $def Optional. The default value to return if the constant cannot be found/defined. Defaults to null.
     * @return mixed The value of the constant or the default value.
     */
    public function getConstant($const_name, $def=null) 
    {
        return $this->defineConstant($const_name) ?constant($const_name) :$def;
    }

    /**
     * Initializes a single constant if it's not already defined.
     * This is primarily used for RT, DT, and TRM constants.
     *
     * @param string $const_name The name of the constant to define.
     * @param bool $reset Optional. If true and the constant is an RT or DT constant,
     *                    it forces a refresh of the underlying lookup tables. Defaults to false.
     * @return bool True if the constant is now defined, false otherwise.
     */
    public function defineConstant($const_name, $reset=false) {

        if(defined($const_name)){
            return true;
        }else{
            global $rtDefines;
            global $dtDefines;
            global $trmDefines;
            if(@$rtDefines[$const_name]){
                $this->defineRTLocalMagic($const_name, $rtDefines[$const_name][1], $rtDefines[$const_name][0], $reset);
            }elseif(@$dtDefines[$const_name]){
                $this->defineDTLocalMagic($const_name, $dtDefines[$const_name][1], $dtDefines[$const_name][0], $reset);
            }elseif(@$trmDefines[$const_name]){
                $this->defineTermLocalMagic($const_name, $trmDefines[$const_name][1], $trmDefines[$const_name][0]);
            }
            return defined($const_name);
        }
    }

    /**
     * Retrieves the configuration for third-party web services and their mapping to Heurist record types and fields.
     * The configuration is read from `controller/record_lookup_config.json`.
     *
     * @return array|null An array of web service configurations, or null if the config file doesn't exist or is invalid.
     *                    Each configuration includes 'rty_ID' and 'fields' mapping.
     */
    private function getWebServiceConfigs(){

        //read service_mapping.json from setting folder
        $config_file = dirname(__FILE__).'/controller/record_lookup_config.json';

        if(!file_exists($config_file)){
            return null;
        }

        $json = file_get_contents($config_file);

        $config = json_decode($json, true);
        if(!is_array($config)){
            return null;
        }

        $config_res = array();

        foreach($config as $cfg){

            $rty_ID = ConceptCode::getRecTypeLocalID($cfg['rty_ID']);

            $cfg['rty_ID'] = $rty_ID;

            foreach($cfg['fields'] as $field=>$code){

                $extra = '_';

                if(strpos($code, '_') !== false){
                    $parts = explode('_', $code);
                    $code = $parts[0];
                    $extra .= $parts[1];
                }

                $dty_ID = ConceptCode::getDetailTypeLocalID($code);

                if($dty_ID != null && $extra != '_'){
                    $dty_ID .= $extra;
                }

                $cfg['fields'][$field] = $dty_ID;
            }

            $config_res[] = $cfg;
            //}
            //}
        }

        return $config_res;
    }


    /**
     * Gets all defined RT, DT, and TRM constants as an associative array (constant name => value).
     * This is typically used to pass these constants to the client-side.
     *
     * @param bool $reset Optional. If true, forces a refresh of the underlying lookup tables for RT and DT constants
     *                    before retrieving them. Defaults to false.
     * @return array An associative array of defined constants.
     */
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

        // Term constants
        global $trmDefines;
        foreach ($trmDefines as $magicTermName => $id) {
            if(defined($magicTermName)){
                $res[$magicTermName] = constant ( $magicTermName );
            }
        }


        return $res;
    }

    /**
    * bind Concept Number Constants to their local id
    * @param string $defString The string name of the constant to define (e.g., 'RT_PERSON').
    * @param int $rtID The original record type ID from the originating database.
    * @param int $dbID The ID of the originating database (defaults to 2, the coreDefinition database).
    * @param bool $reset Optional. If true, forces a refresh of the rectype lookup table. Defaults to false.
    * @return void
    */
    private function defineRTLocalMagic($defString, $rtID, $dbID, $reset=false) {

        $id = $this->rectypeLocalIDLookup($rtID, $dbID, $reset);

        if ($id) {
            define($defString, $id);
        }
    }


    /**
    * Looks up the local ID for a given original record type ID and its originating database ID.
    * It uses a static cache (`$rtyIDs`) for performance, which can be reset.
    * If a record type's originating DB ID is not positive and a system registered ID exists,
    * it assumes the record type originates from the current database instance.
    *
    * @staticvar array $rtyIDs A static array used as a cache for local record type IDs, structured as `$rtyIDs[dbID][originalRtID] = localRtID`.
    * @param int $rtID The original record type ID from the originating database.
    * @param int $dbID Optional. The ID of the originating database. Defaults to 2 (coreDefinition).
    * @param bool $reset Optional. If true, forces a rebuild of the `$rtyIDs` cache. Defaults to false.
    * @return int|null The local record type ID if found, otherwise null. Exits on database error.
    */
    private function rectypeLocalIDLookup($rtID, $dbID = 2, $reset=false) {
        static $rtyIDs;

        if (!$rtyIDs || $reset) {
            $res = $this->mysqli->query('select rty_ID as localID,
            rty_OriginatingDBID as dbID, rty_IDInOriginatingDB as id from defRecTypes order by dbID');
            if (!$res) {
                $this->addError(HEURIST_DB_ERROR, 'Unable to build internal record-type lookup table', $this->mysqli->error);
                exit; // Critical error, cannot proceed
            }

            $regID = $this->settings->get('sys_dbRegisteredID');

            $rtyIDs = array();
            while ($row = $res->fetch_assoc()) {

                if( !isPositiveInt($row['dbID']) && $regID > 0){
                    // If original DB ID is missing/invalid and this DB has a registered ID,
                    // assume it's a locally defined type.
                    $row['dbID'] = $regID;
                    $row['id'] = $row['localID']; // The original ID in this case is the local ID.
                }

                if (!isset($rtyIDs[$row['dbID']])) {
                    $rtyIDs[$row['dbID']] = array();
                }
                $rtyIDs[$row['dbID']][$row['id']] = $row['localID'];
            }
        }
        return isset($rtyIDs[$dbID][$rtID]) ? $rtyIDs[$dbID][$rtID] : null;
    }


    /**
     * Binds a "magic number" constant for a detail type (DT) to its local ID in the current database.
     * For example, defines 'DT_TITLE' with the local ID of the 'Title' detail type.
     *
     * @param string $defString The string name of the constant to define (e.g., 'DT_TITLE').
     * @param int $dtID The original detail type ID from the originating database.
     * @param int $dbID The ID of the originating database.
     * @param bool $reset Optional. If true, forces a refresh of the detail type lookup table. Defaults to false.
     * @return void
     */
    private function defineDTLocalMagic($defString, $dtID, $dbID, $reset=false) {
        $id = $this->detailtypeLocalIDLookup($dtID, $dbID, $reset);
        if ($id) {
            define($defString, $id);
        }
    }


    /**
     * Looks up the local ID for a given original detail type ID and its originating database ID.
     * It uses a static cache (`$dtyIDs`) for performance, which can be reset.
     * If a detail type's originating DB ID is not positive and a system registered ID exists,
     * it assumes the detail type originates from the current database instance.
     *
     * @staticvar array $dtyIDs A static array used as a cache for local detail type IDs, structured as `$dtyIDs[dbID][originalDtID] = localDtID`.
     * @param int $dtID The original detail type ID from the originating database.
     * @param int $dbID Optional. The ID of the originating database. Defaults to 2 (coreDefinition).
     * @param bool $reset Optional. If true, forces a rebuild of the `$dtyIDs` cache. Defaults to false.
     * @return int|null The local detail type ID if found, otherwise null. Exits on database error.
     */
    private function detailtypeLocalIDLookup($dtID, $dbID = 2, $reset=false) {
        static $dtyIDs;

        if (!$dtyIDs || $reset) {
            $res = $this->mysqli->query('select dty_ID as localID,dty_OriginatingDBID as dbID,dty_IDInOriginatingDB as id from defDetailTypes order by dbID');
            if (!$res) {
                // Output error message directly as this is a critical failure during constant definition
                echo "Unable to build internal field-type lookup table. Please ".CONTACT_SYSADMIN
                ." for assistance. MySQL error: " . $this->mysqli->error;
                exit; // Critical error, cannot proceed
            }

            $regID = $this->settings->get('sys_dbRegisteredID');

            $dtyIDs = array();
            while ($row = $res->fetch_assoc()) {

                if( !isPositiveInt($row['dbID']) && $regID > 0){
                    // If original DB ID is missing/invalid and this DB has a registered ID,
                    // assume it's a locally defined type.
                    $row['dbID'] = $regID;
                    $row['id'] = $row['localID']; // The original ID in this case is the local ID.
                }

                if (!isset($dtyIDs[$row['dbID']])) {
                    $dtyIDs[$row['dbID']] = array();
                }
                $dtyIDs[$row['dbID']][$row['id']] = $row['localID'];
            }
        }
        return isset($dtyIDs[$dbID][$dtID]) ? $dtyIDs[$dbID][$dtID] : null;
    }

    /**
     * Binds a "magic number" constant for a term (TRM) to its local ID in the current database.
     * For example, defines 'TRM_SOME_TERM' with the local ID of the specified term.
     *
     * @param string $defString The string name of the constant to define (e.g., 'TRM_MY_TERM').
     * @param int $trmID The original term ID from the originating database.
     * @param int $dbID The ID of the originating database.
     * @return void
     */
    private function defineTermLocalMagic($defString, $trmID, $dbID) {

        $id = ConceptCode::getTermLocalID($dbID.'-'.$trmID);
        if ($id) {
            define($defString, $id);
        }
    }



    //------------------------- END RT DT and TERM CONSTANTS --------------------

    /**
     * Gets the root folder path for the Heurist filestore.
     * It prioritizes the `HEURIST_FILESTORE_ROOT` constant if defined (which is set during `initPathConstants`).
     * Otherwise, it falls back to `$defaultRootFileUploadPath` from `configIni.php` or constructs a default path
     * based on `$_SERVER['DOCUMENT_ROOT']` and 'HEURIST/HEURIST_FILESTORE/'.
     * Ensures the path has a trailing slash and, if not an absolute path starting with ':/' or '/', prepends a leading slash.
     *
     * @global string $defaultRootFileUploadPath The default root file upload path from `configIni.php`.
     * @return string The absolute path to the filestore root folder.
     */
    public function getFileStoreRootFolder(){

        global $defaultRootFileUploadPath;

        if (!isEmptyStr($defaultRootFileUploadPath)) {

            if ($defaultRootFileUploadPath != "/" && !preg_match("/[^\/]\/$/", $defaultRootFileUploadPath)) { //check for trailing /
                $defaultRootFileUploadPath.= "/";// append trailing /
            }

            if ( !strpos($defaultRootFileUploadPath,":/") && $defaultRootFileUploadPath != "/" && !preg_match("/^\/[^\/]/", $defaultRootFileUploadPath)) {
                //check for leading /
                $defaultRootFileUploadPath = "/" . $defaultRootFileUploadPath; // prepend leading /
            }

            return $defaultRootFileUploadPath;

        }else{

            $install_path = 'HEURIST/';
            $dir_Filestore = "HEURIST_FILESTORE/";

            $documentRoot = @$_SERVER['DOCUMENT_ROOT'];
            if( $documentRoot && substr($documentRoot, -1, 1) != '/' ) {$documentRoot = $documentRoot.'/';}


            return  $documentRoot . $install_path . $dir_Filestore;

        }
    }

    /**
    *  Returns three values array for each system folder
    *  Returns an array describing various system folders within a Heurist database's filestore.
    *  Each element in the returned array represents a folder and contains:
    *  - 0: Suffix for the constant name (e.g., 'THUMB' for `HEURIST_THUMB_DIR`). Null if no constant.
    *  - 1: Description of the folder's purpose.
    *  - 2: (Optional) Boolean indicating if web access is allowed (defaults to false).
    *  - 3: (Optional) Boolean indicating if the folder should be backed up (defaults to false, true for some core folders).
    *
    * @global bool $allowWebAccessThumbnails Configuration for web access to thumbnails.
    * @global bool $allowWebAccessUploadedFiles Configuration for web access to uploaded files (not directly used here but for context).
    * @global bool $allowWebAccessEntityFiles Configuration for web access to entity files (icons, etc.).
    * @param bool $is_for_backup Optional. If true, returns a list of folders relevant for backup purposes.
    *                            Defaults to false, returning a more comprehensive list for general system operations.
    * @return array An associative array where keys are folder names (relative to the DB filestore root)
    *               and values are arrays describing the folder properties.
    */
    public function getArrayOfSystemFolders($is_for_backup=false){

        global $allowWebAccessThumbnails, $allowWebAccessUploadedFiles, $allowWebAccessEntityFiles;

        //const name, description, allow webaccess, for backup
        $folders = array();

        $folders['filethumbs']   = array('THUMB','used to store thumbnails for uploaded files', $allowWebAccessThumbnails, true);
        $folders['hml-output']   = array('HML','used to write published records as hml files', true);
        $folders['html-output']  = array('HTML','used to write published records as generic html files', true);
        $folders['smarty-templates']  = array('SMARTY_TEMPLATES','', false, true);
        $folders['settings']      = array('SETTING','', false, true);
        // do not create constant (if name is empty)
        $folders['xsl-templates'] = array('XSL_TEMPLATES','', false, true);


        if(!$is_for_backup)
        {
            $folders['file_uploads'] = array('FILES','used to store uploaded files by default');
            //besides we have HEURIST_SCRATCHSPACE_DIR == sys temp dir
            $folders['scratch']      = array('SCRATCH','used to store temporary files', false);

            $folders['generated-reports'] = array(null,'used to write generated reports');
            $folders['entity']        = array(null,'used to store icons and images for record types users,groups,terms', $allowWebAccessEntityFiles);
            $folders['backup']        = array(null,'used to write files for user data dump');
            $folders['uploaded_tilestacks'] = array('TILESTACKS','used to store uploaded map tiles', true, false);
            //since 2023-06-02 $folders['documentation'] = array('','', false, false);
            $folders['faims']    = array('','');
            $folders['blurredimagescache'] = array(null,'(for blurred due to visibility settings)', true, false);
            $folders['webimagecache'] = array(null,'(for cached web images)', true, false);
        }



        return $folders;
    }

    //
    // $is_for_backup - 0 no, 1 - archive backup, 2 - delete backup
    // returns ar
    //

    /**
    * Returns an array of absolute paths to ALL standard system folders for a given database.
    *
    * @param string|null $database_name Optional. The short name of the database.
    *                                   If null, uses the current database associated with this System instance.
    * @return string[] An array of absolute paths to the system folders, each ending with a slash.
    */
    public function getSystemFolders($database_name=null){

        $folders = $this->getArrayOfSystemFolders();

        $system_folders = array();

        $dbfolder = $this->getSysDir(null, $database_name); //root db folder

        foreach ($folders as $folder_name=>$folder){
            $folder_name = $dbfolder.$folder_name;
            $folder_name = str_replace('\\', '/', $folder_name);
            array_push($system_folders, $folder_name.'/');
        }//for

        return $system_folders;
    }

    /**
    * Returns the absolute path to a specified subfolder within a Heurist database's filestore.
    * If `$folder_name` is null, returns the path to the root filestore directory for the specified database.
    *
    * @param string|null $folder_name Optional. The name of the subfolder (e.g., 'filethumbs', 'hml-output').
    *                                 If null, the path to the database's root filestore directory is returned.
    * @param string|null $database_name Optional. The short name of the database.
    *                                   If null, uses the current database associated with this System instance.
    * @return string|null The absolute path to the folder, ending with a slash, or null if the database name is invalid.
    */
    public function getSysDir($folder_name=null, $database_name=null){
        return $this->getSysFolderRes('path', $folder_name, $database_name);
    }

    /**
     * Returns the absolute URL to a specified subfolder within a Heurist database's filestore.
     * If `$folder_name` is null, returns the URL to the root filestore directory for the specified database.
     *
     * @param string|null $folder_name Optional. The name of the subfolder (e.g., 'filethumbs', 'hml-output').
     *                                 If null, the URL to the database's root filestore directory is returned.
     * @param string|null $database_name Optional. The short name of the database.
     *                                   If null, uses the current database associated with this System instance.
     * @return string|null The absolute URL to the folder, ending with a slash, or null if the database name is invalid.
     */
    public function getSysUrl($folder_name=null, $database_name=null){
        return $this->getSysFolderRes('url', $folder_name, $database_name);
    }

    /**
     * Gets either the path or URL for a system folder.
     * This is a helper method used by `getSysDir` and `getSysUrl`.
     *
     * @global string $defaultRootFileUploadURL The default root file upload URL from `configIni.php`.
     *
     * @param string $type Either 'path' or 'url'.
     * @param string|null $folder_name Optional. The name of the subfolder (e.g., 'filethumbs').
     *                                 If null, the root for the database is returned.
     * @param string|null $database_name Optional. The short name of the database.
     *                                   If null, uses the current database.
     * @return string|null The absolute path or URL, ending with a slash, or null if the database name is invalid.
     */
    private function getSysFolderRes($type, $folder_name=null, $database_name=null){
        global $defaultRootFileUploadURL;

        if($type=='url'){
            $db_root = $defaultRootFileUploadURL;
        }else{
            $db_root = defined('HEURIST_FILESTORE_ROOT')
                            ? HEURIST_FILESTORE_ROOT
                            : $this->getFileStoreRootFolder();
        }

        $database_name = $database_name ?? $this->dbname;

        if(empty($database_name) || preg_match('/[^A-Za-z0-9_\$]/', $database_name)){
            return null; //invalid database name or not initialized
        }

        $dbres = rtrim($db_root, '/') . '/' . $database_name . '/';

        if($folder_name !== null){
            $dir = USanitize::sanitizePath($folder_name); // Sanitize to prevent directory traversal
            $dbres .= rtrim($dir, '/') . '/';
        }

        return $dbres;
    }

    /**
     * Initializes path constants related to the filestore for a given database (or the current one).
     * It defines `HEURIST_FILESTORE_ROOT`, `HEURIST_FILESTORE_DIR`, `HEURIST_FILESTORE_URL`,
     * and constants for various subdirectories (e.g., `HEURIST_THUMB_DIR`, `HEURIST_THUMB_URL`).
     * It also checks for the existence and writability of these directories, creating them if necessary.
     *
     * @global string $defaultRootFileUploadPath The default root file upload path from `configIni.php`.
     * @global string $defaultRootFileUploadURL The default root file upload URL from `configIni.php`.
     *
     * @param string|null $dbname Optional. The short name of the database (without prefix).
     *                            If null, uses the current database name.
     * @return bool True on success, false on failure (e.g., if a directory cannot be accessed or created).
     */
    public function initPathConstants($dbname=null){

        global $defaultRootFileUploadPath, $defaultRootFileUploadURL;

        if(defined('HEURIST_FILESTORE_URL')){
            return true; //already defined
        }

        list($database_name_full, $dbname) = mysql__get_names($dbname);
        if(mysql__check_dbname($dbname)!=null) {return false;}

        $upload_root = $this->getFileStoreRootFolder();

        if (isEmptyStr($defaultRootFileUploadPath)) {

            //path is not configured in ini - set dafault values
            $install_path = 'HEURIST/';
            $dir_Filestore = "HEURIST_FILESTORE/";

            $defaultRootFileUploadURL = HEURIST_SERVER_URL . '/' . $install_path . $dir_Filestore;
        }

        $this->defineConstant2('HEURIST_FILESTORE_ROOT', $upload_root);
        $this->defineConstant2('HEURIST_FILESTORE_DIR', $upload_root . $dbname . '/');

        $check = folderExists(HEURIST_FILESTORE_DIR, true);
        if($check<0){

            $usr_msg = "Cannot access filestore directory for the database <b>". $dbname .
            "</b><br>The directory "
            .(($check==-1)
                ?"does not exist (check setting in heuristConfigIni.php file)"
                :"is not writeable by PHP (check permissions)")
            ."<br><br>On a multi-tier service, the file server may not have restarted correctly or "
            ."may not have been mounted on the web server.";


            $this->addError(HEURIST_SYSTEM_FATAL, $usr_msg, null, "Problem opening database");
            return false;
        }

        define('HEURIST_FILESTORE_URL', $defaultRootFileUploadURL . $dbname . '/');

        $folders = $this->getArrayOfSystemFolders();
        $warnings = array();

        foreach ($folders as $folder_name=>$folder){

            if(isEmptyStr($folder[0])) { continue; }

            $allowWebAccess = (@$folder[2]===true);

            $dir = HEURIST_FILESTORE_DIR.$folder_name.'/';

            $warn = folderCreate2($dir, $folder[1], $allowWebAccess);
            if($warn!=''){ //can't creat or not writeable
                $warnings[] = $warn;
                continue;
            }

            //it defines constants HEURIST_[FOLDER]_DIR and HEURIST_[FOLDER]_URL
            define('HEURIST_'.$folder[0].'_DIR', $dir);
            if($allowWebAccess){
                define('HEURIST_'.$folder[0].'_URL', HEURIST_FILESTORE_URL.$folder_name.'/');
            }
        }//for

        if(!empty($warnings)){
            $this->addError(HEURIST_SYSTEM_FATAL, implode('',$warnings));
            return false;
        }


        define('HEURIST_RTY_ICON', HEURIST_BASE_URL.'?db='.$dbname.'&icon=');//redirected to hserv/controller/fileGet.php

        return true;
    }

    /**
     * Checks if the system has been successfully initialized (i.e., `init()` method completed without errors).
     *
     * @return bool True if the system is initialized, false otherwise.
     */
    public function isInited(){
        return $this->isInited;
    }

    /**
     * Gets the MySQLi database connection object.
     *
     * @return \mysqli|null The MySQLi connection object, or null if not connected.
     */
    public function getMysqli(){
        return $this->mysqli;
    }

    /**
     * Sets the MySQLi database connection object.
     *
     * @param \mysqli|null $mysqli The MySQLi connection object.
     * @return void
     */
    public function setMysqli($mysqli){
        $this->mysqli = $mysqli;
    }

    /**
     * Gets the full name of the current database (including prefix).
     *
     * @return string|null The full database name, or null if not set.
     */
    public function dbnameFull(){
        return $this->dbnameFull;
    }

    /**
     * Gets the short name of the current database (without prefix).
     *
     * @return string|null The short database name, or null if not set.
     */
    public function dbname(){
        return $this->dbname;
    }

    /**
     * Sets the full and short database names based on the provided database identifier.
     * It also performs basic validation on the database name.
     *
     * @param string $db The database identifier (can be full name or short name).
     * @param bool $dbrequired Optional. If true and the database name is invalid or cannot be resolved,
     *                         an error will be added and the method will return false. Defaults to true.
     * @return bool True if the database names were set successfully (or if `$dbrequired` is false and name is invalid),
     *              false otherwise (only if `$dbrequired` is true and an error occurs).
     */
    public function setDbnameFull($db, $dbrequired=true){

        $error = mysql__check_dbname($db);

        if($error==null && preg_match('/[A-Za-z0-9_\$]/', $db)){ //additional validatate database name for sonarcloud
            list($this->dbnameFull, $this->dbname ) = mysql__get_names( $db );
        }else{
            $this->dbname = null;
            $this->dbnameFull = null;

            if($dbrequired){
                $this->addErrorArr($error);
                $this->mysqli = null;
                return false;
            }
        }
        return true;
    }


    /**
    /**
     * Outputs a JSON error response and terminates the script execution.
     * Closes the database connection before exiting.
     *
     * @param string|null $message The error message to display to the user. If null, uses existing error in $this->errors.
     * @param int|null $error_code Optional. The error code. Defaults to `HEURIST_INVALID_REQUEST` if a message is provided.
     * @return void This function calls `exit`.
     */
    public function errorExit( $message, $error_code=null) {

        $this->dbclose();

        header(CTYPE_JSON);
        if($message){
            if($error_code==null){
                $error_code = HEURIST_INVALID_REQUEST;
            }
            $this->addError($error_code, $message);
        }

        print json_encode( $this->getError() );

        exit;
    }

    /**
     * Outputs a JSON error response, typically for API endpoints, and terminates script execution.
     * Sets appropriate HTTP status codes based on the Heurist error code.
     * Closes the database connection before exiting.
     *
     * @param string|null $message Optional. The error message. If provided, an error with this message will be added.
     * @param int|null $error_code Optional. The Heurist error code. Defaults to `HEURIST_INVALID_REQUEST` if a message is provided.
     * @param bool $is_api Optional. If true (default), sets CORS headers and HTTP status codes appropriate for an API.
     *                     If false, behaves more like `errorExit` but still uses the error structure.
     * @return void This function calls `exit`.
     */
    public function errorExitApi( $message=null, $error_code=null, $is_api=true) {

        $this->dbclose();

        if($message){
            if($error_code==null){
                $error_code = HEURIST_INVALID_REQUEST;
            }
            $this->addError($error_code, $message);
        }

        $response = $this->getError();


        if($is_api){
            header(HEADER_CORS_POLICY);
            header(CTYPE_JSON);

            $status = @$response['status'];
            if($status==HEURIST_INVALID_REQUEST){
                $code = 400; // Bad Request - the request could not be understood or was missing required parameters.
            }elseif($status==HEURIST_REQUEST_DENIED) {
                $code = 403; // Forbidden - access denied
            }elseif($status==HEURIST_NOT_FOUND){
                $code = 404; //Not Found - resource was not found.
            }elseif($status==HEURIST_ACTION_BLOCKED) {
                $code = 409; //cannot add an existing object already exists or constraints violation
            }else{
                //HEURIST_ERROR, HEURIST_UNKNOWN_ERROR, HEURIST_DB_ERROR, HEURIST_SYSTEM_CONFIG, HEURIST_SYSTEM_FATAL
                $code = 500; //An unexpected internal error has occurred. Please contact Support for more information.
            }

            http_response_code($code);
        }else{
            header(CTYPE_JSON);
        }

        print json_encode( $response );

        exit;
    }

    /**
     * Prepends a message to the existing error message. If no error is currently set,
     * it creates a new error with the given message and status `HEURIST_ERROR`.
     *
     * @param string $message The message to prepend.
     * @return void
     */
    public function addErrorMsg($message) {
        if($this->errors && isset($this->errors['message'])){
            $this->errors['message']  = $message . $this->errors['message'];
        }else{
            $this->addError(HEURIST_ERROR, $message);
        }
    }

    /**
     * Adds an error to the internal error collection.
     * The input can be an array (typically from `mysql__` functions or remote requests)
     * or a simple string message.
     *
     * If `$error` is an array and has a 'message' key, it's treated as an error structure
     * (e.g., from a remote request).
     * If `$error` is an array without a 'message' key, it's assumed to be `[status, message, sysmsg, title]`.
     * If `$error` is not an array, it's treated as a message string with status `HEURIST_ERROR`.
     *
     * @param array|string $error The error information.
     *                            - Array from `mysql__*`: `[0 => status, 1 => message, 2 => sysmsg (optional), 3 => title (optional)]`
     *                            - Array from remote call: `['status' => ..., 'message' => ..., 'sysmsg' => ..., 'error_title' => ...]`
     *                            - String: Just the error message.
     * @return array The current error array stored in `$this->errors`.
     */
    public function addErrorArr($error) {
        if(!is_array($error)){
            // Just a message string - treat as a general error
            $error = array(HEURIST_ERROR, $error);
        }

        if(isset($error['message'])){
            // Error structure likely from a remote request or already processed
            $status = isset($error['status']) ? $error['status'] : HEURIST_ERROR;
            return $this->addError($status, $error['message'], @$error['sysmsg'], @$error['error_title']);
        } else {
            // Assumed array format [status, message, sysmsg, title] from mysql__ functions or direct creation
            return $this->addError($error[0], $error[1], isset($error[2]) ? $error[2] : null, isset($error[3]) ? $error[3] : null);
        }
    }

    /**
     * Handles serious errors by logging them and preparing a user-friendly message.
     * Serious errors are typically those that are not simple request denials or not found errors.
     * Logs the error to a daily error log file if `HEURIST_FILESTORE_ROOT` is defined.
     * Modifies the message for "MySQL server has gone away" errors to be more specific.
     *
     * @param int $status The Heurist error status code.
     * @param string $message The primary error message.
     * @param string|null $sysmsg Optional. A more technical system message.
     * @param string|null $title Optional. A title for the error.
     * @return void Sets `$this->errors`.
     */
    private function treatSeriousError($status, $message, $sysmsg, $title) {

        $now = getNow(); // global function
        $curr_logfile = 'errors_'.$now->format('Y-m-d').'.log';

        //3. write error into current error log
        $sTitle = 'db: '.preg_replace(REGEX_EOL, ' ', $this->dbname())
        ."\nerr-type: ".preg_replace(REGEX_EOL, ' ', $status)
        ."\nuser: ".$this->getUserId()
        .' '.@$this->currentUser['ugr_FullName']
        .' <'.@$this->currentUser['ugr_eMail'].'>';

        //clear sensetive info
        $sensetive = array('pwd','','chpwd','create_pwd','usrPassword','password');
        array_walk($sensetive,function($key){
            if(array_key_exists($key,$_REQUEST)){
                unset($_REQUEST[$key]);
            }
        });

        $sMsg = "\nMessage: ".preg_replace(REGEX_EOL, ' ', $message)."\n"
        .($sysmsg?'System message: '.$sysmsg."\n":'')
        .'Script: '.@$_SERVER['REQUEST_URI']."\n"
        .'Request: '.substr(print_r($_REQUEST, true),0,2000)."\n\n"
        ."------------------\n";

        if(defined('HEURIST_FILESTORE_ROOT')){
            $root_folder = HEURIST_FILESTORE_ROOT;
            fileAdd($sTitle.'  '.$sMsg, $root_folder.$curr_logfile);
        }

        $mysql_gone_away_error = $this->mysqli && $this->mysqli->errno==2006;
        if($mysql_gone_away_error){
            $message =  $message
            .' There is database server interruption. '.CRITICAL_DB_ERROR_CONTACT_SYSADMIN;
        }else{
            $message = "Heurist was unable to process this request. <br><strong>$message</strong><br>";
            $sysmsg = 'Although errors are emailed to the Heurist team (for servers maintained directly by the project),'
            .' there are several thousand Heurist databases, so we are unable to review all automated reports.'
            .'If this is the first time you have seen this error, please try again in a few minutes in case it is '
            .'a temporary network outage. Please contact us if this error persists and is causing you a problem,' 
            .'as this will help us identify important issues. We apologise for any inconvenience';
        }

        $this->errors = array("status"=>$status, "message"=>$message, "sysmsg"=>$sysmsg, 'error_title'=>$title);
    }


    /**
     * Adds an error message to the system's error collection.
     * If the status indicates a serious error (not invalid request, not found, request denied, or action blocked),
     * it calls `treatSeriousError` to log it and format the message.
     * Otherwise, it stores the error details directly.
     *
     * @param int $status The Heurist error status code (e.g., `HEURIST_ERROR`, `HEURIST_INVALID_REQUEST`).
     * @param string $message Optional. The user-friendly error message. Defaults to an empty string.
     * @param string|null $sysmsg Optional. A technical system message or additional details.
     *                            If status is `HEURIST_REQUEST_DENIED` and sysmsg is null, current user ID is used.
     * @param string|null $title Optional. A title for the error.
     * @return array The error array that was set (contains 'status', 'message', 'sysmsg', 'error_title').
     */
    public function addError($status, $message='', $sysmsg=null, $title=null) {

        if($status == HEURIST_REQUEST_DENIED && $sysmsg === null){
            $sysmsg = (string) $this->getUserId(); // Provide context for denial
        }

        // Check if it's a type of error that should be treated as serious (logged, etc.)
        $isSeriousError = !in_array($status, [
            HEURIST_INVALID_REQUEST,
            HEURIST_NOT_FOUND,
            HEURIST_REQUEST_DENIED,
            HEURIST_ACTION_BLOCKED
        ]);

        if($isSeriousError){
            $this->treatSeriousError($status, $message, $sysmsg, $title);
        }else{
            $this->errors = ["status" => $status, "message" => $message, "sysmsg" => $sysmsg, 'error_title' => $title];
        }

        return $this->errors;
    }

    /**
     * Returns the current error array.
     * The array typically contains 'status', 'message', 'sysmsg', and 'error_title' keys.
     *
     * @return array The error array. Returns an empty array if no errors are set.
     */
    public function getError(){
        return $this->errors;
    }

    /**
     * Gets the user-facing message from the current error array.
     *
     * @return string The error message, or an empty string if no error or message is set.
     */
    public function getErrorMsg(){
        return $this->errors['message'] ?? '';
    }

    /**
     * Clears any currently stored error information.
     *
     * @return void
     */
    public function clearError(){
        $this->errors = array();
    }


    /**
     * Retrieves the total number of non-temporary records in the database,
     * the count of active dashboard entries, and the current user's workset count.
     * Invoked on page initialization and after login.
     *
     * @return array An array containing three integers:
     *               - Total non-temporary records.
     *               - Count of active dashboard entries (respecting `dsh_ShowIfNoRecords` if no records).
     *               - Count of worksets for the current user (0 if not logged in).
     *               Returns `[0,0,0]` if the database connection is not available.
     */
    public function getTotalRecordsAndDashboard(){

        if( !$this->mysqli ){ return array(0,0,0); }


        $db_total_records = 0;
        $db_has_active_dashboard = 0;
        $db_workset_count = 0;

        $db_total_records = mysql__select_value($this->mysqli, 'SELECT count(*) FROM Records WHERE not rec_FlagTemporary');
        $db_total_records = ($db_total_records>0)?$db_total_records:0;

        if($this->hasAccess())
        {
            $query = 'select count(*) from sysDashboard where dsh_Enabled="y"';
            if($db_total_records<1){
                $query = $query.'AND dsh_ShowIfNoRecords="y"';
            }
            $db_has_active_dashboard = mysql__select_value($this->mysqli, $query);
            $db_has_active_dashboard = ($db_has_active_dashboard>0)?$db_has_active_dashboard:0;

            $curr_user_id = $this->getUserId();
            if($curr_user_id>0){
                $query = 'select count(*) from usrWorkingSubsets where wss_OwnerUGrpID='.$curr_user_id;
                $db_workset_count = mysql__select_value($this->mysqli, $query);
                $db_workset_count = ($db_workset_count>0)?$db_workset_count:0;
            }
        }

        return array($db_total_records, $db_has_active_dashboard, $db_workset_count);
    }

    /**
    * Retrieves comprehensive information about the current user and various system configuration parameters.
    * This method always reloads user information from the database by calling `loginVerify(true)`.
    *
    * Key information returned:
    * - `currentUser`: Detailed information about the logged-in user.
    * - `sysinfo`: A wide range of system settings, version information, URLs, database constants,
    *   service configurations, language settings, security-related flags (password presence), etc.
    *
    * @global string|null $passwordForDatabaseCreation Password for creating new databases.
    * @global string|null $passwordForDatabaseDeletion Password for deleting databases.
    * @global string|null $passwordForReservedChanges Password for modifying reserved fields.
    * @global string|null $passwordForServerFunctions Password for server-level functions.
    * @global int $needEncodeRecordDetails Flag indicating if record details need encoding.
    * @global array $saml_service_provides SAML service provider configurations.
    * @global bool $hideStandardLogin Flag to hide standard login form.
    * @global string|null $accessToken_DeepLAPI API key for DeepL translation.
    * @global bool|null $useRewriteRulesForRecordLink Whether to use URL rewriting for record links.
    * @global int $allowCMSCreation Flag indicating if CMS creation is allowed.
    * @global string|null $matomoUrl URL for Matomo analytics.
    * @global string|null $matomoSiteId Site ID for Matomo analytics.
    * @global string|null $accessToken_Matomo API token for Matomo.
    *
    * @param bool $include_reccount_and_dashboard_count Optional. If true, includes total record count,
    *                                                    active dashboard count, and workset count in `sysinfo`. Defaults to false.
    * @param bool $is_guest_allowed Optional. If true, allows guest user access even if the user account is normally disabled,
    *                               by setting 'guest_user' permission. Defaults to false.
    * @return array|false An associative array containing `currentUser` and `sysinfo`, or false on critical failure.
    *                     If the database is not connected, returns a minimal sysinfo structure.
    * @throws \Exception Catches exceptions during the process and sets an error.
    */
    public function getCurrentUserAndSysInfo( $include_reccount_and_dashboard_count=false, $is_guest_allowed=false )
    {
        // Access global configuration variables
        global $passwordForDatabaseCreation, $passwordForDatabaseDeletion,
               $passwordForReservedChanges, $passwordForServerFunctions,
               $needEncodeRecordDetails,
               $saml_service_provides, $hideStandardLogin,
               $accessToken_DeepLAPI, $useRewriteRulesForRecordLink,
               $allowCMSCreation,
               $matomoUrl, $matomoSiteId, $accessToken_Matomo;

        // Initialize $needEncodeRecordDetails if not set
        $needEncodeRecordDetails = $needEncodeRecordDetails ?? 0;

        // Prepare language list (assuming getPreparedLanguageList is a global function)
        [$common_languages, $locale_files] = getPreparedLanguageList();
        
        // Determine if rewrite rules are enabled (USystem::checkRewriteRuleEnabled might be static or global)
        $useRewriteRulesForRecordLink = $useRewriteRulesForRecordLink ?? USystem::checkRewriteRuleEnabled();

        try {
            // Get host logo and URL (USystem::getHostLogoAndUrl might be static or global)
            [$host_logo, $host_url] = USystem::getHostLogoAndUrl();

            // If no database connection, return minimal info
            if(!$this->mysqli){
                return [
                    "currentUser" => null,
                    "sysinfo" => [
                        "help" => HEURIST_HELP,
                        "version" => HEURIST_VERSION,
                        "sysadmin_email" => HEURIST_MAIL_TO_ADMIN,
                        "baseURL" => HEURIST_BASE_URL,
                        'baseURL_pro' => HEURIST_BASE_URL_PRO,
                        "referenceServerURL" => HEURIST_INDEX_BASE_URL,
                        "referenceServerIndexDatabase" => HEURIST_INDEX_DATABASE,
                        "referenceServerBugreportDatabase" => HEURIST_BUGREPORT_DATABASE,
                        "referenceServerHelpDatabase" => HEURIST_HELP_DATABASE,
                        'database_prefix' => HEURIST_DB_PREFIX
                    ],
                    'host_logo' => $host_logo,
                    'host_url' => $host_url,
                    'saml_service_provides' => $saml_service_provides ?? null,
                    'hideStandardLogin' => $hideStandardLogin ?? false,
                    'common_languages' => $common_languages,
                    'localization_files' => $locale_files,
                    'use_redirect' => $useRewriteRulesForRecordLink
                ];
            }

            // Reload current user info from database
            $this->loginVerify( true, $is_guest_allowed );

            // Get database owner info
            $dbowner = user_getDbOwner($this->mysqli);

            // Get list of recently logged-in databases (USystem::sessionRecentDatabases might be static or global)
            $dbrecent = USystem::sessionRecentDatabases($this->currentUser);

            // Get latest code version (USystem::getLastCodeAndDbVersion might be static or global)
            $lastCode_VersionOnServer = USystem::getLastCodeAndDbVersion();

            $res = [
                "currentUser" => $this->currentUser,
                "sysinfo" => [
                    "registration_allowed" => $this->settings->get('sys_AllowRegistration'),
                    "db_registeredid" => $this->settings->get('sys_dbRegisteredID'),
                    "db_managers_groupid" => ($this->settings->get('sys_OwnerGroupID') > 0 ? $this->settings->get('sys_OwnerGroupID') : 1),
                    "help" => HEURIST_HELP,
                    "version" => HEURIST_VERSION,
                    "version_new" => $lastCode_VersionOnServer,
                    "db_version" => getDbVersion($this->getMysqli()),
                    "db_version_req" => HEURIST_MIN_DBVERSION,
                    "dbowner_name" => ($dbowner['ugr_FirstName'] ?? '') . ' ' . ($dbowner['ugr_LastName'] ?? ''),
                    "dbowner_org" => $dbowner['ugr_Organisation'] ?? '',
                    "dbowner_email" => $dbowner['ugr_eMail'] ?? '',
                    "sysadmin_email" => HEURIST_MAIL_TO_ADMIN,
                    "db_total_records" => $this->settings->get('sys_RecordCount'),
                    "db_usergroups" => user_getAllWorkgroups($this->mysqli),
                    "baseURL" => HEURIST_BASE_URL,
                    'baseURL_pro' => HEURIST_BASE_URL_PRO,
                    'database_prefix' => HEURIST_DB_PREFIX,
                    "referenceServerURL" => HEURIST_INDEX_BASE_URL,
                    "referenceServerIndexDatabase" => HEURIST_INDEX_DATABASE,
                    "referenceServerBugreportDatabase" => HEURIST_BUGREPORT_DATABASE,
                    "referenceServerHelpDatabase" => HEURIST_HELP_DATABASE,
                    "dbconst" => $this->getLocalConstants( $include_reccount_and_dashboard_count ),
                    "service_config" => $this->settings->get('sys_ExternalReferenceLookups'),
                    "services_list" => $this->getWebServiceConfigs(),
                    "dbrecent" => $dbrecent,
                    "cms_allowed" => $allowCMSCreation ?? 1,
                    'max_post_size' => USystem::getConfigBytes('post_max_size'), // USystem::getConfigBytes might be static
                    'max_file_size' => USystem::getConfigBytes('upload_max_filesize'),
                    'is_file_multipart_upload' => ($this->settings->getDiskQuota() > 0) ? 1 : 0,
                    'host_logo' => $host_logo,
                    'host_url' => $host_url,
                    'mediaFolder' => $this->settings->get('sys_MediaFolders'),
                    'media_ext_index' => $this->settings->get('sys_MediaExtensions'),
                    'media_ext' => HEURIST_ALLOWED_EXT,
                    'rty_as_place' => $this->settings->get('sys_TreatAsPlaceRefForMapping'),
                    'need_encode' => $needEncodeRecordDetails,
                    'custom_js_allowed' => $this->settings->isJavaScriptAllowed(),
                    'common_languages' => $common_languages,
                    'localization_files' => $locale_files,
                    'saml_service_provides' => $saml_service_provides ?? null,
                    'hideStandardLogin' => $hideStandardLogin ?? false,
                    'nakala_api_key' => $this->settings->get('sys_NakalaKey'),
                    'pwd_DatabaseCreation' => (strlen($passwordForDatabaseCreation ?? '') > 6),
                    'pwd_DatabaseDeletion' => (strlen($passwordForDatabaseDeletion ?? '') > 15),
                    'pwd_ReservedChanges' => (strlen($passwordForReservedChanges ?? '') > 6),
                    'pwd_ServerFunctions' => (strlen($passwordForServerFunctions ?? '') > 6),
                    'api_Translator' => (!empty($accessToken_DeepLAPI)),
                    'use_redirect' => $useRewriteRulesForRecordLink,
                ]
            ];
            
            if(isset($matomoUrl) && isset($matomoSiteId)){
                $res['sysinfo']['matomo_url'] = $matomoUrl;
                $res['sysinfo']['matomo_siteid'] = $matomoSiteId;
                $res['sysinfo']['matomo_api_key'] = $accessToken_Matomo ?? null;
            }

            if($include_reccount_and_dashboard_count){
                [$total_records, $active_dashboard, $workset_count] = $this->getTotalRecordsAndDashboard();
                $res['sysinfo']['db_total_records'] = $total_records;
                $res['sysinfo']['db_has_active_dashboard'] = $active_dashboard;
                $res['sysinfo']['db_workset_count'] = $workset_count;
            }

            $filestoreRoot = $this->getFileStoreRootFolder();
            if(!empty($filestoreRoot)){
                $statsFile = rtrim($filestoreRoot, '/') . "/_DB_STATS/db_stats.txt";
                $lastUpdate = file_exists($statsFile) ? filemtime($statsFile) : false;
                $res['sysinfo']['refreshStatistics'] = (!$lastUpdate || $lastUpdate < strtotime('-1 month')) ? 1 : 0;
            }

            recreateRecLinks( $this, false );

        } catch( \Exception $e ){
            $this->addError(HEURIST_ERROR, 'Unable to retrieve Heurist system information', $e->getMessage());
            return false; // Return false on exception
        }

        return $res;
    }



    /**
     * Gets the information for the currently logged-in user.
     *
     * @return array|null The current user's data as an array, or null if no user is logged in.
     *                    The array structure typically includes 'ugr_ID', 'ugr_Name', 'ugr_FullName', 'ugr_eMail',
     *                    'ugr_Groups', 'ugr_Permissions', and 'ugr_Preferences'.
     */
    public function getCurrentUser(){
        return $this->currentUser;
    }

    /**
     * Sets the current user information.
     *
     * @param array|null $user An array containing the user's data, or null to clear the current user.
     *                         Expected array keys: 'ugr_ID', 'ugr_Name', 'ugr_FullName', 'ugr_eMail', 'ugr_Groups', 'ugr_Permissions', 'ugr_Preferences'.
     * @return void
     */
    public function setCurrentUser($user){
        $this->currentUser = $user;
    }



    /**
     * Gets the ID of the currently logged-in user.
     *
     * @return int The user ID, or 0 if no user is logged in.
     */
    public function getUserId(){
        return $this->currentUser? intval($this->currentUser['ugr_ID']) :0;
    }



    /**
     * Returns an array of IDs for all groups the current user belongs to, plus the current user's own ID.
     *
     * @param string|null $level Optional. Filter groups by membership level ('admin' or 'member').
     *                           If null, all groups the user is part of are returned.
     * @param bool $refresh Optional. If true, forces a refresh of the user's group list from the database.
     *                      Defaults to false (uses cached group list if available).
     * @return int[]|null An array of group IDs and the user ID, or null if no user is logged in.
     */
    public function getUserGroupIds($level=null, $refresh=false){

        $ugrID = $this->getUserId();

        if($ugrID>0){
            $groups = @$this->currentUser['ugr_Groups'];
            if($refresh || !is_array($groups)){
                $groups = $this->currentUser['ugr_Groups'] = user_getWorkgroups($this->mysqli, $ugrID);
            }
            if($level!=null){
                $groups = array();
                foreach($this->currentUser['ugr_Groups'] as $grpid=>$lvl){
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
            return null;
        }
    }



    /**
     * Checks if the current user is a member of any of the specified user/group IDs.
     * Also returns true if 0 is in the list of `$ugs` (often representing 'public' or 'any logged in user' depending on context)
     * or if `$ugs` is empty.
     *
     * @param int|int[] $ugs A single user/group ID or an array of user/group IDs to check against.
     * @return bool True if the current user is a member of at least one of the provided IDs,
     *              or if 0 is in `$ugs`, or if `$ugs` is empty. False otherwise.
     */
    public function isMember($ugs){

        if($ugs == 0 || isEmptyArray($ugs)){
            return true;
        }

        $current_user_grps = $this->getUserGroupIds(); // Get all groups + user ID
        if ($current_user_grps === null && $this->getUserId() == 0) { // Not logged in, and not checking for public
             return false;
        }

        $ugs_to_check = prepareIds($ugs, true);

        foreach ($ugs_to_check as $ug_id_to_check){
            if ($ug_id_to_check == 0) { // Explicitly checking for 'public' or 'any'
                return true;
            }
            if (is_array($current_user_grps) && in_array($ug_id_to_check, $current_user_grps) ){
                return true;
            }
        }
        return false;
    }

    /**
     * Verifies if the current user is the database owner (typically user ID 2).
     * This is often used for permissions to manage comments, files, reminders, bookmarks, and user tags,
     * where only direct owners or members of specific workgroup tags can modify them, unless the current user is the DB owner.
     *
     * @return bool True if the current user is the database owner (ID 2), false otherwise.
     */
    public function isDbOwner(){
        return $this->getUserId() === 2;
    }

    /**
     * Checks if the current user is an administrator.
     * An admin is either the database owner (user ID 2) or an administrator of the
     * "Database managers" group (whose ID is retrieved from system settings `sys_OwnerGroupID`, defaulting to 1).
     *
     * @return bool True if the current user is logged in and is a database owner or an admin of the Database Managers group.
     *              False otherwise.
     */
    public function isAdmin(){
       $userId = $this->getUserId();
       if ($userId <= 0) {
           return false;
       }
       if ($userId === 2) { // DB Owner
           return true;
       }
       $ownerGroupId = $this->settings->get('sys_OwnerGroupID');
       return $this->hasAccess( $ownerGroupId > 0 ? $ownerGroupId : 1 );
    }

    /**
     * Checks if the current user is logged in as a guest.
     * A guest user is identified by the 'guest_user' flag in their permissions.
     *
     * @return bool True if the current user is logged in and has the 'guest_user' permission set to true, false otherwise.
     */
    public function isGuestUser(){
        $user = $this->currentUser;
        return $user !== null && !empty($user['ugr_Permissions']['guest_user']);
    }


    /**
     * Checks if the current user is a system administrator.
     * A system administrator is identified by their email matching `HEURIST_MAIL_TO_ADMIN`
     * (defined in `configIni.php`).
     *
     * @return bool True if the current user is logged in and their email matches the system admin email.
     *              False otherwise, or if not logged in.
     */
    public function isSystemAdmin(){
        if ($this->getUserId() > 0 && $this->currentUser !== null){
            // It's usually better to rely on $this->currentUser['ugr_eMail'] if loginVerify populates it correctly
            // than to do another DB lookup with user_getById.
            // However, sticking to original logic:
            $user = user_getById($this->mysqli, $this->getUserId());
            return defined('HEURIST_MAIL_TO_ADMIN') && isset($user['ugr_eMail']) && ($user['ugr_eMail'] == HEURIST_MAIL_TO_ADMIN);
        } else {
            return false;
        }
    }

    /**
     * Checks if the current user satisfies a required access level.
     *
     * Levels:
     * - `null` or `< 1`: User is logged in (default check).
     * - `1`: User is an admin of group 1 ("Database managers") or DB Owner (user 2).
     * - `2`: User is the database owner (user ID 2).
     * - `n` (any other positive integer): User is an admin of the group with ID `n`, or DB Owner (user 2).
     *
     * @param int|null $requiredLevel Optional. The required access level or group ID to check for admin rights.
     *                                Defaults to null (check if logged in).
     * @return bool True if the current user meets the required access level, false otherwise.
     */
    public function hasAccess( $requiredLevel=null ) {

        $ugrID = $this->getUserId();

        if(!$requiredLevel || $requiredLevel<1){
            return $ugrID>0;//just logged in
        }

        if ($requiredLevel==$ugrID ||   //iself
        2==$ugrID)   //db owner
        {
            return true;
        }else{
            //@$this->current_User['ugr_Groups'][$requiredLevel]=='admin');//admin of given group
            $current_user_grps = $this->getUserGroupIds('admin');
            return is_array($current_user_grps) && in_array($requiredLevel, $current_user_grps);
        }
    }

    /**
    * Starts or restores a PHP session for the Heurist application.
    * It sets the session name to 'heurist-sessionid' and a cache limiter to 'none'.
    * If `$check_session_folder` is true (and `$this->needFullSessionCheck` is true),
    * it first verifies that the session save path is writable.
    * If a session is active and `$_SESSION[$this->dbnameFull]['keepalive']` is set,
    * it attempts to update session cookies.
    *
    * @param bool $check_session_folder Optional. If true, and `needFullSessionCheck` is also true,
    *                                   the session folder's writability is checked. Defaults to true.
    * @return bool True if a session was successfully started or already active,
    *              false if session folder check fails or session cannot be started.
    *              Returns true if headers have already been sent (as session cannot be started then).
    */
    private function startMySession($check_session_folder=true){

        if(headers_sent()) {
            // Cannot start session if headers are already sent.
            // Depending on strictness, this could be an error or simply proceed.
            // Original code returns true, implying it's not a fatal error for this method's contract.
            return true;
        }

        //verify that session folder is writable
        if($this->needFullSessionCheck && $check_session_folder && !USystem::sessionCheckFolder()){
            $this->addError(HEURIST_SYSTEM_FATAL, "The sessions folder has become inaccessible. This is a minor, but annoying, problem for which we apologise. An email has been sent to your system administrator asking them to fix it - this may take up to a day, depending on time differences. Please try again later.");
            return false;
        }

        if (session_status() != PHP_SESSION_ACTIVE) {

            session_name('heurist-sessionid');//set session name
            session_cache_limiter('none');

            @session_start();
        }

        $result = false;

        if (session_status() == PHP_SESSION_ACTIVE)
        {
            if (@$_SESSION[$this->dbnameFull]['keepalive'] && !USystem::sessionUpdateCookies())
            {
                USanitize::errorLog('CANNOT UPDATE COOKIE '.session_id().'   '.$this->dbnameFull);
            }
            $result = true;
        }

        return $result;
    }


    /*
    /**
     * Verifies if there's an active session for a given database and returns the user ID from that session.
     * This method only checks session data and does not perform a full system or database initialization.
     * It calls `startMySession(false)` which means it won't check session folder writability.
     *
     * @param string $db The name of the database (short or full) to check credentials for.
     * @return int|false The user ID (ugr_ID) from the session if credentials are valid and session exists,
     *                   or false otherwise (e.g., if database name is invalid or no session for that DB).
     */
    public function verifyCredentials($db){

        if( $this->setDbnameFull($db) && $this->startMySession(false) ){ // false to skip full session check
            return isset($_SESSION[$this->dbnameFull]['ugr_ID']) ? (int)$_SESSION[$this->dbnameFull]['ugr_ID'] : false;
        }else{
            return false;
        }

    }


    /**
     * Verifies the current user's login status and loads their information.
     * This method is central to establishing the `$this->currentUser` property.
     *
     * Behavior depends on the `$user` parameter:
     * - If `$user` is `true` (boolean): Reloads user information (ID, name, groups, permissions) directly from the database.
     * - If `$user` is `false` (boolean): Attempts to load user information from the `$_SESSION`.
     * - If `$user` is an array: It's assumed to be pre-fetched user data (e.g., after a successful login attempt).
     *   The method then uses this data, sets `reload_user_from_db` to true to ensure session is updated consistently.
     *
     * Regardless of `$user`, `ugr_Preferences` are always reloaded from the database if the user ID is established
     * and `reload_user_from_db` becomes true or was initially true.
     *
     * It also handles:
     * - Checking for linked sessions if no direct session user ID is found.
     * - Checking for a marker file (`HEURIST_FILESTORE_DIR . basename($userID)`) that indicates user info might have been updated externally,
     *   triggering a reload from the database.
     * - Setting guest user permissions if `$is_guest_allowed` is true and the user account is normally disabled.
     *
     * @param bool|array $user Determines how user data is loaded:
     *                         `true` to force reload from DB,
     *                         `false` to load from session,
     *                         `array` of user data to use pre-fetched info.
     * @param bool $is_guest_allowed Optional. If true and the user account is 'disabled',
     *                                 treats the user as a guest by overriding the 'disabled' permission
     *                                 and setting 'guest_user' permission. Defaults to false.
     * @return bool True if a user is successfully logged in and verified, false otherwise.
     */
    private function loginVerify( $user, $is_guest_allowed=false ){

        $reload_user_from_db = false;
        $userID = null;

        if( is_array($user) && isset($user['ugr_ID']) ){  // User info pre-fetched (e.g., from login attempt)
            $reload_user_from_db = true; // Ensure session is updated with this fresh data
            $userID = (int) $user['ugr_ID'];
        } else {
            $reload_user_from_db = ($user === true); // Boolean true forces reload
            $userID = isset($_SESSION[$this->dbnameFull]['ugr_ID']) ? (int)$_SESSION[$this->dbnameFull]['ugr_ID'] : null;
        }

        if($userID === null){
            // Attempt to login via linked session if no direct user ID in current session
            $linkedUserID = $this->doLoginByLinkedSession();
            if ($linkedUserID !== null) {
                $userID = $linkedUserID;
                $reload_user_from_db = true; // Data from linked session needs to populate current session/user object
            }
        }

        if($userID === null || $userID <= 0){ // Ensure userID is valid
            return false; // Not logged in
        }

        // Check for external update marker (e.g., user profile changed elsewhere)
        // HEURIST_FILESTORE_DIR needs to be defined for this to work.
        if (defined('HEURIST_FILESTORE_DIR')) {
            $update_marker_fname = HEURIST_FILESTORE_DIR . basename((string)$userID);
            if(file_exists($update_marker_fname)){
                unlink($update_marker_fname);
                // Mark for client-side refresh if not already forcing a DB reload
                if($user !== true && !is_array($user)) { // only if not already a forced reload
                     $_SESSION[$this->dbnameFull]['need_refresh'] = 1;
                }
                $reload_user_from_db = true;
            }
        } else {
            // Log or handle error: HEURIST_FILESTORE_DIR is not defined, cannot check for external updates.
            // This might be acceptable if this feature is not critical for all setups.
        }


        if($reload_user_from_db){
            if(!$this->updateSessionForUser( $userID )){
                $this->currentUser = null; // Clear currentUser if update fails
                return false; // User not found or disabled, and not allowed as guest
            }

            // If guest allowed and user is disabled, override disabled status for this session
            if($is_guest_allowed && isset($_SESSION[$this->dbnameFull]['ugr_Permissions']['disabled']) && $_SESSION[$this->dbnameFull]['ugr_Permissions']['disabled']){
                $_SESSION[$this->dbnameFull]['ugr_Permissions']['disabled'] = false;
                $_SESSION[$this->dbnameFull]['ugr_Permissions']['guest_user'] = true; // Mark as guest
            }

            // Always reload preferences from DB when user data is reloaded from DB
            // Set a temporary currentUser with ID to ensure user_getPreferences has the correct context
            $this->currentUser = ['ugr_ID' => $userID];
            $_SESSION[$this->dbnameFull]['ugr_Preferences'] = user_getPreferences( $this );
        }

        // Populate $this->currentUser from session (which is now up-to-date if reloaded)
        // Ensure all expected keys are present, defaulting to null or empty arrays if not.
        $this->currentUser = [
            'ugr_ID'          => $userID,
            'ugr_Name'        => $_SESSION[$this->dbnameFull]['ugr_Name'] ?? null,
            'ugr_FullName'    => $_SESSION[$this->dbnameFull]['ugr_FullName'] ?? null,
            'ugr_eMail'       => $_SESSION[$this->dbnameFull]['ugr_eMail'] ?? null,
            'ugr_Groups'      => $_SESSION[$this->dbnameFull]['ugr_Groups'] ?? [],
            'ugr_Permissions' => $_SESSION[$this->dbnameFull]['ugr_Permissions'] ?? [],
            'ugr_Preferences' => $_SESSION[$this->dbnameFull]['ugr_Preferences'] ?? []
        ];

        // Remove sensitive credentials for remote repositories from the currentUser object if they exist
        if(isset($this->currentUser['ugr_Preferences']['externalRepositories'])){
            // It's safer to unset than to set to null if other code might expect the key not to exist.
            unset($this->currentUser['ugr_Preferences']['externalRepositories']);
        }
        
        // Clear the 'need_refresh' flag after processing
        if(isset($_SESSION[$this->dbnameFull]['need_refresh'])) {
            unset($_SESSION[$this->dbnameFull]['need_refresh']);
        }

        return true; // User is considered logged in
    }

    /**
     * Updates the current session with actual user information from the database.
     * Fetches user details, groups, and permissions.
     *
     * @param int $userID The ID of the user to load into the session.
     * @return bool True if the user was found and session updated, false if user not found.
     */
    public function updateSessionForUser( $userID ){

        $user = user_getById($this->mysqli, $userID);

        //user can be removed - check presence
        if($user==null){
            return false; //not logged in
        }

        $_SESSION[$this->dbnameFull]['ugr_ID'] = $userID;
        $_SESSION[$this->dbnameFull]['ugr_Groups']   = user_getWorkgroups( $this->mysqli, $userID );
        $_SESSION[$this->dbnameFull]['ugr_Name']     = $user['ugr_Name'];
        $_SESSION[$this->dbnameFull]['ugr_eMail']    = $user['ugr_eMail'];
        $_SESSION[$this->dbnameFull]['ugr_FullName'] = $user['ugr_FirstName'] . ' ' . $user['ugr_LastName'];
        $_SESSION[$this->dbnameFull]['ugr_Enabled']  = $user['ugr_Enabled'];

        $is_disabled = $user['ugr_Enabled'] == 'n';
        $_SESSION[$this->dbnameFull]['ugr_Permissions'] = array(
            'disabled' => $is_disabled,
            'add' => strpos($user['ugr_Enabled'], 'add') === false && !$is_disabled,
            'delete' => strpos($user['ugr_Enabled'], 'del') === false && !$is_disabled);

        return true;
    }


    /**
    /**
     * Attempts to log in a user by checking for an active session in a linked database.
     * This allows for a form of single sign-on across mutually linked Heurist databases.
     *
     * The process:
     * 1. Reads `sys_UGrpsDatabase` from the current database's `sysIdentification` table to find linked DBs.
     * 2. For each linked DB, checks if a session exists for it (e.g., `$_SESSION[linked_db_full_name]['ugr_ID']`).
     * 3. If a session exists in a linked DB:
     *    a. Verifies that the linked DB also lists the current DB in its `sys_UGrpsDatabase` (mutual link).
     *    b. Retrieves the user's email from the linked DB using the user ID from that session.
     *    c. Searches for a user in the current database with that email.
     *    d. If an active, non-disabled user is found in the current DB, a new session is established for them
     *       in the current DB using `doLoginSession()` with 'public' type, and their user ID is returned.
     *
     * @return int|null The user ID in the current database if login via linked session was successful, otherwise null.
     */
    private function doLoginByLinkedSession(){
        // 1. Find sys_UGrpsDatabase in this database
        $linked_dbs_str = mysql__select_value($this->mysqli, 'select sys_UGrpsDatabase from sysIdentification');
        if(empty($linked_dbs_str)) {
            return null;
        }

        $linked_dbs_array = explode(',', $linked_dbs_str);

        foreach ($linked_dbs_array as $ldb_short_name){
            // Ensure full database name with prefix
            $ldb_full_name = (strpos($ldb_short_name, HEURIST_DB_PREFIX) === 0)
                ? $ldb_short_name
                : HEURIST_DB_PREFIX . $ldb_short_name;

            // 2. Check if session exists for the linked database
            $userID_in_linkedDB = isset($_SESSION[$ldb_full_name]['ugr_ID']) ? (int)$_SESSION[$ldb_full_name]['ugr_ID'] : 0;

            if( $userID_in_linkedDB > 0 ){
                // 3. Find sys_UGrpsDatabase in the linked database to verify mutual link
                $linked_dbs2_str = mysql__select_value($this->mysqli, 'select sys_UGrpsDatabase from `'. $this->mysqli->real_escape_string($ldb_full_name) .'`.sysIdentification');
                if(empty($linked_dbs2_str)) {
                    continue; // Not mutually linked or error
                }

                $linked_dbs2_array = explode(',', $linked_dbs2_str);
                $is_mutually_linked = false;
                foreach ($linked_dbs2_array as $ldb2_short_name){
                    $ldb2_full_name = (strpos($ldb2_short_name, HEURIST_DB_PREFIX) === 0)
                        ? $ldb2_short_name
                        : HEURIST_DB_PREFIX . $ldb2_short_name;

                    if( strcasecmp($this->dbnameFull, $ldb2_full_name) == 0 ){
                        $is_mutually_linked = true;
                        break;
                    }
                }

                if (!$is_mutually_linked) {
                    continue;
                }

                // 4. Find user email in the linked database
                $userEmail_in_linkedDB = mysql__select_value($this->mysqli,
                    'select ugr_eMail from `'. $this->mysqli->real_escape_string($ldb_full_name) .
                    '`.sysUGrps where ugr_ID=' . $userID_in_linkedDB);

                // 5. Find user by email in THIS database
                if($userEmail_in_linkedDB){
                    $user_in_current_db = user_getByField($this->getMysqli(), 'ugr_eMail', $userEmail_in_linkedDB); // user_getByField is global
                    if(null != $user_in_current_db && ($user_in_current_db['ugr_Type'] ?? '') =='user' && ($user_in_current_db['ugr_Enabled'] ?? 'n') !='n') {
                        // 6. Success - establish new session in current DB
                        $this->doLoginSession($user_in_current_db['ugr_ID'], 'public');
                        return (int)$user_in_current_db['ugr_ID'];
                    }
                }
            }
        }
        return null;
    }

    /**
     * Attempts to log in a user with the provided username and password.
     * If successful, it establishes a session for the user and updates `$this->currentUser`.
     *
     * Handles:
     * - Basic validation for username/password presence.
     * - Special "database access password" from `configIni.php` which bypasses normal user password check.
     * - Retrieving user by username or ID (if username is numeric for the bypass).
     * - Checking if the user account is enabled (unless `$is_guest` is true).
     * - Verifying the provided password against the stored hash using `passwordCheck()`.
     *
     * @global string|null $passwordForDatabaseAccess A global password from `configIni.php` that allows access
     *                                                by username (or ID 2 if username is not numeric) without matching user's actual password.
     *
     * @param string $username The username or, in special cases (with `$skip_pwd_check` or global password), potentially a user ID.
     * @param string $password The user's password.
     * @param string $session_type Type of session to establish: 'public', 'shared' (1 day), or 'remember' (30 days).
     *                             Determines cookie lifetime.
     * @param bool $skip_pwd_check Optional. If true, password checking is skipped. This is used internally or
     *                             when the global `$passwordForDatabaseAccess` matches. Defaults to false.
     * @param bool $is_guest Optional. If true, allows login even if the user account is marked as 'disabled',
     *                       typically for guest access scenarios. Defaults to false.
     * @return bool True if login is successful, false otherwise (errors will be set via `addError`).
     */
    public function doLogin($username, $password, $session_type, $skip_pwd_check=false, $is_guest=false){
        global $passwordForDatabaseAccess;

        if(empty($username) || (empty($password) && !$skip_pwd_check)){
            $this->addError(HEURIST_INVALID_REQUEST, "Username / password not defined");
            return false;
        }

        $user = null;

        // Check for global database access password or if password check is explicitly skipped
        if ($skip_pwd_check ||
            (isset($passwordForDatabaseAccess) && strlen($passwordForDatabaseAccess) > 15 && $passwordForDatabaseAccess === $password)
           ) {
            $user_id_to_fetch = is_numeric($username) ? (int)$username : 2; // Default to user ID 2 (admin) if username not numeric for bypass
            $user = user_getById($this->mysqli, $user_id_to_fetch); // user_getById is global
            $skip_pwd_check = true; // Ensure password check is skipped if global password used
        } else {
            $user = user_getByField($this->mysqli, 'ugr_Name', $username); // user_getByField is global
        }

        if(!$user){
            $this->addError(HEURIST_REQUEST_DENIED,  "The credentials supplied are not correct");
        } elseif (!$is_guest && ($user['ugr_Enabled'] ?? 'n') === 'n'){
            $this->addError(HEURIST_REQUEST_DENIED,  "Your user profile is not active. Please contact database owner");
        } elseif ($skip_pwd_check || passwordCheck($password, $user['ugr_Password'], $this->mysqli, $user['ugr_ID']) ) { // passwordCheck is global
            $this->doLoginSession($user['ugr_ID'], $session_type);
            // After doLoginSession, loginVerify(true) should be called to populate $this->currentUser and full session details
            // However, the original flow might rely on getCurrentUserAndSysInfo to do this.
            // For consistency, it's better if doLogin itself ensures currentUser is set or triggers it.
            // $this->loginVerify($user); // Pass the fetched user array to loginVerify
            return true;
        } else {
            $this->addError(HEURIST_REQUEST_DENIED,  "The credentials supplied are not correct");
        }

        return false;
    }

    /**
     * Establishes a session for a given user ID.
     * Sets session cookie lifetime based on `$session_type` and updates the user's last login time in the database.
     *
     * @param int $userID The ID of the user for whom to establish the session.
     * @param string $session_type The type of session:
     *                             - 'public': Session cookie, expires when browser closes.
     *                             - 'shared': Session cookie with 1-day lifetime.
     *                             - 'remember': Session cookie with 30-day lifetime, sets 'keepalive' flag in session.
     * @return void
     */
    private function doLoginSession($userID, $session_type){

        $lifetime = 0; // Default: session cookie (expires when browser closes)
        if($session_type === 'shared'){
            $lifetime = time() + 24*60*60;     // 1 day
        } elseif($session_type === 'remember') {
            $lifetime = time() + 30*24*60*60;  // 30 days
            $_SESSION[$this->dbnameFull]['keepalive'] = true; // Flag to refresh cookie on subsequent visits
        }

        USystem::sessionUpdateCookies($lifetime);

        $_SESSION[$this->dbnameFull]['ugr_ID'] = (int)$userID;

        // Update last login time in the database
        user_updateLoginTime($this->mysqli, $userID);
    }


    /**
     * Logs out the current user.
     * Clears relevant session data for the current database, expires the session cookie,
     * sets `$this->currentUser` to null, and destroys the PHP session.
     *
     * @return true Always returns true.
     */
    public function doLogout(){

        $this->startMySession(false); // Ensure session is started to modify it, false to skip folder check

        unset($_SESSION[$this->dbnameFull]['ugr_ID']);
        unset($_SESSION[$this->dbnameFull]['ugr_Name']);
        unset($_SESSION[$this->dbnameFull]['ugr_FullName']);
        if(@$_SESSION[$this->dbnameFull]['ugr_Groups']) {unset($_SESSION[$this->dbnameFull]['ugr_Groups']);}
        if(@$_SESSION[$this->dbnameFull]['ugr_Permissions']) {unset($_SESSION[$this->dbnameFull]['ugr_Permissions']);}
        if(@$_SESSION[$this->dbnameFull]['ugr_GuestUser']!=null) {unset($_SESSION[$this->dbnameFull]['ugr_GuestUser']);}

        // clear
        // even if user is logged to different databases he has the only session per browser
        // it means logout exits all databases
        $is_https = (@$_SERVER['HTTPS']!=null && $_SERVER['HTTPS']!='');

        setcookie('heurist-sessionid', '', time() - 3600, '/', '', $is_https, true);//logout
        $this->currentUser = null;
        session_destroy();

        session_write_close();
        return true;
    }

    /**
     * Retrieves a specific user preference value from the current user's session data.
     * For 'search_detail_limit', it applies min/max clamping (500-5000).
     *
     * Note: To load the entire set of preferences fresh from the database, `user_getPreferences()`
     * (via `loginVerify`) should be used. This method only reads from the already loaded session data.
     *
     * @param string $property The name of the preference property to retrieve (e.g., 'search_detail_limit').
     * @param mixed $def Optional. The default value to return if the preference is not set in the session. Defaults to null.
     * @return mixed The value of the preference, or the default value if not found.
     *               For 'search_detail_limit', the value is clamped between 500 and 5000. Returns $def if property not found and $def is provided.
     */
    public function userGetPreference($property, $def=null){

        $res = $_SESSION[$this->dbnameFull]["ugr_Preferences"][$property] ?? null;

        // POSSIBLE redundancy: this duplicates same in hapi.js
        if('search_detail_limit' === $property){ // Strict comparison for property name
            if(!$res || $res < 500 ) {$res = 500;} // Strict comparison for numeric values
            elseif($res > 5000 ) {$res = 5000;} // Strict comparison for numeric values
        }elseif($res === null && $def !== null){ // Strict comparison for null and ensure $def is actually provided
            $res = $def;
        }

        return $res;
    }

    /**
     * Logs user activity to a file named `userInteraction.log` in the database's root filestore directory.
     * The log entry is a comma-separated line containing:
     * user ID, action, timestamp (ISO 8601), OS, browser, IP address, and any supplementary data.
     *
     * If `$user_id` is not provided, it attempts to get the current user ID using `loginVerify(false)`
     * (which loads from session without forcing DB reload).
     *
     * @param string $action A string describing the action being logged (e.g., 'login', 'view_record').
     * @param string|array $suplementary Optional. Supplementary information about the action.
     *                                   If an array, its elements are appended as separate CSV fields.
     *                                   If a string, it's appended as a single CSV field. Defaults to an empty string.
     * @param int|null $user_id Optional. The ID of the user performing the action. If null, the current user ID is fetched.
     * @return void
     */
    public function userLogActivity($action, $suplementary = '', $user_id=null){

        if($user_id === null){
            // Ensure user is loaded from session if not already, to get ID
            // loginVerify(false) is appropriate here if currentUser might not be set yet
            // but we only need the ID. If currentUser is reliably set, getUserId() is enough.
            if ($this->currentUser === null) {
                $this->loginVerify( false ); // Load from session if not already loaded
            }
            $user_id = $this->getUserId();
        }

        $now = new \DateTime();
        $user_agent = USystem::getUserAgent();
        $addr_IPv4 = USystem::getUserIP();

        $info = [
            $user_id,
            $action,
            $now->format(\DateTimeInterface::ATOM), // Using ATOM for ISO 8601 compatibility
            $user_agent['os'] ?? 'UnknownOS',
            $user_agent['browser'] ?? 'UnknownBrowser',
            $addr_IPv4
        ];

        if(is_array($suplementary)){
            $info = array_merge($info, $suplementary);
        } elseif (!empty($suplementary)) {
            $info[] = $suplementary;
        }

        $logFilePath = $this->getSysDir() . 'userInteraction.log'; // getSysDir() gives DB root filestore path
        if ($logFilePath) { // Ensure getSysDir() didn't return null
            file_put_contents ( $logFilePath , implode(',', $info)."\n", FILE_APPEND );
        } else {
            // Log error: could not determine log file path
            error_log("userLogActivity: Could not determine system directory to write userInteraction.log for database " . ($this->dbname() ?? 'unknown'));
        }
    }

    /**
     * Generates a URL link to a specific Heurist record.
     * The link can be to the standard HTML record view or to a custom Smarty template view.
     *
     * The URL format depends on the global `$useRewriteRulesForRecordLink` setting:
     * - If true (rewrite rules enabled):
     *   - Standard view: `BASE_URL_PRO/databasename/view/record_id`
     *   - Template view: `BASE_URL_PRO/databasename/tpl/template_name.tpl/record_id`
     * - If false (rewrite rules disabled):
     *   - Standard view: `BASE_URL_PRO?recID=record_id&fmt=html&db=databasename`
     *   - Template view: `BASE_URL_PRO?db=databasename&q=ids:record_id&template=template_name.tpl`
     *
     * The input `$rec_id_input` can be just the record ID (integer) or a string formatted as "record_id/template_name.tpl"
     * to specify a custom template. If a template is specified, its existence is checked.
     *
     * @global bool|null $useRewriteRulesForRecordLink System configuration whether to use SEO-friendly URLs.
     *
     * @param int|string $rec_id_input The record ID (integer) or a string "record_id/path/to/template.tpl".
     * @return string The generated URL for the record. Returns an empty string if the database name is not set or rec_id_input is invalid.
     */
    public function recordLink($rec_id_input){ // Renamed param for clarity internal to function
        global $useRewriteRulesForRecordLink;

        if (empty($this->dbname)) {
            // Cannot generate a link without a database context.
            error_log("recordLink: Called without a database context (dbname is empty).");
            return '';
        }

        $rec_id_val = null; // Use a different var name for the processed record ID
        $template = '';

        if (is_string($rec_id_input) && preg_match('/^(\d+)\/(.+\.tpl)$/', $rec_id_input, $matches)){
            $rec_id_val = (int)$matches[1];
            $potential_template = urldecode($matches[2]);
            $template_path = $this->getSysDir('smarty-templates');

            // Check that the template exists
            if (!empty($template_path) && !empty($potential_template) && file_exists($template_path . $potential_template)) {
                $template = urlencode($potential_template); // Use Smarty template
            }
            // If template specified but not found, it falls back to standard view with the extracted rec_id_val
        } elseif (is_numeric($rec_id_input)) {
            $rec_id_val = (int)$rec_id_input;
        } else {
            // Invalid $rec_id_input format
            error_log("recordLink: Invalid rec_id_input format: " . print_r($rec_id_input, true));
            return '';
        }
        
        if ($rec_id_val <= 0) { // Ensure valid record ID
            error_log("recordLink: Invalid record ID: " . $rec_id_val);
            return '';
        }

        $use_rewrite = !empty($useRewriteRulesForRecordLink); // Treat null or empty as false
        $base_url = HEURIST_BASE_URL_PRO; // Assumes HEURIST_BASE_URL_PRO is always defined

        if (!$use_rewrite) {
            if (!empty($template)) {
                return $base_url . '?db=' . $this->dbname . '&q=ids:' . $rec_id_val . '&template=' . $template;
            }
            return $base_url . '?recID=' . $rec_id_val . '&fmt=html&db=' . $this->dbname;
        }

        // Handle base URL potentially ending with /HEURIST/ or just /
        // This logic aims to correctly construct the path like /HEURIST/dbname or /dbname
        $base_path_segment = rtrim($base_url, '/');
        // If HEURIST_BASE_URL_PRO is 'http://server/HEURIST/', $base_path_segment is 'http://server/HEURIST'
        // If HEURIST_BASE_URL_PRO is 'http://server/', $base_path_segment is 'http://server'
        
        // The original code had some complex logic for /HEURIST/. Simplifying:
        // We want $base_path_segment to be the correct prefix for /dbname/...
        // If $base_url already ends with /HEURIST, then keep it.
        // If $base_url is just the server (e.g. http://localhost/), it implies Heurist might be at root, or this needs prefixing.
        // The original check `strpos($base_url, "/HEURIST/") !== false` is kept.
        // The part ` $parts[ count($parts) - 1 ] == 'HEURIST' ` seems to assume $base_url is a path not a full URL.
        // A safer approach for URL construction is usually to ensure no double slashes.
        // $final_base_url = rtrim($base_url, '/'); // Start with a clean base

        if (!empty($template)) {
            return $base_path_segment . '/' . $this->dbname . '/tpl/' . $template . '/' . $rec_id_val;
        }
        return $base_path_segment . '/' . $this->dbname . '/view/' . $rec_id_val;
    }


    /**
     * Verifies a password entered by a user against a known comparison password (e.g., from `configIni.php`).
     * This is typically used for actions requiring a secondary "challenge" password.
     *
     * @param string $password_entered The password entered by the user.
     * @param string|null $password_to_compare The known, correct password to compare against.
     * @param int $min_length Optional. The minimum required length for `$password_to_compare`.
     *                        If `$password_to_compare` is null or shorter than this, the action is blocked. Defaults to 6.
     * @return bool True if the entered password is WRONG or if the setup is invalid (action should be blocked).
     *              False if the entered password is CORRECT (action is allowed).
     */
    public function verifyActionPassword($password_entered, $password_to_compare, $min_length=6)
    {
        $is_NOT_allowed = true; // Assume not allowed by default

        if(isEmptyStr($password_entered)) {
            $this->addError(HEURIST_ACTION_BLOCKED, 'Password is missing');
            return $is_NOT_allowed;
        }
        
        // Password in configIni.php must be at least $min_length characters
        if($password_to_compare === null || strlen($password_to_compare) < $min_length) {
            $this->addError(HEURIST_ACTION_BLOCKED,
                'This action is not allowed unless a challenge password of sufficient length is set - please consult system administrator');
            return $is_NOT_allowed;
        }
        
        // Check password
        if(strcmp($password_entered, $password_to_compare) === 0) { // Correct password
            $is_NOT_allowed = false;
        } else {
            // Invalid password
            $this->addError(HEURIST_ACTION_BLOCKED, 'Password is incorrect');
        }

        return $is_NOT_allowed;
    }

    /**
     * Sets the HTTP response header, typically for content type.
     * The commented-out section suggests it was also intended for CORS headers in embedding scenarios,
     * but this functionality is currently disabled.
     *
     * @param string|null $content_type Optional. The content type string (e.g., 'text/html', 'application/pdf').
     *                                  If null, defaults to `CTYPE_JSON` (a global constant, likely 'application/json; charset=utf-8').
     *                                  If `CTYPE_JSON` is not defined and `$content_type` is null, it falls back to
     *                                  'application/json; charset=utf-8'.
     * @return void
     */
    public function setResponseHeader($content_type=null){

        /*  Commented out CORS headers section
        $allowed = array(HEURIST_MAIN_SERVER, 'https://epigraphia.efeo.fr', 'https://november1918.adelaide.edu.au');//disabled
        if(isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed, true) === true){
            header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Content-Type');
        }
        */

        if($content_type === null){
            if (defined('CTYPE_JSON')) {
                header(CTYPE_JSON);
            } else {
                // Fallback if CTYPE_JSON is not defined
                header('Content-type: application/json; charset=utf-8');
            }
        }else{
            header('Content-type: '.$content_type);
        }
    }


    /**
     * Removes database definition cache files.
     * Deletes `db.json` (old name) and `dbdef_cache.json` from the database's 'entity' system directory.
     * It checks if the entity directory path can be resolved before attempting deletion.
     *
     * @return void
     */
    public function cleanDefCache(){
        $entityDir = $this->getSysDir('entity');
        if ($entityDir) {
            // fileDelete is assumed to be a global helper function that safely attempts to delete a file.
            fileDelete($entityDir . 'db.json'); 
            fileDelete($entityDir . 'dbdef_cache.json');
        } else {
            // Log error: could not determine entity directory
            error_log("cleanDefCache: Could not determine 'entity' system directory for database " . ($this->dbname() ?? 'unknown'));
        }
    }
}
