/**
* editDetailType.js
* A form to edit field type, or create a new field type. It is utilized as pop-up from manageDetailTypes and manageRectypes
* it may call another pop-ups: selectTerms and selectRecType
*
* 28/04/2011
* @author: Juan Adriaanse
* @author: Artem Osmakov
* @author: Stephen White
*
* @copyright (C) 2005-2011 University of Sydney Digital Innovation Unit.
* @link: http://HeuristScholar.org
* @license http://www.gnu.org/licenses/gpl-3.0.txt
* @package Heurist academic knowledge management system
* @todo
**/

	var editPopup;	// EditDetailType object

/**
* Toggle fields to disable. Is called when status is set to 'Reserved'.
* If changed = true, it means that the status is manually changed to reserved,
* so untill it is saved, it can be changed back. If it was reserved when starting the editRectype,
* keep it disabled
*/
function toggleAll(disable, changed) {
		document.getElementById("dty_Name").disabled = disable;
		document.getElementById("dty_DetailTypeGroupID").disabled = disable;
		//document.getElementById("dty_Status").disabled = disable;
		document.getElementById("dty_OrderInGroup").disabled = disable;
		document.getElementById("dty_ShowInLists").disabled = disable;

		document.getElementById("termsPreview").disabled = disable;
		document.getElementById("btnSelTerms").disabled = disable;
		document.getElementById("btnSelRecType1").disabled = disable;
		document.getElementById("btnSelRecType2").disabled = disable;
}

/**
* helper function. utilized in recreateTermsPreviewSelector only
* converts json string to array
*/
function expandJsonStructure( jsonString ) {
	var retStruct = "";
	if(jsonString && jsonString !== "") {
		try {
			retStruct = eval(jsonString);
		} catch(e) {
			try {
				retStruct = YAHOO.lang.JSON.parse(jsonString);
			} catch(e1) {
				retStruct = "";
			}
		}
	}
	return retStruct;
}

/**
* recreateTermsPreviewSelector
* creates and fills selector for Terms Tree if datatype is enum, relmarker, relationtype
* @param datatype an datatype
* @allTerms - JSON string with terms
* @disabledTerms  - JSON string with disabled terms
*/
function recreateTermsPreviewSelector( datatype, allTerms, disabledTerms ) {

				allTerms = expandJsonStructure(allTerms);
				disabledTerms = expandJsonStructure(disabledTerms);

				if (typeof disabledTerms.join === "function") {
						disabledTerms = disabledTerms.join(",");
				}

				if(allTerms !== null && allTerms!==undefined) {
					//remove old combobox
					var prev = document.getElementById("termsPreview"),
						i;
					for (i = 0; i < prev.children.length; i++) {
						prev.removeChild(prev.childNodes[0]);
					}
					// add new select (combobox)
					if(datatype === "enum") {
						prev.appendChild(top.HEURIST.util.createTermSelect(allTerms, disabledTerms, top.HEURIST.terms.termsByDomainLookup['enum'], null));
					}
					else if(datatype === "relmarker" || datatype === "relationtype") {
						prev.appendChild(top.HEURIST.util.createTermSelect(allTerms, disabledTerms, top.HEURIST.terms.termsByDomainLookup.relation, null));
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

	var sel = YAHOO.util.Dom.get( (type==="fieldsetmarker")? "dty_FieldSetRecTypeIDPreview" : "dty_PtrTargetRectypeIDsPreview" );

	if(sel===null) {
		return;
	}

	//clear select
	while (sel.length>0){
		sel.remove(0);
	}

	if(value===undefined || value===null) {
		return;
	}

	var arr = value.split(","),
		ind;

	for (ind in arr) {
		if(ind!==undefined && ind!==null) {
			var dtName = top.HEURIST.rectypes.names[arr[ind]];

			var option = document.createElement("option");
			option.text = (dtName?dtName:"unconstrained");
			try
			{
				// for IE earlier than version 8
				sel.add(option, sel.options[null]);
			}
			catch (e)
			{
				sel.add(option,null);
			}
		}
	} //for
}

/**
* onSelectTerms
*
* listener of "Change vocabulary" button
* Shows a popup window where user can select terms to create a term tree as wanted
*/
function onSelectTerms(){

	var type = YAHOO.util.Dom.get("dty_Type").value;
	var allTerms = YAHOO.util.Dom.get("dty_JsonTermIDTree").value;
	var disTerms = YAHOO.util.Dom.get("dty_TermIDTreeNonSelectableIDs").value;
	var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));

	top.HEURIST.util.popupURL(top, top.HEURIST.basePath +
		"admin/structure/selectTerms.html?datatype="+type+"&all="+allTerms+"&dis="+disTerms+"&db="+db,
		{
		"close-on-blur": false,
		"no-resize": true,
		height: 650,
		width: 750,
		callback: function(editedTermTree, editedDisabledTerms) {
			if(editedTermTree || editedDisabledTerms) {
				//update hidden fields
				YAHOO.util.Dom.get("dty_JsonTermIDTree").value = editedTermTree;
				YAHOO.util.Dom.get("dty_TermIDTreeNonSelectableIDs").value = editedDisabledTerms;
				recreateTermsPreviewSelector(YAHOO.util.Dom.get("dty_Type").value, editedTermTree, editedDisabledTerms);
			}
		}
	});

}

/**
* onSelectRectype
*
* listener of "Select Record Type" buttons
* Shows a popup window where you can select record types
*/
function onSelectRectype() {
	var type = YAHOO.util.Dom.get("dty_Type").value;
	var args,URL;
	if(type === "fieldsetmarker") {
		if(document.getElementById("dty_FieldSetRecTypeID")) {
			args = document.getElementById("dty_FieldSetRecTypeID").value;
		}
	}
	if(type === "relmarker" || type === "resource") {
		if(document.getElementById("dty_PtrTargetRectypeIDs")) {
			args = document.getElementById("dty_PtrTargetRectypeIDs").value;
		}
	}
	if(args) {
		URL =  top.HEURIST.basePath + "admin/structure/selectRecType.html?type=" + type + "&ids=" + args;
	} else {
		URL =  top.HEURIST.basePath + "admin/structure/selectRecType.html?type=" + type;
	}
	if(type === "relmarker" || type === "resource" || type === "fieldsetmarker") {
		top.HEURIST.util.popupURL(top, URL, {
			"close-on-blur": false,
			"no-resize": true,
			height: 480,
			width: 440,
			callback: function(recordTypesSelected) {
				if(recordTypesSelected !== null) { // TODO: Test this
					if(type === "fieldsetmarker") { // Change comma seperated list to right format
						document.getElementById("dty_FieldSetRecTypeID").value = recordTypesSelected;
					} else {
						document.getElementById("dty_PtrTargetRectypeIDs").value = recordTypesSelected;
					}

					recreateRecTypesPreview(type, recordTypesSelected);
				}
			}
		});
	}
}

/**
* Validates value inserted into input field. In this case, make sure it's an integer
* used to validate order in group value (now hidden)
* @param evt - the evt object for this keypress
*/
function checkIfInteger(evt) {
	if((evt.keyCode) !== 9) {
		var theEvent = evt || window.event;
		var key = theEvent.keyCode || theEvent.which;
		key = String.fromCharCode(key);
		var regex = /[0-9]|\./;
		if( !regex.test(key) ) {
			theEvent.returnValue = false;
			theEvent.preventDefault();
		}
	}
}

/**
* EditDetailType - class for pop-up edit field type window
*
* public methods
*
* apply - sends data to server and closes the pop-up window in case of success
* cancel - checks if changes were made, shows warning and closes the window
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2011.0427
*/

function EditDetailType() {

		var _className = "EditDetailType",
			_detailType, //field type to edit
			_dtyID,     // its ID
			_updatedFields = [], //field names which values were changed to be sent to server
			_updatedDetails = [], //field values
			_keepStatus,// Keeps current status for rollback if user decided to keep it
			_keepType;	// Keeps current datatype for rollback

		//aliases
		var Dom = YAHOO.util.Dom;

	/**
	* Initialization of input form
	*
	* Reads GET parameters, creates group selector and invokes the method that fills values for inputs
	*/
	function _init() {

		var dtgID;

		// reads parameters from GET request
		if (location.search.length > 1) {
				top.HEURIST.parameters = top.HEURIST.parseParams(location.search);
				_dtyID = top.HEURIST.parameters.detailTypeID;
				dtgID = top.HEURIST.parameters.groupID;

				if(_dtyID){
					var dt = top.HEURIST.detailTypes.typedefs[_dtyID];
					if(dt!== undefined && dt !==null){
						_detailType = dt.commonFields;
					}
				}
				if(dtgID===undefined || dtg===null){
					dtgID = 0;
				}
		}

		if (_dtyID && (_detailType===undefined || _detailType===null) ){
			Dom.get("statusMsg").innerHTML = "<strong>Error: field type #"+_dtyID+"  not be found. Clicking 'save' button will create a new Field Type.</strong><br /><br />";
		}
		//creates new empty field type in case ID is not defined
		if(_detailType===undefined  || _detailType===null){
			_dtyID =  -1;
			_detailType = ['','','freetext',0,'',1,'open',dtgID,null,null,null,null];
		}

		_keepStatus = _detailType[6]; // Keeps current status for rollback
		_keepType = _detailType[2]; // Keeps current datatype

		// creates and fills group selector
		_initGroupComboBox();

		//fills input with values from _detailType array
		_fromArrayToUI();
	}

	/**
	* Initialization of group selector
	*
	* Gets all groups in HEURIST DB, creates and adds oprions to group selector
	*/
	function _initGroupComboBox() {

		var el = Dom.get("dty_DetailTypeGroupID"),
			dtg_ID;

		for (dtg_ID in top.HEURIST.detailTypes.groups){
			if(dtg_ID!==undefined && dtg_ID!==null) {
				var grpName = top.HEURIST.detailTypes.groups[dtg_ID].name;

				var option = document.createElement("option");
				option.text = grpName;
				option.value = dtg_ID;

				try
				{
					// for IE earlier than version 8
					el.add(option, el.options[null]);
				}
				catch (e)
				{
					el.add(option,null);
				}
			}
		} //for
	}

	/**
	*  Fills inputs with values from _detailType array
	*/
	function _fromArrayToUI(){

		var i,
			el;
		var fnames = top.HEURIST.detailTypes.typedefs.commomFieldNames;

		for (i = 0, l = fnames.length; i < l; i++) {
			var fname = fnames[i];
			el = Dom.get(fname);
			if(el !== undefined && el!==null){
				el.value = _detailType[i];
			}
		}

		//to trigger setting visibilty for div with terms tree and record pointer
		onChangeType(null);

		// create preview for Terms Tree and record pointer
		recreateTermsPreviewSelector(_detailType[2], _detailType[9], _detailType[10]);
		recreateRecTypesPreview(_detailType[2], ((_detailType[2]==="fieldsetmarker")?_detailType[8]:_detailType[11]) );


		el = Dom.get("dty_ShowInLists");
		el.checked = (_detailType[5]===1);

		if (_dtyID<0){
			Dom.get("dty_ID").innerHTML = 'to be generated';
			document.title = "Create New Field Type";
		}else{
			Dom.get("dty_ID").innerHTML =  _dtyID;
			document.title = "Field Type #: " + _dtyID+" '"+_detailType[0]+"'";

			var aUsage = top.HEURIST.detailTypes.rectypeUsage[_dtyID];
			var iusage = (aUsage===undefined || aUsage===null) ? 0 : aUsage.length;

			if(iusage > 0) {
				if(iusage===1) {
					Dom.get("statusMsg").innerHTML = "<strong>Warning: this fieldtype is used in " + iusage + " recordtype. Changes made, will affect that rectype.</strong><br /><br />";
				} else {
					Dom.get("statusMsg").innerHTML = "<strong>Warning: this fieldtype is used in " + iusage + " recordtypes. Changes made, will affect every one of those.</strong><br /><br />";
				}
			}
		}
	}


	/**
	* Stores the changed values and verifies mandatory fields
	*
	* Compares data in input with values and in _detailType array, then
	* gathers changed values from UI elements (inputs) into 2 arrays _updatedFields and _updatedDetails
	* this function is invoked in 2 places:
	* 1) in cancel method - to check if something was changed and show warning
	* 2) in apply (_updateDetailTypeOnServer) - to gather the data to send to server
	*
	* @param isShowWarn - show alert about empty mandatory fields, it is false for cancel
	* @return "mandatory" in case there are empty mandatory fields (it prevents further saving on server)
	*           or "ok" if all mandatory fields are filled
	*/
	function _fromUItoArray(isShowWarn){

		_updatedFields = [];
		_updatedDetails = [];

		var el = Dom.get("dty_ShowInLists");
		el.value = el.checked?1:0;

		var i;
		var fnames = top.HEURIST.detailTypes.typedefs.commomFieldNames;

		//take only changed values
		for (i = 0, l = fnames.length; i < l; i++){
			var fname = fnames[i];
			el = Dom.get(fname);
			if(el!==undefined && el!==null){
				if(_dtyID<0 || (el.value!==String(_detailType[i]) && !(el.value==="" && _detailType[i]===null)))
				{
					_updatedFields.push(fname);
					_updatedDetails.push(el.value);
				}
			}
		}

		// check mandatory fields
		if(Dom.get("dty_Name").value==="") {
				if(isShowWarn) {
					alert("Name is mandatory field");
				}
				Dom.get("dty_Name").focus();
				_updatedFields = [];
				return "mandatory";
		}
		if(Dom.get("dty_HelpText").value==="") {
				if(isShowWarn) {
					alert("Help text is mandatory field");
				}
				Dom.get("dty_HelpText").focus();
				_updatedFields = [];
				return "mandatory";
		}

		return "ok";
	}

	/**
	* Http responce listener
	*
	* shows information about result of operation of saving on server and closes this pop-up window in case of success
	*
	* @param context - data from server
	*/
	function _updateResult(context) {
		if(!context) {
			alert("An error occurred trying to contact the database");
		}else{
			var error = false,
				report = "",
				ind;

			for(ind in context.result){
				if(ind!==undefined && ind!==null){
					var item = context.result[ind];
					if(isNaN(item)){
						alert("An error occurred: " + item);
						error = true;
					}else{
						detailTypeID = Number(item);
						if(report!=="") {
							report = report + ",";
						}
						report = report + Math.abs(detailTypeID);
					}
				}
			}

			if(!error){
				var ss = (detailTypeID < 0)?"added":"updated";

				if(report.indexOf(",")>0){
					alert("Field types with IDs :"+report+ " were succesfully "+ss);
				}else{
					alert("Field type with ID " + report + " was succesfully "+ss);
				}
				window.close(context); //send back new HEURIST strcuture
			}
		}
	}

	/**
	* Apply form
	* private method for public method "apply"
	* 1. gather changed data from UI (_fromUItoArray) to _updatedFields, _updatedDetails
	* 2. creates object to be sent to server
	* 3. sends data to server
	*/
	function _updateDetailTypeOnServer()
	{

		//1. gather changed data
		if(_fromUItoArray(true)==="mandatory"){ //save all changes
			return;
		}

		var str = null;

		//2. creates object to be sent to server
		if(_dtyID !== null && _updatedFields.length > 0){
			var k,
				val;
			var oDetailType = {detailtype:{
				colNames:{common:[]},
				defs: {}
			}};

			//fill array of updated fieldnames
			//var fieldNames = top.HEURIST.detailTypes.typedefs.commomFieldNames;

			var values = [];
			for(k = 0; k < _updatedFields.length; k++) {
				oDetailType.detailtype.colNames.common.push(_updatedFields[k]);
				values.push(_updatedDetails[k]);
			}

			oDetailType.detailtype.defs[_dtyID] = {};
			oDetailType.detailtype.defs[_dtyID].common = [];
			for(val in values) {
				oDetailType.detailtype.defs[_dtyID].common.push(values[val]);
			}
			str = encodeURIComponent(YAHOO.lang.JSON.stringify(oDetailType));
		}


		if(str !== null) {
//DEBUG alert("Stringified changes: " + str);

			// 3. sends data to server
			var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
								(top.HEURIST.database.name?top.HEURIST.database.name:''));
			var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
			var callback = _updateResult;
			var params = "method=saveDT&db="+db+"&data=" + str;
			top.HEURIST.util.getJsonData(baseurl, callback, params);
		} else {
			window.close(null);
		}
	}


	//public members
	var that = {

			/** Keeps current status for rollback if user decided to keep it */
			keepStatus: _keepStatus,
			/** Keeps current datatype for rollback  */
			keepType: _keepType,

			/**
			 *	Apply form - sends data to server and closes this pop-up window in case of success
			 */
			apply : function () {
				_updateDetailTypeOnServer();
			},

			/**
			 * Cancel form - checks if changes were made, shows warning and closes the window
			 */
			cancel : function () {
				_fromUItoArray(false);
				if(_updatedFields.length > 0) {
					var areYouSure = confirm("Changes were made. By cancelling, all changes will be lost. Are you sure?");
					if(areYouSure) {
						window.close(null);
					}
				}else{
					window.close(null);
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

/**
* onChangeType - listener for datetype selector
*
* Sets visibilty for div with terms tree and record pointer
* Clears hidden fields for term tree and pointer in case of changing type
* is invoked explicitely in _fromArrayToUI
*/
function onChangeType(e){

		var el = YAHOO.util.Dom.get("dty_Type"); //e.target;
		var isInitialCall = (e===null);

		YAHOO.util.Dom.get("pnl_relmarker").style.display = "none";
		YAHOO.util.Dom.get("pnl_enum").style.display = "none";
		YAHOO.util.Dom.get("pnl_fieldsetmarker").style.display = "none";

		var changeToNewType = true;
		if( ((EditDetailType.keepType==="resource") || (EditDetailType.keepType==="relmarker") || (EditDetailType.keepType==="enum")
			|| (EditDetailType.keepType==="relationtype") || (EditDetailType.keepType==="fieldsetmarker"))
			 && el.value!==EditDetailType.keepType){
			changeToNewType = confirm("If you change the type to '"+el.value+
										"' you will lost all your vocabulary settings for type '"+EditDetailType.keepType+
										"'.\n\nAre you sure?");
		}

		if(changeToNewType) {
				//clear hidden fields
				if (!isInitialCall){
					YAHOO.util.Dom.get("dty_JsonTermIDTree").value = "";
					YAHOO.util.Dom.get("dty_TermIDTreeNonSelectableIDs").value = "";
					YAHOO.util.Dom.get("dty_PtrTargetRectypeIDs").value = "";
					YAHOO.util.Dom.get("dty_FieldSetRecTypeID").value = "";
					EditDetailType.keepType = el.value;
					recreateTermsPreviewSelector(EditDetailType.keepType, null, null);
					recreateRecTypesPreview(EditDetailType.keepType, null);
				}
		}else{
				el.value = EditDetailType.keepType;  //rollback
		}

		// setting visibility
		switch(el.value)
		{
		case "resource":
			YAHOO.util.Dom.get("pnl_relmarker").style.display = "block";
			break;
		case "relmarker":
			YAHOO.util.Dom.get("pnl_relmarker").style.display = "block";
		case "enum":
		case "relationtype":
			YAHOO.util.Dom.get("pnl_enum").style.display = "block";
			break;
		case "fieldsetmarker":
			YAHOO.util.Dom.get("pnl_fieldsetmarker").style.display = "block";
			break;
		default:
		}
}

/**
* onChangeStatus - status selector listener
*/
function onChangeStatus(e){

		var el = e.target;
		if(el.value === "reserved") {
			var changeToReserved = confirm("If you change the status to reserved," +
											" you will no longer be able to change any "+
											"fields of this detailtype after you save it.\n\nAre you sure?");
			if(changeToReserved) {
				toggleAll(true, true);
			} else {
				el.value = EditDetailType.keepStatus; //restore previous value
			}
		} else {
			EditDetailType.keepStatus = el.value;
			toggleAll(false, true);
		}
}
