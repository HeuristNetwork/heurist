<?php
/**
* ApiResponse.php - Standard JSON API response writer
*
* Emits successful results and consistent HTTP error envelopes for modern
* read-only controllers without depending on legacy System error handling.
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

use Heurist\Database\QueryTrace;
use Heurist\Database\StatementLimit;

/** Writes JSON at the outermost HTTP boundary. */
final class ApiResponse
{
    /** Emit one successful JSON value. */
    public function send(array $data, int $status = 200): void
    {
        if(QueryTrace::enabled() && !$this->isList($data)){
            // debug=1|2: timings of this request next to pagination/records
            $data['debug'] = QueryTrace::toArray();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        print json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** True for a JSON array (list) rather than an object. */
    private function isList(array $data): bool
    {
        return $data === array() || array_keys($data) === range(0, count($data) - 1);
    }

    /** Emit the standard modern API error envelope. */
    public function sendError(int $status, string $code, string $message): void
    {
        $this->send(array('status'=>$status, 'error'=>$code, 'message'=>$message), $status);
    }

    /**
     * Emit query_timeout / query_cancelled when the failure was the statement
     * time limit or KILL QUERY. Returns false for any other failure.
     */
    public function sendInterrupted(\Throwable $exception): bool
    {
        $kind = StatementLimit::classify($exception);
        if($kind === null){ return false; }
        if($kind === 'timeout'){
            $this->sendError(503, 'query_timeout',
                'The query took longer than '.StatementLimit::seconds().' seconds and was stopped');
        }else{
            $this->sendError(503, 'query_cancelled', 'The query was cancelled');
        }
        return true;
    }

    /** Emit an HTML document with optional response headers. */
    public function sendHtml(string $html, int $status = 200, array $headers = array()): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        foreach($headers as $name=>$value){
            header((string)$name.': '.(string)$value);
        }
        print $html;
    }
}
