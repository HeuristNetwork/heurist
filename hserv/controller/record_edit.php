<?php
/**
* record_edit.php - Handler for CUD actions for Heurst record
* 
* Handler for CUD (create/update/delete) actions for Heurst record.
* Application interface. See HRecordMgr in hapi.js.
*
* @project     Heurist academic knowledge management system
* @package Controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

    //ini_set('max_execution_time', '0');
    set_time_limit(120);

    require_once dirname(__FILE__).'/../../autoload.php';
    require_once dirname(__FILE__).'/../records/edit/recordModify.php';

    $response = array();

    $system = new hserv\System();
    if( ! $system->init(@$_REQUEST['db']) ){

        //get error and response
        $response = $system->getError();

    }else{

        $mysqli = $system->getMysqli();

        if ( $system->getUserId()<1 && !(@$_REQUEST['a']=='s' && @$_REQUEST['Captcha']) ) {

            $response = $system->addError(HEURIST_REQUEST_DENIED);

        }else{

            $action = @$_REQUEST['a'];

            // call function from db_record library
            // these function returns standard response: status and data
            // data is recordset (in case success) or message

            if($action=="a" || $action=="add"){

                if(@$_REQUEST['rt']>0){ //old
                    $record = array();
                    $record['RecTypeID'] = @$_REQUEST['rt'];
                    $record['OwnerUGrpID'] = @$_REQUEST['ro'];
                    $record['NonOwnerVisibility'] =  @$_REQUEST['rv'];
                    $record['FlagTemporary'] = @$_REQUEST['temp'];
                }else{ //new
                    $record = $_REQUEST;
                }

                $response = recordAdd($system, $record);

            } elseif($action=="s" || $action=="save") {

                $response = recordSave($system, $_REQUEST);

            } elseif($action=='batch_save') {

                $rec_ids = array();

                if(array_key_exists('records', $_REQUEST)){

                    foreach ($_REQUEST['records'] as $key => $record) {

                        $response = recordSave($system, $record);

                        if(!$response || $response['status'] != HEURIST_OK){
                            break;
                        }else{
                            $rec_ids[$key] = $response['data'];
                        }
                    }

                    if($response['status'] == HEURIST_OK){
                        $response['data'] = $rec_ids;
                    }
                }else{
                    // to improve message
                    $response = array('status'=>HEURIST_ERROR, 'msg'=>'No records provided');
                }

            } elseif (($action=="d" || $action=="delete") && @$_REQUEST['ids']){

                $response = recordDelete($system, $_REQUEST['ids'], true, @$_REQUEST['check_links'], @$_REQUEST['rec_RecTypeID'], @$_REQUEST['session']);

            } elseif($action=="access"){

                $response = recordUpdateOwnerAccess($system, $_REQUEST);

            } elseif($action=="increment"){

                $response = recordGetIncrementedValue($system, $_REQUEST);

            } elseif($action=="duplicate" && isset($_REQUEST['id'])) {


                $mysqli = $system->getMysqli();
                $keep_autocommit = mysql__begin_transaction($mysqli);
                
                $response = recordDuplicate($system, $_REQUEST['id'], $_REQUEST['permissions']??null, $_REQUEST['likedRtyID']??null, $_REQUEST['namePrefix']??null);

                $isOK = $response && @$response['status']==HEURIST_OK;

                mysql__end_transaction($mysqli, $isOK, $keep_autocommit);

            } else {
                $response = $system->addError(HEURIST_INVALID_REQUEST);
            }
        }

        $system->dbclose();
    }

if($response==false){
    $response = $system->getError();
}

// Return the response object as JSON
// header(CTYPE_JSON)
$system->setResponseHeader();
print json_encode($response);
?>
