<?php

/**
* LookupController.php - Handler for lookup third party web services
* 
* Lookup third party web services to return data to client side recordLookups
* It works as a proxy to avoid cross-origin issues
*
* Currently supporting services:
* GeoName
* TLCMap
* BnF Library
* Nomisma
* Nakala
* Opentheso
* Wikidata SPARQL
*
* @project     Heurist academic knowledge management system
* @package Controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay   <blmckay13@gmail.com>
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/

namespace hserv\controller;

use hserv\System;
use hserv\utilities\USanitize;

use function is_array;
use function array_key_exists;
use function in_array;
use function intval;
use function count;
use function defined;
use function define;
use function is_bool;

require_once dirname(__FILE__).'/../../autoload.php';

// BnF Constants
define('BNF_BASE_URL', 'https://catalogue.bnf.fr/api/SRU?');
define('BNF_XML_RECORDS_NAMESPACE', 'http' . '://www.loc.gov/zing/srw/'); // srw
define('BNF_XML_DETAILS_NAMESPACE', 'info:lc/xmlns/marcxchange-v2'); // mxc

define('NUMERIC', 'number');
define('ALPHANUMERIC', 'mixed');

define('ESTC_ERROR_MSG', 'For licensing reasons this function is only accessible to authorised projects.<br>Please contact the Heurist team if you wish to use this.');

class LookupController{

    private System $system;
    private string $database = '';
    private array $request = [];

    private string $lookupType = '';
    private string $lookupURL = '';
    private string $lookupMetadata = '';
    private string $lookupAction = '';
    private array $lookupHeaders = [];
    private int $lookupTimeout = 30;
    private $lookupResponse = null;

    private bool $isValid = false;
    private bool $isESTC = false;
    private bool $isMetadata = false;
    private bool $isDebug = false;

    private array $serviceURLs = [
        'tlcmap' => [
            'https://tlcmap.org/ghap/search?',
            'https://tlcmap.australiasoutheast.cloudapp.azure.com/ws/ghap/search?'
        ],

        'geonames' => 'http' . '://api.geonames.org/',

        'bnflibrary_bib' => BNF_BASE_URL,
        'bnflibrary_aut' => BNF_BASE_URL,
        'bnf_recdump' => BNF_BASE_URL,

        'nomisma' => [
            'https://nomisma.org/apis/',
            'https://nomisma.org/feed/?'
        ],

        'nakala' => 'https://api.nakala.fr/search?',

        'nakala_author' => 'https://api.nakala.fr/authors/search?',

        'opentheso' => [
            'pactols' => 'https://pactols.frantiq.fr/opentheso/openapi/v1/',
            'huma-num' => 'https://opentheso.huma-num.fr/opentheso/openapi/v1/'
        ],

        'ESTC' => [
            'db' => 'ESTC_Helsinki_Bibliographic_Metadata',
            'action' => 'import_records' // 'record_output'
        ],

        'wikidata_SPARQL' => 'https://query.wikidata.org/sparql?'
    ];

    private const SERVICE_PARAMETERS = [ // array
        'tlcmap' => [
            'name' => ALPHANUMERIC,
            'fuzzyname' => ALPHANUMERIC,
            'anps_id' => ALPHANUMERIC,
            'lga' => ALPHANUMERIC,
            'state' => ALPHANUMERIC
        ],

        'geonames' => [
            'q' => ALPHANUMERIC,
            'country' => ALPHANUMERIC,
            'postalcode' => ALPHANUMERIC,
            'placename' => ALPHANUMERIC,
            'maxRows' => NUMERIC,
            'geonameId' => ALPHANUMERIC
        ],

        'bnf' => [
            'query' => ALPHANUMERIC,
            'maximumRecords' => NUMERIC
        ],

        'nomisma' => [
            'id' => ALPHANUMERIC
        ],

        'nakala' => [
            'fq' => ALPHANUMERIC,
            'q' => ALPHANUMERIC,
            'size' => NUMERIC
        ],

        'opentheso' => [
            'q' => ALPHANUMERIC,
            'lang' => ALPHANUMERIC,
            'group' => ALPHANUMERIC
        ],

        'wikidata_SPARQL' => [
            'query' => ALPHANUMERIC
        ]
    ];

    private string $nakalaFile = '';
    private string $openthesoFile = '';

    private string $ESTCMsg = 'For licensing reasons this function is only accessible to authorised projects.<br>Please contact the Heurist team if you wish to use this.';

    public function __construct(System $system, array $request){

        global $OPENTHESO_SERVERS;

        $this->request = $request;
        $this->system = $system;

        // Check if more servers have been defined within heuristConfigIni
        if(!empty($OPENTHESO_SERVERS) && is_array($OPENTHESO_SERVERS)){
            $this->serviceURLs['opentheso'] = array_merge($this->serviceURLs['opentheso'], $OPENTHESO_SERVERS);
        }
    }

    public function init() : bool{
        return $this->setupRequest() && $this->setupSystem() && $this->verifyRequestParameters();
    }

    private function setupSystem() : bool{

        if($this->system->getUserId() < 1){
            $this->system->addError(HEURIST_REQUEST_DENIED, 'You must be logged in to use the external lookup services');
            return false;
        }elseif(!defined('HEURIST_FILESTORE_ROOT')){
            define('HEURIST_FILESTORE_ROOT', $this->system->getFileStoreRootFolder());
        }

        $this->nakalaFile = HEURIST_FILESTORE_ROOT . '_EXTERNAL_LOOKUP_DATA/NAKALA_metadata_values.json';
        $this->openthesoFile = HEURIST_FILESTORE_ROOT . '_EXTERNAL_LOOKUP_DATA/OPENTHESO_thesauruses.json';

        $this->metadataCleanup();

        return true;
    }

    private function metadataCleanup() : void{

        $oldNakalaFile = HEURIST_FILESTORE_ROOT . 'NAKALA_metadata_values.json';
        $oldOpenthesoFile = HEURIST_FILESTORE_ROOT . 'OPENTHESO_thesauruses.json';

        if(folderExists(HEURIST_FILESTORE_ROOT . '_EXTERNAL_LOOKUP_DATA', true) !== true){
            folderCreate2(HEURIST_FILESTORE_ROOT . '_EXTERNAL_LOOKUP_DATA', '');
        }

        if(file_exists($oldNakalaFile) && !file_exists($this->nakalaFile)){
            rename($oldNakalaFile, $this->nakalaFile);
        }elseif(file_exists($oldNakalaFile)){
            fileDelete($oldNakalaFile);
        }
        if(file_exists($oldOpenthesoFile) && !file_exists($this->openthesoFile)){
            rename($oldOpenthesoFile, $this->openthesoFile);
        }elseif(file_exists($oldOpenthesoFile)){
            fileDelete($oldOpenthesoFile);
        }
    }

    private function setupRequest() : bool{

        global $accessToken_GeonamesAPI, $ESTC_PermittedDBs, $ESTC_UserName, $ESTC_Password;

        if(empty(@$this->request['serviceType']) || !array_key_exists($this->request['serviceType'], $this->serviceURLs)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'The provided lookup details are invalid');
            return false;
        }

        $this->database = @$this->request['db'];
        $this->isDebug = @$this->request['dbg'] == 1;

        $this->lookupType = $this->request['serviceType'];
        $this->lookupURL = array_key_exists('service', $this->request) && !empty($this->request['service'])
            ? filter_var($this->request['service'], FILTER_VALIDATE_URL) : '';
        $this->lookupMetadata = array_key_exists('metadata', $this->request) && !empty($this->request['metadata']) ? $this->request['metadata'] : '';

        $serviceURLs = $this->serviceURLs[$this->lookupType];

        // check that service type and service request match
        if($this->lookupType === 'ESTC'){
            $this->lookupAction = $this->request['action'];
            $this->isESTC = true;
            $this->isValid = $serviceURLs['db'] == $this->database || $serviceURLs['action'] == $this->lookupAction;
        }elseif($this->lookupMetadata){
            $this->isValid = true;
            $this->isMetadata = true;
        }elseif(!is_array($serviceURLs)){
            $this->isValid = $serviceURLs == $this->lookupURL || strpos($this->lookupURL, $serviceURLs) === 0;
        }else{
            foreach($serviceURLs as $url){
                if($url === $this->lookupURL || strpos($this->lookupURL, $url) === 0){
                    $this->isValid = true;
                    break;
                }
            }
        }

        if($this->lookupType == 'geonames' && empty($accessToken_GeonamesAPI)){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Unable to use the geonames API, API key is missing from configuration file');
            $this->isValid = false;
        }elseif(!$this->isValid){
            $msg = empty($this->lookupMetadata) ? 'The provided lookup details do not match any of the lookup details Heurist supports' : 'The requested metadata lookup is not handled by Heurist';
            $this->system->addError(HEURIST_INVALID_REQUEST, $msg);
        }

        if($this->isESTC){

            $this->isValid = !empty($ESTC_PermittedDBs) && !empty($ESTC_UserName) && !empty($ESTC_Password)
                          && strpos($ESTC_PermittedDBs, @$this->request['org_db']) !== false;

            $this->system->addError(HEURIST_REQUEST_DENIED, $this->ESTCMsg);
        }

        return $this->isValid;
    }

    private function verifyRequestParameters() : bool{

        global $accessToken_GeonamesAPI;

        $lookupType = strpos($this->lookupType, 'bnf') !== false ? 'bnf' : $this->lookupType;
        $lookupType = strpos($this->lookupType, 'nakala') !== false ? 'nakala' : $lookupType;

        if($this->isESTC || $this->isDebug || $this->isMetadata || !array_key_exists($lookupType, self::SERVICE_PARAMETERS) || !$this->isValid){
            $this->isValid || $this->system->addError(HEURIST_INVALID_REQUEST, 'Provided service "'. htmlspecialchars($lookupType) .'" is not valid');
            return $this->isValid;
        }

        $urlParts = parse_url($this->lookupURL);
        $serviceParams = self::SERVICE_PARAMETERS[$lookupType];

        $newURL = '';
        $newQuery = [];
        $this->lookupHeaders = [];

        $urlParts = array_merge_unique($urlParts, ['scheme' => 'https', 'query' => '']);
        $path = $urlParts['path'] ?? '';
        $host = isset($urlParts['host']) ? "{$urlParts['host']}{$path}" : $path;
        $newURL = !empty($host) ? "{$urlParts['scheme']}://{$host}" : '';

        parse_str($urlParts['query'], $urlQuery);

        foreach($serviceParams as $field => $type){

            if(!array_key_exists($field, $urlQuery)){
                continue;
            }

            if($field === NUMERIC){
                $newQuery[$field] = intval($urlQuery[$field]);
            }else{
                $newQuery[$field] = htmlspecialchars($urlQuery[$field], ENT_NOQUOTES);
            }
        }

        // Add default extras
        if($lookupType == 'geonames' && !empty($newQuery)){
            $newQuery['username'] = $accessToken_GeonamesAPI;
        }elseif($lookupType == 'bnf'){
            $newQuery['version'] = '1.2';
            $newQuery['operation'] = 'searchRetrieve';
            $newQuery['recordSchema'] = 'unimarcxchange';
        }elseif($lookupType == 'wikidata_simple'){
            $newQuery['action'] = 'wbsearchentities';
            $newQuery['format'] = 'json';
        }elseif($lookupType == 'wikidata_SPARQL'){
            $this->lookupHeaders[] = 'Accept: application/sparql-results+json';
        }

        if(empty($newURL) || empty($newQuery) || !filter_var($newURL, FILTER_VALIDATE_URL)){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Invalid URL provided');
            return false;
        }

        $this->lookupURL = "{$newURL}?" . http_build_query($newQuery);

        return true;
    }

    public function execute() : bool{

        $response = null;

        if($this->isESTC && strpos(strtolower(HEURIST_BASE_URL), strtolower(HEURIST_MAIN_SERVER)) !== false){
            $response = $this->handleESTC();
        }elseif($this->isESTC){
            $response = $this->sendESTCRequest();
        }elseif($this->isMetadata){
            $response = $this->retrieveMetadata();
        }else{
            $this->performLookup();
            $response = $this->lookupResponse !== false && $this->lookupResponse !== null;
        }

        return $response;
    }

    private function performLookup() : bool{
        
        $response = loadRemoteURLContentWithRange($this->lookupURL, null, true, $this->lookupTimeout, $this->lookupHeaders);

        if($response !== false){
            $this->lookupResponse = $response;
            return $this->processLookupResponse();
        }

        global $glb_curl_error, $accessToken_GeonamesAPI;

        $errorCode = (!empty($glb_curl_error)) ? $glb_curl_error : 'Error code: 500 Heurist Error';

        if(strpos($glb_curl_error, '404') !== false && $this->lookupType === 'nomisma'){ // No result for Nomisma returns a 404 error

            $this->lookupResponse = []; // return empty array
            return true;

        }else{

            preg_match("/\d+/", $glb_curl_error, $http_code);
            $http_code = $http_code[0];
            $heuristErrorType = HEURIST_ERROR;

            if($this->lookupType === 'geonames'){
                $url = preg_replace("/&?username=$accessToken_GeonamesAPI&?/", "", $this->lookupURL);
                $_REQUEST['service'] = $url;
            }

            $errorMsg = "<br>Heurist cannot connect/load data from the service url: $url<br>$errorCode";

            if(intval($http_code) >= 500){

                $errorMsg .= '<br><br>Please retry your request in a few minutes as the requested service is currently busy,'
                .  '<br>if the problem persists then please make a bug report.';

                $heuristErrorType = HEURIST_ACTION_BLOCKED;
            }

            $this->system->addError($heuristErrorType, $errorMsg);
            $this->lookupResponse = false;
        }

        return $this->lookupResponse !== false;
    }

    private function processLookupResponse() : bool{

        if($this->lookupResponse === null){
            return false;
        }

        switch($this->lookupType){

            case 'bnflibrary_bib':
                $this->processBnFBibliographicSearch();
                break;

            case 'bnflibrary_aut':
                $this->processBnFAuthoritySearch();
                break;

            case 'nakala':
                $this->processNakalaIDSearch();
                break;

            case 'opentheso':
                $this->processOpenthesoSearch();
                break;

            case 'geonames':
            case 'tlcmap':

                json_decode($this->lookupResponse);

                if(json_last_error() == JSON_ERROR_NONE){
                }elseif($this->request['is_XML'] == 1){
                    // XML to JSON (no attributes/namespace handling required)
                    $xmlObj = simplexml_load_string($this->lookupResponse, null, LIBXML_PARSEHUGE);
                    $this->lookupResponse = $xmlObj;
                }else{

                    $hasGeo = false;
                    $this->lookupResponse = str_getcsv($this->lookupResponse, "\n");//parse the rows
                    if(is_array($this->lookupResponse) && count($this->lookupResponse)>1){

                        $header = str_getcsv(array_shift($this->lookupResponse));
                        $id = 1;
                        foreach($this->lookupResponse as &$line){
                            $line = str_getcsv($line);
                            foreach($header as $idx=>$key){
                                $line[$key] = $line[$idx];
                                unset($line[$idx]);
                            }
                            if(@$line['latitude'] && @$line['longitude']){

                                $line = ['type' => 'Feature', 'id' => $id, 'properties' => $line,
                                    'geometry' => [
                                        'type' => 'Point', 'coordinates' => [
                                            $line['longitude'], $line['latitude']
                                        ]
                                    ]
                                ];

                                $hasGeo = true;
                            }
                        }

                        if(!$hasGeo){
                            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Service did not return data in an appropriate format');
                        }
                    }elseif(is_array($this->lookupResponse) && count($this->lookupResponse) == 1){
                        $this->system->addError(HEURIST_NOT_FOUND, 'No records match the search criteria');
                    }else{
                        $this->system->addError(HEURIST_ERROR, 'Service did not return any data');
                    }

                    $this->lookupResponse = $this->system->getError() ?? $this->lookupResponse;
                }

                break;

            case 'wikidata_SPARQL':
            //case 'wikidata_simple':

                $this->lookupResponse = ['status' => HEURIST_OK, 'data' => json_decode($this->lookupResponse, true)];

                if(array_key_exists('error', $this->lookupResponse['data'])){
                    $this->lookupResponse['status'] = $this->lookupResponse['data']['error']['code'] === 'missingparam' ? HEURIST_INVALID_REQUEST : HEURIST_REQUEST_DENIED;
                    $this->lookupResponse['msg'] = $this->lookupResponse['data']['error']['info'];
                }

                break;

            case 'bnf_recdump':

                $results = [];

                // Create xml object
                $xml_obj = simplexml_load_string($this->lookupResponse, null, LIBXML_PARSEHUGE);

                // Retrieve records from results
                $records = $xml_obj->children(BNF_XML_RECORDS_NAMESPACE, false)->records->record;

                foreach($records as $key => $details){
                    $record = $details->recordData->children(BNF_XML_DETAILS_NAMESPACE, false)->record;
                    $results['record'] = $record->asXML();
                    break;
                }

                $this->lookupResponse = $results;
                break;

            default: // nomisma
                break;
        }

        return true;
    }

    private function processBnFBibliographicSearch() : void{

        $authorCodes = '';

        if(array_key_exists('author_codes', $this->request) && !empty($this->request['author_codes']) && $this->request['author_codes'] != 'all'){
            $authorCodes = explode(',', $this->request['author_codes']);
        }
        $results = [];

        $dfHandled = [010, 101, 200, 210, 214, 215, 327, 700, 701, 702, 710, 712, 716];

        // Create xml object
        $xmlObj = simplexml_load_string($this->lookupResponse, null, LIBXML_PARSEHUGE);

        // Retrieve records from results
        $records = $xmlObj->children(BNF_XML_RECORDS_NAMESPACE, false)->records->record;

        // Move each result's details into seperate array
        foreach ($records as $details) {

            $formattedArray = [];

            $aut_idx = 0;
            $pub_idx = 0;
            $id = '';

            foreach ($details->recordData->children(BNF_XML_DETAILS_NAMESPACE, false)->record->controlfield as $key => $cf_ele) { // controlfield elements
                $cf_tag = @$cf_ele->attributes()['tag'];

                if($cf_tag == '001') { // BnF ID
                    $formattedArray['BnF_ID'] = (string)$cf_ele[0];
                    $id = (string)$cf_ele[0];
                }elseif($cf_tag == '003') { // Record URL
                    $formattedArray['biburl'] = (string)$cf_ele[0];
                    break;
                }elseif(intval($cf_tag) > 3){
                    break;
                }
            }

            foreach ($details->recordData->children(BNF_XML_DETAILS_NAMESPACE, false)->record->datafield as $key => $df_ele) { // datafield elements

                $df_tag = @$df_ele->attributes()['tag'];

                if(!$df_tag || !in_array($df_tag, $dfHandled)){
                    continue;
                }

                if($df_tag == '200') { // Title / Type

                    foreach($df_ele->subfield as $sub_key => $sf_ele) {

                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == 'a') {
                            $formattedArray['title'] = (string)$sf_ele[0];
                        }elseif($sf_code == 'b'){

                            $formattedArray['type'] = (string)$sf_ele[0];

                            if(array_key_exists('title', $formattedArray)){
                                $formattedArray['title'] .= " [{$formattedArray['type']}]";
                            }
                        }else{

                            if(array_key_exists('title', $formattedArray)){
                                $formattedArray['title'] .= ' , ' . (string)$sf_ele[0];
                            }
                        }
                    }
                }elseif($df_tag == '210' || $df_tag == '214') { // Publisher Location / Publisher Name / Year of Publication

                    $value = '';
                    $name = [];
                    $location = [];

                    foreach ($df_ele->subfield as $sub_key => $sf_ele) { // TODO - look for examples of sf_code == {b, r}
                        $sf_code = @$sf_ele->attributes()['code'];

                        $str_val = str_replace(['[', ']'], '', (string)$sf_ele[0]);

                        if($sf_code == 'a'){ // publisher location
                            $location[] = $str_val;
                        }elseif($sf_code == 'c'){ // publisher name
                            $name[] = $str_val;
                        }elseif($sf_code == 'd'){
                            $formattedArray['date'][] = $str_val;
                        }elseif($sf_code == 's'){
                            $formattedArray['publisher'][$pub_idx] = $str_val;
                            break;
                        }
                    }

                    if(!empty($location)){
                        $formattedArray['publisher'][$pub_idx]['location'] = '[' . implode(' ;', $location) . ']';
                    }
                    if(!empty($name)){
                        $formattedArray['publisher'][$pub_idx]['name'] = implode(', ', $name);
                    }

                    $pub_idx ++;
                }elseif($df_tag == '700' || $df_tag == '701' || $df_tag == '702' || $df_tag == '710' || $df_tag == '712' || $df_tag == '716') { // Creator [Author|Contributor]

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

                    $author = [];

                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {

                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == '3'){
                            $id = (string)$sf_ele[0];
                            $author['id'] = $id;
                            continue;
                        }elseif($sf_code == '4'){
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
                                }elseif($sf_code == 'b') { // Given name
                                    $author['firstname'] = (string)$sf_ele[0];
                                }elseif($sf_code == 'f') { // Years active
                                    $author['active'] = (string)$sf_ele[0];
                                }

                                break;

                            case '710':
                            case '711':
                            case '712':
                            case '713':

                                if($sf_code == 'c') { // Date // $sf_code == 'f' Location
                                    $author['active'] = '(' . (string)$sf_ele[0] . ')';
                                }elseif($sf_code == 'b'){ // Sub unit name
                                    $author['surname'] = (string)$sf_ele[0];
                                }elseif($sf_code == 'a') { // Main name
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

                    if(empty($author)){
                        continue;
                    }

                    if(isset($role) && !empty($role) && !empty($authorCodes)){ // role code found

                        if(in_array($role, $authorCodes)){
                            $formattedArray['author'][$id] = $author;
                        }else{
                            $formattedArray['contributor'][$id] = $author;
                        }
                    }else{ // by default, set as author
                        $formattedArray['author'][$id] = $author;
                    }

                    $aut_idx ++;

                }elseif($df_tag == '010') { // ISBN

                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == 'a') {
                            $formattedArray['isbn'][] = (string)$sf_ele[0];
                        }
                    }
                }elseif($df_tag == '215') { // Description

                    $value = '';
                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == 'a' || $sf_code == 'c' || $sf_code == 'd'){
                            $value = $value == '' ? (string)$sf_ele[0] : ' ' . (string)$sf_ele[0];
                        }
                    }

                    if($value != '') {
                        $formattedArray['description'][] = $value;
                    }
                }elseif($df_tag == '101') { // Language, e.g. fre or FR 101

                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == 'a') { // a
                            $formattedArray['language'][] = (string)$sf_ele[0];
                        }
                    }
                }elseif($df_tag == '327') { // Extended Description

                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == 'a') {
                            $formattedArray['ext_description'][] = (string)$sf_ele[0];
                        }
                    }
                }
            }

            $results['result'][] = $formattedArray;
        }

        // Add other details
        $results['numberOfRecords'] = intval($xmlObj->children(BNF_XML_RECORDS_NAMESPACE, false)->numberOfRecords);

        // Encode to json for response to JavaScript
        $this->lookupResponse = $results;
    }

    private function processBnFAuthoritySearch() : void{

        $results = [];

        // Create xml object
        $xmlObj = simplexml_load_string($this->lookupResponse, null, LIBXML_PARSEHUGE);

        // Retrieve records from results
        $records = $xmlObj->children(BNF_XML_RECORDS_NAMESPACE, false)->records->record;

        $dfHandled = [200, 210, 215, 216, 220, 240, 230, 250];

        // Move each result's details into seperate array
        foreach ($records as $key => $details) {

            $formattedArray = [];

            foreach ($details->recordData->children(BNF_XML_DETAILS_NAMESPACE, false)->record->controlfield as $key => $cf_ele) { // controlfield elements
                $cf_tag = @$cf_ele->attributes()['tag'];

                if($cf_tag == '001') { // BnF ID
                    $formattedArray['BnF_ID'] = (string)$cf_ele[0];
                }elseif($cf_tag == '003') { // Record URL
                    $formattedArray['auturl'] = (string)$cf_ele[0];
                    break;
                }elseif(intval($cf_tag) > 3){
                    break;
                }
            }

            foreach ($details->recordData->children(BNF_XML_DETAILS_NAMESPACE, false)->record->datafield as $key => $df_ele) { // datafield elements
                $df_tag = @$df_ele->attributes()['tag'];

                if(!$df_tag || !in_array($df_tag, $dfHandled)){
                    continue;
                }

                foreach($df_ele->subfield as $sub_key => $sf_ele) {

                    $sf_code = @$sf_ele->attributes()['code'];

                    switch ($df_tag) {
                        case '200': // Person

                            if($sf_code == 'a'){ // Surname
                                $formattedArray['name'] = (string)$sf_ele[0];
                            }elseif($sf_code == 'b'){ // First name

                                if( array_key_exists('name', $formattedArray)){
                                    $formattedArray['name'] .= ', ' . (string)$sf_ele[0];
                                }else{
                                    $formattedArray['name'] = (string)$sf_ele[0];
                                }
                            }elseif($sf_code == 'f' || $sf_code == 'd'){ // Years active
                                $formattedArray['years_active'] = (string)$sf_ele[0];
                            }elseif($sf_code == 'c'){ // Role/Job
                                $formattedArray['role'] = (string)$sf_ele[0];
                            }

                            break;

                        case '210': // Collective

                            if($sf_code == 'a'){ // Name
                                $formattedArray['name'] = (string)$sf_ele[0];
                            }elseif($sf_code == 'c'){ // Location
                                $formattedArray['location'] = (string)$sf_ele[0];
                            }elseif($sf_code == 'b'){ // Type
                                $formattedArray['role'] = (string)$sf_ele[0];
                            }

                            break;

                        case '240': // Conventional Title

                            if($sf_code == 'a'){ // Name
                                $formattedArray['name'] = (string)$sf_ele[0];
                            }elseif($sf_code == 't'){ // title

                                if( array_key_exists('name', $formattedArray)){
                                    $formattedArray['name'] .= ' [' . (string)$sf_ele[0] . ']';
                                }else{
                                    $formattedArray['name'] = (string)$sf_ele[0];
                                }
                            }

                            break;

                        case '230': // MISSING - $b $h $k $m

                            if($sf_code != 'a' && $sf_code != 'i'){
                                break;
                            }

                            // Name
                            if(array_key_exists('name', $formattedArray)){
                                $formattedArray['name'] .= ' . ' . (string)$sf_ele[0];
                            }else{
                                $formattedArray['name'] = (string)$sf_ele[0];
                            }

                            break;

                        case '215': // Geographical name
                        case '216': // Marque
                        case '250': // ???

                            if($sf_code != 'a'){ // && $sf_code != 'x'
                                break;
                            }

                            // Name
                            if(array_key_exists('name', $formattedArray)){
                                $formattedArray['name'] .= ' ' . (string)$sf_ele[0];
                            }else{
                                $formattedArray['name'] = (string)$sf_ele[0];
                            }

                            break;

                        case '220': // Family

                            if($sf_code == 'a'){ // Name
                                $formattedArray['name'] = (string)$sf_ele[0];
                            }elseif($sf_code == 'c'){ // Type
                                $formattedArray['role'] = (string)$sf_ele[0];
                            }

                            break;

                        default:
                            break;
                    }
                }

                if(array_key_exists('name', $formattedArray) && !empty($formattedArray['name'])){ // add authority type
                    $formattedArray['authority_type'] = (string)$df_tag[0];
                }

                break;
            }

            if(!empty($formattedArray) && array_key_exists('name', $formattedArray) && !empty($formattedArray['name'])){
                $results['result'][] = $formattedArray;
            }
        }

        // Add other details, can be used for more calls to retrieve all results (currently retrieves 500 records at max)
        $results['numberOfRecords'] = intval($xmlObj->children(BNF_XML_RECORDS_NAMESPACE, false)->numberOfRecords);

        // Encode to json for response to JavaScript
        $this->lookupResponse = $results;
    }

    private function processNakalaIDSearch() : void{

        $this->lookupResponse = json_decode($this->lookupResponse, true);

        if(json_last_error() !== JSON_ERROR_NONE){
            $this->system->addError(HEURIST_ERROR, 'Service did not return data in an handled format');
        }

        $results = [];
        if(!array_key_exists('totalResults', $this->lookupResponse) || json_last_error() !== JSON_ERROR_NONE){
            $this->lookupResponse = json_last_error() !== JSON_ERROR_NONE ? $this->system->getError() : [];
            return;
        }

        $results['count'] = $this->lookupResponse['totalResults'];
        $results['records'] = [];

        if($this->lookupResponse['totalResults'] <= 0){
            $this->lookupResponse = $results;
            return;
        }

        foreach ($this->lookupResponse['datas'] as $records) {

            $id = @$records['identifier'];
            $has_files = array_key_exists('files', $records);

            if($has_files){ // datas, files
                $results['records'][$id]['rec_url'] = "https://nakala.fr/{$id}";
            }else{ // collection, should be filtered out by request - just in case
                continue;
            }

            $results['records'][$id]['citation'] = @$records['citation'];
            $results['records'][$id]['identifier'] = @$records['identifier'];

            $results['records'][$id]['title'] = [];
            $results['records'][$id]['mime_type'] = [];
            $results['records'][$id]['url'] = [];
            $results['records'][$id]['author'] = [];
            $results['records'][$id]['contributor'] = [];
            $results['records'][$id]['source'] = [];
            $results['records'][$id]['copyright'] = [];
            $results['records'][$id]['provenance'] = [];

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
                }elseif(strpos($metadata['propertyUri'], 'creator') !== false){ // Author
                    $results['records'][$id]['author'][] = $metadata['value'];
                }elseif(strpos($metadata['propertyUri'], 'terms#title') !== false){ // Title
                    $results['records'][$id]['title'][] = $metadata['value'];
                }elseif(strpos($metadata['propertyUri'], 'terms#created') !== false){ // Created Date
                    $results['records'][$id]['date'] = $metadata['value'];
                }elseif(strpos($metadata['propertyUri'], 'terms#license') !== false){ // License
                    $results['records'][$id]['license'] = $metadata['value'];
                }elseif(strpos($metadata['propertyUri'], 'abstract') !== false){ // Abstract
                    $results['records'][$id]['abstract'] = $metadata['value'];
                }elseif(strpos($metadata['propertyUri'], 'contributor') !== false){ // Contributor
                    $results['records'][$id]['contributor'][] = $metadata['value'];
                }elseif(strpos($metadata['propertyUri'], 'source') !== false){ // Source
                    $results['records'][$id]['source'][] = $metadata['value'];
                }elseif(strpos($metadata['propertyUri'], 'rightsHolder') !== false){ // Right Holders
                    $results['records'][$id]['copyright'][] = $metadata['value'];
                }elseif(strpos($metadata['propertyUri'], 'provenance') !== false){ // Provenance
                    $results['records'][$id]['provenance'][] = $metadata['value'];
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
                        $results['records'][$id]['url'][] = "https://api.nakala.fr/data/{$id}/{$file['sha1']}";
                    }
                }
            }

            if(count($results['records'][$id]['title']) == 0){
                $results['records'][$id]['title'] = 'Undetermined';
            }
            if(count($results['records'][$id]['author']) == 0){
                $results['records'][$id]['author'] = 'Anonymous';
            }
            if(count($results['records'][$id]['source']) == 0){
                $results['records'][$id]['source'] = 'Unknown';
            }
        }

        $this->lookupResponse = $results;
    }

    private function processOpenthesoSearch() : void{

        $def_lang = $this->system->userGetPreference('layout_language', 'fr');
        $def_lang = getLangCode2($def_lang);
        $def_lang = !$def_lang ? 'fr' : strtolower($def_lang);

        $is_error = strpos($this->lookupResponse, 'Une erreur est survenue') !== false ? 'database' : false;
        $is_error = $is_error && strpos($this->lookupResponse, 'Connexion à la base donnée : OK! Connected') ? 'request' : $is_error;
        $this->lookupResponse = !$is_error ? json_decode($this->lookupResponse, true) : $this->lookupResponse;

        $type_idx = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';
        $trm_uri = 'http://www.w3.org/2004/02/skos/core#Concept';
        $desc_idx = 'http://www.w3.org/2004/02/skos/core#definition';
        $code_idx = 'http://purl.org/dc/terms/identifier';
        $label_idx = 'http://www.w3.org/2004/02/skos/core#prefLabel';

        $geopoint_idx = 'http'.'://www.opengis.net/ont/geosparql#P625';
        $valid_geopoint_type = 'http'.'://www.opengis.net/ont/geosparql#wktLiteral';
        $notes_idx = 'http://www.w3.org/2004/02/skos/core#editorialNote';

        $results = [];

        if($is_error || json_last_error() !== JSON_ERROR_NONE){

            $error_msg = $is_error === 'database' ?
                'Opentheso reported an error with it trying to connect to their internal database, please check that the selected server is currently available before re-trying in a few moments.' :
                'Please report this to the Heurist team, and include your Opentheso request (Selected server, thesaurus and search)';

            $error_msg = $is_error === 'request' ?
                'Opentheso reported an error when trying to run your request, please check that your data can be download as JSON (the link can be found when viewing your concept in Opentheso, then the form row labelled "Exporter le concept en SKOS") before re-trying.' :
                $error_msg;

            $error_msg = json_last_error() !== JSON_ERROR_NONE ? 'The response from Opentheso was not in JSON format, please report this to the Heurist team and include your request (Opentheso server, thesaurus and search)' : $error_msg;

            $error_status = $is_error === 'database' ? HEURIST_ACTION_BLOCKED : HEURIST_UNKNOWN_ERROR;
            $error_status = $is_error === 'request' ? HEURIST_INVALID_REQUEST : $error_status;

            $this->system->addError($error_status, "<br>{$error_msg}");
            return;
        }

        foreach ($this->lookupResponse as $uri => $details) {

            if($details[$type_idx][0]['value'] != $trm_uri){ // not a term
                continue;
            }

            $label = '';
            $desc = '';
            $code = '';
            $translated_labels = [];

            if(array_key_exists($desc_idx, $details)){
                $desc = $details[$desc_idx][0]['value'];
            }
            if(array_key_exists($code_idx, $details)){
                $code = $details[$code_idx][0]['value'];
            }
            $code_parts = explode('/', $uri);
            $code .= (!empty($code) ? ' ;' : '') . implode('/', array_splice($code_parts, -2, 2));

            foreach ($details[$label_idx] as $label_details) {
                if($label_details['lang'] == $def_lang){
                    $label = $label_details['value'];
                    continue;
                }
                if(empty($label) && ($label_details['lang'] == 'fr' || $label_details['lang'] == 'en')){
                    $label = $label_details['value'];
                }

                $lang_code = getLangCode3($label_details['lang']);
                $translated_labels[$lang_code] = "{$lang_code}:{$label_details['value']}";// LANG_CODE:Value
            }

            $notes = array_key_exists($notes_idx, $details) ? $details[$notes_idx][0]['value'] : '';
            $geopoint = array_key_exists($geopoint_idx, $details) && $details[$geopoint_idx][0]['datatype'] == $valid_geopoint_type ?
            $details[$geopoint_idx][0]['value'] : '';

            $results[] = ['term_label' => $label, 'term_desc' => $desc, 'term_code' => $code, 'term_uri' => $uri, 'term_translations' => $translated_labels, 'editor_notes' => $notes, 'geopoint' => $geopoint];
        }

        $this->lookupResponse = $results;
    }

    private function retrieveMetadata() : bool{

        if($this->lookupType === 'opentheso'){

            if(is_array(@$this->request['params'])){
                $this->request = array_merge($this->request, $this->request['params']);
            }

            switch($this->lookupMetadata){

                case 'servers':
                    $this->lookupResponse = ['data' => $this->serviceURLs['opentheso']];
                    break;

                case 'thesauruses':
                    $this->getOpenthesoThesauruses();
                    break;

                case 'collections':
                    $this->getThesauruseCollections();
                    break;

                default:
                    break;
            }

        }elseif($this->lookupType === 'nakala'){
            $this->getNakalaMetadata();
        }else{
            return false;
        }

        if(!$this->lookupResponse){
            $this->lookupResponse = $this->system->getError() ?? [];
        }

        return true;
    }

    /**
     * Retrieves Opentheso thesauruses, potentially refreshing from the cache or server.
     */
    private function getOpenthesoThesauruses() : void{

        $this->lookupResponse = [];
        $response = false;

        $refresh = intval($this->request['refresh']) == 1;

        if(!file_exists($this->openthesoFile)){ // create new file
            $response = $this->updateOpenthesoThesauruses();
        }else{

            $alreadyUpdated = false;

            $this->lookupResponse = file_get_contents($this->openthesoFile);

            if($refresh || !$this->lookupResponse || empty($this->lookupResponse)){
                $response = $this->updateOpenthesoThesauruses();
                $alreadyUpdated = true;
            }else{
                $this->lookupResponse = json_decode($this->lookupResponse, true);
                $response = json_last_error() !== JSON_ERROR_NONE;
            }

            if(!$response || !is_array($this->lookupResponse) || $this->lookupResponse['last_update'] < date('Y-m-d')){
                if($alreadyUpdated){
                    $this->system->errorExitApi('Unable to retrieve Opentheso thesauruses due to unknown error.', HEURIST_ACTION_BLOCKED);
                    exit;
                }
                $response = $this->updateOpenthesoThesauruses();
            }
        }

        if(empty($this->lookupResponse) || !$response){
            return;
        }

        if(is_array($this->lookupResponse)){
            unset($this->lookupResponse['last_update']);
        }
        if(empty($this->lookupResponse)){
            $this->lookupResponse = [HEURIST_ACTION_BLOCKED, 'No thesauruses found'];
        }
    }

    /**
     * Update the list of thesauruses
     *
     * @return bool Whether the updating was success.
     */
    private function updateOpenthesoThesauruses() : bool{

        // Get existing data
        $dataOld = file_exists($this->openthesoFile) && filesize($this->openthesoFile) > 0 ? file_get_contents($this->openthesoFile) : [];
        $dataOld = json_decode($dataOld, true);
        $dataOld = json_last_error() !== JSON_ERROR_NONE || !is_array($dataOld) ? [] : $dataOld;

        $this->lookupResponse = [];

        $servers = !is_array(@$this->request['servers']) || empty($this->request['servers']) ? array_keys($this->serviceURLs['opentheso']) : $this->request['servers'];

        foreach ($servers as $server){

            $baseURI = $this->serviceURLs['opentheso'][$server];

            if(!filter_var($baseURI, FILTER_VALIDATE_URL)){
                continue;
            }

            $this->lookupResponse[$server] = [];

            // Check if server is available
            $ping = loadRemoteURLContentWithRange("{$baseURI}ping", null, true, 60);
            if(!$ping){
                continue;
            }

            $thesauruses = loadRemoteURLContentWithRange("{$baseURI}thesaurus", null, true, 60);
            if($thesauruses === false){
                $this->system->addError(HEURIST_UNKNOWN_ERROR, "Unable to retrieve the available thesauruses from $server"); // reported to developers only
                continue;
            }

            $thesauruses = json_decode($thesauruses, true);
            if(json_last_error() !== JSON_ERROR_NONE){
                $this->system->addError(HEURIST_ERROR, "An error occurred while handling the response for /thesaurus from Opentheso server $server"); // reported to developers only
                continue;
            }

            foreach ($thesauruses as $thesaurus) {

                $key = $thesaurus['idTheso'];
                $label = '';
                $def_label = true;

                foreach($thesaurus['labels'] as $translated_label){
                    $lang = $translated_label['lang'];

                    if(empty($label) || $lang == 'fr' || (!$def_label && $lang == 'en')){
                        $label = $translated_label['title'];
                        $def_label = $lang == 'fr';
                    }
                }

                if(empty($label)){
                    continue;
                }

                $this->lookupResponse[$server][$key] = ['name' => $label, 'groups' => []];
                if($dataOld && is_array($dataOld[$server][$key]) && !empty($dataOld[$server][$key]['groups'])){
                    // Validate cached groups, remove invalid ones

                    $old_groups = $dataOld[$server][$key]['groups'];

                    foreach($old_groups as $group_id => $gname){

                        $group_dtls = loadRemoteURLContentWithRange("{$baseURI}group/$key/$group_id", null, true, 60);
                        if(!$group_dtls){ continue; }

                        $group_dtls = json_decode($group_dtls, true);
                        if(json_last_error() !== JSON_ERROR_NONE || empty($group_dtls)){ continue; }

                        $keys = array_keys($group_dtls);
                        if(!array_key_exists('http://www.w3.org/2004/02/skos/core#prefLabel', $group_dtls[$keys[0]])){ continue; }

                        $this->lookupResponse[$server][$key]['groups'][$group_id] = $gname;
                    }
                }
            }
        }

        $this->lookupResponse['last_update'] = date('Y-m-d');

        $fileSize = fileSave(json_encode($this->lookupResponse), $this->openthesoFile);
        if($fileSize <= 0 && !empty($this->lookupResponse)){
            $this->system->addError(HEURIST_ERROR, 'Cannot save Opentheso thesaurus list into local file store');
        }

        return $fileSize > 0 && !empty($this->lookupResponse);
    }

    private function getThesauruseCollections() : void{ // getOpenthesoCollections

        if(empty($this->request['server']) || !array_key_exists($this->request['server'], $this->serviceURLs['opentheso']) || empty($this->request['thesaurus'])){
            $this->lookupResponse = $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid request to retrieve record groups for thesauruses');
            return;
        }

        $server = $this->request['server'];
        $theso = $this->request['thesaurus'];

        if(!file_exists($this->openthesoFile)){
            $this->lookupResponse = $this->system->addError(HEURIST_UNKNOWN_ERROR, 'Unable to access the Opentheso cache file');
            return;
        }

        $data = filesize($this->openthesoFile) > 0 ? file_get_contents($this->openthesoFile) : null;

        $data = $data !== null ? json_decode($data, true) : null;
        if(json_last_error() !== JSON_ERROR_NONE || !is_array($data) || empty($data)){
            $this->lookupResponse = $this->system->addError(HEURIST_ERROR, 'Unable to retrieve details from the Opentheso cache');
            return;
        }

        if(intval(@$this->request['refresh']) != 1 && !empty($data[$server][$theso]['groups'])){
            $this->lookupResponse = $data[$server][$theso]['groups'];
            return;
        }

        $thesoURI = urlencode($theso);
        $url = "{$this->serviceURLs['opentheso'][$server]}group/{$thesoURI}";

        $groups = loadRemoteURLContentWithRange($url, null, true, 60);

        if($groups === false){
            $this->lookupResponse = $this->system->addError(HEURIST_UNKNOWN_ERROR, "Unable to retrieve the available groups for thesauruses $theso from server $server");
            return;
        }

        $groups = json_decode($groups, true);

        if(json_last_error() !== JSON_ERROR_NONE){
            $this->lookupResponse = $this->system->addError(HEURIST_ERROR, "An error occurred while handling the response for /groups from Opentheso server $server and thesauruses $theso");
            return;
        }

        foreach($groups as $group) {

            $key = $group['idGroup'];
            $label = '';
            $def_label = true;

            foreach($group['labels'] as $translated_label){

                $lang = $translated_label['lang'];

                if(empty($label) || $lang == 'fr' || (!$def_label && $lang == 'en')){
                    $label = $translated_label['title'];
                    $def_label = $lang == 'fr';
                }
            }

            if(empty($label)){
                continue;
            }

            $data[$server][$theso]['groups'][$key] = $label;
        }

        fileSave(json_encode($data), $this->openthesoFile);

        $this->lookupResponse = $data[$server][$theso];// return group details only
    }

    /**
     * Retrieves Nakala metadata based on the provided type.
     *
     * It checks if the metadata in the NAKALA_metadata_values.json file is up-to-date.
     * If the data is outdated or the file doesn't exist, it updates the metadata before returning the data.
     */
    private function getNakalaMetadata() : void{

        $this->lookupResponse = [];
        $this->setupNakalaMetadata();

        // Return the requested type of data or the entire metadata
        $this->lookupResponse = $this->getRequestedNakalaData();
    }

    /**
     * Hardcoded Nakala metadata values for licences, data types, and property types
     * The 'years' type is too be setup client side
     */
    private function setupNakalaMetadata() : void{

        $licences = [
            'CC-BY-4.0',
            'CC-BY-NC-SA-4.0',
            'CC-BY-NC-ND-4.0',
            'CC-BY-NC-4.0',
            'PDM',
            'InC',
            'etalab-2.0',
            'CC-BY-SA-4.0',
            'CC-BY-NC-SA-2.0',
            'CC-BY-NC-ND-3.0',
            'UND',
            'CC0-1.0',
            'Reserved',
            'CC-BY-NC-SA-2.5',
            'CC-BY-NC-SA-3.0',
            'CC-BY-ND-4.0',
            'ODbL-1.0',
            'CC-BY-ND-3.0',
            'CC-BY-NC-2.5',
            'Etalab-2.0',
            'NoC-CR',
            'CNE',
            'CC-BY-NC-SA-2.0-FR',
            'NoC-NC',
            '0BSD'
        ];

        $dataTypes = <<<DATATYPES
        [
            ["Image", "c_c513"],
            ["Journal article", "c_6501"],
            ["Other", "c_1843"],
            ["Book", "c_2f33"],
            ["Archival material", "http://purl.org/library/ArchiveMaterial"],
            ["Map", "c_12cd"],
            ["Letter", "c_0857"],
            ["Sound", "c_18cc"],
            ["Manuscript", "c_0040"],
            ["Text", "c_18cf"],
            ["Periodical", "c_2659"],
            ["Video", "c_12ce"],
            ["Dataset", "c_ddb1"],
            ["Musical notation", "c_18cw"],
            ["Webpage", "c_7ad9"],
            ["Critical edition", "c_ba08"],
            ["Conference object", "c_c94f"],
            ["Report", "c_93fc"],
            ["Bulletin", "http://purl.org/ontology/bibo/Series"],
            ["Learning object", "c_e059"],
            ["Survey data", "https://w3id.org/survey-ontology#SurveyDataSet"],
            ["Software", "c_5ce6"],
            ["Bibliography", "c_86bc"],
            ["Conference poster", "c_6670"],
            ["Preprint", "c_816b"],
            ["Thesis", "c_46ec"],
            ["Data paper", "c_beb9"],
            ["Review", "c_efa0"],
            ["Art exhibition", "http://purl.org/ontology/bibo/Collection"],
            ["Computational notebook", "c_e9a0"]
        ]
        DATATYPES;

        $dataTypes = json_decode($dataTypes);

        $properties = <<<PROPERTIES
        [
            {
                "name": "Nakala title",
                "value": "http://nakala.fr/terms#title",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Nakala creator",
                "value": "http://nakala.fr/terms#creator",
                "repeatable": true,
                "type": "authour"
            },
            {
                "name": "Nakala created",
                "value": "http://nakala.fr/terms#created",
                "repeatable": false,
                "type": "date"
            },
            {
                "name": "Nakala licence",
                "value": "http://nakala.fr/terms#license",
                "repeatable": false,
                "type": "license"
            },
            {
                "name": "Nakala type",
                "value": "http://nakala.fr/terms#type",
                "repeatable": false,
                "type": "type"
            },
            {
                "name": "Title",
                "value": "http://purl.org/dc/terms/title",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Creator",
                "value": "http://purl.org/dc/terms/creator",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Subject",
                "value": "http://purl.org/dc/terms/subject",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Description",
                "value": "http://purl.org/dc/terms/description",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Publisher",
                "value": "http://purl.org/dc/terms/publisher",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Contributor",
                "value": "http://purl.org/dc/terms/contributor",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Date",
                "value": "http://purl.org/dc/terms/date",
                "repeatable": true,
                "type": "date"
            },
            {
                "name": "Type",
                "value": "http://purl.org/dc/terms/type",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Format",
                "value": "http://purl.org/dc/terms/format",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Identifier",
                "value": "http://purl.org/dc/terms/identifier",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Source",
                "value": "http://purl.org/dc/terms/source",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Language",
                "value": "http://purl.org/dc/terms/language",
                "repeatable": true,
                "type": "language_ar2"
            },
            {
                "name": "Relation",
                "value": "http://purl.org/dc/terms/relation",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Coverage",
                "value": "http://purl.org/dc/terms/coverage",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Rights",
                "value": "http://purl.org/dc/terms/rights",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Audience",
                "value": "http://purl.org/dc/terms/audience",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Alternative",
                "value": "http://purl.org/dc/terms/alternative",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Table Of Contents",
                "value": "http://purl.org/dc/terms/tableOfContents",
                "repeatable": false
            },
            {
                "name": "Abstract",
                "value": "http://purl.org/dc/terms/abstract",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Created",
                "value": "http://purl.org/dc/terms/created",
                "repeatable": true,
                "type": "date"
            },
            {
                "name": "Valid",
                "value": "http://purl.org/dc/terms/valid",
                "repeatable": true,
                "type": "date"
            },
            {
                "name": "Available",
                "value": "http://purl.org/dc/terms/available",
                "repeatable": true,
                "type": "date"
            },
            {
                "name": "Issued",
                "value": "http://purl.org/dc/terms/issued",
                "repeatable": true,
                "type": "date"
            },
            {
                "name": "Modified",
                "value": "http://purl.org/dc/terms/modified",
                "repeatable": true,
                "type": "date"
            },
            {
                "name": "Extent",
                "value": "http://purl.org/dc/terms/extent",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Medium",
                "value": "http://purl.org/dc/terms/medium",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Is Version Of",
                "value": "http://purl.org/dc/terms/isVersionOf",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Has Version",
                "value": "http://purl.org/dc/terms/hasVersion",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Is Replaced By",
                "value": "http://purl.org/dc/terms/isReplacedBy",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Replaces",
                "value": "http://purl.org/dc/terms/replaces",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Is Required By",
                "value": "http://purl.org/dc/terms/isRequiredBy",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Requires",
                "value": "http://purl.org/dc/terms/requires",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Is Part Of",
                "value": "http://purl.org/dc/terms/isPartOf",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Has Part",
                "value": "http://purl.org/dc/terms/hasPart",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Is Referenced By",
                "value": "http://purl.org/dc/terms/isReferencedBy",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "References",
                "value": "http://purl.org/dc/terms/references",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Is Format Of",
                "value": "http://purl.org/dc/terms/isFormatOf",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Has Format",
                "value": "http://purl.org/dc/terms/hasFormat",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Conforms To",
                "value": "http://purl.org/dc/terms/conformsTo",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Spatial",
                "value": "http://purl.org/dc/terms/spatial",
                "repeatable": true,
                "type": "geo"
            },
            {
                "name": "Temporal",
                "value": "http://purl.org/dc/terms/temporal",
                "repeatable": true,
                "type": "date"
            },
            {
                "name": "Mediator",
                "value": "http://purl.org/dc/terms/mediator",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Date Accepted",
                "value": "http://purl.org/dc/terms/dateAccepted",
                "repeatable": true,
                "type": "date"
            },
            {
                "name": "Date Copyrighted",
                "value": "http://purl.org/dc/terms/dateCopyrighted",
                "repeatable": true,
                "type": "date"
            },
            {
                "name": "Date Submitted",
                "value": "http://purl.org/dc/terms/dateSubmitted",
                "repeatable": true,
                "type": "date"
            },
            {
                "name": "Education Level",
                "value": "http://purl.org/dc/terms/educationLevel",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Access Rights",
                "value": "http://purl.org/dc/terms/accessRights",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Bibliographic Citation",
                "value": "http://purl.org/dc/terms/bibliographicCitation",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "License",
                "value": "http://purl.org/dc/terms/license",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Rights Holder",
                "value": "http://purl.org/dc/terms/rightsHolder",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Provenance",
                "value": "http://purl.org/dc/terms/provenance",
                "repeatable": true,
                "type": "text"
            },
            {
                "name": "Instructional Method",
                "value": "http://purl.org/dc/terms/instructionalMethod",
                "repeatable": true,
                "type": "text"
            }
        ]
        PROPERTIES;

        // years = range(1, date('Y'))

        $this->lookupResponse = ['licenses' => $licences, 'types' => $dataTypes, 'fields' => $properties];
    }

    /**
    * Returns the requested Nakala data type or the full data.
    *
    * @return array The requested subset of metadata, or the full data if 'all' is specified.
    */
    private function getRequestedNakalaData() : array{

        if(!is_array($this->lookupResponse) || empty($this->lookupResponse)){
            return [];
        }elseif($this->lookupMetadata === 'all'){
            return $this->lookupResponse;
        }

        $requestedMetadata = explode(',', $this->lookupMetadata);

        $response = [];
        foreach($requestedMetadata as $metadataOption){
            $response[$metadataOption] = array_key_exists($metadataOption, $this->lookupResponse) ? $this->lookupResponse[$metadataOption] : [];
        }

        if(count($response) === 1){
            $response = array_pop($response);
        }

        return $response;
    }

    public function output(bool $returnValue = false){

        if(!$this->lookupResponse){
            $this->lookupResponse = $this->system->getError() ?? [];
        }

        if($returnValue){
            return $this->lookupResponse;
        }

        dataOutput($this->lookupResponse);
        exit;
    }

    private function handleESTC() : bool{

        global $ESTC_UserName, $ESTC_Password;

        $isLoggedIn = $this->system->doLogin($ESTC_UserName, $ESTC_Password, 'shared');

        if(!$isLoggedIn){
            $this->system->addError(HEURIST_ERROR, "We are unable to access the records within the ESTC database at this moment.<br>Please contact the Heurist team. Query is: {$this->request['q']}");
            return false;
        }

        if(array_key_exists('entity', $this->request)){ // retrieve definition details, e.g. book type terms
            require_once __DIR__ . '/entityScrud.php';
            exit;
        }

        $this->system->getCurrentUserAndSysInfo(false);

        if(array_key_exists('action', $this->request)){ // import record for LRC18C lookup

            if($this->request['action'] == 'import_records'){ // perform standard record import action, user on ESTC server
                require_once __DIR__ . '/importController.php';
                exit;
            }elseif($this->request['action'] == 'record_output'){ // retrieve record from record_output, user on external server
                require_once __DIR__ . '/record_output.php';
                exit;
            }
        }

        require_once __DIR__ . '/../records/search/recordSearch.php';

        $this->lookupResponse = recordSearch($system, $this->request);

        return true;
    }

    private function sendESTCRequest() : bool{
        $this->system->addError(HEURIST_ACTION_BLOCKED, 'ESTC Request not implemented');
        return false;
    }
}

$params = USanitize::sanitizeInputArray();

// check if service type is ESTC
$isESTC = false;
if(@$params['serviceType'] == 'ESTC'){

    $ESTCdetails = [
        'db' => 'ESTC_Helsinki_Bibliographic_Metadata',
        'action' => 'import_records' // 'record_output'
    ];

    $url = empty(@$params['service']) ? '' : $params['service'];

    $action = @$params['action'];
    $isESTC = @$params['db'] == $ESTCdetails['db'] || $action == $ESTCdetails['action'];
}

$system = new System();
$system->init(@$params['db'], false);

if(!$isESTC){ // !$isIncluded

    $lookupHandler = new LookupController($system, $params);

    if($lookupHandler->init() && $lookupHandler->execute()){
        $lookupHandler->output(false);
    }else{
        dataOutput($system->getError());
        exit;
    }
}

$response = [];

$is_debug = @$params['dbg'] == 1;

$url = '';

$isAllowed = isset($ESTC_PermittedDBs, $ESTC_UserName, $ESTC_Password) && strpos($ESTC_PermittedDBs, @$_REQUEST['org_db']) !== false;

if(strpos(strtolower(HEURIST_BASE_URL), strtolower(HEURIST_MAIN_SERVER)) !== false){ // currently on server where ESTC DB is located

    if(array_key_exists('entity', $params)){ // retrieve entity info (term lookup)
        require_once dirname(__FILE__).'/entityScrud.php';
        exit;
    }

    $isInited = $system->init(@$params['db']);

    if($isInited !== false && $isAllowed){ // search records

        $isLoggedIn = $system->doLogin($ESTC_UserName, $ESTC_Password, 'shared');

        if($isLoggedIn){ // logged in, begin search

            $system->getCurrentUserAndSysInfo(false);

            if(array_key_exists('action', $params)){ // import record for LRC18C lookup

                if($params['action'] == 'import_records'){ // perform standard record import action, user on ESTC server
                    require_once dirname(__FILE__).'/importController.php';
                    exit;
                }elseif($params['action'] == 'record_output'){ // retrieve record from record_output, user on external server
                    require_once dirname(__FILE__).'/record_output.php';
                    exit;
                }
            }

            require_once dirname(__FILE__).'/../records/search/recordSearch.php';

            $response = recordSearch($system, $params);
        }else{ // unable to login, cannot access records
            $query = json_encode($params['q']);
            $response = ['status' => HEURIST_ERROR, 'message' => "We are unable to access the records within the ESTC database at this moment.<br>Please contact the Heurist team. Query is: {$query}"];
        }
    }else{ // cannot access ESTC DB
        $response = $isAllowed ? $system->getError() : ['status' => HEURIST_REQUEST_DENIED, 'message' => ESTC_ERROR_MSG];
    }

}elseif(isset($ESTC_ServerURL)){ // external server

    $baseURL = "{$ESTC_ServerURL}hserv/controller/LookupController.php?";

    if(array_key_exists('action', $params) && @$params['action'] == 'import_records'){

        $baseURL = "{$ESTC_ServerURL}hserv/controller/FrontController.php?"; // record_output
        $params = [];
        $params['action'] = 'record_output';
        $params['serviceType'] = 'ESTC';
        $params['format'] = 'json';
        $params['depth'] = '0';
        $params['db'] = 'ESTC_Helsinki_Bibliographic_Metadata';
        $params['org_db'] = $this->request['org_db'];
        $params['q'] = $this->request['q'];
        $params['rules'] = @$this->request['rules'];
        $url = $baseURL.http_build_query($params);// forward request to ESTC server

        // save file that produced with record_output.php from source to temp file
        $scratchPath = tempnam(HEURIST_SCRATCH_DIR, "_temp_");

        $filesize = saveURLasFile($url, $scratchPath);// perform external request and save results to temp file

        if($filesize > 0 && file_exists($scratchPath)){
            //read temp file, import record

            require_once __DIR__ . '/../records/import/importHeurist.php';

            $params = [
                'dbg' => $this->isDebug ? 1 : 0,
                'owner_id' => $this->system->getUserId(),
                'mapping_defs' => @$this->request['mapping']
            ];

            $res = \ImportHeurist::importRecords($scratchPath, $params2);
            if(is_bool($res) && $res === false){
                $response = $system->getError();
            }else{
                $this->lookupResponse = ["status"=>HEURIST_OK, "data"=> $res];
            }

            unlink($scratchPath);

        }else{
            $response = ['status' => HEURIST_ERROR, 'message' => "Cannot download records from the ESTC database. <br>{$baseURL} to {$scratchPath}<br><br>URL request: {$url}"];
        }

    }else{

        $url = $base_url.http_build_query($params);// forward request to ESTC server
        $response = loadRemoteURLContentWithRange($url, null, true, 60);
    }

    if($response===false){
        global $glb_curl_error;
        $error_code = (!empty($glb_curl_error)) ? $glb_curl_error : 'Error code: 500 Heurist Error';

        $msg = <<<MSG
        We are having trouble performing your request on the ESTC server.<br>
        Please try norrowing down the search with more specific criteria before running this request again.<br><br>
        If this problem persists, please contact the Heurist team.<br><br>
        $error_code<br>
        Request URL: $url<br><br>
        MSG;
        $response = ['status' => HEURIST_ERROR, 'message' => $msg];
    }
}else{ // no access
    $response = ['status' => HEURIST_REQUEST_DENIED, 'message' => ESTC_ERROR_MSG];
}

$response = json_encode($response);

header(CTYPE_JSON);
header(CONTENT_LENGTH . strlen($response));
exit($response);

/*if(@$params['serviceType'] == 'nomisma' && @$params['search_type'] == 'xml'){

    $xml_obj = new \SimpleXMLElement($remote_data, LIBXML_PARSEHUGE);

    $results = [];

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

}*/
