<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* crosstabsController.php - Server-side controller for Crosstabs viewer.
*
* @fileOverview This file handles server-side logic for the crosstab viewer.
* It processes requests from the client-side JavaScript to fetch data needed for
* generating crosstabulations, such as min/max values for fields, distinct pointer
* values, the main crosstab data, and record type information for a given set of records.
*
* @project     Heurist academic knowledge management system
* @package  Viewers\Crosstab
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       3.1.0
*/
require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../../hserv/records/search/recordSearch.php';

$system = new hserv\System();
if( !$system->init(@$_REQUEST['db']) ){
    $response = $system->getError();
}else{

    $mysqli = $system->getMysqli();
    $params = $_REQUEST;

    if(@$_REQUEST['a'] == 'minmax' ){

            $response = recordSearchMinMax( $system, $params );//recordSearch.php

    }elseif(@$_REQUEST['a'] == 'pointers' ){

            $response = recordSearchDistinctPointers( $params );

    }elseif(@$_REQUEST['a'] == 'crosstab' ){

ini_set('max_execution_time', '0');

            $response = getCrossTab( $params );

    }elseif(@$_REQUEST['a'] == 'getRecTypes'){
        $response = getRecTypesCrosstabs($params);
    }else{
            $response = array("status"=>HEURIST_INVALID_REQUEST, 'No proper action defined');
    }
}

header(CTYPE_JSON);
print json_encode($response);
exit;


/**
* find min amd max value for given detail type
*
* @param mixed $mysqli
* @param mixed $params : dt - detail type id
*/
/*
function recordSearchMinMax( $params){
    global $system;

    $mysqli = $system->getMysqli();

    if(@$params['dt']){

// no more rectype filter
//        $query = "select min(cast(dtl_Value as decimal)) as min, max(cast(dtl_Value as decimal)) as max from Records, recDetails where rec_ID=dtl_RecID and rec_RecTypeID="
//                .$params['rt']." and dtl_DetailTypeID=".$params['dt'];


        $query = "select min(cast(dtl_Value as decimal)) as min, max(cast(dtl_Value as decimal)) as max from recDetails where dtl_DetailTypeID=".$params['dt'];

        //@todo - current user constraints

        $res = $mysqli->query($query);
        if (!$res){
            $response = $system->addError(HEURIST_DB_ERROR, 'Search query error on min/max for crosstabs', $mysqli->error);
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
        $response = array("status"=>HEURIST_INVALID_REQUEST);
    }

   return $response;
}
*/
/**
 * Extracts and prepares record IDs from the request parameters.
 * Used to filter queries based on a specific recordset.
 *
 * @param array $params Request parameters, potentially including a 'recordset' key.
 *                      The 'recordset' can be an array or a JSON string containing 'recIDs'.
 * @return array|null An array of prepared record IDs, or null if not provided.
 */
function getWhereRecordIds($params){

    $recIDs = null;

    if(@$params['recordset']){
        if(is_array($params['recordset'])){
            $recids = $params['recordset'];
        }else{
            $recids = json_decode($params['recordset'], true);
        }
        //$recIDs = explode(',',$recids['recIDs']);
        $recIDs = prepareIds($recids['recIDs']);


    }
    return $recIDs;
}


/**
 * Finds the list of distinct record IDs and their titles for a given "record pointer" detail type.
 * Results are filtered by the current user's permissions and any provided recordset or query.
 *
 * @global hserv\System $system The global Heurist system object.
 * @global mysqli $mysqli The global mysqli database connection object.
 * @param array $params Request parameters. Expected keys:
 *                      'dt' (int): The detail type ID for the record pointer field.
 *                      'recordset' (array|string, optional): Recordset to filter by.
 *                      'q' (string, optional): Query string to filter by if recordset not provided.
 *                      'w' (string, optional): Domain for the query string.
 * @return array A response array with 'status' and 'data' (list of {id, text} objects) or error details.
 */
function recordSearchDistinctPointers( $params ){
    global $system, $mysqli;

    if(@$params['dt']){

    $where = getWhereRecordIds($params);

    if($where==null){

        $currentUser = $system->getCurrentUser();

        $query = get_sql_query_clauses($mysqli, $params, $currentUser);
        $where_clause = $query["where"];

        /*remove order by
        $pos = strrpos($where, " order by ");
        if($pos){
            $where = substr($where,0,$pos);
        }*/
        $where = '(select rec_ID FROM Records TOPBIBLIO WHERE '.$where_clause.' )';
    }else{

        $where = '('.implode(',',$where).')';
    }

    $query = "select distinct dtl_Value as id, rec_Title as text from Records, recDetails where rec_ID=dtl_Value and dtl_DetailTypeID="
                        .intval($params['dt'])." and dtl_RecID in ".$where;

        $res = $mysqli->query($query);
        if (!$res){
            $response = $system->addError(HEURIST_DB_ERROR, "Search query error on crosstabs distinct pointers", $mysqli->error);
        }else{


            $outp = array();
            while ($row = $res->fetch_assoc()) {
                array_push($outp, $row);
            }
            $response = array("status"=>HEURIST_OK, "data"=> $outp);
            $res->close();
        }

    }else{
        $response = array("status"=>HEURIST_INVALID_REQUEST);
    }

   return $response;
}

/**
 * Generates the main crosstabulation data based on request parameters.
 * Constructs and executes a complex SQL query to aggregate data according
 * to specified row, column, and page dimensions, and aggregation mode.
 *
 * @global hserv\System $system The global Heurist system object.
 * @global mysqli $mysqli The global mysqli database connection object.
 * @param array $params Request parameters. Expected keys:
 *                      'dt_row' (int): Detail type ID for rows.
 *                      'dt_rowtype' (string): Data type of the row field (e.g., "integer", "float", "enum").
 *                      'dt_col' (int, optional): Detail type ID for columns.
 *                      'dt_coltype' (string, optional): Data type of the column field.
 *                      'dt_page' (int, optional): Detail type ID for pages.
 *                      'dt_pagetype' (string, optional): Data type of the page field.
 *                      'agg_mode' (string, optional): Aggregation mode ("count", "sum", "avg"). Defaults to "count".
 *                      'agg_field' (int, optional): Detail type ID for the field to aggregate if mode is "sum" or "avg".
 *                      'recordset' (array|string, optional): Recordset to filter by.
 *                      'q' (string, optional): Query string to filter by if recordset not provided.
 *                      'w' (string, optional): Domain for the query string.
 * @return array A response array with 'status' and 'data' (the crosstab resultset) or error details.
 */
function getCrossTab( $params){

    global $system;

    $mysqli = $system->getMysqli();

    $dt_page = @$params['dt_page'];
    if($dt_page){
        $pagefld = ", d4.dtl_Value as page";
    }else{
        $pagefld = "";
    }
    $dt_col = @$params['dt_col'];
    if($dt_col){
        $columnfld = "d1.dtl_Value as cls, ";
    }else{
        $columnfld = "0, ";
    }

    $mode = filter_var(@$params['agg_mode'], FILTER_SANITIZE_STRING);
    $issum = (($mode=="avg" || $mode=="sum") && intval(@$params['agg_field'])>0);

    if ($issum){
        $mode = ($mode=='avg'?'avg':'sum').'(cast(d3.dtl_Value as decimal(20,2)))';//.$params['agg_field'].")";
    }else{
        $mode = "count(*)";
    }

    $recIDs = getWhereRecordIds($params);
    if($recIDs!=null){
        $params['q'] = 'ids:'.implode(',',$recIDs);
    }

    $currentUser = $system->getCurrentUser();

    $query = get_sql_query_clauses($mysqli, $params, $currentUser);
    $where = $query["where"];
    $from = $query["from"];


    /*remove order by
    $pos = strrpos($where, " order by ");
    if($pos){
        $where = substr($where,0,$pos);
    }*/

$query = "select d2.dtl_Value as rws, ".$columnfld.$mode." as cnt ".$pagefld." ".$from;

$query = $query." left join recDetails d2 on d2.dtl_RecID=TOPBIBLIO.rec_ID and d2.dtl_DetailTypeID=".intval($params['dt_row']);
if($dt_col>0){
    $query = $query." left join recDetails d1 on d1.dtl_RecID=TOPBIBLIO.rec_ID and d1.dtl_DetailTypeID=".intval($dt_col);
}
if($dt_page>0){
    $query = $query." left join recDetails d4 on d4.dtl_RecID=TOPBIBLIO.rec_ID and d4.dtl_DetailTypeID=".intval($dt_page);
}
if($issum){
    $query = $query
     ." ,recDetails d3 "
    //20130517 ." where rec_RectypeID=".$params['rt']
    ." where d3.dtl_RecID=TOPBIBLIO.rec_ID and d3.dtl_Value is not null && d3.dtl_DetailTypeID=".intval($params['agg_field'])
    .SQL_AND.$where;

}else{
    $query = $query.SQL_WHERE.$where; //20130517 rec_RectypeID=".$params['rt'];
}
//20130517 $query = $query.SQL_AND.$where_2;

$query = $query." group by d2.dtl_Value ";

if($dt_col){
    $query = $query.", d1.dtl_Value";
}
if($dt_page){
    $query = $query.", d4.dtl_Value ";
}

$query = $query." order by ";

if($dt_page){
    if($params['dt_pagetype']=="integer" || $params['dt_pagetype']=="float"){
        $query = $query." cast(d4.dtl_Value as decimal(20,2)), ";
    }else{
        $query = $query." d4.dtl_Value, ";
    }
}

if($params['dt_rowtype']=="integer" || $params['dt_rowtype']=="float"){
    $query = $query." cast(d2.dtl_Value as decimal(20,2)) ";
}else{
    $query = $query." d2.dtl_Value ";
}

if($dt_col){
    if($params['dt_coltype']=="integer" || $params['dt_coltype']=="float"){
        $query = $query.", cast(d1.dtl_Value as decimal(20,2))";
    }else{
        $query = $query.", d1.dtl_Value";
    }
}

        $res = $mysqli->query($query);
        if (!$res){
            $response = $system->addError(HEURIST_DB_ERROR, "Search query error on crosstabs", $mysqli->error);
        }else{

            $outp = array();
            while ($row = $res->fetch_row()) {
                array_push($outp, $row);
            }
            $response = array("status"=>HEURIST_OK, "data"=> $outp);
            $res->close();
        }

return $response;

}

/**
 * Retrieves the record type IDs present in a given list of record IDs.
 * If a single record ID is provided, its record type is returned.
 * If multiple record IDs are provided, it returns a list of unique record type IDs
 * found among those records, ordered by frequency (most common first).
 *
 * @global hserv\System $system The global Heurist system object.
 * @global mysqli $mysqli The global mysqli database connection object.
 * @param array $params Request parameters. Expected key:
 *                      'recIDs' (array|string): A single record ID or an array/comma-separated string of record IDs.
 * @return array A response array with 'status' and 'data' (an array of record type IDs) or error details.
 */
function getRecTypesCrosstabs($params){

    global $system;

    $mysqli = $system->getMysqli();

    $recIDs = prepareIds($params['recIDs']);

    $response = ['status' => HEURIST_OK, 'data' => []];

    if(count($recIDs) == 1){
        $response['data'] = [mysql__select_value($mysqli, "SELECT rec_RecTypeID FROM Records WHERE rec_ID = ?", ['i', $recIDs[0]])];
    }elseif(!empty($recIDs)){
        $recIDs = implode(',', $recIDs);
        $response['data'] = mysql__select_list2($mysqli, "SELECT rec_RecTypeID, COUNT(rec_RecTypeID) AS rty_Count FROM Records WHERE rec_ID IN ({$recIDs}) GROUP BY rec_RecTypeID ORDER BY rty_Count DESC", 'intval');
    }

    return $response;
}
?>