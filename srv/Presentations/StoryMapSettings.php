<?php
/**
* StoryMapSettings.php - Check of the configuration of a story map (Presentation set of kind storymap, plan 27q1 §4, §15)
*
* Only the form of the settings is checked: a draft can be saved without a rule or a template, but a value that is
* given must have the right type. The client normalises the same settings in shared/src/widgets/storymap/storyMapModel.js
* (the client also reads the older form of 2026-11-18: patterns, overview, messages at the top).
*
* @project     Heurist academic knowledge management system
* @package     Presentations
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Presentations;

/** Form check of the settings of a story map. */
final class StoryMapSettings
{
    public const LAYOUTS = array('list', 'horizontal', 'carousel', 'tabs');
    public const DISTINCT = array('highlight', 'veil', 'none');
    public const START_MODES = array('inline', 'tab', 'header', 'none');
    public const END_MODES = array('inline', 'tab', 'footer', 'none');
    public const ORDERS = array('date', 'rule', 'manual');
    public const PLACE_ROLES = array('begin', 'transition', 'end');
    /** Most story elements read for one topic. */
    public const MAX_LIMIT = 500;

    /**
     * Problems of the settings, empty when they are fine.
     *
     * @param mixed $settings Decoded JSON.
     * @return array<int,string>
     */
    public static function problems($settings): array
    {
        if(!self::isObject($settings)){
            return array('The configuration must be a JSON object');
        }
        $problems = array();
        self::story($settings['story'] ?? null, $problems);
        foreach(array('element', 'start', 'end') as $part){
            self::template($part, $settings[$part] ?? null, $problems);
        }
        self::mode('start.mode', $settings['start']['mode'] ?? null, self::START_MODES, $problems);
        self::mode('end.mode', $settings['end']['mode'] ?? null, self::END_MODES, $problems);
        foreach(array('start', 'end') as $part){
            self::texts($part.'.text', $settings[$part]['text'] ?? null, $problems);
        }
        $presentation = $settings['presentation'] ?? null;
        if($presentation !== null && !self::isObject($presentation)){
            $problems[] = 'presentation must be an object';
        }else{
            self::mode('presentation.layout', $presentation['layout'] ?? null, self::LAYOUTS, $problems);
            self::mode('presentation.distinct', $presentation['distinct'] ?? null, self::DISTINCT, $problems);
            $messages = $presentation['messages'] ?? null;
            if($messages !== null && !self::isObject($messages)){
                $problems[] = 'presentation.messages must be an object';
            }else{
                self::texts('presentation.messages.intro', $messages['intro'] ?? null, $problems);
                self::texts('presentation.messages.empty', $messages['empty'] ?? null, $problems);
            }
        }
        self::map($settings['map'] ?? null, $problems);
        $actions = $settings['actions'] ?? null;
        if($actions !== null && !is_array($actions)){
            $problems[] = 'actions must be a list';
        }
        return $problems;
    }

    /** story: {topicType, rule: {query, levels}, order: {by, dir, manual}, limit}. */
    private static function story($story, array &$problems): void
    {
        if($story === null){ return; }
        if(!self::isObject($story)){
            $problems[] = 'story must be an object';
            return;
        }
        $rule = $story['rule'] ?? null;
        if($rule !== null){
            if(!self::isObject($rule) || !is_array($rule['query'] ?? null)){
                $problems[] = 'story.rule must be an expansion rule with a query';
            }elseif(isset($rule['levels']) && !is_array($rule['levels'])){
                $problems[] = 'story.rule.levels must be a list';
            }
        }
        $topicType = $story['topicType'] ?? null;
        if($topicType !== null && !(is_int($topicType) && $topicType > 0)){
            $problems[] = 'story.topicType must be a record type id';
        }
        $limit = $story['limit'] ?? null;
        if($limit !== null && !(is_int($limit) && $limit > 0 && $limit <= self::MAX_LIMIT)){
            $problems[] = 'story.limit must be a number from 1 to '.self::MAX_LIMIT;
        }
        $order = $story['order'] ?? null;
        if($order === null){ return; }
        if(!self::isObject($order)){
            $problems[] = 'story.order must be an object';
            return;
        }
        $by = $order['by'] ?? 'date';
        if(!in_array($by, self::ORDERS, true) && !self::isField($by)){
            $problems[] = 'story.order.by must be date, rule, manual or a field';
        }
        self::mode('story.order.dir', $order['dir'] ?? null, array('asc', 'desc'), $problems);
        $manual = $order['manual'] ?? null;
        if($manual === null){ return; }
        $fine = self::isObject($manual);
        foreach($fine ? $manual : array() as $topic => $ids){
            if(!self::isId((string)$topic) || !is_array($ids) || !self::isList($ids)
                || count(array_filter($ids, array(self::class, 'isId'))) !== count($ids)){
                $fine = false;
            }
        }
        if(!$fine){
            $problems[] = 'story.order.manual must give a list of record ids per topic';
        }
    }

    /** element / start / end: {template: report id or null}. */
    private static function template(string $part, $value, array &$problems): void
    {
        if($value === null){ return; }
        if(!self::isObject($value)){
            $problems[] = $part.' must be an object';
            return;
        }
        $template = $value['template'] ?? null;
        if($template !== null && !self::isId($template)){
            $problems[] = $part.'.template must be the id of a report template';
        }
    }

    /** map: {background, places, symbology, duration, keep}. */
    private static function map($map, array &$problems): void
    {
        if($map === null){ return; }
        if(!self::isObject($map)){
            $problems[] = 'map must be an object';
            return;
        }
        $background = $map['background'] ?? null;
        if($background !== null && !(self::isObject($background) && self::isId($background['mapDocument'] ?? null)
            && (!isset($background['timeFilter']) || is_bool($background['timeFilter'])))){
            $problems[] = 'map.background must be a map document with an optional time filter';
        }
        $places = $map['places'] ?? null;
        if($places !== null){
            if(!self::isObject($places)){
                $problems[] = 'map.places must be an object';
            }else{
                foreach(self::PLACE_ROLES as $role){
                    if(isset($places[$role]) && !self::pathList($places[$role])){
                        $problems[] = 'map.places.'.$role.' must be a list of geographic field paths';
                    }
                }
                if(isset($places['connect']) && !is_bool($places['connect'])){
                    $problems[] = 'map.places.connect must be true or false';
                }
            }
        }
        foreach(array('elementSymbology', 'lineSymbology') as $key){
            if(isset($map[$key]) && !self::isObject($map[$key])){
                $problems[] = 'map.'.$key.' must be an object';
            }
        }
        self::number('map.duration', $map['duration'] ?? null, $problems);
        if(isset($map['keep']) && !is_bool($map['keep'])){
            $problems[] = 'map.keep must be true or false';
        }
    }

    /** A value of an enum, when given. */
    private static function mode(string $name, $value, array $allowed, array &$problems): void
    {
        if($value !== null && !in_array($value, $allowed, true)){
            $problems[] = $name.' must be one of '.implode(', ', $allowed);
        }
    }

    /** A number of 0 or more, when given. */
    private static function number(string $name, $value, array &$problems): void
    {
        if($value !== null && (!is_int($value) && !is_float($value) || $value < 0)){
            $problems[] = $name.' must be a number of 0 or more';
        }
    }

    /** Texts by language: {"def": "...", "fre": "..."}, when given. */
    private static function texts(string $name, $value, array &$problems): void
    {
        if($value === null){ return; }
        if(!self::isObject($value) || count(array_filter($value, 'is_string')) !== count($value)){
            $problems[] = $name.' must be texts by language';
        }
    }

    /** A list of geographic field paths as in Query sources (Records\Map\MapFieldSelector): rty:dty, rty:lt12:rty:dty. */
    private static function pathList($value): bool
    {
        if(!is_array($value) || !self::isList($value)){ return false; }
        foreach($value as $item){
            if(!is_string($item) || preg_match('/^\d+(?::(?:lt|lf|rt|rf|r)\d*:\d+)*:\d+$/i', trim($item)) !== 1){
                return false;
            }
        }
        return true;
    }

    /** A local id (positive integer) or a concept code (2-134). */
    private static function isField($value): bool
    {
        return self::isId($value) || (is_string($value) && preg_match('/^\d+-\d+$/', $value) === 1);
    }

    /** A positive integer (also as digits). */
    private static function isId($value): bool
    {
        return (is_int($value) && $value > 0) || (is_string($value) && preg_match('/^[1-9]\d*$/', $value) === 1);
    }

    /** A JSON list (keys 0..n-1; PHP 8.0 has no array_is_list). */
    private static function isList(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1) || $value === array();
    }

    /** A JSON object (an array with keys that is not an empty list). */
    private static function isObject($value): bool
    {
        return is_array($value) && ($value === array() || !self::isList($value));
    }
}
