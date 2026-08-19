<?php
/**
* MapPublishedController.php - Published map configuration controller
*
* Handles HTTP actions for persisted heurist-map configurations. Storage and
* payload filtering are delegated to MapPublishedService.
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

use hserv\System;
use hserv\utilities\USanitize;
use hserv\records\map\MapPublishedService;

/**
 * HTTP/action layer for published maps.
 */
class MapPublishedController
{
    /** @var System */
    private $system;

    /** @var array */
    private $req_params;

    /** @var MapPublishedService */
    private $service;

    public function __construct($system, $params = null)
    {
        $this->req_params = is_array($params)
            ? $params
            : USanitize::sanitizeInputArray();

        $this->system = $system;
        $this->service = new MapPublishedService($system);
    }

    /**
     * Supported actions: get, save, delete, show.
     */
    public function handleRequest($action): void
    {
        if($action === 'show'){
            $this->showMap();
            return;
        }

        try{
            switch($action){
                case 'get':
                    $result = $this->getMap();
                    break;

                case 'save':
                    $this->requireAuthenticatedUser();
                    $result = $this->saveMap();
                    break;

                case 'delete':
                    $this->requireAuthenticatedUser();
                    $result = $this->deleteMap();
                    break;

                default:
                    throw new \InvalidArgumentException('Invalid "action" parameter');
            }

            dataOutput(array('status'=>HEURIST_OK, 'data'=>$result));
        }catch(\InvalidArgumentException $e){
            $this->system->addError(HEURIST_INVALID_REQUEST, $e->getMessage());
            dataOutput($this->system->getError());
        }catch(\Throwable $e){
            $this->system->addError(HEURIST_ACTION_BLOCKED, $e->getMessage());
            dataOutput($this->system->getError());
        }
    }

    public function getMap(): array
    {
        return $this->service->get($this->getMapId(true), true);
    }

    public function saveMap(): array
    {
        $payload = $this->getPayload();
        $id = $this->getMapId(false);

        return $this->service->save($payload, $id);
    }

    public function deleteMap(): bool
    {
        return $this->service->delete($this->getMapId(true));
    }

    /**
     * Output the standalone published-map page.
     */
    public function showMap(): void
    {
        try{
            $document = $this->service->get($this->getMapId(true), true);
            $bootstrap = $this->service->buildBootstrap($document);

            $bootstrapJson = json_encode(
                $bootstrap,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
            );

            if($bootstrapJson === false){
                throw new \RuntimeException('Published map bootstrap cannot be encoded as JSON');
            }

            $baseUrl = rtrim(HEURIST_BASE_URL, '/').'/';
            $assetBase = $baseUrl.'hclient/bundles/heurist-map/';

            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-cache');

            echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
                .'<meta name="viewport" content="width=device-width,initial-scale=1">'
                .'<meta name="robots" content="noindex,nofollow">'
                .'<title>Heurist Map</title>'
                .'<link rel="stylesheet" href="'
                .htmlspecialchars($assetBase.'heurist-map-main.css', ENT_QUOTES, 'UTF-8').'">'
                .'<style>html,body,#heurist-map{width:100%;height:100%;margin:0;padding:0;overflow:hidden}</style>'
                .'</head><body><div id="heurist-map"></div><script>'
                .'window.heuristMapBootstrap='.$bootstrapJson.';'
                .'</script><script type="module" src="'
                .htmlspecialchars($assetBase.'heurist-map.js', ENT_QUOTES, 'UTF-8').'"></script>'
                .'</body></html>';
        }catch(\InvalidArgumentException $e){
            $this->system->addError(HEURIST_INVALID_REQUEST, $e->getMessage());
            dataOutput($this->system->getError());
        }catch(\Throwable $e){
            $this->system->addError(HEURIST_ACTION_BLOCKED, $e->getMessage());
            dataOutput($this->system->getError());
        }
    }

    private function requireAuthenticatedUser(): void
    {
        if(
            $this->system->getUserId() < 1
            || $this->system->authSession()->verifyCredentials($this->system->dbname()) < 1
        ){
            throw new \RuntimeException('Authentication is required');
        }
    }

    private function getPayload(): array
    {
        $value = $this->req_params['data'] ?? null;

        if(is_string($value)){
            $value = json_decode($value, true);
            if(json_last_error() !== JSON_ERROR_NONE){
                throw new \InvalidArgumentException('Invalid map configuration JSON');
            }
        }

        if(!is_array($value)){
            throw new \InvalidArgumentException(
                'Parameter "data" must contain a map configuration object'
            );
        }

        return $value;
    }

    private function getMapId(bool $required): ?string
    {
        $id = trim((string)(
            $this->req_params['id']
            ?? $this->req_params['map_id']
            ?? ''
        ));

        if($id === ''){
            if($required){
                throw new \InvalidArgumentException('Published map id is required');
            }
            return null;
        }

        $this->service->validateId($id);
        return $id;
    }
}
