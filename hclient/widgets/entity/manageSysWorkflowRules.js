/**
* manageSysWorkflowRules.js - main widget to Workflow Stages Rules
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

//
// there is no search, select mode for reminders - only edit
//
$.widget( "heurist.manageSysWorkflowRules", $.heurist.manageEntity, {
   
    _entityName:'sysWorkflowRules',
    
    //keep to refresh after modifications
    _keepRequest:null,
    
    _init: function() {
        
        if(!this.options.default_palette_class){
            this.options.default_palette_class = 'ui-heurist-design';    
        }
        
        this.options.innerTitle = false;
        this.options.use_cache = false;
        
        if(this.options.edit_mode=='editonly'){
            this.options.edit_mode = 'editonly';
            this.options.select_mode = 'manager';
            this.options.layout_mode = 'editonly';
            this.options.width = 790;
            if(!(this.options.height>0)) this.options.height = 600;
            this.options.beforeClose = function(){}; //to supress default warning
        }else{
            if(this.options.select_mode!='manager'){
                    this.options.edit_mode = 'popup'; 
            }
            this.options.list_header = true; //show header for resultList
        }

        this._super();
        
        
        if(this.options.isFrontUI){
            
            this.searchForm.css({padding:'10px 5px 0 10px'});
            
            //window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, {'background-color':'#fff', opacity:1});   
        
            if(this.options.select_mode=='manager'){ //adjust table widths
                var that = this;
                window.hWin.HAPI4.addEventListener(this, window.hWin.HAPI4.Event.ON_WINDOW_RESIZE, 
                    function(){
                        if(that.recordList && that.recordList.resultList('instance')){
                            that.recordList.resultList('applyViewMode','list', true);
                            that.recordList.resultList('refreshPage');
                        }
                    });
            }    
        }        
        
    },
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }
      
        if(this.options.edit_mode=='editonly'){
            //load rules
            if(this.options.swf_ID>0){
                    var request = {};
                    request['swf_ID']  = this.options.rem_RecID;
                    request['a']          = 'search'; //action
                    request['entity']     = this.options.entity.entityName;
                    request['details']    = 'full';
                    request['request_id'] = window.hWin.HEURIST4.util.random();
                    
                    var that = this;                                                
                    
                    window.hWin.HAPI4.EntityMgr.doRequest(request, 
                        function(response){
                            if(response.status == window.hWin.ResponseStatus.OK){
                                var recset = new hRecordSet(response.data);
                                if(recset.length()>0){
                                    that.updateRecordList(null, {recordset:recset});
                                    that.addEditRecord( recset.getOrder()[0] );
                                }
                                else {
                                    //nothing found - add new bookmark
                                    that.addEditRecord(-1);
                                }                            
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                                that.closeEditDialog();
                            }
                        });        
                        
            }else{
                this.addEditRecord(-1);
            }
        }else{
            this.searchForm.searchSysWorkflowRules(this.options);
            
            
            var iheight = 6;
            this.searchForm.css({'height':iheight+'em',padding:'10px'});
            this.recordList.css({'top':iheight+0.5+'em'});
            
            this.recordList.resultList('option','show_toolbar',false);
            this.recordList.resultList('option','view_mode','list');

            
            this.recordList.find('.div-result-list-content').css({'display':'table','width':'99%'});
            
            this._on( this.searchForm, {
                "searchsysworkflowrulesonresult": this.updateRecordList,
                "searchsysworkflowrulesonadd": function() {
                        this._onActionListener(null, 'add');
                }
            });
            
        }

        return true;
    },
    
//----------------------------------------------------------------------------------    
    _getValidatedValues: function(){
        
        var fields = this._super();
        
        if(fields!=null){
            
            if(fields['swf_Visibility']=='null'){
                    fields['swf_SetVisibility'] = '';
            }else if(fields['swf_Visibility']!='hidden'){ //viewable or public
                    fields['swf_SetVisibility'] = fields['swf_Visibility'];    
            }else if(fields['swf_SetVisibility']==''){ //hidden
                    fields['swf_SetVisibility'] = 'hidden';
            }
            
            if(fields['swf_Ownership']=='null'){
                    fields['swf_SetOwnership'] = '';
            }else if(fields['swf_Ownership']=='0'){ //viewable or public
                    fields['swf_SetOwnership'] = '0';    
            }
            
        }
        
        return fields;
    },

    //
    //
    //
    _saveEditAndClose: function( fields, afteraction ){

        //assign record id    
        if(this.options.edit_mode=='editonly' && this.options.swf_ID>0){
            var ele2 = this._editing.getFieldByName('swf_ID');
            ele2.editing_input('setValue', this.options.swf_ID );
        }
                                                                                  
        this._super();
    },
    
    _afterSaveEventHandler: function( recID, fieldvalues ){
        this._super( recID, fieldvalues );
        
        if(this.options.edit_mode=='editonly'){
            this.closeDialog(true);
        }else{
            //this.getRecordSet().setRecord(recID, fieldvalues);    
            //this.recordList.resultList('refreshPage'); 
            
            this.searchForm.searchSysWorkflowRules('refreshSelectors', fieldvalues['swf_RecTypeID']); 
        }
    },

    _deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this._super(); 
        }else{
            var that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this rule?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
    },
    
    _afterInitEditForm: function(){

        this._super();
        
        var that = this;
        
        if(this.options.edit_mode=='editonly' || this.options.edit_mode=='popup'){

            function __onChangeVisType(is_first){ 
                var ele = that._editing.getFieldByName('swf_Visibility');
                var ele1 = that._editing.getFieldByName('swf_SetVisibility');
                
                var res = ele.editing_input('getValues'); 
                if(res[0]=='hidden'){
                        ele1.show();
                }else{
                        ele1.hide();
                }
                
                if(is_first!==true){
                    ele.editing_input('isChanged', true);
                    that.onEditFormChange();  
                } 
            }

            var ele = that._editing.getFieldByName('swf_Visibility');
            var ele1 = that._editing.getFieldByName('swf_SetVisibility');        
            var res = ele1.editing_input('getValues'); 
            
            //assign value to swf_Visibility
            if(res[0]=='viewable' || res[0]=='public'){
                ele.editing_input('setValue', res[0]);
            }else if(res[0]==''){
                ele.editing_input('setValue', 'null');
            }else{
                ele.editing_input('setValue', 'hidden');
            }
            if(res[0]=='viewable' || res[0]=='public' || res[0]=='hidden'){
                ele1.editing_input('setValue', '');    
            }
            
            ele.editing_input('isChanged', false);
            ele1.editing_input('isChanged', false);
            ele.editing_input('option', 'change', __onChangeVisType);
            __onChangeVisType(true);
            
            //
            //ownership     
            //
            function __onChangeOwnerType(is_first){ 
                var ele = that._editing.getFieldByName('swf_Ownership');
                var ele1 = that._editing.getFieldByName('swf_SetOwnership');
                
                var res = ele.editing_input('getValues'); 
                if(res[0]=='group'){
                        ele1.show();
                        if(is_first!==true) ele1.find('.entity_selector').click();
                }else{
                        ele1.hide();
                }
                
                if(is_first!==true){
                    ele.editing_input('isChanged', true);
                    that.onEditFormChange();  
                } 
            }

            ele = that._editing.getFieldByName('swf_Ownership');
            ele1 = that._editing.getFieldByName('swf_SetOwnership');        
            res = ele1.editing_input('getValues'); 
            
            //assign value to swf_Ownership
            if(res[0]==''){
                ele.editing_input('setValue', 'null');
            }else if(res[0]=='0'){
                ele.editing_input('setValue', '0');
            }else{
                ele.editing_input('setValue', 'group');
            }
            if(res[0]=='' || res[0]=='0'){
                ele1.editing_input('setValue', '');    
            }
            ele.editing_input('isChanged', false);
            ele1.editing_input('isChanged', false);
            ele.editing_input('option', 'change', __onChangeOwnerType);
            __onChangeOwnerType(true);
            
        }
        
/*    
        var that = this;
        var ele = this._editing.getFieldByName('rem_IsPeriodic');
        
        if(this.options.edit_mode=='editonly'){
        
            //reminder
            var val = this._getField('rem_StartDate');
            
            var isManual = window.hWin.HEURIST4.util.isempty(val) || val=='0000-00-00';
            
            function __onChangeType(){ 
                var ele1 = that._editing.getFieldByName('rem_Freq');
                var ele2 = that._editing.getFieldByName('rem_StartDate');
                
                var btn_save;
                if(that._toolbar){
                    btn_save = that._toolbar.find('#btnRecSave');
                }
                
                var res = ele.editing_input('getValues'); 
                if(res[0]=='now'){
                        ele2.editing_input('setValue', '');
                        ele1.hide();
                        ele2.hide();
                        
                        if(btn_save) btn_save.button('option','label','Send');
                }else{
                        ele1.show();
                        ele2.show();
                        
                        if(btn_save) btn_save.button('option','label','Save');
                }
            }
            
            ele.editing_input('option', 'change', __onChangeType);
            ele.editing_input('setValue', isManual?'now':'later');
            __onChangeType();
        
        }else{
            ele.editing_input('option','readonly',true);
            ele.editing_input('setValue', 'later');
            ele.hide();
        }
        
        var ele1 = this._editing.getFieldByName('rem_ToWorkgroupID');
        var ele2 = this._editing.getFieldByName('rem_ToUserID');
        var ele3 = this._editing.getFieldByName('rem_ToEmail');
        
        
        function __onChange2( ){
//console.log($(this.element).editing_input('option','dtID'));
           
           var res = $(this.element).editing_input('getValues')
           if(res[0]!=''){
               var dtID = $(this.element).editing_input('option','dtID');
               if(dtID!='rem_ToWorkgroupID') ele1.editing_input('setValue', '');
               if(dtID!='rem_ToUserID') ele2.editing_input('setValue', '');
               if(dtID!='rem_ToEmail') ele3.editing_input('setValue', '');
           }
        }
    
        ele1.editing_input('option', 'change', __onChange2);
        ele2.editing_input('option', 'change', __onChange2);
        ele3.editing_input('option', 'change', __onChange2);
*/
    
    },

    //
    // header for resultList
    //     
    _recordListHeaderRenderer:function(){
        
        function __cell(colname, width){
          //return '<div style="display:table-cell;width:'+width+'ex">'+colname+'</div>';            
          return '<div style="width:'+width+'ex">'+colname+'</div>';            
        }
        
        //return '<div style="display:table;height:2em;width:99%;font-size:0.9em">'
        return __cell('Stage',24)+__cell('Restricted to',24)+__cell('Ownership',24)
                    +__cell('Visibility',25)+__cell('Notification',25);//+__cell('',12);
                    
    },
    
    //----------------------
    //
    //  overwrite standard render for resultList
    //
    _recordListItemRenderer:function(recordset, record){
        
        function fld(fldname, def){
            
            var val = recordset.fld(record, fldname);
            if(val){
                if(fldname=='swf_Stage'){
                    val = $Db.trm(val,'trm_Label');
                }else{
                    if(fldname=='swf_SetVisibility' && (val=='viewable' || val=='public' || val=='hidden')){
                        return val;
                    }
                    
                    var names = [];
                    $.each(val.split(','), function(i,item){
                        names.push(window.hWin.HEURIST4.util.htmlEscape(window.hWin.HAPI4.sysinfo.db_usergroups[item]));        
                    });
                    return names.join('<br>');    
                }
            }else{
                val = def;
            }
            return window.hWin.HEURIST4.util.htmlEscape(val);
        }
        function fld2(val, col_width){
            swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = 'width:'+col_width;
            }
            return '<div class="truncate" style="display:inline-block;'+swidth+'">'
                    +val+'</div>';
        }
        
        //rem_ID,rem_RecID,rem_OwnerUGrpID,rem_ToWorkgroupID,rem_ToUserID,rem_ToEmail,rem_Message,rem_StartDate,rem_Freq,rem_RecTitle
        //rem_ToWorkgroupName
        //rem_ToUserName        
        
        
        var recID   = recordset.fld(record,'swf_ID');
        
        var s_restrict = fld('swf_StageRestrictedTo','no restrictions'),
            s_ownership = fld('swf_SetOwnership','no changes'),
            s_visibility = fld('swf_SetVisibility','no changes'),
            s_email = fld('swf_SendEmail','no notification'),
            s_stage = fld('swf_Stage');
        
        
        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'
                + fld2(s_stage,'25ex')
                + fld2(s_restrict,'25ex')
                + fld2(s_ownership,'25ex')
                + fld2(s_visibility,'25ex')
                + fld2(s_email,'25ex');
        
        // add edit/remove action buttons
        if(this.options.select_mode=='manager' && this.options.edit_mode=='popup'){
            html = html 
                + '<div class="logged-in-only" style="width:60px;display:inline-block;">' //rec_view_link 
                + '<div title="Click to edit rule" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit"  style="height:16px">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                + '</div>'
                +'<div title="Click to delete rule" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete"  style="height:16px">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
                + '</div></div>';
        }
        //<div style="float:right"></div>' + '<div style="float:right"></div>
        
        html = html + '</div>';

        return html;
        
    }    
    
});
