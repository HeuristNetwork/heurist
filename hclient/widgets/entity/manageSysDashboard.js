/**
* manageSysDashboard.js - main widget to manage sysDashboard
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


$.widget( "heurist.manageSysDashboard", $.heurist.manageEntity, {

    _entityName:'sysDashboard',
    defaultPrefs: {viewmode:'thumbs3', showonstartup:1},
    
    //
    //
    //
    _init: function() {
        
        this.options.coverall_on_save = true;
        this.options.layout_mode = 'short';
        this.options.use_cache = false;
        //this.options.select_return_mode = 'recordset';
        this.options.edit_need_load_fullrecord = true;
        this.options.edit_height = 640;
        this.options.width = 950;

        this.options.height = (isNaN(this.options.height) || this.options.height<815)?900:this.options.height;                    
        
        //window.hWin.
        var fit_to_layout = $('div[layout_id="FAP2"]');
        if(fit_to_layout.length>0){
            this.options.position = {my: "left top", at:'left top', of:fit_to_layout};
            //var pos = fit_to_layout.offset();
            //{ my: "left top", at:'left+'+pos.left+' top+'+pos.top};;
            this.options.width = fit_to_layout.width();
            this.options.height = fit_to_layout.height();
        }
        
        this.options.isViewMode = true;
    
        this._super();
    },
    
/* @todo - add this selector to search form
        this.select_order = $( "<select><option value='1'>"+
            window.hWin.HR("by name")+"</option><option value='5'>"+
            window.hWin.HR("by size")+"</option><option value='6'>"+
            window.hWin.HR("by usage")+"</option><option value='7'>"+
            window.hWin.HR("by date")+"</option><option value='8'>"+
            window.hWin.HR("marked")+"</option></select>", {'width':'80px'} )
*/    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }

        // init search header
        this.searchForm.searchSysDashboard(this.options).addClass("ui-heurist-bg-light");
        
        /*
        var iheight = 7.4;
        this.searchForm.css({'height':iheight+'em', padding:'10px', 'min-width': '730px'});
        this.recordList.css({'top':iheight+2+'em'});
        */
        //init viewer 
        var that = this;
        
        var prefs = this.getUiPreferences();

        this._on( this.searchForm, {
                "searchsysdashboardonresult": this.updateRecordList,
                "searchsysdashboardonadd": function() { this.addEditRecord(-1); },
                "searchsysdashboardonorder": function() { this.saveNewOrder() },
                "searchsysdashboardoninit": function(){
                    
        this.show_longer_description = this.searchForm.find('#show_longer_description');

        this.show_longer_description
                .attr('checked',(prefs.viewmode=='thumbs3'))
            .change(function(){
                that.recordList.resultList('option','view_mode',
                    that.show_longer_description.is(':checked')?'thumbs3':'thumbs');
                that.saveUiPreferences();
        });
        
        this._setMode(true)
        
        this.searchForm.find('#edit_dashboard').click(function(){
            that._setMode(false);
        });
        
        this.show_on_startup.attr('checked', (prefs.showonstartup==1))
            .change(function(){
              that.saveUiPreferences();
        });
                    
                    
                }
                });
                
        //this.recordList.resultList('setHeaderText','<h3>Dashboard</h3><span class="heurist-helper1">A shortcuts to commonly used functions for this particuar database (al functions are available at any time via the menues and filter fields)</span>',
        //    {height:'4em'});
        this.recordList.resultList('option','multiselect', false);
        this.recordList.resultList('option','select_mode', 'select_single');
        
        //load all menu commands
        //note!!! it won't works if structure for this entiy will be more complex than plain fields list
        var menu_entries;
        var app = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('mainMenu');
        if(app && app.widget){
            menu_entries = $(app.widget).mainMenu('menuGetAllActions');
        }
        
//console.log( menu_entries );        
        
        var fields = that.options.entity.fields;
        for (idx=0; idx<fields.length; idx++){
            if(fields[idx]['dtID']=='dsh_CommandToRun'){
                that.options.entity.fields[idx]['dtFields']['rst_FieldConfig'] = menu_entries;
                break;
            }
        }
        
        return true;
    },

    //
    // add specific buttons to bottom dialog bar
    //        
    _initDialog: function(){
        
        var that = this;
            //restore from preferences    
            /* @todo
            this.getUiPreferences();
            this.options['width']  = this.usrPreferences['width'];
            this.options['height'] = this.usrPreferences['height'];
            */
            //take status edit/view from preferences? or always in view mode by default
          
            this.options.btn_array = [{id:'btn_set_mode',
                        text: window.hWin.HR(that.options.isViewMode?'Edit':'View'),
                        click: function( event ) { 
                            that._setMode(!that.options.isViewMode);
                        }}];
                        
            this._super();
            
            this.show_on_startup = $('<input id="show_on_startup" type="checkbox">')
                .appendTo( $('<label style="float:right;padding:12px">Show dashboard on startup&nbsp;</label>')
                    .appendTo(this._as_dialog.parent().find('.ui-dialog-buttonpane')) );            
            
    },
    
    //
    //  set view or edit mode
    // newmode = true - view mode
    //
    _setMode: function(newmode){
        
        if(newmode){
             // view mode
             //this.searchForm.hide();
             //this.recordList.css({'top': 0});
            this.searchForm.css({'height': '6.4em'});
            this.recordList.css({'top': '6.8em'});
            
            this.searchForm.find('#view_mode').show();
            this.searchForm.find('#edit_mode').hide();
            this._as_dialog.parent().find('.ui-dialog-titlebar').hide();
            
            this.recordList.resultList('option', 'sortable', false);
            this.recordList.resultList('option','view_mode',
                this.show_longer_description.is(':checked')?'thumbs3':'thumbs');
        }else{
            // edit mode
            
            //this.searchForm.show();
            this.searchForm.css({'height': '2.4em'});
            this.recordList.css({'top': '2.8em'});

            this.searchForm.find('#view_mode').hide();
            this.searchForm.find('#edit_mode').show();
            this._as_dialog.parent().find('.ui-dialog-titlebar').show();
            
            this.recordList.resultList('option','view_mode','list');
            
            var that = this;
            this.recordList.resultList('option', 'sortable', true);
            this.recordList.resultList('option', 'onSortStop', function(){
                that.searchForm.find('#btn_apply_order').css({'display':'inline-block'});
            });
            
        }
        /*
        this.recordList.resultList('option','show_counter', !newmode);
        this.recordList.resultList('option','show_inner_header', newmode);
        */
        this.recordList.resultList('option','show_toolbar', !newmode);
        
        if(this.options.isViewMode !== newmode){
            this.options.isViewMode = newmode;
            this.searchForm.searchSysDashboard('option','isViewMode',newmode);
            this.searchForm.searchSysDashboard('startSearch');
        }
        
        
        this._as_dialog.parent().find('#btn_set_mode')
            .button('option','label', window.hWin.HR(this.options.isViewMode?'Edit':'View'));
        
    },

    //
    // force refresh after save (note: in case cached entity it auto happens in parent - manageEntity)
    //
    _afterSaveEventHandler: function( recID, fieldvalues ){
        this._super( recID, fieldvalues );
        
        this.getRecordSet().setRecord(recID, fieldvalues);    
        this.recordList.resultList('refreshPage');  
    },
        
    //----------------------
    //
    //  overwrite standard render for resultList
    //
    _recordListItemRenderer:function(recordset, record){
        
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, col_width){
            swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = ' style="width:'+col_width+'"';
            }
            return '<div class="item" '+swidth+'>'+window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname))+'</div>';
        }
        
        //ugr_ID,ugr_Type,ugr_Name,ugr_Description, ugr_eMail,ugr_FirstName,ugr_LastName,ugr_Enabled,ugl_Role
        
        var recID   = fld('dsh_ID');
        
        var rectype = fld('ulf_ExternalFileReference')?'external':'local';
        //var isEnabled = (fld('ugr_Enabled')=='y');
        
        var recTitle = fld2('dsh_Label','auto');//,'20em'
        var recTitleHint = fld('dsh_Description');
        var recOpacity = (fld('dsh_Enabled')=='y')?1:0.3;
        
        var rtIcon = window.hWin.HAPI4.getImageUrl(this._entityName, 0, 'icon');
        //var rtThumb = window.hWin.HAPI4.getImageUrl(this._entityName, 0, 'thumb');
        var recThumb = window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'thumb', 2, this.options.database);
        
        var html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+recThumb+'&quot;);opacity:'+recOpacity+'">'
        +'</div>';

        var html = '<div class="recordDiv landscape" id="rd'+recID+'" recid="'+recID+'">'
        + html_thumb
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        + '<div class="recordIcons" style="min-width:16px;">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '" style="background-image: url(&quot;'+rtIcon+'&quot;);opacity:'+recOpacity+'">'
        + '</div>'
        + '<div class="recordTitle recordTitle2">'  // style="right:180px"
        +     recTitle
        + '</div>'
        + '<div class="recordDescription">'
        +     recTitleHint
        + '</div>';

        
        // add edit/remove action buttons
        if(!this.options.isViewMode && this.options.select_mode=='manager' && this.options.edit_mode=='popup'){
            html = html 
                + '<div title="Click to edit entry" class="rec_edit_link_ext logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                + '</div>&nbsp;&nbsp;'
                + '<div title="Click to delete entry" class="rec_view_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
                + '</div>';
        }
        
        /*+ '<div title="Click to view record (opens in popup)" '
        + '   class="rec_view_link ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" '
        + '   role="button" aria-disabled="false">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-comment"></span><span class="ui-button-text"></span>'
        + '</div>'*/
        html = html + '</div>';

        return html;
        
    },

    //
    // get and set selected records - RecordSet
    //    
    selectedRecords: function(value){
        
        this._super( value );
        
        if(!window.hWin.HEURIST4.util.isnull(value)){
            var record = this._selection.getFirstRecord();
            if(this.options.isViewMode){
                var command = this._selection.fld(record, 'dsh_CommandToRun');
                var params = this._selection.fld(record, 'dsh_Parameters');
    //console.log(command+'  '+params);

                if(command.indexOf('menu-')==0){ //main menu action

                    var app = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('mainMenu');
                    if(app && app.widget){
                        $(app.widget).mainMenu('menuActionById', command);
                    }

                }

            }else{
                var recID = this._selection.fld(record, 'dsh_ID');
                this.addEditRecord(recID);
            }
        }
    },
    
    //
    // apply new order
    //
    saveNewOrder: function(){
        
            this.searchForm.find('#btn_apply_order').hide();    
        
            var recordset = this.recordList.resultList('getRecordSet');
            //assign new value for dtg_Order and save on server side
            var rec_order = recordset.getOrder();
            var idx = 0, len = rec_order.length;
            var fields = [];
            for(; (idx<len); idx++) {
                var record = recordset.getById(rec_order[idx]);
                var oldval = recordset.fld(record, 'dsh_Order');
                var newval = String(idx+1).lpad(0,3);
                if(oldval!=newval){
                    recordset.setFld(record, 'dsh_Order', newval);        
                    fields.push({"dsh_ID":rec_order[idx], "dsh_Order":newval});
                }
            }
            if(fields.length>0){

                var request = {
                    'a'          : 'save',
                    'entity'     : this._entityName,
                    'request_id' : window.hWin.HEURIST4.util.random(),
                    'fields'     : fields                     
                };

                var that = this;                                                
                //that.loadanimation(true);
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            window.hWin.HEURIST4.msg.showMsgFlash('Order saved',300);
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                });

            }
    },
    
    
    //
    getUiPreferences:function(){
        this.usrPreferences = window.hWin.HAPI4.get_prefs_def('prefs_'+this._entityName, this.defaultPrefs);
        return this.usrPreferences;
    },
    
    //    
    saveUiPreferences:function(){
        var params = 
            {viewmode: this.show_longer_description.is(':checked')?'thumbs3':'thumbs',
             showonstartup: this.show_on_startup.is(':checked')?1:0 };
        window.hWin.HAPI4.save_pref('prefs_'+this._entityName, params);     
    },
    

});