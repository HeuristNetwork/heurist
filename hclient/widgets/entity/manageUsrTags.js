/**
* manageDefTerms.js - main widget to manage defTerms
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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
    
    _init: function() {
        
        //this.options.layout_mode = 'basic';
        this.options.use_cache = true;
        this.options.list_mode = 'accordions';
        this.options.edit_mode = 'inline'; //online only

        this._super();

        //initially hide usage/details        
        this.editForm.parent().hide();
        this.recordList.parent().css({'width':'100%', top:0});
        this.editFormToolbar.css({'padding-right':'8em'});
        
        if(this.options.select_mode=='manager'){
        
            //expand and show tag details and usage
            this.btn_show_usage = $('<div>').css({'position':'absolute', top:3, right:3}).appendTo(this.element);
            this.btn_show_usage.css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Usage"), icons: {
                            secondary: "ui-icon-carat-1-w"}});
                    
            this._on( this.btn_show_usage, {"click": this.showHideUsage});
        
        
            this.searchForm.find('.heurist-helper1').hide();
        }else{
            this.searchForm.css('height','10em');    
            this.recordList.css('top','10em');    
        }
        
    },
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }

        // init search header
        this.searchForm.searchUsrTags(this.options);
        

        /*
        if(this.options.select_mode=='manager'){
            this.recordList.parent().css({'border-right':'lightgray 1px solid'});
        }
        */
        
        this._on( this.searchForm, {
                "searchusrtagsonresult": this.updateRecordList
                });
        this._on( this.searchForm, {
                "searchusrtagsonfilter": this.filterRecordList
                });
        this._on( this.searchForm, {
                "searchusrtagsongroupfilter": this.showHideGroups
                });

       return true;
    },
    
    //
    // update all accodions after getting data from server
    //
    updateRecordList: function( event, data ){
        this._super(event, data);
        //use this._cachedRecordset
        this._updateAccordions( this._cachedRecordset );
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
                    
                    var content = $('<div>').attr('data-id', groupID).addClass('summary-content').appendTo(acc);
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
                $('<div class="recordDiv tagDiv">' // style="float:right;"
                + '<input type="text" style="width:15ex" size="60"/>'
                + '<div class="rec_action_link" data-key="add" style="visibility:visible !important"/>'
             /*   
                + '<div title="Click to add tag" class="rec_action_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="add" >'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-plus"></span><span class="ui-button-text"></span>'
                + '</div>'*/
                +'</div>').appendTo(content);
            
        }
        
        __addAcc(window.hWin.HAPI4.currentUser['ugr_ID'], 'Personal tags');
        
        var groups = window.hWin.HAPI4.currentUser.usr_GroupsList;
        if(groups)
        for (idx in groups)
        {
            if(idx){
                var groupID = idx;
                var name = groups[idx][1];
                if(!window.hWin.HEURIST4.util.isnull(name))
                {
                    __addAcc(groupID, name);
                }
            }
        }
        
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
                usage = recordset.fld(record,'tag_Usage');

                
                var content = this.recordList.find('div[data-id="'+groupid+'"]');
                
                var item = '<div  recid="'+recID+'" class="recordDiv tagDiv"'
                    //(this.options.selection_ids.indexOf(recID)<0?'in-available':'in-selected')+'"
                            +'><label>'+ label + (usage>0?(' ('+usage+')'):'')
                            +'</label><div class="rec_action_link" data-key="delete"/>'
                            +'</div>';
                        
                if(this.options.select_mode=='select_multi'){
                    if(this.options.selection_ids.indexOf(recID)<0){
                        $(item).appendTo(content.find('.available'));    
                    }else{
                        $(item).appendTo(content.find('.picked'));    
                    }
                   
                    
                }else{
                    $(item).appendTo(content);
                }
                
            }
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
            
            //edit or select click
            var items = this.recordList.find('div.recordDiv');
            this._on(items, {'click':this.onTagItemClick});
            
            this.element.find('#input_search').focus();
    },

    onAddTag: function( event ) {
        
        var that = this;
                
                    var item = $(event.target).parents('.recordDiv');
                    var inpt = item.find('input');
                    var text = inpt.val();
                    if(!window.hWin.HEURIST4.util.isempty(text)){
                        
                        if(window.hWin.HEURIST4.msg.checkLength(inpt, 'Tag', null, 3, 0)){
                            
                            var groupid = $(event.target).parents('.summary-content').attr('data-id');
                            var fields = {'tag_Text':text,'tag_UGrpID':groupid};

                            //check duplication within group
                            var subset = this._cachedRecordset.getSubSetByRequest(fields, 
                                                        this.options.entity.fields);

                            if(subset.length()>0){
                                
                                if(this.options.select_mode=='select_multi'){
                                    //move duplication to picked
                                    this.recordList.find('div.recordDiv[recid='+subset.getOrder()[0]+']')
                                        .appendTo(this.recordList.find('div[data-id="'+groupid+'"] > .picked'));
                                    inpt.val('').focus();
                                }else{
                                /*
                                inpt.addClass( "ui-state-error" );*/
                                    window.hWin.HEURIST4.msg.showMsgFlash('<span class="ui-state-error" style="padding:10px">Duplication</span>', 2000);
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
                                
                                var idx = this.options.selection_ids.indexOf(recid);
                                var content = item.parents('.summary-content');
                                
                                if(idx<0){
                                    this.options.selection_ids.push(recid);
                                    item.appendTo( content.find('.picked') );
                                }else{
                                    this.options.selection_ids.splice(idx,1);
                                    item.appendTo( content.find('.available') );
                                }
                                
                            }else{
                                
                                this.selectedRecords([recid]);
                                if(this.options.select_mode=='manager'){
                                    this._onActionListener(event, {action:'edit'});
                                }
                            }
                        }
    },

    _afterSaveEventHandler: function( recID, fields ){
        
            var isNewRecord = (this._currentEditID<0);
        
            
            if(isNewRecord){
                this._currentEditID = null;
                this.addEditRecord(null);//clear edit form
                
                var content = this.recordList.find('div[data-id="'+fields['tag_UGrpID']+'"]');
                
                if(this.options.select_mode=='select_multi'){
                    this.options.selection_ids.push(recID);
                    content = content.find('.picked');
                }
                
                var ele = $('<div class="recordDiv tagDiv" recid="'+recID
                +'"><label>'+ fields['tag_Text']
                +'</label><div class="rec_action_link" data-key="delete"/>'
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

                
            }else{
                this.recordList.find('div[recid='+recID+'] > label').text(fields['tag_Text']
                (fields['tag_Usage']>0?(' ('+fields['tag_Usage']+')'):''));
                //reload
                var recordset = this.getRecordSet([recID]);
                this._initEditForm_step4(recordset);
            }
    },
    
    //
    //
    //   
    _afterDeleteEvenHandler: function( recID ){
        this._currentEditID = null;
        //this.addEditRecord(null);
        if(this._editing)this._editing.initEditForm(null, null); 
        this.recordList.find('div.recordDiv[recid='+recID+']').remove();
    },
    
    selectedRecords: function(value){
        
        if(window.hWin.HEURIST4.util.isnull(value)){
            this._selection = this._cachedRecordset.getSubSetByIds(this.options.selection_ids);            
        }
        
        return this._super();
    }
});
