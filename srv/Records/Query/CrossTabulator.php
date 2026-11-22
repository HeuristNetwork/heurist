<?php
/**
* CrossTabulator.php - Crosstabs: record counts (or a measure) by the values of up to three fields (plan 27q2)
*
* Serves `/records/aggregate?op=crosstab`. Dimensions: rows (required), columns, pages. Each dimension is a field path
* code of the thematic maps (`10:20`, `10:lt240:48:237`; plan 17): a field of the records found, or of records linked
* to them. Its buckets are
* - `values`: one bucket per distinct value (at most MAX_VALUE_BUCKETS, the most frequent; the others in `other`);
* - `{"groupby": "decade"}` (dates: month, year, decade, century) or `{"ranges": 10}` (numbers): the ranges of
*   op=ranges (FieldValueBuckets) between the smallest and largest value;
* - `{"ranges": ["1900<>1909", {"value": "69,71", "label": "North"}]}`: the user's intervals and categories
*   (RangeValue syntax), in this order.
* Records without a value go to the bucket `blank`, values outside every bucket to `other`.
*
* Counting is by record key sets: the records of the query are found once; for each dimension the bucket keys of each
* record are read in chunks (fields of linked records through RecordSearchService::linkedPathTargets); the cells are
* the combinations of the keys of each record. So a record counts once in each of its cells, and the totals are
* distinct records (not sums of cells). Dates match a range as the search does: the estimated span of a date (date
* index) overlaps the range. The measure (sum, avg, min, max) is the first numeric value of a field of the record
* (Artem, 2026-11-20).
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

use Heurist\Database\QueryTrace;
use Heurist\Records\Query\Compiler\QueryBuilder;
use Heurist\Records\Query\Compiler\SqlBuildContext;
use Heurist\Utilities\Temporal;

/** Counts records by the buckets of up to three dimensions. */
final class CrossTabulator
{
    /** Most records of one crosstab. */
    public const MAX_RECORDS = 200000;
    /** Most buckets of `values` per dimension. */
    public const MAX_VALUE_BUCKETS = 500;
    /** Most user-defined or generated buckets per dimension. */
    public const MAX_RANGE_BUCKETS = 1000;
    /** Most link steps of a field path (the depth of the thematic field tree). */
    public const MAX_HOPS = 2;
    public const DIMENSIONS = array('rows', 'columns', 'pages');
    public const MEASURES = array('count', 'sum', 'avg', 'min', 'max');
    private const ID_CHUNK_SIZE = 5000;
    private const HEADER_CATEGORIES = array('rectype', 'owner', 'addedby', 'access', 'tag', 'title');
    private const HEADER_DATES = array('added', 'modified');
    private const NUMERIC_TYPES = array('integer', 'float', 'year');
    private const CATEGORY_TYPES = array('freetext', 'enum', 'relationtype', 'boolean', 'resource', 'relmarker');
    /** Link token of a path code → the link predicate from the near records to the far ones. */
    private const LINKS = array('lt'=>'lt', 'lf'=>'lf', 'rt'=>'rt', 'rf'=>'rf', 'r'=>'related');

    /** @var QueryBuilder */
    private $builder;
    /** @var QueryExecutor */
    private $executor;
    /** @var FieldValueCounter */
    private $counter;
    /** @var FieldValueRange */
    private $range;
    /** @var FieldValueBuckets */
    private $buckets;

    public function __construct(QueryBuilder $builder, QueryExecutor $executor)
    {
        $this->builder = $builder;
        $this->executor = $executor;
        $this->counter = new FieldValueCounter($builder, $executor);
        $this->range = new FieldValueRange($builder, $executor);
        $this->buckets = new FieldValueBuckets($builder, $executor);
    }

    /**
     * Validate the crosstab parameter.
     *
     * @param mixed $spec Decoded `crosstab` parameter: {rows, columns?, pages?, measure?}.
     * @return array{dimensions:array<string,array|null>,measure:array{op:string,field:int}}
     * @throws QueryValidationException
     */
    public function describe($spec): array
    {
        if(!is_array($spec) || !is_array($spec['rows'] ?? null)){
            throw new QueryValidationException('op=crosstab requires crosstab={"rows": {"code": ...}}');
        }
        $dimensions = array();
        foreach(self::DIMENSIONS as $name){
            $value = $spec[$name] ?? null;
            if($value === null || $value === array()){ $dimensions[$name] = null; continue; }
            if(!is_array($value)){ throw new QueryValidationException('crosstab.'.$name.' must be an object'); }
            $dimensions[$name] = $this->describeDimension($name, $value);
        }
        $measure = is_array($spec['measure'] ?? null) ? $spec['measure'] : array('op'=>'count');
        $op = strtolower(trim((string)($measure['op'] ?? 'count')));
        if(!in_array($op, self::MEASURES, true)){
            throw new QueryValidationException('crosstab.measure.op must be one of '.implode(', ', self::MEASURES));
        }
        $fieldId = 0;
        if($op !== 'count'){
            $fieldId = intval($measure['field'] ?? 0);
            $type = $fieldId > 0 ? $this->executor->definitions()->fieldType($fieldId) : '';
            if(!in_array($type, self::NUMERIC_TYPES, true)){
                throw new QueryValidationException('crosstab.measure.field must be a numeric field of the records');
            }
        }
        return array('dimensions'=>$dimensions, 'measure'=>array('op'=>$op, 'field'=>$fieldId));
    }

    /**
     * The crosstab of the records found.
     *
     * @param int[] $ids Records of the query (checked for access).
     * @param array $context Search context.
     * @param array $spec describe() result.
     * @param callable(array,array):array $linkedTargets (root ids, via) => root id → linked record ids.
     * @return array{total:int,rows:array,columns:?array,pages:?array,measure:array,cells:array,totals:array}
     */
    public function tabulate(array $ids, array $context, array $spec, callable $linkedTargets): array
    {
        $ids = FieldValueRange::uniqueIds($ids);
        if(count($ids) > self::MAX_RECORDS){
            throw new QueryValidationException('A crosstab can count at most '.self::MAX_RECORDS.' records ('
                .count($ids).' found): narrow the filter');
        }
        // bucket keys of each record per dimension (absent dimensions: one key 0)
        $keys = array();
        $output = array();
        foreach(self::DIMENSIONS as $name){
            $dimension = $spec['dimensions'][$name];
            if($dimension === null){ $output[$name] = null; continue; }
            QueryTrace::begin('crosstab '.$name);
            [$output[$name], $keys[$name]] = $this->dimensionKeys($ids, $context, $dimension, $linkedTargets);
            QueryTrace::end(count($output[$name]['buckets']));
        }
        $measure = $spec['measure'];
        $values = $measure['op'] === 'count' ? array() : $this->measureValues($ids, $measure['field']);

        QueryTrace::begin('crosstab cells');
        $cells = array(); $rowTotals = array(); $columnTotals = array(); $pageTotals = array();
        $grand = self::emptyAggregate();
        $none = array(0);
        foreach($ids as $id){
            $value = $values[$id] ?? null;
            $rows = $keys['rows'][$id];
            $columns = isset($keys['columns']) ? $keys['columns'][$id] : $none;
            $pages = isset($keys['pages']) ? $keys['pages'][$id] : $none;
            self::add($grand, $value);
            foreach($pages as $page){
                self::add($pageTotals[$page], $value);
                foreach($rows as $row){ self::add($rowTotals[$page][$row], $value); }
                foreach($columns as $column){ self::add($columnTotals[$page][$column], $value); }
                foreach($rows as $row){
                    foreach($columns as $column){ self::add($cells[$row.':'.$column.':'.$page], $value); }
                }
            }
        }
        $op = $measure['op'];
        $result = array('total'=>count($ids));
        foreach(self::DIMENSIONS as $name){ $result[$name] = $output[$name]; }
        $result['measure'] = $op === 'count' ? array('op'=>'count') : array('op'=>$op, 'field'=>$measure['field']);
        $result['cells'] = array();
        foreach($cells as $key=>$aggregate){
            [$row, $column, $page] = array_map('intval', explode(':', $key));
            $result['cells'][] = self::row(array($row, $column, $page), $aggregate, $op);
        }
        $result['totals'] = array('rows'=>array(), 'columns'=>array(), 'pages'=>array(), 'grand'=>self::row(array(), $grand, $op));
        foreach($rowTotals as $page=>$byRow){
            foreach($byRow as $row=>$aggregate){ $result['totals']['rows'][] = self::row(array($page, $row), $aggregate, $op); }
        }
        foreach($columnTotals as $page=>$byColumn){
            foreach($byColumn as $column=>$aggregate){ $result['totals']['columns'][] = self::row(array($page, $column), $aggregate, $op); }
        }
        foreach($pageTotals as $page=>$aggregate){ $result['totals']['pages'][] = self::row(array($page), $aggregate, $op); }
        QueryTrace::end(count($result['cells']));
        return $result;
    }

    // ------------------------------------------------------------------ dimensions

    /** Validate one dimension: field path code, field kind and buckets. */
    private function describeDimension(string $name, array $value): array
    {
        $code = trim((string)($value['code'] ?? $value['field'] ?? ''));
        $path = self::parseCode($code);
        if($path === null){
            throw new QueryValidationException('crosstab.'.$name.'.code must be a field path code (rty:dty, rty:lt12:rty:dty)');
        }
        if(count($path['via']) > self::MAX_HOPS){
            throw new QueryValidationException('crosstab.'.$name.': at most '.self::MAX_HOPS.' links to a field');
        }
        $field = $path['field'];
        if(in_array($field, self::HEADER_DATES, true)){
            $kind = 'date';
            $described = $this->range->describeField($field, 'crosstab');
            $fieldType = 'date';
        }elseif(in_array($field, self::HEADER_CATEGORIES, true)){
            $kind = 'category';
            $described = $this->counter->describeField($field);
            $fieldType = $field;
        }else{
            $fieldType = $this->executor->definitions()->fieldType(intval($field));
            if($fieldType === 'date'){
                $kind = 'date';
                $described = $this->range->describeField($field, 'crosstab');
            }elseif(in_array($fieldType, self::NUMERIC_TYPES, true)){
                $kind = 'number';
                $described = $this->counter->describeField($field);
                $described['dtyType'] = $fieldType;
            }elseif(in_array($fieldType, self::CATEGORY_TYPES, true)){
                $kind = 'category';
                $described = $this->counter->describeField($field);
            }else{
                throw new QueryValidationException('crosstab.'.$name.': field type '.($fieldType ?: 'unknown')
                    .' can not be a dimension');
            }
        }
        $dimension = array('name'=>$name, 'code'=>$code, 'field'=>$field, 'kind'=>$kind, 'fieldType'=>$fieldType,
            'via'=>$path['via'], 'source'=>$described, 'buckets'=>$this->describeBuckets($name, $kind, $value['buckets'] ?? 'values'));
        return $dimension;
    }

    /** Buckets: values, {groupby} (dates), {ranges: N} (numbers) or {ranges: [...]} (any field). */
    private function describeBuckets(string $name, string $kind, $buckets): array
    {
        if($buckets === 'values' || $buckets === null){ return array('mode'=>'values'); }
        if(!is_array($buckets)){ throw new QueryValidationException('crosstab.'.$name.'.buckets must be "values" or an object'); }
        if(isset($buckets['groupby'])){
            if($kind !== 'date'){ throw new QueryValidationException('crosstab.'.$name.'.buckets.groupby is for date fields'); }
            return array('mode'=>'auto', 'groupby'=>strtolower(trim((string)$buckets['groupby'])));
        }
        $ranges = $buckets['ranges'] ?? null;
        if(is_int($ranges) || (is_string($ranges) && ctype_digit($ranges))){
            if($kind !== 'number'){ throw new QueryValidationException('crosstab.'.$name.'.buckets.ranges=N is for numeric fields'); }
            return array('mode'=>'auto', 'ranges'=>(string)$ranges);
        }
        if(!is_array($ranges) || empty($ranges)){
            throw new QueryValidationException('crosstab.'.$name.'.buckets.ranges must be a number or a list of ranges');
        }
        if(count($ranges) > self::MAX_RANGE_BUCKETS){
            throw new QueryValidationException('crosstab.'.$name.': at most '.self::MAX_RANGE_BUCKETS.' ranges');
        }
        $list = array();
        foreach(array_values($ranges) as $index=>$range){
            $label = is_array($range) && isset($range['label']) && is_scalar($range['label']) ? (string)$range['label'] : null;
            $parsed = RangeValue::parse($range);
            if($parsed['kind'] === 'empty'){
                throw new QueryValidationException('crosstab.'.$name.'.buckets.ranges['.$index.'] is empty');
            }
            $list[] = array('range'=>$parsed, 'label'=>$label, 'value'=>self::rangeText($parsed));
        }
        return array('mode'=>'ranges', 'ranges'=>$list);
    }

    /**
     * The buckets of a dimension and the bucket keys of each record.
     *
     * @return array{0:array,1:array<int,int[]>} The public dimension, record id → bucket indexes.
     */
    private function dimensionKeys(array $ids, array $context, array $dimension, callable $linkedTargets): array
    {
        // the records that hold the values: the records themselves, or the records reached through the links
        if(empty($dimension['via'])){
            $holdersOf = null;
            $holders = $ids;
        }else{
            $holdersOf = $linkedTargets($ids, $dimension['via']);
            $holders = array();
            foreach($holdersOf as $targets){ foreach($targets as $target){ $holders[intval($target)] = true; } }
            $holders = array_keys($holders);
        }
        $raw = $this->rawValues($holders, $context, $dimension);
        $plan = $this->bucketPlan($dimension, $raw, $holdersOf, $ids);
        $buckets = $plan['buckets'];
        $other = null; $blank = null;
        $keys = array();
        foreach($ids as $id){
            $sources = $holdersOf === null ? array($id) : ($holdersOf[$id] ?? array());
            $found = array(); $hasValue = false;
            foreach($sources as $source){
                foreach($raw[intval($source)] ?? array() as $item){
                    $hasValue = true;
                    foreach(($plan['match'])($item) as $index){ $found[$index] = true; }
                }
            }
            if(empty($found)){
                if($hasValue){
                    $other ??= count($buckets) + ($blank === null ? 0 : 1);
                    $found[$other] = true;
                }else{
                    $blank ??= count($buckets) + ($other === null ? 0 : 1);
                    $found[$blank] = true;
                }
            }
            $keys[$id] = array_keys($found);
        }
        // other / blank after the buckets, in the order they were first needed
        $extra = array();
        if($other !== null){ $extra[$other] = array('other'=>true); }
        if($blank !== null){ $extra[$blank] = array('blank'=>true); }
        ksort($extra);
        foreach($extra as $bucket){ $buckets[] = $bucket; }
        $public = array('code'=>$dimension['code'], 'field'=>$dimension['field'], 'type'=>$dimension['kind'],
            'fieldType'=>$dimension['fieldType'], 'buckets'=>$buckets, 'truncated'=>$plan['truncated']);
        return array($public, $keys);
    }

    /**
     * Values of the holder records: id → list of items. Categories and numbers: {value, label, rty, kind};
     * detail dates: {value, lo, hi} (date index decimals); header dates: {value, lo, hi} (datetimes).
     *
     * @return array<int,array<int,array>>
     */
    private function rawValues(array $holders, array $context, array $dimension): array
    {
        $result = array();
        if(empty($holders)){ return $result; }
        if($dimension['kind'] === 'date'){
            $field = $dimension['source'];
            foreach(array_chunk($holders, self::ID_CHUNK_SIZE) as $chunk){
                $state = new SqlBuildContext($context);
                $parts = $this->range->sourceParts($field, $state);
                $where = array_merge($parts['where'], array(FieldValueRange::idCondition($chunk, $state)));
                $value = $field['dtyId'] === 0 ? $parts['min'] : 'vd.dtl_Value';
                $rows = $this->executor->executeRows($this->builder->sql(
                    'SELECT DISTINCT r.rec_ID, '.$value.', '.$parts['min'].', '.$parts['max'].' FROM {records} r '
                        .$parts['joins'].' WHERE '.implode(' AND ', $where)
                ), $state->types(), $state->values());
                foreach($rows as $row){
                    if($row[2] === null || $row[3] === null){ continue; }
                    $text = $field['dtyId'] === 0 ? substr((string)$row[1], 0, 10) : trim((string)$row[1]);
                    $result[intval($row[0])][] = array('value'=>$text,
                        'lo'=>$field['dtyId'] === 0 ? (string)$row[2] : (float)$row[2],
                        'hi'=>$field['dtyId'] === 0 ? (string)$row[3] : (float)$row[3]);
                }
            }
            return $result;
        }
        foreach($this->counter->recordValues($holders, $context, $dimension['source']) as $row){
            if($row[1] === null || (string)$row[1] === ''){ continue; }
            if($dimension['kind'] === 'number'){
                if(!RangeValue::isNumber((string)$row[1])){ continue; }
                $value = FieldValueRange::number((float)trim((string)$row[1]));
            }else{
                $value = is_numeric($row[1]) && ctype_digit((string)$row[1]) ? intval($row[1]) : (string)$row[1];
            }
            $item = array('value'=>$value);
            if($row[2] !== null && $row[2] !== ''){ $item['label'] = (string)$row[2]; }
            if($row[3] !== null && $row[3] !== ''){ $item['rty'] = intval($row[3]); }
            if($row[4] !== null && $row[4] !== ''){ $item['kind'] = (string)$row[4]; }
            $result[intval($row[0])][] = $item;
        }
        return $result;
    }

    /**
     * The buckets of a dimension and the matcher of one value item → bucket indexes.
     *
     * @return array{buckets:array,match:callable,truncated:bool}
     */
    private function bucketPlan(array $dimension, array $raw, ?array $holdersOf, array $ids): array
    {
        $mode = $dimension['buckets']['mode'];
        if($mode === 'values'){ return $this->valueBuckets($dimension, $raw, $holdersOf, $ids); }
        if($mode === 'auto'){ return $this->autoBuckets($dimension, $raw); }
        $buckets = array(); $tests = array();
        foreach($dimension['buckets']['ranges'] as $index=>$range){
            $bucket = array('value'=>$range['value']);
            if($range['label'] !== null){ $bucket['label'] = $range['label']; }
            $buckets[] = $bucket;
            $tests[$index] = $this->rangeTest($dimension, $range['range']);
        }
        return array('buckets'=>$buckets, 'truncated'=>false, 'match'=>static function(array $item) use ($tests){
            $found = array();
            foreach($tests as $index=>$test){ if($test($item)){ $found[] = $index; } }
            return $found;
        });
    }

    /** One bucket per distinct value: the most frequent MAX_VALUE_BUCKETS, ordered by value. */
    private function valueBuckets(array $dimension, array $raw, ?array $holdersOf, array $ids): array
    {
        $count = array(); $items = array();
        foreach($ids as $id){
            $seen = array();
            foreach($holdersOf === null ? array($id) : ($holdersOf[$id] ?? array()) as $source){
                foreach($raw[intval($source)] ?? array() as $item){
                    $key = (string)$item['value'];
                    if(isset($seen[$key])){ continue; }
                    $seen[$key] = true;
                    $count[$key] = ($count[$key] ?? 0) + 1;
                    if(!isset($items[$key])){
                        $items[$key] = array_intersect_key($item, array('value'=>1, 'label'=>1, 'rty'=>1, 'kind'=>1));
                    }
                }
            }
        }
        $keys = array_map('strval', array_keys($count));
        $truncated = count($keys) > self::MAX_VALUE_BUCKETS;
        if($truncated){
            usort($keys, static function($a, $b) use ($count){ return $count[$b] <=> $count[$a] ?: strnatcasecmp($a, $b); });
            $keys = array_slice($keys, 0, self::MAX_VALUE_BUCKETS);
        }
        $numeric = $dimension['kind'] === 'number';
        usort($keys, static function($a, $b) use ($items, $numeric){
            if($numeric){ return (float)$a <=> (float)$b; }
            return strnatcasecmp((string)($items[$a]['label'] ?? $a), (string)($items[$b]['label'] ?? $b)) ?: strcmp($a, $b);
        });
        $index = array_flip($keys);
        $buckets = array_map(static function($key) use ($items){ return $items[$key]; }, $keys);
        return array('buckets'=>$buckets, 'truncated'=>$truncated, 'match'=>static function(array $item) use ($index){
            $key = (string)$item['value'];
            return isset($index[$key]) ? array($index[$key]) : array();
        });
    }

    /** The ranges of op=ranges between the smallest and the largest value (FieldValueBuckets). */
    private function autoBuckets(array $dimension, array $raw): array
    {
        $field = $this->buckets->describe($dimension['field'], array_diff_key($dimension['buckets'], array('mode'=>1)));
        $min = null; $max = null;
        foreach($raw as $items){
            foreach($items as $item){
                $lo = $dimension['kind'] === 'date' ? $item['lo'] : (float)$item['value'];
                $hi = $dimension['kind'] === 'date' ? $item['hi'] : (float)$item['value'];
                if($min === null || self::less($lo, $min)){ $min = $lo; }
                if($max === null || self::less($max, $hi)){ $max = $hi; }
            }
        }
        $specs = $this->buckets->specsForBounds($field, $min, $max);
        $buckets = array(); $bounds = array();
        foreach($specs as $spec){
            $buckets[] = array('from'=>$spec['from'], 'to'=>$spec['to'], 'label'=>$spec['label']);
            $bounds[] = array($spec['lo'], $spec['hi']);
        }
        $date = $dimension['kind'] === 'date';
        return array('buckets'=>$buckets, 'truncated'=>false, 'match'=>static function(array $item) use ($bounds, $date){
            $found = array();
            foreach($bounds as $index=>[$lo, $hi]){
                $fits = $date
                    ? !self::less($item['hi'], $lo) && !self::less($hi, $item['lo'])
                    : (float)$item['value'] >= (float)$lo && (float)$item['value'] <= (float)$hi;
                if($fits){ $found[] = $index; }
            }
            return $found;
        });
    }

    /** The test of one user range: dates by their span (as the search), the others as RangeValue. */
    private function rangeTest(array $dimension, array $range): callable
    {
        if($dimension['kind'] !== 'date'){
            return static function(array $item) use ($range){ return RangeValue::matches($item['value'], $range); };
        }
        $header = $dimension['source']['dtyId'] === 0;
        $spans = array();
        $parts = $range['kind'] === 'list' ? array_map(static function($value){ return array('kind'=>'value', 'value'=>$value); }, $range['values'])
            : array($range);
        foreach($parts as $part){
            $from = $part['kind'] === 'interval' ? $part['min'] : $part['value'];
            $to = $part['kind'] === 'interval' ? $part['max'] : $part['value'];
            $spans[] = $header ? self::headerSpan($from, $to) : self::dateSpan($from, $to);
        }
        return static function(array $item) use ($spans){
            foreach($spans as [$lo, $hi]){
                if(!self::less($item['hi'], $lo) && !self::less($hi, $item['lo'])){ return true; }
            }
            return false;
        };
    }

    /** Bounds of a detail date range as the search compares them (Temporal, date index decimals). */
    private static function dateSpan(string $from, string $to): array
    {
        $temporal = new Temporal($from === $to ? $from : $from.'/'.$to, true);
        $bounds = $temporal->isValid() ? $temporal->getMinMax() : null;
        if(!is_array($bounds) || $bounds[0] === null || $bounds[1] === null){
            throw new QueryValidationException('Date range '.$from.($from === $to ? '' : ' <> '.$to).' can not be read');
        }
        return array((float)$bounds[0], (float)$bounds[1]);
    }

    /** Bounds of a header date range (added / modified): whole days, a year alone as its whole year. */
    private static function headerSpan(string $from, string $to): array
    {
        $day = static function(string $text, bool $upper): string {
            $text = trim($text);
            if(preg_match('/^-?\d{1,4}$/', $text)){ return $text.($upper ? '-12-31 23:59:59' : '-01-01 00:00:00'); }
            if(preg_match('/^-?\d{4}-\d{2}$/', $text)){
                return $upper ? $text.'-'.Temporal::daysInMonth(intval(substr($text, 0, -3)), intval(substr($text, -2))).' 23:59:59'
                    : $text.'-01 00:00:00';
            }
            if(strlen($text) === 10){ return $text.($upper ? ' 23:59:59' : ' 00:00:00'); }
            return $text;
        };
        return array($day($from, false), $day($to, true));
    }

    // ------------------------------------------------------------------ measure

    /**
     * The first numeric value of a field of each record (Artem, 2026-11-20).
     *
     * @return array<int,float>
     */
    private function measureValues(array $ids, int $fieldId): array
    {
        $values = array();
        foreach(array_chunk($ids, self::ID_CHUNK_SIZE) as $chunk){
            foreach($this->executor->records()->values($chunk, array($fieldId)) as $id=>$fields){
                foreach($fields[$fieldId] ?? array() as $value){
                    if(RangeValue::isNumber($value)){
                        $values[intval($id)] = (float)$value;
                        break;
                    }
                }
            }
        }
        return $values;
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Read a field path code: `[rty:]{link dty[:rty]}*:field`, field = a field id or a header keyword.
     *
     * @return array{field:string,via:array}|null
     */
    public static function parseCode(string $code): ?array
    {
        $tokens = array_values(array_filter(array_map('trim', explode(':', strtolower($code))), static function($token){
            return $token !== '';
        }));
        if(empty($tokens)){ return null; }
        $field = array_pop($tokens);
        if(!ctype_digit($field) && !in_array($field, array_merge(self::HEADER_CATEGORIES, self::HEADER_DATES), true)){
            return null;
        }
        $index = 0;
        if(isset($tokens[0]) && ctype_digit($tokens[0])){ $index = 1; }
        $via = array();
        while($index < count($tokens)){
            if(!preg_match('/^(lt|lf|rt|rf|r)(\d*)$/', $tokens[$index], $match)){ return null; }
            $target = isset($tokens[$index + 1]) && ctype_digit($tokens[$index + 1]) ? $tokens[$index + 1] : null;
            $key = self::LINKS[$match[1]].($match[2] !== '' ? ':'.$match[2] : '');
            $via[] = array($key => $target !== null ? array(array('t'=>$target)) : array());
            $index += $target !== null ? 2 : 1;
        }
        return array('field'=>$field, 'via'=>$via);
    }

    /** The text of a parsed range (the bucket's value in the response). */
    private static function rangeText(array $range): string
    {
        switch($range['kind']){
            case 'interval': return $range['min'].RangeValue::SEPARATOR.$range['max'];
            case 'list': return implode(',', $range['values']);
            default: return (string)($range['value'] ?? '');
        }
    }

    /** a < b: numbers numerically, header datetimes as text. */
    private static function less($a, $b): bool
    {
        return is_string($a) || is_string($b) ? strcmp((string)$a, (string)$b) < 0 : $a < $b;
    }

    /** @return array{n:int,m:int,sum:float,min:?float,max:?float} records, records with a measure value, sum, min, max */
    private static function emptyAggregate(): array
    {
        return array('n'=>0, 'm'=>0, 'sum'=>0.0, 'min'=>null, 'max'=>null);
    }

    /** Add one record (and its measure value) to an aggregate. */
    private static function add(&$aggregate, ?float $value): void
    {
        $aggregate ??= self::emptyAggregate();
        $aggregate['n']++;
        if($value === null){ return; }
        $aggregate['m']++;
        $aggregate['sum'] += $value;
        if($aggregate['min'] === null || $value < $aggregate['min']){ $aggregate['min'] = $value; }
        if($aggregate['max'] === null || $value > $aggregate['max']){ $aggregate['max'] = $value; }
    }

    /** A cell / total row: the keys, the number of records, and for a measure its value and records with a value. */
    private static function row(array $keys, array $aggregate, string $op): array
    {
        $keys[] = $aggregate['n'];
        if($op === 'count'){ return $keys; }
        $value = null;
        if($aggregate['m'] > 0){
            switch($op){
                case 'sum': $value = $aggregate['sum']; break;
                case 'avg': $value = $aggregate['sum'] / $aggregate['m']; break;
                case 'min': $value = $aggregate['min']; break;
                case 'max': $value = $aggregate['max']; break;
            }
        }
        $keys[] = $value === null ? null : FieldValueRange::number(round($value, 10));
        $keys[] = $aggregate['m'];
        return $keys;
    }
}
