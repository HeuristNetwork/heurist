<?php
/**
* DbRegis.php - Class DbRegis
*
* Database registration operations in the Heurist reference index database 
*
* @project     Heurist academic knowledge management system
* @package Utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/
namespace hserv\utilities;
use hserv\System;
use hserv\utilities\DbUtils;
use hserv\utilities\USanitize;
use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/../records/edit/recordModify.php';

/**
* Class DbRegis
* 
* Static class to perform database registration operations in the Heurist reference index database.
* It handles adding, updating, deleting, and retrieving database registration information,
* including interactions with a remote reference server if necessary.
*
* Public Static Methods:
* - initialize(): Initializes the DbRegis class and database connection.
* - registrationDelete(array $params): Removes a database registration.
* - registrationUpdate(array $params): Updates an existing database registration.
* - registrationGet(array $params): Retrieves registration information for a database.
* - registrationAdd(array $params): Adds a new database registration.
* 
*/
class DbRegis {

     /**
     * Construct won't be called inside this class and is uncallable from
     * the outside. This prevents instantiating this class.
     * This is by purpose, because we want a static class.
     */
    private function __construct() {}
    private static $mysqli = null;
    private static $system = null;
    private static $initialized = false;

    private static $lastError = null;

    private static $isOutSideRequest = false;

    /**
     * Initializes the DbRegis class.
     * Sets up the database connection if the request is not an outside request.
     *
     * @return bool True if initialization is successful or already initialized, false otherwise.
     */
    public static function initialize()
    {
        
        if (self::$initialized){
            return true;
        }

        self::$isOutSideRequest = (strpos(strtolower(HEURIST_INDEX_BASE_URL), strtolower(HEURIST_SERVER_URL))===false);

        self::$system = new System();
        
        if(!self::$isOutSideRequest){
            //connect
            if(self::$system->init(HEURIST_INDEX_DATABASE, true, false)){ //init without consts
                self::$system->initPathConstants();
                self::$mysqli = self::$system->getMysqli();
            }else{
                self::addError();
                return false;
            }
        }else{
            self::$system->initPathConstants();
            /*
            if(self::$system->init(null, false, false)){ //init without consts
                self::$system->initPathConstants();
            }else{
                self::addError();
                return false;
            }
            */
        }
        self::$initialized = true;
        return true;

    }

    /**
    * Request Heurist reference server or a specific Heurist server.
    *
    * @param mixed $params
    * @param string|null $serverBaseUrl Base Heurist URL, e.g. https://host/heurist/
    */
    private static function registrationRemoteCall($params, $serverBaseUrl=null, $stage=null){

        if(@$params['db']!=null){
            unset($params['db']);//reset to avoid recursion
        }

        $reg_record = null;

        if($serverBaseUrl==null || $serverBaseUrl==''){
            $serverBaseUrl = HEURIST_MAIN_SERVER.'/heurist/'; //temp - replace with HEURIST_INDEX_BASE_URL as soon as heurist will be updated
        }else{
            $serverBaseUrl = rtrim($serverBaseUrl, '/').'/';
        }

        $remote_url = $serverBaseUrl
                .'hserv/controller/indexController.php?'
                .http_build_query($params);

        $data = loadRemoteURLContentWithRange($remote_url, null, true, 20);

        if (!isset($data) || $data==null) {
            global $glb_curl_error;
            $error_code = (!empty($glb_curl_error)) ? $glb_curl_error : 'no error code provided (curl)';

            $serverType = ($stage==='registered_database_server_lookup')?'database':'reference';
            
            $error = self::makeError(
                HEURIST_NETWORK_ERROR,
                'Unable to connect Heurist '.$serverType.' server, possibly due to timeout or proxy setting',
                array(
                    'code' => 'REMOTE_CONNECT_FAILED',
                    'stage' => $stage ?: 'remote_registry_call',
                    'remote_url' => $remote_url,
                    'transport_error' => $error_code
                )
            );

            self::addError($error);
            return false;
        }else{
            $decoded = json_decode($data, true);
            if(!is_array($decoded)){
                $error = self::makeError(
                    HEURIST_UNKNOWN_ERROR,
                    'Heurist reference server returned an invalid response',
                    array(
                        'code' => 'REMOTE_INVALID_RESPONSE',
                        'stage' => $stage ?: 'remote_registry_call',
                        'remote_url' => $remote_url
                    )
                );
                self::addError($error);
                return false;
            }elseif (count($decoded)==0){
                $error = self::makeError(
                    HEURIST_NOT_FOUND,
                    'Database server URL is not found in central index database',
                    array(
                        'code' => 'REMOTE_NOT_FOUND',
                        'stage' => $stage ?: 'remote_registry_call',
                        'remote_url' => $remote_url
                    )
                );
                self::addError($error);
                return false;
            }

            if(@$decoded['status']==HEURIST_OK){
               $reg_record =  $decoded['data'];
            }else{
                $message = @$decoded['message'] ?: 'Heurist reference server returned an error';
                $error = self::makeError(
                    @$decoded['status'] ?: HEURIST_UNKNOWN_ERROR,
                    'Heurist reference server returns error message: '.$message,
                    array(
                        'code' => @$decoded['code'] ?: 'REMOTE_APPLICATION_ERROR',
                        'stage' => $stage ?: 'remote_registry_call',
                        'remote_url' => $remote_url,
                        'remote_error' => $decoded
                    )
                );
                self::addError($error);//transfer error to global $system
                return false;
            }
        }

        return $reg_record;
    }

    /**
    * Adds/transfers error into global system variable
    */
    private static function makeError($error, $msg=null, $extra=array())
    {
        if(is_array($error)){
            $out = $error;
        }else{
            $out = array('status'=>$error, 'sysmsg'=>$extra, 'message'=>$msg);
        }
        /*
        if(is_array($extra) && !empty($extra)){
            $out = array_merge($out, $extra);
        }*/
        return $out;
    }

    /**
    * Returns last error raised by this class in a resolver-friendly shape.
    */
    public static function getLastError()
    {
        if(self::$lastError!=null){
            return self::$lastError;
        }
        if(self::$system!=null){
            $err = self::$system->getError();
            if(!empty($err)){
                return $err;
            }
        }
        return null;
    }

    /**
    * Adds/transfers error into global system variable
    */
    private static function addError($error=null, $msg=null)
    {
        if($error==null){
            self::$lastError = self::$system->getError();
            self::$system->addErrorArr(self::$lastError);//transfer from this $system
        }elseif (is_array($error)){
            self::$lastError = $error;
            self::$system->addErrorArr($error);
        }else{
            self::$lastError = self::makeError($error, $msg);
            self::$system->addError($error, $msg);
        }
    }

    private static function addErrorToCache($dbID, $errorMsg){
        self::setCachedDatabaseUrl($dbID, 'failure:'.$errorMsg);
        self::addError(HEURIST_NOT_FOUND, $errorMsg);
    }
    

    private static function getLocalIndexFilePath(){
        return rtrim(HEURIST_FILESTORE_ROOT, '/\\').DIRECTORY_SEPARATOR.'_INDEX_OF_REGISTERED_DATABASES.txt';
    }

    private static function getLocalUrlCacheFilePath(){
        return rtrim(HEURIST_FILESTORE_ROOT, '/\\').DIRECTORY_SEPARATOR.'_CACHE_OF_REGISTERED_DATABASE_URLS.txt';
    }

    private static function isFileOutdated($filename, $maxAge){
        return (!file_exists($filename) || !is_readable($filename) || (time() - @filemtime($filename)) > $maxAge);
    }

    private static function normalizeServerUrl($server, $withPortAndPath=false){
        $server = trim((string)$server);
        if($server=='') {return '';}

        $server_lc = strtolower($server);
        if(!(strpos($server_lc, HTTP_SCHEMA)===0 || strpos($server_lc, HTTPS_SCHEMA)===0)){
            $server = HTTPS_SCHEMA.$server;
        }

        $parts = @parse_url($server);
        if(!$parts || !@$parts['host']){
            return rtrim($server, '/');
        }

        $scheme = @$parts['scheme'] ? strtolower($parts['scheme']) : 'https';
        $host = strtolower($parts['host']);
        $port = @$parts['port'] ? ':'.$parts['port'] : '';
        $path = @$parts['path'] ? rtrim($parts['path'], '/') : '';

        return $scheme.'://'.$host . ($withPortAndPath? $port.$path.'/' :'');
    }

    private static function extractServerUrl($url){
        $url = trim((string)$url);
        if($url=='') {return '';}

        $parts = @parse_url($url);
        if(!$parts || !@$parts['host']){
            return rtrim(preg_replace('/[?&]db=[^&]*/i', '', $url), '?&/');
        }

        $scheme = @$parts['scheme'] ? strtolower($parts['scheme']) : 'https';
        $host = strtolower($parts['host']);
        $port = @$parts['port'] ? ':'.$parts['port'] : '';
        $path = @$parts['path'] ? rtrim($parts['path'], '/') : '';

        return $scheme.'://'.$host.$port.$path;
    }

    private static function makeDatabaseUrl($dbName){
        return HEURIST_BASE_URL_PRO.'?db='.$dbName;
    }

    private static function readKeyValueFile($filename){
        $rows = array();
        if(!file_exists($filename) || !is_readable($filename)){
            return $rows;
        }

        $lines = @file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if(!is_array($lines)){
            return $rows;
        }

        foreach($lines as $line){
            $line = trim($line);
            if($line=='' || $line[0]=='#') {continue;}

            $pos = strpos($line, '=');
            if($pos===false) {continue;}

            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos+1));
            if($key!==''){
                $rows[$key] = $val;
            }
        }

        return $rows;
    }

    private static function writeKeyValueFile($filename, $data){
        $dir = dirname($filename);
        if(!is_dir($dir) && !@mkdir($dir, 0775, true)){
            self::addError(HEURIST_FILE_WRITE_ERROR, 'Cannot create folder for file '.htmlspecialchars($filename));
            return false;
        }

        ksort($data, SORT_NATURAL);

        $tmp = $filename.'.tmp';
        $fh = @fopen($tmp, 'wb');
        if(!$fh){
            self::addError(HEURIST_FILE_WRITE_ERROR, 'Cannot write file '.htmlspecialchars($filename));
            return false;
        }

        if(!@flock($fh, LOCK_EX)){
            fclose($fh);
            @unlink($tmp);
            self::addError(HEURIST_FILE_WRITE_ERROR, 'Cannot lock file '.htmlspecialchars($filename));
            return false;
        }

        foreach($data as $key=>$val){
            fwrite($fh, $key.'='.$val."\n");
        }

        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);

        if(!@rename($tmp, $filename)){
            @unlink($tmp);
            self::addError(HEURIST_FILE_WRITE_ERROR, 'Cannot replace file '.htmlspecialchars($filename));
            return false;
        }
        return true;
    }

    private static function rebuildRegisteredDatabaseIndexFile($force=false){

        $filename = self::getLocalIndexFilePath();
        if(!$force && !self::isFileOutdated($filename, 24*3600)){
            return true;
        }

        $mysqli = mysql__init();
        if(!$mysqli){
            self::addError(HEURIST_DB_ERROR, 'Cannot connect to MySQL server to rebuild registered database index');
            return false;
        }

        $dbs = mysql__getdatabases4($mysqli, true);
        $rows = array();

        foreach($dbs as $database_name){
            list($database_name_full, $database_name_plain) = mysql__get_names($database_name);
            $sql = 'SELECT sys_dbRegisteredID FROM `'.$database_name_full.'`.sysIdentification LIMIT 1';
            $dbID = intval(mysql__select_value($mysqli, $sql));
            if($dbID>0){
                $rows[$dbID] = $database_name_plain;
            }
        }

        return self::writeKeyValueFile($filename, $rows);
    }

    /**
    * Retrieves database url from _INDEX_OF_REGISTERED_DATABASES.txt 
    * 
    * @param mixed $dbID
    */
    private static function getLocalDatabaseNameByDbId($dbID){
        if(self::isFileOutdated(self::getLocalIndexFilePath(), 24*3600)){
            if(!self::rebuildRegisteredDatabaseIndexFile(true)){
                return null;
            }
        }

        $rows = self::readKeyValueFile(self::getLocalIndexFilePath());
        $dbID = (string)intval($dbID);

        return @$rows[$dbID] ?: null;
    }

    private static function readUrlCacheFile(){
        $rows = array();
        $filename = self::getLocalUrlCacheFilePath();
        if(!file_exists($filename) || !is_readable($filename)){
            return $rows;
        }

        $lines = @file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if(!is_array($lines)){
            return $rows;
        }

        foreach($lines as $line){
            $parts = explode("	", trim($line));
            if(count($parts)<3) {continue;}
            $rows[intval($parts[0])] = array('url'=>$parts[1], 'ts'=>intval($parts[2]));
        }
        return $rows;
    }

    private static function writeUrlCacheFile($rows){
        $filename = self::getLocalUrlCacheFilePath();
        $dir = dirname($filename);
        if(!is_dir($dir) && !@mkdir($dir, 0775, true)){
            return false;
        }
        ksort($rows, SORT_NUMERIC);
        $tmp = $filename.'.tmp';
        $fh = @fopen($tmp, 'wb');
        if(!$fh){ return false; }
        if(!@flock($fh, LOCK_EX)){
            fclose($fh);
            @unlink($tmp);
            return false;
        }
        foreach($rows as $dbID=>$row){
            fwrite($fh, intval($dbID)."	".$row['url']."	".intval($row['ts'])."\n");
        }
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
        if(!@rename($tmp, $filename)){
            @unlink($tmp);
            return false;
        }
        return true;
    }

    /**
    * Retrieves url from local cached file _CACHE_OF_REGISTERED_DATABASE_URLS.txt
    * if last access was less than hour ago
    * 
    * @param mixed $dbID
    */
    private static function getCachedDatabaseUrl($dbID){
        $rows = self::readUrlCacheFile();
        $dbID = intval($dbID);
        if(!isset($rows[$dbID])){
            return null;
        }
        if((time() - intval($rows[$dbID]['ts'])) > 600){ // 10 min
            unset($rows[$dbID]);
            self::writeUrlCacheFile($rows);
            return null;
        }
        return $rows[$dbID]['url'];
    }

    private static function setCachedDatabaseUrl($dbID, $url){
        $rows = self::readUrlCacheFile();
        $now = time();
        foreach($rows as $id=>$row){
            if(($now - intval($row['ts'])) > 600){
                unset($rows[$id]);
            }
        }
        $rows[intval($dbID)] = array('url'=>$url, 'ts'=>$now);
        self::writeUrlCacheFile($rows);
    }
    
    private static function checkDbId($params){
        $dbID = intval(is_array($params)?@$params['dbID']:$params);
        if($dbID<=0){
            self::addError(HEURIST_INVALID_REQUEST, 'Database ID is not set or invalid. It must be an integer positive value.');
            return false;
        }
        return $dbID;
    }

    /**
    * Retrieves database url from _INDEX_OF_REGISTERED_DATABASES.txt
    * 
    * @param mixed $params
    */
    public static function getDatabaseUrlLocal($params){
        
        if(!self::initialize()) {return false;} //can not connect to index database
        
        $dbID = self::checkDbId($params);
        if(!$dbID){
            return false;
        }

        $dbName = self::getLocalDatabaseNameByDbId($dbID);
        if(!$dbName){
            self::addError(HEURIST_NOT_FOUND, 'Database with ID#'.$dbID.' is not found on server '
                            .self::normalizeServerUrl(self::extractServerUrl(HEURIST_BASE_URL_PRO)));
            return false;
        }

        return self::makeDatabaseUrl($dbName);
    }

    /**
    * Validates serverURL and check presence dbID
    *
    * @param mixed $params
    */
    private static function registrationValidateValues(&$params){

        //check url
        if(@$params["serverURL"]){
            $serverURL = $params["serverURL"];
            $serverURL_lc = strtolower($params["serverURL"]);

            //add default scheme
            if(!(strpos($serverURL_lc,HTTP_SCHEMA)===0 || strpos($serverURL_lc,HTTPS_SCHEMA)===0)){
                $serverURL = HTTPS_SCHEMA.$serverURL;  //https by default
                $serverURL_lc = strtolower($serverURL);
            }

            if(!(strpos(strtolower($serverURL_lc),HTTPS_SCHEMA)===0 || strpos(strtolower($serverURL_lc),HTTP_SCHEMA)===0)){
                self::addError(HEURIST_ACTION_BLOCKED,
                        'Database url does not have a trusted scheme');
                return false;
            }

            if(strpos($serverURL_lc, '://localhost')>0 ||  strpos($serverURL_lc, '://127.0.0.1')>0 || strpos($serverURL_lc, '://web.local')>0){
                self::addError(HEURIST_ACTION_BLOCKED,
                        'Registered databases cannot be on local server '.htmlspecialchars($serverURL));
                return false;
            }

            //sanitize URL
            $serverURL = USanitize::sanitizeURL($serverURL);
            if($serverURL==null){
                self::addError(HEURIST_ACTION_BLOCKED, 'Database url to be registered is not valid');
                return false;
            }

            $params["serverURL"] = $serverURL;
        }

        // Check the record exists
        $dbID = intval(@$params["dbID"]);
        if($dbID>0){
            $res = mysql__select_value(self::$mysqli, 'SELECT rec_ID FROM Records WHERE rec_ID = '.$dbID);
            if(!$res){
                self::addError(HEURIST_INVALID_REQUEST, 'Unable to locate registered database with ID '.$dbID);
                return false;
            }
        }
        return true;
    }

    /**
    * put your comment there...
    *
    * @param mixed $params
    */
    private static function registrationValidateUser($params){

        $mysqli = self::$mysqli;

        $usrEmail = @$params['usrEmail'];
        $usrPassword = @$params['usrPassword'];
        $dbID = intval(@$params['dbID']);

        $user_id = 0; // existing record owner

        // Retrieve user - OWNER CAN BE CHANGED + DETAILS CAN BE CHANGED
        $usrEmail = strtolower(trim($usrEmail));
        $user_id = mysql__select_value($mysqli, 'select ugr_ID from sysUGrps where lower(ugr_eMail)="'
            .$mysqli->real_escape_string($usrEmail).'"');

        // Check if the email address is recognised as a user name
        if($user_id <= 0){
            $user_id = mysql__select_value($mysqli, 'select ugr_ID from sysUGrps where lower(ugr_Name)="'
                .$mysqli->real_escape_string($usrEmail).'"');
        }

        // Validate password
        $user_pwd = '';
        $valid_password = !empty($usrPassword);
        if($valid_password && $user_id > 0){
            $user_pwd = mysql__select_value($mysqli, 'select ugr_Password from sysUGrps where ugr_ID=' . intval($user_id));
            $valid_password = passwordCheck($usrPassword, $user_pwd);
        }

        // Unable to retrieve existing user or provided password is wrong
        if($user_id <= 0 || !$valid_password){
            $errorMsg = ($user_id <= 0 ? 'We were unable to retrieve your user account within the Heurist Index database.'
                : 'We were unable to authenicate your account on the Heurist Index database')
            . '<br>Please ensure that your email address and password on the Heurist Index database match your current email address and password.'
            . '<br>Contact the Heurist team if you require help with updating your email address and password on the Heurist Index database.';

            self::addError(HEURIST_ACTION_BLOCKED, $errorMsg);
            return false;
        }

        if($dbID>0){
            // Check user is owner of record
            $res = mysql__select_value($mysqli, 'SELECT rec_ID FROM Records WHERE rec_ID = ' . $dbID . ' AND rec_OwnerUGrpID = ' . $user_id);
            if(!$res){
                self::addError(HEURIST_ACTION_BLOCKED, 'You do not own the record for this registered database, this could be due to a previous transfer in database ownership.'
                . '<br>Please contact the Heurist team and request that the record for your database be updated.');
                return false;
            }
        }

        return true;
    }

    /**
     * Removes a database registration.
     * If the request is from an external server, it makes a remote call.
     * Otherwise, it validates the user and deletes the registration record.
     *
     * @param array $params Parameters for deleting the registration. Expected keys:
     *                      'dbID' (int) - The ID of the database registration to delete.
     *                      'usrEmail' (string) - User's email for validation.
     *                      'usrPassword' (string) - User's password for validation.
     * @return bool|array False on failure, true on successful local deletion, or an array from remote call.
     */
    public static function registrationDelete($params){

        if(!self::initialize()) {return false;} //can not connect to index database

        if(self::$isOutSideRequest){
            return self::registrationRemoteCall($params, null, 'remote_registry_call');
        }

        $mysqli = self::$mysqli;

        $dbID = intval(@$params["dbID"]);

        if (!isPositiveInt($dbID)){
            self::addError(HEURIST_INVALID_REQUEST, 'Database ID not defined');
            return false;
        }

        if(!self::registrationValidateValues($params)){
            return false;
        }

        if(!self::registrationValidateUser($params)){
            return false;
        }

        mysql__supress_trigger($mysqli, false);
        ConceptCode::setSystem(self::$system);
        $rty_ID_registered_database = ConceptCode::getRecTypeLocalID(HEURIST_INDEX_DBREC);

        $keep_autocommit = mysql__begin_transaction($mysqli);
        self::$system->defineConstant('RT_RELATION');
        $stat = deleteOneRecord(self::$system, $dbID, $rty_ID_registered_database);

        if( array_key_exists('error', $stat) ){
            self::addError(HEURIST_INVALID_REQUEST, $stat['error']);
            $res = false;
            $mysqli->rollback();
        }else{
            $res = true;
            $mysqli->commit();
        }

        if($keep_autocommit===true) {$mysqli->autocommit(true);}

        return $res;
    }



    /**
     * Updates an existing database registration.
     * Handles remote calls if necessary. Validates parameters and user credentials.
     * Updates record URL, title, and specific fields related to the registration.
     *
     * @param array $params Parameters for updating the registration. Expected keys:
     *                      'dbID' (int) - The ID of the database registration to update.
     *                      'dbReg' (string, optional) - New database name.
     *                      'dbTitle' (string, optional) - New database title (description).
     *                      'serverURL' (string, optional) - New server URL for the database.
     *                      'usrEmail' (string) - User's email for validation.
     *                      'usrPassword' (string) - User's password for validation.
     * @return bool|int|array False on failure, the database ID (int) on successful local update, or an array from remote call.
     */
    public static function registrationUpdate($params){

        if(!self::initialize()) {return false;} //can not connect to index database

        if(self::$isOutSideRequest){
            return self::registrationRemoteCall($params, null, 'remote_registry_call');
        }

        $sys = self::$system;
        $mysqli = self::$mysqli;

        // Get parameters passed from update request
        $dbName = @$params['dbReg'];// Database name
        $dbTitle = @$params['dbTitle'];// Database description (DT_NAME)
        $dbID = intval(@$params['dbID']);

        if (!isPositiveInt($dbID)){
            self::addError(HEURIST_INVALID_REQUEST, 'Database ID not defined');
            return false;
        }

        if(!self::registrationValidateValues($params)){
            return false;
        }

        $serverURL = @$params["serverURL"];
        if(!$serverURL && !$dbName){
            self::addError(HEURIST_INVALID_REQUEST, 'Database name and url are not defined');
            return false;
        }

        if(!self::registrationValidateUser($params)){
            return false;
        }

        $defRecTitle = '<i>'.$dbName.'</i>';

        //update rec_URL and rec_Title
        $record = array(
            'rec_ID'=>$dbID,
            'rec_Modified'=>date(DATE_8601)
        );

        $err_msg = '';
        if(!empty($serverURL)){
            $record['rec_URL'] = $mysqli->real_escape_string($serverURL);
            $err_msg = 'URL (server URL)';
        }
        if(!empty($dbTitle)){
            $record['rec_Title'] = $defRecTitle.' : '.$dbTitle;
            $err_msg = $err_msg . (!empty($err_msg) ? ' and' : '') . ' Title (database name)';
        }

        $res = $dbID;
        if($err_msg!=''){  //!empty($serverURL) || !empty($dbTitle)
            $res = mysql__insertupdate($mysqli, 'Records', 'rec_', $record, true);
        }
        if($res != $dbID){
            self::addError(array(HEURIST_DB_ERROR,
                    'Failed to update database registration: ' . $err_msg, $mysqli->error));
            return false;
        }


        ConceptCode::setSystem($sys);

        $dbDisplayName = @$params['dbDisplayName'];
        $dbRights = @$params['dbRights'];

        if($dbDisplayName){
           self::recordUpdateField($sys, $dbID, '2-1', $dbDisplayName);
        }elseif($dbTitle){
           self::recordUpdateField($sys, $dbID, '2-1', $dbTitle);
        }
        if($dbTitle){
           self::recordUpdateField($sys, $dbID, '2-12', $dbTitle);
        }
        if($dbRights){
           self::recordUpdateField($sys, $dbID, '2-311', $dbRights);
        }
        if($dbName){
           self::recordUpdateField($sys, $dbID, '1176-469', $dbName);
        }

        //update record title
        // it does not work - need to convert TitleMask class from static
        // $rty_ID_registered_database = ConceptCode::getRecTypeLocalID(HEURIST_INDEX_DBREC);
        // recordUpdateTitle($sys, $dbID, $rty_ID_registered_database, $defRecTitle);

        return $dbID;
    }
    
    /**
    * Get URL for registered database by its $dbID
    * 
    *  Actions:
    *  1) checks local URL cache
    *  2) checks _INDEX_OF_REGISTERED_DATABASES
    *  3) central index lookup via action=info
    *  4) target server local lookup via action=url   
    * 
    * @param mixed $server
    * @param mixed $dbID
    * @return null
    */
    public static function registrationGet($params){
        //global $system;

        $dbID = self::checkDbId($params);
        if(!$dbID){
            return false;
        }
        if(!self::initialize()) {return false;} //can not connect to index database
        
        // checks local cache file
        $dburl = self::getCachedDatabaseUrl($dbID);
        if(is_string($dburl) && strpos($dburl ,'failure:')===0){ //this $dbID - is marked as failured
            self::addError(HEURIST_NOT_FOUND, substr(strstr($dburl, 'failure:'), strlen('failure:')));
            return false;
        }elseif($dburl){
            //found in cache
            return $dburl;
        }
        
        // is this db registered on this server
        $dburl = self::getDatabaseUrlLocal($dbID); //from _INDEX_OF_REGISTERED_DATABASES 
        if($dburl){
            //found in index file
            self::setCachedDatabaseUrl($dbID, $dburl);
            return $dburl;
        }
        $dburl = false;
        $server = null;
        
        if(@$params['action']!=='resolve_local'){
            
            // Ask the central Heurist Reference Index which server is registered
            // for this database ID. The URL stored in the central index is used only
            // to identify the likely server. It is not treated as the final database URL,
            // because the database name or version path may have changed on that server.
            //
            // Once the server is known, ask that server to resolve the database ID
            // locally via _INDEX_OF_REGISTERED_DATABASES. This gives the current
            // canonical URL for the database on that server.
            $resServer = self::registrationGetFromCentralIndexDb($params);
            if($resServer){
                $server = self::normalizeServerUrl($resServer);
            }
            if($server===null || $server===''){
                
                $err = self::getLastError();
                $errorStatus = $err['status'] ?? null;
                
                if(is_array($err)){
                    $errorMsg = self::formatErrorMessage($err);
                }else{
                    $errorMsg = 'Database server URL is not defined in central index database';    
                }
                
                if($errorStatus===HEURIST_NOT_FOUND){
                    self::setCachedDatabaseUrl($dbID, 'failure:'.$errorMsg);
                }elseif($errorStatus===null){
                    self::addErrorToCache($dbID, $errorMsg);    
                }
                
                return false;
            }

            $localServer = self::normalizeServerUrl(self::extractServerUrl(HEURIST_BASE_URL_PRO));

            if($server === $localServer){ //if $server is current one 
                //we already checked it on previos step - database missed on this server
                self::addErrorToCache($dbID, 'Database with ID#'.$dbID.' is not found in Heurist Reference Index database');
                
            }elseif(strpos($resServer, 'https://heurist-usyd.cloud.edu.au/heurist/?db=') === 0){
                // special case, USYD server is out of date
                $dburl = self::checkUSYDServer($resServer);

            }else{
                //request to server where database can reside
                $server = self::normalizeServerUrl($resServer, true);
                //REMOVE THIS REMARK IF PRODUCTION VERSION FAR BEHIND $server = str_replace('/heurist/','/h7-alpha/',$server); 
                $dburl = self::registrationRemoteCall(array('action'=>'resolve_local', 'dbID'=>$dbID), $server, 'registered_database_server_lookup');
            }
        }
        
        //keep url in cache - if dburl is false - it means failure
        if($dburl){
            self::setCachedDatabaseUrl($dbID, $dburl);
        }else{
            $err = self::getLastError();
            if(is_array($err)){
                $errorMsg = self::formatErrorMessage($err);
            }else{
                $errorMsg = 'Database with ID#'.$dbID.' is not found';
            }
            self::setCachedDatabaseUrl($dbID, 'failure:'.$errorMsg);
        }

        return $dburl;
    } 
    
    private static function formatErrorMessage(array $error): string{

        $message = ''; //'<h2>Request could not be resolved</h2>';
        $sysmsg = isset($error['sysmsg']) && is_array($error['sysmsg']) ?$error['sysmsg'] :[];

        if(!empty($error['message'])){
            $message .= '<p>'.strip_tags($error['message'],['p','b','br','strong']).'</p>';
        }

        if(!empty($sysmsg['remote_url'])){
            $message .= '<p><b>URL checked:</b> '
                .htmlspecialchars($sysmsg['remote_url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                .'</p>';
        }

        if(!empty($sysmsg['transport_error'])){
            $message .= '<p><b>Transport Error:</b> '
                .htmlspecialchars($sysmsg['transport_error'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                .'</p>';
        }
        
        if(!empty($sysmsg['code'])){
            $message .= '<p><b>Error code:</b> '
                .htmlspecialchars($sysmsg['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                .'</p>';
        }

        return $message;
    }     
        
    /**
     * Looks up the server registered for a database ID in the central
     * Heurist Reference Index.
     *
     * This returns the registered server/base URL, not necessarily the final
     * current database URL. The caller should subsequently ask that server
     * to resolve the database ID locally.
     *
     * @param array $params Expected key: dbID.
     * @return string|false Server/base URL on success, false on failure.
     */
    public static function registrationGetFromCentralIndexDb($params){

        $dbID = self::checkDbId($params);
        if(!$dbID){
            return false;
        }
        
        if(!self::initialize()) {return false;} //can not connect to index database

        
        if(self::$isOutSideRequest){ //request goes not from index server
            $params['action'] = 'central_index_lookup';
            return self::registrationRemoteCall($params, null, 'central_index_lookup'); //send request to index server
        }

        $database_url = null;

        $sys = self::$system;
        $mysqli = $sys->getMysqli();

        ConceptCode::setSystem($sys);
        $rty_ID_registered_database = ConceptCode::getRecTypeLocalID(HEURIST_INDEX_DBREC);

        $rec = mysql__select_row_assoc($mysqli,
            'select rec_Title, rec_URL from Records where rec_RecTypeID='
            .$rty_ID_registered_database.' and rec_ID='  //1-22
            .$dbID);

        if ($rec!=null){
            $database_url = @$rec['rec_URL'];
            if(isEmptyStr($database_url)){
                self::addError(HEURIST_NOT_FOUND,
                    'Database URL is not set in Heurist Reference Index database for database ID#'.$dbID);
                return false;
            }
            return $database_url;
        }


        $err = $mysqli->error;
        if($err){
            self::addError(HEURIST_DB_ERROR,
                 'Heurist Reference Index database is not accessible at the moment. Please try later');
        }else{
            self::addError(HEURIST_NOT_FOUND,
                 'Database with ID#'.$dbID.' is not found in Heurist Reference Index database');
        }
        return false;
    }


    /**
     * Adds a new database registration to the Heurist reference index.
     * Handles remote calls, validates parameters, creates or finds the user in the index,
     * and creates the registration record along with associated details.
     * Sends an email notification upon successful registration.
     *
     * @param array $params Parameters for adding the registration. Expected keys:
     *                      'db' (string, optional) - Current database name (if calling itself after remote).
     *                      'dbReg' (string) - Name of the database to register.
     *                      'dbTitle' (string) - Title/description of the database.
     *                      'dbVer' (string, optional) - Version of the database.
     *                      'usrEmail' (string) - Email of the registering user.
     *                      'usrPassword' (string) - Password of the registering user.
     *                      'usrName' (string) - Username of the registering user.
     *                      'usrFirstName' (string) - First name of the user.
     *                      'usrLastName' (string) - Last name of the user.
     *                      'serverURL' (string) - URL of the database server.
     * @return array|false An array containing 'dbID' and 'dbTitle' on successful registration,
     *                     or false on failure. Remote calls might also return an array.
     */
    public static function registrationAdd($params){

        if(!self::initialize()) {return false;} //can not connect to index database

        if(self::$isOutSideRequest){
            $dbname = @$params['db'];//keep
            $reg_rec = self::registrationRemoteCall($params);

            if($dbname!=null && $reg_rec){
                 //on remote servr
                 //$reg_rec = array('dbID'=>$dbID, 'dbTitle'=>$params['dbTitle']);
                 DbUtils::databaseUpdateRegistration($dbname, $reg_rec);
            }
            return $reg_rec;
        }

        $sys = self::$system;
        $mysqli = self::$mysqli;


        //validate serverURL
        if(!self::registrationValidateValues($params)){
            return false;
        }

        $indexdb_user_id = 0; // Flags problem if not reset

        // Get parameters passed from registration request
        // @ preceding $params avoids errors, sets Null if parameter missing
        $dbName = @$params['dbReg'];
        $dbTitle = @$params['dbTitle'];//DT_NAME
        $dbVersion = @$params['dbVer'];
        $dbDisplayName = @$params['dbDisplayName'];
        $dbRights = @$params['dbRights'];

        $usrEmail = @$params['usrEmail'];
        $usrPassword = @$params['usrPassword'];//hashed
        $usrName = @$params['usrName'];
        $usrFirstName = @$params['usrFirstName'];
        $usrLastName = @$params['usrLastName'];

        $serverURL = @$params['serverURL'];
        if(!$serverURL || !$dbName || !$dbTitle){
            self::addError(HEURIST_INVALID_REQUEST, 'Database name and url are not defined');
            return false;
        }

        if(!$usrEmail || !$usrName || !$usrFirstName || !$usrLastName || !$usrPassword) { // error in one or more parameters
            self::addError(HEURIST_INVALID_REQUEST, 'User parameters and credentials are not fully defined');
            return false;
        }

        // the record type for database (collection) descriptor records - fixed for Master database
        ConceptCode::setSystem($sys);
        $rty_ID_registered_database = ConceptCode::getRecTypeLocalID(HEURIST_INDEX_DBREC);

        if(!isPositiveInt($rty_ID_registered_database)){
            self::addError(HEURIST_SYSTEM_CONFIG, 'Record type "Database registration" ('.HEURIST_INDEX_DBREC.') bot found in Heurist reference index database');
            return false;
        }

        // if database is on main server it is possible to register database with user-defined ID
        /* DISABLED
        $newid = intval(@$params["newid"]);
        if($newid>0){
            if(!(strpos(strtolower($serverURL_lc), strtolower(HEURIST_MAIN_SERVER))===0)){
                return '0,It is possible to assign arbitrary ID for databases on heurist servers only';
            }
            $rec_id = mysql__select_value($mysqli, 'select rec_ID from Records where rec_ID='.$newid);
            if($rec_id>0){
                return '0,Database ID '.$newid.' is already allocated. Please choose different number';
            }
        }*/

        // allocate a new user for this database unless the user's email address is recognised
        // If a new user, log the user in and assign the record ownership to that user
        // By allocating users on the database based on email address we can allow them to edit their own registrations
        // but they can't touch anyone else's

        // Find the registering user in the index database, make them the owner of the new record
        $usrEmail = strtolower(trim($usrEmail));

        $indexdb_user_id = mysql__select_value($mysqli, 'select ugr_ID from sysUGrps where lower(ugr_eMail)="'
                .$mysqli->real_escape_string($usrEmail).'"');

        // Check if the email address is recognised as a user name
        // Added 19 Jan 2012: we also use email for ugr_Name and it must be unique, so check it has not been used
        if(!isPositiveInt($indexdb_user_id)) { // no user found on email, try querying on user name
            $indexdb_user_id = mysql__select_value($mysqli, 'select ugr_ID from sysUGrps where lower(ugr_Name)="'
                .$mysqli->real_escape_string($usrEmail).'"');
        }

        if(!isPositiveInt($indexdb_user_id)) { // did not find the user, create a new one and pass back login info

            // Note: we use $usrEmail as user name because the person's name may be repeated across many different users of
            // different databases eg. there are lots of johnsons, which will cause insert statement to fail as ugr_Name is unique.

            $indexdb_user_id = mysql__insertupdate($mysqli, 'sysUGrps', 'ugr_',
                array(
                    'ugr_Name'=>$usrEmail,
                    'ugr_Password'=>$usrPassword,
                    'ugr_eMail'=>$usrEmail,
                    'ugr_Enabled'=>'y',
                    'ugr_FirstName'=>$usrFirstName,
                    'ugr_LastName'=>$usrLastName,
                )
            );

            if(!isPositiveInt($indexdb_user_id)) { // Unable to create the new user
                self::addError(array(HEURIST_DB_ERROR,
                        'Unable to write new user in Heurist reference index database', $mysqli->error));
                return false;
            }
        }


        // write the core database record describing the database to be registered and allocate registration ID
        // This is not a fully valid Heurist record, we let the edit form take care of that
        // First look to see if there is an existing registration - note, this uses the URL to find the record, not the registration ID
        //
        // TOOD: Would be good to have a recaptcha style challenge otherwise can be called repeatedly
        // with slight URL variations to spawn multiple registrations of dummy databases

        $dbID = mysql__select_value($mysqli, "select rec_ID from Records where lower(rec_URL)='".
                        $mysqli->real_escape_string(strtolower(trim($serverURL)))."'");
        if($dbID>0) {
            //database with such id already exist
            self::addError(HEURIST_ACTION_BLOCKED, 'Database with such URL already registered');
            return false;
            //return $dbID;
        }else{// new registration

            $defRecTitle = '<i>'.$dbName.'</i> : '.$dbTitle;

            $mysqli->query('set @logged_in_user_id = 2');

            $record = array(
                    'rec_ID'=>0,  //($newid>0)?-$newid:0,
                    'rec_URL'=>$mysqli->real_escape_string($serverURL),
                    'rec_Added'=>date(DATE_8601),
                    'rec_RecTypeID'=> $rty_ID_registered_database,
                    'rec_Title' => $defRecTitle,
                    'rec_AddedByImport'=>0,
                    'rec_OwnerUGrpID'=>$indexdb_user_id,
                    'rec_NonOwnerVisibility'=>'public',
                    'rec_Popularity'=>99,
                );

            $dbID = mysql__insertupdate($mysqli, 'Records', 'rec_', $record, true);

            $mysqli->query('set @logged_in_user_id = '.$sys->getUserId());

            if($dbID>0){
                // Heurist_Reference_Index field mapping (Ian Johnson's metadata strategy, June 2026):
                //  Display name (sysIdentification.sys_dbName)        -> Database title, concept 2-1
                //  Database rights statement (sys_dbRights)           -> concept 2-311
                //  Long description entered on Design > Register      -> concept 2-12
                //    (>=40 chars, prefilled from sys_dbDescription but editable at registration time)
                if($dbDisplayName){
                    self::recordUpdateField($sys, $dbID, '2-1', $dbDisplayName, false);
                }elseif($dbTitle){
                    // fallback if no Display Name has been set in Design > Properties
                    self::recordUpdateField($sys, $dbID, '2-1', $dbTitle, false);
                }
                if($dbTitle){
                    self::recordUpdateField($sys, $dbID, '2-12', $dbTitle, false);
                }
                if($dbRights){
                    self::recordUpdateField($sys, $dbID, '2-311', $dbRights, false);
                }
                if($dbName){
                    self::recordUpdateField($sys, $dbID, '1176-469', $dbName, false);
                }
                if($dbVersion){
                    self::recordUpdateField($sys, $dbID, '1176-335', $dbVersion, false);
                }

                //update record title
                // it does not work - need to convert TitleMask class from static
                //recordUpdateTitle($sys, $dbID, $rty_ID_registered_database, $defRecTitle);


                // Write the record bookmark into the bookmarks table. It allows the user registering the database
                // to see their list of databases as My Bookmarks
                mysql__insertupdate($mysqli, 'usrBookmarks', 'bkm_',
                    array(
                        'bkm_UGrpID'=>$indexdb_user_id,
                        'bkm_RecID'=>$dbID
                    )
                );

                
                //send email to administrator about new database registration
                $email_text =
                "There is a new Heurist database registration on the Heurist Reference Index\n\n".
                "Database Title:     ".htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8')."\n".
                "Registration ID:    ".$dbID."\n". // was $indexdb_user_id, which is always 0 b/cnot yet logged in to reference index
                "DB Format Version:  ".$dbVersion."\n\n".
                // "User name:    ".$usrFirstName." ".$usrLastName."\n".  // comes out 'every user' b/c user not set
                // "Email address: ".$usrEmail."\n".                      // comes out 'not set for user 0'
                "Go to the address below to review the database:\n".
                $serverURL;

                $dbowner = user_getDbOwner($mysqli);
                $dbowner_Email = $dbowner['ugr_eMail'];
                $email_title = 'Database registration ID: '.$dbID.'. User ['.$indexdb_user_id.']';

                sendEmail($dbowner_Email, $email_title, $email_text);
            
                //END email -----------------------------------

                $res = array('dbID'=>$dbID, 'dbTitle'=>$params['dbTitle']);

                if(@$params['db']!=null && $dbID>0){
                     //on the same server
                     DbUtils::databaseUpdateRegistration($params['db'], $res);
                }

                return $res;
            }else{

                self::addError(array(HEURIST_DB_ERROR, 'Cannot write record in Heurist reference index ', $mysqli->error));
                return false;
            }

        }

    }//registrationAdd

    /**
    * Inserts or update field value (if multiple value - it updates first only)
    *
    * It is applicable for single values. If there are several values - it updated first only
    *
    * @param mixed $system
    * @param mixed $rec_ID
    * @param mixed $conceptCode
    * @param mixed $value
    * @param mixed $isnew
    */
    private static function recordUpdateField($system, $rec_ID, $conceptCode, $value, $is_exist=true){

        $dty_ID = ConceptCode::getDetailTypeLocalID($conceptCode);

        $mysqli = $system->getMysqli();

        $dtl_ID = -1;
        if($is_exist){
            $dtl_ID = mysql__select_value($mysqli, 'SELECT dtl_ID FROM recDetails WHERE dtl_DetailTypeID='.$dty_ID.' AND dtl_RecID='.$rec_ID);
        }

        $detail = array(
            'dtl_DetailTypeID'=>$dty_ID,
            'dtl_Value'=>$value
        );
        if(intval($dtl_ID)>0){
            // update
            $detail['dtl_ID'] = intval($dtl_ID);
        }else{
            //insert
            $detail['dtl_RecID'] = $rec_ID;
        }

        //Write the database title into the details, further data will be entered by the Heurist form
        mysql__insertupdate($mysqli, 'recDetails', 'dtl_', $detail);
    }

    /**
     * Simple check for University of Sydney database, as it's version of Heurist is out of date
     *
     * @param string $url URL to Heurist database on USYD server
     * @return string|false False on failure, otherwise URL to database
     */
    private static function checkUSYDServer($url){

        if(!filter_var($url, FILTER_VALIDATE_URL)){
            self::addError(HEURIST_INVALID_REQUEST, 'Invalid URL to University of Sydney server.');
            return false;
        }

        $headers = get_headers($url);

        if(!$headers || strpos($headers[0], '200') === false){
            self::addError(HEURIST_ACTION_BLOCKED, 'Unable to find Heurist database on University of Sydney server.');
            return false;
        }

        return $url;
    }
}
