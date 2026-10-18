<?php
/**
* QueryValueResolver.php - Resolve semantic query names before SQL composition
*
* @project     Heurist academic knowledge management system
* @package     Records\Search\Query
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

namespace Heurist\Records\Query\Compiler;

use Heurist\Database\DatabaseInterface;
use Heurist\Records\Query\QueryValidationException;
use Heurist\Definitions\DefinitionLookup;
use Heurist\Users\UserGroupRepository;
use Heurist\Records\Query\Parser\RecordQueryParser;

/** Resolves record types, fields, users, and enum values to IDs. */
final class QueryValueResolver
{
    private DatabaseInterface $database;
    private DefinitionLookup $definitions;
    private $parser;
    private $cache = array();

    public function __construct(DatabaseInterface $database, RecordQueryParser $parser)
    {
        $this->database = $database;
        $this->definitions = DefinitionLookup::shared($database);
        $this->parser = $parser;
    }

    /** Resolve one normalized query. Numeric queries pass through unchanged. */
    public function resolve(array $query): array
    {
        $this->cache = array();
        return $this->resolveGroup($query, array());
    }

    /** Resolve a group using the record types declared at this query level. */
    private function resolveGroup(array $group, array $inheritedTypes): array
    {
        $types = $this->declaredTypes($group);
        if(empty($types)){ $types = $inheritedTypes; }
        $result = array();
        foreach($group as $predicate){
            $key = (string)array_keys($predicate)[0];
            $value = $predicate[$key];
            list($base, $suffix) = $this->parser->predicateParts($key);

            if(in_array($base, array('t','type','typeid','typename'), true)){
                $value = $this->resolveRecordTypes($value);
            }elseif(in_array($base, array('user','usr','ws','workset','owner','workgroup','wg','addedby'), true)){
                $value = $this->resolveUser($value);
            }elseif(in_array($base, array('sortby','sort','s'), true)){
                $value = $this->resolveSortFields($value, $types);
            }

            if($this->isFieldPredicate($base) && $suffix !== ''){
                list($fieldName, $qualifier) = $this->splitFieldSuffix($suffix);
                // geo:within / geo:intersects carry a match mode, not a field name
                $isGeoMode = $base === 'geo' && in_array(strtolower($fieldName), array('within','intersects'), true);
                if(!ctype_digit($fieldName) && !$isGeoMode){
                    $fieldId = $this->resolveField($fieldName, $types);
                    $suffix = (string)$fieldId.($qualifier === '' ? '' : ':'.$qualifier);
                    $key = $base.':'.$suffix;
                }
                $fieldId = intval($fieldName === (string)intval($fieldName) ? $fieldName : $this->splitFieldSuffix($suffix)[0]);
                // f:20:code / :term / :desc / :conceptid compare that term column in the compiler
                if(($base === 'f' || $base === 'field') && $fieldId>0 && $qualifier === ''){
                    $value = $this->resolveEnumValue($fieldId, $value, $types);
                }
            }

            if(in_array($base, array('all','any','not'), true) && is_array($value)){
                $value = $this->resolveGroup($this->parser->normalizeQueryArray($value), $types);
            }elseif($this->isNestedLink($base, $value)){
                $value = $this->resolveGroup($this->parser->normalizeQueryArray($value), array());
            }
            $result[] = array($key=>$value);
        }
        return $result;
    }

    private function declaredTypes(array $group): array
    {
        $types = array();
        foreach($group as $predicate){
            $key = (string)array_keys($predicate)[0];
            list($base) = $this->parser->predicateParts($key);
            if(in_array($base, array('t','type','typeid','typename'), true)){
                $resolved = $this->resolveRecordTypes($predicate[$key]);
                $types = array_merge($types, is_array($resolved) ? $resolved : array($resolved));
            }
        }
        return array_values(array_unique(array_map('intval', $types)));
    }

    private function resolveRecordTypes($value)
    {
        $returnArray = is_array($value) || strpos((string)$value, ',') !== false;
        $items = is_array($value) ? $value : preg_split('/\s*,\s*/', trim((string)$value));
        $ids = array();
        foreach($items as $item){
            $item = trim((string)$item);
            if(ctype_digit($item)){ $ids[] = intval($item); continue; }
            if(preg_match('/^\d+-\d+$/', $item)){
                // concept code (one rule, DefinitionLookup)
                $id = $this->definitions->localId('rty', $item);
                if($id < 1){ throw new QueryValidationException('Unknown record type: '.$item); }
                $ids[] = $id;
                continue;
            }
            $rows = $this->definitions->findRectypes($item);
            $row = $this->uniqueMatch($rows, 'record type', $item, 0, array(1,2));
            $ids[] = intval($row[0]);
        }
        $ids = array_values(array_unique($ids));
        return $returnArray ? $ids : $ids[0];
    }

    private function resolveField(string $name, array $recordTypes): int
    {
        $cacheKey = 'field:'.implode(',', $recordTypes).':'.strtolower($name);
        if(isset($this->cache[$cacheKey])){ return $this->cache[$cacheKey]; }
        $rows = $this->definitions->findFields($name, $recordTypes);
        if(empty($rows) && !empty($recordTypes)){
            throw new QueryValidationException('Field "'.$name.'" is not present in record type(s) '.implode(', ', $recordTypes));
        }
        $row = $this->uniqueMatch($rows, 'field', $name, 0, array(1,2));
        return $this->cache[$cacheKey] = intval($row[0]);
    }

    private function resolveUser($value)
    {
        if(is_array($value)){ return array_map(array($this, 'resolveUser'), $value); }
        $item = trim((string)$value);
        if(strpos($item, ',') !== false){
            return implode(',', array_map(array($this, 'resolveUser'), preg_split('/\s*,\s*/', $item)));
        }
        $negative = strpos($item, '-') === 0;
        $lookup = $negative ? substr($item, 1) : $item;
        if($lookup === '' || ctype_digit($lookup) || in_array(strtolower($lookup), array('current','currentuser','current_user'), true)){
            return $value;
        }
        $rows = UserGroupRepository::shared($this->database)->findByName($lookup);
        $row = $this->uniqueMatch($rows, 'user', $lookup, 0, array(1));
        return $negative ? '-'.(string)intval($row[0]) : intval($row[0]);
    }

    private function resolveSortFields($value, array $recordTypes)
    {
        $items = is_array($value) ? $value : explode(',', (string)$value);
        foreach($items as &$item){
            $sort = trim((string)$item); $direction = '';
            if($sort !== '' && ($sort[0] === '-' || $sort[0] === '+')){ $direction=$sort[0]; $sort=substr($sort, 1); }
            if(preg_match('/^(?:f|field):(.+)$/i', $sort, $match) && !ctype_digit($match[1])){
                $sort = 'f:'.$this->resolveField($match[1], $recordTypes);
            }
            $item = $direction.$sort;
        }
        unset($item);
        return is_array($value) ? $items : implode(',', $items);
    }

    /** Resolve exact enum label/code before the record query; operators are retained. */
    private function resolveEnumValue(int $fieldId, $value, array $recordTypes)
    {
        if(is_array($value)){ return $value; }
        $metadata = $this->fieldMetadata($fieldId, $recordTypes);
        if(($metadata['type'] ?? '') !== 'enum'){ return $value; }
        $text = trim((string)$value);
        if($text === '' || is_numeric($text) || stripos($text, 'NULL') !== false || strpbrk($text, '*?@') !== false){ return $value; }
        preg_match('/^(<>|><|<=|>=|==|!=|=|<|>|-)?\s*(.*)$/s', $text, $match);
        $operator = $match[1] ?? ''; $label = trim($match[2] ?? $text);
        if($label === '' || ctype_digit($label)){ return $value; }
        // several term IDs (a multi-value selection, OR): nothing to resolve
        if(preg_match('/^\d+(\s*,\s*\d+)+$/', $label)){ return $value; }
        $path = array_values(array_filter(array_map('trim', explode('.', $label)), static function($part){ return $part !== ''; }));
        $leaf = count($path)>1 ? $path[count($path)-1] : $label;
        $rows = $this->definitions->termsByLabelOrCode($leaf);
        $roots = $metadata['roots'];
        if(!empty($roots)){
            $allowed = array_fill_keys($this->termDescendants($roots), true);
            $rows = array_values(array_filter($rows, static function($row) use ($allowed){ return isset($allowed[intval($row[0])]); }));
        }
        if(count($path)>1){
            $rows = array_values(array_filter($rows, function($row) use ($path){
                return $this->termMatchesPath(intval($row[0]), $path);
            }));
        }
        // no such term: the compiler compares the labels and finds no records - an empty
        // result, not an invalid query (2026-10-17)
        if(empty($rows)){ return $value; }
        $row = $this->uniqueMatch($rows, 'term for field '.$fieldId, $label, 0, array(1,2));
        return $operator === '' ? intval($row[0]) : $operator.(string)intval($row[0]);
    }

    /** Match a dotted hierarchical label, excluding the vocabulary root. */
    private function termMatchesPath(int $termId, array $path): bool
    {
        $labels = array();
        $seen = array();
        while($termId>0 && !isset($seen[$termId])){
            $seen[$termId] = true;
            $term = $this->definitions->term($termId);
            if($term === null){ return false; }
            $parentId = $term['parent'];
            // The top-level row is the vocabulary name and is not part of the
            // public hierarchical term label used by legacy queries.
            if($parentId>0){ array_unshift($labels, $term['term']); }
            $termId = $parentId;
        }
        $actual = strtolower(implode('.', $labels));
        return $actual === strtolower(implode('.', $path));
    }

    /** Field type and the roots of its term tree (the filtered tree of the record types when set). */
    private function fieldMetadata(int $fieldId, array $recordTypes): array
    {
        $key = 'metadata:'.$fieldId.':'.implode(',', $recordTypes);
        if(isset($this->cache[$key])){ return $this->cache[$key]; }
        $field = $this->definitions->field($fieldId);
        if($field === null){ throw new QueryValidationException('Unknown field ID: '.$fieldId); }
        $roots = $field['vocabulary'];
        $filtered = array();
        foreach($recordTypes as $rectypeId){
            $row = $this->definitions->structureRows(intval($rectypeId))[$fieldId] ?? null;
            if($row !== null && !empty($row['termFilter'])){ $filtered = array_merge($filtered, $row['termFilter']); }
        }
        if(!empty($filtered)){ $roots = $filtered; }
        return $this->cache[$key] = array('type'=>$field['type'], 'roots'=>array_map('intval', $roots));
    }

    private function termDescendants(array $roots): array
    {
        return $this->definitions->termDescendants($roots);
    }

    private function uniqueMatch(array $rows, string $kind, string $input, int $idColumn, array $nameColumns): array
    {
        $unique = array(); foreach($rows as $row){ $unique[(string)$row[$idColumn]] = $row; }
        $rows = array_values($unique);
        if(count($rows) === 1){ return $rows[0]; }
        if(empty($rows)){ throw new QueryValidationException('Unknown '.$kind.': '.$input); }
        $matches = array();
        foreach($rows as $row){
            $names = array(); foreach($nameColumns as $column){ if(trim((string)$row[$column]) !== ''){ $names[] = $row[$column]; } }
            $matches[] = $row[$idColumn].' ('.implode(' / ', $names).')';
        }
        throw new QueryValidationException('Ambiguous '.$kind.' "'.$input.'". Matches: '.implode(', ', $matches));
    }

    private function splitFieldSuffix(string $suffix): array
    {
        $parts = explode(':', $suffix, 2); return array($parts[0], $parts[1] ?? '');
    }
    private function isFieldPredicate(string $base): bool
    {
        return in_array($base, array('f','field','fc','count','cnt','geo','file','lt','linked_to','linkedto','lf','linked_from','linkedfrom','rt','related_to','relatedto','rf','related_from','relatedfrom','related','relf','r'), true);
    }
    private function isNestedLink(string $base, $value): bool
    {
        return is_array($value) && in_array($base, array('lt','linked_to','linkedto','lf','linked_from','linkedfrom','rt','related_to','relatedto','rf','related_from','relatedfrom','related','links','connected'), true);
    }
}
