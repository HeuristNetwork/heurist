<?php
/**
* SavedFilterLink.php - The CMS action Saved filter (plan 27j): a public query source, a target page and the values of
* its parameters, as cmsSavedFilter.js of heurist-explorer.
*
* Stored as a reference: `{source, page, values}` (an action menu item has the page in `target`). The link opens the
* target page with `?source=<id>&v.<NAME>=<value>` (an array repeats the key); the site runtime of that page fills the
* values into the query. The server only writes the links and checks the stored values; it does not read them from a
* URL (PHP would turn the dot of `v.NAME` into `_`).
*
* Limits (short URLs, LIMITS = CMS_FILTER_LIMITS): 8 values, 200 characters a value, 10 items an array, 2000 characters
* the query string.
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

/** Values, checks and URL of a Saved filter link. */
final class SavedFilterLink
{
    /** Limits of the values (CMS_FILTER_LIMITS). */
    public const LIMITS = array('values' => 8, 'length' => 200, 'items' => 10, 'query' => 2000);

    /** Name of a parameter (`$NAME$` of the query). */
    private const NAME = '/^[A-Za-z][A-Za-z0-9_]*$/';

    /**
     * The values within the limits (normalizeCmsFilterValues): names of parameters, trimmed strings, arrays of strings;
     * empty values left out.
     *
     * @param mixed $values
     * @return array{0:array<string,string|array<int,string>>,1:bool} The values and whether something was dropped.
     */
    public static function values($values): array
    {
        $result = array();
        $dropped = false;
        if($values === null){ return array($result, false); }
        // an object (names), not a list (PHP 8.0: no array_is_list)
        if(!is_array($values) || (!empty($values) && array_keys($values) === range(0, count($values) - 1))){ return array($result, true); }
        $text = static function($value): ?string {
            if(is_int($value) || is_float($value)){ return (string)$value; }
            return is_string($value) ? trim($value) : null;
        };
        foreach($values as $name => $value){
            $name = (string)$name;
            if(preg_match(self::NAME, $name) !== 1){
                $dropped = true;
                continue;
            }
            // an object is not a value (only a list of strings)
            if(is_array($value) && !empty($value) && array_keys($value) !== range(0, count($value) - 1)){
                $dropped = true;
                continue;
            }
            if(is_array($value)){
                $list = array_map($text, array_values($value));
                foreach($list as $entry){
                    if($entry === null || mb_strlen($entry) > self::LIMITS['length']){ $dropped = true; }
                }
                if(count($list) > self::LIMITS['items']){ $dropped = true; }
                $item = array_slice(array_values(array_filter($list, static function($entry): bool {
                    return $entry !== null && $entry !== '' && mb_strlen($entry) <= SavedFilterLink::LIMITS['length'];
                })), 0, self::LIMITS['items']);
                if(empty($item)){ continue; }
            }else{
                $item = $text($value);
                if($item === null || mb_strlen($item) > self::LIMITS['length']){
                    $dropped = true;
                    continue;
                }
                if($item === ''){ continue; }
            }
            if(count($result) >= self::LIMITS['values']){
                $dropped = true;
                continue;
            }
            $result[$name] = $item;
        }
        return array($result, $dropped);
    }

    /**
     * The query string of the target page (cmsFilterQuery): `source=<id>&v.<NAME>=<value>`; '' when it is longer than
     * the limit or the source is not an id.
     */
    public static function query(int $source, $values): string
    {
        if($source < 1){ return ''; }
        $parts = array('source='.$source);
        foreach(self::values($values)[0] as $name => $value){
            foreach(is_array($value) ? $value : array($value) as $item){
                $parts[] = 'v.'.$name.'='.rawurlencode($item);
            }
        }
        $query = implode('&', $parts);
        return strlen($query) > self::LIMITS['query'] ? '' : $query;
    }

    /** The URL of the target page with the query (cmsFilterHref); '' without a page URL or a query. */
    public static function href(string $pageUrl, int $source, $values): string
    {
        $query = self::query($source, $values);
        if($pageUrl === '' || $query === ''){ return ''; }
        $parts = explode('#', $pageUrl, 2);
        return $parts[0].(strpos($parts[0], '?') !== false ? '&' : '?').$query.(isset($parts[1]) ? '#'.$parts[1] : '');
    }

    /**
     * Why a stored Saved filter is not valid, or null (cmsFilterProblem): a query source and a page, the values within
     * the limits.
     *
     * @param array $settings `source`, `page` (or `target` of an action menu item), `values`.
     */
    public static function problem(array $settings): ?string
    {
        $source = self::id($settings['source'] ?? null);
        if($source < 1){ return 'a Saved filter needs a query source'; }
        if(self::id($settings['page'] ?? $settings['target'] ?? null) < 1){ return 'a Saved filter needs a target page'; }
        [$values, $dropped] = self::values($settings['values'] ?? null);
        if($dropped){
            return 'the values of a Saved filter: at most '.self::LIMITS['values'].', '.self::LIMITS['length']
                .' characters each, arrays of '.self::LIMITS['items'];
        }
        if(self::query($source, $values) === ''){ return 'the values of a Saved filter are longer than '.self::LIMITS['query'].' characters'; }
        return null;
    }

    /** A positive id of a stored value (number or digits), or 0. */
    public static function id($value): int
    {
        if(is_int($value)){ return max(0, $value); }
        return is_string($value) && ctype_digit($value) ? intval($value) : 0;
    }
}
