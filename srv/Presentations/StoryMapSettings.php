<?php
/**
* StoryMapSettings.php - Check of the configuration of a story map (Presentation set of kind storymap, plan 27q1 §4)
*
* Only the form of the settings is checked: a draft can be saved without a rule or a template, but a value that is
* given must have the right type. The client normalises the same settings in shared/src/widgets/storymap/storyMapModel.js.
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
    public const LAYOUTS = array('list', 'horizontal', 'tabs', 'carousel', 'slideshow');
    public const DISTINCT = array('highlight', 'veil', 'none');
    public const START_MODES = array('inline', 'tab', 'header', 'none');
    public const END_MODES = array('inline', 'tab', 'footer', 'none');
    /** Place patterns of 27q1; star, pairs and change come with their own plan (§6.1). */
    public const PATTERNS = array('points', 'path');
    public const PATH_ROLES = array('begin', 'transition', 'end');

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
            self::number('presentation.interval', $presentation['interval'] ?? null, $problems);
        }
        $messages = $settings['messages'] ?? null;
        if($messages !== null && !self::isObject($messages)){
            $problems[] = 'messages must be an object';
        }else{
            self::texts('messages.intro', $messages['intro'] ?? null, $problems);
            self::texts('messages.empty', $messages['empty'] ?? null, $problems);
        }
        self::map($settings['map'] ?? null, $problems);
        $actions = $settings['actions'] ?? null;
        if($actions !== null && !is_array($actions)){
            $problems[] = 'actions must be a list';
        }
        return $problems;
    }

    /** story: {rule: {query, levels}, order: {by, dir}}. */
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
        $order = $story['order'] ?? null;
        if($order === null){ return; }
        if(!self::isObject($order)){
            $problems[] = 'story.order must be an object';
            return;
        }
        $by = $order['by'] ?? 'date';
        if(!in_array($by, array('date', 'rule'), true) && !self::isField($by)){
            $problems[] = 'story.order.by must be date, rule or a field';
        }
        self::mode('story.order.dir', $order['dir'] ?? null, array('asc', 'desc'), $problems);
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

    /** map: {overview, patterns, symbology, duration, keep, layers}. */
    private static function map($map, array &$problems): void
    {
        if($map === null){ return; }
        if(!self::isObject($map)){
            $problems[] = 'map must be an object';
            return;
        }
        $overview = $map['overview'] ?? null;
        if($overview !== null && !(self::isObject($overview)
            && ((isset($overview['querySource']) && self::isId($overview['querySource'])) || is_array($overview['query'] ?? null)))){
            $problems[] = 'map.overview must be a query source or a query';
        }
        $patterns = $map['patterns'] ?? null;
        if($patterns !== null){
            if(!is_array($patterns) || !self::isList($patterns)){
                $problems[] = 'map.patterns must be a list';
            }else{
                foreach($patterns as $index => $pattern){
                    self::pattern('map.patterns['.$index.']', $pattern, $problems);
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
        $layers = $map['layers'] ?? null;
        if($layers !== null && (!is_array($layers) || count(array_filter($layers, array(self::class, 'isId'))) !== count($layers))){
            $problems[] = 'map.layers must be a list of map layer ids';
        }
    }

    /** One place pattern: {type, rectypes?, filter? (points), begin/transition/end (path)}. */
    private static function pattern(string $name, $pattern, array &$problems): void
    {
        if(!self::isObject($pattern)){
            $problems[] = $name.' must be an object';
            return;
        }
        $type = $pattern['type'] ?? null;
        if(!in_array($type, self::PATTERNS, true)){
            $problems[] = $name.'.type must be one of '.implode(', ', self::PATTERNS);
            return;
        }
        if(isset($pattern['rectypes']) && !self::fieldList($pattern['rectypes'])){
            $problems[] = $name.'.rectypes must be a list of record types';
        }
        if($type === 'points'){
            if(isset($pattern['filter']) && !is_array($pattern['filter'])){
                $problems[] = $name.'.filter must be a query';
            }
            return;
        }
        $given = 0;
        foreach(self::PATH_ROLES as $role){
            if(!isset($pattern[$role])){ continue; }
            if(!self::fieldList($pattern[$role])){
                $problems[] = $name.'.'.$role.' must be a list of fields';
            }
            $given += count((array)$pattern[$role]);
        }
        if($given === 0){
            $problems[] = $name.' (path) needs begin, transition or end fields';
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

    /** A list of local ids or concept codes. */
    private static function fieldList($value): bool
    {
        if(!is_array($value) || !self::isList($value)){ return false; }
        foreach($value as $item){
            if(!self::isField($item)){ return false; }
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
