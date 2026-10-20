<?php
/**
* FileContent.php - Content and thumbnail of a registered file
*
* GET /files/{key}/content and /files/{key}/thumbnail (plan 20, §8.3), by the
* obfuscated key (rec_Hash), so file ids cannot be enumerated. Local files are
* sent with range support (206, Content-Range, Accept-Ranges) for audio and
* video; external resources are redirected to; files the legacy code keeps
* elsewhere (tile stacks, old absolute paths) and missing thumbnails are
* redirected to the legacy ?file= / ?thumb= links, which stay.
* Files that require login are refused to guests (all types; legacy only images).
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

use Heurist\Runtime\RuntimeContext;

/** Plans and sends file content responses. */
final class FileContent
{
    /** Bytes per write when sending a file. */
    private const BUFFER = 1048576;

    private FileRowsInterface $rows;
    private FileStorage $storage;
    private RuntimeContext $runtime;

    public function __construct(FileRowsInterface $rows, FileStorage $storage, RuntimeContext $runtime)
    {
        $this->rows = $rows;
        $this->storage = $storage;
        $this->runtime = $runtime;
    }

    /**
     * What to answer (tests check the plan without sending).
     *
     * @param string $kind content or thumbnail
     * @param string $range Range header ("bytes=a-b", "bytes=a-", "bytes=-n" or '').
     * @param bool $download Attachment instead of inline.
     * @return array{status:int,headers:array<string,string>,path?:string,offset?:int,length?:int,error?:string}
     */
    public function plan(string $key, string $kind, string $range = '', bool $download = false): array
    {
        if(!preg_match('/^[a-z0-9]{10,64}$/i', $key)){ return $this->error(404, 'File not found'); }
        $row = $this->rows->rowByKey($key);
        if($row === null){ return $this->error(404, 'File not found'); }
        $loginRequired = strtolower((string)($row['ulf_WhoCanView'] ?? '')) === 'loginrequired';
        if($loginRequired && $this->runtime->userId < 1){ return $this->error(403, 'Log in to see this file'); }

        if($kind === 'thumbnail'){
            $path = $this->storage->thumbnailPath($key);
            if($path === null){ return $this->redirect($this->legacyUrl('thumb', $key)); }
            return array('status' => 200, 'headers' => array('Content-Type' => 'image/png',
                'Content-Length' => (string)filesize($path), 'Cache-Control' => $this->cache($loginRequired)),
                'path' => $path, 'offset' => 0, 'length' => intval(filesize($path)));
        }

        $external = trim((string)($row['ulf_ExternalFileReference'] ?? ''));
        $source = strtolower((string)($row['ulf_PreferredSource'] ?? ''));
        if($source === 'tiled'){ return $this->redirect($this->legacyUrl('file', $key)); }
        $path = $this->storage->localPath($row);
        if($path === null || !is_file($path)){
            if($external !== '' && trim((string)($row['ulf_FileName'] ?? '')) === ''){ return $this->redirect($external); }
            return $this->redirect($this->legacyUrl('file', $key));
        }
        $size = intval(filesize($path));
        $headers = array(
            'Content-Type' => (string)($row['fxm_MimeType'] ?? '') ?: 'application/octet-stream',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => $this->cache($loginRequired),
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'
                .preg_replace('/["\\\\\r\n]/', '_', (string)$row['ulf_OrigFileName']).'"; filename*=UTF-8\'\''
                .rawurlencode((string)$row['ulf_OrigFileName'])
        );
        $range = trim($range);
        if($range === ''){
            return array('status' => 200, 'headers' => $headers + array('Content-Length' => (string)$size),
                'path' => $path, 'offset' => 0, 'length' => $size);
        }
        $bytes = self::range($range, $size);
        if($bytes === null){
            return array('status' => 416, 'headers' => array('Content-Range' => 'bytes */'.$size), 'error' => 'Range not satisfiable');
        }
        list($start, $end) = $bytes;
        return array('status' => 206, 'headers' => $headers + array(
            'Content-Range' => 'bytes '.$start.'-'.$end.'/'.$size, 'Content-Length' => (string)($end - $start + 1)),
            'path' => $path, 'offset' => $start, 'length' => $end - $start + 1);
    }

    /** Send a plan: headers and the file part, or the redirect, or the error. */
    public static function send(array $plan): void
    {
        http_response_code($plan['status']);
        foreach($plan['headers'] as $name => $value){ header($name.': '.$value); }
        if(isset($plan['error'])){
            if(!isset($plan['headers']['Content-Type'])){ header('Content-Type: text/plain; charset=utf-8'); }
            echo $plan['error'];
            return;
        }
        if(!isset($plan['path'])){ return; }
        while(ob_get_level() > 0){ ob_end_clean(); }
        $handle = fopen($plan['path'], 'rb');
        if($handle === false){ return; }
        fseek($handle, $plan['offset']);
        $left = $plan['length'];
        while($left > 0 && !feof($handle) && !connection_aborted()){
            $data = fread($handle, min(self::BUFFER, $left));
            if($data === false || $data === ''){ break; }
            echo $data;
            flush();
            $left -= strlen($data);
        }
        fclose($handle);
    }

    /**
     * First and last byte of a single range of a file, or null when it cannot be served.
     *
     * @return array{0:int,1:int}|null
     */
    public static function range(string $header, int $size): ?array
    {
        if($size < 1 || !preg_match('/^bytes=(\d*)-(\d*)$/i', trim($header), $match) || ($match[1] === '' && $match[2] === '')){
            return null;
        }
        if($match[1] === ''){
            // the last n bytes
            $length = min($size, intval($match[2]));
            return $length < 1 ? null : array($size - $length, $size - 1);
        }
        $start = intval($match[1]);
        $end = $match[2] === '' ? $size - 1 : min($size - 1, intval($match[2]));
        return $start > $end || $start >= $size ? null : array($start, $end);
    }

    private function legacyUrl(string $parameter, string $key): string
    {
        return $this->runtime->baseUrl.'?db='.rawurlencode($this->runtime->databaseName).'&'.$parameter.'='.$key;
    }

    private function redirect(string $url): array
    {
        return array('status' => 302, 'headers' => array('Location' => $url));
    }

    private function error(int $status, string $message): array
    {
        return array('status' => $status, 'headers' => array(), 'error' => $message);
    }

    private function cache(bool $loginRequired): string
    {
        return $loginRequired ? 'private, max-age=3600' : 'public, max-age=86400';
    }
}
