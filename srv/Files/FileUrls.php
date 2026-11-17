<?php
/**
* FileUrls.php - URLs of registered files on the file endpoints (plan 34)
*
* `/api/{db}/files/{key}/asset` (the file), `/web` (reduced copy of a large image), `/thumbnail`. Stored values
* (media HTML, texts, styles) keep the relative form `./api/{db}/files/{key}/{kind}` (relative to the Heurist base
* URL); resolveHtml() / resolveCss() make it absolute for a page. upgrade() turns legacy URLs (`?db=X&file=K`,
* `&fullres=1`, `&thumb=K`, `&download=1`; absolute with the base URL, `./?` or `?`) into the relative form: `?file=`
* in an `img` and in a CSS `url()` is the web copy (as legacy gave it), in other tags the file itself.
* Same rules as heurist-explorer `shared/src/data/fileUrls.js` (fixture `shared/test/fixtures/file-urls.json`,
* tests/FileUrlsTest.php).
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

/** File URLs: relative stored form, absolute page form, legacy upgrade. */
final class FileUrls
{
    /** Kinds of a file URL: `download` = the file as an attachment (`asset?download=1`). */
    public const KINDS = array('asset', 'web', 'thumbnail', 'download');

    /** Tags whose legacy `?file=` URL is the web copy. */
    private const WEB_TAGS = array('img');

    /**
     * URL of a file: relative (`./api/...`, stored form) without `$baseUrl`, absolute with it.
     *
     * @param string $kind asset | web | thumbnail | download
     * @return string '' without a key
     */
    public static function url(string $key, string $kind, string $database, string $baseUrl = ''): string
    {
        $key = trim($key);
        if($key === ''){ return ''; }
        $path = 'api/'.rawurlencode($database).'/files/'.rawurlencode($key).'/';
        $path .= $kind === 'download' ? 'asset?download=1' : (in_array($kind, self::KINDS, true) ? $kind : 'asset');
        $base = self::base($baseUrl);
        return $base === '' ? './'.$path : $base.$path;
    }

    /**
     * Key and kind of a file URL in any form (legacy or new, absolute or relative), or null. `$database`: only URLs
     * of this database; '' = any database of this installation (relative URLs and URLs with the base URL).
     *
     * @return array{key:string,kind:string,database:string}|null
     */
    public static function parse(string $url, string $database, string $baseUrl = ''): ?array
    {
        $value = str_replace('&amp;', '&', trim($url));
        if(preg_match('#^(?:\./|(?:https?:)?//[^?\#]*?/|/[^?\#]*?/)?api/([^/?\#]+)/files/([A-Za-z0-9]+)/(asset|web|thumbnail)(\?download=1)?$#', $value, $api)){
            $db = rawurldecode($api[1]);
            if($database !== '' && $db !== $database){ return null; }
            return array('key' => $api[2], 'kind' => !empty($api[4]) ? 'download' : $api[3], 'database' => $db);
        }
        $query = self::legacyQuery($value, $baseUrl);
        if($query === null){ return null; }
        parse_str($query, $params);
        $db = is_string($params['db'] ?? null) ? $params['db'] : '';
        if($db === '' || ($database !== '' && $db !== $database)){ return null; }
        $thumb = is_string($params['thumb'] ?? null) ? $params['thumb'] : '';
        if($thumb !== '' && preg_match('/^[A-Za-z0-9]+$/', $thumb)){ return array('key' => $thumb, 'kind' => 'thumbnail', 'database' => $db); }
        $file = is_string($params['file'] ?? null) ? $params['file'] : '';
        if($file === '' || !preg_match('/^[A-Za-z0-9]+$/', $file)){ return null; }
        if((string)($params['download'] ?? '') === '1'){ return array('key' => $file, 'kind' => 'download', 'database' => $db); }
        $fullres = array_key_exists('fullres', $params) && (string)$params['fullres'] !== '0';
        return array('key' => $file, 'kind' => $fullres ? 'asset' : 'web', 'database' => $db);
    }

    /** Legacy file URLs of this database in HTML or CSS → the relative new form. */
    public static function upgrade(string $text, string $database, string $baseUrl = ''): string
    {
        if($text === '' || !preg_match('/[?&](?:amp;)?(?:file|thumb)=/', $text)){ return $text; }
        return (string)preg_replace_callback(self::pattern($baseUrl), static function(array $match) use ($text, $database, $baseUrl): string {
            $parsed = self::parse($match[0][0], $database, $baseUrl);
            if($parsed === null){ return $match[0][0]; }
            $kind = $parsed['kind'];
            if($kind === 'web' && !self::webContext($text, intval($match[0][1]))){ $kind = 'asset'; }
            return self::url($parsed['key'], $kind, $parsed['database']);
        }, $text, -1, $count, PREG_OFFSET_CAPTURE);
    }

    /** HTML for a page: legacy URLs upgraded, relative file URLs made absolute. */
    public static function resolveHtml(string $html, string $database, string $baseUrl): string
    {
        $upgraded = self::upgrade($html, $database, $baseUrl);
        $base = self::base($baseUrl);
        if($base === ''){ return $upgraded; }
        return (string)preg_replace_callback('#(^|["\'(=\s])\./api/([^/"\'\s()<>]+)/files/#', static function(array $match) use ($database, $base): string {
            return $database === '' || rawurldecode($match[2]) === $database ? $match[1].$base.'api/'.$match[2].'/files/' : $match[0];
        }, $upgraded);
    }

    /** CSS for a page: the same as resolveHtml (url() values). */
    public static function resolveCss(string $css, string $database, string $baseUrl): string
    {
        return self::resolveHtml($css, $database, $baseUrl);
    }

    /** URLs that may be legacy file URLs: `?...` after the base URL, `./` or nothing, at the start of a value. */
    private static function pattern(string $baseUrl): string
    {
        $prefixes = array('\./');
        $base = self::base($baseUrl);
        if($base !== ''){ array_unshift($prefixes, preg_quote($base, '#')); }
        return '#(?<=^|["\'(=\s])(?:'.implode('|', $prefixes).')?\?[^"\'\s()<>]+#';
    }

    /** The query of a legacy URL (base URL, `./` or nothing before `?`), or null. */
    private static function legacyQuery(string $value, string $baseUrl): ?string
    {
        $base = self::base($baseUrl);
        if($base !== '' && strpos($value, $base) === 0){ $value = substr($value, strlen($base)); }
        elseif(strpos($value, './') === 0){ $value = substr($value, 2); }
        return strpos($value, '?') === 0 ? substr($value, 1) : null;
    }

    /** Whether the URL at the byte offset is an image shown in the page: in an `img` tag or a CSS `url()`. */
    private static function webContext(string $text, int $offset): bool
    {
        $start = max(0, $offset - 2000);
        $before = substr($text, $start, $offset - $start);
        if(preg_match('/url\(\s*["\']?$/i', $before)){ return true; }
        $open = strrpos($before, '<');
        if($open === false){ return false; }
        $close = strrpos($before, '>');
        if($close !== false && $close > $open){ return false; }
        return preg_match('/^<\s*([a-z0-9-]+)/i', substr($before, $open), $tag) === 1
            && in_array(strtolower($tag[1]), self::WEB_TAGS, true);
    }

    /** Base URL with one trailing slash, or ''. */
    private static function base(string $baseUrl): string
    {
        $value = trim($baseUrl);
        return $value === '' ? '' : rtrim($value, '/').'/';
    }
}
