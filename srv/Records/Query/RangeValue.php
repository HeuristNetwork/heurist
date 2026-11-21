<?php
/**
* RangeValue.php - Range values of thematic maps and crosstabs: "40", "40<>50", "69,71,75"
*
* The server side of shared/src/data/ranges/rangeDefinitions.js (plan 17 §2): parse() reads the syntax as parseRange,
* matches() compares a value as rangeMatches (numerically when both sides are numbers, otherwise as text). Used by the
* crosstabs (CrossTabulator, plan 27q2) for the intervals and categories a user defined; dates are compared there with
* the date index (Temporal), not here.
*
* @project     Heurist academic knowledge management system
* @package     Records\Query
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);
namespace Heurist\Records\Query;

/** Reads and matches range values. */
final class RangeValue
{
    /** Separator of an interval. */
    public const SEPARATOR = '<>';

    /**
     * Read a range value: a text in the syntax, a list of values, or {value} / {min, max}.
     *
     * @param mixed $value
     * @return array{kind:string,value?:string,min?:string,max?:string,values?:array<int,string>} kind: value, interval,
     *         list or empty.
     */
    public static function parse($value): array
    {
        if(is_array($value) && !self::isList($value)){
            if(isset($value['min'], $value['max']) && is_scalar($value['min']) && is_scalar($value['max'])){
                return array('kind'=>'interval', 'min'=>(string)$value['min'], 'max'=>(string)$value['max']);
            }
            return self::parse($value['value'] ?? null);
        }
        if(is_array($value)){
            $values = array();
            foreach($value as $item){
                $text = is_scalar($item) ? trim((string)$item) : '';
                if($text !== ''){ $values[] = $text; }
            }
            return empty($values) ? array('kind'=>'empty') : array('kind'=>'list', 'values'=>$values);
        }
        $text = is_scalar($value) ? trim((string)$value) : '';
        if($text === ''){ return array('kind'=>'empty'); }
        if(strpos($text, ',') !== false){
            $values = array_values(array_filter(array_map('trim', explode(',', $text)), static function($item){
                return $item !== '';
            }));
            return count($values) > 1 ? array('kind'=>'list', 'values'=>$values) : array('kind'=>'value', 'value'=>$values[0] ?? '');
        }
        $separator = strpos($text, self::SEPARATOR);
        if($separator !== false){
            $min = trim(substr($text, 0, $separator));
            $max = trim(substr($text, $separator + strlen(self::SEPARATOR)));
            if($min !== '' && $max !== ''){ return array('kind'=>'interval', 'min'=>$min, 'max'=>$max); }
            return array('kind'=>'value', 'value'=>$min !== '' ? $min : $max);
        }
        return array('kind'=>'value', 'value'=>$text);
    }

    /**
     * Whether a value falls in a parsed range.
     *
     * @param mixed $value Field value.
     * @param array $range parse() result.
     */
    public static function matches($value, array $range): bool
    {
        switch($range['kind']){
            case 'interval': return self::between($value, $range['min'], $range['max']);
            case 'list':
                foreach($range['values'] as $candidate){
                    if(self::equal($value, $candidate)){ return true; }
                }
                return false;
            case 'value': return self::equal($value, $range['value']);
            default: return false;
        }
    }

    /** Inclusive interval: numerically when the three are numbers, otherwise as text. */
    public static function between($value, $min, $max): bool
    {
        if(self::isNumber($value) && self::isNumber($min) && self::isNumber($max)){
            return (float)$value >= (float)$min && (float)$value <= (float)$max;
        }
        $text = (string)$value;
        return $text !== '' && strcmp($text, (string)$min) >= 0 && strcmp($text, (string)$max) <= 0;
    }

    /** Equal numerically when both are numbers, otherwise as text. */
    public static function equal($left, $right): bool
    {
        if(self::isNumber($left) && self::isNumber($right)){ return (float)$left === (float)$right; }
        return (string)$left === (string)$right;
    }

    /** A plain finite number (also as text). */
    public static function isNumber($value): bool
    {
        if(is_int($value) || is_float($value)){ return is_finite((float)$value); }
        return is_string($value) && trim($value) !== '' && is_numeric(trim($value));
    }

    /** A JSON list (keys 0..n-1). */
    private static function isList(array $value): bool
    {
        return $value === array() || array_keys($value) === range(0, count($value) - 1);
    }
}
