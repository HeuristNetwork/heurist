<?php
/**
* WebsiteEditService.php - Website manager operations (plan 27b §5)
*
* Used by the Website manager of heurist-explorer (apps/website) through
* /api/{db}/website, later also by the AI agent API (plan 27, phase 7):
* - status(): are the fields of the format installed (setup needed)?
* - summary(): website or page with its version, URL, languages and menu tree;
* - create(): a new v4 website (WebsiteStarter) with one "About" page;
* - publishModule(): a module or Explorer as a standalone page (module Publish);
* - addToMenu() / removeFromMenu(): a page into or out of a website menu;
* - delete(): a standalone page, or a website with the menu pages no other
*   record points to.
* Records are written with RecordSaveService / RecordDeleteService (checks,
* rights, titles), never by SQL. Every save is its own transaction: when a
* later save of create() fails, the records written before are deleted.
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

/** Create, inspect, link into menus and delete v4 websites and pages. */
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
     * 99-51 / 99-52, the logo of a website optional).
     *
     * @return array{installed:bool,missing:array<int,string>,canSetup:bool}
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
        return array('installed' => empty($missing), 'missing' => $missing, 'canSetup' => $this->runtime->isAdmin);
    }

    /**
     * A website or page for the manager.
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
     * The standalone v4 pages the user may change that hold an instance of a module (plan 29:
     * *Update the page* of the publication form), newest first.
     *
     * @param string $module Module type (CmsValidator::TYPES['module']).
     * @return array{pages:array<int,array{id:int,title:string,url:string,modified:string}>}
     * @throws WebsiteException 400 for an unknown module type.
     */
    public function pages(string $module): array
    {
        $type = strtolower(trim($module));
        if(!in_array($type, CmsValidator::TYPES['module'], true)){
            throw new WebsiteException(400, 'Unknown module type "'.$type.'"');
        }
        $codes = $this->repository->codes();
        $pages = array();
        foreach($this->repository->standalonePages() as $id => $record){
            if(WebsiteRepository::value($record, $codes['version']) !== WebsiteService::VERSION || !$this->canEdit($id)){
                continue;
            }
            $parsed = CmsValidator::parse(WebsiteRepository::value($record, $codes['configuration']));
            $instances = is_array($parsed['config']['instances'] ?? null) ? $parsed['config']['instances'] : array();
            $holds = false;
            foreach($instances as $instance){
                if(is_array($instance) && ($instance['kind'] ?? '') === 'module' && ($instance['type'] ?? '') === $type){
                    $holds = true;
                    break;
                }
            }
            if(!$holds){ continue; }
            $pages[] = array('id' => intval($id), 'title' => $this->name($record), 'url' => $this->urls->page(intval($id)),
                'modified' => (string)($record['rec_Modified'] ?? ''));
        }
        usort($pages, static function(array $a, array $b): int {
            return strcmp($b['modified'], $a['modified']) ?: $b['id'] <=> $a['id'];
        });
        return array('pages' => $pages);
    }

    /**
     * Create a v4 website: home record (header with a menu, home content, footer) and one
     * "About" page in its top menu.
     *
     * @param array $input title (required), languages (3-letter codes, the first is the default).
     * @return array{id:int,pageIds:array<int,int>,url:string,issues:array}
     * @throws WebsiteException|RecordEditException
     */
    public function create(array $input): array
    {
        $codes = $this->repository->codes();
        $title = trim((string)($input['title'] ?? ''));
        if($title === ''){ throw new WebsiteException(400, 'The website needs a title'); }
        if(!$this->status()['installed']){
            throw new WebsiteException(409, 'The website definitions are not installed in this database');
        }
        $languageTerms = $this->languageTerms(is_array($input['languages'] ?? null) ? $input['languages'] : array());

        $starter = WebsiteStarter::page();
        $created = array();
        try{
            $pageDetails = array(
                $codes['name'] => $starter['title'],
                $codes['version'] => WebsiteService::VERSION,
                $codes['content'] => $starter['content'],
                $codes['configuration'] => $starter['configuration']
            );
            if($codes['menuPage'] > 0){ $pageDetails[$codes['pageType']] = array($codes['menuPage']); }
            $pageId = $this->insert($codes['page'], $pageDetails);
            $created[] = $pageId;

            $site = WebsiteStarter::website($title);
            $details = array(
                $codes['name'] => $title,
                $codes['version'] => WebsiteService::VERSION,
                $codes['header'] => $site['header'],
                $codes['footer'] => $site['footer'],
                $codes['content'] => $site['content'],
                $codes['configuration'] => $site['configuration'],
                $codes['topMenu'] => array($pageId)
            );
            if(!empty($languageTerms)){ $details[$codes['languages']] = $languageTerms; }
            $result = $this->save->save('POST', 0, RecordInput::fromBody(array(
                'rec_ID' => 0, 'rec_RecTypeID' => $codes['home'], 'rec_NonOwnerVisibility' => 'public', 'details' => $details
            )));
            $siteId = intval($result['recordId']);
        }catch(Throwable $exception){
            foreach(array_reverse($created) as $id){
                try{ $this->delete->delete($id, true); }catch(Throwable $ignored){ /* reported by the first error */ }
            }
            throw $exception;
        }
        return array('id' => $siteId, 'pageIds' => array($pageId), 'url' => $this->urls->page($siteId), 'issues' => $result['issues']);
    }

    /**
     * Publish a module (or Explorer) as a standalone page (plan 27b §6): a new page, or the
     * module's instance replaced in a page published before (its other content is kept).
     *
     * @param array $input type (module), settings, state (bootstrap envelope of the module), title,
     *        pageId (0: new page).
     * @return array{id:int,url:string,title:string,created:bool,issues:array}
     * @throws WebsiteException|RecordEditException
     */
    public function publishModule(array $input): array
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
        $pageId = intval($input['pageId'] ?? 0);

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
     * Put a page into the menu of a website (at the end of the top menu or of a page's sub menu).
     *
     * @param int $parentId 0: top menu; else a page of this website.
     * @return array Summary of the website.
     * @throws WebsiteException|RecordEditException
     */
    public function addToMenu(int $siteId, int $pageId, int $parentId = 0): array
    {
        $codes = $this->repository->codes();
        $site = $this->editableV4($siteId, $codes['home']);
        $this->editableV4($pageId, $codes['page']);
        $menu = $this->repository->menu($site);
        if(isset($menu['pages'][$pageId])){
            throw new WebsiteException(409, 'Page '.$pageId.' is already in the menu of website '.$siteId);
        }
        if($parentId > 0 && $parentId !== $siteId){
            $parent = $menu['pages'][$parentId] ?? null;
            if($parent === null){ throw new WebsiteException(404, 'Page '.$parentId.' is not a page of website '.$siteId); }
            $this->assertEditable($parentId);
            $this->patch($parentId, array($codes['subMenu'] => array_merge(WebsiteRepository::ids($parent, $codes['subMenu']), array($pageId))));
        }else{
            $this->patch($siteId, array($codes['topMenu'] => array_merge(WebsiteRepository::ids($site, $codes['topMenu']), array($pageId))));
        }
        $this->patch($pageId, array($codes['pageType'] => $codes['menuPage'] > 0 ? array($codes['menuPage']) : array()));
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
            $this->patch($id, array($field => array_values(array_diff($ids, array($pageId)))));
            $found = true;
        }
        if(!$found){ throw new WebsiteException(404, 'Page '.$pageId.' is not in the menu of website '.$siteId); }
        $this->patch($pageId, array($codes['pageType'] => $codes['standalonePage'] > 0 ? array($codes['standalonePage']) : array()));
        return $this->summary($siteId);
    }

    /**
     * Delete a v4 standalone page, or a v4 website. With $withPages the menu pages of the
     * website go too, except those other records point to (another website, a page outside
     * this site).
     *
     * @return array{deleted:array<int,int>,kept:array<int,int>}
     * @throws WebsiteException|RecordEditException
     */
    public function delete(int $id, bool $withPages = false): array
    {
        $codes = $this->repository->codes();
        $record = $this->recordOf($id);
        if(WebsiteRepository::value($record, $codes['version']) !== WebsiteService::VERSION){
            throw new WebsiteException(409, 'Record '.$id.' is not of format version 4; delete it in the legacy website editor');
        }
        if(intval($record['rec_RecTypeID']) !== $codes['home']){
            $this->delete->delete($id, false);
            return array('deleted' => array($id), 'kept' => array());
        }
        $pages = $withPages ? array_keys($this->repository->menu($record)['pages']) : array();
        $site = array_merge(array($id), $pages);
        $delete = array();
        $kept = array();
        foreach($pages as $pageId){
            $outside = array_filter($this->delete->references($pageId)['pointers'], static function(array $pointer) use ($site): bool {
                return !in_array(intval($pointer['rec_ID']), $site, true);
            });
            if(empty($outside) && $this->canEdit($pageId)){ $delete[] = $pageId; }else{ $kept[] = $pageId; }
        }
        $this->delete->delete($id, true);
        $deleted = array($id);
        foreach($delete as $pageId){
            $this->delete->delete($pageId, true);
            $deleted[] = $pageId;
        }
        return array('deleted' => $deleted, 'kept' => $kept);
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

    /** Insert a public record; returns its id. */
    private function insert(int $rectype, array $details): int
    {
        $result = $this->save->save('POST', 0, RecordInput::fromBody(array(
            'rec_ID' => 0, 'rec_RecTypeID' => $rectype, 'rec_NonOwnerVisibility' => 'public', 'details' => $details
        )));
        return intval($result['recordId']);
    }

    /** Replace some fields of a record (the others stay). */
    private function patch(int $id, array $details): void
    {
        $this->save->save('PATCH', $id, RecordInput::fromBody(array('rec_ID' => $id, 'details' => $details)));
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
