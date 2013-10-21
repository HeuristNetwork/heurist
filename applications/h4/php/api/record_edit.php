<?php
/**
* Application interface. See hRecordMgr in hapi.js
*     
*       record manipulation - add, save, delete
*/
require_once (dirname(__FILE__).'/../System.php');
require_once (dirname(__FILE__).'/../common/db_records.php');

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

        if($action=="a" || $action=="add"){

           $record = array();
           $record['RecTypeID'] = @$_REQUEST['rt'];
           $record['OwnerUGrpID'] = @$_REQUEST['ro'];
           $record['NonOwnerVisibility'] =  @$_REQUEST['rv'];

           $response = recordAdd($system, $record);

        } else if ($action=="s" || $action=="save") {

           $response = recordSave($system, $_REQUEST);

        } else if (($action=="d" || $action=="delete") && @$_REQUEST['ids']){

            $response = recordDelete($system, $_REQUEST['ids']);

        } else {

            $response = $system->addError(HEURIST_INVALID_REQUEST);
        }
    }
}

header('Content-type: text/javascript');
print json_encode($response);
exit();
?>
