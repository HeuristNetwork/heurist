<?php
    /**
    *  CSV parser for content from client side
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
require_once(dirname(__FILE__)."/../System.php");
$response = null;

$system = new System();

if(!$system->init(@$_REQUEST['db'])){
    //get error and response
    $response = $system->getError();
}else{
    
   if(!$system->is_admin()){
        $response = $system->addError(HEURIST_REQUEST_DENIED);
   }else{
       
        $action = @$_REQUEST["action"];
        
        if($action=='step0'){   
            $res = parse_step0();  //save csv data in temp file
        }else if($action=='step1'){   
            $res = parse_step1();  //encode and invoke parse_prepare with limit
        }else if($action=='step2'){
            $res = parse_step2($_REQUEST["filename"], 0); 
        //}else if($action=='save'){
        // 3$res = parse_db_save();
        }else if(@$_REQUEST['content']){    
            $res = parse_content(); 
        }else{
            $response = $system->addError(HEURIST_INVALID_REQUEST, " wrong parameters");                
        }
        
        
        if(is_bool($res) && !$res){
                $response = $system->getError();
        }else{
                $response = array("status"=>HEURIST_OK, "data"=> $res);
        }
   }
}

header('Content-type: application/json'); //'text/javascript');
print json_encode($response);
exit();
//--------------------------------------

// parse_step0 - save content into file
// parse_step1 - check encoding, save file in new encoding invoke parse_prepare with limit
// parse_step2 - read file, remove spaces, convert dates, validate identifies, find memo and multivalues, save file or return preview array
// parse_db_save - save file into table

function parse_step0(){
    global $system;
        $content = @$_REQUEST['data'];
        if(!$content){
            $system->addError(HEURIST_INVALID_REQUEST, "Parameter 'data' is missed");                
            return false;
        }
    
        $filename = tempnam(HEURIST_FILESTORE_DIR.'scratch/', 'csv');

        $res = file_put_contents($filename, trim($content));
        unset($content);
        if(!$res){
            $system->addError(HEURIST_UNKNOWN_ERROR, 'Cant save temporary file '.$filename);                
            return false;
        }
        
        $path_parts = pathinfo($filename);
        
        return array( "filename"=>$path_parts['basename'] );
}
//
// check encoding, save file in new encoding 
//
function parse_step1(){
    
    global $system;
    
    $filename = HEURIST_FILESTORE_DIR.'scratch/'.$_REQUEST["upload_file_name"];
    $original_filename =  $_REQUEST["upload_file_name"];
    
    $csv_encoding  = $_REQUEST["csv_encoding"];

    $handle = @fopen($filename, "r");
    if (!$handle) {
        $s = null;
        if (! file_exists($filename)) $s = ' does not exist';
        else if (! is_readable($filename)) $s = ' is not readable';
            else $s = ' could not be read';
            
        if($s){
            $system->addError(HEURIST_UNKNOWN_ERROR, 'Temporary file '.$filename. $s);                
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
        return "Empty header line";
    }

    //detect encoding and convert entire file to UTF8
    if( $csv_encoding!='UTF-8' || !mb_check_encoding( $line, 'UTF-8' ) ){

        $line = mb_convert_encoding( $line, 'UTF-8', $csv_encoding);
        if(!$line){
            return 'Your file can\'t be converted to UTF-8. '
            .'Please open it in any advanced editor and save with UTF-8 text encoding';
        }

        $content = file_get_contents($filename);
        $content = mb_convert_encoding( $content, 'UTF-8' );
        if(!$content){
            return 'Your file can\'t be converted to UTF-8. '
            .'Please open it in any advanced editor and save with UTF-8 text encoding';
        }
        
        $temp_file = tempnam(HEURIST_FILESTORE_DIR.'scratch/', $filename);
        $res = file_put_contents($temp_file, $content);
        unset($content);
        if(!$res){
            return 'Cant save temporary file '.$filename;
        }
    }else{
        $temp_file = $filename; 
    }
    unset($line);
/*    
    //get fields
    $fields = str_getcsv ( $line, $csv_delimiter, $csv_enclosure );// $escape = "\\"
    $len = count($fields);
    
    if($len>200){
        return "Too many columns ".$len."  This probably indicates that you have selected the wrong separator type.";
    }
    
    if($len==1){
        return array("warning"=>"You appear to have only one value per line. This probably indicates that "
                    ."you have selected the wrong separator type.",
                    "filename"=>$temp_file, "original"=>$original, "fields"=>$fields );
    }else{
        //success
        return array("warning"=>"Please verify the list of columns",
                    "filename"=>$temp_file, "original"=>$original, "fields"=>$fields );
    }
 */   
    return parse_step2($temp_file, 100);
}

//
// read file, remove spaces, convert dates, validate identifies, find memo and multivalues
//
function parse_step2($filename, $limit){

    global $system;
    
    $err_colnums = array();
    $err_encoding = array();
    $err_keyfields = array();
    
    $memos = array();  //multiline fields
    $multivals = array();
    $values = array();
    $keyfields  = @$_REQUEST["keyfield"];
    $datefields = @$_REQUEST["datefield"];
    $memofields = @$_REQUEST["memofield"];
    
    if(!$keyfields) $keyfields = array();
    if(!$datefields) $datefields = array();
    if(!$memofields) $memofields = array();

    $csv_mvsep     = $_REQUEST["csv_mvsep"];
    $csv_delimiter = $_REQUEST["csv_delimiter"];
    $csv_linebreak = $_REQUEST["csv_linebreak"];
    $csv_enclosure = ($_REQUEST["csv_enclosure"]==1)?"'":'"';
    $csv_dateformat = $_REQUEST["csv_dateformat"];

    if($csv_delimiter=="tab") {
        $csv_delimiter = "\t";
    }

    if($csv_linebreak=="auto"){
        ini_set('auto_detect_line_endings', true);
        $lb = null;
    }else{
        $lb = str_replace("\\n", "\n", $csv_linebreak);
        $lb = str_replace("\\r", "\r", $lb);
        $lb = str_replace("\\t", "\t", $lb);
    }
    
    $handle_wr = null;
    $handle = @fopen($filename, "r");
    if (!$handle) {
        $s = null;
        if (! file_exists($filename)) $s = ' does not exist';
        else if (! is_readable($filename)) $s = ' is not readable';
            else $s = ' could not be read';
            
        if($s){
            $system->addError(HEURIST_UNKNOWN_ERROR, 'Temporary file '.$filename. $s);                
            return false;
        }
    }
    
    //fgetcsv и str_getcsv depends on server locale
    // it is possible to set it in  /etc/default/locale (Debian) or /etc/sysconfig/i18n (CentOS)  LANG="en_US.UTF-8"
    setlocale(LC_ALL, 'en_US.utf8');    

    $len = 0;
    $header = null;

    if($limit==0){
        $prepared_filename = tempnam(HEURIST_FILESTORE_DIR.'scratch/', $filename);  //HEURIST_SCRATCHSPACE_DIR
        if (!is_writable($prepared_filename)) {
            return "cannot save preprocessed file $prepared_filename";
        }
        if (!$handle_wr = fopen($prepared_filename, 'w')) {
            return "Cannot open file ($prepared_filename)";
        }
    }

    $line_no = 0;
    while (!feof($handle)) {

        if($csv_linebreak=="auto" || $lb==null){
            $line = fgets($handle, 1000000);      //read line and auto detect line break
        }else{
            $line = stream_get_line($handle, 1000000, $lb);
        }

        if(count($err_encoding)<100 && !mb_detect_encoding($line, 'UTF-8', true)){
            array_push($err_encoding, array("no"=>$line_no, "line"=>htmlspecialchars(substr($line,0,2000))));
            //if(count($err_encoding)>100) break;
        }

        $fields = str_getcsv ( $line, $csv_delimiter, $csv_enclosure );// $escape = "\\"

        if($len==0){
            $header = $fields;
            $len = count($fields);
            
            if($len>200){
                fclose($handle);
                if($handle_wr) fclose($handle_wr);
                return "Too many columns ".$len."  This probably indicates that you have selected the wrong separator type.";
            }            
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

                    //Convert dates to standardised format.
                    if(in_array($k, $datefields) && $field!=""){
                        if(is_numeric($field) && abs($field)<99999){ //year????

                        }else{
                            if($csv_dateformat==1){
                                $field = str_replace("/","-",$field);
                            }
                            $field = strtotime($field);
                            $field = date('Y-m-d H:i:s', $field);
                        }
                    }else if(in_array($k, $keyfields)){
                        
                         if(!ctype_digit(strval($field))){ //is_integer
                            //not integer
                            if(is_array(@$err_keyfields[$k])){
                                $err_keyfields[$k][1]++;
                            }else{
                                $err_keyfields[$k] = array(0,1);
                            }
                        }else if(intval($field)<1 || intval($field)>2147483648){ //max int value in mysql
                                if(is_array(@$err_keyfields[$k])){  //out of range
                                    $err_keyfields[$k][0]++;
                                }else{
                                    $err_keyfields[$k] = array(1,0);
                                }
                        }
                        
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
                        return "Cannot write to file ($prepared_filename)";
                    }
                    
                }else {
                    array_push($values, $line_values);
                    if($line_no>$limit){
                        break; //for preview
                    }
                }
            }
        }

    }
    fclose($handle);
    if($handle_wr) fclose($handle_wr);

    //???? unlink($filename);

    if($limit>0){
        // returns encoded fileame
        return array( "filename"=>$filename, "step"=>1, "col_count"=>$len, 
                "err_colnums"=>$err_colnums, 
                "err_encoding"=>$err_encoding, 
                "fields"=>$header, "values"=>$values );    
    }else{
      
        if( count($err_colnums)>0 || count($err_encoding)>0 || count($err_keyfields)>0){
            //retunrs prepared filename
            unlink($prepared_filename);
            
            return array( "step"=>2, "col_count"=>$len, 
                "err_colnums"=>$err_colnums, 
                "err_encoding"=>$err_encoding, 
                "err_keyfields"=>$err_keyfields, 
                "memos"=>$memos, "multivals"=>$multivals, "fields"=>$header );    
        }else{
            //everything ok - proceed to save into db
/* @TODO!!!            
            $preproc = array();
            $preproc['prepared_filename'] = $prepared_filename;
            $preproc['original_filename']
            $preproc['fields'] = $header;
            $preproc['memos']  = $memos;
            $preproc['multivals'] = $multivals;
            $preproc['keyfields'] = //indexes => "field_3":"10",
            $preproc['csv_enclosure']
            $preproc['csv_mvsep'],            
*/            
            $res = parse_db_save($preproc);
            if($res!==false){
                    unlink($prepared_filename);
            }
        }
    }
    
}

//
//  save file into table returns session
//
function parse_db_save($preproc){

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
    for ($i = 0; $i < $len; $i++) {
        $query = $query."`field_".$i."` ".(in_array($i, $preproc['memos'])?" mediumtext, ":" varchar(300), " ) ;
        $columns = $columns."field_".$i.",";
        $counts = $counts."count(distinct field_".$i."),";
        //array_push($mapping,0);
    }

    $query = $query." PRIMARY KEY (`imp_ID`)) ENGINE=InnoDB  DEFAULT CHARSET=utf8;";

    $columns = substr($columns,0,-1);
    $counts = $counts." count(*) ";


    if (!$mysqli->query($query)) {
        $system->addError(HEURIST_DB_ERROR, "Cannot create import table: " . $mysqli->error);                
        return false;
    }

    if($csv_enclosure=="'") $csv_enclosure = "\\".$csv_enclosure;

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
        $system->addError(HEURIST_DB_ERROR, 'Unable to import data. MySQL command: "'.$query.'" returns error: '.$mysqli->error);                
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
        "import_name"=>($preproc['original_filename']."  ".date("Y-m-d H:i:s")),
        "columns"=>$preproc['fields'],   //names of columns in file header
        "memos"=>$preproc['memos'],
        "multivals"=>$preproc['multivals'],
        "csv_enclosure"=>$preproc['csv_enclosure'],
        "csv_mvsep"=>$preproc['csv_mvsep'],
        "uniqcnt"=>$uniqcnt,   //count of uniq values per column
        "mapping"=>$mapping,   //mapping of value fields to rectype.detailtype
        "indexes"=>$preproc['keyfields'] );  //names of columns in importtable that contains record_ID


        
        
    $session = saveSession($mysqli, $session);
    if(count($warnings)>0){
        $session['load_warnings'] = $warnings;
    }
    return $session;
}

function saveSession($mysqli, $imp_session){

    $imp_id = mysql__insertupdate($mysqli, "sysImportSessions", "imp",
        array("imp_ID"=>@$imp_session["import_id"],
            "ugr_id"=>get_user_id(),
            "imp_table"=>$imp_session["import_name"],
            "imp_session"=>json_encode($imp_session) ));

    if(intval($imp_id)<1){
        return "Cannot save session. SQL error:".$imp_id;
    }else{
        $imp_session["import_id"] = $imp_id;
        return $imp_session;
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
        }else if($csv_linebreak="max"){
            $lb = "\r";
        }

//error_log(print_r($content,true));
        
        $lines = str_getcsv($content, $lb); 
        
        foreach($lines as &$Row) {
             $row = str_getcsv($Row, $csv_delimiter , $csv_enclosure); //parse the items in rows    
             array_push($response, $row);
        }
    }

    return $response;
}
?>
