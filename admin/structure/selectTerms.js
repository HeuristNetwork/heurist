// SelectTerms object
var selectTerms;

//aliases
var Dom = YAHOO.util.Dom;

/**
*@todo - move to separate utility file
*/
function isnull(obj){
	return ((obj===null) || (obj===undefined));// old js way typeof(obj)==='undefined');
}

/**
* SelectTerms - class for pop-up window to select terms for editing detail type
*
* public methods
*
* apply
* cancel
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2011.0427
*/
function SelectTerms() {

	var _datatype,
		_allTerms, //all terms
		_disTerms; //disabled terms

	var treesByDomain, //from HEURIST
		termsByDomainLookup, //from HEURIST
		existingTree = [],
		disabledTermsList = [],	//array of disabled terms
		_selectedTermsTree,	//tree for selected terms
		_termTree,			//tree for all terms
		termArray = {};		// Contains the tree structure

	/**
	* Initialization of input form
	*
	* Reads GET parameters, creates TabView and triggers init of first tab
	*/
	function _init() {

		var dtyID;

		// read parameters from GET request
		// if ID is defined take all data from detail type record
		// otherwise try to get these parameters from request
		//
		if (location.search.length > 1) {
				top.HEURIST.parameters = top.HEURIST.parseParams(location.search);
				dtyID = top.HEURIST.parameters.detailTypeID;

				if(!isnull(dtyID)){
					var dt = top.HEURIST.detailTypes.typedefs[dtyID];
					if (!isnull(_dt)) {
						_datatype = dt[2];
						_allTerms = dt[9];
						_disTerms = dt[10];

						document.getElementById("dtyName").innerHTML = "Detailtype: " + dt[0];
					}
				}
				if(isnull(_datatype)){
						_datatype = top.HEURIST.parameters.datatype;
						_allTerms = top.HEURIST.parameters.all;
						_disTerms = top.HEURIST.parameters.dis;
				}


				if(isnull(_datatype)) {
					document.getElementById("dtyName").innerHTML = "ERROR: Detailtype was not found";
					// TODO: Stop page from loading
					return;
				}else if(_datatype === "enum"){
					treesByDomain = top.HEURIST.terms.treesByDomain['enum'];
					termsByDomainLookup = top.HEURIST.terms.termsByDomainLookup['enum'];

				}else if(_datatype === "relationtype" || _datatype === "relmarker")
				{
					treesByDomain = top.HEURIST.terms.treesByDomain.relation;
					termsByDomainLookup = top.HEURIST.terms.termsByDomainLookup.relation;

				}else{
					document.getElementById("dtyName").innerHTML = "ERROR: Detailtype '" + _datatype + "' is of invalid base type";
					// TODO: Stop page from loading
					return;
				}

				existingTree = expandJsonStructure(_allTerms);
				disabledTermsList = expandJsonStructure(_disTerms);

				//create trees
				_treesInit();

		}


	}//end _init

	/**
	* utility function -  converts json string to array
	* @todo - mode to separate utility file
	*/
	function expandJsonStructure( jsonString ) {
		var retStruct = [];
		if(jsonString && jsonString !== "") {
				try {
					retStruct = eval(jsonString);
				} catch(e1) {
				try {
					retStruct = YAHOO.lang.JSON.parse(jsonString);
				} catch(e2) {
					retStruct = [];
				}
			}
		}
		return retStruct;
	}

	/**
	* Clears all selection
	*/
	function _clearSelection(){

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

	/**
	* Empties disabled list
	*/
	function _clearDisables(){
		// Reset the 'selected terms tree'
		_selectedTermsTree.removeChildren(_selectedTermsTree.getRoot());
		disabledTermsList = [];
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

		var _allTermsNew = YAHOO.lang.JSON.stringify(termIDTree);
		var _disTermsNew = YAHOO.lang.JSON.stringify(disabledTermsList);


		if ((_allTerms !== _allTermsNew) || (_disTerms !== _disTermsNew)){
			window.close(_allTermsNew, _disTermsNew);
		} else {
			window.close(null, null);
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
	function _selectAllChildren(parent) { //
			var parentNode = _termTree.getNodeByIndex(parent);
			if(parentNode.children.length > 0) {
				var index = 0;
				while(index < parentNode.children.length) { // While it has children, select them and look if they have children too
					var child = parentNode.children[index];
					child.toggleHighlight();
					if(child.children.length > 0) {
						_selectAllChildren(child.index);
					}
					index++;
				}
			}
	}

	/**
	* Gets term ID for given node
	*/
	function getTermIDFromNode(node) {
		var tempTermID = node.label.split('id="');
		var tempTermID2 = tempTermID[1].split('">'); // Use split to retrieve termID
		var termID = tempTermID2[0];
		return termID;
	}

	/**
	* Creates an array (in var termArray) with all selected terms
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
	*/
	function _buildTermTree(parent) { // Look up al root terms and create the nodes

		var nodeIndex,
			term;

		function __createChildren(parentNode, parentEntry) { // Recursively get all children
				var child;
				for(child in parentNode) {
					nodeIndex = _termTree.getNodeCount()+1;

					term = {}; //new Object();
					term.label = '<div id="'+child+'"><a href="javascript:void(0)" onClick="selectTerms.selectAllChildren('+
									nodeIndex+')"> All </a> '+termsByDomainLookup[child][0]+'</div>';
					term.href = "{javascript:void(0)}"; // To make 'select all' clickable, but not leave the page when hitting enter

					childNode = new YAHOO.widget.TextNode(term, parentEntry, false); // Create the node

					if(_inExistingTree(child)) {
						childNode.highlightState = 1;
						parentEntry.expand();
					}

					__createChildren(parentNode[child], childNode); // __createChildren() again for every child found
				}
		}

		var parentElement;
		for (parentElement in treesByDomain) {
			nodeIndex = _termTree.getNodeCount()+1;

			term = {}; //new Object();
			term.label = '<div id="'+parentElement+'"><a href="javascript:void(0)" onClick="selectTerms.selectAllChildren('+nodeIndex+')"> All </a> '+termsByDomainLookup[parentElement][0]+'</div>';
			term.href = "{javascript:void(0)}"; // To make 'select all' clickable, but not leave the page when hitting enter

			var topLayerParent = new YAHOO.widget.TextNode(term, parent, false); // Create the node

			if(_inExistingTree(parentElement)) {
				topLayerParent.highlightState = 1;
			}

			var parntNode = treesByDomain[parentElement];

			__createChildren(parntNode, topLayerParent); // Look for children of the node
		}
	}

	/**
	* Build a tree with all selected terms
	*/
	function _buildSelectedTermsTree(termNode, parentNode) {
		var index = 0;
		var childNode;
		while(index < termNode.children.length) { // While term in _termTree has children
			if(termNode.children[index].highlightState === 1) { // If the term is selected, add it to the 'selected term tree'

				var tempTermName = termNode.children[index].label.split(" </a> "); // Two splits to find out the term name
				var termName = tempTermName[1].split("</div>");

				childNode = new YAHOO.widget.TextNode('<div id="'+getTermIDFromNode(termNode.children[index])+'"><a href="javascript:void(0)"></a>' + termName[0] + '</div>', parentNode, true);
				childNode.href = "{javascript:void(0)}";

				selectedIndex = 0;
				while(selectedIndex < disabledTermsList.length) {
					if(disabledTermsList[selectedIndex] === getTermIDFromNode(childNode)) {
						childNode.toggleHighlight();
					}
					selectedIndex++;
				}
			}
			else {
				childNode = "";
			}
			if(termNode.children[index].children.length > 0) { // If term has children (selected or not doesn't matter)
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
		}
	}

	/**
	* Create an array with all selected (disabled) terms in the selected term tree
	*/
	function _setDisabledTerms()
	{
		var disabledNodes = _selectedTermsTree.getNodesByProperty('highlightState',1);
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
				var isHeader = (headers[termID]? true:false);
				var opt = new Option(termName,termID);

				var option = document.createElement("option");
				option.text = termName;
				option.value = termID;
				option.className = "depth" + depth;

				if(isHeader) { // header term behaves like an option group
					option.className +=  ' termHeader';
					option.disabled = true;
				}
				// not used if (termID == defaultTermID) {option.selected = true;}
				try {
					// for IE earlier than version 8
					sel.add(option, sel.options[null]);
				}catch (e){
					sel.add(option,null);
				}

				if(typeof termSubTree[termID] === "object") {
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

		_termTree = new YAHOO.widget.TreeView("termTree");
		_buildTermTree(_termTree.getRoot()); // Fill the tree with all terms

		// Selected terms tree
		_selectedTermsTree = new YAHOO.widget.TreeView("selectedTermsTree");

		_selectedTermsTree.subscribe("clickEvent",
				function() { // On click, select (disable) the term, and recreate the selected terms, and disabled terms arrays
							this.onEventToggleHighlight.apply(this,arguments);
							_createPreview();
				});
		_selectedTermsTree.render();

		_termTree.subscribe("clickEvent",
				function() { // On click, select the term, and add it to the selected terms tree
					this.onEventToggleHighlight.apply(this,arguments);

					_selectedTermsTree.removeChildren(_selectedTermsTree.getRoot()); // Reset the 'selected terms tree'
					_buildSelectedTermsTree(_termTree.getRoot(), _selectedTermsTree.getRoot()); // Rebuild the 'selected terms tree'
					_selectedTermsTree.render();

					_createPreview();
				});


		/* ??????
		var tempIndex = 0;
		while(tempIndex < tempDisabledTerms.length) {
			disabledTermsList.push(tempDisabledTerms[tempIndex]);
			tempIndex++;
		}
		top.HEURIST.terms.selectedDisabled = disabledTermsList;*/

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
				*	Apply form
				*/
				apply : function () {
						_getUpdatedTerms();
				},

				/**
				* Cancel form
				*/
				cancel : function () {
					window.close(null, null);
				},
				clearSelection : function () {
					_clearSelection();
				},
				clearDisables : function () {
					_clearDisables();
				},
				selectAllChildren : function (nodeIndex) {
					_selectAllChildren(nodeIndex);
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
