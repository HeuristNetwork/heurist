<?php

    /**
    * createCrosswalkTable.php, Imports recordtypes from another Heurist database
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
    * @author      Stephen White
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

    require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');

    // Requires admin user, access to definitions though get_definitions is open
    if (! is_admin()) {
        print "<html><head><link rel=stylesheet href='../../../common/css/global.css'></head>"
        ."<body><div class=wrap><div id=errorMsg><span>You do not have sufficient privileges to access this page</span>"
        ."<p><a href=".HEURIST_BASE_URL."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME
        ." target='_top'>Log out</a></p></div></div></body></html>";
        return;
    }
    require_once(dirname(__FILE__).'/../../../common/php/dbMySqlWrappers.php');
    require_once(dirname(__FILE__).'/../../../common/php/getRecordInfoLibrary.php');

    //ART - It breaks everything! require_once(dirname(__FILE__).'/../../../viewers/smarty/templateOperations.php'); // for listing and converting smarty templates

    mysql_connection_insert($tempDBName); // Use temp database

?>

<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Heurist - Database structure import</title>

        <!-- YUI -->
        <link rel="stylesheet" type="text/css" href="../../../external/yui/2.8.2r1/build/fonts/fonts-min.css" />
        <link rel="stylesheet" type="text/css" href="../../../external/yui/2.8.2r1/build/paginator/assets/skins/sam/paginator.css">
        <link rel="stylesheet" type="text/css" href="../../../external/yui/2.8.2r1/samples/yui-dt-expandable.css"/>
        <link type="text/css" rel="stylesheet" href="../../../external/yui/2.8.2r1/build/datatable/assets/skins/sam/datatable.css">
        <script type="text/javascript" src="../../../external/yui/2.8.2r1/build/yahoo-dom-event/yahoo-dom-event.js"></script>
        <script type="text/javascript" src="../../../external/yui/2.8.2r1/build/element/element-min.js"></script>
        <script type="text/javascript" src="../../../external/yui/2.8.2r1/build/json/json-min.js"></script>
        <script type="text/javascript" src="../../../external/yui/2.8.2r1/build/datasource/datasource-min.js"></script>
        <script type="text/javascript" src="../../../external/yui/2.8.2r1/build/datatable/datatable-min.js"></script>
        <script type="text/javascript" src="../../../external/yui/2.8.2r1/build/paginator/paginator-min.js"></script>
        <script type="text/javascript" src="../../../external/yui/2.8.2r1/samples/yui-dt-expandable.js"></script>
        <script type="text/javascript" src="../../../external/jquery/jquery.js"></script>
        <style type="text/css">
            .yui-skin-sam .yui-dt-liner {
                white-space:nowrap;
            }
            .yui-dt-expandablerow-trigger a {
                display:block;
                padding:20px 5px 0;
                cursor:pointer;
            }
            .tooltip {
                position:absolute;
                z-index:999;
                left:-9999px;
                top:0px;
                background-color:#dedede;
                padding:5px;
                border:1px solid #fff;
                min-width:200;
            }
            #yui-dt0-th-import {width:30px}
            .yui-dt0-col-import div.yui-dt-liner {text-align:center}
            #yui-dt0-th-arrow {width:24px}
            .yui-dt0-col-matches .yui-dt-liner, .yui-dt0-col-import .yui-dt-liner {text-align: center;}


            #popup-saved {text-align :center; color:#FFF; font-size: 18px; background-color: RGBA(0,0,0,0.8);padding: 0; width: 200px; height: 75px; top: 50%; left:50%; margin :-50px -100px; position: absolute;overflow: visible;-moz-border-radius-bottomleft:10px;-moz-border-radius-bottomright:10px;-moz-border-radius-topleft:10px;-moz-border-radius-topright:10px;-webkit-border-bottom-left-radius:10px;-webkit-border-bottom-right-radius:10px;-webkit-border-top-left-radius:10px;-webkit-border-top-right-radius:10px;border-bottom-left-radius:10px;border-bottom-right-radius:10px;border-top-left-radius:10px;border-top-right-radius:10px;border :2px solid #FFF;-webkit-box-shadow: 0 2px 6px rgba(0, 0, 0, 0.6);-moz-box-shadow: 0 2px 6px rgba(0, 0, 0, 0.6);box-shadow: 0 2px 6px rgba(0, 0, 0, 0.6);z-index: 100;}

            #popup-saved b {font-size: 16px;line-height:75px;}

        </style>

        <script type="text/javascript">
            var sourceDBID = <?=$source_db_id?>;
            //var crwDefType = "";
            //var crwLocalCode = "";
            var replaceRecTypeName = "";

            // Fills the YUI Datatable with all recordtypes from the temp DB
            <?php
                $groups = mysql_query("select rtg_ID, rtg_Name from ".$tempDBName.".defRecTypeGroups");
                $rectypeGroups = array();
                while($group = mysql_fetch_assoc($groups)) {
                    array_push($rectypeGroups, array('id'=>$group["rtg_ID"], 'name' => $group["rtg_Name"]));
                }

                $rectypes = mysql_query("select * from ".$tempDBName.".defRecTypes order by rty_RecTypeGroupID, rty_Name");
                $approxMatches = array();
                $tableRows = array();
                // For every recordtype in the temp DB
                while($rectype = mysql_fetch_assoc($rectypes)) {
                    $OriginatingDBID = $rectype["rty_OriginatingDBID"];
                    $IDInOriginatingDB = $rectype["rty_IDInOriginatingDB"];
                    $nameInTempDB = mysql_real_escape_string($rectype["rty_Name"]);

                    // Find recordtypes that are already in the local DB (comparing OriginatingDBID and IDInOriginatingDB
                    $cnt_identical = 0;

                    if($OriginatingDBID>0 && $IDInOriginatingDB>0){
                        $identicalMatches = mysql_query("select rty_ID, rty_Name from " . DATABASE
                            . ".defRecTypes where rty_OriginatingDBID = $OriginatingDBID AND rty_IDInOriginatingDB = $IDInOriginatingDB");
                        // These rectypes are not in the importing database
                        $cnt_identical = mysql_num_rows($identicalMatches);
                    }

                    if(!$cnt_identical) {
                        $approxMatchesRes = mysql_query("select rty_Name, rty_Description from "
                            . DATABASE . ".defRecTypes where (rty_Name like '%$nameInTempDB%')");
                        // TODO: if rectype is more than one word, check for both words
                        $numberOfApproxMatches = mysql_num_rows($approxMatchesRes);
                        // Add all approximate matches to a javascript array
                        if($numberOfApproxMatches > 0) {
                            while($approxRectype = mysql_fetch_assoc($approxMatchesRes)) {
                                $approxRty_Name = mysql_escape_string($approxRectype["rty_Name"]);
                                $approxRty_Description = mysql_escape_string($approxRectype["rty_Description"]);
                                if(@$rectype["rty_ID"] && @$approxMatches[$rectype["rty_ID"]])
                                {
                                    if (!$approxMatches[$rectype["rty_ID"]]){
                                        $approxMatches[$rectype["rty_ID"]] = array(array($approxRty_Name,$approxRty_Description));
                                    }else{
                                        array_push($approxMatches[$rectype["rty_ID"]],array($approxRty_Name,$approxRty_Description));
                                    }
                                }
                            }
                        }
                    }else{

                        if($cnt_identical==1){
                            $rr = mysql_fetch_row($identicalMatches);
                            $numberOfApproxMatches = "#".$rr[0]." ".$rr[1];
                        }else{
                            $numberOfApproxMatches = -$cnt_identical; //identical
                        }

                    }
                    /* TODO: What is this. Remove? Implement?
                    Add recordtypes to the table
                    array_push($tableRows,array('arrow'=>"<img id=\"arrow".$rectype["rty_ID"]."\"
                    src=\"../../../../external/yui/2.8.2r1/build/datatable/assets/images/arrow_closed.png\" />",
                    'rtyID'=>$rectype["rty_ID"],
                    'rectype'=>$rectype["rty_Name"],
                    'matches'=>$numberOfApproxMatches,
                    'import'=>"<a href=\"#import\"><img id=\"importIcon".$rectype["rty_ID"]."\"
                    src=\"../../../common/images/download.png\" width=\"16\" height=\"16\" /></a>",
                    'rtyRecTypeGroupID'=>$rectype["rty_RecTypeGroupID"]));*/

                    array_push($tableRows,array("<img id=\"arrow".$rectype["rty_ID"]
                        ."\" src=\"../../../external/yui/2.8.2r1/build/datatable/assets/images/arrow_closed.png\" />",
                        $rectype["rty_ID"],
                        $rectype["rty_Name"],
                        $numberOfApproxMatches,
                        "<a href=\"#import\"><img id=\"importIcon".$rectype["rty_ID"]
                        ."\" src=\"../../../common/images/download.png\" width=\"16\" height=\"16\" /></a>",
                        $rectype["rty_RecTypeGroupID"]));
                }

                echo "var approxRectypes = ".json_format($approxMatches,true). ";\n";
                echo "var tableData = ".json_format($tableRows,true). ";\n\n";

                mysql_query("use ".$tempDBName);
                $rtyRes = mysql_query("select rty_ID,
                    rst_ID,
                    rst_DetailTypeID,
                    rst_DisplayName,
                    dty_ID,
                    dty_Name,
                    dty_Type,
                    dty_Status,
                    if(dty_OriginatingDBID and dty_IDInOriginatingDB,dty_IDInOriginatingDB,dty_ID) as origDtyID,
                    if(dty_OriginatingDBID,dty_OriginatingDBID,$source_db_id) as origDtyDBID,
                    rty_Description
                    from defRecTypes
                    left join defRecStructure on rty_ID = rst_RecTypeID
                    left join defDetailTypes on rst_DetailTypeID = dty_ID
                    order by rty_ID");

                // For every recordtype, add the structure to a javascript array, to show in a foldout panel
                $rectypeStructures = array();
                if(isset($rtyRes)) {
                    while($rtStruct = mysql_fetch_assoc($rtyRes)) {
                        // check to see if the source rectype field's detailType exist in our DB
                        $dtyExistRes = mysql_query("select dty_ID from " . DATABASE . ".defDetailTypes ".
                            "where dty_OriginatingDBID = ".$rtStruct['origDtyDBID'].
                            " AND dty_IDInOriginatingDB = ".$rtStruct['origDtyID']);
                        $dtyAlreadyImported = mysql_num_rows($dtyExistRes);

                        $rtsShortRow = array($rtStruct["rst_DisplayName"],                                              //[0]
                            mysql_escape_string($rtStruct["dty_Name"]),                 //[1]
                            $rtStruct["dty_Type"],                                                              //[2]
                            $rtStruct["dty_Status"],                                                    //[3]
                            mysql_escape_string($rtStruct["rty_Description"]),  //[4]
                            $dtyAlreadyImported ? 1: 0);                                                //[5]

                        if (!@$rectypeStructures[$rtStruct["rty_ID"]]){
                            $rectypeStructures[$rtStruct["rty_ID"]] = array($rtsShortRow);
                        }else{
                            array_push($rectypeStructures[$rtStruct["rty_ID"]],$rtsShortRow);
                        }
                    }
                }

                echo "var rectypeStructures = ".json_format($rectypeStructures,true). ";\n";
                echo 'var tempDBName = "'.$tempDBName.'";'. "\n";
                echo 'var sourceDBName = "'.$source_db_name.'";'. "\n";
                echo 'var URLBase = "'.HEURIST_BASE_URL.'";'. "\n";
                echo 'var importTargetDBName = "'.HEURIST_DBNAME.'";'. "\n";
                echo 'var importTargetDBFullName = "'.DATABASE.'";'. "\n";
                echo "var rectypeGroups = ".json_format($rectypeGroups,true). ";\n";
                echo "var dtlookups = ".json_format(getDtLookups(),true). ";\n";
            ?>

            var myDataTable;
            var myDataSource;
            var hideTimer;
            var needHideTip;

            YAHOO.util.Event.addListener(window, "load", function() {
                YAHOO.example.Basic = function() {
                    // Create the columns. Arrow contains the collapse/expand arrow, rtyID is hidden and contains the ID,
                    // rectype contains the name, matches the amount of matches and a tooltip, import a button
                    var myColumnDefs = [
                        { key:"arrow", label:"", formatter:YAHOO.widget.RowExpansionDataTable.formatRowExpansion },
                        { key:"import", label:"Import", sortable:false, resizeable:false, width:30 },
                        { key:"rtyRecTypeGroupID", label:"<u>Group</u>", sortable:true, hidden:false, formatter:function(elLiner, oRecord, oColumn, oData) {
                            var grpid = oRecord.getData("rtyRecTypeGroupID");
                            elLiner.innerHTML = "group #"+grpid+" not found";
                            var index;
                            for (index in rectypeGroups) {
                                if( !isNaN(Number(index)) && rectypeGroups[index].id==grpid) {
                                    elLiner.innerHTML = rectypeGroups[index].name;
                                    break;
                                }
                            } //for
                            }
                        },
                        { key:"rtyID", label:"<u>ID</u>", sortable:true, hidden:true },
                        { key:"rectype", label:"<span title='Click on row to view information about the record type'><u>Record type</u></span>",
                            sortable:true, resizeable:true, width:150 },
                        { key:"matches", label:"<span title='Shows the number of record types in the current database with simliar names'>"
                            +"<u>Potential dupes in this DB</u></span>",
                            sortable:true, resizeable:true, formatter:function(elLiner, oRecord, oColumn, oData) {

                                var dup = oRecord.getData("matches");
                                if(dup<0){
                                    elLiner.innerHTML = 'Same origin (x '+Math.abs(dup)+')';
                                }else if (isNaN(Number(dup))){
                                    elLiner.innerHTML = dup+' has same origin';
                                }else if (dup>0){
                                    elLiner.innerHTML = 'Duplicate name (x '+dup+')';
                                }else{
                                    elLiner.innerHTML = '';
                                }
                        }}
                    ];

                    //myDataSource = new YAHOO.util.DataSource();

                    myDataSource = new YAHOO.util.LocalDataSource(tableData, {
                        responseType:YAHOO.util.DataSource.TYPE_JSARRAY,
                        responseSchema: {
                            fields: ["arrow","rtyID","rectype","matches","import","rtyRecTypeGroupID"]
                        },
                        doBeforeCallback: function (req, raw, res, cb) {
                            // This is the filter function
                            var data  = res.results || [],
                            filtered = [],
                            i,l;

                            if (req) {

                                var fvals = req.split("|");

                                var sByGroup  = fvals[0];
                                var showIdentical = (fvals[1]==="1");

                                for (i = 0, l = data.length; i < l; ++i)
                                {
                                    if (showIdentical ||
                                        !(data[i].matches<0 || isNaN(Number(data[i].matches))))
                                    {//show all or non identical only
                                        if (sByGroup==="all" || data[i].rtyRecTypeGroupID===sByGroup)
                                        {
                                            filtered.push(data[i]);
                                        }
                                    }
                                }
                                res.results = filtered;
                            }

                            return res;
                        }
                    });


                    YAHOO.widget.DataTable.MSG_EMPTY = "There are no new record types to import from this database (all types already exist in the target)";
                    // Create the RowExpansionDataTable
                    myDataTable = new YAHOO.widget.RowExpansionDataTable(
                        "crosswalkTable",
                        myColumnDefs,
                        myDataSource,
                        {
                            // Create the expansion for every recordtype, showing all it's recstructure,
                            // and the detailtype name and type the recstructures point to
                            rowExpansionTemplate:
                            function(obj) {
                                var rty_ID = obj.data.getData('rtyID');
                                var info = "<i>" + rectypeStructures[rty_ID][0][4] + "</i><br />";
                                info += '<table><tr><th><b>Field name</b></th><th><b>Field type</b></th><th><b>Data type</b></th>';
                                info += '<th class=\"status\"><b>Status</b></th></tr>';

                                // 0 = rst_DisplayName
                                // 1 = dty_Name
                                // 2 = dty_Type
                                // 3 = dty_Status
                                // 4 = dty_Description
                                // 5 = dty already imported

                                for(i = 0; i < rectypeStructures[rty_ID].length; i++) {
                                    if (rectypeStructures[rty_ID][i][3] == "reserved") {
                                        dtyStatus = "<img src=\"../../../common/images/lock_bw.png\">";
                                    }else{
                                        dtyStatus = rectypeStructures[rty_ID][i][3];
                                    };
                                    info += "<tr"+ (rectypeStructures[rty_ID][i][5] == 1? ' style="background-color:#CCCCCC;"' : "") +
                                    "><td>" + (rectypeStructures[rty_ID][i][5] == 1? "(imported) " : "") + rectypeStructures[rty_ID][i][0] +
                                    "</td><td>" + rectypeStructures[rty_ID][i][1] +
                                    "</td><td>" + dtlookups[rectypeStructures[rty_ID][i][2]] +
                                    "</td><td class=\"status\">" + dtyStatus + "</td></tr>";
                                }
                                info += "</table><br />";
                                obj.liner_element.innerHTML += info;
                            },
                            paginator: new YAHOO.widget.Paginator({
                                rowsPerPage:50,
                                containers:['topPagination','bottomPagination']
                            }),
                            sortedBy: { key:'rtyRecTypeGroupID'}
                        }
                    );

                    myDataTable.subscribe("rowMouseoverEvent", myDataTable.onEventHighlightRow);
                    myDataTable.subscribe("rowMouseoutEvent", myDataTable.onEventUnhighlightRow);
                    // If a row is clicked, except the import column, expand the row, and change the arrow
                    myDataTable.subscribe( 'cellClickEvent', function(oArgs) {
                        var column = this.getColumn(oArgs.target);
                        var record_id;
                        var oRecord;
                        var row;
                        if(column.key != "import") {
                            if(YAHOO.util.Dom.hasClass(oArgs.target, 'yui-dt-expandablerow-trigger')) {
                                record_id = oArgs.target;
                                oRecord = this.getRecord(record_id);
                            } else {
                                oRecord = this.getRecord(oArgs.target);
                                record_id = myDataTable.getTdEl({record:oRecord, column:myDataTable.getColumn("rtyID")});
                            }
                            if(record_id != null) {
                                oRecord = this.getRecord(record_id);
                                var rty_ID = oRecord.getData("rtyID");
                                myDataTable.toggleRowExpansion(record_id);
                                expandedRecord = rty_ID;
                            }
                            var arrowElementSrc = document.getElementById("arrow"+rty_ID).src;
                            if(arrowElementSrc.indexOf("arrow_closed") != -1) {
                                document.getElementById("arrow"+rty_ID).src =
                                "../../../external/yui/2.8.2r1/build/datatable/assets/images/arrow_open.png";
                            } else {
                                document.getElementById("arrow"+rty_ID).src =
                                "../../../external/yui/2.8.2r1/build/datatable/assets/images/arrow_closed.png";
                            }
                        }
                    });

                    // If the import icon is clicked, start the import process, and lock the importing of other ones until it is done
                    myDataTable.subscribe('linkClickEvent', function(oArgs) {
                        var elLink = oArgs.target;
                        var oRecord = this.getRecord(elLink);
                        var rty_ID = oRecord.getData("rtyID");
                        var rty_Name = oRecord.getData("rectype");
                        if(elLink.hash === "#import") {
                            if(importPending) {
                                alert("Please wait until previous import is complete.");
                            } else {
                                importedRowID = oRecord.getId();
                                processAction(rty_ID, "import",rty_Name);
                            }
                        }
                    });

                    // Show tooltip on mouseover the matches column
                    myDataTable.on('cellMouseoverEvent', function (oArgs) {
                        var elLink = oArgs.target;
                        var column = this.getColumn(elLink);
                        if(column != null && column.key == 'matches') {
                            var oRecord = this.getRecord(elLink);
                            var rty_ID = oRecord.getData("rtyID");
                            var rty_Name = oRecord.getData("rectype");
                            showMatchesTooltip(rty_ID, rty_Name, oArgs.event);
                        }
                    });

                    return {
                        oDS: myDataSource,
                        oDT: myDataTable
                    };
                }();

                var filterByGroup = document.getElementById("inputFilterByGroup");
                var index;
                for (index in rectypeGroups) {
                    if( !isNaN(Number(index)) ) {

                        var grpID = rectypeGroups[index].id;
                        var grpName = rectypeGroups[index].name;

                        top.HEURIST.util.addoption(filterByGroup, grpID, grpName);
                    }
                } //for
                filterByGroup.onchange = _updateFilter;
                var filterByExist = document.getElementById("inputFilterByExist");
                filterByExist.onchange = _updateFilter;

                _updateFilter();
            });

            //
            // update filter by rectype group
            //
            function _updateFilter(){

                filterTimeout = null;

                // Reset sort
                /*var state = myDataTable.getState();
                state.sortedBy = {key:'name', dir:YAHOO.widget.DataTable.CLASS_ASC};*/

                var filter_group = document.getElementById("inputFilterByGroup").value;
                var filterByExist = document.getElementById("inputFilterByExist").checked?"1":"0";

                // Get filtered data
                myDataSource.sendRequest(filter_group+"|"+filterByExist, {
                    success : myDataTable.onDataReturnInitializeTable,
                    failure : myDataTable.onDataReturnInitializeTable,
                    scope   : myDataTable,
                    argument : { pagination: { recordOffset: 0 } } // to jump to page 1
                });
            }

            // Create the tooltip
            var currentTipId;
            var needHideTip;
            function showMatchesTooltip(rty_ID, rty_Name, event) {
                // Tooltip div mouse out
                function __hideToolTip() {
                    needHideTip = true;
                }
                // Tooltip div mouse over
                function __clearHideTimer() {
                    needHideTip = false;
                    clearHideTimer();
                }
                var forceHideTip = true;
                if(rty_ID != null ) {
                    if(currentTipId != rty_ID && approxRectypes[rty_ID] && approxRectypes[rty_ID].length) {
                        currentTipId = rty_ID;
                        // 0 = rty_Name
                        // 1 = rty_Description
                        var textTip = '<strong>Approximate matches for record type: '+rty_Name+'</strong><br/><br/>';
                        for(i = 0; i < approxRectypes[rty_ID].length; i++) {
                            textTip += "<li>"+approxRectypes[rty_ID][i][0] + " - " + approxRectypes[rty_ID][i][1] + "</li>";
                        }
                    } else {
                        forceHideTip = false;
                    }
                }
                if(textTip != null) {
                    clearHideTimer();
                    needHideTip = true;
                    var my_tooltip = $("#toolTip");

                    my_tooltip.mouseover(__clearHideTimer);
                    my_tooltip.mouseout(__hideToolTip);
                    var xy = top.HEURIST.util.getMousePos(event);
                    my_tooltip.html(textTip);
                    top.HEURIST.util.showPopupDivAt(my_tooltip, xy, $(window).scrollTop(), $(window).width(), $(window).height(),0);
                    hideTimer = window.setTimeout(_hideToolTip, 2000);
                }
                else if(forceHideTip) {
                    needHideTip = false;
                    _hideToolTip();
                }
            }

            function clearHideTimer() {
                if(hideTimer) {
                    window.clearTimeout(hideTimer);
                    hideTimer = 0;
                }
            }

            function _hideToolTip(){
                if(needHideTip) {
                    currentTipId = null;
                    clearHideTimer();
                    var my_tooltip = $("#toolTip");
                    my_tooltip.css( {
                        left:"-9999px"
                    });
                }
            }
            function directImportWarning(event){
                if(event.target.checked){
                    alert("Checking this box allows record types with constrained pointer fields to be imported even if the target"
                        + " record type is not in the database. This means that incomplete definitions may be imported. Not recommended");
                }
            }

        </script>

        <link rel=stylesheet href="../../../common/css/global.css">
        <link rel=stylesheet href="../../../common/css/admin.css">
    </head>

    <body class="popup yui-skin-sam" onbeforeunload="dropTempDB(false)">
        <div id=popup-saved style="display: none">
            <b>Import succesful</b>
        </div>

        <div class="banner">
            <h2>Import record types from <?= "\"".($source_db_id ?$source_db_id." : " : "").$source_db_name."\""?> </h2>
        </div>
        
        <script src="../../../common/js/utilsLoad.js"></script>
        <script src="../../../common/js/utilsUI.js"></script>

        <div id="page-inner" style="overflow:auto">

            <!--<button id="finish1" onClick="dropTempDB(true)" class="button">Back to databases</button>
            -->

            <?php ?>
            <!-- Smarty templates 
                 TODO:  functions are in templateOperations.php
                     selection list of TPL files - use getList();
                     call smartyLocalIDsToConceptIDs
                     serve up to the calling database
                
            <div id="smarty" style="width:100%; margin:auto;">
                <h3>Smarty report templates</h3>
                <p>
                    The following templates can be downloaded from this database. They will require more, or less, editing depending on the
                    degree to which your database shares original record type / field definitions with the source. If you also download missing
                    record types from the source database, the templates should work with little modification.
                </p>

            </div>
            -->

            <!-- Record types/fields/terms -->
            <div id="crosswalk" style="width:100%;margin:auto;">
                <div>
                    <div style="display:inline-block; vertical-align:top;padding-right:20px;">
                        <label for="inputFilterByGroup">Filter by group:&nbsp;</label><select id="inputFilterByGroup" size="1"
                            style="width:138px;height:16px"><option value="all">all groups</option></select>
                    </div>
                    <div style="display:inline-block;">
                        <input id="inputFilterByExist" type="checkbox"/>
                        <label for="inputFilterByExist">&nbsp;&nbsp;Show record types with same original source</label><br />
                        <input type="checkbox" id="noRecursion" title="Check this to prohibit recursive import of record types."
                            onchange="directImportWarning(event)">
                        <label for="noRecursion">&nbsp;&nbsp;Direct record types import only (w/out all related types - constrained pointers)
                        </label><br />
                        <input type="checkbox" id="strict" title="Check this for strict import of types!" checked>
                        <label for="strict">&nbsp;&nbsp;Strict import - only import if structure is entirely correct</label>
                        <input type="checkbox" id="importVocabs" checked>
                        <label for="importVocabs">&nbsp;&nbsp;Import complete vocabulary even if a limited set of terms is specified</label>
                    </div>
                </div>
                
                <div id="topPagination"></div>
                <div id="crosswalkTable"></div>
                <div id="bottomPagination"></div>
            </div>

            <i>Note: If this function reports 'No records found' this normally means that there are no
               definitions in the selected database which are not already in the current database.</i>

            <p>
                <i>In version 3.0 this may also mean that the database is in a 
                different format version which is not being read correctly</i>
            </p>

            <!-- TODO: need a check on format version and report if there is a difference in format version -->

            <br>&nbsp;<br>
            <button id="finish2" onClick="dropTempDB(true)" class="button">Back to databases</button>
            <div class="tooltip" id="toolTip"><p>tooltip</p></div>

            <div >
                <p>Logs give a more detailed history of the actions taken to import structure.
                Click the links below to see the short version and long version respectively.</p>
            </div>
            
            <a id="shortLog" onClick="showShortLog()" href="#">Show short log</a><br />
            <a id="detailedLog" onClick="showDetailedLog()" href="#">Show detailed log</a><br /><br />
            
            <div id="log"></div><br />
            <div id="log"></div>

            <script type="text/javascript">
                var detailedImportLog = "";
                var logHeader = ""
                var shortImportLog = "";
                var result = "";
                var importedRowID;
                var importPending = false;
                var strictImport = false;
                var noRecursion = false;
                var importVocabs = true;
                // Start an asynchronous call, sending the recordtypeID and action
                function processAction(rtyID, action, rectypeName) {
                    // Lock import, and set import icon to loading icon
                    if(action == "import") {
                        importPending = true;
                        document.getElementById("importIcon"+rtyID).src = "../../../common/images/mini-loading.gif";
                        curTime = new Date();
                        logHeader = "Importing record type " + '<p style="color:green; font-weight:bold">' + rectypeName + " at "+ curTime +"</p>";
                    }
                    strictImport = $("#strict").attr("checked");
                    noRecursion = $("#noRecursion").attr("checked");
                    importVocabs = $("#importVocabs").attr("checked");

                    //ARTEM: @todo all this to stuff to jquery ajax

                    var xmlhttp;
                    if (action.length == 0) {
                        document.getElementById("log").innerHTML="";
                        return;
                    }
                    if (window.XMLHttpRequest) { // code for IE7+, Firefox, Chrome, Opera, Safari
                        xmlhttp = new XMLHttpRequest();
                    }
                    else { // code for IE6, IE5
                        xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
                    }
                    xmlhttp.onreadystatechange=function() {
                        // executed on change of ready state:
                        // 0=not init, 1=server connection, 2=request received, 3=processing request, 4= done
                        if (xmlhttp.readyState==4 && xmlhttp.status==200) { // done and OK
                            importPending = false;
                            var response = xmlhttp.responseText;

                            document.getElementById("importIcon"+rtyID).src = "../../../common/images/download.png";

                            // Handle the response, and give feedback
                            if(response.substring(0,6) == "prompt") {
                                changeDuplicateEntryName(rtyID, rectypeName);
                            } else if(response.substring(0,5) == "Error") {

                                detailedImportLog = '<p style="color:red">'+ logHeader+response+"</p>" + detailedImportLog;

                                document.getElementById("log").innerHTML='<p style="color:red">'+response+"</p>";
                                result += logHeader;
                                response = response.split("<br />");
                                tempLog = "";
                                for (var i =0; i<response.length; i++) {
                                    if (response[i].search(/(Error|error)/)!= -1) {
                                        result += response[i]+"\n\n";
                                        tempLog += response[i]+"<br />";
                                    }
                                }

                                alert("Import error. Check log for details at the end of page");

                                shortImportLog = logHeader+'<p style="color:red">'+tempLog+"</p><br />"+shortImportLog;
                                document.getElementById("detailedLog").innerHTML = "Show detailed log";
                                document.getElementById("shortLog").innerHTML = "Show short log";
                            } else {
                                document.getElementById("importIcon"+rtyID).src = "../../../common/images/import_icon.png";

                                detailedImportLog = '<p style="color:green">'+ logHeader+response+"</p>" + detailedImportLog;
                                result += logHeader;
                                response = response.split("<br />");
                                tempLog = "";
                                for (var i =0; i<response.length; i++) {
                                    if (response[i].search(/^(defRectype:|Successful)/)!= -1) {
                                        result += response[i]+"\n\n";
                                        tempLog += response[i]+"<br />";
                                    }
                                    if (response[i].search(/^defDetailType:/)!= -1) {
                                        tempLog += response[i]+"<br />";
                                    }
                                }
                                shortImportLog = logHeader+'<p style="color:green">'+tempLog+"</p><br />"+shortImportLog;

                                document.getElementById("popup-saved").innerHTML = "<b>Import sucessful</b>";
                                setTimeout(function() {
                                    document.getElementById("popup-saved").style.display = "block";
                                    setTimeout(function() {
                                        document.getElementById("popup-saved").style.display = "none";
                                        }, 1000);
                                    }, 0);
                                if(document.getElementById("detailedLog").innerHTML == "Hide detailed log") {
                                    document.getElementById("log").innerHTML = detailedImportLog;
                                } else if(document.getElementById("shortLog").innerHTML == "Hide short log") {
                                    document.getElementById("log").innerHTML = shortImportLog;
                                }

                                myDataTable.deleteRow(importedRowID, -1);
                            }
                        }
                    } // end readystate callback

                    xmlhttp.open("GET","processAction.php?"+
                        "db=<?=HEURIST_DBNAME?>"+
                        "&action="+action+
                        "&tempDBName="+tempDBName+
                        "&sourceDBName="+sourceDBName+
                        "&sourceDBID="+ (sourceDBID ? sourceDBID : "0")+
                        "&importRtyID="+rtyID+
                        "&strict="+(strictImport?"1":"0")+
                        "&noRecursion="+(noRecursion?"1":"0")+
                        "&importVocabs="+(importVocabs?"1":"0")+
                        //                                              "&crwDefType="+crwDefType+
                        //                                              "&crwLocalCode="+crwLocalCode+
                        "&replaceRecTypeName="+replaceRecTypeName+
                        "&importingTargetDBName="+importTargetDBFullName,
                        true);
                    xmlhttp.send();
                }

                function showShortLog() {
                    if(document.getElementById("detailedLog").innerHTML == "Hide detailed log") {
                        document.getElementById("detailedLog").innerHTML = "Show detailed log";
                    }
                    if(document.getElementById("shortLog").innerHTML == "Show short log") {
                        document.getElementById("shortLog").innerHTML = "Hide short log";
                        if(shortImportLog == "") {
                            document.getElementById("log").innerHTML = "Nothing has been imported yet";
                        } else {
                            document.getElementById("log").innerHTML = shortImportLog;
                        }
                    } else {
                        document.getElementById("shortLog").innerHTML = "Show short log";
                        document.getElementById("log").innerHTML = "";
                    }
                }

                function showDetailedLog() {
                    if(document.getElementById("shortLog").innerHTML == "Hide short log") {
                        document.getElementById("shortLog").innerHTML = "Show short log";
                    }
                    document.getElementById("log").innerHTML = detailedImportLog;
                    if(document.getElementById("detailedLog").innerHTML == "Show detailed log") {
                        document.getElementById("detailedLog").innerHTML = "Hide detailed log";
                        if(shortImportLog == "") {
                            document.getElementById("log").innerHTML = "Nothing has been imported yet";
                        } else {
                            document.getElementById("log").innerHTML = detailedImportLog;
                        }
                    } else {
                        document.getElementById("detailedLog").innerHTML = "Show detailed log";
                        document.getElementById("log").innerHTML = "";
                    }
                }

                // If after trying an import the response says that the rectype name already exists, ask user to enter a new one
                function changeDuplicateEntryName(rtyID,rectypeName) {
                    var newRecTypeName = rectypeName + 1;
                    if (rectypeName) {
                        var match = rectypeName.match(/(.*[\D])(\d+)$/);
                        if (match && match[2]){
                            newRecTypeName = match[1] + (parseInt(match[2]) + 1);
                        }
                    }
                    newRecTypeName = prompt("Duplicate record type name\n\nPlease enter a new name for this record type",newRecTypeName);
                    if(newRecTypeName != replaceRecTypeName) {
                        replaceRecTypeName = newRecTypeName;
                        window.setTimeout(function() { processAction(rtyID, "import", newRecTypeName);},0);
                    } else {
                        //              dropTempDB(true);
                    }
                }

                // Drop the temp DB when the page is closed, or 'Finished' is clicked
                var dropped = false;
                function dropTempDB(redirect) {
                    if(!dropped) {
                        dropped = true;

                        top.HEURIST.util.popupURL(top,
                            "../admin/structure/import/processAction.php?action=drop&db=<?=HEURIST_DBNAME?>&tempDBName=<?=$tempDBName?>", {
                                "close-on-blur": true,
                                "no-resize": true,
                                //"no-close": true,
                                height: 100,
                                width: 300,
                                x: 10000,
                                y: 10000,
                                callback: function(context) {

                                    if(redirect) {
                                        window.location = "<?=HEURIST_BASE_URL?>/admin/structure/import/selectDBForImport.php?db=<?=HEURIST_DBNAME?>";
                                    }
                                }
                        });


                        //  processAction(0, "drop");
                        //  alert("Dropping temporal database");
                    }
                }
            </script>
        </div>
    </body>
</html>