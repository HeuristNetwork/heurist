<?php

    /**
    * Application interface. See hRecordMgr in hapi.js
    * Record search and output in required format
    * used in recordExportCSV.js
    * 
    * 
    * parameters
    * db - heurist database
    * format = json|csv|kml|xml|hml|gephi
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
    require_once(dirname(__FILE__).'/../../common/php/Temporal.php');
    require_once(dirname(__FILE__).'/../../admin/verification/verifyValue.php');

    require_once (dirname(__FILE__).'/../../vendor/autoload.php'); //for geoPHP
    require_once(dirname(__FILE__).'/../../viewers/map/Simplify.php');
    
    
    $response = array();

    $system = new System();
    
    //global variable to keep defs
    $rtStructs = null;
    $detailtypes = null;
    $rtTerms = null;
    
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
    $search_params['rules'] = @$params['rules'];
    if(!(@$params['offset'] || @$params['limit']))
        $search_params['needall'] = 1;  //search without limit of returned record count
    
    if(@$params['recID']>0){
        $search_params['q'] = array('ids'=>$params['recID']);
    }else if(@$params['ids']){
        $search_params['q'] = array('ids'=>implode(',',$params['ids']));
    }else{
        $search_params['q'] = @$params['q'];
    }
    if($search_params['q']==null || $search_params['q']==''){
        $search_params['q'] = 'sortby:-m'; //get all records
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
        
        if(!output_Records($system, $response, $params)){
            $system->error_exit_api();
        }
        
        //output_Data($stream, $params);
    }
exit();


//
// output data as 
//           zip  : 0|1  compress
//           file : 0|1  output as file or ptintout
//           db   : database name for file name
//
function output_Data($params, $stream=null, $tmpfile=null){
    
    $error_log = array();
    $mimetype = 'application/'.$params['format'];
/*
    $out = false;

    if($stream==null){
        array_push($error_log, "Output stream is not defined");
    }else{
        rewind($stream);
        $out = stream_get_contents($stream);
        fclose($stream);
    }

    if($out===false || strlen($out)==0){
        array_push($error_log, "Output stream is empty");
        $out = array('error'=>$error_log );
    }
    $error_log = array();
*/
 
    if(@$params['file']!=1){
        //printout
        if(@$params['zip']==1){
            ob_start(); 
            echo $out;
            $output = gzencode(ob_get_contents(),6); 
            ob_end_clean(); 
            header('Content-Encoding: gzip');
            header('Content-type: '.$mimetype.';charset=UTF-8');
            echo $output; 
            unset($output);   
        }else{
            header('Content-type: '.$mimetype.';charset=UTF-8');
            echo $out;
        }
        unset($out);
        
    }else{ 
        //as file
        $filename = 'Export_'.$params['db'].'_'.date("YmdHis").'.'.$params['format'];
        
        if(@$params['zip']==1){
            //as zip file
            $zipname = 'Export_'.$system->dbname().'_'.date("YmdHis").'.zip';
            $destination = tempnam(HEURIST_SCRATCH_DIR, "zip");
            
            $zip = new ZipArchive();
            if (!$zip->open($destination, ZIPARCHIVE::OVERWRITE)) {
                array_push($error_log, "Cannot create zip $destination");    
            }else{
                // add the in-memory file to the archive, giving a name
                $zip->addFromString($filename,  $out);
                $zip->close();
                if(@file_exists($destination)>0){
                    header('Content-Type: application/zip');
                    header('Content-disposition: attachment; filename='.$zipname);
                    header('Content-Length: ' . filesize($destination));
                    readfile($destination);
                    // remove the zip archive
                    unlink($destination);    
                }else{
                    array_push($error_log, "Zip archive ".$destination." doesn't exist");
                }
            }
            if(count($error_log)>0){
               //can't create zip archive 
               $out = implode(PHP_EOL, $error_log);
               header('Content-Type: text/csv');
               header('Content-disposition: attachment; filename=log.txt');
               header('Content-Length: ' . strlen($out));
               exit($out);
            }
            
        }else{
            //without compress
            header('Content-Type: '.$mimetype);
            header('Content-disposition: attachment; filename='.$filename);
            header('Content-Length: ' . strlen($out));
            exit($out);        
        }
    }
    
}

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
    global $rtStructs;
    
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
    
    if($rtStructs==null) $rtStructs = dbs_GetRectypeStructures($system, null, 2);
    $idx_name = $rtStructs['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];
    $idx_dtype = $rtStructs['typedefs']['dtFieldNamesToIndex']['dty_Type'];
    $idx_term_tree = $rtStructs['typedefs']['dtFieldNamesToIndex']['rst_FilteredJsonTermIDTree'];
    $idx_term_nosel = $rtStructs['typedefs']['dtFieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];
    

    if($include_term_label_and_code){
        $rtTerms = dbs_GetTerms($system);
        /*
        $idx_term_label = $rtTerms['fieldNamesToIndex']['trm_Label'];
        $idx_term_code = $rtTerms['fieldNamesToIndex']['trm_Code'];
        $rtTerms = $rtTerms['termsByDomainLookup']['enum'];
        */
        $rtTerms = new DbsTerms($system, $rtTerms);
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
                        $field_name = $rtStructs['typedefs'][$rt]['dtFields'][$dt_id][$idx_name];
                        $field_type = $rtStructs['typedefs'][$rt]['dtFields'][$dt_id][$idx_dtype];
                    }
                    if($constr_rt_id>0){
                        $rectypename_is_in_fieldname = (strpos(strtolower($field_name), 
                                            strtolower($rtStructs['names'][$constr_rt_id]))!==false);
                        $field_name_title = $field_name.' '
                                                //.($rectypename_is_in_fieldname?'':($rtStructs['names'][$constr_rt_id].' '))
                                                .'RecordTitle';
                        $field_name = $field_name.($rectypename_is_in_fieldname
                                            ?'':' ('.$rtStructs['names'][$constr_rt_id].')').' H-ID';
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
                            $field_name = $rtStructs['names'][$rt].' H-ID';
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
                            
                            $all_terms = $rtStructs['typedefs'][$rty_ID]['dtFields'][$dt_id][$idx_term_tree];
                            $nonsel_terms = $rtStructs['typedefs'][$rty_ID]['dtFields'][$dt_id][$idx_term_nosel];
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
                            && @$rtStructs['typedefs'][$source_rt]['dtFields'][$dt_id]
                           ){ //contrained rt and allowed relation type
                            
                            $all_terms = $rtStructs['typedefs'][$source_rt]['dtFields'][$dt_id][$idx_term_tree];
                            $nonsel_terms = $rtStructs['typedefs'][$source_rt]['dtFields'][$dt_id][$idx_term_nosel];
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
                        $dt_type = $rtStructs['typedefs'][$rty_ID]['dtFields'][$dt_id][$idx_dtype];    
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
                                        $enum_label[] = $rtTerms->getTermLabel($val, $include_term_hierarchy);
                                        // @$rtTerms[$val][$idx_term_label]?$rtTerms[$val][$idx_term_label]:'';
                                        $enum_code[] = $rtTerms->getTermCode($val);
                                        //@$rtTerms[$val][$idx_term_code]?$rtTerms[$val][$idx_term_code]:'';
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
// $parmas 
//    format - json|geojson|xml
//    defs  0|1  include database definitions
//    file  0|1
//    zip   0|1
//
// @todo if output if file and zip - output datatabase,defintions and records as separate files
//      split records by 1000 entries chunks
//
function output_Records($system, $data, $params){
    global $rtStructs;
    
    if (!($data && @$data['status']==HEURIST_OK)){
        return false;
    }

    $data = $data['data'];
    
    if(@$data['memory_warning']){
        $records = array(); //@todo
    }else if(!(@$data['reccount']>0)){
        $records = array();
    }else{
        $records = $data['records'];
    }
    
    $records_out = array(); //ids already out
    $rt_counts = array(); //counts of records by record type
    
    $error_log = array();
    $error_log[] = 'Total rec count '.count($records);

    $tmp_destination = tempnam(HEURIST_SCRATCH_DIR, "exp");    
    //$fd = fopen('php://temp/maxmemory:1048576', 'w');  //less than 1MB in memory otherwise as temp file 
    $fd = fopen($tmp_destination, 'w');  //less than 1MB in memory otherwise as temp file 
    if (false === $fd) {
        $system->addError(HEURIST_SYSTEM_CONFIG, 'Failed to create temporary file in scratch folder');
        return false;
    }   
    
    //OPEN BRACKETS
    if($params['format']=='geojson'){

        if($params['leaflet']){
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
    }else{
        fwrite($fd, '<?xml version="1.0" encoding="UTF-8"?><heurist><records>');     
    }

    //CONTENT
    $timeline_data = [];
    
    $comma = '';
    
    $idx = 0;
    while ($idx<count($records)){ //replace to WHILE
    
        $recID = $records[$idx];
        $idx++;
        $record = recordSearchByID($system, $recID, true);
        
        $rty_ID = $record['rec_RecTypeID'];
        
        if(!@$rt_counts[$rty_ID]){
            $rt_counts[$rty_ID] = 1;
        }else{
            $rt_counts[$rty_ID]++;
        }
        
        if($params['format']=='geojson'){
            
            $feature = getGeoJsonFeature($record, false, @$params['simplify']);
            if($params['leaflet']){
                   if(@$feature['when']){
                        $timeline_data[] = array('rec_ID'=>$recID, 'when'=>$feature['when']['timespans'], 
                                        'rec_RecTypeID'=>$rty_ID, "rec_Title"=>$record['rec_Title']);
                        $feature['when'] = null;
                        unset($feature['when']);
                   }
                   if(!@$feature['geometry']) continue;
            }

            fwrite($fd, $comma.json_encode($feature));
            $comma = ',';
        
        }else if($params['format']=='json'){ 
            fwrite($fd, $comma.json_encode($record));
            $comma = ',';
        }else{
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><record/>');
            array_to_xml($record, $xml);
            //array_walk_recursive($record, array ($xml , 'addChild'));
            fwrite($fd, substr($xml->asXML(),38));
        }
    }//while records
    
    
    //CLOSE brackets
    
    if($params['format']=='geojson'){
        
        fwrite($fd, ']');
        
        if($params['leaflet']){ //return 2 array - pure geojson and timeline items
           fwrite($fd, ',"timeline":'.json_encode($timeline_data).'}');
        }
        
    }else if(@$params['restapi']==1){
        if(count($records)==1 && @$params['recID']>0){
            //fwrite($fd, '');             
        }else{ 
            //@todo xml for api
            fwrite($fd, ']}');             
        }
    }else{
    
        if($params['format']=='json'){
            fwrite($fd, ']');     
        }else{
            fwrite($fd, '</records>');     
        }
        
        if($params['format']!=='geojson'){
        
            // include defintions
            if(@$params['defs']==1){
                
                if($rtStructs==null) $rectypes = dbs_GetRectypeStructures($system, null, 2);
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
                $detailtypes = array('detailtypes'=>$detailtypes);
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
        
    }
 
    if(@$params['zip']==1){
        
    }else{
 
        //$content = stream_get_contents($fd);
        $content = file_get_contents($tmp_destination);
        fclose($fd);
        
        if($params['format']=='json' || $params['format']=='geojson'){
            header( 'Content-Type: application/json');    
        }else{
            header( 'Content-Type: text/xml');
        }
        
        if(@$params['restapi']==1 && count($rt_counts)==0){
            http_response_code(404);
        }
        
        exit($content);
    }
    unlink($tmp_destination);
    
}

//
// output rectordtype template as csv (and terms pckup list)
//
function output_HeaderOnly($system, $data, $params)
{
    global $rtStructs;
    
    $include_term_ids = (@$params['prefs']['include_term_ids']==1);
    $include_term_codes = (@$params['prefs']['include_term_codes']==1);
    $include_resource_titles =  (@$params['prefs']['include_resource_titles']==1);
    $include_term_hierarchy = (@$params['prefs']['include_term_hierarchy']==1);
    
    $fields = @$params['prefs']['fields'];
    $details = array();  //array of detail fields included into output
    $relmarker_details = array(); //relmarker fields included into output
    
    if($rtStructs==null) $rtStructs = dbs_GetRectypeStructures($system, null, 2);
    $idx_name = $rtStructs['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];
    $idx_dtype = $rtStructs['typedefs']['dtFieldNamesToIndex']['dty_Type'];
    $idx_term_tree = $rtStructs['typedefs']['dtFieldNamesToIndex']['rst_FilteredJsonTermIDTree'];
    $idx_term_nosel = $rtStructs['typedefs']['dtFieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];
    

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
                        $field_name = $rtStructs['typedefs'][$rt]['dtFields'][$dt_id][$idx_name];
                        $field_type = $rtStructs['typedefs'][$rt]['dtFields'][$dt_id][$idx_dtype];
                    }
                    if($constr_rt_id>0){
                        $rectypename_is_in_fieldname = (strpos(strtolower($field_name), 
                                            strtolower($rtStructs['names'][$constr_rt_id]))!==false);
                        $field_name_title = $field_name.' '
                                                //.($rectypename_is_in_fieldname?'':($rtStructs['names'][$constr_rt_id].' '))
                                                .'RecordTitle';
                        $field_name = $field_name.($rectypename_is_in_fieldname
                                            ?'':' ('.$rtStructs['names'][$constr_rt_id].')').' H-ID';
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
                            $field_name = $rtStructs['names'][$rt].' H-ID';
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
                                             'term_ids'=>$rtStructs['typedefs'][$rt]['dtFields'][$dt_id][$idx_term_tree],
                                             'nonsel'=>$rtStructs['typedefs'][$rt]['dtFields'][$dt_id][$idx_term_tree]);
                    
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
        $rtTerms = dbs_GetTerms($system);
        $rtTerms = new DbsTerms($system, $rtTerms);
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
                $placeholders = array_fill(0, $cnt_cols, '');
                
                foreach($terms_pickup[$rty_ID] as $dtid => $field){
                    $headers[$rty_ID][] = $field['name'].': Lookup list';
                    $placeholders[] = 'Use to create a value control list';
                    //get list of terms
                    $terms = $rtTerms->getAllowedTermsForField( $field['term_ids'], $field['nonsel'], $field['domain'] );
                    $max_count = max($max_count, count($terms));    
                    $terms_pickup[$rty_ID][$dtid]['terms'] = $terms;
                }
            }
            
            $fd = fopen('php://temp/maxmemory:1048576', 'w');  //less than 1MB in memory otherwise as temp file 
            $streams[$rty_ID] = $fd;
            
            //write header
            fputcsv($fd, $headers[$rty_ID], $csv_delimiter, $csv_enclosure);
              
            //write terms
            if($placeholders!=null){
                
                fputcsv($fd, $placeholders, $csv_delimiter, $csv_enclosure);
            
                $k = 0;
                while ($k<$max_count){

                    $placeholders = array_fill(0, $cnt_cols, '');
                    
                    foreach($terms_pickup[$rty_ID] as $dtid => $field){
                        
                        $terms = $terms_pickup[$rty_ID][$dtid]['terms'];

                        if($k<count($terms)){
                            $placeholders[] =  $rtTerms->getTermLabel($terms[$k], true);
                        }else{
                            $placeholders[] = '';    
                        }
                    }//for fields
                    
                    fputcsv($fd, $placeholders, $csv_delimiter, $csv_enclosure);
            
                    $k++;
                }//while
            
            }

            if($temp_name==null)
                $temp_name = 'Template_'.$system->dbname().'_'.$rty_ID.'_'.$rtStructs['names'][$rty_ID];
        }
    }
    writeResults( $streams, $temp_name, $headers, null );
}

   
//
// save streams into file and zip 
//        
function writeResults( $streams, $temp_name, $headers, $error_log ) {
  
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
                        $filename = $filename.'_'.$rty_ID.'_'.$rtStructs['names'][$rty_ID];
            }
            $filename = $filename.'_'.date("YmdHis").'.csv';
        
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
        header('Content-disposition: attachment; filename='.$filename);
        header('Content-Length: ' . strlen($out));
        exit($out);
        
    }else{
    
        $zipname = $temp_name.'_'.date("YmdHis").'.zip';
        $destination = tempnam(HEURIST_SCRATCH_DIR, "zip");
        
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
            header('Content-disposition: attachment; filename='.$zipname);
            header('Content-Length: ' . filesize($destination));
            readfile($destination);

            // remove the zip archive
            unlink($destination);    
        
        }else{
            array_push($error_log, "Zip archive ".$destination." doesn't exist");
            
            $out = implode(PHP_EOL, $error_log);
            header('Content-Type: text/csv');
            header('Content-disposition: attachment; filename=log.txt');
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
//
function getGeoJsonFeature($record, $extended=false, $simplify=false){

    global $system, $rtStructs, $detailtypes, $rtTerms;

    $rtTerms = null;

    if($extended){
        if($rtStructs==null) $rtStructs = dbs_GetRectypeStructures($system, null, 2);
        $idx_name = $rtStructs['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];

        if($rtTerms==null) {
            $rtTerms = dbs_GetTerms($system);
            $rtTerms = new DbsTerms($system, $rtTerms);
        }
    }

    if($detailtypes==null) $detailtypes = dbs_GetDetailTypes($system, null, 2);
    $idx_dname = $detailtypes['typedefs']['fieldNamesToIndex']['dty_Name'];
    $idx_dtype = $detailtypes['typedefs']['fieldNamesToIndex']['dty_Type'];
    $idx_ccode = $detailtypes['typedefs']['fieldNamesToIndex']['dty_ConceptID'];

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

    //convert details to proper JSON format, extract geo fields and convert WKT to geojson geometry
    foreach ($record['details'] as $dty_ID=>$field_details) {

        $field_type = $detailtypes['typedefs'][$dty_ID]['commonFields'][$idx_dtype];

        foreach($field_details as $dtl_ID=>$value){ //for detail multivalues

            if(is_array($value)){
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

                            $geovalues[] = $json;
                        }
                    }//isEmpty


                    $val = $wkt;
                    continue;  //it will be included into separate geometry property  
                }
            }else{
                if($field_type=='date' || $field_type=='year'){
                    if($dty_ID==DT_START_DATE){
                        $date_start = temporalToSimple($value);
                    }else if($dty_ID==DT_END_DATE){
                        $date_end = temporalToSimple($value);
                    }else{
                        //parse temporal
                        $ta = temporalToSimpleRange($value);
                        if($value!=null) $timevalues[] = $ta;
                    }
                }  
                $val = $value;
            }

            $val = array('ID'=>$dty_ID,'value'=>$val);

            if(@$value['id']>0){ //resource
                $val['resourceTitle']     = @$value['title'];
                $val['resourceRecTypeID'] = @$value['type'];
            }

            if($extended){
                //It needs to include the field name and term label and term standard code.
                if($field_type=='enum' || $field_type=='relationtype'){
                    $val['termLabel'] = $rtTerms->getTermLabel($val, true);
                    $term_code  = $rtTerms->getTermCode($val);
                    if(!$term_code) $val['termCode'] = $term_code;    
                }

                if(@$rtStructs['typedefs'][$rty_ID]['dtFields'][$dty_ID]){
                    $val['fieldName'] = $rtStructs['typedefs'][$rty_ID]['dtFields'][$dty_ID][$idx_name];    
                }else{
                    //non standard field
                    $val['fieldName'] = $detailtypes['typedefs'][$dty_ID]['commonFields'][$idx_dname];    
                }

                $val['fieldType'] = $field_type;
                $val['conceptID'] = $detailtypes['typedefs'][$dty_ID]['commonFields'][$idx_ccode];
            }

            $res['properties']['details'][] = $val;
        } //for detail multivalues
    } //for all details of record


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

    return $res;

}

?>