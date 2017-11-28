<?php

    /**
    * selectDBForImport.php: Shows a list of registered databases to allow choosing source for structural elements
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2016 University of Sydney
    * @author      Tom Murtagh
    * @author      Kim Jackson
    * @author      Stephen White
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     3.1.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

    require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../../records/files/fileUtils.php');
    require_once(dirname(__FILE__)."/../../../common/php/utilsMail.php");

    if(isForAdminOnly("to import structural elements")){
        return;
    }

    if(checkSmtp()){
        $email_text = 'Opened Templates list from "'.HEURIST_DBNAME.'" at '.HEURIST_SERVER_URL;
        $rv = sendEmail(HEURIST_MAIL_TO_INFO, "Open templates", $email_text, null);
    }
?>
<html>
    <head>
        <title>Selection of source database for structure import</title>

        <!-- YUI -->
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="../../../external/yui/2.8.2r1/build/fonts/fonts-min.css" />
        <link rel="stylesheet" type="text/css" href="../../../external/yui/2.8.2r1/build/paginator/assets/skins/sam/paginator.css">
        <link type="text/css" rel="stylesheet" href="../../../external/yui/2.8.2r1/build/datatable/assets/skins/sam/datatable.css">
        <script type="text/javascript" src="../../../external/yui/2.8.2r1/build/yahoo-dom-event/yahoo-dom-event.js"></script>
        <script type="text/javascript" src="../../../external/yui/2.8.2r1/build/element/element-min.js"></script>
        <script type="text/javascript" src="../../../external/yui/2.8.2r1/build/json/json-min.js"></script>
        <script type="text/javascript" src="../../../external/yui/2.8.2r1/build/datasource/datasource-min.js"></script>
        <script type="text/javascript" src="../../../external/yui/2.8.2r1/build/datatable/datatable-min.js"></script>
        <script type="text/javascript" src="../../../external/yui/2.8.2r1/build/paginator/paginator-min.js"></script>

        <script type="text/javascript" src="../../../ext/jquery-ui-1.12.1/jquery-1.12.4.js"></script>


        <link rel=stylesheet href="../../../common/css/global.css">
        <link rel=stylesheet href="../../../common/css/admin.css">

        <style type="text/css">
            .yui-skin-sam .yui-dt-liner { white-space:nowrap; }
            .button {padding:2px; width:auto; height:15px !important}
            button.button {width:auto;}
            .yui-dt-highlighted{
                cursor:pointer !important;
            }
        </style>

        <script type="text/javascript">
        var registeredDBs = {};

    $(document).ready(function() {

        var params = top.HEURIST.parseParams(location.search);
        if(params && params['popup']){
                //$('.banner').hide();
        }
        //document.getElementById("statusMsg").innerHTML = "";
        document.getElementById("filterDiv").style.display = "block";


        // request for server side
        var baseurl = "<?=HEURIST_BASE_URL?>admin/setup/dbproperties/getRegisteredDBs.php";
        
        var params = "db="+top.HEURIST.database.name+"&public=1&named=1&exclude="+top.HEURIST.database.id; //HEURIST_DBNAME, HEURIST_DBID

        top.HEURIST.util.getJsonData(baseurl,
            // fillRegisteredDatabasesTable
            function(responce){

                registeredDBs = {};

                if(!top.HEURIST.util.isnull(responce)){

                    var idx = 0;
                    for(;idx<responce.length;idx++){

                        var regDB = responce[idx];

                        if(!regDB['version'])  // || regDB['version']<top.HEURIST.database.version)
                        {
                            continue;
                        }

                        //
                        var rawURL = regDB['rec_URL'];
                        var splittedURL = rawURL.split('?');
                        var dbURL = splittedURL[0];

                        var matches = rawURL.match(/db=([^&]*).*$/);
                        var dbName = (matches && matches.length>1)?matches[1]:'';
                        matches = rawURL.match( /prefix=([^&]*).*$/ );
                        var dbPrefix = (matches && matches.length>1)?matches[1]:'';

                        if(dbName=='') continue;

                        registeredDBs[ regDB['rec_ID'] ] = [
                                 regDB['rec_ID'],        //0
                                 dbURL,                  //1
                                 dbName,
                                 regDB['rec_Title'],     //3
                                 regDB['rec_Popularity'],//4
                                 dbPrefix
                        ];

                    }//for


                }

                if ($.isEmptyObject( registeredDBs )) {
                    top.HEURIST.util.showError('Unable to access the Heurist index service<br>'+
                        'Please ask your system administrator, or the Heurist team (info at HeuristNetwork dot org) to verify that your server proxy settings are not blocking access or that the clearinghouse is not down.');
                }else{
                    initDbTable();
                }

                //ddiv.innerHTML = s;
            },
        params);

        // Create a YUI DataTable
        var myDataTable;

        function initDbTable(){

                var myColumnDefs = [
                    {key:"id", label:"ID" , formatter:YAHOO.widget.DataTable.formatNumber, sortable:false, resizeable:false, width:"40", className:"right"},
                    {key:"crosswalk", label:"Browse", resizeable:false, width:"60", className: "center"},
                    {key:"name", label:"Database Name" , sortable:false, resizeable:true, formatter:function(elLiner, oRecord, oColumn, oData) {
                        var str = oRecord.getData("name");
                        var id = oRecord.getData("id");
                        if(id=='XXX'){
                            var str2 = oRecord.getData('description');
                            elLiner.innerHTML = '<h2 style="padding-top:3px">'+str+
                                '</h2><div>&nbsp;</div><div style="position: absolute;margin-top: -12px;"><i>'+
                                str2+'</i></div>';
                        }else if(Number(id)<21){
                            elLiner.innerHTML = '<b>'+str+'</b>';
                        }else{
                            elLiner.innerHTML = str;
                        }
                    }},
                    {key:"description", label:"Description", sortable:false, resizeable:true, formatter:function(elLiner, oRecord, oColumn, oData) {
                        var id = oRecord.getData("id");
                        if(id!='XXX'){
                            var str = oRecord.getData("description");
                            elLiner.innerHTML = '<span title="'+str+'">'+str+'</span>';
                        }else{
                            elLiner.innerHTML = '';
                        }
                    }},
                    // Currently no useful data in popularuity value
                    //{key:"popularity", label:"Popularity", formatter:YAHOO.widget.DataTable.formatNumber,sortable:true, resizeable:true, hidden:true }
                    {key:"URL", label:"Server URL",sortable:false, resizeable:true}
                ];

                //TODO: Add the URL as a hyperlink so that one can got to a search of the database
                //      Also add as a filter criteria so you can find databases by server, for example

                // Add databases to an array that YUI DataTable can use. Do not show URL for safety
                dataArray = [
                    ['XXX','Curated templates','Databases curated by the Heurist team as a source of useful entity types for new databases','','']
                ];
                var notAdded = true;
                for(dbID in registeredDBs) {
                    if(notAdded && Number(dbID)>21){
                        notAdded = false;
                        dataArray.push(['XXX','User databases','Databases registered by Heurist users - use with care, look for entity types with good internal documentation','','']);
                    }else{
                        db = registeredDBs[dbID];
                        dataArray.push([db[0],db[2],db[3],
                            '<a href=\"'+db[1]+'?db='+db[2]+'\" target=\"_blank\">'+db[1]+'</a>',
                            '<img src="../../../common/images/b_database.png" class="button"/>']);
                    }
                }

                var myDataSource = new YAHOO.util.LocalDataSource(dataArray,{
                    responseType : YAHOO.util.DataSource.TYPE_JSARRAY,
                    responseSchema : {
                        fields: ["id","name","description","URL","crosswalk"]
                    },
                    // This is the filter function
                    doBeforeCallback : function (req,raw,res,cb) {
                        var data = res.results || [],
                        filtered = [],
                        i,l;
                        if (req) {
                            req = req.toLowerCase();
                            // Do a wildcard search for both name and description and URL
                            for (i = 0, l = data.length; i < l; ++i) {
                                if (data[i].description.toLowerCase().indexOf(req) >= 0 ||
                                    data[i].URL.toLowerCase().indexOf(req) >= 0)
                                {
                                    filtered.push(data[i]);
                                }
                            }
                            res.results = filtered;
                        }
                        return res;
                    }
                });
                // Use pages. Show max 50 databases per page
                myDataTable = new YAHOO.widget.DataTable("selectDB", myColumnDefs, myDataSource, {
                    paginator: new YAHOO.widget.Paginator({
                        rowsPerPage:50,
                        containers:['topPagination','bottomPagination']
                    }),

                    sortedBy: { key:'id' }
                });


                myDataTable.subscribe("rowMouseoverEvent", myDataTable.onEventHighlightRow);
                myDataTable.subscribe("rowMouseoutEvent", myDataTable.onEventUnhighlightRow);

                myDataTable.subscribe("cellClickEvent", function(oArgs){

                    var elTargetCell = oArgs.target;
                    if(elTargetCell) {
                        var oColumn = myDataTable.getColumn(elTargetCell);
                        var oRecord = myDataTable.getRecord(elTargetCell);
                        if(oColumn.key !== 'URL' && oRecord){
                            var dbID = oRecord.getData("id");
                            if(dbID>0) doCrosswalk(dbID);
                        }
                    }

                });

                // Updates the datatable when filtering
                var filterTimeout = null,
                updateFilter  = function () {
                    // Reset timeout
                    filterTimeout = null;

                    // Reset sort
                    var state = myDataTable.getState();
                    state.sortedBy = {key:'id', dir:YAHOO.widget.DataTable.CLASS_ASC};

                    // Get filtered data
                    myDataSource.sendRequest(YAHOO.util.Dom.get('filter').value,{
                        success : myDataTable.onDataReturnInitializeTable,
                        failure : myDataTable.onDataReturnInitializeTable,
                        scope   : myDataTable,
                        argument: state
                    });
                };

                YAHOO.util.Event.on('filter','keyup',function (e) {
                    clearTimeout(filterTimeout);
                    setTimeout(updateFilter,600);
                });

                //$('#statusMsg').hide();
                $('#divLoading').hide();
                
        }//  initDbTable
        // Enter information about the selected database to an invisible form, and submit to the crosswalk page, to start crosswalking
        function doCrosswalk(dbID) {
            db = registeredDBs[dbID];
            document.getElementById("dbID").value = db[0];
            document.getElementById("dbURL").value = db[1];
            document.getElementById("dbName").value = db[2];
            document.getElementById("dbTitle").value = db[3];
            document.getElementById("dbPrefix").value = db[5]?db[5]:"";
            document.forms["crosswalkInfo"].submit();
            document.getElementById('divLoading').style.display = 'inline-block';
            document.getElementById('divLoadingMsg').innerHTML = 'Loading record types'
            document.getElementById('page-inner').style.display = 'none';
        }


    });

        </script>
    </head>

    <body class="popup yui-skin-sam" style="overflow: auto;">

        <script src="../../../common/php/loadCommonInfo.php"></script>

        <div class="banner">
            <h2 style="display:inline-block">Import structural definitions into current database</h2>
        
            <div id="divLoading" style="display:inline-block">&nbsp;<img src="../../../common/images/mini-loading.gif" width="16" height="16" />&nbsp;&nbsp;<span id="divLoadingMsg">Loading databases ...   (Please be patient - this may take several seconds on slow connections)</span></div>
        
        </div>
        <div id="page-inner" style="overflow:auto;top:20;">


           <h4>Use the filter to locate a specific term in the name or description. Click the database icon on the left to view available record types in that database and select them for addition to this database (including all related record types, fields and terms).</h4> 
            
<!--            
            
            The list below shows available databases registered with the Heurist Master Index database
            which have the same major/minor version as the current database<br />
            Use the filter to locate a specific term in the name or title.
            Click the database icon on the left to view available record types in that database.
            <br />
            <b>Bolded</b> databases contain collections of schemas curated by the Heurist team or members of the Heurist community

            <br />

            <h4>
                Older (or newer) format registered databases may not be shown,
                as this list only shows databases with format version number <?=HEURIST_DBVERSION?>.
            </h4>
-->
            <div class="markup" id="filterDiv" style="display:none">
                <label for="filter">Filter:</label> <input type="text" id="filter" value="">
                <div id="tbl"></div>
            </div>
            <div id="topPagination"></div>
            <div id="selectDB"></div>
            <div id="bottomPagination"></div>


            <!-- Beware: buildCrosswalks.php does includes of record structure in  admin/structure/crosswalk, and these includes
            appear to use paths relative to the calling script not relative to buildCrosswalks; so this will break if moved to
            a different level in the tree than other calling scripts -currently admin/setup/dbcreate createNewDB.php -->
            <form id="crosswalkInfo" action="buildCrosswalks.php?db=<?= HEURIST_DBNAME?>" method="POST">
                <input id="dbID" name="dbID" type="hidden">
                <input id="dbURL" name="dbURL" type="hidden">
                <input id="dbName" name="dbName" type="hidden">
                <input id="dbTitle" name="dbTitle" type="hidden">
                <input id="dbPrefix" name="dbPrefix" type="hidden">
            </form>
        </div>
    </body>
</html>
