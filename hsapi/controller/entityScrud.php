<?php

    /**
    * Entity SCRUD controller - web interface. It uses functions from entityScrudSrv.php
    * search, create, read, update and delete
    * 
    * Application interface. See hRecordMgr in hapi.js
    * Add/replace/delete details in batch
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
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

/*    
if (@$argv) {
    //php -f entityScrud.php -- -db xxx -entity usrReminders -a batch

    // handle command-line
    $ARGV = array();
    for ($i = 0;$i < count($argv);++$i) {
        if ($argv[$i][0] === '-') {  //pair: -param value                  
            if (@$argv[$i + 1] && $argv[$i + 1][0] != '-') {
                $ARGV[substr($argv[$i],1)] = $argv[$i + 1];
                ++$i;
            }
        } else {
            //array_push($ARGV, $argv[$i]);
        }
    }

    $_REQUEST = $ARGV;
    
    define('HEURIST_DIR', getcwd().'/../../');
}
*/    
    
    require_once (dirname(__FILE__).'/../System.php');
    require_once ('entityScrudSrv.php');

    $system = new System();
    
    $dbdef_cache = $db_defs = $system->getFileStoreRootFolder().@$_REQUEST['db'].'/entity/db.json';

    if(@$_REQUEST['a']=='structure' && @$_REQUEST['entity']=='all'){
        if(file_exists($dbdef_cache) && $defaultRootFileUploadURL){
            if($allowThumbnailsWebAccessdefault){
                $url = $defaultRootFileUploadURL . $_REQUEST['db'].'/entity/db.json';
                header('Location: '.$url);
            }else{
                downloadFile(null,$db_defs);
            }
            exit();
        }
    }
    
    $response = array();
    $res = false;

    $entity = null;
    
    $need_config = false;
    
    //sanitizeRequest($_REQUEST);  it brokes json strings
    stripScriptTagInRequest($_REQUEST);
    
    if($system->init(@$_REQUEST['db'])){

        $res = array();        
        $entities = array();
        
        if(@$_REQUEST['a']=='structure'){ 
            // see HAPI4.refreshEntityData
            if(@$_REQUEST['entity']=='force_all'){
                //remove cache
                fileDelete($dbdef_cache);
                $_REQUEST['entity']='all';
            }
            $res = entityRefreshDefs($system, @$_REQUEST['entity'], true); //, @$_REQUEST['recID']);
            
            //update dbdef cache
            if(@$_REQUEST['entity']=='all' && $res!==false){
                file_put_contents($dbdef_cache, json_encode($res));
            }
            
        }else {
            $res = entityExecute($system, $_REQUEST);
        }
        
        $system->dbclose();
    }

    header("Access-Control-Allow-Origin: *");
    header('Content-type: application/json;charset=UTF-8');
    
    if(@$_REQUEST['restapi']==1){
        if( is_bool($res) && !$res ){
            
            $system->error_exit_api();
            
        }else{
            //$req = $entity->getData();
            $req = array();
            
            if(@$req['a'] == 'search' && count($res)==0){
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
        
        if (strnatcmp(phpversion(), '7.3') >= 0) {

            try{
                        
               $res = json_encode($response, JSON_THROW_ON_ERROR);

               if(false && strlen($res)>20000){
                   ob_start(); 
                   echo json_encode($res);
                   $output = gzencode(ob_get_contents(),6); 
                   ob_end_clean(); 
                   header('Content-Encoding: gzip');
                   echo $output; 
                   unset($output); 
               }else{
                   echo $res;     
                   unset($res);     
               }
    
    
            } catch (JsonException $e) {
                
                $res = json_encode($response, JSON_INVALID_UTF8_IGNORE );
                
                print $res;    
                
                /*
                $system->addError(HEURIST_SYSTEM_CONFIG, 'Your data definitions (names, descriptions) contain invalid characters. '
                .'Or system cannot convert them properly. '.$e->getMessage());
                print json_encode( $system->getError() );
                */
            }            
            
        }else{
            
            $res = json_encode($response); //JSON_INVALID_UTF8_IGNORE 
            if(!$res){
                
                //
                //find wrong value
                $wrong_string = null;
                try{
                    array_walk_recursive($response, 'find_invalid_string');
                    //$response = array_map('find_invalid_string', $response);
                
                }catch(Exception $exception) {
                       $wrong_string = $exception->getMessage();
                }
                
                $msg = 'Your data definitions (names, descriptions) contain invalid characters (non UTF-8). '
                .'Or system cannot convert them properly.';
                
                if($wrong_string){
                    $msg = $msg . ' Invalid character in string: '.$wrong_string;
                }
                
                $system->addError(HEURIST_SYSTEM_CONFIG, $msg);
                print json_encode( $system->getError() );
            }else{
                print $res;    
            }
            
        }
        
        
    }
    
    function find_invalid_string($val){
        if(is_string($val)){
            $stripped_val = iconv('UTF-8', 'UTF-8//IGNORE', $val);
            if($stripped_val!=$val){
                throw new Exception(mb_convert_encoding($val,'UTF-8'));    
            }
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