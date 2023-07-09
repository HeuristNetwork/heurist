<?php
    require_once (dirname(__FILE__).'/../../../hsapi/System.php');
    require_once (dirname(__FILE__).'/../../../hsapi/controller/entityScrudSrv.php');

    $system = new System();
    if( ! $system->init(@$_REQUEST['db']) ) {
        $response = $system->getError();
        exit($response['message']);
    }
    
/*    
    require_once (dirname(__FILE__).'/../../../hsapi/dbaccess/db_structure.php');
    require_once (dirname(__FILE__).'/../../../hsapi/dbaccess/db_structure_tree.php');

    
define('_DBG2', true); //debug log output
    
$time_debug = microtime(true);        
    
    $data = dbs_GetTerms($system);

print ('terms '.(microtime(true)-$time_debug));        
*/
    $params = null;
    $recID = @$_REQUEST['recID'];
    if($recID>0){
        $params = array('recID'=>$recID);
    }
    
    $res = entityRefreshDefs($system, 'all', true, $params);

    print json_encode($res);    
?>
