<?php
/**
* WebsiteEntityHandler.php - Hooks and actions of the entities "website" and "page" (plan 27d)
*
* /api/{db}/website/... (RT_CMS_HOME) and /api/{db}/page/... (RT_CMS_PAGE) are served by the
* generic record controller (RecordEditController::executeEntity); this handler adds:
*   POST   /website/0                      a new v4 website: starter header, content, footer,
*                                          configuration, public; then an "About" page in its menu
*   PUT|PATCH /website/{id}                owner and visibility passed on to the menu pages;
*                                          language codes in DT_LANGUAGE become terms
*   DELETE /website/{id}[?pages=1]         with pages=1 also the menu pages no other record uses
*   GET    /website/{id}, /page/{id}       meta.website: kind, version, URL, languages, menu tree
*   GET    /website/{id}/validate[?page=&lang=], /page/{id}/validate   problems of the format
*   GET|POST /website/setup, /page/setup   are the definitions installed (with their local ids) /
*                                          install them (database managers)
*   POST   /website/{id}/assign {page, parent?, index?}   put a page into the menu, or move it
*   DELETE /website/{id}/assign {page} (or ?page=)        take a page out of the menu
*   POST   /page/{0|id}/import {type, settings, state, title}   module configuration as a
*                                          standalone page (module Publish)
* POST /{website|page}/{id}/execute (the build job) is routed by api.php to the jobs.
* New pages are public; their owner is the user (a page added to a website menu gets the
* website's owner and visibility).
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

use Heurist\Records\Edit\RecordEditException;
use Heurist\Records\Entity\RecordEntityHandler;

/** Website and page entities. */
final class WebsiteEntityHandler extends RecordEntityHandler
{
    /** "website" or "page". */
    private string $kind;
    private WebsiteEditService $edit;
    private WebsiteService $service;
    private ?WebsiteSetup $setup;
    /** A starter page was written for the website being inserted (removed if its save fails). */
    private bool $starter = false;

    public function __construct(string $kind, WebsiteEditService $edit, WebsiteService $service, ?WebsiteSetup $setup = null)
    {
        $this->kind = $kind;
        $this->edit = $edit;
        $this->service = $service;
        $this->setup = $setup;
    }

    public function beforeSave(string $method, int $id, array $body, array $params, bool $dryRun = false): array
    {
        return $this->run(function() use ($method, $body, $dryRun): array {
            $record = isset($body['records'][0]) && is_array($body['records'][0]) ? $body['records'][0] : $body;
            if($this->kind === 'website' && $method === 'POST'){
                $record = $this->edit->starterBody($record);
                if(!$dryRun){
                    $given = $record['details'][$this->edit->codes()['topMenu']] ?? null;
                    $record = $this->edit->addStarterPage($record);
                    $this->starter = empty($given);
                }
            }elseif($this->kind === 'website' && is_array($record['details'] ?? null)){
                $record['details'] = $this->edit->languageDetails($record['details']);
            }elseif($this->kind === 'page' && $method === 'POST' && !array_key_exists('rec_NonOwnerVisibility', $record)){
                $record['rec_NonOwnerVisibility'] = 'public';
            }
            if(isset($body['records'][0])){
                $body['records'][0] = $record;
                return $body;
            }
            return $record;
        });
    }

    public function saveFailed(string $method, array $body): void
    {
        if(!$this->starter){ return; }
        $this->starter = false;
        $this->edit->removeStarterPage(isset($body['records'][0]) && is_array($body['records'][0]) ? $body['records'][0] : $body);
    }

    public function afterSave(string $method, int $id, array $body, array $params): array
    {
        $this->starter = false;
        if($this->kind !== 'website' || $method === 'POST'){ return array(); }
        $record = isset($body['records'][0]) && is_array($body['records'][0]) ? $body['records'][0] : $body;
        return $this->run(function() use ($id, $record): array {
            if(array_key_exists('rec_OwnerUGrpID', $record) || array_key_exists('rec_NonOwnerVisibility', $record)){
                return $this->edit->shareAccess($id);
            }
            return array();
        });
    }

    public function beforeDelete(int $id, array $params): array
    {
        if($this->kind !== 'website' || empty($params['pages'])){ return array(); }
        return $this->run(function() use ($id): array { return $this->edit->deletablePages($id); });
    }

    public function afterDelete(int $id, array $params, array $state, array $result): array
    {
        if(empty($state)){ return $result; }
        $deleted = $this->edit->deletePages($state['delete'] ?? array());
        $result['deleted'] = array_values(array_unique(array_merge($result['deleted'], array_map('strval', $deleted))));
        $result['kept'] = array_map('strval', $state['kept'] ?? array());
        return $result;
    }

    public function describe(int $id): array
    {
        return $this->run(function() use ($id): array { return array('website' => $this->edit->summary($id)); });
    }

    public function validate(int $id, array $params): array
    {
        return $this->run(function() use ($id, $params): array {
            $context = $this->service->context($id, intval($params['page'] ?? 0), (string)($params['lang'] ?? ''));
            return $context['issues'];
        });
    }

    public function action(string $action, string $method, int $id, array $params, array $body): array
    {
        return $this->run(function() use ($action, $method, $id, $params, $body): array {
            if($action === 'setup' && $id === 0){
                if($method === 'GET'){ return $this->edit->status(); }
                self::allow($method, array('POST'));
                if($this->setup === null){ throw new WebsiteException(501, 'Setup is not available'); }
                return array('report' => $this->setup->install()) + $this->edit->status();
            }
            if($action === 'assign' && $this->kind === 'website' && $id > 0){
                $page = intval($body['page'] ?? $params['page'] ?? 0);
                if($method === 'DELETE'){ return $this->edit->removeFromMenu($id, $page); }
                self::allow($method, array('POST'));
                $index = isset($body['index']) && $body['index'] !== null && $body['index'] !== '' ? intval($body['index']) : null;
                return $this->edit->assign($id, $page, intval($body['parent'] ?? 0), $index);
            }
            if($action === 'import' && $this->kind === 'page'){
                self::allow($method, array('POST'));
                return $this->edit->importModule(max(0, $id), $body);
            }
            throw new RecordEditException(404, 'not_found', 'Unknown '.$this->kind.' action: '.$action);
        });
    }

    /**
     * Run a website operation; WebsiteException becomes RecordEditException (the record
     * controller answers those).
     */
    private function run(callable $operation): array
    {
        try{
            return $operation();
        }catch(WebsiteException $exception){
            throw new RecordEditException($exception->status(), 'website', $exception->getMessage());
        }
    }
}
