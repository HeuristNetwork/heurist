<?php
/**
* Application interface. See hRecordMgr in hapi.js
*     
*       record search
*/
require_once (dirname(__FILE__).'/../System.php');
require_once (dirname(__FILE__).'/../common/db_recsearch.php');

/* DEBUG
$_REQUEST['db'] = 'dos_3';
$_REQUEST['q'] = 'manly';
            */

$response = array();

$system = new System();
if( ! $system->init(@$_REQUEST['db']) ){

    //get error and response
    $response = $system->getError();

}else if(@$_REQUEST['a'] == 'minmax'){

    $response = recordSearchMinMax($system, $_REQUEST);
    
}else if(@$_REQUEST['a'] == 'getfacets'){    

    $response = recordSearchFacets($system, $_REQUEST);
    
}else {

//DEGUG        $currentUser = array('ugr_ID'=>2);

    $need_structure = (@$_REQUEST['f']=='structure');
    $need_details = (@$_REQUEST['f']=='map' || $need_structure);

    $response = recordSearch($system, $_REQUEST, $need_structure, $need_details);
}

header('Content-type: text/javascript');
print json_encode($response);
exit();
?>
