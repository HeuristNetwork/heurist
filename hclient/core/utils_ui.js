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
createTermSelectExt   - create/fill SELECT for terms or returns JSON array 
createTermSelectExt2  - the same but parameters are passed as options object

createRectypeGroupSelect - get SELECT for record type groups
createRectypeSelect - get SELECT for record types   
createRectypeDetailSelect - get SELECT for details of given recordtype
    
createUserGroupsSelect - get SELECT for list of given groups, othewise loads list of groups for current user    

createEntitySelector - get id-name selector for specified entity

Other UI functions    
initDialogHintButtons - add show hint and context buttons into dialog header
initHintButton - button to show/hide hints
initHelper - Inits helper div (slider) and button   

Fast access:
getTermValue - Returns label and code for term by id
getPlainTermsList
*/

if (!top.HEURIST4){
    top.HEURIST4 = {};
}
//init only once
if (!top.HEURIST4.ui) 
{

top.HEURIST4.ui = {

    //
    // helper function to add option to select element
    //
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

    
    //
    // create SELECT element (if selObj is null) and fill with given options
    // topOptions either array or string
    // [{key:'',title:''},....]
    //
    createSelector: function(selObj, topOptions) {
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
                                top.HEURIST4.ui.addoption(selObj, key, title);
                            }

                        }
                    }
                }
            }
        }else if(!top.HEURIST4.util.isempty(topOptions) && topOptions!==false){
            if(topOptions===true) topOptions ='';
            top.HEURIST4.ui.addoption(selObj, '', topOptions);
        }


        return selObj;
    },    
    
    //
    // Returns label and code for term by id
    //
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
    //
    //
    //
    getPlainTermsList: function(datatype, termIDTree, headerTermIDsList, selectedTermID) {
        
        var selObj = top.HEURIST4.ui.createTermSelectExt(null, datatype, termIDTree, headerTermIDsList);

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

    //
    // return term by selectedTermID and its children as well as comma-separated list of non-disabled ancestors
    // it uses createTermSelectExt to get the entire tree
    // used in search_faceted
    //
    getChildrenTerms: function(datatype, termIDTree, headerTermIDsList, selectedTermID) {

        var termtree = top.HEURIST4.ui.createTermSelectExt(null, datatype, termIDTree, headerTermIDsList, null, null, true);
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
    * create/fill SELECT for terms or returns JSON array 
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
        return top.HEURIST4.ui.createTermSelectExt2(selObj,
            {datatype:datatype, termIDTree:termIDTree, headerTermIDsList:headerTermIDsList,
             defaultTermID:defaultTermID, topOptions:topOptions, needArray:needArray});
    },

    createTermSelectExt2: function(selObj, options) {

        var datatype =  options.datatype,
            termIDTree =  options.termIDTree,  //all terms
            headerTermIDsList =  options.headerTermIDsList,  //non selectable
            defaultTermID =  options.defaultTermID,
            topOptions =  options.topOptions,
            needArray  =  options.needArray,
            supressTermCode = options.supressTermCode;


        if(needArray){

        }else{
            selObj = top.HEURIST4.ui.createSelector(selObj, topOptions);
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
                termTree = (termTree>0)?tree[termTree]:tree;
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
    },

    
    //
    // get selector for record type groups
    //
    createRectypeGroupSelect: function(selObj, topOptions) {

        top.HEURIST4.ui.createSelector(selObj, topOptions);

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
                top.HEURIST4.ui.addoption(selObj, rectypes.groups[index].id, name);
            }
        }

        return selObj;

    },

    //
    // get selector for record types
    //
    // rectypeList - constraint options to this list, otherwise show entire list of rectypes separated by groups
    //
    createRectypeSelect: function(selObj, rectypeList, topOptions) {

        top.HEURIST4.ui.createSelector(selObj, topOptions);

        var rectypes = top.HEURIST4.rectypes,
        index;

        if(!rectypes) return selObj;


        if(!top.HEURIST4.util.isempty(rectypeList)){

            if(!top.HEURIST4.util.isArray(rectypeList)){
                rectypeList = rectypeList.split(',');
            }
        }else{
            rectypeList = [];
        }
        
        if(rectypeList.length>0 && rectypeList.length<4){
            for (var idx in rectypeList)
            {
                if(idx){
                    var rectypeID = rectypeList[idx];
                    var name = rectypes.names[rectypeID];
                    if(!top.HEURIST4.util.isnull(name))
                    {
                        top.HEURIST4.ui.addoption(selObj, rectypeID, name);
                    }
                }
            }
        }else{
            for (index in rectypes.groups){
                if (index == "groupIDToIndex" ||
                    rectypes.groups[index].showTypes.length < 1) {
                    continue;
                }
                //show group if at leas one rectype is visible
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
                
                
                var grp = document.createElement("optgroup");
                grp.label = rectypes.groups[index].name;
                selObj.appendChild(grp);

                for (var recTypeIDIndex in rectypes.groups[index].showTypes)
                {
                    var rectypeID = rectypes.groups[index].showTypes[recTypeIDIndex];
                    var name = rectypes.names[rectypeID];

                    if(!top.HEURIST4.util.isnull(name)){
                        var opt = top.HEURIST4.ui.addoption(selObj, rectypeID, name);
                    }
                }
            }
        }

        return selObj;
    },

    
    /**
    * get SELECT for details of given recordtype
    *
    * rtyIDs - record type ID otherwise returns all field types grouped by field groups
    * allowedlist - of data types to this list 
    */
    createRectypeDetailSelect: function(selObj, rtyIDs, allowedlist, topOptions) {

        top.HEURIST4.ui.createSelector(selObj, topOptions);

        var dtyID, details;
        
        if(top.HEURIST4.util.isArrayNotEmpty(rtyIDs) && rtyIDs.length>1){
            //get fields for specified array of record types
            // if number of record types is less than 10 form structure
            // name of field as specified in detailtypes
            //       names of field as specified in record structure (disabled items)
            var dtys = {}, dtyNames = [],dtyNameToID = {},dtyNameToRty={};
            var rtys = {};
            var i,j,recID,rty,rtyName,dty,dtyName,fieldName,opt;
            
            var fi_name = top.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex['rst_DisplayName'], 
                fi_type = top.HEURIST4.detailtypes.typedefs.fieldNamesToIndex['dty_Type'];
            
            //for all rectypes find all fields as Detail names sorted
            for (i in rtyIDs) {
                rty = rtyIDs[i];
                rtyName = top.HEURIST4.rectypes.names[rty];
                for (dty in top.HEURIST4.rectypes.typedefs[rty].dtFields) {
                    
                  if(allowedlist!=null && 
                     allowedlist.indexOf(top.HEURIST4.detailtypes.typedefs[dty].commonFields[fi_type])<0){
                      continue; //not allowed - skip
                  }  
                    
                  dtyName = top.HEURIST4.detailtypes.names[dty];
                  if (!dtys[dtyName]){
                    dtys[dtyName] = [];
                    dtyNameToID[dtyName] = dty;
                    dtyNameToRty[dty] = rty;
                    dtyNames.push(dtyName);
                  }
                  fieldName = rtyName + "." + top.HEURIST4.rectypes.typedefs[rty].dtFields[dty][fi_name];
                  dtys[dtyName].push(fieldName);
                }
            }
            if (dtyNames.length >0) {
                dtyNames.sort();
                //add option for DetailType enabled followed by all Rectype.Fieldname options disabled
                for (i in dtyNames) {
                  dtyName = dtyNames[i];
                  var dtyID = dtyNameToID[dtyName];
                  
                  top.HEURIST4.ui.addoption(selObj, dtyID, dtyName); //dtyNameToRty[dtyID]+'-'+

                  //sort RectypeName.FieldName
                  dtys[dtyName].sort();
                  for (j in dtys[dtyName]){
                    fieldName = dtys[dtyName][j];
                    
                    opt = top.HEURIST4.ui.addoption(selObj, '',  '.  .'+fieldName);
                    opt.innerHTML = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"+fieldName;
                    opt.disabled = "disabled";

                  }
                }
            }else{
                top.HEURIST4.ui.addoption(selObj, '', top.HR('no suitable fields'));
            }
            
            
        }else if((top.HEURIST4.util.isArrayNotEmpty(rtyIDs) && rtyIDs.length==1) || Number(rtyIDs)>0){
            //structure not defined
            var rectype = Number((top.HEURIST4.util.isArray(rtyIDs))?rtyIDs[0]:rtyIDs);
            
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
                top.HEURIST4.ui.addoption(selObj, arrterm[i][0], arrterm[i][1]);
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
                        top.HEURIST4.ui.addoption(selObj, arrterm[i][0], arrterm[i][1]);
                    }
                }

            }

        }


        return selObj;
    },

    /**
    *  get SELECT for list of given groups, othewise loads list of groups for current user    
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
                            top.HEURIST4.ui.createUserGroupsSelect(selObj, groups, topOptions, callback);
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
                        top.HEURIST4.ui.addoption(selObj, key, title);
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
                        top.HEURIST4.ui.addoption(selObj, groupID, name);
                    }
                }
            }
        }

        if(typeof callback === "function"){
            callback();
        }
    },
    
    
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
            
        }else if(configMode.entity=='SysImportSessions'){
            fieldTitle = 'imp_table';
            request['entity'] = 'sysImportSessions';
            request['ugr_ID'] = configMode.filter_group;
        }
        
        
        
        top.HAPI4.EntityMgr.doRequest(request,
                    function(response){
                        if(response.status == top.HAPI4.ResponseStatus.OK){
                            
                            var groups = new hRecordSet(response.data).makeKeyValueArray(fieldTitle);
                            
                            if(!top.HEURIST4.util.isArray(topOptions)){
                                if(topOptions==true){
                                    topOptions = [{key:'',title:top.HR('select...')}];
                                }else if(!top.HEURIST4.util.isempty(topOptions) && topOptions!==false){
                                    if(topOptions===true) topOptions ='';
                                    topOptions = [{key:'',title:topOptions}];
                                }
                            }
                            if(top.HEURIST4.util.isArray(topOptions) && top.HEURIST4.util.isArray(groups)){
                                groups = topOptions.concat(groups);
                            }else if(top.HEURIST4.util.isArray(topOptions)){
                                groups = topOptions;
                            }

                            top.HEURIST4.ui.createSelector(selObj, groups);
                            
                        }else{
                            top.HEURIST4.msg.showMsgErr(response);
                        }
                        
                        if($.isFunction(callback)){
                            callback();
                        }
                        
                    });
                              
        
        
    },

    // Init button that show/hide help tips
    initDialogHintButtons: function($dialog, helpcontent_url, hideHelpButton){
        
        var titlebar = $dialog.parent().find('.ui-dialog-titlebar');
        
        if(!hideHelpButton){
            var $help_button = $('<div>').button({icons: { primary: "ui-icon-help" }, label:'Show help hints', text:false})
                    .addClass('dialog-title-button')
                    .css({'right':'48px'})
                    .appendTo(titlebar)
                    .on('click', top.HEURIST4.ui.switchHintState);
        }

        if(helpcontent_url){                    
            var $info_button = $('<div>')
                    .addClass('dialog-title-button')
                    .css({'right':'26px'})
                    .appendTo(titlebar);
                    
            top.HEURIST4.ui.initHelper($info_button, null, helpcontent_url);
        }
                    
    },
                        
    switchHintState: function switchState(event){
            
            var ishelp_on = top.HAPI4.get_prefs('help_on');

            if(event!=null){ //need to change
                ishelp_on = (ishelp_on==1 || ishelp_on==true)?0:1;
                top.HAPI4.save_pref('help_on',ishelp_on);
            }
            
            if(ishelp_on){
                //$help_button.addClass('ui-state-focus');    
                $('.heurist-helper1').css('display','block');
                $('div.div-table-cell.heurist-helper1').css('display','table-cell');
            }else{
                //$help_button.removeClass('ui-state-focus');
                $('.heurist-table-helper1').css('display','none');
                $('.heurist-helper1').css('display','none');
            }
    },
    
    // to remove?
    //
    initHintButton: function(help_button){

        var $help_button = $(help_button);
        
        var ishelp_on = top.HAPI4.get_prefs('help_on');
        
        
        $help_button.button({icons: { primary: "ui-icon-help" }, label:'Show help hints', text:false})
                    .attr('data-state', ishelp_on)
                    .on('click', top.HEURIST4.ui.switchHintState);
        
        top.HEURIST4.ui.switchHintState(null);
    },
    
    //
    // Inits helper div (slider) and button
    // 
    // position top|button- @todo auto detect position
    //
    initHelper: function(help_button, content_title, content_url, position){

        var $help_button = $(help_button);

        $help_button.button({icons: { primary: "ui-icon-circle-b-info" }, label:'Show context help', text:false})
                    .on('click', function(){
                        var $helper_div = $(document.body).find('#helper');
                        
                        if($helper_div.length==0){
                            $helper_div = $('<div>',{id:'helper'}).hide().appendTo($(document.body));
                            
                            $helper_div.dialog({
                                        autoOpen: false, 
                                        title: top.HR(content_title),
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
                            if(position=='top'){ //show div aboe button
                                divpos = { my: "right bottom", at: "right top", of: $help_button }
                            }else{
                                divpos = { my: "right top", at: "right bottom", of: $help_button };
                            }

                            $helper_div.load(content_url, function(){

                                var div_height = Math.min(500, $(document.body).height()-$help_button.position().top);
                                var div_width  = Math.min(600, $(document.body).width() *0.8);
                                
                                var title = $helper_div.find('#content>h2').text();
                            
                                if(title!='') $helper_div.dialog('option','title',title);
                                $helper_div.dialog('option',{width:div_width, height: div_height, position: divpos});
                                $helper_div.dialog( "open" );
                            });
                        }
                 });
                 
                 
    }
    
}//end ui

}

