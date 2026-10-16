<?php
/**
* RecordEditController.php - Record write HTTP adapter (plan 14)
*
*   POST   /records/{id}            insert; id 0 = next id, negative = that id (import)
*   PUT    /records/{id}            update, the body replaces the whole record
*   PATCH  /records/{id}            update, only the fields in the body
*   DELETE /records/{id}[?force=1]  delete; 409 with the references unless force
*   POST   /records/{id}/duplicate  copy of the record
*   POST   /records/{id}/validate   check a record body without saving (id <= 0: insert)
*
* The body is the record object of GET /records/{id} (see srv/Records/Edit/_README.md).
* A save answers with the saved record in the GET /records/{id} envelope and
* meta.issues (warnings). Errors: 400 invalid_request, 401/403 access_denied,
* 404 not_found, 409 conflict|references, 415 unsupported_media_type,
* 422 validation (with errors[]).
*
* Cross-site requests: POST, PUT and PATCH need Content-Type application/json,
* DELETE must be a real DELETE (or POST with X-HTTP-Method: DELETE); an Origin
* header must match the host. The caller (api.php) passes the real HTTP verb,
* never the ?method= parameter.
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

use Heurist\Records\Edit\RecordDeleteService;
use Heurist\Records\Edit\RecordDuplicateService;
use Heurist\Records\Edit\RecordEditException;
use Heurist\Records\Edit\RecordInput;
use Heurist\Records\Edit\RecordSaveService;
use Heurist\Runtime\ApiResponse;
use Heurist\Runtime\ErrorReporter;
use Heurist\Runtime\RuntimeContext;
use Throwable;

/** HTTP boundary of record writes. */
final class RecordEditController
{
    private RecordSaveService $save;
    private RecordDeleteService $delete;
    private RecordDuplicateService $duplicate;
    private RecordQueryController $query;
    private RuntimeContext $runtime;
    private ApiResponse $response;
    private ErrorReporter $errors;

    public function __construct(
        RecordSaveService $save,
        RecordDeleteService $delete,
        RecordDuplicateService $duplicate,
        RecordQueryController $query,
        RuntimeContext $runtime,
        ?ApiResponse $response = null,
        ?ErrorReporter $errors = null
    ) {
        $this->save = $save;
        $this->delete = $delete;
        $this->duplicate = $duplicate;
        $this->query = $query;
        $this->runtime = $runtime;
        $this->response = $response ?? new ApiResponse();
        $this->errors = $errors ?? new ErrorReporter();
    }

    /**
     * Execute one record write and send its response.
     *
     * @param string $method Real HTTP verb: POST, PUT, PATCH or DELETE.
     * @param array<int,string> $segments Path segments after /records ({id}[, action]).
     * @param array $params Request parameters (force, db).
     * @param array|null $json Decoded JSON body (unsanitized).
     * @param array $request contentType, origin, host of the request (cross-site checks).
     */
    public function handleRequest(string $method, array $segments, array $params, ?array $json, array $request = array()): void
    {
        $this->send(function() use ($method, $segments, $params, $json, $request){
            return $this->execute($method, $segments, $params, $json, $request);
        });
    }

    /**
     * Execute one record write and return [status, response body] (tests call this).
     *
     * @return array{0:int,1:array}
     * @throws RecordEditException
     */
    public function execute(string $method, array $segments, array $params, ?array $json, array $request = array()): array
    {
        $method = strtoupper($method);
        $this->assertSameSite($method, $request);
        $id = (string)($segments[0] ?? '');
        if(!preg_match('/^-?\d+$/', $id)){
            throw RecordEditException::invalid('Record id is not defined');
        }
        $recordId = intval($id);
        $action = (string)($segments[1] ?? '');
        $force = $this->flag($params['force'] ?? false);

        if($action === 'duplicate'){
            $this->allow($method, array('POST'));
            $newId = $this->duplicate->duplicate($recordId);
            return array(201, $this->savedRecord($newId, array(), $params));
        }
        if($action === 'validate'){
            $this->allow($method, array('POST'));
            $result = $this->save->save($recordId > 0 ? 'PUT' : 'POST', $recordId, RecordInput::fromBody($json), true, true);
            return array(200, array('valid'=>true, 'issues'=>$result['issues']));
        }
        if($action !== ''){
            throw new RecordEditException(404, 'not_found', 'Unknown record action: '.$action);
        }
        if($method === 'DELETE'){
            return array(200, $this->delete->delete($recordId, $force));
        }
        $this->allow($method, array('POST', 'PUT', 'PATCH'));
        $result = $this->save->save($method, $recordId, RecordInput::fromBody($json), $force);
        return array($result['created'] ? 201 : 200, $this->savedRecord($result['recordId'], $result['issues'], $params));
    }

    /** The saved record as GET /records/{id} returns it, with meta.issues. */
    private function savedRecord(int $recordId, array $issues, array $params): array
    {
        $response = $this->query->executeItem(array(
            'db' => $params['db'] ?? $this->runtime->databaseName,
            'fields' => '_all'
        ), $recordId);
        unset($response['meta']['self']);
        $response['meta']['issues'] = array_values($issues);
        return $response;
    }

    /**
     * Refuse requests another site could send with the user's cookie: a JSON body is
     * required for POST/PUT/PATCH (an HTML form cannot send one) and the Origin must
     * be this host.
     *
     * @throws RecordEditException 403, 415
     */
    private function assertSameSite(string $method, array $request): void
    {
        $origin = trim((string)($request['origin'] ?? ''));
        if($origin !== '' && $origin !== 'null'){
            $originHost = strtolower((string)parse_url($origin, PHP_URL_HOST));
            $originPort = parse_url($origin, PHP_URL_PORT);
            $host = strtolower((string)($request['host'] ?? ''));
            $requestHost = strtolower((string)parse_url('http://'.$host, PHP_URL_HOST));
            $requestPort = parse_url('http://'.$host, PHP_URL_PORT);
            if($originHost === '' || $originHost !== $requestHost || ($originPort !== null && $requestPort !== null && $originPort !== $requestPort)){
                throw RecordEditException::denied('Requests from another site are not allowed');
            }
        }elseif($origin === 'null'){
            throw RecordEditException::denied('Requests from another site are not allowed');
        }
        if($method !== 'DELETE' && stripos((string)($request['contentType'] ?? ''), 'application/json') !== 0){
            throw new RecordEditException(415, 'unsupported_media_type', 'Content-Type must be application/json');
        }
    }

    /** @throws RecordEditException 405 */
    private function allow(string $method, array $allowed): void
    {
        if(!in_array($method, $allowed, true)){
            header('Allow: '.implode(', ', $allowed));
            throw new RecordEditException(405, 'method_not_allowed', 'Method not allowed');
        }
    }

    /** Run the request and write its JSON response or error. */
    private function send(callable $build): void
    {
        if(defined('HEADER_CORS_POLICY')){ header(HEADER_CORS_POLICY); }
        try{
            list($status, $body) = $build();
            $this->response->send($body, $status);
        }catch(RecordEditException $error){
            $body = array('status'=>$error->status(), 'error'=>$error->errorCode(), 'message'=>$error->getMessage());
            if(!empty($error->issues())){ $body['errors'] = $error->issues(); }
            $this->response->send($body + $error->extra(), $error->status());
        }catch(Throwable $error){
            $this->errors->report($error, $this->runtime);
            $this->response->sendError(500, 'server_error', 'The record could not be saved');
        }
    }

    /** Interpret a boolean request value ("1", "true", "yes", true). */
    private function flag($value): bool
    {
        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
