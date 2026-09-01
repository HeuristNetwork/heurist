<?php
/**
* SystemQueryBuilder.php - Mapped system-record query compiler
*
* Reuses the common Heurist query parser and SQL predicate helpers while
* translating canonical keywords through a system entity schema.
*
* @project     Heurist academic knowledge management system
* @package     System\Query
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);
namespace Heurist\System\Query;

use Heurist\Database\DatabaseInterface;
use Heurist\Records\Query\CompiledQuery;
use Heurist\Records\Query\QueryValidationException;
use Heurist\Records\Query\UnsupportedQueryException;
use Heurist\Records\Query\Parser\RecordQueryParser;
use Heurist\Records\Query\Compiler\FieldPredicateCompiler;
use Heurist\Records\Query\Compiler\SqlBuildContext;
use Heurist\Runtime\RuntimeContext;

/** Compiles one filter/user query against its current legacy storage. */
final class SystemQueryBuilder
{
    private RuntimeContext $runtime;
    private RecordQueryParser $parser;
    private FieldPredicateCompiler $values;
    private array $schema;

    public function __construct(DatabaseInterface $database, RuntimeContext $runtime, array $schema)
    {
        $this->runtime = $runtime;
        $this->schema = $schema;
        $this->parser = new RecordQueryParser(array_keys($schema['fields']));
        $this->values = new FieldPredicateCompiler($database);
    }

    /** Normalize through the shared record-query parser. */
    public function normalize($query): array
    {
        return $this->parser->normalize($query);
    }

    /** Build IDs/count SQL and retain the shared normalized query DTO. */
    public function build($query, int $limit, int $offset, $sort = null, bool $sortProvided = false): array
    {
        $normalized = $this->normalize($query);
        $state = new SqlBuildContext(array(
            'userId'=>$this->runtime->userId,
            'groupIds'=>$this->runtime->groupIds,
            'isDbOwner'=>$this->runtime->isDbOwner,
            'hasAccess'=>$this->runtime->hasAccess
        ));
        $where = $this->compileGroup($normalized, 'AND', $state);
        $this->appendAccess($where, $state);
        $base = ' FROM '.$this->schema['table'].' '.$this->schema['alias'].' WHERE '.implode(' AND ', $where);
        $order = $this->compileSort($normalized, $state, $sort, $sortProvided);
        $idColumn = $this->column($this->schema['headers']['id']);
        $count = new CompiledQuery('SELECT COUNT(DISTINCT '.$idColumn.')'.$base,
            $state->types(), $state->values(), $normalized);

        $idState = new SqlBuildContext($state->context());
        $idWhere = $this->compileGroup($normalized, 'AND', $idState);
        $this->appendAccess($idWhere, $idState);
        $idBase = ' FROM '.$this->schema['table'].' '.$this->schema['alias'].' WHERE '.implode(' AND ', $idWhere);
        $idOrder = $this->compileSort($normalized, $idState, $sort, $sortProvided);
        $idState->bind($limit, 'i');
        $idState->bind($offset, 'i');
        $ids = new CompiledQuery('SELECT DISTINCT '.$idColumn.$idBase.$idOrder.' LIMIT ? OFFSET ?',
            $idState->types(), $idState->values(), $normalized);
        return array('ids'=>$ids, 'count'=>$count);
    }

    private function compileGroup(array $group, string $operator, SqlBuildContext $state): array
    {
        $conditions = array();
        foreach($group as $predicate){
            $key = (string)array_keys($predicate)[0];
            $value = $predicate[$key];
            list($base, $suffix) = $this->parser->predicateParts($key);
            if(in_array($base, array('sortby','sort','s'), true)){ continue; }
            if(in_array($base, array('any','all','not'), true)){
                $nested = $this->parser->normalizeQueryArray($value);
                $parts = $this->compileGroup($nested, $base === 'any' ? 'OR' : 'AND', $state);
                $expression = '('.implode($base === 'any' ? ' OR ' : ' AND ', $parts).')';
                $conditions[] = $base === 'not' ? 'NOT '.$expression : $expression;
                continue;
            }
            $conditions[] = $this->compilePredicate($base, $suffix, $value, $state);
        }
        if(empty($conditions)){ $conditions[] = '1=1'; }
        if($operator === 'OR' && count($conditions)>1){ return array('('.implode(' OR ', $conditions).')'); }
        return $conditions;
    }

    private function compilePredicate(string $base, string $suffix, $value, SqlBuildContext $state): string
    {
        switch($base){
            case '_all': return '1=1';
            case 't': case 'type': case 'typeid': case 'typename':
                $requested = is_array($value) ? reset($value) : $value;
                $requested = strtolower(trim((string)$requested));
                if($requested !== $this->schema['type'] && $requested !== $this->schema['type'].'s'){
                    throw new QueryValidationException('System query type does not match '.$this->schema['type']);
                }
                return '1=1';
            case 'id': case 'ids':
                return $this->idCondition($value, $state);
            case 'title':
                return $this->textCondition($this->column($this->schema['headers']['title']), $value, $state);
            case 'modified':
                return $this->values->headerDateCondition($this->column($this->schema['headers']['modified']), $value, $state);
            case 'owner': case 'workgroup': case 'wg':
                if(!isset($this->schema['headers']['owner'])){
                    throw new UnsupportedQueryException('owner is not available for '.$this->schema['type']);
                }
                return $this->values->integerCondition($this->column($this->schema['headers']['owner']), $value, $state);
            case 'f': case 'field':
                if($suffix === '' || !isset($this->schema['fields'][$suffix])){
                    throw new QueryValidationException('Unknown '.$this->schema['type'].' field: '.$suffix);
                }
                $field = $this->schema['fields'][$suffix];
                return $field['type'] === 'integer'
                    ? $this->values->integerCondition($this->column($field), $value, $state)
                    : $this->textCondition($this->column($field), $value, $state);
            default:
                throw new UnsupportedQueryException('Predicate is not supported for system records: '.$base);
        }
    }

    private function idCondition($value, SqlBuildContext $state): string
    {
        $items = is_array($value) ? $value : preg_split('/\s*,\s*/', trim((string)$value));
        $ids = array();
        foreach($items as $item){
            if(!is_numeric($item) || intval($item)<1){
                throw new QueryValidationException('Invalid system record ID: '.(string)$item);
            }
            $ids[] = intval($item);
        }
        $ids = array_values(array_unique($ids));
        if(empty($ids)){ return '0=1'; }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        foreach($ids as $id){ $state->bind($id, 'i'); }
        return $this->column($this->schema['headers']['id']).' IN ('.$placeholders.')';
    }

    private function textCondition(string $column, $value, SqlBuildContext $state): string
    {
        if(strpos(trim((string)$value), '@') === 0){
            throw new UnsupportedQueryException('Full-text predicates are not supported for legacy system tables');
        }
        return $this->values->scalarCondition($column, $value, $state);
    }

    private function appendAccess(array &$where, SqlBuildContext $state): void
    {
        if($this->schema['constraint']){ $where[] = $this->schema['constraint']; }
        if(!$this->runtime->hasAccess || $this->runtime->userId < 1){
            $where[] = '0=1';
            return;
        }
        if($this->schema['type'] !== 'filter' || $this->runtime->isDbOwner){ return; }
        // Owner 0 is the legacy public scope; System::hasAccess(0) permits it
        // for every authenticated user.
        $owners = array_merge(array(0, $this->runtime->userId), $this->runtime->groupIds);
        $owners = array_values(array_unique(array_filter(
            array_map('intval', $owners), static function($id){return $id>=0;}
        )));
        if(empty($owners)){ $where[] = '0=1'; return; }
        $placeholders = implode(',', array_fill(0, count($owners), '?'));
        foreach($owners as $id){ $state->bind($id, 'i'); }
        $where[] = $this->column($this->schema['headers']['owner']).' IN ('.$placeholders.')';
    }

    private function compileSort(array $query, SqlBuildContext $state, $sort, bool $provided): string
    {
        if(!$provided){
            foreach($query as $predicate){
                $key = (string)array_keys($predicate)[0];
                list($base) = $this->parser->predicateParts($key);
                if(in_array($base, array('sortby','sort','s'), true)){ $sort = $predicate[$key]; break; }
            }
        }
        if($sort === null || $sort === '' || $sort === array()){
            return ' ORDER BY '.$this->column($this->schema['headers']['title']).' ASC';
        }
        if(is_array($sort)){
            $field = (string)array_keys($sort)[0];
            $direction = strtoupper((string)reset($sort));
        }else{
            $text = trim((string)$sort);
            $direction = strpos($text, '-') === 0 ? 'DESC' : 'ASC';
            $field = ltrim($text, '-');
            if(preg_match('/^([^\s]+)\s+(asc|desc)$/i', $field, $matches)){
                $field = $matches[1]; $direction = strtoupper($matches[2]);
            }
        }
        $field = strtolower(preg_replace('/^f:/i', '', trim($field)));
        $aliases = array(
            'rec_id'=>'id', 'rec_rectypeid'=>'type', 'rec_title'=>'title',
            'rec_modified'=>'modified', 'rec_ownerugrpid'=>'owner'
        );
        $field = $aliases[$field] ?? $field;
        if($field === 'type'){
            throw new QueryValidationException('System record type is constant and cannot be sorted');
        }
        $definition = $this->schema['headers'][$field] ?? $this->schema['fields'][$field] ?? null;
        if(!$definition){ throw new QueryValidationException('Unknown sort field: '.$field); }
        if(!empty($definition['virtual'])){
            throw new UnsupportedQueryException('Virtual system field cannot be sorted: '.$field);
        }
        return ' ORDER BY '.$this->column($definition).' '.($direction === 'DESC' ? 'DESC' : 'ASC');
    }

    private function column(array $definition): string
    {
        return $this->schema['alias'].'.'.$definition['column'];
    }
}
