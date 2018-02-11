<?php
    /**
    * Interface for CSV parse and import 
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2015 University of Sydney
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
    
    /*
    parameter
    
    content
        function parse_content - parse CSV from content parameter and returns parsed array (used in import terms)
        
    set_primary_rectype
        set main rectype for given session and returns list of dependencies (resource field->rectype)    

    records
        get records from import table    

        
    action
    1) step0
        parse_step0   save CSV form "data" parameter into temp file in scratch folder, returns filename
                     (used to post pasted csv to server side)    

    2) step1
        parse_step1  check encoding, save file in new encoding invoke parse_step2 with limit 100 to get parse preview
    
    3) step2 
        parse_step2 -  if limit>100 returns first 100 lines to preview parse (used after set of parse parameters)
                       otherwise (used after set of field roles) 
                            remove spaces, convert dates, validate identifies, find memo and multivalues
                            if id and date fields are valid invokes parse_db_save
                            otherwise returns error array and first 100 
                            
        parse_db_save - save content of file into import table, create session object and saves it to sysImportFiles table, returns session

        saveSession - saves session object into  sysImportFiles table (todo move to entity class SysImportFiles)
        getImportSession - get session from sysImportFiles  (todo move to entity class SysImportFiles)

    -------------------
    
        getMultiValues  - split multivalue field
        trim_lower_accent - trim and remove accent characters

    -------------------
    
    4) step3
        assignRecordIds -  Assign record ids to field in import table (negative if not found)
                findRecordIds - find exisiting /matching records in heurist db by provided mapping 
                
    5) step4 
        validateImport - verify mapping parameter for valid detail values (numeric,date,enum,pointers)
        
            getWrongRecords
            validateEnumerations
            validateResourcePointers
            validateNumericField
            validateDateField
        
    5) step5
        doImport - do import - add/update records in heurist database
    

    */
require_once(dirname(__FILE__)."/../System.php");
require_once (dirname(__FILE__).'/../dbaccess/dbSysImportFiles.php');
require_once (dirname(__FILE__).'/../dbaccess/db_structure.php');
require_once (dirname(__FILE__).'/../dbaccess/db_structure_tree.php');
set_time_limit(0);
    
$response = null;

$system = new System();

if(!$system->init(@$_REQUEST['db'])){
    //get error and response
    $response = $system->getError();
}else{
    
   if(!$system->is_admin()){
        $response = $system->addError(HEURIST_REQUEST_DENIED, 'Administrator permissions are required');
   }else{
       
        $action = @$_REQUEST["action"];
        $res = false;        
        
        if($action=='step0'){   
            $res = parse_step0();  //save csv data in temp file
        }else if($action=='step1'){   
            $res = parse_step1();  //encode and invoke parse_prepare with limit
        }else if($action=='step2'){
            $res = parse_step2($_REQUEST["encoded_filename"], $_REQUEST["original_filename"], 0); 
        //}else if($action=='save'){
        // 3$res = parse_db_save();
        }else if($action=='step3'){ // matching - assign record ids
        
            $res = assignRecordIds($_REQUEST); 
            
//error_log(print_r($res,true));            
        
        }else if($action=='step4'){ // validate import - check field values
        
            $res = validateImport($_REQUEST);
        
        }else if($action=='step5'){ // perform import
                
            //$res = doImport($_REQUEST);
        
        }else if(@$_REQUEST['content']){    
            $res = parse_content(); 
            
        }else if($action=='set_primary_rectype'){
            
            $res = setPrimaryRectype(@$_REQUEST['imp_ID'], @$_REQUEST['rty_ID'], @$_REQUEST['sequence']);
            
        }else if($action=='records'){    
            
            if(!@$_REQUEST['table']){
                $system->addError(HEURIST_INVALID_REQUEST, '"table" parameter is not defined');                  
            }
            
            
            if(@$_REQUEST['imp_ID']){
                $res = getRecordsFromImportTable1($_REQUEST['table'], $_REQUEST['imp_ID']);    
            }else{
                $res = getRecordsFromImportTable2($_REQUEST['table'], 
                            @$_REQUEST['id_field'],       
                            @$_REQUEST['mode'], //all, insert, update
                            @$_REQUEST['mapping'],
                            @$_REQUEST['offset'], 
                            @$_REQUEST['limit'],
                            @$_REQUEST['output']
                            );    
            }
            
            if($res && @$_REQUEST['output']=='csv'){
            
                // Open a memory "file" for read/write...
                $fp = fopen('php://temp', 'r+');
                $sz = 0; 
                foreach ($res as $idx=>$row) {
                    $sz = $sz + fputcsv($fp, $row, ',', '"');
                }
                rewind($fp);
                // read the entire line into a variable...
                $data = fread($fp, $sz+1);            
                fclose($fp);
            
                $res = $data;
            }
            
        }else{
            $system->addError(HEURIST_INVALID_REQUEST, "Action parameter is missed or wrong");                
            $res = false;
        }
        
        
        if(is_bool($res) && !$res){
                $response = $system->getError();
        }else{
                $response = array("status"=>HEURIST_OK, "data"=> $res);
        }
   }
}


if(@$_REQUEST['output']=='csv'){


    if($_REQUEST['output']=='csv'){
        header('Content-Type: text/plain;charset=UTF-8');    
        header('Pragma: public');
        header('Content-Disposition: attachment; filename="import.csv"'); //import_name
    }
    
    if($response['status']==HEURIST_OK){
        header('Content-Length: ' . strlen($response['data']));
        print $response['data'];
        
    }else{
        print $response['message'].'. ';
        print 'status: '.$response['status'];
    }
    
                
}else{

    header('Content-type: application/json;charset=UTF-8');
    //DEBUG error_log('RESP'.json_encode($response)); 
    print json_encode($response);
}
exit();
//--------------------------------------
//
//
//
function parse_step0(){
    global $system;
        $content = @$_REQUEST['data'];
        if(!$content){
            $system->addError(HEURIST_INVALID_REQUEST, "Parameter 'data' is missed");                
            return false;
        }
        
        //check if scratch folder exists
        $res = folderExists(HEURIST_SCRATCH_DIR, true);
    // -1  not exists
    // -2  not writable
    // -3  file with the same name cannot be deleted
        
        if($res<0){
            $s='';
            if($res==-1){
                $s = 'Cant find folder "scratch" in database directory';
            }else if($res==-2){
                $s = 'Folder "scratch" in database directory is not writeable';
            }else if($res==-3){
                $s = 'Cant create folder "scratch" in database directory. It is not possible to delete file with the same name';
            }
            
            $system->addError(HEURIST_ERROR, 'Cant save temporary file. '.$s);                
            return false;
        }
    
        $upload_file_name = tempnam(HEURIST_SCRATCH_DIR, 'csv');

        $res = file_put_contents($upload_file_name, trim($content));
        unset($content);
        if(!$res){
            $system->addError(HEURIST_ERROR, 'Cant save temporary file '.$upload_file_name);                
            return false;
        }
        
        $path_parts = pathinfo($upload_file_name);
        
        return array( "filename"=>$path_parts['basename'] );
}
//
// check encoding, save file in new encoding  and parse first 100 lines 
//
function parse_step1(){
    
    global $system;
    
    $upload_file_name = HEURIST_FILESTORE_DIR.'scratch/'.$_REQUEST["upload_file_name"];
    $original_filename =  $_REQUEST["upload_file_name"];
    
    $csv_encoding  = $_REQUEST["csv_encoding"];

    $handle = @fopen($upload_file_name, "r");
    if (!$handle) {
        $s = null;
        if (! file_exists($upload_file_name)) $s = ' does not exist.<br><br>'
        .'Please clear your browser cache and try again. if problem persists please report immediately to Heurist developers (info at HeuristNetwork dot org)';
        else if (! is_readable($upload_file_name)) $s = ' is not readable';
            else $s = ' could not be read';
            
        if($s){
            $system->addError(HEURIST_ERROR, 'Temporary file (uploaded csv data) '.$upload_file_name. $s);                
            return false;
        }
    }

    //fgetcsv и str_getcsv depends on server locale
    // it is possible to set it in  /etc/default/locale (Debian) or /etc/sysconfig/i18n (CentOS)  LANG="en_US.UTF-8"
    setlocale(LC_ALL, 'en_US.utf8');
    
    // read header
    $line = fgets($handle, 1000000);
    fclose($handle);
    if(!$line){
        $system->addError(HEURIST_ERROR, 'Empty header line');
        return false;
    }

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
            $system->addError(HEURIST_ERROR, 'Your file can\'t be converted to UTF-8. '
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
            $system->addError(HEURIST_ERROR, 'Your file can\'t be converted to UTF-8. '
                .'Please open it in any advanced editor and save with UTF-8 text encoding');
            return false;
        }
        
        $encoded_file_name = tempnam(HEURIST_FILESTORE_DIR.'scratch/', $original_filename);
        $res = file_put_contents($encoded_file_name, $content);
        unset($content);
        if(!$res){
            $system->addError(HEURIST_ERROR, 
                'Cant save temporary file (with UTF-8 encoded csv data) '.$encoded_file_name);
            return false;
        }
    }else{
        $encoded_file_name = $upload_file_name; 
    }
    unset($line);
  
    return parse_step2($encoded_file_name, $original_filename, 100);
}

//
// read file, remove spaces, convert dates, validate identifies, find memo and multivalues
// $encoded_filename - csv data in UTF8 - full path
// $original_filename - originally uploaded filename 

// $limit if >0 returns first 100 lines
// otherwise convert dates, validate identifies, find memo and multivalues
// if there are no errors invokes  parse_db_save - to dave csv into database
//
function parse_step2($encoded_filename, $original_filename, $limit){

    global $system;
    
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
    $keyfields  = @$_REQUEST["keyfield"];
    $datefields = @$_REQUEST["datefield"];
    $memofields = @$_REQUEST["memofield"];
    
    if(!$keyfields) $keyfields = array();
    if(!$datefields) $datefields = array();
    if(!$memofields) $memofields = array();
    
    $check_datefield = count($datefields)>0;
    $check_keyfield = count($keyfields)>0;

    $csv_mvsep     = $_REQUEST["csv_mvsep"];
    $csv_delimiter = $_REQUEST["csv_delimiter"];
    $csv_linebreak = $_REQUEST["csv_linebreak"];
    if($_REQUEST["csv_enclosure"]==1){
        $csv_enclosure = "'";    
    }else if($_REQUEST["csv_enclosure"]=='none'){
        $csv_enclosure = 'ʰ'; //rare character
    }else {
        $csv_enclosure = '"';    
    }
    
    $csv_dateformat = $_REQUEST["csv_dateformat"];

    if($csv_delimiter=="tab") {
        $csv_delimiter = "\t";
    }

    if($csv_linebreak=="auto"){
        ini_set('auto_detect_line_endings', true);
        $lb = null;
    }else if($csv_linebreak="win"){
        $lb = "\r\n";
    }else if($csv_linebreak="nix"){
        $lb = "\n";
    }else if($csv_linebreak="mac"){
        $lb = "\r";
    }
    
    $handle_wr = null;
    $handle = @fopen($encoded_filename, "r");
    if (!$handle) {
        $s = null;
        if (! file_exists($encoded_filename)) $s = ' does not exist';
        else if (! is_readable($encoded_filename)) $s = ' is not readable';
            else $s = ' could not be read';
            
        if($s){
            $system->addError(HEURIST_ERROR, 'Temporary file '.$encoded_filename. $s);                
            return false;
        }
    }
    
    //fgetcsv и str_getcsv depends on server locale
    // it is possible to set it in  /etc/default/locale (Debian) or /etc/sysconfig/i18n (CentOS)  LANG="en_US.UTF-8"
    setlocale(LC_ALL, 'en_US.utf8');    

    $len = 0;
    $header = null;

    if($limit==0){
        //get filename for prepared filename with converted dates and removed spaces
        $prepared_filename = tempnam(HEURIST_FILESTORE_DIR.'scratch/', $encoded_filename);  //HEURIST_SCRATCHSPACE_DIR
        if (!is_writable($prepared_filename)) {
            $system->addError(HEURIST_ERROR, 'Cannot save prepared csv data: '.$prepared_filename);                
            return false;
            
        }
        if (!$handle_wr = fopen($prepared_filename, 'w')) {
            $system->addError(HEURIST_ERROR, 'Cannot open file to save prepared csv data: '.$prepared_filename);                
            return false;
        }
    }

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

        if($len==0){
            $header = $fields;
            
            $len = count($fields);
            
            if($len>200){
                fclose($handle);
                if($handle_wr) fclose($handle_wr);
                
                $system->addError(HEURIST_ERROR, 
                    "Too many columns ".$len."  This probably indicates that you have selected the wrong separator type.");                
                return false;
            }            
            
            $int_fields = $fields; //assume all fields are integer
            $num_fields = $fields; //assume all fields are numeric
            $empty_fields = $fields; //assume all fields are empty
            $empty75_fields = array_pad(array(),$len,0);
            
        }else{
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
                        if(is_numeric($field) && abs($field)<99999){ //year????

                        }else{
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
                    }
                    
                    $check_keyfield_K =  ($check_keyfield && @$keyfields['field_'.$k]!=null);
                    //check integer value
                    if(@$int_fields[$k] || $check_keyfield_K){

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
                        return "Cannot write to file $prepared_filename";
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
        // returns encoded filename
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
            //we have errors - delete prepared file
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
           
            $res = parse_db_save($preproc);
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
//  save content of file into import table, create session object and saves ti to sysImportFiles table, returns session
//
function parse_db_save($preproc){
    global $system;

    $filename = $preproc["prepared_filename"];
    
    $s = null;
    if (! file_exists($filename)) $s = ' does not exist';
    else if (! is_readable($filename)) $s = ' is not readable';
        
    if($s){
        $system->addError(HEURIST_UNKNOWN_ERROR, 'Source file '.$filename. $s);                
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

    $query = $query." PRIMARY KEY (`imp_ID`)) ENGINE=InnoDB  DEFAULT CHARSET=utf8;";

    $columns = substr($columns,0,-1);
    $counts = $counts." count(*) ";

    $mysqli = $system->get_mysqli();

    if (!$mysqli->query($query)) {
        $system->addError(HEURIST_DB_ERROR, "Cannot create import table: " . $mysqli->error);                
        return false;
    }

    
    //always " if($csv_enclosure=="'") $csv_enclosure = "\\".$csv_enclosure;

    if(strpos($filename,"\\")>0){
        $filename = str_replace("\\","\\\\",$filename);
    }

    //load file into table  LOCAL
    $query = "LOAD DATA LOCAL INFILE '".$filename."' INTO TABLE ".$import_table
    ." CHARACTER SET UTF8"
    ." FIELDS TERMINATED BY ',' "  //.$csv_delimiter."' "
    ." OPTIONALLY ENCLOSED BY  '\"' " //.$csv_enclosure."' "
    ." LINES TERMINATED BY '\n'"  //.$csv_linebreak."' " 
    //." IGNORE 1 LINES
    ." (".$columns.")";


    if (!$mysqli->query($query)) {
        
        $system->addError(HEURIST_DB_ERROR, 'Unable to import data. '
.'Your MySQL system is not set up correctly for text file import. Please ask your system adminstrator to make the following changes:<br>'
.'<br><br>1. Add to  /etc/mysql/my.cnf'
.'<br>[mysqld] '
.'<br>local-infile = 1'
.'<br>[mysql] '
.'<br>local-infile = 1'
.'<br>2. Replace the driver php5-mysql by the native driver'
.'<br><br>see: http://stackoverflow.com/questions/10762239/mysql-enable-load-data-local-infile');
        //$system->addError(HEURIST_DB_ERROR, 'Unable to import data. MySQL command: "'.$query.'" returns error: '.$mysqli->error);                
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

    //calculate uniqe values
    $query = "select ".$counts." from ".$import_table;
    $res = $mysqli->query($query);
    if (!$res) {
        $system->addError(HEURIST_DB_ERROR, 'Cannot count unique values: ' . $mysqli->error);                
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

    $session = saveSession($session);
    if(count($warnings)>0){
        $session['load_warnings'] = $warnings;
    }
    return $session;
}

//
// @todo save session as entity method
//
function saveSession($imp_session){
    global $system;
    
    $mysqli = $system->get_mysqli();

    $imp_id = mysql__insertupdate($mysqli, "sysImportFiles", "sif",
        array("sif_ID"=>@$imp_session["import_id"],
            "sif_UGrpID"=>$system->get_user_id(),
            "sif_TempDataTable"=>$imp_session["import_name"],
            "sif_ProcessingInfo"=>json_encode($imp_session) ));

    if(intval($imp_id)<1){
        return "Cannot save session. SQL error:".$imp_id;
    }else{
        $imp_session["import_id"] = $imp_id;
        return $imp_session;
    }
}


//
// load session configuration
//
function getImportSession($imp_ID){
    global $system;
    
    
    if($imp_ID && is_numeric($imp_ID)){

        $res = mysql__select_row($system->get_mysqli(),
            "select sif_ProcessingInfo , sif_TempDataTable from sysImportFiles where sif_ID=".$imp_ID);

        $session = json_decode($res[0], true);
        $session["import_id"] = $imp_ID;
        $session["import_file"] = $res[1];
        if(!@$session["import_table"]){ //backward capability
            $session["import_table"] = $res[1];
        }

        return $session;
    }else{
        $system->addError(HEURIST_NOT_FOUND, 'Import session #'.$imp_ID.' not found');
        return false;
    }
    
/*    new way
    $entity = new DbSysImportFiles( $system, array('details'=>'list','sif_ID'=>$imp_ID) );
    $res = $entity->search();
    if( is_bool($res) && !$res ){
        return $res; //error - can not get import session
    }
    if(!@$res['records'][$imp_ID]){
        $system->addError(HEURIST_NOT_FOUND, 'Import session #'.$imp_ID.' not found');
        return false;
    }
    
    $session = json_decode(@$res['records'][$imp_ID][1], true);
    $session["import_id"] = $imp_ID;
    $session["import_file"] = @$res['records'][$imp_ID][0];
    if(!@$session["import_table"]){ //backward capability
            $session["import_table"] = @$res['records'][$imp_ID][0];
    }
    
    return $session;
*/    
}

//
// 1. saves new primary rectype in session
// 2. returns treeview strucuture for given rectype
//
function setPrimaryRectype($imp_ID, $rty_ID, $sequence){

     global $system;
    
     if($sequence!=null){
        //get session   
        $imp_session = getImportSession($imp_ID);
        if($imp_session==false){
                return false;
        }
        //save session with new ID
        $imp_session['primary_rectype'] = $rty_ID;
        $imp_session['sequence'] = $sequence;
        $res = saveSession($imp_session);    
        
        return 'ok';
     }else{
        //get dependent record types
        return dbs_GetRectypeStructureTree($system, $rty_ID, 5, 'resource');
     }
}
                                        
//
// parse csv from content parameter (for terms import)
//
function parse_content(){
    
    $content = $_REQUEST['content'];
    //parse
    $csv_delimiter = @$_REQUEST['csv_delimiter'];
    $csv_enclosure = @$_REQUEST['csv_enclosure'];
    $csv_linebreak = @$_REQUEST['csv_linebreak'];

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
                $temp[] = array_slice($response,$i,$csv_linebreak);
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

//error_log(print_r($content,true));
        //remove spaces
        $content = trim(preg_replace('/([\s])\1+/', ' ', $content));
        
        $lines = str_getcsv($content, $lb); 
        
        foreach($lines as &$Row) {
             $row = str_getcsv($Row, $csv_delimiter , $csv_enclosure); //parse the items in rows    
             array_push($response, $row);
        }
    }

    return $response;
}

function _findDisambResolution($keyvalue, $disamb_resolv){
    
    foreach($disamb_resolv as $idx => $disamb_pair){
        if($keyvalue==$disamb_pair['key']){
            return $disamb_pair['recid'];
        }
    }
    return null;    
}

//==================================================================== MATCHING
/**
* Perform matching - find record id in heurist db 
*
* @param mixed $mysqli
* @param mixed $imp_session
* @param mixed $params
*/
function findRecordIds($imp_session, $params){
    
    global $system;
    
    $imp_session['validation'] = array( 
        "count_update"=>0, 
        "count_insert"=>0,
        "count_update_rows"=>0,
        "count_insert_rows"=>0,
        "count_ignore_rows"=>0, //all key fields are empty - ignore
        "count_error"=>0,  //NOT USED total number of errors (may be several per row)
        "error"=>array(),
        "recs_insert"=>array(),     //full record
        "recs_update"=>array() );

    $import_table = $imp_session['import_table'];
    $multivalue_field_name = @$params['multifield']; //name of multivalue field - among mapped fields ONLY ONE can be multivalued
    if( $multivalue_field_name!=null && $multivalue_field_name>=0 ) $multivalue_field_name = 'field_'.$multivalue_field_name;

    $cnt_update_rows = 0;
    $cnt_insert_rows = 0;

    //disambiguation resolution 
    $disamb_resolv = @$params['disamb_resolv'];   //record id => $keyvalue
    if(!$disamb_resolv){ //old way
        $disamb_ids = @$params['disamb_id'];   //record ids
        $disamb_keys = @$params['disamb_key'];  //key values
        $disamb_resolv = array();
        if($disamb_keys){
            foreach($disamb_keys as $idx => $keyvalue){
                array_push($disamb_resolv, array('recid'=>$disamb_ids[$idx], 'key'=>str_replace("\'", "'", $keyvalue) ));
                //$disamb_resolv[$disamb_ids[$idx]] = str_replace("\'", "'", $keyvalue);  //rec_id => keyvalue
            }
        }
    }

    //get rectype to import
    $recordType = @$params['sa_rectype'];
    $currentSeqIndex = @$params['seq_index'];
    
    $detDefs = dbs_GetDetailTypes($system, 'all', 1 );
    
    $detDefs = $detDefs['typedefs'];
    $idx_dt_type = $detDefs['fieldNamesToIndex']['dty_Type'];
    
    $mapped_fields = array();
    $mapping = @$params['mapping'];
    $sel_fields = array();

    if(is_array($mapping))
    foreach ($mapping as $index => $field_type) {
        if($field_type=="url" || $field_type=="id" || @$detDefs[$field_type]){
                $field_name = "field_".$index;
                $mapped_fields[$field_name] = $field_type;
                $sel_fields[] = $field_name;
        }
    }    
    
    if(count($mapped_fields)==0){
        return 'No mapping defined';
    }
    
    //keep mapping   field_XXX => dty_ID
    $imp_session['validation']['mapped_fields'] = $mapped_fields;

    //already founded IDs
    $pairs = array(); //to avoid search    $keyvalue=>recID
    $records = array();
    $disambiguation = array();
    $disambiguation_lines = array();
    $tmp_idx_insert = array(); //to keep indexes
    $tmp_idx_update = array(); //to keep indexes

    $mysqli = $system->get_mysqli();
    
    //loop all records in import table and detect what is for insert and what for update
    $select_query = "SELECT imp_id, ".implode(",", $sel_fields)." FROM ".$import_table;

    $res = $mysqli->query($select_query);
    if($res){
        $ind = -1;
        while ($row = $res->fetch_assoc()){
            
                $imp_id = $row['imp_id'];
                
                $values_tobind = array();
                $values_tobind[] = ''; //first element for bind_param must be a string with field types

                //BEGIN statement constructor
                $select_query_match_from = array("Records");
                $select_query_match_where = array("rec_RecTypeID=".$recordType);
                
                $multivalue_selquery_from = null;
                $multivalue_selquery_where = null;
                $multivalue_field_value = null;

                $index = 0;
                foreach ($mapped_fields as $fieldname => $field_type) {

                    if($row[$fieldname]==null || trim($row[$fieldname])=='') {
                        continue; //ignore empty values   
                    }
                    
                        if($field_type=="url" || $field_type=="id"){  // || $field_type=="scratchpad"){
                            array_push($select_query_match_where, "rec_".$field_type."=?");
                            $values_tobind[] = trim($row[$fieldname]);
                        }else if(is_numeric($field_type)){

                            $from = '';
                            $where = "d".$index.".dtl_DetailTypeID=".$field_type." and ";
                            $dt_type = $detDefs[$field_type]['commonFields'][$idx_dt_type];

                            if( $dt_type == "enum" ||  $dt_type == "relationtype") {
                                //if fieldname is numeric - compare it with dtl_Value directly
                                $where = $where."( d".$index.".dtl_Value=t".$index.".trm_ID and "
                                ." (? in (t".$index.".trm_Label, t".$index.".trm_Code)))";
                                
                                $from = 'defTerms t'.$index.',';
                            }else{
                                $where = $where." (d".$index.".dtl_Value=?)";
                            }
                            
                            $where = 'rec_ID=d'.$index.'.dtl_RecID and '.$where;
                            $from = $from.'recDetails d'.$index;
                            
                            //we may work with the only multivalue field for matching - otherwise it is not possible to detect proper combination
                            if($multivalue_field_name==$fieldname){
                                $multivalue_selquery_from = $from;
                                $multivalue_selquery_where = $where;
                                $multivalue_field_value = trim($row[$fieldname]);
                            }else{  
                                array_push($select_query_match_where, $where);
                                array_push($select_query_match_from, $from);
                                $values_tobind[] = trim($row[$fieldname]);
                                $values_tobind[0] = $values_tobind[0].'s';
                            }
                            
                        }
                        $index++;
                }//for all fields in match array
                
                if($index==0){//all matching fields in import table are empty - skip it
                    $imp_session['validation']['count_ignore_rows']++;
                    continue;
                }
                
                
                $is_update = false;
                $is_insert = false;

                $ids = array();
                $values = array('');
                //split multivalue field
                if($multivalue_field_name!=null && $multivalue_field_value!=null){
                    $values = getMultiValues($multivalue_field_value, $imp_session['csv_enclosure'], $imp_session['csv_mvsep']);
                    if(!is_array($values) || count($values)==0){
                        $values = array(''); //at least one value
                    }
                }

                foreach($values as $idx=>$value){
                    
                    $a_tobind = $values_tobind; //values for prepared query
                    $a_from = $select_query_match_from;
                    $a_where = $select_query_match_where;
                    
                    if($multivalue_field_name!=null) $row[$multivalue_field_name] = $value;
                    
                    if(trim($value)!=''){
                            $a_tobind[0] = $a_tobind[0].'s';
                            $a_tobind[] = $value;
                 
                            $a_from[] = $multivalue_selquery_from;
                            $a_where[] = $multivalue_selquery_where;
                    }
                    
                    $fc = $a_tobind;
                    array_shift($fc); //remove ssssss
                    //ART TEMP  array_walk($fc, 'trim_lower_accent2');
                    
                    //merge all values - to create unuque key for combination of values
                    if($imp_session['csv_mvsep']=='none'){
                        $keyvalue = implode('', $fc); //$fc;
                    }else{
                        $keyvalue = implode($imp_session['csv_mvsep'], $fc);  //csv_mvsep - separator
                    }
                    
                    if($keyvalue=='') continue;
                    
                    
                    if(@$pairs[$keyvalue]){  //we already found record for this combination
                    
                        if(array_key_exists($keyvalue, $tmp_idx_insert)){
                            $imp_session['validation']['recs_insert'][$tmp_idx_insert[$keyvalue]][0] .= (",".$imp_id);
                            $is_insert = true;
                        }else if(array_key_exists($keyvalue, $tmp_idx_update)) {
                            
                            $imp_session['validation']['recs_update'][$tmp_idx_update[$keyvalue]][0] .= (','.$imp_id);
                            //$imp_session['validation']['recs_update'][$tmp_idx_update[$keyvalue]][1] .= (",".$imp_id);
                            $is_update = true;
                        }
                        array_push($ids, $pairs[$keyvalue]);
                    
                    }else{
                        
                        //query to search record ids
                        $search_query = "SELECT rec_ID, rec_Title "
                        ." FROM ".implode(",",$a_from)
                        ." WHERE ".implode(" and ",$a_where);
                  
                        $search_stmt = $mysqli->prepare($search_query);
                        //$search_stmt->bind_param('s', $field_value);
                        $search_stmt->bind_result($rec_ID, $rec_Title);
                        
                        //assign parameters for search query
                        call_user_func_array(array($search_stmt, 'bind_param'), refValues($a_tobind));
                        $search_stmt->execute();
                        $disamb = array();
                        while ($search_stmt->fetch()) {
                            //keep pair ID => key value
                            $disamb[$rec_ID] = $rec_Title; //get value from binding
                        }
                        
                        $search_stmt->close();
                        
                        if(count($disamb)>1){
                            $resolved_recid = _findDisambResolution($keyvalue, $disamb_resolv);
                        }else{
                            $resolved_recid = null;
                        }                        

                        if(count($disamb)==0  || $resolved_recid<0){ //nothing found - insert
                        
                            $new_id = $ind;
                            $ind--;
                            $rec = $row;
                            $rec[0] = $imp_id;
                            $tmp_idx_insert[$keyvalue] = count($imp_session['validation']['recs_insert']); //keep index in rec_insert
                            array_push($imp_session['validation']['recs_insert'], $rec); //group_concat(imp_id), ".implode(",",$sel_query)
                            $is_insert = true;

                        }else if(count($disamb)==1 || $resolved_recid!=null){ 
                            //array_search($keyvalue, $disamb_resolv, true)!==false){  @$disamb_resolv[addslashes($keyvalue)]){
                            //either found exact or disamiguation is resolved
                            if($resolved_recid!=null){
                                $rec_ID = $resolved_recid;    
                            }
                            
                            //find in rec_update
                        
                            $new_id = $rec_ID;
                            $rec = $row;
                            $rec[0] = $imp_id;
                            
                            //array_unshift($rec, $rec_ID); //add as first element
                            //$tmp_idx_update[$keyvalue] = count($imp_session['validation']['recs_update']); //keep index in rec_update
                            //array_push($imp_session['validation']['recs_update'], $rec); //rec_ID, group_concat(imp_id), ".implode(",",$sel_query)
                            
                            $tmp_idx_update[$keyvalue] = $rec_ID;
                            if(@$imp_session['validation']['recs_update'][$rec_ID]){
                                $imp_session['validation']['recs_update'][$rec_ID][0] .= (','.$imp_id);
                            }else{
                                $imp_session['validation']['recs_update'][$rec_ID][0] = $rec;
                            }
                            
                            $is_update = true;
                        }else{
                            $new_id= 'Found:'.count($disamb); //Disambiguation!
                            $disambiguation[$keyvalue] = $disamb;
                            $disambiguation_lines[$keyvalue] = $imp_id;
                        }
                        $pairs[$keyvalue] = $new_id;
                        array_push($ids, $new_id);
                        
                        
                    }
                    
                }//for multivalues

                if($imp_session['csv_mvsep']=='none'){
                    $records[$imp_id] = count($ids)>0?$ids[0]:'';
                }else{
                    $records[$imp_id] = implode($imp_session['csv_mvsep'], $ids);   //IDS to be added to import table
                }
            
            
            if($is_update) $cnt_update_rows++;
            if($is_insert) $cnt_insert_rows++;

        }//while import table
    }


    

    // result of work - counts of records to be inserted, updated
    $imp_session['validation']['count_update'] = count($imp_session['validation']['recs_update']);
    $imp_session['validation']['count_insert'] = count($imp_session['validation']['recs_insert']);
    $imp_session['validation']['count_update_rows'] = $cnt_update_rows;
    $imp_session['validation']['count_insert_rows'] = $cnt_insert_rows;
    $imp_session['validation']['disambiguation'] = $disambiguation;
    $imp_session['validation']['disambiguation_lines'] = $disambiguation_lines;
    $imp_session['validation']['pairs'] = $pairs;     //keyvalues => record id - count number of unique values

    //MAIN RESULT - ids to be assigned to each record in import table
    $imp_session['validation']['records'] = $records; //imp_id(line#) => list of records ids

    return $imp_session;
}



/**
* MAIN method for first step - finding exisiting /matching records in destination
* Assign record ids to field in import table
* (negative if not found)
*
* since we do match and assign in ONE STEP - first we call findRecordIds
*
* @param mixed $mysqli
* @param mixed $imp_session
* @param mixed $params
* @return mixed
*/
function assignRecordIds($params){
    
    global $system;
    
    //get rectype to import
    $rty_ID = @$params['sa_rectype'];
    $currentSeqIndex = @$params['seq_index'];
    $match_mode = @$params['match_mode'] ?$params['match_mode']: 0;   //by fields, by id, skip match
    
    if(intval($rty_ID)<1 || !(intval($currentSeqIndex)>=0)){
        $system->addError(HEURIST_INVALID_REQUEST, 'Record type not defined or wrong value');
        return false;
    }
    
    $imp_session = getImportSession($params['imp_ID']);

    if( is_bool($imp_session) && !$imp_session ){
        return false; //error - can not get import session
    }
    
    $records = null;
    $pairs = null;
    $disambiguation = array();
    
    if($match_mode == 0){  //find records by mapping
    
    //error_log(print_r($imp_session,true));
    //error_log(print_r($params,true));
    //$system->addError(HEURIST_INVALID_REQUEST, print_r($params,true));
    //return false;
        
        $imp_session = findRecordIds($imp_session, $params);
            
        if(is_array($imp_session)){
            $records = $imp_session['validation']['records']; //imp_id(line#) => list of records ids
            $pairs = $imp_session['validation']['pairs'];     //keyvalues => record id - count number of unique values
            $disambiguation = $imp_session['validation']['disambiguation'];
        }else{
            return $imp_session; //error
        }
        
        //keep counts 
        if(!@$imp_session['sequence'][$currentSeqIndex]['counts']){
            $imp_session['sequence'][$currentSeqIndex]['counts'] = array();
        }

        if(count($disambiguation)>0){
            return $imp_session; //"It is not possible to proceed because of disambiguation";
        }
        
    
    }//$match_mode==0

    $import_table = $imp_session['import_table'];

    $mysqli = $system->get_mysqli();
    
    $id_fieldname = @$params['idfield'];
    $id_field = null;
    $field_count = count($imp_session['columns']);

    if(!$id_fieldname || $id_fieldname=="null"){
        $id_fieldname = $imp_session['sequence'][$currentSeqIndex]['field'];
        //$rectype = dbs_GetRectypeByID($mysqli, $rty_ID);
        //$id_fieldname = $rectype['rty_Name'].' ID'; //not defined - create new identification field
    }
    $index = array_search($id_fieldname, $imp_session['columns']); //find it among existing columns
    if($index!==false){ //this is existing field
        $id_field  = "field_".$index;
        $imp_session['uniqcnt'][$index] = (is_array(@$pairs)&&count($pairs)>0)?count($pairs):$imp_session['reccount'];
    }

    //add new field into import table
    if(!$id_field){
        
        $is_existing_id_field = false;

        $id_field = "field_".$field_count;
        $altquery = "alter table ".$import_table." add column ".$id_field." varchar(255) ";
        if (!$mysqli->query($altquery)) {
            $system->addError(HEURIST_DB_ERROR, 'Cannot alter import session table; cannot add new index field', $mysqli->error);
            return false;
        }
        /*
        $altquery = "update ".$import_table." set ".$id_field."=-1 where imp_id>0";
        if (!$mysqli->query($altquery)) {
            $system->addError(HEURIST_DB_ERROR, 'Cannot set new index field', $mysqli->error);
            return false;
        }*/
        
        array_push($imp_session['columns'], $id_fieldname );
        array_push($imp_session['uniqcnt'], (is_array(@$pairs)&&count($pairs)>0)?count($pairs):$imp_session['reccount'] );
        if(@$params['idfield']){
            array_push($imp_session['multivals'], $field_count ); //!!!!
        }
    }else{
        $is_existing_id_field = true;
    }
    
    
    if($match_mode==2){   //skip matching - all as new

        if($is_existing_id_field){
            $updquery = "update $import_table set $id_field=NULL where imp_id>0";
            $mysqli->query($updquery);
        }
        
        $imp_session['validation'] = array( 
            "count_update"=>0, 
            "count_insert"=>$imp_session['reccount'],
            "count_update_rows"=>0,
            "count_insert_rows"=>$imp_session['reccount'],
            "count_ignore_rows"=>0,
            'disambiguation'=>array()
        );
    }
    else if(count($records)>0)
    {   
        //reset index field to '' (means no matching - record will be ignored on update/insert)
        $updquery = "update $import_table set $id_field='' where imp_id>0";
        $mysqli->query($updquery);
        
        //$records - is result of findRecordsIds
        //update ID values in import table - replace id to found
        foreach($records as $imp_id => $ids){

            if($ids){
                //update
                $updquery = "update ".$import_table." set ".$id_field."='".$ids
                ."' where imp_id = ".$imp_id;
                if(!$mysqli->query($updquery)){
                    $system->addError(HEURIST_DB_ERROR, 'Cannot update import table: set ID field', $mysqli->error.' QUERY:'.$updquery);
                    return false;
                }
            }
        }
        
        // find records to be ignored
        /* they already found in findRecordsIds
        $select_query = "SELECT count(*) FROM ".$import_table." WHERE ".$id_field."=''";
        $cnt_ignored = mysql__select_value($mysqli, $select_query);
        $imp_session['validation']['count_ignore_rows'] = $cnt_ignored;
        */
        
    
    }else if($match_mode==1){
        //find records to insert and update if matching is skipped AND WE USE current key field
        
        // find records to update
        $select_query = "SELECT count(DISTINCT ".$id_field.") FROM ".$import_table
        ." left join Records on rec_ID=".$id_field." WHERE rec_ID is not null and ".$id_field.">0";
        $cnt_update = mysql__select_value($mysqli, $select_query);
        /*if( $cnt_insert>0 ){

                $imp_session['validation']['count_update'] = $cnt;
                $imp_session['validation']['count_update_rows'] = $cnt;
                
                $imp_session['validation']['recs_update'] = array(); //do not send all records to client side
        } */

        // find records to insert
        $select_query = "SELECT count(DISTINCT ".$id_field.") FROM ".$import_table." WHERE ".$id_field."<0";
        $cnt = mysql__select_value($mysqli, $select_query);

        // id field not defined -  it records to insert as well
        $select_query = "SELECT count(*) FROM ".$import_table." WHERE ".$id_field." IS NULL"; 
        $cnt2 = mysql__select_value($mysqli, $select_query);
        $cnt_insert = $cnt + (($cnt2>0)?intval($cnt2):0);
        /*
        if( $cnt>0 ){
                $imp_session['validation']['count_insert'] = $cnt;
                $imp_session['validation']['count_insert_rows'] = $cnt;

                
                //find first 100 records to display
                //$select_query = "SELECT imp_id FROM ".$import_table
                //        .' WHERE '.$id_field.'<0 or '.$id_field.' IS NULL LIMIT 5000';
                //$imp_session['validation']['recs_insert'] = mysql__select_all($mysqli, $select_query);
                
                $imp_session['validation']['recs_insert'] = array(); //do not send all records to client side
        }
        */

        // find records to be ignored
        $select_query = "SELECT count(*) FROM ".$import_table." WHERE ".$id_field."=''";
        $cnt_ignore = mysql__select_value($mysqli, $select_query);

        
        $imp_session['validation'] = array( 
            "count_update"=>$cnt_update, 
            "count_update_rows"=>$cnt_update,
            "count_insert"=>$cnt_insert,
            "count_insert_rows"=>$cnt_insert,
            "count_ignore_rows"=>$cnt_ignore,
            'disambiguation'=>array()
        );
        
        //$imp_session['validation']['count_insert'] = $imp_session['reccount'];
    }
    
    //define field as index in session
    @$imp_session['indexes'][$id_field] = $rty_ID;

    //to keep mapping for index field
    if(!@$imp_session['sequence'][$currentSeqIndex]['mapping_keys']){
        $imp_session['sequence'][$currentSeqIndex]['mapping_keys'] = array();
    }
    
    if($match_mode!=2){
        $imp_session['sequence'][$currentSeqIndex]['mapping_keys'] = @$params['mapping'];
    }
    
    $imp_session['sequence'][$currentSeqIndex]['counts'] = array(
                    $imp_session['validation']['count_update'],      //records to be updated
                    $imp_session['validation']['count_update_rows'], //rows in source
                    $imp_session['validation']['count_insert'], 
                    $imp_session['validation']['count_insert_rows'],
                    $imp_session['validation']['count_ignore_rows']);
    

    $ret_session = $imp_session;
    unset($imp_session['validation']);  //save session without validation info
    saveSession($imp_session);
    return $ret_session;
}


/**
* Split multivalue field
*
* @param array $values
* @param mixed $csv_enclosure
*/
function getMultiValues($values, $csv_enclosure, $csv_mvsep){

    $nv = array();
    $values =  ($csv_mvsep=='none')?array($values) :explode($csv_mvsep, $values);
    if(count($values)==1){
        array_push($nv, trim($values[0]));
    }else{

        if($csv_enclosure==1){
            $csv_enclosure = "'";    
        }else if($csv_enclosure=='none'){
            $csv_enclosure = 'ʰ'; //rare character
        }else {
            $csv_enclosure = '"';    
        }

        foreach($values as $idx=>$value){
            if($value!=""){
                if(strpos($value,$csv_enclosure)===0 && strrpos($value,$csv_enclosure)===strlen($value)-1){
                    $value = substr($value,1,strlen($value)-2);
                }
                array_push($nv, $value);
            }
        }
    }
    return $nv;
}

function  trim_lower_accent($item){
    return mb_strtolower(stripAccents($item));
}


function  trim_lower_accent2(&$item, $key){
    $item = trim_lower_accent($item);
}

/**
* load records from import table
* 
* @param mixed $rec_id
* @param mixed $import_table
*/
function getRecordsFromImportTable1($import_table, $imp_ids){
    global $system;

    $mysqli = $system->get_mysqli();
    
    if(is_array($imp_ids)){
        $imp_ids = implode(',',$imp_ids);
    }
    
    $query = "SELECT * FROM $import_table WHERE imp_id IN ($imp_ids)";
    $res = mysql__select_row($mysqli, $query);
    return $res;
}

function getRecordsFromImportTable2($import_table, $id_field, $mode, $mapping, $offset, $limit=100, $output ){
    global $system;

    $mysqli = $system->get_mysqli();

    if($id_field==null || $id_field=='' || $id_field=='null' || $mode=='all'){
        $where  = '1';
        $order_field = 'imp_id';
    }else if($mode=='insert'){
        $where  = " ($id_field<0 OR $id_field IS NULL) ";
        $order_field = $id_field;
    }else{
        $where  = " ($id_field>0) ";
        $order_field = $id_field;
    }
    
    if(!($offset>0)) $offset = 0;
    if(!is_int($limit)) $limit = 100;

    if($mapping!=null && !is_array($mapping)){
        $mapping = json_decode($mapping, true);
    }
    
    if($mapping && count($mapping)>0){
        
        
        $field_idx = array_keys($mapping);
        
        $sel_fields = array($order_field);
        
        foreach($field_idx as $idx){
            if('field_'.$idx!=$id_field)
                array_push($sel_fields, 'field_'.$idx);        
        }
        if($mode=='insert' && count($sel_fields)>1){
            $order_field = $sel_fields[1];    
        }
        
        $sel_fields = 'DISTINCT '.implode(',',$sel_fields);
    }else{
        $sel_fields = '*';
    }
    
    
    $query = "SELECT $sel_fields FROM $import_table WHERE $where ORDER BY $order_field";
    if($limit>0){
        $query = $query." LIMIT $limit OFFSET $offset";
    }
    
    $res = mysql__select_all($mysqli, $query, 0, ($output=='csv'?0:30) );
    return $res;
}


// import functions =====================================

/**
* 1) Performs mapping validation (required fields, enum, pointers, numeric/date)
* 2) Counts matched (update) and new records
*
* @param mixed $mysqli
*/
/*
params:

imp_ID        - import session id
sa_rectype    - rectype ID
seq_index - currentSeqIndex

ignore_insert = 1  or 0 
recid_field   - field_X
mapping       - field index => $field_type
*/
function validateImport($params){

    global $system;
    
    //get rectype to import
    $rty_ID = @$params['sa_rectype'];
    $currentSeqIndex = @$params['seq_index'];

    if(intval($rty_ID)<1){
        $system->addError(HEURIST_INVALID_REQUEST, 'Record type not defined or wrong value');
        return false;
    }
    
    $imp_session = getImportSession($params['imp_ID']);

    if( is_bool($imp_session) && !$imp_session ){
        return false; //error - can not get import session
    }
    
    //add result of validation to session
    $imp_session['validation'] = array( 
        "count_update"=>0,
        "count_insert"=>0,       //records to be inserted
        "count_update_rows"=>0,
        "count_insert_rows"=>0,  //row that are source of insertion
        "count_ignore_rows"=>0, 
        "count_error"=>0, 
        "error"=>array(),
        "recs_insert"=>array(),     //full record
        "recs_update"=>array() );

        
    //get rectype to import
    
    $id_field = @$params['recid_field']; //record ID field is always defined explicitly
    $ignore_insert = (@$params['ignore_insert']==1); //ignore new records

    if(@$imp_session['columns'][substr($id_field,6)]==null){
        $system->addError(HEURIST_INVALID_REQUEST, 'Identification field is not defined');
        return false;
    }

    $import_table = $imp_session['import_table'];
    $cnt_update_rows = 0;
    $cnt_insert_rows = 0;

    $mapping_params = @$params['mapping'];

    $mapping = array();  // fieldtype => fieldname in import table
    $sel_query = array();
    
    if(is_array($mapping_params) && count($mapping_params)>0){
        foreach ($mapping_params as $index => $field_type) {
        
            $field_name = "field_".$index;

            $mapping[$field_type] = $field_name;
            
            $imp_session['validation']['mapped_fields'][$field_name] = $field_type;

            //all mapped fields - they will be used in validation query
            array_push($sel_query, $field_name);
        }
    }else{
        $system->addError(HEURIST_INVALID_REQUEST, 'Mapping is not defined');
        return false;
    }

    $mysqli = $system->get_mysqli();
    
        $cnt_recs_insert_nonexist_id = 0;

        // validate selected record ID field
        // in case id field is not created on match step (it is from original set of columns)
        // we have to verify that its values are valid
        if(FALSE && !@$imp_session['indexes'][$id_field]){

            //find recid with different rectype
            $query = "select imp_id, ".implode(",",$sel_query).", ".$id_field
            ." from ".$import_table
            ." left join Records on rec_ID=".$id_field
            ." where rec_RecTypeID<>".$rty_ID;
            // TODO: I'm not sure whether message below has been correctly interpreted
            $wrong_records = getWrongRecords( $query, $imp_session,
                "Your input data contain record IDs in the selected ID column for existing records which are not numeric IDs. ".
                "The import cannot proceed until this is corrected.","Incorrect record types", $id_field);
            if(is_array($wrong_records) && count($wrong_records)>0) {
                $wrong_records['validation']['mapped_fields'][$id_field] = 'id';
                $imp_session = $wrong_records;
            }else if($wrong_records===false) {
                return $wrong_records;
            }

            if(!$ignore_insert){      //WARNING - it ignores possible multivalue index field
                //find record ID that do not exist in HDB - to insert
                $query = "select count(imp_id) "
                ." from ".$import_table
                ." left join Records on rec_ID=".$id_field
                ." where ".$id_field.">0 and rec_ID is null";
                $row = mysql__select_row($mysqli, $query);
                if($row && $row[0]>0){
                    $cnt_recs_insert_nonexist_id = $row[0];
                }
            }
        }

        // find records to update
        $select_query = "SELECT count(DISTINCT ".$id_field.") FROM ".$import_table
        ." left join Records on rec_ID=".$id_field." WHERE rec_ID is not null and ".$id_field.">0";
        $cnt = mysql__select_value($mysqli, $select_query);
        if( $cnt>0 ){

                $imp_session['validation']['count_update'] = $cnt;
                $imp_session['validation']['count_update_rows'] = $cnt;
                
                /*
                //find first 100 records to preview
                $select_query = "SELECT ".$id_field.", imp_id, ".implode(",",$sel_query)
                ." FROM ".$import_table
                ." left join Records on rec_ID=".$id_field
                ." WHERE rec_ID is not null and ".$id_field.">0"
                ." ORDER BY ".$id_field." LIMIT 5000";
                $imp_session['validation']['recs_update'] = mysql__select_all($mysqli, $select_query, false);
                */
                $imp_session['validation']['recs_update'] = array();

        }

        if(!$ignore_insert){

            // find records to insert
            $select_query = "SELECT count(DISTINCT ".$id_field.") FROM ".$import_table." WHERE ".$id_field."<0"; //$id_field." is null OR ".
            $cnt1 = mysql__select_value($mysqli, $select_query);
            $select_query = "SELECT count(*) FROM ".$import_table.' WHERE '.$id_field.' IS NULL'; //$id_field." is null OR ".
            $cnt2 = mysql__select_value($mysqli, $select_query);
            if( $cnt1+$cnt2>0 ){
                    $imp_session['validation']['count_insert'] = $cnt1+$cnt2;
                    $imp_session['validation']['count_insert_rows'] = $cnt1+$cnt2;

                    /*find first 100 records to display
                    $select_query = 'SELECT imp_id, '.implode(',',$sel_query)
                            .' FROM '.$import_table.' WHERE '.$id_field.'<0 or '.$id_field.' IS NULL LIMIT 5000';
                    $imp_session['validation']['recs_insert'] = mysql__select_all($mysqli, $select_query, false);
                    */
                    $imp_session['validation']['recs_insert'] = array();
            }
        }
        $select_query = "SELECT count(*) FROM ".$import_table.' WHERE '.$id_field."=''";
        $cnt2 = mysql__select_value($mysqli, $select_query);
        $imp_session['validation']['count_ignore_rows'] = $cnt2;
        
        //additional query for non-existing IDs
        if($cnt_recs_insert_nonexist_id>0){  //NOT USED

            $imp_session['validation']['count_insert_nonexist_id'] = $cnt_recs_insert_nonexist_id;
            $imp_session['validation']['count_insert'] = $imp_session['validation']['count_insert']+$cnt_recs_insert_nonexist_id;
            $imp_session['validation']['count_insert_rows'] = $imp_session['validation']['count_insert'];

            /*
            $select_query = "SELECT imp_id, ".implode(",",$sel_query)
            ." FROM ".$import_table
            ." LEFT JOIN Records on rec_ID=".$id_field
            ." WHERE ".$id_field.">0 and rec_ID is null LIMIT 5000";
            $res = mysql__select_all($mysqli, $select_query, false);
            if($res && count($res)>0){
                if(@$imp_session['validation']['recs_insert']){
                    $imp_session['validation']['recs_insert'] = array_merge($imp_session['validation']['recs_insert'], $res);
                }else{
                    $imp_session['validation']['recs_insert'] = $res;
                }
            }*/
            $imp_session['validation']['recs_insert'] = array();
        }



    // fill array with field in import table to be validated
    $recStruc = dbs_GetRectypeStructures($system, $rty_ID, 2);
    $recStruc = $recStruc['typedefs'];
    $idx_reqtype = $recStruc['dtFieldNamesToIndex']['rst_RequirementType'];
    $idx_fieldtype = $recStruc['dtFieldNamesToIndex']['dty_Type'];

    $dt_mapping = array(); //mapping to detail type ID

    $missed = array();
    $missed_ptr = array();
    $query_reqs = array(); //fieldnames from import table
    $query_reqs_where = array(); //where clause for validation

    $query_enum = array();
    $query_enum_join = array();
    $query_enum_where = array();

    $query_res = array();
    $query_res_join = array();
    $query_res_where = array();

    $query_num = array();
    $query_num_nam = array();
    $query_num_where = array();

    $query_date = array();
    $query_date_nam = array();
    $query_date_where = array();

    $numeric_regex = "'^([+-]?[0-9]+\.*)+'"; // "'^([+-]?[0-9]+\\.?[0-9]*e?[0-9]+)|(0x[0-9A-F]+)$'";


    //loop for all fields in record type structure
    foreach ($recStruc[$rty_ID]['dtFields'] as $ft_id => $ft_vals) {

        //find fields with given field type among mappings
        $field_name = @$mapping[$ft_id];
        if(!$field_name){ //???????
            $field_name = array_search($rty_ID.".".$ft_id, $imp_session["mapping"], true); //from previous session
        }

        if(!$field_name && $ft_vals[$idx_fieldtype] == "geo"){
            //specific mapping for geo fields
            //it may be mapped to itself or mapped to two fields - lat and long

            $field_name1 = @$mapping[$ft_id."_lat"];
            $field_name2 = @$mapping[$ft_id."_long"];
            if(!$field_name1 && !$field_name2){
                $field_name1 = array_search($rty_ID.".".$ft_id."_lat", $imp_session["mapping"], true);
                $field_name2 = array_search($rty_ID.".".$ft_id."_long", $imp_session["mapping"], true);
            }

            if($ft_vals[$idx_reqtype] == "required"){
                if(!$field_name1 || !$field_name2){
                    array_push($missed, $ft_vals[0]);
                }else{
                    array_push($query_reqs, $field_name1);
                    array_push($query_reqs, $field_name2);
                    array_push($query_reqs_where, $field_name1." is null or ".$field_name1."=''");
                    array_push($query_reqs_where, $field_name2." is null or ".$field_name2."=''");
                }
            }
            if($field_name1 && $field_name2){
                array_push($query_num, $field_name1);
                array_push($query_num_where, "(NOT($field_name1 is null or $field_name1='') and NOT($field_name1 REGEXP ".$numeric_regex."))");
                array_push($query_num, $field_name2);
                array_push($query_num_where, "(NOT($field_name2 is null or $field_name2='') and NOT($field_name2 REGEXP ".$numeric_regex."))");
            }


        }else
        if($ft_vals[$idx_reqtype] == "required"){
            if(!$field_name){
                if($ft_vals[$idx_fieldtype] == "resource"){
                    array_push($missed_ptr, $ft_vals[0]);    
                }else{
                    array_push($missed, $ft_vals[0]);    
                }
            }else{
                if($ft_vals[$idx_fieldtype] == "resource"){ //|| $ft_vals[$idx_fieldtype] == "enum"){
                    $squery = "not (".$field_name.">0)";
                }else{
                    $squery = $field_name." is null or ".$field_name."=''";
                }

                array_push($query_reqs, $field_name);
                array_push($query_reqs_where, $squery);
            }
        }

        if($field_name){  //mapping exists

            $dt_mapping[$field_name] = $ft_id; //$ft_vals[$idx_fieldtype];

            if($ft_vals[$idx_fieldtype] == "enum" ||  $ft_vals[$idx_fieldtype] == "relationtype") {
                array_push($query_enum, $field_name);
                $trm1 = "trm".count($query_enum);
                array_push($query_enum_join,
                    " defTerms $trm1 on ($trm1.trm_Label=$field_name OR $trm1.trm_Code=$field_name)");
                array_push($query_enum_where, "(".$trm1.".trm_Label is null and not ($field_name is null or $field_name=''))");

            }else if($ft_vals[$idx_fieldtype] == "resource"){
                array_push($query_res, $field_name);
                $trm1 = "rec".count($query_res);
                array_push($query_res_join, " Records $trm1 on $trm1.rec_ID=$field_name ");
                array_push($query_res_where, "(".$trm1.".rec_ID is null and not ($field_name is null or $field_name=''))");

            }else if($ft_vals[$idx_fieldtype] == "float" ||  $ft_vals[$idx_fieldtype] == "integer") {

                array_push($query_num, $field_name);
                array_push($query_num_where, "(NOT($field_name is null or $field_name='') and NOT($field_name REGEXP ".$numeric_regex."))");



            }else if($ft_vals[$idx_fieldtype] == "date" ||  $ft_vals[$idx_fieldtype] == "year") {

                array_push($query_date, $field_name);
                if($ft_vals[$idx_fieldtype] == "year"){
                    array_push($query_date_where, "(concat('',$field_name * 1) != $field_name "
                        ."and not ($field_name is null or $field_name=''))");
                }else{
                    array_push($query_date_where, "(str_to_date($field_name, '%Y-%m-%d %H:%i:%s') is null "
                        ."and str_to_date($field_name, '%d/%m/%Y') is null "
                        ."and str_to_date($field_name, '%d-%m-%Y') is null "
                        ."and not ($field_name is null or $field_name=''))");
                }

            }

        }
    }

    //ignore_required

    //1. Verify that all required field are mapped  =====================================================
    if( (count($missed)>0 || count($missed_ptr)>0)  &&
        ($imp_session['validation']['count_insert']>0   // there are records to be inserted
            //  || ($params['sa_upd']==2 && $params['sa_upd2']==1)   // Delete existing if no new data supplied for record
        )){
            $error = '';
            if(count($missed)>0){
                $error = 'The following fields are required fields. You will need to map 
them to incoming data before you can import new records:<br><br>'.implode(',', $missed);    
            }
            if(count($missed_ptr)>0){
                $error = $error.'<br>Record pointer fields( '.implode(',', $missed_ptr).' ) require a record identifier value (only shown in the dropdowns in the Identifiers section). This error implies that you have not yet matched and/or imported record types that are specified in previous steps of the import workflow. Please start from the beginning. Please report the error to the Heurist developers if you think you have followed the workflow correctly.';    
            }
            
            
            $system->addError(HEURIST_ERROR, $error);
            return false;
    }

    if($id_field){ //validate only for defined records IDs
        if($ignore_insert){
            $only_for_specified_id = " (".$id_field." > 0) AND ";
        }else{
            $only_for_specified_id = " (NOT(".$id_field." is null OR ".$id_field."='')) AND ";
        }
    }else{
        $only_for_specified_id = "";
    }

    //2. In DB: Verify that all required fields have values =============================================
    $k=0;
    foreach ($query_reqs as $field){
        $query = "select imp_id, ".implode(",",$sel_query)
        ." from $import_table "
        ." where ".$only_for_specified_id."(".$query_reqs_where[$k].")"; // implode(" or ",$query_reqs_where);
        $k++;
        
        $wrong_records = getWrongRecords($query, $imp_session,
            "This field is required - a value must be supplied for every record",
            "Missing Values", $field);
        if(is_array($wrong_records)) {

            $cnt = count(@$imp_session['validation']['error']);//was
            $imp_session = $wrong_records;

            //remove from array to be inserted - wrong records with missed required field
            if(count(@$imp_session['validation']['recs_insert'])>0 ){
                $cnt2 = count(@$imp_session['validation']['error']);//now
                if($cnt2>$cnt){
                    $wrong_recs_ids = $imp_session['validation']['error'][$cnt]['recs_error_ids'];
                    if(count($wrong_recs_ids)>0){
                        $badrecs = array();
                        foreach($imp_session['validation']['recs_insert'] as $idx=>$flds){
                            if(in_array($flds[0], $wrong_recs_ids)){
                                array_push($badrecs, $idx);
                            }
                        }
                        $imp_session['validation']['recs_insert'] = array_diff_key($imp_session['validation']['recs_insert'],
                                    array_flip($badrecs) );
                        $imp_session['validation']["count_insert"] = count($imp_session['validation']['recs_insert']);                                     }
                }
            }


        }else if($wrong_records===false) {
            return $wrong_records;
        }
    }
    //3. In DB: Verify that enumeration fields have correct values =====================================
    if(!@$imp_session['csv_enclosure']){
        $imp_session['csv_enclosure'] = $params['csv_enclosure'];
    }
    if(!@$imp_session['csv_mvsep']){
        $imp_session['csv_mvsep'] = $params['csv_mvsep'];
    }


    $hwv = " have incorrect values";
    $k=0;
    foreach ($query_enum as $field){

        if(true || in_array(intval(substr($field,6)), $imp_session['multivals'])){ //this is multivalue field - perform special validation

            $query = "select imp_id, ".implode(",",$sel_query)
            ." from $import_table where ".$only_for_specified_id." 1";

            $idx = array_search($field, $sel_query)+1;

            $wrong_records = validateEnumerations($query, $imp_session, $field, $dt_mapping[$field], $idx, $recStruc, $rty_ID,
                "Term list values read must match existing terms defined for the field", "Invalid Terms");

        }else{

            $query = "select imp_id, ".implode(",",$sel_query)
            ." from $import_table left join ".$query_enum_join[$k]   //implode(" left join ", $query_enum_join)
            ." where ".$only_for_specified_id."(".$query_enum_where[$k].")";  //implode(" or ",$query_enum_where);
            
            $wrong_records = getWrongRecords($query, $imp_session,
                "Term list values read must match existing terms defined for the field",
                "Invalid Terms", $field);
        }

        $k++;

        //if($wrong_records) return $wrong_records;
        if(is_array($wrong_records)) {
            $imp_session = $wrong_records;
        }else if($wrong_records===false) {
            return $wrong_records;
        }
    }
    //4. In DB: Verify resource fields ==================================================
    $k=0;
    foreach ($query_res as $field){

        if(true || in_array(intval(substr($field,6)), $imp_session['multivals'])){ //this is multivalue field - perform special validation

            $query = "select imp_id, ".implode(",",$sel_query)
            ." from $import_table where ".$only_for_specified_id." 1";

            $idx = array_search($field, $sel_query)+1;

            $wrong_records = validateResourcePointers($query, $imp_session, $field, $dt_mapping[$field], $idx, $recStruc, $rty_ID);

        }else{
            $query = "select imp_id, ".implode(",",$sel_query)
            ." from $import_table left join ".$query_res_join[$k]  //implode(" left join ", $query_res_join)
            ." where ".$only_for_specified_id."(".$query_res_where[$k].")"; //implode(" or ",$query_res_where);
            $wrong_records = getWrongRecords($query, $imp_session,
                "Record pointer field values must reference an existing record in the database",
                "Invalid Pointers", $field);
        }

        $k++;

        //"Fields mapped as resources(pointers)".$hwv,
        if(is_array($wrong_records)) {
            $imp_session = $wrong_records;
        }else if($wrong_records===false) {
            return $wrong_records;
        }
    }

    //5. Verify numeric fields
    $k=0;
    foreach ($query_num as $field){

        if(in_array(intval(substr($field,6)), $imp_session['multivals'])){ //this is multivalue field - perform special validation

            $query = "select imp_id, ".implode(",",$sel_query)
            ." from $import_table where ".$only_for_specified_id." 1";

            $idx = array_search($field, $sel_query)+1;

            $wrong_records = validateNumericField($query, $imp_session, $field, $idx);

        }else{
            $query = "select imp_id, ".implode(",",$sel_query)
            ." from $import_table "
            ." where ".$only_for_specified_id."(".$query_num_where[$k].")";

            $wrong_records = getWrongRecords($query, $imp_session,
                "Numeric fields must be pure numbers, they cannot include alphabetic characters or punctuation",
                "Invalid Numerics", $field);
        }

        $k++;

        // "Fields mapped as numeric".$hwv,
        if(is_array($wrong_records)) {
            $imp_session = $wrong_records;
        }else if($wrong_records===false) {
            return $wrong_records;
        }
    }

    //6. Verify datetime fields
    $k=0;
    foreach ($query_date as $field){

        if(true || in_array(intval(substr($field,6)), $imp_session['multivals'])){ //this is multivalue field - perform special validation

            $query = "select imp_id, ".implode(",",$sel_query)
            ." from $import_table where ".$only_for_specified_id." 1";

            $idx = array_search($field, $sel_query)+1;

            $wrong_records = validateDateField( $query, $imp_session, $field, $idx);

        }else{
            $query = "select imp_id, ".implode(",",$sel_query)
            ." from $import_table "
            ." where ".$only_for_specified_id."(".$query_date_where[$k].")"; //implode(" or ",$query_date_where);
            $wrong_records = getWrongRecords($query, $imp_session,
                "Date values must be in dd-mm-yyyy, dd/mm/yyyy or yyyy-mm-dd formats",
                "Invalid Dates", $field);
        }

        $k++;
        //"Fields mapped as date".$hwv,
        if(is_array($wrong_records)) {
            $imp_session = $wrong_records;
        }else if($wrong_records===false) {
            return $wrong_records;
        }
    }

    //7. TODO Verify geo fields

    return $imp_session;
} //end validateImport


/**
* execute validation query and fill session array with validation results
*
* @param mixed $mysqli
* @param mixed $query
* @param mixed $message
* @param mixed $imp_session
* @param mixed $fields_checked
*/
function getWrongRecords($query, $imp_session, $message, $short_messsage, $fields_checked){

    global $system;
    $mysqli = $system->get_mysqli();
//error_log('valquery: '.$query);

    $res = $mysqli->query($query." LIMIT 5000");
    if($res){
        $wrong_records = array();
        $wrong_records_ids = array();
        while ($row = $res->fetch_row()){
            array_push($wrong_records, $row);
            array_push($wrong_records_ids, $row[0]);
        }
        $res->close();
        $cnt_error = count($wrong_records);
        if($cnt_error>0){
            $error = array();
            $error["count_error"] = $cnt_error;
            $error["recs_error"] = $wrong_records; //array_slice($wrong_records,0,1000); //imp_id, fields
            $error["recs_error_ids"] = $wrong_records_ids;
            $error["field_checked"] = $fields_checked;
            $error["err_message"] = $message;
            $error["short_message"] = $short_messsage;

            $imp_session['validation']['count_error'] = $imp_session['validation']['count_error']+$cnt_error;
            array_push($imp_session['validation']['error'], $error);

            return $imp_session;
        }

    }else{
        $system->addError(HEURIST_DB_ERROR, 'Cannot perform validation query: '.$query, $mysqli->error);
        return false;
    }
    return null; //no error records - validation passed
}


/**
* put your comment there...
*
* @param mixed $mysqli
* @param mixed $query
* @param mixed $imp_session
* @param mixed $fields_checked - name of field to be verified
* @param mixed $dt_id - mapped detail type ID
* @param mixed $field_idx - index of validation field in query result (to get value)
* @param mixed $recStruc - record type structure
* @param mixed $message - error message
* @param mixed $short_messsage
*/
function validateEnumerations($query, $imp_session, $fields_checked, $dt_id, $field_idx, $recStruc, $recordType, $message, $short_messsage){
    global $system;
    $mysqli = $system->get_mysqli();

    $dt_def = $recStruc[$recordType]['dtFields'][$dt_id];

    $idx_fieldtype = $recStruc['dtFieldNamesToIndex']['dty_Type'];
    $idx_term_tree = $recStruc['dtFieldNamesToIndex']['rst_FilteredJsonTermIDTree'];
    $idx_term_nosel = $recStruc['dtFieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];

    $dt_type = $dt_def[$idx_fieldtype];
    
    $res = $mysqli->query($query." LIMIT 5000");

    if($res){
        $wrong_records = array();
        while ($row = $res->fetch_row()){

            $is_error = false;
            $newvalue = array();
            $values = getMultiValues($row[$field_idx], $imp_session['csv_enclosure'], $imp_session['csv_mvsep']);
            foreach($values as $idx=>$r_value){
                $r_value2 = trim_lower_accent($r_value);
                if($r_value2!=""){

                    $is_termid = false;
                    if(ctype_digit($r_value2)){
                        $is_termid = isValidTerm( $dt_def[$idx_term_tree], $dt_def[$idx_term_nosel], $r_value2, $dt_id);
                    }

                    if($is_termid){
                        $term_id = $r_value;
                    }else{
                        $term_id = isValidTermLabel($dt_def[$idx_term_tree], $dt_def[$idx_term_nosel], $r_value2, $dt_id );
                    }

                    if (!$term_id)
                    {//not found
                        $is_error = true;
                        array_push($newvalue, "<font color='red'>".$r_value."AAAA</font>");
                    }else{
                        array_push($newvalue, $r_value);
                    }
                }
            }

            if($is_error){
                if($imp_session['csv_mvsep']=='none'){
                    $row[$field_idx] = count($newvalue)>0?$newvalue[0]:'';
                }else{
                    $row[$field_idx] = implode($imp_session['csv_mvsep'], $newvalue);    
                }
                
                array_push($wrong_records, $row);
            }
        }
        $res->close();
        $cnt_error = count($wrong_records);
        if($cnt_error>0){
            $error = array();
            $error["count_error"] = $cnt_error;
            $error["recs_error"] = array_slice($wrong_records,0,1000);
            $error["field_checked"] = $fields_checked;
            $error["err_message"] = $message;
            $error["short_messsage"] = $short_messsage;

            $imp_session['validation']['count_error'] = $imp_session['validation']['count_error']+$cnt_error;
            array_push($imp_session['validation']['error'], $error);

            return $imp_session;
        }

    }else{
        $system->addError(HEURIST_DB_ERROR, 'Cannot perform validation query: '.$query, $mysqli->error);
        return false;
    }
    return null;
}


/**
* put your comment there...
*
* @param mixed $mysqli
* @param mixed $query
* @param mixed $imp_session
* @param mixed $fields_checked - name of field to be verified
* @param mixed $dt_id - mapped detail type ID
* @param mixed $field_idx - index of validation field in query result (to get value)
* @param mixed $recStruc - record type structure
*/
function validateResourcePointers( $query, $imp_session, $fields_checked, $dt_id, $field_idx, $recStruc, $recordType){

    global $system;
    $mysqli = $system->get_mysqli();

    $dt_def = $recStruc[$recordType]['dtFields'][$dt_id];
    $idx_pointer_types = $recStruc['dtFieldNamesToIndex']['rst_PtrFilteredIDs'];

    $res = $mysqli->query($query." LIMIT 5000");

    if($res){
        $wrong_records = array();
        while ($row = $res->fetch_row()){

            $is_error = false;
            $newvalue = array();
            $values = getMultiValues($row[$field_idx], $imp_session['csv_enclosure'], $imp_session['csv_mvsep']);
            foreach($values as $idx=>$r_value){
                $r_value2 = trim($r_value);
                if($r_value2!=""){

                    if (!isValidPointer($dt_def[$idx_pointer_types], $r_value2, $dt_id ))
                    {//not found
                        $is_error = true;
                        array_push($newvalue, "<font color='red'>".$r_value."</font>");
                    }else{
                        array_push($newvalue, $r_value);
                    }
                }
            }

            if($is_error){
                if($imp_session['csv_mvsep']=='none'){
                    $row[$field_idx] = count($newvalue)>0?$newvalue[0]:'';
                }else{
                    $row[$field_idx] = implode($imp_session['csv_mvsep'], $newvalue);    
                }
                array_push($wrong_records, $row);
            }
        }
        $res->close();
        $cnt_error = count($wrong_records);
        if($cnt_error>0){
            $error = array();
            $error["count_error"] = $cnt_error;
            $error["recs_error"] = array_slice($wrong_records,0,1000);
            $error["field_checked"] = $fields_checked;
            $error["err_message"] = "Record pointer fields must reference an existing record of valid type in the database";
            $error["short_messsage"] = "Invalid Pointers";

            $imp_session['validation']['count_error'] = $imp_session['validation']['count_error']+$cnt_error;
            array_push($imp_session['validation']['error'], $error);

            return $imp_session;
        }

    }else{
        $system->addError(HEURIST_DB_ERROR, 'Cannot perform validation query: '.$query, $mysqli->error);
        return false;
    }
    return null;
}


/**
* put your comment there...
*
* @param mixed $mysqli
* @param mixed $query
* @param mixed $imp_session
* @param mixed $fields_checked - name of field to be verified
* @param mixed $field_idx - index of validation field in query result (to get value)
*/
function validateNumericField( $query, $imp_session, $fields_checked, $field_idx){

    global $system;
    $mysqli = $system->get_mysqli();
    
    $res = $mysqli->query($query." LIMIT 5000");

    if($res){
        $wrong_records = array();
        while ($row = $res->fetch_row()){

            $is_error = false;
            $newvalue = array();
            $values = getMultiValues($row[$field_idx], $imp_session['csv_enclosure'], $imp_session['csv_mvsep']);
            foreach($values as $idx=>$r_value){
                if($r_value!=null && trim($r_value)!=""){

                    if(!is_numeric($r_value)){
                        $is_error = true;
                        array_push($newvalue, "<font color='red'>".$r_value."</font>");
                    }else{
                        array_push($newvalue, $r_value);
                    }
                }
            }

            if($is_error){
                if($imp_session['csv_mvsep']=='none'){
                    $row[$field_idx] = count($newvalue)>0?$newvalue[0]:'';
                }else{
                    $row[$field_idx] = implode($imp_session['csv_mvsep'], $newvalue);    
                }
                array_push($wrong_records, $row);
            }
        }
        $res->close();
        $cnt_error = count($wrong_records);
        if($cnt_error>0){
            $error = array();
            $error["count_error"] = $cnt_error;
            $error["recs_error"] = array_slice($wrong_records,0,1000);
            $error["field_checked"] = $fields_checked;
            $error["err_message"] = "Numeric fields must be pure numbers, they cannot include alphabetic characters or punctuation";
            $error["short_messsage"] = "Invalid Numerics";
            $imp_session['validation']['count_error'] = $imp_session['validation']['count_error']+$cnt_error;
            array_push($imp_session['validation']['error'], $error);

            return $imp_session;
        }

    }else{
        $system->addError(HEURIST_DB_ERROR, 'Cannot perform validation query: '.$query, $mysqli->error);
        return false;
    }
    return null;
}


/**
* put your comment there...
*
* @param mixed $mysqli
* @param mixed $query
* @param mixed $imp_session
* @param mixed $fields_checked - name of field to be verified
* @param mixed $field_idx - index of validation field in query result (to get value)
*/
function validateDateField( $query, $imp_session, $fields_checked, $field_idx){

    global $system;
    $mysqli = $system->get_mysqli();
    
    $res = $mysqli->query($query." LIMIT 5000");

    if($res){
        $wrong_records = array();
        while ($row = $res->fetch_row()){

            $is_error = false;
            $newvalue = array();
            $values = getMultiValues($row[$field_idx], $imp_session['csv_enclosure'], $imp_session['csv_mvsep']);
            foreach($values as $idx=>$r_value){
                if($r_value!=null && trim($r_value)!=""){


                    if( is_numeric($r_value) && intval($r_value) ){
                        array_push($newvalue, $r_value);
                    }else{

                        $date = date_parse($r_value);

                        if ($date["error_count"] == 0 && checkdate($date["month"], $date["day"], $date["year"]))
                        {
                            $value = strtotime($r_value);
                            $value = date('Y-m-d H:i:s', $value);
                            array_push($newvalue, $value);
                        }else{
                            $is_error = true;
                            array_push($newvalue, "<font color='red'>".$r_value."</font>");
                        }

                    }
                }
            }

            if($is_error){
                if($imp_session['csv_mvsep']=='none'){
                    $row[$field_idx] = count($newvalue)>0?$newvalue[0]:'';
                }else{
                    $row[$field_idx] = implode($imp_session['csv_mvsep'], $newvalue);    
                }
                array_push($wrong_records, $row);
            }
        }
        $res->close();
        $cnt_error = count($wrong_records);
        if($cnt_error>0){
            $error = array();
            $error["count_error"] = $cnt_error;
            $error["recs_error"] = array_slice($wrong_records,0,1000);
            $error["field_checked"] = $fields_checked;
            $error["err_message"] = "Date values must be in dd-mm-yyyy, mm/dd/yyyy or yyyy-mm-dd formats";
            $error["short_messsage"] = "Invalid Dates";
            $imp_session['validation']['count_error'] = $imp_session['validation']['count_error']+$cnt_error;
            array_push($imp_session['validation']['error'], $error);

            return $imp_session;
        }

    }else{
        $system->addError(HEURIST_DB_ERROR, 'Cannot perform validation query: '.$query, $mysqli->error);
        return false;
    }
    return null;
}


?>
