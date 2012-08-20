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
		//squery_all,squery_sel,squery_main,
		_variables, //object with all variables
		_varsTree, //treeview object
		_needListRefresh = false, //if true - reload list of templates after editor exit
		_keepTemplateValue,
		_needSelection = false,
		_sQueryMode = "all",
		mySimpleDialog,
		needReload = true,
		infoMessageBox,
		_db;

	var handleYes = function() {
					_operationEditor(2);
				    this.hide();
				};
	var handleNo = function() {
				    _setLayout(true, false);
				    this.hide();
				};

	/**
	*  show the list of available reports
	*  #todo - filter based on record types in result set
	*/
	function _updateTemplatesList(context) {


		var sel = document.getElementById('selTemplates'),
			option,
			keepSelIndex = sel.selectedIndex,
	 		_currentTemplate = top.HEURIST.util.getDisplayPreference("viewerCurrentTemplate");

		//celear selection list
		while (sel.length>0){
				sel.remove(0);
		}

		if(context && context.length>0){

			for (i in context){
			if(i!==undefined){

				Hul.addoption(sel, context[i].filename, context[i].name);
				if(keepSelIndex<0 && _currentTemplate==context[i].filename){
					keepSelIndex = sel.length-1;
				}
			}
			} // for

			sel.selectedIndex = (keepSelIndex<0)?0:keepSelIndex;

			if(sel.selectedIndex>=0){
				_reload(context[sel.selectedIndex].filename);
			}
		}
		_setLayout(true, false);
	}


	/**
	* Inserts template output into container
	*/
	function _updateReps(context) {

		infoMessageBox.hide();

		//converts Heurist.tmap structure to timemap structure
		//HEURIST.tmap = context;
		var div_rep = document.getElementById("rep_container");//Dom.get("rep_container");
		div_rep.innerHTML = context;

		_needSelection = (context.indexOf("Select records to see template output")>0);
    }


	/**
	* Initialization
	*
	* Reads GET parameters and requests for map data from server
	*/
	function _init() {
		_setLayout(true, false); //aftert load show viewer only

		_sQueryMode = top.HEURIST.displayPreferences["showSelectedOnlyOnMapAndSmarty"];
		document.getElementById('cbUseAllRecords2').value = _sQueryMode;
		document.getElementById('cbUseAllRecords1').value = _sQueryMode;

		_db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));

		/*var s1 = location.search;
		if(s1=="" || s1=="?null" || s1=="?noquery"){
			 s1 = null;
			 squery_all = top.HEURIST.currentQuery_all;
			 squery_sel = top.HEURIST.currentQuery_sel;
			 squery_main = top.HEURIST.currentQuery_main;
		}else{
			squery_all = _sQueryMode=="all"?s1:null;
			squery_sel = _sQueryMode=="selected"?s1:null;
			squery_main = _sQueryMode=="main"?s1:null;
		}*/

		_reload_templates();

		infoMessageBox  =
				new YAHOO.widget.SimpleDialog("simpledialog2",
					{ width: "350px",
					fixedcenter: true,
					modal: false,
					visible: false,
					draggable: false,
					close: false,
					text: "some text"
				} );
		infoMessageBox.render(document.body);

		mySimpleDialog =
				new YAHOO.widget.SimpleDialog("simpledialog1",
					{ width: "350px",
					fixedcenter: true,
					modal: true,
					visible: false,
					draggable: false,
					close: true,
					header: 'Warning',
					text: "some text",
					icon: YAHOO.widget.SimpleDialog.WARNING,
					buttons: [
				    { text: "Save", handler: handleYes, isDefault:true },
				    { text:"Discard", handler: handleNo}
					]
				} );
		mySimpleDialog.render(document.body);

	}

	/**
	* loads list of templates
	*/
	function _reload_templates(){
				var baseurl = top.HEURIST.basePath + "viewers/smarty/templateOperations.php";
				var callback = _updateTemplatesList;
				top.HEURIST.util.getJsonData(baseurl, callback, 'db='+_db+'&mode=list');
	}

	/**
	*
	*/
	function _getQueryAndTemplate(template_file, isencode){

				var squery = _getQuery();

				if(Hul.isempty(squery) ||  (squery.indexOf("&q=")<0) || (squery.indexOf("&q=") == squery.length-3)) {
					if(_sQueryMode=="selected"){
						_updateReps("<div class='wrap'><div id='errorMsg'><span>No Records Selected</span></div></div>");
					}else{
						_updateReps("<b><font color='#ff0000'>Perform search to see template output</font></b>");
					}

					return null;

				}else{

					if(Hul.isnull(template_file)){
						var sel = document.getElementById('selTemplates');
						if(Hul.isnull(sel) || Hul.isnull(sel.options) || sel.options.length===0) { return null; }
						template_file = sel.options[sel.selectedIndex].value; // by default first entry
					}

					if(isencode){
						squery = 'hquery='+encodeURIComponent(squery);
					}

					squery = squery + '&template='+template_file;
				}

		return squery;
	}

	/**
	* Executes the template with the given query
	*/
	function _reload(template_file) {

				_showLimitWarning();

				var baseurl = top.HEURIST.basePath + "viewers/smarty/showReps.php";
				var squery = _getQueryAndTemplate(template_file, false);

				if(squery!=null){


					//infoMessageBox.setBody("Execute template '"+template_file+"'. Please wait");
					infoMessageBox.setBody("<img src='../../common/images/loading-animation-white.gif'>");
					infoMessageBox.show();

				top.HEURIST.util.sendRequest(baseurl, function(xhr) {
					var obj = xhr.responseText;
					_updateReps(obj);
				}, squery);

				}
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
	function _generateTemplate(name, isLoadGenerated) {

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
					Dom.get("edTemplateName").innerHTML = name;
					ApplyLineBreaks(Dom.get("edTemplateBody"), context['text']);
					_keepTemplateValue = Dom.get("edTemplateBody").value;
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

			_doExecuteFromEditor(); //execute at once
		}

			var squery = _getQuery();

			if(Hul.isnull(squery)){
				alert('Please select some records in search results');
			}else{
				var baseurl = top.HEURIST.basePath + "viewers/smarty/templateGenerate.php";
				top.HEURIST.util.getJsonData(baseurl, __onGenerateTemplate, squery);//+'&db='+_db);
			}
	}

	/**
	* Creates new template from the given query
	*/
	function _showEditor(template_file) {

		function _onGetTemplate(context){
			Dom.get("edTemplateName").innerHTML = template_file;
			Dom.get("edTemplateBody").value = context;
			_keepTemplateValue = Dom.get("edTemplateBody").value;
			_setLayout(true, true);
		}

			var baseurl = top.HEURIST.basePath + "viewers/smarty/templateOperations.php";

			_originalFileName = template_file;

			var squery = 'db='+_db+'&mode=get&template='+template_file;

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
	* @param mode 0 - just close, 1 - save as (not close),  2 - save, close and execute, 3 - delete and close
	*/
	function _operationEditor(mode) {

		if(mode>0){

			var baseurl = top.HEURIST.basePath + "viewers/smarty/templateOperations.php",
				squery = 'db='+_db+'&mode=',
				template_file = null;

			if(mode<3)
			{ //save
				template_file = jQuery.trim(Dom.get("edTemplateName").innerHTML);

				if(mode==1){ //save as - get new name
					template_file = jQuery.trim(prompt("Please enter new template name", template_file));
					if (Hul.isempty(template_file)){
						return;
					}
					Dom.get("edTemplateName").innerHTML = template_file;
				}

				var template_body = Dom.get("edTemplateBody").value;
				if(template_body && template_body.length>10){
					squery = squery + 'save&template='+encodeURIComponent(template_file)+'&template_body='+encodeURIComponent(template_body);

					_keepTemplateValue = template_body;
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

				var modeRef = mode;
				var alwin;

				function __onOperEnd(context){
					if(context && context.error){
						//do nothing alert(context.error);
					}else{
						var mode = context.ok;
						//if(mode==="delete"){
						if(modeRef===3){ //delete
							//todo!!!! - remove template from the list and clear editor
							_reload_templates();
						}else if(template_file!=null){
							_originalFileName = template_file;//_onGetTemplate(obj);

							infoMessageBox.setBody("Template '"+template_file+"' has been saved");
							infoMessageBox.show();

							setTimeout(function(){infoMessageBox.hide();}, 1000);
						}

						if(modeRef===1 || modeRef===3){
							_needListRefresh = true;
						}
						if(modeRef===2 || modeRef===3){ //for close or delete
							_setLayout(true, false);
						}
						if(modeRef===2){
							//reload and execute the template
							_reload(template_file);
						}
					}
				}

				top.HEURIST.util.getJsonData(baseurl, __onOperEnd, squery);
			}
		}

		if(mode===0){ //for close

			if(_keepTemplateValue!=Dom.get("edTemplateBody").value){

				/*var myButtons = [
				    { text: "Save", handler: handleYes, isDefault:true },
				    { text:"Discard", handler: handleNo}
				];
				mySimpleDialog.cfg.queueProperty("buttons", myButtons);*/

				mySimpleDialog.setHeader("Warning!");
				mySimpleDialog.setBody("Template was changed. Are you sure you wish to exit and lose all modifications?");
				mySimpleDialog.show();
			}else{
				_setLayout(true, false);
			}

/*
			var r = true;
			if(_keepTemplateValue!=Dom.get("edTemplateBody").value){
				r=confirm("Template was changed. Are you sure you wish to exit and lose all modifications?");
			}
			if (r==true){
				_setLayout(true, false);
			}
*/
		}
	}

	/**
	* Executes the template from editor
	*
	* isdebug = 1-yes
	*/
	function _doExecuteFromEditor() {

		var replevel = document.getElementById('cbErrorReportLevel').value;
		if(replevel<0) {
			document.getElementById('cbErrorReportLevel').value = 0;
			replevel = 0;
		}
		/*
		if(document.getElementById('cbDebug').checked){
			replevel = 1;
		}else if (document.getElementById('cbError').checked){
			replevel = 2;
		}*/

		var template_body;
		if(_isEditorVisible()){
			var ed = tinyMCE.get('edTemplateBody');
			template_body = ed.getContent();
		}else{
			template_body = Dom.get("edTemplateBody").value;
		}

		if(template_body && template_body.length>10){

				var squery = _getQuery();

				var baseurl = top.HEURIST.basePath + "viewers/smarty/showReps.php";

				squery = squery + '&replevel='+replevel+'&template_body='+encodeURIComponent(template_body);

				infoMessageBox.setBody("<img src='../../common/images/loading-animation-white.gif'>");
				infoMessageBox.show();

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

		Dom.get("toolbar").style.display = (iseditor) ?"none" :"block";
		Dom.get("rep_container").style.top = (iseditor) ?"0px" :"65px";
		Dom.get("editorcontainer").style.display = (iseditor) ?"block" :"none";

		var dim = Hul.innerDimensions(this);

		var units;
		if(isviewer && iseditor){
				units = [
				{ position: 'top', header: 'Editor', height: dim.h*0.7,
					resize: true, body: 'editorcontainer', gutter:'5px', useShim: true, collapse: true},
				{ position: 'center', body: 'viewercontainer', height: dim.h*0.3}
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
		//Dom.get("layout").style.top = (iseditor) ?"0" :"25";

		//var el = Dom.get('layout');
		layout = null;
		layout = new YAHOO.widget.Layout({
			units: units
		});
		/*layout = new YAHOO.widget.Layout('layout', {
							units: units
							});*/
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
					_varsTree.singleNodeHighlight = true;
					_varsTree.selectable = false;
					_varsTree.subscribe("clickEvent",
						function() { // On click, select the term, and add it to the selected terms tree
							this.onEventToggleHighlight.apply(this,arguments);
							//var parentNode = arguments[0].node;
							//_selectAllChildren(parentNode);
					});
		}


		//internal function
			function __createChildren(parentNode, parent_id, _prefix, parentEntry, varnames) { // Recursively get all children
				var term,
					childNode,
					child;

				for(child in parentNode)
				{
				if(!Hul.isnull(child)){

					var fullid = parentNode[child],
						_varnames = varnames.vars,
						_detailtypes = varnames.detailtypes,
						is_enum = false,
						dt_type = '',
						label = '';

					//check if child is related record
					var is_record = ((typeof(fullid) == "object") &&
									Object.keys(fullid).length > 0);


					if(is_record)
					{ //nodes - records or enum detail types

							//check if child is enumeration detail type
							is_enum = (_detailtypes && child.indexOf(parent_id+".")==0 && _detailtypes[child]==='enum');
							if(is_enum){
								dtype = 'enum';
								label = child.substr(parent_id.length+1);
							}else{
								dtype = '';
								label = child;
							}

					}else{ //usual variables
						label = _varnames[fullid];
						dtype = (_detailtypes)?_detailtypes[fullid]:'';
					}


					if(!Hul.isnull(label)){

					term = {};//new Object();
					term.id = _prefix+"."+child; //fullid;
					term.parent_id = parent_id;
					term.this_id = child;
					term.label = '<div style="padding-left:10px;">'; //???arVars[0];
					term.labelonly = label;
					term.dtype = dtype;

					if(is_enum){
							term.label = term.label + label + '&nbsp;(enum)</div>';
					}else if( is_record ){
/* Ian's reuest 10-28
							term.label = term.label +
							'<a href="javascript:void(0)" onClick="showReps.markAllChildren(\''+
											term.id+'\')">All</a>&nbsp;&nbsp';
							term.href = "{javascript:void(0)}";
*/
							term.label = term.label + '<b>' + label + '</b>' +
'&nbsp;(<a href="javascript:void(0)" title="Insert FOREACH operator for this resource" onClick="showReps.insertSelectedVarsAsLoop(\''+term.id+'\')">loop</a>)</div>';
/*
'&nbsp;<a href="javascript:void(0)" title="Insert marked variables in loop (without parent prefix)" onClick="showReps.insertSelectedVars(\''+term.id+'\', true)">in</a>'+
'&nbsp;<a href="javascript:void(0)" title="Insert marked variables with parent prefix. To use outside the loop" onClick="showReps.insertSelectedVars(\''+term.id+'\', false)">out</a>)</div>';
*/
					}else{
						if(parent_id=="r" || parent_id.indexOf("r")==0){
							term.label = term.label + label +
'&nbsp;(<a href="javascript:void(0)" title="Insert variable" onClick="showReps.insertSelectedVars(\''+term.id+'\', true, false)">insert</a>'+
'&nbsp;<a href="javascript:void(0)" title="Insert IF operator for this variable" onClick="showReps.insertSelectedVars(\''+term.id+'\', true, true)">if</a>)</div>';
						}else{
							term.label = term.label + label +
'&nbsp;(<a href="javascript:void(0)" title="Insert variable in loop (without parent prefix)" onClick="showReps.insertSelectedVars(\''+term.id+'\', true, false)">in</a>'+
'&nbsp;<a href="javascript:void(0)" title="Insert IF operator for variable in loop (without parent prefix)" onClick="showReps.insertSelectedVars(\''+term.id+'\', true, true)">if</a>'+
'&nbsp;&nbsp;<a href="javascript:void(0)" title="Insert variable with parent prefix. To use outside the loop" onClick="showReps.insertSelectedVars(\''+term.id+'\', false, false)">out</a>'+
'&nbsp;<a href="javascript:void(0)" title="Insert IF operator for variable with parent prefix. To use outside the loop" onClick="showReps.insertSelectedVars(\''+term.id+'\', false, true)">if</a>)</div>';

						}
					}

					childNode = new YAHOO.widget.TextNode(term, parentEntry, false); // Create the node
					childNode.enableHighlight = false;

					if( is_record ){
						__createChildren(fullid, child, term.id, childNode, varnames); // createChildren() again for every child found
					}


					}
				}
				}//for
			}//__createChildren
		//end internal function


		//fill treeview with content
		var i, termid, term,
			tv = _varsTree,
			tv_parent = tv.getRoot(),
			first_node,
			varid,
			varnames;

		//clear treeview
		tv.removeChildren(tv_parent);

		for (i=1; i<_variables.length; i++){


			varnames = _variables[i];  // && _variables[i].id===recTypeID

			term = {};//new Object();
			term.id = _variables[i].id;
			term.this_id = 'r';
			term.parent_id = null;
			term.label = '<div style="padding-left:10px;">'+_variables[i].name;
			if(i>0){
				term.label =  '<b>' + term.label + '</b>&nbsp;(<a href="javascript:void(0)" title="Insert IF operator for this record type. It will allow to avoid an error if this type is missed in the result set" onClick="showReps.insertRectypeIf(\''+
											_variables[i].name+'\')">if</a>)';
			}else{
				//DO NOT common section - keep reference and common header values in top of each record type
				term.label =  term.label + '&nbsp;(<a href="javascript:void(0)" title="Insert root FOREACH operator" onClick="showReps.insertRootForEach()">insert loop</a>)';
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



			if(varid==="r"){ //don't create additional level - add childeren to rectype directly
				//common for all types
				__createChildren(_variables[0].tree.r, "r", prefix_id+".r", topLayerParent, _variables[0]);

				//specific for this type
				__createChildren(varnames.tree.r, "r", prefix_id+".r", topLayerParent, varnames);
				continue;
			}

			term = {};//new Object();
			term.id = prefix_id+"."+varid; //unique id including rectype ID
			term.parent_id = "r";  //uniques parent prefix
			term.this_id = varid;
			term.label = '<div style="padding-left:10px;">';//<a href="javascript:void(0)"></a>&nbsp;&nbsp;'+varid+'</div>';

			term.href = "{javascript:void(0)}";
			if( Object.keys(varnames.tree[varid]).length > 0){type:
/*IAN's request 10-28
					term.label = term.label +
					'<a href="javascript:void(0)" onClick="showReps.markAllChildren(\''+
											term.id+'\')">All</a>&nbsp;&nbsp;';
*/
					term.href = "javascript:void(0)"; // To make 'select all' clickable, but not leave the page when hitting enter
					if(varid!=="r"){

						if(varnames.vars[varid]){
							lbl = varnames.vars[varid];
						}else{
							lbl = varid;
						}

						term.label = term.label + '<b>' + lbl + '</b>' +
'&nbsp;(<a href="javascript:void(0)" title="Insert FOREACH operator for this resource" onClick="showReps.insertSelectedVarsAsLoop(\''+term.id+'\')">loop</a>)';
					}else{
						term.label = term.label + varid;
//IAN's '+'(<a href="javascript:void(0)" title="Insert marked variables" onClick="showReps.insertSelectedVars(\''+term.id+'\', false)">ins</a>)&nbsp;';
					}
                    term.label = term.label + '</div>'
			}else{
					term.label = term.label + varid+ //to debug replace to term.id
'&nbsp;<a href="javascript:void(0)" title="Insert variable in loop (without parent prefix)" onClick="showReps.insertSelectedVars(\''+term.id+'\', true, false)">insert</a>'+
'&nbsp;<a href="javascript:void(0)" title="Insert IF opeartor for variable in loop (without parent prefix)" onClick="showReps.insertSelectedVars(\''+term.id+'\', true, true)">if</a>)</div>';

			}

			var rectypeLayer = new YAHOO.widget.TextNode(term, topLayerParent, false); // Create the node

			var _parentNode = varnames.tree[varid];
			rectypeLayer.enableHighlight = false;


			__createChildren(_parentNode, varid, term.id, rectypeLayer, varnames); // Look for children of the node

		}
		}//for  varnames.tree
		}//for  _variables

		//TODO tv.subscribe("labelClick", _onNodeClick);
		//tv.singleNodeHighlight = true;
		//TODO tv.subscribe("clickEvent", tv.onEventToggleHighlight);

		tv.render();
		//first_node.focus();
		//first_node.toggle();
	}

	function _addIfOperator(nodedata, prefix){
		return "{if ($"+prefix+nodedata.this_id+")}\n{else}\n{/if} {* "+prefix+nodedata.this_id+" *}";
	}
	//
	//
	//
	function _addVariable(nodedata, prefix){
		var res= "",
			insertMode = Dom.get("selInsertMode").value;

		if(insertMode==0){ //variable only

			res = "{$"+prefix+nodedata.this_id+"} {*"+nodedata.labelonly+"*}";

		}else if (insertMode==1){ //label+field

			res = nodedata.labelonly+": {$"+prefix+nodedata.this_id+"}";

		}else{
			//lbl="'+nodedata.labelonly+'"
			res = '{wrap var=$'+prefix+nodedata.this_id;
			if(nodedata.dtype === '' || nodedata.this_id === 'recURL'){
				res = res + ' dt="url"';
			}else if(nodedata.dtype === 'geo'){

				res = res + '_originalvalue dt="'+nodedata.dtype+'"';

			}else if(nodedata.dtype === 'file' || nodedata.dtype === 'urlinclude'){
				res = res + '_originalvalue dt="'+nodedata.dtype+'"';
				res = res + ' width="300" height="auto"';
			}else{
			}
			res = res +'}';
		}

		return (res+((insertMode==0)?' ':'<br/>\n'));
	}

	//
	//
	//
	function _insertRootForEach(){
		var textedit = Dom.get("edTemplateBody");
		var _text = '{foreach $results as $r}\n\n{if ($r.recOrder==0)}\n\n{elseif ($r.recOrder==count($results)-1)}\n\n{else}\n\n{/if}\n{/foreach}\n';  //{* INSERT YOUR CODE HERE *}

		insertAtCursor(textedit, _text, false, -12);
	}
	//
	//
	//
	function _insertRectypeIf(rectypeName){

		var textedit = Dom.get("edTemplateBody");
		var _text = '{if ($r.recTypeName=="'+rectypeName+'")}\n\t\n{/if}\n';  //{* INSERT YOUR CODE HERE *}

		insertAtCursor(textedit, _text, false, -7);
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
								insertAtCursor(textedit, _variables[i].text+'<br/>', true, 0);
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
	function _insertSelectedVars( varid, inloop, isif ){

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

							if(isif){
								_text = _text + _addIfOperator(node.data, _prefix);
							}else{
								_text = _text + _addVariable(node.data, _prefix);
							}
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

				if (_nodep.children &&	_nodep.children > 0)
				{

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
				}else{
					if(_inloop){
						_prefix = _nodep.data.parent_id + ".";
					}else{
						var s = _nodep.data.id; //get all parents
						s = s.substring(s.indexOf('.')+1);
						if(s!=="r"){
							s = "r."+s;
						}
						s = s.substring(0,s.length-_nodep.data.this_id.length);
						_prefix = s;
					}

					if(isif){
						_text = _text + _addIfOperator(_nodep.data, _prefix);
					}else{
						_text = _text + _addVariable(_nodep.data, _prefix);
					}
				}
			}
		}

		if(_text!=="")	{
			if(_isEditorVisible()){
				var ed = tinyMCE.get('edTemplateBody');
				ed.selection.setContent(_text);
			}else{
				insertAtCursor(textedit, _text, false, 0);
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
						_text = _text + "\t\n"; //{* INSERT YOUR CODE HERE *}
				}
				_text = _text + "{/foreach}\n";
			}
		}

		if(_text!=="")	{

			if(_isEditorVisible()){
					var ed = tinyMCE.get('edTemplateBody');
					ed.selection.setContent(_text);
			}else{
				insertAtCursor(textedit, _text, false, -12);
			}

			_varsTree.render();

		}else{
			alert('To insert the loop for a particular rectord type, you first have to select one of the parent nodes');
		}
	}


	//
	// utility function - move to HEURIST.utils
	//
	function insertAtCursor(myField, myValue, isApplyBreaks, cursorIndent) {

			var scrollTop = myField.scrollTop;

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

				myField.selectionStart = endPos + myValue.length + cursorIndent;
				myField.selectionEnd = endPos + myValue.length + cursorIndent;

			} else {
				myField.value += myValue;
			}


			if(isApplyBreaks){
				myField = ApplyLineBreaks(myField, myField.value);
			}

			myField.scrollTop = scrollTop;

			setTimeout(function() {myField.focus(); }, 500);
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

	//
	//
	//
	function _insertModifier(modname){

		var textedit = Dom.get("edTemplateBody");

		if (textedit.selectionStart || textedit.selectionStart == '0') {

			//1. detect that cursor inside the variable or wrap function {$ |  }  or {wrap  }
			var startPos = textedit.selectionStart;
			var endPos = textedit.selectionEnd;

			//find last { occurence before endPos
			var k = -1,
				pos1;

			do{
				pos1 = k;
				k = textedit.value.indexOf("{$", k+1);
			}while (k>=0 && k<endPos);

			if(pos1<0){
				alert('Place cursor inside variable entry: between {}');
				return;
			}

			var pos2 = textedit.value.indexOf("}", pos1);
			if(pos2<endPos){
				alert('Place cursor inside variable entry: between {}');
				return;
			}

			//2. insert modifier name in the end of {}
			textedit.value = textedit.value.substring(0, pos2)
				+ "|"+modname
				+ textedit.value.substring(pos2, textedit.value.length);
		}
	}

	//
	function _getQuery(){
		if(_sQueryMode=="all"){
			return top.HEURIST.currentQuery_all;
		}else if(_sQueryMode=="selected"){
			return top.HEURIST.currentQuery_sel;
		}else {
			return top.HEURIST.currentQuery_main;
		}
	}

	//
	function _showLimitWarning(){

			var msg = "";

			var limit = parseInt(top.HEURIST.util.getDisplayPreference("report-output-limit"));
			if (isNaN(limit)) limit = 1000; //def value for dispPreference

			if( (_sQueryMode=="all" && top.HEURIST.currentQuery_all_waslimited) ||
				(_sQueryMode=="selected" && top.HEURIST.currentQuery_sel_waslimited) ||
				(_sQueryMode=="main" && limit<top.HEURIST.search.results.totalQueryResultRecordCount))
			{
				msg = "&nbsp;&nbsp;<font color='red'>(result set limited to "+limit+")</font>";
			}else if(_sQueryMode=="main"){
					msg = "&nbsp;&nbsp;total records: "+top.HEURIST.search.results.totalQueryResultRecordCount;
			}

			document.getElementById('recordCount').innerHTML = msg;
	}

	//
	function _onPublish(template_file){

		var q = _getQueryAndTemplate(template_file, true);

		if(q==null) return;

		var url = top.HEURIST.basePath + "export/publish/manageReports.html?"+q+"&db="+_db;

		var dim = top.HEURIST.util.innerDimensions(top);

		top.HEURIST.util.popupURL(top, url,
		{   "close-on-blur": false,
			"no-resize": false,
			height: 480,
			width: dim.w*0.9,
			callback: null
		});
	}

	//public members
	var that = {

			getQuery: function(){
				return _getQuery();
			},

			/*setQuery: function(q_all, q_sel, q_main){
				if(q_all) squery_all = q_all;
				squery_sel = q_sel;
				squery_main = q_main;
			},*/

			processTemplate: function (template_file){
				_reload(template_file);
			},

			isNeedSelection: function(){
				return _needSelection;
			},

			getQueryMode: function(){
				return _sQueryMode;
			},

			setQueryMode: function(val){
				var isChanged = _sQueryMode != val;
				_sQueryMode = val;
				top.HEURIST.util.setDisplayPreference("showSelectedOnlyOnMapAndSmarty", _sQueryMode);


				if(document.getElementById('cbUseAllRecords1')){
					document.getElementById('cbUseAllRecords1').value = val;
				}
				if(document.getElementById('cbUseAllRecords2')){
					document.getElementById('cbUseAllRecords2').value = val;
				}

				if (isChanged && needReload) {
					_reload(null);
				}
			},

			generateTemplate:  function (name){
				if(_needSelection){
					alert('Please select some records to allow generation of the template');
				}else{
				_needListRefresh = true;
					_generateTemplate(name, true);
				}
			},

			showEditor:  function (template_file){
				if(_needSelection){
					alert('Please select some records in the search results before editing the template');
				}else{
				_showEditor(template_file);
				}
			},

			operationEditor:  function (action){
				_operationEditor(action);
			},

			doExecuteFromEditor: function(){
				_doExecuteFromEditor();
			},

			onPublish:function (template_file){
				_onPublish(template_file);
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
			insertSelectedVars:function(varid, inloop, isif){
				_insertSelectedVars(varid, inloop, isif);
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

			insertModifier:function(modname){
				_insertModifier(modname);
			},

			baseURL:  function (){
				return top.HEURIST.basePath;
			},

			originalFileName:  function (val){
				_originalFileName = val;
				//_setOrigName(val);
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