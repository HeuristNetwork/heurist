<?php
/**
* StyleCompiler.php - The styles JSON of a v4 website page as CSS (plan 27a)
*
* `styles.base` (the base settings, decisions 11-13): a key is the name of a CSS
* variable without "--h-"; written on `html.h-site`, which wins over the
* `:root` defaults of heurist-ui.css, so text, menus, tabs and the modules take
* the site's look. `styles.nodes`: CSS properties of the elements with
* `data-h-node`, with overrides for the screen sizes `medium` (<= 1024 px) and
* `narrow` (<= 640 px). Only the properties of the plan are written and every
* value is checked (StoredCss); the rest is left out with a warning.
* Common styles (plan 27c §6a), written before the node styles so that a node's own
* style wins: `styles.kinds` (defaults of a kind: group, tabs, accordion, text,
* instance) and `styles.presets` (`{name: {label, ...properties, medium, narrow}}`,
* used by an element with `data-h-style="name"`). Website first, then the page.
* A scope (plan 27f §2.2): the page styles of a website page are written below
* `[data-h-page="{id}"]` (its section), so pages loaded into one document (navigation
* modes swap / scroll) do not change each other; base settings become variables of the section.
*
* @project     Heurist academic knowledge management system
* @package     Website
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Website;

use Heurist\Files\FileUrls;
use Heurist\Utilities\StoredCss;

/** Compiles base settings and node styles into one stylesheet. */
final class StyleCompiler
{
    /** Base settings (key = CSS variable without "--h-"). */
    public const BASE = array(
        'font-family', 'color-text', 'color-bg', 'color-surface', 'color-primary', 'color-link',
        'color-border', 'radius', 'site-font-size', 'site-heading-font', 'site-heading-color', 'site-width',
        'color-selected', 'color-selected-border', 'color-primary-text', 'color-text-muted', 'site-logo-height'
    );

    /** Screen sizes: key in a node style => max width. */
    // small (<= 375 px, 2026-11-14): the media rules in this order, the smallest wins
    public const SCREENS = array('medium' => 1024, 'narrow' => 640, 'small' => 375);

    /** Node style properties (plan 27a §4); margin, padding and border by pattern. */
    private const PROPERTIES = array(
        'display', 'flex-direction', 'flex-wrap', 'justify-content', 'justify-items', 'align-items', 'align-content', 'gap', 'flex', 'grid-template-columns',
        'grid-template-rows', 'grid-column', 'grid-row', 'order',
        'width', 'min-width', 'max-width', 'height', 'min-height', 'max-height', 'overflow',
        'border-radius', 'box-shadow', 'text-shadow',
        'color', 'background-color', 'background-image', 'background-position', 'background-repeat', 'background-size', 'font-family', 'font-size', 'font-weight', 'line-height', 'text-align',
        // the item colours of menus, action menus and tabs (plan 27n §7, colours Own)
        '--h-item-text', '--h-item-accent', '--h-item-accent-text'
    );

    /** Kinds of `styles.kinds` and their selectors. */
    /**
     * Selectors of the own styles of the header and footer (review 2026-11-16, CMS_PART_SELECTORS of cmsDocument.js):
     * stronger than the header / footer themes of the layout.
     */
    public const PARTS = array('header' => 'html.h-site .h-site-header', 'footer' => 'html.h-site .h-site-footer');

    public const KINDS = array(
        // not the header default set and its brand (2026-11-14): their fallback layout is the website CSS; :where keeps the
        // specificity
        'group' => '[data-h-control="group"]:where(:not(.h-site-hset, .h-site-brand))',
        'tabs' => '[data-h-control="tabs"]',
        'accordion' => '[data-h-control="accordion"]',
        'text' => '[data-h-text]:not([data-h-media])',
        'media' => '[data-h-media]',
        // modules and widgets, not the hosts of menus (nav) and the language switch (cmsDocument.js CMS_KINDS, 2026-11-12)
        'instance' => '[data-h-instance]:where(:not(nav, .h-site-lang-single):not(:has(> .h-site-lang)))'
    );

    private const NAME = '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/';
    private const DISPLAY = array('block', 'flex', 'grid', 'none');

    /** @var array<int,array{level:string,code:string,message:string,node:?string}> */
    private array $issues = array();
    private string $baseUrl;
    /** Prefix of every selector of the current compile() (scope and a space), or ''. */
    private string $scope = '';

    /** @param string $baseUrl Heurist base URL: background images may only come from it (or be relative). */
    public function __construct(string $baseUrl = '')
    {
        $this->baseUrl = $baseUrl;
    }

    /** Problems found by the last compile(). */
    public function issues(): array
    {
        return $this->issues;
    }

    /**
     * CSS of the base settings, kind defaults, presets and node styles.
     *
     * @param array $base Base settings (website merged with page).
     * @param array<int,array> $nodeSets Node styles, in order (website first, then the page).
     * @param array<int,array> $kindSets Kind defaults (`styles.kinds`), website first, then the page.
     * @param array<int,array> $presetSets Presets (`styles.presets`), website first, then the page.
     * @param string $scope Selector every rule is written below (plan 27f), e.g. `[data-h-page="5"]`; '' = none.
     * @param array $pageStyle Own style of the page (`styles.page`, plan 27i §9): written for its section, without the scope.
     * @param int $pageId The page of $pageStyle; 0 = any page (checks).
     * @param array $partStyles Own styles of the header and footer (`styles.header` / `styles.footer` of the website
     *        record, review 2026-11-16): {header, footer}, written for PARTS.
     */
    public function compile(array $base, array $nodeSets, array $kindSets = array(), array $presetSets = array(),
        string $scope = '', array $pageStyle = array(), int $pageId = 0, array $partStyles = array()): string
    {
        $this->issues = array();
        $this->scope = $scope !== '' ? $scope.' ' : '';
        $css = '';
        $variables = $this->variables($base);
        if($variables !== ''){ $css .= ($scope !== '' ? $scope : 'html.h-site').'{'.$variables."}\n"; }
        $screens = array_fill_keys(array_keys(self::SCREENS), '');
        foreach($kindSets as $kinds){
            if(!is_array($kinds)){ continue; }
            foreach($kinds as $kind => $style){
                $kind = (string)$kind;
                if(!isset(self::KINDS[$kind]) || !is_array($style)){
                    $this->issue('style_kind', 'Kind style "'.$kind.'" is not valid', null);
                    continue;
                }
                $this->rule(self::KINDS[$kind], $style, null, $css, $screens);
            }
        }
        $this->flushScreens($css, $screens);
        foreach($presetSets as $presets){
            if(!is_array($presets)){ continue; }
            foreach($presets as $name => $style){
                $name = (string)$name;
                if(!preg_match(self::NAME, $name) || !is_array($style)){
                    $this->issue('style_preset', 'Style preset "'.$name.'" is not valid', null);
                    continue;
                }
                unset($style['label']);
                $this->rule('[data-h-style="'.$name.'"]', $style, null, $css, $screens);
            }
        }
        $this->flushScreens($css, $screens);
        if(!empty($pageStyle)){
            // the section is the scope itself
            $prefix = $this->scope;
            $this->scope = '';
            $this->rule(self::pageSelector($pageId), self::pageHeightAsMinimum($pageStyle), null, $css, $screens);
            $this->scope = $prefix;
            $this->flushScreens($css, $screens);
        }
        foreach(self::PARTS as $part => $selector){
            $style = $partStyles[$part] ?? null;
            if(!is_array($style) || empty($style)){ continue; }
            // the height is a minimum (the content may need more); not when the header is shrunk or collapsed (style.css)
            $this->rule($selector, self::pageHeightAsMinimum($style), null, $css, $screens);
        }
        $this->flushScreens($css, $screens);
        foreach($nodeSets as $nodes){
            if(!is_array($nodes)){ continue; }
            foreach($nodes as $id => $style){
                $id = (string)$id;
                if(!preg_match(self::NAME, $id) || !is_array($style)){
                    $this->issue('style_node', 'Node style "'.$id.'" is not valid', $id);
                    continue;
                }
                $this->rule('[data-h-node="'.$id.'"]', $style, $id, $css, $screens);
            }
        }
        $this->flushScreens($css, $screens);
        return $css;
    }

    /**
     * The height of a page is its minimum height (2026-11-14): the page grows with its content, which would otherwise
     * run over the footer. Also on the medium and narrow screens.
     */
    private static function pageHeightAsMinimum(array $style): array
    {
        if(isset($style['height'])){
            if(!isset($style['min-height'])){
                $style['min-height'] = $style['height'];
            }
            unset($style['height']);
        }
        foreach($style as $key => $value){
            if(is_array($value)){
                $style[$key] = self::pageHeightAsMinimum($value);
            }
        }
        return $style;
    }

    /** Selector of the section of a page (PageRenderer), for its own style; 0 = any page. */
    public static function pageSelector(int $pageId = 0): string
    {
        return $pageId > 0 ? '.h-site-page[data-h-page="'.$pageId.'"]' : '.h-site-page';
    }

    /**
     * The screen rules collected so far, after the rules of their level (kinds, presets, nodes), so a node's own
     * style wins over a kind default or a preset on every screen (plan 27c §30). Medium first, then narrow: the
     * smaller screen wins.
     */
    private function flushScreens(string &$css, array &$screens): void
    {
        foreach(self::SCREENS as $screen => $width){
            if($screens[$screen] !== ''){
                // .98: a viewport of a fractional width (display scaling, e.g. 375.2 px) is still this screen (2026-11-15)
                $css .= '@media (max-width:'.$width.".98px){\n".$screens[$screen]."}\n";
            }
            $screens[$screen] = '';
        }
    }

    /** The rule of one selector, and its screen rules into $screens. */
    private function rule(string $selector, array $style, ?string $id, string &$css, array &$screens): void
    {
        $selector = $this->scope.$selector;
        $rules = $this->declarations($style, $id);
        if($rules !== ''){ $css .= $selector.'{'.$rules."}\n"; }
        foreach(array_keys(self::SCREENS) as $screen){
            if(isset($style[$screen]) && is_array($style[$screen])){
                $rules = $this->declarations($style[$screen], $id);
                if($rules !== ''){ $screens[$screen] .= $selector.'{'.$rules."}\n"; }
            }
        }
    }

    /** CSS variables of the base settings. */
    private function variables(array $base): string
    {
        $css = '';
        foreach($base as $key => $value){
            $key = (string)$key;
            if(!in_array($key, self::BASE, true)){
                $this->issue('style_base', 'Unknown base setting "'.$key.'"', null);
                continue;
            }
            if(!is_scalar($value) || !$this->isSafe((string)$value, false)){
                $this->issue('style_value', 'Value of base setting "'.$key.'" is not allowed', null);
                continue;
            }
            $css .= '--h-'.$key.':'.trim((string)$value).';';
            if($key === 'color-primary'){
                $css .= '--h-color-primary-hover:color-mix(in srgb, '.trim((string)$value).' 85%, black);';
            }
        }
        return $css;
    }

    /** Declarations of one node style (screen keys are skipped here). */
    private function declarations(array $style, ?string $id): string
    {
        $css = '';
        foreach($style as $property => $value){
            $property = strtolower((string)$property);
            if(isset(self::SCREENS[$property])){ continue; }
            if(!self::isProperty($property)){
                $this->issue('style_property', 'Style property "'.$property.'" is not allowed', $id);
                continue;
            }
            $value = is_scalar($value) ? trim((string)$value) : '';
            $allowed = $value !== '' && $this->isSafe($value, $property === 'background-image')
                && ($property !== 'display' || in_array(strtolower($value), self::DISPLAY, true));
            if(!$allowed){
                $this->issue('style_value', 'Value of style property "'.$property.'" is not allowed', $id);
                continue;
            }
            // file URLs of background images (stored ./api/… or legacy ?file=): the absolute file endpoints (plan 34)
            $css .= $property.':'.($property === 'background-image' ? FileUrls::resolveCss($value, '', $this->baseUrl) : $value).';';
            // a colour of an element is the colour of its headings too (they take --h-site-heading-color, 2026-11-14)
            if($property === 'color'){ $css .= '--h-site-heading-color:'.$value.';'; }
        }
        return $css;
    }

    /** Whether a property may be written. */
    public static function isProperty(string $property): bool
    {
        return in_array($property, self::PROPERTIES, true)
            || (bool)preg_match('/^(?:margin|padding)(?:-(?:top|right|bottom|left))?$/', $property)
            || (bool)preg_match('/^border(?:-(?:top|right|bottom|left))?(?:-(?:width|style|color))?$/', $property);
    }

    /** A value: safe CSS; url() only in background-image, relative or from Heurist. */
    private function isSafe(string $value, bool $urlAllowed): bool
    {
        if(!StoredCss::isSafeValue($value)){ return false; }
        if(!preg_match_all('/url\(\s*([\'"]?)(.*?)\1\s*\)/i', $value, $urls)){ return true; }
        if(!$urlAllowed){ return false; }
        foreach($urls[2] as $url){
            $url = trim($url);
            $relative = parse_url($url, PHP_URL_SCHEME) === null && strpos($url, '//') !== 0;
            if(!$relative && ($this->baseUrl === '' || strpos($url, $this->baseUrl) !== 0)){ return false; }
        }
        return true;
    }

    private function issue(string $code, string $message, ?string $node): void
    {
        $this->issues[] = array('level' => 'warning', 'code' => $code, 'message' => $message, 'node' => $node);
    }
}
