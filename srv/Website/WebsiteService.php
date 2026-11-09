<?php
/**
* WebsiteService.php - Finds what a v4 website URL shows (plan 27a §5)
*
* `/{db}/site/{id}[/{page id}]`: {id} is a website (RT_CMS_HOME), showing its
* home content or one of its menu pages, or a standalone page (RT_CMS_PAGE).
* Field 2-49 must be 4. The result (a page context) is what PageRenderer
* writes; the checks of CmsValidator are part of it.
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

/** Website, page, menu, language and configuration of one request. */
final class WebsiteService
{
    public const VERSION = '4';

    private WebsiteRepository $repository;
    private LanguageCodes $languages;
    private string $baseUrl;

    public function __construct(WebsiteRepository $repository, LanguageCodes $languages, string $baseUrl)
    {
        $this->repository = $repository;
        $this->languages = $languages;
        $this->baseUrl = $baseUrl;
    }

    public function repository(): WebsiteRepository
    {
        return $this->repository;
    }

    public function languageCodes(): LanguageCodes
    {
        return $this->languages;
    }

    /**
     * The page context of a request.
     *
     * @param int $id Website or standalone page.
     * @param int $pageId Page of the website (0: home content).
     * @param string $language Requested language (?lang=, 2 or 3 letters).
     * @return array{site:?array,page:array,home:bool,menu:array,pages:array,language:?string,requestedLanguage:?string,
     *         languages:array<int,string>,siteConfig:array,pageConfig:array,siteTexts:array,
     *         pageTexts:array,issues:array}
     * @throws WebsiteException
     */
    public function context(int $id, int $pageId = 0, string $language = ''): array
    {
        $codes = $this->repository->codes();
        if($codes['home'] < 1 || $codes['page'] < 1){
            throw new WebsiteException(404, 'This database has no website record types');
        }
        $record = $this->repository->record($id);
        if($record === null){
            throw $this->repository->exists($id)
                ? new WebsiteException(403, 'This website is not public. Log in to see it.')
                : new WebsiteException(404, 'Website '.$id.' was not found');
        }
        $type = intval($record['rec_RecTypeID'] ?? 0);
        if($type !== $codes['home'] && $type !== $codes['page']){
            throw new WebsiteException(404, 'Record '.$id.' is not a website or a web page');
        }
        if(WebsiteRepository::value($record, $codes['version']) !== self::VERSION){
            throw new WebsiteException(404, 'Record '.$id.' is not a website of format version 4. Open it at /web/'.$id);
        }

        $site = null;
        $page = $record;
        $menu = array('tree' => array(), 'pages' => array());
        if($type === $codes['home']){
            $site = $record;
            $menu = $this->repository->menu($site);
            if($pageId > 0 && $pageId !== $id){
                if(!isset($menu['pages'][$pageId])){
                    throw new WebsiteException(404, 'Page '.$pageId.' is not a page of website '.$id);
                }
                $page = $menu['pages'][$pageId];
            }
        }
        $home = $site !== null && intval($page['rec_ID']) === intval($site['rec_ID']);

        $siteParsed = $site !== null ? CmsValidator::parse(WebsiteRepository::value($site, $codes['configuration'])) : null;
        $pageParsed = $home ? $siteParsed : CmsValidator::parse(WebsiteRepository::value($page, $codes['configuration']));
        $siteTexts = $site !== null ? CmsValidator::translations(WebsiteRepository::values($site, $codes['translations'])) : array();
        $pageTexts = $home ? $siteTexts : CmsValidator::translations(WebsiteRepository::values($page, $codes['translations']));

        $available = $site !== null ? $this->siteLanguages($site, $codes['languages']) : array_map('strtoupper', array_keys($pageTexts));
        $requested = $this->languages->code3($language);
        $current = $requested !== null && in_array($requested, $available, true) ? $requested : ($available[0] ?? null);
        // interface language of the modules: a standalone page takes any language asked in the URL
        // (its texts stay in the first language), a website only one of its languages
        $requested = $requested !== null && ($site === null || in_array($requested, $available, true)) ? $requested : null;

        $parts = array(WebsiteRepository::value($page, $codes['content']));
        if($site !== null){
            $parts[] = WebsiteRepository::value($site, $codes['header']);
            $parts[] = WebsiteRepository::value($site, $codes['footer']);
        }
        $issues = $pageParsed['issues'];
        if($site !== null && !$home){ $issues = array_merge($issues, $siteParsed['issues']); }
        $checkConfig = $pageParsed['config'];
        if($site !== null && !$home){
            // header and footer hosts are configured on the website record
            $checkConfig['instances'] = $pageParsed['config']['instances'] + $siteParsed['config']['instances'];
        }
        $issues = array_merge($issues, CmsValidator::check($checkConfig, $parts, $pageTexts, $this->baseUrl));

        return array(
            'site' => $site,
            'page' => $page,
            'home' => $home,
            'menu' => $menu['tree'],
            'pages' => $menu['pages'],
            'language' => $current,
            'requestedLanguage' => $requested,
            'languages' => $available,
            'siteConfig' => $siteParsed !== null ? $siteParsed['config'] : null,
            'pageConfig' => $pageParsed['config'],
            'siteTexts' => $siteTexts,
            'pageTexts' => $pageTexts,
            'issues' => $issues
        );
    }

    /**
     * Check of a whole website (review 2026-11-08): the format of the home content and of every menu page, and
     * the records a visitor can not see: the website, its pages and the records picked on them (record fields,
     * media, generated reports, manual record cards, media galleries; not queries). A record field of a public record
     * (also `website.` / `page.`) is listed when the field or some of its values are hidden from visitors (`fields`:
     * state `hidden` = all values, `partly` = some; review 2026-11-08). A standalone page: only itself.
     *
     * @return array{issues:array,records:array<int,array{id:int,title:string,visibility:string,role:string,
     *         usedIn:array<int,array{page:int,pageTitle:string,node:string,kind:string}>,
     *         fields?:array<int,array{id:int,name:string,state:string}>}>}
     * @throws WebsiteException
     */
    public function check(int $id, string $language = ''): array
    {
        $codes = $this->repository->codes();
        $context = $this->context($id, 0, $language);
        $site = $context['site'];
        $title = function(array $record) use ($codes): string {
            $name = WebsiteRepository::value($record, $codes['name']);
            return $name !== '' ? $name : (string)($record['rec_Title'] ?? '');
        };
        $first = $site ?? $context['page'];
        $pages = array(intval($first['rec_ID']) => $first) + $context['pages'];

        $issues = array();
        $seen = array();
        $add = function(array $list, array $page) use (&$issues, &$seen, $title): void {
            foreach($list as $issue){
                // header, footer and website configuration are checked with every page: listed once
                $key = ($issue['code'] ?? '').'|'.($issue['message'] ?? '').'|'.($issue['node'] ?? '');
                if(isset($seen[$key])){ continue; }
                $seen[$key] = true;
                $issues[] = $issue + array('page' => intval($page['rec_ID']), 'pageTitle' => $title($page));
            }
        };
        $add($context['issues'], $first);
        foreach($context['pages'] as $pageId => $page){
            $add($this->context($id, intval($pageId), $language)['issues'], $page);
        }

        // the website and its pages, then the records picked on the pages and the record fields
        $pageRoles = array();
        if($site !== null){ $pageRoles[intval($site['rec_ID'])] = 'website'; }
        $uses = array();
        $fieldUses = array();
        $definitions = $this->repository->definitions();
        foreach($pages as $pageId => $page){
            $pageRoles[intval($pageId)] = $pageRoles[intval($pageId)] ?? 'page';
            $isSite = $site !== null && intval($pageId) === intval($site['rec_ID']);
            $config = $isSite ? $context['siteConfig'] : CmsValidator::parse(WebsiteRepository::value($page, $codes['configuration']))['config'];
            $parts = array(WebsiteRepository::value($page, $codes['content']));
            if($isSite){
                $parts[] = WebsiteRepository::value($page, $codes['header']);
                $parts[] = WebsiteRepository::value($page, $codes['footer']);
            }
            $references = CmsRecordReferences::collect(is_array($config) ? $config : array(), $parts,
                $site !== null ? intval($site['rec_ID']) : 0, intval($pageId));
            foreach($references as $reference){
                $use = array('page' => intval($pageId), 'pageTitle' => $title($page), 'node' => $reference['node'], 'kind' => $reference['kind']);
                if($reference['kind'] === 'field'){
                    $fieldId = $definitions->localId('dty', (string)$reference['field']);
                    if($fieldId > 0){ $fieldUses[$reference['id']][$fieldId][] = $use; }
                    // website. / page.: the record itself is checked as a page
                    if(!empty($reference['own'])){ continue; }
                }
                $uses[$reference['id']][] = $use;
            }
        }

        $found = $this->repository->visibility(array_merge(array_keys($pageRoles), array_keys($uses), array_keys($fieldUses)));
        $public = static function(?array $row): bool {
            // pending: guests see it
            return $row !== null && in_array($row['visibility'], array('public', 'pending'), true);
        };
        $records = array();
        foreach($pageRoles as $recordId => $role){
            $row = $found[$recordId] ?? null;
            if($public($row)){ continue; }
            $records[] = array('id' => $recordId, 'title' => $row !== null ? $row['title'] : '',
                'visibility' => $row === null ? 'missing' : $row['visibility'], 'role' => $role, 'usedIn' => array());
        }
        // record fields of public records: the field or its values may still be hidden from visitors
        $fieldChecks = array();
        foreach($fieldUses as $recordId => $fields){
            if($public($found[$recordId] ?? null)){ $fieldChecks[$recordId] = $fields; }
        }
        $hiddenFields = $this->repository->hiddenFieldValues($fieldChecks);
        foreach(array_unique(array_merge(array_keys($uses), array_keys($fieldUses))) as $recordId){
            $row = $found[$recordId] ?? null;
            if(!$public($row)){
                if(!isset($uses[$recordId])){ continue; } // only website. / page. fields of a page listed above
                $usedIn = $uses[$recordId];
                $records[] = array('id' => $recordId, 'title' => $row !== null ? $row['title'] : '',
                    'visibility' => $row === null ? 'missing' : $row['visibility'], 'role' => 'reference', 'usedIn' => $usedIn);
                continue;
            }
            if(empty($hiddenFields[$recordId])){ continue; }
            $fields = array();
            $usedIn = array();
            foreach($hiddenFields[$recordId] as $fieldId => $state){
                $fields[] = array('id' => $fieldId, 'name' => (string)($definitions->field($fieldId)['name'] ?? $fieldId), 'state' => $state);
                $usedIn = array_merge($usedIn, $fieldUses[$recordId][$fieldId] ?? array());
            }
            $records[] = array('id' => $recordId, 'title' => $row['title'], 'visibility' => $row['visibility'], 'role' => 'reference',
                'usedIn' => $usedIn, 'fields' => $fields);
        }
        return array('issues' => $issues, 'records' => $records);
    }

    /** Languages of a website (DT_LANGUAGE terms) as 3-letter codes, the first is the default. */
    private function siteLanguages(array $site, int $field): array
    {
        $result = array();
        foreach(WebsiteRepository::values($site, $field) as $termId){
            $term = $this->repository->definitions()->term(intval($termId));
            $code = $term !== null ? $this->languages->code3((string)($term['code'] ?? '')) : null;
            if($code !== null && !in_array($code, $result, true)){ $result[] = $code; }
        }
        return $result;
    }
}
