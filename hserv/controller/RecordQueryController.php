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

namespace hserv\controller;

use hserv\records\search\query\QueryBuilder;
use hserv\records\search\QueryValidationException;
use hserv\records\search\RecordSearchService;
use hserv\records\search\SearchExecutionException;
use hserv\records\search\SearchRequest;
use hserv\records\search\SearchResult;
use hserv\records\search\UnsupportedQueryException;
use hserv\records\data\RecordDataService;
use hserv\records\data\RecordFieldSelector;
use hserv\records\search\ExpansionEngine;
use hserv\records\search\ExpansionRequest;

/** Shared request boundary for public and internal modern record searches. */
final class RecordQueryController
{
    /** @var \hserv\System */
    private $system;

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
     * @param \hserv\System $system Initialised Heurist system.
     * @param object|null $service Optional search service for integration tests.
     */
    public function __construct($system, $service = null, $dataService = null, $expansionEngine = null)
    {
        require_once dirname(__FILE__).'/../records/data/RecordFieldSelector.php';
        require_once dirname(__FILE__).'/../records/data/RecordDataService.php';
        require_once dirname(__FILE__).'/../records/search/ExpansionTypes.php';
        $this->system = $system;
        $this->builder = new QueryBuilder($system->getMysqli());
        $this->service = $service ?? new RecordSearchService($system, $this->builder);
        $this->dataService = $dataService ?? new RecordDataService($system);
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
        return new SearchRequest($normalized, array(
            'limit' => $params['limit'] ?? 1000,
            'offset' => $params['offset'] ?? 0,
            'rules' => $params['rules'] ?? null,
            'fields' => $params['fields'] ?? null,
            'detail' => $params['detail'] ?? null
        ));
    }

    /** Execute a request and return its stable DTO representation. */
    public function execute(array $params): array
    {
        $request = $this->buildRequest($params);
        $result = $this->service->search($request);
        if(!$result instanceof SearchResult){
            throw new SearchExecutionException('Record search service returned an invalid result');
        }
        if($request->detail === 'graph'){
            return $result->toArray();
        }
        $selection = $this->fieldSelector->parse($request->fields);
        if($request->detail === 'ids' && empty($selection['headers']) && empty($selection['details'])){
            return $result->toArray();
        }
        return $this->recordsResponse($result, $request, $selection, $params);
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
        $records = $this->dataService->loadRecords($result->ids, $selection['headers'], $native);
        $paths = array();
        $linkedByTraversal = array();
        foreach($linked as $field){ $linkedByTraversal[$field['traversal']][] = $field; }
        foreach($linkedByTraversal as $traversal=>$pathFields){
            $engine = $this->expansionEngine;
            if($engine === null){
                require_once dirname(__FILE__).'/../records/search/ExpansionEngine.php';
                $engine = new ExpansionEngine($this->system, $this->service);
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
                    $records, $field, $occurrences, $publicPathId
                );
            }
        }

        $meta = array(
            'database'=>$params['db'] ?? $this->databaseName(),
            'entity'=>'records',
            'fields'=>array(
                'headers'=>array_values(array_unique(array_merge(
                    array('rec_ID','rec_RecTypeID'), $selection['headers']
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
        if(method_exists($this->system, 'dbname')){ return (string)$this->system->dbname(); }
        if(method_exists($this->system, 'getDbName')){ return (string)$this->system->getDbName(); }
        return '';
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
            header(HEADER_CORS_POLICY);
            $this->system->setResponseHeader();
            http_response_code(200);
            print json_encode($response);
        }catch(QueryValidationException $e){
            $this->system->errorExitApi($e->getMessage(), HEURIST_INVALID_REQUEST, true, 400);
        }catch(UnsupportedQueryException $e){
            $this->system->errorExitApi($e->getMessage(), HEURIST_INVALID_REQUEST, true, 422);
        }catch(SearchExecutionException $e){
            $this->system->errorExitApi('Record query execution failed', HEURIST_ERROR, true, 500);
        }catch(\InvalidArgumentException $e){
            $this->system->errorExitApi($e->getMessage(), HEURIST_INVALID_REQUEST, true, 400);
        }catch(\Throwable $e){
            $this->system->errorExitApi('Record query execution failed', HEURIST_ERROR, true, 500);
        }
    }
}
