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
// 15 2 * * * /usr/bin/php /path/bulkEmailCron.php /etc/heurist/templates/owner_notice_email.txt >> /var/log/bulk_email.log 2>&1


$template = $argv[1] ?? null;

$template = '/etc/heurist/templates/email_template.txt';

if (!$template) {
    fwrite(STDERR, "Usage: php bulkEmailCron.php /path/to/template.txt [--dry-run]\n");
    exit(2);
}

$dryRun = is_array($argv) && in_array("--dry-run", $argv, true);

//$dryRun = true;

$bulk = new bulkEmailSystem($system);
$rtn = $bulk->processCronJob($template, [
    "dry_run" => $dryRun,
    // "use_native" => true,
    // "add_gdpr" => true,
]);

if ($rtn !== 0) {
    fwrite(STDERR, "Bulk email failed: " . $bulk->getError() . "\n");
    exit(1);
}

echo "Bulk email finished OK\n";
exit(0);
