<?php

    /** 
    * Application interface. See hRecordMgr in hapi.js
    *   tags and bookmarks manipulation 
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
    require_once (dirname(__FILE__).'/../common/db_tags.php');

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

                $res = tagSave($system, $_REQUEST);

            } else if ($action=="delete" && @$_REQUEST['ids']) {

                $res = tagDelete($system, $_REQUEST['ids'], @$_REQUEST['UGrpID']);

            } else if ($action=="search" && @$_REQUEST['UGrpID'] ) {

                $res = tagGetByRecords($system, @$_REQUEST['info']!="short", @$_REQUEST['recIDs'], @$_REQUEST['UGrpID']);
                if ( is_array($res) ) {
                    $res['recIDs'] = @$_REQUEST['recIDs'];
                }
                /*
                $res = tagGetByUser($system, false, $_REQUEST['UGrpID']);
                */

            } else if ($action=="replace" && @$_REQUEST['UGrpID'] ) {

                $res = tagReplace($system, $_REQUEST['ids'], $_REQUEST['new_id'], @$_REQUEST['UGrpID']);

            } else if ($action=="rating" ) {
                
                $res = bookmarkRating($system, @$_REQUEST['recIDs'], @$_REQUEST['rating'], @$_REQUEST['UGrpID']);
                
            } else if ($action=="set") {  // assign/remove tags to records

                if(@$_REQUEST['assign']){
                    $res = tagsAssign($system, @$_REQUEST['recIDs'], @$_REQUEST['assign'], null, @$_REQUEST['UGrpID']);
                }else{
                    $res = array();
                }

                if(!is_bool($res) && @$_REQUEST['remove']){
                    $res2 = tagsRemove($system, @$_REQUEST['recIDs'], @$_REQUEST['remove'], null, @$_REQUEST['UGrpID']);
                    if(is_bool($res) && !$res){
                        $res = false;       
                    }else{
                        $res = array_merge($res, $res2);
                    }
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
