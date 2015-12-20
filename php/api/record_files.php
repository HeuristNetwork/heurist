<?php

    /**
    * Application interface. See hRecordMgr in hapi.js
    * uploaded and registered external files manipulation
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2015 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */


    require_once (dirname(__FILE__).'/../System.php');
    require_once (dirname(__FILE__).'/../../common/db_files.php');

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
