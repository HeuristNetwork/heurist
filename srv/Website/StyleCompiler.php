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

use Heurist\Utilities\StoredCss;

/** Compiles base settings and node styles into one stylesheet. */
final class StyleCompiler
{
    /** Base settings (key = CSS variable without "--h-"). */
    public const BASE = array(
        'font-family', 'color-text', 'color-bg', 'color-surface', 'color-primary', 'color-link',
        'color-border', 'radius', 'site-font-size', 'site-heading-font', 'site-heading-color', 'site-width'
    );

    /** Screen sizes: key in a node style => max width. */
    public const SCREENS = array('medium' => 1024, 'narrow' => 640);

    /** Node style properties (plan 27a §4); margin, padding and border by pattern. */
    private const PROPERTIES = array(
        'display', 'flex-direction', 'flex-wrap', 'justify-content', 'align-items', 'gap', 'grid-template-columns',
        'grid-template-rows', 'grid-column', 'grid-row', 'order',
        'width', 'min-width', 'max-width', 'height', 'min-height', 'max-height', 'overflow',
        'border-radius', 'box-shadow',
        'color', 'background-color', 'background-image', 'font-family', 'font-size', 'font-weight', 'line-height', 'text-align'
    );

    private const DISPLAY = array('block', 'flex', 'grid', 'none');

    /** @var array<int,array{level:string,code:string,message:string,node:?string}> */
    private array $issues = array();
    private string $baseUrl;

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
     * CSS of the base settings and node styles.
     *
     * @param array $base Base settings (website merged with page).
     * @param array<int,array> $nodeSets Node styles, in order (website first, then the page).
     */
    public function compile(array $base, array $nodeSets): string
    {
        $this->issues = array();
        $css = '';
        $variables = $this->variables($base);
        if($variables !== ''){ $css .= 'html.h-site{'.$variables."}\n"; }
        $screens = array_fill_keys(array_keys(self::SCREENS), '');
        foreach($nodeSets as $nodes){
            if(!is_array($nodes)){ continue; }
            foreach($nodes as $id => $style){
                $id = (string)$id;
                if(!preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $id) || !is_array($style)){
                    $this->issue('style_node', 'Node style "'.$id.'" is not valid', $id);
                    continue;
                }
                $selector = '[data-h-node="'.$id.'"]';
                $rules = $this->declarations($style, $id);
                if($rules !== ''){ $css .= $selector.'{'.$rules."}\n"; }
                foreach(array_keys(self::SCREENS) as $screen){
                    if(isset($style[$screen]) && is_array($style[$screen])){
                        $rules = $this->declarations($style[$screen], $id);
                        if($rules !== ''){ $screens[$screen] .= $selector.'{'.$rules."}\n"; }
                    }
                }
            }
        }
        // medium first, then narrow: the smaller screen wins
        foreach(self::SCREENS as $screen => $width){
            if($screens[$screen] !== ''){
                $css .= '@media (max-width:'.$width."px){\n".$screens[$screen]."}\n";
            }
        }
        return $css;
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
    private function declarations(array $style, string $id): string
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
            $css .= $property.':'.$value.';';
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
