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
* manageMimetypes.js
* MimetypeManager object for listing and searching of mime types
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


var mimetypeManager;

//aliases
var Dom = YAHOO.util.Dom,
	Hul = top.HEURIST.util;

/**
* MimetypeManager - class for listing and searching of mime types
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2012.1217
*/
function MimetypeManager() {

		var _className = "MimetypeManager",
			_myDataTable,
			_myDataSource,
			_db;
		//
		// filtering UI controls
		//
		var	filterTimeout,
			filterByName;


	/**
	* Updates REMOTE filter conditions and loads data from server side
	*/
	var _updateFilter = function () {

					/**
					* Result handler for search on server
					*/
					function __updateList(context) {

						var arr = [],
							ind, entity;

						if(!Hul.isnull(context)){

							var _list = context;

							for (ind in _list) {
								if(!Hul.isnull(ind))
								{

									entity = _list[ind];

									arr.push([
											entity.fxm_Extension,
											entity.fxm_MimeType,
											entity.fxm_OpenNewWindow,
											entity.fxm_FiletypeName,
											entity.fxm_IconFileName,
											entity.fxm_ImagePlaceholder,
											]);
											//["ext", "mimetype", "newwin", "description", "icon", "thumb"]
								}
							}
						}
						_initTable(arr);
					};

					var baseurl = top.HEURIST.basePath + "admin/structure/mimetypes/srvMimetypes.php";
					var params = "method=search&db=" + _db;
					top.HEURIST.util.getJsonData(baseurl, __updateList, params);
	};


	/**
	* Updates filter conditions for datatable
	*/
	var _updateFilterLocal  = function () {

							// Reset sort
							var state = _myDataTable.getState();
							state.sortedBy = {key:'ext', dir:YAHOO.widget.DataTable.CLASS_ASC};

							var filter_name   = filterByName.value;

							// Get filtered data
							_myDataSource.sendRequest(filter_name, {
								success : _myDataTable.onDataReturnInitializeTable,
								failure : _myDataTable.onDataReturnInitializeTable,
								scope   : _myDataTable,
								argument : { pagination: { recordOffset: 0 } } // to jump to page 1
							});
	};


	/**
	* Initialization of form
	*/
	function _init()
	{


			if (location.search.length > 1) {
								//window.HEURIST.parameters = top.HEURIST.parseParams(location.search);
								top.HEURIST.parameters = top.HEURIST.parseParams(location.search);
			}
			_db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));

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
					fields: ["ext", "mimetype", "newwin", "description", "icon", "thumb"]
				},
				doBeforeCallback : function (req, raw, res, cb) {
					// This is local filter function
					var data  = res.results || [],
					filtered = [],
					i,l;

					if (req) {
						var sByName = req;
						for (i = 0, l = data.length; i < l; ++i)
						{
							if (data[i].ext.toLowerCase().indexOf(sByName)>=0)
							{
								filtered.push(data[i]);
							}
						}
						res.results = filtered;
					}

					return res;
				}
			});

			var myColumnDefs = [

			/*{ key: "icon", label: "Edit", sortable:false,  width:20,
				formatter: function(elLiner, oRecord, oColumn, oData) {
					var sicon = oRecord.getData("icon");
					elLiner.innerHTML = '<a href="#edit"><img src="../../common/images/'+sicon+'" width="16" height="16" border="0" title="Edit" /></a>';
			}},*/

			{ key: "ext", label: "Extension", sortable:true, resizeable:false},

			{ key: "mimetype", label: "<div align='left'>Mime type (general type/specific type)</div>", sortable:true},
			{ key: "newwin", label: "Open in new window", sortable:true,
				formatter: function(elLiner, oRecord, oColumn, oData) {
					var str = oRecord.getData("newwin");
					elLiner.innerHTML =  (str=="1")?'yes':'';
			}},
			{ key: "description", label: "Descriptive name for this file type", sortable:false},
			{ key: null, label: "Edit", sortable:false,  width:20,
				formatter: function(elLiner, oRecord, oColumn, oData) {
					elLiner.innerHTML = '<a href="#edit"><img src="../../common/images/edit-pencil.png" width="16" height="16" border="0" title="Edit" /><\/a>';
			}},
			{ key: null, label: "Del", className:'center', sortable:false,
				formatter: function(elLiner, oRecord, oColumn, oData) {
					elLiner.innerHTML = '<div align="center"><a href="#delete"><img src="../../common/images/cross.png" border="0" title="Delete" /><\/a></div>';
				}
			}
								];


		var myConfigs = {
					//selectionMode: "singlecell",
					paginator : new YAHOO.widget.Paginator({
						rowsPerPage: 50, // REQUIRED
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

		//click on action images
		_myDataTable.subscribe('linkClickEvent', function(oArgs){

				var dt = this;
				var elLink = oArgs.target;
				var oRecord = dt.getRecord(elLink);
				var recID = oRecord.getData("ext");

				if(elLink.hash === "#edit") {

					YAHOO.util.Event.stopEvent(oArgs.event);
					_editMimetype(recID);

				}else if(elLink.hash === "#delete"){

						YAHOO.util.Event.stopEvent(oArgs.event);

						var value = confirm("Do you really want to delete mimetype for extension '"+recID+"'?");
						if(value) {

							function _updateAfterDelete(context) {

								if(!Hul.isnull(context))
								{
									dt.deleteRow(oRecord.getId(), -1);
									alert("Mime type for extension "+recID+" was deleted");
									top.HEURIST.rectypes = context.rectypes;
								}
							}

							var baseurl = top.HEURIST.basePath + "admin/structure/mimetypes/srvMimetypes.php";
							var callback = _updateAfterDelete;
							var params = "method=delete&db=" + _db + "&recID=" + recID;
							top.HEURIST.util.getJsonData(baseurl, callback, params);

						}
				}

		});

		// Subscribe to events for row selection
		_myDataTable.subscribe("rowMouseoverEvent", _myDataTable.onEventHighlightRow);
		_myDataTable.subscribe("rowMouseoutEvent", _myDataTable.onEventUnhighlightRow);

		//init listeners for filter controls
		_initListeners();

		_updateFilter(); //fill table after creation
	}//end of initialization =====================


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
								filterTimeout = setTimeout(_updateFilterLocal, 600);
							};
				}

	} //end init listener

	//
	// call new popup - to edit entity
	//
	function _editMimetype(id) {
		var URL = top.HEURIST.basePath + "admin/structure/mimetypes/editMimetype.html?db=" + _db;

		if(Number(id)<0) {

		} else if (!Hul.isempty(id)) {
			URL = URL + "&recID="+id;
		}else{
			return;
		}

		top.HEURIST.util.popupURL(top, URL, {
			"close-on-blur": false,
			"no-resize": false,
			height: 380,
			width: 620,
			callback: function(context) {
				if(!Hul.isnull(context)){
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

				editMimetype: function(id){ _editMimetype( id ); },

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