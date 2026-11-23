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
        // custom CSS and scripts of websites only with the JavaScript permission (decision 12 changed 2026-11-03:
        // CSS is cleared too, it was kept as checked CSS); a bare script has no HTML wrapper for a sanitizer anyway
        if($field > 0 && in_array($field, array($codes['cmsCss'] ?? 0, $codes['cmsScript'] ?? 0), true)){
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
        $config->set('Core.HiddenElements', array('script'=>true, 'style'=>true));
        $allowed = 'u,i,b,strong,em';
        if($type === 'blocktext'){
            // the font, size and colour of the HTML editor (2026-10-25): inline CSS on span only,
            // these properties only (HTMLPurifier checks their values; no url(), no positioning)
            $config->set('CSS.AllowedProperties', array('font-family', 'font-size', 'color', 'text-decoration'));
            // TinyMCE text, lists, links, tables and Heurist media; no executable embeds, SVG or forms.
            // the Align items of the editor's Formats menu: text-align only on blocks (textAlign())
            $allowed .= ',p[style],div[style],span[style],br,hr,h1[style],h2[style],h3[style],h4[style],h5[style],h6[style],'
                .'blockquote,pre,code,s,sup,sub,small,'
                .'ul,ol[start|type],li[value],dl,dt,dd,a[href|title|target|rel|data-ref],'
                .'img[src|alt|title|width|height|data-id|loading|style],'
                .'table[width|border|cellpadding|cellspacing|summary],caption,colgroup,col[span|width],'
                .'thead,tbody,tfoot,tr,th[colspan|rowspan|scope|abbr|width|height],td[colspan|rowspan|width|height],'
                .'figure[data-h-view|data-h-full|data-h-rec|data-h-align],figcaption,audio[src|controls|preload|data-id],video[src|controls|preload|poster|width|height|data-id|autoplay|muted|loop|playsinline],'
                .'source[src|type],iframe[src|width|height|title|frameborder|allowfullscreen|allow|loading],'
                // website format v4 (plan 27a): page structure, tabs and accordions, nodes and hosts
                // a group with a caption: section[aria-labelledby] = the id of its heading (plan 27c §20)
                .'section[aria-labelledby],article,aside,nav,header,footer,main,details[open],summary,'
                .'*[id|class|title|lang|dir|data-heurist-rec|data-heurist-app-id|data-inited'
                .'|data-h-node|data-h-instance|data-h-text|data-h-control|data-h-style|data-h-media|data-h-field|data-h-link|data-h-link-values|data-h-bg-video'
                .'|data-h-orientation|data-h-item-style|data-h-multiple|data-h-first-open|data-h-reveal|data-h-name|data-h-colors|data-h-hidden]';
            $config->set('HTML.SafeIframe', true);
            $config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/|w\.soundcloud\.com/player/)%');
            $config->set('HTML.Allowed', $allowed);
            $def = $config->getHTMLDefinition(true);
            $def->addElement('figure', 'Block', 'Flow', 'Common');
            $def->addElement('figcaption', 'Block', 'Flow', 'Common');
            $media = array('src'=>'URI', 'controls'=>'Bool', 'preload'=>'Enum#auto,metadata,none', 'data-id'=>'Number');
            $def->addElement('audio', 'Block', 'Flow', 'Common', $media);
            // a muted video in a loop, started at once (written by the site runtime for data-h-bg-video, plan 27i §9 D)
            $def->addElement('video', 'Block', 'Flow', 'Common', $media + array('poster'=>'URI', 'width'=>'Length', 'height'=>'Length',
                'autoplay'=>'Bool', 'muted'=>'Bool', 'loop'=>'Bool', 'playsinline'=>'Bool'));
            $def->addElement('source', 'Inline', 'Empty', 'Common', array('src'=>'URI', 'type'=>'Text'));
            // HTML5 sections of a v4 page (HTMLPurifier knows HTML 4 only)
            foreach(array('section', 'article', 'aside', 'nav', 'header', 'footer', 'main') as $element){
                $def->addElement($element, 'Block', 'Flow', 'Common');
            }
            $def->addAttribute('section', 'aria-labelledby', 'Text');
            $def->addElement('details', 'Block', 'Flow', 'Common', array('open'=>'Bool'));
            $def->addElement('summary', 'Block', 'Flow', 'Common');
            foreach(array('data-heurist-rec', 'data-heurist-app-id', 'data-inited') as $attribute){
                $def->addAttribute('div', $attribute, 'Text');
            }
            $blocks = array('div', 'p', 'span', 'section', 'article', 'aside', 'nav', 'header', 'footer', 'main',
                'details', 'summary', 'figure', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'table', 'img');
            // data-h-style: a style preset of the website or page (plan 27c §6a)
            // data-h-media: a media element (plan 27c §33)
            // data-h-name: the name of an element (plan 27n §9; CmsValidator checks the mask and duplicates)
            foreach(array('data-h-node', 'data-h-instance', 'data-h-control', 'data-h-style', 'data-h-media', 'data-h-name') as $attribute){
                foreach($blocks as $element){ $def->addAttribute($element, $attribute, 'Text'); }
            }
            // a gallery figure (plan 27e §18): what the action Player / Viewer opens; a checked URL, no scripts
            $def->addAttribute('figure', 'data-h-view', 'Enum#image,video,audio,service,iiif,3d,3dhop,pdf');
            $def->addAttribute('figure', 'data-h-full', 'URI');
            // the record of a gallery figure (plan 27g §6): the server adds its link to the record page or rec_URL
            $def->addAttribute('figure', 'data-h-rec', 'Number');
            // the alignment of a media element (2026-11-14): its media and caption centred or right
            $def->addAttribute('figure', 'data-h-align', 'Enum#left,center,right');
            // a Record field element (plan 27e §22): a checked reference to a field of a record
            $reference = self::fieldReference();
            foreach($blocks as $element){ $def->addAttribute($element, 'data-h-field', $reference); }
            // the action of a Record field element (plan 27g §6): `url`, `page:<id>` or `goto:<id>` (Go to a page, 2026-11-14;
            // `goto:home` the home page)
            $link = self::fieldLink();
            foreach($blocks as $element){ $def->addAttribute($element, 'data-h-link', $link); }
            // the values of a Saved filter link (`filter:<page>:<source>`, plan 27j): a JSON object within the limits
            $linkValues = self::linkValues();
            foreach($blocks as $element){ $def->addAttribute($element, 'data-h-link-values', $linkValues); }
            // a text block: a flag without a value (data-h-text="" after purifying)
            foreach($blocks as $element){ $def->addAttribute($element, 'data-h-text', 'Text'); }
            // the background video of a group (plan 27i §9 D): the key of a registered file; the site runtime adds the video
            $fileKey = self::fileKey();
            foreach($blocks as $element){ $def->addAttribute($element, 'data-h-bg-video', $fileKey); }
            // tabs (plan 27k §3): vertical tabs and the style of the tabs (the item styles of the menus; none = tabs)
            foreach($blocks as $element){
                $def->addAttribute($element, 'data-h-orientation', 'Enum#vertical');
                $def->addAttribute($element, 'data-h-item-style', 'Enum#links,underline,pills,buttons,outline');
                // colours of the tabs (plan 27n §7): inverted (over a banner) or own (Own style); none = the colours of the site
                $def->addAttribute($element, 'data-h-colors', 'Enum#inverted,own');
                // accordions (plan 27k §6): several sections open at a time, the first one open at start
                $def->addAttribute($element, 'data-h-multiple', 'Enum#true');
                $def->addAttribute($element, 'data-h-first-open', 'Enum#true');
                // reveal on scroll (plan 27k §6, 27n §8): fade / slide / zoom when it comes into the window; hidden = shown by an action
                $def->addAttribute($element, 'data-h-reveal', 'Enum#fade,up,down,left,right,zoom');
                // initially hidden (plan 27n §18.2b): shown by a Show / hide action, with its reveal effect
                $def->addAttribute($element, 'data-h-hidden', 'Enum#true');
            }
            $def->addAttribute('img', 'data-id', 'Number');
            $def->addAttribute('a', 'data-ref', 'Text');
            $def->addAttribute('iframe', 'allowfullscreen', 'Bool');
            // a media element (plan 27c §33): players load when shown, a service player may start after the click
            $def->addAttribute('iframe', 'allow', 'Enum#autoplay; fullscreen,fullscreen');
            $def->addAttribute('iframe', 'loading', 'Enum#lazy,eager');
            $def->addAttribute('img', 'loading', 'Enum#lazy,eager');
            $align = self::textAlign();
            foreach(array('p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6') as $element){
                $def->addAttribute($element, 'style', $align);
            }
            // the alignment of an image of the text editor (TinyMCE align buttons, 2026-11-14)
            $def->addAttribute('img', 'style', self::imageAlign());
        }else{
            $config->set('CSS.AllowedProperties', array());
            $config->set('HTML.ForbiddenAttributes', array('style'));
            $config->set('HTML.Allowed', $allowed);
        }
        return self::$purifiers[$type] = new \HTMLPurifier($config);
    }

    /**
     * `data-h-field`: `<record id|website|page|record>.<field id or concept code>`, as CmsValidator (`record` = the
     * record of the URL of a record page, plan 27g).
     */
    private static function fieldReference(): \HTMLPurifier_AttrDef
    {
        return new class extends \HTMLPurifier_AttrDef {
            public function validate($string, $config, $context)
            {
                $string = trim((string)$string);
                return preg_match('/^(website|page|record|\d+)\.\d+(-\d+)?$/', $string) === 1 ? $string : false;
            }
        };
    }

    /** `data-h-bg-video` of a group (plan 27i §9 D): the key of a registered file (letters and digits). */
    private static function fileKey(): \HTMLPurifier_AttrDef
    {
        return new class extends \HTMLPurifier_AttrDef {
            public function validate($string, $config, $context)
            {
                $string = trim((string)$string);
                return preg_match('/^[A-Za-z0-9]{1,64}$/', $string) === 1 ? $string : false;
            }
        };
    }

    /**
     * `data-h-link` of a Record field element (plan 27g §6): `url` or `page:<page id>`; `goto:<id>|goto:home` (Go to a
     * page); `filter:<page id>:<query source id>` (Saved filter, plan 27j), `filter:<page id>:f<saved filter id>` (plan 27l).
     */
    private static function fieldLink(): \HTMLPurifier_AttrDef
    {
        return new class extends \HTMLPurifier_AttrDef {
            public function validate($string, $config, $context)
            {
                $string = trim((string)$string);
                return preg_match('/^(url|page:\d+|goto:(\d+|home)|filter:\d+:f?\d+)$/', $string) === 1 ? $string : false;
            }
        };
    }

    /**
     * `data-h-link-values` of a Saved filter link (plan 27j): a JSON object of the values within the limits
     * (SavedFilterLink::values), written again as JSON; anything else is removed.
     */
    private static function linkValues(): \HTMLPurifier_AttrDef
    {
        return new class extends \HTMLPurifier_AttrDef {
            public function validate($string, $config, $context)
            {
                $decoded = json_decode(html_entity_decode(trim((string)$string), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
                if(!is_array($decoded)){ return false; }
                [$values, $dropped] = \Heurist\Website\SavedFilterLink::values($decoded);
                if($dropped || empty($values)){ return false; }
                return (string)json_encode($values, JSON_UNESCAPED_UNICODE);
            }
        };
    }

    /**
     * The style of an image (2026-11-14): only what the align buttons of TinyMCE write: `float` left / right / none,
     * `display: block` with `margin-left` / `margin-right` auto (centred), `vertical-align` (inline images).
     */
    private static function imageAlign(): \HTMLPurifier_AttrDef
    {
        return new class extends \HTMLPurifier_AttrDef {
            public function validate($string, $config, $context)
            {
                $allowed = array(
                    'float' => array('left', 'right', 'none'),
                    'display' => array('block', 'inline', 'inline-block'),
                    'margin-left' => array('auto', '0', '0px'),
                    'margin-right' => array('auto', '0', '0px'),
                    'vertical-align' => array('top', 'middle', 'bottom', 'baseline', 'text-top', 'text-bottom')
                );
                $kept = array();
                foreach(explode(';', (string)$string) as $declaration){
                    $parts = explode(':', $declaration, 2);
                    if(count($parts) !== 2){ continue; }
                    $name = strtolower(trim($parts[0]));
                    $value = strtolower(trim($parts[1]));
                    if(isset($allowed[$name]) && in_array($value, $allowed[$name], true)){ $kept[$name] = $name.': '.$value.';'; }
                }
                return empty($kept) ? false : implode(' ', $kept);
            }
        };
    }

    /** The style of a block (2026-10-25): `text-align` with a keyword, nothing else. */
    private static function textAlign(): \HTMLPurifier_AttrDef
    {
        return new class extends \HTMLPurifier_AttrDef {
            public function validate($string, $config, $context)
            {
                foreach(explode(';', (string)$string) as $declaration){
                    $parts = explode(':', $declaration, 2);
                    if(count($parts) !== 2 || strtolower(trim($parts[0])) !== 'text-align'){ continue; }
                    $value = strtolower(trim($parts[1]));
                    if(in_array($value, array('left', 'center', 'right', 'justify'), true)){ return 'text-align: '.$value.';'; }
                }
                return false;
            }
        };
    }
}
