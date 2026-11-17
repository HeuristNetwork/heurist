<?php
/**
* SavedFilterLink.php - The CMS action Saved filter (plan 27j): a public query source or saved filter (plan 27l §7.2), a
* target page and the values of its parameters, as cmsSavedFilter.js of heurist-explorer.
*
* Stored as a reference: `{source, page, values}` or `{filter, page, values}` (an action menu item has the page in
* `target`). The link opens the target page with `?source=<id>&v.<NAME>=<value>` (`?filter=<id>` for a saved filter; an
* array repeats the key); the site runtime of that page fills the
* values into the query. The server only writes the links and checks the stored values; it does not read them from a
* URL (PHP would turn the dot of `v.NAME` into `_`).
*
* Limits (short URLs, LIMITS = CMS_FILTER_LIMITS): 8 values, 200 characters a value, 10 items an array, 2000 characters
* the query string.
*
* Values from the record (phase B): `{record: 'id'}` or `{field: '<concept code or id>'}`, filled in by PageContent for
* each record (reference(), resolve()); the query string leaves them out until then.
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

    /** A field of a value from the record: concept code or local id. */
    private const FIELD_CODE = '/^\d+(?:-\d+)?$/';

    /** The reference of a value from the record (cmsFilterReference): 'id', the field code, or null. */
    public static function reference($value): ?string
    {
        if(!is_array($value) || count($value) !== 1){ return null; }
        if(($value['record'] ?? null) === 'id'){ return 'id'; }
        $field = $value['field'] ?? null;
        return is_string($field) && preg_match(self::FIELD_CODE, $field) === 1 ? $field : null;
    }

    /** Whether the values have a value from the record. */
    public static function hasReferences($values): bool
    {
        foreach(is_array($values) ? $values : array() as $value){
            if(self::reference($value) !== null){ return true; }
        }
        return false;
    }

    /**
     * The values with the values from the record filled in: `id` the record id, a field its values (one: a string, more:
     * a list); without a record, or an empty field, the value is left out.
     *
     * @param mixed $values Stored values.
     * @param array|null $record A record with `details` (WebsiteRepository::records).
     * @param callable $fieldId Field code => local id.
     */
    public static function resolve($values, ?array $record, callable $fieldId): array
    {
        $result = array();
        foreach(self::values($values)[0] as $name => $value){
            $reference = self::reference($value);
            if($reference === null){
                $result[$name] = $value;
                continue;
            }
            if($record === null){ continue; }
            if($reference === 'id'){
                $result[$name] = (string)intval($record['rec_ID']);
                continue;
            }
            $list = array();
            foreach(WebsiteRepository::values($record, intval($fieldId($reference))) as $item){
                $text = is_scalar($item) ? trim((string)$item) : '';
                if($text !== '' && mb_strlen($text) <= self::LIMITS['length']){ $list[] = $text; }
            }
            if(empty($list)){ continue; }
            $result[$name] = count($list) === 1 ? $list[0] : array_slice($list, 0, self::LIMITS['items']);
        }
        return $result;
    }

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
            // a value from the record (phase B)
            $reference = self::reference($value);
            if($reference !== null){
                if(count($result) >= self::LIMITS['values']){
                    $dropped = true;
                    continue;
                }
                $result[$name] = $reference === 'id' ? array('record' => 'id') : array('field' => $reference);
                continue;
            }
            // another object is not a value (only a list of strings)
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
     * What a Saved filter runs (cmsFilterTarget): `['kind' => 'source', 'id']` (a query source id given as a number, or
     * settings with `source`) or `['kind' => 'filter', 'id']` (settings with `filter`, or the target itself); null
     * without one.
     *
     * @param mixed $value
     * @return array{kind:string,id:int}|null
     */
    public static function target($value): ?array
    {
        if(!is_array($value)){
            $id = self::id($value);
            return $id > 0 ? array('kind' => 'source', 'id' => $id) : null;
        }
        if(in_array($value['kind'] ?? null, array('source', 'filter'), true) && self::id($value['id'] ?? null) > 0){
            return array('kind' => $value['kind'], 'id' => self::id($value['id']));
        }
        if(self::id($value['filter'] ?? null) > 0){ return array('kind' => 'filter', 'id' => self::id($value['filter'])); }
        if(self::id($value['source'] ?? null) > 0){ return array('kind' => 'source', 'id' => self::id($value['source'])); }
        return null;
    }

    /**
     * The query string of the target page (cmsFilterQuery): `source=<id>&v.<NAME>=<value>` (`filter=<id>` for a saved
     * filter); '' when it is longer than the limit or there is no source.
     *
     * @param mixed $source A query source id, or a target / settings (target()).
     */
    public static function query($source, $values): string
    {
        $target = self::target($source);
        if($target === null){ return ''; }
        $parts = array($target['kind'].'='.$target['id']);
        foreach(self::values($values)[0] as $name => $value){
            // a value from the record is filled in first (resolve)
            if(self::reference($value) !== null){ continue; }
            foreach(is_array($value) ? $value : array($value) as $item){
                $parts[] = 'v.'.$name.'='.rawurlencode($item);
            }
        }
        $query = implode('&', $parts);
        return strlen($query) > self::LIMITS['query'] ? '' : $query;
    }

    /**
     * The URL of the target page with the query (cmsFilterHref); '' without a page URL or a query.
     *
     * @param mixed $source A query source id, or a target / settings (target()).
     */
    public static function href(string $pageUrl, $source, $values): string
    {
        $query = self::query($source, $values);
        if($pageUrl === '' || $query === ''){ return ''; }
        $parts = explode('#', $pageUrl, 2);
        return $parts[0].(strpos($parts[0], '?') !== false ? '&' : '?').$query.(isset($parts[1]) ? '#'.$parts[1] : '');
    }

    /**
     * Why a stored Saved filter is not valid, or null (cmsFilterProblem): a query source or saved filter and a page, the
     * values within the limits.
     *
     * @param array $settings `source` or `filter`, `page` (or `target` of an action menu item), `values`.
     */
    public static function problem(array $settings): ?string
    {
        $source = self::target(array('source' => $settings['source'] ?? null, 'filter' => $settings['filter'] ?? null));
        if($source === null){ return 'a Saved filter needs a query source or a saved filter'; }
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
