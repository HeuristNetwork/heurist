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

detectLargeInputs('REQUEST record_lookup', $_REQUEST);
detectLargeInputs('COOKIE record_lookup', $_COOKIE);
    
    $response = array();

    $system = new System();

    $params = $_REQUEST;

    $is_debug = (@$params['dbg']==1);

    $is_estc = (@$params['serviceType'] == 'ESTC' && 
                (@$params['db'] == 'ESTC_Helsinki_Bibliographic_Metadata'
                ||
                @$params['action'] == 'import_records')
                );

    if(!@$params['service'] && !$is_estc){
        $system->error_exit_api('Service parameter is not defined or has wrong value'); //exit from script
    }

    $remote_data = false;
    $url = '';
    
    if($is_estc){

        $is_allowed = (isset($ESTC_PermittedDBs) && strpos($ESTC_PermittedDBs, @$_REQUEST['org_db']) !== false && isset($ESTC_UserName) && isset($ESTC_Password));
        $def_err_msg = 'For licensing reasons this function is only accessible to authorised projects.<br>Please contact the Heurist team if you wish to use this.';

if($is_debug) print HEURIST_BASE_URL.'  '.HEURIST_MAIN_SERVER.'<br>';
        
        if(strpos(strtolower(HEURIST_BASE_URL), strtolower(HEURIST_MAIN_SERVER)) !== false){ // currently on server where ESTC DB is located

            if(array_key_exists('entity', $params)){ // retrieve entity info (term lookup)
                require_once (dirname(__FILE__).'/entityScrud.php');
                exit();
            }

            $is_inited = $system->init(@$params['db']);

            if($is_inited !== false && $is_allowed){ // search records

                $is_logged_in = $system->doLogin($ESTC_UserName, $ESTC_Password, 'shared');

                if($is_logged_in){ // logged in, begin search
                    $system->getCurrentUserAndSysInfo(false);
                    
                    if(array_key_exists('action', $params)){ // import record for LRC18C lookup
    
                        if($params['action'] == 'import_records'){ // perform standard record import action, user on ESTC server
                            require_once(dirname(__FILE__).'/importController.php');
                            exit();
                        }else if($params['action'] == 'record_output'){ // retrieve record from record_output, user on external server
                            require_once(dirname(__FILE__).'/record_output.php');
                            exit();
                        }
                    }
    
                    require_once (dirname(__FILE__).'/../dbaccess/db_recsearch.php');
//if($is_debug) print print_r($params['q'], true).'<br>';
                    $response = recordSearch($system, $params);
                }else{ // unable to login, cannot access records
                    $response = array('status' => HEURIST_ERROR, 'message' => 'We are unable to access the records within the ESTC database at this moment.<br>Please contact the Heurist team. Query is: '.json_encode($params['q']));
                }
            }else{ // cannot access ESTC DB
                $response = $is_allowed ? $system->getError() : array('status' => HEURIST_REQUEST_DENIED, 'message' => $def_err_msg);
            }
        }else if(isset($ESTC_ServerURL)){ // external server

            $base_url = $ESTC_ServerURL . '/h6-alpha/hsapi/controller/record_lookup.php?';

            if(array_key_exists('action', $params) && @$params['action'] == 'import_records'){
                
                $base_url = $ESTC_ServerURL . '/h6-alpha/hsapi/controller/record_lookup.php?';  //record_output
                $params2 = array();
                $params2['action'] = 'record_output';
                $params2['serviceType'] = 'ESTC';
                $params2['format'] = 'json';
                $params2['depth'] = '0';
                $params2['db'] = 'ESTC_Helsinki_Bibliographic_Metadata';
                $params2['org_db'] = $params['org_db'];
                $params2['q'] = $params['q'];
                $params2['rules'] = @$params['rules'];
                $url = $base_url.http_build_query($params2); // forward request to ESTC server
                
if($is_debug) print 'record_output url: '.$url.'  <br>';                   

                
                // save file that produced with record_output.php from source to temp file  
                $heurist_path = tempnam(HEURIST_SCRATCH_DIR, "_temp_");
                
                $filesize = saveURLasFile($url, $heurist_path); // perform external request and save results to temp file

                
if($is_debug)print 'CONTENT '.$filesize;

                
                if($filesize>0 && file_exists($heurist_path)){
                    //read temp file, import record
                    $is_inited = $system->init(@$params['db']);
              
                    require_once (dirname(__FILE__).'/../import/importHeurist.php'); 
                    
                    $params2 = array(
                        'dbg' => ($is_debug?1:0),
                        'owner_id' => $system->get_user_id(),
                        'mapping_defs' => @$params['mapping']
                    );
                    
                    $res = ImportHeurist::importRecords($heurist_path, $params2);
                    if(is_bool($res) && $res === false){
                        $response = $system->getError();
                    }else{
                        $response = array("status"=>HEURIST_OK, "data"=> $res);
                    }

if($is_debug) print print_r($response, true).'!!!!!<br>';

                    unlink($heurist_path);
                    
                }else{
                    $response = array('status' => HEURIST_ERROR, 'message' => 'Cannot download records from '.$params['source_db'].'. <br>'.$remote_path.' to '.$heurist_path.'<br><br>URL request: ' . $url);
                }

            }else{

                $url = $base_url.http_build_query($params); // forward request to ESTC server
                $response = loadRemoteURLContentWithRange($url, null, true, 60);
            }

            if($response===false){
                global $glb_curl_error;
                $error_code = (!empty($glb_curl_error)) ? $glb_curl_error : 'Error code: 500 Heurist Error';

                $msg = 'We are having trouble performing your request on the ESTC server.<br>'
                    . 'Please try norrowing down the search with more specific criteria before running this request again.<br><br>'
                    . 'If this problem persists, please contact the Heurist team.<br><br>'
                    . $error_code . '<br>'
                    . 'Request URL: ' . $url . '<br><br>';
                $response = array('status' => HEURIST_ERROR, 'message' => $msg);
            }
        }else{ // no access
            $response = array('status' => HEURIST_REQUEST_DENIED, 'message' => 'For licensing reasons this function is only accessible to authorised projects.<br>Please contact the Heurist team if you wish to use this.');
        }

        $response = json_encode($response);

        header('Content-Type: application/json');
        header('Content-Length: ' . strlen($response));
        exit($response);
    }else{

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
    }

    $remote_data = loadRemoteURLContent($url, true);    
    if($remote_data===false){
        global $glb_curl_error;
        $error_code = (!empty($glb_curl_error)) ? $glb_curl_error : 'Error code: 500 Heurist Error';

        $system->error_exit_api('<br>Cannot connect/load data from the service: '.$url.'<br>'.$error_code, HEURIST_ERROR);
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
    }else if(@$params['serviceType'] == 'bnflibrary_bib'){ // BnF Library Search

        $results = array();
        
        // Create xml object
        $xml_obj = simplexml_load_string($remote_data, null, LIBXML_PARSEHUGE);
        // xml namespace urls: http://www.loc.gov/zing/srw/ (srw), info:lc/xmlns/marcxchange-v2 (mxc)

        // Retrieve records from results
        $records = $xml_obj->children('http://www.loc.gov/zing/srw/', false)->records->record;

        // Move each result's details into seperate array
        foreach ($records as $key => $details) {

            $formatted_array = array();

            $author_idx = 0;
            $publish_idx = 0;

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

                if($df_tag == '200') { // Title / Type

                    foreach($df_ele->subfield as $sub_key => $sf_ele) {

                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == 'a') {
                            $formatted_array['title'] = (string)$sf_ele[0];
                        }else if($sf_code == 'b'){

                            $formatted_array['type'] = (string)$sf_ele[0];

                            if(array_key_exists('title', $formatted_array)){
                                $formatted_array['title'] .= ' [' . $formatted_array['type'] . ']';
                            }
                        }else{

                            if(array_key_exists('title', $formatted_array)){
                                $formatted_array['title'] .= ' , ' . (string)$sf_ele[0];
                            }
                        }
                    }
                }else if($df_tag == '210' || $df_tag == '214') { // Publisher Location / Publisher Name / Year of Publication
                    
                    $value = '';
                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        $str_val = str_replace(array('[', ']', '(', ')'), '', (string)$sf_ele[0]);

                        if($sf_code == 'a'){
                            $formatted_array['publisher'][$publish_idx]['location'][] = $str_val;
                        }else if($sf_code == 'c'){
                            $formatted_array['publisher'][$publish_idx]['name'][] = $str_val;
                        }else if($sf_code == 'd'){
                            $formatted_array['date'] = $str_val;
                        }
                    }

                    $publish_idx ++;
                }else if($df_tag == '700' || $df_tag == '702' || $df_tag == '710' || $df_tag == '716') { // Creator

                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($df_tag == '710'){
                            if($sf_code == '3') {
                                $formatted_array['author'][$author_idx]['id'] = (string)$sf_ele[0];
                            }else if($sf_code == 'c') {
                                $formatted_array['author'][$author_idx]['surname'] = (string)$sf_ele[0];
                            }else if($sf_code == 'a' || $sf_code == 'b') {
                                $fname = (array_key_exists('firstname', $formatted_array['author'][$author_idx])) ? $formatted_array['author'][$author_idx]['firstname'].'. '.(string)$sf_ele[0] : (string)$sf_ele[0];
                                $formatted_array['author'][$author_idx]['firstname'] = $fname;
                            }
                        }else{                        
                            if($sf_code == '3') {
                                $formatted_array['author'][$author_idx]['id'] = (string)$sf_ele[0];
                            }else if($sf_code == 'a') {
                                $formatted_array['author'][$author_idx]['surname'] = (string)$sf_ele[0];
                            }else if($sf_code == 'b') {
                                $formatted_array['author'][$author_idx]['firstname'] = (string)$sf_ele[0];
                            }else if($sf_code == 'f') {
                                $formatted_array['author'][$author_idx]['active'] = (string)$sf_ele[0];
                            }
                        }

                    }

                    $author_idx ++;
                }else if($df_tag == '010') { // ISBN

                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == 'a') {
                            $formatted_array['isbn'][] = (string)$sf_ele[0];
                        }
                    }
                }else if($df_tag == '215') { // Description

                    $value = '';
                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == 'a' || $sf_code == 'c' || $sf_code == 'd'){
                            $value = ($value == '') ? (string)$sf_ele[0] : ' ' . (string)$sf_ele[0];
                        }
                    }

                    if($value != '') {

                        if($df_tag == '280') {
                            $formatted_array['description'][] = $value;
                        }
                    }
                }else if($df_tag == '101') { // Language, e.g. fre or FR 101

                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == 'a') { // a
                            $formatted_array['language'][] = (string)$sf_ele[0];
                        }
                    }
                }else if($df_tag == '327') { // Extended Description
                    
                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == "a") {
                            $formatted_array['ext_description'] = (array_key_exists('ext_description', $formatted_array)) ? $formatted_array['ext_description'] . ' ' . (string)$sf_ele[0] : (string)$sf_ele[0];
                        }
                    }
                }
            }

            $results['result'][] = $formatted_array;
        }

        // Add other details
        $results['numberOfRecords'] = intval($xml_obj->children('http://www.loc.gov/zing/srw/', false)->numberOfRecords);

        // Encode to json for response to JavaScript
        $remote_data = json_encode($results);
    }else if(@$params['serviceType'] == 'bnflibrary_aut'){

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
                    $formatted_array['auturl'] = (string)$cf_ele[0];
                    break;
                }
            }

            foreach ($details->recordData->children('info:lc/xmlns/marcxchange-v2', false)->record->datafield as $key => $df_ele) { // datafield elements
                $df_tag = @$df_ele->attributes()['tag'];

                if(!$df_tag) {
                    continue;
                }

                if($df_tag == '100' || $df_tag == '110' || $df_tag == '167' || $df_tag == '170') { // Name

                    foreach($df_ele->subfield as $sub_key => $sf_ele) {

                        $sf_code = @$sf_ele->attributes()['code'];

                        if($df_tag == '170' && $sf_code == 'a') {
                            $formatted_array['name'] = (string)$sf_ele[0];
                            break;
                        }else if($df_tag == '100'){

                            if($sf_code == 'a'){ // Name
                                $formatted_array['name'] = (string)$sf_ele[0];
                            }else if($sf_code == 'm'){ // Name

                                if( array_key_exists('name', $formatted_array)){
                                    $formatted_array['name'] .= ', ' . (string)$sf_ele[0];
                                }else{
                                    $formatted_array['name'] = (string)$sf_ele[0];
                                }
                            }else if($sf_code == 'd'){ // Years active

                                if( array_key_exists('name', $formatted_array)){
                                    $formatted_array['name'] .= ' (' . (string)$sf_ele[0] . ')';
                                }else{
                                    $formatted_array['name'] = 'No Name Provided';
                                }
                            }
                        }else if($df_tag == '110'){

                            if($sf_code == 'a'){ // Name
                                $formatted_array['name'] = (string)$sf_ele[0];
                            }else if($sf_code == 'c'){ // Location

                                if( array_key_exists('name', $formatted_array)){
                                    $formatted_array['name'] .= ' (' . (string)$sf_ele[0] . ')';
                                }else{
                                    $formatted_array['name'] = 'No Name Provided';
                                }
                            }
                        }else if($df_tag == '167'){

                            if($sf_code == 'a'){ // Location
                                $formatted_array['name'] = (string)$sf_ele[0];
                            }else if($sf_code == 'm'){ // Dept

                                if( array_key_exists('name', $formatted_array)){
                                    $formatted_array['name'] .= ' (' . (string)$sf_ele[0] . ')';
                                }else{
                                    $formatted_array['name'] = 'No Name Provided';
                                    break;
                                }
                            }else if($sf_code == 'x'){ // Name

                                if( array_key_exists('name', $formatted_array)){
                                    $formatted_array['name'] .= ' - ' . (string)$sf_ele[0];
                                }else{
                                    $formatted_array['name'] = (string)$sf_ele[0];
                                }
                            }
                        } 
                    }

                    break;
                }
            }

            $results['result'][] = $formatted_array;
        }

        // Add other details, can be used for more calls to retrieve all results (currently retrieves 500 records at max)
        $results['numberOfRecords'] = intval($xml_obj->children('http://www.loc.gov/zing/srw/', false)->numberOfRecords);
        $results['nextStart'] = intval($xml_obj->children('http://www.loc.gov/zing/srw/', false)->nextRecordPosition);

        // Encode to json for response to JavaScript
        $remote_data = json_encode($results);
    }else if(@$params['serviceType'] == 'nomisma_rdf'){

        //error_log(print_r($remote_data, TRUE)); //DEBUGGING
		//$remote_data = json_encode($remote_data); // getRdf currently returns an error, so this isn't used
    }else if($is_estc){
        $remote_data = json_encode($remote_data);
    }else if(@$params['serviceType'] == 'nakala_search'){ // Retrieve basic details - Citation, ID, Name(s)

        $remote_data = json_decode($remote_data, TRUE);
        if(json_last_error() == JSON_ERROR_NONE){

            $results = array();
            if(array_key_exists('totalResults', $remote_data)){
                $results['count'] = $remote_data['totalResults'];
                $results['records'] = array();
    
                if($remote_data['totalResults'] > 0){
    
                    foreach ($remote_data['datas'] as $records) {

                        $id = @$records['identifier'];
                        $has_files = array_key_exists('files', $records);

                        // Set up basic details
                        $results['records'][$id]['url'] = 'https://nakala.fr/' . $id;
                        $results['records'][$id]['citation'] = @$records['citation'];
                        $results['records'][$id]['identifier'] = @$records['identifier'];

                        $results['records'][$id]['title'] = '';
                        $results['records'][$id]['mime_type'] = '';
                        $results['records'][$id]['author'] = array();
                        
                        foreach ($records['metas'] as $metadata) {

                            if($metadata['value'] == null){
                                continue;
                            }

                            if(strpos($metadata['propertyUri'], 'terms#creator') !== false){ // Author

                                if(array_key_exists('fullName', $metadata['value'])){
                                    $results['records'][$id]['author'][] = $metadata['value']['fullName'];
                                }else{
                                    $aut_name = '';
                                    if(array_key_exists('givenname', $metadata['value'])){
                                        $aut_name = $metadata['value']['givenname'];
                                    }
                                    if(array_key_exists('surname', $metadata['value'])){
                                        $aut_name .= $metadata['value']['surname'];
                                    }
                                    if($aut_name != ''){
                                        $results['records'][$id]['author'][] = $aut_name;
                                    }
                                }
                            }else if(strpos($metadata['propertyUri'], 'creator') !== false){ // Author
                                $results['records'][$id]['author'][] = $metadata['value'];
                            }else if(strpos($metadata['propertyUri'], 'terms#title') !== false){ // Title
                                $results['records'][$id]['title'] = $metadata['value'];
                            }else if(strpos($metadata['propertyUri'], 'terms#created') !== false){ // Created Date
                                $results['records'][$id]['date'] = $metadata['value'];
                            }else if(strpos($metadata['propertyUri'], 'terms#license') !== false){ // License
                                $results['records'][$id]['license'] = $metadata['value'];
                            }else if(strpos($metadata['propertyUri'], 'abstract') !== false){ // Abstract
                                $results['records'][$id]['abstract'] = $metadata['value'];
                            }
                        }

                        $need_title = $results['records'][$id]['title'] == '';
                        if($has_files){
                            foreach ($records['files'] as $idx => $file) {
                                if($need_title){ // Check for backup title, if necessary
                                    $results['records'][$id]['title'] .= $file['name'];
                                }
                                if(array_key_exists('mime_type', $file) && $results['records'][$id]['mime_type'] == ''){ // Type
                                    $results['records'][$id]['mime_type'] = $file['mime_type'];
                                }
                            }
                        }

                        $results['records'][$id]['title'] = trim($results['records'][$id]['title']);
                        if($results['records'][$id]['title'] == ''){ // Unknown title, just in case
                            $results['records'][$id]['title'] = 'Undetermined';
                        }
                    }
                }
            }

            $remote_data = json_encode($results);
        }else{
            $system->error_exit_api('Service did not return data in an handled format');
        }
    }

	// Return response
    header('Content-Type: application/json');
    //$json = json_encode($json);
    //header('Content-Type: application/vnd.geo+json');
    //header('Content-Disposition: attachment; filename=output.json');
    header('Content-Length: ' . strlen($remote_data));
    exit($remote_data);
?>