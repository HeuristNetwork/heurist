<?php
/**
* importCSV.php: UI for delimeted data import
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
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
* import_session:  import_table, reccount, 
*                  columns:[col1,col2,col3, ...],    names of columns in file header 
*                  uniqcnt: [cnt1, cnt2, .... ],     count of uniq values per column  
*                  mapping:[rt1.dt1, rt2.dt2, rt3.dt3],   mapping of value fields to rectype.detailtype  
*                  indexes:[]   names of column =>rectype : columns in importtable that contains record_ID
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
}else if( @$_REQUEST["clearsession"] ){
    
    clear_import_session($_REQUEST["clearsession"]);
    exit();
}
?>
<html>
    <head>
        <title>Import Records from CSV (comma-separated values)</title>

        <script type="text/javascript" src="../../external/jquery/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
        <script type="text/javascript" src="../../external/jquery/jquery-ui-1.10.2/ui/jquery-ui.js"></script>
        <link rel="stylesheet" href="../../applications/h4/ext/jquery-ui-1.10.2/themes/base/jquery-ui.css">
<!--        
        <script type="text/javascript" src="../../external/jquery/jquery.js"></script>
        <script type="text/javascript" src="../../external/jquery/jquery-ui-1.8.13.custom.min.js "></script>
        <link rel="stylesheet" href="../../external/jquery/jquery-ui-1.8.css">
-->        
        
        <link rel=stylesheet href="../../common/css/global.css" media="all">
        
        <script src="../../applications/h4/js/utils.js"></script>
<style type="text/css">
.tbmain th, .subh
{
    border-left: 1px solid gray;
    border-bottom: 1px solid gray;
}
.subh
{
    border-top: 1px solid gray;
}
.tbmain td
{
    border-left: 1px solid gray;
}
.tbmain
{
    border-top: 1px solid gray;
    border-right: 1px solid gray;
    border-bottom: 1px solid gray;
    font-size: 0.7em;
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
    display:inline-block;
    padding:5px;
    min-width:250px;
}
div.header{
    display:inline-block;
    font-weight: bold;
}
.truncate {
  max-width: 150px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.required{
    font-weight: bold;
}
.help{
    font-size:0.8em;
}
</style>
    </head>

<body class="popup">
<script type="text/javascript">
//
//
//
function showProgressMsg(msg){
    $("#div-progress").html(msg);
    $("#div-progress").show();
}
//
// clear all upload tables and empty session table
//
function doClearSession(session_id){
   
   var msg;
   if(session_id=='all') {
       msg = "Clear all saved sessions?";
   }else{
       msg = "Clear saved session?";
   }
    
   if( confirm(msg) ) {
       
      var currentDb = "<?=HEURIST_DBNAME?>"; 
       
                 $.ajax({
                         url: top.HEURIST.basePath+'import/delimited/importCSV.php',
                         type: "POST",
                         data: {clearsession: session_id, db:currentDb},
                         dataType: "json",
                         cache: false,
                         error: function(jqXHR, textStatus, errorThrown ) {
                              alert('Error connecting server. '+textStatus);
                         },
                         success: function( response, textStatus, jqXHR ){
                             if(response=="ok"){
                                 alert("Clear session operation completed");
                                 if(session_id=='all'){
                                    $("#import_id").empty();
                                 }else{
                                    window.close();
                                     //setTimeout(function(){window.close();}, 2000);
                                 }
                             }else{
                                alert(response);
                             }
                         }
                     });
   }
}
</script>
<?php
//USER_ID:=get_user_id()
$imp_session = null;

$sa_mode = @$_REQUEST['sa_mode'];
$step = intval(@$_REQUEST["step"]);
if(!$step) $step=0;

echo '<div id="div-progress2"></div>';
echo '<div id="div-progress" style="display:none" class="loading">&nbsp;</div>';
ob_flush();flush();

//load session
if(intval(@$_REQUEST["import_id"])>0){
   echo '<script>showProgressMsg("Please wait, session loading in progress")</script>';
   ob_flush();flush();
   $imp_session = get_import_session($mysqli, $_REQUEST["import_id"]);
}



//first step - load file into import table
if($step==1 && $imp_session==null){ 
        echo '<script>showProgressMsg("Please wait, file is processing on server")</script>';
        ob_flush();flush();
    
        $val_separator = $_REQUEST["val_separator"];
        $csv_delimiter = $_REQUEST["csv_delimiter"];
        $csv_linebreak = $_REQUEST["csv_linebreak"];
        $csv_enclosure = $_REQUEST["csv_enclosure"];
        
        $imp_session = postmode_file_selection();
}

//session is loaded - render second step page
if(is_array($imp_session)){ 
?>
        <script src="../../common/php/loadCommonInfo.php?db=<?=HEURIST_DBNAME?>"></script>
        <script src="../../common/js/utilsUI.js"></script>
        <script src="importCSV.js"></script>
        <script>
        var currentId = 1;
        var recCount = <?=$imp_session['reccount']?$imp_session['reccount']:0?>;
        var currentTable = "<?=$imp_session['import_table']?>";
        var currentSessionName = "<?=$imp_session['import_name']?>";
        var currentDb = "<?=HEURIST_DBNAME?>";
        var form_vals = <?=($step>1)?json_encode($_REQUEST):"{}"?>;
        </script>
<?php    
    
    if($step>1){
        $res = null;

        if($sa_mode==0){ //matching
            
            if($step==2){  //find
            
                echo '<script>showProgressMsg("Please wait, matching in progress")</script>';
                ob_flush();flush();
            
                $res = matchingSearch($mysqli, $imp_session, $_REQUEST);
                
            }else if($step==3){  //assign ids
                
                echo '<script>showProgressMsg("Please wait, assign of records ids")</script>';
                ob_flush();flush();
            
                $res = matchingAssign($mysqli, $imp_session, $_REQUEST);
            }
            
        }else{//importing
        
            if($step==2){  //verification
            
                echo '<script>showProgressMsg("Please wait, mapping validation in progress")</script>';
                ob_flush();flush();
            
                $res = validateImport($mysqli, $imp_session, $_REQUEST);
                
//error_log(print_r($res, true));
                
            }else if($step==3){  //create records - load to import data to database
            
                echo '<script>showProgressMsg("Please wait, records are creating/updating")</script>';
                ob_flush();flush();
            
                $res = doImport($mysqli, $imp_session, $_REQUEST);
            }
        
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
        <h4>IMPORT DATA Step 2 <font color="red">(Work in Progress)</font></h4>
        <hr width="100%" />
        <div class="help">
If the spreadsheet data is complex, this function will allow you to progressively import columns which identify subsidiary entities (record types) such as place, organisation, collection, series, artist etc. The first step is to match key fields and create new records from unmatched rows. This will create a new column ending in ID. This can be used as the key field to import additional columns. Once all subsidiary entities have been matched and imported, you can import the primary entity type representing by the table.
<br/><br/>
Please visit <a target="_blank" href="http://HeuristNetwork.org/archive/importing-data">HeuristNetwork.org/archive/importing-data</a> for a detailed explanation and examples of record import.        
<br/><br/><br/>
        </div>

        <form action="importCSV.php" method="post" enctype="multipart/form-data" name="import_form" onsubmit="return verifySubmit()">
                <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
                <input type="hidden" name="step" id="input_step" value="2">
                <input type="hidden" name="import_id" value="<?=$imp_session["import_id"]?>">


    <div class="input-line">
        <div class="header"><label for="sa_rectype">Select record type</label></div>
        <select name="sa_rectype" id="sa_rectype" class="text ui-widget-content ui-corner-all" style="min-width:290px"></select>
    </div>
<!--    
    <div>
        <div class="header"><label>Action mode</label></div><br/>
        <table style="padding-left:30px; width:600px">
        <tr><td>
                <input type="radio" <?=@$_REQUEST['sa_mode']==1?"":"checked"?> name="sa_mode" id="sa_mode0" 
                        value="0" class="text" onchange="showUpdMode()" />&nbsp;
                <label for="sa_mode0"><b>Search / match</b></label>
        </td><td>
                <input type="radio" <?=@$_REQUEST['sa_mode']==1?"checked":""?> name="sa_mode" id="sa_mode1" 
                        value="1" class="text" onchange="showUpdMode()"/>&nbsp;
                <label for="sa_mode1"><b>Insert / update records</b></label>
        </td></tr><tr class="help"><td><span class="help">
            Select one or more of the input columns to match input rows with records in the database. Matched rows will be identified by record ID. Unmatched rows will be marked for creation of new records</span>
        </td><td><span class="help">Choose the ID column identifying the records to be inserted or updated from the dropdown. Choose the data fields to be updated from the table.</span>
        </td><tr>
        </table>
    </div>
-->
<input type="hidden" value="<?=@$_REQUEST['sa_mode']?>" name="sa_mode" id="sa_mode"/> 

<div id="tabs_actions">
    <ul>
        <li><a href="#matching">Search / match</a></li>
        <li><a href="#import">Insert / update records</a></li>
    </ul>    
    <div id="matching">
        <span class="help">Select one or more of the input columns to match input rows with records in the database. Matched rows will be identified by record ID. Unmatched rows will be marked for creation of new records<br/><br/></span>
<!--        
        <span class="help">Select key fields and find record ID to unique identification of import record. Heurist database is not affected<br/><br/></span>
-->
        <div id="div_idfield" style="padding-left:30px;display:none;">
                <!-- div class="header"><label>ID field</label></div -->
                
                <span id="idf_reuse">To redo the matching, re-use one of existing fields</span>
                
                <table cellspacing="0" cellpadding="2" width="90%">
                <?php
                //radiobuttons - list of all ID fields
                for ($i = 0; $i < $len; $i++) {     
                    
                   //'.(@$_REQUEST['idfield']=="field_".$i?"checked":"").' 
                   //($ischecked?"":"checked")
                    
                   $rectype = @$imp_session['indexes']["field_".$i];
                   if($rectype){
                       print '<tr class="idfield_'.$rectype.'">'
                                .'<td align="center" width="5">'
                                .'<input type="radio" name="idfield" id="rb_dt_'.$i.'" value="field_'.$i.'" /></td>'   //radio
                                .'<td align="left" class="truncate">'.$imp_session['columns'][$i].'</td></tr>';                                        //name
                   }
                }
                ?>
                    <tr><td colspan="2"><span id="idf_new">Enter new field name to hold record identifiers</span></td></tr>
                    <tr>
                        <td align="center" width="5">
                            <input type="radio" name="idfield" id="rb_dt_new" value="field_<?=$len?>" checked="checked" />
                        </td><td align="left">
                            <input name="new_idfield" id="new_idfield" type="text" size="20"/>
                            <span class="help">(you may wish to change this to identify a specific use of the entity represented by the key columns selected)</span>
                        </td></tr>
                </table>
        </div>
    
        <div>
        
        <span class="help">Select import columns and matching to database fields for the selected record type</span>
        <input type="hidden" name="recid_field" id="recid_field" />
        <table class="tbmain" style="width:100%" cellspacing="0" cellpadding="2">
        <thead><tr>
            <th>Key<br/>field</th><th>Unique<br/>values</th><th>Column</th><th>Mapping</th>
            <th width="300px" style="text-align: left;padding-left: 6px;">
                <a href="#" onclick="getValues(0);return false;"><img src="../../common/images/calendar-ll-arrow.gif" /></a>
                <a href="#" onclick="getValues(-1);return false;"><img src="../../common/images/calendar-l-arrow.gif" /></a>
                Values in row <span id="currrow_0"></span>
                <a href="#" onclick="getValues(1);return false;"><img src="../../common/images/calendar-r-arrow.gif" /></a>
                <a href="#" onclick="getValues(recCount);return false;"><img src="../../common/images/calendar-rr-arrow.gif" /></a>
            </th></tr>
        </thead>
        <?php
        //
        // render matching table 
        //
        for ($i = 0; $i < $len; $i++) {     
            print '<td align="right">&nbsp;<span style="display:none;"><input type="checkbox" id="cbsa_keyfield_'.$i.'" value="field_'.$i
                    .'" onchange="{showHideSelect2('.$i.');}"/></span></td>';
            print '<td align="center">'.$imp_session['uniqcnt'][$i].'</td><td class="truncate">'.$imp_session['columns'][$i].'</td>';
            
            print '<td style="width:306px;">&nbsp;<span style="display:none;">'
                       .'<select name="sa_keyfield_'.$i.'" id="sa_keyfield_'.$i.'" style="max-width:260px" onchange="{onFtSelect2('.$i.');}">'
                       . '</select></span></td>';    
            print '<td id="impval_'.$i.'" style="text-align: left;padding-left: 16px;"> </td></tr>';
              
        }

        ?>
        </table>
        </div>
    
    </div>
<!-- ************************************************************************************ -->    
    <div id="import">
        <span class="help">Create/update records in Heurist database according to record ID field<br/></span>    
    
        <div style="padding-left:30px;">
            <div class="header"><label>Update mode settings</label></div><br/>
            <div style="padding-left:30px;display: inline-block;">
            <input type="radio" <?=@$_REQUEST['sa_upd']>0?"":"checked"?> name="sa_upd" id="sa_upd0" value="0" class="text" 
                    onchange="{onUpdateModeSet()}"/>&nbsp;
            <label for="sa_upd0">Retain existing values and append new data as repeat values</label><br/>
            
            <input type="radio" <?=@$_REQUEST['sa_upd']==1?"checked":""?> name="sa_upd" id="sa_upd1" value="1" class="text" 
                    onchange="{onUpdateModeSet()}"/>&nbsp;
            <label for="sa_upd1">Add new data only if field is empty (new data ignored for non-empty fields)</label><br/>
            
            <input type="radio" <?=@$_REQUEST['sa_upd']==2?"checked":""?> name="sa_upd" id="sa_upd2" value="2" class="text" 
                    onchange="{onUpdateModeSet()}" />&nbsp;
            <label for="sa_upd2">Add and replace all existing value(s) for the record with new data</label>
            </div>
            <div style="padding-left:10px;display: <?=@$_REQUEST['sa_upd']==2?"inline-block":"none"?>; vertical-align: top;" id="divImport2">
            <input type="radio" <?=@$_REQUEST['sa_upd2']>0?"":"checked"?> name="sa_upd2" id="sa_upd20" value="0" class="text" />&nbsp;
            <label for="sa_upd20">Retain existing if no new data supplied for record</label><br/>
            
            <input type="radio" <?=@$_REQUEST['sa_upd2']==1?"checked":""?> name="sa_upd2" id="sa_upd21" value="1" class="text" />&nbsp;
            <label for="sa_upd21">Delete existing if no new data supplied for record</label>
            </div>
        </div>    
        
    
        <span class="help">Choose the ID column identifying the records to be inserted or updated from the dropdown. Choose the data fields to be updated from the table.</span>
        
        <table class="tbmain" style="width:100%" cellspacing="0" cellpadding="2">
        <thead><tr>
            <th>ID<br/>field</th>
            <th>Import<br/>value</th><th>Unique<br/>values</th><th style="max-width:100px;">Column</th><th width="310">Mapping</th>
            <th width="30%" style="text-align: left;padding-left: 16px;">
                <a href="#" onclick="getValues(0);return false;"><img src="../../common/images/calendar-ll-arrow.gif" /></a>
                <a href="#" onclick="getValues(-1);return false;"><img src="../../common/images/calendar-l-arrow.gif" /></a>
                Values in row <span id="currrow_1"></span>
                <a href="#" onclick="getValues(1);return false;"><img src="../../common/images/calendar-r-arrow.gif" /></a>
                <a href="#" onclick="getValues(recCount);return false;"><img src="../../common/images/calendar-rr-arrow.gif" /></a>
            </th></tr>
        </thead>
        <?php
        //
        // render mapping table with list of columns and datatype selectors
        //
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
                $checkbox = '<td>&nbsp;</td><td align="center"><input type="checkbox" disabled="disabled" /></td>'; //processed
            }else{
                $rectype = @$imp_session['indexes']["field_".$i];
                $isIndex = ($rectype!=null);
                if($isIndex){
                    $idradio = '<td align="center">&nbsp;'
                        .'<span class="idfield_'.$rectype.'">'
                        .'<input type="radio" name="recid" id="recid_'.$i.'" value="field_'.$i.'" onchange="{onRecIDselect('.$i.')}" /></span></td>';
                }else{
                    $idradio = '<td>&nbsp;</td>';
                }
                $checkbox = $idradio
                .'<td align="right">&nbsp;<span style="display:none;"><input type="checkbox" id="cbsa_dt_'.$i.'" onchange="{showHideSelect('.$i.');}"/></span></td>';
            }
            
            $s = '<tr>'.$checkbox.'<td align="center">'.$imp_session['uniqcnt'][$i].'</td><td class="truncate">'.$imp_session['columns'][$i].'</td>';

            if($isProcessed){
                
                $rt_dt = explode(".",$isProcessed);
                $recTypeName = $recStruc['names'][$rt_dt[0]];//['commonFields'][ $idx_rt_name ];
                $dt_Name = intval($rt_dt[1])>0?$recStruc['typedefs'][$rt_dt[0]]['dtFields'][$rt_dt[1]][$idx_dt_name]:$rt_dt[1];

                $s = $s.'<td>'.$recTypeName.' '.$dt_Name.'  ('.$rt_dt[0].'.'.$rt_dt[1].')</td>';
            }else{ ;
                $s = $s.'<td style="width:306px;">&nbsp;<span style="display:none;">'
                       .'<select name="sa_dt_'.$i.'" id="sa_dt_'.$i.'" style="max-width:260px" onchange="{onFtSelect('.$i.');}"'
                       . ($isIndex?'class="indexes"':'').'></select></span></td>';
            }
            $s = $s.'<td id="impval'.$i.'" style="text-align: left;padding-left: 16px;"> </td></tr>';

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
            print '<tr><td class="subh" colspan="6"><b>Record IDs</b></td></tr>'.$sIndexes;
        }
        if($sRemain){
            print '<tr><td class="subh" colspan="6"><b>Remaining Data</b></td></tr>'.$sRemain;
        }
        if($sProcessed){
            print '<tr><td class="subh" colspan="6"><b>Already imported</b></td></tr>'.$sProcessed;
        }
            
        ?>
        </table>  
               
    </div>
</div>    
<br />
<b>Records in buffer: <?=$imp_session['reccount']?>&nbsp;&nbsp;Fields:&nbsp;<?=$len?></b><br />

                <table style="vertical-align:middle;width:100%">
                    <tr>
                    <td width="30%">  
                        <span class="matching">
                            <span id="btnStartMatch" style="display: none">
                                <input type="submit" value="Start search/match" style="font-weight: bold;">
                            </span>
                        </span>
                        <span class="importing"><span id="btnStartImport" style="display: none"><input type="submit" value="Analyse data in buffer" style="font-weight: bold;"></span></span>
                        <div class="help"><br></div>
                    </td>
<?php
//
// render validation result box
//

    $validationRes = @$imp_session['validation'];
    if($validationRes){
        $cnt_error   = intval(@$validationRes['count_error']);
        $show_err    = ($cnt_error>0)?"<a href='#' onclick='showRecords(\"error\")'>show</a>" :"&nbsp;";

        $cnt_update  = intval(@$validationRes['count_update']);
        $show_update = ($cnt_update>0)?"<a href='#' onclick='showRecords(\"update\")'>show</a>" :"&nbsp;";

        $cnt_insert  = intval(@$validationRes['count_insert']);
        $show_insert = ($cnt_insert>0)?"<a href='#' onclick='showRecords(\"insert\")'>show</a>" :"&nbsp;";
?>                    
                    <td><div class="analized">
                        <table style="display: inline-block; border:none" border="0">
                            <tr><td>Records matched:</td><td><?=$cnt_update?></td><td><?=$show_update?></td></tr>
                            <tr><td>New records to create:</td><td><?=$cnt_insert?></td><td><?=$show_insert?></td></tr>
<?php        if($sa_mode==0){ ?>                            
                            <tr><td>&nbsp;</td></tr>
<?php        } else { ?>                            
                            <tr><td>Rows with field errors:</td><td><?=$cnt_error?></td><td><?=$show_err?></td></tr>
<?php        }        ?>                            
                        </table>
                        <div style="float:right;vertical-align:middle;padding-top:10px">
                            <input type="button" value="<?=(($sa_mode==0)?'Assign IDs':'Create records')?>" onclick="doDatabaseUpdate()" style="font-weight: bold;">
                        </div>
                    </div></td>
<?php
    }else{
        print '<div style="width:500px;display:inline-block"></div>';
    }
?>                  
                    <td style="text-align:right;width:30%">  
                        <input type="button" value="Close" onClick="window.close();" style="margin-right: 5px;">
                        <input type="button" value="Clear" onclick="doClearSession(<?=$_REQUEST["import_id"]?>)">
                        <div class="help">Unprocessed data is <br>retained in buffer</div>
                    </td>
                </tr>
                </table>
        </form>    
</div>
<?php
if($validationRes){
    if($cnt_error>0){    
?>
    <div id="main_error" style="display:none;">
            <h4>RECORDS WITH FIELD ERRORS</h4>
            <hr width="100%" />
            <div>
                <font color="red"><?=@$validationRes['err_message']?></font><span class="help">&nbsp;(* indicates fields with invalid values)</span>
            </div><br />
<?php
            renderRecords( 'error', $imp_session );
            print "</div>";
    }
    if($cnt_insert>0){    
?>
    <div id="main_insert" style="display:none;">
            <h4>RECORDS TO BE CREATED</h4>
            <hr width="100%" />
<?php
            renderRecords( 'insert', $imp_session );
            print "</div>";
    }
    if($cnt_update>0){    
?>
    <div id="main_update" style="display:none;">
            <h4>RECORDS TO BE UPDATED</h4>
            <hr width="100%" />
<?php
            renderRecords( 'update', $imp_session );
            print "</div>";
    }
}


// PAGE STEP 1 ================================================================================
}else{ 
    if($imp_session!=null){
        echo "<p color='red'>ERROR: ".$imp_session."</p>";        
    }
?>
<script type="text/javascript">
//
// submit form on new file upload
//
function doUpload(){
    showProgressMsg('Please wait, file is uploading (time required will depend on your network connection speed)');
    $(document.forms[0]).hide();
    document.forms[0].submit();
}
//
// submit form on session select
//
function doSelectSession(){
    showProgressMsg('Loading selected session');
    $(document.forms[0]).hide();
    document.forms[0].submit();
}
</script>
        <h4>UPLOAD DATAFILE OR SELECT SAVED SESSION Step 1 <font color="red">Work in Progress</font></h4>
        <hr width="100%" />
        
        <form action="importCSV.php" method="post" enctype="multipart/form-data" name="upload_form">
                <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
                <input type="hidden" name="step" value="1">
            
                <div class="input-line">
                     <label>Select existing session:</label>
                     <select name="import_id" id="import_id" onchange="doSelectSession()"><?=get_list_import_sessions()?></select>
                     <input type="button" value="Clear All Sessions" style="font-weight: bold;float:right;" 
                                    onclick="doClearSession('all')">
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

//
// get file from _REQUEST and call import to db if everything is OK
//
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
            $error = postmode_file_load_to_db($_FILES['import_file']['tmp_name'], $_FILES['import_file']['name']);    
        }
    }

    return $error;
}

//
// read header (1st line)
// create temporary table import_datetime
// load file into table
// add record to import_log
//
function postmode_file_load_to_db($filename, $original) {

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
    $query = $query." PRIMARY KEY (`imp_ID`)) ENGINE=InnoDB  DEFAULT CHARSET=utf8;";

    $columns = substr($columns,0,-1);
    $counts = $counts." count(*) ";
    
    
    if (!$mysqli->query($query)) {
        return "can not create table: " . $mysqli->error;
    }    
    
    if($csv_enclosure=="'") $csv_enclosure = "\\".$csv_enclosure;
    
    //load file into table
    $query = "LOAD DATA LOCAL INFILE '".$filename."' INTO TABLE ".$import_table
        ." CHARACTER SET UTF8"
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
                     "import_name"=>($original."  ".date("Y-m-d H:i:s")),
                     "columns"=>$fields,   //names of columns in file header 
                     "uniqcnt"=>$uniqcnt,   //count of uniq values per column  
                     "mapping"=>$mapping,   //mapping of value fields to rectype.detailtype  
                     "indexes"=>array() );  //names of columns in importtable that contains record_ID
    
    return saveSession($mysqli, $session);
}

?>
