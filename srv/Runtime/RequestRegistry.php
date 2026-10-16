<?php
/**
* RequestRegistry.php - Running query registry for client-side cancellation
*
* A client sends a random request id in the X-Heurist-Request-Id header. While
* the request runs, the id is mapped to the MySQL connection id in a small temp
* file; POST /api/{db}/records/cancel {rid} reads it and runs KILL QUERY.
* Aborting fetch() in the browser alone leaves the query running on the server.
*
* @project     Heurist academic knowledge management system
* @package     Runtime
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Runtime;

use Heurist\Database\DatabaseInterface;
use Heurist\Database\MysqlDatabase;

/** Maps request ids of running requests to database connections. */
final class RequestRegistry
{
    /** Entries older than this are ignored (the request has surely ended). */
    private const MAX_AGE_SECONDS = 3600;

    /** Sanitised request id of the current HTTP request, or ''. */
    public static function currentRequestId(): string
    {
        return self::sanitize((string)($_SERVER['HTTP_X_HEURIST_REQUEST_ID'] ?? ''));
    }

    /** Keep only characters safe for a file name; ids are random client tokens. */
    public static function sanitize(string $requestId): string
    {
        return substr(preg_replace('/[^A-Za-z0-9_\-]/', '', $requestId), 0, 64);
    }

    /**
     * Register the current request when it carries a request id. The entry is
     * removed when the script ends.
     */
    public static function register(DatabaseInterface $database, RuntimeContext $runtime): void
    {
        $requestId = self::currentRequestId();
        if($requestId === '' || !$database instanceof MysqlDatabase){ return; }
        $file = self::file($runtime->databaseNameFull, $requestId);
        $entry = array(
            'connectionId'=>$database->connectionId(),
            'userId'=>$runtime->userId,
            'createdAt'=>time()
        );
        if(@file_put_contents($file, json_encode($entry), LOCK_EX) === false){ return; }
        register_shutdown_function(static function() use ($file): void {
            if(is_file($file)){ @unlink($file); }
        });
    }

    /**
     * Cancel the running query of a request started by the same user.
     *
     * @return string 'cancelled', 'not_found' (finished or unknown) or 'forbidden'
     */
    public static function cancel(DatabaseInterface $database, RuntimeContext $runtime, string $requestId): string
    {
        $requestId = self::sanitize($requestId);
        if($requestId === '' || !$database instanceof MysqlDatabase){ return 'not_found'; }
        $file = self::file($runtime->databaseNameFull, $requestId);
        $entry = is_file($file) ? json_decode((string)@file_get_contents($file), true) : null;
        if(!is_array($entry) || (time() - intval($entry['createdAt'] ?? 0)) > self::MAX_AGE_SECONDS){
            return 'not_found';
        }
        if(intval($entry['userId'] ?? -1) !== $runtime->userId){ return 'forbidden'; }
        @unlink($file);
        return $database->killQuery(intval($entry['connectionId'] ?? 0)) ? 'cancelled' : 'not_found';
    }

    private static function file(string $databaseNameFull, string $requestId): string
    {
        return rtrim(sys_get_temp_dir(), '/\\').DIRECTORY_SEPARATOR
            .'heurist_rq_'.md5($databaseNameFull).'_'.$requestId.'.json';
    }
}
