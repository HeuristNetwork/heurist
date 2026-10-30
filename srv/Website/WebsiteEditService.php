<?php
/**
* WebsiteEditService.php - Website operations behind the entities "website" and "page" (plans 27b, 27d)
*
* Used by WebsiteEntityHandler (hooks and actions of /api/{db}/website and /page, plan 27d)
* and by the build job:
* - status(): are the fields of the format installed (setup needed)? With the local ids;
* - summary(): website or page with its version, URL, languages and menu tree (meta.website);
* - starterBody() / addStarterPage(): defaults of a new v4 website and its "About" page;
* - importModule(): a module or Explorer as a standalone page (module Publish);
* - assign() / removeFromMenu(): a page into, inside or out of a website menu;
* - shareAccess(): owner and visibility of a website passed on to its menu pages;
* - deletablePages() / deletePages(): menu pages deleted with a website.
* Records are written with RecordSaveService / RecordDeleteService (checks,
* rights, titles), never by SQL. Every save is its own transaction.
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

use Heurist\Records\Edit\RecordDeleteService;
use Heurist\Records\Edit\RecordEditException;
use Heurist\Records\Edit\RecordInput;
use Heurist\Records\Edit\RecordPermissionService;
use Heurist\Records\Edit\RecordSaveService;
use Heurist\Runtime\RuntimeContext;
use Heurist\Utilities\LanguageCodes;
use Throwable;

/** Defaults, menus, access and deletion of v4 websites and pages. */
final class WebsiteEditService
{
    private WebsiteRepository $repository;
    private RecordSaveService $save;
    private RecordDeleteService $delete;
    private RecordPermissionService $permissions;
    private LanguageCodes $languages;
    private SiteUrls $urls;
    private RuntimeContext $runtime;

    public function __construct(
        WebsiteRepository $repository,
        RecordSaveService $save,
        RecordDeleteService $delete,
        RecordPermissionService $permissions,
        LanguageCodes $languages,
        SiteUrls $urls,
        RuntimeContext $runtime
    ) {
        $this->repository = $repository;
        $this->save = $save;
        $this->delete = $delete;
        $this->permissions = $permissions;
        $this->languages = $languages;
        $this->urls = $urls;
        $this->runtime = $runtime;
    }

    /**
     * Whether the definitions of the format are in place (fields in the structures of
     * 99-51 / 99-52, the logo of a website optional), and the local ids of the record types,
     * fields and terms the entities use (WebsiteRepository::CONCEPTS names).
     *
     * @return array{installed:bool,missing:array<int,string>,canSetup:bool,codes:array<string,int>}
     */
    public function status(): array
    {
        $definitions = $this->repository->definitions();
        $codes = $this->repository->codes();
        $missing = array();
        foreach(array('home' => '99-51', 'page' => '99-52') as $name => $rectypeCode){
            if($codes[$name] < 1){ $missing[] = 'record type '.$rectypeCode; continue; }
            $structure = $definitions->structureRows($codes[$name]);
            foreach(WebsiteSetup::FIELDS as $fieldCode){
                $fieldId = $definitions->localId('dty', $fieldCode);
                if($fieldId < 1 || !isset($structure[$fieldId])){ $missing[] = 'field '.$fieldCode.' in '.$rectypeCode; }
            }
            if($name === 'home' && $codes['logo'] > 0 && ($structure[$codes['logo']]['requirement'] ?? '') === 'required'){
                $missing[] = 'optional logo 2-38 in 99-51';
            }
        }
        return array('installed' => empty($missing), 'missing' => $missing, 'canSetup' => $this->runtime->isAdmin,
            'codes' => $codes);
    }

    /**
     * Local ids of the record types, fields and terms of websites (WebsiteRepository::CONCEPTS names).
     *
     * @return array<string,int>
     */
    public function codes(): array
    {
        return $this->repository->codes();
    }

    /**
     * A website or page for the manager (meta.website of GET /website/{id} and /page/{id}).
     *
     * @return array{id:int,kind:string,version:string,v4:bool,title:string,languages:array<int,string>,url:string,
     *         canEdit:bool,pages:array<int,array>}
     * @throws WebsiteException 404 when the record is not visible or not a website / page.
     */
    public function summary(int $id): array
    {
        $codes = $this->repository->codes();
        $record = $this->recordOf($id);
        $type = intval($record['rec_RecTypeID']);
        $version = WebsiteRepository::value($record, $codes['version']);
        $v4 = $version === WebsiteService::VERSION;
        $standalone = $type === $codes['page']
            && in_array((string)$codes['standalonePage'], array_map('strval', WebsiteRepository::values($record, $codes['pageType'])), true);
        $kind = $type === $codes['home'] ? 'website' : ($standalone ? 'standalone' : 'page');
        $pages = array();
        $languages = array();
        if($kind === 'website'){
            $menu = $this->repository->menu($record);
            $pages = $this->pageTree($menu['tree'], $menu['pages']);
            $languages = $this->siteLanguages($record);
        }
        $url = $kind === 'page' ? '' : ($v4 ? $this->urls->page($id) : $this->urls->legacy($id));
        return array(
            'id' => $id,
            'kind' => $kind,
            'version' => $version,
            'v4' => $v4,
            'title' => $this->name($record),
            'languages' => array_map('strtolower', $languages),
            'url' => $url,
            'canEdit' => $this->canEdit($id),
            'pages' => $pages
        );
    }

    /**
     * The body of POST /website/0 with the defaults of a new v4 website: version 4, header with
     * a menu, home content, footer, configuration (WebsiteStarter) and public visibility. Values
     * given in the body are kept. Language codes ("eng") in the language field become its terms.
     *
     * @param array $body Record object (title in DT_NAME required).
     * @return array The body to save.
     * @throws WebsiteException 400 without a title or with an unknown language, 409 without the definitions.
     */
    public function starterBody(array $body): array
    {
        $codes = $this->repository->codes();
        if(!$this->status()['installed']){
            throw new WebsiteException(409, 'The website definitions are not installed in this database');
        }
        $details = is_array($body['details'] ?? null) ? $body['details'] : array();
        $title = trim(strip_tags((string)self::first($details[$codes['name']] ?? '')));
        if($title === ''){ throw new WebsiteException(400, 'The website needs a title'); }
        $site = WebsiteStarter::website($title);
        $defaults = array(
            $codes['version'] => WebsiteService::VERSION,
            $codes['header'] => $site['header'],
            $codes['footer'] => $site['footer'],
            $codes['content'] => $site['content'],
            $codes['configuration'] => $site['configuration']
        );
        foreach($defaults as $fieldId => $value){
            if(!self::hasValue($details, $fieldId)){ $details[$fieldId] = $value; }
        }
        $body['details'] = $this->languageDetails($details);
        if(!array_key_exists('rec_NonOwnerVisibility', $body)){ $body['rec_NonOwnerVisibility'] = 'public'; }
        return $body;
    }

    /**
     * Language codes in the language field of a body become its terms (PUT / PATCH of a website).
     *
     * @throws WebsiteException 400 for a language without a term.
     */
    public function languageDetails(array $details): array
    {
        $field = $this->repository->codes()['languages'];
        if($field < 1 || !isset($details[$field])){ return $details; }
        $values = is_array($details[$field]) ? $details[$field] : array($details[$field]);
        $codes = array_filter($values, static function($value): bool { return is_string($value) && !ctype_digit($value); });
        if(empty($codes)){ return $details; }
        $terms = $this->languageTerms(array_values($codes));
        $details[$field] = array_values(array_unique(array_merge(array_map('intval', array_diff_key($values, $codes)), $terms)));
        return $details;
    }

    /**
     * The "About" page of a new website (the top menu is required, so it is written before the
     * website), with the owner and visibility the website body gives (plan 27c §10). Nothing when
     * the body has a top menu.
     *
     * @param array $body Website body after starterBody().
     * @return array The body with the page in its top menu.
     * @throws RecordEditException
     */
    public function addStarterPage(array $body): array
    {
        $codes = $this->repository->codes();
        if(self::hasValue($body['details'] ?? array(), $codes['topMenu'])){ return $body; }
        $starter = WebsiteStarter::page();
        $details = array(
            $codes['name'] => $starter['title'],
            $codes['version'] => WebsiteService::VERSION,
            $codes['content'] => $starter['content'],
            $codes['configuration'] => $starter['configuration']
        );
        if($codes['menuPage'] > 0){ $details[$codes['pageType']] = array($codes['menuPage']); }
        $page = array('rec_ID' => 0, 'rec_RecTypeID' => $codes['page'], 'details' => $details,
            'rec_NonOwnerVisibility' => (string)($body['rec_NonOwnerVisibility'] ?? 'public'));
        if(isset($body['rec_OwnerUGrpID'])){ $page['rec_OwnerUGrpID'] = intval($body['rec_OwnerUGrpID']); }
        $result = $this->save->save('POST', 0, RecordInput::fromBody($page));
        $body['details'][$codes['topMenu']] = array(intval($result['recordId']));
        return $body;
    }

    /** Delete the starter page of a website that was not saved (addStarterPage()). */
    public function removeStarterPage(array $body): void
    {
        $ids = $body['details'][$this->repository->codes()['topMenu']] ?? array();
        foreach(is_array($ids) ? $ids : array($ids) as $id){
            try{ $this->delete->delete(intval($id), true); }catch(Throwable $ignored){ /* reported by the first error */ }
        }
    }

    /**
     * Import a module (or Explorer) configuration into a standalone page (plan 27b §6, 27d): a
     * new page (id 0), or the module's instance replaced in a page published before (its other
     * content is kept).
     *
     * @param array $input type (module), settings, state (bootstrap envelope of the module), title.
     * @return array{id:int,url:string,title:string,created:bool,issues:array}
     * @throws WebsiteException|RecordEditException
     */
    public function importModule(int $pageId, array $input): array
    {
        $codes = $this->repository->codes();
        $type = strtolower(trim((string)($input['type'] ?? '')));
        if(!in_array($type, CmsValidator::TYPES['module'], true)){
            throw new WebsiteException(400, 'Unknown module type "'.$type.'"');
        }
        if(!$this->status()['installed']){
            throw new WebsiteException(409, 'The website definitions are not installed in this database');
        }
        $instance = array(
            'kind' => 'module',
            'type' => $type,
            'configVersion' => CmsValidator::CONFIG_VERSION,
            'settings' => is_array($input['settings'] ?? null) && !empty($input['settings']) ? $input['settings'] : new \stdClass(),
            'state' => is_array($input['state'] ?? null) && !empty($input['state']) ? $input['state'] : null
        );
        $title = trim(strip_tags((string)($input['title'] ?? '')));
        if($title === ''){ $title = ucfirst($type).' '.gmdate('Y-m-d'); }

        if($pageId < 1){
            $node = WebsiteStarter::id('n');
            $instanceId = WebsiteStarter::id('i');
            $config = array('schema' => CmsValidator::SCHEMA, 'instances' => array($instanceId => $instance),
                'styles' => array('nodes' => array($node => array('height' => '100vh'))));
            $details = array(
                $codes['name'] => $title,
                $codes['version'] => WebsiteService::VERSION,
                $codes['content'] => self::host($node, $instanceId),
                $codes['configuration'] => self::json($config)
            );
            if($codes['standalonePage'] > 0){ $details[$codes['pageType']] = array($codes['standalonePage']); }
            $result = $this->save->save('POST', 0, RecordInput::fromBody(array(
                'rec_ID' => 0, 'rec_RecTypeID' => $codes['page'], 'rec_NonOwnerVisibility' => 'public', 'details' => $details
            )));
            $id = intval($result['recordId']);
            return array('id' => $id, 'url' => $this->urls->page($id), 'title' => $title, 'created' => true,
                'issues' => $result['issues']);
        }

        $page = $this->editableV4($pageId, $codes['page']);
        if($this->summary($pageId)['kind'] !== 'standalone'){
            throw new WebsiteException(409, 'Page '.$pageId.' is in a website menu; publish as a new page');
        }
        $parsed = CmsValidator::parse(WebsiteRepository::value($page, $codes['configuration']));
        if(CmsValidator::hasError($parsed['issues'])){
            throw new WebsiteException(409, 'The configuration of page '.$pageId.' is not valid; publish as a new page');
        }
        $config = $parsed['config'];
        $html = WebsiteRepository::value($page, $codes['content']);
        // the first instance of this module that still has its host
        $instanceId = null;
        foreach($config['instances'] as $id => $existing){
            if(is_array($existing) && ($existing['kind'] ?? '') === 'module' && ($existing['type'] ?? '') === $type
                && strpos($html, 'data-h-instance="'.$id.'"') !== false){
                $instanceId = (string)$id;
                if(isset($existing['mount'])){ $instance['mount'] = $existing['mount']; }
                break;
            }
        }
        if($instanceId === null){
            $instanceId = WebsiteStarter::id('i');
            $html .= self::host(WebsiteStarter::id('n'), $instanceId);
        }
        $config['instances'][$instanceId] = $instance;
        $names = WebsiteRepository::values($page, $codes['name']);
        $translated = array_values(array_filter($names, static function($value): bool {
            return is_string($value) && preg_match('/^[a-z]{2,3}:/i', $value) === 1;
        }));
        $result = $this->save->save('PATCH', $pageId, RecordInput::fromBody(array('rec_ID' => $pageId, 'details' => array(
            $codes['name'] => array_merge(array($title), $translated),
            $codes['content'] => $html,
            $codes['configuration'] => self::json($config)
        ))));
        return array('id' => $pageId, 'url' => $this->urls->page($pageId), 'title' => $title, 'created' => false,
            'issues' => $result['issues']);
    }

    /** Host element of a module instance. */
    private static function host(string $node, string $instance): string
    {
        return '<div data-h-node="'.$node.'" data-h-instance="'.$instance.'"><p>This page needs JavaScript.</p></div>';
    }

    private static function json(array $config): string
    {
        foreach(array('instances', 'styles') as $part){
            if(isset($config[$part]) && is_array($config[$part]) && empty($config[$part])){ $config[$part] = new \stdClass(); }
        }
        return (string)json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Put a page into the menu of a website, or move it inside the menu: into the top menu or a
     * page's sub menu, at a position (null or out of range: at the end). A page added to the
     * menu becomes a menu page with the owner and visibility of the website (plan 27c §10).
     *
     * @param int $parentId 0 (or the website id): top menu; else a page of this website.
     * @param int|null $index Position in the parent's menu (0 = first).
     * @return array Summary of the website.
     * @throws WebsiteException|RecordEditException
     */
    public function assign(int $siteId, int $pageId, int $parentId = 0, ?int $index = null): array
    {
        $codes = $this->repository->codes();
        $site = $this->editableV4($siteId, $codes['home']);
        $this->editableV4($pageId, $codes['page']);
        $menu = $this->repository->menu($site);
        $inMenu = isset($menu['pages'][$pageId]);
        if($parentId === $siteId){ $parentId = 0; }
        if($parentId > 0){
            if(!isset($menu['pages'][$parentId])){
                throw new WebsiteException(404, 'Page '.$parentId.' is not a page of website '.$siteId);
            }
            if($parentId === $pageId || in_array($parentId, self::descendants($menu['tree'], $pageId), true)){
                throw new WebsiteException(409, 'A page can not be put under itself or its sub pages');
            }
            $this->assertEditable($parentId);
        }
        $parents = array($siteId => array($site, $codes['topMenu']));
        foreach($menu['pages'] as $id => $record){ $parents[$id] = array($record, $codes['subMenu']); }
        $target = $parentId > 0 ? $parentId : $siteId;

        // out of its old place (a move)
        foreach($parents as $id => list($record, $field)){
            $ids = WebsiteRepository::ids($record, $field);
            if($id === $target || !in_array($pageId, $ids, true)){ continue; }
            $this->assertEditable($id);
            $this->patch($id, array('details' => array($field => array_values(array_diff($ids, array($pageId))))));
        }
        list($record, $field) = $parents[$target];
        $ids = array_values(array_diff(WebsiteRepository::ids($record, $field), array($pageId)));
        $at = $index === null || $index < 0 || $index > count($ids) ? count($ids) : $index;
        array_splice($ids, $at, 0, array($pageId));
        $this->patch($target, array('details' => array($field => $ids)));
        if(!$inMenu){
            $this->patch($pageId, array(
                'details' => array($codes['pageType'] => $codes['menuPage'] > 0 ? array($codes['menuPage']) : array())
            ) + $this->access($siteId, $site));
        }
        return $this->summary($siteId);
    }

    /**
     * Take a page out of the menu of a website; the page record stays (a standalone page).
     *
     * @return array Summary of the website.
     * @throws WebsiteException|RecordEditException
     */
    public function removeFromMenu(int $siteId, int $pageId): array
    {
        $codes = $this->repository->codes();
        $site = $this->editableV4($siteId, $codes['home']);
        $menu = $this->repository->menu($site);
        $parents = array($siteId => array($site, $codes['topMenu']));
        foreach($menu['pages'] as $id => $record){ $parents[$id] = array($record, $codes['subMenu']); }
        $found = false;
        foreach($parents as $id => list($record, $field)){
            $ids = WebsiteRepository::ids($record, $field);
            if(!in_array($pageId, $ids, true)){ continue; }
            $this->assertEditable($id);
            $this->patch($id, array('details' => array($field => array_values(array_diff($ids, array($pageId))))));
            $found = true;
        }
        if(!$found){ throw new WebsiteException(404, 'Page '.$pageId.' is not in the menu of website '.$siteId); }
        $this->patch($pageId, array(
            'details' => array($codes['pageType'] => $codes['standalonePage'] > 0 ? array($codes['standalonePage']) : array())
        ));
        return $this->summary($siteId);
    }

    /**
     * Pass the owner and visibility of a website on to its menu pages (plan 27c §10). Pages the
     * user may not change are left as they are, with a warning.
     *
     * @return array<int,array> Warnings (record issue format).
     * @throws RecordEditException
     */
    public function shareAccess(int $siteId): array
    {
        $codes = $this->repository->codes();
        $site = $this->recordOf($siteId);
        if(intval($site['rec_RecTypeID']) !== $codes['home']){ return array(); }
        $access = $this->access($siteId, $site);
        $issues = array();
        foreach($this->repository->menu($site)['pages'] as $pageId => $page){
            $current = $this->permissions->owners(intval($pageId));
            if($current !== null && intval($current['owner']) === $access['rec_OwnerUGrpID']
                && (string)($page['rec_NonOwnerVisibility'] ?? '') === $access['rec_NonOwnerVisibility']){
                continue;
            }
            if(!$this->canEdit(intval($pageId))){
                $issues[] = array('field' => 'rec_OwnerUGrpID', 'index' => null, 'code' => 'website', 'severity' => 'warning',
                    'message' => 'Page '.$pageId.' keeps its owner and visibility: you can not change it');
                continue;
            }
            $this->patch(intval($pageId), $access);
        }
        return $issues;
    }

    /**
     * The menu pages deleted with a website: those no record outside the site points to and the
     * user may delete; the others are kept.
     *
     * @return array{delete:array<int,int>,kept:array<int,int>}
     * @throws WebsiteException 409 for a website that is not of format version 4.
     */
    public function deletablePages(int $siteId): array
    {
        $codes = $this->repository->codes();
        $record = $this->recordOf($siteId);
        if(intval($record['rec_RecTypeID']) !== $codes['home']){ return array('delete' => array(), 'kept' => array()); }
        if(WebsiteRepository::value($record, $codes['version']) !== WebsiteService::VERSION){
            throw new WebsiteException(409, 'Website '.$siteId.' is not of format version 4; delete its pages in the legacy website editor');
        }
        $pages = array_keys($this->repository->menu($record)['pages']);
        $site = array_merge(array($siteId), $pages);
        $delete = array();
        $kept = array();
        foreach($pages as $pageId){
            $outside = array_filter($this->delete->references($pageId)['pointers'], static function(array $pointer) use ($site): bool {
                return !in_array(intval($pointer['rec_ID']), $site, true);
            });
            if(empty($outside) && $this->canEdit($pageId)){ $delete[] = intval($pageId); }else{ $kept[] = intval($pageId); }
        }
        return array('delete' => $delete, 'kept' => $kept);
    }

    /**
     * Delete menu pages of a deleted website (references between them are removed).
     *
     * @param array<int,int> $ids From deletablePages().
     * @return array<int,int> Deleted ids.
     */
    public function deletePages(array $ids): array
    {
        $deleted = array();
        foreach($ids as $pageId){
            $this->delete->delete(intval($pageId), true);
            $deleted[] = intval($pageId);
        }
        return $deleted;
    }

    /**
     * A record the user may build (job "website-build"): a v4 website or standalone page.
     *
     * @return array Summary.
     * @throws WebsiteException
     */
    public function buildable(int $id): array
    {
        $summary = $this->summary($id);
        if(!$summary['v4'] || $summary['kind'] === 'page'){
            throw new WebsiteException(400, 'Only a website or a standalone page of format version 4 can be generated');
        }
        if(!$summary['canEdit']){
            throw new WebsiteException(403, 'You can not generate website '.$id);
        }
        return $summary;
    }

    /** Pages of the menu tree with titles. */
    private function pageTree(array $tree, array $pages): array
    {
        $result = array();
        foreach($tree as $item){
            $page = $pages[$item['id']] ?? null;
            if($page === null){ continue; }
            $result[] = array(
                'id' => intval($item['id']),
                'title' => $this->name($page),
                'children' => $this->pageTree($item['children'], $pages)
            );
        }
        return $result;
    }

    /** Ids of the sub pages (all levels) of a page in a menu tree. */
    private static function descendants(array $tree, int $pageId, bool $inside = false): array
    {
        $result = array();
        foreach($tree as $item){
            $id = intval($item['id']);
            if($inside){ $result[] = $id; }
            $result = array_merge($result, self::descendants($item['children'], $pageId, $inside || $id === $pageId));
        }
        return $result;
    }

    /** Name of a website or page in the default language, as text (rec_Title of CMS records is HTML from the title mask). */
    private function name(array $record): string
    {
        $value = $this->languages->valueIn(WebsiteRepository::values($record, $this->repository->codes()['name']), 'ENG');
        $text = trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return $text !== '' ? $text : trim(strip_tags((string)($record['rec_Title'] ?? '')));
    }

    /** A visible website or page record. */
    private function recordOf(int $id): array
    {
        $codes = $this->repository->codes();
        $record = $id > 0 ? $this->repository->record($id) : null;
        $type = intval($record['rec_RecTypeID'] ?? 0);
        if($record === null || ($type !== $codes['home'] && $type !== $codes['page'])){
            throw new WebsiteException(404, 'Website or page '.$id.' was not found');
        }
        return $record;
    }

    /** A v4 record of the given type that the user may change. */
    private function editableV4(int $id, int $rectype): array
    {
        $record = $this->recordOf($id);
        if(intval($record['rec_RecTypeID']) !== $rectype){
            throw new WebsiteException(400, 'Record '.$id.' is not a '.($rectype === $this->repository->codes()['home'] ? 'website' : 'page'));
        }
        if(WebsiteRepository::value($record, $this->repository->codes()['version']) !== WebsiteService::VERSION){
            throw new WebsiteException(409, 'Record '.$id.' is not of format version 4');
        }
        $this->assertEditable($id);
        return $record;
    }

    private function assertEditable(int $id): void
    {
        if(!$this->canEdit($id)){
            throw new WebsiteException(403, 'You can not change record '.$id
                .'; you must be the database administrator or a member of a group that owns it');
        }
    }

    private function canEdit(int $id): bool
    {
        if($this->runtime->userId < 1){ return false; }
        $owners = $this->permissions->owners($id);
        return $owners !== null && $this->permissions->canEditOwned($owners['owner'], $owners['editGroups']);
    }

    /** Owner and visibility of a website, as record headers. */
    private function access(int $siteId, array $site): array
    {
        $owners = $this->permissions->owners($siteId);
        return array(
            'rec_OwnerUGrpID' => intval($owners['owner'] ?? $this->runtime->userId),
            'rec_NonOwnerVisibility' => (string)($site['rec_NonOwnerVisibility'] ?? 'public')
        );
    }

    /** Change some headers and fields of a record (the others stay). */
    private function patch(int $id, array $body): void
    {
        $this->save->save('PATCH', $id, RecordInput::fromBody(array('rec_ID' => $id) + $body));
    }

    /** Whether a field of a body has a non-empty value. */
    private static function hasValue(array $details, int $fieldId): bool
    {
        $value = $details[$fieldId] ?? null;
        return is_array($value) ? !empty($value) : ($value !== null && $value !== '');
    }

    /** First value of a field of a body. */
    private static function first($value)
    {
        while(is_array($value)){ $value = reset($value); }
        return $value === false ? '' : $value;
    }

    /** Website languages as 3-letter codes (DT_LANGUAGE terms), the first is the default. */
    private function siteLanguages(array $site): array
    {
        $codes = $this->repository->codes();
        $result = array();
        foreach(WebsiteRepository::values($site, $codes['languages']) as $termId){
            $term = $this->repository->definitions()->term(intval($termId));
            $code = $term !== null ? $this->languages->code3((string)($term['code'] ?? '')) : null;
            if($code !== null && !in_array($code, $result, true)){ $result[] = $code; }
        }
        return $result;
    }

    /**
     * Terms of the language field for language codes (order kept).
     *
     * @throws WebsiteException 400 for a language without a term.
     */
    private function languageTerms(array $languages): array
    {
        if(empty($languages)){ return array(); }
        $definitions = $this->repository->definitions();
        $field = $definitions->field($this->repository->codes()['languages']);
        $byCode = array();
        foreach($definitions->terms($definitions->termDescendants($field['vocabulary'] ?? array())) as $id => $term){
            $code = $this->languages->code3((string)($term['code'] ?? ''));
            if($code !== null && !isset($byCode[$code])){ $byCode[$code] = intval($id); }
        }
        $result = array();
        foreach($languages as $language){
            $code = $this->languages->code3((string)$language);
            if($code === null || !isset($byCode[$code])){
                throw new WebsiteException(400, 'Language "'.$language.'" is not in the language vocabulary of this database');
            }
            if(!in_array($byCode[$code], $result, true)){ $result[] = $byCode[$code]; }
        }
        return $result;
    }
}
