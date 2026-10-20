<?php
/**
* FileController.php - /api/{db}/files: registered files as records
*
* Routes (plan 20, Phases 1-2):
*   GET|POST /files                     search; the parameters and response of /records
*   GET      /files/{id}                one file record (fields default to _all)
*   POST     /files/0                   register: {"upload": token} or rec_URL, with file fields
*   PUT|PATCH /files/{id}               change; with "upload" the content is replaced
*   DELETE   /files/{id}                delete (409 while records refer to it)
*   POST     /files/{id}/validate       the checks of a save, nothing written
*   POST     /files/upload              start a chunked upload {name, size, type} -> {token, chunkSize}
*   PUT      /files/upload/{token}      one chunk: raw body, Content-Range "bytes a-b/total"
*   GET      /files/upload/{token}      received bytes (resume)
*   DELETE   /files/upload/{token}      cancel
*   GET      /files/{key}/content       the file (range requests), by the obfuscated key
*   GET      /files/{key}/thumbnail     its thumbnail
* Content and thumbnails may be read by guests (viewable files); everything else
* needs a logged-in user. Writes must come from this site: the Origin is checked,
* JSON bodies are required and chunks need the X-Heurist-Client header (a custom
* header cannot be sent cross-site without a CORS preflight).
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

use Heurist\Files\FileContent;
use Heurist\Files\FileStoreInterface;
use Heurist\Files\FileWriteService;
use Heurist\Records\Edit\RecordEditException;
use Heurist\Records\Query\QueryValidationException;
use Heurist\Records\Query\SearchExecutionException;
use Heurist\Records\Query\SearchRequest;
use Heurist\Records\Query\UnsupportedQueryException;
use Heurist\Runtime\ApiResponse;
use Heurist\Runtime\ErrorReporter;
use Heurist\Runtime\RuntimeContext;

/** Writes the responses of the /files domain. */
final class FileController
{
    private FileStoreInterface $store;
    private ?FileWriteService $writer;
    private ?FileContent $content;
    private RuntimeContext $runtime;
    private ApiResponse $response;
    private ErrorReporter $errors;

    public function __construct(
        FileStoreInterface $store,
        RuntimeContext $runtime,
        ?FileWriteService $writer = null,
        ?FileContent $content = null,
        ?ApiResponse $response = null,
        ?ErrorReporter $errors = null
    ) {
        $this->store = $store;
        $this->runtime = $runtime;
        $this->writer = $writer;
        $this->content = $content;
        $this->response = $response ?? new ApiResponse();
        $this->errors = $errors ?? new ErrorReporter();
    }

    /**
     * Run one JSON request without writing output (tests).
     *
     * @param string $method Real HTTP verb.
     * @param array<int,string> $segments Path after /files.
     * @param array|null $json Decoded JSON body.
     * @param array $request contentType, origin, host, contentRange, client (X-Heurist-Client), body (raw chunk).
     * @return array{0:int,1:array} HTTP status and response body.
     * @throws RecordEditException|QueryValidationException|UnsupportedQueryException
     */
    public function execute(string $method, array $segments, array $params, ?array $json = null, array $request = array()): array
    {
        $method = strtoupper($method);
        $segments = array_values(array_filter($segments, static function($segment){ return $segment !== ''; }));
        $first = (string)($segments[0] ?? '');
        $second = (string)($segments[1] ?? '');

        if($first === ''){
            $this->allow($method, array('GET', 'POST'));
            $this->assertUser();
            return array(200, $this->store->search($params));
        }
        if($first === 'aggregate' && $second === ''){
            $this->allow($method, array('GET', 'POST'));
            $this->assertUser();
            $this->assertAggregateOp($params);
            return array(200, $this->store->aggregate($params));
        }
        if($first === 'upload'){
            return $this->upload($method, $segments, $json, $request);
        }
        if(!preg_match('/^\d+$/', $first)){
            throw new RecordEditException(404, 'not_found', 'Unknown files route');
        }
        $fileId = intval($first);
        if($second === 'validate'){
            $this->allow($method, array('POST'));
            $this->assertSameSite($method, $request, true);
            $result = $this->writer()->save($fileId > 0 ? 'PUT' : 'POST', $fileId, $json, true, true);
            return array(200, array('valid' => true, 'issues' => $result['issues']));
        }
        if($second === 'duplicate'){
            $this->allow($method, array());
        }
        if($second !== ''){
            throw new RecordEditException(404, 'not_found', 'Unknown files route');
        }
        if($method === 'GET'){
            $this->assertUser();
            return array(200, $this->store->item($params, $fileId));
        }
        $this->allow($method, array('GET', 'POST', 'PUT', 'PATCH', 'DELETE'));
        $this->assertSameSite($method, $request, $method !== 'DELETE');
        if($method === 'DELETE'){
            return array(200, $this->writer()->delete($fileId));
        }
        $result = $this->writer()->save($method, $fileId, $json, $this->flag($params['force'] ?? false));
        $body = $this->store->item(array('db' => $params['db'] ?? $this->runtime->databaseName, 'fields' => '_all'),
            $result['fileId']);
        unset($body['meta']['self']);
        $body['meta']['issues'] = $result['issues'];
        if($result['existing']){ $body['meta']['existing'] = true; }
        return array($result['created'] ? 201 : 200, $body);
    }

    /** Run one request and write its response (JSON, or the file content). */
    public function handleRequest(string $method, array $segments, array $params, ?array $json = null, array $request = array()): void
    {
        $segments = array_values(array_filter($segments, static function($segment){ return $segment !== ''; }));
        if(count($segments) === 2 && in_array($segments[1], array('content', 'thumbnail'), true)
            && !preg_match('/^\d+$/', (string)$segments[0])){
            $this->sendContent(strtoupper($method), (string)$segments[0], (string)$segments[1], $params, $request);
            return;
        }
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
        }catch(UnsupportedQueryException $e){
            $this->response->sendError(422, 'unsupported_query', $e->getMessage());
        }catch(\InvalidArgumentException $e){
            $this->response->sendError(400, 'invalid_request', $e->getMessage());
        }catch(SearchExecutionException $e){
            if($this->response->sendInterrupted($e)){ return; }
            $this->errors->report($e, $this->runtime);
            $this->response->sendError(500, 'server_error', 'File query execution failed');
        }catch(\Throwable $e){
            if($this->response->sendInterrupted($e)){ return; }
            $this->errors->report($e, $this->runtime);
            $this->response->sendError(500, 'server_error', 'The file request failed');
        }
    }

    /**
     * The op of /files/aggregate: count or rectypes, the same on both stores.
     *
     * @throws QueryValidationException When op is missing or unknown.
     * @throws UnsupportedQueryException For values, minmax and ranges.
     */
    private function assertAggregateOp(array $params): void
    {
        $op = strtolower(trim((string)($params['op'] ?? '')));
        if(!in_array($op, SearchRequest::AGGREGATE_OPS, true)){
            throw new QueryValidationException($op === '' ? 'op is required (count or rectypes)'
                : 'Unknown aggregate op: '.$op.' (count or rectypes)');
        }
        if($op !== 'count' && $op !== 'rectypes'){
            throw new UnsupportedQueryException('op='.$op.' is not supported by /files (count or rectypes)');
        }
    }

    /** Chunked upload routes. */
    private function upload(string $method, array $segments, ?array $json, array $request): array
    {
        $uploads = $this->writer()->uploads();
        $token = (string)($segments[1] ?? '');
        if(count($segments) > 2){ throw new RecordEditException(404, 'not_found', 'Unknown upload route'); }
        if($token === ''){
            $this->allow($method, array('POST'));
            $this->assertSameSite($method, $request, true);
            return array(201, $uploads->start(is_array($json) ? $json : array()));
        }
        if($method === 'GET'){ return array(200, $uploads->status($token)); }
        $this->allow($method, array('GET', 'PUT', 'DELETE'));
        $this->assertSameSite($method, $request, false);
        if($method === 'DELETE'){
            $uploads->cancel($token);
            return array(200, array('cancelled' => $token));
        }
        if(trim((string)($request['client'] ?? '')) === ''){
            throw RecordEditException::denied('The X-Heurist-Client header is required');
        }
        return array(200, $this->writer()->uploadChunk($token, (string)($request['contentRange'] ?? ''), (string)($request['body'] ?? '')));
    }

    private function sendContent(string $method, string $key, string $kind, array $params, array $request): void
    {
        if($method !== 'GET' && $method !== 'HEAD'){
            $this->response->sendError(405, 'method_not_allowed', 'Use GET');
            return;
        }
        try{
            if($this->content === null){ throw new \LogicException('File content is not configured'); }
            $plan = $this->content->plan($key, $kind, (string)($request['range'] ?? ''), $this->flag($params['download'] ?? false));
            if($method === 'HEAD'){ unset($plan['path']); }
            FileContent::send($plan);
        }catch(\Throwable $e){
            $this->errors->report($e, $this->runtime);
            $this->response->sendError(500, 'server_error', 'The file cannot be sent');
        }
    }

    private function writer(): FileWriteService
    {
        if($this->writer === null){ throw new RecordEditException(405, 'method_not_allowed', 'Files cannot be changed here'); }
        return $this->writer;
    }

    /** @throws RecordEditException 401 */
    private function assertUser(): void
    {
        if($this->runtime->userId < 1){ throw new RecordEditException(401, 'unauthorized', 'Log in to see the files'); }
    }

    /**
     * Refuse writes another site could send with the user's cookie (as RecordEditController):
     * the Origin must be this host; JSON bodies are required where $json.
     *
     * @throws RecordEditException 403, 415
     */
    private function assertSameSite(string $method, array $request, bool $json): void
    {
        $this->assertUser();
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
