<?php

    /**
    * SCRUD controller
    * search, create, read, update and delete
    * 
    * Application interface. See hRecordMgr in hapi.js
    * Add/replace/delete details in batch
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
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
    require_once ('entityScrudSrv.php');

    $response = array();
    $res = false;

    $system = new System();
    $entity = null;
    
    $need_config = false;
    
    //sanitizeRequest($_REQUEST);  it brokes json strings
    stripScriptTagInRequest($_REQUEST);
    
    if($system->init(@$_REQUEST['db'])){

        $res = array();        
        $entities = array();
        
        if(@$_REQUEST['a']=='structure'){ 
            // see HAPI4.refreshEntityData
            $res = entityRefreshDefs($system, $_REQUEST['entity'], true);
        }else {
            $res = entityExecute($system, $_REQUEST);
        }
    }

    header("Access-Control-Allow-Origin: *");
    header('Content-type: application/json;charset=UTF-8');
    
    if(@$_REQUEST['restapi']==1){
        if( is_bool($res) && !$res ){
            
            $system->error_exit_api();
            
        }else{
            //$req = $entity->getData();
            $req = array();
            
            if(count($res)==0 && @$req['a'] == 'search'){
                $code = 404;    
            }else if (@$req['a'] == 'save'){
                $code = 201;
            }else{
                $code = 200;
            }
            http_response_code($code);    
        
            print json_encode($res);
        }
    }else{
        
        if( is_bool($res) && !$res ){
            $response = $system->getError();
        }else{
            $response = array("status"=>HEURIST_OK, "data"=> $res);
        }
        
        $res = json_encode($response); //JSON_INVALID_UTF8_IGNORE 
        if(!$res){
            $system->addError(HEURIST_SYSTEM_CONFIG, 'Your data definitions (names, descriptions) contain invalid characters. Or system can not convert them properly');
            print json_encode( $system->getError() );
        }else{
            print $res;    
        }
        
    }
/*
Description Of Usual Server Responses:
200 OK - the request was successful (some API calls may return 201 instead).
201 Created - the request was successful and a resource was created.
204 No Content - the request was successful but there is no representation to return (i.e. the response is empty).
400 Bad Request - the request could not be understood or was missing required parameters.
401 Unauthorized - authentication failed or user doesn't have permissions for requested operation.
403 Forbidden - access denied.
404 Not Found - resource was not found.
405 Method Not Allowed - requested method is not supported for resource.
*/    
?>