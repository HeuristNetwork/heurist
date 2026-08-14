<?php
/**
* MapPublishedService.php - Published map configuration service
*
* Persists, retrieves and deletes standalone heurist-map configuration files.
*
* @project     Heurist academic knowledge management system
* @package     map
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/
namespace hserv\records\map;

/**
 * Storage and validation logic for published heurist-map configurations.
 */
class MapPublishedService
{
    /** @var \hserv\System */
    private $system;

    /** @var string */
    private $dir;

    public function __construct($system)
    {
        $this->system = $system;
        $this->dir = rtrim($this->system->getSysDir('generated-maps'), '/\\').DIRECTORY_SEPARATOR;
    }

    /**
     * Read one published map configuration.
     *
     * @return array|null null when missing and $missingIsError is false.
     */
    public function get(string $id, bool $missingIsError = true): ?array
    {
        $this->validateId($id);

        $file = $this->mapFileName($id);
        if(!is_file($file)){
            if($missingIsError){
                throw new \RuntimeException('Published map was not found');
            }
            return null;
        }

        $json = file_get_contents($file);
        $data = $json !== false ? json_decode($json, true) : null;
        if(!is_array($data)){
            throw new \RuntimeException('Published map configuration is invalid');
        }

        return $data;
    }

    /**
     * Save a new published map or update an existing owned map.
     *
     * @return array Saved map id and public standalone URL.
     */
    public function save(array $payload, ?string $id = null): array
    {
        $payload = $this->normalisePayload($payload);

        $existing = null;
        if($id !== null && $id !== ''){
            $this->validateId($id);
            $existing = $this->get($id, false);
            if($existing !== null && !$this->canModify($existing)){
                throw new \RuntimeException('You do not have permission to update this published map');
            }
        }else{
            $id = $this->createMapId();
        }

        $now = gmdate('c');
        $created = is_array($existing) && !empty($existing['created'])
            ? $existing['created']
            : $now;
        $createdBy = is_array($existing) && isset($existing['createdBy'])
            ? intval($existing['createdBy'])
            : intval($this->system->getUserId());

        $document = array(
            'format' => 'heurist-map-publish',
            'version' => 1,
            'created' => $created,
            'createdBy' => $createdBy,
            'modified' => $now,
            'options' => $payload['options'],
            'config' => $payload['config'],
            'state' => $payload['state']
        );

        if(!is_dir($this->dir) && !mkdir($this->dir, 0775, true) && !is_dir($this->dir)){
            throw new \RuntimeException('Cannot create generated maps directory');
        }

        $json = json_encode(
            $document,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
        if($json === false){
            throw new \InvalidArgumentException('Published map configuration cannot be encoded as JSON');
        }

        $file = $this->mapFileName($id);
        $tmp = $file.'.tmp-'.bin2hex(random_bytes(4));
        if(file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $file)){
            @unlink($tmp);
            throw new \RuntimeException('Cannot save published map configuration');
        }

        return array(
            'id' => $id,
            'url' => $this->buildShowUrl($id)
        );
    }

    /**
     * Delete an owned published map.
     */
    public function delete(string $id): bool
    {
        $document = $this->get($id, true);
        if(!$this->canModify($document)){
            throw new \RuntimeException('You do not have permission to delete this published map');
        }

        if(!@unlink($this->mapFileName($id))){
            throw new \RuntimeException('Cannot delete published map configuration');
        }

        return true;
    }

    /**
     * Convert persisted settings to the standalone heurist-map bootstrap.
     */
    public function buildBootstrap(array $document): array
    {
        $baseUrl = rtrim(HEURIST_BASE_URL, '/').'/';

        return array(
            'runtime' => array(
                'database' => $this->system->dbname(),
                'baseUrl' => $baseUrl,
                'apiBaseUrl' => $baseUrl.'api'
            ),
            'settings' => array(
                'format' => 'heurist-map-settings',
                'version' => 1,
                'options' => $document['options'] ?? array(),
                'config' => $document['config'] ?? array()
            ),
            'state' => $document['state'] ?? null
        );
    }

    public function buildShowUrl(string $id): string
    {
        $this->validateId($id);

        return HEURIST_BASE_URL.'?db='.rawurlencode($this->system->dbname())
            .'&map_id='.rawurlencode($id);
    }

    public function validateId(string $id): void
    {
        $id = trim($id);
        if($id === ''){
            throw new \InvalidArgumentException('Published map id is required');
        }
        if(!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $id)){
            throw new \InvalidArgumentException('Invalid published map id');
        }
    }

    private function normalisePayload(array $value): array
    {
        return array(
            'options' => $this->filterOptions(
                is_array($value['options'] ?? null) ? $value['options'] : array()
            ),
            'config' => $this->filterConfig(
                is_array($value['config'] ?? null) ? $value['config'] : array()
            ),
            'state' => is_array($value['state'] ?? null) ? $value['state'] : array()
        );
    }

    private function filterOptions(array $value): array
    {
        return array(
            'ui' => $this->pick($value['ui'] ?? array(), array(
                'enabled','placement','position','initiallyExpanded','showCurrentDocument',
                'showMapDocuments','showLayers','showBaseMaps','showLegend','showZoomControl',
                'showSearch','showPublish','controlCss'
            )),
            'mapDocuments' => $this->pick(
                $value['mapDocuments'] ?? array(),
                array('allowed','initiallyActive')
            ),
            'baseMaps' => $this->pick(
                $value['baseMaps'] ?? array(),
                array('allowed','initial')
            ),
            'interaction' => $this->pick(
                $value['interaction'] ?? array(),
                array('selectionEnabled','popupEnabled','zoomOnSelection')
            )
        );
    }

    private function filterConfig(array $value): array
    {
        $defaults = $this->pick($value['defaults'] ?? array(), array(
            'zoomToPointInKM','symbology','selectSymbology','preventContinuousWorldBasemap',
            'markerClustering','maxAllowedFeatures','dynamicRequests','popupTemplate'
        ));

        $dynamic = $this->pick($value['dynamicDocument'] ?? array(), array(
            'enabled','title','minZoom','maxZoom','minimumZoomKm','maximumZoomKm','bounds'
        ));

        return array(
            'defaults' => $defaults,
            'dynamicDocument' => $dynamic
        );
    }

    private function pick($value, array $allowed): array
    {
        if(!is_array($value)){
            return array();
        }
        return array_intersect_key($value, array_flip($allowed));
    }

    private function createMapId(): string
    {
        do{
            $id = bin2hex(random_bytes(16));
        }while(file_exists($this->mapFileName($id)));

        return $id;
    }

    private function mapFileName(string $id): string
    {
        return $this->dir.$id.'.json';
    }

    private function canModify(array $document): bool
    {
        $userId = intval($this->system->getUserId());
        return $userId > 0
            && (
                intval($document['createdBy'] ?? 0) === $userId
                || $this->system->isAdmin()
            );
    }
}
