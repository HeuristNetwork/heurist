/**
* manageDefTerms.js - main widget to manage defTerms
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


$.widget( "heurist.manageUsrTags", $.heurist.manageEntity, {
   
    _entityName:'usrTags',
    
    //keep to refresh after modifications
    _keepRequest:null,
    _showAutoTags:false,
    
    _init: function() {
        
        this.options.default_palette_class = 'ui-heurist-admin';
        
        if(!$.isArray(this.options.selection_ids)) this.options.selection_ids = [];
        
        //this.options.layout_mode = 'basic';
        this.options.use_cache = true;
        if(this.options.list_mode!='compact') this.options.list_mode = 'accordions';
        this.options.edit_mode = 'inline'; //inline only
        
         //all,personal,grouponly,or list of ids
        if(!this.options.groups || this.options.groups == 'all'){
            this.options.groups = Object.keys(window.hWin.HAPI4.currentUser.ugr_Groups);    
            this.options.groups.unshift(window.hWin.HAPI4.user_id());
        }else if(this.options.groups=='personal'){
            this.options.groups = [window.hWin.HAPI4.user_id()];
        }else if(this.options.groups=='grouponly'){
            this.options.groups = Object.keys(window.hWin.HAPI4.currentUser.ugr_Groups);    
        }
        

        if(this.options.list_mode!='compact'){
            this.options.width = 900;    
            this.options.height = 600;
        }
        
        this._super();

        if(this.options.list_mode!='compact'){
            //initially hide usage/details        
            this.editForm.parent().hide();
            this.recordList.parent().css({'width':'100%', top:0});
            this.editFormToolbar.css({'padding-right':'8em'});
        
        
            if(this.options.select_mode=='manager'){
           /* 
                //expand and show tag details and usage
                this.btn_show_usage = $('<div>').css({'position':'absolute', top:3, right:3}).appendTo(this.element);
                this.btn_show_usage.css({'min-width':'9m','z-index':2})
                        .button({label: window.hWin.HR("Usage"), icons: {
                                secondary: "ui-icon-carat-1-w"}});
                        
                this._on( this.btn_show_usage, {"click": this.showHideUsage});
            */
            
                //this.searchForm.find('.heurist-helper1').hide();
                
                this._initInlineEditorControls();
                
            }

            this.searchForm.css({'height':'7em','padding':'10px'});    
            this.recordList.css('top','7em');    
            
        }
      
        var $parent = this.recordList.parents('.ui-dialog-content');
        if($parent.length==0) $parent = this.recordList.parents('body');
      
        this.list_div = $('<div class="list_div">')
            .addClass('ui-heurist-header')
            .css({'z-index':999999999, height:'auto', 'max-height':'200px', 'padding':'4px',
                  cursor:'pointer'})
            .appendTo($parent).hide();
        
        
        if(this.options.list_mode!='compact'){
            /*
            this._on(this.element, {'competency': function(event, level){
                var top = 3.3;
                if(level>0 && this.options.select_mode!='manager'){
                    top = top + 7;
                }
                this.searchForm.css('height',top+'em');    
                this.recordList.css('top',top+'em');    
                
            }});
            */
        }
        
    },
    //  
    // invoked from _init after loading of entity config    
    //
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }

        if(this.options.list_mode!='compact'){
            // init search header
            this.searchForm.searchUsrTags(this.options);
            
            this._on( this.searchForm, {
                    "searchusrtagsonresult": this.updateRecordList
                    });
            this._on( this.searchForm, {
                    "searchusrtagsonfilter": this.filterRecordList
                    });
            this._on( this.searchForm, {
                    "searchusrtagsongroupfilter": this.showHideGroups
                    });
        }else{
            
            var that = this;
            window.hWin.HAPI4.EntityMgr.getEntityData(this.options.entity.entityName, 
                (window.hWin.HAPI4.NEED_TAG_REFRESH===true), //force reload
                function(response){
                        that.updateRecordList(null, {recordset:response});
                });
                
            window.hWin.HAPI4.NEED_TAG_REFRESH = false;    
        }
        
        return true;
    },
    
    //
    // update all accodions after getting data from server
    //
    updateRecordList: function( event, data ){
        this._super(event, data);
        
        if(this.options.list_mode=='compact'){
            this._initCompactUI();
        }else{
            this._updateAccordions( this._cachedRecordset );    
        }
    },
    
    showHideGroups: function(event, groupid){
    
        if(groupid!='any' && groupid>0){
            this.recordList.find('div.group-acc').hide();
            this.recordList.find('div[data-id-acc="'+groupid+'"]').show();
        }else{
            this.recordList.find('div.group-acc').show();
        }
    },
    
    //
    //
    //
    showHideUsage:function(event){
        
        if(this.editForm.parent().is(':visible')){
            this.editForm.parent().hide();
            this.recordList.parent().css({'width':'100%'});
            this.recordList.css({'top':'2.8em'});
            this.searchForm.css({'height':'2.2em'});
            this.btn_show_usage.button('option','icons', {secondary: "ui-icon-carat-1-w"});
        }else{
            this._showRightHandPanel();
            this.btn_show_usage.button('option','icons', {secondary: "ui-icon-carat-1-e"});
        }
    },
    
    //
    //
    //
    _showRightHandPanel: function(){
            this.editForm.parent().show();
            this.editForm.parent().css({'left':306});
            this.recordList.parent().css({'width':305});
            this.recordList.css({'top':'7em'});
            this.searchForm.css({'height':'6.4em'});
    },
    
    //
    //
    //
    filterRecordList: function(event, request){
        //this._super();
        
        if(this._cachedRecordset && this.options.use_cache){
            this._keepRequest = request;
            var subset = this._cachedRecordset.getSubSetByRequest(request, this.options.entity.fields);
            this._updateAccordions( subset );
            this.showHideGroups(null, this.element.find('#input_search_group').val());
        }
    },
    
    refreshRecordList:function(){
        this.filterRecordList(null, this._keepRequest);
    },
    
//----------------------------------------------------------------------------------    
    
    _updateAccordions: function( recordset ){
        
        var that = this, idx;
       
        this.recordList.empty();
        
        //add accordion for group and "add tag" input
        function __addAcc(groupID, name){
            
                    var acc = $('<div>').addClass('group-acc')
                        .attr('data-id-acc', groupID).addClass('summary-accordion').appendTo(that.recordList);
                    $('<h3>').text(name).appendTo(acc);
                    
                    var content = $('<div>').attr('data-id', groupID)
                        .addClass('summary-content ui-heurist-bg-light')
                        .css('padding','10px').appendTo(acc);
                    //init
                    acc.accordion({
                        collapsible: true,
                        //active: (usrPreferences.activeTabs.indexOf(String(idx))>=0) ,
                        heightStyle: "content"
                    });
                    
                    if(that.options.select_mode=='select_multi'){
                        //split content on two section - available and selected
                        $('<div>').addClass('available')
                            .css({'min-width':'50%', width:'50%', 'border-right':'1px #9CC4D9 solid', 'padding-right':'10px', float:'left'})
                            .appendTo(content).append('<span style="height:2em">&nbsp;</span>');
                            
                        content = $('<div>').addClass('picked')
                            .css({width:'45%', 'padding-left':'10px', display:'inline-block'})
                            .appendTo(content);
                            
                            //'border-left':'1px #9CC4D9 solid',
                    }

                //ADD button        
                $('<div class="recordDiv tagDiv tagDiv-fixed-width" style="display:block;text-decoration:none">'
                + '<label>Add a tag</label> <input type="text" style="width:15ex" size="60"/>'
                + '<div class="rec_action_link" data-key="add" style="margin-left:4px;visibility:visible !important"/>'
             /*   
                + '<div title="Click to add tag" class="rec_action_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="add" >'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-plus"></span><span class="ui-button-text"></span>'
                + '</div>'*/
                +'</div>').appendTo(content);
            
        }
        
        __addAcc(window.hWin.HAPI4.user_id(), 'Personal tags');
        
        for (var groupID in window.hWin.HAPI4.currentUser.ugr_Groups)
        if(groupID>0){
            var name = window.hWin.HAPI4.sysinfo.db_usergroups[groupID];
            if(!window.hWin.HEURIST4.util.isnull(name)){
                __addAcc(groupID, name);
            }
        }
        
        //add content
        var records = recordset.getRecords();
        var order = recordset.getOrder();
        var recID, label, groupid, record;
        
        var maxlen = {};

        for (idx=0;idx<order.length;idx++){

            recID = order[idx];
            if(recID && records[recID]){
                
                record = records[recID];
                label = recordset.fld(record,'tag_Text');
                groupid = recordset.fld(record,'tag_UGrpID');
                usage = recordset.fld(record,'tag_Usage');

                
                var content = this.recordList.find('div[data-id="'+groupid+'"]');
                
                //var label = label + (usage>0?(' ('+usage+')'):'');
                
                var item = '<div  recid="'+recID+'" groupid="'+groupid+'" usage="'+usage
                            +'" class="recordDiv tagDiv tagDiv-fixed-width"'
                    //(this.options.selection_ids.indexOf(recID)<0?'in-available':'in-selected')+'"
                            +'><label>'+ label
                            +'</label>'
+('<span class="user-list-edit" style="margin:0 4px;display:'
+ (usage>0?'inline-block':'none')
+';width:3ex;" '
+'title="Search for records marked with this tag">'
+usage
//+'<span class="ui-icon ui-icon-link" style="font-size:0.8em;float:right;top:2px;right:2px"></span>
+'</span>')                                                  
                            +'<div class="rec_action_link" data-key="delete" style="float:right"/>'
                            +'</div>';
               
                if(!maxlen[groupid] || label.length>maxlen[groupid]) {
                    maxlen[groupid] = label.length;   
                }
                        
                if(this.options.select_mode=='select_multi'){
                    if(window.hWin.HEURIST4.util.findArrayIndex(recID, this.options.selection_ids)<0){
                        $(item).appendTo(content.find('.available'));    
                    }else{
                        $(item).appendTo(content.find('.picked'));    
                    }
                   
                    
                }else{
                    $(item).appendTo(content);
                }
                
            }
        }//for

        //set min widht by max label width        
        for (var groupid in maxlen)
        if(groupid>0){
            var wd = (maxlen[groupid]<10?10:maxlen[groupid])+8;

            this.recordList.find('div.recordDiv[groupid="'+groupid+'"]')
                .attr('data-wd',wd)
                .css({'min-width': wd+'ex','max-width': wd+'ex'});
        }
        
        
            
            //add buttons
            var btns = this.recordList.find('div.rec_action_link[data-key="add"]').button(
                            {icons: {primary: 'ui-icon-circle-plus'}, 
                             text: false, 
                             label: window.hWin.HR('Click to add tag')});

            var inputs = this.recordList.find('input');                             
            
            this._on(inputs, {'keypress': function(event){
                
                    var code = (event.keyCode ? event.keyCode : event.which);
                    if (code == 13) {
                        this.onAddTag( event );
                        window.hWin.HEURIST4.util.stopEvent(event);
                    }
                
            }   });               
            this._on(btns, {'click': this.onAddTag});
            
            if(this.options.select_mode=='manager'){
                //delete buttons        
                btns = this.recordList.find('div.rec_action_link[data-key="delete"]').button(
                            {icons: {primary: 'ui-icon-circle-close'}, 
                             text: false, 
                             label: window.hWin.HR('Click to delete tag')});

                this._on(btns, {'click':this.onDeleteTag});
            }
            
            //edit on select click
            var items = this.recordList.find('div.recordDiv');
            this._on(items, {'click':this.onTagItemClick});
            
            this.element.find('#input_search').focus();
    },

    //
    //
    //
    onAddTag: function( event ) {
        
        var that = this;
                
        var item = $(event.target).parents('.tagDiv');
        var inpt = item.find('input');
        var text = inpt.val();
                    
        if(!window.hWin.HEURIST4.util.isempty(text)){
            
            if(window.hWin.HEURIST4.msg.checkLength(inpt, 'Tag', null, 3, 0)){
                
                var groupid;
                if(this.options.list_mode!='compact'){
                    groupid = $(event.target).parents('.summary-content').attr('data-id');
                }else{
                    groupid = item.find('select').val();
                }
                var request = {'tag_Text':'='+text,'tag_UGrpID':groupid};  //exact
                var fields = {'tag_Text':text,'tag_UGrpID':groupid};

                //check duplication within group
                var subset = this._cachedRecordset.getSubSetByRequest(request, 
                                            this.options.entity.fields);

                if(subset.length()>0){
                    
                    var recID = Number(subset.getOrder()[0]);
                    if(window.hWin.HEURIST4.util.findArrayIndex(recID, this.options.selection_ids)<0){
                    
                        if(this.options.select_mode=='select_multi'){
                            //move duplication to picked
                            if(this.options.list_mode!='compact'){
                                this.recordList.find('div.recordDiv[recid='+recID+']')
                                    .appendTo(this.recordList.find('div[data-id="'+groupid+'"] > .picked'));
                            }else{
                                this._addTagToPicked(recID);
                            }
                            inpt.val('').focus();
                        }else{
                        /*
                        inpt.addClass( "ui-state-error" );*/
                            window.hWin.HEURIST4.msg.showMsgFlash('<span class="ui-state-error" style="padding:10px">Duplication</span>', 2000);
                        }
                        
                    }
                   //todo select tag instead of warning
                   
                }else{                                                        
                    that._currentEditID = -1;
                    fields['tag_ID'] = -1;
                    that._saveEditAndClose( fields );
                    inpt.val('').focus();
                    return;
                }
            }
        }
        inpt.focus(); 
    },
    
    onDeleteTag: function(event){
        
        var recid = $(event.target).parents('.recordDiv').attr('recid');
        //var key = $(event.target).parents('.rec_action_link').attr('data-key');
        var action = {action:'delete', recID:recid}
        this._onActionListener(null, action);
        
        window.hWin.HEURIST4.util.stopEvent(event);
    },

    onTagItemClick: function(event) {
        
                        var item = $(event.target).parents('.recordDiv');
                        var recid = item.attr('recid');
                        if(recid>0){
                            if(this.options.select_mode=='select_multi'){
                                
                                var idx = window.hWin.HEURIST4.util.findArrayIndex(recid, this.options.selection_ids);
                                var content = item.parents('.summary-content');
                                
                                if(idx<0){
                                    this.options.selection_ids.push(recid);
                                    item.appendTo( content.find('.picked') );
                                }else{
                                    this.options.selection_ids.splice(idx,1);
                                    item.appendTo( content.find('.available') );
                                }
                                
                            }
                            else if(this.options.select_mode=='select_single'){

                                //this.selectedRecords([recid]);
                                this.options.selection_ids.push(recid);
                                this._selectAndClose();
                                
                            }else{
                                
                                this.selectedRecords([recid]);
                                
                                var isSearch = $(event.target).is('.user-list-edit')
                                window.hWin.HEURIST4.util.stopEvent(event);
                                
                                if(isSearch){
                                    var sURL = window.hWin.HAPI4.baseURL + '?db='+window.hWin.HAPI4.database+'&q=tag:'+recid;
                                    window.open(sURL)                                    
                                } else{
                                    //this._onActionListener(event, {action:'edit'});
                                    //replace label with input element to edit/replace
                                    this._showInlineEditorControls(item);
                                }
                            }
                        }
    },

    _afterSaveEventHandler: function( recID, fields ){
        
            var isNewRecord = (this._currentEditID<0);
            
            if(isNewRecord){
                //added in manageEntity this._cachedRecordset.addRecord(recID, fields);
                
                this._currentEditID = null;
                this.addEditRecord(null);//clear edit form
                
                if(this.options.list_mode=='compact'){
                    this._addTagToPicked(recID);
                }else{
                
                    var content = this.recordList.find('div[data-id="'+fields['tag_UGrpID']+'"]');
                    
                    if(this.options.select_mode=='select_multi'){
                        this.options.selection_ids.push(recID);
                        content = content.find('.picked');
                    }
                    
                    var ele = $('<div class="recordDiv tagDiv tagDiv-fixed-width" recid="'+recID
                    +'"><label>'+ fields['tag_Text']
                    +'</label><div class="rec_action_link" data-key="delete" style="float:right"/>'
                    +'</div>')
                            .appendTo(content);
                            
                            
                    if(this.options.select_mode=='manager'){
                        var btns = ele.find('div.rec_action_link').button(
                                        {icons: {primary: 'ui-icon-circle-close'}, 
                                         text: false, 
                                         title: window.hWin.HR('Click to delete tag')});
                        this._on(btns, {'click':this.onDeleteTag});
                    }
                    this._on(ele, {'click':this.onTagItemClick});
                }
                
            }else{
                var item = this.recordList.find('div[recid='+recID+']');
                
                item.find('label').text(fields['tag_Text']); //set new tagname
                
                item.find('span.user-list-edit')
                    .css('display',usage>0?'inline-block':'none').text(usage);
                    
                var oldwd = item.attr('data-wd');
                var wd = fields['tag_Text'].length+9;
                if(wd>oldwd){ //reset tag width for all items in group
                    this.recordList.find('div.recordDiv[groupid="'+fields['tag_UGrpID']+'"]')
                        .attr('data-wd', wd)
                        .css({'min-width': wd+'ex','max-width': wd+'ex'});
                }
                    

                //reload
                //var recordset = this.getRecordSet([recID]);
                //this._initEditForm_step4(recordset);
            }
    },
    
    //
    //
    //   
    _afterDeleteEvenHandler: function( recID ){
        this._currentEditID = null;
        //this.addEditRecord(null);
        //if(this._editing)this._editing.initEditForm(null, null); 
        
        //detach inline input    
        if(this.edit_replace_input) this.edit_replace_input.appendTo(this.element);
            
        this.recordList.find('div.recordDiv[recid='+recID+']').remove();
        this._cachedRecordset.removeRecord(recID);
    },
    
    //
    //
    //
    selectedRecords: function(value){
        
        if(window.hWin.HEURIST4.util.isnull(value)){
            this._selection = this._cachedRecordset.getSubSetByIds(this.options.selection_ids);            
        }
        
        return this._super();
    },
    
//----------------------------------------------------------------------------------    
    
    //
    // compact mode
    // consist of three elements 
    // 1) selected tags by group
    // 2) input and group selector  
    // 3) top and recent tags by group
    _initCompactUI: function(){
        
       var that = this;
        
       var idx, panel = this.recordList, pnl_picked;
       
       panel.empty();//.css({'font-size': '0.9em'});
       
       if(that.options.show_top_n_recent){
            $('<div class="header header-label"><label><b>Tags to assign:</b></label></div>')
                .css({display:'inline-block', 'vertical-align': 'top', 'padding-right': '16px',
                            width: '157px', 'text-align': 'right'}).hide()   //was 98px
                .appendTo(panel);
            pnl_picked = $('<div>').appendTo(panel); //A91 .css({display:'inline-block'})
            $('<br>').appendTo(panel);
            $('<hr style="margin:5px 10px;margin-top:20px">').appendTo(panel);
       }else{
            pnl_picked = panel;
       }
       

       if(window.hWin.HEURIST4.util.findArrayIndex(window.hWin.HAPI4.user_id(), this.options.groups)>=0){ 
            //1. selected tags by group
            if(that.options.show_top_n_recent){
                var dele = $('<div>').css({display:'table-row','line-height':'20px'})
                    .appendTo(pnl_picked).hide();
                $('<div class="header header-label">Personal</div>')
                    .css({display:'table-cell', 'vertical-align': 'top', 'padding-right': '16px',
                            'max-width': '157px', 'width': '157px', 'text-align': 'right', 'font-style':'italic'})
                    .attr('data-id-header', window.hWin.HAPI4.user_id())
                    .addClass('truncate')
                    .appendTo(dele);        
                $('<div>')
                    .css({display:'table-cell'})
                    .attr('data-id', window.hWin.HAPI4.user_id())
                    .appendTo(dele);        
            }else{
                $('<div><i style="display:inline-block;">Personal:&nbsp;</i></div>') //width:110px;text-align:right;
                    .css({'padding':'3px 0px'})
                    .attr('data-id', window.hWin.HAPI4.user_id())
                    .hide().appendTo(pnl_picked);
            }
       }

            
        //render group divs
        //with list of selected tags
        var groups = window.hWin.HAPI4.currentUser.ugr_Groups;
        for (var groupID in groups)
        {
            if(groupID>0 && window.hWin.HEURIST4.util.findArrayIndex(groupID, this.options.groups)>=0){
                var name = window.hWin.HAPI4.sysinfo.db_usergroups[groupID];
                if(!window.hWin.HEURIST4.util.isnull(name))
                {   
                    if(that.options.show_top_n_recent){
                        var dele = $('<div>').css({'display':'table-row','line-height':'20px'})
                                .appendTo(pnl_picked).hide();         
                        $('<div class="header header-label">'+name+'</div>')
                            .css({display:'table-cell', 'vertical-align': 'top', 'padding-right': '16px',
                                    'max-width': '157px', 'width': '157px', 'text-align': 'right', 'font-style':'italic'})
                            .attr('data-id-header',groupID)
                            .addClass('truncate')
                            .appendTo(dele);        
                        $('<div>')
                            .css({display:'table-cell'})
                            .attr('data-id', groupID)
                            .appendTo(dele);        
                    }else{
                        $('<div><i style="display:inline-block;">'+name+':&nbsp;</i></div>') //width:110px;text-align:right;
                            .css({'padding':'3px 0px'})
                            .attr('data-id', groupID).hide().appendTo(pnl_picked);
                    }
                    
                    
                }
            }
        }
        /* OLD WAY        
        for (idx in groups)
        {
            if(idx){
                var groupID = idx;
                var name = groups[idx][1];
                if(!window.hWin.HEURIST4.util.isnull(name))
                {
                    $('<div><i style="display:inline-block;width:110px;text-align:right;">'+name+':&nbsp;</i></div>')
                        .css({'padding':'3px 4px'})
                        .attr('data-id', groupID).hide().appendTo(panel);
                }
            }
        }
        */
        //add content - selected tags
        var recordset = this._cachedRecordset;
        var records = recordset.getRecords();
        var recID, label, groupid, record, grp, isnone = true;
        
        for (idx=0;idx<this.options.selection_ids.length;idx++){
            this._addTagToPicked(this.options.selection_ids[idx], true);
        }  
        
        //2. add group selector and search/add input     
        var mdiv = $('<div class="tagDiv" style="text-decoration:none;">'
                + (that.options.show_top_n_recent
                    ?('<div class="header" style="display: inline-block;text-align:right;padding:0 16px 0 0">'
                        +'<label>Find/assign:</label></div>'):'')
                + ' <input type="text" style="width:15ex;margin-right:10px" size="60"/>&nbsp;in&nbsp;&nbsp;'
                + '<select style="max-width:220px"></select>&nbsp;'
                + '<div class="rec_action_link" data-key="add" style="margin-left:10px;visibility:visible !important"/>'
                + '</div>').appendTo(panel);
                
        //3. top and recent tags        
        var top_n_recent = $('<div>').appendTo(panel);
        
                
        // add elements to mdiv - input and group selector                        
        var $parent = panel.parents('.ui-dialog-content');
        if($parent.length==0) $parent = panel.parents('body');
        
         
        var input_tag = mdiv.find('input');                             

        var sel_group = mdiv.find('select');
        
        var topOpt = null;
        if(window.hWin.HEURIST4.util.findArrayIndex(window.hWin.HAPI4.user_id(), this.options.groups)>=0){
            topOpt = [{key:window.hWin.HAPI4.user_id(), title:'Personal tags'}];
        }
        
        window.hWin.HEURIST4.ui.createUserGroupsSelect(sel_group[0], this.options.groups, topOpt);
        
        sel_group.change(function(){
              input_tag.val('');
              if(that.list_div) that.list_div.hide();  //drop down list

              var groupid = sel_group.val(); 

              //that.recordList.find('div[data-id!='+groupid+']').hide();
              //show assigned
              //that.recordList.find('div[data-id='+groupid+']').show();
              
              //show top and recent lists
              top_n_recent.empty();
              if(groupid>0 && that.options.show_top_n_recent){
                  
                    
                function __renderTags( event ){
                    if(event) that._showAutoTags = $(event.target).is(':checked'); 
                    top_n_recent.empty();
                    
                    $('<div style="padding-left:5px;font-style:italic;font-size:0.9em" class="header"><label>or select below:</label></div>').appendTo(top_n_recent);
                    that._getTagList(groupid,'Top', 10).appendTo(top_n_recent);
                    that._getTagList(groupid,'Recent', 10).appendTo(top_n_recent);
                    
                    $('<div style="padding:10px 6px;" class="header">'
                        +'<label for="cbShowAutoTags" style="font-style:italic;font-size:0.9em">Show automatically-added tags </label>'
                        +'<input id="cbShowAutoTags" type="checkbox" '+(that._showAutoTags?'checked':'')
                        +'/></div>').appendTo(top_n_recent);
                    top_n_recent.find('#cbShowAutoTags').click( __renderTags );    
                }    
                                    
                __renderTags( null );
              }
        });
        sel_group.change();
        
        
        //add button
        var btn_add = mdiv.find('div.rec_action_link')
                        .css({'vertical-align': 'bottom', height:'10px', 'font-size': '0.8em'}).hide()
                        .button({
                        //icons: {primary: 'ui-icon-circle-plus'}, 
                        //text: false, 
                         title: window.hWin.HR('Click to add tag to selection'),
                         label: window.hWin.HR('ADD')});

        this._on(input_tag, {'keypress': function(event){
            
                var code = (event.keyCode ? event.keyCode : event.which);
                if (code == 13) {
                    this.onAddTag( event );
                    window.hWin.HEURIST4.util.stopEvent(event);
                }
            
        },
        'keyup': function(event){
            
            //var input_tag = $(event.target);
            
            if(input_tag.val().length>1){
                
                var request = {tag_Text:input_tag.val(), tag_UGrpID:sel_group.val() };    
                var recordset = this._cachedRecordset.getSubSetByRequest(request, this.options.entity.fields);
                
                var records = recordset.getRecords();
                var order = recordset.getOrder();
                var recID, label, record;
                
                if(order.length>0){
                    that.list_div.empty();  
                    
                    var is_added = false;
                    
                    for (idx=0;idx<order.length;idx++){

                        recID = order[idx];
                        var kk = window.hWin.HEURIST4.util.findArrayIndex(recID,that.options.selection_ids);

                        if(recID && kk<0 && records[recID]){
                            is_added = true;
                            label = recordset.fld(records[recID],'tag_Text');
                            $('<div recid="'+recID+'" class="truncate">'+label+'</div>').appendTo(that.list_div)
                            .click( function(event){
                                $(event.target).hide();
                                var recID = $(event.target).attr('recid');
                                that._addTagToPicked(recID);
                                that.list_div.hide();
                                input_tag.val('').focus();
                            } );

                        }
                    }
                                           
                    that.list_div.addClass('ui-widget-content').position({my:'left top', at:'left bottom', of:input_tag})
                    //.css({'max-width':(maxw+'px')});
                    .css({'max-width':input_tag.width()+60});
                    if(is_added){
                        that.list_div.show();    
                    }else{
                        that.list_div.hide();
                    }
                    
                }else if(input_tag.val().length>2){
                    that.list_div.empty();
                    $('<div style="min-width:160px;font-size:0.8em" class="ui-widget-content">'
                    +'<span class="ui-icon ui-icon-check" '
                    +'style="display:inline-block;vertical-align:bottom;"/>'
                    +'Confirm&nbsp;and&nbsp;assign&nbsp;new&nbsp;Tag</div>')
                        .appendTo(that.list_div)
                            .click( function(event){
                                    btn_add.click();
                                    that.list_div.hide();
                            });
                    that.list_div.show()
                        .position({my:'left top', at:'left bottom', of:input_tag}).css({'max-width':'160px'});
                      
                }else{
                    that.list_div.hide();  
                }
            }else{
                that.list_div.hide();    
            }
            
        }}
        ); 
                      
        this._on(btn_add, {'click': this.onAddTag});
        
        this._on($(document), {'click': function(event){
           if($(event.target).parents('.list_div').length==0) { that.list_div.hide(); };
        }});
        
    },
    
    //
    //
    //
    _getTagList: function(group_id, sort_mode, limit){
        
        var request = {tag_UGrpID:group_id};    
        
        if(sort_mode=='Top'){
            request['sort:tag_Usage'] = '-1';
        }else if(sort_mode=='Recent'){
            request['sort:tag_Modified'] = '-1' 
        }
        
        var recordset = this._cachedRecordset.getSubSetByRequest(request, this.options.entity.fields);
        
        
        var records = recordset.getRecords();
        var order = recordset.getOrder();
        var recID, label, record;
        var list_div = $('<div><span">'+top.HR(sort_mode)+': </span></div>')   // style="font-weight:bold
            .css({'padding':'4px'}); //,'line-height':'22px'
        var that = this;
        
        if(order.length>0){
            
            var limit = (limit>0)?Math.min(order.length,limit):order.length;
            var iadded = 0;
            var ISO_8601 = /^(?:19|20)[0-9]{2}-(?:(?:0[1-9]|1[0-2])-(?:0[1-9]|1[0-9]|2[0-9])|(?:(?!02)(?:0[1-9]|1[0-2])-(?:30))|(?:(?:0[13578]|1[02])-31))$/i
            
            for (idx=0;idx<order.length;idx++){

                recID = order[idx];
                label = recordset.fld(records[recID], 'tag_Text');
                
                if(!that._showAutoTags){
                    var is_auto_tag = false;
                    var wrds = label.split(' ');
                    if(wrds.length>2){
                        for (var d in wrds) {
                            if (ISO_8601.test(wrds[d])) { 
                                is_auto_tag = true;
                                break;
                            }
                        }
                        if(is_auto_tag) continue;
                    }
                }
                
                    $('<a recid="'+recID+'" href="#">'+label+'</a>')
                        .click( function(event){
                            var recID = $(event.target).attr('recid');
                            that._addTagToPicked(recID);
                            return false;
                    } )
                    //.appendTo($('<div class="truncate" style="display:inline-block;padding:0px 10px"></div>')
                    .appendTo(list_div);
                    
                    $('<span>&nbsp;|&nbsp;</span>')
                    .appendTo(list_div);
                    
                iadded++;
                if(iadded>=limit){
                    break;                    
                }
            }//for
        }
        
        return list_div;
    },
    
    _addTagToPicked: function(recID, isinit){

        var that = this, is_picked = false;;
        
        recID = Number(recID);
        //
        if(isinit!==true && window.hWin.HEURIST4.util.findArrayIndex(recID, that.options.selection_ids)>=0){
            return;
        }
        
        
        var recordset = this._cachedRecordset;
        var record = recordset.getById(recID);
        if(record){
            is_picked = true;
            
            var label = recordset.fld(record,'tag_Text');
            var groupid = recordset.fld(record,'tag_UGrpID');
                 
                      this.recordList.find('div[data-id-header='+groupid+']').parent().show();
            var grp = this.recordList.find('div[data-id='+groupid+']').show();
            var ele = $('<div class="tagDiv2" style="display:inline-block;padding-right:4px">'
                         + '<a href="' + window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                         + '&q=tag:'+label
                         + '" target="_blank">'+label+'</a>'
            +'<span class="ui-icon ui-icon-circlesmall-close" recid="'+recID
            +'" style="display:inline-block;visibility:hidden;width:12px;vertical-align:middle"/></div>')
                         .appendTo(grp);
            //css hover doesn't work for unknown reason - todo uss css                                     
            this._on(ele, {'mouseover':function(event){ 
                $(event.target).parents('.tagDiv2').find('span').css('visibility','visible');  
            },'mouseout':function(event){ 
                $(event.target).parents('.tagDiv2').find('span').css('visibility','hidden');  
            }});
            
            //delete button
            ele.find('span').click(function(event){
                 var recID = Number($(event.target).attr('recid'));
                 var idx = window.hWin.HEURIST4.util.findArrayIndex(recID,that.options.selection_ids);
                 that.options.selection_ids.splice(idx, 1);
                 $(event.target).parents('.tagDiv2').remove();
                 that._trigger( "onselect", null, {selection:that.options.selection_ids, 
                    astext:that._selectedTagsAsString()});
            });
            
                      
            if(isinit!==true){
                that.options.selection_ids.push(recID);
                that._trigger( "onselect", null, {selection:that.options.selection_ids,
                    astext:that._selectedTagsAsString()});
            }
                         
        }
        
        var header_label = this.recordList.find('div.header-label');
        if(header_label){
            if(is_picked){
                header_label.show();
            }else{
                header_label.hide();
            }
        }
        
    },
    
    //
    // return selected tags as wokrgroupname/label
    //
    _selectedTagsAsString: function(){

        var res = [];        
        var recordset = this.selectedRecords();
        
        //add content
        var records = recordset.getRecords();
        var order = recordset.getOrder();
        var recID, label, groupid, record;

        for (idx=0;idx<order.length;idx++){

            recID = order[idx];
            if(recID && records[recID]){
                
                record = records[recID];
                label = recordset.fld(record,'tag_Text');
                groupid = recordset.fld(record,'tag_UGrpID');
                
                if(window.hWin.HAPI4.user_id()==groupid){
                    res.push(label);    
                }else{
                    var grpName = window.hWin.HAPI4.sysinfo.db_usergroups[groupid];
                    res.push(grpName+'\\'+label);
                }
            }
        }
        
        return res;
    },
    
    //
    // init input element
    //    
    _initInlineEditorControls: function(){
        
        var that = this;
        
        this.edit_replace_input = $('<input type="text" style="width:10em;" size="60"/>')
            .css({height:'auto', 'font-size':'0.9em',
                  cursor:'pointer'})
            .appendTo(this.element).hide();

        this._on(that.edit_replace_input, {'keypress': function(event){
            
                var code = (event.keyCode ? event.keyCode : event.which);
                if (code == 27) {
                    that._hideInlineEditorControls();
                    window.hWin.HEURIST4.util.stopEvent(event);
                }else 
                if (code == 13) {
                    that._renameTag( 0 );
                    window.hWin.HEURIST4.util.stopEvent(event);
                }
            
        },
        'keyup': function(event){
            
            if(that.edit_replace_input.val().length>1){
                
                var groupid = this.edit_replace_input.parent().attr('groupid');
                
                var request = {tag_Text:that.edit_replace_input.val(), tag_UGrpID:groupid };
                var recordset = this._cachedRecordset.getSubSetByRequest(request, this.options.entity.fields);
                
                var records = recordset.getRecords();
                var order = recordset.getOrder();
                var recID, label, record;
                
                if(order.length>0){
                    that.list_div.empty();  
                    
                    for (idx=0;idx<order.length;idx++){

                        recID = order[idx];
                        if(recID && window.hWin.HEURIST4.util.findArrayIndex(recID,that.options.selection_ids)<0 && records[recID]){
                            label = recordset.fld(records[recID],'tag_Text');
                            $('<div recid="'+recID+'" class="truncate">'
                            +label+'</div>').appendTo(that.list_div)
                            .click( function(event){
                                $(event.target).hide();
                                var newTagID = $(event.target).attr('recid');
                                
                                that._replaceTag(newTagID);
                            } );

                        }
                    }
                    
                    that.list_div.show()
                    .position({my:'left top', at:'left bottom', of:that.edit_replace_input})
                    //.css({'max-width':(maxw+'px')});
                    .css({'max-width':that.edit_replace_input.width()+60});
                    
                }else if(that.edit_replace_input.val().length>2){
                    that.list_div.empty();
                    $('<div><span class="ui-icon ui-icon-check" style="display:inline-block;vertical-align:bottom"/>Confirm Rename</div>')
                        .appendTo(that.list_div)
                            .click( function(event){
                                    that._renameTag( 1 );
                            });
                    that.list_div.show()
                        .position({my:'left top', at:'left bottom', of:that.edit_replace_input}).css({'max-width':'120px'});
                      
                }else{
                    that.list_div.hide();  
                }
            }else{
                that.list_div.hide();    
            }
            
        }}
        );      

        this._on($(document), {'click': function(event){
           if($(event.target).parents('.list_div').length==0) { 
                that.list_div.hide(); 
                
                if(!$(event.target).is(that.edit_replace_input) && that.edit_replace_input.is(':visible')){
                    that._hideInlineEditorControls();
                }
           };
        }});
        
            
    },
    
    //
    // 0 ask, 1 rename, 2 - final
    //
    _renameTag: function(_step){
        
        if(!window.hWin.HEURIST4.msg.checkLength(this.edit_replace_input, 'Tag', null, 3, 0)){
            return;
        }
        
        var item = this.edit_replace_input.parent();
        var newTagLabel = this.edit_replace_input.val(); 
        var tagID = item.attr('recid');
        var groupid = item.attr('groupid');
        var that = this;
        
        if(_step<2){
        
            //check duplication
            var request = {'tag_Text':'='+newTagLabel,'tag_UGrpID':groupid};

            //check duplication within group
            var subset = this._cachedRecordset.getSubSetByRequest(request, 
                                                this.options.entity.fields);

            if(subset.length()>0){
                var newTagID = Number(subset.getOrder()[0]);
                if(newTagID!=tagID){
                    that._replaceTag(newTagID);
                }
                return;
            }
        }
        
        if(_step>0){
            
            //on edit(rename) - count is not set
            var usage = item.attr('usage');
            
            var fields = {tag_ID:tagID, 'tag_Text':newTagLabel,'tag_UGrpID':groupid, 'tag_Usage':usage };
            that._currentEditID = tagID;
       
            that._saveEditAndClose( fields );
            that._hideInlineEditorControls(); 
                
        }else{
            var oldTagLabel = this._getTagLabel(tagID); 
            
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to rename tag "'+oldTagLabel+'" to "'+newTagLabel+'"?', 
                    function(){ that._renameTag(2) }, 
                    
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
            
        }
    },

    //
    //
    //
    _replaceTag: function(newTagID, unconditionally){
        
        var item = this.edit_replace_input.parent();
        var tagID = item.attr('recid');
        
        if(tagID==newTagID){
            this._hideInlineEditorControls();
            return;
        }
        
        var groupid = item.attr('groupid');
        var oldTagLabel = this._getTagLabel(tagID);
        var newTagLabel = this._getTagLabel(newTagID);
        
        var that = this;
        
        if(unconditionally===true){
            
            var request = {};
            request['a']       = 'action'; //batch action
            request['entity']  = 'usrTags';
            request['tagIDs']  = tagID;
            request['newTagID']  = newTagID;
            request['removeOld'] = 1;
            request['request_id'] = window.hWin.HEURIST4.util.random();
            
            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        that._afterDeleteEvenHandler(tagID); //remove old tag
                        
                        var usage = response.data;
                        that._currentEditID = newTagID;
                        var fields = {tag_ID:newTagID, 'tag_Text':newTagLabel,'tag_UGrpID':groupid, 'tag_Usage':usage };
                        that._afterSaveEventHandler( newTagID, fields ); //to update usage 
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
            
            this._hideInlineEditorControls();
        }else{
            
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to replace tag "'+oldTagLabel+'" with tag "'+newTagLabel+'"?', 
                    function(){ that._replaceTag(newTagID, true) }, 
                    
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
        
            
    },

    //
    //
    //
    _hideInlineEditorControls: function(){
        
        var item  = this.edit_replace_input.parent();
        if(item.hasClass('recordDiv')){
            item.find('label').show();
            if(item.attr('usage')>0){
                item.find('span.user-list-edit').css('display','inline-block');    
            }
            item.find('div.rec_action_link').show();
        }
        this.edit_replace_input.hide();
        this.list_div.hide();    
        
    },
    
    _showInlineEditorControls: function( item ){
        
        if(this.edit_replace_input.parent().hasClass('recordDiv')){
            this.edit_replace_input.parent().find('label').show();
        }
        var lbl = item.find('label');
        this.edit_replace_input.insertBefore(lbl).show();
        lbl.hide();

        item.find('span.user-list-edit').hide();
        item.find('div.rec_action_link').hide();
        
        var tagID = item.attr('recid');
        this.edit_replace_input.val(this._getTagLabel(tagID));
        this.edit_replace_input.focus();
        
    },
    
    _getTagLabel:function(tagID){
        
        var recordset = this._cachedRecordset;
        var record = recordset.getById(tagID);
        if(record){
            return recordset.fld(record,'tag_Text');
        }else{
            return '';
        }
    }
    
    ,_deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this._super(); 
        }else{
            var that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this tag?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
    }
    
});
