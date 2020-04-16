<?php

    /**
    * TO BE REPLACED WITH ExportRecords 
    *
    * Application interface. See hRecordMgr in hapi.js
    * Record search and output in required format
    * used in recordExportCSV.js
    * 
    * 
    * parameters
    * db - heurist database
    * format = geojson|json|csv|kml|xml|hml|gephi
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
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2019 University of Sydney
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

    require_once (dirname(__FILE__).'/../../vendor/autoload.php'); //for geoPHP
    require_once (dirname(__FILE__).'/../../viewers/map/Simplify.php');
    
    require_once(dirname(__FILE__).'/../import/GpointConverter.php');
    require_once (dirname(__FILE__).'/../import/exportRecords.php');
    
    $response = array();

    $system = new System();
    
    //global variable to keep defs
    $defRecTypes = null;
    $defDetailtypes = null;
    $defTerms = null;
    $find_places_for_geo = ($system->user_GetPreference('deriveMapLocation', 1)==1);

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
        /*forcefully sort by record type    
        if(is_array(@$params['q'])){
            $query_json = $params['q'];
        }else{
            $query_json = json_decode(@$params['q'], true);
        }

        if(is_array($query_json) && count($query_json)>0){
            $params['q'] = $query_json;
            if($is_csv) $params['q']['sortby']='rt';
        }else if($is_csv) {
             $params['q'] = @$params['q'].' sortby:rt';
        }
        */
        
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
        
        //header('Content-type: application/json;charset=UTF-8');
        //echo json_encode($out);
        
        //@todo replace with 
        if(!ExportRecords::output( $response, $params )) {
        //if(!output_Records($system, $response, $params)) {
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
// output records as json or xml 
//
// $data - recordSearch response
//
// $parmas 
//    format - json|geojson|xml|gephi
//    defs  0|1  include database definitions
//    file  0|1
//    zip   0|1
//    depth 0|1|2|...all  
//
// @todo if output if file and zip - output datatabase,defintions and records as separate files
//      split records by 1000 entries chunks
//
function output_Records($system, $data, $params){
    
    global $defRecTypes, $defTerms;

    if (!($data && @$data['status']==HEURIST_OK)){
        return false;
    }

    $data = $data['data'];
    
    if(@$data['memory_warning']){ //memory overflow in recordSearch
        $records = array(); //@todo
    }else if(!(@$data['reccount']>0)){   //empty response
        $records = array();
    }else{
        $records = $data['records'];
    }
    
    $records_out = array(); //ids already out
    $rt_counts = array(); //counts of records by record type
    
    $error_log = array();
    $error_log[] = 'Total rec count '.count($records);
    
    $tmp_destination = tempnam(HEURIST_SCRATCHSPACE_DIR, "exp"); //system temp folder
    //$fd = fopen('php://temp/maxmemory:1048576', 'w');  //less than 1MB in memory otherwise as temp file 
    $fd = fopen($tmp_destination, 'w');  //less than 1MB in memory otherwise as temp file 
    if (false === $fd) {
        $system->addError(HEURIST_SYSTEM_CONFIG, 'Failed to create temporary file in scratch folder');
        return false;
    }   
    //to store gephi links
    $gephi_links_dest = null;
    $fd_links = null;
    $links_cnt = 0;
    
    //convert TLCMAP dataset to MAP_LAYER 
    $is_tlc_export = (@$params['tlcmap']!=null && defined('RT_TLCMAP_DATASET'));
    $maplayer_fields = null;
    $maplayer_records = array();
    $maplayer_extents = array();
    $ds_file_fields = null; //keep ids of all file fields
    if($is_tlc_export){
        //get list of detail types for MAP_LAYER
        $maplayer_fields = mysql__select_list2($system->get_mysqli(),
            'select rst_DetailTypeID from defRecStructure where rst_RecTypeID='.RT_MAP_LAYER);        
        //get list of field types with type "file"
        $ds_file_fields = mysql__select_list2($system->get_mysqli(),
            'select dty_ID from defDetailTypes where dty_Type="file"');        
        //get default values for mapspace
        $mapdoc_defaults = mysql__select_assoc2($system->get_mysqli(),
            'select rst_DetailTypeID, rst_DefaultValue from defRecStructure where rst_RecTypeID='.RT_MAP_DOCUMENT
            .' AND rst_DetailTypeID in ('.DT_MAP_BOOKMARK.','.DT_ZOOM_KM_POINT.')' );        
    }
    
    //OPEN BRACKETS
    if($params['format']=='geojson'){

        if(@$params['leaflet']){
            fwrite($fd, '{"geojson":');         
        }
        
        fwrite($fd, '[');         
        
    }else if(@$params['restapi']==1){
        
        if(count($records)==1 && @$params['recID']>0){
            //fwrite($fd, '');             
        }else{
            //@todo xml for api
            fwrite($fd, '{"records":[');             
        }
        
    }else if($params['format']=='json'){
        fwrite($fd, '{"heurist":{"records":[');         
    }else if($params['format']=='gephi'){

        $gephi_links_dest = tempnam(HEURIST_SCRATCHSPACE_DIR, "links");    
        //$fd = fopen('php://temp/maxmemory:1048576', 'w');  //less than 1MB in memory otherwise as temp file 
        $fd_links = fopen($gephi_links_dest, 'w');  //less than 1MB in memory otherwise as temp file 
        if (false === $fd_links) {
            $system->addError(HEURIST_SYSTEM_CONFIG, 'Failed to create temporary file in scratch folder');
            return false;
        }   

        $t2 = new DateTime();
        $dt = $t2->format('Y-m-d');
/*
        $gephi_header = 
'<gexf xmlns="http://www.gexf.net/1.2draft" xmlns:xsi="https://www.w3.org/2001/XMLSchema-instance"'
    .' xsi:schemaLocation="http://www.gexf.net/1.2draft http://www.gexf.net/1.2draft/gexf.xsd" version="1.2">'
    ."<meta lastmodifieddate=\"{$dt}\">"
        .'<creator>HeuristNetwork.org</creator>'
        .'<description>Visualisation export</description>'
    .'</meta>'
    .'<graph mode="static" defaultedgetype="directed">'
        .'<attributes class="node">'
            .'<attribute id="0" title="name" type="string"/>'
            .'<attribute id="1" title="image" type="string"/>'
            .'<attribute id="2" title="rectype" type="string"/>'
            .'<attribute id="3" title="count" type="float"/>'
        .'</attributes>'
        .'<attributes class="edge">'
            .'<attribute id="0" title="relation-id" type="float"/>'
            .'<attribute id="1" title="relation-name" type="string"/>'
            .'<attribute id="2" title="relation-image" type="string"/>'
            .'<attribute id="3" title="relation-count" type="float"/>'
        .'</attributes>'
        .'<nodes>';
*/
//although anyURI is defined it is not recognized by gephi v0.92

    $heurist_url = HEURIST_BASE_URL.'?db='.HEURIST_DBNAME;

        $gephi_header = <<<XML
<gexf xmlns="http://www.gexf.net/1.2draft" xmlns:xsi="https://www.w3.org/2001/XMLSchema-instance"
     xsi:schemaLocation="http://www.gexf.net/1.2draft http://www.gexf.net/1.2draft/gexf.xsd" version="1.2">
    <meta lastmodifieddate="{$dt}">
        <creator>HeuristNetwork.org</creator>
        <description>Visualisation export $heurist_url </description>
    </meta>
    <graph mode="static" defaultedgetype="directed">
        <attributes class="node">
            <attribute id="0" title="name" type="string"/>
            <attribute id="1" title="image" type="string"/>
            <attribute id="2" title="rectype" type="string"/>
            <attribute id="3" title="count" type="float"/>
            <attribute id="4" title="url" type="string"/>
        </attributes>
        <attributes class="edge">
            <attribute id="0" title="relation-id" type="float"/>
            <attribute id="1" title="relation-name" type="string"/>
            <attribute id="2" title="relation-image" type="string"/>
            <attribute id="3" title="relation-count" type="float"/>
        </attributes>
        <nodes>
XML;

        $gephi_header = '<?xml version="1.0" encoding="UTF-8"?>'.$gephi_header;

        fwrite($fd, $gephi_header);     
    }else{
        fwrite($fd, '<?xml version="1.0" encoding="UTF-8"?><heurist><records>');     
    }

    //CONTENT
    $timeline_data = [];
    $layers_record_ids = [];
    
    $comma = '';
    
    if(@$params['leaflet']){ //get only limited set of fields 
        $retrieve_fields = "rec_ID,rec_RecTypeID,rec_Title";
    }else{
        $retrieve_fields = null;
    }

    //
    //
    //    
    $max_depth = 0;
    if(@$params['depth']){
        $max_depth = (@$params['depth']=='all') ?9999:intval(@$params['depth']);
    }
    if($max_depth>0){
        //search direct and reverse links 
        if($params['format']=='gephi' && @$params['limit']>0){
           $limit = $params['limit'];  
        }else{
           $limit = 0; 
        }
        
        recordSearchRelatedIds($system, $records, 0, 0, $max_depth, $limit);
    }
    
    //$is_constructed_record = false;
    
    $idx = 0;
    while ($idx<count($records)){   //loop by record ids
    
        $recID = $records[$idx];
        if(is_array($recID)){//$is_constructed_record
            $record = $records[$idx];
            $recID = $record['rec_ID'];
        }else{
            $record = recordSearchByID($system, $recID, ($params['format']!='gephi'), $retrieve_fields );
        }
        $idx++;
        
        $rty_ID = $record['rec_RecTypeID'];
        
        //change record type to layer, remove redundant fields
        if($is_tlc_export){
            if($rty_ID==RT_TLCMAP_DATASET){
                $record['rec_RecTypeID'] = RT_MAP_LAYER;
                $rty_ID = RT_MAP_LAYER;
                $new_details = array();
                //remove redundant fields from RT_TLCMAP_DATASET
                foreach($record["details"] as $dty_ID => $values){
                    if(in_array($dty_ID, $maplayer_fields)){
                        $new_details[$dty_ID] = $values;                    
                        
                    }
                    if($dty_ID==DT_GEO_OBJECT){
                        //keep geo to calculate extent for mapdocument
                        array_push($maplayer_extents, $values);
                    }
                }
                $record["details"] = $new_details;
                
                //{"id":"287","type":"29","title":"Region cities","hhash":null}            
                $midx = (count($maplayer_records)+5);
                $maplayer_records[$midx] = array('id'=>$record['rec_ID']);
                
            }else{
                //@todo - add db parameter for query datasource
                //@todo - convert uploaded images to external url
                /*
                foreach($record["details"] as $dty_ID => $values){
                    if(in_array($dty_ID, $ds_file_fields)){
                        
                        foreach($record["details"][$dty_ID] as $dtl_ID=>$value){
                            //change to dowload link from source database
                            $record["details"][$dty_ID][$dtl_ID]['file']['fullPath'] = null;
                            $record["details"][$dty_ID][$dtl_ID]['file']['ulf_ExternalFileReference'] = 
                                HEURIST_BASE_URL.'?db='.HEURIST_DBNAME
                                .'&file='.$value['fileid'];
                        }         
                    }
                }
                */
/* sample of structure                        
http://127.0.0.1/h5-ao/?db=osmak_38&file=5a9fa3e19f000366d439dbe98779f4dd6acb858d
{"1900":{"file":{"ulf_ID":"38","fullPath":null,"ulf_ExternalFileReference":"https:\/\/oldsaratov.ru\/sites\/default\/files\/styles\/post_image\/public\/photos\/2012-05\/radishchevskiymuzeyleontevy.jpg","fxm_MimeType":"image\/jpeg","ulf_Parameters":null,"ulf_OrigFileName":"_remote","ulf_FileSizeKB":"0","ulf_ObfuscatedFileID":"486e821f21de3d4876b255f839834f458eee05af","ulf_Description":"","ulf_Added":"2020-01-13 14:10:03","ulf_MimeExt":"jpg"},"fileid":"486e821f21de3d4876b255f839834f458eee05af"}}                        
*/
            }
        }
        
        if(!@$rt_counts[$rty_ID]){
            $rt_counts[$rty_ID] = 1;
        }else{
            $rt_counts[$rty_ID]++;
        }
        
        if($params['format']=='geojson'){
            
            $feature = getGeoJsonFeature($record, (@$params['extended']==2), @$params['simplify'], @$params['leaflet']);
            if(@$params['leaflet']){ //include only geoenabled features, timeline data goes in separate timeline array
                   if(@$feature['when']){
                        $timeline_data[] = array('rec_ID'=>$recID, 'when'=>$feature['when']['timespans'], 
                                        'rec_RecTypeID'=>$rty_ID, "rec_Title"=>$record['rec_Title']);
                        $feature['when'] = null;
                        unset($feature['when']);
                   }
                   
                   if( (defined('RT_TLCMAP_DATASET') && $rty_ID==RT_TLCMAP_DATASET) || $rty_ID==RT_MAP_LAYER){
                        array_push($layers_record_ids, $recID);    
                   }
                   
                   if(!@$feature['geometry']) continue;
            }

            fwrite($fd, $comma.json_encode($feature));
            $comma = ',';
        
        }else if($params['format']=='json'){ 
            
            if(@$params['extended']>0 && @$params['extended']<3){
                $feature = getJsonFeature($record, $params['extended']);
                fwrite($fd, $comma.json_encode($feature));
            }else{
                fwrite($fd, $comma.json_encode($record)); //as is
            }
            $comma = ',';
            
        }else if($params['format']=='gephi'){ 
            
            $name   = htmlspecialchars($record['rec_Title']);                               
            $image  = htmlspecialchars(HEURIST_ICON_SCRIPT.$rty_ID);
            $recURL = htmlspecialchars(HEURIST_BASE_URL.'recID='.$recID.'&fmt=html&db='.HEURIST_DBNAME);                               
                               
            $gephi_node = <<<XML
<node id="{$recID}" label="{$name}">                               
    <attvalues>
    <attvalue for="0" value="{$name}"/>
    <attvalue for="1" value="{$image}"/>
    <attvalue for="2" value="{$rty_ID}"/>
    <attvalue for="3" value="0"/>
    <attvalue for="4" value="{$recURL}"/>
    </attvalues>
</node>
XML;
            fwrite($fd, $gephi_node);
            
            $links = recordSearchRelated($system, $recID, 0, false);
            if($links['status']==HEURIST_OK){
                if(@$links['data']['direct'])
                    fwrite($fd_links, _composeGephiLinks($records, $links['data']['direct'], $links_cnt, 'direct'));
                if(@$links['data']['reverse'])
                    fwrite($fd_links, _composeGephiLinks($records, $links['data']['reverse'], $links_cnt, 'reverse'));
            }else{
                return false;
            }
            
             
        }else{
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><record/>');
            array_to_xml($record, $xml);
            //array_walk_recursive($record, array ($xml , 'addChild'));
            fwrite($fd, substr($xml->asXML(),38));
        }
        
        
        if($is_tlc_export && $idx==count($records)){
            //calculate extent of mapdocument
            $zoomKm = 0;
            $mbookmark = null;
            $mbox = array();
            foreach($maplayer_extents as $dtl_ID=>$values){
                foreach($values as $value){
                    if(is_array($value) && @$value['geo']){
                        $wkt = $value['geo']['wkt'];
                        $bbox = getExtentFromWkt($wkt);  
                        if($bbox!=null){
                            if( !@$mbox['maxy'] || $mbox['maxy']<$bbox['maxy'] ){
                                $mbox['maxy'] = $bbox['maxy'];
                            }        
                            if( !@$mbox['maxx'] || $mbox['maxx']<$bbox['maxx'] ){
                                $mbox['maxx'] = $bbox['maxx'];
                            }        
                            if( !@$mbox['miny'] || $mbox['miny']>$bbox['miny'] ){
                                $mbox['miny'] = $bbox['miny'];
                            }        
                            if( !@$mbox['minx'] || $mbox['minx']>$bbox['minx'] ){
                                $mbox['minx'] = $bbox['minx'];
                            }        
                        }
                    }
                }
            }
            if(count($mbox)==4){
                
                $gPoint = new GpointConverter();
                $gPoint->setLongLat($mbox['minx'], $mbox['miny']);
                $zoomKm = round($gPoint->distanceFrom($mbox['maxx'], $mbox['miny'])/100000,0);
                
                
                $mbookmark = 'Extent,'.$mbox['miny'].','.$mbox['minx']
                             .','.$mbox['maxy'].','.$mbox['maxx'].',1800,2050';
                
                $mbox = array($mbox['minx'].' '.$mbox['miny'], $mbox['minx'].' '.$mbox['maxy'], 
                                $mbox['maxx'].' '.$mbox['maxy'], $mbox['maxx'].' '.$mbox['miny'], 
                                  $mbox['minx'].' '.$mbox['miny']);
                $mbox = 'POLYGON(('.implode(',',$mbox).'))';
                
            }
            
            //add constructed mapspace record
            $record['rec_ID'] = 999999999;
            $record['rec_RecTypeID'] = RT_MAP_DOCUMENT;
            $record['rec_Title'] = $params['tlcmap'];
            $record['rec_URL'] = ''; 
            $record['rec_ScratchPad'] = '';
            $record["details"] = array(
                DT_NAME=>array('1'=>$params['tlcmap']),
                DT_MAP_BOOKMARK=>array('2'=>($mbookmark!=null?$mbookmark:$mapdoc_defaults[DT_MAP_BOOKMARK])),
                DT_ZOOM_KM_POINT=>array('3'=>($zoomKm>0?$zoomKm:$mapdoc_defaults[DT_ZOOM_KM_POINT])),
                DT_GEO_OBJECT=>array('4'=>($mbookmark!=null?array('geo'=>array("type"=>"pl", "wkt"=>$mbox)):null)),
                DT_MAP_LAYER=>$maplayer_records
            );
            
            $records[$idx] = $record;
            $is_tlc_export = false; //avoid infinite loop
        }
    }//while records
    
    
    //CLOSE brackets
    
    if($params['format']=='geojson'){
        
        fwrite($fd, ']');
        
        if(@$params['leaflet']){ //return 2 array - pure geojson and timeline items
           fwrite($fd, ',"timeline":'.json_encode($timeline_data));
           fwrite($fd, ',"layers_ids":'.json_encode($layers_record_ids).'}');
        }
        
    }else if(@$params['restapi']==1){
        if(count($records)==1 && @$params['recID']>0){
            //fwrite($fd, '');             
        }else{ 
            //@todo xml for api
            fwrite($fd, ']}');             
        }
    }else if($params['format']=='gephi'){
    
        fwrite($fd, '</nodes>');
        
        fwrite($fd, '<edges>'.file_get_contents($gephi_links_dest).'</edges>');
        
        fwrite($fd, '</graph></gexf>');
        
        fclose($fd_links);
    
    }else{
            //json or xml 
    
            if($params['format']=='json'){
                fwrite($fd, ']');     
            }else{ //xml
                fwrite($fd, '</records>');     
            }
        
            $rectypes = dbs_GetRectypeStructures($system, null, 2);
            // include defintions
            if(@$params['defs']==1){
                
                $detailtypes = dbs_GetDetailTypes($system, null, 2);
                $terms = dbs_GetTerms($system);
                
                unset($rectypes['names']);
                unset($rectypes['pluralNames']);
                unset($rectypes['groups']);
                unset($rectypes['dtDisplayOrder']);
                
                unset($detailtypes['names']);
                unset($detailtypes['groups']);
                unset($detailtypes['rectypeUsage']);

                $rectypes = array('rectypes'=>$rectypes);
                $defDetailtypes = array('detailtypes'=>$detailtypes);
                $terms = array('terms'=>$terms);
                
                if($params['format']=='json'){
                    fwrite($fd, ',{"definitions":['); 
                    fwrite($fd, json_encode($rectypes).',');
                    fwrite($fd, json_encode($detailtypes).',');
                    fwrite($fd, json_encode($terms));
                    fwrite($fd, ']}'); 
                }else{
                    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><definitions/>');
                    array_to_xml($rectypes, $xml);
                    array_to_xml($detailtypes, $xml);
                    array_to_xml($terms, $xml);
                    fwrite($fd, substr($xml->asXML(),38));
                }
            }
            
            //add database information to be able to load definitions later
            $dbID = $system->get_system('sys_dbRegisteredID');
            $database_info = array('id'=>$dbID, 
                                                'url'=>HEURIST_BASE_URL, 
                                                'db'=>$system->dbname());
                
            $query = 'select rty_ID,rty_Name,'
            ."if(rty_OriginatingDBID, concat(cast(rty_OriginatingDBID as char(5)),'-',cast(rty_IDInOriginatingDB as char(5))), concat('$dbID-',cast(rty_ID as char(5)))) as rty_ConceptID"
            .' from defRecTypes where rty_ID in ('.implode(',',array_keys($rt_counts)).')';    
            $rectypes = mysql__select_all($system->get_mysqli(),$query,1);    
                
            foreach($rt_counts as $rtid => $cnt){
                //include record types that are in output - name, ccode and count
                $rt_counts[$rtid] = array('name'=>$rectypes[$rtid][0],'code'=>$rectypes[$rtid][1],'count'=>$cnt);
            }
            $database_info['rectypes'] = $rt_counts;
            
            if($params['format']=='json'){
                fwrite($fd, ',"database":'.json_encode($database_info));
                fwrite($fd, '}}');     
            }else{
                $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><database/>');
                array_to_xml($database_info, $xml);
                fwrite($fd, substr($xml->asXML(),38));
                fwrite($fd, '</heurist>');     
            }
        
    }
 
    if(@$params['zip']==1 || @$params['zip']===true){
        
        $output = gzencode(file_get_contents($tmp_destination), 6); 
        fclose($fd);
        
        header('Content-Encoding: gzip');
        if($params['format']=='json' || $params['format']=='geojson'){
            header( 'Content-Type: application/json');    
        }else{
            header( 'Content-Type: text/xml');
        }
        unlink($tmp_destination);
        echo $output; 
        unset($output);   
        
        return true;
    }else{
        
        //$content = stream_get_contents($fd);
        $content = file_get_contents($tmp_destination);
        fclose($fd);
        
        if($params['format']=='json' || $params['format']=='geojson'){
            header( 'Content-Type: application/json');    
        }else{
            header( 'Content-Type: text/xml');
        }
        
        if(!(@$params['file']==0 || @$params['file']===false))
        {
            if($params['file']==1 || $params['file']===true){
                $filename = 'Export_'.$params['db'].'_'.date("YmdHis").'.'.$params['format'];    
            }else{
                $filename = $params['file'].'.'.$params['format'];
            }
            
            header('Content-Disposition: attachment; filename='.$filename);
            header('Content-Length: ' . strlen($content));
        }
        
        if(@$params['restapi']==1 && count($rt_counts)==0){
            http_response_code(404);
        }
        unlink($tmp_destination);
        
        exit($content);
    }
    
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
       


//
// json to xml
//
function array_to_xml( $data, &$xml_data ) {
    foreach( $data as $key => $value ) {
        if( is_numeric($key) ){
            $key = 'item'.$key; //dealing with <0/>..<n/> issues
        }
        if( is_array($value) ) {
            $subnode = $xml_data->addChild($key);
            array_to_xml($value, $subnode);
        } else {
            $xml_data->addChild("$key",htmlspecialchars("$value"));
        }
     }
}

//
// convert heurist record to GeoJSON Feasture
// 
// $extended - include concept codes, term code and labels
// $simplify - simplify paths with more than 1000 vertices
// $leaflet_minimum_fields  - only header fields rec_ID, RecTypeID and rec_Title
//
function getGeoJsonFeature($record, $extended=false, $simplify=false, $leaflet_minimum_fields=false){

    global $system, $defRecTypes, $defDetailtypes, $defTerms, $find_places_for_geo;

    if($extended){
        if($defRecTypes==null) $defRecTypes = dbs_GetRectypeStructures($system, null, 2);
        $idx_name = $defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];

        if($defTerms==null) {
            $defTerms = dbs_GetTerms($system);
            $defTerms = new DbsTerms($system, $defTerms);
        }
    }
    
    if($defDetailtypes==null) $defDetailtypes = dbs_GetDetailTypes($system, null, 2);
    $idx_dname = $defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Name'];
    $idx_dtype = $defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Type'];
    $idx_ccode = $defDetailtypes['typedefs']['fieldNamesToIndex']['dty_ConceptID'];

    $res = array('type'=>'Feature',
        'id'=>$record['rec_ID'], 
        'properties'=>array(),
        'geometry'=>array());

    $rty_ID = $record['rec_RecTypeID'];

    $res['properties'] = $record;
    $res['properties']['details'] = array();

    $geovalues = array();
    $timevalues = array();
    $date_start = null;
    $date_end = null;
    $symbology = null;

    //convert details to proper JSON format, extract geo fields and convert WKT to geojson geometry
    foreach ($record['details'] as $dty_ID=>$field_details) {

        $field_type = $defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_dtype];

        foreach($field_details as $dtl_ID=>$value){ //for detail multivalues

            if(is_array($value)){ //geo,file,resource
                if(@$value['file']){
                    //remove some fields
                    $val = $value['file'];
                    unset($val['ulf_ID']);
                    unset($val['fullPath']);
                    unset($val['ulf_Parameters']);

                }else if(@$value['id']){ //resource
                    $val = $value['id'];
                }else if(@$value['geo']){

                    $wkt = $value['geo']['wkt'];
                    /*if($value['geo']['type']=='r'){
                    //@todo convert rect to polygone  
                    }else if($value['geo']['type']='c'){
                    //@todo convert circle to polygone
                    }*/
                    $json = getJsonFromWkt($wkt, $simplify);
                    if($json){
                       $geovalues[] = $json; 
                    }

                    $val = $wkt;
                    continue;  //it will be included into separate geometry property  
                }
            }else{
                if($field_type=='date' || $field_type=='year'){
                    if($dty_ID==DT_START_DATE){
                        $date_start = temporalToSimple($value);
                    }else if($dty_ID==DT_END_DATE){
                        $date_end = temporalToSimple($value);
                    }else if($value!=null){
                        //parse temporal
                        $ta = temporalToSimpleRange($value);
                        if($ta!=null){
                            if($ta[0]=='' && $ta[1]!='') $ta[0] = $ta[1];
                            if($ta[3]=='' && $ta[2]!='') $ta[3] = $ta[2];
                            $timevalues[] = $ta;
                        } 
                    }
                }else if(defined('DT_SYMBOLOGY') && $dty_ID==DT_SYMBOLOGY){
                    $symbology = json_decode($value,true);                    
                }
                $val = $value;
            }
            
            if(!isset($val)) $val = '';

            $val = array('ID'=>$dty_ID,'value'=>$val);

            if(@$value['id']>0){ //resource
                $val['resourceTitle']     = @$value['title'];
                $val['resourceRecTypeID'] = @$value['type'];
            }

            if($extended){
                //It needs to include the field name and term label and term standard code.
                if($field_type=='enum' || $field_type=='relationtype'){
                    $val['termLabel'] = $defTerms->getTermLabel($val, true);
                    $term_code  = $defTerms->getTermCode($val);
                    if($term_code) $val['termCode'] = $term_code;    
                }

                if(@$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dty_ID]){
                    $val['fieldName'] = $defRecTypes['typedefs'][$rty_ID]['dtFields'][$dty_ID][$idx_name];    
                }else{
                    //non standard field
                    $val['fieldName'] = $defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_dname];    
                }

                $val['fieldType'] = $field_type;
                $val['conceptID'] = $defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_ccode];
            }

            $res['properties']['details'][] = $val;
        } //for detail multivalues
    } //for all details of record

    
    if($leaflet_minimum_fields){
        $res['properties']['details'] = null;
        unset($res['properties']['details']);
    }
    
    if(count($geovalues)==0 && $find_places_for_geo){
        //this record does not have geo value - find it in related/linked places
        $geodetails = recordSearchGeoDetails($system, $record['rec_ID']);    
        foreach ($geodetails as $dty_ID=>$field_details) {
            foreach($field_details as $dtl_ID=>$value){ //for detail multivalues
                    $wkt = $value['geo']['wkt'];
                    $json = getJsonFromWkt($wkt, $simplify);
                    if($json){
                       $geovalues[] = $json; 
                    }
            }
        }
    }

    if(count($geovalues)>1){
        $res['geometry'] = array('type'=>'GeometryCollection','geometries'=>$geovalues);
    }else if(count($geovalues)==1){
        $res['geometry'] = $geovalues[0];
    }else{
        //$res['geometry'] = [];
    }

    if($date_start!=null || $date_end!=null){
        if($date_start==null){
            $date_start = $date_end;
        }
        if($date_end==null) $date_end = '';
        $timevalues[] = array($date_start, '', '', $date_end, '');
    }

    if(count($timevalues)>0){
        //https://github.com/kgeographer/topotime/wiki/GeoJSON%E2%80%90T
        // "timespans": [["-323-01-01 ","","","-101-12-31","Hellenistic period"]],  [start, latest-start, earliest-end, end, label]
        $res['when'] = array('timespans'=>$timevalues);
    }
    if($symbology){
        $res['style'] = $symbology;
    }

    return $res;

}

function getExtentFromWkt($wkt)
{
        $bbox = null;
        $geom = geoPHP::load($wkt, 'wkt');
        if(!$geom->isEmpty()){
            $bbox = $geom->getBBox();
        }
        return $bbox;
}

//
//
//
function getJsonFromWkt($wkt, $simplify=true)
{
        $geom = geoPHP::load($wkt, 'wkt');
        if(!$geom->isEmpty()){

            /*The GEOS-php extension needs to be installed for these functions to be available 
            if($simplify)$geom->simplify(0.0001, TRUE);
            */

            $geojson_adapter = new GeoJSON(); 
            $json = $geojson_adapter->write($geom, true); 

            if(count($json['coordinates'])>0){

                if($simplify){
                    if($json['type']=='LineString'){

                        simplifyCoordinates($json['coordinates']);

                    } else if($json['type']=='Polygon'){
                        for($idx=0; $idx<count($json['coordinates']); $idx++){
                            simplifyCoordinates($json['coordinates'][$idx]);
                        }
                    } else if ( $json['type']=='MultiPolygon' || $json['type']=='MultiLineString')
                    {
                        for($idx=0; $idx<count($json['coordinates']); $idx++) //shapes
                            for($idx2=0; $idx2<count($json['coordinates'][$idx]); $idx2++) //points
                                simplifyCoordinates($json['coordinates'][$idx][$idx2]);
                    }
                }

                return $json;
            }
        }//isEmpty
        return null;
}



//
// convert heurist record to more interpretable format
// 
// $mode = 0 - as is, 1 - details in format {dty_ID: val: }, 2 - with concept codes and names/labels
//
function getJsonFeature($record, $mode){

    global $system, $defRecTypes, $defDetailtypes, $defTerms;
    
    if($mode==0){ //leave as is
        return $record;
    }

    $rty_ID = $record['rec_RecTypeID'];

    $res = $record;
    $res['details'] = array();
    
    
    if($mode==2){ //extended - with concept codes and names/labels
        if($defRecTypes==null) $defRecTypes = dbs_GetRectypeStructures($system, null, 2);
        $idx_name = $defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];

        if($defTerms==null) {
            $defTerms = dbs_GetTerms($system);
            $defTerms = new DbsTerms($system, $defTerms);
        }
    
        if($defDetailtypes==null) $defDetailtypes = dbs_GetDetailTypes($system, null, 2);
        $idx_dname = $defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Name'];
        $idx_dtype = $defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Type'];
        $idx_ccode = $defDetailtypes['typedefs']['fieldNamesToIndex']['dty_ConceptID'];
        
        
        $idx_ccode2 = $defRecTypes['typedefs']['commonNamesToIndex']['rty_ConceptID'];
        
        $res['rec_RecTypeName'] = $defRecTypes['names'][$rty_ID];    
        $idx_ccode2 = $defRecTypes['typedefs'][$rty_ID]['commonFields'][$idx_ccode2];
        if($idx_ccode2) $res['rec_RecTypeConceptID'] = $idx_ccode2;
        
        
    }


    //convert details to proper JSON format, extract geo fields and convert WKT to geojson geometry
    foreach ($record['details'] as $dty_ID=>$field_details) {

        foreach($field_details as $dtl_ID=>$value){ //for detail multivalues
        
            $val = array('dty_ID'=>$dty_ID,'value'=>$value);

            if($mode==2){ //extended - with concept codes and names/labels
                $field_type = $defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_dtype];
                //It needs to include the field name and term label and term standard code.
                if($field_type=='enum' || $field_type=='relationtype'){
                    $val['termLabel'] = $defTerms->getTermLabel($value, true);
                    $term_code  = $defTerms->getTermCode($value);
                    if($term_code) $val['termCode'] = $term_code; 
                    $term_code  = $defTerms->getTermConceptID($value);
                    if($term_code) $val['termConceptID'] = $term_code;    
                }

                if(@$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dty_ID]){
                    $val['fieldName'] = $defRecTypes['typedefs'][$rty_ID]['dtFields'][$dty_ID][$idx_name];    
                }else{
                    //non standard field
                    $val['fieldName'] = $defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_dname];    
                }

                $val['fieldType'] = $field_type;
                $val['conceptID'] = $defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_ccode];
            }

            $res['details'][] = $val;
        } //for detail multivalues
    } //for all details of record

    return $res;
}

/**
* returns xml string with gephi links
* 
* @param mixed $records - array of record ids to limit output only for links in this array
* @param mixed $links - array of relations produced by recordSearchRelated
*/
function _composeGephiLinks(&$records, &$links, &$links_cnt, $direction){
    

    global $system, $defRecTypes, $defDetailtypes, $defTerms;

    if($defTerms==null) {
            $defTerms = dbs_GetTerms($system);
            $defTerms = new DbsTerms($system, $defTerms);
    }
    
    if($defDetailtypes==null) $defDetailtypes = dbs_GetDetailTypes($system, null, 2);
    $idx_dname = $defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Name'];
    
    
   $edges = ''; 
    
   if($links){
       
        foreach ($links as $link){
            if($direction=='direct'){
                $source =  $link->recID;
                $target =  $link->targetID;
            }else{
                $source = $link->sourceID;
                $target = $link->recID;
            }
            
            $dtID = $link->dtID;
            $trmID = $link->trmID;
            $relationName = "Floating relationship";
            $relationID = 0;
        
            if(in_array($source, $records) && in_array($target, $records)){
                
                    if($dtID > 0) {
                        //type = window.hWin.HEURIST4.detailtypes.typedefs[dtID].commonFields[1];
                        $relationName = $defDetailtypes['typedefs'][$dtID]['commonFields'][$idx_dname];
                        $relationID = $dtID;
                    }else if($trmID > 0) {
                        $relationName = $defTerms->getTermLabel($trmID, true);
                        $relationID = $trmID;
                    }
                    
                    $relationName  = htmlspecialchars($relationName);                               
                    //$image = htmlspecialchars(HEURIST_TERM_URL.$trmID.'.png');
                    //<attvalue for="2" value="{$image}"/>
                    $links_cnt++; 
                                       
                    $edges = $edges.<<<XML
<edge id="{$links_cnt}" source="{$source}" target="{$target}" weight="1">                               
    <attvalues>
    <attvalue for="0" value="{$relationID}"/>
    <attvalue for="1" value="{$relationName}"/>
    <attvalue for="3" value="1"/>
    </attvalues>
</edge>
XML;
                    
                    
            }   
        }//for
   }
   return $edges;         
}
?>