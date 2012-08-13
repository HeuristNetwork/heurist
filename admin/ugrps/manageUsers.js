/**
* manageUsers.js
* UserManager object for listing and searching of users
*
* @version 2011.0510
* @author: Artem Osmakov
*
* @copyright (C) 2005-2011 University of Sydney Digital Innovation Unit.
* @link: http://HeuristScholar.org
* @license http://www.gnu.org/licenses/gpl-3.0.txt
* @package Heurist academic knowledge management system
* @todo
**/
var userManager;

//aliases
var Dom = YAHOO.util.Dom,
	Hul = top.HEURIST.util;

/**
* UserManager - class for listing and searching of users
*
* @param _isFilterMode - either select from all users or filtering of existing set of users
* @param _isSelection - is selection column is visible
* @param _isWindowMode - true in window popup, false in div

* @author Artem Osmakov <osmakov@gmail.com>
* @version 2011.0510
*/
function UserManager(_isFilterMode, _isSelection, _isWindowMode) {

		var _className = "UserManager",
			_myDataTable,
			_myDataSource,
			_arr_selection = [],
			_callback_func, //callback function for non-window mode
			_initRecID, //to open edit user at once
			_grpID, //if group is defined as parameter - filter by this group
			_db,
			_isSingleSelection = false,
			isNotAdmin = true,
			_workgroups;
		//
		// filtering UI controls
		//
		var filterTimeout = null,
			filterByName,
			filterByRole,
			filterByGroup,
			filterByDisable,
			filterBySelection1,
			filterBySelection2,
			lblSelect1,
			lblSelect2;

		//for tooltip
		var currentTipId,
			needHideTip = true,
			hideTimer,
			infoMessageBox;

		var _roles = [{value:"admin", text:"admin"},{value:"member", text:"member"}];
		//{value:"invited", text:"invited", enabled:false},{value:"request", text:"request", disabled:true },
		//{value:"delete", text:"remove"} ];

	/**
	* Result handler for search on server
	*/
	var _updateUserList = function (context) {

		var arr = [],
			user, ind;


		for (ind in context.userslist) {
		if (!Hul.isnull(ind) )
		{
			user = context.userslist[ind];
			user[0] = false; //_arr_selection.indexOf(ind)>=0;
			arr.push(user);
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

							var filter_name   = filterByName.value;
							var filter_group  = (_isSelection || Hul.isnull(_grpID)) ?filterByGroup.value:_grpID;
							var filter_nogroup = (_isSelection && !Hul.isnull(_grpID)) ?_grpID:"all";
							var filter_role   = filterByRole.value;
							var filter_disabled = ((filterByDisable && filterByDisable.checked)?1:0);

							var baseurl = top.HEURIST.baseURL + "admin/ugrps/loadUserGrps.php";
							var callback = _updateUserList;
							var params = "method=searchuser&db=" + _db +
												"&nogrpID=" + filter_nogroup +
												"&grpID=" + filter_group + "&grpRole=" + filter_role +
												"&name="+encodeURIComponent(filter_name) + "&disabled=" + filter_disabled;
							top.HEURIST.util.getJsonData(baseurl, callback, params);



							//Dom.get("btnSelectAdd1").style.display = (filter_group==="all")?"none":"block";
							//Dom.get("btnSelectAdd2").style.display = (filter_group==="all")?"none":"block";

							isNotAdmin = _isNotAdmin(filter_group);

							var nstyle = (!_isSelection && (filter_group==="all" || !isNotAdmin))?"inline-block":"none";
							var nstyle2 = (filter_group==="all")?"none":"inline-block";

							//hide both buttons in case not admin
							Dom.get("pnlCtrlEdit1").style.display	 = nstyle;
							Dom.get("pnlCtrlEdit2").style.display	 = nstyle;

							//hide only select button in case all groups
							Dom.get("pnlFilterByRole").style.display = nstyle2;
							Dom.get("btnSelectAdd1").style.display	 = nstyle2;
							Dom.get("btnSelectAdd2").style.display	 = nstyle2;

							if(_myDataTable) {
									var col_1 = _myDataTable.getColumn("role");
									var col_2 = _myDataTable.getColumn("kickoff");
									if(filter_group==="all"){
										col_1._elThLabel.innerHTML = "";
										col_2._elThLabel.innerHTML = "";
									}else{
										col_1._elThLabel.innerHTML = "Role";
										col_2._elThLabel.innerHTML = "Remove";
									}

									if (filter_group==="all" || isNotAdmin) { //top.HEURIST.is_admin() ||
										col_1.formatter = null;
									}else{
										col_1.formatter = YAHOO.widget.DataTable.formatDropdown;
									}

									/*var col = _myDataTable.getColumn("role2");
									col.hidden = !_isSelection || !isNotAdmin;*/

									/*_myDataTable.getColumn("role").hidden = _isSelection ||
											filter_group==="all" || top.HEURIST.is_admin() || isNotAdmin;

									_myDataTable.getColumn("role").label = "Role";
									*/
									var ishidden = _isSelection ||	top.HEURIST.is_admin() || isNotAdmin;
									//_myDataTable.getColumn("kickoff").hidden = ishidden;
									//_myDataTable.getColumn("kickoff").width = ishidden?0:20;
							}


							//do it only once - on load
							if(!Hul.isnull(_initRecID)){
								_editUser(_initRecID);
								_initRecID = null

							}


	};

	function _isNotAdmin(__grpID){

		if(__grpID === "all"){
			return true;
		}else{
			var curruser_id = String(top.HEURIST.get_user_id());
			var grpID;
			for (grpID in _workgroups)
			{
				if(grpID === __grpID){
					var ind;
					var admins = _workgroups[grpID].admins;
					for (ind in admins){
						if(!Hul.isnull(ind) && admins[ind].id === curruser_id)
						{
							return false;
						}
					}
					return true;
				}
			}
		}
		return true;
	}

	/**
	* Updates LOCAL filter conditions for datatable
	*/
	var _updateFilterLocal  = function () {

							var filter_select = ((filterBySelection2 && filterBySelection2.checked)?1:0);
							// Reset sort
							var state = _myDataTable.getState();

							state.sortedBy = {key:'name', dir:YAHOO.widget.DataTable.CLASS_ASC};
							// Get filtered data
							_myDataSource.sendRequest(filter_select,
							{
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
	function _init(grpID, _callback)
	{

		infoMessageBox  =
				new YAHOO.widget.SimpleDialog("simpledialog2",
					{ width: "350px",
					fixedcenter: true,
					modal: false,
					visible: false,
					draggable: false,
					close: false,
					text: "some text"
				} );
		infoMessageBox.render(document.body);

		top.HEURIST.parameters = top.HEURIST.parseParams(this.location.search);
		
		if(top.HEURIST.parameters){ //to open edit user at once
			_initRecID = top.HEURIST.parameters.recID;
		}

		_callback_func = _callback;
		_db = (top.HEURIST.parameters && top.HEURIST.parameters.db?
				top.HEURIST.parameters.db :
				(top.HEURIST.database && top.HEURIST.database.name?
					top.HEURIST.database.name:''));

				if (Hul.isnull(grpID) && location.search.length > 1) {
									//window.HEURIST.parameters = top.HEURIST.parseParams(location.search);
									

									grpID = top.HEURIST.parameters.grpID;

									_isSelection = (top.HEURIST.parameters.selection === "1");

									_setMode("selection", _isSelection);
									_setMode("listing", !_isSelection);

							if (!_db) {
								_db = (top.HEURIST.parameters && top.HEURIST.parameters.db?
										top.HEURIST.parameters.db : '');
							}
									//list of selected
									var sIDs = top.HEURIST.parameters.ids;
									if (sIDs) {
										_arr_selection = sIDs.split(',');
									}

				}

				_grpID = grpID;


//				Dom.get('currUserInfo').innerHTML = 'DEBUG '+top.HEURIST.get_user_name();


				//init listeners for filter controls
				_initListeners();

				//load list of groups
				_initGroupSelector(); //then it call _initTable([]);
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
										fields: ["selection", "id", "name", "fullname", "email", "enabled", "organisation", "role"]
									},
									doBeforeCallback : function (req, raw, res, cb) {
										// This is the filter function
										var data  = res.results || [],
										filtered = [],
										i,l;

										if (req) {
											//parse request
											var fvals = req.split("|");

											var isBySelect = (fvals[0]==="0");
											var sByRole  = (fvals.length>1)?fvals[1]:"all";
											var sByName  = (fvals.length>2)?fvals[2].toLowerCase():"";

											for (i = 0, l = data.length; i < l; ++i)
											{
												if ((sByRole==="all" || data[i].role===sByRole) &&
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
			{ key: "selection", label: "Sel", hidden:(!_isSelection), sortable:true, width:20,
				formatter:YAHOO.widget.DataTable.formatCheckbox, className:'center' },

			{ key: "id", label: "#", sortable:true, className:'right',resizeable:false},

			{ key: null, label: "Active", sortable:false,  hidden:(_isSelection), className:'center',
				formatter: function(elLiner, oRecord, oColumn, oData) {
					var isenabled = (oRecord.getData('enabled')==="y");
					elLiner.innerHTML = (isenabled)?"<img src=\"../../common/images/tick-grey.gif\">":"";
			}},
			{ key: null, label: "Edit", sortable:false,  hidden:(_isSelection), width:20,
				formatter: function(elLiner, oRecord, oColumn, oData) {
elLiner.innerHTML = '<a href="#edit_user"><img src="../../common/images/edit-pencil.png" width="16" height="16" border="0" title="Edit"><\/a>';}
			},
			{ key: "name", label: "<div align='left'><u>Name</u></div>", sortable:true,
				formatter: function(elLiner, oRecord, oColumn, oData){
					if(Hul.isempty(oRecord.getData('email'))){
						elLiner.innerHTML = oRecord.getData('name');
					}else{
						elLiner.innerHTML = '<a href="mailto:'+oRecord.getData('email')+
						'" title="'+oRecord.getData('email')+'">'+
						oRecord.getData('name')+'</a>';
					}
					}},
			{ key: "fullname", label: "<div align='left'>Full name</div>", sortable:false,
				formatter: function(elLiner, oRecord, oColumn, oData) {
					var str = oRecord.getData("fullname");
					var tit = "";
					if(Hul.isempty(str)){
						str = "";
					}else if (str.length>40) {
						tit = str;
						str = str.substr(0,40)+"&#8230";
					}
					elLiner.innerHTML = '<label title="'+tit+'">'+str+'</label>';
			}},
			{ key: "organisation", label: "<div align='left'>Institution/Organisation</div>",
				hidden:(_isSelection), sortable:true, width:200,
				formatter: function(elLiner, oRecord, oColumn, oData) {
					var str = oRecord.getData("organisation");
					var tit = "";
					if(Hul.isempty(str)){
						str = "";
					}else if (str.length>35) {
						tit = str;
						str = str.substr(0,35)+"&#8230";
					}
					elLiner.innerHTML = '<label title="'+tit+'">'+str+'</label>';
			}},

			{ key: "role2", label: "Role", sortable:false, hidden: true, width:70,
				formatter: function(elLiner, oRecord, oColumn, oData) {
							elLiner.innerHTML = oRecord.getData('role');
				}},
			{ key: "role", label: "Role", sortable:false, hidden: _isSelection, width:90,
				/*formatter:function(elLiner, oRecord, oColumn, oData, oDataTable){

					var filter_group = (_isSelection || Hul.isnull(_grpID)) ?filterByGroup.value:_grpID;
					var is_NotAdmin = _isNotAdmin(filter_group);

					if (filter_group==="all" || is_NotAdmin) { //top.HEURIST.is_admin() ||
						elLiner.innerHTML = (filter_group==="all")?"":oRecord.getData('role');
					}else{
							oColumn.formatter = YAHOO.widget.DataTable.formatDropdown;
							//( elLiner , oRecord , oColumn , oData , oDataTable);
					}
				},*/
				dropdownOptions: _roles },
			{ key: "id", label: "Delete", width:30, className:'center', sortable:false, hidden:(_isSelection || !top.HEURIST.is_admin() ||  !Hul.isnull(_workgroups[_grpID]) ),
				formatter: function(elLiner, oRecord, oColumn, oData) {
						if(Number(oRecord.getData('id'))===2){
elLiner.innerHTML = "<img src=\"../../common/images/lock_bw.png\" title=\"System user - Locked\">";
						}else{
elLiner.innerHTML = '<a href="#delete_user"><img src="../../common/images/cross.png" title="Delete this User" /><\/a>';
						}
				}
			},
			{ key: "kickoff", label: "Remove", width:30, className:'center', sortable:false, hidden: _isSelection,
				formatter: function(elLiner, oRecord, oColumn, oData) {

					var filter_group = (_isSelection || Hul.isnull(_grpID)) ?filterByGroup.value:_grpID;
					var isNotAdmin = (filter_group==="all" || _isNotAdmin(filter_group));

					if(isNotAdmin || Number(oRecord.getData('id'))===top.HEURIST.get_user_id()){
						elLiner.innerHTML = "";
					}else{
						//oColumn._elThLabel.innerHTML = "Remove";
elLiner.innerHTML = '<a href="#kickoff_user"><img src="../../common/images/cross.png" border="0" title="Delete this User from group" /><\/a>';
					}
				}
			}

								];

		// Define a custom row formatter function
		var myRowFormatter = function(elTr, oRecord) {
			if (oRecord.getData('enabled')!=="y") {
				Dom.addClass(elTr, 'inactive');
			}
			return true;
		};

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
									}),
									formatRow: myRowFormatter
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

				if(elLink.hash === "#edit_user") {
					YAHOO.util.Event.stopEvent(oArgs.event);
					_editUser(recID);

				}else if(elLink.hash === "#kickoff_user"){
					YAHOO.util.Event.stopEvent(oArgs.event);

					var groupToBeUpdated = (Hul.isnull(_grpID)?filterByGroup.value:_grpID);

					var baseurl = top.HEURIST.baseURL + "admin/ugrps/saveUsergrps.php";
					var params = "method=changeRole&db="+_db+"&recID=" + groupToBeUpdated +
								"&oldrole=member&role=delete&recIDs="+encodeURIComponent(recID);
					top.HEURIST.util.getJsonData(baseurl, _updateRoles, params);

				}else if(elLink.hash === "#delete_user"){

					YAHOO.util.Event.stopEvent(oArgs.event);

						var value = confirm("Do you really want to delete user '"+oRecord.getData('fullname')+"'?");
						if(value) {

							function _updateAfterDelete(context) {

								if(Hul.isnull(context) || !context){
									alert("Unknown error on server side");
								}else if(Hul.isnull(context.error)){
									dt.deleteRow(oRecord.getId(), -1);
									alert("User #"+recID+" was deleted");
								} /*else {
									// if error is property of context it will be shown by getJsonData
									//alert("Deletion failed. "+context.error);
								}*/
							}

							var baseurl = top.HEURIST.baseURL + "admin/ugrps/saveUsergrps.php";
							var callback = _updateAfterDelete;
							var params = "method=deleteUser&db=" + _db + "&recID=" + recID;
							top.HEURIST.util.getJsonData(baseurl, callback, params);

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

			//role selector handler
			_myDataTable.subscribe('dropdownChangeEvent', function(oArgs){

				var groupToBeUpdated = (Hul.isnull(_grpID)) ?filterByGroup.value:_grpID;
				if (Hul.isnull(groupToBeUpdated) || groupToBeUpdated==="all") { return; }

				var elDropdown = oArgs.target;
				var record = this.getRecord(elDropdown);
				var column = this.getColumn(elDropdown);
				var newValue = elDropdown.options[elDropdown.selectedIndex].value;
				var oldValue = record.getData(column.key);

				if(newValue!=="invited" && newValue!=="request" && newValue!==oldValue)
				{
					var data = record.getData();

					function __onUpdateRole(context){
						if(_updateRole(context)){

							infoMessageBox.setBody("Role has been changed");
							infoMessageBox.show();
							setTimeout(function(){infoMessageBox.hide();}, 1000);

							data.role = newValue;
							if(newValue==="delete"){
								_updateFilter();
							}

						}else{ //restore previous value in dropdown
							elDropdown.value = oldValue;
						}
					}

					//keep the track of changes in special object
					//TODO _updateUser(record);
					var baseurl = top.HEURIST.baseURL + "admin/ugrps/saveUsergrps.php";
					var params = "method=changeRole&db="+_db+"&recID=" + groupToBeUpdated +
								"&oldrole=" + oldValue+
								"&role=" + newValue+"&recIDs="+encodeURIComponent(data.id);
					top.HEURIST.util.getJsonData(baseurl, __onUpdateRole, params);

				}
			});

			_updateFilter(); //fill table after creation

	}//end of initialization =====================


	/**
	*
	*/
	function _updateRole(context) {
		if(Hul.isnull(context) || !context) {
			alert("Server side error");
		}else if(context.error){
				alert("An error occurred trying to change role: "+context.error);
		}else if(context.errors && context.errors.length>0){
			var ind;
			var rep = "";
			for (ind in context.errors){
				if (!Hul.isnull(ind) ){
					rep = rep + context.errors[ind]+" ";
				}
			}

			alert(rep);
		}else{
			return true;
		}
		return false;
	}

	/**
	*
	*/
	function _updateRoles(context) {
		if(_updateRole(context)){
			_updateFilter();
		}
	}


	//
	//
	function clearHideTimer(){
		if (hideTimer) {
			window.clearTimeout(hideTimer);
			hideTimer = 0;
		}
	}
	function _hideToolTip(){
		if(needHideTip){
			currentTipId = null;
			clearHideTimer();
			var my_tooltip = $("#toolTip2");
			my_tooltip.css( {
				left:"-9999px"
			});
		}
	}
	/**
	* Show popup div with information
	* NOT USED
	*/
	function _showInfoToolTip(recID, event) {

				//tooltip div mouse out
				function __hideToolTip2() {
					needHideTip = true;
				}
				//tooltip div mouse over
				function __clearHideTimer2() {
					needHideTip = false;
					clearHideTimer();
				}

				var textTip = null;
				var forceHideTip = true;
				if(!Hul.isnull(recID)){
					if(currentTipId !== recID) {
						currentTipId = recID;

					} else {
						forceHideTip = false;
					}
				}
				if(!Hul.isnull(textTip)) {
					clearHideTimer();
					needHideTip = true;
					var my_tooltip = $("#toolTip2");

					my_tooltip.mouseover(__clearHideTimer2);
					my_tooltip.mouseout(__hideToolTip2);

					var xy = Hul.getMousePos(event);
					my_tooltip.html(textTip);  //DEBUG xy[0]+",  "+xy[1]+"<br/>"+

					Hul.showPopupDivAt(my_tooltip, xy, $(window).scrollTop(), $(window).width(), $(window).height());
					hideTimer = window.setTimeout(_hideToolTip, 5000);
				}
				else if(forceHideTip) {
					_hideToolTip();
				}

	}

	/**
	* Listener of checkbox in datatable
	* Adds or removes record  ID from array _arr_selection
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

									lblSelect1.innerHTML = "<b>"+_arr_selection.length+"</b> users"+((_arr_selection.length>1)?"s":"");
									if(!Hul.isnull(lblSelect2)) {
										lblSelect2.innerHTML = lblSelect1.innerHTML;
									}
	}


	/**
	*
	*/
	var _updateGroupList = function (context) {

		_workgroups = context.workgroups;
		//_workgroupIDs = context.workgroupIDs;

		_handlerGroupSelector();
		_initTable([]);
	};

	/**
	* Fills the selector (combobox) with names of groups
	* @see _init
	*/
	function _initGroupSelector(){

			filterByGroup = Dom.get('inputFilterByGroup');

			var baseurl = top.HEURIST.baseURL + "admin/ugrps/loadUserGrps.php";
			var callback = _updateGroupList;
			var params = "method=searchgroup&db=" + _db;
			top.HEURIST.util.getJsonData(baseurl, callback, params);
	}

	/**
	*
	*/
	function _handlerGroupSelector(e)
	{
		if(!( _isSelection || Hul.isnull(_grpID) )){

			if(Hul.isnull(_workgroups[_grpID])){
				_grpID = null;
			}else{
			//if group id is defined as parameter - hide filter by group div
				var divfil = Dom.get("pnlFilterByGroup");
				divfil.style.display = "none";
				Dom.get("pnlGroupTitle").style.display = "block";
				Dom.get("lblGroupTitle").innerHTML = "Members of <span class='recTypeName'>"+_workgroups[_grpID].name+"</span>";
				return;
			}
		}

		Dom.get("pnlGroupTitle").style.display = "none";

		if( _isSelection && !Hul.isnull(_grpID) && _grpID!=="all"){
			Dom.get("lblGroupTitleSelection").innerHTML = "Select and add users for group '"+_workgroups[_grpID].name+"'";
		}

		var sfilter;

		if(!Hul.isnull(e)) {
			sfilter = e.target.value.toLowerCase();
			if(sfilter.length<3) { sfilter = null; }
		}

		var grpID, grpName, option;

		//clear selection list
		while (filterByGroup.length>1){
				filterByGroup.remove(1);
		}

							// add
							for (grpID in _workgroups)
							{
								if(Hul.isnull(grpID) || grpID==="length"){
									continue;
								}

								grpName = _workgroups[grpID].name;

								if((Hul.isnull(sfilter) || (grpName.toLowerCase().indexOf(sfilter)>=0)) )
								{

									if(_isSelection && _grpID === grpID){
										continue; //exclude itself
									}

									Hul.addoption(filterByGroup, grpID, grpName);
								}
							} //for

		filterByGroup.onchange = _updateFilter;

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
								if(filterTimeout===null){
									clearTimeout(filterTimeout);
									filterTimeout = setTimeout(_updateFilter, 600);
								}
							};
				}

				filterByDisable = Dom.get('inputFilterByEnable1');
				if(filterByDisable) { filterByDisable.onchange = _updateFilter; }
				filterByDisable = Dom.get('inputFilterByEnable2');
				if(filterByDisable) { filterByDisable.onchange = _updateFilter; }

				filterBySelection1 = Dom.get('inputFilterBySelection1');
				if(filterBySelection1) { filterBySelection1.onchange = _updateFilterLocal; }
				filterBySelection2 = Dom.get('inputFilterBySelection2');
				if(filterBySelection2) { filterBySelection2.onchange = _updateFilterLocal; }

				lblSelect1 = Dom.get('lblSelect1');
				lblSelect2 = Dom.get('lblSelect2');
				var btnClear = Dom.get('btnClearSelection');
				if(btnClear) { btnClear.onclick = _clearSelection; }


				filterByRole = Dom.get('inputFilterByRole');
				if(!Hul.isnull(filterByRole)) { filterByRole.onchange = _updateFilter; }


				//var inputFilterGroup = Dom.get('inputFilterByGroup');
				//if(!Hul.isnull(inputFilterGroup)) { inputFilterGroup.onkeyup = _handlerGroupSelector; }

	} //end init listener

	/**
	* call new popup - to edit User
	*/
	function _editUser(user) {
		var URL = "";

		var userID = (!Hul.isnull(user))?Number(user):0;

		if(userID>0) {
			URL = top.HEURIST.basePath + "admin/ugrps/editUser.html?db=" + _db + "&recID="+userID;
		}
		else {
			//add new user to specified group
			var groupToBeUpdated = (Hul.isnull(_grpID)?filterByGroup.value:_grpID);
			if(!Hul.isnull(groupToBeUpdated) && groupToBeUpdated!=="all") {
				groupToBeUpdated = "&groupID="+groupToBeUpdated;
			}else{
				groupToBeUpdated = "";
			}

			URL = top.HEURIST.basePath + "admin/ugrps/editUser.html?db=" + _db + groupToBeUpdated;
		}
		top.HEURIST.util.popupURL(top, URL, {
			"close-on-blur": false,
			"no-resize": false,
			height: 560,
			width: 740,
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

	/**
	* Opens popup for selection of existing user to current group
	*/
	function _findAndAddUser() {

		var groupToBeUpdated = (Hul.isnull(_grpID)?filterByGroup.value:_grpID);

		var url = top.HEURIST.baseURL + "admin/ugrps/manageUsers.html?db=" +
										_db + "&selection=1&grpID="+groupToBeUpdated;

		top.HEURIST.util.popupURL(top, url,
		{   "close-on-blur": false,
			"no-resize": false,
			height: 600,
			width: 820,
			callback: function(usersSelected) {
				if(!Hul.isnull(usersSelected)){
//DEBUG alert(usersSelected);

					var baseurl = top.HEURIST.baseURL + "admin/ugrps/saveUsergrps.php";
					var params = "method=changeRole&db="+_db+"&recID=" + groupToBeUpdated +
								"&role=member&recIDs="+encodeURIComponent(usersSelected);
					top.HEURIST.util.getJsonData(baseurl, _updateRoles, params);


				}
			}
		});

	}

	/**
	* show either selection or listing controls
	*/
	function _setMode(className, val)
	{
		$("."+className).toggleClass(val?"activated":"deactivated", true);
		$("."+className).toggleClass(val?"deactivated":"activated", false);

		if(className==="selection"){
			$(".banner").toggleClass("activated", !val);
			$(".banner").toggleClass("deactivated", val);
		}
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
							window.close(res, _grpID);
						}else if (!Hul.isnull(_callback_func) ) {
							_callback_func(res, _grpID);
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

				/**
				* @param user - userID or email
				*/
				editUser: function(user){ _editUser( user ); },

				findAndAddUser: function(){ _findAndAddUser(); },

				//not used
				showInfo: function(recID, event){ _showInfoToolTip( recID, event ); },
				hideInfo: function() { hideTimer = window.setTimeout(_hideToolTip, 1000); },

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