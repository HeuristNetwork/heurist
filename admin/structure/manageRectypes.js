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
 * A form to edit field type, or create a new field type. It is utilized as pop-up from manageDetailTypes and manageRectypes
 * it may call another pop-ups: selectTerms and selectRecType
 *
 * @author      Stephen White   <stephen.white@sydney.edu.au>
 * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
 * @author      Juan Adriaanse
 * @copyright   (C) 2005-2013 University of Sydney
 * @link        http://Sydney.edu.au/Heurist
 * @version     3.1.0
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @package     Heurist academic knowledge management system
 * @subpackage  AdminStructure
 */

var rectypeManager;

//aliases
var Dom = YAHOO.util.Dom,
Hul = top.HEURIST.util;

function RectypeManager() {

    //private members
    var _className = "RectypeManager";

    var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
        (top.HEURIST.database.name?top.HEURIST.database.name:''));

    //keep table and source - to avoid repeat load and for filtering
    var arrTables = [],
    arrDataSources = [];

    var currentTipId,
    _rolloverInfo;

    var _groups = [],  //for dropdown list
    myDTDrags = {},
    _deleted = [], //keep removed types to exclude on filtering
    _cloneHEU = null; //keep Heurist for rollback in case user cancels group/visibility editing

    var __d = new Date(),
    curtimestamp = __d.getMilliseconds();

    //object to send changes (visibility and group belong) for update on server
    var _oRecordType = {rectype:{
            colNames:{common:['rty_ShowInLists','rty_RecTypeGroupID'], dtFields:[]},
            defs: {}
    }};


    var _updatesCnt = 0, //number of affected rec types (visibility, group belong)
    _filterForAll = true,
    _filterText = "",
    _filterVisible = 0,
    _initRecID;

    var tabView = new YAHOO.widget.TabView();


    //
    //
    //
    function _init()
    {
        var grpID,
        ind = 0,
        index;
        //
        // init tabview with names of group
        for (index in top.HEURIST.rectypes.groups) {
            if( !isNaN(Number(index)) ) {
                _addNewTab(ind,
                    top.HEURIST.rectypes.groups[index].id,
                    top.HEURIST.rectypes.groups[index].name,
                    top.HEURIST.rectypes.groups[index].description);
                ind++;
            }
        }//for groups

        top.HEURIST.parameters = top.HEURIST.parseParams(this.location.search);

        if(top.HEURIST.parameters){ //to open edit recordtyep structure at once
            _initRecID = top.HEURIST.parameters.rtID;
        }

        _rolloverInfo = new HintDiv('inforollover', 260, 170, '<div id="inforollover2"></div>');

        tabView.addTab(new YAHOO.widget.Tab({
                    id: "newGroup",
                    label: "<label title='Create new group, edit or delete an existing group' style='font-style:italic'>+/-</label>",
                    content:
                    ('<div id="formGroupEditor">'+
                        '<style>#formGroupEditor .input-row .input-header-cell {vertical-align: baseline;}</style>'+
                        '<h3>Create a new record type group or edit an existing one</h3><br/>'+
                        '<div class="input-row"><div class="input-header-cell">Group:</div><div class="input-cell"><select id="edGroupId" onchange="onGroupChange()"></select></div></div>'+
                        '<div class="input-row"><div class="input-header-cell">Name:</div><div class="input-cell"><input id="edName" style="width:300px"/></div></div>'+
                        '<div class="input-row"><div class="input-header-cell">Description:</div><div class="input-cell"><input id="edDescription" style="width:300px"/></div></div>'+
                        '<div class="input-row"><div class="input-header-cell"></div><div class="input-cell">'+
                        '<input id="btnGrpSave" style="display:inline-block" type="submit" value="Save" onclick="{rectypeManager.doGroupSave()}" />'+
                        '<input id="btnGrpCancel" type="submit" value="Cancel" onclick="{rectypeManager.doGroupCancel()}" style="margin:0 5px" />'+
                        '<input id="btnGrpDelete" onclick="{rectypeManager.doGroupDelete()}" value="Delete selected group" type="submit" style="margin-left:100px"/>'+
                        '</div></div>'+
                        '</div>')
            }));
        tabView.appendTo("modelTabs");


        /*		var bookmarkedTabViewState = YAHOO.util.History.getBookmarkedState("tabview");
        var initialTabViewState = bookmarkedTabViewState || "tab0";

        YAHOO.util.History.register("tabview", initialTabViewState, function (state) {
        tabView.set("activeIndex", state.substr(3));	//restre the index from history
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
        */			initTabView();

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

        tabView.addTab(new YAHOO.widget.Tab({
                    id: grpID,
                    label: "<label title='"+grpDescription+"'>"+grpName+"</label>",
                    content:
                    ('<div><br>&nbsp;&nbsp;<b>'+ grpDescription + '</b><br>&nbsp;<hr style="width: 100%; height: 1px;"><p>' + //for="filter"
                        '<div style="float:right; display:inline-block; margin-bottom:10px;width:360px;padding-left:10px;">'+
                        '<label>Filter by name:&nbsp;&nbsp;</label>'+
                        '<input type="text" id="filter'+grpID+'" value="">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'+
                        '<input type="checkbox" id="filter'+grpID+'vis" value="1" style="padding-top:5px;">&nbsp;Show active only&nbsp;&nbsp;'+
                        '</div>'+
                        '<div style="float:right; text-align:right">'+
                        '<label id="lblNoticeAboutChanges'+grpID+'" '+
                        'style="padding-left:3px; padding-right:3px; background-color:white; color:red; display: inline-block;"></label>'+
                        '&nbsp;&nbsp;&nbsp;'+
                        '<input id="btnSave'+grpID+'" type="button" value="Save order" '+
                        'style="color:red; display: none !important;margin-right:5px;"/>'+ //inline-block
                        '<input type="button" id="btnAddRecordType'+grpID+'" value="Define new record type / fields" class="add"/>'+
                        //'<input type="button" id="btnAddFieldType'+grpID+'" value="Add Field Type" style="float:right;"/>'+
                        '</div></div>'+
                        '<div id="tabContainer'+grpID+'"></div>'+
                        '<div style="position:absolute;bottom:8px;right:375px;">'+
                        '<input type="button" id="btnAddRecordType'+grpID+'_2" value="Define new record type / fields" class="add"/>'+
                        '</div>'+
                        '</div>')

            }), ind);

        arrTables.push(null);
        arrDataSources.push(null);
    }

    //
    // on changing of tab - create and fill datatable for particular group
    //
    function _handleTabChange (e) {

        _rolloverInfo.close();

        var option;

        var id = e.newValue.get("id");
        if(id==="newGroup"){
            //fill combobox on edit group form
            var sel = Dom.get('edGroupId');

            //celear selection list
            while (sel.length>0){
                sel.remove(0);
            }

            Hul.addoption(sel, "-1", "Add new group");

            var i;
            for (i in _groups){
                if(!Hul.isnull(i)){
                    Hul.addoption(sel, _groups[i].value, _groups[i].text);
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
    function initTabContent(tab) {

        var grpID = tab.get('id');

        _updateSaveNotice(grpID);

        var needFilterUpdate = false;
        var el1 = Dom.get('filter'+grpID);
        var el2 = Dom.get('filter'+grpID+'vis');

        if(_filterForAll){
            var newval = (_filterVisible === 1);
            needFilterUpdate = ((el1.value !== _filterText) || (el2.checked !== newval));
            el1.value = _filterText;
            el2.checked = (_filterVisible === 1);
        }else{
            _filterText = el1.value;
            _filterVisible = el2.checked?1:0;
        }

        var _currentTabIndex = tabView.get('activeIndex');

        var dt = arrTables[_currentTabIndex];

        if(Hul.isnull(dt)) {

            var arr = [],
            rectypeID,
            fi = top.HEURIST.rectypes.typedefs.commonNamesToIndex;
            //create datatable and fill it values of particular group
            for (rectypeID in top.HEURIST.rectypes.typedefs)
            {
                if(!isNaN(Number(rectypeID))) {

                    var td = top.HEURIST.rectypes.typedefs[rectypeID];
                    var rectype = td.commonFields;
                    if (rectype && rectype[fi.rty_RecTypeGroupID] === grpID) {  //(rectype[9].indexOf(grpID)>-1) {
                        arr.push([Number(rectypeID),
                                (Number(rectype[fi.rty_ShowInLists])===1),
                                '', //icon
                                rectype[fi.rty_Name],
                                rectype[fi.rty_Description],
                                rectype[fi.rty_Status],
                                rectype[fi.rty_RecTypeGroupID],
                                null,
                                rectype[fi.rty_ConceptID]]);

                        /*TODO: top.HEURIST.rectype.rectypeUsage[rectypeID].length*/
                    }
                }
            }

            var myDataSource = new YAHOO.util.LocalDataSource(arr,{
                    responseType : YAHOO.util.DataSource.TYPE_JSARRAY,
                    responseSchema : {
                        fields: [ "id", "active", "icon", "thumb", "name", "description", "status", "grp_id", "info", "conceptid"]
                    },
                    doBeforeCallback : function (req,raw,res,cb) {
                        // This is the filter function
                        var data  = res.results || [],
                        filtered = [],
                        i,l;

                        if (!Hul.isempty(_filterText) || _filterVisible===1) {

                            //var fvals = req.split("|");

                            var sByName   = _filterText; //fvals[0].toLowerCase();
                            var iByVisibility = _filterVisible; //fvals[1];

                            // when we change the table, the datasource is not changed
                            // thus we need an additional filter to filter out the deleted rows
                            // and rows that were moved to another groups
                            var tabIndex = tabView.get('activeIndex');
                            var grpID = tabView.getTab(tabIndex).get('id');

                            for (i = 0, l = data.length; i < l; ++i) {

                                // when we change the table, the datasource is not changed
                                //thus we need to update visibility manually
                                var rec_ID = data[i].id;
                                var df = _oRecordType.rectype.defs[rec_ID];
                                if(!Hul.isnull(df)){
                                    data[i].active  = df.common[0];
                                    data[i].grp_id = df.common[1];
                                }

                                if ((data[i].name.toLowerCase().indexOf(sByName)>-1)
                                    && (data[i].grp_id === grpID) //(data[i].grp_id.indexOf(grpID)>-1)
                                    && (_deleted.indexOf(rec_ID)<0)
                                    && (iByVisibility===0 || Number(data[i].active)===iByVisibility))
                                    {
                                    filtered.push(data[i]);
                                }
                            }//for

                            res.results = filtered;
                        }

                        return res;
                    }
            });

            var myColumnDefs = [
                { key: "id", label: "Code", sortable:true, minWidth:25, maxAutoWidth:25, width:25, className:'right',
                    formatter: function(elLiner, oRecord, oColumn, oData) {
                        var rectypeID = oRecord.getData('id');
                        elLiner.innerHTML = '<a href="#search" title="Click to launch search for '+oRecord.getData("name")+' records">'+ rectypeID + '</a>';
                    },
                },
                // 8/10/12 Concept ID hidden by Ian to make space, shoudl show on rollover of local code
                { key: "conceptid", label: "Concept", sortable:true, minWidth:40, maxAutoWidth:40, width:40, className:'right', hidden:true },
                { key: "icon", label: "Icon", className:'center', sortable:false,
                    formatter: function(elLiner, oRecord, oColumn, oData) {
                        var id = oRecord.getData("id");

                        var str1 = top.HEURIST.iconBaseURL + id + ".png?" + curtimestamp;
                        var thumb = top.HEURIST.iconBaseURL + "thumb/th_" + id + ".png?" + curtimestamp;
                        var icon ="<div class=\"rectypeImages\">"+
                        "<a href=\"#edit_icon\">"+
                        "<img src=\"../../common/images/16x16.gif\" style=\"background-image:url("+str1+")\" id=\"icon"+id+"\">"+
                        "</a>"+
                        "<div id=\"thumb"+id+"\" style=\"background-image:url("+thumb+");\" class=\"thumbPopup\">"+
                        "<a href=\"#edit_icon\"><img src=\"../../common/images/16x16.gif\" width=\"75\" height=\"75\"></a>"+
                        "</div>"+
                        "</div>";
                        elLiner.innerHTML = icon;
                }},
                { key: "thumb", label: "Thumb", className:'center', sortable:false,
                    formatter: function(elLiner, oRecord, oColumn, oData) {
                        var id = oRecord.getData("id");

                        var str1 = top.HEURIST.iconBaseURL + "thumb/th_" + id + ".png?" + curtimestamp;
                        var thumb ="<div class=\"rectypeImages\">"+
                        "<a href=\"#edit_thumb\">"+
                        "<img src=\"../../common/images/16x16.gif\" style=\"background-image:url("+str1+")\" id=\"icon"+id+"\">"+
                        "</a>"+
                        "<div id=\"thumb"+id+"\" style=\"background-image:url("+thumb+");\" class=\"thumbPopup\">"+
                        "<a href=\"#edit_icon\"><img src=\"../../common/images/16x16.gif\" width=\"75\" height=\"75\"></a>"+
                        "</div>"+
                        "</div>";
                        elLiner.innerHTML = thumb;
                }},
                { key: "name", label: "Name", sortable:true, minWidth:160, maxAutoWidth:160, width:160, gutter:0,
                    formatter: function(elLiner, oRecord, oColumn, oData) {
                        var str = oRecord.getData("name");
                        var tit = "";
                        if(str.length>25) {
                            tit = str;
                            str = str.substr(0,25)+"&#8230";
                        }
                        elLiner.innerHTML = '<a href="#edit_sctructure" class="bare"><label style="cursor:pointer !important;" title="'+tit+'">'+str+'</label></a>';
                }},
                { key: "edit", label: "Edit", sortable:false, className:'center', minWidth:40, maxAutoWidth:40, width:40, formatter: function(elLiner, oRecord, oColumn, oData) {
                        elLiner.innerHTML = '<a href="#edit_rectype"><img src="../../common/images/edit-recType.png" width="16" height="16" border="0" title="Edit record type" /><\/a>'; }
                },
                { key: "description", label: "Description", sortable:false, minWidth:200, 
                    formatter: function(elLiner, oRecord, oColumn, oData) {
                        var str = oRecord.getData("description");
                        var tit = oRecord.getData("description");
                        if(Hul.isempty(str)){
                            str = "";
                        }/*else if (str.length>40) {
                        tit = str;
                        str = str.substr(0,40)+"&#8230";
                        }*/
                        elLiner.innerHTML = '<a href="#edit_sctructure" class="bare"><span title="'+tit+'">'+str+'</span></a>';
                }},

                { key: "struc", hidden:true, label: "Struc", sortable:false, className:'center', minWidth:40, maxAutoWidth:40, width:40, formatter: function(elLiner, oRecord, oColumn, oData) {
                        elLiner.innerHTML = '<a href="#edit_sctructure"><img src="../../common/images/edit-structure.png" width="16" height="16" border="0" title="Edit record strcuture" /><\/a>'; }
                },
                { key: "active", label: "Show", sortable:false, width:40, formatter:YAHOO.widget.DataTable.formatCheckbox, className:'center' },
                { key: "info", label: "Fields", sortable:false, className:'center', formatter: function(elLiner, oRecord, oColumn, oData) {
                        var rectypeID = oRecord.getData('id');
                        elLiner.innerHTML = '<img src="../../common/images/info.png"'+
                        'style="cursor:pointer;" onclick="rectypeManager.showInfo('+rectypeID+', event)" onmouseout="rectypeManager.hideInfo()"/>'; }
                },
                { key: "usage", label: "Usage", hidden:true },
                { key: "grp_id", label: "Group", sortable:false, minWidth:90, maxAutoWidth:90, width:90, className:'center',
                    formatter: YAHOO.widget.DataTable.formatDropdown,
                    dropdownOptions: _groups },
                /*{ key: null, label: "Del", sortable:false, className:'center', minWidth:40, maxAutoWidth:40, width:40, formatter: function(elLiner, oRecord, oColumn, oData) {
                elLiner.innerHTML = '<a href="#delete"><img src="../../common/images/cross.png" border="0" title="Delete" /><\/a>'; }
                },*/
                { key: "status", label: "Status", sortable:true, className:'center', minWidth:40, maxAutoWidth:40, width:40,
                    formatter: function(elLiner, oRecord, oColumn, oData) {
                        var str = oRecord.getData("status");
                        if (str === "reserved"){
                            rectypeStatus = "<img src=\"../../common/images/lock_bw.png\" title=\"Status: "+str+" - Locked\">";
                        }else{
                            rectypeStatus = "<a href=\"#delete\"><img src=\"../../common/images/cross.png\" border=\"0\" title=\"Status: "+str+" - Delete\"/><\/a>";
                        };
                        elLiner.innerHTML = rectypeStatus;
                }},
            ];

            // Define a custom row formatter function
            var myRowFormatter = function(elTr, oRecord) {
                if (!oRecord.getData('active')) {
                    Dom.addClass(elTr, 'greyout');
                }
                return true;
            };

            var myConfigs = {
                // Enable the row formatter
                formatRow: myRowFormatter,
                //selectionMode: "singlecell",
                paginator : new YAHOO.widget.Paginator({
                        rowsPerPage: 100,
                        totalRecords: arr.length,

                        // use a custom layout for pagination controls
                        template: "&nbsp;Page: {PageLinks} &nbsp Show {RowsPerPageDropdown} per page",

                        // show all links
                        pageLinks: YAHOO.widget.Paginator.VALUE_UNLIMITED,

                        // use these in the rows-per-page dropdown
                        rowsPerPageOptions: [25, 50, 100, 250]

                })
            };

            dt = new YAHOO.widget.DataTable('tabContainer'+grpID, myColumnDefs, myDataSource, myConfigs);

            //click on action images
            dt.subscribe('linkClickEvent', function(oArgs){
                    YAHOO.util.Event.stopEvent(oArgs.event);

                    var dt = this;
                    var elLink = oArgs.target;
                    var oRecord = dt.getRecord(elLink);
                    var rectypeID = oRecord.getData("id");
                    var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
                        (top.HEURIST.database.name?top.HEURIST.database.name:''));

                    if(elLink.hash === "#search") {
                        window.open(top.HEURIST.baseURL+'search/search.html?w=all&q=t:'+rectypeID+'&db='+db,'_blank');
                    }else if(elLink.hash === "#edit_rectype") {
                        _onAddEditRecordType(rectypeID, 0);
                        // TO REMOVE editRectypeWindow(rectypeID);
                    } else if(elLink.hash === "#edit_sctructure") {
                        _editRecStructure(rectypeID);
                    }else if(elLink.hash === "#edit_icon") {
                        _upload_icon(rectypeID,0);
                    }else if(elLink.hash === "#edit_thumb") {
                        _upload_icon(rectypeID,1);
                        /*var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
                        (top.HEURIST.database.name?top.HEURIST.database.name:''));
                        var sURL = top.HEURIST.baseURL + "admin/structure/uploadRectypeIcon.php?db="+ db + "&rty_ID="+rectypeID;
                        top.HEURIST.util.popupURL(top, sURL, {
                        "close-on-blur": false,
                        "no-resize": false,
                        height: 150,
                        width: 340,
                        //callback: icon_refresh
                        });*/

                    }else if(elLink.hash === "#delete"){
                        var iUsage = 0; //@todo oRecord.getData('usage');
                        if(iUsage<1){
                            if(_needToSaveFirst()) { return; }

                            var value = confirm("Do you really want to delete record type # "+rectypeID+" '"+oRecord.getData('name')+"' ?");
                            if(value) {

                                function _updateAfterDelete(context) {

                                    if(!Hul.isnull(context)){
                                        dt.deleteRow(oRecord.getId(), -1);
                                        //  this alert is a pain alert("Record type #"+rectypeID+" was deleted");
                                        top.HEURIST.rectypes = context.rectypes;
                                        _cloneHEU = null;
                                    }
                                }


                                var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
                                var callback = _updateAfterDelete;
                                var params = "method=deleteRT&db=" + db + "&rtyID=" + rectypeID;
                                Hul.getJsonData(baseurl, callback, params);

                            }
                        }//iUsege<1
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

                        //remove destination table
                        _removeTable(newValue, false);

                        //remove from this table and refresh another one
                        window.setTimeout(function() {
                                dt.deleteRow(record.getId(), -1);
                            }, 100);

                        //keep the track of changes in special object
                        _updateRecordType(record);
                        _updateSaveNotice(oldValue);
                    }
            });

            //subscribe on checkbox event (visibility)
            dt.subscribe("checkboxClickEvent", function(oArgs) {
                    var elCheckbox = oArgs.target;
                    var oRecord = dt.getRecord(elCheckbox);
                    var data = oRecord.getData();
                    data.active = elCheckbox.checked;//?1:0;

                    var recindex = dt.getRecordIndex(oRecord);
                    dt.updateRow(recindex, data);

                    //keep the track of changes in special array
                    _updateRecordType(oRecord);
            });

            //
            // keep the changes in object that will be send to server
            //
            function _updateRecordType(oRecord)
            {
                var rty_ID = oRecord.getData('id'),
                grp_id = oRecord.getData('grp_id');

                var newvals = [(oRecord.getData('active')?1:0), grp_id];

                //keep copy
                if(Hul.isnull(_cloneHEU)) { _cloneHEU = Hul.cloneObj(top.HEURIST.rectypes); }
                //update HEURIST
                var td = top.HEURIST.rectypes.typedefs[rty_ID];
                var deftype = td.commonFields;
                deftype[top.HEURIST.rectypes.typedefs.commonNamesToIndex.rty_ShowInList] = newvals[0]; //visibility
                deftype[top.HEURIST.rectypes.typedefs.commonNamesToIndex.rty_RecTypeGroupID] = newvals[1]; //group

                //update keep object
                //var dt_def = _oRecordType.rectype.defs[rty_ID];

                if(Hul.isnull(_oRecordType.rectype.defs[rty_ID])){
                    _oRecordType.rectype.defs[rty_ID] = [];
                    _oRecordType.rectype.defs[rty_ID].push({common:newvals,dtFields:[]});
                    _updatesCnt++;
                }else{
                    _oRecordType.rectype.defs[rty_ID][0].common = newvals;
                }

                _updateSaveNotice(grp_id);

            }

            /* MOVED TO LISTENERS OF INFO IMAGE
            mouse over help colums shows the detailed description
            dt.on('cellMouseoverEvent', function (oArgs) {

            var target = oArgs.target;
            var column = this.getColumn(target);
            var rectypeID = null;

            if(!Hul.isnull(column) && column.key === 'info') {

            var record = this.getRecord(target);
            rectypeID = record.getData('id');
            }
            _showInfoToolTip(rectypeID, oArgs.event);
            });

            dt.on('cellMouseoutEvent', function (oArgs) {
            hideTimer = window.setTimeout(_hideToolTip, 2000);
            });
            */


            arrTables[_currentTabIndex] = dt;
            arrDataSources[_currentTabIndex] = myDataSource;

            // add listeners
            /*Ian's
            var filter_forall = Dom.get('filter_forall'+grpID);
            filter_forall.onchange = function (e) {
            _filterForAll = filter_forall.checked;
            };
            */
            var filter = Dom.get('filter'+grpID);
            filter.onkeyup = function (e) {
                clearTimeout(filterTimeout);
                filterTimeout = setTimeout(updateFilter,600);  };

            var filtervis = Dom.get('filter'+grpID+'vis');
            filtervis.onchange = function (e) {
                clearTimeout(filterTimeout);
                updateFilter();  };

            var btnAddRecordType = Dom.get('btnAddRecordType'+grpID);
            btnAddRecordType.onclick = function (e) {
                if(confirm('Before defining new record types we suggest importing suitable definitions\nfrom Heurist databases registered in the Heurist clearinghouse\n\n(Import Structure link on the left)')){
                    var currentTabIndex = tabView.get('activeIndex');
                    var grpID = tabView.getTab(currentTabIndex).get('id');
                    _onAddEditRecordType(0, grpID);
                }
            };
            var btnAddRecordType2 = Dom.get('btnAddRecordType'+grpID+'_2');
            btnAddRecordType2.onclick = btnAddRecordType.onclick;
            /*var btnAddFieldType = Dom.get('btnAddFieldType'+grpID);
            btnAddFieldType.onclick = function (e) {
            var currentTabIndex = tabView.get('activeIndex');
            var grpID = tabView.getTab(currentTabIndex).get('id');
            _onAddFieldType(0, 0);
            };*/


            //$$('.ellipsis').each(ellipsis);

        }//if(dt==undefined || dt==null)
        else if (needFilterUpdate){
            updateFilter();
        }

        //do it only once - on load
        if(!Hul.isnull(_initRecID)){
            _editRecStructure(_initRecID);
            _initRecID = null
        }

    }//initTabContent =============================================== END DATATABLE INIT


    /**
     * Show popup div with information about field types in use for given record type
     */
    function _showInfoToolTip(rectypeID, event) {

        var forceHideTip = true;
        var textTip;

        if(!Hul.isnull(rectypeID)){
            if(currentTipId !== rectypeID) {
                currentTipId = rectypeID;

                var recname = top.HEURIST.rectypes.names[rectypeID];
                if(recname.length>40) { recname = recname.substring(0,40)+"..."; }
                //find all records that reference this type
                var details = top.HEURIST.rectypes.typedefs[rectypeID].dtFields;
                textTip = '<h3 style="padding-bottom: 5px;display:block">'+recname+'</h3>'+
                '<b>Fields:</b><label style="color: #999;margin-left:5px">Click on field type to edit</label><ul>';

                var detail;
                for(detail in details) {
                    textTip = textTip + "<li><a href='javascript:void(0)' onClick=\"rectypeManager.editDetailType("+detail+")\">" + details[detail][0] + "</a></li>";
                }
                textTip = textTip + "</ul>";
            } else {
                forceHideTip = false;
            }
        }
        if(!Hul.isnull(textTip)) {

            var xy = Hul.getMousePos(event);
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
            if( (Number(tabIndex) === Number(currIndex)) && needRefresh)
                {
                setTimeout(function(){initTabContent(tabView.getTab(tabIndex));},500);
            }

        }
    }

    //  SAVE BUNCH OF TYPES =============================================================
    //
    // send updates to server
    //
    function _updateRecordTypeOnServer(event) {
        var str = YAHOO.lang.JSON.stringify(_oRecordType);
        //DEBUG alert("Stringified changes: " + str);

        if(!Hul.isnull(str)) {
            //_updateResult(""); //debug
            //return;//debug

            var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
            var callback = _updateResult;
            var params = "method=saveRT&db="+db+"&data=" + encodeURIComponent(str);
            Hul.getJsonData(baseurl, callback, params);
        }
    }
    //
    // after saving a bunch of rec types
    //
    function _updateResult(context) {

        if(!Hul.isnull(context)){
            var error = false,
            report = "",
            ind;

            for(ind in context.result)
            {
                if(!Hul.isnull(ind)){
                    var item = context.result[ind];
                    if(isNaN(item)){
                        Hul.showError(item);
                        error = true;
                    }else{
                        recTypeID = Number(item);
                        if(!Hul.isempty(report)) { report = report + ","; }
                        report = report + recTypeID;
                    }
                }
            }

            if(!error) {
                if(report.indexOf(",")>0){
                    // this alert is a pain  alert("Record types with IDs :"+report+ " were succesfully updated");
                }else{
                    // this alert is a pain  alert("Record type with ID " + report + " was succesfully  updated");
                }
                _clearGroupAndVisibilityChanges(false);
            }
            top.HEURIST.rectypes = context.rectypes;
            _cloneHEU = null;
        }
    }

    /**
     * Show/hide information about number of fieldtypes with changed activity
     */
    function _updateSaveNotice(grp_id){

        //var _lblNotice = Dom.get("lblNoticeAboutChanges"+grp_id);
        //var _btnSave   = Dom.get("btnSave"+grp_id);

        if(_updatesCnt>0){
            _updateRecordTypeOnServer();
            /*
            _lblNotice.innerHTML = 'You have changed <b>'+_updatesCnt+'</b> record type'+((_updatesCnt>1)?'s':'');
            _btnSave.style.display = 'inline-block';
            _btnSave.onclick = _updateRecordTypeOnServer;*/
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
        _oRecordType.rectype.defs = {}; //clear keeptrack

        _updateSaveNotice(_getGroupByIndex(tabView.get('activeIndex')));

        if(_cloneHEU) { top.HEURIST.rectypes = Hul.cloneObj(_cloneHEU); }
        _cloneHEU = null;

        if(withReload){
            var ind;
            for(ind in arrTables){
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
                _updateRecordTypeOnServer(null);
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
    function updateFilter() {
        // Reset timeout
        filterTimeout = null;

        var tabIndex = tabView.get('activeIndex');
        var dtable = arrTables[tabIndex];
        var dsource = arrDataSources[tabIndex];

        // Reset sort
        var state = dtable.getState();
        state.sortedBy = {key:'name', dir:YAHOO.widget.DataTable.CLASS_ASC};

        var grpID = _getGroupByIndex(tabIndex);

        _filterText = Dom.get('filter'+grpID).value;
        _filterVisible = Dom.get('filter'+grpID+'vis').checked?1:0;

        // Get filtered data
        dsource.sendRequest(_filterText+'|'+_filterVisible, {
                success : dtable.onDataReturnInitializeTable,
                failure : dtable.onDataReturnInitializeTable,
                scope	  : dtable,
                argument : { pagination: { recordOffset: 0 } } // to jump to page 1
        });
    }

    //
    // call new popup - to edit detail type
    //
    function _editDetailType(detailTypeID) {
        var URL = "";
        if(detailTypeID) {
            URL = top.HEURIST.basePath + "admin/structure/editDetailType.html?db="+db+"&detailTypeID="+detailTypeID;
        }
        else {
            URL = top.HEURIST.basePath + "admin/structure/editDetailType.html?db="+db;
        }

        var dim = Hul.innerDimensions(window);

        Hul.popupURL(top, URL, {
                "close-on-blur": false,
                "no-resize": false,
                height: 700,
                width: 700,
                callback: function(changedValues) {
                    if(Hul.isnull(changedValues)) {
                        // Canceled
                    } else {
                        // TODO: reload datatable
                    }
                }
        });
    }

    //
    // edit strcuture (from image link in table)
    //
    function _editRecStructure(rty_ID) {

        var URL = top.HEURIST.basePath + "admin/structure/editRecStructure.html?db="+db+"&rty_ID="+rty_ID;
        //this.location.replace(URL);

        var dim = Hul.innerDimensions(this.window);

        Hul.popupURL(top, URL, {
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
    }

    function _updateTitleMask(rty_ID){
        var URL = top.HEURIST.basePath + "admin/verification/recalcTitlesSpecifiedRectypes.php?db="+db+"&recTypeIDs="+rty_ID;

        Hul.popupURL(top, URL, {
                "close-on-blur": false,
                "no-resize": true,
                height: 400,
                width: 400,
                callback: function(context) {
                }
        });
    }

    /*  THIS feature is removed by Ian's request
    // listener of add button
    //
    function _onAddFieldType(){
    var url = top.HEURIST.basePath + "admin/structure/editDetailType.html?db="+db;

    Hul.popupURL(top, url,
    {   "close-on-blur": false,
    "no-resize": false,
    height: 430,
    width: 650,
    callback: function(context) {
    // NO ACTION REQUIRED HERE
    }
    });
    }*/

    //
    // listener of add button
    //
    function _onAddEditRecordType(rty_ID, rtg_ID){

        if(_needToSaveFirst()) { return; }

        var url = top.HEURIST.basePath + "admin/structure/editRectype.html?db="+db;
        if(rty_ID>0){
            url = url + "&rectypeID="+rty_ID; //existing
        }else{
            url = url + "&groupID="+rtg_ID; //new one
        }
        var dim = Hul.innerDimensions(this.window);
        Hul.popupURL(top, url,
            {   "close-on-blur": false,
                "no-resize": false,
                height: dim.h*0.9,
                width: 700,
                callback: function(context) {
                    if(!Hul.isnull(context)){

                        var rty_ID;

                        if(context.result){
                            //update id
                            rty_ID = Math.abs(Number(context.result[0]));

                            //if user changes group in popup need update both  old and new group tabs
                            var grpID_old = -1,
                            ind_grpfld = top.HEURIST.rectypes.typedefs.commonNamesToIndex.rty_RecTypeGroupID;

                            if(Number(context.result[0])>0){
                                grpID_old = top.HEURIST.rectypes.typedefs[rty_ID].commonFields[ind_grpfld];
                            }

                            //refresh the local heurist
                            top.HEURIST.rectypes = context.rectypes;
                            _cloneHEU = null;

                            //detect what group
                            var grpID = top.HEURIST.rectypes.typedefs[rty_ID].commonFields[ind_grpfld];

                            var d = new Date();
                            curtimestamp = d.getMilliseconds();

                            _removeTable(grpID, true);
                            if(grpID_old!==grpID){
                                _removeTable(grpID_old, true);
                            }

                        }//context.result
                        else{
                            rty_ID = context.rty_ID;
                            icon_refresh(rty_ID);
                        }

                        if(context.isOpenEditStructure){
                            _editRecStructure(rty_ID);
                            //alert("open edit strcutre");
                        }
                        if(context.changeTitleMask){
                            _updateTitleMask(rty_ID);
                        }

                        /*
                        //is it current tab
                        var ind = _getIndexByGroupId(grpID);
                        var ind_old = _getIndexByGroupId(grpID_old);

                        //refresh tables
                        var tabIndex = tabView.get('activeIndex');

                        var ndt = arrTables[ind];
                        if(ndt!=null){
                        arrTables[ind] = null;
                        //if it is current tab force datatable refresh
                        if(tabIndex == ind)
                        {
                        initTabContent(tabView.getTab(tabIndex));
                        }
                        }
                        if(ind_old>=0){
                        ndt = arrTables[ind_old];
                        if(ndt!=null){
                        arrTables[ind_old] = null;
                        if (tabIndex == ind_old)
                        {
                        initTabContent(tabView.getTab(tabIndex));
                        }
                        }
                        }
                        */
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
        description = Dom.get('edDescription').value.replace(/^\s+|\s+$/g, ''), //trim
        grpID = sel.options[sel.selectedIndex].value,
        grp; //object in HEURIST

        if(Hul.isempty(name)){
            alert('Group name is required. Please sepecify it');
            Dom.get('edName').focus();
            return;
        }
        if(Hul.isempty(description)){
            alert('Group description is required. Please sepecify it');
            Dom.get('edDescription').focus();
            return;
        }


        var orec = {rectypegroups:{
                colNames:['rtg_Name','rtg_Description'],
                defs: {}
        }};


        //define new or exisiting
        if(grpID<0) {
            grp = {name: name, description:description};
            orec.rectypegroups.defs[-1] = [];
            orec.rectypegroups.defs[-1].push({values:[name, description]});
        }else{
            //for existing - rename
            grp = top.HEURIST.rectypes.groups[top.HEURIST.rectypes.groups.groupIDToIndex[grpID]];
            grp.name = name;
            grp.description = description;
            orec.rectypegroups.defs[grpID] = [name, description];
        }

        //top.HEURIST.rectypes.groups[grpID] = grp;
        var str = YAHOO.lang.JSON.stringify(orec);

        //DEBUG alert(str);

        //make this tab active
        function _updateOnSaveGroup(context){
            //for new - add new tab
            if(!Hul.isnull(context))
                {
                //for new - add new tab
                if(!Hul.isnull(context['0'].error)){
                    Hul.showError(context['0']);
                }else{
                    var ind;
                    top.HEURIST.rectypes = context.rectypes;
                    _cloneHEU = null;

                    if(grpID<0){

                        _refreshAllTables();

                        grpID = context['0'].result;
                        ind = _groups.length;
                        _addNewTab(ind, grpID, name, description);
                        dragDropEnable();
                    }else{
                        //update label
                        for (ind in _groups){
                            if(!Hul.isnull(ind) && Number(_groups[ind].value)===Number(grpID)){
                                var tab = tabView.getTab(ind);
                                var el = tab._getLabelEl();
                                el.innerHTML = "<label title='"+description+"'>"+name+"</label>";
                                _groups[ind].text = name;
                                break;
                        }}
                        _refreshAllTables();
                    }
                    tabView.set("activeIndex", ind);
                }
            }
        }


        if(!Hul.isnull(str)){

            var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
            var callback = _updateOnSaveGroup;
            var params = "method=saveRTG&db="+db+"&data=" + encodeURIComponent(str);

            Hul.getJsonData(baseurl, callback, params);
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

        var orec = {rectypegroups:{
                colNames:['rtg_Order'],
                defs: {}
        }};

        var i,
        parentNode = document.getElementsByClassName("yui-nav")[0];

        for (var i = 0; i < parentNode.childNodes.length; i++) {
            var child = parentNode.childNodes[i];
            var id = child.id;
            var ind = top.HEURIST.rectypes.groups.groupIDToIndex[id];
            if(!Hul.isnull(ind)){
                grp = top.HEURIST.rectypes.groups[ind];
                grp.order = i;
                orec.rectypegroups.defs[id] = [i];
            }
        }

        var str = YAHOO.lang.JSON.stringify(orec);

        if(!Hul.isnull(str)) {
            var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
            var callback = null;//_updateOnSaveGroup;
            var params = "method=saveRTG&db="+db+"&data=" + encodeURIComponent(str);

            top.HEURIST.util.getJsonData(baseurl, callback, params);
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

        var grp = top.HEURIST.rectypes.groups[top.HEURIST.rectypes.groups.groupIDToIndex[grpID]];

        if(!Hul.isnull(grp.types) && grp.types.length>0)
            {
            alert("This group cannot be deleted as it contains record types - please move them first");
        }else{
            var r=confirm("Confirm the deletion of group '"+grp.name+"'");
            if (r) {
                var ind;
                //
                function _updateAfterDeleteGroup(context) {
                    if(!Hul.isnull(context)){
                        //remove tab from tab view and select 0 index
                        var id = _groups[ind].value;
                        if (myDTDrags[id]) {
                            myDTDrags[id].unreg();
                            delete myDTDrags[id];
                        }

                        _groups.splice(ind, 1);
                        arrTables.splice(ind, 1);
                        arrDataSources.splice(ind, 1);

                        tabView.removeTab(tabView.getTab(ind));
                        tabView.set("activeIndex", 0);
                        top.HEURIST.rectypes = context.rectypes;
                        _cloneHEU = null;

                        _refreshAllTables();
                    }
                }


                //1. find index of tab to be removed
                for (ind in _groups){
                    if(!Hul.isnull(ind) && Number(_groups[ind].value)===Number(grpID)){

                        var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
                        var callback = _updateAfterDeleteGroup;
                        var params = "method=deleteRTG&db="+db+"&rtgID=" + grpID;
                        Hul.getJsonData(baseurl, callback, params);

                        break;
                }}

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
        //return top.HEURIST.rectypes.groups.groupIDToIndex[grpID]

        var ind;
        for (ind in _groups){
            if(!Hul.isnull(ind) && Number(_groups[ind].value)===Number(grpID)){
                return Number(ind);
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


    //public members
    var that = {

        init: function(){
            _init();
        },
        editDetailType: _editDetailType,
        doGroupSave: function(){ _doGroupSave(); },
        doGroupDelete: function(){ _doGroupDelete(); },
        doGroupCancel: function(){ _doGroupCancel(); },
        hasChanges: function(){ return  (_updatesCnt>0); },
        showInfo: function(rectypeID, event){ _showInfoToolTip( rectypeID, event ); },
        hideInfo: function() {  currentTipId = ''; _rolloverInfo.hide();},

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
//
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
        edName.value = top.HEURIST.rectypes.groups[top.HEURIST.rectypes.groups.groupIDToIndex[grpID]].name;
        edDescription.value = top.HEURIST.rectypes.groups[top.HEURIST.rectypes.groups.groupIDToIndex[grpID]].description;
    }

}

function _upload_icon(rectypeID,mode) {
    if (!mode){
        mode = 0;
    }

    var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));
    var sURL = top.HEURIST.baseURL + "admin/structure/uploadRectypeIcon.php?db="+ db + "&mode="+mode+"&rty_ID=" + rectypeID;
    top.HEURIST.util.popupURL(top, sURL, {
            "close-on-blur": false,
            "no-resize": false,
            height: (mode==0?200:250),
            width: 350,
            callback: icon_refresh(rectypeID)
    });

}

function icon_refresh(rectypeID) {
    if(rectypeID){
        var d = new Date();
        curtimestamp = d.getMilliseconds();

        var db = top.HEURIST.database.id;
        var imgIcon = "#icon" + rectypeID;
        var img = $(imgIcon);
        if(img){
            img.css('background-image', 'url("' + top.HEURIST.iconBaseURL+rectypeID+".png?"+curtimestamp+'")');
        }

        var imgThumb = "#thumb" + rectypeID;
        img = $(imgThumb);
        if(img){
            img.css('background-image', 'url("' + top.HEURIST.iconBaseURL + "thumb/th_" + rectypeID+".png?"+curtimestamp+'")');
            ///img.style.backgroundImage = 'url("' + top.HEURIST.iconBaseURL + "thumb/th_" + rectypeID + ".png?" + curtimestamp+'") !important';
        }
    }
}
/*

function ellipsis(e) {
var w = e.getWidth() - 10000;
var t = e.innerHTML;
e.innerHTML = "<span>" + t + "</span>";
e = e.down();
while (t.length > 0 && e.getWidth() >= w) {
t = t.substr(0, t.length - 1);
e.innerHTML = t + "...";
}
}

document.write('<style type="text/css">' +
'.ellipsis { margin-right:-10000px; }</style>');

$j(document).ready(function(){
$j('.ellipsis').each(function (i) {
var e = this;
var w = $j(e).width() - 10000;
var t = e.innerHTML;
$j(e).html("<span>" + t + "</span>");
e = $j(e).children(":first-child")
while (t.length > 0 && $j(e).width() >= w) {
t = t.substr(0, t.length - 1);
$j(e).html(t + "...");
}
});
});
*/
