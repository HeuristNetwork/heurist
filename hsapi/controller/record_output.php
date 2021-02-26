<?php

    /**
    * Application interface. See hRecordMgr in hapi.js
    * Record search and output in required format
    * used in recordExportCSV.js
    * 
    * 
    * parameters
    * db - heurist database
    * format = geojson|json|csv|kml|xml|hml|gephi
    * linkmode = direct, direct_links, none, all
    * prefs:{ format specific parameters }, }
    * 
    * prefs for csv
                csv_delimiter :','
                csv_enclosure :'""
                csv_mvsep     :'|',
                csv_linebreak :'nix',
                csv_header    :true
                csv_headeronly:false
    *           fields        : {rtid:[dtid1, dtid3, dtid2]}
    * 
    * prefs for json,xml
    *           zip  : 0|1  compress
    *           file : 0|1  output as file or ptintout
    *           defs : 0|1  include database definitions
    *           resapi: 0|1  not include db description and heurist header
    * 
    * prefs for geojson, json
    *   extended 0 as is (in heurist internal format), 1 - interpretable, 2 include concept code and labels
    *   leaflet - true|false returns strict geojson and timeline data as two separate arrays, without details, only header fields rec_ID, RecTypeID and rec_Title
    *   simplify  true|false simplify  paths with more than 1000 vertices 
    * 
    * datatable -   datatable session id
    *               >1 and "q" is defined - save query request in session to result set returned, 
    *               >1 and "q" not defined and "draw" is defined - takes query from session
    *                1 - use "q" parameter
    * 
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
    require_once (dirname(__FILE__).'/../dbaccess/db_recsearch.php');
    require_once (dirname(__FILE__).'/../dbaccess/utils_db.php');
    require_once (dirname(__FILE__).'/../structure/dbsTerms.php');
    require_once (dirname(__FILE__).'/../../common/php/Temporal.php');
    require_once (dirname(__FILE__).'/../../admin/verification/verifyValue.php');

    require_once (dirname(__FILE__).'/../dbaccess/recordsExport.php');
    
    $response = array();

    $system = new System();
    
    //global variable to keep defs
    $defRecTypes = null;
    $defDetailtypes = null;
    $defTerms = null;

    if(@$_REQUEST['postdata']){
        //in export csv all parameters send as json array in postdata 
        $params = json_decode($_REQUEST['postdata'], true);
    }else{
        $params = $_REQUEST;
    }
    
    
    if( ! $system->init(@$params['db']) ){
        //get error and response
        $system->error_exit_api(); //exit from script
    }
    
    if(!@$params['format']) $params['format'] = 'json';
    
    $search_params = array();
    $search_params['w'] = @$params['w'];
    
    if(@$params['format']=='gephi'){
        $search_params['limit'] = (@$params['limit']>0)?$params['limit']:null;
    }else
    if(!(@$params['offset'] || @$params['limit'])){
        $search_params['needall'] = 1;  //search without limit of returned record count
    }
    
    //
    // search for single record by "recID", by set of "ids" or heurist query "q"
    //
    if(@$params['recID']>0){
        $search_params['q'] = array('ids'=>$params['recID']);
    }else if(@$params['ids']){
        $search_params['q'] = array('ids'=>implode(',', prepareIds($params['ids']) ));
    }else{
        $search_params['q'] = @$params['q'];
    }
    if($search_params['q']==null || $search_params['q']==''){
        $search_params['q'] = 'sortby:-m'; //get all records
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

/* DEBUG    
    if(is_array($search_params['q'])){
        error_log(json_encode($search_params['q']));    
    }else{
        error_log($search_params['q']);    
    }
*/
    if(@$params['prefs']['csv_headeronly']===true){
        $response = array('status'=>HEURIST_OK,'data'=>array());
        //$search_params['limit'] = 1;
        //$search_params['needall'] = 0;
    }else{
        
//    datatable -   datatable session id  - returns json suitable for datatable ui component
//              >1 and "q" is defined - save query request in session to result set returned, 
//              >1 and "q" not defined and "draw" is defined - takes ids/query from session
//              1 - use "q" parameter
        if(@$params['format']=='json' && @$params['datatable']>1){
            
            $dt_key = 'datatable'.$params['datatable'];
            
            if(@$params['q']==null){
                //restore query by id from session
                $search_params['q'] = $system->user_GetPreference($dt_key);
                
                if($search_params['q']==null){
                    //query was removed 
                    header( 'Content-Type: application/json');    
                    echo json_encode(array('error'=>'Datatable session expired. Please refresh search'));
                    exit();
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
                    $response = recordSearch($system, $search_params);
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
                
            }else if(@$params['q']!=null){  //first request - save base filter
                //remove all other datatable keys from session
                $dbname = $system->dbname_full();
                $keys = array_keys(@$_SESSION[$dbname]["ugr_Preferences"]);
                if(is_array($keys))
                foreach ($keys as $key) {
                    if(strpos($key,'datatable')===0){
                        $_SESSION[$dbname]["ugr_Preferences"][$key] = null;    
                        unset($_SESSION[$dbname]["ugr_Preferences"][$key]);
                    }
                }
                //save int session and exit
                user_setPreferences($system, array($dt_key=>$params['q']));
                //returns OK
                header( 'Content-Type: application/json');    
                echo json_encode(array('status'=>HEURIST_OK));
                exit();
            }
        }
        
        
        $response = recordSearch($system, $search_params);
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
        
    if($is_csv){
        
        if(@$params['prefs']['csv_headeronly'])   //export record type template
        {
            output_HeaderOnly($system, $response, $params);
        }else{
            output_CSV($system, $response, $params);    
        }
        
    }else{
        
        if(!RecordsExport::output( $response, $params )) {
            $system->error_exit_api();
        }
        
    }
exit();

/*
$data    array('status'=>HEURIST_OK,
                                'data'=> array(
                                'queryid'=>@$params['id'],  //query unqiue id
                                'entityName'=>'Records',
                                'count'=>$total_count_rows,
                                'offset'=>get_offset($params),
                                'reccount'=>count($records),
                                'records'=>$records));
                                
if parameter prefs.fields is defined it creates separate file for every record type
                               
fields {rtid:{id, url, title, dt1, dt2, ....  dt4:resource_rt1, dt4:resource_rt2  } }                               
                               
for constrained resource fields we use "dt#:rt#"                                
@todo for enum fields use dt#:code,dt#:id,dt#:label
                               
NOTE: fastest way it simple concatenation in comparison to fputcsv and implode. We use fputcsv
*/
function output_CSV($system, $data, $params){
    global $defRecTypes;

    if (!($data && @$data['status']==HEURIST_OK)){
        print print_r($data, true); //print out error array
        return;
    }

    $data = $data['data'];

    if(!(@$data['reccount']>0)){
        print 'EMPTY RESULT SET'; //'empty result set';
        return;
    }

    $isJoinTable = (isset($params['prefs']['join_record_types']) && $params['prefs']['join_record_types']) ? true : false;

    // Get the main record type IDs.
    $mainRecordTypeIDs = [];
    if (isset($params['prefs']['main_record_type_ids'])) {
        $mainRecordTypeIDs = $params['prefs']['main_record_type_ids'];
    } else {
        print 'No field selected from the main record type';
        return;
    }

    $include_term_label_and_code = true;
    $include_term_ids = (@$params['prefs']['include_term_ids']==1);
    $include_term_codes = (@$params['prefs']['include_term_codes']==1);
    $include_resource_titles =  (@$params['prefs']['include_resource_titles']==1);
    $include_term_hierarchy = (@$params['prefs']['include_term_hierarchy']==1);

    $fields = @$params['prefs']['fields'];
    $details = array();  //array of detail fields included into output
    $relmarker_details = array(); //relmarker fields included into output

    if($defRecTypes==null) $defRecTypes = dbs_GetRectypeStructures($system, null, 2);
    $idx_name = $defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];
    $idx_dtype = $defRecTypes['typedefs']['dtFieldNamesToIndex']['dty_Type'];
    $idx_term_tree = $defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_FilteredJsonTermIDTree'];
    $idx_term_nosel = $defRecTypes['typedefs']['dtFieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];


    if($include_term_label_and_code){
        $defTerms = dbs_GetTerms($system);
        $defTerms = new DbsTerms($system, $defTerms);
    }

    // Track column indices for advanced option fields.
    $groupFields = [];
    $sortFields = [];
    $countFields = [];
    $sumFields = [];
    $percentageFields = [];
    $groupColIndices = [];
    $sortColIndices = [];
    $sortOrders = [];
    $countColIndices = [];
    $sumColIndices = [];
    $percentageColIndices = [];
    
    $has_advanced = false;
    $need_check_memory = true;

    if (isset($params['prefs']['advanced_options']) || is_array($params['prefs']['advanced_options'])) {
        foreach ($params['prefs']['advanced_options'] as $fieldCode => $option) {
            $codeParts = explode(':', $fieldCode);
            if ($codeParts > 1) {
                $recordTypeID = $codeParts[count($codeParts) - 2];
                $fieldID = $codeParts[count($codeParts) - 1];
                $fieldKey = $recordTypeID . ':' . $fieldID;
                if (isset($option['total'])) {
                    if ($option['total'] === 'group') {
                        $groupFields[] = $fieldKey;
                        $has_advanced = true;
                    } elseif ($option['total'] === 'sum') {
                        $sumFields[]  = $fieldKey;
                        $has_advanced = true;
                    } elseif ($option['total'] === 'count') {
                        $countFields[]  = $fieldKey;
                        $has_advanced = true;
                    }
                }
                if (!empty($option['sort'])) {
                    $sortFields[$fieldKey] = $option['sort'];
                        $has_advanced = true;
                }

                if (isset($option['use_percentage']) && $option['use_percentage']) {
                    $percentageFields[] = $fieldKey;
                        $has_advanced = true;
                }
            }
        }
        
        
    }

    $memory_limit = get_php_bytes('memory_limit');

    //create header
    $any_rectype = null;
    $headers = array();
    $columnInfo = [];
    if($fields){
        foreach($fields as $rt=>$flds){

            //always include ID field into output
            if($flds[0]!='rec_ID') array_unshift($flds, 'rec_ID');
            $fields[$rt] = $flds;

            $details[$rt] = array();
            $headers[$rt] = array();
            $relmarker_details[$rt] = array();
            $columnInfo[$rt] = [];

            foreach($flds as $dt_id){

                $csvColIndex = null;
                $fieldFullID = $dt_id;

                $constr_rt_id = 0;
                if(strpos($dt_id,':')>0){ //for constrained resource fields
                    //example author:person or organization
                    list($dt_id, $constr_rt_id) = explode(':',$dt_id);
                }

                $field_name_title = '';

                if(is_numeric($dt_id) && $dt_id>0){

                    if($dt_id==DT_PARENT_ENTITY){
                        $field_name = 'Parent entity';
                        $field_type = 'resource';
                    }else{
                        //get field name from structure
                        $field_name = $defRecTypes['typedefs'][$rt]['dtFields'][$dt_id][$idx_name];
                        $field_type = $defRecTypes['typedefs'][$rt]['dtFields'][$dt_id][$idx_dtype];
                    }
                    if($constr_rt_id>0){
                        $rectypename_is_in_fieldname = (strpos(strtolower($field_name), 
                            strtolower($defRecTypes['names'][$constr_rt_id]))!==false);
                        $field_name_title = $field_name.' '
                        //.($rectypename_is_in_fieldname?'':($defRecTypes['names'][$constr_rt_id].' '))
                        .'RecordTitle';
                        $field_name = $field_name.($rectypename_is_in_fieldname
                            ?'':' ('.$defRecTypes['names'][$constr_rt_id].')').' H-ID';
                    }else{
                        $field_name_title = $field_name.' RecordTitle';
                    }
                    if($field_type=='relmarker'){
                        $relmarker_details[$rt][$dt_id] = $constr_rt_id; 
                    }else{
                        array_push($details[$rt], $dt_id);    
                    }

                }else{
                    $field_type = null;

                    if($dt_id=='rec_ID'){
                        if($rt>0){
                            $field_name = $defRecTypes['names'][$rt].' H-ID';
                        }else{
                            $field_name = 'H-ID';
                            $any_rectype = $rt;
                        }
                    }else{
                        $field_name = $dt_id; //record header field
                    }
                }

                if($field_type=='enum' || $field_type=='relationtype'){

                    array_push($headers[$rt], $field_name);  //labels are always included           
                    $csvColIndex = count($headers[$rt]) - 1;
                    $columnInfo[$rt][] = [
                        'index' => $csvColIndex,
                        'type' => 'value',
                        'field_id' => $fieldFullID,
                    ];

                    if($include_term_ids){
                        array_push($headers[$rt], $field_name.' ID');
                        $columnInfo[$rt][] = [
                            'index' => count($headers[$rt]) - 1,
                            'type' => 'term_id',
                            'field_id' => $fieldFullID,
                        ];
                    }

                    if($include_term_codes){
                        array_push($headers[$rt], $field_name.' StdCode' );
                        $columnInfo[$rt][] = [
                            'index' => count($headers[$rt]) - 1,
                            'type' => 'term_code',
                            'field_id' => $fieldFullID,
                        ];
                    }


                }else{
                    array_push($headers[$rt], $field_name);                
                    $csvColIndex = count($headers[$rt]) - 1;
                    $columnInfo[$rt][] = [
                        'index' => $csvColIndex,
                        'type' => 'value',
                        'field_id' => $fieldFullID,
                    ];
                }

                //add title for resource fields
                if($include_resource_titles && ($field_type=='resource' || $field_type=='relmarker')){

                    array_push($headers[$rt], $field_name_title);
                    $columnInfo[$rt][] = [
                        'index' => count($headers[$rt]) - 1,
                        'type' => 'resource_title',
                        'field_id' => $fieldFullID,
                    ];
                }

                // Save column index for advanced options.
                if ($csvColIndex !== null) {
                    $fieldKey = $rt . ':' . $dt_id;
                    if (in_array($fieldKey, $groupFields)) {
                        if (!isset($groupColIndices[$rt])) {
                            $groupColIndices[$rt] = [];
                        }
                        $groupColIndices[$rt][] = $csvColIndex;
                    }
                    if (in_array($fieldKey, $countFields)) {
                        if (!isset($countColIndices[$rt])) {
                            $countColIndices[$rt] = [];
                        }
                        $countColIndices[$rt][] = $csvColIndex;
                        $headers[$rt][$csvColIndex] = 'Count of ' . $headers[$rt][$csvColIndex];
                    }
                    if (in_array($fieldKey, $sumFields)) {
                        if (!isset($sumColIndices[$rt])) {
                            $sumColIndices[$rt] = [];
                        }
                        $sumColIndices[$rt][] = $csvColIndex;
                        $headers[$rt][$csvColIndex] = 'Sum of ' . $headers[$rt][$csvColIndex];
                    }
                    if (in_array($fieldKey, $percentageFields)) {
                        if (!isset($percentageColIndices[$rt])) {
                            $percentageColIndices[$rt] = [];
                        }
                        $percentageColIndices[$rt][] = $csvColIndex;
                    }
                    if (!empty($sortFields[$fieldKey])) {
                        if (!isset($sortColIndices[$rt])) {
                            $sortColIndices[$rt] = [];
                        }
                        if (!isset($sortOrders[$rt])) {
                            $sortOrders[$rt] = [];
                        }
                        $sortColIndices[$rt][] = $csvColIndex;
                        $sortOrders[$rt][] = $sortFields[$fieldKey];
                    }
                }
            }
        }
    }

    $csv_delimiter =  $params['prefs']['csv_delimiter']?$params['prefs']['csv_delimiter']:',';
    $csv_enclosure =  $params['prefs']['csv_enclosure']?$params['prefs']['csv_enclosure']:'"';
    $csv_mvsep =  $params['prefs']['csv_mvsep']?$params['prefs']['csv_mvsep']:'|';
    $csv_linebreak =  $params['prefs']['csv_linebreak']?$params['prefs']['csv_linebreak']:'nix'; //not used
    $csv_header =  $params['prefs']['csv_header']?$params['prefs']['csv_header']:true;

    //------------
    $records = $data['records'];    

    $streams = array(); //one per record type
    $rt_counts = array();
    $csvData = [];

    $error_log = array();
    $error_log[] = 'Total rec count '.count($records);

    $idx = 0;
    while ($idx<count($records)){ //replace to WHILE

        $recID = $records[$idx];
        $record = recordSearchByID($system, $recID, false);
        $rty_ID = ($any_rectype!=null)?$any_rectype :$record['rec_RecTypeID'];

        $idx++;

        if(!@$fields[$rty_ID]) continue; //none of fields for this record type marked to output


        if($has_advanced){ // everything is putting into memory
            
            if (!isset($csvData[$rty_ID])) {
                $csvData[$rty_ID] = [];
                if($csv_header) {
                    $csvData[$rty_ID][] = $headers[$rty_ID];
                }

                $rt_counts[$rty_ID] = 1;
            } else {
                $rt_counts[$rty_ID]++;
            }
            
        }else {        
            if(!@$streams[$rty_ID]){
                // create a temporary file
                $fd = fopen('php://temp/maxmemory:1048576', 'w');  //less than 1MB in memory otherwise as temp file 
                if (false === $fd) {
                    $system->error_exit_api('Failed to create temporary file for csv output');
                }        
                $streams[$rty_ID] = $fd;
                
                //write header
                if($csv_header)
                    fputcsv($fd, $headers[$rty_ID], $csv_delimiter, $csv_enclosure);
                
                $rt_counts[$rty_ID] = 1;
            }else{
                $fd = $streams[$rty_ID];
                
                $rt_counts[$rty_ID]++;
            }
        }

        if(count(@$details[$rty_ID])>0){
            //fills $record
            recordSearchDetails($system, $record, $details[$rty_ID]);
        }
        if(count(@$relmarker_details[$rty_ID])>0){
            $related_recs = recordSearchRelated($system, array($recID), 0);
            if(@$related_recs['status']==HEURIST_OK){
                $related_recs = $related_recs['data'];
            }else{
                $related_recs = array();    
            }
        }else{
            $related_recs = array();
        }

        //prepare output array
        $record_row = array();
        foreach($fields[$rty_ID] as $dt_id){

            //suppl.fields for enum and resource fields
            $enum_label = array();
            $enum_code = array();
            $resource_titles = array();

            $constr_rt_id = 0;
            if(strpos($dt_id,':')>0){ //for constrained resource fields
                list($dt_id, $constr_rt_id) = explode(':', $dt_id);
            }

            if(is_numeric($dt_id) && $dt_id>0){

                if ($constr_rt_id>0 && @$relmarker_details[$rty_ID][$dt_id]==$constr_rt_id) {  //relation

                    $vals = array();

                    foreach($related_recs['direct'] as $relation){
                        $target_rt = $related_recs['headers'][$relation->targetID][1];
                        if( $constr_rt_id==$target_rt && $relation->trmID>0){ //contrained rt and allowed relation type

                            $all_terms = $defRecTypes['typedefs'][$rty_ID]['dtFields'][$dt_id][$idx_term_tree];
                            $nonsel_terms = $defRecTypes['typedefs'][$rty_ID]['dtFields'][$dt_id][$idx_term_nosel];
                            $is_allowed = VerifyValue::isValidTerm($all_terms, $nonsel_terms, $relation->trmID, $dt_id);    

                            if($is_allowed){
                                //if record type among selected -  add record to list to be exported
                                //otherwise export only ID  as field "Rectype H-ID"
                                if($fields[$target_rt]){ //record type exists in output
                                    if(!in_array($relation->targetID, $records)){
                                        array_push($records, $relation->targetID);  //add to be exported  
                                    }
                                }
                                $vals[] = $relation->targetID;

                                if($include_resource_titles){
                                    $resource_titles[] = $related_recs['headers'][$relation->targetID][0];
                                }
                            }
                        }
                    }//foreach

                    //reverse will work only in case source record has detail id as in target
                    foreach($related_recs['reverse'] as $relation){
                        $source_rt = $related_recs['headers'][$relation->sourceID][1];
                        if( $constr_rt_id==$source_rt && $relation->trmID>0
                        && @$defRecTypes['typedefs'][$source_rt]['dtFields'][$dt_id]
                        ){ //contrained rt and allowed relation type

                            $all_terms = $defRecTypes['typedefs'][$source_rt]['dtFields'][$dt_id][$idx_term_tree];
                            $nonsel_terms = $defRecTypes['typedefs'][$source_rt]['dtFields'][$dt_id][$idx_term_nosel];
                            $is_allowed = VerifyValue::isValidTerm($all_terms, $nonsel_terms, $relation->trmID, $dt_id);    

                            if($is_allowed){
                                //if record type among selected -  add record to list to be exported
                                //otherwise export only ID  as field "Rectype H-ID"
                                if($fields[$source_rt]){ //record type exists in output
                                    if(!in_array($relation->sourceID, $records)){
                                        array_push($records, $relation->sourceID);  //add to be exported  
                                    }
                                }
                                $vals[] = $relation->sourceID;

                                if($include_resource_titles){
                                    $resource_titles[] = $related_recs['headers'][$relation->sourceID][0];
                                }
                            }
                        }
                    }                    

                    $value = implode($csv_mvsep, $vals);

                    if($include_resource_titles && count($vals)<1){ //empty value
                        $resource_titles[] = '';
                    }

                }else{

                    if($dt_id == DT_PARENT_ENTITY){
                        $dt_type = 'resource';
                    }else{
                        $dt_type = $defRecTypes['typedefs'][$rty_ID]['dtFields'][$dt_id][$idx_dtype];    
                    }

                    $values = @$record['details'][$dt_id];

                    if(isset($values)){

                        //$values = array_values($values); //get plain array
                        $vals = array();

                        if($dt_type=="resource"){

                            //if record type among selected -  add record to list to be exported
                            //otherwise export only ID (and optionally title)  as field "Rectype H-ID"
                            foreach($values as $val){
                                if( (!($constr_rt_id>0)) || $constr_rt_id==$val['type'] ){ //unconstrained or exact required rt

                                    if($fields[$val['type']]){ //record type exists in output
                                        if(!in_array($val['id'], $records)){
                                            array_push($records, $val['id']);  //add to be exported  
                                        }
                                    }
                                    $vals[] = $val['id'];

                                    if($include_resource_titles){
                                        $resource_titles[] = $val['title'];
                                    }
                                }
                            }
                        }else if($dt_type=='geo'){
                            foreach($values as $val){
                                $vals[] = $val['geo']['wkt'];
                            }
                        }else if($dt_type=='file'){
                            foreach($values as $val){
                                $vals[] = $val['file']['ulf_ObfuscatedFileID'];
                            }                        
                        }else if($dt_type=='date'){
                            foreach($values as $val){
                                $vals[] = temporalToHumanReadableString(trim($val));
                            }                        
                        }else if($dt_type=='enum' || $dt_type=='relationtype'){

                            if(count($values)>0){
                                foreach($values as $val){
                                    $enum_label[] = $defTerms->getTermLabel($val, $include_term_hierarchy);
                                    // @$defTerms[$val][$idx_term_label]?$defTerms[$val][$idx_term_label]:'';
                                    $enum_code[] = $defTerms->getTermCode($val);
                                    //@$defTerms[$val][$idx_term_code]?$defTerms[$val][$idx_term_code]:'';
                                }                        
                            }else{
                                $enum_label[] = '';
                                $enum_code[] = ''; 
                            }
                            $vals = $values;
                        }else{
                            $vals = $values;
                        }

                        $value = implode($csv_mvsep, $vals);
                    }else{
                        $value = null;
                    }

                    //empty values
                    if($value === null){
                        if($dt_type=='enum' || $dt_type=='relationtype'){

                            $enum_label[] = '';
                            $enum_code[] = ''; 

                        }else if($include_resource_titles && $dt_type=='resource'){
                            $resource_titles[] = '';
                        }
                    }

                }

            }else if ($dt_id=='rec_Tags'){

                $value = recordSearchPersonalTags($system, $recID);
                $value = ($value===null)?'':implode($csv_mvsep, $value);

            }else{
                $value = @$record[$dt_id]; //from record header
            }
            if($value===null) $value = '';                       


            if(count($enum_label)>0){
                $record_row[] = implode($csv_mvsep,$enum_label);    
                if($include_term_ids) $record_row[] = $value;
                if($include_term_codes) $record_row[] = implode($csv_mvsep,$enum_code);    
            }else {
                $record_row[] = $value;

                if (count($resource_titles)>0){
                    $record_row[] = implode($csv_mvsep,$resource_titles);    
                }
            }

        }//for fields

        // write the data to csv
        if(count($record_row)>0) {
            if($has_advanced){
                $csvData[$rty_ID][] = $record_row;    
                
                if($need_check_memory){
                    $cnt = count($records);
                    
                    if($cnt>2000){
                        
                        if(strlen(implode(',',$record_row))*$cnt*1.5>$memory_limit){
                            $system->error_exit_api('Sorry, this export exceeds the limit set for this server. Please reduce the number of records or the number of fields selected');                                            
                        }
                        
                    }
                    $need_check_memory = false;
                }
                
                
            }else{
                fputcsv($fd, $record_row, $csv_delimiter, $csv_enclosure);    
            }
        }

    }//for records

    // Join csv tables.
    if ($isJoinTable && !empty($mainRecordTypeIDs)) {
        $mainRecordTypeID = $mainRecordTypeIDs[0];
        if (!empty($csvData[$mainRecordTypeID]) && !empty($columnInfo[$mainRecordTypeID])) {
            $csvData = [
                $mainRecordTypeID => createJointCSVTables($csvData, $columnInfo, $mainRecordTypeID, $csv_mvsep, $csv_header),
            ];

            // Change advanced option column indices.
            $groupColIndices = changeAdvancedOptionColumnIndex($groupColIndices, $mainRecordTypeID, $columnInfo);
            $sumColIndices = changeAdvancedOptionColumnIndex($sumColIndices, $mainRecordTypeID, $columnInfo);
            $countColIndices = changeAdvancedOptionColumnIndex($countColIndices, $mainRecordTypeID, $columnInfo);
            $percentageColIndices = changeAdvancedOptionColumnIndex($percentageColIndices, $mainRecordTypeID, $columnInfo);
            $sortColIndices = changeAdvancedOptionColumnIndex($sortColIndices, $mainRecordTypeID, $columnInfo);
            $sortOrders = changeSortOrders($sortOrders, $mainRecordTypeID);
        }
    }

    // Save data to streams.
    if ($has_advanced && !empty($csvData)) {
        foreach ($csvData as $recordTypeID => $rows) {
            $streams[$recordTypeID] = fopen('php://temp/maxmemory:1048576', 'w');

            if (count($rows) > 0) {
                if ($csv_header) {
                    $headerRow = array_shift($rows);
                    if (!empty($percentageColIndices[$recordTypeID])) {
                        $headerRow = usePercentageForCSVHeaders($headerRow, $percentageColIndices[$recordTypeID]);
                    }
                    fputcsv($streams[$recordTypeID], $headerRow, $csv_delimiter, $csv_enclosure);
                }
                // Apply advanced options.
                if (!empty($groupColIndices[$recordTypeID])) {
                    $sumCols = empty($sumColIndices[$recordTypeID]) ? [] : $sumColIndices[$recordTypeID];
                    $countCols = empty($countColIndices[$recordTypeID]) ? [] : $countColIndices[$recordTypeID];
                    $rows = groupCSVRows($rows, $groupColIndices[$recordTypeID], $sumCols, $countCols);
                }
                if (!empty($percentageColIndices[$recordTypeID])) {
                    $rows = usePercentageForCSVRows($rows, $percentageColIndices[$recordTypeID]);
                }
                if (!empty($sortColIndices[$recordTypeID])) {
                    // Mutate col indices as new columns inserted.
                    for ($i = 0; $i < count($sortColIndices[$recordTypeID]); $i++) {
                        $colIndex = $sortColIndices[$recordTypeID][$i];
                        foreach ($percentageColIndices[$recordTypeID] as $percentageColIndex) {
                            if ($colIndex > $percentageColIndex) {
                                $sortColIndices[$recordTypeID][$i]++;
                            }
                        }
                    }
                    $rows = sortCSVRows($rows, $sortColIndices[$recordTypeID], $sortOrders[$recordTypeID]);
                }

                foreach ($rows as $row) {
                    fputcsv($streams[$recordTypeID], $row, $csv_delimiter, $csv_enclosure);
                }
            }
        }
    }//$has_advanced


    //calculate number of streams with columns more than one
    $count_streams = 0;
    foreach($headers as $rty_ID => $columns){
        if(count($columns)>1){
            $count_streams++;        
        }
    }

    $error_log[] = print_r($rt_counts, true);

    writeResults( $streams, 'Export_'.$system->dbname(), $headers, $error_log );

    //DEBUG error_log(print_r($error_log,true));


}

//
// output rectordtype template as csv (and terms pckup list)
//
function output_HeaderOnly($system, $data, $params)
{
    global $defRecTypes;
    
    $include_term_ids = (@$params['prefs']['include_term_ids']==1);
    $include_term_codes = (@$params['prefs']['include_term_codes']==1);
    $include_resource_titles =  (@$params['prefs']['include_resource_titles']==1);
    $include_term_hierarchy = (@$params['prefs']['include_term_hierarchy']==1);
    
    $fields = @$params['prefs']['fields'];
    $details = array();  //array of detail fields included into output
    $relmarker_details = array(); //relmarker fields included into output
    
    if($defRecTypes==null) $defRecTypes = dbs_GetRectypeStructures($system, null, 2);
    $idx_name = $defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];
    $idx_dtype = $defRecTypes['typedefs']['dtFieldNamesToIndex']['dty_Type'];
    $idx_term_tree = $defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_FilteredJsonTermIDTree'];
    $idx_term_nosel = $defRecTypes['typedefs']['dtFieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];
    

    //create header
    $any_rectype = null;
    $headers = array();
    $terms_pickup = array();
    if($fields){
        foreach($fields as $rt=>$flds){
            
            //always include ID field into output
            if($flds[0]!='rec_ID') array_unshift($flds, 'rec_ID');
            $fields[$rt] = $flds;
            
            $details[$rt] = array();
            $headers[$rt] = array();
            $relmarker_details[$rt] = array();
            
            foreach($flds as $dt_id){
                
                $constr_rt_id = 0;
                if(strpos($dt_id,':')>0){ //for constrained resource fields
                    //example author:person or organization
                    list($dt_id, $constr_rt_id) = explode(':',$dt_id);
                }
                
                $field_name_title = '';
                
                if(is_numeric($dt_id) && $dt_id>0){
                    
                    if($dt_id==DT_PARENT_ENTITY){
                        $field_name = 'Parent entity';
                        $field_type = 'resource';
                    }else{
                        //get field name from structure
                        $field_name = $defRecTypes['typedefs'][$rt]['dtFields'][$dt_id][$idx_name];
                        $field_type = $defRecTypes['typedefs'][$rt]['dtFields'][$dt_id][$idx_dtype];
                    }
                    if($constr_rt_id>0){
                        $rectypename_is_in_fieldname = (strpos(strtolower($field_name), 
                                            strtolower($defRecTypes['names'][$constr_rt_id]))!==false);
                        $field_name_title = $field_name.' '
                                                //.($rectypename_is_in_fieldname?'':($defRecTypes['names'][$constr_rt_id].' '))
                                                .'RecordTitle';
                        $field_name = $field_name.($rectypename_is_in_fieldname
                                            ?'':' ('.$defRecTypes['names'][$constr_rt_id].')').' H-ID';
                    }else{
                        $field_name_title = $field_name.' RecordTitle';
                    }
                    if($field_type=='relmarker'){
                        $relmarker_details[$rt][$dt_id] = $constr_rt_id; 
                    }else{
                        array_push($details[$rt], $dt_id);    
                    }
                    
                }else{
                    //record header
                    $field_type = null;
                    
                    if($dt_id=='rec_ID'){
                        if($rt>0){
                            $field_name = $defRecTypes['names'][$rt].' H-ID';
                        }else{
                            $field_name = 'H-ID';
                            $any_rectype = $rt;
                        }
                    }else{
                        $field_name = $dt_id; //record header field
                    }
                }
    
                if($field_type=='enum' || $field_type=='relationtype'){

                    array_push($headers[$rt], $field_name);  //labels are always included           
                    
                    if($include_term_ids){
                        array_push($headers[$rt], $field_name.' ID');            
                    }
                    
                    if($include_term_codes){
                        array_push($headers[$rt], $field_name.' StdCode' );   
                    }    
                    
                    //add terms pickup list
                    if(!@$terms_pickup[$rt]) $terms_pickup[$rt] = array();
                    $terms_pickup[$rt][$dt_id] = array('name'=>$field_name, 'domain'=>$field_type,
                                             'term_ids'=>$defRecTypes['typedefs'][$rt]['dtFields'][$dt_id][$idx_term_tree],
                                             'nonsel'=>$defRecTypes['typedefs'][$rt]['dtFields'][$dt_id][$idx_term_tree]);
                    
                }else{
                    array_push($headers[$rt], $field_name);                
                }
                
                //add title for resource fields
                if($include_resource_titles && ($field_type=='resource' || $field_type=='relmarker')){
                    array_push($headers[$rt], $field_name_title);            
                }
            }
        }
    }
    
    
    if(count($terms_pickup)>0) {
        $defTerms = dbs_GetTerms($system);
        $defTerms = new DbsTerms($system, $defTerms);
    }
    
    
    $csv_delimiter =  $params['prefs']['csv_delimiter']?$params['prefs']['csv_delimiter']:',';
    $csv_enclosure =  $params['prefs']['csv_enclosure']?$params['prefs']['csv_enclosure']:'"';
   
    $streams = array(); //one per record type
    
    $temp_name = null;    
    //------------
    foreach($headers as $rty_ID => $columns){
        
        $placeholders = null;
        $cnt_cols = count($columns);
        if($cnt_cols>1){
            if(!@$fields[$rty_ID]) continue; //none of fields for this record type marked to output
            
            //prepare terms
            if(is_array(@$terms_pickup[$rty_ID])){  //there are enum fields for this rt
                
                $max_count = 0;
                $placeholders = array(); //array_fill(0, $cnt_cols, '');
                
                foreach($terms_pickup[$rty_ID] as $dtid => $field){
                    $headers[$rty_ID][] = $field['name'].': Lookup list';
                    $placeholders[] = $field['name'].'. Use to create value control lists';
                    //get list of terms
                    $vocabId = $field['term_ids'];
                    $terms = $defTerms->treeData($vocabId, 3);
                    array_unshift($terms, $vocabId); 
                    $max_count = max($max_count, count($terms));    
                    $terms_pickup[$rty_ID][$dtid]['terms'] = $terms;
                }
            }
            
            $fd = fopen('php://temp/maxmemory:1048576', 'w');  //less than 1MB in memory otherwise as temp file 
            $streams[$rty_ID] = $fd;
            
            //write header
            fputcsv($fd, $headers[$rty_ID], $csv_delimiter, $csv_enclosure);
            fwrite($fd, "\n\n");
              
            //write terms
            if($placeholders!=null){
                
                fputcsv($fd, $placeholders, $csv_delimiter, $csv_enclosure);
            
                $k = 0;
                while ($k<$max_count){

                    $placeholders = array(); //no need to create empty columns: array_fill(0, $cnt_cols, '');
                    
                    foreach($terms_pickup[$rty_ID] as $dtid => $field){
                        
                        $terms = $terms_pickup[$rty_ID][$dtid]['terms'];

                        if($k<count($terms)){
                            $placeholders[] =  $defTerms->getTermLabel($terms[$k], true);
                        }else{
                            $placeholders[] = '';    
                        }
                    }//for fields
                    
                    fputcsv($fd, $placeholders, $csv_delimiter, $csv_enclosure);
            
                    $k++;
                }//while
            
            }

            if($temp_name==null)
                $temp_name = 'Heurist_'.$system->dbname();//.'_t'.$rty_ID.'_'.$defRecTypes['names'][$rty_ID];
        }
    }
    writeResults( $streams, $temp_name, $headers, null );
}

   
//
// save CSV streams into file and zip 
//        
function writeResults( $streams, $temp_name, $headers, $error_log ) {
  
    global $defRecTypes;
    
    if(count($streams)<2){
        
        $out = false;
        $rty_ID = 0;
        
        if(count($streams)==0){
            if($error_log) array_push($error_log, "Streams are not defined");
        }else{
            $rty_ID = array_keys($streams);
            $rty_ID = $rty_ID[0];
        
            $filename = $temp_name;
            if($rty_ID>0){
                $filename = $filename.'_t'.$rty_ID.'_'.$defRecTypes['names'][$rty_ID];
            }
            $filename = $filename.'.csv'; //'_'.date("YmdHis").
        
            $fd = $streams[$rty_ID];

            if($fd==null){
                if($error_log) array_push($error_log, "Stream for record type $rty_ID is not defined");
            }else{
                rewind($fd);
                $out = stream_get_contents($fd);
                fclose($fd);
            }
        }

        if( !isset($out) || $out===false || strlen($out)==0){
            $out = "Stream for record type $rty_ID is empty";
            if($error_log) {
                array_push($error_log, $out);   
                $out = implode(PHP_EOL, $error_log);
            }
        }
        
        //header('Content-Description: File Transfer');
        //header('Content-Type: application/octet-stream');
        //header('Content-Transfer-Encoding: binary');

        
        $content_len = strlen($out);
        if(!($content_len>0)) $content_len = 0;
        $content_len = $content_len+3;
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename='.$filename);
        header('Content-Length: ' . $content_len);
        echo "\xEF\xBB\xBF"; // Byte Order Mark        
        exit($out);
        
    }else{
    
        $zipname = $temp_name.'_'.date("YmdHis").'.zip';
        $destination = tempnam(HEURIST_SCRATCHSPACE_DIR, "zip");
        
        $zip = new ZipArchive();
        if (!$zip->open($destination, ZIPARCHIVE::OVERWRITE)) {
            array_push($error_log, "Cannot create zip $destination");    
        }else{
            $is_first = true;
        
            foreach($streams as $rty_ID => $fd){
                
                if($fd==null){
                    array_push($error_log, "Stream for record type $rty_ID is not defined");
                }else{
                    // return to the start of the stream
                    rewind($fd);
                    
                    if($is_first || count($headers[$rty_ID])>1){
                        $is_first = false;
                    
                        $content = stream_get_contents($fd);

                        if($content===false || strlen($content)==0){
                            array_push($error_log, "Stream for record type $rty_ID is empty");
                        }else{
                            // add the in-memory file to the archive, giving a name
                            $zip->addFromString('rectype-'.$rty_ID.'.csv',  $content);
                        }
                        
                    }
                    //close the file
                    fclose($fd);
                }
            }    
            
            if(count($error_log)>0){
                $zip->addFromString('log.txt', implode(PHP_EOL, $error_log) );
            }
            
            // close the archive
            $zip->close();
        }
        
        if(@file_exists($destination)>0){
        
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename='.$zipname);
            header('Content-Length: ' . filesize($destination));
            readfile($destination);

            // remove the zip archive
            unlink($destination);    
        
        }else{
            array_push($error_log, "Zip archive ".$destination." doesn't exist");
            
            $out = implode(PHP_EOL, $error_log);
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename=log.txt');
            header('Content-Length: ' . strlen($out));
            exit($out);
            
        }
        
    }    
    
}       

/**
 * Group by columns for exported CSV rows.
 *
 * @param array $rows The CSV row data.
 * @param array $groupColIndices The indices of the group by columns.
 * @param array $sumColIndices The indices of the columns applied with SUM function.
 * @param array $countColIndices The indices of the columns applied with COUNT function.
 *
 * @return array The grouped CSV rows.
 */
function groupCSVRows(array $rows, array $groupColIndices = [], array $sumColIndices = [], array $countColIndices = []) {
    if (count($groupColIndices) > 0) {
        $groupedRows = [];
        foreach ($rows as $row) {
            $findRowIndex = -1;
            for ($i = 0; $i < count($groupedRows); $i++) {
                $isMatch = true;
                for ($j = 0; $j < count($groupColIndices); $j++) {
                    if ($groupedRows[$i][$groupColIndices[$j]] !== $row[$groupColIndices[$j]]) {
                        $isMatch = false;
                        break;
                    }
                }
                if ($isMatch) {
                    $findRowIndex = $i;
                    break;
                }
            }
            if ($findRowIndex >= 0) {
                for ($i = 0; $i < count($countColIndices); $i++) {
                    $groupedRows[$findRowIndex][$countColIndices[$i]] += 1;
                }
                for ($i = 0; $i < count($sumColIndices); $i++) {
                    $groupedRows[$findRowIndex][$sumColIndices[$i]] += valueToNumeric($row[$sumColIndices[$i]]);
                }
            } else {
                for ($i = 0; $i < count($countColIndices); $i++) {
                    $row[$countColIndices[$i]] = 1;
                }
                for ($i = 0; $i < count($sumColIndices); $i++) {
                    $row[$sumColIndices[$i]] = valueToNumeric($row[$sumColIndices[$i]]);
                }
                $groupedRows[] = $row;
            }
        }
        return $groupedRows;
    } else {
        return $rows;
    }
}

function usePercentageForCSVHeaders(array $headers, array $usePercentageColIndices = []) {
    if (count($usePercentageColIndices) > 0) {
        $colIncrease = 0;
        for ($i = 0; $i < count($usePercentageColIndices); $i++) {
            $colIndex = $usePercentageColIndices[$i] + $colIncrease;
            if ($colIndex + 1 > count($headers) - 1) {
                $headers[] = $headers[$colIndex] . '(%)';
            } else {
                array_splice($headers, $colIndex + 1, 0, [$headers[$colIndex] . '(%)']);
            }
            $colIncrease++;
        }
    }
    return $headers;
}

/**
 * Calculate the percentage value of the specified columns in the CSV rows.
 *
 * @param array $rows The CSV row data.
 * @param array $usePercentageColIndices The indices of the columns to calculate the percentages.
 *
 * @return array The CSV rows with the percentage values calculated.
 */
function usePercentageForCSVRows(array $rows, array $usePercentageColIndices = []) {
    if (count($usePercentageColIndices) > 0) {
        $colTotal = [];
        for ($i = 0; $i < count($rows); $i++) {
            for ($j = 0; $j < count($usePercentageColIndices); $j++) {
                $colIndex = $usePercentageColIndices[$j];
                if (!isset($colTotal[$colIndex])) {
                    $colTotal[$colIndex] = valueToNumeric($rows[$i][$colIndex]);
                } else {
                    $colTotal[$colIndex] += valueToNumeric($rows[$i][$colIndex]);
                }
            }
        }
        for ($i = 0; $i < count($rows); $i++) {
            $colIncrease = 0;
            for ($j = 0; $j < count($usePercentageColIndices); $j++) {
                $colIndex = $usePercentageColIndices[$j];
                $percentage = round(valueToNumeric($rows[$i][$colIndex + $colIncrease]) / $colTotal[$colIndex], 4) * 100;
                if ($colIndex + 1 > count($rows[$i]) - 1) {
                    $rows[$i][] = $percentage;
                } else {
                    array_splice($rows[$i], $colIndex + $colIncrease + 1, 0, [$percentage]);
                }
                $colIncrease++;
            }
        }
    }
    return $rows;
}

/**
 * Sort the CSV rows by specified columns and orders.
 *
 * @param array $rows The CSV row data.
 * @param array $sortByColIndices The indices of the columns to sort.
 * @param array $sortOrders The sort orders of the specified columns. Each element can either
 *   be 'asc' or 'des'.
 *
 * @return array The sorted CSV rows.
 */
function sortCSVRows(array $rows, array $sortByColIndices = [], array $sortOrders = []) {
    if (count($sortByColIndices) > 0) {
        usort($rows, function($a, $b) use ($sortByColIndices, $sortOrders) {
            $result = 0;
            for ($i = 0; $i < count($sortByColIndices); $i++) {
                $sortByColIndex = $sortByColIndices[$i];
                $isAscending = true;
                if (isset($sortOrders[$i])) {
                    $isAscending = ($sortOrders[$i] === 'asc');
                }
                if (is_numeric($a[$sortByColIndex]) && is_numeric($b[$sortByColIndex])) {
                    if ($a[$sortByColIndex] == $b[$sortByColIndex]) {
                        $result = 0;
                    } else {
                        $result = $a[$sortByColIndex] < $b[$sortByColIndex] ? -1 : 1;
                        if (!$isAscending) {
                            $result = -$result;
                        }
                        break;
                    }
                } else {
                    $result = strcmp($a[$sortByColIndex], $b[$sortByColIndex]);
                    if ($result !== 0) {
                        if (!$isAscending) {
                            $result = -$result;
                        }
                        break;
                    }
                }
            }
            return $result;
        });
    }
    return $rows;
}

/**
 * Create the joint CSV data from multiple record types.
 *
 * @param array $csvData The original CSV data divided by record types.
 * @param array $columnInfo The column information of the original CSV data by
 *   record types.
 * @param int $mainRecordTypeID The ID of the root record type to export.
 * @param string $filedValueDelimiter The delimiter used for multi-value field.
 * @param bool $includeHeader Whether the header included in the CSV data.
 *
 * @return array The joint CSV data.
 */
function createJointCSVTables($csvData, &$columnInfo, $mainRecordTypeID, $filedValueDelimiter, $includeHeader = true) {
    $csvRows = $csvData[$mainRecordTypeID];

    // Create join table lookups.
    $csvRowLookups = [];
    foreach ($csvData as $recordTypeID => $rows) {
        if ($recordTypeID != $mainRecordTypeID) {
            if (!isset($csvRowLookups[$recordTypeID])) {
                $csvRowLookups[$recordTypeID] = [];
            }
            foreach ($rows as $index => $row) {
                if ($includeHeader && $index === 0) {
                    $csvRowLookups[$recordTypeID]['header'] = $row;
                } else {
                    $csvRowLookups[$recordTypeID][$row[0]] = $row;
                }
            }
        }
    }

    $jointRows = [];

    foreach ($csvRows as $row) {
        $jointRow = [];
        $recordTypeIDTrack = [];
        createJointCSVRow($jointRow, $row, $mainRecordTypeID, $columnInfo, $csvRowLookups, $recordTypeIDTrack, $filedValueDelimiter);
        $jointRows[] = $jointRow;
    }

    return $jointRows;
}

/**
 * Create a row for the joint CSV.
 *
 * @param array $jointRow Passed in reference. It will contain the data for the row
 *   after the function is finished.
 * @param array $row The row data from the original CSV data.
 * @param int $recordTypeID The record type ID of the original row data.
 * @param array $columnInfo The full array containing the original column information.
 *   It will contain the new column indices in the joint CSV after the function is finished.
 * @param array $csvRowLookups The lookup array for each record type keyed by record ID.
 * @param array $recordTypeIDTrack The array which keeps a track of the record type IDs have
 *   been joint.
 * @param string $filedValueDelimiter The delimiter used for multi-value field.
 * @param int $level The depth of the current record type.
 */
function createJointCSVRow(&$jointRow, $row, $recordTypeID, &$columnInfo, $csvRowLookups, &$recordTypeIDTrack, $filedValueDelimiter, $level = 1) {
    $recordTypeIDTrack[] = (int) $recordTypeID;
    foreach ($columnInfo[$recordTypeID] as &$colInfo) {
        if (strpos($colInfo['field_id'], ':') === false) {
            if ($level === 1 || $colInfo['index'] !== 0) {
                $jointRow[] = $row[$colInfo['index']];
                $colInfo['joint_column_index'] = count($jointRow) - 1;
            }
        } else {
            $fieldIDParts = explode(':', $colInfo['field_id']);
            $targetRecordTypeID = $fieldIDParts[count($fieldIDParts) - 1];
            if (in_array((int) $targetRecordTypeID, $recordTypeIDTrack) && $colInfo['type'] !== 'resource_title') {
                $jointRow[] = $row[$colInfo['index']];
                $colInfo['joint_column_index'] = count($jointRow) - 1;
            } else {
                if ($colInfo['type'] === 'value') {
                    $jointRow[] = $row[$colInfo['index']];
                    $colInfo['joint_column_index'] = count($jointRow) - 1;
                    $targetRecordID = $row[$colInfo['index']];
                    if (!empty($targetRecordID) && !is_numeric($targetRecordID) && strpos($targetRecordID, $filedValueDelimiter) === false) {
                        $targetRecordID = 'header';
                    }
                    if (empty($targetRecordID)) {
                        $jointRow = array_merge($jointRow, generateEmptyCellsForTargetRecordType($targetRecordTypeID, $csvRowLookups));
                    } else {
                        $targetRow = findInCSVRowLookup($targetRecordID, $targetRecordTypeID, $csvRowLookups, $filedValueDelimiter);
                        if ($targetRow) {
                            createJointCSVRow($jointRow, $targetRow, $targetRecordTypeID, $columnInfo, $csvRowLookups, $recordTypeIDTrack, $filedValueDelimiter, $level + 1);
                        } else {
                            $jointRow = array_merge($jointRow, generateEmptyCellsForTargetRecordType($targetRecordTypeID, $csvRowLookups));
                        }
                    }
                }
            }
        }
    }
}

/**
 * Lookup the row data based on the record ID.
 *
 * @param string $recordIDLiteral The record ID. It could be multiple IDs separated
 *   by the delimiter.
 * @param string $targetRecordTypeID The ID of the target record type.
 * @param string $csvRowLookups The lookup data.
 * @param string $filedValueDelimiter The delimiter used for multiple IDs.
 *
 * @return array|bool
 */
function findInCSVRowLookup($recordIDLiteral, $targetRecordTypeID, $csvRowLookups, $filedValueDelimiter) {
    $recordIDs = explode($filedValueDelimiter, $recordIDLiteral);
    $lookupRow = [];
    foreach ($recordIDs as $recordID) {
        if (isset($csvRowLookups[$targetRecordTypeID][$recordID])) {
            foreach ($csvRowLookups[$targetRecordTypeID][$recordID] as $index => $item) {
                if (!isset($lookupRow[$index])) {
                    $lookupRow[$index] = [];
                }
                $lookupRow[$index][] = $item;
            }
        }
    }
    if (empty($lookupRow)) {
        return false;
    } else {
        $concatRow = [];
        foreach ($lookupRow as $values) {
            $concatRow[] = implode($filedValueDelimiter, $values);
        }
        return $concatRow;
    }
}

/**
 * Generate an array of empty strings for a target record type.
 *
 * This function is used to generate empty cells in the joint CSV when the
 * reference value is empty, or the reference value can be found from the
 * lookup data.
 *
 * @param string $targetRecordTypeID The ID of the target record type.
 * @param array $csvRowLookups The lookup data.
 *
 * @return array
 */
function generateEmptyCellsForTargetRecordType($targetRecordTypeID, $csvRowLookups) {
    $cells = [];
    if (!empty($csvRowLookups[$targetRecordTypeID])) {
        $length = count(reset($csvRowLookups[$targetRecordTypeID])) - 1;
        for ($i = 0; $i < $length; $i++) {
            $cells[] = "";
        }
    }
    return $cells;
}

/**
 * Change the column index array after the CSV data is joint.
 *
 * This function will change the column indices of advanced option to the
 * new indices in the joint CSV table.
 *
 * @param array $columnIndices The original column indices of advanced option.
 * @param array $mainRecordTypeID The ID of the root record type to export.
 * @param array $columnInfo The column information. After the csv data is joined,
 *   this array contains the new column indices in the joint CSV table.
 *
 * @return array The new column indices.
 */
function changeAdvancedOptionColumnIndex($columnIndices, $mainRecordTypeID, $columnInfo) {
    if (empty($columnIndices)) {
        return $columnIndices;
    }
    $newColumnIndices = [
        $mainRecordTypeID => [],
    ];
    foreach ($columnIndices as $recordTypeID => $colIndices) {
        foreach ($colIndices as $columnIndex) {
            if (isset($columnInfo[$recordTypeID][$columnIndex]['joint_column_index'])) {
                $newColumnIndices[$mainRecordTypeID][] = $columnInfo[$recordTypeID][$columnIndex]['joint_column_index'];
            }
        }
    }
    return $newColumnIndices;
}

/**
 * Change the sorting orders to fit the joint CSV table.
 *
 * @param array $sortOrders The original sorting orders.
 * @param array $mainRecordTypeID The ID of the root record type to export.
 *
 * @return array The new sorting orders.
 */
function changeSortOrders($sortOrders, $mainRecordTypeID) {
    if (empty($sortOrders)) {
        return $sortOrders;
    }
    $newSortOrders = [
        $mainRecordTypeID => [],
    ];
    foreach ($sortOrders as $recordTypeID => $orders) {
        foreach ($orders as $order) {
            $newSortOrders[$mainRecordTypeID][] = $order;
        }
    }
    return $newSortOrders;
}

/**
 * Convert a value to numeric.
 *
 * @param $value
 *
 * @return int
 */
function valueToNumeric($value) {
    if (!is_numeric($value)) {
        $value = intval($value);
    }
    return $value;
}

?>