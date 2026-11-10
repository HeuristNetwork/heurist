<?php
/**
* PageRenderer.php - Writes the HTML document of a v4 website page (plan 27a §5)
*
* The document: title and keywords; the website bundle CSS (heurist-ui.css and
* the site styles); one <style> with the base settings and node styles
* (StyleCompiler), the page styles below its section (plan 27f); the custom CSS (99-946) of the website
* (a standalone page: its own; pages of a website have none, plan 27f §9.3), only with isJavaScriptAllowed;
* header, page content and footer with the texts of the current language;
* the instance configuration in <script type="application/json" id="h-site-config">;
* the site runtime (apps/website in heurist-explorer), which mounts the modules
* directly. External files and custom JavaScript only with the CMS code
* exception (isJavaScriptAllowed). No session data is written into the page.
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

use Heurist\Utilities\LanguageCodes;

/** HTML of a page context (WebsiteService::context). */
final class PageRenderer
{
    /** Bundle of the site runtime below the Heurist base URL. */
    public const BUNDLE = 'hclient/bundles/heurist-website/';

    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

    private array $codes;
    private LanguageCodes $languages;
    /** @var array{database:string,baseUrl:string,javaScriptAllowed:bool,urls:SiteUrls} */
    private array $environment;
    private ?PageContent $content;

    /**
     * @param array<string,int> $codes WebsiteRepository::codes().
     * @param array $environment database, baseUrl, javaScriptAllowed, urls (SiteUrls).
     * @param PageContent|null $content Field values and widgets (plan 27e); null: the stored HTML as it is.
     */
    public function __construct(array $codes, LanguageCodes $languages, array $environment, ?PageContent $content = null)
    {
        $this->codes = $codes;
        $this->languages = $languages;
        $this->environment = $environment;
        $this->content = $content;
    }

    /** The HTML document of a page. */
    public function render(array $context): string
    {
        $site = $context['site'];
        $page = $context['page'];
        $language = $context['language'];
        $translate = $language !== null && $language !== ($context['languages'][0] ?? null);
        $textKey = strtolower((string)$language);
        $siteTexts = $translate ? ($context['siteTexts'][$textKey] ?? array()) : array();
        $pageTexts = $translate ? ($context['pageTexts'][$textKey] ?? array()) : array();
        $baseUrl = $this->environment['baseUrl'];

        $siteConfig = $context['siteConfig'] ?? array('instances' => array(), 'styles' => array());
        $pageConfig = $context['pageConfig'];
        $pageId = intval($page['rec_ID']);
        // a page of a website: the website styles, and the page styles below its section (plan 27f §2.2);
        // the home page and a standalone page have one set (the website record / the page)
        $withSite = $site !== null && !$context['home'];
        $own = $withSite ? $siteConfig : $pageConfig;
        $styles = (new StyleCompiler($baseUrl))->compile(self::part($own, 'base'), array(self::part($own, 'nodes')),
            array(self::part($own, 'kinds')), array(self::part($own, 'presets')));
        $pageStyles = $withSite ? (new StyleCompiler($baseUrl))->compile(self::part($pageConfig, 'base'),
            array(self::part($pageConfig, 'nodes')), array(self::part($pageConfig, 'kinds')), array(self::part($pageConfig, 'presets')),
            self::pageScope($pageId)) : '';

        $content = CmsHtml::translate(WebsiteRepository::value($page, $this->codes['content']), $pageTexts);
        $header = $site !== null ? CmsHtml::translate(WebsiteRepository::value($site, $this->codes['header']), $siteTexts) : '';
        $footer = $site !== null ? CmsHtml::translate(WebsiteRepository::value($site, $this->codes['footer']), $siteTexts) : '';
        $content = $this->fill($content, $context);
        $header = $this->fill($header, $context);
        $footer = $this->fill($footer, $context);
        // plan 27f §3: no header / footer at all, or classes of their position (the hide flags of a page are
        // classes too: the navigation modes keep one header and footer for all pages)
        $layout = $this->layout($context);
        if(($layout['header']['position'] ?? '') === 'none'){ $header = ''; }
        if(($layout['footer']['position'] ?? '') === 'none'){ $footer = ''; }
        $bodyClass = self::bodyClasses($layout);
        // the banner image of the website and own colours of header / footer (plan 27f §13): a block of its own, the
        // preview of the designer replaces h-site-styles with the draft styles and keeps this one
        $layoutCss = self::layoutCss($layout, $site !== null ? $this->fileUrlOf($site, intval($this->codes['banner'] ?? 0)) : '');

        $title = $this->text($page, $this->codes['name'], $language);
        $siteTitle = $site !== null ? $this->text($site, $this->codes['name'], $language) : '';
        $documentTitle = $site !== null && !$context['home'] && $siteTitle !== '' ? $title.' – '.$siteTitle : ($title ?: $siteTitle);
        $keywords = trim(strip_tags(WebsiteRepository::value($page, $this->codes['keywords'])));

        $html = '<!doctype html><html lang="'.self::attr($this->languages->code2($language ?? '') ?? 'en').'" class="h-site'
            .(!empty($context['design']) ? ' h-site-design' : '').'">'
            .'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>'.self::text2html($documentTitle).'</title>'
            .($keywords !== '' ? '<meta name="keywords" content="'.self::attr($keywords).'">' : '')
            .$this->alternateLinks($context)
            .'<link rel="stylesheet" href="'.self::attr($baseUrl.self::BUNDLE.'heurist-website-main.css').'">'
            .'<style id="h-site-styles">'."\n".$styles.'</style>'
            .($pageStyles !== '' ? '<style id="h-page-styles" data-h-page="'.$pageId.'">'."\n".$pageStyles.'</style>' : '')
            .($layoutCss !== '' ? '<style id="h-site-layout">'."\n".$layoutCss.'</style>' : '')
            .$this->customCss($site ?? $page)
            .$this->externalFiles($site ?? $page)
            .'</head><body class="'.self::attr($bodyClass).'">'
            .($header !== '' ? '<header class="h-site-header">'.$header.'</header>' : '')
            .'<main class="h-site-main"><section class="h-site-page" data-h-page="'.$pageId.'">'.$content.'</section></main>'
            .($footer !== '' ? '<footer class="h-site-footer">'.$footer.'</footer>' : '')
            .'<script type="application/json" id="h-site-config">'.$this->configJson($context).'</script>'
            .'<script type="module" src="'.self::attr($baseUrl.self::BUNDLE.'heurist-website.js').'"></script>'
            .$this->customScript($site ?? $page)
            .'</body></html>';
        return $html;
    }

    /**
     * The page of one instance in an iframe ("mount": "iframe"): the module bundle with
     * its bootstrap (the page of the former module publications).
     */
    public function frame(array $context, string $instanceId): string
    {
        $instance = $this->instances($context)[$instanceId] ?? null;
        if($instance === null || ($instance['kind'] ?? '') !== 'module' || isset($instance['problem'])){
            throw new WebsiteException(404, 'Instance '.$instanceId.' is not a module of this page');
        }
        $type = (string)$instance['type'];
        $bootstrap = json_encode(array('runtime' => $this->runtime($context), 'settings' => $instance['settings'] ?? array(),
            'state' => $instance['state'] ?? null), self::JSON_FLAGS);
        $module = 'heurist-'.$type;
        $assets = $this->environment['baseUrl'].'hclient/bundles/'.$module.'/';
        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>'.self::text2html($module).'</title>'
            .'<link rel="stylesheet" href="'.self::attr($assets.$module.'-main.css').'">'
            .'<style>html,body,#'.$module.'{width:100%;height:100%;margin:0;padding:0;overflow:hidden}</style>'
            .'</head><body><div id="'.$module.'"></div>'
            .'<script>window.heuristModuleBootstrap='.$bootstrap.';</script>'
            .'<script type="module" src="'.self::attr($assets.$module.'.js').'"></script></body></html>';
    }

    /** Field values, widgets and the menu of one part of the page, written by the server (plan 27e). */
    private function fill(string $html, array $context): string
    {
        $instances = self::instanceList($context['pageConfig']) + self::instanceList($context['siteConfig'] ?? null);
        return CmsHtml::fill($html, function(string $reference) use ($context): ?string {
            return $this->content !== null ? $this->content->field($reference, $context) : null;
        }, function(string $id) use ($instances, $context): ?array {
            $instance = is_array($instances[$id] ?? null) ? $instances[$id] : array();
            if(($instance['kind'] ?? '') !== 'widget'){ return null; }
            $settings = is_array($instance['settings'] ?? null) ? $instance['settings'] : array();
            if(($instance['type'] ?? '') === 'menu'){ return array('html' => $this->menuHtml($context, $settings)); }
            if(($instance['type'] ?? '') === 'language'){ return $this->languageHtml($context, $settings); }
            if(($instance['type'] ?? '') === 'actionmenu'){ return array('html' => $this->actionMenuHtml($context, $settings)); }
            return $this->content !== null ? $this->content->widget($instance, $context) : null;
        });
    }

    /**
     * The website menu as links (for crawlers and visitors without JS); HMenu activates it. Settings (plan 27f §4):
     * `orientation` horizontal / vertical / auto (follows the header), `showHome` a Home entry first,
     * `selectableParent` (default true) a page with sub pages opens; else its title only opens the sub menu.
     * The languages are a widget of their own (HLangSwitch).
     */
    private function menuHtml(array $context, array $settings = array()): string
    {
        $tree = $this->menu($context, $context['menu']);
        if(!empty($settings['showHome']) && $context['site'] !== null){
            array_unshift($tree, $this->homeItem($context));
        }
        $selectable = ($settings['selectableParent'] ?? true) !== false;
        return '<nav class="'.self::attr(self::menuClass($settings)).'" aria-label="Site menu">'
            .'<button type="button" class="h-site-menu-toggle" aria-expanded="false">Menu</button>'
            .self::menuList($tree, 'h-site-menu-list', $selectable).'</nav>';
    }

    /** The home page as a menu item. */
    private function homeItem(array $context): array
    {
        $siteId = intval($context['site']['rec_ID']);
        return array('id' => $siteId, 'title' => $this->text($context['site'], $this->codes['name'], $context['language']),
            'url' => $this->url($context, $siteId, $context['language']), 'current' => !empty($context['home']),
            'icon' => '', 'format' => 'name', 'children' => array());
    }

    /**
     * The language switch (HLangSwitch, plan 27f §5): links to this page in the languages of the website, the
     * current one marked; HLangSwitch shows a select instead (`appearance` select, or auto when narrow). Empty for
     * a website with one language.
     */
    private function languageHtml(array $context, array $settings): array
    {
        if(count($context['languages']) < 2){ return array('html' => '', 'class' => 'h-site-lang-single'); }
        $appearance = in_array($settings['appearance'] ?? '', array('select', 'list'), true) ? $settings['appearance'] : 'auto';
        $current = strtolower((string)$context['language']);
        $links = '';
        foreach($context['languages'] as $code){
            $lower = strtolower($code);
            $links .= '<li><a href="'.self::attr($this->url($context, intval($context['page']['rec_ID']), $code)).'" hreflang="'.self::attr($lower).'"'
                .($lower === $current ? ' class="is-current" aria-current="true"' : '').'>'
                .self::text2html(strtoupper($this->languages->code2($code) ?? $lower)).'</a></li>';
        }
        return array('html' => '<div class="h-site-lang h-site-lang-'.$appearance.'"><ul class="h-site-lang-list" aria-label="Language">'
            .$links.'</ul></div>');
    }

    /**
     * An action menu (plan 27f §6): items of its settings as links (a page of this website, an external URL) or
     * actions done in the page (Add record: through the Explorer of the page). `presentation` links, buttons, icons
     * or dropdown (`label` on its button).
     */
    private function actionMenuHtml(array $context, array $settings): string
    {
        $items = array();
        $language = strtolower((string)($context['language'] ?? ''));
        $current = intval($context['page']['rec_ID']);
        foreach(is_array($settings['items'] ?? null) ? $settings['items'] : array() as $item){
            if(!is_array($item)){ continue; }
            $labels = is_array($item['labels'] ?? null) ? $item['labels'] : array();
            $entry = array('title' => (string)($labels[$language] ?? $item['label'] ?? ''), 'url' => '#', 'current' => false,
                'fa' => self::faClass((string)($item['icon'] ?? '')), 'format' => !empty($item['iconOnly']) ? 'icon' : 'name',
                'newTab' => !empty($item['newTab']), 'children' => array());
            $action = (string)($item['action'] ?? 'url');
            $target = $item['target'] ?? '';
            if($action === 'page'){
                $pageId = intval($target);
                $siteId = $context['site'] !== null ? intval($context['site']['rec_ID']) : 0;
                if($pageId < 1 || ($pageId !== $siteId && !isset($context['pages'][$pageId]))){ continue; }
                $entry['url'] = $this->url($context, $pageId, $context['language']);
                $entry['current'] = $pageId === $current || ($pageId === $siteId && !empty($context['home']));
            }elseif($action === 'url'){
                $url = trim((string)$target);
                if(!preg_match('#^(https?://|mailto:|tel:|/|\./|\.\./|\#)#i', $url)){ continue; }
                $entry['url'] = $url;
            }elseif($action === 'addRecord'){
                $entry['action'] = 'addRecord';
                $entry['target'] = (string)$target;
            }else{
                continue;
            }
            $items[] = $entry;
        }
        $label = trim((string)($settings['labels'][$language] ?? $settings['label'] ?? ''));
        $presentation = self::menuPresentation($settings);
        return '<nav class="'.self::attr(self::menuClass($settings)).'" aria-label="'.self::attr($label !== '' ? $label : 'Actions').'">'
            .($presentation === 'dropdown' ? '<button type="button" class="h-site-menu-toggle" aria-expanded="false">'
                .self::text2html($label !== '' ? $label : 'Menu').'</button>' : '')
            .self::menuList($items, 'h-site-menu-list').'</nav>';
    }

    /** Font Awesome classes of an icon (letters, digits, dashes and spaces only), or ''. */
    private static function faClass(string $icon): string
    {
        $icon = trim($icon);
        return preg_match('/^[a-z0-9 -]{1,80}$/', $icon) === 1 && strpos($icon, 'fa-') !== false ? $icon : '';
    }

    /** Presentation of an action menu: links, buttons, icons, dropdown; `menu` for the website menu. */
    private static function menuPresentation(array $settings): string
    {
        $presentation = (string)($settings['presentation'] ?? 'menu');
        return in_array($presentation, array('links', 'buttons', 'icons', 'dropdown'), true) ? $presentation : 'menu';
    }

    /** Classes of a menu: orientation (plan 27f §4) and the presentation of an action menu (§6); menuClass of HMenu.js. */
    public static function menuClass(array $settings): string
    {
        $orientation = (string)($settings['orientation'] ?? 'horizontal');
        $presentation = self::menuPresentation($settings);
        return 'h-site-menu'.(in_array($orientation, array('vertical', 'auto'), true) ? ' h-site-menu-'.$orientation : '')
            .($presentation !== 'menu' ? ' h-site-menu-'.$presentation : '');
    }

    /**
     * A list of menu items with all their sub pages (the HTML of HMenu). An item: title, url, current, children,
     * icon (URL or ''), format name / icon / both.
     */
    public static function menuList(array $items, string $class, bool $selectableParent = true): string
    {
        $html = '';
        foreach($items as $item){
            $children = is_array($item['children'] ?? null) ? $item['children'] : array();
            $classes = 'h-site-menu-item'.(!empty($item['current']) ? ' is-current' : '').(!empty($children) ? ' has-children' : '');
            if(self::hasCurrent($children)){ $classes .= ' is-current-parent'; }
            $label = self::menuLabel($item);
            $entry = !empty($children) && !$selectableParent
                ? '<span class="h-site-menu-label" tabindex="0">'.$label.'</span>'
                : '<a href="'.self::attr((string)$item['url']).'"'.(!empty($item['current']) ? ' aria-current="page"' : '')
                    .(($item['format'] ?? 'name') === 'icon' ? ' title="'.self::attr((string)$item['title']).'"' : '')
                    .(!empty($item['newTab']) ? ' target="_blank" rel="noopener"' : '')
                    .(!empty($item['action']) ? ' data-h-action="'.self::attr((string)$item['action']).'" data-h-target="'
                        .self::attr((string)($item['target'] ?? '')).'"' : '')
                    .'>'.$label.'</a>';
            $html .= '<li class="'.$classes.'">'.$entry
                .(!empty($children) ? self::menuList($children, 'h-site-menu-sub', $selectableParent) : '').'</li>';
        }
        return '<ul class="'.$class.'">'.$html.'</ul>';
    }

    /** Icon and / or title of a menu entry. */
    private static function menuLabel(array $item): string
    {
        $format = (string)($item['format'] ?? 'name');
        $icon = (string)($item['icon'] ?? '');
        $fa = (string)($item['fa'] ?? '');
        $hasIcon = $fa !== '' || $icon !== '';
        $text = '<span class="h-site-menu-text'.($format === 'icon' && $hasIcon ? ' h-site-visually-hidden' : '').'">'
            .self::text2html((string)$item['title']).'</span>';
        if($fa !== ''){ return '<i class="h-site-menu-fa '.self::attr($fa).'" aria-hidden="true"></i>'.$text; }
        return ($format !== 'name' && $icon !== '' ? '<img class="h-site-menu-icon" src="'.self::attr($icon).'" alt="">' : '').$text;
    }

    private static function hasCurrent(array $items): bool
    {
        foreach($items as $item){
            if(!empty($item['current']) || self::hasCurrent(is_array($item['children'] ?? null) ? $item['children'] : array())){ return true; }
        }
        return false;
    }

    /** The JSON block read by the site runtime. */
    private function configJson(array $context): string
    {
        $site = $context['site'];
        $page = $context['page'];
        return (string)json_encode(array(
            'schema' => CmsValidator::SCHEMA,
            'runtime' => $this->runtime($context),
            'site' => array(
                'id' => $site !== null ? intval($site['rec_ID']) : null,
                'pageId' => intval($page['rec_ID']),
                'home' => $context['home'],
                'title' => $site !== null ? $this->text($site, $this->codes['name'], $context['language']) : '',
                'language' => $context['language'] !== null ? strtolower($context['language']) : null,
                'languages' => array_map(function(string $code) use ($context){
                    return array('code' => strtolower($code), 'url' => $this->url($context, intval($context['page']['rec_ID']), $code));
                }, $context['languages']),
                // the page has the menu as HTML; the designer's draft replaces it and needs the tree
                'menu' => !empty($context['design']) ? $this->menu($context, $context['menu']) : array(),
                'homeItem' => $site !== null && !empty($context['design']) ? $this->homeItem($context) : null,
                'layout' => $this->layout($context),
                // pages of the website in menu order, for the navigation modes swap / scroll (plan 27f §2)
                'pages' => $this->pageList($context)
            ),
            'instances' => $this->instances($context)
        ), self::JSON_FLAGS);
    }

    /** Runtime values given to every module (the bootstrap `runtime`). */
    private function runtime(array $context): array
    {
        $baseUrl = $this->environment['baseUrl'];
        $site = $context['site'];
        $requested = $context['requestedLanguage'] ?? null;
        return array(
            'database' => $this->environment['database'],
            'baseUrl' => $baseUrl,
            'apiBaseUrl' => $baseUrl.'api',
            'runtimeMode' => 'published',
            // ?lang= wins over the module's own language setting (plan 29, 2026-10-28)
            'language' => strtolower((string)($requested ?? $context['language'] ?? 'eng')),
            'languageRequested' => $requested !== null,
            // preview of the CMS designer (plan 27c §6): the runtime gives the design hooks
            'design' => !empty($context['design']),
            'site' => $site !== null ? intval($site['rec_ID']) : 0,
            'page' => intval($context['page']['rec_ID']),
            'moduleBaseUrl' => $baseUrl.'hclient/bundles/',
            'frameUrl' => $this->environment['urls']->frame($site !== null ? intval($site['rec_ID']) : intval($context['page']['rec_ID']),
                $site !== null && !$context['home'] ? intval($context['page']['rec_ID']) : 0)
        );
    }

    /**
     * Instances of the page: website instances (header, footer, home) and the page's.
     * An instance that can not be mounted gets `problem` (shown in its host).
     */
    private function instances(array $context): array
    {
        $instances = $context['site'] !== null ? self::instanceList($context['siteConfig']) : array();
        $instances = self::instanceList($context['pageConfig']) + $instances;
        $result = array();
        foreach($instances as $id => $instance){
            $instance = is_array($instance) ? $instance : array();
            $entry = array(
                'kind' => (string)($instance['kind'] ?? ''),
                'type' => (string)($instance['type'] ?? ''),
                'mount' => ($instance['mount'] ?? 'direct') === 'iframe' ? 'iframe' : 'direct',
                'settings' => self::publicSettings($instance, !empty($context['design'])),
                'state' => $instance['state'] ?? null
            );
            $problem = CmsValidator::instanceProblem($instance);
            if($problem !== null){ $entry['problem'] = $problem[1]; }
            $result[(string)$id] = $entry;
        }
        return $result;
    }

    /** Settings given to the page: a widget written by the server needs only the options of its start. */
    private static function publicSettings(array $instance, bool $design): array
    {
        $settings = is_array($instance['settings'] ?? null) ? $instance['settings'] : array();
        if($design || !in_array($instance['type'] ?? '', array('report', 'cards', 'gallery', 'badges', 'actionmenu'), true)){ return $settings; }
        return array_intersect_key($settings, array('presentation' => true, 'action' => true, 'slider' => true));
    }

    /** Layout settings of the website (a standalone page: its own) and the hide flags of the page (plan 27f). */
    private function layout(array $context): array
    {
        $own = $context['site'] !== null ? ($context['siteConfig'] ?? array()) : $context['pageConfig'];
        $layout = is_array($own['layout'] ?? null) ? $own['layout'] : array();
        $layout = array_intersect_key($layout, array('navigation' => true, 'cache' => true, 'header' => true, 'footer' => true));
        $page = is_array($context['pageConfig']['layout'] ?? null) ? $context['pageConfig']['layout'] : array();
        foreach(array('hideHeader', 'hideFooter') as $flag){
            if(($page[$flag] ?? false) === true){ $layout[$flag] = true; }
        }
        return $layout;
    }

    /**
     * The home page and the pages of the menu, depth first, as {id, url, title}; empty for the navigation mode
     * reload, the designer and a standalone page.
     */
    private function pageList(array $context): array
    {
        $navigation = $this->layout($context)['navigation'] ?? 'reload';
        if($context['site'] === null || !empty($context['design']) || !in_array($navigation, array('swap', 'scroll'), true)){
            return array();
        }
        $siteId = intval($context['site']['rec_ID']);
        $list = array(array('id' => $siteId, 'url' => $this->url($context, $siteId, $context['language']),
            'title' => $this->text($context['site'], $this->codes['name'], $context['language'])));
        $seen = array($siteId => true);
        $walk = function(array $items) use (&$walk, &$list, &$seen, $context): void {
            foreach($items as $item){
                $id = intval($item['id']);
                if(!isset($seen[$id])){
                    $seen[$id] = true;
                    $list[] = array('id' => $id, 'url' => $item['url'], 'title' => $item['title']);
                }
                $walk($item['children']);
            }
        };
        $walk($this->menu($context, $context['menu']));
        return $list;
    }

    /** Classes of the body: header and footer position and orientation, hide flags of the page (plan 27f §3). */
    public static function bodyClasses(array $layout): string
    {
        $classes = array('h-site-body');
        $header = is_array($layout['header'] ?? null) ? $layout['header'] : array();
        $footer = is_array($layout['footer'] ?? null) ? $layout['footer'] : array();
        // no position = fixed (the default since 2026-11-09); slide = an expand / collapse button (§13)
        $position = (string)($header['position'] ?? CmsValidator::LAYOUT['header']['position'][0]);
        if(in_array($position, array('fixed', 'slide'), true)){ $classes[] = 'h-site-header-'.$position; }
        if(($header['orientation'] ?? '') === 'vertical'){ $classes[] = 'h-site-header-vertical'; }
        if(($header['rows'] ?? '') === 'two'){ $classes[] = 'h-site-header-rows2'; }
        if((($footer['position'] ?? '') === 'fixed')){ $classes[] = 'h-site-footer-fixed'; }
        foreach(array('header' => $header, 'footer' => $footer) as $part => $settings){
            $theme = (string)($settings['theme'] ?? 'default');
            if($theme !== 'default' && in_array($theme, CmsValidator::LAYOUT[$part]['theme'], true)){ $classes[] = 'h-site-'.$part.'-theme-'.$theme; }
        }
        if(!empty($layout['hideHeader'])){ $classes[] = 'h-site-hide-header'; }
        if(!empty($layout['hideFooter'])){ $classes[] = 'h-site-hide-footer'; }
        return implode(' ', $classes);
    }

    /**
     * CSS of the layout (plan 27f §13): the banner image as the variable --h-site-banner (the background of the header
     * default set) and the own colours of the header and footer (theme `own`).
     */
    public static function layoutCss(array $layout, string $bannerUrl): string
    {
        $css = '';
        if($bannerUrl !== ''){ $css .= 'html.h-site{--h-site-banner:url("'.addcslashes($bannerUrl, '"\\').'");}'."\n"; }
        foreach(array('header', 'footer') as $part){
            $settings = is_array($layout[$part] ?? null) ? $layout[$part] : array();
            if(($settings['theme'] ?? '') !== 'own' || !CmsValidator::isLayoutColors($settings['colors'] ?? null)){ continue; }
            $rules = '';
            if(!empty($settings['colors']['bg'])){ $rules .= '--h-color-bg:'.trim($settings['colors']['bg']).';'; }
            if(!empty($settings['colors']['text'])){
                // links, headings and the menu of the part take the text colour too
                $text = trim($settings['colors']['text']);
                $rules .= '--h-color-text:'.$text.';--h-color-link:'.$text.';--h-site-heading-color:'.$text.';';
            }
            if($rules !== ''){ $css .= '.h-site-'.$part.'{'.$rules."}\n"; }
        }
        return $css;
    }

    /** URL of the file in a file field of a record, or ''. */
    private function fileUrlOf(array $record, int $fieldId): string
    {
        $fileId = $fieldId > 0 ? intval(WebsiteRepository::value($record, $fieldId)) : 0;
        return $fileId > 0 && $this->content !== null ? $this->content->fileUrl($fileId) : '';
    }

    /** Scope of the page styles: its section (plan 27f §2.2). */
    public static function pageScope(int $pageId): string
    {
        return '[data-h-page="'.$pageId.'"]';
    }

    /** The menu tree with titles and URLs in the current language. */
    private function menu(array $context, array $tree): array
    {
        $result = array();
        $current = intval($context['page']['rec_ID']);
        foreach($tree as $item){
            $page = $context['pages'][$item['id']] ?? null;
            if($page === null){ continue; }
            $result[] = array(
                'id' => $item['id'],
                'title' => $this->text($page, $this->codes['name'], $context['language']),
                'url' => $this->url($context, intval($item['id']), $context['language']),
                'current' => intval($item['id']) === $current,
                'icon' => $this->menuIcon($page),
                'format' => $this->menuFormat($page),
                'children' => $this->menu($context, $item['children'])
            );
        }
        return $result;
    }

    /** URL of the icon of a page's menu entry (DT_THUMBNAIL), or ''. */
    private function menuIcon(array $page): string
    {
        $fileId = intval(WebsiteRepository::value($page, intval($this->codes['thumbnail'] ?? 0)));
        return $fileId > 0 && $this->content !== null ? $this->content->fileUrl($fileId) : '';
    }

    /** What the menu entry of a page shows (DT_CMS_MENU_FORMAT): name (default), icon or both. */
    private function menuFormat(array $page): string
    {
        $term = intval(WebsiteRepository::value($page, intval($this->codes['menuFormat'] ?? 0)));
        if($term > 0 && $term === intval($this->codes['menuIconOnly'] ?? 0)){ return 'icon'; }
        if($term > 0 && $term === intval($this->codes['menuNameAndIcon'] ?? 0)){ return 'both'; }
        return 'name';
    }

    /** URL of a page of this website (or the standalone page) in a language. */
    private function url(array $context, int $pageId, ?string $language): string
    {
        $default = $context['languages'][0] ?? null;
        $lang = $language !== null && $language !== $default ? strtolower($language) : '';
        if($context['site'] === null){
            return $this->environment['urls']->page($pageId, 0, $lang);
        }
        $siteId = intval($context['site']['rec_ID']);
        return $this->environment['urls']->page($siteId, $pageId === $siteId ? 0 : $pageId, $lang);
    }

    /** <link rel="alternate" hreflang> for the other languages. */
    private function alternateLinks(array $context): string
    {
        if(count($context['languages']) < 2){ return ''; }
        $links = '';
        foreach($context['languages'] as $code){
            $links .= '<link rel="alternate" hreflang="'.self::attr($this->languages->code2($code) ?? strtolower($code)).'" href="'
                .self::attr($this->url($context, intval($context['page']['rec_ID']), $code)).'">';
        }
        return $links;
    }

    /**
     * Custom CSS of the website (a standalone page: its own), only with the code exception (isJavaScriptAllowed;
     * plan 27a decision 12). Pages of a website have none (plan 27f §9.3): every page of a website is the same
     * document for the navigation modes.
     */
    private function customCss(array $record): string
    {
        $value = $this->environment['javaScriptAllowed'] ? WebsiteRepository::value($record, $this->codes['css']) : '';
        if(trim($value) === ''){ return ''; }
        return '<style class="h-site-custom-css">'."\n".str_ireplace('</style', '<\/style', $value)."\n".'</style>';
    }

    /** External scripts and stylesheets (2-939) of the website or standalone page, only with the code exception. */
    private function externalFiles(array $record): string
    {
        if(!$this->environment['javaScriptAllowed']){ return ''; }
        $tags = '';
        foreach(WebsiteRepository::values($record, $this->codes['extFiles']) as $url){
            $url = trim(strip_tags((string)$url));
            if(!preg_match('#^(https?:)?//|^[./]#i', $url)){ continue; }
            $tags .= preg_match('/\.css(\?|$)/i', $url)
                ? '<link rel="stylesheet" href="'.self::attr($url).'">'
                : '<script src="'.self::attr($url).'"></script>';
        }
        return $tags;
    }

    /** Custom JavaScript (2-927) of the website or standalone page, only with the code exception. */
    private function customScript(array $record): string
    {
        $value = $this->environment['javaScriptAllowed'] ? trim(WebsiteRepository::value($record, $this->codes['script'])) : '';
        if($value === ''){ return ''; }
        // a value saved with its <script> element is written as it is
        return stripos($value, '<script') !== false ? $value : '<script>'.str_ireplace('</script', '<\/script', $value).'</script>';
    }

    /** A text field in a language, without HTML. */
    private function text(array $record, int $fieldId, ?string $language): string
    {
        $value = $this->languages->valueIn(WebsiteRepository::values($record, $fieldId), $language ?? 'ENG');
        return trim(html_entity_decode(strip_tags(str_ireplace(array('<br>', '<br/>', '<br />'), ' ', (string)$value)),
            ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function part(?array $config, string $name): array
    {
        $value = $config['styles'][$name] ?? array();
        return is_array($value) ? $value : array();
    }

    private static function instanceList(?array $config): array
    {
        $value = $config['instances'] ?? array();
        return is_array($value) ? $value : array();
    }

    private static function attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function text2html(string $value): string
    {
        return htmlspecialchars($value, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
    }
}
