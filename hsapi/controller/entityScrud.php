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

    require_once (dirname(__FILE__).'/../entity/dbUsrTags.php');
    require_once (dirname(__FILE__).'/../entity/dbSysDatabases.php');
    require_once (dirname(__FILE__).'/../entity/dbSysIdentification.php');
    require_once (dirname(__FILE__).'/../entity/dbSysGroups.php');
    require_once (dirname(__FILE__).'/../entity/dbSysUsers.php');
    require_once (dirname(__FILE__).'/../entity/dbDefDetailTypeGroups.php');
    require_once (dirname(__FILE__).'/../entity/dbDefFileExtToMimetype.php');
    require_once (dirname(__FILE__).'/../entity/dbDefTerms.php');
    require_once (dirname(__FILE__).'/../entity/dbDefRecTypeGroups.php');
    require_once (dirname(__FILE__).'/../entity/dbDefDetailTypes.php');
    require_once (dirname(__FILE__).'/../entity/dbDefRecTypes.php');
    require_once (dirname(__FILE__).'/../entity/dbSysArchive.php');
    require_once (dirname(__FILE__).'/../entity/dbSysBugreport.php');
    require_once (dirname(__FILE__).'/../entity/dbSysDashboard.php');
    require_once (dirname(__FILE__).'/../entity/dbSysImportFiles.php');
    require_once (dirname(__FILE__).'/../entity/dbRecThreadedComments.php');
    require_once (dirname(__FILE__).'/../entity/dbRecUploadedFiles.php');
    //require_once (dirname(__FILE__).'/../entity/dbRecords.php');
    require_once (dirname(__FILE__).'/../entity/dbUsrBookmarks.php');
    require_once (dirname(__FILE__).'/../entity/dbUsrReminders.php');
    require_once (dirname(__FILE__).'/../entity/dbUsrSavedSearches.php');
    require_once (dirname(__FILE__).'/../dbaccess/utils_db.php');

    $response = array();
    $res = false;

    $system = new System();
    $entity = null;
    
    sanitizeRequest($_REQUEST);
    
    if($system->init(@$_REQUEST['db'])){
    
        $entity_name = @$_REQUEST['entity'];
        $classname = 'Db'.ucfirst($entity_name);
        $entity = new $classname($system, $_REQUEST);
        //$r = new ReflectionClass($classname);
        //$entity = $r->newInstanceArgs($system, $_REQUEST);        
        //$entity->$method();

        if(!$entity){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong entity parameter: $entity_name");
        }else{
            $res = $entity->run();    
        }

    }
    
    
    if(@$_REQUEST['restapi']==1){
        if( is_bool($res) && !$res ){
            
            $system->error_exit_api();
            
        }else{
            $response = array("status"=>HEURIST_OK, "data"=> $res);
            
            
            header("Access-Control-Allow-Origin: *");
            header('Content-type: application/json;charset=UTF-8');
            
            $req = $entity->getData();
            
            $response = $response['data'];
            if(count($response)==0 && @$req['a'] == 'search'){
                $code = 404;    
            }else if (@$req['a'] == 'save'){
                $code = 201;
            }else{
                $code = 200;
            }
            http_response_code($code);    
            print json_encode($response);
        }
    }else{
        
        if( is_bool($res) && !$res ){
            $response = $system->getError();
        }else{
            $response = array("status"=>HEURIST_OK, "data"=> $res);
        }
        print json_encode($response);
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