<?php
/**
* CrosstabSettings.php - Check of the configuration of crosstabs (Presentation set of kind crosstabs, plan 27q2 §5)
*
* Only the form of the settings is checked: a draft can be saved without rows, but a value that is given must have the
* right type. A dimension (rows, columns, pages) has the form of the `crosstab` parameter of
* /records/aggregate?op=crosstab (Records\Query\CrossTabulator): a field path code and its buckets, plus a caption.
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

use Heurist\Records\Query\CrossTabulator;
use Heurist\Records\Query\RangeValue;

/** Form check of the settings of crosstabs. */
final class CrosstabSettings
{
    public const GROUPBY = array('month', 'year', 'decade', 'century');
    public const MAX_RANGES = 20;
    public const DISPLAY_FLAGS = array('values', 'totals', 'percentRow', 'percentColumn', 'blankZero', 'showBlanks');
    public const SHOW = array('table', 'chart', 'both');
    /** Chart.js types (a heatmap needs a plugin: later, plan 27q2 §7). */
    public const CHARTS = array('bar', 'stackedBar', 'horizontalBar', 'line', 'pie', 'doughnut');
    public const LEVELS = array('view', 'display', 'pivot');
    public const EXPORTS = array('csv', 'xlsx', 'pdf', 'png', 'print');
    public const MAX_DECIMALS = 6;

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
        $fields = $settings['fields'] ?? null;
        if($fields !== null && !(self::isObject($fields) && (!isset($fields['rectype']) || self::isId($fields['rectype'])))){
            $problems[] = 'fields must be an object with the record type of the field list';
        }
        foreach(CrossTabulator::DIMENSIONS as $name){
            self::dimension($name, $settings[$name] ?? null, $problems);
        }
        $measure = $settings['measure'] ?? null;
        if($measure !== null){
            if(!self::isObject($measure)){
                $problems[] = 'measure must be an object';
            }else{
                self::mode('measure.op', $measure['op'] ?? null, CrossTabulator::MEASURES, $problems);
                if(($measure['op'] ?? 'count') !== 'count' && !self::isId($measure['field'] ?? null)){
                    $problems[] = 'measure.field must be a numeric field';
                }
            }
        }
        $display = $settings['display'] ?? null;
        if($display !== null){
            if(!self::isObject($display)){
                $problems[] = 'display must be an object';
            }else{
                foreach(self::DISPLAY_FLAGS as $flag){
                    if(isset($display[$flag]) && !is_bool($display[$flag])){ $problems[] = 'display.'.$flag.' must be true or false'; }
                }
                $decimals = $display['decimals'] ?? null;
                if($decimals !== null && !(is_int($decimals) && $decimals >= 0 && $decimals <= self::MAX_DECIMALS)){
                    $problems[] = 'display.decimals must be a number from 0 to '.self::MAX_DECIMALS;
                }
                self::texts('display.title', $display['title'] ?? null, $problems);
            }
        }
        $chart = $settings['chart'] ?? null;
        if($chart !== null){
            if(!self::isObject($chart)){
                $problems[] = 'chart must be an object';
            }else{
                self::mode('chart.show', $chart['show'] ?? null, self::SHOW, $problems);
                self::mode('chart.type', $chart['type'] ?? null, self::CHARTS, $problems);
            }
        }
        self::visitor($settings['visitor'] ?? null, $problems);
        // the Filter Form of the parameters of the query (plan 27q2 §17)
        $filterForm = $settings['filterForm'] ?? null;
        if($filterForm !== null && !self::isObject($filterForm)){
            $problems[] = 'filterForm must be an object';
        }
        return $problems;
    }

    /** rows / columns / pages: {code, label?, buckets?} or null. */
    private static function dimension(string $name, $value, array &$problems): void
    {
        if($value === null){ return; }
        if(!self::isObject($value)){
            $problems[] = $name.' must be an object';
            return;
        }
        $code = $value['code'] ?? null;
        if(!is_string($code) || CrossTabulator::parseCode($code) === null){
            $problems[] = $name.'.code must be a field path code (rty:dty, rty:lt12:rty:dty)';
        }elseif(count(CrossTabulator::parseCode($code)['via']) > CrossTabulator::MAX_HOPS){
            $problems[] = $name.'.code: at most '.CrossTabulator::MAX_HOPS.' links to a field';
        }
        self::texts($name.'.label', $value['label'] ?? null, $problems);
        $buckets = $value['buckets'] ?? null;
        if($buckets === null || $buckets === 'values'){ return; }
        if(!self::isObject($buckets)){
            $problems[] = $name.'.buckets must be "values" or an object';
            return;
        }
        if(isset($buckets['groupby'])){
            self::mode($name.'.buckets.groupby', $buckets['groupby'], self::GROUPBY, $problems);
            return;
        }
        $ranges = $buckets['ranges'] ?? null;
        if(is_int($ranges)){
            if($ranges < 1 || $ranges > self::MAX_RANGES){
                $problems[] = $name.'.buckets.ranges must be a number from 1 to '.self::MAX_RANGES.' or a list';
            }
            return;
        }
        if(!is_array($ranges) || !self::isList($ranges) || count($ranges) > CrossTabulator::MAX_RANGE_BUCKETS){
            $problems[] = $name.'.buckets.ranges must be a number or a list of ranges';
            return;
        }
        foreach($ranges as $index=>$range){
            $label = is_array($range) ? ($range['label'] ?? null) : null;
            if(!(is_string($range) || (self::isObject($range) && (is_string($range['value'] ?? null) || isset($range['min'], $range['max']))))
                || RangeValue::parse($range)['kind'] === 'empty' || ($label !== null && !is_string($label))){
                $problems[] = $name.'.buckets.ranges['.$index.'] must be a range ("40<>50", "1,2") or {value, label}';
            }
        }
    }

    /** visitor: {level, fields, measures, export, drill}. */
    private static function visitor($visitor, array &$problems): void
    {
        if($visitor === null){ return; }
        if(!self::isObject($visitor)){
            $problems[] = 'visitor must be an object';
            return;
        }
        self::mode('visitor.level', $visitor['level'] ?? null, self::LEVELS, $problems);
        $fields = $visitor['fields'] ?? null;
        if($fields !== null && (!is_array($fields) || !self::isList($fields)
            || count(array_filter($fields, static function($code){
                return is_string($code) && CrossTabulator::parseCode($code) !== null;
            })) !== count($fields))){
            $problems[] = 'visitor.fields must be a list of field path codes';
        }
        foreach(array('measures' => CrossTabulator::MEASURES, 'export' => self::EXPORTS) as $key => $allowed){
            $list = $visitor[$key] ?? null;
            if($list !== null && (!is_array($list) || !self::isList($list) || array_diff($list, $allowed) !== array())){
                $problems[] = 'visitor.'.$key.' must be a list of '.implode(', ', $allowed);
            }
        }
        if(isset($visitor['drill']) && !is_bool($visitor['drill'])){
            $problems[] = 'visitor.drill must be true or false';
        }
    }

    /** A value of an enum, when given. */
    private static function mode(string $name, $value, array $allowed, array &$problems): void
    {
        if($value !== null && !in_array($value, $allowed, true)){
            $problems[] = $name.' must be one of '.implode(', ', $allowed);
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

    /** A positive integer (also as digits). */
    private static function isId($value): bool
    {
        return (is_int($value) && $value > 0) || (is_string($value) && preg_match('/^[1-9]\d*$/', $value) === 1);
    }

    /** A JSON list (keys 0..n-1). */
    private static function isList(array $value): bool
    {
        return $value === array() || array_keys($value) === range(0, count($value) - 1);
    }

    /** A JSON object (an array with keys that is not an empty list). */
    private static function isObject($value): bool
    {
        return is_array($value) && ($value === array() || !self::isList($value));
    }
}
