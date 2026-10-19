<?php
/**
* RecordQueryController.php - Modern record-query HTTP adapter
*
* Converts public or internal request parameters into SearchRequest, invokes
* RecordSearchService, and emits the stable records or IDs response, and the
* counts, values and bounds of /{domain}/aggregate. Query parsing, SQL generation, expansion execution, and
* record output do not belong in this class.
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

namespace Heurist\Controller;

use Heurist\Database\DatabaseInterface;
use Heurist\Database\QueryTrace;
use Heurist\Runtime\ApiResponse;
use Heurist\Runtime\ErrorReporter;
use Heurist\Runtime\RuntimeContext;
use Heurist\Records\Query\Compiler\QueryBuilder;
use Heurist\Records\Query\QueryValidationException;
use Heurist\Records\Query\RecordSearchService;
use Heurist\Records\Query\SearchExecutionException;
use Heurist\Records\Query\SearchRequest;
use Heurist\Records\Query\SearchResult;
use Heurist\Records\Query\QueryExecutor;
use Heurist\Records\Query\UnsupportedQueryException;
use Heurist\Records\Data\RecordDataService;
use Heurist\Records\Data\RecordFieldSelector;
use Heurist\Records\Data\RecordPageAssembler;
use Heurist\Records\Expansion\ExpansionEngine;
use Heurist\Records\Store\RecordRepository;
use Heurist\Records\Store\RecordSchema;

/** Shared request boundary for public and internal modern record searches. */
final class RecordQueryController
{
    private RuntimeContext $runtime;
    private ApiResponse $response;
    private ErrorReporter $errors;
    private QueryExecutor $executor;

    /** @var QueryBuilder */
    private $builder;

    /** @var RecordSearchService|object */
    private $service;

    /** @var RecordDataService|object */
    private $dataService;

    /** @var RecordFieldSelector */
    private $fieldSelector;

    /** @var ExpansionEngine|object|null */
    private $expansionEngine;

    /** Name of the API domain in meta.entity: records, or files for fileRecords (plan 20). */
    private string $entity;

    /**
     * @param DatabaseInterface $database Modern database adapter.
     * @param RuntimeContext $runtime Current database and user context.
     * @param object|null $service Optional search service for integration tests.
     * @param RecordSchema|null $schema Another record table pair (fileRecords/fileRecDetails); null = Records.
     * @param string $entity meta.entity of the responses.
     */
    public function __construct(
        DatabaseInterface $database,
        RuntimeContext $runtime,
        $service = null,
        $dataService = null,
        $expansionEngine = null,
        ?ApiResponse $response = null,
        ?ErrorReporter $errors = null,
        ?RecordSchema $schema = null,
        string $entity = 'records'
    )
    {
        $this->runtime = $runtime;
        $this->entity = $entity;
        $this->response = $response ?? new ApiResponse();
        $this->errors = $errors ?? new ErrorReporter();
        $records = $schema === null ? null : new RecordRepository($database, $schema);
        $this->executor = new QueryExecutor($database, $records);
        $this->builder = new QueryBuilder($database, $schema);
        $this->service = $service ?? new RecordSearchService($database, $runtime, $this->builder, $this->executor);
        $this->dataService = $dataService ?? new RecordDataService($database, $runtime, $records);
        $this->fieldSelector = new RecordFieldSelector();
        $this->expansionEngine = $expansionEngine;
    }

    /** Convert HTTP-style parameters to a validated modern search request. */
    public function buildRequest(array $params): SearchRequest
    {
        $query = $params['query'] ?? $params['q'] ?? null;
        if(($query === null || $query === '') && isset($params['ids'])){
            $query = array(array('ids'=>$params['ids']));
        }
        if($query === null || $query === ''){
            $query = array(array('_all'=>true));
        }

        $normalized = $this->builder->normalize($query);
        $options = array(
            'limit' => $params['limit'] ?? 1000,
            'offset' => $params['offset'] ?? 0,
            'fields' => $params['fields'] ?? null,
            'detail' => $params['detail'] ?? null,
            // total=0: no count query for a full page (pagination.total is then -1)
            'total' => $params['total'] ?? null,
            'resolveDetails' => $params['resolveDetails'] ?? false,
            'defs' => $params['defs'] ?? false,
            'filter' => $this->structuredParameter($params['filter'] ?? null)
        );
        if(strtolower(trim((string)($params['detail'] ?? ''))) === 'minmax'){
            // op=minmax (aggregate): bounds of one numeric or date field
            $options['field'] = $params['field'] ?? null;
            return new SearchRequest($normalized, $options);
        }
        if(strtolower(trim((string)($params['detail'] ?? ''))) === 'ranges'){
            // op=ranges (aggregate): record counts per range of one numeric or date field
            $options['field'] = $params['field'] ?? null;
            foreach(array('groupby','ranges','match') as $key){
                if(array_key_exists($key, $params)){ $options[$key] = $params[$key]; }
            }
            return new SearchRequest($normalized, $options);
        }
        if(strtolower(trim((string)($params['detail'] ?? ''))) === 'values'){
            // op=values (aggregate): field + text; "sort" orders the values (count|value), not records
            $options['field'] = $params['field'] ?? null;
            $options['text'] = $params['text'] ?? '';
            $options['valueSort'] = $params['sort'] ?? 'count';
            // the field is in linked records reached through these link predicates
            $via = $this->structuredParameter($params['via'] ?? null);
            if(is_array($via) && !empty($via)){ $options['via'] = $via; }
            return new SearchRequest($normalized, $options);
        }
        if(array_key_exists('sort', $params)){
            $options['sort'] = $this->structuredParameter($params['sort']);
        }
        return new SearchRequest($normalized, $options);
    }

    /** Decode JSON query/filter values supplied through GET without changing text syntax. */
    private function structuredParameter($value)
    {
        if(!is_string($value)){ return $value; }
        $text = trim($value);
        if($text === '' || ($text[0] !== '[' && $text[0] !== '{')){ return $value; }
        $decoded = json_decode($text, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    /** Execute a request and return its stable DTO representation. */
    public function execute(array $params): array
    {
        $detail = strtolower(trim((string)($params['detail'] ?? '')));
        if(in_array($detail, SearchRequest::AGGREGATE_OPS, true)){
            // removed in plan 20 Phase 3: counts, values and bounds are /{domain}/aggregate
            throw new QueryValidationException(
                'detail='.$detail.' is not supported by /'.$this->entity.'; use /'.$this->entity.'/aggregate?op='.$detail
            );
        }
        $request = $this->buildRequest($params);
        if($request->detail === 'graph'){
            throw new QueryValidationException(
                'detail=graph is not supported by /records; use the /graph endpoint'
            );
        }
        $result = $this->service->search($request);
        if(!$result instanceof SearchResult){
            throw new SearchExecutionException('Record search service returned an invalid result');
        }
        $selection = $this->fieldSelector->parse($request->fields);
        if($request->detail === 'ids'){
            return $result->toArray();
        }
        return $this->recordsResponse($result, $request, $selection, $params);
    }

    /**
     * GET|POST /{domain}/aggregate?op=count|rectypes|values|minmax|ranges (plan 20 Phase 3):
     * the same query parameters as search, plus the parameters of the operation
     * (field, text, sort, via, limit, offset for values; field, groupby, ranges, match
     * for ranges; field for minmax).
     *
     * @return array count {query, total}; rectypes {query, total, rectypes};
     *         values {query, total, values}; ranges and minmax as their services give them.
     * @throws QueryValidationException When op is missing or unknown.
     */
    public function aggregate(array $params): array
    {
        $op = strtolower(trim((string)($params['op'] ?? '')));
        if(!in_array($op, SearchRequest::AGGREGATE_OPS, true)){
            throw new QueryValidationException(
                $op === '' ? 'op is required (count, rectypes, values, minmax or ranges)'
                    : 'Unknown aggregate op: '.$op.' (count, rectypes, values, minmax or ranges)'
            );
        }
        $params['detail'] = $op;
        $request = $this->buildRequest($params);
        $methods = array('values'=>'countValues', 'ranges'=>'valueBuckets', 'minmax'=>'valueRange');
        if(isset($methods[$op])){
            if(!method_exists($this->service, $methods[$op])){
                throw new UnsupportedQueryException('op='.$op.' is not supported by this search service');
            }
            return array_merge(
                array('query'=>$this->responseQuery($params, $request)),
                $this->service->{$methods[$op]}($request)
            );
        }
        $result = $this->service->search($request);
        if(!$result instanceof SearchResult){
            throw new SearchExecutionException('Record search service returned an invalid result');
        }
        $summary = array(
            'query'=>$this->responseQuery($params, $request),
            'total'=>$result->total
        );
        if($op === 'rectypes'){
            $summary['rectypes'] = $result->rectypes ?? array();
        }
        return $summary;
    }

    /** Preserve the caller's query representation in compact summary responses. */
    private function responseQuery(array $params, SearchRequest $request)
    {
        if(array_key_exists('query', $params)){ return $this->structuredParameter($params['query']); }
        if(array_key_exists('q', $params)){ return $this->structuredParameter($params['q']); }
        return $request->query;
    }

    /** Build the universal records envelope for native and linked fields. */
    private function recordsResponse(
        SearchResult $result,
        SearchRequest $request,
        array $selection,
        array $params
    ): array {
        $assembler = new RecordPageAssembler($this->dataService, function(){
            return $this->expansionEngine ?? new ExpansionEngine($this->executor, $this->service);
        });
        QueryTrace::begin('details');
        $page = $assembler->assemble($result->ids, $selection, array(
            'resolveDetails'=>$request->resolveDetails
        ));
        $records = $page['records'];
        $paths = $page['paths'];
        QueryTrace::end(count($records));

        $meta = array(
            'database'=>$params['db'] ?? $this->databaseName(),
            'entity'=>$this->entity,
            'fields'=>array(
                'headers'=>array_values(array_unique(array_merge(
                    array('rec_ID','rec_RecTypeID','rec_Title'),
                    $selection['headers'], $selection['virtuals'] ?? array()
                ))),
                'details'=>$assembler->fieldMetadata($selection, $records)
            )
        );
        if(!empty($paths)){ $meta['paths'] = $paths; }
        if($request->defs){
            $definitions = $assembler->definitions($records);
            $meta['recordTypes'] = (object)$definitions['recordTypes'];
            $meta['terms'] = (object)$definitions['terms'];
        }
        return array(
            'records'=>$records,
            'meta'=>$meta,
            'pagination'=>$this->pagination($result, $params)
        );
    }

    private function databaseName(): string
    {
        return $this->runtime->databaseName;
    }

    private function pagination(SearchResult $result, array $params): array
    {
        $pagination = array(
            'total'=>$result->total,
            'offset'=>$result->offset,
            'limit'=>$result->limit
        );
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if($uri !== ''){
            $pagination['self'] = $uri;
            if($result->offset+$result->limit < $result->total){
                $next = $params;
                $next['offset'] = $result->offset+$result->limit;
                $next['limit'] = $result->limit;
                unset($next['query'], $next['debug']);
                $pagination['next'] = strtok($uri, '?').'?'.http_build_query($next);
            }
        }
        return $pagination;
    }

    /**
     * One record: GET /api/{db}/records/{id}. The same record object and meta as
     * /records?ids={id}; fields default to _all (header fields and every detail).
     * Response {records:[record], meta} without pagination; a missing or hidden
     * record gives an empty records array.
     */
    public function executeItem(array $params, int $recordId): array
    {
        unset($params['query'], $params['q'], $params['rules'], $params['filter'], $params['sort']);
        $params['ids'] = (string)$recordId;
        $params['limit'] = 1;
        $params['offset'] = 0;
        $params['total'] = 0;
        if(!isset($params['fields']) || $params['fields'] === '' || $params['fields'] === array()){
            $params['fields'] = '_all';
        }
        $detail = strtolower(trim((string)($params['detail'] ?? '')));
        if($detail !== '' && $detail !== 'records' && $detail !== 'ids'){
            throw new QueryValidationException('detail='.$detail.' is not supported by /records/{id}');
        }
        $response = $this->execute($params);
        if($detail === 'ids'){
            return array(
                'records'=>array_map('strval', $response['ids'] ?? array()),
                'meta'=>array('database'=>$params['db'] ?? $this->databaseName(), 'entity'=>$this->entity)
            );
        }
        unset($response['pagination']);
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if($uri !== ''){ $response['meta']['self'] = $uri; }
        return $response;
    }

    /** Execute executeItem() and write the JSON response (errors as output()). */
    public function outputItem(array $params, int $recordId): void
    {
        $this->send(function() use ($params, $recordId){ return $this->executeItem($params, $recordId); });
    }

    /** Execute and write a public-compatible JSON response. */
    public function output(array $params): void
    {
        $this->send(function() use ($params){ return $this->execute($params); });
    }

    /** Execute aggregate() and write the JSON response (errors as output()). */
    public function outputAggregate(array $params): void
    {
        $this->send(function() use ($params){ return $this->aggregate($params); });
    }

    /** Run a response builder and write its JSON, or the error response. */
    private function send(callable $build): void
    {
        try{
            $response = $build();
            if(defined('HEADER_CORS_POLICY')){ header(HEADER_CORS_POLICY); }
            $this->response->send($response);
        }catch(QueryValidationException $e){
            $this->response->sendError(400, 'invalid_request', $e->getMessage());
        }catch(UnsupportedQueryException $e){
            $this->response->sendError(422, 'unsupported_query', $e->getMessage());
        }catch(SearchExecutionException $e){
            if($this->response->sendInterrupted($e)){ return; }
            $this->errors->report($e, $this->runtime);
            $this->response->sendError(500, 'server_error', 'Record query execution failed');
        }catch(\InvalidArgumentException $e){
            $this->response->sendError(400, 'invalid_request', $e->getMessage());
        }catch(\Throwable $e){
            if($this->response->sendInterrupted($e)){ return; }
            $this->errors->report($e, $this->runtime);
            $this->response->sendError(500, 'server_error', 'Record query execution failed');
        }
    }
}
