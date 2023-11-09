<?php

/**
* Lookup third party web service to return data to client side recordLookups
* It works as a proxy to avoid cross-origin issues
* 
* Currently supporting services:
* GeoName
* TLCMap
* BnF Library
* Nomisma
* Nakala
* Opentheso
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
    require_once dirname(__FILE__).'/../System.php';
    require_once dirname(__FILE__).'/../dbaccess/utils_db.php';

    // allowed and handled services, 'serviceType' => 'service/url base'
    $services_details = array(
        'tlcmap' => array(
            'https://tlcmap.org/ghap/search?', 
            'https://tlcmap.australiasoutheast.cloudapp.azure.com/ws/ghap/search?'
        ), 
        'geonames' => 'http://api.geonames.org/', 

        'bnflibrary_bib' => 'https://catalogue.bnf.fr/api/SRU?',
        'bnflibrary_aut' => 'https://catalogue.bnf.fr/api/SRU?',
        'bnf_recdump' => 'https://catalogue.bnf.fr/api/SRU?',

        'nomisma' => array(
            'https://nomisma.org/apis/', 
            'https://nomisma.org/feed/?q='
        ), 

        'nakala' => array(
            'https://api.nakala.fr/search?q=', 
            'nakala_get_metadata'
        ), 
        'nakala_author' => 'https://api.nakala.fr/authors/search?q=',

        'opentheso' => array(
            'pactols' => 'https://pactols.frantiq.fr/opentheso/openapi/v1/',
            'huma-num' => 'https://opentheso.huma-num.fr/opentheso/openapi/v1/',
            'other' => 'opentheso_get_thesauruses'
        ), 

        'ESTC' => array(
            'db' => 'ESTC_Helsinki_Bibliographic_Metadata',
            'action' => 'import_records' // 'record_output'
        )
    );

    $is_estc = false;
    $valid_service = false;
    if(!empty(@$_REQUEST['serviceType']) && array_key_exists($_REQUEST['serviceType'], $services_details)){

        $service_type = $_REQUEST['serviceType'];
        $srv_details = $services_details[$service_type];

        $url = empty(@$_REQUEST['service']) ? '' : $_REQUEST['service'];

        if($service_type == 'ESTC'){
            $action = @$_REQUEST['action'];
            $valid_service = @$_REQUEST['db'] == $srv_details['db'] || $action == $srv_details['action'];
            $is_estc = $valid_service;
        }else if(!is_array($srv_details)){
            $valid_service = strpos($srv_details, $url) == 0;
        }else if(in_array($url, $srv_details)){
            $valid_service = true;
        }else{
            foreach ($srv_details as $srv_base) {
                if($url == $srv_base || strpos($srv_base, $url) == 0){
                    $valid_service = true;
                    break;
                }
            }
        }
    }

    $response = array();

    $system = new System();

    $params = $_REQUEST;

    $is_debug = (@$params['dbg']==1);

    if(!$valid_service){
        $system->error_exit_api('The provided look up details are invalid', HEURIST_INVALID_REQUEST); //exit from script
    }

    $remote_data = false;
    $url = '';
    
    if($is_estc){

        
        $is_allowed = (isset($ESTC_PermittedDBs) && strpos($ESTC_PermittedDBs, @$_REQUEST['org_db']) !== false && isset($ESTC_UserName) && isset($ESTC_Password));
        $def_err_msg = 'For licensing reasons this function is only accessible to authorised projects.<br>Please contact the Heurist team if you wish to use this.';

        if(strpos(strtolower(HEURIST_BASE_URL), strtolower(HEURIST_MAIN_SERVER)) !== false){ // currently on server where ESTC DB is located

            if(array_key_exists('entity', $params)){ // retrieve entity info (term lookup)
                require_once dirname(__FILE__).'/entityScrud.php';
                exit;
            }

            $is_inited = $system->init(@$params['db']);

            if($is_inited !== false && $is_allowed){ // search records

                $is_logged_in = $system->doLogin($ESTC_UserName, $ESTC_Password, 'shared');

                if($is_logged_in){ // logged in, begin search
                    $system->getCurrentUserAndSysInfo(false);
                    
                    if(array_key_exists('action', $params)){ // import record for LRC18C lookup
    
                        if($params['action'] == 'import_records'){ // perform standard record import action, user on ESTC server
                            require_once dirname(__FILE__).'/importController.php';
                            exit;
                        }else if($params['action'] == 'record_output'){ // retrieve record from record_output, user on external server
                            require_once dirname(__FILE__).'/record_output.php';
                            exit;
                        }
                    }
    
                    require_once dirname(__FILE__).'/../records/search/recordSearch.php';

                    $response = recordSearch($system, $params);
                }else{ // unable to login, cannot access records
                    $response = array('status' => HEURIST_ERROR, 'message' => 'We are unable to access the records within the ESTC database at this moment.<br>Please contact the Heurist team. Query is: '.json_encode($params['q']));
                }
            }else{ // cannot access ESTC DB
                $response = $is_allowed ? $system->getError() : array('status' => HEURIST_REQUEST_DENIED, 'message' => $def_err_msg);
            }
        }else if(isset($ESTC_ServerURL)){ // external server
            //change hsapi to hserv when master index will be v7
            $base_url = $ESTC_ServerURL
                .(strpos($ESTC_ServerURL,'/h6-alpha')>0?'hserv':'hsapi')
                .'/controller/record_lookup.php?';

            if(array_key_exists('action', $params) && @$params['action'] == 'import_records'){
                
                $base_url = $ESTC_ServerURL 
                    .(strpos($ESTC_ServerURL,'/h6-alpha')>0?'hserv':'hsapi')
                    .'/controller/record_lookup.php?';  //record_output
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

                $is_inited = $system->init(@$params['db']);
                
                // save file that produced with record_output.php from source to temp file  
                $heurist_path = tempnam(HEURIST_SCRATCH_DIR, "_temp_");
                
                $filesize = saveURLasFile($url, $heurist_path); // perform external request and save results to temp file

                if($filesize>0 && file_exists($heurist_path)){
                    //read temp file, import record
              
                    require_once dirname(__FILE__).'/../records/import/importHeurist.php'; 
                    
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
            $response = array('status' => HEURIST_REQUEST_DENIED, 'message' => $def_err_msg);
        }

        $response = json_encode($response);

        header('Content-Type: application/json');
        header('Content-Length: ' . strlen($response));
        exit($response);
    }

    if( !$system->init(@$params['db']) ){  //@todo - we don't need db connection here - it is enough check the session
        //get error and response
        $system->error_exit_api(); //exit from script
    }else if ( $system->get_user_id()<1 ) {
        $system->error_exit_api('You must be logged in to use the external lookup services', HEURIST_REQUEST_DENIED); 
        //$response = $system->addError(HEURIST_REQUEST_DENIED);
    }

    $system->dbclose();

    if(@$params['service'] == 'nakala_get_metadata' || @$params['service'] == 'opentheso_get_thesauruses'){

        $response = array();
        if($service_type == 'opentheso'){
            $response = getOpenthesoThesauruses($system);
        }else if($service_type == 'nakala'){
            $response = getNakalaMetadata($system, @$params['type']);
        }
        $response = json_encode($response);

        header('Content-Type: application/json');
        header('Content-Length: ' . strlen($response));
        exit($response);
    }

    // Perform external lookup / API request
    $url = $params['service'];

    $remote_data = loadRemoteURLContentWithRange($url, null, true, 30);
    if($remote_data===false){

        global $glb_curl_error;
        $error_code = (!empty($glb_curl_error)) ? $glb_curl_error : 'Error code: 500 Heurist Error';

        if(strpos($glb_curl_error, '404') !== false && @$params['serviceType'] == 'nomisma'){ // No result for Nomisma returns a 404 error
            $remote_data = json_encode(array()); // return empty array
        }else{
            $system->error_exit_api('<br>Cannot connect/load data from the service: '.$url.'<br>'.$error_code, HEURIST_ERROR);
        }
    }

    if(@$params['serviceType'] == 'geonames' || @$params['serviceType'] == 'tlcmap'){ // GeoName and TLCMap lookups

        json_decode($remote_data);
        if(json_last_error() == JSON_ERROR_NONE){
        }else if(@$_REQUEST['is_XML'] == 1){
            // XML to JSON (no attributes/namespace handling required)
            $xml_obj = simplexml_load_string($remote_data, null, LIBXML_PARSEHUGE);
            $remote_data = json_encode($xml_obj);
        }else{

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

        $author_codes = '';
        //$contributor_codes = '';
        if(array_key_exists('author_codes', $params) && !empty($params['author_codes']) && $params['author_codes'] != 'all'){
            $author_codes = explode(',', $params['author_codes']);
        }
        /*if(array_key_exists('contributor_codes', $params) && !empty($params['contributor_codes'])){
            $contributor_codes = explode(',', $params['contributor_codes']);
        }*/

        $results = array();
        
        // Create xml object
        $xml_obj = simplexml_load_string($remote_data, null, LIBXML_PARSEHUGE);
        // xml namespace urls: https://www.loc.gov/zing/srw/ (srw), info:lc/xmlns/marcxchange-v2 (mxc)

        // Retrieve records from results
        $records = $xml_obj->children('https://www.loc.gov/zing/srw/', false)->records->record;

        // Move each result's details into seperate array
        foreach ($records as $key => $details) {

            $formatted_array = array();

            $aut_idx = 0;
            $pub_idx = 0;
            $id = '';

            foreach ($details->recordData->children('info:lc/xmlns/marcxchange-v2', false)->record->controlfield as $key => $cf_ele) { // controlfield elements
                $cf_tag = @$cf_ele->attributes()['tag'];

                if($cf_tag == '001') { // BnF ID
                    $formatted_array['BnF_ID'] = (string)$cf_ele[0];
                    $id = (string)$cf_ele[0];
                }else if($cf_tag == '003') { // Record URL
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
                    $is_valid = false;
                    $name = array();
                    $location = array();

                    foreach ($df_ele->subfield as $sub_key => $sf_ele) { // TODO - look for examples of sf_code == {b, r}
                        $sf_code = @$sf_ele->attributes()['code'];

                        $str_val = str_replace(array('[', ']'), '', (string)$sf_ele[0]);

                        if($sf_code == 'a'){ // publisher location
                            $location[] = $str_val;
                        }else if($sf_code == 'c'){ // publisher name
                            $name[] = $str_val;
                        }else if($sf_code == 'd'){
                            $formatted_array['date'][] = $str_val;
                        }else if($sf_code == 's'){
                            $formatted_array['publisher'][$pub_idx] = $str_val;
                            break;
                        }
                    }

                    if(!empty($location)){
                        $formatted_array['publisher'][$pub_idx]['location'] = '[' . implode(' ; ', $location) . ']';
                    }
                    if(!empty($name)){
                        $formatted_array['publisher'][$pub_idx]['name'] = implode(', ', $name);
                    }

                    $pub_idx ++;
                }else if($df_tag == '700' || $df_tag == '701' || $df_tag == '702' || $df_tag == '710' || $df_tag == '712' || $df_tag == '716') { // Creator [Author|Contributor]

                    /**
                     * 3 => ID
                     * a => Surname | Name
                     * b => Given name
                     * f => Years active
                     * 4 => Role Code
                     */

                    $value = '';
                    $id = $aut_idx;
                    $role = null;

                    $author = array();

                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {

                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == '3'){
                            $id = (string)$sf_ele[0];
                            $author['id'] = $id;
                            continue;
                        }else if($sf_code == '4'){
                            $role = (string)$sf_ele[0];
                            $author['role'] = $role;
                            continue;
                        }

                        switch ($df_tag) {
                            case '700':
                            case '701':
                            case '702':
                            case '703':

                                if($sf_code == 'a') { // Surname
                                    $author['surname'] = (string)$sf_ele[0];
                                }else if($sf_code == 'b') { // Given name
                                    $author['firstname'] = (string)$sf_ele[0];
                                }else if($sf_code == 'f') { // Years active
                                    $author['active'] = (string)$sf_ele[0];
                                }

                                break;
                            
                            case '710':
                            case '711':
                            case '712':
                            case '713':

                                if($sf_code == 'c') { // Date // $sf_code == 'f' Location
                                    $author['active'] = '(' . (string)$sf_ele[0] . ')';
                                }else if($sf_code == 'b'){ // Sub unit name
                                    $author['surname'] = (string)$sf_ele[0];
                                }else if($sf_code == 'a') { // Main name
                                    $author['firstname'] = (string)$sf_ele[0];
                                }

                                break;

                            case '720':
                            case '721':
                            case '722':
                            case '723':

                                if($sf_code == 'a') { // Name
                                    $author['name'] = (string)$sf_ele[0];
                                }
 
                                break;
 
                            default:
                                break;
                        }
                    }

                    if(!empty($author)){
                        if(isset($role) && !empty($role) && !empty($author_codes)){ // role code found

                            if(in_array($role, $author_codes)){
                                $formatted_array['author'][$id] = $author;
                            }else{
                                $formatted_array['contributor'][$id] = $author;
                            }
                        }else{ // by default, set as author
                            $formatted_array['author'][$id] = $author;
                        }

                        $aut_idx ++;
                    }
                    
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
                        $formatted_array['description'][] = $value;
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
                            $formatted_array['ext_description'][] = (string)$sf_ele[0];
                        }
                    }
                }
            }

            $results['result'][] = $formatted_array;
        }

        // Add other details
        $results['numberOfRecords'] = intval($xml_obj->children('https://www.loc.gov/zing/srw/', false)->numberOfRecords);

        // Encode to json for response to JavaScript
        $remote_data = json_encode($results);
    }else if(@$params['serviceType'] == 'bnflibrary_aut'){

        $results = array();
        
        // Create xml object
        $xml_obj = simplexml_load_string($remote_data, null, LIBXML_PARSEHUGE);
        // xml namespace urls: https://www.loc.gov/zing/srw/ (srw), info:lc/xmlns/marcxchange-v2 (mxc)

        // Retrieve records from results
        $records = $xml_obj->children('https://www.loc.gov/zing/srw/', false)->records->record;

        $df_handled = array(200, 210, 240, 230, 215, 216, 250, 220);

        // Move each result's details into seperate array
        foreach ($records as $key => $details) {

            $formatted_array = array();

            foreach ($details->recordData->children('info:lc/xmlns/marcxchange-v2', false)->record->controlfield as $key => $cf_ele) { // controlfield elements
                $cf_tag = @$cf_ele->attributes()['tag'];

                if($cf_tag == '001') { // BnF ID
                    $formatted_array['BnF_ID'] = (string)$cf_ele[0];
                }else if($cf_tag == '003') { // Record URL
                    $formatted_array['auturl'] = (string)$cf_ele[0];
                    break;
                }
            }

            foreach ($details->recordData->children('info:lc/xmlns/marcxchange-v2', false)->record->datafield as $key => $df_ele) { // datafield elements
                $df_tag = @$df_ele->attributes()['tag'];

                if(!$df_tag) {
                    continue;
                }

                if(in_array($df_tag, $df_handled)) {

                    foreach($df_ele->subfield as $sub_key => $sf_ele) {

                        $sf_code = @$sf_ele->attributes()['code'];

                        switch ($df_tag) {
                            case '200': // Person

                                if($sf_code == 'a'){ // Surname
                                    $formatted_array['name'] = (string)$sf_ele[0];
                                }else if($sf_code == 'b'){ // First name
    
                                    if( array_key_exists('name', $formatted_array)){
                                        $formatted_array['name'] .= ', ' . (string)$sf_ele[0];
                                    }else{
                                        $formatted_array['name'] = (string)$sf_ele[0];
                                    }
                                }else if($sf_code == 'f' || $sf_code == 'd'){ // Years active
                                    $formatted_array['years_active'] = (string)$sf_ele[0];
                                }else if($sf_code == 'c'){ // Role/Job
                                    $formatted_array['role'] = (string)$sf_ele[0];
                                }

                                break;

                            case '210': // Collective

                                if($sf_code == 'a'){ // Name
                                    $formatted_array['name'] = (string)$sf_ele[0];
                                }else if($sf_code == 'c'){ // Location
                                    $formatted_array['location'] = (string)$sf_ele[0];    
                                }else if($sf_code == 'b'){ // Type
                                    $formatted_array['role'] = (string)$sf_ele[0];
                                }

                                break;

                            case '240': // Conventional Title

                                if($sf_code == 'a'){ // Name
                                    $formatted_array['name'] = (string)$sf_ele[0];
                                }else if($sf_code == 't'){ // title
                                    
                                    if( array_key_exists('name', $formatted_array)){
                                        $formatted_array['name'] .= ' [' . (string)$sf_ele[0] . ']';
                                    }else{
                                        $formatted_array['name'] = (string)$sf_ele[0];
                                    }
                                }

                                break;
                            
                            case '230': // MISSING - $b $h $k $m

                                if($sf_code != 'a' && $sf_code != 'i'){
                                    break;
                                }

                                // Name
                                if( array_key_exists('name', $formatted_array)){
                                    $formatted_array['name'] .= ' . ' . (string)$sf_ele[0];
                                }else{
                                    $formatted_array['name'] = (string)$sf_ele[0];
                                }

                                break;

                            case '215': // Geographical name

                                if($sf_code != 'a'){ // && $sf_code != 'x'
                                    break;
                                }

                                // Name
                                if( array_key_exists('name', $formatted_array)){
                                    $formatted_array['name'] .= ' ' . (string)$sf_ele[0];
                                }else{
                                    $formatted_array['name'] = (string)$sf_ele[0];
                                }

                                break;

                            case '216': // Marque
                            case '250': // 

                                if($sf_code != 'a'){
                                    break;
                                }

                                // Name
                                if( array_key_exists('name', $formatted_array)){
                                    $formatted_array['name'] .= ' ' . (string)$sf_ele[0];
                                }else{
                                    $formatted_array['name'] = (string)$sf_ele[0];
                                }

                                break;

                            case '220': // Family

                                if($sf_code == 'a'){ // Name
                                    $formatted_array['name'] = (string)$sf_ele[0];
                                }else if($sf_code == 'c'){ // Type
                                    $formatted_array['role'] = (string)$sf_ele[0];
                                }

                                break;

                            default:
                                break;
                        }
                    }

                    if(array_key_exists('name', $formatted_array) && !empty($formatted_array['name'])){ // add authority type
                        $formatted_array['authority_type'] = (string)$df_tag[0];
                    }

                    break;
                }
            }

            if(count($formatted_array) > 0 && array_key_exists('name', $formatted_array) && !empty($formatted_array['name'])){
                $results['result'][] = $formatted_array;
            }
        }

        // Add other details, can be used for more calls to retrieve all results (currently retrieves 500 records at max)
        $results['numberOfRecords'] = intval($xml_obj->children('https://www.loc.gov/zing/srw/', false)->numberOfRecords);
        $results['nextStart'] = intval($xml_obj->children('https://www.loc.gov/zing/srw/', false)->nextRecordPosition);

        // Encode to json for response to JavaScript
        $remote_data = json_encode($results);
    }else if(@$params['serviceType'] == 'bnf_recdump'){
        $results = array();
        
        // Create xml object
        $xml_obj = simplexml_load_string($remote_data, null, LIBXML_PARSEHUGE);
        // xml namespace urls: https://www.loc.gov/zing/srw/ (srw), info:lc/xmlns/marcxchange-v2 (mxc)

        // Retrieve records from results
        $records = $xml_obj->children('https://www.loc.gov/zing/srw/', false)->records->record;

        foreach($records as $key => $details){
            $record = $details->recordData->children('info:lc/xmlns/marcxchange-v2', false)->record;
            $results['record'] = $record->asXML();//json_encode($record, JSON_PRETTY_PRINT);
            break;
        }

        $remote_data = json_encode($results);

    }else if(@$params['serviceType'] == 'nomisma' && @$params['search_type'] == 'xml'){

		$xml_obj = new SimpleXMLElement($remote_data, LIBXML_PARSEHUGE);

        $results = array();

        $idx = 0;
        foreach ($xml_obj->children()->entry as $record) {

            $id = (string)$record->id[0];
            $link = @$record->link;
            if(empty($link) || $link->attributes()['rel'] != 'canonical'){
                $link = 'https://nomisma.org/id/' . $id;
            }else{
                $link = (string)$link->attributes()['href'];
            }

            $results[$idx] = array('rec_ID' => $id, 'rec_Title' => (string)$record->title[0], 'rec_ScratchPad' => (string)$record->summary[0], 'rec_URL' => $link);
            $idx ++;
        }

        $remote_data = json_encode(array("status" => HEURIST_OK, "entry" => $results));

    }else if(@$params['serviceType'] == 'nakala'){ // Retrieve basic details - Citation, ID, Name(s)

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

                        if($has_files){ // datas, files
                            $results['records'][$id]['rec_url'] = 'https://nakala.fr/' . $id;
                        }else{ // collection, should be filtered out by request - just in case
                            continue;
                        }

                        $results['records'][$id]['citation'] = @$records['citation'];
                        $results['records'][$id]['identifier'] = @$records['identifier'];

                        $results['records'][$id]['title'] = array();
                        $results['records'][$id]['mime_type'] = array();
                        $results['records'][$id]['url'] = array();
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
                                $results['records'][$id]['title'][] = $metadata['value'];
                            }else if(strpos($metadata['propertyUri'], 'terms#created') !== false){ // Created Date
                                $results['records'][$id]['date'] = $metadata['value'];
                            }else if(strpos($metadata['propertyUri'], 'terms#license') !== false){ // License
                                $results['records'][$id]['license'] = $metadata['value'];
                            }else if(strpos($metadata['propertyUri'], 'abstract') !== false){ // Abstract
                                $results['records'][$id]['abstract'] = $metadata['value'];
                            }
                        }

                        if($has_files){
                            foreach ($records['files'] as $idx => $file) {
                                if(array_key_exists('name', $file)){ // Name
                                    $results['records'][$id]['filename'][] = $file['name'];
                                }
                                if(array_key_exists('mime_type', $file)){ // Type
                                    $results['records'][$id]['mime_type'][] = $file['mime_type'];
                                }
                                if(array_key_exists('sha1', $file)){ // File URI
                                    $results['records'][$id]['url'][] = 'https://api.nakala.fr/data/' . $id . '/' . $file['sha1'];
                                }
                            }
                        }

                        if(count($results['records'][$id]['title']) == 0){
                            $results['records'][$id]['title'] = 'Undetermined';
                        }
                        if(count($results['records'][$id]['author']) == 0){
                            $results['records'][$id]['author'] = 'Anonymous';
                        }
                    }
                }
            }

            $remote_data = json_encode($results);
        }else{
            $system->error_exit_api('Service did not return data in an handled format');
        }
    }else if(@$params['serviceType'] == 'opentheso'){
        
        $def_lang = $system->user_GetPreference('layout_language', 'en');

        $remote_data = json_decode($remote_data, TRUE);

        $type_idx = 'https://www.w3.org/1999/02/22-rdf-syntax-ns#type';
        $trm_uri = 'https://www.w3.org/2004/02/skos/core#Concept';
        $desc_idx = 'https://www.w3.org/2004/02/skos/core#definition';
        $code_idx = 'https://purl.org/dc/terms/identifier';
        $label_idx = 'https://www.w3.org/2004/02/skos/core#prefLabel';

        $parent_idx = 'https://www.w3.org/2004/02/skos/core#broader';
        $child_idx = 'https://www.w3.org/2004/02/skos/core#narrower';
        $sibling_idx = 'https://www.w3.org/2004/02/skos/core#related';

        $results = array();

        if(json_last_error() === JSON_ERROR_NONE){

            foreach ($remote_data as $uri => $details) {
                if($details[$type_idx][0]['value'] != $trm_uri){ // not a term
                    continue;
                }

                $label = '';
                $desc = '';
                $code = '';
                $translated_labels = array();

                if(array_key_exists($desc_idx, $details)){
                    $desc = $details[$desc_idx][0]['value'];
                }
                if(array_key_exists($code_idx, $details)){
                    $code = $details[$code_idx][0]['value'];
                }
                $code_parts = explode('/', $uri);
                $code .= (!empty($code) ? ' ; ' : '') . implode('/', array_splice($code_parts, -2, 2));

                foreach ($details[$label_idx] as $label_details) {
                    if($label_details['lang'] == $def_lang){
                        $label = $label_details['value'];
                        continue;
                    }
                    if(empty($label) && ($label_details['lang'] == 'fr' || $label_details['lang'] == 'en')){
                        $label = $label_details['value'];
                    }

                    $lang_code = getLangCode3($label_details['lang']);
                    $translated_labels[$lang_code] = $lang_code . ":" . $label_details['value']; // LANG_CODE:Value
                }

                array_push($results, array('label' => $label, 'desc' => $desc, 'code' => $code, 'uri' => $uri, 'translations' => $translated_labels));
            }
        }else{
            $system->error_exit_api('An error occurred while attempting to process the search results from Opentheso', HEURIST_UNKNOWN_ERROR);
        }

        $remote_data = json_encode($results);
    }

	// Return response
    header('Content-Type: application/json');
    header('Content-Length: ' . strlen($remote_data));

    exit($remote_data);

    function getOpenthesoThesauruses($system){

        $opentheso_file = HEURIST_FILESTORE_ROOT . 'OPENTHESO_thesauruses.json';

        $data = array();
        $fd = fileOpen($opentheso_file);

        if($fd <= 0){ // create new file
            $data = updateOpenthesoThesauruses($system);
        }else{

            fclose($fd);
            $data = file_get_contents($opentheso_file, filesize($opentheso_file));
            $already_updated = false;

            if(!$data || empty($data)){
                $data = updateOpenthesoThesauruses($system);
                $already_updated = true;
            }else{
                $data = json_decode($data, TRUE);
            }

            if(json_last_error() !== JSON_ERROR_NONE || !is_array($data) || $data['last_update'] < date('Y-m-d')){
                if($already_updated){
                    $system->error_exit_api('Unable to retrieve Opentheso thesauruses due to unknown error.');
                    exit;
                }
                $data = updateOpenthesoThesauruses($system);
            }
        }

        if(!$data || !is_array($data) || count($data) == 0){
            return array();
        }else{
            unset($data['last_update']);
            return $data;
        }
    }
    function updateOpenthesoThesauruses($system){

        global $services_details;

        // update OPENTHESO_thesauruses.json
        $opentheso_file = HEURIST_FILESTORE_ROOT . 'OPENTHESO_thesauruses.json';

        $data_rtn = array('pactols' => array(), 'huma-num' => array());

        foreach ($services_details['opentheso'] as $server => $base_uri) {

            if($server == 'other'){
                continue;
            }

            $thesauruses = loadRemoteURLContentWithRange($base_uri.'thesaurus', null, true, 60);

            if($thesauruses === false){
                $system->error_exit_api('Unable to retrieve the available thesauruses from ' . $server);
                exit;
            }

            $thesauruses = json_decode($thesauruses, TRUE);

            if(json_last_error() !== JSON_ERROR_NONE){
                $system->error_exit_api('An error occurred while handling a response from an Opentheso server', HEURIST_UNKNOWN_ERROR);
            }
            foreach ($thesauruses as $thesaurus) {

                $key = $thesaurus['idTheso'];
                $label = '';

                foreach($thesaurus['labels'] as $translated_label){
                    $lang = $translated_label['lang'];

                    if(strpos($translated_label['title'], '_'.$lang) === false){
                        $label = $translated_label['title'];
                        break;
                    }
                    if(empty($label)){
                        $label = $translated_label['title'];
                    }
                }

                $data_rtn[$server][$key] = $label;
            }
        }

        $data_rtn['last_update'] = date('Y-m-d');

        $file_size = fileSave(json_encode($data_rtn), $opentheso_file);
        if($file_size <= 0){
            $system->error_exit_api('Cannot save Opentheso thesaurus list into local file store');
            exit;
        }else{
            return $data_rtn;
        }
    }

    function getNakalaMetadata($system, $type){
        // check NAKALA_metadata_values.json
        // if date in file is old (data.last_update), update metadata first (all types)
        // then return data.types

        $type = empty($type) ? 'all' : $type;
        $nakala_file = HEURIST_FILESTORE_ROOT . 'NAKALA_metadata_values.json';
        $data = array();
        $fd = fileOpen($nakala_file);

        if($fd <= 0){ // create new file
            $data = updateNakalaMetadata($system);
        }else{ // read from file

            fclose($fd);
            $data = file_get_contents($nakala_file, filesize($nakala_file));
            $already_updated = false;

            if(!$data || empty($data)){
                $data = updateNakalaMetadata($system);
                $already_updated = true;
            }else{
                $data = json_decode($data, TRUE);
            }

            if(json_last_error() !== JSON_ERROR_NONE || !is_array($data) || $data['last_update'] < date('Y-m-d')){
                if($already_updated){
                    $system->error_exit_api('Unable to retrieve Nakala metadata due to unknown error.');
                    exit;
                }
                $data = updateNakalaMetadata($system);
            }
        }

        if(!$data || !is_array($data) || count($data) == 0){
            return array();
        }

        $data_rtn = array();
        if($type == 'types'){
            $data_rtn = $data['types'];
        }
        if($type == 'licenses'){
            $data_rtn = $data['licenses'];
        }
        if($type == 'years'){
            $data_rtn = $data['years'];
        }

        if(empty($data_rtn) || count($data_rtn) == 0){ // all
            return $data;
        }else{
            return $data_rtn;
        }
    }

    function updateNakalaMetadata($system){
        // update NAKALA_metadata_values.json
        $nakala_file = HEURIST_FILESTORE_ROOT . 'NAKALA_metadata_values.json';

        $page = 1;
        $totalPages = 10;
        $data_rtn = array('years' => array(), 'licenses' => array(), 'types' => array());

        // Get datatype names, Nakala only provides the datatype ids
        $datatypes_xml = loadRemoteURLContentWithRange('https://vocabularies.coar-repositories.org/resource_types/resource_types_for_dspace.xml', null, true, 60);
        $datatypes_xml = simplexml_load_string($datatypes_xml, null, LIBXML_PARSEHUGE);

        while ($page <= $totalPages) {

            $results = loadRemoteURLContentWithRange('https://api.nakala.fr/search?fq=scope%3Ddata&order=relevance&page='.$page.'&size=1000', null, true, 60);

            if($results === false){
                $system->error_exit_api('Unable to retrieve metadata values from Nakala');
                exit;
            }

            $results = json_decode($results, TRUE);
            if(json_last_error() == JSON_ERROR_NONE){

                if($results['totalResults'] > 0 && count($results['datas']) > 0){

                    foreach ($results['datas'] as $records) {

                        $handled_type = false;
                        $handled_license = false;
                        $handled_year = false;
                        
                        foreach ($records['metas'] as $metadata) {

                            if($metadata['value'] == null){
                                continue;
                            }

                            if(strpos($metadata['propertyUri'], 'terms#created') !== false){ // Created Date

                                if(strpos($metadata['value'], '-') !== false){ // YYYY-MM | YYYY-MM-DD
                                    $metadata['value'] = explode('-', $metadata['value'])[0];
                                }

                                if(!in_array($metadata['value'], $data_rtn['years'])){
                                    $data_rtn['years'][] = $metadata['value'];
                                }

                                $handled_year = true;
                            }else if(strpos($metadata['propertyUri'], 'terms#license') !== false && !in_array($metadata['value'], $data_rtn['licenses'])){ // License
                                $data_rtn['licenses'][] = $metadata['value'];
                                $handled_license = true;
                            }else if(strpos($metadata['propertyUri'], 'terms#type') !== false){ // Type

                                //Retrieve type name from XML object
                                $code = explode('/', $metadata['value']);
                                $code = $code[count($code) - 1];
                                $label = $code;

                                if(strpos($code, 'c_') === false){
                                    $code = $metadata['value'];
                                }

                                if(!array_key_exists($code, $data_rtn['types'])){

                                    if(strpos($code, 'c_') !== false){

                                        $nodes = $datatypes_xml->xpath("//node[@id='". $code ."']");
                                        if(is_array($nodes) && count($nodes) > 0){
                                            $label = $nodes[0]->attributes()->label;
                                            $label = ucfirst($label);
                                        }else{
                                            $label .= ' (deprecated type)';
                                        }
                                    }
    
                                    $data_rtn['types'][$code] = $label;
                                }

                                $handled_type = true;
                            }

                            if($handled_year && $handled_type && $handled_license){ // handled all necessary metadata for current record
                                break;
                            }
                        }
                    }
                }else{ // no more records
                    break;
                }
            }else{
                $system->error_exit_api('Unable to retrieve metadata values from Nakala, receieved a response not in a JSON format');
                exit;
            }

            $page ++;
        }

        if(count($data_rtn['years']) > 0){ // sort years
            sort($data_rtn['years']);
        }
        if(count($data_rtn['types']) > 0){
            sort($data_rtn['types']);
        }
        if(count($data_rtn['licenses']) > 0){
            sort($data_rtn['licenses']);
        }

        $data_rtn['last_update'] = date('Y-m-d');

        $file_size = fileSave(json_encode($data_rtn), $nakala_file);
        if($file_size <= 0){
            $system->error_exit_api('Cannot save Nakala metadata into local file store');
            exit;
        }else{
            return $data_rtn;
        }
    }
?>