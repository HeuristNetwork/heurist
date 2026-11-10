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
                if($node['field'] !== null && preg_match('/^(website|page|\d+)\.(\d+(?:-\d+)?)$/', $node['field'], $match) === 1){
                    $own = !ctype_digit($match[1]);
                    $id = $match[1] === 'website' ? $websiteId : ($match[1] === 'page' ? $pageId : intval($match[1]));
                    if($id > 0){
                        $result[] = array('id' => $id, 'node' => $name, 'kind' => 'field', 'field' => $match[2]) + ($own ? array('own' => true) : array());
                    }
                }
                if($node['media'] !== null && is_array($media[$node['media']] ?? null)){
                    $record = intval($media[$node['media']]['settings']['record'] ?? 0);
                    if($record > 0){ $result[] = array('id' => $record, 'node' => $name, 'kind' => 'media'); }
                }
                if($node['instance'] !== null && is_array($instances[$node['instance']] ?? null)){
                    foreach(self::instanceIds($instances[$node['instance']]) as $kind => $ids){
                        foreach($ids as $id){ $result[] = array('id' => $id, 'node' => $name, 'kind' => $kind); }
                    }
                }
            }
        }
        return $result;
    }

    /** @return array<string,array<int,int>> kind => record ids of one widget. */
    private static function instanceIds(array $instance): array
    {
        $settings = is_array($instance['settings'] ?? null) ? $instance['settings'] : array();
        switch((string)($instance['type'] ?? '')){
            case 'report':
                return array('report' => self::ids(is_array($settings['files'] ?? null) ? array_values($settings['files']) : array()));
            case 'cards':
                if((string)($settings['mode'] ?? 'manual') !== 'manual'){ return array(); }
                $text = is_array($settings['query'] ?? null) ? ($settings['query'][0]['ids'] ?? '') : '';
                return array('cards' => self::ids(is_string($text) || is_int($text) ? explode(',', (string)$text) : array()));
            case 'gallery':
                return array('gallery' => self::ids(is_array($settings['ids'] ?? null) ? $settings['ids'] : array()));
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
