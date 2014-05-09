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

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__)."/../../common/php/getRecordInfoLibrary.php");
require_once('importCSV_lib.php');


/*    if(isForAdminOnly("to modify database structure")){
        return;
    }*/

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
table.tbmain th, table.tbmain td
{
border: 0.5px solid gray;
}
div.loading {
    width:90%;
    height:100%;
    background-image: url(../../common/images/loading-animation-white.gif);
    background-repeat: no-repeat;
    background-position:center center;
    padding:20px;
    text-align:center;
    font-weight: bold;
}
div.input-line{
    height:3em;
}
div.analized{
    background-color:#DDD;
    border:black solid 1px;
    width:500px;
    display:inline-block;
    padding:5px;
}
div.header{
    display:inline-block;
    font-weight: bold;
}
</style>
    </head>

<body class="popup">
USER_ID:<?=get_user_id()?>
<?php
$imp_session = null;
$validationResults = null;
if(intval(@$_REQUEST["import_id"])>0){
   $imp_session = get_import_session($mysqli, $_REQUEST["import_id"]);
}
$step = intval(@$_REQUEST["step"]);
if(!$step) $step=0;

if($step==1 && $imp_session==null){ //load session
        echo '<div id="div-progress" class="loading">Please wait, file is processing on server</div>';
        ob_flush();flush();
    
        $val_separator = $_REQUEST["val_separator"];
        $csv_delimiter = $_REQUEST["csv_delimiter"];
        $csv_linebreak = $_REQUEST["csv_linebreak"];
        $csv_enclosure = $_REQUEST["csv_enclosure"];
        
        $imp_session = postmode_file_selection();
}

//session is loaded - create full page
if(is_array($imp_session)){ 
?>
        <script src="../../common/php/loadCommonInfo.php?db=<?=HEURIST_DBNAME?>"></script>
        <script src="../../common/js/utilsUI.js"></script>
        <script src="importCSV.js"></script>
        <script>
        var currentId = 1;
        var recCount = <?=$imp_session['reccount']?$imp_session['reccount']:0?>;
        var currentTable = "<?=$imp_session['import_table']?>";
        var currentDb = "<?=HEURIST_DBNAME?>";
        var form_vals = <?=($step>1)?json_encode($_REQUEST):"{}"?>;
        </script>
<?php    
    
    if($step>1){
        $res = null;
        if($step==2){  //verification
        
            echo '<div id="div-progress" class="loading">Please wait, mapping validation in progress</div>';
            ob_flush();flush();
        
            $res = validateImport($mysqli, $imp_session, $_REQUEST);
            
        }else if($step==3){  //create records - load to import data to database
        
            echo '<div id="div-progress2"></div>';
            echo '<div id="div-progress" class="loading">Please wait, records are creating/updating</div>';
            ob_flush();flush();
        
            $res = doImport($mysqli, $imp_session, $_REQUEST);
        }
        if(is_array($res)) {
            $imp_session = $res;
        }else if($res && !is_array($res)){
            echo "<p style='color:red'>ERROR: ".$res."</p>";        
        }
    }
    
    $len = count($imp_session['columns']);
  
/* DEBUG  
echo "<div>imp session: ".print_r($imp_session)."<br>";
echo "REUQEST: ".print_r($_REQUEST)."</div>";
*/
?>
<div id="main_mapping">
        <h4>IMPORT DATA Step 2</h4>
        <hr width="100%" />
        <div class="help">
If the spreadsheet data is complex, this function will allow you to progressively import columns which identify subsidiary entities (record types) such as place, organisation, collection, series, artist etc. The first step is to match key fields and create new records from unmatched rows. This will create a new column ending in ID. This can be used as the key field to import additional columns. Once all subsidiary entities have been matched and imported, you can import the primary entity type representing by the table.
<br/><br/>
Please visit <a target="_blank" href="http://HeuristNetwork.org/archive/importing-data">HeuristNetwork.org/archive/importing-data</a> for a detailed explanation and examples of record import.        
<br/><br/><br/>
        </div>

        <form action="importCSV.php" method="post" enctype="multipart/form-data" name="import_form" onsubmit="return verifyData()">
                <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
                <input type="hidden" name="step" id="input_step" value="2">
                <input type="hidden" name="import_id" value="<?=$imp_session["import_id"]?>">


    <div class="input-line">
        <div class="header"><label for="sa_rectype">Select record type to import</label></div>
        <select name="sa_rectype" id="sa_rectype" class="text ui-widget-content ui-corner-all" style="min-width:300px"></select>
    </div>
    <div>
        <div class="header"><label for="sa_rectype">Match / create</label></div><br/>
        <div style="padding-left:30px;display: inline-block; width:180px">
        <input type="radio" <?=@$_REQUEST['sa_type']==1?"":"checked"?> name="sa_type" id="sa_type1" value="0" class="text" />&nbsp;
        <label for="sa_type1">Secondary records</label><br>
        
        <input type="radio" <?=@$_REQUEST['sa_type']==1?"checked":""?> name="sa_type" id="sa_type0" value="1" class="text" />&nbsp;
        <label for="sa_type0">Primary records</label>
        </div>
        <div style="display: inline-block;">
            <div class="help" style="font-size:0.8em;padding-bottom: 4px;">Secondary records = repeating values in column(s) in the table</div>
            <div class="help" style="font-size:0.8em">Primary records = the main items representing by this table.<br>Do matching first before update to create record key columns</div>
        </div>
    </div>
    <div>
        <div class="header"><label for="sa_rectype">Update</label></div><br/>
        <div style="padding-left:30px">
        <input type="radio" <?=@$_REQUEST['sa_upd']>0?"":"checked"?> name="sa_upd" id="sa_upd0" value="0" class="text" />&nbsp;
        <label for="sa_upd0">Update fields</label>
        
        <input type="radio" <?=@$_REQUEST['sa_upd']==1?"checked":""?> name="sa_upd" id="sa_upd1" value="1" class="text" />&nbsp;
        <label for="sa_upd1">Add to existing values</label>
        
        <input type="radio" <?=@$_REQUEST['sa_upd']==2?"checked":""?> name="sa_upd" id="sa_upd2" value="2" class="text" />&nbsp;
        <label for="sa_upd2">Replace existing values</label>
        </div>
    </div>
    
    <!-- div>
        <label for="sa_addmode0">Insert</label><input type="radio" checked="checked" name="sa_addmode" id="sa_addmode0" value="0" class="text" />
        <label for="sa_addmode1">Update</label><input type="radio" name="sa_addmode" id="sa_addmode1" value="1" class="text" />
    </div -->
    <br />
    <br />

<br />
<b>Records in buffer: <?=$imp_session['reccount']?>&nbsp;&nbsp;Fields:&nbsp;<?=$len?></b><br /><br />
<table style="width:100%" cellspacing="0" cellpadding="2" class="tbmain">
<thead>
    <th>Inport<br/>value</th><th>Unique<br/>values</th><th>Column</th><th width="310">Mapping</th>
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
$recStruc = getAllRectypeStructures(true);
$idx_dt_name = $recStruc['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];
//$idx_rt_name = $recStruc['commonNamesToIndex']['rty_Name'];

for ($i = 0; $i < $len; $i++) {     
    
    $isProcessed = @$imp_session["mapping"]["field_".$i];
    $isIndex = false;
    
    if($isProcessed){
        $checkbox = '<td>&nbsp;</td>'; //processed
    }else{
        $isIndex = (array_search ( "field_".$i , $imp_session['indexes'], true )!==false);
        
        $checkbox = '<td align="right"><input type="checkbox" id="cbsa_dt_'.$i.'" onclick="{hideFtSelect('.$i.');}"/></td>';
    }
    
    $s = '<tr>'.$checkbox.'<td align="center">'.$imp_session['uniqcnt'][$i].'</td><td>'.$imp_session['columns'][$i].'</td>';

    if($isProcessed){
        
        $rt_dt = explode(".",$isProcessed);
        $recTypeName = $recStruc['names'][$rt_dt[0]];//['commonFields'][ $idx_rt_name ];
        $dt_Name = intval($rt_dt[1])>0?$recStruc['typedefs'][$rt_dt[0]]['dtFields'][$rt_dt[1]][$idx_dt_name]:$rt_dt[1];

        $s = $s.'<td>'.$recTypeName.' '.$dt_Name.'  ('.$rt_dt[0].'.'.$rt_dt[1].')</td>';
    }else{ ;
        $s = $s.'<td>&nbsp;<span style="min-width:302px;display:none">'
               .'<select name="sa_dt_'.$i.'" id="sa_dt_'.$i.'" style="min-width:300px" '
               . ($isIndex?'class="indexes"':'').'></select></span></td>';
    }
    $s = $s.'<td id="impval'.$i.'"> </td></tr>';

    if($isProcessed){
        $sProcessed = $sProcessed.$s;    
    }else{
        if($isIndex){
            $sIndexes=$sIndexes.$s;    
        }else {
            $sRemain=$sRemain.$s;    
        }
    }
}//for
if($sIndexes){
    print '<tr><td colspan="5"><b>Record IDs</b></td></tr>'.$sIndexes;
}
if($sRemain){
    print '<tr><td colspan="5"><b>Remaining Data</b></td></tr>'.$sRemain;
}
if($sProcessed){
    print '<tr><td colspan="5"><b>Already imported</b></td></tr>'.$sProcessed;
}
    
?>
</table> 
<br/><br/>
                <div style="vertical-align:middle;">
                    <div style="display:inline-block">  
                        <input type="submit" value="Analyse data in buffer" style="font-weight: bold;">
                        <div class="help" style="font-size: 0.8em;"><br></div>
                    </div>
<?php
    $validationRes = @$imp_session['validation'];
    if($validationRes){
        $err_cnt = count($validationRes['rec_error']);
        
        $show_err     = ($err_cnt>0)?"<a href='#' onclick='showRecords(\"error\")'>show</a>" :"&nbsp;";
        $show_matched = $validationRes['rec_to_update']>0?"<a href='#' onclick='showRecords(\"update\")'>show</a>" :"&nbsp;";
        $show_new     = $validationRes['rec_to_add']>0?"<a href='#' onclick='showRecords(\"create\")'>show</a>" :"&nbsp;";
?>                    
                    <div class="analized">
                        <table style="display: inline-block; border:none" border="0">
                            <tr><td>Records matched:</td><td><?=$validationRes['rec_to_update']?></td><td><?=$show_matched?></td></tr>
                            <tr><td>New records to create:</td><td><?=$validationRes['rec_to_add']?></td><td><?=$show_new?></td></tr>
                            <tr><td>Rows with field errors:</td><td><?=$err_cnt?></td><td><?=$show_err?></td></tr>
                        </table>
                        <div style="float:right;vertical-align:middle;padding-top:10px">
                            <input type="button" value="Create records" onclick="doImport()" style="font-weight: bold;">
                        </div>
                    </div>
<?php
    }else{
        print '<div style="width:500px;display:inline-block"></div>';
    }
?>                  
                    <div style="display:inline-block">  
                        <input type="button" value="Close" onClick="window.close();" style="margin-right: 5px;">
                        <div class="help" style="font-size: 0.8em;">Unprocessed data is <br>retained in buffer</div>
                    </div>
                </div>
        </form>    
</div>
<div id="main_error" style="display:none;">
        <h4>RECORDS WITH FIELD ERRORS</h4>
        <hr width="100%" />
        <div>
            Message: <?=$validationRes['err_message']?>
        </div>
<?php
    if($validationRes){
        ///DEBUG print "fields ".print_r(@$validationRes['field_checked'],true)."<br> recs";
        ///DEBUG print print_r(@$validationRes['rec_error'],true);
        
        $err_cnt = count($validationRes['rec_error']);
        if($err_cnt>0){
            print '<table class="tbmain">';
            print "<thead><th>Line #</th>";
            
            foreach($validationRes['field_checked'] as $field_name) {
                
                $colname = @$imp_session['columns'][substr($field_name,6)];
                
                print "<th>".$colname."</th>";
            }
            print "</thead>";
            foreach ($validationRes['rec_error'] as $row) {  

                print "<tr>";
                if(is_array($row)){
                    foreach($row as $value) {     
                        print "<td>".($value?$value:"&nbsp;")."</td>";
                    }
                }
                print "</tr>";
            }
            print "</table>";
        }
    }    
?>
    <input type="button" value="Back" onClick="showRecords('mapping');">
</div>
<?php    
}else{ //================================================================================
    if($imp_session!=null){
        echo "<p color='red'>ERROR: ".$imp_session."</p>";        
    }
?>
<script type="text/javascript">
function doUpload(){
    $("#div-progress").show();
    $(document.forms[0]).hide();
    document.forms[0].submit();
}
function doSelectSession(){
    $("#div-progress").html('Loading selected session');
    $("#div-progress").show();
    $(document.forms[0]).hide();
    document.forms[0].submit();
}
</script>
        <div id="div-progress" class="loading" style="display: none;">
                Please wait, file is uploading (time required will depend on your network connection speed)
        </div>

        <h4>UPLOAD DATAFILE OR SELECT SAVED SESSION Step 1</h4>
        <hr width="100%" />
        
        <form action="importCSV.php" method="post" enctype="multipart/form-data" name="upload_form">
                <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
                <input type="hidden" name="step" value="1">
            
                <div class="input-line">
                     <label>Select existing session:</label>
                     <select name="import_id" onchange="doSelectSession()"><?=get_list_import_sessions()?></select>
                </div>
                <div class="input-line">
                    <label>Or upload new CSV file:</label><input type="file" size="50" name="import_file">
                </div>
                <div class="input-line">
                    Field separator: <select name="csv_delimiter"><option value="," selected>comma</option><option value="\t">tab</option></select>&nbsp;&nbsp;&nbsp;
                    <!-- Multi-value separator: <select name="val_separator"><option selected value="|">|</option><option value=",">,</option><option value=":">:</option><option value=";">;</option></select>&nbsp;&nbsp;&nbsp; -->
                    Line separator: <input name="csv_linebreak" value="\n">&nbsp;&nbsp;&nbsp;
                    Quote: <select name="csv_enclosure"><option selected value='"'>"</option><option value="'">'</option></select><br/><br/>
                </div>
                <div class="actionButtons">
                    <input type="button" value="Cancel" onClick="window.close();" style="margin-right: 5px;">
                    <input type="button" value="Continue" style="font-weight: bold;" onclick="doUpload()">
                </div>
        </form>    
<?php
}
?>        
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

//error_log(">>>> ".print_r($fields, true)."  ".$len);
    
    //create temporary table import_datetime
    $query = "CREATE TABLE `".$import_table."` (`imp_ID` int(10) unsigned NOT NULL AUTO_INCREMENT, ";
    $columns = "";
    $counts = "";
    $mapping = array();
    for ($i = 0; $i < $len; $i++) {     
        $query = $query."`field_".$i."` varchar(250), ";
        $columns = $columns."field_".$i.",";
        $counts = $counts."count(distinct field_".$i."),";
        //array_push($mapping,0);
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
            array("ugr_id"=>get_user_id(), "imp_table"=>$import_table ,"imp_session"=>json_encode($session) ));    
    
    $session["import_id"] = $import_id;
    
    return $session;
}

?>