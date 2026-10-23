<?php
/**
* SystemEditController.php - Writes of the /sys domain (plan 20, §10.4)
*
* /sys gives a mapped type in the record format and takes it back the same way
* (POST /sys/{type}/0, PUT / PATCH / DELETE /sys/{type}/{id},
* POST /sys/{type}/{id}/validate). Phase 4a maps tags, with the tag actions:
*
*   POST   /sys/tag/0                   add a tag (record JSON, as GET /sys/tag/{id} gives it)
*   PUT    /sys/tag/{id}                replace (fields not given are emptied)
*   PATCH  /sys/tag/{id}                change the given fields
*   DELETE /sys/tag/{id}[?force=1]      delete (409 tag_in_use while records have it, unless forced)
*   POST   /sys/tag/{id}/validate       the checks only
*   POST   /sys/tag/{id}/merge          {"into": id}: its records get the other tag, it is deleted
*   POST   /sys/tag/assign              {"records": [...], "add": [...], "remove": [...], "replace": false}
*
* Phase 4b maps bookmarks and reminders (plan 20, §10.9):
*
*   POST   /sys/bookmark/0, PUT / PATCH / DELETE /sys/bookmark/{id}, POST /sys/bookmark/{id}/validate
*   POST   /sys/reminder/0, PUT / PATCH / DELETE /sys/reminder/{id}, POST /sys/reminder/{id}/validate
*   POST   /sys/reminder/send           e-mail the reminder of the body now, without saving it
*
* The other types are read only for now (405). The reads stay SystemQueryController.
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
use Heurist\Records\Query\QueryValidationException;
use Heurist\Runtime\ApiResponse;
use Heurist\Runtime\ErrorReporter;
use Heurist\Runtime\RuntimeContext;
use Heurist\System\Query\SystemEntitySchemaRegistry;
use Heurist\System\Query\SystemQueryService;
use Heurist\System\Entity\EntityWriteService;

/** Write routes of /sys. */
final class SystemEditController
{
    private SystemQueryService $query;
    private RuntimeContext $runtime;
    /** @var array<string,EntityWriteService> Write service by type (tag, bookmark, reminder). */
    private array $services;
    private ApiResponse $response;
    private ErrorReporter $errors;

    public function __construct(
        SystemQueryService $query,
        RuntimeContext $runtime,
        array $services = array(),
        ?ApiResponse $response = null,
        ?ErrorReporter $errors = null
    ) {
        $this->query = $query;
        $this->runtime = $runtime;
        $this->services = $services;
        $this->response = $response ?? new ApiResponse();
        $this->errors = $errors ?? new ErrorReporter();
    }

    /**
     * Run one write request without writing output (tests).
     *
     * @param array<int,string> $segments Path after /sys: type, id or action, action.
     * @param array $request contentType, origin, host.
     * @return array{0:int,1:array} HTTP status and response body.
     * @throws RecordEditException|QueryValidationException
     */
    public function execute(string $method, array $segments, array $params, ?array $json = null, array $request = array()): array
    {
        $method = strtoupper($method);
        // every write needs a user: a guest is told so before anything else
        if($this->runtime->userId < 1){ throw new RecordEditException(401, 'unauthorized', 'Log in to change system records'); }
        $segments = array_values(array_filter($segments, static function($segment){ return $segment !== ''; }));
        $type = (new SystemEntitySchemaRegistry())->get((string)($segments[0] ?? ''))['type'];
        $second = (string)($segments[1] ?? '');
        $action = (string)($segments[2] ?? '');
        $service = $this->services[$type] ?? null;
        if($service === null){
            throw new RecordEditException(405, 'method_not_allowed', 'System records of type '.$type.' cannot be changed yet');
        }
        $force = $this->flag($params['force'] ?? false);
        $login = 'Log in to change system records';

        if($type === 'tag' && $second === 'assign' && $action === ''){
            $this->allow($method, array('POST'));
            SameSiteGuard::assert($this->runtime, $request, true, $login);
            return array(200, $service->assign($json));
        }
        if($type === 'reminder' && $second === 'send' && $action === ''){
            $this->allow($method, array('POST'));
            SameSiteGuard::assert($this->runtime, $request, true, $login);
            return array(200, $service->send($json));
        }
        if(!preg_match('/^\d+$/', $second)){ throw new RecordEditException(404, 'not_found', 'Unknown /sys route'); }
        $id = intval($second);
        if($action === 'validate'){
            $this->allow($method, array('POST'));
            SameSiteGuard::assert($this->runtime, $request, true, $login);
            $result = $service->save($id > 0 ? 'PUT' : 'POST', $id, $json, true, true);
            return array(200, array('valid' => true, 'issues' => $result['issues']));
        }
        if($type === 'tag' && $action === 'merge'){
            $this->allow($method, array('POST'));
            SameSiteGuard::assert($this->runtime, $request, true, $login);
            $result = $service->merge($id, $json);
            $body = $this->item($params, $type, $result['tagId']);
            $body['meta']['merged'] = $result['merged'];
            $body['meta']['moved'] = $result['moved'];
            return array(200, $body);
        }
        if($action !== ''){ throw new RecordEditException(404, 'not_found', 'Unknown /sys route'); }
        $this->allow($method, array('POST', 'PUT', 'PATCH', 'DELETE'));
        SameSiteGuard::assert($this->runtime, $request, $method !== 'DELETE', $login);
        if($method === 'DELETE'){ return array(200, $type === 'tag' ? $service->delete($id, $force) : $service->delete($id)); }
        $result = $service->save($method, $id, $json, $force);
        $body = $this->item($params, $type, $result[$type.'Id']);
        $body['meta']['issues'] = $result['issues'];
        return array($result['created'] ? 201 : 200, $body);
    }

    /** Run one request and write its response. */
    public function handleRequest(string $method, array $segments, array $params, ?array $json = null, array $request = array()): void
    {
        if(defined('HEADER_CORS_POLICY')){ header(HEADER_CORS_POLICY); }
        try{
            list($status, $body) = $this->execute($method, $segments, $params, $json, $request);
            $this->response->send($body, $status);
        }catch(RecordEditException $e){
            $body = array('status' => $e->status(), 'error' => $e->errorCode(), 'message' => $e->getMessage());
            if(!empty($e->issues())){ $body['errors'] = $e->issues(); }
            $this->response->send($body + $e->extra(), $e->status());
        }catch(QueryValidationException $e){
            $this->response->sendError(400, 'invalid_request', $e->getMessage());
        }catch(\InvalidArgumentException $e){
            $this->response->sendError(400, 'invalid_request', $e->getMessage());
        }catch(\Throwable $e){
            $this->errors->report($e, $this->runtime);
            $this->response->sendError(500, 'server_error', 'The system record request failed');
        }
    }

    /** The saved record as GET /sys/{type}/{id} gives it (all fields). */
    private function item(array $params, string $type, int $id): array
    {
        $body = $this->query->execute(array('db' => $params['db'] ?? $this->runtime->databaseName, 'fields' => '_all'), $type, $id);
        if(!is_array($body)){ throw RecordEditException::notFound(ucfirst($type).' '.$id.' not found'); }
        return $body;
    }

    /** @throws RecordEditException 405 */
    private function allow(string $method, array $allowed): void
    {
        if(!in_array($method, $allowed, true)){
            if(!headers_sent() && PHP_SAPI !== 'cli'){ header('Allow: '.implode(', ', $allowed)); }
            throw new RecordEditException(405, 'method_not_allowed', 'Method not allowed');
        }
    }

    private function flag($value): bool
    {
        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
