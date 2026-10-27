<?php
/**
* WebsiteController.php - Websites of the format v4 (plans 27a, 27b)
*
* Pages (FrontController, RequestRouter action "site"):
*   GET /{db}/site/{id}[/{page id}][?lang=xxx]        website home / page, or a standalone page
*   GET /{db}/site/{id}[/{page id}]/frame/{instance}   one module in an iframe ("mount": "iframe")
*   (parameter form: ?db=&site=&pageid=&frame=&lang=)
* API (hserv/controller/api.php, resource "website"; logged-in users, writes same site + JSON):
*   GET    /api/{db}/website/setup                     are the definitions installed
*   POST   /api/{db}/website/setup                     install them (database managers)
*   POST   /api/{db}/website                           create a website {title, languages}
*   POST   /api/{db}/website/publish                   a module as a standalone page {type, settings, state, title, pageId}
*   GET    /api/{db}/website/{id}                      summary: kind, version, URL, languages, menu tree
*   GET    /api/{db}/website/{id}/validate[?page=]     problems of the format
*   POST   /api/{db}/website/{id}/menu                 {pageId, parentId?}: add a page to the menu
*   DELETE /api/{db}/website/{id}/menu/{page id}       take a page out of the menu
*   DELETE /api/{db}/website/{id}[?pages=1]            delete a standalone page, or a website (with its menu pages)
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

use Heurist\Records\Edit\RecordEditException;
use Heurist\Runtime\ApiResponse;
use Heurist\Runtime\ErrorReporter;
use Heurist\Runtime\RuntimeContext;
use Heurist\Website\PageRenderer;
use Heurist\Website\WebsiteEditService;
use Heurist\Website\WebsiteException;
use Heurist\Website\WebsiteService;
use Heurist\Website\WebsiteSetup;
use Throwable;

/** HTTP boundary of the v4 websites. */
final class WebsiteController
{
    private WebsiteService $service;
    /** @var callable(array):PageRenderer Renderer for a request (pretty or parameter URLs). */
    private $renderer;
    private ?WebsiteSetup $setup;
    private ?WebsiteEditService $edit;
    private RuntimeContext $runtime;
    private ApiResponse $response;
    private ErrorReporter $errors;

    /**
     * @param callable(bool):PageRenderer $renderer Renderer for pretty (true) or parameter URLs.
     * @param WebsiteEditService|null $edit Manager operations (null: read only).
     */
    public function __construct(
        WebsiteService $service,
        callable $renderer,
        RuntimeContext $runtime,
        ?WebsiteSetup $setup = null,
        ?WebsiteEditService $edit = null,
        ?ApiResponse $response = null,
        ?ErrorReporter $errors = null
    ) {
        $this->service = $service;
        $this->renderer = $renderer;
        $this->runtime = $runtime;
        $this->setup = $setup;
        $this->edit = $edit;
        $this->response = $response ?? new ApiResponse();
        $this->errors = $errors ?? new ErrorReporter();
    }

    /**
     * Write a page (or an instance frame).
     *
     * @param array $params site, pageid, frame, lang.
     * @param bool $pretty The request used a /{db}/site/ URL.
     */
    public function showPage(array $params, bool $pretty): void
    {
        try{
            $context = $this->service->context(intval($params['site'] ?? 0), intval($params['pageid'] ?? 0),
                (string)($params['lang'] ?? ''));
            $renderer = ($this->renderer)($pretty);
            $frame = trim((string)($params['frame'] ?? ''));
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

    /**
     * Handle /api/{db}/website/...
     *
     * @param string $method HTTP method.
     * @param array<int,string> $path Segments after "website".
     * @param array $params Request parameters.
     * @param array|null $body Decoded JSON body.
     * @param array $request contentType, origin, host of the request (cross-site checks of writes).
     */
    public function handleApi(string $method, array $path, array $params, ?array $body = null, array $request = array()): void
    {
        try{
            $this->response->send($this->api(strtoupper($method), array_values(array_filter($path, 'strlen')), $params,
                $body ?? array(), $request));
        }catch(WebsiteException $exception){
            $this->response->sendError($exception->status(), 'website', $exception->getMessage());
        }catch(RecordEditException $exception){
            $this->response->send(array('status' => $exception->status(), 'error' => $exception->errorCode(),
                'message' => $exception->getMessage(), 'errors' => $exception->issues()), $exception->status());
        }catch(Throwable $exception){
            $this->errors->report($exception, $this->runtime);
            $this->response->sendError(500, 'server_error', 'Website request failed');
        }
    }

    /**
     * The answer of one API request.
     *
     * @throws WebsiteException|RecordEditException
     */
    private function api(string $method, array $path, array $params, array $body, array $request): array
    {
        $first = (string)($path[0] ?? '');
        if($this->runtime->userId < 1){ throw new WebsiteException(401, 'Log in to manage websites'); }
        if($method !== 'GET'){ self::assertSameSite($method, $request); }

        if($first === 'setup'){
            if($method === 'GET'){ return $this->edit()->status(); }
            self::allow($method, 'POST');
            if($this->setup === null){ throw new WebsiteException(501, 'Setup is not available'); }
            return array('report' => $this->setup->install()) + ($this->edit !== null ? $this->edit->status() : array());
        }
        if($first === ''){
            self::allow($method, 'POST');
            return $this->edit()->create($body);
        }
        if($first === 'publish'){
            self::allow($method, 'POST');
            return $this->edit()->publishModule($body);
        }
        if(!ctype_digit($first)){ throw new WebsiteException(404, 'Unknown website request'); }
        $id = intval($first);
        $action = (string)($path[1] ?? '');

        if($action === ''){
            if($method === 'DELETE'){ return $this->edit()->delete($id, !empty($params['pages'])); }
            self::allow($method, 'GET');
            return $this->edit()->summary($id);
        }
        if($action === 'validate'){
            self::allow($method, 'GET');
            $context = $this->service->context($id, intval($params['page'] ?? 0), (string)($params['lang'] ?? ''));
            return array(
                'id' => $id,
                'page' => intval($context['page']['rec_ID']),
                'language' => $context['language'],
                'languages' => $context['languages'],
                'issues' => $context['issues']
            );
        }
        if($action === 'menu'){
            if($method === 'DELETE'){ return $this->edit()->removeFromMenu($id, intval($path[2] ?? 0)); }
            self::allow($method, 'POST');
            return $this->edit()->addToMenu($id, intval($body['pageId'] ?? 0), intval($body['parentId'] ?? 0));
        }
        throw new WebsiteException(404, 'Unknown website request');
    }

    private function edit(): WebsiteEditService
    {
        if($this->edit === null){ throw new WebsiteException(501, 'Website editing is not available'); }
        return $this->edit;
    }

    private static function allow(string $method, string $allowed): void
    {
        if($method !== $allowed){ throw new WebsiteException(405, 'Use '.$allowed); }
    }

    /** Writes only from the same site, with a JSON body (as the record writes). */
    private static function assertSameSite(string $method, array $request): void
    {
        $origin = trim((string)($request['origin'] ?? ''));
        if($origin === 'null'){ throw new WebsiteException(403, 'Requests from another site are not allowed'); }
        if($origin !== ''){
            $host = strtolower((string)parse_url('http://'.(string)($request['host'] ?? ''), PHP_URL_HOST));
            if(strtolower((string)parse_url($origin, PHP_URL_HOST)) !== $host){
                throw new WebsiteException(403, 'Requests from another site are not allowed');
            }
        }
        $type = (string)($request['contentType'] ?? '');
        if($method !== 'DELETE' && $type !== '' && stripos($type, 'application/json') !== 0){
            throw new WebsiteException(415, 'Content-Type must be application/json');
        }
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
