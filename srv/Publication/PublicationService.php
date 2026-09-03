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

use DomainException;
use Heurist\Runtime\RuntimeContext;
use InvalidArgumentException;
use OutOfBoundsException;
use RuntimeException;

/** Storage and validation logic shared by published Heurist modules. */
final class PublicationService
{
    public const TYPES = array('map', 'data', 'timeline', 'graph', 'crosstabs');

    private RuntimeContext $runtime;
    private string $directory;

    /** Initialise storage from explicit modern runtime dependencies. */
    public function __construct(RuntimeContext $runtime, string $directory)
    {
        $directory = trim($directory);
        if($directory === ''){
            throw new InvalidArgumentException('Publication directory is not configured');
        }
        $this->runtime = $runtime;
        $this->directory = rtrim($directory, '/\\').DIRECTORY_SEPARATOR;
    }

    /** Retrieve one publication document. */
    public function get(string $id, bool $missingIsError = true): ?array
    {
        $this->validateId($id);
        $file = $this->publicationFileName($id);
        if(!is_file($file)){
            if($missingIsError){
                throw new OutOfBoundsException('Publication was not found');
            }
            return null;
        }

        $json = file_get_contents($file);
        $data = $json !== false ? json_decode($json, true) : null;
        if(!is_array($data)){
            throw new RuntimeException('Publication configuration is invalid');
        }
        return $data;
    }

    /** Create or replace one publication document. */
    public function save(array $payload, string $type, ?string $id = null): array
    {
        $type = $this->validateType($type);
        $payload = $this->normalisePayload($payload);
        $existing = null;
        if($id !== null && $id !== ''){
            $this->validateId($id);
            $existing = $this->get($id, false);
            if($existing !== null && !$this->canModify($existing)){
                throw new DomainException('You do not have permission to update this publication');
            }
        }else{
            $id = $this->createPublicationId();
        }

        $now = gmdate('c');
        $document = array(
            'format'=>'heurist-publication',
            'version'=>1,
            'type'=>$type,
            'created'=>is_array($existing) && !empty($existing['created']) ? $existing['created'] : $now,
            'createdBy'=>is_array($existing) && isset($existing['createdBy'])
                ? intval($existing['createdBy']) : $this->runtime->userId,
            'modified'=>$now,
            'options'=>$payload['options'],
            'config'=>$payload['config'],
            'state'=>$payload['state']
        );

        $this->ensureDirectory();
        $json = json_encode(
            $document,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
        if($json === false){
            throw new InvalidArgumentException('Publication configuration cannot be encoded as JSON');
        }

        $file = $this->publicationFileName($id);
        $temporaryFile = $file.'.tmp-'.bin2hex(random_bytes(4));
        if(file_put_contents($temporaryFile, $json, LOCK_EX) === false
            || !rename($temporaryFile, $file)){
            @unlink($temporaryFile);
            throw new RuntimeException('Cannot save publication configuration');
        }

        return array('id'=>$id, 'type'=>$type, 'url'=>$this->buildShowUrl($id));
    }

    /** Delete one publication owned by the current user or a database administrator. */
    public function delete(string $id): bool
    {
        $document = $this->get($id, true);
        if(!$this->canModify($document)){
            throw new DomainException('You do not have permission to delete this publication');
        }
        if(!@unlink($this->publicationFileName($id))){
            throw new RuntimeException('Cannot delete publication configuration');
        }
        return true;
    }

    /** Build the module-neutral standalone bootstrap. */
    public function buildBootstrap(array $document): array
    {
        return array(
            'runtime'=>array(
                'database'=>$this->runtime->databaseName,
                'baseUrl'=>$this->runtime->baseUrl,
                'apiBaseUrl'=>$this->runtime->baseUrl.'api',
                'runtimeMode'=>'published'
            ),
            'settings'=>array(
                'format'=>'heurist-'.($document['type'] ?? '').'-settings',
                'version'=>1,
                'options'=>$document['options'] ?? array(),
                'config'=>$document['config'] ?? array()
            ),
            'state'=>$document['state'] ?? null
        );
    }

    /** Build the public URL without repeating the module type. */
    public function buildShowUrl(string $id): string
    {
        $this->validateId($id);
        return $this->runtime->baseUrl.'?db='.rawurlencode($this->runtime->databaseName)
            .'&pub_id='.rawurlencode($id);
    }

    /** Validate a public publication identifier. */
    public function validateId(string $id): void
    {
        if(!preg_match('/^[A-Za-z0-9_-]{8,64}$/', trim($id))){
            throw new InvalidArgumentException(
                trim($id) === '' ? 'Publication id is required' : 'Invalid publication id'
            );
        }
    }

    /** Validate and normalise a module type. */
    public function validateType(string $type): string
    {
        $type = strtolower(trim($type));
        if(!in_array($type, self::TYPES, true)){
            throw new InvalidArgumentException('Invalid publication type');
        }
        return $type;
    }

    private function normalisePayload(array $value): array
    {
        if(isset($value['format']) && $value['format'] !== 'heurist-publication'){
            throw new InvalidArgumentException('Invalid publication format');
        }
        return array(
            'options'=>is_array($value['options'] ?? null) ? $value['options'] : array(),
            'config'=>is_array($value['config'] ?? null) ? $value['config'] : array(),
            'state'=>is_array($value['state'] ?? null) ? $value['state'] : array()
        );
    }

    private function createPublicationId(): string
    {
        do{
            $id = bin2hex(random_bytes(16));
        }while(file_exists($this->publicationFileName($id)));
        return $id;
    }

    private function publicationFileName(string $id): string
    {
        return $this->directory.$id.'.json';
    }

    private function ensureDirectory(): void
    {
        if(!is_dir($this->directory)
            && !mkdir($this->directory, 0775, true)
            && !is_dir($this->directory)){
            throw new RuntimeException('Cannot create publication directory');
        }
    }

    private function canModify(array $document): bool
    {
        return $this->runtime->userId > 0
            && (intval($document['createdBy'] ?? 0) === $this->runtime->userId
                || $this->runtime->isAdmin);
    }
}
