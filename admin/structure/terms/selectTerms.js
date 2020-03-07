/*
* Copyright (C) 2005-2019 University of Sydney
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
* @copyright   (C) 2005-2019 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

// SelectTerms object
var selectTerms;

//aliases
var Dom = YAHOO.util.Dom,
	Hul = window.hWin.HEURIST4.util;

/**
* SelectTerms
* class for pop-up window to select terms for editing detail type
*
* @param _isFilterMode - either select from all terms or filtering of existing tree
* @param _isWindowMode - true in window popup, false in div
* public methods
*
* apply
* cancel
*/

function SelectTerms(_isFilterMode, _isWindowMode) {

	var _className = "SelectTerms",
		_dtyID,
        _isNeedSave = false, //when it is invoked from editRecord - the changes are saved in defDetailType implicitely
		_datatype,
        _mode,
		_allTerms, //all terms
		_disTerms, //disabled terms
		_callback_func; //callback function for non-window mode

	var treesByDomain, //from db strucute   - tree
		termsByDomainLookup, //from db strucute  - list
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
				
				dtyID = Hul.getUrlParameter('detailTypeID', location.search); 
                _mode = Hul.getUrlParameter('mode', location.search);
                _isNeedSave = (_mode==="editrecord");
				var _dt_id = Hul.getUrlParameter('dtname', location.search);
                
				if(!Hul.isnull(_dt_id)){
                    if(isNaN(Number(_dt_id))){
                        Dom.get("dtyName").innerHTML = '<h2 class="dtyName">'+_dt_id+'</h2>';
                    }else if(_dt_id<0){
						Dom.get("dtyName").innerHTML = '<h2 class="dtyName">New Field Type</h2>';
					}else{
						Dom.get("dtyName").innerHTML = _getTitle(_dt_id);
					}
				}

				if(Hul.getUrlParameter('selonly', location.search) == "1"){
					Dom.get("headerDiv").style.display = "none";
					Dom.get("selectedTermsTree").className = "selectedTerms";
				}

		}


		if(!Hul.isnull(dtyID)){

					_dtyID = dtyID;
					var dt = window.hWin.HEURIST4.detailtypes.typedefs[dtyID].commonFields,
						fi = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex;

					if (!Hul.isnull(dt)) {
						_datatype = dt[fi.dty_Type];
						_allTerms = dt[fi.dty_JsonTermIDTree];
						_disTerms = dt[fi.dty_TermIDTreeNonSelectableIDs];

						Dom.get("dtyName").innerHTML = _getTitle(_dtyID);
					}
					if(Hul.isempty(_allTerms)){
						_isFilterMode = false;
					}
		}
		if(Hul.isnull(_datatype) && (location.search.length > 1)){
						_datatype = Hul.getUrlParameter('datatype', location.search);
						_allTerms = Hul.getUrlParameter('all', location.search);
						_disTerms = Hul.getUrlParameter('dis', location.search);
		}

		if(Hul.isnull(_datatype)) {
			Dom.get("dtyName").innerHTML = "ERROR: Detailtype was not found";
			// TODO: Stop page from loading
			return;
		}

				if(_datatype === "enum"){
					treesByDomain = window.hWin.HEURIST4.terms.treesByDomain['enum'];
					termsByDomainLookup = window.hWin.HEURIST4.terms.termsByDomainLookup['enum'];

				}else if(_datatype === "relation" || _datatype === "relationtype" || _datatype === "relmarker")
				{
					_datatype = "relation";
					treesByDomain = window.hWin.HEURIST4.terms.treesByDomain.relation;
					termsByDomainLookup = window.hWin.HEURIST4.terms.termsByDomainLookup.relation;

				}else{
					Dom.get("dtyName").innerHTML = "ERROR: Detailtype '" + _datatype + "' is of invalid base type";
					// TODO: Stop page from loading
					return;
				}

				
                existingTree = expandJsonStructure(_allTerms);
                disabledTermsList = expandJsonStructure(_disTerms);

				///for filtered mode - to prevent enabling of originally disabled terms
				disabledTermsListOriginal = expandJsonStructure(_disTerms);

				//create trees
				_treesInit();




	}//end _init

    
    function expandJsonStructure(jsonString){
        var retStruct = "";
        if(jsonString && jsonString !== "") {
            try {
                retStruct = $.parseJSON(jsonString);
            } catch(e) {
            }
        }
        return retStruct;        
    }
    
	//
	function _getTitle(dt_id){
			var dtname =  "<div style='display:inline-block;font-weight:800;'>field id:</div><h2 style='display:inline-block;padding-left:5px;'>" + dt_id + '</h2>';
			var dt = window.hWin.HEURIST4.detailtypes.typedefs[dt_id].commonFields;
			if (!Hul.isnull(dt)) {
							dtname = dtname + " <div style='display:inline-block;font-weight:800;padding-left:20px;'>name:</div><h2 style='display:inline-block;padding-left:5px;'>"+dt[window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex.dty_Name]+"</h2>";
			}
			return dtname;
	}


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
	* Empties disabled list (not used anymore)
	*/
	function _clearDisables(){
		// Reset the 'selected terms tree'
		_selectedTermsTree.removeChildren(_selectedTermsTree.getRoot());

		if(_isFilterMode){
			//clone
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
            //there were changes
            if(_isNeedSave){
                 _updateDetailTypeOnServer(_allTermsNew, _disTermsNew)
            }

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
    * sends updated list of terms in defDetailType - applicable when this form is invoked from editRecord
    */
    function _updateDetailTypeOnServer(_allTerms, _disTerms)
    {

        var str = null;

        //creates object to be sent to server
        if(_dtyID !== null){
            var k,
                val;

			var oDetailType = {detailtype:{
				colNames:{common:['dty_JsonTermIDTree', 'dty_TermIDTreeNonSelectableIDs']},
				defs: {}
			}};

			oDetailType.detailtype.defs[_dtyID] = {};
			oDetailType.detailtype.defs[_dtyID].common = [_allTerms, _disTerms];

            // 3. sends data to server
            var baseurl = window.hWin.HAPI4.baseURL + "admin/structure/saveStructure.php";
            var callback = null; //_updateResult;
            var request = {method:'saveDT', db:window.hWin.HAPI4.database, data:oDetailType};
            
            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, callback);
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
	function _selectAllChildren(termid, state) { //
			var parentNode = _findNodeById(termid, false); // _termTree.getNodeByIndex(parent);

			if(state<0){
				state = parentNode.data.isall?0:1;
				parentNode.data.isall = !parentNode.data.isall;
				if(parentNode.data.isall){
					parentNode.label = parentNode.label.replace("&nbsp;&nbsp;all</a>", "&nbsp;&nbsp;none</a>");
				}else{
					parentNode.label = parentNode.label.replace("&nbsp;&nbsp;none</a>", "&nbsp;&nbsp;all</a>");
				}
				_termTree.render();
			}

			if(parentNode.children.length > 0) {
				var index = 0;
				while(index < parentNode.children.length) { // While it has children, select them and look if they have children too
					var child = parentNode.children[index];

					if(child.highlightState != state){
							child.toggleHighlight();
					}

					if(child.children.length > 0) {
						_selectAllChildren(child.data.id, state); //index);
					}
					index++;
				}
			}
	}
	/**
	* Finds node by term id
	*/
	function _findNodeById(termid, needExpand) {

		//internal
		function __doSearchById(node){
			return (node.data.id==termid);
		}

		var nodes = _termTree.getNodesBy(__doSearchById);

		//select and expand the node
		if(nodes){
			var node = nodes[0];
			if(needExpand){
				node.focus();
				node.toggle();
			}

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
	* Creates an array (in var termArray) with all selected terms
	*/
	function _createTermArray(parent, parentsArray) { //

		var index = 0,
			nextParent;
		// If parent is root - button to do this has been commented out 13 apr 2016 b/c it confuses the form and is not very useful
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
					term.isall = false;

					cnt_children = Object.keys(parentNode[term_id]).length;
					term.label = '<div id="'+term_id+'">';

					if(cnt_children>0){
						term.label = term.label +
					'<a href="javascript:void(0)" onClick="selectTerms.selectAllChildren(\''+
term_id+'\', -1)">&nbsp;&nbsp;all</a>';//+termsByDomainLookup[term_id][0]+'</div>';
						term.href = "{javascript:void(0)}"; // To make 'select all' clickable, but not leave the page when hitting enter
					}else if(_isFilterMode){
						term.label = term.label + '&nbsp;&nbsp;';
					}

					if(_isFilterMode){

						if(_inExistingTree(term_id)) { //selected
							if(_isDisabledOriginally(term_id)){
								term.label = term.label + '<b>'+termsByDomainLookup[term_id][0]+'</b></div>';
							}else{
								term.label = term.label + termsByDomainLookup[term_id][0];
							}
							term.label = term.label + '</div>';

							term.label = ((cnt_children>0)?'<b>':'') + term.label + ((cnt_children>0)?'</b>':'') + '</div>';

							childNode = new YAHOO.widget.TextNode(term, parentEntry, false); // Create the node
							childNode.highlightState = 1;
							parentEntry.expand();
						}else{
							childNode = parentEntry;
						}

					}else{
                         if(!termsByDomainLookup[term_id]){
                            //error alert(term_id);
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
			term.isall = false;
			term.label = '<div id="'+parentElement+'">';

			if(cnt_children>0){
				term.label = term.label + '<a href="javascript:void(0)" onClick="selectTerms.selectAllChildren(\''+
term.id+'\', -1)">&nbsp;&nbsp;all</a>&nbsp;'; //+termsByDomainLookup[parentElement][0]+'</div>';
				term.href = "{javascript:void(0)}"; // To make 'select all' clickable, but not leave the page when hitting enter
			}

			if(_isFilterMode){
				if(_inExistingTree(parentElement)) {
					if(_isDisabledOriginally(parentElement)){
						term.label = term.label + '<b>'+ termsByDomainLookup[parentElement][0]+'</b></div>';
					}else{
						term.label = term.label + termsByDomainLookup[parentElement][0]+'</div>';
					}
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
	* Build a tree with all selected terms
	* @param termNode - root node of "all terms" tree
	* @param parentNode - root node of "selected terms" tree
	*/
	function _buildSelectedTermsTree(termNode, parentNode) {
		var index = 0;
		var childNode;
		while(index < termNode.children.length) { // While term in _termTree has children
			if(termNode.children[index].highlightState === 1) { // If the term is selected, add it to the 'selected term tree'

				var _node = termNode.children[index];
				var term_id = _node.data.id;
				var termName = termsByDomainLookup[term_id][0];

				childNode = new YAHOO.widget.TextNode('<div id="'+term_id+'"><a href="javascript:void(0)"></a>&nbsp;' + termName + '</div>', parentNode, true);
				childNode.id = term_id;
				childNode.href = "{javascript:void(0)}";
				childNode.isVocabulary = (_node.parent && _node.parent._type=="RootNode");

				if ((!_isDisabled(term_id)) && (!childNode.isVocabulary)) {
					childNode.toggleHighlight();
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
		}
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
		if(_isFilterMode){
				var index = 0;
				while(index < disabledTermsListOriginal.length) {
					if(disabledTermsListOriginal[index] === term_id) {
						return true;
					}
					index++;
				}
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
		/*var sel = Dom.get('previewList');
		while (sel.length>0){
			sel.remove(0);
		}*/

		var prev = $("#previewListDiv");
        prev.empty();
		/*i, len = prev.children.length;
		for (i = 0; i < len; i++) {
			prev.removeChild(prev.childNodes[0]);
		}*/


		var termIDTree = _createTermArray(_selectedTermsTree.getRoot());

		//add otions to select
		if (!termIDTree || termIDTree === "") { return null; }

		try { // In case no terms have been disabled yet
			_setDisabledTerms();
		}catch(e) { }

        $input = window.hWin.HEURIST4.ui.createTermSelectExt2(null,
                {datatype:_datatype, termIDTree:termIDTree, headerTermIDsList:(disabledTermsList || ""),
                    defaultTermID:null, topOptions:false, supressTermCode:true, useHtmlSelect:false});
        
        
        $input.addClass('previewList').appendTo($(prev));

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
						var _node = arguments[0].node;
						var term_id = _node.id;
						//prevent click on checkbox
						if(!_isDisabledOriginally(term_id) &&  !_node.isVocabulary) {
							this.onEventToggleHighlight.apply(this,arguments);
							_createPreview();
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
		window.hWin.HEURIST4.terms.selectedDisabled = disabledTermsList;*/

		// Fill the tree with all terms
		_buildTermTree(_termTree.getRoot());
		//redraw all terms tree
		_termTree.render();
		//create selected terms tree
		_buildSelectedTermsTree(_termTree.getRoot(), _selectedTermsTree.getRoot()); // Rebuild the 'selected terms tree'
		//redaw selected terms tree
		_selectedTermsTree.render();
		_createPreview();

		if(existingTree){
			setTimeout(function(){
				for (var termid in existingTree){
					_findNodeById(termid, true);
					break;
				}
			}, 500);
		}
	}

/*
END TREE REALTED ROUTINES ---------------------------------------
*/

	/**
	*  open editTerm in popup - refresh tree on close
	*/
	function _addNewTerm(){

		existingTree = _createTermArray(_selectedTermsTree.getRoot());
		try { // In case no terms have been disabled yet
			_setDisabledTerms();
		}catch(e) { }


	var sURL = window.hWin.HAPI4.baseURL + "admin/structure/terms/editTerms.php?popup=1&db="
                    +window.hWin.HAPI4.database+"&treetype="+_datatype;
    window.hWin.HEURIST4.msg.showDialog(sURL, {
		"close-on-blur": false,
		"no-resize": false,
        title: (_datatype=='enum')?'Manage Terms':'Manage Relationship types',
        height: (_datatype=='enum')?780:820,
        width: 950,
		afterclose: function() {

                var  _type = 'enum';
                    
				if(_datatype === "relation" || _datatype === "relationtype" || _datatype === "relmarker")
				{
                        _type = 'relation';
				}
                
                treesByDomain = window.hWin.HEURIST4.terms.treesByDomain[_type];
                termsByDomainLookup = window.hWin.HEURIST4.terms.termsByDomainLookup[_type];
				_treesInit();
		}
		});
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
				selectAllChildren : function (termid, state) {
					_selectAllChildren(termid, state);
				},
				setFilterMode : function (val) {
					_isFilterMode = val;
				},
				addNewTerm : function (val) {
					_addNewTerm();
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
