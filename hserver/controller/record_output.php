<?php

    /**
    * Application interface. See hRecordMgr in hapi.js
    * Record search and output in required format
    * 
    * paremeters
    * db - heurist database
    * format = json|csv|kml|hml
    * prefs:{ format specific parameters }, }
    * 
    * prefs for csv
                csv_delimiter :','
                csv_enclosure :'""
                csv_mvsep     :'|',
                csv_linebreak :'nix',
                csv_header    :true
    *           fields        : {rtid:[dtid1, dtid3, dtid2]}
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2018 University of Sydney
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
    require_once(dirname(__FILE__).'/../../common/php/Temporal.php');

    
    $response = array();

    $system = new System();
    
    if(@$_REQUEST['postdata']){
        $params = json_decode($_REQUEST['postdata'], true);
    }else{
        $params = $_REQUEST;
    }
    
    
    if( ! $system->init(@$params['db']) ){
        //get error and response
        $response = $system->getError();
        print 'Error';
        exit();
    }
    
    if(!@$params['format']) $params['format'] = 'json';
    
    $search_params = array();
    $search_params['w'] = @$params['w'];
    $search_params['rules'] = @$params['rules'];
    $search_params['needall'] = 1;
    
    if(@$params['ids']){
        $search_params['q'] = array('ids'=>implode(',',$params['ids']));
    }else{
        $search_params['q'] = $params['q'];
    }

    $is_csv = (@$params['format'] == 'csv');
    if($is_csv){
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
        $search_params['detail'] = 'ids';
    }
    
    
    
    $response = recordSearch($system, $search_params);

    if($is_csv){
        outputXML($system, $response, $params);
    }else {
        // Return the response object as JSON
        header('Content-type: application/json;charset=UTF-8');
        print json_encode($response);
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
                               
for constrained resourse fields we use "dt#:rt#"                                
@todo for enum fields use dt#:code,dt#:id,dt#:label
                               
NOTE: fastest way it simple concatenation in comparison to fputcsv and implode. We use fputcsv
*/
function outputXML($system, $data, $params){
    
    if (!($data && $data['status']==HEURIST_OK)){
        print print_r($rec_ids, true); //print out error array
        return;
    }
    
    $data = $data['data'];
    
    if(!(@$data['reccount']>0)){
        print ''; //'empty result set';
        return;
    }
    
    $fields = @$params['prefs']['fields'];
    $details = array();
    
    $rtStructs = dbs_GetRectypeStructures($system, null, 2);
    $idx_name = $rtStructs['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];
    $idx_dtype = $rtStructs['typedefs']['dtFieldNamesToIndex']['dty_Type'];
    
    //create header
    $any_rectype = null;
    $headers = array();
    if($fields){
        foreach($fields as $rt=>$flds){
            $details[$rt] = array();
            $headers[$rt] = array();
            foreach($flds as $dt_id){
                
                $constr_rt_id = 0;
                if(strpos($dt_id,':')>0){ //for constrained resource fields
                //example author:person or organization
                    list($dt_id, $constr_rt_id) = explode(':',$dt_id);
                }
                
                if(is_numeric($dt_id) && $dt_id>0){
                    
                    array_push($details[$rt], $dt_id);
                    
                    //get field name from structure
                    $field_name = $rtStructs['typedefs'][$rt]['dtFields'][$dt_id][$idx_name];
                    if($constr_rt_id>0){
                        $field_name = $field_name.' ('.$rtStructs['names'][$constr_rt_id].') H-ID';
                    }
                }else{
                    
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
    
                array_push($headers[$rt], $field_name);            
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
    $records_out = array(); //ids already out
    
    $streams = array(); //one per record type
    $idx = 0;
    while ($idx<count($records)){ //replace to WHILE
    
        $recID = $records[$idx];
        $idx++;
        $record = recordSearchByID($system, $recID, false);
        
        $rty_ID = ($any_rectype!=null)?$any_rectype :$record['rec_RecTypeID'];
        
        if(!@$fields[$rty_ID]) continue;
        
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
            
        }else{
            $fd = $streams[$rty_ID];
        }
        
        if(count(@$details[$rty_ID])>0){
            recordSearchDetails($system, $record, $details[$rty_ID]);
        }
        
        //prepare output array
        $record_row = array();
        foreach($fields[$rty_ID] as $dt_id){
            
            $constr_rt_id = 0;
            if(strpos($dt_id,':')>0){ //for constrained resource fields
                list($dt_id, $constr_rt_id) = explode(':',$dt_id);
            }
            
            if(is_numeric($dt_id) && $dt_id>0){
                
                $values = @$record['details'][$dt_id];
                if($values){
                    
                    $dt_type = $rtStructs['typedefs'][$rty_ID]['dtFields'][$dt_id][$idx_dtype];
                    
                    //$values = array_values($values); //get plain array
                    $vals = array();
                    
                    if($dt_type=="resource"){
                        
                            //if record type among selected -  add record to list to be exported
                            //otherwise export only ID  as field "Rectype H-ID"
                            foreach($values as $val){
                                if( (!($constr_rt_id>0)) || $constr_rt_id==$val['type'] ){ //unconstrained or exact required rt
                                    
                                    if($fields[$val['type']]){ //record type exists in output
                                        if(!in_array($val['id'], $records)){
                                                 array_push($records, $val['id']);  //add to be exported  
                                        }
                                    }
                                    $vals[] = $val['id'];
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
                    }else{
                        $vals = $values;
                    }
                    
                    $value = implode($csv_mvsep, $vals);
                }else{
                    $value = null;
                }
            }else if ($dt_id=='rec_Tags'){
                
                $value = recordSearchPersonalTags($system, $recID);
                $value = ($value==null)?'':implode($csv_mvsep, $value);
                
            }else{
                $value = @$record[$dt_id]; //from record header
            }
            if($value==null) $value = '';
            
            $record_row[] = $value;
        }//for fields
        
        // write the data to csv
        fputcsv($fd, $record_row, $csv_delimiter, $csv_enclosure);
        
    }//for records
    
    if(count($streams)==1){
        
        $rty_ID = array_keys($streams);
        $rty_ID = $rty_ID[0];
        
        $filename = 'Export_'.$system->dbname();
        if($rty_ID>0){
            $filename = $filename.'_'.$rtStructs['names'][$rty_ID];
        }
        $filename = $filename.'_'.date("YmdHis").'.csv';
        
        $fd = $streams[$rty_ID];
        
        rewind($fd);
        $out = stream_get_contents($fd);
        fclose($fd);
        
        header('Content-Type: text/csv');
        header('Content-disposition: attachment; filename='.$filename);
        header('Content-Length: ' . strlen($out));
        
        exit($out);
        
    }else{
    
        $zipname = 'Export_'.$system->dbname().'_'.date("YmdHis").'.zip';
        $zip = new ZipArchive;
        $zip->open($zipname, ZipArchive::CREATE);    
        
        foreach($streams as $rty_ID => $fd){
            // return to the start of the stream
            rewind($fd);

            // add the in-memory file to the archive, giving a name
            $zip->addFromString('rectype-'.$rty_ID.'.csv', stream_get_contents($fd) );
            //close the file
            fclose($fd);
        }    
        
        // close the archive
        $zip->close();
        header('Content-Type: application/zip');
        header('Content-disposition: attachment; filename='.$zipname);
        header('Content-Length: ' . filesize($zipname));
        readfile($zipname);

        // remove the zip archive
        // you could also use the temp file method above for this.
        unlink($zipname);    
        
    }


}

    
?>