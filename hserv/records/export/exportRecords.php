<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* exportRecords.php - abstract class to export records
* 
* Controller is records_output
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/


require_once dirname(__FILE__).'/../../../vendor/autoload.php'; //for geoPHP
require_once dirname(__FILE__).'/../../utilities/geo/mapSimplify.php';
require_once dirname(__FILE__).'/../../utilities/geo/mapCoordConverter.php';
require_once dirname(__FILE__).'/../../utilities/Temporal.php';
require_once dirname(__FILE__).'/../../structure/dbsTerms.php';

/**
* 
*  setSession - switch current datbase
*  output - main method
* 
*/
abstract class ExportRecords {

    private $initialized = false;
    
    protected $system = null;
    protected $mysqli = null;
    
    protected $records;
    
    protected $rt_counts; //count by rectype ids:  rty_ID => count
    
    private $tmp_destination; //temp file 
    protected $fd;  //file handler
    protected $comma = ''; //separator for json 

    //csv of header fields
    protected $retrieve_header_fields = null;
    //array of detail fields
    protected $retrieve_detail_fields = false;
    
    //param "extended"
    //0 as is (in heurist internal format), 1 - interpretable, 2 include concept code and labels, 3 - for media viewer    
    protected $extended_mode = 0;

    protected static $defRecTypes = null;
    protected static $defDetailtypes = null;
    protected static $defTerms = null;
  
//
//
//  
public function __construct( $system ) {
    $this->setSession($system);
}  
    
//
//
//    
private function initialize()  
{
    if ($this->initialized)
        return;

    global $system;
    $this->system  = $system;
    $this->mysqli = $system->get_mysqli();
    $this->initialized = true;
}

//
// set session different that current global one (to work with different database)
//
public function setSession($system){
    $this->system  = $system;
    $this->mysqli = $system->get_mysqli();
    $this->initialized = true;
}

//
// output records as json or xml 
//
// $data - recordSearch response
//
// $params: 
//    format - json|geojson|xml|gephi|iiif
//    linkmode = direct, direct_links, none, all
//    defs  0|1  include database definitions
//    file  0|1
//    filename - export into file with given name
//    zip   0|1
//    depth 0|1|2|...all  
//
//    tlcmap 0|1  convert tlcmap records to layer
//    restapi 0|1  - json output in format {records:[]}
//
// prefs for iiif
//     version 2 or 3(default) 

// prefs for geojson, json
//    extended 0 as is (in heurist internal format), 1 - interpretable, 2 include concept code and labels, 3 - for media viewer
//
//    datatable -   datatable session id  - returns json suitable for datatable ui component
//              >1 and "q" is defined - save query request in session to result set returned, 
//              >1 and "q" not defined and "draw" is defined - takes query from session
//              1 - use "q" parameter
//    columns - array of header and detail fields to be returned
//    detail_mode  0|1|2  - 0- no details, 1 - inline, 2 - fill in "details" subarray
//
//    leaflet - 0|1 returns strict geojson and timeline data as two separate arrays, without details, only header fields rec_ID, RecTypeID and rec_Title
//        geofields  - additional filter - get geodata from specified fields only (in facetsearch format rt:dt:rt:dt )
//        suppress_linked_places - do not retriev geodata from linked places 
//        separate - do not create GeometryCollection for heurist record
//    simplify  0|1 simplify  paths with more than 1000 vertices 
//
//    limit for leaflet and gephi only
//
// @todo if output if file and zip - output datatabase,defintions and records as separate files
//      split records by 1000 entries chunks
//
protected function _outputPrepare($data, $params){

    $this->initialize();

    if (!($data && @$data['status']==HEURIST_OK)){
        return false;
    }

    $data = $data['data'];
    
    if(@$data['memory_warning']){ //memory overflow in recordSearch
        $this->records = array(); //@todo
    }else if(!(@$data['reccount']>0)){   //empty response
        $this->records = array();
    }else{
        $this->records = $data['records'];
    }
    
    $records_out = array(); //ids already out - NOT USED
    $this->rt_counts = array(); //counts of records by record type

    //NOT USED    
    $records_original_count = is_array($this->records)?count($this->records):0; //mainset of ids (result of search without linked/related)
    $error_log = array(); //NOT USED
    $error_log[] = 'Total rec count '.count($this->records);
    
    $this->tmp_destination = tempnam(HEURIST_SCRATCHSPACE_DIR, "exp");    
    //$this->fd = fopen('php://temp/maxmemory:1048576', 'w');  //less than 1MB in memory otherwise as temp file 
    $this->fd = fopen($this->tmp_destination, 'w');  //less than 1MB in memory otherwise as temp file 
    if (false === $this->fd) {
        $this->system->addError(HEURIST_SYSTEM_CONFIG, 'Failed to create temporary file in scratch folder');
        return false;
    }   
    
    if(intval(@$params['extended'])>0){
        $this->extended_mode = intval($params['extended']); //prefs for geojson, json
    }else{
        $this->extended_mode = 0;
    }
    
    //
    // in case depth>0 gather all linked and related record ids with given depth
    //    
    $max_depth = 0;
    if(@$params['depth']!=null){
        $max_depth = (@$params['depth']=='all') ?9999:intval(@$params['depth']);
    }
    
    $direction = 0;// both direct and reverse links
    $no_relationships = false;
        
    if(@$params['linkmode']){//direct, direct_links, none, all

        if($params['linkmode']=='none'){
            $max_depth = 0;
        }else if($params['linkmode']=='direct'){
            $direction = 1; //direct only
        }else if($params['linkmode']=='direct_links'){
            $direction = 1; //direct only
            $no_relationships = true;
        }
    }
    
    if($max_depth>0){
        if($params['format']=='gephi' && @$params['limit']>0){
           $limit = $params['limit'];  
        }else{
           $limit = 0; 
        }
        
        //search direct and reverse linked records for given set of records
        //it adds ids to $this->records
        recordSearchRelatedIds($this->system, $this->records, $direction, $no_relationships, 0, $max_depth, $limit);
    }
    
    $this->_outputPrepareFields($params);
    
    return true;
}

//
// Detect what header and detail fields to be retrieved
//
protected function _outputPrepareFields($params){

        $default_all_fields = true;
    
        $this->retrieve_header_fields = array();
        $this->retrieve_detail_fields = array();
        
        if(@$params['detail']){
            $params['columns'] = is_array($params['detail'])?$params['detail']:explode(',',$params['detail']);
            $default_all_fields = false;
        }
        
        if(@$params['columns'] && is_array($params['columns'])){
            
            foreach($params['columns'] as $col_name){
                
                if(is_numeric($col_name) && $col_name>0){
                    array_push($this->retrieve_detail_fields, $col_name); 
                }else if(strpos($col_name,'rec_')===0){
                    array_push($this->retrieve_header_fields, $col_name);
                }
        
            }
        }

        //header fields
        if(count($this->retrieve_header_fields)==0){
            $this->retrieve_header_fields = null; //retrieve all header fields
        }else{
            //always include rec_ID and rec_RecTypeID
            if(!in_array('rec_RecTypeID',$this->retrieve_header_fields)) array_unshift($this->retrieve_header_fields, 'rec_RecTypeID');
            if(!in_array('rec_ID',$this->retrieve_header_fields)) array_unshift($this->retrieve_header_fields, 'rec_ID');
            $this->retrieve_header_fields = implode(',', $this->retrieve_header_fields);
        }
        
        //detail fields  (by default NONE detail fields)  ????
        $this->retrieve_detail_fields = (count($this->retrieve_detail_fields)>0)?$this->retrieve_detail_fields:$default_all_fields;
    
}

//
//
//
abstract protected function _outputHeader();

abstract protected function _outputRecord($record);

abstract protected function _outputFooter();

//
//
//
public function output($data, $params){
    
    if(!$this->_outputPrepare($data, $params)){
        return false;
    }

    $this->_outputHeader();    

    //MAIN LOOP  ----------------------------------------
    foreach($this->records as $record){
    
        if(is_array($record)){
            //record data is already loaded
            $recID = $record['rec_ID'];
        }else{
            $recID = $record;
            $record = recordSearchByID($this->system, $recID, $this->retrieve_detail_fields, $this->retrieve_header_fields );
        }
        
        $rty_ID = @$record['rec_RecTypeID'];
        
        if($rty_ID>0){
            if(!@$this->rt_counts[$rty_ID]){
                $this->rt_counts[$rty_ID] = 1;
            }else{
                $this->rt_counts[$rty_ID]++;
            }
        }
        
        if(!$this->_outputRecord($record)){
            break;
        }
        
        
    }//while records
    
    //CLOSE brackets ----------------------------------------
    $this->_outputFooter();
    
    $format = @$params['format'];
    if($format==null) $format = 'xml';
    if($format=='json' || $format=='geojson' || $format=='iiif' || @$params['serial_format']=='json'){
        $mimeType = 'Content-Type: application/json';    
    }else if(@$params['serial_format']=='ntriples' || @$params['serial_format']=='turtle'){ //$format=='rdf'
        $mimeType = 'Content-Type: text/html';
    }else {
        $mimeType = 'Content-Type: text/xml';
    }

    //
    // OUTPUT
    //
    if(@$params['zip']==1 || @$params['zip']===true){
        
        $output = gzencode(file_get_contents($this->tmp_destination), 6); 
        fclose($this->fd);
        
        header('Content-Encoding: gzip');
        header($mimeType);

        fileDelete($this->tmp_destination);
        echo $output; 
        unset($output);   
        
        return true;
    }else{
        
        //$content = stream_get_contents($this->fd);
        fclose($this->fd);
        
        //
        // download output as a file
        //
        if(@$params['filename'] || @$params['metadata']){
            
            $record = null;
            $originalFileName = null;
            if(@$params['metadata']){
                list($db_meta,$rec_ID) = explode('-',$params['metadata']);
                if(!$db_meta && $rec_ID) $db_meta = $this->system->dbname(); 
                
                $record = array("rec_ID"=>$rec_ID);
                if($db_meta!=$this->system->dbname()){
                    $this->system->init($db_meta, true, false);
                    //mysql__usedatabase($this->mysqli, $db_meta);
                }
                
                if($this->system->defineConstant('DT_NAME', true)){
                    
                    //$val = mysql__select_value($this->mysqli,'select dtl_Value from recDetails where rec_ID='
                    //    .$params['metadata'].' and dtl_DetailTypeID='.DT_NAME);
                    //if($val){
                        //$originalFileName = USanitize::sanitizeFileName($val);
                    //}
                    
                    recordSearchDetails($this->system, $record, array(DT_NAME));
                    if(is_array($record['details'][DT_NAME])){
                        $originalFileName = USanitize::sanitizeFileName(array_values($record['details'][DT_NAME])[0]);
                    }
                }
                if(!$originalFileName) $originalFileName = 'Dataset_'.$record['rec_ID'];
                
            }else{
                $originalFileName = $params['filename'];
            }
            
            
            //save into specified file in scratch folder
            $file_records  = $originalFileName.'.'.($format=='gephi'?'gexf':$format);

            //archive into zip    
            $file_zip = $originalFileName.'.zip';
            $file_zip_full = tempnam(HEURIST_SCRATCHSPACE_DIR, "arc");
            $zip = new ZipArchive();
            if (!$zip->open($file_zip_full, ZIPARCHIVE::CREATE)) {
                $this->system->addError(HEURIST_SYSTEM_CONFIG, "Cannot create zip $file_zip_full");
                return false;
            }else{
                $zip->addFile($this->tmp_destination, $file_records);
            }
            
            // SAVE hml into file DOES NOT WORK - need to rewrite flathml
            if(@$params['metadata']){//save hml into scratch folder
                    $zip->addFromString($originalFileName.'.txt', 
                                    recordLinksFileContent($this->system, $record));    

            }
            $zip->close();
            //donwload
            $contentDispositionField = 'Content-Disposition: attachment; '
                . sprintf('filename="%s"; ', rawurlencode($file_zip))
                . sprintf("filename*=utf-8''%s", rawurlencode($file_zip));            
            
            header('Content-Type: application/zip');
            header($contentDispositionField);
            header('Content-Length: ' . self::get_file_size($file_zip_full));
            self::readfile_by_chunks($file_zip_full);
                                     
            // remove the zip archive and temp files
            //unlink($file_zip_full); 
            //unlink($file_metadata_full);
            fileDelete($this->tmp_destination);   
            return true;
        }else{
            //$content = file_get_contents($this->tmp_destination);

            if(@$params['restapi']){
                //header("Access-Control-Allow-Origin: *");    
                //header("Access-Control-Allow-Methods: POST, GET");
                
                // Allow from any origin
                if (isset($_SERVER['HTTP_ORIGIN'])) {
                    // Decide if the origin in $_SERVER['HTTP_ORIGIN'] is one
                    // you want to allow, and if so:
                    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
                    header('Access-Control-Allow-Credentials: true');
                    header('Access-Control-Max-Age: 5');    // default value 5 sec
                    //header('Access-Control-Max-Age: 86400');    // cache for 1 day
                /*}else if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        
                    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
                        // may also be using PUT, PATCH, HEAD etc
                        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
                    
                    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
                        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
                    exit(0);*/
                }else{
                    //2024-02-23 header("Access-Control-Allow-Origin: *");
                }                
            }
            
            header($mimeType);    
        
            if(@$params['file']==1 || @$params['file']===true){
                
                if($format=='iiif'){
                    $filename = 'manifest_'.$params['db'].'_'.date("YmdHis").'.json';
                }else{
                    $filename = 'Export_'.$params['db'].'_'.date("YmdHis").'.'.($format=='gephi'?'gexf':$format);    
                }
                
                header('Content-Disposition: attachment; filename='.$filename);
                header('Content-Length: ' . self::get_file_size($this->tmp_destination));
            }
            
            if(@$params['restapi']){
                
                if(count($this->rt_counts)==0){
                    http_response_code(404);
                }else{
                    http_response_code(200);
                }
            }
            self::readfile_by_chunks($this->tmp_destination);
            fileDelete($this->tmp_destination);
            
            return true;
//            exit($content);
        }
    }
    
}

//
//
//
protected function _getDatabaseInfo(){
    
    //add database information to be able to load definitions later
    $dbID = $this->system->get_system('sys_dbRegisteredID');
    $database_info = array('id'=>$dbID, 
                            'url'=>HEURIST_BASE_URL, 
                            'db'=>$this->system->dbname());
        
    $query = 'select rty_ID,rty_Name,'
    ."if(rty_OriginatingDBID, concat(cast(rty_OriginatingDBID as char(5)),'-',cast(rty_IDInOriginatingDB as char(5))), concat('$dbID-',cast(rty_ID as char(5)))) as rty_ConceptID"
    .' from defRecTypes where rty_ID in ('.implode(',',array_keys($this->rt_counts)).')';    
    $rectypes = mysql__select_all($this->system->get_mysqli(),$query,1);    
        
    foreach($this->rt_counts as $rtid => $cnt){
        //include record types that are in output - name, ccode and count
        $this->rt_counts[$rtid] = array('name'=>$rectypes[$rtid][0],'code'=>$rectypes[$rtid][1],'count'=>$cnt);
    }
    $database_info['rectypes'] = $this->rt_counts;
    
    return $database_info;
}

//
// read file by 10MB chunks
//
private static function readfile_by_chunks($file_path) 
{
    $file_size = self::get_file_size($file_path);
    $chunk_size = 10 * 1024 * 1024; // 10 MiB
    if ($chunk_size && $file_size > $chunk_size) {
        $handle = fopen($file_path, 'rb');
        while (!feof($handle)) {
            echo fread($handle, $chunk_size);
            @ob_flush();
            @flush();
        }
        fclose($handle);
        return $file_size;
    }
    return readfile($file_path);
}
  
  
// Fix for overflowing signed 32 bit integers,
// works for sizes up to 2^32-1 bytes (4 GiB - 1):
private static function fix_integer_overflow($size) {
    if ($size < 0) {
        $size += 2.0 * (PHP_INT_MAX + 1);
    }
    return $size;
}

private static function get_file_size($file_path, $clear_stat_cache = false) {
    if ($clear_stat_cache) {
        if (version_compare(phpversion(), '5.3.0') >= 0) { //strnatcmp(phpversion(), '5.3.0') >= 0
            clearstatcache(true, $file_path);
        } else {
            clearstatcache();
        }
    }
    if(file_exists($file_path)){
        return self::fix_integer_overflow(filesize($file_path));
    }else{
        return 0;
    }
}

} //end class
?>