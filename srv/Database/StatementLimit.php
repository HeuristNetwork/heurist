<?php
/**
* StatementLimit.php - Server-side execution time limit for modern queries
*
* Sets a per-session statement time limit so a runaway query is stopped by
* the database server itself, even when the browser has already gone away.
* The limit is HEURIST_QUERY_TIME_LIMIT seconds (default 30, 0 = no limit);
* command-line scripts are not limited.
*
* @project     Heurist academic knowledge management system
* @package     Database
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Database;

use PDO;
use PDOException;

/** Applies and recognises the MySQL/MariaDB statement time limit. */
final class StatementLimit
{
    public const DEFAULT_SECONDS = 30;

    /** MariaDB: max_statement_time exceeded. */
    private const MARIADB_TIMEOUT = 1969;

    /** MySQL: max_execution_time exceeded. */
    private const MYSQL_TIMEOUT = 3024;

    /** Query interrupted by KILL QUERY. */
    private const INTERRUPTED = 1317;

    /** Configured limit in seconds; 0 means no limit. CLI scripts and tests are not limited. */
    public static function seconds(): int
    {
        if(PHP_SAPI === 'cli'){ return 0; }
        if(defined('HEURIST_QUERY_TIME_LIMIT') && is_numeric(HEURIST_QUERY_TIME_LIMIT)){
            return max(0, intval(HEURIST_QUERY_TIME_LIMIT));
        }
        return self::DEFAULT_SECONDS;
    }

    /** Set the limit for the session of the given MySQL/MariaDB connection. */
    public static function apply(PDO $pdo): void
    {
        $seconds = self::seconds();
        if($seconds <= 0){ return; }
        try{
            $version = (string)$pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            if(stripos($version, 'mariadb') !== false){
                $pdo->exec('SET SESSION max_statement_time='.$seconds);
            }else{
                // MySQL limits only read-only SELECT statements, in milliseconds.
                $pdo->exec('SET SESSION max_execution_time='.($seconds * 1000));
            }
        }catch(PDOException $exception){
            // Older servers without the variable keep working without a limit.
        }
    }

    /**
     * Classify a database failure: 'timeout', 'cancelled' or null.
     * Walks the exception chain to find the original PDO error.
     */
    public static function classify(?\Throwable $exception): ?string
    {
        while($exception !== null){
            if($exception instanceof PDOException){
                $code = intval($exception->errorInfo[1] ?? 0);
                if($code === self::MARIADB_TIMEOUT || $code === self::MYSQL_TIMEOUT){ return 'timeout'; }
                if($code === self::INTERRUPTED){ return 'cancelled'; }
                $message = $exception->getMessage();
                if(stripos($message, 'max_statement_time') !== false
                    || stripos($message, 'maximum statement execution time') !== false){
                    return 'timeout';
                }
            }
            $exception = $exception->getPrevious();
        }
        return null;
    }
}
