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
* import_session:  import_table, reccount, 
*                  columns:[col1,col2,col3, ...],    names of columns in file header 
*                  uniqcnt: [cnt1, cnt2, .... ],     count of uniq values per column  
*                  mapping:[rt1.dt1, rt2.dt2, rt3.dt3],   mapping of value fields to rectype.detailtype  
*                  indexes:[]   names of columns in importtable that contains record_ID
*/

require_once(dirname(__FILE__).'/../../common/config/initialise.php');
require_once('importCSV_lib.php');

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
<style type="text/css">
th, td
{
border: 1px solid black;
}
div#div-progress {
    background-image: url(../../common/images/loading-animation-white.gif);
    background-repeat: no-repeat;
    background-position:center center;
}
div.analized{
    background-color:#DDD;
    border:black solid 1px;
}
</style>
    </head>

<body class="popup">
<?php
$imp_session = null;
$validationResults = null;
if(intval(@$_REQUEST["import_id"])>0){
   $imp_session = get_import_session($mysqli, $_REQUEST["import_id"]);
}
$step = intval(@$_REQUEST["step"]);
if(!$step) $step=0;

if($step==1 && $imp_session==null){ //load session
    
        $val_separator = $_REQUEST["val_separator"];
        $csv_delimiter = $_REQUEST["csv_delimiter"];
        $csv_linebreak = $_REQUEST["csv_linebreak"];
        $csv_enclosure = $_REQUEST["csv_enclosure"];
        
        $imp_session = postmode_file_selection();
}

//session is loaded - create full page
if(is_array($imp_session)){ 
?>
<script src="../../../common/php/loadCommonInfo.php"></script>
<script src="../../common/js/utilsUI.js"></script>
<script src="importCSV.js"></script>
<script>
var currentId = 1;
var recCount = <?=$imp_session['reccount']?$imp_session['reccount']:0?>;
var currentTable = "<?=$imp_session['import_table']?>";
var currentDb = "<?=HEURIST_DBNAME?>";
var form_vals = <?=($step>1)?json_encode($_REQUEST):"{}"?>;
</script>
<div id="div-progress">&nbsp;</div>
<div id="div-progress2">&nbsp;</div>
<?php    
    ob_flush();flush();
    
    if($step>1){
        if($step==2){  //verification
            $res = validateImport($mysqli, $imp_session, $_REQUEST);
        }else if($step==3){  //create records - load to import data to database
            $res = doImport($mysqli, $imp_session, $_REQUEST);
        }
        if(is_array($res)){
            $imp_session = $res;    
        }else{
            echo "<p style='color:red'>AERROR: ".$res."</p>";        
        }
    }
    
    $len = count($imp_session['columns']);
?>
        <form action="importCSV.php" method="post" enctype="multipart/form-data" name="import_form" onsubmit="return verifyData()">
                <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
                <input type="hidden" name="step" id="input_step" value="2">
                <input type="hidden" name="import_id" value="<?=$imp_session["import_id"]?>">

<fieldset>
    <div>
        <div class="header"><label for="sa_rectype">Record type</label></div>
        <select name="sa_rectype" id="sa_rectype" class="text ui-widget-content ui-corner-all" style="min-width:300px"></select>
    </div>
    <div>
        <label for="sa_type0">Primary</label><input type="radio" checked="checked" name="sa_type" id="sa_type0" value="0" class="text" />
        <label for="sa_type1">Resource</label><input type="radio" name="sa_type" id="sa_type1" "value="1" class="text" />
    </div>
    <!-- div>
        <label for="sa_addmode0">Insert</label><input type="radio" checked="checked" name="sa_addmode" id="sa_addmode0" value="0" class="text" />
        <label for="sa_addmode1">Update</label><input type="radio" name="sa_addmode" id="sa_addmode1" value="1" class="text" />
    </div -->
    <br />
    <br />
</fieldset>
<br />
<b>Records in buffer: <?=$imp_session['reccount']?>&nbsp;&nbsp;Fields:&nbsp;<?=$len?></b><br /><br />
<table style="width:100%" cellspacing="0" cellpadding="2">
<thead>
    <th>Column</th><th>Unique<br/>values</th><th>Mapping</th>
    <th width="30%">
        <a href="#" onclick="getValues(0)"><img src="../../common/images/calendar-ll-arrow.gif" /></a>
        <a href="#" onclick="getValues(-1)"><img src="../../common/images/calendar-l-arrow.gif" /></a>
        Values
        <a href="#" onclick="getValues(1)"><img src="../../common/images/calendar-r-arrow.gif" /></a>
        <a href="#" onclick="getValues(recCount)"><img src="../../common/images/calendar-rr-arrow.gif" /></a>
    </th>
</thead>
<?php
//table with list of columns and datatype selectors

$sIndexes = "";
$sRemain = "";
$sProcessed = "";

for ($i = 0; $i < $len; $i++) {     
    $s = '<tr><td>'.$imp_session['columns'][$i].'</td><td>'.$imp_session['uniqcnt'][$i].'</td>';

    if(@$imp_session["mapping"][$i]){
        $s = $s.'<td>'.$imp_session["mapping"][$i].'</td>';
    }else{ ;
        $s = $s.'<td><select name="sa_dt_'.$i.'" id="sa_dt_'.$i.'" style="min-width:300px"></select></td>';
    }
    $s = $s.'<td id="impval'.$i.'"> </td></tr>';

    if(@$imp_session["mapping"][$i]){
        $sProcessed = $sProcessed.$s;    
    }else{

        $rectype = array_search ( "field_".$i , $imp_session['indexes'], true );

        if($rectype){
            $sIndexes=$sIndexes.$s;    
        }else {
            $sRemain==$sRemain.$s;    
        }
    }
}//for
if($sIndexes){
    print '<tr><td colspan="4">Record IDs</td></tr>'.$sIndexes;
}
if($sRemain){
    print '<tr><td colspan="4">Remaining Data</td></tr>'.$sRemain;
}
if($sProcessed){
    print '<tr><td colspan="4">Already imported</td></tr>'.$sProcessed;
}
    
?>
</table>        
                <div>
                    <input type="submit" value="Analyse data in buffer" style="font-weight: bold;">
<?php
    $validationRes = @$imp_session['validation'];
    if($validationRes){
?>                    
                    <div style="analized">
                        <div style="display:inline:block">
                            Records matched:&nbsp;<?=$validationRes['rec_to_update']?><br />
                            New records to create:&nbsp;<?=$validationRes['rec_to_add']?><br />
                            Rows with field errors:&nbsp;<?=$validationRes['rec_error']?><br />
                        </div> 
                        <div>
                            <input type="button" value="Create records" onclick="doImport()" style="font-weight: bold;">
                        </div>
                    </div>
<?php
    }
?>                    
                    <input type="button" value="Close" onClick="window.close();" style="margin-right: 5px;">
                </div>
<?php    
}else{
    if($imp_session!=null){
        echo "<p color='red'>ERROR: ".$imp_session."</p>";        
    }
?>
        <form action="importCSV.php" method="post" enctype="multipart/form-data" name="import_form">
                <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
                <input type="hidden" name="step" value="1">
            
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
    $reccount = array_pop ( $uniqcnt );
   
//error_log("uniq>>>> ".print_r($uniqcnt, true)."  ".$len);
    //add record to import_log
    $session = array("reccount"=>$reccount,
                     "import_table"=>$import_table,
                     "columns"=>$fields,   //names of columns in file header 
                     "uniqcnt"=>$uniqcnt,   //count of uniq values per column  
                     "mapping"=>$mapping,   //mapping of value fields to rectype.detailtype  
                     "indexes"=>array() );  //names of columns in importtable that contains record_ID
    
    $import_id = mysql__insertupdate($mysqli, "import_sessions", "imp", 
            array("imp_table"=>$import_table ,"imp_session"=>json_encode($session) ));    
    
    $session["import_id"] = $import_id;
    
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
function get_import_session($mysqli, $import_id){
    
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


?>