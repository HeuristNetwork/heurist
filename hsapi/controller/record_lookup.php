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

if(count($_REQUEST)>900){
    error_log('TOO MANY _REQUEST PARAMS '.count($_REQUEST).' record_loopup');
    error_log(print_r(array_slice($_REQUEST, 0, 100),true));
}    


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
        
        // Create xml object
        $xml_obj = simplexml_load_string($remote_data, null, LIBXML_PARSEHUGE);
        // xml namespace urls: http://www.loc.gov/zing/srw/ (srw), info:lc/xmlns/marcxchange-v2 (mxc)

        // Retrieve records from results
        $records = $xml_obj->children('http://www.loc.gov/zing/srw/', false)->records->record;

        // Move each result's details into seperate array
        foreach ($records as $key => $details) {

            $formatted_array = array();

            foreach ($details->recordData->children('info:lc/xmlns/marcxchange-v2', false)->record->controlfield as $key => $cf_ele) { // controlfield elements
                $cf_tag = @$cf_ele->attributes()['tag'];

                if($cf_tag == '003') { // Record URL
                    $formatted_array['biburl'] = (string)$cf_ele[0];
                    break;
                }
            }

            foreach ($details->recordData->children('info:lc/xmlns/marcxchange-v2', false)->record->datafield as $key => $df_ele) { // datafield elements
                $df_tag = @$df_ele->attributes()['tag'];

                if(!$df_tag) {
                    continue;
                }

                if($df_tag == '010') { // ISBN

                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == 'a') {
                            $formatted_array['isbn'] = (string)$sf_ele[0];
                        }
                    }
                }else if($df_tag == '071' || $df_tag == '073' || $df_tag == '464') { // Description Fields

                    $value = '';
                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($df_tag == '071' && ($sf_code == 'b' || $sf_code == 'a')) {
                            $value = ($value == '') ? (string)$sf_ele[0] : ' ' . (string)$sf_ele[0];
                        }else if($df_tag == '073' && $sf_code == 'a') {
                            $value = 'Code à barres commercial : EAN ' . (string)$sf_ele[0];
                        }else if($df_tag == '464' && $sf_code == 't') {
                            $value = (string)$sf_ele[0];
                        }
                    }

                    if($value != '') {
                        $index = ($df_tag == '464') ? 0 : 1;

                        if(!array_key_exists('description', $formatted_array) || !array_key_exists($index, $formatted_array['description'])) {
                            $formatted_array['description'][$index] = $value;
                        }else{
                            $formatted_array['description'][$index] .= ' ' . $value;
                        }
                    }
                }else if($df_tag == '101' || $df_tag == '102') { // Language, e.g. fre or FR

                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == 'a') {
                            $formatted_array['language'][] = (string)$sf_ele[0];
                        }
                    }
                }else if($df_tag == '200' || $df_tag == '500') { // Title / Type / Primary Author (Full Name, Firstname Lastname)

                    foreach($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == 'a') {

                            if($df_tag == '500') {
                                $formatted_array['title'] = (string)$sf_ele[0];
                                break;
                            }else if(!array_key_exists('title', $formatted_array)) { // Default Title
                                $formatted_array['title'] = (string)$sf_ele[0];
                                continue;
                            }
                        }else if($df_tag == '200' && $sf_code == 'b') {
                            $formatted_array['type'] = (string)$sf_ele[0];
                        }
                    }
                }else if($df_tag == '210' || $df_tag == '214') { // Publisher Location / Publisher Name / Year of Publication
                    
                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == 'a') {
                            $formatted_array['publisher']['location'] = (string)$sf_ele[0];
                        }else if($sf_code == 'c') {
                            $formatted_array['publisher']['name'] = (string)$sf_ele[0];
                        }else if($sf_code == 'd') {
                            $formatted_array['date'] = (string)$sf_ele[0];
                        }
                    }
                }else if($df_tag == '606') { // Subject Fields
                    
                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == "a" || $sf_code == "y" || $sf_code == "z") {

                            if(!array_key_exists('subject', $formatted_array)) {
                                $formatted_array['subject'] = (string)$sf_ele[0];
                            }else{
                                $formatted_array['subject'] .= (string)$sf_ele[0];
                            }
                        }
                    }
                }else if($df_tag == '608') { // Type, mostly for music compsitions for movies and such

                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == 'a' && $sf_ele[0] == 'édition phonographique' && !array_key_exists('type', $formatted_array)) {
                            $formatted_array['type'] = 'enregistrement sonore';
                        }
                    }
                }else if($df_tag == '700' || $df_tag == '702') { // Primary Author Details & Secondary Author/Contributor Details

                    $index = ($df_tag == '700') ? 'creator' : 'contributor';
                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == '3') {
                            $formatted_array[$index]['id'] = (string)$sf_ele[0];
                        }else if($sf_code == 'a') {
                            $formatted_array[$index]['surname'] = (string)$sf_ele[0];
                        }else if($sf_code == 'b') {
                            $formatted_array[$index]['firstname'] = (string)$sf_ele[0];
                        }else if($sf_code == 'f') {
                            $formatted_array[$index]['active'] = (string)$sf_ele[0];
                        }
                    }
                }
            }

            $results['result'][] = $formatted_array;
        }

        // Add other details, can be used for more calls to retrieve all results (currently retrieves 500 records at max)
        $results['numberOfRecords'] = intval($xml_obj->children('http://www.loc.gov/zing/srw/', false)->numberOfRecords);
        $results['nextStart'] = intval($xml_obj->children('http://www.loc.gov/zing/srw/', false)->nextRecordPosition);

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