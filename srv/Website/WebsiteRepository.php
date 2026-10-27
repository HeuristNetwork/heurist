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
        'logo' => 'DT_FILE_RESOURCE'
    );

    /** Depth of the menu tree read for a website (top pages = 1). */
    private const MENU_DEPTH = 3;

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
