<?php

/**
* assessFAIR.php - Calculates and persists an approximate FAIR score for Heurist databases
*
* @fileOverview For every database on this server (or a single named database), calculates an
*               approximate FAIR (Findable, Accessible, Interoperable, Reusable) score out of 10,
*               broken into the four components (each worth up to 2.5), and writes it to
*               FAIRscore.txt in the 'settings' subdirectory of the database's filestore directory.
*               See hserv/utilities/FairScore.php for the scoring logic and tunable weights.
*
*               This is a rough, indicative measure rather than a rigorous standards-based FAIR
*               assessment - intended to nudge database owners towards better metadata, more open
*               data and a properly configured website, not to be a definitive certification.
*
*               Intended to be run nightly from cron (after downloadDBMetadata.php, since metadata
*               quality and DOI feed into the Findable/Reusable components), e.g.:
*               php -f /var/www/html/heurist/admin/describe/assessFAIR.php
*
*               Can also be run for a single database for testing:
*               php -f assessFAIR.php -- -db mydatabase
*
* @project     Heurist academic knowledge management system
* @package Admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       7
*/

use hserv\utilities\FairScore;

// Include the shared CLI setup script
require_once dirname(__FILE__) . '/../../hserv/utilities/cli_bootstrap.php';

$cnt_done = 0;
$cnt_errors = 0;

print "Heurist FAIR score assessment - " . date(DATE_8601) . $eol . $eol;

foreach ($databases as $db_name) {
    $short_name = basename($db_name);
    list($database_name_full, $short_name) = mysql__get_names($short_name);

    if (mysql__check_dbname($short_name) !== null) {
        continue;
    }

    if (!hasTable($mysqli, 'sysIdentification', $database_name_full) || !hasTable($mysqli, 'Records', $database_name_full)) {
        // not a valid/complete Heurist database (e.g. mid-creation or broken) - skip silently
        continue;
    }

    try {
        $score = FairScore::computeForDatabase($mysqli, $database_name_full, $short_name, $upload_root);
    } catch (\Throwable $e) {
        print "Database '$short_name': ERROR calculating score - " . $e->getMessage() . $eol;
        $cnt_errors++;
        continue;
    }

    $filestore_dir = rtrim($upload_root, '/') . '/' . $short_name . '/';

    if (FairScore::writeScore($filestore_dir, $score)) {
        print "Database '$short_name': FAIR " . $score['TOTAL'] . "/10  "
            . "(F=" . $score['F'] . " A=" . $score['A'] . " I=" . $score['I'] . " R=" . $score['R'] . ")" . $eol;
        $cnt_done++;
    } else {
        print "Database '$short_name': ERROR writing FAIRscore.txt to $filestore_dir" . "settings/" . $eol;
        $cnt_errors++;
    }
}

print $eol . "Finished. Scored: $cnt_done   Errors: $cnt_errors" . $eol;
