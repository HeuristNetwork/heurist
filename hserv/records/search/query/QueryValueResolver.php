<?php
/**
* QueryValueResolver.php - Resolve semantic query names before SQL composition
*
* @project     Heurist academic knowledge management system
* @package     Records\Search\Query
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       7.0
*/

namespace hserv\records\search\query;

require_once dirname(__FILE__, 2).'/SearchTypes.php';

use hserv\records\search\QueryValidationException;
use hserv\records\search\SearchExecutionException;

/** Resolves record types, fields, users, and enum values to IDs. */
final class QueryValueResolver
{
    private $mysqli;
    private $parser;
    private $cache = array();

    public function __construct($mysqli, RecordQueryParser $parser)
    {
        $this->mysqli = $mysqli;
        $this->parser = $parser;
    }

    /** Resolve one normalized query. Numeric queries pass through unchanged. */
    public function resolve(array $query): array
    {
        if(!$this->mysqli){ return $query; }
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
                if(!ctype_digit($fieldName)){
                    $fieldId = $this->resolveField($fieldName, $types);
                    $suffix = (string)$fieldId.($qualifier === '' ? '' : ':'.$qualifier);
                    $key = $base.':'.$suffix;
                }
                $fieldId = intval($fieldName === (string)intval($fieldName) ? $fieldName : $this->splitFieldSuffix($suffix)[0]);
                if(($base === 'f' || $base === 'field') && $fieldId>0){
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
            $rows = $this->rows(
                'SELECT rty_ID,rty_Name,rty_Plural FROM defRecTypes WHERE LOWER(rty_Name)=LOWER(?) OR LOWER(rty_Plural)=LOWER(?)',
                'ss', array($item, $item)
            );
            if(empty($rows)){
                $like = '%'.$item.'%';
                $rows = $this->rows(
                    'SELECT rty_ID,rty_Name,rty_Plural FROM defRecTypes WHERE rty_Name LIKE ? OR rty_Plural LIKE ? ORDER BY rty_ID',
                    'ss', array($like, $like)
                );
            }
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
        $params = array($name, $name); $types = 'ss';
        $scope = '';
        if(!empty($recordTypes)){
            $scope = ' AND rst.rst_RecTypeID IN ('.implode(',', array_fill(0, count($recordTypes), '?')).')';
            foreach($recordTypes as $id){ $types .= 'i'; $params[] = $id; }
        }
        $select = 'SELECT DISTINCT dty.dty_ID,dty.dty_Name,rst.rst_DisplayName FROM defDetailTypes dty '
            .'INNER JOIN defRecStructure rst ON rst.rst_DetailTypeID=dty.dty_ID WHERE ';
        $rows = $this->rows($select.'(LOWER(dty.dty_Name)=LOWER(?) OR LOWER(rst.rst_DisplayName)=LOWER(?))'.$scope.' ORDER BY dty.dty_ID', $types, $params);
        if(empty($rows)){
            $like = '%'.$name.'%'; $params[0] = $like; $params[1] = $like;
            $rows = $this->rows($select.'(dty.dty_Name LIKE ? OR rst.rst_DisplayName LIKE ?)'.$scope.' ORDER BY dty.dty_ID', $types, $params);
        }
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
        $rows = $this->rows('SELECT ugr_ID,ugr_Name FROM sysUGrps WHERE LOWER(ugr_Name)=LOWER(?)', 's', array($lookup));
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
        $rows = $this->rows(
            'SELECT trm_ID,trm_Label,trm_Code FROM defTerms WHERE LOWER(trm_Label)=LOWER(?) OR LOWER(trm_Code)=LOWER(?) ORDER BY trm_ID',
            'ss', array($label, $label)
        );
        $roots = $metadata['roots'];
        if(!empty($roots)){
            $allowed = array_fill_keys($this->termDescendants($roots), true);
            $rows = array_values(array_filter($rows, static function($row) use ($allowed){ return isset($allowed[intval($row[0])]); }));
        }
        $row = $this->uniqueMatch($rows, 'term for field '.$fieldId, $label, 0, array(1,2));
        return $operator === '' ? intval($row[0]) : $operator.(string)intval($row[0]);
    }

    private function fieldMetadata(int $fieldId, array $recordTypes): array
    {
        $key = 'metadata:'.$fieldId.':'.implode(',', $recordTypes);
        if(isset($this->cache[$key])){ return $this->cache[$key]; }
        $rows = $this->rows(
            'SELECT dty_Type,dty_JsonTermIDTree FROM defDetailTypes WHERE dty_ID=?',
            'i', array($fieldId)
        );
        if(empty($rows)){ throw new QueryValidationException('Unknown field ID: '.$fieldId); }
        $tree = $rows[0][1];
        if(!empty($recordTypes)){
            $sql = 'SELECT rst_FilteredJsonTermIDTree FROM defRecStructure WHERE rst_DetailTypeID=? AND rst_RecTypeID IN ('
                .implode(',', array_fill(0, count($recordTypes), '?')).')';
            $filtered = $this->rows($sql, 'i'.str_repeat('i', count($recordTypes)), array_merge(array($fieldId), $recordTypes));
            $filtered = array_values(array_filter(array_column($filtered, 0), static function($value){ return trim((string)$value) !== ''; }));
            if(!empty($filtered)){ $tree = implode(',', $filtered); }
        }
        preg_match_all('/\d+/', (string)$tree, $matches);
        return $this->cache[$key] = array('type'=>(string)$rows[0][0], 'roots'=>array_map('intval', $matches[0]));
    }

    private function termDescendants(array $roots): array
    {
        sort($roots); $cacheKey = 'terms:'.implode(',', $roots);
        if(isset($this->cache[$cacheKey])){ return $this->cache[$cacheKey]; }
        $all = array_fill_keys($roots, true); $level = $roots;
        while(!empty($level)){
            $sql = 'SELECT trl_TermID FROM defTermsLinks WHERE trl_ParentID IN ('.implode(',', array_fill(0, count($level), '?')).')';
            $rows = $this->rows($sql, str_repeat('i', count($level)), $level); $next = array();
            foreach($rows as $row){ $id=intval($row[0]); if(!isset($all[$id])){ $all[$id]=true; $next[]=$id; } }
            $level = $next;
        }
        return $this->cache[$cacheKey] = array_map('intval', array_keys($all));
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

    private function rows(string $sql, string $types, array $values): array
    {
        $stmt = $this->mysqli->prepare($sql);
        if(!$stmt){ throw new SearchExecutionException('Unable to prepare query value resolution: '.$this->mysqli->error); }
        try{
            if($types !== ''){
                $args = array($types); foreach($values as $index=>&$value){ $args[]=&$value; } unset($value);
                if(!call_user_func_array(array($stmt, 'bind_param'), $args)){ throw new SearchExecutionException('Unable to bind query value resolution: '.$stmt->error); }
            }
            if(!$stmt->execute()){ throw new SearchExecutionException('Unable to execute query value resolution: '.$stmt->error); }
            $metadata = $stmt->result_metadata();
            if(!$metadata){ return array(); }
            $row = array_fill(0, $metadata->field_count, null); $metadata->free();
            $refs = array(); foreach($row as $index=>&$column){ $refs[$index]=&$column; } unset($column);
            call_user_func_array(array($stmt, 'bind_result'), $refs);
            $rows = array();
            while($stmt->fetch()){
                $copy = array(); foreach($row as $column){ $copy[]=$column; } $rows[]=$copy;
            }
            return $rows;
        }finally{ $stmt->close(); }
    }

    private function splitFieldSuffix(string $suffix): array
    {
        $parts = explode(':', $suffix, 2); return array($parts[0], $parts[1] ?? '');
    }
    private function isFieldPredicate(string $base): bool
    {
        return in_array($base, array('f','field','fc','count','cnt','geo','file','lt','linked_to','linkedto','lf','linked_from','linkedfrom','rt','related_to','relatedto','rf','related_from','relatedfrom','relf','r'), true);
    }
    private function isNestedLink(string $base, $value): bool
    {
        return is_array($value) && in_array($base, array('lt','linked_to','linkedto','lf','linked_from','linkedfrom','rt','related_to','relatedto','rf','related_from','relatedfrom','related'), true);
    }
}
