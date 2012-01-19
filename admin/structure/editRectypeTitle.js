//aliases
var Dom = YAHOO.util.Dom,
	Hul = top.HEURIST.util,
	Event = YAHOO.util.Event;

/**
* EditRectypeTitle - class for pop-up window to define recordtype title mask
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2011.0919
*/
function EditRectypeTitle() {

	var _className = "EditRectypeTitle",
		_rectypeID,
		_varsTree; //treeview object

	/**
	* Initialization of input form
	*
	* Reads GET parameters, creates TabView and triggers init of first tab
	*/
	function _init() {

		// read parameters from GET request
		// recordtype ID and current value of title mask
		//
		if(location.search.length > 1) {

				top.HEURIST.parameters = top.HEURIST.parseParams(location.search);

				_rectypeID = top.HEURIST.parameters.rectypeID;
				Dom.get("rty_TitleMask").value =  top.HEURIST.parameters.mask;
		}


		if(Hul.isnull(_rectypeID)){
			window.close("Rectype ID is not defined");
			return;
		}else{

            var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
                                (top.HEURIST.database.name?top.HEURIST.database.name:''));

			//find variables for given rectypeID and create variable tree
			var baseurl = top.HEURIST.basePath + "viewers/smarty/templateGenerate.php?mode=varsonly&rty_id="+_rectypeID+
				"&ver=1&w=all&stype=&db="+db  + "&q=type:" + _rectypeID; //"&q=id:146433";
			top.HEURIST.util.getJsonData(baseurl, _onGenerateVars, "");
		}
	}//end _init


	function _onGenerateVars(context){

			if(context===false || Hul.isnull(context['vars'])){
				alert('No variables generated');
				return;
			}

			_variables = context['vars'];

			/*fille selection box with the list of templates
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
			*/

			_onSelectRectype();

			//fille list of records
			var sel = Dom.get("listRecords");
			//celear selection list
			while (sel.length>1){
				sel.remove(1);
			}

			var _recs = context['records'];

			var i;
			for (i in _recs){
			if(i!==undefined){

				option = document.createElement("option");
				option.text = _recs[i].rec_Title; //name of rectype
				option.value = _recs[i].rec_ID; //id of rectype
				try {
					// for IE earlier than version 8
					sel.add(option, sel.options[null]);
				}catch (ex2){
					sel.add(option,null);
				}
			}
			} // for

			sel.selectedIndex = 0;
	}

	//
	// fill the treeview of variables of specific record type
	//
	function _onSelectRectype(){

		/*
		var container = Dom.get("vars_list"),
			sel = Dom.get('selRectype'),
			recTypeID,
			varnames; //contains vars - flat array and tree - tree array

		if(sel.selectedIndex<0 || sel.selectedIndex>=sel.options.length){
			return;
		}
		recTypeID = sel.options[sel.selectedIndex].value;
		*/
		if(Hul.isnull(_variables) || _variables.length<1) return;

		recTypeID = _variables[0].id;


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

							var _node = arguments[0].node;
							if(_node.children.length<1){
								this.onEventToggleHighlight.apply(this,arguments);
							}

							window.setTimeout(function() {
									var textedit = Dom.get("rty_TitleMask");
									textedit.focus();
								}, 300);


							//var parentNode = arguments[0].node;
							//_selectAllChildren(parentNode);
					});
			}
			//fill treeview with content
			_fillTreeView(_varsTree, varnames);
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

			//do not create first level
			if(varid!='r'){

			var term = {};//new Object();
			term.id = varid;
			term.parent_id = "r";
			term.this_id = varid;
			term.label = '<div style="padding-left:10px;">';//<a href="javascript:void(0)"></a>&nbsp;&nbsp;'+varid+'</div>';

			//term.href = "{javascript:void(0)}";
			if( Object.keys(varnames.tree[varid]).length > 0){
					term.label = term.label + '<b>' + varid + '</b></div>';
					//'<a href="javascript:void(0)" onClick="showReps.markAllChildren(\''+
					// varid+'\')">All</a>&nbsp;&nbsp;';
					//term.href = "{javascript:void(0)}"; // To make 'select all' clickable, but not leave the page when hitting enter
			}else{
				term.label = term.label + varid+'</div>';
			}

			}


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
					term.this_id = label;
					term.label = '<div style="padding-left:10px;">'; //???arVars[0];

					if( is_record ){
							term.label = term.label + '<b>' + label + '</b></div>';
							//'<a href="javascript:void(0)" onClick="showReps.markAllChildren(\''+
							//				child+'\')">All</a>&nbsp;&nbsp';
							term.href = "{javascript:void(0)}";
					}else{
							term.label = term.label + label + '</div>';
					}

					childNode = new YAHOO.widget.TextNode(term, parentEntry, false); // Create the node
					if( is_record ){
						__createChildren(fullid, parent_id+'.'+label, childNode); // createChildren() again for every child found
					}


					}
				}
				}//for
			}

			if(varid!='r'){

				var topLayerParent = new YAHOO.widget.TextNode(term, tv_parent, false); // Create the node
				if(!first_node) { first_node = topLayerParent;}

				var _parentNode = varnames.tree[varid];
				__createChildren(_parentNode, varid, topLayerParent); // Look for children of the node

			}else{
				__createChildren(varnames.tree[varid], varid, tv_parent);
			}

		}
		}//for

		//TODO tv.subscribe("labelClick", _onNodeClick);
		//tv.singleNodeHighlight = true;
		//TODO tv.subscribe("clickEvent", tv.onEventToggleHighlight);

		tv.render();
		//first_node.focus();
		//first_node.toggle();
	}

	/**
	*
	*/
	function _doTest()
	{
			var mask = document.getElementById('rty_TitleMask').value;
			var rec_type = top.HEURIST.parameters.rectypeID;

            var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
                                (top.HEURIST.database.name?top.HEURIST.database.name:''));


			var baseurl = top.HEURIST.basePath + "admin/structure/editRectypeTitle.php";
			var squery = "rty_id="+rec_type+"&mask="+mask+"&db="+db+"&check=1";

			top.HEURIST.util.sendRequest(baseurl, function(xhr) {
					var obj = xhr.responseText;
					if(obj===""){
						var sel = Dom.get("listRecords");
						if (sel.selectedIndex>0){

							var rec_id = sel.value;
							squery = "rty_id="+rec_type+"&rec_id="+rec_id+"&mask="+mask+"&db="+db;
							top.HEURIST.util.sendRequest(baseurl, function(xhr) {
								var obj2 = xhr.responseText;
								document.getElementById('testResult').innerHTML = obj2;
							}, squery);
						}else{
							alert('Select record from pulldown to test your title mask');
						}
					}else{
						alert(obj);
					}

				}, squery);
	}

	//
	// utility function - move to HEURIST.utils
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

	/**
	*
	*/
	function _doInsert(){

		var textedit = Dom.get("rty_TitleMask"),
			_text = "";

		//function for each node in _varsTree - create the list
		function __loopNodes(node){
				if(node.children.length===0 && node.highlightState===1){
						node.highlightState=0;
						var parent = (node.data.parent_id=='r')?'':(node.data.parent_id+'.');
						_text = _text + '['+parent + node.data.this_id+']';
				}
				return false;
		}
		//loop all nodes of tree
		_varsTree.getNodesBy(__loopNodes);

		if(_text!=="")	{
			insertAtCursor(textedit, _text);
			_varsTree.render();
		}else{
			alert('No one variable is selected');
		}

		var textedit = Dom.get("rty_TitleMask");
		textedit.focus();
	}

	//
	//public members
	//
	var that = {

			//fill the list with variables of specific record type
			onSelectRectype:function(){ //TO REMOVE
				_onSelectRectype();
			},

			doTest:function(){
				_doTest();
			},
			doInsert:function(){
				_doInsert();
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
