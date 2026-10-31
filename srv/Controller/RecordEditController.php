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
* Record-based entities (plan 27d, plan 28 §6; Records/Entity/RecordEntityRegistry):
*   GET    /{entity}/{id}                    the record (fields=_all) with meta.canEdit and the
*                                            entity's own meta (RecordEntityHandler::describe)
*   POST   /{entity}/0, PUT|PATCH|DELETE /{entity}/{id}   as /records/{id}; the record type
*                                            must be the entity's
*   GET    /{entity}/{id}/validate           problems of the stored record (entity check)
*   POST   /{entity}/{id}/validate|duplicate as /records/{id}
*   GET|POST|DELETE /{entity}/{id}/{action}, /{entity}/{action}   specific actions of the entity
* There is no search on an entity: GET /records?q=t:{record type} (plan 28 §9.7).
* The hooks of an entity's record type run for every write, also through /records/{id}.
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
use Heurist\Records\Edit\RecordPermissionService;
use Heurist\Records\Edit\RecordSaveService;
use Heurist\Records\Entity\RecordEntityHandler;
use Heurist\Records\Entity\RecordEntityRegistry;
use Heurist\Records\Store\RecordRepository;
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
    private ?RecordEntityRegistry $entities;
    private ?RecordRepository $records;
    private ?RecordPermissionService $permissions;
    private ApiResponse $response;
    private ErrorReporter $errors;

    /**
     * @param RecordEntityRegistry|null $entities Record-based entities (null: no hooks, no entity routes).
     * @param RecordRepository|null $records Record headers (record type of a write); needed with $entities.
     * @param RecordPermissionService|null $permissions meta.canEdit of entity reads; needed with $entities.
     */
    public function __construct(
        RecordSaveService $save,
        RecordDeleteService $delete,
        RecordDuplicateService $duplicate,
        RecordQueryController $query,
        RuntimeContext $runtime,
        ?RecordEntityRegistry $entities = null,
        ?RecordRepository $records = null,
        ?RecordPermissionService $permissions = null,
        ?ApiResponse $response = null,
        ?ErrorReporter $errors = null
    ) {
        $this->save = $save;
        $this->delete = $delete;
        $this->duplicate = $duplicate;
        $this->query = $query;
        $this->runtime = $runtime;
        $this->entities = $entities;
        $this->records = $records;
        $this->permissions = $permissions;
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
            $saveMethod = $recordId > 0 ? 'PUT' : 'POST';
            $handler = $this->handlerFor($saveMethod, $recordId, $json);
            $body = $handler !== null ? $handler->beforeSave($saveMethod, $recordId, $json ?? array(), $params, true) : $json;
            $result = $this->save->save($saveMethod, $recordId, RecordInput::fromBody($body), true, true);
            return array(200, array('valid'=>true, 'issues'=>$result['issues']));
        }
        if($action !== ''){
            throw new RecordEditException(404, 'not_found', 'Unknown record action: '.$action);
        }
        $handler = $this->handlerFor($method, $recordId, $json);
        if($method === 'DELETE'){
            $state = $handler !== null ? $handler->beforeDelete($recordId, $params) : array();
            $result = $this->delete->delete($recordId, $force);
            return array(200, $handler !== null ? $handler->afterDelete($recordId, $params, $state, $result) : $result);
        }
        $this->allow($method, array('POST', 'PUT', 'PATCH'));
        $body = $handler !== null ? $handler->beforeSave($method, $recordId, $json ?? array(), $params) : $json;
        try{
            $result = $this->save->save($method, $recordId, RecordInput::fromBody($body), $force);
        }catch(Throwable $exception){
            if($handler !== null){ $handler->saveFailed($method, $body ?? array()); }
            throw $exception;
        }
        $issues = $result['issues'];
        if($handler !== null){
            $issues = array_merge($issues, $handler->afterSave($method, $result['recordId'], $body ?? array(), $params));
        }
        return array($result['created'] ? 201 : 200, $this->savedRecord($result['recordId'], $issues, $params));
    }

    /**
     * Handle /api/{db}/{entity}/... of a record-based entity and send its response.
     *
     * @param string $entity Entity name (RecordEntityRegistry::ENTITIES).
     * @param string $method Real HTTP verb.
     * @param array<int,string> $segments Path segments after the entity name.
     */
    public function handleEntityRequest(string $entity, string $method, array $segments, array $params, ?array $json,
        array $request = array()): void
    {
        $this->send(function() use ($entity, $method, $segments, $params, $json, $request){
            return $this->executeEntity($entity, $method, $segments, $params, $json, $request);
        });
    }

    /**
     * Execute one request of a record-based entity and return [status, response body] (tests call this).
     *
     * @return array{0:int,1:array}
     * @throws RecordEditException
     */
    public function executeEntity(string $entity, string $method, array $segments, array $params, ?array $json,
        array $request = array()): array
    {
        $method = strtoupper($method);
        $rectype = $this->entities !== null ? $this->entities->rectype($entity) : 0;
        if($rectype < 1){
            throw RecordEditException::notFound('Unknown entity "'.$entity.'", or its record type is not in this database');
        }
        $segments = array_values(array_filter(array_map('strval', $segments), 'strlen'));
        $first = (string)($segments[0] ?? '');
        if($first === ''){
            throw new RecordEditException(405, 'method_not_allowed',
                'Search with GET /records?q=t:'.$rectype.'; an entity URL needs a record id or an action');
        }
        if($method !== 'GET'){ $this->assertSameSite($method, $request); }
        $handler = $this->entities->handler($entity);
        if(!preg_match('/^-?\d+$/', $first)){
            // an action of the whole entity, e.g. /website/setup
            return array(200, $handler->action($first, $method, 0, $params, $json ?? array()));
        }
        $recordId = intval($first);
        $action = (string)($segments[1] ?? '');
        if($recordId > 0 && !($method === 'POST' && $action === '')){
            $this->assertRectype($recordId, $rectype, $entity);
        }

        if($recordId < 1 && $method !== 'POST'){
            throw RecordEditException::notFound('Record '.$recordId.' was not found');
        }
        if($action === '' && $method === 'GET'){
            return array(200, $this->entityRecord($handler, $recordId, $params));
        }
        if($action === 'validate' && $method === 'GET'){
            $issues = $handler->validate($recordId, $params);
            $errors = array_filter($issues, static function(array $issue): bool {
                return ($issue['level'] ?? $issue['severity'] ?? '') === 'error';
            });
            return array(200, array('valid'=>empty($errors), 'issues'=>array_values($issues)));
        }
        if($action === '' || $action === 'validate' || $action === 'duplicate'){
            if($method === 'POST' && $action !== 'duplicate'){
                $json = is_array($json) ? $json : array();
                $body = isset($json['records'][0]) && is_array($json['records'][0]) ? $json['records'][0] : $json;
                $type = intval($body['rec_RecTypeID'] ?? 0);
                if($type > 0 && $type !== $rectype){
                    throw RecordEditException::invalid('rec_RecTypeID '.$type.' is not the record type of "'.$entity.'"');
                }
                if(isset($json['records'][0]) && is_array($json['records'][0])){
                    $json['records'][0]['rec_RecTypeID'] = $rectype;
                }else{
                    $json['rec_RecTypeID'] = $rectype;
                }
            }
            return $this->execute($method, $segments, $params, $json, $request);
        }
        return array(200, $handler->action($action, $method, $recordId, $params, $json ?? array()));
    }

    /** GET /{entity}/{id}: the record with meta.canEdit and the entity's meta. */
    private function entityRecord(RecordEntityHandler $handler, int $recordId, array $params): array
    {
        $response = $this->query->executeItem(array(
            'db' => $params['db'] ?? $this->runtime->databaseName,
            'fields' => $params['fields'] ?? '_all'
        ), $recordId);
        if(empty($response['records'])){
            throw RecordEditException::notFound('Record '.$recordId.' was not found');
        }
        $owners = $this->permissions !== null && $this->runtime->userId > 0 ? $this->permissions->owners($recordId) : null;
        $response['meta']['canEdit'] = $owners !== null
            && $this->permissions->canEditOwned($owners['owner'], $owners['editGroups']);
        foreach($handler->describe($recordId) as $key => $value){ $response['meta'][$key] = $value; }
        return $response;
    }

    /** @throws RecordEditException 404 when the record does not exist or is of another type */
    private function assertRectype(int $recordId, int $rectype, string $entity): void
    {
        $header = $this->records !== null ? $this->records->header($recordId) : null;
        if($header === null || intval($header['rec_RecTypeID']) !== $rectype){
            throw RecordEditException::notFound('Record '.$recordId.' is not a '.$entity);
        }
    }

    /**
     * Handler of the record type of a write: of the body for an insert, of the stored record
     * otherwise (null: not an entity).
     */
    private function handlerFor(string $method, int $recordId, ?array $json): ?RecordEntityHandler
    {
        if($this->entities === null){ return null; }
        if($method === 'POST'){
            $body = is_array($json) && isset($json['records'][0]) && is_array($json['records'][0]) ? $json['records'][0] : $json;
            $rectype = intval($body['rec_RecTypeID'] ?? 0);
        }else{
            $header = $this->records !== null && $recordId > 0 ? $this->records->header($recordId) : null;
            $rectype = intval($header['rec_RecTypeID'] ?? 0);
        }
        return $this->entities->handlerOf($rectype);
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
