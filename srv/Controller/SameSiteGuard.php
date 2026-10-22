<?php
/**
* SameSiteGuard.php - Refuse writes another site could send with the user's cookie
*
* Shared by the write controllers of /files and /sys (plan 20, §10.4): the user
* must be logged in, the Origin (when the browser sends it) must be this host, and
* a body must be JSON.
*
* @project     Heurist academic knowledge management system
* @package     Controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Controller;

use Heurist\Records\Edit\RecordEditException;
use Heurist\Runtime\RuntimeContext;

/** Checks of a write request. */
final class SameSiteGuard
{
    /**
     * @param array $request origin, host, contentType of the request.
     * @param bool $json Whether the request must have a JSON body.
     * @param string $loginMessage The message for a guest.
     * @throws RecordEditException 401, 403, 415
     */
    public static function assert(RuntimeContext $runtime, array $request, bool $json, string $loginMessage): void
    {
        if($runtime->userId < 1){ throw new RecordEditException(401, 'unauthorized', $loginMessage); }
        $origin = trim((string)($request['origin'] ?? ''));
        if($origin === 'null'){ throw RecordEditException::denied('Requests from another site are not allowed'); }
        if($origin !== ''){
            $originHost = strtolower((string)parse_url($origin, PHP_URL_HOST));
            $originPort = parse_url($origin, PHP_URL_PORT);
            $host = strtolower((string)($request['host'] ?? ''));
            $requestHost = strtolower((string)parse_url('http://'.$host, PHP_URL_HOST));
            $requestPort = parse_url('http://'.$host, PHP_URL_PORT);
            if($originHost === '' || $originHost !== $requestHost
                || ($originPort !== null && $requestPort !== null && $originPort !== $requestPort)){
                throw RecordEditException::denied('Requests from another site are not allowed');
            }
        }
        if($json && stripos((string)($request['contentType'] ?? ''), 'application/json') !== 0){
            throw new RecordEditException(415, 'unsupported_media_type', 'Content-Type must be application/json');
        }
    }
}
