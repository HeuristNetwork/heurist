/*
* Copyright (C) 2005-2019 University of Sydney
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
* EditRecStructure - class for pop-up edit record type structure
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2019 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

// EditRecStrucutre object
var editStructure;

// reference to popup window - select or add new field type
var popupSelect = null;

//aliases
var Dom = YAHOO.util.Dom,
Event = YAHOO.util.Event,
DDM = YAHOO.util.DragDropMgr,
Hul = window.hWin.HEURIST4.util;

function EditRecStructure() {

    var _className = "EditRecStructure",
    _myDataTable,
     _actionInProgress = false,
    rty_ID,
    _isDragEnabled = false,
    _updatedDetails = [], //list of dty_ID that were affected with edition
    _updatedFields = [],  //list of indexes in fieldname array that were affected
    _expandedRecord = null, //rstID of record (row) in design datatable that is (was) expanded (edited)
    _highightedRow = null,
    _isServerOperationInProgress = false, //prevents send request if there is not respoce from previous one
    _isReserved = false,
    _rty_Status,
    myDTDrags = {},
    _fieldMenu = null,
    db,
    warningPopupID = null,
    _structureWasUpdated = false,
    
    _newOptionalFields = {},
    
    _isStreamLinedAddition = true, 
    _isStreamLinedAdditionAction = false;

    // buttons on top and bottom of design tab
    var hToolBar = '<div style=\"display:none;\">'+
        //<div style="display:inline-block; text-align:left">
        //'<input type="button" value="collapse all" onclick="onCollapseAll()"/>'+
        //'<input type="button" value="Enable Drag" onclick="onToggleDrag(event)"/></div>'+

        '<input style="visibility:hidden ;width:0; color:red;" type="button" id="btnSaveOrder" value="Save order" onclick="onUpdateStructureOnServer(false)"/>'+

        '<input type="button" style="visibility:hidden; width:0;" value="Define New Base Field Type" onClick="onDefineNewType()" '+
        'title="Add a new base field type which can be used by all record types - use an existing field (Add Field) if a suitable one exists" class="add"/>'+

        '<input type="button" style="visibility:hidden; width:0;" value="Add Section" onClick="onAddSeparator()" '+
        'title="Add a new section heading, to break the data entry form up into groups of related fields. Heading is inserted at bottom, drag up into required position." class="add"/>'+
        '</div>'+

        '<span style="padding-left:20px">'+   //style="float:right; text-align:right;"
        '<span id="div_rty_ID" style="font-size:11px;font-weight:normal;"></span>&nbsp;'+
        '<span id="div_rty_ConceptID" style="font-size:11px;font-weight:normal;"></span>&nbsp;&nbsp;</span>'+
        '<span style="display:none;">'+
        '<a href="#" onclick="{onEditRecordType();}">edit general description<img src="../../../common/images/edit-pencil.png" width="16" height="16" border="0" title="Edit" /></a>&nbsp;&nbsp;'+
        '<a href="#" onclick="{editStructure.doEditTitleMask(false);}">edit title mask<img src="../../../common/images/edit-pencil.png" width="16" height="16" border="0" title="Edit" /></a>&nbsp;&nbsp;&nbsp;&nbsp;</span>'+
        '<span style="float:right; text-align:right;">'+ 
        '<input class="save-btn" type="button" value="Save/Close" onClick="editStructure.closeWin();"/>'+
        '</span>'+
        
        '<div  id="recStructure_toolbar" style=\"text-align:right;float:right;display:none;\">'+
        '<input id="btn_addfield" type="button" style="margin:0 5px" value="Add field" onclick="onAddNewDetail()" '+
        'title="Insert an existing field into the data entry form for this record type" class="add"/>'+
        // note class=add --> global.css add-button, is set to float:right, but class adds the + to the button      ; margin:0 5px
        // Removed Ian 8/10/12, moved to within insertion of existing fields to encourge re-use
        // '<input type="button" value="Save" onclick="onUpdateStructureOnServer(true)"/>'+
        //'Add existing fields where possible &nbsp;&nbsp; '+
        '</div>';    

    /**
    * Initialization of input form
    *
    * Reads GET parameters, creates TabView and triggers init of first tab
    */
    function _init() {
        

        db = window.hWin.HAPI4.database;

        // read GET parameters
        if (location.search.length > 1) {
            rty_ID = Hul.getUrlParameter('rty_ID', location.search);
        }
        _refreshTitleAndIcon();
        
        Dom.get("modelTabs").innerHTML = '<div id="tabDesign"><div id="tableContainer"></div></div>';
        _initTabDesign(null);
        
        
        var df = window.hWin.HAPI4.get_prefs_def('editRecStructure_StreamLinedAddition', 1);
        $('#cbStreamLinedAddition').prop('checked', df==1);
        
        //click outside of list
        $(document).click(function(event){
           if($(event.target).parents('.list_div').length==0) { 
                $('.list_div').hide(); 
           };
        });
        
    }

    /**
    * Initializes design tab (list of detailtypes in expandable datatable)
    *
    * @param _rty_ID record type ID for which user defines the structure
    */
    function _initTabDesign(_rty_ID){

        if(Hul.isnull(_myDataTable) || !Hul.isnull(_rty_ID)){

            if(!Hul.isnull(_rty_ID)) { rty_ID = _rty_ID; }
            if(Hul.isnull(rty_ID)) { return; }

            // take list of detail types from HEURIST DB
            var typedef = window.hWin.HEURIST4.rectypes.typedefs[rty_ID];
            var fi = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex;

            if(Hul.isnull(typedef)){
                alert("Internal error: Record type ID "+rty_ID+" does not exist. Please report as bug.");
                rty_ID = null;
                return;
            }


            _rty_Status = typedef[ window.hWin.HEURIST4.rectypes.typedefs.commonNamesToIndex.rty_Status];
            _isReserved = (_rty_Status==='reserved'); // || (_rty_Status==='approved');

            //clear _updatedDetails and _updatedFields
            _clearUpdates();

            var expansionFormatter  = function(el, oRecord, oColumn, oData) {
                var cell_element = el.parentNode;

                //Set trigger
                if( oData ){ //Row is closed
                    Dom.addClass( cell_element,
                        "yui-dt-expandablerow-trigger" );
                }

            };
            
            //fill the values of record detail strcutures
            var arr = [];
            var _dts = typedef.dtFields;

            //only for this group and visible in UI
            if(!Hul.isnull(_dts)){
                var rst_ID;  //field type ID
                for (rst_ID in _dts) {
                    var statusLock;
                    var aval = _dts[rst_ID];

                    var fieldType = window.hWin.HEURIST4.detailtypes.typedefs[rst_ID].commonFields[window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex.dty_Type];
                    var conceptCode = window.hWin.HEURIST4.detailtypes.typedefs[rst_ID].commonFields[window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex.dty_ConceptID];
                    
                    arr.push([ rst_ID,
                        rst_ID,
                        rst_ID,
                        Number(aval[fi.rst_DisplayOrder]),
                        window.hWin.HEURIST4.detailtypes.typedefs[rst_ID].commonFields[window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex.dty_Name], //field name
                        aval[fi.rst_DisplayName],
                        fieldType, //field type
                        aval[fi.rst_RequirementType],
                        aval[fi.rst_DisplayWidth],
                        aval[fi.rst_MinValues],
                        aval[fi.rst_MaxValues],
                        aval[fi.rst_DefaultValue],
                        aval[fi.rst_Status],
                        aval[fi.rst_NonOwnerVisibility],
                        aval[fi.rst_DisplayHelpText],
                        '',
                        conceptCode,
                        aval[fi.rst_CreateChildIfRecPtr],
                        aval[fi.rst_PointerMode], 
                        aval[fi.rst_PointerBrowseFilter],
                        aval[fi.rst_DisplayHeight],
                        aval[fi.rst_DisplayExtendedDescription]]);
                    //statusLock]);   last column stores edited values and show either delete or lock image
                }
            }

            if(arr.length==0){
                $("#recStructure_toolbar").show();
            }
            
            // define datasource for datatable
            var myDataSource = new YAHOO.util.LocalDataSource(arr,{
                responseType : YAHOO.util.DataSource.TYPE_JSARRAY,
                responseSchema : {
                    fields: [  "addColumn", "rst_ID", "expandColumn","rst_DisplayOrder",
                        "dty_Name",
                        "rst_DisplayName", "dty_Type", "rst_RequirementType",
                        "rst_DisplayWidth", "rst_MinValues", "rst_MaxValues", "rst_DefaultValue", "rst_Status",
                        "rst_NonOwnerVisibility", "rst_DisplayHelpText", "rst_values", "conceptCode", 
                        "rst_CreateChildIfRecPtr", "rst_PointerMode", "rst_PointerBrowseFilter", 
                        "rst_DisplayHeight", "rst_DisplayExtendedDescription"]
                }
            });

            function _hidevalueforseparator(elLiner, oRecord, oColumn, oData){
                var type = oRecord.getData("dty_Type");
                if(type!=='separator'){
                    elLiner.innerHTML = oData;
                    return true;
                }else{
                    return false;
                }
            }
            
            var titleEditor = new YAHOO.widget.TextboxCellEditor({ 
                            disableBtns:true,
                            /*validator: function(inputValue, currentValue, editorInstance){
                               return;  //if returns undefined it restores old value
                            },
                            doAfterRender:function(){
                               console.log(arguments);  
                            },*/
                            asyncSubmitter:function(fnCallback, oNewValue){
                                
                                var rec = this.getRecord();
                                if(oNewValue=='') {
                                    fnCallback(true, rec.getData("rst_DisplayName")); //restore old value
                                }else{
                                    var swarn = window.hWin.HEURIST4.ui.validateName(oNewValue, "Prompt (display name)", 255)
                                    if(swarn!=""){
                                        alert(swarn);
                                        fnCallback(true, rec.getData("rst_DisplayName")); //restore old value
                                    }else{
                                        _updateSingleField(rec.getData("rst_ID"), 'rst_DisplayName', 
                                                rec.getData("rst_DisplayName"), oNewValue, fnCallback); //fnCallback(bSuccess, oNewValue)   
                                    }
                                }
                            } 
                                });
           titleEditor.subscribe('keydownEvent',function(oArgs){ //editor, event
                            var event = oArgs.event;
                            return window.hWin.HEURIST4.ui.preventChars(event);
                            });                     


            var myColumnDefs = [
                {
                    key:"rst_ID", label: "Divider", sortable:false, width:45,
                    formatter: function(elLiner, oRecord, oColumn, oData){

                        /* icon                        
                        elLiner.innerHTML = "<img src='../../../common/images/insert_header.png' style='cursor:pointer;' "
                        +" title='Click this button to insert a new section header at this point in the record structure / data entry form' "+
                        " rst_ID='"+oRecord.getData("rst_ID")+"' onclick='{editStructure.onAddFieldAtIndex(event, true);}' >";
                        */

                        //label
                        elLiner.innerHTML = 
                        '<div style="font-size:0.75em" '
                        +" title='Click to insert a new section divider at this point in the record structure / data entry form' "+
                        " rst_ID='"+oRecord.getData("rst_ID")+"'>"+
                        'Add&nbsp;divider</div>'+
                        '<img src="../../../common/images/arrow_right2.png" style="float:right"'
                        +' rst_ID="'+oRecord.getData("rst_ID")+'"/>';
                        
                        $(elLiner).css({cursor:'pointer'})
                            .attr('rst_ID', oRecord.getData("rst_ID"));
                        $(elLiner).off('click');
                        $(elLiner).click(function(event){
                                    editStructure.onAddFieldAtIndex(event, true);
                                    window.hWin.HEURIST4.util.stopEvent(event);
                            });
                        //elLiner.innerHTML = oData;
                        //elLiner.title = oRecord.getData("conceptCode");
                    }
                },
                {
                    key:'addColumn',
                    label: "Field",
                    sortable:false, width:40,
                    formatter: function(elLiner, oRecord, oColumn, oData) {
                        /*
                        elLiner.innerHTML = "<img src='../../../common/images/insert_field.png' style='cursor:pointer;' "
                        +" title='Click this button to insert a new field at this point in the record structure / data entry form' "+
                        " rst_ID='"+oRecord.getData("rst_ID")+"' onclick='{editStructure.onAddFieldAtIndex(event, false);}' >";*/
                        //label
                        elLiner.innerHTML = 
                        '<div style="font-size:0.75em;width:40px;font-weight:bold;" '
                        +" title='Click to insert a new field at this point in the record structure / data entry form' "+
                        " rst_ID='"+oRecord.getData("rst_ID")+"'>"+
                        'Add&nbsp;field</div>'+
                        '<img src="../../../common/images/arrow_right2.png" style="float:right"'
                        +' rst_ID="'+oRecord.getData("rst_ID")+'"/>';
                        
                        
                        /*var cell_element = elLiner.parentNode;
                        //Set trigger
                        if( oData ){ //Row is closed
                            Dom.addClass( cell_element, "yui-dt-expandablerow-trigger-conditional" );
                        }*/
                        
                        $(elLiner).css({cursor:'pointer'})
                            .attr('rst_ID', oRecord.getData("rst_ID"))
                        $(elLiner).off('click');
                        $(elLiner).click(function(event){
                                
                                    _isStreamLinedAddition = $('#cbStreamLinedAddition').is(':checked');
                                
                                    if(!_isStreamLinedAddition){
                                        editStructure.onAddFieldAtIndex(event, false);    
                                        window.hWin.HEURIST4.util.stopEvent(event);
                                    }else{
                                        var oArgs = {event:'MouseEvent', target: elLiner};
                                        onCellClickEventHandler( oArgs ); 
                                    }
                                });

                    }                                    
                },
                {
                    key:'rst_NonOwnerVisibility',
                    label: "<img src='../../../common/images/up-down-arrow.png'>",
                    sortable:false, width:20,
                    formatter: function(elLiner, oRecord, oColumn, oData) {
                        if(Number(_expandedRecord) !== Number(oRecord.getData("rst_ID"))){
                            elLiner.innerHTML = "<img src='../../../common/images/up-down-arrow.png' title='Drag up/down to change order of fields in the data entry form' style='cursor:pointer;'>";
                        }
                    }
                },
                {
                    key:"expandColumn",
                    label: "Edit",  width:20,
                    hidden: false, //width : "16px",
                    sortable:false,
                    formatter: function(elLiner, oRecord, oColumn, oData){
                        elLiner.innerHTML = '<img src="../../../common/images/edit-recType.png" width="16" height="16" border="0" title="Edit" />'; 
                        
                        var cell_element = elLiner.parentNode;

                        //Set trigger
                        if( oData ){ //Row is closed
                            Dom.addClass( cell_element, "yui-dt-expandablerow-trigger" );
                        }
                        
                    }
                    //expansionFormatter
                },
                {
                    key:"rst_DisplayOrder", label: "Order", sortable:true, hidden:true
                },
                {
                    key:"rst_DisplayName", label: "Field prompt in form", width:150, minWidth:150, sortable:false,
                    
                    editor: titleEditor,
                            
                    formatter: function(elLiner, oRecord, oColumn, oData) {
                        elLiner.innerHTML = oData;
                        
                        var s = oRecord.getData("rst_DisplayHelpText");
                        s = s.trim();
                        var s1 = '', k = 0;
                        while (k<s.length && k<500){
                            s1 = s1 + s.substring(k,k+60) + "\n";
                            k = k + 60;
                        }
                        s = oRecord.getData("rst_DisplayExtendedDescription");
                        s = s.trim();
                        var s2 = ''; 
                        k = 0;
                        while (k<s.length && k<500){
                            s2 = s2 + s.substring(k,k+60) + "\n";
                            k = k + 60;
                        }

                        var ccode = oRecord.getData("conceptCode");
                        elLiner.title = 
                        "ID: "+oRecord.getData("rst_ID")+
                        (ccode?("\n\nConcept code: "+ccode):'')+
                        "\n\nBase field type: "+oRecord.getData("dty_Name")+"\n\n"+
                        "Help: "+s1+"\n"+
                        "Ext: "+s2+"\n"+
                        "For non owner: " + oRecord.getData("rst_NonOwnerVisibility");
                        
                        var type = oRecord.getData("dty_Type");
                        if(type=='separator'){
                            $(elLiner).css({'text-decoration':'underline'});//'font-size':'1.2em',,'font-weight':'bold','cursor':'normal'});
                            elLiner.innerHTML = elLiner.innerHTML.toUpperCase();
                        }
                        $(elLiner).css({'font-weight':'bold'})
                               .addClass('yui-dt-liner-editable');   
                        

                    }
                },
                { key: "dty_Type", label: "Data Type", sortable:false, width:90, 
                    formatter: function(elLiner, oRecord, oColumn, oData) {
                        var type = oRecord.getData("dty_Type");
                        if(type!=='separator'){
                            elLiner.innerHTML = window.hWin.HEURIST4.detailtypes.lookups[type];
                            if(type=='resource' && oRecord.getData("rst_CreateChildIfRecPtr")==1){
                                    elLiner.innerHTML = elLiner.innerHTML + ' (child)';     
                            }
                        }

                        var ccode = oRecord.getData("conceptCode");
                        elLiner.title = 
                        "ID: "+oRecord.getData("rst_ID")+
                        (ccode?("\n\nConcept code: "+ccode):'')+
                        "\n\nBase field type: "+oRecord.getData("dty_Name")+"\n\n"+
                        "Help: "+oRecord.getData("rst_DisplayHelpText")+"\n\n"+
                        "For non owner: " + oRecord.getData("rst_NonOwnerVisibility");
                        
                    }
                },
                {
                    key:"rst_DisplayWidth", label: "Width", sortable:false, width:25, minWidth:25, maxWidth:25, className:"center" ,
                    
                    editor: new YAHOO.widget.TextboxCellEditor({
                            validator: YAHOO.widget.DataTable.validateNumber,
                            disableBtns:true,
                            asyncSubmitter:function(fnCallback, oNewValue){
                                var rec = this.getRecord();
                                _updateSingleField(rec.getData("rst_ID"), 'rst_DisplayWidth', 
                                            rec.getData("rst_DisplayWidth"), oNewValue, fnCallback); //fnCallback(bSuccess, oNewValue)   
                            } 
                    } ),
                    
                    formatter: function(elLiner, oRecord, oColumn, oData){
                        var wid = oRecord.getData('rst_DisplayWidth');
                        var typ = oRecord.getData('dty_Type');
                        if (_isNoWidth(typ))
                        {
                            res = "";
                            $(elLiner).css({'cursor':'normal'}); 
                        }
                        else{
                            res = wid;
                            $(elLiner).addClass('yui-dt-liner-editable');   
                        }


                        elLiner.innerHTML = res;
                    }
                },
                //{ key:"rst_DisplayHelpText", label: "Prompt", sortable:false },
                {
                    key:"rst_RequirementType", label: "Requirement", sortable:false,
                    minWidth:70, maxAutoWidth:70, maxWidth:70, width:70, className:'left',
                    
                    editor: new YAHOO.widget.DropdownCellEditor({ 
                            dropdownOptions: ['required', 'recommended', 'optional', {value:'forbidden',label:'hidden'} ],  
                            disableBtns:true,
                            asyncSubmitter:function(fnCallback, oNewValue){
                                var rec = this.getRecord();
                                _updateSingleField(rec.getData("rst_ID"), 'rst_RequirementType', 
                                            rec.getData("rst_RequirementType"), oNewValue, fnCallback); //fnCallback(bSuccess, oNewValue)   
                            } 
                    } ),
                            
                    formatter: function(elLiner, oRecord, oColumn, oData){
                        
                        var type = oRecord.getData("dty_Type");
                        if(type!=='separator'){
                            elLiner.innerHTML = oData;
                            $(elLiner).addClass('yui-dt-liner-editable2');
                            if(oRecord.getData("rst_RequirementType")=='forbidden') $(elLiner).text('hidden');
                        }else{
                            if(oRecord.getData("rst_RequirementType")=='forbidden') {
                                
                                $('<span>hidden</span>').appendTo($(elLiner)).click(
                                function(){
                                    var __rst_ID = oRecord.getData("rst_ID");
                                    _updateSingleField(__rst_ID, 'rst_RequirementType', 
                                            'forbidden', 'recommended', null); //fnCallback(bSuccess,
                                
                                    var oRecInfo = _getRecordById(__rst_ID);
                                    var row_index = oRecInfo.row_index;
                                    var dataupdate = oRecInfo.record.getData();
                                
                                    dataupdate['rst_RequirementType'] = 'recommended';
                                    _myDataTable.updateRow(row_index, dataupdate);
                                    /*
                                    var elLiner = _myDataTable.getTdLinerEl({record:oRecord, 
                                            column:_myDataTable.getColumn('rst_RequirementType')});
                                    elLiner.innerHTML = "";*/
                                });                               
                            }else{
                                $(elLiner).text('');   
                            }
                        }
                    }
                    /*function(elLiner, oRecord, oColumn, oData){
                        var type = oRecord.getData("dty_Type");
                        if(type!=='separator'){
                            elLiner.innerHTML = YAHOO.widget.DataTable.formatDropdown(elLiner, oRecord, oColumn, oData);                    
                        }            
                    },
                    dropdownOptions: _requirements */
                },
                {
                    key:"rst_MinValues", label: "Min", hidden:true
                },
                {
                    key:"rst_MaxValues", label: "Repeatability", sortable:false,
                    minWidth:70, maxAutoWidth:70, maxWidth:70, width:70, className:'left',
                    editor: new YAHOO.widget.DropdownCellEditor({ 
                            dropdownOptions: 
                            //['single', 'repeatable', 'limited'],
                    [{value:0, label:'repeatable'}, {value:1, label:'single'}, 
                        {value:2, label:'limit 2'},{value:3, label:'limit 3'},{value:4, label:'limit 4'},{value:5, label:'limit 5'} ], 
                            disableBtns:true,
                            asyncSubmitter:function(fnCallback, oNewValue){
                                var rec = this.getRecord();
                                _updateSingleField(rec.getData("rst_ID"), 'rst_MaxValues', 
                                            rec.getData("rst_MaxValues"), oNewValue, fnCallback); //fnCallback(bSuccess, oNewValue)   
                            } 
                    } ),
                    
                    formatter: function(elLiner, oRecord, oColumn, oData){
                        var type = oRecord.getData("dty_Type");
                        if(type!=='separator'){
                            var minval = oRecord.getData('rst_MinValues');
                            var maxval = oRecord.getData('rst_MaxValues');
                            var res = 'repeatable';
                            if(Number(maxval)===1){
                                res = 'single'; // value';
                            }else if(Number(maxval)>1){
                                res = 'limit '+maxval;
                            }
                            elLiner.innerHTML = res;
                            $(elLiner).addClass('yui-dt-liner-editable2');   
                        }
                    }

                },
                {
                    key:"rst_DefaultValue", label: "Default", sortable:false,className:"center",width:40,
                    formatter: function(elLiner, oRecord, oColumn, oData){
                        var reqtype = oRecord.getData('rst_DefaultValue');
                        if (reqtype && reqtype.length > 0){
                            var type = oRecord.getData("dty_Type");
                            if( type=='enum' ){
                                if(reqtype>0){
                                    var term = window.hWin.HEURIST4.terms.termsByDomainLookup['enum'][reqtype];
                                    if(term){
                                        reqtype = term[0];//.substring(0,15);
                                    }
                                }else{
                                    reqtype = '';
                                }
                            }

                            elLiner.innerHTML = '<label class="truncate" style="max-width:44">'+reqtype+'</label>';
                        }else{
                            elLiner.innerHTML = '';
                        }
                    }
                },
                {
                    key:"dty_Name", label: "Based on template", width:100, sortable:false, hidden:true,
                    formatter: _hidevalueforseparator
                },
                {
                    key:"rst_Status", label: "Status", sortable:false, className:"center", hidden:true,
                    formatter: _hidevalueforseparator
                },
                {
                    key: "rst_values",
                    label: "Del",
                    width : "16px",
                    sortable: false,
                    className:"center",
                    formatter: function(elLiner, oRecord, oColumn, oData){
                        var status = oRecord.getData('rst_Status');
                        var isRequired = (oRecord.getData('rst_RequirementType')==='required');
                        if ( (_isReserved && isRequired) || status === "reserved"){ // || status === "approved"
                            //before it was "lock" icon
                            statusLock  ='<a href="#delete"><span class="ui-icon ui-icon-trash" '
                            +'title="Click to remove field from this record type"></span><\/a>';
                            /*
                            statusLock  ='<a href="#delete"><img src="../../../common/images/cross.png" width="12" height="12" border="0" '+
                            'title="Click to remove field from this record type" /><\/a>';
                            */
                        }else{
                            statusLock  ='<a href="#delete"><span class="ui-icon ui-icon-trash" '
                            +'title="Click to remove field from this record type"></span><\/a>';
                            /*
                            statusLock = '<a href="#delete"><img src="../../../common/images/cross.png" width="12" height="12" border="0" '+
                            'title="Click to remove field from this record type" /><\/a>';
                            */
                        }
                        elLiner.innerHTML = statusLock;
                    }
                }
            ];

            //special formatter
            var myRowFormatter = function(elTr, oRecord) {
                var val0 = oRecord.getData('dty_Type');
                if(val0 === 'separator'){
                    Dom.addClass(elTr, 'separator');
                }
                //var val1 = oRecord.getData('rst_NonOwnerVisibility');  val1==='hidden' || 
                var val2 = oRecord.getData('rst_RequirementType');
                if (val2==='forbidden') {
                    Dom.addClass(elTr, 'gray');
                }else{
                    Dom.removeClass(elTr, 'gray');
                }
                return true;
            };

            //create datatable
            _myDataTable = new YAHOO.widget.RowExpansionDataTable(
                "tableContainer",
                myColumnDefs,
                myDataSource,
                //this is box of expandable record   - to show onEventToggleRowExpansion
                {	sortedBy:{key:'rst_DisplayOrder', dir:YAHOO.widget.DataTable.CLASS_ASC},
                    formatRow: myRowFormatter,
                    rowExpansionTemplate :
                    function ( obj ) {
                        var rst_ID = obj.data.getData('rst_ID');
                        //var rst_values = obj.data.getData('rst_values');
                        var ccode = obj.data.getData("conceptCode");
                        
                        var fieldType = window.hWin.HEURIST4.detailtypes.typedefs[rst_ID].commonFields[window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex.dty_Type];
                        var allowEditBaseFieldType = (fieldType=='enum' || fieldType=='resource' || fieldType=='relmarker' || fieldType=='relationtype');
                        var allowIncrement = (fieldType=='freetext') || (fieldType=='integer') || (fieldType=='float');
                        
                        var incrementTip = (fieldType=='freetext')
                                    ?'The default value supplied for new records will be the last value entered for the field with any integer at the end of that value incremented by 1, or the last value entered with 1 appended if the last character is not a numeral'
                                    :'The default value supplied for new records will be the largest numeric value for this field + 1';
                        
                        /*
                        THIS IS FORM to edit detail structure. It is located on expandable row of table
                        */
                        
                        if(_isStreamLinedAdditionAction){ //global
                            
                            var _rst_ID = rst_ID;
                            rst_ID = 0;
                        
                        obj.liner_element.innerHTML =   // padding-right:5; padding-left:30; 
                        '<div style="margin: 4px 60px; padding-bottom:5;border:black 1px solid">'+
                        
                        '<div class="input-row"><div class="input-header-cell">Field name:</div>'+
                            '<div class="input-cell">'+
                                '<input id="ed_rty_ID" type="hidden" value="'+rty_ID+'">'+
                                '<input id="ed_rst_ID" type="hidden" value="'+_rst_ID+'">'+
                                '<input id="ed_dty_Name" style="width:200px;" maxlength="255" onchange="_onDispNameChange(event)" '+
                                    'onkeypress="window.hWin.HEURIST4.ui.preventChars(event)" '+
                                    //'onblur="setTimeout(function(){$(\'.list_div\').hide()},200)" '+
                                    'onkeyup="onFieldAddSuggestion(event,'+_rst_ID+')" '+  //need id to detect index_toinsert 
                                    'title="The name of the field, displayed next to the field in data entry and used to identify the field in report formats, analyses and so forth"/>'+
                            '</div></div>'+

                        '<div class="input-row">'+
                            '<div class="input-header-cell" style="vertical-align:top">Help text:</div>'+
                            '<div class="input-cell">'+
                                '<textarea class="initially-dis" style="width:450px" maxlength="255" cols="450" rows="4" id="ed_dty_HelpText" '+
                                'onkeypress="removeErrorClass(this)"'+
                                'onfocus="setTimeout(function(){$(\'.list_div\').hide()},200)" '+
                                'title="Help text displayed underneath the data entry field when help is ON"></textarea>'+
                                '<div class="prompt">Use &lt;br&gt; for new line and &lt;a href="web page URL" target=_blank&gt;Help&lt;/a&gt; for a link to a help page.</div>'+
                                
                                //'<div class="prompt">Please edit the heading and this text to values appropriate to this record type. '+
                                //            'This text is optional.</div>'+
                            '</div></div>'+
                            
                        '<div class="input-row"><div class="input-header-cell" style="vertical-align:top;">Requirement:</div>'+
                            '<div class="input-cell" title="Determines whether the field must be filled in, should generally be filled in, or is optional">'+
                                '<select id="ed0_rst_RequirementType" onchange="onReqtypeChange(event)" class="initially-dis" style="display:inline; margin-right:0px;vertical-align: top;">'+
                                    '<option value="required">required</option>'+
                                    '<option value="recommended" selected>recommended</option>'+
                                    '<option value="optional">optional</option>'+
                                    '<option value="forbidden">hidden</option></select>'+ //was forbidden
                                    
                                '<input type="hidden" id="ed0_rst_Status" value="open">'+
                            '</div></div>'+
                            
                        // Repeatability
                        '<div class="input-row">'+
                            '<div class="input-header-cell">Repeatability :</div>'+
                            '<div class="input-cell" title="Determines whether multiple values can be entered for this field" >'+
                                '<select id="ed0_Repeatability" class="initially-dis" onchange="onRepeatChange(event)">'+
                                    '<option value="single">single</option>'+
                                    '<option value="repeatable" selected>repeatable</option>'+
                                    '<option value="limited">limited</option>'+ //IJ request HIDE IT 2012-10-12
                                '</select>'+

                        // Minimum values
                            '<span id="ed0_spanMinValue" style="display:none;visibility:hidden;"><label class="input-header-cell">Minimum&nbsp;values:</label>'+
                            '<input id="ed0_rst_MinValues" title="Minimum number of values which are required in data entry" style="width:20px" size="2" '+
                            'onblur="onRepeatValueChange(event)" onkeypress="window.hWin.HEURIST4.ui.preventNonNumeric(event)"/></span>'+
                        // Maximum values
                            '<span id="ed0_spanMaxValue" style="visibility:hidden;"><label class="input-header-cell">Maximum&nbsp;values:</label>'+

                                '<input id="ed0_rst_MaxValues" title="Maximum number of values which are permitted in data entry" '+
                                'style="width:20px; text-align:center;" size="2" '+
                                    'onblur="onRepeatValueChange(event)" onkeypress="window.hWin.HEURIST4.ui.preventNonNumeric(event)"/></span>'+
                            '</div></div>'+                            
                       
                        '<div class="input-row"><div class="input-header-cell" style="vertical-align:top;">Data type :</div>'+
                            '<div class="input-cell">'+
                                '<select id="ed_dty_Type" class="initially-dis" onchange="onDetTypeChange(event)" style="display:inline; margin-right:0px;vertical-align: top;">'+
                                    '<option value="enum" selected>Dropdown (Terms)</option>'+
                                    '<option value="float">Numeric (integer or decimal)</option>'+
                                    '<option value="freetext">Text (single line)</option>'+
                                    '<option value="blocktext">Text (memo)</option>'+
                                    '<option value="date">Date / time</option>'+
                                    '<option value="geo">Geospatial (point, line, polygon ...)</option>'+
                                    '<option value="file">File or remote URL</option>'+
                                    '<option value="resource">Record pointer</option>'+
                                    '<option value="relmarker">Relationship marker</option></select>'+
                            '</div></div>'+
                                                        
                        // Terms - enums and relmarkers
                        '<div class="input-row" Xstyle="display:none"><div class="input-header-cell">Vocabulary (terms):</div>'+
                            '<div class="input-cell" title="Select vocabulary">'+
                                '<select id="selVocab" Xclass="initially-dis" style="width:300px"/>'+
                                '<input id="ed_dty_JsonTermIDTree" type="hidden"/>'+
                                '<input id="ed_dty_TermIDTreeNonSelectableIDs" type="hidden"/>'+
                                '<span>'+
                                    '<a href="#" onClick="onAddVocabulary()">add a vocabulary</a>'+
                                '</span>'+
                            '</div></div>'+
                            
                        //PREVIEW Terms - enums and relmarkers
                        '<div class="input-row"><div class="input-header-cell">Preview:</div>'+
                        '<div class="input-cell" title="Preview of terms available for selection as values for this field">'+
                        '<span class="input-cell" id="termsPreview" class="dtyValue"></span>'+
                        //'<span class="input-cell" style="margin:0 10px">&nbsp;&nbsp;to change click "Edit Base Field Definition"</span>'+
                        '</div></div>'+
                            

                        // Pointer target types - pointers and relmarkers   ed_dty_PtrTargetRectypeIDs
                        '<div class="input-row" style="display:none"><div class="input-header-cell">Target entity type:</div>'+
                            '<div class="input-cell" title="Determines which record types this pointer field can point to. It is preferable to select target record types than to leave the pointer unconstrained">'+
                                '<span class="dtyValue" id="pointerPreview" style="cursor:pointer;width:270px;border: 1px solid #DCDCDC;line-height: 19px;padding-left:2px" onClick="onSelectRectype()"></span>'+
                                //'&nbsp;<input type="submit" value="Select Record Types" id="btnSelRecType2" onClick="onSelectRectype()"/>'+
                                '<input id="ed_dty_PtrTargetRectypeIDs" type="hidden"/>'+
                            '</div></div>'+
                            
                        '<div style="border-top:1px solid black;padding:10px;">'+
                            '<span style="font-style:italic;display:inline-block;">All fields in this form are required. For additional options, cancel,<br>'+
                            'uncheck streamlined field addition checkbox, and click on add field again</span>'+
                            '<span style="float:right;padding:4px;">'+
                                '<input style="margin-right:10px;" type="button" value="Create Field" '+
                                    'title="Create new base field type and adds to this record type"'+
                                    ' onclick="onCreateFieldTypeAndAdd('+_rst_ID+');">'+                            
                                '<input style="margin-right:10px;" type="button" value="Cancel" '+
                                    'title="Create new base field type and adds to this record type"'+
                                    ' onclick="editStructure.doExpliciteCollapse('+_rst_ID+', false);">'+                            
                            '</span>'+
                        '</div>'+
                        
                        '</div>';
                        
                        }else{

                        var exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);
                        
                        obj.liner_element.innerHTML =
                        '<div class="edit-form" style="display:none;margin: 4px 20px; padding-bottom:5;border:black 1px solid">'+

                        // Name / prompt
                        '<div class="input-row"><div class="input-header-cell">Prompt (display name):</div>'+
                            '<div class="input-cell">'+
                                '<input id="ed_rty_ID" type="hidden" value="'+rty_ID+'">'+
                                '<input id="ed_rst_ID" type="hidden" value="'+rst_ID+'">'+
                                '<input id="ed'+rst_ID+'_rst_DisplayName" style="width:200px;" onchange="_onDispNameChange(event)" '+
                                    'onkeypress="window.hWin.HEURIST4.ui.preventChars(event)" maxlength="255" '+
                                    'title="The name of the field, displayed next to the field in data entry and used to identify the field in report formats, analyses and so forth"/>'+
                                '<span>'+    
                                // Field width
                                '<span><label style="min-width:65px;width:65px">Field width:</label>'+
                                '<input id="ed'+rst_ID+'_rst_DisplayWidth" '+
                                    'title="Display width of this field in the data entry form (does not limit maximum data length)" style="width:40" size="4" onkeypress="window.hWin.HEURIST4.ui.preventNonNumeric(event)"/>'+
                                '</span>'+
                                '<span id="span'+rst_ID+'_rst_DisplayHeight"><label style="min-width:35px;width:35px">&nbsp;Height:</label>'+
                                '<input id="ed'+rst_ID+'_rst_DisplayHeight" '+
                                    'title="Display height in rows of this field in the data entry form" style="width:40" size="4" onkeypress="window.hWin.HEURIST4.ui.preventNonNumeric(event)"/>'+
                                '</span>'+
                                '</span>'+
                            '</div></div>'+

                        // Help text
                        '<div class="input-row">'+
                        '<div class="input-header-cell" style="vertical-align:top">Help text:</div>'+
                        '<div class="input-cell">'+
                        '<textarea style="width:650px;max-width:650px" cols="450" rows="4" maxlength="1000" id="ed'+rst_ID+'_rst_DisplayHelpText" '+
                        'title="Help text displayed underneath the data entry field when help is ON"></textarea>'+
                        '<div class="prompt">Use &lt;br&gt; for new line and &lt;a href="web page URL" target=_blank&gt;Help&lt;/a&gt; for a link to a help page.</div>'+
                        
                        //'<div class="prompt">Please edit the heading and this text to values appropriate to this record type. '+
                        //                    'This text is optional.</div>'+
                        '</div></div>'+

                        '<div class="input-row">'+
                        '<div class="input-header-cell">Hidden:</div>'+
                        '<div class="input-cell">'+
                        '<input id="ed'+rst_ID+'_rst_RequirementType_separator" type="checkbox" value="forbidden" />'+
                        '</div></div>'+
                        
                        // Required/recommended optional
                        '<div class="input-row"><div class="input-header-cell" style="vertical-align:top;">Requirement:</div>'+
                        '<div class="input-cell" title="Determines whether the field must be filled in, should generally be filled in, or is optional">'+
                        '<select id="ed'+rst_ID+'_rst_RequirementType" onchange="onReqtypeChange(event)" style="display:inline-block; margin-right:0px;vertical-align: top;">'+
                        '<option value="required">required</option>'+
                        '<option value="recommended">recommended</option>'+
                        '<option value="optional">optional</option>'+
                        '<option value="forbidden">hidden</option></select>'+ //was forbidden

                        
                        // Default value
                        /*
                        '<div class="input-row"><div class="input-header-cell">Default Value:</div><div class="input-cell">'+
                        '<span class="input-cell" id="termsDefault" name="def'+rst_ID+'_rst_DefaultValue" class="dtyValue"></span>'+
                        '<input id="ed'+rst_ID+'_rst_DefaultValue" title="Select or enter the default value to be inserted automatically into new records"/>'+
                        '</div></div>'+
                        */
                        ((allowIncrement)
                        ?('<span style="display:inline-block;padding-left:40px">'+
                        '<label style="min-width:95px;width:95px;text-align:left;">'+  // for="incValue_'+rst_ID+'_1"
                        '<input type="radio" id="incValue_'+rst_ID+'_1" name="incValue_'+rst_ID+'" value="0" checked onchange="onIncrementModeChange('+rst_ID+')">'+
                        'Default&nbsp;Value:</label>'+
                        '<div id="termsDefault_'+rst_ID+'" style="display:inline-block;padding-right:1em"><input id="ed'+rst_ID+'_rst_DefaultValue" title="Select or enter the default value to be inserted automatically into new records"/></div>'+
                        '<br>'+
                        '<label style="min-width:120px;text-align:left;" title="'+incrementTip+'">'+  //for="incValue_'+rst_ID+'_2" 
                        '<input type="radio" id="incValue_'+rst_ID+'_2" name="incValue_'+rst_ID+'" value="1"  title="'+incrementTip+'" onchange="onIncrementModeChange('+rst_ID+')">'+
                        'Increment value by 1</label>'+
                        '</span>')
                        :('<div style="padding-left:50px;display:inline-block">'+
                        '<label class="input-header-cell" for="ed'+rst_ID+'_rst_DefaultValue">Default&nbsp;Value:</label>'+
                        '<div id="termsDefault_'+rst_ID+'" style="display:inline-block;"><input id="ed'+rst_ID+'_rst_DefaultValue" title="Select or enter the default value to be inserted automatically into new records"/>'+
                        ((fieldType=='date')?'<br><span class="prompt">yesterday, today, tomorrow, now, specific date</span>':'')+
                        '</div>'+
                        '</div>'))+

                        // Minimum values
                        '<span id="ed'+rst_ID+'_spanMinValue" style="display:none;"><label class="input-header-cell">Minimum&nbsp;values:</label>'+
                        '<input id="ed'+rst_ID+
                        '_rst_MinValues" title="Minimum number of values which are required in data entry" style="width:20px" size="2" '+
                        'onblur="onRepeatValueChange(event)" onkeypress="window.hWin.HEURIST4.ui.preventNonNumeric(event)"/></span>'+
                        '</div></div>'+
                        
                        // Repeatability
                        '<div class="input-row" id="divRepeatability'+rst_ID+'">'+
                        '<div class="input-header-cell">Repeatability :</div>'+
                        '<div class="input-cell" title="Determines whether multiple values can be entered for this field" >'+
                        '<select id="ed'+rst_ID+'_Repeatability" onchange="onRepeatChange(event)">'+
                        '<option value="single">single</option>'+
                        '<option value="repeatable">repeatable</option>'+
                        '<option value="limited">limited</option>'+ //IJ request HIDE IT 2012-10-12
                        '</select>'+

                        // Maximum values
                        '<span id="ed'+rst_ID+'_spanMaxValue"><label class="input-header-cell">Maximum&nbsp;values:</label>'+
                        '<input id="ed'+rst_ID+
                        '_rst_MaxValues" title="Maximum number of values which are permitted in data entry" style="width:20px; text-align:center;" size="2" '+
                        'onblur="onRepeatValueChange(event)" onkeypress="window.hWin.HEURIST4.ui.preventNonNumeric(event)"/></span>'+
                        '</div>'+
                        '</div>'+

                        //PREVIEW Terms - enums and relmarkers
                        '<div class="input-row"><div class="input-header-cell">Terms list preview:</div>'+
                        '<div class="input-cell" title="The list of terms available for selection as values for this field">'+
                        '<input id="ed'+rst_ID+'_rst_FilteredJsonTermIDTree" type="hidden"/>'+
                        '<input id="ed'+rst_ID+'_rst_TermIDTreeNonSelectableIDs" type="hidden"/>'+
                        '<span class="input-cell" id="termsPreview" class="dtyValue"></span>'+
                        //'<span class="input-cell" style="margin:0 10px">&nbsp;&nbsp;to change click "Edit Base Field Definition"</span>'+
                        '</div></div>'+

                        // Pointer target types - pointers and relmarkers
                        '<div class="input-row"><div class="input-header-cell">Can point to:</div>'+
                        '<div id="pointerPreview" class="input-cell" title="Determines which record types this pointer field can point to. It is preferable to select target record types than to leave the pointer unconstrained">'+
                        '<input id="ed'+rst_ID+'_rst_PtrFilteredIDs" type="hidden"/>'+
                        // TODO: the following message is not showing, whereas the one above does
                        //'<span class="input-cell" style="margin:0 10px">&nbsp;&nbsp;to change click "Edit Base Field Definition"</span>'+ // and then "Select Record Types"
                        '</div></div>'+

                        '<div class="input-row" id="pointerDefValue'+rst_ID+'"></div>'+

                        '<div class="input-row"><div class="input-header-cell">Pointer mode:</div>'+
                                '<div class="input-cell">'+
                        '<select id="ed'+rst_ID+'_rst_PointerMode" onchange="onPointerModeChange(event,'+rst_ID+')">'+
                        '<option value="addorbrowse">add or browse</option>'+
                        '<option value="addonly">add only</option>'+
                        '<option value="browseonly">browse only</option>'+ 
                        '</select>'+
                            '<span><label class="input-header-cell" style="padding-top:0px">Browse&nbsp;Filter:</label>'+
                                '<input id="ed'+rst_ID+'_rst_PointerBrowseFilter" style="width:300px"/></span>'+
                        '</div></div>'+

                        
                        '<div class="input-row"><div class="input-header-cell">Create new records as children:</div>'+
                        
                        '<div class="input-cell">'+
                        '<input id="ed'+rst_ID+'_rst_CreateChildIfRecPtr" type="checkbox" value="1"  onclick="onCreateChildIfRecPtr(event,'+rst_ID+','+rty_ID+')"/>'+
                        
                        '<a href="#" class="help_rst_CreateChildIfRecPtr" onclick="onCreateChildIfRecPtrInfo(event)" style="vertical-align:bottom">'+
                            '<img src="../../../common/images/info.png" width="14" height="14" border="0" title="Info"></a>'+
                        '<span class="help prompt">New records created via this field become child records. Click "i" for further information.</span>'+    
                            
                        '</div></div>'+
                        
                        
                        /* OLD PLACE 
                        // Base field definitions  (button)
                        '<div>'+
                        '<div style="width:90%;text-align:right;padding:5px 20px 15px 0">'+ //margin-left:190px; 
                        
                        (allowEditBaseFieldType?
                        '<input style="margin-right:100px;" id="btnEdit_'+rst_ID+'" type="button" value="Edit Base Field Definition" '+
                        'title="Allows modification of the underlying field definition (shared by all record types that use this base field)"'+
                        ' onclick="_onAddEditFieldType('+rst_ID+','+rty_ID+');">':'<span style="margin-right:220px;">&nbsp;</span>')+

                        //Save and cancel (buttons)
                        '<input style="margin-right:20px;" id="btnSave_'+rst_ID+'" type="button" value="Save"'+
                        'title="Save any changes to the field settings. You may also simply click on another field to save this one and open the other" onclick="doExpliciteCollapse(event);"  style="margin:0 2px;"/>'+
                        '<input id="btnCancel_'+rst_ID+'" type="button" value="Cancel" '+
                        'title="Cancel any changes made to the field settings for this field (will not cancel previously saved settings)" onclick="doExpliciteCollapse(event);" style="margin:0 2px;"/>'+
                        '</div>'+
                        */
                        
                        '<div id="divMoreDefs'+rst_ID+'" class="togglepnl" '+
                        'style="background-color:white;'+((exp_level!=0)?'display:none;':'')+'">'+
                        '<a style="margin-left: 40px; line-height:25px" onMouseDown="'+
                        "$('#options').slideToggle('fast'); $('#divMoreDefs"+rst_ID+"').toggleClass('show'); $('#options').toggleClass('hidden');"+
                        '">more ...</a></div>'+

                        '<div id="options" class="hidden" style="background-color:white">'+

                        
                        '<div class="input-row">'+
                            '<div class="input-header-cell" style="vertical-align:top">Extended Description:</div>'+
                            '<div class="input-cell">'+
                                '<textarea style="width:450px" maxlength="5000" cols="450" rows="4" id="ed'+rst_ID+'_rst_DisplayExtendedDescription" '+
                                'onkeypress="removeErrorClass(this)"'+
                                'title="An extended description of the content of this field type and references to any standards used"></textarea>'+
                                '<div class="prompt">An extended description of the content of this field type and references to any standards used</div>'+
                            '</div></div>'+
                        
                        // Status
                        '<div class="input-row">'+
                            '<div class="input-header-cell">Status:</div>'+
                            '<div class="input-cell" title="Determines the degree of authority assigned to this field - reserved is used for internal Heurist definitions, open is the lowest level">'+
                                '<select id="ed'+rst_ID+
                                '_rst_Status" style="display:inline-block" onchange="onStatusChange(event)">'+
                                        '<option value="open">open</option>'+
                                        '<option value="pending">pending</option>'+
                                        '<option value="approved">approved</option>'+
                                '</select>'+  //<option value="reserved">reserved</option>

                                // Non-owner visibility
                                '<span><label class="input-header-cell" '+
                                    'title="Determines whether the field can be viewed by users other than the record owner/owner group">Non-owner visibility:</label>'+
                                    '<select id="ed'+rst_ID+
                                            '_rst_NonOwnerVisibility">'+  // style="display:inline-block"
                                            '<option value="hidden">hidden</option>'+
                                            '<option value="viewable">viewable</option>'+
                                            '<option value="public">public</option>'+
                                            '<option value="pending">pending</option></select>'+
                                '</span>'+
                        
                        '</div></div>'+

                        (ccode?('<div class="input-row">'+
                            '<div class="input-header-cell">Concept code:</div>'+
                            '<div style="display:table-cell">'+ccode+'</div></div>'):'')+
                        
                        '</div>'+
                        
                        '<div style="border-top:1px solid black;padding:10px;">'+
                            '<span style="font-style:italic;display:inline-block;">'+
                            (allowEditBaseFieldType?
                                'To change terms list or target entity types: '+
                                '<a href="#" onclick="_onAddEditFieldType('+rst_ID+','+rty_ID+');">Edit base field definitions</a>'
                            :'&nbsp;')+
                            
                            '</span><span style="float:right;">'+

                                (false && allowEditBaseFieldType?
                                '<input style="margin-right:20px;" id="btnEdit_'+rst_ID+'" type="button" value="Edit Base Field Definition" '+
                                'title="Allows modification of the underlying field definition (shared by all record types that use this base field)"'+
                                ' onclick="_onAddEditFieldType('+rst_ID+','+rty_ID+');">':'')+  

                                //Save and cancel (buttons)
                                '<input style="margin-right:10px;padding-left:20px;padding-right:20px;" id="btnSave_'+rst_ID+'" type="button" value="Save"'+
                                'title="Save any changes to the field settings. You may also simply click on another field to save this one and open the other" onclick="if(checkDisplayName(event)){doExpliciteCollapse(event);}"  style="margin:0 2px;"/>'+
                                '<input id="btnCancel_'+rst_ID+'" type="button" value="Cancel" '+
                                'title="Cancel any changes made to the field settings for this field (will not cancel previously saved settings)" onclick="doExpliciteCollapse(event);" style="margin:0 2px;"/>'+
                                    
                            '</span>'+
                        '</div>'+
                        
                        
                        
                        
                        '</div>';
                        
                        }
                        
                        

                    }
                }
            );

            // highlight listeners
            //_myDataTable.subscribe("rowMouseoverEvent", _myDataTable.onEventHighlightRow);
            //_myDataTable.subscribe("rowMouseoutEvent", _myDataTable.onEventUnhighlightRow);
            
            //ART16 _myDataTable.subscribe("rowClickEvent", _myDataTable.onEventSelectRow);
            
            //
            // Subscribe to a click event to bind to expand/collapse the row
            //
            _actionInProgress = false;

            _myDataTable.subscribe( 'cellMousedownEvent', function(oArgs){
                
                if(_actionInProgress || _isServerOperationInProgress || _isDragEnabled){
                    return;
                }
                if($(oArgs.event.target).is('img')){
                
                var column = _myDataTable.getColumn(oArgs.event.target);

                if(!Hul.isnull(column) && column.key === 'rst_NonOwnerVisibility')
                { 
                    window.hWin.HEURIST4.msg.showMsgFlash('Please save changes to the field you are currently editing to allow dragging'
                            ,700,null,oArgs.target);
                    
                }
                }
            });
            
            
            _myDataTable.subscribe( 'cellClickEvent', onCellClickEventHandler );
            

            //
            // Subscribe to a click event on delete image
            //
            _myDataTable.subscribe('linkClickEvent', function(oArgs){

                if(!Hul.isnull(popupSelect) || _isServerOperationInProgress) { 
                    return; 
                }

                YAHOO.util.Event.stopEvent(oArgs.event);

                // It has not been necessary to click save since ~2011, so this code should never be called
                // TODO: verify and remove
                if(!Hul.isnull(_updatedFields) && _updatedFields.indexOf(9)>=0){ //order was changed
                    alert("Please click Save Order button to save changes in view-order before exiting");
                    return;
                }

                var elLink = oArgs.target;
                var oRecord = this.getRecord(elLink);
                var rst_ID = oRecord.getData("rst_ID");

                // result listener for delete operation
                function __updateAfterDelete(response) {

                    if(response.status == window.hWin.ResponseStatus.OK){

                        _myDataTable.deleteRow(oRecord.getId(), -1);

                        _isNewOptionalFieldAppeared( response.data.rectypes );
                        
                        window.hWin.HEURIST4.rectypes = response.data.rectypes;
                        window.hWin.HEURIST4.detailtypes = response.data.detailtypes;

                        if(_myDataTable.getRecordSet().getLength()<1){
                            $("#recStructure_toolbar").show();
                        }
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                    _isServerOperationInProgress = false;
                }

                /*if(elLink.hash === "#edit"){
                    _onAddEditFieldType(rst_ID, 0); //NOT USED
                }else */
                if(elLink.hash === "#delete"){
                    
                    if(oRecord.getData("rst_Status") === 'reserved')
                    {
                        var isRequired = (oRecord.getData('rst_RequirementType')==='required');
                        
                        alert("This is a reserved field, which may be required for correct operation of a specific function, "
                           + "such as Zotero import or mapping.\n"
                           + "It cannot therefore be deleted. If you do not need to use the field we suggest "
                           + (isRequired?'making it "optional" and ':'') + "dragging it to "
                           + "the end of the form under a heading 'Unused' or 'Ignore'.\n"
                           + "Empty fields do not affect performance or take up any space in the database.");
                           
                           return;
                    }
                    

                    function _continueDel(){         

                        var baseurl = window.hWin.HAPI4.baseURL + "admin/structure/saveStructure.php";

                        function __onCheckEntries(response)
                        {
                            if(response.status == window.hWin.ResponseStatus.OK){
                                
                                var dty_name = oRecord.getData('dty_Name');

                                var sWarn;
                                if(response.data[rty_ID]){
                                    sWarn = "This field #"+rst_ID+" '"+dty_name+"' is utilised in the title mask. If you delete it you will need to edit the title mask in the record type definition form (click on the pencil icon for the record type after exiting this popup).\n\n Do you still wish to delete this field?";
                                    var r=confirm(sWarn);
                                }else{
                                    // unnecessary: sWarn =  "Delete field # "+rst_ID+" '"+dty_name+"' from this record structure?";
                                    var r=1; // force deletion
                                }

                                if (r) {

                                    _doExpliciteCollapse(null ,false); //force collapse this row

                                    var callback = __updateAfterDelete;
                                    var request = {method:'deleteRTS', db:window.hWin.HAPI4.database, rtyID:rty_ID, dtyID:rst_ID};
                                    _isServerOperationInProgress = true;
                                    window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, callback); 
                                }
                                
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }                                        
                        }

                        
                        var callback = __onCheckEntries;
                        var request = {method:'checkDTusage', db:window.hWin.HAPI4.database, rtyID:rty_ID, dtyID:rst_ID};

                        window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, callback); 

                    }                    
                    
                    var exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2); //beginner default

                    if(exp_level>0){
                        var that_dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                        'Are you sure you want to remove this field from this record type?',
                                    function(){ _continueDel(); })
                    }else{
                        _continueDel();
                    }
                    
                    
                }
            });


            _myDataTable.subscribe("initEvent", function() {
                _myDataTable.sortColumn(_myDataTable.getColumn('rst_DisplayOrder'),YAHOO.widget.DataTable.CLASS_ASC); //fix bug in Chrome
                _setDragEnabled(true);
            });

            //////////////////////////////////////////////////////////////////////////////
            // Create DDRows instances when DataTable is initialized
            //////////////////////////////////////////////////////////////////////////////
            // WE DO IT MANUALLY
            /*_myDataTable.subscribe("initEvent", function() {
            var i, id,
            allRows = this.getTbodyEl().rows;

            for(i=0; i<allRows.length; i++) {
            id = allRows[i].id;
            // Clean up any existing Drag instances
            if (myDTDrags[id]) {
            myDTDrags[id].unreg();
            delete myDTDrags[id];
            }
            // Create a Drag instance for each row
            myDTDrags[id] = new YAHOO.example.DDRows(id);
            }
            });*/

            //////////////////////////////////////////////////////////////////////////////
            // Create DDRows instances when new row is added
            //////////////////////////////////////////////////////////////////////////////
            _myDataTable.subscribe("rowAddEvent",function(e){
                if(_isDragEnabled){
                    var id = e.record.getId();
                    myDTDrags[id] = new YAHOO.example.DDRows(id);
                }
            });
            /*
            _myDataTable.subscribe("rowUpdateEvent",function(e){
            var id = e.record.getId();
            if (myDTDrags[id]) {
            myDTDrags[id].unreg();
            delete myDTDrags[id];
            }
            myDTDrags[id] = new YAHOO.example.DDRows(id);
            })*/

        }
    } // end _initTabDesign -------------------------------

    
    function onCellClickEventHandler(oArgs){
                
        if(_actionInProgress){
            return;
        }


        var column = _myDataTable.getColumn(oArgs.target);
        
        _isStreamLinedAddition = $('#cbStreamLinedAddition').is(':checked');

        //prevent any operation in case of opened popup
        if(!Hul.isnull(popupSelect) || _isServerOperationInProgress ||
            (!Hul.isnull(column) && 
                (column.key === 'rst_values' || column.key === 'rst_NonOwnerVisibility' || 
                    (column.key === 'addColumn' && !_isStreamLinedAddition) ) ))
            { 
                return; 
            }



        var record_id;
        var oRecord;
        if(Dom.hasClass( oArgs.target, 'yui-dt-expandablerow-trigger' )){
            record_id = oArgs.target;
            oRecord = _myDataTable.getRecord(record_id);
        }else{
            oRecord = _myDataTable.getRecord(oArgs.target);
            record_id = _myDataTable.getTdEl({record:oRecord, column:_myDataTable.getColumn("expandColumn")});
        }

        //init inline edit
        var typ = oRecord.getData("dty_Type");
        if(!Hul.isnull(record_id) && 
            (_expandedRecord==null) &&  //do not allow edit of some field is expanded
                (column.key === 'rst_RequirementType' || column.key === 'rst_MaxValues' || column.key === 'rst_DisplayName'
                || (column.key === 'rst_DisplayWidth' && !_isNoWidth(typ))
                )){
            if(typ!=='separator' || column.key === 'rst_DisplayName'){
                _myDataTable.onEventShowCellEditor(oArgs);
                
                if(column.key === 'rst_RequirementType' || column.key === 'rst_MaxValues'){ //force open dropdown
                
                    var editor = _myDataTable.getCellEditor();
                    
                    function __down(ele){
                        var pos = $(ele).offset(); // remember position
                        var len = $(ele).find("option").length;
                            if(len > 20) {
                                len = 20;
                            }

                        $(ele).css("position", "absolute");
                        $(ele).css("zIndex", 9999);
                        $(ele).offset(pos+18);   // reset position
                        $(ele).attr("size", len); // open dropdown
                        $(ele).unbind("focus", down);
                        $(ele).focus();
                                
                    }
                    __down(editor.dropdown);
                }
                
                return;
            }
        }
        
        
                    // after expansion - fill input values from HEURIST db
                    // after collapse - save data on server
                    function __toggle(){
                        
                        if(!isExpanded){ //now it is expanded
                            
                            _expandedRecord = _isStreamLinedAdditionAction?null:rst_ID;

                            _myDataTable.onEventToggleRowExpansion(record_id);
                            
                            if(_highightedRow!=null) _myDataTable.unhighlightRow(_highightedRow);
//                            
//console.log(tr);                            
//{'border-top':'2px solid blue', 'border-bottom': '2px solid blue'}
                            
                            window.hWin.HEURIST4.util.setDisabled($('.initially-dis'), true);
                            window.hWin.HEURIST4.util.setDisabled($('#selVocab'), true);

                            if(!_isStreamLinedAdditionAction){
                                
                                var tr = _myDataTable.getTrEl(oRecord);
                                _myDataTable.highlightRow( tr );
                                _highightedRow = tr;
                                
                                $('.edit-form').show('fade', null, 1000);
                                _fromArrayToUI(rst_ID, false); //after expand restore values from HEURIST
                            }else{
                                onDetTypeChange(); //to fill term selector
                                $('#ed_dty_Name').focus();
                            }

                            var rowrec = _myDataTable.getTrEl(oRecord);
                            var maindiv = Dom.get("page-inner");
                            var pos = rowrec.offsetTop;
                            maindiv.scrollTop = pos - 30;

                            var elLiner = _myDataTable.getTdLinerEl({record:oRecord, column:_myDataTable.getColumn('rst_NonOwnerVisibility')});
                            elLiner.innerHTML = "";

                        }else {
                            _saveUpdates(false); //save on server
                            _expandedRecord = null;
                        }

                        /*var elLiner = _myDataTable.getTdLinerEl({record:oRecord, column:_myDataTable.getColumn('rst_NonOwnerVisibility')});
                        if(_expandedRecord != null){
                        elLiner.innerHTML = "";
                        }else{
                        elLiner.innerHTML = "<img src='../../common/images/up-down-arrow.png'>"
                        }*/

                        _actionInProgress = false;
                    }
                    
                    if(!Hul.isnull(record_id) && 
                            (column.key === 'expandColumn' ||
                             column.key === 'addColumn' && _isStreamLinedAddition ))
                    {
                       
                        _actionInProgress = true;
                        
                        oRecord = _myDataTable.getRecord(record_id);
                        var rst_ID = oRecord.getData("rst_ID");

                        var state = _myDataTable._getRecordState( record_id );
                        var isExpanded = ( state && state.expanded );
                        
                        if(isExpanded){
                            _doExpliciteCollapse(rst_ID, true); //save this record on collapse
                            _setDragEnabled(true);
                        }else{
                            //collapse and save by default
                            if(!Hul.isnull(_expandedRecord)){
                                _doExpliciteCollapse(_expandedRecord, true);
                            }
                            _setDragEnabled(false);
                            
                        }
                        _isStreamLinedAdditionAction = (column.key === 'addColumn' && _isStreamLinedAddition);                            

                        // after expand/collapse need delay before filling values
                        setTimeout(__toggle, 100);

                    }

    }

    
    
    /**
    * Opens popup with preview
    */
    function _initPreview(){
    }

    /**
    * Collapses the expanded row and save record structure type to server
    * @see _saveUpdates
    *
    * @param rst_ID  record structure type ID, if it is null it means that row is already collapsed and we take
    *				rstID from the last expanded row - from _expandedRecord. In this case it performs the saving only
    * @param needSave  whether to save data on server, it is false for collapse on delete only
    */
    function _doExpliciteCollapse(rst_ID, needSave, needClose){

        if(Hul.isnull(rst_ID)){ //when user open select and new field type popup we have to save all changes
            rst_ID = _expandedRecord;
            if(Hul.isnull(rst_ID)) {
                if(!Hul.isnull(_updatedFields) && _updatedFields.indexOf(9)>=0 && needSave){ //order was changed
                    _saveUpdates(false); //global function
                }
                return;
            }
        }
        
        needClose = (needClose===true);

        var oRecord = _getRecordById(rst_ID).record;
        var record_id = _myDataTable.getTdEl({record:oRecord, column:_myDataTable.getColumn("expandColumn")});
        if(!Hul.isnull(record_id)){

            var elLiner = _myDataTable.getTdLinerEl({record:oRecord, column:_myDataTable.getColumn('rst_NonOwnerVisibility')});
            elLiner.innerHTML = "<img src='../../../common/images/up-down-arrow.png'>";

            if(needSave && !_isStreamLinedAdditionAction){
                _fromUItoArray(rst_ID); //before collapse save from UI to HEURIST
            }
            _setDragEnabled(true);
            _myDataTable.onEventToggleRowExpansion(record_id); //collapse row

            _expandedRecord = null;

            if(needSave){
                _saveUpdates(needClose); //global function
            }
        }
        
        _isStreamLinedAdditionAction = false;
    }
    
    //_fromUItoArray(rst_ID);
    //_saveUpdates(false);
    
    
    /**
    * Find the row in database by recstructure type ID and returns the object with references.
    * This object has 2 properties: reference to record (row in datatable) and its index in datatable
    *
    * @param rst_ID  record structure type ID
    * @return object with record (from datatable) and row_index properties, null if nothing found
    */
    function _getRecordById(rst_ID){
        var recs = _myDataTable.getRecordSet();
        var len = recs.getLength();
        var row_index;
        for (row_index = 0; row_index < len; row_index++ )
        {
            var rec = _myDataTable.getRecord(row_index);
            if(rec.getData('rst_ID') == rst_ID){
                return {record:rec, row_index:row_index};
            }
        }

        return null;
    }

    
    /**
    * Takes values from edit form to _updateXXX arrays and back to HEURIST db strucure
    *
    * Fills _updatedFields and _updatedDetails with changed value from edit form on expanded row
    * and update local HEURIST db strucure
    *
    * @param _rst_ID record structure type ID, if null it takes values from all types in recstructure
    * (if order was changes it affects all types)
    */
    function _fromUItoArray(_rst_ID){

        var old_typedefs = window.hWin.HEURIST4.util.cloneJSON(window.hWin.HEURIST4.rectypes.typedefs[rty_ID].dtFields);
        
        var arrStrucuture = window.hWin.HEURIST4.rectypes.typedefs[rty_ID].dtFields;
        var fieldnames = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNames;

        // gather data for given recstructure type
        function __setFor(__rst_ID){
            //var dbg = Dom.get("dbg");
            var isChanged = false;

            //find the record with given rst_ID
            var oRecInfo = _getRecordById(__rst_ID);
            if(Hul.isnull(oRecInfo)) {
                return;
            }
            var row_index = oRecInfo.row_index;
            var dataupdate = oRecInfo.record.getData();

            var values = arrStrucuture[__rst_ID];
            var dti = fieldnames.indexOf('dty_Type');

            var k;
            for(k=0; k<fieldnames.length; k++){
                var ed_name = 'ed'+__rst_ID+'_'+fieldnames[k];
                var edt = Dom.get(ed_name);
                if(!Hul.isnull(edt)){
                    //DEBUG if(values[k] != edt.value){
                    //	dbg.value = dbg.value + (fieldnames[k]+'='+edt.value);
                    //}
                    
                    if(fieldnames[k]=="rst_DefaultValue"){
                         if ($('#incValue_'+__rst_ID+'_2').is(':checked')){
                            edt.value = 'increment_new_values_by_1';
                            
                         }else if(values[dti]=='resource') {
                            var ele = $('#pointerDefValue'+__rst_ID).find('div.def_values')
                            if(ele.editing_input('instance')){
                                edt.value = ele.editing_input('getValues');    
                            }else{
                                edt.value = '';
                            }
                         }
                    }else if(fieldnames[k]=="rst_CreateChildIfRecPtr"){
                            edt.value = $(edt).is(':checked')?1:0;
                            
                    }else if(fieldnames[k]=="rst_DisplayName" && edt.value == ''){
//console.log(__rst_ID+'  empty name');                        
                    }
                    
                    //values && k<values.length && 
                    if(values[k] !== edt.value){

                        if(fieldnames[k]=="rst_DisplayName"){
                            if(edt.value=='' || window.hWin.HEURIST4.ui.validateName(edt.value, "Prompt (display name)", 255)!=""){
                                continue;
                            }
                        }

                        values[k] = edt.value;
                        
                        

                        isChanged = true;
                        //track the changes for further save
                        if(!Hul.isnull(_updatedFields) && _updatedFields.indexOf(k)<0){
                            _updatedFields.push(k);
                        }
                        if(_updatedDetails.indexOf(__rst_ID)<0){
                            _updatedDetails.push(__rst_ID);
                        }

                        dataupdate[fieldnames[k]] = edt.value;
                    }
                }
            }//end for


            //special case for separator
            var k = fieldnames.indexOf('rst_RequirementType');
            
            if(values[dti]=='separator') {
                var ed_name = '#ed'+__rst_ID+'_rst_RequirementType_separator';   
                if($(ed_name).length>0){
                    var is_hidden = $(ed_name).is(':checked');
                    if ((is_hidden && values[k] !== 'forbidden') || (!is_hidden && values[k] == 'forbidden')){
                       isChanged = true;
                       values[k] = is_hidden?'forbidden':'optional';
                       $('#ed'+__rst_ID+'_rst_RequirementType').val(values[k]);
                            if(!Hul.isnull(_updatedFields) && _updatedFields.indexOf(k)<0){
                                _updatedFields.push(k);
                            }
                            if(_updatedDetails.indexOf(__rst_ID)<0){
                                _updatedDetails.push(__rst_ID);
                            }
                            dataupdate['rst_RequirementType'] = values[k];
                    }
                }
            }
            

            //update visible row in datatable
            if(isChanged){
                dataupdate.rst_values = values;
                //update data
                _myDataTable.updateRow(row_index, dataupdate);
                arrStrucuture[__rst_ID] = values;
            }

            return isChanged;
        }//__setFor

        if(Hul.isnull(_rst_ID)){ //for all
            //fill values from array
            var rst_ID;
            for (rst_ID in arrStrucuture){
                if(!Hul.isnull(rst_ID)){
                    __setFor(rst_ID);

                    /* given up attempt to gather all data with jQuery
                    get array of all inputs that started with ed+rst_ID
                    var arrInputs = $('[id^=ed'+rst_ID+'_]');
                    */
                }
            }
        }else{
            __setFor(_rst_ID);
        }

        //saves back to HEURIST
        _isNewOptionalFieldAppeared( arrStrucuture, old_typedefs)
        window.hWin.HEURIST4.rectypes.typedefs[rty_ID].dtFields = arrStrucuture;
    }//end of _doExpliciteCollapse

    /**
    * add or remove 'reserved' option in status dropdown
    */
    function _optionReserved(selstatus, isAdd){
        if(selstatus){
            if(isAdd && selstatus.length<4){
                window.hWin.HEURIST4.ui.addoption(selstatus, "reserved", "reserved");
            }else if (!isAdd && selstatus.length===4){
                selstatus.length=3;
                //selstaus.remove(3);
            }
        }
    }

    /**
    * Restores values from HEURSIT db structure to input controls after expand the row
    * @param _rst_ID record structure type ID
    * @param isAll - not used (false always)
    */
    function _fromArrayToUI(rst_ID, isAll)
    {
        if(!rst_ID>0) return;
        
        var findex = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex;

        var fieldnames = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNames,
        values = window.hWin.HEURIST4.rectypes.typedefs[rty_ID].dtFields[rst_ID],
        rst_type = window.hWin.HEURIST4.detailtypes.typedefs[rst_ID].commonFields[findex.dty_Type],
        selstatus = Dom.get('ed'+rst_ID+'_rst_Status'),
        ext_description = Dom.get('ed'+rst_ID+'_rst_DisplayExtendedDescription'),
        dbId = Number(window.hWin.HAPI4.sysinfo['db_registeredid']);

        //find original dbid
        var original_dbId = values[window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex.rst_OriginatingDBID];
        if(Hul.isnull(original_dbId)){
            //if original dbid is not defined for this field, we take it from common(header) of rectype
            //			original_dbId = window.hWin.HEURIST4.rectypes.typedefs[rty_ID].commonFields[window.hWin.HEURIST4.rectypes.typedefs.commonNamesToIndex.rty_OriginatingDBID];
            if(Hul.isnull(original_dbId)){
                original_dbId = dbId;
            }
        }

        var status = values[window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex.rst_Status];
        var isReserved = (status === "reserved");// || status === "approved");

        // Reserved can only be set on database controleld by the Heurist group, identified by DBID<1000
        if(selstatus){
            if (((dbId>0) && (dbId<1001) /* && ian 23/9/12 allow setting Reserved even if not origin (original_dbId===dbId) */ ) || isReserved)
            {
                _optionReserved(selstatus, true);
            }else{
                _optionReserved(selstatus, false);
            }
            //2017-02-09 no more restrictions for reserved fields
            //selstatus.disabled = ((status === "reserved") && (original_dbId!==dbId) && 
            //                      (original_dbId>0) && (original_dbId<1001));
        }
        
        //store to show warning for reserved field
        $('#ed'+rst_ID+'_rst_RequirementType').attr('data-original', values[window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex.rst_RequirementType]);
        $(selstatus).attr('data-original', status);

        if(rst_type === "separator"){

            $('#ed'+rst_ID+'_rst_RequirementType_separator').attr('checked',
            values[window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex.rst_RequirementType]=='forbidden');
        }else{
            $('#ed'+rst_ID+'_rst_RequirementType_separator').parents('.input-row').hide();  
            //.parentNode.parentNode.style.display = "block";  
        }
        
        
        var k;
        for(k=0; k<fieldnames.length; k++){
            var ed_name = 'ed'+rst_ID+'_'+fieldnames[k];
            var edt = Dom.get(ed_name);
            if( !Hul.isnull(edt) && (isAll || edt.parentNode.id.indexOf("row")<0)){
                
                if(values[k]=='increment_new_values_by_1' && fieldnames[k]=="rst_DefaultValue" && $('#incValue_'+rst_ID+'_2').length>0){
                    $('#incValue_'+rst_ID+'_2').attr('checked',true);
                    onIncrementModeChange(rst_ID);
                }else{
                    edt.value = values[k];
                }

                if(rst_type === "relmarker" && fieldnames[k] === "rst_DefaultValue"){
                    //hide defaulvalue
                    edt.parentNode.parentNode.style.display = "none";
                    //show disable jsontree
                }else if(fieldnames[k] === "rst_TermIDTreeNonSelectableIDs"){

                    var edt_def = Dom.get('ed'+rst_ID+'_rst_DefaultValue');

                    if(rst_type === "enum" || rst_type === "relmarker" || rst_type === "relationtype"){
                        //show filter jsontree
                        edt.parentNode.parentNode.style.display = "block";

                        var edt2 = Dom.get('ed'+rst_ID+'_rst_FilteredJsonTermIDTree');

                        /* Ian's request - no more filtering. ARTEM. WTF??? Why hardcode index again????!!!
                        recreateTermsPreviewSelector(rst_type,
                        (Hul.isempty(edt2.value)?window.hWin.HEURIST4.detailtypes.typedefs[rst_ID].commonFields[9]:edt2.value),//dty_JsonTermIDTree
                        (Hul.isempty(edt.value)?window.hWin.HEURIST4.detailtypes.typedefs[rst_ID].commonFields[10]:edt.value),//dty_TermIDTreeNonSelectableIDs
                        edt_def.value);
                        */
                        var allTerms = (Hul.isempty(edt2.value)?window.hWin.HEURIST4.detailtypes.typedefs[rst_ID].commonFields[findex.dty_JsonTermIDTree]:edt2.value);
                        var disabledTerms = (Hul.isempty(edt.value)?window.hWin.HEURIST4.detailtypes.typedefs[rst_ID].commonFields[findex.dty_TermIDTreeNonSelectableIDs]:edt.value);
                        var _dtyID = window.hWin.HEURIST4.detailtypes.typedefs[rst_ID].commonFields[findex['dty_ID']];
                        if(_dtyID == window.hWin.HAPI4.sysinfo['dbconst']['DT_RELATION_TYPE']){ //specific behaviour
                            allTerms = 0;
                            disabledTerms = "";
                        }

                        recreateTermsPreviewSelector(rst_ID, rst_type,
                            allTerms,
                            disabledTerms,
                            edt_def.value); //default value

                        //Dom.setStyle(edt_def.parentNode, "display", "none");

                    }else{
                        edt.parentNode.parentNode.style.display = "none";
                        Dom.setStyle(edt_def.parentNode, "display", "inline-block");
                        //var el = document.getElementById("termsDefault");
                        //Dom.setStyle(el, "display", "none");
                    }

                }else if(fieldnames[k] === "rst_PtrFilteredIDs"){
                    if(rst_type === "relmarker" || rst_type === "resource"){
                        //show filter jsontree
                        edt.parentNode.parentNode.style.display = "block";

                        /* Ian's request - no more filtering
                        recreateRecTypesPreview(rst_type,
                        (Hul.isempty(edt.value)?window.hWin.HEURIST4.detailtypes.typedefs[rst_ID].commonFields[11]:edt.value) ); //dty_PtrTargetRectypeIDs
                        */
                        recreateRecTypesPreview(rst_type,
                            window.hWin.HEURIST4.detailtypes.typedefs[rst_ID].commonFields[window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex.dty_PtrTargetRectypeIDs]);


                        
                        
                    }else{
                        edt.parentNode.parentNode.style.display = "none";
                    }

                }else if(fieldnames[k] === "rst_CreateChildIfRecPtr"){

                    if(rst_type === "resource"){
                        //$(edt).parent('.input-row').show();
                        edt.parentNode.parentNode.style.display = "block";
                        
                        $('#'+ed_name).attr('checked', values[k]==1);
                        
                        
                    }else{
                        edt.parentNode.parentNode.style.display = "none";
                        //$(edt).parent('.input-row').hide();
                    }
                    
                }else if(fieldnames[k] === "rst_PointerMode"){ // || fieldnames[k] === "rst_PointerBrowseFilter"){
                    if(rst_type === "resource"){
                        edt.parentNode.parentNode.style.display = "block";
                        onPointerModeChange(values[k], rst_ID);
                        //edt.value
                    }else{
                        edt.parentNode.parentNode.style.display = "none";
                    }
                    
                }else if(rst_type === "relationtype"){

                }else if(rst_type === "resource"){
                    //show disable target pnr rectype

                }else if(rst_type === "separator" ) {
                     if(!(fieldnames[k] === "rst_DisplayName" //|| fieldnames[k] === "rst_DisplayWidth" 
                            || fieldnames[k] === "rst_DisplayHelpText" )){ //|| fieldnames[k] === "rst_RequirementType"
                        //hide all but name  and help
                        edt.parentNode.parentNode.style.display = "none";
                     }else
                     if(fieldnames[k] === "rst_DisplayName" && edt.value.indexOf('edit the name')==edt.value.length-13){
                            edt.value = '';  
                     }
                            
                }else if(rst_type === "fieldsetmarker" && 
                        !(fieldnames[k] === "rst_DisplayName" || fieldnames[k] === "rst_Status")){ //fieldnames[k] === "rst_DisplayWidth" || 
                            //hide all, required - once
                            edt.parentNode.parentNode.style.display = "none";
                }
                
                //hide width for alle except blocktext
                if(fieldnames[k] === "rst_DisplayHeight"){
                    if(rst_type=='blocktext'){
                        ///edt.parentNode.style.display = "none";
                        $(edt).parent().show();
                    }else{
                        //edt.parentNode.style.display = "block";
                        $(edt).parent().hide();
                    }
                }else 
                //hide width for some field types
                if(fieldnames[k] === "rst_DisplayWidth"){
                    if(_isNoWidth(rst_type) ){
                        //edt.parentNode.style.display = "none";
                        $(edt).parent().hide();
                    }else{
                        //edt.parentNode.style.display = "block";
                        $(edt).parent().show();
                    }
                }


            }
        }//for



        if(rst_type === "separator"){
            //hide repeatability
            $('#divRepeatability'+rst_ID).hide();
            $('#divMoreDefs'+rst_ID).hide();
            
        }else{

            //determine what is repeatability type
            var sel = Dom.get("ed"+rst_ID+"_Repeatability");
            var maxval = Number(Dom.get("ed"+rst_ID+"_rst_MaxValues").value);
            var res = 'repeatable';
            if(maxval===1){
                res = 'single';
            }else if(maxval>1){
                res = 'limited';
            }
            
            $(sel).attr('data-original', res);
            
            sel.value = res;
            onRepeatChange(Number(rst_ID));

            //update min/max visibility
            onReqtypeChange(Number(rst_ID));
        }
        var ele = Dom.get('ed'+rst_ID+'_rst_DisplayName');
        ele.focus();
        if(rst_type === "separator"){
            //ele.select();
            ele.setSelectionRange(0, ele.value.length);
        }

        //If reserved, requirements can only be increased, nor can you change min or max values
        onStatusChange(Number(rst_ID));
    }

    /**
    * These field types have no width
    */
    function _isNoWidth(typ){
        return ((typ === "enum") || (typ==="resource") ||  (typ==="fieldsetmarker") ||    
            (typ==="relmarker") || (typ === "relationtype") ||
            (typ==="file") || (typ==="geo") || (typ==="separator"));
    }


    /**
    * adds separator field type
    */
    function _onAddSeparator(index_toinsert){

        //find seprator field type ID that is not yet added to this record strucuture
        var ft_separator_id =  null;
        var ft_separator_group =  window.hWin.HEURIST4.detailtypes.groups[0].id;
        var dtypes = window.hWin.HEURIST4.detailtypes.typedefs;
        var fi = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex;
        var fnames = window.hWin.HEURIST4.detailtypes.typedefs.commonFieldNames;
        var recDetTypes = window.hWin.HEURIST4.rectypes.typedefs[rty_ID].dtFields;

        var ind, k = 1;
        for (ind in dtypes){
            if(!Hul.isnull(ind) && !isNaN(Number(ind)) ){
                if(dtypes[ind].commonFields[fi.dty_Type]==="separator"){
                    k++;
                    ft_separator_group = dtypes[ind].commonFields[fi.dty_DetailTypeGroupID];
                    if(Hul.isnull(recDetTypes[ind])){
                        ft_separator_id = ind;
                        break;
                    }
                }
            }
        }

        if(!Hul.isnull(ft_separator_id)){
            _doExpliciteCollapse(null, true);
            _addDetails(ft_separator_id, index_toinsert);
        }else{ //"not used" separator field type not found - create new one

            var _detailType = [];//new Array();

            _detailType[fnames[fi.dty_Name]] = 'DIVIDER '+k;
            _detailType[fnames[fi.dty_ExtendedDescription]] = '';
            _detailType[fnames[fi.dty_Type]] = 'separator';
            _detailType[fnames[fi.dty_OrderInGroup]] = 0;
            _detailType[fnames[fi.dty_HelpText]] = ''; //'Dividers serve to break the data entry form up into sections';
            _detailType[fnames[fi.dty_ShowInLists]] = 1;
            _detailType[fnames[fi.dty_Status]] = 'open';
            _detailType[fnames[fi.dty_DetailTypeGroupID]] = ft_separator_group;
            _detailType[fnames[fi.dty_FieldSetRectypeID]] = null;
            _detailType[fnames[fi.dty_JsonTermIDTree]] = null;
            _detailType[fnames[fi.dty_TermIDTreeNonSelectableIDs]] = null;
            _detailType[fnames[fi.dty_PtrTargetRectypeIDs]] = null;
            _detailType[fnames[fi.dty_NonOwnerVisibility]] = 'viewable';


            var oDetailType = {detailtype:{
                colNames:{common:[]},
                defs: {}
            }};

            oDetailType.detailtype.defs[-1] = {};
            oDetailType.detailtype.defs[-1].common = [];

            var fname;
            for (fname in _detailType){
                if(!Hul.isnull(fname)){
                    oDetailType.detailtype.colNames.common.push(fname);
                    oDetailType.detailtype.defs[-1].common.push(_detailType[fname]);
                }
            }

            var str = YAHOO.lang.JSON.stringify(oDetailType);


            function _addNewSeparator(response) {

                if(response.status == window.hWin.ResponseStatus.OK){
    
                    var error = false,
                    report = "",
                    ind;

            
//console.log('added on server '+(new Date().getTime() / 1000 - _time_debug));            
//_time_debug = new Date().getTime() / 1000;                       
                    
                    for(ind in response.data.result){
                        if( !Hul.isnull(ind) ){
                            var item = response.data.result[ind];
                            if(isNaN(item)){
                                window.hWin.HEURIST4.msg.showMsgErr(item);
                                error = true;
                            }else{
                                ft_separator_id = ""+Math.abs(Number(item));

                                //refresh the local heurist
                                window.hWin.HEURIST4.detailtypes = response.data.detailtypes;

                                _doExpliciteCollapse(null, true);
                                _addDetails(ft_separator_id, index_toinsert);
                            }
                        }
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            }
                                   
//            _time_debug = new Date().getTime() / 1000;                       
//console.log('send save separator')            
            //
            var baseurl = window.hWin.HAPI4.baseURL + "admin/structure/saveStructure.php";
            var callback = _addNewSeparator;
            
            var request = {method:'saveDT', db:window.hWin.HAPI4.database, data:oDetailType};    //add separator
            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, callback);
            

        }
    }
    
    var _time_debug;
    
    function _getIndex_toinsert(event){
        
        var recs = _myDataTable.getRecordSet();
        var row_index, k, len = recs.getLength();
        
        var index_toinsert = recs.getLength();
        var rst_ID;
        
        if(event!=null){
            event = window.hWin.HEURIST4.util.stopEvent(event);
            var targ = event.target;
            rst_ID = targ.getAttribute("rst_ID");
            if(!rst_ID) return null;

            index_toinsert = _getRecordById(rst_ID).row_index+1; 
        }else{
            var sels = _myDataTable.getSelectedRows();
            
            for (row_index = 0; row_index < len; row_index++ )
            {
                var rec = _myDataTable.getRecord(row_index);
                if(sels[0]==rec.getId()){
                    index_toinsert = row_index+1;
                    break;
                }
            }
        }
            
       return index_toinsert;  
    }

    /**
    * Adds the list of new detail types to this record structure
    *
    * After addition it saves all on server side
    * This is function for global method addDetail. It is invoked after selection of detail types or creation of new one
    * @param dty_ID_list - comma separated list of detail type IDs
    */
    function _addDetails(dty_ID_list, index_toinsert, defValues){

        
        if(dty_ID_list=='section_header'){
            _onAddSeparator(index_toinsert);
            return;
        }

        var arrDty_ID = dty_ID_list.split(",");
        if(Hul.isempty(dty_ID_list) || arrDty_ID.length<1) {
            return;
        }

        var recDetTypes = window.hWin.HEURIST4.rectypes.typedefs[rty_ID].dtFields;

        //new odetail type
        if(Hul.isnull(recDetTypes)){
            recDetTypes = {}; //new Object();
        }
        if(Hul.isnull(defValues)) defValues = {rst_RequirementType:'optional',rst_MinValues:0,rst_MaxValues:1};
        


        var data_toadd = [];
        var detTypes = window.hWin.HEURIST4.detailtypes.typedefs,
        fi = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex,
        rst = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex;

        //find max order and index to insert
        var recs = _myDataTable.getRecordSet();
        var row_index, k, len = recs.getLength();

        if(Hul.isnull(index_toinsert)){
            
            index_toinsert = _getIndex_toinsert()
            
        }

        var order = 0;
        if(index_toinsert<0){
            index_toinsert = 0;
        }else{
            var rec = _myDataTable.getRecord(index_toinsert>=recs.getLength()?recs.getLength()-1:index_toinsert);
            order = Number(rec.getData('rst_DisplayOrder'));
        }
        
        //moves detail types to

        for(k=0; k<arrDty_ID.length; k++){
            var dty_ID = arrDty_ID[k];
            if(Hul.isnull(recDetTypes[dty_ID])){ //not added
                var arrs = detTypes[dty_ID].commonFields;
                // add new detail type
                // note that integer, boolean, year, urlinclude can no longer be created but are retained for backward compatibility
                var def_width = 80, def_height = 0;  // default width, used by single line text fields
                var dt_type = arrs[fi.dty_Type];

                if (_isNoWidth(dt_type))  // types which have no intrinsic width ie. adapt to content
                {
                    def_width = 0;
                }else if (dt_type === "blocktext"){  // multi line text generally need to be wider
                    def_width = 100;
                    def_height = 8;
                }else if (dt_type === "date" || dt_type === "integer" || dt_type === "float" || dt_type === "year" ||
                    dt_type === "calculated") { // numeric and date don't need much width by default
                        def_width = 20;
                    }else if (dt_type === "boolean") {
                        def_width = 4; break;
                    }
                    
                var isSingleSep = (arrDty_ID.length==1 && dt_type === 'separator');

                var arr_target = new Array();
                arr_target[rst.rst_DisplayName] = arrs[fi.dty_Name];
                arr_target[rst.rst_DisplayHelpText] = (isSingleSep)?'':arrs[fi.dty_HelpText];
                arr_target[rst.rst_DisplayExtendedDescription] = arrs[fi.dty_ExtendedDescription];
                arr_target[rst.rst_DefaultValue ] = "";
                arr_target[rst.rst_RequirementType] = defValues.rst_RequirementType;
                arr_target[rst.rst_MaxValues] = defValues.rst_MaxValues;
                arr_target[rst.rst_MinValues] = defValues.rst_MinValues;
                arr_target[rst.rst_DisplayWidth] = def_width;
                arr_target[rst.rst_DisplayHeight] = def_height; //rows
                arr_target[rst.rst_RecordMatchOrder] = "0";
                arr_target[rst.rst_DisplayOrder] = order;
                arr_target[rst.rst_DisplayDetailTypeGroupID] = "1";
                arr_target[rst.rst_FilteredJsonTermIDTree] = null;
                arr_target[rst.rst_PtrFilteredIDs] = null;
                arr_target[rst.rst_CreateChildIfRecPtr] = 0;
                arr_target[rst.rst_PointerMode] = 'addorbrowse',
                arr_target[rst.rst_PointerBrowseFilter] = null,
                arr_target[rst.rst_TermIDTreeNonSelectableIDs] = null;
                arr_target[rst.rst_CalcFunctionID] = null;
                arr_target[rst.rst_Status] = "open";
                arr_target[rst.rst_OrderForThumbnailGeneration] = null;
                arr_target[rst.dty_TermIDTreeNonSelectableIDs] = null;
                arr_target[rst.dty_FieldSetRectypeID] = null;
                arr_target[rst.rst_NonOwnerVisibility] = "viewable";

                recDetTypes[dty_ID] = arr_target;

                data_toadd.push({
                    rst_ID:dty_ID,
                    addColumn:dty_ID,
                    expandColumn:dty_ID,
                    rst_DisplayOrder: order,
                    dty_Name: arrs[fi.dty_Name],
                    rst_DisplayName: arrs[fi.dty_Name], //isSingleSep?'':
                    dty_Type: arrs[fi.dty_Type],
                    rst_RequirementType: defValues.rst_RequirementType,
                    rst_DisplayWidth: def_width,
                    rst_DisplayHeight: def_height,
                    rst_MinValues: defValues.rst_MinValues,
                    rst_MaxValues: defValues.rst_MaxValues,
                    rst_DefaultValue: "",
                    rst_Status: "open",
                    rst_NonOwnerVisibility: "viewable",
                    rst_values: arr_target });


                _updatedDetails.push(dty_ID); //track change

                order++;
                
                if(defValues.rst_RequirementType=='optional'){
                     _newOptionalFields[dty_ID] = true; 
                }
            }
        }//end for

        if(data_toadd.length>0){
            
            window.hWin.HEURIST4.rectypes.typedefs[rty_ID].dtFields = recDetTypes;

            _myDataTable.addRows(data_toadd, index_toinsert);

//console.log('added on datatable '+(new Date().getTime() / 1000 - _time_debug));            
//_time_debug = new Date().getTime() / 1000;                       
            
            _myDataTable.unselectAllRows();
            //ART16 _myDataTable.selectRow(index_toinsert);

            // in case of addition - all fields were affected
            _updatedFields = null;

            dragDropDisable();
            dragDropEnable();

            _saveUpdates(false, function(){

//console.log('saved into structure '+(new Date().getTime() / 1000 - _time_debug));            
//_time_debug = new Date().getTime() / 1000;                       
                
                //ART 2018-07-30 
                _updateOrderAfterInsert( data_toadd[data_toadd.length-1]['rst_ID'] );
                
            });

            $("#recStructure_toolbar").hide();

        }

    }//end _addDetails

    //
    //
    //
    function _updateOrderAfterInsert( lastID ) {

        var recs = _myDataTable.getRecordSet(),
        len = recs.getLength(),
        neworder = [],
        isChanged = false,
        i;

        //loop through current records and see if this has been added before
        for ( i = 0; i < len; i++ )
        {
            var rec = _myDataTable.getRecord(i);
            var data = rec.getData();
            //if it's been added already, update it
            if(data.rst_DisplayOrder !== i){
                data.rst_DisplayOrder = i;

                //_myDataTable.updateRow(i, data);  - important this method replace table row to new one
                //var rowrec = _myDataTable.getTrEl(rec);
                var id = rec.getId();
                if (myDTDrags[id]) {
                    myDTDrags[id].unreg();
                    delete myDTDrags[id];
                }
                myDTDrags[id] = new YAHOO.example.DDRows(id);

                if(_updatedDetails.indexOf(data.rst_ID)<0){
                    _updatedDetails.push(data.rst_ID);
                }
                window.hWin.HEURIST4.rectypes.typedefs[rty_ID].dtFields[data.rst_ID][window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex.rst_DisplayOrder] = i;
                isChanged = true;
            }
            neworder.push(data.rst_ID);
        }

        if(isChanged){
            //index if field rst_DisplayOrder
            var field_index = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex.rst_DisplayOrder;

            if(!Hul.isnull(_updatedFields) && _updatedFields.indexOf(field_index)<0){
                _updatedFields.push(field_index);
            }
            window.hWin.HEURIST4.rectypes.dtDisplayOrder[rty_ID] = neworder;
            _saveUpdates(false);
        }
        
        _isServerOperationInProgress = false;
        
        //remark this exit to prevent expand after insert
        if($('#showEditOnNewField').is(':checked')){
            
            //emulate click on just added row
            setTimeout(function(){
               
//console.log('expand '+(new Date().getTime() / 1000 - _time_debug));            
//_time_debug = new Date().getTime() / 1000;                       
                
                var oRecord = _getRecordById(lastID).record;
                var elLiner = _myDataTable.getTdLinerEl({record:oRecord, column:_myDataTable.getColumn('expandColumn')});
                //$.find("tr.yui-dt-last > td.yui-dt-col-rst_DisplayName")[0]
                var oArgs = {event:'MouseEvent', target: elLiner};
                onCellClickEventHandler(oArgs);  //expand
            },50);
        }
    }
    
    function _isNewOptionalFieldAppeared(typedef_new, typedef){
        
        if(typedef_new.typedefs) typedef_new = typedef_new.typedefs[rty_ID].dtFields;
        if(!typedef) typedef = window.hWin.HEURIST4.rectypes.typedefs[rty_ID].dtFields;
        
        var fi_req = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex.rst_RequirementType;
                       
        for (var dty_ID  in typedef_new) {
            if(typedef_new[dty_ID][fi_req]=='optional'){
                
                if(!typedef[dty_ID] || typedef[dty_ID][fi_req]!='optional'){
                    _newOptionalFields[dty_ID] = true;                
                }
                
            }else if( _newOptionalFields[dty_ID] ){ //removed or was optional
                delete _newOptionalFields[dty_ID];
            }
        }        
        for (var dty_ID  in typedef) {
            if( _newOptionalFields[dty_ID] && !typedef_new[dty_ID] ){ //deleted
                delete _newOptionalFields[dty_ID];
            }
        }
        
    }
    

    /**
    * asyncSubmitter for inline editor
    */
    function _updateSingleField(dty_ID, fieldName, oOldValue, oNewValue, fnCallback){
         
         if(fnCallback) fnCallback(true, oNewValue);
          
         if(oOldValue!=oNewValue){
    
            _isServerOperationInProgress = true;             
             
            _myDataTable.render();
            //create and fill the data structure
            var orec = {rectype:{
                colNames:{common:[], dtFields:[fieldName]},
                defs: {}
            }};
            orec.rectype.defs[rty_ID] = {common:[], dtFields:{}};
            orec.rectype.defs[rty_ID].dtFields[dty_ID] = [oNewValue];
            
            var str = YAHOO.lang.JSON.stringify(orec);            
     
            var updateResult = function(response){
                if(response.status == window.hWin.ResponseStatus.OK){

                    _isNewOptionalFieldAppeared( response.data.rectypes );
                
                    window.hWin.HEURIST4.rectypes = response.data.rectypes;
                    window.hWin.HEURIST4.detailtypes = response.data.detailtypes;
                    window.hWin.HEURIST4.terms = response.data.terms;
    
                    editStructure._structureWasUpdated = true;
                    
                    //fnCallback(true, oNewValue);
                }else{
                    //window.hWin.HEURIST4.msg.showMsgErr(response);
                }
                _isServerOperationInProgress = false;
                
                dragDropDisable();
                dragDropEnable();
                
                $('.save-btn').removeProp('disabled').css('opacity','1');
            };
            
            $('.save-btn').prop('disabled', 'disabled').css('opacity','0.3');
            
            var baseurl = window.hWin.HAPI4.baseURL + "admin/structure/saveStructure.php";
            var callback = updateResult;
            
            var request = {method:'saveRTS', db:window.hWin.HAPI4.database, data:orec};
            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, callback);
            
            
         }else{
            //fnCallback(true, oNewValue); 
         }
    }

    

    /**
    * Clears _updateXXX arrays
    *
    */
    function _clearUpdates(){
        _updatedDetails = [];  //list of dty_ID that were affected with edition
        _updatedFields = [];   //list of indexes in fieldname array that were affected
    }

    /**
    * Creates and fills the data structure to be sent to server
    * It takes data from HEURIST db - it is already modified before this operation
    * reference what fields and details ara updated are taken from _updatedFields and _updatedDetails
    *
    * @return stringfied JSON array with data to be sent to server
    */
    function _getUpdates()
    {
        _fromUItoArray(null); //save all changes

        if(!Hul.isnull(rty_ID) && _updatedDetails.length>0){
            var k;
            //create and fill the data structure
            var orec = {rectype:{
                colNames:{common:[], dtFields:[]},
                defs: {}
            }};
            //fill array of updated fieldnames
            var fieldnames = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNames;
            if(Hul.isnull(_updatedFields)){ //all fields are updated
                _updatedFields = [];
                for(k=0; k<fieldnames.length; k++){
                    orec.rectype.colNames.dtFields.push(fieldnames[k]);
                    _updatedFields.push(k);
                }
            }else{
                for(k=0; k<_updatedFields.length; k++){
                    orec.rectype.colNames.dtFields.push(fieldnames[_updatedFields[k]]);
                }
            }
            orec.rectype.defs[rty_ID] = {common:[], dtFields:{}};
            var typedefs = window.hWin.HEURIST4.rectypes.typedefs[rty_ID].dtFields;
            //loop through updated details
            for(k=0; k<_updatedDetails.length; k++){
                var dt_id = _updatedDetails[k];
                var vals = [];
                var l;
                for(l=0; l<_updatedFields.length; l++){
                    vals.push(typedefs[dt_id][_updatedFields[l]]);
                }
                orec.rectype.defs[rty_ID].dtFields[_updatedDetails[k]] = vals;
            }
            //var str = YAHOO.lang.JSON.stringify(orec);
            return orec;
        }else{
            return {};
        }
    }

    /**
    * Sends all changes to server
    * before sending it prepares the object with @see _getUpdates
    *
    * @needClose - if true closes this popup window, but it is always false now
    */
    function _saveUpdates(needClose, callback_after_save)
    {
        if(_isStreamLinedAdditionAction) return; //do nothing
        
        var orec = _getUpdates();
        //if(str!=null)	alert(str);  //you can check the strcuture here
        //clear all trace of changes
        _clearUpdates();

        var btnSaveOrder = Dom.get('btnSaveOrder');
        btnSaveOrder.style.display = "none";
        btnSaveOrder.style.visibility = "hidden";

        if(!$.isEmptyObject(orec)){
            
            window.hWin.HEURIST4.msg.bringCoverallToFront( $(this.document).find('body') );            

            var updateResult = function(response){
                
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                
                _isServerOperationInProgress = false;
                
                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    _isNewOptionalFieldAppeared( response.data.rectypes );
                                               
                    window.hWin.HEURIST4.rectypes = response.data.rectypes;
                    window.hWin.HEURIST4.detailtypes = response.data.detailtypes;
                    window.hWin.HEURIST4.terms = response.data.terms;
                    
                    editStructure._structureWasUpdated = true;
                    if(needClose){
                        window.close(editStructure._structureWasUpdated);
                    }else if($.isFunction(callback_after_save)){
                        callback_after_save.call();
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);            
                }                                                 
                
            };
            var baseurl = window.hWin.HAPI4.baseURL + "admin/structure/saveStructure.php";
            var callback = updateResult;
            
            _isServerOperationInProgress = true;
            var request = {method:'saveRTS', db:window.hWin.HAPI4.database, data:orec};
            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, callback);
            

        }else if(needClose){
            window.close();
        }
    }

    /**
    * returns true if there is at list on required field in structure
    */
    function _checkForRequired(){
        
        var typedef = window.hWin.HEURIST4.rectypes.typedefs[rty_ID];
        var fi = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex;
        var _dts = typedef.dtFields;

        //only for this group and visible in UI
        if(!Hul.isnull(_dts)){
            var rst_ID;  //field type ID
            for (rst_ID in _dts) {
                var aval = _dts[rst_ID];
                if(aval[fi.rst_RequirementType] === "required"){
                    return true;
                }
            }
        }

        return false;
    }

    /**
    * verify titlemask, some fields may be removed while editing rectype structure
    * (similar in editRectypeTitle.js)
    */
    function _checkForTitleMask(callback)
    {
        var typedef = window.hWin.HEURIST4.rectypes.typedefs[rty_ID];
        var mask = typedef.commonFields[ window.hWin.HEURIST4.rectypes.typedefs.commonNamesToIndex.rty_TitleMask ];
        
        var baseurl = window.hWin.HAPI4.baseURL + "hsapi/controller/rectype_titlemask.php";

        var request = {rty_id:rty_ID, mask:mask, db:window.hWin.HAPI4.database, check:1}; //verify titlemask
        
        window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, 
            function (response) {
                if(response.status != window.hWin.ResponseStatus.OK || response.message){

                      var ele = document.getElementById("dlgWrongTitleMask");
                    
                      window.hWin.HEURIST4.msg.showElementAsDialog({
                          element:ele,
                          close: function(){ _doEditTitleMask(true); },
                          title:'Wrong title mask',height:400, height:160}
                      );

                    /*$(ele).find("#dlgWrongTitleMask_closeBtn").click(function(){
                        if($dlg_warn!=null){
                            $dlg_warn.dialog('close');
                        }
                        _doEditTitleMask(true);
                    });*/
                    
                }else{ //correct mask
                    if(callback){
                        callback.call();
                    }
                }                                        
            }
        );

    }


    //show edit title mask
    function _doEditTitleMask(is_onexit){
        
        //@todo if(is_onexit) top.HEURIST.util.closePopupLast();
        
        warningPopupID = null;
        
        var typedef = window.hWin.HEURIST4.rectypes.typedefs[rty_ID];
        var maskvalue = typedef.commonFields[ window.hWin.HEURIST4.rectypes.typedefs.commonNamesToIndex.rty_TitleMask ];

        var sURL = window.hWin.HAPI4.baseURL +
            "admin/structure/rectypes/editRectypeTitle.html?rectypeID="+rty_ID
            +"&mask="+encodeURIComponent(maskvalue)+"&db="+window.hWin.HAPI4.database;
            
        window.hWin.HEURIST4.msg.showDialog(sURL, {    
                "close-on-blur": false,
                "no-resize": true,
                title: 'Record Type Title Mask Edit',
                height: 800,
                width: 800,
                callback: function(newvalue) {
                    if(!is_onexit && newvalue){
                        document.getElementById('rty_TitleMask').value = newvalue;    
                    }
                },
                afterclose: function(){
                    popupSelect = null;
                }
        });
    }

    function _onAddFieldAtIndex(e, isSectionHeader){
        
        var index_toinsert = _getIndex_toinsert(e);
        
        if(index_toinsert>=0){
            
            /*
            var recs = _myDataTable.getRecordSet();
            var index_toinsert = recs.getLength();
            
            if(e!=null){
                e = window.hWin.HEURIST4.util.stopEvent(e);
                var targ = e.target;
                var rst_ID = targ.getAttribute("rst_ID");
                if(!rst_ID) return;

                index_toinsert = _getRecordById(rst_ID).row_index;
            }
            */

            _myDataTable.unselectAllRows();

            
            if(isSectionHeader===true){
                _onAddSeparator(index_toinsert);
            }else{
                onAddNewDetail(index_toinsert);
            }
        
        }
    }

    //Ian decided to remove this feature -however it may be helpful in another place
    var onMenuClick = function (eventName, eventArgs, subscriptionArg){
        var clonearr =  window.hWin.HEURIST4.util.cloneJSON(subscriptionArg);
        var fname = clonearr.shift();
        var args = clonearr;
        //@todo top.HEURIST.util.executeFunctionByName(fname, window, args);
    }

    function _addFieldMenu(e){

        e = window.hWin.HEURIST4.util.stopEvent(e);
        var targ = e.target;
        var rst_ID = targ.getAttribute("rst_ID")

        if(!rst_ID) return;

        var index_toinsert = _getRecordById(rst_ID).row_index+1;
        _myDataTable.unselectAllRows();
        //ART16 _myDataTable.selectRow(index_toinsert);

        var oMenu;

        if(!_fieldMenu){
            _fieldMenu = new YAHOO.widget.Menu("menu_addfield",{zindex: 21});
            _fieldMenu.addItems([
                { text: "Add field (use existing definition)" },
                { text: "Define field (create new definition)" },
                { text: "Add section divider" }
            ]);
            _fieldMenu.render(document.getElementById('recStrcuture_bottom')); //document.body);
            $("#menu_addfield").bind("mouseleave",function(){
                _fieldMenu.hide();
            });
        }
        oMenu = _fieldMenu;
        //_fieldMenu.hide();

        var items = oMenu.getItems();
        items[0].cfg.setProperty("onclick", { fn: onMenuClick, obj: ["onAddNewDetail", index_toinsert] } );
        items[1].cfg.setProperty("onclick", { fn: onMenuClick, obj: ["onDefineNewType", index_toinsert] } );
        items[2].cfg.setProperty("onclick", { fn: onMenuClick, obj: ["onAddSeparator", index_toinsert] } );


        oMenu.cfg.setProperty("context",
            [e.target, "bl", "bl"]);
        oMenu.show();

    }



    //------------------- DRAG AND DROP ROUTINES

    /**
    * Enables/disbales drag mode
    * This mode is disable if some row becomes expanded
    */
    function _setDragEnabled(newmode) {
        if (newmode !== _isDragEnabled)
        {
            _isDragEnabled = newmode;
            if(_isDragEnabled){
                //_fromUItoArray(null); //save all changes
                //_myDataTable.collapseAllRows();
                dragDropEnable();
            }else{
                dragDropDisable();
            }
            DDM.refreshCache();
        }
    }

    //////////////////////////////////////////////////////////////////////////////
    // Init drag and drop class (eanble)
    //////////////////////////////////////////////////////////////////////////////
    function dragDropEnable() {
        var i, id,
        allRows = _myDataTable.getTbodyEl().rows;

        for(i=0; i<allRows.length; i++) {
            id = allRows[i].id;
            // Clean up any existing Drag instances
            if (myDTDrags[id]) {
                try{
                    myDTDrags[id].unreg();
                }catch(e){
                    console.log('cant unreg prev drag');
                }
                delete myDTDrags[id];
            }
            // Create a Drag instance for each row
            myDTDrags[id] = new YAHOO.example.DDRows(id);
        }
        _isDragEnabled = true;
    }
    //////////////////////////////////////////////////////////////////////////////
    // Disable drag and drop class
    //////////////////////////////////////////////////////////////////////////////
    function dragDropDisable() {
        var i, id,
        allRows = _myDataTable.getTbodyEl().rows;

        for(i=0; i<allRows.length; i++) {
            id = allRows[i].id;
            // Clean up any existing Drag instances
            if (myDTDrags[id]) {
                myDTDrags[id].unreg();
                delete myDTDrags[id];
            }
        }
        myDTDrags = {};
        _isDragEnabled = false;
    }

    /**
    * Updates order after drag and drop
    */
    function _updateOrderAfterDrag() {

        var recs = _myDataTable.getRecordSet(),
        len = recs.getLength(),
        neworder = [],
        isChanged = false,
        i;

        //loop through current records and see if this has been added before
        for ( i = 0; i < len; i++ )
        {
            var rec = _myDataTable.getRecord(i);
            var data = rec.getData();
            //if it's been added already, update it
            if(data.rst_DisplayOrder !== i){
                data.rst_DisplayOrder = i;

                _myDataTable.updateRow(i, data);
                var id = rec.getId();
                if (myDTDrags[id]) {
                    myDTDrags[id].unreg();
                    delete myDTDrags[id];
                }
                myDTDrags[id] = new YAHOO.example.DDRows(id);


                if(_updatedDetails.indexOf(data.rst_ID)<0){
                    _updatedDetails.push(data.rst_ID);
                }

                //@todo update rst_values directly
                window.hWin.HEURIST4.rectypes.typedefs[rty_ID].dtFields[data.rst_ID][window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex.rst_DisplayOrder] = i;
                /*
                var ed_name = 'ed'+data.rst_ID+'_rst_DisplayOrder';
                var edt = Dom.get(ed_name);
                edt.value = i; */

                isChanged = true;
            }

            neworder.push(data.rst_ID);
        }

        if(isChanged){
            //index if field rst_DisplayOrder
            var field_index = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex.rst_DisplayOrder;

            if(!Hul.isnull(_updatedFields) && _updatedFields.indexOf(field_index)<0){
                _updatedFields.push(field_index);
            }
            window.hWin.HEURIST4.rectypes.dtDisplayOrder[rty_ID] = neworder;

            dragDropDisable();
            dragDropEnable();
            //					DDM.refreshCache();

            _saveUpdates(false);
            /* art 2012-01-17 - save at once
            var btnSaveOrder = YAHOO.util.Dom.get('btnSaveOrder');
            btnSaveOrder.style.display = "inline-block";
            btnSaveOrder.style.visibility = "visible";
            */
        }
    }

    
    //////////////////////////////////////////////////////////////////////////////
    // Custom drag and drop class
    //////////////////////////////////////////////////////////////////////////////
    YAHOO.example.DDRows = function(id, sGroup, config) {
        YAHOO.example.DDRows.superclass.constructor.call(this, id, sGroup, config);
        Dom.addClass(this.getDragEl(),"dragrow-class");
        this.goingUp = false;
        this.lastY = 0;
    };

    //////////////////////////////////////////////////////////////////////////////
    // DDRows extends DDProxy
    //////////////////////////////////////////////////////////////////////////////
    YAHOO.extend(YAHOO.example.DDRows, YAHOO.util.DDProxy, {
        proxyEl: null,
        srcEl:null,
        srcData:null,
        srcIndex: null,
        tmpIndex:null,

        startDrag: function(x, y) {

            if(!_isDragEnabled) { return; }
            
            if(_highightedRow!=null) _myDataTable.unhighlightRow(_highightedRow);            

            proxyEl = this.proxyEl = this.getDragEl();
            srcEl = this.srcEl = this.getEl();
            
            var dcol  = $(srcEl).find('td.yui-dt0-col-rst_NonOwnerVisibility');

            if(x<dcol.position().left+10 || x>dcol.position().left+dcol.width()){ //area on row where DnD can be inited
                proxyEl.innerHTML = "";
                Dom.setStyle(this.proxyEl, "visibility", "hidden");
                Dom.setStyle(srcEl, "visibility", "");
                this.srcIndex = null;
                return;
            }

            var rec = _myDataTable.getRecord(this.srcEl);
            if(Hul.isnull(rec)) { return; }
            this.srcData = rec.getData();
            this.srcIndex = srcEl.sectionRowIndex;
            // Make the proxy look like the source element
            
            Dom.setStyle(srcEl, "visibility", "hidden");  //hide original
            //proxyEl.innerHTML = "<table><tbody>"+srcEl.innerHTML+"</tbody></table>";
            proxyEl.innerHTML = ''; //'<div class="dragrow">'+this.srcData.rst_DisplayName+"</div>";
            proxyEl.style.cursor = "row-resize";
            //$(proxyEl).css({'background':'green', 'border':'2px solid red'}); //rgb(200,200,200,0.5)

            //var rst_ID = this.srcData.rst_ID
            //_fromUItoArray(rst_ID); //before collapse save to UI

        },

        endDrag: function(x,y) {

            if(this.srcIndex===null) { return; }

            var position,
            srcEl = this.srcEl;

            //proxyEl.innerHTML = "";
            Dom.setStyle(this.proxyEl, "visibility", "hidden"); //hide drag div
            Dom.setStyle(srcEl, "visibility", "");
            
            //restore color and font
            if(this.tmpIndex>=0){
                var rec = _myDataTable.getRecord(this.tmpIndex);
                var rowrec = _myDataTable.getTrEl(rec);
                Dom.setStyle(rowrec,'font-weight','normal');
                Dom.setStyle(rowrec,'background','');
                
                _highightedRow = rowrec;
                _myDataTable.highlightRow(_highightedRow);
                //$(_highightedRow).effect( 'bounce', {}, 500, function(){_updateOrderAfterDrag()} );
                _updateOrderAfterDrag();
               
            }else{
                _updateOrderAfterDrag();    
            }
            
        },
        onDrag: function(e) {
            // Keep track of the direction of the drag for use during onDragOver
            var y = Event.getPageY(e);

            if (y < this.lastY) {
                this.goingUp = true;
            } else if (y > this.lastY) {
                this.goingUp = false;
            }

            this.lastY = y;
        },

        onDragOver: function(e, id) {
            // Reorder rows as user drags
            var srcIndex = this.srcIndex,
            destEl = Dom.get(id);

            if(srcIndex===null) { return; }

            if(destEl){

                var destIndex = destEl.sectionRowIndex,
                tmpIndex = this.tmpIndex;

                if (destEl.nodeName.toLowerCase() === "tr") {
                    if(!Hul.isnull(tmpIndex)) {
                        _myDataTable.deleteRow(tmpIndex);
                    }
                    else {
                        _myDataTable.deleteRow(this.srcIndex);
                    }

                    //this.srcData.rst_DisplayOrder = destIndex;

                    _myDataTable.addRow(this.srcData, destIndex);
                    this.tmpIndex = destIndex;
                    //add special color for destIndex
                    var rec = _myDataTable.getRecord(this.tmpIndex);
                    var rowrec = _myDataTable.getTrEl(rec);
                    Dom.setStyle(rowrec,'font-weight','bold');
                    Dom.setStyle(rowrec,'background','LightSkyBlue');
            


                    //_updateOrderAfterDrag();

                    DDM.refreshCache();
                }
            }
        }
    });
    
    
    function _refreshTitleAndIcon(){
        var recTypeIcon  = window.hWin.HAPI4.iconBaseURL+rty_ID+'&t='+(new Date()).getTime();
        var formTitle = document.getElementById('recordTitle');
        //formTitle.innerHTML = '';
        formTitle.innerHTML = "<div class=\"rectypeIconHolder\" style=\"background-image:url("+
                recTypeIcon+")\"></div><span class=\"recTypeName\">"+window.hWin.HEURIST4.rectypes.names[rty_ID]+"</span>"+
                hToolBar;
        
        var fi  = window.hWin.HEURIST4.rectypes.typedefs.commonNamesToIndex;
        var rectype = window.hWin.HEURIST4.rectypes.typedefs[rty_ID].commonFields;

        document.getElementById('rty_TitleMask').value = rectype[fi.rty_TitleMask];
        document.getElementById('rty_Description').innerHTML = rectype[fi.rty_Description];

        document.getElementById("div_rty_ID").innerHTML = 'Local ID: '+rty_ID;
        
        if(Hul.isempty(rectype[fi.rty_ConceptID])){
            document.getElementById("div_rty_ConceptID").innerHTML = '';
        }else{
            document.getElementById("div_rty_ConceptID").innerHTML = 'Concept Code: '+ rectype[fi.rty_ConceptID];
        }
    }
    
    //
    // apparently it should be moved to ui?
    //
    function __findParentRecordTypes(childRecordType){

        var parentRecordTypes = []; //result
        
        var fieldIndexMap = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex;
        //var dtyFieldNamesIndexMap = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex;
        
        childRecordType = ''+childRecordType; //must be strig otherwise indexOf fails
        
        var rtyID, dtyID, allrectypes = window.hWin.HEURIST4.rectypes.typedefs;
        for (rtyID in allrectypes)
        if(rtyID>0){

            var fields = allrectypes[rtyID].dtFields;
            
            for (dtyID in fields)
            if(dtyID>0 && 
                fields[dtyID][fieldIndexMap['dty_Type']]=='resource' &&
                fields[dtyID][fieldIndexMap['rst_CreateChildIfRecPtr']]==1)
            { //for all fields in this rectype
                var ptrIds = fields[dtyID][fieldIndexMap['rst_PtrFilteredIDs']];
                if(ptrIds.split(',').indexOf(childRecordType)>=0){
                    parentRecordTypes.push(rtyID);
                    break;
                }
            }
        }
        
        return parentRecordTypes;
    }    
    

    //---------------------------------------------
    // public members
    //
    var that = {

        /** returns current recstructure ID to pass it on selection details popup */
        getRty_ID: function(){
            return rty_ID;
        },
        /* NOT USED - explicit on/off drag mode
        toggleDrag: function () {
        _isDragEnabled = !_isDragEnabled;
        if(_isDragEnabled){
        _fromUItoArray(null); //save all changes
        _myDataTable.collapseAllRows();
        dragDropEnable();
        }else{
        dragDropDisable();
        }
        DDM.refreshCache();
        return _isDragEnabled;
        },*/
        /*DEBUG initTabDesign: function(rty_ID){
        _initTabDesign(rty_ID);
        },*/

        /**
        * Adds new detail types from selection popup or new detail type after its definition
        * @param dty_ID_list - comma separated list of detail type IDs
        */
        addDetails:function(dty_ID_list, index_toinsert, insertAfterRstID, defValues){
            if(insertAfterRstID>0){
                index_toinsert = _getRecordById(insertAfterRstID).row_index+1;
            }
            _addDetails(dty_ID_list, index_toinsert, defValues);
        },

        /**
        * Takes values from edit form to _updateXXX arrays and back to HEURIST db strucure
        *
        * Fills _updatedFields and _updatedDetails with changed value from edit form on expanded row
        * and update local HEURIST db strucure
        *
        * * @param _rst_ID record structure type ID, if null it takes values from all types in recstructure
        * (if order was changes it affects all types)
        */
        doExpliciteCollapse:function(rst_ID, needSave){
            _doExpliciteCollapse(rst_ID, needSave);
        },
        saveUpdates:function(needClose){
            return _saveUpdates(needClose);
        },
        initPreview:function(){
            return _initPreview();
        },
        onAddSeparator: function(index_toinsert){
            _onAddSeparator(index_toinsert);
        },

        onAddFieldAtIndex: function(e, isSectionHeader){
            _onAddFieldAtIndex(e, isSectionHeader);
        },

        onAddFieldMenu: function(e){
            _addFieldMenu(e);
        },
        
        doEditTitleMask: function(is_onexit){
            _doEditTitleMask(is_onexit);
        },
        
        refreshTitleAndIcon:function(){
            _refreshTitleAndIcon();
        },

        closeWarningPopup: function(){
            //top.HEURIST.util.closePopupLast();
            warningPopupID = null;
        },

        closeWin:function(){
            if(_expandedRecord!=null){
                
                _doExpliciteCollapse(null, true, true);
                
                //alert("You have to save or cancel changes for editing field first");
                return;                
            }
            
            function __reviseTitleMask(){ 
                        //wait for inline editor
                        function __waitForInlineEditor(){
                             if(_isServerOperationInProgress){ //wait next 500 seconds
                                setTimeout(__waitForInlineEditor, 300);
                             }else{
                                window.close(editStructure._structureWasUpdated);          
                             }
                        }
                        
                        setTimeout(__waitForInlineEditor, 300);
                }
            
            if(_checkForRequired()){

                _checkForTitleMask(__reviseTitleMask);

            }else{
                //check for parent pointers
                var parent_rtys = __findParentRecordTypes(rty_ID);
                if(parent_rtys.length>0){
                    var $dlg = window.hWin.HEURIST4.msg.showMsgDlg('We recommend that you have at least one REQUIRED field '
+'from this record structure (in addition to any parent record fields), to ensure that the record has a distinctive title.<br><br> '
+'Click [OK] to revise the title (recommended), [Proceed without revision] if you want to proceed without additional fields.',
[{text:'OK', click: function(){$dlg.dialog( "close" );}},
 {text:'Proceed without revision', click: function(){$dlg.dialog( "close" );  _checkForTitleMask(__reviseTitleMask); }} ]        
);
                }else{
                    alert("You should have at least one required field and at least one of them should appear in the title mask to ensure that the constructed title is not blank. \n\nPlease set one or more fields to Required.");
                }
            }
        },
        
        newOptionalFeldsWereAdded: function(){

            var usrPreferences = window.hWin.HAPI4.get_prefs_def('prefs_records', {optfields:true});
            var isfields_on = usrPreferences['optfields']==true || usrPreferences['optfields']=='true';
            
            if(!isfields_on){                
            
                var snames = [];
                var fi_name = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex.rst_DisplayName;
                
                for (var dty_ID in _newOptionalFields){
                    snames.push(window.hWin.HEURIST4.rectypes.typedefs[rty_ID].dtFields[dty_ID][fi_name]);
                }
                if(snames.length>0){
                    var sMsg = 'You\'ve added (or set) optional field'+(snames.length==1
                                    ?' '+snames[0]
                                    :'s '+snames.join(', '))
                                +' but optional fields are not displayed. '
                                +'Turn on optional fields to see the field'
                                +(snames.length==1?'':'s')+' you have added.';
                    window.hWin.HEURIST4.msg.showMsgDlg(sMsg, null, 'Optional fields added');
                }
            }
        },

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            return (strClass === _className);
        }

    };

    _init();  // initialize before returning
    return that;
}

/////////////////////////////////////////////////
//			GENERAL
/////////////////////////////////////////////////

/**
* Invokes popup window to select and add field type from the existing
*/
function onAddNewDetail(index_toinsert){

    if(Hul.isnull(popupSelect))
    {

        editStructure.doExpliciteCollapse(null, true);

        //var pos = this.window.offset();
        // x: pos.left+$(window).width(),
        // y: pos.top,
        var body = $(window.hWin.document).find('body');
        var dim = {h:body.innerHeight(), w:body.innerWidth()};
            
        var sURL = window.hWin.HAPI4.baseURL +
            "admin/structure/fields/selectDetailType.html?rty_ID="+editStructure.getRty_ID()+"&db="+window.hWin.HAPI4.database;
        
        window.hWin.HEURIST4.msg.showDialog(sURL, {    
                "close-on-blur": false,
                "no-resize": false,
                title: 'Add Field', // or Section Header
                height: dim.h*0.9,
                width: 700, //back to fixed width    dim.w*0.5,

                callback: function(detailTypesToBeAdded) {
                    if(!Hul.isnull(detailTypesToBeAdded)){
                        editStructure.addDetails(detailTypesToBeAdded, index_toinsert);
                    }
                    popupSelect = null;
                },
                afterclose: function(){
                    popupSelect = null;
                }
        });

    }
}

/**
* Adds separator field type
*/
function onAddSeparator(index_toinsert){
    editStructure.onAddSeparator(index_toinsert);
}

/**
* Invokes popup window to create and add new field type
*/
function onDefineNewType(index_toinsert){

    if(Hul.isnull(popupSelect))
    {
        editStructure.doExpliciteCollapse(null, true);

        var sURL = window.hWin.HAPI4.baseURL + "admin/structure/fields/editDetailType.html?db="+window.hWin.HAPI4.database;

        window.hWin.HEURIST4.msg.showDialog(sURL, {
            	"close-on-blur": false,
                "no-resize": false,
                title: 'Edit field type',
                height: 700,
                width: 840,
                callback: function(context) {

                    if(!Hul.isnull(context)){
                        //refresh the local heurist
                        window.hWin.HEURIST4.detailtypes = context.detailtypes;

                        //new field type to be added
                        var dty_ID = Math.abs(Number(context.result[0]));
                        editStructure.addDetails(String(dty_ID), index_toinsert);
                    }

                    popupSelect =  null;
                },
                afterclose: function(){
                    popupSelect = null;
                }
        });
    }
}

/**
* Closes the expanded detail recstructure edit form before open popup window
*/
function doExpliciteCollapse(event){
    YAHOO.util.Event.stopEvent(event);
    var btn = event.target;
    var rst_ID = btn.id.substr(btn.id.indexOf("_")+1);
    editStructure.doExpliciteCollapse(rst_ID, btn.id.indexOf("btnSave")===0 );
}
function checkDisplayName(event){
    YAHOO.util.Event.stopEvent(event);
    var btn = event.target;
    var rst_ID = btn.id.substr(btn.id.indexOf("_")+1);
    if(window.hWin.HEURIST4.util.isempty( $('#ed'+rst_ID+'_rst_DisplayName').val().trim())){
        
        window.hWin.HEURIST4.msg.showMsgFlash('Please enter a Prompt (display name)');
        setTimeout(function(){$('#ed'+rst_ID+'_rst_DisplayName').focus();},1100);
        return false;
    }else{
        return true;
    }
}

/**
* Save button listener to save order
*
* @param needClose whether need to close this window after finishing of operation
*/
function onUpdateStructureOnServer(needClose)
{
    editStructure.saveUpdates(needClose);
}

/**
*
*/
function onStatusChange(evt){
    
    var dbId = Number(window.hWin.HAPI4.sysinfo['db_registeredid']);
    
    if(dbId>=1000) return;

    var name, el;

    if(typeof evt === 'number'){
        name = 'ed'+evt;
    }else{
        var el = evt.target;
        name = el.id.substring(0,el.id.indexOf("_")); //. _rst_RequirementType
    }
    
    el = Dom.get(name+"_rst_Status");

    if(el==null) return;    
    
    var curr_value = $(el).attr('data-original');
    var new_value = el.value;
        
    if(curr_value=='reserved' && new_value!=curr_value){ //value was changed - confirm
        el.value = curr_value; //restore
        if(window.hWin.HAPI4.sysinfo['pwd_ReservedChanges']){ //password defined
            //show prompt
            window.hWin.HEURIST4.msg.showPrompt('Enter password: ',
                function(value){
                    
                    window.hWin.HAPI4.SystemMgr.action_password({action:'ReservedChanges', password:value},
                        function(response){
                            if(response.status == window.hWin.ResponseStatus.OK && response.data=='ok'){
                                $(el).attr('data-original', ''); //reset to warining once
                                el.value = new_value;
                            }else{
                                alert('Wrong password');
                            }
                        }
                    );
                    
                },
            'To reset field status from "reserved"');
        }else{
            window.hWin.HEURIST4.msg.showMsgDlg('Reserved field changes is not allowed unless a challenge password is set - please consult system administrator');
        }
    }
    
    
    //2017-02-09 no more restrictions for reserved fields
    /*
    //If reserved, requirements can only be increased, nor can you change min or max values
    var isReserved = (status === "reserved"); // || status === "approved");
    Dom.get(name+"_rst_MinValues").disabled = isReserved;
    Dom.get(name+"_rst_MaxValues").disabled = isReserved;
    var sel = Dom.get(name+"_Repeatability");
    sel.disabled = isReserved;

    sel = Dom.get(name+"_rst_RequirementType");
    sel.disabled = (isReserved && (sel.value==='required'));
    */
}

function onIncrementModeChange(rst_ID){
    
        
    if($('#incValue_'+rst_ID+'_1').is(':checked')){
        $('#ed'+rst_ID+'_rst_DefaultValue').css('background-color','#ECF1FB').attr('disabled',false);
    }else{
        $('#ed'+rst_ID+'_rst_DefaultValue').css('background-color','lightgray').attr('disabled',true).val('');
    }
}

/**
* Listener of requirement type selector (combobox)
*/
function onReqtypeChange(evt){
    var el, name, rst_ID;

    if(typeof evt === 'number'){
        el = Dom.get("ed"+evt+"_rst_RequirementType")
        name = 'ed'+evt;
        rst_ID = evt;
    }else{
        el = evt.target;
        name = el.id.substring(0,el.id.indexOf("_")); //. _rst_RequirementType
        rst_ID = Number(name.substring(2));
    }
    
    //in case of reserved field show warning
    var status = Dom.get(name+"_rst_Status").value;
    if (status === "reserved"){
        
        /*var rty_ID = editStructure.getRty_ID();
        var values = window.hWin.HEURIST4.rectypes.typedefs[rty_ID].dtFields[rst_ID];    
        var curr_value = values[window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex.rst_RequirementType];     */
        var curr_value = $(el).attr('data-original');
        var new_value = el.value;
        if(new_value=='forbidden' || new_value=='hidden'){
            el.value = curr_value; //restore
            return;
        }

        
        if(curr_value=='required' && new_value!=curr_value){ //value was changed - confirm
        
            el.value = curr_value; //restore
            
            //show confirmation dialog
            var $__dlg = window.hWin.HEURIST4.msg.showMsgDlg(
            'This is a reserved field which may be required by a specific function such as Zotero import or mapping. '+
                'Reserved fields can be marked as Recommended or Optional rather than Required, however we recommend thinking ' +
                'carefully about whether the value is required before changing this setting.',
             {'Change' :function(){ 
                    $(el).attr('data-original', new_value); //reset - to show this warning once only
                    el.value = new_value;
                    ___onReqtypeChange_continue();
                    
                    $__dlg.dialog( "close" );
                },
             'Cancel':function(){ 
                    $__dlg.dialog( "close" );
                }},
                {title:'Confirm', width:600});
            
            
        }
    }//approve change of "requirement" for reserved field
    else{
        ___onReqtypeChange_continue();    
    }

    
    function ___onReqtypeChange_continue(){

    var rep_el =  Dom.get(name+'_Repeatability');

    var el_min = Dom.get(name+"_rst_MinValues");
    var el_max = Dom.get(name+"_rst_MaxValues");
    //var status = Dom.get(name+"_rst_Status").value;

    if(el.value === "required"){
        if(Number(el_min.value)===0)
        {
            el_min.value = 1;
        }

        //Dom.setStyle(span_min, "visibility", "visible");
    } else if(el.value === "recommended"){
        el_min.value = 0;

        //Dom.setStyle(span_min, "visibility", "hidden");
    } else if(el.value === "optional"){
        el_min.value = 0;

        //Dom.setStyle(span_min, "visibility", "hidden");
    } else if(el.value === "forbidden" || el.value === "hidden"){
        el_min.value = 0;
        el_max.value = 0;

        var span_max = Dom.get(name+'_spanMaxValue');
        Dom.setStyle(span_max, "visibility", "hidden");
    }

    if (!(el.value == "forbidden" || el.value == "hidden" )){
        //rep_el.disabled = false;
        onRepeatChange(evt);
    }
    
    }  //___onReqtypeChange_continue
}

/**
* Listener of Repeatable type selector (combobox)
*/
function onRepeatChange(evt){

    var el, name;

    if(typeof evt === 'number'){
        el = Dom.get("ed"+evt+"_Repeatability")
        name = 'ed'+evt;
        rst_ID = evt;
    }else{
        el = evt.target;
        name = el.id.substring(0,el.id.indexOf("_")); 
        rst_ID = Number(name.substring(2));
    }
    
    
    //in case of reserved field show warning
    var status = Dom.get(name+"_rst_Status");
    if (status && status.value=== "reserved"){
        
        var curr_value = $(el).attr('data-original');
        var new_value = el.value;
        
        // empty curr_value means that user already changed repeatability
        if(curr_value!='' && new_value!=curr_value){ //value was changed - confirm
        
            el.value = curr_value;
            
            var ele = document.getElementById("change_Req");
            $("#change_Req").css("display","block");
            $("#reqText").text("This is a reserved field which may be required by a specific function such as Zotero import or mapping."
            +" Please think carefully before changing between a single value and repeating value field for a reserved field, "
            +"as this could affect the way that the field");
            
            var $_dialogbox;

            $("#change_Btn").click(function(){
                el.value = new_value;
                $(el).attr('data-original',''); //reset
                ___onRepeatChange_continue();
                $_dialogbox.dialog("close");
            });
            $("#cancel_Btn").click(function(){
                $_dialogbox.dialog("close");
            });
            //show confirmation dialog
            $_dialogbox = Hul.popupElement(top, ele,
                {
                    "close-on-blur": false,
                    "no-resize": true,
                    title: '',
                    height: 140,
                    width: 600
            });
            
            
        }
    }//approve change of "requirement" for reserved field
    else{
        ___onRepeatChange_continue();    
    }

    
    function ___onRepeatChange_continue(){    

    var el =  Dom.get(name+'_rst_RequirementType');
    if(!(el.value === "forbidden" || el.value === "hidden")){

        el =  Dom.get(name+'_Repeatability');
        var span_min = Dom.get(name+'_spanMinValue');
        var span_max = Dom.get(name+'_spanMaxValue');
        var el_min = Dom.get(name+"_rst_MinValues");
        var el_max = Dom.get(name+"_rst_MaxValues");

        Dom.setStyle(span_max, "visibility", "hidden");

        if(el.value === "single"){
            el_max.value = 1;
        } else if(el.value === "repeatable"){
            el_max.value = 0;
        } else if(el.value === "limited"){
            if(el_max.value<2) el_max.value = 2;
            Dom.setStyle(span_max, "visibility", "visible");
        }

    }
    
    }
}

/**
* Max repeat value must be >= then min value
*/
function onRepeatValueChange(evt){
    var el = evt.target;
    var name = el.id.substring(0,el.id.indexOf("_")); //. _rst_RequirementType
    var el_min = Dom.get(name+"_rst_MinValues");
    var el_max = Dom.get(name+"_rst_MaxValues");
    if(el_max.value<el_min.value){
        el_max.value=el_min.value;
    }
}

function onPointerModeChange(evt, rst_ID){
    
    if(evt && evt.target){
        var ele = evt.target;
        value = ele.options[ele.selectedIndex].value;
    }else{
        value = evt;
    }
    if(!value) value = 'addorbrowse';
    
    var ele2 = document.getElementById('ed'+rst_ID+'_rst_PointerBrowseFilter');
    
    if(value=='addonly'){
        ele2.parentNode.style.display = "none";
    }else{
        ele2.parentNode.style.display = "inline-block";
    }
    
    
}

function onCreateChildIfRecPtr(event,rst_ID, rty_ID){
  event.preventDefault();
  
  var ele = event.target;
  var $dlg;
    
  if(!$(ele).is(':checked')){
    //warning on cancel
 $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
'<h3>Turning off child-record function</h3><br>'
+'<div><b>DO NOT DO THIS</b> if you have entered data for child records, unless you fully understand the consequences. It is likely to invalidate the titles of child records, make it hard to retrieve them or to identify what they belong to.</div><br>'
+'<div>If you do accidentally turn this function off, it IS possible to turn it back on again (preferably immediately …) and recover most of the information/functionality.</div><br>'
+'<div><label><input type="checkbox">Yes, I want to turn child-record function OFF for this field</label></div>',
                     function(){
                       $(ele).prop('checked',false);
                     },
                     {title:'Warning',yes:'Proceed',no:'Cancel'});    
      
  }else{
 $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
'<h3>Convert existing records to children</h3><br>'
+'<div>Records referenced by this pointer field will become child records of the record which references them. Once allocated as a child record, the parent cannot be changed. </div><br>'
+'<div>WARNING: It is difficult to undo this step ie. to change a child record pointer field back to a standard pointer field.</div><br>'
+'<div><label><input type="checkbox">Yes, I want to turn child-record function ON for this field</label></div>',
                     function(){
                        
                        window.hWin.HEURIST4.msg.showMsgFlash('converting to child records, may take up to a minute, please wait …', false);
                        window.hWin.HEURIST4.msg.bringCoverallToFront( $(this.document).find('body') );            
                        
                        //start action
                        var request = {
                             a: 'add_reverse_pointer_for_child',
                             rtyID: rty_ID,   //rectype id
                             dtyID: rst_ID,   //field type id 
                             allow_multi_parent:true
                        };
                        
                        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                        
                            window.hWin.HEURIST4.msg.closeMsgFlash();
                            window.hWin.HEURIST4.msg.sendCoverallToBack();
                            
                            if(response.status == hWin.ResponseStatus.OK){
                                //show report

var link = '<a target="blank" href="'+window.hWin.HAPI4.baseURL + '?db='+window.hWin.HAPI4.database+'&q=ids:';
var link2 = '"><img src="'+window.hWin.HAPI4.baseURL+'common/images/external_link_16x16.gif">&nbsp;';
           
function __getlink(arr){
    return link+arr.join(',')+link2+arr.length; 
}           
           
                   
var fi = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex;
var sName = window.hWin.HEURIST4.rectypes.typedefs[rty_ID].dtFields[rst_ID][fi.rst_DisplayName];
                                
sMsg = '<h3>Conversion of records to child records</h3><br><b>Pointer field:'+ sName +'</b><br><br>'
+'<div>'+response.data['passed']+' record pointer values were found for this field</div>'
+(response.data['disambiguation']>0?('<div>'+response.data['disambiguation']+' values ignored. The same records were pointed to as a child record by more than one parent</div>'):'')
+(response.data['noaccess']>0?('<div>'+response.data['noaccess']+' records cannot be converted to child records (no access rights)</div>'):'');

if(response.data['passed']>0)
{
if(response.data['processedParents'] && response.data['processedParents'].length>0){
sMsg = sMsg
+'<br><div>'+__getlink(response.data['processedParents'])+' parent records</a> (records of this type with this pointer field) were processed</div>'
+((response.data['childInserted'].length>0)?('<div>'+__getlink(response.data['childInserted'])+' records</a> were converted to child records</div>'):'')
+((response.data['childUpdated'].length>0)?('<div>'+__getlink(response.data['childUpdated'])+' child records</a> changed its parent</div>'):'')
+((response.data['titlesFailed'].length>0)?('<div>'+__getlink(response.data['titlesFailed'])+' child records</a> failed to update tecord title</div>'):'');
}
if(response.data['childAlready'] && response.data['childAlready'].length>0){
sMsg = sMsg
+'<div>'+__getlink(response.data['childAlready'])+' child records</a> already have the required reverse pointer (OK)</div>';
}

if(response.data['childMiltiplied'] && response.data['childMiltiplied'].length>0){
sMsg = sMsg
+'<div>'+__getlink(response.data['childMiltiplied'])+' records</a> were pointed to as a child record by more than one parent (Problem)</div>'
+'<br><div>You will need to edit these records and choose which record is the parent (child records can only have one parent).</div>'
+'<div>To find these records use Verify > Verify integrity <new tab icon></div><br>'
}
}
//sMsg = sMsg 
sMsg = sMsg 
+'<br>Notes<br><div>We STRONGLY recommend removing - from the record structure of the child record type(s) -  any existing field which points back to the parent record</div>'
+'<br><div>You will also need to update the record title mask to use the new Parent Entity field to provide information (rather than existing fields which point back to the parent)</div>'
+'<br><div>You can do both of these changes through Structure > Modify / Extend <new tab icon> or the Modify structure <new tab icon> link when editing a record.</div>';
                            
                                window.hWin.HEURIST4.msg.showMsgDlg(sMsg);
                                
                                $(ele).prop('checked', true);
                                window.hWin.HEURIST4.rectypes.typedefs[rty_ID].dtFields[rst_ID][fi.rst_CreateChildIfRecPtr] = 1;
                                //save from UI to HEURIST - it is saved on server side in add_reverse_pointer_for_child
                                //editStructure.doExpliciteCollapse(rst_ID, true);
                                
                            }else{
                                $(ele).prop('checked',false);
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        });
                     },
                     {title:'Warning',yes:'Proceed',no:'Cancel'});    
  }
  
  var btn = $dlg.parent().find('button:contains("Proceed")');
  var chb = $dlg.find('input[type="checkbox"]').change(function(){
      window.hWin.HEURIST4.util.setDisabled(btn, !chb.is(':checked') );
  })
  window.hWin.HEURIST4.util.setDisabled(btn, true);
    
  return false;
}

//
// show warning for parent-child function of pointer field
//
function onCreateChildIfRecPtrInfo(evt){
  evt.preventDefault();
    
  window.hWin.HEURIST4.msg.showElementAsDialog({
      element:document.getElementById('info_rst_CreateChildIfRecPtr'),
      title:'Creation of records as children',height:300}
  );
    
  return false;
}


function _preventSel(event){
    event.target.selectedIndex=0;
}
function _setDefTermValue(event){
    /*
    var el = event.target,
    name = el.parentElement.attributes.name.nodeValue; //item('id').nodeValue;

    name = name.substr(3);

    var edt_def = Dom.get('ed'+name);
    edt_def.value =  event.target.value;
    */
}

/**
* recreateTermsPreviewSelector
* creates and fills selector for Terms Tree if datatype is enum, relmarker, relationtype
* @param datatype an datatype
* @allTerms - JSON string with terms
* @disabledTerms  - JSON string with disabled terms
*/
function recreateTermsPreviewSelector(rst_ID, datatype, allTerms, disabledTerms, defvalue ) {

    //allTerms = Hul.expandJsonStructure(allTerms);
    //disabledTerms = Hul.expandJsonStructure(disabledTerms);

    if (disabledTerms && typeof disabledTerms.join === "function") {
        disabledTerms = disabledTerms.join(",");
    }else{
        disabledTerms = '';
    }

        var el_sel;
        /* = Dom.get(_id);
        var parent = el_sel.parentNode;
        parent.removeChild( el_sel );
        */

        var parent1 = document.getElementById("termsPreview"),
        parent2 = document.getElementById("termsDefault_"+rst_ID); //default value

        function __recreate(parent, onchangehandler, bgcolor, _defvalue, isdefselector){
            var i;
            
            $(parent).empty();
                              /*
            for (i = 0; i < parent.children.length; i++) {
                parent.removeChild(parent.childNodes[0]);
            }                   */

            if(!Hul.isnull(allTerms)) {
                //remove old combobox
            
            //el_sel = Hul.createTermSelectExt(allTerms, disabledTerms, datatype, _defvalue, isdefselector);
            el_sel = window.hWin.HEURIST4.ui.createTermSelectExt2(null,
                    {datatype:datatype, termIDTree:allTerms, headerTermIDsList:disabledTerms,
                     defaultTermID:_defvalue, 
                     topOptions:isdefselector?[{key:'',title:'<blank value>'}]:null,
                     needArray:false, useHtmlSelect:true});
            el_sel = el_sel[0];
            
            el_sel.id = isdefselector?('ed'+rst_ID+'_rst_DefaultValue'):'termsPreview_select';
            el_sel.style.backgroundColor = bgcolor;
            //el_sel.onchange =  onchangehandler;
            el_sel.className = "previewList"; //was for enum only?
            el_sel.style.display = 'block';
            if(!isdefselector) el_sel.style.width = '300px';
            parent.appendChild(el_sel);
            
            if($(el_sel).hSelect('instance')!==undefined) $(el_sel).hSelect('destroy');
            var el_menu = window.hWin.HEURIST4.ui.initHSelect(el_sel, false);
            el_menu.hSelect({change: function(event, data){
                
                   var selval = data.item.value;
                   $(el_sel).val(selval);
                   //onchangehandler();
                
            }});
            el_menu.hSelect('menuWidget').css({background:bgcolor, 'max-height':100});
            $('#'+el_sel.id+'-button').css({background:bgcolor});
            
            }
        }//end __recreate

        __recreate(parent1, _preventSel, "#cccccc", null, false);
        if(parent2) __recreate(parent2, _setDefTermValue, "#ffffff", defvalue, true);
    
}

/**
* recreateRecTypesPreview - creates and fills selector for Record(s) pointers if datatype
* is fieldsetmarker, relmarker, resource
*
* @param type an datatype
* @value - comma separated list of rectype IDs
*/
function recreateRecTypesPreview(type, value) {

    var divRecType = Dom.get("pointerPreview");
    var txt = "";
    if(divRecType===null) {
        return;
    }

    if(value) {
        var arr = value.split(","),
        ind, dtName;
        for (ind in arr) {
            dtName = window.hWin.HEURIST4.rectypes.names[arr[ind]];
            if(!txt) {
                txt = dtName;
            }else{
                txt += ", " + dtName;
            }
        } //for
    }else{
        txt = "select...";
    }

    if (txt && txt.length > 40){
        divRecType.title = txt;
        txt = txt.substr(0,40) + "&#8230";
    }else{
        divRecType.title = "";
    }
    divRecType.innerHTML = txt;
    
    
    var rty_ID = Dom.get("ed_rty_ID").value;
    var rst_ID = Dom.get("ed_rst_ID").value;
    var edt_def = Dom.get('ed'+rst_ID+'_rst_DefaultValue');
    
    var fieldType = window.hWin.HEURIST4.detailtypes.typedefs[rst_ID].commonFields[window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex.dty_Type];
    
    edt_def.parentNode.parentNode.style.display = "none";
    
    if(fieldType === "resource") {
    
        var dtID = rst_ID;
        var dtFields = window.hWin.HEURIST4.util.cloneJSON(window.hWin.HEURIST4.rectypes.typedefs[rty_ID].dtFields[rst_ID]);
        var fi = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex;
        dtFields[fi['rst_DisplayName']] = 'Default Value:';
        dtFields[fi['rst_RequirementType']] = 'optional';
        dtFields[fi['rst_MaxValues']] = 1;
        dtFields[fi['rst_DisplayHelpText']] = '';
        dtFields[fi['rst_DisplayWidth']] = 25; //
  
        var ed_options = {
            recID: -1,
            dtID: dtID,
            //rectypeID: rectypeID,
            rectypes: window.hWin.HEURIST4.rectypes,
            values: [edt_def.value],
            readonly: false,

            showclear_button: true,
            //input_width: '350px',
            //detailtype: field['type']  //overwrite detail type from db (for example freetext instead of memo)
            dtFields:dtFields

        };
        
        var ele2 = $('#pointerDefValue'+rst_ID);

        ele2.empty();
        var ele = $('<div>').addClass('def_values').appendTo(ele2);
        ele.editing_input(ed_options); //init
        
        ele.find('.input-div').css('font-size','0.8em');
        ele.find('.editint-inout-repeat-button').css('min-width',0);
        ele.find('.header').css({'width': '190px','text-align': 'right'});
    }
    
}

//
// show dropdown for field suggestions to be added
//
function onFieldAddSuggestion(event, insertAfterRstID){
    
    var input_name = $(event.target);
    
    removeErrorClass(input_name);
    
    var fields_list_div = $('.list_div');
    
    var setdis = input_name.val().length<3;
    window.hWin.HEURIST4.util.setDisabled($('.initially-dis'), setdis );
    window.hWin.HEURIST4.util.setDisabled($('#selVocab'), setdis);

  
    if(input_name.val().length>2){
       
        var rty_ID = editStructure.getRty_ID(); 
        var dty_ID, field_name,
            fi = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex;
            
        fields_list_div.empty();  
        var is_added = false;
        
        //add current value as first
        var first_ele = $('<div class="truncate"><b>'+input_name.val()+' [new]</b></div>').appendTo(fields_list_div)
                        .click( function(event){
                            window.hWin.HEURIST4.util.stopEvent(event);
                            fields_list_div.hide(); 
                            $('#ed_dty_HelpText').focus();
                        });


        //find among fields that are not in current record type
        for (dty_ID in window.hWin.HEURIST4.detailtypes.names){
           if(dty_ID>0){ 
               var td = window.hWin.HEURIST4.detailtypes.typedefs[dty_ID];
               var deftype = td.commonFields;

               var aUsage = window.hWin.HEURIST4.detailtypes.rectypeUsage[dty_ID];
               
               field_name = window.hWin.HEURIST4.detailtypes.names[dty_ID];

               if( deftype[fi.dty_ShowInLists]!="0" && deftype[fi.dty_Type]!='separator'
                    && ( window.hWin.HEURIST4.util.isnull(aUsage) || aUsage.indexOf(rty_ID)<0 ) 
                    && (field_name.toLowerCase().indexOf( input_name.val().toLowerCase() )>=0) )
               {
                   
                   var ele;
                   if(field_name.toLowerCase()==input_name.val().toLowerCase()){
                       ele = first_ele;
                   }else{
                       ele = $('<div class="truncate">').appendTo(fields_list_div);
                   }

                   is_added = true;
                    ele.attr('recid',dty_ID)
                       .text(field_name+' ['+ window.hWin.HEURIST4.detailtypes.lookups[deftype[fi.dty_Type]] +']')
                       .click( function(event){
                            window.hWin.HEURIST4.util.stopEvent(event);

                            var ele = $(event.target).hide();
                            var _dty_ID = ele.attr('recid');
                     
                   
                            if(_dty_ID>0){
                                fields_list_div.hide();
                                input_name.val('').focus();
                                
                                window.hWin.HEURIST4.msg.showMsgFlash('Field is added to record structure');
                                
                                editStructure.doExpliciteCollapse(insertAfterRstID, false);
                                editStructure.addDetails(_dty_ID, null, insertAfterRstID);
                            }
                     });
                    
               }
            }
        }

                if(is_added){
                    fields_list_div.show();    
                    fields_list_div.position({my:'left top', at:'left bottom', of:input_name})
                        //.css({'max-width':(maxw+'px')});
                        .css({'max-width':input_name.width()+60});
                }else{
                    fields_list_div.hide();
                }

  }else{
        fields_list_div.hide();  
  }

}

//
//
//
function onDetTypeChange()
{
    var dty_Type = document.getElementById("ed_dty_Type").value;
    var ele1 = $("#ed_dty_JsonTermIDTree").parents('.input-row');
    var ele1a = $("#termsPreview").parents('.input-row');
    var ele2 = $("#ed_dty_PtrTargetRectypeIDs").parents('.input-row');
    
    if(dty_Type=='enum' || dty_Type=='relmarker'){
        ele1.show();
        ele1a.show();
        $("#ed_dty_JsonTermIDTree").val('');
        _recreateTermsVocabSelector(dty_Type);
    }else{
        ele1.hide();
        ele1a.hide();
        $("#ed_dty_JsonTermIDTree").val('');
    }
    if(dty_Type=='resource' || dty_Type=='relmarker'){
        ele2.show();
        recreateRecTypesPreview(dty_Type, $("#ed_dty_PtrTargetRectypeIDs").val() );
    }else{
        ele2.hide();
        $("#ed_dty_PtrTargetRectypeIDs").val('');
    }
    
}

//
//
//
function _recreateTermsVocabSelector(datatype, toselect)  {

//        var prev = document.getElementById("termsVocab");
//        prev.innerHTML = "";

        if(Hul.isempty(datatype)) return;

        var vocabId = toselect ?toselect: Number(document.getElementById("ed_dty_JsonTermIDTree").value),
        sel_index = -1;
        if(isNaN(vocabId)){
            vocabId = 0;
        }

        var dom = (datatype === "relation" || datatype === "relmarker" || datatype === "relationtype")?"relation":"enum",
        fi_label = window.hWin.HEURIST4.terms.fieldNamesToIndex['trm_Label'];
        var termID,
        termName,
        termTree = window.hWin.HEURIST4.terms.treesByDomain[dom],
        terms = window.hWin.HEURIST4.terms.termsByDomainLookup[dom];
                            
        var el_sel = $('#selVocab').get(0);
        $(el_sel).empty();

        window.hWin.HEURIST4.ui.addoption(el_sel, -1, 'select...');
        
        //add to temp array
        var vocabs = [];
        for(termID in termTree) { // For every term in first levet of tree
            if(!Hul.isnull(termID)){
                termName = terms[termID][fi_label];
                vocabs.push({key:termID, title:termName });
            }
        }
        //sort array 
        vocabs.sort(function(a,b){ return a.title<b.title?-1:1; });

        for(var idx in vocabs) { // For every term in first levet of tree
            if(vocabs[idx]){
                window.hWin.HEURIST4.ui.addoption(el_sel, vocabs[idx].key, vocabs[idx].title);
                if(Number(vocabs[idx].key)==vocabId){
                    sel_index = el_sel.length-1;
                }
            }
        }

        if(sel_index<0) {
            sel_index = (document.getElementById("ed_dty_JsonTermIDTree").value!='' && vocabId==0)?el_sel.length-1:0;
        }
        el_sel.selectedIndex = sel_index;

        el_sel.onchange =  function(){
            document.getElementById("ed_dty_JsonTermIDTree").value =  el_sel.value;
        };
        el_sel.style.maxWidth = '300px';
       
        if($(el_sel).hSelect('instance')!==undefined) $(el_sel).hSelect('destroy');
        var el_menu = window.hWin.HEURIST4.ui.initHSelect(el_sel, false);
            el_menu.hSelect({change: function(event, data){
                   var selval = data.item.value;
                   document.getElementById("ed_dty_JsonTermIDTree").value =  selval;
                   recreateTermsPreviewSelector('', datatype,
                            selval,
                            '', null);
                   
            }});
        el_menu.hSelect('menuWidget').css('max-height',100);
        el_menu.hSelect("refresh"); 

        recreateTermsPreviewSelector('', datatype,
                            '',
                            '', null);
        
}                                          

//
//
//
/**
* onSelectRectype
*
* listener of "Select Record Type" buttons
* Shows a popup window where you can select record types
*/
function onSelectRectype()
{

        var type = document.getElementById("ed_dty_Type").value;
        if(type === "relmarker" || type === "resource")
        {

            var args, sURL;

            if(document.getElementById("ed_dty_PtrTargetRectypeIDs")) {
                    args = document.getElementById("ed_dty_PtrTargetRectypeIDs").value;
            }

            if(args) {
                sURL =  window.hWin.HAPI4.baseURL + "admin/structure/rectypes/selectRectype.html?type=" 
                    + type + "&ids=" + args+"&db="+window.hWin.HAPI4.database;
            } else {
                sURL =  window.hWin.HAPI4.baseURL + "admin/structure/rectypes/selectRectype.html?type=" 
                + type+"&db="+window.hWin.HAPI4.database;
            }

            window.hWin.HEURIST4.msg.showDialog(sURL, {
                "close-on-blur": false,
                "no-resize": true,
                title: 'Select Record Type',
                height: 600,
                width: 640,
                callback: function(recordTypesSelected) {
                    if(!Hul.isnull(recordTypesSelected)) {
                        document.getElementById("ed_dty_PtrTargetRectypeIDs").value = recordTypesSelected;
                        recreateRecTypesPreview(type, recordTypesSelected );
                    }
                }
            });
        }
}


function removeErrorClass( ele ){
    $(ele).removeClass('ui-state-error');
}
//
//  create new field type and to this record type
//
function onCreateFieldTypeAndAdd( insertAfterRstID ){

        _updatedFields = [];
        _updatedDetails = [];

        var i;
        var fnames = window.hWin.HEURIST4.detailtypes.typedefs.commonFieldNames;

        //take only changed values
        for (i = 0, l = fnames.length; i < l; i++){
            var fname = fnames[i];
            el = $('#ed_'+fname);
      
            if( el.length>0 ){
        
                if(el.val()!=='')
                {
                    _updatedFields.push(fname);
                    _updatedDetails.push(el.val());
                }
            }
        }
        
        
        // check mandatory fields
        if (!(window.hWin.HEURIST4.msg.checkLength($('#ed_dty_HelpText'), 'Help text', null, 1, 0) && 
            window.hWin.HEURIST4.msg.checkLength($('#ed_dty_Type'), 'Data Type', null, 1, 0) &&
            window.hWin.HEURIST4.msg.checkLength($('#ed_dty_Name'), 'Field name', null, 1, 0))) return;
        
        var swarn = "";
        var dt_name = document.getElementById("ed_dty_Name").value.toLowerCase().trim();
        swarn = window.hWin.HEURIST4.ui.validateName(dt_name, "Field 'Name'", 255);
        //check that already exists
        if(swarn==""){
            for (var dty_ID in window.hWin.HEURIST4.detailtypes.names){
                if(dty_ID>0 && window.hWin.HEURIST4.detailtypes.names[dty_ID].toLowerCase()==dt_name){
                       swarn = "Field name '"+window.hWin.HEURIST4.detailtypes.names[dty_ID]
                            +"' aready exists in this database";
                }
            }
        }
        if(swarn!=""){
            window.hWin.HEURIST4.msg.showMsgFlash( swarn );
            $("#ed_dty_Name").addClass( "ui-state-error" ).focus();
            return;
        }
        
        var dty_Type = document.getElementById("ed_dty_Type").value;
        if(dty_Type==="enum"){
            var dd = document.getElementById("ed_dty_JsonTermIDTree").value;
            if( dd==="" || dd==="{}" ) {
                swarn = 'Please select or add a vocabulary. Vocabularies must contain at least one term.';
            }
        }else if(dty_Type==="relmarker"){
            
            var dd = document.getElementById("ed_dty_JsonTermIDTree").value;
            if( dd==="" || dd==="{}" ) {
                swarn = 'Please select or add relationship types';
            }else{
                var dd = document.getElementById("ed_dty_PtrTargetRectypeIDs").value;
                if( dd==="" ) {
                    swarn = 'Please select target record type. Unconstrained relationship is not allowed';
                }
            }
        }else if(dty_Type==="resource"){
            
            var dd = document.getElementById("ed_dty_PtrTargetRectypeIDs").value;
            if( dd==="" ) {
                swarn = 'Please select target record type(s) for this entity pointer field'
                    +'<br><br>We strongly recommend NOT creating an unconstrained entity pointer';
            }
        }        
        
        if(swarn!=""){
            window.hWin.HEURIST4.msg.showMsgFlash( swarn );
            return;
        }
        
        //keep reqtype and min max values (repeat)        
        var defValues = {rst_RequirementType:Dom.get("ed0_rst_RequirementType").value,
                          rst_MinValues:Dom.get("ed0_rst_MaxValues").value,
                          rst_MaxValues:Dom.get("ed0_rst_MaxValues").value};

        _updatedFields.push('dty_ID');
        _updatedFields.push('dty_Status');
        _updatedFields.push('dty_NonOwnerVisibility');
        _updatedFields.push('dty_ShowInLists');
        _updatedFields.push('dty_DetailTypeGroupID');
        _updatedDetails.push('');
        _updatedDetails.push('open');
        _updatedDetails.push('viewable');
        _updatedDetails.push(1);
        _updatedDetails.push( Number(window.hWin.HEURIST4.detailtypes.groups[0].id) );//add to first group
        
//console.log(_updatedFields);  
//console.log(_updatedDetails);
        //save new field type
            var k,
            val;
            var oDetailType = {detailtype:{
                colNames:{common:[]},
                defs: {}
            }};
            var values = [];
            for(k = 0; k < _updatedFields.length; k++) {
                oDetailType.detailtype.colNames.common.push(_updatedFields[k]);
                values.push(_updatedDetails[k]);
            }

            oDetailType.detailtype.defs[-1] = {};
            oDetailType.detailtype.defs[-1].common = [];
            for(val in values) {
                oDetailType.detailtype.defs[-1].common.push(values[val]);
            }

            window.hWin.HEURIST4.msg.bringCoverallToFront( $(this.document).find('body') );            
            
            // 3. sends data to server
            var baseurl = window.hWin.HAPI4.baseURL + "admin/structure/saveStructure.php";
            
            var request = {method:'saveDT', db:window.hWin.HAPI4.database, data:oDetailType};
            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, function(response){
                
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                
                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    var context = response.data;
                    var error = false;
                    var _dtyID = -1;

                    for(ind in context.result){
                        if( !Hul.isnull(ind) ){
                            var item = context.result[ind];
                            if(isNaN(item)){
                                window.hWin.HEURIST4.msg.showMsgErr(item);
                                error = true;
                            }else{
                                _dtyID = Math.abs(Number(item));
                            }
                        }
                    }
                    if(!error && _dtyID>0){
                        //collapse
                        editStructure.doExpliciteCollapse(insertAfterRstID, false);
                    
                        window.hWin.HEURIST4.msg.showMsgFlash('New field created');
                        window.hWin.HEURIST4.detailtypes = context.detailtypes;
                        editStructure.addDetails(''+_dtyID, null, insertAfterRstID, defValues);
                    }
                }
            });
}

//
//
//
function _onDispNameChange(event){
    var name = event.target.value;
    var swarn = window.hWin.HEURIST4.ui.validateName(name, "Prompt (display name)", 255);
    if(swarn){
        alert(swarn);
    }
}

function onAddVocabulary(){
    var is_add_vocab = true;
    
        var type = document.getElementById("ed_dty_Type").value;
        var dt_name = document.getElementById("ed_dty_Name").value;
        var allTerms = document.getElementById("ed_dty_JsonTermIDTree").value;
        var disTerms = '';

        if(type!="enum"){
            type="relation";
        }

        var el_sel = document.getElementById("selVocab");
        
        var vocab_id =  el_sel.value>0?el_sel.value:''; //keep value
        var is_frist_time = true;

        var sURL = window.hWin.HAPI4.baseURL +
                "admin/structure/terms/editTermForm.php?treetype="+type+"&parent="+(is_add_vocab?0:el_sel.value)
                +"&db="+window.hWin.HAPI4.database;
        window.hWin.HEURIST4.msg.showDialog(sURL, {

                    "close-on-blur": false,
                    "no-resize": true,
                    noClose: true, //hide close button
                    title: 'Edit Vocabulary',
                    height: 340,
                    width: 700,
                    onpopupload:function(dosframe){
                        var ele = $(dosframe.contentDocument).find('#trmName');
                        if(is_add_vocab && is_frist_time){
                           is_frist_time = false;
                           if( !window.hWin.HEURIST4.util.isempty(dt_name)){
                                ele.val( dt_name+' vocab' );    
                           }
                        }
                        ele.focus();
                    },
                    callback: function(context) {
                        if(context!="") {

                            if(context=="ok"){    //after edit term tree
                                _recreateTermsVocabSelector(type, vocab_id);
                                
                            }else if(!Hul.isempty(context)) { //after add new vocab
                                document.getElementById("ed_dty_JsonTermIDTree").value =  context;
                                document.getElementById("ed_dty_TermIDTreeNonSelectableIDs").value = "";
                                
                                _recreateTermsVocabSelector(type, context);
                            }
                        }
                    }
            });
    
}

//
//   dty_ID (=rst_ID) - field type ID - edit base field definition
//
function _onAddEditFieldType(dty_ID, rty_ID){

    //show warning first
    
    //find all record types that reference this type
    var aUsage = window.hWin.HEURIST4.detailtypes.rectypeUsage[dty_ID];
    if(!Hul.isnull(aUsage)){

        textTip = '';
        var k;
        for (k in aUsage) {
            if(rty_ID!=aUsage[k]){
                //textTip = textTip + '<li>'+aUsage[k]+'  '+window.hWin.HEURIST4.rectypes.names[aUsage[k]]+'</li>';
                textTip = textTip + aUsage[k]+'  '+window.hWin.HEURIST4.rectypes.names[aUsage[k]]+'\n';
            }
        }
        if(textTip!=''){
        
            var detname = window.hWin.HEURIST4.detailtypes.names[dty_ID];
            if(detname.length>40) { detname = detname.substring(0,40)+"..."; }

            textTip = //'<b>Warning: Use with care</b><br>'
                'Changes made to the base field type "'+detname
                +'" (changes to vocabulary or list of terms, changes of pointer target type) '
                +'affect ALL record types which use the base field type:\n\n'
                +textTip+'\n\nWarning: Use with care. Proceed?';
                
           if(!confirm(textTip)) return;
                
                
        }
    }    
    
    
    var sURL = window.hWin.HAPI4.baseURL + "admin/structure/fields/editDetailType.html?db="
        +window.hWin.HAPI4.database+ "&detailTypeID="+dty_ID; //existing

    window.hWin.HEURIST4.msg.showDialog(sURL, {
           "close-on-blur": false,
            "no-resize": false,
            height: 680,
            width: 840,
            callback: function(context) {
                if(!Hul.isnull(context)){

                    //update id
                    var dty_ID = Math.abs(Number(context.result[0]));

                    /*if user changes group in popup need update both  old and new group tabs
                    var grpID_old = -1;
                    if(Number(context.result[0])>0){
                    grpID_old = window.hWin.HEURIST4.detailtypes.typedefs[dty_ID].commonFields[7];
                    }*/

                    //refresh the local heurist
                    if(context.rectypes) window.hWin.HEURIST4.rectypes = context.rectypes;
                    window.hWin.HEURIST4.detailtypes = context.detailtypes;
                    _cloneHEU = null;
                    
                    editStructure._structureWasUpdated = true; //no access  to top object
                    
                    var fi = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex;

                    var rst_type = window.hWin.HEURIST4.detailtypes.typedefs[dty_ID].commonFields[fi.dty_Type];
                    //update
                    if(rst_type === "enum" || rst_type === "relmarker" || rst_type === "relationtype"){
                        recreateTermsPreviewSelector(dty_ID, rst_type,
                            window.hWin.HEURIST4.detailtypes.typedefs[dty_ID].commonFields[fi.dty_JsonTermIDTree],
                            window.hWin.HEURIST4.detailtypes.typedefs[dty_ID].commonFields[fi.dty_TermIDTreeNonSelectableIDs], null);
                    }
                    if(rst_type === "relmarker" || rst_type === "resource"){
                        recreateRecTypesPreview(dty_ID,
                            window.hWin.HEURIST4.detailtypes.typedefs[dty_ID].commonFields[fi.dty_PtrTargetRectypeIDs]); //rst_type, null);
                    }

                    /*detect what group
                    var grpID = window.hWin.HEURIST4.detailtypes.typedefs[dty_ID].commonFields[7];

                    _removeTable(grpID, true);
                    if(grpID_old!==grpID){
                    _removeTable(grpID_old, true);
                    }*/
                }
            },
            afterclose: function(){
                popupSelect = null;
            }
            
    });
}


function onEditRecordType(){

        var sURL = window.hWin.HAPI4.baseURL 
                    + "admin/structure/rectypes/editRectype.html?supress=1&db="
                    + window.hWin.HAPI4.database +"&rectypeID="+editStructure.getRty_ID();

        var body = $(window.hWin.document).find('body');
        var dim = {h:body.innerHeight(), w:body.innerWidth()};
        
    window.hWin.HEURIST4.msg.showDialog(sURL, {
                "close-on-blur": false,
                "no-resize": false,
                title:'Edit Record Type',
                height: dim.h*0.9,
                width: 800,
                    
            callback: function(context) {

                if(!Hul.isnull(context) && context.result){
                     //refresh the local heurist
                    window.hWin.HEURIST4.rectypes = context.rectypes;
                }
                //refresh icon, title, mask
                editStructure.refreshTitleAndIcon();
            },
            afterclose: function(){
                popupSelect = null;
            }
        });
        
        return false;
}

function showWarningAboutOptionalFields(){
    if(editStructure && editStructure.newOptionalFeldsWereAdded() ){
        
    }
}