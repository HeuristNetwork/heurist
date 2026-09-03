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

use Heurist\Publication\PublicationService;
use hserv\utilities\USanitize;

/** HTTP/action layer for module publications. */
final class PublicationController
{
    /** @var \hserv\System */
    private $system;
    private array $reqParams;
    private PublicationService $service;

    public function __construct($system, ?array $params = null)
    {
        $this->reqParams = is_array($params) ? $params : USanitize::sanitizeInputArray();
        $this->system = $system;
        $this->service = new PublicationService($system);
    }

    public function handleRequest($action, $type = 'map'): void
    {
        try{
            $type = $this->service->validateType((string)$type);
            if($action === 'show'){ $this->showPublication($type); return; }
            switch($action){
                case 'get': $result = $this->getPublication($type); break;
                case 'save': $this->requireAuthenticatedUser(); $result = $this->savePublication($type); break;
                case 'delete': $this->requireAuthenticatedUser(); $result = $this->deletePublication($type); break;
                default: throw new \InvalidArgumentException('Invalid "action" parameter');
            }
            dataOutput(array('status'=>HEURIST_OK, 'data'=>$result));
        }catch(\InvalidArgumentException $e){
            $this->system->addError(HEURIST_INVALID_REQUEST, $e->getMessage()); dataOutput($this->system->getError());
        }catch(\Throwable $e){
            $this->system->addError(HEURIST_ACTION_BLOCKED, $e->getMessage()); dataOutput($this->system->getError());
        }
    }

    public function getPublication(string $type): array { return $this->service->get($this->getPublicationId(true), $type, true); }
    public function savePublication(string $type): array { return $this->service->save($this->getPayload(), $type, $this->getPublicationId(false)); }
    public function deletePublication(string $type): bool { return $this->service->delete($this->getPublicationId(true), $type); }

    public function showPublication(string $type): void
    {
        try{
            $document = $this->service->get($this->getPublicationId(true), $type, true);
            $bootstrapJson = json_encode($this->service->buildBootstrap($document),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            if($bootstrapJson === false){ throw new \RuntimeException('Publication bootstrap cannot be encoded as JSON'); }
            $moduleName = 'heurist-'.$type;
            $baseUrl = rtrim(HEURIST_BASE_URL, '/').'/';
            $assetBase = $baseUrl.'hclient/bundles/'.$moduleName.'/';
            header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-cache');
            echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
                .'<meta name="viewport" content="width=device-width,initial-scale=1">'
                .'<meta name="robots" content="noindex,nofollow">'
                .'<title>Heurist Academic Knowledge Management System</title>'
                .'<link rel="stylesheet" href="'.htmlspecialchars($assetBase.$moduleName.'-main.css', ENT_QUOTES, 'UTF-8').'">'
                .'<style>html,body,#'.$moduleName.'{width:100%;height:100%;margin:0;padding:0;overflow:hidden}</style>'
                .'</head><body><div id="'.$moduleName.'"></div><script>window.heuristModuleBootstrap='.$bootstrapJson.';</script>'
                .'<script type="module" src="'.htmlspecialchars($assetBase.$moduleName.'.js', ENT_QUOTES, 'UTF-8').'">'
                .'</script></body></html>';
        }catch(\InvalidArgumentException $e){
            $this->system->addError(HEURIST_INVALID_REQUEST, $e->getMessage()); dataOutput($this->system->getError());
        }catch(\Throwable $e){
            $this->system->addError(HEURIST_ACTION_BLOCKED, $e->getMessage()); dataOutput($this->system->getError());
        }
    }

    private function requireAuthenticatedUser(): void
    {
        if($this->system->getUserId() < 1 || $this->system->authSession()->verifyCredentials($this->system->dbname()) < 1){
            throw new \RuntimeException('Authentication is required');
        }
    }

    private function getPayload(): array
    {
        $value = $this->reqParams['data'] ?? null;
        if(is_string($value)){
            $value = json_decode($value, true);
            if(json_last_error() !== JSON_ERROR_NONE){ throw new \InvalidArgumentException('Invalid publication configuration JSON'); }
        }
        if(!is_array($value)){ throw new \InvalidArgumentException('Parameter "data" must contain a publication configuration object'); }
        return $value;
    }

    private function getPublicationId(bool $required): ?string
    {
        $id = trim((string)($this->reqParams['id'] ?? $this->reqParams['publication_id'] ?? ''));
        if($id === ''){
            if($required){ throw new \InvalidArgumentException('Publication id is required'); }
            return null;
        }
        $this->service->validateId($id); return $id;
    }
}
