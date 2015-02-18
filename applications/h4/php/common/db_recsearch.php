<?php

    /** 
    * Library to search records
    * 
    * recordSearchMinMax - Find minimal and maximal values for given detail type and record type
    * recordSearch
    * 
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
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


    require_once (dirname(__FILE__).'/db_users.php');
    require_once (dirname(__FILE__).'/db_files.php');
    require_once (dirname(__FILE__).'/compose_sql.php');
    require_once (dirname(__FILE__).'/db_structure.php');

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
            .$params['rt']." and dtl_DetailTypeID=".$params['dt'];

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
            $response = $system->addError(HEURIST_INVALID_REQUEST);
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
    /**
    * Find
    * 
    * @param mixed $system
    * @param mixed $params - array  dt - detail type(s) ID or recTitle, recModified,      - field for current query
    *                               type - field type (freetext, date, integer), for deepest level
    *                               q - current query
    *                               w - domain
    *                               level0 - f:XXX for level 0
    *                               level1 - query for level 1   t:XXX f:YYY
    *                               level2 - query for level 2 
    * 
    * The idea is to add count to current search - this search is always returns rectypes defined in level 0
    * 
    */
    function recordSearchFacets($system, $params){

        if(@$params['level0'] && @$params['type']){

            $mysqli = $system->get_mysqli();
            $currentUser = $system->getCurrentUser();
            $dt_type = $params['type'];
            $step_level = @$params['step'];

            //get SQL clauses for current query
            $qclauses = get_sql_query_clauses($mysqli, $params, $currentUser);

            if($dt_type=="rectype"){

                $select_clause = "SELECT rec_RecTypeID, count(*) as cnt ";
                $where_clause = " WHERE ";
                $grouporder_clause = " GROUP BY rec_RecTypeID ORDER BY rec_RecTypeID";

            }else{

                //@todo take type with getDetailType ?
                $fieldid = $params['level0'];
                if(strpos($fieldid,"f:")===0){
                    $fieldid = substr($fieldid,2);
                }

                //fill array max length 3  from level 0 to 2
                $resource_rt0 = null;
                $resource_rt1 = null;   
                $resource_field0 = null;
                $resource_field1 = null;
                $links = array();
                $links[0] = array("field"=>$fieldid);

                if(@$params['level1']){
                    $links[1] = _getRt_Ft($params['level1']);
                    $resource_rt0 = $links[1]["rt"];
                    $resource_field0 = $links[1]["field"];
                    if(@$params['level2']){
                        $links[2] = _getRt_Ft($params['level2']);
                        $resource_rt1 = $links[2]["rt"];
                        $resource_field1 = $links[2]["field"];
                    }
                }

                $select_field = "";
                $where_clause2 = "";

                if($resource_rt0==null){ //only level 0 -------------------------------------

                    if($fieldid=='recTitle'){
                        $select_field = "TOPBIBLIO.rec_Title";
                    }else if($fieldid=='recModified'){
                        $select_field = "TOPBIBLIO.rec_Modified";
                    }else{
                        $select_field = "TOPDET.dtl_Value";
                        $where_clause2 = ", recDetails TOPDET "
                        ." WHERE TOPDET.dtl_RecId=TOPBIBLIO.rec_ID and TOPDET.dtl_DetailTypeId=".$fieldid;
                    }


                }else{

                    if($resource_rt1==null){  //only level 1 ------------------------------------

                        $linkdt0 = "";
                        $linkdt0_where = "";

                        if($resource_field0=='title'){
                            $select_field = "linked0.rec_Title";
                        }else if($resource_field0=='modified'){
                            $select_field = "linked0.rec_Modified";
                        }else{
                            $select_field = "linkeddt0.dtl_Value";
                            $linkdt0 = " LEFT JOIN recDetails linkeddt0 ON (linkeddt0.dtl_RecID=linked0.rec_ID and linkeddt0.dtl_DetailTypeID=".$resource_field0.")";
                            $linkdt0_where = " and linkeddt0.dtl_Value is not null";
                        }

                        if($fieldid=="relmarker"){

                            $where_clause2 = ", recRelationshipsCache rel0 "
                            ." LEFT JOIN Records linked0 ON (linked0.rec_RecTypeID in (".$resource_rt0.")) "
                            .$linkdt0
                            ." WHERE ((rel0.rrc_TargetRecID=TOPBIBLIO.rec_ID and rel0.rrc_SourceRecID=linked0.rec_ID) "
                            ."     or (rel0.rrc_SourceRecID=TOPBIBLIO.rec_ID and rel0.rrc_TargetRecID=linked0.rec_ID)) ".$linkdt0_where;

                        }else{
                            $where_clause2 = ", recDetails TOPDET "
                            ." LEFT JOIN Records linked0 ON (TOPDET.dtl_Value=linked0.rec_ID and linked0.rec_RecTypeID in (".$resource_rt0.")) "
                            .$linkdt0
                            ." WHERE TOPDET.dtl_RecId=TOPBIBLIO.rec_ID and TOPDET.dtl_DetailTypeId=".$fieldid . $linkdt0_where;
                        }


                    }else{ //level 2  -------------------------------------

                        $linkdt1 = "";
                        $linkdt1_where = "";
                        if($resource_field1=='title'){
                            $select_field = "linked1.rec_Title";
                        }else if($resource_field1=='modified'){
                            $select_field = "linked1.rec_Modified";
                        }else{
                            $select_field = "linkeddt1.dtl_Value";
                            $linkdt1 = " LEFT JOIN recDetails linkeddt1 ON (linkeddt1.dtl_RecID=linked1.rec_ID and linkeddt1.dtl_DetailTypeID=".$resource_field1.")";
                            $linkdt1_where = " and linkeddt1.dtl_Value is not null";
                        }

                        if($fieldid=="relmarker"){   //CASE1 relation - relation

                            if($resource_field0=='relmarker'){
                                $where_clause2 = ", recRelationshipsCache rel0, recRelationshipsCache rel1 "
                                ." LEFT JOIN Records linked0 ON (linked0.rec_RecTypeID in (".$resource_rt0.")) "
                                ." LEFT JOIN Records linked1 ON (linked1.rec_RecTypeID in (".$resource_rt1.")) "
                                .$linkdt1
                                ." WHERE ((rel0.rrc_TargetRecID=TOPBIBLIO.rec_ID and rel0.rrc_SourceRecID=linked0.rec_ID) "
                                ."     or (rel0.rrc_SourceRecID=TOPBIBLIO.rec_ID and rel0.rrc_TargetRecID=linked0.rec_ID)) "
                                ."    and ((rel1.rrc_TargetRecID=rel0.rrc_SourceRecID and rel1.rrc_SourceRecID=linked1.rec_ID)"
                                ."     or (rel1.rrc_SourceRecID=rel0.rrc_TargetRecID and rel1.rrc_TargetRecID=linked1.rec_ID)) ". $linkdt1_where;

                            }else{  //CASE2 relation - pointer

                                $where_clause2 = ", recRelationshipsCache rel0 "
                                ." LEFT JOIN Records linked0 ON (linked0.rec_RecTypeID in (".$resource_rt0.")) "
                                ." LEFT JOIN recDetails linkeddt0 ON (linkeddt0.dtl_RecID=linked0.rec_ID and linkeddt0.dtl_DetailTypeID=".$resource_field0.")"
                                ." LEFT JOIN Records linked1 ON (linkeddt0.dtl_Value=linked1.rec_ID and linked1.rec_RecTypeID in (".$resource_rt1.")) "
                                .$linkdt1
                                ." WHERE ((rel0.rrc_TargetRecID=TOPBIBLIO.rec_ID and rel0.rrc_SourceRecID=linked0.rec_ID) "
                                ."     or (rel0.rrc_SourceRecID=TOPBIBLIO.rec_ID and rel0.rrc_TargetRecID=linked0.rec_ID)) " . $linkdt1_where;
                            }


                        }else{

                            if($resource_field0=='relmarker'){  //CASE3 pointer - relation

                                $where_clause2 = ", recDetails TOPDET, recRelationshipsCache rel1 "
                                ." LEFT JOIN Records linked0 ON (TOPDET.dtl_Value=linked0.rec_ID and linked0.rec_RecTypeID in (".$resource_rt0.")) "
                                ." LEFT JOIN Records linked1 ON (linked1.rec_RecTypeID in (".$resource_rt1.")) "
                                .$linkdt1
                                ." WHERE TOPDET.dtl_RecID=TOPBIBLIO.rec_ID and TOPDET.dtl_DetailTypeID=".$fieldid." and "
                                ."    and ((rel1.rrc_TargetRecID=linked0.rec_ID and rel1.rrc_SourceRecID=linked1.rec_ID)"
                                ."     or (rel1.rrc_SourceRecID=linked0.rec_ID and rel1.rrc_TargetRecID=linked1.rec_ID)) " . $linkdt1_where;

                            }else{ //CASE4 pointer - pointer

                                $where_clause2 = ", recDetails TOPDET "
                                ." LEFT JOIN Records linked0 ON (TOPDET.dtl_Value=linked0.rec_ID and linked0.rec_RecTypeID in (".$resource_rt0.")) "
                                ." LEFT JOIN recDetails linkeddt0 ON (linkeddt0.dtl_RecID=linked0.rec_ID and linkeddt0.dtl_DetailTypeID=".$resource_field0.")"
                                ." LEFT JOIN Records linked1 ON (linkeddt0.dtl_Value=linked1.rec_ID and linked1.rec_RecTypeID in (".$resource_rt1.")) "
                                .$linkdt1
                                ." WHERE TOPDET.dtl_RecID=TOPBIBLIO.rec_ID and TOPDET.dtl_DetailTypeID=". $fieldid . $linkdt1_where;

                            }

                        }

                    }

                }

                $where_clause = ($where_clause2=="")?" WHERE ":$where_clause2." and ";
                $grouporder_clause = "";
                $ranges = array();

                if($dt_type=="freetext"){

                    if($step_level>0){

                    }else{
                        $select_field = "SUBSTRING(trim(".$select_field."), 1, 1)";    
                    }


                }else if($dt_type=="date" || $dt_type=="year"){

                    /*      GROUP BY DECADES
                    select concat(decade, '-', decade + 9) as year, count(*) as count
                    from (select floor(year(dtl_Value) / 10) * 10 as decade
                    from recDetails, Records where dt_RecId=rec_ID and dtl_DetailTypeId=2)
                    group by decade

                    select dtl_Value, getTemporalDateString(dtl_Value) as bdate, floor( if( dtl_Value = concat( '', 0 + dtl_Value ), dtl_Value,  year(dtl_Value) ) / 10) * 10 as decade 
                    from recDetails, Records where dtl_RecId=rec_ID and dtl_DetailTypeId=2 and rec_RecTypeID=20


                    select count(*) as count, concat(decade, '-', decade + 9) as year
                    from (select floor(year(`year`) / 10) * 10 as decade
                    from tbl_people) t
                    group by decade
                    GROUP BY YEAR(record_date), MONTH(record_date)
                    GROUP BY EXTRACT(YEAR_MONTH FROM record_date)
                    */                

                    $order_field = $select_field;
                    if(strpos($select_field, "dtl_Value")>0){
                        $select_field = "cast(if(cast(getTemporalDateString(TOPDET.dtl_Value) as DATETIME) is null,concat(cast(getTemporalDateString(TOPDET.dtl_Value) as SIGNED),'-1-1'),getTemporalDateString(TOPDET.dtl_Value)) as DATETIME)";
                    }
                    $select_clause = "SELECT min($select_field) as min, max($select_field) as max, count(*) as cnt  ";
                    //min-max query
                    $query =  $select_clause.$qclauses["from"].$where_clause.$qclauses["where"];

                    //DEBUG error_log("MINMAX >>>".$query);

                    $res = $mysqli->query($query);
                    if (!$res){
                        $response = $system->addError(HEURIST_DB_ERROR, "Minmax query error", $mysqli->error);
                    }else{

                        $row = $res->fetch_row();
                        $min = new DateTime($row[0]);
                        $max = new DateTime($row[1]);
                        $cnt = $row[2];
                        $INTERVAL_NUM = 10;

                        if($cnt>$INTERVAL_NUM){ //otherwise individual values

                            //find delta 1000 years, 100 years, 10 years, 1 year, 12 month, days                    
                            $diff = $max->diff($min);
                            $format = "Y";

                            //error_log("difference >>>".$diff->y."   ".$row[0]."  ".$row[1]);                        

                            if($diff->y > 20){
                                if($diff->y > 2000){
                                    $div=1000;
                                }else if($diff->y > 150){
                                    $div=100;
                                }else{
                                    $div=10;
                                }
                                $delta = new DateInterval("P".$div."Y");
                                $min = DateTime::createFromFormat('Y-m-d', str_pad( floor(intval($min->format('Y'))/$div)*$div, 4, "0", STR_PAD_LEFT).'-01-01' );
                            }else if($diff->y > 1){
                                $delta = new DateInterval("P1Y");
                                $min = date_create($min->format('Y-1-1'));
                            }else if($diff->m > 1){
                                $delta = new DateInterval("P1M");
                                $format = "Y-M";
                                $min = date_create($min->format('Y-m-1'));
                            }else {
                                $delta = new DateInterval("P1D");
                                $format = "Y-m-d";
                            }

                            $caseop = "(case ";

                            while ($min<$max){

                                $smin = $min->format('Y-m-d');
                                $ssmin = $min->format($format);
                                $min->add($delta);
                                $smax = $min->format('Y-m-d');
                                //$ssmax = $min->format($format);

                                $caseop .= " when ( $select_field>='".$smin."' and $select_field<'".$smax."') then '".count($ranges)."' ";

                                array_push($ranges, array("label"=>$ssmin, "query"=>"$smin<>$smax"));
                            }

                            $caseop .= " end)";                        

                            $grouporder_clause = " GROUP BY rng ORDER BY $order_field"; 
                            $select_field = $caseop; 
                        }
                    }


                }else if($dt_type=="integer" || $dt_type=="float"){

                    //if ranges are not defined there are two steps 1) find min and max values 2) create select case
                    $select_field = "cast($select_field as DECIMAL)";

                    $select_clause = "SELECT min($select_field) as min, max($select_field) as max, count(*) as cnt ";
                    //min-max query
                    $query =  $select_clause.$qclauses["from"].$where_clause.$qclauses["where"];

                    //DEBUG error_log("MINMAX >>>".$query);

                    $res = $mysqli->query($query);
                    if (!$res){
                        $response = $system->addError(HEURIST_DB_ERROR, "Minmax query error", $mysqli->error);
                    }else{
                        $row = $res->fetch_row();
                        $min = $row[0];
                        $max = $row[1];
                        $cnt = $row[2];

                        $INTERVAL_NUM = 10;

                        if($cnt>$INTERVAL_NUM){ //otherwise individual values

                            $delta = ($max - $min)/$INTERVAL_NUM;
                            if($dt=="integer"){
                                if(Math.abs($delta)<1){
                                    $delta = $delta<0?-1:1;
                                }
                                $delta = Math.floor($delta);
                            }
                            $cnt = 0;
                            $caseop = "(case ";


                            while ($min<$max && $cnt<$INTERVAL_NUM){
                                $val1 = ($min+$delta>$max)?$max:$min+$delta;
                                if($cnt==$INTERVAL_NUM-1){
                                    $val1 = $max;
                                }

                                $caseop .= " when $select_field between ".$min." and ".$val1." then '".count($ranges)."' ";

                                array_push($ranges, array("label"=>"$min ~ $val1", "query"=>"$min<>$val1"));

                                $min = $val1;
                                $cnt++;
                            }

                            $caseop .= " end)";

                            $grouporder_clause = " GROUP BY rng ORDER BY $select_field"; 
                            $select_field = $caseop; 
                        }
                    }

                }

                $select_clause = "SELECT $select_field as rng, count(*) as cnt ";
                if($grouporder_clause==""){
                    $grouporder_clause = " GROUP BY $select_field ORDER BY $select_field"; 
                }
            }

            /*                  
            // in case first field and second title
            $where_clause2 = ", recDetails TOPDET "
            ." LEFT JOIN Records linked0 ON (TOPDET.dtl_Value=linked0.rec_ID and linked0.rec_RecTypeID in (".$resource_rt0.")) "
            ." LEFT JOIN recDetails linkeddt0 ON (linkeddt0.dtl_RecID=linked0.rec_ID and linkeddt0.dtl_DetailTypeID=".$resource_field0.")"
            ." LEFT JOIN Records linked1 ON (linkeddt0.dtl_Value=linked1.rec_ID and linked1.rec_RecTypeID in (".$resource_rt1.")) "
            ." WHERE TOPDET.dtl_RecID=TOPBIBLIO.rec_ID and TOPDET.dtl_DetailTypeID=".$fieldid." and ";

            // in case 2 levels
            $where_clause2 = ", recDetails TOPDET "
            ." LEFT JOIN Records linked0 ON (TOPDET.dtl_Value=linked0.rec_ID and linked0.rec_RecTypeID in (".$resource_rt0.")) "
            ." LEFT JOIN recDetails linkeddt0 ON (linkeddt0.dtl_RecID=linked0.rec_ID and linkeddt0.dtl_DetailTypeID=".$resource_field0.")"
            ." LEFT JOIN Records linked1 ON (linkeddt0.dtl_Value=linked1.rec_ID and linked1.rec_RecTypeID in (".$resource_rt1.")) "
            ." LEFT JOIN recDetails linkeddt1 ON (linkeddt1.dtl_RecID=linked1.rec_ID and linkeddt1.dtl_DetailTypeID=".$resource_field1.")"
            ." WHERE TOPDET.dtl_RecID=TOPBIBLIO.rec_ID and TOPDET.dtl_DetailTypeID=".$fieldid." and linkeddt1.dtl_Value is not null and ";

            // in case relaionship and second detail (remove dt in case title)   
            $where_clause2 = ", recRelationshipsCache rel0 "
            ." LEFT JOIN Records linked0 ON (linked0.rec_RecTypeID in (".$resource_rt0.")) "
            ." LEFT JOIN recDetails linkeddt0 ON (linkeddt0.dtl_RecID=linked0.rec_ID and linkeddt0.dtl_DetailTypeID=".$resource_field0.")"
            ." LEFT JOIN Records linked1 ON (linkeddt0.dtl_Value=linked1.rec_ID and linked1.rec_RecTypeID in (".$resource_rt1.")) "
            ." LEFT JOIN recDetails linkeddt1 ON (linkeddt1.dtl_RecID=linked1.rec_ID and linkeddt1.dtl_DetailTypeID=".$resource_field1.")"
            ." WHERE ((rel0.rrc_TargetRecID=TOPBIBLIO.rec_ID and rel0.rrc_SourceRecID=linked0.rec_ID) "
            ."     or (rel0.rrc_SourceRecID=TOPBIBLIO.rec_ID and rel0.rrc_TargetRecID=linked0.rec_ID)) and linkeddt1.dtl_Value is not null and ";

            // in case 2 relaionships
            $where_clause2 = ", recRelationshipsCache rel0, recRelationshipsCache rel1 "
            ." LEFT JOIN Records linked0 ON (linked0.rec_RecTypeID in (".$resource_rt0.")) "
            ." LEFT JOIN Records linked1 ON (linked1.rec_RecTypeID in (".$resource_rt1.")) "
            ." WHERE ((rel0.rrc_TargetRecID=TOPBIBLIO.rec_ID and rel0.rrc_SourceRecID=linked0.rec_ID) "
            ."     or (rel0.rrc_SourceRecID=TOPBIBLIO.rec_ID and rel0.rrc_TargetRecID=linked0.rec_ID)) "
            ."    and ((rel1.rrc_TargetRecID=rel0.rrc_SourceRecID and rel1.rrc_SourceRecID=linked1.rec_ID)"
            ."     or (rel1.rrc_SourceRecID=rel0.rrc_TargetRecID and rel1.rrc_TargetRecID=linked1.rec_ID)) and ";

            // in pointer and relaionships
            $where_clause2 = ", recDetails TOPDET, recRelationshipsCache rel1 "
            ." LEFT JOIN Records linked0 ON (TOPDET.dtl_Value=linked0.rec_ID and linked0.rec_RecTypeID in (".$resource_rt0.")) "

            ." LEFT JOIN Records linked1 ON (linked1.rec_RecTypeID in (".$resource_rt1.")) "
            ." LEFT JOIN recDetails linkeddt1 ON (linkeddt1.dtl_RecID=linked1.rec_ID and linkeddt1.dtl_DetailTypeID=".$resource_field1.")"
            ." WHERE TOPDET.dtl_RecID=TOPBIBLIO.rec_ID and TOPDET.dtl_DetailTypeID=".$fieldid." and "
            ."    and ((rel1.rrc_TargetRecID=linked0.rec_ID and rel1.rrc_SourceRecID=linked1.rec_ID)"
            ."     or (rel1.rrc_SourceRecID=linked0.rec_ID and rel1.rrc_TargetRecID=linked1.rec_ID)) and ";
            */                       


            /*    OLD VERSION    
            //nested resource pointer
            $resource = @$params['resource'];  // f:15(t:20 f:41)  f:15(t:20 f:41(t:10 title))
            $resource_field = null;
            if($resource){

            $vr = explode(" ", $resource);
            $resource_rt = substr($vr[0],2);
            $resource_field = $vr[1];
            if(strpos($resource_field,"f:")===0){
            $resource_field = substr($resource_field,2);
            }
            }

            if($dt_type=="freetext"){
            //find count by first letter
            if($fieldid=='recTitle' || $resource_field=='title'){

            if($resource_rt){   //second level

            $select_clause = "SELECT SUBSTRING(trim(linked.rec_Title), 1, 1) as alpha, count(*) as cnt ";
            $where_clause = ", recDetails TOPDET "
            ." LEFT JOIN Records linked ON (TOPDET.dtl_Value=linked.rec_ID and linked.rec_RecTypeID in (".$resource_rt.")) " //(STRCMP(TOPDET.dtl_Value, linked.rec_ID)=0
            ." WHERE TOPDET.dtl_RecID=TOPBIBLIO.rec_ID and TOPDET.dtl_DetailTypeID=".$fieldid." and ";
            $grouporder_clause = " GROUP BY SUBSTRING(trim(linked.rec_Title), 1, 1) ORDER BY SUBSTRING(trim(linked.rec_Title), 1, 1)";

            }else{

            $select_clause = "SELECT SUBSTRING(trim(rec_Title), 1, 1) as alpha, count(*) as cnt ";
            $where_clause = " WHERE ";
            $grouporder_clause = " GROUP BY SUBSTRING(trim(rec_Title), 1, 1) ORDER BY SUBSTRING(trim(rec_Title), 1, 1)";

            }

            }else{
            if($resource_rt){

            $select_clause = "SELECT SUBSTRING(trim(linkeddt.dtl_Value), 1, 1) as alpha, count(*) as cnt ";
            $where_clause = ", recDetails TOPDET LEFT JOIN Records linked ON (TOPDET.dtl_Value=linked.rec_ID and linked.rec_RecTypeID in (".$resource_rt.")) " // STRCMP(TOPDET.dtl_Value, linked.rec_ID)=0
            ." LEFT JOIN recDetails linkeddt ON (linkeddt.dtl_RecID=linked.rec_ID and linkeddt.dtl_DetailTypeID=".$resource_field.")"
            ." WHERE TOPDET.dtl_RecID=TOPBIBLIO.rec_ID and TOPDET.dtl_DetailTypeID=".$fieldid." and linkeddt.dtl_Value is not null and ";
            $grouporder_clause = " GROUP BY SUBSTRING(trim(linkeddt.dtl_Value), 1, 1) ORDER BY SUBSTRING(trim(linkeddt.dtl_Value), 1, 1)";


            }else{
            $select_clause = "SELECT SUBSTRING(trim(dtl_Value), 1, 1) as alpha, count(*) ";
            $where_clause = ", recDetails TOPDET "
            ." WHERE TOPDET.dtl_RecId=TOPBIBLIO.rec_ID and TOPDET.dtl_DetailTypeId=".$fieldid." and ";

            $grouporder_clause = " GROUP BY SUBSTRING(trim(dtl_Value), 1, 1) ORDER BY SUBSTRING(trim(dtl_Value), 1, 1)";    
            }
            }

            }else if($dt_type=="rectype"){    

            $select_clause = "SELECT rec_RecTypeID as alpha, count(*) as cnt ";
            $where_clause = " WHERE ";
            $grouporder_clause = " GROUP BY rec_RecTypeID ORDER BY rec_RecTypeID";

            }else if($dt_type=="enum" || $dt_type=="relationtype"){    

            if($resource_rt){

            $select_clause = "SELECT linkeddt.dtl_Value as termid, count(*) as cnt ";
            $where_clause = ", recDetails TOPDET LEFT JOIN Records linked ON (TOPDET.dtl_Value=linked.rec_ID and linked.rec_RecTypeID in (".$resource_rt.")) " //STRCMP(TOPDET.dtl_Value, linked.rec_ID)=0
            ." LEFT JOIN recDetails linkeddt ON (linkeddt.dtl_RecID=linked.rec_ID and linkeddt.dtl_DetailTypeID=".$resource_field.")"
            ." WHERE TOPDET.dtl_RecID=TOPBIBLIO.rec_ID and TOPDET.dtl_DetailTypeID=".$fieldid." and linkeddt.dtl_Value is not null and ";
            $grouporder_clause = " GROUP BY linkeddt.dtl_Value ORDER BY linkeddt.dtl_Value";

            }else{
            $select_clause = "SELECT dtl_Value as termid, count(*) ";
            $where_clause = ", recDetails WHERE dtl_RecId=rec_ID and dtl_DetailTypeId=".$fieldid." and ";
            $grouporder_clause = " GROUP BY dtl_Value ORDER BY dtl_Value";    
            }
            }else{
            return array("status"=>HEURIST_OK, "data"=> array());
            }        
            */       


            //count query
            $query =  $select_clause.$qclauses["from"].$where_clause.$qclauses["where"].$grouporder_clause;

            //error_log("COUNT >>>".$query);

            $res = $mysqli->query($query);
            if (!$res){
                $response = $system->addError(HEURIST_DB_ERROR, "Count query error", $mysqli->error);
            }else{
                $data = array();

                while ( $row = $res->fetch_row() ) {
                    if(count($ranges)>0 && @$ranges[$row[0]]){
                        array_push($data, array($ranges[$row[0]]["label"], $row[1], $ranges[$row[0]]["query"]));
                    }else{
                        array_push($data, array($row[0], $row[1], ($step_level<2)?$row[0]."%":$row[0]) );
                        //array_push($data, $row);
                    }
                }
                $response = array("status"=>HEURIST_OK, "data"=> $data, "facet_index"=>@$params['facet_index'], "type"=>@$params['type'], "q"=>@$params['q'], "dt"=>@$params['dt']);
                $res->close();
            }

        }else{
            $response = $system->addError(HEURIST_INVALID_REQUEST);
        }

        return $response;
    }

    /**
    * Find all related record IDs for given set record IDs
    * 
    * @param mixed $system
    * @param mixed $ids
    */
    function recordSearchRealted($system, $ids){
        
        if(!@$ids){
            return $system->addError(HEURIST_INVALID_REQUEST, "Invalid search request");
        }
        //$ids = explode(",", $ids);
        
        $direct = array();
        $reverse = array();
        
        $mysqli = $system->get_mysqli();
        
        //find all target related records
        $query = 'SELECT rl_SourceID, rl_TargetID, rl_RelationTypeID, rl_DetailTypeID FROM recLinks '
            .'where rl_SourceID in ('.$ids.') order by rl_SourceID';
       
//error_log("1>>>".$query);
        
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

//error_log("2>>>".$query);
        
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
                     
//error_log(print_r($response, true));

        return $response;                     
        
    }
    
    

    /**
    * put your comment there...
    *
    * @param mixed $system
    * @param mixed $params
    * @param mixed $need_structure
    * @param mixed $need_details
    */
    function recordSearch($system, $params, $need_structure, $need_details)
    {
        $mysqli = $system->get_mysqli();
        $currentUser = $system->getCurrentUser();
        
        if ( $system->get_user_id()<1 ) {
            //$currentUser['ugr_Groups'] = user_getWorkgroups( $mysqli, $currentUser['ugr_ID'] );
            $params['w'] = 'all'; //does not allow to search bookmarks if not logged in
        }

        if(@$params['q'] && !is_string($params['q'])){
            return $system->addError(HEURIST_INVALID_REQUEST, "Invalid search request");
        }


        $select_clause = 'select SQL_CALC_FOUND_ROWS '   //this function does not pay attention on LIMIT - it returns total number of rows
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
        
        if(@$params['tq']){        
            // if params has "TQ" parameter this is search for linked/related records
            // tsort, tlimit and toffset are parameters for top(parent) query
            // besides to simplify query, instead of these 4 parameters we may have "TIDS" - comma separated list of parent IDS
            
            //1. define query parts for top/parent query
            $params_top = $params;
            $params_top['q'] = @$params['tq'];
            $params_top['s'] = @$params['ts']; //sortby
            $params_top['l'] = @$params['tl']; //limit
            $params_top['o'] = @$params['to']; //offset

            $query_top = get_sql_query_clauses($mysqli, $params_top, $currentUser);
            
            //2. define current query - set one of paremters as a reference to the parent query
            
            $params['parentquery'] = $query_top;

//error_log("parent query ".print_r($query_top, true));
        
        }else if( @$params['topids'] ){ //if topids are defined we use them as starting point for following rule query
         
            $query_top = array();
            
            //@todo - move all this stuff into get_sql_query_clauses???
            if($currentUser && @$currentUser['ugr_ID']>0){
                $currUserID = $currentUser['ugr_ID'];
            }else{
                $currUserID = 0;
                $params['w'] = 'all';
            }
            
            if (strcasecmp(@$params['w'],'B') == 0  ||  strcasecmp(@$params['w'], 'bookmark') == 0) {
                $query_top['from'] = 'FROM usrBookmarks TOPBKMK LEFT JOIN Records TOPBIBLIO ON bkm_recID=rec_ID ';
            }else{
                $query_top['from'] = 'FROM Records TOPBIBLIO LEFT JOIN usrBookmarks TOPBKMK ON bkm_recID=rec_ID and bkm_UGrpID='.$currUserID.' ';
            }
            $query_top['where'] = "(TOPBIBLIO.rec_ID in (".$params['topids']."))";
            $query_top['sort'] =  '';
            $query_top['limit'] =  '';
            $query_top['offset'] =  '';
            
            $params['parentquery'] = $query_top;
        }
        
        $aquery = get_sql_query_clauses($mysqli, $params, $currentUser);   //!!!! IMPORTANT CALL   OR compose_sql_query at once
        
//error_log("query ".print_r($aquery, true));        
        
        $chunk_size = 1001;
        $query =  $select_clause.$aquery["from"]." WHERE ".$aquery["where"].$aquery["sort"].$aquery["limit"].$aquery["offset"];

        //DEGUG 
//error_log("AAA ".$query);

        $res = $mysqli->query($query);
        if (!$res){
            $response = $system->addError(HEURIST_DB_ERROR, "Search query error", $mysqli->error);
        }else{

            $fres = $mysqli->query('select found_rows()');
            if (!$fres)     {
                $response = $system->addError(HEURIST_DB_ERROR, "Search query error", $mysqli->error);
            }else{

                $total_count_rows = $fres->fetch_row();
                $total_count_rows = $total_count_rows[0];
                $fres->close();

                // read all field names
                $_flds =  $res->fetch_fields();
                $fields = array();
                foreach($_flds as $fld){
                    array_push($fields, $fld->name);
                }
                array_push($fields, 'rec_ThumbnailURL'); //last one
                
                $rectype_structures  = array();
                $rectypes = array();
                $records = array();
                $order = array();
                    
                    // load all records
                    while ( ($row = $res->fetch_row()) && (count($records)<$chunk_size) ) {  //1000 maxim allowed chunk
                        array_push( $row, fileGetThumbnailURL($system, $row[2]) );
                        $records[$row[2]] = $row;
                        array_push($order, $row[2]);
                        if(!array_key_exists($row[4], $rectypes)){
                            $rectypes[$row[4]] = 1;
                        }
                    }
                    $res->close();
                    
                    
                    $rectypes = array_keys($rectypes);
                    //$rectypes = array_unique($rectypes);  it does not suit - since it returns array with original keys and on client side it is treaten as object

                    if($need_details && count($records)>0){
                        
                        //search for specific details
                        // @todo - we may use getAllRecordDetails
                        $res_det = $mysqli->query(
                            "select dtl_RecID,
                            dtl_DetailTypeID,
                            dtl_Value,
                            astext(dtl_Geo),
                            dtl_UploadedFileID,
                            recUploadedFiles.ulf_ObfuscatedFileID,
                            recUploadedFiles.ulf_Parameters
                            from recDetails 
                            left join recUploadedFiles on ulf_ID = dtl_UploadedFileID   
                            where dtl_RecID in (" . join(",", array_keys($records)) . ")");
                        if (!$res_det){
                            $response = $system->addError(HEURIST_DB_ERROR, "Search query error", $mysqli->error);
                            return $response;
                        }else{
                            while ($row = $res_det->fetch_row()) {
                                $recID = array_shift($row);
                                if( !array_key_exists("d", $records[$recID]) ){
                                    $records[$recID]["d"] = array();
                                }
                                $dtyID = $row[0];
                                if( !array_key_exists($dtyID, $records[$recID]["d"]) ){
                                    $records[$recID]["d"][$dtyID] = array();
                                }

                                if($row[2]){
                                    $val = $row[1]." ".$row[2]; //for geo
                                }else if($row[3]){
                                    $val = array($row[4], $row[5]); //obfuscted value for fileid
                                }else { 
                                    $val = $row[1];
                                }
                                array_push($records[$recID]["d"][$dtyID], $val);
                            }
                            $res_det->close();

                        }
                    }
                    if($need_structure && count($rectypes)>0){ //rarely used
                          //description of recordtype and used detail types
                          $rectype_structures = dbs_GetRectypeStructures($system, $rectypes, 1); //no groups
                    }

                    //"query"=>$query,
                    $response = array("status"=>HEURIST_OK,
                        "data"=> array(
                            //"query"=>$query,
                            "queryid"=>@$params['id'],  //query unqiue id
                            "count"=>$total_count_rows,
                            "offset"=>get_offset($params),
                            "fields"=>$fields,
                            "records"=>$records,
                            "order"=>$order,
                            "rectypes"=>$rectypes,
                            "structures"=>$rectype_structures));

                            
                
            
            }

        }

        return $response;

    }
?>
