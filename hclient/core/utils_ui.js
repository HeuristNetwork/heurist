/**
*  Utility functions 
* a) to create standard record types, field types and terms selectors
* b) fast access to db structure defintions
*
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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


createVocabularySelect - creatres selector with vocabularies only (top level terms)
createTermSelect


createRectypeGroupSelect - get SELECT for record type groups
createDetailtypeGroupSelect
createVocabularyGroupSelect

createRectypeSelect - get SELECT for record types   
createRectypeDetailSelect - get SELECT for details of given recordtype
createRectypeTreeSelect - get SELECT for hierarchy of record types   
    
createUserGroupsSelect - get SELECT for list of given groups, othewise loads list of groups for current user    

setValueAndWidth assign value to input and adjust its width

initHSelect - converts HTML select to jquery selectmenu

getRecordTitle - retuns of title for given record id
createTemplateSelector - fills with names of smarty templates

ENTITY

openRecordEdit  - open add/edit record form/dialog
openRecordInPopup - open viewer or add/edit record form
createRecordLinkInfo - creates ui for resource or relationship record

createEntitySelector - get id-name selector for specified entity
showEntityDialog - entity editor/selector dialog

showPublishDialog


Other UI functions    
initDialogHintButtons - add show hint and context buttons into dialog header
initHelper - Inits helper div (slider) and button   


createRecordLinkInfo - return ui for link and relationship

onInactive invokes cb after ms user inactivity
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
        //option = new Option(text,value);
        option.text = text; //window.hWin.HEURIST4.util.htmlEscape(text);
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
            
        }else { //select by default
            if($inpt==null || !$inpt.is('select')){
                $inpt = $('<select style="width:100px">').uniqueId();
            }
            if($inpt.width()<100) $inpt.width(100);
            
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

    //
    //
    //
    fillSelector: function(selObj, topOptions) {
        
        if(window.hWin.HEURIST4.util.isArray(topOptions)){
            var idx,key,title,disabled,depth, border;
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
                            depth = 0;
                            border = false;
                        }else{
                            key = topOptions[idx].key;
                            title = topOptions[idx].title;
                            disabled = (topOptions[idx].disabled===true);
                            depth = (topOptions[idx].depth>0)?topOptions[idx].depth:0;
                            border = (topOptions[idx].hasborder===true);
                        }
                        if(!window.hWin.HEURIST4.util.isnull(title))
                        {
                            if(!window.hWin.HEURIST4.util.isnull(topOptions[idx].optgroup)){
                                var grp = document.createElement("optgroup");
                                grp.label =  title;
                                selObj.appendChild(grp);
                            }else{
                                var opt = window.hWin.HEURIST4.ui.addoption(selObj, key, title, disabled);
                                if(topOptions[idx].group>0){
                                    $(opt).attr('group', topOptions[idx].group);
                                }else if(depth>0){
                                    $(opt).attr('depth', depth);
                                }
                                if(border){
                                    $(opt).attr('hasborder', 1);
                                }
                            }

                        }
                    }
                }
            }
            
            
        }else  if(false && !$.isEmptyObject(topOptions) && Object.keys(topOptions).length>0 ) {
           
                for (var key in topOptions)
                if(!window.hWin.HEURIST4.util.isempty(topOptions[key])){
                        window.hWin.HEURIST4.ui.addoption(selObj, key, topOptions[key], false);
                }
            
        }else if(!window.hWin.HEURIST4.util.isempty(topOptions) && topOptions!==false){
            if(topOptions===true) topOptions = '  ';  // <blank>
            window.hWin.HEURIST4.ui.addoption(selObj, '', topOptions);
        }


        return selObj;
    },    

    
    //
    // options: vocab_id, topOptions, defaultTermID, useHtmlSelect:false
    //
    createTermSelect: function(selObj, options){
      
        var vocab_id =  options.vocab_id, 
            defaultTermID =  options.defaultTermID>0?options.defaultTermID:null,
            topOptions =  options.topOptions,
            supressTermCode = options.supressTermCode,
            useHtmlSelect  = (options.useHtmlSelect===true);
        
        //create selector 
        selObj = window.hWin.HEURIST4.ui.createSelector(selObj, topOptions);
            
        var data = $Db.trm_TreeData(vocab_id, 'select');                
        var termCode;
        
        //add optgroups and options
        for(var i=0; i<data.length; i++){
            
            if(data[i].is_vocab){
                
                var opt = window.hWin.HEURIST4.ui.addoption(selObj, 
                                                    data[i].key, data[i].title);
                
                $(opt).attr('disabled', 'disabled');
                $(opt).attr('group', 1);
                
            }else{
            
                if(supressTermCode || window.hWin.HEURIST4.util.isempty(data[i].code)){
                    termCode = '';
                }else{
                    termCode = " [code "+data[i].code+"]";
                }
                
                var opt = window.hWin.HEURIST4.ui.addoption(selObj, 
                                                data[i].key, data[i].title+termCode);
                $(opt).attr('depth', data[i].depth);

                if (data[i].key == defaultTermID || data[i].title == defaultTermID) {
                        opt.selected = true;
                }
                
            }
            /*            
            $(opt).attr('term-img', hasImage?1:0);
            if(useIds && termID>0){
                $(opt).attr('entity-id', termID);
            }
            if(termParents!=''){
                $(opt).attr('parents', termParents);
                $(opt).attr('term-orig', origName);  
                $(opt).attr('term-view', termName+termCode);
            }             
            */
        }//for
        
        //init selectmenu
        selObj = window.hWin.HEURIST4.ui.initHSelect(selObj, useHtmlSelect);

        return $(selObj);
    },

    //
    // useGroups - true - grouped by vocabulary tabs, or group id
    // domain - any enum relation for useGroup = true
    //
    createVocabularySelect: function(selObj, options) {

        var defaultTermID =  options.defaultTermID,
        topOptions = options.topOptions,
        useGroups = options.useGroups;

        var domain = (options.domain=='enum' || options.domain=='relation')?options.domain:null;

        if (!(useGroups>0 || useGroups===false)){
            useGroups = true;
        }

        //vocab groups    
        var vgroups=[], vocabs = {};
        if(useGroups!==true){
            if(useGroups===false){
                vocabs['0'] = [];
                vgroups.push(0);
            }else if(useGroups>0){  //specific group only
                vocabs[useGroups] = [];
                vgroups.push(useGroups);
            }
        }

        //find all vocabularies and group them by vocab groups
        $Db.trm().each(function(trmID, record){
            var parent_id = this.fld(record, 'trm_ParentTermID');
            if(!(parent_id>0)){
                var grp_id = this.fld(record, 'trm_VocabularyGroupID');
                if(grp_id>0){ 
                    if(useGroups===false){
                        vocabs['0'].push(trmID);
                    }else if(useGroups===true){
                        if(domain==null || domain==$Db.trm(trmID,'trm_Domain')){
                            if(!vocabs[grp_id]){
                                vocabs[grp_id] = [];  
                                vgroups.push(grp_id);
                            } 
                            vocabs[grp_id].push(trmID);
                        }
                    }else if(useGroups>0 && useGroups==grp_id){
                        vocabs[grp_id].push(trmID);
                    }
                }
            }
        });
        
        //sort groups by vcg_Order
        if(useGroups===true){
             
            //sort by name within group
            vgroups.sort(function(a,b){
                return $Db.vcg(a,'vcg_Order')<$Db.vcg(b,'vcg_Order')?-1:1;
            });
            
            
        }
        

        //create selector 
        selObj = window.hWin.HEURIST4.ui.createSelector(selObj, topOptions);

        //add optgroups and options  - vgroups is sorted array
        $.each(vgroups,function(i,grp_id){

            if(useGroups===true  && grp_id>0 && vocabs[grp_id].length>1){
                //add group header
                var group_name = $Db.vcg(grp_id,'vcg_Name');
                if(!group_name) group_name = 'Group# '+grp_id+' (missed)';
                
                var opt = window.hWin.HEURIST4.ui.addoption(selObj, grp_id, group_name);
                $(opt).attr('disabled', 'disabled');
                $(opt).attr('group', 1);
            }

            //sort by name within group
            vocabs[grp_id].sort(function(a,b){
                return $Db.trm(a,'trm_Label')<$Db.trm(b,'trm_Label')?-1:1;
            });

            $.each(vocabs[grp_id],function(i,trm_id){
                var trm_name = $Db.trm(trm_id,'trm_Label');
                var opt = window.hWin.HEURIST4.ui.addoption(selObj, trm_id, trm_name);
                $(opt).attr('depth', 1);

                if (trm_id == defaultTermID || trm_name == defaultTermID) {
                    opt.selected = true;
                }

            });
        });

        //init selectmenu
        selObj = window.hWin.HEURIST4.ui.initHSelect(selObj, false);

        return $(selObj);

    },


    //
    // get selector for record/detail/vocabulary type groups
    //
    createEntityGroupSelect: function(entity, selObj, topOptions) {

        var recset = $Db[entity]();
        var options = recset.makeKeyValueArray(entity+'_Name');
        
        if(!(window.hWin.HEURIST4.util.isArray(topOptions) ||
           window.hWin.HEURIST4.util.isempty(topOptions) ||
           topOptions===false)){
            if(topOptions===true) topOptions = '  ';  // <blank>
            topOptions = [{key:'', title:topOptions}];
        }

        if(topOptions){
            options = topOptions.concat(options);
        } 
        
        window.hWin.HEURIST4.ui.createSelector(selObj, options);
        
        return selObj;
    },

    createRectypeGroupSelect: function(selObj, topOptions) {
        return window.hWin.HEURIST4.ui.createEntityGroupSelect('rtg', selObj, topOptions);
    },
    
    createDetailtypeGroupSelect: function(selObj, topOptions) {
        return window.hWin.HEURIST4.ui.createEntityGroupSelect('dtg', selObj, topOptions);
    },
    
    createVocabularyGroupSelect: function(selObj, topOptions) {
        return window.hWin.HEURIST4.ui.createEntityGroupSelect('vcg', selObj, topOptions);
    },

    //
    // get selector for record types
    //
    // rectypeList - constraint options to this list, otherwise show entire list of rectypes separated by groups
    //
    createRectypeSelect: function(selObj, rectypeList, topOptions, useHtmlSelect) {

            return window.hWin.HEURIST4.ui.createRectypeSelectNew(selObj, 
                {rectypeList:rectypeList, topOptions:topOptions, useHtmlSelect:useHtmlSelect});
    },
    
    createRectypeSelectNew: function(selObj, options) {
        
        var rectypeList = options.rectypeList;
        var topOptions = options.topOptions;
        var useHtmlSelect = (options.useHtmlSelect===true);
        var useIcons = (options.useIcons===true); //!useHtmlSelect && 
        var useCounts = (options.useCounts===true);
        var useGroups = (options.useGroups!==false);
        var useIds = (options.useIds===true);
        var useCheckboxes = (options.useCheckboxes===true);
        
        var showAllRectypes = (options.showAllRectypes===true); //otherwise only non-hidden
 
        selObj = window.hWin.HEURIST4.ui.createSelector(selObj, topOptions);

        useHtmlSelect = (useHtmlSelect===true);
        
        //recordset 
        var rectypes = $Db.rty();
        var index;

        if(rectypes){ 

        if(!window.hWin.HEURIST4.util.isempty(rectypeList)){

            if(!window.hWin.HEURIST4.util.isArray(rectypeList)){
                rectypeList = rectypeList.split(',');
            }
        }else if(!useGroups){ //all rectypes however plain list (not grouped)
            rectypeList = rectypes.getIds();
        }else{
            rectypeList = []; //all rectypes
        }
        
        if(!useGroups || (rectypeList.length>0 && rectypeList.length<7)){  //show only specified list of rectypes
        
            if(useCounts){//sort by count
                rectypeList.sort(function(a,b){
                     var ac = $Db.rty(a,'rty_RecCount');   
                     var bc = $Db.rty(b,'rty_RecCount');   
                                        
                     if(isNaN(ac)) ac = 0;
                     if(isNaN(bc)) bc = 0;
                     return Number(ac)<Number(bc)?1:-1;
                });
            }
            
            var isEmpty = true;
        
            for (var idx in rectypeList)
            {
                if(idx){
                    var rectypeID = rectypeList[idx];
                    var name = window.hWin.HEURIST4.util.htmlEscape($Db.rty(rectypeID,'rty_Name'));
                    if(!window.hWin.HEURIST4.util.isnull(name))
                    {

                        var rty_Count = 0;
                        if(useCounts){
                            rty_Count = $Db.rty(rectypeID,'rty_RecCount');
                            if(isNaN(rty_Count) || rty_Count<1) continue;
                        }
                        
                        isEmpty = false;
                       
                        var opt = window.hWin.HEURIST4.ui.addoption(selObj, rectypeID, name);
                        
                        if(useIcons){
                            var icon = window.hWin.HAPI4.iconBaseURL + rectypeID + '.png';
                            $(opt).attr('icon-url', icon);
                        }
                        if(useCounts){
                            $(opt).attr('rt-count', rty_Count);
                        }
                        if(useIds){
                            $(opt).attr('entity-id', rectypeID);
                        }
                    }
                }
            }
            
            if(useCounts && isEmpty){
                window.hWin.HEURIST4.ui.addoption(selObj, 0, 'No records in the database')
            }
            
        }else{  //show rectypes separated by groups
        
            //var groups = $Db.rtg().makeKeyValueArray('rtg_Name');
            var groups = {};
            var groups_order = $Db.rtg().getSubSetByRequest({ 'sort:rtg_Order':1 });
            
            groups_order.each(function(rtgID, record){
                groups[rtgID] = {title:$Db.rtg(rtgID,'rtg_Name'),rty:[]};
            });
            
            //scan all rectypes and group by rtg groups        
            rectypes.each(function(rtyID, record){
                
                if ((rectypeList.length==0 
                    || window.hWin.HEURIST4.util.findArrayIndex(rtyID, rectypeList)>=0)
                    &&
                    (showAllRectypes || $Db.rty(rtyID,'rty_ShowInLists')==1)){
                        
                   groups[$Db.rty(rtyID,'rty_RecTypeGroupID')]['rty'].push(rtyID);
                }
                
            });
            
            groups_order = groups_order.getOrder();
            
            for(var k=0; k<groups_order.length; k++){
                
                var rtgID  = groups_order[k];
                if(groups[rtgID].rty.length>0){
                
                    //add header    
                    if(useHtmlSelect){
                        var grp = document.createElement("optgroup");
                        grp.label = groups[rtgID].title;
                        selObj.appendChild(grp);
                    }else{
                        var opt = window.hWin.HEURIST4.ui.addoption(selObj, 0, groups[rtgID].title);
                        $(opt).attr('disabled', 'disabled');
                        $(opt).attr('group', 1);
                    }


                    //add rectypes
                    for (var i=0; i<groups[rtgID].rty.length; i++){
                        
                        var rtyID = groups[rtgID]['rty'][i];
                        var name = window.hWin.HEURIST4.util.htmlEscape($Db.rty(rtyID, 'rty_Name'));

                        var opt = window.hWin.HEURIST4.ui.addoption(selObj, rtyID, name);
                        $(opt).attr('depth', 1);
                        
                        $(opt).attr('title', $Db.rtg(rtyID, 'rty_Description'));
                        
                        
                        if(useIcons){
                            var icon = window.hWin.HAPI4.iconBaseURL + rtyID + '.png';
                            $(opt).attr('icon-url', icon);
                        }
                        if(useCounts){
                            $(opt).attr('rt-count',$Db.rty(rtyID, 'rty_RecCount'));
                        }
                        if(useIds){
                            $(opt).attr('entity-id', rtyID);
                        }
                        if(useCheckboxes){
                            var r = window.hWin.HEURIST4.util.findArrayIndex(rtyID, options.marked);
                            $(opt).attr('rt-checkbox', (r>=0)?1:0);
                            $(opt).attr('data-id', rtyID);
                        }
                    }
                    
                }
            }
            
 
            if(rectypeList.length>0){
                $(selObj).val(rectypeList[0]);
            }
            
        }
        }
        
        selObj = window.hWin.HEURIST4.ui.initHSelect(selObj, useHtmlSelect);

        return $(selObj);
    },

    // NOT USED
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
    * 
    * usage search.js, searchQuick.js, recordAction.js
    */
    createRectypeDetailSelect: function(selObj, rtyIDs, allowedlist, topOptions, options ) {

        window.hWin.HEURIST4.ui.createSelector(selObj, topOptions);
        
        var showDetailType = false;
        var addLatLongForGeo = false;
        var requriedHighlight = false;
        var selectedValue = null;
        var show_parent_rt = false; //show parent entity field
        var useHtmlSelect = true;
        var useIds = false;
        var initial_indent = 0;
        if(options){  //at the moment it is implemented for single rectype only
            showDetailType    = options['show_dt_name']==true;
            addLatLongForGeo  = options['show_latlong']==true;
            requriedHighlight = options['show_required']==true;
            selectedValue     = options['selected_value'];
            show_parent_rt    = options['show_parent_rt']==true;
            initial_indent    = options['initial_indent']>0?options['initial_indent']:0;
            useHtmlSelect     = options['useHtmlSelect']!==false;
            useIds            = options['useIds']===true;
        }
        
        //var trash_id = $Db.getTrashGroupId('dtg');
        
        
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
            
            //for all rectypes find all fields as Detail names sorted
            for (i in rtyIDs) {
                rty = rtyIDs[i];
                rtyName = $Db.rty(rty, 'rty_Name');
                
                var dtFields = $Db.rst(rty);
                
                dtFields.each2(function(dty, detail){
                    
                      var field_type = $Db.dty(dty, 'dty_Type');
                      if(allowedlist!=null && 
                         allowedlist.indexOf(field_type)<0){
                         //not allowed - skip
                         
                      }else{  
                      
                          dtyName = $Db.dty(dty, 'dty_Name');
                          if (!dtys[dtyName]){
                            dtys[dtyName] = [];
                            dtyNameToID[dtyName] = dty;
                            dtyNameToRty[dty] = rty; //not used
                            dtyNames.push(dtyName);
                          }
                          fieldName = rtyName + "." + detail['rst_DisplayName'];
                          dtys[dtyName].push(fieldName);
                      }
                });
                
            }//for rectypes
            
            //fill select
            if (dtyNames.length >0) {
                
                //sort by name - case insensitive
                dtyNames.sort(function(a, b) {
                    var nameA = a.toUpperCase(); // ignore upper and lowercase
                    var nameB = b.toUpperCase(); // ignore upper and lowercase
                    if (nameA < nameB) {
                        return -1;
                    }
                    if (nameA > nameB) {
                        return 1;
                    }

                    // names must be equal
                    return 0;
                });
                //add option for DetailType enabled followed by all Rectype.Fieldname options disabled
                for (i in dtyNames) {
                  dtyName = dtyNames[i];
                  var dtyID = dtyNameToID[dtyName];
                  
                  opt = window.hWin.HEURIST4.ui.addoption(selObj, dtyID, dtyName); //dtyNameToRty[dtyID]+'-'+
                    
                  if(useIds){
                    $(opt).attr('entity-id', dtyID);
                  }
                  
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
            
            var rectype = Number((window.hWin.HEURIST4.util.isArray(rtyIDs))?rtyIDs[0]:rtyIDs);
            
            details = $Db.rst(rectype);
            if(!details) return selObj; //structure not defined
                
            var arrterm = [];
            rectype = ''+rectype;
            
            var child_rectypes = [];
            if(show_parent_rt){
                var DT_PARENT_ENTITY  = window.hWin.HAPI4.sysinfo['dbconst']['DT_PARENT_ENTITY'];
                //get all child rectypes
                var all_structs = $Db.rst_idx2();
                for (var rty_ID in all_structs){
                    var recset = all_structs[rty_ID];
                    recset.each2(function(dty_ID, record){
                    
                        if(record['rst_CreateChildIfRecPtr']==1){
                            var fieldtype = $Db.dty(dty_ID, 'dty_Type');
                            var constraint = $Db.dty(dty_ID, 'dty_PtrTargetRectypeIDs');
                            if(fieldtype=='resource' && constraint && constraint.split(',').indexOf(rectype)>=0){
                                
                                    var name = 'Parent record ('+$Db.rty(record['rst_RecTypeID'], 'rty_Name')+')';
                                    
                                    if(showDetailType){
                                        name = name + ' [resource]';
                                    }

                                    arrterm.push([DT_PARENT_ENTITY, name, false]);    
                                    
                                    return false;
                            }
                        }
                    });
                    if(arrterm.length>0) break;
                }
            }

            
            var trash_id = $Db.getTrashGroupId('dtg');
                                        
            details.each2(function(dtyID, detail){
                if(dtyID){

                    var field_reqtype = detail['rst_RequirementType'];
                    
                    if(field_reqtype=='forbidden' || detail['dty_DetailTypeGroupID'] == trash_id) return true; 
                        //2021-02-19 || $Db.dty(dtyID, 'dty_ShowInLists')==0
                    
                    var field_type = $Db.dty(dtyID, 'dty_Type');

                    if(allowedlist==null || allowedlist.indexOf(field_type)>=0)
                    {
                        
                        var name = detail['rst_DisplayName'];
                        
                        if(showDetailType){
                            name = name + ' ['+$Db.baseFieldType[ field_type ]+']';
                        }

                        if(!window.hWin.HEURIST4.util.isnull(name)){
                                arrterm.push([dtyID, name, (field_reqtype=="required") ]);    
                        }
                    }
                    if(addLatLongForGeo && field_type=="geo"){
                        arrterm.push([ dtyID+'_long', name+' [longitude]', false ]);
                        arrterm.push([ dtyID+'_lat', name+' [latitude]', false ]);
                    } 
                }
            });

            //sort by name
            arrterm.sort(function(a, b) {
                    var nameA = a[1].toUpperCase(); // ignore upper and lowercase
                    var nameB = b[1].toUpperCase(); // ignore upper and lowercase
                    if (nameA < nameB) {
                        return -1;
                    }
                    if (nameA > nameB) {
                        return 1;
                    }

                    // names must be equal
                    return 0;
            });
            
            
            //add to select
            var i=0, cnt= arrterm.length;
            for(;i<cnt;i++) {
                var opt = window.hWin.HEURIST4.ui.addoption(selObj, arrterm[i][0], arrterm[i][1]);
                if(arrterm[i][2] && requriedHighlight){
                    opt.className = "required";
                }
                $(opt).attr('depth',initial_indent);
                if(useIds){
                    $(opt).attr('entity-id', arrterm[i][0]);
                }
            }

        }
        // for all fields
        else{ //show all detail types

            $Db.dtg().each2(function(groupID, group){
            
                var arrterm = [];
                
                $Db.dty().each2(function(detailID, field)
                {
                    if(field['dty_DetailTypeGroupID']==groupID &&
                      //2021-02-19 (field['dty_ShowInLists']==1) &&
                      (allowedlist==null || allowedlist.indexOf(field['dty_Type'])>=0))
                    {
                        arrterm.push([detailID, field['dty_Name']]);
                    }
                });


                if(arrterm.length>0){
                    
                    //add header    
                    if(useHtmlSelect){
                        var grp = document.createElement("optgroup");
                        grp.label = group['dtg_Name'];
                        selObj.appendChild(grp);
                    }else{
                        var opt = window.hWin.HEURIST4.ui.addoption(selObj, 0, group['dtg_Name']);
                        $(opt).attr('disabled', 'disabled');
                        $(opt).attr('group', 1);
                    }

                    //sort by name
                    arrterm.sort(function(a, b) {
                            var nameA = a[1].toUpperCase(); // ignore upper and lowercase
                            var nameB = b[1].toUpperCase(); // ignore upper and lowercase
                            if (nameA < nameB) {
                                return -1;
                            }
                            if (nameA > nameB) {
                                return 1;
                            }
                            // names must be equal
                            return 0;
                    });
                    
                    //add to select
                    var i=0, cnt= arrterm.length;
                    for(;i<cnt;i++) {
                        var opt = window.hWin.HEURIST4.ui.addoption(selObj, arrterm[i][0], arrterm[i][1]);
                        $(opt).attr('depth',1);
                        if(useIds){
                            $(opt).attr('entity-id', arrterm[i][0]);
                        }
                    }
                }
                
            });

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
            topOptions.push({key:0, title:'──────────',disabled:true});
            
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
                        window.hWin.HEURIST4.ui.addoption(selObj, key, title, (topOptions[idx].disabled==true));
                        addedontop.push(key);
                    }
                }
            }
        }
        if(groups){   //it may 1) array of group ids 2) [ids=>name] 3) [ids=a>rray(0,name,0)]

            for (var idx in groups)
            {
                if(idx>=0){
                    
                    var groupID = groups[idx];
                    var name = null;
                    if(parseInt(groupID)>0){ //case 1
                        name = window.hWin.HAPI4.sysinfo.db_usergroups[groupID];
                    }else{
                        groupID = idx;
                        name = groups[groupID];    
                        if($.isArray(name)) name = name[1] //backward  case 3
                    }
                    
                    if(window.hWin.HEURIST4.util.findArrayIndex(groupID,addedontop)<0 
                        && !window.hWin.HEURIST4.util.isnull(name))
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
    
    //
    //
    //
    initHSelect: function(selObj, useHtmlSelect, apply_style){            

        //var isNotFirefox = (navigator.userAgent.indexOf('Firefox')<0);
        ////depth>1 || (optgroup==null && depth>0
        
        selObj = $(selObj);
            
        //for usual HTML select we have to add spaces for indent
        if(useHtmlSelect){
            
            selObj.find('option').each(
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
            
            var parent_ele = selObj.parents('.ui-dialog');
            if(!parent_ele || parent_ele.length==0) {
                
                parent_ele = selObj.parents('.selectmenu-parent'); //add special class to some top most div 
                
                /*
                var sel = $(selObj)[0];
                if(sel.ownerDocument != document){ //inside iframe
                
                    var pwin = (sel.ownerDocument.parentWindow || sel.ownerDocument.defaultView);
                    var parent_dlg_id = pwin.frameElement.getAttribute("parent-dlg-id");
                    parent_ele = $('#'+parent_dlg_id).parent();    
                }                
                //parent dialog not found
                if(parent_ele.length==0)
                */
                    if(!parent_ele || parent_ele.length==0) {
                        parent_ele = selObj.parent();   
                    }
            }
            
            if(selObj.hSelect("instance")!=undefined){
                selObj.hSelect("destroy"); 
            }
            
 //console.log(parent_ele);
            var menu = selObj.hSelect(       
              { style: 'dropdown',
                position:(navigator.userAgent.indexOf('Firefox')<0)?{collision: "flip"}:{},
                appendTo: parent_ele,
                /*positionOptions: {
                    collision: 'none',
                    my: "left top",
                    at: "left bottom",
                    offset: null
                },*/
                change: function( event, data ) {
 
                        selObj.val(data.item.value);//change value for underlaying html select
                        selObj.trigger('change');
                },
                open: function(event, ui){
                    //console.log(menu.hSelect( "menuWidget" ).width());
                    //increase width of dropdown to avoid word wrap
                    var wmenu = $(event.target).hSelect( "menuWidget" );  //was menu
                    wmenu.width( wmenu.width()+20 ); 
                    var wmenu_div = wmenu.parent('div.ui-selectmenu-menu');
                    var pos = wmenu_div.position().top;
                    
//console.log( '>>> ' + wmenu.parents('body').height() );
                    var parent_body = wmenu.parents('body');
                    var bodyheight = parent_body.height()>0?parent_body.height():parent_body[0].clientHeight;
                    
                    if(bodyheight>0 && pos+wmenu.height()>bodyheight){
                        var newtop = bodyheight-wmenu.height()-5;
                        if(newtop<0){
                            newtop = 2;
                            wmenu_div.height(bodyheight-2);
                        }
                        wmenu_div.css('top', newtop);
                    }
                    //calculate position
//console.log(pos+'+'+wmenu.height()+'>'+bodyheight);
                }
              });
                
                
            var dwidth = selObj.css('width');    
            if(!dwidth || dwidth=='0px' || (dwidth.indexOf('px')>0 && parseFloat(dwidth)<21)) dwidth = 'auto';
            
            var dminwidth = selObj.css('min-width');    
            if(dminwidth=='0px' || window.hWin.HEURIST4.util.isempty(dminwidth)) dminwidth = '10em';

            var menuwidget = menu.hSelect( "menuWidget" );
            menuwidget.css( {'background':'#F4F2F4','zIndex':9999999 }); //'padding':0,
            menuwidget.addClass('heurist-selectmenu overflow').css({'max-height':'300px','font-size':'12px'});
            
            //menuwidget.find()
            
            if(apply_style){
                menu.hSelect( "widget" ).css(apply_style);    
                menu.hSelect( "menuWidget" ).css(apply_style);
            }else{
                menu.hSelect( "widget" ).css({'padding':0, 'font-size':'1.1em', //'background':'#FFF',
                    width:(dwidth?dwidth:'auto'),'min-width':dminwidth }); //,'min-width':'16em''#F4F2F4'
            }
                
        }
        return selObj;
    },           
    
    //
    // exp_level 2 beginner, 1 intermediate, 0 expert
    // heurist-helper2  for level>1
    // heurist-helper1  for level>0
    //
    applyCompetencyLevel: function(exp_level, $context){

            if(!(exp_level>=0)){
                exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2); //beginner default
            }
            
            var is_exit = false;
            if(!$context){
                is_exit = true;
                $context = $(window.hWin.document);
            }
        
            /* since 2018-12=24 help is not related to competency level
        
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
            */
            
            
            $context.find('li[data-user-experience-level]').each(function(){
                if(exp_level > $(this).data('exp-level')){
                    $(this).hide();    
                }else{
                    $(this).show();    
                }
            });
            
            
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
      
    // Init button that show/hide help tips for popup dialog
    //
    //  usrPrefKey - prefs_entityName - context 
    //
    initDialogHintButtons: function($dialog, button_container_id, helpcontent_url, hideExpLevelButton){
        
        //IJ 2018-12-04 hide it! 
        var hasContextHelp =  !window.hWin.HEURIST4.util.isempty(helpcontent_url);
        hideExpLevelButton = true; //always hide
        
        var titlebar = $dialog.parent().find('.ui-dialog-titlebar');
        if(titlebar.length==0){
            titlebar = $dialog.find(button_container_id);
        } 
        
        if(!hideExpLevelButton){
            var $help_menu = $('<ul><li data-user-admin-status="2"><a><span class="ui-icon"/>Beginner</a></li>'
                +'<li data-user-admin-status="1"><a><span class="ui-icon"/>Intermediate</a></li>'
                +'<li data-user-admin-status="0"><a><span class="ui-icon"/>Expert</a></li><ul>')
                .width(150).hide().appendTo($dialog);
            
        var $help_button = $('<div>').button({icons: { primary: "ui-icon-book" }, 
                    label:'Set experience level for user interface', text:false})
                    .addClass('dialog-title-button')
                    .css({'right':hasContextHelp?'48px':'26px'})
                    .appendTo(titlebar)
                    .on('click', function(event){
                           //show popup menu 
                           var exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);
                           
                           $help_menu.find('span').removeClass('ui-icon-check');
                           $help_menu.find('li[data-user-admin-status="'+exp_level+'"] > a > span').addClass('ui-icon-check');
                           
                           
                           if($help_menu.parent().length==0){
                               $help_menu.menu().appendTo($help_button.parents('.ui-dialog').find('.ui-dialog-content'));
                           }
                           $help_menu.menu().on( {
                               //mouseenter : function(){_show(this['menu_'+name], this['btn_'+name])},
                               click: function(event){ 
                                   //change level
                                   var exp_level = $(event.target).parents('li').attr('data-user-admin-status');

                                   window.hWin.HAPI4.save_pref('userCompetencyLevel', exp_level);

                                   window.hWin.HEURIST4.ui.applyCompetencyLevel(exp_level, $dialog);

                                   $help_menu.find('span').removeClass('ui-icon-check');
                                   $help_menu.find('li[data-user-admin-status="'+exp_level+'"] > a > span').addClass('ui-icon-check');
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

        if(hasContextHelp){                    
            var $info_button = $('<div>')
                    .addClass('dialog-title-button')
                    .css({'right':'26px'})
                    .appendTo(titlebar);
                    
            window.hWin.HEURIST4.ui.initHelper({button:$info_button, url:helpcontent_url});
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
    switchHintState2: function(state, $container, className){
        
            if(!className){
                className = '.heurist-helper1';
            }
        
            if(state){
                //$help_button.addClass('ui-state-focus');    
                $.each($container.find(className),function(i,ele){ 
                    if(!$(ele).hasClass('separator-hidden')) $(ele).css('display','block');
                });
                $.each($container.find('div.div-table-cell'+className),function(i,ele){ 
                    if(!$(ele).hasClass('separator-hidden')) $(ele).css('display','table-cell');
                });
                
                //$container.find(className).css('display','block');
                //$container.find('div.div-table-cell'+className).css('display','table-cell');
            }else{
                //$help_button.removeClass('ui-state-focus');
                $container.find('div.div-table-cell'+className).css('display','none');
                $container.find(className).css('display','none');
            }
    },
    
    //
    // Inits helper div (slider) and button
    //  options:  
    //      button - ref to element
    //      no_init - do not init button
    //      title - header of popup
    //      url - ref to help file
    //      position - juquery position - default to bottom of button
    //      container - adds help to this container rather than popup
    //      is_open_at_once   
    //
    initHelper: function( options ){
        
        
        var $help_button = $(options.button);

        if(options.no_init!==true){ //do not init button    ui-icon-circle-b-info  carat-2-e
            $help_button.button({icons:{primary:"ui-icon-circle-help"}, label:'Show context help', text:false});
        }
        
        var is_popup = false;
        if(!options.container){
            is_popup = true;
            options.container = $(document.body);
        }
        var _innerTitle;
        var oEffect = {effect:'slide',direction:'right',duration:500};
        
        function __closeHelpDiv($helper_div){
            oEffect.complete =  function(){ options.container.children('.ent_content_full').css({right:1, width:'auto'}); };
            $helper_div.hide(oEffect);
            $help_button.button({icons:{primary:"ui-icon-circle-help"}});
        }
        //ui-menu6
        
        
        $help_button.on('click', function(){
            
                        var $helper_div = options.container.find('.ui-helper-popup');
                        
                        if($helper_div.length==0){
                            $helper_div = $('<div>').addClass('ui-helper-popup')
                                            .hide().appendTo(options.container);
                            
                            if(is_popup){
                                $helper_div.dialog({
                                            autoOpen: false, 
                                            //title: window.hWin.HR(options.title),
                                            show: oEffect,
                                            hide: oEffect
                                         });                 
                            }else{
                                $helper_div.css('width','30%');
                                /*
                                _innerTitle = $('<div>').addClass('ui-heurist-header').appendTo($helper_div);  
                                
                                $('<span>').appendTo(_innerTitle);
                                $('<button>').button({icon:'ui-icon-closethick',showLabel:false, label:'Close'}) 
                                                        //classes:'ui-corner-all ui-dialog-titlebar-close'})
                                         .css({'position':'absolute', 'right':'4px', 'top':'6px', height:24, width:24})
                                         .appendTo(_innerTitle)
                                         .on({click:function(){
                                             $helper_div.hide(oEffect);
                                             //__onDialogClose();
                                         }});
                                
                                
                                $('<div>').css({top:38}).addClass('ent_wrapper').appendTo($helper_div);  
                                */
                            }
                        }
                        
                        if(is_popup){
                            
                            if($helper_div.dialog( "isOpen" )){
                                $helper_div.dialog( "close" );
                            }else{                        
                            
                                //var div_height = Math.min(500, (document.body).height()-$help_button.top());
                                //var div_width  = Math.min(600, (document.body).width() *0.8);
                                divpos = null;
                                if(options.position && $.isPlainObject(options.position)){
                                    divpos = options.position;
                                    //divpos['of'] = $help_button;
                                }else if(options.position=='top'){ //show div above button
                                    divpos = { my: "right bottom", at: "right top", of: $help_button }
                                }else{
                                    divpos = { my: "right top", at: "right bottom", of: $help_button };
                                }
                                
                                $helper_div.load(options.url, function(response, status, xhr){
                                    
                                    if(status=='error'){
                                        
                                        window.hWin.HEURIST4.msg.showMsgFlash('Sorry context help was not found',1000);
                                        
                                    }else{

                                        var div_height = Math.min(400, $(document.body).height()-$help_button.position().top);
                                        var div_width  = Math.min(700, $(document.body).width() *0.8);
                                       
                                        var head = $helper_div.find('#content>h2');
                                        if(head.length==1){
                                            if(!options.title) options.title = head.text();
                                            head.empty();
                                        }
                                        if(!options.title){
                                            options.title = 'Heurist context help';                                      
                                        }
                                    
                                        $helper_div.dialog('option','title', options.title);
                                        $helper_div.dialog('option', {width:div_width, height: 'auto', position: divpos});
                                        $helper_div.dialog( "open" );
                                        setTimeout(function(){
                                                $helper_div.find('#content').scrollTop(1);
                                        }, 1000);
                                    
                                        //click outside    
                                        $( document ).one( "click", function() { $helper_div.dialog( "close" ); });
                                    }
                                });
                            }
                            
                            
                        }else{
                            
                            if($helper_div.is(':visible')){
                                __closeHelpDiv($helper_div);         
                            }else{
                                $help_button.button({icons:{primary:"ui-icon-carat-2-e"}});
                                //find('.ent_wrapper')
                                $helper_div.load(options.url, function(response, status, xhr){

                                    if(status=='error'){
                                        
                                        window.hWin.HEURIST4.msg.showMsgFlash('Sorry context help was not found');
                                        __closeHelpDiv($helper_div);
                                        
                                    }else{

                                        /*
                                        $helper_div.css({position:'absolute',right:1,top:38,bottom:1,
                                                    width:300,'font-size':'0.9em',padding:'4px'});
                                        */
                                        
                                        var head = $helper_div.find('#content>h2');
                                        if(head.length==1){
                                            if(!options.title) options.title = head.text();
                                            head.empty();
                                        }
                                        if(!options.title){
                                            options.title = 'Heurist context help';                                      
                                        }
                                        
                                        //_innerTitle.find('span').text( options.title );
                                        $helper_div.show( 'slide', {direction:'right'}, 500 );                        
                                        options.container.children('.ent_content_full').css({width:'70%'}); //right:424
                                    
                                        setTimeout(function(){
                                                $helper_div.find('#content').scrollTop(1);
                                        }, 1000);
                                    }

                                
                                });
                            }
                            
                        } 
                        
                        
                 });
                 
        if(options.is_open_at_once && options.container){
            options.container.find('.ui-helper-popup').hide();
            $help_button.click();    
        }
    },
    
    //
    //
    //
    checkAndLogin: function(isforsed, callback){

        if(!window.hWin.HAPI4.has_access()){
            // {status:window.hWin.ResponseStatus.REQUEST_DENIED} 
            if(typeof showLoginDialog !== "undefined" && $.isFunction(showLoginDialog)){  // already loaded in index.php
                //window.hWin.HEURIST4.msg.showMsgErr(top.HR('Session expired2'));
                showLoginDialog(isforsed, callback);
            }else{
                $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/profile/profile_login.js', function(){
                    window.hWin.HEURIST4.ui.checkAndLogin(isforsed, callback);
                }); 
            }
            return false;
        }else{
            return true;
        }
        
    },

    //
    // important manageRecords.js and selectRecords.js must be loaded
    // 
    // rec_ID - record to edit
    // query_or_recordset - recordset or query returns set of records that can be edit in bunch (next/prev buttons)
    // popup_options['onselect'] - define function that accepts added/edited record as recordset
    // popup_options['edit_structure'] - allows edit structure only
    // 
    openRecordEdit:function(rec_ID, query_or_recordset, popup_options){
        
        /*
                var usrPreferences = window.hWin.HAPI4.get_prefs_def('edit_record_dialog', 
                        {width: (window.hWin?window.hWin.innerWidth:window.innerWidth)*0.95,
                        height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });
        */
        if(!window.hWin.HEURIST4.ui.checkAndLogin(false, function(is_logged){
                    if(is_logged!==false){
                        window.hWin.HEURIST4.ui.openRecordEdit(rec_ID, query_or_recordset, popup_options);
                    }
                })){
            return;
        }
        
    
                var $container;
                var isPopup = false;
                
                if(popup_options && 
                    $.isPlainObject(popup_options.new_record_params) && 
                        (popup_options.new_record_params['rt']>0 || popup_options.new_record_params['RecTypeID']>0)){
                    //rec_ID = -1;
                    query_or_recordset = null;
                    
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

                        //+ '<div class="ent_header editHeader"></div>'
                        + '<div class="editFormDialog ent_content_full">'
                        
                                + '<div class="ui-layout-north">'
                                        +'<div class="editStructureHeader" style="background:white"></div>'
                                + '</div>' 
                        
                                + '<div class="ui-layout-west"><div class="editStructure treeview_with_header" style="background:white">'
                                    +'</div></div>' //container for rts_editor
                                + '<div class="ui-layout-center"><div class="editForm"/></div>'
                                + '<div class="ui-layout-east"><div class="editFormSummary">....</div></div>'
                                //+ '<div class="ui-layout-south><div class="editForm-toolbar"/></div>'
                        + '</div>'
                        //+ '<div class="ent_footer editForm-toolbar"/>'
                    +'</div>',
                    onInitFinished:function( last_attempt ){
                        
                        if( query_or_recordset && 
                            (typeof query_or_recordset.isA == "function") && 
                             query_or_recordset.isA("hRecordSet") )
                        {
                            //array of record ids 
                            this.updateRecordList(null, {recordset:query_or_recordset});
                            this.addEditRecord( (rec_ID>0)?rec_ID:query_or_recordset.getOrder()[0] );
                            return;
                        }
                        
                        var query_request = null;
                        
                        if(query_or_recordset){
                            if(!$.isPlainObject(query_or_recordset)){ //just string
                                query_request = {q:query_or_recordset, w:'all'};
                            }
                        }else if(query_request==null && rec_ID>0){
                            query_request = {q:'ids:'+rec_ID, w:'e'}; //including temporary
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
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    
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
                                        if(last_attempt!==true && query_request && rec_ID>0){
                                            query_request = null;
                                            onInitFinished( true );
                                            return;
                                        }
                                        
                                        var sMsg = ' does not exist in database or has status "hidden" for non owners';
                                        if(rec_ID>0){
                                            sMsg = 'Record id#'+rec_ID + sMsg;
                                        }else{
                                            sMsg = 'Record '+ sMsg;                                                    
                                        }
                                        window.hWin.HEURIST4.msg.showMsgDlg(sMsg, null, 
                                                {ok:'Close', title:'Record not found or hidden'}, 
                                                    {close:function(){ widget.closeEditDialog();}});
                                    }
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                    widget.closeEditDialog();
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
    //  Opens record edit  ->  openRecordEdit 
    //            or viewer  -> renderRecordData.php
    //  query_request - recordset or query string 
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
                url = url + 'viewers/record/renderRecordData.php?db='+window.hWin.HAPI4.database
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
    // info {rec_ID,rec_Title,rec_RecTypeID,relation_recID,trm_ID,dtl_StartDate,dtl_EndDate,rec_IsChildRecord}
    //
    //
    // selector_function opens select dialog. it it is true it opens record edit popup dialog
    createRecordLinkInfo:function(container, info, selector_function){
        
        //headers[targetID][0], headers[targetID][2] + headers[targetID][3]
       
        var rec_Title = info['rec_Title'];
        if(info['dtl_StartDate'] || info['dtl_EndDate']){
            rec_Title += ': ';
            if(info['dtl_StartDate']){
                rec_Title += info['dtl_StartDate'];
            }
            if(info['dtl_EndDate']){
                rec_Title += (' - '+info['dtl_EndDate']);
            }
        }
        rec_Title = window.hWin.HEURIST4.util.stripTags(rec_Title); //was htmlEscape
        
        var ph_gif = window.hWin.HAPI4.baseURL + 'hclient/assets/16x16.gif';
        var sRelBtn = '';
       
        var isHiddenRecord = false;
        
        if(selector_function !== false ){
                
            
                var not_owner = !(window.hWin.HAPI4.is_admin() || window.hWin.HAPI4.is_member( info['rec_OwnerUGrpID'] ));
                if(not_owner){
                     selector_function = false;
                     //this record hidden for current user
                     isHiddenRecord = (info['rec_NonOwnerVisibility']=='hidden');
                }
        }
        
        var isEdit = (selector_function!==false);
        
        if(info['trm_ID']>0){
            //margin-left:0.5em;
            sRelBtn = 
                '<div style="display:table-cell;min-width:120px;text-align:right;vertical-align: middle;">'
                +'<div class="btn-edit"/><div class="btn-rel"/><div class="btn-del"/></div>';
        }else if (!isHiddenRecord) {
            sRelBtn = '<div style="display:table-cell;min-width:23px;text-align:right;padding-left:16px;vertical-align: middle;">'
            +'<div class="btn-edit"/></div>';     // data-recID="'+info['rec_ID']+'"
        }
        
        var reltype = ''
        if(info['trm_ID']>0){
            var term_ID = info['trm_ID'];
            if (info['is_inward']){
                term_ID = window.hWin.HEURIST4.dbs.getInverseTermById(term_ID);    
            }
            var lbl = $Db.trm(term_ID, 'trm_Label');
            var len = lbl.length;
            lbl = window.hWin.HEURIST4.util.htmlEscape(lbl);
            //class="detailType" 'min-width:'+ Math.max(19, Math.min(reltype.length,25))+'ex;
            reltype = '<div style="display:table-cell;min-width:'+(Math.min(20,len)+1)+'ex;'
                +'color: #999999;text-transform: none;padding-left:4px;vertical-align: middle;">'
                +  lbl + '</div>'
        }
        
        var rectype_icon = '<div style="display:table-cell;vertical-align: middle;padding: 0 4px'+(reltype==''?'':' 0 16px')+';">'
                        + '<img src="'+ph_gif+'"  class="rt-icon" style="' //'margin-right:10px;'
                        + ((info['rec_RecTypeID']>0)?
                            'background-image:url(\''    //vertical-align:top;margin-top:2px;
                            + top.HAPI4.iconBaseURL+info['rec_RecTypeID'] + '\');'   //rectype icon
                           :'') 
                        + '"/>'
                        + '</div>';
        
        var ele = $('<div class="link-div ui-widget-content ui-corner-all"  data-relID="'
                        +(info['relation_recID']>0?info['relation_recID']:'')+'" '
                        +' style="display: table-row;margin-bottom:0.2em;background:#F4F2F4 !important;">' //padding-bottom:0.2em;

                        //relation type
                        
        + '<div '  // class="detail"   truncate
                        + 'style="display:table-cell; word-break: break-word; vertical-align: middle;">'  //min-width:60ex;max-width:160ex;
                        
                        + reltype
                        
                        + (info['rec_IsChildRecord']==1
                            ?'<span style="font-size:0.8em;color:#999999;padding:4px 2px;display:table-cell;min-width: 5ex;vertical-align:middle;">'
                                +'child</span>':'')
                            
                        //triangle icon fo
                        + ((reltype!='' && isEdit)?'<span style="display:table-cell;vertical-align:middle;padding-top:3px">'
                            +'<span class="ui-icon ui-icon-triangle-1-e"/></span>':'') //padding-top:3px;

                        //record type icon for resource
                        + (reltype==''?rectype_icon:'')
                        
                        // record title 
                        
                        +'<span class="related_record_title'
                            + ((info['rec_RecTypeID']>0)?'':' ui-state-error')
                            + '" data-rectypeid="'+ info['rec_RecTypeID']
                            + '" data-recid="'
                                        +info['rec_ID']
                                        +'" style="display:table-cell;vertical-align:middle">'  //padding-top:4px;
                        + rec_Title
                        + '</span>'
                        
        + '</div>'
                        //record type icon for relmarker
                        //+ (reltype==''?'': '<div class="btn-edit"/>')                        
                        + sRelBtn
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
            .css({'font-size': '0.8em', height: '21px', 'max-width': '18px'})
            .click(function(event){
                window.hWin.HEURIST4.msg.showMsgDlg(
                    'You are about to delete link between records<br><br>Are you sure?',
                     function(){
                        
                          var recID = ele.attr('data-relID');
                         
                          if(recID>0){  
                              
                              window.hWin.HAPI4.RecordMgr.remove({ids: recID}, 
                                function(response){
                                    if(response.status == window.hWin.ResponseStatus.OK){
                                        
                                          ele.trigger('remove');
                                          ele.remove();
                                          window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Relation has been deleted'));

                                          if($(container).find('.link-div').length==0){
                                                $(container).find('.add-rel-button').show();
                                          }
                                          
                                    }else{
                                        window.hWin.HEURIST4.msg.showMsgErr( response );
                                    }
                                });

                              /*  
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
                              */
                          
                          }else{
                              //remove link field
                              
                              //todo
                          }
                     },
                     {title:'Warning',yes:'Proceed',no:'Cancel'});
            });
        }
        

        if(info['relation_recID']>0){
            
            var bele = ele.find('.btn-rel');
            
            $('<img src="'+ph_gif+'"  class="rt-icon" style="vertical-align: middle;background-image:url(\''
                            + top.HAPI4.iconBaseURL + '1\');"/>'
            +'<span class="ui-button-icon ui-icon ui-icon-pencil" style="margin:0"></span>').appendTo(bele);
            
            //.button({text:false, label:top.HR((isEdit?'Edit':'View')+' relationship record'),icons:{primary:'ui-icon-pencil'}})
            
            bele.addClass('ui-button').css({'font-size': '0.8em', height: '18px', 'max-width': '40px',
                'min-width': '40px', display: 'inline-block', padding: 0, background: 'none'})
            .click(function(event){
                event.preventDefault();
                
                var recID = ele.attr('data-relID');
                window.hWin.HEURIST4.ui.openRecordInPopup(recID, null, isEdit,
                    {relmarker_field: info['relmarker_field'], relmarker_is_inward: info['is_inward'],
                    selectOnSave:true, edit_obstacle: true, onselect:
                    function(event, res){
                        if(res && window.hWin.HEURIST4.util.isRecordSet(res.selection)){
                            var recordset = res.selection;
                            var record = recordset.getFirstRecord();
                            var related_ID = recordset.fld(record, 'rec_ID'); //relationship record                             
                            var DT_RELATION_TYPE = window.hWin.HAPI4.sysinfo['dbconst']['DT_RELATION_TYPE'];
                            var DT_RELATED_REC_ID = window.hWin.HAPI4.sysinfo['dbconst']
                                [info['is_inward']?'DT_PRIMARY_RESOURCE':'DT_TARGET_RESOURCE'];

                            // e - search for temp also
                            window.hWin.HAPI4.RecordMgr.search({q: 'ids:'+related_ID, w: "e", 
                                        f:[DT_RELATION_TYPE,DT_RELATED_REC_ID]},  
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    var recordset = new hRecordSet(response.data);
                                    if(recordset.length()>0){
                                        var record = recordset.getFirstRecord();
                                        var term_ID = recordset.fld(record,DT_RELATION_TYPE);
                                        //update relation type !!!!
                                        if(info['is_inward']){
                                            term_ID = window.hWin.HEURIST4.dbs.getInverseTermById(term_ID);
                                        }
                                        ele.find('.detailType').text($Db.trm(info['trm_ID'], 'trm_Label')); 
                                        var related_ID = recordset.fld(record, DT_RELATED_REC_ID);  

                                        // e - search for temp also
                                        window.hWin.HAPI4.RecordMgr.search({q: 'ids:'+related_ID, w: "e", f:"header"},  
                                        function(response){
                                            if(response.status == window.hWin.ResponseStatus.OK){
                                                var recordset = new hRecordSet(response.data);
                                                if(recordset.length()>0){
                                                    var record = recordset.getFirstRecord();
                                                    var rec_Title = recordset.fld(record,'rec_Title');
                                                    if(!rec_Title) {rec_Title = 'New record. Title is not defined yet.';}
                                        
                                                    ele.find('.related_record_title')
                                                            .text( window.hWin.HEURIST4.util.stripTags(rec_Title) )
                                                            .attr('data-recid', related_ID);
                                                            
                                                    var rec_RecType = recordset.fld(record,'rec_RecTypeID');                            
                                                    //@todo - update record type icon
                                                }
                                            }
                                        });
                                    }
                                }
                            });
                            
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
            
            
            //rectype_icon+
            
            if(info['rec_RecTypeID']>0){
                
                
                if(info['relation_recID']>0){
            
                    $('<img src="'+ph_gif+'"  class="rt-icon" style="vertical-align: middle;background-image:url(\''
                            + top.HAPI4.iconBaseURL + info['rec_RecTypeID'] + '\');"/>'
                            +'<span class="ui-button-icon ui-icon ui-icon-pencil" style="margin:0"></span>').appendTo(btn_edit);
            
                    btn_edit.addClass('ui-button').css({'font-size': '0.8em', 'height': '18px', 'max-width': '40px',
                            'min-width': '40px', padding: 0, 'margin-right': '10px', background: 'none'});
                            
                }else{
                    
                    btn_edit.button({text:false, label:top.HR('Edit linked record'),
                                    icons:{primary:'ui-icon-pencil'}})
                                .css({'font-size': '0.8em', height: '21px', 'max-width': '18px'})
                    
                }
                
                btn_edit
                    .attr('data-recID', info['rec_ID'])
                    .click(function(event){
           
            var recID = $(event.target).hasClass('ui-button')
                    ?$(event.target).attr('data-recID')
                    :$(event.target).parent('.ui-button').attr('data-recID');
          
            window.hWin.HEURIST4.ui.openRecordInPopup(recID, null, isEdit,
            {selectOnSave:true, edit_obstacle: true, onselect: 
                    function(event, res){
                        if(res && window.hWin.HEURIST4.util.isRecordSet(res.selection)){
                            var recordset = res.selection;
                            var record = recordset.getFirstRecord();
                            var rec_Title = window.hWin.HEURIST4.util.stripTags(recordset.fld(record,'rec_Title')); //was htmlEscape
                            
                            ele.find('span[data-recID='+recID+']').text(rec_Title);
                        }
                    }}
                );
                            
                        });
                        
            }else{
                btn_edit.hide();    
            }
        }
        
        $(container).find('.add-rel-button').hide();
        
        return ele;
    },
    
    
    //
    //
    //
    getRecordTitle: function(recID, callback){
        
            window.hWin.HAPI4.RecordMgr.search({q: 'ids:'+recID, w: "e", f:"header"},  
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    var recordset = new hRecordSet(response.data);
                    if(recordset.length()>0){
                        var record = recordset.getFirstRecord();
                        if($.isFunction(callback)){
                            callback(recordset.fld(record,'rec_Title'));    
                        }
                    }
                }
            });
        
    },

    //
    // $select jquery select
    //
    createTemplateSelector: function($select, topOptions, defValue){
        
        var baseurl = window.hWin.HAPI4.baseURL + "viewers/smarty/templateOperations.php";
        var request = {mode:'list', db:window.hWin.HAPI4.database};
        
        window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, 
            function(context){
                
                var opts = topOptions?topOptions:[];
                if(context && context.length>0){
                    for (var i=0; i<context.length; i++){
                        opts.push({key:context[i].filename, title:context[i].name});
                    } // for
                }
                
                window.hWin.HEURIST4.ui.fillSelector($select[0], opts);
                if(defValue){
                    $select.val( defValue );
                }
                window.hWin.HEURIST4.ui.initHSelect($select[0], false);
                
            });
        

    },
    
    //------------------------------------
    // configMode.entity
    // configMode.filter_group
    createEntitySelector: function(selObj, configMode, topOptions, callback){
        
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
        }else if(configMode.entity=='defRecTypeGroups'){
            
            selObj = window.hWin.HEURIST4.ui.createRectypeGroupSelect(selObj, topOptions);
            return selObj;
            
        }else if(configMode.entity=='defDetailTypeGroups'){
            
            selObj = window.hWin.HEURIST4.ui.createDetailtypeGroupSelect(selObj, topOptions);
            return selObj;

        }else if(configMode.entity=='defVocabularyGroups'){
            
            selObj = window.hWin.HEURIST4.ui.createVocabularyGroupSelect(selObj, topOptions);
            return selObj;
            
/*            
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
*/          
        }else if(configMode.entity=='SysImportFiles'){
            fieldTitle = 'sif_TempDataTable';//'imp_table';
            request['entity'] = 'sysImportFiles';
            request['ugr_ID'] = configMode.filter_group;
        }

        selObj = window.hWin.HEURIST4.ui.createSelector(selObj, null);
        
        if(request['entity']){
        
            window.hWin.HAPI4.EntityMgr.doRequest(request,
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            
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

                            selObj = window.hWin.HEURIST4.ui.createSelector(selObj, groups);
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                        
                        if($.isFunction(callback)){
                            callback(selObj);
                        }
                        
                    });
                              
        }
        return selObj;
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
            
            if(!options.container){ //container not defined - add new one to body
                
                manage_dlg = $('<div id="heurist-dialog-'+entityName+'-'+window.hWin.HEURIST4.util.random()+'">')
                    .appendTo( $('body') )
                    [widgetName]( options );
            }else{
                if($(options.container)[widgetName]('instance')){
                    $(options.container)[widgetName]('destroy');
                }
                $(options.container).empty();
                $(options.container).show();
                
                if(!$(options.container).attr('id')){
                    $(options.container).uniqueId()
                }
                
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
                 entityName=='DefRecTypeGroups' || 
                 entityName=='DefRecStructure' || 
                 entityName=='DefTerms' || 
                 entityName=='DefVocabularyGroups' || 
                 entityName=='SysBugreport')){ 
                scripts.push(path+'search'+entityName+'.js');
            }
            
            //load missed javascripts
            $.getMultiScripts(scripts)
            .done(function() {
                // all done
                window.hWin.HEURIST4.ui.showEntityDialog(entityName, options);
            }).fail(function(error) {
                //console.log(error);                
                // one or more scripts failed to load
                window.hWin.HEURIST4.msg.showMsgWorkInProgress();
            }).always(function() {
                // always called, both on success and error
            });
            
        }
    },

    //
    // show record action dialog
    //
    showImportStructureDialog: function(options){
    
            var  doc_body = $(window.hWin.document).find('body');
            var manage_dlg = $('<div id="heurist-dialog-importRectypes-'+window.hWin.HEURIST4.util.random()+'">')
                .appendTo( doc_body )
                .importStructure( options );
                
        
    },
    
    //
    //
    //
    showPublishDialog: function( options ){
        
        //OK! script as been loaded
        if( typeof hPublishDialog==='undefined' || !$.isFunction(hPublishDialog)){        
            var that = this;
            $.getScript(window.hWin.HAPI4.baseURL+'hclient/framecontent/publishDialog.js?t'
                        +window.hWin.HEURIST4.util.random(),  
                function(){ 
                        window.hWin.HEURIST4.ui.showPublishDialog( options );
                }
            );        
        }else{
            if(!window.hWin.HEURIST4.ui.publishDlg) {
                window.hWin.HEURIST4.ui.publishDlg = new hPublishDialog();                        
            }
            window.hWin.HEURIST4.ui.publishDlg.openPublishDialog( options );
        }
        
    },
    
    //
    // show map style edit dialog
    //
    showEditSymbologyDialog: function(current_value, needName, callback){
        //todo optionally load dynamically editing_exts.js
        editSymbology(current_value, needName, callback);
    },

    //
    // show heurist theme dialog
    //
    showEditThemeDialog: function(current_value, needName, callback){
        //todo optionally load dynamically editTheme.js
        editTheme(current_value, callback);
    },

    //
    // show record action dialog
    // options 
    // record_id
    //  -1 create set of records for website
    //  -2 create webpage record for embed
    // field_id - to open editor of specific field for edit_input
    // callback
    // webpage_title  -title for new embed page
    //
    showEditCMSDialog: function( options ){
        //todo optionally load dynamically editCMS.js
        if( window.hWin.HEURIST4.util.isNumber( options ) ){
            options = {record_id:options};
        }
            
        editCMS(  options ); //see editCMS.js
    },

    //
    // show action dialog based on 
    //   recordAction widgets (see widget/records) or 
    //   cms/embedDialog widget
    //   
    //
    showRecordActionDialog: function(actionName, options){

        
        if(!options) options = {};
        if(options.isdialog!==false) options.isdialog = true; //by default popup      

        var  doc_body = $(window.hWin.document).find('body');
        if($.isFunction(doc_body[actionName])){ //OK! widget script js has been loaded
        
            var manage_dlg;
            
            if(!options.container){ //container not defined - add new one to body
                
                manage_dlg = $('<div id="heurist-dialog-'+actionName+'-'+window.hWin.HEURIST4.util.random()+'">')
                    .appendTo( doc_body )
                    [actionName]( options );
            }else{
                
                if($(options.container)[actionName]('instance')){
                    
                    if(options.need_reload===false){
                        $(options.container).show();
                        return;
                    }else{
                        $(options.container)[actionName]('destroy');
                    }
                    
                }
                $(options.container).empty();
                $(options.container).show();
                
                manage_dlg = $(options.container)[actionName]( options );
            }
            
            return manage_dlg;
        
        }else{
            
            var path = window.hWin.HAPI4.baseURL + 'hclient/widgets/'
                +(actionName=='embedDialog'?'cms/':'record/');
            
            var scripts = [ path+actionName+'.js'];
            if(actionName=='recordAdd'){
                scripts= [path+'recordAccess.js', path+'recordAdd.js'];
            }
            
            //load missed javascripts
            $.getMultiScripts(scripts)
            .done(function() {
                // all done
                window.hWin.HEURIST4.ui.showRecordActionDialog(actionName, options);
            }).fail(function(error) {
                // one or more scripts failed to load
                //console.log(error);                
                window.hWin.HEURIST4.msg.showMsgWorkInProgress();
            }).always(function() {
                // always called, both on success and error
            });
            
        }
    },    
    
    //
    // 
    getRidGarbageHelp: function(help_text){

        //get rid of garbage help text
        if (window.hWin.HEURIST4.util.isnull(help_text) ||
            help_text.indexOf('Please rename to an appropriate heading')==0 || 
            help_text.indexOf('Please document the nature of this detail type')==0 ||
            help_text=='Another separator field' ||
            help_text=='Headings serve to break the data entry form up into sections'){
                
            help_text='';
        }
        
        return help_text;        
    },
    
    
    getMousePos: function(e){

        var posx = 0;
        var posy = 0;
        if (!e) var e = window.event;
        if (e.pageX || e.pageY)     {
            posx = e.pageX;
            posy = e.pageY;
        }
        else if (e.clientX || e.clientY)     {
            posx = e.clientX
            + document.documentElement.scrollLeft;
            posy = e.clientY
            + document.documentElement.scrollTop;
        }

        return [posx, posy];
    },
    
    
    validateName: function(name, lbl, maxlen){
      
            var swarn = "";
            var regex = /[\[\].\$]+/;
            var name = name.toLowerCase();
            if( name=="id" || name=="modified" || name=="rectitle"){
                   swarn = lbl+", you defined, is a reserved word. Please try an alternative";
            //}else if (name.indexOf('.')>=0 ) {  //regex.test(name)
            }else if (name!=''  && !(/^[^.'"}{\[\]]+$/.test(name))) {
                   swarn = lbl+" contains . [ ] { } ' \" restricted characters which are not permitted in this context. Please use alphanumeric characters.";
            }else if (name.indexOf('<')>=0 && name.indexOf('<')< name.indexOf('>') ) {
                   swarn = lbl+" contains '<>' characters which are not permitted in this context. Please use alphanumeric characters.";
            }else
            if(maxlen>0  && name.length>maxlen){
                swarn = 'Sorry, '+lbl+' exceeds the maximum allowed length - '+maxlen+' characters - by '
                    +(name.length-maxlen)+' characters. Please reduce length.';                
            }
            
            return swarn;
    },
        
    //
    // prevents entering restricted characters
    //
    preventChars: function(event){

        event = event || window.event;
        var charCode = typeof event.which == "number" ? event.which : event.keyCode;
        if (charCode && charCode > 31)
        {
            var keyChar = event.key?event.key:String.fromCharCode(charCode);
            // Old test only allowed specific characters, far too restrictive. New test only restrcts characters which will pose a problem
            // if(!/^[a-zA-Z0-9$_<> /,–—]+$/.test(keyChar)){
            var sWarn = '';
            
            var value = $(event.target).val();
            if((value.indexOf('<')>=0 && keyChar=='>') || 
               (value.indexOf('>')>0 && keyChar=='<')){
                   sWarn = 'Both < and > are forbid';
            }else
            if(/^[{}'".\[\]]+$/.test(keyChar)){
                sWarn = 'Restricted characters: . [ ] { } \' " ';
            }
            
            if(sWarn!=''){
                event.returnValue = false;
                var trg = event.target;
                
                window.hWin.HEURIST4.util.stopEvent(event);
                
                window.hWin.HEURIST4.msg.showMsgFlash(sWarn,700,null, trg);
                setTimeout(function(){
                        $(trg).focus();
                }, 750);
                
                return false;
            }
        }
        return true;
    },    
    
    preventNonNumeric: function(evt) {
        var theEvent = evt || window.event;
        var key = theEvent.keyCode || theEvent.which;
        if(key==37 || key==39) return;
        key = String.fromCharCode( key );
        var regex = /[0-9]|\./;
        if( !regex.test(key) ) {
            theEvent.returnValue = false;
            theEvent.preventDefault();
        }
    },

    preventNonAlphaNumeric: function(evt) {
        var theEvent = evt || window.event;
        var key = theEvent.keyCode || theEvent.which;
        if(key==37 || key==39) return;
        key = String.fromCharCode( key );
        if(!/^[a-zA-Z0-9$_]+$/.test(key)){
            theEvent.returnValue = false;
            theEvent.preventDefault();
        }
    },
    
    cleanFilename: function(filename) {
        filename = filename.replace(/\s+/gi, '-'); // Replace white space with dash
        filename= filename.split(/[^a-zA-Z0-9\-\_\.]/gi).join('_');
        return filename;
    },

    //
    //
    //
    rgbToHex: function (r, g, b) {
        function __componentToHex(c) {
          var hex = c.toString(16);
          return hex.length == 1 ? "0" + hex : hex;
        }
      return "#" + __componentToHex(r) + __componentToHex(g) + __componentToHex(b);
    },
    
    hexToRgb: function (hex) {
      var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
      return result ? {
        r: parseInt(result[1], 16),
        g: parseInt(result[2], 16),
        b: parseInt(result[3], 16)
      } : null;
    },
    
    hexToRgbStr: function (hex, opacity) {
        var rgb = window.hWin.HEURIST4.ui.hexToRgb(hex);
        if(rgb!=null){
            if(opacity>0 && opacity<1){
                return 'rgba('+rgb.r+','+rgb.g+','+rgb.b+','+opacity+')';
            }else{
                return 'rgb('+rgb.r+','+rgb.g+','+rgb.b+')';
            }
        }else{
            return null;
        }
    },
    // returns hex rgb by name
    // see getColorFromTermValue  
    //
    getColorArr: function(x) {
      if (x == "names") {return ['aliceblue','antiquewhite','aqua','aquamarine','azure','beige','bisque','black','blanchedalmond','blue','blueviolet','brown','burlywood','cadetblue','chartreuse','chocolate','coral','cornflowerblue','cornsilk','crimson','cyan','darkblue','darkcyan','darkgoldenrod','darkgray','darkgrey','darkgreen','darkkhaki','darkmagenta','darkolivegreen','darkorange','darkorchid','darkred','darksalmon','darkseagreen','darkslateblue','darkslategray','darkslategrey','darkturquoise','darkviolet','deeppink','deepskyblue','dimgray','dimgrey','dodgerblue','firebrick','floralwhite','forestgreen','fuchsia','gainsboro','ghostwhite','gold','goldenrod','gray','grey','green','greenyellow','honeydew','hotpink','indianred','indigo','ivory','khaki','lavender','lavenderblush','lawngreen','lemonchiffon','lightblue','lightcoral','lightcyan','lightgoldenrodyellow','lightgray','lightgrey','lightgreen','lightpink','lightsalmon','lightseagreen','lightskyblue','lightslategray','lightslategrey','lightsteelblue','lightyellow','lime','limegreen','linen','magenta','maroon','mediumaquamarine','mediumblue','mediumorchid','mediumpurple','mediumseagreen','mediumslateblue','mediumspringgreen','mediumturquoise','mediumvioletred','midnightblue','mintcream','mistyrose','moccasin','navajowhite','navy','oldlace','olive','olivedrab','orange','orangered','orchid','palegoldenrod','palegreen','paleturquoise','palevioletred','papayawhip','peachpuff','peru','pink','plum','powderblue','purple','rebeccapurple','red','rosybrown','royalblue','saddlebrown','salmon','sandybrown','seagreen','seashell','sienna','silver','skyblue','slateblue','slategray','slategrey','snow','springgreen','steelblue','tan','teal','thistle','tomato','turquoise','violet','wheat','white','whitesmoke','yellow','yellowgreen']; }
/*      
      if (x == "names") {return ['AliceBlue','AntiqueWhite','Aqua','Aquamarine','Azure','Beige','Bisque','Black','BlanchedAlmond','Blue','BlueViolet','Brown','BurlyWood','CadetBlue','Chartreuse','Chocolate','Coral','CornflowerBlue','Cornsilk','Crimson','Cyan','DarkBlue','DarkCyan','DarkGoldenRod','DarkGray','DarkGrey','DarkGreen','DarkKhaki','DarkMagenta','DarkOliveGreen','DarkOrange','DarkOrchid','DarkRed','DarkSalmon','DarkSeaGreen','DarkSlateBlue','DarkSlateGray','DarkSlateGrey','DarkTurquoise','DarkViolet','DeepPink','DeepSkyBlue','DimGray','DimGrey','DodgerBlue','FireBrick','FloralWhite','ForestGreen','Fuchsia','Gainsboro','GhostWhite','Gold','GoldenRod','Gray','Grey','Green','GreenYellow','HoneyDew','HotPink','IndianRed','Indigo','Ivory','Khaki','Lavender','LavenderBlush','LawnGreen','LemonChiffon','LightBlue','LightCoral','LightCyan','LightGoldenRodYellow','LightGray','LightGrey','LightGreen','LightPink','LightSalmon','LightSeaGreen','LightSkyBlue','LightSlateGray','LightSlateGrey','LightSteelBlue','LightYellow','Lime','LimeGreen','Linen','Magenta','Maroon','MediumAquaMarine','MediumBlue','MediumOrchid','MediumPurple','MediumSeaGreen','MediumSlateBlue','MediumSpringGreen','MediumTurquoise','MediumVioletRed','MidnightBlue','MintCream','MistyRose','Moccasin','NavajoWhite','Navy','OldLace','Olive','OliveDrab','Orange','OrangeRed','Orchid','PaleGoldenRod','PaleGreen','PaleTurquoise','PaleVioletRed','PapayaWhip','PeachPuff','Peru','Pink','Plum','PowderBlue','Purple','RebeccaPurple','Red','RosyBrown','RoyalBlue','SaddleBrown','Salmon','SandyBrown','SeaGreen','SeaShell','Sienna','Silver','SkyBlue','SlateBlue','SlateGray','SlateGrey','Snow','SpringGreen','SteelBlue','Tan','Teal','Thistle','Tomato','Turquoise','Violet','Wheat','White','WhiteSmoke','Yellow','YellowGreen']; }
*/      
      if (x == "hexs") {return ['f0f8ff','faebd7','00ffff','7fffd4','f0ffff','f5f5dc','ffe4c4','000000','ffebcd','0000ff','8a2be2','a52a2a','deb887','5f9ea0','7fff00','d2691e','ff7f50','6495ed','fff8dc','dc143c','00ffff','00008b','008b8b','b8860b','a9a9a9','a9a9a9','006400','bdb76b','8b008b','556b2f','ff8c00','9932cc','8b0000','e9967a','8fbc8f','483d8b','2f4f4f','2f4f4f','00ced1','9400d3','ff1493','00bfff','696969','696969','1e90ff','b22222','fffaf0','228b22','ff00ff','dcdcdc','f8f8ff','ffd700','daa520','808080','808080','008000','adff2f','f0fff0','ff69b4','cd5c5c','4b0082','fffff0','f0e68c','e6e6fa','fff0f5','7cfc00','fffacd','add8e6','f08080','e0ffff','fafad2','d3d3d3','d3d3d3','90ee90','ffb6c1','ffa07a','20b2aa','87cefa','778899','778899','b0c4de','ffffe0','00ff00','32cd32','faf0e6','ff00ff','800000','66cdaa','0000cd','ba55d3','9370db','3cb371','7b68ee','00fa9a','48d1cc','c71585','191970','f5fffa','ffe4e1','ffe4b5','ffdead','000080','fdf5e6','808000','6b8e23','ffa500','ff4500','da70d6','eee8aa','98fb98','afeeee','db7093','ffefd5','ffdab9','cd853f','ffc0cb','dda0dd','b0e0e6','800080','663399','ff0000','bc8f8f','4169e1','8b4513','fa8072','f4a460','2e8b57','fff5ee','a0522d','c0c0c0','87ceeb','6a5acd','708090','708090','fffafa','00ff7f','4682b4','d2b48c','008080','d8bfd8','ff6347','40e0d0','ee82ee','f5deb3','ffffff','f5f5f5','ffff00','9acd32']; }
    },

  wait_timeout:0,
  wait_callback:null,
  wait_ms:3000,
  wait_terminated:false,
  
  //
  // clear timeout and starts new one
  // it is called on server call and user change
  //
  onInactiveReset: function(doNotStartAgain){
      
        if(window.hWin.HEURIST4.ui.wait_timeout) clearTimeout(window.hWin.HEURIST4.ui.wait_timeout);
        window.hWin.HEURIST4.ui.wait_timeout = 0;

        var events = ['mousedown', 'keydown', 'scroll', 'touchstart', 'focus']; //'mousemove', 
        events.forEach(function(name) {
            window.hWin.document.removeEventListener(name, window.hWin.HEURIST4.ui.onInactiveReset); 
        });    
        
        if(doNotStartAgain===true) {
            window.hWin.HEURIST4.wait_terminated = true;
            window.hWin.HEURIST4.ui.wait_ms == 0;
            return;   
        }

        //start again        
        if(!window.hWin.HEURIST4.wait_terminated && window.hWin.HEURIST4.ui.wait_ms>0){
            window.hWin.HEURIST4.ui.onInactiveStart();    
        }
        
  },

  //
  //
  //
  onInactiveStart: function(ms, cb){
      return; //DISABLED
      
      if(!window.hWin.HEURIST4.ui.wait_timeout){
          if(!isNaN(ms) && Number(ms)>0){
             window.hWin.HEURIST4.wait_terminated = false;
             window.hWin.HEURIST4.ui.wait_ms = ms;
          }
          if($.isFunction(cb)){
             window.hWin.HEURIST4.wait_callback = cb;
          }
          if($.isFunction(window.hWin.HEURIST4.wait_callback)){

             window.hWin.HEURIST4.ui.wait_timeout = setTimeout(
                  function(){
                      if(!window.hWin.HEURIST4.wait_terminated){
                          window.hWin.HEURIST4.wait_callback();
                      }
                  }, window.hWin.HEURIST4.ui.wait_ms); 

              var events = ['mousedown', 'keypress', 'scroll', 'touchstart', 'focus']; //'mousemove', 
              events.forEach(function(name) {
                        window.hWin.document.addEventListener(name, window.hWin.HEURIST4.ui.onInactiveReset, true); 
              });        

          }
      }
  },
  
  // @todo remove
  // edit base field definition (see saveStructureLib.php)
  //
  editBaseFieldDefinition: function(dty_ID, callback){
  
    var sURL = window.hWin.HAPI4.baseURL + "admin/structure/fields/editDetailType.html?db="
        +window.hWin.HAPI4.database+ "&detailTypeID="+dty_ID; //existing

    window.hWin.HEURIST4.msg.showDialog(sURL, {
           "close-on-blur": false,
            "no-resize": false,
            height: 680,
            width: 840,
            callback: function(context) {
                if(!Hul.isnull(context)){
                    window.hWin.HEURIST4.detailtypes = context.detailtypes;
                }
                if($.isFunction(callback)){
                    callback.call();
                }
            },
            afterclose: function(){
            }
            
    });
    
    return false;
  }
  
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
    if(item.element.attr( 'ui-state-error' )){
        wrapper.addClass('ui-state-error');
    }
    
    
    var rt_checkbox = item.element.attr( "rt-checkbox" );
    if(rt_checkbox>=0){
        $('<span style="float:left;padding:2px 0;min-width:1.5em;border:1px dot lightblue" '
                + ' data-id="'+item.element.attr( 'data-id' )
                + '" class="rt-checkbox ui-icon ui-icon-check-'+(rt_checkbox==1?'on':'off')+'"/>')
          .appendTo( wrapper );    
    }
    
    var icon_url = item.element.attr( "icon-url" );
    if(icon_url){
    
        $('<span style="float:left;padding-right:2px"><img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        + '" class="rt-icon" style="background-image: url(&quot;'+icon_url+ '&quot;);"/></span>')
          .appendTo( wrapper );    

    }

    var rt_count = item.element.attr( "rt-count" );
    if(rt_count>0){
        $('<span style="float:right;padding-right:2px">'+rt_count+'</span>')
          .appendTo( wrapper );    
    }

    var entity_id = item.element.attr( 'entity-id' );
    if(entity_id>0){
        $('<span style="font-size:0.7em;font-style:italic;padding-left:1em">id'+entity_id+'</span>')
          .appendTo( wrapper );    
    }
    
/*    
    if($(item.element).attr('depth')>0){
        console.log($(item.element).attr('depth')+'   '+item.label);
    }
*/    
    var depth = parseInt($(item.element).attr('depth'));
    if(!(depth>0)) depth = 0;
    if(rt_checkbox>=0) depth = depth + 1;
    wrapper.css('padding-left',(depth+0.2)+'em');
    
    /*icon
    $( "<span>", {
      style: item.element.attr( "data-style" ),
      "class": "ui-icon " + item.element.attr( "data-class" )
    })
      .appendTo( wrapper );
    */   

    return li.append( wrapper ).appendTo( ul );
  },
  
  
  hideOnMouseLeave: function(parent){
      
        var myTimeoutId = -1;
        
        //show hide function
        var _hide = function(ele) {
            myTimeoutId = setTimeout(function() {
                ele.hSelect('close');
                //$( ele ).hide();
                }, 800);
        },
        _show = function(ele, parent) {
            clearTimeout(myTimeoutId);
            
            $('.menu-or-popup').hide(); //hide other
            
            //parent.click();
            
            return false;
        };
      

        this._on( parent, {
            mouseenter : function(){_show(this.element, parent)},
            mouseleave : function(){_hide(this.element)}
        });
        this._on( this.element.hSelect('menuWidget'), {
            mouseenter : function(){_show(this.element, parent)},
            mouseleave : function(){_hide(this.element)}
        });
      
  },
});

$.fn.sideFollow = function(dtime) {

    var floating = $(this);
    var originalTop =  parseInt(floating.attr('data-top'));
    if(!(originalTop>=0)) originalTop = 180;

    dtime ? dtime = dtime : dtime = 1000;

    goFollow();

    $(window).scroll(function() {
        goFollow();
    });

    function goFollow() {
        var scrollTop = $(this).scrollTop();
        floating.animate({
            top: originalTop + scrollTop
        }, {
            duration: dtime,
            queue: false
        });
    }

}
