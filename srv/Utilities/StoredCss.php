<?php
/**
* StoredCss.php - Save-time CSS policy (plan 27a, decision 12)
*
* Custom CSS of a website or page (DT_CMS_CSS) is kept without the JavaScript
* permission: CSS cannot run code, so only the parts that can load or run
* something are removed. The same checks are used for the values of the node
* styles of a v4 page (Website\StyleCompiler).
*
* Removed: comments, `expression(`, `behavior`, `-moz-binding`, `javascript:` /
* `vbscript:` URLs, `data:` URLs other than raster images, `@import` of another
* site (Google Fonts allowed), `@charset`, and the characters `<` and `\0`
* (so a `</style>` can never end the style element). CSS escapes are decoded
* before the checks, so `\65 xpression(` is found too.
*
* @project     Heurist academic knowledge management system
* @package     Utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Utilities;

/** Removes the parts of a stylesheet that can load or run code. */
final class StoredCss
{
    /** Hosts an `@import` may load from (web fonts). */
    private const IMPORT_HOSTS = array('fonts.googleapis.com');

    /**
     * Clean a stylesheet. A declaration, selector or at-rule with a forbidden part
     * is dropped; the rest of the stylesheet is kept as written.
     */
    public static function clean(string $css): string
    {
        $css = str_replace(array("\0", '<'), '', $css);
        // comments can hide a forbidden word (expr/**/ession)
        $css = (string)preg_replace('#/\*.*?(\*/|$)#s', '', $css);
        // a ";" inside url(data:image/png;base64,...) must not split the declaration
        $urls = array();
        $css = (string)preg_replace_callback('/url\((?:[^)\'"]|\'[^\']*\'|"[^"]*")*\)/i', static function(array $match) use (&$urls): string {
            $urls[] = $match[0];
            return "\x01".(count($urls) - 1)."\x01";
        }, $css);
        $restore = static function(string $text) use (&$urls): string {
            return (string)preg_replace_callback('/\x01(\d+)\x01/', static function(array $match) use (&$urls): string {
                return $urls[intval($match[1])] ?? '';
            }, $text);
        };
        $result = '';
        // segments end with ";", "{" or "}" (the delimiter is kept)
        foreach(preg_split('/([;{}])/', $css, -1, PREG_SPLIT_DELIM_CAPTURE) as $index => $part){
            if($index % 2 === 1){ $result .= $part; continue; }
            $part = $restore($part);
            if(trim($part) === '' || self::isSafe($part)){
                $result .= $part;
            }
        }
        return trim(str_replace("\x01", '', $result));
    }

    /** Whether one CSS value (a node style of a v4 page) is safe; same rules as clean(). */
    public static function isSafeValue(string $value): bool
    {
        return strpbrk($value, "<;{}\0") === false && strpos($value, '/*') === false && self::isSafe($value);
    }

    /** One segment of a stylesheet: a declaration, a selector or an at-rule prelude. */
    private static function isSafe(string $segment): bool
    {
        $text = strtolower(self::decode($segment));
        $compact = (string)preg_replace('/\s+/', '', $text);
        foreach(array('expression(', 'behavior:', '-moz-binding', 'javascript:', 'vbscript:', '@charset') as $word){
            if(strpos($compact, $word) !== false){ return false; }
        }
        $urlCount = preg_match_all('/url\(\s*([\'"]?)(.*?)\1\s*\)/', $text, $urls);
        // an unclosed url( (escaped, or cut by a ";") cannot be checked
        if($urlCount !== substr_count($compact, 'url(')){ return false; }
        foreach($urls[2] as $url){
            if(!self::isSafeUrl(trim($url))){ return false; }
        }
        if(preg_match('/@import\s+(?:url\(\s*)?[\'"]?([^\'"\s)]*)/', $text, $import)){
            $host = parse_url($import[1], PHP_URL_HOST);
            if($host !== null && $host !== false && !in_array($host, self::IMPORT_HOSTS, true)){ return false; }
            if(strpos($import[1], '//') === 0 && $host === null){ return false; }
        }
        return true;
    }

    /** Relative, http(s) and raster image data URLs only. */
    private static function isSafeUrl(string $url): bool
    {
        if(preg_match('/^data:/', $url)){
            return (bool)preg_match('#^data:image/(?:png|gif|jpe?g|webp)[;,]#', $url);
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        return $scheme === null || $scheme === false || in_array(strtolower($scheme), array('http', 'https'), true);
    }

    /** Decode CSS escapes (`\65 ` and `\e`) so the checks see the real characters. */
    private static function decode(string $text): string
    {
        return (string)preg_replace_callback('/\\\\(?:([0-9a-f]{1,6})\s?|(.))/i', static function(array $match): string {
            if(($match[1] ?? '') !== ''){
                $code = hexdec($match[1]);
                return ($code > 0 && $code <= 0x10FFFF) ? mb_chr((int)$code, 'UTF-8') : '';
            }
            return $match[2] ?? '';
        }, $text);
    }
}
