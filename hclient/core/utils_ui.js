/**
*  Utility functions 
* a) to create standard record types, field types and terms selectors
* b) fast access to db structure defintions
*
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/*
Selectors:
addoption - helper function to add option to select element
createSelector - create SELECT element (if selObj is null) and fill with given options

getChildrenTerms - returns entire terms tree or only part of it for selected termID
getChildrenLabels - returns all tems labels of children terms for given term
createTermSelectExt   - create/fill SELECT for terms or returns JSON array 
createTermSelectExt2  - the same but parameters are passed as options object
getTermValue - Returns label and code for term by id
getTermDesc
getPlainTermsList
getFullTermLabel

createRectypeGroupSelect - get SELECT for record type groups
createRectypeSelect - get SELECT for record types   
createRectypeDetailSelect - get SELECT for details of given recordtype
createRectypeTreeSelect - get SELECT for hierarchy of record types   
    
createUserGroupsSelect - get SELECT for list of given groups, othewise loads list of groups for current user    

setValueAndWidth assign value to input and adjust its width

ENTITY

openRecordEdit  - open add/edit record form/dialog
openRecordInPopup - open viewer or add/edit record form
createRecordLinkInfo - creates ui for resource or relationship record

createEntitySelector - get id-name selector for specified entity
showEntityDialog - get id-name selector for specified entity


Other UI functions    
initDialogHintButtons - add show hint and context buttons into dialog header
initHelper - Inits helper div (slider) and button   


createRecordLinkInfo - return ui for link and relationship
*/

if (!window.hWin.HEURIST4){
    window.hWin.HEURIST4 = {};
}
//init only once
if (!window.hWin.HEURIST4.ui) 
{

window.hWin.HEURIST4.ui = {

    setValueAndWidth: function(ele, value, padding){
        
        if(window.hWin.HEURIST4.util.isempty(value)) value='';
        ele = $(ele);
        if(ele.is('input')){
            
            if(!(padding>0)) padding = 4;
            
            ele.val( value )
                .css('width', (Math.min(80,Math.max(20,value.length))+padding+'ex'));
        }else{
            ele.html( value );
        }
    },
    
    //
    // helper function to add option to select element
    //
    addoption: function(sel, value, text, disabled)
    {
        var option = document.createElement("option");
        option.text = window.hWin.HEURIST4.util.htmlEscape(text);
        option.value = value;
        if(disabled===true){
            option.disabled = true;
        }
        
        //$(option).appendTo($(sel));
        sel.appendChild(option);
        /*
        try {
            // for IE earlier than version 8
            sel.add(option, sel.options[null]);
        }catch (ex2){
            sel.add(option, null);
        }
        */
        
        return option;
    },

    //
    // create checkboxm, radio or select element
    // options
    //  type:
    //  hideclear 
    //  values: [{key:'',title:''},....]
    //
    createInputSelect: function($inpt, options) {
        
        if(options.type=='checkbox'){
            if($inpt==null || !$inpt.is('input')){
                $inpt = $('<input>');
            }
            $inpt.attr('type','checkbox');
            
            //@todo
            
            return $inpt;
        }else if(options.type=='radio'){
            
            var $parent = null;
            if($inpt!=null){
                $parent = $inpt.parent();
            }
            
            var $inpt_group = $('<div>').attr('radiogroup',1).uniqueId()
                        .css({background: 'none', padding: '2px'});
                        
           if($parent!=null) {
                $inpt_group.insertBefore($inpt);   
                $inpt.remove();
           }
                        
            var id = $inpt_group.attr('id');
            
            for (idx in options.values)
            if(idx>=0){
                if(window.hWin.HEURIST4.util.isnull(options.values[idx].key) && 
                   window.hWin.HEURIST4.util.isnull(options.values[idx].title))
                {
                    key = options.values[idx];
                    title = options.values[idx];
                    disabled = false;
                }else{
                    key = options.values[idx].key;
                    title = options.values[idx].title;
                }
                if(!window.hWin.HEURIST4.util.isnull(title)){
                    $('<label style="padding-right:5px"><input type="radio" value="'
                            +key+'" name="'+id+'">'
                            +window.hWin.HEURIST4.util.htmlEscape(title)+'</label>').appendTo($inpt_group);
                }
            }
            
            return $inpt_group;
            
        }else { //select bu default
            if($inpt==null || !$inpt.is('select')){
                $inpt = $('<select>').uniqueId();
            }
            
            return window.hWin.HEURIST4.ui.createSelector($inpt[0], options.values);
        }
        
    
    },
        
    //
    // create SELECT element (if selObj is null) and fill with given options
    // topOptions either array or string
    // [{key:'',title:''},....]
    //
    createSelector: function(selObj, topOptions) {
        if(selObj==null){
            selObj = document.createElement("select");
        }else{
            $(selObj).off('change');
            if($(selObj).hSelect("instance")!=undefined){
               $(selObj).hSelect("destroy"); 
            }
            $(selObj).empty();
        }
        
        window.hWin.HEURIST4.ui.fillSelector(selObj, topOptions);
        
        return selObj;
    },

    fillSelector: function(selObj, topOptions) {
        
        if(window.hWin.HEURIST4.util.isArray(topOptions)){
            var idx,key,title,disabled;
            if(topOptions){  //list of options that must be on top of list
                for (idx in topOptions)
                {
                    if(idx){
                        if(window.hWin.HEURIST4.util.isnull(topOptions[idx].key) && 
                           window.hWin.HEURIST4.util.isnull(topOptions[idx].title))
                        {
                            key = topOptions[idx];
                            title = topOptions[idx];
                            disabled = false;
                        }else{
                            key = topOptions[idx].key;
                            title = topOptions[idx].title;
                            disabled = (topOptions[idx].disabled===true);
                        }
                        if(!window.hWin.HEURIST4.util.isnull(title))
                        {
                            if(!window.hWin.HEURIST4.util.isnull(topOptions[idx].optgroup)){
                                var grp = document.createElement("optgroup");
                                grp.label =  title;
                                selObj.appendChild(grp);
                            }else{
                                window.hWin.HEURIST4.ui.addoption(selObj, key, title, disabled);
                            }

                        }
                    }
                }
            }
        }else if(!window.hWin.HEURIST4.util.isempty(topOptions) && topOptions!==false){
            if(topOptions===true) topOptions ='';
            window.hWin.HEURIST4.ui.addoption(selObj, '', topOptions);
        }


        return selObj;
    },    
    
    //
    // return list of all children for given trm_ParentTermID in lower case
    //
    getChildrenLabels: function(trm_ParentDomain, trm_ParentTermID){
        
            var trm_ParentChildren = [];
        
            //get list of children labels
            function __getSiblings(children){
                for(trmID in children){
                    if(children.hasOwnProperty(trmID)){
                        if(trmID==trm_ParentTermID){
                            for(var id in children[trmID]){
                                if(children[trmID].hasOwnProperty(id)){
                                    var term = allterms.termsByDomainLookup[trm_ParentDomain][id];
                                    if(term && term[0])
                                        trm_ParentChildren.push(term[0].toLowerCase());
                                }
                            }
                            break;
                        }else{
                            __getSiblings(children[trmID]);
                        }
                    }
                }
            }
            
            var allterms;
            if(top.HEURIST && top.HEURIST.terms){
                allterms = top.HEURIST.terms;
            }else if(window.hWin.HEURIST4 && window.hWin.HEURIST4.terms){
                allterms = window.hWin.HEURIST4.terms;
            }            
            
            var trmID, tree = allterms.treesByDomain[trm_ParentDomain];
            __getSiblings(tree);  
              
            return trm_ParentChildren;
    },
    
    getTermById: function(termID){
        
        var terms = window.hWin.HEURIST4.terms;
        if(!terms || window.hWin.HEURIST4.util.isempty(termID)) return '';
        
        var term, termLookup = terms.termsByDomainLookup['enum'];
        if(termLookup[termID]){
            term = termLookup[termID];
        }else{
            termLookup = terms.termsByDomainLookup['relation'];
            term = termLookup[termID];
        }
        
        return term;
    },

    // get inverse term id
    //
    getInverseTermById: function(termID){
        var term = window.hWin.HEURIST4.ui.getTermById(termID);
        if(term){
            var terms = window.hWin.HEURIST4.terms;
            var invTermID = term[terms.fieldNamesToIndex['trm_InverseTermID']];
            if(invTermID>0) return invTermID;
            return termID;
        }
        return '';
    },
    
    //
    // Returns label and code for term by id
    //
    getTermValue: function(termID, withcode){

        var term = window.hWin.HEURIST4.ui.getTermById(termID);    
        
        var termName, termCode='';

        if(term){
            var terms = window.hWin.HEURIST4.terms;
            termName = term[terms.fieldNamesToIndex['trm_Label']];
            termCode = term[terms.fieldNamesToIndex['trm_Code']];
            if(window.hWin.HEURIST4.util.isempty(termCode)){
                termCode = '';
            }else{
                termCode = " ("+termCode+")";
            }
        } else {
            termName = 'not found term#'+termID;
        }

        return termName+(withcode ?termCode :'');
    },
    
    //
    // get description of label for term
    //
    getTermDesc: function(termID){

        var term = window.hWin.HEURIST4.ui.getTermById(termID);    
        if(term){

            var terms = window.hWin.HEURIST4.terms;
            var termDesc = term[terms.fieldNamesToIndex['trm_Description']];
            if(window.hWin.HEURIST4.util.isempty(termDesc)){
                return term[terms.fieldNamesToIndex['trm_Label']];
            }else{
                return termDesc;
            }
            
        }else{
            return 'not found term#'+termID;
        }

    },

    // not used
    //
    //
    //
    getPlainTermsList: function(datatype, termIDTree, headerTermIDsList, selectedTermID) {
        
        //var selObj = window.hWin.HEURIST4.ui.createTermSelectExt(null, datatype, termIDTree, headerTermIDsList);
        
        var selObj = window.hWin.HEURIST4.ui.createTermSelectExt2(null,
            {datatype:datatype, termIDTree:termIDTree, headerTermIDsList:headerTermIDsList,
             defaultTermID:null, topOptions:null, needArray:false, useHtmlSelect:true});
        

        var reslist = [];

        if(selObj){
            selObj = selObj[0];
            for (var i=0; i<selObj.length; i++){
                if(!selObj.options[i].disabled){
                    reslist.push({id:selObj.options[i].value, text:selObj.options[i].text});
                }
            }
        }
        return reslist;
    },

    //
    //
    //
    isTermInList: function(datatype, termIDTree, headerTermIDsList, selectedTermID) {
        
        //var selObj = window.hWin.HEURIST4.ui.createTermSelectExt(null, datatype, termIDTree, headerTermIDsList);

        var selObj = window.hWin.HEURIST4.ui.createTermSelectExt2(null,
            {datatype:datatype, termIDTree:termIDTree, headerTermIDsList:headerTermIDsList,
             defaultTermID:null, topOptions:null, needArray:false, useHtmlSelect:true});
        
        if(selObj){
            selObj = selObj[0];
            for (var i=0; i<selObj.length; i++){
                if(!selObj.options[i].disabled){
                    if(selObj.options[i].value==selectedTermID){
                        return true;
                    }
                }
            }
        }
        return false;
    },
    
    //
    // return term by selectedTermID and its children as well as comma-separated list of non-disabled ancestors
    // it uses createTermSelectExt to get the entire tree
    // used in search_faceted
    //
    getChildrenTerms: function(datatype, termIDTree, headerTermIDsList, selectedTermID) {

        var termtree = window.hWin.HEURIST4.ui.createTermSelectExt(null, datatype, termIDTree, headerTermIDsList, 
                                                                        null, null, true);
        /*
        function __setParents(parent, terms){

        for (var i=0; i<terms.length; i++)
        {
        if(!terms[i].parents){
        terms[i].parents = [];
        }else{
        terms[i].parents = terms[i].parents.concat(parent.parents);
        }
        terms[i].parents.unshift(parent);

        __setParents(terms[i], terms[i].children);
        }
        }
        */
        function __findTerm(termId, parent, terms)
        {
            for (var i=0; i<terms.length; i++){

                if(terms[i].id==termId){
                    return terms[i];
                }else{
                    var res = __findTerm(termId, terms[i], terms[i].children);
                    if(res!=null){
                        return res;
                    }
                }
            }
            return null; //not found in this level
        }

        var root = {id:null, text:window.hWin.HR('all'), children:termtree};

        //__setParents(root, termtree);

        return window.hWin.HEURIST4.util.isnull(selectedTermID)?root:__findTerm(selectedTermID, root, termtree);
    },

    /**
    * create/fill SELECT for terms or returns JSON array 
    *
    * datatype enum|relation
    * termIDTree - json string or object (tree) OR number - in this case this vocabulary ID, if not defined all terms are taken from window.hWin.HEURIST4.terms.treesByDomain
    * headerTermIDsList - json string or array (disabled terms)
    * defaultTermID - term to be selected
    * topOptions - text or array for top most item(s)
    * needArray  return array tree if terms (instead of select element)
    *
    */
    createTermSelectExt: function(selObj, datatype, termIDTree, headerTermIDsList, defaultTermID, topOptions, needArray) {
        return window.hWin.HEURIST4.ui.createTermSelectExt2(selObj,
            {datatype:datatype, termIDTree:termIDTree, headerTermIDsList:headerTermIDsList,
             defaultTermID:defaultTermID, topOptions:topOptions, needArray:needArray, useHtmlSelect:false});
    },

    createTermSelectExt2: function(selObj, options) {

        var datatype =  options.datatype,
            termIDTree =  options.termIDTree,  //all terms
            headerTermIDsList =  options.headerTermIDsList,  //non selectable
            defaultTermID =  options.defaultTermID,
            topOptions =  options.topOptions,
            needArray  =  options.needArray,
            supressTermCode = options.supressTermCode,
            useHtmlSelect  = (options.useHtmlSelect===true);


        if(needArray){

        }else{
            selObj = window.hWin.HEURIST4.ui.createSelector(selObj, topOptions);
        }

        if(datatype=="relation" || datatype === "relmarker" || datatype === "relationtype"){
            datatype = "relation";
        }else{
            datatype = "enum";
        }

        var terms = window.hWin.HEURIST4.terms;
        if(!(datatype=="enum" || datatype=="relation") || !terms ){
            return needArray ?[] :selObj;
        }

        var termLookup = terms.termsByDomainLookup[datatype];

        //prepare header
        //
        var temp = ( window.hWin.HEURIST4.util.isArray(headerTermIDsList)   //instanceof(Array)
            ? headerTermIDsList
            : (( typeof(headerTermIDsList) === "string" && !window.hWin.HEURIST4.util.isempty(headerTermIDsList) )
                ? $.parseJSON(headerTermIDsList)  //headerTermIDsList.split(",")
                : [] ));

        var headerTerms = {};
        for (var id in temp) {
            headerTerms[temp[id]] = temp[id];
        }

        //
        //
        var isNotFirefox = (navigator.userAgent.indexOf('Firefox')<0);

        function createSubTreeOptions(optgroup, parents, termSubTree, termLookupInner, defaultTermID) {
            var termID;
            var localLookup = termLookupInner;
            var termName,
            termCode, hasImage,
            arrterm = [],
            reslist2 = [];

            for(termID in termSubTree) { // For every term in 'term'
                termName = "";
                termCode = "";

                if(localLookup[termID]){
                    termName = localLookup[termID][terms.fieldNamesToIndex['trm_Label']];
                    termCode = localLookup[termID][terms.fieldNamesToIndex['trm_Code']];
                    hasImage = localLookup[termID][terms.fieldNamesToIndex['trm_HasImage']];
                    if(supressTermCode || window.hWin.HEURIST4.util.isempty(termCode)){
                        termCode = '';
                    }else{
                        termCode = " [code "+termCode+"]";
                    }
                }

                if(window.hWin.HEURIST4.util.isempty(termName)) continue;

                arrterm.push([termID, termName, termCode, hasImage]);
            }

            //sort by name
            arrterm.sort(function (a,b){
                return a[1]<b[1]?-1:1;
            });



            var i=0, cnt= arrterm.length;
            for(;i<cnt;i++) { // For every term in 'term'

                termID = arrterm[i][0];
                termName = arrterm[i][1];
                termCode = arrterm[i][2];
                hasImage = arrterm[i][3];
                var termParents = '';
                var origName = arrterm[i][1];
                
                var depth = parents.length;

                /* not used anymore - replaced with jquery selecmenu
                if(isNotFirefox && (depth>1 || (optgroup==null && depth>0) )){
                    //for non mozilla add manual indent
                    var a = new Array( ((depth<7)?depth:7)*2 );
                    termName = a.join('. ') + termName;       
                }*/
                if(depth>0){
                    termParents = parents.join('.');
                }
                

                var isDisabled = (headerTerms[termID]? true:false);
                var hasChildren = ( typeof termSubTree[termID] == "object" && Object.keys(termSubTree[termID]).length>0 );
                var isHeader   = ((headerTerms[termID]? true:false) && hasChildren);
                var new_optgroup;

                //in FF optgroup is allowed on first level only - otherwise it is invisible

                if(isHeader && depth==0) { // header term behaves like an option group
                    //opt.className +=  ' termHeader';

                    if(selObj){

                        new_optgroup = document.createElement("optgroup");
                        new_optgroup.label = termName;
                        new_optgroup.depth = 0;

                        if(optgroup==null){
                            selObj.appendChild(new_optgroup);
                        }else{
                            optgroup.appendChild(new_optgroup);
                        }
                    }

                }else{

                    if(selObj){

                        var opt = new Option(termName+termCode, termID);
                        opt.className = "depth" + (depth<7)?depth:7;
                        //opt.depth = depth;
                        $(opt).attr('depth', depth);
                        opt.disabled = isDisabled;
                        $(opt).attr('term-img', hasImage?1:0);
                        
                        
                        if(termParents!=''){
                            $(opt).attr('parents', termParents);
                            $(opt).attr('term-orig', origName);  
                            $(opt).attr('term-view', termName+termCode);
                        } 

                        if (termID == defaultTermID ||
                            termName == defaultTermID) {
                            opt.selected = true;
                        }

                        if(optgroup==null){
                            selObj.appendChild(opt);
                        }else{
                            optgroup.appendChild(opt);
                        }
                        new_optgroup = optgroup;
                    }
                }

                var children = [];
                if(hasChildren){
                    var parents2 = parents.slice();
                    parents2.push(termName);      //depth+1
                    children = createSubTreeOptions( new_optgroup, parents2, termSubTree[termID], localLookup, defaultTermID);
                }
                var k=0, cnt2 = children.length, termssearch=[];
                for(;k<cnt2;k++){
                    /*if(!children[k].disabled || children[k].children.length>0){
                    termssearch.push(children[k].id);
                    }*/
                    termssearch = termssearch.concat( children[k].termssearch );
                }
                if(!isDisabled){ //} || children.length>0){
                    termssearch.push(termID); //add itself
                }

                reslist2.push({id:termID, text:termName, depth:depth, disabled:isDisabled, children:children, termssearch:termssearch });
                var parent = reslist2[reslist2.length-1];
                for(k=0;k<cnt2;k++){
                    parent.children[k].parent = parent;
                }
            } //for
            
            return reslist2;
        }//end internal function

        //
        //
        //
        var toparray = [];
        if(window.hWin.HEURIST4.util.isArray(termIDTree)){
            toparray = termIDTree;
        }else{
            toparray = [ termIDTree ];
        }

        var m, lenn = toparray.length;
        var reslist_final = [];

        for(m=0;m<lenn;m++){

            var termTree = toparray[m];
            
            if(!window.hWin.HEURIST4.util.isempty(termTree)){

                //
                //prepare tree
                //
                if(window.hWin.HEURIST4.util.isNumber(termTree)){
                    //this is vocabulary id - show list of all terms for this vocab
                    var tree = terms.treesByDomain[datatype];
                    termTree = (termTree>0)?tree[termTree]:tree;
                }else{
                    termTree = (typeof termTree == "string") ? $.parseJSON(termTree) : null;
                    if(termTree==null){
                        termTree = terms.treesByDomain[datatype];
                    }
                }

                var reslist = createSubTreeOptions(null, [], termTree, termLookup, defaultTermID);
                if(!selObj){
                    reslist_final = reslist_final.concat( reslist);
                }
            
            }
        }

        if(selObj){
            if (!defaultTermID) selObj.selectedIndex = 0;
            
            //apply select menu
            selObj = window.hWin.HEURIST4.ui.initHSelect(selObj, useHtmlSelect);
            
            return selObj;
        }else{
            return reslist_final;
        }
    },

    
    //
    // get selector for record type groups
    //
    createRectypeGroupSelect: function(selObj, topOptions) {

        window.hWin.HEURIST4.ui.createSelector(selObj, topOptions);

        var rectypes = window.hWin.HEURIST4.rectypes,
        index;

        if(!rectypes) return selObj;


        for (index in rectypes.groups){
            if (index == "groupIDToIndex" ){
                //rectypes.groups[index].showTypes.length < 1)
                continue;
            }

            var name = rectypes.groups[index].name;
            if(!window.hWin.HEURIST4.util.isnull(name)){
                window.hWin.HEURIST4.ui.addoption(selObj, rectypes.groups[index].id, name);
            }
        }

        return selObj;

    },

    //
    // get selector for record types
    //
    // rectypeList - constraint options to this list, otherwise show entire list of rectypes separated by groups
    //
    createRectypeSelect: function(selObj, rectypeList, topOptions, useHtmlSelect) {

        selObj = window.hWin.HEURIST4.ui.createSelector(selObj, topOptions);

        useHtmlSelect = (useHtmlSelect===true);
        
        var rectypes = window.hWin.HEURIST4.rectypes,
        index;

        if(rectypes){ 


        if(!window.hWin.HEURIST4.util.isempty(rectypeList)){

            if(!window.hWin.HEURIST4.util.isArray(rectypeList)){
                rectypeList = rectypeList.split(',');
            }
        }else{
            rectypeList = [];
        }
        
        if(rectypeList.length>0 && rectypeList.length<4){  //show only specified list of rectypes
            for (var idx in rectypeList)
            {
                if(idx){
                    var rectypeID = rectypeList[idx];
                    var name = rectypes.names[rectypeID];
                    if(!window.hWin.HEURIST4.util.isnull(name))
                    {
                        window.hWin.HEURIST4.ui.addoption(selObj, rectypeID, name);
                    }
                }
            }
        }else{  //show rectypes separated by groups
        
            for (index in rectypes.groups){
                if (index == "groupIDToIndex" ||
                    rectypes.groups[index].showTypes.length < 1) {
                    continue;
                }
                //show group if at least one rectype is visible
                if(rectypeList.length>0){
                    var notfound = true;
                    for (var recTypeIDIndex in rectypes.groups[index].showTypes){
                        var rectypeID = rectypes.groups[index].showTypes[recTypeIDIndex];
                        if(rectypeList.indexOf(rectypeID)>=0){
                            notfound = false;
                            break;
                        }
                    }
                    if(notfound) continue;
                }
                
                if(useHtmlSelect){
                    var grp = document.createElement("optgroup");
                    grp.label = rectypes.groups[index].name;
                    selObj.appendChild(grp);
                }else{
                    var opt = window.hWin.HEURIST4.ui.addoption(selObj, 0, rectypes.groups[index].name);
                    $(opt).attr('disabled', 'disabled');
                    $(opt).attr('group', 1);
                }

                for (var recTypeIDIndex in rectypes.groups[index].showTypes)
                {
                    var rectypeID = rectypes.groups[index].showTypes[recTypeIDIndex];
                    var name = rectypes.names[rectypeID];

                    if(!window.hWin.HEURIST4.util.isnull(name) && 
                        (rectypeList.length==0 || rectypeList.indexOf(rectypeID)>=0) )
                    {
                        var opt = window.hWin.HEURIST4.ui.addoption(selObj, rectypeID, name);
                        $(opt).attr('depth', 1);
                    }
                }
                
            }
            
        }
        }
        
        selObj = window.hWin.HEURIST4.ui.initHSelect(selObj, useHtmlSelect);

        return $(selObj);
    },

    //
    // get selector for record types tree
    //
    // rectypeTree - constraint options to this list, otherwise show entire list of rectypes separated by groups
    //   id          : rectype id
    //   text        : name
    //   type        : "rectype"
    //   children    : []  // array of fields
    //
    createRectypeTreeSelect: function(selObj, rectypeTree, topOptions, indent) {

        if(!indent) indent=0;
        
        if(indent==0){
            window.hWin.HEURIST4.ui.createSelector(selObj, topOptions);
            if($.isArray(rectypeTree) && rectypeTree.length>0){
                rectypeTree = rectypeTree[0];
            }
        }

        var isNotFirefox = (navigator.userAgent.indexOf('Firefox')<0);
        
        var index, rectypeName, is_used = false;

        var rectypes = window.hWin.HEURIST4.rectypes;
        if(!rectypes) return selObj;
        
        var parent_Name = window.hWin.HEURIST4.util.trim_IanGt(rectypeTree.title);
                
        if(rectypeTree.type=='rectype' ||  rectypeTree.constraint==1){
            
            var recTypeID = rectypeTree.key;
            if(rectypeTree.type=='rectype'){
                rectypeName = parent_Name+((indent>0 && parent_Name!=rectypeTree.parent)?(' as '+rectypeTree.parent):'');    
            }else {                           
                recTypeID = rectypeTree.rt_ids;
                rectypeName = rectypes.names[rectypeTree.rt_ids]+
                        ((rectypes.names[rectypeTree.rt_ids]!=parent_Name)?(' as '+parent_Name):'');
            }
            
            /* rerplaced witj jquery selectmenu see hSelect 
            if(isNotFirefox && indent>0){
                var a = new Array( ((indent<7)?indent:7)*2 );
                rectypeName = a.join('. ') + rectypeName;
            }
            */
            
            var opt = window.hWin.HEURIST4.ui.addoption(selObj, recTypeID, rectypeName); 
            opt.className = "depth" + (indent<7)?indent:7;
            $(opt).attr('depth', indent);        
            is_used = true;
        }
        
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(rectypeTree.children))
        for (index=0;index<rectypeTree.children.length;index++){
               var child = rectypeTree.children[index];
               child.parent = parent_Name;
               window.hWin.HEURIST4.ui.createRectypeTreeSelect(selObj, child, null, 
                    indent+(is_used?1:0) );
        }

        if(indent==0){
            selObj = window.hWin.HEURIST4.ui.initHSelect(selObj, false);        
        }
        
        return selObj;
    },
    
    /**
    * get SELECT for details of given recordtype
    *
    * rtyIDs - record type ID otherwise returns all field types grouped by field groups
    * allowedlist - of data types to this list 
    */
    createRectypeDetailSelect: function(selObj, rtyIDs, allowedlist, topOptions, options ) {

        window.hWin.HEURIST4.ui.createSelector(selObj, topOptions);
        
        var showDetailType = false;
        var addLatLongForGeo = false;
        var requriedHighlight = false;
        var selectedValue = null;
        var showLatLongForGeo = false;
        var show_parent_rt = false;
        var useHtmlSelect = true;
        var initial_indent = 0;
        if(options){  //at the moment it is implemented for single rectype only
            showDetailType    = options['show_dt_name']==true;
            addLatLongForGeo  = options['show_latlong']==true;
            requriedHighlight = options['show_required']==true;
            selectedValue     = options['selected_value'];
            show_parent_rt    = options['show_parent_rt']==true;
            initial_indent    = options['initial_indent']>0?options['initial_indent']:0;
            useHtmlSelect     = options['useHtmlSelect']!==false;
        }
        
        var dtyID, details;
        
        //show fields for specified set of record types
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(rtyIDs) && rtyIDs.length>1){
            //get fields for specified array of record types
            // if number of record types is less than 10 form structure
            // name of field as specified in detailtypes
            //       names of field as specified in record structure (disabled items)
            var dtys = {}, dtyNames = [],dtyNameToID = {},dtyNameToRty={};
            var rtys = {};
            var i,j,recID,rty,rtyName,dty,dtyName,fieldName,opt;
            
            var fi_name = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_DisplayName'], 
                fi_type = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex['dty_Type'];
            

            //for all rectypes find all fields as Detail names sorted
            for (i in rtyIDs) {
                rty = rtyIDs[i];
                rtyName = window.hWin.HEURIST4.rectypes.names[rty];
                
                for (dty in window.hWin.HEURIST4.rectypes.typedefs[rty].dtFields) {
                    
                  var field_type = window.hWin.HEURIST4.detailtypes.typedefs[dty].commonFields[fi_type];
                  if(allowedlist!=null && 
                     allowedlist.indexOf(field_type)<0){
                      continue; //not allowed - skip
                  }  
                  
                  dtyName = window.hWin.HEURIST4.detailtypes.names[dty];
                  if (!dtys[dtyName]){
                    dtys[dtyName] = [];
                    dtyNameToID[dtyName] = dty;
                    dtyNameToRty[dty] = rty; //not used
                    dtyNames.push(dtyName);
                  }
                  fieldName = rtyName + "." + window.hWin.HEURIST4.rectypes.typedefs[rty].dtFields[dty][fi_name];
                  dtys[dtyName].push(fieldName);
                }
            }//for rectypes
            
            //fill select
            if (dtyNames.length >0) {
                dtyNames.sort();
                //add option for DetailType enabled followed by all Rectype.Fieldname options disabled
                for (i in dtyNames) {
                  dtyName = dtyNames[i];
                  var dtyID = dtyNameToID[dtyName];
                  
                  window.hWin.HEURIST4.ui.addoption(selObj, dtyID, dtyName); //dtyNameToRty[dtyID]+'-'+

                  //sort RectypeName.FieldName
                  dtys[dtyName].sort();
                  
                  for (j in dtys[dtyName]){
                    fieldName = dtys[dtyName][j];
                    
                    opt = window.hWin.HEURIST4.ui.addoption(selObj, '',  fieldName);
                    $(opt).attr('depth',1);
                    //opt.innerHTML = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"+fieldName;
                    opt.disabled = "disabled";

                  }
                }
            }else{
                window.hWin.HEURIST4.ui.addoption(selObj, '', window.hWin.HR('no suitable fields'));
            }
            
        }else 
        //details for the only recordtype
        if((window.hWin.HEURIST4.util.isArrayNotEmpty(rtyIDs) && rtyIDs.length==1) || Number(rtyIDs)>0){
            //structure not defined
            var rectype = Number((window.hWin.HEURIST4.util.isArray(rtyIDs))?rtyIDs[0]:rtyIDs);
            
            if(!(window.hWin.HEURIST4.rectypes && window.hWin.HEURIST4.rectypes.typedefs)) return selObj;
            var rectypes = window.hWin.HEURIST4.rectypes.typedefs[rectype];

            if(!rectypes) return selObj;
            details = rectypes.dtFields;

            var fi = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_DisplayName'],
            fit = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['dty_Type'],
            fir = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_RequirementType'];

            var rst_fi = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex;

            var arrterm = [];
            
            var child_rectypes = [];
            if(show_parent_rt){
                var DT_PARENT_ENTITY  = window.hWin.HAPI4.sysinfo['dbconst']['DT_PARENT_ENTITY'];
                //get all child rectypes
                for (rty in window.hWin.HEURIST4.rectypes.typedefs) {
                    for (dty in window.hWin.HEURIST4.rectypes.typedefs[rty].dtFields) {
                        var dtValue = window.hWin.HEURIST4.rectypes.typedefs[rty].dtFields[dty];
                        if(dtValue[rst_fi['dty_Type']]=='resource' && dtValue[rst_fi['rst_CreateChildIfRecPtr']]==1){
                            var constraint = dtValue[rst_fi['rst_PtrFilteredIDs']];
                            if(constraint.split(',').indexOf((''+rectype))>=0){
                            
                                var name = 'Parent record ('+window.hWin.HEURIST4.rectypes.names[rty]+')';
                                
                                if(showDetailType){
                                    name = name + ' [resource]';
                                }

                                arrterm.push([DT_PARENT_ENTITY, name, false]);    
                                
                                break;
                            }
                        }
                    }
                }
            }
                        

            for (dtyID in details){
                if(dtyID){
                    
                    if(details[dtyID][fir]=="forbidden") continue;

                    if(allowedlist==null || allowedlist.indexOf(details[dtyID][fit])>=0)
                    {
                        
                        var name = details[dtyID][fi];
                        
                        if(showDetailType){
                            name = name + ' ['+window.hWin.HEURIST4.detailtypes.lookups[ details[dtyID][fit] ]+']';
                        }

                        if(!window.hWin.HEURIST4.util.isnull(name)){
                                arrterm.push([dtyID, name, (details[dtyID][fir]=="required") ]);    
                        }
                        
                    }
                    if(addLatLongForGeo && details[dtyID][fit]=="geo"){
                        arrterm.push([ 'longitude', 'geo: Longitude', false ]);
                        arrterm.push([ 'latitude', 'geo: Latitude', false ]);
                    } 
                }
            }

            //sort by name
            arrterm.sort(function (a,b){ return a[1]<b[1]?-1:1; });
            //add to select
            var i=0, cnt= arrterm.length;
            for(;i<cnt;i++) {
                var opt = window.hWin.HEURIST4.ui.addoption(selObj, arrterm[i][0], arrterm[i][1]);
                if(arrterm[i][2] && requriedHighlight){
                    opt.className = "required";
                }
                $(opt).attr('depth',initial_indent);
            }

        }
        // for all fields
        else{ //show all detail types

            if(!window.hWin.HEURIST4.detailtypes) return selObj;

            var detailtypes = window.hWin.HEURIST4.detailtypes;
            var fit = detailtypes.typedefs.fieldNamesToIndex['dty_Type'];


            for (index in detailtypes.groups){
                if (index == "groupIDToIndex" ||
                    detailtypes.groups[index].showTypes.length < 1) {   //ignore empty group
                    continue;
                }

                var arrterm = [];

                for (var dtIDIndex in detailtypes.groups[index].showTypes)
                {
                    var detailID = detailtypes.groups[index].showTypes[dtIDIndex];
                    if(allowedlist==null || allowedlist.indexOf(detailtypes.typedefs[detailID].commonFields[fit])>=0)
                    {
                        var name = detailtypes.names[detailID];

                        if(!window.hWin.HEURIST4.util.isnull(name)){
                            arrterm.push([detailID, name]);
                        }
                    }
                }

                if(arrterm.length>0){
                    var grp = document.createElement("optgroup");
                    grp.label = detailtypes.groups[index].name;
                    selObj.appendChild(grp);
                    //sort by name
                    arrterm.sort(function (a,b){ return a[1]<b[1]?-1:1; });
                    //add to select
                    var i=0, cnt= arrterm.length;
                    for(;i<cnt;i++) {
                        var opt = window.hWin.HEURIST4.ui.addoption(selObj, arrterm[i][0], arrterm[i][1]);
                        $(opt).attr('depth',1);
                    }
                }

            }

        }
        
        if(options && options['bottom_options']){
            window.hWin.HEURIST4.ui.fillSelector(selObj, options['bottom_options']);
        }   

        if(selectedValue){
            $(selObj).val(selectedValue);
        }

        selObj = window.hWin.HEURIST4.ui.initHSelect(selObj, useHtmlSelect); 
        
        return selObj;
    },

    /**
    *  get SELECT for list of given groups, othewise loads list of groups for current user    
    */
    createUserGroupsSelect: function(selObj, groups, topOptions, callback) {

        $(selObj).empty();

        if(groups=='all'){  //all groups - sorted by name
            
            groups = window.hWin.HAPI4.sysinfo.db_usergroups;
            
        }else 
        if(groups=='all_my_first'){ //all groups by name - my groups first
            
            if(!topOptions) topOptions = [];
            for (var groupID in window.hWin.HAPI4.currentUser.ugr_Groups)
            if(groupID>0){
                var name = window.hWin.HAPI4.sysinfo.db_usergroups[groupID];
                if(!window.hWin.HEURIST4.util.isnull(name)){
                        topOptions.push({key:groupID, title:name});
                }
            }
            topOptions.push({key:0, title:'------'});
            
            groups = window.hWin.HAPI4.sysinfo.db_usergroups;
            
        }else 
        if(!groups){ //use groups of current user
        
            groups = {};
            for (var groupID in window.hWin.HAPI4.currentUser.ugr_Groups)
            if(groupID>0){
                var name = window.hWin.HAPI4.sysinfo.db_usergroups[groupID];
                if(!window.hWin.HEURIST4.util.isnull(name)){
                        groups[groupID] = name;
                }
            }
        }

        var idx;
        var addedontop = [];
        if(topOptions){  //list of options that must be on top of list
            for (idx in topOptions)
            {
                if(idx){
                    var key = topOptions[idx].key;
                    var title = topOptions[idx].title;
                    if(!window.hWin.HEURIST4.util.isnull(title))
                    {
                        window.hWin.HEURIST4.ui.addoption(selObj, key, title);
                        addedontop.push(key);
                    }
                }
            }
        }
        if(groups){

            for (var groupID in groups)
            {
                if(groupID>0 && addedontop.indexOf(groupID)<0){
                    var name = groups[groupID];
                    if($.isArray(name)) name = name[1] //backward
                    if(!window.hWin.HEURIST4.util.isnull(name))
                    {
                        window.hWin.HEURIST4.ui.addoption(selObj, groupID, name);
                    }
                }
            }
        }

        if(typeof callback === "function"){
            callback();
        }
    },
    
    initHSelect: function(selObj, useHtmlSelect){            

        //var isNotFirefox = (navigator.userAgent.indexOf('Firefox')<0);
        ////depth>1 || (optgroup==null && depth>0
        
        //for usual HTML select we have to add spaces for indent
        if(useHtmlSelect){
            
            $(selObj).find('option').each(
                function(idx, item){
                    var opt = $(item);
                    var depth = parseInt(opt.attr('depth'));
                    if(depth>0) { 
                        //for non mozilla add manual indent
                        var a = new Array( 2 + ((depth<7)?depth:7)*2 );
                        opt.html(a.join('&nbsp;&nbsp;') + opt.text());       
                    }
            });
            
        }else{
            
            var menu = $(selObj).hSelect(       
              { style: 'dropdown',
                appendTo: $(selObj).parent(),
                /*positionOptions: {
                    collision: 'none',
                    my: "left top",
                    at: "left bottom",
                    offset: null
                },*/
                  change: function( event, data ) {
    
                        $(selObj).val(data.item.value);//.change();
                        $(selObj).trigger('change');
                }});
            
            menu.hSelect( "menuWidget" ).css({'padding':0,'background':'#F4F2F4','zIndex':9999999});
            menu.hSelect( "menuWidget" ).addClass('heurist-selectmenu overflow').css({'max-height':'300px'});
            menu.hSelect( "widget" ).css({'padding':0,'background':'#FFF',width:'auto','min-width':'16em'}); //'#F4F2F4'
        }
        return $(selObj);
    },           
    
    //
    // exp_level 2 beginner, 1 intermediate, 0 expert
    //
    applyCompetencyLevel: function(exp_level, $context){

            if(!(exp_level>=0)){
                exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);
                if(isNaN(Number(exp_level))) exp_level = 2; //beginner by default
            }
            
            var is_exit = false;
            if(!$context){
                is_exit = true;
                $context = $(window.hWin.document);
            }
        
            if(exp_level>1){
                //show beginner level
                $context.find('.heurist-helper2').css('display','block');
                $context.find('.heurist-table-helper2').css('display','table-cell');
            }else{
                $context.find('.heurist-table-helper2').css('display','none');
                $context.find('.heurist-helper2').css('display','none');
            }
      
            if(exp_level>0){
                //show beginner and intermediate levels
                $context.find('.heurist-helper1').css('display','block');
                $context.find('.heurist-table-helper1').css('display','table-cell');
            }else{
                $context.find('.heurist-table-helper1').css('display','none');
                $context.find('.heurist-helper1').css('display','none');
            }
            
            if($context.hasClass('manageRecords')){
                //special bhaviour for record edit form
                var prefs = window.hWin.HAPI4.get_prefs_def('prefs_records');
                if(prefs){
                    var ishelp_on = (prefs['help_on']==true || prefs['help_on']=='true');
                    window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, $context);
                }
            }
            
            $context.trigger('competency', exp_level); //some contexts need specific behaviour to apply the level
            
            //if(is_exit) return;
            //window.hWin.HEURIST4.ui.applyCompetencyLevel( exp_level );
    }, 
      
    // Init button that show/hide help tips
    //
    //  usrPrefKey - prefs_entityName - context 
    //
    initDialogHintButtons: function($dialog, button_container_id, helpcontent_url, hideHelpButton){
        
        var titlebar = $dialog.parent().find('.ui-dialog-titlebar');
        if(titlebar.length==0){
            titlebar = $dialog.find(button_container_id);
        } 
        
        if(!hideHelpButton){
            var $help_menu = $('<ul><li data-level="2"><a><span class="ui-icon"/>Beginner</a></li>'
                +'<li data-level="1"><a><span class="ui-icon"/>Intermediate</a></li>'
                +'<li data-level="0"><a><span class="ui-icon"/>Expert</a></li><ul>')
                .width(150).hide().appendTo($dialog);
            
        var $help_button = $('<div>').button({icons: { primary: "ui-icon-book" }, label:'Show help hints', text:false})
                    .addClass('dialog-title-button')
                    .css({'right':'48px'})
                    .appendTo(titlebar)
                    .on('click', function(event){
                           //show popup menu 
                           var exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);
                           if(exp_level=='beginner') exp_level = 2;
                           
                           $help_menu.find('span').removeClass('ui-icon-check');
                           $help_menu.find('li[data-level="'+exp_level+'"] > a > span').addClass('ui-icon-check');
                           
                           
                           if($help_menu.parent().length==0){
                               $help_menu.menu().appendTo($help_button.parents('.ui-dialog').find('.ui-dialog-content'));
                           }
                           $help_menu.menu().on( {
                               //mouseenter : function(){_show(this['menu_'+name], this['btn_'+name])},
                               click: function(event){ 
                                   //change level
                                   var exp_level = $(event.target).parents('li').attr('data-level');

                                   window.hWin.HAPI4.save_pref('userCompetencyLevel', exp_level);

                                   window.hWin.HEURIST4.ui.applyCompetencyLevel(exp_level, $dialog);

                                   $help_menu.find('span').removeClass('ui-icon-check');
                                   $help_menu.find('li[data-level="'+exp_level+'"] > a > span').addClass('ui-icon-check');
                                   $help_menu.hide();
                               },
                               mouseleave : function(){ $help_menu.hide()}
                           });
            
                           
                           $help_menu.show().css('z-index',9999999)
                            .position({my: "right top+10", at: "right bottom", of: $help_button });
                            
                           //window.hWin.HEURIST4.ui.applyCompetencyLevel(exp_level, $dialog); 
                    });


           //window.hWin.HEURIST4.ui.switchHintState(usrPrefKey, $dialog, false);         
        }

        if(helpcontent_url){                    
            var $info_button = $('<div>')
                    .addClass('dialog-title-button')
                    .css({'right':'26px'})
                    .appendTo(titlebar);
                    
            window.hWin.HEURIST4.ui.initHelper($info_button, null, helpcontent_url);
        }
                    
    },
      
    //
    // not used anymore
    //                  
    switchHintState: function(usrPrefKey, $dialog, needReverse){
            
            var ishelp_on, prefs;
            if(usrPrefKey==null){
                ishelp_on = window.hWin.HAPI4.get_prefs('help_on');   
            }else{
                prefs = window.hWin.HAPI4.get_prefs(usrPrefKey);   
                ishelp_on = prefs ?prefs.help_on:true;
            }

            //change to reverse
            ishelp_on = (ishelp_on==1 || ishelp_on==true || ishelp_on=='true');
            if(needReverse){
                ishelp_on = !ishelp_on;
                if(usrPrefKey==null){
                    window.hWin.HAPI4.save_pref('help_on',ishelp_on);
                }else{
                    if(!prefs) prefs = {};
                    prefs.help_on = ishelp_on;
                    window.hWin.HAPI4.save_pref(usrPrefKey, prefs);
                }
            }
            
            window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, $dialog);
            
    },
    
    //
    //  used in manageRecords only
    //
    switchHintState2: function(state, $container){
        
            if(state){
                //$help_button.addClass('ui-state-focus');    
                $container.find('.heurist-helper1').css('display','block');
                $container.find('div.div-table-cell.heurist-helper1').css('display','table-cell');
            }else{
                //$help_button.removeClass('ui-state-focus');
                $container.find('.heurist-table-helper1').css('display','none');
                $container.find('.heurist-helper1').css('display','none');
            }
    },
    
    //
    // Inits helper div (slider) and button
    // 
    // position top|button- @todo auto detect position
    //
    initHelper: function(help_button, content_title, content_url, position, nobutton){

        var $help_button = $(help_button);

        if(nobutton!==true){
            $help_button.button({icons: { primary: "ui-icon-circle-b-info" }, label:'Show context help', text:false});
        }
        
        $help_button.on('click', function(){
                        var $helper_div = $(document.body).find('#helper');
                        
                        if($helper_div.length==0){
                            $helper_div = $('<div>',{id:'helper'}).hide().appendTo($(document.body));
                            
                            $helper_div.dialog({
                                        autoOpen: false, 
                                        title: window.hWin.HR(content_title),
                                        show: {
                                            effect: "slide",
                                            direction : 'right',
                                            duration: 1000
                                        },
                                        hide: {
                                            effect: "slide",
                                            direction : 'right',
                                            duration: 1000
                                        }
                                     });                 
                        }
                        
                        if($helper_div.dialog( "isOpen" )){
                            $helper_div.dialog( "close" );
                        }else{                        
                        
                            //var div_height = Math.min(500, (document.body).height()-$help_button.top());
                            //var div_width  = Math.min(600, (document.body).width() *0.8);
                            divpos = null;
                            if($.isPlainObject(position)){
                                divpos = position;
                                //divpos['of'] = $help_button;
                            }else if(position=='top'){ //show div above button
                                divpos = { my: "right bottom", at: "right top", of: $help_button }
                            }else{
                                divpos = { my: "right top", at: "right bottom", of: $help_button };
                            }

                           
                            $helper_div.load(content_url, function(){

                                var div_height = Math.min(500, $(document.body).height()-$help_button.position().top);
                                var div_width  = Math.min(700, $(document.body).width() *0.8);
                               
                                var title = (content_title)?content_title:'Heurist context help';
                                var head = $helper_div.find('#content>h2');
                                if(head.length==1){
                                    title = head.text();
                                    head.empty();
                                }
                            
                                if(title!='') $helper_div.dialog('option','title',title);
                                $helper_div.dialog('option', {width:div_width, height: div_height, position: divpos});
                                $helper_div.dialog( "open" );
                                setTimeout(function(){
                                        $helper_div.find('#content').scrollTop(1);
                                }, 1000);
                            });
                        }
                 });
                 
                 
    },

    //
    // important manageRecords.js and selectRecords.js must be loaded
    // if callback is defined it returns added/edited record as recordset
    //             
    openRecordEdit:function(rec_ID, query_request, popup_options){
        
        /*
                var usrPreferences = window.hWin.HAPI4.get_prefs_def('edit_record_dialog', 
                        {width: (window.hWin?window.hWin.innerWidth:window.innerWidth)*0.95,
                        height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });
        */
    
                var $container;
                var isPopup = false;
                
                if(popup_options && 
                    $.isPlainObject(popup_options.new_record_params) && popup_options.new_record_params['rt']>0){
                    //rec_ID = -1;
                    query_request = null;
                }
                
                popup_options = $.extend(popup_options, {
                    select_mode: 'manager',
                    edit_mode: 'editonly', //only edit form is visible, list is hidden
                    //height: usrPreferences.height,
                    //width: usrPreferences.width,
                    select_return_mode:'recordset',
                    
                    title: window.hWin.HR('Edit record'),
                    layout_mode:'<div class="ent_wrapper editor">'
                        + '<div class="ent_content_full recordList"  style="display:none;"/>'

                        + '<div class="ent_header editHeader"></div>'
                        + '<div class="editFormDialog ent_content_full">'
                                + '<div class="ui-layout-center"><div class="editForm"/></div>'
                                + '<div class="ui-layout-east"><div class="editFormSummary">....</div></div>'
                                //+ '<div class="ui-layout-south><div class="editForm-toolbar"/></div>'
                        + '</div>'
                        //+ '<div class="ent_footer editForm-toolbar"/>'
                    +'</div>',
                    onInitFinished:function( ){
                        
                        if(query_request){
                            if(!$.isPlainObject(query_request)){ //just string
                                query_request = {q:query_request, w:'all'};
                            }
                        }else if(rec_ID>0){
                            query_request = {q:'ids:'+rec_ID, w:'all'};
                        }
                        
                        var widget = this; //reference to manageRecords
                        
                        //find record or add after complete of initialiation of popup
                        if(query_request){
                            
                            query_request['limit'] = 100;
                            query_request['needall'] = 1;
                            query_request['detail'] = 'ids';
                        
                            window.hWin.HAPI4.RecordMgr.search(query_request, 
                            function( response ){
                                //that.loadanimation(false);
                                if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                                    
                                    var recset = new hRecordSet(response.data);
                                    if(recset.length()>0){
/*                                        
                                        widget.manageRecords('updateRecordList', null, {recordset:recset});
                                        widget.manageRecords('addEditRecord', 
                                                        (rec_ID>0)?rec_ID:recset.getOrder()[0]);
*/                                                        
                                        widget.updateRecordList(null, {recordset:recset});
                                        widget.addEditRecord( (rec_ID>0)?rec_ID:recset.getOrder()[0] );
                                                        
                                    }
                                    else {
                                        widget.closeEditDialog();
                                        //window.close();  //nothing found
                                    }
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                    widget.closeEditDialog();
                                    //window.close();
                                }

                            });
                        
                        }else{
                            widget.addEditRecord(-1);
                            //$container.manageRecords('addEditRecord',-1);
                        }                            
                        
                    },
                    //selectOnSave: $.isFunction(callback),
                    //onselect: callback
                });    
    
                window.hWin.HEURIST4.ui.showEntityDialog('Records', popup_options);
    },
    
    //
    //  
    //
    openRecordInPopup:function(rec_ID, query_request, isEdit, popup_options){
    
            var url = window.hWin.HAPI4.baseURL,
                dwidth, dheight, dtitle;    
            
            if(!popup_options) popup_options = {};
                
            if(isEdit==true){
                window.hWin.HEURIST4.ui.openRecordEdit(rec_ID, query_request, popup_options);
                return;
                
                // section below NOT USED
                // it loads manageRecords in popup iframe
                /*  
                var usrPreferences = window.hWin.HAPI4.get_prefs_def('edit_record_dialog', 
                        {width: (window.hWin?window.hWin.innerWidth:window.innerWidth)*0.95,
                        height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });

                url = url + 'hclient/framecontent/recordEdit.php?popup=1&';
                
                if(query_request){
                    if($.isPlainObject(query_request)){
                        url = url + window.hWin.HEURIST4.util.composeHeuristQueryFromRequest(query_request, true);
                    }else{
                        url = url + 'db=' + window.hWin.HAPI4.database + '&q=' + encodeURIComponent(query_request);                              }
                }else{
                    url = url + 'db=' + window.hWin.HAPI4.database;
                }
                
                url = url + '&recID='+rec_ID;
                    
                dtitle = 'Edit record';
                dheight = usrPreferences.height;
                dwidth = usrPreferences.width;
                */
            }else{
                url = url + 'records/view/renderRecordData.php?db='+window.hWin.HAPI4.database
                +'&recID='+ rec_ID;
                                                        
                dtitle = 'Record Info';
                dheight = 640;
                dwidth = 800;
            
                var $dosframe = window.hWin.HEURIST4.msg.showDialog(url, {
                    height:dheight, 
                    width:dwidth,
                    padding:0,
                    title: window.hWin.HR(dtitle),
                    class:'ui-heurist-bg-light',
                    callback: popup_options.callback,
                    beforeClose: function(){
                        //access manageRecord within frame within this popup and call close prefs
                        if($.isFunction($dosframe[0].contentWindow.onBeforeClose)){
                                $dosframe[0].contentWindow.onBeforeClose();
                        }
                    }
                    });
            }        
    },
    
    //
    // info {rec_ID,rec_Title,rec_RecTypeID,relation_recID,trm_ID}
    //
    // selector_function opens select dialog. it it is true it opens record edit popup dialog
    createRecordLinkInfo:function(container, info, selector_function){
        
        var ph_gif = window.hWin.HAPI4.baseURL + 'hclient/assets/16x16.gif';
        var sRelBtn = '';
        
        var isEdit = (selector_function!==false);
        
        
        if(info['trm_ID']>0){
            sRelBtn = '<div style="float:right;margin-left:0.5em"><div class="btn-rel"/><div class="btn-del"/></div>';
        }else{
            sRelBtn = '<div style="float:right;margin-left:0.5em"><div class="btn-edit"/></div>';     // data-recID="'+info['rec_ID']+'"
        }
        var ele = $('<div class="link-div ui-widget-content ui-corner-all"  data-relID="'
                        +(info['relation_recID']>0?info['relation_recID']:'')+'" '
                        +' style="margin-bottom:0.2em;background:#F4F2F4 !important;">' //padding-bottom:0.2em;
                        + (info['trm_ID']>0
                           ?'<div class="detailType" style="display:inline-block;width:15ex;padding-top:4px;float:left;">'
                            + window.hWin.HEURIST4.ui.getTermValue(info['trm_ID'])+'</div>'
                           :'')  
                        + '<div class="detail" '  // truncate
                        + 'style="display:inline-block;min-width:60ex;padding:2px;max-width:160ex;">'
                        + (info['rec_IsChildRecord']==1?'<span style="font-size:0.8em;color:#999999;margin:2px">child</span>':'')
                        + (isEdit?'<span class="ui-icon ui-icon-triangle-1-e" style="display: inline-block;vertical-align: middle;margin:2px"/>':'') //&nbsp;&nbsp;&nbsp;
                        + '<img src="'+ph_gif+'" style="vertical-align:top;margin-top:2px;margin-right:10px;background-image:url(\''
                        + top.HAPI4.iconBaseURL+info['rec_RecTypeID']    //rectype icon
                        + '\');"/>'
                        //2017-11-08 no more link here
                        //+ '<a target=_new href="#" data-recID="'+info['rec_ID'] +'">'
                        //+ window.hWin.HEURIST4.util.htmlEscape(info['rec_Title'])+'</a>'
                        + '<span data-recID="'+info['rec_ID'] +'">'
                        + window.hWin.HEURIST4.util.htmlEscape(info['rec_Title'])
                        + '</span>'
                        + sRelBtn
                        + '</div>'
                        + '</div>')
        .appendTo($(container));
        
        
        if(isEdit){
            
            if($.isFunction(selector_function)){
                var triangle_icon = ele.find('.ui-icon-triangle-1-e');
                if(triangle_icon.length>0){
                   ele.find('.detail').css({'cursor':'hand'});
                   triangle_icon.click(selector_function);
                }
                ele.find('span[data-recID='+info['rec_ID']+']').click(selector_function);
            }
            
            //remove button
            ele.find('.btn-del').button({text:false, label:top.HR('Remove '+(info['relation_recID']>0?'relation':'link')),
                            icons:{primary:'ui-icon-circlesmall-close'}})
            .css({'font-size': '0.8em', height: '18px', 'max-width': '18px'})
            .click(function(event){
                window.hWin.HEURIST4.msg.showMsgDlg(
                    'You are about to delete link between records<br><br>Are you sure?',
                     function(){
                        
                          var recID = ele.attr('data-relID');
                         
                          if(recID>0){  
                         
                              var url = window.hWin.HAPI4.baseURL + 'hapi/php/deleteRecord.php';

                              var request = {
                                db: window.hWin.HAPI4.database,
                                id: recID
                              }
                             
                              window.hWin.HEURIST4.util.sendRequest(url, request, null, function(response){
                                  if(response){
                                      if(response.error){
                                          window.hWin.HEURIST4.msg.showMsgErr( response.error );
                                      }else if(response.deleted>0){
                                          //link is deleted - remove this element
                                          ele.trigger('remove');
                                          ele.remove();
                                          window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Relation has been deleted'));
                                          
                                          if($(container).find('.link-div').length==0){
                                                $(container).find('.add-rel-button').show();
                                          }
                                      }
                                  }
                              });
                          
                          }else{
                              //remove link field
                              
                              //todo
                          }
                     },
                     {title:'Warning',yes:'Proceed',no:'Cancel'});
            });
        }
        

        if(info['relation_recID']>0){
            
            ele.find('.btn-rel').button({text:false, label:top.HR((isEdit?'Edit':'View')+' relationship record'),
                            icons:{primary:'ui-icon-pencil'}})
            .css({'font-size': '0.8em', height: '18px', 'max-width': '18px'})
            .click(function(event){
                event.preventDefault();
                
                var recID = ele.attr('data-relID');
                window.hWin.HEURIST4.ui.openRecordInPopup(recID, null, isEdit,
                {selectOnSave:true, edit_obstacle: true, onselect:
                    function(event, res){
                        if(res && window.hWin.HEURIST4.util.isRecordSet(res.selection)){
                            var recordset = res.selection;
                            var DT_RELATION_TYPE = window.hWin.HAPI4.sysinfo['dbconst']['DT_RELATION_TYPE'];
                            var record = recordset.getFirstRecord();
                            var term_ID = recordset.fld(record,DT_RELATION_TYPE);
                            ele.find('.detailType').text(window.hWin.HEURIST4.ui.getTermValue(term_ID)); //update relation type
                        }
                }});
            });
        
        }
        
        /* 2017-08-11 no more link for edit linked record :(    
        ele.find('a').click(function(event){
            event.preventDefault();
            var inpt = $(event.target);
            var recID = inpt.attr('data-recID');
            window.hWin.HEURIST4.ui.openRecordInPopup(recID, null, isEdit,
            {selectOnSave:true, edit_obstacle: true, onselect: 
                    function(event, res){
                        if(res && window.hWin.HEURIST4.util.isRecordSet(res.selection)){
                            var recordset = res.selection;
                            var record = recordset.getFirstRecord();
                            var rec_Title = window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record,'rec_Title'));
                            inpt.text(rec_Title);
                        }
                    }}
                );
        });        
        */
        var btn_edit = ele.find('div.btn-edit');
        if(btn_edit.length>0){
            
            btn_edit.button({text:false, label:top.HR('Edit linked record'),
                            icons:{primary:'ui-icon-pencil'}})
                        .attr('data-recID', info['rec_ID'])
                        .css({'font-size': '0.8em', height: '18px', 'max-width': '18px'})
                        .click(function(event){
           
           
            var recID = $(event.target).parent('.ui-button').attr('data-recID');
          
            window.hWin.HEURIST4.ui.openRecordInPopup(recID, null, isEdit,
            {selectOnSave:true, edit_obstacle: true, onselect: 
                    function(event, res){
                        if(res && window.hWin.HEURIST4.util.isRecordSet(res.selection)){
                            var recordset = res.selection;
                            var record = recordset.getFirstRecord();
                            var rec_Title = window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record,'rec_Title'));
                            
                            ele.find('span[data-recID='+recID+']').text(rec_Title);
                        }
                    }}
                );
                            
                        });
        }
        
        $(container).find('.add-rel-button').hide();
        
        return ele;
    },
    
    //------------------------------------
    // configMode.entity
    // configMode.filter_group
    createEntitySelector: function(selObj, configMode, topOptions, callback){
       
        $(selObj).empty();
        
        var request = {a:'search','details':'name'};
        var fieldTitle;
        
        if(configMode.entity=='SysUsers'){
            fieldTitle = 'ugr_Name';
            request['entity'] = 'sysUGrps';
            request['ugr_Type'] = 'user';
            request['ugl_GroupID'] = configMode.filter_group;
            
        }else if(configMode.entity=='SysGroups'){
            fieldTitle = 'ugr_Name';
            request['entity'] = 'sysUGrps';
            request['ugr_Type'] = 'workgroup';
            request['ugl_UserID'] = configMode.filter_group;
            
        }else if(configMode.entity=='DefTerms'){
            fieldTitle = 'trm_Label';
            request['entity'] = 'defTerms';
            request['trm_Domain'] = configMode.filter_group;
            request['trm_ParentTermID'] = [0,'NULL']; //get vocabs only
            
        }else if(configMode.entity=='DefRecTypeGroups'){
            fieldTitle = 'rtg_Name';
            request['entity'] = 'defRecTypeGroups';
            
        }else if(configMode.entity=='DefDetailTypeGroups'){
            fieldTitle = 'dtg_Name';
            request['entity'] = 'defDetailTypeGroups';
            
        }else if(configMode.entity=='DefRecTypeGroups'){
            fieldTitle = 'rtg_Name';
            request['entity'] = 'defRecTypes';
            request['rty_RecTypeGroupID'] = configMode.filter_group;
            
        }else if(configMode.entity=='DefDetailTypeGroups'){
            fieldTitle = 'dtg_Name';
            request['entity'] = 'defDetailTypes';
            request['dty_DetailTypeGroupID'] = configMode.filter_group;
            
        }else if(configMode.entity=='SysImportFiles'){
            fieldTitle = 'sif_TempDataTable';//'imp_table';
            request['entity'] = 'sysImportFiles';
            request['ugr_ID'] = configMode.filter_group;
        }else{
            return;
        }
        
        
        
        window.hWin.HAPI4.EntityMgr.doRequest(request,
                    function(response){
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                            
                            var groups = new hRecordSet(response.data).makeKeyValueArray(fieldTitle);
                            
                            if(!window.hWin.HEURIST4.util.isArray(topOptions)){
                                if(topOptions==true){
                                    topOptions = [{key:'',title:window.hWin.HR('select...')}];
                                }else if(!window.hWin.HEURIST4.util.isempty(topOptions) && topOptions!==false){
                                    if(topOptions===true) topOptions ='';
                                    topOptions = [{key:'',title:topOptions}];
                                }
                            }
                            if(window.hWin.HEURIST4.util.isArray(topOptions) && window.hWin.HEURIST4.util.isArray(groups)){
                                groups = topOptions.concat(groups);
                            }else if(window.hWin.HEURIST4.util.isArray(topOptions)){
                                groups = topOptions;
                            }

                            window.hWin.HEURIST4.ui.createSelector(selObj, groups);
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                        
                        if($.isFunction(callback)){
                            callback();
                        }
                        
                    });
                              
        
        
    },

    //
    // checks wether the appropriate javascript is loaded
    //
    showEntityDialog: function(entityName, options){

        entityName = entityName.charAt(0).toUpperCase() + entityName.slice(1); //entityName.capitalize();
                            
        var widgetName = 'manage'+entityName;
        
        if(!options) options = {};
        if(options.isdialog!==false) options.isdialog = true; //by default popup      

        if($.isFunction($('body')[widgetName])){ //OK! widget script js has been loaded
        
            var manage_dlg;
            
            if(!options.container){
                
                manage_dlg = $('<div id="heurist-dialog-'+entityName+'-'+window.hWin.HEURIST4.util.random()+'">')
                    .appendTo( $('body') )
                    [widgetName]( options );
            }else{
                manage_dlg = $(options.container)[widgetName]( options );
            }
            
            return manage_dlg;
        
        }else{
            
            var path = window.hWin.HAPI4.baseURL + 'hclient/widgets/entity/';
            var scripts = [ path+widgetName+'.js'];
            
            //entities without search option
            if(!(entityName=='UsrBookmarks' || 
                 entityName=='SysIdentification' ||
                 entityName=='DefDetailTypeGroups' || 
                 entityName=='SysBugreport')){ 
                scripts.push(path+'search'+entityName+'.js');
            }
            
            //load missed javascripts
            $.getMultiScripts(scripts)
            .done(function() {
                // all done
                window.hWin.HEURIST4.ui.showEntityDialog(entityName, options);
            }).fail(function(error) {
                // one or more scripts failed to load
                window.hWin.HEURIST4.msg.showMsgWorkInProgress();
            }).always(function() {
                // always called, both on success and error
            });
            
        }
    },
 
    
    
}//end ui

}

/*
hSelect - decendant of jquery.selectmenu
*/
$.widget( "heurist.hSelect", $.ui.selectmenu, {
  _renderItem: function( ul, item ) {
    var li = $( "<li>" ),
      wrapper = $( "<div>", { text: item.label } );

    if ( item.disabled ) {
        li.addClass( "ui-state-disabled" );
    }      
    if ( $(item.element).attr('group') == 1 ){
        li.css({'opacity':1});  
        wrapper.css({'font-weight':'bold'});
    }
    if( $(item.element).hasClass('required')) {
        wrapper.addClass('required');  
    }


/*    
    if($(item.element).attr('depth')>0){
        console.log($(item.element).attr('depth')+'   '+item.label);
    }
*/    
    var depth = parseInt($(item.element).attr('depth'));
    if(!(depth>0)) depth = 0;
    wrapper.css('padding-left',(depth+0.2)+'em');
    
    /*icon
    $( "<span>", {
      style: item.element.attr( "data-style" ),
      "class": "ui-icon " + item.element.attr( "data-class" )
    })
      .appendTo( wrapper );
    */   

    return li.append( wrapper ).appendTo( ul );
  }
});
