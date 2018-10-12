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
    _isViewMode: true, 
    
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

        //for selection mode set some options
        if(this.options.select_mode!='manager'){
            this.options.width = (isNaN(this.options.width) || this.options.width<815)?900:this.options.width;                    
            //this.options.edit_mode = 'none'
        }
        this.options.height = (isNaN(this.options.height) || this.options.height<815)?900:this.options.height;                    
    
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
        
        //take from preferences? or always inited as view?
        this.options.isViewMode = this._isViewMode;

        // init search header
        this.searchForm.searchSysDashboard(this.options);
        
        this._setMode(this._isViewMode)
        
        /*
        var iheight = 7.4;
        this.searchForm.css({'height':iheight+'em', padding:'10px', 'min-width': '730px'});
        this.recordList.css({'top':iheight+2+'em'});
        */
        //init viewer 
        var that = this;
        
        this._on( this.searchForm, {
                "searchsysdashboardonresult": this.updateRecordList,
                "searchsysdashboardonadd": function() { this.addEditRecord(-1); }
                });
                
        this.recordList.resultList('setHeaderText','<h3>Dashboard</h3><span class="heurist-helper1">A shortcuts to commonly used functions for this particuar database (al functions are available at any time via the menues and filter fields)</span>',
            {height:'4em'});
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
          
            this.options.btn_array = [{text: window.hWin.HR(that._isViewMode?'Edit':'View'),
                        click: function( event ) { 
                            $(event.target).button('option','label', window.hWin.HR(!that._isViewMode?'Edit':'View'));
                            that._setMode(!that._isViewMode);
                        }}];
        
            this._super();
            
    },
    
    //
    //  set view or edit mode
    //
    _setMode: function(newmode){
        
        if(newmode){
            this.searchForm.hide();
            this.recordList.css({'top': 0});
            this._as_dialog.parent().find('.ui-dialog-titlebar').hide();
        }else{
            this.searchForm.show();
            this.recordList.css({'top': '2.8em'});
            this._as_dialog.parent().find('.ui-dialog-titlebar').show();
        }
        this.recordList.resultList('option','show_counter', !newmode);
        this.recordList.resultList('option','show_inner_header', newmode);
        
        if(this._isViewMode !== newmode){
            this._isViewMode = newmode;
            this.options.isViewMode = newmode;
            this.searchForm.searchSysDashboard('option','isViewMode',newmode);
            this.searchForm.searchSysDashboard('startSearch');
        }
        
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
        + '<div class="recordDescription" style="display:none">'
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
            var command = this._selection.fld(record, 'dsh_CommandToRun');
            var params = this._selection.fld(record, 'dsh_Parameters');
console.log(command+'  '+params);

            if(command.indexOf('menu-')==0){ //main menu action

                var app = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('mainMenu');
                if(app && app.widget){
                    $(app.widget).mainMenu('menuActionById', command);
                }

            }

            
        }
    },
    
    
    //
    getUiPreferences:function(){
        return null;
    },
    
    //    
    saveUiPreferences:function(){
        
    },
    

});
/*
                            {"key":"menu-database-browse","title":"Browse databases"},
                            {"key":"menu-database-create","title":"Create new database"},
                            {"key":"menu-database-clone","title":"Clone current database"},
                            {"key":"menu-database-properties","title":"Database Properties"},
                            {"key":"menu-database-register","title":"Database Register"},
                            
                            
                            {"key":"menu-structure-rectypes","title":"Manage Record Types"},
                            {"key":"menu-structure-import","title":"Browse templates"},
                            {"key":"menu-structure-terms","title":"Manage terms"},
                            {"key":"menu-structure-reltypes","title":"Manage relationship types"},
                            {"key":"menu-structure-fields","title":"Manage base field types"},
                            {"key":"menu-structure-summary","title":"Visualise DB Structure"},
                            
                            {"key":"menu-structure-verify","title":"Verify integrity"},
                            {"key":"menu-structure-refresh","title":"Refresh memory"},
                            
                            
                            {"key":"menu-profile-preferences","title":""},
                            {"key":"menu-profile-tags","title":""},
                            {"key":"menu-profile-reminders","title":""},
                            {"key":"menu-profile-files","title":""},

                            {"key":"","title":""},
                            {"key":"","title":""},
                            {"key":"","title":""},
                            
                            
                            {"key":"menu-export-csv","title":"Export toCSV"},
                            {"key":"","title":""},
                            {"key":"","title":""},
                            {"key":"","title":""},
                            
*/