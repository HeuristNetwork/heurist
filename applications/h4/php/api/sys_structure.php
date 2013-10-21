<?php
/**
* Application interface. See hSystemMgr in hapi.js
*     
*       database definitions - record types, record structure, field types, terms
* 
*/


require_once (dirname(__FILE__).'/../System.php');
require_once (dirname(__FILE__).'/../common/db_structure.php');

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

    if (@$_REQUEST['rectypes']) {
        $ids = $_REQUEST['rectypes']=='all'?null:$_REQUEST['rectypes'];
        $data["rectypes"] = dbs_GetRectypeStructures($system, $ids, intval(@$_REQUEST['mode']) );
    }

    $response = array("status"=>HEURIST_OK, "data"=> $data );

}
header('Content-type: text/javascript');
print json_encode($response);
exit();
?>