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

    require_once (dirname(__FILE__).'/../dbaccess/exportRecords.php');
    
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
                //restore ids from session
                $search_params['q'] = $system->user_GetPreference($dt_key);
                
                if($search_params['q']==null){
                    //query was removed 
                    header( 'Content-Type: application/json');    
                    echo json_encode(array('error'=>'Datatable session expired. Please refresh search'));
                    exit();
                }
                
                if(@$params['start']>0){
                    $search_params['offset'] = $params['start'];
                }
                if($params['length']>0){
                    $search_params['limit'] = $params['length'];
                    $search_params['needall'] = 0;
                }
                
            }else if(@$params['q']!=null){
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
        
        if(@$params['prefs']['csv_headeronly'])
        {
            output_HeaderOnly($system, $response, $params);
        }else{
            output_CSV($system, $response, $params);    
        }
        
    }else{
        
        if(!ExportRecords::output( $response, $params )) {
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
    
    //create header
    $any_rectype = null;
    $headers = array();
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
    
    $csv_delimiter =  $params['prefs']['csv_delimiter']?$params['prefs']['csv_delimiter']:',';
    $csv_enclosure =  $params['prefs']['csv_enclosure']?$params['prefs']['csv_enclosure']:'"';
    $csv_mvsep =  $params['prefs']['csv_mvsep']?$params['prefs']['csv_mvsep']:'|';
    $csv_linebreak =  $params['prefs']['csv_linebreak']?$params['prefs']['csv_linebreak']:'nix'; //not used
    $csv_header =  $params['prefs']['csv_header']?$params['prefs']['csv_header']:true;
    
    //------------
    $records = $data['records'];    
    
    $streams = array(); //one per record type
    $rt_counts = array();
    
    $error_log = array();
    $error_log[] = 'Total rec count '.count($records);
    
    $idx = 0;
    while ($idx<count($records)){ //replace to WHILE
    
        $recID = $records[$idx];
        $record = recordSearchByID($system, $recID, false);
        $rty_ID = ($any_rectype!=null)?$any_rectype :$record['rec_RecTypeID'];

        $idx++;
        
        if(!@$fields[$rty_ID]) continue; //none of fields for this record type marked to output
        
        if(!@$streams[$rty_ID]){
            // create a temporary file
            $fd = fopen('php://temp/maxmemory:1048576', 'w');  //less than 1MB in memory otherwise as temp file 
            if (false === $fd) {
                die('Failed to create temporary file');
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
                    
                    //if(window.hWin.HEURIST4.ui.isTermInList(this.detailType, allTerms, headerTerms, direct[k]['trmID']))
                
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
                    
                    if($values){
                        
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
                    if($value == null){
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
                $value = ($value==null)?'':implode($csv_mvsep, $value);
                
            }else{
                $value = @$record[$dt_id]; //from record header
            }
            if($value==null) $value = '';                       
            
            
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
        if(count($record_row)>0)
        fputcsv($fd, $record_row, $csv_delimiter, $csv_enclosure);
        
    }//for records
    
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
                    $terms = $defTerms->getAllowedTermsForField( $field['term_ids'], $field['nonsel'], $field['domain'] );
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

        if($out===false || strlen($out)==0){
            $out = "Stream for record type $rty_ID is empty";
            if($error_log) {
                array_push($error_log, $out);   
                $out = implode(PHP_EOL, $error_log);
            }
        }
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename='.$filename);
        header('Content-Length: ' . strlen($out));
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
?>