<?php
/**
* ExternalServiceController.php - External services of the server (special endpoint, plan 28)
*
* POST /api/{db}/external/translate {"text":"...","target":"fr","source":"en","format":"text|html"}
* answers {"text":"<translation>"} (TranslationService; the server chooses the provider).
* Logged-in users of an association member only; the same-site check of writes applies
* (the request uses a paid service with the user's cookie).
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

use Heurist\ExternalService\TranslationService;
use Heurist\Records\Edit\RecordEditException;
use Heurist\Runtime\ApiResponse;
use Heurist\Runtime\RuntimeContext;

/** HTTP adapter of the external services (/external/{service}). */
final class ExternalServiceController
{
    private RuntimeContext $runtime;
    /** @var \Closure():TranslationService Made only when a text is translated. */
    private \Closure $translationService;
    private ApiResponse $response;

    /** @param \Closure():TranslationService $translationService */
    public function __construct(RuntimeContext $runtime, \Closure $translationService, ?ApiResponse $response = null)
    {
        $this->runtime = $runtime;
        $this->translationService = $translationService;
        $this->response = $response ?? new ApiResponse();
    }

    /**
     * Serve /external/{service}.
     *
     * @param string $method HTTP method.
     * @param array<int,string> $path Path after /external.
     * @param array|null $json JSON body.
     * @param array $request contentType, origin, host (SameSiteGuard).
     */
    public function handleRequest(string $method, array $path, ?array $json, array $request = array()): void
    {
        $service = (string)($path[0] ?? '');
        if($service !== 'translate' || count($path) > 1){
            $this->response->sendError(404, 'not_found', 'Unknown external service: '.$service);
            return;
        }
        if($method !== 'POST'){
            header('Allow: POST');
            $this->response->sendError(405, 'method_not_allowed', 'Method not allowed');
            return;
        }
        try{
            SameSiteGuard::assert($this->runtime, $request, true, 'Please sign in to translate text.');
        }catch(RecordEditException $e){
            $this->response->sendError($e->status(), $e->errorCode(), $e->getMessage());
            return;
        }
        $this->translate($json ?? array());
    }

    /** Translate the text of the body. */
    private function translate(array $body): void
    {
        foreach(array('text', 'target', 'source', 'format') as $key){
            if(array_key_exists($key, $body) && $body[$key] !== null && !is_string($body[$key])){
                $this->response->sendError(400, 'invalid_request', 'Parameter '.$key.' must be a string');
                return;
            }
        }
        $text = (string)($body['text'] ?? '');
        $target = trim((string)($body['target'] ?? ''));
        if($target === ''){
            $this->response->sendError(400, 'invalid_request', 'Target language (target) is not defined');
            return;
        }
        $source = trim((string)($body['source'] ?? ''));
        $format = (string)($body['format'] ?? 'html');
        try{
            $translated = ($this->translationService)()->translate($text, $target, $source === '' ? null : $source, $format);
        }catch(\InvalidArgumentException $e){
            $this->response->sendError(400, 'invalid_request', $e->getMessage());
            return;
        }catch(\RuntimeException $e){
            // 401 / 403 of the service policy; else the provider failed or none is configured
            $status = $e->getCode();
            if($status === 401){
                $this->response->sendError(401, 'unauthorized', $e->getMessage());
            }elseif($status === 403){
                $this->response->sendError(403, 'access_denied', $e->getMessage());
            }else{
                $this->response->sendError(502, 'service_error', $e->getMessage());
            }
            return;
        }
        if(defined('HEADER_CORS_POLICY')){ header(HEADER_CORS_POLICY); }
        $this->response->send(array('text' => $translated));
    }
}
