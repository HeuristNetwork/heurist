
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


var TAB_WIDTH = 350;
// EditTerms object
var editTerms;

/**
* create composed label with all parents
*/
function getParentLabel(_node){
    //if(_node.parent._type==="RootNode"){
    if(_node.parent.isRootNode()){
        return _node.data.label;
    }else{
        return getParentLabel(_node.parent)+" > "+_node.data.label;
    }
}

//
// get vocabulary id - top most terms
//
function getVocabId(_node){
    //if(_node.parent._type==="RootNode"){
    if(_node.getLevel()==1){
        return _node.data.id;
    }else{
        return getVocabId(_node.parent);
    }
}

function getParentId(_node){
    //if(_node.parent._type==="RootNode"){
    if(_node.getLevel()==1){
        return _node.data.id;
    }else{
        return _node.data.parent_id;
    }
}

//aliases
var Hul = top.HEURIST.util;

/**
* EditTerms - class for pop-up window to edit terms
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2011.0504
*/
function EditTerms() {

    //private members
    var _className = "EditTerms",
    _currentNode,
    _keepCurrentParent = null,
    _vocabulary_toselect,
    _parentNode,
    _currentDomain,
    treetype = null,
    _db,
    _isWindowMode=false,
    _isSomethingChanged=false,
    _affectedVocabs = [],
    keep_target_newparent_id = null,
    $top_ele,
    $treediv;



    /**
    *    Initialization of tabview with 2 tabs with treeviews
    */
    function _init (){



        top.HEURIST.parameters = top.HEURIST.parseParams(location.search);

        _isWindowMode = !Hul.isnull(top.HEURIST.parameters.popup);

        _vocabulary_toselect = top.HEURIST.parameters.vocabid;

        treetype = top.HEURIST.parameters.treetype;
        if (!top.HEURIST.util.isempty(treetype)){
            treetype = (treetype == 'enum')?'terms':'relationships';
        }

        var initdomain = 0;

        if(top.HEURIST.parameters.domain=='relation' && top.HEURIST.util.isempty(treetype)) {
            if(top.HEURIST.util.isempty(treetype)) initdomain  = 1;
        }

        _db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));

        if (!(treetype == "terms" || treetype == "relationships")){
            treetype = "terms";
        } 
        
        if (treetype == "terms")
        {
                _currentDomain = "enum";
                $("#divBanner h2").text("Manage terms used by term-list (category/dropdown) fields");
        }else if (treetype == "relationships")
        {
                _currentDomain = "relation";
                $("#divBanner h2").html("Manage terms used for relationship type in relationship records");
        }

        /*
        $('<div style="height:90%; max-width:'+TAB_WIDTH+'; overflow:hidden">'
        +'<div id="termTree" class="termTree" style="height:100%;width:100%;overflow:hidden"></div></div>').appendTo($('#pnlLeft'));
        */
            
        _defineContentTreeView();
        

        var dv1 = $('#divApply');
        var dv2 = $('#divBanner');
        var dv3 = $('.ent_content_full');//'#page-inner');
        if(_isWindowMode){
            dv1.show();
            dv2.hide();
            dv3.css({top:'0'});
            window.close = that.applyChanges;

        }else{
            dv1.hide();
            dv2.show();
        }

        _initFileUploader();
        
        $('#edName').click(function(){
           if( $('#edName').val()=="new term") edName.select(); 
        });

    }

    //
    // find node(s) by name in current treeview
    // it returns the array of nodes
    //
    function _findNodes(sSearch) {

        function __doSearchByName(node){
            return (node && node.data.label && (node.data.label.toLowerCase().indexOf(sSearch)>=0));
        }
        sSearch = sSearch.toLowerCase();
        var tree = $treediv.fancytree("getTree");
        var nodes=[];

        tree.visit(function(node){
            if(__doSearchByName(node))
                nodes.push(node);

        });

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

        var tree = $treediv.fancytree("getTree");
        var nodes=[];

        tree.visit(function(node){
            if(__doSearchById(node))
                nodes.push(node);

        });


        //select and expand the node
        if(nodes){
            var node = nodes[0];
            if(needExpand) {
                //node.focus();
                //node.toggle();
                
                node.setFocus(true);
                node.setActive(true);
                node.setExpanded(true);
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
            
            var termName = $('#edName').val().trim();

            var iInverseId = Number($('#edInverseTermId').val());
            iInverseId = (Number(iInverseId)>0) ?iInverseId:null;
            var iInverseId_prev = Number(_currentNode.data.inverseid);
            iInverseId_prev = (iInverseId_prev>0)?iInverseId_prev:null;
            
            ischanged =
            termName != _currentNode.data.label ||
            $('#edDescription').val().trim() != _currentNode.data.description ||
            $('#edCode').val().trim() != _currentNode.data.termcode ||
            $('#trm_Status').val() != _currentNode.data.status ||
            iInverseId != iInverseId_prev;
        }

        var ele = $('#btnSave');
        if (ischanged && termName!='') {
            //ele.prop('disabled', 'disabled');
            //ele.css('color','lightgray');
            ele.removeProp('disabled');
            $(ele).removeClass('save-disabled');
        }else{
            ele.prop('disabled', 'disabled');
            $(ele).addClass('save-disabled');
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
            // alert(_currentNode.data.label);
            var disable_status = false,
            disable_fields = false,
            add_reserved = false,
            status = 'open';

            if(!Hul.isnull(node)){
                $('#formMessage').hide();
                $("#btnMerge").css("display", "none");
                if (node.isActive())

                {
                    $('#formEditor').show();
                }

                if (Hul.isempty( node.data.conceptid)){
                    $('#div_ConceptID').empty();
                }else{
                    $('#div_ConceptID').html('Concept ID: '+node.data.conceptid);
                }


                //    alert("label was clicked"+ node.data.id+"  "+node.data.domain+"  "+node.label);
                $('#edId').val(node.data.id);
                $('#edParentId').val(node.data.parent_id>0?node.data.parent_id:0);
                var edName = $('#edName');
                edName.val(node.data.label);
                /*if(node.data.label==="new term"){
                    //highlight all text
                    edName.selectionStart = 0;
                    edName.selectionEnd = 8;
                }
                */
                edName.focus();
                if(node.data.label==="new term") edName.select();
                if(Hul.isnull(node.data.description)) {
                    node.data.description="";
                }
                if(Hul.isnull(node.data.termcode)) {
                    node.data.termcode="";
                }
                $('#edDescription').val(node.data.description);
                $('#edCode').val(node.data.termcode);

                //image
                if(node.data.id>0){
                    var curtimestamp = (new Date()).getMilliseconds();
                    $('#termImage').html(
                    '<a href="javascript:void(0)" onClick="{editTerms.showFileUploader()}" title="Click to change image">'+
                    '<img id="imgThumb" style="max-width: 380px;" src="'+
                    top.HEURIST.iconBaseURL + node.data.id + "&ent=term&editmode=1&t=" + curtimestamp +
                    '"></a>');
                    $('#termImage').css({display:'inline-block'});
                    $('#btnClearImage').css({display:'inline-block'});
                    $('#termImageForNew').hide();
                }else{
                    $('#termImage').hide();
                    $('#btnClearImage').hide();
                    $('#termImageForNew').css({display:'inline-block'});
                }


                var node_invers = null;
                if(node.data.inverseid>0){
                    node_invers = _findNodeById(node.data.inverseid, false);
                }
                if(!Hul.isnull(node_invers)){ //inversed term found
                    $('#edInverseTermId').val(node_invers.data.id);
                    $('#edInverseTerm').val(getParentLabel(node_invers));
                    $('#btnInverseSetClear').val('clear');

                    $('#divInverse').css({display:(node_invers.data.id==node.data.id)?'none':'block'});
                    $('#cbInverseTermItself').prop('checked',(node_invers.data.id==node.data.id));
                    $('#cbInverseTermOther').prop('checked',!(node_invers.data.id==node.data.id));
                }else{
                    node.data.inverseid = null;
                    $('#edInverseTermId').val('0');
                    $('#edInverseTerm').val('click me to select inverse term...');
                    $('#btnInverseSetClear').val('set');
                    $('#divInverse').hide();
                    $('#cbInverseTermItself').prop('checked',true);
                    $('#cbInverseTermOther').prop('checked',false);
                }

                if(isExistingNode(node)){
                    $('#div_btnAddChild').css({display:'inline-block'});
                    $('#btnDelete').val("Delete");
                    $('#btnSave').val("Save changes");
                }else{//new term
                    $('#div_btnAddChild').hide()
                    $('#btnDelete').val("Cancel");
                    $('#btnSave').val("Save term");
                }

                $('#divInverseType').css({display: (_currentDomain=="relation")?"block":"none"});

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
                $("#trm_Status").val(status);

                _toggleAll(disable_status || disable_fields, disable_status);
            }//node!=null
        }

        $('#formInverse').hide();
        if(Hul.isnull(node)){
            $('#formEditor').hide();
            $('#formMessage').show();

        }
    }

    /**
    * adds reserved option to status dropdown list
    */
    function _optionReserved(isAdd){
        var selstatus = $("#trm_Status")[0];
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

        document.getElementById ("trm_Status").disabled = reserved;

        if(disable){
            document.getElementById ("btnDelete").onclick = _disableWarning;
            document.getElementById ("btnInverseSetClear").onclick = _disableWarning2;
            document.getElementById ('edInverseTerm').onclick = _disableWarning2;

            document.getElementById ("cbInverseTermItself").onclick= _disableWarning2;
            document.getElementById ("cbInverseTermOther").onclick= _disableWarning2;
        }else{
            document.getElementById ("btnDelete").onclick = _doDelete;
            document.getElementById ("btnInverseSetClear").onclick = _setOrclearInverseTermId;
            document.getElementById ('edInverseTerm').onclick = _setOrclearInverseTermId2;

            document.getElementById ("cbInverseTermItself").onclick= _onInverseTypeClick;
            document.getElementById ("cbInverseTermOther").onclick= _onInverseTypeClick;
        }
    }

    /**
    *
    */
    function _onInverseTypeClick(){

        if(document.getElementById ("cbInverseTermItself").checked){
            document.getElementById ('edInverseTermId').value = $('#edId').val();
            document.getElementById ('divInverse').style.display = 'none';
        }else{
            document.getElementById ('edInverseTermId').value = "0";
            document.getElementById ('divInverse').style.display = 'block';
            document.getElementById ('btnInverseSetClear').value = 'set';
            document.getElementById ('edInverseTerm').value = "";
        }
        _isNodeChanged();
    }

    function _setOrclearInverseTermId2(){

        function _doSearch(event){

            var el = event.target;

            var sel = $(top.document).find("#resSearchInverse").get(0); //(el.id === 'edSearch')? $(top.document).get('resSearch'):$
            //var sel = (el.id === 'edSearch')?Dom.get('resSearch'):Dom.get('resSearchInverse');

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


        function _SelectInverse(){
            var sel = $(top.document).find('#resSearchInverse').get(0);
            if(sel.selectedIndex>=0 && !Hul.isnull(_currentNode) ){

                var nodeid = sel.options[sel.selectedIndex].value;
                var node = _findNodeById(nodeid, false);
                if(false && _currentNode.data.id===nodeid){
                    alert("Not possible for a term to be inverse of itself");
                }
                else if(_currentNode === _findTopLevelForId(_currentNode.data.id))
                {
                    alert("you can't set inverse on top level vocabulary");

                }
                else if( node === _findTopLevelForId(nodeid) ){
                    alert("you can't make top level vocabulary an inverse");
                }else{

                    document.getElementById ('edInverseTerm').value = sel.options[sel.selectedIndex].text;
                    document.getElementById ('edInverseTermId').value = nodeid;
                    document.getElementById ('btnInverseSetClear').value = 'clear';
                    //document.getElementById ('formInverse').style.display = "none";
                    _doSave(true, false);

                }
            }


        }

        var $_dialogbox = "";
        var ele = document.getElementById('formInverse');
        //var _originalParentNode ;



        $("#edSearchInverse").keyup(function(event){_doSearch(event)});
        $("#btnSelect2").click(function(){
            _SelectInverse();
            $_dialogbox.dialog($_dialogbox ).dialog('close');

            //ele.style.display = "none";
            //_originalParentNode.appendChild(ele)

        });
        //$("#resSearchInverse").dblclick(_SelectInverse());
        //show confirmation dialog
        $_dialogbox = Hul.popupElement(top, ele,
            {
                "close-on-blur": false,
                "no-resize": true,
                title: 'Set As Inverse',
                height: 310,
                width: 560
        });

        // _originalParentNode  = ele.parentNode;
    }

    /**
    * Clear button listener
    */
    function _setOrclearInverseTermId(){
        if(document.getElementById ('btnInverseSetClear').value==='cancel'){
            document.getElementById ('btnInverseSetClear').value = (document.getElementById ('edInverseTermId').value!=="0")?'clear':'set';
            //document.getElementById ('formInverse').style.display = "block"; Popup is being used instead rather than displaying in the current Html page
            document.getElementById ('formInverse').style.display = "none";
            _setOrclearInverseTermId2()
        }else if(document.getElementById ('edInverseTermId').value==="0"){
            //show inverse div
            document.getElementById ('btnInverseSetClear').value = 'cancel';
            //document.getElementById ('formInverse').style.display = "block";
            document.getElementById ('formInverse').style.display = "none";

            //clear search result - since some terms may be removed/renamed
            document.getElementById ('resSearchInverse').innerHTML = "";
            _setOrclearInverseTermId2()

        }else{
            document.getElementById ('btnInverseSetClear').value = 'set';
            //document.getElementById ('formInverse').style.display = "block";
            document.getElementById ('formInverse').style.display = "none";
            document.getElementById ('edInverseTerm').value = "";
            document.getElementById ('edInverseTermId').value = "0";
            _setOrclearInverseTermId2()
        }
    }

    function _showWarning(message){
        var ele = document.getElementById ('divMessage');
        document.getElementById ('divMessage-text').innerHTML =  message;
        Hul.popupTinyElement(this, ele);
    }
    function _disableWarning(){
        _showWarning("Sorry, this term is marked as "+document.getElementById ("trm_Status").value+" and cannot therefore be deleted");
    }
    function _disableWarning2(){
        _showWarning("Sorry, this term is marked as "+document.getElementById ("trm_Status").value+". Inverse term cannot be set");
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
    * NOT USED - called from _mergeTerms
    * nodeid - to be merged
    * retain_nodeid - target
    */
    function _doTermMerge(retain_nodeid, nodeid){

        var $_dialogbox;

        var _updateResult = function(context){

            if(!Hul.isnull(context) && !context.error){

                top.HEURIST.terms = context.terms;
                if(top.hWin && top.hWin.HEURIST4){
                    top.hWin.HEURIST4.terms = context.terms;
                }
                
                /*_isSomethingChanged = true;
                document.getElementById ('div_btnAddChild').style.display = "inline-block";
                document.getElementById ('btnDelete').value = "Delete";
                document.getElementById ('btnSave').value = "Save";
                document.getElementById ('div_SaveMessage').style.display = "inline-block";
                setTimeout(function(){document.getElementById ('div_SaveMessage').style.display = "none";}, 2000);*/

                //@todo !!!! verify that we need this code
                /*
                if(_termTree2==null){
                    _fillTreeView(_termTree1);
                }else{
                    var ind = _tabView.get("activeIndex");
                    // _fillTreeView((ind===0)?_termTree1:_termTree2); FillTreeView only called for Yui tree
                    _fillTreeView((ind===0)?_termTree1:_termTree2);
                }
                */
            }
        };

        // var _updateOnServer = function(){

        var trmLabel = $(top.document).find('#lblMergeLabel1').text();// $(top.document).find('input:radio[name="rbMergeLabel"]:checked').val();

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

        var str = JSON.stringify(oTerms);

        //alert(str);

        var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
        var callback = _updateResult;
        var params = "method=mergeTerms&data=" + encodeURIComponent(str)+"&retain="+retain_nodeid+"&merge="+nodeid+"&db="+_db;
        Hul.getJsonData(baseurl, callback, params);


        if($_dialogbox) top.HEURIST.util.closePopup($_dialogbox.id);
        $_dialogbox = null;
        //};


        /*var termsByDomainLookup = top.HEURIST.terms.termsByDomainLookup[_currentDomain],
        fi = top.HEURIST.terms.fieldNamesToIndex;

        var arTerm = termsByDomainLookup[nodeid];
        $('#lblTerm_toMerge').html(arTerm[fi.trm_Label]+' ['.fontsize(1)+arTerm[fi.trm_ConceptID].fontsize(1) +']'.fontsize(1));

        var arTerm2 = termsByDomainLookup[retain_nodeid];
        $('#lblTerm_toRetain').html(arTerm2[fi.trm_Label]+' ['.fontsize(1)+arTerm2[fi.trm_ConceptID].fontsize(1) +']'.fontsize(1));

        $('#lblMergeLabel1').text(arTerm2[fi.trm_Label]);


        /*  if(Hul.isempty(arTerm[fi.trm_Label])){
        $('#mergeLabel2').hide();
        }else{
        $('#mergeLabel2').show();
        $('#lblMergeLabel2').html(arTerm[fi.trm_Label]);
        $('#rbMergeLabel2').val(arTerm[fi.trm_Label]);
        } */


        /***  Check and Description Buttons-- if "Neither" - disable both buttons and show <none> in both
        if "One" - select the one and disable the other and show <none> in the empty one
        if "Both" - Select the first one and the other


        if((Hul.isempty(arTerm[fi.trm_Code])) && (Hul.isempty(arTerm2[fi.trm_Code]))  &&
        (Hul.isempty(arTerm[fi.trm_Description])) && (Hul.isempty(arTerm2[fi.trm_Description]))){
        $('#mergeCode1').show();
        $('#mergeCode2').show();
        $('#mergeDescr1').show();
        $('#mergeDescr2').show();
        $('input[name ="rbMergeCode"]').attr('disabled', 'disabled');
        $('input[name ="rbMergeDescr"]').attr('disabled', 'disabled' );
        $('#lblMergeCode2').html('&#60;none&#62;');
        $('#rbMergeCode2').val('&#60;none&#62;');
        $('#lblMergeCode1').html('&#60;none&#62;');
        $('#rbMergeCode1').val('&#60;none&#62;');
        $('#lblMergeDescr1').html('&#60;none&#62;');
        $('#rbMergeDescr1').val('&#60;none&#62;');
        $('#lblMergeDescr2').html('&#60;none&#62;');
        $('#rbMergeDescr2').val('&#60;none&#62;');
        }
        else if((!Hul.isempty(arTerm[fi.trm_Code])) && (!Hul.isempty(arTerm[fi.trm_Description])) && (Hul.isempty(arTerm2[fi.trm_Code])))
        {
        $('#mergeCode1').show();
        $('#mergeCode2').show();
        $('#mergeDescr1').show();
        $('#mergeDescr2').show();
        $('#rbMergeCode1').attr('disabled', 'disabled');
        $("#rbMergeCode2").attr('checked', 'checked');
        $('#rbMergeDescr1').attr('disabled', 'disabled');
        $("#rbMergeDescr2").attr('checked', 'checked');
        $('#lblMergeCode2').html(arTerm[fi.trm_Code]);
        $('#rbMergeCode2').val(arTerm[fi.trm_Code]);
        $('#lblMergeDescr2').html(arTerm[fi.trm_Description]);
        $('#rbMergeDescr2').val(arTerm[fi.trm_Description]);
        }
        else if((Hul.isempty(arTerm[fi.trm_Code])) && (!Hul.isempty(arTerm2[fi.trm_Code]))
        && (!Hul.isempty(arTerm2[fi.trm_Description])))
        {
        $('#mergeCode1').show();
        $('#mergeCode2').show();
        $('#mergeDescr1').show();
        $('#mergeDescr2').show();
        $('#rbMergeCode2').attr('disabled', 'disabled');
        $("rbMergeCode1").attr('checked', 'checked');
        $('#rbMergeDescr2').attr('disabled', 'disabled');
        $("#rbMergeDescr1").attr('checked', 'checked');
        $('#lblMergeCode1').html(arTerm2[fi.trm_Code]);
        $('#rbMergeCode1').val(arTerm2[fi.trm_Code]);
        $('#lblMergeDescr1').html(arTerm2[fi.trm_Description]);
        $('#rbMergeDescr1').val(arTerm2[fi.trm_Description]);
        }
        else
        {
        $('#mergeCode1').show();
        $('#mergeCode2').show();
        $('#mergeDescr1').show();
        $('#mergeDescr2').show();
        $('input[name ="rbMergeCode"]').removeAttr( "disabled" );
        $('input[name ="rbMergeCode"]').attr('checked', 'checked');
        $('input[name ="rbMergeDescr"]').removeAttr( "disabled" );
        $('input[name ="rbMergeDescr"]').attr('checked', 'checked');
        $('#lblMergeCode1').html(arTerm2[fi.trm_Code]);
        $('#rbMergeCode1').val(arTerm2[fi.trm_Code]);
        $('#lblMergeCode2').html(arTerm[fi.trm_Code]);
        $('#rbMergeCode2').val(arTerm[fi.trm_Code]);
        $('#lblMergeDescr1').html(arTerm2[fi.trm_Description]);
        $('#rbMergeDescr1').val(arTerm2[fi.trm_Description]);
        $('#lblMergeDescr2').html(arTerm[fi.trm_Description]);
        $('#rbMergeDescr2').val(arTerm[fi.trm_Description]);
        }


        /*if((Hul.isempty(arTerm[fi.trm_Description]))){
        $('#mergeDescr2').hide();

        $('#lblMergeDescr2')
        $('#rbMergeDescr2').val('&#60;none&#62;');;
        }else{
        $('#mergeDescr2').show();
        $('#lblMergeDescr2').html(arTerm[fi.trm_Description]);
        $('#rbMergeDescr2').val(arTerm[fi.trm_Description]);
        } */


        //var arTerm2 = termsByDomainLookup[retain_nodeid];

        //$('#lblTerm_toRetain').html(arTerm2[fi.trm_Label]+' ['.fontsize(1)+arTerm2[fi.trm_ConceptID].fontsize(1) +']'.fontsize(1));

        //$('#lblTerm_toRetain').html(' ['+arTerm2[fi.trm_ConceptID]+']').style.fontSize= "x-small";
        /*   if(Hul.isempty(arTerm2[fi.trm_Label])){
        $('#mergeLabel2').hide();
        $('#lblMergeLabel1').html(arTerm[fi.trm_Label]);
        $('#rbMergeLabel1').val(arTerm[fi.trm_Label]);
        }else{
        $('#lblMergeLabel1').html(arTerm2[fi.trm_Label]);
        $('#rbMergeLabel1').val(arTerm2[fi.trm_Label]);
        } */
        /* if(Hul.isempty(arTerm2[fi.trm_Code])){
        $('#mergeCode2').hide();
        $('#lblMergeCode1').html('&#60;none&#62;');
        $('#rbMergeCode1').val('&#60;none&#62;');


        }else{

        $('#lblMergeCode1').html('&#60;none&#62;');
        $('#rbMergeCode1').val('&#60;none&#62;');

        }
        if(Hul.isempty(arTerm2[fi.trm_Description])){
        $('#mergeDescr2').hide();
        $('#lblMergeDescr1').html(arTerm[fi.trm_Description]);
        $('#rbMergeDescr1').val(arTerm[fi.trm_Description]);
        }else{
        $('#lblMergeDescr1').html(arTerm2[fi.trm_Description]);
        $('#rbMergeDescr1').val(arTerm2[fi.trm_Description]);
        } *


        $('#lblRetainId').html(retain_nodeid);
        $('#lblMergeId').html(nodeid);

        //fill elements of con
        var ele = document.getElementById('divTermMergeConfirm');

        $("#btnMergeCancel").click(function(){$_dialogbox.remove();});//if($_dialogbox) top.HEURIST.util.closePopup($_dialogbox.id);});
        $("#btnMergeOK").click(function(){_updateOnServer; $_dialogbox.remove(); });



        //show confirmation dialog
        $_dialogbox = Hul.popupElement(top, ele,
        {
        "close-on-blur": false,
        "no-resize": true,
        title: 'Select values to be retained',
        height: 310,
        width: 560
        }); */


    }


    /**
    * Saves the term on server side
    */
    function _doSave(needConfirm, noValidation, callback){


        var sName = document.getElementById ('edName').value.trim().replace(/\s+/g,' ');
        document.getElementById ('edName').value = sName;
        var sDesc = document.getElementById ('edDescription').value.trim();
        document.getElementById ('edDescription').value = sDesc;
        var sCode = document.getElementById ('edCode').value.trim().replace(/\s+/g,' ');
        document.getElementById ('edCode').value = sCode;
        var sStatus = document.getElementById ('trm_Status').value;

        var iInverseId = Number(document.getElementById ('edInverseTermId').value);
        iInverseId = (Number(iInverseId)>0) ?iInverseId:null;
        var iInverseId_prev = Number(_currentNode.data.inverseid);
        iInverseId_prev = (iInverseId_prev>0)?iInverseId_prev:null;

        var iParentId = Number(document.getElementById ('edParentId').value);
        iParentId = (Number(iParentId)>0)?iParentId:null;
        var iParentId_prev = Number(_currentNode.data.parent_id);
        iParentId_prev = (iParentId_prev>0)?iParentId_prev:null;

        var wasChanged = ((_currentNode.data.label !== sName) ||
            (_currentNode.data.description !== sDesc) ||
            (_currentNode.data.termcode !== sCode) ||
            (_currentNode.data.status !== sStatus) ||
            (iParentId_prev !== iParentId) ||
            (iInverseId_prev !== iInverseId));


        if(wasChanged){
            var ele = $('#btnSave');
            ele.removeProp('disabled');
            $(ele).removeClass('save-disabled');
        }

        //( !(Hul.isempty(_currentNode.data.inverseid)&&Hul.isnull(iInverseId)) &&
        //    Number(_currentNode.data.inverseid) !== iInverseId));


        if(wasChanged || !isExistingNode(_currentNode) ){

            var swarn = "";
            if(Hul.isempty(sName)){
                swarn = "The term cannot be blank (the standard code, description and image are optional)"
            }else {
                //IJ 2014-04-09 swarn = Hul.validateName(sName, "Field 'Display Name'");
            }
            if(swarn!=""){
                alert(swarn);
                document.getElementById ('edName').setFocus();
                return;
            }
            if((_currentDomain=="relation") && document.getElementById ('cbInverseTermOther').checked && iInverseId==null){
                alert("Please select the inverse term, or select non-directional in the radio buttons");
                document.getElementById ('edInverseTermId').setFocus();
                return;
            }
            
            //keep node, since current node can be changed while this async action
            var nodeForAction = _currentNode;

            if(needConfirm){
                
                if(hasH4()){
                    
                     var buttons = {};
                     buttons['Yes'] = function() {
                            var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                            $dlg.dialog( "close" );
                            __doSave_continue();
                     };
                     buttons['No'] = function() {
                            var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                            $dlg.dialog( "close" );
                            if( !isExistingNode(nodeForAction) ){
                                _doDelete2(nodeForAction, false);           //@todo - to fix - not removed from fancytree
                            }
                     };

                     window.hWin.HEURIST4.msg.showMsgDlg("Term "+
                        _currentNode.data.id+'  '
                        +_currentNode.data.label+',  '+sName
                        +" was modified. Save it?", buttons, "");
                }else{
                    var r = confirm("Term was modified. Save it?");
                    if (!r) {
                        //if new - remove from tree
                        if( !isExistingNode(nodeForAction) ){
                            _doDelete2(nodeForAction, false);           //@todo - not removed from fancytree
                        }
                        return;
                    }else{
                        __doSave_continue();
                    }
                }
            }else{
                __doSave_continue();
            }

            function __doSave_continue(){
            
                if(noValidation || _validateDups(nodeForAction, sName, sCode)){

                    var needReload = (nodeForAction.data.parent_id != iParentId || nodeForAction.data.inverseid != iInverseId);

                    nodeForAction.data.label = sName;
                    nodeForAction.data.description = sDesc;
                    nodeForAction.data.termcode = sCode;
                    nodeForAction.data.status = sStatus;

                    nodeForAction.data.inverseid = iInverseId;
                    nodeForAction.data.title = _currentNode.data.description;

                    nodeForAction.data.parent_id = iParentId;

                    _updateTermsOnServer(nodeForAction, needReload, callback);

                    //alert("TODO SAVE ON SERVER");
                }
            }
        }
    }

    /**
    *
    */
    function _validateDups(node, new_name, new_code){

        // var sibs = node.getSiblings(); //getSiblings is a function for YuI, we updated the tree to fancyTree
        var sibs = node.getChildren();
        var ind;
        for (ind in sibs){
            if(!Hul.isnull(ind)){
                if(sibs[ind].label == new_name){
                    alert("Duplicate term '"+new_name+"' - there is already a term with the same label in this branch at this level.");
                    return false;
                }
                if(new_code!='' && sibs[ind].data.termcode == new_code){
                    alert("There is already a term with the standard code '"+new_code+"' in this branch.");
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
    function _updateTermsOnServer(node, _needReload, main_callback)
    {

        var term = node.data;
        var current_id = node.data.id;
        var wasExpanded = node.isExpanded();
        var parent_id = null;

        /*
        var sibs = node.getSiblings();
        var ind;
        for (ind in sibs){
        if(!Hul.isnull(ind)){
        if(sibs[ind].label == node.label){
        alert("There is already a term with the same label in this branch");
        return;
        }
        if(sibs[ind].data.termcode == node.data.termcode){
        alert("There is already a term with the same code in this branch");
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

        //PLEASE NOTE use "node.label' for YUI TREE INSTEAD OF 'term.label'
        oTerms.terms.defs[term.id] = [term.label, term.inverseid, term.description, term.domain, term.parent_id, term.status, term.termcode ];

        var str = JSON.stringify(oTerms);

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

                                if(term.id != item){ //new term
                                    term.id = item;
                                    //find vocab term (top level)
                                    //top.HEURIST.treesByDomain
                                    var topnode = _findTopLevelForId(term.parent_id);
                                    if (!Hul.isnull(topnode)) {
                                        var vocab_id = topnode.data.id;
                                        if(_affectedVocabs.indexOf(vocab_id)<0){
                                            _affectedVocabs.push(vocab_id);
                                            _showFieldUpdater();
                                            /*if(_affectedVocabs.length===1){
                                            document.getElementById ('formAffected').style.display = 'block';
                                            }*/
                                        }
                                    }
                                    if(_parentNode){
                                        //reselect parent node
                                        parent_id = _parentNode.data.id;
                                        wasExpanded = true;
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
                                    document.getElementById ('edId').value = item;
                                }
                                /*
                                if(_parentNode){
                                    //reselect parent node
                                    var parent_id = _parentNode.data.id;
                                    _findNodeById(parent_id, true);
                                    wasExpanded = true;
                                    //_parentNode.setExpanded(true);
                                }
                                */
                            }
                        }
                    }//for

                    if(!error) {
                        
                        top.HEURIST.terms = context.terms;
                        if(top.hWin && top.hWin.HEURIST4){
                            top.hWin.HEURIST4.terms = context.terms;
                        }

                        _isSomethingChanged = true;
                        document.getElementById ('div_btnAddChild').style.display = "inline-block";
                        document.getElementById ('btnDelete').value = "Delete";

                        /* var $_dialogbox;
                        var ele = document.getElementById('div_SaveMessage');
                        $_dialogbox = Hul.popupElement(top, ele,
                        {
                        "close-on-blur": false,
                        "no-resize": true,
                        title: '',
                        height: 100,
                        width: 200
                        });*/


                        //document.getElementById ('div_SaveMessage').style.display = "inline-block";
                        $("#Saved" ).css("display","inline-block");
                        setTimeout(function(){$("#Saved" ).css("display","none")}, 1000);
                        $("#btnSave" ).prop('disabled', 'disabled');
                        $('#btnSave').addClass('save-disabled');

                        /*FancyTree needs to be reinitialised manually when terms are modified*/

                        
                        $treediv.remove();
                        _defineContentTreeView();
                        
                        var term_id;
                        if(parent_id){
                            term_id = parent_id;
                        }else if(_currentNode){
                            term_id = _currentNode.data.id;
                        }else{
                            term_id = current_id;    
                        }
                        
                        var node = _findNodeById(term_id, wasExpanded);
                        node.setFocus(true);    
                        
                        
                        //_currentNode.setExpanded(true);
                        
                        /*_currentNode.setFocus(true);
                        _currentNode.setActive(true);*/


                        if(needReload){



                        }
                        
                    }
                    if($.isFunction(main_callback)){
                        main_callback.call(this, !error);    
                    }
                }
            };

            //
            var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
            var callback = _updateResult;
            var params = "method=saveTerms&data=" + encodeURIComponent(str)+"&db="+_db;
            Hul.getJsonData(baseurl, callback, params);
        }
    }

    /**
    * new of existing nodeF
    */
    function isExistingNode(node){
        return ((typeof node.data.id === "number") || node.data.id.indexOf("-")<0);
    }

    /**
    * Deletes current term
    */
    function _doDelete(needConfirm){
        _doDelete2(_currentNode, needConfirm);
    }

    function _doDelete2(nodeToDelete, needConfirm){
        
        if(nodeToDelete===null) return;
        var isExistingTerm = isExistingNode(nodeToDelete),
        sMessage = "";

        if(isExistingTerm){
            sMessage = "Delete term '"+nodeToDelete.data.label+"'?";
            if(nodeToDelete.countChildren()>0){
                sMessage = sMessage + "\nWarning: All child terms will be deleted as well";
            }
        }else{
            sMessage = "Cancel the addition of new term?";
        }


        var r = (!needConfirm) || confirm(sMessage);

        if (r) {

            if(isExistingTerm){
                //this term exists in database - delete it
                document.getElementById ('deleteMessage').style.display = "block";
                document.getElementById ('formEditor').style.display = "none";

                function __updateAfterDelete(context) {

                    if(!Hul.isnull(context) && !context['error']){

                        top.HEURIST.terms = context.terms;
                        if(top.hWin && top.hWin.HEURIST4){
                            top.hWin.HEURIST4.terms = context.terms;
                        }
                        //remove from tree
                        var term_id = _currentNode?_currentNode.data.id:0;
                        var term_id2 = nodeToDelete.data.id;
                        
                        nodeToDelete.remove();
                        nodeToDelete = null;
                        if(term_id2==term_id){
                            _currentNode = null;    
                            _onNodeClick(null);
                        }
                        
                        _isSomethingChanged = true;
                    }else{
                        document.getElementById ('formEditor').style.display = "block";
                    }
                    document.getElementById ('deleteMessage').style.display = "none";
                }

                var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
                var callback = __updateAfterDelete;
                var params = "method=deleteTerms&trmID=" + _currentNode.data.id+"&db="+_db;
                Hul.getJsonData(baseurl, callback, params);
            }else{
                
                        var term_id = _currentNode?_currentNode.data.id:0;
                        var term_id2 = nodeToDelete.data.id;
                        
                        nodeToDelete.remove();
                        nodeToDelete = null;
                        if(term_id2==term_id){
                            _currentNode = null;    
                            _onNodeClick(null);
                        }
                
                document.getElementById ('deleteMessage').style.display = "none";
            }
        }
    }

    /**
    * Open popup and select new parent term
    */
    function _selectParent(){

        if(_currentNode===null) return;
        if (_currentNode === _findTopLevelForId(_currentNode.data.id))
        {
            alert("Sorry, the top level of the tree is a vocabulary (hierarchical list of terms). Vocabularies cannot be moved or merged.");
            return;
        }
        var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));
        var nodeid = _currentNode.data.id;

        //_keepCurrentParent = _getNextSiblingId(_currentNode);
        //if(_keepCurrentParent==null)
        _keepCurrentParent = _currentNode.data.parent_id;


        var url = top.HEURIST.baseURL +
        "admin/structure/terms/selectTermParent.html?domain="+_currentDomain+"&child="+nodeid+"&mode=0&db="+db;
        if(keep_target_newparent_id){
            url = url + "&parent=" + keep_target_newparent_id;
        }

        Hul.popupURL(top, url,
            {
                "close-on-blur": false,
                "no-resize": true,
                height: 500,
                width: 500,
                callback: function(newparent_id) {
                    if(newparent_id) {
                        if(newparent_id === "root") {
                            document.getElementById ('edParentId').value = "";
                        }else{
                            document.getElementById ('edParentId').value = newparent_id;
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
    * NOT USED 
    * Open popup and select term to be merged
    */
    function _mergeTerms(){

        if(_currentNode===null) return;
        
        if (_currentNode === _findTopLevelForId(_currentNode.data.id))
        {
            alert("Sorry, the top level of the tree is a vocabulary (hierarchical list of terms). Vocabularies cannot be moved or merged.");
            return;
        }
        var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db : (top.HEURIST.database.name?top.HEURIST.database.name:''));
        var retain_nodeid = _currentNode.data.id;

        //_keepCurrentParent = _getNextSiblingId(_currentNode);
        //if(_keepCurrentParent==null)
        _keepCurrentParent = _currentNode.data.parent_id;


        var url = top.HEURIST.baseURL +
        "admin/structure/terms/selectTermParent.html?domain="+_currentDomain+"&child="+retain_nodeid+"&mode=1&db="+db;
        /*if(keep_target_newparent_id){
        url = url + "&parent=" + keep_target_newparent_id;
        }*/

        var name_with_path = _getTermLabel(_currentDomain, _keepCurrentParent) + ' - '
        + _getTermLabel(_currentDomain, retain_nodeid);

        Hul.popupURL(top, url,
            {
                "close-on-blur": false,
                "no-resize": true,
                title: 'Merge into: '+name_with_path,
                height: 500,
                width: 500,
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
        var tree = $treediv.fancytree("getTree");
        var term;
        if(value==null){
            if (isRoot){
                value = {id:null,label:"",desription:""}; //new term [vocab]
            } else {
                value = {id:null,label:"new term",desription:""}; //
            }
        }

        if(isRoot){

            var rootNode =   $treediv.fancytree("getRootNode");


            term = {}; //new Object();
            //term.id = (value.id)?value.id:"0-" + (rootNodet.getNodeCount()); //correct
            var newNode= rootNode.addChildren({


                id: (value.id)?value.id:"0-" + (tree.count()),
                parent_id: null,
                conceptid: null,
                domain:_currentDomain,
                label: value.label,
                description:value.description,
                termcode : value.termcode,
                inverseid: null,
                folder:true,
                title:value.label});


            newNode.setActive(true);

            _onNodeClick(newNode);

        }else if(!Hul.isnull(_currentNode))
        {

            var _temp = _currentNode;
            var newNode= _currentNode.addChildren({


                id: (value.id)?value.id:"0-" + (tree.count()),
                parent_id: null,
                conceptid: null,
                domain:_currentDomain,
                label: value.label,
                description:value.description,
                termcode : value.termcode,
                inverseid: null,
                folder:true,
                title:value.label});


            newNode.setActive(true); //expand
            _currentNode.setExpanded(true);
            _parentNode = newNode.getParent();
            document.getElementById ('edParentId').value = _parentNode.data.id>0?_parentNode.data.id:0;
        }
    }

    /**
    * take the current selection from resSearch, find and open it in tree and show in edit form
    */
    function _doEdit(){
        var sel = document.getElementById ('resSearch');
        if(sel.selectedIndex>=0){
            var nodeid = sel.options[sel.selectedIndex].value;
            var node = _findNodeById(nodeid, true); 

            if(!Hul.isnull(node)){
                //scroll does not work 
                //node.makeVisible();//{noAnimation: true, noEvents: true, scrollIntoView: true}); 
                setTimeout(function(){
                  $(node.li).get(0).scrollIntoView();  
                },500);
                
                //term_tree
                //old 
                //node.setFocus(true);
                //_onNodeClick(node);
            }
        }
    }
    function _doSelectInverse(){ // This Function may not be necessary as internal function (_Select inverse) has been defined in _setOrclearInverseTermId2 for PopUp
        var sel = document.getElementById ('resSearchInverse');
        if(sel.selectedIndex>=0 && !Hul.isnull(_currentNode) ){

            var nodeid = sel.options[sel.selectedIndex].value;
            var node = _findNodeById(nodeid, false);
            if(false && _currentNode.data.id===nodeid){
                alert("It is not possible to make a term the inverse of itself.");
            }
            else if(_currentNode === _findTopLevelForId(_currentNode.data.id))
            {
                alert("you can't set inverse on a top level vocabulary");

            }
            else if( node === _findTopLevelForId(nodeid) ){
                alert("you can't make a top level vocabulary an inverse");
            }else{

                document.getElementById ('edInverseTerm').value = sel.options[sel.selectedIndex].text;
                document.getElementById ('edInverseTermId').value = nodeid;
                document.getElementById ('btnInverseSetClear').value = 'clear';
                document.getElementById ('formInverse').style.display = "none";
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

            var sURL = top.HEURIST.baseURL + "admin/structure/terms/printVocabulary.php?db="+ _db
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
            var term_label = (isRoot)?'root vocabulary':_currentNode.data.label;

            /* old way
            var sURL = top.HEURIST.baseURL + "admin/structure/terms/editTermsImport.php?db="+ _db +
            "&parent="+term_id+
            "&domain="+_currentDomain;
            */

            var sURL = top.HEURIST.baseURL + "hclient/framecontent/import/importDefTerms.php?db="+ _db +
            "&trm_ID="+term_id;

            Hul.popupURL(top, sURL, {
                "close-on-blur": false,
                "no-resize": false,
                title: 'Import Terms for '+term_label,
                //height: 200,
                //width: 500,
                height: 460,
                width: 800,
                'context_help':top.HEURIST.baseURL+'context_help/defTerms.html #import',
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
            if(hasH4()){
                window.hWin.HEURIST4.msg.showMsgDlg(context.result.length
                    + ' term'
                    + (context.result.length>1?'s were':' was')
                    + ' added.', null, 'Terms imported');
                window.hWin.HEURIST4.terms = context.terms;
            }


            top.HEURIST.terms = context.terms;
            
            var res = context.result,
            ind,
            parentNode = (context.parent===0)?_currTreeView.getRoot():_currentNode, //??????
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
                                term.title = arTerm[fi.trm_Label];
                                term.folder = false;
                                term.conceptid = null;
                                term.description = arTerm[fi.trm_Description];
                                term.termcode = "";
                                term.parent_id = context.parent; //_currentNode.data.id;
                                term.domain = _currentDomain;
                                term.inverseid = null;

                                var newNode= parentNode.addChildren(term);    
                            }
                        }
                    }
                }//for
                
            
                parentNode.setExpanded(true);    
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
        _needtype = (_currentDomain==='enum')?'enum':'relationtype';   //relmarker???


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

            var parent = document.getElementById ('formEditFields');
            parent.innerHTML = ""; //"<h2>No affected field types found</h2>";

            document.getElementById ('formAffected').style.display = 'none';

        }else{
            //debug alert(arrRes.join(","));

            var parent = document.getElementById ('formEditFields');
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
        var el_sel = document.getElementById ("selector"+dty_ID);
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

        Hul.popupURL(top, top.HEURIST.baseURL +
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

                        var str = JSON.stringify(_oDetailType);

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

                            var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
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
                    $('#termImage').find('img').prop('src', top.HEURIST.baseURL + 'hclient/assets/100x100click.png');
                }
            }
            }, null);

    }

    function _initFileUploader(){

        var $input = $('#new_term_image');
        var $input_img = $('#termImage');

        $input.fileupload({
            url: top.HEURIST.baseURL +  'hserver/utilities/fileUpload.php',
            formData: [ {name:'db', value: _db},
                {name:'entity', value:'terms'},
                {name:'newfilename', value: document.getElementById ('edId').value }],
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
                            //window.hWin.HEURIST4.msg.showMsgErr(file.error);
                        }else{
                            var curtimestamp = (new Date()).getMilliseconds();
                            var url = top.HEURIST.iconBaseURL + document.getElementById ('edId').value+ "&ent=term&t=" + curtimestamp
                            $input_img.find('img').prop('src', url); //file.url);
                        }
                    });
                }
            }
        });

    }
    
    /**
    * IN USE!!!
    * 
    * @param retain_node
    * @param node - node to be merged
    */
    function _doMerge(retain_node, node, hitMode){

        var merge_term = node.data;
        var retain_term =retain_node.data;
        
        var _updateResult = function(context){
            if(!Hul.isnull(context) && !context.error){

                top.HEURIST.terms = context.terms;
                if(top.hWin && top.hWin.HEURIST4){
                    top.hWin.HEURIST4.terms = context.terms;
                }
                
                while(node.hasChildren())
                {
                    node.getFirstChild().moveTo(retain_node, hitMode);
                }
                $(node.span).hide();                
            }
        };

        // var _updateOnServer = function(){

        var trmLabel = retain_term.label;// $(top.document).find('input:radio[name="rbMergeLabel"]:checked').val();

        var retain_nodeid = retain_term.id
        var nodeid = merge_term.id


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
            $(top.document).find('input:radio[name="rbMergeCode"]:checked').val()];

        var str = JSON.stringify(oTerms);

        //alert(str);

        var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
        var callback = _updateResult;
        var params = "method=mergeTerms&data=" + encodeURIComponent(str)+"&retain="+retain_nodeid+"&merge="+nodeid+"&db="+_db;
        
        Hul.getJsonData(baseurl, callback, params);

    }

    function verifyLibrariesLoaded()
    {
        //verify that all required libraries have been loaded
        if(!$.isFunction($('body').fancytree)){        //jquery.fancytree-all.min.js
            $.getScript(window.hWin.HAPI4.baseURL+'ext/fancytree/jquery.fancytree-all.min.js');
            return;
        }
        else if(!$.ui.fancytree._extensions["dnd"]){
            alert('drag-n-drop extension for tree not loaded')
            return;
        }
        return;
    }

    //
    //Method to define the content of fancyTreeView
    function  _defineContentTreeView(){
        //

        verifyLibrariesLoaded();

        var $_dialogbox;
        
        var  treedata = [];
        var termid,
        first_node,
        treesByDomain = top.HEURIST.terms.treesByDomain[_currentDomain],
        termsByDomainLookup = top.HEURIST.terms.termsByDomainLookup[_currentDomain],
        fi = top.HEURIST.terms.fieldNamesToIndex;

        //first level terms
        for (termid in treesByDomain)
        {

            if(!Hul.isnull(termid)){

                //var nodeIndex = tv.getNodeCount()+1;

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


                function __createChildren(parentNode, parent_id) { // Recursively get all children
                    var _term,
                    childNode,
                    nodeIndex,
                    child;
                    var children  = []

                    for(child in parentNode)
                    {
                        if(!Hul.isnull(child)){
                            var _arTerm = termsByDomainLookup[child];

                            if(!Hul.isnull(_arTerm)){

                                _term = {};

                                _term.id = child;
                                _term.parent_id = parent_id;
                                _term.conceptid = _arTerm[fi.trm_ConceptID];
                                _term.domain = _currentDomain;
                                _term.label = Hul.isempty(_arTerm[fi.trm_Label])?'ERROR N/A':_arTerm[fi.trm_Label];
                                _term.description =_arTerm[fi.trm_Description];
                                _term.termcode  =  _arTerm[fi.trm_Code];
                                _term.inverseid = _arTerm[fi.trm_InverseTermID];
                                _term.status = _arTerm[fi.trm_Status];
                                _term.original_db = _arTerm[fi.trm_OriginatingDBID];


                                // childNode = parentEntry.addNode(term);

                                children.push({id:child,parent_id:parent_id,conceptid:_arTerm[fi.trm_ConceptID],domain:_currentDomain, label:_term.label, description:_arTerm[fi.trm_Description],termcode:_arTerm[fi.trm_Code],inverseid:_arTerm[fi.trm_InverseTermID],status:_arTerm[fi.trm_Status],original_db:_arTerm[fi.trm_OriginatingDBID], title:_term.label,folder:true,children: __createChildren(parentNode[child], _term.id)});


                            }
                        }
                    }

                    return children;
                }


                var parentNode = treesByDomain[termid];
                //all: { title: window.hWin.HR('My Searches'), folder: true,
                treedata.push({id:termid,parent_id:term.parent_id,conceptid:term.conceptid,domain:term.domain, label:term.label, description:term.description,termcode:term.termcode,inverseid:term.inverseid,status:term.status,original_db:term.original_db,title:term.label,folder:true,children:__createChildren(parentNode, termid)});



                // Look for children of the node

            }
        }

        var top_ele = document.getElementById("termTree");
        $treediv = $('<div>').attr('id','term_tree').appendTo(top_ele);

        var dragEnterTimeout = 0;
        
        $treediv.fancytree(
            {
                activeVisible:true,
                checkbox: false,
                //titlesTabbable: false,     // Add all node titles to TAB chain
                //selectMode: 1,
                source:treedata,
                activate: function(event, data){
                    // A node was activated: display its details
                    _onNodeClick(data);
                },
                click: function(event, data){
                  if(data.targetType=='expander'){
                      
                   setTimeout(function(){
                    $.each( $('.fancytree-node'), function( idx, item ){
                        __defineActionIcons(item);
                    });
                    }, 500);  
                      
                      
                  }  
                },
                extensions:['dnd','themeroller'],
                dnd: {
                    autoExpandMS: 500, //it does not work - we expand manually in dragEnter
                    //focusOnClick: true,
                    draggable: { // modify default jQuery draggable options
                        zIndex: 10000,
                        scroll: true,
                        scrollSpeed: 7,
                        scrollSensitivity: 10
                    },
                    preventVoidMoves: true, // Prevent dropping nodes 'before self', etc.
                    preventRecursiveMoves: true, // Prevent dropping nodes on own descendants
                    dragStart: function(node, data) {
                        /*if(node.getLevel()===1){
                        return false;
                        }*/

                        //_onNodeClick(node);
                        return true;
                    },
                    dragEnter: function(node, data) {
                        if (data.otherNode.getLevel()===1){ //we drag vocabulary
                            if(node.getLevel()>1){ //over child
                                //console.log('vocab over child '+node.data.id);    
                                return false;
                            }

                            return "over";
                        }


                        if ( data.otherNode.getLevel()>1 && node.getLevel()===1 ){
                            //we drag child over vocab 
                            //1. for vocab with children - expand it manually
                            if(node.hasChildren()){
                                if(dragEnterTimeout>0) clearTimeout(dragEnterTimeout);
                                dragEnterTimeout = setTimeout(function(){
                                        node.setExpanded(true);
                                },1000);
                                
                                return false;
                            }else{
                                //allow to add as a first child
                                return 'after';
                            }
                        }


                        return true;

                    },
                    dragDrop: function(node, data) {
                        
                        //_onNodeClick(data.otherNode);

                        if(data.otherNode.getLevel()===1){ //drop vocab
                            if(node.getLevel()!=1)  
                                return false;  //not allowed to drop on child
                            else{
                                showMergePopUp(node,data); //merge vocabs
                            }

                        }else if((data.otherNode.hasChildren()) && (data.otherNode.getLevel()!=1)){
                            showMergePopUp(node,data);
                        }
                        else{
                            showMergePopUp(node,data);
                        }
                        //add child
                    }

                },
                themeroller: {
                    activeClass: "ui-state-active"
                }
        });
        
        
       setTimeout(function(){
            
            var tree = $treediv.fancytree("getTree");
            tree.getRootNode().sortChildren(null, true); //true - deep sort
            
            $.each( $('.fancytree-node'), function( idx, item ){
                __defineActionIcons(item);
            });
        }, 500);  

       function __defineActionIcons(item){ 
           if($(item).find('.svs-contextmenu3').length==0){

               var actionspan = $('<div class="svs-contextmenu3">'
                   +'<span class="ui-icon ui-icon-plus" title="Add a child term (a term hierarchichally below the current vocabulary or term)"></span>'
                   +((_currentDomain=="relation")
                      ?'<span class="ui-icon ui-icon-reload" title="Set the inverse term for this term"></span>' //for relations only
                      :'')
                   +'<span class="ui-icon ui-icon-close" title="Delete this term (if unused in database)"></span>'
                   //+'<span class="ui-icon ui-icon-image" title="Add an image which illustrates this term"></span>'
                   +'<span class="ui-icon ui-icon-arrowthick-1-w" title="IMPORT a comma-delimited list of terms (and optional codes and labels) as children of this term"></span>'
                   +'<span class="ui-icon ui-icon-arrowthick-1-e" title="EXPORT this vocabulary to a text file"></span>'
                   +'</div>').appendTo(item);
                   
                   
               actionspan.find('.ui-icon').click(function(event){
                    var ele = $(event.target);
                    
                    //timeour need to activate current node    
                    setTimeout(function(){                         
                    if(ele.hasClass('ui-icon-plus')){
                        _doAddChild(false);
                    }else if(ele.hasClass('ui-icon-reload')){
                         $("#btnInverseSetClear").click();
                         //_setOrclearInverseTermId();
                    }else if(ele.hasClass('ui-icon-close')){
                        $("#btnDelete").click();
                        //_doDelete();
                    }else if(ele.hasClass('ui-icon-arrowthick-1-w')){
                        _import(false)
                    }else if(ele.hasClass('ui-icon-arrowthick-1-e')){
                        _export(false);
                    }else if(ele.hasClass('ui-icon-image')){
                         that.showFileUploader();
                    }
                    },500);
                    //window.hWin.HEURIST4.util.stopEvent(event); 
                    //return false;
               });
               /*
               $('<span class="ui-icon ui-icon-pencil"></span>')                                                                
               .click(function(event){ 
               //tree.contextmenu("open", $(event.target) ); 
               
               ).appendTo(actionspan);
               */

               //hide icons on mouse exit
               function _onmouseexit(event){
                       var node;
                       if($(event.target).is('li')){
                          node = $(event.target).find('.fancytree-node');
                       }else if($(event.target).hasClass('fancytree-node')){
                          node =  $(event.target);
                       }else{
                          //hide icon for parent 
                          node = $(event.target).parents('.fancytree-node');
                          if(node) node = $(node[0]);
                       }
                       var ele = node.find('.svs-contextmenu3'); //$(event.target).children('.svs-contextmenu3');
                       ele.hide();//css('visibility','hidden');
               }               
               
               $(item).hover(
                   function(event){
                       var node;
                       if($(event.target).hasClass('fancytree-node')){
                          node =  $(event.target);
                       }else{
                          node = $(event.target).parents('.fancytree-node');
                       }
                       var ele = $(node).find('.svs-contextmenu3');
                       ele.css('display','inline-block');//.css('visibility','visible');
                   }
               );               
               $(item).mouseleave(
                   _onmouseexit
               );
           }
       }

       
       //
       // node - item drop on
       // data.otherNode - dragged node
       //             
       function showMergePopUp(node, data)
       {

            if(data.hitMode==='over'){

                function displayPopUpContents(cbCode1,cbCode2,cbDescr1,cbDescr2){
                    if(cbCode1){
                        $('#rbMergeCode1').attr('checked', 'checked');
                        $('#lblMergeCode1').html(arTerm2[fi.trm_Code]);
                    }
                    else{
                        $('#rbMergeCode1').attr('disabled', 'disabled');
                        $('#lblMergeCode1').css({'color':'gray','font-size':'0.8em'}).html('&#60;none&#62;');
                    }
                    if(cbCode2){
                        $("#rbMergeCode2").attr('checked', 'checked');
                        $('#lblMergeCode2').html((arTerm[fi.trm_Code]));
                    }
                    else{
                        $("#rbMergeCode2").attr('disabled', 'disabled');
                        $('#lblMergeCode2').css({'color':'gray','font-size':'0.8em'}).html('&#60;none&#62;');
                    }
                    if(cbDescr1)
                    {
                        $('#rbMergeDescr1').attr('checked', 'checked');
                        $('#lblMergeDescr1').html(arTerm2[fi.trm_Description]);
                    }
                    else{
                        $('#rbMergeDescr1').attr('disabled', 'disabled');
                        $('#lblMergeDescr1').css({'color':'gray','font-size':'0.8em'}).html('&#60;none&#62;');
                    }
                    if(cbDescr2){
                        $("#rbMergeDescr2").attr('checked', 'checked');
                        $('#lblMergeDescr2').html(arTerm[fi.trm_Description]);
                    }
                    else{
                        $("#rbMergeDescr2").attr('disabled', 'disabled');
                        $('#lblMergeDescr2').css({'color':'gray','font-size':'0.8em'}).html('&#60;none&#62;');
                    }

                }


                var termsByDomainLookup = top.HEURIST.terms.termsByDomainLookup[_currentDomain],
                fi = top.HEURIST.terms.fieldNamesToIndex;
                var merge_node = data.otherNode.data;
                var retain_node = node.data;

                var arTerm = termsByDomainLookup[merge_node.id];
                $('#lblTerm_toMerge').html(arTerm[fi.trm_Label]+' ['.fontsize(1)+arTerm[fi.trm_ConceptID].fontsize(1) +']'.fontsize(1));

                var arTerm2 = termsByDomainLookup[retain_node.id];
                $('#lblTerm_toRetain').html(arTerm2[fi.trm_Label]+' ['.fontsize(1)+arTerm2[fi.trm_ConceptID].fontsize(1) +']'.fontsize(1));

                $('#lblMergeLabel1').text(arTerm2[fi.trm_Label]);

                /***  Check and Description Buttons-- if "Neither" - disable both buttons and show <none> in both
                if "One" - select the one and disable the other and show <none> in the empty one
                if "Both" - Select the first one and the other

                ***/
                if((Hul.isempty(arTerm[fi.trm_Code])) && (Hul.isempty(arTerm2[fi.trm_Code]))  &&
                    (Hul.isempty(arTerm[fi.trm_Description])) && (Hul.isempty(arTerm2[fi.trm_Description]))){
                    displayPopUpContents(false,false,false,false);
                }
                else if((!Hul.isempty(arTerm[fi.trm_Code])) && (Hul.isempty(arTerm[fi.trm_Description]))
                    && (Hul.isempty(arTerm2[fi.trm_Code])) && (Hul.isempty(arTerm2[fi.trm_Description])))
                    {
                        displayPopUpContents(true,false,false,false);

                    }
                    else if((!Hul.isempty(arTerm[fi.trm_Code])) && (!Hul.isempty(arTerm[fi.trm_Description]))
                        && (Hul.isempty(arTerm2[fi.trm_Code])) && (Hul.isempty(arTerm2[fi.trm_Description])))
                        {
                            displayPopUpContents(true,false,true,false);

                        }
                        else if((!Hul.isempty(arTerm[fi.trm_Code])) && (!Hul.isempty(arTerm[fi.trm_Description]))
                            && (!Hul.isempty(arTerm2[fi.trm_Code])) && (Hul.isempty(arTerm2[fi.trm_Description])))
                            {
                                displayPopUpContents(true,true,true,false);
                            }
                            else if((Hul.isempty(arTerm[fi.trm_Code])) && (!Hul.isempty(arTerm[fi.trm_Description]))
                                && (Hul.isempty(arTerm2[fi.trm_Code])) && (Hul.isempty(arTerm2[fi.trm_Description])))
                                {
                                    displayPopUpContents(false,false,true,false);

                                }
                                else if((Hul.isempty(arTerm[fi.trm_Code])) && (!Hul.isempty(arTerm[fi.trm_Description]))
                                    && (!Hul.isempty(arTerm2[fi.trm_Code])) && (Hul.isempty(arTerm2[fi.trm_Description])))
                                    {
                                        displayPopUpContents(false,true,true,false);
                                    }
                                    else if((Hul.isempty(arTerm[fi.trm_Code])) && (!Hul.isempty(arTerm[fi.trm_Description]))
                                        && (Hul.isempty(arTerm2[fi.trm_Code])) && (!Hul.isempty(arTerm2[fi.trm_Description])))
                                        {
                                            displayPopUpContents(false,false,true,true);
                                        }
                                        else if((Hul.isempty(arTerm[fi.trm_Code])) && (!Hul.isempty(arTerm[fi.trm_Description]))
                                            && (!Hul.isempty(arTerm2[fi.trm_Code])) && (!Hul.isempty(arTerm2[fi.trm_Description])))
                                            {
                                                displayPopUpContents(false,true,true,true);
                                            }
                                            else if((Hul.isempty(arTerm[fi.trm_Code])) && (Hul.isempty(arTerm[fi.trm_Description]))
                                                && (!Hul.isempty(arTerm2[fi.trm_Code])) && (!Hul.isempty(arTerm2[fi.trm_Description])))
                                                {
                                                    displayPopUpContents(false,false,true,true);
                                                }
                                                else if((!Hul.isempty(arTerm[fi.trm_Code])) && (Hul.isempty(arTerm[fi.trm_Description]))
                                                    && (!Hul.isempty(arTerm2[fi.trm_Code])) && (Hul.isempty(arTerm2[fi.trm_Description])))
                                                    {
                                                        displayPopUpContents(true,true,false,false);
                                                    }
                                                    else if((Hul.isempty(arTerm[fi.trm_Code])) && (Hul.isempty(arTerm[fi.trm_Description]))
                                                        && (!Hul.isempty(arTerm2[fi.trm_Code])) && (Hul.isempty(arTerm2[fi.trm_Description])))
                                                        {
                                                            displayPopUpContents(false,true,false,false);
                                                        }
                                                        else
                                                        {
                                                            displayPopUpContents(false,false,false,true);
                                                        }

                var ele = document.getElementById('divTermMergeConfirm');
                $("#moveText").html("Move "+ "<b>" +data.otherNode.data.label+"</b> under <b>"+node.data.label
                    +"</b>");
                
                var isVocabulary = (node === _findTopLevelForId(node.data.id));
                    
                $('#divInsertAsChild').css('display', (isVocabulary)?'none':'block');
                //show confirmation dialog
                $_dialogbox = Hul.popupElement(top, ele,
                    {
                        "close-on-blur": false,
                        "no-resize": true,
                        close: function(){
                            $_dialogbox.find("#btnMergeOK").off('click');
                            $_dialogbox.find("#moveBtn").off('click');
                            $_dialogbox.find("#btnMergeCancel").off('click');
                            //$_dialogbox.find("#moveBtnCancel").off('click');
                        },
                        title: 'Select values to be retained',
                        height: 440,
                        width: 600
                });
                
                
                //$_dialogbox.find("#moveBtnCancel").click(function(){ $_dialogbox.dialog($_dialogbox).dialog("close"); });
                $_dialogbox.find("#btnMergeCancel").click(function(){ $_dialogbox.dialog($_dialogbox).dialog("close"); });
                $_dialogbox.find("#btnMergeOK").click(function(){
                        _doMerge(node, data.otherNode, data.hitMode);
                        $_dialogbox.dialog($_dialogbox).dialog("close");
                });


                if(!data.otherNode.hasChildren()){ //no children in term to be merged
                    $_dialogbox.find("#moveBtn").click(function(){

                        _findNodeById(data.otherNode.data.id, true);
                        
                        if(node.data.id)
                        {
                            
                            var prev_parent = document.getElementById ('edParentId').value;
                            if(node.data.id === "root") {
                                document.getElementById ('edParentId').value = "";
                            }else{
                                document.getElementById ('edParentId').value = node.data.id>0?node.data.id:0;

                            }

                            _doSave(false, true);
                            data.otherNode.moveTo(node, data.hitMode);
                        }
                        
                        $_dialogbox.dialog($_dialogbox).dialog("close");
                    });

                }
                else{ //has children - vocabulary to be merged
                    $_dialogbox.find("#moveBtn").click(function(){


                        if(node.data.id)
                        {
                            if(node.data.id === "root") {
                                document.getElementById ('edParentId').value = "";
                            }else{
                                document.getElementById ('edParentId').value = node.data.id>0?node.data.id:0;

                            }
                            // alert($('#edParentId').val());

                            // _updateTermsOnServer(_currentNode,false);
                            _doSave(false,true);
                        }

                        data.otherNode.moveTo(node,data.hitMode);
                        $_dialogbox.dialog($_dialogbox).dialog("close");
                    });
                }
                
                
                
                


            }
            else if (data.hitMode==='before' || data.hitMode==='after'){

                var parentNodeId = null;
                
                //data.otherNode - dragging node
                //node target node
                
                var isAddChild = (data.otherNode.getLevel()>1 && node.getLevel()===1 && !node.hasChildren());
                if(isAddChild){
                    //add fist child to empty vocab
                    parentNodeId = node.data.id;
                }else{
                    var parentNode = node.getParent();    
                    parentNodeId = parentNode.data.id;
                }
                if(parentNodeId === 'root'){
                    parentNodeId = '';
                }
                
                function __moveNode(){
                        if(isAddChild){
                            //add first child to empty vocab 
                            node.addNode(data.otherNode);
                        }else{
                            data.otherNode.moveTo(node,data.hitMode);    
                        }                    
                }
                
                //find vocabs for source and destination
                vocab_id_Target = getVocabId(node);
                vocab_id_Source = getVocabId(data.otherNode);
                
                //move to another vocab
                if (parentNodeId!=null){
                    
                    function __proceed(need_reload){
                        //everything is OK
                        need_reload = false;
                        var nodeForAction = data.otherNode;            
                        if(nodeForAction.data.parent_id != parentNodeId){
                            nodeForAction.data.parent_id = parentNodeId; //new parent
                            _updateTermsOnServer(nodeForAction, need_reload, (need_reload?null:__moveNode));
                        }
                        //document.getElementById('edParentId').value = parentNodeId>0?parentNodeId:0;
                        //_doSave(false, true);
                        
                    }
                                
                    if(vocab_id_Source != vocab_id_Target){
                        
                        // verify whether term is in use in field that uses vocabulry
                        // if yes it means it can not be moved into different vocabulary
                        var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
                        var params = "method=checkTerm&termID=" + data.otherNode.data.id + "&db="+_db;
                        Hul.getJsonData(baseurl, function(context){
                        if(!Hul.isnull(context) && !context.error){
                            
                            if(context.warning){
                                if(hasH4()){
                                    //msg = {message:context.warning, error_title:context.error_title};
                                    //window.hWin.HEURIST4.msg.showMsgErr(msg);
                                    window.hWin.HEURIST4.msg.showMsgDlg(context.warning, function(){
                                        __proceed(true);
                                    },{title:context.error_title, yes:'Proceed'})
                                }else{
                                    if (confirm(context.warning)) {
                                        __proceed(true);
                                    }
                                }
                            }else{
                                __proceed(true);
                            }
                            
                        }}, params);
                        
                    }else{
                        __proceed(false);
                    } 

                }else{
                    __moveNode();
                } 
            }

        }

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
                    {name:'newfilename', value: document.getElementById ('edId').value }]);

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

    // var sel = (el.id === 'edSearch')? $(top.document).find('#resSearch'):$(top.document).find('#resSearchInverse');
    var sel = (el.id === 'edSearch')?document.getElementById ('resSearch'):document.getElementById ('resSearchInverse');

    //remove all
    while (sel.length>0){

        sel.remove(0);
    }

    //el = document.getElementById ('edSeartch');
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





























