<?php

    /**
    * Application interface. See hRecordMgr in hapi.js
    * Add/replace/delete details in batch
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2019 University of Sydney
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
    require_once (dirname(__FILE__).'/../dbaccess/dbRecDetails.php');
    require_once (dirname(__FILE__).'/../dbaccess/utils_db.php');

    $response = array();
    $res = false;

    $system = new System();
    if( ! $system->init(@$_REQUEST['db']) ){
        //get error and response
        $response = $system->getError();

    }else {
        
        set_time_limit(0);
        
        $dbRecDetails = new DbRecDetails($system, $_REQUEST);

        if(@$_REQUEST['a'] == 'add_child'){ //add child record
            
           $res = $dbRecDetails->detailsAddChild();
            
        }else         
        if(is_array(@$_REQUEST['actions'])){
        
            $res = $dbRecDetails->multiAction();
        
        }else         
        if(@$_REQUEST['a'] == 'add'){

            $res = $dbRecDetails->detailsAdd();

        }else if(@$_REQUEST['a'] == 'replace'){ //returns
        
            $res = $dbRecDetails->detailsReplace();

        }else if(@$_REQUEST['a'] == 'addreplace'){ //returns
        
                $res = $dbRecDetails->detailsReplace();
                if($res['passed']==1 && $res['undefined']==1){
                    $res = $dbRecDetails->detailsAdd();
                }
            
        }else if(@$_REQUEST['a'] == 'delete'){

            $res = $dbRecDetails->detailsDelete();

        }else if(@$_REQUEST['a'] == 'add_reverse_pointer_for_child'){
            
            $res = $dbRecDetails->addRevercePointerForChild();
            
        }else if(@$_REQUEST['a'] == 'rectype_change'){

            $res = $dbRecDetails->changeRecordTypeInBatch();

        }else if(@$_REQUEST['a'] == 'extract_pdf'){

            $res = $dbRecDetails->extractPDF();

        }else {

            $system->addError(HEURIST_INVALID_REQUEST, "Type of request not defined or not allowed");
        }
        
        $dbRecDetails->removeSession();
    }

    
    if( is_bool($res) && !$res ){
        $response = $system->getError();
    }else{
        $response = array("status"=>HEURIST_OK, "data"=> $res);
    }
    
    header('Content-type: application/json'); //'text/javascript');
    print json_encode($response);
    exit();
?>