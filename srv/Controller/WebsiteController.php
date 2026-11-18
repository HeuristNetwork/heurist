<?php
/**
* WebsiteController.php - Pages of the websites of format v4 (plan 27a)
*
* Pages (FrontController, RequestRouter action "site"):
*   GET /{db}/site/{id}[/{page id}][?lang=xxx]        website home / page, or a standalone page
*   GET /{db}/site/{id}/{page id}/{record id}          a record page showing a record (plan 27g)
*   GET /{db}/site/{id}[/{page id}]/frame/{instance}   one module in an iframe ("mount": "iframe")
*   GET /{db}/site/{id}/sitemap.xml                    the sitemap, read as a guest (plan 27m §5)
*   (parameter form: ?db=&site=&pageid=&rec=&frame=&lang=, &sitemap=1)
*   ?design=1 (logged-in users): the preview of the CMS designer (plan 27c §6); ?live=1: the live output (noindex)
* A generated site (plan 27m §1-2, `generated-sites/{id}`) answers a bare page URL (+ lang) from its files
* (GeneratedPages); everything else is rendered live. An own domain (plan 27m §5, param `domain`): the same
* pages at `https://host/[{page}[/{record}]]`, `/sitemap.xml` and `/robots.txt` (param `robots`); the files
* of a generated site get the URLs of the domain when they are sent.
* The API of websites and pages is /api/{db}/website/... and /api/{db}/page/... on the
* generic record controller (RecordEditController, Website/WebsiteEntityHandler; plan 27d).
*
* @project     Heurist academic knowledge management system
* @package     Controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Controller;

use Heurist\Runtime\ApiResponse;
use Heurist\Runtime\ErrorReporter;
use Heurist\Runtime\RuntimeContext;
use Heurist\Website\Build\GeneratedPages;
use Heurist\Website\Build\WebsiteBuilder;
use Heurist\Website\PageRenderer;
use Heurist\Website\SiteUrls;
use Heurist\Website\WebsiteException;
use Heurist\Website\WebsiteService;
use Throwable;

/** HTTP boundary of the v4 website pages. */
final class WebsiteController
{
    private WebsiteService $service;
    /** @var callable(SiteUrls):PageRenderer Renderer with the URLs of a request. */
    private $renderer;
    private RuntimeContext $runtime;
    /** @var callable(int):array|null fn(website id): its context read as a guest (sitemap, robots.txt). */
    private $guestContext;
    /** @var callable(SiteUrls):PageRenderer|null Renderer reading as a guest (sitemap). */
    private $guestRenderer;
    private ?GeneratedPages $generated;
    private ApiResponse $response;
    private ErrorReporter $errors;

    /**
     * @param callable(SiteUrls):PageRenderer $renderer Renderer with the URLs of a request.
     * @param array{context?:callable,renderer?:callable} $guest Context and renderer read as a guest (plan 27m §5).
     * @param GeneratedPages|null $generated Files of the generated sites (plan 27m §1).
     */
    public function __construct(
        WebsiteService $service,
        callable $renderer,
        RuntimeContext $runtime,
        ?ApiResponse $response = null,
        ?ErrorReporter $errors = null,
        array $guest = array(),
        ?GeneratedPages $generated = null
    ) {
        $this->service = $service;
        $this->renderer = $renderer;
        $this->runtime = $runtime;
        $this->guestContext = $guest['context'] ?? null;
        $this->guestRenderer = $guest['renderer'] ?? null;
        $this->generated = $generated;
        $this->response = $response ?? new ApiResponse();
        $this->errors = $errors ?? new ErrorReporter();
    }

    /**
     * Write a page (or an instance frame, the sitemap, robots.txt of an own domain).
     *
     * @param array $params site, pageid, rec, frame, lang, design, live, sitemap, robots, domain.
     * @param bool $pretty The request used a /{db}/site/ URL.
     */
    public function showPage(array $params, bool $pretty): void
    {
        $urls = $this->urls($params, $pretty);
        if(!empty($params['robots'])){
            $this->showRobots(intval($params['site'] ?? 0), $urls);
            return;
        }
        if(!empty($params['sitemap'])){
            $this->showSitemap(intval($params['site'] ?? 0), $urls, $params);
            return;
        }
        try{
            // a generated site answers a bare page URL (plan 27m §1)
            $file = $this->generated !== null ? $this->generated->file($params) : null;
            if($file !== null){
                $this->sendGenerated($file, 'text/html; charset=utf-8', $params);
                return;
            }
            $context = $this->service->context(intval($params['site'] ?? 0), intval($params['pageid'] ?? 0),
                (string)($params['lang'] ?? ''), intval($params['rec'] ?? 0));
            // the preview of the CMS designer (plan 27c §6): logged-in users only; the page runtime
            // opens the designer when the page is not inside it
            $context['design'] = !empty($params['design']) && $this->runtime->userId > 0;
            // the live output beside a generated site (plan 27m §1): never indexed
            $context['live'] = !empty($params['live']);
            $renderer = ($this->renderer)($urls);
            $frame = trim((string)($params['frame'] ?? ''));
            // a record page needs a record (plan 27g); the designer shows it without one
            if($context['recordPage'] && $context['record'] === null && !$context['design'] && $frame === ''){
                throw new WebsiteException(404, 'This page shows a record: open it from a link to a record');
            }
            $html = $frame !== '' ? $renderer->frame($context, $frame) : $renderer->render($context);
            $this->response->sendHtml($html, 200, array('Cache-Control' => 'no-cache'));
        }catch(WebsiteException $exception){
            $this->response->sendHtml(self::errorPage($exception->getMessage()), $exception->status(),
                array('Cache-Control' => 'no-cache'));
        }catch(Throwable $exception){
            $this->errors->report($exception, $this->runtime);
            $this->response->sendHtml(self::errorPage('The website page can not be shown'), 500);
        }
    }

    /** URLs of the request: pretty or parameter form, or the own domain of the website (plan 27m §5). */
    private function urls(array $params, bool $pretty): SiteUrls
    {
        $database = $this->runtime->databaseName;
        if(!empty($params['domain']) && intval($params['site'] ?? 0) > 0 && self::requestRoot() !== ''){
            return SiteUrls::domain($database, $this->runtime->baseUrl, self::requestRoot(), intval($params['site']));
        }
        return new SiteUrls($database, $this->runtime->baseUrl, $pretty);
    }

    /** The sitemap of a website or standalone page (plan 27m §5); a site a guest can not see: 404. */
    private function showSitemap(int $id, SiteUrls $urls, array $params): void
    {
        try{
            $file = $this->generated !== null ? $this->generated->sitemap($id) : null;
            if($file !== null){
                $this->sendGenerated($file, 'application/xml; charset=utf-8', $params);
                return;
            }
            if($this->guestContext === null || $this->guestRenderer === null){ throw new WebsiteException(404, 'No sitemap'); }
            $xml = ($this->guestRenderer)($urls)->sitemap(($this->guestContext)($id));
            self::sendText($xml, 'application/xml; charset=utf-8', array('Cache-Control' => 'max-age=3600'));
        }catch(WebsiteException $exception){
            $this->response->sendHtml(self::errorPage($exception->getMessage()),
                $exception->status() === 403 ? 404 : $exception->status(), array('Cache-Control' => 'no-cache'));
        }catch(Throwable $exception){
            $this->errors->report($exception, $this->runtime);
            $this->response->sendHtml(self::errorPage('The sitemap can not be shown'), 500);
        }
    }

    /** robots.txt of an own domain (plan 27m §5): the sitemap of its website, or nothing allowed. */
    private function showRobots(int $id, SiteUrls $urls): void
    {
        try{
            $index = $this->guestContext !== null && PageRenderer::indexable(($this->guestContext)($id));
        }catch(WebsiteException $exception){
            $index = false;
        }
        self::sendText(WebsiteBuilder::robots($index, $urls->sitemap($id)), 'text/plain; charset=utf-8',
            array('Cache-Control' => 'max-age=3600'));
    }

    /**
     * Send a file of a generated site: Last-Modified / ETag (304 when unchanged), `X-Heurist-Site: generated`; on an
     * own domain the URLs of the website get its form (the files have the /{db}/site/{id} form of the build).
     */
    private function sendGenerated(string $file, string $type, array $params): void
    {
        $modified = (int)filemtime($file);
        $domain = !empty($params['domain']) ? self::requestRoot() : '';
        $etag = '"'.md5($file.'|'.$modified.'|'.filesize($file).'|'.$domain).'"';
        $headers = array('Last-Modified' => gmdate('D, d M Y H:i:s', $modified).' GMT', 'ETag' => $etag,
            'Cache-Control' => 'no-cache', 'X-Heurist-Site' => 'generated '.gmdate('Y-m-d\TH:i:s\Z', $modified));
        $match = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        $since = strtotime((string)($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));
        if(($match !== '' && $match === $etag) || ($match === '' && $since !== false && $since >= $modified)){
            http_response_code(304);
            foreach($headers as $name => $value){ header($name.': '.$value); }
            return;
        }
        $content = (string)file_get_contents($file);
        if($domain !== ''){
            $siteId = intval($params['site'] ?? 0);
            $built = (string)($this->generated !== null ? ($this->generated->sites()->manifest($siteId)['baseUrl'] ?? '') : '');
            $content = $this->domainForm($content, $siteId, $domain, $built !== '' ? $built : $this->runtime->baseUrl);
        }
        self::sendText($content, $type, $headers);
    }

    /**
     * URLs of a generated file in the form of the own domain (plan 27m §5): `{server}/{db}/site/{id}` → the domain
     * root, other v4 pages `{server}/{db}/site/` → `{domain}/site/`, then the server root → the domain root. The server
     * is the Heurist address of the build (manifest `baseUrl`), matched with http and https.
     */
    private function domainForm(string $content, int $siteId, string $domain, string $builtBaseUrl): string
    {
        $host = (string)parse_url($builtBaseUrl, PHP_URL_HOST);
        $port = parse_url($builtBaseUrl, PHP_URL_PORT);
        if($host === ''){ return $content; }
        $server = 'https?://'.preg_quote($host.($port ? ':'.$port : ''), '#');
        $prefix = preg_quote('/'.rawurlencode($this->runtime->databaseName).'/site/', '#');
        $content = (string)preg_replace('#'.$server.$prefix.$siteId.'(?![0-9])/?#', $domain.'/', $content);
        $content = (string)preg_replace('#'.$server.$prefix.'#', $domain.'/site/', $content);
        return (string)preg_replace('#'.$server.'/#', $domain.'/', $content);
    }

    /** "https://host[:port]" of the request, '' without a host. */
    private static function requestRoot(): string
    {
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        if($host === '' || preg_match('/^[A-Za-z0-9.-]+(:\d+)?$/', $host) !== 1){ return ''; }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        return ($https ? 'https' : 'http').'://'.strtolower($host);
    }

    /** Send a text answer. */
    private static function sendText(string $text, string $type, array $headers = array()): void
    {
        http_response_code(200);
        header('Content-Type: '.$type);
        foreach($headers as $name => $value){ header($name.': '.$value); }
        print $text;
    }

    /** A plain error page. */
    private static function errorPage(string $message): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1"><title>Heurist website</title>'
            .'<style>body{font:14px Arial,Helvetica,sans-serif;color:#2f2f2f;margin:3em auto;max-width:40em;padding:0 1em}</style>'
            .'</head><body><h1>Heurist website</h1><p>'.htmlspecialchars($message, ENT_QUOTES, 'UTF-8').'</p></body></html>';
    }
}
