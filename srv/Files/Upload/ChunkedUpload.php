<?php
/**
* ChunkedUpload.php - Uploads in chunks into the scratch folder
*
* The upload part of the /files domain (plan 20, §8.3): a client starts an
* upload (name, size), sends the content in chunks with Content-Range and gets
* a token. After the last chunk the file is checked (extension, size), its MD5
* and MIME type are found and an image gets a 200×200 thumbnail. The token is
* then given when the file is registered (POST /files/0).
*
* Files: <scratch>/uploads/<token> (content), <token>.json (state), <token>.png
* (thumbnail). Unfinished or unregistered uploads older than a day are removed
* when a new upload starts.
*
* @project     Heurist academic knowledge management system
* @package     Files\Upload
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Files\Upload;

use Heurist\Files\ImageThumbnail;
use Heurist\Records\Edit\RecordEditException;

/** Upload sessions of one user. */
final class ChunkedUpload
{
    /** Chunk size given to clients (bytes); lower when the PHP post limit is lower. */
    public const CHUNK_SIZE = 5242880;

    /** Age (seconds) after which an unregistered upload is removed. */
    private const MAX_AGE = 86400;

    /** Extensions never accepted (legacy UploadHandler). */
    private const DENIED = '/\.(bat|exe|cmd|sh|php(\d)?|pl|cgi|386|dll|com|torrent|js|app|jar|pif|vb|vbscript|asp|cer|csr|jsp'
        .'|drv|sys|ade|adp|bas|chm|cpl|crt|csh|fxp|hlp|hta|inf|ins|isp|jse|htaccess|htpasswd|ksh|lnk|mdb|mde|mdt|mdw|msc'
        .'|msi|msp|mst|ops|pcd|prg|reg|scr|sct|shb|shs|url|vbe|vbs|wsc|wsf|wsh)$/i';

    private string $folder;
    private int $userId;
    /** @var array<string,string> Allowed extension => MIME type (defFileExtToMimetype). */
    private array $mimeTypes;
    /** @var (callable():array{0:int,1:int})|null Disk quota and use in bytes. */
    private $quota;
    private int $chunkSize;

    /**
     * @param string $folder Folder of the upload sessions (FilePaths::uploadSessions()).
     * @param int $userId Current user (uploads belong to one user).
     * @param array<string,string> $mimeTypes Allowed extensions => MIME type.
     * @param callable|null $quota Returns [quota, used] in bytes; null = no quota check.
     * @param int $postLimit Largest request body PHP accepts (0 = unknown).
     */
    public function __construct(string $folder, int $userId, array $mimeTypes, ?callable $quota = null, int $postLimit = 0)
    {
        $this->folder = rtrim(str_replace('\\', '/', $folder), '/').'/';
        $this->userId = $userId;
        $this->mimeTypes = $mimeTypes;
        $this->quota = $quota;
        $limit = $postLimit > 0 ? max(65536, $postLimit - 65536) : self::CHUNK_SIZE;
        $this->chunkSize = min(self::CHUNK_SIZE, $limit);
    }

    /**
     * Start an upload.
     *
     * @param array{name?:string,size?:int|string,type?:string} $info Original file name and size.
     * @return array{token:string,chunkSize:int,received:int,size:int,name:string}
     * @throws RecordEditException 400 bad name/size, 413 quota, 415 file type
     */
    public function start(array $info): array
    {
        $this->assertUser();
        $name = self::cleanName((string)($info['name'] ?? ''));
        $size = intval($info['size'] ?? 0);
        if($name === ''){ throw RecordEditException::invalid('The file name is not defined'); }
        if(strlen($name) > 255){ throw RecordEditException::invalid('The file name is longer than 255 bytes'); }
        if($size < 1){ throw RecordEditException::invalid('The file is empty'); }
        $this->assertType($name);
        if($this->quota !== null){
            list($quota, $used) = ($this->quota)();
            if($quota > 0 && $used + $size > $quota){
                throw new RecordEditException(413, 'quota_exceeded', 'The disk quota of this database ('
                    .round($quota / 1048576).' MB) does not allow this file');
            }
        }
        $this->cleanup();
        if(!is_dir($this->folder) && !@mkdir($this->folder, 0775, true) && !is_dir($this->folder)){
            throw new \RuntimeException('Upload folder cannot be created');
        }
        $token = bin2hex(random_bytes(16));
        $state = array('token' => $token, 'user' => $this->userId, 'name' => $name, 'size' => $size,
            'type' => (string)($info['type'] ?? ''), 'received' => 0, 'complete' => false, 'started' => time());
        touch($this->folder.$token);
        $this->write($state);
        return array('token' => $token, 'chunkSize' => $this->chunkSize, 'received' => 0, 'size' => $size, 'name' => $name);
    }

    /**
     * Append one chunk. The chunk must start where the received content ends; otherwise
     * 409 with the received size, so the client can resume.
     *
     * @param string $contentRange Header value "bytes a-b/total".
     * @param string $body The chunk.
     * @return array Status (received, complete) and, after the last chunk, the file (finish()).
     * @throws RecordEditException 400, 404, 409, 415
     */
    public function append(string $token, string $contentRange, string $body): array
    {
        $state = $this->state($token);
        if($state['complete']){ return $this->publicState($state); }
        if(!preg_match('/^bytes\s+(\d+)-(\d+)\/(\d+)$/i', trim($contentRange), $range)){
            throw RecordEditException::invalid('Content-Range "bytes start-end/total" is required');
        }
        list(, $start, $end, $total) = array_map('intval', $range);
        if($total !== intval($state['size'])){ throw RecordEditException::invalid('The total size does not match the upload'); }
        if($end < $start || $end >= $total || strlen($body) !== $end - $start + 1){
            throw RecordEditException::invalid('The chunk does not match its Content-Range');
        }
        if(strlen($body) > $this->chunkSize){ throw RecordEditException::invalid('The chunk is larger than '.$this->chunkSize.' bytes'); }
        $received = $this->received($token);
        if($start !== $received){
            throw new RecordEditException(409, 'range_mismatch', 'The upload continues at byte '.$received,
                array(), array('received' => $received));
        }
        if(file_put_contents($this->folder.$token, $body, FILE_APPEND | LOCK_EX) === false){
            throw new \RuntimeException('The chunk cannot be written');
        }
        clearstatcache(true, $this->folder.$token);
        $state['received'] = $this->received($token);
        if($state['received'] >= $state['size']){
            $state = $this->finish($state);
        }
        $this->write($state);
        return $this->publicState($state);
    }

    /** State of an upload (received bytes; the file after the last chunk). */
    public function status(string $token): array
    {
        $state = $this->state($token);
        $state['received'] = $state['complete'] ? $state['size'] : $this->received($token);
        return $this->publicState($state);
    }

    /** Cancel an upload and remove its files. */
    public function cancel(string $token): void
    {
        $this->state($token);
        $this->remove($token);
    }

    /**
     * A complete upload for registration: its state with the content and thumbnail paths.
     *
     * @return array{token:string,name:string,size:int,md5:string,mime:string,ext:string,path:string,thumbnail:?string}
     * @throws RecordEditException 404 unknown, 409 not complete
     */
    public function take(string $token): array
    {
        $state = $this->state($token);
        if(!$state['complete']){
            throw new RecordEditException(409, 'upload_incomplete', 'The upload is not complete',
                array(), array('received' => $this->received($token)));
        }
        $state['path'] = $this->folder.$token;
        $state['thumbnail'] = is_file($this->folder.$token.'.png') ? $this->folder.$token.'.png' : null;
        return $state;
    }

    /** Remove the files of an upload after its registration (the content was moved). */
    public function release(string $token): void
    {
        $this->remove($token);
    }

    /** Whether an extension may be uploaded (allowed and not denied). */
    public function allowed(string $name): bool
    {
        return !preg_match(self::DENIED, $name) && isset($this->mimeTypes[self::extension($name)]);
    }

    /** Lower-case extension of a file name ('' when none). */
    public static function extension(string $name): string
    {
        $dot = strrpos($name, '.');
        return $dot === false ? '' : strtolower(substr($name, $dot + 1));
    }

    /** File name without path and control characters. */
    public static function cleanName(string $name): string
    {
        $name = str_replace(array("\0", '\\'), array('', '/'), $name);
        $name = basename($name);
        return trim(preg_replace('/[\x00-\x1F\x7F]/', '', $name));
    }

    /** Checks of a complete upload: MD5, MIME type, thumbnail of an image. */
    private function finish(array $state): array
    {
        $path = $this->folder.$state['token'];
        $ext = self::extension($state['name']);
        $state['complete'] = true;
        $state['received'] = $state['size'];
        $state['md5'] = (string)md5_file($path);
        $state['ext'] = $ext;
        $state['mime'] = $this->mimeTypes[$ext] ?? 'application/octet-stream';
        if(function_exists('finfo_open')){
            $info = finfo_open(FILEINFO_MIME_TYPE);
            $detected = $info ? (string)finfo_file($info, $path) : '';
            if($info){ finfo_close($info); }
            if($detected !== ''){ $state['detectedMime'] = $detected; }
        }
        if(strpos($state['mime'], 'image/') === 0){
            ImageThumbnail::autoOrient($path);
            clearstatcache(true, $path);
            $state['size'] = filesize($path) ?: $state['size'];
            $state['received'] = $state['size'];
            $state['md5'] = (string)md5_file($path);
            $state['hasThumbnail'] = ImageThumbnail::create($path, $path.'.png');
        }
        return $state;
    }

    /** State of an upload of the current user. */
    private function state(string $token): array
    {
        $this->assertUser();
        if(!preg_match('/^[a-f0-9]{32}$/', $token) || !is_file($this->folder.$token.'.json')){
            throw RecordEditException::notFound('Upload not found');
        }
        $state = json_decode((string)file_get_contents($this->folder.$token.'.json'), true);
        if(!is_array($state) || intval($state['user'] ?? 0) !== $this->userId){
            throw RecordEditException::notFound('Upload not found');
        }
        return $state;
    }

    private function publicState(array $state): array
    {
        $result = array('token' => $state['token'], 'name' => $state['name'], 'size' => intval($state['size']),
            'received' => intval($state['received']), 'complete' => (bool)$state['complete'], 'chunkSize' => $this->chunkSize);
        if($state['complete']){
            foreach(array('md5', 'mime', 'ext') as $key){ $result[$key] = $state[$key] ?? null; }
            $result['thumbnail'] = !empty($state['hasThumbnail']);
            if(isset($state['duplicates'])){ $result['duplicates'] = $state['duplicates']; }
        }
        return $result;
    }

    /** Record the duplicates (same MD5) found after the last chunk. */
    public function setDuplicates(string $token, array $fileIds): array
    {
        $state = $this->state($token);
        $state['duplicates'] = array_values(array_map('intval', $fileIds));
        $this->write($state);
        return $this->publicState($state);
    }

    private function received(string $token): int
    {
        clearstatcache(true, $this->folder.$token);
        return is_file($this->folder.$token) ? intval(filesize($this->folder.$token)) : 0;
    }

    private function write(array $state): void
    {
        file_put_contents($this->folder.$state['token'].'.json', json_encode($state), LOCK_EX);
    }

    private function remove(string $token): void
    {
        foreach(array('', '.json', '.png') as $suffix){
            if(is_file($this->folder.$token.$suffix)){ @unlink($this->folder.$token.$suffix); }
        }
    }

    /** Remove uploads older than a day (of every user). */
    private function cleanup(): void
    {
        foreach(glob($this->folder.'*.json') ?: array() as $file){
            if(filemtime($file) < time() - self::MAX_AGE){
                $this->remove(basename($file, '.json'));
            }
        }
    }

    /** @throws RecordEditException 415 */
    private function assertType(string $name): void
    {
        if(!$this->allowed($name)){
            throw new RecordEditException(415, 'file_type_not_allowed', 'Files of this type ('
                .(self::extension($name) ?: 'no extension').') cannot be uploaded');
        }
    }

    /** @throws RecordEditException 403 */
    private function assertUser(): void
    {
        if($this->userId < 1){ throw RecordEditException::denied('Log in to upload files'); }
    }
}
