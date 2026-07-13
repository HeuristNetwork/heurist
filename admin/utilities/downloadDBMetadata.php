<?php
/**
* downloadDBMetadata.php - Nightly synchronisation of database metadata from the Heurist Reference Index
*
* @fileOverview For every database on this server that has been registered with the Heurist Reference
*               Index (sysIdentification.sys_dbRegisteredID > 0), this script:
*               1) downloads the flat HML/XML export of the registration record from the reference
*                  index (https://<reference index host>/heurist/export/xml/flathml.php?w=a&db=<index db>&q=ids:<regID>)
*               2) checks the downloaded content is valid XML before doing anything destructive
*               3) overwrites (or creates) DBMetadata.xml in the 'settings' subdirectory of the
*                  database's filestore directory (HEURIST_FILESTORE_DIR equivalent for each db)
*               4) emails the system administrator if a database's metadata could not be retrieved
*                  or was invalid, so the existing local copy is deliberately left untouched
*
*               The reference index database is considered the single source of truth for this
*               metadata, so this performs a simple overwrite rather than a diff/merge - the amount
*               of data involved is small and not worth the complexity of change detection.
*
*               Intended to be run nightly from cron, e.g.:
*               php -f /var/www/html/heurist/admin/utilities/downloadDBMetadata.php
*
*               Can also be run for a single database for testing:
*               php -f downloadDBMetadata.php -- -db mydatabase
*
* @project     Heurist academic knowledge management system
* @package Admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       7
*/

// CLI-only: this script performs bulk file writes and should not be callable from the web
if(php_sapi_name() !== 'cli'){
    http_response_code(403);
    exit("This script must be run from the command line.\n");
}

global $arg_no_action;

$is_shell = false;
$single_db = null;
$eol = "\n";

if (@$argv) {

    $is_shell = true;

    // handle command-line queries
    // e.g.  php -f downloadDBMetadata.php -- -db mydatabase
    $ARGV = array();
    for ($i = 0; $i < count($argv); ++$i) {
        if ($argv[$i][0] === '-') {
            if (@$argv[$i + 1] && $argv[$i + 1][0] != '-') {
                $ARGV[$argv[$i]] = $argv[$i + 1];
                ++$i;
            } else {
                $ARGV[$argv[$i]] = true;
            }
        } else {
            array_push($ARGV, $argv[$i]);
        }
    }

    if (@$ARGV['-db']) {
        $single_db = $ARGV['-db'];
    }
} else {
    // Artem: this script must only be called from the command line, not via a web request.
    http_response_code(403);
    header('Content-type: text/plain');
    exit("This script may only be run from the command line (e.g. php -f downloadDBMetadata.php).\n");
}

use hserv\utilities\USanitize;

require_once dirname(__FILE__) . '/../../autoload.php';

// not autoloaded classes - plain function libraries
require_once dirname(__FILE__) . '/../../hserv/utilities/UFile.php';
require_once dirname(__FILE__) . '/../../hserv/utilities/UMail.php';

$system = new hserv\System();

if (!$is_shell) {
    // web invocation must be password protected like other server-function scripts
    $sysadmin_pwd = USanitize::getAdminPwd();
    if (!$system->verifyActionPassword($sysadmin_pwd, $passwordForServerFunctions)) {
        include_once dirname(__FILE__) . '/../../hclient/framecontent/infoPage.php';
        exit;
    }
    header('Content-type: text/html; charset=utf-8');
}

if (!$system->init(null, false, false)) {
    exit("Cannot establish connection to sql server\n");
}

$mysqli = $system->getMysqli();

$upload_root = $system->getFileStoreRootFolder();
if (!defined('HEURIST_FILESTORE_ROOT')) {
    define('HEURIST_FILESTORE_ROOT', $upload_root);
}

$databases = $single_db ? array($single_db) : mysql__getdatabases4($mysqli, false);

if (!is_array($databases)) {
    exit("Unable to retrieve list of databases on this server\n");
}

set_time_limit(0);

$cnt_updated = 0;
$cnt_skipped = 0;
$cnt_errors  = 0;

print "Heurist database metadata synchronisation - " . date(DATE_8601) . $eol . $eol;

foreach ($databases as $db_name) {

    $short_name = basename($db_name); // sanitize - no path components allowed

    list($database_name_full, $short_name) = mysql__get_names($short_name);

    if (mysql__check_dbname($short_name) !== null) {
        continue; //not a valid heurist database name
    }

    $regID = mysql__select_value(
        $mysqli,
        'SELECT sys_dbRegisteredID FROM `' . $database_name_full . '`.sysIdentification LIMIT 1'
    );

    if (!isPositiveInt($regID)) {
        // database is not registered with the Heurist Reference Index - nothing to do
        $cnt_skipped++;
        continue;
    }

    print "Database '$short_name' is registered (ID #$regID)$eol";

    $metadata_url = rtrim(HEURIST_INDEX_BASE_URL, '/') . '/'
        . 'export/xml/flathml.php?w=a&db=' . HEURIST_INDEX_DATABASE . '&q=ids:' . $regID;

    $xml_data = loadRemoteURLContentWithRange($metadata_url, null, true, 30);

    if (empty($xml_data)) {

        global $glb_curl_error;

        $err_msg = "Heurist was unable to download the registration metadata for database '$short_name' "
            . "(registration ID #$regID) from the Heurist Reference Index.\n\n"
            . "URL requested:\n$metadata_url\n\n"
            . "Transport error: " . ($glb_curl_error ?: 'none reported') . "\n\n"
            . "The locally cached DBMetadata.xml file (if any) for this database has been left unchanged.";

        print "  ERROR: download failed - " . ($glb_curl_error ?: 'no data returned') . $eol;
        sendEmailToAdmin('Heurist metadata sync failed for ' . $short_name, $err_msg, false);
        $cnt_errors++;
        continue;
    }

    // validate the downloaded content is well-formed XML before overwriting anything
    libxml_use_internal_errors(true);
    $parsed = simplexml_load_string($xml_data);
    $xml_errors = libxml_get_errors();
    libxml_clear_errors();

    if ($parsed === false) {

        $err_detail = '';
        foreach ($xml_errors as $xml_error) {
            $err_detail .= trim($xml_error->message) . " (line {$xml_error->line})\n";
        }

        $err_msg = "Heurist downloaded registration metadata for database '$short_name' "
            . "(registration ID #$regID) from the Heurist Reference Index, but the content "
            . "was not valid XML and has NOT been saved.\n\n"
            . "URL requested:\n$metadata_url\n\n"
            . "XML parser errors:\n" . ($err_detail ?: 'unknown parse error');

        print "  ERROR: downloaded content is not valid XML$eol";
        sendEmailToAdmin('Heurist metadata sync: invalid XML for ' . $short_name, $err_msg, false);
        $cnt_errors++;
        continue;
    }

    // settings subdirectory of this database's filestore directory
    $settings_dir = rtrim($upload_root, '/') . '/' . $short_name . '/settings/';

    if (!folderCreate($settings_dir, true)) {

        $err_msg = "Heurist could not create or write to the settings folder for database '$short_name':\n"
            . $settings_dir;

        print "  ERROR: cannot access settings folder $settings_dir$eol";
        sendEmailToAdmin('Heurist metadata sync: filestore error for ' . $short_name, $err_msg, false);
        $cnt_errors++;
        continue;
    }

    $target_file = $settings_dir . 'DBMetadata.xml';

    if (fileSave($xml_data, $target_file) > 0) {
        print "  OK - metadata written to $target_file$eol";
        $cnt_updated++;
    } else {
        $err_msg = "Heurist downloaded valid registration metadata for database '$short_name' "
            . "but was unable to write it to:\n$target_file";

        print "  ERROR: unable to write $target_file$eol";
        sendEmailToAdmin('Heurist metadata sync: write failure for ' . $short_name, $err_msg, false);
        $cnt_errors++;
    }
}

print $eol . "Finished. Updated: $cnt_updated   Not registered (skipped): $cnt_skipped   Errors: $cnt_errors" . $eol;
