<?php
/**
* Application interface. See hSystemMgr in hapi.js
*     
*       database definitions - record types, record structure, field types, terms
* 
*/


require_once (dirname(__FILE__).'/../System.php');
require_once (dirname(__FILE__).'/../common/db_structure.php');
require_once (dirname(__FILE__).'/../common/db_structure_tree.php');

/* DEBUG
$_REQUEST['db'] = 'dos_3';
$_REQUEST['q'] = 'manly';
            */

$response = array();

$system = new System();
if( ! $system->init(@$_REQUEST['db']) ){

    //get error and response
    $response = $system->getError();

}else{

    //$currentUser = $system->getCurrentUser();
    $data = array();

    if (@$_REQUEST['terms']) {
        $data["terms"] = dbs_GetTerms($system);
    }

    if (@$_REQUEST['detailtypes']) {
        $ids = $_REQUEST['detailtypes']=='all'?null:$_REQUEST['detailtypes'];
        $data["detailtypes"] = dbs_GetDetailTypes($system, $ids, intval(@$_REQUEST['mode']) );
    }
    
    if (@$_REQUEST['rectypes']) {
        $ids = $_REQUEST['rectypes']=='all'?null:$_REQUEST['rectypes'];
        $mode = intval(@$_REQUEST['mode']);
        
        if($mode>2){
            $data["rectypes"] = dbs_GetRectypeStructureTree($system, $ids, $mode );    
        }else{
            $data["rectypes"] = dbs_GetRectypeStructures($system, $ids, $mode );    
        }
    }

    $response = array("status"=>HEURIST_OK, "data"=> $data );

}
header('Content-type: text/javascript');
print json_encode($response);
exit();
?>