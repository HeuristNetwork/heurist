<?php

    /**
    * Read file map source record (KML, CSV or DBF) and returns content either file snippet or entire file
    * 
    * params
    * recID
    * format = geojson converts file to geojson 
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
    
    require_once (dirname(__FILE__).'/../../vendor/autoload.php'); //for geoPHP
    require_once(dirname(__FILE__).'/../../viewers/map/Simplify.php');
    require_once (dirname(__FILE__).'/../import/importParser.php'); //parse CSV, KML and save into import table
    require_once (dirname(__FILE__).'/../import/exportRecords.php');
    
    $response = array();

    $system = new System();
    
    $params = $_REQUEST;
    
    $parser_parms = array();
    
    $input_format = null;
    
    if( ! $system->init(@$params['db']) ){
        //get error and response
        $system->error_exit_api(); //exit from script
    }
    if(!(@$params['recID']>0)){
        $system->error_exit_api('recID parameter is not defined or has wrong value'); //exit from script
    }
    $system->defineConstants();
    
    /*
    if(!(defined('DT_KML') && defined('DT_KML_FILE'))){
        $system->error_exit_api('Database '.$params['db'].' does not have field definitions for KML/CSV snipppet and file'); //exit from script
    }*/
    
    $record = array("rec_ID"=>$params['recID']);
    //load record with details and 2 header fields
    $record = recordSearchByID($system, $params['recID'], true, "rec_ID, rec_RecTypeID");
    //array(DT_KML, DT_KML_FILE, DT_FILE_RESOURCE));
    if (@$record["details"] &&
       (@$record["details"][DT_KML] || @$record["details"][DT_KML_FILE] || @$record["details"][DT_FILE_RESOURCE]))
    {
            $input_format = ($record['rec_RecTypeID']==RT_KML_SOURCE)?'kml':'csv';
    
            if(@$record["details"][DT_KML]){
                //snippet
                $file_content = array_shift($record["details"][DT_KML]);   
            }
            else
            {
                if(@$record["details"][DT_KML_FILE]){
                    $kml_file = array_shift($record["details"][DT_KML_FILE]);
                }else{
                    $kml_file = array_shift($record["details"][DT_FILE_RESOURCE]);
                }
                $kml_file = $kml_file['file'];
                $url = @$kml_file['ulf_ExternalFileReference'];
                
                if($url){
                    $file_content = loadRemoteURLContent($url, true);    
                    if($file_content===false){
                      $system->error_exit_api('Cannot load remote file '.$url, HEURIST_ERROR);    
                    } 
                    
                    $ext = strtolower(substr($url,-4,4));
                    
                }else {
                    $filepath = resolveFilePath($kml_file['fullPath']);
                    
                    if (file_exists($filepath)) {
                        
                        $ext = strtolower(substr($filepath,-4,4));
                }else if($ext=='.csv'){
                        
                        if(strpos(strtolower($filepath),'.kmz')==strlen($filepath)-4){
                            //check if scratch folder exists
                            $res = folderExistsVerbose(HEURIST_SCRATCH_DIR, true, 'scratch');
                            if($res!==true){
                                $system->error_exit_api('Cannot extract kmz data to "scratch" folder. '.$res, HEURIST_ERROR);    
                            }
                            
                            $files = unzipArchiveFlat($filepath, HEURIST_SCRATCH_DIR);
                            
                            foreach($files as $filename){
                                if(strpos(strtolower($filename),'.kml')==strlen($filename)-4){
                                    $filepath = $filename;
                                }else{
                                    unlink( $filename );
                                }
                            }
                                
                        }
                        $file_content = file_get_contents($filepath);
                    }else{
                        error_log('Cannot load file '.$kml_file['fullPath']);                    
                        exit(); //@todo error return
                    }
                }
                
                if($input_format=='kml' || $ext=='.kmz' || $ext=='.kml'){
                    $input_format = 'kml';                                                                                      
                    $input_format = 'csv';
                }else if($ext=='.tsv'){
                    $input_format = 'csv';
                    $parser_parms['csv_delimiter'] = 'tab';
                }
            }
        
            //output format        
            if(@$params['format']=='geojson'){
                
                //detect type of data
                if($input_format==null){
                    $totest = strtolower($file_content);
                    $pos = strpos($totest,'<placemark>');
                    if($pos!==false && $pos < strpos($totest,'</placemark>')){
                        $input_format = 'kml';
                    }else{
                        $input_format = 'csv';
                    }
                }
                
                /*
                $system->defineConstant('DT_NAME');
                $system->defineConstant('DT_EXTENDED_DESCRIPTION');
                $system->defineConstant('DT_START_DATE');
                $system->defineConstant('DT_END_DATE');
                $system->defineConstant('DT_GEO_OBJECT');
                */
                
                //X 2-930, Y 2-931, t1 2-932, t2 2-933, Name 2-934, Summary description 2-935
                $mapping = array();
                $fm_name = ConceptCode::getDetailTypeLocalID('2-934');
                $fm_desc = ConceptCode::getDetailTypeLocalID('2-935');
                $fm_X = ConceptCode::getDetailTypeLocalID('2-930');
                $fm_Y = ConceptCode::getDetailTypeLocalID('2-931'); 
                $fm_t1 = ConceptCode::getDetailTypeLocalID('2-932');
                $fm_t2 = ConceptCode::getDetailTypeLocalID('2-933');
                
                
                if($fm_name!=null && is_array(@$record["details"][$fm_name])){
                    $mapping[DT_NAME] = array_shift($record["details"][$fm_name]);
                }
                if($fm_desc!=null && is_array(@$record["details"][$fm_desc])){
                    $mapping[DT_EXTENDED_DESCRIPTION] = array_shift($record["details"][$fm_desc]);
                }
                    
                if($fm_t1!=null && is_array(@$record["details"][$fm_t1])){
                    $mapping[DT_START_DATE] = array_shift($record["details"][$fm_t1]);
                }
                if($fm_t2!=null && is_array(@$record["details"][$fm_t2])){
                    $mapping[DT_END_DATE] = array_shift($record["details"][$fm_t2]);
                }


                if($input_format == 'kml'){
                    $parser_parms['kmldata'] = true; 
                    $mapping[DT_GEO_OBJECT] = 'geometry';
                    if(!@$mapping[DT_START_DATE]) $mapping[DT_START_DATE] = 'timespan_begin';//'timespan';
                    if(!@$mapping[DT_END_DATE]) $mapping[DT_END_DATE] = 'timespan_end';//'timespan';
                    if(!@$mapping[DT_DATE]) $mapping[DT_DATE] = 'timestamp'; //'when'; 
                    
                }else{
                    $parser_parms['csvdata'] = true; 
                    
                    if($fm_X!=null && @$record["details"][$fm_X]){
                        $mapping['longitude'] = array_shift($record["details"][$fm_X]);
                    }
                    if($fm_Y!=null && @$record["details"][$fm_Y]){
                        $mapping['latitude'] = array_shift($record["details"][$fm_Y]);
                    }
                        
                }
                    
                if(!@$mapping[DT_NAME]){
                    $mapping[DT_NAME] = 'name';  
                } 
                
                if(count($mapping)>0){
                    
                    //returns csv with header
                    $parsed = ImportParser::parseAndValidate($file_content, null, PHP_INT_MAX, $parser_parms);
                    //'fields'=>$header, 'values'=>$parsed_values
                    
                    //returns records in PLACE? format recordSearchByID/recordSearchDetails
                    $records = ImportParser::convertParsedToRecords($parsed, $mapping);
                    
                    //it outputs geojson and exits 
                    $recdata = array('status'=>HEURIST_OK, 'data'=>array('reccount'=>count($records), 'records'=>$records));
                    ExportRecords::output($recdata, array('format'=>'geojson','leaflet'=>true,'depth'=>0, 'simplify'=>true));
                    
                    
                }else{
                    //etire kml is considered as unified map entry
                    try{
                        $geom = geoPHP::load($file_content, 'kml');
                    }catch(Exception $e){
                        $system->error_exit_api('Cannot process kml: '.$e->getMessage(), HEURIST_ERROR);    
                    }
                    if($geom!==false && !$geom->isEmpty()){
                        
                        
                            $geojson_adapter = new GeoJSON(); 
                            $json = $geojson_adapter->write($geom, true); 
                            
                            if(@$json['coordinates'] && count($json['coordinates'])>0){
                                
                                if(@$params['simplify']){
                                    
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
                                
                                $json = array(array(
                                    'type'=>'Feature',
                                    'geometry'=>$json,
                                    'properties'=>array()
                                ));
                            }else if(false){
                                
                            }
                            
                            $json = json_encode($json);
                            header('Content-Type: application/json');
                            //header('Content-Type: application/vnd.geo+json');
                            //header('Content-Disposition: attachment; filename=output.json');
                            header('Content-Length: ' . strlen($json));
                            exit($json);
                    }else{
                        $system->error_exit_api('No coordinates retrieved from kml file', HEURIST_ERROR);
                    }
                }

                
            }
            else
            if(@$params['format']=='zip_with_metadata'){
                
                //archive file with record metadata
                
        
            }
            else
            {
                //downloadFile()
                
                if($input_format=='kml'){
                    header('Content-Type: application/vnd.google-earth.kml+xml');
                    header('Content-Disposition: attachment; filename=output.kml');
                }else if($input_format=='csv'){
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename=output.csv');
                }else if($input_format=='dbf'){
                    header('Content-Type: application/x-dbase');
                    header('Content-Disposition: attachment; filename=output.dbf');
                } 
                header('Content-Length: ' . strlen($file_content));
                exit($file_content);
            }
    }else{
        $system->error_exit_api('Database '.$params['db'].'. Record '.$params['recID'].' does not have data for KML/CSV snipppet or file');
    }
?>