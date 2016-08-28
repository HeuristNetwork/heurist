<?php

/*
* Copyright (C) 2005-2016 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
*   Corsstabs server side DB requests
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../search/parseQueryToSQL.php');

$mysqli = mysqli_connection_overwrite("hdb_".@$_REQUEST['db']);

$params = $_REQUEST;

if(@$_REQUEST['a'] == 'minmax' ){

        $response = recordSearchMinMax($mysqli, $params);

}else if(@$_REQUEST['a'] == 'pointers' ){

        $response = recordSearchDistictPointers($mysqli, $params);

}else if(@$_REQUEST['a'] == 'crosstab' ){

        $response = getCrossTab($mysqli, $params);

}else{
        $response = array("status"=>"INVALID REQUEST");
}

header('Content-type: text/javascript');
print json_encode($response);
exit();

/**
* find min amd max value for given detail type
*
* @param mixed $mysqli
* @param mixed $params : dt - detail type id
*/
function recordSearchMinMax($mysqli, $params){

    if(@$params['dt']){

// no more rectype filter
//        $query = "select min(cast(dtl_Value as decimal)) as min, max(cast(dtl_Value as decimal)) as max from Records, recDetails where rec_ID=dtl_RecID and rec_RecTypeID="
//                .$params['rt']." and dtl_DetailTypeID=".$params['dt'];


        $query = "select min(cast(dtl_Value as decimal)) as min, max(cast(dtl_Value as decimal)) as max from recDetails where dtl_DetailTypeID=".$params['dt'];

        //@todo - current user constraints

        $res = $mysqli->query($query);
        if (!$res){
            $response = array("status"=>"INVALID REQUEST", "message"=>$mysqli->error);
            //$response = $system->addError(HEURIST_DB_ERROR, "Search query error", $mysqli->error);
        }else{
            $row = $res->fetch_assoc();
            if($row){
                $response = array("status"=>"OK", "data"=> $row);
            }else{
                $response = array("status"=>"NOT FOUND");
            }
            $res->close();
        }

    }else{
        $response = array("status"=>"INVALID REQUEST");
    }

   return $response;
}

function getWhereRecordIds($params){

    $recIDs = null;
    
    if(@$params['recordset']){
        if(is_array($params['recordset'])){
            $recids = $params['recordset'];  
        }else{
            $recids = json_decode($params['recordset'], true);    
        }
        //$recIDs = explode(',',$recids['recIDs']);
        $recIDs = $recids['recIDs'];
    }
    return $recIDs;
}


/**
* finds the list of distict record IDs for given detail type "record pointer"
*
* @param mixed $mysqli
* @param mixed $params:  dt
*/
function recordSearchDistictPointers($mysqli, $params){

    if(@$params['dt']){

    $where = getWhereRecordIds($params);
        
    if($where==null){
        if (function_exists('get_user_id')) {
            $wg_ids = mysql__select_array(USERS_DATABASE.'.sysUsrGrpLinks left join '.USERS_DATABASE.'.sysUGrps grp on grp.ugr_ID=ugl_GroupID', 'ugl_GroupID',
                                          'ugl_UserID='.get_user_id().' and grp.ugr_Type != "User" order by ugl_GroupID');
        }else{
            $wg_ids = null;
        }

        $search_type = (@$params['w']=="bookmark" || @$params['w']=="b")?$params['w']:"all";
        
        $where = parse_query($search_type, @$params['q'], null, $wg_ids, false);
        
        //remove order by
        $pos = strrpos($where, " order by ");
        if($pos){
            $where = substr($where,0,$pos);
        }
        $where = '(select rec_ID '.$where.' )';
    }else{
        $where = '('.$where.')';
    }
    
    $query = "select distinct dtl_Value as id, rec_Title as text from Records, recDetails where rec_ID=dtl_Value and dtl_DetailTypeID="
                        .$params['dt']." and dtl_RecID in ".$where;
        
//DEBUG error_log($query);        
        
        $res = $mysqli->query($query);
        if (!$res){
            $response = array("status"=>"INVALID REQUEST", "message"=>$mysqli->error);
            //$response = $system->addError(HEURIST_DB_ERROR, "Search query error", $mysqli->error);
        }else{


            $outp = array();
            while ($row = $res->fetch_assoc()) {
                array_push($outp, $row);
            }
            $response = array("status"=>"OK", "data"=> $outp);
            $res->close();
        }

    }else{
        $response = array("status"=>"INVALID REQUEST");
    }

   return $response;
}

/**
* main request to find crosstab data
* 
* @param mixed $mysqli
* @param mixed $params
*               dt_page - detail type for page/groups
*               dt_col - detail type for columns
*               dt_row - detail type for rows
*               agg_mode - aggreagation mode: sum, avg, count   
*               agg_field - field for avg or sum mode
*               q - current Heurist query
*/
function getCrossTab($mysqli, $params){

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

    $mode = @$params['agg_mode'];
    $issum = (($mode=="avg" || $mode=="sum") && @$params['agg_field']);

    if ($issum){
        $mode = $mode."(cast(d3.dtl_Value as decimal(20,2)))";  //.$params['agg_field'].")";
    }else{
        $mode = "count(*)";
    }


    
    if (function_exists('get_user_id')) {
        $wg_ids = mysql__select_array(USERS_DATABASE.'.sysUsrGrpLinks left join '.USERS_DATABASE.'.sysUGrps grp on grp.ugr_ID=ugl_GroupID', 'ugl_GroupID',
                                      'ugl_UserID='.get_user_id().' and grp.ugr_Type != "User" order by ugl_GroupID');
    }else{
        $wg_ids = null;
    }

    $search_type = (@$params['w']=="bookmark" || @$params['w']=="b")?$params['w']:"all";

    $where = getWhereRecordIds($params);
    if($where==null){
        $where = parse_query($search_type, @$params['q'], null, $wg_ids, false);
    }else{    
        $where = parse_query($search_type, 'ids:'.$where, null, $wg_ids, false);
    }

    //remove order by
    $pos = strrpos($where, " order by ");
    if($pos){
        $where = substr($where,0,$pos);
    }
    //insert our where clauses
    $pos = strpos($where, " where ");
    $where_1 = substr($where,0,$pos);
    $where_2 = substr($where,$pos+7);
    

$query = "select d2.dtl_Value as rws, ".$columnfld.$mode." as cnt ".$pagefld." ".$where_1;

$query = $query." left join recDetails d2 on d2.dtl_RecID=rec_ID and d2.dtl_DetailTypeID=".$params['dt_row'];
if($dt_col){
    $query = $query." left join recDetails d1 on d1.dtl_RecID=rec_ID and d1.dtl_DetailTypeID=".$dt_col;
}
if($dt_page){
    $query = $query." left join recDetails d4 on d4.dtl_RecID=rec_ID and d4.dtl_DetailTypeID=".$dt_page;
}
if($issum){
    $query = $query
     ." ,recDetails d3 "
    //20130517 ." where rec_RectypeID=".$params['rt']
    ." where d3.dtl_RecID=rec_ID and d3.dtl_Value is not null && d3.dtl_DetailTypeID=".$params['agg_field']
    ." and ".$where_2;

}else{
    $query = $query." where ".$where_2; //20130517 rec_RectypeID=".$params['rt'];
}
//20130517 $query = $query." and ".$where_2;

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

//error_log($query);

        $res = $mysqli->query($query);
        if (!$res){
            $response = array("status"=>"INVALID REQUEST", "message"=>$mysqli->error);
            //$response = $system->addError(HEURIST_DB_ERROR, "Search query error", $mysqli->error);
        }else{

            $outp = array();
            while ($row = $res->fetch_row()) {
                array_push($outp, $row);
            }
            $response = array("status"=>"OK", "data"=> $outp);
            $res->close();
        }

return $response;

}
?>