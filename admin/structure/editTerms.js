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
		_currentDomain,
		_db;

	/**
	*	Initialization of tabview with 2 tabs with treeviews
	*/
	function _init (){

		_db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));

		_tabView.addTab(new YAHOO.widget.Tab({
							id: 'enum',
							label: 'Enum',
							content: '<div style="height:90%; max-width:300; overflow: auto;"><div id="termTree1" class="termTree ygtv-highlight"></div></div>'
						}));
		_tabView.addTab(new YAHOO.widget.Tab({
							id: 'relation',
							label: 'Relation',
							content: '<div style="height:90%; max-width:300; overflow:auto"><div id="termTree2" class="termTree ygtv-highlight"></div></div>'
						}));
		_tabView.addListener("activeTabChange", _handleTabChange);
		_tabView.appendTo("tabContainer");

		_tabView.set("activeIndex", 0);

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
			termsByDomainLookup = top.HEURIST.terms.termsByDomainLookup[_currentDomain];

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
			term.label = arTerm[0];
			term.description = arTerm[1];
			term.inverseid = arTerm[2];

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
					term.label = arTerm[0];
					term.description = arTerm[1];
					term.inverseid = arTerm[2];

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

		if(_currentNode !== node)
		{
			if(!Hul.isnull(_currentNode)){
				_doSave(true);
			}
			_currentNode = node;

		if(!Hul.isnull(node)){
			Dom.get('formInverse').style.display = "block";
			Dom.get('formEditor').style.display = "block";
			Dom.get('formMngTree2').style.display = "block";

			Dom.get('edName2').value = node.label;
			//	alert("label was clicked"+ node.data.id+"  "+node.data.domain+"  "+node.label);
			Dom.get('edId').value = node.data.id;
			Dom.get('edName').value = node.label;
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
			}else{
					node.data.inverseid = null;
					Dom.get('edInverseTermId').value = '0';
					Dom.get('edInverseTerm').value = '';
			}
		}
		}

		if(Hul.isnull(node)){
				Dom.get('formInverse').style.display = "none";
				Dom.get('formEditor').style.display = "none";
				Dom.get('formMngTree2').style.display = "none";
		}

	}

	/**
	* Saves the term on server side
	*/
	function _doSave(needConfirm){

		var sName = Dom.get('edName').value;
		var sDesc = Dom.get('edDescription').value;
		var iInverseId = Number(Dom.get('edInverseTermId').value);
		iInverseId = (iInverseId>0) ?iInverseId:null;

		var wasChanged = ((_currentNode.label !== sName) ||
			(_currentNode.data.description !== sDesc) ||
			( !(Hul.isempty(_currentNode.data.inverseid)&&Hul.isnull(iInverseId)) &&
				Number(_currentNode.data.inverseid) !== iInverseId));

		if(wasChanged){

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

			_currentNode.data.inverseid = (iInverseId>0) ?iInverseId:null;
			_currentNode.title = _currentNode.data.description;
			_currTreeView.render();

			_updateTermsOnServer(_currentNode);
			//alert("TODO SAVE ON SERVER");
		}
	}

	/**
	* Sends data to server
	*/
	function _updateTermsOnServer(node)
	{

		var term = node.data;

		var oTerms = {terms:{
				colNames:['trm_Label','trm_InverseTermId','trm_Description','trm_Domain','trm_ParentTermID'],
				defs: {}
		}};
		oTerms.terms.defs[term.id] = [node.label, term.inverseid, term.description, term.domain, term.parent_id ];

		var str = YAHOO.lang.JSON.stringify(oTerms);

		if(!Hul.isnull(str)) {
alert("Stringified changes: " + str);

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
							}
						}
						}//for

						if(!error) {
								alert("Term was succesfully saved");
						}
					}
			};

			// TODO: Change base URL
			var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
			var callback = _updateResult;
			var params = "method=saveTerms&data=" + encodeURIComponent(str)+"&db="+_db;
			top.HEURIST.util.getJsonData(baseurl, callback, params);
		}
	}


	/**
	* Deletes current term
	*/
	function _doDelete(needConfirm){
		var r = (!needConfirm) ||
		confirm("Delete term '"+_currentNode.label+"'? Are you sure? All children terms will be deleted as well");

		if (r && !Hul.isnull(_currTreeView)) {
			//_currTreeView.removeChildren(_currentNode);

			if(_currentNode.data.id.indexOf("-")<0){
				//this term exists in database - delete it

					function __updateAfterDelete(context) {

							if(!context){
								alert("Unknown server side error");
							}
							else if(Hul.isnull(context.error)){
								_currTreeView.popNode(_currentNode);
								_currTreeView.render();
								_currentNode = null;
								_onNodeClick(null);
							}
					}

					var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
					var callback = __updateAfterDelete;
					var params = "method=deleteTerms&trmID=" + _currentNode.data.id+"&db="+_db;
					top.HEURIST.util.getJsonData(baseurl, callback, params);
			}
		}
	}

	/**
	* Adds new child term for current term or adds new root term
	*/
	function _doAddChild(isRoot)
	{
		var term;

		if(isRoot){

			var rootNode = _currTreeView.getRoot();

			term = {}; //new Object();
			term.id = "0-" + (rootNode.getNodeCount()); //correct
			term.parent_id = null;
			term.domain = _currentDomain;
			term.label = "New Term";
			term.description = "";
			term.inverseid = null;

			rootNode = new YAHOO.widget.TextNode(term, rootNode, false); // Create root node
			_currTreeView.render();

			rootNode.focus(); //expand

			_onNodeClick(rootNode);

		}else
		if(!Hul.isnull(_currentNode))
		{
			term = {}; //new Object();
			term.id = _currentNode.data.id+"-" + (_currentNode.getNodeCount());  //correct
			term.parent_id = _currentNode.data.id;
			term.domain = _currentDomain;
			term.label = "New Term";
			term.description = "";
			term.inverseid = null;
			var newNode = new YAHOO.widget.TextNode(term, _currentNode, false);
			_currTreeView.render();

			newNode.focus(); //expand

			_onNodeClick(newNode);
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
			}
		}
	}


	//
	//public members
	//
	var that = {

				doSave: function(){ _doSave(false); },
				doDelete: function(){ _doDelete(true); },
				doAddChild: function(isRoot){ _doAddChild(isRoot); },

				findNodes: function(sSearch){ return _findNodes(sSearch); },
				doEdit: function(){ _doEdit(); },
				doSelectInverse: function(){ _doSelectInverse(); },

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
function clearInverseTermId(){
	Dom.get('edInverseTerm').value = "";
	Dom.get('edInverseTermId').value = "0";
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
