
/**
* manageReports.js
* ReportManager object for listing and searching of scheduled report
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

var reportManager;


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

		var _className = "ReportManager",
			_myDataTable,
			_myDataSource,
			_usrID, //filter - @todo to show only reports that belongs to this user
			_arr_selection = [],
			_callback_func, //callback function for non-window mode
			_isSingleSelection = false,
			_records, //array of all reports from server
			_keepParameters = null;
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
				function __updateRecordsList(response )
				{
                    if(response.status == window.hWin.ResponseStatus.OK){
					    _records = window.hWin.HEURIST4.util.isnull(response.data)?[]:response.data;
					    _initTable( _records );
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
				};

				var sfilter = "";
				var baseurl = window.hWin.HAPI4.baseURL + "export/publish/loadReports.php";
                var request = {method:'searchreports', db:window.hWin.HAPI4.database};
				window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, __updateRecordsList);
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

		_keepParameters = null;

				if (window.hWin.HEURIST4.util.isnull(usrID) && location.search.length > 1) { //for selection mode
					
					//list of selected
					var sIDs = window.hWin.HEURIST4.util.getUrlParameter('ids', location.search);
					if (sIDs) {
							_arr_selection = sIDs.split(',');
					}

					if(!(window.hWin.HEURIST4.util.isempty(
                                window.hWin.HEURIST4.util.getUrlParameter('hquery', location.search)) 
                        || window.hWin.HEURIST4.util.isempty(
                                window.hWin.HEURIST4.util.getUrlParameter('template', location.search))))
					{
						_keepParameters = location.search;
						//auto open _onAddEditRecord(_keepParameters);
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
				if(!window.hWin.HEURIST4.util.isnull(_myDataTable)){

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

			{ key: "status", label: "<div style='font-size:10;'>Status</div>", hidden:false, sortable:true,
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

			{ key: "rps_ID", label: "<div style='max-width:15px;'>#</div>", sortable:true, className:'right',resizeable:false},

			{ key: null, label: "<div style='font-size:10;'>Edit</div>", sortable:false,width:12,resizeable:false,
				formatter: function(elLiner, oRecord, oColumn, oData) {
					elLiner.innerHTML = '<a href="#edit_record"><img src="../../common/images/edit-pencil.png" width="16" height="16" border="0" title="Edit"></a>';
			}},

			{ key: null, label: "<div style='font-size:10;'>Exec</div>", sortable:false,resizeable:false,width:12,
				formatter: function(elLiner, oRecord, oColumn, oData) {
					var status = Number(oRecord.getData('status'));
					if(status==1){
					elLiner.innerHTML = '';
					}else{
					var recID = oRecord.getData('rps_ID');
					elLiner.innerHTML = '<a href="../../viewers/smarty/updateReportOutput.php?db='
                        +window.hWin.HAPI4.database+'&publish=1&id='
                        +recID
                        +'" target="_blank">'
                        +'<img src="../../common/images/lightning.png" width="16" height="16" border="0" title="Run report">'
                        +'</a>';
//					elLiner.innerHTML = '<a href="#execute_report"><img src="../../common/images/lightning.png" width="16" height="16" border="0" title="Run report"><\/a>';
					}
			}},

			{ key: null, label: "<div style='font-size:10;min-width:30px;'>HTML</div>", sortable:false,resizeable:false,width:18,
					formatter: function(elLiner, oRecord, oColumn, oData) {
					var status = Number(oRecord.getData('status'));
					if(status==1){
						elLiner.innerHTML = '';
					}else{
						var recID = oRecord.getData('rps_ID');
						elLiner.innerHTML = '<a href="../../viewers/smarty/updateReportOutput.php?db='
                        +window.hWin.HAPI4.database+'&publish=3&id='
                        +recID
                        +'" target="_blank">'
                        +'<img src="../../common/images/external_link_16x16.gif" width="16" height="16" border="0" title="HTML link">'
                        +'</a>';
					}
			}},
			{ key: null, label: "<div style='font-size:10;'>JS</div>", sortable:false,resizeable:false,width:7,
				formatter: function(elLiner, oRecord, oColumn, oData) {
					var status = Number(oRecord.getData('status'));
					if(status==1){
						elLiner.innerHTML = '';
					}else{
						var recID = oRecord.getData('rps_ID');
						elLiner.innerHTML = '<a href="../../viewers/smarty/updateReportOutput.php?db='
                        +window.hWin.HAPI4.database+'&publish=3&mode=js&id='
                        +recID+'" target="_blank">'
                        +'<img src="../../common/images/external_link_16x16.gif" width="16" height="16" border="0" title="JavaScript link"></a>';
					}
			}},


			{ key: "rps_Title", label: "Title", sortable:true, resizeable:true},
			{ key: "rps_HQuery", label: "Query", sortable:false, resizeable:true, 
				formatter: function(elLiner, oRecord, oColumn, oData) {
						var hquery = oRecord.getData('rps_HQuery');
						elLiner.innerHTML = "<div style='max-width:400px;overflow: hidden;white-space: nowrap;text-overflow: ellipsis;'>"+hquery+"</div>";
                        //"<div style='max-width:100px;'>"+hquery+"</div>";//substr(hquery, 25);
			}},
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
					_onAddEditRecord("?db="+window.hWin.HAPI4.database+"&recID="+Number(recID));

				}else if(elLink.hash === "#delete_record"){
					YAHOO.util.Event.stopEvent(oArgs.event);

                        window.hWin.HEURIST4.msg.showMsgDlg(
                            "Do you really want to delete '"+oRecord.getData('rps_Title')+"'?",
                            function(){ 
                                function _updateAfterDelete(response) {
                                    if(response.status == window.hWin.ResponseStatus.OK){
                                            dt.deleteRow(oRecord.getId(), -1);
                                            window.hWin.HEURIST4.msg.showMsgFlash(
                                                "Report schedule #"+recID+" was deleted",1000);
                                    }else{
                                        window.hWin.HEURIST4.msg.showMsgErr(response);
                                    }
                                }

                                var baseurl = window.hWin.HAPI4.baseURL + "export/publish/loadReports.php";
                                var callback = _updateAfterDelete;
                                var request = {method:'deletereport', db:window.hWin.HAPI4.database, recID:recID};
                                window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, callback);
                            }, 
                            {title:'Confirm',yes:'Continue',no:'Cancel'});

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
									if(!window.hWin.HEURIST4.util.isnull(lblSelect2)) {
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
							if(!window.hWin.HEURIST4.util.isnull(lblSelect2)) {lblSelect2.innerHTML = "";}
							_updateFilterLocal();
	}

	/**
	* Assign event listener for filter UI controls
	* @see _init
	*/
	function _initListeners()
	{
				filterByName = document.getElementById('inputFilterByName');
				if(!window.hWin.HEURIST4.util.isnull(filterByName)){
							filterByName.onkeyup = function (e) {
								setTimeout(_updateFilterLocal, 600);
							};
				}

				/*to remove - not used
				filterBySelection1 = document.getElementById('inputFilterBySelection1');
				if(filterBySelection1) { filterBySelection1.onchange = _updateFilterLocal; }
				filterBySelection2 = document.getElementById('inputFilterBySelection2');
				if(filterBySelection2) { filterBySelection2.onchange = _updateFilterLocal; }

				lblSelect1 = document.getElementById('lblSelect1');
				lblSelect2 = document.getElementById('lblSelect2');
				var btnClear = document.getElementById('btnClearSelection');
				if(btnClear) { btnClear.onclick = _clearSelection; }
				*/

	} //end init listener

	/**
	* Opens popup to add/edit record
	*/
	function _onAddEditRecord(params){

		var url = window.hWin.HAPI4.baseURL + "export/publish/editReportSchedule.html";
		if(!window.hWin.HEURIST4.util.isempty(params)){
			url = url + params;
		}

		window.hWin.HEURIST4.msg.showDialog( url, {
		    "close-on-blur": false,
			"no-resize": false,
			height: 400,
			width: 620,
			callback: function(context) {
				if(!window.hWin.HEURIST4.util.isnull(context)){

					//update id
					var recID = Math.abs(Number(context.data[0]));

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
						}else if (!window.hWin.HEURIST4.util.isnull(_callback_func) ) {
							_callback_func(res, _usrID);
						}
				},

				/**
				 * Cancel form - closes this window (NOT USED HERE)
				 */
				cancel : function () {
					if(_isWindowMode){
						window.close();
					}else if (!window.hWin.HEURIST4.util.isnull(_callback_func) ) {
						_callback_func();
					}
				},

				editReport: function(recID){
					_onAddEditRecord((recID<0 && _keepParameters)
                        ?_keepParameters
                        :"?db="+window.hWin.HAPI4.database+"&recID="+recID);
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