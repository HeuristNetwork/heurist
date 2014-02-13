/**
*  Various utility functions
* 
*  @todo - split to generic utilities and UI utilities
* 
*/

if (!top.HEURIST){
    top.HEURIST = {};
}
if (! top.HEURIST.util) top.HEURIST.util = {
    
    /*
    isArray: function (a)
    {
        return Object.prototype.toString.apply(a) === '[object Array]';
    },
    */

    isnull: function(obj){
        return ( (typeof obj==="undefined") || (obj===null));
    },

    isempty: function(obj){
        return ( top.HEURIST.util.isnull(obj) || (obj==="") || (obj==="null") );
    },
    
    isNumber: function (n) {
        //return typeof n === 'number' && isFinite(n);
        return !isNaN(parseFloat(n)) && isFinite(n);
    },

    getUrlQueryAndDomain: function(qsearch)            
    {
            var domain = null;
            if(qsearch && qsearch.indexOf('?')==0){
                domain = top.HEURIST.util.getUrlParameter('w', qsearch);
                qsearch = top.HEURIST.util.getUrlParameter('q', qsearch);
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

        var terms = top.HEURIST.terms;
        if(!terms || top.HEURIST.util.isempty(termID)) return '';

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
            if(top.HEURIST.util.isempty(termCode)){
                termCode = '';
            }else{
                termCode = " ("+termCode+")";
            }
        }

        return termName+(withcode ?termCode :'');
    },

    getPlainTermsList: function(datatype, termIDTree, headerTermIDsList) {
        var selObj = top.HEURIST.util.createTermSelectExt(null, datatype, termIDTree, headerTermIDsList);

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


    /**
    * create/fill SELECT for terms
    *
    * datatype enum|relation
    * termIDTree - json string or object (tree) OR number - in this case this vocabulary ID, if not defined all terms are taken from top.HEURIST.terms.treesByDomain
    * headerTermIDsList - json string or array
    *
    */
    createTermSelectExt: function(selObj, datatype, termIDTree, headerTermIDsList, defaultTermID, isAddFirstEmpty, needPlainList) {

        if(selObj==null){
            selObj = document.createElement("select");
        }else{
            $(selObj).empty();
        }

        if(datatype === "relmarker" || datatype === "relationtype"){
            datatype = "relation";
        }
        if(!(datatype=="enum" || datatype=="relation")){
            return selObj;
        }

        var terms = top.HEURIST.terms;

        if(!terms) return selObj;

        var termLookup = terms.termsByDomainLookup[datatype];

        //prepare header
        //
        var temp = ( top.HEURIST.util.isArray(headerTermIDsList)   //instanceof(Array)
            ? headerTermIDsList
            : (( typeof(headerTermIDsList) === "string" && !top.HEURIST.util.isempty(headerTermIDsList) )
                ? $.parseJSON(headerTermIDsList)  //headerTermIDsList.split(",")
                : [] ));

        var headerTerms = {};
        for (var id in temp) {
            headerTerms[temp[id]] = temp[id];
        }

        //prepare tree
        //
        if(top.HEURIST.util.isNumber(termIDTree)){
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
            arrterm = [];

            for(termID in termSubTree) { // For every term in 'term'
                termName = "";
                termCode = "";

                if(localLookup[termID]){
                    termName = localLookup[termID][terms.fieldNamesToIndex['trm_Label']];
                    termCode = localLookup[termID][terms.fieldNamesToIndex['trm_Code']];
                    if(top.HEURIST.util.isempty(termCode)){
                        termCode = '';
                    }else{
                        termCode = " ("+termCode+")";
                    }
                }

                if(top.HEURIST.util.isempty(termName)) continue;

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
                    var a = new Array(depth*2);
                    termName = a.join('. ') + termName;
                }

                var isDisabled = (headerTerms[termID]? true:false);
                var hasChildren = ( typeof termSubTree[termID] == "object" && Object.keys(termSubTree[termID]).length>0 );
                var isHeader   = ((headerTerms[termID]? true:false) && hasChildren);

                //in FF optgroup is allowed on first level only - otherwise it is invisible

                if(isHeader && depth==0) { // header term behaves like an option group
                    //opt.className +=  ' termHeader';

                    var new_optgroup = document.createElement("optgroup");
                    new_optgroup.label = termName;

                    if(optgroup==null){
                        selObj.appendChild(new_optgroup);
                    }else{
                        optgroup.appendChild(new_optgroup);
                    }

                    //A dept of 8 (depth starts at 0) is maximum, to keep it organised
                    createSubTreeOptions( new_optgroup, ((depth<7)?depth+1:depth), termSubTree[termID], localLookup, defaultTermID)
                }else{
                    var opt = new Option(termName+termCode, termID);
                    opt.className = "depth" + depth;
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

                    //second and more levels terms
                    if(hasChildren) {
                        // A depth of 8 (depth starts at 0) is the max indentation, to keep it organised
                        createSubTreeOptions( optgroup, ((depth<7)?depth+1:depth), termSubTree[termID], localLookup, defaultTermID);
                    }
                }
            }
        }

        if(isAddFirstEmpty){
            top.HEURIST.util.addoption(selObj, '', '');
        }

        createSubTreeOptions(null, 0,termIDTree, termLookup, defaultTermID);
        if (!defaultTermID) selObj.selectedIndex = 0;
        return selObj;
    }

    ,createSelector: function(selObj, sFirstEmptyEntry) {
        if(selObj==null){
            selObj = document.createElement("select");
        }else{
            $(selObj).empty();
        }
        if(sFirstEmptyEntry){
            top.HEURIST.util.addoption(selObj, '', sFirstEmptyEntry);
        }
        return selObj;
    }
    
    /**
    * create/fill SELECT for rectypes groups
    */
    ,createRectypeGroupSelect: function(selObj, sFirstEmptyEntry) {
        
        top.HEURIST.util.createSelector(selObj, sFirstEmptyEntry);

        var rectypes = top.HEURIST.rectypes,
            index;

        if(!rectypes) return selObj;


        for (index in rectypes.groups){
                    if (index == "groupIDToIndex" ){
                      //rectypes.groups[index].showTypes.length < 1) 
                      continue;
                    }
                    
                    var name = rectypes.groups[index].name;
                    if(!top.HEURIST.util.isnull(name)){
                           top.HEURIST.util.addoption(selObj, rectypes.groups[index].id, name);
                    }
        }

        return selObj;
    
    }
    
    /**
    * create/fill SELECT for rectypes
    *
    * rectypeList - constraint options to this list
    */
    , createRectypeSelect: function(selObj, rectypeList, sFirstEmptyEntry) {

        top.HEURIST.util.createSelector(selObj, sFirstEmptyEntry);

        var rectypes = top.HEURIST.rectypes,
            index;

        if(!rectypes) return selObj;


        if(rectypeList){

            if(!top.HEURIST.util.isArray(rectypeList)){
                   rectypeList = rectypeList.split(',');
            }

            for (var idx in rectypeList)
            {
                if(idx){
                    var rectypeID = rectypeList[idx];
                    var name = rectypes.names[rectypeID];
                    if(!top.HEURIST.util.isnull(name))
                    {
                         top.HEURIST.util.addoption(selObj, rectypeID, name);
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

                          if(!top.HEURIST.util.isnull(name)){
                                var opt = top.HEURIST.util.addoption(selObj, rectypeID, name);
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
    createRectypeDetailSelect: function(selObj, rectype, allowedlist, sFirstEmptyEntry, needEmpty) {

        top.HEURIST.util.createSelector(selObj, sFirstEmptyEntry);
        
        var dtyID, details;

        if(Number(rectype)>0){
            //structure not defined 
            if(!(top.HEURIST.rectypes && top.HEURIST.rectypes.typedefs)) return selObj;
            var rectypes = top.HEURIST.rectypes.typedefs[rectype];
            
            if(!rectypes) return selObj;
            details = rectypes.dtFields;
            
            var fi = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex['rst_DisplayName'],
                fit = top.HEURIST.rectypes.typedefs.dtFieldNamesToIndex['dty_Type'];
            
            var arrterm = [];
            
            for (dtyID in details){
               if(dtyID){

                   if(allowedlist==null || allowedlist.indexOf(details[dtyID][fit])>=0)
                   {
                          var name = details[dtyID][fi];

                          if(!top.HEURIST.util.isnull(name)){
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
                top.HEURIST.util.addoption(selObj, arrterm[i][0], arrterm[i][1]);  
            }
            
        }else{ //show all detail types
        
            if(!top.HEURIST.detailtypes) return selObj;
            
            var detailtypes = top.HEURIST.detailtypes;
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

                              if(!top.HEURIST.util.isnull(name)){
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
                            top.HEURIST.util.addoption(selObj, arrterm[i][0], arrterm[i][1]);  
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
            groups = top.HAPI.currentUser.usr_GroupsList;
            if(!groups){        
                //looad detailed info about user groups
                top.HAPI.SystemMgr.mygroups(
                    function(response){
                        if(response.status == top.HAPI.ResponseStatus.OK){
                            groups = top.HAPI.currentUser.usr_GroupsList = response.data;
                            top.HEURIST.util.createUserGroupsSelect(selObj, groups, topOptions, callback);
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
                    if(!top.HEURIST.util.isnull(title))
                    {
                         top.HEURIST.util.addoption(selObj, key, title);
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
                        if(!top.HEURIST.util.isnull(name))
                        {
                            top.HEURIST.util.addoption(selObj, groupID, name);
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
        
        top.HEURIST.util.showMsgDlg(msg, null, "Error");
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
            $dlg = top.HEURIST.util.getMsgDlg();
            $dlg.load(url, function(){
                    top.HEURIST.util.showMsgDlg(null, buttons, title);    
            });
        }
    },
    
    showMsgDlg: function(message, buttons, title){
        
        $dlg = top.HEURIST.util.getMsgDlg();
        
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
        top.HEURIST.util.showMsgDlg(message, null, 'Error');
    },

    checkLength: function( input, title, message, min, max ) {
       var message_text = top.HEURIST.util.checkLength2( input, title, min, max );
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
