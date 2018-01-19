/*
* Copyright (C) 2005-2016 University of Sydney
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
*  pop-up window to define recordtype title mask
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
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
 * EditRectypeTitle - class for pop-up window to define recordtype title mask
 *
 * @author Artem Osmakov <osmakov@gmail.com>
 * @version 2011.0919
 */
function EditRectypeTitle() {

    var _className = "EditRectypeTitle",
    _rectypeID,
    _varsTree, //treeview object
    _db;

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
            window.close("Record type ID is not defined");
            return;
        }else{

            _db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
                (top.HEURIST.database.name?top.HEURIST.database.name:''));

            //find variables for given rectypeID and create variable tree
            var baseurl = top.HEURIST.baseURL + "common/php/recordTypeTree.php?mode=varsonly&rty_id="+_rectypeID+
            "&ver=1&w=all&stype=&db="+_db  + "&q=type:" + _rectypeID; //"&q=id:146433";
            top.HEURIST.util.getJsonData(baseurl, _onGenerateVars, "");
        }
    }//end _init


    function _onGenerateVars(context){

        if(!Hul.isnull(context))
            {
            if(context===false || !context['vars']){
                alert('No variables generated');
                return;
            }

            _variables = context['vars'];

            /* TODO: Clean up all this old code            
            
            //   {rt_id: , rt_name, recID, recTitle ..... 
            //                  fNNN:'name', 
            //                  fNNN:array(termfield_name: , id, code:  )
            //                  fNNN:array(rt_name: , recID ...... ) //unconstrained pointer
            //                  fNNN:array(rt_id: , rt_name, recID, recTitle ... ) //constrined pointer
            //
 
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

            //fill list of records
            var sel = Dom.get("listRecords");
            //clear selection list
            while (sel.length>1){
                sel.remove(1);
            }

            var _recs = context['records'];

            var i;
            for (i in _recs){
                if(i!==undefined){
                    Hul.addoption(sel, _recs[i].rec_ID, _recs[i].rec_Title);
                }
            } // for

            sel.selectedIndex = 0;
        }
    }

    //
    // fill the treeview with variables of specified record type
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

        recTypeID = _variables.rt_id;

        //find list of variables for current record type
        var i, j;  
        
        /*TODO: Cleanup old code
            Artem 2013-12-11        
                for (i in _variables){
                    if(i!==undefined && _variables[i].id===recTypeID){
                        varnames = _variables[i];
                        break;
                    }
                }//for
        */        

        if(Hul.isnull(_varsTree)){
            _varsTree = new YAHOO.widget.TreeView("varsTree");
            _varsTree.subscribe("clickEvent",
                function() { // On click, select the term, and add it to the selected terms tree

                    var _node = arguments[0].node;
                    
                    if(_node.children.length<1 && _node.data.this_id!='remark'){
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
        _fillTreeView(_varsTree, _variables);
        
        /* it does not work since yui tree is formed dynamically
        $('#varsTree').find('.nocheckbox').each(function(idx, item){
                $(item).parents('td.ygtvcell').css('background','none');
        });
        */
        
        
    }

    /**
     *	Fills the given treeview with the list of variables
     * 	varnames  - contains vars - flat array and tree - tree array
     */
    function _fillTreeView (tv, _variables) {

        var termid,
        tv_parent = tv.getRoot(),
        first_node,
        varid;

        //clear treeview
        tv.removeChildren(tv_parent);

        __createChildren(tv_parent, _variables, '', '');

        function __createChildren(parentNode, rectypeTree, parent_id, parent_full) { // Recursively get all children
        
                var term,
                    childNode,
                    child, id;
                    
                var rectype_id = rectypeTree.rt_id;

                for(id in rectypeTree)
                {
                if(! (Hul.isnull(id) || id=='rt_id' || id=='rt_name' || id=='termfield_name' || id=='recURL' || id=='recWootText' || id=='Relationship') ){  // ||
                
                   if(parent_full=='' && id=='recTitle'){
                       continue; //do not allow rectitle for first level
                   }
                
                    
                    var label = null;
                    
                    //cases
                    // common fields like recID, recTitle
                    // detail fields  fNNN: name
                    // detail fields ENUMERATION fNNN:array(termfield_name
                    // unconstained pointers fNNN:array(rt_name
                    // multi-contrained pointers fNNN:array(array(rt_id  - need another recursive loop

                    child = rectypeTree[id];
                    var is_record = ((typeof(child) == "object") &&
                                    Object.keys(child).length > 0);
                                    
                    
                    term = {};//new Object();
                    term.id = parent_full+"."+id; //fullid;
                    term.parent_id = parent_id;
                    term.this_id = id;
                    term.label = '<div style="padding-left:10px;"'+(is_record||id=='remark'?' class="nocheckbox"':'')+'>';
                    
                    
                    var is_multicontstrained = false; 
                                   
                    if(!is_record){ //simple
                    
                        label = child;   
                        if(id=='remark'){
                            term.label = term.label + '<i>' + label + '</i></div>';
                        }else{
                            term.label = term.label + label + '</div>';    
                        }
                        
                        
                    }else{
                         
                         if(child['termfield_name']) {
                             label = child['termfield_name']; //enumeration
                             term.label = term.label + label+'</div>'; //'<b>' +  + '</b></div>';
                         }else{
                             if ( typeof(child[0]) == "string" ) {
                                is_multicontstrained = true;
                                label = child[0];         
                             }else{
                                label = child['rt_name'];         
                             }
                             term.label = term.label + '<b><i>' + label + '</i></b></div>';
                         }
                    }

                    //term.labelonly = label;
                    term.full_path = ((parent_full)?parent_full+".":"")+label;

                    if( is_multicontstrained ){

                        var k;                        
                        for(k=1; k<child.length; k++){
                            
                            var rt_term = {};//new Object();
                            rt_term.id = term.id+"."+child[k].rt_id;  //record type

                            var _varname = term.id;
                            
                            rt_term.label =  '<div style="padding-left:10px;"><b>'+child[k].rt_name + '</b>('+child[0]+')</div>';
                            //rt_term.href = "javascript:void(0)";

                            var rectypeNode = new YAHOO.widget.TextNode(rt_term, parentNode, false);
                            
                            __createChildren(rectypeNode, child[k], term.this_id, term.full_path);
                        }
                        
                    }else{
                        
                         childNode = new YAHOO.widget.TextNode(term, parentNode, false); // Create the node
                         //childNode.enableHighlight = false;
                        
                         if( is_record ){ //next recursion
                            __createChildren(childNode, child, term.this_id, term.full_path);
                         }
                    }
                }
                }//for        
        }
        
        
/*        
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

                            var fullid = parentNode[child],
                            _varnames = varnames.vars,
                            _detailtypes = varnames.detailtypes,
                            is_enum = false,
                            dt_type = '',
                            label = '';

                            var is_record = ((typeof(fullid) == "object") &&
                                Object.keys(fullid).length > 0);

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
                                term.id = fullid;
                                term.parent_id = parent_id;
                                term.this_id = label;
                                term.label = '<div style="padding-left:10px;">'; //???arVars[0];
                                term.labelonly = label;
                                term.dtype = dtype;

                                if(is_enum){
                                    term.label = term.label + label + '&nbsp;(enum)</div>';
                                }else if( is_record ){

                                    term.label = term.label + '<b>' + label + '</b></div>';
                                    //'<a href="javascript:void(0)" onClick="showReps.markAllChildren(\''+
                                    //				child+'\')">All</a>&nbsp;&nbsp';
                                    //term.href = "#";
                                    //term.onclick = "{javascript:void(0); return false;}";
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
*/
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


        var baseurl = top.HEURIST.baseURL + "admin/structure/rectypes/editRectypeTitle.php";
        var squery = "rty_id="+rec_type+"&mask="+encodeURIComponent(mask)+"&db="+_db+"&check=1";

        top.HEURIST.util.sendRequest(baseurl, function(xhr) {
                var obj = xhr.responseText;
                if(obj==="" || obj==="\n"){
                    var sel = Dom.get("listRecords");
                    if (sel.selectedIndex>0){

                        var rec_id = sel.value;
                        squery = "rty_id="+rec_type+"&rec_id="+rec_id+"&mask="+encodeURIComponent(mask)+"&db="+_db;
                        top.HEURIST.util.sendRequest(baseurl, function(xhr) {
                                var obj2 = xhr.responseText;
                                document.getElementById('testResult').innerHTML = obj2;
                            }, squery);
                    }else{
                        alert('Select a record from the pulldown to test your title mask');
                    }
                }else{
                    alert(obj);
                }

            }, squery);
    }

    /**
     * First step: check title mask
     */
    function _doSave_Step1_Verification()
    {

        function __loopNodes(node){
            if(node.children.length===0 && node.highlightState===1){
                return true;
            }
            return false;
        }
        
        var res = _varsTree.getNodesBy(__loopNodes);
        if(res && res.length>0){
            alert('You have not yet inserted the selected fields in the title mask. Please click Insert Fields, or unselect the fields in the tree.');
            return;
        }
        
        
        var mask = document.getElementById('rty_TitleMask').value;
        //var rec_type = top.HEURIST.parameters.rectypeID;

        var baseurl = top.HEURIST.baseURL + "admin/structure/rectypes/editRectypeTitle.php";
        var squery = "rty_id="+_rectypeID+"&mask="+encodeURIComponent(mask)+"&db="+_db+"&check=1";

        top.HEURIST.util.sendRequest(baseurl, function(xhr) {
               var obj = xhr.responseText;
               if(obj==="" || obj==="\n"){
                    _doSave_Step2_SaveRectype();
               }else{
                    alert(obj);
               }

            }, squery);
    }
    /**
    * Second step - update record type definition
    */
    function _doSave_Step2_SaveRectype(){
                    
            var newvalue = document.getElementById('rty_TitleMask').value;
            if(newvalue != top.HEURIST.parameters.mask){
                
                var typedef = top.HEURIST.rectypes.typedefs[_rectypeID];
                
                typedef.commonFields[ top.HEURIST.rectypes.typedefs.commonNamesToIndex.rty_TitleMask ] = newvalue;
                
                var _defs = {};
                _defs[_rectypeID] = [{common:[newvalue],dtFields:[]}];
                var oRectype = {rectype:{colNames:{common:["rty_TitleMask"],dtFields:[]},
                            defs:_defs}}; //{_rectypeID:[{common:[newvalue],dtFields:[]}]}
                var str = JSON.stringify(oRectype);
                
                var baseurl = top.HEURIST.baseURL + "admin/structure/saveStructure.php";
                var callback = _updateTitleMask;// updateResult;
                var params = "method=saveRT&db="+_db+"&data=" + encodeURIComponent(str);
                Hul.sendRequest(baseurl, function(xhr) {
                    _updateTitleMask();
                    /*var obj = xhr.responseText;
                    if(obj===""){
                        _updateTitleMask();
                    }else{
                        alert(obj);
                    }*/
                }, params);                                
            }else{
                window.close(newvalue);
            }                    
            
    }
    /**
    * Third step - update records - change title
    */
    function _updateTitleMask(){
        var URL = top.HEURIST.baseURL + "admin/verification/recalcTitlesSpecifiedRectypes.php?db="+_db+"&recTypeIDs="+_rectypeID;

        Hul.popupURL(top, URL, {
                "close-on-blur": false,
                "no-resize": true,
                height: 400,
                width: 400,
                callback: function(context) {
                }
        });
        
        var edMask = document.getElementById('rty_TitleMask');
        window.close(edMask.value);
    }
    

    /**
    * Artem: do not remove this - it is used for debug
    * not used   TODO: DELETE UNUSED CODE
    */
    function _doCanonical(mode){

        var mask = (mode==2)?document.getElementById('rty_TitleMask').value :document.getElementById('rty_CanonincalMask').value;
        var rec_type = top.HEURIST.parameters.rectypeID;

        var db = (top.HEURIST.parameters.db? top.HEURIST.parameters.db :
            (top.HEURIST.database.name?top.HEURIST.database.name:''));


        var baseurl = top.HEURIST.baseURL + "admin/structure/rectypes/editRectypeTitle.php";
        var squery = "rty_id="+rec_type+"&mask="+encodeURIComponent(mask)+"&db="+db+"&check="+mode;

        top.HEURIST.util.sendRequest(baseurl, function(xhr) {
                var obj = xhr.responseText;

                if(mode==2){
                    document.getElementById('rty_CanonincalMask').value = obj;
                }else{
                    document.getElementById('rty_TitleMask').value = obj;
                }
            }, squery);

    }

    //
    // utility function - TODO: move to HEURIST.utils
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
     * Insert field into title mask input field
     */
    function _doInsert(){

        var textedit = Dom.get("rty_TitleMask"),
        _text = "";

        //function for each node in _varsTree - create the list
        function __loopNodes(node){
            if(node.children.length===0 && node.highlightState===1){
                node.highlightState=0;

                _text = _text + ' ['+node.data.full_path+'] ';
            }
            return false;
        }
        //loop all nodes of tree
        _varsTree.getNodesBy(__loopNodes);

        if(_text!=="")	{
            insertAtCursor(textedit, _text);
            _varsTree.render();
        }else{
            alert('You must select a field for insertion');
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
        doSave:function(){
            _doSave_Step1_Verification();
        },
        doInsert:function(){
            _doInsert();
        },

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            return (strClass === _className);
        },

        doCanonical:function(mode){
            _doCanonical(mode);
        }

    };


    _init();  // initialize before returning
    return that;

}
