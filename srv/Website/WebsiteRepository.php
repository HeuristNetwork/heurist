<?php
/**
* WebsiteRepository.php - Reads the records of a v4 website (plan 27a)
*
* A website is an RT_CMS_HOME record (99-51) pointing to its top pages
* (99-742); a page (RT_CMS_PAGE, 99-52) points to its sub pages (99-761). A
* page with the page type "standalone" is opened alone. Records are read
* with the rights of the visitor through RecordRepository and
* RecordDataService (plan 15); ids come from concept codes (DefinitionLookup).
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

use Heurist\Database\DatabaseInterface;
use Heurist\Definitions\DefinitionLookup;
use Heurist\Records\Data\RecordDataService;
use Heurist\Records\Files\FileRepository;
use Heurist\Records\Store\RecordAccess;
use Heurist\Records\Store\RecordRepository;
use Heurist\Runtime\RuntimeContext;

/** Website, page and menu records visible to the current user. */
final class WebsiteRepository
{
    /** The definitions of the format: key => SystemConstants name (2026-10-27: was concept codes). */
    public const CONSTANTS = array(
        'home' => 'RT_CMS_HOME',
        'page' => 'RT_CMS_PAGE',
        'name' => 'DT_NAME',
        'shortSummary' => 'DT_SHORT_SUMMARY',
        'content' => 'DT_EXTENDED_DESCRIPTION',
        'version' => 'DT_VERSION',
        'configuration' => 'DT_CONFIGURATION',
        'translations' => 'DT_CMS_PAGE_TRANSLATIONS',
        'header' => 'DT_CMS_HEADER',
        'footer' => 'DT_CMS_FOOTER',
        'css' => 'DT_CMS_CSS',
        'script' => 'DT_CMS_SCRIPT',
        'extFiles' => 'DT_CMS_EXTFILES',
        'languages' => 'DT_LANGUAGE',
        'topMenu' => 'DT_CMS_TOP_MENU',
        'subMenu' => 'DT_CMS_MENU',
        'keywords' => 'DT_CMS_KEYWORDS',
        'pageType' => 'DT_CMS_PAGETYPE',
        'standalonePage' => 'TRM_PAGETYPE_WEBPAGE',
        'menuPage' => 'TRM_PAGETYPE_MENUITEM',
        'logo' => 'DT_FILE_RESOURCE',
        // the header default set (plan 27f §13): subtitle and banner image of the website
        'subtitle' => 'DT_ALTERNATE_NAME',
        'banner' => 'DT_CMS_BANNER',
        // menu entry of a page (plan 27f §4): icon (thumbnail) and what is shown: name, icon or both
        'thumbnail' => 'DT_THUMBNAIL',
        'menuFormat' => 'DT_CMS_MENU_FORMAT',
        'menuIconOnly' => 'TRM_ICON_ONLY',
        'menuNameAndIcon' => 'TRM_NAME_AND_ICON',
        // a page hidden in the main menu (review 2026-11-11): its <li> is written with the class is-menu-hidden
        'menuHidden' => 'TRM_HIDDEN_ITEM'
    );

    /** Depth of the menu tree read for a website (top pages = 1); was 3, a fourth level was lost (2026-11-12). A page
     *  in the tree twice is read once (no endless loop). */
    private const MENU_DEPTH = 10;

    private DatabaseInterface $database;
    private RuntimeContext $runtime;
    private DefinitionLookup $definitions;
    /** @var array<string,int>|null */
    private ?array $codes = null;

    public function __construct(DatabaseInterface $database, RuntimeContext $runtime)
    {
        $this->database = $database;
        $this->runtime = $runtime;
        $this->definitions = DefinitionLookup::shared($database);
    }

    /** @return array<string,int> Local ids of CONSTANTS (0 when missing in this database). */
    public function codes(): array
    {
        if($this->codes === null){
            $this->codes = array();
            foreach(self::CONSTANTS as $key => $name){
                $this->codes[$key] = $this->definitions->constant($name);
            }
        }
        return $this->codes;
    }

    public function definitions(): DefinitionLookup
    {
        return $this->definitions;
    }

    /** One record visible to the current user with all its details, or null. */
    public function record(int $id): ?array
    {
        return $this->records(array($id))[$id] ?? null;
    }

    /**
     * Records visible to the current user, by id (order of $ids).
     *
     * @return array<int,array>
     */
    public function records(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function(int $id){ return $id > 0; })));
        if(empty($ids)){ return array(); }
        $visible = RecordRepository::shared($this->database)->visibleIds($ids, RecordAccess::fromRuntime($this->runtime));
        if(empty($visible)){ return array(); }
        $result = array();
        $loaded = (new RecordDataService($this->database, $this->runtime))
            ->loadRecords($visible, array('rec_NonOwnerVisibility'), array(), array('allDetails' => true));
        foreach($loaded as $record){ $result[intval($record['rec_ID'])] = $record; }
        $ordered = array();
        foreach($ids as $id){
            if(isset($result[$id])){ $ordered[$id] = $result[$id]; }
        }
        return $ordered;
    }

    /**
     * Visibility of records for the check of a website: rec_NonOwnerVisibility of each record that exists, the
     * title only when the user may see the record (records that do not exist are left out).
     *
     * @return array<int,array{visibility:string,title:string}>
     */
    public function visibility(array $ids): array
    {
        $repository = RecordRepository::shared($this->database);
        $rows = $repository->headers($ids, array('rec_ID', 'rec_Title', 'rec_NonOwnerVisibility'));
        $visible = array_flip($repository->visibleIds(array_keys($rows), RecordAccess::fromRuntime($this->runtime)));
        $result = array();
        foreach($rows as $id => $row){
            $result[$id] = array(
                'visibility' => (string)($row['rec_NonOwnerVisibility'] ?? ''),
                'title' => isset($visible[$id]) ? (string)($row['rec_Title'] ?? '') : ''
            );
        }
        return $result;
    }

    /**
     * Record fields whose values a visitor (guest) can not all see: a hidden or logged-in-only field of the record
     * structure, or values hidden from the public (dtl_HideFromPublic). Fields without values are left out.
     *
     * @param array<int,array<int,mixed>> $fields Record id => field id => anything.
     * @return array<int,array<int,string>> Record id => field id => `hidden` (no value visible) or `partly`.
     */
    public function hiddenFieldValues(array $fields): array
    {
        if(empty($fields)){ return array(); }
        $fieldIds = array();
        foreach($fields as $list){ $fieldIds = array_merge($fieldIds, array_keys($list)); }
        $repository = RecordRepository::shared($this->database);
        $guest = RecordAccess::guest();
        $counts = array();
        foreach($repository->details(array_keys($fields), array_values(array_unique($fieldIds)), array('owner' => true)) as $row){
            if(!isset($fields[$row['record']][$row['field']])){ continue; }
            $count = $counts[$row['record']][$row['field']] ?? array(0, 0);
            $count[0]++;
            if(!$repository->valueVisible($row, $guest)){ $count[1]++; }
            $counts[$row['record']][$row['field']] = $count;
        }
        $result = array();
        foreach($counts as $recordId => $list){
            foreach($list as $fieldId => $count){
                if($count[1] > 0){ $result[$recordId][$fieldId] = $count[1] === $count[0] ? 'hidden' : 'partly'; }
            }
        }
        return $result;
    }

    /** Whether a record exists, visible or not (a 404 and a "log in" page differ). */
    public function exists(int $id): bool
    {
        return !empty(RecordRepository::shared($this->database)->rectypeOf(array($id)));
    }

    /**
     * The menu tree of a website: its top pages and their sub pages (visible ones).
     *
     * @return array{tree:array<int,array{id:int,children:array}>,pages:array<int,array>}
     */
    public function menu(array $site): array
    {
        $codes = $this->codes();
        $pages = array();
        $level = self::ids($site, $codes['topMenu']);
        $children = array();
        for($depth = 1; $depth <= self::MENU_DEPTH && !empty($level); $depth++){
            $next = array();
            foreach($this->records(array_diff($level, array_keys($pages))) as $id => $page){
                $pages[$id] = $page;
                $children[$id] = $depth < self::MENU_DEPTH ? self::ids($page, $codes['subMenu']) : array();
                $next = array_merge($next, $children[$id]);
            }
            $level = array_values(array_unique($next));
        }
        $build = static function(array $ids, array $seen) use (&$build, $pages, $children): array {
            $tree = array();
            foreach($ids as $id){
                if(!isset($pages[$id]) || isset($seen[$id])){ continue; }
                $tree[] = array('id' => $id, 'children' => $build($children[$id] ?? array(), $seen + array($id => true)));
            }
            return $tree;
        };
        return array('tree' => $build(self::ids($site, $codes['topMenu']), array()), 'pages' => $pages);
    }

    /**
     * Template file of a report: a report record id or a `.tpl` file name (a page template). The report record is
     * not checked for access: the author of the website chose it; the records of the cards follow the visitor's rights.
     *
     * @param mixed $template Record id or file name.
     * @return string File name, '' when there is none.
     */
    public function reportTemplate($template): string
    {
        if(is_string($template) && !ctype_digit($template)){
            return substr($template, -4) === '.tpl' ? basename($template) : '';
        }
        $id = intval($template);
        $field = $this->definitions()->constant('DT_FILE_NAME');
        if($id < 1 || $field < 1){ return ''; }
        $values = RecordRepository::shared($this->database)->values(array($id), array($field));
        return basename((string)($values[$id][$field][0] ?? ''));
    }

    /** The key of a registered file ('' when it is not found). */
    public function fileKey(int $fileId): string
    {
        return (string)((new FileRepository($this->database))->files(array($fileId))[$fileId]['ulf_ObfuscatedFileID'] ?? '');
    }

    /**
     * Title and URL of records the visitor may see (record links, plan 27g §6).
     *
     * @return array<int,array{title:string,url:string}> Record id => plain title and rec_URL ('' without one).
     */
    public function recordHeads(array $ids): array
    {
        $result = array();
        $ids = array_values(array_filter(array_map('intval', $ids), static function(int $id){ return $id > 0; }));
        if(empty($ids)){ return $result; }
        foreach(RecordRepository::shared($this->database)->headers($ids, array('rec_Title', 'rec_URL'), RecordAccess::fromRuntime($this->runtime)) as $id => $row){
            $result[intval($id)] = array('title' => trim(html_entity_decode(strip_tags((string)($row['rec_Title'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                'url' => trim((string)($row['rec_URL'] ?? '')));
        }
        return $result;
    }

    /** Values of a field of a record (hidden marks removed). */
    public static function values(array $record, int $fieldId): array
    {
        if($fieldId < 1){ return array(); }
        $values = array();
        foreach($record['details'][(string)$fieldId] ?? array() as $value){
            $values[] = is_array($value) ? ($value['value'] ?? ($value['rec_ID'] ?? ($value['trm_ID'] ?? ''))) : $value;
        }
        return $values;
    }

    /** First value of a field as text ('' when empty). */
    public static function value(array $record, int $fieldId): string
    {
        $values = self::values($record, $fieldId);
        return isset($values[0]) && is_scalar($values[0]) ? (string)$values[0] : '';
    }

    /** Record ids of a pointer field. */
    public static function ids(array $record, int $fieldId): array
    {
        return array_values(array_filter(array_map('intval', self::values($record, $fieldId))));
    }
}
