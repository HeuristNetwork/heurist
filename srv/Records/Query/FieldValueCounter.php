<?php
/**
* FieldValueCounter.php - Unique values of one field over a record query
*
* Serves `detail=values`: the distinct values a detail field (or header
* property) has among the records a query finds, each with the number of
* distinct records carrying it. It is the data behind value pickers and facet
* counts. Values are grouped in SQL over the compiled query; queries that need
* the ordered set fallback are grouped over their ID list in chunks.
*
* Accepted fields: a detail type ID (freetext, enum, relationtype, integer,
* float, year, date, boolean, resource, relmarker) or one of the header
* keywords rectype, owner, addedby, access, tag.
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

/** Groups the values of one field over the records of a query. */
final class FieldValueCounter
{
    public const MAX_LIMIT = 1000;
    private const ID_CHUNK_SIZE = 5000;
    private const HEADER_FIELDS = array('rectype','owner','addedby','access','tag');
    private const DETAIL_TYPES = array(
        'freetext','enum','relationtype','integer','float','year','date','boolean','resource','relmarker'
    );

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
     * @return array{field:string,type:string,dtyId:int,targets:int[]}
     */
    public function describeField($field): array
    {
        $name = strtolower(trim((string)$field));
        if($name === ''){
            throw new QueryValidationException('detail=values requires a field parameter');
        }
        if(in_array($name, self::HEADER_FIELDS, true)){
            return array('field'=>$name, 'type'=>$name, 'dtyId'=>0, 'targets'=>array());
        }
        if(!ctype_digit($name) || intval($name) < 1){
            throw new QueryValidationException('Unsupported field for detail=values: '.$field);
        }
        $rows = $this->executor->executeRows(
            'SELECT dty_Type, dty_PtrTargetRectypeIDs FROM defDetailTypes WHERE dty_ID=?',
            'i', array(intval($name))
        );
        if(empty($rows)){
            throw new QueryValidationException('Unknown field for detail=values: '.$field);
        }
        $type = (string)$rows[0][0];
        if(!in_array($type, self::DETAIL_TYPES, true)){
            throw new QueryValidationException('Field type '.$type.' is not supported by detail=values');
        }
        $targets = array_values(array_filter(array_map('intval',
            preg_split('/\s*,\s*/', trim((string)$rows[0][1])) ?: array())));
        return array('field'=>$name, 'type'=>$type, 'dtyId'=>intval($name), 'targets'=>$targets);
    }

    /**
     * Count values over an SQL-compilable query.
     *
     * @param array $query Normalized query.
     * @param array $context Search context (userId, groupIds, isDbOwner).
     * @param array $field describeField() result.
     * @param array $options text, limit, offset, sort (count|value).
     * @return array{total:int,values:array}
     */
    public function countForQuery(array $query, array $context, array $field, array $options): array
    {
        if(!$this->hasVisibleSource($field, $context)){ return array('total'=>0, 'values'=>array()); }
        $options = $this->normalizeOptions($options);

        $state = new SqlBuildContext($context);
        $parts = $this->sourceParts($field, $state, $context);
        $where = array_merge($parts['where'], $this->detailVisibility($field, $state),
            $this->builder->compileConditions($query, $state, $context));
        $where = array_merge($where, $this->targetConditions($field, $state, $context));
        $this->appendText($where, $parts, $state, $options['text']);
        $from = 'FROM Records r '.$parts['joins'].' WHERE '.implode(' AND ', $where);
        $state->bind($options['limit'], 'i');
        $state->bind($options['offset'], 'i');
        $rows = $this->executor->executeRows(
            'SELECT '.$parts['value'].' AS v, COUNT(DISTINCT r.rec_ID) AS c, MAX('.$parts['label'].'),'
                .' MAX('.$parts['rty'].'), MAX('.$parts['kind'].') '.$from
                .' GROUP BY v '.$this->orderBy($options['sort'], $parts['label']).' LIMIT ? OFFSET ?',
            $state->types(), $state->values()
        );

        $countState = new SqlBuildContext($context);
        $countParts = $this->sourceParts($field, $countState, $context);
        $countWhere = array_merge($countParts['where'], $this->detailVisibility($field, $countState),
            $this->builder->compileConditions($query, $countState, $context));
        $countWhere = array_merge($countWhere, $this->targetConditions($field, $countState, $context));
        $this->appendText($countWhere, $countParts, $countState, $options['text']);
        $total = intval($this->executor->executeScalar(new CompiledQuery(
            'SELECT COUNT(DISTINCT '.$countParts['value'].') FROM Records r '.$countParts['joins']
                .' WHERE '.implode(' AND ', $countWhere),
            $countState->types(), $countState->values(), $query
        )));
        return array('total'=>$total, 'values'=>array_map(array($this, 'valueRow'), $rows));
    }

    /**
     * Count values over an already evaluated ID list (fallback path).
     *
     * @param int[] $ids Matching record IDs.
     * @return array{total:int,values:array}
     */
    public function countForIds(array $ids, array $context, array $field, array $options): array
    {
        if(!$this->hasVisibleSource($field, $context)){ return array('total'=>0, 'values'=>array()); }
        $options = $this->normalizeOptions($options);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $merged = array();
        foreach(array_chunk($ids, self::ID_CHUNK_SIZE) as $chunk){
            $state = new SqlBuildContext($context);
            $parts = $this->sourceParts($field, $state, $context);
            $where = array_merge($parts['where'], $this->detailVisibility($field, $state));
            foreach($chunk as $id){ $state->bind($id, 'i'); }
            $where[] = 'r.rec_ID IN ('.implode(',', array_fill(0, count($chunk), '?')).')';
            $where = array_merge($where, $this->targetConditions($field, $state, $context));
            $this->appendText($where, $parts, $state, $options['text']);
            $rows = $this->executor->executeRows(
                'SELECT '.$parts['value'].' AS v, COUNT(DISTINCT r.rec_ID), MAX('.$parts['label'].'),'
                    .' MAX('.$parts['rty'].'), MAX('.$parts['kind'].') FROM Records r '.$parts['joins']
                    .' WHERE '.implode(' AND ', $where).' GROUP BY v',
                $state->types(), $state->values()
            );
            // chunks partition the records, so per-value counts add up
            foreach($rows as $row){
                $key = (string)$row[0];
                if(isset($merged[$key])){ $merged[$key][1] += intval($row[1]); }
                else{ $merged[$key] = array($row[0], intval($row[1]), $row[2], $row[3], $row[4]); }
            }
        }
        $values = array_map(array($this, 'valueRow'), array_values($merged));
        usort($values, $this->comparator($options['sort']));
        return array(
            'total'=>count($values),
            'values'=>array_slice($values, $options['offset'], $options['limit'])
        );
    }

    /** Tags need a logged-in user; every other field is visible to guests too. */
    private function hasVisibleSource(array $field, array $context): bool
    {
        return $field['type'] !== 'tag' || intval($context['userId'] ?? 0) > 0;
    }

    /** @return array{text:string,limit:int,offset:int,sort:string} */
    private function normalizeOptions(array $options): array
    {
        $sort = strtolower(trim((string)($options['sort'] ?? 'count')));
        return array(
            'text'=>trim((string)($options['text'] ?? '')),
            'limit'=>min(self::MAX_LIMIT, max(1, intval($options['limit'] ?? self::MAX_LIMIT))),
            'offset'=>max(0, intval($options['offset'] ?? 0)),
            'sort'=>$sort === 'value' ? 'value' : 'count'
        );
    }

    /**
     * SQL fragments for one field: JOINs, extra conditions and the value,
     * label, record type and kind expressions. JOIN parameters are bound first.
     */
    private function sourceParts(array $field, SqlBuildContext $state, array $context): array
    {
        $parts = array('joins'=>'', 'where'=>array(), 'label'=>'NULL', 'rty'=>'NULL', 'kind'=>'NULL', 'textOn'=>null);
        switch($field['type']){
            case 'rectype':
                return array_merge($parts, array(
                    'value'=>'r.rec_RecTypeID',
                    'joins'=>'JOIN defRecTypes vrt ON vrt.rty_ID=r.rec_RecTypeID',
                    'label'=>'vrt.rty_Name', 'textOn'=>'vrt.rty_Name'
                ));
            case 'owner':
            case 'addedby':
                $column = $field['type'] === 'owner' ? 'r.rec_OwnerUGrpID' : 'r.rec_AddedByUGrpID';
                return array_merge($parts, array(
                    'value'=>$column,
                    'joins'=>'LEFT JOIN sysUGrps vug ON vug.ugr_ID='.$column,
                    'label'=>'vug.ugr_Name',
                    'kind'=>'IF(vug.ugr_Type="user","user","group")',
                    'textOn'=>'vug.ugr_Name'
                ));
            case 'access':
                return array_merge($parts, array(
                    'value'=>'r.rec_NonOwnerVisibility', 'textOn'=>'r.rec_NonOwnerVisibility'
                ));
            case 'tag':
                $owners = array_values(array_unique(array_filter(array_merge(
                    array(intval($context['userId'] ?? 0)),
                    array_map('intval', (array)($context['groupIds'] ?? array()))
                ), static function($id){ return $id > 0; })));
                foreach($owners as $id){ $state->bind($id, 'i'); }
                return array_merge($parts, array(
                    'value'=>'vtg.tag_ID',
                    'joins'=>'JOIN usrRecTagLinks vrtl ON vrtl.rtl_RecID=r.rec_ID'
                        .' JOIN usrTags vtg ON vtg.tag_ID=vrtl.rtl_TagID AND vtg.tag_UGrpID IN ('
                        .implode(',', array_fill(0, count($owners), '?')).')',
                    'label'=>'vtg.tag_Text', 'textOn'=>'vtg.tag_Text'
                ));
            case 'resource':
                $state->bind($field['dtyId'], 'i');
                return array_merge($parts, array(
                    'value'=>'vt.rec_ID',
                    'joins'=>'JOIN recDetails vd ON vd.dtl_RecID=r.rec_ID AND vd.dtl_DetailTypeID=?'
                        .' JOIN Records vt ON vt.rec_ID=vd.dtl_Value',
                    'label'=>'vt.rec_Title', 'rty'=>'vt.rec_RecTypeID', 'textOn'=>'vt.rec_Title'
                ));
            case 'relmarker':
                // related records in either direction of a relationship
                return array_merge($parts, array(
                    'value'=>'vt.rec_ID',
                    'joins'=>'JOIN recLinks vl ON vl.rl_RelationID IS NOT NULL'
                        .' AND (vl.rl_SourceID=r.rec_ID OR vl.rl_TargetID=r.rec_ID)'
                        .' JOIN Records vt ON vt.rec_ID=IF(vl.rl_SourceID=r.rec_ID,vl.rl_TargetID,vl.rl_SourceID)',
                    'label'=>'vt.rec_Title', 'rty'=>'vt.rec_RecTypeID', 'textOn'=>'vt.rec_Title'
                ));
            case 'enum':
            case 'relationtype':
                $state->bind($field['dtyId'], 'i');
                return array_merge($parts, array(
                    'value'=>'vd.dtl_Value',
                    'joins'=>'JOIN recDetails vd ON vd.dtl_RecID=r.rec_ID AND vd.dtl_DetailTypeID=?'
                        .' LEFT JOIN defTerms vtr ON vtr.trm_ID=vd.dtl_Value',
                    // labels stay client-side (UI language); the join only serves text filtering
                    'textOn'=>'vtr.trm_Label'
                ));
            default:
                $state->bind($field['dtyId'], 'i');
                return array_merge($parts, array(
                    'value'=>'vd.dtl_Value',
                    'joins'=>'JOIN recDetails vd ON vd.dtl_RecID=r.rec_ID AND vd.dtl_DetailTypeID=?',
                    'textOn'=>'vd.dtl_Value'
                ));
        }
    }

    /** Hidden detail values stay hidden (the rule of every detail predicate); header fields have none. */
    private function detailVisibility(array $field, SqlBuildContext $state): array
    {
        if($field['dtyId'] < 1 || $field['type'] === 'relmarker'){ return array(); }
        $condition = $this->builder->detailVisibilityCondition($state, 'vd', 'r');
        return $condition === '' ? array() : array($condition);
    }

    /** Access (and target type) conditions for the linked record of resource/relmarker fields. */
    private function targetConditions(array $field, SqlBuildContext $state, array $context): array
    {
        if($field['type'] !== 'resource' && $field['type'] !== 'relmarker'){ return array(); }
        $where = $this->builder->accessConditions($state, $context, 'vt');
        if($field['type'] === 'relmarker' && !empty($field['targets'])){
            foreach($field['targets'] as $id){ $state->bind($id, 'i'); }
            $where[] = 'vt.rec_RecTypeID IN ('.implode(',', array_fill(0, count($field['targets']), '?')).')';
        }
        return $where;
    }

    /** Add a case-insensitive substring filter on the field's text. */
    private function appendText(array &$where, array $parts, SqlBuildContext $state, string $text): void
    {
        if($text === '' || $parts['textOn'] === null){ return; }
        $state->bind('%'.addcslashes($text, '%_\\').'%', 's');
        $where[] = $parts['textOn'].' LIKE ?';
    }

    /** Order by count (then value), or by label falling back to the value. */
    private function orderBy(string $sort, string $label): string
    {
        return $sort === 'value'
            ? 'ORDER BY COALESCE(MAX('.$label.'), v) ASC'
            : 'ORDER BY c DESC, v ASC';
    }

    /** Comparator matching orderBy() for merged fallback rows. */
    private function comparator(string $sort): callable
    {
        return static function(array $a, array $b) use ($sort): int {
            $labelA = (string)($a['label'] ?? $a['value']);
            $labelB = (string)($b['label'] ?? $b['value']);
            if($sort === 'count' && $a['count'] !== $b['count']){ return $b['count'] <=> $a['count']; }
            return strnatcasecmp($labelA, $labelB);
        };
    }

    /** Public row shape: value, count, and label/rty/kind when known. */
    private function valueRow(array $row): array
    {
        $value = $row[0];
        $result = array(
            'value'=>is_numeric($value) && ctype_digit((string)$value) ? intval($value) : (string)$value,
            'count'=>intval($row[1])
        );
        if($row[2] !== null && $row[2] !== ''){ $result['label'] = (string)$row[2]; }
        if($row[3] !== null && $row[3] !== ''){ $result['rty'] = intval($row[3]); }
        if($row[4] !== null && $row[4] !== ''){ $result['kind'] = (string)$row[4]; }
        return $result;
    }
}
