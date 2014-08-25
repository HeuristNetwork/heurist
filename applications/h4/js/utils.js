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

    getUrlQueryAndDomain: function(qsearch)            
    {
        var domain = null;
        if(qsearch && qsearch.indexOf('?')==0){
            domain = top.HEURIST4.util.getUrlParameter('w', qsearch);
            qsearch = top.HEURIST4.util.getUrlParameter('q', qsearch);
        }
        domain = (domain=='b' || domain=='bookmark')?'bookmark':'all';
        return [qsearch, domain];
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
            sel.add(option,null);
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
    * headerTermIDsList - json string or array
    * defaultTermID - term to be selected
    * sFirstEmptyItem - text for first empty value item
    * needArray  return array tree if terms (instead of select element)
    * 
    */
    createTermSelectExt: function(selObj, datatype, termIDTree, headerTermIDsList, defaultTermID, topOptions, needArray) {

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

        //prepare tree
        //
        if(top.HEURIST4.util.isNumber(termIDTree)){
            //this is vocabulary id - show list of all terms for this vocab
            var tree = terms.treesByDomain[datatype];
            termIDTree = tree[termIDTree];
        }else{
            termIDTree = (typeof termIDTree == "string") ? $.parseJSON(termIDTree) : null;
            if(termIDTree==null){
                termIDTree =terms.treesByDomain[datatype];
            }
        }


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
                    if(top.HEURIST4.util.isempty(termCode)){
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

        var reslist = createSubTreeOptions(null, 0,termIDTree, termLookup, defaultTermID);
        if(selObj){
            if (!defaultTermID) selObj.selectedIndex = 0;
            return selObj;
        }else{
            return reslist;
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
    * rectypeList - constraint options to this list
    */
    , createRectypeSelect: function(selObj, rectypeList, topOptions) {

        top.HEURIST4.util.createSelector(selObj, topOptions);

        var rectypes = top.HEURIST4.rectypes,
        index;

        if(!rectypes) return selObj;


        if(rectypeList){

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

    getMsgDlg: function(){
        var $dlg = $( "#dialog-common-messages" );
        if($dlg.length==0){
            $dlg = $('<div>',{id:'dialog-common-messages'}).css({'min-wdith':'380px','max-width':'640px'}).appendTo('body');
        }
        return $dlg;
    },

    showMsgDlgUrl: function(url, buttons, title){

        if(url){
            $dlg = top.HEURIST4.util.getMsgDlg();
            $dlg.load(url, function(){
                top.HEURIST4.util.showMsgDlg(null, buttons, title);    
            });
        }
    },

    showMsgDlg: function(message, buttons, title){

        if(!$.isFunction(top.HR)){
            alert(message);
            return;
        }

        $dlg = top.HEURIST4.util.getMsgDlg();

        if(message!=null){
            $dlg.empty();
            $dlg.append('<span>'+top.HR(message)+'</span>');
        }

        if(!title) title ='Info';
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

        $dlg.dialog({
            title: top.HR(title),
            resizable: false,
            //height:140,
            width: 'auto',
            modal: true,
            buttons: buttons
        });

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
