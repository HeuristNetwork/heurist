<?php
/**
* CmsRecordReferences.php - Record ids a v4 page refers to (check of a website, review 2026-11-08)
*
* Picked records only, queries are not followed:
*   field     `data-h-field="<id>.<field>"`; `website.` / `page.` give the website / page id with `own` (only the
*             visibility of the field and its values is checked for them)
*   media     media element, `config.media[id].settings.record`
*   report    Generated report widget, `settings.files{language: output id}`
*   cards     Record cards widget with manual records, `settings.query[0].ids`
*   gallery   Media gallery widget, `settings.ids`
*   storymap  Story map widget, `settings.preset` (a Presentation set, plan 27q1)
*   crosstabs Crosstabs widget, `settings.preset` (a Presentation set, plan 27q2)
*   filter    the query source of a Saved filter (plan 27j): a Record field link `filter:<page>:<source>`, a media element,
*             Record cards / Media gallery with the action, an action menu item; visitors must be able to read it
* The card template (a report record) is not listed: it is read without the visitor's rights.
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

/** Picked record ids of page HTML and its configuration. */
final class CmsRecordReferences
{
    /**
     * References of the parts of one page.
     *
     * @param array $config Parsed configuration (CmsValidator::parse()['config']) of the record the parts belong to.
     * @param array<int,string> $parts HTML fragments (content, or header and footer).
     * @param int $websiteId Record of `website.` field references (0: none).
     * @param int $pageId Record of `page.` field references.
     * @return array<int,array{id:int,node:string,kind:string,field?:string,own?:bool}> `field`: id or concept code of a
     *         record field.
     */
    public static function collect(array $config, array $parts, int $websiteId = 0, int $pageId = 0): array
    {
        $instances = is_array($config['instances'] ?? null) ? $config['instances'] : array();
        $media = is_array($config['media'] ?? null) ? $config['media'] : array();
        $result = array();
        foreach($parts as $html){
            foreach(CmsHtml::nodes((string)$html) as $node){
                $name = (string)($node['node'] !== '' ? $node['node'] : ($node['instance'] ?? ''));
                // the name of the element (plan 27n §9), shown by the Check report
                $named = $node['name'] !== null ? array('name' => $node['name']) : array();
                // `record.` (plan 27g): the record of the URL, not a record of the website
                if($node['field'] !== null && preg_match('/^(website|page|\d+)\.(\d+(?:-\d+)?)$/', $node['field'], $match) === 1){
                    $own = !ctype_digit($match[1]);
                    $id = $match[1] === 'website' ? $websiteId : ($match[1] === 'page' ? $pageId : intval($match[1]));
                    if($id > 0){
                        $result[] = array('id' => $id, 'node' => $name, 'kind' => 'field', 'field' => $match[2]) + ($own ? array('own' => true) : array()) + $named;
                    }
                }
                if($node['media'] !== null && is_array($media[$node['media']] ?? null)){
                    $record = intval($media[$node['media']]['settings']['record'] ?? 0);
                    if($record > 0){ $result[] = array('id' => $record, 'node' => $name, 'kind' => 'media') + $named; }
                    $source = self::filterSource($media[$node['media']]['settings'] ?? null);
                    if($source > 0){ $result[] = array('id' => $source, 'node' => $name, 'kind' => 'filter') + $named; }
                }
                if(($node['link'] ?? null) !== null && preg_match('/^filter:\d+:(\d+)$/', (string)$node['link'], $filter) === 1){
                    $result[] = array('id' => intval($filter[1]), 'node' => $name, 'kind' => 'filter') + $named;
                }
                if($node['instance'] !== null && is_array($instances[$node['instance']] ?? null)){
                    foreach(self::instanceIds($instances[$node['instance']]) as $kind => $ids){
                        foreach($ids as $id){ $result[] = array('id' => $id, 'node' => $name, 'kind' => $kind) + $named; }
                    }
                }
            }
        }
        return $result;
    }

    /** The query source of settings with the action Saved filter (plan 27j) or of a search box (plan 27k §6), or 0. */
    private static function filterSource($settings): int
    {
        return is_array($settings) && in_array($settings['action'] ?? '', array('savedFilter', 'search'), true)
            ? SavedFilterLink::id($settings['source'] ?? null) : 0;
    }

    /** @return array<string,array<int,int>> kind => record ids of one widget. */
    private static function instanceIds(array $instance): array
    {
        $settings = is_array($instance['settings'] ?? null) ? $instance['settings'] : array();
        $type = (string)($instance['type'] ?? '');
        // Saved filter (plan 27j): of Record cards, a Media gallery, the items of an action menu
        $sources = array();
        if($type === 'actionmenu'){
            foreach(is_array($settings['items'] ?? null) ? $settings['items'] : array() as $item){ $sources[] = self::filterSource($item); }
        }else{
            $sources[] = self::filterSource($settings);
        }
        $filters = self::ids($sources);
        $ids = self::widgetIds($type, $settings);
        // the own records of a module (plan 27l §11.3, §12): its query source or record, when it uses them
        $from = is_array($instance['records']['from'] ?? null) ? $instance['records']['from'] : array();
        if(in_array('own', $from, true) || in_array($instance['sync'] ?? null, array('selection', 'none'), true) || $type === 'recordview'){
            $own = CmsValidator::instanceDataSource($instance['dataSource'] ?? null, $type);
            $id = intval($own['source'] ?? $own['record'] ?? 0);
            if($id > 0){ $ids['module'] = array($id); }
        }
        return empty($filters) ? $ids : $ids + array('filter' => $filters);
    }

    /** @return array<string,array<int,int>> kind => record ids picked in a widget. */
    private static function widgetIds(string $type, array $settings): array
    {
        switch($type){
            case 'report':
                return array('report' => self::ids(is_array($settings['files'] ?? null) ? array_values($settings['files']) : array()));
            case 'cards':
                // the query source of the cards (plan 27l §11.4)
                if((string)($settings['mode'] ?? '') === 'source'){ return array('cards' => self::ids(array($settings['querySource'] ?? 0))); }
                if((string)($settings['mode'] ?? 'manual') !== 'manual'){ return array(); }
                $text = is_array($settings['query'] ?? null) ? ($settings['query'][0]['ids'] ?? '') : '';
                return array('cards' => self::ids(is_string($text) || is_int($text) ? explode(',', (string)$text) : array()));
            case 'gallery':
                return array('gallery' => self::ids(is_array($settings['ids'] ?? null) ? $settings['ids'] : array()));
            case 'storymap':
                return array('storymap' => self::ids(array($settings['preset'] ?? 0)));
            case 'crosstabs':
                // a Presentation set of kind Crosstab (plan 27q2)
                return array('crosstabs' => self::ids(array($settings['preset'] ?? 0)));
        }
        return array();
    }

    /** @return array<int,int> Positive unique ids. */
    private static function ids(array $values): array
    {
        $ids = array();
        foreach($values as $value){
            $id = is_scalar($value) ? intval($value) : 0;
            if($id > 0 && !in_array($id, $ids, true)){ $ids[] = $id; }
        }
        return $ids;
    }
}
