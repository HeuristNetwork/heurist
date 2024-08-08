/**
* manageSysDashboard.js - main widget to manage sysDashboard
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


$.widget( "heurist.manageSysDashboard", $.heurist.manageEntity, {
    
    options: {
        is_iconlist_mode: true,  //show in compact mode
        isViewMode: true
    },

    _entityName:'sysDashboard',
    defaultPrefs: {viewmode:'thumbs3', show_on_startup:1, show_as_ribbon:1},
    
    //
    //
    //
    _init: function() {
        
        this.options.default_palette_class = 'ui-heurist-design';
        
        this.options.coverall_on_save = true;
        this.options.layout_mode = 'short';
        this.options.use_cache = false;
        //this.options.select_return_mode = 'recordset';
        this.options.edit_need_load_fullrecord = true;
        this.options.edit_height = 600;
        this.options.width = 1200;

        this.options.height = (isNaN(this.options.height) || this.options.height<815)?900:this.options.height;                    
        
        this.options.title = 'Shortcuts';
        
        //this.options.isViewMode = true;
        
        this.options.no_bottom_button_bar = true;
    
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
        if(this.options.is_iconlist_mode){
            this.searchForm.hide();    
        }
        
        //init viewer 
        let that = this;
        
        let prefs = this.getUiPreferences();

        this._on( this.searchForm, {
                "searchsysdashboardonresult": this.updateRecordList,
                "searchsysdashboardviewmode": function() { this._setMode( true );  }, //back to view mode
                "searchsysdashboardonadd": function() { this.addEditRecord(-1); },
                "searchsysdashboardonorder": function() { this.saveNewOrder() },
                "searchsysdashboardonclose": function() { this.closeDialog() },
                "searchsysdashboardoninit": function(){
                    
        //this.show_longer_description = this.searchForm.find('#show_longer_description');
        this.show_on_startup = this.searchForm.find('#show_on_startup');
        this.show_as_ribbon = this.searchForm.find('#show_as_ribbon');
        //this.show_on_startup2 = this.searchForm.find('#show_on_startup2');

        /*this.show_longer_description
                .attr('checked',(prefs.viewmode=='thumbs3'))
            .on('change', function(){
                that.recordList.resultList('option','view_mode', 'thumbs');
                    //that.show_longer_description.is(':checked')?'thumbs3':'thumbs');
                that.saveUiPreferences();
        });*/
        
        this._on(this.show_on_startup, {change:this.saveUiPreferences});
        this._on(this.show_as_ribbon, {change:this.saveUiPreferences});
        
        //this._on(this.show_on_startup2,{change:this.saveUiPreferences});
        
        this._setMode(this.options.isViewMode);
        
        this.searchForm.find('#edit_dashboard').on('click', function(){
            that._setMode(false);
        });
                    
                    
                }
                });
                
        //this.recordList.resultList('setHeaderText','<h3>Dashboard</h3><span class="heurist-helper1">A shortcuts to commonly used functions for this particuar database (al functions are available at any time via the menues and filter fields)</span>',
        //    {height:'4em'});
        this.recordList.resultList('option','multiselect', false);
        this.recordList.resultList('option','select_mode', 'select_single');
        
        //load all menu commands
        let menu_entries = [];
        menu_entries.push({key:'',title:'select command ...'});
        menu_entries.push({key:'menu-import-add-record',title:'Add record'});
        menu_entries.push({key:'action-AddRecord',title:'Add record - specific type'});
        menu_entries.push({key:'menu-help-bugreport',title:'Bug report'});
        menu_entries.push({key:'menu-manage-archive',title:'Create archive package'});
        menu_entries.push({key:'menu-structure-rectypes',title:'Edit record types'});
        menu_entries.push({key:'menu-structure-vocabterms',title:'Edit vocabularies'});
        menu_entries.push({key:'menu-structure-fieldtypes',title:'Edit base fields'});
        menu_entries.push({key:'menu-profile-groups',title:'Edit workgroups'});
        menu_entries.push({key:'menu-cms-edit',title:'Edit website'});
        menu_entries.push({key:'action-CreateFilter',title:'Filter builder'});
        menu_entries.push({key:'action-SearchById',title:'Filter - saved filter'});
        menu_entries.push({key:'action-Search',title:'Filter - specific string'});
        menu_entries.push({key:'menu-export-csv',title:'Export CSV'});
        menu_entries.push({key:'menu-help-online',title:'Help'});
        menu_entries.push({key:'menu-files-upload',title:'Import media'});
        menu_entries.push({key:'menu-profile-files',title:'Manage files'});
        menu_entries.push({key:'menu-profile-preferences',title:'Preferences'});
        menu_entries.push({key:'menu-structure-refresh',title:'Refresh memory'});
        menu_entries.push({key:'menu-structure-verify',title:'Verify integrity'});
        //get all saved searches
        let saved_searches = [{key:0, title:'select saved filter....'}];
        let ssearches = window.hWin.HAPI4.currentUser.usr_SavedSearch;
        let grp_IDs = [];
        for (let svsID in ssearches)
        {
            grp_IDs.push(ssearches[svsID][2]);
            saved_searches.push({key:svsID, title:ssearches[svsID][0], grpID:ssearches[svsID][2] });
        }
        
        window.hWin.HAPI4.SystemMgr.usr_names({UGrpID: grp_IDs}, function(res){
            if(res && res.status==window.hWin.ResponseStatus.OK){
                for (let idx in saved_searches)
                {
                    if(res.data[saved_searches[idx].grpID]){
                        saved_searches[idx].title = res.data[saved_searches[idx].grpID] +  ' > ' +   saved_searches[idx].title;
                    }
                }
            }
        });
        
        
        let k = 0;
        let fields = that.options.entity.fields;
        for (let idx=0; idx<fields.length; idx++){
            if(fields[idx]['dtID']=='dsh_CommandToRun'){
                that.options.entity.fields[idx]['dtFields']['rst_FieldConfig'] = menu_entries;
                k++;
            }else if(fields[idx]['dtID']=='dsh_ParameterSavedSearch'){
                that.options.entity.fields[idx]['dtFields']['rst_DefaultValue'] = 0;
                that.options.entity.fields[idx]['dtFields']['rst_FieldConfig'] = saved_searches;
                k++;
            }
            if(k==2) break;
        }
        
        return true;
    },

    //
    // start/refresh search
    //
    startSearch: function(){
        if(this.searchForm.searchSysDashboard('instance')){
            this.searchForm.searchSysDashboard('startSearch');
        }
    },
        
    //
    //
    //    
    updateRecordList: function( event, data ){    
        this._super(event, data);
        this._adjustHeight();
    },
    
    //
    //
    //
    _adjustHeight: function(){
        

        let h = 600;
        if(this.options.isViewMode){
            let recordset = this.recordList.resultList('getRecordSet');
            if(recordset){
                let len = recordset.length();
                //set height to fit number of entries   
                h = 100 + Math.ceil(len/3) * 120;
                h = (h<300)?300:(h>600?600:h);                                  
            }
        }
        
        if(this.isdialog){
            this._as_dialog.dialog('option', 'height', h);                                        
        }
        
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
            this.searchForm.css({'height': '7.4em'});
            
            this.searchForm.find('#view_mode').show();
            this.searchForm.find('#edit_mode').hide();
            //this._as_dialog.parent().find('.ui-dialog-titlebar').hide();
            
            this.recordList.resultList('option', 'sortable', false);
            
            if(this.options.is_iconlist_mode){
                this.recordList.resultList('applyViewMode','icons_list', true);
                //hide header
                this.recordList
                    .css({top:0,right:4,left:100,'border-left':'1px solid lightgray'})
                    .removeClass('ui-heurist-bg-light');
                this.recordList.find('.div-result-list-content')
                    .removeClass('ui-heurist-bg-light');

                //this.element.css({'background-color':'rgb(234, 236, 240) !important'}); ('option','custom_css',
                this.recordList.css({'background-color': 'rgb(234, 236, 240) !important'});
                
                if(!this.div_openconfig){
                    this.element.css({'background-color':'rgb(234, 236, 240) !important'});
                    this.div_openconfig = $('<span><i>Shortcuts </i>'
                    +'<span title="Customize" class="ui-icon ui-icon-gear" style="padding-left:10px;cursor:pointer"></span>') //background:'rgb(234, 236, 240)'
                        .css({color:'black',position:'absolute',left:0,top:12,//'padding-top':'12px',
                                            'padding-left': '6px',
                                            height:36,width:99})
                        .appendTo(this.element);

                    this._on(this.div_openconfig, {
                        click:function(){
                            let that = this;
                            window.hWin.HEURIST4.ui.showEntityDialog('sysDashboard',   //edit mode
                                {isViewMode:false, is_iconlist_mode:false,
                                onClose:function(){
                                    setTimeout('$(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE)',1000);
                            }  });}
                        }
                    );                        
                        
                }
            }else{
                this.recordList.resultList('option','view_mode','thumbs');    
                this.recordList.css({'top': '5.4em'});
            }
            
            this.recordList.find('.div-result-list-content').css({'overflow-y':'auto'});
            
        }else{
            // edit mode
            
            //this.searchForm.show();
            this.searchForm.css({'height': '2.4em'});
            this.recordList.css({'top': '2.8em'});

            this.searchForm.find('#view_mode').hide();
            this.searchForm.find('#edit_mode').show();
            //this._as_dialog.parent().find('.ui-dialog-titlebar').show();
            
            this.recordList.resultList('option','view_mode','list');
            
            let that = this;
            this.recordList.resultList('option', 'sortable', true);
            this.recordList.resultList('option', 'onSortStop', function(){
                that.saveNewOrder(); //auto save order
                //that.searchForm.find('#btn_apply_order').css({'display':'inline-block'});
            });
            
            this.show_on_startup.prop('checked', (this.usrPreferences.show_on_startup==1));// || this.usrPreferences.show_as_ribbon==1
            this.show_as_ribbon.prop('checked', this.usrPreferences.show_as_ribbon==1);
            
            //window.hWin.HEURIST4.util.setDisabled(this.show_on_startup, (this.usrPreferences.show_as_ribbon==1));

        }
        /*
        this.recordList.resultList('option','show_counter', !newmode);
        this.recordList.resultList('option','show_inner_header', newmode);
        */
        this.recordList.resultList('option','show_toolbar', !newmode);
        
        if(this.options.isViewMode !== newmode && this.searchForm.searchSysDashboard('instance')){
            this.options.isViewMode = newmode;
            this.searchForm.searchSysDashboard('option','isViewMode',newmode);
            this.startSearch();
        }
        
        /*
        this.btn_set_mode
            .button('option','label',window.hWin.HR(this.options.isViewMode?'Close':'View'))
            .attr('title',this.options.isViewMode
                        ?'The dashboard function can be turned back on by selecting Management > Dashboard'
                        :'Turn back to presentation mode');
        */
        this._adjustHeight();
        
    },

    _initEditForm_step4: function(recordset){

        if(recordset!=null){
            
                let record = recordset.getFirstRecord();
                let command = recordset.fld(record, 'dsh_CommandToRun');
                let params = recordset.fld(record, 'dsh_Parameters');
                if(command=='action-AddRecord'){
                    recordset.setFld(record, 'dsh_ParameterAddRecord', params);
                }else if(command=='action-SearchById'){
                    recordset.setFld(record, 'dsh_ParameterSavedSearch', params);
                }
        }
        
        
        this._super(recordset);        
    },
    
    
    //-----
    // change parameter entry according to specified command
    //
    _afterInitEditForm: function(){

        this._super();
        
        
        //fill init values of virtual fields
        //add lister for dty_Type field to show hide these fields
        let elements = this._editing.getInputs('dsh_CommandToRun');
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(elements)){
            this._on( $(elements[0]), {    
                'change': function(event){
                       let dsh_command = $(event.target).val();
                       
                       let ele_param = this._editing.getFieldByName('dsh_Parameters');
                       let ele_param_ar = this._editing.getFieldByName('dsh_ParameterAddRecord');
                       let ele_param_sf = this._editing.getFieldByName('dsh_ParameterSavedSearch');
                       
                       $(ele_param).hide();
                       $(ele_param_ar).hide();
                       $(ele_param_sf).hide();
                                              
                       if(dsh_command=='action-AddRecord'){
                           $(ele_param_ar).show();
                           
                       }else if(dsh_command=='action-Search'){
                            $(ele_param).show();
                           
                       }else if(dsh_command=='action-SearchById'){
                            $(ele_param_sf).show();
                           
                       }
                    
                }
                
            });
            
            $(elements[0]).trigger('change'); //trigger
        }

    },
    //
    // force refresh after save (note: in case cached entity it auto happens in parent - manageEntity)
    //
    _afterSaveEventHandler: function( recID, fieldvalues ){
        this._super( recID, fieldvalues );
        
        this.getRecordSet().setRecord(recID, fieldvalues);    
        this.recordList.resultList('refreshPage');  
        
        //refresh count of active dashboards
        window.hWin.HAPI4.SystemMgr.sys_info_count();
    },
    
    _afterDeleteEvenHandler: function( recID ){
        this._super( recID );
        
        //refresh count of active dashboards
        window.hWin.HAPI4.SystemMgr.sys_info_count();
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
            let swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = ' style="width:'+col_width+'"';
            }
            return '<div class="item" '+swidth+'>'+window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname))+'</div>';
        }
        
        
        let specialWidth = '';
        
        if (this.options.isViewMode && !this.options.is_iconlist_mode) {
            specialWidth = 'width:250px';
            //detect if there is no description
            recordset.each( function(id, record){
                if (this.fld(record, 'dsh_Description')!='') {
                    specialWidth = '';
                    return false;
                }
            });
        }
        
        
                
        //ugr_ID,ugr_Type,ugr_Name,ugr_Description, ugr_eMail,ugr_FirstName,ugr_LastName,ugr_Enabled,ugl_Role
        
        let recID   = fld('dsh_ID');
        
        let rectype = fld('ulf_ExternalFileReference')?'external':'local';
        
        let recTitle = fld2('dsh_Label','auto');//,'20em'
        let recTitleHint = fld('dsh_Description');
        let recOpacity = (fld('dsh_Enabled')=='y')?1:0.3;
        
        let rtIcon = window.hWin.HAPI4.getImageUrl(this._entityName, 0, 'icon');
        let recThumb = window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'thumb', 2, this.options.database);
        
        let html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+recThumb+'&quot;);opacity:'+recOpacity+'">'
        +'</div>';

        //id="rd'+recID+'" 
        let html = '<div class="recordDiv landscape'+(this.options.isViewMode?' dashboard outline_suppress':'')
        +'" recid="'+recID+'" style="cursor:pointer;'+specialWidth+'">'
        + html_thumb;
        
        if(!this.options.isViewMode && this.options.select_mode=='manager' && this.options.edit_mode=='popup'){
        
            html = html + '<div style="display: inline-block;padding-top:5px;">'
                + '<div title="Click to delete entry" style="width:16px;height:16px" class="logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
                + '</div></div>';
        }
        //+ '<div class="recordSelector"><input type="checkbox" /></div>'
        html = html 
        /*+ '<div class="recordIcons" style="min-width:16px;">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '" style="background-image: url(&quot;'+rtIcon+'&quot;);opacity:'+recOpacity+'">'
        + '</div>' */
        + '<div class="recordTitle recordTitle2">'  // style="right:180px"
        +     recTitle
        + '</div>'
        + '<div class="recordDescription">'
        +     recTitleHint
        + '</div>';

        
        // add edit/remove action buttons
        if(!this.options.isViewMode && this.options.select_mode=='manager' && this.options.edit_mode=='popup'){
                /* these are standard buttons _ IJ wants special place
                + '<div title="Click to edit entry" class="rec_edit_link_ext logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                + '</div>&nbsp;&nbsp;'
                + '<div title="Click to delete entry" class="rec_view_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
                + '</div>';
                */
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
            let record = this._selection.getFirstRecord();
            if(this.options.isViewMode){
                let command = this._selection.fld(record, 'dsh_CommandToRun');
                let params = this._selection.fld(record, 'dsh_Parameters');
                const dsh_ID = this._selection.fld(record, 'dsh_ID');
                
                if(command.indexOf('menu-')==0){ //main menu action
                
                    //temporal fix (error in coreDefinition.txt)
                    if(command=='menu-export-csv' && dsh_ID==10){
                        command = 'menu-import-csv';
                    }else if(command=='menu-import-add-record'){
                        
                        let widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('mainMenu6');
                        if(widget){
                             let ele = this.element.find('div.recordDiv[recid='+dsh_ID+']');
                             widget.mainMenu6('show_ExploreMenu', null, 'recordAdd', {top:0, left:ele.offset().left });
                             return;
                        }
                    }

                    window.hWin.HAPI4.LayoutMgr.executeCommand('mainMenu', 'menuActionById', command);
                    /*
                    let widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('mainMenu');
                    if(widget){
                        widget.mainMenu('menuActionById', command);
                    }
                    */

                }else if(command=='action-AddRecord'){ //add new record
                    let params = window.hWin.HEURIST4.util.isJSON( this._selection.fld(record, 'dsh_Parameters') );
                    if(params!==false){
                        window.hWin.HEURIST4.ui.openRecordEdit(-1, null, {new_record_params:params});
                    }
                
                }else if(command=='action-SearchById'){ //search saved filter
                    
                    this.closeDialog();
                
                    let svsID = this._selection.fld(record, 'dsh_Parameters');
                    let widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('svs_list');
                    if(widget){
                        widget.svs_list('doSearchByID', svsID);        
                    }
                    

                }else if(command=='action-Search'){ //search query saved as param
                
                    this.closeDialog();
                
                    let qsearch = this._selection.fld(record, 'dsh_Parameters');
                    
                    let widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('svs_list');
                    if(widget){
                        widget.svs_list('doSearch', 0, '', qsearch, false);        
                    }


                }else if(command=='action-CreateFilter'){ //add new record
                
                    this.closeDialog();

                    let widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('mainMenu6');
                    if(widget){
                         let ele = this.element.find('div.recordDiv[recid='+dsh_ID+']');
                         widget.mainMenu6('show_ExploreMenu', null, 'searchBuilder', {top:0, left:ele.offset().left });
                    }else{
                        widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('search');
                        if(widget){
                            widget.search('showSearchAssistant');        
                        }
                    }
               
                }

            }else{
                let recID = this._selection.fld(record, 'dsh_ID');
                this.addEditRecord(recID);
            }
        }
    },
    
    //
    // apply new order
    //
    saveNewOrder: function(){
        
            this.searchForm.find('#btn_apply_order').hide();    
        
            let recordset = this.recordList.resultList('getRecordSet');
            //assign new value for dtg_Order and save on server side
            let rec_order = recordset.getOrder();
            let idx = 0, len = rec_order.length;
            let fields = [];
            for(; (idx<len); idx++) {
                let record = recordset.getById(rec_order[idx]);
                let oldval = recordset.fld(record, 'dsh_Order');
                let newval = String(idx+1).lpad(0,3);
                if(oldval!=newval){
                    recordset.setFld(record, 'dsh_Order', newval);        
                    fields.push({"dsh_ID":rec_order[idx], "dsh_Order":newval});
                }
            }
            if(fields.length>0){

                let request = {
                    'a'          : 'save',
                    'entity'     : this._entityName,
                    'request_id' : window.hWin.HEURIST4.util.random(),
                    'fields'     : fields                     
                };

                let that = this;                                                
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
       /* 
       this.usrPreferences = {viewmode: 'thumbs'};

       this.usrPreferences['show_as_ribbon']  = (this.show_as_ribbon.is(':checked'))?1:0;
       if(this.usrPreferences['show_as_ribbon']){
            this.usrPreferences['show_on_startup'] = 1;
            this.show_on_startup.prop('checked',true);    
       }
       this.usrPreferences['show_on_startup'] = (this.options.isViewMode? true : this.show_on_startup.is(':checked'))?1:0;
       window.hWin.HEURIST4.util.setDisabled(this.show_on_startup, this.usrPreferences['show_as_ribbon']);
       
       window.hWin.HAPI4.save_pref('prefs_'+this._entityName, this.usrPreferences);     
       */
    },
    

});