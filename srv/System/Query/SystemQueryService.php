<?php
/**
* SystemQueryService.php - Search and retrieval for mapped system records
*
* Executes the common SearchRequest/SearchResult contract for legacy system
* entities and emits universal rec_* headers plus resolved logical fields.
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
use Heurist\Records\Query\QueryValidationException;
use Heurist\Records\Query\SearchRequest;
use Heurist\Records\Query\SearchResult;
use Heurist\Runtime\RuntimeContext;

/** Orchestrates filter/user query compilation, execution and record output. */
final class SystemQueryService
{
    private DatabaseInterface $database;
    private RuntimeContext $runtime;
    private SystemEntitySchemaRegistry $schemas;

    public function __construct(
        DatabaseInterface $database,
        RuntimeContext $runtime,
        ?SystemEntitySchemaRegistry $schemas = null
    ) {
        $this->database = $database;
        $this->runtime = $runtime;
        $this->schemas = $schemas ?? new SystemEntitySchemaRegistry();
    }

    /** Execute a collection or item request and return its stable representation. */
    public function execute(array $params, ?string $pathType = null, ?int $recordId = null)
    {
        $rawQuery = $params['query'] ?? $params['q'] ?? null;
        $type = $this->schemas->typeFromQuery($rawQuery, $pathType);
        $schema = $this->schemas->get($type);
        $builder = new SystemQueryBuilder($this->database, $this->runtime, $schema);

        if($recordId !== null){
            if($recordId < 1){ throw new QueryValidationException('System record id is not defined'); }
            $rawQuery = array(array('t'=>$type), array('ids'=>$recordId));
        }elseif(($rawQuery === null || $rawQuery === '') && isset($params['ids'])){
            $rawQuery = array(array('t'=>$type), array('ids'=>$params['ids']));
        }elseif($rawQuery === null || $rawQuery === ''){
            $rawQuery = array(array('t'=>$type), array('_all'=>true));
        }

        if(!empty($params['rules'])){
            throw new QueryValidationException('rules are not supported for system records');
        }
        $query = $builder->normalize($rawQuery);
        if(isset($params['filter']) && $params['filter'] !== '' && $params['filter'] !== null){
            $filter = $this->structured($params['filter']);
            $query[] = array('all'=>$builder->normalize($filter));
        }
        $legacyFilterTypes = $this->extractLegacyFilterTypePredicate($query, $schema);
        $request = new SearchRequest($query, array(
            'limit'=>$recordId === null ? ($params['limit'] ?? 1000) : 1,
            'offset'=>$recordId === null ? ($params['offset'] ?? 0) : 0,
            'fields'=>$params['fields'] ?? null,
            'detail'=>$params['detail'] ?? null,
            'resolveDetails'=>$params['resolveDetails'] ?? false,
            'sort'=>$params['sort'] ?? null
        ));
        list($ids, $total) = empty($legacyFilterTypes)
            ? $this->executeSqlPage($builder, $request, $params)
            : $this->executeLegacyFilterTypePage($builder, $request, $params, $schema, $legacyFilterTypes);
        $result = new SearchResult($ids, $total, $request->offset, $request->limit);

        if($request->detail === 'ids'){ return $result->toArray(); }
        $selection = $this->selectFields($request->fields, $schema, $recordId !== null);
        $records = $this->loadRecords($ids, $schema, $selection);
        if($recordId !== null){ return $records[0] ?? null; }
        return array(
            'records'=>$records,
            'meta'=>array(
                'database'=>$params['db'] ?? $this->runtime->databaseName,
                'entity'=>'sys',
                'type'=>$type,
                'fields'=>array(
                    'headers'=>$selection['outputs'],
                    'details'=>$this->fieldMetadata($selection['fields'], $schema)
                )
            ),
            'pagination'=>$this->pagination($result, $params)
        );
    }

    private function selectFields($value, array $schema, bool $item): array
    {
        $requested = array();
        if(is_array($value)){ $requested = $value; }
        elseif(is_string($value) && trim($value) !== ''){ $requested = preg_split('/\s*,\s*/', trim($value)); }

        $headerAliases = array(
            'rec_id'=>'id', 'rec_rectypeid'=>'type', 'rec_title'=>'title',
            'rec_modified'=>'modified', 'rec_ownerugrpid'=>'owner', 'type'=>'type', 't'=>'type'
        );
        $headers = array('id'=>true, 'type'=>true, 'title'=>true);
        $fields = array();
        if($item && empty($requested)){
            foreach($schema['headers'] as $name=>$unused){ $headers[$name] = true; }
            foreach($schema['fields'] as $name=>$unused){ $fields[$name] = true; }
        }
        foreach($requested as $field){
            $field = strtolower(trim((string)$field));
            $field = preg_replace('/^f:/', '', $field);
            $field = $headerAliases[$field] ?? $field;
            if($field === 'type'){ $headers['type'] = true; }
            elseif(isset($schema['headers'][$field])){ $headers[$field] = true; }
            elseif(isset($schema['fields'][$field])){ $fields[$field] = true; }
            elseif($field !== ''){ throw new QueryValidationException('Unknown '.$schema['type'].' output field: '.$field); }
        }
        $outputs = array('rec_ID','rec_RecTypeID','rec_Title');
        foreach($headers as $name=>$unused){
            if(isset($schema['headers'][$name])){ $outputs[] = $schema['headers'][$name]['output']; }
        }
        return array(
            'headers'=>array_keys($headers),
            'fields'=>array_keys($fields),
            'outputs'=>array_values(array_unique($outputs))
        );
    }

    private function loadRecords(array $ids, array $schema, array $selection): array
    {
        if(empty($ids)){ return array(); }
        $columns = array();
        foreach($selection['headers'] as $name){
            if($name === 'type' || !isset($schema['headers'][$name])){ continue; }
            $definition = $schema['headers'][$name];
            $columns[$definition['output']] = $definition['column'];
        }
        foreach($selection['fields'] as $name){
            $columns['field_'.$name] = $schema['fields'][$name]['column'];
        }
        $select = array();
        foreach($columns as $output=>$column){ $select[] = 's.'.$column.' AS '.$output; }
        $sql = 'SELECT '.implode(',', $select).' FROM '.$schema['table'].' s WHERE s.'
            .$schema['headers']['id']['column'].' IN ('.implode(',', array_fill(0, count($ids), '?')).')';
        if($schema['constraint']){ $sql .= ' AND '.$schema['constraint']; }
        $rows = $this->database->fetchAll($sql, $ids);
        $byId = array();
        foreach($rows as $row){
            $record = array(
                'rec_ID'=>intval($row['rec_ID']),
                'rec_RecTypeID'=>$schema['type'],
                'rec_Title'=>(string)($row['rec_Title'] ?? '')
            );
            foreach($selection['headers'] as $name){
                if($name === 'type' || !isset($schema['headers'][$name])){ continue; }
                $output = $schema['headers'][$name]['output'];
                if(in_array($output, array('rec_ID','rec_Title'), true)){ continue; }
                if(array_key_exists($output, $row)){ $record[$output] = $row[$output]; }
            }
            if(!empty($selection['fields'])){
                $record['details'] = array();
                foreach($selection['fields'] as $name){
                    $definition = $schema['fields'][$name];
                    $output = $definition['output'] ?? $name;
                    $value = !empty($definition['virtual'])
                        ? $this->legacyVirtualFieldValue($name, $row['field_'.$name] ?? null)
                        : ($row['field_'.$name] ?? null);
                    $record['details'][$output] = array(array('value'=>$value));
                }
            }
            $byId[$record['rec_ID']] = $record;
        }
        $ordered = array();
        foreach($ids as $id){ if(isset($byId[$id])){ $ordered[] = $byId[$id]; } }
        return $ordered;
    }

    private function fieldMetadata(array $fields, array $schema): array
    {
        $result = array();
        foreach($fields as $name){
            $field = $schema['fields'][$name];
            $result[] = array(
                'code'=>$field['output'] ?? $name,
                'name'=>$field['name'], 'type'=>$field['type']
            );
        }
        return $result;
    }

    /** Execute the ordinary SQL-paginated system query. */
    private function executeSqlPage(
        SystemQueryBuilder $builder, SearchRequest $request, array $params
    ): array {
        $compiled = $builder->build(
            $request->query, $request->limit, $request->offset,
            $request->sort, array_key_exists('sort', $params)
        );
        $total = intval($this->database->fetchValue(
            $compiled['count']->sql, $compiled['count']->values, 0
        ));
        $ids = array_map('intval', $this->database->fetchColumn(
            $compiled['ids']->sql, $compiled['ids']->values
        ));
        return array($ids, $total);
    }

    /*
     * LEGACY SAVED-FILTER TYPE COMPATIBILITY
     * --------------------------------------
     * filterType is not a physical usrSavedSearches column. Until filters move
     * to sysRecords/sysDetails, remove this virtual predicate before SQL is
     * compiled, classify every accessible candidate from svs_Query, and apply
     * offset/limit only after classification. Delete this block when filterType
     * becomes a normal resolved system field.
     */

    /** Remove top-level/conjunctive filterType predicates and return their values. */
    private function extractLegacyFilterTypePredicate(array &$query, array $schema): array
    {
        if($schema['type'] !== 'filter'){ return array(); }
        $types = array();
        $query = $this->stripLegacyFilterTypePredicates($query, $types, true);
        return array_values(array_unique($types));
    }

    private function stripLegacyFilterTypePredicates(array $group, array &$types, bool $conjunctive): array
    {
        $result = array();
        foreach($group as $predicate){
            $key = (string)array_keys($predicate)[0];
            $value = $predicate[$key];
            $normalizedKey = strtolower($key);
            if(in_array($normalizedKey, array('filtertype','f:filtertype','field:filtertype'), true)){
                if(!$conjunctive){
                    throw new QueryValidationException('filterType is supported only as an AND predicate');
                }
                foreach($this->normalizeLegacyFilterTypes($value) as $type){ $types[] = $type; }
                continue;
            }
            if(in_array($normalizedKey, array('all','any','not'), true) && is_array($value)){
                $predicate[$key] = $this->stripLegacyFilterTypePredicates(
                    $value, $types, $conjunctive && $normalizedKey === 'all'
                );
            }
            $result[] = $predicate;
        }
        return $result;
    }

    private function normalizeLegacyFilterTypes($value): array
    {
        $values = is_array($value) ? $value : preg_split('/\s*,\s*/', trim((string)$value));
        $result = array();
        foreach($values as $type){
            $type = strtolower(trim((string)$type));
            if(!in_array($type, array('faceted','rules','filter'), true)){
                throw new QueryValidationException('Unknown filterType: '.$type);
            }
            $result[] = $type;
        }
        if(empty($result)){ throw new QueryValidationException('filterType is not defined'); }
        return $result;
    }

    /** Query all ordinary candidates, classify them, then apply API pagination. */
    private function executeLegacyFilterTypePage(
        SystemQueryBuilder $builder,
        SearchRequest $request,
        array $params,
        array $schema,
        array $filterTypes
    ): array {
        $countQuery = $builder->build(
            $request->query, 1, 0, $request->sort, array_key_exists('sort', $params)
        );
        $candidateTotal = intval($this->database->fetchValue(
            $countQuery['count']->sql, $countQuery['count']->values, 0
        ));
        if($candidateTotal<1){ return array(array(), 0); }
        $allQuery = $builder->build(
            $request->query, $candidateTotal, 0,
            $request->sort, array_key_exists('sort', $params)
        );
        $candidateIds = array_map('intval', $this->database->fetchColumn(
            $allQuery['ids']->sql, $allQuery['ids']->values
        ));
        $filteredIds = $this->filterLegacySavedSearchIds($candidateIds, $schema, $filterTypes);
        $total = count($filteredIds);
        return array(array_slice($filteredIds, $request->offset, $request->limit), $total);
    }

    /** Load only svs_ID/svs_Query and retain the original SQL result order. */
    private function filterLegacySavedSearchIds(array $ids, array $schema, array $filterTypes): array
    {
        if(empty($ids)){ return array(); }
        $idColumn = $schema['headers']['id']['column'];
        $queryColumn = $schema['fields']['query']['column'];
        $sql = 'SELECT '.$idColumn.' AS id,'.$queryColumn.' AS query_value FROM '.$schema['table']
            .' WHERE '.$idColumn.' IN ('.implode(',', array_fill(0, count($ids), '?')).')';
        $rows = $this->database->fetchAll($sql, $ids);
        $typeById = array();
        foreach($rows as $row){
            $typeById[intval($row['id'])] = $this->classifyLegacySavedSearch($row['query_value'] ?? null);
        }
        return array_values(array_filter($ids, static function($id) use ($typeById, $filterTypes){
            return isset($typeById[$id]) && in_array($typeById[$id], $filterTypes, true);
        }));
    }

    /** Derive the temporary public filterType value from svs_Query JSON. */
    private function classifyLegacySavedSearch($value): string
    {
        $decoded = is_array($value) ? $value : json_decode(trim((string)$value), true);
        if(!is_array($decoded)){ return 'filter'; }
        if(array_key_exists('facets', $decoded)){ return 'faceted'; }
        $query = trim((string)($decoded['q'] ?? ''));
        $rules = $decoded['rules'] ?? '';
        $hasRules = is_array($rules) ? !empty($rules) : trim((string)$rules) !== '';
        return $query === '' && $hasRules ? 'rules' : 'filter';
    }

    private function legacyVirtualFieldValue(string $name, $source)
    {
        if($name === 'filtertype'){ return $this->classifyLegacySavedSearch($source); }
        return null;
    }

    private function pagination(SearchResult $result, array $params): array
    {
        $pagination = array('total'=>$result->total, 'offset'=>$result->offset, 'limit'=>$result->limit);
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if($uri !== ''){
            $pagination['self'] = $uri;
            if($result->offset+$result->limit < $result->total){
                $next = $params;
                $next['offset'] = $result->offset+$result->limit;
                $next['limit'] = $result->limit;
                unset($next['query']);
                $pagination['next'] = strtok($uri, '?').'?'.http_build_query($next);
            }
        }
        return $pagination;
    }

    private function structured($value)
    {
        if(!is_string($value)){ return $value; }
        $decoded = json_decode(trim($value), true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
