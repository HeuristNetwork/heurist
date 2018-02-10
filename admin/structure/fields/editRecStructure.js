/*
* Copyright (C) 2005-2016 University of Sydney
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
* @copyright   (C) 2005-2016 University of Sydney
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
Hul = top.HEURIST.util;


top.HEURIST.database.id = 1; //temp

function EditRecStructure() {

    var _className = "EditRecStructure",
    _myDataTable,
     _actionInProgress = false,
    rty_ID,
    _isDragEnabled = false,
    _updatedDetails = [], //list of dty_ID that were affected with edition
    _updatedFields = [],  //list of indexes in fieldname array that were affected
    _expandedRecord = null, //rstID of record (row) in design datatable that is (was) expanded (edited)
    _isServerOperationInProgress = false, //prevents send request if there is not respoce from previous one
    _isReserved = false,
    _rty_Status,
    myDTDrags = {},
    _fieldMenu = null,
    db,
    warningPopupID = null,
    _structureWasUpdated = false;

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
        
        if(!top.HEURIST.rectypes && window.hWin && window.hWin.HEURIST4){
            top.HEURIST.rectypes = window.hWin.HEURIST4.rectypes;
            top.HEURIST.detailTypes = window.hWin.HEURIST4.detailtypes;
            top.HEURIST.terms = window.hWin.HEURIST4.terms;
        }

        db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
            (top.HEURIST.database.name?top.HEURIST.database.name:''));

        // read GET parameters
        if (location.search.length > 1) {
            window.HEURIST.parameters = top.HEURIST.parseParams(location.search);
            rty_ID = window.HEURIST.parameters.rty_ID;
        }
        _refreshTitleAndIcon();
        
        Dom.get("modelTabs").innerHTML = '<div id="tabDesign"><div id="tableContainer"></div></div>';
        _initTabDesign(null);
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
            var typedef = top.HEURIST.rectypes.typedefs[rty_ID];
            var fi = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex;

            if(Hul.isnull(typedef)){
                alert("Internal error: Record type ID "+rty_ID+" does not exist. Please report as bug.");
                rty_ID = null;
                return;
            }


            _rty_Status = typedef[ top.HEURIST.rectypes.typedefs.commonNamesToIndex.rty_Status];
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

                    var fieldType = top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[top.HEURIST.detailTypes.typedefs.fieldNamesToIndex.dty_Type];
                    var conceptCode = top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[top.HEURIST.detailTypes.typedefs.fieldNamesToIndex.dty_ConceptID];
                    
                    //get rid of garbage help text
                    if (aval[fi.rst_DisplayHelpText]!='' &&
                       (aval[fi.rst_DisplayHelpText]=='Please rename to an appropriate heading within each record structure' || 
                        aval[fi.rst_DisplayHelpText].indexOf('Please document the nature of this detail type')==0 ||
                        aval[fi.rst_DisplayHelpText]=='Another separator field' ||
                        aval[fi.rst_DisplayHelpText]=='Headings serve to break the data entry form up into sections')){
                            
                        aval[fi.rst_DisplayHelpText]='';
                    }
                    

                    arr.push([ rst_ID,
                        rst_ID,
                        rst_ID,
                        Number(aval[fi.rst_DisplayOrder]),
                        top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[top.HEURIST.detailTypes.typedefs.fieldNamesToIndex.dty_Name], //field name
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
                        aval[fi.rst_DisplayHeight]]);
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
                        "rst_CreateChildIfRecPtr", "rst_DisplayHeight"]
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


            var myColumnDefs = [
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
                    key:"rst_ID", label: "Hdr", sortable:false, width:20,
                    formatter: function(elLiner, oRecord, oColumn, oData){
                        
                        elLiner.innerHTML = "<img src='../../../common/images/insert_header.png' style='cursor:pointer;' "
                        +" title='Click this button to insert a new section header at this point in the record structure / data entry form' "+
                        " rst_ID='"+oRecord.getData("rst_ID")+"' onclick='{editStructure.onAddFieldAtIndex(event, true);}' >";
                        
                        //elLiner.innerHTML = oData;
                        //elLiner.title = oRecord.getData("conceptCode");
                    }
                },
                {
                    key:'addColumn',
                    label: "Add",
                    sortable:false, width:20,
                    formatter: function(elLiner, oRecord, oColumn, oData) {
                        elLiner.innerHTML = "<img src='../../../common/images/insert_field.png' style='cursor:pointer;' "
                        +" title='Click this button to insert a new field at this point in the record structure / data entry form' "+
                        " rst_ID='"+oRecord.getData("rst_ID")+"' onclick='{editStructure.onAddFieldAtIndex(event, false);}' >";
                        //editStructure.onAddFieldMenu(event);
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
                    key:"rst_DisplayName", label: "Field prompt in form", width:120, sortable:false,
                    
                    editor: new YAHOO.widget.TextboxCellEditor({ 
                            disableBtns:true,
                            asyncSubmitter:function(fnCallback, oNewValue){
                                var rec = this.getRecord();
                                _updateSingleField(rec.getData("rst_ID"), 'rst_DisplayName', 
                                            rec.getData("rst_DisplayName"), oNewValue, fnCallback); //fnCallback(bSuccess, oNewValue)   
                            } 
                                }),
                            
                    formatter: function(elLiner, oRecord, oColumn, oData) {
                        elLiner.innerHTML = oData;

                        var ccode = oRecord.getData("conceptCode");
                        elLiner.title = 
                        "ID: "+oRecord.getData("rst_ID")+
                        (ccode?("\n\nConcept code: "+ccode):'')+
                        "\n\nBase field type: "+oRecord.getData("dty_Name")+"\n\n"+
                        "Help: "+oRecord.getData("rst_DisplayHelpText")+"\n\n"+
                        "For non owner: " + oRecord.getData("rst_NonOwnerVisibility");
                        
                        var type = oRecord.getData("dty_Type");
                        if(type=='separator'){
                            $(elLiner).css({'font-size':'1.2em'});//,'font-weight':'bold','cursor':'normal'});
                        }
                        $(elLiner).css({'font-weight':'bold'})
                                .addClass('yui-dt-liner-editable');   
                        

                    }
                },
                { key: "dty_Type", label: "Data Type", sortable:false, width:90,
                    formatter: function(elLiner, oRecord, oColumn, oData) {
                        var type = oRecord.getData("dty_Type");
                        if(type!=='separator'){
                            elLiner.innerHTML = top.HEURIST.detailTypes.lookups[type];
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
                    key:"rst_DisplayWidth", label: "Width", sortable:false, width:25, className:"center" ,
                    
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
                    minWidth:80, maxAutoWidth:80, width:80, className:'left',
                    
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
                        if(_hidevalueforseparator(elLiner, oRecord, oColumn, oData)){
                            $(elLiner).addClass('yui-dt-liner-editable2');
                            if(oRecord.getData("rst_RequirementType")=='forbidden') $(elLiner).text('hidden');
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
                    minWidth:80, maxAutoWidth:80, width:80, className:'left',
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
                    key:"rst_DefaultValue", label: "Default", sortable:false,className:"center",
                    formatter: function(elLiner, oRecord, oColumn, oData){
                        var reqtype = oRecord.getData('rst_DefaultValue');
                        if (reqtype && reqtype.length > 0){
                            var type = oRecord.getData("dty_Type");
                            if( type=='enum' ){
                                var term = top.HEURIST.terms.termsByDomainLookup['enum'][reqtype];
                                if(term){
                                    reqtype = term[0].substring(0,15);
                                }
                            }else{
                                reqtype = reqtype.substring(0,9);// Artem, why do we do this??
                            }

                            elLiner.innerHTML = reqtype;
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
                            statusLock  ='<a href="#delete"><img src="../../../common/images/cross.png" width="12" height="12" border="0" '+
                            'title="Click to remove field from this record type" /><\/a>';
                        }else{
                            statusLock = '<a href="#delete"><img src="../../../common/images/cross.png" width="12" height="12" border="0" '+
                            'title="Click to remove field from this record type" /><\/a>';
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
                var val1 = oRecord.getData('rst_NonOwnerVisibility');
                var val2 = oRecord.getData('rst_RequirementType');
                if (val1==='hidden' || val2==='forbidden') {
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
                //this is box of expandable record
                {	sortedBy:{key:'rst_DisplayOrder', dir:YAHOO.widget.DataTable.CLASS_ASC},
                    formatRow: myRowFormatter,
                    rowExpansionTemplate :
                    function ( obj ) {
                        var rst_ID = obj.data.getData('rst_ID');
                        //var rst_values = obj.data.getData('rst_values');
                        var ccode = obj.data.getData("conceptCode");
                        
                        var fieldType = top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[top.HEURIST.detailTypes.typedefs.fieldNamesToIndex.dty_Type];
                        var allowEditBaseFieldType = (fieldType=='enum' || fieldType=='resource' || fieldType=='relmarker' || fieldType=='relationtype');
                        var allowIncrement = (fieldType=='freetext') || (fieldType=='integer') || (fieldType=='float');
                        
                        var incrementTip = (fieldType=='freetext')
                                    ?'The default value supplied for new records will be the last value entered for the field with any integer at the end of that value incremented by 1, or the last value entered with 1 appended if the last character is not a numeral'
                                    :'The default value supplied for new records will be the largest numeric value for this field + 1';
                        
                        /*
                        THIS IS FORM to edit detail structure. It is located on expandable row of table
                        */
                        obj.liner_element.innerHTML =
                        '<div style="padding-left:30; padding-bottom:5; padding-right:5; background-color:#EEE">'+

                        // Name / prompt
                        '<div class="input-row"><div class="input-header-cell">Prompt (display name):</div>'+
                            '<div class="input-cell">'+
                                '<input id="ed'+rst_ID+'_rst_DisplayName" style="width:200px;" onchange="_onDispNameChange(event)" '+
                                    'title="The name of the field, displayed next to the field in data entry and used to identify the field in report formats, analyses and so forth"/>'+
                                '<span>'+    
                                // Field width
                                '<span><label style="min-width:65px;width:65px">Field width:</label>'+
                                '<input id="ed'+rst_ID+'_rst_DisplayWidth" '+
                                    'title="Display width of this field in the data entry form (does not limit maximum data length)" style="width:40" size="4" onkeypress="Hul.validate(event)"/>'+
                                '</span>'+
                                '<span id="span'+rst_ID+'_rst_DisplayHeight"><label style="min-width:35px;width:35px">&nbsp;Height:</label>'+
                                '<input id="ed'+rst_ID+'_rst_DisplayHeight" '+
                                    'title="Display height in rows of this field in the data entry form" style="width:40" size="4" onkeypress="Hul.validate(event)"/>'+
                                '</span>'+
                                '</span>'+
                            '</div></div>'+


                        // Help text
                        '<div class="input-row">'+
                        '<div class="input-header-cell" style="vertical-align:top">Help text:</div>'+
                        '<div class="input-cell">'+
                        '<textarea style="width:450px" cols="450" rows="3" id="ed'+rst_ID+'_rst_DisplayHelpText" '+
                        'title="Help text displayed underneath the data entry field when help is ON"></textarea>'+
                        '<div class="prompt">Please edit the heading and this text to values appropriate to this record type. '+
                                            'This text is optional.</div>'+
                        '</div></div>'+

                        '<div class="input-row">'+
                        '<div class="input-header-cell">Hidden:</div>'+
                        '<div class="input-cell">'+
                        '<input id="ed'+rst_ID+'_rst_RequirementType_separator" type="checkbox" value="forbidden" />'+
                        '</div></div>'+
                        
                        // Required/recommended optional
                        '<div class="input-row"><div class="input-header-cell" style="vertical-align:top;">Requirement:</div>'+
                        '<div class="input-cell" title="Determines whether the field must be filled in, should generally be filled in, or is optional">'+
                        '<select id="ed'+rst_ID+'_rst_RequirementType" onchange="onReqtypeChange(event)" style="display:inline; margin-right:0px;vertical-align: top;">'+
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
                        ?'<span style="padding-left:50px">'+
                        '<input type="radio" id="incValue_'+rst_ID+'_1" name="incValue_'+rst_ID+'" value="0" checked onchange="onIncrementModeChange('+rst_ID+')">'+
                        '<label style="min-width: 60px;width: 60px;" for="incValue_'+rst_ID+'_1">Default&nbsp;Value:</label>'+
                        '<div id="termsDefault_'+rst_ID+'" style="display:inline-block;padding-right:1em"><input id="ed'+rst_ID+'_rst_DefaultValue" title="Select or enter the default value to be inserted automatically into new records"/></div>'+
                        '<input type="radio" id="incValue_'+rst_ID+'_2" name="incValue_'+rst_ID+'" value="1"  title="'+incrementTip+'" onchange="onIncrementModeChange('+rst_ID+')">'+
                        '<label  style="min-width: 120px;width: 120px;" for="incValue_'+rst_ID+'_2" title="'+incrementTip+'">Increament value by 1</label>'+
                        '</span>'
                        :'<div style="padding-left:50px;display:inline-block">'+
                        '<label class="input-header-cell" for="ed'+rst_ID+'_rst_DefaultValue">Default&nbsp;Value:</label>'+
                        '<div id="termsDefault_'+rst_ID+'" style="display:inline-block;"><input id="ed'+rst_ID+'_rst_DefaultValue" title="Select or enter the default value to be inserted automatically into new records"/>'+
                        ((fieldType=='date')?'<br><span class="prompt">yesterday, today, tomorrow, now, specific date</span>':'')+
                        '</div>'+
                        '</div>')+

                        // Minimum values
                        '<span id="ed'+rst_ID+'_spanMinValue" style="display:none;"><label class="input-header-cell">Minimum&nbsp;values:</label>'+
                        '<input id="ed'+rst_ID+
                        '_rst_MinValues" title="Minimum number of values which are required in data entry" style="width:20px" size="2" '+
                        'onblur="onRepeatValueChange(event)" onkeypress="Hul.validate(event)"/></span>'+
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
                        'onblur="onRepeatValueChange(event)" onkeypress="Hul.validate(event)"/></span>'+
                        '</div>'+
                        '</div>'+

                        // Terms - enums and relmarkers
                        '<div class="input-row"><div class="input-header-cell">Terms list:</div>'+
                        '<div class="input-cell" title="The lsit of terms available for selection as values for this field">'+
                        '<input id="ed'+rst_ID+'_rst_FilteredJsonTermIDTree" type="hidden"/>'+
                        '<input id="ed'+rst_ID+'_rst_TermIDTreeNonSelectableIDs" type="hidden"/>'+
                        '<span class="input-cell" id="termsPreview" class="dtyValue"></span>'+
                        '<span class="input-cell" style="margin:0 10px">&nbsp;&nbsp;to change click "Edit Base Field Definition"</span>'+
                        '</div></div>'+

                        // Pointer target types - pointers and relmarkers
                        '<div class="input-row"><div class="input-header-cell">Can point to:</div>'+
                        '<div id="pointerPreview" class="input-cell" title="Determines which record types this pointer field can point to. It is preferable to select target record types than to leave the pointer unconstrained">'+
                        '<input id="ed'+rst_ID+'_rst_PtrFilteredIDs" type="hidden"/>'+
                        // TODO: the following message is not showing, whereas the one above does
                        '<span class="input-cell" style="margin:0 10px">&nbsp;&nbsp;to change click "Edit Base Field Definition"</span>'+ // and then "Select Record Types"
                        '</div></div>'+

                        '<div class="input-row"><div class="input-header-cell">Create new records as children:</div>'+
                        
                        '<div class="input-cell">'+
                        '<input id="ed'+rst_ID+'_rst_CreateChildIfRecPtr" type="checkbox" value="1"  onclick="onCreateChildIfRecPtr(event,'+rst_ID+','+rty_ID+')"/>'+
                        
                        '<a href="#" class="help_rst_CreateChildIfRecPtr" onclick="onCreateChildIfRecPtrInfo(event)" style="vertical-align:bottom">'+
                            '<img src="../../../common/images/info.png" width="14" height="14" border="0" title="Info"></a>'+
                        '<span class="help prompt">New records created via this field become child records. Click "i" for further information.</span>'+    
                            
                        '</div></div>'+
                        
                        
                        // Base field definitions  (button)
                        '<div>'+
                        '<div style="width:90%;text-align:right;padding:5px 20px 15px 0">'+ //margin-left:190px; 
                        
                        (allowEditBaseFieldType?
                        '<input style="margin-right:100px;" id="btnEdit_'+rst_ID+'" type="button" value="Edit Base Field Definition" '+
                        'title="Allows modification of the underlying field definition (shared by all record types that use this base field)"'+
                        ' onclick="_onAddEditFieldType('+rst_ID+','+rty_ID+');">':'<span style="margin-right:220px;">&nbsp;</span>')+

                        // Save and cancel (buttons)
                        '<input style="margin-right:20px;" id="btnSave_'+rst_ID+'" type="button" value="Save"'+
                        'title="Save any changes to the field settings. You may also simply click on another field to save this one and open the other" onclick="doExpliciteCollapse(event);"  style="margin:0 2px;"/>'+
                        '<input id="btnCancel_'+rst_ID+'" type="button" value="Cancel" '+
                        'title="Cancel any changes made to the field settings for this field (will not cancel previously saved settings)" onclick="doExpliciteCollapse(event);" style="margin:0 2px;"/>'+
                        '</div>'+

                        '<div id="divMoreDefs'+rst_ID+'" class="togglepnl"><a style="margin-left: 40px;" onMouseDown="'+
                        "$('#options').slideToggle('fast'); $('#divMoreDefs"+rst_ID+"').toggleClass('show'); $('#options').toggleClass('hidden');"+
                        '">more ...</a>'+

                        '<div id="options" class="hidden" style="background-color:#EEE;">'+

                        // Status
                        '<div class="input-row"><div class="input-header-cell">Status:</div>'+
                        '<div class="input-cell" title="Determines the degree of authority assigned to this field - reserved is used for internal Heurist definitions, open is the lowest level"><select id="ed'+rst_ID+
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
                        '<option value="pending">pending</option></select></span>'+
                        
                        '</div></div>'+

                        (ccode?'<div class="input-row"><div class="input-header-cell">Concept code:</div><div style="display:table-cell">'+ccode+'</div></div>':'')+
                        
                        '</div></div>'

                    }
                }
            );

            // highlight listeners
            _myDataTable.subscribe("rowMouseoverEvent", _myDataTable.onEventHighlightRow);
            _myDataTable.subscribe("rowMouseoutEvent", _myDataTable.onEventUnhighlightRow);
            //ART16 _myDataTable.subscribe("rowClickEvent", _myDataTable.onEventSelectRow);
            
            //
            // Subscribe to a click event to bind to expand/collapse the row
            //
            _actionInProgress = false;

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
                function __updateAfterDelete(context) {

                    if(!Hul.isnull(context)){

                        _myDataTable.deleteRow(oRecord.getId(), -1);

                        top.HEURIST.rectypes = context.rectypes;
                        top.HEURIST.detailTypes = context.detailTypes;
                        
                        if(top.hWin && top.hWin.HEURIST4){
                            top.hWin.HEURIST4.rectypes = context.rectypes;
                            top.hWin.HEURIST4.detailtypes = context.detailTypes;
                        }

                        if(_myDataTable.getRecordSet().getLength()<1){
                            $("#recStructure_toolbar").show();
                        }
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

                    var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";

                    function _onCheckEntries(context)
                    {
                        if(!Hul.isnull(context))
                        {
                            var dty_name = oRecord.getData('dty_Name');

                            var sWarn;
                            if(context[rty_ID]){
                                sWarn = "This field #"+rst_ID+" '"+dty_name+"' is utilised in the title mask. If you delete it you will need to edit the title mask in the record type definition form (click on the pencil icon for the record type after exiting this popup).\n\n Do you still wish to delete this field?";
                                var r=confirm(sWarn);
                            }else{
                                // unnecessary: sWarn =  "Delete field # "+rst_ID+" '"+dty_name+"' from this record structure?";
                                var r=1; // force deletion
                            }

                            if (r) {

                                _doExpliciteCollapse(null ,false); //force collapse this row

                                var callback = __updateAfterDelete;
                                var params = "method=deleteRTS&db="+db+"&rtyID="+rty_ID+"&dtyID="+rst_ID;
                                _isServerOperationInProgress = true;
                                Hul.getJsonData(baseurl, callback, params);
                            }

                        }
                    }

                    var callback = _onCheckEntries;
                    var params = "method=checkDTusage&db="+db+"&rtyID="+rty_ID+"&dtyID="+rst_ID;
                    top.HEURIST.util.getJsonData(baseurl, callback, params);

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

                    //prevent any operation in case of opened popup
                    if(!Hul.isnull(popupSelect) || _isServerOperationInProgress ||
                        (!Hul.isnull(column) && 
                            (column.key === 'rst_values' || column.key === 'rst_NonOwnerVisibility' || column.key === 'addColumn') ))
                        { 
                            //if (!Hul.isnull(popupSelect) || _isServerOperationInProgress) console.log('popup or action-in-progress');
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
                            _expandedRecord = rst_ID;

                            _myDataTable.onEventToggleRowExpansion(record_id);

                            _fromArrayToUI(rst_ID, false); //after expand restore values from HEURIST


                            var rowrec = _myDataTable.getTrEl(oRecord);
                            var maindiv = Dom.get("page-inner");
                            var pos = rowrec.offsetTop;
                            maindiv.scrollTop = pos - 30;

                            var elLiner = _myDataTable.getTdLinerEl({record:oRecord, column:_myDataTable.getColumn('rst_NonOwnerVisibility')});
                            elLiner.innerHTML = "";

                        }else{
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

                    if(!Hul.isnull(record_id) && column.key === 'expandColumn'){
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

                        // after expand/collapse need delay before filling values
                        setTimeout(__toggle, 300);

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

            if(needSave){
                _fromUItoArray(rst_ID); //before collapse save from UI to HEURIST
            }
            _setDragEnabled(true);
            _myDataTable.onEventToggleRowExpansion(record_id); //collapse row

            _expandedRecord = null;

            if(needSave){
                _saveUpdates(needClose); //global function
            }
        }
    }
    
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
        var arrStrucuture = top.HEURIST.rectypes.typedefs[rty_ID].dtFields;
        var fieldnames = top.HEURIST.rectypes.typedefs.dtFieldNames;

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
            var k;
            for(k=0; k<fieldnames.length; k++){
                var ed_name = 'ed'+__rst_ID+'_'+fieldnames[k];
                var edt = Dom.get(ed_name);
                if(!Hul.isnull(edt)){
                    //DEBUG if(values[k] != edt.value){
                    //	dbg.value = dbg.value + (fieldnames[k]+'='+edt.value);
                    //}
                    
                    if(fieldnames[k]=="rst_DefaultValue" && $('#incValue_'+__rst_ID+'_2').is(':checked')){
                            edt.value = 'increment_new_values_by_1';
                    }else if(fieldnames[k]=="rst_CreateChildIfRecPtr"){
                            edt.value = $(edt).is(':checked')?1:0;
                    }
                    
                    if(values[k] !== edt.value){

                        if(fieldnames[k]=="rst_DisplayName"){
                            if(top.HEURIST.util.validateName(edt.value, "Prompt (display name)")!=""){
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
            var dti = fieldnames.indexOf('dty_Type');
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
        top.HEURIST.rectypes.typedefs[rty_ID].dtFields = arrStrucuture;
    }//end of _doExpliciteCollapse

    /**
    * add or remove 'reserved' option in status dropdown
    */
    function _optionReserved(selstatus, isAdd){
        if(selstatus){
            if(isAdd && selstatus.length<4){
                Hul.addoption(selstatus, "reserved", "reserved");
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
        var findex = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex;

        var fieldnames = top.HEURIST.rectypes.typedefs.dtFieldNames,
        values = top.HEURIST.rectypes.typedefs[rty_ID].dtFields[rst_ID],
        rst_type = top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[findex.dty_Type],
        selstatus = Dom.get('ed'+rst_ID+'_rst_Status'),
        dbId = Number(top.HEURIST.database.id);

        //find original dbid
        var original_dbId = values[top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex.rst_OriginatingDBID];
        if(Hul.isnull(original_dbId)){
            //if original dbid is not defined for this field, we take it from common(header) of rectype
            //			original_dbId = top.HEURIST.rectypes.typedefs[rty_ID].commonFields[top.HEURIST.rectypes.typedefs.commonNamesToIndex.rty_OriginatingDBID];
            if(Hul.isnull(original_dbId)){
                original_dbId = dbId;
            }
        }

        var status = values[top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex.rst_Status];
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
        $('#ed'+rst_ID+'_rst_RequirementType').attr('data-original', values[top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex.rst_RequirementType]);
        $(selstatus).attr('data-original', status);

        if(rst_type === "separator"){

            $('#ed'+rst_ID+'_rst_RequirementType_separator').attr('checked',
            values[top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex.rst_RequirementType]=='forbidden');
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
                        (Hul.isempty(edt2.value)?top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[9]:edt2.value),//dty_JsonTermIDTree
                        (Hul.isempty(edt.value)?top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[10]:edt.value),//dty_TermIDTreeNonSelectableIDs
                        edt_def.value);
                        */
                        var allTerms = (Hul.isempty(edt2.value)?top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[findex.dty_JsonTermIDTree]:edt2.value);
                        var disabledTerms = (Hul.isempty(edt.value)?top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[findex.dty_TermIDTreeNonSelectableIDs]:edt.value);
                        var _dtyID = top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[findex['dty_ID']];
                        if(_dtyID==top.HEURIST.magicNumbers['DT_RELATION_TYPE']){ //specific behaviour
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
                        (Hul.isempty(edt.value)?top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[11]:edt.value) ); //dty_PtrTargetRectypeIDs
                        */
                        recreateRecTypesPreview(rst_type,
                            top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[top.HEURIST.detailTypes.typedefs.fieldNamesToIndex.dty_PtrTargetRectypeIDs]);

                        // IAN's request 2013-02-14
                        var edt_def = Dom.get('ed'+rst_ID+'_rst_DefaultValue');
                        edt_def.parentNode.parentNode.style.display = "none";

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
                    
                    
                }else if(rst_type === "relationtype"){

                }else if(rst_type === "resource"){
                    //show disable target pnr rectype

                }else if(rst_type === "separator"  &&
                    !(fieldnames[k] === "rst_DisplayName" //|| fieldnames[k] === "rst_DisplayWidth" 
                            || fieldnames[k] === "rst_DisplayHelpText" )){ //|| fieldnames[k] === "rst_RequirementType"
                        //hide all but name  and help
                        edt.parentNode.parentNode.style.display = "none";
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
        Dom.get('ed'+rst_ID+'_rst_DisplayName').focus();


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
        var ft_separator_group =  top.HEURIST.detailTypes.groups[0].id;
        var dtypes = top.HEURIST.detailTypes.typedefs;
        var fi = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex;
        var fnames = top.HEURIST.detailTypes.typedefs.commonFieldNames;
        var recDetTypes = top.HEURIST.rectypes.typedefs[rty_ID].dtFields;

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

            _detailType[fnames[fi.dty_Name]] = 'HEADING '+k;
            _detailType[fnames[fi.dty_ExtendedDescription]] = '';
            _detailType[fnames[fi.dty_Type]] = 'separator';
            _detailType[fnames[fi.dty_OrderInGroup]] = 0;
            _detailType[fnames[fi.dty_HelpText]] = 'Headings serve to break the data entry form up into sections';
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


            function _addNewSeparator(context) {

                if(!Hul.isnull(context)){

                    var error = false,
                    report = "",
                    ind;

                    for(ind in context.result){
                        if( !Hul.isnull(ind) ){
                            var item = context.result[ind];
                            if(isNaN(item)){
                                Hul.showError(item);
                                error = true;
                            }else{
                                ft_separator_id = ""+Math.abs(Number(item));

                                //refresh the local heurist
                                top.HEURIST.detailTypes = context.detailTypes;

                                _doExpliciteCollapse(null, true);
                                _addDetails(ft_separator_id, index_toinsert);
                            }
                        }
                    }
                }
            }

            //
            var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
            var callback = _addNewSeparator;
            var params = "method=saveDT&db="+db+"&data=" + encodeURIComponent(str);
            Hul.getJsonData(baseurl, callback, params);

        }
    }

    /**
    * Adds the list of new detail types to this record structure
    *
    * After addition it saves all on server side
    * This is function for global method addDetail. It is invoked after selection of detail types or creation of new one
    * @param dty_ID_list - comma separated list of detail type IDs
    */
    function _addDetails(dty_ID_list, index_toinsert){

        if(dty_ID_list=='section_header'){
            _onAddSeparator(index_toinsert);
            return;
        }

        var arrDty_ID = dty_ID_list.split(",");
        if(Hul.isempty(dty_ID_list) || arrDty_ID.length<1) {
            return;
        }

        var recDetTypes = top.HEURIST.rectypes.typedefs[rty_ID].dtFields;

        //new odetail type
        if(Hul.isnull(recDetTypes)){
            recDetTypes = {}; //new Object();
        }

        var data_toadd = [];
        var detTypes = top.HEURIST.detailTypes.typedefs,
        fi = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex,
        rst = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex;

        //find max order and index to insert
        var recs = _myDataTable.getRecordSet();
        var row_index, k, len = recs.getLength();

        if(Hul.isnull(index_toinsert)){
            var sels = _myDataTable.getSelectedRows();

            for (row_index = 0; row_index < len; row_index++ )
            {
                var rec = _myDataTable.getRecord(row_index);
                if(sels[0]==rec.getId()){
                    index_toinsert = row_index+1;
                    break;
                }
            }
            if(index_toinsert==null){
                index_toinsert = recs.getLength()-1;
            }
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
                    def_height = 3;
                }else if (dt_type === "date" || dt_type === "integer" || dt_type === "float" || dt_type === "year" ||
                    dt_type === "calculated") { // numeric and date don't need much width by default
                        def_width = 10;
                    }else if (dt_type === "boolean") {
                        def_width = 4; break;
                    }

                var arr_target = new Array();
                arr_target[rst.rst_DisplayName] = arrs[fi.dty_Name];
                arr_target[rst.rst_DisplayHelpText] = arrs[fi.dty_HelpText];
                arr_target[rst.rst_DisplayExtendedDescription] = arrs[fi.dty_ExtendedDescription];
                arr_target[rst.rst_DefaultValue ] = "";
                arr_target[rst.rst_RequirementType] = "optional";
                arr_target[rst.rst_MaxValues] = "1";
                arr_target[rst.rst_MinValues] = "0";
                arr_target[rst.rst_DisplayWidth] = def_width;
                arr_target[rst.rst_DisplayHeight] = def_height; //rows
                arr_target[rst.rst_RecordMatchOrder] = "0";
                arr_target[rst.rst_DisplayOrder] = order;
                arr_target[rst.rst_DisplayDetailTypeGroupID] = "1";
                arr_target[rst.rst_FilteredJsonTermIDTree] = null;
                arr_target[rst.rst_PtrFilteredIDs] = null;
                arr_target[rst.rst_CreateChildIfRecPtr] = 0;
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
                    rst_DisplayName: arrs[fi.dty_Name],
                    dty_Type: arrs[fi.dty_Type],
                    rst_RequirementType: "optional",
                    rst_DisplayWidth: def_width,
                    rst_DisplayHeight: def_height,
                    rst_MinValues: 1,
                    rst_MaxValues: 1,
                    rst_DefaultValue: "",
                    rst_Status: "open",
                    rst_NonOwnerVisibility: "viewable",
                    rst_values: arr_target });


                _updatedDetails.push(dty_ID); //track change

                order++;
            }
        }//end for

        if(data_toadd.length>0){
            top.HEURIST.rectypes.typedefs[rty_ID].dtFields = recDetTypes;

            _myDataTable.addRows(data_toadd, index_toinsert);

            _myDataTable.unselectAllRows();
            //ART16 _myDataTable.selectRow(index_toinsert);

            // in case of addition - all fields were affected
            _updatedFields = null;

            dragDropDisable();
            dragDropEnable();

            _saveUpdates(false);

            $("#recStructure_toolbar").hide();

            _updateOrderAfterInsert( data_toadd[data_toadd.length-1]['rst_ID'] );
        }

    }//end _addDetails

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
                top.HEURIST.rectypes.typedefs[rty_ID].dtFields[data.rst_ID][top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex.rst_DisplayOrder] = i;
                isChanged = true;
            }
            neworder.push(data.rst_ID);
        }

        if(isChanged){
            //index if field rst_DisplayOrder
            var field_index = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex.rst_DisplayOrder;

            if(!Hul.isnull(_updatedFields) && _updatedFields.indexOf(field_index)<0){
                _updatedFields.push(field_index);
            }
            top.HEURIST.rectypes.dtDisplayOrder[rty_ID] = neworder;
            _saveUpdates(false);
        }
        
        _isServerOperationInProgress = false;
        
        //remark this exit to prevent expand after insert
        if($('#showEditOnNewField').is(':checked')){
            //emulate click on just added row
            setTimeout(function(){
                
                var oRecord = _getRecordById(lastID).record;
                var elLiner = _myDataTable.getTdLinerEl({record:oRecord, column:_myDataTable.getColumn('expandColumn')});
                //$.find("tr.yui-dt-last > td.yui-dt-col-rst_DisplayName")[0]
                var oArgs = {event:'MouseEvent', target: elLiner};
                onCellClickEventHandler(oArgs);
            },500);
        }
    }

    /**
    * asyncSubmitter for inline editor
    * 
    */
    function _updateSingleField(dty_ID, fieldName, oOldValue, oNewValue, fnCallback){
         
         fnCallback(true, oNewValue);
          
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
     
            var updateResult = function(context){
                if(!Hul.isnull(context)){
                    var win = top;
                    win.HEURIST.rectypes = context.rectypes;
                    win.HEURIST.detailTypes = context.detailTypes;
                        if(win.hWin && win.hWin.HEURIST4){
                            win.hWin.HEURIST4.rectypes = context.rectypes;
                            win.hWin.HEURIST4.detailtypes = context.detailTypes;
                            win.hWin.HEURIST4.terms = context.terms;
                        }
    
                    editStructure._structureWasUpdated = true;
                    
                    //fnCallback(true, oNewValue);
                }else{
                    //fnCallback(false, oNewValue);    
                }
                _isServerOperationInProgress = false;
                
                $('.save-btn').removeProp('disabled').css('opacity','1');
            };
            
            $('.save-btn').prop('disabled', 'disabled').css('opacity','0.3');
            
            var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
            var callback = updateResult;
            var params = "method=saveRTS&db="+db+"&data=" + encodeURIComponent(str);

            Hul.getJsonData(baseurl, callback, params);           
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
            var fieldnames = top.HEURIST.rectypes.typedefs.dtFieldNames;
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
            var typedefs = top.HEURIST.rectypes.typedefs[rty_ID].dtFields;
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
            var str = YAHOO.lang.JSON.stringify(orec);
            return str;
        }else{
            return null;
        }
    }

    /**
    * Sends all changes to server
    * before sending it prepares the object with @see _getUpdates
    *
    * @needClose - if true closes this popup window, but it is always false now
    */
    function _saveUpdates(needClose)
    {
        var str = _getUpdates();
        //if(str!=null)	alert(str);  //you can check the strcuture here
        //clear all trace of changes
        _clearUpdates();

        var btnSaveOrder = Dom.get('btnSaveOrder');
        btnSaveOrder.style.display = "none";
        btnSaveOrder.style.visibility = "hidden";

        if(!Hul.isnull(str)){
            var updateResult = function(context){
                if(!Hul.isnull(context)){
                    top.HEURIST.rectypes = context.rectypes;
                    top.HEURIST.detailTypes = context.detailTypes;
                        if(top.hWin && top.hWin.HEURIST4){
                            top.hWin.HEURIST4.rectypes = context.rectypes;
                            top.hWin.HEURIST4.detailtypes = context.detailTypes;
                            top.hWin.HEURIST4.terms = context.terms;
                        }
                    
                    editStructure._structureWasUpdated = true;
                }
                _isServerOperationInProgress = false;
                if(needClose){
                    window.close(editStructure._structureWasUpdated);
                }
            };
            var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
            var callback = updateResult;
            var params = "method=saveRTS&db="+db+"&data=" + encodeURIComponent(str);
            _isServerOperationInProgress = true;
            Hul.getJsonData(baseurl, callback, params);

        }else if(needClose){
            window.close();
        }
    }

    /**
    * returns true if there is at list on required field in structure
    */
    function _checkForRequired(){


        var typedef = top.HEURIST.rectypes.typedefs[rty_ID];
        var fi = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex;
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
        var typedef = top.HEURIST.rectypes.typedefs[rty_ID];
        var maskvalue = typedef.commonFields[ top.HEURIST.rectypes.typedefs.commonNamesToIndex.rty_TitleMask ];

        var baseurl = top.HEURIST.baseURL + "admin/structure/rectypes/editRectypeTitle.php";
        var squery = "rty_id="+rty_ID+"&mask="+encodeURIComponent(maskvalue)+"&db="+db+"&check=1";

        top.HEURIST.util.sendRequest(baseurl, function(xhr) {
            var obj = xhr.responseText;
            if(obj==="" || obj==="\n"){
                if(callback){
                    callback.call();
                }
            }else{
                //alert('It appears that you removed some field(s) that are in use in the title mask. Please edit the title mask to correct it. '+obj);
                var ele = document.getElementById("dlgWrongTitleMask");


                var $dlg_warn = Hul.popupTinyElement(window, ele,
                    { "no-titlebar": false, "no-close": false, width: 400, height:160 });

                $(ele).find("#dlgWrongTitleMask_closeBtn").click(function(){
                    if($dlg_warn!=null){
                        $dlg_warn.dialog('close');
                    }
                    _doEditTitleMask(true);
                });

            }

            }, squery);
    }


    //show edit title mask
    function _doEditTitleMask(is_onexit){
        if(is_onexit) top.HEURIST.util.closePopupLast();
        //top.HEURIST.util.closePopup(warningPopupID);
        warningPopupID = null;
        //top.HEURIST.util.closePopupAll();
        var typedef = top.HEURIST.rectypes.typedefs[rty_ID];
        var maskvalue = typedef.commonFields[ top.HEURIST.rectypes.typedefs.commonNamesToIndex.rty_TitleMask ];

        Hul.popupURL(top, top.HEURIST.baseURL +
            "admin/structure/rectypes/editRectypeTitle.html?rectypeID="+rty_ID+"&mask="+encodeURIComponent(maskvalue)+"&db="+db,
            {
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
        e = top.HEURIST.util.stopEvent(e);
        var targ = e.target;
        var rst_ID = targ.getAttribute("rst_ID")

        if(!rst_ID) return;

        var index_toinsert = _getRecordById(rst_ID).row_index;
        _myDataTable.unselectAllRows();

        
        if(isSectionHeader===true){
            _onAddSeparator(index_toinsert);
        }else{
            onAddNewDetail(index_toinsert);
        }
        
    }

    //Ian decided to remove this feature -however it may be helpful in another place
    var onMenuClick = function (eventName, eventArgs, subscriptionArg){
        var clonearr = top.HEURIST.util.cloneObj(subscriptionArg);
        var fname = clonearr.shift();
        var args = clonearr;
        top.HEURIST.util.executeFunctionByName(fname, window, args);
    }

    function _addFieldMenu(e){

        e = top.HEURIST.util.stopEvent(e);
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
                { text: "Add section header" }
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
                myDTDrags[id].unreg();
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
                top.HEURIST.rectypes.typedefs[rty_ID].dtFields[data.rst_ID][top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex.rst_DisplayOrder] = i;
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
            var field_index = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex.rst_DisplayOrder;

            if(!Hul.isnull(_updatedFields) && _updatedFields.indexOf(field_index)<0){
                _updatedFields.push(field_index);
            }
            top.HEURIST.rectypes.dtDisplayOrder[rty_ID] = neworder;

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

            proxyEl = this.proxyEl = this.getDragEl();
            srcEl = this.srcEl = this.getEl();

            if(x>50){
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
            $(proxyEl).css({'background':'rgb(200,200,200,0.5)', 'border':'2px dotted red'});

            //var rst_ID = this.srcData.rst_ID
            //_fromUItoArray(rst_ID); //before collapse save to UI

        },

        endDrag: function(x,y) {

            if(this.srcIndex===null) { return; }

            var position,
            srcEl = this.srcEl;

            proxyEl.innerHTML = "";
            Dom.setStyle(this.proxyEl, "visibility", "hidden"); //hide drag div
            Dom.setStyle(srcEl, "visibility", "");
            
            //restore color and font
            if(this.tmpIndex>=0){
                var rec = _myDataTable.getRecord(this.tmpIndex);
                var rowrec = _myDataTable.getTrEl(rec);
                Dom.setStyle(rowrec,'font-weight','normal');
                Dom.setStyle(rowrec,'background','');
            }
            

            _updateOrderAfterDrag();
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
        var recTypeIcon  = top.HEURIST.iconBaseURL+rty_ID+'&t='+(new Date()).getTime();
        var formTitle = document.getElementById('recordTitle');
        //formTitle.innerHTML = '';
        formTitle.innerHTML = "<div class=\"rectypeIconHolder\" style=\"background-image:url("+
                recTypeIcon+")\"></div><span class=\"recTypeName\">"+top.HEURIST.rectypes.names[rty_ID]+"</span>"+
                hToolBar;
        
        var fi  = top.HEURIST.rectypes.typedefs.commonNamesToIndex;
        var rectype = top.HEURIST.rectypes.typedefs[rty_ID].commonFields;

        document.getElementById('rty_TitleMask').value = rectype[fi.rty_TitleMask];
        document.getElementById('rty_Description').innerHTML = rectype[fi.rty_Description];

        document.getElementById("div_rty_ID").innerHTML = 'Local ID: '+rty_ID;
        
        if(Hul.isempty(rectype[fi.rty_ConceptID])){
            document.getElementById("div_rty_ConceptID").innerHTML = '';
        }else{
            document.getElementById("div_rty_ConceptID").innerHTML = 'Concept Code: '+ rectype[fi.rty_ConceptID];
        }
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
        addDetails:function(dty_ID_list, index_toinsert){
            _addDetails(dty_ID_list, index_toinsert);
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
            top.HEURIST.util.closePopupLast();
            //top.HEURIST.util.closePopup(warningPopupID);
            warningPopupID = null;
        },

        closeWin:function(){
            if(_expandedRecord!=null){
                
                _doExpliciteCollapse(null, true, true);
                
                //alert("You have to save or cancel changes for editing field first");
                return;                
            }
            if(_checkForRequired()){

                _checkForTitleMask(function(){ 
                        //wait for inline editor
                        function __waitForInlineEditor(){
                             if(_isServerOperationInProgress){ //wait next 500 seconds
                                setTimeout(__waitForInlineEditor, 300);
                             }else{
                                window.close(editStructure._structureWasUpdated);          
                             }
                        }
                        
                        setTimeout(__waitForInlineEditor, 300);
                });

            }else{
                alert("You should have at least one required field and at least one of them should appear in the title mask to ensure that the constructed title is not blank. \n\nPlease set one or more fields to Required.");
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
        var dim = top.HEURIST.util.innerDimensions(top);
        var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
            (top.HEURIST.database.name?top.HEURIST.database.name:''));
        popupSelect = Hul.popupURL(window, top.HEURIST.baseURL +
            "admin/structure/fields/selectDetailType.html?rty_ID="+editStructure.getRty_ID()+"&db="+db,
            {	"close-on-blur": false,
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

        var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
            (top.HEURIST.database.name?top.HEURIST.database.name:''));
        var url = top.HEURIST.baseURL + "admin/structure/fields/editDetailType.html?db="+db;

        popupSelect = Hul.popupURL(top, url,
            {	"close-on-blur": false,
                "no-resize": false,
                title: 'Edit field type',
                height: 700,
                width: 840,
                callback: function(context) {

                    if(!Hul.isnull(context)){
                        //refresh the local heurist
                        top.HEURIST.detailTypes = context.detailTypes;

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
    
    var dbId = Number(top.HEURIST.database.id);
    
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
            //show prompt
            window.hWin.HEURIST4.msg.showPrompt('Enter password: ',
                function(value){
                    
                    window.hWin.HAPI4.SystemMgr.action_password({action:'ReservedChanges', password:value},
                        function(response){
                            if(response.status == window.hWin.HAPI4.ResponseStatus.OK && response.data=='ok'){
                                $(el).attr('data-original', ''); //reset to warining once
                                el.value = new_value;
                            }else{
                                alert('Wrong password');
                            }
                        }
                    );
                    
                },
            'To reset field status from "reserved"');
    
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
        var values = top.HEURIST.rectypes.typedefs[rty_ID].dtFields[rst_ID];    
        var curr_value = values[top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex.rst_RequirementType];     */
        var curr_value = $(el).attr('data-original');
        var new_value = el.value;
        if(new_value=='forbidden' || new_value=='hidden'){
            el.value = curr_value; //restore
            return;
        }

        
        if(curr_value=='required' && new_value!=curr_value){ //value was changed - confirm
        
            el.value = curr_value; //restore
            
            var ele = document.getElementById("change_Req");
            $("#change_Req").css("display","block");
            $("#reqText").text("This is a reserved field which may be required by a specific function such as Zotero import or mapping. "+
                "Reserved fields can be marked as Recommended or Optional rather than Required, however we recommend thinking " +
                "carefully about whether the value is required before changing this setting.")

            $("#change_Btn").click(function(){
                $(el).attr('data-original', new_value); //reset - to show this warning once only
                el.value = new_value;
                ___onReqtypeChange_continue();
                $_dialogbox.dialog($_dialogbox).dialog("close");
            });
            $("#cancel_Btn").click(function(){
                $_dialogbox.dialog($_dialogbox).dialog("close");
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
    var status = Dom.get(name+"_rst_Status").value;
    if (status === "reserved"){
        
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

            $("#change_Btn").click(function(){
                el.value = new_value;
                $(el).attr('data-original',''); //reset
                ___onRepeatChange_continue();
                $_dialogbox.dialog($_dialogbox).dialog("close");
            });
            $("#cancel_Btn").click(function(){
                $_dialogbox.dialog($_dialogbox).dialog("close");
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
            //Dom.setStyle(span_min, "visibility", "hidden");
            //Dom.setStyle(span_max, "visibility", "hidden");
        } else if(el.value === "repeatable"){
            el_max.value = 0;
            //Dom.setStyle(span_min, "visibility", "hidden");
            //Dom.setStyle(span_max, "visibility", "hidden");
        } else if(el.value === "limited"){
            if(el_max.value<2) el_max.value = 2;
            Dom.setStyle(span_max, "visibility", "visible");
            //TEMP Dom.setStyle(span_max, "visibility", "visible");
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
                        $(ele).prop('checked',true);
                        //start action
                        var request = {
                             a: 'add_reverse_pointer_for_child',
                             rtyID: rty_ID,
                             dtyID: rst_ID,
                             allow_multi_parent:true
                        };
                        
                        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                            if(response.status == hWin.HAPI4.ResponseStatus.OK){
                                //show report

var link = '<a target="blank" href="'+window.hWin.HAPI4.baseURL + '?db='+window.hWin.HAPI4.database+'&q=ids:';
var link2 = '"><img src="'+window.hWin.HAPI4.baseURL+'common/images/external_link_16x16.gif">&nbsp;';
           
function __getlink(arr){
    return link+arr.join(',')+link2+arr.length; 
}           
           
                                
sMsg = '<h3>Conversion of records to child records</h3><br>'
+'<div>'+response.data['passed']+' values were found (child record ids)</div>'
+(response.data['disambiguation']>0?('<div>'+response.data['disambiguation']+' values ignored. The same records were pointed to as a child record by more than one parent</div>'):'')
+(response.data['noaccess']>0?('<div>'+response.data['noaccess']+' records can not be converted to child records (no access rights)</div>'):'');

if(response.data['processedParents'].length>0){
sMsg = sMsg
+'<br><div>'+__getlink(response.data['processedParents'])+' parent records</a> (records of this type with this pointer field) were processed</div>'
+((response.data['childInserted'].length>0)?('<div>'+__getlink(response.data['childInserted'])+' records</a> were converted to child records</div>'):'')
+((response.data['childUpdated'].length>0)?('<div>'+__getlink(response.data['childUpdated'])+' child records</a> changed its parent</div>'):'')
+((response.data['titlesFailed'].length>0)?('<div>'+__getlink(response.data['titlesFailed'])+' child records</a> failed to update tecord title</div>'):'');
}
if(response.data['childAlready'].length>0){
sMsg = sMsg
+'<div>'+__getlink(response.data['childAlready'])+' child records</a> already have the required reverse pointer</div>';
}

if(response.data['childMiltiplied'].length>0){
sMsg = sMsg
+'<div>'+__getlink(response.data['childMiltiplied'])+' records</a> were pointed to as a child record by more than one parent</div>'
+'<br><div>You will need to edit these records and choose which record is the parent (child records can only have one parent).</div>'
+'<div>To find these records use Manage > Structure > Verify <new tab icon></div><br>'
}

//sMsg = sMsg 
sMsg = sMsg 
+'<br>Notes<br><div>We STRONGLY recommend removing - from the record structure of the child record type(s) -  any existing field which points back to the parent record</div>'
+'<br><div>You will also need to update the record title mask to use the new Parent Entity field to provide information (rather than existing fields which point back to the parent)</div>'
+'<br><div>You can do both of these changes through Manage > Structure > Build <new tab icon> or the modify structure <new tab icon> link when editing a record.</div>';
                            
                                window.hWin.HEURIST4.msg.showMsgDlg(sMsg);
                                
                                //save from UI to HEURIST
                                editStructure.doExpliciteCollapse(rst_ID, true);
                                
                            }else{
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
    
  top.HEURIST.util.popupElement(
                hasH4()?top:window.hWin, 
                document.getElementById('info_rst_CreateChildIfRecPtr'),
                {title:'Creation of records as children',height:300});
    
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

    allTerms = Hul.expandJsonStructure(allTerms);
    disabledTerms = Hul.expandJsonStructure(disabledTerms);

    if (typeof disabledTerms.join === "function") {
        disabledTerms = disabledTerms.join(",");
    }

    if(!Hul.isnull(allTerms)) {
        //remove old combobox
        var el_sel;
        /* = Dom.get(_id);
        var parent = el_sel.parentNode;
        parent.removeChild( el_sel );
        */

        var parent1 = document.getElementById("termsPreview"),
        parent2 = document.getElementById("termsDefault_"+rst_ID);

        function __recreate(parent, onchangehandler, bgcolor, _defvalue, isdefselector){
            var i;
            for (i = 0; i < parent.children.length; i++) {
                parent.removeChild(parent.childNodes[0]);
            }

            el_sel = Hul.createTermSelectExt(allTerms, disabledTerms, datatype, _defvalue, isdefselector);
            el_sel.id = 'ed'+rst_ID+'_rst_DefaultValue';
            el_sel.style.backgroundColor = bgcolor;
            el_sel.onchange =  onchangehandler;
            el_sel.className = "previewList"; //was for enum only?
            parent.appendChild(el_sel);
        }//end __recreate

        __recreate(parent1, _preventSel, "#cccccc", null, false);
        __recreate(parent2, _setDefTermValue, "#ffffff", defvalue, true);
    }
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
            dtName = top.HEURIST.rectypes.names[arr[ind]];
            if(!txt) {
                txt = dtName;
            }else{
                txt += ", " + dtName;
            }
        } //for
    }else{
        txt = "unconstrained";
    }

    if (txt && txt.length > 40){
        divRecType.title = txt;
        txt = txt.substr(0,40) + "&#8230";
    }else{
        divRecType.title = "";
    }
    divRecType.innerHTML = txt;
}

function _onDispNameChange(event){
    var name = event.target.value;
    var swarn = top.HEURIST.util.validateName(name, "Prompt (display name)");
    if(swarn){
        alert(swarn);
    }
}

//
//   dty_ID (=rst_ID) - field type ID - edit base field definition
//
function _onAddEditFieldType(dty_ID, rty_ID){

    //show warning first
    
    //find all records that reference this type
    var aUsage = top.HEURIST.detailTypes.rectypeUsage[dty_ID];
    if(!Hul.isnull(aUsage)){

        textTip = '';
        var k;
        for (k in aUsage) {
            if(rty_ID!=aUsage[k]){
                //textTip = textTip + '<li>'+aUsage[k]+'  '+top.HEURIST.rectypes.names[aUsage[k]]+'</li>';
                textTip = textTip + aUsage[k]+'  '+top.HEURIST.rectypes.names[aUsage[k]]+'\n';
            }
        }
        if(textTip!=''){
        
            var detname = top.HEURIST.detailTypes.names[dty_ID];
            if(detname.length>40) { detname = detname.substring(0,40)+"..."; }

            textTip = //'<b>Warning: Use with care</b><br>'
                'Changes made to the base field type "'+detname
                +'" (changes to vocabulary or list of terms, changes of pointer target type) '
                +'affect ALL record types which use the base field type:\n\n'
                +textTip+'\n\nWarning: Use with care. Proceed?';
                
           if(!confirm(textTip)) return;
                
                
        }
    }    
    
    
    
    
    var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
        (top.HEURIST.database.name?top.HEURIST.database.name:''));
    var url = top.HEURIST.baseURL + "admin/structure/fields/editDetailType.html?db="+db+ "&detailTypeID="+dty_ID; //existing

    top.HEURIST.util.popupURL(top, url,
        {   "close-on-blur": false,
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
                    grpID_old = top.HEURIST.detailTypes.typedefs[dty_ID].commonFields[7];
                    }*/

                    //refresh the local heurist
                    top.HEURIST.detailTypes = context.detailTypes;
                    _cloneHEU = null;
                    
                    editStructure._structureWasUpdated = true; //no access  to top object
                    
                    var fi = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex;

                    var rst_type = top.HEURIST.detailTypes.typedefs[dty_ID].commonFields[fi.dty_Type];
                    //update
                    if(rst_type === "enum" || rst_type === "relmarker" || rst_type === "relationtype"){
                        recreateTermsPreviewSelector(dty_ID, rst_type,
                            top.HEURIST.detailTypes.typedefs[dty_ID].commonFields[fi.dty_JsonTermIDTree],
                            top.HEURIST.detailTypes.typedefs[dty_ID].commonFields[fi.dty_TermIDTreeNonSelectableIDs], null);
                    }
                    if(rst_type === "relmarker" || rst_type === "resource"){
                        recreateRecTypesPreview(dty_ID,
                            top.HEURIST.detailTypes.typedefs[dty_ID].commonFields[fi.dty_PtrTargetRectypeIDs]); //rst_type, null);
                    }

                    /*detect what group
                    var grpID = top.HEURIST.detailTypes.typedefs[dty_ID].commonFields[7];

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

        var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
            (top.HEURIST.database.name?top.HEURIST.database.name:''));
            
        var url = top.HEURIST.baseURL 
                    + "admin/structure/rectypes/editRectype.html?supress=1&db="
                    + db+"&rectypeID="+editStructure.getRty_ID();

        var dim = Hul.innerDimensions(top);                    
        
        popupSelect = Hul.popupURL(top, url,
            {   "close-on-blur": false,
                "no-resize": false,
                title:'Edit Record Type',
                height: dim.h*0.9,
                width: 800,
                    
            callback: function(context) {

                if(!Hul.isnull(context) && context.result){

                     //refresh the local heurist
                     top.HEURIST.rectypes = context.rectypes;
                        if(top.hWin && top.hWin.HEURIST4){
                            top.hWin.HEURIST4.rectypes = context.rectypes;
                        }
                     //var _rtyID = Number(context.result[0]);
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

