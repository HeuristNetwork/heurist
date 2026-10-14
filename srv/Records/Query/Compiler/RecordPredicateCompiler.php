<?php
/**
* RecordPredicateCompiler.php - Record-header and user predicate compiler
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
use Heurist\Definitions\DefinitionLookup;
use Heurist\Records\Store\RecordAccess;
use Heurist\Records\Store\RecordRepository;
use Heurist\Users\UserGroupRepository;
use Heurist\Records\Query\QueryValidationException;
use Heurist\Records\Query\SearchExecutionException;
use Heurist\Records\Query\UnsupportedQueryException;

/** Compiles scalar record-header, user, workset, and tag predicates. */
final class RecordPredicateCompiler
{
    private DatabaseInterface $database;
    private $fields;
    /** Initialise record predicates with database name resolution. */
    private RecordRepository $repository;

    public function __construct(DatabaseInterface $database, FieldPredicateCompiler $fields, ?RecordRepository $repository = null)
    {
        $this->database = $database;
        $this->fields = $fields;
        $this->repository = $repository ?? RecordRepository::shared($database);
    }

    /** Definitions of the database (record types by concept code or name). */
    private function definitions(): DefinitionLookup
    {
        return DefinitionLookup::shared($this->database);
    }

    public function compile(string $base, string $suffix, $value, SqlBuildContext $state, string $r): ?string
    {
        switch($base){
            case 't': case 'type': case 'typeid': case 'typename':
                return $this->recordTypeCondition($r.'.rec_RecTypeID', $value, $state);
            case 'id': case 'ids':
                if(is_array($value) && empty($value)){ return '0=1'; }
                return $this->recordIdCondition($r.'.rec_ID', $value, $state);
            case '_all': return '1=1';
            case 'title': return $this->fields->textCondition($r.'.rec_Title', $value, $state);
            case 'url': return $this->fields->textCondition($r.'.rec_URL', $value, $state);
            case 'notes': return $this->fields->textCondition($r.'.rec_ScratchPad', $value, $state);
            case 'added': return $this->fields->headerDateCondition($r.'.rec_Added', $value, $state);
            case 'modified': return $this->fields->headerDateCondition($r.'.rec_Modified', $value, $state);
            case 'before': return $this->fields->headerDateCondition($r.'.rec_Modified', '<='.(string)$value, $state);
            case 'after': case 'since': return $this->fields->headerDateCondition($r.'.rec_Modified', '>'.(string)$value, $state);
            case 'addedby': return $this->userCondition($r.'.rec_AddedByUGrpID', $value, $state);
            case 'owner': case 'workgroup': case 'wg': return $this->userCondition($r.'.rec_OwnerUGrpID', $value, $state);
            case 'access': return $this->accessCondition($r.'.rec_NonOwnerVisibility', $value, $state);
            case 'user': case 'usr':
                $state->bind($this->resolveUserId($value, $state), 'i');
                return 'EXISTS (SELECT 1 FROM {bookmarks} ub WHERE ub.bkm_recID='.$r.'.rec_ID AND ub.bkm_UGrpID=?)';
            case 'ws': case 'workset':
                $state->bind($this->resolveUserId($value, $state), 'i');
                return 'EXISTS (SELECT 1 FROM {worksets} wss WHERE wss.wss_RecID='.$r.'.rec_ID AND wss.wss_OwnerUGrpID=?)';
            case 'tag': case 'keyword': case 'kwd': return $this->tagCondition($r, $value, $state);
        }
        return null;
    }

    public function appendAccessConditions(array &$where, SqlBuildContext $state, array $context, string $r='r'): void
    {
        // the one visibility rule (RecordRepository::accessSql, plan 15)
        list($condition, $values) = $this->repository->accessSql($r, RecordAccess::fromContext($context));
        foreach($values as $value){ $state->bind($value, 'i'); }
        $where[] = $condition;
    }

    /**
     * access: one visibility value with the scalar operators, or a list of values
     * "viewable,public" (IN; a leading "-" excludes them: NOT IN).
     */
    private function accessCondition(string $column, $value, SqlBuildContext $state): string
    {
        $text = is_array($value) ? implode(',', $value) : trim((string)$value);
        if(!preg_match('/^(-?)\s*([a-z]+(?:\s*,\s*[a-z]+)+)$/i', $text, $match)){
            return $this->fields->scalarCondition($column, $value, $state);
        }
        $items = array_values(array_unique(array_map('strtolower', preg_split('/\s*,\s*/', $match[2]))));
        $allowed = array('hidden','viewable','pending','public');
        $unknown = array_diff($items, $allowed);
        if(!empty($unknown)){
            throw new QueryValidationException('Unknown visibility: '.implode(', ', $unknown));
        }
        foreach($items as $item){ $state->bind($item, 's'); }
        return $column.($match[1] === '-' ? ' NOT IN (' : ' IN (').implode(',', array_fill(0, count($items), '?')).')';
    }

    private function userCondition(string $column, $value, SqlBuildContext $state): string
    {
        $items = is_array($value) ? $value : preg_split('/\s*,\s*/', trim((string)$value));
        $negate = false; $ids = array(); $names = array();
        foreach($items as $item){
            $item = trim((string)$item);
            if(strpos($item, '-') === 0){ $negate = true; $item = substr($item, 1); }
            if(ctype_digit($item)){ $ids[] = intval($item); continue; }
            if(in_array(strtolower($item), array('current','currentuser','current_user'), true)){
                $ids[] = $this->resolveUserId('current', $state); continue;
            }
            if($item !== ''){ $names[] = $item; }
        }
        $ids = array_merge($ids, $this->lookupUserIds($names));
        $ids = array_values(array_unique($ids));
        if(empty($ids)){ return $negate ? '1=1' : '0=1'; }
        $condition = $this->fields->inCondition($column, $ids, $state);
        return $negate ? 'NOT ('.$condition.')' : $condition;
    }
    private function lookupUserIds(array $names): array
    {
        return UserGroupRepository::shared($this->database)->idsByName($names);
    }

    /**
     * Tag (keyword) predicate. Each tag is an ID or a tag text (a text matches every
     * tag with that text, as in legacy Heurist); IDs and texts may be mixed:
     * "1,6" / [1,6] / ["key1","DBM 1"] - any of them; {"any":[…]} likewise;
     * {"all":[…]} - every one; {"not":{…}} - the negation of the inner form;
     * a leading "-" negates a list ("-1,6": none of them); NULL - records without
     * tags, -NULL or "" - records with any tag. "Owner\\text" (a user or group name, a
     * backslash, the tag text) picks that owner's tag when several owners use the same
     * text, e.g. "Database Managers\\key1" (in JSON the backslash is written twice).
     */
    private function tagCondition(string $recordAlias, $value, SqlBuildContext $state): string
    {
        $all = false;
        if(is_array($value) && $this->fields->isAssociative($value)){
            if(array_key_exists('not', $value)){
                return 'NOT ('.$this->tagCondition($recordAlias, $value['not'], $state).')';
            }
            $all = array_key_exists('all', $value);
            $value = $value[$all ? 'all' : 'any'] ?? array();
        }
        $values = is_array($value) ? $value : preg_split('/\s*,\s*/', trim((string)$value));
        $values = array_values(array_filter(array_map(static function($v){ return trim((string)$v); }, $values),
            static function($v){ return $v !== ''; }));
        if(count($values) === 1 && strtoupper($values[0]) === 'NULL'){
            return 'NOT EXISTS (SELECT 1 FROM {tagLinks} rtl WHERE rtl.rtl_RecID='.$recordAlias.'.rec_ID)';
        }
        if(empty($values) || (count($values) === 1 && strtoupper($values[0]) === '-NULL')){
            return 'EXISTS (SELECT 1 FROM {tagLinks} rtl WHERE rtl.rtl_RecID='.$recordAlias.'.rec_ID)';
        }
        $negate = false;
        foreach($values as &$tag){ if(strpos($tag, '-') === 0){ $negate = true; $tag = substr($tag, 1); } }
        unset($tag);
        // one tag: its ID, or every tag with its text
        $one = function(string $tag) use ($state): string {
            if(ctype_digit($tag)){ $state->bind(intval($tag), 'i'); return 'rtl.rtl_TagID=?'; }
            $cut = strrpos($tag, '\\');
            if($cut !== false && $cut > 0 && $cut < strlen($tag) - 1){
                $state->bind(trim(substr($tag, 0, $cut)), 's');
                $state->bind(trim(substr($tag, $cut + 1)), 's');
                return 'rtl.rtl_TagID IN (SELECT ut.tag_ID FROM {tags} ut INNER JOIN {groups} ug ON ug.ugr_ID=ut.tag_UGrpID'
                    .' WHERE ug.ugr_Name=? AND ut.tag_Text=?)';
            }
            $state->bind($tag, 's');
            return 'rtl.rtl_TagID IN (SELECT ut.tag_ID FROM {tags} ut WHERE ut.tag_Text=?)';
        };
        $tagged = static function(string $condition) use ($recordAlias): string {
            return $recordAlias.'.rec_ID IN (SELECT rtl.rtl_RecID FROM {tagLinks} rtl WHERE '.$condition.')';
        };
        if($all && count($values) > 1){
            $parts = array();
            foreach($values as $tag){ $parts[] = $tagged($one($tag)); }
            $condition = '('.implode(' AND ', $parts).')';
        }else{
            $parts = array();
            foreach($values as $tag){ $parts[] = $one($tag); }
            $condition = $tagged('('.implode(' OR ', $parts).')');
        }
        return $negate ? 'NOT ('.$condition.')' : $condition;
    }
    private function recordIdCondition(string $column, $value, SqlBuildContext $state): string
    {
        $text = is_array($value) ? '' : trim((string)$value); $negate = false;
        if($text !== '' && strpos($text, '-') === 0){ $negate = true; $value = substr($text, 1); }
        $ids = $this->fields->numericList($value, 'record');
        $ids = array_values(array_unique(array_merge($ids, $this->replacementRecordIds($ids))));
        $condition = $this->fields->inCondition($column, $ids, $state);
        return $negate ? 'NOT ('.$condition.')' : $condition;
    }

    /** Follow recForwarding internally, retaining both requested and current IDs. */
    private function replacementRecordIds(array $recordIds): array
    {
        if(empty($recordIds)){ return $recordIds; }
        $current = array_values(array_unique(array_map('intval', $recordIds)));
        $seen = array_fill_keys($current, true);
        for($level=0; $level<10; $level++){
            $next = array();
            foreach($this->repository->forwarding($current) as $newIds){
                foreach($newIds as $id){
                    if($id > 0 && !isset($seen[$id])){ $seen[$id] = true; $next[] = $id; }
                }
            }
            if(empty($next)){ break; }
            $current = array_values(array_unique($next));
        }
        return array_map('intval', array_keys($seen));
    }

    /** Record types accept local IDs, names, and OriginDB-ID concept codes. */
    private function recordTypeCondition(string $column, $value, SqlBuildContext $state): string
    {
        if(is_array($value)){
            $ids = $this->fields->numericList($value, 'record type');
            return $this->fields->inCondition($column, $ids, $state);
        }
        $text = trim((string)$value); $negate = false;
        if(strpos($text, '-') === 0 && !preg_match('/^\d+-\d+$/', $text)){
            $negate = true; $text = substr($text, 1);
        }
        if(preg_match('/^\d+(?:\s*,\s*\d+)*$/', $text)){
            $condition = $this->fields->inCondition($column, $this->fields->numericList($text, 'record type'), $state);
        }elseif(preg_match('/^\d+-\d+$/', $text)){
            // concept code to the local id (DefinitionLookup, one rule); unknown: no match
            $state->bind($this->definitions()->localId('rty', $text), 'i');
            $condition = $column.' = ?';
        }else{
            $state->bind($this->definitions()->rectypeIdByName($text), 'i');
            $condition = $column.' = ?';
        }
        return $negate ? 'NOT ('.$condition.')' : $condition;
    }
    private function resolveUserId($value, SqlBuildContext $state): int
    {
        $text = strtolower(trim((string)$value));
        if($value === null || $text === '' || $text === 'current'){
            $userId = intval($state['context']['userId'] ?? 0);
            if($userId < 1){
                throw new QueryValidationException('Current-user query requires authentication');
            }
            return $userId;
        }
        if(!ctype_digit($text) || intval($text)<1){
            throw new QueryValidationException('User ID must be a positive integer or current');
        }
        return intval($text);
    }
}
