/**
* editTerms.js: Support file for editTerms.php
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @author      Stephen White
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


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
    _keepCurrentParent = null,
    _vocabulary_toselect,
    _parentNode,
    _currentDomain,
    _db,
    _isWindowMode=false,
    _isSomethingChanged=false,
    _affectedVocabs = [],
    keep_target_newparent_id = null;

    /**
    *	Initialization of tabview with 2 tabs with treeviews
    */
    function _init (){

        top.HEURIST.parameters = top.HEURIST.parseParams(location.search);

        _isWindowMode = !Hul.isnull(top.HEURIST.parameters.popup);

        _vocabulary_toselect = top.HEURIST.parameters.vocabid;
        var initdomain = 0;
        if(top.HEURIST.parameters.domain=='relation'){
            initdomain  = 1;
        }

        _db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));

        _tabView.addTab(new YAHOO.widget.Tab({
            id: 'enum',
            label: 'Terms',
            content: '<div style="height:90%; max-width:300; overflow: auto;"><div id="termTree1" class="termTree ygtv-highlight" style="width:100%;height:100%;"></div></div>'
        }));
        _tabView.addTab(new YAHOO.widget.Tab({
            id: 'relation',
            label: 'Relationship types',
            content: '<div style="height:90%; max-width:300; overflow:auto"><div id="termTree2" class="termTree ygtv-highlight" style="width:100%;height:100%;"></div></div>'
        }));
        _tabView.addListener("activeTabChange", _handleTabChange);
        _tabView.appendTo("tabContainer");

        _tabView.set("activeIndex", initdomain);

        var dv1 = Dom.get('divApply');
        var dv2 = Dom.get('divBanner');
        var dv3 = Dom.get('page-inner');
        if(_isWindowMode){
            dv1.style.display = "block";
            dv2.style.display = "none";
            dv3.style.top = "5px";
            window.close = that.applyChanges;

        }else{
            dv1.style.display = "none";
            dv2.style.display = "block";
        }
        
        _initFileUploader();
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

        if(_vocabulary_toselect){
            _findNodeById(_vocabulary_toselect, true);
            _vocabulary_toselect=null;
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
            if(!Hul.isnull(termid)){

                var nodeIndex = tv.getNodeCount()+1;

                var arTerm = termsByDomainLookup[termid];

                var term = {};//new Object();
                term.id = termid;
                term.parent_id = null;
                term.conceptid = arTerm[fi.trm_ConceptID];
                term.domain = _currentDomain;
                term.label = Hul.isempty(arTerm[fi.trm_Label])?'ERROR N/A':arTerm[fi.trm_Label];
                term.description = arTerm[fi.trm_Description];
                term.termcode  = arTerm[fi.trm_Code];
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
                                //if(child==5746){ //debug
                                //    term.id = child;
                                //}
                                
                                term.id = child;
                                term.parent_id = parent_id;
                                term.conceptid = arTerm[fi.trm_ConceptID];
                                term.domain = _currentDomain;
                                term.label = Hul.isempty(arTerm[fi.trm_Label])?'ERROR N/A':arTerm[fi.trm_Label];
                                term.description = arTerm[fi.trm_Description];
                                term.termcode  =  arTerm[fi.trm_Code];
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

        //tv.subscribe("labelClick", _onNodeClick);
        tv.singleNodeHighlight = true;

        tv.subscribe("clickEvent", _onNodeClick); //tv.onEventToggleHighlight);

        tv.render();
        //first_node.focus();
        //first_node.toggle();

        // todo
        if(_keepCurrentParent!=null){
            //need some time for render
            setTimeout(function(){
                    var node = _findNodeById(_keepCurrentParent);
                    node.focus();
                    node.toggle();
            }, 1000);
            //_onNodeClick(node);
        }
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

        nodeid = ""+nodeid;

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
    /*
    * find vocabulary term (top level node)
    */
    function _findTopLevelForId(nodeid){

        var node = _findNodeById(nodeid, false);
        if(Hul.isnull(node)){
            return null;
        }else{
            var parent_id = Number(node.data.parent_id)
            if(Hul.isnull(parent_id) || isNaN(parent_id) || parent_id===0){
                return node;
            }else{
                return _findTopLevelForId(parent_id);
            }
        }
    }
    
    function _isNodeChanged(){
        
        var ischanged = false;
        
        if(_currentNode!=null){
        
                ischanged =  
                    Dom.get('edName').value.trim() != _currentNode.label ||
                    Dom.get('edDescription').value.trim() != _currentNode.data.description || 
                    Dom.get('edCode').value.trim() != _currentNode.data.termcode ||
                    Dom.get("trm_Status").value != _currentNode.data.status ||
                    Dom.get('edInverseTermId').value != _currentNode.data.inverseid; 
        }
        
          var ele = $('#btnSave');
          if (ischanged) {
                ele.removeProp('disabled');
                ele.css('color','black');
          }else{
                ele.prop('disabled', 'disabled');
                ele.css('color','lightgray');
          }
            
    }
    

    /**
    * Loads values to edit form
    */
    function _onNodeClick(obj){

        var node = null;
        if(obj){
            if(obj.node){
                node = obj.node;
            }else{
                node = obj;
            }
        }

        _parentNode = null;

        if(_currentNode !== node)
        {
            if(!Hul.isnull(_currentNode)){
                _keepCurrentParent = null;
                _doSave(true, false);
            }
            _currentNode = node;

            var disable_status = false,
            disable_fields = false,
            add_reserved = false,
            status = 'open';

            if(!Hul.isnull(node)){
                Dom.get('formMessage').style.display = "none";
                Dom.get('formEditor').style.display = "block";

                if (Hul.isempty( node.data.conceptid)){
                    Dom.get('div_ConceptID').innerHTML = '';
                }else{
                    Dom.get('div_ConceptID').innerHTML = 'Concept ID: '+node.data.conceptid;
                }

                
                //	alert("label was clicked"+ node.data.id+"  "+node.data.domain+"  "+node.label);
                Dom.get('edId').value = node.data.id;
                Dom.get('edParentId').value = node.data.parent_id;
                var edName = Dom.get('edName');
                edName.value = node.label;
                if(node.label==="new term"){
                    //highlight all text
                    edName.selectionStart = 0;
                    edName.selectionEnd = 8;
                }
                edName.focus();
                if(Hul.isnull(node.data.description)) {
                    node.data.description="";
                }
                if(Hul.isnull(node.data.termcode)) {
                    node.data.termcode="";
                }
                Dom.get('edDescription').value = node.data.description;
                Dom.get('edCode').value = node.data.termcode;
                
                //image
                if(node.data.id>0){                                       
                    var curtimestamp = (new Date()).getMilliseconds();
                    Dom.get('termImage').innerHTML =
            '<a href="javascript:void(0)" onClick="{editTerms.showFileUploader()}" title="Click to change image">'+
            '<img id="imgThumb" style="max-width: 380px;" src="'+
            top.HEURIST.iconBaseURL + node.data.id + "&ent=term&editmode=1&t=" + curtimestamp +
            '"></a>';
                    Dom.get('termImage').style.display = 'block';
                }else{
                    Dom.get('termImage').style.display = 'none';
                }
                

                var node_invers = null;
                if(node.data.inverseid>0){
                    node_invers = _findNodeById(node.data.inverseid, false);
                }
                if(!Hul.isnull(node_invers)){ //inversed term found
                    Dom.get('edInverseTermId').value = node_invers.data.id;
                    Dom.get('edInverseTerm').value = getParentLabel(node_invers);
                    Dom.get('btnInverseSetClear').value = 'clear';

                    Dom.get('divInverse').style.display = (node_invers.data.id==node.data.id)?'none':'block';
                    Dom.get('cbInverseTermItself').checked = (node_invers.data.id==node.data.id)?'checked':'';
                    Dom.get('cbInverseTermOther').checked = (node_invers.data.id==node.data.id)?'':'checked';
                }else{
                    node.data.inverseid = null;
                    Dom.get('edInverseTermId').value = '0';
                    Dom.get('edInverseTerm').value = 'click me to select inverse term...';
                    Dom.get('btnInverseSetClear').value = 'set';

                    Dom.get('divInverse').style.display = "none"; //(_currTreeView === _termTree2)?"block":"none";
                    Dom.get('cbInverseTermItself').checked = 'checked';
                    Dom.get('cbInverseTermOther').checked = '';
                }

                if(isExistingNode(node)){
                    Dom.get('div_btnAddChild').style.display = "inline-block";
                    Dom.get('btnDelete').value = "Delete";
                    Dom.get('btnSave').value = "Save changes";
                }else{//new term
                    Dom.get('div_btnAddChild').style.display = "none";
                    Dom.get('btnDelete').value = "Cancel";
                    Dom.get('btnSave').value = "Save term";
                }

                Dom.get('divInverseType').style.display = (_currTreeView === _termTree2)?"block":"none";

                var dbId = Number(top.HEURIST.database.id),
                original_dbId = Number(node.data.original_db),
                status = node.data.status;

                if(Hul.isnull(original_dbId)) {original_dbId = dbId;}

                if((dbId>0) && (dbId<1001) && (original_dbId===dbId)) {
                    add_reserved = true;
                }

                if(status==='reserved'){ //if reserved - it means original dbid<1001

                    disable_status = (original_dbId!==dbId) && (original_dbId>0) && (original_dbId<1001);
                    disable_fields = true;
                    add_reserved = true;

                }else if(status==='approved'){
                    disable_fields = true;
                }


                _optionReserved(add_reserved);
                var selstatus = Dom.get("trm_Status");
                selstatus.value = status;

                _toggleAll(disable_status || disable_fields, disable_status);
            }//node!=null
        }

        Dom.get('formInverse').style.display = "none";
        if(Hul.isnull(node)){
            Dom.get('formEditor').style.display = "none";
            Dom.get('formMessage').style.display = "block";
        }

        if(node){ //obj && obj.event){
            var ind = _tabView.get("activeIndex");
            var tv = (ind===0)?_termTree1:_termTree2;
            tv.onEventToggleHighlight(node);
        }
        
        _isNodeChanged();
    }

    /**
    * adds reserved option to status dropdown list
    */
    function _optionReserved(isAdd){
        var selstatus = Dom.get("trm_Status");
        if(isAdd && selstatus.length<4){
            Hul.addoption(selstatus, "reserved", "reserved");
        }else if (!isAdd && selstatus.length===4){
            selstatus.length=3;
            //selstaus.remove(3);
        }
    }

    /**
    * Toggle fields to disable. Is called when status is set to 'Reserved'.
    */
    function _toggleAll(disable, reserved) {

        Dom.get("trm_Status").disabled = reserved;

        if(disable){
            Dom.get("btnDelete").onclick = _disableWarning;
            Dom.get("btnInverseSetClear").onclick = _disableWarning2;
            Dom.get('edInverseTerm').onclick = _disableWarning2;

            Dom.get("cbInverseTermItself").onclick= _disableWarning2;
            Dom.get("cbInverseTermOther").onclick= _disableWarning2;
        }else{
            Dom.get("btnDelete").onclick = _doDelete;
            Dom.get("btnInverseSetClear").onclick = _setOrclearInverseTermId;
            Dom.get('edInverseTerm').onclick = _setOrclearInverseTermId2;

            Dom.get("cbInverseTermItself").onclick= _onInverseTypeClick;
            Dom.get("cbInverseTermOther").onclick= _onInverseTypeClick;
        }
    }

    /**
    *
    */
    function _onInverseTypeClick(){

        if(Dom.get("cbInverseTermItself").checked){
            Dom.get('edInverseTermId').value = Dom.get('edId').value;
            Dom.get('divInverse').style.display = 'none';
        }else{
            Dom.get('edInverseTermId').value = "0";
            Dom.get('divInverse').style.display = 'block';
            Dom.get('btnInverseSetClear').value = 'set';
            Dom.get('edInverseTerm').value = "";
        }
        _isNodeChanged();
    }

    function _setOrclearInverseTermId2(){
        Dom.get('formInverse').style.display = "block";
        Dom.get('edInverseTerm').value = "";
    }
    
    /**
    * Clear button listener
    */
    function _setOrclearInverseTermId(){
        if(Dom.get('btnInverseSetClear').value==='cancel'){
            Dom.get('btnInverseSetClear').value = (Dom.get('edInverseTermId').value!=="0")?'clear':'set';
            Dom.get('formInverse').style.display = "block";
        }else if(Dom.get('edInverseTermId').value==="0"){
            //show inverse div
            Dom.get('btnInverseSetClear').value = 'cancel';
            Dom.get('formInverse').style.display = "block";
            //clear search result - since some terms may be removed/renamed
            Dom.get('resSearchInverse').innerHTML = "";

        }else{
            Dom.get('btnInverseSetClear').value = 'set';
            Dom.get('formInverse').style.display = "block";
            Dom.get('edInverseTerm').value = "";
            Dom.get('edInverseTermId').value = "0";
        }
    }

    function _showWarning(message){
        var ele = Dom.get('divMessage');
        Dom.get('divMessage-text').innerHTML =  message;
        Hul.popupTinyElement(this, ele);
    }
    function _disableWarning(){
        _showWarning("Sorry, this term is marked as "+Dom.get("trm_Status").value+" and cannot therefore be deleted");
    }
    function _disableWarning2(){
        _showWarning("Sorry, this term is marked as "+Dom.get("trm_Status").value+". Inverse term cannot be set");
    }

    /**
    *
    */
    function _onChangeStatus(event){
        var el = event.target;
        if(el.value === "reserved" || el.value === "approved") {
            _toggleAll(true, false);
        } else {
            _toggleAll(false, false);
        }
        _isNodeChanged();
    }

    /**
    * nodeid - to be merged
    * retain_nodeid - target
    */
    function _doTermMerge(retain_nodeid, nodeid){

        var _dialogbox;

        var _updateResult = function(context){
                if(!Hul.isnull(context) && !context.error){

                        top.HEURIST.terms = context.terms;

                        /*_isSomethingChanged = true;
                        Dom.get('div_btnAddChild').style.display = "inline-block";
                        Dom.get('btnDelete').value = "Delete";
                        Dom.get('btnSave').value = "Save";
                        Dom.get('div_SaveMessage').style.display = "inline-block";
                        setTimeout(function(){Dom.get('div_SaveMessage').style.display = "none";}, 2000);*/

                        var ind = _tabView.get("activeIndex");
                        _fillTreeView((ind===0)?_termTree1:_termTree2);
                }
        };

        var _updateOnServer = function(){

                        var trmLabel = $(top.document).find('input:radio[name="rbMergeLabel"]:checked').val();

                        var retain_nodeid = $(top.document).find('#lblRetainId').html();
                        var nodeid = $(top.document).find('#lblMergeId').html();


                        if(Hul.isempty(trmLabel)){
                            alert('Term label cannot be empty');
                            return;
                        }

                        var oTerms = {terms:{
                            colNames:['trm_Label','trm_Description','trm_Code'],
                            defs: {}
                        }};
                        oTerms.terms.defs[retain_nodeid] = [
                        trmLabel,
                        $(top.document).find('input:radio[name="rbMergeDescr"]:checked').val(),
                        $(top.document).find('input:radio[name="rbMergeCode"]:checked').val() ];

                        var str = YAHOO.lang.JSON.stringify(oTerms);

                        //alert(str);

                        var baseurl = top.HEURIST.baseURL_V3 + "admin/structure/saveStructure.php";
                        var callback = _updateResult;
                        var params = "method=mergeTerms&data=" + encodeURIComponent(str)+"&retain="+retain_nodeid+"&merge="+nodeid+"&db="+_db;
                        Hul.getJsonData(baseurl, callback, params);


            if(_dialogbox) top.HEURIST.util.closePopup(_dialogbox.id);
            _dialogbox = null;
        };


        var termsByDomainLookup = top.HEURIST.terms.termsByDomainLookup[_currentDomain],
                             fi = top.HEURIST.terms.fieldNamesToIndex;

        var arTerm = termsByDomainLookup[nodeid];
        $('#lblTerm_toMerge').html(arTerm[fi.trm_Label]+' ['+arTerm[fi.trm_ConceptID]+']');

        if(Hul.isempty(arTerm[fi.trm_Label])){
            $('#mergeLabel2').hide();
        }else{
            $('#mergeLabel2').show();
            $('#lblMergeLabel2').html(arTerm[fi.trm_Label]);
            $('#rbMergeLabel2').val(arTerm[fi.trm_Label]);
        }
        if(Hul.isempty(arTerm[fi.trm_Code])){
            $('#mergeCode2').hide();
        }else{
            $('#mergeCode2').show();
            $('#lblMergeCode2').html(arTerm[fi.trm_Code]);
            $('#rbMergeCode2').val(arTerm[fi.trm_Code]);
        }
        if(Hul.isempty(arTerm[fi.trm_Description])){
            $('#mergeDescr2').hide();
        }else{
            $('#mergeDescr2').show();
            $('#lblMergeDescr2').html(arTerm[fi.trm_Description]);
            $('#rbMergeDescr2').val(arTerm[fi.trm_Description]);
        }


        var arTerm2 = termsByDomainLookup[retain_nodeid];
        $('#lblTerm_toRetain').html(arTerm2[fi.trm_Label]+' ['+arTerm2[fi.trm_ConceptID]+']');

        if(Hul.isempty(arTerm2[fi.trm_Label])){
            $('#mergeLabel2').hide();
            $('#lblMergeLabel1').html(arTerm[fi.trm_Label]);
            $('#rbMergeLabel1').val(arTerm[fi.trm_Label]);
        }else{
            $('#lblMergeLabel1').html(arTerm2[fi.trm_Label]);
            $('#rbMergeLabel1').val(arTerm2[fi.trm_Label]);
        }
        if(Hul.isempty(arTerm2[fi.trm_Code])){
            $('#mergeCode2').hide();
            $('#lblMergeCode1').html(arTerm[fi.trm_Code]);
            $('#rbMergeCode1').val(arTerm[fi.trm_Code]);
        }else{
            $('#lblMergeCode1').html(arTerm2[fi.trm_Code]);
            $('#rbMergeCode1').val(arTerm2[fi.trm_Code]);
        }
        if(Hul.isempty(arTerm2[fi.trm_Description])){
            $('#mergeDescr2').hide();
            $('#lblMergeDescr1').html(arTerm[fi.trm_Description]);
            $('#rbMergeDescr1').val(arTerm[fi.trm_Description]);
        }else{
            $('#lblMergeDescr1').html(arTerm2[fi.trm_Description]);
            $('#rbMergeDescr1').val(arTerm2[fi.trm_Description]);
        }

        $('#lblRetainId').html(retain_nodeid);
        $('#lblMergeId').html(nodeid);

        //fill elements of con
        var ele = document.getElementById('divTermMergeConfirm');

        $("#btnMergeCancel").click(function(){if(_dialogbox) top.HEURIST.util.closePopup(_dialogbox.id);});
        $("#btnMergeOK").click( _updateOnServer );


        //show confirmation dialog
        _dialogbox = Hul.popupElement(top, ele,
            {
                "close-on-blur": false,
                "no-resize": true,
                title: 'Select values to be retained',
                height: 300,
                width: 400
        });


    }


    /**
    * Saves the term on server side
    */
    function _doSave(needConfirm, noValidation){

        var sName = Dom.get('edName').value.trim().replace(/\s+/g,' ');
        Dom.get('edName').value = sName;
        var sDesc = Dom.get('edDescription').value.trim();
        Dom.get('edDescription').value = sDesc;
        var sCode = Dom.get('edCode').value.trim().replace(/\s+/g,' ');
        Dom.get('edCode').value = sCode;
        var sStatus = Dom.get('trm_Status').value;

        var iInverseId = Number(Dom.get('edInverseTermId').value);
        iInverseId = (Number(iInverseId)>0) ?iInverseId:null;
        var iInverseId_prev = Number(_currentNode.data.inverseid);
        iInverseId_prev = (iInverseId_prev>0)?iInverseId_prev:null;

        var iParentId = Number(Dom.get('edParentId').value);
        iParentId = (Number(iParentId)>0)?iParentId:null;
        var iParentId_prev = Number(_currentNode.data.parent_id);
        iParentId_prev = (iParentId_prev>0)?iParentId_prev:null;

        var wasChanged = ((_currentNode.label !== sName) ||
            (_currentNode.data.description !== sDesc) ||
            (_currentNode.data.termcode !== sCode) ||
            (_currentNode.data.status !== sStatus) ||
            (iParentId_prev !== iParentId) ||
            (iInverseId_prev !== iInverseId));
            
            
        if(wasChanged){
            $('#btnSave').removeProp('disabled');
            $('#btnSave').css('color','black');
        }    

        //( !(Hul.isempty(_currentNode.data.inverseid)&&Hul.isnull(iInverseId)) &&
        //	Number(_currentNode.data.inverseid) !== iInverseId));

        if(wasChanged || !isExistingNode(_currentNode) ){

            var swarn = "";
            if(Hul.isempty(sName)){
                swarn = "Term (label) is mandatory - terms must have a term / label"
            }else {
                //IJ 2014-04-09 swarn = Hul.validateName(sName, "Field 'Display Name'");
            }
            if(swarn!=""){
                alert(swarn);
                Dom.get('edName').setFocus();
                return;
            }
            if(_currTreeView === _termTree2 && Dom.get('cbInverseTermOther').checked && iInverseId==null){
                alert("Please find inverse term, or select non-directional in the radio buttons");
                Dom.get('edInverseTermId').setFocus();
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

            if(noValidation || _validateDups(_currentNode, sName, sCode)){

                var needReload = (_currentNode.data.parent_id != iParentId || _currentNode.data.inverseid != iInverseId);

                _currentNode.label = sName;
                _currentNode.data.description = sDesc;
                _currentNode.data.termcode = sCode;
                _currentNode.data.status = sStatus;

                _currentNode.data.inverseid = iInverseId;
                _currentNode.title = _currentNode.data.description;

                _currentNode.data.parent_id = iParentId;
                            
                _currTreeView.render();

                _updateTermsOnServer(_currentNode, needReload);
                //alert("TODO SAVE ON SERVER");
            }
        }
    }

    /**
    *
    */
    function _validateDups(node, new_name, new_code){

        var sibs = node.getSiblings();
        var ind;
        for (ind in sibs){
            if(!Hul.isnull(ind)){
                if(sibs[ind].label == new_name){
                    alert("Duplicate term '"+new_name+"' - there is already a term with the same label in this branch at this level");
                    return false;
                }
                if(new_code!='' && sibs[ind].data.termcode == new_code){
                    alert("There is already a term with the standard code '"+new_code+"' in this branch");
                    return false;
                }
            }
        }
        return true;
    }

    function _getNextSiblingId(node){
        var sibs = node.getSiblings();
        if(sibs!=null){
            var ind, len = sibs.length;
            for (ind in sibs){
                if(!Hul.isnull(ind)){
                    if(sibs[ind].data.id==node.data.id && ind+1<len){
                        return sibs[ind+1].data.id
                    }
                }
            }
        }
        return null;
    }


    /**
    * Sends data to server
    */
    function _updateTermsOnServer(node, _needReload)
    {

        var term = node.data;

        /*
        var sibs = node.getSiblings();
        var ind;
        for (ind in sibs){
        if(!Hul.isnull(ind)){
        if(sibs[ind].label == node.label){
        alert("There is already the term with the same label in this branch");
        return;
        }
        if(sibs[ind].data.termcode == node.data.termcode){
        alert("There is already the term with the same code in this branch");
        return;
        }
        }
        }
        */


        var needReload = _needReload;

        var oTerms = {terms:{
            colNames:['trm_Label','trm_InverseTermId','trm_Description','trm_Domain','trm_ParentTermID','trm_Status','trm_Code'],
            defs: {}
        }};
        oTerms.terms.defs[term.id] = [node.label, term.inverseid, term.description, term.domain, term.parent_id, term.status, term.termcode ];

        var str = YAHOO.lang.JSON.stringify(oTerms);

        if(!Hul.isnull(str)) {

            var _updateResult = function(context){

                if(!Hul.isnull(context)){

                    var error = false,
                    report = "",
                    ind;

                    for(ind in context.result)
                    {
                        if(!Hul.isnull(ind)){
                            var item = context.result[ind];
                            if(isNaN(item)){
                                Hul.showError(item);
                                error = true;
                            }else{

                                if(node.data.id != item){ //new term
                                    node.data.id = item;
                                    //find vocab term (top level)
                                    //top.HEURIST.treesByDomain
                                    var topnode = _findTopLevelForId(node.data.parent_id);
                                    if (!Hul.isnull(topnode)) {
                                        var vocab_id = topnode.data.id;
                                        if(_affectedVocabs.indexOf(vocab_id)<0){
                                            _affectedVocabs.push(vocab_id);
                                            _showFieldUpdater();
                                            /*if(_affectedVocabs.length===1){
                                            Dom.get('formAffected').style.display = 'block';
                                            }*/
                                        }
                                    }
                                }

                                /*update inverse term
                                var trm0 = context.terms['termsByDomainLookup']['relation'][node.data.id]; //get term by id
                                if(trm0){ //if found - this is relation term
                                    //get inverse id
                                    node.data.inverseid = Number(trm0[context.terms['fieldNamesToIndex']['trm_InverseTermID']]);
                                    //set inverse id for other term
                                    if(node.data.inverseid>0){
                                        var node2 = _findNodeById(node.data.inverseid, false);
                                        if(node2)
                                            node2.data.inverseid = node.data.id;
                                    }
                                }*/



                                //update ID field
                                if(_currentNode ===  node){
                                    Dom.get('edId').value = item;
                                }
                                if(_parentNode){
                                    _onNodeClick(_parentNode);
                                }
                            }
                        }
                    }//for

                    if(!error) {
                        top.HEURIST.terms = context.terms;

                        _isSomethingChanged = true;
                        Dom.get('div_btnAddChild').style.display = "inline-block";
                        Dom.get('btnDelete').value = "Delete";
                        Dom.get('btnSave').value = "Save";
                        Dom.get('div_SaveMessage').style.display = "inline-block";
                        setTimeout(function(){Dom.get('div_SaveMessage').style.display = "none";}, 2000);
                        //alert("Term was succesfully saved");

                        if(needReload){
                            var ind = _tabView.get("activeIndex");
                            _fillTreeView((ind===0)?_termTree1:_termTree2);
                        }
                    }
                }
            };

            //
            var baseurl = top.HEURIST.baseURL_V3 + "admin/structure/saveStructure.php";
            var callback = _updateResult;
            var params = "method=saveTerms&data=" + encodeURIComponent(str)+"&db="+_db;
            Hul.getJsonData(baseurl, callback, params);
        }
    }

    /**
    * new of existing node
    */
    function isExistingNode(node){
        return ((typeof node.data.id === "number") || node.data.id.indexOf("-")<0);
    }

    /**
    * Deletes current term
    */
    function _doDelete(needConfirm){
        if(_currentNode===null) return;
        var isExistingTerm = isExistingNode(_currentNode),
        sMessage = "";

        if(isExistingTerm){
            sMessage = "Delete term '"+_currentNode.label+"'?";
            if(_currentNode.children.length>0){
                sMessage = sMessage + "\nWarning: All child terms will be deleted as well";
            }
        }else{
            sMessage = "Cancel the addition of new term?";
        }


        var r = (!needConfirm) || confirm(sMessage);

        if (r && !Hul.isnull(_currTreeView)) {
            //_currTreeView.removeChildren(_currentNode);

            if(isExistingTerm){
                //this term exists in database - delete it
                Dom.get('deleteMessage').style.display = "block";
                Dom.get('formEditor').style.display = "none";

                function __updateAfterDelete(context) {

                    if(!Hul.isnull(context) && !context['error']){

                            top.HEURIST.terms = context.terms;
                            //remove from tree
                            _currTreeView.popNode(_currentNode);
                            _currTreeView.render();
                            _currentNode = null;
                            _onNodeClick(null);
                            _isSomethingChanged = true;
                    }else{
                        Dom.get('formEditor').style.display = "block";
                    }
                    Dom.get('deleteMessage').style.display = "none";
                }

                var baseurl = top.HEURIST.baseURL_V3 + "admin/structure/saveStructure.php";
                var callback = __updateAfterDelete;
                var params = "method=deleteTerms&trmID=" + _currentNode.data.id+"&db="+_db;
                Hul.getJsonData(baseurl, callback, params);
            }else{
                _currTreeView.popNode(_currentNode);
                _currTreeView.render();
                _currentNode = null;
                _onNodeClick(null);
                Dom.get('deleteMessage').style.display = "none";
            }
        }
    }

    /**
    * Open popup and select new parent term
    */
    function _selectParent(){

        if(_currentNode===null) return;

        var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));
        var nodeid = _currentNode.data.id;

        //_keepCurrentParent = _getNextSiblingId(_currentNode);
        //if(_keepCurrentParent==null)
        _keepCurrentParent = _currentNode.data.parent_id;


        var url = top.HEURIST.baseURL_V3 +
            "admin/structure/terms/selectTermParent.html?domain="+_currentDomain+"&child="+nodeid+"&mode=0&db="+db;
        if(keep_target_newparent_id){
            url = url + "&parent=" + keep_target_newparent_id;
        }

        Hul.popupURL(top, url,
            {
                "close-on-blur": false,
                "no-resize": true,
                height: 500,
                width: 450,
                callback: function(newparent_id) {
                    if(newparent_id) {
                        if(newparent_id === "root") {
                            Dom.get('edParentId').value = "";
                        }else{
                            Dom.get('edParentId').value = newparent_id;
                        }
                        _doSave(false, true);

                        keep_target_newparent_id = newparent_id;

                        /*//reselct the edited node
                        var node = _findNodeById(nodeid, true);
                        if(!Hul.isnull(node)){
                            _onNodeClick(node);
                        }*/
                        return true;
                    }
                }
        });



    }
    
    function _getTermLabel(domain, term_id){
        var trm = top.HEURIST.terms.termsByDomainLookup[domain][term_id];
        return (trm)?trm[top.HEURIST.terms.fieldNamesToIndex.trm_Label]:'';
    }

    /**
    * Open popup and select term to be merged
    */
    function _mergeTerms(){

        if(_currentNode===null) return;

        var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));
        var retain_nodeid = _currentNode.data.id;

        //_keepCurrentParent = _getNextSiblingId(_currentNode);
        //if(_keepCurrentParent==null)
        _keepCurrentParent = _currentNode.data.parent_id;


        var url = top.HEURIST.baseURL_V3 +
            "admin/structure/terms/selectTermParent.html?domain="+_currentDomain+"&child="+retain_nodeid+"&mode=1&db="+db;
        /*if(keep_target_newparent_id){
            url = url + "&parent=" + keep_target_newparent_id;
        }*/
        
        var name_with_path = _getTermLabel(_currentDomain, _keepCurrentParent) + '/' 
                                + _getTermLabel(_currentDomain, retain_nodeid);

        Hul.popupURL(top, url,
            {
                "close-on-blur": false,
                "no-resize": true,
                title: 'Merge into: '+name_with_path,
                height: 500,
                width: 450,
                callback: function(merge_nodeid) {
                    if(merge_nodeid && merge_nodeid !== "root") {

                        _doTermMerge(retain_nodeid, merge_nodeid);

                        return true;
                    }
                }
        });

    }


    /**
    * Adds new child term for current term or adds new root term
    */
    function _doAddChild(isRoot, value)
    {
        var term;
        if(value==null){
            if (isRoot){
                value = {id:null,label:"",desription:""}; //new term [vocab]
            } else {
                value = {id:null,label:"new term",desription:""}; //
            }
        }

        if(isRoot){

            var rootNode = _currTreeView.getRoot();

            term = {}; //new Object();
            term.id = (value.id)?value.id:"0-" + (rootNode.getNodeCount()); //correct
            term.parent_id = null;
            term.conceptid = null;
            term.domain = _currentDomain;
            term.label = value.label;
            term.description = value.description;
            term.termcode = value.termcode;
            term.inverseid = null;

            rootNode = new YAHOO.widget.TextNode(term, rootNode, false); // Create root node
            _currTreeView.render();

            rootNode.focus(); //expand

            _onNodeClick(rootNode);

        }else if(!Hul.isnull(_currentNode))
        {
            term = {}; //new Object();
            term.id = (value.id)?value.id:_currentNode.data.id+"-" + (_currentNode.getNodeCount());  //correct
            term.parent_id = _currentNode.data.id;
            term.conceptid = null;
            term.domain = _currentDomain;
            term.label = value.label;
            term.description = value.description;
            term.termcode = value.termcode;
            term.inverseid = null;

            var newNode = new YAHOO.widget.TextNode(term, _currentNode, false);
            _currTreeView.render();

            var _temp = _currentNode;

            newNode.focus(); //expand

            _onNodeClick(newNode);

            _parentNode = _temp;
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
            if(false && _currentNode.data.id===nodeid){
                alert("Not possible to inverse on itself");
            }else{
                Dom.get('edInverseTerm').value = sel.options[sel.selectedIndex].text;
                Dom.get('edInverseTermId').value = nodeid;
                Dom.get('btnInverseSetClear').value = 'clear';
                Dom.get('formInverse').style.display = "none";
            }
        }
    }

    //
    // export vocabulary as human readable list
    //    
    function _export(isRoot){
        
        if(!Hul.isnull(_currentNode)){
            
            var term_ID = 0;
            if(_currentNode.children && _currentNode.children.length>0){
                term_ID = _currentNode.data.id;
            }else{
                term_ID = _currentNode.data.parent_id;
            }
            
            var sURL = top.HEURIST.baseURL_V3 + "admin/structure/terms/printVocabulary.php?db="+ _db 
                + '&domain=' + _currentDomain + '&trm_ID=' + term_ID;

            window.open(sURL, '_blank');
        }
        
    }

    /**
    * invokes popup to import list of terms from file
    */
    function _import(isRoot) {

        if(isRoot || !Hul.isnull(_currentNode)){

            var term_id = (isRoot)?0:_currentNode.data.id;
            var term_label = (isRoot)?'root vocabulary':_currentNode.label;
            
            /* old way
            var sURL = top.HEURIST.baseURL_V3 + "admin/structure/terms/editTermsImport.php?db="+ _db +
            "&parent="+term_id+
            "&domain="+_currentDomain;
            */

            var sURL = top.HEURIST.baseURL_V3 + "hclient/framecontent/import/importDefTerms.php?db="+ _db +
                        "&trm_ID="+term_id;
            
            Hul.popupURL(top, sURL, {
                "close-on-blur": false,
                "no-resize": false,
                title: 'Import Terms for '+term_label,
                //height: 200,
                //width: 500,
                height: 460,
                width: 800,
                'context_help':top.HEURIST.baseURL_V3+'context_help/defTerms.html #import',
                callback: _import_complete
            });
            
        }
    }

    /**
    *  Add the list of imported terms
    */
    function _import_complete(context){
        if(!Hul.isnull(context) && !Hul.isnull(context.terms))
        {
            
            if(top.HEURIST4 && top.HEURIST4.msg){
            top.HEURIST4.msg.showMsgDlg(context.result.length
                                + ' term'
                                + (context.result.length>1?'s were':' was')
                                + ' added.', null, 'Terms imported');
            }
            
            
            top.HEURIST.terms = context.terms;
            var res = context.result,
            ind,
            parentNode = (context.parent===0)?_currTreeView.getRoot():_currentNode,
            fi = top.HEURIST.terms.fieldNamesToIndex;

            if(res.length>0){

                for (ind in res)
                {
                    if(!Hul.isnull(ind)){
                        var termid = Number(res[ind]);

                        if(!isNaN(termid))
                        {
                            var arTerm = top.HEURIST.terms.termsByDomainLookup[_currentDomain][termid];
                            if(!Hul.isnull(arTerm)){
                                var term = {}; //new Object();
                                term.id = termid;
                                term.label = arTerm[fi.trm_Label];
                                term.conceptid = null;
                                term.description = arTerm[fi.trm_Description];
                                term.termcode = "";
                                term.parent_id = context.parent; //_currentNode.data.id;
                                term.domain = _currentDomain;
                                term.inverseid = null;

                                var newNode = new YAHOO.widget.TextNode(term, parentNode, false);
                            }
                        }
                    }
                }//for
                _currTreeView.render();
                parentNode.focus(); //expand
                /*var _temp = _currentNode;
                _onNodeClick(_currentNode);
                _parentNode = _temp;
                */
            }//if length>0
        }
    }

    //
    function _preventSel(event){
        event.target.selectedIndex=0;
    }

    //show pop - to update affected field type
    function _showFieldUpdater(){

        //_affectedVocabs

        //1. find the affected fieldtypes
        var fi = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex,
        allTerms = top.HEURIST.terms.treesByDomain[_currentDomain],
        dty_ID, ind, td, deftype,
        arrRes = [],
        _needtype = _currentDomain==='enum'?'enum':'relationtype';   //relmarker???


        //
        // internal function to loop through all field terms tree
        // if one of term has parent that is in affectedVocabs
        //
        function __loopForFieldTerms(parentNode){

            for(child in parentNode)
            {
                if(!Hul.isnull(child)){

                    var topnode = _findTopLevelForId(child);
                    if (!Hul.isnull(topnode)) {
                        var vocab_id = topnode.data.id;
                        if(_affectedVocabs.indexOf(vocab_id)>=0){
                            return true;
                        }
                    }

                    var newparentNode = parentNode[child];
                    if(__loopForFieldTerms(newparentNode)){
                        return true;
                    }
                }
            }//for

            return false;
        }

        //2. loop
        for (dty_ID in top.HEURIST.detailTypes.typedefs) {

            if(!isNaN(Number(dty_ID)))
            {
                td = top.HEURIST.detailTypes.typedefs[dty_ID];
                deftype = td.commonFields;

                if(deftype[fi["dty_Type"]]===_needtype){

                    var fldTerms = Hul.expandJsonStructure(deftype[fi["dty_JsonTermIDTree"]]);
                    //check if one these terms are child of affected vocab
                    if(!Hul.isnull(fldTerms)) {

                        if(__loopForFieldTerms(fldTerms)){
                            arrRes.push(dty_ID);
                        }
                    }
                }
            }
        }//for


        //3. show list of affected field types
        if(arrRes.length==0){

            var parent = Dom.get('formEditFields');
            parent.innerHTML = ""; //"<h2>No affected field types found</h2>";

            Dom.get('formAffected').style.display = 'none';

        }else{
            //debug alert(arrRes.join(","));

            var parent = Dom.get('formEditFields');
            parent.innerHTML = "Term(s) have been added to the term tree but this does not add them to the individual trees for different fields," +
            " since these are individually selected from the complete term tree. Please update the lists for each field to which these terms should be added." +
            "<p>The fields most likely to be affected are:</p>";

            for(ind=0; ind<arrRes.length; ind++){

                dty_ID = arrRes[ind];
                td = top.HEURIST.detailTypes.typedefs[dty_ID];
                deftype = td.commonFields;

                var _div = document.createElement("div");
                _div.innerHTML = '<div class="dtyLabel">'+deftype[fi.dty_Name]+':</div>'+
                '<div class="dtyField">'+
                '<input type="submit" value="Change vocabulary" id="btnST_'+dty_ID+'" onClick="window.editTerms.onSelectTerms(event)"/>&nbsp;&nbsp;'+
                '</div>';
                //'<span class="dtyValue" id="termsPreview"><span>preview:</span></span></div>'

                _recreateSelector(dty_ID, deftype[fi.dty_JsonTermIDTree], deftype[fi.dty_TermIDTreeNonSelectableIDs], _div);

                parent.appendChild(_div);
            }

        }

    }

    function _recreateSelector(dty_ID, allTerms, disabledTerms, parentdiv)
    {
        //
        var el_sel = Dom.get("selector"+dty_ID);
        if(el_sel){
            parentdiv = el_sel.parentNode;
            parentdiv.removeChild(el_sel);
        }

        allTerms = Hul.expandJsonStructure(allTerms);
        disabledTerms = Hul.expandJsonStructure(disabledTerms);

        el_sel = Hul.createTermSelect(allTerms, disabledTerms, _currentDomain, null);
        el_sel.id = "selector"+dty_ID;
        el_sel.style.backgroundColor = "#cccccc";
        el_sel.width = 180;
        el_sel.style.maxWidth = '180px';
        el_sel.onchange =  _preventSel;
        parentdiv.appendChild(el_sel);
    }

    /**
    * onSelectTerms
    *
    * listener of "Change vocabulary" button
    * Shows a popup window where user can select terms to create a term tree as wanted
    */
    function _onSelectTerms(event){

        var dty_ID = event.target.id.substr(6);
        var td = top.HEURIST.detailTypes.typedefs[dty_ID],
        deftype = td.commonFields,
        fi = top.HEURIST.detailTypes.typedefs.fieldNamesToIndex;

        var type = deftype[fi.dty_Type];
        var allTerms = deftype[fi.dty_JsonTermIDTree];
        var disTerms = deftype[fi.dty_TermIDTreeNonSelectableIDs];
        var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));

        Hul.popupURL(top, top.HEURIST.baseURL_V3 +
            "admin/structure/terms/selectTerms.html?dtname="+dty_ID+"&datatype="+type+"&all="+allTerms+"&dis="+disTerms+"&db="+db,
            {
                "close-on-blur": false,
                "no-resize": true,
                height: 500,
                width: 750,
                callback: function(editedTermTree, editedDisabledTerms) {
                    if(editedTermTree || editedDisabledTerms) {

                        //Save update vocabulary on server side
                        var _oDetailType = {detailtype:{
                            colNames:{common:['dty_JsonTermIDTree','dty_TermIDTreeNonSelectableIDs']},
                            defs: {}
                        }};

                        _oDetailType.detailtype.defs[dty_ID] = {common:[editedTermTree, editedDisabledTerms]};

                        var str = YAHOO.lang.JSON.stringify(_oDetailType);

                        function _updateResult(context) {

                            if(!Hul.isnull(context)){

                                /* @todo move this to the separate function */
                                var error = false,
                                report = "",
                                ind;

                                for(ind in context.result)
                                {
                                    if(!Hul.isnull(ind)){
                                        var item = context.result[ind];
                                        if(isNaN(item)){
                                            Hul.showError(item);
                                            error = true;
                                        }else{
                                            detailTypeID = Number(item);
                                            if(!Hul.isempty(report)) { report = report + ","; }
                                            report = report + detailTypeID;
                                        }
                                    }
                                }

                                if(!error) {
                                    //recreate selector
                                    deftype[fi.dty_JsonTermIDTree] = editedTermTree;
                                    deftype[fi.dty_TermIDTreeNonSelectableIDs] = editedDisabledTerms;
                                    _recreateSelector(dty_ID, editedTermTree, editedDisabledTerms, null);
                                }
                            }
                        }//end internal function

                        if(!Hul.isnull(str)) {

                            var baseurl = top.HEURIST.baseURL_V3 + "admin/structure/saveStructure.php";
                            var callback = _updateResult;
                            var params = "method=saveDT&db="+db+"&data=" + encodeURIComponent(str);
                            Hul.getJsonData(baseurl, callback, params);
                        }

                    }
                }
        });

    }
    
    function _clearImage(){
        
        if(_currentNode===null) return;
        
        var baseurl = top.HEURIST.iconBaseURL + _currentNode.data.id + "&ent=term&deletemode=1";
        Hul.getJsonData(baseurl, function(context){
            if(!Hul.isnull(context) && !context.error){
                 if(context.res=='ok'){
                    $('#termImage').find('img').prop('src', top.HEURIST.baseURL_V3 + 'hclient/assets/100x100click.png'); 
                 }
            }
        }, null);
        
    }

    function _initFileUploader(){
        
            var $input = $('#new_term_image');
            var $input_img = $('#termImage');
        
            $input.fileupload({
    url: top.HEURIST.baseURL_V3 +  'hserver/utilities/fileUpload.php', 
    formData: [ {name:'db', value: _db}, 
                {name:'entity', value:'terms'},
                {name:'newfilename', value: Dom.get('edId').value }],
    acceptFileTypes: /(\.|\/)(gif|jpe?g|png)$/i,
    autoUpload: true,
    sequentialUploads:true,
    dataType: 'json',
    //dropZone: $input_img,
    add: function (e, data) {  
        $input_img.addClass('loading');
        $input_img.find('img').hide();
        data.submit(); 
    },    
    done: function (e, response) {
                $input_img.removeClass('loading');
                $input_img.find('img').show();
                response = response.result;
                if(response.status=='ok'){
                    var data = response.data;
                    $.each(data.files, function (index, file) {
                        if(file.error){
                            $input_img.find('img').prop('src', '');
                            //top.HEURIST4.msg.showMsgErr(file.error);
                        }else{
                            var curtimestamp = (new Date()).getMilliseconds();
                            var url = top.HEURIST.iconBaseURL + Dom.get('edId').value+ "&ent=term&t=" + curtimestamp
                            $input_img.find('img').prop('src', url); //file.url);
                        }
                    });
                }
            }                    
                        });
        
    }

    //
    //public members
    //
    var that = {

        doSave: function(){
            _keepCurrentParent = null;
            _doSave(false, false);
        },
        doDelete: function(){ _doDelete(true); },
        doAddChild: function(isRoot){ _doAddChild(isRoot); },
        selectParent: function(){ _selectParent(); },
        mergeTerms: function(){ _mergeTerms(); },
        onChangeStatus: function(event){ _onChangeStatus(event); },

        findNodes: function(sSearch){ return _findNodes(sSearch); },
        doEdit: function(){ _doEdit(); },
        doSelectInverse: function(){ _doSelectInverse(); },

        clearImage: function(){ _clearImage()},

        doImport: function(isRoot){ _import(isRoot); },
        doExport: function(isRoot){ _export(isRoot); },

        isChanged: function(){
            _isNodeChanged();
        },
        
        applyChanges: function(event){ //for window mode only
            if(_isWindowMode){
                window.close(_isSomethingChanged);
                //Hul.closePopup.apply(this, [_isSomethingChanged]);
            }
        },

        showFieldUpdater: function(){
            _showFieldUpdater();
        },

        showFileUploader: function(){
            
            var $input = $('#new_term_image');
            $input.fileupload('option','formData',
                [ {name:'db', value: _db}, 
                {name:'entity', value:'terms'},
                {name:'newfilename', value: Dom.get('edId').value }]);
    
            $input.click();
            
        },
        
        getClass: function () {
            return _className;
        },

        onSelectTerms : function(event){ _onSelectTerms(event); },

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

                var option = Hul.addoption(sel, node.data.id, getParentLabel(node));
                option.title = option.text;
            }
        }//for

    }
}
