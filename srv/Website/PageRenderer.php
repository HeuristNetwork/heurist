<?php
/**
* PageRenderer.php - Writes the HTML document of a v4 website page (plan 27a §5)
*
* The document: title and keywords; the website bundle CSS (heurist-ui.css and
* the site styles); one <style> with the base settings and node styles
* (StyleCompiler); the custom CSS (99-946, StoredCss) of website and page;
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
use Heurist\Utilities\StoredCss;

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

    /**
     * @param array<string,int> $codes WebsiteRepository::codes().
     * @param array $environment database, baseUrl, javaScriptAllowed, urls (SiteUrls).
     */
    public function __construct(array $codes, LanguageCodes $languages, array $environment)
    {
        $this->codes = $codes;
        $this->languages = $languages;
        $this->environment = $environment;
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
        $base = $site !== null && !$context['home']
            ? array_merge(self::part($siteConfig, 'base'), self::part($pageConfig, 'base'))
            : self::part($pageConfig, 'base');
        $withSite = $site !== null && !$context['home'];
        $sets = static function(string $part) use ($withSite, $siteConfig, $pageConfig): array {
            return $withSite ? array(self::part($siteConfig, $part), self::part($pageConfig, $part)) : array(self::part($pageConfig, $part));
        };
        $styles = (new StyleCompiler($baseUrl))->compile($base, $sets('nodes'), $sets('kinds'), $sets('presets'));

        $content = CmsHtml::translate(WebsiteRepository::value($page, $this->codes['content']), $pageTexts);
        $header = $site !== null ? CmsHtml::translate(WebsiteRepository::value($site, $this->codes['header']), $siteTexts) : '';
        $footer = $site !== null ? CmsHtml::translate(WebsiteRepository::value($site, $this->codes['footer']), $siteTexts) : '';

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
            .$this->customCss($site, $page, $context['home'])
            .$this->externalFiles($site, $page, $context['home'])
            .'</head><body class="h-site-body">'
            .($header !== '' ? '<header class="h-site-header">'.$header.'</header>' : '')
            .'<main class="h-site-main">'.$content.'</main>'
            .($footer !== '' ? '<footer class="h-site-footer">'.$footer.'</footer>' : '')
            .'<script type="application/json" id="h-site-config">'.$this->configJson($context).'</script>'
            .'<script type="module" src="'.self::attr($baseUrl.self::BUNDLE.'heurist-website.js').'"></script>'
            .$this->customScript($site, $page, $context['home'])
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
                'menu' => $this->menu($context, $context['menu'])
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
                'settings' => is_array($instance['settings'] ?? null) ? $instance['settings'] : array(),
                'state' => $instance['state'] ?? null
            );
            $problem = CmsValidator::instanceProblem($instance);
            if($problem !== null){ $entry['problem'] = $problem[1]; }
            $result[(string)$id] = $entry;
        }
        return $result;
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
                'children' => $this->menu($context, $item['children'])
            );
        }
        return $result;
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

    /** Custom CSS of website and page; cleaned again unless the code exception applies now. */
    private function customCss(?array $site, array $page, bool $home): string
    {
        $css = '';
        $records = $site !== null && !$home ? array($site, $page) : array($page);
        foreach($records as $record){
            $value = WebsiteRepository::value($record, $this->codes['css']);
            if(trim($value) === ''){ continue; }
            $value = $this->environment['javaScriptAllowed'] ? str_ireplace('</style', '<\/style', $value) : StoredCss::clean($value);
            $css .= '<style class="h-site-custom-css">'."\n".$value."\n".'</style>';
        }
        return $css;
    }

    /** External scripts and stylesheets (2-939), only with the code exception. */
    private function externalFiles(?array $site, array $page, bool $home): string
    {
        if(!$this->environment['javaScriptAllowed']){ return ''; }
        $tags = '';
        $records = $site !== null && !$home ? array($site, $page) : array($page);
        foreach($records as $record){
            foreach(WebsiteRepository::values($record, $this->codes['extFiles']) as $url){
                $url = trim(strip_tags((string)$url));
                if(!preg_match('#^(https?:)?//|^[./]#i', $url)){ continue; }
                $tags .= preg_match('/\.css(\?|$)/i', $url)
                    ? '<link rel="stylesheet" href="'.self::attr($url).'">'
                    : '<script src="'.self::attr($url).'"></script>';
            }
        }
        return $tags;
    }

    /** Custom JavaScript (2-927) of website and page, only with the code exception. */
    private function customScript(?array $site, array $page, bool $home): string
    {
        if(!$this->environment['javaScriptAllowed']){ return ''; }
        $scripts = '';
        $records = $site !== null && !$home ? array($site, $page) : array($page);
        foreach($records as $record){
            $value = trim(WebsiteRepository::value($record, $this->codes['script']));
            if($value === ''){ continue; }
            // a value saved with its <script> element is written as it is
            $scripts .= stripos($value, '<script') !== false ? $value : '<script>'.str_ireplace('</script', '<\/script', $value).'</script>';
        }
        return $scripts;
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
