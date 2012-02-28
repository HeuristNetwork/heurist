var selectDetailType;

//aliases
var Dom = YAHOO.util.Dom,
	Hul = top.HEURIST.util;

/**
*
*/
function isnull(obj){
	return ((obj===null) || (obj===undefined));// old js way typeof(obj)==='undefined');
}

/**
* SelectDetailType - class for pop-up window to select detail types for record structure
*
* public methods
*
* apply -
* cancel -
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2011.0427
*/
function SelectDetailType() {

		var _className = "SelectDetailType",
			_myDataTable,
			_myDataSource,
			_arr_selection = [],
			rty_ID,  // the target record type (to filter out types that are already has been added)
			showTimer, hideTimer;

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
			needHideTip = true;

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
	* 2. create and fill table of detail types
	* 3. fill combobox(selector) with detail groups
	* 4. assign listeners for filter UI controls
	*/
	function _init() {

		if(isnull(_myDataTable)){

								// 1. Reads GET parameters
								if (location.search.length > 1) {
									//window.HEURIST.parameters = top.HEURIST.parseParams(location.search);
									top.HEURIST.parameters = top.HEURIST.parseParams(location.search);
									rty_ID = top.HEURIST.parameters.rty_ID;
									document.title = "Insert fields -> " + top.HEURIST.rectypes.names[rty_ID];
								}

								//////////////////// create data table
								var arr = [],
									dty_ID,
									fi = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex;

								//2. create datatable and fill it values of particular group
								for (dty_ID in top.HEURIST.detailTypes.typedefs) {
									if(!isNaN(Number(dty_ID)))
									{
										var td = top.HEURIST.detailTypes.typedefs[dty_ID];
										var deftype = td.commonFields;

										var aUsage = top.HEURIST.detailTypes.rectypeUsage[dty_ID];

										if(isnull(aUsage) || aUsage.indexOf(rty_ID)<0){

											var iusage = isnull(aUsage) ? 0 : aUsage.length;
											var ptr_1 = isnull(deftype[fi.dty_FieldSetRectypeID])?'':deftype[fi.dty_FieldSetRectypeID];
											var ptr_2 = isnull(deftype[fi.dty_PtrTargetRectypeIDs])?'':deftype[fi.dty_PtrTargetRectypeIDs];
											// add order in group, name, help, type and status,
											// doc will be hidden (for pop-up)
											arr.push([0,
												deftype[fi.dty_OrderInGroup],
												deftype[fi.dty_Name],
												deftype[fi.dty_HelpText],
												deftype[fi.dty_Type],
												deftype[fi.dty_Status],
												deftype[fi.dty_ExtendedDescription],
												deftype[fi.dty_DetailTypeGroupID],
												dty_ID,iusage,ptr_1,ptr_2]);

										}
									}
								}

								_myDataSource = new YAHOO.util.LocalDataSource(arr, {
									responseType : YAHOO.util.DataSource.TYPE_JSARRAY,
									responseSchema : {
										fields: ["selection", "order", "name", "help",  "type", "status", "description", "group", "info", "usage", "fieldset_rectypeid", "ptrtarget_rectypeids"]
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
													data[i].selection = (_arr_selection.indexOf(data[i].info)>=0);
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
								{ key: "selection", label: "", sortable:true, formatter:YAHOO.widget.DataTable.formatCheckbox, className:'center' },
								{ key: "order", label: "Order", hidden:true },
								{ key: "name", label: "Field type name", sortable:true, width:100 },
								{ key: "help", label: "Help", hidden:true, sortable:false},
								{ key: "type", label: "Field type", sortable:true, width:40 },
								{ key: "status", label: "Status", hidden:true, sortable:false },
								{ key: "description",   hidden:true},
								{ key: "group",   hidden:true},
								{ key: "info", hidden:true, label: "Info", sortable:false, formatter: function(elLiner, oRecord, oColumn, oData){
										elLiner.innerHTML = '<img src="../../common/images/info_icon.png" width="16" height="16" border="0" title="Info"/>';} },
								{ key: "usage", label: "Used in", sortable:true, width:25, className:'center' },
								{ key: "fieldset_rectypeid",   hidden:true},
								{ key: "ptrtarget_rectypeids", label: "Pointer targets"}
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
								_myDataTable.subscribe("checkboxClickEvent", function(oArgs) {
									//YAHOO.util.Event.stopEvent(oArgs.event);
									var elCheckbox = oArgs.target;
									_toggleSelection(elCheckbox);
								});

								// hides tooltip div
								function __hideToolTip(){
									if(needHideTip){
										currentTipId = null;
										__clearHideTimer();
										var my_tooltip = $("#toolTip2");
										my_tooltip.css( {
											left:"-9999px"
										});
									}
								}
								// clear hidetimer
								function __clearHideTimer(){
									if (hideTimer) {
										window.clearTimeout(hideTimer);
										hideTimer = 0;
									}
								}

								//
								// hide tooltip on mouse over
								//
								_myDataTable.on('cellMouseoutEvent', function (oArgs) {
									hideTimer = window.setTimeout(__hideToolTip, 2000);
								});

								//
								// mouse over "help" column shows the datailed description (tooltip)
								//
								_myDataTable.on('cellMouseoverEvent', function (oArgs){

									__clearHideTimer();

									var forceHideTip = true,
										textTip = null,
										target = oArgs.target,
										column = this.getColumn(target),
										record = this.getRecord(target),
										xy;

									/*if (!isnull(column) && column.key === 'help') {
										var description = record.getData('description') || 'no further description';
										xy = [parseInt(oArgs.event.clientX,10) + 10 ,parseInt(oArgs.event.clientY,10) + 10 ];
										textTip = '<p>'+description+'</p>';

									}else*/
									if(!isnull(column) && column.key === 'type') {

										var dttype = record.getData('type');
										var value;
										if(dttype === "fieldsetmarker") {
											value = record.getData('fieldset_rectypeid');
										} else {
											value = record.getData('ptrtarget_rectypeids');
										}

										textTip = _getRecPointers(dttype, value);
										currentTipId=record.getData('info');
										xy = [parseInt(oArgs.event.clientX,10), parseInt(oArgs.event.clientY,10) - 20 ];

									}else if(!isnull(column) && column.key === 'usage') {
										var dty_ID = record.getData('info');

										if(currentTipId!==dty_ID){
											currentTipId=dty_ID;

											xy = [parseInt(oArgs.event.clientX,10), parseInt(oArgs.event.clientY,10) - 20 ];

											//find all records that reference this type
											var aUsage = top.HEURIST.detailTypes.rectypeUsage[dty_ID];
											if(!isnull(aUsage)){
												textTip = "<p><ul>";
												var k;
												for (k in aUsage) {   //<a href='editRecordType.html'></a>
													textTip = textTip + "<li>"+top.HEURIST.rectypes.names[k]+"</li>";
												}
												textTip = textTip + "</ul></p>";
											}
										}else{
											forceHideTip = false;
										}
									}

									if(!isnull(textTip) && textTip.length>0){

										needHideTip = true;

										var my_tooltip = $("#toolTip2");

										my_tooltip.mouseover(function(){needHideTip = false; __clearHideTimer();});
										my_tooltip.mouseout(function(){ needHideTip = true;});

					my_tooltip.html(textTip);  //DEBUG xy[0]+",  "+xy[1]+"<br/>"+

//					var xy = Hul.getMousePos(oArgs);
					xy[1] = xy[1] + $(window).scrollTop();
					Hul.showPopupDivAt(my_tooltip, xy, $(window).scrollTop(), $(window).width(), $(window).height());

/*
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
*/
										hideTimer = window.setTimeout(__hideToolTip, 5000);
									}
									else if(forceHideTip)
									{
										__hideToolTip();
									}
								});//end _onCellMouseOver

								//
								// Subscribe to events for row selection highlighting
								//
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

								//3. init Group Combo Box Filter
								_initGroupComboBoxFilter();

								//4. init listeners for filter controls
								_initListeners();

		} //isnull(_myDataTable)
	}//end of initialization ==============================

	/**
	* recreateRecTypesPreview - creates and fills selector for Record(s) pointers if datatype
	* is relmarker or resource
	*
	* @param type an datatype
	* @value - comma separated list of rectype IDs
	*
	* @returns div for popup tooltip
	*/
	function _getRecPointers(type, value) {

		var txt = "";
		if(type === "relmarker" || type === "resource" || type === "fieldsetmarker")
		{

			if(value) {
					var arr = value.split(","),
					ind, dtName;
					for (ind in arr) {
						dtName = top.HEURIST.rectypes.names[arr[ind]];
						txt = txt + "<li>"+dtName+"</li>";
					} //for
			}else{
				txt = "";
			}

			if (txt.length > 0){
				txt = "<p><ul>"+txt+"</ul></p>";
			}
		}
		return txt;
	}


	/**
	* Listener of checkbox in datatable
	* Adds or removes detail type ID from array _arr_selection
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
									if(newValue){
										_arr_selection.push(data.info);
									}else{
										var ind = _arr_selection.indexOf(data.info);
										if(ind>=0){
											_arr_selection.splice(ind,1);
										}
									}

									lblSelect1.innerHTML = "You selected <b>"+_arr_selection.length+"</b> detail type"+((_arr_selection.length>1)?"s":"");
									lblSelect2.innerHTML = lblSelect1.innerHTML;
	}

	/**
	* Fills the selector (combobox) with names of group
	* @see _init
	*/
	function _initGroupComboBoxFilter()
	{
							filterByGroup = Dom.get('inputFilterByGroup');
							var dtg_ID,
								index;

				for (index in top.HEURIST.detailTypes.groups) {
					if( !isNaN(Number(index)) ) {

						dtg_ID = top.HEURIST.detailTypes.groups[index].id;
						var grpName = top.HEURIST.detailTypes.groups[index].name;

								var option = document.createElement("option");
								option.text = grpName;
								option.value = dtg_ID;
								try
								{
									// for IE earlier than version 8
									filterByGroup.add(option, filterByGroup.options[null]);
								}
								catch (e)
								{
									filterByGroup.add(option,null);
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
							lblSelect2.innerHTML = "";
							_updateFilter();
	}

	/**
	* Assign event listener for filte UI controls
	* @see _init
	*/
	function _initListeners()
	{
							filterByName = Dom.get('inputFilterByName');
							filterByName.onkeyup = function (e) {
								clearTimeout(filterTimeout);
								setTimeout(_updateFilter, 600);
							};
							setTimeout(function(){filterByName.focus();}, 1000);

							filterBySelection1 = Dom.get('inputFilterBySelection1');
							filterBySelection1.onchange = _updateFilter;
							filterBySelection2 = Dom.get('inputFilterBySelection2');
							filterBySelection2.onchange = _updateFilter;

							lblSelect1 = Dom.get('lblSelect1');
							lblSelect2 = Dom.get('lblSelect2');
							var btnClear = Dom.get('btnClearSelection');
							if(btnClear) btnClear.onclick = _clearSelection;
	} //end init listener

	//
	//public members
	//
	var that = {

				/**
				 *	Apply form - close this window and returns comma separated list of selected detail types
				 */
				apply : function () {
						var res = _arr_selection.join(",");
						window.close(res);
				},

				/**
				 * Cancel form - closes this window
				 */
				cancel : function () {
					window.close(null);
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