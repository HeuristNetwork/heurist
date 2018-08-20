/**
* recordAccess.js - apply ownership and access rights
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2018 University of Sydney
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

$.widget( "heurist.recordAccess", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 520,
        width:  800,
        modal:  true,
        init_scope: 'selected',
        title:  'Change Record Access and Ownership',
        currentOwner: 0,
        currentAccess: null,
        currentAccessGroups: null,
        
        htmlContent: 'recordAccess.html',
        helpContent: 'recordAccess.html' //in context_help folder
    },

    _initControls:function(){
        
        /*
        var fieldSelect = this.element.find('#sel_Ownership');
        window.hWin.HEURIST4.ui.createUserGroupsSelect(fieldSelect[0], null,  //take groups of current user
                [{key:0, title:'Everyone (no restriction)'}, 
                 {key:window.hWin.HAPI4.currentUser['ugr_ID'], title:window.hWin.HAPI4.currentUser['ugr_FullName']}]);
        */         

        //define group selector for edit
        var ele = $('#sel_OwnerGroups');
        if(!ele.editing_input('instance')){
            ele.empty();
            this._createGroupSelectorElement('sel_OwnerGroups', null);    
        }
        ele.hide();    
        
        
        //define group selector for access
        var ele = $('#sel_AccessGroups');
        if(!ele.editing_input('instance')){
            ele.empty();
            this._createGroupSelectorElement('sel_AccessGroups', this.options.currentAccessGroups);    
        }
        ele.hide();    
        
        if(!window.hWin.HEURIST4.util.isempty(this.options.currentOwner)){
            
            if(this.options.currentOwner==0){
                $('#rb_Owner-everyone').prop('checked', true);    
            }else if(this.options.currentOwner == window.hWin.HAPI4.currentUser['ugr_ID']){
                $('#rb_Owner-user').prop('checked', true);    
            }else{
                $('#rb_Owner-group').prop('checked', true);    
                $('#sel_OwnerGroups').show().editing_input('setValue', [this.options.currentOwner]);
            }
        }
        
        if(this.options.currentAccess){
            //fieldSelect.val(this.options.currentOwner);
            
            if(this.options.currentAccess=='viewable' && this.options.currentAccessGroups){
                $('#rb_Access-viewable-group').prop('checked', true);
                $('#sel_AccessGroups').show();
            }else{
                $('#rb_Access-'+this.options.currentAccess).prop('checked', true);
                
            }
        }
        
        var that = this;


        $('input[name="rb_Owner"]').change(function(){
            
            if($('#rb_Owner-group').prop('checked')){
                $('#sel_OwnerGroups').show();
            }else{
                $('#sel_OwnerGroups').hide();
            }
            
            that._onRecordScopeChange();
            
        });
        
        $('input[name="rb_Access"]').change(function(){
            
            if($('#rb_Access-viewable-group').prop('checked')){
                $('#sel_AccessGroups').show();
            }else{
                $('#sel_AccessGroups').hide();
            }
           
            that._onRecordScopeChange();
        });
        
        if(this.options.scope_types=='none'){
            this._onRecordScopeChange();    
        }
        
        return this._super();
    },

    //    
    //
    //
    _getActionButtons: function(){
        var res = this._super();
        res[1].text = window.hWin.HR('Apply');
        return res;
    },    

    //
    //
    //
    _createGroupSelectorElement: function(input_id, init_value){
        
        if(window.hWin.HEURIST4.util.isnull(init_value)) init_value = '';

        var ed_options = {
            recID: -1,
            dtID: input_id, //'group_selector',
            //rectypeID: rectypeID,
            //rectypes: window.hWin.HEURIST4.rectypes,
            values: [init_value],
            readonly: false,
            showclear_button: true,
            dtFields:{
                dty_Type:"resource",
                rst_DisplayName:'Select Groups:', rst_DisplayHelpText:'',
                rst_FieldConfig: {entity:'sysGroups', csv:true}
            }
            //change:_onAddRecordChange
        };

        /*
        $("<div>").attr('id','group_selector').editing_input(ed_options).appendTo($.find(input_id));
        var ele = $('#group_selector');
        ele.css('display','table');
        ele.find('.header').css({'min-width':'150px','text-align':'right'})
        */
        
        var ele = $('#'+input_id);
        ele.editing_input(ed_options);
        ele.find('.editint-inout-repeat-button').hide();
        ele.find('.header').css('padding-right','16px');
    },
    
    //
    //
    //
    doAction: function(){

            var scope_val = this.selectRecordScope.val();
            var ownership = this.element.find('input[type="radio"][name="rb_Owner"]:checked').val();
                        //this.element.find('#sel_Ownership').val();
            if(ownership=='everyone') {
                ownership = 0;
            } else if(ownership=='user') {
                ownership = window.hWin.HAPI4.currentUser['ugr_ID'];
            } else {
                ownership = $('#sel_OwnerGroups').editing_input('getValues');
                if(ownership && ownership.length>0){
                    ownership = ownership[0];
                }
                if(!ownership){
                    window.hWin.HEURIST4.msg.showMsgFlash('Select group with edit permission');
                    return;
                }
            }        
            
            var visibility = this.element.find('input[type="radio"][name="rb_Access"]:checked').val();
            var visibility_groups = '';
            
            if (window.hWin.HEURIST4.util.isempty(scope_val) 
                && window.hWin.HEURIST4.util.isempty(ownership) 
                && window.hWin.HEURIST4.util.isempty(visibility))  return;
        
            if(visibility=='viewable' && $('#sel_AccessGroups').editing_input('instance')){
                var sel = $('#sel_AccessGroups').editing_input('getValues');
                if(sel && sel.length>0){
                    visibility_groups = sel[0];
                }
            }
        
        
            if(this.options.scope_types=='none'){
                //return values as context
                this._context_on_close = {
                'OwnerUGrpID': ownership,
                'NonOwnerVisibility': visibility,
                'NonOwnerVisibilityGroups':visibility_groups,
                };
                
                this.closeDialog();
                return;
            }
            
            var scope = [], 
            rec_RecTypeID = 0;
            
            if(scope_val == 'selected'){
                scope = this._currentRecordsetSelIds;
            }else { //(scope_val == 'current'
                scope = this._currentRecordset.getIds();
                if(scope_val  >0 ){
                    rec_RecTypeID = scope_val;
                }   
            }
            
/*            
console.log(visibility_groups);            
console.log(ownership);
*/
            
            var request = {
                'request_id' : window.hWin.HEURIST4.util.random(),
                'ids'  : scope,
                'OwnerUGrpID': ownership,
                'NonOwnerVisibility': visibility,
                'NonOwnerVisibilityGroups':visibility_groups,
                };
                
            if(rec_RecTypeID>0){
                request['rec_RecTypeID'] = rec_RecTypeID;
            }
                
                var that = this;                                                
                
                window.hWin.HAPI4.RecordMgr.access(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){

                            that._context_on_close = (response.data.updated>0);
                            
                            that.closeDialog();
                            
                            var msg = 'Processed : '+response.data.processed + ' record'
                                + (response.data.processed>1?'s':'') +'. Updated: '
                                + response.data.updated  + ' record'
                                + (response.data.updated>1?'s':'');
                           if(response.data.noaccess>0){
                               msg += ('<br><br>No enough rights for '+response.data.noaccess+
                                        ' record' + (response.data.noaccess>1?'s':''));
                           }     
                            
                            window.hWin.HEURIST4.msg.showMsgFlash(msg, 2000);
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
        
    },

    //
    // overwritten
    //
    _onRecordScopeChange: function () 
    {
        var scope_val = (this.options.scope_types=='none' || !this.selectRecordScope)?'1':this.selectRecordScope.val();
        var ownership = this.element.find('input[type="radio"][name="rb_Owner"]:checked').val();
        var visibility = this.element.find('input[type="radio"][name="rb_Access"]:checked').val();
            
        var isdisabled = (!(scope_val!='' && ownership && visibility));

        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('#btnDoAction'), isdisabled );
    },
  
});

