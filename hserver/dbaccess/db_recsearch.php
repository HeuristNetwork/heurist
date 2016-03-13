<?php

    /**
    * Library to search records
    *
    * recordSearchMinMax - Find minimal and maximal values for given detail type and record type
    * recordSearchFacets
    * recordSearchRelated
    * recordSearch
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2016 University of Sydney
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
    require_once (dirname(__FILE__).'/db_files.php');
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
            $currentUser = $system->getCurrentUser();

            $query = "select min(cast(dtl_Value as decimal)) as min, max(cast(dtl_Value as decimal)) as max from Records, recDetails where rec_ID=dtl_RecID and rec_RecTypeID="
            .$params['rt']." and dtl_DetailTypeID=".$params['dt']." and dtl_Value is not null and dtl_Value!=''";

            //@todo - current user constraints

            $res = $mysqli->query($query);
            if (!$res){
                $response = $system->addError(HEURIST_DB_ERROR, "Search query error", $mysqli->error);
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
    // @param mixed $system
    // @param mixed $params - array or parameters
    //      q - JSON query array
    //      field - field id to search
    //      type - field type (todo - search it dynamically with getDetailType)
    // @return
    //
    function recordSearchFacets($system, $params){

        //for error message
        $savedSearchName = @$params['qname']?"Saved search: ".$params['qname']."<br>":"";

        if(@$params['q'] && @$params['field']){

            $mysqli = $system->get_mysqli();

            $currentUser = $system->getCurrentUser();
            $dt_type     = @$params['type'];
            $step_level  = @$params['step'];
            $fieldid     = $params['field'];

            //do not include bookmark join
            if(!(strcasecmp(@$params['w'],'B') == 0  ||  strcasecmp(@$params['w'],BOOKMARK) == 0)){
                 $params['w'] = NO_BOOKMARK;
            }

            if(!@$params['q']){
                return $system->addError(HEURIST_INVALID_REQUEST, $savedSearchName."Facet query search request. Missed query parameter");
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
                   $select_field  = "dt0.dtl_Value";
                   $detail_link   = ", recDetails dt0 ";
                   $details_where = " AND (dt0.dtl_RecID=r0.rec_ID and dt0.dtl_DetailTypeID=".$fieldid.") AND (NULLIF(dt0.dtl_Value, '') is not null)";
                   //$detail_link   = " LEFT JOIN recDetails dt0 ON (dt0.dtl_RecID=r0.rec_ID and dt0.dtl_DetailTypeID=".$fieldid.")";
                   //$details_where = " and (dt0.dtl_Value is not null)";
            }

            $select_clause = "";
            $grouporder_clause = "";

            if($dt_type=="date"){

                    $details_where = $details_where." AND (cast(getTemporalDateString(".$select_field.") as DATETIME) is not null  OR cast(getTemporalDateString(".$select_field.") as SIGNED) is not null)";

                    $select_field = "cast(if(cast(getTemporalDateString(".$select_field.") as DATETIME) is null,"
                        ."concat(cast(getTemporalDateString(".$select_field.") as SIGNED),'-1-1'),"
                        ."getTemporalDateString(".$select_field.")) as DATETIME)";

                    $select_clause = "SELECT min($select_field) as min, max($select_field) as max, count(distinct r0.rec_ID) as cnt ";

            }
            else if($dt_type=="integer" || $dt_type=="float" || $dt_type=="year"){

                    //if ranges are not defined there are two steps 1) find min and max values 2) create select case
                    $select_field = "cast($select_field as DECIMAL)";

                    $select_clause = "SELECT min($select_field) as min, max($select_field) as max, count(distinct r0.rec_ID) as cnt ";

            }
            else { //freetext and other if($dt_type==null || $dt_type=="freetext")

                if($step_level>0 || !($dt_type=="freetext" || $dt_type=="integer" || $dt_type=="float")){

                }else{
                    $select_field = "SUBSTRING(trim(".$select_field."), 1, 1)";
                }

                if($params['needcount']==1){

                    $select_clause = "SELECT $select_field as rng, count(*) as cnt ";
                    if($grouporder_clause==""){
                            $grouporder_clause = " GROUP BY $select_field ORDER BY $select_field";
                    }

                }else if($params['needcount']==2){ //count for related

                    $select_clause = "SELECT $select_field as rng, count(distinct r0.rec_ID) as cnt ";
                    if($grouporder_clause==""){
                            $grouporder_clause = " GROUP BY $select_field ORDER BY $select_field";
                    }

                }else{ //for fields from related records - search distinc values only

                    $select_clause = "SELECT DISTINCT $select_field as rng, 0 as cnt ";
                    if($grouporder_clause==""){
                            $grouporder_clause = " ORDER BY $select_field";
                    }
                }

            }


            //count query
            $query =  $select_clause.$qclauses["from"].$detail_link." WHERE ".$qclauses["where"].$details_where.$grouporder_clause;


            //
//DEBUG
if(@$params['debug']) echo $query."<br>";

            $res = $mysqli->query($query);
            if (!$res){
                $response = $system->addError(HEURIST_DB_ERROR, $savedSearchName."Facet query error", $mysqli->error);
            }else{
                $data = array();

                while ( $row = $res->fetch_row() ) {

                    if($dt_type=="integer" || $dt_type=="float" || $dt_type=="year" || $dt_type=="date"){
                        $third_element = $row[2];
                    }else if($step_level>0 || $dt_type!='freetext'){
                        $third_element = $row[0];
                    }else{
                        $third_element = $row[0].'%';
                    }

                    array_push($data, array($row[0], $row[1], $third_element ));
                }
                $response = array("status"=>HEURIST_OK, "data"=> $data, "svs_id"=>@$params['svs_id'], 
                            "facet_index"=>@$params['facet_index'], 'q'=>$params['q'] );
                $res->close();
            }

        }else{
            $response = $system->addError(HEURIST_INVALID_REQUEST, $savedSearchName."Facet query parameters are invalid. Try to edit and correct this facet search");
        }

        return $response;
    }

    /**
    * Find all related record IDs for given set record IDs
    *
    * @param mixed $system
    * @param mixed $ids -
    *
    * @return array of direct and reverse links (record id, relation type (termid), detail id)
    */
    function recordSearchRelated($system, $ids){

        if(!@$ids){
            return $system->addError(HEURIST_INVALID_REQUEST, "Invalid search request");
        }
        if(is_array($ids)){
            $ids = explode(",", $ids);
        }

        $direct = array();
        $reverse = array();

        $mysqli = $system->get_mysqli();

        //find all target related records
        $query = 'SELECT rl_SourceID, rl_TargetID, rl_RelationTypeID, rl_DetailTypeID FROM recLinks '
            .'where rl_SourceID in ('.$ids.') order by rl_SourceID';


        $res = $mysqli->query($query);
        if (!$res){
            return $system->addError(HEURIST_DB_ERROR, "Search query error", $mysqli->error);
        }else{
                while ($row = $res->fetch_row()) {
                    $relation = new stdClass();
                    $relation->recID = intval($row[0]);
                    $relation->targetID = intval($row[1]);
                    $relation->trmID = intval($row[2]);
                    $relation->dtID  = intval($row[3]);
                    array_push($direct, $relation);
                }
                $res->close();
        }

        //find all reverse related records
        $query = 'SELECT rl_TargetID, rl_SourceID, rl_RelationTypeID, rl_DetailTypeID FROM recLinks '
            .'where rl_TargetID in ('.$ids.') order by rl_TargetID';


        $res = $mysqli->query($query);
        if (!$res){
            return $system->addError(HEURIST_DB_ERROR, "Search query error", $mysqli->error);
        }else{
                while ($row = $res->fetch_row()) {
                    $relation = new stdClass();
                    $relation->recID = intval($row[0]);
                    $relation->sourceID = intval($row[1]);
                    $relation->trmID = intval($row[2]);
                    $relation->dtID  = intval($row[3]);
                    array_push($reverse, $relation);
                }
                $res->close();
        }

        $response = array("status"=>HEURIST_OK,
                     "data"=> array("direct"=>$direct, "reverse"=>$reverse));


        return $response;

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
            return null;// $system->addError(HEURIST_DB_ERROR, "Search query error", $mysqli->error);
        }else{
            if($row = $res->fetch_row()) {
                return $row[0];
            }else{
                return null;
            }
        }
    }

    //
    // returns relationship records(s) for given source and target records
    //
    function recordGetRelationship($system, $sourceID, $targetID ){

        $mysqli = $system->get_mysqli();

        //find all target related records
        $query = 'SELECT rl_RelationID FROM recLinks '
            .'WHERE rl_SourceID='.$sourceID.' AND rl_TargetID='.$targetID.' AND rl_RelationID IS NOT NULL';


        $res = $mysqli->query($query);
        if (!$res){
            return $system->addError(HEURIST_DB_ERROR, "Search query error", $mysqli->error);
        }else{
                $ids = array();
                while ($row = $res->fetch_row()) {
                    array_push($ids, intval($row[0]));
                }
                $res->close();

                return recordSearch($system, array('q'=>'ids:'.implode(',', $ids), 'detail'=>'detail'));
        }


    }

    /**
    * put your comment there...
    *
    * @param mixed $system
    * @param mixed $params
    *
    *       FOR RULES
    *       rules - rules queries - to search related records on server side
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
    *       s - sort order
    *
    *       OUTPUT parameters
    *       vo (=h3) - output format in h3 for backward capability (for detail=ids only)
    *       needall (=1) - by default it returns only first 3000, to return all set it to 1,
    *                      it is set to 1 for server-side rules searches
    *       publiconly (=1) - ignore current user and returns only public records
    *
    *       detail (former 'f') - ids       - only record ids
    *                             header    - record header
    *                             timemap   - record header + timemap details
    *                             detail    - record header + all details
    *                             structure - record header + all details + record type structure (for editing) - NOT USED
    *
    *       CLIENT SIDE
    *       id - unque id to sync with client side
    *       source - id of html element that is originator of this search
    *       qname - original name of saved search (for messaging)
    */
    function recordSearch($system, $params)
    {

        //for error message
        $savedSearchName = @$params['qname']?"Saved search: ".$params['qname']."<br>":"";

        if(!@$params['detail']){
            $params['detail'] = @$params['f']; //backward capability
        }

        $istimemap_request = (@$params['detail']=='timemap');
        $istimemap_counter = 0; //total records with timemap data

        
        $fieldtypes_ids = null;
        if($istimemap_request){
                $fieldtypes_ids = '9,10,11,28';
        }else if(  !in_array(@$params['detail'], array('header','timemap','detail','structure')) ){

            $fieldtypes_ids = explode(',', $params['detail']);
            if(is_array($fieldtypes_ids) && (count($fieldtypes_ids)>1 || is_numeric($fieldtypes_ids[0])) ){
                $fieldtypes_ids = implode(',', $fieldtypes_ids);
                $params['detail'] = 'detail';
            }else{
                $fieldtypes_ids = null;
                $params['detail'] = 'ids';
            }

        }

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


        if($is_ids_only){

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
            .'bkm_PwdReminder ';
            /*.'rec_URLLastVerified,'
            .'rec_URLErrorMessage,'
            .'bkm_PwdReminder ';*/

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

            $params['needall'] = 1; //return all records

            //find main query results
            $fin_result = recordSearch($system, $params);
            //main result set
            $flat_rules[0]['results'] = $is_ids_only ?$fin_result['data']['records'] :array_keys($fin_result['data']['records']); //get ids

            $is_get_relation_records = (@$params['getrelrecs']==1); //get all related and relationship records

            foreach($flat_rules as $idx => $rule){
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

                    $response = recordSearch($system, $params3);

                    if($response['status'] == HEURIST_OK){

                            //merge with final results
                            if($is_ids_only){
                                $fin_result['data']['records'] = array_merge($fin_result['data']['records'], $response['data']['records']);
                            }else{
                                $fin_result['data']['records'] = mergeRecordSets($fin_result['data']['records'], $response['data']['records']);

                                $fin_result['data']['order'] = array_merge($fin_result['data']['order'], array_keys($response['data']['records']));
                                foreach( array_keys($response['data']['records']) as $rt){
                                    $rectype_id = @$rt['4'];
                                    if($rectype_id){
                                        /*if(@$fin_result['data']['rectypes'][$rectype_id]){
                                            $fin_result['data']['rectypes'][$rectype_id]++;
                                        }else{
                                            $fin_result['data']['rectypes'][$rectype_id]=1;
                                        }*/
                                        if(!array_key_exists($rectype_id, $fin_result['data']['rectypes'])){
                                            $fin_result['data']['rectypes'][$rectype_id] = 1;
                                        }
                                    }

                                }
                            }

                            if(!$is_last){ //add top ids for next level
                                $flat_rules[$idx]['results'] = array_merge($flat_rules[$idx]['results'],
                                        $is_ids_only ?$response['data']['records'] :array_keys($response['data']['records']));
                            }

                            if($is_get_relation_records && (strpos($params3['q'],"related_to")>0 || strpos($params3['q'],"relatedfrom")>0) ){ //find relation records (recType=1)

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

                                $where = "WHERE (TOPBIBLIO.rec_ID in (select rl_RelationID from recLinks where (rl_RelationID is not null) and $fld1 in ("
                                            .$params3['topids'].") and $fld2 in ("
                                            .implode(",", $is_ids_only ?$response['data']['records'] :array_keys($response['data']['records'])).")))";

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
                                        $fin_result['data']['relationship'] = array_merge($fin_result['data']['relationship'], $response['data']['records']);
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
                            }  //$is_get_relation_records

                    }else{
                        //@todo terminate execution and return error
                    }

                    $k = $k + 3000;
                }//while chunks

            } //for rules


            $fin_result['data']['count'] = count($fin_result['data']['records']);

            if($return_h3_format){
                        $fin_result = array("resultCount" => $fin_result['data']['count'],
                                          "recordCount" => $fin_result['data']['count'],
                                          "recIDs" => implode(",", $fin_result['data']['records']) );
            }

            //@todo - assign if size less than 3000? only
            $fin_result['data']['mainset'] = $flat_rules[0]['results'];


            return $fin_result;
        }//END RULES

        $chunk_size = PHP_INT_MAX;

        if(@$params['sql']){
             $query = $params['sql'];
        }else{

            $is_mode_json = false;

            if(@$params['q']){

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
                return $system->addError(HEURIST_INVALID_REQUEST, $savedSearchName."Invalid search request. Missed query parameter 'q'");
            }

            if($is_mode_json){
                $aquery = get_sql_query_clauses_NEW($mysqli, $params, $currentUser);
            }else{
                $aquery = get_sql_query_clauses($mysqli, $params, $currentUser);   //!!!! IMPORTANT CALL OR compose_sql_query at once
            }

            if($is_ids_only && @$params['needall']){
                $chunk_size = PHP_INT_MAX;
                $aquery["limit"] = '';
            }else{
                $chunk_size = $system->user_GetPreference('search_detail_limit'); //limit for map/timemap output
            }


            if(!isset($aquery["where"]) || trim($aquery["where"])===''){
                return $system->addError(HEURIST_DB_ERROR, "Invalid search request; unable to construct valid SQL query", null);
            }

            $query =  $select_clause.$aquery["from"]." WHERE ".$aquery["where"].$aquery["sort"].$aquery["limit"].$aquery["offset"];

//error_log($is_mode_json.' '.$query);
            
        }
        


        $res = $mysqli->query($query);
        if (!$res){
            $response = $system->addError(HEURIST_DB_ERROR, $savedSearchName.'Search query error', $mysqli->error);
        }else{

            $fres = $mysqli->query('select found_rows()');
            if (!$fres)     {
                $response = $system->addError(HEURIST_DB_ERROR, $savedSearchName.'Search query error (retrieving number of records)', $mysqli->error);
            }else{

                $total_count_rows = $fres->fetch_row();
                $total_count_rows = $total_count_rows[0];
                $fres->close();

                if($is_ids_only)
                { //------------------------  LOAD and RETURN only IDS

                    $records = array();

                    while ($row = $res->fetch_row())  {
                        array_push($records, (int)$row[0]);
                    }
                    $res->close();

                    if(@$params['vo']=='h3'){ //output version
                        $response = array('resultCount' => $total_count_rows,
                                          'recordCount' => count($records),
                                          'recIDs' => implode(',', $records) );
                    }else{

                        $response = array('status'=>HEURIST_OK,
                            'data'=> array(
                                'queryid'=>@$params['id'],  //query unqiue id
                                'count'=>$total_count_rows,
                                'offset'=>get_offset($params),
                                'reccount'=>count($records),
                                'records'=>$records));

                    }

                }else{ //----------------------------------


                    // read all field names
                    $_flds =  $res->fetch_fields();
                    $fields = array();
                    foreach($_flds as $fld){
                        array_push($fields, $fld->name);
                    }
                    array_push($fields, 'rec_ThumbnailURL');
                    //array_push($fields, 'rec_Icon'); //last one -icon ID

                    $rectype_structures  = array();
                    $rectypes = array();
                    $records = array();
                    $order = array();
                    
                    // load all records
                    while ($row = $res->fetch_row()) {  //3000 maximal allowed chunk
                        array_push( $row, ($fieldtypes_ids)?'':fileGetThumbnailURL($system, $row[2]) );
                        //array_push( $row, $row[4] ); //by default icon if record type ID
                        $records[$row[2]] = $row;
                        array_push($order, $row[2]);
                        if(!@$rectypes[$row[4]]){
                            $rectypes[$row[4]]=1;
                        }
                    }
                    $res->close();
                    
                        if(($istimemap_request ||
                            $params['detail']=='detail' ||
                            $params['detail']=='structure') && count($records)>0){

                            //search for specific details
                            if(!$fieldtypes_ids && $fieldtypes_ids!=''){

                                $detail_query =  'select dtl_RecID,'
                                .'dtl_DetailTypeID,'     // 0
                                .'dtl_Value,'            // 1
                                .'AsWKT(dtl_Geo), 0, 0, 0 '
                                .'from recDetails
                                where dtl_RecID in (' . join(',', array_keys($records)) . ') '
                                .' and dtl_DetailTypeID in ('.$fieldtypes_ids.')';

                            }else{
                                $detail_query = 'select dtl_RecID,'
                                .'dtl_DetailTypeID,'     // 0
                                .'dtl_Value,'            // 1
                                .'AsWKT(dtl_Geo),'    // 2
                                .'dtl_UploadedFileID,'   // 3
                                .'recUploadedFiles.ulf_ObfuscatedFileID,'   // 4
                                .'recUploadedFiles.ulf_Parameters '         // 5
                                .'from recDetails
                                  left join recUploadedFiles on ulf_ID = dtl_UploadedFileID
                                where dtl_RecID in (' . join(',', array_keys($records)) . ')';

                            }

                            // @todo - we may use getAllRecordDetails
                            $res_det = $mysqli->query( $detail_query );


                            if (!$res_det){
                                $response = $system->addError(HEURIST_DB_ERROR, $savedSearchName.'Search query error (retrieving details)', $mysqli->error);
                                return $response;
                            }else{
                                
                                while ($row = $res_det->fetch_row()) {
                                    $recID = array_shift($row);
                                    if( !array_key_exists('d', $records[$recID]) ){
                                        $records[$recID]['d'] = array();
                                    }
                                    $dtyID = $row[0];

                                    $val = null;
                                    
                                    if($row[2]){
                                        $val = $row[1].' '.$row[2];     //dtl_Geo @todo convert to JSON
                                    }else if($row[3]){
                                        $val = array($row[4], $row[5]); //obfuscated value for fileid
                                    }else if(@$row[1]) {
                                        $val = $row[1];
                                    }
                                    
                                    if($val){
                                        if( !array_key_exists($dtyID, $records[$recID]['d']) ){
                                            $records[$recID]['d'][$dtyID] = array();
                                        }
                                        array_push($records[$recID]['d'][$dtyID], $val);
                                    }
                                }//while
                                $res_det->close();

///@todo
// 1. optimize loop - include into main detail loop
// 2. exit loop if more than 5000 geo enabled
// 3. return geojson and timeline items
                                //additional loop for timemap request 
                                //1. exclude records without timemap data
                                //2. limit to $chunk_size
                                if($istimemap_request){
                                    $tm_records = array();
                                    $order = array();
                                    $rectypes = array();
                                    foreach ($records as $recID => $record) {
                                        if(is_array($record['d']) && count($record['d'])>0){
                                            //this record is time enabled 
                                            if($istimemap_counter<$chunk_size){
                                                $tm_records[$recID] = $record;        
                                                array_push($order, $recID);
                                                $rectypes[$record[4]]=1; 
                                            }
                                            $istimemap_counter++;
                                        }
                                   }
                                   $records = $tm_records;
                                   $total_count_rows = $istimemap_counter;
                                }//$istimemap_request
                            }
                        }//$need_details

                        $rectypes = array_keys($rectypes);
                        if( $params['detail']=='structure' && count($rectypes)>0){ //rarely used in editing.js
                              //description of recordtype and used detail types
                              $rectype_structures = dbs_GetRectypeStructures($system, $rectypes, 1); //no groups
                        }

                        //"query"=>$query,
                        $response = array('status'=>HEURIST_OK,
                            'data'=> array(
                                //'query'=>$query,
                                'queryid'=>@$params['id'],  //query unqiue id
                                'count'=>$total_count_rows,
                                'offset'=>get_offset($params),
                                'reccount'=>count($records),
                                'fields'=>$fields,
                                'records'=>$records,
                                'order'=>$order,
                                'rectypes'=>$rectypes,
                                'structures'=>$rectype_structures));


                }//$is_ids_only


            

            }
        }

        return $response;

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

    function _createFlatRule(&$flat_rules, $r_tree, $parent_index){

            if($r_tree){
                foreach ($r_tree as $rule) {
                    $e_rule = array('query'=>$rule['query'],
                                    'results'=>array(),
                                    'parent'=>$parent_index,
                                    'islast'=>(!@$rule['levels'] || count($rule['levels'])==0)?1:0 );
                    array_push($flat_rules, $e_rule );
                    _createFlatRule($flat_rules, @$rule['levels'], count($flat_rules)-1);
                }
            }

    }


    function _loadRecordDetails( $system, $record_ids){

    }
?>
