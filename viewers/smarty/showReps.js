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
		_varsTree; //treeview object


	/**
	*  show the list of available reports
	*  #todo - filter based on record types in result set
	*/
	function _updateTemplatesList(context) {


		var sel = document.getElementById('selTemplates'),
			option;

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


			var squery = location.searchl; //_currenQuery;
			_reload(squery, context[0].filename);

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

			//fille selection box with the list of templates
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
			_onSelectRectype();

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
					}
				}

				top.HEURIST.util.getJsonData(baseurl, __onOperEnd, squery);
			}
		}

		if(mode===0 || mode===3){ //for close or delete
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

				squery = squery + '&debug='+isdebug+'&template_body='+template_body;

				top.HEURIST.util.sendRequest(baseurl, function(xhr) {
					var obj = xhr.responseText;
					_updateReps(obj);
				}, squery);

		}else{
			alert('Nothing to execute');
		}
	}


	var layout, _isviewer, _iseditor;

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

		//var el = Dom.get('layout');
		layout = null;
		layout = new YAHOO.widget.Layout('layout', {
							units: units
							});
		layout.render();
	}

	//
	//fill the list of variable of specific record type
	//
	function _onSelectRectype_old(){

		var container = Dom.get("vars_list"),
			sel = Dom.get('selRectype'),
			recTypeID = sel.options[sel.selectedIndex].value;

		function __addoption(name, label)
		{
			var cb, cb1;

			cb1 = document.createElement("input");
			cb1.type = "checkbox";
			cb1.value = name;
			cb1.text = label;
			container.appendChild(cb1);

			cb = document.createElement("label");
			cb.innerHTML = "&nbsp;"+name;
			container.appendChild(cb);

			cb = document.createElement("br");
			container.appendChild(cb);

			return cb1;
		}

		//clear previous list
		while (container.childNodes.length>0){
			container.removeChild(container.firstChild);
		}

		var cbb = __addoption("select all");
		cbb.onchange = _selectAllVars;

		//create new list
		var i, j;
		for (i in _variables){
			if(i!==undefined && _variables[i].id===recTypeID){
				var varnames = _variables[i].vars;
				for (j in varnames){
					if(j!==undefined){
						__addoption(j, varnames[j]); // varnames[j]);
					}
				}//for
				break;
			}
		}//for
	}

	//
	// fill the treeview of variables of specific record type
	//
	function _onSelectRectype(){

		var container = Dom.get("vars_list"),
			sel = Dom.get('selRectype'),
			recTypeID,
			varnames; //contains vars - flat array and tree - tree array

		if(sel.selectedIndex<0 || sel.selectedIndex>=sel.options.length){
			return;
		}
		recTypeID = sel.options[sel.selectedIndex].value;


		//find list of variables for current record type
		var i, j;
		for (i in _variables){
			if(i!==undefined && _variables[i].id===recTypeID){
				varnames = _variables[i];
				break;
			}
		}//for


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
			_fillTreeView(_varsTree, varnames);
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

		var termid,
			tv_parent = tv.getRoot(),
			first_node,
			varid;

		//clear treeview
		tv.removeChildren(tv_parent);

		//first level terms
		for (varid in varnames.tree)
		{
		if(!Hul.isnull(varid)){

			var nodeIndex = tv.getNodeCount()+1;

			//var variable = varnames.vars[varid];

			var term = {};//new Object();
			term.id = varid;
			term.parent_id = "r";
			term.this_id = varid;
			term.label = '<div style="padding-left:10px;">';//<a href="javascript:void(0)"></a>&nbsp;&nbsp;'+varid+'</div>';

			term.href = "{javascript:void(0)}";
			if( Object.keys(varnames.tree[varid]).length > 0){
					term.label = term.label +
					'<a href="javascript:void(0)" onClick="showReps.markAllChildren(\''+
											varid+'\')">All</a>&nbsp;&nbsp;';
					term.href = "{javascript:void(0)}"; // To make 'select all' clickable, but not leave the page when hitting enter
			}
			term.label = term.label + varid+'</div>';


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

					var fullid = parentNode[child];

					var is_record = ((typeof(fullid) == "object") &&
									Object.keys(fullid).length > 0);

					var _varnames = varnames.vars;
					var label = is_record?child:_varnames[fullid];

					if(!Hul.isnull(label)){

					term = {};//new Object();
					term.id = fullid;
					term.parent_id = parent_id;
					term.this_id = child;
					term.label = '<div style="padding-left:10px;">'; //???arVars[0];

					if( is_record ){
							term.label = term.label +
							'<a href="javascript:void(0)" onClick="showReps.markAllChildren(\''+
											child+'\')">All</a>&nbsp;&nbsp';
							term.href = "{javascript:void(0)}";
					}

					term.label = term.label + label + '</div>';

					childNode = new YAHOO.widget.TextNode(term, parentEntry, false); // Create the node
					if( is_record ){
						__createChildren(fullid, child, childNode); // createChildren() again for every child found
					}


					}
				}
				}//for
			}

			var topLayerParent = new YAHOO.widget.TextNode(term, tv_parent, false); // Create the node
			if(!first_node) { first_node = topLayerParent;}

			var _parentNode = varnames.tree[varid];
			__createChildren(_parentNode, varid, topLayerParent); // Look for children of the node
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
	function _selectAllVars(event){
		var	i,
			container = Dom.get("vars_list"),
			childs = container.childNodes,
			ischecked = false;

			//find record type in _variables
		for (i in childs){
		if(i!==undefined){
			if(Number(i)===0){
				ischecked = childs[0].checked;
			}else if(Number(i)===1){
				childs[1].innerHTML = (ischecked?"uns":"s")+"elect all";
			}else if(childs[i].type === "checkbox")
			{
				childs[i].checked = ischecked;
			}
		}
		}
	}

	//
	// inserts template section for selected record type
	//
	function _insertRectypeSection(){
		var	i,
			textedit = Dom.get("edTemplateBody"),
			sel = Dom.get('selRectype'),
			recTypeID = sel.options[sel.selectedIndex].value;

			//find record type in _variables
		for (i in _variables){
			if(i!==undefined && _variables[i].id===recTypeID){

				if(_isEditorVisible()){
					var ed = tinyMCE.get('edTemplateBody');
					ed.selection.setContent(_variables[i].text);
				}else{
					var scrollTop = textedit.scrollTop;
					insertAtCursor(textedit, _variables[i].text);
					textedit = ApplyLineBreaks(textedit, textedit.value);
					textedit.scrollTop = scrollTop;
				}

				break;
			}
		}
	}

	//
	// inserts selected variables
	//
	function _insertSelectedVars_OLD(){
		var	i,
			textedit = Dom.get("edTemplateBody"),
			container = Dom.get("vars_list"),
			childs = container.childNodes,
			_text = "";

			//find record type in _variables
		for (i in childs){
			if(i!==undefined && Number(i)>0 && childs[i].type === "checkbox" && childs[i].checked)
			{
				_text = _text + "{$"+childs[i].value+"}";
			}
		}

		var scrollTop = textedit.scrollTop;
		insertAtCursor(textedit, _text);
		textedit.scrollTop = scrollTop;
	}

	//
	// inserts selected variables
	//
	function _insertSelectedVars(){

		var textedit = Dom.get("edTemplateBody"),
			insertMode = Dom.get("selInsertMode").value,
			_text = "";

		//function for each node in _varsTree - create the list
		function __loopNodes(node){
				if(node.children.length===0 && node.highlightState===1){

					if(insertMode<2){
						if(insertMode==1){
							_text = _text + node.data.label+":";
						}
						_text = _text + "{$"+node.data.id+"}";
					}else{
						_text = _text + '{out2 lbl="'+node.data.label+'" var=$'+node.data.id+ '}';
					}
				}
				return false;
		}
		//loop all nodes of tree
		_varsTree.getNodesBy(__loopNodes);

		if(_text!=="")	{
			if(_isEditorVisible()){
				var ed = tinyMCE.get('edTemplateBody');
				ed.selection.setContent(_text);
			}else{
				var scrollTop = textedit.scrollTop;
				insertAtCursor(textedit, _text);
				textedit.scrollTop = scrollTop;
			}
		}else{
			alert('No one variable is selected');
		}
	}

	//
	function _insertSelectedVarsAsLoop(){

		var textedit = Dom.get("edTemplateBody"),
			_text = "";

		//function for each node in _varsTree - create the list
		// returns false if there are not detail fields
		function __loopNodes(children, indent){
			var len = children.length;
			var res = false;

			if( len > 0) {
				var index = 0;
				while(index < len) {

					var node = children[index];

					if(node.highlightState===1){ //marked
						if(node.children.length>0){

							var arr_name = (node.data.id==="r")?"results":(node.data.parent_id+"."+node.data.this_id+"s");

							_text = _text + "<div style='padding-left:"+(indent*20)+
										"px;background-color:#ffcccc;'>{foreach $"+
										arr_name+" as $"+node.data.this_id+"}</div>";

							if(!__loopNodes(node.children, indent+1)){
								_text = _text + "<div style='padding-left:"+((indent+1)*20)+"px;'>{* INSERT YOUR CODE HERE *}</div>";
							}

							_text = _text + "<div style='padding-left:"+
										(indent*20)+"px;background-color:#ffcccc;'>{/foreach}</div>";

						}else if(_text!==""){
							_text = _text + "<div style='padding-left:"+(indent*20)+"px;'>{$"+node.data.id+"}</div>";
							res = true;
						}
					}
					index++;
				}
			}
			return res;
		}

		//loop all nodes of tree
		__loopNodes(_varsTree.getRoot().children, 0);

		if(_text!=="")	{

			if(_isEditorVisible()){
					var ed = tinyMCE.get('edTemplateBody');
					ed.selection.setContent(_text);
			}else{
				var scrollTop = textedit.scrollTop;
				insertAtCursor(textedit, _text);
				textedit.scrollTop = scrollTop;
			}
		}else{
			alert('No one parent entity is selected');
		}
	}


	//
	// utility function
	//
	function insertAtCursor(myField, myValue) {
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

			processTemplate:  function (squery, template_file){
				_reload(squery, template_file);
			},

			generateTemplate:  function (name, squery){
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

			//fill the list with variables of specific record type
			onSelectRectype:function(){
				_onSelectRectype();
			},

			//insert section type at the cursor position
			insertRectypeSection:function(){
				_insertRectypeSection();
			},

			//inserts selected variables inside the loop
			insertSelectedVarsAsLoop:function(){
				_insertSelectedVarsAsLoop();
			},

			//inserts selected variables
			insertSelectedVars:function(){
				_insertSelectedVars();
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