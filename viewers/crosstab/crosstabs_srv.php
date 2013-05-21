<?php

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../search/parseQueryToSQL.php');

$mysqli = mysqli_connection_overwrite("hdb_".@$_REQUEST['db']);

$params = $_REQUEST;

if(@$_REQUEST['a'] == 'minmax' ){

        $response = recordSearchMinMax($mysqli, $params);

}else if(@$_REQUEST['a'] == 'crosstab' ){

        $response = getCrossTab($mysqli, $params);

}else{
        $response = array("status"=>"INVALID REQUEST");
}

header('Content-type: text/javascript');
print json_encode($response);
exit();

function recordSearchMinMax($mysqli, $params){

    if(@$params['rt'] && @$params['dt']){

        $query = "select min(cast(dtl_Value as decimal)) as min, max(cast(dtl_Value as decimal)) as max from Records, recDetails where rec_ID=dtl_RecID and rec_RecTypeID="
                .$params['rt']." and dtl_DetailTypeID=".$params['dt'];

        //@todo - current user constraints

//error_log(">>>>".@$_REQUEST['db']."<<<<<<".  $query);

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

    $search_type = (@$parms['w']=="bookmark" || @$parms['w']=="b")?$parms['w']:"all";

    $where = parse_query($search_type, @$params['q'], null, $wg_ids, false);

    //remove order by
    $pos = strrpos($where, " order by ");
    if($pos){
        $where = substr($where,0,$pos);
    }
    //insert our where clauses
    $pos = strpos($where, " where ");
    $where_1 = substr($where,0,$pos);
    $where_2 = substr($where,$pos+7);

//    error_log("Q=".@$params['q']);
//DEBUG error_log(@$params['q'].">>>>".$where_1);
//DEBUG error_log("2>>>".$where_2);
/*

from Records TOPBIBLIO left join usrBookmarks TOPBKMK on bkm_recID=rec_ID and bkm_UGrpID=1000 where (rec_OwnerUGrpID=1000 or not rec_NonOwnerVisibility="hidden"
or rec_OwnerUGrpID in (1,0)) and not rec_FlagTemporary  order by rec_Title = "", rec_Title

from Records TOPBIBLIO left join usrBookmarks TOPBKMK on bkm_recID=rec_ID and bkm_UGrpID=1000 where (rec_OwnerUGrpID=1000 or not rec_NonOwnerVisibility="hidden" or
rec_OwnerUGrpID in (1,0)) and ((exists (select * from recDetails rd left join defDetailTypes rdt on rdt.dty_ID=rd.dtl_DetailTypeID left join Records link on rd.dtl_Value=link.rec_ID left join
 defTerms trm on trm.trm_Label  like '%all%' where rd.dtl_RecID=TOPBIBLIO.rec_ID  and if(rdt.dty_Type = "resource" AND 1, link.rec_Title  like '%all%', if(rdt.dty_Type in ("enum","relationtype"), rd
.dtl_Value = trm.trm_ID, rd.dtl_Value  like '%all%')) and rd.dtl_DetailTypeID = 4) and rec_RecTypeID = 15)) and not rec_FlagTemporary  order by rec_Title = "", rec_Title
*/


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

//DEBUG error_log(">>>".$query);

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
