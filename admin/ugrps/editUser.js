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
* editUser.js
* A form to edit user, or create a new user. It is utilized as pop-up from manageGroups and manageUsers
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
* UserEditor - class for pop-up edit group
*
* public methods
*
* save - sends data to server and closes the pop-up window in case of success
* cancel - checks if changes were made, shows warning and closes the window
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2011.0509
*/

function UserEditor() {

		var _className = "UserEditor",
			_entity, //object (user) to edit
			_recID,     // its ID
			_groupID,   // new user will be added to this group
			_updatedFields = [], //field names which values were changed to be sent to server
			_updatedDetails = [], //field values
			_db,
			_isAdmin = true,
			_isRegistration = false;

	/**
	* Initialization of input form
	*
	* Reads GET parameters, creates group selector and invokes the method that fills values for inputs
	*/
	function _init() {

		_isAdmin = true;

		var	_isApprove = false;

		var groupID;
		_db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));
		// reads parameters from GET request
		if (location.search.length > 1) {
				top.HEURIST.parameters = top.HEURIST.parseParams(location.search);
				_recID = top.HEURIST.parameters.recID;
				_groupID = top.HEURIST.parameters.groupID;
				_isApprove = (top.HEURIST.is_admin() && top.HEURIST.parameters.approve=='1');

				if(Hul.isempty(_groupID) && Hul.isempty(_recID)){
					_recID = top.HEURIST.get_user_id();
				}else{
					_recID = Number(_recID);
				}

				if(Number(_recID)>0){
					_entity = top.HEURIST.userGrp.users[_recID];
				}
		}

		if (_recID>0 && Hul.isnull(_entity) ){
			Dom.get("statusMsg").innerHTML = "<strong>Error: User #"+_recID+"  was not be found. This form will create a new user.</strong><br /><br />";
		}
		//creates new empty field type in case ID is not defined
		if(Hul.isnull(_entity)){
			_recID =  -1;
			_entity = [-1,'user','','','','','','','','','','','','','y',''];

			_isRegistration  = (!top.HEURIST.is_logged_in() || window.parent == window);
			if(_isRegistration){
				$('#condition').removeClass('hidden');
				$('#div-inpit-ugr_ID').addClass('hidden');
				$('#div-inpit-ugr_Enabled').addClass('hidden');
				$('#div-inpit-ugr_IsModelUser').addClass('hidden');
				$('#editButtons').addClass('hidden');
				$('#regButtons').removeClass('hidden');
				$('#regInfo').removeClass('hidden');
				$('#contactDetails').html("Email "+top.HEURIST.userGrp['adminName']+": <a href=\"mailto:'"+top.HEURIST.userGrp['adminMail']+"'\">"+
								top.HEURIST.userGrp['adminMail']+"</a>");
			}

		}else{ //reset password
			//permisssions
			_isAdmin = (top.HEURIST.is_admin() || _recID === top.HEURIST.get_user_id());

			_entity[4] = "";
		}

		//fills input with values from _entity array
		_fromArrayToUI();


		if(!top.HEURIST.is_admin()){
			//hide and rename buttons
			$('#div-inpit-ugr_Enabled').addClass('hidden');
			$('#div-inpit-ugr_IsModelUser').addClass('hidden');
		}
		if(!_isAdmin){
			Dom.get("btn_edits").style.display = "none";
			Dom.get("btn_view").style.display = "inline-block";
		}
		if(_isApprove && !Dom.get("ugr_Enabled").checked){
			Dom.get("ugr_Enabled").checked = true;
			Dom.get("btn_approve").style.display = "inline-block";
		}


	}

	/**
	* Toggle fields to disable. Is called when enabled is set to 'false'.
	* If changed = true, it means that the status is manually changed to reserved,
	* so untill it is saved, it can be changed back. If it was reserved when starting the editGroup,
	* keep it disabled
	*/
	function _toggleAll(disable, changed) {

		var i,
			el,
			fnames = top.HEURIST.userGrp.users.fieldNames;

		for (i = 0, l = fnames.length; i < l; i++) {
			var fname = fnames[i];
			el = Dom.get(fname);
			if(!(Hul.isnull(el) || fname==="ugr_Enabled")) {
				el.disabled = disable;
			}
		}
		//plus second password
		Dom.get("ugr_Password2").disabled = Dom.get("ugr_Password").disabled;
	}

	/**
	* Fills inputs with values from _entity array
	* @todo Change HEURIST so it will contain field names for Group
	*/
	function _fromArrayToUI(){

		var i,
			el,
			fnames = top.HEURIST.userGrp.users.fieldNames;

		for (i = 1, l = fnames.length; i < l; i++) {
			var fname = fnames[i];
			el = Dom.get(fname);
			if(!Hul.isnull(el)){
				if( fname === "ugr_Enabled" ){  // el.type === "checkbox"
					el.checked = (_entity[i]==="y");
					if(_isAdmin){
						el.onchange = _onChangeEnable;
					}

					//Ian's request 2012-02-07 _toggleAll(!el.checked, true);
				}else{
					el.value = _entity[i];
					el.readOnly = !_isAdmin;
				}
			}
		}

		//plus second password
		Dom.get("ugr_Password2").readOnly = Dom.get("ugr_Password").readOnly;


		el = Dom.get("ugr_ID");
		if (_recID<0){
				el.innerHTML = 'to be generated';
				document.title = (_isRegistration)?"Registration":"Create New User";
		}else{
				el.innerHTML =  _recID;
				//document.title = "User #: " + _recID+" '"+_entity[6]+" "+_entity[7]+"'";
				document.title = "Editing User: "+" "+_entity[6]+" "+_entity[7];
				Dom.get("statusMsg").innerHTML = "";
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
			fnames = top.HEURIST.userGrp.users.fieldNames;

		//take only changed values
		for (i = 1, l = fnames.length; i < l; i++){
			var fname = fnames[i];
			el = Dom.get(fname);
			if( !Hul.isnull(el) ){
				if(el.type === "checkbox"){
					el.value = el.checked?'y':'n';
				}
				var old_val = String(_entity[i]);
				if(_recID<0 || (el.value!==old_val && !(el.value==="" && _entity[i]===null)))
				{
					_updatedFields.push(fname);
					_updatedDetails.push(el.value);
				}
			}
		}

		function __checkMandatory(field, label){

			if(Dom.get(field).value==="") {
				if(isShowWarn) {
					alert(label+" is a mandatory field");
				}
				Dom.get(field).focus();
				_updatedFields = [];
				return true;
			}else{
				return false;
			}
		}

		// check mandatory fields
		if(
			__checkMandatory("ugr_FirstName","First name") ||
			__checkMandatory("ugr_LastName","Last name") ||
			__checkMandatory("ugr_eMail","Email") ||
			__checkMandatory("ugr_Organisation","Institution/company") ||
			__checkMandatory("ugr_Interests","Research Interests") ||
			__checkMandatory("ugr_Name","Login") ||
			(_recID<0 && __checkMandatory("ugr_Password","Password"))
		){
				return "mandatory";
		}

		if(Dom.get("ugr_Password").value!==Dom.get("ugr_Password2").value){
				if(isShowWarn) {
					alert("Passwords are different");
				}
				Dom.get("ugr_Password2").focus();
				return "wrong_password";
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

				if(_isRegistration){
					//reload page with reg success message
					window.location.replace(top.HEURIST.baseURL + "admin/ugrps/msgRegistrationSuccess.html")
				}else{
					// this alert is a pain  alert("User with ID " + report + " was succesfully "+ss);
                    window.close(context); //send back new HEURIST strcuture
				}
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
		if(_fromUItoArray(true)!=="ok"){ //save all changes
			return;
		}

		var str = null;

		//2. creates object to be sent to server
		if(_recID !== null && _updatedFields.length > 0){
			var k,
				val;
			var oDataToServer = {user:{
				colNames:[],
				defs: {}
			}};

			var values = [];
			for(k = 0; k < _updatedFields.length; k++) {
				oDataToServer.user.colNames.push(_updatedFields[k]);
				values.push(_updatedDetails[k]);
			}

			oDataToServer.user.defs[_recID] = [];
			for(val in values) {
				oDataToServer.user.defs[_recID].push(values[val]);
			}
			str = YAHOO.lang.JSON.stringify(oDataToServer);
		}


		if(str !== null) {

			// 3. sends data to server
			var baseurl = top.HEURIST.baseURL + "admin/ugrps/saveUsergrps.php";
			var callback = _updateResult;
			var params = "method=saveUser&db=" + _db + "&data=" + encodeURIComponent(str);
			if(!Hul.isnull(_groupID)){
				params = params + "&groupID="+_groupID;
			}

			top.HEURIST.util.getJsonData(baseurl, callback, params);
		} else {
			window.close(null);
		}
	}

	/**
	* enable checkbox listener
	*/
	function _onChangeEnable(e){
/* IAN's request 2012-02-07
		var el = e.target;
		if(!el.checked) {
			var changeToDisabled = confirm("If you change user to disabled," +
											" you will no longer be able to change any "+
											"fields of this user after you save it.\n\nAre you sure?");
			if(changeToDisabled) {
					_toggleAll(true, true);
			} else {
					el.checked = true; //restore previous value
			}
		} else {
				_toggleAll(false, true);
		}
*/
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
