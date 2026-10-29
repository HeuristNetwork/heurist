<?php
/**
* LegacyFileQueryBuilder.php - Record query language on the legacy file table
*
* Compiles the standard query (RecordQueryParser) of the /files domain to SQL on
* recUploadedFiles while files are kept there (plan 20, §8.2). Supported: ids,
* title, url, added, modified, owner/addedby, f:<field> of the mapped fields (f without a field = the title)
* (MIME type, registered file type, names and other texts, size) and any/all/not.
* The table is written as {files} (Compiler/SqlTables, FileRepository::TABLES).
*
* @project     Heurist academic knowledge management system
* @package     Files
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Files;

use Heurist\Database\DatabaseInterface;
use Heurist\Records\Query\CompiledQuery;
use Heurist\Records\Query\QueryValidationException;
use Heurist\Records\Query\UnsupportedQueryException;
use Heurist\Records\Query\Parser\RecordQueryParser;
use Heurist\Records\Query\Compiler\FieldPredicateCompiler;
use Heurist\Records\Query\Compiler\SqlBuildContext;
use Heurist\Records\Query\Compiler\SqlTables;
use Heurist\Runtime\RuntimeContext;

/** Compiles one /files query to ids and count SQL on recUploadedFiles. */
final class LegacyFileQueryBuilder
{
    private const ALIAS = 'f';

    private RuntimeContext $runtime;
    private FileRecordMapping $mapping;
    private RecordQueryParser $parser;
    private FieldPredicateCompiler $values;
    private SqlTables $tables;

    public function __construct(DatabaseInterface $database, RuntimeContext $runtime, FileRecordMapping $mapping)
    {
        $this->runtime = $runtime;
        $this->mapping = $mapping;
        $this->parser = new RecordQueryParser();
        $this->tables = new SqlTables();
        $this->values = new FieldPredicateCompiler($database, $this->tables);
    }

    /** Normalize through the shared record-query parser. */
    public function normalize($query): array
    {
        return $this->parser->normalize($query);
    }

    /**
     * IDs (one page) and count SQL of a normalized query.
     *
     * @return array{ids:CompiledQuery,count:CompiledQuery}
     */
    public function build(array $query, int $limit, int $offset, $sort = null): array
    {
        $state = new SqlBuildContext();
        $where = $this->compileGroup($query, 'AND', $state);
        $this->appendAccess($where);
        $base = ' FROM {files} '.self::ALIAS.' WHERE '.implode(' AND ', $where);
        $count = new CompiledQuery($this->tables->resolve('SELECT COUNT(*)'.$base),
            $state->types(), $state->values(), $query);

        $idState = new SqlBuildContext();
        $idWhere = $this->compileGroup($query, 'AND', $idState);
        $this->appendAccess($idWhere);
        $order = $this->compileSort($query, $sort);
        $idState->bind($limit, 'i');
        $idState->bind($offset, 'i');
        $ids = new CompiledQuery($this->tables->resolve('SELECT '.self::ALIAS.'.ulf_ID FROM {files} '.self::ALIAS
            .' WHERE '.implode(' AND ', $idWhere).$order.' LIMIT ? OFFSET ?'),
            $idState->types(), $idState->values(), $query);
        return array('ids' => $ids, 'count' => $count);
    }

    private function compileGroup(array $group, string $operator, SqlBuildContext $state): array
    {
        $conditions = array();
        foreach($group as $predicate){
            $key = (string)array_keys($predicate)[0];
            $value = $predicate[$key];
            list($base, $suffix) = $this->parser->predicateParts($key);
            if(in_array($base, array('sortby', 'sort', 's'), true)){ continue; }
            if(in_array($base, array('any', 'all', 'not'), true)){
                $nested = $this->parser->normalizeQueryArray($value);
                $parts = $this->compileGroup($nested, $base === 'any' ? 'OR' : 'AND', $state);
                $expression = '('.implode($base === 'any' ? ' OR ' : ' AND ', $parts).')';
                $conditions[] = $base === 'not' ? 'NOT '.$expression : $expression;
                continue;
            }
            $conditions[] = $this->compilePredicate($base, $suffix, $value, $state);
        }
        if(empty($conditions)){ $conditions[] = '1=1'; }
        if($operator === 'OR' && count($conditions) > 1){ return array('('.implode(' OR ', $conditions).')'); }
        return $conditions;
    }

    private function compilePredicate(string $base, string $suffix, $value, SqlBuildContext $state): string
    {
        $f = self::ALIAS;
        switch($base){
            case '_all':
                return '1=1';
            case 't': case 'type': case 'typeid': case 'typename':
                return $this->typeCondition($value);
            case 'id': case 'ids':
                return $this->idCondition($value, $state);
            case 'plain': case 'title':
                return $this->values->textCondition(FileRecordMapping::titleSql($f), (string)$value, $state);
            case 'url':
                return $this->values->textCondition($f.'.ulf_ExternalFileReference', (string)$value, $state);
            case 'added':
                return $this->values->headerDateCondition($f.'.ulf_Added', $value, $state);
            case 'modified':
                return $this->values->headerDateCondition($f.'.ulf_Modified', $value, $state);
            case 'owner': case 'workgroup': case 'wg': case 'addedby':
                return $this->ownerCondition($value, $state);
            case 'f': case 'field':
                return $this->fieldCondition($suffix, $value, $state);
            default:
                throw new UnsupportedQueryException('Predicate is not supported for files: '.$base);
        }
    }

    /** t: the file record type (its id, concept code or the name "file"); another type finds nothing. */
    private function typeCondition($value): string
    {
        $items = is_array($value) ? $value : preg_split('/\s*,\s*/', trim((string)$value));
        foreach($items as $item){
            $item = strtolower(trim((string)$item));
            if(in_array($item, array('file', 'files', FileRecordMapping::RECTYPE, (string)$this->mapping->rectypeId()), true)){
                return '1=1';
            }
        }
        return '0=1';
    }

    private function idCondition($value, SqlBuildContext $state): string
    {
        $items = is_array($value) ? $value : preg_split('/\s*,\s*/', trim((string)$value));
        $ids = array();
        foreach($items as $item){
            if(!is_numeric($item) || intval($item) < 1){
                throw new QueryValidationException('Invalid file ID: '.(string)$item);
            }
            $ids[] = intval($item);
        }
        $ids = array_values(array_unique($ids));
        if(empty($ids)){ return '0=1'; }
        return $this->values->inCondition(self::ALIAS.'.ulf_ID', $ids, $state);
    }

    /** owner/addedby: user ids or "current"; a leading "-" excludes them. */
    private function ownerCondition($value, SqlBuildContext $state): string
    {
        $items = is_array($value) ? $value : preg_split('/\s*,\s*/', trim((string)$value));
        $negate = false;
        $ids = array();
        foreach($items as $item){
            $item = strtolower(trim((string)$item));
            if(strpos($item, '-') === 0){ $negate = true; $item = substr($item, 1); }
            if(in_array($item, array('current', 'currentuser', 'current_user'), true)){
                $ids[] = $this->runtime->userId;
            }elseif(ctype_digit($item)){
                $ids[] = intval($item);
            }elseif($item !== ''){
                throw new QueryValidationException('owner of files requires user IDs or "current"');
            }
        }
        $ids = array_values(array_unique($ids));
        if(empty($ids)){ return $negate ? '1=1' : '0=1'; }
        $condition = $this->values->inCondition(self::ALIAS.'.ulf_UploaderUGrpID', $ids, $state);
        return $negate ? 'NOT ('.$condition.')' : $condition;
    }

    /** f:<field>: a mapped field by id (enum fields by term id or label, size in bytes, else text). */
    private function fieldCondition(string $suffix, $value, SqlBuildContext $state): string
    {
        if($suffix === ''){
            // f without a field = any field on /records; for a file that is its title
            // (caption, else original name or URL): the search box of record lists (plan 21)
            return $this->values->textCondition(FileRecordMapping::titleSql(self::ALIAS), (string)$value, $state);
        }
        $fieldId = ctype_digit($suffix) ? intval($suffix) : 0;
        $mapping = $this->mapping->fields()[$fieldId] ?? null;
        if($mapping === null){
            throw new UnsupportedQueryException('Field is not available for files: '.$suffix);
        }
        $column = self::ALIAS.'.'.$mapping['column'];
        $convert = $mapping['convert'] ?? '';
        if($convert === 'size'){
            return $this->values->numericCondition('('.$column.'*1024)', $value, $state);
        }
        if($convert === 'ext' || $convert === 'term'){
            $items = is_array($value) ? $value : preg_split('/\s*,\s*/', trim((string)$value));
            $negate = false;
            $legacy = array();
            foreach($items as $item){
                $item = trim((string)$item);
                if(strpos($item, '-') === 0){ $negate = true; $item = substr($item, 1); }
                $legacy = array_merge($legacy, $this->mapping->legacyEnumValues($fieldId, $item));
            }
            $legacy = array_values(array_unique($legacy));
            if(empty($legacy)){ return $negate ? '1=1' : '0=1'; }
            $condition = $this->values->inCondition('LOWER('.$column.')', $legacy, $state);
            return $negate ? 'NOT ('.$condition.')' : $condition;
        }
        if(!empty($mapping['admin']) && !$this->isAdmin()){
            throw new UnsupportedQueryException('Field is not available for files: '.$suffix);
        }
        return $this->values->textCondition($column, (string)$value, $state);
    }

    /**
     * Visibility: logged-in users see every file (viewable and login-required ones,
     * as records with public and viewable visibility); guests only viewable files.
     */
    private function appendAccess(array &$where): void
    {
        if($this->runtime->hasAccess && $this->runtime->userId > 0){ return; }
        $f = self::ALIAS;
        $where[] = '('.$f.'.ulf_WhoCanView IS NULL OR '.$f.'.ulf_WhoCanView=\'\' OR '.$f.'.ulf_WhoCanView=\'viewable\')';
    }

    private function isAdmin(): bool
    {
        return $this->runtime->isAdmin || $this->runtime->isDbOwner;
    }

    /** ORDER BY of the sort parameter or a sortby predicate: the sort names of the records search (SortCompiler). */
    private function compileSort(array $query, $sort): string
    {
        if($sort === null || $sort === '' || $sort === array()){
            foreach($query as $predicate){
                $key = (string)array_keys($predicate)[0];
                list($base) = $this->parser->predicateParts($key);
                if(in_array($base, array('sortby', 'sort', 's'), true)){ $sort = $predicate[$key]; break; }
            }
        }
        $f = self::ALIAS;
        $order = array();
        $items = is_array($sort) ? $sort : preg_split('/\s*,\s*/', trim((string)$sort));
        foreach($items as $field => $item){
            if(is_string($field)){
                // {"rec_Added":"desc"}
                $direction = strtoupper((string)$item) === 'DESC' ? 'DESC' : 'ASC';
                $name = $field;
            }else{
                $text = trim((string)$item);
                if($text === ''){ continue; }
                $direction = strpos($text, '-') === 0 ? 'DESC' : 'ASC';
                $name = ltrim($text, '-');
                if(preg_match('/^([^\s]+)\s+(asc|desc)$/i', $name, $matches)){
                    $name = $matches[1];
                    $direction = strtoupper($matches[2]);
                }
            }
            $order[] = $this->sortExpression(strtolower(preg_replace('/^f:/i', '', trim($name)))).' '.$direction;
        }
        $order[] = $f.'.ulf_ID ASC'; // as the records search: by id when there is no sort
        return ' ORDER BY '.implode(',', $order);
    }

    private function sortExpression(string $name): string
    {
        $f = self::ALIAS;
        $headers = array(
            'id' => 'ulf_ID', 'ids' => 'ulf_ID', 'rec_id' => 'ulf_ID',
            'a' => 'ulf_Added', 'added' => 'ulf_Added', 'rec_added' => 'ulf_Added',
            'm' => 'ulf_Modified', 'modified' => 'ulf_Modified', 'rec_modified' => 'ulf_Modified',
            'u' => 'ulf_ExternalFileReference', 'url' => 'ulf_ExternalFileReference', 'rec_url' => 'ulf_ExternalFileReference',
            'owner' => 'ulf_UploaderUGrpID', 'rec_ownerugrpid' => 'ulf_UploaderUGrpID'
        );
        if(in_array($name, array('t', 'title', 'rec_title'), true)){ return FileRecordMapping::titleSql($f); }
        if(isset($headers[$name])){ return $f.'.'.$headers[$name]; }
        $mapping = ctype_digit($name) ? ($this->mapping->fields()[intval($name)] ?? null) : null;
        if($mapping === null || (!empty($mapping['admin']) && !$this->isAdmin())){
            throw new QueryValidationException('Unknown sort field for files: '.$name);
        }
        return $f.'.'.$mapping['column'];
    }
}
