/*
* Copyright (C) 2005-2020 University of Sydney
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
* manageDetailTypes.js
* DetailTypeManager object for listing and searching of detail types
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


var g_version = "1";

var detailTypeManager;

//aliases
var Dom = YAHOO.util.Dom,
Hul = window.hWin.HEURIST4.util;

function DetailTypeManager() {

    var _className = "DetailTypeManager",
    _ver = g_version;				//version number for data representation
    var db = window.hWin.HAPI4.database;

    //keep tablea and source - to avoid repeat load and for filtering
    var arrTables = [],
    arrDataSources = [];

    var currentTipId,
    _rolloverInfo;

    var _groups = [],  //for dropdown list
    myDTDrags = {},
    _deleted = [], //keep removed types to exclude on filtering
    _cloneHEU = null; //keep Heurist for rollback in case user cancels group/visibility editing

    //object to send changes (visibility and group belong) for update on server
    var _oDetailType = {detailtype:{
        colNames:{common:['dty_ShowInLists','dty_DetailTypeGroupID']},
        defs: {}
    }};

    var _updatesCnt = 0, //number of affected field types
    _filterForAll = true,
    _filterText = "",
    _filterVisible = 0;

    var tabView = new YAHOO.widget.TabView();

    //
    //
    //
    function _init()
    {
        //window.hWin.HEURIST4.detailtypes.rectypeUsage = {};
        
        var ind = 0,
        dtg_ID,
        index;
        //
        // init tabview with names of group
        for (index in window.hWin.HEURIST4.detailtypes.groups) {
            if( !isNaN(Number(index)) ) {
                _addNewTab(ind, window.hWin.HEURIST4.detailtypes.groups[index].id,
                    window.hWin.HEURIST4.detailtypes.groups[index].name,
                    window.hWin.HEURIST4.detailtypes.groups[index].description);
                ind++;
            }
        } //for

        _rolloverInfo = new HintDiv('inforollover', 260, 170, '<div id="inforollover2"></div>');

        tabView.addTab(new YAHOO.widget.Tab({
            id: "newGroup",
            label: "<label title='Create new group, edit or delete the existing group' style='font-style:bold'> +/- </label>",
            content:
            ('<div id="formGroupEditor">'+
                '<style>#formGroupEditor .input-row .input-header-cell {vertical-align: baseline;}</style>'+
                '<h3>Create a new detail group or edit an existing one</h3><br/>'+
                '<div class="input-row required"><div class="input-header-cell"><b>Group</b></div><div class="input-cell"><select id="edGroupId" onchange="onGroupChange()"></select></div></div>'+
                '<div class="input-row required"><div class="input-header-cell"><b>Name</b></div><div class="input-cell"><input id="edName" style="width:300px"/></div></div>'+
                '<div class="input-row required"><div class="input-header-cell"><b>Description</b></div><div class="input-cell"><input id="edDescription" style="width:300px"/></div></div>'+
                '<div class="input-row"><div class="input-header-cell"></div><div class="input-cell">'+
                '<input id="btnGrpSave" style="display:inline-block" type="submit" value="Save" onclick="{detailTypeManager.doGroupSave()}" />'+
                '<input id="btnGrpCancel" type="submit" value="Cancel" onclick="{detailTypeManager.doGroupCancel()}" style="margin:0 5px" />'+
                '<input id="btnGrpDelete" onclick="{detailTypeManager.doGroupDelete()}" value="Delete selected group" type="submit" style="margin-left:100px"/>'+
                '</div></div>'+
                '</div>')
        }));

        tabView.appendTo("modelTabs");

        //new YAHOO.util.DDTarget("ul"+i);

        /*		var bookmarkedTabViewState = YAHOO.util.History.getBookmarkedState("tabview");
        var initialTabViewState = bookmarkedTabViewState || "tab0";

        YAHOO.util.History.register("tabview", initialTabViewState, function (state) {
        tabView.set("activeIndex", state.substr(3));  //restre the index from history
        });

        YAHOO.util.History.onReady(function () {
        var currentState;

        initTabView();

        currentState = YAHOO.util.History.getCurrentState("tabview");

        if(currentState && currentState.length>3) {
        tabView.set("activeIndex", currentState.substr(3));  //restore active tab from history
        }
        });

        try {
        YAHOO.util.History.initialize("yui-history-field", "yui-history-iframe");
        } catch (e) {
        }
        */
        initTabView();

        dragDropEnable();
    }//end _init

    /*
    */
    function dragDropEnable() {

        var i;
        for (i=0; i<_groups.length; i++){
            var id = ""+_groups[i].value;
            if (myDTDrags[id]) {
                myDTDrags[id].unreg();
                delete myDTDrags[id];
            }
            myDTDrags[id] = new YAHOO.example.DDList(id, _updateOrderAfterDrag);
        } // for

    }


    //
    // adds new tab and into 3 spec arrays
    //
    function _addNewTab(ind, grpID, grpName, grpDescription)
    {
        if(Hul.isempty(grpDescription)){
            grpDescription = "Describe this group!";
        }

        _groups.push({value:grpID, text:grpName});

        var newTab = new YAHOO.widget.Tab({
            id: grpID,
            label: "<label title='"+grpDescription+"'>"+grpName+"</label>",
            content:
            ('<div><br>&nbsp;&nbsp;<b><span id="grp'+grpID+'_Desc">'+ grpDescription + '</span></b><br>&nbsp;<hr style="width: 100%; height: 1px;"><p>'+ //for="filter"
                '<div style="float:right; display:none; margin-bottom: 10px;"><label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Filter by name:</label>'+
                '<input type="text" id="filter'+grpID+'" value="">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'+
                '<input type="checkbox"  id="filter'+grpID+'vis" value="1" style="padding-top:5px;">&nbsp;Show visible only&nbsp;&nbsp;'+
                '</div>'+
                '<div style="float:right; text-align:right">'+
                '<label id="lblNoticeAboutChanges'+grpID+'" '+
                'style="padding-left:3px; padding-right:3px; background-color:white; color:red; display: inline-block;"></label>'+
                '&nbsp;&nbsp;&nbsp;'+
                '<input id="btnSave'+grpID+'" type="button" value="Save Changes" '+
                'style="color:red; display: none !important;"/>'+ //inline-block

                '<input type="button" id="btnAdd'+grpID+'" value="Define New Field Type" style="float:right;" class="add"/>'+
                '</div></div>'+
                '<div id="tabContainer'+grpID+'"></div></div>')
        });

        tabView.addTab(newTab, ind);

        // style="float:right; text-align:right"

        arrTables.push(null);
        arrDataSources.push(null);
    }

    //
    // on changing of tab - create and fill datatable for particular group
    //
    function _handleTabChange (e) {

        _rolloverInfo.hide();

        var option;
        var id = e.newValue.get("id");
        if(id==="newGroup"){
            //fill combobox on edit group form
            var sel = Dom.get('edGroupId');

            //celear selection list
            while (sel.length>0){
                sel.remove(0);
            }

            window.hWin.HEURIST4.ui.addoption(sel, "-1", "new group");

            var i;
            for (i in _groups){
                if(i!==undefined){
                    window.hWin.HEURIST4.ui.addoption(sel, _groups[i].value, _groups[i].text);
                }
            } // for

            Dom.get('edName').value = "";
            Dom.get('edDescription').value = "";

        }else if (e.newValue!==e.prevValue)
        {
            initTabContent(e.newValue);
        }
    }// end _handleTabChange

    //
    //add listener for tabview
    //
    function initTabView () {
        tabView.addListener("activeTabChange", _handleTabChange);

        //init the content for the first tab (table and buttons)
        tabView.set("activeIndex", 0);
    }

    // =============================================== START DATATABLE INIT
    //
    // create the content of tab: buttons and datatable
    //
    function initTabContent(tab){

        var dtg_ID = tab.get('id');

        _updateSaveNotice(dtg_ID);

        var needFilterUpdate = false;
        var el1 = Dom.get('filter'+dtg_ID);
        var el2 = Dom.get('filter'+dtg_ID+'vis');

        if(_filterForAll){
            var newval = (_filterVisible === 1);
            needFilterUpdate = ((el1.value !== _filterText) || (el2.checked !== newval));
            el1.value = _filterText;
            el2.checked = (_filterVisible === 1);
        }else{
            _filterText = el1.value;
            _filterVisible = el2.checked?1:0;
        }

        //does not work var dt = Dom.get("datatable"+dtg_ID);

        var currentTabIndex = tabView.get('activeIndex');
        var dt = arrTables[currentTabIndex];

        if(Hul.isnull(dt)){

            var arr = [],
            dty_ID,
            fi = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex;
            
            //window.hWin.HEURIST4.detailtypes.rectypeUsage = {}; //temp

            //create datatable and fill it values of particular group
            for (dty_ID in window.hWin.HEURIST4.detailtypes.typedefs) {

                if(!isNaN(Number(dty_ID)))
                {
                    var td = window.hWin.HEURIST4.detailtypes.typedefs[dty_ID];
                    var deftype = td.commonFields;
                    //only for this group and  visible in UI
                    if(Number(deftype[fi['dty_DetailTypeGroupID']])===Number(dtg_ID)){
                        var aUsage = window.hWin.HEURIST4.detailtypes.rectypeUsage[dty_ID];
                        var iusage = (Hul.isnull(aUsage)) ? 0 : aUsage.length;
                        // add order in group, name, help, type and status,
                        // doc will be hidden (for pop-up)
                        // last 3 columns for actions
                        arr.push([Number(dty_ID),
                            (Number(deftype[fi.dty_ShowInLists])===1),
                            deftype[fi.dty_OrderInGroup],
                            deftype[fi.dty_Name],
                            deftype[fi.dty_HelpText],
                            deftype[fi.dty_Type],
                            deftype[fi.dty_Status],
                            deftype[fi.dty_ExtendedDescription],
                            dtg_ID,
                            iusage,
                            deftype[fi.dty_ConceptID]]);
                    }
                }
            }
            //alert(' len:'+arr.length);

            var myDataSource = new YAHOO.util.LocalDataSource(arr,{
                responseType : YAHOO.util.DataSource.TYPE_JSARRAY,
                responseSchema : {
                    fields: [ "id", "vis", "order", "name", "help",  "type", "status",
                        "description", "grp_id", "usage", "conceptid"]
                },
                doBeforeCallback : function (req,raw,res,cb) {
                    // This is the filter function
                    var data  = res.results || [],
                    filtered = [],
                    i,l;

                    if (!Hul.isempty(_filterText) || _filterVisible===1) {

                        //var fvals = req.split("|");

                        var sByName   = _filterText.toLowerCase(); //fvals[0].toLowerCase();
                        var iByVisibility = _filterVisible; //fvals[1];

                        // when we change the table, the datasource is not changed
                        // thus we need an additional filter to filter out the deleted rows
                        // and rows that were moved to another groups
                        var currentTabIndex = tabView.get('activeIndex');
                        var dtg_ID = tabView.getTab(currentTabIndex).get('id');

                        for (i = 0, l = data.length; i < l; ++i) {

                            // when we change the table, the datasource is not changed
                            //thus we need to update visibility manually
                            var dty_ID = data[i].id;
                            var df = _oDetailType.detailtype.defs[dty_ID];
                            if(!Hul.isnull(df)){
                                data[i].vis = df.common[0];
                                data[i].grp_id = df.common[1];
                            }

                            if ((data[i].name.toLowerCase().indexOf(sByName)>-1)
                                && (Number(dtg_ID) === Number(data[i].grp_id))
                                && (_deleted.indexOf(dty_ID)<0)
                                && (iByVisibility===0 || Number(data[i].vis)===iByVisibility))
                            {
                                filtered.push(data[i]);
                            }
                        }
                        res.results = filtered;
                    }

                    return res;
                }
            });

            /*var formatterDelete = function(elLiner, oRecord, oColumn, oData) {
            //if(oRecord.getData("field3") > 100) { Dom.replaceClass(elLiner.parentNode, "down", "up");
            elLiner.innerHTML = ' <img src="delete_icon.png">';
            };
            // Add the custom formatter to the shortcuts
            YAHOO.widget.DataTable.Formatter.formatterDelete = formatterDelete;*/

            var myColumnDefs = [
                { key: "id", label: "Code", sortable:true, width:30, className:'right',resizeable:false,
                    formatter: function(elLiner, oRecord, oColumn, oData) {
                         var str = oRecord.getData("id");
                         var tit = oRecord.getData("conceptid");
                         elLiner.innerHTML = str;//'<label title="'+tit+'">'+str+'</label>';
                         elLiner.title = tit;
                    }
                },
                // 8/10/12 - removed Ian to make space for more important fields, these should be shown as rollovers
                { key: "conceptid", label: "Concept", sortable:true, className:'right',resizeable:false, hidden:true },
                { key: "order", hidden:true },
                { key: "name", label: "Name", sortable:true, className:'bold_column', width:120, minWidth:120,
                    formatter: function(elLiner, oRecord, oColumn, oData) {
                        var str = oRecord.getData("name");
                        var tit = "";
                        if(str.length>30) {
                            tit = str;
                            str = str.substr(0,30)+"&#8230";
                        }
                        elLiner.innerHTML = '<label title="'+tit+'">'+str+'</label>';
                }},
                { key: "help", label: "Description", sortable:false, minWidth:200, maxAutoWidth:300,
                    formatter: function(elLiner, oRecord, oColumn, oData) {
                        var str = oRecord.getData("help");
                        var tit = oRecord.getData("description");
                        if(Hul.isnull(str)){
                            str = "";
                        }/*else if (str.length>30) {
                        tit = str;
                        str = str.substr(0,30)+"&#8230";
                        }*/
                        elLiner.innerHTML = '<div title="'+tit+'">'+str+'</div>';
                }},
                { key: "type", label: "Data Type", width:90, sortable:true,
                    formatter: function(elLiner, oRecord, oColumn, oData) {
                        var type = oRecord.getData("type");
                        elLiner.innerHTML = window.hWin.HEURIST4.detailtypes.lookups[type];
                    }
                },
                { key: "description",   hidden:true},
                { key: null, label: "Edit", sortable:false, formatter: function(elLiner, oRecord, oColumn, oData){
                    elLiner.innerHTML = '<a href="#edit"><img src="../../../common/images/edit-pencil.png" width="16" height="16" border="0" title="Edit" /><\/a>'; } },
                { key: "vis", label: "Show", sortable:false, formatter:YAHOO.widget.DataTable.formatCheckbox, className:'center' },
                { key: "usage", label: "Used in ...", sortable:true, className:'center',
                    formatter: function(elLiner, oRecord, oColumn, oData) {
                        var str = oRecord.getData("usage");
                        var id = oRecord.getData("id");
                        elLiner.innerHTML = '<span class="count" '+
                        'style="cursor:pointer;" onclick="detailTypeManager.showInfo('+id+', event)" onmouseout="detailTypeManager.hideInfo()"/>'+str+'</span>';
                }},
                { key: "grp_id", label: "Show in group ...", sortable:false, width:90, className:'center',
                    formatter: YAHOO.widget.DataTable.formatDropdown,
                    dropdownOptions:_groups},

                /*{ key: null, label: "Del", sortable:false, formatter: function(elLiner, oRecord, oColumn, oData){
                elLiner.innerHTML = '<a href="#delete"><img src="../../../common/images/cross.png" width="12" height="12" border="0" title="Delete" /><\/a>'; } },*/
                //{ key: "info", label: "Info", sortable:false, formatter: function(elLiner, oRecord, oColumn, oData){
                //	elLiner.innerHTML = '<a href="#info"><img src="../../../common/images/info.png" width="16" height="16" border="0" title="Info" /><\/a>'} },
                { key: "status", label: "Status", sortable:true, className:'center', minWidth:20, maxAutoWidth:40, width:25,
                    formatter: function(elLiner, oRecord, oColumn, oData) {
                        var str = oRecord.getData("status");
                        if (str === "reserved") {
                            rectypeStatus = "<img src=\"../../../common/images/lock_bw.png\" title=\"Status:"+str+" - Locked\">";
                        }else{
                            rectypeStatus = "<a href=\"#delete\"><img src=\"../../../common/images/cross.png\" border=\"0\" title=\"Status: "+str+" - Delete\"/><\/a>";
                        };
                        elLiner.innerHTML = rectypeStatus;
                }},
            ];

            // Define a custom row formatter function
            var myRowFormatter = function(elTr, oRecord) {
                if (!oRecord.getData('vis')) {
                    Dom.addClass(elTr, 'greyout');
                }
                return true;
            };


            var myConfigs = {
                // Enable the row formatter
                formatRow: myRowFormatter,
                //selectionMode: "singlecell",
                paginator : new YAHOO.widget.Paginator({
                    rowsPerPage: 100, // REQUIRED
                    totalRecords: arr.length, // OPTIONAL

                    // use an existing container element
                    //containers: 'dt_pagination',

                    // use a custom layout for pagination controls
                    template: "&nbsp;Page: {PageLinks} Show {RowsPerPageDropdown} per page",

                    // show all links
                    pageLinks: YAHOO.widget.Paginator.VALUE_UNLIMITED,

                    // use these in the rows-per-page dropdown
                    rowsPerPageOptions: [25, 50, 100]

                })
            };

            dt = new YAHOO.widget.DataTable('tabContainer'+dtg_ID, myColumnDefs, myDataSource, myConfigs);

            //dt.subscribe("cellClickEvent", this.singleCellSelectDataTable.onEventSelectCell);

            //click on action images
            dt.subscribe('linkClickEvent', function(oArgs){
                YAHOO.util.Event.stopEvent(oArgs.event);

                var dt = this;
                var elLink = oArgs.target;
                var oRecord = dt.getRecord(elLink);
                var dty_ID = oRecord.getData("id");

                //                 alert("Action "+elLink.hash+" for:"+dty_ID);
                if(elLink.hash === "#edit"){

                    _onAddEditFieldType(dty_ID, 0);

                }else if(elLink.hash === "#delete"){

                    //find all records that reference this type
                    var aUsage = window.hWin.HEURIST4.detailtypes.rectypeUsage[dty_ID];
                    var sUsage = "";
                    if(!Hul.isnull(aUsage)){
                        var k;
                        for (k in aUsage) {
                            sUsage = sUsage + "<br/>&nbsp;&nbsp;&nbsp;&nbsp;<a href='#' onclick='{top.frames[\"adminFrame\"].detailTypeManager.editRecStructure("+aUsage[k]+");}'>"+window.hWin.HEURIST4.rectypes.names[aUsage[k]]+"</a>";
                        }
                    }

                    if(Hul.isempty(sUsage))
                    {

                        if(_needToSaveFirst()) {
                            return;
                        }


                        var r=confirm("Delete field type#"+dty_ID+" '"+oRecord.getData('name')+"?");
                        if (r) {

                            function _updateAfterDelete(response) {

                                if(response.status == window.hWin.ResponseStatus.OK){

                                    dt.deleteRow(oRecord.getId(), -1);
                                    _deleted.push( dty_ID );
                                    // alert is a pain alert("Field type #"+dty_ID+" was deleted");
                                    window.hWin.HEURIST4.detailtypes = response.data.detailtypes;
                                    _cloneHEU = null;
                                    
                                }else{
                                        window.hWin.HEURIST4.msg.showMsgErr(response);
                                }                                        
                            }

                            var baseurl = window.hWin.HAPI4.baseURL + "admin/structure/saveStructure.php";
                            var callback = _updateAfterDelete;

                            var request = {method:'deleteDT', db:window.hWin.HAPI4.database, dtyID:dty_ID};
                            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, callback);
                            
                        }

                    }else{

                        sUsage = '<div><b>Warning</b><br/><br/>This field type is used in the following record types:<br/>'
                        +sUsage
                        +'<br/><br/>'
                        +'You have to either delete the field from the record type, '
                        +'or delete the record type (it may not be possible or desirable to delete the record type)</div>';

                        window.hWin.HEURIST4.msg.showMsgDlg(sUsage);
                        
                    }
                }

            });

            // highlight listeners
            dt.subscribe("rowMouseoverEvent", dt.onEventHighlightRow);
            dt.subscribe("rowMouseoutEvent", dt.onEventUnhighlightRow);

            dt.subscribe('dropdownChangeEvent', function(oArgs){
                var elDropdown = oArgs.target;
                var record = this.getRecord(elDropdown);
                var column = this.getColumn(elDropdown);
                var newValue = elDropdown.options[elDropdown.selectedIndex].value;
                var oldValue = record.getData(column.key);
                var recordIndex = this.getRecordIndex(record);
                var recordKey = record.getData('recordKey');
                if(newValue!==oldValue){
                    //this.deleteRow(recordIndex);
                    var data = record.getData();
                    data.grp_id = newValue;

                    ///var newTabIndex = elDropdown.selectedIndex; //getTabIndexByGroup(newValue);

                    //remove destination table
                    _removeTable(newValue, false);


                    //remove from this table and refresh another one
                    window.setTimeout(function() {
                        dt.deleteRow(record.getId(), -1);
                        }, 100);

                    //keep the track of changes in special object
                    _updateDetailType(record);
                    _updateSaveNotice(oldValue);
                }
            });

            //subscribe on checkbox event (visibility)
            dt.subscribe("checkboxClickEvent", function(oArgs) {
                var elCheckbox = oArgs.target;
                var oRecord = dt.getRecord(elCheckbox);
                var data = oRecord.getData();
                data.vis = elCheckbox.checked;//?1:0;

                var recindex = dt.getRecordIndex(oRecord);
                dt.updateRow(recindex, data);

                //keep the track of changes in special array
                _updateDetailType(oRecord);

                dt.render();
            });

            //
            // keep the changes in object that will be send to server
            //
            function _updateDetailType(oRecord)
            {
                var dty_ID = oRecord.getData('id'),
                grp_id = oRecord.getData('grp_id'),
                newvals = [(oRecord.getData('vis')?1:0), grp_id];

                //keep copy
                if(Hul.isnull(_cloneHEU)) {
                    _cloneHEU = Hul.cloneJSON(window.hWin.HEURIST4.detailtypes);
                }
                //update HEURIST
                var td = window.hWin.HEURIST4.detailtypes.typedefs[dty_ID];
                var deftype = td.commonFields;
                deftype[window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex['dty_ShowInLists']] = newvals[0]; //visibility
                deftype[window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex['dty_DetailTypeGroupID']] = newvals[1]; //group

                //update keep object
                var dt_def = _oDetailType.detailtype.defs[dty_ID];
                if(Hul.isnull(dt_def)){
                    _oDetailType.detailtype.defs[dty_ID] = {common:newvals};
                    _updatesCnt++;
                }else{
                    _oDetailType.detailtype.defs[dty_ID].common = newvals;
                }

                _updateSaveNotice(grp_id);
            }

            /* MOVED TO SPAN LISTENER

            //mouse over help colums shows the datailed description
            dt.on('cellMouseoverEvent', function (oArgs) {

            var target = oArgs.target;
            var column = this.getColumn(target);
            var dty_ID = null;

            if(!Hul.isnull(column) && column.key === 'usage') {

            var record = this.getRecord(target);
            dty_ID = record.getData('id');
            }
            _showInfoToolTip(dty_ID, oArgs.event);

            });
            dt.on('cellMouseoutEvent', function (oArgs) {
            //hideTimer = window.setTimeout(_hideToolTip, 0);
            _hideToolTip();
            });*/

            arrTables[currentTabIndex] = dt;
            arrDataSources[currentTabIndex] = myDataSource;

            var filter_forall = Dom.get('filter_forall'+dtg_ID);
            if(!Hul.isnull(filter_forall)){
                filter_forall.onchange = function (e) {
                    _filterForAll = filter_forall.checked;
                };
            }

            var filter = Dom.get('filter'+dtg_ID);
            if(!Hul.isnull(filter)){
                filter.onkeyup = function (e) {
                    clearTimeout(filterTimeout);
                    setTimeout(updateFilter,600);  };
            }

            var filtervis = Dom.get('filter'+dtg_ID+'vis');
            if(!Hul.isnull(filtervis)){
                filtervis.onchange = function (e) {
                    clearTimeout(filterTimeout);
                    updateFilter();  };
            }

            var btnAdd = Dom.get('btnAdd'+dtg_ID);
            btnAdd.onclick = function (e) {
                var currentTabIndex = tabView.get('activeIndex');
                var grpID = tabView.getTab(currentTabIndex).get('id');
                _onAddEditFieldType(0, grpID);
            };


            /*
            YAHOO.util.Event.on('filter','onkeyup',function (e) {
            clearTimeout(filterTimeout);
            setTimeout(updateFilter,600);
            });    */
            $th = $( "span:contains('Show')" ).parent().parent();
            $th.css('width','1%');
            $th = $( "span:contains('Edit')" ).parent().parent();
            $th.css('width','1%');

        } //if(dt==undefined || dt==null)
        else if (needFilterUpdate) {
            updateFilter();
        }
    }//initTabContent =============================================== END DATATABLE INIT


    //
    // edit strcuture (to remove unwanted fieldtypes)
    //
    function _editRecStructure(rty_ID) {

        var sURL = window.hWin.HAPI4.baseURL + "admin/structure/fields/editRecStructure.html?db="+db+"&rty_ID="+rty_ID;
        //this.location.replace(URL);

        var body = $(window.hWin.document).find('body');
        var dim = {h:body.innerHeight(), w:body.innerWidth()};

        window.hWin.HEURIST4.msg.showDialog(sURL, {
            "close-on-blur": false,
            "no-resize": false,
            height: dim.h*0.9,
            width: 860,
            callback: function(context) {
                if(Hul.isnull(context)) {
                    // Canceled
                } else {
                    // alert("Structure is saved");
                }
            }
        });

        return false;
    }

    /**
    * Show popup div with information about field types in use for given record type
    */
    function _showInfoToolTip(dty_ID, event) {

        var forceHideTip = true;
        var textTip;

        if(!Hul.isnull(dty_ID)){
            if(currentTipId !== dty_ID) {
                currentTipId = dty_ID;

                var detname = window.hWin.HEURIST4.detailtypes.names[dty_ID];
                if(detname.length>40) { detname = detname.substring(0,40)+"..."; }

                //find all records that reference this type
                var aUsage = window.hWin.HEURIST4.detailtypes.rectypeUsage[dty_ID];
                if(!Hul.isnull(aUsage)){

                    textTip = '<h3>'+detname+'</h3><br/>'+
                    '<b>Used in record types:</b><br/><label style="color: #999;margin-left:5px">Click on field type to edit</label><ul>';

                    var k;
                    for (k in aUsage) {
                        url = "admin/structure/fields/editRecStructure.html?db="+db+"&rty_ID="+aUsage[k];
                        textTip = textTip + "<li><a href='#' onClick='"
                        +"window.hWin.HEURIST4.msg.showDialog(\""+url
                        +"\",{\"close-on-blur\": false,\"no-resize\": false,height: 580,width: 1100})'>"+window.hWin.HEURIST4.rectypes.names[aUsage[k]]+"</a></li>";
                    }
                    textTip = textTip + "</ul>";
                }

            } else {
                forceHideTip = false;
            }
        }
        if(!Hul.isnull(textTip)) {
            var xy = window.hWin.HEURIST4.ui.getMousePos(event);
            xy[0] = xy[0] - 10;

            _rolloverInfo.showInfoAt(xy,"inforollover2",textTip);
        }
        else if(forceHideTip) {
            currentTipId = '';
            _rolloverInfo.close();
        }


    }

    //
    //  Removes and recreates table from given tab (group)
    //
    function _removeTable(grpID, needRefresh){

        if(!Hul.isnull(grpID) && grpID>0)
        {
            var tabIndex = _getIndexByGroupId(grpID);

            var ndt = arrTables[tabIndex];
            if(!Hul.isnull(ndt)){

                //find parent tab
                var tab = Dom.get('tabContainer'+grpID);
                while (tab.childNodes.length>0){
                    tab.removeChild(tab.childNodes[0]);
                }

                // need to refill the destionation table,
                // otherwise datasource is not updated
                arrTables[tabIndex] = null; //.addRow(record.getData(), 0);

                var currIndex = tabView.get('activeIndex');
                if((Number(tabIndex) === Number(currIndex)) && needRefresh)
                {
                    setTimeout(function(){initTabContent(tabView.getTab(tabIndex));},500);
                }

            }
        }
    }


    //  SAVE BUNCH OF TYPES =============================================================
    //
    // send updates to server (group belong and visibility)
    //
    function _updateDetailTypeOnServer(event) {
        var str = YAHOO.lang.JSON.stringify(_oDetailType);

        if(!Hul.isnull(str)) {
            //_updateResult(""); //debug
            //return;//debug

            var baseurl = window.hWin.HAPI4.baseURL + "admin/structure/saveStructure.php";
            var callback = _updateResult;
            
            var request = {method:'saveDT', db:window.hWin.HAPI4.database, data:_oDetailType};
            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, callback);
            
        }
    }
    //
    // after saving a bunch of field types
    //
    function _updateResult(response) {

        if(response.status == window.hWin.ResponseStatus.OK){

            var error = false,
            report = "",
            ind;

            for(ind in response.data.result)
            {
                if(!Hul.isnull(ind)){
                    var item = response.data.result[ind];
                    if(isNaN(item)){
                        window.hWin.HEURIST4.msg.showMsgErr(item);
                        error = true;
                    }else{
                        detailTypeID = Number(item);
                        if(!Hul.isempty(report)) { report = report + ","; }
                        report = report + detailTypeID;
                    }
                }
            }

            if(!error) {

                if(report.indexOf(",")>0){
                    // this alert is a pain  alert("Field types with IDs :"+report+ " were succesfully updated");
                }else{
                    // this alert is a pain  alert("Field type with ID " + report + " was succesfully  updated");
                }
                //window.setTimeout(function(){alwin.hide();}, 1000);
                _clearGroupAndVisibilityChanges(false);
            }
            window.hWin.HEURIST4.detailtypes = response.data.detailtypes;
            _cloneHEU = null;
        }else{
             window.hWin.HEURIST4.msg.showMsgErr(response);
        }
    }

    /**
    * Show/hide information about number of fieldtypes with changed activity
    */
    function _updateSaveNotice(grp_id){

        //var _lblNotice = Dom.get("lblNoticeAboutChanges"+grp_id);
        //var _btnSave   = Dom.get("btnSave"+grp_id);

        if(_updatesCnt>0){
            _updatesCnt = 0;
            _updateDetailTypeOnServer();
            /* Ian's request to apply changes immediately
            _lblNotice.innerHTML = 'You have changed <b>'+_updatesCnt+'</b> field type'+((_updatesCnt>1)?'s':'');
            _btnSave.style.display = 'inline-block';
            _btnSave.onclick = _updateDetailTypeOnServer;*/
        }else{
            //_btnSave.style.display = 'none';
            //_lblNotice.innerHTML = '';
        }
    }

    //
    // clear all changes with visibility and groups
    //
    function _clearGroupAndVisibilityChanges(withReload){

        _updatesCnt = 0;
        _oDetailType.detailtype.defs = {}; //clear keeptrack

        _updateSaveNotice(_getGroupByIndex(tabView.get('activeIndex')));

        if(_cloneHEU) {
            window.hWin.HEURIST4.detailtypes = Hul.cloneJSON(_cloneHEU);
        }
        _cloneHEU = null;

        if(withReload){
            var ind;
            for(ind in arrTables)
            {
                if(!Hul.isnull(ind)){
                    _removeTable( _getGroupByIndex(ind), true);
                }
            }
        }
    }
    //
    // if user chnaged visibility of group, it is required to save changes before new edit
    // (otherwise HEURIST will be rewritten and we get the mess)
    //
    function _needToSaveFirst(){
        if(_updatesCnt>0){
            var r = confirm("You have made changes. Before new edit you have to save them. Save?");
            if (r) {
                _updateDetailTypeOnServer(null);
            }else{
                _clearGroupAndVisibilityChanges(true);
            }
            return true;
        }else{
            return false;
        }
    }

    //  SAVE BUNCH OF TYPES ======================================================== END

    //
    // filtering by name
    // listenter is activated along with dataTable creation
    //
    var filterTimeout = null;
    //
    function updateFilter() {
        // Reset timeout
        filterTimeout = null;

        var currentTabIndex = tabView.get('activeIndex');
        var dtable = arrTables[currentTabIndex];
        var dsource = arrDataSources[currentTabIndex];

        // Reset sort
        var state = dtable.getState();
        state.sortedBy = {key:'name', dir:YAHOO.widget.DataTable.CLASS_ASC};

        var grpID = _getGroupByIndex(currentTabIndex);

        _filterText = Dom.get('filter'+grpID).value;
        _filterVisible = Dom.get('filter'+grpID+'vis').checked?1:0;

        // Get filtered data
        dsource.sendRequest(_filterText+'|'+_filterVisible,{
            success : dtable.onDataReturnInitializeTable,
            failure : dtable.onDataReturnInitializeTable,
            scope   : dtable,
            argument : { pagination: { recordOffset: 0 } } // to jump to page 1
        });
    }

    //
    // listener of add button
    //
    function _onAddEditFieldType(dty_ID, dtg_ID){

        if(_needToSaveFirst()) { return; }

        var sURL = window.hWin.HAPI4.baseURL + "admin/structure/fields/editDetailType.html?db="+db;
        if(dty_ID>0){
            sURL = sURL + "&detailTypeID="+dty_ID; //existing
        }else{
            sURL = sURL + "&groupID="+dtg_ID; //new one
        }

        window.hWin.HEURIST4.msg.showDialog(sURL, {
               "close-on-blur": false,
                "no-resize": false,
                height: 680,
                width: 840,
                callback: function(context) {
                    if(!Hul.isnull(context)){

                        //update id
                        var dty_ID = Math.abs(Number(context.result[0]));

                        //if user changes group in popup need update both  old and new group tabs
                        var grpID_old = -1;
                        if(Number(context.result[0])>0){
                            grpID_old = Number(window.hWin.HEURIST4.detailtypes.typedefs[dty_ID].commonFields[window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex['dty_DetailTypeGroupID']]);
                        }

                        //refresh the local heurist
                        window.hWin.HEURIST4.detailtypes = context.detailtypes;
                        _cloneHEU = null;

                        //detect what group
                        var grpID = Number(window.hWin.HEURIST4.detailtypes.typedefs[dty_ID].commonFields[window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex['dty_DetailTypeGroupID']]);

                        _removeTable(grpID, true);
                        if(grpID_old!==grpID){
                            _removeTable(grpID_old, true);
                        }
                    }
                }
        });
    }

    //============================================ GROUPS
    //
    // managing goups
    //
    function _doGroupSave()
    {
        if(_needToSaveFirst()) { return; }

        var sel = Dom.get('edGroupId'),
        name = Dom.get('edName').value.replace(/^\s+|\s+$/g, ''), //trim
        description = Dom.get('edDescription').value.replace(/^\s+|\s+$/g, ''),
        grpID = sel.options[sel.selectedIndex].value,
        grp; //object in HEURIST

        if(Hul.isempty(name)){
            alert('Group name is required');
            Dom.get('edName').focus();
            return;
        }
        if(Hul.isempty(description)){
            alert('Group description is required');
            Dom.get('edDescription').focus();
            return;
        }

        var orec = {dettypegroups:{
            colNames:['dtg_Name','dtg_Description'],
            defs: {}
        }};


        //define new or exisiting
        if(grpID<0) {
            grp = {name: name, description:description};
            orec.dettypegroups.defs[-1] = [];
            orec.dettypegroups.defs[-1].push({values:[name, description]});
        }else{
            //for existing - rename
            grp = window.hWin.HEURIST4.detailtypes.groups[window.hWin.HEURIST4.detailtypes.groups.groupIDToIndex[grpID]];
            grp.name = name;
            grp.description = description;
            orec.dettypegroups.defs[grpID] = [name, description];
        }

        //make this tab active
        function _updateOnSaveGroup(response){

            if(response.status == window.hWin.ResponseStatus.OK)
            {
                //for new - add new tab
                if(!Hul.isnull(response.data.groups['0'].error)){
                    window.hWin.HEURIST4.msg.showMsgErr(response.data.groups['0']);
                }else{
                    var ind;
                    window.hWin.HEURIST4.detailtypes = response.data.detailtypes;
                    _cloneHEU = null;

                    if(Number(grpID)<0){

                        _refreshAllTables();

                        grpID = response.data.groups['0'].result;
                        ind = _groups.length;
                        _addNewTab(ind, grpID, name, description);
                        dragDropEnable();
                    }else{
                        //update label
                        ind = _getIndexByGroupId(grpID);
                        if(ind>=0){
                            var tab = tabView.getTab(ind);
                            var el = tab._getLabelEl();
                            el.innerHTML = "<label title='"+description+"'>"+name+"</label>";
                            _groups[ind].text = name;
                            $('#grp'+grpID+'_Desc').text(description);
                        }
                        _refreshAllTables();
                    }
                    tabView.set("activeIndex", ind);
                }
            }
            else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }               
        }

        //window.hWin.HEURIST4.detailtypes.groups[grpID] = grp;
        var str = YAHOO.lang.JSON.stringify(orec);

        //alert(str);

        if(!$.isEmptyObject(orec)){
            
            var baseurl = window.hWin.HAPI4.baseURL + "admin/structure/saveStructure.php";
            var callback = _updateOnSaveGroup;
            
            var request = {method:'saveDTG',db:window.hWin.HAPI4.database ,data:orec};
            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, callback);
            
        }

    }

    //
    //
    //
    function _refreshAllTables(){

        var ind;
        for(ind in arrTables){
            if(!(Hul.isnull(ind) || Hul.isnull(arrTables[ind]))){
                _removeTable( _getGroupByIndex(ind), true);
                //arrTables[ind].render();
            }
        }
    }

    /**
    * Updates group order after drag and drop
    */
    function _updateOrderAfterDrag() {

        var orec = {dettypegroups:{
            colNames:['dtg_Order'],
            defs: {}
        }};

        var i,
        parentNode = document.getElementsByClassName("yui-nav")[0];

        for (var i = 0; i < parentNode.childNodes.length; i++) {
            var child = parentNode.childNodes[i];
            var id = child.id;
            var ind = window.hWin.HEURIST4.detailtypes.groups.groupIDToIndex[id];
            if(!Hul.isnull(ind)){
                grp = window.hWin.HEURIST4.detailtypes.groups[ind];
                grp.order = i;
                orec.dettypegroups.defs[id] = [i];
            }
        }

        var str = YAHOO.lang.JSON.stringify(orec);

        if(!Hul.isnull(str)) {
            var baseurl = window.hWin.HAPI4.baseURL + "admin/structure/saveStructure.php";
            var callback = null;//_updateOnSaveGroup;
            
            var request = {method:'saveDTG',db:window.hWin.HAPI4.database,data:orec};
            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, callback);
        }

    }

    //
    //
    //
    function _doGroupDelete(){

        if(_needToSaveFirst()) { return; }

        var sel = Dom.get('edGroupId');
        var grpID = sel.options[sel.selectedIndex].value;

        if(grpID<0) { return; }

        var grp = window.hWin.HEURIST4.detailtypes.groups[window.hWin.HEURIST4.detailtypes.groups.groupIDToIndex[grpID]];

        if(!Hul.isnull(grp.types) && grp.types.length>0)
        {
            alert("This group contains field types. Please move them to another group before deleting.");
        }else{
            var r=confirm("Confirm the deletion of group '"+grp.name+"'");
            if (r) {
                var ind;
                //
                function _updateAfterDeleteGroup(response) {
                    if(response.status == window.hWin.ResponseStatus.OK){

                        var id = _groups[ind].value;
                        if (myDTDrags[id]) {
                            myDTDrags[id].unreg();
                            delete myDTDrags[id];
                        }
                        window.hWin.HEURIST4.detailtypes = response.data.detailtypes;

                        //remove tab from tab view and select 0 index
                        _groups.splice(ind, 1);
                        arrTables.splice(ind, 1);
                        arrDataSources.splice(ind, 1);

                        tabView.removeTab(tabView.getTab(ind));
                        tabView.set("activeIndex", 0);
                        _cloneHEU = null;
                        _refreshAllTables();
                        
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                }


                //1. find index of tab to be removed
                ind = _getIndexByGroupId(grpID);
                if(ind>=0){

                    var baseurl = window.hWin.HAPI4.baseURL + "admin/structure/saveStructure.php";
                    var callback = _updateAfterDeleteGroup;
                    var request = {method:'deleteDTG', db:window.hWin.HAPI4.database, dtgID:grpID};
                    
                    window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, callback);
                }

            }
        }

    }
    //
    // just hide tab and back to previos one
    //
    function _doGroupCancel(){
        tabView.set("activeIndex", tabView.get('activeIndex'));
    }

    //
    //
    //
    function _getIndexByGroupId(grpID){
        //return window.hWin.HEURIST4.detailtypes.groups.groupIDToIndex[grpID];
        var ind;
        for (ind in _groups){
            if(!Hul.isnull(ind) && (Number(_groups[ind].value)===Number(grpID)) ){
                return ind;
            }
        }
        return -1;
    }
    //
    //
    //
    function _getGroupByIndex(ind){
        return _groups[ind].value;
    }

    //
    // public members
    //
    var that = {

        init: function(){
            _init();
        },
        doGroupSave: function(){ _doGroupSave(); },
        doGroupDelete: function(){ _doGroupDelete(); },
        doGroupCancel: function(){ _doGroupCancel(); },
        hasChanges: function(){ return  (_updatesCnt>0); },
        showInfo: function(rectypeID, event){ _showInfoToolTip( rectypeID, event ); },
        hideInfo: function() {  currentTipId = ''; _rolloverInfo.hide();},

        editRecStructure: function(rty_ID) {
            _editRecStructure(rty_ID);
            return false;
        },

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            return (strClass === _className);
        }

    };

    return that;
}

//
//general functions
//
function onGroupChange() {
    var sel = Dom.get('edGroupId'),
    edName = Dom.get('edName'),
    edDescription = Dom.get('edDescription'),
    grpID = sel.options[sel.selectedIndex].value;

    if(grpID<0){
        edName.value = "";
        edDescription.value = "";
    }else{
        edName.value = window.hWin.HEURIST4.detailtypes.groups[window.hWin.HEURIST4.detailtypes.groups.groupIDToIndex[grpID]].name;
        edDescription.value = window.hWin.HEURIST4.detailtypes.groups[window.hWin.HEURIST4.detailtypes.groups.groupIDToIndex[grpID]].description;
    }
}