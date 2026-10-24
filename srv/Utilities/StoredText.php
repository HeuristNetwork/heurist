<?php
/** Shared save-time HTML policy. Never use regular expressions as an HTML sanitizer. */
declare(strict_types=1);

namespace Heurist\Utilities;

final class StoredText
{
    private static array $purifiers = array();

    /** Code is trusted only in these fields of these CMS record types, with server authorization. */
    public static function allowsCode(int $rectype, int $field, array $codes, bool $authorized): bool
    {
        return $authorized && $rectype > 0 && $field > 0
            && in_array($rectype, array($codes['cmsHome'] ?? 0, $codes['cmsPage'] ?? 0), true)
            && in_array($field, array($codes['cmsScript'] ?? 0, $codes['cmsCss'] ?? 0,
                $codes['cmsHeader'] ?? 0, $codes['cmsFooter'] ?? 0, $codes['extendedDescription'] ?? 0), true);
    }

    public static function clean(string $value, string $type, int $rectype = 0, int $field = 0,
        array $codes = array(), bool $authorized = false): string
    {
        $value = self::trim($value);
        if(self::allowsCode($rectype, $field, $codes, $authorized)){ return $value; }
        // A bare script or stylesheet has no HTML wrapper for a sanitizer to recognize.
        if($field > 0 && in_array($field, array($codes['cmsScript'] ?? 0, $codes['cmsCss'] ?? 0), true)){
            return '';
        }
        // Structured settings must remain valid JSON when one of their string values contains HTML.
        if(isset($value[0]) && ($value[0] === '{' || $value[0] === '[')){
            $json = json_decode($value);
            if(json_last_error() === JSON_ERROR_NONE){
                $cleaned = self::cleanJson($json, $type);
                return $cleaned == $json ? $value : (string)json_encode($cleaned, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        // Plain text is also used for URLs and queries: do not entity-encode it. A tag starts with
        // "<" directly followed by a letter, "!", "/" or "?" (as in a browser): "a < b" is text.
        if(!preg_match('/<[a-z!\/?]/i', $value)){ return $value; }
        return self::trim(self::purifier($type)->purify($value));
    }

    private static function cleanJson($value, string $type)
    {
        if(is_string($value)){ return self::clean($value, $type); }
        if(is_array($value)){ return array_map(static function($item) use ($type){ return self::cleanJson($item, $type); }, $value); }
        if(is_object($value)){
            $copy = new \stdClass();
            foreach($value as $key=>$item){
                $safeKey = self::clean((string)$key, $type);
                $copy->$safeKey = self::cleanJson($item, $type);
            }
            return $copy;
        }
        return $value;
    }

    private static function trim(string $value): string
    {
        return (string)preg_replace('/^[\s\x{00A0}\x{FEFF}]+|[\s\x{00A0}\x{FEFF}]+$/u', '', $value);
    }

    private static function purifier(string $type): \HTMLPurifier
    {
        $type = $type === 'blocktext' ? 'blocktext' : 'freetext';
        if(isset(self::$purifiers[$type])){ return self::$purifiers[$type]; }
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('Cache.DefinitionImpl', null);
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('URI.AllowedSchemes', array('http'=>true, 'https'=>true, 'mailto'=>true, 'tel'=>true));
        $config->set('Attr.EnableID', true);
        $config->set('Attr.AllowedFrameTargets', array('_blank', '_self'));
        $config->set('CSS.AllowedProperties', array());
        $config->set('HTML.ForbiddenAttributes', array('style'));
        $config->set('Core.HiddenElements', array('script'=>true, 'style'=>true));
        $allowed = 'u,i,b,strong,em';
        if($type === 'blocktext'){
            // TinyMCE text, lists, links, tables and Heurist media; no executable embeds, SVG or forms.
            $allowed .= ',p,div,span,br,hr,h1,h2,h3,h4,h5,h6,blockquote,pre,code,s,sup,sub,small,'
                .'ul,ol[start|type],li[value],dl,dt,dd,a[href|title|target|rel|data-ref],'
                .'img[src|alt|title|width|height|data-id],'
                .'table[width|border|cellpadding|cellspacing|summary],caption,colgroup,col[span|width],'
                .'thead,tbody,tfoot,tr,th[colspan|rowspan|scope|abbr|width|height],td[colspan|rowspan|width|height],'
                .'figure,figcaption,audio[src|controls|preload|data-id],video[src|controls|preload|poster|width|height|data-id],'
                .'source[src|type],iframe[src|width|height|title|frameborder|allowfullscreen],'
                .'*[id|class|title|lang|dir|data-heurist-rec|data-heurist-app-id|data-inited]';
            $config->set('HTML.SafeIframe', true);
            $config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/|w\.soundcloud\.com/player/)%');
            $config->set('HTML.Allowed', $allowed);
            $def = $config->getHTMLDefinition(true);
            $def->addElement('figure', 'Block', 'Flow', 'Common');
            $def->addElement('figcaption', 'Block', 'Flow', 'Common');
            $media = array('src'=>'URI', 'controls'=>'Bool', 'preload'=>'Enum#auto,metadata,none', 'data-id'=>'Number');
            $def->addElement('audio', 'Block', 'Flow', 'Common', $media);
            $def->addElement('video', 'Block', 'Flow', 'Common', $media + array('poster'=>'URI', 'width'=>'Length', 'height'=>'Length'));
            $def->addElement('source', 'Inline', 'Empty', 'Common', array('src'=>'URI', 'type'=>'Text'));
            foreach(array('data-heurist-rec', 'data-heurist-app-id', 'data-inited') as $attribute){
                $def->addAttribute('div', $attribute, 'Text');
            }
            $def->addAttribute('img', 'data-id', 'Number');
            $def->addAttribute('a', 'data-ref', 'Text');
            $def->addAttribute('iframe', 'allowfullscreen', 'Bool');
        }else{
            $config->set('HTML.Allowed', $allowed);
        }
        return self::$purifiers[$type] = new \HTMLPurifier($config);
    }
}
