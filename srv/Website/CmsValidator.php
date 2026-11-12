<?php
/**
* CmsValidator.php - Checks of the website format v4 (plan 27a §7)
*
* Used on save (RecordValidator record check: JSON errors block the save, the
* rest are warnings) and by the renderer (a problem in one instance shows a
* message in its host; the rest of the page renders). Nothing is guessed or
* removed from the stored record.
*
* The same cases are checked by shared/src/site/cmsDocument.js in the client
* (heurist-explorer); both run the fixtures of shared/test/fixtures/cms-v4.
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

/** Checks the configuration JSON, the page HTML and the translations of a v4 page. */
final class CmsValidator
{
    public const SCHEMA = 'heurist-cms/4';

    /** Known instance types by kind, and the newest configuration version of each kind. */
    public const TYPES = array(
        'module' => array('explorer', 'data', 'map', 'timeline', 'graph', 'recordview'),
        'widget' => array('menu', 'report', 'cards', 'gallery', 'language', 'actionmenu', 'badges', 'email')
    );
    public const CONFIG_VERSION = 1;

    /** Website layout (`layout`, plan 27f §2-3): allowed values of each setting; the first is the default (CMS_LAYOUT). */
    public const LAYOUT = array(
        'navigation' => array('reload', 'swap', 'scroll'),
        // fixed is the default since 2026-11-09 (was `default` = scroll); slide = an expand / collapse button (§13)
        'header' => array('position' => array('fixed', 'scroll', 'slide', 'none'), 'orientation' => array('horizontal', 'vertical'),
            // the header default set: one row on normal screens, or two (plan 27f §13)
            'rows' => array('auto', 'two'), 'theme' => array('default', 'surface', 'primary', 'selected', 'own')),
        'footer' => array('position' => array('default', 'fixed', 'none'), 'theme' => array('default', 'surface', 'primary', 'selected', 'own'))
    );
    /** A colour of the own theme of the header or footer (`colors`: bg, text), as CMS_LAYOUT_COLOR. */
    public const LAYOUT_COLOR = '/^(?:#[0-9a-f]{3,8}|[a-z]{3,20}|(?:rgb|hsl)a?\([0-9\s.,%deg\/]+\))$/i';
    /** Header height / width and footer height (`layout.header.height|width`, `layout.footer.height`; review 2026-11-10),
     *  as CMS_LAYOUT_SIZE: a number with px, em, rem, vh, vw or %. */
    public const LAYOUT_SIZE = '/^\d{1,4}(?:\.\d{1,2})?(?:px|em|rem|vh|vw|%)$/';
    /** The layout settings that are sizes (LAYOUT_SIZE). */
    public const LAYOUT_SIZES = array('header' => array('height', 'width'), 'footer' => array('height'));
    /** Pages kept by the navigation mode swap (`layout.cache`). */
    public const LAYOUT_CACHE = array('default' => 8, 'min' => 1, 'max' => 50);

    /**
     * Parse the configuration JSON (2-1187). An empty value is an empty configuration.
     *
     * @return array{config:array,issues:array} The configuration (empty parts when it is not valid).
     */
    public static function parse(string $json): array
    {
        $empty = array('schema' => self::SCHEMA, 'instances' => array(), 'styles' => array(), 'media' => array());
        if(trim($json) === ''){ return array('config' => $empty, 'issues' => array()); }
        $config = json_decode($json, true);
        if(!is_array($config) || json_last_error() !== JSON_ERROR_NONE || (isset($config[0]) && !isset($config['schema']))){
            return array('config' => $empty, 'issues' => array(self::issue('error', 'cms_json',
                'The website configuration is not a valid JSON object')));
        }
        if(($config['schema'] ?? null) !== self::SCHEMA){
            return array('config' => $empty, 'issues' => array(self::issue('error', 'cms_schema',
                'The website configuration is not "'.self::SCHEMA.'"')));
        }
        $issues = array();
        if(isset($config['layout']) && !is_array($config['layout'])){
            $issues[] = self::issue('error', 'cms_json', 'The "layout" of the website configuration is not an object');
            unset($config['layout']);
        }
        foreach(array('instances', 'styles', 'media') as $part){
            if(isset($config[$part]) && !is_array($config[$part])){
                $issues[] = self::issue('error', 'cms_json', 'The "'.$part.'" of the website configuration is not an object');
                $config[$part] = array();
            }
        }
        $config['instances'] = $config['instances'] ?? array();
        $config['styles'] = $config['styles'] ?? array();
        $config['media'] = $config['media'] ?? array();
        return array('config' => $config, 'issues' => $issues);
    }

    /**
     * All checks of one page.
     *
     * @param array $config Parsed configuration (parse()).
     * @param array<int,string> $htmlParts Page HTML, and header / footer for a website.
     * @param array<string,array> $translations Language => {node id: html}.
     * @param string $baseUrl Heurist base URL (background images).
     * @param array<int,string> $otherInstances Instance ids configured elsewhere (website header / footer).
     * @return array<int,array{level:string,code:string,message:string,node:?string}>
     */
    public static function check(array $config, array $htmlParts, array $translations = array(), string $baseUrl = '',
        array $otherInstances = array()): array
    {
        $issues = array();
        $nodes = array();
        foreach($htmlParts as $html){
            foreach(CmsHtml::nodes((string)$html) as $node){ $nodes[] = $node; }
        }
        $seenNodes = array();
        $seenHosts = array();
        $seenMedia = array();
        $media = is_array($config['media'] ?? null) ? $config['media'] : array();
        $texts = array();
        foreach($nodes as $node){
            $id = $node['node'];
            if($id !== ''){
                if(isset($seenNodes[$id])){
                    $issues[] = self::issue('warning', 'duplicate_node', 'Node id "'.$id.'" is used more than once', $id);
                }
                $seenNodes[$id] = true;
                // the value of a field comes from its record, it has no translations here (plan 27e)
                if($node['text'] && $node['field'] === null){ $texts[$id] = true; }
            }
            if($node['field'] !== null && preg_match('/^(website|page|record|\d+)\.\d+(-\d+)?$/', $node['field']) !== 1){
                $issues[] = self::issue('warning', 'field_reference', 'Field reference "'.$node['field'].'" is not valid', $id);
            }
            // a media element (plan 27c §33): its settings in config.media, one element each
            if($node['media'] !== null){
                if(isset($seenMedia[$node['media']])){
                    $issues[] = self::issue('warning', 'duplicate_media', 'Media "'.$node['media'].'" has more than one element', $id);
                }elseif(!isset($media[$node['media']])){
                    $issues[] = self::issue('warning', 'media_without_config', 'Media "'.$node['media'].'" has no settings', $id);
                }
                $seenMedia[$node['media']] = true;
            }
            $instance = $node['instance'];
            if($instance === null){ continue; }
            if(isset($seenHosts[$instance])){
                $issues[] = self::issue('warning', 'duplicate_instance', 'Instance "'.$instance.'" has more than one host', $id);
                continue;
            }
            $seenHosts[$instance] = true;
            if(!isset($config['instances'][$instance]) && !in_array($instance, $otherInstances, true)){
                $issues[] = self::issue('warning', 'host_without_config', 'Instance "'.$instance.'" is not configured', $id);
            }
        }
        foreach($config['instances'] as $instance => $settings){
            $instance = (string)$instance;
            if(!isset($seenHosts[$instance])){
                $issues[] = self::issue('warning', 'config_without_host', 'Instance "'.$instance.'" has no host in the page');
            }
            $problem = self::instanceProblem(is_array($settings) ? $settings : array());
            if($problem !== null){
                $issues[] = self::issue('warning', $problem[0], 'Instance "'.$instance.'": '.$problem[1], null);
            }
        }
        foreach($media as $mediaId => $settings){
            $mediaId = (string)$mediaId;
            if(!isset($seenMedia[$mediaId])){
                $issues[] = self::issue('warning', 'media_without_element', 'Media "'.$mediaId.'" has no element in the page');
            }
            $problem = self::mediaProblem(is_array($settings) ? $settings : array());
            if($problem !== null){
                $issues[] = self::issue('warning', 'media_settings', 'Media "'.$mediaId.'": '.$problem, null);
            }
        }
        foreach(self::layoutProblems(is_array($config['layout'] ?? null) ? $config['layout'] : array()) as $problem){
            $issues[] = self::issue('warning', 'layout', 'Layout: '.$problem);
        }
        $compiler = new StyleCompiler($baseUrl);
        $styles = $config['styles'];
        $compiler->compile(is_array($styles['base'] ?? null) ? $styles['base'] : array(),
            array(is_array($styles['nodes'] ?? null) ? $styles['nodes'] : array()),
            array(is_array($styles['kinds'] ?? null) ? $styles['kinds'] : array()),
            array(is_array($styles['presets'] ?? null) ? $styles['presets'] : array()));
        $issues = array_merge($issues, $compiler->issues());
        foreach($translations as $language => $blocks){
            foreach(array_keys(is_array($blocks) ? $blocks : array()) as $id){
                if(!isset($texts[(string)$id])){
                    $issues[] = self::issue('warning', 'translation_orphan',
                        'Translation "'.$language.'" of "'.$id.'": the page has no text block with this id', (string)$id);
                }
            }
            foreach(array_keys($texts) as $id){
                if(!isset($blocks[$id])){
                    $issues[] = self::issue('info', 'translation_missing', 'Text block "'.$id.'" is not translated into "'.$language.'"', $id);
                }
            }
        }
        return $issues;
    }

    /**
     * The Record cards widget of a record page (plan 27g §2.2): the first cards widget in mode `url` (the record of the
     * page URL). A page with it is a record page.
     *
     * @param array $config Parsed configuration of the page (parse()['config']).
     * @return array|null Settings of the widget, or null: not a record page.
     */
    public static function recordCards(array $config): ?array
    {
        foreach(is_array($config['instances'] ?? null) ? $config['instances'] : array() as $instance){
            if(is_array($instance) && ($instance['kind'] ?? '') === 'widget' && ($instance['type'] ?? '') === 'cards'
                && is_array($instance['settings'] ?? null) && ($instance['settings']['mode'] ?? '') === 'url'){
                return $instance['settings'];
            }
        }
        return null;
    }

    /**
     * Why an instance can not be mounted, or null.
     *
     * @return array{0:string,1:string}|null Issue code and message.
     */
    public static function instanceProblem(array $settings): ?array
    {
        $kind = (string)($settings['kind'] ?? '');
        $type = (string)($settings['type'] ?? '');
        if(!isset(self::TYPES[$kind]) || !in_array($type, self::TYPES[$kind], true)){
            return array('unknown_type', 'unknown kind / type "'.$kind.'" / "'.$type.'"');
        }
        if(intval($settings['configVersion'] ?? 1) > self::CONFIG_VERSION){
            return array('config_version', 'configuration version '.intval($settings['configVersion']).' is newer than this Heurist');
        }
        if(isset($settings['mount']) && !in_array($settings['mount'], array('direct', 'iframe'), true)){
            return array('mount', 'mount must be "direct" or "iframe"');
        }
        return null;
    }

    /** A size of the header or footer (LAYOUT_SIZE). */
    public static function isLayoutSize($value): bool
    {
        return is_string($value) && preg_match(self::LAYOUT_SIZE, trim($value)) === 1;
    }

    /** Own colours of the header or footer: {bg, text}, each a colour (LAYOUT_COLOR). */
    public static function isLayoutColors($colors): bool
    {
        if(!is_array($colors) || (!empty($colors) && array_keys($colors) === range(0, count($colors) - 1))){ return false; }
        foreach($colors as $part => $colour){
            if(!in_array((string)$part, array('bg', 'text'), true) || !is_string($colour) || !preg_match(self::LAYOUT_COLOR, trim($colour))){
                return false;
            }
        }
        return true;
    }

    /**
     * Problems of the layout settings (cmsLayoutProblems of cmsDocument.js): unknown keys and values not in LAYOUT;
     * `cache` a whole number in LAYOUT_CACHE; `hideHeader` / `hideFooter` (a page) true or false.
     *
     * @return array<int,string>
     */
    public static function layoutProblems(array $layout): array
    {
        $problems = array();
        foreach($layout as $key => $value){
            $key = (string)$key;
            if($key === 'navigation'){
                if(!in_array($value, self::LAYOUT['navigation'], true)){
                    $problems[] = 'navigation must be '.implode(', ', self::LAYOUT['navigation']);
                }
            }elseif($key === 'cache'){
                if(!is_int($value) || $value < self::LAYOUT_CACHE['min'] || $value > self::LAYOUT_CACHE['max']){
                    $problems[] = 'cache must be a number from '.self::LAYOUT_CACHE['min'].' to '.self::LAYOUT_CACHE['max'];
                }
            }elseif($key === 'hideHeader' || $key === 'hideFooter'){
                if(!is_bool($value)){ $problems[] = $key.' must be true or false'; }
            }elseif($key === 'header' || $key === 'footer'){
                if(!is_array($value) || (!empty($value) && array_keys($value) === range(0, count($value) - 1))){
                    $problems[] = $key.' must be an object';
                    continue;
                }
                foreach($value as $name => $item){
                    if((string)$name === 'colors'){
                        if(!self::isLayoutColors($item)){ $problems[] = $key.'.colors must be {bg, text} colours'; }
                        continue;
                    }
                    if(in_array((string)$name, self::LAYOUT_SIZES[$key], true)){
                        if(!self::isLayoutSize($item)){ $problems[] = $key.'.'.$name.' must be a size such as 120px, 10em or 20%'; }
                        continue;
                    }
                    $allowed = self::LAYOUT[$key][(string)$name] ?? null;
                    if($allowed === null){
                        $problems[] = 'unknown setting '.$key.'.'.$name;
                    }elseif(!in_array($item, $allowed, true)){
                        $problems[] = $key.'.'.$name.' must be '.implode(', ', $allowed);
                    }
                }
            }else{
                $problems[] = 'unknown setting '.$key;
            }
        }
        return $problems;
    }

    /**
     * Why the settings of a media element are not valid, or null: `file` the key of a registered file,
     * `width` / `height` 'auto', px or %, `action` none or viewer; `playerWidth` / `playerHeight` / `player` of older pages
     * (as cmsMediaProblem of cmsDocument.js).
     */
    public static function mediaProblem(array $settings): ?string
    {
        if(!preg_match('/^[A-Za-z0-9]+$/', (string)($settings['file'] ?? ''))){ return 'the file is missing'; }
        foreach(array('width', 'height', 'playerWidth', 'playerHeight') as $name){
            $value = (string)($settings[$name] ?? 'auto');
            if($value !== 'auto' && !preg_match('/^(?:\d+\.?\d*|\.\d+)(?:px|%)$/', $value)){
                return $name.' must be auto, px or %';
            }
        }
        if(isset($settings['player']) && !is_bool($settings['player'])){ return 'player must be true or false'; }
        if(isset($settings['action']) && !in_array($settings['action'], array('none', 'viewer'), true)){ return 'action must be none or viewer'; }
        return null;
    }

    /** Translations of a record: the "xx:{json}" values of 2-1202 by language (3 letters, lower case). */
    public static function translations(array $values): array
    {
        $result = array();
        foreach($values as $value){
            $value = is_array($value) ? (string)($value['value'] ?? '') : (string)$value;
            if(!preg_match('/^([A-Za-z]{2,3}):(.*)$/s', $value, $match)){ continue; }
            $blocks = json_decode($match[2], true);
            if(is_array($blocks)){ $result[strtolower($match[1])] = $blocks; }
        }
        return $result;
    }

    /** Whether the issues include an error (a save is refused). */
    public static function hasError(array $issues): bool
    {
        foreach($issues as $issue){
            if(($issue['level'] ?? '') === 'error'){ return true; }
        }
        return false;
    }

    private static function issue(string $level, string $code, string $message, ?string $node = null): array
    {
        return array('level' => $level, 'code' => $code, 'message' => $message, 'node' => $node);
    }
}
