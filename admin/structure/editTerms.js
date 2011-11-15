/**
* editTerms.js
* Support file for editTerms.php
*
* 28/04/2011
* @author: Juan Adriaanse
* @author: Artem Osmakov
* @author: Stephen White
*
* @copyright (C) 2005-2011 University of Sydney Digital Innovation Unit.
* @link: http://HeuristScholar.org
* @license http://www.gnu.org/licenses/gpl-3.0.txt
* @package Heurist academic knowledge management system
* @todo
**/

// EditTerms object
var editTerms;

/**
* create composed label with all parents
*/
function getParentLabel(_node){
		if(_node.parent._type==="RootNode"){
			return _node.label;
		}else{
			return getParentLabel(_node.parent)+" > "+_node.label;
		}
}

//aliases
var Dom = YAHOO.util.Dom,
	Hul = top.HEURIST.util;

/**
* EditTerms - class for pop-up window to edit terms
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2011.0504
*/
function EditTerms() {

	//private members
	var _className = "EditTerms",
		_tabView = new YAHOO.widget.TabView(),
		_termTree1, //treeview for enum terms
		_termTree2, //treeview for relation terms
		_currTreeView,
		_currentNode,
		_parentNode,
		_currentDomain,
		_db,
		_isWindowMode=false,
		_isSomethingChanged=false;

	/**
	*	Initialization of tabview with 2 tabs with treeviews
	*/
	function _init (){

		top.HEURIST.parameters = top.HEURIST.parseParams(location.search);

		_isWindowMode = !Hul.isnull(top.HEURIST.parameters.popup);

		_db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));

		_tabView.addTab(new YAHOO.widget.Tab({
							id: 'enum',
							label: 'Enum',
							content: '<div style="height:90%; max-width:300; overflow: auto;"><div id="termTree1" class="termTree ygtv-highlight" style="width:100%;height:100%;"></div></div>'
						}));
		_tabView.addTab(new YAHOO.widget.Tab({
							id: 'relation',
							label: 'Relation',
							content: '<div style="height:90%; max-width:300; overflow:auto"><div id="termTree2" class="termTree ygtv-highlight" style="width:100%;height:100%;"></div></div>'
						}));
		_tabView.addListener("activeTabChange", _handleTabChange);
		_tabView.appendTo("tabContainer");

		_tabView.set("activeIndex", 0);

		var dv1 = Dom.get('divApply');
		var dv2 = Dom.get('divBanner');
		var dv3 = Dom.get('page-inner');
		if(_isWindowMode){
			dv1.style.display = "block";
			dv2.style.display = "none";
			dv3.style.top = "5px";
			window.close = that.applyChanges;

		}else{
			dv1.style.display = "none";
			dv2.style.display = "block";
		}
	}

	/**
	* Listener of  tabview control. Creates new treeview on the tab
	*/
	function _handleTabChange (e) {
		var ind = _tabView.get("activeIndex");
		if(e.newValue!==e.prevValue){
			if(ind===0){
				_currentDomain = "enum";

				if(Hul.isnull(_termTree1)){
					_termTree1 = new YAHOO.widget.TreeView("termTree1");
					//fill treeview with content
					_fillTreeView(_termTree1);
				}
				_currTreeView = _termTree1;

			}else if(ind===1){
				_currentDomain = "relation";

				if(Hul.isnull(_termTree2)){
					_termTree2 = new YAHOO.widget.TreeView("termTree2");
					//fill treeview with content
					_fillTreeView(_termTree2);
				}
				_currTreeView = _termTree2;
			}
		}
	}

	/**
	*	Fills the given treeview with the appropriate content
	*/
	function _fillTreeView (tv) {

		var termid,
			tv_parent = tv.getRoot(),
			first_node,
			treesByDomain = top.HEURIST.terms.treesByDomain[_currentDomain],
			termsByDomainLookup = top.HEURIST.terms.termsByDomainLookup[_currentDomain],
			fi = top.HEURIST.terms.fieldNamesToIndex;

		tv.removeChildren(tv_parent); // Reset the the tree

		//first level terms
		for (termid in treesByDomain)
		{
		if(!Hul.isnull(termid)){

			var nodeIndex = tv.getNodeCount()+1;

			var arTerm = termsByDomainLookup[termid];

			var term = {};//new Object();
			term.id = termid;
			term.parent_id = null;
			term.domain = _currentDomain;
			term.label = arTerm[fi.trm_Label];
			term.description = arTerm[fi.trm_Description];
			term.inverseid = arTerm[fi.trm_InverseTermID];
            term.status = arTerm[fi.trm_Status];
            term.original_db = arTerm[fi.trm_OriginatingDBID];

			//'<div id="'+parentElement+'"><a href="javascript:void(0)" onClick="selectAllChildren('+nodeIndex+')">All </a> '+termsByDomainLookup[parentElement]+'</div>';
			//term.href = "javascript:void(0)";
		//internal function
			function __createChildren(parentNode, parent_id, parentEntry) { // Recursively get all children
				var term,
					childNode,
					nodeIndex,
					child;

				for(child in parentNode)
				{
				if(!Hul.isnull(child)){
					nodeIndex = tv.getNodeCount()+1;

					var arTerm = termsByDomainLookup[child];

					if(!Hul.isnull(arTerm)){

					term = {};//new Object();
					term.id = child;
					term.parent_id = parent_id;
					term.domain = _currentDomain;
					term.label = arTerm[fi.trm_Label];
					term.description = arTerm[fi.trm_Description];
					term.inverseid = arTerm[fi.trm_InverseTermID];
                    term.status = arTerm[fi.trm_Status];
                    term.original_db = arTerm[fi.trm_OriginatingDBID];


					//term.label = '<div id="'+child+'"><a href="javascript:void(0)" onClick="selectAllChildren('+nodeIndex+')">All </a> '+termsByDomainLookup[child]+'</div>';
					//term.href = "javascript:void(0)"; // To make 'select all' clickable, but not leave the page when hitting enter

					childNode = new YAHOO.widget.TextNode(term, parentEntry, false); // Create the node

					__createChildren(parentNode[child], term.id, childNode); // createChildren() again for every child found
					}
				}
				}//for
			}

			var topLayerParent = new YAHOO.widget.TextNode(term, tv_parent, false); // Create the node
			
			if(!first_node) { first_node = topLayerParent;}

			var parentNode = treesByDomain[termid];
			__createChildren(parentNode, termid, topLayerParent); // Look for children of the node
		}
		}//for

		tv.subscribe("labelClick", _onNodeClick);
		tv.singleNodeHighlight = true;
		
		tv.subscribe("clickEvent", tv.onEventToggleHighlight);

		tv.render();
		//first_node.focus();
		//first_node.toggle();

		if(_currentNode){
			_findNodeById(_currentNode.data.id);
		}
	}

	//
	// find node(s) by name in current treeview
	// it returns the array of nodes
	//
	function _findNodes(sSearch) {

		function __doSearchByName(node){
			return (node && node.label && (node.label.toLowerCase().indexOf(sSearch)>=0));
		}

		sSearch = sSearch.toLowerCase();
		var nodes = _currTreeView.getNodesBy(__doSearchByName);

		//alert("search "+sSearch);
		return nodes;
	}
	//
	// select node by id and expand it
	//
	function _findNodeById(nodeid, needExpand) {

		nodeid = ""+nodeid;

		function __doSearchById(node){
			return (node.data.id===nodeid);
		}

		var nodes = _currTreeView.getNodesBy(__doSearchById);

		//select and expand the node
		if(nodes){
			var node = nodes[0];
			if(needExpand) {
				node.focus();
				node.toggle();
			}
			return node;
		}else{
			return null;
		}
		//internal
	}

	/**
	* Loads values to edit form
	*/
	function _onNodeClick(node){

		_parentNode = null;

		if(_currentNode !== node)
		{
			if(!Hul.isnull(_currentNode)){
				_doSave(true);
			}
			_currentNode = node;

			var disable_status = false,
				disable_fields = false,
				add_reserved = false,
				status = 'open';

			if(!Hul.isnull(node)){
				Dom.get('formMessage').style.display = "none";
				Dom.get('formEditor').style.display = "block";

				//	alert("label was clicked"+ node.data.id+"  "+node.data.domain+"  "+node.label);
				Dom.get('edId').value = node.data.id;
				Dom.get('edParentId').value = node.data.parent_id;
				var edName = Dom.get('edName');
				edName.value = node.label;
				if(node.label==="New Term"){
					//highlight all text
					edName.selectionStart = 0;
					edName.selectionEnd = 8;
				}
				edName.focus();
				if(Hul.isnull(node.data.description)) {
					node.data.description="";
				}
				Dom.get('edDescription').value = node.data.description;

				var node_invers = null;
				if(node.data.inverseid>0){
					node_invers = _findNodeById(node.data.inverseid, false);
				}
				if(!Hul.isnull(node_invers)){ //inversed term found
						Dom.get('edInverseTermId').value = node_invers.data.id;
						Dom.get('edInverseTerm').value = getParentLabel(node_invers);
						Dom.get('btnInverseSetClear').value = 'clear';
				}else{
						node.data.inverseid = null;
						Dom.get('edInverseTermId').value = '0';
						Dom.get('edInverseTerm').value = '';
						Dom.get('btnInverseSetClear').value = 'set';
				}

				if(isExistingNode(node)){
						Dom.get('div_btnAddChild').style.display = "inline-block";
						Dom.get('btnDelete').value = "Delete Term";
						Dom.get('btnSave').value = "Save changes";
				}else{//new term
						Dom.get('div_btnAddChild').style.display = "none";
						Dom.get('btnDelete').value = "Cancel";
						Dom.get('btnSave').value = "Add Term";
				}

				Dom.get('divInverse').style.display = (_currTreeView === _termTree2)?"block":"none";


				var dbId = Number(top.HEURIST.database.id),
					original_dbId = node.data.original_db,
					status = node.data.status;

				if(Hul.isnull(original_dbId)) {original_dbId = dbId;}

				if((dbId>0) && (dbId<1001) && (original_dbId===dbId)) {
					add_reserved = true;
				}

				if(status==='reserved'){ //if reserved - it means original dbid<1001

					disable_status = (original_dbId!==dbId) && (original_dbId>0) && (original_dbId<1001);
					disable_fields = true;
					add_reserved = true;

				}else if(status==='approved'){
					disable_fields = true;
				}


				_optionReserved(add_reserved);
				_toggleAll(disable_status || disable_fields, disable_status);

				var selstatus = Dom.get("trm_Status");
				selstatus.value = status;
			}//node!=null
		}

		Dom.get('formInverse').style.display = "none";
		if(Hul.isnull(node)){
			Dom.get('formEditor').style.display = "none";
			Dom.get('formMessage').style.display = "block";
		}

	}

	/**
	* adds reserved option to status dropdown list
	*/
	function _optionReserved(isAdd){
		var selstatus = Dom.get("trm_Status");
		if(isAdd && selstatus.length<4){
				var option = document.createElement("option");
				option.text = 'reserved';
				option.value = 'reserved';
				try {
					// for IE earlier than version 8
					selstatus.add(option, sel.options[null]);
				}catch (ex2){
					selstatus.add(option,null);
				}
		}else if (!isAdd && selstatus.length===4){
			selstatus.length=3;
			//selstaus.remove(3);
		}
	}

	/**
	* Toggle fields to disable. Is called when status is set to 'Reserved'.
	*/
	function _toggleAll(disable, reserved) {

			Dom.get("trm_Status").disabled = reserved;

			Dom.get("btnDelete").disabled = disable;
			Dom.get("btnInverseSetClear").disabled = disable;
	}

	/**
	*
	*/
	function _onChangeStatus(event){
		var el = event.target;
		if(el.value === "reserved" || el.value === "approved") {
            _toggleAll(true, false);
		} else {
			_toggleAll(false, false);
		}
	}

	/**
	* Saves the term on server side
	*/
	function _doSave(needConfirm){

		var sName = Dom.get('edName').value;
		var sDesc = Dom.get('edDescription').value;
		var sStatus = Dom.get('trm_Status').value;
		var iInverseId = Number(Dom.get('edInverseTermId').value);
		iInverseId = (iInverseId>0) ?iInverseId:null;
		var iParentId = Number(Dom.get('edParentId').value);
		iParentId = (iParentId>0)?iParentId:null;
		var iParentId_prev = Number(_currentNode.data.parent_id);
		iParentId_prev = (iParentId_prev>0)?iParentId_prev:null;

		var wasChanged = ((_currentNode.label !== sName) ||
			(_currentNode.data.description !== sDesc) ||
			(_currentNode.data.status !== sStatus) ||
			(iParentId_prev !== iParentId) ||
			( !(Hul.isempty(_currentNode.data.inverseid)&&Hul.isnull(iInverseId)) &&
				Number(_currentNode.data.inverseid) !== iInverseId));

		if(wasChanged || !isExistingNode(_currentNode) ){

			if(Hul.isempty(sName)){
				if(needConfirm){
					alert("Field 'Display Name' is mandatory");
					Dom.get('edName').setFocus();
				}
				return;
			}

			if(needConfirm){
				var r=confirm("Term was modified. Save it?");
				if (!r) {
					//if new - remove from tree
					if( isNaN(Number(_currentNode.data.id)) ){
						_doDelete(false);
					}
					return;
				}
			}

			_currentNode.label = sName;
			_currentNode.data.description = sDesc;
			_currentNode.data.status = sStatus;

			_currentNode.data.inverseid = (iInverseId>0) ?iInverseId:null;
			_currentNode.title = _currentNode.data.description;

			var needReload = (_currentNode.data.parent_id != iParentId);
			_currentNode.data.parent_id = iParentId;

			_currTreeView.render();

			_updateTermsOnServer(_currentNode, needReload);
			//alert("TODO SAVE ON SERVER");
		}
	}

	/**
	* Sends data to server
	*/
	function _updateTermsOnServer(node, _needReload)
	{

		var term = node.data;
		var needReload = _needReload;

		var oTerms = {terms:{
				colNames:['trm_Label','trm_InverseTermId','trm_Description','trm_Domain','trm_ParentTermID','trm_Status'],
				defs: {}
		}};
		oTerms.terms.defs[term.id] = [node.label, term.inverseid, term.description, term.domain, term.parent_id, term.status ];

		var str = YAHOO.lang.JSON.stringify(oTerms);

		if(!Hul.isnull(str)) {
//DEBUG alert("Stringified changes: " + str);

			var _updateResult = function(context){

					if(!context) {
						alert("An error occurred trying to contact the database");
					} else {

						var error = false,
							report = "",
							ind;

						for(ind in context.result)
						{
						if(!Hul.isnull(ind)){
							var item = context.result[ind];
							if(isNaN(item)){
								alert("An error occurred: " + item);
								error = true;
							}else{
								node.data.id = item;
								//update ID field
								if(_currentNode ===  node){
									Dom.get('edId').value = item;
								}
								if(_parentNode){
									_onNodeClick(_parentNode);
								}
							}
						}
						}//for

						if(!error) {
								top.HEURIST.terms = context.terms;

								_isSomethingChanged = true;
								Dom.get('div_btnAddChild').style.display = "inline-block";
								Dom.get('btnDelete').value = "Delete Term";
								Dom.get('btnSave').value = "Save";
								Dom.get('div_SaveMessage').style.display = "inline-block";
								setTimeout(function(){Dom.get('div_SaveMessage').style.display = "none";}, 2000);
								//alert("Term was succesfully saved");

								if(needReload){
									var ind = _tabView.get("activeIndex");
									_fillTreeView((ind===0)?_termTree1:_termTree2);
								}
						}
					}
			};

			//
			var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
			var callback = _updateResult;
			var params = "method=saveTerms&data=" + encodeURIComponent(str)+"&db="+_db;
			top.HEURIST.util.getJsonData(baseurl, callback, params);
		}
	}

	/**
	* new of existing node
	*/
	function isExistingNode(node){
			return ((typeof node.data.id === "number") || node.data.id.indexOf("-")<0);
	}

	/**
	* Deletes current term
	*/
	function _doDelete(needConfirm){
		if(_currentNode===null) return;
		var isExistingTerm = isExistingNode(_currentNode);

		var r = (!needConfirm) ||
		confirm(isExistingTerm
		?("Delete term\n\n     '"+_currentNode.label+"'\n\nAre you sure? \nWarning: All child terms will be deleted as well")
		:"Cancel the addition of new term?");

		if (r && !Hul.isnull(_currTreeView)) {
			//_currTreeView.removeChildren(_currentNode);

			if(isExistingTerm){
				//this term exists in database - delete it

					function __updateAfterDelete(context) {

							if(!context){
								alert("Unknown server side error");
							}
							else if(Hul.isnull(context.error)){
								top.HEURIST.terms = context.terms;

								_currTreeView.popNode(_currentNode);
								_currTreeView.render();
								_currentNode = null;
								_onNodeClick(null);
								_isSomethingChanged = true;
							}
					}

					var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
					var callback = __updateAfterDelete;
					var params = "method=deleteTerms&trmID=" + _currentNode.data.id+"&db="+_db;
					top.HEURIST.util.getJsonData(baseurl, callback, params);
			}else{
				_currTreeView.popNode(_currentNode);
				_currTreeView.render();
				_currentNode = null;
				_onNodeClick(null);
			}
		}
	}

	/**
	* Open popup and select new parent term
	*/
	function _selectParent(){

	if(_currentNode===null) return;

	var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));

	Hul.popupURL(top, top.HEURIST.basePath +
		"admin/structure/selectTermParent.html?domain="+_currentDomain+"&child="+_currentNode.data.id+"&db="+db,
		{
		"close-on-blur": false,
		"no-resize": true,
		height: 500,
		width: 450,
		callback: function(newparent_id) {
			if(newparent_id) {
				if(newparent_id === "root") {
					Dom.get('edParentId').value = "";
				}else{
					Dom.get('edParentId').value = newparent_id;
				}
				_doSave(false);
			}
		}
	});



	}

	/**
	* Adds new child term for current term or adds new root term
	*/
	function _doAddChild(isRoot, value)
	{
		var term;
		if(value==null){
			if (isRoot){
					value = {id:null,label:"new term [vocab]",desription:""};
				} else {
					value = {id:null,label:"new term",desription:""};
				}
		}

		if(isRoot){

			var rootNode = _currTreeView.getRoot();

			term = {}; //new Object();
			term.id = (value.id)?value.id:"0-" + (rootNode.getNodeCount()); //correct
			term.parent_id = null;
			term.domain = _currentDomain;
			term.label = value.label;
			term.description = value.description;
			term.inverseid = null;

			rootNode = new YAHOO.widget.TextNode(term, rootNode, false); // Create root node
			_currTreeView.render();

			rootNode.focus(); //expand

			_onNodeClick(rootNode);

		}else if(!Hul.isnull(_currentNode))
		{
			term = {}; //new Object();
			term.id = (value.id)?value.id:_currentNode.data.id+"-" + (_currentNode.getNodeCount());  //correct
			term.parent_id = _currentNode.data.id;
			term.domain = _currentDomain;
			term.label = value.label;
			term.description = value.description;
			term.inverseid = null;

			var newNode = new YAHOO.widget.TextNode(term, _currentNode, false);
			_currTreeView.render();

			var _temp = _currentNode;

			newNode.focus(); //expand

			_onNodeClick(newNode);

			_parentNode = _temp;
		}
	}

	/**
	* take the current selection from resSearch, find and open it in tree and show in edit form
	*/
	function _doEdit(){
		var sel = Dom.get('resSearch');
		if(sel.selectedIndex>=0){
			var nodeid = sel.options[sel.selectedIndex].value;
			var node = _findNodeById(nodeid, true);
			if(!Hul.isnull(node)){
				_onNodeClick(node);
			}
		}
	}
	function _doSelectInverse(){
		var sel = Dom.get('resSearchInverse');
		if(sel.selectedIndex>=0 && !Hul.isnull(_currentNode) ){

			var nodeid = sel.options[sel.selectedIndex].value;
			if(_currentNode.data.id===nodeid){
				alert("Not possible to inverse on itself");
			}else{
				Dom.get('edInverseTerm').value = sel.options[sel.selectedIndex].text;
				Dom.get('edInverseTermId').value = nodeid;
				Dom.get('btnInverseSetClear').value = 'clear';
				Dom.get('formInverse').style.display = "none";
			}
		}
	}

	/**
	* invokes popup to import list of terms
	*/
	function _import(isRoot) {

		if(isRoot || !Hul.isnull(_currentNode)){

			var term_id = (isRoot)?0:_currentNode.data.id;

			var sURL = top.HEURIST.baseURL + "admin/structure/editTermsImport.php?db="+ _db +
						"&parent="+term_id+
						"&domain="+_currentDomain;

			Hul.popupURL(top, sURL, {
					"close-on-blur": false,
					"no-resize": false,
					height: 140,
					width: 500,
					callback: _import_complete
			});

		}
	}

	/**
	*  Add the list of imported terms
	*/
	function _import_complete(context){
		if(context && !context.error) {
			top.HEURIST.terms = context.terms;
			var res = context.result,
				ind;
				parentNode = (context.parent===0)?_currTreeView.getRoot():_currentNode,
				fi = top.HEURIST.terms.fieldNamesToIndex;

			if(res.length>0){

			for (ind in res)
			{
				if(!Hul.isnull(ind)){
					var termid = res[ind];

					var arTerm = top.HEURIST.terms.termsByDomainLookup[_currentDomain][termid];

					var term = {}; //new Object();
					term.id = termid;
					term.label = arTerm[fi.trm_Label];
					term.description = arTerm[fi.trm_Description];
					term.parent_id = context.parent; //_currentNode.data.id;
					term.domain = _currentDomain;
					term.inverseid = null;

					var newNode = new YAHOO.widget.TextNode(term, parentNode, false);
				}
			}//for
			_currTreeView.render();
			parentNode.focus(); //expand
					/*var _temp = _currentNode;
					_onNodeClick(_currentNode);
					_parentNode = _temp;
					*/
			}//if length>0
		}
	}


	//
	//public members
	//
	var that = {

				doSave: function(){ _doSave(false); },
				doDelete: function(){ _doDelete(true); },
				doAddChild: function(isRoot){ _doAddChild(isRoot); },
				selectParent: function(){ _selectParent(); },
				onChangeStatus: function(event){ _onChangeStatus(event); },

				findNodes: function(sSearch){ return _findNodes(sSearch); },
				doEdit: function(){ _doEdit(); },
				doSelectInverse: function(){ _doSelectInverse(); },

				doImport: function(isRoot){ _import(isRoot); },

				applyChanges: function(event){ //for window mode only
						if(_isWindowMode){
							window.close(_isSomethingChanged);
							//top.HEURIST.util.closePopup.apply(this, [_isSomethingChanged]);
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

}


/*
* general functions
*/

/**
* Clear button listener
*/
function setOrclearInverseTermId(){
	if(Dom.get('btnInverseSetClear').value==='cancel'){
		Dom.get('btnInverseSetClear').value = (Dom.get('edInverseTermId').value!=="0")?'clear':'set';
		Dom.get('formInverse').style.display = "none";
	}else if(Dom.get('edInverseTermId').value==="0"){
		//show inverse div
		Dom.get('btnInverseSetClear').value = 'cancel';
		Dom.get('formInverse').style.display = "block";
	}else{
		Dom.get('btnInverseSetClear').value = 'set';
		Dom.get('edInverseTerm').value = "";
		Dom.get('edInverseTermId').value = "0";
	}
}

/**
*
*/
function doSearch(event){

	var el = event.target;
	var sel = (el.id === 'edSearch')?Dom.get('resSearch'):Dom.get('resSearchInverse');

	//remove all
	while (sel.length>0){
		sel.remove(0);
	}

	//el = Dom.get('edSeartch');
	if(el.value && el.value.length>2){

		//fill selection element with found nodes
		var nodes = editTerms.findNodes(el.value),
			ind;
		for (ind in nodes)
		{
		if(!Hul.isnull(ind)){
			var node = nodes[ind];

			var option = document.createElement("option");
				option.text = getParentLabel(node);
				option.value = node.data.id;
				option.title = option.text;
			try {
				// for IE earlier than version 8
				sel.add(option, sel.options[null]);
			}catch (e){
				sel.add(option,null);
			}
		}
		}//for

	}
}
