<?php
/**
* FileWriteService.php - Register, change, replace and delete registered files
*
* The write side of the /files domain (plan 20, §8.3), the same for both stores
* (FileRowsInterface): the request is a file record (as GET /files/{id}) with
* "upload": <token> of a finished ChunkedUpload, or with rec_URL for an external
* resource. Files are moved by FileStorage, URLs inspected by FileUrlInspector.
* The row is written in a transaction that is rolled back when the file cannot
* be placed. Permissions as legacy: any logged-in user registers; the uploader
* or the database owner changes and deletes.
*
* @project     Heurist academic knowledge management system
* @package     Files
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Files;

use Heurist\Definitions\DefinitionLookup;
use Heurist\Files\Upload\ChunkedUpload;
use Heurist\Records\Edit\RecordEditException;
use Heurist\Records\Edit\RecordInput;
use Heurist\Records\Store\RecordRepository;
use Heurist\Runtime\RuntimeContext;

/** File registration and changes. */
final class FileWriteService
{
    private FileRowsInterface $rows;
    private FileRecordMapping $mapping;
    private FileStorage $storage;
    private ChunkedUpload $uploads;
    private FileUrlInspector $inspector;
    private RecordRepository $records;
    private DefinitionLookup $definitions;
    private RuntimeContext $runtime;
    /** @var array<string,string> Known extension => MIME type. */
    private array $mimeTypes;

    public function __construct(
        FileRowsInterface $rows,
        FileRecordMapping $mapping,
        FileStorage $storage,
        ChunkedUpload $uploads,
        FileUrlInspector $inspector,
        RecordRepository $records,
        DefinitionLookup $definitions,
        RuntimeContext $runtime,
        array $mimeTypes
    ) {
        $this->rows = $rows;
        $this->mapping = $mapping;
        $this->storage = $storage;
        $this->uploads = $uploads;
        $this->inspector = $inspector;
        $this->records = $records;
        $this->definitions = $definitions;
        $this->runtime = $runtime;
        $this->mimeTypes = $mimeTypes;
    }

    public function uploads(): ChunkedUpload
    {
        return $this->uploads;
    }

    /**
     * Append a chunk; after the last one the files with the same content are given (duplicates).
     */
    public function uploadChunk(string $token, string $contentRange, string $body): array
    {
        $status = $this->uploads->append($token, $contentRange, $body);
        if($status['complete'] && !isset($status['duplicates'])){
            $status = $this->uploads->setDuplicates($token, $this->rows->idsByChecksum((string)$status['md5']));
        }
        return $status;
    }

    /**
     * Insert (POST, id 0), replace (PUT) or change (PATCH) a file.
     *
     * @param array|null $body File record; "upload": token of a finished upload.
     * @param bool $force Write over a newer rec_Modified; register a URL that is registered already.
     * @param bool $dryRun Only the checks (POST /files/{id}/validate).
     * @return array{fileId:int,created:bool,existing:bool,issues:array}
     * @throws RecordEditException
     */
    public function save(string $method, int $fileId, ?array $body, bool $force = false, bool $dryRun = false): array
    {
        $this->assertAccount();
        $body = $body ?? array();
        if(isset($body['records']) && is_array($body['records']) && count($body['records']) === 1){
            $body = array_values($body['records'])[0];
            if(!is_array($body)){ throw RecordEditException::invalid('The record must be a JSON object'); }
        }
        $token = trim((string)($body['upload'] ?? ''));
        unset($body['upload']);
        $input = RecordInput::fromBody($body);
        if($input->has('rec_ID') && $input->intHeader('rec_ID') !== $fileId){
            throw RecordEditException::invalid('rec_ID of the body is not the file of the path');
        }
        $columns = $this->mapping->writableColumns($input->details());
        if($input->has('rec_NonOwnerVisibility')){
            $visibility = FileRecordMapping::legacyVisibility((string)$input->header('rec_NonOwnerVisibility'));
            if($visibility === null){ throw RecordEditException::invalid('Visibility of a file is public or viewable'); }
            $columns['ulf_WhoCanView'] = $visibility;
        }
        if($fileId < 1){
            if($method !== 'POST'){ throw RecordEditException::invalid('A new file is added with POST /files/0'); }
            return $this->insert($input, $columns, $token, $force, $dryRun);
        }
        if($method === 'POST'){ throw RecordEditException::invalid('Change a file with PUT or PATCH'); }
        return $this->update($method, $fileId, $input, $columns, $token, $force, $dryRun);
    }

    /**
     * Delete a file that no record refers to.
     *
     * @throws RecordEditException 404, 403, 409 (with the referring records)
     */
    public function delete(int $fileId): array
    {
        $this->assertAccount();
        $row = $this->existing($fileId);
        $this->assertCanEdit($row);
        $references = $this->records->fileReferences(array($fileId))[$fileId] ?? array();
        if(!empty($references)){
            throw new RecordEditException(409, 'file_in_use', 'The file is used by '.count($references).' record(s)',
                array(), array('records' => $references));
        }
        $this->rows->transaction(function() use ($fileId){ $this->rows->delete($fileId); });
        $this->storage->remove($row);
        return array('deleted' => $fileId);
    }

    private function insert(RecordInput $input, array $columns, string $token, bool $force, bool $dryRun): array
    {
        $url = trim((string)$input->header('rec_URL'));
        if($token === '' && $url === ''){ throw RecordEditException::invalid('A new file needs "upload" (a token) or rec_URL'); }
        if($token !== '' && $url !== ''){ throw RecordEditException::invalid('Give "upload" or rec_URL, not both'); }
        $now = date('Y-m-d H:i:s');
        $columns += array('ulf_WhoCanView' => 'viewable');
        $columns['ulf_UploaderUGrpID'] = $this->runtime->userId;
        $columns['ulf_Added'] = $now;
        $columns['ulf_Modified'] = $now;
        $issues = array();
        $upload = null;
        $name = '';

        if($url !== ''){
            $this->assertUrl($url);
            $existing = $force ? array() : $this->rows->idsByUrl($url);
            if(empty($existing)){
                $info = $this->inspector->inspect($url);
                $existing = $force || $info['url'] === $url ? array() : $this->rows->idsByUrl($info['url']);
                $columns['ulf_ExternalFileReference'] = $info['url'];
                $columns['ulf_PreferredSource'] = $info['source'];
                $columns['ulf_MimeExt'] = $info['ext'];
                $columns['ulf_OrigFileName'] = $columns['ulf_OrigFileName'] ?? $info['name'];
                $columns['ulf_FileSizeKB'] = 0;
            }
            if(!empty($existing)){
                // as legacy registerURL: the registration of this URL is used
                return array('fileId' => $existing[0], 'created' => false, 'existing' => true, 'issues' => array(
                    $this->issue('existing', 'This URL is registered already as file '.$existing[0])));
            }
        }else{
            $upload = $this->uploads->take($token);
            $tiled = $this->requestedType($input) === 'tiled' || $upload['ext'] === 'mbtiles';
            $name = (string)($columns['ulf_OrigFileName'] ?? '') ?: (string)$upload['name'];
            $columns['ulf_OrigFileName'] = $tiled ? '_tiled@'.pathinfo($name, PATHINFO_FILENAME) : $name;
            $columns['ulf_MimeExt'] = $upload['ext'];
            $columns['ulf_FileSizeKB'] = self::kilobytes(intval($upload['size']));
            $columns['ulf_MD5Checksum'] = $upload['md5'];
            $columns['ulf_PreferredSource'] = $tiled ? 'tiled' : $this->localSource($upload);
            if(!empty($upload['duplicates'])){
                $issues[] = $this->issue('duplicate', 'The same file is registered already: '.implode(', ', $upload['duplicates']));
            }
        }
        if(!isset($this->mimeTypes[strtolower((string)$columns['ulf_MimeExt'])])){
            throw new RecordEditException(415, 'file_type_not_allowed', 'Unknown file type: '.$columns['ulf_MimeExt']);
        }
        if($dryRun){ return array('fileId' => 0, 'created' => false, 'existing' => false, 'issues' => $issues); }

        $fileId = $this->rows->transaction(function() use ($columns, $upload, $name){
            $fileId = $this->rows->insert($columns);
            $key = $this->storage->newKey($fileId);
            $placed = array('ulf_ObfuscatedFileID' => $key);
            if($upload !== null){
                $placed += $columns['ulf_PreferredSource'] === 'tiled'
                    ? $this->storage->placeTiles($upload, $fileId, $name, $key)
                    : $this->storage->placeUpload($upload, $fileId, (string)$columns['ulf_OrigFileName'], $key);
            }
            $this->rows->update($fileId, $placed);
            return $fileId;
        });
        if($upload !== null){ $this->uploads->release($token); }
        return array('fileId' => $fileId, 'created' => true, 'existing' => false, 'issues' => $issues);
    }

    private function update(string $method, int $fileId, RecordInput $input, array $columns, string $token, bool $force,
        bool $dryRun): array
    {
        if(!in_array($method, array('PUT', 'PATCH'), true)){ throw RecordEditException::invalid('Unsupported method '.$method); }
        $row = $this->existing($fileId);
        $this->assertCanEdit($row);
        if($input->has('rec_Modified') && !$force && (string)$input->header('rec_Modified') !== (string)$row['ulf_Modified']){
            throw new RecordEditException(409, 'conflict', 'The file was changed by someone else', array(),
                array('rec_Modified' => $row['ulf_Modified']));
        }
        if($method === 'PUT'){
            $columns += array_fill_keys($this->mapping->writableColumnNames(), null);
            if(($columns['ulf_OrigFileName'] ?? null) === null){ unset($columns['ulf_OrigFileName']); } // the name stays
        }
        $isRemote = trim((string)($row['ulf_FileName'] ?? '')) === '' && trim((string)($row['ulf_ExternalFileReference'] ?? '')) !== '';
        $url = trim((string)$input->header('rec_URL'));
        if($input->has('rec_URL') && $url !== (string)$row['ulf_ExternalFileReference']){
            if(!$isRemote || $row['ulf_PreferredSource'] === 'tiled'){
                throw RecordEditException::invalid('The URL of an uploaded file cannot be changed');
            }
            $this->assertUrl($url);
            $info = $this->inspector->inspect($url);
            $columns['ulf_ExternalFileReference'] = $info['url'];
            $columns['ulf_PreferredSource'] = $info['source'];
            $columns['ulf_MimeExt'] = $info['ext'];
        }
        $issues = array();
        $upload = null;
        if($token !== ''){
            if($isRemote){ throw RecordEditException::invalid('An external resource has no content to replace'); }
            $upload = $this->uploads->take($token);
            $columns['ulf_OrigFileName'] = $columns['ulf_OrigFileName'] ?? (string)$upload['name'];
            $columns['ulf_MimeExt'] = $upload['ext'];
            $columns['ulf_FileSizeKB'] = self::kilobytes(intval($upload['size']));
            $columns['ulf_MD5Checksum'] = $upload['md5'];
            $columns['ulf_PreferredSource'] = $this->localSource($upload);
            if(!empty($upload['duplicates'])){
                $issues[] = $this->issue('duplicate', 'The same file is registered already: '.implode(', ', $upload['duplicates']));
            }
        }
        $columns['ulf_Modified'] = date('Y-m-d H:i:s');
        if($dryRun){ return array('fileId' => $fileId, 'created' => false, 'existing' => false, 'issues' => $issues); }

        $this->rows->transaction(function() use ($fileId, $row, $columns, $upload){
            $this->rows->update($fileId, $columns);
            if($upload !== null){
                $key = (string)$row['ulf_ObfuscatedFileID'] ?: $this->storage->newKey($fileId);
                $this->storage->remove($row);
                $placed = $this->storage->placeUpload($upload, $fileId, (string)$columns['ulf_OrigFileName'], $key);
                $this->rows->update($fileId, $placed + array('ulf_ObfuscatedFileID' => $key));
            }
        });
        if($upload !== null){ $this->uploads->release($token); }
        return array('fileId' => $fileId, 'created' => false, 'existing' => false, 'issues' => $issues);
    }

    /** local, or iiif/iiif_image for a local IIIF manifest or image information (JSON). */
    private function localSource(array $upload): string
    {
        if($upload['ext'] !== 'json' || intval($upload['size']) > 2097152){ return 'local'; }
        $json = json_decode((string)@file_get_contents((string)$upload['path']), true);
        return FileUrlInspector::iiifType(is_array($json) ? $json : null) ?? 'local';
    }

    /** Registered file type asked for in the request (field 2-1186, a term id or label), or ''. */
    private function requestedType(RecordInput $input): string
    {
        $fieldId = $this->mapping->fieldId('2-1186');
        $value = $fieldId > 0 ? ($input->details()[$fieldId][0] ?? null) : null;
        if(is_array($value)){ $value = $value['value'] ?? null; }
        $value = strtolower(trim((string)$value));
        if(ctype_digit($value)){
            $value = strtolower(trim((string)($this->definitions->term(intval($value))['term'] ?? '')));
        }
        return $value;
    }

    private function existing(int $fileId): array
    {
        $row = $this->rows->row($fileId);
        if($row === null){ throw RecordEditException::notFound('File '.$fileId.' not found'); }
        return $row;
    }

    /** Legacy KB size (at least 1 KB for a non-empty file). */
    private static function kilobytes(int $bytes): int
    {
        return $bytes < 1 ? 0 : max(1, intdiv($bytes, 1024));
    }

    private function issue(string $code, string $message): array
    {
        return array('code' => $code, 'severity' => 'warning', 'message' => $message);
    }

    /** @throws RecordEditException 400 */
    private function assertUrl(string $url): void
    {
        if(!preg_match('#^https?://[^\s]+$#i', $url) || strlen($url) > 1000){
            throw RecordEditException::invalid('rec_URL must be an http(s) address of at most 1000 characters');
        }
    }

    /** @throws RecordEditException 403 */
    private function assertAccount(): void
    {
        if($this->runtime->userId < 1){ throw RecordEditException::denied('Log in to change files'); }
    }

    /** The uploader or the database owner (legacy DbRecUploadedFiles::_validatePermission). */
    private function assertCanEdit(array $row): void
    {
        if(!$this->runtime->isDbOwner && intval($row['ulf_UploaderUGrpID'] ?? 0) !== $this->runtime->userId){
            throw RecordEditException::denied('Only the user who added the file or the database owner can change it');
        }
    }
}
