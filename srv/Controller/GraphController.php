<?php
/**
* GraphController.php - Modern graph-document HTTP adapter
*
* Converts public or internal request parameters into a GraphRequest, invokes
* GraphService, and emits the renderer-neutral graph document consumed by the
* heurist-graph client. Query parsing, edge discovery and expansion execution
* do not belong in this class.
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

declare(strict_types=1);

namespace Heurist\Controller;

use Heurist\Database\DatabaseInterface;
use Heurist\Runtime\ApiResponse;
use Heurist\Runtime\ErrorReporter;
use Heurist\Runtime\RuntimeContext;
use Heurist\Records\Graph\GraphRequest;
use Heurist\Records\Graph\GraphService;
use Heurist\Records\Query\Compiler\QueryBuilder;
use Heurist\Records\Query\QueryValidationException;
use Heurist\Records\Query\SearchExecutionException;
use Heurist\Records\Query\UnsupportedQueryException;

/** Shared request boundary for public and internal graph documents. */
final class GraphController
{
    private RuntimeContext $runtime;
    private ApiResponse $response;
    private ErrorReporter $errors;

    /** @var QueryBuilder */
    private $builder;

    /** @var GraphService|object */
    private $service;

    /**
     * @param DatabaseInterface $database Modern database adapter.
     * @param RuntimeContext $runtime Current database and user context.
     * @param object|null $service Optional graph service for integration tests.
     */
    public function __construct(
        DatabaseInterface $database,
        RuntimeContext $runtime,
        $service = null,
        ?ApiResponse $response = null,
        ?ErrorReporter $errors = null
    ) {
        $this->runtime = $runtime;
        $this->response = $response ?? new ApiResponse();
        $this->errors = $errors ?? new ErrorReporter();
        $this->builder = new QueryBuilder($database);
        $this->service = $service ?? new GraphService($database, $runtime, null, null, $this->builder);
    }

    /** Convert HTTP-style parameters to a validated graph request. */
    public function buildRequest(array $params): GraphRequest
    {
        $query = $params['query'] ?? $params['q'] ?? null;
        if(($query === null || $query === '') && isset($params['ids'])){
            $query = array(array('ids' => $params['ids']));
        }
        if($query === null || $query === ''){
            $query = array(array('_all' => true));
        }
        $normalized = $this->builder->normalize($query);

        $options = array(
            'limit' => $params['limit'] ?? 1000,
            'offset' => $params['offset'] ?? 0,
            'links' => $this->structuredParameter($params['links'] ?? null),
            'rule' => $this->structuredParameter($params['rule'] ?? null),
            'rules' => $this->structuredParameter($params['rules'] ?? null),
            'limits' => $this->structuredParameter($params['limits'] ?? null),
            'maxNodes' => $params['maxNodes'] ?? null,
            'maxEdges' => $params['maxEdges'] ?? null,
            'maxDepth' => $params['maxDepth'] ?? null,
            'displayQuery' => $this->responseQuery($params, $normalized),
        );
        return new GraphRequest($normalized, $options);
    }

    /** Decode JSON query/links/limits values supplied through GET. */
    private function structuredParameter($value)
    {
        if(!is_string($value)){ return $value; }
        $text = trim($value);
        if($text === '' || ($text[0] !== '[' && $text[0] !== '{')){ return $value; }
        $decoded = json_decode($text, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    /** Preserve the caller's query representation in the response. */
    private function responseQuery(array $params, array $normalized)
    {
        if(array_key_exists('query', $params)){ return $this->structuredParameter($params['query']); }
        if(array_key_exists('q', $params)){ return $this->structuredParameter($params['q']); }
        if(isset($params['ids'])){ return array('ids' => $params['ids']); }
        return $normalized;
    }

    /** Execute a request and return its stable DTO representation. */
    public function execute(array $params): array
    {
        return $this->service->build($this->buildRequest($params))->toArray();
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
            $this->response->sendError(500, 'server_error', 'Graph query execution failed');
        }catch(\InvalidArgumentException $e){
            $this->response->sendError(400, 'invalid_request', $e->getMessage());
        }catch(\Throwable $e){
            $this->errors->report($e, $this->runtime);
            $this->response->sendError(500, 'server_error', 'Graph query execution failed');
        }
    }
}
