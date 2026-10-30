<?php
/**
* WebsiteController.php - Pages of the websites of format v4 (plan 27a)
*
* Pages (FrontController, RequestRouter action "site"):
*   GET /{db}/site/{id}[/{page id}][?lang=xxx]        website home / page, or a standalone page
*   GET /{db}/site/{id}[/{page id}]/frame/{instance}   one module in an iframe ("mount": "iframe")
*   (parameter form: ?db=&site=&pageid=&frame=&lang=)
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
use Heurist\Website\PageRenderer;
use Heurist\Website\WebsiteException;
use Heurist\Website\WebsiteService;
use Throwable;

/** HTTP boundary of the v4 website pages. */
final class WebsiteController
{
    private WebsiteService $service;
    /** @var callable(array):PageRenderer Renderer for a request (pretty or parameter URLs). */
    private $renderer;
    private RuntimeContext $runtime;
    private ApiResponse $response;
    private ErrorReporter $errors;

    /**
     * @param callable(bool):PageRenderer $renderer Renderer for pretty (true) or parameter URLs.
     */
    public function __construct(
        WebsiteService $service,
        callable $renderer,
        RuntimeContext $runtime,
        ?ApiResponse $response = null,
        ?ErrorReporter $errors = null
    ) {
        $this->service = $service;
        $this->renderer = $renderer;
        $this->runtime = $runtime;
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

    /** A plain error page. */
    private static function errorPage(string $message): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1"><title>Heurist website</title>'
            .'<style>body{font:14px Arial,Helvetica,sans-serif;color:#2f2f2f;margin:3em auto;max-width:40em;padding:0 1em}</style>'
            .'</head><body><h1>Heurist website</h1><p>'.htmlspecialchars($message, ENT_QUOTES, 'UTF-8').'</p></body></html>';
    }
}
