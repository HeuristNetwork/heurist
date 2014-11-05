<?php
    /**
    * importCSV.php: UI for delimited text file normalising data import
    * filename: explanation
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
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

    require_once('importCSV_lib.php');

    $mysqli = mysqli_connection_overwrite(DATABASE);
    mysql_connection_overwrite(DATABASE); //for getRecordInfoLibrary

    
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
        <script type="text/javascript" src="../../external/jquery/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
        <script type="text/javascript" src="../../external/jquery/jquery-ui-1.10.2/ui/jquery-ui.js"></script>
        <link rel="stylesheet" href="../../applications/h4/ext/jquery-ui-1.10.2/themes/base/jquery-ui.css">

        <link rel=stylesheet href="../../common/css/global.css" media="all">

        <style type="text/css">
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
                                alert("Clear uploaded file operation completed");
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
            //
            // submit form for second attempt of file upload
            //
            function doUpload2(){
                showProgressMsg('Please wait');
                $(document.forms[0]).hide();
                document.forms[0].submit();
            }
        </script>
        <?php
            //USER_ID:=get_user_id()
            $imp_session = null;

            $sa_mode = @$_REQUEST['sa_mode'];
            $step = intval(@$_REQUEST["step"]);
            if(!$step) $step=0;

            ob_start();
            echo '<div id="div-progress" style="display:none" class="loading">&nbsp;</div>';
            ob_flush();flush();

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


                if(@$_REQUEST["filename"]){ //after postprocessing - additional step load from temp file
                    $imp_session = postmode_file_load_to_db($_REQUEST["filename"], $_REQUEST["original"], (@$_REQUEST["preprocess"]=="1"));
                }else{
                    $imp_session = postmode_file_selection();
                }
            ?>

            <script type="text/javascript">
                $( function(){ $("#div-progress").hide(); });

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

        <form action="importCSV.php" method="post" enctype="multipart/form-data" name="upload_form">
            <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
            <input type="hidden" id="step" name="step" value="1">
            <input type="hidden" name="filename" value="<?=$imp_session['filename']?>">
            <input type="hidden" name="original" value="<?=$imp_session['original']?>">

            <input type="hidden" name="csv_delimiter" value="<?=$_REQUEST['csv_delimiter']?>">
            <input type="hidden" name="csv_linebreak" value="<?=$_REQUEST['csv_linebreak']?>">
            <input type="hidden" name="csv_enclosure" value="<?=$_REQUEST['csv_enclosure']?>">
            <input type="hidden" name="csv_dateformat" value="<?=$_REQUEST['csv_dateformat']?>">
            <input type="hidden" name="csv_mvsep" value="<?=$_REQUEST['csv_mvsep']?>">

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

                    //error_log(print_r($res, true));

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

                }else if($step==3){  //create records - load to import data to database
                    $mode_import_result = ' style="display:none"';
                ?>

                <div id="main_import_result">
                    <h4>IMPORT DATA RESULTS</h4>
                    <hr width="100%" />
                    <div id="div-progress2"></div>
                    <div>
                        <?php
                            ob_flush();flush();

                            $res = doImport($mysqli, $imp_session, $_REQUEST);
                        ?>
                    </div>
                    <br /><br /><input type="button" value="Back" onClick="{showRecords('mapping');}">
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
    <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
    <input type="hidden" name="step" id="input_step" value="2">
    <input type="hidden" id="ignore_insert" name="ignore_insert" value="<?=@$_REQUEST["ignore_insert"]?>">
    <input type="hidden" id="mvm" name="mvm" value="0">
    <input type="hidden" id="multifield" name="multifield" value="">
    <input type="hidden" id="import_id" name="import_id" value="<?=$imp_session["import_id"]?>">
    <input type="hidden" id="csv_enclosure" name="csv_enclosure" value="<?=@$imp_session["csv_enclosure"]?>">
    <input type="hidden" id="csv_mvsep" name="csv_mvsep" value="<?=@$imp_session["csv_mvsep"]?>">

    <div id="main_mapping"<?=$mode_import_result?>>

        <h4>Step 2: Matching and inserting/updating records</h4>

        <div style="position:absolute;right:25px;top:10px;">

            Please visit <a target="_blank" href="http://HeuristNetwork.org/archive/importing-data">
                HeuristNetwork.org/archive/importing-data</a> for a detailed explanation and examples of record import.

        </div>

        <hr width="100%" />

        <div class="help">
            If the spreadsheet data is complex, this function will allow you to progressively import columns which identify
            subsidiary entities (record types) such as place, organisation, collection, series, artist etc. The first step
            is to match key fields and create new records from unmatched rows. This will create a new column ending in ID.
            This can be used as the key field to import additional columns. Once all subsidiary entities have been matched
            and imported, you can import the primary entity type represented by the table.
            <br/><br/>
        </div>

        <div class="input-line">
            <div class="header"><label for="sa_rectype" style="color:red">Select record type</label></div>
            <select name="sa_rectype" id="sa_rectype" class="text ui-widget-content ui-corner-all" style="min-width:290px"></select>

            <input type="button"
                value="New upload" onClick="{window.location.href='importCSV.php?db=<?=HEURIST_DBNAME?>'}"
                style="margin-right: 10px; margin-left:20px;"
                title="Return to the upload screen to select a new delimited file to upload to the server for processing">
            <?php if(@$_REQUEST["import_id"]){ ?>
            <input type="button"
                value="Clear data" onclick="doClearSession(<?=@$_REQUEST["import_id"]?>)" style="margin-right: 10px;"
                title="Clear the data for this uploaded file from the server">
            <input type="button"
                value="Download data to file"
                onclick="window.open('importCSV.php/import.csv?db=<?=HEURIST_DBNAME?>&getsession=<?=@$_REQUEST["import_id"]?>','_blank')"
                title="Download the data as currently displayed (including matching/IDs) to a new delimited file for further desktop editing">
            <?php } ?>
        </div>

        <?php
            if(@$imp_session['load_warnings']){
                print "<div style='padding:20px;'><span style='color:red;font-size:14px; font-weight:bold'>"
                ."Warning: there are errors in the data read</span>&nbsp;"
                .$imp_session['load_warnings'][0]."&nbsp;&nbsp;<a href='#' onclick='showRecords(\"load_warnings\")'><b>show errors &gt;</b></a></div>";
            }
        ?>

        <input type="hidden" value="<?=$sa_mode?>" name="sa_mode" id="sa_mode"/>

        <b>Records in memory: <?=$imp_session['reccount']?>&nbsp;&nbsp;Fields:&nbsp;<?=$len?></b>
        <div class="help" style="float:right;width:240px;text-align:right;">
                Unprocessed data is retained in buffer on exit
        </div>
        
        <br /><br /><br />

        <table style="vertical-align:middle;">
            <tr>
                <td>
                    <span class="matching">
                        <span id="btnStartMatch" style="display: none">
                            <input type="button" value="Start search / match / Assign IDs"
                                onclick="doMatching()" style="font-weight: bold;">

                        </span>
                    </span>
                    <span class="importing" id="divPreviousBtn">
                        <input type="button" value="<< Previous" style="font-weight: bold;"
                            title="Go to Matching step" onclick='$( "#tabs_actions" ).tabs( "option", "active", 0 );' >
                    </span>
                </td>

                <?php
                    //
                    // render validation result box
                    //

                    $validationRes = @$imp_session['validation'];
                    if($validationRes){
                        $cnt_error   = intval(@$validationRes['count_error']);
                        $show_err    = ($cnt_error>0)?"<a href='#' onclick='showRecords(\"error\")'>show</a>" :"&nbsp;";

                        $cnt_disamb   = count(@$validationRes['disambiguation']);
                        $show_disamb    = ($cnt_disamb>0)?"<a href='#' onclick='showRecords(\"disamb\")'>show</a>" :"&nbsp;";

                        
                        $url = 'importCSV.php/import.csv?db='.HEURIST_DBNAME.'&getsession='.@$_REQUEST["import_id"].'&idfield='.@$_REQUEST["recid_field"].'&mode=';
                        
                        $cnt_update  = intval(@$validationRes['count_update']);
                        $show_update = ($cnt_update>0)?"<a href='#' onclick='showRecords(\"update\")'>show</a>" :"&nbsp;";
                        $download_update= ($cnt_update>0)?"<a href='#' onclick='window.open(\"".$url."0\" ,\"_blank\")'>download</a>" :"&nbsp;";

                        $cnt_insert  = intval(@$validationRes['count_insert']);
                        $show_insert = ($cnt_insert>0)?"<a href='#' onclick='showRecords(\"insert\")'>show</a>" :"&nbsp;";
                        $download_insert= ($cnt_insert>0)?"<a href='#' onclick='window.open(\"".$url."1\" ,\"_blank\")'>download</a>" :"&nbsp;";

                        $cnt_insert_nonexist_id  = intval(@$validationRes['count_insert_nonexist_id']);

                    ?>
                    <td><div class="analized2">
                            <table style="display: inline-block; border:none" border="0">
                                <tr><td>Records matched:</td><td><?=$cnt_update?></td><td width="80"><?=$show_update?></td><td width="80"><?=$download_update?></td></tr>
                                <tr><td>New records to create:</td><td><?=$cnt_insert?></td><td><?=$show_insert?></td><td><?=$download_insert?></td></tr>
                                <?php        if($sa_mode==0){ ?>
                                    <tr><td><font<?=($cnt_disamb>0?" color='red'":'')?>>Rows with ambiguous match:</font></td>
                                        <td><?=$cnt_disamb?></td><td><?=$show_disamb?></td><td></td></tr>
                                    <?php        } else { ?>
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
                                        <input type="button" value="Next >>" title="Go to Import step"
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
                    <?php 
                          if($sa_mode==1 && $validationRes){  ?>
                            <span class="importing analized2">
                                        <input type="button" value="<?=($cnt_update>0 && $cnt_insert>0)?"Create/Update":($cnt_insert>0?"Create":"Update")?> records"
                                            onclick="doDatabaseUpdate(<?=$cnt_insert_nonexist_id?>, <?=$cnt_error?>)" style="font-weight: bold;"></span>
                    <?php } ?>
                            <span class="importing analized3">
                                <input  id="btnStartImport" type="submit"
                                    value="Prepare insert/update >>" style="disabled:disabled;font-weight: bold;">
                            </span>
                </td>
            </tr>
        </table>
        
        <br />
        
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
                    <span class=help>
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
                        <label for="sa_upd21" style="font-size:0.9em;">Delete existing if no new data supplied for record</label>
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
            <h4>RECORDS WITH FIELD ERRORS</h4>
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

        $( function(){ $("#div-progress").hide(); });

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
            showProgressMsg('Loading saved file');
            $(document.forms[0]).hide();
            document.forms[0].submit();
        }

    </script>


    <h4>Step 1: Upload a new file or select a previously uploaded file</h4>
    <hr width="100%" />

    <div class="help" style="margin-left: 150px; margin-top: 20px; margin-bottom: 30px;">
        This function loads a comma-separated or tab-separated text file, such as those generated by many spreadsheets and databases, allowing<br />
        matching of rows against existing data based on one or more columns eg. name(s), dates, identifiers. Unmatched rows can be added as new records.
        <br /><br />
        The process can be repeated on the file to extract multiple entities from different columns and replace them with record IDs which can be<br />
        used in a subsequent insertion or update of records.
        <br /><br />
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
        <br/><br/>
    </div>

    <form action="importCSV.php" method="post" enctype="multipart/form-data" name="upload_form">
        <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
        <input type="hidden" name="step" value="1">

        <table width="100%">
            <tr>
                <td align="right"><label>Upload new CSV file:</label></td><td><input type="file" name="import_file"></td>
            </tr>
            <tr><td align="center">OR</td>

            </tr>
            <tr><td></td><td></td></tr>
            <tr><td align="right"><label>Select previously uploaded file:</label></td><td>
                    <select name="import_id" id="import_id" onchange="doSelectSession()"><?=get_list_import_sessions()?></select>
                </td></tr>

            <tr><td>&nbsp;</td></tr>
            <tr>
                <td></td>
                <td align="left">
                    <input type="button" value="Clear all files" style="margin-right: 10px;"
                        onclick="doClearSession('all')">
                    <input type="button" value="Cancel" onClick="window.close();" style="margin-right: 30px;">
                    <input type="button" value="Continue" style="font-weight: bold; margin-right: 100px;" onclick="doUpload()">
                </td>
            </tr>

            <tr height:40px;><td>&nbsp;</td></tr>
            <tr><td colspan=2><hr/></td></tr>
            <tr><td>&nbsp;</td></tr>

            <tr><td align="right">Field separator:</td><td>
                <select name="csv_delimiter"><option value="," selected>comma</option><option value="tab">tab</option></select></td></tr>
            <tr><td align="right">Line separator:</td><td>
                    <select name="csv_linebreak">
                        <option selected value="auto">Auto detect</option>
                        <option value="\r\n">Windows</option>
                        <option value="\n">Unix</option>
                        <option value="\r">Mac</option>
                    </select>
                </td></tr>
            <tr><td align="right">Fields enclosed in:</td><td>
                <select name="csv_enclosure"><option selected value='2'>"</option><option value="1">'</option></select></td></tr>
            <tr><td align="right">Multivalue separator:</td><td><select name="csv_mvsep">
                        <option value="|" selected>|</option>
                        <option value=";">;</option>
                        <option value=":">:</option>
                        <option value="/">/</option>
                        <!-- option value=",">,</option -->
                    </select></td></tr>
            <tr><td align="right">Date format:</td><td><select name="csv_dateformat">
                <option selected value='1'>dd/mm/yyyy</option><option value="2">mm/dd/yyyy</option></select></td></tr>
            <tr><td></td><td>Also supports ISO yyyy-mm-dd (and optional hh:mm:ss)
                <br />and human friendly dates such as 1827, 1st Sept 1827, 1 sep 1827</td></tr>

        </table>
    </form>
    <?php
    }
?>

<div id="divMatchingPopup" style="display:none;padding:10px;">
<p>One or more of the records you are trying to update does not yet exist in the database, so it/they need to be created from scratch. However, you have not assigned data for all the required fields, so these records cannot be created.</p> 

<p>Option 1: Hit Cancel, then assign the required data fields so that the missing records can be created. It is essential to check the appropriate radio button to make sure that the values in your input file do not overwrite data for existing (matched) records in the database which may have been edited or imported from another source. </p>

<p>Option 2: Download the non-matching rows as a tab-delimited text file and delete them from the current data before proceeding:  
<div id="divUnmatchedBtns"><input type="button" id="btnUnMatchDownload" value="Download unmatched rows"/>  <input id="btnUnMatchDelete" type="button" value="Delete unmatched rows"/></div>
<div id="divUnmatchedRes" class="error"></div>
</p>

<p>If you proceed, Heurist will update only the records which have been matched to input rows</p>
<p><input type="button" id="btnMatchProceed" value="Proceed"/>  <input id="btnMatchCancel" type="button" value="Cancel"/></p>

</div>

</body>
</html>


<?php

    //
    // get file from _REQUEST and call import to db if everything is OK
    //
    function postmode_file_selection() {

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
                $error = postmode_file_load_to_db($_FILES['import_file']['tmp_name'], $_FILES['import_file']['name'], true);
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

        //get fields
        $fields = str_getcsv ( $line, $csv_delimiter, $csv_enclosure );// $escape = "\\"
        $len = count($fields);

        if($len>200){
            return "Too many columns ".$len."  This probably indicates that you have selected the wrong separator type.";
        }

        if($is_preprocess){
            $temp_file = tempnam(sys_get_temp_dir(), $filename);
            if (move_uploaded_file($filename, $temp_file)) {
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
            $query = $query."`field_".$i."` ".(in_array($i, $preproc['memos'])?" text, ":" varchar(300), " ) ;
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

        if(strpos($filename,"\\")>0){
            $filename = str_replace("\\","\\\\",$filename);
        }
        
        //load file into table
        $query = "LOAD DATA LOCAL INFILE '".$filename."' INTO TABLE ".$import_table
        ." CHARACTER SET UTF8"
        ." FIELDS TERMINATED BY '".$csv_delimiter."' "
        ." OPTIONALLY ENCLOSED BY '".$csv_enclosure."' "
        ." LINES TERMINATED BY '\n'"  //.$csv_linebreak."' "   //".$csv_linebreak."
        //." IGNORE 1 LINES
        ." (".$columns.")";


        if (!$mysqli->query($query)) {
            return "can not import data: " . $mysqli->error;
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
            return "can not count unique values: " . $mysqli->error;
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

        $temp_name = tempnam(sys_get_temp_dir(), $filename);
        if (!is_writable($temp_name)) {
            return "can not save preprocessed file $temp_name";
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
