/**
*  Various utility functions
*
* @todo - split to generic utilities and UI utilities
* @todo - split utilities for hapi and load them dynamically from hapi
*
* @see editing_input.js
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

if (!top.HEURIST4){
    top.HEURIST4 = {};
}
//init only once
if (!top.HEURIST4.util) 
{

top.HEURIST4.util = {


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

    cloneJSON:function (data){
        try{
            return JSON.parse(JSON.stringify(data));
        }catch (ex2){
            console.log('cannot clone json array '+data);
            return [];
        }
    },

    // get current font size in em
    em: function(input) {
        var emSize = parseFloat($("body").css("font-size"));
        return (emSize * input);
    },

    // get current font size in pixels
    px: function(input) {
        var emSize = parseFloat($("body").css("font-size"));
        return (input / emSize);
    },

    //
    // enable or disable element
    //
    setDisabled: function(element, mode){
      if(element){
          if(!$.isArray(element)){
                element = [element];
          }
          $.each(element, function(idx, ele){
              ele = $(ele);
              if (mode) {
                    ele.prop('disabled', 'disabled');
                    ele.addClass('ui-state-disabled');
              }else{
                    ele.removeProp('disabled');
                    ele.removeClass('ui-state-disabled');
              }
          });
      }
    },
    
    isIE: function () {
        var myNav = navigator.userAgent.toLowerCase();
        return (myNav.indexOf('msie') != -1) ? parseInt(myNav.split('msie')[1]) : false;
    },
    
    //
    //
    //
    checkProtocolSupport: function(url){
        
        if (top.HEURIST4.util.isIE()) { //This bastard always needs special treatment
        
            if (typeof (navigator.msLaunchUri) == typeof (Function)) {
                
                navigator.msLaunchUri(url,
                    function () { /* Success */ },
                    function () { /* Failure */ top.HEURIST4.util.showMsgErr('Not supported') });
                return;
            }
                
            try {
                var flash = new ActiveXObject("Plugin.mailto");
            } catch (e) {
                //not installed
            }
        } else { //firefox,chrome,opera
            //navigator.plugins.refresh(true);
            var mimeTypes = navigator.mimeTypes;
            var mime = navigator.mimeTypes['application/x-mailto'];
            if(mime) {
                //installed
            } else {
                //not installed
                 top.HEURIST4.util.showMsgErr('Not supported');
            }
        }      
      
        
    },

    //
    // from object to query string
    //
    composeHeuristQueryFromRequest: function(query_request, encode){
            var query_string = 'db=' + top.HAPI4.database;
        
            if(!top.HEURIST4.util.isnull(query_request)){

                query_string = query_string + '&w='+query_request.w;
                
                if(!top.HEURIST4.util.isempty(query_request.q)){
                    
                    if($.isArray(query_request.q)){
                        sq = JSON.stringify(query_request.q);
                    }else{
                        sq = query_request.q;
                    }
                    
                    query_string = query_string + '&q=' + (encode?encodeURIComponent(sq):sq);
                }
                if(!top.HEURIST4.util.isempty(query_request.rules)){
                    //@todo simplify rules array - rempove redundant info
                    query_string = query_string + '&rules=' + 
                        (encode?encodeURIComponent(query_request.rules):query_request.rules);
                }
            }else{
                query_string = query_string + '&w=all';
            }        
            return query_string;        
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

    //
    // topOptions either array or string
    // [{key:'',title:''},....]
    //
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
    *  Fills give SELECT selObj with list of current Workgroups
    */
    createUserGroupsSelect: function(selObj, groups, topOptions, callback) {

        $(selObj).empty();

        if(!groups){ //use groups of current user
            groups = top.HAPI4.currentUser.usr_GroupsList;
            if(!groups){
                //looad detailed info about Workgroups
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

    getScrollBarWidth: function() {
        var $outer = $('<div>').css({visibility: 'hidden', width: 100, overflow: 'scroll'}).appendTo('body'),
            widthWithScroll = $('<div>').css({width: '100%'}).appendTo($outer).outerWidth();
        $outer.remove();
        return 100 - widthWithScroll;
    },

    /**
    * convert wkt to
    * format - 0 timemap, 1 google
    *
    * @todo 2 - kml
    * @todo 3 - OpenLayers
    */
    parseCoordinates: function(type, wkt, format) {

        if(type==1 && typeof google.maps.LatLng != "function") {
            return null;
        }

        var matches = null;

        switch (type) {
            case "p":
            case "point":
                matches = wkt.match(/POINT\((\S+)\s+(\S+)\)/i);
                break;

            case "c":  //circle
            case "circle":
                matches = wkt.match(/LINESTRING\((\S+)\s+(\S+),\s*(\S+)\s+\S+,\s*\S+\s+\S+,\s*\S+\s+\S+\)/i);
                break;

            case "l":  //polyline
            case "polyline":
            case "path":
                matches = wkt.match(/LINESTRING\((.+)\)/i);
                if (matches){
                    matches = matches[1].match(/\S+\s+\S+(?:,|$)/g);
                }
                break;

            case "r":  //rectangle
            case "rect":
                //matches = wkt.match(/POLYGON\(\((\S+)\s+(\S+),\s*(\S+)\s+(\S+),\s*(\S+)\s+(\S+),\s*(\S+)\s+(\S+),\s*\S+\s+\S+\)\)/i);
                //break;
            case "pl": //polygon
            case "polygon":
                matches = wkt.match(/POLYGON\(\((.+)\)\)/i);
                if (matches) {
                    matches = matches[1].match(/\S+\s+\S+(?:,|$)/g);
                }
                break;
        }


        var bounds, southWest, northEast,
        shape  = null,
        points = []; //google points

        if(matches && matches.length>0){

            switch (type) {
                case "p":
                case "point":

                    if(format==0){
                        shape = { point:{lat: parseFloat(matches[2]), lon:parseFloat(matches[1]) } };
                    }else{
                        point = new google.maps.LatLng(parseFloat(matches[2]), parseFloat(matches[1]));

                        bounds = new google.maps.LatLngBounds(
                            new google.maps.LatLng(point.lat() - 0.5, point.lng() - 0.5),
                            new google.maps.LatLng(point.lat() + 0.5, point.lng() + 0.5));
                        points.push(point);
                    }

                    break;

                /*
                case "r":  //rectangle
                case "rect":

                    if(matches.length<6){
                        matches.push(matches[3]);
                        matches.push(matches[4]);
                    }

                    var x0 = parseFloat(matches[0]);
                    var y0 = parseFloat(matches[2]);
                    var x1 = parseFloat(matches[5]);
                    var y1 = parseFloat(matches[6]);

                    if(format==0){
                        shape  = [
                            {lat: y0, lon: x0},
                            {lat: y0, lon: x1},
                            {lat: y1, lon: x1},
                            {lat: y1, lon: x0},
                        ];

                        shape = {polygon:shape};
                    }else{

                        southWest = new google.maps.LatLng(y0, x0);
                        northEast = new google.maps.LatLng(y1, x1);
                        bounds = new google.maps.LatLngBounds(southWest, northEast);

                        points.push(southWest, new google.maps.LatLng(y0, x1), northEast, new google.maps.LatLng(y1, x0));
                    }

                    break;
                */
                case "c":  //circle
                case "circle":  //circle

                    if(format==0){

                        var x0 = parseFloat(matches[1]);
                        var y0 = parseFloat(matches[2]);
                        var radius = parseFloat(matches[3]) - parseFloat(matches[1]);

                        shape = [];
                        for (var i=0; i <= 40; ++i) {
                            var x = x0 + radius * Math.cos(i * 2*Math.PI / 40);
                            var y = y0 + radius * Math.sin(i * 2*Math.PI / 40);
                            shape.push({lat: y, lon: x});
                        }
                        shape = {polygon:shape};

                    }else{
                        /* ARTEM TODO
                        var centre = new google.maps.LatLng(parseFloat(matches[2]), parseFloat(matches[1]));
                        var oncircle = new google.maps.LatLng(parseFloat(matches[2]), parseFloat(matches[3]));
                        setstartMarker(centre);
                        createcircle(oncircle);

                        //bounds = circle.getBounds();
                        */
                    }

                    break;

                case "l":  ///polyline
                case "path":
                case "polyline":

                case "r":  //rectangle
                case "rect":
                case "pl": //polygon
                case "polygon":

                    shape = [];

                    var j;
                    var minLat = 9999, maxLat = -9999, minLng = 9999, maxLng = -9999;
                    for (j=0; j < matches.length; ++j) {
                        var match_matches = matches[j].match(/(\S+)\s+(\S+)(?:,|$)/);

                        var point = {lat:parseFloat(match_matches[2]), lon:parseFloat(match_matches[1])};

                        if(format==0){
                            shape.push(point);
                        }else{
                            points.push(new google.maps.LatLng(points.lat, points.lon));
                        }

                        if (point.lat < minLat) minLat = point.lat;
                        if (point.lat > maxLat) maxLat = point.lat;
                        if (point.lon < minLng) minLng = point.lon;
                        if (point.lon > maxLng) maxLng = point.lon;
                    }

                    if(format==0){
                        shape = (type=="l" || type=="polyline")?{polyline:shape}:{polygon:shape};
                    }else{
                        southWest = new google.maps.LatLng(minLat, minLng);
                        northEast = new google.maps.LatLng(maxLat, maxLng);
                        bounds = new google.maps.LatLngBounds(southWest, northEast);
                    }
            }

        }

        return (format==0)?shape:{bounds:bounds, points:points};
    },//end parseCoordinates

    //
    // Get CSS property value for a not yet applied class
    //
    getCSS: function (prop, fromClass) {
        var $inspector = $("<div>").css('display', 'none').addClass(fromClass);
        $("body").append($inspector); // add to DOM, in order to read the CSS property
        try {
            return $inspector.css(prop);
        } finally {
            $inspector.remove(); // and remove from DOM
        }
    },

    //
    // init helper div and button
    //
    initHelper: function(help_button, content_title, content_url){

        //add helper div
        if($(document.body).find('#helper').length==0){
            $(document.body).append($('<div>',{id:'helper'}));
        }
        
        var $help_button = $(help_button);
        var $helper_div = $(document.body).find('#helper');
        
        $help_button.button({icons: { primary: "ui-icon-help" }, text:false})
                    .on('click', 3, function(){
                        var $helper_div = $(document.body).find('#helper');
                        
                        if($helper_div.dialog( "isOpen" )){
                            $helper_div.dialog( "close" );
                        }else{
                            var div_height = Math.min(500, $(document.body).height()-$help_button.position().top);
                            var div_width  = Math.min(600, $(document.body).width() *0.8);
                            
                            $helper_div.dialog('option',{width:div_width, height: div_height});
                            $helper_div.dialog( "open" );
                        }
                 });
                 
                 
        $helper_div.load(content_url);
        
        //var div_height = Math.min(500, (document.body).height()-$help_button.top());
        //var div_width  = Math.min(600, (document.body).width() *0.8);
        
        $helper_div.dialog({
                    autoOpen: false, 
                    title: top.HR(content_title),
                    position: { my: "right top", at: "right bottom", of: $help_button },
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

}



$.getMultiScripts = function(arr, path) {
    var _arr = $.map(arr, function(scr) {
        return $.getScript( (path||"") + scr );
    });

    _arr.push($.Deferred(function( deferred ){
        $( deferred.resolve );
    }));

    return $.when.apply($, _arr);
}
