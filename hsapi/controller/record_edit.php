<?php

    /**
    * Application interface. See hRecordMgr in hapi.js
    * record manipulation - add, save, delete
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

    require_once (dirname(__FILE__).'/../System.php');
    require_once (dirname(__FILE__).'/../dbaccess/db_records.php');

detectLargeInputs('REQUEST record_edit', $_REQUEST);
detectLargeInputs('COOKIE record_edit', $_COOKIE);
    
    $response = array();
    
    $system = new System();
    if( ! $system->init(@$_REQUEST['db']) ){

        //get error and response
        $response = $system->getError();

    }else{

        $mysqli = $system->get_mysqli();

        if ( $system->get_user_id()<1 && !(@$_REQUEST['a']=='s' && @$_REQUEST['Captcha']) ) {
            
            $response = $system->addError(HEURIST_REQUEST_DENIED);

        }else{
            
            $action = @$_REQUEST['a'];// || @$_REQUEST['action'];

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

            } else if ($action=="s" || $action=="save") {

                $response = recordSave($system, $_REQUEST);

            } else if ($action=='batch_save') {

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
                        //unset($response['rec_Title']);
                    }
                }else{
                    // TODO: Improve message
                    $response = array('status'=>HEURIST_ERROR, 'msg'=>'No records provided');
                }

            } else if (($action=="d" || $action=="delete") && @$_REQUEST['ids']){

                $response = recordDelete($system, $_REQUEST['ids'], true, @$_REQUEST['check_links'], @$_REQUEST['rec_RecTypeID'], @$_REQUEST['session']);

            } else if ($action=="access"){

                $response = recordUpdateOwnerAccess($system, $_REQUEST);

            } else if ($action=="increment"){

                $response = recordGetIncrementedValue($system, $_REQUEST);
                
            } else if ($action=="duplicate" && @$_REQUEST['id']) {

                
                $mysqli = $system->get_mysqli();
                $keep_autocommit = mysql__select_value($mysqli, 'SELECT @@autocommit');
                if($keep_autocommit===true) $mysqli->autocommit(FALSE);
                if (strnatcmp(phpversion(), '5.5') >= 0) {
                    $mysqli->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
                }

                $response = recordDuplicate($system, $_REQUEST['id']);

                if( $response && @$response['status']==HEURIST_OK ){
                    $mysqli->commit();
                }else{
                    $mysqli->rollback();
                }
                if($keep_autocommit===true) $mysqli->autocommit(TRUE);                

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
//header('Content-type: application/json;charset=UTF-8');
$system->setResponseHeader();
print json_encode($response);
?>
