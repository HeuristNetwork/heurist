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
        // storymap: a Presentation set of kind StoryMap (plan 27q1)
        'widget' => array('menu', 'report', 'cards', 'gallery', 'language', 'actionmenu', 'badges', 'email', 'breadcrumbs', 'filter', 'storymap')
    );
    public const CONFIG_VERSION = 1;

    /** Page sync of a module instance (plan 27l §2.4; SYNC_MODES of shared/src/sync/SyncEngine.js). */
    public const SYNC_MODES = array('all', 'selection', 'none');
    /** A source of the records of a module (plan 27l §12; CMS_RECORDS_TOKEN of cmsDocument.js). */
    public const RECORDS_TOKEN = '/^(own|links|filter:[A-Za-z0-9_-]+|module:[A-Za-z0-9_-]+)$/';
    /** Record modules with their own records on a page (plan 27l §11.3; CMS_SOURCE_MODULES of cmsDocument.js). */
    public const SOURCE_MODULES = array('data', 'map', 'timeline', 'graph', 'recordview');
    /** Actions of a media element (CMS_MEDIA_ACTIONS of cmsDocument.js). */
    public const MEDIA_ACTIONS = array('none', 'viewer', 'url', 'recordPage', 'page', 'savedFilter');

    /** Website layout (`layout`, plan 27f §2-3): allowed values of each setting; the first is the default (CMS_LAYOUT). */
    public const LAYOUT = array(
        'navigation' => array('reload', 'swap', 'scroll'),
        // fixed is the default since 2026-11-09 (was `default` = scroll); slide = an expand / collapse button (§13)
        'header' => array('position' => array('fixed', 'scroll', 'slide', 'none'), 'orientation' => array('horizontal', 'vertical'),
            // a fixed horizontal header when the page scrolls (plan 27k §4): as it is, or small (logo, menu, languages)
            'onScroll' => array('keep', 'shrink'),
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

    /** An image of assets/cms of the website bundle: placeholders of media elements (plan 27i §9, CMS_ASSET_NAME). */
    /** Name of an element (plan 27n §9, `data-h-name`; CMS_NAME of cmsDocument.js). */
    public const NAME = '/^[A-Za-z0-9_-]{1,64}$/';

    /** Colours of menus, action menus and tabs (plan 27n §7; ITEM_COLORS of cmsMenu.js): none = the colours of the site. */
    public const ITEM_COLORS = array('inverted', 'own');

    public const ASSET_NAME = '/^cms\/(?:[A-Za-z0-9_-]+\/)?[A-Za-z0-9_-]+\.(?:png|jpe?g)$/';

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
        $seenNames = array();
        $seenHosts = array();
        $seenMedia = array();
        $media = is_array($config['media'] ?? null) ? $config['media'] : array();
        $texts = array();
        foreach($nodes as $node){
            $id = $node['node'];
            // the name of an element (plan 27n §9): letters, digits, _ and -, unique
            if($node['name'] !== null){
                if(preg_match(self::NAME, $node['name']) !== 1){
                    $issues[] = self::issue('warning', 'element_name', 'Name "'.$node['name'].'" may contain only letters, digits, _ and -', $id !== '' ? $id : null);
                }elseif(isset($seenNames[$node['name']])){
                    $issues[] = self::issue('warning', 'duplicate_name', 'Name "'.$node['name'].'" is used more than once', $id !== '' ? $id : null);
                }
                $seenNames[$node['name']] = true;
            }
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
                }elseif(self::isPlaceholder($media[$node['media']])){
                    // a placeholder image of a page template stands in for the file (2026-11-17)
                    $issues[] = self::issue('warning', 'media_placeholder', 'Replace placeholder image', $id);
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
            array(is_array($styles['presets'] ?? null) ? $styles['presets'] : array()), '',
            is_array($styles['page'] ?? null) ? $styles['page'] : array(), 0,
            array('header' => is_array($styles['header'] ?? null) ? $styles['header'] : array(),
                'footer' => is_array($styles['footer'] ?? null) ? $styles['footer'] : array()));
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
        // the name of the element of an issue (plan 27n §9): the Check report shows `name (node id)`
        $names = array();
        foreach($nodes as $node){
            if($node['name'] !== null && $node['node'] !== ''){ $names[$node['node']] = $node['name']; }
        }
        foreach($issues as $index => $issue){
            if($issue['node'] !== null && isset($names[$issue['node']])){ $issues[$index]['name'] = $names[$issue['node']]; }
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
        // page sync of a module (plan 27l §2.4)
        if(isset($settings['sync']) && !in_array($settings['sync'], self::SYNC_MODES, true)){
            return array('sync', 'sync must be "all", "selection" or "none"');
        }
        // the own records of a module (plan 27l §11.3)
        if(isset($settings['dataSource'])
            && (!in_array($type, self::SOURCE_MODULES, true) || self::instanceDataSource($settings['dataSource'], $type) === null)){
            return array('data_source', 'dataSource must be {source}, {filter}, {query} without parameters or {record} (Record View)');
        }
        // records and selection (plan 27l §12)
        if((isset($settings['records']) || isset($settings['selection'])) && ($kind !== 'module' || !self::recordsValid($settings))){
            return array('records', 'records must be {from: [own | links | filter:<id> | module:<id>]} and selection true or false');
        }
        return null;
    }

    /** Whether `records` / `selection` of an instance have the stored form (plan 27l §12; cmsRecordsValid of cmsDocument.js). */
    public static function recordsValid(array $settings): bool
    {
        if(isset($settings['records'])){
            $from = is_array($settings['records']) ? ($settings['records']['from'] ?? null) : null;
            if(!is_array($from) || isset($settings['records'][0])){ return false; }
            foreach($from as $token){
                if(!is_string($token) || preg_match(self::RECORDS_TOKEN, $token) !== 1){ return false; }
            }
        }
        return !isset($settings['selection']) || is_bool($settings['selection']);
    }

    /**
     * The own records of a module that does not take the results of the page (plan 27l §11.3; cmsInstanceDataSource of
     * cmsDocument.js): {source: id} | {filter: id} | {query: [...]} without parameters | {record: id} (Record View).
     *
     * @param mixed $value The stored `dataSource`.
     * @param string $type Module type.
     * @return array|null
     */
    public static function instanceDataSource($value, string $type = ''): ?array
    {
        if(!is_array($value) || count($value) !== 1 || isset($value[0])){ return null; }
        $key = (string)array_key_first($value);
        if(in_array($key, array('source', 'filter'), true) || ($key === 'record' && $type === 'recordview')){
            $id = $value[$key];
            return (is_int($id) || (is_string($id) && ctype_digit($id))) && intval($id) > 0 ? array($key => intval($id)) : null;
        }
        if($key !== 'query'){ return null; }
        $query = $value['query'];
        if(!is_array($query) || empty($query)){ return null; }
        if(array_keys($query) !== range(0, count($query) - 1)){ $query = array($query); }
        if(preg_match('/\$[A-Za-z][A-Za-z0-9_]*\$/', (string)json_encode($query)) === 1){ return null; }
        return array('query' => $query);
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
     * `cache` a whole number in LAYOUT_CACHE; `hideHeader` / `hideFooter` (a page), `backToTop` (the button of plan
     * 27k §6) and `searchEngines` (plan 27m §5: false = noindex, not given = index) true or false; `startPage` the id of
     * the page shown by the website URL without a page (2026-11-14; not given: the home page).
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
            }elseif($key === 'startPage'){
                if(!is_int($value) || $value < 1){ $problems[] = 'startPage must be a page id'; }
            }elseif($key === 'hideHeader' || $key === 'hideFooter' || $key === 'backToTop' || $key === 'searchEngines'){
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

    /** A media element that shows a placeholder image of assets/cms instead of a file (as cmsIsPlaceholder). */
    public static function isPlaceholder($settings): bool
    {
        return is_array($settings) && is_string($settings['placeholder'] ?? null)
            && preg_match(self::ASSET_NAME, $settings['placeholder']) === 1
            && !preg_match('/^[A-Za-z0-9]+$/', (string)($settings['file'] ?? ''));
    }

    /**
     * Why the settings of a media element are not valid, or null: `file` the key of a registered file (or `placeholder`,
     * an image of assets/cms of the website bundle),
     * `width` / `height` 'auto', px or %, `action` none or viewer; `playerWidth` / `playerHeight` / `player` of older pages
     * (as cmsMediaProblem of cmsDocument.js).
     */
    public static function mediaProblem(array $settings): ?string
    {
        // a placeholder image of the website bundle stands in for the file until one is chosen (plan 27i §9)
        $placeholder = $settings['placeholder'] ?? null;
        if($placeholder !== null && (!is_string($placeholder) || !preg_match(self::ASSET_NAME, $placeholder))){
            return 'placeholder is not an image of assets/cms';
        }
        if(!preg_match('/^[A-Za-z0-9]+$/', (string)($settings['file'] ?? '')) && empty($placeholder)){ return 'the file is missing'; }
        foreach(array('width', 'height', 'playerWidth', 'playerHeight') as $name){
            $value = (string)($settings[$name] ?? 'auto');
            if($value !== 'auto' && !preg_match('/^(?:\d+\.?\d*|\.\d+)(?:px|%)$/', $value)){
                return $name.' must be auto, px or %';
            }
        }
        if(isset($settings['player']) && !is_bool($settings['player'])){ return 'player must be true or false'; }
        // the links of a record (plan 27g §6) and Go to a page (2026-11-14)
        if(isset($settings['action']) && !in_array($settings['action'], self::MEDIA_ACTIONS, true)){
            return 'action must be '.implode(', ', self::MEDIA_ACTIONS);
        }
        if(($settings['action'] ?? '') === 'page' && intval($settings['page'] ?? 0) < 1){ return 'page must be a page id'; }
        // Saved filter (plan 27j): a query source, the target page, the values within the limits
        if(($settings['action'] ?? '') === 'savedFilter'){
            $problem = SavedFilterLink::problem($settings);
            if($problem !== null){ return $problem; }
        }
        if(isset($settings['align']) && !in_array($settings['align'], array('left', 'center', 'right'), true)){
            return 'align must be left, center or right';
        }
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
