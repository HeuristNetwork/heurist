/**
* showReps.js
*
* @version 2011.0812
* @author: Artem Osmakov
*
* @copyright (C) 2005-2011 University of Sydney Digital Innovation Unit.
* @link: http://HeuristScholar.org
* @license http://www.gnu.org/licenses/gpl-3.0.txt
* @package Heurist academic knowledge management system
* @todo
**/

//aliases
var Dom = YAHOO.util.Dom,
	Hul = top.HEURIST.util,
	Event = YAHOO.util.Event;

/**
* UserEditor - class for pop-up edit group
*
* public methods
*
* save - sends data to server and closes the pop-up window in case of success
* cancel - checks if changes were made, shows warning and closes the window
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2011.0509
*/

function ShowReps() {

	var _className = "ShowReps",
		_originalFileName,
		_currenQuery,
		_variables, //object with all variables
		_varsTree, //treeview object
		_needListRefresh = false; //if true - reload list of templates after editor exit


	/**
	*  show the list of available reports
	*  #todo - filter based on record types in result set
	*/
	function _updateTemplatesList(context) {


		var sel = document.getElementById('selTemplates'),
			option,
			keepSelIndex = sel.selectedIndex;

		//celear selection list
		while (sel.length>0){
				sel.remove(0);
		}

		if(context && context.length>0){

			for (i in context){
			if(i!==undefined){

				option = document.createElement("option");
				option.text = context[i].name;
				option.value = context[i].filename;
				try {
					// for IE earlier than version 8
					sel.add(option, sel.options[null]);
				}catch (ex2){
					sel.add(option,null);
				}
			}
			} // for


			sel.selectedIndex = (keepSelIndex<0)?0:keepSelIndex;

			var squery = location.search; //_currenQuery;
			if(sel.selectedIndex>=0){
				_reload(squery, context[sel.selectedIndex].filename);
			}
		}
		_setLayout(true, false);
	}


	/**
	* Inserts template output into container
	*/
	function _updateReps(context) {

		//converts Heurist.tmap structure to timemap structure
		//HEURIST.tmap = context;
		var div_rep = document.getElementById("rep_container");//Dom.get("rep_container");
		div_rep.innerHTML = context;
    }


	function _onSelectionChange(eventType, argList) {
		if (parent.document.getElementById("s").className == "yui-hidden") {
			return false;
		}else {
				if (eventType == "heurist-selectionchange"){
					top.HEURIST.search.smartySelected();
				}
		}
	}


	/**
	* Initialization
	*
	* Reads GET parameters and requests for map data from server
	*/
	function _init() {
		_setLayout(true, true); //aftert load show viewer only

		_reload_templates();

		if (top.HEURIST) {
			top.HEURIST.registerEvent(that,"heurist-selectionchange", _onSelectionChange);
		}
	}

	/**
	* loads list of templates
	*/
	function _reload_templates(){
				var baseurl = top.HEURIST.basePath + "viewers/smarty/templateOperations.php";
				var callback = _updateTemplatesList;
				top.HEURIST.util.getJsonData(baseurl, callback, 'mode=list');
	}

	/**
	* Executes the template with the given query
	*/
	function _reload(squery, template_file) {

				var baseurl = top.HEURIST.basePath + "viewers/smarty/showReps.php";
//window.open(baseurl+squery, '_blank');

				if(Hul.isnull(squery)){
					squery = _currenQuery;
				}else{
					_currenQuery = squery;
				}

				if(Hul.isnull(template_file)){
					var sel = document.getElementById('selTemplates');
					if(Hul.isnull(sel) || Hul.isnull(sel.options) || sel.options.length===0) { return; }
					template_file = sel.options[sel.selectedIndex].value; // by default first entry
				}
				squery = squery + '&template='+template_file;

				top.HEURIST.util.sendRequest(baseurl, function(xhr) {
					var obj = xhr.responseText;
					_updateReps(obj);
				}, squery);

				//top.HEURIST.util.getJsonData(baseurl, callback, squery);
	}

	//
	function _isEditorVisible(){
		//
		if(!isEditorVisible && !isInited){
			//MCE works only once????  setupMCE(); //auto setup mce
			return false;
		}else{
			return isEditorVisible;
		}
	}

	/**
	* Creates new template from the given query
	*
	* isLoadGenerated - new template
	*/
	function _generateTemplate(name, squery, isLoadGenerated) {

		function __onGenerateTemplate(context){

			if(context===false || Hul.isnull(context['text'])){
				alert('No template generated');
				return;
			}

			if(isLoadGenerated){

				if(_isEditorVisible()){
					var ed = tinyMCE.get('edTemplateBody');
					ed.setContent(context['text']);
				}else{
					Dom.get("edTemplateName").value = name;
					ApplyLineBreaks(Dom.get("edTemplateBody"), context['text']);
				}
			}

			_variables = context['vars'];

			/* fille selection box with the list of rectypes
			var sel = Dom.get("selRectype");
			//celear selection list
			while (sel.length>0){
				sel.remove(0);
			}

			var i;
			for (i in _variables){
			if(i!==undefined){

				option = document.createElement("option");
				option.text = _variables[i].name; //name of rectype
				option.value = _variables[i].id; //id of rectype
				try {
					// for IE earlier than version 8
					sel.add(option, sel.options[null]);
				}catch (ex2){
					sel.add(option,null);
				}
			}
			} // for

			sel.selectedIndex = 0;
			*/
			_fillTreeView();

			_setLayout(true, true);

			_doExecute(); //execute at once
		}

			if(Hul.isnull(squery)){
				squery = _currenQuery;
			}
			if(Hul.isnull(squery)){
				alert('You have to select some records in search result');
			}else{
				var baseurl = top.HEURIST.basePath + "viewers/smarty/templateGenerate.php";
				top.HEURIST.util.getJsonData(baseurl, __onGenerateTemplate, squery);
			}
	}

	/**
	* Creates new template from the given query
	*/
	function _showEditor(template_file) {

		function _onGetTemplate(context){
			Dom.get("edTemplateName").value = template_file;
			Dom.get("edTemplateBody").value = context;
			_setLayout(true, true);
		}

			var baseurl = top.HEURIST.basePath + "viewers/smarty/templateOperations.php";

			_originalFileName = template_file;

			var squery = 'mode=get&template='+template_file;

			top.HEURIST.util.sendRequest(baseurl, function(xhr) {
					var obj = top.HEURIST.util.evalJson(xhr.responseText);
					if (obj  &&  obj.error) {
						alert(obj.error);
					}else{
						_onGetTemplate(xhr.responseText);
					}
			}, squery);

			_generateTemplate(template_file, null, false);
	}

	/**
	* Close editor
	* @param mode 0 - just close, 1 - save as (not close),  2 - save (not close), 3 - delete and close
	*/
	function _operationEditor(mode) {

		if(mode>0){

			var baseurl = top.HEURIST.basePath + "viewers/smarty/templateOperations.php",
				squery = 'mode=',
				template_file = null;

			if(mode<3)
			{ //save
				template_file = jQuery.trim(Dom.get("edTemplateName").value);

				if(mode==1){ //save as - get new name
					template_file = jQuery.trim(prompt("Please enter new template name", template_file));
					if (Hul.isempty(template_file)){
						return;
					}
					Dom.get("edTemplateName").value = template_file;
				}

				var template_body = Dom.get("edTemplateBody").value;
				if(template_body && template_body.length>10){
					squery = squery + 'save&template='+template_file+'&template_body='+template_body;

				}else{
					alert('The template body is suspiciously short. No operation performed');
					squery = null;
				}
			}
			else if (mode===3 && _originalFileName!=="") //delete template
			{ //delete
				var r=confirm("Are you sure you wish to delete template '"+_originalFileName+"'?");
				if (r==true){
					squery = squery + 'delete&template='+_originalFileName
					_originalFileName = null;
				}else{
					return;
				}
			}

			if(squery){

				var modeRef = mode

				function __onOperEnd(context){
					if(context && context.error){
						//do nothing alert(context.error);
					}else{
						var mode = context.ok;
						if(mode==="delete"){
							//todo!!!! - remove template from the list and clear editor
						}else if(template_file!=null){
							_originalFileName = template_file;//_onGetTemplate(obj);
							alert("Template '"+template_file+"' has been saved");
							//add new entry into list
						}

						if(modeRef===1 || modeRef===3){
							_needListRefresh = true;
						}
						if(modeRef===3){ //for close or delete
							_setLayout(true, false);
						}
					}
				}

				top.HEURIST.util.getJsonData(baseurl, __onOperEnd, squery);
			}
		}

		if(mode===0){ //for close or delete
			_setLayout(true, false);
		}
	}

	/**
	* Executes the template from editor
	*
	* isdebug = 1-yes
	*/
	function _doExecute(squery) {

		var isdebug = document.getElementById('cbDebug').checked?1:0;

		var template_body;
		if(_isEditorVisible()){
			var ed = tinyMCE.get('edTemplateBody');
			template_body = ed.getContent();
		}else{
			template_body = Dom.get("edTemplateBody").value;
		}

		if(template_body && template_body.length>10){

				if(Hul.isnull(squery)){
					squery = _currenQuery;
				}

				var baseurl = top.HEURIST.basePath + "viewers/smarty/showReps.php";

				squery = squery + '&debug='+isdebug+'&template_body='+encodeURIComponent(template_body);

				top.HEURIST.util.sendRequest(baseurl, function(xhr) {
					var obj = xhr.responseText;
					_updateReps(obj);
				}, squery);

		}else{
			alert('Nothing to execute');
		}
	}


	var layout, _isviewer, _iseditor;

	/**
	* change visibility
	*/
	function _setLayout(isviewer, iseditor){

		if(_isviewer===isviewer && _iseditor===iseditor ||
			(!isviewer && !iseditor) )
		{
			return;
		}
		_isviewer=isviewer;
		_iseditor=iseditor;

		var units;
		if(isviewer && iseditor){
				units = [
				{ position: 'top', header: 'Editor', height: 150,
					resize: true, body: 'editorcontainer', gutter:'2px', collapse: true},
				{ position: 'center', body: 'viewercontainer'}
				];
		}else if(isviewer){
				units = [
					{ position: 'center', body: 'viewercontainer'}
						];
		}else if(iseditor){
				units = [
					{ position: 'center', body: 'editorcontainer'}
						];
		}

		//
		Dom.get("toolbar").style.display = (iseditor) ?"none" :"block";
		Dom.get("layout").style.top = (iseditor) ?"0" :"25";

		//var el = Dom.get('layout');
		layout = null;
		layout = new YAHOO.widget.Layout('layout', {
							units: units
							});
		layout.render();

		//reload templates list
		if(_needListRefresh && !iseditor){
			_needListRefresh = false;
			_reload_templates();
		}
	}

	//
	//
	//
	function _selectAllChildren(parentNode) {
			var len = parentNode.children?parentNode.children.length:0;
			if( len > 0) {
				var index = 0;
				while(index < len) { // While it has children, select them and look if they have children too
					var child = parentNode.children[index];
					child.highlight();
					/*child.highlightState = parentNode.highlightState;
					if(parentNode.highlightState===1){
						child.highlight(); //mark the checkbox
					}else{
						child.unhighlight()
					}*/
					if(child.children.length > 0) {
						_selectAllChildren(child.data);
					}
					index++;
				}
			}
	}


	/**
	* Finds node by term id
	*/
	function _findNodeById(varid) {

		//internal
		function __doSearchById(node){
			return (node.data.id==varid);
		}

		var nodes = _varsTree.getNodesBy(__doSearchById);

		if(nodes){
			var node = nodes[0];
			return node;
		}else{
			return null
		}
	}

	//
	//
	//
	function _markAllChildren(varid){
		var node = _findNodeById(varid);
		if(node){
			_selectAllChildren(node);
		}
	}

	//
	//
	//
	function _clearAllSeelectionInTree(){
			//function for each node in _varsTree - removes highlight
			function __resetSelection(node){
				node.unhighlight();
				return false;
			}
			//loop all nodes of tree
			_varsTree.getNodesBy(__resetSelection);
			_varsTree.render();
	}

	/**
	*	Fills the given treeview with the list of variables
	* 	varnames  - contains vars - flat array and tree - tree array
	*/
	function _fillTreeView (tv, varnames) {

		//create treeview
		if(Hul.isnull(_varsTree)){
					_varsTree = new YAHOO.widget.TreeView("varsTree");
					_varsTree.subscribe("clickEvent",
						function() { // On click, select the term, and add it to the selected terms tree
							this.onEventToggleHighlight.apply(this,arguments);
							//var parentNode = arguments[0].node;
							//_selectAllChildren(parentNode);
					});
		}

		//fill treeview with content
		var i, termid, term,
			tv = _varsTree,
			tv_parent = tv.getRoot(),
			first_node,
			varid,
			varnames;

		//clear treeview
		tv.removeChildren(tv_parent);

		for (i in _variables){
		if(i!==undefined)
		{
			varnames = _variables[i];  // && _variables[i].id===recTypeID

			term = {};//new Object();
			term.id = _variables[i].id;
			term.this_id = 'r';
			term.parent_id = null;
			term.label = '<div style="padding-left:10px;">'+_variables[i].name;
			if(i>0){
				term.label =  term.label + '&nbsp;(<a href="javascript:void(0)" title="Insert IF operator for this record type. It will allow to avoid an error if this type is missed in the result set" onClick="showReps.insertRectypeIf(\''+
											_variables[i].name+'\')">IF</a>)';
			}else{
				term.label =  term.label + '&nbsp;(<a href="javascript:void(0)" title="Insert root FOREACH operator" onClick="showReps.insertRootForEach()">FOREACH</a>)';
			}
			term.label =  term.label + '</div>';

			term.href = "javascript:void(0)";

			var topLayerParent = new YAHOO.widget.TextNode(term, tv_parent, false); // Create the node
			if(!first_node) { first_node = topLayerParent;}

			var prefix_id = term.id; //rectype id

		//first level terms
		for (varid in varnames.tree)
		{
		if(!Hul.isnull(varid)){

			term = {};//new Object();
			term.id = prefix_id+"."+varid; //unique id including rectype ID
			term.parent_id = "r";  //uniques parent prefix
			term.this_id = varid;
			term.label = '<div style="padding-left:10px;">';//<a href="javascript:void(0)"></a>&nbsp;&nbsp;'+varid+'</div>';

			term.href = "{javascript:void(0)}";
			if( Object.keys(varnames.tree[varid]).length > 0){type:
					term.label = term.label +
					'<a href="javascript:void(0)" onClick="showReps.markAllChildren(\''+
											term.id+'\')">All</a>&nbsp;&nbsp;';
					term.href = "javascript:void(0)"; // To make 'select all' clickable, but not leave the page when hitting enter

					if(varid!=="r"){
						term.label = term.label +
'(<a href="javascript:void(0)" title="Insert FOREACH operator for this resource" onClick="showReps.insertSelectedVarsAsLoop(\''+term.id+'\')">FOR</a>'+
'&nbsp;<a href="javascript:void(0)" title="Insert marked variables in loop (without parent prefix)" onClick="showReps.insertSelectedVars(\''+term.id+'\', true)">In</a>'+
'&nbsp;<a href="javascript:void(0)" title="Insert marked variables with parent prefix. To use outside the loop" onClick="showReps.insertSelectedVars(\''+term.id+'\', false)">Out</a>)&nbsp;';
					}else{
						term.label = term.label +
'(<a href="javascript:void(0)" title="Insert marked variables" onClick="showReps.insertSelectedVars(\''+term.id+'\', false)">Ins</a>)&nbsp;';
					}

			}
			term.label = term.label + varid+'</div>'; //to debug replace to term.id

			//'<div id="'+parentElement+'"><a href="javascript:void(0)" onClick="selectAllChildren('+nodeIndex+')">All </a> '+termsByDomainLookup[parentElement]+'</div>';
			//term.href = "javascript:void(0)";
		//internal function
			function __createChildren(parentNode, parent_id, _prefix, parentEntry) { // Recursively get all children
				var term,
					childNode,
					child;

				for(child in parentNode)
				{
				if(!Hul.isnull(child)){

					var fullid = parentNode[child];

					var is_record = ((typeof(fullid) == "object") &&
									Object.keys(fullid).length > 0);

					var _varnames = varnames.vars;
					var label = is_record?child:_varnames[fullid];

					if(!Hul.isnull(label)){

					term = {};//new Object();
					term.id = _prefix+"."+child; //fullid;
					term.parent_id = parent_id;
					term.this_id = child;
					term.label = '<div style="padding-left:10px;">'; //???arVars[0];
					term.labelonly = label;

					if( is_record ){
							term.label = term.label +
							'<a href="javascript:void(0)" onClick="showReps.markAllChildren(\''+
											term.id+'\')">All</a>&nbsp;&nbsp';
							term.href = "{javascript:void(0)}";

							term.label = term.label +
'(<a href="javascript:void(0)" title="Insert FOREACH operator for this resource" onClick="showReps.insertSelectedVarsAsLoop(\''+term.id+'\')">FOR</a>'+
'&nbsp;<a href="javascript:void(0)" title="Insert marked variables in loop (without parent prefix)" onClick="showReps.insertSelectedVars(\''+term.id+'\', true)">In</a>'+
'&nbsp;<a href="javascript:void(0)" title="Insert marked variables with parent prefix. To use outside the loop" onClick="showReps.insertSelectedVars(\''+term.id+'\', false)">Out</a>)&nbsp;';
					}

					term.label = term.label + label + '</div>'; //to debug replace to term.id

					childNode = new YAHOO.widget.TextNode(term, parentEntry, false); // Create the node
					if( is_record ){
						__createChildren(fullid, child, term.id, childNode); // createChildren() again for every child found
					}


					}
				}
				}//for
			}

			var rectypeLayer = new YAHOO.widget.TextNode(term, topLayerParent, false); // Create the node

			var _parentNode = varnames.tree[varid];
			__createChildren(_parentNode, varid, term.id, rectypeLayer); // Look for children of the node
		}
		}//for
		}
		}//for

		//TODO tv.subscribe("labelClick", _onNodeClick);
		//tv.singleNodeHighlight = true;
		//TODO tv.subscribe("clickEvent", tv.onEventToggleHighlight);

		tv.render();
		//first_node.focus();
		//first_node.toggle();
	}

	//
	//
	//
	function _addVariable(nodedata, prefix){
		var res= "",
			insertMode = Dom.get("selInsertMode").value;

		if(insertMode<2){
			if(insertMode==1){
				res = nodedata.labelonly+":";
			}
			res = res + "{$"+prefix+nodedata.this_id+"}";
		}else{
			res = '{out2 lbl="'+nodedata.labelonly+'" var=$'+prefix+nodedata.this_id+ '}';
		}

		return (res+'<br/>\n');
	}

	//
	//
	//
	function _insertRootForEach(){
		var textedit = Dom.get("edTemplateBody");
		var _text = '{foreach $results as $r}\n{* INSERT YOUR CODE HERE *}\n{/foreach}\n';

		var scrollTop = textedit.scrollTop;
		insertAtCursor(textedit, _text);
		textedit.scrollTop = scrollTop;
		textedit.focus();
	}
	//
	//
	//
	function _insertRectypeIf(rectypeName){

		var textedit = Dom.get("edTemplateBody");
		var _text = '{if ($r.recTypeName=="'+rectypeName+'")}\n{* INSERT YOUR CODE HERE *}\n{/if}\n';

		var scrollTop = textedit.scrollTop;
		insertAtCursor(textedit, _text);
		textedit.scrollTop = scrollTop;
		textedit.focus();
	}

	//
	// inserts template section for selected record type (NOT USED)
	//
	function _insertRectypeSection(){
		var	i,
			textedit = Dom.get("edTemplateBody");


		//function for each node in _varsTree - create the list
		function __loopNodes2(node){
				if(node.data.parent_id === null && node.highlightState===1){ //marked rectype

					var recTypeID = node.data.id;

					//find record type in _variables
					for (i in _variables){
						if(i!==undefined && _variables[i].id===recTypeID){

							if(_isEditorVisible()){
								var ed = tinyMCE.get('edTemplateBody');
								ed.selection.setContent(_variables[i].text);
							}else{
								var scrollTop = textedit.scrollTop;
								insertAtCursor(textedit, _variables[i].text+'<br/>');
								textedit = ApplyLineBreaks(textedit, textedit.value);
								textedit.scrollTop = scrollTop;
								textedit.focus();
							}

							break;
						}
					}

					node.highlightState=0;
				}
				return false;
		}
		//loop all nodes of tree
		_varsTree.getNodesBy(__loopNodes2);


	}

	//
	// inserts selected variables
	//
	function _insertSelectedVars( varid, inloop ){

		var textedit = Dom.get("edTemplateBody"),
			_text = "",
			_varid = varid,
			_inloop = inloop;
			_prefix = "";

		//function for each node in _varsTree - create the list
		/*
		function __loopNodes(node){
				if(node.children.length===0 && node.highlightState===1){

					_text = _text + _addVariable(node.data, _prefix);
				}
				return false;
		}
		//loop all nodes of tree
		_varsTree.getNodesBy(__loopNodes);
		*/
		if(!_inloop){
			_prefix = "r.";
		}


		function __loopNodes2(children){
			var len = children.length;
			var res = false;

			if( len > 0) {
				var index = 0;
				while(index < len) {

					var node = children[index];

					if(node.data.parent_id === null){

						__loopNodes2(node.children);

					}else if(node.highlightState===1){ //marked
						node.highlightState = 0;
						if(node.children.length>0){

							if(node.data.parent_id!=="r"){
								_prefix = _prefix + node.data.this_id + ".";
							}

							__loopNodes2(node.children);

						}else {
							_text = _text + _addVariable(node.data, _prefix);
						}
					}
					index++;
				}
			}
		}

		if(varid==null){
			//loop all nodes of tree
			__loopNodes2(_varsTree.getRoot().children);
		}else{

			var _nodep = _findNodeById(varid);
			if(_nodep){
				if(!_inloop){
					/*if(_nodep.data.parent_id!=="r"){
						_prefix = _prefix + _nodep.data.this_id + ".";
					}else{
						_prefix = _nodep.data.this_id + ".";
					}*/
				}

				if(_inloop){
					_prefix = _nodep.data.this_id + ".";
				}else{
					var s = _nodep.data.id; //get all parents
					s = s.substring(s.indexOf('.')+1);
					if(s!=="r"){
						s = "r."+s;
					}
					_prefix = s + ".";
					/*if(_prefix!==_nodep.data.id){
						_prefix = s_prefix + _nodep.data.id + ".";
					}*/
				}

				__loopNodes2(_nodep.children);
			}
		}

		if(_text!=="")	{
			if(_isEditorVisible()){
				var ed = tinyMCE.get('edTemplateBody');
				ed.selection.setContent(_text);
			}else{
				var scrollTop = textedit.scrollTop;
				insertAtCursor(textedit, _text);
				textedit.scrollTop = scrollTop;
				textedit.focus();
			}
			_varsTree.render();
		}else{
			alert('You have to mark the desired variables in the tree');
		}
	}

	//
	function _insertSelectedVarsAsLoop( varid ){

		var textedit = Dom.get("edTemplateBody"),
			insertMode = Dom.get("selInsertMode").value,
			_text = "",
			_prefix = "";

		//function for each node in _varsTree - create the list
		// returns false if there are not detail fields
		function __loopNodes(children, indent){
			var len = children.length;
			var res = false;

			if( len > 0) {
				var index = 0;
				while(index < len) {

					var node = children[index];

					if(node.data.parent_id === null){

						__loopNodes(node.children, indent);

					}else if(node.highlightState===1){ //marked
						node.highlightState = 0;
						if(node.children.length>0){

							var arr_name = (node.data.this_id==="r")?"results":(node.data.parent_id+"."+node.data.this_id+"s");
							_text = _text + "{foreach $"+arr_name+" as $"+node.data.this_id+"}\n";
							_prefix = node.data.this_id + ".";

							if(!__loopNodes(node.children, indent+1)){
								_text = _text + "{* INSERT YOUR CODE HERE *}\n";
							}
							_text = _text + "{/foreach}\n";

						}else if(_text!==""){
							//_text = _text + "<div style='padding-left:"+(indent*20)+"px;'>{$"+node.data.id+"}</div>";
							_text = _text + _addVariable(node.data, _prefix);
							res = true;
						}
					}
					index++;
				}
			}
			return res;
		}

		if(varid==null){
			//loop all nodes of tree
			__loopNodes(_varsTree.getRoot().children, 0);
		}else{
			var _nodep = _findNodeById(varid);
			if(_nodep){
				var arr_name = (_nodep.data.this_id==="r")?"results":(_nodep.data.parent_id+"."+_nodep.data.this_id+"s");
				_text = _text + "{foreach $"+arr_name+" as $"+_nodep.data.this_id+"}\n";

				_prefix = _nodep.data.this_id + ".";

				if( !__loopNodes(_nodep.children, 0) ){
						_text = _text + "{* INSERT YOUR CODE HERE *}\n";
				}
				_text = _text + "{/foreach}\n";
			}
		}

		if(_text!=="")	{

			if(_isEditorVisible()){
					var ed = tinyMCE.get('edTemplateBody');
					ed.selection.setContent(_text);
			}else{
				var scrollTop = textedit.scrollTop;
				insertAtCursor(textedit, _text);
				textedit.scrollTop = scrollTop;
				textedit.focus();
			}

			_varsTree.render();

		}else{
			alert('To insert the loop for particular rectord type you have to select one of parent nodes');
		}
	}


	//
	// utility function - move to HEURIST.utils
	//
	function insertAtCursor(myField, myValue) {

			//var scrollTop = myField.scrollTop;

			//IE support
			if (document.selection) {
				myField.focus();
				sel = document.selection.createRange();
				sel.text = myValue;
			}
			//MOZILLA/NETSCAPE support
			else if (myField.selectionStart || myField.selectionStart == '0') {
				var startPos = myField.selectionStart;
				var endPos = myField.selectionEnd;
				myField.value = myField.value.substring(0, startPos)
				+ myValue
				+ myField.value.substring(endPos, myField.value.length);
			} else {
				myField.value += myValue;
			}

			//myField.scrollTop = scrollTop;
			//myField.focus();
	}
	//
	// apply line breaks
	//
	function ApplyLineBreaks(oTextarea, text) {

		if (oTextarea.wrap) {
			oTextarea.setAttribute("wrap", "off");
		}
		else {
			oTextarea.setAttribute("wrap", "off");
			var newArea = oTextarea.cloneNode(true);
			newArea.value = text; //oTextarea.value;
			oTextarea.parentNode.replaceChild(newArea, oTextarea);
			oTextarea = newArea;
		}

		var strRawValue = text;// oTextarea.value;
		oTextarea.value = "";
		var k = text.indexOf("\\n");
		while (k>=0){
			oTextarea.value += text.substr(0, k) + "\n"; // + text.substr(k+2);
			text = text.substr(k+2);
			k = text.indexOf("\\n");
		}
		oTextarea.value += text;
/*
	    var nEmptyWidth = oTextarea.scrollWidth;
	    var nLastWrappingIndex = -1;
	    for (var i = 0; i < strRawValue.length; i++) {
	        var curChar = strRawValue.charAt(i);
	        if (curChar == ' ' || curChar == '-' || curChar == '+')
	            nLastWrappingIndex = i;
	        oTextarea.value += curChar;
	        if (oTextarea.scrollWidth > nEmptyWidth) {
	            var buffer = "";
	            if (nLastWrappingIndex >= 0) {
	                for (var j = nLastWrappingIndex + 1; j < i; j++)
	                    buffer += strRawValue.charAt(j);
	                nLastWrappingIndex = -1;
	            }
	            buffer += curChar;
	            oTextarea.value = oTextarea.value.substr(0, oTextarea.value.length - buffer.length);
	            oTextarea.value += "\n" + buffer;
	        }
	    }
*/
		oTextarea.setAttribute("wrap", "");

		return oTextarea;
	}

	//public members
	var that = {

			getQuery: function(){
				return _currenQuery;
			},

			processTemplate:  function (squery, template_file){
				_reload(squery, template_file);
			},

			generateTemplate:  function (name, squery){
				_needListRefresh = true;
				_generateTemplate(name, squery, true);
			},

			showEditor:  function (template_file){
				_showEditor(template_file);
			},

			operationEditor:  function (action){
				_operationEditor(action);
			},

			doExecute: function(squery){
				_doExecute(squery);
			},

			//insert section type at the cursor position
			insertRectypeSection:function(){
				_insertRectypeSection();
			},

			//inserts selected variables inside the loop
			insertSelectedVarsAsLoop:function(varid){
				_insertSelectedVarsAsLoop(varid);
			},

			//inserts selected variables
			insertSelectedVars:function(varid, inloop){
				_insertSelectedVars(varid, inloop);
			},

			insertRectypeIf:function(rectypeName){
				_insertRectypeIf(rectypeName);
			},
			insertRootForEach:function(){
				_insertRootForEach();
			},
			//clear all marked checkbox in variable tree
			clearAllSeelectionInTree:function(){
				_clearAllSeelectionInTree();
			},

			// mark - unmark child nodes
			markAllChildren:function(varid){
				_markAllChildren(varid);
			},

			baseURL:  function (){
				return top.HEURIST.basePath;
			},

			getClass: function () {
				return _className;
			},

			isA: function (strClass) {
				return (strClass === _className);
		}

	};

	// init on load
	_init();
	return that;
}