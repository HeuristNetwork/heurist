<?php
/**
* Application interface. See hRecordMgr in hapi.js
*     
*       uploaded and registered external files manipulation
*/

require_once (dirname(__FILE__).'/../System.php');
require_once (dirname(__FILE__).'/../common/db_files.php');

$response = array();

$system = new System();
if( ! $system->init(@$_REQUEST['db']) ){

    //get error and response
    $response = $system->getError();

}else{

    $mysqli = $system->get_mysqli();

    if ( $system->get_user_id()<1 ) {

         $response = $system->addError(HEURIST_REQUEST_DENIED);

    }else{

        $action = @$_REQUEST['a'];// || @$_REQUEST['action'];

        // call function from db_record library
        // these function returns standard response: status and data
        // data is recordset (in case success) or message

        $res = false;

        if($action=="add" || $action=="save"){

           $res = fileSave($system, $_REQUEST);

        } else if ($action=="delete" && @$_REQUEST['ids']) {

            $res = fileDelete($system, $_REQUEST['ids'], @$_REQUEST['UGrpID']);

        } else if ($action=="search" ) {

            $res = fileSearch($system, true, @$_REQUEST['recIDs'], @$_REQUEST['mediaType'], @$_REQUEST['UGrpID']);
            if ( is_array($res) ) {
                $res['recIDs'] = @$_REQUEST['recIDs'];
            }
            
        } else if ($action=="viewer" ) {    

            //find all files for given set of records
            $res = fileSearch($system, true, @$_REQUEST['recIDs']);
            if(@$_REQUEST['mode']=="yox"){ 
                //generate html output for yoxviewer in frame ???? or on client side ????
                exit();                
            }else if ( is_array($res) ) {
                $res['recIDs'] = @$_REQUEST['recIDs'];
            }
            
            
        } else {

            $system->addError(HEURIST_INVALID_REQUEST, "Type of request not defined or not allowed");
        }

        if( is_bool($res) && !$res ){
            $response = $system->getError();
        }else{
            $response = array("status"=>HEURIST_OK, "data"=> $res);
        }

    }
}

header('Content-type: text/javascript');
print json_encode($response);
exit();
?>
