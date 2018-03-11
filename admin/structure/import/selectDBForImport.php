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
        var modeCloneTemplate = true; //two modes - clone template and browse for rt import
        var myDataTable;

    $(document).ready(function() {

        var params = top.HEURIST.parseParams(location.search);
        if(params && params['popup']){
                //$('.banner').hide();
        }
        
        if(params && params['browse']==1){
            setModeCloneTemplate(false);
        }else{
            setModeCloneTemplate(true);
<?php
            $isOutSideRequest = (strpos(HEURIST_INDEX_BASE_URL, HEURIST_SERVER_URL)===false);
            if($isOutSideRequest) { //clone from template not allowed
?>        
                modeCloneTemplate = false;
                $('#div_clone_description').hide(); 
                $('#cloneNotAllowed').show();
                $('#div_DB_selector').hide();
<?php            
            }
?>        
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
        

        function initDbTable(){

                var myColumnDefs = [
                    {key:"id", label:"ID" , formatter:YAHOO.widget.DataTable.formatNumber, sortable:false, resizeable:false, width:"40", className:"right"},
                    {key:"crosswalk", label:"Browse", resizeable:false, width:"60", className: "center",
                        formatter:function(elLiner, oRecord, oColumn, oData) {
                            if(modeCloneTemplate && oRecord.getData("id")>0){
                                elLiner.innerHTML = '<img src="../../../common/images/drag_up_down_16x16.png" class="button"/>';
                            }else{
                                var str = oRecord.getData("crosswalk");
                                elLiner.innerHTML = str;
                            }
                        }
                    },
                    {key:"name", label:"Database Name" , sortable:false, resizeable:true, formatter:function(elLiner, oRecord, oColumn, oData) {
                        var str = oRecord.getData("name");
                        var id = oRecord.getData("id");
                        if(id=='XXX' || id=='YYY'){
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
                        if(id!='XXX' && id!='YYY'){
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
                    if(notAdded && Number(dbID)>20){
                        notAdded = false;
                        dataArray.push(['YYY','User databases','Databases registered by Heurist users - use with care, look for entity types with good internal documentation','','']);
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
//console.log('1>>>'+req);                        
                        if(modeCloneTemplate && !req){
                            req = '';
                        }
//console.log('2>>>'+req);                        
                        if (req!=null) {
                            req = req.toLowerCase();
                            // Do a wildcard search for both name and description and URL
                            for (i = 0, l = data.length; i < l; ++i) {
                                var id = data[i].id;
                                if (modeCloneTemplate && (id=='YYY' || id>20)) continue;
                                
                                if ( id=='XXX' || id=='YYY' ||
                                    data[i].description.toLowerCase().indexOf(req) >= 0 ||
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
                            if(dbID>0){
                                if (modeCloneTemplate) {
                                    //doCloneDatabase(dbID);
                                    dbName = registeredDBs[dbID][2];
                                    var URL = top.HEURIST.baseURL 
                                        + 'admin/setup/dboperations/cloneDatabase.php?db=<?= HEURIST_DBNAME?>&templatedb='+dbName;
                                    document.location.href = URL;
                                }else{
                                    doCrosswalk(dbID);      
                                }
                            } 
                            
                            
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

                //YAHOO.util.Event.on('filter','keyup',
                $('#filter').on({'keyup':
                function (e) {
                    clearTimeout(filterTimeout);
                    if($('#filter').val()==''){
                        updateFilter();
                    }else{
                        setTimeout(updateFilter, 600);    
                    }
                    
                }});

                //$('#statusMsg').hide();
                $('#divLoading').hide();
                if(modeCloneTemplate){
                    document.getElementById('div_clone_description').style.display = 'block';
                }
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
        
        //
        //
        //
        function doCloneDatabase(dbID){
          db = registeredDBs[dbID];
          document.getElementById("cloneDatabseName").value = db[2];
          document.forms["cloneDatabse"].submit();
        }
        
    });


        function setModeCloneTemplate(mode){
            modeCloneTemplate = mode;
            if(modeCloneTemplate){
                $('#div_clone_description').show(); 
                $('#divHeader').text('Clone template database');    
            }else{
                $('#cloneNotAllowed').hide();
                $('#div_clone_description').hide(); 
                $('#divHeader').text('Import structural definitions into current database');
            }
        }

        </script>
    </head>

    <body class="popup yui-skin-sam" style="overflow: auto;">

        <script src="../../../common/php/loadCommonInfo.php"></script>

        <div class="banner">
            <h2 style="display:inline-block" id="divHeader">Import structural definitions into current database</h2>
        
            <div id="divLoading" style="display:inline-block">&nbsp;<img src="../../../common/images/mini-loading.gif" width="16" height="16" />&nbsp;&nbsp;<span id="divLoadingMsg">Loading databases ...   (Please be patient - this may take several seconds on slow connections)</span></div>
        
        </div>
        <div id="page-inner" style="overflow:auto;top:20;">

            <div id="cloneNotAllowed" style="display:none;font-style:italic;padding-top:10px">
<p>Cloning of templates can only be carried out on the Heurist service at heurist.sydney.edu.au. You are accessing an alternative service. In order to create a database based on these templates you will need to register with / log into heurist.sydney.edu.au </p>

<p>Having created and experimented with the clone you can download it with Manage > Administration > Create archive package. The downloaded database can then be loaded on your server by your system administrator (or contact support - at - heuristnetwork dot org for assistance).</p>

<p><a href="#" 
    onclick="{setModeCloneTemplate(false); $('#div_DB_selector').show(); return false;}"><b>follow this link</b></a>
 to selectively add structural elements (record types, fields, vocabularies and terms) to your current database</p>
           </div>
        
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
            <div id="div_DB_selector">
           <h4>Use the filter to locate a specific term in the name or description. Click the database icon on the left to view available record types in that database and select them for addition to this database (including all related record types, fields and terms).</h4> 
            
            <div class="markup" id="filterDiv" style="display:none">
                <label for="filter">Filter:</label> <input type="text" id="filter" value="">
                <div id="tbl"></div>
            </div>
            <div id="topPagination"></div>
            <div id="selectDB"></div>
            <div id="bottomPagination"></div>
            </div>

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

            <!--
            <form id="cloneDatabse" action="../../setup/dboperations/cloneDatbase.php" method="POST">
                <input id="cloneDatabseName" name="db" type="hidden">
                <input name="mode" value="clone_template" type="hidden">
            </form>
            -->

            
            <div style="font-style:italic;display:none" id="div_clone_description">
            
<h4>Clone database</h4>

<p>Click one of the copy icons above to clone the template database. You will become the owner of the clone which is completely independent from its source. The clone will include some example data (easily deleted), as well as saved filters and report formats.</p>

<p>The cloned database is just a starting point which you can then extend or modify to your specific needs. You are free to do anything you wish in this cloned database - it is yours exclusively, until you invite others to share it.</p>

<h4>Nothing suitable?</h4>

<p>We only have a limited set of template databases, although we are working on more. The lack of a suitable template does not mean Heurist is not appropriate to your project. </p>

<p>If you find nothing suitable as a starting point. <a href="#" 
    onclick="{setModeCloneTemplate(false); $('#filter').val(''); $('#filter').trigger('keyup'); return false;}"><b>follow this link</b></a>
 to selectively add structural elements (record types, fields, vocabularies and terms) to your current database, chosen from the databases listed above or from databases created and registered by other users. </p>

<p>If you find nothing suitable in any of the registered databases, you can also create new structural elements from scratch (and extend and modify existing elements) with Structure > Build on the Manage tab of the main page.</p>
            
            </div>
            
        </div>
    </body>
</html>
