<?php
/**
* RecordQueryController.php - Modern record-query HTTP adapter
*
* Converts public or internal request parameters into SearchRequest, invokes
* RecordSearchService, and emits the stable IDs/count and optional expansion
* graph response. Query parsing, SQL generation, expansion execution, and
* record output do not belong in this class.
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

namespace Heurist\Controller;

use Heurist\Database\DatabaseInterface;
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
use Heurist\Records\Expansion\ExpansionEngine;
use Heurist\Records\Expansion\ExpansionRequest;

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

    /**
     * @param DatabaseInterface $database Modern database adapter.
     * @param RuntimeContext $runtime Current database and user context.
     * @param object|null $service Optional search service for integration tests.
     */
    public function __construct(
        DatabaseInterface $database,
        RuntimeContext $runtime,
        $service = null,
        $dataService = null,
        $expansionEngine = null,
        ?ApiResponse $response = null,
        ?ErrorReporter $errors = null
    )
    {
        $this->runtime = $runtime;
        $this->response = $response ?? new ApiResponse();
        $this->errors = $errors ?? new ErrorReporter();
        $this->executor = new QueryExecutor($database);
        $this->builder = new QueryBuilder($database);
        $this->service = $service ?? new RecordSearchService($database, $runtime, $this->builder, $this->executor);
        $this->dataService = $dataService ?? new RecordDataService($database, $runtime, $this->executor);
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
            'resolveDetails' => $params['resolveDetails'] ?? false,
            'filter' => $this->structuredParameter($params['filter'] ?? null)
        );
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
        if($request->detail === 'count' || $request->detail === 'rectypes'){
            $summary = array(
                'query'=>$this->responseQuery($params, $request),
                'total'=>$result->total
            );
            if($request->detail === 'rectypes'){
                $summary['rectypes'] = $result->rectypes ?? array();
            }
            return $summary;
        }
        $selection = $this->fieldSelector->parse($request->fields);
        if($request->detail === 'ids'){
            return $result->toArray();
        }
        return $this->recordsResponse($result, $request, $selection, $params);
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
        $native = array_values(array_filter($selection['details'], static function($field){
            return $field['traversal'] === null;
        }));
        $linked = array_values(array_filter($selection['details'], static function($field){
            return $field['traversal'] !== null;
        }));
        $valueOptions = array(
            'resolveDetails'=>$request->resolveDetails,
            // Presentation-only virtual headers are resolved by RecordDataService.
            'virtuals'=>$selection['virtuals'] ?? array()
        );
        $records = $this->dataService->loadRecords(
            $result->ids, $selection['headers'], $native, $valueOptions
        );
        $paths = array();
        $linkedByTraversal = array();
        foreach($linked as $field){ $linkedByTraversal[$field['traversal']][] = $field; }
        foreach($linkedByTraversal as $traversal=>$pathFields){
            $engine = $this->expansionEngine;
            if($engine === null){
                $engine = new ExpansionEngine($this->executor, $this->service);
            }
            $expansion = $engine->expand(new ExpansionRequest($result->ids, $traversal));
            $terminalPathId = null;
            foreach($expansion->getPaths() as $pathId=>$code){
                if($code === $traversal){ $terminalPathId = (string)$pathId; }
            }
            if($terminalPathId === null){ continue; }
            $publicPathId = (string)(count($paths)+1);
            $paths[$publicPathId] = $traversal;
            $occurrences = $expansion->getOccurrences($terminalPathId);
            foreach($pathFields as $field){
                $this->dataService->attachLinkedValues(
                    $records, $field, $occurrences, $publicPathId, $valueOptions
                );
            }
        }

        $meta = array(
            'database'=>$params['db'] ?? $this->databaseName(),
            'entity'=>'records',
            'fields'=>array(
                'headers'=>array_values(array_unique(array_merge(
                    array('rec_ID','rec_RecTypeID','rec_Title'),
                    $selection['headers'], $selection['virtuals'] ?? array()
                ))),
                'details'=>$this->dataService->loadFieldMetadata($selection['details'])
            )
        );
        if(!empty($paths)){ $meta['paths'] = $paths; }
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
                unset($next['query']);
                $pagination['next'] = strtok($uri, '?').'?'.http_build_query($next);
            }
        }
        return $pagination;
    }

    /** Execute and write a public-compatible JSON response. */
    public function output(array $params): void
    {
        try{
            $response = $this->execute($params);
            if(defined('HEADER_CORS_POLICY')){ header(HEADER_CORS_POLICY); }
            $this->response->send($response);
        }catch(QueryValidationException $e){
            $this->response->sendError(400, 'invalid_request', $e->getMessage());
        }catch(UnsupportedQueryException $e){
            $this->response->sendError(422, 'unsupported_query', $e->getMessage());
        }catch(SearchExecutionException $e){
            $this->errors->report($e, $this->runtime);
            $this->response->sendError(500, 'server_error', 'Record query execution failed');
        }catch(\InvalidArgumentException $e){
            $this->response->sendError(400, 'invalid_request', $e->getMessage());
        }catch(\Throwable $e){
            $this->errors->report($e, $this->runtime);
            $this->response->sendError(500, 'server_error', 'Record query execution failed');
        }
    }
}
