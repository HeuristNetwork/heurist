<?php
/**
* WebsiteBuilder.php - Writes a v4 website into files (plan 27b §7, plan 27m §2)
*
* Every page of a website (home and menu pages) or a standalone page, in the chosen site
* languages, rendered by PageRenderer with the rights of a visitor who is not logged in
* (the WebsiteService given here must read as a guest: no author session or private record
* goes into the output). Files: `{lang}/index.html` (what the URL without a page shows),
* `{lang}/{page id}.html`, the generated record pages `{lang}/{page id}/{record id}.html`
* (plan 27m §2.1: the records of a query per record page, at most RECORD_FILE_LIMIT files).
*
* Two kinds (plan 27m): `folder` = the generated site served by Heurist at the live URLs
* (links are live URLs, `sitemap.xml`); `zip` = a copy for another host (relative links,
* `index.html` at the root opening the default language; with the public base URL also
* canonical, alternates, `sitemap.xml` and `robots.txt`). The site runtime, module bundles
* and data still come from the Heurist server (a built site is not an offline copy).
*
* @project     Heurist academic knowledge management system
* @package     Website\Build
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Website\Build;

use Heurist\Website\PageRenderer;
use Heurist\Website\SiteUrls;
use Heurist\Website\WebsiteException;
use Heurist\Website\WebsiteService;
use InvalidArgumentException;
use RuntimeException;

/** Renders all pages of a site into a folder. */
final class WebsiteBuilder
{
    /** Most record page files of one build (records × languages, plan 27m §2.1). */
    public const RECORD_FILE_LIMIT = 5000;

    /** Targets of a build (plan 27m §2.1). */
    public const TARGETS = array('folder', 'zip', 'both');

    private WebsiteService $service;
    /** @var callable(SiteUrls):PageRenderer Renderer with the given URLs. */
    private $renderer;
    /** @var callable(array,int):array|null Record ids of a query read as a guest (record pages). */
    private $search;
    private string $database;
    private string $baseUrl;

    /**
     * @param WebsiteService $service Service reading with guest rights.
     * @param callable(SiteUrls):PageRenderer $renderer Renderer for the URLs of a build.
     * @param string $database Database name (URLs).
     * @param string $baseUrl Heurist base URL (URLs).
     * @param callable(array,int):array|null $search fn(query, limit): record ids read as a guest.
     */
    public function __construct(WebsiteService $service, callable $renderer, string $database, string $baseUrl, ?callable $search = null)
    {
        $this->service = $service;
        $this->renderer = $renderer;
        $this->database = $database;
        $this->baseUrl = $baseUrl;
        $this->search = $search;
    }

    /**
     * The options of a build as given to the job (plan 27m §2.1): `languages` (3 letters, empty = all), `target`
     * folder | zip | both, `baseUrl` the public URL of a ZIP (http/https, optional), `recordPages` page id => query
     * (JSON array or its text).
     *
     * @throws InvalidArgumentException For a value that is not allowed.
     */
    public static function options(array $options): array
    {
        $languages = array();
        foreach(is_array($options['languages'] ?? null) ? $options['languages'] : array() as $code){
            $code = strtolower(trim((string)$code));
            if(preg_match('/^[a-z]{3}$/', $code) !== 1){ throw new InvalidArgumentException('Language '.$code.' is not a 3-letter code'); }
            $languages[] = $code;
        }
        $target = (string)($options['target'] ?? 'folder');
        if(!in_array($target, self::TARGETS, true)){ throw new InvalidArgumentException('target must be '.implode(', ', self::TARGETS)); }
        $baseUrl = trim((string)($options['baseUrl'] ?? ''));
        if($baseUrl !== '' && (preg_match('#^https?://[^\s/?\#]+(/[^\s?\#]*)?$#i', $baseUrl) !== 1)){
            throw new InvalidArgumentException('The public address must be an http or https URL without parameters');
        }
        $recordPages = array();
        foreach(is_array($options['recordPages'] ?? null) ? $options['recordPages'] : array() as $pageId => $query){
            if(is_string($query)){
                if(trim($query) === ''){ continue; }
                $query = json_decode($query, true);
            }
            if(!is_array($query) || empty($query) || intval($pageId) < 1){
                throw new InvalidArgumentException('The query of record page '.$pageId.' is not a query');
            }
            $recordPages[(string)intval($pageId)] = $query;
        }
        return array('languages' => array_values(array_unique($languages)), 'target' => $target, 'baseUrl' => $baseUrl,
            'recordPages' => $recordPages);
    }

    /**
     * Write the site into a folder.
     *
     * @param int $id Website or standalone page.
     * @param string $directory Empty or new folder.
     * @param string $kind `folder` (served at the live URLs) or `zip` (relative files).
     * @param array $options options().
     * @param callable|null $check Called before each page (Stop, time limit).
     * @param callable|null $progress fn(int done, int total, string message).
     * @return array{files:array<int,string>,pages:int,languages:array<int,string>,defaultLanguage:string,title:string,
     *         records:int,recordPages:array<string,int>,modified:string,sitemap:bool,baseUrl:string}
     * @throws WebsiteException When a visitor can not see the site (not public) or it is not v4.
     * @throws InvalidArgumentException Too many record pages, no chosen language of the site.
     */
    public function build(int $id, string $directory, string $kind = 'zip', array $options = array(), ?callable $check = null,
        ?callable $progress = null): array
    {
        try{
            $start = $this->service->context($id);
        }catch(WebsiteException $exception){
            throw $exception->status() === 403
                ? new WebsiteException(403, 'A visitor can not see record '.$id.'. Make the website and its pages public first.')
                : $exception;
        }
        $siteLanguages = !empty($start['languages']) ? $start['languages'] : array('ENG');
        $chosen = $options['languages'] ?? array();
        $languages = array_values(array_filter($siteLanguages, static function(string $code) use ($chosen): bool {
            return empty($chosen) || in_array(strtolower($code), $chosen, true);
        }));
        if(empty($languages)){ throw new InvalidArgumentException('None of the chosen languages is a language of the site'); }
        $folders = array_map('strtolower', $languages);
        $default = strtolower($siteLanguages[0]);
        $pageIds = array_merge(array(0), $start['site'] !== null ? array_map('intval', array_keys($start['pages'])) : array());
        // a Startup page is index.html: the home page is written as {website id}.html (its links)
        if(!empty($start['startPage'])){ $pageIds[] = intval($start['site']['rec_ID']); }

        $records = $this->recordPages($start, is_array($options['recordPages'] ?? null) ? $options['recordPages'] : array(), count($languages));
        $recordFlags = array_map(static function(array $ids): array { return array_fill_keys(array_keys($ids), true); }, $records);
        $publicBase = $kind === 'zip' ? (string)($options['baseUrl'] ?? '') : '';
        $urls = $kind === 'folder' ? new SiteUrls($this->database, $this->baseUrl, true)
            : SiteUrls::files($this->database, $this->baseUrl, $default, $publicBase, $recordFlags);
        $renderer = ($this->renderer)($urls);
        // a record page of a ZIP is one folder deeper ({lang}/{page}/{record}.html)
        $deeper = $kind === 'folder' ? $renderer : $renderer->withUrls($urls->withDepth(2));

        $recordCount = array_sum(array_map('count', $records));
        $total = (count($pageIds) + $recordCount) * count($languages);
        $files = array();
        $done = 0;
        $step = static function() use (&$done, $total, $progress): void {
            $done++;
            if($progress !== null){ $progress($done, $total, 'Page '.$done.' of '.$total); }
        };

        foreach($languages as $position => $language){
            foreach($pageIds as $pageId){
                if($check !== null){ $check(); }
                $context = $this->service->context($id, $pageId, $language);
                // a record page shows a record of its URL: its files are the records of its query (below)
                if(empty($context['recordPage'])){
                    $name = $folders[$position].'/'.($pageId > 0 ? $pageId : 'index').'.html';
                    $this->write($directory, $name, $renderer->render($context));
                    $files[] = $name;
                }
                $step();
            }
            foreach($records as $pageId => $recordIds){
                foreach(array_keys($recordIds) as $recordId){
                    if($check !== null){ $check(); }
                    try{
                        $context = $this->service->context($id, intval($pageId), $language, intval($recordId));
                        $name = $folders[$position].'/'.$pageId.'/'.$recordId.'.html';
                        $this->write($directory, $name, $deeper->render($context));
                        $files[] = $name;
                    }catch(WebsiteException $exception){
                        // the record is not visible any more: no file (the live page answers)
                    }
                    $step();
                }
            }
        }

        // the sitemap (plan 27m §5): the generated site, a ZIP only with its public address
        $sitemap = $kind === 'folder' || $publicBase !== '';
        if($sitemap){
            $home = $this->service->context($id, $start['site'] !== null ? $id : 0);
            $this->write($directory, GeneratedSites::SITEMAP, $renderer->sitemap($home, $records));
            $files[] = GeneratedSites::SITEMAP;
        }
        if($kind === 'zip'){
            $this->write($directory, 'index.html', self::redirect($folders[0].'/index.html'));
            $files[] = 'index.html';
            if($publicBase !== ''){
                $this->write($directory, 'robots.txt', self::robots(PageRenderer::indexable($start),
                    rtrim($publicBase, '/').'/'.GeneratedSites::SITEMAP));
                $files[] = 'robots.txt';
            }
        }

        $title = (string)(($start['site'] ?? $start['page'])['rec_Title'] ?? '');
        return array('files' => $files, 'pages' => count($pageIds), 'languages' => $folders, 'defaultLanguage' => $default,
            'title' => $title, 'records' => $recordCount, 'recordPages' => array_map('count', $records),
            'modified' => self::lastChange($start), 'sitemap' => $sitemap, 'baseUrl' => $this->baseUrl);
    }

    /**
     * Records of the record pages (plan 27m §2.1): page id => record id => rec_Modified; only record pages of the site,
     * only records a guest sees (the search runs as a guest).
     *
     * @throws InvalidArgumentException Too many files.
     */
    private function recordPages(array $start, array $queries, int $languages): array
    {
        $result = array();
        if(empty($queries) || $start['site'] === null){ return $result; }
        if($this->search === null){ throw new RuntimeException('Record pages can not be generated: no record search'); }
        $limit = intdiv(self::RECORD_FILE_LIMIT, max(1, $languages));
        $repository = $this->service->repository();
        foreach($queries as $pageId => $query){
            $pageId = intval($pageId);
            $page = $start['pages'][$pageId] ?? null;
            if($page === null){ continue; }
            $context = $this->service->context(intval($start['site']['rec_ID']), $pageId);
            if(empty($context['recordPage'])){ continue; }
            $ids = array_values(array_unique(array_map('intval', (array)call_user_func($this->search, $query, $limit + 1))));
            $heads = $repository->recordHeads($ids);
            $result[(string)$pageId] = array();
            foreach($ids as $recordId){
                if(isset($heads[$recordId])){ $result[(string)$pageId][$recordId] = $heads[$recordId]['modified']; }
            }
            if(array_sum(array_map('count', $result)) * $languages > self::RECORD_FILE_LIMIT){
                throw new InvalidArgumentException('Too many record pages: at most '.self::RECORD_FILE_LIMIT
                    .' files (records × languages). Narrow the queries or choose fewer languages.');
            }
        }
        return $result;
    }

    /** The latest change of the site and its pages (rec_Modified), '' when unknown. */
    public static function lastChange(array $context): string
    {
        $dates = array((string)(($context['site'] ?? $context['page'])['rec_Modified'] ?? ''));
        foreach($context['pages'] ?? array() as $page){ $dates[] = (string)($page['rec_Modified'] ?? ''); }
        return (string)max($dates);
    }

    /**
     * Zip a built folder.
     *
     * @return int Size of the zip file in bytes.
     */
    public static function zip(string $directory, array $files, string $zipFile): int
    {
        if(!class_exists('ZipArchive')){
            throw new RuntimeException('The PHP zip extension is needed to generate a website');
        }
        $zip = new \ZipArchive();
        if($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true){
            throw new RuntimeException('Cannot create the website zip file');
        }
        foreach($files as $name){
            $zip->addFile(rtrim($directory, '/\\').'/'.$name, $name);
        }
        $zip->close();
        return (int)filesize($zipFile);
    }

    /** Remove a built folder with all its files (after zipping; `$files` is not needed any more). */
    public static function remove(string $directory, array $files = array()): void
    {
        GeneratedSites::removeFolder(rtrim($directory, '/\\'));
    }

    private function write(string $directory, string $name, string $content): void
    {
        $path = rtrim($directory, '/\\').'/'.$name;
        $folder = dirname($path);
        if(!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)){
            throw new RuntimeException('Cannot create folder '.$folder);
        }
        if(file_put_contents($path, $content) === false){
            throw new RuntimeException('Cannot write '.$name);
        }
    }

    /** The robots.txt of a site: all allowed with the sitemap, or nothing (plan 27m §4-5). */
    public static function robots(bool $index, string $sitemapUrl): string
    {
        return "User-agent: *\n".($index ? "Allow: /\nSitemap: ".$sitemapUrl."\n" : "Disallow: /\n");
    }

    /** Root page that opens the default language. */
    private static function redirect(string $target): string
    {
        $url = htmlspecialchars($target, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0; url='.$url.'">'
            .'<link rel="canonical" href="'.$url.'"><title>Heurist website</title></head>'
            .'<body><p><a href="'.$url.'">Open the website</a></p></body></html>';
    }
}
