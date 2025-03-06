<?php
/**
* dbStatsBackground: Create a text file filled with simple statics about each database, stored locally, and sent to the Main server
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
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

//const SERVER_NAME = !defined('HEURIST_SERVER_NAME') || empty(HEURIST_SERVER_NAME) ? gethostbyname(gethostname()) : HEURIST_SERVER_NAME;
define('SERVER_NAME', !defined('HEURIST_SERVER_NAME') || empty(HEURIST_SERVER_NAME) ? gethostbyname(gethostname()) : HEURIST_SERVER_NAME);

if(SERVER_NAME == 'localhost' || SERVER_NAME == '127.0.0.1' || SERVER_NAME == '::1' || isLocalHost()){
    dataOutput(['status' => HEURIST_ACTION_BLOCKED, 'message' => '']);
    exit;    
}

$is_main_server = strpos(strtolower(HEURIST_BASE_URL), strtolower(HEURIST_MAIN_SERVER)) !== false;

// Define various constants for file & file paths
define('FILESTORE_ROOT', $system->getFileStoreRootFolder());
define('DB_STATS', FILESTORE_ROOT . '_DB_STATS'); // FILESTORE/_DB_STATS
define('DB_STATS_FILE', DB_STATS . '/db_stats.txt'); // FILESTORE/_DB_STATS/db_stats.txt
define('ALL_STATS', FILESTORE_ROOT . '_ALL_SERVER_STATS'); // FILESTORE/_ALL_SERVER_STATS
define('TESTING_FILE', 'testing_stats.txt'); // for testing output ONLY
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

    if(!$last_update || $last_update < strtotime('-1 month')){
        createStats();
        sendStatsToMain();
    }

    dataOutput(['status' => HEURIST_OK, 'data' => 1]);
    exitScript();
    exit;
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
        exitScript(HEURIST_REQUEST_DENIED, 'Missing server name', true);
    }

    $server_name = $req_params['server'];

    $server_name = htmlspecialchars($server_name);
    $new_file = ALL_STATS . "/{$server_name}_db_stats.txt"; // in case the zipping fails, add server name as prefix

    move_uploaded_file($temp_file, $new_file);

    zipStats($new_file, $server_name, true);

    dataOutput(['status' => HEURIST_OK, 'data' => 1]);
    exitScript();
    exit;
}

/**
 * Send db_stats.txt to main server's _ALL_STATS
 *
 * @return never
 */
function sendStatsToMain(){

    global $system, $is_main_server;

    $script = HEURIST_MAIN_SERVER . '/h6-bm/admin/describe/dbStatsBackground.php';

    if(!file_exists(DB_STATS_FILE) && !createStats()){
        exitScript(null, null, true);
    }elseif($is_main_server){

        checkDirectory(ALL_STATS);

        zipStats(DB_STATS_FILE, SERVER_NAME);

        dataOutput(['status' => HEURIST_OK, 'data' => 1]);
        exitScript();
        exit;
    }

    $file_path = resolveFilePath(DB_STATS_FILE);

    $curl_file = new CURLFile($file_path);

    $ch = curl_init($script);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['stats_file' => $curl_file, 'server' => SERVER_NAME]));
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
    exit;
}

/**
 * Create db_stats.txt file within local _DB_STATS
 *  Per database, includes: record count, last newest record, last record modification, new records per month
 *
 * @return bool success or failure
 */
function createStats(){

    global $system;

    checkDirectory(DB_STATS);

    $mysqli = $system->getMysqli();

    $databases = mysql__getdatabases4($mysqli);

    $head_liner = "Server: " . SERVER_NAME . "   Database count: " . count($databases) . "\n";
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
 * Zips the db stats within _ALL_STATS
 *
 * @param string $file_to_zip file going into zip achive
 * @param string $server_name used for zip name
 * @param bool $delete_original delete the original text file, if file is from external server
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
        fileDelete($zip_name);
        exitScript(HEURIST_ERROR, 'Failed to add stats into zip', true);
    }
    $zip->close();

    if($delete_original){
        fileDelete($file_to_zip);
    }
}

/**
 * Check if directory: has been created and can be written to
 *
 * @param string $dir directory to check
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
 * Remove file lock and exit on error if necessary
 *
 * @param integer|null $status HTTP status code
 * @param string|null $msg error message
 * @param bool $is_error whether this is for an error
 */
function exitScript($status = null, $msg = null, $is_error = false){

    global $system;

    fileDelete(LOCKING_FILE);

    if($is_error){
        empty($msg) ? $system->errorExitApi(null, null, false) : $system->errorExit($msg, $status);
    }
}
