<?php
/**
* SystemQueryController.php - Modern system-record HTTP adapter
*
* Handles mapped filter/user collection and item reads through the shared
* Heurist query contract.
*
* @project     Heurist academic knowledge management system
* @package     Controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);
namespace Heurist\Controller;

use Heurist\Records\Query\QueryValidationException;
use Heurist\Records\Query\UnsupportedQueryException;
use Heurist\Runtime\ApiResponse;
use Heurist\Runtime\ErrorReporter;
use Heurist\Runtime\RuntimeContext;
use Heurist\System\Query\SystemQueryService;

/** Writes public-compatible responses for /api/{db}/sys. */
final class SystemQueryController
{
    private SystemQueryService $service;
    private RuntimeContext $runtime;
    private ApiResponse $response;
    private ErrorReporter $errors;

    public function __construct(
        SystemQueryService $service,
        RuntimeContext $runtime,
        ?ApiResponse $response = null,
        ?ErrorReporter $errors = null
    ) {
        $this->service = $service;
        $this->runtime = $runtime;
        $this->response = $response ?? new ApiResponse();
        $this->errors = $errors ?? new ErrorReporter();
    }

    /** Execute without writing output, primarily for integration tests. */
    public function execute(array $params, ?string $type = null, ?int $recordId = null)
    {
        return $this->service->execute($params, $type, $recordId);
    }

    /** Execute and emit one collection or item response. */
    public function output(array $params, ?string $type = null, ?int $recordId = null): void
    {
        try{
            $result = $this->execute($params, $type, $recordId);
            if($recordId !== null && $result === null){
                $this->response->sendError(404, 'not_found', 'System record is not available');
                return;
            }
            if(defined('HEADER_CORS_POLICY')){ header(HEADER_CORS_POLICY); }
            $this->response->send($result);
        }catch(QueryValidationException $error){
            $this->response->sendError(400, 'invalid_request', $error->getMessage());
        }catch(UnsupportedQueryException $error){
            $this->response->sendError(422, 'unsupported_query', $error->getMessage());
        }catch(\Throwable $error){
            $this->errors->report($error, $this->runtime);
            $this->response->sendError(500, 'server_error', 'System query execution failed');
        }
    }
}
