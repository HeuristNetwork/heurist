<?php

/**
 * cli_bootstrap.php - Shared bootstrap setup for Heurist CLI database scripts
 */

// CLI-only validation
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("This script must be run from the command line.\n");
}

global $arg_no_action;
$single_db = null;
$eol = "\n";

if (@$argv) {
    $ARGV = [];
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
}

require_once dirname(__FILE__) . '/../../autoload.php';
require_once dirname(__FILE__) . '/UFile.php';

$system = new hserv\System();

if (!$system->init(null, false, false)) {
    exit("Cannot establish connection to sql server\n");
}

$mysqli = $system->getMysqli();
$upload_root = $system->getFileStoreRootFolder();

if (!defined('HEURIST_FILESTORE_ROOT')) {
    define('HEURIST_FILESTORE_ROOT', $upload_root);
}

$databases = $single_db ? [$single_db] : mysql__getdatabases4($mysqli, false);

if (!is_array($databases)) {
    exit("Unable to retrieve list of databases on this server\n");
}

set_time_limit(0);
