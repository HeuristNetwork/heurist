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
* editMimetype.js
* A form to edit mime types, or add a new one. It is utilized as pop-up from manageMimetypes
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


//aliases
var Dom = YAHOO.util.Dom,
	Hul = top.HEURIST.util;

/**
* ReportScheduleEditor - class for pop-up edit report schedules
*
* public methods
*
* save - sends data to server and closes the pop-up window in case of success
* cancel - checks if changes were made, shows warning and closes the window
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2011.0427
*/

function MimetypeEditor() {

		var _className = "MimetypeEditor",
			_entity, //object (mimetype) to edit
			_isNew = true,
			_updatedFields = [], //field names which values were changed to be sent to server
			_updatedDetails = [], //field values
			_db;

	/**
	* Initialization of input form
	*/
	function _init() {

		_db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));

		// reads parameters from GET request
		if (location.search.length > 1) {
				top.HEURIST.parameters = top.HEURIST.parseParams(location.search);


				var _recID = top.HEURIST.parameters.recID;
				_isNew = (Hul.isempty(_recID) || Number(_recID)<1);

				if(!_isNew){
					_entity = top.HEURIST.editMimetype.records[_recID];
				}
		}

		if (!_isNew && Hul.isnull(_entity) ){
			_isNew = true;
			Dom.get("statusMsg").innerHTML = "<strong>Error: Mime type was not found. Clicking 'save' button will create a new one.</strong><br /><br />";
		}
		//creates new empty field type in case ID is not defined
		if(Hul.isnull(_entity)){
			_isNew = true;
		  //"fxm_Extension", "fxm_MimeType", "fxm_OpenNewWindow", "fxm_FiletypeName", "fxm_IconFileName", "fxm_ImagePlaceholder", "fxm_Modified"
			_entity = ['','',0,'','','',0];
		}

		_fromArrayToUI();
	}

	/**
	* Fills inputs with values from _entity array
	*/
	function _fromArrayToUI(){

		var i,
			el,
			fnames = top.HEURIST.editMimetype.fieldNames;

		for (i = 0, l = fnames.length; i < l; i++) {
			var fname = fnames[i];
			el = Dom.get(fname);
			if(!Hul.isnull(el)){

				el.value = _entity[i];
			}
		}

		var ele_id = Dom.get("fxm_Extension");
		if (_isNew){
			ele_id.value = "";
			ele_id.disabled = false;
			setTimeout(function(){ele_id.focus();}, 1000);
		}else{
			ele_id.disabled = true;
			setTimeout(function(){Dom.get("fxm_MimeType").focus();}, 1000);
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
			fnames = top.HEURIST.editMimetype.fieldNames;

		//take only changed values
		for (i = 0, l = fnames.length; i < l; i++){
			var fname = fnames[i];
			el = Dom.get(fname);
			if( !Hul.isnull(el) ){
				if(_isNew || (el.value!==String(_entity[i]) && !(el.value==="" && _entity[i]===null)))
				{
					// DEBUG alert(el.value+" "+String(_entity[i]));
					_updatedFields.push(fname);
					_updatedDetails.push(el.value);
				}
				if(Hul.isempty(el.value) && (fname==='fxm_Extension' || fname==='fxm_MimeType')) {
					if(isShowWarn) {
						alert( (fname==='fxm_Extension'?'Extension':'Mime Type')+" is mandatory field");
					}
					el.focus();
					_updatedFields = [];
					return "mandatory";
				}
			}
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
						//_recID = item;
						/*if(report!=="") {
							report = report + ",";
						}
						report = report + Math.abs(_recID);
						*/
					}
				}
			}

			if(!error){
				var ss = (_isNew)?"added":"updated";

				// this alert is a pain  alert("Report schedule with ID " + report + " was succesfully "+ss);
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
			var oDataToServer = {entity:{
				colNames:[],
				defs: {}
			}};

			var values = [];
			for(k = 0; k < _updatedFields.length; k++) {
				oDataToServer.entity.colNames.push(_updatedFields[k]);
				values.push(_updatedDetails[k]);
			}
			var _recID = _isNew? -1: Dom.get("fxm_Extension").value;

			oDataToServer.entity.defs[_recID] = [];
			for(val in values) {
				oDataToServer.entity.defs[_recID].push(values[val]);
			}
			str = YAHOO.lang.JSON.stringify(oDataToServer);
		}


		if(!Hul.isempty(str)) {
//DEBUG alert("Stringified changes: " + str);

			// 3. sends data to server
			var baseurl = top.HEURIST.basePath + "admin/structure/srvMimetypes.php";
			var callback = _updateResult;
			var params = "method=save&db=" + _db + "&data=" + encodeURIComponent(str);
			Hul.getJsonData(baseurl, callback, params);
		} else {
			window.close(null);
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
