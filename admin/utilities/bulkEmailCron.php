<?php
/**
* bulkEmailCron.php - CLI wrapper script
*
* @fileOverview This file defines the `BulkEmailSystem` class, which encapsulates
*               the functionality for processing form data, generating user lists,
*               constructing, and sending emails to users across multiple Heurist databases.
*               It handles email templating with placeholder substitution, CSV export of
*               targeted users, and receipt generation. It supports sending emails via
*               PHP's native `mail()`, PHPMailer, or a specified mail relay.
*
* @project     Heurist academic knowledge management system
* @package Admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay <blmckay13@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       6.0
*/
// -----------------------------------------------------------------------------
// Basic safety: CLI only
// -----------------------------------------------------------------------------
if (php_sapi_name() !== 'cli') {
    //fwrite(STDERR, "This script must be run from the command line.\n");
    //exit(1);
}

require_once dirname(__FILE__).'/../../autoload.php';

require_once "bulkEmailSystem.php";

// Example
// 15 2 * * * /usr/bin/php /path/bulkEmailCron.php /srv/scripts/owner_notice_email.txt >> /var/log/bulk_email.log 2>&1


$template = $argv[1] ?? null;

//$template = '/srv/scripts/email_template_backup_your_db.txt';
//$template = 'c:/docs/email_template.txt';
//$template = '/var/www/html/HEURIST/email_template_backup_your_db.txt';

if (!$template) {
    $sMsg = "Usage: php -f bulkEmailCron.php /path/to/template.txt [--dry-run]\n";
    if(defined('STDERR')){
        fwrite(STDERR, $sMsg);    
    }else{
        echo $sMsg;
    }
    exit(2);
}

$dryRun = is_array($argv) && in_array("--dry-run", $argv, true);

//$dryRun = false;

$bulk = new bulkEmailSystem($system);
$rtn = $bulk->processCronJob($template, [
    "dry_run" => $dryRun,
    //"use_native" => true,
    // "add_gdpr" => true,
]);

if ($rtn !== 0) {
    $sMsg = "Bulk email failed: " . $bulk->getError() . "\n";
    if(defined('STDERR')){
        fwrite(STDERR, $sMsg);    
    }else{
        echo $sMsg;
    }
    
    exit(1);
}

echo "Bulk email finished OK\n";
exit(0);
