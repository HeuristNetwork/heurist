<?php
/**
* JobController.php - Background jobs HTTP adapter (/api/{db}/jobs)
*
*   POST /jobs {"type": "...", "params": {...}}  start; returns the queued job, then
*                                                the same PHP process runs it
*   GET  /jobs[?all=1][&type=..][&brief=1]       own jobs (managers: all=1 for every job), newest
*                                                first; type: only that job type; brief: without
*                                                the stored parameters (small answer for lists)
*   GET  /jobs/{id}                              state and progress
*   POST /jobs/{id}/cancel                       Stop (also KILL QUERY on its connections)
*   DELETE /jobs/{id}                            remove a finished job and its result files
*   GET  /jobs/{id}/result                       stored result content (e.g. preview HTML), or the
*                                                result file of the job (e.g. an export) as a
*                                                download; the file only for the job's owner
*
* The start response is sent and the connection closed before the job runs
* (fastcgi_finish_request when available, otherwise Content-Length +
* Connection: close). The client polls GET /jobs/{id}. The PHP session is closed
* before the job runs: a job holding the session lock would block every other
* request of the same browser (polling included) until it ends.
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
use Heurist\Jobs\JobRunner;
use Heurist\Runtime\ApiResponse;
use Heurist\Runtime\ErrorReporter;
use Heurist\Runtime\RuntimeContext;
use InvalidArgumentException;
use OutOfBoundsException;
use Throwable;

/** HTTP boundary for background jobs. */
final class JobController
{
    private JobRunner $runner;
    private RuntimeContext $runtime;
    private ApiResponse $response;
    private ErrorReporter $errors;
    /** @var bool Close the HTTP connection before running a started job. */
    private bool $detach;

    /**
     * @param bool|null $detach Close the connection before the job runs; default: not in CLI.
     */
    public function __construct(
        JobRunner $runner,
        RuntimeContext $runtime,
        ?ApiResponse $response = null,
        ?ErrorReporter $errors = null,
        ?bool $detach = null
    ) {
        $this->runner = $runner;
        $this->runtime = $runtime;
        $this->response = $response ?? new ApiResponse();
        $this->errors = $errors ?? new ErrorReporter();
        $this->detach = $detach ?? PHP_SAPI !== 'cli';
    }

    /**
     * Execute one jobs request.
     *
     * @param string $method HTTP method.
     * @param array<int,string> $segments Path segments after /jobs.
     * @param array $params Sanitized request parameters.
     * @param array|null $json Decoded JSON body (unsanitized: may hold a template body).
     */
    public function handleRequest(string $method, array $segments, array $params, ?array $json = null): void
    {
        $id = (string)($segments[0] ?? '');
        $action = (string)($segments[1] ?? '');
        $body = is_array($json) ? $json : array();

        try{
            if($id === ''){
                if($method === 'GET'){
                    $type = trim((string)($params['type'] ?? ''));
                    $this->send($this->runner->listJobs(
                        filter_var($params['all'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        $type === '' ? null : $type,
                        filter_var($params['brief'] ?? false, FILTER_VALIDATE_BOOLEAN)
                    ));
                }elseif($method === 'POST'){
                    $type = (string)($body['type'] ?? $params['type'] ?? '');
                    $jobParams = $body['params'] ?? array();
                    if(!is_array($jobParams)){
                        throw new InvalidArgumentException('JSON property "params" must be an object');
                    }
                    $job = $this->runner->start($type, $jobParams);
                    $this->sendAndRun($job);
                }else{
                    $this->methodNotAllowed('GET, POST');
                }
                return;
            }

            switch($action){
                case '':
                    if($method === 'DELETE'){
                        $this->send($this->runner->delete($id));
                        break;
                    }
                    if($method !== 'GET'){ $this->methodNotAllowed('GET, DELETE'); break; }
                    $this->send($this->runner->get($id));
                    break;
                case 'cancel':
                    if($method !== 'POST'){ $this->methodNotAllowed('POST'); break; }
                    $this->send($this->runner->cancel($id));
                    break;
                case 'result':
                    if($method !== 'GET'){ $this->methodNotAllowed('GET'); break; }
                    $file = $this->runner->resultFile($id);
                    if($file !== null){
                        $this->sendFile($file);
                        break;
                    }
                    $content = $this->runner->resultContent($id);
                    if($content === null){
                        throw new OutOfBoundsException('The job has no result content');
                    }
                    $this->response->sendHtml($content, 200, array('Cache-Control' => 'no-cache'));
                    break;
                default:
                    $this->response->sendError(404, 'not_found', 'Unknown job action: '.$action);
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

    /** Answer with the queued job, close the connection, then run the job. */
    private function sendAndRun(array $job): void
    {
        $body = json_encode(array('status' => 0, 'data' => $job), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        // legacy code may have reopened the session (SessionStore::get keeps it open); after the
        // response is sent it cannot be started again (headers sent)
        if(session_status() === PHP_SESSION_ACTIVE){ session_write_close(); }
        if(!$this->detach){
            print $body;
            $this->runJob((string)$job['id']);
            return;
        }

        ignore_user_abort(true);
        while(ob_get_level() > 0){ ob_end_clean(); }
        http_response_code(202);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Encoding: none'); // keep mod_deflate from buffering the response
        header('Content-Length: '.strlen($body));
        header('Connection: close');
        print $body;
        flush();
        if(function_exists('fastcgi_finish_request')){
            fastcgi_finish_request();
        }
        $this->runJob((string)$job['id']);
    }

    /** Run a started job; failures are kept in the job state, not sent. */
    private function runJob(string $id): void
    {
        try{
            $this->runner->run($id);
        }catch(Throwable $error){
            $this->errors->report($error, $this->runtime);
        }
    }

    /** Stream a result file as a download (no session lock, no output buffering). */
    private function sendFile(array $file): void
    {
        if($this->detach){
            // a large file must not be collected in output buffers (CLI tests keep theirs)
            while(ob_get_level() > 0){ ob_end_clean(); }
        }
        $name = str_replace(array('"', "\r", "\n"), '', $file['name']);
        header('Content-Type: '.$file['mime']);
        header('Content-Disposition: attachment; filename="'.$name."\"; filename*=UTF-8''".rawurlencode($file['name']));
        header('Content-Length: '.filesize($file['path']));
        header('Cache-Control: private, no-cache');
        header('X-Content-Type-Options: nosniff');
        readfile($file['path']);
    }

    private function send($data): void
    {
        $this->response->send(array('status' => 0, 'data' => $data));
    }

    private function methodNotAllowed(string $allow): void
    {
        header('Allow: '.$allow);
        $this->response->sendError(405, 'method_not_allowed', 'Method not allowed');
    }
}
