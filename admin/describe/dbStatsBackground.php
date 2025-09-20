<?php
/**
* dbStatsBackground.php - Creates and manages database statistics files.
*
* @fileOverview This script runs in the background to generate a text file (`db_stats.txt`)
*               containing simple statistics for each database on the server. These statistics
*               are stored locally and can be sent to a main Heurist server for aggregation.
*               It also handles receiving stats files from other servers if this is the main server.
* @project     Heurist academic knowledge management system
* @package Admin
* @link        https://HeuristNetwork.org
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/

require_once __DIR__ . '/../../autoload.php';

use hserv\utilities\USanitize;

define('PDIR', '../../');

// Initialise system and connect MySQL
$system = new hserv\System();
$is_inited = $system->init(null, false, false);

if(!$is_inited){
    $system->errorExit('Heurist failed to initialise', HEURIST_ERROR);
}

$mysqli = $system->getMysqli();

$databases = mysql__getdatabases4($mysqli);
if(empty($databases)){
    $system->errorExit('No databases', HEURIST_ACTION_BLOCKED);
}

$req_params = USanitize::sanitizeInputArray();
$sysadmin_pwd = USanitize::getAdminPwd();

define('SERVER_NAME', !defined('HEURIST_SERVER_NAME') || empty(HEURIST_SERVER_NAME) ? gethostbyname(gethostname()) : HEURIST_SERVER_NAME);

$isPublic = filter_var($_SERVER['SERVER_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
if(!$isPublic || SERVER_NAME == 'localhost' || SERVER_NAME == '127.0.0.1' || SERVER_NAME == '::1' || isLocalHost()){
    $system->errorExitApi('Function is not for local setups', HEURIST_ACTION_BLOCKED);
    exit;
}

$forcedRefresh = !empty($sysadmin_pwd) && !$system->verifyActionPassword($sysadmin_pwd, $passwordForServerFunctions);

$is_main_server = strpos(strtolower(HEURIST_BASE_URL), strtolower(HEURIST_MAIN_SERVER)) !== false;

// Define various constants for file & file paths
define('FILESTORE_ROOT', $system->getFileStoreRootFolder());
define('DB_STATS', FILESTORE_ROOT . '_DB_STATS'); // FILESTORE/_DB_STATS
define('ALL_STATS', FILESTORE_ROOT . '_ALL_SERVER_STATS'); // FILESTORE/_ALL_SERVER_STATS
define('DB_STATS_FILE', DB_STATS . '/db_stats.txt'); // FILESTORE/DB_STATS/db_stats.txt
define('LOCKING_FILE', DB_STATS . '/db_stats_lock.txt'); // prevent the function from running multiple times at once

// Check for file lock
if(file_exists(LOCKING_FILE)){
    dataOutput(['status' => HEURIST_ACTION_BLOCKED, 'message' => 'Operation already in progress']);
    exit;
}
// Create file lock
file_put_contents(LOCKING_FILE, '1');

// Check if db_stats.txt needs updating
if(array_key_exists('refresh', $req_params)){

    $last_update = file_exists(DB_STATS_FILE) ? filemtime(DB_STATS_FILE) : false;

    if(!$last_update || $last_update < strtotime('-1 month') || $forcedRefresh){
        createStats();
        sendStatsToMain();
    }

    dataOutput(['status' => HEURIST_OK, 'data' => 1]);
    exitScript();
}

// Uploading stats from external server
if(count($_FILES) == 1 && array_key_exists('stats_file', $_FILES)){

    if(!$is_main_server){ // operation is for main server only
        exitScript(HEURIST_INVALID_REQUEST, 'Invalid request', true);
    }

    $temp_file = USanitize::sanitizePath($_FILES['stats_file']['tmp_name'], true);

    // Check file typing, only accepting text files
    $type = null;
    if(extension_loaded('fileinfo')){
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $type = finfo_file($finfo, $temp_file);
    }else{
        $type = strtolower(pathinfo($temp_file, PATHINFO_EXTENSION));
    }

    if($type !== 'text/plain' && $type !== 'txt'){
        exitScript(HEURIST_REQUEST_DENIED, 'Invalid file type', true);
    }

    checkDirectory(ALL_STATS);

    if(!array_key_exists('server', $req_params) || empty($req_params['server'])){
        $system->addError(HEURIST_REQUEST_DENIED, 'Missing server name');
        exitScript(null, null, true);
    }

    $server_name = $req_params['server'];

    $server_name = htmlspecialchars($server_name);
    $new_file = ALL_STATS . "/{$server_name}_db_stats.txt"; // in case the zipping fails, add server name as prefix

    move_uploaded_file($temp_file, $new_file);

    zipStats($new_file, $server_name, true);

    dataOutput(['status' => HEURIST_OK, 'data' => 1]);
    exitScript();
}

/**
 * Send db_stats.txt to main server's _ALL_STATS directory.
 * If this is the main server, it zips the stats locally. Otherwise, it POSTs the file.
 *
 * @global hserv\System $system The global system object.
 * @global bool $is_main_server Flag indicating if this is the main Heurist server.
 * @return void This function calls exitScript() and does not return.
 */
function sendStatsToMain(){

    global $system, $is_main_server;

    $script = HEURIST_MAIN_SERVER . '/h7-alpha/admin/describe/allServerStats.php'; // @todo: replace '/h7-alpha' with '/heurist', once /heurist has been updated

    if(!file_exists(DB_STATS_FILE) && !createStats()){
        exitScript(null, null, true);
    }elseif($is_main_server){

        checkDirectory(ALL_STATS);

        zipStats(DB_STATS_FILE, SERVER_NAME);

        dataOutput(['status' => HEURIST_OK, 'data' => 1]);
        exitScript();
    }

    $file_path = resolveFilePath(DB_STATS_FILE);
    $file_name = pathinfo($file_path, PATHINFO_BASENAME);

    $curl_file = new CURLFile($file_path, 'text/plain', $file_name);

    $ch = curl_init($script);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['stats_file' => $curl_file, 'server' => SERVER_NAME, 'type' => 'db_stats']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_POSTREDIR, 3);

    $response = curl_exec($ch);
    $error = curl_error($ch);

    curl_close($ch);

    if($error){
        $system->addErrorArr($error);
        exitScript(null, null, true);
    }elseif(!$response){
        exitScript(HEURIST_ERROR, 'Unable to send stats to main server', true);
    }

    dataOutput($response);
    exitScript();
}

/**
 * Create db_stats.txt file within local _DB_STATS directory.
 * Per database, includes: record count, last newest record, last record modification, new records per month.
 *
 * @global hserv\System $system The global system object.
 * @return bool True on success, false on failure.
 */
function createStats(){

    global $system;

    checkDirectory(DB_STATS);

    $mysqli = $system->getMysqli();

    $databases = mysql__getdatabases4($mysqli);

    $head_liner = "Server: " . SERVER_NAME . "   Database count: " . count($databases) . "   Date: " . date("Y-m-d H:i:s") . "\n";
    if(!file_put_contents(DB_STATS_FILE,  $head_liner)){
        $system->addError(HEURIST_ERROR, 'Unable to write to stats file');
        return false;
    }

    foreach($databases as $database){

        mysql__usedatabase($mysqli, $database);

        $rec_count = mysql__select_value($mysqli, "SELECT COUNT(rec_ID) FROM Records WHERE rec_FlagTemporary != 1");
        $rec_count = $rec_count ?: 0;

        $last_add = 'None';
        $last_mod = 'None';
        $rec_by_month = 'None';
        if($rec_count > 0){

            $last_add = mysql__select_value($mysqli, "SELECT DATE_FORMAT(rec_Added, '%Y-%m-%d') FROM Records WHERE rec_FlagTemporary != 1 ORDER BY rec_Added DESC LIMIT 1");

            $last_mod = mysql__select_value($mysqli, "SELECT DATE_FORMAT(rec_Added, '%Y-%m-%d') FROM Records WHERE rec_FlagTemporary != 1 ORDER BY rec_Modified DESC LIMIT 1");

            $rec_by_month = mysql__select_assoc2($mysqli, "SELECT DATE_FORMAT(rec_Added, '%Y-%m') AS date, COUNT(rec_ID) AS count FROM Records WHERE rec_FlagTemporary != 1 GROUP BY DATE_FORMAT(rec_Added, '%Y-%m') ORDER BY rec_Added");

            array_walk($rec_by_month, function(&$count, $date){ $count = "{$date}={$count}"; });
            $rec_by_month = implode(', ', $rec_by_month);
        }

        $line = "{$database}, count {$rec_count}, lastAdd {$last_add}, lastUpdate {$last_mod}, {$rec_by_month}\n";

        if(!file_put_contents(DB_STATS_FILE, $line, FILE_APPEND)){
            $system->addError(HEURIST_ERROR, 'Unable to write to stats file');
            return false;
        }
    }

    return true;
}

/**
 * Zips the db stats file into an archive within the _ALL_STATS directory.
 *
 * @param string $file_to_zip     Path to the file to be zipped (e.g., db_stats.txt).
 * @param string $server_name     The name of the server, used for naming the zip archive.
 * @param bool   $delete_original Optional. If true, the original text file will be deleted after zipping. Defaults to false.
 * @return void This function calls exitScript() on failure and does not return directly.
 */
function zipStats($file_to_zip, $server_name, $delete_original = false){

    $zip_name = ALL_STATS . "/{$server_name}.zip";

    // Zip text file
    $zip = new ZipArchive();
    if(!$zip->open($zip_name, ZipArchive::CREATE)){
        exitScript(HEURIST_ERROR, 'Failed to create zip folder for stats', true);
    }

    if(!$zip->addFile($file_to_zip, "db_stats.txt")){
        $zip->close();
        exitScript(HEURIST_ERROR, 'Failed to add stats into zip', true);
    }
    $zip->close();

    if($delete_original){
        fileDelete($file_to_zip);
    }
}

/**
 * Checks if a directory exists and is writable. Creates it if it doesn't exist.
 * Calls exitScript() on failure.
 *
 * @param string $dir The path to the directory.
 * @return void This function calls exitScript() on failure and does not return directly.
 */
function checkDirectory($dir){

    $dir_name = explode('/', $dir);
    $dir_name = array_pop($dir_name);

    $is_dir_writable = folderExists($dir, true);
    if($is_dir_writable === -1){
        $res = folderCreate2($dir, '');
        if($res !== ''){
            exitScript(HEURIST_ERROR, "Heurist is unable to create the {$dir_name} directory", true);
        }
    }elseif($is_dir_writable < 0){
        exitScript(HEURIST_ERROR, "Heurist is unable to access the {$dir_name} directory", true);
    }
}

/**
 * Removes the lock file and exits the script.
 * If an error is indicated, it calls the system error exit handler.
 *
 * @global hserv\System $system The global system object.
 * @param string|null $status    HTTP status code (used if $is_error is true and $msg is provided).
 * @param string|null $msg       Error message (used if $is_error is true).
 * @param bool        $is_error  Whether this exit is due to an error.
 * @return void This function always exits the script and does not return.
 */
function exitScript($status = null, $msg = null, $is_error = false){

    global $system;

    fileDelete(LOCKING_FILE);

    if($is_error){
        empty($msg) ? $system->errorExitApi(null, null, false) : $system->errorExit($msg, $status);
    }

    exit;
}
