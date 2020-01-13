<?php

    /**
    * Returns content of shp+dbf files as geojson or wkt output
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
    require_once (dirname(__FILE__).'/../../viewers/map/Simplify.php');
    //require_once (dirname(__FILE__).'/../../vendor/autoload.php'); //for ShapeFile
    
// Register autoloader
require_once('../../vendor/gasparesganga/php-shapefile/src/Shapefile/ShapefileAutoloader.php');
Shapefile\ShapefileAutoloader::register();

// Import classes
use Shapefile\Shapefile;
use Shapefile\ShapefileException;
use Shapefile\ShapefileReader; 

    $response = array();

    $system = new System();
    
    $params = $_REQUEST;
    
    if( ! $system->init(@$params['db']) ){
        //get error and response
        $system->error_exit_api(); //exit from script
    }
    if(!(@$params['recID']>0)){
        $system->error_exit_api('recID parameter is not defined or has wrong value'); //exit from script
    }
    
    $params['simplify'] = true;
    
    $fields = array();
    
    if($system->defineConstant('DT_ZIP_FILE')){
        $fields[] = DT_ZIP_FILE;    
    }else{
        define('DT_ZIP_FILE',0);
    }
    if($system->defineConstant('DT_SHAPE_FILE')){
        $system->defineConstant('DT_DBF_FILE');
        $system->defineConstant('DT_SHX_FILE');
        $fields[] = DT_SHAPE_FILE;    
        $fields[] = DT_DBF_FILE;    
        $fields[] = DT_SHX_FILE;    
    }else{
        define('DT_SHAPE_FILE',0);
    }
    if($system->defineConstant('DT_FILE_RESOURCE')){
        $fields[] = DT_FILE_RESOURCE;    
    }else{
        define('DT_FILE_RESOURCE',0);
    }
    
    
    if( count($fields) == 0 ){
        $system->error_exit_api('Database '.$params['db']
                    .' does not have field definitions for shp, zip or simple resource file'
                    , HEURIST_SYSTEM_CONFIG); //exit from script
    }
    $isZipArchive = false;
    
    $record = array("rec_ID"=>$params['recID']);
    recordSearchDetails($system, $record, $fields);
    
    if (@$record["details"] &&
       (@$record["details"][DT_SHAPE_FILE] || @$record["details"][DT_ZIP_FILE] || @$record["details"][DT_FILE_RESOURCE]))
    {
        
                $dbf_file = null;
                $shx_file = null;

                if(DT_SHAPE_FILE>0 && @$record["details"][DT_SHAPE_FILE]){
                    
                    $shp_file = fileRetrievePath(array_shift($record["details"][DT_SHAPE_FILE]));
                    $dbf_file = fileRetrievePath(array_shift($record["details"][DT_DBF_FILE]));
                    $shx_file = fileRetrievePath(array_shift($record["details"][DT_SHX_FILE]));
                    
                }else if(DT_ZIP_FILE>0 && @$record["details"][DT_ZIP_FILE]){
                    $shp_file = fileRetrievePath(array_shift($record["details"][DT_ZIP_FILE]),'shp',true);
                    $isZipArchive = true;
                }else{
                    $shp_file = fileRetrievePath(array_shift($record["details"][DT_FILE_RESOURCE]),'shp',true);
                    $isZipArchive = true;
                }
                
                try {
                
                    if($dbf_file && file_exists($dbf_file)){
                        
                        $files = array(
                            'shp'   => $shp_file,
                            'dbf'   => $dbf_file);
                            
                        if($shx_file && file_exists($shx_file)){
                            $files['shx'] = $shx_file;    
                        }
                        $shapeFile = new ShapefileReader($files);
                    }else if(file_exists($shp_file)){
                        //if provide only shapefile, it finds other automatically
                        $shapeFile = new ShapefileReader($shp_file);
                    }else{
                        $system->error_exit_api('Cannot process shp file', HEURIST_ERROR);
                    }
                    
                    $json = array();
                    
                    // Read all the records
                    while ($record = $shapeFile->fetchRecord()){
                        
                        // Skip the record if marked as "deleted"
                        if ($record->isDeleted()) {
                            continue;
                        }
                        
                        /* v2 old way
                        $shapeFile->getRecord(Shapefile::GEOMETRY_GEOJSON_FEATURE)) { //GEOMETRY_WKT
                        if ($record['dbf']['_deleted']) continue;
                        
                        $record['shp']
                        */

                        $feature = json_decode($record->getGeoJSON(false,true), true);
                        
                        if(@$params['simplify']){
                            $geo = @$feature['geometry'];
                            if(is_array(@$geo['coordinates']) && count(@$geo['coordinates'])>0){
                                
                                if($geo['type']=='LineString'){

                                    simplifyCoordinates($geo['coordinates']);

                                } else if($geo['type']=='Polygon'){
                                    for($idx=0; $idx<count($geo['coordinates']); $idx++){
                                        simplifyCoordinates($geo['coordinates'][$idx]);
                                    }
                                } else if ( $geo['type']=='MultiPolygon' || $geo['type']=='MultiLineString')
                                {
                                    for($idx=0; $idx<count($geo['coordinates']); $idx++) //shapes
                                        for($idx2=0; $idx2<count($geo['coordinates'][$idx]); $idx2++) //points
                                            simplifyCoordinates($geo['coordinates'][$idx][$idx2]);
                                }
                            }    
                        } 
                         
                        $json[] = $feature;
                    }                    
                
                    $json = json_encode($json);
                    header('Content-Type: application/json');
                    //header('Content-Type: application/vnd.geo+json');
                    //header('Content-disposition: attachment; filename=output.json');
                    header('Content-Length: ' . strlen($json));
                    exit($json);

                } catch (ShapeFileException $e) {
                    // Print detailed error information
error_log($e->getCode().' ('.$e->getErrorType().'): '.$e->getMessage());                    
                    //.$e->getCode().' ('.$e->getErrorType().'): '
                    $system->error_exit_api('Cannot process shp file: '.$e->getMessage(), HEURIST_ERROR);
                } catch (Exception $e) {
error_log($e->getCode().' ('.$e->getErrorType().'): '.$e->getMessage());                    
                    $system->error_exit_api('Cannot init ShapeFile library: '.$e->getMessage(), HEURIST_ERROR);
                }                
                
    }else{
        $system->error_exit_api('Record '
            .$params['recID']
            .' does not have fields where stored reference to shp or zip file',
            HEURIST_NOT_FOUND); 
    }

//
// check file existance, readability and opens the file
// returns file handle, or -1 not exist, -2 not readable -3 can't open
//
function fileOpen($file)
{
    if (!(file_exists($file) && is_file($file))) {
        return -1;
    }
    if (!is_readable($file)) {
        return -2;
    }
    $handle = fopen($file, 'rb');
    if (!$handle) {
        return -3;
    }
    return $handle;
}
    
//
// $fileinfo as fileGetFullInfo
//
function fileRetrievePath($fileinfo, $need_ext=null, $isArchive=false){
    
    if(@$fileinfo['file']){
        $fileinfo = $fileinfo['file']; //
    }
            
    $filepath = $fileinfo['fullPath'];  //concat(ulf_FilePath,ulf_FileName as fullPath
    $external_url = $fileinfo['ulf_ExternalFileReference'];
    $originalFileName = $fileinfo['ulf_OrigFileName'];
    $mimeType = $fileinfo['fxm_MimeType'];  //fxm_MimeType

    $filepath = resolveFilePath($filepath);

//DEBUG error_log($filepath.'  '.file_exists($filepath).'  '.$mimeType);  
    if(file_exists($filepath)){
        
    }else if($external_url){
        //tempnam(HEURIST_SCRATCH_DIR, 'remote_shp.zip');
        $filepath = tempnam(HEURIST_SCRATCH_DIR.'_remote_');
        saveURLasFile($external_url, $filepath);
    }    
    
    if(file_exists($filepath)){
        
        if($need_ext!==null){
            $destination = HEURIST_SCRATCH_DIR;//.$system->get_user_id().'/';
            
            $files = unzipArchiveFlat($filepath, $destination);
            
            if($files!==false){
                foreach ($files as $filename) {
                    
                        $path_parts = pathinfo($filename);
                        if(array_key_exists('extension', $path_parts))
                        {
                            $ext = strtolower($path_parts['extension']);
                            if(file_exists($filename) && $need_ext==$ext)
                            {
                                return $filename;
                            }
                        }
                }
                return null; //not found
            }else{
                return null; //broken archive
            }
        }else{
            return $filepath;
        }       
        
    }
    
}
?>