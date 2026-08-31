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
 * Minimal temporal helper used by the modern srv query compiler.
 *
 * This intentionally covers the date-conversion methods required by the current
 * search pipeline rather than reimplementing the entire legacy Temporal object.
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
        $this->value = $value;
        $this->isForSearch = (bool)$isForSearch;
    }

    public function isValid(): bool
    {
        return self::dateToISO($this->value) !== null;
    }

    public function getMinMax(): array
    {
        $iso = self::dateToISO($this->value);
        if($iso === null){
            return array(0.0, 0.0);
        }
        $timestamp = strtotime($iso);
        if($timestamp === false){
            return array(0.0, 0.0);
        }
        return array((float)$timestamp, (float)$timestamp);
    }

    private $value;
    private $isForSearch;
}
