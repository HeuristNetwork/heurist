<?php
/**
* SortCompiler.php - Record and typed-detail ordering compiler
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

namespace hserv\records\search\query;


require_once dirname(__FILE__).'/SqlBuildContext.php';
require_once dirname(__FILE__).'/FieldPredicateCompiler.php';
require_once dirname(__FILE__).'/RecordQueryParser.php';

use hserv\records\search\QueryValidationException;

/** Compiles deterministic ORDER BY clauses. */
final class SortCompiler
{
    private $fields; private $parser;
    public function __construct(FieldPredicateCompiler $fields, RecordQueryParser $parser){$this->fields=$fields;$this->parser=$parser;}

    public function compileSort(array $query, SqlBuildContext $state): string
    {
        $sortValues = array();
        foreach($query as $predicate){
            $key = (string)array_keys($predicate)[0];
            list($base) = $this->parser->predicateParts($key);
            if(in_array($base, array('sortby','sort','s'), true)){
                $value = $predicate[$key];
                foreach(is_array($value) ? $value : explode(',', (string)$value) as $sort){
                    $sort = trim((string)$sort);
                    if($sort !== ''){ $sortValues[] = $sort; }
                }
            }
        }
        if(empty($sortValues)){ return ' ORDER BY r.rec_ID'; }

        $expressions = array();
        foreach($sortValues as $sort){
            $direction = 'ASC';
            if($sort[0] === '-'){
                $direction = 'DESC'; $sort = substr($sort, 1);
            }elseif($sort[0] === '+'){
                $sort = substr($sort, 1);
            }
            list($base, $suffix) = $this->parser->predicateParts(strtolower($sort));
            switch($base){
                case 'id': case 'ids': $expressions[] = 'r.rec_ID '.$direction; break;
                case 't': case 'title': $expressions[] = 'r.rec_Title '.$direction; break;
                case 'rt': case 'type': $expressions[] = 'r.rec_RecTypeID '.$direction; break;
                case 'm': case 'modified': $expressions[] = 'r.rec_Modified '.$direction; break;
                case 'a': case 'added': $expressions[] = 'r.rec_Added '.$direction; break;
                case 'u': case 'url': $expressions[] = 'r.rec_URL '.$direction; break;
                case 'p': case 'popularity': $expressions[] = 'r.rec_Popularity '.$direction; break;
                case 'r': case 'rating':
                    $userId = intval($state['context']['userId'] ?? 0);
                    if($userId < 1){ throw new QueryValidationException('Rating sort requires authentication'); }
                    $state->bind($userId, 'i');
                    $expressions[] = 'COALESCE((SELECT ub.bkm_Rating FROM usrBookmarks ub '
                        .'WHERE ub.bkm_recID=r.rec_ID AND ub.bkm_UGrpID=? LIMIT 1),0) '.$direction;
                    break;
                case 'f': case 'field':
                    if($suffix === '' || !ctype_digit($suffix)){
                        throw new QueryValidationException('Field sort requires a numeric field ID');
                    }
                    $fieldId = intval($suffix); $fieldType = $this->fields->fieldType($fieldId);
                    $state->bind($fieldId, 'i');
                    if($fieldType === 'date'){
                        $expressions[] = '(SELECT MIN(si.rdi_estMinDate) FROM recDetailsDateIndex si '
                            .'INNER JOIN recDetails sdate ON sdate.dtl_ID=si.rdi_DetailID '
                            .'WHERE si.rdi_RecID=r.rec_ID AND si.rdi_DetailTypeID=?'
                            .$this->fields->detailVisibilityCondition('sdate', 'r', $state).') '.$direction;
                    }elseif($fieldType === 'enum'){
                        $expressions[] = '(SELECT MIN(CONCAT(LPAD(COALESCE(st.trm_OrderInBranch,999999),6,"0"),st.trm_Label)) '
                            .'FROM recDetails sd INNER JOIN defTerms st ON st.trm_ID=sd.dtl_Value '
                            .'WHERE sd.dtl_RecID=r.rec_ID AND sd.dtl_DetailTypeID=?'
                            .$this->fields->detailVisibilityCondition('sd', 'r', $state).') '.$direction;
                    }elseif($fieldType === 'integer'){
                        $expressions[] = '(SELECT MIN(CAST(sd.dtl_Value AS SIGNED)) FROM recDetails sd '
                            .'WHERE sd.dtl_RecID=r.rec_ID AND sd.dtl_DetailTypeID=?'
                            .$this->fields->detailVisibilityCondition('sd', 'r', $state).') '.$direction;
                    }elseif($fieldType === 'float'){
                        $expressions[] = '(SELECT MIN(CAST(sd.dtl_Value AS DECIMAL(65,20))) FROM recDetails sd '
                            .'WHERE sd.dtl_RecID=r.rec_ID AND sd.dtl_DetailTypeID=?'
                            .$this->fields->detailVisibilityCondition('sd', 'r', $state).') '.$direction;
                    }else{
                        $expressions[] = '(SELECT MIN(sd.dtl_Value) FROM recDetails sd '
                            .'WHERE sd.dtl_RecID=r.rec_ID AND sd.dtl_DetailTypeID=?'
                            .$this->fields->detailVisibilityCondition('sd', 'r', $state).') '.$direction;
                    }
                    $expressions[] = 'r.rec_Title ASC';
                    break;
                case 'set': case 'fixed':
                    $ids = $suffix === '' ? $this->fixedSortIds($query) : $this->fields->numericList($suffix, 'fixed sort record');
                    if(empty($ids)){ throw new QueryValidationException('Fixed-set sort requires an IDs predicate'); }
                    $placeholders = array();
                    foreach($ids as $id){ $state->bind($id, 'i'); $placeholders[] = '?'; }
                    $expressions[] = 'FIELD(r.rec_ID,'.implode(',', $placeholders).') ASC';
                    break;
                default:
                    throw new QueryValidationException('Unknown sort expression: '.$sort);
            }
        }
        $expressions[] = 'r.rec_ID ASC';
        return ' ORDER BY '.implode(', ', array_values(array_unique($expressions)));
    }
    private function fixedSortIds(array $query): array
    {
        foreach($query as $predicate){
            $key = (string)array_keys($predicate)[0];
            list($base) = $this->parser->predicateParts($key);
            if($base === 'id' || $base === 'ids'){
                try{ return $this->fields->numericList($predicate[$key], 'fixed sort record'); }
                catch(QueryValidationException $e){ return array(); }
            }
        }
        return array();
    }
}
