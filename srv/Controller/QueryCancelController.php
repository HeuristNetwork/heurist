<?php
/**
* QueryCancelController.php - Stops a running query of the same user
*
* POST /api/{db}/records/cancel {"rid":"..."} runs KILL QUERY for the request
* registered under that id (see RequestRegistry). The cancelled request then
* answers with the query_cancelled error.
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

use Heurist\Database\DatabaseInterface;
use Heurist\Runtime\ApiResponse;
use Heurist\Runtime\RequestRegistry;
use Heurist\Runtime\RuntimeContext;

/** HTTP adapter for query cancellation. */
final class QueryCancelController
{
    private DatabaseInterface $database;
    private RuntimeContext $runtime;
    private ApiResponse $response;

    public function __construct(DatabaseInterface $database, RuntimeContext $runtime, ?ApiResponse $response = null)
    {
        $this->database = $database;
        $this->runtime = $runtime;
        $this->response = $response ?? new ApiResponse();
    }

    /** Cancel one request id or a list of them: {"rid":"a"} or {"rid":["a","b"]}. */
    public function output(array $params): void
    {
        $ids = $params['rid'] ?? array();
        if(!is_array($ids)){ $ids = explode(',', (string)$ids); }
        $ids = array_slice(array_values(array_filter(array_map('strval', $ids))), 0, 50);
        if(empty($ids)){
            $this->response->sendError(400, 'invalid_request', 'Request id (rid) is not defined');
            return;
        }
        $result = array();
        foreach($ids as $requestId){
            $result[$requestId] = RequestRegistry::cancel($this->database, $this->runtime, $requestId);
        }
        if(defined('HEADER_CORS_POLICY')){ header(HEADER_CORS_POLICY); }
        $this->response->send(array('cancel'=>$result));
    }
}
