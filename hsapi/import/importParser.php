<?php

/**
* importParser.php:  operations with uploaded import file (csv, kml)
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
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

/**
* two public methods
* validateImport
* performImport
* 
*/
class ImportParser {
    private function __construct() {}    
    private static $system = null;
    private static $initialized = false;
    
private static function initialize()
{
    if (self::$initialized)
        return;

    global $system;
    self::$system  = $system;
    self::$initialized = true;
}
    
/**
*  STEP 0
*  save CSV from $content into temp file in scratch folder, returns filename
*                    (used to post pasted csv to server side)    
*
*  returns  array( "filename"=> temp file name );
*/
public static function saveToTempFile($content, $extension='csv'){
    
    self::initialize();

    if(!$content){
        self::$system->addError(HEURIST_INVALID_REQUEST, "Parameter 'data' is missing");                
        return false;
    }
        
    //check if scratch folder exists
    $res = folderExistsVerbose(HEURIST_SCRATCH_DIR, true, 'scratch');
// -1  not exists
// -2  not writable
// -3  file with the same name cannot be deleted
    
    if($res!==true){
        self::$system->addError(HEURIST_ERROR, 'Cant save temporary file. '.$res);                
        return false;
    }

    $upload_file_name = tempnam(HEURIST_SCRATCH_DIR, $extension);

    $res = file_put_contents($upload_file_name, trim($content));
    unset($content);
    if(!$res){
        self::$system->addError(HEURIST_ERROR, 'Cant save temporary file '.$upload_file_name);                
        return false;
    }
    
    $path_parts = pathinfo($upload_file_name);
    //$extension = strtolower(pathinfo($upload_file_name, PATHINFO_EXTENSION));
    //, 'isKML'=>($extension=='kml') 
    
    return array( 'filename'=>$path_parts['basename'], 'fullpath'=>$upload_file_name);
}

//--------------------------------------
// STEP 1
//
// check encoding, save file in new encoding and parse first x lines for preview
//
// $params
//     csv_encoding
public static function encodeAndGetPreview($upload_file_name, $params){
    
    self::initialize();
    
    $original_filename =  $upload_file_name;
    $upload_file_name = HEURIST_FILESTORE_DIR.'scratch/'.$upload_file_name;
    
    $s = null;
    if (! file_exists($upload_file_name)) $s = ' does not exist.<br><br>'
    .'Please clear your browser cache and try again. '
    .' If problem persists please '.CONTACT_HEURIST_TEAM.' immediately';
    else if (! is_readable($upload_file_name)) $s = ' is not readable';
        
    if($s){
        self::$system->addError(HEURIST_ERROR, 'Temporary file (uploaded csv data) '.$upload_file_name. $s);                
        return false;
    }
  
    $extension = strtolower(pathinfo($upload_file_name, PATHINFO_EXTENSION));
    if($extension=='kml' || $extension=='kmz'){
        return self::parseAndValidate($upload_file_name, $original_filename, 3, $params);
    }
    
    $handle = @fopen($upload_file_name, "r");
    if (!$handle) {
        self::$system->addError(HEURIST_ERROR, 'Can\'t open temporary file (uploaded csv data) '.$upload_file_name);                          return false;
        return false;
    }

    //fgetcsv и str_getcsv depends on server locale
    // it is possible to set it in  /etc/default/locale (Debian) or /etc/sysconfig/i18n (CentOS)  LANG="en_US.UTF-8"
    setlocale(LC_ALL, 'en_US.utf8');
    
    // read header
    $line = fgets($handle, 1000000);
    fclose($handle);
    if(!$line){
        self::$system->addError(HEURIST_ERROR, 'Empty header line');
        return false;
    }
    
    $csv_encoding = @$params['csv_encoding'];
    
    //detect encoding and convert entire file to UTF8 
    // WARNING: it checks header (first line) only. It may happen that file has non UTF characters in body
    if( $csv_encoding!='UTF-8' || !mb_check_encoding( $line, 'UTF-8' ) ){

        //try to convert ONE line only - to check is it possible       
        if($csv_encoding!='UTF-8'){
             $line = mb_convert_encoding( $line, 'UTF-8', $csv_encoding);
        }else{
             $line = mb_convert_encoding( $line, 'UTF-8');
        }
        if(!$line){
            self::$system->addError(HEURIST_ERROR, 'Your file can\'t be converted to UTF-8. '
                .'Please open it in any advanced editor and save with UTF-8 text encoding');
            return false;
        }

        //convert entire file
        $content = file_get_contents($upload_file_name);
        if($csv_encoding!='UTF-8'){
            $content = mb_convert_encoding( $content, 'UTF-8', $csv_encoding);
        }else{
            $content = mb_convert_encoding( $content, 'UTF-8');
        }
        if(!$content){
            self::$system->addError(HEURIST_ERROR, 'Your file can\'t be converted to UTF-8. '
                .'Please open it in any advanced editor and save with UTF-8 text encoding');
            return false;
        }
        
        $encoded_file_name = tempnam(HEURIST_FILESTORE_DIR.'scratch/', $original_filename);
        $res = file_put_contents($encoded_file_name, $content);
        unset($content);
        if(!$res){
            self::$system->addError(HEURIST_ERROR, 
                'Cant save temporary file (with UTF-8 encoded csv data) '.$encoded_file_name);
            return false;
        }
    }else{
        $encoded_file_name = $upload_file_name; 
    }
    unset($line);
  
    return self::parseAndValidate($encoded_file_name, $original_filename, 1000, $params);
}

///--------------------------------------
// STEP 2
//
// $encoded_filename - csv data in UTF8 - full path
// $original_filename - originally uploaded filename 
// $limit if >0 returns first X lines, otherwise try to parse, validate, save it into $prepared_filename 
//                        and pass data to save to database
// $params
//    keyfield,datefield,memofield
//    csv_dateformat
//    csv_mvsep,csv_delimiter,csv_linebreak,csv_enclosure
//
// read file, remove spaces, convert dates, validate identifies/integers, find memo and multivalues
// if there are no errors and $limit=0 invokes  saveToDatabase - to save prepared csv into database
//
public static function parseAndValidate($encoded_filename, $original_filename, $limit, $params){

    self::initialize();
    
    $is_kml_data = (@$params["kmldata"]===true);
    $is_csv_data = (@$params["csvdata"]===true);
    $extension = null;
    
    if($is_kml_data){
        $extension = 'kml';
    }else if(!$is_csv_data) {
    
        $s = null;
        if(!$encoded_filename){
            $encoded_filename = '';
            $s = ' not defined';
        }else if (! file_exists($encoded_filename)) $s = ' does not exist';
        else if (! is_readable($encoded_filename)) $s = ' is not readable';
        if($s){
            self::$system->addError(HEURIST_ERROR, 'Temporary file '.$encoded_filename. $s);                
            return false;
        }
        
        $extension = strtolower(pathinfo($encoded_filename, PATHINFO_EXTENSION));
    }
    $isKML = ($extension=='kml' || $extension=='kmz');
    
    $err_colnums = array();
    $err_encoding = array();
    $err_keyfields = array();
    $err_encoding_count = 0;
    
    $int_fields = array(); // array of fields with integer values
    $num_fields = array(); // array of fields with numeric values
    $empty_fields = array(); // array of fields with NULL/empty values
    $empty75_fields = array(); // array of fields with NULL/empty values in 75% of lines
    
    $memos = array();  //multiline fields
    $multivals = array();
    $parsed_values = array();
    

    
    // fields that were marked by user as particular type
    $keyfields  = @$params["keyfield"];
    $datefields = @$params["datefield"];
    $memofields = @$params["memofield"];
    
    if(!$keyfields) $keyfields = array();
    if(!$datefields) $datefields = array();
    if(!$memofields) { $memofields = array(); }
    
    $csv_dateformat = @$params["csv_dateformat"];
    
    $check_datefield = count($datefields)>0;
    $check_keyfield = count($keyfields)>0;

    $len = 0;
    $header = null;
    $handle_wr = null;

    if($limit==0){ //if limit no defined prepare data and write into temp csv file
        //get filename for prepared filename with converted dates and removed spaces
        $prepared_filename = tempnam(HEURIST_FILESTORE_DIR.'scratch/', $encoded_filename);  //HEURIST_SCRATCH_DIR
        if (!is_writable($prepared_filename)) {
            self::$system->addError(HEURIST_ERROR, 'Cannot save prepared data: '.$prepared_filename);                
            return false;
            
        }
        if (!$handle_wr = fopen($prepared_filename, 'w')) {
            self::$system->addError(HEURIST_ERROR, 'Cannot open file to save prepared data: '.$prepared_filename);                
            return false;
        }
    }

    if($isKML){
        
        if($extension=='kmz'){
            
            $files = unzipArchiveFlat($encoded_filename, HEURIST_SCRATCH_DIR);
            
            foreach($files as $filename){
                if(strpos(strtolower($filename),'.kml')==strlen($filename)-4){
                    $encoded_filename = $filename;
                }else{
                    unlink( $filename );
                }
            }
        }
        
        if($is_kml_data){
            $kml_content =  $encoded_filename;    
            $encoded_filename = null;
        }else{
            $kml_content = file_get_contents($encoded_filename);
        }
            

        // Change tags to lower-case
        preg_match_all('%(</?[^? ><![]+)%m', $kml_content, $result, PREG_PATTERN_ORDER);
        $result = $result[0];

        $result = array_unique($result);
        sort($result);
        $result = array_reverse($result);

        foreach ($result as $search) {
          $replace = mb_strtolower($search, mb_detect_encoding($search));
          $kml_content = str_replace($search, $replace, $kml_content);
        }

        // Load into DOMDocument
        $xmlobj = new DOMDocument();
        //@
        $xmlobj->loadXML($kml_content);
        if ($xmlobj === false) {
            self::$system->addError(HEURIST_ERROR, 'Invalid KML '.($is_kml_data?'data':('file '.$encoded_filename)));                
            return false;
        }
        
        $geom_types = geoPHP::geometryList();
        $placemark_elements = $xmlobj->getElementsByTagName('placemark');
        $line_no = 0;
        if ($placemark_elements && $placemark_elements->length) {
          foreach ($placemark_elements as $placemark) {
                $properties = self::parseKMLPlacemark($placemark, $geom_types);
                if($properties==null) continue;
                if($line_no==0){
                    $fields = array_keys($properties);
                    //always add geometry, timestamp, timespan_begin, timespan_end
                    if(!@$fields['geometry']) $fields[] = 'geometry';
                    if(!@$fields['timestamp']) $fields[] = 'timestamp';
                    if(!@$fields['timespan_begin']) $fields[] = 'timespan_begin';
                    if(!@$fields['timespan_end']) $fields[] = 'timespan_end';
                    
                    $header = $fields;
                    $len = count($fields);                    
                    $int_fields = $fields; //assume all fields are integer
                    $num_fields = $fields; //assume all fields are numeric
                    $empty_fields = $fields; //assume all fields are empty
                    $empty75_fields = array_pad(array(),$len,0);
                }
                
                $k=0;
                $newfields = array();
                $line_values = array();
                foreach($header as $field_name){

                    $field = @$properties[$field_name];
                    
                    if($field==null) $field='';
                        
                    //Identify repeating value fields and flag - will not be used as key fields
                    if($field_name=='geometry'){
                        $int_fields[$k] = null;
                        $num_fields[$k] = null;
                        $empty_fields[$k] = null;
                        array_push($memos, $k);
                    }else{
                            
                        if( !in_array($k, $multivals) && strpos($field, '|')!==false ){
    //DEBUG error_log('Line '.$line_no.'  '.$field.'  '.strpos($field, '|').'  field '.$k.' is multivalue');
                            array_push($multivals, $k);
                        }
                        
                        if( !in_array($k, $memos) && (in_array($k, $memofields) || strlen($field)>250 || strpos($field, '\\r')!==false) ){
                            array_push($memos, $k);
                        }

                        //Remove any spaces at start/end of fields (including potential memos) & any redundant spaces in field that is not multi-line
                        if(in_array($k, $memos)){
                            $field = trim($field);
                        }else{
                            $field = trim(preg_replace('/([\s])\1+/', ' ', $field)); 
                        }

                        //Convert dates to standardised format.  //'field_'.
                        if($check_datefield && @$datefields[$k]!=null && $field!=""){
                            $field = self::prepareDateField($field, $csv_dateformat);
                        }
                        
                        $check_keyfield_K =  ($check_keyfield && @$keyfields['field_'.$k]!=null);
                        //check integer value
                        if(@$int_fields[$k] || $check_keyfield_K){
                            self::prepareIntegerField($field, $k, $check_keyfield_K, $err_keyfields, $int_fields);
                        }
                        if(@$num_fields[$k] && !is_numeric($field)){
                            $num_fields[$k]=null;
                        }
                        if($field==null || trim($field)==''){ //not empty
                             $empty75_fields[$k]++;
                        }else if(@$empty_fields[$k]){
                             $empty_fields[$k]=null; //field has value
                        }                    
                        
                        }//not geometry

                        //Doubling up as an escape for quote marks
                        $field = addslashes($field);
                        array_push($line_values, $field);
                        $field = '"'.$field.'"';
                        array_push($newfields, $field);
                        $k++;
                }//foreach field value
                    
                    $line_no++;

                    if ($handle_wr){
                        $line = implode(',', $newfields)."\n";

                        if (fwrite($handle_wr, $line) === FALSE) {
                            return "Cannot write to file $prepared_filename";
                        }
                        
                    }else {
                        array_push($parsed_values, $line_values);
                        if($line_no>$limit){
                            break; //for preview
                        }
                    }                
                
          }//foreach
        }//for placemarks        
        
        
        $csv_enclosure = '"';
        $csv_mvsep = '|';
        
    }
    else{   //CSV
        
        $csv_mvsep     = @$params["csv_mvsep"];
        $csv_delimiter = @$params["csv_delimiter"];
        $csv_linebreak = @$params["csv_linebreak"];
        if(@$params["csv_enclosure"]==1){
            $csv_enclosure = "'";    
        }else if(@$params["csv_enclosure"]=='none'){
            $csv_enclosure = 'ʰ'; //rare character
        }else {
            $csv_enclosure = '"';    
        }

        if($csv_delimiter=='tab') {
            $csv_delimiter = "\t";
        }else if($csv_delimiter==null) {
            $csv_delimiter = ",";
        }
        
        $lb = null;
        if($csv_linebreak=='auto'){
            ini_set('auto_detect_line_endings', true);
            $lb = null;
        }else if($csv_linebreak=='win'){
            $lb = "\r\n";
        }else if($csv_linebreak=='nix'){
            $lb = "\n";
        }else if($csv_linebreak=='mac'){
            $lb = "\r";
        }
        
        if($is_csv_data){
            $limitMBs = 10 * 1024 * 1024;
            $handle = fopen("php://temp/maxmemory:$limitMBs", 'r+');
            fputs($handle, $encoded_filename);
            rewind($handle);            
        }else{
            $handle = @fopen($encoded_filename, "r");
            if (!$handle) {
                self::$system->addError(HEURIST_ERROR, 'Temporary file '.$encoded_filename.' could not be read');                
                return false;
            }
        }
        //fgetcsv и str_getcsv depends on server locale
        // it is possible to set it in  /etc/default/locale (Debian) or /etc/sysconfig/i18n (CentOS)  LANG="en_US.UTF-8"
        setlocale(LC_ALL, 'en_US.utf8');    
        
        $line_no = 0;
        while (!feof($handle)) {

            if($csv_linebreak=="auto" || $lb==null){
                $line = fgets($handle, 1000000);      //read line and auto detect line break
            }else{
                $line = stream_get_line($handle, 1000000, $lb);
            }

            if(!mb_detect_encoding($line, 'UTF-8', true)){
                $err_encoding_count++;
                if(count($err_encoding)<100){
                    $line = mb_convert_encoding( substr($line,0,2000), 'UTF-8'); //to send back to client
                    array_push($err_encoding, array("no"=>($line_no+2), "line"=>htmlspecialchars($line)));
                }
                //if(count($err_encoding)>100) break;
            }

            $fields = str_getcsv ( $line, $csv_delimiter, $csv_enclosure );// $escape = "\\"

            if($len==0){ //first line is header with field names
                $header = $fields;
                
                $len = count($fields);
                
                if($len>200){
                    fclose($handle);
                    if($handle_wr) fclose($handle_wr);
                    
                    self::$system->addError(HEURIST_ERROR, 
                        "Too many columns ".$len."  This probably indicates that you have selected the wrong separator type.");                
                    return false;
                }            
                
                $int_fields = $fields; //assume all fields are integer
                $num_fields = $fields; //assume all fields are numeric
                $empty_fields = $fields; //assume all fields are empty
                $empty75_fields = array_pad(array(),$len,0);
                
            }
            else{
                $line_no++;

                if(trim($line)=="") continue;

                if($len!=count($fields)){        //number of columns differs from header
                    // Add error to log if wrong field count
                    array_push($err_colnums, array("cnt"=>count($fields), "no"=>$line_no, "line"=>htmlspecialchars(substr($line,0,2000))));
                    if(count($err_colnums)>100) break; //too many mistakes
                }else{
                    $k=0;
                    $newfields = array();
                    $line_values = array();
                    foreach($fields as $field){

                        //Identify repeating value fields and flag - will not be used as key fields
                        if( !in_array($k, $multivals) && strpos($field, '|')!==false ){
    //DEBUG error_log('Line '.$line_no.'  '.$field.'  '.strpos($field, '|').'  field '.$k.' is multivalue');
                            array_push($multivals, $k);
                        }
                        if( !in_array($k, $memos) && (in_array($k, $memofields) || strlen($field)>250 || strpos($field, '\\r')!==false) ){
                            array_push($memos, $k);
                        }

                        //Remove any spaces at start/end of fields (including potential memos) & any redundant spaces in field that is not multi-line
                        if(in_array($k, $memos)){
                            $field = trim($field);
                        }else{
                            $field = trim(preg_replace('/([\s])\1+/', ' ', $field)); 
                        }

                        //Convert dates to standardised format.  //'field_'.
                        if($check_datefield && @$datefields[$k]!=null && $field!=""){
                            $field = self::prepareDateField($field, $csv_dateformat);
                        }
                        
                        $check_keyfield_K =  ($check_keyfield && @$keyfields['field_'.$k]!=null);
                        //check integer value
                        if(@$int_fields[$k] || $check_keyfield_K){
                            self::prepareIntegerField($field, $k, $check_keyfield_K, $err_keyfields, $int_fields);
                        }
                        if(@$num_fields[$k] && !is_numeric($field)){
                            $num_fields[$k]=null;
                        }
                        if($field==null || trim($field)==''){ //not empty
                             $empty75_fields[$k]++;
                        }else if(@$empty_fields[$k]){
                             $empty_fields[$k]=null; //field has value
                        }                    

                        //Doubling up as an escape for quote marks
                        $field = addslashes($field);
                        array_push($line_values, $field);
                        $field = '"'.$field.'"';
                        array_push($newfields, $field);
                        $k++;
                    }

                    if ($handle_wr){
                        $line = implode(',', $newfields)."\n";

                        if (fwrite($handle_wr, $line) === FALSE) {
                            self::$system->addError(HEURIST_ERROR, "Cannot write to file $prepared_filename");                
                            return false;
                        }
                        
                    }else {
                        array_push($parsed_values, $line_values);
                        if($line_no>$limit){
                            break; //for preview
                        }
                    }
                }
            }

        }
        fclose($handle);

    }

    
    

    if($handle_wr) fclose($handle_wr);

    //???? unlink($encoded_filename);
    $empty75 = array();
    $lines75 = $line_no*0.75;
    foreach ($empty75_fields as $k=>$cnt){
        if($cnt>=$lines75) $empty75[$k] = $cnt;
    }
    /*$empty_fields = array_keys($empty_fields);
    $int_fields = array_keys($int_fields);
    $num_fields = array_keys($num_fields);*/
    

    if($limit>0){
        // returns encoded filename  and parsed values for given limit lines
        return array( 
                'encoded_filename'=>$encoded_filename,   //full path
                'original_filename'=>$original_filename, //filename only
                'step'=>1, 'col_count'=>$len, 
                'err_colnums'=>$err_colnums, 
                'err_encoding'=>$err_encoding,
                'err_encoding_count'=>$err_encoding_count, 
                
                'int_fields'=>$int_fields, 
                'empty_fields'=>$empty_fields, 
                'num_fields'=>$num_fields,
                'empty75_fields'=>$empty75, 
                
                'fields'=>$header, 'values'=>$parsed_values );    
    }else{
      
        if( count($err_colnums)>0 || count($err_encoding)>0 || count($err_keyfields)>0){
            //we have errors - delete temporary prepared file
            unlink($prepared_filename);
            
            return array( 'step'=>2, 'col_count'=>$len, 
                'err_colnums'=>$err_colnums, 
                'err_encoding'=>$err_encoding, 
                'err_keyfields'=>$err_keyfields, 
                'err_encoding_count'=>$err_encoding_count, 
                
                'int_fields'=>$int_fields, 
                'num_fields'=>$num_fields,
                'empty_fields'=>$empty_fields, 
                'empty75_fields'=>$empty75, 
                
                'memos'=>$memos, 'multivals'=>$multivals, 'fields'=>$header );    
        }else{
            //everything ok - proceed to save into db
            
            $preproc = array();
            $preproc['prepared_filename'] = $prepared_filename;
            $preproc['encoded_filename']  = $encoded_filename;
            $preproc['original_filename'] = $original_filename;  //filename only
            $preproc['fields'] = $header;
            $preproc['memos']  = $memos;
            $preproc['multivals'] = $multivals;
            $preproc['keyfields'] = $keyfields; //indexes => "field_3":"10",
            
            $preproc['csv_enclosure'] = $csv_enclosure;
            $preproc['csv_mvsep'] = $csv_mvsep;            
           
            $res = self::saveToDatabase($preproc);
            if($res!==false){
                //delete prepare
                unlink($prepared_filename);
                //delete encoded
                if(file_exists($encoded_filename)) unlink($encoded_filename);
                //delete original
                $upload_file_name = HEURIST_FILESTORE_DIR.'scratch/'.$original_filename;
                if(file_exists($upload_file_name)) unlink($upload_file_name);
            }
            return $res;
        }
    }
    
}

//
//
//
private static function prepareDateField($field, $csv_dateformat){
    
    if(is_numeric($field) && abs($field)<99999){ //year????

    }else{
        //$field = str_replace(".","-",$field);
        if($csv_dateformat==1){
            $field = str_replace("/","-",$field);
        }

        try{   
            $t2 = new DateTime($field);
            $t3 = $t2->format('Y-m-d H:i:s');
            $field = $t3;
        } catch (Exception  $e){
            //print $field.' => NOT SUPPORTED<br>';                            
        }                            
        /* strtotime works for dates after 1901 ONLY!
        $field2 = strtotime($field);
        $field3 = date('Y-m-d H:i:s', $field2);
        $field = $field3;
        */
    }
    return $field;
}

//
//
//
private static function prepareIntegerField($field, $k, $check_keyfield_K, &$err_keyfields, &$int_fields){

    $values = explode('|', $field);
    foreach($values as $value){
        if($value=='')continue;

        if(!ctype_digit(strval($value))){ //is_integer
            //not integer
            if($check_keyfield_K){

                if(is_array(@$err_keyfields[$k])){
                    $err_keyfields[$k][1]++;
                }else{
                    $err_keyfields[$k] = array(0,1);
                }
            }
            //exclude from array of fields with integer values
            if(@$int_fields[$k]) $int_fields[$k]=null;

        }else if(intval($value)<1 || intval($value)>2147483646){ //max int value in mysql

            if($check_keyfield_K){
                if(is_array(@$err_keyfields[$k])){  //out of range
                    $err_keyfields[$k][0]++;
                }else{
                    $err_keyfields[$k] = array(1,0);
                }
            }
            //exclude from array of fields with integer values
            if(@$int_fields[$k]) $int_fields[$k]=null;
        }
    }
}

//
//                                 
//
private static function parseKMLPlacemark($placemark, &$geom_types){
    
        $wkt = new WKT();  
        $properties = array();
        $textnodes = array('#text', 'lookat', 'style', 'styleurl');

        foreach ($placemark->childNodes as $child) {
          // Node names are all the same, except for MultiGeometry, which maps to GeometryCollection
          $node_name = $child->nodeName == 'multigeometry' ? 'geometrycollection' : $child->nodeName;
          
          if (array_key_exists($node_name, $geom_types))
          {
            $adapter = new KML;
            $geometry = $adapter->read($child->ownerDocument->saveXML($child));
            $geometry = $wkt->write($geometry);
            $properties['geometry'] = $geometry;
          }
          elseif ($node_name == 'extendeddata')
          {
              
            foreach ($child->childNodes as $data) {
              if ($data->nodeName != '#text') {
                if ($data->nodeName == 'data') {
                  $items = $data->getElementsByTagName('value'); //DOMNodeList 
                  if($items->length>0){
                        //$items->item(0);
                        $value = preg_replace('/\n\s+/',' ',trim($items[0]->textContent));
                  }else{
                        $value = ''; 
                  }
                  $properties[$data->getAttribute('name')] = $value;
                }
                elseif ($data->nodeName == 'schemadata')
                {
                  foreach ($data->childNodes as $schemadata) {
                    if ($schemadata->nodeName != '#text') {
                      $properties[$schemadata->getAttribute('name')] = preg_replace('/\n\s+/',' ',trim($schemadata->textContent));
                    }
                  }
                }

              }
            }
          }
          elseif ($node_name == 'timespan'){
            foreach ($child->childNodes as $timedata) {
                if ($timedata->nodeName == 'begin') {
                    $properties['timespan_begin'] = preg_replace('/\n\s+/',' ',trim($timedata->textContent));
                }else if ($timedata->nodeName == 'end') {
                    $properties['timespan_end'] = preg_replace('/\n\s+/',' ',trim($timedata->textContent));
                }
            }
          }
          elseif (!in_array($node_name, $textnodes))
          {
            $properties[$child->nodeName] = preg_replace('/\n\s+/',' ',trim($child->textContent));
          }

        }
    
        return (@$properties['geometry'])?$properties:null;
}

//
//  save content of file into import table, create session object and saves it to sysImportFiles table, returns session
//
private static function saveToDatabase($preproc){


    $filename = $preproc["prepared_filename"];
    
    $s = null;
    if (! file_exists($filename)) $s = ' does not exist';
    else if (! is_readable($filename)) $s = ' is not readable';
        
    if($s){
        self::$system->addError(HEURIST_UNKNOWN_ERROR, 'Source file '.$filename. $s);                
        return false;
    }
    
    
    $import_table = "import".date("YmdHis");

    //create temporary table import_datetime
    $query = "CREATE TABLE `".$import_table."` (`imp_ID` int(10) unsigned NOT NULL AUTO_INCREMENT, ";
    $columns = "";
    $counts = "";
    $mapping = array();
    $len = count($preproc['fields']);
    for ($i = 0; $i < $len; $i++) {
        $query = $query."`field_".$i."` ".(in_array($i, $preproc['memos'])?" mediumtext, ":" varchar(300), " ) ;
        $columns = $columns."field_".$i.",";
        $counts = $counts."count(distinct field_".$i."),";
        //array_push($mapping,0);
    }

    $query = $query." PRIMARY KEY (`imp_ID`)) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb3;";  //was utf8 this is alias utf8mb3

    $columns = substr($columns,0,-1);
    $counts = $counts." count(*) ";

    $mysqli = self::$system->get_mysqli();

    if (!$mysqli->query($query)) {
        self::$system->addError(HEURIST_DB_ERROR, "Cannot create import table", $mysqli->error);                
        return false;
    }

    
    //always " if($csv_enclosure=="'") $csv_enclosure = "\\".$csv_enclosure;

    if(strpos($filename,"\\")>0){
        $filename = str_replace("\\","\\\\",$filename);
    }
    if(strpos($filename,"'")>0){
        $filename = str_replace("'","\\'",$filename);
    }
    ;
    $mysqli->query('SET GLOBAL local_infile = true');
    //load file into table  LOCAL
    $query = "LOAD DATA LOCAL INFILE '".$filename."' INTO TABLE ".$import_table
    ." CHARACTER SET utf8mb3"    //was UTF8 this is alias for utf8mb3
    ." FIELDS TERMINATED BY ',' "  //.$csv_delimiter."' "
    ." OPTIONALLY ENCLOSED BY  '\"' " //.$csv_enclosure."' "
    ." LINES TERMINATED BY '\n'"  //.$csv_linebreak."' " 
    //." IGNORE 1 LINES
    ." (".$columns.")";


    if (!$mysqli->query($query)) {
        
        self::$system->addError(HEURIST_DB_ERROR, 'Unable to import data. '
.'Your MySQL system is not set up correctly for text file import. Please ask your system adminstrator to make the following changes:<br>'
.'<br><br>1. Add to  /etc/mysql/my.cnf'
.'<br>[mysqld] '
.'<br>local-infile = 1'
.'<br>[mysql] '
.'<br>local-infile = 1'
.'<br>2. Replace the driver php5-mysql by the native driver'
.'<br><br>see: http://stackoverflow.com/questions/10762239/mysql-enable-load-data-local-infile', $mysqli->error);
        //self::$system->addError(HEURIST_DB_ERROR, 'Unable to import data. MySQL command: "'.$query.'" returns error: '.$mysqli->error);                
        return false;
    }

    $warnings = array();
    if ($info = $mysqli->info) {
        if ($mysqli->warning_count) {
            array_push($warnings, $info);
            $e = $mysqli->get_warnings();
            do {
                array_push($warnings, $e->message); //$e->errno.": ".
            } while ($e->next());
        }
        /*if(strpos("$info", "Warnings: 0")===false){
        $mysqli->query("SHOW WARNINGS");
        }*/
    }

    //calculate unique values
    $query = "select ".$counts." from ".$import_table;
    $res = $mysqli->query($query);
    if (!$res) {
        self::$system->addError(HEURIST_DB_ERROR, 'Cannot count unique values', $mysqli->error);                
        return false;
    }

    $uniqcnt = $res->fetch_row();
    $reccount = array_pop ( $uniqcnt );

    //add record to import_log
    $session = array("reccount"=>$reccount,
        "import_table"=>$import_table,
        "import_name"=>((substr($preproc['original_filename'],-4)=='.tmp'?'csv':$preproc['original_filename']).' '.date('Y-m-d H:i:s')),
        "columns"=>$preproc['fields'],   //names of columns in file header
        "memos"=>$preproc['memos'],
        "multivals"=>$preproc['multivals'],  //columns that have multivalue separator
        "csv_enclosure"=>$preproc['csv_enclosure'],
        "csv_mvsep"=>$preproc['csv_mvsep'],
        "uniqcnt"=>$uniqcnt,   //count of uniq values per column
        "indexes"=>$preproc['keyfields'] );  //names of columns in import table that contains record_ID
        
    //new parameters to replace mapping and indexes_keyfields    
    $session['primary_rectype'] =  0; //main rectype    

    $session = ImportSession::save($session);
    if(!is_array($session)){
        self::$system->addError(HEURIST_DB_ERROR, 'Cannot save import session', $session);
        return false;
    }
    
    if(count($warnings)>0){
        $session['load_warnings'] = $warnings;
    }
    return $session;
}

//
// parse csv from content parameter (for terms import)
//
public static function simpleCsvParser($params){

    $content = $params['content'];
    //parse
    $csv_delimiter = @$params['csv_delimiter'];
    $csv_enclosure = @$params['csv_enclosure'];
    $csv_linebreak = @$params['csv_linebreak'];

    if(!$csv_delimiter) $csv_delimiter = ',';
    else if($csv_delimiter=='tab') $csv_delimiter="\t";
    else if($csv_delimiter=='space') $csv_delimiter=" ";
    
    if(!$csv_linebreak) $csv_linebreak = "auto";

    $csv_enclosure = ($csv_enclosure==1)?"'":'"';

    $response = array();

    if(intval($csv_linebreak)>0){  //no breaks - group by
            $group_by = $csv_linebreak;
            $response = str_getcsv($content, $csv_delimiter, $csv_enclosure); 
        
            $temp = array();
            $i = 0;
            while($i<count($response)) {
                $temp[] = array_slice($response, $i, $csv_linebreak);
                $i = $i + $csv_linebreak;
            }
            $response = $temp;
        
    }else{
        
        if($csv_linebreak=="auto"){
            //ini_set('auto_detect_line_endings', true);
            $lb = "\n";
        }else if($csv_linebreak="win"){
            $lb = "\r\n";
        }else if($csv_linebreak="nix"){
            $lb = "\n";
        }else if($csv_linebreak="mac"){
            $lb = "\r";
        }

        //remove spaces
        $content = trim(preg_replace('/([\s])\1+/', ' ', $content));
        
        $lines = str_getcsv($content, $lb, '¢'); 
        
        foreach($lines as &$Row) {
             $row = str_getcsv($Row, $csv_delimiter , $csv_enclosure); //parse the items in rows    
             array_push($response, $row);
        }
    }

    return $response;
    
}

//
// Converts data prepared by parseAndValidate to record format recordSearchByID/recordSearchDetails
// $parsed - parseAndValidate output 
// $mapping - $dtyID=> column name in $parsed['fields']
//
public static function convertParsedToRecords($parsed, $mapping, $rec_RecTypeID=null){
    
    $fields = $parsed['fields'];
    $values = $parsed['values'];
    if($rec_RecTypeID==null){
      $rec_RecTypeID = 12;//RT_PLACE;   
    } 
    $records = array();
    
    foreach($values as $idx=>$entry){
        
        $record = array('rec_ID'=>'C'.$idx,'rec_RecTypeID'=>$rec_RecTypeID, 'rec_Title'=>'');    
        
        $detail = array();
        $lat  = null;
        $long = null;
        foreach($mapping as $dty_ID=>$column){
            
            $dty_ID = array_search($column, $mapping);
            //$dty_ID = @$mapping[$column];
            if($dty_ID>0 || $dty_ID=='longitude' || $dty_ID=='latitude'){
                $col_index = array_search($column, $fields);
                if($col_index>=0){
                    $detailValue = $entry[$col_index]; 
                    if($dty_ID==DT_GEO_OBJECT){
                            $detailValue = array(
                                "geo" => array(
                                    "type" => '',
                                    "wkt" => $detailValue
                                )
                            );
                    }else if($dty_ID==DT_NAME){
                        $record['rec_Title'] = $detailValue;    
                    }else if($dty_ID==DT_EXTENDED_DESCRIPTION){
                        $record['Description'] = $detailValue;    
                    }else if($dty_ID=='longitude'){
                        $long = $detailValue;
                    }else if($dty_ID=='latitude'){
                        $lat = $detailValue;
                    }
                    
                    if($dty_ID>0){
                        $detail[$dty_ID][0] = $detailValue;
                    }
                }
            }
        }
        
        if(is_numeric($lat) && is_numeric($long)){
            $detail[DT_GEO_OBJECT][0] = array(
                                "geo" => array(
                                    "type" => '',
                                    "wkt" => $value = "POINT(".$long." ".$lat.")"
                                )
                            );
        }
        
        $record['details'] = $detail;
        $records[$idx] = $record;
    }
    
    return $records;    
}


} //end class
?>
