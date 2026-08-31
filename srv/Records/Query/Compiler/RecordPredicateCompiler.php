<?php
/**
* RecordPredicateCompiler.php - Record-header and user predicate compiler
*
* @project     Heurist academic knowledge management system
* @package     Records\Search\Query
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

namespace Heurist\Records\Query\Compiler;



use Heurist\Database\DatabaseInterface;
use Heurist\Records\Query\QueryValidationException;
use Heurist\Records\Query\SearchExecutionException;
use Heurist\Records\Query\UnsupportedQueryException;

/** Compiles scalar record-header, user, workset, and tag predicates. */
final class RecordPredicateCompiler
{
    private DatabaseInterface $database;
    private $fields;
    /** Initialise record predicates with database name resolution. */
    public function __construct(DatabaseInterface $database, FieldPredicateCompiler $fields){ $this->database=$database; $this->fields=$fields; }

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
            case 'access': return $this->fields->scalarCondition($r.'.rec_NonOwnerVisibility', $value, $state);
            case 'user': case 'usr':
                $state->bind($this->resolveUserId($value, $state), 'i');
                return 'EXISTS (SELECT 1 FROM usrBookmarks ub WHERE ub.bkm_recID='.$r.'.rec_ID AND ub.bkm_UGrpID=?)';
            case 'ws': case 'workset':
                $state->bind($this->resolveUserId($value, $state), 'i');
                return 'EXISTS (SELECT 1 FROM usrWorkingSubsets wss WHERE wss.wss_RecID='.$r.'.rec_ID AND wss.wss_OwnerUGrpID=?)';
            case 'tag': case 'keyword': case 'kwd': return $this->tagCondition($r, $value, $state);
        }
        return null;
    }

    public function appendAccessConditions(array &$where, SqlBuildContext $state, array $context, string $r='r'): void
    {
        $userId=max(0,intval($context['userId']??0));
        $groupIds=array_values(array_unique(array_filter(array_map('intval',is_array($context['groupIds']??null)?$context['groupIds']:array()),static function($id){return $id>=0;})));
        if($userId>0&&!in_array($userId,$groupIds,true)){$groupIds[]=$userId;}
        if(!in_array(0,$groupIds,true)){$groupIds[]=0;}
        $where[]=$r.'.rec_FlagTemporary=0';
        if($userId<1){$where[]=$r.'.rec_NonOwnerVisibility IN ("public","pending")';return;}
        if(!empty($context['isDbOwner'])){return;}
        $visibility=array($r.'.rec_NonOwnerVisibility IN ("public","pending")');
        if(!empty($groupIds)){
            $ph=implode(',',array_fill(0,count($groupIds),'?'));
            foreach($groupIds as $id){$state->bind($id,'i');}
            $visibility[]=$r.'.rec_OwnerUGrpID IN ('.$ph.')';
            foreach($groupIds as $id){$state->bind($id,'i');}
            $visibility[]='('.$r.'.rec_NonOwnerVisibility="viewable" AND (NOT EXISTS (SELECT 1 FROM usrRecPermissions rp0 WHERE rp0.rcp_RecID='.$r.'.rec_ID) OR EXISTS (SELECT 1 FROM usrRecPermissions rp WHERE rp.rcp_RecID='.$r.'.rec_ID AND rp.rcp_UGrpID IN ('.$ph.'))))';
        }
        $where[]='('.implode(' OR ',$visibility).')';
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
        if(empty($names)){ return array(); }
        $names = array_values(array_unique($names));
        $sql = 'SELECT ugr_ID FROM sysUGrps WHERE ugr_Name IN ('
            .implode(',', array_fill(0, count($names), '?')).')';
        return array_values(array_filter(array_map(
            'intval', $this->database->fetchColumn($sql, $names)
        )));
    }

    /** Complete numeric/text tag predicate including any/all and NULL forms. */
    private function tagCondition(string $recordAlias, $value, SqlBuildContext $state): string
    {
        $all = false;
        if(is_array($value) && $this->fields->isAssociative($value)){
            $all = array_key_exists('all', $value);
            $value = $value[$all ? 'all' : 'any'] ?? array();
        }
        $values = is_array($value) ? $value : preg_split('/\s*,\s*/', trim((string)$value));
        $values = array_values(array_filter(array_map('strval', $values), static function($v){ return $v !== ''; }));
        if(count($values) === 1 && strtoupper($values[0]) === 'NULL'){
            return 'NOT EXISTS (SELECT 1 FROM usrRecTagLinks rtl WHERE rtl.rtl_RecID='.$recordAlias.'.rec_ID)';
        }
        if(empty($values) || (count($values) === 1 && strtoupper($values[0]) === '-NULL')){
            return 'EXISTS (SELECT 1 FROM usrRecTagLinks rtl WHERE rtl.rtl_RecID='.$recordAlias.'.rec_ID)';
        }
        $negate = false;
        foreach($values as &$tag){ if(strpos($tag, '-') === 0){ $negate = true; $tag = substr($tag, 1); } }
        unset($tag);
        $numeric = count(array_filter($values, 'ctype_digit')) === count($values);
        $column = $numeric ? 'rtl.rtl_TagID' : 'ut.tag_Text';
        $conditions = array();
        foreach($values as $tag){
            $state->bind($numeric ? intval($tag) : $tag, $numeric ? 'i' : 's');
            $conditions[] = $column.'=?';
        }
        $sql = 'SELECT rtl.rtl_RecID FROM usrRecTagLinks rtl';
        if(!$numeric){ $sql .= ' INNER JOIN usrTags ut ON ut.tag_ID=rtl.rtl_TagID'; }
        $sql .= ' WHERE ('.implode(' OR ', $conditions).')';
        if($all && count($values)>1){ $sql .= ' GROUP BY rtl.rtl_RecID HAVING COUNT(DISTINCT '.$column.')='.count($values); }
        $condition = $recordAlias.'.rec_ID IN ('.$sql.')';
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
            $sql = 'SELECT rfw_OldRecID,rfw_NewRecID FROM recForwarding WHERE rfw_OldRecID IN ('
                .implode(',', array_fill(0, count($current), '?')).')';
            $next = array();
            foreach($this->database->fetchRows($sql, $current) as $row){
                $id = intval($row[1]);
                if($id > 0 && !isset($seen[$id])){ $seen[$id] = true; $next[] = $id; }
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
        }elseif(preg_match('/^(\d+)-(\d+)$/', $text, $matches)){
            $state->bind(intval($matches[1]), 'i'); $state->bind(intval($matches[2]), 'i');
            $condition = $column.' = (SELECT rty_ID FROM defRecTypes '
                .'WHERE rty_OriginatingDBID=? AND rty_IDInOriginatingDB=? LIMIT 1)';
        }else{
            $state->bind($text, 's');
            $condition = $column.' = (SELECT rty_ID FROM defRecTypes WHERE rty_Name=? LIMIT 1)';
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
