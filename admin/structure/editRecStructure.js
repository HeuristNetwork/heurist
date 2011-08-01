// EditRecStrucutre object
var editStructure;

// reference to popup window - select or add new field type
var popupSelect = null;

//aliases
var Dom = YAHOO.util.Dom,
	Event = YAHOO.util.Event,
	DDM = YAHOO.util.DragDropMgr,
	Hul = top.HEURIST.util;


/**
* EditRecStructure - class for pop-up edit record type structure
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2011.0427
*/

function EditRecStructure() {

	var _className = "EditRecStructure",
	_myDataTable,
	_tabView = new YAHOO.widget.TabView(),
	rty_ID,
	_isDragEnabled = false,
	_updatedDetails = [], //list of dty_ID that were affected with edition
	_updatedFields = [],  //list of indexes in fieldname array that were affected
	_expandedRecord = null, //rstID of record (row) in design datatable that is (was) expanded (edited)
	_isServerOperationInProgress = false; //prevents send request if there is not respoce from previous one
	myDTDrags = {};

	/**
	* Initialization of input form
	*
	* Reads GET parameters, creates TabView and triggers init of first tab
	*/
	function _init() {

		// read GET parameters
		if (location.search.length > 1) {
			window.HEURIST.parameters = top.HEURIST.parseParams(location.search);
			rty_ID = window.HEURIST.parameters.rty_ID;
			//DEBUG Dom.get("ed_rty_ID").value = rty_ID;
			document.title = "Record Type: " + rty_ID+" : " + top.HEURIST.rectypes.names[rty_ID];
		}

		// buttons on top and bottom of design tab
		var hToolBar = '<div style="width:600px"><div style="display:inline-block; text-align:left">'+
		//'<input type="button" value="collapse all" onclick="onCollapseAll()"/>'+
		//'<input type="button" value="Enable Drag" onclick="onToggleDrag(event)"/></div>'+
		'<label style="width:400px;text-align:center;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Click row to edit or Drag to change the order</label>'+
		'<div style="float:right; text-align:right;min-width:360;">'+
		'<input style="display:none;" type="button" id="btnSaveOrder" value="Save Order" onclick="onUpdateStructureOnServer(false)"/>'+
		'<input type="button" value="Insert Field" onclick="onAddNewDetail()"/>'+
		//'<input type="button" value="Done" onclick="onUpdateStructureOnServer(true)"/>'+
		'</div></div>';
		//'<textarea id="dbg" rows="5"></textarea>';

		//create TabView with 2 tabs
		_tabView.addTab(new YAHOO.widget.Tab({
			id: 'design',
			label: 'Design',
			content: '<div id="tabDesign" style="width:600;margin:auto;">'+hToolBar+'<div id="tableContainer"></div>'+hToolBar+'</div>'
		}));
		_tabView.addTab(new YAHOO.widget.Tab({
			id: 'preview',
			label: 'Preview',
			content:
			'<div id="all-inputs" style="width:590, max-width:590;"></div>'
		}));
		_tabView.addListener("activeTabChange", _handleTabChange);
		_tabView.appendTo("modelTabs");

		//init design tab
		_tabView.set("activeIndex", 0);
/*
			'<div id="previewpage">'+
				'<input type=checkbox id=input-visibility onClick="Hul.setDisplayPreference(\'input-visibility\', this.checked? \'all\' : \'recommended\')" class=minimal>'+
				'<label for=input-visibility>show optional fields</label>'+
			'</div>'+
				'<script>'+
					'top.HEURIST.whenLoaded("display-preferences", function() {'+
						'document.getElementById("input-visibility").checked = (top.HEURIST.displayPreferences["input-visibility"] === "all");'+
					'});'+
				'</script>'+
*/
	}

	/**
	* Event listener on TabView active page change
	*
	* Creates either design grid or fills preview of Record input form
	*/
	function _handleTabChange (e) {

		var ind = _tabView.get("activeIndex");
		if(e.newValue!==e.prevValue){
			if(ind===0){
				//reload design
				_initTabDesign(null);

			}else if(ind===1){
				//reload preview
				_initTabPreview();
			}
		}
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
			if(Hul.isnull(typedef)){
				alert("Record type ID "+rty_ID+" is not exist");
				rty_ID = null;
				return;
			}

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
				var rst_ID;
				for (rst_ID in _dts) {
					var aval = _dts[rst_ID];
					arr.push([ rst_ID, rst_ID, aval[9],
					aval[0], top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[2], aval[4],
					aval[7], aval[6], aval[5], aval[3], aval[15],
					aval]);
				}
			}

			// define datasource for datatable
			var myDataSource = new YAHOO.util.LocalDataSource(arr,{
				responseType : YAHOO.util.DataSource.TYPE_JSARRAY,
				responseSchema : {
					fields: [ "rst_ID","expandColumn", "rst_DisplayOrder",
					"rst_DisplayName", "dty_Type", "rst_RequirementType",
					"rst_DisplayWidth", "rst_MinValues", "rst_MaxValues", "rst_DefaultValue", "rst_Status",
					"rst_values"]
				}
			});

			/*
			0"rst_DisplayName", +
			1"rst_DisplayHelpText", +
			2"rst_DisplayExtendedDescription",
			3"rst_DefaultValue" +
			4"rst_RequirementType" +
			5"rst_MaxValues" +
			6"rst_MinValues" +
			7"rst_DisplayWidth" +
			8"rst_RecordMatchOrder"  ????
			9"rst_DisplayOrder" +
			10"rst_DisplayDetailTypeGroupID"
			11"rst_FilteredJsonTermIDTree"
			12"rst_PtrFilteredIDs"
			13 "rst_TermIDTreeNonSelectableIDs"
			14 "rst_CalcFunctionID"
			15 "rst_Status"   !!!!!
			16 "rst_OrderForThumbnailGeneration"
			17 "dty_TermIDTreeNonSelectableIDs"
			18 "dty_FieldSetRectypeID"
			*/

			var myColumnDefs = [
			{
				key:"rst_ID", label: "Code", sortable:false
			},
			{
				key:"expandColumn",
				label: "Edit",
				hidden:true, //width : "16px",
				sortable:false,
				formatter:expansionFormatter
			},
			{
				key:"rst_DisplayOrder", label: "Order", sortable:true, hidden:true
			},
			{
				key:"rst_DisplayName", label: "DispName", width:120, sortable:false },
			{
				key:"dty_Type", label: "Type", sortable:false
			},
			{
				key:"rst_DisplayWidth", label: "Wdt", sortable:false, width:10
			},
			//{ key:"rst_DisplayHelpText", label: "Prompt", sortable:false },
			{
				key:"rst_RequirementType", label: "Req", sortable:false, width:10,
				formatter: function(elLiner, oRecord, oColumn, oData){
					var reqtype = oRecord.getData('rst_RequirementType');
					elLiner.innerHTML = reqtype.substring(0,3);
				}

			},
			{
				key:"rst_MinValues", label: "Min", sortable:false, width:10
			},
			{
				key:"rst_MaxValues", label: "Max", sortable:false, width:10
			},
			{
				key:"rst_DefaultValue", label: "Default", sortable:false,
				formatter: function(elLiner, oRecord, oColumn, oData){
					var reqtype = oRecord.getData('rst_DefaultValue');
					elLiner.innerHTML = reqtype.substring(0,9);
				}
			},
			{
				key:"rst_Status", label: "Status", sortable:false
			},
			{
				key: "rst_values",
				label: "Del",
				width : "16px",
				sortable: false,
				formatter: function(elLiner, oRecord, oColumn, oData){
					elLiner.innerHTML =
					'<a href="#delete"><img src="../../common/images/delete_icon.png" width="16" height="16" border="0" title="Remove detail" /><\/a>';
				}
			}
			];

			//create datatable
			_myDataTable = new YAHOO.widget.RowExpansionDataTable(
			"tableContainer",
			myColumnDefs,
			myDataSource,
			//this is box of expandable record
			{ rowExpansionTemplate :
				function ( obj ) {
					var rst_ID = obj.data.getData('rst_ID');
					//var rst_values = obj.data.getData('rst_values');
					/*
					THIS IS FORM to edit detail structure. It is located on expandable row of table
					*/
					obj.liner_element.innerHTML =
					'<div style="padding-left:30; padding-bottom:5; padding-right:5">'+
					'<div class="dtyField"><label class="dtyLabel">Display name/Label:</label><input id="ed'+rst_ID+'_rst_DisplayName" title="Display Name/Label"/></div>'+
					'<div class="dtyField"><label class="dtyLabel">Help Text/Prompt:</label><input id="ed'+rst_ID+'_rst_DisplayHelpText" style="width:350px" title="Help Text"/></div>'+
					'<div class="dtyField"><label class="dtyLabel">Default Value:</label><input id="ed'+rst_ID+'_rst_DefaultValue" title="Default Value"/></div>'+
					'<div class="dtyField"><label class="dtyLabel">Width:</label><input id="ed'+rst_ID+'_rst_DisplayWidth" title="Visible width of field" style="width:40" size="4" onkeypress="Hul.validate(event)"/></div>'+

					'<div class="dtyField"><label class="dtyLabel">Requirement type:</label>'+
					'<select id="ed'+rst_ID+'_rst_RequirementType" onchange="onReqtypeChange(event)">'+
					'<option value="required">required</option>'+
					'<option value="recommended">recommended</option>'+
					'<option value="optional">optional</option>'+
					'<option value="forbidden">forbidden</option></select>'+
					'&nbsp;&nbsp;repeat from&nbsp;<input id="ed'+rst_ID+
					'_rst_MinValues" title="Min Values" style="width:20" size="2" '+
					'onblur="onRepeatValueChange(event)" onkeypress="Hul.validate(event)"/>&nbsp;'+
					'&nbsp;to&nbsp;<input id="ed'+rst_ID+
					'_rst_MaxValues" title="Max Values"  style="width:20" size="2"  '+
					'onblur="onRepeatValueChange(event)" onkeypress="Hul.validate(event)"/>&nbsp;times</div>'+

					'<div class="dtyField"><label class="dtyLabel">Terms list:</label>'+
					'<input id="ed'+rst_ID+'_rst_FilteredJsonTermIDTree" type="hidden"/>'+
					'<input id="ed'+rst_ID+'_rst_TermIDTreeNonSelectableIDs" type="hidden"/>'+
					'<input type="button" value="Filter terms" id="btnSelTerms" onclick="showTermsTree('+rst_ID+', event)"/>'+
					'<div id="termsPreview" class="dtyValue"></div>'+
					'</div>'+

					'<div class="dtyField"><label class="dtyLabel">Rectype pointer:</label>'+
					'<div id="pointerPreview" class="dtyValue"></div>'+
					'<input id="ed'+rst_ID+'_rst_PtrFilteredIDs" type="hidden"/>'+
					'<input type="button" value="Filter pointers" id="btnSelTerms" onclick="showPointerFilter('+rst_ID+', event)"/></div>'+

					'<div class="dtyField"><label class="dtyLabel">Status:</label><select id="ed'+rst_ID+
					'_rst_Status" style="display:inline-block">'+
					'<option value="reserved">reserved</option>'+
					'<option value="approved">approved</option>'+
					'<option value="pending">pending</option>'+
					'<option value="open">open</option></select>'+

					'<div style="float:right; text-align:right;min-width:250;">'+
					'<input id="btnSave_'+rst_ID+
					'" style="display:inline-block" type="button" value="Save" onclick="doExpliciteCollapse(event);" />'+
					'<input id="btnCancel_'+rst_ID+
					'" type="button" value="Cancel" onclick="doExpliciteCollapse(event);" />'+
					'</div></div></div>';
				}
			}
			);

			// highlight listeners
			_myDataTable.subscribe("rowMouseoverEvent", _myDataTable.onEventHighlightRow);
			_myDataTable.subscribe("rowMouseoutEvent", _myDataTable.onEventUnhighlightRow);
			_myDataTable.subscribe("rowClickEvent", _myDataTable.onEventSelectRow);

			//
			// Subscribe to a click event to bind to expand/collapse the row
			//
			_myDataTable.subscribe( 'cellClickEvent', function(oArgs)
			{

				var column = this.getColumn(oArgs.target);

				//prevent any operation in case of opened popup
				if(!Hul.isnull(popupSelect) || _isServerOperationInProgress ||
				(!Hul.isnull(column) && column.key === 'rst_values'))
				{ return; }



				var record_id;
				var oRecord;
				if(Dom.hasClass( oArgs.target, 'yui-dt-expandablerow-trigger' )){
					record_id = oArgs.target;
					oRecord = this.getRecord(record_id);
				}else{
					oRecord = this.getRecord(oArgs.target);
					record_id = _myDataTable.getTdEl({record:oRecord, column:_myDataTable.getColumn("expandColumn")});
				}

				// after expantion - fill input values from HEURIST db
				// after collapse - save data on server
				function __toggle(){

					if(!isExpanded){ //now it is expanded
						_myDataTable.onEventToggleRowExpansion(record_id);
						_expandedRecord = rst_ID;
						_fromArrayToUI(rst_ID, false); //after expand restore values from HEURIST
					}else{
						_saveUpdates(false); //save on server
					}
				}

				if(!Hul.isnull(record_id)){
					oRecord = this.getRecord(record_id);
					var rst_ID = oRecord.getData("rst_ID");

					var state = this._getRecordState( record_id );
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

			} );


			//
			// Subscribe to a click event on delete image
			//
			_myDataTable.subscribe('linkClickEvent', function(oArgs){

				if(!Hul.isnull(popupSelect) || _isServerOperationInProgress) { return; }

				YAHOO.util.Event.stopEvent(oArgs.event);

				if(!Hul.isnull(_updatedFields) && _updatedFields.indexOf(9)>=0){ //order was changed
					alert("You have to save your changes in view order first");
					return;
				}

				var elLink = oArgs.target;
				var oRecord = this.getRecord(elLink);
				var dty_ID = oRecord.getData("rst_ID");

				// result listener for delete operation
				function __updateAfterDelete(context) {

					if(!context){
						alert("Unknown error on server side");
					}else if(Hul.isnull(context.error)){

						_myDataTable.deleteRow(oRecord.getId(), -1);

						alert("Field type #"+dty_ID+" was deleted from record #"+rty_ID);
						top.HEURIST.rectypes = context.rectypes;
					}
					//else {
					// if error is property of context it will be shown by getJsonData
					// alert("Deletion failed. "+context.error);
					//}
					_isServerOperationInProgress = false;
				}

				if(elLink.hash === "#delete"){
					var rst_values = oRecord.getData('rst_values');
					var r=confirm("Delete detail #"+dty_ID+" '"+rst_values[0]+"' from this record structure?");
					if (r) {

						_doExpliciteCollapse(null ,false); //force collapse this row

						var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
											(top.HEURIST.database.name?top.HEURIST.database.name:''));
						var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
						var callback = __updateAfterDelete;
						var params = "method=deleteRTS&db="+db+"&rtyID="+rty_ID+"&dtyID="+dty_ID;
						_isServerOperationInProgress = true;
						Hul.getJsonData(baseurl, callback, params);


					}
				}
			});


			_myDataTable.subscribe("initEvent", function() {_setDragEnabled(true);});

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

	/**
	* Shows all our chanages on preview tab
	*
	* It utilizes methods from HEURIST.edit
	*
	* 1) Saves all changes if some row (detail type) is expanded
	* 2) Loads all changes top.HEURIST.rectypes.typedefs[rty_ID].dtFields
	* 3) Creates inputs with createInputsForRectype
	*/
	function _initTabPreview(){

		//save all changes
		_doExpliciteCollapse(null, true);


			top.HEURIST.edit.allInputs = [];
			//top.HEURIST.edit.inputs = {};
			top.HEURIST.edit.modules = {};

			top.HEURIST.edit.record = {bdValuesByType:{},
					bibID: null,
					bkmkID: null,
					comments:[],
					isTemporary:[],
					moddate:"",
					quickNotes:"",
					recID: "0",
					rectype: "", //name of rectype
					rectypeID: rty_ID,
					relatedRecords:[],
					reminders:[],
					retrieved:"",
					rtConstraints:[],
					tagString:"",
					title:"kala mala",
					url:"",
					wikis:[],
					workgroup:"Heurist",
					workgroupID: "12",
					workgroupTags:[]

		};


		//loadPublicEditFrame();
		//setTimeout(renderInputs, 500);
		renderInputs();
	}

function renderInputs() {

	var rectype = rty_ID,
		inputs;
	// Clear out any existing inputs

	var allInputs = document.getElementById("all-inputs");
	while (allInputs.childNodes.length > 0){
		allInputs.removeChild(allInputs.childNodes[0]);
	}

	var showAllDiv = document.getElementById("show-all-div");
	if (showAllDiv){
		showAllDiv.parentNode.removeChild(showAllDiv);
	}

	var innerDims = Hul.innerDimensions(window);

	if (rectype) {

		var defaultInputValues = {};

		/*if(!window.HEURIST.edit){
			window.HEURIST.edit = top.HEURIST.edit;
		}*/

		inputs = top.HEURIST.edit.createInputsForRectype(rectype, defaultInputValues, allInputs);

		renderShowAll();

		//renderAdditionalDataSection(allInputs, rectype);
		//window.HEURIST.inputs = inputs;
	}

}

function renderShowAll() {
	var hrRow = document.getElementById("all-inputs").appendChild(document.createElement("div"));
		hrRow.className = "separator_row";
		hrRow.style.margin = "20px 0 0 0";

	var showDiv = document.getElementById("all-inputs").appendChild(document.createElement("div"));
		showDiv.id = "show-all-div";
		showDiv.className = "title-row not-optional-fields";

	var showLink = showDiv.appendChild(document.createElement("div")).appendChild(document.createElement("a"));
		showLink.className = "additional-header-cell not-optional-fields";
		showLink.style.fontWeight = "bold";
		showLink.href = "#";
		showLink.appendChild(document.createTextNode("Show all fields"));
		showLink.onclick = function() {
			Hul.setDisplayPreference("input-visibility", "all");
			//Dom.get("input-visibility").checked = true;
			return false;
		};

	var showInnerDiv = showDiv.appendChild(document.createElement("div"));
		showInnerDiv.appendChild(document.createTextNode("Only "));
		showInnerDiv.className = "input-cell";
	var showInnerSpan = showInnerDiv.appendChild(document.createElement("span"));
		showInnerSpan.className = "required-only";
		showInnerSpan.style.fontWeight = "bold";
		showInnerSpan.appendChild(document.createTextNode("required"));
		showInnerSpan = showInnerDiv.appendChild(document.createElement("span"));
		showInnerSpan.className = "recommended";
		showInnerSpan.style.fontWeight = "bold";
		showInnerSpan.appendChild(document.createTextNode("recommended"));
		showInnerDiv.appendChild(document.createTextNode(" fields are currently visible"));
}


	/*function loadPublicEditFrame() {
			//fills top.HEURRIST.edit.record

			if (! top.HEURIST.edit.record) {
					alert("Sorry - record not found");
			}else{
				var editFrame = document.getElementById("edit-frame");

				var parameters = top.HEURIST.edit.record;
				//if (parameters  &&  parameters.bibID) {
					editFrame.src = top.HEURIST.baseURL + "records/edit/tabs/publicInfoTab.html";
					editFrame.style.display = "block";
				//}
			}
	}*/

	/**
	* Collapses the expanded row and save record structure type to server
	* @see _saveUpdates
	*
	* @param rst_ID  record structure type ID, if it is null it means that row is already collapsed and we take
	*				rstID from the last expanded row - from _expandedRecord. In this case it performs the saving only
	* @param needSave  whether to save data on server, it is false for collapse on delete only
	*/
	function _doExpliciteCollapse(rst_ID, needSave){

		if(Hul.isnull(rst_ID)){ //when user open select and new field type popup we have to save all changes
			rst_ID = _expandedRecord;
			if(Hul.isnull(rst_ID)) {
				if(!Hul.isnull(_updatedFields) && _updatedFields.indexOf(9)>=0 && needSave){ //order was changed
					_saveUpdates(false); //global function
				}
				return;
			}
		}

		var oRecord = _getRecordById(rst_ID).record;
		var record_id = _myDataTable.getTdEl({record:oRecord, column:_myDataTable.getColumn("expandColumn")});
		if(!Hul.isnull(record_id)){
			if(needSave){
				_fromUItoArray(rst_ID); //before collapse save from UI to HEURIST
			}
			_setDragEnabled(true);
			_myDataTable.onEventToggleRowExpansion(record_id); //collapse row

			_expandedRecord = null;

			if(needSave){
				_saveUpdates(false); //global function
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
			if(rec.getData('rst_ID') === rst_ID){
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

					if(values[k] !== edt.value){
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
	* Restores values from HEURSIT db structure to input controls after expand the row
	* @param _rst_ID record structure type ID
	* @param isAll - not used (false always)
	*/
	function _fromArrayToUI(rst_ID, isAll)
	{
		var fieldnames = top.HEURIST.rectypes.typedefs.dtFieldNames;
		var values = top.HEURIST.rectypes.typedefs[rty_ID].dtFields[rst_ID];
		var rst_type = top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[2];

		var k;
		for(k=0; k<fieldnames.length; k++){
			var ed_name = 'ed'+rst_ID+'_'+fieldnames[k];
			var edt = Dom.get(ed_name);
			if( !Hul.isnull(edt) && (isAll || edt.parentNode.id.indexOf("row")<0)){
				edt.value = values[k];

			if(rst_type === "relmarker" && fieldnames[k] === "rst_DefaultValue"){
					//hide defaulvalue
					edt.parentNode.style.display = "none";
					//show disable jsontree
			}else if(fieldnames[k] === "rst_TermIDTreeNonSelectableIDs"){
				if(rst_type === "enum" || rst_type === "relmarker" || rst_type === "relationtype"){
					//show filter jsontree
					edt.parentNode.style.display = "block";

					var edt2 = Dom.get('ed'+rst_ID+'_rst_FilteredJsonTermIDTree');

					recreateTermsPreviewSelector(rst_type,
					(Hul.isempty(edt2.value)?top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[9]:edt2.value),   //dty_JsonTermIDTree
					(Hul.isempty(edt.value)?top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[10]:edt.value)); //dty_TermIDTreeNonSelectableIDs

					//editedTermTree, editedDisabledTerms);

				}else{
					edt.parentNode.style.display = "none";
				}

			}else if(fieldnames[k] === "rst_PtrFilteredIDs"){
				if(rst_type === "relmarker" || rst_type === "resource"){
					//show filter jsontree
					edt.parentNode.style.display = "block";

					recreateRecTypesPreview(rst_type,
					(Hul.isempty(edt.value)?top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[11]:edt.value) ); //dty_PtrTargetRectypeIDs
				}else{
					edt.parentNode.style.display = "none";
				}

			}else if(rst_type === "relationtype"){

			}else if(rst_type === "resource"){
					//show disable target pnr rectype

			}else if(rst_type === "separator"  &&
				!(fieldnames[k] === "rst_DisplayName" || fieldnames[k] === "rst_DisplayWidth")){
					//hide all but width
					edt.parentNode.style.display = "none";
			}else if(rst_type === "fieldsetmarker" && !(fieldnames[k] === "rst_DisplayName" || fieldnames[k] === "rst_Status")){
					//hide all, required - once
					edt.parentNode.style.display = "none";
			}
			}
		}//for

	}


	/**
	* Adds the list of new detail types to this record structure
	*
	* After addition it saves all on server side
	* This is function for global method addDetail. It is invoked after selection of detail types or creation of new one
	* @param dty_ID_list - comma separated list of detail type IDs
	*/
	function _addDetails(dty_ID_list){

		var arrDty_ID = dty_ID_list.split(",");
		if(arrDty_ID.length<1) {
			return;
		}

		var recDetTypes = top.HEURIST.rectypes.typedefs[rty_ID].dtFields;

		//new odetail type
		if(Hul.isnull(recDetTypes)){
			recDetTypes = {}; //new Object();
		}

		var data_toadd = [];
		var detTypes = top.HEURIST.detailTypes.typedefs;

		//find max order and index to insert
		var recs = _myDataTable.getRecordSet();
		var index_toinsert = recs.getLength()-1;
		var order = 0;
		if(index_toinsert<0){
			index_toinsert = 0;
		}else{
			var rec = _myDataTable.getRecord(index_toinsert);
			order = Number(rec.getData('rst_DisplayOrder')) + 1;
		}

		//moves detail types to
		var k;
		for(k=0; k<arrDty_ID.length; k++){
			var dty_ID = arrDty_ID[k];
			if(Hul.isnull(recDetTypes[dty_ID])){
				var arrs = detTypes[dty_ID].commonFields;
				//add new detail type
				// 0		1		2	   3	4		 5   6   7    8?    9  10?
				var arr_target = [arrs[0],arrs[4],arrs[1],"","optional","1","0","60","0",order,"1",
				null, null, null, null, "open", null, null, null];
				// 11	12    13    14     15	 16	    17    18

				recDetTypes[dty_ID] = arr_target;

				data_toadd.push({rst_ID:dty_ID,
					expandColumn:dty_ID,
					rst_DisplayOrder: order,
					rst_DisplayName: arrs[0],
					dty_Type: arrs[2],
					rst_RequirementType: "optional",
					rst_MinValues: 1,
					rst_MaxValues: 1,
					rst_DefaultValue: "",
					rst_DisplayWidth: 60,
					rst_Status: "open",
					rst_values: arr_target });

				_updatedDetails.push(dty_ID); //track change

				order++;
			}
		}//end for

		if(data_toadd.length>0){
			top.HEURIST.rectypes.typedefs[rty_ID].dtFields = recDetTypes;

			_myDataTable.addRows(data_toadd, index_toinsert);

			// in case of addition - all fields were affected
			_updatedFields = null;

			_saveUpdates(false);
		}

		/*
		0"dty_Name"
		1"dty_ExtendedDescription"
		2"dty_Type"
		3"dty_OrderInGroup"
		4"dty_HelpText"
		5"dty_ShowInLists"
		6"dty_Status"
		7"dty_DetailTypeGroupID"
		8"dty_FieldSetRecTypeID"
		9"dty_JsonTermIDTree"
		10"dty_TermIDTreeNonSelectableIDs"
		11"dty_PtrTargetRectypeIDs"
		12"dty_ID"
		*/

	}//end _addDetails

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

		if(!Hul.isnull(str)){
			//DEBUG  alert(str);
			var updateResult = function(context){
				if(context){
					top.HEURIST.rectypes = context.rectypes;
				}else{
					alert("Unknown error on server side");
				}
				_isServerOperationInProgress = false;
			};
//DEBUG alert(str);
			var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
								(top.HEURIST.database.name?top.HEURIST.database.name:''));
			var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
			var callback = updateResult;
			var params = "method=saveRTS&db="+db+"&data=" + encodeURIComponent(str);
			_isServerOperationInProgress = true;
			Hul.getJsonData(baseurl, callback, params);

			if(needClose){
				window.close();
			}
		}
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
	}
	//////////////////////////////////////////////////////////////////////////////
	// Diable drag and drop class
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
				top.HEURIST.rectypes.typedefs[rty_ID].dtFields[data.rst_ID][9] = i;
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
			if(!Hul.isnull(_updatedFields) && _updatedFields.indexOf(9)<0){
				_updatedFields.push(9);
			}
			top.HEURIST.rectypes.dtDisplayOrder[rty_ID] = neworder;

			dragDropDisable();
			dragDropEnable();
			//					DDM.refreshCache();

			var btnSaveOrder = YAHOO.util.Dom.get('btnSaveOrder');
			btnSaveOrder.style.display = "inline-block";
		}
	}



	//////////////////////////////////////////////////////////////////////////////
	// Custom drag and drop class
	//////////////////////////////////////////////////////////////////////////////
	YAHOO.example.DDRows = function(id, sGroup, config) {
		YAHOO.example.DDRows.superclass.constructor.call(this, id, sGroup, config);
		Dom.addClass(this.getDragEl(),"custom-class");
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

			var rec = _myDataTable.getRecord(this.srcEl);
			if(Hul.isnull(rec)) { return; }
			this.srcData = rec.getData();
			this.srcIndex = srcEl.sectionRowIndex;
			// Make the proxy look like the source element
			Dom.setStyle(srcEl, "visibility", "hidden");
			//proxyEl.innerHTML = "<table><tbody>"+srcEl.innerHTML+"</tbody></table>";
			proxyEl.innerHTML = "<div>dragging detail to new place</div>";

			//var rst_ID = this.srcData.rst_ID
			//_fromUItoArray(rst_ID); //before collapse save to UI

		},

		endDrag: function(x,y) {
			var position,
			srcEl = this.srcEl;

			proxyEl.innerHTML = "";
			Dom.setStyle(this.proxyEl, "visibility", "hidden");
			Dom.setStyle(srcEl, "visibility", "");

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

					//_updateOrderAfterDrag();

					DDM.refreshCache();
				}
			}
		}
	});

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
		addDetails:function(dty_ID_list){
			_addDetails(dty_ID_list);
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
function onAddNewDetail(){

	if(Hul.isnull(popupSelect))
	{

		editStructure.doExpliciteCollapse(null, true);

		//var pos = this.window.offset();
		// x: pos.left+$(window).width(),
		// y: pos.top,

		var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
							(top.HEURIST.database.name?top.HEURIST.database.name:''));
		popupSelect = Hul.popupURL(top, top.HEURIST.basePath +
		"admin/structure/selectDetailType.html?rty_ID="+editStructure.getRty_ID()+"&db="+db,
		{	"close-on-blur": false,
			"no-resize": false,
			height: 480,
			width: 440,
			callback: function(detailTypesToBeAdded) {
				if(!Hul.isnull(detailTypesToBeAdded)){
					editStructure.addDetails(detailTypesToBeAdded);
				}
				popupSelect = null;
			}
		});

		//alert("111");

	}
}

/**
* Invokes popup window to create and add new field type
*/
function onDefineNewType(){

	if(Hul.isnull(popupSelect))
	{
		editStructure.doExpliciteCollapse(null, true);

		var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
							(top.HEURIST.database.name?top.HEURIST.database.name:''));
		var url = top.HEURIST.basePath + "admin/structure/editDetailType.html?db="+db;

		popupSelect = Hul.popupURL(top, url,
		{	"close-on-blur": false,
			"no-resize": false,
			height: 430,

			width: 600,
			callback: function(context) {

				if(!Hul.isnull(context)){
					//refresh the local heurist
					top.HEURIST.detailTypes = context.detailTypes;

					//new field type to be added
					var dty_ID = Math.abs(Number(context.result[0]));
					editStructure.addDetails(String(dty_ID));
				}

				popupSelect =  null;
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
* Listener of requirement type selector (combobox)
*/
function onReqtypeChange(evt){
	var el = evt.target;
	var name = el.id.substring(0,el.id.indexOf("_")); //. _rst_RequirementType

	var el_min = Dom.get(name+"_rst_MinValues");
	var el_max = Dom.get(name+"_rst_MaxValues");

	if(el.value === "required"){
		el_min.value = 1;
		el_max.value = 1;
	} else if(el.value === "recommended"){
		el_min.value = 0;
		el_max.value = 1;
	} else if(el.value === "optional"){
		el_min.value = 0;
		el_max.value = 1;
	} else if(el.value === "forbidden"){
		el_min.value = 0;
		el_max.value = 0;
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

/**
* Hides div with json tree filter
*/
function closeDivPopup1(_allTerms, _disTerms, _dtyID){

		if(!Hul.isnull(_dtyID)){
			//assign new values to inputs
			var edt1 = Dom.get('ed'+_dtyID+'_rst_FilteredJsonTermIDTree');
			var edt2 = Dom.get('ed'+_dtyID+'_rst_TermIDTreeNonSelectableIDs');
			edt1.value = _allTerms;
			edt2.value = _disTerms;

			var rst_type = top.HEURIST.detailTypes.typedefs[_dtyID].commonFields[2];

			recreateTermsPreviewSelector( rst_type, edt1.value, edt2.value );
		}

		var my_div = $("#termsFilter");
		my_div.css( {
			left:"-9999px"
		});
}

/**
* Shows div on top with terms filter
*/
function showTermsTree(rst_ID, event){

	if(Hul.isnull(selectTerms)){
		selectTerms = new  SelectTerms(true, false); //filtered mode, in div
	}else{
		selectTerms.setFilterMode(true);
	}
	selectTerms.reinit(rst_ID, closeDivPopup1);

	var border_top = $(window).scrollTop();

	var my_div = $("#termsFilter");

	my_div.css( {
			left:'5px', top:(border_top+5)+'px', width:'97%', height:'95%'
			//width:$(window).width()-20, height:$(window).height()-20
	});

	//_showDivPopupAt(my_div, [0, border_top]);
}

/**
* Hides div with rectype pointer filter
*/
function closeDivPopup2(_values, _dtyID){

		if(!Hul.isnull(_dtyID)){
			//assign new values to inputs
			var edt = Dom.get('ed'+_dtyID+'_rst_PtrFilteredIDs');
			edt.value = _values;

			var rst_type = top.HEURIST.detailTypes.typedefs[_dtyID].commonFields[2];

			recreateRecTypesPreview( rst_type, edt.value );
		}

		var my_div = $("#pointerFilter");
		my_div.css( {
			left:"-9999px"
		});
}

/**
* Shows popup div with rectype pointer filter
*/
function showPointerFilter(rst_ID, event){

	if(Hul.isnull(selectRecordType)){
		selectRecordType = new  SelectRecordType(true, false); //filtered mode, in div
	}else{
		selectRecordType.setFilterMode(true);
	}
	selectRecordType.reinit(rst_ID, closeDivPopup2);

	var border_top = $(window).scrollTop();

	var my_div = $("#pointerFilter");

	my_div.css( {
			left:'5px', top:(border_top+5)+'px', width:'97%', height:'95%'
			//width:$(window).width()-20, height:$(window).height()-20
	});

	//_showDivPopupAt(my_div, [0, border_top]);
}



// DEBUG
//temp function to fill values with given rty_ID
/*
function _tempFillValue(){
editStructure.initTabDesign(document.getElementById("ed_rty_ID").value);
}
*/

/** REMOVE
* prevents out of border for popup div
* @todo move to common util
*/
	function _showDivPopupAt(_tooltip, xy){

		var border_top = $(window).scrollTop();
		var border_right = $(window).width();
		var border_height = $(window).height();
		var left_pos;
		var top_pos;
		var offset = 5;
		if(border_right - (offset *2) >= _tooltip.width() +  xy[0]) {
			left_pos = xy[0]+offset;
		} else {
			left_pos = border_right-_tooltip.width()-offset;
		}

		if((border_top + offset *2) >=  xy[1] - _tooltip.height()) {
			top_pos = border_top + offset + xy[1]; //
		} else {
			top_pos = border_top + xy[1] - _tooltip.height()-offset;
		}
		if(top_pos + _tooltip.height() > border_top+border_height){
			top_pos	= border_top + border_height - _tooltip.height()-5;
		}

		//var lft = _tooltip.css('left');
		_tooltip.css( {
			left:left_pos+'px', top:top_pos+'px'
		});

	}


function _preventSel(event){
		event.target.selectedIndex=0;
}
/**
* recreateTermsPreviewSelector
* creates and fills selector for Terms Tree if datatype is enum, relmarker, relationtype
* @param datatype an datatype
* @allTerms - JSON string with terms
* @disabledTerms  - JSON string with disabled terms
*/
function recreateTermsPreviewSelector(datatype, allTerms, disabledTerms ) {

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

					var parent = document.getElementById("termsPreview"),
						i;
					for (i = 0; i < parent.children.length; i++) {
						parent.removeChild(parent.childNodes[0]);
					}

					// add new select (combobox)
					if(datatype === "enum") {
						el_sel = Hul.createTermSelect(allTerms, disabledTerms, top.HEURIST.terms.termsByDomainLookup['enum'], null);
						el_sel.style.backgroundColor = "#cccccc";
						el_sel.onchange =  _preventSel;
						parent.appendChild(el_sel);
					}
					else if(datatype === "relmarker" || datatype === "relationtype") {
						el_sel = Hul.createTermSelect(allTerms, disabledTerms, top.HEURIST.terms.termsByDomainLookup.relation, null);
						el_sel.style.backgroundColor = "#cccccc";
						el_sel.onchange =  _preventSel;
						parent.appendChild(el_sel);
					}
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

		if (txt.length > 40){
			divRecType.title = txt;
			txt = txt.substr(0,40) + "&#8230";
		}else{
			divRecType.title = "";
		}
		divRecType.innerHTML = txt;
}