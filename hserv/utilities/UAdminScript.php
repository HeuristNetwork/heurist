<?php

namespace hserv\utilities;

/**
* UAdminScript.php - Shared boilerplate for nightly/admin maintenance scripts that iterate
*                     over every database on the server (or a single named database).
*
* @fileOverview Several admin scripts (assessFAIR.php, downloadDBMetadata.php, ...) share an
*               identical pattern: they can be invoked either from cron (CLI, with an optional
*               "-db somedb" to target a single database) or, password-protected, from the web;
*               they then connect to the SQL server, work out the filestore root, and loop over
*               every valid Heurist database. This class factors out that common scaffolding so
*               individual scripts only need to contain their own per-database logic.
*
* @project     Heurist academic knowledge management system
* @package Admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       7
*/
class UAdminScript
{
    /**
    * Parse $argv (if running from CLI) into a simple -flag => value map, and work out
    * whether we're running from the shell, whether a single database was requested via
    * -db, and which end-of-line sequence to use for progress output.
    *
    * e.g.  php -f somescript.php -- -db mydatabase
    *
    * @param array|null $argv  the raw $argv superglobal, or null/false if not running from CLI
    * @return array{is_shell: bool, single_db: ?string, eol: string}
    */
    public static function parseArgs($argv)
    {
        $is_shell = false;
        $single_db = null;
        $eol = "\n";

        if ($argv) {

            $is_shell = true;

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
        } else {
            $eol = '<br>';
        }

        return ['is_shell' => $is_shell, 'single_db' => $single_db, 'eol' => $eol];
    }

    /**
    * When invoked from the web, enforce the same password protection as other server-function
    * scripts and set the response content type. No-op when running from the shell.
    *
    * Exits the request (via infoPage.php) if the password check fails.
    *
    * @param \hserv\System $system
    * @param bool $is_shell
    * @param string|null $passwordForServerFunctions
    * @return void
    */
    public static function requireWebPasswordIfNeeded($system, $is_shell, $passwordForServerFunctions)
    {
        if (!$is_shell) {
            $sysadmin_pwd = USanitize::getAdminPwd();
            if (!$system->verifyActionPassword($sysadmin_pwd, $passwordForServerFunctions)) {
                include_once dirname(__FILE__) . '/../../hclient/framecontent/infoPage.php';
                exit;
            }
            header('Content-type: text/html; charset=utf-8');
        }
    }

    /**
    * Work out the filestore root for this server, defining HEURIST_FILESTORE_ROOT if it
    * isn't already defined elsewhere.
    *
    * @param \hserv\System $system  an already-initialised System instance
    * @return string upload_root
    */
    public static function initFilestoreRoot($system)
    {
        $upload_root = $system->getFileStoreRootFolder();
        if (!defined('HEURIST_FILESTORE_ROOT')) {
            define('HEURIST_FILESTORE_ROOT', $upload_root);
        }
        return $upload_root;
    }

    /**
    * Get the list of databases to process - either just the single requested database, or
    * every database on the server.
    *
    * @param \mysqli $mysqli
    * @param string|null $single_db
    * @return array|null|false  null/false if the database list could not be retrieved
    */
    public static function getDatabaseList($mysqli, $single_db)
    {
        return $single_db ? [$single_db] : mysql__getdatabases4($mysqli, false);
    }

    /**
    * Sanitise/validate a raw database name taken from the database list and split it into
    * its full and short forms.
    *
    * @param string $db_name
    * @return array{0: string, 1: string}|null  array($database_name_full, $short_name), or null if invalid
    */
    public static function resolveDbName($db_name)
    {
        $short_name = basename($db_name); // sanitize - no path components allowed
        list($database_name_full, $short_name) = mysql__get_names($short_name);

        if (mysql__check_dbname($short_name) !== null) {
            return null; // not a valid heurist database name
        }

        return [$database_name_full, $short_name];
    }

    /**
    * Full bootstrap for a nightly/admin script: parse CLI args, enforce the web password
    * gate, connect to the SQL server, work out the filestore root and the list of databases
    * to process. This is everything both assessFAIR.php and downloadDBMetadata.php used to
    * duplicate line-for-line before their per-database logic began.
    *
    * Exits the script directly (with a message) if the SQL connection or database list
    * cannot be obtained, same as the two calling scripts always did.
    *
    * @param array|null $argv
    * @param string|null $passwordForServerFunctions
    * @return array{system: \hserv\System, mysqli: \mysqli, upload_root: string,
    *               databases: array, eol: string, is_shell: bool}
    */
    public static function bootstrap($argv, $passwordForServerFunctions)
    {
        $args = self::parseArgs($argv);
        $is_shell = $args['is_shell'];
        $single_db = $args['single_db'];
        $eol = $args['eol'];

        $system = new \hserv\System();

        self::requireWebPasswordIfNeeded($system, $is_shell, $passwordForServerFunctions);

        if (!$system->init(null, false, false)) {
            exit("Cannot establish connection to sql server\n");
        }

        $mysqli = $system->getMysqli();
        $upload_root = self::initFilestoreRoot($system);

        $databases = self::getDatabaseList($mysqli, $single_db);

        if (!is_array($databases)) {
            exit("Unable to retrieve list of databases on this server\n");
        }

        set_time_limit(0);

        return [
            'system' => $system,
            'mysqli' => $mysqli,
            'upload_root' => $upload_root,
            'databases' => $databases,
            'eol' => $eol,
            'is_shell' => $is_shell,
        ];
    }

    /**
    * Iterate over a raw database list, skipping invalid names, and invoke a callback with
    * the resolved ($database_name_full, $short_name) pair for each valid database. Factors
    * out the resolveDbName()+continue pattern that was repeated at the top of every script's
    * foreach loop.
    *
    * @param array $databases        raw database list, as returned by getDatabaseList()
    * @param callable $callback      function(string $database_name_full, string $short_name): void
    * @return void
    */
    public static function eachValidDatabase($databases, callable $callback)
    {
        foreach ($databases as $db_name) {
            $resolved = self::resolveDbName($db_name);
            if ($resolved === null) {
                continue;
            }
            list($database_name_full, $short_name) = $resolved;
            $callback($database_name_full, $short_name);
        }
    }
}
