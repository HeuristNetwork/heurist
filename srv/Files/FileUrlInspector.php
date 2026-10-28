<?php
/**
* FileUrlInspector.php - What an external file URL is (port of legacy registerURL)
*
* Finds the registered file type and the extension (MIME type) of an external
* resource: YouTube, Vimeo and SoundCloud by their address, IIIF manifests and
* image services (canonical info.json), otherwise the Content-Type of a HEAD
* request or the extension in the address. Wikimedia and Nakala details of the
* legacy code are not ported yet (they use the HEAD request).
* Also checks local JSON files for IIIF (isIiif).
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

/** Inspects external URLs and JSON content. */
final class FileUrlInspector
{
    /** Largest body read from a remote JSON (bytes). */
    private const MAX_BODY = 2097152;

    /** @var array<string,string> Known extension => MIME type. */
    private array $mimeTypes;

    /** @var callable(string,bool):array{status:int,type:string,body:string} */
    private $fetch;

    /**
     * @param array<string,string> $mimeTypes Known extension => MIME type (defFileExtToMimetype).
     * @param callable|null $fetch fn(url, headOnly) => {status, type, body}; null = curl.
     */
    public function __construct(array $mimeTypes, ?callable $fetch = null)
    {
        $this->mimeTypes = $mimeTypes;
        $this->fetch = $fetch ?? array(self::class, 'curlFetch');
    }

    /**
     * Registered file type and extension of a URL.
     *
     * @return array{url:string,source:string,ext:string,name:string,guessed:bool} source: external, iiif or
     *         iiif_image; name: the legacy ulf_OrigFileName of remote files (_remote, _iiif, _iiif_image);
     *         guessed: neither the address nor the server gave a known file type (ext is bin, 2026-10-27).
     */
    public function inspect(string $url): array
    {
        $url = trim($url);
        $lower = strtolower($url);
        foreach(array('youtube' => array('youtube.com/', 'youtu.be/'), 'vimeo' => array('vimeo.com/'),
            'soundcloud' => array('soundcloud.com/')) as $service => $hosts){
            foreach($hosts as $host){
                if(strpos($lower, $host) !== false){ return $this->result($url, 'external', $this->known($service)); }
            }
        }
        $ext = self::urlExtension($url);
        if($ext === 'json' || substr($lower, -10) === '/info.json'){
            $iiif = $this->remoteIiif($url);
            if($iiif !== null){ return $iiif; }
        }elseif(!in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'tif', 'tiff', 'pdf', 'zip'), true)
            && strpos($lower, '/full/') === false){
            // an IIIF image service given without info.json
            $iiif = $this->remoteIiif(rtrim($url, '/').'/info.json');
            if($iiif !== null && $iiif['source'] === 'iiif_image'){ return $iiif; }
        }
        $head = $this->call($url, true);
        $type = strtolower(trim(explode(';', $head['type'])[0]));
        if($type !== ''){
            if(in_array($type, array('application/json', 'application/ld+json'), true)){
                $iiif = $this->remoteIiif($url);
                if($iiif !== null){ return $iiif; }
                return $this->result($url, 'external', $this->known('json'));
            }
            $byType = array_keys($this->mimeTypes, $type, true);
            if(!empty($byType) && ($ext === '' || ($this->mimeTypes[$ext] ?? '') !== $type)){
                // image/jpeg: "jpeg" rather than the first of jpe, jpeg, jpg
                $subtype = substr($type, strpos($type, '/') + 1);
                return $this->result($url, 'external', in_array($subtype, $byType, true) ? $subtype : (string)$byType[0]);
            }
        }
        if(isset($this->mimeTypes[$ext])){ return $this->result($url, 'external', $ext); }
        return array('guessed' => true) + $this->result($url, 'external', $this->known('bin'));
    }

    /**
     * IIIF type of decoded JSON: iiif (manifest), iiif_image (image information) or null.
     */
    public static function iiifType(?array $json): ?string
    {
        if(!is_array($json)){ return null; }
        $type = (string)($json['@type'] ?? $json['type'] ?? '');
        if($type === 'sc:Manifest' || $type === 'Manifest'){ return 'iiif'; }
        $context = (string)json_encode($json['@context'] ?? '', JSON_UNESCAPED_SLASHES);
        $profile = (string)json_encode($json['profile'] ?? '', JSON_UNESCAPED_SLASHES);
        $id = $json['@id'] ?? $json['id'] ?? null;
        $isImage = strpos($context, 'iiif.io/api/image') !== false
            || in_array($type, array('iiif:Image', 'ImageService2', 'ImageService3'), true)
            || strpos($profile, 'iiif.io/api/image') !== false;
        if($id !== null && isset($json['width'], $json['height']) && $isImage){ return 'iiif_image'; }
        return null;
    }

    /** Lower-case extension of the path of a URL ('' when none). */
    public static function urlExtension(string $url): string
    {
        $path = (string)parse_url($url, PHP_URL_PATH);
        $base = basename($path);
        $dot = strrpos($base, '.');
        return $dot === false ? '' : strtolower(substr($base, $dot + 1));
    }

    /** Result for a remote IIIF manifest or image service, or null. */
    private function remoteIiif(string $url): ?array
    {
        $response = $this->call($url, false);
        if($response['status'] < 200 || $response['status'] >= 300){ return null; }
        $json = json_decode($response['body'], true);
        $type = self::iiifType(is_array($json) ? $json : null);
        if($type === null){ return null; }
        if($type === 'iiif_image'){
            $id = rtrim((string)($json['@id'] ?? $json['id']), '/');
            return $this->result($id.'/info.json', 'iiif_image', $this->known('json'));
        }
        return $this->result($url, 'iiif', $this->known('json'));
    }

    private function result(string $url, string $source, string $ext): array
    {
        $names = array('external' => '_remote', 'iiif' => '_iiif', 'iiif_image' => '_iiif_image');
        return array('url' => $url, 'source' => $source, 'ext' => $ext, 'name' => $names[$source], 'guessed' => false);
    }

    /** The extension when this database knows it, else bin (or the first known extension). */
    private function known(string $ext): string
    {
        if(isset($this->mimeTypes[$ext])){ return $ext; }
        return isset($this->mimeTypes['bin']) ? 'bin' : (string)(array_key_first($this->mimeTypes) ?? $ext);
    }

    private function call(string $url, bool $headOnly): array
    {
        if(!preg_match('#^https?://#i', $url)){ return array('status' => 0, 'type' => '', 'body' => ''); }
        try{
            $response = ($this->fetch)($url, $headOnly);
        }catch(\Throwable $e){
            return array('status' => 0, 'type' => '', 'body' => '');
        }
        return array('status' => intval($response['status'] ?? 0), 'type' => (string)($response['type'] ?? ''),
            'body' => (string)($response['body'] ?? ''));
    }

    /** Default fetch: curl, redirects followed, 10 s, body up to MAX_BODY. */
    public static function curlFetch(string $url, bool $headOnly): array
    {
        if(!function_exists('curl_init')){ return array('status' => 0, 'type' => '', 'body' => ''); }
        $body = '';
        $handle = curl_init($url);
        curl_setopt_array($handle, array(
            CURLOPT_NOBODY => $headOnly,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'Heurist',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION => static function($curl, $data) use (&$body){
                if(strlen($body) + strlen($data) > self::MAX_BODY){ return 0; }
                $body .= $data;
                return strlen($data);
            }
        ));
        curl_exec($handle);
        $status = intval(curl_getinfo($handle, CURLINFO_RESPONSE_CODE));
        $type = (string)curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        unset($handle); // freed with the object (curl_close does nothing since PHP 8)
        return array('status' => $status, 'type' => $type, 'body' => $body);
    }
}
