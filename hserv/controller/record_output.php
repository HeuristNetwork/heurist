<?php
/**
* record_output.php - Handler for records search and export
*
* It searches the records and outputs data in the required format.
* For usage see HRecordMgr.search_new in hapi.js or export routines.
*
* parameters
* db - The target Heurist database name.
* format - Output format for the records. Supported: geojson, json, csv, kml, xml, hml, gephi, iiif.
* linkmode - Specifies how links between records are handled. Options: direct, direct_links, none, all.
* prefs - An object containing format-specific parameters.
*
* prefs for csv:
*   csv_delimiter : (char) Delimiter character, e.g., ','.
*   csv_enclosure : (char) Field enclosure character, e.g., '"'.
*   csv_mvsep     : (char) Separator for multi-value fields, e.g., '|'.
*   csv_linebreak : (string) Line break style, e.g., 'nix' (\n), 'win' (\r\n), 'mac' (\r).
*   csv_header    : (boolean) If true, include a header row.
*   csv_headeronly: (boolean) If true, output only the header row.
*   fields        : (object) Defines specific fields to include, e.g., {rtid:[dtid1, dtid3, dtid2]} where rtid is record type ID and dtid is detail type ID.
*   include_term_ids        : (boolean) If true, include term IDs.
*   include_term_codes      : (boolean) If true, include term codes.
*   include_file_url        : (boolean) If true, include file URLs.
*   include_record_url_html : (boolean) If true, include HTML record URLs.
*   include_record_url_xml  : (boolean) If true, include XML record URLs.
*   include_term_hierarchy  : (boolean) If true, include term hierarchy information.
*   include_resource_titles : (boolean) If true, include titles of linked resources.
*   include_temporals       : (boolean) If true, include temporal data.
*
* prefs for json, xml:
*   zip     : (0|1) If 1, compress the output.
*   file    : (0|1) If 1, output as a downloadable file; otherwise, print to output.
*   defs    : (0|1) Include database definitions (Currently NOT USED).
*   restapi : (0|1) If 1, does not include database description and Heurist header, suitable for REST API responses.
*
* prefs for geojson, json:
*   extended: (0|1|2|3) Specifies the level of detail for JSON/GeoJSON output:
*             0 - As is (Heurist internal format).
*             1 - Interpretable format.
*             2 - Include concept codes and labels.
*             3 - Simple plain object for mediaViewer (only records with file fields are included).
*   leaflet : (true|false) If true, returns strict GeoJSON and timeline data as separate arrays, including only header fields (rec_ID, RecTypeID, rec_Title) and no other details.
*   simplify: (true|false) If true, simplifies geometry paths with more than 1000 vertices.
*
* datatable - Session ID for datatable integration. Controls behavior for requests from a datatable widget:
*             If >1 and "q" (query) is defined: Saves the query request in the session for the returned result set.
*             If >1 and "q" is not defined and "draw" is defined: Takes the query from the session.
*             If 1: Uses the "q" parameter directly for the search.
*
* @project     Heurist academic knowledge management system
* @package Controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

    use hserv\utilities\USanitize;
    use hserv\utilities\USystem;
    use hserv\records\export\RecordsExportCSV;

    require_once dirname(__FILE__).'/../../autoload.php';

    require_once dirname(__FILE__).'/../records/search/recordSearch.php';
    require_once dirname(__FILE__).'/../records/search/recordFile.php';
    require_once dirname(__FILE__).'/../structure/dbsTerms.php';
    require_once dirname(__FILE__).'/../../admin/verification/verifyValue.php';

    $response = array();

    if(isset($req_params)){ //if set array has been already modified in api.php
        $params = $req_params;
    }else{
        $params = USanitize::sanitizeInputArray();
    }

    if(@$params['postdata']){
        //in export csv all parameters send as json array in postdata
        $params = json_decode($params['postdata'], true);
    }

    if(!isset($system) || $system==null){

        $system = new hserv\System();

        if( ! $system->init(@$params['db']) ){
            //get error and response
            $system->errorExitApi();//exit from script
        }
    }

    set_time_limit(0);//no limit

    if(@$params['file_refs']){
        downloadFileReferences($system, $params['ids'], array_key_exists('essentials', $params));
        exit;
    }elseif(@$params['mapmarker_csv']){
        downloadMapMarkers($system, $params['ids']);
        exit;
    }elseif(array_key_exists('prepare', $params)){
        prepareParameters('export', $params);
        exit;
    }elseif(array_key_exists('preparedID', $params)){
        if(!retrieveParameters($system, 'export', $params)){
            $system->errorExitApi();
        }
    }

    if(!@$params['format']){
        $params['format'] = @$params['fmt'];
    }
    if(!@$params['format']){
        $params['format'] = 'json';
    }



    $search_params = array();
    $search_params['w'] = filter_var(@$params['w'], FILTER_SANITIZE_STRING);

    if(@$params['format']=='gephi' || @$params['format']=='geojson'){
        $search_params['limit'] = (@$params['limit']>0)?intval($params['limit']):null;
    }elseif(!(@$params['offset'] || @$params['limit'])){
        $search_params['needall'] = 1;  //search without limit of returned record count
    }

    //
    // search for single record by "recID", by set of "ids" or heurist query "q"
    //
    if(@$params['recID']>0){
        $search_params['q'] = array('ids'=>intval($params['recID']));
    }elseif(@$params['ids']){
        $search_params['q'] = array('ids'=>filter_var(implode(',', prepareIds($params['ids']) ), FILTER_SANITIZE_STRING));
    }elseif(@$params['iiif_image']){
        $params['format'] = 'iiif';
        $search_params['q'] = '*file @'.filter_var($params['iiif_image'],FILTER_SANITIZE_STRING);

    }else{
        $search_params['q'] = @$params['q'];
    }
    if($search_params['q']==null || $search_params['q']==''){
        $search_params['q'] = 'sortby:-m';//get all records
    }


    if(@$params['rules']!=null){
        $search_params['rules'] = $params['rules'];
        if(@$params['rulesonly']==true || @$params['rulesonly']==1){
            $search_params['rulesonly'] = 1;
        }
    }


    $is_csv = (@$params['format'] == 'csv');
    if(@$params['format']){
        //search only ids - all
        $search_params['detail'] = 'ids';
    }

    if(@$params['prefs']['csv_headeronly']===true){
        $response = array('status'=>HEURIST_OK,'data'=>array());
    }else{

//    datatable -   datatable session id  - returns json suitable for datatable ui component
//              >1 and "q" is defined - save query request in session to result set returned,
//              >1 and "q" not defined and "draw" is defined - takes ids/query from session
//              1 - use "q" parameter
        if(@$params['format']=='json' && @$params['datatable']>1){

            $dt_key = 'datatable'.$params['datatable'];

            if(@$params['q']==null){
                //restore query by id from session
                $search_params['q'] = $system->userGetPreference($dt_key);

                if($search_params['q']==null){
                    //query was removed
                    header(CTYPE_JSON);
                    echo json_encode(array('error'=>'Datatable session expired. Please refresh search'));
                    exit;
                }

                $search_by_type = '';
                $search_by_field = '';
                //search by record type
                if(is_array($params['columns'])){
                    foreach($params['columns'] as $idx=>$column){
                        if($column['data']=='rec_RecTypeID' && @$column['search']['value']!=''){
                            $search_by_type = '{"t":"'.$column['search']['value'].'"},';
                            break;
                        }
                    }
                }
                if(@$params['search']['value']!=''){
                      $search_by_field = '{"f":"'.addslashes($params['search']['value']).'"},';
                }
                if($search_by_type!='' || $search_by_field!=''){
                    $search_params['q'] = '['.$search_by_type.$search_by_field.$search_params['q'].']';

                    $search_params['detail'] = 'count';
                    $response = recordSearch($system, $search_params);//datatable search - reccount only
                    $search_params['detail'] = 'ids';

                    $params['recordsFiltered'] = $response['data']['count'];
                }

                if(@$params['start']>0){
                    $search_params['offset'] = $params['start'];
                }
                if($params['length']>0){
                    $search_params['limit'] = $params['length'];
                    $search_params['needall'] = 0;
                }

            }elseif(@$params['q']!=null){  //first request - save base filter
                //remove all other "datatableXXX" keys from session
                $dbname = $system->dbnameFull(); //dbnameFullWithHost
                if(@$_SESSION[$dbname]['ugr_Preferences']!=null){
                    $keys = array_keys($_SESSION[$dbname]['ugr_Preferences']);
                    if(is_array($keys)){
                        foreach ($keys as $key) {
                            if(strpos($key,'datatable')===0){
                                $_SESSION[$dbname]['ugr_Preferences'][$key] = null;
                                unset($_SESSION[$dbname]['ugr_Preferences'][$key]);
                            }
                        }
                    }
                }
                //save int session and exit
                user_setPreferences($system, array($dt_key=>$params['q']));
                //returns OK
                header(CTYPE_JSON);
                echo json_encode(array('status'=>HEURIST_OK));
                exit;
            }
        }
        $response = recordSearch($system, $search_params);//search ids
    }

    $system->defineConstant('DT_PARENT_ENTITY');
    $system->defineConstant('DT_START_DATE');
    $system->defineConstant('DT_END_DATE');
    $system->defineConstant('DT_SYMBOLOGY');

    $system->defineConstant('RT_TLCMAP_DATASET');
    $system->defineConstant('RT_MAP_LAYER');
    $system->defineConstant('RT_MAP_DOCUMENT');
    $system->defineConstant('DT_NAME');
    $system->defineConstant('DT_MAP_LAYER');
    $system->defineConstant('DT_MAP_BOOKMARK');
    $system->defineConstant('DT_ZOOM_KM_POINT');
    $system->defineConstant('DT_GEO_OBJECT');

    $res = true;

    if($is_csv){

        if(@$params['prefs']['csv_headeronly'])   //export record type template
        {
            RecordsExportCSV::output_header( $response, $params );
        }else{
            RecordsExportCSV::output( $response, $params );
        }

    }else{

            $allowed_formats = array('xml','geojson','gephi','iiif','json','rdf','html');
            $idx = array_search(strtolower($params['format']),$allowed_formats);

            if($idx===false || !($idx>0)){
                $idx = 0;
            }

            $classname = 'hserv\records\export\ExportRecords'.strtoupper($allowed_formats[$idx]);

            try{
                $outputHandler = new $classname($system);
            } catch (\Throwable $e) {
                echo $classname . ': ' . $e->getMessage();
            }

            if(!$outputHandler){
                $system->addError(HEURIST_INVALID_REQUEST, 'Wrong parameter "format": '.htmlspecialchars(@$params['format']));
                return false;
            }else{
                $res = $outputHandler->output( $response, $params );
            }
    }

    if(!$res) {
        $system->errorExitApi();
    }

    $system->dbclose();


/**
 * Writes file references out into CSV format.
 *
 * Retrieves details for specified uploaded files and outputs them as a CSV file.
 * The CSV includes information such as file ID, name, path, URL, description,
 * uploader, dates, and records referencing the file.
 *
 * @param \hserv\System $system Initialised Heurist system object.
 * @param string|array $ids File IDs to include (comma-separated string, array, or 'all').
 * @param bool $essentialOnly Whether to return the essential fields only (File IDs, name, path, size and referenced by)
 * @return void Outputs a CSV file or an HTML error message.
 */
function downloadFileReferences($system, $ids, $essentialOnly){

    if(empty($ids)){

        header(CTYPE_HTML);
        echo 'No file ids have been provided';
        exit;
    }

    $whereClause = '';
    if(is_array($ids) || (is_string($ids) && $ids != 'all')){ // change comma separated list into array
        $ids = prepareIds($ids);
        $whereClause = !empty($ids) ? ' WHERE ulf_ID IN ('. implode(',', $ids) .')' : '';
    }

    // Set headers
    $filename = $system->dbname() . '_File_References.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '";');
    header('Pragma: no-cache');
    header('Expires: ' . gmdate("D, d M Y H:i:s", time() - 3600));

    // open output handler
    $fd = fopen('php://output', 'w');
    if(!$fd){

        header(CTYPE_HTML);
        echo 'Unable to open temporary output for writing CSV.<br>Please contact the Heurist team.';
        exit;
    }

    $seperator = "\t";

    // retrieve file details
    $mysqli = $system->getMysqli();
    $fileQuery = "SELECT ulf_ID, ulf_FileName, ulf_ExternalFileReference, ulf_ObfuscatedFileID, ulf_FilePath, ulf_Description, ulf_MimeExt, ulf_FileSizeKB,
                    ugr_Name, ulf_Added, ulf_Modified, ulf_OrigFileName, ulf_Caption, ulf_Copyright, ulf_Copyowner
                   FROM recUploadedFiles
                   LEFT JOIN sysUGrps ON ulf_UploaderUGrpID = ugr_ID {$whereClause}";

    $resFiles = $mysqli->query($fileQuery);

    $errMessage = null;
    if (!$resFiles) {
        $errMessage = 'File record details could not be retrieved from database.<br><br>'
                        .(!empty($mysqli->error) ? $mysqli->error :'Unknown error');
    }else{
        $resultCount = mysql__found_rows($mysqli);
        if($resultCount == 0){
            $errMessage = 'Empty result set';
        }
    }

    if($errMessage!=null){
        fclose($fd);

        header(CTYPE_HTML);
        echo $errMessage;
        exit;
    }

    // return setup

    // write results
    $headers = ["Uploaded_File_ID", "Name", "Path", "File Size (in KB)", "Referenced by"];
    if($essentialOnly){
        $headers[] = "Obfuscated ID";
    }else{
        array_push($headers, ...["Obfuscated URL", "Description", "Caption", "Copyright", "Copy Owner", "File Type",  "Checksum", "Uploaded By", "Added On", "Last Modified", "Original file name", "New ref H-IDs"]);
    }
    fputcsv($fd, $headers, $seperator, "\"", "\\");

    /*
        [0] => File Name
        [1] => Link to external file
        [2] => Obfuscated File ID
        [3] => Local file path
        [4] => Description
        [5] => File Type
        [6] => File Size in KB
        [7] => Uploader Name
        [8] => Added On
        [9] => Last Modified
        [10] => Original file name
        [11] => Caption
        [12] => Copyright
        [13] => Copyowner
    */
    while ($details = $resFiles->fetch_row()){

        $id = array_shift($details);

        $name = !empty($details[0]) ? $details[0] : $details[1];
        $path = !empty($details[3]) ? $details[3] : 'External Source';
        $obfURL = empty($details[2]) ? 'MISSING' : HEURIST_BASE_URL . '?db=' . $system->dbname() . '&file=' . $details[2];
        $fileSize = $details[6] == 0 ? 'remote' : $details[6];

        $fullpath = !empty($details[0]) ? resolveFilePath( $details[3].$details[0] ) : '';
        $checksum = empty($fullpath) ? 'remote' : md5_file($fullpath);

        $usage_query = "SELECT dtl_RecID FROM recDetails WHERE dtl_UploadedFileID = {$id}";
        $recIDs = mysql__select_list2($mysqli, $usage_query);
        if(empty($recIDs)){
            $recIDs = [0];
        }

        $fields = [$id, $name, $path, $fileSize, implode('|', $recIDs)];
        if($essentialOnly){
            $fields[] = $details[2];
        }else{
            array_push($fields, ...[$obfURL, $details[4], $details[11], $details[12], $details[13], $details[5], $checksum, $details[7], $details[8], $details[9], $details[10], '']);
        }
        fputcsv($fd, $fields,  $seperator, "\"", "\\");
    }
    $resFiles->close();

    fclose($fd);

    exit;
}

/**
 * Writes record url details out into CSV format.
 *
 * Retrieves record url details and outputs them as a CSV file.
 * The CSV includes information such as record ID, title, url, and any field that contains the word "URL".
 *
 * @param \hserv\System $system Initialised Heurist system object.
 * @param string|array $ids Record IDs to include (comma-separated string or array).
 * @return void Outputs a CSV file or an HTML error message.
 */
function downloadMapMarkers($system, $ids){

    global $useRewriteRulesForRecordLink;

    $ids = prepareIds($ids);
    if(empty($ids)){

        header(CTYPE_HTML);
        echo 'No record ids have been provided';
        exit;
    }

    $accessOwnerIDs = $system->getUserGroupIds(); // all groups current user is a member
    if(!is_array($accessOwnerIDs)){
        $accessOwnerIDs = [];
    }

    array_push($accessOwnerIDs, 0); // everyone

    $accessCondition = count($accessOwnerIDs) === 1 ? "= {$accessOwnerIDs[0]}" : 'IN ('. implode(',', $accessOwnerIDs) .')';
    $accessCondition = '(rec_NonOwnerVisibility = "public"' . ($system->hasAccess() ? " OR (rec_NonOwnerVisibility != 'hidden' OR rec_OwnerUGrpID {$accessCondition}))" : ')');

    $whereClause = count($ids) > 1 ? ' rec_ID IN ('. implode(',', $ids) .')' : '';
    $whereClause = count($ids) == 1 ? " rec_ID = {$ids[0]}" : $whereClause;
    $whereClause = !empty($whereClause) ? "{$accessCondition} AND {$whereClause}" : $accessCondition;

    // Set headers
    $filename = $system->dbname() . '_MapMarkers.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '";');
    header('Pragma: no-cache');
    header('Expires: ' . gmdate("D, d M Y H:i:s", time() - 3600));

    // open output handler
    $fd = fopen('php://output', 'w');
    if(!$fd){

        header(CTYPE_HTML);
        echo 'Unable to open temporary output for writing CSV.<br>Please contact the Heurist team.';
        exit;
    }

    $seperator = "\t";

    // retrieve file details
    $mysqli = $system->getMysqli();
    $recodQuery = "SELECT rec_ID, rec_Title, rec_URL, rec_RecTypeID
                   FROM Records
                   WHERE {$whereClause}";

    $resRecords = $mysqli->query($recodQuery);

    $errMessage = null;
    if(!$resRecords){
        $errMessage = 'Record details could not be retrieved from database.<br><br>'
                        .(!empty($mysqli->error) ? $mysqli->error :'Unknown error');
    }else{
        $resultCount = mysql__found_rows($mysqli);
        if($resultCount == 0){
            $errMessage = 'Empty result set';
        }
    }

    if($errMessage!=null){
        fclose($fd);

        header(CTYPE_HTML);
        echo $errMessage;
        exit;
    }

    $baseRecLink = $useRewriteRulesForRecordLink || USystem::checkRewriteRuleEnabled() ?
        HEURIST_BASE_URL . '?fmt=html&db='. $system->dbname() .'&recID=' :
        HEURIST_BASE_URL . $system->dbname() .'/view/';

    $headings = ['Record ID', 'Record Title', 'Record URL', 'Record Link'];
    $handledFields = [];
    $urlFieldQuery = "SELECT rst_DetailTypeID FROM defRecStructure WHERE LOWER(rst_DisplayName) LIKE '%URL%' AND rst_RecTypeID = ";
    $rows = [];

    foreach($ids as $recID){

        $recTypeID = mysql__select_value($mysqli, 'SELECT rec_RecTypeID FROM Records WHERE rec_ID = ?', ['i', $recID]);
        if(!array_key_exists($recTypeID, $handledFields)){

            $handledFields[$recTypeID] = mysql__select_list2($mysqli, "{$urlFieldQuery} {$recTypeID}", 'intval');
            if(empty($handledFields[$recTypeID])){
                continue;
            }

            $dtyFilter = count($handledFields[$recTypeID]) === 1 ? "rst_DetailTypeID = {$handledFields[$recTypeID][0]}" : 'rst_DetailTypeID IN ('. implode(',', $handledFields[$recTypeID]) .')';
            $titles = mysql__select_list2($mysqli, "SELECT rst_DisplayName FROM defRecStructure WHERE rst_RecTypeID = {$recTypeID} AND {$dtyFilter}");

            $titles = array_filter($titles, function($heading) use ($headings){ return !in_array($heading, $headings); });
            array_push($headings, ...$titles);

        }elseif(empty($handledFields[$recTypeID])){
            continue;
        }

        $row = [];
        
        $fieldFilter = count($handledFields[$recTypeID]) === 1 ? "dtl_DetailTypeID = {$handledFields[$recTypeID][0]}" : 'dtl_DetailTypeID IN ('. implode(',', $handledFields[$recTypeID]) .')';
        $valueResults = $mysqli->query("SELECT rst_DisplayName, dtl_Value FROM recDetails WHERE dtl_RecID = {$recID} AND {$fieldFilter}");

        if(mysql__found_rows($mysqli) === 0 || !$valueResults){
            continue;
        }

        while($valueRow = $valueResults->fetch_row()){
            if(!array_key_exists($valueRow[0], $row)){
                $row[$valueRow[0]] = $valueRow[1];
            }else{
                $row[$valueRow[0]] .= " | {$valueRow[1]}";
            }
        }

        $rows[$recID] = $row;
    }

    // write results
    fputcsv($fd, $headings, $seperator, "\"", "\\");

    /*
        [0] => Record ID
        [1] => Record Title
        [2] => Record URL
        [3, ...] => Record Fields containing the word "URL"
    */
    while($details = $resRecords->fetch_row()){

        $row = [];
        foreach($headings as $heading){

            $recID = $details[0];
            $recTypeID = $details[3];

            $value = '';
            switch($heading){
                case 'Record ID':
                    $value = $details[0];
                    break;
                case 'Record Title':
                    $value = $details[1];
                    break;
                case 'Record URL':
                    $value = $details[2];
                    break;
                case 'Record Link':
                    $value = "{$baseRecLink}{$recID}";
                    break;
                default:
                    $dtyID = array_search($heading, $handledFields[$recTypeID], true);
                    if($dtyID !== false){
                        $value = $rows[$recID][$dtyID];
                    }
                    break;
            }

            $row[] = $value;
        }

        fputcsv($fd, $row, $seperator, "\"", "\\");
    }
    $resRecords->close();

    fclose($fd);

    exit;
}

/**
 * Store parameters to be used in an upcoming server call, this is done to avoid excessively long URLs that lead to 414 errors
 * @todo: move location to somewhere more accessible, include retrieveParameters
 *
 * @param string $type Process type, e.g. 'export' or 'import'
 * @param array $parameters Parameters to be saved, ignores 'prepare', 'replace' and 'DBGSESSID' keys
 * @return void
 */
function prepareParameters($type, $parameters){

    if(empty($parameters)){
        dataOutput(['status' => HEURIST_OK, 'data' => null]);
    }

    $id = !is_numeric(@$parameters['prepare']) || intval($parameters['prepare']) <= 0 ? time() : intval($parameters['prepare']);

    /*
    0 - Complete replace
    1 - Merge + maintain existing
    2 - Merge + replace existing
    */
    $replace = !is_numeric(@$parameters['replace']) ? 0 : intval($parameters['replace']);
    $replace = $replace > 2 || $replace < 0 ? 0 : $replace;

    $paramsFile = HEURIST_SCRATCH_DIR . "{$type}_{$id}.json";//yml

    $storedParameters = [];
    if(file_exists($paramsFile)){
        $storedParameters = file_get_contents($paramsFile);

        $storedParameters = json_decode($storedParameters, true);
        $storedParameters = json_last_error() !== JSON_ERROR_NONE ? [] : $storedParameters;
    }

    foreach($parameters as $key => $value){

        if($key === 'prepare' || $key === 'replace' || $key === 'DBGSESSID'){
            continue;
        }elseif($replace !== 0 && array_key_exists($key, $storedParameters) && $key !== 'db'){
            if(is_array($storedParameters[$key]) && is_array($value)){
                $storedParameters[$key] = $replace === 1 ? array_merge($storedParameters[$key], $value) : array_merge($value, $storedParameters[$key]);
                continue;
            }elseif(is_string($storedParameters[$key]) && is_string($value)){
                $storedParameters[$key] .= $value;
                continue;
            }
        }

        $storedParameters[$key] = $value;
    }

    file_put_contents($paramsFile, json_encode($storedParameters));

    dataOutput(['status' => HEURIST_OK, 'data' => $id]);
}

/**
 * Retrieve previously saved parameters, this will not replace existing keys
 *
 * @param string $type Process type, e.g. 'export' or 'import'
 * @param array $parameters Parameters array to be updated with stored parameters
 * @return bool
 */
function retrieveParameters($system, $type, &$parameters){

    if(!is_numeric(@$parameters['preparedID'])){
        $system->addError(HEURIST_INVALID_REQUEST, 'Wrong parameter preparedID. Must be integer');
        return false;
    }

    $id = intval($parameters['preparedID']);

    $paramsFile = HEURIST_SCRATCH_DIR . "{$type}_{$id}.json";//yml

    if(!file_exists($paramsFile)){
        $system->addError(HEURIST_INVALID_REQUEST, 'Query parameters file not found. Either parameter preparedID is wrong or session expired');
        return false;
    }

    $storedParameters = file_get_contents($paramsFile);

    $storedParameters = json_decode($storedParameters, true);
    $storedParameters = json_last_error() !== JSON_ERROR_NONE ? [] : $storedParameters;

    foreach($storedParameters as $key => $value){
        if(array_key_exists($key, $parameters)){
            continue;
        }
        $parameters[$key] = $value;
    }

    fileDelete($paramsFile);

    return true;
}
?>
