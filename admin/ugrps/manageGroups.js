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
* brief description of file
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
/**
* manageGroups.js
* GroupManager object for listing and searching of groups
*
* @version 2011.0509
* @author: Artem Osmakov
*
* @copyright (C) 2005-2011 University of Sydney Digital Innovation Unit.
* @link: http://HeuristNetwork.org
* @license http://www.gnu.org/licenses/gpl-3.0.txt
* @package Heurist academic knowledge management system
* @todo
**/
var groupManager;

//aliases
var Dom = YAHOO.util.Dom,
	Hul = top.HEURIST.util;

/**
* GroupManager - class for listing and searching of groups
*
* @param _isFilterMode - either select from all groups or filtering of existing set of groups
* @param _isSelection - is selection column is visible
* @param _isWindowMode - true in window popup, false in div

* @author Artem Osmakov <osmakov@gmail.com>
* @version 2011.0509
*/
function GroupManager(_isFilterMode, _isSelection, _isWindowMode) {

		var _className = "GroupManager",
			_myDataTable,
			_myDataSource,
			_arr_selection = [],
			_callback_func, //callback function for non-window mode
			_usrID,
			_db,
			_isSingleSelection = false,
			_isadmin = false,
			_ispopup = false,
			_currentuserid = 0,
			_rolloverInfo,
			currentTipId,
			_workgroups; //array of all workgroups from server
		//
		// filtering UI controls
		//
		var filterTimeout = null,
			filterByName,
			filterByType,
			filterBySelection1,
			filterBySelection2,
			lblSelect1,
			lblSelect2,
			filterByMembership;

	/**
	* Result handler for search on server
	*/
	var _updateGroupList = function (context) {

		var arr = [],
			group, grp_ID;

		if(!Hul.isnull(context)){

			_workgroups = context.workgroups;

			for (grp_ID in _workgroups) {
				if(!Hul.isnull(grp_ID) && grp_ID !== "length")
				{
					group = _workgroups[grp_ID];

					arr.push([(_arr_selection.indexOf(grp_ID)>0),
							group.name, //name
							group.description, //descr
							group.url, //url
							null, //type
							group.memberCount, //membercount
							grp_ID
							]);
				}
			}
		}
		_initTable(arr);
	};

	/**
	* Updates REMOTE filter conditions and loads data from server side
	*/
	var _updateFilter  = function () {
							// Reset timeout
							filterTimeout = null;

							var sfilter = "";
							if(filterByMembership && !filterByMembership.checked){

								sfilter = "&userID=" +  _currentuserid + "&grpRole=";

								if(Dom.get('inputFilterByMembership2').checked){
									sfilter = sfilter + "any";
								}else if(Dom.get('inputFilterByMembership3').checked){
									sfilter = sfilter + "admin";
								}else if(Dom.get('inputFilterByMembership4').checked){
									sfilter = sfilter + "member";
								}
							}

							var baseurl = top.HEURIST.baseURL + "admin/ugrps/loadUserGrps.php";
							var callback = _updateGroupList;
							var params = "method=searchgroup&db=" + _db + sfilter;
							top.HEURIST.util.getJsonData(baseurl, callback, params);

	};


	/**
	* Updates filter conditions for datatable
	*/
	var _updateFilterLocal  = function () {
							// Reset timeout
							filterTimeout = null;

							// Reset sort
							var state = _myDataTable.getState();
							state.sortedBy = {key:'name', dir:YAHOO.widget.DataTable.CLASS_ASC};

							var filter_name   = filterByName.value;
							var filter_type   = "all";//filterByType.value;
							var filter_select = ((filterBySelection2 && filterBySelection2.checked)?1:0);

							// Get filtered data
							_myDataSource.sendRequest(filter_type+'|'+filter_name+'|'+filter_select, {
								success : _myDataTable.onDataReturnInitializeTable,
								failure : _myDataTable.onDataReturnInitializeTable,
								scope   : _myDataTable,
								argument : { pagination: { recordOffset: 0 } } // to jump to page 1
							});
	};


	/**
	* Initialization of form
	*
	* 1. Reads GET parameters
	* 2. create and fill table of groups
	* 3. fille selector for type of groups
	* 4. assign listeners for filter UI controls
	*/
	function _init(usrID, _callback)
	{
		_isadmin = top.HEURIST.is_admin();
		_currentuserid = top.HEURIST.get_user_id();

		_callback_func = _callback;
		_db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));

				if (Hul.isnull(usrID) && location.search.length > 1) {
									//window.HEURIST.parameters = top.HEURIST.parseParams(location.search);
									top.HEURIST.parameters = top.HEURIST.parseParams(location.search);
									//datatype = top.HEURIST.parameters.type;
									//list of selected
									var sIDs = top.HEURIST.parameters.ids;
									if (sIDs) {
										_arr_selection = sIDs.split(',');
									}

				}

		_rolloverInfo = new HintDiv('inforollover', 260, 170, '<div id="inforollover2"></div>');

		_ispopup = (Dom.get('titleBanner')==null);

				_usrID = usrID;
				//////////////////// create data table
				_initTable([]);
	}

	/**
	* Creates and (re)fill datatable
	*/
	function _initTable(arr)
	{

	//if datatable exists, only refill ==========================
				if(!Hul.isnull(_myDataTable)){

					// all stuff is already inited, change livedata in datasource only
					_myDataSource.liveData = arr;

					//refresh table
					_myDataSource.sendRequest("", {
								success : _myDataTable.onDataReturnInitializeTable,
								failure : _myDataTable.onDataReturnInitializeTable,
								scope   : _myDataTable,
								argument : { pagination: { recordOffset: 0 } } // to jump to page 1
					});

					return;
				}

	//create new datatable ==========================

								_myDataSource = new YAHOO.util.LocalDataSource(arr, {
									responseType : YAHOO.util.DataSource.TYPE_JSARRAY,
									responseSchema : {
										fields: ["selection", "name", "description", "url", "type", "members", "id"]
									},
									doBeforeCallback : function (req, raw, res, cb) {
										// This is the filter function
										var data  = res.results || [],
										filtered = [],
										i,l;

										if (req) {
											//parse request
											var fvals = req.split("|");

											var sByType  = fvals[0];
											var sByName   = fvals[1].toLowerCase();
											var isBySelect = (fvals[2]==="0");

											for (i = 0, l = data.length; i < l; ++i)
											{
												if ((sByType==="all" || data[i].type===sByType) &&
												(data[i].name.toLowerCase().indexOf(sByName)>=0))
												{
													data[i].selection = (_arr_selection.indexOf(data[i].id)>=0);
													if(isBySelect || data[i].selection){
														filtered.push(data[i]);
													}
												}
											}
											res.results = filtered;
										}

										return res;
									}
								});

								var myColumnDefs = [
			{ key: "selection", label: "Sel", hidden:(!_isSelection), sortable:true,
				formatter:YAHOO.widget.DataTable.formatCheckbox, className:'center' },

			{ key: "id", label: "#", sortable:true, className:'right',resizeable:false},

			{ key: null, label: "Admins", sortable:false, className:'center',
				formatter: function(elLiner, oRecord, oColumn, oData){
					var recID = oRecord.getData('id');
elLiner.innerHTML = '<div align="center"><img src="../../common/images/info.png" '+
'onmouseover="groupManager.showInfo('+recID+', event)" onmouseout="groupManager.hideInfo()"/></div>';}
			},
			{ key: null, label: "Edit", sortable:false,  width:20,
				formatter: function(elLiner, oRecord, oColumn, oData) {
					var recID = oRecord.getData('id');
					if(_isadmin || _isGroupAdmin(_currentuserid, recID) ){
elLiner.innerHTML = '<a href="#edit_group"><img src="../../common/images/edit-pencil.png" width="16" height="16" border="0" title="Edit"><\/a>';
					}else{
						elLiner.innerHTML = "<img src=\"../../common/images/lock_bw.png\" title=\"Status: Not Admin - Locked\">";
					}
			}},

			{ key: "members", label: "Edit Membership", sortable:false, className:'center',
				formatter: function(elLiner, oRecord, oColumn, oData){
elLiner.innerHTML = '<div align="center">'+
		'<a href="'+top.HEURIST.baseURL + "admin/ugrps/manageUsers.php?db=" +
		_db + "&grpID="+oRecord.getData("id")+(_ispopup?"&popup=yes":"")+
		'" title="Show listing of members/Edit membership"><span class="count_edit">'+oRecord.getData('members')+'</span></a></div>';

//IREK that does not work! <a href="#" onClick=top.HEURIST.search.popupLink("'+top.HEURIST.baseURL + 'admin/ugrps/manageUsers.//html?db=' +_db + '&grpID='+oRecord.getData("id")+'","wide") title="Show listing of members/Edit membership">
				}},
			{ key: "name", label: "<div align='left'><u>Name</u></div>", sortable:true,
				formatter: function(elLiner, oRecord, oColumn, oData){
					if(Hul.isempty(oRecord.getData('url'))){
						elLiner.innerHTML = oRecord.getData('name');
					}else{
						elLiner.innerHTML = '<a href="'+oRecord.getData('url')+
						'" target="_blank" title="'+oRecord.getData('url')+'">'+
						oRecord.getData('name')+'</a>';
					}
					}},
			{ key: "description", label: "<div align='left'>Description</div>", sortable:false,
				formatter: function(elLiner, oRecord, oColumn, oData) {
					var str = oRecord.getData("description");
					var tit = "";
					if(Hul.isempty(str)){
						str = "";
					}else if (str.length>70) {
						tit = str;
						str = str.substr(0,70)+"&#8230";
					}
					elLiner.innerHTML = '<label title="'+tit+'">'+str+'</label>';
			}},
			{ key: "type", hidden:true},
/*			{ key: "id", label: "Admins", sortable:false,
				formatter: function(elLiner, oRecord, oColumn, oData){
					var recID = oRecord.getData('id');
elLiner.innerHTML = '<div align="center"><img src="../../common/images/info.png" '+
'onmouseover="groupManager.showInfo('+recID+', event)" onmouseout="groupManager.hideInfo()"/></div>';}
			},
*/			{ key: null, label: "Del", className:'center', sortable:false,
				formatter: function(elLiner, oRecord, oColumn, oData) {
					/*r iusage = Number(oRecord.getData('members'));
					if(iusage>1){
						elLiner.innerHTML = "";
					}else{}*/
					var recID = oRecord.getData('id');
					if(Number(recID)===1)
					{
						elLiner.innerHTML = "<img src=\"../../common/images/lock_bw.png\" title=\"System group - Locked\">";
					//}else if(Number(oRecord.getData('members'))>1){
//elLiner.innerHTML = "<img src=\"../../common/images/lock_bw.png\" title=\"Impossible to delete group with members\">";
					} else if(_isadmin || _isGroupAdmin(_currentuserid, recID) )
					{
elLiner.innerHTML = '<div align="center"><a href="#delete_group"><img src="../../common/images/cross.png" border="0" title="Delete this Group"" /><\/a></div>';
					}else{
						elLiner.innerHTML = "<img src=\"../../common/images/lock_bw.png\" title=\"Status: Not Admin - Locked\">";
					}

				}
			}
								];


		var myConfigs = {
									//selectionMode: "singlecell",
									paginator : new YAHOO.widget.Paginator({
										rowsPerPage: 100, // REQUIRED
										totalRecords: arr.length, // OPTIONAL
										containers: ['dt_pagination_top','dt_pagination_bottom'],
										// use a custom layout for pagination controls
										template: "{PageLinks}",  //" Show {RowsPerPageDropdown} per page",
										// show all links
										pageLinks: YAHOO.widget.Paginator.VALUE_UNLIMITED
										// use these in the rows-per-page dropdown
										//, rowsPerPageOptions: [100]
									})
		};

		_myDataTable = new YAHOO.widget.DataTable('tabContainer', myColumnDefs, _myDataSource, myConfigs);

		//
		// subscribe on datatable events
		//
		if(_isSelection){
			_myDataTable.subscribe("checkboxClickEvent", function(oArgs) {
									//YAHOO.util.Event.stopEvent(oArgs.event);
									var elCheckbox = oArgs.target;
									_toggleSelection(elCheckbox);
			});
		}

		//click on action images
		_myDataTable.subscribe('linkClickEvent', function(oArgs){

				var dt = this;
				var elLink = oArgs.target;
				var oRecord = dt.getRecord(elLink);
				var recID = oRecord.getData("id");

				if(elLink.hash === "#edit_group") {
					YAHOO.util.Event.stopEvent(oArgs.event);
					_onAddEditGroup(recID);

				}else if(elLink.hash === "#delete_group"){
					YAHOO.util.Event.stopEvent(oArgs.event);

						var value = confirm("Do you really want to delete group '"+oRecord.getData('name')+"'?");
						if(value) {

							function _updateAfterDelete(context) {

								if(!Hul.isnull(context))
								{
									dt.deleteRow(oRecord.getId(), -1);
									alert("Group #"+recID+" was deleted");
									top.HEURIST.rectypes = context.rectypes;
								}
							}

							var baseurl = top.HEURIST.baseURL + "admin/ugrps/saveUsergrps.php";
							var callback = _updateAfterDelete;
							var params = "method=deleteGroup&db=" + _db + "&recID=" + recID;
							top.HEURIST.util.getJsonData(baseurl, callback, params);

						}else{
							//alert("Impossible to delete group which is in use");
						}

				}

		});

			// Subscribe to events for row selection
			_myDataTable.subscribe("rowMouseoverEvent", _myDataTable.onEventHighlightRow);
			_myDataTable.subscribe("rowMouseoutEvent", _myDataTable.onEventUnhighlightRow);
			if(_isSelection)
			{
			_myDataTable.subscribe("cellClickEvent", function(oArgs){

								//YAHOO.util.Event.stopEvent(oArgs.event);
								//var elTarget = oArgs.target;
								//var elTargetRow = _myDataTable.getTrEl(elTarget);
								var elTargetCell = oArgs.target;
								if(elTargetCell) {
									var oRecord = _myDataTable.getRecord(elTargetCell);
									//get first cell
									var cell = _myDataTable.getTdEl({record:oRecord, column:_myDataTable.getColumn("selection")});
									if(elTargetCell!==cell){
										var elCheckbox = cell.firstChild.firstChild;
										elCheckbox.checked = !elCheckbox.checked;
										_toggleSelection(elCheckbox);
									}
								}

								});//_myDataTable.onEventSelectRow);
			}

								_initTypeComboBoxFilter();

								//init listeners for filter controls
								_initListeners();


			_updateFilter(); //fill table after creation

	}//end of initialization =====================


	/**
	*
	*/
	function _isGroupAdmin(userID, recID){

						var admins = _workgroups[recID].admins;
						var index;
						for(index in admins) {
							if(!Hul.isnull(index) && Number(userID) === Number(admins[index].id)){
								return true;
							}
						}
						return false;
	}

/**
	* Show popup div with information about field types in use for given record type
	*/
	function _showInfoToolTip(recID, event) {

				var textTip = null;
				var forceHideTip = true;
				if(!Hul.isnull(recID)){
					if(currentTipId !== recID) {
						currentTipId = recID;

						var grpname = _workgroups[recID].name;
						if(grpname.length>40) { grpname = grpname.substring(0,40)+"&#8230"; }
						//find all records that reference this type
						var admins = _workgroups[recID].admins;
						textTip = '<h3>'+grpname+'</h3><br>'+
						'<b>Administrators:</b><label style="color: #999;margin-left:5px">Click on admin to edit</label><ul>';
						var index;
						for(index in admins) {
							if(!Hul.isnull(index)){
								textTip = textTip + "<li><a href='javascript:void(0)' onClick=\"groupManager.editUser('"+
											admins[index].id+"')\">" +
											admins[index].name + "</a></li>";  //"+detail.email+"
							}
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

	/**
	* Listener of checkbox in datatable
	* Adds or removes record type ID from array _arr_selection
	* Updates info label
	* @param elCheckbox - reference to checkbox element that is clicked
	*/
	function _toggleSelection(elCheckbox)
	{
									var newValue = elCheckbox.checked;
									var oRecord = _myDataTable.getRecord(elCheckbox);

									var data = oRecord.getData();
									data.selection = newValue;
									/* it works
									var recordIndex = this.getRecordIndex(oRecord);
									_myDataTable.updateRow(recordIndex, data);
									*/
									if(newValue){ //add
										if(_isSingleSelection){
											_arr_selection = [data.id];
											window.close(data.id);
										}else{//relmarker or resource
											_arr_selection.push(data.id);
										}

									}else{ //remove
										var ind = _arr_selection.indexOf(data.id);
										if(ind>=0){
											_arr_selection.splice(ind,1);
										}
									}

									lblSelect1.innerHTML = "<b>"+_arr_selection.length+"</b> record type"+((_arr_selection.length>1)?"s":"");
									if(!Hul.isnull(lblSelect2)) {
										lblSelect2.innerHTML = lblSelect1.innerHTML;
									}
	}

	/**
	* Fills the selector (combobox) with names of group types
	* it is hardcoded in HTML !!!
	* @see _init
	*/
	function _initTypeComboBoxFilter()
	{
		filterByType = Dom.get('inputFilterByType');
		if(!Hul.isnull(filterByType)) { filterByType.onchange = _updateFilterLocal; }

	}

	/**
	* Listener of btnClearSelection
	* Empties _arr_selection array
	*/
	function _clearSelection(){
							_arr_selection = [];
							lblSelect1.innerHTML = "";
							if(!Hul.isnull(lblSelect2)) {lblSelect2.innerHTML = "";}
							_updateFilterLocal();
	}

	/**
	* Assign event listener for filte UI controls
	* @see _init
	*/
	function _initListeners()
	{
				filterByName = Dom.get('inputFilterByName');
				if(!Hul.isnull(filterByName)){
							filterByName.onkeyup = function (e) {
								clearTimeout(filterTimeout);
								setTimeout(_updateFilterLocal, 600);
							};
				}

				filterBySelection1 = Dom.get('inputFilterBySelection1');
				if(filterBySelection1) { filterBySelection1.onchange = _updateFilterLocal; }
				filterBySelection2 = Dom.get('inputFilterBySelection2');
				if(filterBySelection2) { filterBySelection2.onchange = _updateFilterLocal; }

				lblSelect1 = Dom.get('lblSelect1');
				lblSelect2 = Dom.get('lblSelect2');
				var btnClear = Dom.get('btnClearSelection');
				if(btnClear) { btnClear.onclick = _clearSelection; }

				//remote filter
				filterByMembership = Dom.get('inputFilterByMembership2');
				if(filterByMembership) { filterByMembership.onchange = _updateFilter; }
				filterByMembership = Dom.get('inputFilterByMembership3');
				if(filterByMembership) { filterByMembership.onchange = _updateFilter; }
				filterByMembership = Dom.get('inputFilterByMembership4');
				if(filterByMembership) { filterByMembership.onchange = _updateFilter; }
				filterByMembership = Dom.get('inputFilterByMembership1');
				if(filterByMembership){
					if(_isadmin){
						Dom.get('lblForInputFilterByMembership1').style.display = "inline-block";
						filterByMembership.style.display = "inline-block";;
						filterByMembership.onchange = _updateFilter;
					}else{
						Dom.get('lblForInputFilterByMembership1').style.display = "none";
						filterByMembership.style.display = "none";
					}
				}

	} //end init listener

	//
	// call new popup - to edit User
	//
	function _editUser(user) {
		var URL = "";

		var userID = (!Hul.isnull(user))?Number(user):0;

		if(userID>0) {
			URL = top.HEURIST.baseURL + "admin/ugrps/editUser.html?db=" + _db + "&recID="+userID;
		} else if (userID<0) {
			URL = top.HEURIST.baseURL + "admin/ugrps/editUser.html?db=" + _db; //new user
		}else{
			return;
		//}else if(!Hul.isnull(user)){
		//	URL = top.HEURIST.baseURL + "admin/ugrps/editUser.html?db=" + _db + "&user="+user; //email
		}
		var dim = top.HEURIST.util.innerDimensions(window);
		top.HEURIST.util.popupURL(top, URL, {
			"close-on-blur": false,
			"no-resize": false,
			height:dim.h*0.8,
			width:dim.w*0.8,
			callback: function(changedValues) {
				/*if(Hul.isnull(changedValues)) {
					// Canceled
				} else {
					// TODO: reload datatable
				}*/
			}
		});
	}

	/**
	* Opens popup to add/edit group
	*
	*/
	function _onAddEditGroup(oArgs){

		var url = top.HEURIST.baseURL + "admin/ugrps/editGroup.html?db=" + _db;
		if(Number(oArgs)>0){
			url = url + "&recID="+Number(oArgs); //existing
		}

		top.HEURIST.util.popupURL(top, url,
		{   "close-on-blur": false,
			"no-resize": false,
			height: 480,
			width: 620,
			callback: function(context) {
				if(!Hul.isnull(context)){

					//update id
					var recID = Math.abs(Number(context.result[0]));

					//refresh table
					_updateFilter();

				}
			}
		});
	}

	//
	//public members
	//
	var that = {

				/**
				* Reinitialization of form for new detailtype
				* @param usrID - detail type id to work with
				* @param _callback - callback function that obtain 3 parameters all terms, disabled terms and usrID
				*/
				reinit : function (usrID, _callback) {
						_init(usrID, _callback);
				},

				/**
				 *	Apply form - close this window and returns comma separated list of selected detail types
				 */
				returnSelection : function () {
						var res = _arr_selection.join(",");

						if(_isWindowMode){
							window.close(res, _usrID);
						}else if (!Hul.isnull(_callback_func) ) {
							_callback_func(res, _usrID);
						}
				},

				/**
				 * Cancel form - closes this window
				 */
				cancel : function () {
					if(_isWindowMode){
						window.close();
					}else if (!Hul.isnull(_callback_func) ) {
						_callback_func();
					}
				},

				editGroup: function(recID){ _onAddEditGroup( recID ); },

				/**
				* @param user - userID or email
				*/
				editUser: function(user){ _editUser( user ); },
				showInfo: function(recID, event){ _showInfoToolTip( recID, event ); },
				hideInfo: function() { currentTipId = ''; _rolloverInfo.hide(); },
				//forcehideInfo: function() { hideTimer = window.setTimeout(_hideToolTip, 0); },

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