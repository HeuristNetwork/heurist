/**
*  Various utility functions
* 
* @todo - split to generic utilities and UI utilities
* @see editing_input.js
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
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


if (!top.HEURIST4){
    top.HEURIST4 = {};
}
if (! top.HEURIST4.util) top.HEURIST4.util = {


    isnull: function(obj){
        return ( (typeof obj==="undefined") || (obj===null));
    },

    isempty: function(obj){
        if (top.HEURIST4.util.isnull(obj)){
            return true;
        }else if(top.HEURIST4.util.isArray(obj)){
            return obj.length<1;
        }else{
            return (obj==="") || (obj==="null");    
        }

    },

    isNumber: function (n) {
        //return typeof n === 'number' && isFinite(n);
        return !isNaN(parseFloat(n)) && isFinite(n);
    },
    
    em: function(input) {
        var emSize = parseFloat($("body").css("font-size"));
        return (emSize * input);
    },
    
    px: function(input) {
        var emSize = parseFloat($("body").css("font-size"));
        return (input / emSize);
    },

    composeHeuristQuery2: function(params){
        if(params)
            return top.HEURIST4.util.composeHeuristQuery(params.q, params.w, params.rules, params.notes);
        else
            return '?';
    },
    
    composeHeuristQuery: function(query, domain, rules, notes){
            var query_to_save = []; 
            if(!(top.HEURIST4.util.isempty(domain) || domain=="all")){
                query_to_save.push('w='+domain);
            }
            if(!top.HEURIST4.util.isempty(query)){
               query_to_save.push('q='+query);
            }
            if(!top.HEURIST4.util.isempty(rules)){
               query_to_save.push('rules='+rules);
            }
            if(!top.HEURIST4.util.isempty(notes)){
               query_to_save.push('notes='+notes);
            }
            return '?'+query_to_save.join('&');
    },
    
    //
    // converts query string to object
    //
    parseHeuristQuery: function(qsearch)            
    {
        var domain = null, rules = '', notes = '';
        if(qsearch && qsearch.indexOf('?')==0){
            domain  = top.HEURIST4.util.getUrlParameter('w', qsearch);
            rules   = top.HEURIST4.util.getUrlParameter('rules', qsearch);
            notes   = top.HEURIST4.util.getUrlParameter('notes', qsearch);
            qsearch = top.HEURIST4.util.getUrlParameter('q', qsearch);
        }
        domain = (domain=='b' || domain=='bookmark')?'bookmark':'all';
        
        return {q:qsearch, w:domain, rules:rules, notes:notes};
    },

    getUrlParameter: function getUrlParameter(name, query){

        if(!query){
            query = window.location.search;
        }

        var regexS = "[\\?&]"+name+"=([^&#]*)";
        var regex = new RegExp( regexS );
        var results = regex.exec( query );

        if( results == null ) {
            return null;
        } else {
            return results[1];
        }
    },

    isArrayNotEmpty: function (a){
        return (top.HEURIST4.util.isArray(a) && a.length>0);
    },

    isArray: function (a)
    {
        return Object.prototype.toString.apply(a) === '[object Array]';
    },
    
    htmlEscape: function(s) {
        return s?s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/'/g, "&#39;").replace(/"/g, "&#34;"):'';
    },

    isObject: function (a)
    {
        return Object.prototype.toString.apply(a) === '[object Object]';
    },
    
    stopEvent: function(e){
        if (!e) e = window.event;

        if (e) {
            e.cancelBubble = true;
            if (e.stopPropagation) e.stopPropagation();
        }
        return e;
    },

    addoption: function(sel, value, text)
    {
        var option = document.createElement("option");
        option.text = text;
        option.value = value;
        try {
            // for IE earlier than version 8
            sel.add(option, sel.options[null]);
        }catch (ex2){
            sel.add(option, null);
        }
        return option;
    },


    getTermValue: function(datatype, termID, withcode){

        var terms = top.HEURIST4.terms;
        if(!terms || top.HEURIST4.util.isempty(termID)) return '';

        if(datatype === "relmarker" || datatype === "relationtype"){
            datatype = "relation";
        }
        if(!(datatype=="enum" || datatype=="relation")){
            return '';
        }

        var termLookup = terms.termsByDomainLookup[datatype],
        termName,
        termCode;

        if(termLookup[termID]){
            termName = termLookup[termID][terms.fieldNamesToIndex['trm_Label']];
            termCode = termLookup[termID][terms.fieldNamesToIndex['trm_Code']];
            if(top.HEURIST4.util.isempty(termCode)){
                termCode = '';
            }else{
                termCode = " ("+termCode+")";
            }
        }

        return termName+(withcode ?termCode :'');
    },

    // not used 
    getPlainTermsList: function(datatype, termIDTree, headerTermIDsList, selectedTermID) {
        var selObj = top.HEURIST4.util.createTermSelectExt(null, datatype, termIDTree, headerTermIDsList);

        var reslist = [];

        if(selObj){
            for (var i=0; i<selObj.length; i++){
                if(!selObj.options[i].disabled){
                    reslist.push({id:selObj.options[i].value, text:selObj.options[i].text});
                }
            }
        }
        return reslist;
    },

    //return term and its children as well as comma-separated list of non-disabled ancestors
    getChildrenTerms: function(datatype, termIDTree, headerTermIDsList, selectedTermID) {

        var termtree = top.HEURIST4.util.createTermSelectExt(null, datatype, termIDTree, headerTermIDsList, null, null, true);
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

        var root = {id:null, text:top.HR('all'), children:termtree};

        //__setParents(root, termtree);

        return top.HEURIST4.util.isnull(selectedTermID)?root:__findTerm(selectedTermID, root, termtree);
    },

    /**
    * create/fill SELECT for terms
    *
    * datatype enum|relation
    * termIDTree - json string or object (tree) OR number - in this case this vocabulary ID, if not defined all terms are taken from top.HEURIST4.terms.treesByDomain
    * headerTermIDsList - json string or array (disabled terms)
    * defaultTermID - term to be selected
    * topOptions - text or array for top most item(s)
    * needArray  return array tree if terms (instead of select element)
    * 
    */
    createTermSelectExt: function(selObj, datatype, termIDTree, headerTermIDsList, defaultTermID, topOptions, needArray) {
        return top.HEURIST4.util.createTermSelectExt2(selObj, 
            {datatype:datatype, termIDTree:termIDTree, headerTermIDsList:headerTermIDsList, 
             defaultTermID:defaultTermID, topOptions:topOptions, needArray:needArray});    
    },

    createTermSelectExt2: function(selObj, options) {
        
        var datatype =  options.datatype,
            termIDTree =  options.termIDTree,
            headerTermIDsList =  options.headerTermIDsList,
            defaultTermID =  options.defaultTermID,
            topOptions =  options.topOptions,
            needArray  =  options.needArray,
            supressTermCode = options.supressTermCode;
        
        
        if(needArray){

        }else{
            selObj = top.HEURIST4.util.createSelector(selObj, topOptions);
        }

        if(datatype === "relmarker" || datatype === "relationtype"){
            datatype = "relation";
        }

        var terms = top.HEURIST4.terms;
        if(!(datatype=="enum" || datatype=="relation") || !terms ){
            return needArray ?[] :selObj;
        }

        var termLookup = terms.termsByDomainLookup[datatype];

        //prepare header
        //
        var temp = ( top.HEURIST4.util.isArray(headerTermIDsList)   //instanceof(Array)
            ? headerTermIDsList
            : (( typeof(headerTermIDsList) === "string" && !top.HEURIST4.util.isempty(headerTermIDsList) )
                ? $.parseJSON(headerTermIDsList)  //headerTermIDsList.split(",")
                : [] ));

        var headerTerms = {};
        for (var id in temp) {
            headerTerms[temp[id]] = temp[id];
        }

        //
        //
        var isNotFirefox = (navigator.userAgent.indexOf('Firefox')<0);

        function createSubTreeOptions(optgroup, depth, termSubTree, termLookupInner, defaultTermID) {
            var termID;
            var localLookup = termLookupInner;
            var termName,
            termCode,
            arrterm = [],
            reslist2 = [];

            for(termID in termSubTree) { // For every term in 'term'
                termName = "";
                termCode = "";

                if(localLookup[termID]){
                    termName = localLookup[termID][terms.fieldNamesToIndex['trm_Label']];
                    termCode = localLookup[termID][terms.fieldNamesToIndex['trm_Code']];
                    if(supressTermCode || top.HEURIST4.util.isempty(termCode)){
                        termCode = '';
                    }else{
                        termCode = " ("+termCode+")";
                    }
                }

                if(top.HEURIST4.util.isempty(termName)) continue;

                arrterm.push([termID, termName, termCode]);
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

                if(isNotFirefox && (depth>1 || (optgroup==null && depth>0) )){
                    //for non mozilla add manual indent
                    var a = new Array( ((depth<7)?depth:7)*2 );
                    termName = a.join('. ') + termName;
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
                        opt.depth = depth;
                        opt.disabled = isDisabled;

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

                var children = (hasChildren)?createSubTreeOptions( new_optgroup, depth+1, termSubTree[termID], localLookup, defaultTermID):[];
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
        if(top.HEURIST4.util.isArray(termIDTree)){
            toparray = termIDTree;
        }else{
            toparray = [ termIDTree ];
        }
        
        var m, lenn = toparray.length;
        var reslist_final = [];
        
        for(m=0;m<lenn;m++){
            
            var termTree = toparray[m];
            
            //
            //prepare tree
            //
            if(top.HEURIST4.util.isNumber(termTree)){
                //this is vocabulary id - show list of all terms for this vocab
                var tree = terms.treesByDomain[datatype];
                termTree = tree[termTree];
            }else{
                termTree = (typeof termTree == "string") ? $.parseJSON(termTree) : null;
                if(termTree==null){
                    termTree = terms.treesByDomain[datatype];
                }
            }
            
            var reslist = createSubTreeOptions(null, 0, termTree, termLookup, defaultTermID);
            if(!selObj){
                reslist_final = reslist_final.concat( reslist);
            }
        }
        
        if(selObj){
            if (!defaultTermID) selObj.selectedIndex = 0;
            return selObj;
        }else{
            return reslist_final;
        }
    }

    ,createSelector: function(selObj, topOptions) {
        if(selObj==null){
            selObj = document.createElement("select");
        }else{
            $(selObj).empty();
        }

        if(top.HEURIST4.util.isArray(topOptions)){
            var idx;
            if(topOptions){  //list of options that must be on top of list
                for (idx in topOptions)
                {
                    if(idx){
                        var key = topOptions[idx].key;
                        var title = topOptions[idx].title;
                        if(!top.HEURIST4.util.isnull(title))
                        {
                            if(!top.HEURIST4.util.isnull(topOptions[idx].optgroup)){
                                var grp = document.createElement("optgroup");
                                grp.label =  title;
                                selObj.appendChild(grp);
                            }else{
                                top.HEURIST4.util.addoption(selObj, key, title);    
                            }

                        }
                    }
                }
            }        
        }else if(!top.HEURIST4.util.isempty(topOptions) && topOptions!==false){
            if(topOptions===true) topOptions ='';
            top.HEURIST4.util.addoption(selObj, '', topOptions); 
        }


        return selObj;
    }

    /**
    * create/fill SELECT for rectypes groups
    */
    ,createRectypeGroupSelect: function(selObj, topOptions) {

        top.HEURIST4.util.createSelector(selObj, topOptions);

        var rectypes = top.HEURIST4.rectypes,
        index;

        if(!rectypes) return selObj;


        for (index in rectypes.groups){
            if (index == "groupIDToIndex" ){
                //rectypes.groups[index].showTypes.length < 1) 
                continue;
            }

            var name = rectypes.groups[index].name;
            if(!top.HEURIST4.util.isnull(name)){
                top.HEURIST4.util.addoption(selObj, rectypes.groups[index].id, name);
            }
        }

        return selObj;

    }

    /**
    * create/fill SELECT for rectypes
    *
    * rectypeList - constraint options to this list, otherwise show entire list of rectypes
    */
    , createRectypeSelect: function(selObj, rectypeList, topOptions) {

        top.HEURIST4.util.createSelector(selObj, topOptions);

        var rectypes = top.HEURIST4.rectypes,
        index;

        if(!rectypes) return selObj;


        if(!top.HEURIST4.util.isempty(rectypeList)){

            if(!top.HEURIST4.util.isArray(rectypeList)){
                rectypeList = rectypeList.split(',');
            }

            for (var idx in rectypeList)
            {
                if(idx){
                    var rectypeID = rectypeList[idx];
                    var name = rectypes.names[rectypeID];
                    if(!top.HEURIST4.util.isnull(name))
                    {
                        top.HEURIST4.util.addoption(selObj, rectypeID, name);
                    }
                }
            }
        }else{
            for (index in rectypes.groups){
                if (index == "groupIDToIndex" ||
                    rectypes.groups[index].showTypes.length < 1) {
                    continue;
                }
                var grp = document.createElement("optgroup");
                grp.label = rectypes.groups[index].name;
                selObj.appendChild(grp);

                for (var recTypeIDIndex in rectypes.groups[index].showTypes)
                {
                    var rectypeID = rectypes.groups[index].showTypes[recTypeIDIndex];
                    var name = rectypes.names[rectypeID];

                    if(!top.HEURIST4.util.isnull(name)){
                        var opt = top.HEURIST4.util.addoption(selObj, rectypeID, name);
                    }
                }
            }
        }

        return selObj;
    },

    /**
    * create/fill SELECT for details of given recordtype
    *
    * allowedlist - constraint options to this list
    */
    createRectypeDetailSelect: function(selObj, rectype, allowedlist, topOptions, needEmpty) {

        top.HEURIST4.util.createSelector(selObj, topOptions);

        var dtyID, details;

        if(Number(rectype)>0){
            //structure not defined 
            if(!(top.HEURIST4.rectypes && top.HEURIST4.rectypes.typedefs)) return selObj;
            var rectypes = top.HEURIST4.rectypes.typedefs[rectype];

            if(!rectypes) return selObj;
            details = rectypes.dtFields;

            var fi = top.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_DisplayName'],
            fit = top.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['dty_Type'];

            var arrterm = [];

            for (dtyID in details){
                if(dtyID){

                    if(allowedlist==null || allowedlist.indexOf(details[dtyID][fit])>=0)
                    {
                        var name = details[dtyID][fi];

                        if(!top.HEURIST4.util.isnull(name)){
                            arrterm.push([dtyID, name]);
                        }
                    }
                }
            }

            //sort by name
            arrterm.sort(function (a,b){ return a[1]<b[1]?-1:1; });
            //add to select
            var i=0, cnt= arrterm.length;
            for(;i<cnt;i++) {
                top.HEURIST4.util.addoption(selObj, arrterm[i][0], arrterm[i][1]);  
            }

        }else{ //show all detail types

            if(!top.HEURIST4.detailtypes) return selObj;

            var detailtypes = top.HEURIST4.detailtypes;
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

                        if(!top.HEURIST4.util.isnull(name)){
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
                        top.HEURIST4.util.addoption(selObj, arrterm[i][0], arrterm[i][1]);  
                    }
                }

            }

        }


        return selObj;
    },

    /**
    *  Fills give SELECT selObj with list of current user groups
    */
    createUserGroupsSelect: function(selObj, groups, topOptions, callback) {

        $(selObj).empty();

        if(!groups){ //use groups of current user
            groups = top.HAPI4.currentUser.usr_GroupsList;
            if(!groups){        
                //looad detailed info about user groups
                top.HAPI4.SystemMgr.mygroups(
                    function(response){
                        if(response.status == top.HAPI4.ResponseStatus.OK){
                            groups = top.HAPI4.currentUser.usr_GroupsList = response.data;
                            top.HEURIST4.util.createUserGroupsSelect(selObj, groups, topOptions, callback);
                        }
                });
                return;    
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
                    if(!top.HEURIST4.util.isnull(title))
                    {
                        top.HEURIST4.util.addoption(selObj, key, title);
                        addedontop.push(key);
                    }
                }
            }
        }            
        if(groups){

            for (var idx in groups)
            {
                if(idx && addedontop.indexOf(idx)<0){
                    var groupID = idx;
                    var name = groups[idx][1];
                    if(!top.HEURIST4.util.isnull(name))
                    {
                        top.HEURIST4.util.addoption(selObj, groupID, name);
                    }
                }
            }
        }

        if(typeof callback === "function"){
            callback();
        }

    },

    showMsgErr: function(response){
        var msg;
        if(typeof response === "string"){
            msg = response;
        }else{
            msg = response.status;
            if(response.message){
                msg = msg + '<br>' + response.message;
            }
            if(response.sysmsg){

                if(typeof response.sysmsg['join'] === "function"){
                    msg = msg + '<br>' +response.sysmsg.join('<br>');
                }else{
                    msg = msg + '<br>' + response.sysmsg;
                }

            }
        }

        top.HEURIST4.util.showMsgDlg(msg, null, "Error");
    },
    
    //load content to dialog and show it
    showMsgDlgUrl: function(url, buttons, title){

        if(url){
            var $dlg = top.HEURIST4.util.getMsgDlg();
            $dlg.load(url, function(){
                top.HEURIST4.util.showMsgDlg(null, buttons, title);    
            });
        }
    },
    
    showMsgWorkInProgress: function( message ){
        
        if(top.HEURIST4.util.isempty(message)){
            message = "this feature";
        }
        
        message = "Beta version: we are still working on "
              + message 
              + "<br/><br/>Please email Heurist support (support _at_ HeuristNetwork.org)"
              + "<br/>if you need this feature and we will provide workarounds and/or fast-track your needs.";
            
        top.HEURIST4.util.showMsgDlg(message, null, "Work in Progress");
    },
    
    getMsgDlg: function(){
        var $dlg = $( "#dialog-common-messages" );
        if($dlg.length==0){
            $dlg = $('<div>',{id:'dialog-common-messages'}).css({'min-wdith':'380px','max-width':'640px'}).appendTo('body');
        }
        return $dlg.removeClass('ui-heurist-border');
    },

    //similar to  dialog-common-messages - but without width limit
    getPopupDlg: function(){
        var $dlg = $( "#dialog-popup" );
        if($dlg.length==0){
            $dlg = $('<div>',{id:'dialog-popup'}).css('padding','2em').css({'min-wdith':'380px'}).appendTo('body'); //,'max-width':'640px'
            $dlg.removeClass('ui-heurist-border');
        }
        return $dlg;
    },
    
    // buttons - callback function
    showMsgDlg: function(message, buttons, title, position_to_element, isPopupDlg){

        if(!$.isFunction(top.HR)){
            alert(message);
            return;
        }

        var $dlg = top.HEURIST4.util.getMsgDlg();
        var isPopup = false; //bigger and resizable

        if(message!=null){
            $dlg.empty();
            
            isPopupDlg = isPopupDlg || (message.indexOf('#')===0 && $(message).length>0);
            
            if(isPopupDlg){

                $dlg = top.HEURIST4.util.getPopupDlg();
                if(message.indexOf('#')===0 && $(message).length>0){
                    $dlg.html($(message).html());    
                }else{
                    $dlg.html(message);    
                }
                isPopup = true;
                
            }else{
                isPopup = false;
                $dlg.append('<span>'+top.HR(message)+'</span>');    
            }
        }

        if(!title) title = 'Info';
        if (typeof buttons === "function"){

            var titleYes = top.HR('Yes'),
            titleNo = top.HR('No'),
            callback = buttons;

            buttons = {};
            buttons[titleYes] = function() {
                callback.call();
                $dlg.dialog( "close" );
            };
            buttons[titleNo] = function() {
                $dlg.dialog( "close" );
            };
        }else if(!buttons){

            var titleOK = top.HR('OK');
            buttons = {};
            buttons[titleOK] = function() {
                $dlg.dialog( "close" );
            };

        }
        
        var options =  {
            title: top.HR(title),
            resizable: false,
            //height:140,
            width: 'auto',
            modal: true,
            closeOnEscape: true,
            buttons: buttons
        };
        if(isPopup){
            options.height = 515;
            options.width = 705;
            options.resizable = true;
            options.resizeStop = function( event, ui ) {
                    $dlg.css({overflow: 'none !important','width':'100%', 'height':$dlg.parent().height() 
                            - $dlg.parent().find('.ui-dialog-titlebar').height() - $dlg.parent().find('.ui-dialog-buttonpane').height() - 20 });
                };
        }
        
        if(position_to_element){
           options.position = { my: "left top", at: "left bottom", of: $(position_to_element) };
        }
        
        $dlg.dialog(options);
        
        //$dlg.parent().find('.ui-dialog-buttonpane').removeClass('ui-dialog-buttonpane');
        //$dlg.parent().find('.ui-dialog-buttonpane').css({'background-color':''});
        //$dlg.parent().find('.ui-dialog-buttonpane').css({'background':'red none repeat scroll 0 0 !important','background-color':'transparent !important'});
        //'#8ea9b9 none repeat scroll 0 0 !important'     none !important','background-color':'none !important
    },

    showMsgFlash: function(message, timeout, title, position_to_element){

        if(!$.isFunction(top.HR)){
            alert(message);
            return;
        }
        
        $dlg = top.HEURIST4.util.getMsgDlg();
        
        $dlg.addClass('ui-heurist-border');

        if(message!=null){
            $dlg.empty();
            $dlg.append('<span>'+top.HR(message)+'</span>');
        }

        if(!title) title = 'Info';
        
        var options =  {
            title: top.HR(title),
            resizable: false,
            //height:140,
            width: 'auto',
            modal: false,
            buttons: {}
        };
        
        
        if(position_to_element){
           options.position = { my: "left top", at: "left bottom", of: $(position_to_element) };
        }
        
        $dlg.dialog(options);
        $dlg.dialog("option", "buttons", null);
        
        if (!(timeout>200)) {
            timeout = 1000;
        }
    
        setTimeout(function(){
            $dlg.dialog('close');    
        }, timeout);
        
    },
        
    //@todo - redirect to error page
    redirectToError: function(message){
        top.HEURIST4.util.showMsgDlg(message, null, 'Error');
    },

    checkLength: function( input, title, message, min, max ) {
        var message_text = top.HEURIST4.util.checkLength2( input, title, min, max );
        if(message_text!=''){

            if(message){
                message.text(message_text);
                message.addClass( "ui-state-highlight" );
                setTimeout(function() {
                    message.removeClass( "ui-state-highlight", 1500 );
                    }, 500 );
            }

            return false;
        }else{
            return true;
        }

    },

    checkLength2: function( input, title, min, max ) {

        if ( (max>0 && input.val().length > max) || input.val().length < min ) {
            input.addClass( "ui-state-error" );
            if(max>0 && min>1){
                message_text = top.HR(title)+" "+top.HR("length must be between ") +
                min + " "+top.HR("and")+" " + max + ".";
            }else if(min==1){
                message_text = top.HR(title)+" "+top.HR("required field");
            }

            return message_text; 

        } else {
            return '';
        }
    },

    checkRegexp:function ( o, regexp ) {
        if ( !( regexp.test( o.val() ) ) ) {
            o.addClass( "ui-state-error" );
            return false;
        } else {
            return true;
        }
    },
    
    sendRequest: function(url, request, caller, callback){

        if(!request.db){
            request.db = top.HAPI4.database;
        }

        //note jQuery ajax does not properly in the loop - success callback does not work often   
        $.ajax({
            url: url,
            type: "POST",
            data: request,
            dataType: "json",
            cache: false,
            error: function(jqXHR, textStatus, errorThrown ) {
                if(callback){
                    callback(caller, {status:top.HAPI4.ResponseStatus.UNKNOWN_ERROR,
                        message: jqXHR.responseText });
                }
                //message:'Error connecting server '+textStatus});
            },
            success: function( response, textStatus, jqXHR ){
                if(callback){
                    callback(caller, response);
                }
            }
        });
    },

    /**
    * show url in iframe within popup dialog
    */
    showDialog: function(url, options){
    
                if(!options) options = {};
        
        
                var opener = options['window']?options['window'] :window;
        
                //.appendTo( that.document.find('body') )
                
                //create new div for dialogue with $(this).uniqueId();                 
                var $dlg = $('<div>')
                           .css('background','url('+top.HAPI4.basePath+'assets/loading-animation-white.gif) no-repeat center center')
                           .appendTo( $(opener.document).find('body') );
                           
                var $dosframe = $( "<iframe>" ).css({overflow: 'none !important', width:'100% !important'}).appendTo( $dlg );
                
                //on close event listener
                $dosframe[0].close = function() {
                    var rval = true;
                    var closeCallback = options['callback'];
                    if(closeCallback){
                        rval = closeCallback.apply(opener, arguments);
                    }
                    if ( !rval  &&  rval !== undefined){
                        return false;
                    }
                    
                    $dlg.dialog('close');
                    return true;
                };             
                
                //on load content event listener
                $dosframe.on('load', function(){
                        var content = $dosframe[0].contentWindow;
                        if(!options["title"]){
                            $dlg.dialog( "option", "title", content.document.title );
                        }
                        content.close = $dosframe[0].close;    // make window.close() do what we expect
                        //content.popupOpener = opener;  
                        
                        
                        var onloadCallback = options['onpopupload'];
                        if(onloadCallback){
                                onloadCallback.call(opener, $dosframe[0]);
                        }
                        
                        $dlg.css('background','none');
                });
                
//    options['callback']

                        var opts = {
                                autoOpen: true,
                                width : (options.width>0?options.width+20:690),
                                height: (options.height>0?options.height+20:690),
                                modal: true,
                                resizable: (options['no-resize']==true),
                                //draggable: false,
                                title: options["title"],
                                resizeStop: function( event, ui ) {
                                    $dosframe.css('width','100%');
                                },
                                close: function(event, ui){
                                    $dlg.remove();
                                }
                        };
                        $dosframe.attr('src', url);
                        $dlg.dialog(opts);
    },
    
    showElementAsDialog: function(options){
        
            var opener = options['window']?options['window'] :window;
        
            var $dlg = $('<div>')
               .appendTo( $(opener.document).find('body') );
               
            var element = options['element'];
            var originalParentNode = element.parentNode;
            element.parentNode.removeChild(element);
               
            $(element).show().appendTo($dlg);

            var opts = {
                    autoOpen: true,
                    width : (options.width>0?options.width+20:690),
                    height: (options.height>0?options.height+20:690),
                    modal: true,
                    resizable: (options['no-resize']==true),
                    //draggable: false,
                    title: options["title"],
                    close: function(event, ui){
                        
                        //var element = popup.element.parentNode.removeChild(popup.element);
                        element.style.display = "none";
                        originalParentNode.appendChild(element);
                        
                        $dlg.remove();
                    }
            };
            $dlg.dialog(opts);
        
            return {id:0};
    },
    
    getScrollBarWidth: function() {
        var $outer = $('<div>').css({visibility: 'hidden', width: 100, overflow: 'scroll'}).appendTo('body'),
            widthWithScroll = $('<div>').css({width: '100%'}).appendTo($outer).outerWidth();
        $outer.remove();
        return 100 - widthWithScroll;
    }


    }//end util

String.prototype.htmlEscape = function() {
    return this.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/'/g, "&#39;");
}

if (!Array.prototype.indexOf)
{
    Array.prototype.indexOf = function(elt /*, from*/)
    {
        var len = this.length;

        var from = Number(arguments[1]) || 0;
        from = (from < 0)
        ? Math.ceil(from)
        : Math.floor(from);
        if (from < 0)
            from += len;

        for (; from < len; from++)
        {
            if (from in this &&
                this[from] === elt)
                return from;
        }
        return -1;
    };
}
