<?php
/**
* FieldValueBuckets.php - Counts of records per range of a numeric or date field
*
* Serves `detail=ranges`: the values of one field over the records a query
* finds, grouped into ranges, each with the number of distinct records.
* It is the data behind date/number facets presented as a list in filter forms.
*
* - Dates: `groupby=month|year|decade|century` (detail dates from
*   recDetailsDateIndex, header dates added/modified directly). Year-based
*   ranges start at a multiple of the step (decade 1990-1999, century 1900-1999).
* - Numbers: `ranges=N` (1-20): equal ranges with round boundaries between the
*   field's smallest and largest value; integer fields get integer boundaries.
*
* Each range carries `from`/`to` in the form record search accepts, and its
* count follows the search's own comparison, so selecting a range finds exactly
* `count` records: a detail date counts in every range its span overlaps
* (`match=overlap`, the default: operators <> and "falls in") or only in the
* range that contains its whole span (`match=within`: operator ><). Bounds of a
* detail date range are parsed with Temporal exactly as the search parses them.
*
* Ranges are listed from the smallest to the largest value, empty ranges left
* out. When the requested grouping would give more than MAX_BUCKETS ranges
* (e.g. months across millennia, prehistoric years), only the ranges that hold
* the start or end of some value are considered, and at most MAX_BUCKETS are
* returned (`truncated`).
*
* @project     Heurist academic knowledge management system
* @package     Records\Search
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

namespace Heurist\Records\Query;

use Heurist\Records\Query\Compiler\QueryBuilder;
use Heurist\Records\Query\Compiler\SqlBuildContext;
use Heurist\Utilities\Temporal;

/** Groups the values of one numeric or date field into ranges with record counts. */
final class FieldValueBuckets
{
    public const MAX_BUCKETS = 1000;
    public const MAX_RANGES = 20;
    public const DEFAULT_RANGES = 10;
    private const DATE_STEPS = array('month'=>0, 'year'=>1, 'decade'=>10, 'century'=>100);

    /** @var QueryBuilder */
    private $builder;
    /** @var QueryExecutor */
    private $executor;
    /** @var FieldValueRange */
    private $range;

    public function __construct(QueryBuilder $builder, QueryExecutor $executor)
    {
        $this->builder = $builder;
        $this->executor = $executor;
        $this->range = new FieldValueRange($builder, $executor);
    }

    /**
     * Validate the field and grouping.
     *
     * @param mixed $field Detail type ID or added/modified.
     * @param array $options groupby (dates), ranges (numbers), match (overlap|within).
     * @return array describeField() result plus groupby, ranges, match.
     */
    public function describe($field, array $options): array
    {
        $described = $this->range->describeField($field, 'ranges');
        $match = strtolower(trim((string)($options['match'] ?? 'overlap')));
        if($match !== 'overlap' && $match !== 'within'){
            throw new QueryValidationException('match must be overlap or within');
        }
        $described['match'] = $match;
        if($described['type'] === 'date'){
            $groupBy = strtolower(trim((string)($options['groupby'] ?? 'year')));
            if(!array_key_exists($groupBy, self::DATE_STEPS)){
                throw new QueryValidationException('groupby must be month, year, decade or century');
            }
            $described['groupby'] = $groupBy;
        }else{
            $ranges = trim((string)($options['ranges'] ?? ''));
            $count = $ranges === '' ? self::DEFAULT_RANGES : intval($ranges);
            if(($ranges !== '' && !ctype_digit($ranges)) || $count < 1 || $count > self::MAX_RANGES){
                throw new QueryValidationException('ranges must be a whole number from 1 to '.self::MAX_RANGES);
            }
            $described['ranges'] = $count;
        }
        return $described;
    }

    /**
     * Ranges over an SQL-compilable query.
     *
     * @param array $query Normalized query.
     * @param array $context Search context.
     * @param array $field describe() result.
     * @return array{total:int,truncated:bool,buckets:array}
     */
    public function bucketsForQuery(array $query, array $context, array $field): array
    {
        return $this->buckets(array('query'=>$query), $context, $field);
    }

    /**
     * Ranges over an already evaluated ID list (fallback path).
     *
     * @param int[] $ids Matching record IDs.
     * @return array{total:int,truncated:bool,buckets:array}
     */
    public function bucketsForIds(array $ids, array $context, array $field): array
    {
        return $this->buckets(array('ids'=>FieldValueRange::uniqueIds($ids)), $context, $field);
    }

    /** Bounds, range list, then counts. */
    private function buckets(array $source, array $context, array $field): array
    {
        [$min, $max, $total] = isset($source['query'])
            ? $this->range->rawForQuery($source['query'], $context, $field)
            : $this->range->rawForIds($source['ids'], $context, $field);
        if($total === 0 || $min === null || $max === null){
            return array('total'=>0, 'truncated'=>false, 'buckets'=>array());
        }

        $truncated = false;
        if($field['type'] === 'number'){
            $specs = $this->numberSpecs($field, (float)$min, (float)$max);
        }else{
            $keys = $this->denseDateKeys($field, $min, $max);
            if($keys === null){
                $keys = $this->endpointDateKeys($source, $context, $field);
                $truncated = count($keys) > self::MAX_BUCKETS;
                $keys = array_slice($keys, 0, self::MAX_BUCKETS);
            }
            $specs = array_map(function($key) use ($field){ return $this->dateSpec($field, $key); }, $keys);
        }

        $counts = $this->count($source, $context, $field, $specs);
        $buckets = array();
        foreach($specs as $index=>$spec){
            if(empty($counts[$index])){ continue; }
            $buckets[] = array('from'=>$spec['from'], 'to'=>$spec['to'], 'label'=>$spec['label'],
                'count'=>$counts[$index]);
        }
        return array('total'=>$total, 'truncated'=>$truncated, 'buckets'=>$buckets);
    }

    /**
     * Number of distinct records per range: the ranges are a derived table
     * joined on the search's comparison.
     *
     * @return array<int,int> Count by range index.
     */
    private function count(array $source, array $context, array $field, array $specs): array
    {
        $counts = array();
        if(empty($specs)){ return $counts; }
        $chunks = isset($source['query']) ? array(null) : array_chunk($source['ids'], FieldValueRange::ID_CHUNK_SIZE);
        foreach($chunks as $chunk){
            $state = new SqlBuildContext($context);
            $parts = $this->range->sourceParts($field, $state);
            $type = $field['dtyId'] === 0 ? 's' : 'd';
            $rows = array();
            foreach($specs as $index=>$spec){
                $state->bind($index, 'i'); $state->bind($spec['lo'], $type); $state->bind($spec['hi'], $type);
                $rows[] = $index === 0 ? 'SELECT ? AS k, ? AS lo, ? AS hi' : 'SELECT ?, ?, ?';
            }
            $where = $parts['where'];
            $where = array_merge($where, $chunk === null
                ? $this->builder->compileConditions($source['query'], $state, $context)
                : array(FieldValueRange::idCondition($chunk, $state)));
            $result = $this->executor->executeRows(
                'SELECT b.k, COUNT(DISTINCT r.rec_ID) FROM Records r '.$parts['joins']
                    .' JOIN ('.implode(' UNION ALL ', $rows).') b ON '.$this->matchCondition($field)
                    .' WHERE '.implode(' AND ', $where).' GROUP BY b.k',
                $state->types(), $state->values()
            );
            // chunks partition the records, so counts per range add up
            foreach($result as $row){ $counts[intval($row[0])] = ($counts[intval($row[0])] ?? 0) + intval($row[1]); }
        }
        return $counts;
    }

    /** The search's comparison of one value with a range `b.lo`..`b.hi`. */
    private function matchCondition(array $field): string
    {
        if($field['dtyId'] === 0){
            $column = $field['field'] === 'added' ? 'r.rec_Added' : 'r.rec_Modified';
            return $column.' BETWEEN b.lo AND b.hi';
        }
        if($field['type'] === 'number'){
            return FieldValueRange::NUMERIC_VALUE.' BETWEEN b.lo AND b.hi';
        }
        return $field['match'] === 'within'
            ? 'b.lo <= vdi.rdi_estMinDate AND vdi.rdi_estMaxDate <= b.hi'
            : 'vdi.rdi_estMaxDate >= b.lo AND vdi.rdi_estMinDate <= b.hi';
    }

    /**
     * Every range key between the bounds, or null when there are too many.
     * Keys: the first year of a year/decade/century, or year*12+month-1.
     *
     * @return int[]|null
     */
    private function denseDateKeys(array $field, $min, $max): ?array
    {
        $first = $this->dateKey($field, $min, false);
        $last = $this->dateKey($field, $max, true);
        $step = max(1, self::DATE_STEPS[$field['groupby']]);
        if(($last - $first) / $step + 1 > self::MAX_BUCKETS){ return null; }
        return range($first, $last, $step);
    }

    /**
     * Keys of the ranges holding the start or end of some value (sparse
     * grouping), ascending. At most MAX_BUCKETS + 1, to report truncation.
     *
     * @return int[]
     */
    private function endpointDateKeys(array $source, array $context, array $field): array
    {
        $keys = array();
        $chunks = isset($source['query']) ? array(null) : array_chunk($source['ids'], FieldValueRange::ID_CHUNK_SIZE);
        foreach($chunks as $chunk){
            $state = new SqlBuildContext($context);
            $parts = $this->range->sourceParts($field, $state);
            $where = array_merge($parts['where'], $chunk === null
                ? $this->builder->compileConditions($source['query'], $state, $context)
                : array(FieldValueRange::idCondition($chunk, $state)));
            $rows = $this->executor->executeRows(
                'SELECT DISTINCT '.$parts['min'].', '.$parts['max'].' FROM Records r '.$parts['joins']
                    .' WHERE '.implode(' AND ', $where).' LIMIT 100000',
                $state->types(), $state->values()
            );
            foreach($rows as $row){
                $keys[$this->dateKey($field, $row[0], false)] = true;
                $keys[$this->dateKey($field, $row[1], true)] = true;
            }
        }
        $keys = array_map('intval', array_keys($keys));
        sort($keys);
        return array_slice($keys, 0, self::MAX_BUCKETS + 1);
    }

    /** Range key of one bound (index decimal or header datetime). */
    private function dateKey(array $field, $value, bool $upper): int
    {
        if($field['dtyId'] === 0){
            $year = intval(substr((string)$value, 0, 4));
            $month = intval(substr((string)$value, 5, 2));
        }else{
            [$year, $month] = Temporal::decimalParts($value);
            if($month < 1 || $month > 12){ $month = $upper ? 12 : 1; }
        }
        $step = self::DATE_STEPS[$field['groupby']];
        if($step === 0){ return $year * 12 + $month - 1; }
        return $year - self::floorMod($year, $step);
    }

    /** @return int Non-negative remainder (so -505 falls in the decade -510). */
    private static function floorMod(int $value, int $step): int
    {
        return (($value % $step) + $step) % $step;
    }

    /**
     * One date range: query values, label and the SQL bounds the search would
     * compare with (Temporal for detail dates, datetimes for header dates).
     */
    private function dateSpec(array $field, int $key): array
    {
        $step = self::DATE_STEPS[$field['groupby']];
        if($step === 0){
            $year = intdiv($key - self::floorMod($key, 12), 12);
            $month = self::floorMod($key, 12) + 1;
            $from = FieldValueRange::isoDate($year, $month, 1);
            $to = FieldValueRange::isoDate($year, $month, Temporal::daysInMonth($year, $month));
            $label = substr($from, 0, -3);
        }else{
            $end = $key + $step - 1;
            // detail dates keep year precision, so year-only values fall inside
            $from = $field['dtyId'] === 0 ? FieldValueRange::isoDate($key, 1, 1) : (string)$key;
            $to = $field['dtyId'] === 0 ? FieldValueRange::isoDate($end, 12, 31) : (string)$end;
            $label = $step === 1 ? (string)$key : $key.'–'.$end;
        }
        if($field['dtyId'] === 0){
            return array('from'=>$from, 'to'=>$to, 'label'=>$label, 'lo'=>$from.' 00:00:00', 'hi'=>$to.' 23:59:59');
        }
        $temporal = new Temporal($from.'/'.$to, true);
        $bounds = $temporal->isValid() ? $temporal->getMinMax() : array(null, null);
        if($bounds[0] === null || $bounds[1] === null){
            throw new QueryValidationException('Range '.$from.'/'.$to.' cannot be searched');
        }
        return array('from'=>$from, 'to'=>$to, 'label'=>$label, 'lo'=>(float)$bounds[0], 'hi'=>(float)$bounds[1]);
    }

    /**
     * Equal numeric ranges with round boundaries (1, 2, 2.5, 5 × 10^n), at most
     * `ranges` of them. Integer fields: whole steps, ranges that do not share
     * their end values.
     */
    private function numberSpecs(array $field, float $min, float $max): array
    {
        $integer = $field['dtyType'] !== 'float';
        if($max <= $min){
            $value = self::formatNumber($min);
            return array(array('from'=>$value, 'to'=>$value, 'label'=>(string)$value, 'lo'=>$min, 'hi'=>$min));
        }
        $wanted = $field['ranges'];
        $step = $this->niceStep(($max - $min) / $wanted, $integer);
        while(true){
            $start = floor($min / $step) * $step;
            $count = $integer ? intval(floor(($max - $start) / $step)) + 1 : max(1, intval(ceil(($max - $start) / $step - 1e-9)));
            if($count <= $wanted){ break; }
            $step = $this->niceStep($step * 1.000001, $integer);
        }
        $specs = array();
        for($index = 0; $index < $count; $index++){
            $lo = $start + $index * $step;
            $hi = $integer ? $lo + $step - 1 : $lo + $step;
            $from = self::formatNumber($lo); $to = self::formatNumber($hi);
            $specs[] = array('from'=>$from, 'to'=>$to, 'label'=>$from === $to ? (string)$from : $from.' – '.$to,
                'lo'=>(float)$from, 'hi'=>(float)$to);
        }
        return $specs;
    }

    /** Smallest round step (1, 2, 2.5, 5 × 10^n) not below `raw`; whole steps for integers. */
    private function niceStep(float $raw, bool $integer): float
    {
        if($integer && $raw <= 1){ return 1; }
        $magnitude = pow(10, floor(log10($raw)));
        foreach(array(1, 2, 2.5, 5, 10) as $factor){
            $step = $factor * $magnitude;
            if($integer && floor($step) != $step){ continue; }
            if($step >= $raw - 1e-12){ return $step; }
        }
        return 10 * $magnitude;
    }

    /** @return int|float A bound without floating-point noise. */
    private static function formatNumber(float $value)
    {
        return FieldValueRange::number(round($value, 10));
    }
}
