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
		_myDataSource,
		_myTermTable,
		_myTermSource,
		_myToolTip,
		showTimer, hideTimer,
		_currentPair,
		_currentPairRecord;

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
				term_ID,
				fi_trm_label = top.HEURIST.terms.fieldNamesToIndex.trm_Label;


			createRectypeSelector("selSrcRectypes", true);
			createRectypeSelector("selTrgRectypes", true);

			_myToolTip = new YAHOO.widget.Tooltip("myTooltip");

			//fi = top.HEURIST.rectypes.names;

			//create datatable and fill it values of particular group
			for (rec_ID in top.HEURIST.rectypes.constraints) {

				if(!Hul.isnull(rec_ID))
				{
					for (target_rec_ID in top.HEURIST.rectypes.constraints[rec_ID].byTarget) {
						if(!Hul.isnull(target_rec_ID))
						{
							var terms = [],
								hasAny = false;

							for (term_ID in top.HEURIST.rectypes.constraints[rec_ID].byTarget[target_rec_ID] ) {
								if(Hul.isnull(term_ID)) continue;

								var notes = top.HEURIST.rectypes.constraints[rec_ID].byTarget[target_rec_ID][term_ID].notes;
								var limit = top.HEURIST.rectypes.constraints[rec_ID].byTarget[target_rec_ID][term_ID].limit;
								if(limit=='unlimited') limit=0;

								if(term_ID=='any'){

									hasAny = true;
									terms.push(['null',
												'any',
												limit,
												notes,
												false]);

								}else if(!isNaN(Number(term_ID))){
									terms.push([term_ID,
												top.HEURIST.terms.termsByDomainLookup.relation[term_ID][fi_trm_label],
												limit,
												notes,
												false]);
								}
							}//for

							var sname = (rec_ID=='any')?rec_ID: top.HEURIST.rectypes.names[rec_ID];
							if(rec_ID=='any')  { rec_ID = 0; }
							var tname = (target_rec_ID=='any')?target_rec_ID: top.HEURIST.rectypes.names[target_rec_ID];
							if(target_rec_ID=='any')  { target_rec_ID = 0; }

							arr.push([Number(rec_ID),
									sname,
									Number(target_rec_ID),
									tname,
									terms.length,
									terms,
									hasAny ]);
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
										fields: ["src_id", "src_name", "trg_id", "trg_name", "count", "terms", "hasAny"]
									}
								});

								var myColumnDefs = [
			{ key: "src_id", label: "", sortable:true, className:'right',resizeable:false, width:5},
			{ key: "src_name", label: "From", sortable:true},
			{ key: "trg_id", label: "", sortable:true, className:'right',resizeable:false, width:5},
			{ key: "trg_name", label: "To", sortable:true},
			{ key: "count", label: "Cnt", sortable:true, className:'center',resizeable:false, width:10}
								];


		var myConfigs = {};

		_myDataTable = new YAHOO.widget.DataTable('tabContainer', myColumnDefs, _myDataSource, myConfigs);


		//click on action images
		_myDataTable.subscribe('rowClickEvent', function(oArgs){


				var dt = this;
				var elLink = oArgs.target;
				var oRecord = dt.getRecord(elLink);

				//YAHOO.util.Event.stopEvent(oArgs.event);
				_editConstraint(oRecord);

		});

		// Subscribe to events for row selection
		_myDataTable.subscribe("rowMouseoverEvent", _myDataTable.onEventHighlightRow);
		_myDataTable.subscribe("rowMouseoutEvent", _myDataTable.onEventUnhighlightRow);

		_myDataTable.on('theadCellMouseoverEvent', _showToolTip);
		_myDataTable.on('theadCellMouseoutEvent', _hideToolTip);

		_initTable(arr);

	}//end of initialization =====================

	function _showToolTip(oArgs){

				if (showTimer) {
					window.clearTimeout(showTimer);
					showTimer = 0;
				}

				var target = oArgs.target;
				var column = this.getColumn(target);
				var description = null;
				if (column.key == 'src_name') {
					description = "Source: The record type from which relationships will be established";
				}else if (column.key == 'trg_name') {
					description = "Target: The record type to which relationships will point";
				}else if (column.key == 'trm_label') {
					description = "Term: The term to be constrained (constraint is applied to the term and its children)";
				}else if (column.key == 'limit') {
					description = "Limit: The maximum number of relationships which can be established using this combination (blank = unlimited)";
				}else if (column.key == 'notes') {
					description = "Notes: Notes about the nature of the constraint";
				}
				if(description){
					var xy = [parseInt(oArgs.event.clientX,10) + 10 ,parseInt(oArgs.event.clientY,10) + 10 ];

					showTimer = window.setTimeout(function() {
						_myToolTip.setBody(description);
						_myToolTip.cfg.setProperty('xy',xy);
						_myToolTip.show();
						hideTimer = window.setTimeout(function() {
							tt.hide();
						},5000);
					},500);
				}

	}
	function _hideToolTip(oArgs){
				if (showTimer) {
					window.clearTimeout(showTimer);
					showTimer = 0;
				}
				if (hideTimer) {
					window.clearTimeout(hideTimer);
					hideTimer = 0;
				}
				_myToolTip.hide();
	}


	/**
	* Creates and (re)fill TERMS datatable
	*/
	function _initTermTable(arr)
	{

	//if datatable exists, only refill ==========================
				if(!Hul.isnull(_myTermTable)){

					// all stuff is already inited, change livedata in datasource only
					_myTermSource.liveData = arr;

					//refresh table
					_myTermSource.sendRequest("", {
								success : _myTermTable.onDataReturnInitializeTable,
								failure : _myTermTable.onDataReturnInitializeTable,
								scope   : _myTermTable,
								argument : { pagination: { recordOffset: 0 } } // to jump to page 1
					});

					return;
				}

//									terms.push([term_ID, top.HEURIST.rectypes.constraints[rec_ID].byTarget[target_rec_ID][term_ID].limit, 'notes']);

	//create new datatable ==========================

								_myTermSource = new YAHOO.util.LocalDataSource(arr, {
									responseType : YAHOO.util.DataSource.TYPE_JSARRAY,
									responseSchema : {
										fields: ["trm_id", "trm_label", "limit", "notes", "changed"]
									}
								});

								var myColumnDefs = [
			{ key: "trm_label", label: "Term", sortable:true, resizeable:true, width:100},
			{ key: "limit", label: "Limit", sortable:true, className:'center',
				resizeable:false, formatter:'number',
				editor: new YAHOO.widget.TextboxCellEditor({disableBtns:true, maxlength:4, size:4}),
				formatter: function(elLiner, oRecord, oColumn, oData) {
					var val = oRecord.getData('limit');
					elLiner.innerHTML = "<span title='Click to edit'>"+((Number(val)<1)?'&nbsp;&nbsp;&nbsp;':val)+"</span>";
			}
			},
			{ key: "notes", label: "Notes", resizeable:true, sortable:false, width:200, editor: new YAHOO.widget.TextareaCellEditor(),
				formatter: function(elLiner, oRecord, oColumn, oData) {
					var val = oRecord.getData('notes');
					if(Hul.isempty(val)){
						val = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
					}
					elLiner.innerHTML = "<span title='Click to edit'>"+val+"</span>";
			}},
			{ key: "changed", label: "Del", sortable:false,  resizeable:false, className:'center',
				formatter: function(elLiner, oRecord, oColumn, oData) {
elLiner.innerHTML = '<a href="#delete_term"><img src="../../common/images/cross.png" title="Delete this Term" /><\/a>';
						}
			}
								];


		var myConfigs = {};

		_myTermTable = new YAHOO.widget.DataTable('tabContainer2', myColumnDefs, _myTermSource, myConfigs);

		_myTermTable.subscribe("cellClickEvent", _myTermTable.onEventShowCellEditor);

		_myTermTable.subscribe("editorKeydownEvent", function( oArgs ){
			/* bug in YUI - it does not return CellEditor
			var clm = oArgs.editor.getColumn();
			if(clm.field == "limit")
			{
				if(oArgs.editor.getInputValue().length>2){
					YAHOO.util.Event.stopEvent(oArgs.event);
				}
			}
			*/
			var keyCode = oArgs.event.keyCode;
			if(oArgs.event.target.type=="text" && keyCode>46)
			{

 				var isNumeric = (keyCode >= 48 /* KeyboardEvent.DOM_VK_0 */ && keyCode <= 57 /* KeyboardEvent.DOM_VK_9 */) ||
                        	(keyCode >= 96 /* KeyboardEvent.DOM_VK_NUMPAD0 */ && keyCode <= 105 /* KeyboardEvent.DOM_VK_NUMPAD9 */);
				//if(/^[a-z]+$/i.test(field.value)) return true;
				//var s = String.fromCharCode(keyCode);

				if (!isNumeric || oArgs.event.target.value && oArgs.event.target.value.length>2)
				{
					YAHOO.util.Event.stopEvent(oArgs.event);
				}
			}
		});

		_myTermTable.subscribe("editorSaveEvent", function( oArgs) {
				var oRecord = oArgs.editor.getRecord();
				oRecord.setData("changed", true);
				_saveConstraint(null, null, false, null);
		});

		//click on action images
		_myTermTable.subscribe('linkClickEvent', function(oArgs){


				var dt = this;
				var elLink = oArgs.target;
				var oRecord = dt.getRecord(elLink);

				if(elLink.hash === "#delete_term"){

					YAHOO.util.Event.stopEvent(oArgs.event);

						var cnt = _currentPairRecord.getData("count");
						var swarn = (cnt==1)
									? "'"+oRecord.getData("trm_label")+"' is the only term for the current entity. Do you really want to delete the entity pair completely?"
									: "Do you really want to delete term '"+oRecord.getData("trm_label")+"' ?";

						var value = confirm(swarn);
						if(value) {

							function _updateAfterDelete(context) {

								if(Hul.isnull(context) || !context){
									alert("Unknown error on server side");
								}else if(Hul.isnull(context.error)){

									if(cnt==1){
										_deleteAtAll(true);
									}else{

										if(oRecord.getData("trm_label")=="any"){
											Dom.get('btnAddAny').style.visibility = "visible";
											_currentPairRecord.setData('hasAny', false);
										}

										var idx = _findTermIndex(oRecord.getData("trm_id"));
										if(idx>=0){
											var curterms= _currentPairRecord.getData("terms");
											curterms.splice(idx,1);
											_updateTerms(curterms);
										}
										dt.deleteRow(oRecord.getId(), -1);
									}
									top.HEURIST.rectypes.constraints = context.constraints;
									//alert("Constraint was deleted");
								}
							}

							var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
							var callback = _updateAfterDelete;
							var params = "method=deleteRTC&db="+db + _currentPair +
																	"&trmID=" + oRecord.getData("trm_id");
							top.HEURIST.util.getJsonData(baseurl, callback, params);

						}
				}

		});

		// Subscribe to events for row selection
		_myTermTable.subscribe("rowMouseoverEvent", _myTermTable.onEventHighlightRow);
		_myTermTable.subscribe("rowMouseoutEvent", _myTermTable.onEventUnhighlightRow);
		_myTermTable.on('theadCellMouseoverEvent', _showToolTip);
		_myTermTable.on('theadCellMouseoutEvent', _hideToolTip);

		_initTermTable(arr);

	}//end of initialization =====================

	/**
	* update UI if all terms are deleted
	*/
	function _deleteAtAll(todelete){
		if(todelete){
			_myDataTable.deleteRow(_currentPairRecord.getId(),-1);
		}
		_currentPairRecord = null;
		_currentPair = null;
		Dom.get('termsList').style.display = 'none';
	}

	/**
	*
	*/
	function _updateTerms(curterms){
				_currentPairRecord.setData("terms",curterms);
				_currentPairRecord.setData("count",curterms.length);
				_myDataTable.updateCell ( _currentPairRecord , "count" , curterms.length , false );
				//_myDataTable.updateRow(_currentPairRecord.getId(), _currentPairRecord.getData()); //it works only once
	}


	/**
	* Show table with list of relation types
	*/
	function _editConstraint(oRecord){

		Dom.get('termsList').style.display = 'inline-block';
		Dom.get('currPairTitle').innerHTML = oRecord.getData("src_name") + ' to ' + oRecord.getData("trg_name");

		_currentPairRecord = oRecord;
		_currentPair = "&srcID=" + oRecord.getData("src_id") +	"&trgID=" + oRecord.getData("trg_id");

		var _currentTerms = oRecord.getData("terms");

		var hasAny = oRecord.getData("hasAny");
		Dom.get('btnAddAny').style.visibility = hasAny?"hidden":"visible";

		//init terms table
		_initTermTable(_currentTerms);
	}

	/**
	*
	*/
	function _saveConstraint(src_id, trg_id, update_all, terms_to_delete){

		var isFirst = (src_id!=null || trg_id!=null);

							function _updateAfterSave(context) {

								if(Hul.isnull(context) || !context){
									alert("Unknown error on server side");
								}else if(Hul.isnull(context.error)){

									if(isFirst)
									{

									//reset
									_deleteAtAll(false);

									var i;
									for( i=0; i<context.result.length; i++){
										var res = context.result[i];

										var sname = 'any',
										    rec_ID = 0,
										    tname = 'any',
										    target_rec_ID = 0;

										if(!isNaN(Number(res[0]))){
											rec_ID = Number(res[0]);
											sname = top.HEURIST.rectypes.names[rec_ID];
										}
										if(!isNaN(Number(res[1]))){
											target_rec_ID = Number(res[1]);
											tname = top.HEURIST.rectypes.names[target_rec_ID];
										}

										res =  {src_id:Number(rec_ID),
												src_name:sname,
												trg_id:Number(target_rec_ID),
												trg_name:tname,
												count:1,
												terms:[['null',
												'any',
												0,
												'',
												false]],
												hasAny:true}; //terms

	            						_myDataTable.addRow(res);
										}
									}

									top.HEURIST.rectypes.constraints = context.constraints;
								}
							}

			//1. creates object to be sent to server
			var values = [],
				currPair;
			if(!isFirst){

				currPair = _currentPair;

				var i,
					records = _myTermTable.getRecordSet(),
					len = records._records.length-1,
					currterms = [];

				for(i=0;i<=len;i++){
					var rec = records._records[i];
					currterms.push([rec.getData("trm_id"),
									rec.getData("trm_label"),
									rec.getData("limit"),
									rec.getData("notes"),
									false]);
					if(update_all || rec.getData("changed")){
						values.push(currterms[currterms.length-1]);
					}
					rec.setData("changed", false);
				}

				_currentPairRecord.setData("terms", currterms);

				if(!Hul.isempty(terms_to_delete)){
					currPair = currPair + "&del=" + terms_to_delete;
				}

 			}else{

				if(Hul.isempty(src_id) && Hul.isempty(trg_id)){
					alert("Define rectype for source or target");
					return;
				}


 				//check that this pair is unique
 				var srcrec = top.HEURIST.rectypes.constraints[(src_id==''?'any':src_id)];
 				if(Hul.isnull(srcrec) || Hul.isnull(srcrec.byTarget[(trg_id==''?'any':trg_id)])){

 					currPair = "&srcID=" + src_id +	"&trgID=" + trg_id;
					values.push(['null','null','null','']);
 				}else{
					alert('This pair of entities is already listed');
					return;
 				}
			}
			var str = YAHOO.lang.JSON.stringify(values);


			// 2. sends data to server
			var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
			var callback = _updateAfterSave;
			var params = "method=saveRTC&db="+db + currPair + "&data=" + encodeURIComponent(str);
			Hul.getJsonData(baseurl, callback, params);

	}

	/**
	*
	*/
	function createRectypeSelector(selname, isall)
	{
		var rectypes = top.HEURIST.rectypes;
		var rectypeValSelect = document.getElementById(selname);
		rectypeValSelect.innerHTML = '<option value="" selected>any</option>';
		// rectypes displayed in Groups by group display order then by display order within group
		for (var index in rectypes.groups){
			if (index == 'groupIDToIndex' || rectypes.groups[index].showTypes.length < 1){
				continue;
			}
			var grp = document.createElement("optgroup");
			var firstInGroup = true,
				i=0;
			grp.label = rectypes.groups[index].name;
			for (; i < rectypes.groups[index].showTypes.length; i++) {
				var recTypeID = rectypes.groups[index].showTypes[i];
				if (recTypeID && (isall || rectypes.usageCount[recTypeID]>0)) {
					if (firstInGroup){
						rectypeValSelect.appendChild(grp);
						firstInGroup = false;
					}
					Hul.addoption(rectypeValSelect, recTypeID, rectypes.names[recTypeID]);
				}
			}
		}
	}

	/**
	* find index in term array for current pair
	*/
	function _findTermIndex(termID){
		if(_currentPairRecord){
			var curterms = _currentPairRecord.getData("terms");
			var idx;
			for( idx in curterms){
				if(!Hul.isnull(idx)){
					if(termID == curterms[idx][0]){
						return Number(idx);
					}
				}
			}
		}
		return -1;
	}

	/**
	*
	*/
	function _addAny(){
		var curterms = _currentPairRecord.getData("terms");
		curterms.push(['null',
						'any',
						0,
						'',
						true]);
		//update table
		_currentPairRecord.setData("hasAny", true);
		_updateTerms(curterms);

		Dom.get('btnAddAny').style.visibility = "hidden";

		_initTermTable(curterms);

		_saveConstraint(null, null, false, null); //update on server side
	}

	/**
	* onSelectTerms
	*
	* listener of "Add Term" button
	* Shows a popup window where user can select terms
	*/
	function _onSelectTerms(){

		var curterms = _currentPairRecord.getData("terms");
		var allTerms = "{";
		var idx, termID;
		for( idx in curterms){
			if(!Hul.isnull(idx)){
				termID = curterms[idx][0];
				if(!Hul.isnull(termID)){
					allTerms = allTerms + '"'+termID+'":{},'
				}
			}
		}
		if(allTerms!='') {
			allTerms = allTerms+ '}';
		}

		Hul.popupURL(top, top.HEURIST.basePath +
			"admin/structure/selectTerms.html?datatype=relationtype&all="+allTerms+"&selonly=1&db="+db,
			{
			"close-on-blur": false,
			"no-resize": true,
			height: 450,
			width: 750,
			callback: function(editedTermTree, editedDisabledTerms) {
				if(editedTermTree || editedDisabledTerms) {

					var existingTree = Hul.expandJsonStructure(editedTermTree);
					//var disabledTerms = Hul.expandJsonStructure(editedDisabledTerms);
					var selterms = [];

					function __getFlatArray(termSubTree){
						//get flat list
						for( termID in termSubTree){
							if(!Hul.isnull(termID)){
								//if(disabledTerms.indexOf(termID)<0){
									selterms.push(termID);
									if(typeof termSubTree[termID] === "object") {
										__getFlatArray(termSubTree[termID]);
									}
								//}
							}
						}
					}

					__getFlatArray(existingTree);

					var affected = 0,
						terms_to_delete = "";
					//update terms in current pair record and reload table
					idx = 0;
					while( idx<curterms.length){
							if(curterms[idx][1]!='any')
							{
								termID = curterms[idx][0];
								if(selterms.indexOf(termID)<0){
									//remove
									terms_to_delete = terms_to_delete+termID+",";
									curterms.splice(idx,1);
									affected++;
									continue;
								}
							}
							idx++;
					}
					if(terms_to_delete.length>0){
						terms_to_delete = terms_to_delete.substr(0,terms_to_delete.length-1);
					}

					var fi_trm_label = top.HEURIST.terms.fieldNamesToIndex.trm_Label;
					for( idx in selterms){
						if(!Hul.isnull(idx)){
							termID = selterms[idx];
							if(_findTermIndex(termID)<0){
								//add to array
								curterms.push([termID,
												top.HEURIST.terms.termsByDomainLookup.relation[termID][fi_trm_label],
												0,
												'',
												true]);

								affected++;
							}
						}
					}

					if(affected>0){ //autosave
						//update table
						if(curterms.length>0){
							_updateTerms(curterms);
							_initTermTable(curterms);
						}else{
							_deleteAtAll(true);
						}

						_saveConstraint(null, null, false, terms_to_delete); //all
					}


				}
			}
		});

	}


	//
	//public members
	//
	var that = {

				/**
				* @param user - userID or email
				*/
				addConstraint: function(user){
						_saveConstraint(Dom.get('selSrcRectypes').value, Dom.get('selTrgRectypes').value, false, null);
				},

				addTerms:function(){
					_onSelectTerms();
				},

				addAny:function(){
					_addAny();
				},

				/* now there is "auto-save"
				saveTerms:function(){
					_saveConstraint(null,null, false);
					Dom.get('termsList').style.display = 'none';
				},
				*/

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