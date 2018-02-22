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
    // @param mixed $system
    // @param mixed $params - array or parameters
    //      q - JSON query array
    //      field - field id to search
    //      type - field type (todo - search it dynamically with getDetailType)
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
            $facet_type =  @$params['facet_type']; //0 direct search search, 1 - select/slider, 2 - list inline, 3 - list column
            $facet_groupby =  @$params['facet_groupby'];  //by first char for freetext, by year for dates, by level for enum
            $vocabulary_id =  @$params['vocabulary_id'];  //special case for groupby first level
            $limit         = @$params['limit']; //limit for preview
            
            //special parameter to avoid nested queries - it allows performs correct count for distinct target record type
            //besides it return correct field name to be used in count function
            $params['nested'] = (@$params['needcount']!=2); 
            

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
            
                //NOTE - it applies for VOCABULARY only (individual selection of terms is not applicable)
                
                // 1. get first level of terms using $vocabulary_id 
                $first_level = getTermChildren($vocabulary_id, $system, true); //get first level for vocabulary
                
//error_log($vocabulary_id.'  '.print_r($first_level, true));                                
                
                // 2.  find all children as plain array  [[parentid, child_id, child_id....],.....]
                $terms = array();
                foreach ($first_level as $parentID){
                    $children = getTermChildren($parentID, $system, false); //get first level for vocabulary    
                    array_unshift($children, $parentID);
                    array_push($terms, $children);
                }
//error_log(print_r($terms, true));                
                //3.  find distinct count for recid for every set of terms
                $select_clause = "SELECT count(distinct r0.rec_ID) as cnt ";
                
                $data = array();
                
                foreach ($terms as $vocab){
                    $d_where = $details_where.' AND ('.$select_field.' IN ('.implode(',', $vocab).'))';
                    //count query
                    $query =  $select_clause.$qclauses['from'].$detail_link.' WHERE '.$qclauses['where'].$d_where;
                    
                    /*if($limit>0){
                        $query = $query.' LIMIT '.$limit;    
                    }*/
                    
//error_log($query);                    
                    $res = $mysqli->query($query);
                    if (!$res){
                        return $system->addError(HEURIST_DB_ERROR, $savedSearchName
                        .'Facet query error. Parameters:'.print_r($params, true), $mysqli->error);
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
                            "facet_index"=>@$params['facet_index'], 'q'=>$params['q'] );
                
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
                
                    /*temp solution for single level linkage only
                    if(strpos($qclauses["where"],'recLinks rl0_')===false){
                        if (strpos($qclauses["where"],'rl0x1.rl_SourceID IN')>0){
                            $select_clause = "SELECT $select_field as rng, count(DISTINCT rl0x1.rl_SourceID) as cnt ";
                        }else if (strpos($qclauses["where"],'rl0x1.rl_TargetID IN')>0){
                            $select_clause = "SELECT $select_field as rng, count(DISTINCT rl0x1.rl_TargetID) as cnt ";   
                        }
                    }else{
                        // it counts linked records, thus
                        // it returns wrong value if there is more than one linked record per target record type
                        $select_clause = "SELECT $select_field as rng, count(*) as cnt ";
                    }
                    */
                    
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
//DEBUG     facet search
//if(@$params['debug']) echo $query."<br>";
//error_log($query);

            $res = $mysqli->query($query);
            if (!$res){
                $response = $system->addError(HEURIST_DB_ERROR, $savedSearchName
                        .'Facet query error. Parameters:'.print_r($params, true), $mysqli->error);
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
                    }
                    
                    //value, count, second value(max for range) or search value for firstchar
                    array_push($data, array($row[0], $row[1], $third_element ));
                }
                $response = array("status"=>HEURIST_OK, "data"=> $data, "svs_id"=>@$params['svs_id'], 
                            "request_id"=>@$params['request_id'], //'dbg_query'=>$query,
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
    * @param mixed $direction -  1 direct/ -1 reverse/ 0 both
    *
    * @return array of direct and reverse links (record id, relation type (termid), detail id)
    */
    function recordSearchRelated($system, $ids, $direction=0){

        if(!@$ids){
            return $system->addError(HEURIST_INVALID_REQUEST, "Invalid search request");
        }
        if(is_array($ids)){
            $ids = implode(",", $ids);
        }
        if(!($direction==1||$direction==-1)){
            $direction = 0;
        }
        
        $rel_ids = array();
        $direct = array();
        $reverse = array();
        $headers = array(); //record title and type for main record

        $mysqli = $system->get_mysqli();
        
        
        //find all target related records
        $query = 'SELECT rl_SourceID, rl_TargetID, rl_RelationTypeID, rl_DetailTypeID, rl_RelationID FROM recLinks '
            .'where rl_SourceID in ('.$ids.') order by rl_SourceID';

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
                    array_push($direct, $relation);
                    
                    array_push($rel_ids, intval($row[1]));
                }
                $res->close();
        }

        //find all reverse related records
        $query = 'SELECT rl_TargetID, rl_SourceID, rl_RelationTypeID, rl_DetailTypeID, rl_RelationID FROM recLinks '
            .'where rl_TargetID in ('.$ids.') order by rl_TargetID';


        $res = $mysqli->query($query);
        if (!$res){
            return $system->addError(HEURIST_DB_ERROR, "Search query error on reverse related records. Query ".$query, $mysqli->error);
        }else{
                while ($row = $res->fetch_row()) {
                    $relation = new stdClass();
                    $relation->recID = intval($row[0]);
                    $relation->sourceID = intval($row[1]);
                    $relation->trmID = intval($row[2]);
                    $relation->dtID  = intval($row[3]);
                    $relation->relationID  = intval($row[4]);
                    array_push($reverse, $relation);
                    
                    array_push($rel_ids, intval($row[1]));
                }
                $res->close();
        }
        
        
        //find all rectitles and record types for main recordset AND all related records
        if(!is_array($ids)){
            $ids = explode(',',$ids);
        }
        $ids = array_merge($ids, $rel_ids);        
        $query = 'SELECT rec_ID, rec_Title, rec_RecTypeID from Records where rec_ID in ('.implode(',',$ids).')';
        $res = $mysqli->query($query);
        if (!$res){
            return $system->addError(HEURIST_DB_ERROR, "Search query error on search related. Query ".$query, $mysqli->error);
        }else{
                while ($row = $res->fetch_row()) {
                    $headers[$row[0]] = array($row[1], $row[2]);   
                }
                $res->close();
        }
        
        $response = array("status"=>HEURIST_OK,
                     "data"=> array("direct"=>$direct, "reverse"=>$reverse, "headers"=>$headers));


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
    // returns relationship records(s) for given source and target records
    //
    function recordGetRelationship($system, $sourceID, $targetID ){

        $mysqli = $system->get_mysqli();

        //find all target related records
        $query = 'SELECT rl_RelationID FROM recLinks '
            .'WHERE rl_SourceID='.$sourceID.' AND rl_TargetID='.$targetID.' AND rl_RelationID IS NOT NULL';


        $res = $mysqli->query($query);
        if (!$res){
            return $system->addError(HEURIST_DB_ERROR, "Search query error on relationship records for source-target. Query ".$query, $mysqli->error);
        }else{
                $ids = array();
                while ($row = $res->fetch_row()) {
                    array_push($ids, intval($row[0]));
                }
                $res->close();

                return recordSearch($system, array('q'=>'ids:'.implode(',', $ids), 'detail'=>'detail'));
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
    *                             count     - only count of records  
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

        $memory_limit = $system->get_php_bytes('memory_limit');
        
        //for error message
        $savedSearchName = @$params['qname']?"Saved search: ".$params['qname']."<br>":"";

        if(!@$params['detail']){
            $params['detail'] = @$params['f']; //backward capability
        }
        

        $fieldtypes_in_res = null;
        $istimemap_request = (@$params['detail']=='timemap');
        $istimemap_counter = 0; //total records with timemap data
        $needThumbField = false;
        $needThumbBackground = false;
        $needCompleteInformation = false; //if true - get all header fields and relations
        $relations = null;

        if(@$params['detail']=='complete'){
            $params['detail'] = 'detail';
            $needCompleteInformation = true;
        }
        
        $fieldtypes_ids = null;
        if($istimemap_request){
             //get date,year and geo fields from structure
             $fieldtypes_ids = dbs_GetDetailTypes($system, array('date','year','geo'), 3);
             if($fieldtypes_ids==null || count($fieldtypes_ids)==0){
                //this case nearly impossible since system always has date and geo fields 
                $fieldtypes_ids = array(DT_GEO_OBJECT, DT_DATE, DT_START_DATE, DT_END_DATE); //9,10,11,28';    
             }
             $fieldtypes_ids = implode(',', $fieldtypes_ids);
             $needThumbField = true;
//DEBUG error_log('timemap fields '.$fieldtypes_ids);
             
        }else if(  !in_array(@$params['detail'], array('header','timemap','detail','structure')) ){ //list of specific detailtypes
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
        
       
        //specific for boro parameters - returns prevail bg color for thumbnail image
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

            $params['needall'] = 1; //return all records
            
            $resSearch = recordSearch($system, $params);

            $keepMainSet = (@$params['rulesonly']!=1);
            
            if(is_array($resSearch) && $resSearch['status']!=HEURIST_OK){
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
                $fin_result['data']['records'] = array();
                $fin_result['data']['reccount'] = 0;
                $fin_result['data']['count'] = 0;
            }

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

//DEBUg  error_log(print_r($params3,true));                    
                    
                    $response = recordSearch($system, $params3);

                    if($response['status'] == HEURIST_OK){

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

                            if(!$is_last){ //add top ids for next level
                                $flat_rules[$idx]['results'] = array_merge_unique($flat_rules[$idx]['results'],
                                        $is_ids_only ?$response['data']['records'] :array_keys($response['data']['records']));
                            }

                            if($is_get_relation_records && 
                            (strpos($params3['q'],"related_to")>0 || strpos($params3['q'],"relatedfrom")>0) ){ //find relationship records (recType=1)

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
                                
                                $ids_party1 = $params3['topids'];
                                $ids_party2 = $is_ids_only?$response['data']['records'] :array_keys($response['data']['records']);
                                
                                if(is_array($ids_party2) && count($ids_party2)>0)
                                {

//error_log('get related '.$params3['q'].'   '.implode(",",$ids_party2));
                                
                                $where = "WHERE (TOPBIBLIO.rec_ID in (select rl_RelationID from recLinks "
                                    ."where (rl_RelationID is not null) and $fld1 in ("
                                    .$ids_party1.") and $fld2 in (".implode(",", $ids_party2).")))";

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

            if($return_h3_format){
                        $fin_result = array("resultCount" => $fin_result['data']['count'],
                                          "recordCount" => $fin_result['data']['count'],
                                          "recIDs" => implode(",", $fin_result['data']['records']) );
            }

            //@todo - assign if size less than 3000? only
            $fin_result['data']['mainset'] = $flat_rules[0]['results'];

            return $fin_result;
        }//END RULES

        $search_detail_limit = PHP_INT_MAX;

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
            

            

            if($is_count_only || ($is_ids_only && @$params['needall']) || !$system->has_access() ){ //not logged in
                $search_detail_limit = PHP_INT_MAX;
                $aquery["limit"] = '';
            }else{
                $search_detail_limit = $system->user_GetPreference('search_detail_limit'); //limit for map/timemap output
            }


            if(!isset($aquery["where"]) || trim($aquery["where"])===''){
                return $system->addError(HEURIST_DB_ERROR, "Invalid search request; unable to construct valid SQL query", null);
            }

            $query =  $select_clause.$aquery["from"]." WHERE ".$aquery["where"].$aquery["sort"].$aquery["limit"].$aquery["offset"];

        }
        
//error_log(' Q='.$query);                
//DEBUG 
//return $system->addError(HEURIST_INVALID_REQUEST,  'DEBUG:'.$query);

//error_log($istimemap_request.' limit '.$aquery["limit"]);

        $res = $mysqli->query($query);
        if (!$res){
            
//error_log('params '.print_r($params, true));            
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
                    return $system->addError(HEURIST_SYSTEM_CONFIG, 
                        'Search query produces '.$total_count_rows.' records. Memory limit does not allow to retrieve all of them.'
                         .' Please filter to a smaller set of results.');
                }
                

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
                                'entityName'=>'Records',
                                'count'=>$total_count_rows,
                                'offset'=>get_offset($params),
                                'reccount'=>count($records),
                                'records'=>$records));

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
                    if($needThumbField) array_push($fields, 'rec_ThumbnailURL');
                    if($needThumbBackground) array_push($fields, 'rec_ThumbnailBg');
                    //array_push($fields, 'rec_Icon'); //last one -icon ID

                    
                    // load all records
                    while ($row = $res->fetch_row()) {

                        if($needThumbField) {
                            $tres = fileGetThumbnailURL($system, $row[2], $needThumbBackground);   
                            array_push( $row, $tres['url'] );
                            if($needThumbBackground) array_push( $row, $tres['bg_color'] );
                        }
                        
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
                     
//error_log('total '.$res_count.'  '.$istimemap_request);                     
$loop_cnt=1;                            
                    while ($offset<$res_count){   
                            
//here was as problem, since chunk size for mapping can be 5000 or more we got memory overflow here
//resaon the list of ids in SELECT is bigger than mySQL limit
//solution - we perfrom the series of request for details by 1000 records
                            $chunk_rec_ids = array_slice($all_rec_ids, $offset, 1000); 
                            $offset = $offset + 1000;

                            //search for specific details
                            if($fieldtypes_ids!=null && $fieldtypes_ids!=''){
                                $detail_query =  'select dtl_RecID,'
                                .'dtl_DetailTypeID,'     // 0
                                .'dtl_Value,'            // 1
                                .'AsWKT(dtl_Geo), 0, 0, 0 '
                                .'from recDetails
                                where dtl_RecID in (' . join(',', $chunk_rec_ids) . ') '
                                .' and dtl_DetailTypeID in ('.$fieldtypes_ids.')';
                                
                            }else{
                                
                                if($needCompleteInformation){
                                    $ulf_fields = 'f.ulf_OrigFileName,f.ulf_ExternalFileReference,f.ulf_ObfuscatedFileID,'
                                                    .'f.ulf_MimeExt, f.ulf_Parameters';  //4,5,6,7,8
                                }else{
                                    $ulf_fields = 'f.ulf_ObfuscatedFileID, f.ulf_Parameters';  //4,5
                                }
                                
                                $detail_query = 'select dtl_RecID,'
                                .'dtl_DetailTypeID,'     // 0
                                .'dtl_Value,'            // 1
                                .'AsWKT(dtl_Geo),'    // 2
                                .'dtl_UploadedFileID,'   // 3
                                .$ulf_fields   
                                .' from recDetails
                                  left join recUploadedFiles as f on f.ulf_ID = dtl_UploadedFileID
                                where dtl_RecID in (' . join(',', $chunk_rec_ids) . ')';

                            }

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
                                    }
                                    $dtyID = $row[0];

                                    $val = null;
                                    
                                    if($row[2]){
                                        $val = $row[1].' '.$row[2];     //dtl_Geo @todo convert to JSON
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
                            //error_log('found count '.count($records));                                   
                            //error_log('maptime. total '.count($records).'  toclnt='.count($tm_records).' tot_tm='.$istimemap_counter);                                   
                                   $records = $tm_records;
                                   $total_count_rows = $istimemap_counter;
                            }else
                            if($needCompleteInformation){
                                $relations = recordSearchRelated($system, $all_rec_ids);
                                if($relations['status']==HEURIST_OK){
                                    $relations = $relations['data'];
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
    
    //
    // pickup only timemap enabled records
    //
    function _getTimemapRecords($res){
        
        
    }

    //backward capability - remove as soon as old uploadFileOrDefineURL get rid of use
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
    
?>
