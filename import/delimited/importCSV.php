<?php
/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* importCSV.php
* save, load mappings for csv import
*
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2014 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.6.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
    * @param includeUgrps=1 will output user and group information in addition to definitions
    * @param approvedDefsOnly=1 will only output Reserved and Approved definitions
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

/**
* import_session:  columns:[col1,col2,col3, ...],    names of columns in file header 
*                  uniqcnt: [cnt1, cnt2, .... ],     count of uniq values per column  
*                  mapping:[rt1.dt1, rt2.dt2, rt3.dt3],   mapping of value fields to rectype.detailtype  
*                  indexes:[]   names of columns in importtable that contains record_ID
*/

require_once(dirname(__FILE__).'/../../common/config/initialise.php');

$mysqli = mysqli_connection_overwrite(DATABASE);    

if(intval(@$_REQUEST["recid"])>0 && @$_REQUEST["table"] ){
    get_import_value($_REQUEST["recid"], $_REQUEST["table"]);
    exit();
}
?>
<html>
    <head>
        <title>Import Records from CSV (comma-separated values)</title>
        <link rel=stylesheet href="../../common/css/global.css" media="all">
        <link rel=stylesheet href="../../common/css/admin.css" media="all">
        
        <script src="../../external/jquery/jquery.js"></script>
        <script src="../../applications/h4/js/utils.js"></script>
    </head>

<body class="popup">
<?php
$imp_session = null;
if(@$_REQUEST["step1"]){
    
    if(intval(@$_REQUEST["import_id"])>0){
        
        $imp_session = get_import_session($_REQUEST["import_id"]);
        
    }else{
    
        $val_separator = $_REQUEST["val_separator"];
        $csv_delimiter = $_REQUEST["csv_delimiter"];
        $csv_linebreak = $_REQUEST["csv_linebreak"];
        $csv_enclosure = $_REQUEST["csv_enclosure"];
        
        $imp_session = postmode_file_selection();
    }
}else if(@$_REQUEST["step2"]){
//start import
    
}

//session is loaded - create full page
if(is_array($imp_session)){
    
    $len = count($imp_session['columns']);
?>
<script src="../../../common/php/loadCommonInfo.php"></script>
<script src="../../common/js/utilsUI.js"></script>
<script src="importCSV.js"></script>
<script>
var currentId = 1;
var recCount = <?=$imp_session['uniqcnt'][$len]?>;
var currentTable = "<?=$imp_session['import_table']?>";
var currentDb = "<?=HEURIST_DBNAME?>";
</script>

        <form action="importCSV.php" method="post" enctype="multipart/form-data" name="import_form" onsubmit="return verifyData()">
                <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
                <input type="hidden" name="step2" value="1">

<fieldset>
    <div>
        <div class="header"><label for="sa_rectype">Record type</label></div>
        <select name="sa_rectype" id="sa_rectype" class="text ui-widget-content ui-corner-all" style="min-width:300px"></select>
    </div>
    <div>
        <label for="sa_type0">Primary</label><input type="radio" checked="checked" name="sa_type" id="sa_type0" value="0" class="text" />
        <label for="sa_type1">Resource</label><input type="radio" name="sa_type" id="sa_type1" "value="1" class="text" />
    </div>
    <div>
        <label for="sa_addmode0">Insert</label><input type="radio" checked="checked" name="sa_addmode" id="sa_addmode0" value="0" class="text" />
        <label for="sa_addmode1">Update</label><input type="radio" name="sa_addmode" id="sa_addmode1" value="1" class="text" />
    </div>
    <br />
    <br />
</fieldset>

Total records: <?=$imp_session['uniqcnt'][$len]?><br />
<table border="1">
<thead>
    <th>Column</th><th>Uniq</th><th>Mapping</th>
    <th><a href="#" onclick="getValues(-1)">&lt;&lt;</a>Values<a href="#" onclick="getValues(1)">&gt;&gt;</a></th>
</thead>
<?php
//table with list of columns and datatype selectors

for ($i = 0; $i < $len; $i++) {     
print '<tr><td>'.$imp_session['columns'][$i].'</td><td>'.$imp_session['uniqcnt'][$i].'</td>';
print '<td><select name="sa_dt_'.$i.'" id="sa_dt_'.$i.'" style="min-width:300px"></select></td><td id="impval'.$i.'"> </td></tr>';
}
//apply    
    
?>
</table>        
                <div class="actionButtons">
                    <input type="button" value="Close" onClick="window.close();" style="margin-right: 5px;">
                    <input type="submit" value="Continue" style="font-weight: bold;">
                </div>
<?php    
}else{
    if($imp_session!=null){
        echo "<p color='red'>ERROR: ".$imp_session."</p>";        
    }
?>
        <form action="importCSV.php" method="post" enctype="multipart/form-data" name="import_form">
                <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
                <input type="hidden" name="step1" value="1">
            
                <div>
                     <label>Select existing session:</label>
                     <select name="import_id" onchange="document.forms[0].submit()"><?=get_list_import_sessions()?></select>
                </div>
                <div><label>Or upload new CSV file:</label><input type="file" size="50" name="import_file"></div>
                <div>
                    Field separator: <select name="csv_delimiter"><option value="," selected>comma</option><option value="\t">tab</option></select>&nbsp;&nbsp;&nbsp;
                    Multi-value separator: <select name="val_separator"><option selected value="|">|</option><option value=",">,</option><option value=":">:</option><option value=";">;</option></select>&nbsp;&nbsp;&nbsp;
                    Line separator: <input name="csv_linebreak" value="\n">&nbsp;&nbsp;&nbsp;
                    Quote: <select name="csv_enclosure"><option selected value='"'>"</option><option value="'">'</option></select><br/><br/>
                </div>
                <div class="actionButtons">
                    <input type="button" value="Cancel" onClick="window.close();" style="margin-right: 5px;">
                    <input type="submit" value="Continue" style="font-weight: bold;">
                </div>
<?php
}
?>        
        </form>    
</body>
</html>
<?php

function postmode_file_selection() {

/*****DEBUG****/// error_log("postmode_file_selection");

    // there are two ways into the file selection mode;
    // either the user has just arrived at the import page,
    // or they've selected a file *and might progress to file-parsing mode*
    $error = '';
    if (@$_FILES['import_file']) {
        if ($_FILES['import_file']['size'] == 0) {
            $error = 'no file was uploaded';
        } else {
            switch ($_FILES['import_file']['error']) {
                case UPLOAD_ERR_OK:
                break;
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                $error = "The uploaded file was too large.  Please consider importing it in several stages.";
                break;
                case UPLOAD_ERR_PARTIAL:
                $error = "The uploaded file was only partially uploaded.";
                break;
                case UPLOAD_ERR_NO_FILE:
                $error = "No file was uploaded.";
                break;
                case UPLOAD_ERR_NO_TMP_DIR:
                $error = "Missing a temporary folder.";
                break;
                case UPLOAD_ERR_CANT_WRITE:
                $error = "Failed to write file to disk";
                break;
                default:
                $error = "Unknown file error";
            }
        }

        if (!$error) {    // move on to the next stage!
            $error = postmode_file_load_to_db($_FILES['import_file']['tmp_name']);    
        }
    }

    return $error;
}

//read header (1st line)
//create temporary table import_datetime
//load file into table
//add record to import_log
function postmode_file_load_to_db($filename) {

    global $csv_delimiter,$csv_linebreak,$csv_enclosure,$mysqli;

    $handle = @fopen($filename, "r");
    if (!$handle) {
            if (! file_exists($filename)) return 'file does not exist';
            else if (! is_readable($filename)) return 'file is not readable';
            else return 'file could not be read';
    }
    
    // read header
    $line = stream_get_line($handle, 1000000, "\n");//$csv_linebreak);
    fclose($handle);
    if(!$line){
        return "empty header line";
    }

    //get fields
    $fields = str_getcsv ( $line, $csv_delimiter, $csv_enclosure );// $escape = "\\"
    $len = count($fields);
    $import_table = "import".date("YmdHis");
    
    if($len>200){
        return "too many columns ".$len;
    }

error_log(">>>> ".print_r($fields, true)."  ".$len);
    
    //create temporary table import_datetime
    $query = "CREATE TABLE `".$import_table."` (`imp_ID` int(10) unsigned NOT NULL AUTO_INCREMENT, ";
    $columns = "";
    $counts = "";
    $mapping = array();
    for ($i = 0; $i < $len; $i++) {     
        $query = $query."`field_".$i."` varchar(250), ";
        $columns = $columns."field_".$i.",";
        $counts = $counts."count(distinct field_".$i."),";
        array_push($mapping,0);
    }
    $query = $query." PRIMARY KEY (`imp_ID`))";

    $columns = substr($columns,0,-1);
    $counts = $counts." count(*) ";
    
    
    if (!$mysqli->query($query)) {
        return "can not create table: " . $mysqli->error;
    }    
    
    if($csv_enclosure=="'") $csv_enclosure = "\\".$csv_enclosure;
    
    //load file into table
    $query = "LOAD DATA LOCAL INFILE '".$filename."' INTO TABLE ".$import_table
        ." FIELDS TERMINATED BY '".$csv_delimiter."' "
        ." OPTIONALLY ENCLOSED BY '".$csv_enclosure."' "
        ." LINES TERMINATED BY '\n' "   //".$csv_linebreak."
        ." IGNORE 1 LINES (".$columns.")";
        
    if (!$mysqli->query($query)) {
        return "can not import data: " . $mysqli->error;
    }    
    
    //calculate uniq values    
    $query = "select ".$counts." from ".$import_table;
    $res = $mysqli->query($query);
    if (!$res) {
        return "can not count unique values: " . $mysqli->error;
    }    
    
    $uniqcnt = $res->fetch_row();
   
error_log("uniq>>>> ".print_r($uniqcnt, true)."  ".$len);
    //add record to import_log
    $session = array("columns"=>$fields,   //names of columns in file header 
                     "uniqcnt"=>$uniqcnt,   //count of uniq values per column  
                     "mapping"=>$mapping,   //mapping of value fields to rectype.detailtype  
                     "indexes"=>array("rt_0") );  //names of columns in importtable that contains record_ID
    
    $import_id = mysql__insertupdate($mysqli, "import_sessions", "imp", 
            array("imp_table"=>$import_table ,"imp_session"=>json_encode($session) ));    
    
    $session["import_id"] = $import_id;
    $session["import_table"] = $import_table;
    
    return $session;
}

/*
* put your comment there...
* 
* @param mixed $rec_id
* @param mixed $import_table
*/
function get_import_value($rec_id,$import_table){
    global $mysqli;
    
    $query = "select * from $import_table where imp_id=".$rec_id;
    $res = mysql__select_array2($mysqli, $query);
                
//error_log(">>>".$query."  ".json_encode($res));
                
    header('Content-type: text/javascript');
    print json_encode($res);
}

/**
* 
* @param mixed $import_id
* @return mixed
*/
function get_import_session($import_id){
    global $mysqli;
    if($import_id && is_numeric($import_id)){
        
        $res = mysql__select_array2($mysqli, 
                "select imp_session, imp_table from import_sessions where imp_id=".$import_id);
        
        $session = json_decode($res[0], true);
        $session["import_id"] = $import_id;
        $session["import_table"] = $res[1];
    
        return $session;    
    }else{
        return "Can not load import session id#".$import_id;       
    }
}
/**
* put your comment there...
*  
*/
function get_list_import_sessions(){
    
    global $mysqli;
    
     $query = "CREATE TABLE IF NOT EXISTS `import_sessions` (
    `imp_ID` int(11) unsigned NOT NULL auto_increment,
    `imp_table` varchar(255) NOT NULL default '',
    `imp_session` text,
    PRIMARY KEY  (`imp_ID`))";
    if (!$mysqli->query($query)) {
        return "can not create import session table: " . $mysqli->error;
    }    

    $ret = '<option value="0">select session...</option>';
    $query = "select imp_ID, imp_table from import_sessions";
    $res = $mysqli->query($query);
    if ($res){
        while ($row = $res->fetch_row()){
            $ret = $ret.'<option value="'.$row[0].'">'.$row[1].'</option>';
        }
        $res->close();
    }    
    //return "can not load list of sessions: " . $mysqli->error;
    return $ret;
}

//a couple functions from h4/utils_db.php
function mysql__select_array2($mysqli, $query) {
        $result = null;
        if($mysqli){
            $res = $mysqli->query($query);
            if($res){
                $row = $res->fetch_row();
                if($row){
                    $result = $row;
                }
                $res->close();
            }
        }
        return $result;
}
function mysql__insertupdate($mysqli, $table_name, $table_prefix, $record){

    $ret = null;

    if (substr($table_prefix, -1) !== '_') {
        $table_prefix = $table_prefix.'_';
    }

    $rec_ID = intval(@$record[$table_prefix.'ID']);
    $isinsert = ($rec_ID<1);

    if($isinsert){
        $query = "INSERT into $table_name (";
        $query2 = ') VALUES (';
    }else{
        $query = "UPDATE $table_name set ";
    }

    $params = array();
    $params[0] = '';

    foreach($record as $fieldname => $value){

            if(strpos($fieldname, $table_prefix)!==0){ //ignore fields without prefix
                //$fieldname = $table_prefix.$fieldname;
                continue;
            }

            if($isinsert){
                $query = $query.$fieldname.', ';
                $query2 = $query2.'?, ';
            }else{
                if($fieldname==$table_prefix."ID"){
                    continue;
                }
                $query = $query.$fieldname.'=?, ';
            }

            $params[0] = $params[0].((substr($fieldname, -2) === 'ID')?'i':'s');
            array_push($params, $value);
    }

    $query = substr($query,0,strlen($query)-2);
    if($isinsert){
        $query2 = substr($query2,0,strlen($query2)-2).")";
        $query = $query.$query2;
    }else{
        $query = $query." where ".$table_prefix."ID=".$rec_ID;
    }

//DEBUG print $query."<br>";

    $stmt = $mysqli->prepare($query);
    if($stmt){
        call_user_func_array(array($stmt, 'bind_param'), refValues($params));
        if(!$stmt->execute()){
            $ret = $mysqli->error;
        }else{
            $ret = ($isinsert)?$stmt->insert_id:$rec_ID;
        }
        $stmt->close();
    }else{
        $ret = $mysqli->error;
    }

    return $ret;
}

?>