<?php
/**
* TemplateModifiers.php - Modifiers of report templates
*
* Port of the heuristModifier* functions of hserv/report/smartyInit.php
* (array_key_exists, array_column, arraysortby, array_multisort, sort, asort,
* ksort) and of the |translate modifier (ULocale getTranslation).
*
* Difference from the legacy engine: |translate translates terms and file
* captions (the legacy modifier looked for a global $smarty that the engine
* never set, so it always gave the untranslated text).
*
* @project     Heurist academic knowledge management system
* @package     Reports\Smarty
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Reports\Smarty;

/** Modifiers registered by SmartyEngineFactory. */
final class TemplateModifiers
{
    private TemplateApi $api;
    private LanguageCodes $languages;

    /**
     * @param TemplateApi $api The $heurist object of the run.
     * @param LanguageCodes $languages Language codes.
     */
    public function __construct(TemplateApi $api, LanguageCodes $languages)
    {
        $this->api = $api;
        $this->languages = $languages;
    }

    /** |sort */
    public static function sort($array)
    {
        if(is_array($array)){ sort($array); }
        return $array;
    }

    /** |asort */
    public static function asort($array)
    {
        if(is_array($array)){ asort($array); }
        return $array;
    }

    /** |ksort */
    public static function ksort($array)
    {
        if(is_array($array)){ ksort($array); }
        return $array;
    }

    /** |array_multisort - sorts the arrays, returns the last one. */
    public static function arrayMultisort(...$params)
    {
        array_multisort(...$params);
        return end($params);
    }

    /** |array_key_exists */
    public static function arrayKeyExists($key, $array): bool
    {
        return is_array($array) && (is_int($key) || is_string($key)) && array_key_exists($key, $array);
    }

    /** |array_column ('' for a non-array). */
    public static function arrayColumn($array, $column)
    {
        return is_array($array) ? array_column($array, $column) : '';
    }

    /**
     * |arraysortby:"field,-field,#numeric" - sort records by fields; "-" descending,
     * "#" numeric. Without fields: by the first key, or a plain sort.
     *
     * @param mixed $array
     * @param mixed $sortBy
     * @return mixed
     */
    public static function arraySortBy($array, $sortBy = null)
    {
        if(!is_array($array) || empty($array)){ return $array; }
        if($sortBy === null){
            $first = reset($array);
            if(!is_array($first)){
                sort($array);
                return $array;
            }
            $sortBy = array_keys($first)[0];
        }
        $keys = is_array($sortBy) ? $sortBy : explode(',', (string)$sortBy);
        uasort($array, static function($a, $b) use ($keys){
            foreach($keys as $key){
                $direction = 1;
                if(substr($key, 0, 1) === '-'){
                    $direction = -1;
                    $key = substr($key, 1);
                }
                $numeric = false;
                if(substr($key, 0, 1) === '#'){
                    $numeric = true;
                    $key = substr($key, 1);
                }
                $left = $a[$key] ?? null;
                $right = $b[$key] ?? null;
                if($left == $right){ continue; }
                if($numeric){
                    return $direction * ((float)$left <=> (float)$right);
                }
                return $direction * strcasecmp((string)(is_array($left) ? '' : $left), (string)(is_array($right) ? '' : $right));
            }
            return 0;
        });
        return $array;
    }

    /**
     * |translate:lang[:field] - a term (array with "term") or file (array with
     * "ulf_ID") in another language, or the value of a multilingual field.
     *
     * @param mixed $input
     * @param mixed $lang
     * @param mixed $field
     * @return mixed
     */
    public function translate($input, $lang = null, $field = null)
    {
        $code = $this->languages->code3($lang) ?? $this->api->language();
        if(is_array($input) && !empty($input)){
            $first = isset($input[0]) && is_array($input[0]) ? $input[0] : $input;
            if(!empty($first['term'])){
                return $this->api->getTranslation('trm', $first['id'], $field === null ? 'label' : (string)$field, $code);
            }
            if(!empty($first['ulf_ID'])){
                $fileField = $field === null || strpos((string)$field, 'cap') !== false ? 'ulf_Caption' : 'ulf_Description';
                return $this->api->getTranslation('ulf', $first['ulf_ID'], $fileField, $code);
            }
        }
        $value = $this->languages->valueIn($input, $code);
        return $value === null ? $input : $value;
    }
}
