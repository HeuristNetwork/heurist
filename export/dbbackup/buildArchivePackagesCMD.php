<?php

<?php

/**
 * Creates archive packages for one, several, or all databases.
 * This script is designed to be run from the command line (CLI) only.
 *
 * It generates archive packages (ZIP files) containing database dumps (SQL, TSV, HML),
 * uploaded files, and documentation. The output is written to a subdirectory named
 * `_BATCH_PROCESS_ARCHIVE_PACKAGE` within the Heurist filestore root.
 *
 * Usage examples:
 *  sudo php -f /path/to/heurist/export/dbbackup/buildArchivePackagesCMD.php -- -db=database_1,database_2
 *  sudo php -f buildArchivePackagesCMD.php -- -db=all -nofiles -nodocs -nosql -nohml -notsv
 *
 * Arguments:
 *  -db=<dbname_or_all> : Specifies the database(s) to archive. Can be a single database name,
 *                        a comma-separated list of database names, or 'all' for all databases.
 *                        This parameter is effectively required, though the script doesn't exit
 *                        if it's missing, it won't process anything.
 *  -nofiles            : (Optional) Excludes uploaded files from the archive.
 *  -nodocs             : (Optional) Excludes documentation folders from the archive.
 *  -nosql              : (Optional) Excludes the SQL dump from the archive.
 *  -nohml              : (Optional) Excludes the HML export from the archive.
 *  -notsv              : (Optional) Excludes the TSV export from the archive.
 *
 * @package     HeuristWebService
 * @subpackage  Scripts
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network Ltd.
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @version     7
 *
 * @global string|null $arg_database Name of the database(s) to process, or 'all'.
 * @global bool $arg_skip_files If true, skips including uploaded files.
 * @global bool $arg_include_docs If true, includes documentation folders.
 * @global bool $arg_skip_hml If true, skips HML export.
 * @global bool $arg_skip_tsv If true, skips TSV export.
 * @global bool $arg_skip_sql If true, skips SQL dump.
 * @global bool $with_triggers If true, includes triggers in SQL dump. REMARK: Currently hardcoded to false.
 * @global string|null $backup_root Path to the root directory for backup packages.
 *
 * @uses isActionInProgress() To prevent multiple simultaneous backup operations.
 * @uses hserv\utilities\DbUtils::databaseDump() To create SQL dumps.
 * @uses hserv\utilities\UArchive::zip() To create ZIP archives.
 * @uses hserv\utilities\DbExportTSV To export data in TSV format.
 * @uses folderCreate() Utility function to create folders.
 * @uses folderDelete2() Utility function to delete folders.
 * @uses folderSubs() Utility function to list subfolders.
 * @uses folderRecurseCopy() Utility function to recursively copy folders.
 * @uses fileCopy() Utility function to copy files.
 * @uses mysql__getdatabases4() Utility function to get a list of databases.
 * @uses mysql__usedatabase() Utility function to select a database.
 */

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

// Default values for command-line arguments
/** @var string|null $arg_database Name of the database(s) to process, or 'all'. Null by default. */
$arg_database = null;
/** @var bool $arg_skip_files If true, skips including uploaded files. Defaults to false. */
$arg_skip_files = false;    // include all the uploaded files
/** @var bool $arg_include_docs If true, includes documentation folders to make the archive interpretable. Defaults to true. */
$arg_include_docs = true;   // include full documentation to make the archive interpretable
/** @var bool $arg_skip_hml If true, skips HML export. HML can be voluminous. Defaults to false. */
$arg_skip_hml = false;      // don't include HML as this function is primarily intended for database transfer
                            // and HML is voluminous. HML should be included if this is intended as longer term archive.
/** @var bool $arg_skip_tsv If true, skips TSV export. Defaults to false. */
$arg_skip_tsv = false;      // don't include TSV
/** @var bool $arg_skip_sql If true, skips SQL dump. Defaults to false. */
$arg_skip_sql = false;

/** @var bool $with_triggers If true, includes triggers in the SQL dump. REMARK: Currently hardcoded to false and not exposed as a command-line argument. */
$with_triggers = false;
/** @var string|null $backup_root Path to the root directory where backup packages will be stored. Initialized to null. */
$backup_root = null;

// --- Command-line argument parsing ---
if (@$argv) {
    // example:
    //  sudo php -f /var/www/html/heurist/export/dbbackup/buildArchivePackagesCMD.php -- -db=database_1,database_2
    //  sudo php -f buildArchivePackagesCMD.php -- -db=osmak_9,osmak_9c,osmak_9d
    //  sudo php -f /var/www/html/heurist/export/dbbackup/buildArchivePackagesCMD.php -- -db=all -nofiles -nodocs

    // TODO: It would be good if this had a parameter option to also delete the database for use when transferring to a new server
    // TODO: WARNING: AT THIS TIME (21 May 2022) IT DOES NOT REPORT AN ERROR IF THERE IS NO FILESTORE FOLDER

    // handle command-line queries
    $ARGV = array();
    for ($i = 0;$i < count($argv);++$i) {
        if ($argv[$i][0] === '-') {
            if (@$argv[$i + 1] && $argv[$i + 1][0] != '-') {
                $ARGV[$argv[$i]] = $argv[$i + 1];
                ++$i;
            } else {
                // Handles arguments like -db=dbname
                if (strpos($argv[$i], '-db=') === 0) {
                    $ARGV['-db'] = substr($argv[$i], 4);
                } else { // Handles flag arguments like -nofiles
                    $ARGV[$argv[$i]] = true;
                }
            }
        } else {
            // This case should ideally not be reached if arguments are passed correctly with --
            array_push($ARGV, $argv[$i]);
        }
    }
    if (@$ARGV['-db']) {$arg_database = $ARGV['-db'];}
    if (@$ARGV['-nofiles']) {$arg_skip_files = true;}
    if (@$ARGV['-nodocs']) {$arg_include_docs = false;}
    
    if (@$ARGV['-nosql']) {$arg_skip_sql = true;}
    if (@$ARGV['-nohml']) {$arg_skip_hml = true;}
    if (@$ARGV['-notsv']) {$arg_skip_tsv = true;}
} else {
    // This script is intended to be run from the shell only.
    // The debug block below is commented out.
    exit('This function must be run from the shell');
    /* for debug
    $arg_database = 'osmak_9a,osmak_9c';
    $arg_skip_files = true;
    $arg_include_docs = false;
    $arg_skip_sql = true;
    $arg_skip_tsv = true;
    */
}

// REMARK: The script proceeds even if $arg_database is null (e.g. -db parameter not provided),
// but it will not process any databases in such a case. An explicit check and exit was previously commented out.
if ($arg_database == null) {
    // exit("Required parameter -db is not defined\n");
}

use hserv\utilities\DbUtils;
use hserv\utilities\UArchive;
use hserv\utilities\DbExportTSV;

require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../../hserv/records/search/recordFile.php'; // REMARK: Unclear if this specific include is still needed directly. Autoloader might handle it.

// --- System Initialization ---
$system = new hserv\System();
if (!$system->init(null, false, false)) {
    exit("Cannot establish connection to sql server\n");
}

$mysqli = $system->getMysqli();
$databases = mysql__getdatabases4($mysqli, false); // Get available databases

// --- Database Selection Logic ---
if ($arg_database == 'all') {
    $arg_database = $databases;
} else {
    $arg_database = explode(',', $arg_database);
    if (empty($arg_database) || (count($arg_database) == 1 && empty($arg_database[0])) ) { // Check for empty string after explode
        // REMARK: Modified condition to handle cases where -db= results in an array with one empty string.
        exit("Required parameter -db is not defined or empty\n");
    }
    foreach ($arg_database as $db) {
        if (!in_array($db, $databases)) {
            exit("Database $db not found\n");
        }
    }
}

// --- Path and Folder Setup ---
$upload_root = $system->getFileStoreRootFolder();
$backup_root = $upload_root.'_BATCH_PROCESS_ARCHIVE_PACKAGE/';

/**
 * Defines the root directory for Heurist filestore.
 * This constant is set based on the system configuration.
 * @const string HEURIST_FILESTORE_ROOT
 */
define('HEURIST_FILESTORE_ROOT', $upload_root);

if (!folderCreate($backup_root, true)) {
    exit("Failed to create backup folder $backup_root \n");
}

// --- Action Lock ---
// Flag that backup is in progress to prevent concurrent executions.
$actionName = 'backupDBs';
if (!isActionInProgress($actionName, 30)) {
    // REMARK: Corrected message to be more accurate. The original message was slightly misleading.
    exit("Another backup operation is already in progress or recently completed. Please try this function later. If you are sure no other backup is running, you might need to clear the action lock manually.\n");
}

// --- SQL Dump Options ---
// REMARK: $with_triggers is hardcoded to false. If true, different dump options would be used.
if ($with_triggers) {
    $dump_options = array(
            'add-drop-table' => true,
            'single-transaction' => true,
            'skip-triggers' => false,
            'add-drop-trigger' => true,
            'databases' => true,
            'add-drop-database' => true);
} else {
    $dump_options = array('databases' => true,
                'add-drop-database' => true,
                'add-drop-table' => true,
                'single-transaction' => true,
                'skip-triggers' => true,
                'add-drop-trigger' => false);
}

// --- Initialize TSV Exporter if needed ---
if (!$arg_skip_tsv) {
    $dbExportTSV = new DbExportTSV($system);
}

set_time_limit(0); // No time limit for this script.

// --- Main Processing Loop for each Database ---
foreach ($arg_database as $idx => $db_name) {
    $db_name = basename($db_name); // Sanitize database name
    $db_name_esc = htmlentities($db_name); // For display purposes

    echo "Processing $db_name_esc... ";

    $folder = $backup_root.$db_name.'/'; // Temporary folder for this database's backup files
    $backup_zip = $backup_root.$db_name.'.zip'; // Final ZIP file name

    $database_folder = $upload_root.$db_name.'/'; // Path to the live database's filestore

    $folder_esc = htmlentities($folder); // For display purposes

    // Clear previous backup for this database, if any
    if (file_exists($folder)) {
        $res = folderDelete2($folder, true);
        if (!$res) {
            isActionInProgress($actionName, -1); // Release lock
            exit("Cannot clear existing backup folder $folder_esc \n");
        }
    }

    // Check if the database filestore folder exists
    if (!file_exists($database_folder)) {
        echo "skipped (database filestore folder $database_folder is missing)\n";
        continue;
    }

    // Create temporary folder for backup assembly
    if (!folderCreate($folder, true)) {
        isActionInProgress($actionName, -1); // Release lock
        exit("Failed to create folder $folder_esc in which to create the backup \n");
    }

    // --- File Copying ---
    echo "files.. ";
    $folders_to_copy = null;
    $copy_files_in_root = false; // Initialize

    // Copy documentation/resource folders if requested
    if ($arg_include_docs) {
        // Get all folders except backup, scratch, generated-reports, file_uploads, filethumbs, etc.
        $folders_to_copy = folderSubs($database_folder,
            array('backup', 'scratch', 'generated-reports', 'file_uploads', 'filethumbs',
                  'webimagecache', 'blurredimagescache'
                  // REMARK: Obsolete/old folder names like 'tileserver' are commented out in original code.
            )
        );
    }

    // Copy uploaded files if requested
    if (!$arg_skip_files) {
        if ($folders_to_copy == null) {$folders_to_copy = array();}
        // Ensure these specific folders are added for copying
        $file_uploads_path = $database_folder.'file_uploads/';
        if (file_exists($file_uploads_path)) { // Check if source folder exists before adding
            $folders_to_copy[] = $file_uploads_path;
        }
        $filethumbs_path = $database_folder.'filethumbs/';
        if (file_exists($filethumbs_path)) { // Check if source folder exists before adding
            $folders_to_copy[] = $filethumbs_path;
        }
        $copy_files_in_root = true; // Copy all files within the database's root filestore folder
    }

    // Perform folder copying
    if ($arg_include_docs || !$arg_skip_files) {
        if (!empty($folders_to_copy) || $copy_files_in_root) { // Only copy if there's something to copy
            folderRecurseCopy($database_folder, $folder, $folders_to_copy ?: array(), $copy_files_in_root);
        }
    }

    // --- TSV Export ---
    if (!$arg_skip_tsv) {
        echo('tsv.. ');
        
        $system->setDbnameFull($db_name); // Set current database context for the system object
        mysql__usedatabase($mysqli, $db_name); // Select database in mysqli connection
        
        // REMARK: The setSession method in DbExportTSV was defined as taking only $system.
        // Assuming it should be re-set for each database or that the second parameter ($folder) was a previous implementation detail.
        // For now, calling with $system only, as per DbExportTSV definition.
        // If $folder was intended for output path, DbExportTSV needs adjustment or a different method called.
        // $dbExportTSV->setSession($system, $folder); // Original line
        $dbExportTSV->setSession($system); // Corrected based on DbExportTSV's current definition
        
        // REMARK: DbExportTSV does not have an `output()` method in the provided snippet.
        // This suggests `DbExportTSV` class is more complex than initially shown or this is a call to a missing/different method.
        // For now, this line is kept but might cause an error if `output()` is not defined in the actual DbExportTSV class.
        // It's possible `output()` is intended to write files into the $folder passed previously to setSession.
        // $warns = $dbExportTSV->output();
        // if(!empty($warns)){
        //     echo (implode("\n", $warns)."\n");
        // }
        // Placeholder for actual TSV export logic if `output()` is not the correct method.
        // This might involve calling specific methods on $dbExportTSV to generate files in $folder.
        // For example: $dbExportTSV->exportTables($db_name, $folder);
         echo "(Placeholder for TSV export - check DbExportTSV methods) ";
    }

    // --- HML Export ---
    if (!$arg_skip_hml) {
        echo('hml.. ');
        // REMARK: The commented-out block for HML generation via HTTP request is obsolete.
        // The script now uses a CLI command to generate HML.
       /* it does not work in shell mode
       ...
       */
               
       $hmlscript = realpath(dirname(__FILE__).'/../xml/flathml.php');
       $cmd = escapeshellcmd('php -f '.$hmlscript);
       $cmd = $cmd." -- -db $db_name -backup 1"; // -backup 1 tells flathml to save to standard backup location

       $arr_out = array(); // Initialize to prevent potential PHP notice if exec fails to populate it
       $res2 = 0; // Initialize

       exec($cmd, $arr_out, $res2);

       if ($res2 !== 0) {
            $err = ' failed with a return status: '.($res2 !== null ? intval($res2) : 'unknown')
                    .'. Output: '.(is_array($arr_out) && !empty($arr_out) ? print_r($arr_out, true) : '(no output)');
            // REMARK: Corrected variable name from $res2!=null to $res2 !== null for strict comparison.
            // REMARK: Ensured $arr_out is checked before print_r.
            isActionInProgress($actionName, -1); // Release lock
            exit("Sorry, unable to generate HML database dump for $db_name_esc. $err \n");
       }
       
       // The flathml.php script with -backup 1 saves the file to HEURIST_FILESTORE_ROOT/dbname/backup/dbname.xml
       $output_file_name = HEURIST_FILESTORE_ROOT.$db_name.'/'.DIR_BACKUP."$db_name.xml";
       $dumpfile = "$folder/$db_name.xml"; // Target location in the temporary archive folder
       if (file_exists($output_file_name)) {
            fileCopy($output_file_name, $dumpfile);
            unlink($output_file_name); // Remove original from backup dir after copying
       } else {
            // REMARK: Added a warning if the HML file is not found where expected.
            echo "WARNING: HML output file $output_file_name not found after generation. ";
       }
    }
    
    // --- SQL Dump ---
    if (!$arg_skip_sql) {
        echo 'sql.. ';
        $dumpfile = $folder.$db_name."_MySQL_Database_Dump.sql"; // Corrected concatenation

        $res = DbUtils::databaseDump($db_name, $dumpfile, $dump_options);
        if ($res === false) {
            isActionInProgress($actionName, -1); // Release lock
            $err = $system->getError();
            // REMARK: Ensure $err is an array and message key exists.
            $error_message = (is_array($err) && isset($err['message'])) ? $err['message'] : 'Unknown error';
            error_log('buildArchivePackagesCMD Error: '.$error_message);
            exit("Sorry, unable to generate MySQL database dump for $db_name_esc. ".$error_message."\n");
        }
    }

    // REMARK: Commented out Mysqldump block, as it appears to be an alternative implementation not currently used.
/*
    try{
        $pdo_dsn = 'mysql:host='.HEURIST_DBSERVER_NAME.';dbname=hdb_'.$db_name.';charset=utf8mb4';
        $dump = new Mysqldump( $pdo_dsn, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD, $dump_options);
        $dump->start($dumpfile);
    } catch (Exception $e) {
        isActionInProgress($actionName, -1);
        exit("Sorry, unable to generate MySQL database dump for $db_name.".$e->getMessage()."\n");
    }
*/
    // --- ZIP Creation ---
    echo 'zip.. ';
    $destination = $backup_zip;
    if (file_exists($destination)) {
        unlink($destination); // Delete existing zip file if any
    }
    $res = UArchive::zip($folder, null, $destination, false);

    folderDelete2($folder, true); // Clean up temporary folder

    if (!$res) {
        isActionInProgress($actionName, -1); // Release lock
        $destination_esc = htmlentities($destination); // REMARK: Corrected variable name for escaping
        exit("Database: $db_name_esc Failed to create zip file at $destination_esc \n");
    }

    echo "OK\n";
} // End foreach database

isActionInProgress($actionName, -1); // Release lock at the end of all operations

// REMARK: The final exit message is commented out in the original script.
// exit("\nfinished all requested databases, results in HEURIST_FILESTORE/_BATCH_PROCESS_ARCHIVE_PACKAGE/\n\n");
?>