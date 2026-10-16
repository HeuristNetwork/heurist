<?php
/**
* allServerStats.php - Saves server statistics to the _ALL_SERVER_STATS directory.
*
* @fileOverview This script receives statistics files (currently 'db_stats') from other Heurist servers
*               and stores them, typically in a compressed format. It's designed to be used by the
*               main Heurist server to aggregate statistics from various installations.
* @project     Heurist academic knowledge management system
* @package Admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/



require_once __DIR__ . '/../../autoload.php';

use hserv\utilities\USanitize;

$allowed_stats = ['db_stats'];
$req_params = USanitize::sanitizeInputArray();

// Initialise system and connect MySQL
$system = new hserv\System();
$is_inited = $system->init(null, false, false);

define('FILESTORE_ROOT', $system->getFileStoreRootFolder());
define('ALL_STATS', FILESTORE_ROOT . '_ALL_SERVER_STATS');

if(!array_key_exists('type', $req_params)){
    $system->errorExitApi('Stats type not supported', HEURIST_INVALID_REQUEST, false);
}elseif(strpos(HEURIST_BASE_URL, HEURIST_MAIN_SERVER) === false){
    $system->errorExitApi('Only the main Heurist server can use this function', HEURIST_ACTION_BLOCKED, false);
}elseif(!array_key_exists('server', $req_params) || empty($req_params['server'])){
    $system->errorExitApi('Missing server name', HEURIST_REQUEST_DENIED, false);
}

$server_name = trim((string)$req_params['server']);
if($server_name === 'localhost' || $server_name === '127.0.0.1' || $server_name === '::1' || isLocalHost()){
    $system->errorExitApi('Function is not for local setups', HEURIST_ACTION_BLOCKED);
    exit;
}

ensureStatsFolder();

if($req_params['type'] === 'server_activity'){
    saveServerActivity($req_params);
    dataOutput(['status' => HEURIST_OK, 'data' => 1]);
    exit;
}

if(empty($_FILES) || (!array_key_exists('stats_file', $_FILES) && !array_key_exists('stats_zip', $_FILES))){
    $system->errorExitApi('Stats file is missing', HEURIST_INVALID_REQUEST, false);
}

$stat_types = $req_params['type'];
$stat_types = explode(',', $stat_types);
$stat_types = $stat_types[0] === 'all'
    ? $allowed_stats
    : array_filter($stat_types, function($type) use ($allowed_stats) { return in_array($type, $allowed_stats); });

if(empty($stat_types)){
    $system->errorExitApi('Stats type not supported', HEURIST_INVALID_REQUEST, false);
}
$type_label = count($stat_types) == 1 ? $stat_types[0] : 'multiple';

$index = array_key_exists('stats_file', $_FILES) ? 'stats_file' : 'stats_zip';
$temp_file = $_FILES[$index]['tmp_name'];

// Check file typing, only accepting text files
$file_type = null;
if(extension_loaded('fileinfo')){
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $file_type = finfo_file($finfo, $temp_file);
}else{
    $file_type = strtolower(pathinfo($temp_file, PATHINFO_EXTENSION));
}

$is_zip = $file_type === 'application/zip' && $file_type === 'zip';
if(!$is_zip && $file_type !== 'text/plain' && $file_type !== 'txt'){
    $system->errorExitApi('Invalid file provided', HEURIST_ACTION_BLOCKED, false);
}elseif(!$is_zip && count($stat_types) > 1){
    $system->errorExitApi('Multiple loose files are not handled', HEURIST_ACTION_BLOCKED, false);
}

$new_file = ALL_STATS . "/{$server_name}_{$type_label}_stats." . ($is_zip ? 'zip' : 'txt'); // in case the zipping fails, add server name as prefix

move_uploaded_file($temp_file, $new_file);

if($is_zip){
    transferZipFiles($new_file, $server_name, $stat_types);
}else{
    transferTextFile($new_file, $server_name, $stat_types[0]);
}

dataOutput(['status' => HEURIST_OK, 'data' => 1]);
exit;

/**
 * Ensure the shared server statistics directory exists and is writable.
 *
 * @return void
 */
function ensureStatsFolder(){

    global $system;

    $dir_name = basename(ALL_STATS);
    $is_dir_writable = folderExists(ALL_STATS, true);

    if($is_dir_writable === -1){
        $res = folderCreate2(ALL_STATS, '', true);
        if($res !== ''){
            $system->errorExitApi("Heurist is unable to create the {$dir_name} directory", HEURIST_ERROR, false);
        }
    }elseif($is_dir_writable < 0){
        $system->errorExitApi("Heurist is unable to access the {$dir_name} directory", HEURIST_ERROR, false);
    }elseif(!file_exists(ALL_STATS . '/.htaccess')){
        allowWebAccessForForlder(ALL_STATS . '/');
    }
}

/**
 * Append one server activity report to servers.log as a CSV row.
 *
 * Columns: date, server, server_ip, server_admin, server_db_count.
 *
 * @param array $params Sanitised request parameters.
 * @return void
 */
function saveServerActivity($params){

    global $system;

    $server = trim((string)($params['server'] ?? ''));
    $server_ip = trim((string)($params['server_ip'] ?? ''));
    $server_admin = trim((string)($params['server_admin'] ?? ''));
    $server_db_count = $params['server_db_count'] ?? null;

    if($server === ''){
        $system->errorExitApi('Missing server name', HEURIST_INVALID_REQUEST, false);
    }
    if($server_ip !== 'Unknown' && !filter_var($server_ip, FILTER_VALIDATE_IP)){
        $system->errorExitApi('Invalid server IP address', HEURIST_INVALID_REQUEST, false);
    }
    if(filter_var($server_db_count, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false){
        $system->errorExitApi('Invalid server database count', HEURIST_INVALID_REQUEST, false);
    }

    $handle = @fopen(ALL_STATS . '/servers.log', 'ab');
    if($handle === false){
        $system->errorExitApi('Unable to open server activity log', HEURIST_ERROR, false);
    }

    if(!flock($handle, LOCK_EX)){
        fclose($handle);
        $system->errorExitApi('Unable to lock server activity log', HEURIST_ERROR, false);
    }

    $report_date = getNow()->format('Y-m-d');
    $saved = fputcsv($handle, [$report_date, $server, $server_ip, $server_admin, (int)$server_db_count]);
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    if($saved === false){
        $system->errorExitApi('Unable to save server activity report', HEURIST_ERROR, false);
    }
}

/**
 * Zip text into server's file within ALL_SERVER_STATS.
 *
 * @global hserv\System $system The global system object.
 * @param string $remote_file Location of remote server's text file.
 * @param string $server_name Remote server's name.
 * @param string $file_name   Stat type, becomes local file's name.
 * @return void
 */
function transferTextFile($remote_file, $server_name, $file_name){

    global $system;

    $to_zip = new ZipArchive();
    if($to_zip->open(ALL_STATS . "/{$server_name}.zip", ZipArchive::CREATE) !== true){
        $system->errorExitApi('Unable to create local zip for remote server', HEURIST_ERROR, false);
    }

    $to_zip->addFile($remote_file, "{$file_name}.txt");
    $to_zip->close();

    fileDelete($remote_file);
}

/**
 * Transfer text files from zip folder, received from remote server.
 *
 * @global hserv\System $system The global system object.
 * @param string $remote_zip      Location of remote server's zip folder.
 * @param string $server_name     Remote server's name.
 * @param array<string> $allowed_stats Filenames to save.
 * @return void
 */
function transferZipFiles($remote_zip, $server_name, $allowed_stats){

    global $system;

    $local_zip = ALL_STATS . "/{$server_name}.zip";

    $from_zip = new ZipArchive();
    if($from_zip->open($remote_zip) !== true){
        fileDelete($remote_zip);
        $system->errorExitApi('Unable to open archive from remote server', HEURIST_ERROR, false);
    }

    $to_zip = new ZipArchive();
    if($to_zip->open($local_zip, ZipArchive::CREATE) !== true){
        fileDelete($remote_zip);
        $system->errorExitApi('Unable to create local zip for remote server', HEURIST_ERROR, false);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    foreach($allowed_stats as $file_name){

        $idx = $from_zip->locateName("{$file_name}.txt");
        if(!$idx){
            continue;
        }

        $type = finfo_file($finfo, "zip://{$remote_zip}#{$file_name}.txt");

        if($type !== 'text/plain'){
            continue;
        }

        $to_zip->addFromString("{$file_name}.txt", $from_zip->getFromIndex($idx));
    }
    finfo_close($finfo);

    $to_zip->close();
    $from_zip->close();

    fileDelete($remote_zip);
}
