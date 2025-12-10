#!/usr/bin/env php
<?php
/**
 * refresh_association_members.php
 *
 * Replacement for refresh_association_members.sh
 *
 * - Run from cron (CLI only).
 * - Connects directly to the local MySQL server.
 * - Exports current Heurist Network association members as CSV.
 * - Writes to /var/www/html/HEURIST/association_members.txt atomically.
 *
 * Usage from cron (example):
 *   MAILTO="support@heuristnetwork.org"
 *   18 * * * * php -f /var/www/html/heurist/admin/utilities/refreshMembershipList.php
 *
 * On success:  silent (exit 0)
 * On failure:  message on STDERR, non-zero exit → cron mails you if MAILTO is set.
 */

declare(strict_types=1);


// -----------------------------------------------------------------------------
// Basic safety: CLI only
// -----------------------------------------------------------------------------
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/../../autoload.php';
use hserv\System;
use hserv\report\ReportExecute;

// -----------------------------------------------------------------------------
// Configuration
// -----------------------------------------------------------------------------

// Output file (same as in the old script)
const OUT_FOLDER = '/var/www/html/HEURIST/';
const OUT_FILE = 'association-members.txt';


function main(): int {
    // Set a restrictive umask similar to old script (umask 077)
    umask(0077);

    try {
        
        $db_name = 'Heurist_Contacts'; // Database names or paths??
        $system = new System();
        if (!$system->init($db_name, true, true)) {
            throw new Exception("Cannot establish connection to database $db_name\n");
        }
        
        $system->setCurrentUser([
            'ugr_ID'=>2, 
            'ugr_Groups'=>user_getWorkgroups( $system->getMysqli(), 2 )
        ]);
        

        $params = array(
            'template'=>'Members_as_CSV.tpl',
            'output'=>OUT_FILE,
            'mode'=>'txt',
            'void'=>1,
            'q'=>'svs:74',
            'publish'=>1
        );

        $repExec = new ReportExecute($system, $params);
        $repExec->execute();

//error_log(.'  '.$system->getSysDir('generated-reports').OUT_FILE.'  => '.OUT_FOLDER.OUT_FILE);        
        $generated = $system->getSysDir('generated-reports').OUT_FILE; 
        if(file_exists($generated)){
            
            $path = OUT_FOLDER.'association_members.txt';
            
            copy($generated, $path);
            chown($path, 'apache');     // owner → apache
            chgrp($path, 'heurist');    // group → heurist

            // Permissions: rw-r----- (owner rw, group r)
            chmod($path, 0640);

            
        }

        return 0;
    } catch (Throwable $e) {
        // On error: print a message to STDERR and non-zero exit → cron will mail
        $msg = sprintf(
            "ERROR in refreshMembershipList.php: %s (code %d)\n",
            $e->getMessage(),
            $e->getCode()
        );
        fwrite(STDERR, $msg);
        return 1;
    }
}

// -----------------------------------------------------------------------------
// Run
// -----------------------------------------------------------------------------
exit(
    main()
);
