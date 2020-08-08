<?php

/**
* Lookup third party web service to return data to client side recordLookup.js
* It works as a proxy to avoid cross-origin issues
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
    require_once (dirname(__FILE__).'/../dbaccess/utils_db.php');

    $response = array();

    $system = new System();

    $params = $_REQUEST;

    if( !$system->init(@$params['db']) ){
        //get error and response
        $system->error_exit_api(); //exit from script
    }else if ( $system->get_user_id()<1 ) {
        $system->error_exit_api(null, HEURIST_REQUEST_DENIED); 
        //$response = $system->addError(HEURIST_REQUEST_DENIED);
    }

    if(!(@$params['service'])){
        $system->error_exit_api('Service parameter is not defined or has wrong value'); //exit from script
    }

    $url = $params['service'];
    $remote_data = loadRemoteURLContent($url, true);    
    if($remote_data===false){
        $system->error_exit_api('Cannot connect/load data from the service: '.$url, HEURIST_ERROR);    
    }

    json_decode($remote_data);
    if(json_last_error() == JSON_ERROR_NONE){
    }else{
/*        
        $array = array_map("str_getcsv", explode("\n", $csv));
        $json = json_encode($array);
*/
        $hasGeo = false;
        $remote_data = str_getcsv($remote_data, "\n"); //parse the rows
        if(is_array($remote_data) && count($remote_data)>1){
            
            $header = str_getcsv(array_shift($remote_data));
            $id = 1;
            foreach($remote_data as &$line){
                $line = str_getcsv($line);  
                foreach($header as $idx=>$key){
                     $line[$key] = $line[$idx];
                     unset($line[$idx]);
                }
                if(@$line['latitude'] && @$line['longitude']){
                    $line = array('type'=>'Feature','id'=>$id, 'properties'=>$line,
                        'geometry'=>array('type'=>'Point','coordinates'=>array($line['longitude'], $line['latitude'])));
                    $hasGeo = true;
                }
            } 
            
            if(!$hasGeo){
                $system->error_exit_api('Service did not return data in an appropriate format');
            }
        }else if(is_array($remote_data) && count($remote_data)==1){
                $system->error_exit_api('No records match the search criteria', HEURIST_NOT_FOUND);
        }else{
                $system->error_exit_api('Service did not return any data');
        }
        
        
        $remote_data = json_encode($remote_data);
    }

    header('Content-Type: application/json');
    //$json = json_encode($json);
    //header('Content-Type: application/vnd.geo+json');
    //header('Content-Disposition: attachment; filename=output.json');
    header('Content-Length: ' . strlen($remote_data));
    exit($remote_data);
?>