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
* brief description of file
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

// SelectTermParent object
var selectTermParent;

//aliases
var Dom = YAHOO.util.Dom,
	Hul = top.HEURIST.util;

/**
* SelectTerms - class for pop-up window to select terms for editing detail type
*
* apply
* cancel
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2011.0427
*/
function SelectTermParent() {

	var _className = "SelectTermParent",
		_currentDomain,
		_childTerm,
		_currentNode,
		_termTree;

	/**
	* Initialization of input form
	*
	* Reads GET parameters, creates TabView and triggers init of first tab
	*/
	function _init() {

		// read parameters from GET request
		// if ID is defined take all data from detail type record
		// otherwise try to get these parameters from request
		//
		if(location.search.length > 1) {
				top.HEURIST.parameters = top.HEURIST.parseParams(location.search);
				_childTerm = top.HEURIST.parameters.child;
				_currentDomain = top.HEURIST.parameters.domain;

				if(_childTerm){
						Dom.get("childTermName").innerHTML = "<h2 class='dtyName'>"+
									top.HEURIST.terms.termsByDomainLookup[_currentDomain][_childTerm][top.HEURIST.terms.fieldNamesToIndex.trm_Label]+"</h2>";
				}else{
						Dom.get("childTermName").innerHTML = "";
				}

		}

		if(Hul.isnull(_currentDomain)) {
			Dom.get("childTermName").innerHTML = "ERROR: Domain is not defined";
			// TODO: Stop page from loading
			return;
		}

				if (!(_currentDomain === "enum" || _currentDomain === "relation")){
					Dom.get("dtyName").innerHTML = "ERROR: Domain '" + _currentDomain + "' is of invalid value";
					// TODO: Stop page from loading
					return;
				}

				//create trees
				if(Hul.isnull(_termTree)){
					_termTree = new YAHOO.widget.TreeView("termTree");
					//fill treeview with content
					_fillTreeView(_termTree);
				}
	}//end _init

	/**
	* Result function for public "apply" method
	*
	* Creates 2 result strings from selected and disabled terms, returns then to invoker window
	* and closes this window
	*/
	function _getNewParent(isRoot) {
		if(isRoot){
			window.close("root");
		}else if(_currentNode){
			var newparent_id = _currentNode.data.id;
			window.close(newparent_id);
		}else{
			alert("Please select a term in the tree");
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
		if(!Hul.isnull(termid) && termid!=_childTerm){

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
	}

	/**
	* Loads values to edit form (NOT USED)
	*/
	function _onNodeClick(node){
		_currentNode = node;
	}

	//
	//public members
	//
	var that = {

				/**
				*	Apply form
				*/
				apply : function (isRoot) {
						_getNewParent(isRoot);
				},

				/**
				* Cancel form
				*/
				cancel : function () {
					window.close();
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
