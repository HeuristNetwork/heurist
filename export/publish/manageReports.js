/**
* manageReports.js
* ReportManager object for listing and searching of scheduled report
*
* @version 2012.0426
* @author: Artem Osmakov
*
* @copyright (C) 2005-2011 University of Sydney Digital Innovation Unit.
* @link: http://HeuristScholar.org
* @license http://www.gnu.org/licenses/gpl-3.0.txt
* @package Heurist academic knowledge management system
* @todo
**/
var reportManager;

//aliases
var Dom = YAHOO.util.Dom,
	Hul = top.HEURIST.util;

/**
* ReportManager - class for listing and searching of scheduled reports
*
* @param _isFilterMode - either select from all reports or filtering of existing set of reports (NOT USED)
* @param _isSelection - is selection column is visible (NOT USED)
* @param _isWindowMode - true in window popup, false in div

* @author Artem Osmakov <osmakov@gmail.com>
* @version 2012.0426
*/
function ReportManager(_isFilterMode, _isSelection, _isWindowMode) {

		var _className = "GroupManager",
			_myDataTable,
			_myDataSource,
			_usrID, //filter - @todo to show only reports that belongs to this user
			_arr_selection = [],
			_callback_func, //callback function for non-window mode
			_db,
			_isSingleSelection = false,
			_records; //array of all reports from server
		//
		// filtering UI controls
		//
		var filterByName,
			filterByType,
			filterBySelection1,
			filterBySelection2,
			lblSelect1,
			lblSelect2;


	/**
	* Updates REMOTE filter conditions and loads data from server side
	*/
	var _updateFilter  = function () {

				/**
				* Result handler for search on server
				*/
				function __updateRecordsList(context) {
					_records = context;
					_initTable(_records);
				};

				var sfilter = "";

				var baseurl = top.HEURIST.basePath + "export/publish/loadReports.php";
				var params = "method=searchreports&db=" + _db + sfilter;
				top.HEURIST.util.getJsonData(baseurl, __updateRecordsList, params);
	};


	/**
	* Updates filter conditions for datatable
	*/
	var _updateFilterLocal  = function () {

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
	* 2. create and fill table of reports
	* 3. fille selector for type of groups
	* 4. assign listeners for filter UI controls
	*/
	function _init(usrID, _callback)
	{
		_callback_func = _callback;
		_db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));

				if (Hul.isnull(usrID) && location.search.length > 1) { //for selection mode
					//window.HEURIST.parameters = top.HEURIST.parseParams(location.search);
					top.HEURIST.parameters = top.HEURIST.parseParams(location.search);
					//datatype = top.HEURIST.parameters.type;
					//list of selected
					var sIDs = top.HEURIST.parameters.ids;
					if (sIDs) {
							_arr_selection = sIDs.split(',');
					}

					if(!(Hul.isempty(top.HEURIST.parameters.hquery) || Hul.isempty(top.HEURIST.parameters.template)))
					{
						_onAddEditRecord(location.search);
					}
				}

				_usrID = usrID; //filter - show only this user reports

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
										fields: ["rps_ID", "rps_Type", "rps_Title", "rps_FilePath", "rps_URL", "rps_FileName", "rps_HQuery", "rps_Template", "rps_IntervalMinutes", "selection", "status"]
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
												(data[i].rps_Title.toLowerCase().indexOf(sByName)>=0))
												{
													data[i].selection = (_arr_selection.indexOf(data[i].rps_ID)>=0);
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

			{ key: "status", label: "Status", hidden:false, sortable:true,
				formatter:  function(elLiner, oRecord, oColumn, oData) {
					var status = Number(oRecord.getData('status'));
					if(status>0){
						var simg, shint;
						if(status==1){
							simg = 'url_error.png';
							shint = 'template file does not exsist';
						}else if(status==2){
							simg = 'url_warning.png';
							shint = 'output folder does not exsist';
						}else if(status==3){
							simg = 'url_warning.png';
							shint = 'generated report is not created yet';
						}
						elLiner.innerHTML = '<img src="../../common/images/'+simg+'" width="16" height="16" border="0" title="'+shint+'">';
					}else{
						elLiner.innerHTML = "";
					}

			}},

			{ key: "rps_ID", label: "#", sortable:true, className:'right',resizeable:false},

			{ key: null, label: "Edit", sortable:false,  width:5,
				formatter: function(elLiner, oRecord, oColumn, oData) {
					elLiner.innerHTML = '<a href="#edit_record"><img src="../../common/images/edit-pencil.png" width="16" height="16" border="0" title="Edit"><\/a>';
			}},

			{ key: null, label: "Execute", sortable:false,  width:5,
				formatter: function(elLiner, oRecord, oColumn, oData) {
					var status = Number(oRecord.getData('status'));
					if(status==1){
					elLiner.innerHTML = '';
					}else{
					var recID = oRecord.getData('rps_ID');
					elLiner.innerHTML = '<a href="../../viewers/smarty/updateReportOutput.php?db='+_db+'&publish=1&id='+recID+'" target="_blank"><img src="../../common/images/lightning.png" width="16" height="16" border="0" title="Run report"><\/a>';
//					elLiner.innerHTML = '<a href="#execute_report"><img src="../../common/images/lightning.png" width="16" height="16" border="0" title="Run report"><\/a>';
					}
			}},

			{ key: "rps_Title", label: "Title", sortable:true, resizeable:true},
			{ key: "rps_HQuery", label: "Query", sortable:false, resizeable:true},
			{ key: "rps_IntervalMinutes", label: "Interval", sortable:true, resizeable:false},

			{ key: null, label: "Del", className:'center', sortable:false,
				formatter: function(elLiner, oRecord, oColumn, oData) {
elLiner.innerHTML = '<div align="center"><a href="#delete_record"><img src="../../common/images/cross.png" border="0" title="Delete this Group"" /><\/a></div>';
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
				var recID = oRecord.getData("rps_ID");

				if(elLink.hash === "#edit_record") {
					YAHOO.util.Event.stopEvent(oArgs.event);
					_onAddEditRecord("?db="+_db+"&recID="+Number(recID));

				}else if(elLink.hash === "#delete_record"){
					YAHOO.util.Event.stopEvent(oArgs.event);

						var value = confirm("Do you really want to delete '"+oRecord.getData('rps_Title')+"'?");
						if(value) {

							function _updateAfterDelete(context) {

								if(Hul.isnull(context.error)){
									dt.deleteRow(oRecord.getId(), -1);
									alert("Report schedule #"+recID+" was deleted");
									top.HEURIST.rectypes = context.rectypes;
								} /*else {
									// if error is property of context it will be shown by getJsonData
									//alert("Deletion failed. "+context.error);
								}*/
							}

							var baseurl = top.HEURIST.basePath + "export/publish/loadReports.php";
							var callback = _updateAfterDelete;
							var params = "method=deletereport&db=" + _db + "&recID=" + recID;
							top.HEURIST.util.getJsonData(baseurl, callback, params);

						}else{
							//alert("Impossible to delete");
						}

				}else if(eLink.hash === "#execute_report"){


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

			//init listeners for filter controls
			_initListeners();

			_updateFilter(); //fill table after creation - load data from server side

	}//end of initialization =====================

	/**
	* NOT USED HERE (selection mode)
	* Listener of checkbox in datatable
	* Adds or removes selected ID to/from array _arr_selection
	* Updates info label
	* @param elCheckbox - reference to checkbox element that is clicked
	*/
	function _toggleSelection(elCheckbox)
	{
									var newValue = elCheckbox.checked;
									var oRecord = _myDataTable.getRecord(elCheckbox);

									var data = oRecord.getData(),
										recID = data.rps_ID;
										data.selection = newValue;

									/* it works
									var recordIndex = this.getRecordIndex(oRecord);
									_myDataTable.updateRow(recordIndex, data);
									*/
									if(newValue){ //add
										if(_isSingleSelection){
											_arr_selection = [recID];
											window.close(recID);
										}else{//relmarker or resource
											_arr_selection.push(recID);
										}

									}else{ //remove
										var ind = _arr_selection.indexOf(recID);
										if(ind>=0){
											_arr_selection.splice(ind, 1);
										}
									}

									lblSelect1.innerHTML = "<b>"+_arr_selection.length+"</b> record type"+((_arr_selection.length>1)?"s":"");
									if(!Hul.isnull(lblSelect2)) {
										lblSelect2.innerHTML = lblSelect1.innerHTML;
									}
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
	* Assign event listener for filter UI controls
	* @see _init
	*/
	function _initListeners()
	{
				filterByName = Dom.get('inputFilterByName');
				if(!Hul.isnull(filterByName)){
							filterByName.onkeyup = function (e) {
								setTimeout(_updateFilterLocal, 600);
							};
				}

				/*to remove - not used
				filterBySelection1 = Dom.get('inputFilterBySelection1');
				if(filterBySelection1) { filterBySelection1.onchange = _updateFilterLocal; }
				filterBySelection2 = Dom.get('inputFilterBySelection2');
				if(filterBySelection2) { filterBySelection2.onchange = _updateFilterLocal; }

				lblSelect1 = Dom.get('lblSelect1');
				lblSelect2 = Dom.get('lblSelect2');
				var btnClear = Dom.get('btnClearSelection');
				if(btnClear) { btnClear.onclick = _clearSelection; }
				*/

	} //end init listener

	/**
	* Opens popup to add/edit record
	*/
	function _onAddEditRecord(params){

		var url = top.HEURIST.basePath + "export/publish/editReportSchedule.html";
		if(!Hul.isempty(params)){
			url = url + params;
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
				 * Cancel form - closes this window (NOT USED HERE)
				 */
				cancel : function () {
					if(_isWindowMode){
						window.close();
					}else if (!Hul.isnull(_callback_func) ) {
						_callback_func();
					}
				},

				editReport: function(recID){ _onAddEditRecord("?db="+_db+"&recID="+recID ); },

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