/**
* editRectypeConstraints.js
* DetailTypeManager object for listing and searching of detail types
*
* @version 2012.0822
* @author: Artem Osmakov
*
* @copyright (C) 2005-2012 University of Sydney Digital Innovation Unit.
* @link: http://HeuristScholar.org
* @license http://www.gnu.org/licenses/gpl-3.0.txt
* @package Heurist academic knowledge management system
* @todo
**/
var g_version = "1";

var constraintManager;

//aliases
var Dom = YAHOO.util.Dom,
	Hul = top.HEURIST.util;

function ConstraintManager() {

	var _className = "ConstraintManager",
		_ver = g_version,				//version number for data representation
		_myDataTable,
		_myDataSource;

	var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
				(top.HEURIST.database.name?top.HEURIST.database.name:''));

	//
	//
	//
	function _init()
	{

			var arr = [],
				rec_ID,
				target_rec_ID,
				term_ID;

			//fi = top.HEURIST.rectypes.names;

			//create datatable and fill it values of particular group
			for (rec_ID in top.HEURIST.rectypes.constraints) {

				if(!Hul.isnull(rec_ID))
				{
					for (target_rec_ID in top.HEURIST.rectypes.constraints[rec_ID].byTarget) {
						if(!Hul.isnull(target_rec_ID))
						{
							var terms = [];
							for (term_ID in top.HEURIST.rectypes.constraints[rec_ID].byTarget[target_rec_ID] ) {
								if(!isNaN(Number(term_ID))){
									terms.push([term_ID, top.HEURIST.rectypes.constraints[rec_ID].byTarget[target_rec_ID][term_ID].limit, 'notes']);
								}
							}

							var sname = (rec_ID=='any')?rec_ID: top.HEURIST.rectypes.names[rec_ID];
							if(rec_ID=='any')  { rec_ID=0; }
							var tname = (target_rec_ID=='any')?target_rec_ID: top.HEURIST.rectypes.names[target_rec_ID];
							if(target_rec_ID=='any')  { target_rec_ID=0; }

							arr.push([Number(rec_ID),
									sname,
									Number(target_rec_ID),
									tname,
									terms.length,
									terms ]);
						}
					}
				}
			}

			_initTable(arr);
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
										fields: ["src_id", "src_name", "trg_id", "trg_name", "count", "terms"]
									}
								});

								var myColumnDefs = [
			{ key: "src_id", label: "From", sortable:true, className:'right',resizeable:false},
			{ key: "src_name", label: " ", sortable:true},
			{ key: "trg_id", label: "To", sortable:true, className:'right',resizeable:false},
			{ key: "trg_name", label: " ", sortable:true},
			{ key: "count", label: "Count", sortable:true, className:'right',resizeable:false},
			{ key: null, label: "Edit", sortable:false,  width:20,
				formatter: function(elLiner, oRecord, oColumn, oData) {
elLiner.innerHTML = '<a href="#edit_item"><img src="../../common/images/edit-pencil.png" width="16" height="16" border="0" title="Edit"><\/a>';}
			}
								];


		var myConfigs = {};

		_myDataTable = new YAHOO.widget.DataTable('tabContainer', myColumnDefs, _myDataSource, myConfigs);


		//click on action images
		_myDataTable.subscribe('linkClickEvent', function(oArgs){


				var dt = this;
				var elLink = oArgs.target;
				var oRecord = dt.getRecord(elLink);
				var recID = oRecord.getData("id");

				if(elLink.hash === "#edit_item") {
					YAHOO.util.Event.stopEvent(oArgs.event);
					_editConstraint(recID);

				}else if(elLink.hash === "#delete_item"){

					YAHOO.util.Event.stopEvent(oArgs.event);

						var value = confirm("Do you really want to delete constraint?"); // '"+oRecord.getData('fullname')+"'?");
						if(value) {

							function _updateAfterDelete(context) {

								if(Hul.isnull(context) || !context){
									alert("Unknown error on server side");
								}else if(Hul.isnull(context.error)){
									dt.deleteRow(oRecord.getId(), -1);
									top.HEURIST.detailTypes = context.detailTypes;
									alert("Constrain was deleted");
								}
							}

							var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
							var callback = _updateAfterDelete;
							var params = "method=deleteRTC&db="+db+"&recID=" + recID;
							top.HEURIST.util.getJsonData(baseurl, callback, params);

						}
				}

		});

		// Subscribe to events for row selection
		_myDataTable.subscribe("rowMouseoverEvent", _myDataTable.onEventHighlightRow);
		_myDataTable.subscribe("rowMouseoutEvent", _myDataTable.onEventUnhighlightRow);

		_initTable(arr);

	}//end of initialization =====================


	/**
	* Show table with list of relation types
	*/
	function _editConstraint(recID){


	}

	/**
	*
	*/
	function _addConstraint(){

	}



	//
	//public members
	//
	var that = {

				/**
				* @param user - userID or email
				*/
				addConstraint: function(user){ _addConstraint(); },

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