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
* editGroup.js
* A form to edit Workgroups, or create a new group. It is utilized as pop-up from manageGroup
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

//aliases

var Dom = YAHOO.util.Dom,
	Hul = top.HEURIST.util;

/**
* GroupEditor - class for pop-up edit group
*
* public methods
*
* save - sends data to server and closes the pop-up window in case of success
* cancel - checks if changes were made, shows warning and closes the window
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2011.0427
*/

function GroupEditor() {

		var _className = "GroupEditor",
			_entity, //object (group) to edit
			_recID,     // its ID
			_updatedFields = [], //field names which values were changed to be sent to server
			_updatedDetails = [], //field values
			_db,
			_isAdmin = true;

	/**
	* Initialization of input form
	*
	* Reads GET parameters, creates group selector and invokes the method that fills values for inputs
	*/
	function _init() {

		_isAdmin = true;

		var typeID;
		_db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));
		// reads parameters from GET request
		if (location.search.length > 1) {
				top.HEURIST.parameters = top.HEURIST.parseParams(location.search);
				_recID = top.HEURIST.parameters.recID;
				typeID = top.HEURIST.parameters.typeID;

				if(_recID>=0){
					_entity = top.HEURIST.userGrp.groups[_recID];
				}
				if(Hul.isnull(typeID)){
					typeID = 0;
				}
		}

		if (_recID>=0 && Hul.isnull(_entity) ){
			Dom.get("statusMsg").innerHTML = "<strong>Error: Group #"+_recID+"  not be found. Clicking 'save' button will create a new Group.</strong><br /><br />";
		}
		//creates new empty field type in case ID is not defined
		if(Hul.isnull(_entity)){
			_recID =  -1;
			//"ugr_ID", "ugr_Type", "ugr_Name", "ugr_LongName", "ugr_Description", "ugr_URLs", "ugr_Enabled");
			_entity = [-1,'workgroup','','','','','y'];
		}else{
			//permisssions
			_isAdmin = (top.HEURIST.is_admin() || _entity.admins.indexOf(String(top.HEURIST.get_user_id()))>=0);
		}

		//fills input with values from _entity array
		_fromArrayToUI();

		if(!_isAdmin){
			//hide and rename buttons
			Dom.get("btn_edits").style.display = "none";
			Dom.get("btn_view").style.display = "block";

		}

	}

	/**
	* Toggle fields to disable. Is called when enabled is set to 'false'.
	* If changed = true, it means that the status is manually changed to reserved,
	* so untill it is saved, it can be changed back. If it was reserved when starting the editGroup,
	* keep it disabled
	*/
	function _toggleAll(disable, changed) {
			Dom.get("ugr_Name").disabled = disable;
			Dom.get("ugr_LongName").disabled = disable;
			Dom.get("ugr_Description").disabled = disable;
			Dom.get("ugr_URLs").disabled = disable;

			//Dom.get("ugr_Type").disabled = disable;
	}

	/**
	* Fills inputs with values from _entity array
	* @todo Change HEURIST so it will contain field names for Group
	*/
	function _fromArrayToUI(){

		var i,
			el,
			fnames = top.HEURIST.userGrp.groups.fieldNames;

		for (i = 0, l = fnames.length; i < l; i++) {
			var fname = fnames[i];
			el = Dom.get(fname);
			if(!Hul.isnull(el)){
				if ( el.type === "checkbox") { // dty_ShowInLists
					el.checked = (_entity[i]==="y");
				}

				el.value = _entity[i];
				el.readOnly = !_isAdmin;
			}
		}

		if (_recID<0){
			Dom.get("ugr_ID").innerHTML = 'to be generated';
			document.title = "Create New Workgroup";
		}else{
			Dom.get("ugr_ID").innerHTML =  _recID;
			document.title = "Workgroup #: " + _recID+" '"+_entity[2]+"'";

			//var iusage = top.HEURIST.workgroups[_recID].memberCount;

			Dom.get("statusMsg").innerHTML = "";
		}

		if(_isAdmin){
				Dom.get("ugr_Enabled").onchange = _onChangeEnable;
		}
	}


	/**
	* Stores the changed values and verifies mandatory fields
	*
	* Compares data in input with values and in _entity array, then
	* gathers changed values from UI elements (inputs) into 2 arrays _updatedFields and _updatedDetails
	* this function is invoked in 2 places:
	* 1) in cancel method - to check if something was changed and show warning
	* 2) in save (_updateOnServer) - to gather the data to send to server
	*
	* @param isShowWarn - show alert about empty mandatory fields, it is false for cancel
	* @return "mandatory" in case there are empty mandatory fields (it prevents further saving on server)
	*           or "ok" if all mandatory fields are filled
	*/
	function _fromUItoArray(isShowWarn){

		_updatedFields = [];
		_updatedDetails = [];

		var i,
			fnames = top.HEURIST.userGrp.groups.fieldNames;

		//take only changed values
		for (i = 0, l = fnames.length; i < l; i++){
			var fname = fnames[i];
			el = Dom.get(fname);
			if( !Hul.isnull(el) ){
				if(el.type === "checkbox"){
					el.value = el.checked?"y":"n";
				}
				if(_recID<0 || (el.value!==String(_entity[i]) && !(el.value==="" && _entity[i]===null)))
				{
					_updatedFields.push(fname);
					_updatedDetails.push(el.value);
				}
			}
		}

		// check mandatory fields
		if(Hul.isempty(Dom.get("ugr_Name").value)) {
				if(isShowWarn) {
					alert("Name is a mandatory field");
				}
				Dom.get("ugr_Name").focus();
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
						_recID = Number(item);
						if(report!=="") {
							report = report + ",";
						}
						report = report + Math.abs(_recID);
					}
				}
			}

			if(!error){
				var ss = (_recID < 0)?"added":"updated";

				// this alert is a pain  alert("Group with ID " + report + " was succesfully "+ss);
				window.close(context); //send back new HEURIST strcuture
			}
		}
	}

	/**
	* Apply form
	* private method for public method "save"
	* 1. gather changed data from UI (_fromUItoArray) to _updatedFields, _updatedDetails
	* 2. creates object to be sent to server
	* 3. sends data to server
	*/
	function _updateOnServer()
	{

		//1. gather changed data
		if(_fromUItoArray(true)==="mandatory"){ //save all changes
			return;
		}

		var str = null;

		//2. creates object to be sent to server
		if(_recID !== null && _updatedFields.length > 0){
			var k,
				val;
			var oDataToServer = {group:{
				colNames:[],
				defs: {}
			}};

			var values = [];
			for(k = 0; k < _updatedFields.length; k++) {
				oDataToServer.group.colNames.push(_updatedFields[k]);
				values.push(_updatedDetails[k]);
			}
			//for new group add stub for required email field
			if(_recID<0){
				oDataToServer.group.colNames.push("ugr_eMail");
				values.push("EMAIL NOT SET FOR "+Dom.get("ugr_Name").value);
			}

			oDataToServer.group.defs[_recID] = [];
			for(val in values) {
				oDataToServer.group.defs[_recID].push(values[val]);
			}
			str = YAHOO.lang.JSON.stringify(oDataToServer);
		}


		if(!Hul.isempty(str)) {

			// 3. sends data to server
			var baseurl = top.HEURIST.baseURL + "admin/ugrps/saveUsergrps.php";
			var callback = _updateResult;
			var params = "method=saveGroup&db=" + _db + "&data=" + encodeURIComponent(str);
			top.HEURIST.util.getJsonData(baseurl, callback, params);
		} else {
			window.close(null);
		}
	}

	/**
	* enable checkbox listener
	*/
	function _onChangeEnable(e){

		var el = e.target;
		if(!el.checked) {
			var changeToReserved = confirm(
"If you change group to disabled,\nyou will no longer be able to "+
"change the fields in this workgroup\nuntil you re-enable the workgroup");
			if(changeToReserved) {
					_toggleAll(true, true);
			} else {
					el.checked = true; //restore previous value
			}
		} else {
				_toggleAll(false, true);
		}
	}


	//public members
	var that = {

			/**
			 *	Apply form - sends data to server and closes this pop-up window in case of success
			 */
			save : function () {
				_updateOnServer();
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

	_init();
	return that;
}
