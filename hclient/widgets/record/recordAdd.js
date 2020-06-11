/**
* recordAccess.js - apply ownership and access rights
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

$.widget( "heurist.recordAdd", $.heurist.recordAccess, {

    // default options
    options: {
        width: 800,
        height: 800,
        title:  'Add new Record',
        currentRecType: 0,
        currentRecTags: null,
        scope_types: 'none',
        get_params_only: false
    },

    _initControls:function(){
        
        if(this.options.RecTypeID>0){
            
            this.options.currentRecType =  this.options.RecTypeID;           
            this.options.currentOwner = this.options.OwnerUGrpID;           
            this.options.currentAccess = this.options.NonOwnerVisibility;           
            this.options.currentRecTags = this.options.RecTags;           
            this.options.currentAccessGroups = this.options.NonOwnerVisibilityGroups;           
        
        }else
        if(this.options.currentRecType==0){
            //take from current user preferences
            var add_rec_prefs = window.hWin.HAPI4.get_prefs('record-add-defaults');
            if(!$.isArray(add_rec_prefs) || add_rec_prefs.length<4){
                add_rec_prefs = [0, 0, 'viewable', '']; //rt, owner, access, tags  (default to Everyone)
            }
            if(add_rec_prefs.length<5){ //visibility groups
                add_rec_prefs.push('');
            }
            this.options.currentRecType =  add_rec_prefs[0];           
            this.options.currentOwner = add_rec_prefs[1];           
            this.options.currentAccess = add_rec_prefs[2];           
            this.options.currentRecTags = add_rec_prefs[3];           
            this.options.currentAccessGroups = add_rec_prefs[4];           
        }
        
        
       //add and init record type selector
       var ele = this.element.find('fieldset');  
       $('<div id="div_sel_rectype" style="padding: 0.2em; min-width: 600px;" class="input">'
            +'<div class="header" style="padding: 0 16px 0 16px;"><label for="sel_recordtype">Type of record to add:</label></div>'
            +'<select id="sel_recordtype" style="width:40ex;max-width:30em"></select>'
            
            +'<div id="btnAddRecord" style="font-size:0.9em;display:none;margin:0 30px"></div>'
            +'<div id="btnAddRecordInNewWin" style="font-size:0.9em;display:none;"></div>'
        +'</div><hr style="margin:5px"/>').prependTo( ele );
      
        this._fillSelectRecordTypes( this.options.currentRecType );
      
        if(this.options.get_params_only==false){
            
            this._on(this.element.find('#btnAddRecord').button({label: window.hWin.HR('Add Record').toUpperCase() })
                .show(), {click:this.doAction});
            this._on(this.element.find('#btnAddRecordInNewWin').button({icon:'ui-icon-extlink', 
                    label:window.hWin.HR('Add Record in New Window'), showLabel:false })
                .show(), {click:this.doAction});
        }
            //function(event){that.doAction(event)} );
        
        $('#div_more_options').show();
        $('#btn_more_options').click(function(){
            $('.add_record').show();
            $('#div_more_options').hide();
        })
        
        
        window.hWin.HEURIST4.ui.showEntityDialog('usrTags', {
                isdialog : false,
                container: $('#div_sel_tags2'),
                select_mode:'select_multi', 
                layout_mode: '<div class="recordList"/>',
                list_mode: 'compact', //special option for tags
                selection_ids: [], //already selected tags
                select_return_mode:'recordset', //ids by default
                onselect:function(event, data){
                    if(data && data.selection){
                        that.options.currentRecTags = data.astext; //data.selection;
                        _onRecordScopeChange();
                    }
                }
        });
        
        
        return this._super();
    },

    //    
    //
    //
    _getActionButtons: function(){
        var res = this._super();
        res[1].text = window.hWin.HR(this.options.get_params_only?'Get Parameters':'Add Record');
        return res;
    },    

    //
    // extended
    //
    getSelectedParameters: function( showWarning ){
        
        var rtSelect = this.element.find('#sel_recordtype');
        if(rtSelect.val()>0){
            if(this._super( showWarning )){
                this.options.currentRecType = rtSelect.val();
                return true;
            }
        }else if ( showWarning ) {
            window.hWin.HEURIST4.msg.showMsgFlash('Select record type for record to be added');            
        }
        return false;
    },
    
    //
    //
    //
    doAction: function(){
        
        if (!this.getSelectedParameters(true))  return;
        
        var new_record_params = {
                'RecTypeID': this.options.currentRecType,
                'OwnerUGrpID': this.options.currentOwner,
                'NonOwnerVisibility': this.options.currentAccess,
                'NonOwnerVisibilityGroups':this.options.currentAccessGroups,
        };
                
        if(this.options.get_params_only==true){
            //return values as context
            new_record_params.RecTags = this.options.currentRecTags;
            this._context_on_close =  new_record_params;
        }else{
            
            var add_rec_prefs = [this.options.currentRecType, this.options.currentOwner, this.options.currentAccess, 
                        this.options.currentRecTags, this.options.currentAccessGroups];    

            window.hWin.HAPI4.save_pref('record-add-defaults', add_rec_prefs);        
                
            window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE, {origin:'recordAdd'});
            
            if(event && $(event.target).parent('div').attr('id')=='btnAddRecordInNewWin'){
                var url = $('#txt_add_link').val();
                window.open(url, '_blank');
            }else{
                window.hWin.HEURIST4.ui.openRecordEdit(-1, null, {new_record_params:new_record_params});    
            }
        }
               
        this.closeDialog(); 
    },

    //
    // record type selector for change record type action
    // 
    _fillSelectRecordTypes: function( value ) {
        var rtSelect = this.element.find('#sel_recordtype');
        rtSelect.empty();
        
        var ele = window.hWin.HEURIST4.ui.createRectypeSelect( rtSelect.get(0), null, window.hWin.HR('select record type'), false );
        
        var that = this;
        
        ele.hSelect({change: function(event, data){
            var selval = data.item.value;
            rtSelect.val(selval);
            that._onRecordScopeChange();
        }});
        
        $(ele).val(value).hSelect("refresh"); 
        
        return ele;
    },
    
    
    //
    // overwritten
    //
    _onRecordScopeChange: function () 
    {
        
        var isdisabled = !this.getSelectedParameters( false );
        
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('#btnDoAction'), isdisabled );
        window.hWin.HEURIST4.util.setDisabled( this.element.find('#btnAddRecordInNewWin'), isdisabled);
        window.hWin.HEURIST4.util.setDisabled( this.element.find('#btnAddRecord'), isdisabled);
        
        var url = '';
        
        if(!isdisabled){
            
            url = window.hWin.HAPI4.baseURL+'hclient/framecontent/recordEdit.php?db='+window.hWin.HAPI4.database
            +'&rec_rectype=' + this.options.currentRecType
            +'&rec_owner='+this.options.currentOwner
            +'&rec_visibility='+this.options.currentAccess;
            
            if( !window.hWin.HEURIST4.util.isempty( this.options.currentAccessGroups )){
                url = url + '&visgroups='+this.options.currentAccessGroups;    
            }
            
            if( !window.hWin.HEURIST4.util.isempty( this.options.currentRecTags)){
                if($.isArray(this.options.currentRecTags) && this.options.currentRecTags.length>0){
                    this.options.currentRecTags = this.options.currentRecTags.join(',');
                }
                //encodeuricomponent
                url = url + '&tag='+this.options.currentRecTags;    
            }
            
        }
        $('#txt_add_link').val(url);
        
        
    }
    
  
});
