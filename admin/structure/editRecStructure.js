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
	rty_ID,
	_isDragEnabled = false,
	_updatedDetails = [], //list of dty_ID that were affected with edition
	_updatedFields = [],  //list of indexes in fieldname array that were affected
	_expandedRecord = null, //rstID of record (row) in design datatable that is (was) expanded (edited)
	_isServerOperationInProgress = false, //prevents send request if there is not respoce from previous one
	_isReserved = false,
	_rty_Status,
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
			var recTypeIcon  = top.HEURIST.iconBaseURL+rty_ID+".png";
			var formTitle = document.getElementById('recordTitle');
			formTitle.innerHTML = "<div class=\"rectypeIconHolder\" style=\"background-image:url("+recTypeIcon+")\"></div><span class=\"recTypeName\">"+top.HEURIST.rectypes.names[rty_ID]+"</span>";
		}

		// buttons on top and bottom of design tab
		var hToolBar = '<div style=\"text-align:right;float:right;\">'+
		//<div style="display:inline-block; text-align:left">
		//'<input type="button" value="collapse all" onclick="onCollapseAll()"/>'+
		//'<input type="button" value="Enable Drag" onclick="onToggleDrag(event)"/></div>'+
		'<input style="display:none override;color:red;visibility:hidden;" type="button" id="btnSaveOrder" value="Save order" onclick="onUpdateStructureOnServer(false)"/>'+
		'<input type="button" style="margin:0" value="Insert heading" onClick="onAddSeparator()" class="add"/>'+
        '<input type="button" class="add" value="Insert field" onclick="onAddNewDetail()"/>'+
		// note class=add --> global.css add-button, is set to float:right, but class adds the + to the button
		// Removed Ian 8/10/12, moved to within insertion of existing fields to encourge re-use
        // '<input type="button" style="margin:0 5px" value="Define New Field Type" onClick="onDefineNewType()" class="add"/>'+
		// '<input type="button" value="Done" onclick="onUpdateStructureOnServer(true)"/>'+
		'Use existing fields where possible &nbsp;&nbsp;'+
        '</div>';

		Dom.get("recordTitle").innerHTML += hToolBar;
	  	Dom.get("modelTabs").innerHTML =  '<div id="tabDesign"><div id="tableContainer"></div></div>';
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
				alert("Record type ID "+rty_ID+" is not exist");
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

					arr.push([ rst_ID,
						rst_ID,
						Number(aval[fi.rst_DisplayOrder]),
						top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[top.HEURIST.detailTypes.typedefs.fieldNamesToIndex.dty_Name], //field name
						aval[fi.rst_DisplayName],
						top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[top.HEURIST.detailTypes.typedefs.fieldNamesToIndex.dty_Type], //field type
						aval[fi.rst_RequirementType],
						aval[fi.rst_DisplayWidth],
						aval[fi.rst_MinValues],
						aval[fi.rst_MaxValues],
						aval[fi.rst_DefaultValue],
						aval[fi.rst_Status],
						aval[fi.rst_NonOwnerVisibility],
						'']);
					//statusLock]);   last column stores edited values and show either delete or lock image
				}
			}

			// define datasource for datatable
			var myDataSource = new YAHOO.util.LocalDataSource(arr,{
				responseType : YAHOO.util.DataSource.TYPE_JSARRAY,
				responseSchema : {
					fields: [  "rst_ID","expandColumn","rst_DisplayOrder",
					"dty_Name",
					"rst_DisplayName", "dty_Type", "rst_RequirementType",
					"rst_DisplayWidth", "rst_MinValues", "rst_MaxValues", "rst_DefaultValue", "rst_Status",
					"rst_NonOwnerVisibility", "rst_values"]
				}
			});

			function _hidevalueforseparator(elLiner, oRecord, oColumn, oData){
					var type = oRecord.getData("dty_Type");
					if(type!=='separator'){
						elLiner.innerHTML = oData;
					}
			}


			var myColumnDefs = [
			{
				key:'rst_NonOwnerVisibility',
				label: "<img src='../../common/images/blue-up-down-triangle.png' title='Drag to change order'>",
				sortable:false, width:10,
				formatter: function(elLiner, oRecord, oColumn, oData) {
					if(_expandedRecord != oRecord.getData("rst_ID")){
						elLiner.innerHTML = "<img src='../../common/images/blue-up-down-triangle.png'>"
					}
				}
			},
			{
				key:"rst_ID", label: "Code", sortable:false, className:"center"
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
				key:"rst_DisplayName", label: "Field prompt in form", width:120, sortable:false },
			{ key: "dty_Type", label: "Data Type", sortable:false, width:90,
				formatter: function(elLiner, oRecord, oColumn, oData) {
					var type = oRecord.getData("dty_Type");
					if(type!=='separator'){
						elLiner.innerHTML = top.HEURIST.detailTypes.lookups[type];
					}
				}
			},
			{
				key:"rst_DisplayWidth", label: "Width", sortable:false, width:25, className:"center" ,
                formatter: function(elLiner, oRecord, oColumn, oData){
                    var wid = oRecord.getData('rst_DisplayWidth');
                    var typ = oRecord.getData('dty_Type');
                    if (_isNoWidth(typ))
                    {
                        res = "";
                    }
                    else{
                        res = wid;
					}


                    elLiner.innerHTML = res;
                }
			},
			//{ key:"rst_DisplayHelpText", label: "Prompt", sortable:false },
			{
				key:"rst_RequirementType", label: "Requirement", sortable:false,
				formatter: _hidevalueforseparator
			},
			{
				key:"rst_MinValues", label: "Min", hidden:true
			},
			{
				key:"rst_MaxValues", label: "Repeatability", sortable:false,
				formatter: function(elLiner, oRecord, oColumn, oData){
					var type = oRecord.getData("dty_Type");
					if(type!=='separator'){
						var minval = oRecord.getData('rst_MinValues');
						var maxval = oRecord.getData('rst_MaxValues');
						var res = 'repeatable';
						if(Number(maxval)===1){
							res = 'single value';
						}else if(Number(maxval)>1){
							res = 'limit '+maxval;
						}
						elLiner.innerHTML = res;
					}
				}

			},
			{
				key:"rst_DefaultValue", label: "Default", sortable:false,className:"center",
				formatter: function(elLiner, oRecord, oColumn, oData){
					var reqtype = oRecord.getData('rst_DefaultValue');
					if (reqtype && reqtype.length > 0){
						elLiner.innerHTML = reqtype.substring(0,9);// Artem, why do we do this??
					}
				}
			},
            {
                key:"dty_Name", label: "Based on template", width:100, sortable:false,
				formatter: _hidevalueforseparator
            },
			{
				key:"rst_Status", label: "Status", sortable:false, className:"center",
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
							statusLock  = '<img src="../../common/images/lock_bw.png" title="Detail locked" />';
						}else{
							statusLock = '<a href="#delete"><img src="../../common/images/cross.png" width="12" height="12" border="0" title="Remove detail" /><\/a>';
						};
						elLiner.innerHTML = statusLock;
				}
			}
			];

			//special formatter
			var myRowFormatter = function(elTr, oRecord) {
				var val0 = oRecord.getData('dty_Type');
				if(val0 == 'separator'){
					Dom.addClass(elTr, 'greyout');
				}else{
					var val1 = oRecord.getData('rst_NonOwnerVisibility');
					var val2 = oRecord.getData('rst_RequirementType');
					if (val1==='hidden' || val2==='forbidden') {
						Dom.addClass(elTr, 'gray');
					}else{
						Dom.removeClass(elTr, 'gray');
					}
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
					/*
					THIS IS FORM to edit detail structure. It is located on expandable row of table
					*/
					obj.liner_element.innerHTML =
					'<div style="padding-left:30; padding-bottom:5; padding-right:5">'+

                    '<div class="input-row"><div class="input-header-cell">Prompt (display name):</div><div class="input-cell"><input id="ed'+rst_ID+'_rst_DisplayName" title="Display Name/Label"/></div></div>'+

                    // Field width
                    '<div class="input-row"><div class="input-header-cell">Field width:</div><div class="input-cell"><input id="ed'+rst_ID+'_rst_DisplayWidth" title="Visible width of field" style="width:40" size="4" onkeypress="Hul.validate(event)"/></div></div>'+

					'<div class="input-row"><div class="input-header-cell">Help text (under field):</div><div class="input-cell"><input id="ed'+rst_ID+'_rst_DisplayHelpText" style="width:350px" title="Help Text"/></div></div>'+

					// Required/recommended optional
                    '<div class="input-row"><div class="input-header-cell">Requirement:</div>'+
					'<div class="input-cell">'+
					'<select id="ed'+rst_ID+'_rst_RequirementType" onchange="onReqtypeChange(event)" style="display:inline; margin-right:0px">'+
					'<option value="required">required</option>'+
					'<option value="recommended">recommended</option>'+
					'<option value="optional">optional</option>'+
					'<option value="forbidden">forbidden</option></select>'+
					'<span id="ed'+rst_ID+'_spanMinValue" style="visibility:hidden;"><label class="input-header-cell">Minimum&nbsp;values:</label>'+
					'<input id="ed'+rst_ID+
					// Minimum values
                    '_rst_MinValues" title="Min Values" style="width:20px" size="2" '+
					'onblur="onRepeatValueChange(event)" onkeypress="Hul.validate(event)"/></span></div></div>'+

					// Repeatability
                    '<div class="input-row" id="divRepeatability"><div class="input-header-cell">Repeatability :</div>'+
					'<div class="input-cell">'+
					'<select id="ed'+rst_ID+'_Repeatability" onchange="onRepeatChange(event)">'+
					'<option value="single">single</option>'+
					'<option value="repeatable">repeatable</option>'+
					'<option value="limited">limited</option>'+ //IJ request HIDE IT 2012-10-12
					'</select>'+
					// Maximum values
                    '<span id="ed'+rst_ID+'_spanMaxValue"><label class="input-header-cell">Maximum&nbsp;values:</label>'+
					'<input id="ed'+rst_ID+
					'_rst_MaxValues" title="Maximum Values" style="width:20px; text-align:center;" size="2" '+
					'onblur="onRepeatValueChange(event)" onkeypress="Hul.validate(event)"/></span></div></div>'+

					// Terms - enums and relmarkers
                    '<div class="input-row"><div class="input-header-cell">Terms list:</div>'+
					'<div class="input-cell">'+
					'<input id="ed'+rst_ID+'_rst_FilteredJsonTermIDTree" type="hidden"/>'+
					'<input id="ed'+rst_ID+'_rst_TermIDTreeNonSelectableIDs" type="hidden"/>'+
                    //REMOVED BY IAN's request on 16-09-11 - this provides too much complexity
                    //'<input type="submit" value="Filter terms" id="btnSelTerms" onclick="showTermsTree('+rst_ID+', event)" style="margin:0 20px 0 0"/>'+
                    //					'Preview:'+
					'<span class="input-cell" id="termsPreview" class="dtyValue"></span>'+
					'<span class="input-cell" style="margin:0 10px">to change click "Edit Field Type" and then "Change Vocabulary"</span>'+
					'</div></div>'+

					// Pointer target types - pointers and relmarkers
                    '<div class="input-row"><div class="input-header-cell">Can point to:</div>'+
					'<div id="pointerPreview" class="input-cell">'+
					'<input id="ed'+rst_ID+'_rst_PtrFilteredIDs" type="hidden"/>'+
                    //REMOVED BY IAN's request on 16-09	- too much complexity
                    // '<input value="Filter pointers" id="btnSelTerms" onclick="showPointerFilter('+rst_ID+', event)">'+
					'</div></div>'+


                    // Default value
                    '<div class="input-row"><div class="input-header-cell">Default Value:</div><div class="input-cell">'+
                    '<span class="input-cell" id="termsDefault" name="def'+rst_ID+'_rst_DefaultValue" class="dtyValue"></span>'+
                    '<input id="ed'+rst_ID+'_rst_DefaultValue" title="Default Value"/></div></div>'+

					// Status
                    '<div class="input-row"><div class="input-header-cell">Status:</div>'+
					'<div class="input-cell"><select id="ed'+rst_ID+
					'_rst_Status" style="display:inline-block" onchange="onStatusChange(event)">'+
					'<option value="open">open</option>'+
					'<option value="pending">pending</option>'+
					'<option value="approved">approved</option>'+
					'</select>'+  //<option value="reserved">reserved</option>

					// Non-owner visibility
                    '<span><label class="input-header-cell">Non owner visibility:</label><select id="ed'+rst_ID+
					'_rst_NonOwnerVisibility">'+  // style="display:inline-block"
					'<option value="hidden">hidden</option>'+
					'<option value="viewable">viewable</option>'+
					'<option value="public">public</option>'+
					'<option value="pending">pending</option></select></span>'+
					'</div></div>'+

					'<div style="text-align:right; margin:5	px 0">'+
					'<input id="btnEdit_'+rst_ID+'" type="button" value="Edit Field Type" onclick="_onAddEditFieldType('+rst_ID+');">'+
					'<input id="btnSave_'+rst_ID+'" type="button" value="Save" onclick="doExpliciteCollapse(event);" style="margin:0 10px;"/>'+
					'<input id="btnCancel_'+rst_ID+'" type="button" value="Cancel" onclick="doExpliciteCollapse(event);" />'+
					'</div></div>';
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
				(!Hul.isnull(column) && (column.key === 'rst_values' || column.key === 'rst_NonOwnerVisibility') ))
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
						elLiner.innerHTML = "<img src='../../common/images/blue-up-down-triangle.png'>"
					}*/

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
					alert("Please click Save Order button to save changes in view-order beofre exiting");
					return;
				}

				var elLink = oArgs.target;
				var oRecord = this.getRecord(elLink);
				var dty_ID = oRecord.getData("rst_ID");

				// result listener for delete operation
				function __updateAfterDelete(context) {

					if(!context){
						alert("Unknown error on server in editRecStructure.js");
					}else if(Hul.isnull(context.error)){

						_myDataTable.deleteRow(oRecord.getId(), -1);

						top.HEURIST.rectypes = context.rectypes;
						top.HEURIST.detailTypes = context.detailTypes;
					}
					_isServerOperationInProgress = false;
				}
				if(elLink.hash === "#edit"){
					_onAddEditFieldType(dty_ID, 0);
				}

				if(elLink.hash === "#delete"){
					var dty_name = oRecord.getData('dty_Name');
					var r=confirm("Delete detail #"+dty_ID+" '"+dty_name+"' from this record structure?");
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

	/**
	* Opens popup with preview
	*
	* Button is hident in UI
	* Preview record structure - doesn't always work reliably. It hasn't been fully debugged.
	* Option:   use records/add/formAddRecordPopup.html
	*
	*/
	function _initPreview(){

	if(Hul.isnull(popupSelect))
	{
		//save all changes
		_doExpliciteCollapse(null, true);

		var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
							(top.HEURIST.database.name?top.HEURIST.database.name:''));

		var url = top.HEURIST.basePath +
		"admin/structure/editRecStructurePreview.html?rty_ID="+editStructure.getRty_ID()+"&db="+db;

		window.open(url,'','scrollbars=no,menubar=no,height=600,width=800,resizable=yes,toolbar=no,location=no,status=no');
/*
		popupSelect = Hul.popupURL(top, url,
		{	"close-on-blur": false,
			"no-resize": false,
			height: 640,
			width: 800,
			callback: function(context) {
				popupSelect = null;
			}
		});
*/
	}

	}

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

			var elLiner = _myDataTable.getTdLinerEl({record:oRecord, column:_myDataTable.getColumn('rst_NonOwnerVisibility')});
			elLiner.innerHTML = "<img src='../../common/images/blue-up-down-triangle.png'>";

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
		var fieldnames = top.HEURIST.rectypes.typedefs.dtFieldNames,
			values = top.HEURIST.rectypes.typedefs[rty_ID].dtFields[rst_ID],
			rst_type = top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[top.HEURIST.detailTypes.typedefs.fieldNamesToIndex.dty_Type],
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
			selstatus.disabled = ((status === "reserved") && (original_dbId!==dbId) && (original_dbId>0) && (original_dbId<1001));
		}

		var k;
		for(k=0; k<fieldnames.length; k++){
			var ed_name = 'ed'+rst_ID+'_'+fieldnames[k];
			var edt = Dom.get(ed_name);
			if( !Hul.isnull(edt) && (isAll || edt.parentNode.id.indexOf("row")<0)){
				edt.value = values[k];

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
					recreateTermsPreviewSelector(rst_type,
						(Hul.isempty(edt2.value)?top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[top.HEURIST.detailTypes.typedefs.fieldNamesToIndex.dty_JsonTermIDTree]:edt2.value),
						(Hul.isempty(edt.value)?top.HEURIST.detailTypes.typedefs[rst_ID].commonFields[top.HEURIST.detailTypes.typedefs.fieldNamesToIndex.dty_TermIDTreeNonSelectableIDs]:edt.value),
						edt_def.value); //default value

					//editedTermTree, editedDisabledTerms);
					Dom.setStyle(edt_def, "visibility", "hidden");

				}else{
					edt.parentNode.parentNode.style.display = "none";
					Dom.setStyle(edt_def, "visibility", "visible");
					var el = document.getElementById("termsDefault");
					Dom.setStyle(el, "visibility", "hidden");
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

				}else{
					edt.parentNode.parentNode.style.display = "none";
				}

			}else if(rst_type === "relationtype"){

			}else if(rst_type === "resource"){
					//show disable target pnr rectype

			}else if(rst_type === "separator"  &&
				!(fieldnames[k] === "rst_DisplayName")){
					//hide all but name
					edt.parentNode.parentNode.style.display = "none";
			}else if(rst_type === "fieldsetmarker" && !(fieldnames[k] === "rst_DisplayName" || fieldnames[k] === "rst_Status")){
					//hide all, required - once
					edt.parentNode.parentNode.style.display = "none";
			}

			//hide width for some field types
			if(fieldnames[k] === "rst_DisplayWidth" && _isNoWidth(rst_type) ){
					edt.parentNode.parentNode.style.display = "none";
			}

			}
		}//for



		if(rst_type === "separator"){
			//hide repeatability
			var dr = Dom.get('divRepeatability');
			Dom.setStyle(dr, "display", "none");
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
			sel.value = res;
			onRepeatChange(Number(rst_ID));

			//update min/max visibility
			onReqtypeChange(Number(rst_ID));
		}


		//If reserved, requirements can only be increased, nor can you change min or max values
		onStatusChange(Number(rst_ID));
	}

	/**
	* These field types have no width
	*/
	function _isNoWidth(typ){
	       return ((typ == "enum") || (typ=="resource") ||
                    (typ=="relmarker") || (typ === "relationtype") ||
                    (typ=="file") || (typ=="geo") || (typ=="separator"));
	}


	/**
	* adds separator field type
	*/
	function _onAddSeparator(){

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
			_addDetails(ft_separator_id);
		}else{ //"not used" separator field type not found - create new one

			var _detailType = new Array();

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
				if(!context) {
					alert("An error occurred trying to contact the database");
				}else{
					var error = false,
						report = "",
						ind;

					for(ind in context.result){
						if( !Hul.isnull(ind) ){
							var item = context.result[ind];
							if(isNaN(item)){
								alert("An error occurred: " + item);
								error = true;
							}else{
								ft_separator_id = ""+Math.abs(Number(item));

								//refresh the local heurist
								top.HEURIST.detailTypes = context.detailTypes;

								_doExpliciteCollapse(null, true);
								_addDetails(ft_separator_id);
							}
						}
					}
				}
			}

			//
			var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
								(top.HEURIST.database.name?top.HEURIST.database.name:''));
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
		var detTypes = top.HEURIST.detailTypes.typedefs,
			fi = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex,
			rst = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex;

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
			if(Hul.isnull(recDetTypes[dty_ID])){ //not added
				var arrs = detTypes[dty_ID].commonFields;
				//add new detail type
                // note that integer, boolean, year, urlinclude can no longer be created but are retained for backward compatibility
				var def_width = 40;
				var dt_type = arrs[fi.dty_Type];

                if (_isNoWidth(dt_type))
				{
                    def_width = 0;
				}else if (dt_type === "date" || dt_type === "integer" || dt_type === "float" || dt_type === "year" ||
							dt_type === "calculated") {
                    def_width = 15;
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
				arr_target[rst.rst_RecordMatchOrder] = "0";
				arr_target[rst.rst_DisplayOrder] = order;
				arr_target[rst.rst_DisplayDetailTypeGroupID] = "1";
				arr_target[rst.rst_FilteredJsonTermIDTree] = null;
				arr_target[rst.rst_PtrFilteredIDs] = null;
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
					expandColumn:dty_ID,
					rst_DisplayOrder: order,
					dty_Name: arrs[fi.dty_Name],
					rst_DisplayName: arrs[fi.dty_Name],
					dty_Type: arrs[fi.dty_Type],
					rst_RequirementType: "optional",
					rst_DisplayWidth: def_width,
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

			_myDataTable.selectRow(index_toinsert);

			// in case of addition - all fields were affected
			_updatedFields = null;

			dragDropDisable();
			dragDropEnable();

			_saveUpdates(false);
		}

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
		btnSaveOrder.style.visibility = "hidden";

		if(!Hul.isnull(str)){
			//DEBUG  alert(str);
			var updateResult = function(context){
				if(context){
					top.HEURIST.rectypes = context.rectypes;
					top.HEURIST.detailTypes = context.detailTypes;
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
		_isDragEnabled = true;
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
			Dom.setStyle(srcEl, "visibility", "hidden");
			//proxyEl.innerHTML = "<table><tbody>"+srcEl.innerHTML+"</tbody></table>";
			proxyEl.innerHTML = "";

			//var rst_ID = this.srcData.rst_ID
			//_fromUItoArray(rst_ID); //before collapse save to UI

		},

		endDrag: function(x,y) {

			if(this.srcIndex==null) return;

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

			if(srcIndex==null) return;

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
		initPreview:function(){
			return _initPreview();
		},
		onAddSeparator: function(){
			_onAddSeparator();
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
		var dim = top.HEURIST.util.innerDimensions(top);
		var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
							(top.HEURIST.database.name?top.HEURIST.database.name:''));
		popupSelect = Hul.popupURL(top, top.HEURIST.basePath +
		"admin/structure/selectDetailType.html?rty_ID="+editStructure.getRty_ID()+"&db="+db,
		{	"close-on-blur": false,
			"no-resize": false,
			height: dim.h*0.9,
			width: dim.w*0.5,

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
* Adds separator field type
*/
function onAddSeparator(){
  editStructure.onAddSeparator();
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
			height: 700,

			width: 650,
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
*
*/
function onStatusChange(evt){
	var name;

	if(typeof evt === 'number'){
		name = 'ed'+evt;
	}else{
		var el = evt.target;
		name = el.id.substring(0,el.id.indexOf("_")); //. _rst_RequirementType
	}

	//If reserved, requirements can only be increased, nor can you change min or max values
	var status = Dom.get(name+"_rst_Status").value;
	var isReserved = (status === "reserved"); // || status === "approved");
	Dom.get(name+"_rst_MinValues").disabled = isReserved;
	Dom.get(name+"_rst_MaxValues").disabled = isReserved;
	var sel = Dom.get(name+"_Repeatability");
	sel.disabled = isReserved;

	sel = Dom.get(name+"_rst_RequirementType");
	sel.disabled = (isReserved && (sel.value==='required'));
}

/**
* Listener of requirement type selector (combobox)
*/
function onReqtypeChange(evt){
	var el, name;

	if(typeof evt === 'number'){
		el = Dom.get("ed"+evt+"_rst_RequirementType")
		name = 'ed'+evt;
	}else{
		el = evt.target;
		name = el.id.substring(0,el.id.indexOf("_")); //. _rst_RequirementType
	}

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
	} else if(el.value === "forbidden"){
		el_min.value = 0;
		el_max.value = 0;

		var span_max = Dom.get(name+'_spanMaxValue');
		Dom.setStyle(span_max, "visibility", "hidden");
	}

	if(el.value !== "forbidden"){
		//rep_el.disabled = false;
		onRepeatChange(evt);
	}
}

/**
* Listener of Repeatable type selector (combobox)
*/
function onRepeatChange(evt){

	var el, name;

	if(typeof evt === 'number'){
		el = Dom.get("ed"+evt+"_Repeatability")
		name = 'ed'+evt;
	}else{
		el = evt.target;
	 	name = el.id.substring(0,el.id.indexOf("_")); //. _rst_RequirementType
	}

	var el =  Dom.get(name+'_rst_RequirementType');
	if(el.value !== "forbidden"){

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

// DEBUG
//temp function to fill values with given rty_ID
/*
function _tempFillValue(){
editStructure.initTabDesign(document.getElementById("ed_rty_ID").value);
}
*/


function _preventSel(event){
		event.target.selectedIndex=0;
}
function _setDefTermValue(event){

	var el = event.target,
		name = el.parentElement.attributes.item('id').nodeValue;

	name = name.substr(3);

	var edt_def = Dom.get('ed'+name);
	edt_def.value =  event.target.value;
}

/**
* recreateTermsPreviewSelector
* creates and fills selector for Terms Tree if datatype is enum, relmarker, relationtype
* @param datatype an datatype
* @allTerms - JSON string with terms
* @disabledTerms  - JSON string with disabled terms
*/
function recreateTermsPreviewSelector(datatype, allTerms, disabledTerms, defvalue ) {

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
						parent2 = document.getElementById("termsDefault");

					function __recreate(parent, onchangehandler, bgcolor, _defvalue, isdefselector){
						var i;
						for (i = 0; i < parent.children.length; i++) {
							parent.removeChild(parent.childNodes[0]);
						}

						// add new select (combobox)
						if(datatype === "enum") {
							el_sel = Hul.createTermSelectExt(allTerms, disabledTerms, top.HEURIST.terms.termsByDomainLookup['enum'], _defvalue, isdefselector);
							el_sel.style.backgroundColor = bgcolor;
							el_sel.onchange =  onchangehandler;
							el_sel.className = "previewList";
							parent.appendChild(el_sel);
						}
						else if(datatype === "relmarker" || datatype === "relationtype") {
							el_sel = Hul.createTermSelectExt(allTerms, disabledTerms, top.HEURIST.terms.termsByDomainLookup.relation, _defvalue, isdefselector);
							el_sel.style.backgroundColor = bgcolor;
							el_sel.onchange =  onchangehandler;
							parent.appendChild(el_sel);
						}
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

		if (txt.length > 40){
			divRecType.title = txt;
			txt = txt.substr(0,40) + "&#8230";
		}else{
			divRecType.title = "";
		}
		divRecType.innerHTML = txt;
}

function _onAddEditFieldType(dty_ID, dtg_ID){

		var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
											(top.HEURIST.database.name?top.HEURIST.database.name:''));
		var url = top.HEURIST.basePath + "admin/structure/editDetailType.html?db="+db+ "&detailTypeID="+dty_ID; //existing

		top.HEURIST.util.popupURL(top, url,
		{   "close-on-blur": false,
			"no-resize": false,
			height: 520,
			width: 640,
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

					var fi = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex;

					var rst_type = top.HEURIST.detailTypes.typedefs[dty_ID].commonFields[fi.dty_Type];
					//update
					if(rst_type === "enum" || rst_type === "relmarker" || rst_type === "relationtype"){
						recreateTermsPreviewSelector(rst_type,
							top.HEURIST.detailTypes.typedefs[dty_ID].commonFields[fi.dty_Type.dty_JsonTermIDTree],
							top.HEURIST.detailTypes.typedefs[dty_ID].commonFields[fi.dty_TermIDTreeNonSelectableIDs], null);
					}
					if(rst_type === "relmarker" || rst_type === "resource"){
						recreateRecTypesPreview(rst_type,
							top.HEURIST.detailTypes.typedefs[dty_ID].commonFields[fi.dty_PtrTargetRectypeIDs], null);
					}

					/*detect what group
					var grpID = top.HEURIST.detailTypes.typedefs[dty_ID].commonFields[7];

					_removeTable(grpID, true);
					if(grpID_old!==grpID){
						_removeTable(grpID_old, true);
					}*/
				}
			}
		});
}
