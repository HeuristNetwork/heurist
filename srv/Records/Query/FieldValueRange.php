<?php
/**
* FieldValueRange.php - Smallest and largest value of one field over a record query
*
* Serves `detail=minmax`: the bounds a numeric or date field takes among the
* records a query finds, and the number of those records that have a value.
* It is the data behind "auto" slider bounds in filter forms, and the first
* step of `detail=ranges` (FieldValueBuckets). Bounds are computed in SQL over
* the compiled query; queries that need the ordered set fallback are evaluated
* over their ID list in chunks.
*
* Accepted fields: a detail type ID (integer, float, year, date) or one of the
* header keywords added, modified. Numeric bounds are numbers; date bounds are
* ISO dates (YYYY-MM-DD, years before 1 as -YYYY-MM-DD; Temporal::decimalToYMD) taken from the
* recDetailsDateIndex estimates, so fuzzy and ranged dates count by their span.
* Values compare as record search does: numbers as DECIMAL(65,20), detail dates
* through the date index, header dates directly; hidden detail values are
* left out for users who may not see them.
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

/** Finds the bounds of one numeric or date field over the records of a query. */
final class FieldValueRange
{
    public const ID_CHUNK_SIZE = 5000;
    private const HEADER_FIELDS = array('added','modified');
    private const NUMERIC_TYPES = array('integer','float','year');
    /** A detail value that is a plain number (leading sign, optional fraction and exponent). */
    private const NUMERIC_PATTERN = '^[[:space:]]*[-+]?[0-9]+([.][0-9]+)?([eE][-+]?[0-9]+)?[[:space:]]*$';
    /** Numeric detail values compare like the record search's numeric predicates. */
    public const NUMERIC_VALUE = 'CAST(vd.dtl_Value AS DECIMAL(65,20))';

    /** @var QueryBuilder */
    private $builder;
    /** @var QueryExecutor */
    private $executor;

    public function __construct(QueryBuilder $builder, QueryExecutor $executor)
    {
        $this->builder = $builder;
        $this->executor = $executor;
    }

    /**
     * Validate and describe the requested field.
     *
     * @param mixed $field Detail type ID or header keyword.
     * @param string $detail Request mode named in errors.
     * @return array{field:string,type:string,dtyType:string,dtyId:int}
     */
    public function describeField($field, string $detail = 'minmax'): array
    {
        $name = strtolower(trim((string)$field));
        if($name === ''){
            throw new QueryValidationException('detail='.$detail.' requires a field parameter');
        }
        if(in_array($name, self::HEADER_FIELDS, true)){
            return array('field'=>$name, 'type'=>'date', 'dtyType'=>'header', 'dtyId'=>0);
        }
        if(!ctype_digit($name) || intval($name) < 1){
            throw new QueryValidationException('Unsupported field for detail='.$detail.': '.$field);
        }
        $rows = $this->executor->executeRows(
            'SELECT dty_Type FROM defDetailTypes WHERE dty_ID=?', 'i', array(intval($name))
        );
        if(empty($rows)){
            throw new QueryValidationException('Unknown field for detail='.$detail.': '.$field);
        }
        $type = (string)$rows[0][0];
        if($type !== 'date' && !in_array($type, self::NUMERIC_TYPES, true)){
            throw new QueryValidationException('Field type '.$type.' is not supported by detail='.$detail);
        }
        return array('field'=>$name, 'type'=>$type === 'date' ? 'date' : 'number', 'dtyType'=>$type,
            'dtyId'=>intval($name));
    }

    /**
     * Bounds over an SQL-compilable query.
     *
     * @param array $query Normalized query.
     * @param array $context Search context (userId, groupIds, isDbOwner).
     * @param array $field describeField() result.
     * @return array{min:mixed,max:mixed,count:int}
     */
    public function rangeForQuery(array $query, array $context, array $field): array
    {
        return $this->result($field, $this->rawForQuery($query, $context, $field));
    }

    /**
     * Bounds over an already evaluated ID list (fallback path).
     *
     * @param int[] $ids Matching record IDs.
     * @return array{min:mixed,max:mixed,count:int}
     */
    public function rangeForIds(array $ids, array $context, array $field): array
    {
        return $this->result($field, $this->rawForIds($ids, $context, $field));
    }

    /**
     * Bounds in the field's SQL representation: numbers, date index decimals
     * (YYYY.MMDD) or header datetimes.
     *
     * @return array{0:mixed,1:mixed,2:int} min, max, number of records with a value.
     */
    public function rawForQuery(array $query, array $context, array $field): array
    {
        $state = new SqlBuildContext($context);
        $parts = $this->sourceParts($field, $state);
        $where = array_merge($parts['where'], $this->builder->compileConditions($query, $state, $context));
        $rows = $this->executor->executeRows(
            'SELECT MIN('.$parts['min'].'), MAX('.$parts['max'].'), COUNT(DISTINCT r.rec_ID)'
                .' FROM Records r '.$parts['joins'].' WHERE '.implode(' AND ', $where),
            $state->types(), $state->values()
        );
        $row = $rows[0] ?? array(null, null, 0);
        return array($row[0], $row[1], intval($row[2]));
    }

    /** rawForQuery() over an ID list, in chunks. */
    public function rawForIds(array $ids, array $context, array $field): array
    {
        $min = null; $max = null; $count = 0;
        foreach(array_chunk(self::uniqueIds($ids), self::ID_CHUNK_SIZE) as $chunk){
            $state = new SqlBuildContext($context);
            $parts = $this->sourceParts($field, $state);
            $where = array_merge($parts['where'], array(self::idCondition($chunk, $state)));
            $rows = $this->executor->executeRows(
                'SELECT MIN('.$parts['min'].'), MAX('.$parts['max'].'), COUNT(DISTINCT r.rec_ID)'
                    .' FROM Records r '.$parts['joins'].' WHERE '.implode(' AND ', $where),
                $state->types(), $state->values()
            );
            $row = $rows[0] ?? array(null, null, 0);
            // chunks partition the records: counts add up, bounds combine
            if($row[0] !== null && ($min === null || $this->less($field, $row[0], $min))){ $min = $row[0]; }
            if($row[1] !== null && ($max === null || $this->less($field, $max, $row[1]))){ $max = $row[1]; }
            $count += intval($row[2]);
        }
        return array($min, $max, $count);
    }

    /**
     * SQL fragments for one field: JOINs (parameters bound first), conditions,
     * the lower/upper bound expressions of one value, and `value` for a point value.
     *
     * @return array{joins:string,where:string[],min:string,max:string}
     */
    public function sourceParts(array $field, SqlBuildContext $state): array
    {
        if($field['dtyId'] === 0){
            $column = $field['field'] === 'added' ? 'r.rec_Added' : 'r.rec_Modified';
            return array('joins'=>'', 'where'=>array($column.' IS NOT NULL'), 'min'=>$column, 'max'=>$column);
        }
        $state->bind($field['dtyId'], 'i');
        $visibility = $this->builder->detailVisibilityCondition($state, 'vd', 'r');
        $where = $visibility === '' ? array() : array($visibility);
        if($field['type'] === 'date'){
            // estimated bounds of each (possibly fuzzy or ranged) date, as decimal YYYY.MMDD
            return array(
                'joins'=>'JOIN recDetailsDateIndex vdi ON vdi.rdi_RecID=r.rec_ID AND vdi.rdi_DetailTypeID=?'
                    .' JOIN recDetails vd ON vd.dtl_ID=vdi.rdi_DetailID',
                'where'=>$where,
                'min'=>'vdi.rdi_estMinDate', 'max'=>'vdi.rdi_estMaxDate'
            );
        }
        $where[] = "vd.dtl_Value REGEXP '".self::NUMERIC_PATTERN."'";
        return array(
            'joins'=>'JOIN recDetails vd ON vd.dtl_RecID=r.rec_ID AND vd.dtl_DetailTypeID=?',
            'where'=>$where,
            'min'=>self::NUMERIC_VALUE, 'max'=>self::NUMERIC_VALUE
        );
    }

    /** @return int[] Distinct positive IDs. */
    public static function uniqueIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    /** `r.rec_ID IN (...)` for one chunk, binding the IDs. */
    public static function idCondition(array $chunk, SqlBuildContext $state): string
    {
        foreach($chunk as $id){ $state->bind($id, 'i'); }
        return 'r.rec_ID IN ('.implode(',', array_fill(0, count($chunk), '?')).')';
    }

    /** Whether bound `$a` is smaller than `$b` in the field's own SQL representation. */
    private function less(array $field, $a, $b): bool
    {
        return $field['dtyId'] === 0 ? strcmp((string)$a, (string)$b) < 0 : (float)$a < (float)$b;
    }

    /** Public shape: bounds in the field's value type (null without values). */
    private function result(array $field, array $raw): array
    {
        [$min, $max, $count] = $raw;
        if($count === 0 || $min === null || $max === null){
            return array('min'=>null, 'max'=>null, 'count'=>0);
        }
        if($field['dtyId'] === 0){
            return array('min'=>substr((string)$min, 0, 10), 'max'=>substr((string)$max, 0, 10), 'count'=>$count);
        }
        if($field['type'] === 'date'){
            return array('min'=>Temporal::decimalToYMD($min, false), 'max'=>Temporal::decimalToYMD($max, true), 'count'=>$count);
        }
        return array('min'=>self::number($min), 'max'=>self::number($max), 'count'=>$count);
    }

    /** @return int|float A whole number as int, otherwise a float. */
    public static function number($value)
    {
        $number = (float)$value;
        return floor($number) === $number && abs($number) < PHP_INT_MAX ? intval($number) : $number;
    }

    /** @return string YYYY-MM-DD, negative years as -YYYY-MM-DD. */
    public static function isoDate(int $year, int $month, int $day): string
    {
        return ($year < 0 ? '-' : '').sprintf('%04d-%02d-%02d', abs($year), $month, $day);
    }

}
