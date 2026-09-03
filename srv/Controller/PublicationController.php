<?php
/**
* PublicationController.php - Shared module publication controller
*
* Handles HTTP actions for persisted map, data, timeline, graph and crosstabs
* publications. Storage is delegated to PublicationService.
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

use DomainException;
use Heurist\Publication\PublicationService;
use Heurist\Runtime\ApiResponse;
use Heurist\Runtime\ErrorReporter;
use Heurist\Runtime\RuntimeContext;
use InvalidArgumentException;
use OutOfBoundsException;
use Throwable;

/** HTTP boundary for module publications. */
final class PublicationController
{
    private PublicationService $service;
    private RuntimeContext $runtime;
    private ApiResponse $response;
    private ErrorReporter $errors;

    /** Initialise the controller from explicit modern dependencies. */
    public function __construct(
        PublicationService $service,
        RuntimeContext $runtime,
        ?ApiResponse $response = null,
        ?ErrorReporter $errors = null
    ) {
        $this->service = $service;
        $this->runtime = $runtime;
        $this->response = $response ?? new ApiResponse();
        $this->errors = $errors ?? new ErrorReporter();
    }

    /** Execute one publication action and write its HTTP response. */
    public function handleRequest(string $action, string $type = 'map', array $params = array()): void
    {
        try{
            if($action === 'show'){
                $this->showPublication($params);
                return;
            }

            $type = $this->service->validateType($type);
            switch($action){
                case 'get':
                    $result = $this->getPublication($params);
                    break;
                case 'save':
                    $this->requireAuthenticatedUser();
                    $result = $this->savePublication($params, $type);
                    break;
                case 'delete':
                    $this->requireAuthenticatedUser();
                    $result = $this->deletePublication($params);
                    break;
                default:
                    throw new InvalidArgumentException('Invalid "action" parameter');
            }
            $this->response->send(array('status'=>0, 'data'=>$result));
        }catch(InvalidArgumentException $error){
            $this->response->sendError(400, 'invalid_request', $error->getMessage());
        }catch(OutOfBoundsException $error){
            $this->response->sendError(404, 'not_found', $error->getMessage());
        }catch(DomainException $error){
            $this->response->sendError(403, 'access_denied', $error->getMessage());
        }catch(Throwable $error){
            $this->errors->report($error, $this->runtime);
            $this->response->sendError(500, 'server_error', 'Publication request failed');
        }
    }

    /** Retrieve a publication for a JSON request. */
    public function getPublication(array $params): array
    {
        return $this->service->get($this->getPublicationId($params, true), true);
    }

    /** Persist a publication for the requested module type. */
    public function savePublication(array $params, string $type): array
    {
        return $this->service->save(
            $this->getPayload($params),
            $type,
            $this->getPublicationId($params, false)
        );
    }

    /** Delete one publication. */
    public function deletePublication(array $params): bool
    {
        return $this->service->delete($this->getPublicationId($params, true));
    }

    /** Render the standalone page using the type stored in the publication. */
    public function showPublication(array $params): void
    {
        $document = $this->service->get($this->getPublicationId($params, true), true);
        $type = $this->service->validateType((string)($document['type'] ?? ''));
        $bootstrapJson = json_encode(
            $this->service->buildBootstrap($document),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        if($bootstrapJson === false){
            throw new InvalidArgumentException('Publication bootstrap cannot be encoded as JSON');
        }

        $moduleName = 'heurist-'.$type;
        $assetBase = $this->runtime->baseUrl.'hclient/bundles/'.$moduleName.'/';
        $stylesheet = htmlspecialchars($assetBase.$moduleName.'-main.css', ENT_QUOTES, 'UTF-8');
        $javascript = htmlspecialchars($assetBase.$moduleName.'.js', ENT_QUOTES, 'UTF-8');
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<meta name="robots" content="noindex,nofollow">'
            .'<title>Heurist Academic Knowledge Management System</title>'
            .'<link rel="stylesheet" href="'.$stylesheet.'">'
            .'<style>html,body,#'.$moduleName
            .'{width:100%;height:100%;margin:0;padding:0;overflow:hidden}</style>'
            .'</head><body><div id="'.$moduleName.'"></div>'
            .'<script>window.heuristModuleBootstrap='.$bootstrapJson.';</script>'
            .'<script type="module" src="'.$javascript.'"></script></body></html>';
        $this->response->sendHtml($html, 200, array('Cache-Control'=>'no-cache'));
    }

    private function requireAuthenticatedUser(): void
    {
        if($this->runtime->userId < 1 || !$this->runtime->hasAccess){
            throw new DomainException('Authentication is required');
        }
    }

    private function getPayload(array $params): array
    {
        $value = $params['data'] ?? null;
        if(is_string($value)){
            $value = json_decode($value, true);
            if(json_last_error() !== JSON_ERROR_NONE){
                throw new InvalidArgumentException('Invalid publication configuration JSON');
            }
        }
        if(!is_array($value)){
            throw new InvalidArgumentException(
                'Parameter "data" must contain a publication configuration object'
            );
        }
        return $value;
    }

    private function getPublicationId(array $params, bool $required): ?string
    {
        $id = trim((string)($params['pub_id'] ?? ''));
        if($id === ''){
            if($required){
                throw new InvalidArgumentException('Publication id is required');
            }
            return null;
        }
        $this->service->validateId($id);
        return $id;
    }
}
