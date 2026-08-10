<?php
/**
* MapController.php - Published map configuration controller
*
* Saves, retrieves, deletes, and displays published Heurist map configurations.
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

/**
 * Handles persistence and standalone display of published map configurations.
 *
 * Published configurations are stored as JSON files in the database-specific
 * generated-maps system directory. Reading and standalone display are public;
 * save and delete require an authenticated user.
 */
class MapController
{
    /** @var System */
    private $system;

    /** @var array */
    private $req_params;

    /** @var string */
    private $dir;

    /**
     * Initialise the published-map controller.
     *
     * @param System $system Initialised Heurist system context.
     * @param array|null $params Sanitised request parameters.
     */
    public function __construct($system, $params = null)
    {
        $this->req_params = is_array($params) ? $params : USanitize::sanitizeInputArray();
        $this->system = $system;
        $this->dir = rtrim($this->system->getSysDir('generated-maps'), '/\\').DIRECTORY_SEPARATOR;
    }

    /**
     * Dispatch a published-map action.
     *
     * Supported actions: get, save, delete, show.
     *
     * @param string|null $action Requested action.
     * @return void
     */
    public function handleRequest($action): void
    {
        if($action === 'show'){
            $this->showMap();
            return;
        }

        $result = false;

        try {
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
                    throw new \Exception('Invalid "action" parameter');
            }
        } catch (\Throwable $e) {
            $this->system->addError(HEURIST_ACTION_BLOCKED, $e->getMessage());
            $result = false;
        }

        if(is_bool($result) && $result === false){
            dataOutput($this->system->getError());
        }else{
            dataOutput(['status' => HEURIST_OK, 'data' => $result]);
        }
    }

    /**
     * Return one published map configuration.
     *
     * @return array|false Published configuration or false on error.
     */
    public function getMap()
    {
        $id = $this->getMapId();
        if($id === null){
            return false;
        }

        return $this->readMapFile($id);
    }

    /**
     * Save a new published map or update an existing owned map.
     *
     * Request parameter `data` may be either an associative array or a JSON
     * string. When `id` is omitted a new cryptographically random identifier is
     * generated. Updating an existing identifier is restricted to its creator
     * or a database administrator.
     *
     * @return array|false Saved map identifier and standalone URL.
     */
    public function saveMap()
    {
        $payload = $this->getPayload();
        if($payload === null){
            return false;
        }

        $id = $this->getMapId(false);
        $existing = null;
        if($id !== null){
            $existing = $this->readMapFile($id, false);
            if($existing === false){
                return false;
            }
            if($existing !== null && !$this->canModify($existing)){
                $this->system->addError(HEURIST_REQUEST_DENIED, 'You do not have permission to update this published map');
                return false;
            }
        }else{
            $id = $this->createMapId();
        }

        $now = gmdate('c');
        $created = is_array($existing) && !empty($existing['created']) ? $existing['created'] : $now;
        $createdBy = is_array($existing) && isset($existing['createdBy'])
            ? intval($existing['createdBy'])
            : intval($this->system->getUserId());

        $document = [
            'format' => 'heurist-map-publish',
            'version' => 1,
            'created' => $created,
            'createdBy' => $createdBy,
            'modified' => $now,
            'options' => $payload['options'],
            'config' => $payload['config'],
            'state' => $payload['state']
        ];

        if(!is_dir($this->dir) && !mkdir($this->dir, 0775, true) && !is_dir($this->dir)){
            $this->system->addError(HEURIST_ERROR, 'Cannot create generated maps directory');
            return false;
        }

        $json = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if($json === false){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Published map configuration cannot be encoded as JSON');
            return false;
        }

        $file = $this->mapFileName($id);
        $tmp = $file.'.tmp-'.bin2hex(random_bytes(4));
        if(file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $file)){
            @unlink($tmp);
            $this->system->addError(HEURIST_ERROR, 'Cannot save published map configuration');
            return false;
        }

        return [
            'id' => $id,
            'url' => $this->buildShowUrl($id)
        ];
    }

    /**
     * Delete an owned published map configuration.
     *
     * @return bool True when deleted.
     */
    public function deleteMap(): bool
    {
        $id = $this->getMapId();
        if($id === null){
            return false;
        }

        $document = $this->readMapFile($id);
        if($document === false){
            return false;
        }
        if(!$this->canModify($document)){
            $this->system->addError(HEURIST_REQUEST_DENIED, 'You do not have permission to delete this published map');
            return false;
        }

        if(!@unlink($this->mapFileName($id))){
            $this->system->addError(HEURIST_ERROR, 'Cannot delete published map configuration');
            return false;
        }

        return true;
    }

    /**
     * Output a standalone page containing the heurist-map application.
     *
     * The complete published envelope is exposed as `window.heuristMapPublished`
     * for the map bootstrap. Runtime connection values are supplied separately
     * and are never taken from the stored user configuration.
     *
     * @return void
     */
    public function showMap(): void
    {
        $id = $this->getMapId();
        if($id === null){
            dataOutput($this->system->getError());
            return;
        }

        $document = $this->readMapFile($id);
        if($document === false){
            dataOutput($this->system->getError());
            return;
        }

        $publishedJson = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $baseUrl = rtrim(HEURIST_BASE_URL, '/').'/';
        $bootstrap = [
            'runtime' => [
                'database' => $this->system->dbname(),
                'baseUrl' => $baseUrl,
                'apiBaseUrl' => $baseUrl.'api'
            ],
            'settings' => [
                'format' => 'heurist-map-settings',
                'version' => 1,
                'options' => $document['options'] ?? [],
                'config' => $document['config'] ?? []
            ],
            'state' => $document['state'] ?? null
        ];
        $bootstrapJson = json_encode($bootstrap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $assetBase = $baseUrl.'external/heurist-map/';

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-cache');

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<meta name="robots" content="noindex,nofollow">'
            .'<title>Heurist Map</title>'
            .'<link rel="stylesheet" href="'.htmlspecialchars($assetBase.'heurist-map-main.css', ENT_QUOTES, 'UTF-8').'">'
            .'<style>html,body,#heurist-map{width:100%;height:100%;margin:0;padding:0;overflow:hidden}</style>'
            .'</head><body><div id="heurist-map"></div><script>'
            .'window.heuristMapBootstrap='.$bootstrapJson.';'
            .'</script><script type="module" src="'.htmlspecialchars($assetBase.'heurist-map.js', ENT_QUOTES, 'UTF-8').'"></script>'
            .'</body></html>';
    }

    /** Require a logged-in user for modifying actions. */
    private function requireAuthenticatedUser(): void
    {
        if($this->system->getUserId() < 1
            || $this->system->authSession()->verifyCredentials($this->system->dbname()) < 1){
            throw new \Exception('Authentication is required');
        }
    }

    /**
     * Decode and filter the published-map payload.
     *
     * @return array|null Normalised public-safe payload.
     */
    private function getPayload(): ?array
    {
        $value = $this->req_params['data'] ?? null;
        if(is_string($value)){
            $value = json_decode($value, true);
            if(json_last_error() !== JSON_ERROR_NONE){
                $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid map configuration JSON');
                return null;
            }
        }
        if(!is_array($value)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Parameter "data" must contain a map configuration object');
            return null;
        }

        return [
            'options' => $this->filterOptions(is_array($value['options'] ?? null) ? $value['options'] : []),
            'config' => $this->filterConfig(is_array($value['config'] ?? null) ? $value['config'] : []),
            'state' => is_array($value['state'] ?? null) ? $value['state'] : []
        ];
    }

    /** Filter persisted heuristMapOptions to the agreed user-editable schema. */
    private function filterOptions(array $value): array
    {
        return [
            'ui' => $this->pick($value['ui'] ?? [], [
                'enabled','placement','position','initiallyExpanded','showCurrentDocument',
                'showMapDocuments','showLayers','showBaseMaps','showLegend','showZoomControl',
                'showSearch','showPublish','controlCss'
            ]),
            'mapDocuments' => $this->pick($value['mapDocuments'] ?? [], ['allowed','initiallyActive']),
            'baseMaps' => $this->pick($value['baseMaps'] ?? [], ['allowed','initial']),
            'interaction' => $this->pick($value['interaction'] ?? [], ['selectionEnabled','popupEnabled','zoomOnSelection'])
        ];
    }

    /** Filter persisted heuristMapConfig to the agreed user-editable schema. */
    private function filterConfig(array $value): array
    {
        $dynamic = $this->pick($value['dynamicDocument'] ?? [], [
            'enabled','title','initiallyActive','minZoom','maxZoom','minimumZoomKm',
            'maximumZoomKm','zoomToPointInKM','bounds','symbology','selectSymbology',
            'preventContinuousWorldBasemap'
        ]);

        $layer = $this->pick($value['currentResultsLayer'] ?? [], ['title','visible','selectable','style']);
        $layer['options'] = $this->pick($value['currentResultsLayer']['options'] ?? [], [
            'markerClustering','maxAllowedFeatures','dynamicRequests','minZoom','maxZoom',
            'minimumZoomKm','maximumZoomKm','popupTemplate'
        ]);

        return [
            'dynamicDocument' => $dynamic,
            'currentResultsLayer' => $layer
        ];
    }

    /** Return only allowlisted keys from an associative array. */
    private function pick($value, array $allowed): array
    {
        if(!is_array($value)){
            return [];
        }
        return array_intersect_key($value, array_flip($allowed));
    }

    /**
     * Validate a published-map identifier from the request.
     *
     * @param bool $required Whether a missing id is an error.
     * @return string|null
     */
    private function getMapId(bool $required = true): ?string
    {
        $id = trim((string)($this->req_params['id'] ?? $this->req_params['map_id'] ?? ''));
        if($id === ''){
            if($required){
                $this->system->addError(HEURIST_INVALID_REQUEST, 'Published map id is required');
            }
            return null;
        }
        if(!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $id)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid published map id');
            return null;
        }
        return $id;
    }

    /** Generate a non-guessable unused published-map identifier. */
    private function createMapId(): string
    {
        do {
            $id = bin2hex(random_bytes(16));
        } while(file_exists($this->mapFileName($id)));
        return $id;
    }

    /** Resolve a published map file path from a validated/generated id. */
    private function mapFileName(string $id): string
    {
        return $this->dir.$id.'.json';
    }

    /**
     * Read and decode a published map file.
     *
     * @param string $id Validated map id.
     * @param bool $missingIsError Whether absence should register an error.
     * @return array|null|false Array when found, null when absent and allowed, false on error.
     */
    private function readMapFile(string $id, bool $missingIsError = true)
    {
        $file = $this->mapFileName($id);
        if(!is_file($file)){
            if($missingIsError){
                $this->system->addError(HEURIST_INVALID_REQUEST, 'Published map was not found');
                return false;
            }
            return null;
        }

        $json = file_get_contents($file);
        $data = $json !== false ? json_decode($json, true) : null;
        if(!is_array($data)){
            $this->system->addError(HEURIST_ERROR, 'Published map configuration is invalid');
            return false;
        }
        return $data;
    }

    /** Determine whether the current user may modify a stored map. */
    private function canModify(array $document): bool
    {
        $userId = intval($this->system->getUserId());
        return $userId > 0
            && (intval($document['createdBy'] ?? 0) === $userId || $this->system->isAdmin());
    }

    /** Build the public standalone page URL for a saved map. */
    private function buildShowUrl(string $id): string
    {
        //shortcut
        return HEURIST_BASE_URL.'?db='.rawurlencode($this->system->dbname())
            .'&map_id='.rawurlencode($id);
        //long    
        //return HEURIST_BASE_URL.'?db='.rawurlencode($this->system->dbname())
        //    .'&controller=MapController&action=show&id='.rawurlencode($id);
    }
}
