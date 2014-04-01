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
		_variables, //object from server - record type tree
		_varsTree, //treeview object
		_needListRefresh = false, //if true - reload list of templates after editor exit
		_keepTemplateValue,
		_needSelection = false,
		_sQueryMode = "all",
		mySimpleDialog,
		needReload = true,
		codeEditor = null,
		_isAceEditor = false,
		infoMessageBox,
		_db;

	var top_repcontainer = '30px';


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
	 		_currentTemplate = Hul.getDisplayPreference("viewerCurrentTemplate");

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
        
        window.onbeforeunload = _onbeforeunload;
	}

	/**
	* loads list of templates
	*/
	function _reload_templates(){
				var baseurl = top.HEURIST.basePath + "viewers/smarty/templateOperations.php";
				var callback = _updateTemplatesList;
				Hul.getJsonData(baseurl, callback, 'db='+_db+'&mode=list');
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

				Hul.sendRequest(baseurl, function(xhr) {
					var obj = xhr.responseText;
					_updateReps(obj);
				}, squery);

				}
				//Hul.getJsonData(baseurl, callback, squery);
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
    
    function _onbeforeunload() {
            if(_iseditor && _keepTemplateValue && _keepTemplateValue!=codeEditor.getValue()){
                return "Template was changed. Are you sure you wish to exit and lose all modifications?";
            }
    }
    

	/**
	* Creates new template from the given query
	*
	* isLoadGenerated - new template
	*/
	function _generateTemplate(name, isLoadGenerated) {

		function __onGenerateTemplate(context){

			if(Hul.isnull(context)){
				return;
			}
			if(context===false){
				alert('No template generated');
				return;
			}

			if(isLoadGenerated){

				if(_isEditorVisible()){ //NOT USED
					var ed = tinyMCE.get('edTemplateBody');
					ed.setContent(context['text']);
				}else if(true) {

					//ApplyLineBreaks
					var text = [
                    
                    
                '{* This is a very simple Smarty report template which you can edit into something more sophisticated.',
                '   Enter html for web pages or other text format. Use tree on right to insert fields.',
                '   Use this format to include comments in your file, use <!-- --> for output of html comments.',
                '   Smarty help describes many functions you can apply, loop counting/summing, custom fucntions et.*}',
                '',
                '<h2>Title for report</h2> {* Text here appears at start of report *}',
                '<hr>',
                '',
                '{*------------------------------------------------------------*}',
                '{foreach $results as $r} {* Start records loop, do not remove *}',
                '{*------------------------------------------------------------*}',
                '',
                '',
                '',   
                '',   
                '  {* put the data you want output for each record here - insert the *}',
                '  {* fields using the tree of record types and fields on the right *}',
                '',
                '  {* Examples: *}',
                '     {$r.recID}  {* the unique record ID *}',
                '     {$r.f1}     {* the name / title field - may or may not be present *}  ',
                '',
                '',
                '',
                '<br/> {* line break between each record *}',
                '',
                '{*------------------------------------------------------------*}',
                '{/foreach} {* end records loop, do not remove *}',
                '{*------------------------------------------------------------*}',
                '',
                '<hr/>',
                '<h2>End of report</h2> {* Text here appears at end of report *}'];
        
                        
					var k;
					var res = "";
                    for (k=0;k<text.length;k++){
						res += text[k] + "\n"; // + text.substr(k+2);
					}

					Dom.get("edTemplateName").innerHTML = name;
					_initEditor(res);
					_keepTemplateValue = codeEditor.getValue();
				}else{ //NOT USED
					Dom.get("edTemplateName").innerHTML = name;
					ApplyLineBreaks(Dom.get("edTemplateBody"), context['text']);
					_keepTemplateValue = Dom.get("edTemplateBody").value;
				}
			}

			_variables = context;

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
				var baseurl = top.HEURIST.basePath + "common/php/recordTypeTree.php";
				Hul.getJsonData(baseurl, __onGenerateTemplate, squery);//+'&db='+_db);
			}
	}

	function _initEditor(content) {
		if(codeEditor==null){
			if(_isAceEditor){
    			codeEditor = ace.edit("editor");
    			codeEditor.setTheme("ace/theme/chrome");
    			codeEditor.getSession().setMode("ace/mode/php");
    			codeEditor.setValue(content);
			}else{
                
				codeEditor = CodeMirror(Dom.get("templateCode"), {
                    mode           : "smartymixed",
                    tabSize        : 2,
                    indentUnit     : 2,
                    indentWithTabs : false,
                    lineNumbers    : true,
                    smartyVersion  : 3,
                    matchBrackets  : true,                    
                    smartIndent    : true,
                    extraKeys: {
                        "Enter": function(e){
                                insertAtCursor(null, "");
                           }                
                    }});
			}
		}else if(!_isAceEditor){
			$('.CodeMirror').hide();
		}

		codeEditor.setValue(content);

		if(!_isAceEditor){
			setTimeout(function(){
				$('.CodeMirror').show();
				codeEditor.refresh();
			},2000);
		}
	}

	/**
	* Creates new template from the given query
	*/
	function _showEditor(template_file) {

		function _onGetTemplate(context){

			Dom.get("edTemplateName").innerHTML = template_file;
			_initEditor(context);
			//Dom.get("edTemplateBody").value = context;
			_keepTemplateValue = context; //Dom.get("edTemplateBody").value;
			_setLayout(true, true);
		}

			var baseurl = top.HEURIST.basePath + "viewers/smarty/templateOperations.php";

			_originalFileName = template_file;

			var squery = 'db='+_db+'&mode=get&template='+template_file;

			Hul.sendRequest(baseurl, function(xhr) {
					var obj = Hul.evalJson(xhr.responseText);
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

				var template_body = codeEditor.getValue();// Dom.get("edTemplateBody").value;
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

					if(!Hul.isnull(context))
					{
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
						if(modeRef===3){ //for close or delete
							_setLayout(true, false);
						}
						if(modeRef===2){
							//reload and execute the template
							_reload(template_file);
						}
					}
				}

				Hul.getJsonData(baseurl, __onOperEnd, squery);
			}
		}

		if(mode===0){ //for close

			if(_keepTemplateValue!=codeEditor.getValue()){ //.get("edTemplateBody").value){

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
		}else if (true){
			template_body = codeEditor.getValue();
		}else{
			template_body = Dom.get("edTemplateBody").value;
		}

		if(template_body && template_body.length>10){

				var squery = _getQuery();

				var baseurl = top.HEURIST.basePath + "viewers/smarty/showReps.php";

				squery = squery + '&replevel='+replevel+'&template_body='+encodeURIComponent(template_body);

				infoMessageBox.setBody("<img src='../../common/images/loading-animation-white.gif'>");
				infoMessageBox.show();

				Hul.sendRequest(baseurl, function(xhr) {
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

		if(_iseditor!=iseditor){
			//find in parent
			var restLayout = top.document.getElementById("resetPanels_Smarty"+(iseditor?"On":"Off"));
			if(restLayout){
				Hul.clickworkaround(restLayout);
			}
		}

		_isviewer=isviewer;
		_iseditor=iseditor;

		Dom.get("toolbardiv").style.display = (iseditor) ?"none" :"block";
		Dom.get("rep_container").style.top = (iseditor) ?"0px" :top_repcontainer;
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
        
        
        //top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex.rst_DisplayName;
        var idx_maxval = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex.rst_MaxValues;
        var idx_dtype  = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex.dty_Type;

		//internal function
            // parent_single - true if recrod pointer or enum is not repeatable field type
            //
			function __createChildren(parentNode, rectypeTree, parent_id, parent_full, parent_single) { // Recursively get all children
            //  __createChildren(topLayerNode, _variables[i], "r", prefix_id+".r");
            
				var term,
					childNode,
					child, id;
                    
                var rectype_id = rectypeTree.rt_id;

				for(id in rectypeTree)
				{
				if(! (Hul.isnull(id) || id=='rt_id' || id=='rt_name' || id=='termfield_name' ) ){
                    
                    var label = null;
                    
                    //cases
                    // common fields like recID, recTitle
                    // detail fields  fNNN: name
                    // detail fields ENUMERATION fNNN:array(termfield_name
                    // unconstained pointers fNNN:array(rt_name
                    // multi-contrained pointers fNNN:array(array(rt_id  - need another recursive loop

                    term = {};//new Object();
                    term.id = parent_full+"."+id; //fullid;
                    term.parent_full_id = parent_full;
                    term.parent_id = parent_id;
                    term.this_id = id;
                    term.label = '<div style="padding-left:10px;">'; //???arVars[0];
                    
                    child = rectypeTree[id];

                    var is_record = ((typeof(child) == "object") &&
                                    Object.keys(child).length > 0);
                                    
                    var is_multicontstrained = false; 
                    var is_single = true; //non repeatable field
                    
                    var vartype = term.this_id.substring(0,1);
                    var dtid = term.this_id.substring(1);
                    
                    if(vartype=='f' && Hul.isNumber(dtid) ){
                        term.dtype = top.HEURIST.detailTypes.typedefs[dtid].commonFields[idx_dtype];
                        
                        var maxval = top.HEURIST.rectypes.typedefs[rectype_id].dtFields[dtid][idx_maxval];
                        is_single = (Number(maxval)===1);
                    }else if (term.this_id=="Relationship") {
                        is_single = false;
                    }
                    
                                   
                    if(!is_record){ //simple
                    
                        label = child;   
                        
                        if(parent_single){   //parent_id=="r"){ // || parent_id.indexOf("r")==0){
                            term.label = term.label + label +
'&nbsp;<span class="insert-popup">(<a href="javascript:void(0)" title="Insert variable" onClick="showReps.showInsertPopup(\''+term.id+'\', false, this)">insert</a>)</span>'+
'<span class="insert-intree">'+                            
'&nbsp;(<a href="javascript:void(0)" title="Insert variable" onClick="showReps.insertSelectedVars(\''+term.id+'\', false, false)">insert</a>'+
'&nbsp;<a href="javascript:void(0)" title="Insert IF operator for this variable" onClick="showReps.insertSelectedVars(\''+term.id+'\', true, true)">if</a>)</span></div>';
                        }else{
                            term.label = term.label + label +
'&nbsp;<span class="insert-popup">(<a href="javascript:void(0)" title="Insert variable" onClick="showReps.showInsertPopup(\''+term.id+'\', true, this)">insert</a>)</span>'+
'<span class="insert-intree">'+                            
'&nbsp;(<a href="javascript:void(0)" title="Insert variable in repeat (without parent prefix)" onClick="showReps.insertSelectedVars(\''+term.id+'\', true, false)">in</a>'+
'&nbsp;<a href="javascript:void(0)" title="Insert IF operator for variable in repeat (without parent prefix)" onClick="showReps.insertSelectedVars(\''+term.id+'\', true, true)">if</a>'+
'&nbsp;&nbsp;<a href="javascript:void(0)" title="Insert variable with parent prefix. To use outside the repeat" onClick="showReps.insertSelectedVars(\''+term.id+'\', false, false)">out</a>'+
'&nbsp;<a href="javascript:void(0)" title="Insert IF operator for variable with parent prefix. To use outside the repeat" onClick="showReps.insertSelectedVars(\''+term.id+'\', false, true)">if</a>)</span></div>';
                        }
                        
                    }else{
                         
                         if(child['termfield_name']) {
                             label = child['termfield_name'];
                             term.label = term.label + label; //<b> + '</b>';
                         }else{
                             if ( typeof(child[0]) == "string" ) {
                                is_multicontstrained = true;
                                label = child[0];         
                             }else{
                                label = child['rt_name'];         
                             }
                             term.label = term.label + '<b><i>' + label + '</i></b>';
                         }
                         
                         
                         if(!is_single){
                            term.label = term.label + '&nbsp;(<a href="javascript:void(0)" '+
                            'title="Insert FOREACH operator for this resource" onClick="showReps.insertSelectedVarsAsLoop(\''+term.id+'\')">repeat</a>)';    
                         }
                         term.label = term.label + '</div>';
                    }

                    term.labelonly = label;

					childNode = new YAHOO.widget.TextNode(term, parentNode, false); // Create the node
					childNode.enableHighlight = false;
                    
                    if( is_multicontstrained ){

                        var k;                        
                        for(k=1; k<child.length; k++){
                            
                            var rt_term = {};//new Object();
                            rt_term.id = term.id+"."+child[k].rt_id;  //record type

                            var _varname;
                            if(is_single){
                                _varname = term.parent_id+"."+term.id;
                            }else{
                                _varname = term.id;
                            }
                            
                            rt_term.label =  '<div style="padding-left:10px;"><b>' + child[k].rt_name + 
                                        '</b>&nbsp;(<a href="javascript:void(0)" '+
                                        'title="Insert IF operator for this record type. It will allow to avoid an error if this type is missed in the result set" '+
                                        'onClick="showReps.insertRectypeIf(\''+term.id+'\',' + child[k].rt_id + ', \'' + child[k].rt_name.replace("'", "\\'") + '\')">if</a>)';
                                        
                                        //'onClick="showReps.insertRectypeIf(\''+term.this_id+'\', \'' + child[k].rt_name.replace("'", "\\'") + '\')">if</a>)';
                                        
                            rt_term.label =  rt_term.label + '</div>';

                            rt_term.href = "javascript:void(0)";

                            var rectypeNode = new YAHOO.widget.TextNode(rt_term, childNode, false);
                            
                            __createChildren(rectypeNode, child[k], term.this_id, term.id, is_single);
                        }
                        
                    }else if( is_record ){ //next recursion
                        __createChildren(childNode, child, term.this_id, term.id, is_single);
					}
				}
				}//for
			}//__createChildren
		//end internal function


		//fill treeview with content
		var i, termid, term,
			tv = _varsTree,
			tv_parent = tv.getRoot(),
			varid,
			varnames;

		//clear treeview
		tv.removeChildren(tv_parent);

//    _variables    
//   {rt_id: , rt_name, recID, recTitle ..... 
//                  fNNN:'name', 
//                  fNNN:array(termfield_name: , id, code:  )
//                  fNNN:array(rt_name: , recID ...... ) //unconstrained pointer
//                  fNNN:array(rt_id: , rt_name, recID, recTitle ... ) //constrined pointer
//
        

		for (i=0; i<_variables.length; i++){ //root elements - all rectypes in search result

			varnames = _variables[i];  // && _variables[i].id===recTypeID

			term = {};//new Object();
			term.id = _variables[i].rt_id;  //record type
			term.this_id = 'r';
			term.parent_id = null;
            
			term.label =  '<div style="padding-left:10px;"><b>' +_variables[i].rt_name + 
                        '</b>&nbsp;(<a href="javascript:void(0)" '+
                        'title="Insert IF operator for this record type. It will allow to avoid an error if this type is missed in the result set" '+
                        'onClick="showReps.insertRectypeIf(\'r\', ' + _variables[i].rt_id + ', \'' + _variables[i].rt_name.replace("'", "\\'") + '\')">if</a>)';
                        //'onClick="showReps.insertRectypeIf(\'r\', \'' + _variables[i].rt_name.replace("'", "\\'") + '\')">if</a>)';
                        
			term.label =  term.label + '</div>';

			term.href = "javascript:void(0)";

			var topLayerNode = new YAHOO.widget.TextNode(term, tv_parent, false); // Create the node
            
            __createChildren(topLayerNode, _variables[i], 'r', 'r', true);

		}//for  _variables

		//TODO tv.subscribe("labelClick", _onNodeClick);
		//tv.singleNodeHighlight = true;
		//TODO tv.subscribe("clickEvent", tv.onEventToggleHighlight);

		tv.render();
		//first_node.focus();
		//first_node.toggle();
	}

	function _addIfOperator(nodedata, varname){
        //var varname = nodedata.id; //was prefix+nodedata.this_id
        var parname = _getVariableName(nodedata.parent_full_id);
        if(parname!=""){
           parname = parname + " >> ";
        }
        var remark = "{* "+ parname + _getVariableName(nodedata.id) + " *}"; //was this_id
		return "{if ($"+varname+")}"+remark+"\n  \n{else}\n{/if}"+remark;
	}
	//
	//
	//
	function _addVariable(nodedata, varname){
		var res= "",
			insertMode = Dom.get("selInsertMode").value;
            
        //var varname = nodedata.id; //was prefix+nodedata.this_id
         var parname = _getVariableName(nodedata.parent_full_id);
        if(parname!=""){
           parname = parname + " >> ";
        }

        var remark = (parname + _getVariableName(nodedata.id)).trim(); //was this_id
            

		if(insertMode==0){ //variable only

			res = "{$"+varname+"}{*" +  remark + "*}";

		}else if (insertMode==1){ //label+field

			res = nodedata.labelonly+": {$"+varname+"}";

		}else{
            var dtype = nodedata.dtype;
            /*
            var vartype = nodedata.this_id.substring(0,1);
            var dtid = nodedata.this_id.substring(1);
            if(vartype=='f' && Hul.isNumber(dtid) ){
               dtype = top.HEURIST.detailTypes.typedefs[dtid].commonFields[3];
            }*/
            
			//lbl="'+nodedata.labelonly+'"
			res = '{wrap var=$'+varname;
			if(Hul.isempty(dtype) || nodedata.this_id === 'recURL'){
				res = res + ' dt="url"';
			}else if(dtype === 'geo'){

				res = res + '_originalvalue dt="'+dtype+'"';

			}else if(dtype === 'file'){
				res = res + '_originalvalue dt="'+dtype+'"';
				res = res + ' width="300" height="auto"';
			}else{
			}
			res = res +'}';
		}

		return (res+((insertMode==0)?' ':'<br/>\n'));
	}

	//
	// root loop
	//
	function _insertRootForEach(){
		var textedit = Dom.get("edTemplateBody");
		var _text = '{foreach $results as $r}\n\n  {if ($r.recOrder==0)}\n    \n  {elseif ($r.recOrder==count($results)-1)}\n    \n  {else}\n  \n{/if}\n{/foreach}\n';  //{* INSERT YOUR CODE HERE *}

		insertAtCursor(textedit, _text, false, -12);
	}
	//
	// if for root rectypes
	//
	function _insertRectypeIf(parent, rectypeId, rectypeName){

		var textedit = Dom.get("edTemplateBody");
		
        //var _text = '{if ($'+parent+'.recTypeName=="'+rectypeName+'")}\n\t\n{/if}\n';
        
        var _text = '{if ($'+parent+'.recTypeID=="'+rectypeId+'")}{* '+rectypeName+ ' *}\n  \n{/if}{* '+rectypeName+ ' *}\n';  //{* INSERT YOUR CODE HERE *}
        

		insertAtCursor(textedit, _text, false, -7);
	}
    
    var insertPopupID, insert_ID; 

    function _showInsertPopup( varid, isloop, elt ){
    
        var ele = document.getElementById("insert-popup");
        
        if(isloop){
            $(".ins_isloop").show();
        }else{
            $(".ins_isloop").hide();
        }

        function __shownewpopup(){
                var pos = top.HEURIST.getPosition(elt);
                var scroll = document.getElementById("treeContainer").scrollTop;
                insert_ID = varid;
                
                    var node = _findNodeById(varid);
                    var title;
                    if(node){
                        title = ucwords(node.data.labelonly);
                    }else{
                        title = 'Insert variable';
                    }
                
                
                var w = top.HEURIST.util.popupTinyElement(top, ele, {"no-titlebar": false, "title":title, x: pos.x + elt.offsetWidth, "no-close": false, y: pos.y-scroll, width: 400, height: 200});
                                    // 
                insertPopupID = w.id;
        }
        
        if(top.HEURIST.util.popups.list.length>0){ //close previous
            top.HEURIST.util.closePopupAll();
            //top.HEURIST.util.closePopup(insertPopupID);
            insertPopupID = null;
            //setTimeout(__shownewpopup, 2000);
        }else{
            __shownewpopup();
        }
    }
    
	//
	// inserts selected variables
	//
    function _insertSelectedVars( varid, inloop, isif ){
        
        top.HEURIST.util.closePopupAll();
        if(varid==null){
            //top.HEURIST.util.closePopup(insertPopupID);
            insertPopupID = null;
            varid = insert_ID;
        }

        var textedit = Dom.get("edTemplateBody"),
            _text = "",
            _varid = varid,
            _inloop = inloop;
            _varname = "";


            var _nodep = _findNodeById(varid);
            if(_nodep){
                
                    _nodep = _nodep.data;
                
                    if(_inloop){
                        _varname = _nodep.parent_id+"."+_nodep.this_id;
                    }else{
                        _varname = _nodep.id;
                    }

                
                    if(isif){
                        _text = _text + _addIfOperator(_nodep, _varname);
                    }else{
                        _text = _text + _addVariable(_nodep, _varname);
                    }
            }
                
            if(_text!=="")    {
                insertAtCursor(textedit, _text, false, 0);
            }
    }
    

	//
	function _insertSelectedVarsAsLoop( varid ){

		var textedit = Dom.get("edTemplateBody"),
			insertMode = Dom.get("selInsertMode").value,
			_text = "",
			_prefix = "";


		var _nodep = _findNodeById(varid);
		if(_nodep){
			var arr_name = (_nodep.data.this_id==="r") ?"results" : _nodep.data.parent_id+'.'+_nodep.data.this_id+'s';
            var item_name = (_nodep.data.this_id==="r") ?"r" : _nodep.data.this_id;
            var remark = "{* "+_getVariableName(_nodep.data.id)+" *}";
               
			_text = "{foreach $"+arr_name+" as $"+item_name+"}"+remark+"\n  \n{/foreach}"+remark+"\n";
		}
        
        if(_text!=="")    {
            insertAtCursor(textedit, _text, false, -12);
        }
       
	}

    function ucwords (str) {
      return (str + '').replace(/^([a-z\u00E0-\u00FC])|\s+([a-z\u00E0-\u00FC])/g, function ($1) {
        return $1.toUpperCase();
      });
    }    
    
    function _getVariableName(id){
        if(!Hul.isempty(id)){
            
                if (id=="r"){
                    return "";
                }else if (id=="Relationship") {
                    return id;
                }else{
                    
                    var node = _findNodeById(id);
                    if(node){
                        return ucwords(node.data.labelonly);
                    }else{
                        return "notfound";
                    }                  
                }
        
        }else{
            return "";
        }
    }
    
    function _getVariableName_old(id){
        if(!Hul.isempty(id)){
            
                if (id=="r"){
                    return "";
                }else if( id=="Relationship" || !Hul.isNumber(id.substring(1)) ) {
                    return id;
                }else{
                
                    var type = id.substring(0,1);
                    id  = id.substring(1);
                    
                    if(type=="r"){
                        return ucwords(top.HEURIST.rectypes.names[id]);
                    }else{
                        return ucwords(top.HEURIST.detailTypes.names[id]);
                    }
                }
        
        }else{
            return "";
        }
    }

    
    /**
    * myField, isApplyBreaks, cursorIndent - not used
    */
	function insertAtCursor(myField, myValue, isApplyBreaks, cursorIndent) {
		if(_isAceEditor){
			codeEditor.insert(myValue);
		}else{
            //for codemirror
            var crs = codeEditor.getCursor();
            //calculate required indent
            var l_no = crs.line;
            var line = "";
            var indent = 0;
            
            while (line=="" && l_no>0){
                line = codeEditor.getLine(l_no);
                
                l_no--;
                if(line=="") continue;
                
                indent = CodeMirror.countColumn(line, null, codeEditor.getOption("tabSize"));
                
                if(line.indexOf("{if")>=0 || line.indexOf("{foreach")>=0){
                    indent = indent + 2;
                }
            }
            
            var off = new Array(indent + 1).join(' ');
            
            myValue = "\n" + myValue;
            myValue = myValue.replace(/\n/g, "\n"+off);
            
			codeEditor.replaceSelection(myValue);

            if(myValue.indexOf("{if")>=0 || myValue.indexOf("{foreach")>=0){
                crs.line = crs.line+2;
                crs.ch = indent + 2;
                //crs.ch = 0;
            }else{
                crs = codeEditor.getCursor();
            }

			codeEditor.setCursor(crs);
            setTimeout(function(){codeEditor.focus();},200);
		}
	}
	//
	// utility function - move to HEURIST.utils
	//
	function insertAtCursor_fortextarea(myField, myValue, isApplyBreaks, cursorIndent) {

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

		insertAtCursor(null, "|"+modname, null, null);

/*
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
*/
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

			var limit = parseInt(Hul.getDisplayPreference("report-output-limit"));
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

		var dim = Hul.innerDimensions(top);

		Hul.popupURL(top, url,
		{   "close-on-blur": false,
			"no-resize": false,
			height: 480,
			width: dim.w*0.9,
			callback: null
		});
	}

	function _onResize(newwidth){

		var newval = newwidth>490?'30px':'60px';

		if(top_repcontainer!=newval){
			top_repcontainer = newval;
			if(!_iseditor){
				Dom.get("rep_container").style.top = top_repcontainer;
			}
		}
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
				Hul.setDisplayPreference("showSelectedOnlyOnMapAndSmarty", _sQueryMode);


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

			//inserts selected variables inside the loop
			insertSelectedVarsAsLoop:function(varid){
				_insertSelectedVarsAsLoop(varid);
			},

            showInsertPopup:function(varid, isloop, elt){
                _showInsertPopup(varid, isloop, elt);
            },
            
			//inserts selected variables
			insertSelectedVars:function(varid, inloop, isif){
				_insertSelectedVars(varid, inloop, isif);
			},

			insertRectypeIf:function(parent, rectypeId, rectypeName){
				_insertRectypeIf(parent, rectypeId, rectypeName);
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

			onResize: function(newwidth){
				_onResize(newwidth);
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

/*
$(function(){
     * this swallows backspace keys on any non-input element.
     * stops backspace -> back
    var rx = /INPUT|SELECT|TEXTAREA/i;

    $(document).bind("keydown keypress", function(e){
        if( e.which == 8 ){ // 8 == backspace
            if(!rx.test(e.target.tagName) || e.target.disabled || e.target.readOnly ){
                e.preventDefault();
            }
        }
    });
});
*/

