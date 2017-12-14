<?php
/**
* importCSV.php: UI for delimited text file normalising data import
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2
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

require_once(dirname(__FILE__).'/importCSV_lib.php');
set_time_limit(0);

$mysqli = mysqli_connection_overwrite(DATABASE);
mysql_connection_overwrite(DATABASE); //for getRecordInfoLibrary

if(@$_REQUEST["action"]=='step4' || @$_REQUEST["action"]=='step5'){ //case for new UI

    if(!is_admin()){
        $response = array("status"=>'denied','message'=>'');
    }else{
    
        $imp_session = get_import_session($mysqli, @$_REQUEST["imp_ID"]);
        if(!is_array($imp_session)){
            $response = array("status"=>'error', "message"=> $imp_session);
        }else{

            if ($_REQUEST["action"]=='step4') {
                $res = validateImport($mysqli, $imp_session, $_REQUEST);
            }else{
                $res = doImport($mysqli, $imp_session, $_REQUEST, 'array'); 
            }
            
            if(is_array($res)){
                $response = array("status"=>'ok', "data"=> $res);
            }else{
                $response = array("status"=>'error', "message"=>$res);
            }
        }
    }

//error_log(print_r($response,true));
    
    header('Content-type: application/json;charset=UTF-8');
    print json_encode($response);
    exit();
}//end case for new UI


$post_max_size = get_config_bytes(ini_get('post_max_size'));
$file_max_size = get_config_bytes(ini_get('upload_max_filesize'));
            

if(intval(@$_REQUEST["recid"])>0 && @$_REQUEST["table"] ){
    $res = get_import_value($_REQUEST["recid"], $_REQUEST["table"]);
    header('Content-type: text/javascript');
    print json_encode($res);
    exit();
} else
    if( @$_REQUEST["clearsession"] ){
        clear_import_session($_REQUEST["clearsession"]);
        exit();
    } else
        if( @$_REQUEST["getsession"] ){ //download session
            download_import_session($_REQUEST["getsession"], @$_REQUEST["idfield"], @$_REQUEST["mode"]);
            exit();
        } else
            if( @$_REQUEST["deleteunmatched"] ){ //download session
                delete_unmatched_records($_REQUEST["deleteunmatched"], @$_REQUEST["idfield"]);
                exit();
            }


?>

<html>

    <head>
        <title>Normalising importer for comma or tab delimited text files</title>

        <meta http-equiv="content-type" content="text/html; charset=utf-8">

        <link rel="stylesheet" type="text/css" href="../../ext/jquery-ui-themes-1.12.1/themes/heurist/jquery-ui.css" />
        
        <script type="text/javascript" src="../../ext/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="../../ext/jquery-ui-1.12.1/jquery-ui.js"></script>
        
        <script type="text/javascript" src="../../ext/jquery-file-upload/js/jquery.iframe-transport.js"></script>
        <script type="text/javascript" src="../../ext/jquery-file-upload/js/jquery.fileupload.js"></script>
        
        <link rel=stylesheet href="../../common/css/global.css" media="all">

        <style type="text/css">
            .show a {
                background-image: url("../../common/images/tdown.gif") !important;
            }
            .togglepnl a {
                background-image: url("../../common/images/tright.gif");
                background-repeat: no-repeat;
                background-position: left;
                cursor: pointer;
                font-size: 1.1em;
                text-decoration:none;
                padding-left:20px;
            }
            .hidden{
                display:none;
            }
            .tbmain th
            {
                border-left: 1px solid lightgray;
                border-bottom: 1px solid gray;
            }
            .subh
            {
                border-left: 1px solid lightgray;
                border-bottom: 1px solid lightgray;
                border-top: 1px solid lightgray;
            }
            .tbmain td
            {
                border-left: 1px solid lightgray;
            }
            .tbmain
            {
                border-top: 1px solid gray;
                border-right: 1px solid gray;
                border-bottom: 1px solid gray;
                border-left: 1px solid gray;
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
            div.analized, div.analized2{
                /*background-color:#DDD;*/
                border:gray solid 1px;
                display:inline-block;
                padding:5px;
                min-width:250px;
            }
            div.header{
                display:inline-block;
                font-weight: bold;
            }
            .truncate {
                max-width: 100ex;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .required {
                font-weight: bold;
                color:red;
            }
            .help{
                font-size:0.9em;
            }
            
            .ui-progressbar {
                position: relative;
                width:100%;   
                height: 18px;                
            }
            .progress-label {
                position: absolute;
                left: 50%;
                top: 4px;
                font-weight: bold;
                text-shadow: 1px 1px 0 #fff;
            }            
            
        </style>
    </head>

    <body class="popup">
    
        <script type="text/javascript">

            var currentDb = "<?=HEURIST_DBNAME?>";
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
                    msg = "Clear all uploaded files?";
                }else{
                    msg = "Clear uploaded files?";
                }

                if( confirm(msg) ) {

                    $.ajax({
                        url: top.HEURIST.baseURL+'import/delimited/importCSV.php',
                        type: "POST",
                        data: {clearsession: session_id, db:currentDb},
                        dataType: "json",
                        cache: false,
                        error: function(jqXHR, textStatus, errorThrown ) {
                            alert('Error connecting to server. '+textStatus);
                        },
                        success: function( response, textStatus, jqXHR ){
                            if(response=="ok"){
                                if(session_id=='all'){
                                    $("#import_id").empty();
                                    $("#btnClearUploaded").prop('disabled', 'disabled');
                                    $("#btnClearUploaded").css('color','lightgray');
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
            //
            // submit form for second attempt of file upload
            //
            function doUpload2(){
                document.forms[0].submit();
                hideThisFrame();
            }


            //
            // it calls before each submit
            //
            function hideThisFrame(){

                if(window.frameElement){
                    var reference_to_parent_dialog = window.frameElement.getAttribute('parent-dlg-id');
                    if( reference_to_parent_dialog ){
                            var frame = window.frameElement;
                            //$(reference_to_parent_dialog).css('background','green');
                            var ele = parent.document.getElementById(reference_to_parent_dialog);
                            $(ele).addClass('loading');
                            $(frame).hide();
                    }
                }

                return true;
            }
        </script>
        <?php
        //USER_ID:=get_user_id()
        $imp_session = null;

        $sa_mode = @$_REQUEST['sa_mode'];
        $step = intval(@$_REQUEST["step"]);
        if(!$step) $step=0;

/* ART 2016-02-01
        ob_start();
        echo '<div id="div-progress" style="display:none" class="loading">&nbsp;</div>';
        ob_flush();flush();
*/
        //load session
        if(intval(@$_REQUEST["import_id"])>0){
            ob_start();
            echo '<script>showProgressMsg("Please wait, processing ... ")</script>';
            ob_flush();flush();
            $imp_session = get_import_session($mysqli, $_REQUEST["import_id"]);
        }

        //first step - load file into import table
        if($step==1 && $imp_session==null){
            ob_start();
            echo '<script>showProgressMsg("Please wait, file is processing on server")</script>';
            ob_flush();flush();

            if(@$_REQUEST["upload_file_name"]){ //load file into db
                $imp_session = postmode_file_load_to_db(HEURIST_FILESTORE_DIR.'scratch/'.$_REQUEST["upload_file_name"], 
                        $_REQUEST["upload_file_name"], true);
            }else if(@$_REQUEST["filename"]){ //after postprocessing - additional step load from temp file
                $imp_session = postmode_file_load_to_db($_REQUEST["filename"], $_REQUEST["original"], (@$_REQUEST["preprocess"]=="1"));
            }else{
                $imp_session = postmode_file_selection();
            }
            ?>

            <script type="text/javascript">
                $(document).ready( function(){ 
                    $("#div-progress").hide(); 
                });

                //
                // reload
                //
                function doReload(){
                    $("#step").val(0);
                    doUpload2();
                }
            </script>

            <?php
            if(is_array($imp_session) && (@$imp_session['errors'] || @$imp_session['err_encoding']) ){ //preprocessing

                if(count(@$imp_session['errors'])>0){

                    $col_count = $imp_session['col_count'];
                    $errors = $imp_session['errors'];
                    $fields = $imp_session['fields'];

                    print "<h4>ERROR. Wrong field count in parsed data. Expected field count:&nbsp;&nbsp;".$col_count." </h4>";
                    print "<div>Header: ".implode(", ", $fields)."</div>";
                    print "<hr width='100%' />";

                    print "<table><tr><th>Line#</th><th>Field count</th><th>Raw data</th></tr>";

                    foreach($errors as $err){
                        print "<tr><td>".$err['no']."</td><td>".$err['cnt']."</td><td>".htmlspecialchars($err['line'])."</td></tr>";
                    }
                    print "</table>";

                }
                if(count(@$imp_session['err_encoding'])>0){

                    $errors = $imp_session['err_encoding'];

                    print "<h4>ERROR. Wrong encoding detected in import file. At least ".count($errors)." lines have such issue. Please save in UTF8.</h4>";
                    print "<hr width='100%' />";

                    print "<table><tr><th>Line#</th><th>Raw data</th></tr>";

                    foreach($errors as $err){
                        print "<tr><td>".$err['no']."</td><td>".htmlspecialchars($err['line'])."</td></tr>";
                    }
                    print "</table>";

                }
                ?>

                <div class="actionButtons" >
                    <input type="button" value="Cancel"
                        onClick="{window.location.href='importCSV.php?db=<?=HEURIST_DBNAME?>'}"
                        style="margin-right: 10px;">
                </div>

            </body>
        </html>

        <?php
        exit();

    }else if(is_array($imp_session) && @$imp_session['fatal_error']){ //preprocessing
        ?>
        <p style='color:red'>ERROR: <?=$imp_session['fatal_error']?></p>
        <hr width="100%" />
        <div class="actionButtons" >
            <input type="button" value="Cancel"
                onClick="{window.location.href='importCSV.php?db=<?=HEURIST_DBNAME?>'}"
                style="margin-right: 10px;">
        </div>
        </body>
        </html>

        <?php
        exit();

    }else if(is_array($imp_session) && @$imp_session['warning']){ //preprocessing
        ?>

        <h4><?=$imp_session['warning']?></h4>
        <hr width="100%" />

        <form action="importCSV.php" method="post" enctype="multipart/form-data" name="upload_form" onsubmit="hideThisFrame()">
            <!--input type="hidden" name="DBGSESSID" value="423973326605900002;d=1,p=0,c=0" -->
            <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
            <input type="hidden" id="step" name="step" value="1">
            <input type="hidden" name="filename" value="<?=$imp_session['filename']?>">
            <input type="hidden" name="original" value="<?=$imp_session['original']?>">

            <input type="hidden" name="csv_delimiter" value="<?=$_REQUEST['csv_delimiter']?>">
            <input type="hidden" name="csv_linebreak" value="<?=$_REQUEST['csv_linebreak']?>">
            <input type="hidden" name="csv_enclosure" value="<?=$_REQUEST['csv_enclosure']?>">
            <input type="hidden" name="csv_dateformat" value="<?=$_REQUEST['csv_dateformat']?>">
            <input type="hidden" name="csv_mvsep" value="<?=$_REQUEST['csv_mvsep']?>">
            <input type="hidden" name="csv_encoding" value="<?=$_REQUEST['csv_encoding']?>">

            <?php
            $fields = @$imp_session['fields'];
            if($fields){
                $k=0;
                ?>

                <div>Please select input columns that contain dates which may contain human-friendly dates such as 1827, 14 sep 1827, 14th Sept 1827.<br />
                    These columns will be parsed to extract conistently formatted date fields.<br />
                    Date fields in standard format (dd-mm-yyyy, mm-dd-yyyy as selected, or ISO standard YYYY-MM-DD) will be handled automatically.<br />
                    <br /><br />
                    <input type="button" value="Cancel" onClick="doReload();" style="margin-left: 70px; margin-right: 10px;">
                    <input type="button" value="Continue" style="font-weight: bold;" onclick="doUpload2()">
                </div>

                <div class="actionButtons" >
                </div>

                <table>
                <?php
                foreach($fields as $field){
                    ?>
                    <tr>
                        <td width=50px;></td>
                        <td width=30px; align="center"><input type="checkbox" id="d_field_<?=$k?>" name="datefield[]" value="<?=$k?>"/></td>
                        <td><label><?=$field?></td>
                    </tr>
                    <?php
                    $k++;
                }
                print '</table>';
            }else{
                print '<input type="hidden" name="preprocess" value="1">';
            }
            ?>

        </form>
        </body>
        </html>

        <?php
        exit();
    }
}

//session is loaded - render second step page
if(is_array($imp_session)){

    ?>
    <script src="../../common/php/loadCommonInfo.php?db=<?=HEURIST_DBNAME?>"></script>
    <script src="../../common/js/utilsUI.js"></script>
    <script>
        var currentId = 1;
        var recCount = <?=$imp_session['reccount']?$imp_session['reccount']:0?>;
        var currentTable = "<?=$imp_session['import_table']?>";
        var currentSessionName = "<?=$imp_session['import_name']?>";
        var form_vals = <?=($step>1)?json_encode($_REQUEST):"{}"?>;
    </script>
    <script src="importCSV.js"></script>

    <?php

    $mode_import_result = "";
    if($step>1){
        $res = null;

        if($sa_mode==0){ //matching

            if($step==2){  //find  - NOT USED ANYMORE  - we trying to assign IDs at once
                // ARTEM TODO: REMOVE REDUNDANT CODE

                ob_start();
                echo '<script>showProgressMsg("Please wait, matching in progress")</script>';
                ob_flush();flush();

                $res = matchingSearch($mysqli, $imp_session, $_REQUEST);


            }else if($step==3){  //assign ids

                ob_start();
                echo '<script>showProgressMsg("Please wait, assign of records ids")</script>';
                ob_flush();flush();

                $res = assignMultivalues($mysqli, $imp_session, $_REQUEST);
                //NOT USED ANYMORE $res = matchingAssign($mysqli, $imp_session, $_REQUEST);

                if(is_array($res) && count(@$res['validation']['disambiguation'])>0){
                    //There is ambiguity with your matching criteria. Please resolve it before proceeding further
                    ?>

                    <script>
                        form_vals["error_message"] = "One or more rows in your file match multiple records in the database. "+
                        "Please click on \"Rows with ambiguous match\" to view and resolve these ambiguous matches.<br><br> "+
                        "If you have many such ambiguities you may need to select adidtional key fields or edit the incoming "+
                        "data file to add further matching information.";
                    </script>

                    <?php
                }else{
                    print '<script>form_vals["auto_switch_to_import"] = "1";</script>';
                    //$sa_mode = 1; //ART switch to import  autimatically???
                }
            }
        }else{//importing

            if($step==2){  //verification

                ob_start();
                echo '<script>showProgressMsg("Please wait, mapping validation in progress")</script>';
                ob_flush();flush();

                $res = validateImport($mysqli, $imp_session, $_REQUEST);

            }else if($step==3){  //create records - load from import data to database
                $mode_import_result = ' style="display:none"';
                ?>

                <div id="main_import_result">
                    <h4>IMPORT DATA RESULTS</h4>
                    <hr width="100%" />
                    <div id="div-progress2"></div>
                    <div>
                        <?php
                        ob_flush();flush();

                        $res = doImport($mysqli, $imp_session, $_REQUEST, 'html');
                        ?>
                    </div>
                    <br /><br />
                    <input type="button"
                value="&lt;&lt; Back to start" onClick="{window.location.href='importCSV.php?db=<?=HEURIST_DBNAME?>'}"
                style="margin-right: 10px; margin-left:20px;"
                title="Return to the upload screen to select a new delimited file to upload to the server for processing">
                </div><!-- main_import_result -->
                <?php
            }

        }
        if(is_array($res)) {
            $imp_session = $res;
        }else if($res && !is_array($res)){
            print '<script>form_vals["error_message"] = "'.$res.'";</script>';
            //echo "<p style='color:red'>ERROR: ".$res."</p>";
        }
    }

    $len = count($imp_session['columns']);

    ?>
    <form action="importCSV.php" method="post" enctype="multipart/form-data" name="import_form" onsubmit="return verifySubmit()">
    <!-- input type="hidden" name="DBGSESSID" value="423973326605900002;d=1,p=0,c=0" -->
    <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
    <input type="hidden" name="step" id="input_step" value="2">
    <input type="hidden" id="ignore_insert" name="ignore_insert" value="<?=@$_REQUEST["ignore_insert"]?>">
    <input type="hidden" id="mvm" name="mvm" value="0">
    <input type="hidden" id="multifield" name="multifield" value="">
    <input type="hidden" id="import_id" name="import_id" value="<?=$imp_session["import_id"]?>">
    <input type="hidden" id="csv_enclosure" name="csv_enclosure" value="<?=@$imp_session["csv_enclosure"]?>">
    <input type="hidden" id="csv_mvsep" name="csv_mvsep" value="<?=@$imp_session["csv_mvsep"]?>">


    <!-- ----------------- STEP 2: DISPLAY FIELDS FOR MATCHING -------------- -->

    <div id="main_mapping"<?=$mode_import_result?>>

        <div  style="padding-bottom:10px">

            <input type="button"
                value="&lt;&lt; Back to start" onClick="{window.location.href='importCSV.php?db=<?=HEURIST_DBNAME?>'}"
                style="margin-right: 10px; margin-left:20px;"
                title="Return to the upload screen to select a new delimited file to upload to the server for processing">
            <?php if(@$_REQUEST["import_id"]){ ?>
                <input type="button"
                    value="Download data to file"
                    onclick="window.open('importCSV.php/import.csv?db=<?=HEURIST_DBNAME?>&getsession=<?=@$_REQUEST["import_id"]?>','_blank')"
                    title="Download the data as currently displayed (including matching/IDs) to a new delimited file for further desktop editing">
                <div class="help" style="float:right;min-width:300px">
                    <span class="help">
                        Note: Data is retained between sessions until cleared
                    </span>
                    <input type="button"
                        value="Clear uploaded file" onclick="doClearSession(<?=@$_REQUEST["import_id"]?>)" style="margin-right: 10px;"
                        title="Clear the data for this uploaded file from the server">
                </div>
                <?php } ?>
        </div>

        <div class="help" style="padding-left: 20px;">
            Please visit <a target="_blank" href="http://HeuristNetwork.org/archive/importing-data">
                <b>HeuristNetwork.org/archive/importing-data</b></a> for an overview explanation and examples of record import.
        </div>
        <div class="help togglepnl show" style="width:620px;padding-bottom:2em" id="div_help_top2">
            <a onMouseDown="$('#div_help_top2').toggleClass('show'); $('#div_help_details2').toggleClass('hidden');">
                If the spreadsheet data is complex, this function will allow you to progressively import sets of columns which identify subsidiary entities (record types) such as places, organisations, collections, series, artists etc.
            </a>
        </div>
        <div class="help" id="div_help_details2">
            <b>Workflow:</b><br /><br />
            &nbsp;&nbsp;&nbsp;1: Choose a record type and match one or more key fields in order to: <br />
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;a. identify rows which belong to existing records (sets record ID value);<br />
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;b. create new records from unmatched rows (creates new record ID value). <br />
            &nbsp;&nbsp;&nbsp;2: The record ID value is used to import values in additional columns into the appropriate records.<br />
            &nbsp;&nbsp;&nbsp;3: The record ID column(s) subsequently supply value(s) for appropriate pointer fields in importing additional record types.<br />
            <br/>
        </div>

        <?php
        if(@$imp_session['load_warnings']){
            print "<div style='padding:20px;'><span style='color:red;font-size:14px; font-weight:bold'>"
            ."Warning: there are errors in the data read</span>&nbsp;"
            .$imp_session['load_warnings'][0]."&nbsp;&nbsp;<a href='#' onclick='showRecords(\"load_warnings\")'><b>show errors &gt;</b></a></div>";
        }
        ?>

        <input type="hidden" value="<?=$sa_mode?>" name="sa_mode" id="sa_mode"/>

        <b>Rows in memory: <?=$imp_session['reccount']?>&nbsp;&nbsp;Fields:&nbsp;<?=$len?></b>

        <br /><br />


        <!-- ACTIONS box -->

        <table style="vertical-align:middle;border:solid blue 2px;background-color:#EDF5FF;padding:5px" class="ui-corner-all">
            <tr>
                <td colspan="3" style ="font-size: 0.75em;">
                    <b>Actions</b> &nbsp; (make selections below before clicking)
                </td>
            </tr>

            <tr>
                <td colspan="3">
                    &nbsp;
                </td>
            </tr>

            <tr style="margin-top: 15px; margin-bottom: 20px;;">
                <td>
                    <span class="matching">
                        <span>
                            <input type="button" value="Import as new (skip matching)"
                                disabled=disabled  id="btnSkipMatch"
                                onclick='{$( "#tabs_actions" ).tabs( "option", "active", 1 );}' style="font-weight: bold;color:#CCC">

                        </span>
                        <span>
                            <input type="button" value="Match against existing records &gt;&gt;"
                                disabled=disabled  id="btnStartMatch"
                                onclick="doMatching()" style="font-weight: bold;color:#CCC">

                        </span>
                    </span>
                    <span class="importing" id="divPreviousBtn">
                        <input type="button" value="&lt;&lt; BACK: Match again" style="font-weight: bold;"
                            title="Go to initial (Matching) step" onclick='$( "#tabs_actions" ).tabs( "option", "active", 0 );' >
                    </span>
                </td>

                <?php
                //
                // render validation result box
                //

                $validationRes = @$imp_session['validation'];
                $cnt_update = 0;
                $cnt_insert = 0;
                if($validationRes){
                    $cnt_error   = intval(@$validationRes['count_error']);
                    $show_err    = ($cnt_error>0)?"<a href='#' onclick='showRecords(\"error\")'>show</a>" :"&nbsp;";

                    $cnt_disamb   = count(@$validationRes['disambiguation']);
                    $show_disamb    = ($cnt_disamb>0)?"<a href='#' onclick='showRecords(\"disamb\")'>show</a>" :"&nbsp;";


                    $url = 'importCSV.php/import.csv?db='.HEURIST_DBNAME.'&getsession='.@$_REQUEST["import_id"].'&idfield='.@$_REQUEST["recid_field"].'&mode=';

                    $cnt_update  = intval(@$validationRes['count_update']);
                    $cnt_update_rows  = intval(@$validationRes['count_update_rows']);
                    $show_update = ($cnt_update>0)?"<a href='#' onclick='showRecords(\"update\")'>show</a>" :"&nbsp;";
                    $download_update= ($cnt_update>0)?"<a href='#' onclick='window.open(\"".$url."0\" ,\"_blank\")'>download</a>" :"&nbsp;";

                    $cnt_insert  = intval(@$validationRes['count_insert']);
                    $cnt_insert_rows  = intval(@$validationRes['count_insert_rows']);
                    if($cnt_insert>0){
                        $show_insert = "<a href='#' onclick='showRecords(\"insert\")' title='show rows to be inserted as new records'>show</a>";
                        $download_insert= "<a href='#' onclick='window.open(\"".$url."1\" ,\"_blank\")'>download</a>"
                        .' &nbsp;&nbsp; <input type="checkbox" id="ignore_insert" '
                        .' onclick="{document.getElementById(\'ignore_insert\').value=this.checked?1:0; }">'
                        .'<label for="ignore_insert" title="Do not insert new records. Do update only">ignore</label>';
                    }else{
                        $show_insert = "&nbsp;";
                        $download_insert= "&nbsp;";
                    }

                    $cnt_insert_nonexist_id  = intval(@$validationRes['count_insert_nonexist_id']);

                    ?>
                    <td><div class="analized2">
                            <table style="display: inline-block; border:none" border="0">
                                <?php        if($sa_mode==0){ ?>
                                    <tr><td width="130">Records matched</td>
                                        <td><?=$cnt_update?></td>
                                        <td> &nbsp;&nbsp;&nbsp;&nbsp;rows:</td>
                                        <td width="40"><?=$cnt_update_rows?></td>
                                        <td width="50"><?=$show_update?></td>
                                        <td width="120"><?=$download_update?></td>
                                    </tr>
                                    <tr><td>New records to create</td>
                                        <td><?=$cnt_insert?></td>
                                        <td>&nbsp;&nbsp;&nbsp;&nbsp;rows:</td>
                                        <td><?=$cnt_insert_rows?></td>
                                        <td><?=$show_insert?></td>
                                        <td><?=$download_insert?></td>
                                    </tr>

                                    <tr><td><font<?=($cnt_disamb>0?" color='red'":'')?>>Ambiguous matches</font></td>
                                        <td>&nbsp;</td>
                                        <td>&nbsp;&nbsp;&nbsp;&nbsp;rows:</td>
                                        <td><?=$cnt_disamb?></td>
                                        <td><?=$show_disamb?></td>
                                    </tr>
                                    <?php        } else {

                                    ?>
                                    <tr><td>Rows with valid values:</td>
                                        <td><?=($cnt_insert + $cnt_update)?></td><td><?=$show_update?></td><td><?=$show_insert?"new: ".$show_insert:""?></td></tr>
                                    <tr><td>Rows with field errors:</td><td><?=$cnt_error?></td><td><?=$show_err?></td><td></td></tr>
                                    <?php        }        ?>
                            </table>

                            <div style="float:right;vertical-align:middle;padding-top:10px">
                                <?php if($sa_mode==1){  ?>
                                    <!-- <span class="importing">
                                    <input type="button" value="<?=($cnt_update>0 && $cnt_insert>0)?"Create/Update":($cnt_insert>0?"Create":"Update")?> records"
                                    onclick="doDatabaseUpdate(<?=$cnt_insert_nonexist_id?>, <?=$cnt_error?>)" style="font-weight: bold;"></span>
                                    -->
                                    <?php }
                                if($cnt_disamb>0){
                                    ?>
                                    <span class="matching">
                                    <input type="button" value="Resolve ambiguous matches"
                                        onclick="showRecords('disamb')" style="font-weight: bold;">
                                    <?php }else{ ?>
                                    <span class="matching">
                                        <input type="button" value="Insert/Update step>>" title="Go to Import step"
                                            onclick='$( "#tabs_actions" ).tabs( "option", "active", 1 );' style="font-weight: bold;"></span>
                                    <?php
                                }
                                ?>
                            </div>
                        </div>
                    </td>

                    <?php
                }else{
                    print '<td></td>';
                }
                ?>

                <td>
                    <span class="importing">
                        <?php
                        //($cnt_update>0 && $cnt_insert>0)?"Create/Update":($cnt_insert>0?"Create":"Update")
                        if($cnt_update+$cnt_insert>0){  ?>
                            <span class="analized2">
                                <input type="button" value="NEXT: Create/Update records &gt;&gt;"
                                    onclick="doDatabaseUpdate(<?=$cnt_insert_nonexist_id?>, <?=$cnt_error?>)" style="font-weight: bold;"></span>
                            <?php } ?>
                        <span class="importing analized3">
                            <input  id="btnStartImport" type="submit"
                                value="NEXT: Prepare insert/update &gt;&gt;" style="disabled:disabled;font-weight: bold;">
                        </span></span>
                </td>
            </tr>

            <tr>
                <td colspan="3">
                    &nbsp;
                </td>
            </tr>

        </table> <!-- End of ACTIONS box -->

        <br />


        <!-- SELECT RECORD TYPE TO IMPORT -->
        <div class="input-line" style="margin-top: 15px; margin-bottom: 5px;">
            <div class="header"><label for="sa_rectype" style="color:red">Select record type&nbsp;</label></div>
            <select name="sa_rectype" id="sa_rectype" class="text ui-widget-content ui-corner-all" style="min-width:150px"></select>
            <span class="help">
                &nbsp;&nbsp;&nbsp;&nbsp; If a record type is not shown in the pulldown, check the 'Show' column in Database &gt; Manage Structure
            </span>
        </div>


        <!--IMPORT STEPS -->

        <div id="tabs_actions">
            <ul>
                <li><a  style="background-color:F7F7F7;" href="#matching">Search / match</a></li>
                <li><a  style="background-color:F7FFFF;" href="#import">Insert / update records</a></li>
            </ul>

            <!-- *** SEARCH/MATCH TAB ********************************************************************************* -->

            <div id="matching" style="background-color:F7F7F7;">
                <div id="div_idfield" style="padding-left:30px;display:none;">
                    <span class="help"><b>Key fields:</b> Select one or more of the input columns to match input rows with records in the database.<br />
                        Matched rows will be identified by record ID. Unmatched rows will be marked for creation of new records.
                        <br/><br/></span>
                    <label for="idfield">Column name to hold record IDs</label>
                    <input id="idfield" name="idfield" type="text" size="20" />&nbsp;&nbsp;&nbsp;
                    <span class="help">
                        You may wish to change the column name to identify a specific use of the entities
                        represented by the key columns selected eg. author ID rather than person ID
                    </span>
                </div>

                <!-- table showing input columns and controls for selection and matching -->
                <div>
                    <br/>
<?php
//DEBUG echo print_r($imp_session['multivals'],true)
?>
                    <table class="tbmain" style="width:100%" cellspacing="0" cellpadding="2">
                        <thead><tr> <!-- Table headings -->
                            <th style="width:40px;">Key<br/>field&nbsp;</th>
                            <th style="width:50px;">Unique<br/>values</th>
                            <th style="width:200px;">Column</th>
                            <th style="width:310px;">Mapping</th>
                            <!-- last column allows step through imported data records-->
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
                        // render table allowing contextual selection of key fields for matching
                        //
                        for ($i = 0; $i < $len; $i++) {
                            // Keyfield selection
                            print '<td align="center">&nbsp;<span style="display:none;"><input type="checkbox" id="cbsa_keyfield_'
                            .$i.'" value="field_'.$i.'" onchange="{showHideSelect2('.$i.');}" '
                            .(in_array($i, $imp_session['multivals'])?'multivalue="yes"':'').' column="'.$imp_session['columns'][$i].'"'
                            .'/></span></td>';
                            // Unique values
                            print '<td align="center">'.$imp_session['uniqcnt'][$i].'</td>';
                            // input column name
                            print '<td style="padding-left:15px;" class="truncate">'.$imp_session['columns'][$i].'</td>';
                            // Select mapping for imported column, initially hidden until keyfield checkbox selected
                            print '<td style="width:306px;">&nbsp;<span style="display:none;">'
                            .'<select name="sa_keyfield_'.$i.'" id="sa_keyfield_'.$i.'" style="max-width:260px;" onchange="{onFtSelect2('.$i.');}">'
                            . '</select></span></td>';
                            // Imported data value for column
                            print '<td id="impval_'.$i.'" style="text-align: left;padding-left: 16px;"> </td></tr>';
                        }
                        ?>
                    </table>
                </div>

            </div>


            <!-- *** INSERT/UPDATE TAB ********************************************************************************* -->

            <div id="import" style="background-color:F7FFFF;">

                <div>
                    <label for="recid_field" style="color:red; font-size: small;">Record ID column</label>
                    <select id="recid_field" name="recid_field" onchange="{onRecIDselect2()}">
                        <option value="">select...</option>
                        <?php
                        //created ID fields
                        $hasc = false;
                        for ($i = 0; $i < $len; $i++) {
                            $rectype = @$imp_session['indexes']["field_".$i];
                            $isIndex = ($rectype!=null);
                            if($isIndex){
                                $hasc = true;
                                print '<option class="idfield_'.$rectype.'" value="field_'.$i.'">'.$imp_session['columns'][$i].'</option>';
                            }
                        }
                        if($hasc)
                            print '<option id="idfield_separator" value="0" disabled="disabled" >-------</option>';
                        //all other fields
                        for ($i = 0; $i < $len; $i++) {
                            $rectype = @$imp_session['indexes']["field_".$i];
                            $isIndex = ($rectype!=null);
                            if(!$isIndex){
                                print '<option value="field_'.$i.'">'.$imp_session['columns'][$i].'</option>';
                            }
                        }
                        ?>
                    </select>
                    <span class="help">
                        Choose the column containing the record identifier for the records to be inserted / updated
                    </span>
                </div>

                <br />

                <div style="padding-left:30px;">
                    <div style="padding-left:30px;">
                        <input type="radio" <?=@$_REQUEST['sa_upd']>0?"":"checked"?> name="sa_upd" id="sa_upd0" value="0" class="text"
                            onchange="{onUpdateModeSet()}"/>&nbsp;
                        <label for="sa_upd0">Retain existing values and append distinct new data as repeat values
                            (existing values are not duplicated)</label><br/>

                        <input type="radio" <?=@$_REQUEST['sa_upd']==1?"checked":""?> name="sa_upd" id="sa_upd1" value="1" class="text"
                            onchange="{onUpdateModeSet()}"/>&nbsp;
                        <label for="sa_upd1">Add new data only if field is empty (new data ignored for non-empty fields)</label><br/>

                        <input type="radio" <?=@$_REQUEST['sa_upd']==2?"checked":""?> name="sa_upd" id="sa_upd2" value="2" class="text"
                            onchange="{onUpdateModeSet()}" />&nbsp;
                        <label for="sa_upd2">Add and replace all existing value(s) for the record with new data</label>
                    </div>
                    <div style="padding-left:60px;font-size:0.9em;vertical-align: top;
                        display:<?=(@$_REQUEST['sa_upd']==2?"block":"none")?>" id="divImport2">
                        <input type="radio" <?=@$_REQUEST['sa_upd2']>0?"":"checked"?> name="sa_upd2" id="sa_upd20" value="0" class="text" />&nbsp;
                        <label for="sa_upd20" style="font-size:0.9em;">Retain existing if no new data supplied for record</label><br/>

                        <input type="radio" <?=@$_REQUEST['sa_upd2']==1?"checked":""?> name="sa_upd2" id="sa_upd21" value="1" class="text" />&nbsp;
                        <label for="sa_upd21" style="font-size:0.9em;">Delete existing even if no new data supplied for record</label>
                    </div>
                </div>

                <br/>

                <span>
                    Choose the data fields to be updated from the table below<br/><br/>
                </span>


                <table class="tbmain" style="width:100%" cellspacing="0" cellpadding="2">
                    <thead><tr> <!-- Table headings -->
                        <th style="width:40px;">Import&nbsp;<br/>value</th>
                        <th style="width:50px;">Unique&nbsp;<br/>values</th>
                        <th style="width:200px;">Column</th>
                        <th style="width:310px;">Mapping</th>
                        <!-- last column allows step through imported data records-->
                        <th width="300px" style="text-align: left;padding-left: 16px;">
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

                        // set up checkboxes for selection of fields to insert / update
                        if($isProcessed){
                            $checkbox = '<td align="center"><input type="checkbox" disabled="disabled" /></td>'; //processed
                        }else{
                            $rectype = @$imp_session['indexes']["field_".$i];
                            $isIndex = ($rectype!=null);
                            $checkbox = '<td align="center">&nbsp;<span style="display:none;"><input type="checkbox" id="cbsa_dt_'
                            .$i.'" onchange="{showHideSelect('.$i.');}"/></span></td>';
                        }

                        $s = '<tr>'.$checkbox;

                        // count of unique values
                        $s = $s.'<td align="center">'.$imp_session['uniqcnt'][$i];

                        // column names
                        $s = $s.'</td><td style="padding-left:15px; class="truncate">'.$imp_session['columns'][$i].'</td>';

                        // mapping
                        if($isProcessed){ // already selected
                            $rt_dt = explode(".",$isProcessed);
                            $recTypeName = $recStruc['names'][$rt_dt[0]]; //['commonFields'][ $idx_rt_name ];
                            $dt_Name = intval($rt_dt[1])>0?$recStruc['typedefs'][$rt_dt[0]]['dtFields'][$rt_dt[1]][$idx_dt_name]:$rt_dt[1];
                            $s = $s.'<td class="truncate">'.$recTypeName.' '.$dt_Name.'  ('.$rt_dt[0].'.'.$rt_dt[1].')</td>';
                        }else{ // to select
                            $s = $s.'<td style="width:306px;">&nbsp;<span style="display:none;">'
                            .'<select name="sa_dt_'.$i.'" id="sa_dt_'.$i.'" style="max-width:260px" onchange="{onFtSelect('.$i.');}"'
                            . ($isIndex?'class="indexes"':'').'></select></span></td>';
                        }

                        // imported value for current example record]
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
                        print '<tr height="40"; style="valign:bottom"><td class="subh" colspan="4"><br /><b>Record IDs</b></td>'
                        .'<td class="subh"><br />Negative value indicates new record to be created</td></tr>'.$sIndexes;
                    }
                    if($sRemain){
                        print '<tr height="40"; style="valign:bottom"><td class="subh" colspan="6"><br /><b>Remaining Data</b></td></tr>'.$sRemain;
                    }
                    if($sProcessed){
                        print '<tr height="40"; style="valign:bottom"><td class="subh" colspan="6"><br /><b>Already imported</b></td></tr>'.$sProcessed;
                    }

                    ?>
                </table>

            </div>      
        </div>

    </div>

    <?php
    //Warnings that SQL command LOAD DATA generates
    if(@$imp_session['load_warnings']){
        ?>
        <div id="main_load_warnings" style="display:none;">
        <h4>WARNINGS ON LOAD DATA</h4>
        <hr width="100%" />
        <?php
        renderWarnings( $imp_session );
        print "</div>";
    }

    if($validationRes){
        if($cnt_error>0){
            ?>
            <div id="main_error" style="display:none;">
            <h4>ROWS WITH FIELD ERRORS</h4>
            <hr width="100%" />
            <div>
            </div>

            <br />

            <?php
            //<span class="help">&nbsp;(* indicates fields with invalid values)</span>
            //                <font color="red"><@$validationRes['err_message']</font>

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

        if($cnt_disamb>0){
            ?>

            <div id="main_disamb" style="display:none;">
            <h4>DISAMBIGUATION</h4>
            <hr width="100%" />
            <?php
            renderDisambiguation( 'disamb', $imp_session );
            print "</div>";
        }
    }

    print '</form>';

    // PAGE STEP 1 ================================================================================
}else{
    if($imp_session!=null){
        echo "<p style='color:red'>ERROR: ".$imp_session."</p>";
    }
    ?>

    <script type="text/javascript">

        $( function(){
                $("#div-progress").hide();

                //change title in parent dialog
                if(window.frameElement){
                    var reference_to_parent_dialog = window.frameElement.getAttribute('parent-dlg-id');
                    if( reference_to_parent_dialog ){ 
                        var ele = parent.document.getElementById(reference_to_parent_dialog);
                        $(ele.parentElement).find('.ui-dialog-title').text( 'Import delimited text (csv, tsv)' );
                        //dialog( "option", "title", 'Import delimited text (csv, tsv)');
                    }
                }
                
                if($('#import_id > option').length<2){
                    $("#btnClearUploaded").prop('disabled', 'disabled');    
                    $("#btnClearUploaded").css('color','lightgray');
                }
                
                var uploadData = null;
                var pbar_div = $('#progressbar_div');
                var pbar = $('#progressbar');
                var progressLabel = pbar.find('.progress-label').text('');
                pbar.progressbar({value:0});
                
                $('#progress_stop').button().on({click: function() {
                       if(uploadData && uploadData.abort) uploadData.abort();
                }});
                
                var max_file_size = <?php echo $file_max_size;?>;
                var max_post_size = <?php echo $post_max_size;?>;
                var max_size = Math.min(max_file_size,max_post_size);
                
                var uploadWidget = $('#upload_file');
                
                uploadWidget.fileupload({
        url: window.hWin.HAPI4.baseURL +  'hserver/utilities/fileUpload.php', 
        formData: [ {name:'db', value: window.hWin.HAPI4.database}, //{name:'DBGSESSID', value:'424533833945300001;d=1,p=0,c=0'},
                    {name:'max_file_size', value: max_size},
                    {name:'entity', value:'temp'}],  //just place file into scratch folder
        //acceptFileTypes: /(\.|\/)(gif|jpe?g|png)$/i,
        autoUpload: true,
        sequentialUploads:false,
        dataType: 'json',
        //dropZone: $input_img,
        add: function (e, data) {  
        
            uploadData = data;//keep this object to use conviniece methods (abort for instance)
            data.submit(); 

        },
        //send: function (e, data) {},
        //start: function(e, data){},
        //change: function(){},
        error: function (jqXHR, textStatus, errorThrown) {
            $('#upload_form_div').show();
            pbar_div.hide();
            if(textStatus!='abort'){
                window.hWin.HEURIST4.msg.showMsgErr(textStatus+' '+errorThrown);
            }
        },
        done: function (e, response) {

                $('#upload_form_div').show();                
                pbar_div.hide();
                response = response.result;
                if(response.status==window.hWin.HAPI4.ResponseStatus.OK){
                    var data = response.data;
                    $.each(data.files, function (index, file) {
                        if(file.error){
                            window.hWin.HEURIST4.msg.showMsgErr(file.error);
                        }else{
                            $('#upload_file_name').val(file.name);
                            doUpload();
                        }
                    });
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response.message);
                }
                 
                    /* var inpt = this;
                    btnUploadFile.off('click');
                    btnUploadFile.on({click: function(){
                                $(inpt).click();
                    }});  */              
                },//done                    
                progressall: function (e, data) { // to implement
                    var progress = parseInt(data.loaded / data.total * 100, 10);
                    progressLabel = pbar.find('.progress-label').text(data.loaded+' / '+data.total);
                    pbar.progressbar({value: progress});
                    if (data.total>max_size && uploadData) {
                            uploadData.abort();
                            window.hWin.HEURIST4.msg.showMsgErr(
                            'Sorry, this file exceeds the upload '
                            + ((max_file_size<max_post_size)?'file':'(post data)')
                            + ' size limit set for this server ('
                            + Math.round(max_size/1024/1024) + ' MBytes). '
                            +'Please reduce the file size, or ask your system administrator to increase the upload limit.'
                            );
                    }else if(!pbar_div.is(':visible')){
                        $('#upload_form_div').hide();
                        pbar_div.show();
                    }
                }                            
                
                            });
                
                
        });

        //
        // submit form on new file upload
        //
        function doUpload(){
            document.forms[0].submit();
            hideThisFrame();
        }


        //
        // submit form on session select
        //
        function doSelectSession(){
            //showProgressMsg('Loading saved file');
            //$(document.forms[0]).hide();
            document.forms[0].submit();
            hideThisFrame();
        }

    </script>

    <div class="help togglepnl show" style="margin:20 0 10 150;width:680px" id="div_help_top1">
        <a onMouseDown="$('#div_help_top1').toggleClass('show'); $('#div_help_details1').toggleClass('hidden');">
        This function loads a comma-separated or tab-separated text file, such as those generated by many spreadsheets and databases, allowing
        matching of rows against existing data based on one or more columns eg. name(s), dates, identifiers. Unmatched rows can be added as new records.
        </a>
    </div>
    <div class="help" style="margin-left: 150px; margin-bottom: 30px;" id="div_help_details1">
        The process can be repeated on the file to extract multiple entities from different columns and replace them with record IDs which can be<br />
        used in a subsequent insertion or update of records.
        <br /><br />
        The first line MUST contain column labels. Do it for your own sanity!<br />
        Data rows must occupy a single line of data terminated with a CRLF (Windows) or an LF (Unix/Mac).<br />
        Linefeeds within memo fields should be represented by CR only. Fields should be separated by tab or comma.<br />
        Quotes may exist within unquoted fields, but within quoted fields they should be preceded by a backslash ( \" ).<br />
        Fields containing the field separator should be enclosed in quotes.
        <br/><br/>
        Editors such as <a href="http://notepad-plus-plus.org/" target="_blank">Notepad++</a>
        (free, Open Source, Windows) show tabs, CR and LF as symbols and can do global replacements on them.
        <br/><br/>
        Please visit the page on <a href="http://heuristnetwork.org/10-tips-for-using-heurist/importing-data/importing-delimited-text-files        "
            target="_blank">Importing delimited text files</a> on the Heurist network site for tips on sucessful import.
        <br/>
    </div>
    
    <div id="upload_form_div">

    <form action="importCSV.php" method="post" enctype="multipart/form-data" name="upload_form" onsubmit="hideThisFrame()" >
        <!-- input type="hidden" name="DBGSESSID" value="424537638752800003;d=1,p=0,c=0" -->
        <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
        <input type="hidden" name="step" value="1">
        <input type="hidden" id="upload_file_name" name="upload_file_name">

        <table width="100%">

            <tr>
                <td align="right" width="250px">Select previously uploaded file:</td>
                <td><select name="import_id" id="import_id" onchange="doSelectSession()"><?=get_list_import_sessions()?></select>
                    <input type="button" value="Clear all files" 
                                            id="btnClearUploaded"
                                            style="margin-right: 10px;" onclick="doClearSession('all')">
                </td>
            </tr>
        
            <tr>
                <td colspan=2><hr/></td>
            </tr>

            <tr>
                <td align="right"><label>OR Upload new CSV/TSV file</label></td>
            </tr>
        
            
            <tr>
                <td>&nbsp;</td>
            </tr>

            <!--tr>
                <td align="right"><label>Upload new CSV/TSV file:</label></td>
                <td><input type="file" id="import_file" name="import_file"></td>
            </tr-->
            
            <tr>
                <td align="right">Encoding:</td>
                <td>
                    <select name="csv_encoding">
<option>UTF-8</option>
<option>UTF-16</option>
<option>UTF-16BE</option>
<option>UTF-16LE</option>
<option>CP1251</option>
<option>CP1252</option>
<option>KOI8-R</option>
<option>UCS-4</option>
<option>UCS-4BE</option>
<option>UCS-4LE</option>
<option>UCS-2</option>
<option>UCS-2BE</option>
<option>UCS-2LE</option>
<option>UTF-32</option>
<option>UTF-32BE</option>
<option>UTF-32LE</option>
<option>UTF-7</option>
<option>UTF7-IMAP</option>
<option>ASCII</option>
<option>EUC-JP</option>
<option>SJIS</option>
<option>eucJP-win</option>
<option>SJIS-win</option>
<option>ISO-2022-JP</option>
<option>ISO-2022-JP-MS</option>
<option>CP932</option>
<option>CP51932</option>
<option>MacJapanese</option>
<option>SJIS-DOCOMO</option>
<option>SJIS-KDDI</option>
<option>SJIS-SOFTBANK</option>
<option>UTF-8-DOCOMO</option>
<option>UTF-8-KDDI</option>
<option>UTF-8-SOFTBANK</option>
<option>ISO-2022-JP-KDDI</option>
<option>JIS</option>
<option>JIS-ms</option>
<option>CP50220</option>
<option>CP50220raw</option>
<option>CP50221</option>
<option>CP50222</option>
<option>ISO-8859-1</option>
<option>ISO-8859-2</option>
<option>ISO-8859-3</option>
<option>ISO-8859-4</option>
<option>ISO-8859-5</option>
<option>ISO-8859-6</option>
<option>ISO-8859-7</option>
<option>ISO-8859-8</option>
<option>ISO-8859-9</option>
<option>ISO-8859-10</option>
<option>ISO-8859-13</option>
<option>ISO-8859-14</option>
<option>ISO-8859-15</option>
<option>byte2be</option>
<option>byte2le</option>
<option>byte4be</option>
<option>byte4le</option>
<option>BASE64</option>
<option>HTML-ENTITIES</option>
<option>7bit</option>
<option>8bit</option>
<option>EUC-CN</option>
<option>CP936</option>
<option>GB18030</option>
<option>HZ</option>
<option>EUC-TW</option>
<option>CP950</option>
<option>BIG-5</option>
<option>EUC-KR</option>
<option>UHC</option>
<option>ISO-2022-KR</option>
<option>CP866</option>
                    </select>
                </td>
            </tr>

            <tr>
                <td align="right">Field separator:</td>
                <td>
                    <select name="csv_delimiter">
                        <option value="," selected>comma</option>
                        <option value="tab">tab</option>
                        <option value=";">semicolon</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td align="right">Line separator:</td>
                <td>
                    <select name="csv_linebreak">
                        <option selected value="auto">Auto detect</option>
                        <option value="\r\n">Windows</option>
                        <option value="\n">Unix</option>
                        <option value="\r">Mac</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td align="right">Fields enclosed in:</td>
                <td><select name="csv_enclosure"><option selected value='2'>"</option><option value="1">'</option></select></td>
            </tr>
            <tr>
                <td align="right">Multivalue separator:</td>
                <td><select name="csv_mvsep">
                        <option value="|" selected>|</option>
                        <option value=";">;</option>
                        <option value=":">:</option>
                        <option value="/">/</option>
                        <!-- option value=",">,</option -->
                    </select></td>
            </tr>
            <tr>
                <td align="right">Date format:</td>
                <td><select name="csv_dateformat">
                        <option selected value='1'>dd/mm/yyyy</option><option value="2">mm/dd/yyyy</option></select>
                    &nbsp;&nbsp;<span class=help>Also supports ISO yyyy-mm-dd (and optional hh:mm:ss)
                        and human friendly dates such as 1827, 1st Sept 1827, 1 sep 1827</span></td>
            </tr>

            <!-- tr>
                <td colspan=2><hr/></td>
            </tr>

            <tr height:40px;>
                <td>&nbsp;</td>
            </tr>

            <tr>
                <td></td>
                <td align="left">
                    <input type="button" value="Cancel" onClick="window.close();" style="margin-right: 30px;">
                    <input type="button" value="Continue" style="font-weight: bold; margin-right: 100px;" onclick="doUpload()">
                </td>
            </tr -->
    
        </table>
        </form>
        
        <div style="padding-left:255px;padding-top:10px">
            <input type="file" id="upload_file">
        </div>
        
    </div> <!-- upload_form_div -->
            
        <div id="progressbar_div" style="width:99%;height:40px;padding:5px;text-align:center;display:none">
            <div id="progressbar">
                <div class="progress-label">Loading data...</div>
            </div>
            <div id="progress_stop" style="text-align:center;margin-top:4px">Abort</div>
        </div>
                                        
    
        <div style="width:99%;text-align:right;padding:5px;">
            <input type="button" value="Cancel" onClick="window.close();" style="margin-right: 30px;">
        </div>
    
    
    <?php
}
?>

<div id="divMatchingPopup" style="display:none;padding:10px;">
    <p>One or more of the records you are trying to update does not yet exist in the database (rows with negative ID), so it/they need to be created from scratch. However, you have not assigned <b>data for all the required fields</b>, so these records cannot be created.</p>
    <br/>
    <p>Option 1: Hit Cancel, then assign the required data fields so that the missing records can be created. It is essential to check the appropriate radio button to make sure that the values in your input file do not overwrite data for existing (matched) records in the database which may have been edited or imported from another source. </p>
    <br/>
    <p>Option 2: Download the non-matching rows as a tab-delimited text file and delete them from the current data before proceeding:
        <div id="divUnmatchedBtns"><input type="button" id="btnUnMatchDownload" value="Download unmatched rows"/>  <input id="btnUnMatchDelete" type="button" value="Delete unmatched rows"/></div>
        <div id="divUnmatchedRes" class="error"></div>
    </p>
    <br/>
    <br/>
    <p>If you proceed, Heurist will update only the records which have been matched to input rows (records witn defined Record ID)</p>
    <p><input type="button" id="btnMatchProceed" value="Proceed"/>  <input id="btnMatchCancel" type="button" value="Cancel"/></p>

</div>

</body>
</html>


<?php

function fix_integer_overflow($size) {
        if ($size < 0) {
            $size += 2.0 * (PHP_INT_MAX + 1);
        }
        return $size;
}
function get_config_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return fix_integer_overflow($val);
}

function get_file_size($file_path, $clear_stat_cache = false) {
    if ($clear_stat_cache) {
        if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
            clearstatcache(true, $file_path);
        } else {
            clearstatcache();
        }
    }
    return fix_integer_overflow(filesize($file_path));
}

//
// get file from _REQUEST and call import to db if everything is OK
//
function postmode_file_selection() {

    $param_name = 'import_file';
    
    // there are two ways into the file selection mode;
    // either the user has just arrived at the import page,
    // or they've selected a file *and might progress to file-parsing mode*
    $error = '';
    if (@$_FILES[$param_name]) {
        if ($_FILES[$param_name]['size'] == 0) {
            $error = 'no file was uploaded';
        } else {
 //DEBUG print $_FILES['import_file']['error'];            
            switch ($_FILES[$param_name]['error']) {
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
            
        $content_length = fix_integer_overflow((int)@$_SERVER['CONTENT_LENGTH']);
        
        $post_max_size = get_config_bytes(ini_get('post_max_size'));
        if ($post_max_size && ($content_length > $post_max_size)) {
            $error = 'The uploaded file exceeds the post_max_size directive in php.ini';
        }else{
            if ($_FILES[$param_name]['tmp_name'] && is_uploaded_file($_FILES[$param_name]['tmp_name'])) {
                $file_size = get_file_size($_FILES[$param_name]['tmp_name']);
            } else {
                $file_size = $content_length;
            }
            $file_max_size = get_config_bytes(ini_get('upload_max_filesize'));
            if ($file_max_size && ($content_length > $file_max_size)) {
                $error = 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
            }
            
        }
            
print $error;            
        }

        if (!$error) {    // move on to the next stage!
            $error = postmode_file_load_to_db($_FILES[$param_name]['tmp_name'], $_FILES[$param_name]['name'], true);
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
function postmode_file_load_to_db($filename, $original, $is_preprocess) {

    global $mysqli;

    //$val_separator = $_REQUEST["val_separator"];
    $csv_mvsep     = $_REQUEST["csv_mvsep"];
    $csv_delimiter = $_REQUEST["csv_delimiter"];
    $csv_encoding = $_REQUEST["csv_encoding"];
    $csv_linebreak = $_REQUEST["csv_linebreak"];
    $csv_enclosure = ($_REQUEST["csv_enclosure"]==1)?"'":'"';

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

    $handle = @fopen($filename, "r");
    if (!$handle) {
        if (! file_exists($filename)) return 'file does not exist';
        else if (! is_readable($filename)) return 'file is not readable';
            else return 'file could not be read';
    }

    //fgetcsv и str_getcsv depends on server locale
    // it is possible to set it in  /etc/default/locale (Debian) or /etc/sysconfig/i18n (CentOS)  LANG="en_US.UTF-8"
    setlocale(LC_ALL, 'en_US.utf8');

    // read header
    if($csv_linebreak=="auto"){
        $line = fgets($handle, 1000000);
    }else{
        $line = stream_get_line($handle, 1000000, $lb);
    }
    fclose($handle);
    if(!$line){
        return "Empty header line";
    }

    //detect encoding and convert to UTF8
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
        $res = file_put_contents($filename, $content);
        if(!$res){
            return 'Cant save temporary file '.$filename;
        }
    }


//  mb_detect_encoding($line, "UTF-8, ISO-8859-1, ISO-8859-15", true);




    //get fields
    $fields = str_getcsv ( $line, $csv_delimiter, $csv_enclosure );// $escape = "\\"
    $len = count($fields);

    if($len>200){
        return "Too many columns ".$len."  This probably indicates that you have selected the wrong separator type.";
    }

    if($is_preprocess){
        
        if(strpos($filename, HEURIST_FILESTORE_DIR.'scratch/')===0){
            $temp_file = $filename;
            $ok_moved = true;
        }else{
            $temp_file = tempnam(HEURIST_SCRATCHSPACE_DIR, $filename);
            $ok_moved = move_uploaded_file($filename, $temp_file);
        }
        
        if ($ok_moved) {
            if($len==1){
                return array("warning"=>"You appear to have only one value per line. This probably indicates that "
                    ."you have selected the wrong separator type.",
                    "filename"=>$temp_file, "original"=>$original, "fields"=>$fields );
            }else{
                return array("warning"=>"Please verify the list of columns",
                    "filename"=>$temp_file, "original"=>$original, "fields"=>$fields );
            }
        }else {
            return "Failed to keep the uploaded file '$temp_file'."; //array("fatal_error"=>"Failed to keep the uploaded file '$temp_file'.");
        }
    }

    //array( "filename"=>$temp_name, "errors"=>$errors, "memos"=>$memos, "multivals"=>$multivals )

    $preproc = preprocess_uploaded_file($filename);

    if(count($preproc['errors'])>0 || count($preproc['err_encoding'])>0){
        return array("errors"=>$preproc['errors'],
            "col_count"=>$preproc['col_count'],
            "fields"=>$preproc['fields'],
            "err_encoding"=>$preproc['err_encoding']
        );
    }

    $filename = $preproc['filename'];

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
        return "cannot create table: " . $mysqli->error;
    }

    if($csv_enclosure=="'") $csv_enclosure = "\\".$csv_enclosure;

    if(strpos($filename,"\\")>0){
        $filename = str_replace("\\","\\\\",$filename);
    }

    //load file into table  LOCAL
    $query = "LOAD DATA LOCAL INFILE '".$filename."' INTO TABLE ".$import_table
    ." CHARACTER SET UTF8"
    ." FIELDS TERMINATED BY '".$csv_delimiter."' "
    ." OPTIONALLY ENCLOSED BY '".$csv_enclosure."' "
    ." LINES TERMINATED BY '\n'"  //.$csv_linebreak."' "   //".$csv_linebreak."
    //." IGNORE 1 LINES
    ." (".$columns.")";


    if (!$mysqli->query($query)) {
        return 'Unable to import data. MySQL command: "'.$query.'" returns error: '.$mysqli->error;
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

    if(!$is_preprocess && file_exists($filename)){
        unlink($filename);
    }

    //calculate uniqe values
    $query = "select ".$counts." from ".$import_table;
    $res = $mysqli->query($query);
    if (!$res) {
        return "cannot count unique values: " . $mysqli->error;
    }

    $uniqcnt = $res->fetch_row();
    $reccount = array_pop ( $uniqcnt );

    //add record to import_log
    $session = array("reccount"=>$reccount,
        "import_table"=>$import_table,
        "import_name"=>($original."  ".date("Y-m-d H:i:s")),
        "columns"=>$fields,   //names of columns in file header
        "memos"=>$preproc['memos'],
        "multivals"=>$preproc['multivals'],
        "csv_enclosure"=>$_REQUEST['csv_enclosure'],
        "csv_mvsep"=>$_REQUEST['csv_mvsep'],
        "uniqcnt"=>$uniqcnt,   //count of uniq values per column
        "mapping"=>$mapping,   //mapping of value fields to rectype.detailtype
        "indexes"=>array() );  //names of columns in importtable that contains record_ID

    $session = saveSession($mysqli, $session);
    if(count($warnings)>0){
        $session['load_warnings'] = $warnings;
    }
    return $session;
}

/*
Read file

Add error to log if wrong field count
Identify memo fields by presence of \r within text and flag
Identify repeating value fields and flag - will not be used as key fields
Remove any spaces at start/end of fields (including potential memos) & any redundant spaces in field that is not multi-line
Convert dates to standardised format.

Write processed file

return 3 arrays:
errors
memos
multivalues
*/
function preprocess_uploaded_file($filename){

    $errors = array();
    $err_encoding = array();
    $memos = array();  //multiline fields
    $multivals = array();
    $datefields = @$_REQUEST["datefield"];
    $memofields = @$_REQUEST["memofield"];
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

    $handle = @fopen($filename, "r");
    if (!$handle) {
        if (! file_exists($filename)) return 'file does not exist';
        else if (! is_readable($filename)) return 'file is not readable';
            else return 'file could not be read';
    }

    $len = 0;
    $header = null;

    $temp_name = tempnam(HEURIST_SCRATCHSPACE_DIR, $filename);
    if (!is_writable($temp_name)) {
        return "cannot save preprocessed file $temp_name";
    }
    if (!$handle_wr = fopen($temp_name, 'w')) {
        return "Cannot open file ($temp_name)";
    }


    $line_no = 0;
    while (!feof($handle)) {

        if($csv_linebreak=="auto" || $lb==null){
            $line = fgets($handle, 1000000);      //read line and auto detect line break
        }else{
            $line = stream_get_line($handle, 1000000, $lb);
        }

        if(count($err_encoding)<100 && !mb_detect_encoding($line, 'UTF-8', true)){
            array_push($err_encoding, array("no"=>$line_no, "line"=>substr($line,0,2000)));
            //if(count($err_encoding)>100) break;
        }

        $fields = str_getcsv ( $line, $csv_delimiter, $csv_enclosure );// $escape = "\\"

        if($len==0){
            $header = $fields;
            $len = count($fields);
        }else{
            $line_no++;

            if(trim($line)=="") continue;

            if($len!=count($fields)){
                // Add error to log if wrong field count
                array_push($errors, array("cnt"=>count($fields), "no"=>$line_no, "line"=>substr($line,0,2000)));
                if(count($errors)>100) break;
            }else{
                $k=0;
                $newfields = array();
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
                    }

                    //Doubling up as an escape for quote marks
                    $field = addslashes($field);
                    $field = $csv_enclosure.$field.$csv_enclosure;
                    array_push($newfields, $field);
                    $k++;
                }

                $line = implode($csv_delimiter, $newfields)."\n";

                if (fwrite($handle_wr, $line) === FALSE) {
                    return "Cannot write to file ($temp_name)";
                }
            }
        }

    }
    fclose($handle);
    fclose($handle_wr);

    unlink($filename);

    return array( "filename"=>$temp_name, "col_count"=>$len, "errors"=>$errors, "err_encoding"=>$err_encoding, "memos"=>$memos, "multivals"=>$multivals, "fields"=>$header );
}

?>
