<?php
/**
* ReportController.php - Reports manager HTTP adapter (/api/{db}/reports)
*
*   GET    /reports[?scope=all|card|set]   list (card: single-record reports, public)
*   POST   /reports                        create {title, isCardView, body?, description?}
*   GET    /reports/generated[?prefix=]    files in generated-reports (name starts with prefix)
*   DELETE /reports/generated/{file}       delete a generated file
*   GET    /reports/schedules              all visible schedules with their last generated file
*   POST   /reports/setup                  install definitions, convert old schedules
*   POST   /reports/import                 multipart "import_template" (.gpl/.tpl)
*   GET    /reports/{ref}                  one report (ref = record id or file name)
*   PUT    /reports/{ref}                  change {title?, description?, isCardView?, file?}
*   DELETE /reports/{ref}[?keepFile=1]     delete record (+ unused file) or file
*   GET    /reports/{ref}/template         template body
*   PUT    /reports/{ref}/template         save {body}
*   POST   /reports/{ref}/register         create the record of an unregistered file
*   POST   /reports/{id}/schedules         add a schedule {title?, querySource, output?, format?, intervalMinutes?}
*   PUT    /reports/{id}/schedules/{sid}   change a schedule {title?, querySource?, suffix?, format?, intervalMinutes?}
*   DELETE /reports/{id}/schedules/{sid}   delete a schedule
*   GET    /reports/{ref}/export           download with concept codes (.gpl)
*   GET    /reports/{ref}/render?rec=N     HTML of one record (always allowed)
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

use DomainException;
use Heurist\Reports\ReportService;
use Heurist\Runtime\ApiResponse;
use Heurist\Runtime\ErrorReporter;
use Heurist\Runtime\RuntimeContext;
use InvalidArgumentException;
use OutOfBoundsException;
use Throwable;

/** HTTP boundary for the reports manager. */
final class ReportController
{
    /** Words used as the first path segment that are not report references. */
    private const COLLECTION_ACTIONS = array('generated', 'setup', 'import', 'schedules');

    private ReportService $service;
    private RuntimeContext $runtime;
    private ApiResponse $response;
    private ErrorReporter $errors;

    /** Initialise the controller from explicit modern dependencies. */
    public function __construct(
        ReportService $service,
        RuntimeContext $runtime,
        ?ApiResponse $response = null,
        ?ErrorReporter $errors = null
    ) {
        $this->service = $service;
        $this->runtime = $runtime;
        $this->response = $response ?? new ApiResponse();
        $this->errors = $errors ?? new ErrorReporter();
    }

    /**
     * Execute one reports request and write its HTTP response.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE).
     * @param array<int,string> $segments Path segments after /reports (raw, URL-encoded).
     * @param array $params Sanitized request parameters.
     * @param array|null $json Decoded JSON request body, unsanitized (template bodies).
     * @param array $files Uploaded files ($_FILES).
     */
    public function handleRequest(string $method, array $segments, array $params, ?array $json = null, array $files = array()): void
    {
        $body = is_array($json) ? $json : array();
        $first = isset($segments[0]) ? rawurldecode((string)$segments[0]) : '';
        $action = isset($segments[1]) ? (string)$segments[1] : '';

        try{
            if($first === ''){
                if($method === 'GET'){
                    $this->send($this->service->listReports((string)($params['scope'] ?? 'all')));
                }elseif($method === 'POST'){
                    $this->send($this->service->createReport(
                        (string)($body['title'] ?? $params['title'] ?? ''),
                        $this->flag($body['isCardView'] ?? $params['isCardView'] ?? false),
                        (string)($body['body'] ?? ''),
                        (string)($body['description'] ?? ''),
                        (string)($body['file'] ?? '')
                    ));
                }else{
                    $this->methodNotAllowed('GET, POST');
                }
                return;
            }

            if(in_array($first, self::COLLECTION_ACTIONS, true)){
                $this->collectionAction($first, $method, $params, $files, $segments);
                return;
            }

            switch($action){
                case '':
                    if($method === 'GET'){
                        $this->send($this->service->getReport($first));
                    }elseif($method === 'PUT'){
                        $this->send($this->service->updateReport($first, $body));
                    }elseif($method === 'DELETE'){
                        $this->send($this->service->deleteReport($first, $this->flag($params['keepFile'] ?? false)));
                    }else{
                        $this->methodNotAllowed('GET, PUT, DELETE');
                    }
                    break;

                case 'template':
                    if($method === 'GET'){
                        $this->send($this->service->readTemplate($first));
                    }elseif($method === 'PUT' || $method === 'POST'){
                        if(!array_key_exists('body', $body) || !is_string($body['body'])){
                            throw new InvalidArgumentException('JSON property "body" with the template text is required');
                        }
                        $this->send($this->service->saveTemplate($first, $body['body']));
                    }else{
                        $this->methodNotAllowed('GET, PUT');
                    }
                    break;

                case 'register':
                    if($method !== 'POST'){ $this->methodNotAllowed('POST'); break; }
                    $this->send($this->service->registerFile(
                        $first,
                        (string)($body['title'] ?? $params['title'] ?? ''),
                        $this->flag($body['isCardView'] ?? $params['isCardView'] ?? false),
                        (string)($body['description'] ?? '')
                    ));
                    break;

                case 'schedules':
                    $scheduleId = intval($segments[2] ?? 0);
                    if($method === 'POST' && $scheduleId === 0){
                        $this->send($this->service->createSchedule($first, array_merge($params, $body)));
                    }elseif($method === 'PUT' && $scheduleId > 0){
                        $this->send($this->service->updateSchedule($first, $scheduleId, array_merge($params, $body)));
                    }elseif($method === 'DELETE' && $scheduleId > 0){
                        $this->send($this->service->deleteSchedule($first, $scheduleId));
                    }else{
                        $this->methodNotAllowed('POST, PUT, DELETE');
                    }
                    break;

                case 'export':
                    if($method !== 'GET'){ $this->methodNotAllowed('GET'); break; }
                    $export = $this->service->exportTemplate($first);
                    header('Content-Type: text/plain; charset=utf-8');
                    header('Content-Disposition: attachment; filename="'.str_replace('"', '', $export['file']).'"');
                    header('Content-Length: '.strlen($export['body']));
                    print $export['body'];
                    break;

                case 'render':
                    if($method !== 'GET'){ $this->methodNotAllowed('GET'); break; }
                    $html = $this->service->renderRecord($first, intval($params['rec'] ?? 0));
                    $this->response->sendHtml($html, 200, array('Cache-Control' => 'no-cache'));
                    break;

                default:
                    $this->response->sendError(404, 'not_found', 'Unknown report action: '.$action);
            }
        }catch(InvalidArgumentException $error){
            $this->response->sendError(400, 'invalid_request', $error->getMessage());
        }catch(OutOfBoundsException $error){
            $this->response->sendError(404, 'not_found', $error->getMessage());
        }catch(DomainException $error){
            $this->response->sendError($this->runtime->userId > 0 ? 403 : 401, 'access_denied', $error->getMessage());
        }catch(Throwable $error){
            $this->errors->report($error, $this->runtime);
            $this->response->sendError(500, 'server_error', $error->getMessage());
        }
    }

    /** Handle /reports/generated, /reports/schedules, /reports/setup and /reports/import. */
    private function collectionAction(string $name, string $method, array $params, array $files, array $segments = array()): void
    {
        switch($name){
            case 'generated':
                $file = isset($segments[1]) ? rawurldecode((string)$segments[1]) : '';
                if($method === 'GET' && $file === ''){
                    $this->send($this->service->listGenerated((string)($params['prefix'] ?? '')));
                }elseif($method === 'DELETE' && $file !== ''){
                    $this->send($this->service->deleteGenerated($file));
                }else{
                    $this->methodNotAllowed('GET, DELETE');
                }
                return;
            case 'schedules':
                if($method !== 'GET'){ $this->methodNotAllowed('GET'); return; }
                $this->send($this->service->listSchedules());
                return;
            case 'setup':
                if($method !== 'POST'){ $this->methodNotAllowed('POST'); return; }
                $this->send($this->service->setup());
                return;
            case 'import':
                if($method !== 'POST'){ $this->methodNotAllowed('POST'); return; }
                $upload = $files['import_template'] ?? null;
                if(!is_array($upload)){
                    throw new InvalidArgumentException('Multipart field "import_template" is required');
                }
                $this->send($this->service->importTemplate($upload, $this->flag($params['isCardView'] ?? false)));
                return;
        }
    }

    /** Emit the standard success envelope. */
    private function send(array $data): void
    {
        $this->response->send(array('status' => 0, 'data' => $data));
    }

    private function methodNotAllowed(string $allow): void
    {
        header('Allow: '.$allow);
        $this->response->sendError(405, 'method_not_allowed', 'Method not allowed');
    }

    /** Interpret a boolean request value ("1", "true", "yes", true). */
    private function flag($value): bool
    {
        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
