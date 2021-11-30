<?php

/**
* Lookup third party web service to return data to client side recordLookups
* It works as a proxy to avoid cross-origin issues
* 
* Currently supporting services:
* GeoName
* TLCMap
* BnF Library
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

    if(!(@$params['service'])){
        $system->error_exit_api('Service parameter is not defined or has wrong value'); //exit from script
    }
    
    if( !$system->init(@$params['db']) ){  //@todo - we don't need db connection here - it is enough check the session
        //get error and response
        $system->error_exit_api(); //exit from script
    }else if ( $system->get_user_id()<1 ) {
        $system->error_exit_api(null, HEURIST_REQUEST_DENIED); 
        //$response = $system->addError(HEURIST_REQUEST_DENIED);
    }

    $system->dbclose();
    
	// Perform external lookup / API request
    $url = $params['service'];
    $remote_data = loadRemoteURLContent($url, true);    
    if($remote_data===false){
        $system->error_exit_api('Cannot connect/load data from the service: '.$url, HEURIST_ERROR);    
    }

    if(@$params['serviceType'] == 'geonames' || @$params['serviceType'] == 'tlcmap'){ // GeoName and TLCMap lookups

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
    }else if(@$params['serviceType'] == 'bnflibrary'){ // BnF Library Search

        $results = array();

        // Create xml object, BnF Library Search always returns as XML no matter the chosen record schema
        $xml_obj = simplexml_load_string($remote_data, null, LIBXML_PARSEHUGE);         
        // xml namespace urls: http://www.loc.gov/zing/srw/ (srw), http://www.openarchives.org/OAI/2.0/oai_dc/ (oai_dc), http://purl.org/dc/elements/1.1/ (dc)

        // Retrieve records from results
        $records = $xml_obj->children('http://www.loc.gov/zing/srw/', false)->records->record;

        $nextStart = 0; // can be used to run the query again with a new start, startRecord

        // Move each result's details into separate array
        foreach ($records as $key => $details) {
            $nextStart = intval($details->recordPosition);
            $results['result'][] = $details->recordData->children('http://www.openarchives.org/OAI/2.0/oai_dc/', false)->children('http://purl.org/dc/elements/1.1/', false);
        }

        // Add other details, can be used for more calls to retrieve all results (currently retrieves 500 records at max)
        $results['numberOfRecords'] = intval($xml_obj->children('http://www.loc.gov/zing/srw/', false)->numberOfRecords);
        $results['nextStart'] = $nextStart + 1;

        // Encode to json for response to JavaScript
        $remote_data = json_encode($results);
    }

	// Return response
    header('Content-Type: application/json');
    //$json = json_encode($json);
    //header('Content-Type: application/vnd.geo+json');
    //header('Content-Disposition: attachment; filename=output.json');
    header('Content-Length: ' . strlen($remote_data));
    exit($remote_data);
?>