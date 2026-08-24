<?php
/**
* RecordQueryController.php - Modern record-query HTTP adapter
*
* Converts public or internal request parameters into SearchRequest, invokes
* RecordSearchService, and emits the stable IDs/count response. Query parsing,
* SQL generation, expansion, and record output do not belong in this class.
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

use hserv\records\search\QueryBuilder;
use hserv\records\search\QueryValidationException;
use hserv\records\search\RecordSearchService;
use hserv\records\search\SearchExecutionException;
use hserv\records\search\SearchRequest;
use hserv\records\search\SearchResult;
use hserv\records\search\UnsupportedQueryException;

/** Shared request boundary for public and internal modern record searches. */
final class RecordQueryController
{
    /** @var \hserv\System */
    private $system;

    /** @var QueryBuilder */
    private $builder;

    /** @var RecordSearchService|object */
    private $service;

    /**
     * @param \hserv\System $system Initialised Heurist system.
     * @param object|null $service Optional search service for integration tests.
     */
    public function __construct($system, $service = null)
    {
        $this->system = $system;
        $this->builder = new QueryBuilder($system->getMysqli());
        $this->service = $service ?? new RecordSearchService($system, $this->builder);
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

        $rules = $params['rules'] ?? null;
        if($rules !== null && $rules !== '' && $rules !== array()){
            throw new UnsupportedQueryException('rules execution will be introduced in Phase 5');
        }

        $normalized = $this->builder->normalize($query);
        return new SearchRequest($normalized, array(
            'limit' => $params['limit'] ?? 1000,
            'offset' => $params['offset'] ?? 0,
            'rules' => $rules,
            'fields' => $params['fields'] ?? null,
            'detail' => $params['detail'] ?? null
        ));
    }

    /** Execute a request and return its stable DTO representation. */
    public function execute(array $params): array
    {
        $result = $this->service->search($this->buildRequest($params));
        if(!$result instanceof SearchResult){
            throw new SearchExecutionException('Record search service returned an invalid result');
        }
        return $result->toArray();
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
