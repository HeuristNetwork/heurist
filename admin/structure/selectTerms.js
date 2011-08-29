// SelectTerms object
var selectTerms;

//aliases
var Dom = YAHOO.util.Dom,
	Hul = top.HEURIST.util;

/**
* SelectTerms - class for pop-up window to select terms for editing detail type
*
* @param _isFilterMode - either select from all terms or filtering of existing tree
* @param _isWindowMode - true in window popup, false in div
* public methods
*
* apply
* cancel
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2011.0427
*/
function SelectTerms(_isFilterMode, _isWindowMode) {

	var _dtyID,
		_datatype,
		_allTerms, //all terms
		_disTerms, //disabled terms
		_callback_func; //callback function for non-window mode

	var treesByDomain, //from HEURIST   - tree
		termsByDomainLookup, //from HEURIST  - list
		existingTree = [],
		disabledTermsList = [],	//array of disabled terms
		disabledTermsListOriginal = [],	//array of originally disabled terms
		_selectedTermsTree,	//tree for selected terms
		_termTree,			//tree for all terms
		termArray = {};		// Contains the tree structure

	/**
	* Initialization of input form
	*
	* Reads GET parameters, creates TabView and triggers init of first tab
	*/
	function _init(dtyID, _callback) {

		_callback_func = _callback;

		// read parameters from GET request
		// if ID is defined take all data from detail type record
		// otherwise try to get these parameters from request
		//
		if(Hul.isnull(dtyID) && (location.search.length > 1)) {
				top.HEURIST.parameters = top.HEURIST.parseParams(location.search);
				dtyID = top.HEURIST.parameters.detailTypeID;
		}


		if(!Hul.isnull(dtyID)){

					_dtyID = dtyID;
					var dt = top.HEURIST.detailTypes.typedefs[dtyID].commonFields;
					if (!Hul.isnull(dt)) {
						_datatype = dt[2];
						_allTerms = dt[9];
						_disTerms = dt[10];

						Dom.get("dtyName").innerHTML = "Detailtype: " + dt[0];
					}
					if(Hul.isempty(_allTerms)){
						_isFilterMode = false;
					}
		}
		if(Hul.isnull(_datatype) && (location.search.length > 1)){
						_datatype = top.HEURIST.parameters.datatype;
						_allTerms = top.HEURIST.parameters.all;
						_disTerms = top.HEURIST.parameters.dis;
		}

		if(Hul.isnull(_datatype)) {
			Dom.get("dtyName").innerHTML = "ERROR: Detailtype was not found";
			// TODO: Stop page from loading
			return;
		}

				if(_datatype === "enum"){
					treesByDomain = top.HEURIST.terms.treesByDomain['enum'];
					termsByDomainLookup = top.HEURIST.terms.termsByDomainLookup['enum'];

				}else if(_datatype === "relationtype" || _datatype === "relmarker")
				{
					treesByDomain = top.HEURIST.terms.treesByDomain.relation;
					termsByDomainLookup = top.HEURIST.terms.termsByDomainLookup.relation;

				}else{
					Dom.get("dtyName").innerHTML = "ERROR: Detailtype '" + _datatype + "' is of invalid base type";
					// TODO: Stop page from loading
					return;
				}

				existingTree = Hul.expandJsonStructure(_allTerms);
				disabledTermsList = Hul.expandJsonStructure(_disTerms);

				///for filtered mode - to prevent enabling of originally disabled terms
				disabledTermsListOriginal = Hul.expandJsonStructure(_disTerms);

				//create trees
				_treesInit();




	}//end _init


	/**
	* Clears all selection
	*/
	function _clearSelection(){

			var p = confirm('Do you wish to clear all selection?');
			if(p){

			existingTree = [];

			//function for each node in _termTree - removes highlight
			function __resetSelection(node){
				node.highlightState = 0;
				return false;
			}
			//loop all nodes of tree
			_termTree.getNodesBy(__resetSelection);
			_termTree.render();

			//_termTree.removeChildren(_termTree.getRoot());
			//_buildTermTree(_termTree.getRoot()); // Fill the tree with all terms
			_clearDisables();

			}
	}

	/**
	* Empties disabled list
	*/
	function _clearDisables(){
		// Reset the 'selected terms tree'
		_selectedTermsTree.removeChildren(_selectedTermsTree.getRoot());

		if(_isFilterMode){
			//clone - restore originally selected
			disabledTermsList = (disabledTermsListOriginal.join(",")).split(",");
		}else{
			disabledTermsList = [];
		}

		// Rebuild the 'selected terms tree'
		_buildSelectedTermsTree(_termTree.getRoot(), _selectedTermsTree.getRoot());
		_selectedTermsTree.render();
		_createPreview();
	}

	/**
	* Result function for public "apply" method
	*
	* Creates 2 result strings from selected and disabled terms, returns then to invoker window
	* and closes this window
	*/
	function _getUpdatedTerms() {

		var termIDTree = _createTermArray(_selectedTermsTree.getRoot());
		try { // In case no terms have been disabled yet
			_setDisabledTerms();
		}catch(e) { }

		//results
		var _allTermsNew = YAHOO.lang.JSON.stringify(termIDTree);
		var _disTermsNew = YAHOO.lang.JSON.stringify(disabledTermsList);


		if ((_allTerms !== _allTermsNew) || (_disTerms !== _disTermsNew)){
			if(_isWindowMode){
				window.close(_allTermsNew, _disTermsNew, _dtyID);
			}else if (!Hul.isnull(_callback_func) ) {
				_callback_func(_allTermsNew, _disTermsNew, _dtyID);
			}
		} else {
			if(_isWindowMode){
				window.close();
			}else if (!Hul.isnull(_callback_func) ) {
				_callback_func();
			}

		}
	}

	/**
	* Verifies that term with given ID is existing selection tree
	*
	* @param termID - id of term to be searched
	* @return true if specified term is found in existingTree
	*/
	function _inExistingTree(termID) {

			var foundTerm;

			function __getSubtree(tree) {
				var term2;
				for( term2 in tree) {
					if(term2 === termID) {
						foundTerm = true;
					}
					else if(typeof tree[term2] === "object") {
							__getSubtree(tree[term2]);
					}
				}//for
			}

			if(existingTree === termID) {
				return true;
			}

			var term;
			for( term in existingTree) {
				if(term === termID) {
					return true;
				} else if(typeof existingTree[term] === "object") {
					__getSubtree(existingTree[term]);
				}
			}

			if(foundTerm) {
				foundTerm = false;
				return true;
			}
			return false;
	}

	/**
	* Listener of checkbox of item in the tree
	*
	* Selects all children of the node clicked
	*/
	function _selectAllChildren(termid) { //
			var parentNode = _findNodeById(termid); // _termTree.getNodeByIndex(parent);
			if(parentNode.children.length > 0) {
				var index = 0;
				while(index < parentNode.children.length) { // While it has children, select them and look if they have children too
					var child = parentNode.children[index];
					child.toggleHighlight(); //mark the checkbox
					if(child.children.length > 0) {
						_selectAllChildren(child.data.id); //index);
					}
					index++;
				}
			}
	}
	/**
	* Finds node by term id
	*/
	function _findNodeById(termid) {

		//internal
		function __doSearchById(node){
			return (node.data.id==termid);
		}

		var nodes = _termTree.getNodesBy(__doSearchById);

		//select and expand the node
		if(nodes){
			var node = nodes[0];
			return node;
		}else{
			return null
		}
	}


	/**
	* Gets term ID for given node
	*/
	function getTermIDFromNode(node) {
		var termID = node.id;
		return termID;
	}

	/**
	* Creates an array (in var termArray) with all selected terms (from selectedTermsTree (that is in the middle))
	*/
	function _createTermArray(parent, parentsArray) { //

		var index = 0,
			nextParent;
		// If parent is root
		if(parent === _selectedTermsTree.getRoot()) {
			termArray = {};

			while(index < parent.children.length) { // While root had children

				// Add term to array, as a (so far empty) array
				nextParent = termArray[getTermIDFromNode(parent.children[index])] = {};
				if(parent.children[index].children.length > 0) {
					// If the term has children, run _createTermArray() again
					_createTermArray(parent.children[index], nextParent);
				}
				index++;
			}
			return termArray;
		}
		else
		{ // If parent is not root

			while(index < parent.children.length) { // While parent contains more children

				// Add term to parent (instead of array root, which happens in the first if), as a (so far empty) array
				nextParent = parentsArray[getTermIDFromNode(parent.children[index])] = {};
				if(parent.children[index].children.length > 0) {
					// If the term has children, run _createTermArray() again
					_createTermArray(parent.children[index], nextParent);
				}
				index++;
			}
		}
	}

/*
TREE REALTED ROUTINES ---------------------------------------
*/
	/**
	* Creates tree for all terms
	* @param parent - root noed of "all terms" tree
	*/
	function _buildTermTree(parent) { // Look up al root terms and create the nodes

		var nodeIndex,
			term,
			cnt_children;

		function __createChildren(parentNode, parentEntry) { // Recursively get all children
				var term_id;
				for(term_id in parentNode) {
					nodeIndex = _termTree.getNodeCount()+1;

					term = {}; //new Object();
					term.id = term_id;

					cnt_children = Object.keys(parentNode[term_id]).length;
					term.label = '<div id="'+term_id+'">';

					if(cnt_children>0){
						term.label = term.label +
					'<a href="javascript:void(0)" onClick="selectTerms.selectAllChildren(\''+
											term_id+'\')">&nbsp;&nbsp;All</a>';//+termsByDomainLookup[term_id][0]+'</div>';
						term.href = "{javascript:void(0)}"; // To make 'select all' clickable, but not leave the page when hitting enter
					}else if(_isFilterMode){
						term.label = term.label + '&nbsp;&nbsp;';
					}

					if(_isFilterMode){//form recstructure edit

						if(_inExistingTree(term_id)) { //selected

							term.label = term.label + ((cnt_children>0)?'<b>':'');

							if(_isDisabledOriginally(term_id)){
								term.label = term.label + '<font color="#cccccc">'+termsByDomainLookup[term_id][0]+'</font>';
							}else{
								term.label = term.label + termsByDomainLookup[term_id][0];
							}
							term.label = term.label + '</div>';

							term.label = term.label + ((cnt_children>0)?'</b>':'') + '</div>';

							childNode = new YAHOO.widget.TextNode(term, parentEntry, false); // Create the node
							childNode.highlightState = 1;
							parentEntry.expand();
						}else{
							childNode = parentEntry;
						}

					}else{
						term.label = term.label + '&nbsp;&nbsp;' +
								((cnt_children>0)?'<b>':'')+
								termsByDomainLookup[term_id][0]+
								((cnt_children>0)?'</b> ('+cnt_children+')':'')+'</div>';
						childNode = new YAHOO.widget.TextNode(term, parentEntry, false); // Create the node

						if(_inExistingTree(term_id)) { //selected
							childNode.highlightState = 1;
							parentEntry.expand();
						}
					}

					__createChildren(parentNode[term_id], childNode); // __createChildren() again for every child found
				}
		}

		var parentElement;
		var topLayerParent;
		for (parentElement in treesByDomain) {
			nodeIndex = _termTree.getNodeCount()+1;

			cnt_children = Object.keys(treesByDomain[parentElement]).length;

			term = {}; //new Object();
			term.id = parentElement;
			term.label = '<div id="'+parentElement+'">';

			if(cnt_children>0){
				term.label = term.label + '<a href="javascript:void(0)" onClick="selectTerms.selectAllChildren(\''+
							term.id+'\')">&nbsp;&nbsp;All</a>&nbsp;'; //+termsByDomainLookup[parentElement][0]+'</div>';
				term.href = "{javascript:void(0)}"; // To make 'select all' clickable, but not leave the page when hitting enter
			}

			if(_isFilterMode){
				if(_inExistingTree(parentElement)) {

							term.label = term.label + ((cnt_children>0)?'<b>':'');

							if(_isDisabledOriginally(parentElement)){
								term.label = term.label + '<font color="#cccccc">'+termsByDomainLookup[parentElement][0]+'</font>';
							}else{
								term.label = term.label + termsByDomainLookup[parentElement][0];
							}

							term.label = term.label + ((cnt_children>0)?'</b>':'') + '</div>';

					topLayerParent = new YAHOO.widget.TextNode(term, parent, false); // Create the node
					topLayerParent.highlightState = 1;
				}else{
					topLayerParent = parent;
				}
			}else{
				term.label = term.label + '&nbsp;&nbsp;'+
								((cnt_children>0)?'<b>':'')+
								termsByDomainLookup[parentElement][0]+
								((cnt_children>0)?'</b> ('+cnt_children+')':'')+'</div>';
				topLayerParent = new YAHOO.widget.TextNode(term, parent, false); // Create the node
				if(_inExistingTree(parentElement)) {
					topLayerParent.highlightState = 1;
				}
			}

			var parntNode = treesByDomain[parentElement];

			__createChildren(parntNode, topLayerParent); // Look for children of the node
		}
	}

	/**
	* Build a tree with all selected terms (it is in the middle of window)
	* @param termNode - root node of "all terms" tree
	* @param parentNode - root node of "selected terms" tree
	*/
	function _buildSelectedTermsTree(termNode, parentNode) {
		var index = 0;
		var childNode;
		while(index < termNode.children.length) { // While term in _termTree has children
			if(termNode.children[index].highlightState === 1) { // If the term is selected, add it to the 'selected term tree'

				var term_id = termNode.children[index].data.id;
				var termName = termsByDomainLookup[term_id][0];

				childNode = new YAHOO.widget.TextNode('<div id="'+term_id+'"><a href="javascript:void(0)"></a>&nbsp;' + termName + '</div>', parentNode, true);
				childNode.id = term_id;
				childNode.href = "{javascript:void(0)}";

				if(!_isDisabled(term_id)){
					childNode.highlightState = 1;
					//childNode.toggleHighlight();
				}

				//now parentNode has childrens 1) add it to disables 2) make font bold
				if(parentNode!=_selectedTermsTree.getRoot()){

					if(parentNode.children.length == 1){ //do it only once
						//1. add to disabled
						parentNode.highlightState = 0;

						//2. make bold label
						var termName2 = termsByDomainLookup[parentNode.id][0];
						parentNode.label =
						'<div id="'+parentNode.id+'"><a href="javascript:void(0)"></a>&nbsp;<b>' + termName2 + '</b></div>'
					}
				}
			}
			else {
				childNode = "";
			}
			// If term has children (selected or not doesn't matter)
			if(termNode.children[index].children.length > 0) {
				if(childNode) { // If it's parent was selected, call _buildSelectedTermsTree() with his parent, else, use the parent before that
					_buildSelectedTermsTree(termNode.children[index], childNode);
				}
				else {
					_buildSelectedTermsTree(termNode.children[index], parentNode);
				}
			}
			else {
				childNode = "";
			}
			index++;
		}//while
	}

	/**
	* Create an array with all selected (disabled) terms in the selected term tree
	*/
	function _setDisabledTerms()
	{

			var disabledNodes = _selectedTermsTree.getNodesByProperty('highlightState',0);
			index = 0;
			disabledTermsList = [];
			if(disabledNodes) {
				while(index < disabledNodes.length) {
					disabledTermsList.push(getTermIDFromNode(disabledNodes[index]));
					index++;
				}
			}

	}
	/**
	* Verifies if given term is disabled
	*/
	function _isDisabled(term_id){
				var index = 0;
				while(index < disabledTermsList.length) {
					if(disabledTermsList[index] === term_id) {
						return true;
					}
					index++;
				}
		return false;
	}
	/**
	* Verifies if given term is disabled originally
	*/
	function _isDisabledOriginally(term_id){
				var index = 0;
				while(index < disabledTermsListOriginal.length) {
					if(disabledTermsListOriginal[index] === term_id) {
						return true;
					}
					index++;
				}
		return false;
	}


	/**
	* Fills a Select HTML object filled with an option element for each term "depth first"
	* tagged with class depthN and termHeader according to the terms tree depth and if it's id in the headerList.
	* @author Stephen White
	**/
	function _createPreview() {
		//clear select/combobox
		var sel = Dom.get('previewList');
		while (sel.length>0){
			sel.remove(0);
		}

		var termIDTree = _createTermArray(_selectedTermsTree.getRoot());

		//add otions to select
		if (!termIDTree || termIDTree === "") { return null; }

		try { // In case no terms have been disabled yet
			_setDisabledTerms();
		}catch(e) { }

		var headers = {};
		var id;
		for (id in disabledTermsList) {  //??????? we may use indexOf, why so complexity?
			headers[disabledTermsList[id]] = disabledTermsList[id];
		}

		function __createSubTreeOptions(depth, termSubTree) {
			var termID;
			for( termID in termSubTree)
			{ // For every term in 'term'
				var termName = termsByDomainLookup[termID][0];
				var isDisabled = (headers[termID]? true:false);
				var isHeader = (Object.keys(termSubTree[termID]).length>0);
				var opt = new Option(termName,termID);

				var option = document.createElement("option");
				option.text = termName;
				option.value = termID;
				option.className = "depth" + depth;

				if(isHeader){
					option.className +=  ' termHeader';
					option.disabled = true;
				}else if(isDisabled) { // header term behaves like an option group
					option.className +=  ' termDisabled';
					option.disabled = true;
				}
				// not used if (termID == defaultTermID) {option.selected = true;}
				try {
					// for IE earlier than version 8
					sel.add(option, sel.options[null]);
				}catch (e){
					sel.add(option,null);
				}

				if(typeof termSubTree[termID] === "object" && isHeader) {
					if(depth === 7) { // A dept of 8 (depth starts at 0) is maximum, to keep it organised
						__createSubTreeOptions(depth, termSubTree[termID]);
					} else {
						__createSubTreeOptions(depth+1, termSubTree[termID]);
					}
				}
			}
		}
		__createSubTreeOptions(0,termIDTree);

		//if (!defaultTermID) sel.selectedIndex = 0;
	}

	/**
	* Initialize the trees
	* on initialization
	*/
	function _treesInit() {

		if(Hul.isnull(_termTree)){
			 _termTree = new YAHOO.widget.TreeView("termTree");
			 _termTree.subscribe("clickEvent",
				function() { // On click, select the term, and add it to the selected terms tree
					this.onEventToggleHighlight.apply(this,arguments);

					_selectedTermsTree.removeChildren(_selectedTermsTree.getRoot()); // Reset the 'selected terms tree'
					_buildSelectedTermsTree(_termTree.getRoot(), _selectedTermsTree.getRoot()); // Rebuild the 'selected terms tree'
					_selectedTermsTree.render();

					_createPreview();
				});
		}else{
			_termTree.removeChildren(_termTree.getRoot());
		}

		// Selected terms tree
		if(Hul.isnull(_selectedTermsTree)){
			_selectedTermsTree = new YAHOO.widget.TreeView("selectedTermsTree");

			_selectedTermsTree.subscribe("clickEvent",
					function() { // On click, select (disable) the term, and recreate the selected terms, and disabled terms arrays
						var term_id = arguments[0].node.id;
						if(!_isDisabledOriginally(term_id)) {
							if(arguments[0].node.children.length==0){ //parent notes are always disabled
								this.onEventToggleHighlight.apply(this,arguments); //original event
								_createPreview();
							}
						}
				});
		}else{
			_selectedTermsTree.removeChildren(_selectedTermsTree.getRoot());
		}

		/* ??????
		var tempIndex = 0;
		while(tempIndex < tempDisabledTerms.length) {
			disabledTermsList.push(tempDisabledTerms[tempIndex]);
			tempIndex++;
		}
		top.HEURIST.terms.selectedDisabled = disabledTermsList;*/

		// Fill the tree with all terms
		_buildTermTree(_termTree.getRoot());
		//redraw all terms tree
		_termTree.render();
		//create selected terms tree
		_buildSelectedTermsTree(_termTree.getRoot(), _selectedTermsTree.getRoot()); // Rebuild the 'selected terms tree'
		//redaw selected terms tree
		_selectedTermsTree.render();
		_createPreview();
	}

/*
END TREE REALTED ROUTINES ---------------------------------------
*/


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
				*	Apply form
				*/
				apply : function () {
						_getUpdatedTerms();
				},

				/**
				* Cancel form
				*/
				cancel : function () {
					if(_isWindowMode){
						window.close();
					}else if (!Hul.isnull(_callback_func) ) {
						_callback_func();
					}
				},
				clearSelection : function () {
					_clearSelection();
				},
				clearDisables : function () {
					_clearDisables();
				},
				selectAllChildren : function (termid) {
					_selectAllChildren(termid);
				},
				setFilterMode : function (val) {
					_isFilterMode = val;
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
