<?php
/**
* TimeDataController.php - Read-only temporal record projection
*
* `/api/{db}/time` deliberately reuses the ordinary records response and adds
* only the timeline-specific `when` property. Search, fields and pagination
* therefore have exactly the same semantics as `/records`.
*
* @project     Heurist academic knowledge management system
* @package     Controller
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       7.0
*/

declare(strict_types=1);

namespace Heurist\Controller;

use Heurist\Records\Query\QueryValidationException;
use Heurist\Records\Query\SearchExecutionException;
use Heurist\Records\Query\UnsupportedQueryException;
use Heurist\Records\Time\TimeDataService;
use Heurist\Runtime\ApiResponse;
use Heurist\Runtime\ErrorReporter;
use Heurist\Runtime\RuntimeContext;

/** HTTP boundary for the modern record-to-timeline projection. */
final class TimeDataController
{
    private RecordQueryController $records;
    private TimeDataService $time;
    private ApiResponse $response;
    private ErrorReporter $errors;
    private RuntimeContext $runtime;

    public function __construct(
        RecordQueryController $records,
        TimeDataService $time,
        RuntimeContext $runtime,
        ?ApiResponse $response = null,
        ?ErrorReporter $errors = null
    ) {
        $this->records = $records;
        $this->time = $time;
        $this->runtime = $runtime;
        $this->response = $response ?? new ApiResponse();
        $this->errors = $errors ?? new ErrorReporter();
    }

    /** Execute `/records`, then append temporal spans without changing its envelope. */
    public function execute(array $params): array
    {
        $recordParams = $params;
        unset($recordParams['timefields'], $recordParams['timeFields']);
        // /time always returns records; summary/ids-only detail modes are not
        // part of this endpoint contract.
        unset($recordParams['detail']);

        $result = $this->records->execute($recordParams);
        $this->time->attachWhen($result, $params['timefields'] ?? $params['timeFields'] ?? null);
        return $result;
    }

    public function output(array $params): void
    {
        try{
            $result = $this->execute($params);
            if(defined('HEADER_CORS_POLICY')){ header(HEADER_CORS_POLICY); }
            $this->response->send($result);
        }catch(QueryValidationException $exception){
            $this->response->sendError(400, 'invalid_request', $exception->getMessage());
        }catch(UnsupportedQueryException $exception){
            $this->response->sendError(422, 'unsupported_query', $exception->getMessage());
        }catch(SearchExecutionException $exception){
            $this->errors->report($exception, $this->runtime);
            $this->response->sendError(500, 'server_error', 'Timeline query execution failed');
        }catch(\InvalidArgumentException $exception){
            $this->response->sendError(400, 'invalid_request', $exception->getMessage());
        }catch(\Throwable $exception){
            $this->errors->report($exception, $this->runtime);
            $this->response->sendError(500, 'server_error', 'Unable to produce timeline data');
        }
    }
}
