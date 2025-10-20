<?php
/**
* recordSearch.php - Functions library to search records
*
* recordSearchMinMax - Find minimal and maximal values for given detail type and record type
* recordSearchFacets - returns counts for facets for given query
*
* recordSearchRelatedIds - search all related (links and releationship) records for given set of records recursively
* recordSearchRelated
* recordLinkedCount  - search count by target record type for given source type and base field
* recordSearchPermissions  - all view group permissions for given set of records
* recordGetOwnerVisibility - NOT USED returns sql where to check record visibility
* recordGetRelationshipType - returns only first relationship type ID for 2 given records
* recordGetRelationship - returns relrecord (RT#1) for given pair of records (id or full record)
* recordGetLinkedRecords - returns all linked record and their types (for update titles)
* recordSearchMenuItems - returns all CMS records for given CMS home record
* not implemented recordSearchMapDocItems - returns all layers and datasource records for given map document record
*
* recordSearchFindParent - find parent record for rec_ID with given record type
*
* recordSearch - MAIN method - parses query and searches for heurist records
* recordSearchByID - returns header (and details)
* recordSearchDetails - returns details for given rec id
* recordSearchGeoDetails - find geo in linked places
* recordSearchPersonalTags
*
* @project     Heurist academic knowledge management system
* @package Records\Search
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/
use hserv\utilities\USystem;
use hserv\utilities\Temporal;
use hserv\entity\DbsUsersGroups;
use hserv\structure\ConceptCode;
use hserv\entity\DbRecUploadedFiles;

require_once 'recordFile.php';//it includes UFile.php
require_once 'composeSql.php';
require_once dirname(__FILE__).'/../../structure/search/dbsData.php';
require_once dirname(__FILE__).'/../../structure/dbsTerms.php';

define('MSG_SAVED_FILTER', 'Saved filter: ');
define('MSG_MEMORY_LIMIT', ' records are in result of search query. Memory limit does not allow to retrieve all of them. Please filter to a smaller set of results.');

define('SQL_RECDETAILS', ' FROM Records, recDetails WHERE rec_ID=dtl_RecID AND rec_FlagTemporary!=1 AND ');
define('SQL_RELMARKER_CONSTR', 'SELECT dty_ID, dty_JsonTermIDTree, dty_PtrTargetRectypeIDs FROM defDetailTypes WHERE dty_Type = "relmarker" AND ');

/**
 * Finds distinct detail values and their counts for a given detail type and record type or a specific set of records.
 *
 * The function can operate in two modes:
 * 1. If `rec_IDs` are provided in `$params`: It counts distinct values and total occurrences
 *    for the specified `dty_ID` within those records. If `rty_ID` is also provided and all records
 *    of that type are included in `rec_IDs`, it switches to the second mode.
 * 2. If `rec_IDs` are not provided or if `all_records_for_rty` is true: It counts distinct values
 *    and total occurrences for the specified `dty_ID` across all records of `rty_ID`.
 *
 * The `$params['mode']` controls what is returned:
 * - Mode 0 or 1 (default): Calculates both unique value count and total detail count.
 * - Mode 2: Calculates only total detail count.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param array $params An associative array of parameters:
 *                      - 'dty_ID' (int): The Detail Type ID to search within.
 *                      - 'rty_ID' (int, Optional): The Record Type ID to constrain the search. Required if 'rec_IDs' is not set.
 *                      - 'rec_IDs' (array|string, Optional): An array or comma-separated string of Record IDs to search within.
 *                      - 'mode' (int, Optional): Search mode. 0 or 1 for unique and total counts, 2 for only total count. Defaults to 1.
 * @return array An associative array with 'status' and 'data'.
 *               'data' contains 'unique' (count of distinct dtl_Value) and 'total' (count of all dtl_Value occurrences).
 *               Returns an error array if parameters are invalid or a DB error occurs.
 */
function recordSearchDistinctValue($system, $params){

    $mysqli = $system->getMysqli();
    $all_records_for_rty = false; //if false - search for given set of record ids

    //0 unique, 1 -both, 2 - all values
    if(!@$params['mode']){
        $params['mode'] = 1;
    }
    $search_unique = (intval($params['mode'])<=1);
    $search_all = (intval($params['mode'])>=1);

    if(@$params['rec_IDs']){
        $rec_IDs = prepareIds($params['rec_IDs']);
        $total_cnt = count($rec_IDs);
        $offset = 0;
        if($total_cnt>0 && intval(@$params['dty_ID'])>0){

            if(intval(@$params['rty_ID'])>0){
                $query = 'SELECT count(rec_ID) FROM Records WHERE rec_FlagTemporary!=1 AND rec_RecTypeID='.intval($params['rty_ID']);
                $res = mysql__select_value($mysqli, $query);
                if(intval($res)==$total_cnt){
                    $all_records_for_rty = true;
                }
            }

            if(!$all_records_for_rty){

                $values_unique = array();
                $detail_count = 0;

                while ($offset<$total_cnt){

                    $rec_IDs_chunk = array_slice($rec_IDs, $offset, 1000);

                    if($search_unique){

                        $query = 'SELECT DISTINCT dtl_Value '
                        .SQL_RECDETAILS
                        .predicateId('rec_ID',$rec_IDs_chunk)
                        .SQL_AND
                        .predicateId('dtl_DetailTypeID',$params['dty_ID']);

                        $values = mysql__select_list2($mysqli, $query);

                        $values_unique = array_unique(array_merge($values_unique, $values));

                    }
                    if($search_all){
                        $query = 'SELECT count(dtl_ID) '
                        .SQL_RECDETAILS
                        .predicateId('rec_ID',$rec_IDs_chunk)
                        .SQL_AND
                        .predicateId('dtl_DetailTypeID',$params['dty_ID']);

                        $detail_count = $detail_count+mysql__select_value($mysqli, $query);
                    }

                    $offset = $offset+1000;
                }//while

                $response = array('status'=>HEURIST_OK, 'data'=> array('unique'=>count($values_unique),'total'=>$detail_count));
            }

        }else{
            $response = $system->addError(HEURIST_INVALID_REQUEST, 'Count query parameters are invalid');
        }
    }else{
        $all_records_for_rty = true;
    }

    if($all_records_for_rty){
        if(intval(@$params['rty_ID'])>0 && intval(@$params['dty_ID'])>0){

            $unique_count = 0;
            $detail_count = 0;

            if($search_unique){
                $query = 'SELECT COUNT(DISTINCT dtl_Value) '
                .SQL_RECDETAILS
                .predicateId('rec_RecTypeID',$params['rty_ID'])
                .SQL_AND
                .predicateId('dtl_DetailTypeID',$params['dty_ID']);

                $res = mysql__select_value($mysqli, $query);
                if ($res==null){
                    return $system->addError(HEURIST_DB_ERROR, 'Search query error on unique values count. Query '.$query, $mysqli->error);
                }
                $unique_count = intval($res);
            }
            if($search_all){
                $query = 'SELECT COUNT(dtl_ID) '
                .SQL_RECDETAILS
                .predicateId('rec_RecTypeID',$params['rty_ID'])
                .SQL_AND
                .predicateId('dtl_DetailTypeID',$params['dty_ID']);
                $res = mysql__select_value($mysqli, $query);
                if ($res==null){
                    return $system->addError(HEURIST_DB_ERROR, 'Search query error on details count. Query '.$query, $mysqli->error);
                }
                $detail_count = intval($res);
            }

            $response = array('status'=>HEURIST_OK, 'data'=> array('unique'=>$unique_count,'total'=>$detail_count));

        }else{
            $response = $system->addError(HEURIST_INVALID_REQUEST, 'Count query parameters are invalid');
        }
    }

    return $response;
}

/**
 * Searches for matching or non-matching detail values between a source set of records
 * and a target record type/detail type combination.
 *
 * It iterates through a list of source record IDs (`$params['rec_IDs']`) in chunks.
 * For each source record, it looks for its detail value (specified by `$params['dty_src']`).
 * Then, it searches for other records of a target record type (`$params['rty_trg']`)
 * that have a matching detail value in a target detail type (`$params['dty_trg']`).
 *
 * Depending on parameters, it can:
 * - Count total distinct pairs of matching records (`$params['pairs']` is false, `$params['nonmatch']` is false).
 * - Return the actual pairs of (d1.dtl_RecID, d2.dtl_RecID) for matches (`$params['pairs']` is true).
 * - Report source records/values that do NOT have a match in the target set (`$params['nonmatch']` is true).
 *
 * @param \hserv\System $system The Heurist system object.
 * @param array $params An associative array of parameters:
 *                      - 'dty_src' (int): The Detail Type ID for the source records' values.
 *                      - 'rty_trg' (int): The Record Type ID for the target records.
 *                      - 'dty_trg' (int): The Detail Type ID for the target records' values.
 *                      - 'rec_IDs' (array|string): An array or comma-separated string of source Record IDs.
 *                      - 'nonmatch' (int, Optional): If 1, reports source records/values with no matches. Defaults to 0.
 *                      - 'pairs' (int, Optional): If 1 (and 'nonmatch' is 0), returns pairs of matching (sourceRecID, targetRecID).
 *                                               Otherwise (if 0 or 'nonmatch' is 1), returns counts or non-match info. Defaults to 0.
 * @return array An associative array with 'status' and 'data'.
 *               'data' contains:
 *               - If counting matches: An integer count.
 *               - If returning pairs: An array of arrays, each `[sourceRecID, targetRecID]`.
 *               - If reporting non-matches: An array of arrays, each `[sourceRecID, sourceRecTitle, sourceValueWithNoMatch]`.
 *               Returns an error array if parameters are invalid or a DB error occurs.
 */
function recordSearchMatchedValues($system, $params){

    if(intval(@$params['dty_src'])>0 &&
       intval(@$params['rty_trg'])>0 && intval(@$params['dty_trg'])>0){ // rty_src was commented out, assuming it's not strictly needed if rec_IDs are given
        $mysqli = $system->getMysqli();


        $need_nonmatches = (@$params['nonmatch']==1); // Report non-matches
        $need_ids = (@$params['pairs']==1); //return pairs - otherwise just count

        $rec_IDs = prepareIds($params['rec_IDs']);

        $total_cnt = count($rec_IDs);
        $offset = 0;

        if($total_cnt>0){

            //'distinct d1.dtl_RecID, d2.dtl_RecID '
            //d1.dtl_Value, d2.dtl_Value,
            if($need_nonmatches || $need_ids){
                $result = array();
            }else{
                $result = 0;
            }

            $iteration = 1;
            $is_completed_without_error = true;

            while ($offset<$total_cnt){

                $rec_IDs_chunk = array_slice($rec_IDs, $offset, 500);

                if($need_nonmatches){

                    $query = 'select distinct d1.dtl_RecID, r1.rec_Title, d1.dtl_Value FROM Records r1, recDetails d1 '
                    .' LEFT JOIN recDetails d2 on d1.dtl_Value=d2.dtl_Value and d1.dtl_RecID!=d2.dtl_RecID'
                    .' LEFT JOIN Records r2 on d2.dtl_RecID=r2.rec_ID and r2.rec_RecTypeID='
                    .intval($params['rty_trg']).' and d2.dtl_DetailTypeID='.intval($params['dty_trg'])
                    .' WHERE r1.rec_ID IN ('
                    .implode(',',$rec_IDs_chunk).') and d1.dtl_DetailTypeID='
                    .intval($params['dty_src'])
                    .' and d1.dtl_RecID=r1.rec_ID and d2.dtl_Value is null';

                }else {
                    if($need_ids){
                        $query = 'select distinct d1.dtl_RecID, d2.dtl_RecID ';
                    }else{
                        $query = 'select count(distinct d1.dtl_RecID, d2.dtl_RecID) ';
                    }
                    $query = $query
                    .' from recDetails d1, recDetails d2, Records r2'   //Records r1,
                    .' where d1.dtl_RecID IN ('.implode(',',$rec_IDs_chunk).')'      //=r1.rec_ID and r1.rec_RecTypeID='.intval($params['rty_src'])
                    .' and d1.dtl_DetailTypeID='.intval($params['dty_src'])
                    .' and d2.dtl_RecID=r2.rec_ID and r2.rec_RecTypeID='.intval($params['rty_trg'])
                    .' and d2.dtl_DetailTypeID='.intval($params['dty_trg'])
                    .' and d1.dtl_RecID!=d2.dtl_RecID and d1.dtl_Value=d2.dtl_Value';
                }

                if($need_nonmatches){
                    $query .= ' ORDER BY d1.dtl_RecID';
                    $res = mysql__select_all($mysqli, $query, 0, 100);
                }elseif($need_ids){
                    $query .= ' ORDER BY d1.dtl_RecID';
                    $res = mysql__select_all($mysqli, $query);
                }else{
                    $res = mysql__select_value($mysqli, $query);
                }

                if ($res == null) {
                    if(is_array($res)){
                        //error_log('Empty array on interation '.$iteration);
                    }else{
                        $response = $system->addError(HEURIST_DB_ERROR, 'Search query error on matching values. '
                            .'<br> Records given: '.$total_cnt
                            .'<br> Iteration: '.$iteration
                            .'<br> Found so far '.(is_array($result)?count($result):$result)
                            //.'<br>Res: '.print_r($res,true)
                            .'<br>Query '.$query, $mysqli->error);
                        $is_completed_without_error = false;
                        break;
                    }
                }else{
                    if($need_nonmatches || $need_ids){
                        if(!empty($res)){
                            $result = array_merge($result, $res);
                        }
                    }else{
                        $result = $result + $res;
                    }
                }

                $offset = $offset+500;
                $iteration++;
            }//wile

            if ($is_completed_without_error){
                $response = array('status'=>HEURIST_OK, 'data'=> $result);
            }

        }else{
            $response = $system->addError(HEURIST_INVALID_REQUEST, 'Source records are not defined as matching query parameter');
        }
    }else{
        $response = $system->addError(HEURIST_INVALID_REQUEST, 'Matching query parameters are invalid');
    }

    return $response;
}


/**
 * Finds the minimum and maximum numeric values for a given detail type within a specific record type.
 *
 * The detail values are cast to DECIMAL for comparison.
 * Optionally filters by user's working subset if the user is logged in.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param array $params An associative array of parameters:
 *                      - 'rt' (int): The Record Type ID.
 *                      - 'dt' (int): The Detail Type ID (should be a numeric type).
 * @return array An associative array with 'status' and 'data' (containing 'MIN' and 'MAX' values),
 *               or an error array if parameters are invalid or a DB error occurs.
 *               Returns status HEURIST_NOT_FOUND if no matching data is found.
 */
function recordSearchMinMax($system, $params){

    if(intval(@$params['rt'])>0 && intval(@$params['dt'])>0){

        $mysqli = $system->getMysqli();
        //$currentUser = $system->getCurrentUser();

        $query = 'SELECT MIN(CAST(dtl_Value as decimal)) as MIN, MAX(CAST(dtl_Value as decimal)) AS MAX '
        .SQL_RECDETAILS;

        $where_clause  = predicateId('rec_RecTypeID',$params['rt'])
        .SQL_AND
        .predicateId('dtl_DetailTypeID',$params['dt'])
        ." AND dtl_Value is not null AND dtl_Value!=''";

        $currUserID = $system->getUserId();
        if( $currUserID > 0 ) {
            $q2 = 'select wss_RecID from usrWorkingSubsets where wss_OwnerUGrpID='.$currUserID.' LIMIT 1';
            if(mysql__select_value($mysqli, $q2)>0){
                $query = $query.', usrWorkingSubsets ';
                $where_clause = $where_clause.' AND wss_RecID=rec_ID AND wss_OwnerUGrpID='.$currUserID;
            }

        }
        //@todo - current user constraints

        //$res = $mysqli->query($query.$where_clause);
        $res = mysql__select($mysqli, $query.$where_clause);
        if (!$res){
            $response = $system->addError(HEURIST_DB_ERROR, "Search query error on min/max. Query ".$query, $mysqli->error);
        }else{
            $row = $res->fetch_assoc();
            if($row){
                $response = array("status"=>HEURIST_OK, "data"=> $row);
            }else{
                $response = array("status"=>HEURIST_NOT_FOUND);
            }
            $res->close();
        }

    }else{
        $response = $system->addError(HEURIST_INVALID_REQUEST, "MinMax query parameters are invalid");
    }

    return $response;
}

/**
 * Parses a resource string (e.g., "t:20 f:41") and returns an array
 * containing the record type ID and detail type (field) ID.
 *
 * The input string is expected to be space-separated, with the first part
 * being the record type (prefixed with "t:") and the second part being
 * the detail type/field (prefixed with "f:").
 *
 * @param string|null $resource The resource string to parse.
 * @return array|null An associative array `['rt' => string, 'field' => string]`
 *                    containing the extracted IDs, or null if the input is empty.
 *                    Returns IDs as strings as extracted.
 */
function _getRt_Ft($resource)
{
    if($resource){

        $vr = explode(" ", $resource);
        $resource_rt = substr($vr[0],2);
        $resource_field = $vr[1];
        if(strpos($resource_field,"f:")===0){
            $resource_field = substr($resource_field,2);
        }

        return array("rt"=>$resource_rt, "field"=>$resource_field);
    }

    return null;
}

/**
 * Calculates facet counts for a given query and facet field.
 *
 * This function determines the distinct values (or ranges for dates/numbers) for a specified facet field
 * based on an initial Heurist query. It then counts how many records match each of these distinct facet values
 * within the context of the original query.
 *
 * Handles various field types for faceting:
 * - Record Type (`rectype`, `typeid`, `typename`)
 * - Record Header Fields (`recTitle`, `id`, `owner`, `addedby`, `notes`, `url`, `tag`, `access`, `recAdded`, `recModified`)
 * - Detail Fields (`fieldid` parameter):
 *   - Date fields: Can group by month, year, decade, century, or provide min/max for a slider.
 *   - Enum/Reltype fields: Can group by first-level terms of a vocabulary.
 *   - Integer/Float fields: Can provide min/max for a slider or group by value.
 *   - Freetext fields: Can group by the first character of the value or by exact value.
 *
 * The function supports multi-step faceting where the counts are refined based on previously selected facet values.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param array $params An associative array of parameters:
 *                      - 'q': (array|string) The base Heurist query (JSON array or string).
 *                      - 'field': (string|int) The field to facet on. Can be a field ID or a keyword like "rectype", "title".
 *                      - 'type': (string, Optional) The data type of the facet field (e.g., "date", "enum", "freetext").
 *                                Used to determine faceting strategy.
 *                      - 'step': (int, Optional) The current step in a multi-step faceting process. Default 0.
 *                      - 'count_query': (array|string, Optional) The query used for counting, may include prior facet selections.
 *                      - 'facet_type': (int, Optional) Type of facet display influencing grouping (1:Select/Slider, 2:List Inline, 3:List Column).
 *                      - 'facet_groupby': (string, Optional) Grouping strategy (e.g., "year", "month" for dates; "firstlevel" for enums).
 *                      - 'vocabulary_id': (int, Optional) Vocabulary ID for enum "firstlevel" grouping.
 *                      - 'limit': (int, Optional) Limit for previewing facet values (not fully implemented in SQL generation).
 *                      - 'w': (string, Optional) Search domain (e.g., "all", "bookmark").
 *                      - 'qname': (string|int, Optional) Name or ID of a saved search, for error reporting context.
 *                      - 'needcount': (int, Optional) If 2, adjusts counting for related records (experimental).
 *                      - 'svs_id': (int, Optional) Saved search ID, passed through to response.
 *                      - 'request_id': (mixed, Optional) Request ID, passed through to response.
 *                      - 'facet_index': (mixed, Optional) Index of the facet, passed through to response.
 *                      - 'relation_direction': (string, Optional) Hint for counting related records ('relatedfrom', 'related_to').
 * @return array An associative array containing the facet data or an error object.
 *               On success: `['status'=>HEURIST_OK, 'data'=>array(...), 'svs_id'=>..., 'request_id'=>..., 'facet_index'=>..., 'q'=>..., 'count_query'=>...]`
 *               'data' is an array of arrays, each `[value, count, search_value_for_next_step]`.
 *               For date/numeric sliders, 'data' might be `['min'=>..., 'max'=>..., 'cnt'=>...]`.
 */
function recordSearchFacets($system, $params){

    $ft_Select = 1; // Facet type constant for select/slider
    $ft_List = 2;   // Facet type constant for list inline
    $ft_Column = 3;
    $suppress_counts = false;

    $mysqli = $system->getMysqli();

    //set savedSearchName for error messages
    $savedSearchName = '';
    if(is_numeric(@$params['qname']) && $params['qname'] > 0){ // retrieve extra details

        $query = 'SELECT svs_ID AS qID, svs_Name AS qName, svs_UGrpID as uID, ugr_Name as uName '
        . 'FROM usrSavedSearches '
        . 'INNER JOIN sysUGrps ON ugr_ID = svs_UGrpID '
        . 'WHERE svs_ID = ' . intval($params['qname']);

        $saved_search = mysql__select_row_assoc($mysqli, $query);

        if($saved_search !== null){
            $name = empty($saved_search['qName']) ? $saved_search['qID'] : $saved_search['qName'] . ' (# '. $saved_search['qID'] .')';
            $workgroup = $saved_search['uName'] . ' (# '. $saved_search['uID'] .')';//empty($saved_search['uName']) ? $saved_search['uID'] :
            $savedSearchName = '<br>'.MSG_SAVED_FILTER . $name . '<br>Workgroup: ' . $workgroup . '<br>';
        }else{
            $savedSearchName = MSG_SAVED_FILTER.$params['qname'].'<br>';
        }
    }else{
        $savedSearchName = @$params['qname'] ? MSG_SAVED_FILTER. $params['qname'] .'<br>' : '';
    }
    $savedSearchName .= empty($savedSearchName) ? '' : 'It is probably best to delete this saved filter and re-create it.<br>';

    $missingIds = false;

    if(@$params['q'] && @$params['field']){

        //{type:'freetext',step:0,field:1,facet_type:3,
        $currentUser = $system->getCurrentUser();
        $dt_type     = @$params['type'];
        $step_level  = intval(@$params['step']);
        $fieldid     = $params['field'];
        $count_query = @$params['count_query'];
        $facet_type =  intval(@$params['facet_type']);//0 direct search search, 1 - select/slider, 2 - list inline, 3 - list column
        $facet_groupby = @$params['facet_groupby'];//by first char for freetext, by year for dates, by level for enum
        $vocabulary_id = @$params['vocabulary_id'];//special case for groupby first level
        $limit         = @$params['limit'];//limit for preview

        //special parameter to avoid nested queries - it allows performs correct count for distinct target record type
        //besides it return correct field name to be used in count function
        $params['nested'] = (@$params['needcount']!=2);


        //do not include bookmark join
        if(!(strcasecmp(@$params['w'],'B') == 0  ||  strcasecmp(@$params['w'],BOOKMARK) == 0)){
            $params['w'] = NO_BOOKMARK;
        }

        if(!@$params['q']){
            return $system->addError(HEURIST_INVALID_REQUEST, $savedSearchName."Facet query search request. Missing query parameter");
        }

        if( $system->getUserId() > 0 ) {
            //use subset for initial search only
            $params['use_user_wss'] = @$params['step']==0;
        } else {
            $params['use_user_wss'] = false;
        }
        
        $recIDs = null;
        if(is_array($params['q']) && array_key_exists('ids',$params['q'])){
            $recIDs = $params['q']['ids'];
        }

        //get SQL clauses for current query
        $qclauses = get_sql_query_clauses_NEW($mysqli, $params, $currentUser);

        $select_field  = "";
        $detail_link   = "";
        $details_where = "";

        if($fieldid=="rectype" || $fieldid=="typeid"){
            $select_field = "r0.rec_RecTypeID";
        }elseif($fieldid=='typename'){

            $select_field = "rty_Name";
            $detail_link   = ", defRecTypes ";
            $details_where = " AND (rty_ID = r0.rec_RecTypeID) ";

        }elseif($fieldid=='recTitle' || $fieldid=='title'){
            $select_field = "r0.rec_Title";
            $dt_type = "freetext";
        }elseif($fieldid=='id' || $fieldid=='ids' || $fieldid=='recID'){
            $select_field = "r0.rec_ID";
            $dt_type = "integer";
        }elseif($fieldid=='owner'){
            $select_field = "r0.rec_OwnerUGrpID";
            $dt_type = "integer";
        }elseif($fieldid=='addedby'){
            $select_field = "r0.rec_AddedByUGrpID";
            $dt_type = "integer";
        }elseif($fieldid=='notes'){
            $select_field = "r0.rec_ScratchPad";
            $dt_type = "freetext";
        }elseif($fieldid=='url'){
            $select_field = "r0.rec_URL";
            $dt_type = "freetext";
        }elseif($fieldid=='tag'){

            $select_field = "tag_Text";
            $detail_link   = ", usrTags, usrRecTagLinks ";
            $details_where = " AND (rtl_TagID=tag_ID AND r0.rec_ID=rtl_RecID) ";

        }elseif($fieldid=='access'){
            $select_field = "r0.rec_NonOwnerVisibility";
            //$dt_type = "freetext";
        }elseif($fieldid=='recAdded' || $fieldid=='added'){
            $select_field = "r0.rec_Added";
        }elseif($fieldid=='recModified' || $fieldid=='modified'){
            $select_field = "r0.rec_Modified";
        }else{
            
            $compare_field = '';
            
            if(strpos($fieldid,',')>0 && getCommaSepIds($fieldid)!=null){
                $compare_field = 'IN ('.$fieldid.')';
            }elseif(intval($fieldid)>0){
                $compare_field = '='.intval($fieldid);
            }

            $select_field  = "dt0.dtl_Value";
            $detail_link   = ", recDetails dt0 ";
            $details_where = " AND (dt0.dtl_RecID=r0.rec_ID and dt0.dtl_DetailTypeID $compare_field) "
            ." AND (NULLIF(dt0.dtl_Value, '') is not null)";
            //$detail_link   = " LEFT JOIN recDetails dt0 ON (dt0.dtl_RecID=r0.rec_ID and dt0.dtl_DetailTypeID=".$fieldid.")";
            //$details_where = " and (dt0.dtl_Value is not null)";
        }

        $select_clause = "";
        $grouporder_clause = "";
        $rec_query = "";

        if($dt_type=='date'){

            //select valid dates
            $select_field = 'dt0.rdi_estMinDate';
            $detail_link = ', recDetailsDateIndex dt0';
            $details_where = " AND (dt0.rdi_estMinDate<2100 and dt0.rdi_RecID=r0.rec_ID and dt0.rdi_DetailTypeID $compare_field) ";

            //OLD ' AND (cast(getTemporalDateString('.$select_field.') as DATETIME) is not null ';
            //OLD .'OR (cast(getTemporalDateString('.$select_field.') as SIGNED) is not null  AND '
            //OLD .'cast(getTemporalDateString('.$select_field.') as SIGNED) !=0) )';

            //for dates we search min and max values to provide data to slider
            //facet_groupby   by year, day, month, decade, century
            if ($facet_groupby=='month') {


                $select_field = 'ROUND(dt0.rdi_estMinDate ,2)';

                //OLD $select_field = 'LAST_DAY(cast(getTemporalDateString('.$select_field.') as DATE))';

                $select_clause = "SELECT $select_field as rng, count(*) as cnt ";
                if($grouporder_clause==''){
                    $grouporder_clause = ' GROUP BY rng ORDER BY rng';
                }

            }elseif($facet_groupby=='year' || $facet_groupby=='decade' || $facet_groupby=='century') {

                $select_field = 'ROUND(dt0.rdi_estMinDate ,0)';

                if($facet_groupby=='decade'){
                    $select_field = $select_field.' DIV 10 * 10';
                }elseif($facet_groupby=='century'){
                    $select_field = $select_field.' DIV 100 * 100';
                }

                $select_clause = "SELECT $select_field as rng, count(*) as cnt ";
                if($grouporder_clause==''){
                    $grouporder_clause = ' GROUP BY rng ORDER BY rng';
                    //" GROUP BY $select_field ORDER BY $select_field";
                }

            }else{

                //concat('00',
                //OLD $select_field = "cast(if(cast(getTemporalDateString( $select_field ) as DATETIME) is null,"
                //OLD ."concat('00',cast(getTemporalDateString( $select_field ) as SIGNED),'-1-1'),"  //year
                //OLD ."concat('00',getTemporalDateString( $select_field ))) as DATETIME)";

                $select_clause = "SELECT min(dt0.rdi_estMinDate) as min, max(dt0.rdi_estMaxDate) as max, count(distinct r0.rec_ID) as cnt ";

                if($facet_type==$ft_Select){
                    $rec_query = "SELECT r0.rec_ID ";
                }
            }

        }

        elseif(($dt_type=="enum" || $dt_type=="reltype") && $facet_groupby=='firstlevel' && $vocabulary_id!=null){

            $params_enum = null;
            if($count_query){
                $params_enum = json_decode( json_encode($params), true);
            }

            $qclauses = get_sql_query_clauses_NEW($mysqli, $params_enum, $currentUser);


            //NOTE - it applies for VOCABULARY only (individual selection of terms is not applicable)

            // 1. get first level of terms using $vocabulary_id
            $first_level = getTermChildren($vocabulary_id, $system, true);//get first level for vocabulary



            // 2.  find all children as plain array  [[parentid, child_id, child_id....],.....]
            $terms = array();
            foreach ($first_level as $parentID){
                $children = getTermChildren($parentID, $system, false);//get first level for vocabulary
                array_unshift($children, $parentID);
                array_push($terms, $children);
            }

            //3.  find distinct count for recid for every set of terms
            $select_clause = "SELECT count(distinct r0.rec_ID) as cnt ";

            $data = array();

            foreach ($terms as $vocab){


                if($params_enum!=null){ //new way
                    $params_enum['q'] = __assignFacetValue($count_query, implode(',', $vocab) );

                    $qclauses2 = get_sql_query_clauses_NEW($mysqli, $params_enum, $currentUser);
                    $query =  $select_clause.$qclauses2['from'].SQL_WHERE.$qclauses2['where'];
                }else{
                    $d_where = $details_where.' AND ('.$select_field.SQL_IN.implode(',', $vocab).'))';
                    //count query
                    $query =  $select_clause.$qclauses['from'].$detail_link.SQL_WHERE.$qclauses['where'].$d_where;
                }

                $res = mysql__select($mysqli, $query);
                if (!$res){
                    return $system->addError(HEURIST_DB_ERROR, $savedSearchName
                        .'Facet query error(A). Parameters:'.print_r($params, true), $mysqli->error);
                    //'.$query.'
                }else{
                    $row = $res->fetch_row();

                    //firstlevel term id, count, search value (set of all terms)
                    if($row[0]>0){
                        array_push($data, array($vocab[0], $row[0], implode(',', $vocab) ));
                        $res->close();
                    }
                }

            }//for
            return array("status"=>HEURIST_OK, "data"=> $data, "svs_id"=>@$params['svs_id'],
                "request_id"=>@$params['request_id'], //'dbg_query'=>$query,
                "facet_index"=>@$params['facet_index'], 'q'=>$params['q'], 'count_query'=>$count_query );

        }
        //SLIDER
        elseif((($dt_type=="integer" || $dt_type=="float") && $facet_type==$ft_Select) || $dt_type=="year"){

            //if ranges are not defined there are two steps 1) find min and max values 2) create select case
            $select_field = "cast($select_field as DECIMAL)";

            $select_clause = "SELECT min($select_field) as min, max($select_field) as max, count(distinct r0.rec_ID) as cnt ";

        }
        else { //freetext and other if($dt_type==null || $dt_type=="freetext")

            if($dt_type=="integer" || $dt_type=="float"){

                $select_field = "cast($select_field as DECIMAL)";

            /*}elseif($dt_type=="enum"){
        
                $select_field = 'dt0.rdi_Value';
                $detail_link = ', recDetailsEnumIndex dt0';
                $details_where = " AND (dt0.rdi_RecID=r0.rec_ID and dt0.rdi_DetailTypeID $compare_field) ";
            */    
            }elseif($step_level==0 && $dt_type=="freetext"){

                $select_field = 'SUBSTRING(trim('.$select_field.'), 1, 1)';//group by first charcter                }
            }
            
            /*if($recIDs!=null && $dt_type=="enum"){

                $select_clause = "SELECT $select_field as rng, count(DISTINCT dt0.rdi_RecID) as cnt ";
                if($grouporder_clause==""){
                    $grouporder_clause = " GROUP BY $select_field ORDER BY $select_field";
                }
                $qclauses["from"] = ' FROM recDetailsEnumIndex dt0 ';
                $detail_link = '';
                $details_where = '';
                $qclauses["where"] = 'dt0.rdi_RecID IN ('.$recIDs.')';
                
            }else */
            if(@$params['needcount']!=2){

                $select_clause = "SELECT $select_field as rng, count(DISTINCT r0.rec_ID) as cnt ";
                if($grouporder_clause==""){
                    $grouporder_clause = " GROUP BY $select_field ORDER BY $select_field";
                }

            }else{ //count for related records (in both directions) if($params['needcount']==2)

                $tab = 'r0';
                while(strpos($qclauses["from"], 'Records '.$tab.'_0')>0){
                    $tab = $tab.'_0';
                }
                $recordID_field = $tab.'.rec_ID';
                
                if( strpos($qclauses["from"], 'recLinks rl0x1')>0 ){
                    if(@$params['relation_direction']=='relatedfrom'){
                        $recordID_field = 'rl0x1.rl_SourceID';
                    }elseif(@$params['relation_direction']=='related_to'){
                        $recordID_field = 'rl0x1.rl_TargetID';
                    }elseif(@$params['relation_direction']=='related'){
                        //not directional - suppress counts in ui
                        //due to complexity of query and it is not possible to find facet count for both-directions relationship
                        //@todo - possible solution use relmarker field id in recLinks
                        $suppress_counts = true;
                    }
                }
                
                $select_clause = "SELECT $select_field as rng, count(DISTINCT $recordID_field) as cnt ";

                if($grouporder_clause==""){
                    $grouporder_clause = " GROUP BY $select_field ORDER BY $select_field";
                }

            }
            /*else{ //for fields from related records - search distinc values only

            $select_clause = "SELECT DISTINCT $select_field as rng, 0 as cnt ";
            if($grouporder_clause==""){
            $grouporder_clause = " ORDER BY $select_field";
            }
            }*/

        }


        //count query
        if($grouporder_clause!='' && strpos($grouporder_clause,'ORDER BY')>0){  //mariadb hates "order by" in the same time with "group by"
            $grouporder_clause = substr($grouporder_clause,0,strpos($grouporder_clause,'ORDER BY')-1);
        }

        $query =  $select_clause.$qclauses["from"].$detail_link.SQL_WHERE.$qclauses["where"].$details_where.$grouporder_clause;
        $rec_query = !empty($rec_query) ? "{$rec_query}{$qclauses["from"]}{$detail_link} WHERE {$qclauses["where"]}{$details_where}" : '';

        /*
        if($limit>0){
        $query = $query.' LIMIT '.$limit;
        }
        */

/*  performance test        
$rustart = getrusage();
$time_start = microtime(true);         
*/

        $res = mysql__select($mysqli, $query);
  
/* performance test        
$ru = getrusage();
$time_end = microtime(true);
$s = USystem::rutime($ru, $rustart, "utime");
error_log(($time_end - $time_start)/60);
*/
        
        if (!$res){
            $response = $system->addError(HEURIST_DB_ERROR, $savedSearchName
                .'Facet query error(B). '.$query);// 'Parameters:'.print_r($params, true), $mysqli->error);
            //'.$query.'
        }else{
            $data = array();

            while ( $row = $res->fetch_row() ) {

                if((($dt_type=='integer' || $dt_type=='float') && $facet_type==$ft_Select)  ||
                (($dt_type=='year' || $dt_type=='date') && $facet_groupby==null)  ){
                    $third_element = $row[2];// slider - third parameter is COUNT for range

                    if(!$missingIds &&
                    (is_Array($params['q']) && !array_key_exists('ids', $params['q'])) &&
                    $row[2] != 0)
                    { // For range's histogram
                        $missingIds = true;
                    }
                }elseif($dt_type=="year" || $dt_type=="date") {

                    if($facet_groupby=='decade'){
                        $third_element = $row[0]+10;
                        //$row[0] = $row[0].'-01-01';
                    }elseif($facet_groupby=='century'){
                        $third_element = $row[0]+100;
                        //$row[0] = $row[0].'-01-01';
                    }

                    $third_element = $row[0];
                }elseif($step_level==0 && $dt_type=="freetext"){
                    $third_element = $row[0].'%';// first character
                }elseif($step_level>0 || $dt_type!='freetext'){
                    $third_element = $row[0];
                    if($dt_type=='freetext'){
                        $third_element = ('='.$third_element);
                    }
                }

                //value, count, second value(max for range) or search value for firstchar
                array_push($data, array($row[0], $row[1], $third_element ));

                // Retrieve list of record IDs, for additional functions (histogram)
                if(!empty($rec_query)){

                    $rec_ids = mysql__select_list2($mysqli, $rec_query, 'intval');
                    if(!empty($rec_ids)){
                        array_push($data, implode(',', $rec_ids));
                    }
                }
            }

            if($missingIds){

                $recid_query = "SELECT DISTINCT rec_ID " . $qclauses["from"] . $detail_link .
                SQL_WHERE . $qclauses["where"] . $details_where . $grouporder_clause;

                $recid_res = $mysqli->query($recid_query);
                if($recid_res){
                    $recids = array();

                    while($recid_row = $recid_res->fetch_row()){
                        array_push($recids, $recid_row[0]);
                    }

                    if(!empty($recids)){
                        $params['q']['ids'] = implode(',', $recids);
                    }

                    $recid_res->close();
                }
            }

            $response = array("status"=>HEURIST_OK, "data"=> $data, "svs_id"=>@$params['svs_id'],
                "request_id"=>@$params['request_id'], //'dbg_query'=>$query,
                "facet_index"=>@$params['facet_index'],
                'suppress_counts'=>$suppress_counts);
                //'q'=>$params['q'], 'count_query'=>$count_query );
            $res->close();
        }

    }else{
        $response = $system->addError(HEURIST_INVALID_REQUEST, $savedSearchName."Facet query parameters are invalid. Try to edit and correct this facet search");
    }

    return $response;
}

/**
 * Recursively traverses a query parameter array/object and replaces occurrences
 * of the placeholder string '$FACET_VALUE' with a specified substitution value.
 *
 * This is used in multi-step faceting to inject the selected value from a previous
 * facet step into the query for the next facet calculation.
 *
 * @param array|object $params The query parameters structure (array or object) to traverse. Passed by reference.
 * @param mixed $subs The substitution value to replace '$FACET_VALUE'.
 * @return array|object The modified query parameters structure.
 */
function __assignFacetValue(&$params, $subs){ // Note: PHP passes arrays by value unless explicitly by reference in call. Here it's by value.
    foreach ($params as $key => $value){
        if(is_array($value)){
            $params[$key] = __assignFacetValue($params[$key], $subs); // Corrected to modify the current level's array/object
        } elseif($value=='$FACET_VALUE'){
            $params[$key] = $subs;
             return $params;
        }
    }
    return $params;
}

/**
 * Generates data for a date histogram based on a given date range, interval, and set of records.
 *
 * It divides the date range into intervals and counts how many of the provided records
 * fall into each interval based on a specified date detail field.
 * The function dynamically adjusts the interval unit (year, month, day) and size
 * to aim for a reasonable number of bins (around 15-20).
 *
 * @param \hserv\System $system The Heurist system object.
 * @param array $range An array with two elements: [min_date_string, max_date_string].
 * @param int $interval The desired number of intervals or a starting interval size.
 * @param array|string $rec_ids An array or comma-separated string of record IDs to analyze.
 * @param int $dty_id The Detail Type ID of the date field to use for histogram calculation.
 * @param string $format The initial date format/unit to consider for intervals ("year", "month", "day"). Defaults to "year".
 * @param bool $is_between If true (default), a record counts in an interval if its date range is *within* the interval.
 *                         If false, it counts if its date range *overlaps* with the interval. (Note: current implementation detail for $is_between might differ)
 * @return array An associative array with 'status' and 'data'.
 *               'data' is an array of arrays, each `[interval_start_decimal_year, interval_end_decimal_year, count_of_records_in_interval]`.
 *               Returns an error array if parameters are invalid.
 */
function getDateHistogramData($system, $range, $interval, $rec_ids, $dty_id, $format="year", $is_between=true){

    $mysqli = $system->getMysqli();

    $date_int = null;
    $intervals = array();
    $count = 0;
    $add_day = new DateInterval('P1D');// Keep the class limits inclusive
    $is_years_only = ($format=='years_only');
    if($is_years_only) {$format='year';}

    // Validate Input
    if($rec_ids == null){
        return $system->addError(HEURIST_INVALID_REQUEST, "No record ids have been provided");
    }elseif(is_string($rec_ids) && strpos($rec_ids, ',') !== false){
        $rec_ids = explode(',', $rec_ids);
    }elseif(!is_array($rec_ids) && intval($rec_ids) > 0){
        $rec_ids = array($rec_ids);
    }elseif(!is_array($rec_ids)){
        return $system->addError(HEURIST_INVALID_REQUEST, "Record ids have been provided in an un-supported format<br>".$rec_ids);
    }

    if($dty_id == null || !is_numeric($dty_id)){
        return $system->addError(HEURIST_INVALID_REQUEST, "An invalid detail type id has been provided");
    }

    if(is_array($interval) || intval($interval) == 0){
        return $system->addError(HEURIST_INVALID_REQUEST, "An invalid interval has been provided");
    }

    $period = Temporal::getPeriod($range[0], $range[1]);
    if(!$period){
        return false;
    }

    $years = $period['years'];
    $months = @$period['months'];
    $days = @$period['days'];
    $fulldays = @$period['fulldays'];

    $s_date = new Temporal($range[0]);
    $e_date = new Temporal($range[1]);

    // Control variables
    $org_interval = $interval;
    $lower_level = false;
    $in_count = 0;

    if($format=='year'){

        if($months > 0 || $days > 0){ // Round up
            $years += 1;
        }

        $count = $years / $interval; // get the init number of classes

        $format = 'Y';

        if($count < 20){ // decrease interval size

            while($count < 20){

                $interval -= 5;
                if($interval <= 1){
                    $lower_level = true;
                    break;
                }
                $count = $years / $interval;

            }

            if($lower_level){
                return getDateHistogramData($system, $range, $org_interval, $rec_ids, $dty_id, 'month', $is_between);
            }
        }elseif($count > $interval){ // increase internal size

            while($count > $org_interval){

                $interval += 5;
                $count = $years / $interval;
            }
        }

        if($count <= 1){
            //$s_date->format($format), $e_date->format($format)
            array_push($intervals, array($s_date->getMinMax()[0], $e_date->getMinMax()[1], count($rec_ids)));
            return array("status"=>HEURIST_OK, "data"=>$intervals);
        }

        $date_int = new DateInterval('P'.$interval.'Y');
        $count = ceil($count);

    }elseif($format == 'month'){

        // Round up, +1 for any days and +12 for any years
        if($days > 0){
            $months += 1;
        }

        if($years > 0){
            $months += (12 * $years);
        }

        $count = $months / $interval; // get the init number of classes

        $format = 'd M Y';

        if($count < 15){ // decrease interval size

            while($count < 15){

                $interval -= 12;
                if($interval <= 1){
                    $lower_level = true;
                    break;
                }
                $count = $months / $interval;
            }

            if($lower_level){
                return getDateHistogramData($system, $range, $org_interval, $rec_ids, $dty_id, 'day', $is_between);
            }
        }elseif($count > $interval){ // increase internal size

            while($count > $org_interval){
                $interval += 12;
                $count = $months / $interval;

                $in_count++;
            }

            if($in_count >= 15){
                return getDateHistogramData($system, $range, $org_interval, $rec_ids, $dty_id, 'year', $is_between);
            }
        }

        if($count <= 1){
            //$s_date->format($format), $e_date->format($format)
            array_push($intervals, array($s_date->getMinMax()[0], $e_date->getMinMax()[1], count($rec_ids)));
            return array("status"=>HEURIST_OK, "data"=>$intervals);
        }

        $date_int = new DateInterval('P'.$interval.'M');
        $count = ceil($count);

    }
    else{  //DAYS

        $days = $fulldays>0?$fulldays:$days;

        $count = $days / $interval; // get the init number of classes

        $format = 'd M Y';

        if($count > $interval){ // increase internal size

            while($count > $org_interval){
                $interval += 30;
                $count = $days / $interval;

                $in_count++;
            }

            if($in_count >= 12){
                return getDateHistogramData($system, $range, $org_interval, $rec_ids, $dty_id, 'month', $is_between);
            }
        }elseif($count < 15){ // decrease interval size

            while($interval - 30 > 1 && $count < 1){

                $interval  = $interval - 30;
                if($interval <= 1){
                    $interval = 1;
                    break;
                }
                $count = $days / $interval;
            }
        }

        if($count <= 1){
            //$s_date->format($format), $e_date->format($format)
            array_push($intervals, array($s_date->getMinMax()[0], $e_date->getMinMax()[1], count($rec_ids)));
            return array("status"=>HEURIST_OK, "data"=>$intervals);
        }

        $date_int = new DateInterval('P'.$interval.'D');
        $count = ceil($count);

    }

    // Create date intervals (class limits)
    if($is_years_only){
        $lower = $s_date->getMinMax()[0];//in decimal
        $end_year = $e_date->getMinMax()[1];
        for($i = 0; $i < $count; $i++){

            $upper = $lower +  $interval;

            if($upper > $end_year){ // last class
                array_push($intervals, array($lower, ($end_year>0?($end_year+0.1231):$end_year), 0));
                break;
            }else{ // add class
                array_push($intervals, array($lower, $upper, 0));
            }

            $lower = $upper;
        }
    }else{
        try{
            $start_interval0 = Temporal::decimalToYMD($s_date->getMinMax()[0]);
            $start_interval = new DateTime($start_interval0);
        }catch(Exception $e){
            return $system->addError(HEURIST_ERROR, 'Wrong start of range '.$range[0].'  '.$s_date->getMinMax()[0].' '.$start_interval0);
        }
        try{
            $end_date = new DateTime(Temporal::decimalToYMD($e_date->getMinMax()[1]));
        }catch(Exception $e){
            return $system->addError(HEURIST_ERROR, 'Wrong end of range '.$range[1].'  '.$s_date->getMinMax()[1]);
        }

        for($i = 0; $i < $count; $i++){

            $lower = floatval($start_interval->format('Y.md'));
            $upper = new DateTime($start_interval->add($date_int)->format('Y-m-d'));

            if($upper > $end_date){ // last class
                array_push($intervals, array($lower, $e_date->getMinMax()[1], 0));
                break;
            }else{ // add class
                array_push($intervals, array($lower, floatval($upper->format('Y.md')), 0));
            }

            $start_interval->add($add_day);
        }
    }

    $sql = 'SELECT rdi_estMinDate, rdi_estMaxDate '
    .' FROM recDetailsDateIndex'
    .' WHERE rdi_estMaxDate<2100 AND rdi_RecID IN ('
    .implode(',', $rec_ids).") AND rdi_DetailTypeID = ".$dty_id;

    $res = mysql__select($mysqli, $sql);
    if(!$res){
        return $system->addError(HEURIST_DB_ERROR, "An SQL Error has Occurred => " . $mysqli->error);
    }

    while($row = $res->fetch_row()){ // cycle through all records

        $dt0 = $row[0];
        $dt1 = $row[1];

        $class_found = 0;

        for($k = 0; $k < count($intervals); $k++){ // cycle through classes, add to required count

            $lower = $intervals[$k][0];
            $upper = $intervals[$k][1];

            if($lower <= $dt0 && $dt1 <= $upper){
                $intervals[$k][2] += 1;
                if($is_between){ break; } // within - exclusive
                // else overlap - inclusive
                $class_found = 1;
            }elseif($class_found == 1){
                break;
            }
        }
    }

    return array("status"=>HEURIST_OK, "data"=>$intervals);

    //return $system->addError(HEURIST_UNKNOWN_ERROR, "An unknown error has occurred with attempting to retrieve the date data for DB => " . HEURIST_DBNAME . ", record ids => " . implode(',', $rec_ids));
}

/**
 * Searches recursively for all related records (via direct links or relationship records)
 * for a given set of initial record IDs and appends them to the input `$ids` array.
 *
 * This function explores links from the `$new_level_ids` (initially `$ids`).
 * For each record found, if it's not already in `$ids`, it's added.
 * The process then repeats for newly found IDs, up to `$max_depth`.
 * Temporary relationship records (type RT_RELATION, flagTemporary=1) are excluded.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param array &$ids An array of record IDs. Found related IDs will be merged into this array (passed by reference).
 * @param int $direction Search direction:
 *                       -  0: Both direct (outgoing) and reverse (incoming) links/relationships.
 *                       -  1: Direct links/relationships only.
 *                       - -1: Reverse links/relationships only.
 *                       Defaults to 0.
 * @param bool $no_relationships If true, only considers direct resource links (`rl_RelationID IS NULL`).
 *                               If false (default), considers both direct links and relationships.
 * @param int $depth Current recursion depth. Internal use. Defaults to 0.
 * @param int $max_depth Maximum recursion depth. Defaults to 1 (find immediately related records).
 * @param int $limit Maximum number of unique record IDs to accumulate in `$ids`. If 0, no limit. Defaults to 0.
 * @param array|null $new_level_ids Record IDs from the previous recursion level for which to find relations.
 *                                  Internal use. Defaults to initial `$ids`.
 * @param array|null $temp_ids Array of temporary relationship record IDs to exclude. Fetched on first call if null.
 *                             Internal use.
 * @return void Modifies `$ids` array directly.
 */
function recordSearchRelatedIds($system, &$ids, $direction=0, $no_relationships=false,
    $depth=0, $max_depth=1, $limit=0, $new_level_ids=null, $temp_ids=null){

    if($depth>=$max_depth) {return;}

    if($new_level_ids==null) {$new_level_ids = $ids;}

    if(!($direction==1||$direction==-1)){
        $direction = 0;
    }

    $mysqli = $system->getMysqli();

    $res1 = null; $res2 = null;

    if($temp_ids==null && !$no_relationships){
        //find temp relationship records (rt#1)
        $relRT = ($system->defineConstant('RT_RELATION')?RT_RELATION:0);
        $query = 'SELECT rec_ID FROM Records '
        .' where rec_RecTypeID='.$relRT.' AND rec_FlagTemporary=1';
        $temp_ids = mysql__select_list2($mysqli, $query);
    }

    if($direction>=0){

        //find all target related records
        $query = 'SELECT rl_TargetID, rl_RelationID FROM recLinks, Records '
        .' where rl_SourceID in ('.implode(',',$new_level_ids).') '
        .' AND rl_TargetID=rec_ID AND rec_FlagTemporary=0';
        if($no_relationships){
            $query = $query . ' AND rl_RelationID IS NULL';
        }

        $res = $mysqli->query($query);
        if ($res){
            $res1 = array();

            while ($row = $res->fetch_row()){

                $id = intval($row[1]);
                if($id>0){
                    if($temp_ids!=null && in_array($id, $temp_ids)){ //is temporary
                        continue;     //exclude temporary
                    }elseif(!in_array($id, $ids)){
                        array_push($res1, $id);//add relationship record
                    }
                }

                $id = intval($row[0]);
                if(!in_array($id, $ids)) {array_push($res1, $id);}
            }
            $res->close();
        }
    }

    if($direction<=0){
        $query = 'SELECT rl_SourceID, rl_RelationID FROM recLinks, Records where rl_TargetID in ('
        .implode(',',$new_level_ids).') '
        .' AND rl_SourceID=rec_ID AND rec_FlagTemporary=0';
        if($no_relationships){
            $query = $query . ' AND rl_RelationID IS NULL';
        }

        $res = $mysqli->query($query);
        if ($res){
            $res2 = array();

            while ($row = $res->fetch_row()){

                $id = intval($row[1]);
                if($id>0){
                    if($temp_ids!=null && in_array($id, $temp_ids)){ //is temporary
                        continue;
                    }elseif(!in_array($id, $ids)){
                        array_push($res2, $id);
                    }
                }

                $id = intval($row[0]);
                if(!in_array($id, $ids)) {array_push($res2, $id);}
            }
            $res->close();
        }
    }

    if(!isEmptyArray($res1) && is_array($res2)){
        $res = array_merge_unique($res1, $res2);
    }elseif(!isEmptyArray($res1)){
        $res = $res1;
    }else{
        $res = $res2;
    }

    //find new level
    if(!isEmptyArray($res)){
        $ids = array_merge_unique($ids, $res);

        if($limit>0 && count($ids)>=$limit){
            $ids = array_slice($ids,0,$limit);
        }else{
            recordSearchRelatedIds($system, $ids, $direction, $no_relationships, $depth+1, $max_depth, $limit, $res, $temp_ids);
        }

    }
}

/**
 * Finds all directly related (linked or via relationship records) records for a given set of record IDs.
 *
 * This function retrieves details about the links/relationships, including the target/source IDs,
 * relationship type (trmID), detail type of the link (dtID), and the relationship record ID itself (relationID).
 * Optionally, it can also fetch header information (title, type, owner, visibility) for all involved records.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param array|string $ids An array or comma-separated string of initial record IDs.
 * @param int $direction Search direction:
 *                       -  0: Both direct (outgoing) and reverse (incoming) links/relationships (default).
 *                       -  1: Direct links/relationships only (records pointed to by/related from initial IDs).
 *                       - -1: Reverse links/relationships only (records pointing to/related to initial IDs).
 * @param bool|string $need_headers If true (default), fetches header information for all unique records involved
 *                                  (initial, targets, sources, relationship records).
 *                                  If 'ids', returns only arrays of target/source IDs instead of full relation objects.
 *                                  If false, no headers are fetched beyond what's in the relation objects.
 * @param int $link_type Type of links to consider:
 *                       - 0: All links and relationships (default).
 *                       - 1: Only direct resource links (`rl_RelationID IS NULL`).
 *                       - 2: Only relationships (`rl_RelationID IS NOT NULL`).
 * @return array An associative array with 'status' and 'data'.
 *               'data' contains:
 *               - 'direct': Array of objects/IDs representing outgoing links/relationships.
 *                           Each object has `recID` (source), `targetID`, `trmID`, `dtID`, `relationID`,
 *                           optionally `dtl_StartDate`, `dtl_EndDate`.
 *               - 'reverse': Array of objects/IDs representing incoming links/relationships.
 *                            Each object has `recID` (target), `sourceID`, `trmID`, `dtID`, `relationID`,
 *                            optionally `dtl_StartDate`, `dtl_EndDate`.
 *               - 'headers': If `$need_headers` is true, an associative array mapping record ID to
 *                            `[title, recTypeID, ownerUGrpID, nonOwnerVisibility]`.
 *               Returns an error array if parameters are invalid or a DB error occurs.
 */
function recordSearchRelated($system, $ids, $direction=0, $need_headers=true, $link_type=0){

    if(!@$ids){
        return $system->addError(HEURIST_INVALID_REQUEST, 'Invalid search request: IDs not provided.');
    }

    $ids = prepareIds($ids);

    if(empty($ids)) {return array("status"=>HEURIST_OK, 'data'=>array());}//returns empty array

    if(!($direction==1||$direction==-1)){
        $direction = 0;
    }
    if(!($link_type>=0 && $link_type<3)){
        $link_type = 0;
    }
    if($link_type==2){ //relations only
        $sRelCond  = ' AND (rl_RelationID IS NOT NULL)';
    }elseif($link_type==1){ //links only
        $sRelCond  = ' AND (rl_RelationID IS NULL)';
    }else{
        $sRelCond = '';
    }

    $rel_ids = array();//relationship records (rt #1)

    $direct = array();
    $reverse = array();
    $headers = array();//record title and type for main record
    $direct_ids = array();//sources
    $reverse_ids = array();//targets

    $mysqli = $system->getMysqli();

    //query to find start and end date for relationship
    $system->defineConstant('DT_START_DATE');
    $system->defineConstant('DT_END_DATE');
    $query_rel = 'SELECT rec_ID, rec_Title, d2.dtl_Value t2, d3.dtl_Value t3 from Records '
    .' LEFT JOIN recDetails d2 on rec_ID=d2.dtl_RecID and d2.dtl_DetailTypeID='.(defined('DT_START_DATE')?DT_START_DATE:0)
    .' LEFT JOIN recDetails d3 on rec_ID=d3.dtl_RecID and d3.dtl_DetailTypeID='.(defined('DT_END_DATE')?DT_END_DATE:0)
    .SQL_WHERE.' rec_ID=';

    if($direction>=0){

        //find all target related records
        $query = 'SELECT rl_SourceID, rl_TargetID, rl_RelationTypeID, rl_DetailTypeID, rl_RelationID FROM recLinks '
        .SQL_WHERE.predicateId('rl_SourceID', $ids).$sRelCond.' order by rl_SourceID';

        $res = $mysqli->query($query);
        if (!$res){
            return $system->addError(HEURIST_DB_ERROR, "Search query error on related records. Query ".$query, $mysqli->error);
        }else{
            while ($row = $res->fetch_row()) {
                $relation = new stdClass();
                $relation->recID = intval($row[0]);
                $relation->targetID = intval($row[1]);
                $relation->trmID = intval($row[2]);// rl_RelationTypeID
                $relation->dtID  = intval($row[3]);// rl_DetailTypeID
                $relation->relationID  = intval($row[4]);//rl_RelationID

                if($relation->relationID>0) {

                    $vals = mysql__select_row($mysqli, $query_rel.$relation->relationID);
                    if($vals!=null){
                        $relation->relationTitle = $vals[1];
                        $relation->dtl_StartDate = $vals[2];
                        $relation->dtl_EndDate = $vals[3];
                    }
                }

                array_push($rel_ids, intval($row[1]));
                array_push($direct, $relation);
            }
            $res->close();
            if($need_headers=='ids'){
                $direct_ids = $rel_ids;
            }
        }

    }

    if($direction<=0){

        //find all reverse related records
        $query = 'SELECT rl_TargetID, rl_SourceID, rl_RelationTypeID, rl_DetailTypeID, rl_RelationID FROM recLinks '
        .SQL_WHERE.predicateId('rl_TargetID', $ids).$sRelCond.' order by rl_TargetID';


        $res = $mysqli->query($query);
        if (!$res){
            return $system->addError(HEURIST_DB_ERROR, 'Search query error on reverse related records. Query '.$query, $mysqli->error);
        }else{
            while ($row = $res->fetch_row()) {
                $relation = new stdClass();
                $relation->recID = intval($row[0]);
                $relation->sourceID = intval($row[1]);
                $relation->trmID = intval($row[2]);
                $relation->dtID  = intval($row[3]);
                $relation->relationID  = intval($row[4]);

                if($relation->relationID>0) {

                    $vals = mysql__select_row($mysqli, $query_rel.$relation->relationID);
                    if($vals!=null){
                        $relation->relationTitle = $vals[1];
                        $relation->dtl_StartDate = $vals[2];
                        $relation->dtl_EndDate = $vals[3];
                    }
                }

                array_push($reverse, $relation);
                array_push($rel_ids, intval($row[1]));
                array_push($reverse_ids, intval($row[1]));
            }
            $res->close();
        }

    }

    //find all rectitles and record types for main recordset AND all related records
    if($need_headers===true){

        $ids = array_merge($ids, $rel_ids);

        $query = 'SELECT rec_ID, rec_Title, rec_RecTypeID, rec_OwnerUGrpID, rec_NonOwnerVisibility from Records '
        .' WHERE rec_ID IN ('.implode(',',$ids).')';
        $res = $mysqli->query($query);
        if (!$res){
            return $system->addError(HEURIST_DB_ERROR, "Search query error on search related. Query ".$query, $mysqli->error);
        }else{

            while ($row = $res->fetch_row()) {
                $headers[$row[0]] = array($row[1], $row[2], $row[3], $row[4]);
            }
            $res->close();
        }

    }

    if($need_headers==='ids'){
        $response = array("status"=>HEURIST_OK,
            "data"=> array("direct"=>$direct_ids, "reverse"=>$reverse_ids, "headers"=>$headers));
    }else{
        $response = array("status"=>HEURIST_OK,
            "data"=> array("direct"=>$direct, "reverse"=>$reverse, "headers"=>$headers));
    }


    return $response;

}



/**
 * Counts how many source records of a specific type (`$source_rty_ID`) are linked
 * to target records of another specific type (or list of types, `$target_rty_ID`)
 * via a particular detail field (`$dty_ID`).
 *
 * The result is grouped by the target record ID, providing a count of source records linked to each target.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int $source_rty_ID The Record Type ID of the source records.
 * @param int|array $target_rty_ID The Record Type ID (or an array of IDs) of the target records.
 * @param int|null $dty_ID (Optional) The Detail Type ID of the pointer field in the source records.
 *                         If 0 or null, links via any detail type are considered (though this might be unintended if rl_DetailTypeID=0 has special meaning).
 * @return array An associative array with 'status' and 'data'.
 *               'data' is an associative array where keys are target record IDs (`rl_TargetID`)
 *               and values are the counts of source records linked to them.
 *               Returns an error array if parameters are invalid or a DB error occurs.
 */
function recordLinkedCount($system, $source_rty_ID, $target_rty_ID, $dty_ID){

    if(!( (is_array($target_rty_ID) || (is_numeric($target_rty_ID) && $target_rty_ID > 0)) && (is_numeric($source_rty_ID) && $source_rty_ID > 0) )){
        return $system->addError(HEURIST_INVALID_REQUEST, 'Invalid search request. Source and target record type IDs must be positive integers (target can be an array).');
    }

    $query = 'SELECT rl_TargetID, count(rl_SourceID) as cnt FROM recLinks, ';

    if(is_array($target_rty_ID)){
        $query = $query.'Records r1 WHERE rl_TargetID in ('.implode(',',$target_rty_ID).')';
    }else{
        $query = $query.'Records r1,  Records r2 '
        .'WHERE rl_TargetID=r2.rec_ID AND r2.rec_RecTypeID='.$target_rty_ID;

    }

    $query = $query.' AND rl_SourceID=r1.rec_ID AND r1.rec_RecTypeID='.$source_rty_ID;
    if($dty_ID>0){
        $query = $query.' AND rl_DetailTypeID='.$dty_ID;
    }
    $query = $query.' GROUP BY rl_TargetID ORDER BY cnt DESC';

    /*
    use hdb_MPCE_Mapping_Print_Charting_Enlightenment;
    SELECT rl_TargetID, count(rl_SourceID) FROM recLinks, Records r1,  Records r2
    WHERE rl_SourceID=r1.rec_ID AND r1.rec_RecTypeID=55
    AND rl_TargetID=r2.rec_ID AND r2.rec_RecTypeID=56
    AND rl_DetailTypeID=955
    group by rl_TargetID
    */
    $mysqli = $system->getMysqli();

    $list = mysql__select_assoc2($mysqli, $query);

    if (!$list && $mysqli->error){
        return $system->addError(HEURIST_DB_ERROR, 'Search query error on related records. Query '.$query, $mysqli->error);
    }else{
        return array("status"=>HEURIST_OK, "data"=> $list);
    }
}


/**
 * Retrieves all explicit view and edit permissions for a given set of record IDs.
 *
 * Queries the `usrRecPermissions` table to find group-based permissions (`rcp_Level` = 'view' or 'edit')
 * associated with the provided record IDs.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param array|string $ids An array or comma-separated string of record IDs.
 * @return array An associative array with 'status', 'view', and 'edit'.
 *               'view' and 'edit' are associative arrays where keys are record IDs,
 *               and values are arrays of group IDs (`rcp_UGrpID`) granted that permission level.
 *               Returns an error array if IDs are not provided or a DB error occurs.
 */
function recordSearchPermissions($system, $ids){
    if(!@$ids){
        return $system->addError(HEURIST_INVALID_REQUEST, "Invalid search request: Record IDs not provided.");
    }

    $ids = prepareIds($ids);

    $permissions = array();
    $mysqli = $system->getMysqli();

    $query = 'SELECT rcp_RecID, rcp_UGrpID, rcp_Level FROM usrRecPermissions '
    .' WHERE rcp_RecID IN ('.implode(",", $ids).')';
    $res = $mysqli->query($query);
    if (!$res){
        return $system->addError(HEURIST_DB_ERROR, "Search query error on search permissions. Query ".$query, $mysqli->error);
    }else{

        $response = array("status"=>HEURIST_OK, "view"=>array(), "edit"=>array());

        while ($row = $res->fetch_row()) {
            if(@$response[$row[2]][$row[0]]){
                array_push($response[$row[2]][$row[0]], $row[1]);
            }else{
                $response[$row[2]][$row[0]] = array($row[1]);
            }
        }
        $res->close();

        return $response;
    }

}

/**
 * Returns an SQL WHERE clause snippet for record visibility based on a user/group ID.
 * Note: This function is marked as NOT USED in the original file comments.
 * It seems to construct conditions similar to those applied in `get_sql_query_clauses_NEW`.
 *
 * @see \hserv\records\search\get_sql_query_clauses_NEW() For current visibility logic.
 * @see \hserv\structure\DbDefRecTypes::_getRecordOwnerConditions() For similar logic within DbDefRecTypes.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int $ugrID The user or group ID for whom to determine visibility conditions.
 * @return string An SQL WHERE clause snippet string.
 */
function recordGetOwnerVisibility($system, $ugrID){

    $is_db_owner = ($ugrID==2); // User ID 2 is typically the database owner/superadmin.

    $where2 = '';

    if(!$is_db_owner){

        $where2 = '(rec_NonOwnerVisibility="public")';// in ("public","pending")

        if($ugrID>0){ //logged in
            $mysqli = $system->getMysqli();
            $wg_ids = user_getWorkgroups($this->mysqli, $ugrID);
            array_push($wg_ids, $ugrID);
            array_push($wg_ids, 0);// be sure to include the generic everybody workgroup

            //$this->from_clause = $this->from_clause.' LEFT JOIN usrRecPermissions ON rcp_RecID=r0.rec_ID ';

            $where2 = $where2.' OR (rec_NonOwnerVisibility="viewable")';
            // and (rcp_UGrpID is null or rcp_UGrpID in ('.join(',', $wg_ids).')))';

            $where2 = '( '.$where2.' OR rec_OwnerUGrpID in (' . join(',', $wg_ids).') )';
        }
    }

    return $where2;

}

/**
 * Retrieves the first relationship type ID (`rl_RelationTypeID`) found for a direct relationship
 * from a source record to a target record.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int $sourceID The `rec_ID` of the source record.
 * @param int $targetID The `rec_ID` of the target record.
 * @return int|null The `rl_RelationTypeID` if a relationship is found, otherwise null.
 *                  Returns null also on database query error.
 */
function recordGetRelationshipType($system, $sourceID, $targetID ){

    $mysqli = $system->getMysqli();
    $sourceID = intval($sourceID);
    $targetID = intval($targetID);

    $query = 'SELECT rl_RelationTypeID FROM recLinks '
           .'WHERE rl_SourceID='.$sourceID.' AND rl_TargetID='.$targetID.' AND rl_RelationID IS NOT NULL LIMIT 1';
    $res = $mysqli->query($query);

    if (!$res){
        // Optionally log error: $system->addError(HEURIST_DB_ERROR, "Search query error on get relationship type", $mysqli->error);
        return null;
    } else {
        if($row = $res->fetch_row()) {
            $res->close();
            return (int)$row[0];
        } else {
            $res->close();
            return null;
        }
    }
}

/**
 * Retrieves all unique record IDs and their types for records linked to or from a given record ID.
 * This includes both direct resource links and records participating in relationships.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int $recordID The `rec_ID` of the central record.
 * @return array|false An associative array mapping linked/related record IDs to their `rec_RecTypeID`,
 *                     or false if a database error occurs.
 */
function recordGetLinkedRecords($system, $recordID){
    $recordID = intval($recordID);
    $mysqli = $system->getMysqli();

    // Get records targeted by $recordID (outgoing links/relations)
    $query1 = 'SELECT DISTINCT rl.rl_TargetID, r.rec_RecTypeID FROM recLinks rl JOIN Records r ON rl.rl_TargetID = r.rec_ID WHERE rl.rl_SourceID='.$recordID;
    $targets = mysql__select_assoc2($mysqli, $query1);
    if($targets===null){ // mysql__select_assoc2 returns null on error
        $system->addError(HEURIST_DB_ERROR, "Search query error for target linked/related records. Query: ".$query1, $mysqli->error);
        return false;
    }

    // Get records that target $recordID (incoming links/relations)
    $query2 = 'SELECT DISTINCT rl.rl_SourceID, r.rec_RecTypeID FROM recLinks rl JOIN Records r ON rl.rl_SourceID = r.rec_ID WHERE rl.rl_TargetID='.$recordID;
    $sources = mysql__select_assoc2($mysqli, $query2);
    if($sources===null){
        $system->addError(HEURIST_DB_ERROR, "Search query error for source linked/related records. Query: ".$query2, $mysqli->error);
        return false;
    }

    // Merge results, ensuring uniqueness by record ID. $sources will overwrite $targets on key collision.
    return array_merge($targets, $sources);
}


/**
 * Retrieves relationship records (typically Record Type 1) that link a specified source and target record.
 *
 * If `$search_request` is null or defines 'detail' other than 'ids', it performs a `recordSearch`
 * for the found relationship record IDs to return full details.
 * If `$search_request['detail'] == 'ids'`, it returns just an array of the relationship record IDs.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int $sourceID The `rec_ID` of the source record in the relationship. Can be 0 to wildcard.
 * @param int $targetID The `rec_ID` of the target record in the relationship. Can be 0 to wildcard.
 * @param array|null $search_request (Optional) A `recordSearch` parameter array to customize the output
 *                                   for the relationship records. If 'detail' is 'ids', returns only IDs.
 *                                   If null, defaults to fetching 'detail' for relationship records.
 * @return array|false An array of relationship record IDs, or a `recordSearch` result array,
 *                     or false on database error.
 */
function recordGetRelationship($system, $sourceID, $targetID, $search_request=null){

    $mysqli = $system->getMysqli();
    $sourceID = intval($sourceID);
    $targetID = intval($targetID);

    $query = 'SELECT rl_RelationID FROM recLinks WHERE rl_RelationID IS NOT NULL';

    if($sourceID>0){
        $query = $query.' AND rl_SourceID='.$sourceID;
    }
    if($targetID>0){
        $query = $query.' AND rl_TargetID='.$targetID;
    }

    $res = $mysqli->query($query);
    if (!$res){
        return $system->addError(HEURIST_DB_ERROR, "Search query error on relationship records for source-target. Query ".$query, $mysqli->error);
    }else{
        $ids = array();
        while ($row = $res->fetch_row()) {
            array_push($ids, intval($row[0]));
        }
        $res->close();

        if($search_request==null){
            $search_request = array('q'=>'ids:'.implode(',', $ids), 'detail'=>'detail');
        }else{
            $search_request['q'] = 'ids:'.implode(',', $ids);
            if(@$search_request['detail']=='ids'){
                return $ids;
            }elseif(!@$search_request['detail']){
                $search_request['detail'] = 'detail';//returns all details
            }
        }

        return recordSearch($system, $search_request);
    }


}

/**
 * Recursively finds a parent record of a specific target record type.
 *
 * Starting from `$rec_ID`, it checks if the record is of `$target_recTypeID`.
 * If not, it queries `recLinks` to find records that link *to* the current `$rec_ID`
 * via one of the `$allowedDetails` (detail type IDs representing parent-child links).
 * It then takes the first parent found and recursively calls itself.
 *
 * This is typically used to find a main "container" or "home" record in a CMS-like structure.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int $rec_ID The starting record ID from which to find the parent.
 * @param int $target_recTypeID The Record Type ID of the parent to find.
 * @param array|null $allowedDetails An array of Detail Type IDs that constitute a "parent" link.
 *                                   If null, any `rl_DetailTypeID IS NOT NULL` is considered (which might be too broad).
 * @param int $level Current recursion level to prevent infinite loops (max 5 levels).
 * @return int|false The `rec_ID` of the found parent record of `$target_recTypeID`,
 *                   or false if not found, an error occurs, or max recursion depth is hit.
 */
function recordSearchFindParent($system, $rec_ID, $target_recTypeID, $allowedDetails, $level=0){
    $rec_ID = intval($rec_ID);
    $target_recTypeID = intval($target_recTypeID);

    $query = 'SELECT rec_RecTypeID from Records WHERE rec_ID='.$rec_ID;
    $rtype = mysql__select_value($system->getMysqli(), $query);

    if($rtype==$target_recTypeID){
        return $rec_ID;
    }

    $query = 'SELECT rl_SourceID FROM recLinks '
    .'WHERE rl_TargetID='.$rec_ID;
    if(is_array($allowedDetails)){
        $query = $query.' AND rl_DetailTypeID IN ('.implode(',',$allowedDetails).')';
    }else{
        $query = $query.' AND rl_DetailTypeID IS NOT NULL';
    }

    $parents = mysql__select_list2($system->getMysqli(), $query);
    if(!isEmptyArray($parents)){
        if($level>5){
            $system->addError(HEURIST_ERROR, 'Cannot find parent CMS Home record. It appears that menu items refers recursively');
            return false;
        }

        $parent_ID = $parents[0];

        if(count($parents)>1 && defined('DT_CMS_PAGETYPE')){ //more that one parent
            $webpage = ConceptCode::getTermLocalID('2-6254');
            foreach($parents as $rec_ID){
                $isWebPage = false;
                $rec = recordSearchByID($system, $rec_ID, array(DT_CMS_PAGETYPE), 'rec_ID,rec_RecTypeID');
                if(@$rec['rec_RecTypeID']==RT_CMS_MENU && is_array(@$rec['details'][DT_CMS_PAGETYPE])){
                    //get term id by concept code
                    $val = recordGetField($rec, DT_CMS_PAGETYPE);
                    $isWebPage = ($val==$webpage);//standalone
                }
                if(!$isWebPage){
                    $parent_ID = $rec_ID;
                    break;
                }
            }
        }

        return recordSearchFindParent($system, $parent_ID, $target_recTypeID, $allowedDetails, $level+1);
    }else{
        $system->addError(HEURIST_ERROR, 'Cannot find parent CMS Home record');
        return false;
    }
}
/**
 * Recursively gathers all record IDs that form a menu structure, starting from initial menu item(s),
 * and then optionally fetches full details for these records.
 *
 * This function is used to build website navigation menus. It traverses records linked via
 * `DT_CMS_MENU` or `DT_CMS_TOP_MENU` detail fields.
 *
 * If `$find_root_menu` is true and this is the initial call (`empty($result)`):
 * - If `menuitems[0]` is 0, it finds the first `RT_CMS_HOME` record.
 * - If `menuitems[0]` is a specific record, it attempts to find its parent `RT_CMS_HOME` record,
 *   unless the record itself is a standalone CMS page (pagetype '2-6254').
 *
 * The gathered record IDs are accumulated in the `$result` array (passed by reference).
 * Finally, if `$ids_only` is false, it performs a `recordSearch` to get details for all
 * accumulated menu item records.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param array $menuitems An array of record IDs representing the current level of menu items to process.
 * @param array &$result Accumulator array (passed by reference) storing all unique record IDs found in the menu structure.
 * @param bool $find_root_menu If true on the initial call, special logic is applied to find the root
 *                             CMS Home record or handle standalone pages. Default false.
 * @param bool $ids_only If true, the function will return only the array of accumulated record IDs
 *                       (from the modified `$result` array, though the direct return is from `recordSearch`
 *                       or an error, so the caller should use `$result`).
 *                       If false (default), it returns a full `recordSearch` result for all menu items.
 * @return array|false If `$ids_only` is true and it's the root call, it should ideally return `$result`,
 *                     but current logic returns `recordSearch` output or error.
 *                     If `$ids_only` is false, returns the result of `recordSearch` for all menu items,
 *                     or an error array from `system->getError()` if root finding fails.
 */
function recordSearchMenuItems($system, $menuitems, &$result, $find_root_menu=false, $ids_only=false){

    $menuitems = prepareIds($menuitems, true); // Ensure $menuitems is an array of unique positive integers
    $isRoot = (empty($result)); // Is this the initial call?
    
    if($isRoot && $find_root_menu){

        //if root record is menu - we have to find parent cms home
        if(count($menuitems)==1){
            if($menuitems[0]==0){
                //find ANY first home record
                $response = recordSearch($system, array('q'=>'t:'.RT_CMS_HOME, 'detail'=>'ids', 'w'=>'a'));

                if($response['status'] == HEURIST_OK  && !isEmptyArray(@$response['data']['records']) ){
                    $res = $response['data']['records'][0];
                }else{
                    return $system->addError(HEURIST_ERROR,
                        'Cannot find website home record');
                }

            }else{
                $root_rec_id = $menuitems[0];
                $isWebPage = false;

                if($system->defineConstant('DT_CMS_PAGETYPE')){
                    //check that this is single web page (for embed)
                    $rec = recordSearchByID($system, $root_rec_id, array(DT_CMS_PAGETYPE), 'rec_ID,rec_RecTypeID');
                    if(@$rec['rec_RecTypeID']==RT_CMS_MENU && is_array(@$rec['details'][DT_CMS_PAGETYPE])){
                        //get term id by concept code
                        $val = recordGetField($rec, DT_CMS_PAGETYPE);
                        $isWebPage = ($val==ConceptCode::getTermLocalID('2-6254'));//standalone
                    }
                }

                if($isWebPage){
                    
                    $details = array(DT_NAME,DT_SHORT_SUMMARY,DT_CMS_TARGET,DT_CMS_CSS,
                            DT_CMS_PAGETITLE,DT_EXTENDED_DESCRIPTION,DT_CMS_TOP_MENU,DT_CMS_MENU,DT_THUMBNAIL);
                    if(defined('DT_CMS_TOPMENUSELECTABLE')){
                        $details[] = DT_CMS_TOPMENUSELECTABLE;    
                    }
                    if(defined('DT_CMS_MENU_HOME')){
                        $details[] = DT_CMS_MENU_HOME;    
                    }
                    return recordSearch($system, array('q'=>array('ids'=>$root_rec_id),
                        'detail'=>$details,
                        'w'=>'e', 'cms_cut_description'=>1));
                }else{
                    //find parent home record
                    $res = recordSearchFindParent($system,
                        $root_rec_id, RT_CMS_HOME, array(DT_CMS_MENU,DT_CMS_TOP_MENU));
                }
            }
            if($res===false){
                return $system->getError();
            }else{
                $menuitems[0] = $res;
            }
        }
    }

    $rec_IDs = array();

    $system->defineConstant('DT_CMS_MENU');
    $system->defineConstant('DT_CMS_TOP_MENU');
    
    foreach ($menuitems as $rec_ID){
        if(!in_array($rec_ID, $result)){ //to avoid recursion
            array_push($result, $rec_ID);
            array_push($rec_IDs, $rec_ID);
        }
    }

    if(!empty($rec_IDs)){
        /*
        $query = 'SELECT dtl_Value FROM recDetails WHERE dtl_RecID in ('
        .implode(',',$rec_IDs).') AND (dtl_DetailTypeID='.DT_CMS_MENU
        .' OR dtl_DetailTypeID='.DT_CMS_TOP_MENU.')';
        */
        $query = 'SELECT rl_TargetID FROM recLinks WHERE rl_SourceID in ('
        .implode(',',$rec_IDs).') AND (rl_DetailTypeID='.DT_CMS_MENU
        .' OR rl_DetailTypeID='.DT_CMS_TOP_MENU.')';

        $menuitems2 = mysql__select_list2($system->getMysqli(), $query);

        $menuitems2 = prepareIds( $menuitems2 );

        if(!isEmptyArray($menuitems2)){
            recordSearchMenuItems($system, $menuitems2, $result);
        }
    }elseif($isRoot) {
        return $system->addError(HEURIST_INVALID_REQUEST, 'Root record id is not specified');
    }


    if($isRoot){
        if($ids_only){
            return $result;
        }else{
            
            $details = array(DT_NAME,DT_SHORT_SUMMARY,DT_CMS_TARGET,DT_CMS_CSS,DT_CMS_PAGETITLE,DT_EXTENDED_DESCRIPTION,
                    DT_CMS_TOP_MENU,DT_CMS_MENU,DT_THUMBNAIL);
            if(defined('DT_CMS_TOPMENUSELECTABLE')){
                $details[] = DT_CMS_TOPMENUSELECTABLE;    
            }
            if(defined('DT_CMS_MENU_HOME')){
                $details[] = DT_CMS_MENU_HOME;    
            }
            
            //return recordset
            return recordSearch($system, array('q'=>array('ids'=>$result),
                'detail'=>$details, //'detail'
                'w'=>'e', 'cms_cut_description'=>1));
        }
    }

}

/**
 * Recursively gathers all record IDs in a menu structure and organizes them into a tree.
 *
 * This function is an alternative to `recordSearchMenuItems`. It first collects all unique
 * record IDs forming the menu structure into `$resultIds`. Then, if it's the root call,
 * it fetches details for these records and uses `recordSearchMenuItemsTree` to build
 * a hierarchical array representing the menu tree.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param array $menuitems An array of record IDs for the current level of menu items.
 * @param array &$resultIds Accumulator array (passed by reference) for all unique record IDs in the menu.
 * @param bool $isRoot True if this is the initial (root) call to the function.
 * @return array|void If it's the root call, returns the constructed menu tree (an associative array
 *                    where keys are record IDs and values are arrays of their sub-items, recursively).
 *                    Otherwise (non-root call), modifies `$resultIds` by reference and returns nothing.
 */
function recordSearchMenuItems2($system, $menuitems, &$resultIds, $isRoot){

    $resultTree = array(); // Used only if $isRoot is true, to build the final tree.
    
    $current_level_ids_prepared = prepareIds($menuitems, true);
    $new_ids_for_this_level = array(); // IDs to process in this iteration

    foreach ($current_level_ids_prepared as $rec_ID){
        if(!in_array($rec_ID, $resultIds)){ // Avoid recursion and redundant processing
            array_push($resultIds, $rec_ID);
            array_push($new_ids_for_this_level, $rec_ID);
            
            if($isRoot){ // Initialize a spot in the tree for root items
                $resultTree[$rec_ID] = [];
            }
        }
    }
    
    if(!empty($new_ids_for_this_level)){
        // Fetch sub-menu items linked via DT_CMS_MENU or DT_CMS_TOP_MENU
        $query = 'SELECT rl_TargetID FROM recLinks WHERE rl_SourceID IN ('
               . implode(',', $new_ids_for_this_level) . ') AND (rl_DetailTypeID=' . DT_CMS_MENU
               . ' OR rl_DetailTypeID=' . DT_CMS_TOP_MENU . ')';

        $submenu_items_ids = mysql__select_list2($system->getMysqli(), $query);
        $submenu_items_ids_prepared = prepareIds($submenu_items_ids, true); // Sanitize and unique

        if(!isEmptyArray($submenu_items_ids_prepared)){
            recordSearchMenuItems2($system, $submenu_items_ids_prepared, $resultIds, false); // Recursive call for sub-items
        }
    }

    if($isRoot){
            // After all IDs are collected, fetch details for all of them            
            $detailIds = array(DT_NAME, DT_CMS_TOP_MENU, DT_CMS_MENU, DT_THUMBNAIL);
            if(defined('DT_CMS_MENU_FORMAT')){
                array_push($detailIds, DT_CMS_MENU_FORMAT);
            }
            //find details
            $all_menu_records_details = recordSearchDetailsForRecIds($system, $resultIds, $detailIds, false);
            
        // Build the tree structure
        foreach($resultTree as $root_item_ID => $subs){ // Iterate only through initial root items
            recordSearchMenuItemsTree($root_item_ID, $resultTree[$root_item_ID], $all_menu_records_details);
        }
            
        //on final step we assign full records info to $resultIds
        $resultIds = $all_menu_records_details;
            
        return $resultTree; 
    }
    // Non-root calls modify $resultIds by reference and don't return a value.
}

/**
 * Recursively builds a hierarchical menu tree for a given menu item.
 *
 * This function takes a menu item ID (`$item_ID`) and populates its entry
 * in `$resultTree` with its sub-menu items. It uses pre-fetched `$all_records_details`
 * which contains details (including `DT_CMS_TOP_MENU` and `DT_CMS_MENU` links) for all
 * records in the menu structure.
 *
 * @param int $item_ID The current menu item's record ID to process.
 * @param array &$resultTree The portion of the menu tree corresponding to `$item_ID`'s children. Passed by reference.
 * @param array $all_records_details An associative array where keys are record IDs and values are their
 *                                   details (including links for sub-menus).
 * @return void Modifies `$resultTree` by reference.
 */
function recordSearchMenuItemsTree($item_ID, &$resultTree, $all_records_details){
    
    // Find the current item's details in the pre-fetched list
    $record_details = $all_records_details[$item_ID] ?? null;
    if (!$record_details) return;

    // Get sub-item IDs from either DT_CMS_TOP_MENU or DT_CMS_MENU details
    $subitems_ids = @$record_details[DT_CMS_TOP_MENU] ?? @$record_details[DT_CMS_MENU];
    
    if(is_array($subitems_ids)){
        foreach($subitems_ids as $subitem_dtl_id => $subitem_rec_id){ // Details are [dtl_ID => rec_ID]
            // Ensure the subitem_rec_id is valid and not already processed in a way that causes loops (though primary check is in recordSearchMenuItems2)
            if(!array_key_exists($subitem_rec_id, $resultTree)) { // Add if not already a key at this level
                 $resultTree[$subitem_rec_id] = array(); // Initialize children array for this sub-item
            }
            // Recursively build the tree for this sub-item
            recordSearchMenuItemsTree($subitem_rec_id, $resultTree[$subitem_rec_id], $all_records_details);
        }
    }
}


//-----------------------------------------------------------------------
/**
 * Performs a search for Heurist records based on a wide range of parameters.
 *
 * This is the main search function, capable of handling:
 * - Simple keyword queries (old plain text format or new JSON format).
 * - Searches by saved filter ID (`svs:ID`).
 * - Complex rule-based searches involving multiple dependent queries (`rules` parameter).
 * - Query sets that are intersected or merged (`queryset` parameter).
 * - Various output detail levels (`detail` parameter: ids, count, header, detail, timemap, complete).
 * - Pagination (`limit`, `offset`), sorting (`s` or `sortby` in q).
 * - Search domains (`w`: all, bookmark).
 * - User context for permissions and working subsets.
 * - Retrieval of related records and relationship metadata.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param array $params An associative array of search parameters. Key parameters include:
 *    **Query Definition & Rules:**
 *    - 'q': (string|array) The primary query. Can be a plain text string (legacy),
 *           a JSON query string/array, or "svs:ID" to load a saved search.
 *    - 'rules': (array|string, Optional) JSON array/string defining rule-based searches. Rules are dependent queries.
 *    - 'rulesonly': (int, Optional) Controls output for rule-based searches:
 *                   0: Keep original query results + all rule results.
 *                   1: Return only results from all rule extensions.
 *                   2: Return only results from the last rule extension.
 *                   3: Keep original query results + last rule results.
 *    - 'getrelrecs': (int, Optional) If 1, also fetches relationship records (type 1) involved in rule-based 'related' queries.
 *    - 'topids': (array|string, Optional) Comma-separated string or array of record IDs used as a base for rule queries.
 *    - 'queryset': (array|string, Optional) JSON array/string of multiple queries to be executed.
 *    - 'intersect': (int, Optional) If 1, results from `queryset` are intersected (AND). If 0 (default), results are merged (OR).
 *    - 'parentquery': (array, Optional) SQL clauses from a parent query, used for context in sub-queries (internal use).
 *
 *    **Search Domain & Context:**
 *    - 'w': (string, Optional) Search domain: 'a'/'all' (default), 'b'/'bookmark'.
 *    - 'publiconly': (int, Optional) If 1, searches only public records, ignoring user context.
 *    - 'use_user_wss': (bool, Optional) If true and user is logged in, filters by user's working subset.
 *
 *    **Output Control & Formatting:**
 *    - 'detail': (string|array, Optional) Specifies the level of detail in the output. Default 'ids'.
 *                - 'ids': Returns only an array of record IDs.
 *                - 'count': Returns only the total count of matching records.
 *                - 'count_by_rty': Returns counts grouped by record type.
 *                - 'header': Returns record header fields only.
 *                - 'timemap': Returns header + specific fields suitable for time/map visualization.
 *                             Also triggers geographic data enrichment for linked places.
 *                - 'detail': Returns header + specified detail fields. If `detail` is an array of field IDs/names,
 *                            those specific fields are fetched. Otherwise, all details are fetched.
 *                - 'complete': Returns all header fields, all details, relations, and full file info.
 *                - 'structure': (Not fully used) Intended for record editing, returns header, all details, and type structure.
 *    - 'tags': (int, Optional) If > 0 (typically user ID), includes personal tags for that user with each record.
 *    - 'needall': (int, Optional) If 1, attempts to retrieve all records, bypassing default limits (e.g., for server-side rule processing).
 *                 Memory limits still apply.
 *    - 'limit': (int, Optional) Number of records to return per page.
 *    - 'offset': (int, Optional) Starting offset for pagination.
 *    - 's': (string, Optional) Sort order string (legacy, can be overridden by 'sortby' in 'q').
 *    - 'cms_cut_description': (int, Optional) If 1 (used with CMS menu items), truncates long descriptions.
 *
 *    **Client-Side / Request Metadata:**
 *    - 'id': (mixed, Optional) Unique ID for the query, passed through to the response (for client-side syncing).
 *    - 'source': (string, Optional) Identifier for the HTML element that originated the search (client-side context).
 *    - 'qname': (string|int, Optional) Name or ID of a saved search being executed, for context in messages/errors.
 *    - 'is_json': (bool, Optional) Hint that 'q' is in JSON format (internal use).
 *
 * @param string|null $relation_query (Optional) A complete SQL query string to execute directly.
 *                                    If provided, most of `$params` are bypassed except for output formatting.
 *                                    Used internally for specific cases like fetching relationship records.
 * @return array An associative array representing the search result or an error.
 *               Structure depends heavily on the 'detail' parameter. Generally includes 'status', 'data'.
 *               'data' can contain 'records', 'count', 'fields', 'rectypes', 'relations', etc.
 */
function recordSearch($system, $params, $relation_query=null)
{
    // If $params['q'] has svsID it means search by saved filter - all parameters will be taken from saved filter
    // {"svs":5}

    $mysqli = $system->getMysqli();

    $return_h3_format = false;

    if(!array_key_exists('q', $params) && (array_key_exists('svs', $params) || array_key_exists('svsID', $params))){
        $svsID = array_key_exists('svs', $params) ? $params['svs'] : $params['svsID'];
        $params['q'] = "svs:{$svsID}";
    }

    if(@$params['q']){

        $svsID = null;
        $query_json = is_array(@$params['q']) ?$params['q'] :json_decode(@$params['q'], true);
        if(!isEmptyArray($query_json)){
            $svsID = @$query_json['svs'];

            if(@$query_json['any'] || @$query_json['all']){
                //first level is defined explicitely as "any" ot "all" - we will execute it separately - to avoid complex nested queries
                $params['queryset'] = @$query_json['any']?$query_json['any']:$query_json['all'];
                $params['intersect'] = @$query_json['all']?1:0;
                $params['sortby'] = @$query_json['sortby']?$query_json['sortby']
                :(@$query_json['sort']?$query_json['sort']
                    :(@$query_json['s']?$query_json['s']:null));
            }

        }elseif(@$params['q'] && strpos($params['q'],':')>0){
            list($predicate, $svsID) = explode(':', $params['q']);
            if(!($predicate=='svs' && $svsID>0)){
                $svsID = null;
            }
        }
        if($svsID>0){ //saved search id

            $vals = mysql__select_row($mysqli,
                'SELECT svs_Name, svs_Query FROM usrSavedSearches WHERE svs_ID='.$mysqli->real_escape_string( $svsID ));

            if($vals){
                $query = $vals[1];
                $params['qname'] = $vals[0];

                $query = !is_string($query) || strpos($query, '{') !== 0 ? $query : json_decode($query, true);

                if(is_string($query) && strpos($query, '?')===0){
                    parse_str(substr($query,1), $new_params);

                    if(@$new_params['q']) { $params['q'] = @$new_params['q'];}
                    if(@$new_params['rules']) { $params['rules'] = @$new_params['rules'];}
                    if(@$new_params['w']) { $params['w'] = @$new_params['w'];}
                    if(@$new_params['notes']) { $params['notes'] = @$new_params['notes'];}

                    return recordSearch($system, $params);

                }else if(is_array($query) && array_key_exists('q', $query) && !array_key_exists('facets', $query)){

                    $params['q'] = $query['q'];
                    if(@$query['rules']) { $params['rules'] = $query['rules']; }
                    if(@$query['w']) { $params['w'] = $query['w']; }
                    if(@$query['rulesonly']) { $params['rulesonly'] = $query['rulesonly']; }

                    return recordSearch($system, $params);

                }else{
                    //this is faceted search - it is not supported
                    return $system->addError(HEURIST_ERROR, 'Saved search '
                        .$params['qname']
                        .'<br> It is not possible to run faceted search as a query string');
                }
            }
        }
    }


    $memory_limit = USystem::getConfigBytes('memory_limit');

    //set savedSearchName for error messages
    $savedSearchName = '';
    if(is_numeric(@$params['qname']) && $params['qname'] > 0){ // retrieve extra details

        $query = 'SELECT svs_ID AS qID, svs_Name AS qName, svs_UGrpID as uID, ugr_Name as uName '
        . 'FROM usrSavedSearches '
        . 'INNER JOIN sysUGrps ON ugr_ID = svs_UGrpID '
        . 'WHERE svs_ID = ' . $params['qname'];

        $saved_search = mysql__select_row_assoc($mysqli, $query);

        if($saved_search !== null){
            $name = empty($saved_search['qName']) ? $saved_search['qID'] : $saved_search['qName'] . ' (# '. $saved_search['qID'] .')';
            $workgroup = empty($saved_search['uName']) ? $saved_search['uID'] : $saved_search['uName'] . ' (# '. $saved_search['uID'] .')';
            $savedSearchName = '<br>' .MSG_SAVED_FILTER. $name . '<br>Workgroup: ' . $workgroup . '<br>';
        }else{
            $savedSearchName = MSG_SAVED_FILTER.$params['qname'].'<br>';
        }
    }else{
        $savedSearchName = @$params['qname']? MSG_SAVED_FILTER. $params['qname'] .'<br>' : '';
    }
    $savedSearchName .= empty($savedSearchName) ? '' : 'It is probably best to delete this saved filter and re-create it.<br>';

    $system->defineConstant('RT_CMS_MENU');
    $system->defineConstant('DT_EXTENDED_DESCRIPTION');

    $useNewTemporalFormatInRecDetails = ($system->settings->get('sys_dbSubSubVersion')>=14);

    $fieldtypes_in_res = null;
    //search for geo and time fields and remove non timemap records - for rules we need all records
    $istimemap_request = (@$params['detail']=='timemap' && @$params['needall']!=1);
    $find_places_for_geo = false;
    $istimemap_counter = 0; //total records with timemap data
    $needThumbField = false;
    $needThumbBackground = false;
    $needCompleteInformation = false; //if true - get all header fields, relations, full file info
    $needTags = (@$params['tags']>0)?$system->getUserId():0;
    $checkFields = (@$params['checkFields'] == 1);// check validity of certain field types

    $relations = null;
    $permissions = null;

    if(!@$params['detail']){// list of rec_XXX and field ids, if rec_XXX is missed all header fields are included
        $params['detail'] = @$params['f'];//backward capability
        if(!@$params['detail']){
            $params['detail'] = 'ids';
        }
    }
    if($params['detail']=='complete'){
        $params['detail'] = 'detail';
        $needCompleteInformation = true; //all header fields, relations, full file info
    }

    $header_fields = null;
    $fieldtypes_ids = null;

    $is_count_only = ('count'==$params['detail']);
    $is_count_by_rty = ('count_by_rty'==$params['detail']);
    if($is_count_by_rty) {$is_count_only = true;}
    $is_ids_only = ('ids'==$params['detail']);

    if($params['detail']=='timemap'){ //($istimemap_request){
        $params['detail']='detail';

        $system->defineConstant('DT_START_DATE');
        $system->defineConstant('DT_END_DATE');
        $system->defineConstant('DT_GEO_OBJECT');
        $system->defineConstant('DT_DATE');
        $system->defineConstant('DT_SYMBOLOGY_POINTMARKER');//outdated
        $system->defineConstant('DT_SYMBOLOGY_COLOR');//outdated
        $system->defineConstant('DT_BG_COLOR');//outdated
        $system->defineConstant('DT_OPACITY');//outdated

        //list of rectypes that are sources for geo location
        $rectypes_as_place = $system->settings->get('sys_TreatAsPlaceRefForMapping');
        if($rectypes_as_place){
            $rectypes_as_place = prepareIds($rectypes_as_place);
        }else {
            $rectypes_as_place = array();
        }
        //Place always in this array
        if($system->defineConstant('RT_PLACE')){
            if(!in_array(RT_PLACE, $rectypes_as_place)){
                array_push($rectypes_as_place, RT_PLACE);
            }
        }

        //get date,year and geo fields from structure
        $fieldtypes_ids = dbs_GetDetailTypes($system, array('date','year','geo'), 3);
        if(isEmptyArray($fieldtypes_ids)){
            //this case nearly impossible since system always has date and geo fields
            $fieldtypes_ids = array(DT_GEO_OBJECT, DT_DATE, DT_START_DATE, DT_END_DATE);//9,10,11,28';
        }
        //add symbology fields
        if(defined('DT_SYMBOLOGY_POINTMARKER')) {$fieldtypes_ids[] = DT_SYMBOLOGY_POINTMARKER;}
        if(defined('DT_SYMBOLOGY_COLOR')) {$fieldtypes_ids[] = DT_SYMBOLOGY_COLOR;}
        if(defined('DT_BG_COLOR')) {$fieldtypes_ids[] = DT_BG_COLOR;}
        if(defined('DT_OPACITY')) {$fieldtypes_ids[] = DT_OPACITY;}

        $fieldtypes_ids = prepareIds($fieldtypes_ids);

        $fieldtypes_ids = implode(',', $fieldtypes_ids);
        $needThumbField = true;

        //find places linked to result records for geo field
        if(@$params['suppres_derivemaplocation']!=1){ //for production sites - such as USyd Book of Remembrance Online or Digital Harlem
            $find_places_for_geo = !empty($rectypes_as_place) &&
            ($system->userGetPreference('deriveMapLocation', 1)==1);
        }

    }elseif(  !in_array($params['detail'], array('count','count_by_rty','ids','header','timemap','detail','structure')) ){ //list of specific detailtypes
        //specific set of detail fields and header fields
        if(is_array($params['detail'])){
            $fieldtypes_ids = $params['detail'];
        } else {
            $fieldtypes_ids = explode(',', $params['detail']);
        }

        if(!isEmptyArray($fieldtypes_ids))
        //(count($fieldtypes_ids)>1 || is_numeric($fieldtypes_ids[0])) )
        {
            $f_res = array();
            $header_fields = array();

            foreach ($fieldtypes_ids as $dt_id){

                if(is_numeric($dt_id) && $dt_id>0){
                    array_push($f_res, $dt_id);
                }elseif($dt_id=='rec_ThumbnailURL'){
                    $needThumbField = true;
                }elseif($dt_id=='rec_ThumbnailBg'){
                    $needThumbBackground = true;
                }elseif(strpos($dt_id,'rec_')===0){
                    array_push($header_fields, $dt_id);
                }
            }

            if(!isEmptyArray($f_res)){
                $fieldtypes_ids = implode(',', $f_res);
                $params['detail'] = 'detail';
                $needThumbField = true;
            }else{
                $fieldtypes_ids = null;
            }
            if(empty($header_fields)){
                $header_fields = null;
            }else{
                //always include rec_ID and rec_RecTypeID
                if(!in_array('rec_RecTypeID',$header_fields)) {array_unshift($header_fields, 'rec_RecTypeID');}
                if(!in_array('rec_ID',$header_fields)) {array_unshift($header_fields, 'rec_ID');}
            }

        }else{
            $fieldtypes_ids = null;
            $params['detail'] = 'ids';
        }

    }else{
        $needThumbField = true;
    }


    //specific for USyd Book of Remembrance parameters - returns prevail bg color for thumbnail image
    $needThumbBackground = $needThumbBackground || (@$params['thumb_bg']==1);

    if(null==$system){
        $system = new hserv\System();
        if( ! $system->init(htmlspecialchars(@$_REQUEST['db'])) ){
            $response = $system->getError();
            if($return_h3_format){
                $response['error'] = $response['message'];
            }
            return $response;
        }
    }

    $currentUser = $system->getCurrentUser();

    if ( $system->getUserId()<1 ) {
        $params['w'] = 'all';//does not allow to search bookmarks if not logged in
    }

    if($is_count_only){

        if($is_count_by_rty){
            $select_clause = 'select rec_RecTypeID, count(rec_ID) ';
        }else{
            $select_clause = 'select count(rec_ID) ';
        }


    }elseif($is_ids_only){

        //
        $select_clause = 'select SQL_CALC_FOUND_ROWS DISTINCT rec_ID ';

    }elseif($header_fields!=null){

        $select_clause = 'select SQL_CALC_FOUND_ROWS DISTINCT '.implode(',',$header_fields).' ';

    }else{

        $select_clause = 'select SQL_CALC_FOUND_ROWS DISTINCT '   //this function does not pay attention on LIMIT - it returns total number of rows
        .'bkm_ID,'
        .'bkm_UGrpID,'
        .'rec_ID,'
        .'rec_URL,'
        .'rec_RecTypeID,'
        .'rec_Title,'
        .'rec_OwnerUGrpID,'
        .'rec_NonOwnerVisibility,'
        .'rec_Modified,'
        .'bkm_PwdReminder,'
        .'rec_URLErrorMessage ';//don't forget trailing space
        /*
        .'rec_URLLastVerified,'
        .'bkm_PwdReminder ';*/


        if($needCompleteInformation){
            $select_clause = $select_clause
            .',rec_Added'
            .',rec_AddedByUGrpID'
            .',rec_ScratchPad'
            .',bkm_Rating ';
        }
    }
    
    if($currentUser && @$currentUser['ugr_ID']>0){
        $currUserID = $currentUser['ugr_ID'];
    }else{
        $currUserID = 0;
        $params['w'] = 'all';
    }


    if ( @$params['topids'] ){ //if topids are defined we use them as starting point for following rule query
        // it is used for incremental client side only

        if ( @$params['is_json'] ){

            //second parameter is link - add ids
            $keys = array_keys($params['q']);
            array_push($params['q'][$keys[count($keys)>1?1:0]],array('ids'=>prepareIds($params['topids'])));

        }else{

            $query_top = array();

            if (strcasecmp(@$params['w'],'B') == 0  ||  strcasecmp(@$params['w'], 'bookmark') == 0) {
                $query_top['from'] = 'FROM usrBookmarks TOPBKMK LEFT JOIN Records TOPBIBLIO ON bkm_recID=rec_ID ';
            }else{
                $query_top['from'] = 'FROM Records TOPBIBLIO LEFT JOIN usrBookmarks TOPBKMK ON bkm_recID=rec_ID and bkm_UGrpID='.$currUserID.' ';
            }
            $query_top['where'] = "(TOPBIBLIO.rec_ID in (".implode(',',prepareIds($params['topids']))."))";
            $query_top['sort'] =  '';
            $query_top['limit'] =  '';
            $query_top['offset'] =  '';

            $params['parentquery'] = $query_top;  //parentquery parameter is used in  get_sql_query_clauses

        }

    }
    elseif( @$params['rules'] ){ //set of consequent queries that depend on main query

        // rules - JSON array the same as stored in saved searches table

        if(is_array($params['rules'])){
            $rules_tree = $params['rules'];
        }else{
            $rules_tree = json_decode($params['rules'], true);
        }

        $flat_rules = array();
        $flat_rules[0] = array();

        //create flat rule array
        _createFlatRule( $flat_rules, $rules_tree, 0 );

        //find result for main query
        unset($params['rules']);
        if(@$params['limit']) {unset($params['limit']);}
        if(@$params['offset']) {unset($params['offset']);}

        $params['needall'] = 1; //return all records, otherwise dependent records could not be found

        $resSearch = recordSearch($system, $params);//search for main set
        //rulesonly 3 - keep original+last rule,  2 - returns only last extension, 1- returns all exts, 0 keep original+all rules
        $keepMainSet = (@$params['rulesonly']!=1 && @$params['rulesonly']!=2);
        $keepLastSetOnly = (@$params['rulesonly']==2 || @$params['rulesonly']==3);

        if(is_array($resSearch) && $resSearch['status']!=HEURIST_OK){  //error
            return $resSearch;
        }

        //find main query results
        $fin_result = $resSearch;
        //main result set
        $has_results = @$fin_result['data']['records'] && is_array($fin_result['data']['records']);
        if($has_results){
            $flat_rules[0]['results'] = $is_ids_only
            ?$fin_result['data']['records']
            :array_keys($fin_result['data']['records']);//get ids
        }else{
            $flat_rules[0]['results'] = array();
        }

        if(!$has_results || !$keepMainSet){
            //empty main result set
            $fin_result['data']['records'] = array();//empty
            $fin_result['data']['reccount'] = 0;
            $fin_result['data']['count'] = 0;
        }

        $is_get_relation_records = (@$params['getrelrecs']==1);//get all related and relationship records

        foreach($flat_rules as $idx => $rule){ //loop for all rules
            if($idx==0) {continue;}

            $is_last = (@$rule['islast']==1);

            //create request
            $params['q'] = $rule['query'];
            $parent_ids = $flat_rules[$rule['parent']]['results'];//list of record ids of parent resultset
            $rule['results'] = array();//reset

            //split by 3000 - search based on parent ids (max 3000)
            $k = 0;
            if(is_array($parent_ids)){
                while ($k < count($parent_ids)) {

                    //$need_details2 = $need_details && ($is_get_relation_records || $is_last);

                    $params3 = $params;
                    $params3['topids'] = implode(",", array_slice($parent_ids, $k, 3000));
                    if( !$is_last ){  //($is_get_relation_records ||
                        //$params3['detail'] = 'ids';//no need in details for preliminary results  ???????
                    }

                    if(is_array($params3['q'])){
                        $params3['is_json'] = true;
                    }elseif(strpos($params3['q'],'related_to')>0){
                        //t:54 related_to:10 =>   {"t":"54","related":"10"}
                        $params3['q'] = str_replace('related_to','related',$params3['q']);

                    }elseif(strpos($params3['q'],'relatedfrom')>0){

                        $params3['q'] = str_replace('relatedfrom','related',$params3['q']);
                    }

                    if($needCompleteInformation){
                        $params3['detail'] = 'complete';
                    }

                    $response = recordSearch($system, $params3);

                    if($response['status'] == HEURIST_OK){

                        if(!$rule['ignore'] && (!$keepLastSetOnly || $is_last)){
                            //merge with final results
                            if($is_ids_only){

                                $fin_result['data']['records'] = array_merge_unique($fin_result['data']['records'],
                                    $response['data']['records']);

                            }else{
                                $fin_result['data']['records'] = mergeRecordSets($fin_result['data']['records'],
                                    $response['data']['records']);

                                $fin_result['data']['fields_detail'] = array_merge_unique($fin_result['data']['fields_detail'],
                                    $response['data']['fields_detail']);

                                $fin_result['data']['rectypes'] = array_merge_unique($fin_result['data']['rectypes'],
                                    $response['data']['rectypes']);

                                $fin_result['data']['order'] = array_merge($fin_result['data']['order'],
                                    array_keys($response['data']['records']));
                            }
                        }

                        if(!$is_last){ //add top ids for next level
                            $flat_rules[$idx]['results'] = array_merge_unique($flat_rules[$idx]['results'],
                                $is_ids_only ?$response['data']['records'] :array_keys($response['data']['records']));
                        }

                        if($is_get_relation_records &&
                            (strpos($params3['q'],"related")>0 ||
                                strpos($params3['q'],"related_to")>0 || strpos($params3['q'],"relatedfrom")>0) )
                            { //find relationship records (recType=1)

                                //create query to search related records
                                if (strcasecmp(@$params3['w'],'B') == 0  ||  strcasecmp(@$params3['w'], 'bookmark') == 0) {
                                    $from = 'FROM usrBookmarks TOPBKMK LEFT JOIN Records TOPBIBLIO ON bkm_recID=rec_ID ';
                                }else{
                                    $from = 'FROM Records TOPBIBLIO LEFT JOIN usrBookmarks TOPBKMK ON bkm_recID=rec_ID and bkm_UGrpID='.$currUserID.' ';
                                }

                                if(strpos($params3['q'],"related_to")>0){
                                    $fld2 = "rl_SourceID";
                                    $fld1 = "rl_TargetID";
                                }else{
                                    $fld1 = "rl_SourceID";
                                    $fld2 = "rl_TargetID";
                                }

                                $ids_party1 = $params3['topids'];//source ids (from top query)
                                $ids_party2 = $is_ids_only?$response['data']['records'] :array_keys($response['data']['records']);

                                if(!isEmptyArray($ids_party2))
                                {


                                    $where = "WHERE (TOPBIBLIO.rec_ID in (select rl_RelationID from recLinks "
                                    ."where (rl_RelationID is not null) and ".
                                    "(($fld1 in (".$ids_party1.") and $fld2 in (".implode(",", $ids_party2).")) OR ".
                                    " ($fld2 in (".$ids_party1.") and $fld1 in (".implode(",", $ids_party2)."))) ".
                                    "))";

                                    $params2 = $params3;
                                    unset($params2['topids']);
                                    unset($params2['q']);

                                    $relation_query = $select_clause.$from.$where;

                                    $response = recordSearch($system, $params2, $relation_query);//search for relationship records
                                    if($response['status'] == HEURIST_OK){

                                        if(!@$fin_result['data']['relationship']){
                                            $fin_result['data']['relationship'] = array();
                                        }

                                        if($is_ids_only){
                                            $fin_result['data']['relationship'] = array_merge_unique(
                                                $fin_result['data']['relationship'],
                                                $response['data']['records']);
                                        }else{
                                            $fin_result['data']['relationship'] = mergeRecordSets($fin_result['data']['relationship'], $response['data']['records']);
                                        }


                                        /*merge with final results
                                        if($is_ids_only){
                                        $fin_result['data']['records'] = array_merge($fin_result['data']['records'], $response['data']['records']);
                                        }else{
                                        $fin_result['data']['records'] = mergeRecordSets($fin_result['data']['records'], $response['data']['records']);
                                        $fin_result['data']['order'] = array_merge($fin_result['data']['order'], array_keys($response['data']['records']));
                                        $fin_result['data']['rectypes'][1] = 1;
                                        }
                                        */
                                    }
                                }//array of ids is defined

                        }  //$is_get_relation_records

                    }else{
                        //@todo terminate execution and return error
                    }

                    $k = $k + 3000;
                }//while chunks
            }
        } //for rules


        if($is_ids_only){
            //$fin_result['data']['records'] = array_unique($fin_result['data']['records']);
        }
        $fin_result['data']['count'] = count($fin_result['data']['records']);
        $fin_result['data']['reccount'] = $fin_result['data']['count'];

        if($return_h3_format){
            $fin_result = array("resultCount" => $fin_result['data']['count'],
                "recordCount" => $fin_result['data']['count'],
                "recIDs" => implode(",", $fin_result['data']['records']) );
        }

        //@todo - assign if size less than 3000? only
        $fin_result['data']['mainset'] = $flat_rules[0]['results'];

        return $fin_result;
    }//END RULES ------------------------------------------
    elseif( @$params['queryset'] ){ //list of queries with OR (default) or AND operators
        // to facilitate database server workload. Old versions of mySQL (5.7) fail to execute
        // complex nested queries. Especailly with OR operators

        if(is_array($params['queryset'])){
            $queryset = $params['queryset'];
        }else{
            $queryset = json_decode($params['queryset'], true);
        }

        $is_or_conjunction = (@$params['intersect']!=1);//intersect or merge = AND or OR
        $details = @$params['detail'];
        $limit = @$params['limit'];
        $sortby = @$params['sortby'];

        unset($params['queryset']);
        unset($params['all']);
        if(@$params['limit']) {unset($params['limit']);}
        if(@$params['offset']) {unset($params['offset']);}
        if(@$params['sortby']) {unset($params['sortby']);}

        $params['detail'] = 'ids';
        $params['needall'] = 1;
        $fin_result = null;
        foreach($queryset as $idx => $query){ //loop for all queries

            $params['q'] = $query;

            $resSearch = recordSearch($system, $params);//search for main set
            if(is_array($resSearch) && $resSearch['status']!=HEURIST_OK){  //error
                return $resSearch;
            }
            if($fin_result==null){
                $fin_result = $resSearch;
            }else{
                //if OR - merge unique
                if($is_or_conjunction){
                    $fin_result['data']['records'] = array_merge_unique(
                        $fin_result['data']['records'],
                        $resSearch['data']['records']);
                }else{
                    //if AND - intersect
                    $fin_result['data']['records'] = array_intersect(
                        $fin_result['data']['records'],
                        $resSearch['data']['records']);
                }
            }
        }//foreach

        if(@$fin_result['data']['records']){
            $total_count = count($fin_result['data']['records']);
            if($limit>0 && $limit<$total_count){
                $fin_result['data']['records'] = array_slice($fin_result['data']['records'],0,$limit);
            }

            if(($details=='ids' || $details==null) && $sortby==null){
                $fin_result['data']['offset'] = 0;
                $fin_result['data']['reccount'] = count($fin_result['data']['records']);
            }else{
                $params['details'] = $details;
                $params['q'] = array('ids'=>$fin_result['data']['records']);
                $params['q']['sortby'] = $sortby;
                $fin_result = recordSearch($system, $params);//search for main set
            }

            if($fin_result['status']==HEURIST_OK){
                $fin_result['data']['count'] = $total_count; //total count
            }
        }

        return $fin_result;
    }
    elseif( $currUserID>0 ) {
        //find user work susbset (except EVERYTHING search)
        $params['use_user_wss'] = (@$params['w']!='e');//(strcasecmp(@$params['w'],'E') == 0);
    }


    $search_detail_limit = PHP_INT_MAX;

    if($relation_query!=null){
        $query = $relation_query;
    }else{

        $is_mode_json = false;

        $q = @$params['q'];

        if($q!=null && $q!=''){

            if(is_array($q)){
                $query_json = $q;
            }else{
                $query_json = json_decode($q, true);

                //try to parse plain string
                if( strpos($q,'*')===0 && isEmptyArray($query_json)){
                    $q = substr($q, 1);
                    $query_json = parse_query_to_json( $q );
                }
            }

            if(!isEmptyArray($query_json)){
                $params['q'] = $query_json;
                $is_mode_json = true;
            }

        }else{
            return $system->addError(HEURIST_INVALID_REQUEST, $savedSearchName."Invalid search request. Missing query parameter 'q'");
        }

        if($is_mode_json){
            $aquery = get_sql_query_clauses_NEW($mysqli, $params, $currentUser);//main usage
        }else{
            $aquery = get_sql_query_clauses($mysqli, $params, $currentUser);//!!!! IMPORTANT CALL OR compose_sql_query at once
        }

        if(@$aquery['error']=='create_fulltext'){
            return $system->addError(HEURIST_ACTION_BLOCKED, '<h3 style="margin:4px;">Building full text index</h3>'
                .'<p>To process word searches efficiently we are building a full text index.</p>'
                .'<p>This is a one-off operation and may take some time for large, text-rich databases '
                .'(where it will make the biggest difference to retrieval speeds).</p>', null);
        }elseif(@$aquery['error']){
            return $system->addError(HEURIST_ERROR, 'Unable to construct valid SQL query. '.@$aquery['error'], null);
        }
        if(!isset($aquery["where"]) || trim($aquery["where"])===''){
            return $system->addError(HEURIST_ERROR, 'Invalid search request; unable to construct valid SQL query', null);
        }

        if($is_count_only || ($is_ids_only && @$params['needall']) || !$system->hasAccess() ){ //not logged in
            $search_detail_limit = PHP_INT_MAX;
            $aquery['limit'] = '';
            if($is_count_only) {$aquery['sort'] = '';}
            $aquery['offset'] = '';
        }else{
            $search_detail_limit = $system->userGetPreference('search_detail_limit');//limit for map/timemap output
        }
        if($is_count_by_rty){
            $aquery['sort'] = ' GROUP BY rec_RecTypeID';
        }

        $query =  $select_clause.$aquery['from'].SQL_WHERE.$aquery["where"].$aquery["sort"].$aquery["limit"].$aquery["offset"];

    }

    if(@$_REQUEST['dbg']==1) {
        print htmlspecialchars($query);
        exit;
    }


    //$res = $mysqli->query($query);
    $res = mysql__select($mysqli, $query);
    if (!$res){

        $sMsg = '';
        if($savedSearchName){
            $sMsg = 'in saved filter '.$savedSearchName;
        }else{
            $sMsg = 'in your query';
        }

        $response = $system->addError(HEURIST_ACTION_BLOCKED,
            '<h4>Uninterpretable Heurist query/filter</h4>'
            .'There is an error '.$sMsg.' syntax generating invalid SQL. Please check for misspelled keywords or incorrect syntax. See help for assistance.<br><br>'

            //.$params['q'].'  '.$query.'<br><br>'

            .'If you think the filter is correct, please make a bug report (link under Help menu at top right) or email the Heurist team, including the text of your filter.');

        //$response = $system->addError(HEURIST_DB_ERROR, $savedSearchName.
        //    ' Search query error on saved search. Parameters:'.print_r($params, true).' Query '.$query, $mysqli->error);
    }elseif($is_count_by_rty){

        $total_count_rows = 0;
        $records = array();

        while ($row = $res->fetch_row())  {
            $records[$row[0]] = (int)$row[1];
            $total_count_rows = $total_count_rows + (int)$row[1];
        }
        $res->close();

        $response = array('status'=>HEURIST_OK,
            'data'=> array(
                'queryid'=>@$params['id'],  //query unqiue id
                'recordtypes'=>$records,
                'count'=>$total_count_rows));

    }elseif($is_count_only){

        $total_count_rows = $res->fetch_row();
        $total_count_rows = (int)$total_count_rows[0];
        $res->close();

        $response = array('status'=>HEURIST_OK,
            'data'=> array(
                'queryid'=>@$params['id'],  //query unqiue id
                'count'=>$total_count_rows));

    }else{

        $total_count_rows = mysql__found_rows($mysqli);

        if($total_count_rows*10>$memory_limit){
            return $system->addError(HEURIST_ACTION_BLOCKED,
                $total_count_rows.MSG_MEMORY_LIMIT);
        }

        $rec_RecTypeID_index = false;

        if($is_ids_only)
        { //------------------------  LOAD and RETURN only IDS

            $records = array();

            while ($row = $res->fetch_row())  {
                array_push($records, (int)$row[0]);
            }
            $res->close();

            $response = array('status'=>HEURIST_OK,
                'data'=> array(
                    'queryid'=>@$params['id'],  //query unqiue id
                    'entityName'=>'Records',
                    'count'=>$total_count_rows,
                    'offset'=>get_offset($params),
                    'reccount'=>count($records),
                    'records'=>$records));

            if(@$params['links_count'] && !empty($records)){

                $links_counts = recordLinkedCount($system,
                    $params['links_count']['source'],
                    count($records)<500?$records:
                    $params['links_count']['target'],
                    @$params['links_count']['dty_ID']);

                if($links_counts['status']==HEURIST_OK && !isEmptyArray(@$links_counts['data']) ){

                    //order output
                    $res = array_keys($links_counts['data']);
                    if(count($res) < count($records)){
                        foreach ($records as $id){
                            if(!in_array($id, $res)){
                                $res[] = $id;
                            }
                        }
                    }
                    $response['data']['records'] = $res;
                    $response['data']['links_count'] = $links_counts['data'];
                    $response['data']['links_query'] = '{"t":"'
                    .$params['links_count']['source']
                    .'","linkedto'
                    .(@$params['links_count']['dty_ID']>0?(':'.$params['links_count']['dty_ID']):'')
                    .'":"[ID]"}';
                }
            }



        }else{ //----------------------------------

            $rectype_structures  = array();
            $rectypes = array();
            $records = array();
            $order = array();
            $all_rec_ids = array();
            $memory_warning = null;
            $limit_warning = false;

            // read all field names
            $_flds =  $res->fetch_fields();
            $fields = array();
            foreach($_flds as $fld){
                array_push($fields, $fld->name);
            }
            $rec_ID_index = array_search('rec_ID', $fields);
            $rec_RecTypeID_index = array_search('rec_RecTypeID', $fields);
            $date_add_index = array_search('rec_Added', $fields);
            $date_mod_index = array_search('rec_Modified', $fields);

            if($needThumbField) {array_push($fields, 'rec_ThumbnailURL');}
            if($needThumbBackground) {array_push($fields, 'rec_ThumbnailBg');}

            //array_push($fields, 'rec_Icon');//last one -icon ID
            if($needTags>0) {array_push($fields, 'rec_Tags');}

            // load all records
            while ($row = $res->fetch_row()) {

                if($needThumbField) {
                    $tres = fileGetThumbnailURL($system, $row[$rec_ID_index], $needThumbBackground);
                    array_push( $row, $tres['url'] );
                    if($needThumbBackground) {array_push( $row, $tres['bg_color'] );}
                }
                if($needTags>0){ //get record tags for given user/group
                    /*var dbUsrTags = new DbUsrTags($system, array('details'=>'label',
                    'tag_UGrpID'=>$needTags,
                    'rtl_RecID'=>$row[2] ));*/

                    $query = 'SELECT tag_Text FROM usrTags, usrRecTagLinks WHERE tag_ID=rtl_TagID AND tag_UGrpID='
                    .$needTags.' AND rtl_RecID='.$row[$rec_ID_index];
                    array_push( $row, mysql__select_list2($mysqli, $query));
                }

                //convert add and modified date to UTC
                if($date_add_index!==false) {
                    // zero date not allowed by default since MySQL 5.7, default date changed to 1000
                    if($row[$date_add_index]=='0000-00-00 00:00:00'
                    || $row[$date_add_index]=='1000-01-01 00:00:00'){ //not defined
                        $row[$date_add_index] = '';
                    }else{
                        $row[$date_add_index] = DateTime::createFromFormat(DATE_8601, $row[$date_add_index])
                        ->setTimezone(new DateTimeZone('UTC'))
                        ->format(DATE_8601);
                    }
                }
                if($date_mod_index!==false) {
                    $row[$date_mod_index] = DateTime::createFromFormat(DATE_8601, $row[$date_mod_index])
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format(DATE_8601);
                }


                //array_push( $row, $row[4] );//by default icon if record type ID
                $rec_ID = intval($row[$rec_ID_index]);
                $records[$rec_ID] = $row;
                array_push($order, $rec_ID);
                array_push($all_rec_ids, $rec_ID);
                if($rec_RecTypeID_index>=0 && !@$rectypes[$row[$rec_RecTypeID_index]]){  //rectypes is resultset
                    $rectypes[$row[$rec_RecTypeID_index]]=1;
                }

                if(count($all_rec_ids)>5000){
                    $mem_used = memory_get_usage();
                    if($mem_used>$memory_limit-104857600){ //100M
                        return $system->addError(HEURIST_ACTION_BLOCKED,
                            $total_count_rows.MSG_MEMORY_LIMIT);
                    }
                }

            }//load headers
            $res->close();

            //LOAD DETAILS
            if(($istimemap_request ||
            $params['detail']=='detail' ||
            $params['detail']=='structure') && !empty($records)){


                //$all_rec_ids = array_keys($records);
                $res_count = count($all_rec_ids);
                //split to 2500 to use in detail query
                $offset = 0;

                if($istimemap_request){
                    $tm_records = array();
                    $order = array();
                    $rectypes = array();
                    $istimemap_counter = 0;
                }

                $fieldtypes_in_res = array();//reset

                // FIX on fly: get "file" field types  - @todo  remove on 2022-08-22
                $file_field_types = mysql__select_list2($mysqli,'select dty_ID from defDetailTypes where dty_Type="file"');

                $datetime_field_types = mysql__select_list2($mysqli,'select dty_ID from defDetailTypes where dty_Type="date"');

                $loop_cnt=1;
                while ($offset<$res_count){

                    //here was a problem, since chunk size for mapping can be 5000 or more we got memory overflow here
                    //reason the list of ids in SELECT is bigger than mySQL limit
                    //solution - we perfrom the series of request for details by 1000 records
                    $chunk_rec_ids = array_slice($all_rec_ids, $offset, 1000);
                    $offset = $offset + 1000;

                    $ulf_fields = 'f.ulf_ObfuscatedFileID, f.ulf_MimeExt';//5,6  was ulf_Parameters

                    //search for specific details
                    if($fieldtypes_ids!=null && $fieldtypes_ids!=''){

                        $detail_query = 'select dtl_ID, dtl_RecID,'
                        .'dtl_DetailTypeID,'     // 0
                        .'dtl_Value,'            // 1
                        .'ST_asWKT(dtl_Geo), dtl_UploadedFileID, '  //2,3
                        .'dtl_HideFromPublic, ' //4
                        .$ulf_fields
                        .' FROM recDetails '
                        . ' left join recUploadedFiles as f on f.ulf_ID = dtl_UploadedFileID '
                        . SQL_WHERE
                        .predicateId('dtl_RecID',$chunk_rec_ids)
                        .SQL_AND
                        .predicateId('dtl_DetailTypeID',$fieldtypes_ids);


                        if($find_places_for_geo){ //find location in linked Place records
                            $detail_query = $detail_query . 'UNION  '
                            .'SELECT dtl_ID, rl_SourceID,dtl_DetailTypeID,dtl_Value, ST_asWKT(dtl_Geo), rl_TargetID, 0, 0, 0 '
                            .' FROM recDetails, recLinks, Records '
                            .' WHERE (dtl_Geo IS NOT NULL) ' //'dtl_DetailTypeID='. DT_GEO_OBJECT
                            .' AND dtl_RecID=rl_TargetID AND rl_TargetID=rec_ID AND '
                            .predicateId('rec_RecTypeID',$rectypes_as_place)
                            .SQL_AND
                            .predicateId('rl_SourceID',$chunk_rec_ids);
                        }
                    }else{

                        if($needCompleteInformation){
                            $ulf_fields = 'f.ulf_OrigFileName,f.ulf_ExternalFileReference,f.ulf_ObfuscatedFileID,'
                            .'f.ulf_MimeExt,f.ulf_Caption,f.ulf_WhoCanView';//5,6,7,8,9,10
                        }else{

                        }

                        $detail_query = 'select dtl_ID, dtl_RecID,'
                        .'dtl_DetailTypeID,'     // 0
                        .'dtl_Value,'            // 1
                        .'ST_asWKT(dtl_Geo),'    // 2
                        .'dtl_UploadedFileID,'   // 3
                        .'dtl_HideFromPublic,'   // 4
                        .$ulf_fields
                        .' from recDetails
                        left join recUploadedFiles as f on f.ulf_ID = dtl_UploadedFileID
                        where dtl_RecID in (' . join(',', $chunk_rec_ids) . ')';

                    }
                    //$detail_query = $detail_query . ' order by dtl_RecID, dtl_ID';
                    $need_Concatenation = false;
                    $loop_cnt++;
                    // @todo - we may use getAllRecordDetails
                    $res_det = $mysqli->query( $detail_query );

                    if (!$res_det){
                        $response = $system->addError(HEURIST_DB_ERROR,
                            $savedSearchName.'Search query error (retrieving details)',
                            $mysqli->error);
                        return $response;
                    }else{

                        while ($row = $res_det->fetch_row()) {
                            $dtl_ID = array_shift($row);
                            $recID = array_shift($row);
                            if( !array_key_exists('d', $records[$recID]) ){
                                $records[$recID]['d'] = array();
                                $need_Concatenation = $need_Concatenation ||
                                (defined('RT_CMS_MENU') && $records[$recID][4]==RT_CMS_MENU);
                            }
                            $dtyID = $row[0];


                            // FIX on fly - @todo  remove on 2022-08-22
                            if( (!($row[3]>0)) && in_array($dtyID,$file_field_types) ){
                                if($ruf_entity==null){
                                    $ruf_entity = new DbRecUploadedFiles($system);
                                }
                                $fileinfo = $ruf_entity->registerURL($row[1], false, $dtl_ID);

                                if($fileinfo && !isEmptyArray($fileinfo)){

                                    if($needCompleteInformation){
                                        $row[3] = $fileinfo['ulf_ID'];
                                        $row[5] = $fileinfo['ulf_OrigFileName'];
                                        $row[6] = $fileinfo['ulf_ExternalFileReference'];
                                        $row[7] = $fileinfo['ulf_ObfuscatedFileID'];
                                        $row[8] = $fileinfo['ulf_MimeExt'];
                                        $row[9] = $fileinfo['ulf_Caption'];
                                        $row[10] = $fileinfo['ulf_WhoCanView'];
                                    }else{
                                        $row[5] = $fileinfo['ulf_ObfuscatedFileID'];
                                        $row[6] = $fileinfo['ulf_MimeExt'];
                                    }
                                }

                            }

                            $val = null;
                            $field_error = null;

                            if($row[2]){ //GEO
                                //dtl_Geo @todo convert to JSON
                                $val = $row[1];//geotype

                                // see $find_places_for_geo 3d value is record id of linked place
                                $linked_Place_ID = $row[3];//linked place record id
                                if($linked_Place_ID>0){
                                    $val = $val.':'.$linked_Place_ID;      //reference to real geo record
                                }

                                $val = $val.' '.$row[2];//WKT

                            }elseif($row[3]){ //uploaded file

                                if($needCompleteInformation){

                                    $val = [
                                        'ulf_ID'=>$row[3],
                                        'ulf_OrigFileName'=>$row[5],
                                        'ulf_ExternalFileReference'=>$row[6],
                                        'ulf_ObfuscatedFileID'=>$row[7],
                                        'ulf_MimeExt'=>$row[8],
                                        'ulf_Caption'=>$row[9],
                                        'ulf_WhoCanView'=>$row[10]
                                    ];

                                }else{
                                    $val = array($row[5], $row[6]);//obfuscated value for fileid and parameters
                                }

                            }elseif(in_array($dtyID, $datetime_field_types) && @$row[1]!=null) {
                                //!$useNewTemporalFormatInRecDetails &&
                                //convert date to old plain string temporal object to return to client side
                                $val = Temporal::getValueForRecDetails( $row[1], false );

                                if($checkFields){ // check if this date has been indexed and interpreted

                                    $check_query = 'SELECT rdi_estMinDate, rdi_estMaxDate FROM recDetailsDateIndex WHERE rdi_DetailID = '.intval($dtl_ID);// AND rdi_estMinDate != 0 AND rdi_estMaxDate != 0
                                    $check_res = $mysqli->query($check_query);

                                    if($check_res){

                                        $extraMsg = $system->isAdmin() ? ', try running the "Date Index" option under "Admin > Verify integerity" or saving this record' : ', saving this record will attempt to index this date';
                                        $field_error = $check_res->num_rows == 0 ? "This date has not been indexed{$extraMsg}" : null;

                                        if(!$field_error){ // has been indexed
                                            $row = $check_res->fetch_row();
                                            $field_error = intval($row[0]) === 0 && intval($row[1]) === 0 ? 'This date has been indexed, but it couldn\'t be interpreted' : null;
                                        }

                                    } // else mysql error
                                }

                            }elseif(@$row[1]!=null) {
                                $val = $row[1];//dtl_Value
                            }

                            if($val!=null){
                                $fieldtypes_in_res[$dtyID] = 1;
                                if( !array_key_exists($dtyID, $records[$recID]['d']) ){
                                    $records[$recID]['d'][$dtyID] = array();
                                    $records[$recID]['v'][$dtyID] = array();

                                    if($checkFields) { $records[$recID]['errors'][$dtyID] = array();}
                                }
                                array_push($records[$recID]['d'][$dtyID], $val);

                                //individual field visibility
                                array_push($records[$recID]['v'][$dtyID], $row[4]);//dtl_HideFromPublic

                                // if checked, return any errors found with the field
                                if($checkFields) { array_push($records[$recID]['errors'][$dtyID], $field_error);}
                            }
                        }//while
                        $res_det->close();


                        ///@todo optionally return geojson and timeline items

                        //additional loop for timemap request
                        //1. exclude records without timemap data
                        //2. limit to $search_detail_limit from preferences 'search_detail_limit'
                        if($istimemap_request){

                            foreach ($chunk_rec_ids as $recID) {
                                $record = $records[$recID];
                                if(!isEmptyArray(@$record['d'])){
                                    //this record is time enabled
                                    if($istimemap_counter<$search_detail_limit){
                                        $tm_records[$recID] = $record;
                                        array_push($order, $recID);
                                        if($rec_RecTypeID_index>=0) {$rectypes[$record[$rec_RecTypeID_index]] = 1; }
                                        //$records[$recID] = null; //unset
                                        //unset($records[$recID]);
                                    }else{
                                        $limit_warning = true;
                                        break;
                                    }
                                    $istimemap_counter++;
                                }
                            }
                        }//$istimemap_request
                        //it has RT_CMS_MENU - need concatenate all DT_EXTENDED_DESCRIPTION

                        if($need_Concatenation){

                            foreach ($chunk_rec_ids as $recID) {
                                $record = $records[$recID];
                                if($record[4]==RT_CMS_MENU
                                && is_array(@$record['d'][DT_EXTENDED_DESCRIPTION]))
                                {
                                    $records[$recID]['d'][DT_EXTENDED_DESCRIPTION] = array(implode('',$record['d'][DT_EXTENDED_DESCRIPTION]));

                                    if(@$params['cms_cut_description']==1 && @$records[$recID]['d'][DT_EXTENDED_DESCRIPTION][0]){
                                        $records[$recID]['d'][DT_EXTENDED_DESCRIPTION][0] = 'X';
                                    }
                                }
                            }
                        }


                        if($res_count>5000){
                            $mem_used = memory_get_usage();
                            if($mem_used>$memory_limit-52428800){ //50M
                                //cut off exceed records
                                $order = array_slice($order, 0, $offset);
                                $sliced_records = array();
                                if($istimemap_request){
                                    foreach ($order as $recID) {
                                        $sliced_records[$recID] = $tm_records[$recID];
                                    }
                                    $tm_records = $sliced_records;
                                    $memory_warning = '';
                                }else{
                                    foreach ($order as $recID) {
                                        $sliced_records[$recID] = $records[$recID];
                                    }
                                    $records = $sliced_records;
                                    $memory_warning = 'Search query produces '.$res_count.' records. ';
                                }
                                $memory_warning = $memory_warning.'The result is limited to '.count($sliced_records).' records due to server limitations.'
                                .' Please filter to a smaller set of results.';
                                break;
                            }
                        }

                    }

                }//while offset

                if($istimemap_request){

                    $records = $tm_records;
                    $total_count_rows = $istimemap_counter;
                }elseif($needCompleteInformation){
                    $relations = recordSearchRelated($system, $all_rec_ids);
                    if($relations['status']==HEURIST_OK){
                        $relations = $relations['data'];
                    }

                    $permissions = recordSearchPermissions($system, $all_rec_ids);
                    if($permissions['status']==HEURIST_OK){
                        $view_permissions = $permissions['view'];

                        array_push($fields, 'rec_NonOwnerVisibilityGroups');
                        $group_perm_index = array_search('rec_NonOwnerVisibilityGroups', $fields);
                        foreach ($view_permissions as $recid=>$groups){
                            $records[$recid][$group_perm_index] = implode(',', $groups);
                        }

                        $edit_permissions = $permissions['edit'];
                        $group_perm_index = array_search('rec_OwnerUGrpID', $fields);
                        foreach ($edit_permissions as $recid=>$groups){
                            array_unshift($groups, $records[$recid][$group_perm_index]);
                            $records[$recid][$group_perm_index] = implode(',', $groups);
                        }

                    }
                    //array("direct"=>$direct, "reverse"=>$reverse, "headers"=>$headers));
                }



            }//$need_details

            $rectypes = array_keys($rectypes);
            if( @$params['detail']=='structure' && !empty($rectypes)){ //rarely used in editing.js
                //description of recordtype and used detail types
                $rectype_structures = dbs_GetRectypeStructures($system, $rectypes, 1);//no groups
            }

            //"query"=>$query,
            $response = array('status'=>HEURIST_OK,
                'data'=> array(
                    //'query'=>$query,
                    'queryid'=>@$params['id'],  //query unqiue id
                    'pageno'=>@$params['pageno'],  //to sync page
                    'entityName'=>'Records',
                    'count'=>$total_count_rows,
                    'offset'=>get_offset($params),
                    'reccount'=>count($records),
                    'tmcount'=>$istimemap_counter,
                    'fields'=>$fields,
                    'fields_detail'=>array(),
                    'records'=>$records,
                    'order'=>$order,
                    'rectypes'=>$rectypes,
                    'limit_warning'=>$limit_warning,
                    'memory_warning'=>$memory_warning));
            if(is_array($fieldtypes_in_res)){
                $response['data']['fields_detail'] =  array_keys($fieldtypes_in_res);
            }
            if(is_array($relations)){
                $response['data']['relations'] =  $relations;
            }
        }//$is_ids_only





    }

    return $response;

}

/**
 * Merges array `$b` into array `$a`, ensuring all values in the resulting array are unique.
 * Only items from `$b` that are not already present in `$a` are appended.
 * This function only checks for value uniqueness, keys are not preserved from `$b` for new elements;
 * they are numerically re-indexed if appended.
 *
 * @param array $a The base array.
 * @param array $b The array to merge into `$a`.
 * @return array The merged array with unique values.
 */
function array_merge_unique($a, $b) {
    foreach($b as $item){
        if(array_search($item, $a, true)===false){ // Use strict search
            $a[] = $item;
        }
    }
    return $a;
}

/**
 * Merges two sets of Heurist records (associative arrays keyed by record ID).
 * If a record ID from `$rec2` does not exist in `$rec1`, it's added to `$rec1`.
 * This function effectively adds records from `$rec2` to `$rec1` without overwriting existing ones.
 *
 * @param array $rec1 The first set of records (associative array: recID => recordData).
 * @param array $rec2 The second set of records to merge into `$rec1`.
 * @return array The merged set of records.
 */
function mergeRecordSets($rec1, $rec2){
    $res = $rec1;
    if (is_array($rec2)) { // Ensure $rec2 is an array before iterating
        foreach ($rec2 as $recID => $record) {
            if(!isset($res[$recID])){ // Use isset for performance with associative arrays
                $res[$recID] = $record;
            }
        }
    }
    return $res;
}

/**
 * Flattens a hierarchical tree of search rules into a linear array.
 * Each element in the flat array represents a rule and includes its query,
 * a placeholder for results, its parent's index in the flat array,
 * and flags indicating if it should be ignored or if it's a leaf node ('islast').
 *
 * @param array &$flat_rules The array (passed by reference) to store the flattened rules.
 * @param array|null $r_tree The current level of the rule tree being processed.
 *                           Each node is expected to be an array with 'query', optionally 'ignore', and 'levels' (for children).
 * @param int $parent_index The index in `$flat_rules` of the parent rule for the current `$r_tree` level.
 * @return void Modifies `$flat_rules` by reference.
 */
function _createFlatRule(&$flat_rules, $r_tree, $parent_index){
    if($r_tree){ // Ensure $r_tree is not null and is iterable
        foreach ($r_tree as $rule) {
            $e_rule = array(
                'query' => @$rule['query'],
                'results' => array(), // Placeholder for results of this rule
                'parent' => $parent_index,
                'ignore' => (@$rule['ignore'] == 1), // Rule result not included in final combined set
                'islast' => (isEmptyArray(@$rule['levels'])) ? 1 : 0 // Is it a leaf node in the rule tree?
            );
            array_push($flat_rules, $e_rule);
            // Recursively process child rules, current new rule is the parent for them
            _createFlatRule($flat_rules, @$rule['levels'], count($flat_rules) - 1);
        }
    }
}

/**
 * Recursively finds the current/forwarded `rec_ID` for a given `rec_id` by checking `recForwarding` table.
 *
 * If the provided `$rec_id` has been forwarded to a new ID (`rfw_NewRecID`),
 * this function will recursively search for the current ID for that new ID, up to a maximum depth of 10 levels
 * to prevent infinite loops in case of circular forwarding (though that shouldn't happen).
 *
 * @param \mysqli $mysqli The mysqli database connection object.
 * @param int $rec_id The record ID to check for forwarding.
 * @param int $level Current recursion level (max 10).
 * @return int The current, effective `rec_ID` after considering any forwarding, or 0 if input was not positive.
 */
function recordSearchReplacement($mysqli, $rec_id, $level=0){
    $rec_id = intval($rec_id); // Ensure integer
    if($rec_id > 0){
        $rep_id = mysql__select_value($mysqli,
            'select rfw_NewRecID from recForwarding where rfw_OldRecID=' . $rec_id);
        if($rep_id > 0){ // If a forwarding record exists
            if($level < 10){ // Max recursion depth
                return recordSearchReplacement($mysqli, (int)$rep_id, $level + 1); // Corrected: $level++ to $level + 1
            } else {
                return (int)$rep_id; // Max depth reached, return current replacement ID
            }
        } else {
            return $rec_id; // No forwarding found, original ID is current
        }
    } else {
        return 0; // Invalid input rec_id
    }
}

/**
 * Generates a template structure for a new record of a given record type ID.
 * The template includes placeholder values for standard record header fields
 * and lists all applicable detail fields for that record type, with generic
 * placeholders for their values (e.g., 'TEXT', 'NUMERIC', 'DATE').
 * For resource and enum/relationtype fields, it attempts to list possible target types or vocabulary names.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int $id The Record Type ID for which to generate the template.
 * @return array An associative array representing the record template.
 *               Includes 'rec_ID', 'rec_RecTypeID', 'rec_Title', etc., and a 'details' array.
 *               The 'details' array is keyed by dty_ID, with values being example structures or placeholders.
 */
function recordTemplateByRecTypeID($system, $id){
    $id = intval($id);
    $record = array(
        'rec_ID'=>'RECORD-IDENTIFIER',
        'rec_RecTypeID'=>$id,
        'rec_Title'=>'',
        'rec_URL'=>'URL',
        'rec_ScratchPad'=>'',
        'rec_OwnerUGrpID'=>2,
        'rec_NonOwnerVisibility'=>'public',
        'rec_URLLastVerified'=>'',
        'rec_URLErrorMessage'=>'',
        'rec_AddedByUGrpID'=>2);

    $mysqli = $system->getMysqli();
    $fields = mysql__select_assoc($mysqli, 'select dty_ID, dty_Type, dty_JsonTermIDTree, dty_PtrTargetRectypeIDs '
        .'from defRecStructure, defDetailTypes where dty_ID = rst_DetailTypeID '
        .'and rst_RecTypeID = '.$id);

    $details = array();
    $idx = 1;

    foreach ($fields as $dty_ID=>$fieldDetails){

        $dty_Type = $fieldDetails['dty_Type'];

        if($dty_Type=='separator') {continue;}


        if($dty_Type=='file'){
            $details[$dty_ID] = array($idx=>array('file'=>array('file'=>'TEXT', 'fileid'=>'TEXT')) );

        }elseif($dty_Type=='resource'){

            $extra_details = '';
            if(array_key_exists('dty_PtrTargetRectypeIDs', $fieldDetails)){ // retrieve list of rectype names

                $rty_names = mysql__select_list2($mysqli, 'SELECT rty_Name FROM defRecTypes WHERE rty_ID IN (' . $fieldDetails['dty_PtrTargetRectypeIDs'] .')');
                if(!empty($rty_names)){
                    $extra_details = ' to ' . implode(' | ', $rty_names);
                }
            }

            $details[$dty_ID] = array($idx=>array('id'=>'RECORD_REFERENCE'.$extra_details, 'type'=>0, 'title'=>''));
        }elseif($dty_Type=='relmarker'){

            $extra_details = '';
            if(array_key_exists('dty_JsonTermIDTree', $fieldDetails)){ // retrieve list of vocab labels
                $trm_names = mysql__select_list2($mysqli, 'SELECT trm_Label FROM defTerms WHERE trm_ID IN ('. $fieldDetails['dty_JsonTermIDTree'] .')');
                if(!empty($trm_names)){
                    $extra_details = ', ' . implode(' | ', $trm_names) . ' relation to ';
                }
            }
            if(array_key_exists('dty_PtrTargetRectypeIDs', $fieldDetails)){ // retrieve list of rectype names
                $rty_names = mysql__select_list2($mysqli, 'SELECT rty_Name FROM defRecTypes WHERE rty_ID IN ('. $fieldDetails['dty_PtrTargetRectypeIDs'] .')');
                if(!empty($rty_names)){
                    if(empty($extra_details)){
                        $extra_details = ', relation to ';
                    }
                    $extra_details .= implode(' | ', $rty_names);
                }
            }

            $details[$dty_ID] = array($idx=>'SEE NOTES AT START'.$extra_details);
        }elseif($dty_Type=='geo'){
            $details[$dty_ID] = array($idx=>array('geo'=>array('wkt'=>'WKT_VALUE')) );//'type'=>'TEXT',

        }elseif($dty_Type=='enum' || $dty_Type=='relationtype'){

            $extra_details = '';
            if(array_key_exists('dty_JsonTermIDTree', $fieldDetails)){ // retrieve list of vocab labels
                $trm_names = mysql__select_list2($mysqli, 'SELECT trm_Label FROM defTerms WHERE trm_ID IN ('. $fieldDetails['dty_JsonTermIDTree'] .')');
                if(!empty($trm_names)){
                    $extra_details = ' from ' . implode(' | ', $trm_names);
                }
            }

            $details[$dty_ID] = array($idx=>'VALUE'.$extra_details);
        }elseif($dty_Type=='integer' || $dty_Type=='float' || $dty_Type=='year' ){
            $details[$dty_ID] = array($idx=>'NUMERIC');
        }elseif($dty_Type=='blocktext' ){
            $details[$dty_ID] = array($idx=>'MEMO_TEXT');
        }elseif($dty_Type=='date' ){
            $details[$dty_ID] = array($idx=>'DATE');
        }else{
            $details[$dty_ID] = array($idx=>'TEXT');
        }

        $idx++;
    }
    $record['details'] = $details;

    return $record;
}


/**
 * Retrieves a single record by its ID, optionally including its details.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int $id The Record ID (`rec_ID`) of the record to retrieve.
 * @param bool|array $need_details (Optional) Determines if and which details are fetched.
 *                                 - `true` (default): Fetches all details for the record.
 *                                 - `false`: Fetches only the record header fields specified by `$fields`.
 *                                 - `array`: An array of Detail Type IDs to fetch.
 * @param string|null $fields (Optional) A comma-separated string of `Records` table fields to retrieve.
 *                            If null, a default set of header fields is fetched.
 * @return array|null An associative array representing the record (header and optionally details),
 *                    or null if the record is not found.
 */
function recordSearchByID($system, $id, $need_details = true, $fields = null)
{
    $id = intval($id);
    if($fields==null){
        $fields = "rec_ID,
        rec_RecTypeID,
        rec_Title,
        rec_URL,
        rec_ScratchPad,
        rec_OwnerUGrpID,
        rec_NonOwnerVisibility,
        rec_URLLastVerified,
        rec_URLErrorMessage,
        rec_Added,
        rec_Modified,
        rec_AddedByUGrpID,
        rec_Hash,
        rec_FlagTemporary";
    }

    $mysqli = $system->getMysqli();
    $record = mysql__select_row_assoc( $mysqli,
        "select $fields from Records where rec_ID = $id");
    if ($need_details !== false && $record) {
        recordSearchDetails($system, $record, $need_details);
    }
    return $record;
}

/**
 * Retrieves the first value of a specified detail field from a record array.
 *
 * Assumes the record array has a 'details' key, which is an associative array
 * mapping Detail Type IDs to arrays of their values (since fields can be repeatable).
 * This function returns the first value from such an array for the given `$field_id`.
 *
 * @param array $record The record array, expected to contain a 'details' sub-array.
 * @param int $field_id The Detail Type ID of the field whose first value is to be retrieved.
 * @return mixed|null The first value of the specified detail field, or null if the field
 *                    is not set, is empty, or the 'details' array doesn't exist.
 */
function recordGetField($record, $field_id){
    $field_id = intval($field_id);
    $value_array = @$record['details'][$field_id];
    if(!isEmptyArray($value_array) && is_array($value_array)){ // Check if it's a non-empty array
        // Detail values are often stored as [dtl_ID => value]. We need the actual value.
        // If it's indexed by dtl_ID, array_shift would work on the numerically indexed array of values from fetch_assoc loop.
        // Let's assume $value_array is the array of actual values for $field_id.
        return array_shift($value_array); // Returns the first element
    }else{
        return null;
    }
}

/**
 * Fetches and attaches detail field information to a given record array.
 *
 * This function queries `recDetails` (and related tables like `defDetailTypes`, `Records` for resources,
 * `recUploadedFiles` for files) to populate the 'details' element of the `$record` array (passed by reference).
 * It handles various detail types, formatting their values appropriately (e.g., WKT for geo, file info arrays, linked resource info).
 * It also considers field visibility based on user permissions and record ownership.
 *
 * Structure of `$record['details']` after population:
 * `[dty_ID => [dtl_ID => value, ...], ...]`
 *
 * Value format varies by `dty_Type`:
 * - "freetext", "blocktext", "date", "enum", etc.: Raw `dtl_Value`.
 * - "file": Array `['file' => fileinfo_array_from_fileGetFullInfo, 'fileid' => obfuscated_id]`.
 * - "resource": If `$expanded` is true: `['id' => linked_rec_ID, 'type' => linked_recTypeID, 'title' => linked_rec_Title, 'hhash' => linked_rec_Hash]`.
 *               If `$expanded` is false: Raw `dtl_Value` (the linked record ID).
 * - "geo": Array `['geo' => ['type' => dtl_Value, 'wkt' => dtl_Geo_WKT_string]]`.
 *          If linked place: `type` becomes `geotype:linked_place_ID`.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param array &$record The record array (passed by reference) to which details will be added. Must contain 'rec_ID'.
 * @param bool|array $detail_types Specifies which detail types to fetch:
 *                                 - `true` (default): Fetches all detail types for the record's type, subject to visibility.
 *                                 - `array`: An array of specific Detail Type IDs or string type names (e.g., "geo", "date") to fetch.
 * @param bool $expanded If true (default for most cases), for resource type details, fetches linked record's ID, type, title, and hash.
 *                       If false, resource details will only contain the target record ID.
 * @return void Modifies `$record` by reference.
 */
function recordSearchDetails($system, &$record, $detail_types, $expanded=true) {

    $mysqli = $system->getMysqli();

    $recID = $record['rec_ID'];

    $squery =
    'select dtl_ID,
    dtl_DetailTypeID,
    dtl_Value,
    ST_asWKT(dtl_Geo) as dtl_Geo,
    dtl_UploadedFileID,
    dty_Type ';
    
    if($expanded){
        $squery .= ', rec_ID,rec_Title,rec_RecTypeID,rec_Hash ';
    }
    $squery .= ' from recDetails left join defDetailTypes on dty_ID = dtl_DetailTypeID';
    
    if($expanded){
        $squery .= " left join Records on rec_ID = dtl_Value and dty_Type = 'resource'";
    }

    $swhere = " WHERE dtl_RecID = $recID";

    $relmarker_fields = array();

    if(!isEmptyArray($detail_types) ){

        if(is_numeric($detail_types[0]) && $detail_types[0]>0){ //by id

            $swhere .= SQL_AND.predicateId('dtl_DetailTypeID', $detail_types);
            $qr = SQL_RELMARKER_CONSTR.predicateId('dty_ID', $detail_types);
            $relmarker_fields =  mysql__select_all($mysqli, $qr);

        }else{ //by type
            $swhere .= ' AND dty_Type in ("'.implode('","',$detail_types).'")';
        }
    }

    //individual visibility for fields
    $rec_visibility = @$record['rec_NonOwnerVisibility'];
    $rec_owner = @$record['rec_OwnerUGrpID'];
    $rec_type = @$record['rec_RecTypeID'];

    if($rec_type!=null && $rec_type>0){

        $usr_groups = $system->getUserGroupIds();
        if(!is_array($usr_groups)) {$usr_groups = array();}
        array_push($usr_groups, 0);//everyone

        if($system->hasAccess() && in_array($rec_owner, $usr_groups)){
            //owner of record can see any field
            $detail_visibility_conditions = ' AND (IFNULL(rst_RequirementType,"")!="forbidden")';//ifnull needed for non-standard fields
        }else{
            $detail_visibility_conditions = array('(rst_NonOwnerVisibility IS NULL)');//not standard field
            if($system->hasAccess()){
                //logged in user can see viewable
                $detail_visibility_conditions[] = '(rst_NonOwnerVisibility="viewable")';
            }
            $detail_visibility_conditions[] = '((rst_NonOwnerVisibility="public" OR rst_NonOwnerVisibility="pending") AND IFNULL(dtl_HideFromPublic, 0)!=1)';

            $detail_visibility_conditions = ' AND (IFNULL(rst_RequirementType,"")!="forbidden") AND ('
            .implode(' OR ',$detail_visibility_conditions).')';
        }

        if($detail_visibility_conditions!=null){
            $squery .= 'left join defRecStructure rdr on rdr.rst_DetailTypeID = dtl_DetailTypeID and rdr.rst_RecTypeID = '.$rec_type;
            $swhere .= $detail_visibility_conditions;
        }
    }

    $squery .= $swhere;

    //main query for details
    $res = $mysqli->query($squery);

    $ruf_entity = null;
    $details = array();
    if($res){
        while ($rd = $res->fetch_assoc()) {
            // skip all invalid values
            if (( !$rd["dty_Type"] === "file" && $rd["dtl_Value"] === null ) ||
            (($rd["dty_Type"] === "enum" || $rd["dty_Type"] === "relationtype") && !$rd["dtl_Value"])) {
                continue;
            }

            if (! @$details[$rd["dtl_DetailTypeID"]]) {$details[$rd["dtl_DetailTypeID"]] = array();}

            $detailValue = null;

            switch ($rd["dty_Type"]) {
                case "blocktext":
                case "freetext":
                case "float":
                case "date":
                case "enum":
                case "relationtype":
                case "integer": case "boolean": case "year": case "urlinclude": // these shoudl no logner exist, retained for backward compatibility
                    $detailValue = $rd["dtl_Value"];
                    break;

                case "file":

                    $fileinfo = null;

                    if(!($rd['dtl_UploadedFileID']>0)){
                        // FIX on fly - @todo  remove on 2022-08-22
                        if($ruf_entity==null){
                            $ruf_entity = new DbRecUploadedFiles($system);
                        }
                        $fileinfo = $ruf_entity->registerURL($rd['dtl_Value'], false, $rd['dtl_ID']);
                    }else{
                        $fileinfo = fileGetFullInfo($system, $rd["dtl_UploadedFileID"]);
                        if(!isEmptyArray($fileinfo)){
                            $fileinfo = $fileinfo[0];//
                        }
                    }

                    if($fileinfo){
                        $detailValue = array("file" => $fileinfo, "fileid"=>$fileinfo["ulf_ObfuscatedFileID"]);
                    }

                    break;

                case "resource":
                    if($expanded){
                        $detailValue = array(
                            "id" => $rd["rec_ID"],
                            "type"=>$rd["rec_RecTypeID"],
                            "title" => $rd["rec_Title"],
                            "hhash" => $rd["rec_Hash"]
                        );
                    }else{
                        $detailValue = $rd["dtl_Value"];
                    }
                    break;

                case "geo":
                    if ($rd["dtl_Value"]  &&  $rd["dtl_Geo"]) {
                        $detailValue = array(
                            "geo" => array(
                                "type" => $rd["dtl_Value"],
                                "wkt" => $rd["dtl_Geo"]
                            )
                        );
                    }
                    break;

                case "separator":    // this should never happen since separators are not saved as details, skip if it does
                case "relmarker":    // relmarkers are places holders for display of relationships constrained in some way
                default:
                    break;
            }

            if ($detailValue!=null && $detailValue!='') {
                $details[$rd["dtl_DetailTypeID"]][$rd["dtl_ID"]] = $detailValue;
            }
        }

        //special case for RT_CMS_MENU - JOIN all descriptions
        $system->defineConstant('DT_EXTENDED_DESCRIPTION');
        if($system->defineConstant('RT_CMS_MENU') && RT_CMS_MENU==@$record['rec_RecTypeID']
        && is_array(@$details[DT_EXTENDED_DESCRIPTION]))
        {
            $details[DT_EXTENDED_DESCRIPTION] = array(implode('',$details[DT_EXTENDED_DESCRIPTION]));
        }

        $res->close();
    }



    $record["details"] = $details;
}

/**
 * Fetches and adds information about "relmarker" type relationships to a record's details.
 *
 * Relmarkers are special detail types (`dty_Type = "relmarker"`) that define a specific kind of relationship
 * view or constraint. This function identifies such relmarker fields applicable to the given record's type
 * (or specified by `$detail_types`). For each relmarker, it finds related records (using `recordSearchRelated`)
 * and filters them based on constraints defined in the relmarker's `dty_JsonTermIDTree` (allowed relation types)
 * and `dty_PtrTargetRectypeIDs` (allowed record types for the other side of the relation).
 *
 * The found related records are then added to the `$record['details']` array, keyed by the relmarker's dty_ID.
 * Each entry is an array of objects, where each object represents a related record and includes its
 * 'id', 'type', 'title', and 'relation_id' (the ID of the relationship record itself).
 *
 * @param \hserv\System $system The Heurist system object.
 * @param array &$record The record array (passed by reference) to which relationship details will be added. Must contain 'rec_ID'.
 * @param array|null $detail_types An array of Detail Type IDs. If provided, only relmarkers within this list are processed.
 *                                 If null or empty, it might attempt to find all relmarkers applicable to the record's type (though current logic requires explicit IDs or types for initial query).
 * @return void Modifies `$record['details']` by reference.
 */
function recordSearchDetailsRelations($system, &$record, $detail_types) {

    $mysqli = $system->getMysqli();
    $recID = intval($record['rec_ID']);

    $relmarker_fields = array();

    if(is_array($detail_types) && !empty($detail_types) ){

        if(is_numeric($detail_types[0]) && $detail_types[0]>0){ //by id

            $qr = SQL_RELMARKER_CONSTR.predicateId('dty_ID',$detail_types);

        }else{ //by type

            $qr = 'SELECT dty_ID, dty_JsonTermIDTree, dty_PtrTargetRectypeIDs '
            .' FROM defDetailTypes, defRecStructure, Records'
            .' WHERE rec_ID='.$recID
            .' AND dty_ID=rst_DetailTypeID AND rst_RecTypeID=rec_RecTypeID AND dty_Type = "relmarker"';
        }

        $relmarker_fields =  mysql__select_all($mysqli, $qr);
    }

    //query for relmarkers
    if(!isEmptyArray($relmarker_fields)){
        $terms = new DbsTerms($system, dbs_GetTerms($system));

        // both directions (0), need headers
        $related_recs = recordSearchRelated($system, $recID, 0, true, 2);
        // filter out by allowed relation type and constrained record type

        foreach ($relmarker_fields as $dty_ID=>$constraints) {

            $allowed_terms = null; //$terms->treeData($constraints[1], 'set');
            $constr_rty_ids = explode(',', $constraints[2]);
            if(empty($constr_rty_ids)) {$constr_rty_ids = false;}

            //find among related record that satisfy contraints
            foreach ($related_recs['data']['direct'] as $relation){

                if(!$allowed_terms || in_array($relation->trmID, $allowed_terms)){

                    $rty_ID = $related_recs['data']['headers'][$relation->targetID][1];//rectype id
                    if(!$constr_rty_ids || in_array($rty_ID, $constr_rty_ids) ){
                        if(!@$record["details"][$constraints[0]]) {$record["details"][$constraints[0]] = array();}
                        $record["details"][$constraints[0]][] = array('id'=>$relation->targetID,
                            'type'=>$rty_ID,
                            'title'=>$related_recs['data']['headers'][$relation->targetID][0],
                            'relation_id'=>$relation->relationID);
                    }
                }
            }
            foreach ($related_recs['data']['reverse'] as $relation){

                if(!$allowed_terms || in_array($relation->trmID, $allowed_terms)){

                    $rty_ID = $related_recs['data']['headers'][$relation->sourceID][1];//rectype id
                    if(!$constr_rty_ids || in_array($rty_ID, $constr_rty_ids) ){
                        if(!@$record["details"][$constraints[0]]) {$record["details"][$constraints[0]] = array();}
                        $record["details"][$constraints[0]][] = array('id'=>$relation->sourceID,
                            'type'=>$rty_ID,
                            'title'=>$related_recs['data']['headers'][$relation->sourceID][0],
                            'relation_id'=>$relation->relationID);
                    }
                }

            }
        }
    }

}

/**
 * Retrieves raw detail data for a given record ID.
 *
 * Fetches `dtl_ID`, `dtl_DetailTypeID`, `dtl_Value`, `dtl_Geo` (as WKT), and `dtl_UploadedFileID`
 * for all details associated with the specified `rec_ID`.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int $rec_ID The Record ID for which to fetch raw details.
 * @return array|null An associative array where keys are `dtl_ID` and values are associative arrays
 *                    of the detail fields, or null if an error occurs or no details are found.
 *                    (Note: `mysql__select_assoc` typically returns an array of rows if dtl_ID is not unique,
 *                     or a single associative array if dtl_ID is used as the key in `mysql__select_assoc`'s implementation).
 *                     Assuming it returns an array of rows, each being an assoc array of fields.
 */
function recordSearchDetailsRaw($system, $rec_ID) {
    $rec_ID = intval($rec_ID);
    $query =
    "select dtl_ID,dtl_DetailTypeID,dtl_Value,ST_asWKT(dtl_Geo) as dtl_Geo,dtl_UploadedFileID"
    ." from recDetails where dtl_RecID = $rec_ID";

    return mysql__select_assoc($system->getMysqli(), $query); // Behavior depends on mysql__select_assoc implementation
}

/**
 * Generates a string containing a short description of a record, including its ID, name (if DT_NAME exists),
 * and links to its XML and HTML representations in Heurist.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int|array $record Either a record ID (int) or a record array (which should have 'rec_ID' and optionally 'details[DT_NAME]').
 *                          If an ID is passed, `recordSearchDetails` is called to fetch DT_NAME.
 * @return string A formatted string with record information and links.
 */
function recordLinksFileContent($system, $record){

    if(is_numeric($record)){ // If $record is just an ID
        $record_id = intval($record);
        $record_array = array("rec_ID" => $record_id);
        // Fetch DT_NAME if defined, to include in the output
        if ($system->defineConstant('DT_NAME') && DT_NAME > 0) {
            recordSearchDetails($system, $record_array, array(DT_NAME));
        }
        $record = $record_array; // Now $record is an array
    }
    $rec_id_val = $record['rec_ID'] ?? 'N/A';

    $url = HEURIST_SERVER_URL . HEURIST_DEF_DIR . '?db='.$system->dbname().'&recID='.$record['rec_ID'];

    return 'Downloaded from: '.$system->settings->get('sys_dbName', true)."\n"
    .'Dataset ID: '.$record['rec_ID']."\n"
    .(is_array(@$record['details'][DT_NAME])?'Dataset: '.array_values($record["details"][DT_NAME])[0]."\n":'')
    .'Full metadata (XML): '.$url."\n"
    .'Human readable (html): '.($url.'&fmt=html')."\n";

}

/**
 * Searches for geographic details (WKT, geo type) from records linked to a given source record.
 * This is used to find associated place information for a record that might not have direct geo-details.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int $recID The `rec_ID` of the source record for which to find linked geographic details.
 * @param array|bool $find_geo_by_linked_rty Specifies which linked record types should be considered as places.
 *                                           - If `true`, defaults to `RT_PLACE`.
 *                                           - If an array, it's a list of Record Type IDs to consider as places.
 *                                           - If empty or false, the function returns empty details.
 * @param array|null $find_geo_by_linked_dty (Optional) An array of Detail Type IDs. If provided,
 *                                           only links via these pointer fields on the source record
 *                                           will be followed to find places. If null or empty,
 *                                           links via any pointer field are considered.
 * @return array An associative array of geographic details found, structured similarly to how
 *               `recordSearchDetails` formats geo-details:
 *               `[dty_ID_of_geo_field_in_place => [dtl_ID_of_geo_detail => [
 *                   "geo" => ["type"=>geo_type, "wkt"=>wkt_string, "placeID"=>linked_place_recID,
 *                             "pointerDtyID"=>dty_ID_of_pointer_on_source, "relationID"=>relation_type_if_any]
 *               ]]]`
 *               Returns an empty array if no relevant linked geo-details are found or on error.
 */
function recordSearchGeoDetails($system, $recID, $find_geo_by_linked_rty, $find_geo_by_linked_dty) {
    $recID = intval($recID);
    $details = array();

    if ($find_geo_by_linked_rty === true) {
        if ($system->defineConstant('RT_PLACE') && RT_PLACE > 0) {
            $find_geo_by_linked_rty = array(RT_PLACE);
        } else {
            $find_geo_by_linked_rty = array(); // No default RT_PLACE defined
        }
    }

    if(isEmptyArray($find_geo_by_linked_rty)){
        return $details; // No record types specified to search for geo-details in.
    }
    $find_geo_by_linked_rty = prepareIds($find_geo_by_linked_rty, true); // Ensure clean array of IDs

    $squery = 'SELECT rl.rl_SourceID, rd.dtl_DetailTypeID, rd.dtl_Value, ST_asWKT(rd.dtl_Geo) as dtl_Geo, '
            . 'rl.rl_TargetID, rd.dtl_ID, rl.rl_DetailTypeID as pointerDtyID, rl.rl_RelationTypeID' // Added aliases for clarity
            . ' FROM recDetails rd'
            . ' JOIN recLinks rl ON rd.dtl_RecID = rl.rl_TargetID' // Link from recDetails (of place) to recLinks
            . ' JOIN Records r ON rl.rl_TargetID = r.rec_ID'      // Join Records table for the place
            . ' WHERE (rd.dtl_Geo IS NOT NULL)'
            . ' AND r.rec_RecTypeID IN (' . implode(',', $find_geo_by_linked_rty) . ')'
            . ' AND rl.rl_SourceID = '.$recID;

    if(!isEmptyArray($find_geo_by_linked_dty)){
        $find_geo_by_linked_dty = prepareIds($find_geo_by_linked_dty, true);
        $squery .= ' AND rl.rl_DetailTypeID IN (' . implode(',', $find_geo_by_linked_dty) . ')';
    }
    $squery .= ' ORDER BY rl.rl_ID'; // Order by recLinks ID

    $mysqli = $system->getMysqli();
    $res = $mysqli->query($squery);
    if(!$res){
        // Optional: $system->addError(...)
        return $details;
    }

    while ($row_data = $res->fetch_assoc()) {
        if ($row_data["dtl_Value"] && $row_data["dtl_Geo"]) {
            $detailValue = array(
                "geo" => array(
                    "type" => $row_data["dtl_Value"],         // Geo type from place's dtl_Value
                    "wkt" => $row_data["dtl_Geo"],            // WKT string from place's dtl_Geo
                    "placeID" => (int)$row_data["rl_TargetID"],    // ID of the linked place record
                    "pointerDtyID" => (int)$row_data["pointerDtyID"], // dty_ID of the pointer field on source record
                    "relationID" => (int)$row_data['rl_RelationTypeID'] // rel_RelationTypeID if it was a relationship link
                )
            );
            // Keyed by the dty_ID of the geo field *in the place record*
            $geo_field_dty_id = (int)$row_data["dtl_DetailTypeID"];
            $geo_detail_dtl_id = (int)$row_data["dtl_ID"];
            if (!isset($details[$geo_field_dty_id])) {
                $details[$geo_field_dty_id] = array();
            }
            $details[$geo_field_dty_id][$geo_detail_dtl_id] = $detailValue;
        }
    }
    $res->close();

    return $details;
}

/**
 * Recursively replaces a placeholder '$IDS' within a query structure with a given record ID.
 *
 * This function traverses a nested array (representing a JSON query). When it encounters
 * a string value equal to '$IDS', it replaces that value with `$recID`.
 * If the query structure itself is the string '$IDS', it's replaced by `array('ids' => $recID)`.
 *
 * @param array|string &$q The query structure (array or string) to modify. Passed by reference.
 * @param int $recID The record ID to substitute for '$IDS'.
 * @return void Modifies `$q` by reference.
 */
function __fillQuery(&$q, $recID){
    if(is_array($q)){
        foreach ($q as $idx => &$predicate_container){ // Ensure modification by reference for array elements
            if (is_array($predicate_container)) {
                foreach ($predicate_container as $key => &$val) { // Ensure modification by reference
                    if( is_array($val)){
                        __fillQuery($val, $recID); // Recursive call, $val is already by reference
                        $q[$idx][$key] = $val;
                    } elseif( is_string($val) && $val == '$IDS') {
                        $val = $recID; // Substitute
                        $q[$idx][$key] = $recID;
                    }
                }
                unset($val); // Unset inner loop reference
            }
        }
        unset($predicate_container); // Unset outer loop reference
    } elseif( is_string($q) && $q == '$IDS') {
        $q = array('ids'=>$recID); // Replace the whole query string
    }
}

/**
 * Searches for details of records linked from a specific source record via a given query.
 *
 * First, it executes the provided `$query` (after substituting '$IDS' with `$recID`)
 * to find a set of linked record IDs. Then, for each of these linked record IDs,
 * it fetches details specified by `$dty_IDs`.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int $recID The source Record ID to use as context (replaces '$IDS' in the query).
 * @param array|string $dty_IDs An array or comma-separated string of Detail Type IDs to fetch for the linked records.
 * @param array|string $query The query structure (JSON array or string) used to find linked records.
 *                            It should contain '$IDS' as a placeholder for `$recID`.
 * @return array An associative array where keys are dty_IDs and values are arrays of detail values
 *               (accumulated from all found linked records).
 */
function recordSearchLinkedDetails($system, $recID, $dty_IDs, $query) {
    $recID = intval($recID);
    $dty_IDs_prepared = prepareIds($dty_IDs, true); // Ensure $dty_IDs is a clean array of IDs

    __fillQuery($query, $recID); // Substitute $IDS placeholder in $query with $recID

    // Find linked record IDs based on the modified query
    $search_params = array('detail'=>'ids', 'q'=>$query, 'needall'=>1); // Ensure all linked IDs are fetched
    $linked_recs_result = recordSearch($system, $search_params);

    if ($linked_recs_result['status'] !== HEURIST_OK || empty($linked_recs_result['data']['records'])) {
        return array(); // Return empty if no linked records found or if there was an error
    }
    $linked_rec_ids = $linked_recs_result['data']['records'];

    $accumulated_details = array();
    foreach($linked_rec_ids as $linked_rec_id){
        $current_record_shell = array('rec_ID' => intval($linked_rec_id));
        // Fetch specified details for the current linked record
        recordSearchDetails($system, $current_record_shell, $dty_IDs_prepared);

        foreach($current_record_shell['details'] as $dty_ID => $field_details_for_dty){
            if(!isset($accumulated_details[$dty_ID])){
                $accumulated_details[$dty_ID] = array();
            }
            // Merge details for this dty_ID, ensuring dtl_ID keys are preserved
            foreach ($field_details_for_dty as $dtl_ID => $value){

                $accumulated_details[$dty_ID][$dtl_ID] = $value;
            }
        }
    }
    return $accumulated_details;
}

/**
 * Fetches specified details for a list of record IDs.
 *
 * Iterates through each record ID in `$recIDs`, calls `recordSearchDetails` to fetch
 * the details specified by `$dty_IDs` for that record, and aggregates the results.
 * The final structure is an associative array keyed by `rec_ID`.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param array|string $recIDs An array or comma-separated string of Record IDs.
 * @param array|string $dty_IDs An array or comma-separated string of Detail Type IDs to fetch for each record.
 * @param bool $expanded (Optional) Passed to `recordSearchDetails`. If true, resource details are expanded. Default true.
 * @return array An associative array where keys are `rec_ID`s and values are arrays
 *               of their fetched details, structured as `[dty_ID => [dtl_ID => value, ...]]`.
 */
function recordSearchDetailsForRecIds($system, $recIDs, $dty_IDs, $expanded=true) {
    $recIDs_prepared = prepareIds($recIDs, true);
    $dty_IDs_prepared = prepareIds($dty_IDs, true);

    $all_records_details = array();
    foreach($recIDs_prepared as $recid){
        $current_record_shell = array('rec_ID' => $recid);
        recordSearchDetails($system, $current_record_shell, $dty_IDs_prepared, $expanded);
        
        // Even if $current_record_shell['details'] is empty, we add an entry for the recid
        $all_records_details[$recid] = $current_record_shell['details'] ?? array();
    }
    return $all_records_details;
}

/**
 * Loads personal tags (for the current user) for a given record ID.
 *
 * @param \hserv\System $system The Heurist system object, used to get current user ID and DB connection.
 * @param int $rec_ID The Record ID for which to fetch personal tags.
 * @return array An array of tag_Text strings, or an empty array if no tags or on error.
 */
function recordSearchPersonalTags($system, $rec_ID) {
    $rec_ID = intval($rec_ID);
    $mysqli = $system->getMysqli();
    $current_user_id = $system->getUserId();

    if ($current_user_id <= 0) {
        return array(); // No user, no personal tags
    }

    return mysql__select_list2($mysqli,
        'SELECT tag_Text FROM usrRecTagLinks, usrTags WHERE '
        ."tag_ID = rtl_TagID and tag_UGrpID= ".$current_user_id." and rtl_RecID = $rec_ID order by rtl_Order");
}

?>
