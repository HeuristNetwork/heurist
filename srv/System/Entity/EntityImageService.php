<?php
/**
* EntityImageService.php - The image of a user or group: GET / PUT / DELETE /sys/{type}/{id}/image
*
* Plan 20, Phase 4d (§10.13). PUT takes a finished chunked upload ({"upload": token}, as
* /files); the image is kept by EntityImageStorage (the legacy entity folders). The answer
* is the image's URLs (legacy fileGet.php, as the legacy UI shows them), or none. Who may
* change an image is decided per type by the write service of the type (`canEdit`).
*
* @project     Heurist academic knowledge management system
* @package     System\Entity
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\System\Entity;

use Heurist\Files\EntityImageStorage;
use Heurist\Files\Upload\ChunkedUpload;
use Heurist\Records\Edit\RecordEditException;
use Heurist\Runtime\RuntimeContext;

/** Images of system entities. */
final class EntityImageService
{
    private EntityImageStorage $storage;
    private ?ChunkedUpload $uploads;
    private RuntimeContext $runtime;
    /** @var array<string,callable(int):void> type => check that the user may change the image of an id (throws) */
    private array $canEdit;

    /**
     * @param array<string,callable(int):void> $canEdit Per type: throws RecordEditException 403 / 404.
     */
    public function __construct(EntityImageStorage $storage, ?ChunkedUpload $uploads, RuntimeContext $runtime, array $canEdit)
    {
        $this->storage = $storage;
        $this->uploads = $uploads;
        $this->runtime = $runtime;
        $this->canEdit = $canEdit;
    }

    /**
     * The URLs of the image (null when the entity has none).
     *
     * @return array{type:string,id:int,image:?array{url:string,thumbnail:string}}
     * @throws RecordEditException 404
     */
    public function get(string $type, int $id): array
    {
        $this->assertType($type);
        $image = null;
        if($this->storage->path($type, $id) !== null){
            $image = array(
                'url' => EntityImageStorage::url($this->runtime->baseUrl, $this->runtime->databaseName, $type, $id, 'full'),
                'thumbnail' => EntityImageStorage::url($this->runtime->baseUrl, $this->runtime->databaseName, $type, $id, 'thumbnail')
            );
        }
        return array('type' => $type, 'id' => $id, 'image' => $image);
    }

    /**
     * Put the image of a finished upload.
     *
     * @param array|null $body {"upload": token}
     * @throws RecordEditException 400, 403, 404, 405
     */
    public function put(string $type, int $id, ?array $body): array
    {
        $this->assertType($type);
        $this->check($type, $id);
        if($this->uploads === null){ throw new RecordEditException(405, 'method_not_allowed', 'Images cannot be uploaded here'); }
        $token = trim((string)($body['upload'] ?? ''));
        if($token === ''){ throw RecordEditException::invalid('The image is a finished upload: {"upload": token}'); }
        $upload = $this->uploads->take($token);
        try{
            $this->storage->place($type, $id, (string)$upload['path'], (string)$upload['ext']);
        }catch(\RuntimeException $e){
            throw RecordEditException::invalid($e->getMessage());
        }finally{
            $this->uploads->release($token);
        }
        return $this->get($type, $id);
    }

    /**
     * Remove the image.
     *
     * @throws RecordEditException 403, 404
     */
    public function delete(string $type, int $id): array
    {
        $this->assertType($type);
        $this->check($type, $id);
        $this->storage->remove($type, $id);
        return $this->get($type, $id);
    }

    /** @throws RecordEditException 403, 404 */
    private function check(string $type, int $id): void
    {
        call_user_func($this->canEdit[$type], $id);
    }

    /** @throws RecordEditException 404 */
    private function assertType(string $type): void
    {
        if(!EntityImageStorage::supports($type) || !isset($this->canEdit[$type])){
            throw RecordEditException::notFound('System records of type '.$type.' have no image');
        }
    }
}
