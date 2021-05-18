/*
* Copyright (C) 2005-2020 University of Sydney
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
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
// SelectRecordType object
// SelectRecordType object
var selectRecordType;

//aliases
var Dom = YAHOO.util.Dom,
	Hul = window.hWin.HEURIST4.util;

/**
* SelectRecordType - class for pop-up window to select record types for editing detail type
*
* @param _isFilterMode - either select from all rectypes or filtering of existing set of rectypes
* @param _isWindowMode - true in window popup, false in div

* public methods
*
* save
* cancel
*
* @author Artem Osmakov <artem.osmakov@sydney.edu.au>
* @version 2011.0427
*/
function SelectRecordType(_isFilterMode, _isWindowMode) {

		var _className = "SelectRecordType",
			_myDataTable,
			_myDataSource,
			_arr_selection = [],
            _arr_selection_on_init = [],
			datatype,  // datatype of parent detailtype: relmarker,resource or fieldsetmarker
			_callback_func, //callback function for non-window mode
			_dtyID;
		//
		// filtering UI controls
		//
		var filterTimeout = null,
			filterByName,
			filterByGroup,
			filterBySelection1,
			filterBySelection2,
			lblSelect1,
			lblSelect2;

		//for tooltip
		var currentTipId,
			needHideTip = true,
			hideTimer;

	/**
	* Updates filter conditions for datatable
	*/
	var _updateFilter  = function () {
							// Reset timeout
							filterTimeout = null;

							// Reset sort
							var state = _myDataTable.getState();
							state.sortedBy = {key:'name', dir:YAHOO.widget.DataTable.CLASS_ASC};

							var filter_name  = filterByName.value;
							var filter_group = filterByGroup.value;
							var filter_select = ((filterBySelection2.checked)?1:0);

							// Get filtered data
							_myDataSource.sendRequest(filter_group+'|'+filter_name+'|'+filter_select, {
								success : _myDataTable.onDataReturnInitializeTable,
								failure : _myDataTable.onDataReturnInitializeTable,
								scope   : _myDataTable,
								argument : { pagination: { recordOffset: 0 } } // to jump to page 1
							});
	};


	/**
	* Initialization of input form
	*
	* 1. Reads GET parameters
	* 2. create and fill table of record types
	* 3. fill combobox(selector) with rectype groups
	* 4. assign listeners for filter UI controls
	*/
	function _init(dtyID, _callback)
	{
		if(_callback) _callback_func = _callback;

				if (Hul.isnull(dtyID) && location.search.length > 1) {
                                    
                    datatype = window.hWin.HEURIST4.util.getUrlParameter('type', location.search);
                    var sIDs = window.hWin.HEURIST4.util.getUrlParameter('ids', location.search);

					if (!Hul.isempty(sIDs)) {
                        dtyID = window.hWin.HEURIST4.util.getUrlParameter('dtID', location.search);
						_arr_selection = sIDs.split(',');
                        _arr_selection_on_init = sIDs.split(',');
					}else{
						_arr_selection = [];
					}

					if (datatype==="select"){
                        Dom.get('btnApply1').value = "Select";
                        Dom.get('btnApply2').value = "Select";
                        //Dom.get('btnAllSelection').style.display = "block";
                        Dom.get('divDefineNew').style.display = "none";
                    }
				}


				_dtyID = dtyID;
				_reinit_table();
                Dom.get('inputFilterByName').focus();
	}

	function _reinit_table()
	{
				//////////////////// create data table
				var arr = [];
				var rty_ID;
				var rectype;
				lblSelect1 = Dom.get('lblSelect1');



				var fi = window.hWin.HEURIST4.rectypes.typedefs.commonNamesToIndex;

				if(_isFilterMode){


					if(Hul.isnull(_dtyID)){
						lblSelect1.innerHTML = "ERROR: Detail type was not found";
						return;
					}else{
						lblSelect1.innerHTML = "";
					}

					//get datatype by id
					var recsPtr = window.hWin.HEURIST4.detailtypes.typedefs[_dtyID].commonFields[window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex.dty_PtrTargetRectypeIDs];
					if(Hul.isempty(recsPtr)){
						_arr_selection = [];
					}else{
						_arr_selection = recsPtr.split(",");
					}

					var ind;
					for (ind in _arr_selection) {

							rty_ID = _arr_selection[ind];
							if(Hul.isempty(rty_ID) || rty_ID === "undefined") continue;

							rectype = window.hWin.HEURIST4.rectypes.typedefs[rty_ID].commonFields;

							arr.push([true, //selected
											"", //icon
											rectype[fi.rty_Name], //name
											rectype[fi.rty_Description], //descr
											rectype[fi.rty_Status], //status
											rectype[fi.rty_RecTypeGroupID], //group
											rty_ID
											]);
					} //for

					if(arr.length<1){
						_isFilterMode = false;
						_init(_dtyID, _callback);
						return;
					}

					datatype = window.hWin.HEURIST4.detailtypes.typedefs[_dtyID].commonFields[window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex.dty_Type];

						Dom.get('filtertoolbar').style.display = 'none';


				}else{
						Dom.get('filtertoolbar').style.display = 'block';

						//create datatable and fill it values of all rectypes
						for (rty_ID in window.hWin.HEURIST4.rectypes.typedefs) {
								if(!isNaN(Number(rty_ID)))
								{
										rectype = window.hWin.HEURIST4.rectypes.typedefs[rty_ID].commonFields;

										if(datatype!=="fieldsetmarker" || rectype[fi.rty_FlagAsFieldset]==="1")//??????????????SAW what is this  (flagAsFieldSet)
										{                                                                      //AO: only rectypes with this marker may be selected for fieldsetmarker
										arr.push([(_arr_selection.indexOf(rty_ID)>=0),
											"", //icon
											rectype[fi.rty_Name], //name
											rectype[fi.rty_Description], //descr
											rectype[fi.rty_Status], //status
											rectype[fi.rty_RecTypeGroupID], //group
											rty_ID
											]);
										}
								}
						}

						_showSelectCount();
				}

				if(Hul.isnull(_myDataTable)){

								_myDataSource = new YAHOO.util.LocalDataSource(arr, {
									responseType : YAHOO.util.DataSource.TYPE_JSARRAY,
									responseSchema : {
										fields: ["selection", "icon", "name", "description",  "status", "group", "id"]
									},
									doBeforeCallback : function (req, raw, res, cb) {
										// This is the filter function
										var data  = res.results || [],
										filtered = [],
										i,l;

										if (req) {
											//parse request
											var fvals = req.split("|");

											var sByGroup  = fvals[0];
											var sByName   = fvals[1].toLowerCase();
											var isBySelect = (fvals[2]==="0");

											for (i = 0, l = data.length; i < l; ++i)
											{
												if ((sByGroup==="all" || data[i].group===sByGroup) &&
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
								{ key: "selection", label: "Sel", /*???????*/ hidden:(datatype==="fieldsetmarker")/* why???????*/, sortable:true, formatter:YAHOO.widget.DataTable.formatCheckbox, className:'center' },
								{ key: "icon", label: "Icon", className:'center', sortable:false,
				formatter: function(elLiner, oRecord, oColumn, oData) {
					var id = oRecord.getData("id");

					var str1 = window.hWin.HAPI4.iconBaseURL + id;
					var thumb = window.hWin.HAPI4.iconBaseURL + "thumb/th_" + id + ".png";
					var icon ="<div class=\"rectypeImages\"><a href=\"#edit_icon\"><img src=\"../../../common/images/16x16.gif\" style=\"background-image:url("+str1+")\" id=\"icon"+id+"\"></a><div style=\"background-image:url("+thumb+");\" class=\"thumbPopup\"><a href=\"#edit_thumb\"><img src=\"../../../common/images/16x16.gif\" width=\"75\" height=\"75\">``</a></div></div>"
					elLiner.innerHTML = icon;
			}},
								{ key: "name", label: "<u>Name</u>", sortable:true },
								{ key: "description", hidden:true, sortable:false},
								{ key: "status", label: "<u>Status</u>", hidden:true, sortable:false },
								{ key: "group",   hidden:true},
								{ key: "id", label: "Info", sortable:false, formatter: function(elLiner, oRecord, oColumn, oData){
            elLiner.innerHTML = '<img src="../../../common/images/info.png" width="16" height="16" border="0" title="'+
                                oRecord.getData('description')+'"/>';}
								}

								];


								var myConfigs = {
									//selectionMode: "singlecell",
									/*paginator : new YAHOO.widget.Paginator({
										rowsPerPage: 100, // REQUIRED
										totalRecords: arr.length, // OPTIONAL
										containers: ['dt_pagination_top','dt_pagination_bottom'],
										// use a custom layout for pagination controls
										template: "&nbsp;Page: {PageLinks}", // Show {RowsPerPageDropdown} per page",
										// show all links
										pageLinks: YAHOO.widget.Paginator.VALUE_UNLIMITED
										// use these in the rows-per-page dropdown
										//, rowsPerPageOptions: [100]
									})*/
								};

								_myDataTable = new YAHOO.widget.DataTable('tabContainer', myColumnDefs, _myDataSource, myConfigs);

								//
								// subscribe on datatable events
								//
								_myDataTable.subscribe("checkboxClickEvent", function(oArgs) {
									//YAHOO.util.Event.stopEvent(oArgs.event);
									var elCheckbox = oArgs.target;
									_toggleSelection(elCheckbox);
								});

								function __hideToolTip(){
									if(needHideTip){
										currentTipId = null;
										__clearHideTimer();
										var my_tooltip = $("#toolTip2");
										if(my_tooltip){
											my_tooltip.css( {left:"-9999px" });
										}
									}
								}
								function __clearHideTimer(){
									if (hideTimer) {
										window.clearTimeout(hideTimer);
										hideTimer = 0;
									}
								}

/*
								//
								_myDataTable.on('cellMouseoutEvent', function (oArgs) {
									hideTimer = window.setTimeout(__hideToolTip, 2000);
								});

								//
								// mouse over help colums shows the datailed description
								//
								_myDataTable.on('cellMouseoverEvent', function (oArgs){

									__clearHideTimer();

									var forceHideTip = true,
										textTip = null,
										target = oArgs.target,
										column = this.getColumn(target),
										record = this.getRecord(target),
										xy;

									if (!Hul.isnull(column) && column.key === 'id') {

										var description = record.getData('description') || 'no further description';
										xy = [parseInt(oArgs.event.clientX,10) + 10 ,parseInt(oArgs.event.clientY,10) + 10 ];
										textTip = '<p>'+description+'</p>';
									}

									if(!Hul.isnull(textTip)){

										needHideTip = true;

										var my_tooltip = $("#toolTip2");
										if(Hul.isnull(my_tooltip)){ return; }

										my_tooltip.mouseover(function(){needHideTip = false; __clearHideTimer();});
										my_tooltip.mouseout(function(){ needHideTip = true;});

										var border_top = $(window).scrollTop();
										var border_right = $(window).width();
										var border_height = $(window).height();
										var left_pos;
										var top_pos;
										var offset = 15;
										if(border_right - (offset *2) >= my_tooltip.width() +  xy[0]) {
											left_pos = xy[0]+offset;
										} else {
											left_pos = border_right-my_tooltip.width()-offset;
										}

										if((border_top + offset *2) >=  xy[1] - my_tooltip.height()) {
											top_pos = border_top + offset + xy[1]; //
										} else {
											top_pos = border_top + xy[1] - my_tooltip.height()-offset;
										}
										if(top_pos + my_tooltip.height() > border_top+border_height){
											top_pos	= border_top + border_height - my_tooltip.height()-5;
										}


										//var lft = my_tooltip.css('left');
										my_tooltip.css( {
											left:left_pos+'px', top:top_pos+'px'
										});//.fideIn(500).fideOut(5000);
										//lft = my_tooltip.css('left');
										my_tooltip.html(textTip);
										hideTimer = window.setTimeout(__hideToolTip, 5000);
									}
									else if(forceHideTip)
									{
										__hideToolTip();
									}
								});//end _onCellMouseOver
*/
								// Subscribe to events for row selection
								_myDataTable.subscribe("rowMouseoverEvent", _myDataTable.onEventHighlightRow);
								_myDataTable.subscribe("rowMouseoutEvent", _myDataTable.onEventUnhighlightRow);
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


						//there are not filter and search controls
						if(!_isFilterMode){
							// init Group Combo Box Filter
							_initGroupComboBoxFilter();

											//init listeners for filter controls
							_initListeners();
							_updateFilter();
						}


				}else{
					// all stuff is already inited, change livedata in datasource only
					_myDataSource.liveData = arr;

					//refresh table
					_myDataSource.sendRequest("", {
								success : _myDataTable.onDataReturnInitializeTable,
								failure : _myDataTable.onDataReturnInitializeTable,
								scope   : _myDataTable,
								argument : { pagination: { recordOffset: 0 } } // to jump to page 1
					});

					_updateFilter();

				}

	}//end of initialization =====================


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
										if(datatype==="fieldsetmarker"){
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

									_showSelectCount();

	}

	function _showSelectCount(){
				lblSelect1.innerHTML = "<b>"+_arr_selection.length+"</b> selected";
				if(!Hul.isnull(lblSelect2)) {
					lblSelect2.innerHTML = lblSelect1.innerHTML;
				}
	}

	/**
	* Fills the selector (combobox) with names of group
	* @see _init
	*/
	function _initGroupComboBoxFilter()
	{

				filterByGroup = Dom.get('inputFilterByGroup');
				var grpID,
					index;

				for (index in window.hWin.HEURIST4.rectypes.groups) {
					if( !isNaN(Number(index)) ) {

						grpID = window.hWin.HEURIST4.rectypes.groups[index].id;
						var grpName = window.hWin.HEURIST4.rectypes.groups[index].name;

						window.hWin.HEURIST4.ui.addoption(filterByGroup, grpID, grpName);

						if(filterByGroup.length==2){
							filterByGroup.selectedIndex = 1;
							filterByGroup.value = grpID;
						}
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
			_updateFilter();
			return false;
	}

    /**
    * Listener of btnAllSelection
    * fill _arr_selection array with all rectypes
    */
    function _selectAll(){
            return false;
    }
    
	/**
	* Assign event listener for filte UI controls
	* @see _init
	*/
	function _initListeners()
	{
							filterByName = Dom.get('inputFilterByName');
							filterByName.onkeyup = function (e) {
								filterByGroup.selectedIndex = 0;
								clearTimeout(filterTimeout);
								setTimeout(_updateFilter, 600);
							};

							filterBySelection1 = Dom.get('inputFilterBySelection1');
							filterBySelection1.onchange = _updateFilter;
							filterBySelection2 = Dom.get('inputFilterBySelection2');
							filterBySelection2.onchange = _updateFilter;

							lblSelect1 = Dom.get('lblSelect1');
							lblSelect2 = Dom.get('lblSelect2');
							var btnClear = Dom.get('btnClearSelection');
							btnClear.onclick = _clearSelection;
                            var btnSelectAll = Dom.get('btnAllSelection');
                            btnSelectAll.onclick = _selectAll;

	} //end init listener

	function _onDefineNewType(){
            
        var maxh = (window.hWin?window.hWin.innerHeight:window.innerHeight);
        var sURL = window.hWin.HAPI4.baseURL +
            "admin/structure/rectypes/editRectype.html?supress=1&db="+window.hWin.HAPI4.database;
            
        window.hWin.HEURIST4.msg.showDialog(sURL, {    
                "close-on-blur": false,
                "no-resize": false,
                title: 'Record Type Title Mask Edit',
                height: maxh*0.9,
                width: 800,
                callback: function(context) {

                    if(!Hul.isnull(context)){
                        //refresh the local heurist
                        window.hWin.HEURIST4.rectypes = context.rectypes;


                        var _rtyID = Number(context.result[0]);
                        if(!isNaN(_rtyID)){
                            _arr_selection.push(""+Math.abs(_rtyID));
                        }

                        //new field type to be added - refresh list
                        _reinit_table();
                    }
                }
        });            
            
	}
    
    function _verifyThatRemoved_RecTypes_AreNotInUse(){
        
            if(_dtyID>0 && _arr_selection_on_init.length>0){

                var _arr_removed = _arr_selection_on_init.filter(function(id) {return _arr_selection.indexOf(id) < 0;});

                if(_arr_removed.length>0){
                    // verify whether term is in use in field that uses vocabulry
                    // if yes it means it cannot be moved into different vocabulary
                    var baseurl = window.hWin.HAPI4.baseURL + "admin/structure/saveStructure.php";
                        
                    var request = {method:'checkDtPtr', db:window.hWin.HAPI4.database,
                                     dty_ID:_dtyID, rty_ID:_arr_removed.join(',')};
                    window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, function(response){
            
                        if(response.status == window.hWin.ResponseStatus.OK){

                            var res = _arr_selection.join(",");

                            if(_isWindowMode){
                                window.close(res, _dtyID);
                            }else if (!Hul.isnull(_callback_func) ) {
                                _callback_func(res, _dtyID);
                            }                            
                                                
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }                                        
                    });
                        
                    return true;
                }
            }
            return false;
    }
	//
	//public members
	//
	var that = {

				/**
				* Reinitialization of form for new detailtype
				* @param dtyID - detail type id to work with
				* @param _callback - callback function that obtain 3 parameters all terms, disabled terms and dtyID
				*/
				reinit : function (dtyID, _callback) {
						_init(dtyID, _callback);
				},

				/**
				 *	Apply form - close this window and returns comma separated list of selected detail types
				 */
				returnSelection : function () {
                        //verify that new selection is correct
                    
                        if(_verifyThatRemoved_RecTypes_AreNotInUse()) return;
                    
						var res = _arr_selection.join(",");

						if(_isWindowMode){
							window.close(res, _dtyID);
						}else if (!Hul.isnull(_callback_func) ) {
							_callback_func(res, _dtyID);
						}
				},
                
                
				setFilterMode : function (val) {
					_isFilterMode = val;
				},

				onDefineNewType: function(){
					_onDefineNewType();
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

				getClass: function () {
					return _className;
				},

				isA: function (strClass) {
					return (strClass === _className);
				}

	};

	_init();  // initialize before returning
	return that;

};