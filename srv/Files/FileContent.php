<?php
/**
* FileContent.php - Content and thumbnail of a registered file
*
* GET /files/{key}/asset (was content, 2026-11-16) and /files/{key}/thumbnail (plan 20, §8.3), by the
* obfuscated key (rec_Hash), so file ids cannot be enumerated. Local files are
* sent with range support (206, Content-Range, Accept-Ranges) for audio and
* video; external resources are redirected to; files the legacy code keeps
* elsewhere (tile stacks, old absolute paths) and missing thumbnails are
* redirected to the legacy ?file= / ?thumb= links, which stay.
* Files that require login are refused to guests (all types; legacy only images).
* GET /files/{key}/web (2026-11-16): the reduced copy of a large local image (WebImageCache), as
* legacy ?file= without fullres; the file itself otherwise.
* As legacy (plan 34): a public file is redirected to the file store of the web server when the
* installation allows web access to uploaded files ($allowWebAccessUploadedFiles); a file of a
* repository with credentials (Nakala) is redirected to legacy ?file= (sent with the API key).
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

    /** URL of the file store root when the web server may send uploaded files itself (legacy
     *  $allowWebAccessUploadedFiles), else null. */
    private ?string $staticRootUrl;

    public function __construct(FileRowsInterface $rows, FileStorage $storage, RuntimeContext $runtime, ?string $staticRootUrl = null)
    {
        $this->rows = $rows;
        $this->storage = $storage;
        $this->runtime = $runtime;
        $this->staticRootUrl = $staticRootUrl !== null && $staticRootUrl !== '' ? rtrim($staticRootUrl, '/').'/' : null;
    }

    /**
     * What to answer (tests check the plan without sending).
     *
     * @param string $kind asset (the file), thumbnail or web
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
        // a file of a repository with credentials (Nakala): legacy sends it with the API key (plan 34)
        if(self::repositoryFile($row)){ return $this->redirect($this->legacyUrl('file', $key)); }
        $path = $this->storage->localPath($row);
        if($path === null || !is_file($path)){
            if($external !== '' && trim((string)($row['ulf_FileName'] ?? '')) === ''){ return $this->redirect($external); }
            return $this->redirect($this->legacyUrl('file', $key));
        }
        if($kind === 'web'){
            // the reduced copy of a large image; otherwise the file itself
            $cached = $this->storage->webCache()->get($row, $path, $this->rows);
            if($cached !== null){
                return array('status' => 200, 'headers' => array('Content-Type' => 'image/jpeg',
                    'Content-Length' => (string)filesize($cached), 'Cache-Control' => $this->cache($loginRequired)),
                    'path' => $cached, 'offset' => 0, 'length' => intval(filesize($cached)));
            }
        }
        // the web server sends public files itself when the installation allows it (legacy
        // $allowWebAccessUploadedFiles; ranges by the web server), not a download (plan 34)
        $static = !$download && !$loginRequired ? $this->staticUrl($path) : null;
        if($static !== null){
            // the redirect of a public file is cached by the browser: pages that show the same file (a banner on every
            // page) do not ask the server again (2026-11-16); the static file itself is revalidated by the web server
            $plan = $this->redirect($static);
            $plan['headers']['Cache-Control'] = $this->cache(false);
            return $plan;
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

    /**
     * The answer for a guest (plan 34: the request without the legacy system and session): sent unless the file
     * needs a login (403), then the caller runs the request again with the session of the user.
     *
     * @return bool Whether the answer was sent.
     */
    public function trySendPublic(string $method, string $key, string $kind, string $range = '', bool $download = false): bool
    {
        $method = strtoupper($method);
        if(!in_array($method, array('GET', 'HEAD'), true) || !in_array($kind, array('asset', 'web', 'thumbnail'), true)
            || $this->runtime->userId > 0){
            return false;
        }
        $plan = $this->plan($key, $kind, $range, $download);
        if(intval($plan['status']) === 403){ return false; }
        if($method === 'HEAD'){ unset($plan['path']); }
        self::send($plan);
        return true;
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

    /** Whether a file is read through a repository with stored credentials (ulf_Parameters `repository`). */
    private static function repositoryFile(array $row): bool
    {
        $parameters = json_decode((string)($row['ulf_Parameters'] ?? ''), true);
        return is_array($parameters) && !empty($parameters['repository']);
    }

    /** URL of a file below the file store root for the web server, or null (not allowed or outside the store). */
    private function staticUrl(string $path): ?string
    {
        if($this->staticRootUrl === null){ return null; }
        $root = rtrim(str_replace('\\', '/', $this->storage->paths()->root), '/').'/';
        $path = str_replace('\\', '/', $path);
        if(strpos($path, $root) !== 0){ return null; }
        $relative = substr($path, strlen($root));
        return $this->staticRootUrl.implode('/', array_map('rawurlencode', explode('/', $relative)));
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
