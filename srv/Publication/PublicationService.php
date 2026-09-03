<?php
/**
* PublicationService.php - Module publication storage service
*
* Persists, retrieves and deletes standalone Heurist module publications.
*
* @project     Heurist academic knowledge management system
* @package     Publication
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);
namespace Heurist\Publication;

/** Storage and validation logic shared by published Heurist modules. */
final class PublicationService
{
    public const TYPES = array('map', 'data', 'timeline', 'graph', 'crosstabs');

    /** @var \hserv\System */
    private $system;
    private string $dir;

    public function __construct($system)
    {
        $this->system = $system;
        $this->dir = rtrim($this->system->getSysDir('generated-pubs'), '/\\')
            .DIRECTORY_SEPARATOR;
    }

    public function get(string $id, string $type, bool $missingIsError = true): ?array
    {
        $type = $this->validateType($type);
        $this->validateId($id);
        $file = $this->publicationFileName($id);
        if(!is_file($file)){
            if($missingIsError){ throw new \RuntimeException('Publication was not found'); }
            return null;
        }
        $json = file_get_contents($file);
        $data = $json !== false ? json_decode($json, true) : null;
        if(!is_array($data)){ throw new \RuntimeException('Publication configuration is invalid'); }
        if(($data['type'] ?? null) !== $type){
            throw new \InvalidArgumentException('Publication type does not match the requested module');
        }
        return $data;
    }

    public function save(array $payload, string $type, ?string $id = null): array
    {
        $type = $this->validateType($type);
        $payload = $this->normalisePayload($payload);
        $existing = null;
        if($id !== null && $id !== ''){
            $this->validateId($id);
            $existing = $this->get($id, $type, false);
            if($existing !== null && !$this->canModify($existing)){
                throw new \RuntimeException('You do not have permission to update this publication');
            }
        }else{
            $id = $this->createPublicationId();
        }

        $now = gmdate('c');
        $document = array(
            'format'=>'heurist-publication', 'version'=>1, 'type'=>$type,
            'created'=>is_array($existing) && !empty($existing['created']) ? $existing['created'] : $now,
            'createdBy'=>is_array($existing) && isset($existing['createdBy'])
                ? intval($existing['createdBy']) : intval($this->system->getUserId()),
            'modified'=>$now, 'options'=>$payload['options'],
            'config'=>$payload['config'], 'state'=>$payload['state']
        );
        if(!is_dir($this->dir) && !mkdir($this->dir, 0775, true) && !is_dir($this->dir)){
            throw new \RuntimeException('Cannot create publication directory');
        }
        $json = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if($json === false){ throw new \InvalidArgumentException('Publication configuration cannot be encoded as JSON'); }
        $file = $this->publicationFileName($id);
        $tmp = $file.'.tmp-'.bin2hex(random_bytes(4));
        if(file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $file)){
            @unlink($tmp);
            throw new \RuntimeException('Cannot save publication configuration');
        }
        return array('id'=>$id, 'type'=>$type, 'url'=>$this->buildShowUrl($id, $type));
    }

    public function delete(string $id, string $type): bool
    {
        $document = $this->get($id, $type, true);
        if(!$this->canModify($document)){ throw new \RuntimeException('You do not have permission to delete this publication'); }
        if(!@unlink($this->publicationFileName($id))){ throw new \RuntimeException('Cannot delete publication configuration'); }
        return true;
    }

    public function buildBootstrap(array $document): array
    {
        $baseUrl = rtrim(HEURIST_BASE_URL, '/').'/';
        return array(
            'runtime'=>array('database'=>$this->system->dbname(), 'baseUrl'=>$baseUrl,
                'apiBaseUrl'=>$baseUrl.'api', 'runtimeMode'=>'published'),
            'settings'=>array('format'=>'heurist-'.($document['type'] ?? '').'-settings', 'version'=>1,
                'options'=>$document['options'] ?? array(), 'config'=>$document['config'] ?? array()),
            'state'=>$document['state'] ?? null
        );
    }

    public function buildShowUrl(string $id, string $type): string
    {
        $this->validateId($id); $type = $this->validateType($type);
        return HEURIST_BASE_URL.'?db='.rawurlencode($this->system->dbname())
            .'&publication_id='.rawurlencode($id).'&type='.rawurlencode($type);
    }

    public function validateId(string $id): void
    {
        if(!preg_match('/^[A-Za-z0-9_-]{8,64}$/', trim($id))){
            throw new \InvalidArgumentException(trim($id) === '' ? 'Publication id is required' : 'Invalid publication id');
        }
    }

    public function validateType(string $type): string
    {
        $type = strtolower(trim($type));
        if(!in_array($type, self::TYPES, true)){ throw new \InvalidArgumentException('Invalid publication type'); }
        return $type;
    }

    private function normalisePayload(array $value): array
    {
        if(isset($value['format']) && $value['format'] !== 'heurist-publication'){
            throw new \InvalidArgumentException('Invalid publication format');
        }
        return array(
            'options'=>is_array($value['options'] ?? null) ? $value['options'] : array(),
            'config'=>is_array($value['config'] ?? null) ? $value['config'] : array(),
            'state'=>is_array($value['state'] ?? null) ? $value['state'] : array()
        );
    }

    private function createPublicationId(): string
    {
        do{ $id = bin2hex(random_bytes(16)); }while(file_exists($this->publicationFileName($id)));
        return $id;
    }

    private function publicationFileName(string $id): string { return $this->dir.$id.'.json'; }

    private function canModify(array $document): bool
    {
        $userId = intval($this->system->getUserId());
        return $userId > 0 && (intval($document['createdBy'] ?? 0) === $userId || $this->system->isAdmin());
    }
}
