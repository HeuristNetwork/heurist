<?php
//@TODO convert to class

/**
* Library to search records
*
* recordSearchMinMax - Find minimal and maximal values for given detail type and record type
* recordSearchFacets - returns counts for facets for given query
* 
* recordSearchRelatedIds - search all related (links and releationship) records for given set of records recursively
* recordSearchRelated
* recordLinkedCount  - search count by target record type for given source type and base field
* recordSearchPermissions  - all view group permissions for given set of records
* recordGetOwnerVisibility
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
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

//require_once (dirname(__FILE__).'/../System.php');
require_once (dirname(__FILE__).'/db_users.php');
require_once (dirname(__FILE__).'/db_files.php');  //it includes utils_file.php
require_once (dirname(__FILE__).'/compose_sql.php');
require_once (dirname(__FILE__).'/compose_sql_new.php');
require_once (dirname(__FILE__).'/db_structure.php');
require_once (dirname(__FILE__).'/db_searchfacets.php');

require_once ( dirname(__FILE__).'/../../common/php/Temporal.php');

/**
* Find minimal and maximal values for given detail type and record type
*
* @param mixed $system
* @param mixed $params - array  rt - record type, dt - detail type
*/
function recordSearchMinMax($system, $params){

    if(@$params['rt'] && @$params['dt']){

        $mysqli = $system->get_mysqli();
        //$currentUser = $system->getCurrentUser();

        $query = 'SELECT MIN(CAST(dtl_Value as decimal)) as MIN, MAX(CAST(dtl_Value as decimal)) AS MAX FROM Records, recDetails';
        $where_clause  = ' WHERE rec_ID=dtl_RecID AND rec_RecTypeID='
        .$params['rt'].' AND dtl_DetailTypeID='.$params['dt']." AND dtl_Value is not null AND dtl_Value!=''";

        $currUserID = $system->get_user_id();
        if( $currUserID > 0 ) {
            $q2 = 'select wss_RecID from usrWorkingSubsets where wss_OwnerUGrpID='.$currUserID.' LIMIT 1';
            if(mysql__select_value($mysqli, $q2)>0){
                $query = $query.', usrWorkingSubsets ';
                $where_clause = $where_clause.' AND wss_RecID=rec_ID AND wss_OwnerUGrpID='
                .$currUserID;
            }

        }
        //@todo - current user constraints

        $res = $mysqli->query($query.$where_clause);
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
* parses string  $resource - t:20 f:41
* and returns array of recordtype and detailtype IDs
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

//
// returns counts for facets for given query
//
//  1) It finds all possible facet values for current query
//  2) Calculates counts for every value 
// 
//
// @param mixed $system
// @param mixed $params - array or parameters
//      q - JSON query array
//      field - field id to search
//      type - field type (todo - search it dynamically with getDetailType)
//      needcount
// @return
//
function recordSearchFacets($system, $params){

    define('_FT_SELECT', 1);
    define('_FT_LIST', 2);
    define('_FT_COLUMN', 3);

    //for error message
    $savedSearchName = @$params['qname']?"Saved search: ".$params['qname']."<br>":"";

    if(@$params['q'] && @$params['field']){

        $mysqli = $system->get_mysqli();

        $currentUser = $system->getCurrentUser();
        $dt_type     = @$params['type'];
        $step_level  = @$params['step'];
        $fieldid     = $params['field'];
        $count_query = @$params['count_query'];
        $facet_type =  @$params['facet_type']; //0 direct search search, 1 - select/slider, 2 - list inline, 3 - list column
        $facet_groupby = @$params['facet_groupby'];  //by first char for freetext, by year for dates, by level for enum
        $vocabulary_id = @$params['vocabulary_id'];  //special case for groupby first level
        $limit         = @$params['limit']; //limit for preview

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

        if( $system->get_user_id() > 0 ) {
            //use subset for initial search only
            $params['use_user_wss'] = @$params['step']==0;
        } else {
            $params['use_user_wss'] = false;  
        }           
        
        //get SQL clauses for current query
        $qclauses = get_sql_query_clauses_NEW($mysqli, $params, $currentUser);

        $select_field  = "";
        $detail_link   = "";
        $details_where = "";

        if($fieldid=="rectype"){
            $select_field = "r0.rec_RecTypeID";
        }else if($fieldid=='recTitle' || $fieldid=='title'){
            $select_field = "r0.rec_Title";
            $dt_type = "freetext";
        }else if($fieldid=='recModified' || $fieldid=='modified'){
            $select_field = "r0.rec_Modified";
        }else{
            if(strpos($fieldid,',')>0 && getCommaSepIds($fieldid)!=null){
                $compare_field = 'IN ('.$fieldid.')';
            }else{
                $compare_field = '='.$fieldid;
            }

            $select_field  = "dt0.dtl_Value";
            $detail_link   = ", recDetails dt0 ";
            $details_where = " AND (dt0.dtl_RecID=r0.rec_ID and dt0.dtl_DetailTypeID $compare_field) AND (NULLIF(dt0.dtl_Value, '') is not null)";
            //$detail_link   = " LEFT JOIN recDetails dt0 ON (dt0.dtl_RecID=r0.rec_ID and dt0.dtl_DetailTypeID=".$fieldid.")";
            //$details_where = " and (dt0.dtl_Value is not null)";
        }

        $select_clause = "";
        $grouporder_clause = "";

        if($dt_type=="date"){

            $details_where = $details_where.' AND (cast(getTemporalDateString('.$select_field.') as DATETIME) is not null '
            .'OR (cast(getTemporalDateString('.$select_field.') as SIGNED) is not null  AND '
            .'cast(getTemporalDateString('.$select_field.') as SIGNED) !=0) )';

            //for dates we search min and max values to provide data to slider
            //@todo facet_groupby   by year, day, month, decade, century
            if($facet_groupby=='year' || $facet_groupby=='decade' || $facet_groupby=='century'){

                $select_field = '(cast(getTemporalDateString('.$select_field.') as SIGNED))';
                //'YEAR(cast(getTemporalDateString('.$select_field.') as DATE))';
                if($facet_groupby=='decade'){
                    $select_field = $select_field.' DIV 10 * 10';
                }else if($facet_groupby=='century'){
                    $select_field = $select_field.' DIV 100 * 100';
                }


                $select_clause = "SELECT $select_field as rng, count(*) as cnt ";
                if($grouporder_clause==''){
                    $grouporder_clause = ' GROUP BY rng ORDER BY rng';
                    //" GROUP BY $select_field ORDER BY $select_field";
                }

            }else{    

                $select_field = "cast(if(cast(getTemporalDateString(".$select_field.") as DATETIME) is null,"
                ."concat(cast(getTemporalDateString(".$select_field.") as SIGNED),'-1-1'),"
                ."getTemporalDateString(".$select_field.")) as DATETIME)";

                $select_clause = "SELECT min($select_field) as min, max($select_field) as max, count(distinct r0.rec_ID) as cnt ";

            }

        }
        else if($dt_type=="enum" && $facet_groupby=='firstlevel' && $vocabulary_id!=null){ 

            $params_enum = null;
            if($count_query){
                $params_enum = json_decode( json_encode($params), true);
            }

            $qclauses = get_sql_query_clauses_NEW($mysqli, $params_enum, $currentUser);


            //NOTE - it applies for VOCABULARY only (individual selection of terms is not applicable)

            // 1. get first level of terms using $vocabulary_id 
            $first_level = getTermChildren($vocabulary_id, $system, true); //get first level for vocabulary



            // 2.  find all children as plain array  [[parentid, child_id, child_id....],.....]
            $terms = array();
            foreach ($first_level as $parentID){
                $children = getTermChildren($parentID, $system, false); //get first level for vocabulary    
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
                    $query =  $select_clause.$qclauses2['from'].' WHERE '.$qclauses2['where'];
                }else{
                    $d_where = $details_where.' AND ('.$select_field.' IN ('.implode(',', $vocab).'))';
                    //count query
                    $query =  $select_clause.$qclauses['from'].$detail_link.' WHERE '.$qclauses['where'].$d_where;
                }

                /*if($limit>0){
                $query = $query.' LIMIT '.$limit;    
                }*/


                $res = $mysqli->query($query);
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
        else if((($dt_type=="integer" || $dt_type=="float") && $facet_type==_FT_SELECT) || $dt_type=="year"){

            //if ranges are not defined there are two steps 1) find min and max values 2) create select case
            $select_field = "cast($select_field as DECIMAL)";

            $select_clause = "SELECT min($select_field) as min, max($select_field) as max, count(distinct r0.rec_ID) as cnt ";

        }
        else { //freetext and other if($dt_type==null || $dt_type=="freetext")

            if($dt_type=="integer" || $dt_type=="float"){

                $select_field = "cast($select_field as DECIMAL)";

            }else if($step_level==0 && $dt_type=="freetext"){ 

                $select_field = 'SUBSTRING(trim('.$select_field.'), 1, 1)';    //group by first charcter                }
            }

            if($params['needcount']==1){

                $select_clause = "SELECT $select_field as rng, count(DISTINCT r0.rec_ID) as cnt ";
                if($grouporder_clause==""){
                    $grouporder_clause = " GROUP BY $select_field ORDER BY $select_field";
                }

            }else{ //count for related  if($params['needcount']==2)

                $tab = 'r0';
                while(strpos($qclauses["from"], 'Records '.$tab.'_0')>0){
                    $tab = $tab.'_0';
                }
                $select_clause = "SELECT $select_field as rng, count(DISTINCT ".$tab.".rec_ID) as cnt ";

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
        $query =  $select_clause.$qclauses["from"].$detail_link." WHERE ".$qclauses["where"].$details_where.$grouporder_clause;

        /*
        if($limit>0){
        $query = $query.' LIMIT '.$limit;    
        }
        */

        //


        $res = $mysqli->query($query);
        if (!$res){
            $response = $system->addError(HEURIST_DB_ERROR, $savedSearchName
                .'Facet query error(B). '.$query);// 'Parameters:'.print_r($params, true), $mysqli->error);
            //'.$query.' 
        }else{
            $data = array();

            while ( $row = $res->fetch_row() ) {

                if((($dt_type=="integer" || $dt_type=="float") && $facet_type==_FT_SELECT)  || 
                (($dt_type=="year" || $dt_type=="date") && $facet_groupby==null)  ){
                    $third_element = $row[2];          // slider - third parameter is MAX for range
                }else if ($dt_type=="year" || $dt_type=="date") {

                    if($facet_groupby=='decade'){
                        $third_element = $row[0]+10;
                        //$row[0] = $row[0].'-01-01';
                    }else if($facet_groupby=='century'){
                        $third_element = $row[0]+100;
                        //$row[0] = $row[0].'-01-01';
                    }

                    $third_element = $row[0];
                }else if($step_level==0 && $dt_type=="freetext"){
                    $third_element = $row[0].'%';      // first character
                }else if($step_level>0 || $dt_type!='freetext'){
                    $third_element = $row[0];
                    if($dt_type=='freetext'){
                        $third_element = ('='.$third_element);   
                    }
                }

                //value, count, second value(max for range) or search value for firstchar
                array_push($data, array($row[0], $row[1], $third_element ));
            }
            $response = array("status"=>HEURIST_OK, "data"=> $data, "svs_id"=>@$params['svs_id'], 
                "request_id"=>@$params['request_id'], //'dbg_query'=>$query,
                "facet_index"=>@$params['facet_index'], 
                'q'=>$params['q'], 'count_query'=>$count_query );
            $res->close();
        }

    }else{
        $response = $system->addError(HEURIST_INVALID_REQUEST, $savedSearchName."Facet query parameters are invalid. Try to edit and correct this facet search");
    }

    return $response;
}

//
//
//
function __assignFacetValue($params, $subs){
    foreach ($params as $key=>$value){
        if(is_array($value)){
            $params[$key] = __assignFacetValue($value, $subs);
        }else if($value=='$FACET_VALUE'){
            $params[$key] = $subs;
            return $params;
        }
    }
    return $params;
}

/**
* search all related (links and releationship) records for given set of records
* it searches links recursively and adds found records into original array  $ids 
* 
* @param mixed $system
* @param mixed $ids
* @param mixed $direction  -  1 direct/ -1 reverse/ 0 both
*/
function recordSearchRelatedIds($system, &$ids, $direction=0, $no_relationships=false, 
        $depth=0, $max_depth=1, $limit=0, $new_level_ids=null, $temp_ids=null){

    if($depth>=$max_depth) return;

    if($new_level_ids==null) $new_level_ids = $ids;

    if(!($direction==1||$direction==-1)){
        $direction = 0;
    }

    $mysqli = $system->get_mysqli();

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
                    }else if(!in_array($id, $ids)){
                        array_push($res1, $id); //add relationship record   
                    }
                }

                $id = intval($row[0]);     
                if(!in_array($id, $ids)) array_push($res1, $id);
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
                    }else if(!in_array($id, $ids)){
                        array_push($res2, $id);   
                    }
                }

                $id = intval($row[0]);     
                if(!in_array($id, $ids)) array_push($res2, $id);
            }
            $res->close();
        }
    }

    if(is_array($res1) && is_array($res2) && count($res1)>0 && count($res2)>0){
        $res = array_merge_unique($res1, $res2);
    }else if(is_array($res1) && count($res1)>0){
        $res = $res1;
    }else{
        $res = $res2;
    }

    //find new level
    if(is_array($res) && count($res)>0){
        $ids = array_merge_unique($ids, $res);

        if($limit>0 && count($ids)>=$limit){
            $ids = array_slice($ids,0,$limit);
        }else{
            recordSearchRelatedIds($system, $ids, $direction, $no_relationships, $depth+1, $max_depth, $limit, $res, $temp_ids);    
        }

    }
    return;    
}

/**
* Finds all related (and linked) record IDs for given set record IDs
*
* @param mixed $system
* @param mixed $ids -
* @param mixed $direction -  1 direct/ -1 reverse/ 0 both
* @param mixed $need_headers - if "true" returns array of titles,ownership,visibility for linked records
*                              if "ids" returns ids only 
* @param mixed $link_type 0 all, 1 links, 2 relations
*
* @return array of direct and reverse links (record id, relation type (termid), detail id)  
*/
function recordSearchRelated($system, $ids, $direction=0, $need_headers=true, $link_type=0){

    if(!@$ids){
        return $system->addError(HEURIST_INVALID_REQUEST, 'Invalid search request');
    }
    if(is_array($ids)){
        $ids = implode(",", $ids);
    }
    if(!($direction==1||$direction==-1)){
        $direction = 0;
    }
    if(!($link_type>=0 && $link_type<3)){
        $link_type = 0;
    }
    if($link_type==2){ //relations only
        $sRelCond  = 'AND (rl_RelationID IS NOT NULL)';
    }else if($link_type==1){ //links only
        $sRelCond  = 'AND (rl_RelationID IS NULL)';
    }else{
        $sRelCond = '';
    }

    $rel_ids = array(); //relationship records (rt #1)

    $direct = array();
    $reverse = array();
    $headers = array(); //record title and type for main record
    $direct_ids = array(); //sources
    $reverse_ids = array(); //targets

    $mysqli = $system->get_mysqli();

    //query to find start and end date for relationship
    $system->defineConstant('DT_START_DATE');
    $system->defineConstant('DT_END_DATE');
    $query_rel = 'SELECT rec_ID, d2.dtl_Value t2, d3.dtl_Value t3 from Records '
    .' LEFT JOIN recDetails d2 on rec_ID=d2.dtl_RecID and d2.dtl_DetailTypeID='.(defined('DT_START_DATE')?DT_START_DATE:0)
    .' LEFT JOIN recDetails d3 on rec_ID=d3.dtl_RecID and d3.dtl_DetailTypeID='.(defined('DT_END_DATE')?DT_END_DATE:0)
    .' WHERE rec_ID=';



    if($direction>=0){

        //find all target related records
        $query = 'SELECT rl_SourceID, rl_TargetID, rl_RelationTypeID, rl_DetailTypeID, rl_RelationID FROM recLinks '
        .'where rl_SourceID in ('.$ids.') '.$sRelCond.' order by rl_SourceID';

        $res = $mysqli->query($query);
        if (!$res){
            return $system->addError(HEURIST_DB_ERROR, "Search query error on related records. Query ".$query, $mysqli->error);
        }else{
            while ($row = $res->fetch_row()) {
                $relation = new stdClass();
                $relation->recID = intval($row[0]);
                $relation->targetID = intval($row[1]);
                $relation->trmID = intval($row[2]);
                $relation->dtID  = intval($row[3]);
                $relation->relationID  = intval($row[4]);

                if($relation->relationID>0) {
                    $vals = mysql__select_row($mysqli, $query_rel.$relation->relationID);
                    if($vals!=null){
                        $relation->dtl_StartDate = $vals[1];
                        $relation->dtl_EndDate = $vals[2];
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
        .'where rl_TargetID in ('.$ids.') '.$sRelCond.' order by rl_TargetID';


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
                        $relation->dtl_StartDate = $vals[1];
                        $relation->dtl_EndDate = $vals[2];
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

        if(!is_array($ids)){
            $ids = explode(',',$ids);
        }
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
* Search count by target record type for given source type and base field
* 
* @param mixed $system
* @param mixed $rty_ID
* @param mixed $dty_ID - base field id
* @param mixed $direction -  1 direct/ -1 reverse/ 0 both
*/
function recordLinkedCount($system, $source_rty_ID, $target_rty_ID, $dty_ID){
    
    if(!( (is_array($target_rty_ID) || $target_rty_ID>0) && $source_rty_ID>0)){
        return $system->addError(HEURIST_INVALID_REQUEST, 'Invalid search request. Source and target record type not defined');
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
    $mysqli = $system->get_mysqli();
    
    $list = mysql__select_assoc2($mysqli, $query);

    if (!$list && $mysqli->error){
        return $system->addError(HEURIST_DB_ERROR, 'Search query error on related records. Query '.$query, $mysqli->error);
    }else{
        return array("status"=>HEURIST_OK, "data"=> $list);
    }
}


/**
* get all view group permissions for given set of records
*     
* @param mixed $system
* @param mixed $ids
*/
function recordSearchPermissions($system, $ids){
    if(!@$ids){
        return $system->addError(HEURIST_INVALID_REQUEST, "Invalid search request");
    }

    $ids = prepareIds($ids);

    $permissions = array();
    $mysqli = $system->get_mysqli();

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

//
//  returns SQL owner/visibility conditions for given user/group
//    
function recordGetOwnerVisibility($system, $ugrID){

    $is_db_owner = ($ugrID==2);

    $where2 = '';

    if(!$is_db_owner){

        $where2 = '(rec_NonOwnerVisibility="public")'; // in ("public","pending")

        if($ugrID>0){ //logged in 
            $mysqli = $system->get_mysqli();
            $wg_ids = user_getWorkgroups($this->mysqli, $ugrID);
            array_push($wg_ids, $ugrID);
            array_push($wg_ids, 0); // be sure to include the generic everybody workgroup

            //$this->from_clause = $this->from_clause.' LEFT JOIN usrRecPermissions ON rcp_RecID=r0.rec_ID ';

            $where2 = $where2.' OR (rec_NonOwnerVisibility="viewable")'; 
            // and (rcp_UGrpID is null or rcp_UGrpID in ('.join(',', $wg_ids).')))';

            $where2 = '( '.$where2.' OR rec_OwnerUGrpID in (' . join(',', $wg_ids).') )';
        }
    }

    return $where2;

}

//
// returns only first relationship type ID for 2 given records
//
function recordGetRelationshipType($system, $sourceID, $targetID ){

    $mysqli = $system->get_mysqli();

    //find all target related records
    $query = 'SELECT rl_RelationTypeID FROM recLinks '
    .'WHERE rl_SourceID='.$sourceID.' AND rl_TargetID='.$targetID.' AND rl_RelationID IS NOT NULL';
    $res = $mysqli->query($query);
    if (!$res){
        return null;// $system->addError(HEURIST_DB_ERROR, "Search query error on get relationship type", $mysqli->error);
    }else{
        if($row = $res->fetch_row()) {
            return $row[0];
        }else{
            return null;
        }
    }
}

//
// return linked record ids and their types (for update linked record titles)
//
function recordGetLinkedRecords($system, $recordID){

    $mysqli = $system->get_mysqli();
    $query = 'SELECT DISTINCT rl_TargetID, rec_RecTypeID FROM recLinks, Records WHERE rl_TargetID=rec_ID  AND rl_SourceID='.$recordID;
    $ids1 = mysql__select_assoc2($mysqli, $query);
    if($ids1===null){
        $system->addError(HEURIST_DB_ERROR, "Search query error for target linked and related records. Query ".$query, $mysqli->error);
        return false;
    }
    $query = 'SELECT DISTINCT rl_SourceID, rec_RecTypeID FROM recLinks, Records WHERE rl_SourceID=rec_ID  AND rl_TargetID='.$recordID;
    $ids2 = mysql__select_assoc2($mysqli, $query);
    if($ids2===null){
        $system->addError(HEURIST_DB_ERROR, "Search query error for source linked and related records. Query ".$query, $mysqli->error); 
        return false;
    }

    //merge
    if(count($ids2)>count($ids1)){
        foreach($ids1 as $recid=>$rectype_id){
            if(!@$ids2[$recid]){
                $ids2[$recid] = $rectype_id;
            }
        }
        return $ids2;
    }else{
        foreach($ids2 as $recid=>$rectype_id){
            if(!@$ids1[$recid]){
                $ids1[$recid] = $rectype_id;
            }
        }
        return $ids1;
    }



}

//
// returns relationship records(s) (RT#1) for given source and target records
//
function recordGetRelationship($system, $sourceID, $targetID, $search_request=null){

    $mysqli = $system->get_mysqli();

    //find all target related records
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
            }else if(!@$search_request['detail']){
                $search_request['detail'] = 'detail'; //returns all details
            }
        }

        return recordSearch($system, $search_request);
    }


}


//
// find parent record for rec_ID with given record type
//
function recordSearchFindParent($system, $rec_ID, $target_recTypeID, $allowedDetails, $level=0){

    $query = 'SELECT rec_RecTypeID from Records WHERE rec_ID='.$rec_ID;
    $rtype = mysql__select_value($system->get_mysqli(), $query);

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

    $parents = mysql__select_list2($system->get_mysqli(), $query);
    if(is_array($parents) && count($parents)>0){
        if($level>5){
            $system->addError(HEURIST_ERROR, 'Can not find parent CMS Home record. It appears that menu items refers recursively');         
            return false;
        }
        return recordSearchFindParent($system, $parents[0], $target_recTypeID, $allowedDetails, $level+1);
    }else{
        $system->addError(HEURIST_ERROR, 'Can not find parent CMS Home record');         
        return false;
    }
}
//
// $menuitems - record ids
// fills $result array recursively with record ids and returns full detail at the end
//
function recordSearchMenuItems($system, $menuitems, &$result, $ids_only=false){

    $system->defineConstants();

    $menuitems = prepareIds($menuitems, true);
    $isRoot = (count($result)==0); //find any first CMS_HOME
    if($isRoot){
        if(!($system->defineConstant('RT_CMS_HOME') &&
        $system->defineConstant('DT_CMS_MENU') && 
        $system->defineConstant('DT_CMS_TOP_MENU'))){

            return $system->addError(HEURIST_ERROR, 'Required field type "Menu" not defined in this database');         
        }

        //if root record is menu - we have to find parent cms home
        if(count($menuitems)==1){
            if($menuitems[0]==0){
                //find first home record
                $query = 'SELECT rec_ID from Records '
                .'WHERE (not rec_FlagTemporary) AND rec_RecTypeID='.RT_CMS_HOME;
                $res = mysql__select_value($system->get_mysqli(), $query);
                if($res==null){
                    return $system->addError(HEURIST_ERROR, 
                        'Can not find website home record');                    
                }
            }else{
                $root_rec_id = $menuitems[0];
                $isWebPage = false;

                //check that this is single web page (for embed)
                if($system->defineConstant('DT_CMS_PAGETYPE') && 
                $system->defineConstant('RT_CMS_MENU'))
                {
                    $rec = recordSearchByID($system, $root_rec_id, array(DT_CMS_PAGETYPE), 'rec_ID,rec_RecTypeID');
                    if(@$rec['rec_RecTypeID']==RT_CMS_MENU && is_array(@$rec['details'][DT_CMS_PAGETYPE])){
                        //get term id by concept code
                        $val = array_shift($rec['details'][DT_CMS_PAGETYPE]);
                        $isWebPage = ($val==ConceptCode::getTermLocalID('2-6254'));
                    }
                }

                if($isWebPage){
                    return recordSearch($system, array('q'=>array('ids'=>$root_rec_id), 
                        'detail'=>array(DT_NAME,DT_SHORT_SUMMARY,DT_CMS_TARGET,DT_CMS_CSS,DT_CMS_PAGETITLE,DT_EXTENDED_DESCRIPTION,DT_CMS_TOP_MENU,DT_CMS_MENU), //'detail' 
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

    foreach ($menuitems as $rec_ID){   
        if(!in_array($rec_ID, $result)){ //to avoid recursion
            array_push($result, $rec_ID);
            array_push($rec_IDs, $rec_ID);
        }
    }

    if(count($rec_IDs)>0){       
        /*
        $query = 'SELECT dtl_Value FROM recDetails WHERE dtl_RecID in ('
        .implode(',',$rec_IDs).') AND (dtl_DetailTypeID='.DT_CMS_MENU
        .' OR dtl_DetailTypeID='.DT_CMS_TOP_MENU.')';
        */        
        $query = 'SELECT rl_TargetID FROM recLinks WHERE rl_SourceID in ('
        .implode(',',$rec_IDs).') AND (rl_DetailTypeID='.DT_CMS_MENU
        .' OR rl_DetailTypeID='.DT_CMS_TOP_MENU.')';

        $menuitems2 = mysql__select_list2($system->get_mysqli(), $query);

        $menuitems2 = prepareIds( $menuitems2 );

        /*
        if(count($menuitems2)>10){
        error_log($query);
        return $system->addError(HEURIST_ERROR, 'Record '.implode(',',$rec_IDs).' produce '.count($menuitems2).' items '.$query);
        }*/


        if(is_array($menuitems2) && count($menuitems2)>0){                          
            recordSearchMenuItems($system, $menuitems2, $result);
        }
    }else if ($isRoot) {
        return $system->addError(HEURIST_INVALID_REQUEST, 'Root record id is not specified');
    }


    if($isRoot){
        if($ids_only){
            return $result;
        }else{
            //return recordset
            return recordSearch($system, array('q'=>array('ids'=>$result), 
                'detail'=>array(DT_NAME,DT_SHORT_SUMMARY,DT_CMS_TARGET,DT_CMS_CSS,DT_CMS_PAGETITLE,DT_EXTENDED_DESCRIPTION,DT_CMS_TOP_MENU,DT_CMS_MENU), //'detail' 
                'w'=>'e', 'cms_cut_description'=>1));
        }
    }

}

//-----------------------------------------------------------------------
/**
* put your comment there...
*
* @param mixed $system
* @param mixed $params
*
*       FOR RULES
*       rules - rules queries - to search related records on server side
*       rulesonly - return rules only (without original query)
*       getrelrecs (=1) - search relationship records (along with related) on server side
*       topids - list of records ids, it is used to compose 'parentquery' parameter to use in rules (@todo - replace with new rules algorithm)
*
*       INTERNAL/recursive
*       parentquery - sql expression to substiture in rule query
*       sql - sql expression to execute (used as recursive parameters to search relationship records)
*
*       SEARCH parameters that are used to compose sql expression
*       q - query string (old mode) or json array (new mode)
*       w (=all|bookmark a|b) - search among all or bookmarked records
*       limit  - limit for sql query is set explicitely on client side
*       offset - offset parameter value for sql query
*       s - sort order - if defined it overwrites sortby in q param
*
*       OUTPUT parameters
*       vo (=h3) - output format in h3 for backward capability (for detail=ids only)
*       needall (=1) - by default it returns only first 3000, to return all set it to 1,
*                      it is set to 1 for server-side rules searches
*       publiconly (=1) - ignore current user and returns only public records
*
*       detail (former 'f') - ids       - only record ids
*                             count     - only count of records  
*                             header    - record header
*                             timemap   - record header + timemap details (time, location and symbology fields)
*                             detail    - record header + all details
*                             structure - record header + all details + record type structure (for editing) - NOT USED
*       tags                  returns with tags for current user (@todo for given user, group)
*       CLIENT SIDE
*       id - unque id to sync with client side
*       source - id of html element that is originator of this search
*       qname - original name of saved search (for messaging)
*/
function recordSearch($system, $params)
{
    //if $params['q'] has svsID it means search by saved filter - all parameters will be taken from saved filter
    // {"svs":5}
    if(@$params['q']){

        $svsID = null;
        $query_json = is_array(@$params['q']) ?$params['q'] :json_decode(@$params['q'], true);
        if(is_array($query_json) && count($query_json)>0){
            $svsID = @$query_json['svs'];
        }else if(@$params['q'] && strpos($params['q'],':')>0){
            list($predicate, $svsID) = explode(':', $params['q']);
            if(!($predicate=='svs' && $svsID>0)){
                $svsID = null;
            }
        }
        if($svsID>0){

            $mysqli = $system->get_mysqli();
            $vals = mysql__select_row($mysqli,
                'SELECT svs_Name, svs_Query FROM usrSavedSearches WHERE svs_ID='.$mysqli->real_escape_string( $svsID ));        

            if($vals){
                $query = $vals[1];
                $params['qname'] = $vals[0];

                if(strpos($query, '?')===0){
                    parse_str(substr($query,1), $new_params);

                    if(@$new_params['q']) { $params['q'] = @$new_params['q']; }
                    if(@$new_params['rules']) { $params['rules'] = @$new_params['rules']; }
                    if(@$new_params['w']) { $params['w'] = @$new_params['w']; }
                    if(@$new_params['notes']) { $params['notes'] = @$new_params['notes']; }

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


    $memory_limit = get_php_bytes('memory_limit');

    //for error message
    $savedSearchName = @$params['qname']?"Saved search: ".$params['qname']."<br>":"";

    if(!@$params['detail']){
        $params['detail'] = @$params['f']; //backward capability
    }

    $system->defineConstant('RT_CMS_MENU');
    $system->defineConstant('DT_EXTENDED_DESCRIPTION');


    $fieldtypes_in_res = null;
    //search for geo and time fields and remove non timemap records - for rules we need all records
    $istimemap_request = (@$params['detail']=='timemap' && @$params['needall']!=1);  
    $find_places_for_geo = false;
    $istimemap_counter = 0; //total records with timemap data
    $needThumbField = false;
    $needThumbBackground = false;
    $needCompleteInformation = false; //if true - get all header fields, relations, full file info
    $needTags = (@$params['tags']>0)?$system->get_user_id():0;

    $relations = null;
    $permissions = null;

    if(@$params['detail']=='complete'){
        $params['detail'] = 'detail';
        $needCompleteInformation = true; //all header fields, relations, full file info
    }

    $fieldtypes_ids = null;
    if(@$params['detail']=='timemap'){ //($istimemap_request){
        $params['detail']=='detail';

        $system->defineConstant('DT_START_DATE');
        $system->defineConstant('DT_END_DATE');
        $system->defineConstant('DT_GEO_OBJECT');
        $system->defineConstant('DT_DATE');
        $system->defineConstant('DT_SYMBOLOGY_POINTMARKER'); //outdated
        $system->defineConstant('DT_SYMBOLOGY_COLOR'); //outdated
        $system->defineConstant('DT_BG_COLOR'); //outdated
        $system->defineConstant('DT_OPACITY');  //outdated

        //list of rectypes that are sources for geo location
        $rectypes_as_place = $system->get_system('sys_TreatAsPlaceRefForMapping');
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
        if($fieldtypes_ids==null || count($fieldtypes_ids)==0){
            //this case nearly impossible since system always has date and geo fields 
            $fieldtypes_ids = array(DT_GEO_OBJECT, DT_DATE, DT_START_DATE, DT_END_DATE); //9,10,11,28';    
        }
        //add symbology fields
        if(defined('DT_SYMBOLOGY_POINTMARKER')) $fieldtypes_ids[] = DT_SYMBOLOGY_POINTMARKER;
        if(defined('DT_SYMBOLOGY_COLOR')) $fieldtypes_ids[] = DT_SYMBOLOGY_COLOR;
        if(defined('DT_BG_COLOR')) $fieldtypes_ids[] = DT_BG_COLOR;
        if(defined('DT_OPACITY')) $fieldtypes_ids[] = DT_OPACITY;

        $fieldtypes_ids = implode(',', $fieldtypes_ids);
        $needThumbField = true;
        //DEBUG error_log('timemap fields '.$fieldtypes_ids);

        //find places linked to result records for geo field
        if(@$params['suppres_derivemaplocation']!=1){ //for production sites - such as USyd Book of Remembrance Online or Digital Harlem
            $find_places_for_geo = count($rectypes_as_place)>0 && 
            ($system->user_GetPreference('deriveMapLocation', 1)==1);
        }

    }else 
        if(  !in_array(@$params['detail'], array('header','timemap','detail','structure')) ){ //list of specific detailtypes
            //specific set of detail fields
            if(is_array($params['detail'])){
                $fieldtypes_ids = $params['detail'];
            } else {
                $fieldtypes_ids = explode(',', $params['detail']);
            }

            if(is_array($fieldtypes_ids) && count($fieldtypes_ids)>0)  
            //(count($fieldtypes_ids)>1 || is_numeric($fieldtypes_ids[0])) )
            {
                $f_res = array();

                foreach ($fieldtypes_ids as $dt_id){

                    if(is_numeric($dt_id) && $dt_id>0){
                        array_push($f_res, $dt_id);
                    }else if($dt_id=='rec_ThumbnailURL'){
                        $needThumbField = true;
                    }else if($dt_id=='rec_ThumbnailBg'){
                        $needThumbBackground = true;
                    }
            }
            if(count($f_res)>0){
                $fieldtypes_ids = implode(',', $f_res);
                $params['detail'] = 'detail';
                $needThumbField = true;
            }else{
                $fieldtypes_ids = null;
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


    $is_count_only = ('count'==$params['detail']);
    $is_ids_only = ('ids'==$params['detail']);
    $return_h3_format = (@$params['vo']=='h3' &&  $is_ids_only);

    if(null==$system){
        $system = new System();
        if( ! $system->init(@$_REQUEST['db']) ){
            $response = $system->getError();
            if($return_h3_format){
                $response['error'] = $response['message'];
            }
            return $response;
        }
    }


    $mysqli = $system->get_mysqli();
    $currentUser = $system->getCurrentUser();

    if ( $system->get_user_id()<1 ) {
        $params['w'] = 'all'; //does not allow to search bookmarks if not logged in
    }

    if($is_count_only){

        $select_clause = 'select count(rec_ID) ';

    }else if($is_ids_only){

        //
        $select_clause = 'select SQL_CALC_FOUND_ROWS DISTINCT rec_ID ';

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
        .'bkm_PwdReminder ';
        /*.'rec_URLLastVerified,'
        .'rec_URLErrorMessage,'
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

        //@todo - implement it in different way - substitute topids to query json as predicate ids:

        $query_top = array();

        if (strcasecmp(@$params['w'],'B') == 0  ||  strcasecmp(@$params['w'], 'bookmark') == 0) {
            $query_top['from'] = 'FROM usrBookmarks TOPBKMK LEFT JOIN Records TOPBIBLIO ON bkm_recID=rec_ID ';
        }else{
            $query_top['from'] = 'FROM Records TOPBIBLIO LEFT JOIN usrBookmarks TOPBKMK ON bkm_recID=rec_ID and bkm_UGrpID='.$currUserID.' ';
        }
        $query_top['where'] = "(TOPBIBLIO.rec_ID in (".$params['topids']."))";
        $query_top['sort'] =  '';
        $query_top['limit'] =  '';
        $query_top['offset'] =  '';

        $params['parentquery'] = $query_top;  //parentquery parameter is used in  get_sql_query_clauses

    }
    else if( @$params['rules'] ){ //special case - server side operation

        // rules - JSON array the same as stored in saved searches table

        if(is_array(@$params['rules'])){
            $rules_tree = $params['rules'];
        }else{
            $rules_tree = json_decode($params['rules'], true);
        }

        $flat_rules = array();
        $flat_rules[0] = array();

        //create flat rule array
        $rules = _createFlatRule( $flat_rules, $rules_tree, 0 );

        //find result for main query
        unset($params['rules']);
        if(@$params['limit']) unset($params['limit']);
        if(@$params['offset']) unset($params['offset']);
        if(@$params['vo']) unset($params['vo']);

        $params['needall'] = 1; //return all records, otherwise dependent records could not be found

        $resSearch = recordSearch($system, $params); //search for main set

        $keepMainSet = (@$params['rulesonly']!=1 && @$params['rulesonly']!=2);
        $keepLastSetOnly = (@$params['rulesonly']==2);

        if(is_array($resSearch) && $resSearch['status']!=HEURIST_OK){  //error
            return $resSearch;
        }

        if($keepMainSet){
            //find main query results
            $fin_result = $resSearch;
            //main result set
            $flat_rules[0]['results'] = $is_ids_only ?$fin_result['data']['records'] 
            :array_keys($fin_result['data']['records']); //get ids
        }else{
            //remove from $fin_result! but keep in $flat_rules[0]['results']?
            $flat_rules[0]['results'] = $is_ids_only ?$resSearch['data']['records'] 
            :array_keys($resSearch['data']['records']); //get ids

            //empty main result set
            $fin_result = $resSearch;
            $fin_result['data']['records'] = array(); //empty
            $fin_result['data']['reccount'] = 0;
            $fin_result['data']['count'] = 0;
        }

        $is_get_relation_records = (@$params['getrelrecs']==1); //get all related and relationship records

        foreach($flat_rules as $idx => $rule){ //loop for all rules
            if($idx==0) continue;

            $is_last = (@$rule['islast']==1);

            //create request
            $params['q'] = $rule['query'];
            $parent_ids = $flat_rules[$rule['parent']]['results']; //list of record ids of parent resultset
            $rule['results'] = array(); //reset

            //split by 3000 - search based on parent ids (max 3000)
            $k = 0;
            while ($k < count($parent_ids)) {

                //$need_details2 = $need_details && ($is_get_relation_records || $is_last);

                $params3 = $params;
                $params3['topids'] = implode(",", array_slice($parent_ids, $k, 3000));
                if( !$is_last ){  //($is_get_relation_records ||
                    //$params3['detail'] = 'ids';  //no need in details for preliminary results  ???????
                }

                //t:54 related_to:10 =>   {"t":"54","related":"10"}     
                if(strpos($params3['q'],'related_to')>0){

                    $params3['q'] = str_replace('related_to','related',$params3['q']);

                }else if(strpos($params3['q'],'relatedfrom')>0){

                    $params3['q'] = str_replace('relatedfrom','related',$params3['q']);
                }

                //DEBUg error_log(print_r($params3,true));                    

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
                            /*
                            foreach( array_keys($response['data']['records']) as $rt){
                            $rectype_id = @$rt['4'];
                            if($rectype_id){
                            //if(@$fin_result['data']['rectypes'][$rectype_id]){
                            //    $fin_result['data']['rectypes'][$rectype_id]++;
                            //}else{
                            //    $fin_result['data']['rectypes'][$rectype_id]=1;
                            //}
                            if(!array_key_exists($rectype_id, $fin_result['data']['rectypes'])){
                            $fin_result['data']['rectypes'][$rectype_id] = 1;
                            }
                            }
                            }
                            */
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

                            $ids_party1 = $params3['topids'];  //source ids (from top query)
                            $ids_party2 = $is_ids_only?$response['data']['records'] :array_keys($response['data']['records']);

                            if(is_array($ids_party2) && count($ids_party2)>0)
                            {


                                $where = "WHERE (TOPBIBLIO.rec_ID in (select rl_RelationID from recLinks "
                                ."where (rl_RelationID is not null) and ".
                                "(($fld1 in (".$ids_party1.") and $fld2 in (".implode(",", $ids_party2).")) OR ".
                                " ($fld2 in (".$ids_party1.") and $fld1 in (".implode(",", $ids_party2)."))) ".
                                "))";

                                $params2 = $params3;
                                unset($params2['topids']);
                                unset($params2['q']);

                                $params2['sql'] = $select_clause.$from.$where;

                                $response = recordSearch($system, $params2);  //search for relationship records
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
    else if( $currUserID>0 ) {
        //find user work susbset (except EVERYTHING search)
        $params['use_user_wss'] = (@$params['w']!='e'); //(strcasecmp(@$params['w'],'E') == 0); 
    }


    $search_detail_limit = PHP_INT_MAX;

    if(@$params['sql']){
        $query = $params['sql'];
    }else{

        $is_mode_json = false;

        if(@$params['q']!=null && @$params['q']!=''){

            if(is_array(@$params['q'])){
                $query_json = $params['q'];
                //DEBUG error_log('Q='.print_r($params['q'],true));                
            }else{
                $query_json = json_decode(@$params['q'], true);
            }

            if(is_array($query_json) && count($query_json)>0){
                $params['q'] = $query_json;
                $is_mode_json = true;
            }

        }else{
            return $system->addError(HEURIST_INVALID_REQUEST, $savedSearchName."Invalid search request. Missing query parameter 'q'");
        }

        if($is_mode_json){
            $aquery = get_sql_query_clauses_NEW($mysqli, $params, $currentUser); //main usage
        }else{
            $aquery = get_sql_query_clauses($mysqli, $params, $currentUser);   //!!!! IMPORTANT CALL OR compose_sql_query at once
        }

        if(@$aquery['error']=='create_fulltext'){
            return $system->addError(HEURIST_ACTION_BLOCKED, '<h3 style="margin:4px;">Building full text index</h3>'
                    .'<p>To process word searches efficiently we are building a full text index.</p>'
                    .'<p>This is a one-off operation and may take some time for large, text-rich databases '
                    .'(where it will make the biggest difference to retrieval speeds).</p>', null);        
        }else if(@$aquery['error']){
            return $system->addError(HEURIST_ERROR, 'Unable to construct valid SQL query. '.@$aquery['error'], null);
        }
        if(!isset($aquery["where"]) || trim($aquery["where"])===''){
            return $system->addError(HEURIST_ERROR, 'Invalid search request; unable to construct valid SQL query', null);
        }

        if($is_count_only || ($is_ids_only && @$params['needall']) || !$system->has_access() ){ //not logged in
            $search_detail_limit = PHP_INT_MAX;
            $aquery["limit"] = '';
        }else{
            $search_detail_limit = $system->user_GetPreference('search_detail_limit'); //limit for map/timemap output
        }


        $query =  $select_clause.$aquery["from"]." WHERE ".$aquery["where"].$aquery["sort"].$aquery["limit"].$aquery["offset"];

    }

    //DEBUG
    //if($is_ids_only && $system->dbname()=='ExpertNation') 
    //error_log($params['use_user_wss']);
    //error_log($query);

    $res = $mysqli->query($query);
    if (!$res){


        $response = $system->addError(HEURIST_DB_ERROR, $savedSearchName.
            ' Search query error on saved search. Parameters:'.print_r($params, true).' Query '.$query, $mysqli->error);
    }else if($is_count_only){

        $total_count_rows = $res->fetch_row();
        $total_count_rows = (int)$total_count_rows[0];
        $res->close();

        $response = array('status'=>HEURIST_OK,
            'data'=> array(
                'queryid'=>@$params['id'],  //query unqiue id
                'count'=>$total_count_rows));

    }else{

        $fres = $mysqli->query('select found_rows()');
        if (!$fres)     {
            $response = $system->addError(HEURIST_DB_ERROR, 
                $savedSearchName.'Search query error (retrieving number of records)', $mysqli->error);
        }else{

            $total_count_rows = $fres->fetch_row();
            $total_count_rows = $total_count_rows[0];
            $fres->close();

            if($total_count_rows*10>$memory_limit){
                //error_log($query);

                return $system->addError(HEURIST_ACTION_BLOCKED, 
                    'Search query produces '.$total_count_rows
                    .' records. Memory limit does not allow to retrieve all of them.'
                    .' Please filter to a smaller set of results.');
            }


            if($is_ids_only)
            { //------------------------  LOAD and RETURN only IDS

                $records = array();

                while ($row = $res->fetch_row())  {
                    array_push($records, (int)$row[0]);
                }
                $res->close();

                if(@$params['vo']=='h3'){ //output version used in showReps.php (where else???)
                    $response = array('resultCount' => $total_count_rows,
                        'recordCount' => count($records),
                        'recIDs' => implode(',', $records) );
                }else{

                    $response = array('status'=>HEURIST_OK,
                        'data'=> array(
                            'queryid'=>@$params['id'],  //query unqiue id
                            'entityName'=>'Records',
                            'count'=>$total_count_rows,
                            'offset'=>get_offset($params),
                            'reccount'=>count($records),
                            'records'=>$records));
                    
                    if(@$params['links_count'] && count($records)>0){
                        
                        $links_counts = recordLinkedCount($system, 
                                    $params['links_count']['source'], 
                                    count($records)<500?$records:
                                        $params['links_count']['target'], 
                                    @$params['links_count']['dty_ID']);
                                    
                        if($links_counts['status']==HEURIST_OK && count(@$links_counts['data'])>0){
                            
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
                    
                }

            }else{ //----------------------------------

                $rectype_structures  = array();
                $rectypes = array();
                $records = array();
                $order = array();
                $memory_warning = null;
                $limit_warning = false;

                /*if($istimemap_request){ //special case need to scan all result set and pick up only timemap enabled

                $tm_records = _getTimemapRecords($res);    

                }else{ */

                // read all field names
                $_flds =  $res->fetch_fields();
                $fields = array();
                foreach($_flds as $fld){
                    array_push($fields, $fld->name);
                }
                $date_add_index = array_search('rec_Added', $fields);
                $date_mod_index = array_search('rec_Modified', $fields);

                if($needThumbField) array_push($fields, 'rec_ThumbnailURL');
                if($needThumbBackground) array_push($fields, 'rec_ThumbnailBg');

                //array_push($fields, 'rec_Icon'); //last one -icon ID
                if($needTags>0) array_push($fields, 'rec_Tags');

                // load all records
                while ($row = $res->fetch_row()) {

                    if($needThumbField) {
                        $tres = fileGetThumbnailURL($system, $row[2], $needThumbBackground);   
                        array_push( $row, $tres['url'] );
                        if($needThumbBackground) array_push( $row, $tres['bg_color'] );
                    }
                    if($needTags>0){ //get record tags for given user/group
                        /*var dbUsrTags = new DbUsrTags($system, array('details'=>'label', 
                        'tag_UGrpID'=>$needTags, 
                        'rtl_RecID'=>$row[2] ));*/

                        $query = 'SELECT tag_Text FROM usrTags, usrRecTagLinks WHERE tag_ID=rtl_TagID AND tag_UGrpID='
                        .$needTags.' AND rtl_RecID='.$row[2];
                        array_push( $row, mysql__select_list2($mysqli, $query));
                    }

                    //convert add and modified date to UTC
                    if($date_add_index!==false) {
                        // zero date not allowed by default since MySQL 5.7, default date changed to 1000
                        if($row[$date_add_index]=='0000-00-00 00:00:00' 
                        || $row[$date_add_index]=='1000-01-01 00:00:00'){ //not defined
                            $row[$date_add_index] = '';    
                        }else{
                            $row[$date_add_index] = DateTime::createFromFormat('Y-m-d H:i:s', $row[$date_add_index])
                            ->setTimezone(new DateTimeZone('UTC'))
                            ->format('Y-m-d H:i:s');
                        }
                    }
                    if($date_mod_index!==false) {
                        $row[$date_mod_index] = DateTime::createFromFormat('Y-m-d H:i:s', $row[$date_mod_index])
                        ->setTimezone(new DateTimeZone('UTC'))
                        ->format('Y-m-d H:i:s');
                    }


                    //array_push( $row, $row[4] ); //by default icon if record type ID
                    $records[$row[2]] = $row;
                    array_push($order, $row[2]);
                    if(!@$rectypes[$row[4]]){
                        $rectypes[$row[4]]=1;
                    }

                    if(count($order)>5000){
                        $mem_used = memory_get_usage();
                        if($mem_used>$memory_limit-104857600){ //100M
                            //error_log($query);

                            return $system->addError(HEURIST_ACTION_BLOCKED, 
                                'Search query produces '.$total_count_rows
                                .' records. Memory limit does not allow to retrieve all of them.'
                                .' Please filter to a smaller set of results.');
                        }
                    }                           

                }//load headers
                $res->close();

                //LOAD DETAILS
                if(($istimemap_request ||
                $params['detail']=='detail' ||
                $params['detail']=='structure') && count($records)>0){


                    $all_rec_ids = array_keys($records); 
                    $res_count = count($all_rec_ids);
                    //split to 2500 to use in detail query
                    $offset = 0;

                    if($istimemap_request){
                        $tm_records = array();
                        $order = array();
                        $rectypes = array();
                        $istimemap_counter = 0;
                    }

                    $fieldtypes_in_res = array(); //reset


                    $loop_cnt=1;                            
                    while ($offset<$res_count){   

                        //here was a problem, since chunk size for mapping can be 5000 or more we got memory overflow here
                        //reason the list of ids in SELECT is bigger than mySQL limit
                        //solution - we perfrom the series of request for details by 1000 records
                        $chunk_rec_ids = array_slice($all_rec_ids, $offset, 1000); 
                        $offset = $offset + 1000;

                        $ulf_fields = 'f.ulf_ObfuscatedFileID, f.ulf_Parameters';  //4,5

                        //search for specific details
                        if($fieldtypes_ids!=null && $fieldtypes_ids!=''){

                            $detail_query = 'select dtl_RecID,'
                            .'dtl_DetailTypeID,'     // 0
                            .'dtl_Value,'            // 1
                            .'ST_asWKT(dtl_Geo), dtl_UploadedFileID, '
                            .$ulf_fields
                            .' FROM recDetails '
                            . ' left join recUploadedFiles as f on f.ulf_ID = dtl_UploadedFileID '
                            . ' WHERE (dtl_RecID in (' . join(',', $chunk_rec_ids) . ') '
                            .' AND dtl_DetailTypeID in ('.$fieldtypes_ids.'))';


                            if($find_places_for_geo){ //find location in linked Place records
                                $detail_query = $detail_query . 'UNION  '
                                .'SELECT rl_SourceID,dtl_DetailTypeID,dtl_Value,ST_asWKT(dtl_Geo), rl_TargetID, 0, 0 '
                                .' FROM recDetails, recLinks, Records '
                                .' WHERE dtl_DetailTypeID='. DT_GEO_OBJECT
                                .' AND dtl_RecID=rl_TargetID AND rl_TargetID=rec_ID AND rec_RecTypeID in ('. join(',', $rectypes_as_place)
                                .') AND rl_SourceID in (' . join(',', $chunk_rec_ids) . ')';
                            }

                            //error_log($detail_query);

                        }else{

                            if($needCompleteInformation){
                                $ulf_fields = 'f.ulf_OrigFileName,f.ulf_ExternalFileReference,f.ulf_ObfuscatedFileID,'
                                .'f.ulf_MimeExt, f.ulf_Parameters';  //4,5,6,7,8
                            }else{

                            }

                            $detail_query = 'select dtl_RecID,'
                            .'dtl_DetailTypeID,'     // 0
                            .'dtl_Value,'            // 1
                            .'ST_asWKT(dtl_Geo),'    // 2
                            .'dtl_UploadedFileID,'   // 3
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
                                $recID = array_shift($row);
                                if( !array_key_exists('d', $records[$recID]) ){
                                    $records[$recID]['d'] = array();
                                    $need_Concatenation = $need_Concatenation || 
                                    (defined('RT_CMS_MENU') && $records[$recID][4]==RT_CMS_MENU);
                                }
                                $dtyID = $row[0];

                                $val = null;

                                if($row[2]){ //GEO
                                    //dtl_Geo @todo convert to JSON
                                    $val = $row[1]; //geotype 

                                    $linked_Place_ID = $row[3]; //linke place record id
                                    if($linked_Place_ID>0){
                                        $val = $val.':'.$linked_Place_ID;      //reference to real geo record
                                    }

                                    $val = $val.' '.$row[2];  //WKT

                                }else if($row[3]){ //uploaded file

                                    if($needCompleteInformation){

                                        $params = fileParseParameters($row[8]);//ulf_Parameters

                                        $val = array('ulf_ID'=>$row[3],
                                            'ulf_OrigFileName'=>$row[4],
                                            'ulf_ExternalFileReference'=>$row[5],
                                            'ulf_ObfuscatedFileID'=>$row[6],
                                            'ulf_MimeExt'=>$row[7],
                                            'mediaType'=>$params['mediaType'],
                                            'remoteSource'=>$params['remoteSource']);


                                    }else{
                                        $val = array($row[4], $row[5]); //obfuscated value for fileid and parameters
                                    }

                                }else if(@$row[1]!=null) {
                                    $val = $row[1];
                                }

                                if($val!=null){
                                    $fieldtypes_in_res[$dtyID] = 1;
                                    if( !array_key_exists($dtyID, $records[$recID]['d']) ){
                                        $records[$recID]['d'][$dtyID] = array();
                                    }
                                    array_push($records[$recID]['d'][$dtyID], $val);
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
                                    if(is_array(@$record['d']) && count($record['d'])>0){
                                        //this record is time enabled 
                                        if($istimemap_counter<$search_detail_limit){
                                            $tm_records[$recID] = $record;        
                                            array_push($order, $recID);
                                            $rectypes[$record[4]]=1; 
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
                    }else
                        if($needCompleteInformation){
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
                if( @$params['detail']=='structure' && count($rectypes)>0){ //rarely used in editing.js
                    //description of recordtype and used detail types
                    $rectype_structures = dbs_GetRectypeStructures($system, $rectypes, 1); //no groups
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
                //if(is_array($permissions)){
                //        $response['data']['permissions'] =  $permissions;
                //}

            }//$is_ids_only




        }
    }

    return $response;

}

/**
* array_merge_unique - return an array of unique values,
* composed of merging one or more argument array(s).
*
* As with array_merge, later keys overwrite earlier keys.
* Unlike array_merge, however, this rule applies equally to
* numeric keys, but does not necessarily preserve the original
* numeric keys.
*/
function array_merge_unique($a, $b) {
    foreach($b as $item){
        //if(!in_array($item,$b)){
        if(array_search($item, $a)===false){
            $a[] = $item;
        }
    }                
    return $a;
}     
function array_merge_unique3(array $array1 /* [, array $...] */) {
    $result = array_flip(array_flip($array1));
    foreach (array_slice(func_get_args(),1) as $arg) { 
        $result = 
        array_flip(
            array_flip(
                array_merge($result,$arg)));
    } 
    return $result;
}    


function mergeRecordSets($rec1, $rec2){

    $res = $rec1;

    foreach ($rec2 as $recID => $record) {
        if(!@$rec1[$recID]){
            $res[$recID] = $record;
        }
    }

    return $res;
}

//
//
//
function _createFlatRule(&$flat_rules, $r_tree, $parent_index){

    if($r_tree){
        foreach ($r_tree as $rule) {
            $e_rule = array('query'=>@$rule['query'],
                'results'=>array(),
                'parent'=>$parent_index,
                'ignore'=>(@$rule['ignore']==1), //not include in final result
                'islast'=>(!@$rule['levels'] || count($rule['levels'])==0)?1:0 );
            array_push($flat_rules, $e_rule );
            _createFlatRule($flat_rules, @$rule['levels'], count($flat_rules)-1);
        }
    }

}

//
// backward capability - remove as soon as old uploadFileOrDefineURL get rid of use
//
function fileParseParameters($params){
    $res = array();
    if($params){
        $pairs = explode('|', $params);
        foreach ($pairs as $pair) {
            if(strpos($pair,'=')>0){
                list($k, $v) = explode("=", $pair); //array_map("urldecode", explode("=", $pair));
                $res[$k] = $v;
            }
        }
    }

    $res["mediaType"] = (array_key_exists('mediatype', $res))?$res['mediatype']:null;
    $res["remoteSource"] = (array_key_exists('source', $res))?$res['source']:null;

    return $res;

}

//
// find replacement for given record id
//
function recordSearchReplacement($mysqli, $rec_id, $level=0){

    if($rec_id>0){    
        $rep_id = mysql__select_value($mysqli, 
            'select rfw_NewRecID from recForwarding where rfw_OldRecID=' . intval($rec_id));
        if($rep_id>0){
            if($level<10){
                return recordSearchReplacement($mysqli, $rep_id, $level++);
            }else{
                return $rep_id;
            }
        }else{
            return $rec_id;
        }
    }else{
        return 0;
    }
} 

//-----------------------
function recordTemplateByRecTypeID($system, $id){    

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

    $mysqli = $system->get_mysqli();
    $fields = mysql__select_assoc2($mysqli, 'select dty_ID, dty_Type '
        .'from defRecStructure, defDetailTypes where dty_ID = rst_DetailTypeID '
        .'and rst_RecTypeID = '.$id);

    $details = array();
    $idx = 1;

    foreach ($fields as $dty_ID=>$dty_Type){
        if($dty_Type=='separator')continue;


        if($dty_Type=='file'){
            $details[$dty_ID] = array($idx=>array('file'=>array('file'=>'TEXT', 'fileid'=>'TEXT')) );    

        }else if($dty_Type=='resource'){
            $details[$dty_ID] = array($idx=>array('id'=>'RECORD_REFERENCE', 'type'=>0, 'title'=>''));    

        }else if($dty_Type=='relmarker'){
            $details[$dty_ID] = array($idx=>'SEE NOTES AT START');    

        }else if($dty_Type=='geo'){
            $details[$dty_ID] = array($idx=>array('geo'=>array('wkt'=>'WKT_VALUE')) ); //'type'=>'TEXT',     

        }else if($dty_Type=='enum' || $dty_Type=='relationtype'){
            $details[$dty_ID] = array($idx=>'VALUE');        
        }else if($dty_Type=='integer' || $dty_Type=='float' || $dty_Type=='year' ){
            $details[$dty_ID] = array($idx=>'NUMERIC');
        }else if($dty_Type=='blocktext' ){
            $details[$dty_ID] = array($idx=>'MEMO_TEXT');        
        }else if($dty_Type=='date' ){
            $details[$dty_ID] = array($idx=>'DATE');
        }else{
            $details[$dty_ID] = array($idx=>'TEXT');        
        }

        $idx++;
    }
    $record['details'] = $details;

    return $record;
}    


//------------------------
function recordSearchByID($system, $id, $need_details = true, $fields = null) 
{
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
        rec_Hash";
    }

    $mysqli = $system->get_mysqli();
    $record = mysql__select_row_assoc( $mysqli, 
        "select $fields from Records where rec_ID = $id");
    if ($need_details !== false && $record) {
        recordSearchDetails($system, $record, $need_details);
    }
    return $record;
}

//
// load details for given record plus id,type and title for linked records
//    
/*

details
dty_ID 
dtl_ID=>value

value 
for file  file=>ulf_ID, fileid=>ulf_ObfuscatedFileID   
for resource id=>rec_ID, type=>rec_RecTypeID, title=>rec_Title
for geo   geo => array(type=> , wkt=> )

*/
function recordSearchDetails($system, &$record, $need_details) {

    $recID = $record["rec_ID"];
    $squery =
    "select dtl_ID,
    dtl_DetailTypeID,
    dtl_Value,
    ST_asWKT(dtl_Geo) as dtl_Geo,
    dtl_UploadedFileID,
    dty_Type,
    rec_ID,
    rec_Title,
    rec_RecTypeID,
    rec_Hash
    from recDetails
    left join defDetailTypes on dty_ID = dtl_DetailTypeID
    left join Records on rec_ID = dtl_Value and dty_Type = 'resource'
    where dtl_RecID = $recID";

    if(is_array($need_details) && count($need_details)>0 ){

        if(is_numeric($need_details[0]) && $need_details[0]>0){ //
            if(count($need_details)==1){
                $squery = $squery. ' AND dtl_DetailTypeID = '.$need_details[0];
            }else{
                $squery = $squery. ' AND dtl_DetailTypeID in ('.implode(',',$need_details).')';    
            }

        }else{
            $squery = $squery. ' AND dty_Type in ("'.implode('","',$need_details).'")';
        }
    }

    $mysqli = $system->get_mysqli();
    $res = $mysqli->query($squery);

    $details = array();
    if($res){
        while ($rd = $res->fetch_assoc()) {
            // skip all invalid values
            if (( !$rd["dty_Type"] === "file" && $rd["dtl_Value"] === null ) ||
            (($rd["dty_Type"] === "enum" || $rd["dty_Type"] === "relationtype") && !$rd["dtl_Value"])) {
                continue;
            }

            if (! @$details[$rd["dtl_DetailTypeID"]]) $details[$rd["dtl_DetailTypeID"]] = array();

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

                    //$detailValue = get_uploaded_file_info($rd["dtl_UploadedFileID"], false);

                    $fileinfo = fileGetFullInfo($system, $rd["dtl_UploadedFileID"]);
                    if(is_array($fileinfo) && count($fileinfo)>0){
                        $detailValue = array("file" => $fileinfo[0], "fileid"=>$fileinfo[0]["ulf_ObfuscatedFileID"]);
                    }

                    break;

                case "resource":
                    $detailValue = array(
                        "id" => $rd["rec_ID"],
                        "type"=>$rd["rec_RecTypeID"],
                        "title" => $rd["rec_Title"],
                        "hhash" => $rd["rec_Hash"]
                    );
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

//
// returns string with short description and links to record view and hml
// $record - rec id or record array
//
function recordLinksFileContent($system, $record){

    if(is_numeric($record)){
        $record = array("rec_ID"=>$record);
        recordSearchDetails($system, $record, array(DT_NAME));
    }

    $url = HEURIST_SERVER_URL . '/heurist/?db='.$system->dbname().'&recID='.$record['rec_ID'];

    return 'Downloaded from: '.$system->get_system('sys_dbName', true)."\n"
    .'Dataset ID: '.$record['rec_ID']."\n"
    .(is_array(@$record['details'][DT_NAME])?'Dataset: '.array_values($record["details"][DT_NAME])[0]."\n":'')
    .'Full metadata (XML): '.$url."\n"
    .'Human readable (html): '.($url.'&fmt=html')."\n";

}

//
// find geo in linked places 
//
function recordSearchGeoDetails($system, $recID) {

    $details = array();        
    if($system->defineConstant('RT_PLACE') && $system->defineConstant('DT_GEO_OBJECT')){

        //$recID = $record["rec_ID"];     
        $squery = 'SELECT rl_SourceID,dtl_DetailTypeID,dtl_Value,ST_asWKT(dtl_Geo) as dtl_Geo, rl_TargetID,dtl_ID'
        .' FROM recDetails, recLinks, Records '
        .' WHERE dtl_DetailTypeID='. DT_GEO_OBJECT
        .' AND dtl_RecID=rl_TargetID AND rl_TargetID=rec_ID AND rec_RecTypeID='.RT_PLACE
        //'in ('. join(',', $rectypes_as_place)
        .' AND rl_SourceID = '.$recID; 
        //'in (' . join(',', $chunk_rec_ids) . ')';

        $mysqli = $system->get_mysqli();
        $res = $mysqli->query($squery);


        if($res){
            while ($rd = $res->fetch_assoc()) {

                if ($rd["dtl_Value"]  &&  $rd["dtl_Geo"]) {
                    $detailValue = array(
                        "geo" => array(
                            "type" => $rd["dtl_Value"],
                            "wkt" => $rd["dtl_Geo"],
                            "placeID" => $rd["rl_TargetID"]
                        )
                    );
                    $details[$rd["dtl_DetailTypeID"]][$rd["dtl_ID"]] = $detailValue;
                }
            }
            $res->close();

            /*
            if(!@$record["details"]){
            $record["details"] = $details;
            }else{
            $record["details"] = array_merge($record["details"],$details);
            }
            */

        }
    }
    return $details;
}

//
// load personal tags (current user) for given record ID
//
function recordSearchPersonalTags($system, $rec_ID) {

    $mysqli = $system->get_mysqli();

    return mysql__select_list2($mysqli, 
        'SELECT tag_Text FROM usrRecTagLinks, usrTags WHERE '
        ."tag_ID = rtl_TagID and tag_UGrpID= ".$system->get_user_id()." and rtl_RecID = $rec_ID order by rtl_Order");        
}       
?>
