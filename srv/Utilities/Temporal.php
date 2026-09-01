<?php
/**
* Temporal.php - Local temporal conversion utilities for the srv layer
*
* This is the minimal replacement for the legacy hserv utility used by the
* modern query compiler. It provides only the static conversion APIs required in
* the new code path, without importing the full legacy implementation.
*
* @project     Heurist academic knowledge management system
* @package     Utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);

namespace Heurist\Utilities;

/**
 * Temporal helper used by the modern srv query compiler.
 *
 * Date-index values are Heurist decimal dates (YYYY.MMDD), not Unix timestamps.
 * Search values may also be intervals such as 1999/2001, optionally prefixed by
 * the overlap/within operators consumed by the query compiler.
 */
final class Temporal
{
    /**
     * Convert a date-like value to ISO-8601 using the legacy Heurist conventions.
     *
     * @param mixed $value Raw date input.
     * @return string|null ISO date string, or null if the value cannot be coerced.
     */
    public static function dateToISO($value): ?string
    {
        if($value === null || $value === ''){
            return null;
        }

        if(is_string($value)){
            $value = trim($value);
            if($value === ''){
                return null;
            }

            if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1){
                return $value;
            }
            if(preg_match('/^\d{4}-\d{2}$/', $value) === 1){
                return $value.'-01';
            }
            if(preg_match('/^\d{4}$/', $value) === 1){
                return $value.'-01-01';
            }

            $ts = strtotime($value);
            if($ts !== false){
                return date('Y-m-d', $ts);
            }
        }

        if($value instanceof \DateTimeInterface){
            return $value->format('Y-m-d');
        }

        if(is_numeric($value)){
            return date('Y-m-d', (int)$value);
        }

        return null;
    }

    public function __construct($value, $isForSearch = false)
    {
        $this->value = is_string($value) ? trim($value) : $value;
        $this->isForSearch = (bool)$isForSearch;
        $this->minMax = $this->parseMinMax($this->value);
    }

    public function isValid(): bool
    {
        return $this->minMax !== null;
    }

    public function getMinMax(): array
    {
        return $this->minMax ?? array(0.0, 0.0);
    }

    /** @return array<int,float>|null */
    private function parseMinMax($value): ?array
    {
        if(!is_scalar($value)){ return null; }
        $text = trim((string)$value);
        if(strpos($text, '><') === 0 || strpos($text, '<>') === 0){
            $text = trim(substr($text, 2));
        }

        // Heurist's plain temporal syntax uses slash for start/end. A range's
        // missing precision expands outwards (1999/2001 => 1999..2001.1231).
        if(preg_match('/^(.+?)\s*\/\s*(.+)$/', $text, $parts) === 1){
            $start = self::dateParts($parts[1]);
            $end = self::dateParts($parts[2]);
            if($start === null || $end === null){ return null; }
            return array(self::decimalDate($start, false), self::decimalDate($end, true));
        }

        $parts = self::dateParts($text);
        if($parts === null){ return null; }
        $date = self::decimalDate($parts, false);
        return array($date, $date);
    }

    /** @return array{year:int,month:int|null,day:int|null}|null */
    private static function dateParts(string $value): ?array
    {
        $value = trim($value);
        if(preg_match('/^(-?\d{1,6})(?:-(\d{1,2})(?:-(\d{1,2}))?)?$/', $value, $match) !== 1){
            $iso = self::dateToISO($value);
            if($iso === null || preg_match('/^(-?\d+)-(\d{2})-(\d{2})/', $iso, $match) !== 1){ return null; }
        }
        $year = intval($match[1]);
        $month = isset($match[2]) && $match[2] !== '' ? intval($match[2]) : null;
        $day = isset($match[3]) && $match[3] !== '' ? intval($match[3]) : null;
        if($month !== null && ($month < 1 || $month > 12)){ return null; }
        if($day !== null && ($day < 1 || $day > self::daysInMonth($year, $month ?? 1))){ return null; }
        return array('year'=>$year, 'month'=>$month, 'day'=>$day);
    }

    private static function decimalDate(array $parts, bool $upperBound): float
    {
        $month = $parts['month'];
        $day = $parts['day'];
        if($upperBound){
            if($month === null){ $month = 12; }
            if($day === null){
                $day = self::daysInMonth($parts['year'], $month);
            }
        }
        if($month === null){ return (float)$parts['year']; }
        $digits = str_pad((string)$month, 2, '0', STR_PAD_LEFT);
        if($day !== null){ $digits .= str_pad((string)$day, 2, '0', STR_PAD_LEFT); }
        return (float)((string)$parts['year'].'.'.$digits);
    }

    private static function daysInMonth(int $year, int $month): int
    {
        if($month === 2){
            $absoluteYear = abs($year);
            $leap = ($absoluteYear % 4 === 0) && (($absoluteYear % 100 !== 0) || ($absoluteYear % 400 === 0));
            return $leap ? 29 : 28;
        }
        return in_array($month, array(4,6,9,11), true) ? 30 : 31;
    }

    private $value;
    private $isForSearch;
    private $minMax;
}
