<?php
/**
* StaticFileSender.php - Thumbnails and web copies sent before the system is initialised
*
* GET /api/{db}/files/{key}/thumbnail (and /web) are asked for many times on every page with images. The full
* request (System::init: database connection, session, login, then the srv services) for each image overloads the
* server. When the file is already on disk in a folder the installation shares with the web anyway, it is sent
* here without any of that, as a static file: no database, no session.
*
* - thumbnail: `filethumbs/ulf_{key}.png`, when `$allowWebAccessThumbnails` (the thumbnail folder is public);
* - web: `webimagecache/{key}.jpg`, when `$allowWebAccessUploadedFiles` (uploaded files are public).
*
* Otherwise (setting off, file not made yet, a POST...) nothing is sent and the normal request runs (FileContent:
* access check, a thumbnail or web copy made on the first request). The file store root is the rule of legacy
* System::getFileStoreRootFolder ($defaultRootFileUploadPath, else DOCUMENT_ROOT/HEURIST/HEURIST_FILESTORE/).
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

/** Sends an existing public thumbnail or web copy without initialising the system. */
final class StaticFileSender
{
    /** Browsers and proxies keep the file a day (a key is never reused for other content: new content = new copy). */
    private const MAX_AGE = 86400;

    /**
     * Send the file of a request when it can be sent as a static file.
     *
     * @param string $method HTTP method (GET, HEAD).
     * @param array<int,string> $segments Path after /api: database, `files`, key, kind.
     * @param array<string,mixed> $config `allowWebAccessThumbnails`, `allowWebAccessUploadedFiles`,
     *        `defaultRootFileUploadPath`, `documentRoot`, `ifModifiedSince`, `ifNoneMatch`.
     * @return bool Whether the answer was sent (the caller exits).
     */
    public static function trySend(string $method, array $segments, array $config): bool
    {
        $path = self::path($method, $segments, $config);
        if($path === null){ return false; }
        $mtime = intval(@filemtime($path));
        $size = intval(@filesize($path));
        if($mtime < 1 || $size < 1){ return false; }
        $etag = '"'.dechex($mtime).'-'.dechex($size).'"';
        header('Cache-Control: public, max-age='.self::MAX_AGE);
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT');
        header('ETag: '.$etag);
        $since = strtotime((string)($config['ifModifiedSince'] ?? '')) ?: 0;
        if((string)($config['ifNoneMatch'] ?? '') === $etag || ($since > 0 && $since >= $mtime)){
            http_response_code(304);
            return true;
        }
        header('Content-Type: '.(substr($path, -4) === '.png' ? 'image/png' : 'image/jpeg'));
        header('Content-Length: '.$size);
        if(strtoupper($method) !== 'HEAD'){ readfile($path); }
        return true;
    }

    /**
     * The file of a request that may be sent statically, or null.
     *
     * @param array<int,string> $segments
     * @param array<string,mixed> $config See trySend.
     */
    public static function path(string $method, array $segments, array $config): ?string
    {
        if(!in_array(strtoupper($method), array('GET', 'HEAD'), true)){ return null; }
        $segments = array_values($segments);
        if(count($segments) !== 4 || $segments[1] !== 'files'){ return null; }
        list($database, , $key, $kind) = $segments;
        if(!preg_match('/^[A-Za-z0-9_$]+$/', (string)$database) || !preg_match('/^[a-z0-9]{10,64}$/i', (string)$key)){ return null; }
        if($kind === 'thumbnail' && !empty($config['allowWebAccessThumbnails'])){
            $file = 'filethumbs/ulf_'.$key.'.png';
        }elseif($kind === 'web' && !empty($config['allowWebAccessUploadedFiles'])){
            $file = 'webimagecache/'.$key.'.jpg';
        }else{
            return null;
        }
        $root = self::root((string)($config['defaultRootFileUploadPath'] ?? ''), (string)($config['documentRoot'] ?? ''));
        $path = $root.$database.'/'.$file;
        return is_file($path) ? $path : null;
    }

    /** File store root (legacy System::getFileStoreRootFolder). */
    public static function root(string $configured, string $documentRoot): string
    {
        if(trim($configured) !== ''){
            $root = $configured;
            if($root !== '/' && !preg_match('#[^/]/$#', $root)){ $root .= '/'; }
            if(strpos($root, ':/') === false && $root !== '/' && !preg_match('#^/[^/]#', $root)){ $root = '/'.$root; }
            return $root;
        }
        if($documentRoot !== '' && substr($documentRoot, -1) !== '/'){ $documentRoot .= '/'; }
        return $documentRoot.'HEURIST/HEURIST_FILESTORE/';
    }
}
