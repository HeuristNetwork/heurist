/**
* manageRecords.js - main widget to EDIT Records
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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

//EXPERIMENTAL - THIS FUNCTION IS IN COURSE OF DEVELOPMENT
$.widget( "heurist.manageRecords", $.heurist.manageEntity, {
   
    _entityName:'records',
    
    _currentEditRecTypeID:null,
    _swf_rules: [], //getSwfByRectype
    _isInsert: false,
    _additionWasPerformed: false, //NOT USED for selectAndSave mode
    _updated_tags_selection: null,
    _keepYPos: 0,
    _menuTimeoutId: 0,
    _rts_selector_flag: false, //mouse over rts selectors
    _rts_changed_flag: false,
    _resizeTimer: 0,
    
    // additional option for this widget
    // this.options.fixed_search - search form hidden and search query is fixed
    // this.options.rectype_set  - filter for available record types (for selection)
    // this.options.edit_structure - open edit structure mode at once and work with fake record
    // this.options.selectOnSave - special case when open edit record from select popup
    // this.options.allowAdminToolbar  - if false hide ModifyStructure, Edit title mask and others
    // this.options.rts_editor  - back reference to manageDefRecStructure widget
    // parententity
    // relmarker_field - if relationship record is opened from source or target record - constraints are applied
    // relmarker_is_inward

    options: {
        fill_in_data: null // initial fill in data, for new records
    },

    usrPreferences:{},
    defaultPrefs:{
        width:(window.hWin?window.hWin.innerWidth:window.innerWidth)*0.95,
        height:(window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95,
        optfields:true, 
        help_on:true,
        show_warn_about_relationship: true,
        
        structure_closed: 1,          
        structure_width:180, //280,
        
        summary_closed:true,          
        summary_width:400, 
        summary_tabs:['0','1']},
    
    // For external lookups, holds values that need to be processed
    term_values: [],
    file_values: {},
    resource_values: [],

    _init: function() {
        
        if(!this.options.default_palette_class){
            this.options.default_palette_class = 'ui-heurist-populate';  
            //since 2021-05-05 there is no difference this.options.edit_structure?'ui-heurist-design':'ui-heurist-populate';   
        }
        
        if(this.options.layout_mode=='short'){
                this.options.layout_mode = //slightly modified 'short' layout
                        '<div class="ent_wrapper editor">'
                            +'<div class="ent_wrapper">'
                                +    '<div class="ent_header searchForm" style="height:auto"/>'     
                                +    '<div class="ent_content_full recordList"/>'
                            +'</div>'

                            + '<div class="editFormDialog ent_wrapper editor">'
                                    + '<div class="ui-layout-west"><div class="editStructure">..</div></div>' //container for rts_editor
                                    + '<div class="ui-layout-center"><div class="editForm"/></div>'
                                    + '<div class="ui-layout-east"><div class="editFormSummary">....</div></div>'
                                    //+ '<div class="ui-layout-south><div class="editForm-toolbar"/></div>'
                            +'</div>'
                        +'</div>';
        }
        
        this.options.use_cache = false;
        //this.options.edit_height = 640;
        //this.options.edit_width = 1200;

        //for selection mode set some options
        if(this.options.select_mode!='manager'){
            this.options.width = (isNaN(this.options.width) || this.options.width<640)?640:this.options.width;      
        }else{
            this.options.width = 1200;                    
        }
        
        this.options.editClassName = 'recordEditor';

        this.getUiPreferences();
        
        this._super();
        
        //this.editForm.empty();

        var hasSearchForm = (this.searchForm && this.searchForm.length>0);
        
        if(this.options.edit_mode=='inline' || this.options.edit_mode=='editonly'){
            // for manager - inline mode means that only editor is visible and we have to search init exterally
            // see recordEdit.php
            if(hasSearchForm) this.searchForm.parent().css({width:'0px'});    
            this.editFormPopup.css({left:0}).show();
            
            var $dlg = this._getEditDialog(true);
            if($dlg){
                $dlg.parent().addClass(this.options.default_palette_class);
            }
            
        }else{
            //for select mode we never will have inline mode
            if(hasSearchForm) this.searchForm.parent().width('100%');
            this.editFormPopup.css({left:0}).hide(); 
        }
        
        //-----------------
        var sh = 0;
        if(hasSearchForm && this.searchForm.is(':visible')){
            
            var rt_list = this.options.rectype_set;
            var is_expand_rt_list = false;
            var is_only_rt = false;
            if(!window.hWin.HEURIST4.util.isempty(rt_list)){
                if(!window.hWin.HEURIST4.util.isArray(rt_list)){
                    rt_list = rt_list.split(',');
                }
                cnt = rt_list.length;
                is_only_rt = (cnt==1);
            }else{
                cnt = 1;
            }            
            
            this._adjustResultListTop();
            
        }else{
            this.recordList.css('top', 0);    
        }
        
        //  this.recordList.css('top', sh+'em');    
        
        var that = this;
        
        jQuery(document).keydown(function(event) {
                // If Control or Command key is pressed and the S key is pressed
                // run save function. 83 is the key code for S.
                if((event.ctrlKey || event.metaKey) && event.which == 83) {
                    // Save Function
                    event.preventDefault();
                    that._saveEditAndClose( null, 'none' );
                    return false;
                }
            }
        ); 
        
        //create field actions for rts editor
        if(true || this.options.rts_editor){ //height:17px;background:#95A7B7 !important;
            this.rts_actions_menu = 
            $('<div class="rts-editor-actions" '
                    +'style="width:110px;background:lightblue;display:none;padding-top:2px;'
                    +'font-size:10px;font-weight:normal;cursor:pointer">'
                   //+'<div style="line-height:18px">&nbsp;</div>' 
                   +'<div data-action="edit" style="background:lightblue;padding:2px 4px;width:102px;">'
                        +'<span class="ui-icon ui-icon-pencil" title="Edit" style="font-size:9px;font-weight:normal"/>Edit</div>'           
                   +'<div data-action="field" style="background:lightcyan;padding:2px 4px;display:block;width: 102px;">'
                        +'<span class="ui-icon ui-icon-arrowreturn-1-e" title="Add a new field to this record type" '
                        +'style="transform: rotate(90deg);font-size:9px;font-weight:normal"/>Insert field</div>'
                   +'<div data-action="block" title="Add a new group/separator" style="background:lightgreen;padding:2px 4px;width:102px;">'    +'<span class="ui-icon ui-icon-arrowreturn-1-e" '
                       +'style="transform: rotate(90deg);font-size:9px;font-weight:normal"></span>Insert tab/divider</div>'               
                        
                   +'<div class="edit_rts_sel" style="padding:2px 4px;width: 102px;" title="Requirement type">'
                        +'<select class="edit_rts s_reqtype"><option>required</option><option>recommended</option><option>optional</option>'
                        +'<option value="forbidden">hidden</option></select></div>' 
                                  
                   +'<div class="edit_rts_sel" style="padding:2px 4px;width: 102px;" title="Repeatability">'
                        +'<select class="edit_rts s_repeat"><option value="1">single</option><option value="0">repeatable</option>'
                        +'<option value="2">limited 2</option><option value="3">limited 3</option>'
                        +'<option value="5">limited 5</option><option value="10">limited 10</option></select></div>'           
                        
                   +'<div class="edit_rts_sel s_width" style="padding:2px 4px;width: 102px;" title="Width of field">Width: '
                        +'<select class="edit_rts s_width" style="display:none">'
                        +'<option>5</option><option>10</option><option>20</option><option>30</option>'
                        +'<option>40</option><option>50</option><option>60</option><option>80</option><option>100</option>'
                        +'<option>120</option><option>150</option>'
                        +'<option>200</option><option>250</option>'
                        +'<option value="0">Max</option></select></div>'

                   +'<span class="edit_rts_btn" style="top:24px;left:80px;position:absolute;background:lightblue;display:none" '
                   +' data-apply="1" title="Save changes for field properties">Apply</span>'
                   +'<span class="edit_rts_btn" style="top:24px;left:130px;position:absolute;background:lightblue;display:none">'
                   +'Cancel</span>'
                           
                    +'</div>')
                    .appendTo(this.element);
            
            
            window.hWin.HEURIST4.ui.initHSelect(this.rts_actions_menu.find('select.s_reqtype'),
                                            false, {'max-width':'100px','font-size':'0.9em', padding:0});
            window.hWin.HEURIST4.ui.initHSelect(this.rts_actions_menu.find('select.s_repeat'),
                                            false, {'max-width':'100px','font-size':'0.9em', padding:0});
            var ele = window.hWin.HEURIST4.ui.initHSelect(this.rts_actions_menu.find('select.s_width'),
                                            false, {'max-width':'60px','font-size':'0.9em', padding:0});
            
            
            
/* pre 2020-11-04 version                    
            $('<div class="rts-editor-actions" '
                    +'style="width:272px;background:none !important;display:none;padding-top:2px;'
                    +'font-size:10px;font-weight:normal;cursor:pointer">'
                   +'<div style="line-height:18px">&nbsp;</div>' 
                   +'<span data-action="edit" style="background:lightblue;padding:3px;display:inline-block;width:52px;">'
                        +'<span class="ui-icon ui-icon-pencil" title="Edit" style="font-size:9px;font-weight:normal"/>Edit</span>'           
                        
                   +'<span class="edit_rts_sel" style="background:lightblue;padding:4px" title="Requirement type">'
                        +'<select class="edit_rts s_reqtype"><option>required</option><option>recommended</option><option>optional</option>'
                        +'<option value="forbidden">hidden</option></select></span>'           
                   +'<span class="edit_rts_sel" style="background:lightblue;padding:4px" title="Repeatability">'
                        +'<select class="edit_rts s_repeat"><option value="1">single</option><option value="0">repeatable</option>'
                        +'<option value="2">limited 2</option><option value="3">limited 3</option>'
                        +'<option value="5">limited 5</option><option value="10">limited 10</option></select></span>'           
                   +'<span class="edit_rts_sel" style="background:lightblue;padding:4px" title="Width of field">'
                        +'<select class="edit_rts s_width"><option>5</option><option>10</option><option>20</option><option>30</option>'
                        +'<option>40</option><option>50</option><option>60</option><option>80</option><option>100</option>'
                        +'<option>120</option></select></span>'

                   +'<span class="edit_rts_btn" style="top:24px;left:80px;position:absolute;background:lightblue;display:none" '
                   +' data-apply="1" title="Save changes for field properties">Apply</span>'
                   +'<span class="edit_rts_btn" style="top:24px;left:130px;position:absolute;background:lightblue;display:none">'
                   +'Cancel</span>'
                           
                   +'<br><span data-action="field" style="background:lightcyan;padding:4px;display:inline-block;width: 50px;">'
                        +'<span class="ui-icon ui-icon-plus" title="Add a new field to this record type" style="font-size:9px;font-weight:normal"/>Field</span>'
                   +'<br><span data-action="block" title="Add a new group/separator" style="background:lightgreen;padding:4px;display:inline-block;width: 50px;"><span style="font-size:11px">&nbsp;+&nbsp;&nbsp;</span>Block</span>'               
                    +'</div>')
*/                    
                    
            
            //save/cancel rts buttons
            this.edit_rts_apply = this.rts_actions_menu.find('.edit_rts_btn').button();
            this._on( this.edit_rts_apply, {
                click: function(e){
                    
                    if($(e.target).attr('data-apply')){

                            var dtId = this.rts_actions_menu.attr('data-did');
                        
                            var fields = {
                                rst_RecTypeID: this._currentEditRecTypeID,
                                rst_DetailTypeID: dtId,
                                rst_MaxValues: this.rts_actions_menu.find('select.s_repeat').val(), 
                                rst_DisplayWidth: this.rts_actions_menu.find('select.s_width').val(), 
                                rst_RequirementType: this.rts_actions_menu.find('select.s_reqtype').val()};

                            var request = {
                                'a'          : 'save',
                                'entity'     : 'defRecStructure',
                                'request_id' : window.hWin.HEURIST4.util.random(),
                                'fields'     : fields                     
                                };
                                
                            var dlged = that._getEditDialog();
                            if(dlged) window.hWin.HEURIST4.msg.bringCoverallToFront(dlged);
                            
                            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                                function(response){
                                    
                                    window.hWin.HEURIST4.msg.sendCoverallToBack();
                                    
                                    if(response.status == window.hWin.ResponseStatus.OK){
                                        
                                        //update db definitions and tree    
                                        that.options.rts_editor.manageDefRecStructure(
                                            'refreshRecset_Definition_TreeNodeItem', dtId, fields);
                                        
                                        //recreate edit field
                                        var dtFields = that._prepareFieldForEditor( null, that._currentEditRecTypeID, dtId );
                                        var inpt = that._editing.getFieldByName(dtId);
                                        inpt.editing_input('option', {dtFields:dtFields, recreate:true} );
                                        that._createRtsEditButton(dtId, $(that.element).find('div[data-dtid="'+dtId+'"]') );
                                        
                                        if(fields['rst_RequirementType']=='forbidden'){
                                            inpt.find('.header').css({'opacity':'0.3'});   //header
                                            inpt.find('.header').next().css({'opacity':'0.3'}); //repeat btn 
                                            //var ele_id = ele_fb.next().next().css({'opacity':'0.3'}); //input-cell 
                                            inpt.find('input,textarea,button,.ui-selectmenu-button').css('border','1px dotted red');
                                        }
                                        
										/* Hide Help Text if 'Show Help' isn't checked, help text is shown by default */
                                        if(!that.element.find('.chb_show_help').is(':checked')){
                                            $('.heurist-helper1').hide();
                                        }
                                        
                                        if((dtFields['dty_Type'] == 'freetext' || dtFields['dty_Type'] == 'blocktext' || dtFields['dty_Type'] == 'float') 
                                                && dtFields['rst_DisplayWidth'] == 0){

                                            var width = that.editForm.width() * ((that.options.rts_editor) ? 0.7 : 0.8);

                                            inpt.find('input, textarea').css({'min-width': width, width: width});
                                        }
                                    }else{
                                        window.hWin.HEURIST4.msg.showMsgErr(response);
                                    }
                                });
                        
                    }
                    this.rts_actions_menu.hide(); 
                }
            });

            //prevent exit on select events                    
            this._on( this.rts_actions_menu.find('.edit_rts'), {
                mouseover : function(){ this._rts_selector_flag = true; clearTimeout(this._menuTimeoutId); },
                mouseleave : function(){ this._rts_selector_flag = false; },
                change: function(event){
                        this._rts_changed_flag = true;
                        this.edit_rts_apply[0].click();
                        //this.edit_rts_apply.show();
                }                
            });
                    
            this._on( this.rts_actions_menu, {
                mouseover : function(){ clearTimeout(this._menuTimeoutId); },
                mouseleave : function(){ 
                    if(this._rts_selector_flag || this._rts_changed_flag) return;
                    if($('.ui-selectmenu-menu.ui-selectmenu-open').length>0) return; //do not hide if dropdown is opened

                    that._menuTimeoutId = setTimeout(function() {
                        that.rts_actions_menu.hide(); 
                        that.options.rts_editor.manageDefRecStructure('highlightNode', null);
                    }, 800);  
                },
                click: function(event){
            
                        var trg = $(event.target);                     
                        if(trg.parents('.ui-selectmenu-button').length>0) return;
                    
                        if(this._rts_selector_flag || this._rts_changed_flag) return;
                        var dt_id = this.rts_actions_menu.attr('data-did');
                        
                        this.rts_actions_menu.hide();
                        
                        var ele = $(event.target);
                        var action = ele.attr('data-action');
                        if(!action) action = ele.parent().attr('data-action');
                        
                        if(action=='field' || action=='block' || action=='edit'){
                            
                            function __modifyStructureAction(){
                                
                                that._editing.setModified(0);

                                if(action=='field'){    

                                    var rec_dlg = that.options.isdialog && that._as_dialog != null ? that._as_dialog.parent() : null; 

                                    that.options.rts_editor.manageDefRecStructure(
                                        'showBaseFieldEditor', -1, dt_id, null, rec_dlg);
                                }else if(action=='block'){
                                    
                                    that.options.rts_editor.manageDefRecStructure(
                                        'addNewSeparator', dt_id);
                                    
                                }else if(action=='edit'){
                                    that.options.rts_editor.manageDefRecStructure(
                                        'editField', dt_id);
                                }
                            }
                            
                            //save record silently
                            that.saveQuickWithoutValidation( __modifyStructureAction );
                            
                        }
                            
                    }
            });
                    
        }
    },
    
    //
    //
    //
    saveQuickWithoutValidation: function( _callback ){
      
        if(this._editing.isModified()){ //2020-12-06 !this.options.edit_structure &&   
            var fields = this._editing.getValues(false);
            fields['no_validation'] = 1; //do not validate required fields
            this._saveEditAndClose( fields, _callback);           
        }else if($.isFunction(_callback)){
            _callback();
        }
        
    },
    
    
    //
    // adds gear button before edit field - it opens rts_actions_menu on mouse over
    //
    _createRtsEditButton : function(dtId, div_ele){  
        
        var that = this;
                      
        var rst_fields = (dtId != null) ? $Db.rst(that._currentEditRecTypeID, dtId) : null;
        if(rst_fields){
            
            var sep_id = $(div_ele).attr('separator-dtid');

            var is_folder = false;      
            var ele = $('<div'+(sep_id>0?(' data-dtid="'+sep_id+'"'):'')
                    +'><span class="ui-icon ui-icon-gear"></span></div>')
            .css({'display':(sep_id>0?'inline-block':'table-cell'),'vertical-align':'top',
                'min-width':'32px','cursor':'pointer','padding-top':'0.4em'});
            if(sep_id>0){
                ele.insertBefore($(div_ele));    
            }else{
                ele.prependTo($(div_ele));        
            }
                
            //ele = ele.find('.ui-icon-gear');
            that._on(ele,{mouseover:function(event){
                clearTimeout(that._menuTimeoutId);
                var el = $(event.target);

                var dtId = el.attr('data-dtid');
                if(!(dtId>0)) dtId = el.parents('div[data-dtid]').attr('data-dtid');

                var rst_fields = $Db.rst(that._currentEditRecTypeID, dtId);
                
                var dt_type = $Db.dty(dtId, 'dty_Type');
                if(dt_type=='separator'){
                    that.rts_actions_menu.width(110); //43
                    that.rts_actions_menu.find('.edit_rts_sel').hide();
                }else{
                    that.rts_actions_menu.width(110); //280
                    that.rts_actions_menu.find('.edit_rts_sel').show();
                    that.rts_actions_menu.find('select.s_reqtype').val(rst_fields['rst_RequirementType']).hSelect('refresh');
                    var v = rst_fields['rst_MaxValues'];
                    that.rts_actions_menu.find('select.s_repeat').val(v!=null && v>=0?v:0).hSelect('refresh');
                    var prev_v = 5;
                    if(dt_type=='freetext' || dt_type=='blocktext' || dt_type=='float'){
                        that.rts_actions_menu.find('div.s_width').show();
						prev_v = 0;
                    }else{
                        that.rts_actions_menu.find('div.s_width').hide()    
                    }
                    v = Number(rst_fields['rst_DisplayWidth']);
                    if(isNaN(v) || window.hWin.HEURIST4.util.isempty(v)) v=100;
                    //v = (v!=null && v>0)?(v==5?v :(Math.floor(v/10)*10)):100;
                    that.rts_actions_menu.find('select.s_width > option').each(function(i,item){
                        if(Number($(item).val())>v){
                            v = prev_v;
                            return false;
                        }
                        prev_v = Number($(item).val());
                    });
                    if(prev_v<v) v = prev_v;
                    
                    that.rts_actions_menu.find('select.s_width').val(v).hSelect('refresh');
                    //console.log(rst_fields['rst_DisplayWidth']+'  '+rst_fields['rst_MaxValues']);                                
                }

                that.rts_actions_menu.find('.edit_rts_btn').hide();
                that._rts_changed_flag = false;
                that.rts_actions_menu
                .attr('data-did', dtId)
                .show()
                .position({ my:'left top', at:'left+20 top', of: el});

                that.options.rts_editor.manageDefRecStructure('highlightNode', dtId);

                }, mouseout: function(event){
                    that._menuTimeoutId = setTimeout(function() {
                        that.rts_actions_menu.hide();
                        that.options.rts_editor.manageDefRecStructure('highlightNode', null); 
                    }, 800);
            }});
        }else{
            //placeholder
            var ele = $('<div>').css({'display':'table-cell','min-width':'32px'})
            .prependTo($(div_ele));    
        }
    },
    
    //
    //
    //
    _createNonStandardField: function(dtId, div_ele){

        var that = this;
        let parententity = Number(window.hWin.HAPI4.sysinfo['dbconst']['DT_PARENT_ENTITY']);

        if(dtId == null || $Db.rst(this._currentEditRecTypeID, dtId) != null || $Db.dty(dtId, 'dty_Type') == 'separator' || dtId == parententity){
            return;
        }

        if(!div_ele || div_ele.length == 0){
            div_ele = this.element.find('div[data-dtid="'+ dtId +'"] .input-cell');

            if(div_ele.length == 0){
                return;
            }
        }

        let max_values = 1;
        if(div_ele.length > 1){ // place in front of first input
            div_ele = $(div_ele[0]);
            max_values = 0; // force repeating
        }

        let $arrow_ele = $('<div data-dtid="'+ dtId +'" title="Add this base field and content to the current record type">'
            + '<span class="ui-icon ui-icon-arrowthick-1-n" style="font-size: 17px;"></span></div>')
        .css({
            display: 'table-cell',
            'min-width': '15px',
            cursor: 'pointer',
            'padding-top': '7px'
        }).insertBefore(div_ele);

        this._on($arrow_ele, {
            'click': (event) => {

                let $ele = $(event.target);

                let dtid = $ele.attr('data-dtid');
                if(!(dtid>0)) dtid = $ele.parents('div[data-dtid]').attr('data-dtid');

                let display_order = '001';
                $Db.rst(that._currentEditRecTypeID).each2((id, f) => {
                    if(f['rst_DisplayOrder'] > display_order){
                        display_order = String(+f['rst_DisplayOrder'] + 1).padStart(3, 0);
                    }
                });

                let fields = {
                    rst_ID: dtid,
                    rst_RecTypeID: that._currentEditRecTypeID,
                    rst_DisplayOrder: display_order,
                    rst_DetailTypeID: dtid,
                    rst_DisplayName: $Db.dty(dtid,'dty_Name'),
                    rst_DisplayHelpText: $Db.dty(dtid,'dty_HelpText'),
                    rst_RequirementType: 'optional',
                    rst_MaxValues: max_values,
                    rst_DisplayWidth: '100',  
                    rst_SemanticReferenceURL: $Db.dty(dtid,'dty_SemanticReferenceURL'),
                    rst_TermsAsButtons: 0
                };

                var request = {
                    'a'          : 'save',
                    'entity'     : 'defRecStructure',
                    'request_id' : window.hWin.HEURIST4.util.random(),
                    'fields'     : fields
                };

                let $dlg = that._getEditDialog();
                if($dlg) window.hWin.HEURIST4.msg.bringCoverallToFront($dlg);

                window.hWin.HAPI4.EntityMgr.doRequest(request, (response) => {

                    window.hWin.HEURIST4.msg.sendCoverallToBack();

                    if(response.status == window.hWin.ResponseStatus.OK){
                        $Db.rst(that._currentEditRecTypeID).setRecord(dtid, fields);
                        that.reloadEditForm(true);
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
            }
        });
    },
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {


        var reset_to_defs = !this.options.resultList || !this.options.resultList.searchfull;
        
        if(this.options.resultList && this.options.select_mode!='manager'){
            this.options.resultList.transparent_background = true;
        }
        
        if(!this._super()){
            return false;
        }

        // init search header
        if(this.searchForm && this.searchForm.length>0){
            this.searchForm.searchRecords(this.options);   //.addClass('ui-heurist-bg-light')  
            this._adjustResultListTop();
        }
/*        
        var iheight = 2;
        //if(this.searchForm.width()<200){  - width does not work here  
        if(this.options.select_mode=='manager'){            
            iheight = iheight + 4;
        }
        
        this.searchForm.css({'height':iheight+'em'});
        this.recordList.css({'top':iheight+0.4+'em'});
        */
        //if full search function and renderer were not set - reset to defaults
        if(reset_to_defs){
            this.recordList.resultList(
                    {
                        searchfull:null,
                        renderer:true //use default renderer but custom onaction see _onActionListener
                    }); //use default recordList renderer
        }
                
        if(this.options.select_mode=='manager'){
            this.recordList.parent().css({'border-right':'lightgray 1px solid'});
        }
        
        if(this.searchForm && this.searchForm.length>0){
            
            this._on( this.searchForm, {
                "searchrecordsonstart": this._adjustResultListTop,
                "searchrecordsonresult": this.updateRecordList,
                "searchrecordsonaddrecord": function( event, data){
                    if(window.hWin.HEURIST4.util.isObject(data)){                    
                        this._currentEditRecTypeID = data._rectype_id;
                        this.options.fill_in_data = data.fill_in_data;
                    }else{
                        this._currentEditRecTypeID = data;
                    }
                    this.addEditRecord(-1);
                },
                "searchrecordsonlinkscount": function( event, data ){
                    this.recordList.resultList({
                        aggregate_values: data.links_count,
                        aggregate_link: data.links_query
                    });
                },
                
        });
        }
                

        this.editForm.css({'overflow-y':'auto !important', 'overflow-x':'hidden', 'padding':'5px'});
         
        return true;
    },
    
    //
    //
    //
    _adjustResultListTop: function(){
         if(this.searchForm && this.searchForm.length>0 && this.searchForm.is(':visible')){
             this.recordList.css('top', this.searchForm.height());
         }else{
             this.recordList.css('top', 0); 
         }
        
    },
    
    //
    //
    //
    _onActionListener:function(event, action){    
            var res = this._super(event, action)
            if(!res){
                
                 var recID = 0;
                 if(action && action.action){
                     recID =  action.recID;
                     action = action.action;
                 }
                
                if(action=='edit_ext' && recID>0){
                    var url = window.hWin.HAPI4.baseURL + "?fmt=edit&db="+window.hWin.HAPI4.database+"&recID="+recID;
                    window.open(url, "_new");
                    res = true;
                }
                else if(action=='save_quick'){
                    this.saveQuickWithoutValidation();    
                    res = true;
                }
                
                
            }
            return res;
    },
    
    //
    //
    //
    _initDialog: function(){
        
            //restore from preferences    
            if(this.options.edit_mode == 'editonly'){
                this.getUiPreferences();
                if(this.options.forced_Width){
                    this.options.width = this.options.forced_Width;
                }else{
                    this.options['width']  = this.usrPreferences['width'];    
                }
                this.options['height'] = this.usrPreferences['height'];
            }
        
            this._super();
    },

    //
    //
    //
    _navigateToRec: function(dest){
        if(this._currentEditID>0){
                var recset = this.recordList.resultList('getRecordSet');
                var order  = recset.getOrder();
                var idx = order.indexOf(Number(this._currentEditID));
                idx = idx + dest;
                if(idx>=0 && idx<order.length){
                    
                    var newRecID = order[idx];
                    var that = this;
                    
                    if(this._editing.isModified()){
                        
                        var $__dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                        'Save changes and move to '+((dest<0)?'previous':'next')+' record?',
                        {'Save changes' :function(){ 
                                //save changes and go to next step
                                that._saveEditAndClose( null, function(){ that.addEditRecord(newRecID); } );
                                $__dlg.dialog( "close" );
                            },
                         'Drop changes':function(){ 
                                that._currentEditID = null;
                                that.addEditRecord(newRecID);
                                //that._initEditForm_step3(that._currentEditID)
                                $__dlg.dialog( "close" );
                            },
                         'Cancel':function(){ 
                                $__dlg.dialog( "close" );
                            }},  
                            {title:'Confirm'});
                            //,{my:'top left', at:'-100 left+200', of:this._toolbar.find('#btnPrev')});
                         var dlged = that._as_dialog.parent('.ui-dialog');   
                         $__dlg.parent('.ui-dialog').css({
                                top: dlged.position().top+dlged.height()-200,
                                left:that._toolbar.find('#btnPrev').position().left});    
                    }else if(this._toolbar) {
                        this._toolbar.find('#divNav').html( (idx+1)+'/'+order.length);
                        
                        window.hWin.HEURIST4.util.setDisabled(this._toolbar.find('#btnPrev'), (idx==0));
                        window.hWin.HEURIST4.util.setDisabled(this._toolbar.find('#btnNext'), (idx+1==order.length));
                        
                        if(dest!=0){
                            this.addEditRecord(newRecID);
                        }
                    }
                }
        }
    },    
    //override some editing methods
    
    //
    //
    //
    _getEditDialogButtons: function(){
                                    
            var that = this;        
            var btns = [];

            if(false && this.options.selectOnSave==true){ //always show standard set of buttons
                btns = [
                        {text:window.hWin.HR('Cancel'), 
                              css:{'margin-right':'15px'},
                              click: function() { 
                                  that.closeEditDialog(); 
                              }},
                        /*{text:window.hWin.HR('Cancel'), id:'btnRecCancel', 
                              css:{'visibility':'hidden','margin-right':'15px'},
                              click: function() { that.closeEditDialog(); }},*/
                       
                        {text:window.hWin.HR('Save'), id:'btnRecSaveAndClose',
                              css:{'margin-right':'15px'},
                              click: function() { that._saveEditAndClose( null, 'close' ); }}
                
                ];
            }else{
                
                if(this.options.selectOnSave==true){
                    btns = [               
                                
                        {text:window.hWin.HR('Save'), id:'btnRecSave',
                              accesskey:"S",
                              css:{'font-weight':'bold'},
                              click: function() { that._saveEditAndClose( null, 'none' ); }},
                        {text:window.hWin.HR('Save + Close'), id:'btnRecSaveAndClose',
                              css:{'margin-left':'0.5em'},
                              click: function() { that._saveEditAndClose( null, 'close' ); }},
                        {text:window.hWin.HR('Close'), 
                              css:{'margin-left':'0.5em'},
                              click: function() { 
                                  
                                /*A123  remarked since onselect triggered in onClose event  */
                                if(true || that._additionWasPerformed){
                                    that.options.select_mode = 'select_single'
                                    that.selectedRecords(that._currentEditRecordset); //it calls that._selectAndClose();
                                }else{
                                    that.closeEditDialog();   
                                }
                                  
                              }},
                        {text:window.hWin.HR('Drop Changes'), id:'btnRecCancel', 
                              css:{'margin-left':'3em'},
                              click: function() { that._initEditForm_step3(that._currentEditID) }},  //reload edit form
                              
                              
                              ];    
                }else if(this.options.edit_structure==true){
                    // Check if rectype has fields
                    btns = [ 
                        {text:window.hWin.HR('Save'), id:'btnRecSave', //AndClose
                          css:{'margin-left':'0.5em'},
                          click: function() { that._saveEditAndClose( null, 'none' ); }}, //'close'
                        {text:window.hWin.HR('Close'), 
                          click: function() { 
                            var recset = $Db.rst(that._currentEditRecTypeID);
                            var hasField = false;

                            if(!window.hWin.HEURIST4.util.isempty(recset)){

                                recset.each2(function(id, f){
                                    if($Db.dty(f.rst_ID, 'dty_Type') == 'separator'){ return; }
                                    hasField = true;
                                });
                            }

                            if(!hasField){ // check if any fields have been added to rectype

                                var btns = {};
                                btns[window.hWin.HR('Continue editing')] = function(){
                                    var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                                    $dlg.dialog('close');
                                };
                                btns[window.hWin.HR('Exit with no fields')] = function(){
                                    var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                                    $dlg.dialog('close');
                                    that.closeEditDialog();
                                };
                                window.hWin.HEURIST4.msg.showMsgDlg('You need to define fields to make this record type usable.', 
                                    btns, 
                                    {title:'No fields defined', no:window.hWin.HR('Continue editing'), yes:window.hWin.HR('Exit with no fields')},
                                    {default_palette_class: 'ui-heurist-design'}); 
                            }else{
                                that.closeEditDialog();
                            }
                          }
                        }
                    ];
                    
                }else{
                
                    btns = [       /*{text:window.hWin.HR('Reload'), id:'btnRecReload',icons:{primary:'ui-icon-refresh'},
                        click: function() { that._initEditForm_step3(that._currentEditID) }},  //reload edit form*/
                              
                              
                        {showText:false, icons:{primary:'ui-icon-circle-triangle-w'},title:window.hWin.HR('Previous'),
                              css:{'display':'none','margin-right':'0.5em',}, id:'btnPrev',
                              click: function() { that._navigateToRec(-1); }},
                        {showText:false, icons:{secondary:'ui-icon-circle-triangle-e'},title:window.hWin.HR('Next'),
                              css:{'display':'none','margin-left':'0.5em','margin-right':'1.5em'}, id:'btnNext',
                              click: function() { that._navigateToRec(1); }},
                              
                        {text:window.hWin.HR('Dupe'), id:'btnRecDuplicate',
                            css:{'margin-left':'1em'},
                            click: function(event) { 

                                function duplicate_record(){ 
                                    var btn = $(event.target);                        
                                    btn.hide();

                                    var dlged = that._getEditDialog();
                                    if(dlged) window.hWin.HEURIST4.msg.bringCoverallToFront(dlged);

                                    window.hWin.HAPI4.RecordMgr.duplicate({id: that._currentEditID}, 
                                        function(response){

                                            window.hWin.HEURIST4.msg.sendCoverallToBack();

                                            btn.css('display','inline-block');  //restore button visibility
                                            if(response.status == window.hWin.ResponseStatus.OK){
                                                window.hWin.HEURIST4.msg.showMsgFlash(
                                                    window.hWin.HR('Record has been duplicated'));
                                                var new_recID = ''+response.data.added;
                                                that._initEditForm_step3(new_recID);
                                                  
                                                var dlged = that._getEditDialog();
                                                dlged.find('.coverall-div-bare').remove();

                                            }else{
                                                window.hWin.HEURIST4.msg.showMsgErr(response);
                                            }
                                        }
                                    );
                                }

                                if(that._editing.isModified()){
                                    that._saveEditAndClose( null, duplicate_record);
                                }else{
                                    duplicate_record();
                                }
                            }},
                        {text:window.hWin.HR('New'), id:'btnRecSaveAndNew',
                              css:{'margin-left':'0.5em','margin-right':'10em'},
                              click: function() { 

                                    var isChanged = that._editing.isModified() || that._updated_tags_selection!=null;
                                    if(isChanged){
                                        that._saveEditAndClose( null, 'newrecord' );      
                                    }else{
                                        that._initEditForm_step3(-1);
                                    }
                              }},
                              
                        {text:window.hWin.HR('Save'), id:'btnRecSave',
                              accesskey:"S",
                              css:{'font-weight':'bold'},
                              click: function() { that._saveEditAndClose( null, 'none' ); }},
                        {text:window.hWin.HR('Save + Close'), id:'btnRecSaveAndClose',
                              css:{'margin-left':'0.5em'},
                              click: function() { that._saveEditAndClose( null, 'close' ); }},
                        {text:window.hWin.HR('Close'), 
                              css:{'margin-left':'0.5em'},
                              click: function() { 
                                  that.closeEditDialog(); 
                              }},
                        {text:window.hWin.HR('Drop Changes'), id:'btnRecCancel', 
                              css:{'margin-left':'3em'},
                              click: function() { that._initEditForm_step3(that._currentEditID) }},  //reload edit form
                              
                              ];
                }
                                
                //btns = btns.concat([]); 
                              
            }
            return btns;
    },
    
    //
    //
    //
    _initEditForm_step1: function(recID){
        if(this.options.edit_mode=='popup'){

            var query = null, popup_options={};
            //NEW WAY open as another widget 
            if(recID<0){
                popup_options = {selectOnSave: this.options.selectOnSave, 
                                 parententity: this.options.parententity,
                                 new_record_params:{RecTypeID:this._currentEditRecTypeID},
                                 fill_in_data: this.options.fill_in_data};
                   
                if(this.options.new_record_params){                 
                    if(this.options.new_record_params['ro']>=0 || this.options.new_record_params['ro']=='current_user') 
                            popup_options.new_record_params['OwnerUGrpID'] = this.options.new_record_params['ro'];
                    if(!window.hWin.HEURIST4.util.isempty(this.options.new_record_params['rv'])) 
                            popup_options.new_record_params['NonOwnerVisibility'] = this.options.new_record_params['rv'];
                }
                                 
                if(this.options.select_mode!='manager' && this.options.selectOnSave){ 
                    //this is select form that all addition of new record
                    //it should be closed after addition of new record
                    var that = this;
                    popup_options['onselect'] = function(event, data){
                            if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                that._trigger( "onselect", null, {selection:
                                    (that.options.select_return_mode=='recordset') ?data.selection :data.selection.getIds()});
                                that.closeDialog();
                            }
                    };
                    
                }
            }else{
                var recset = this.recordList.resultList('getRecordSet');
                if(recset && recset.length()<1000){
                    query = 'ids:'+recset.getIds().join(',');
                }else{
                    query = null;
                }
            }
            window.hWin.HEURIST4.ui.openRecordEdit( recID, query, popup_options);
            
        }else{
            this._super( recID );
        }
    },
    
    //
    // open popup edit dialog if we need it
    //
    _initEditForm_step2: function(recID){
    
        if(recID==null || this.options.edit_mode=='none') return;
        
        var isOpenAready = false;
        if(this.options.edit_mode=='popup'){
            if(this._edit_dialog){
                try{
                    isOpenAready = this._edit_dialog.dialog('isOpen');
                }catch(e){}
            }
        } else if(this.options.edit_mode=='inline') { //inline 
            isOpenAready = !this.editFormToolbar.is(':empty');
        }

        if(!isOpenAready){            
    
            var that = this; 
            this._currentEditID = (recID<0)?0:recID;
            
            if(this.options.edit_mode=='popup'){
                //OLD WAY - NOT USED
                this.editForm.css({'top': 0});//, 'overflow-y':'auto !important', 'overflow-x':'hidden'});
//this.editFormPopup = this.editForm.parent();//this.element.find('.editFormDialog');

                if(!this.options.beforeClose){
                    this.options.beforeClose = function(){
                            that.saveUiPreferences();
                    };
                }
                
                this._edit_dialog =  window.hWin.HEURIST4.msg.showElementAsDialog({
                        window:  window.hWin, //opener is top most heurist window
                        element:  this.editFormPopup[0],
                        height: this.usrPreferences.height,
                        width:  this.usrPreferences.width,
                        resizable: true,
                        //title: dialog_title,
                        buttons: this._getEditDialogButtons(),
                        //do not save prefs for popup addition beforeClose: this.options.beforeClose
                        //save only for main edit record (editonly)
                    });
                    
                //assign unique identificator to get proper position of child edit dialogs
                //this._edit_dialog.attr('posid','edit'+this._entityName+'-'+(new Date()).getTime());
                    
                //help and tips buttons on dialog header
                this._edit_dialog.addClass('manageRecords'); //need for special behaviour in applyCompetencyLevel
                
                if(this.options.helpContent){
                    var helpURL = window.hWin.HRes( this.options.entity.helpContent )+' #content';
                    window.hWin.HEURIST4.ui.initDialogHintButtons(this._edit_dialog, null, helpURL, false);    
                }
        
                this._toolbar = this._edit_dialog.parent().find('.ui-dialog-buttonpane'); //this.editFormPopup.parent();
        
            }//popup
            else { //initialize action buttons
                
                if(this.options.edit_mode=='editonly'){
                    //this is popup dialog
                   this.editFormToolbar
                     .addClass('ui-dialog-buttonpane')
                     .css({
                        padding: '0.8em 1em .2em .4em',
                        background: 'none',
                        'background-color': '#95A7B7 !important'
                     });
                }
                
                if(this.editFormToolbar && this.editFormToolbar.length>0){
                    
                    var btn_array = this._getEditDialogButtons();
                    
                    this._toolbar = this.editFormToolbar;
                    this.editFormToolbar.empty();
                    var btn_div = $('<div>').addClass('ui-dialog-buttonset').appendTo(this._toolbar);
                    for(var idx in btn_array){
                        this._defineActionButton2(btn_array[idx], btn_div);
                    }
                }
            }
            
            if(this._toolbar){
                this._toolbar.find('.ui-dialog-buttonset').css({'width':'100%','text-align':'right'});
            
                if(this._toolbar.find('#divNav3').length===0){
                        //padding-left:10em;
                        $('<div id="divNav3" style="font-weight:bold;display:inline-block;text-align:right">Save then</div>')
                            .insertBefore(this._toolbar.find('#btnRecDuplicate'));
                }
            }
            
            
            var recset = this.recordList.resultList('getRecordSet');
            if(recset && recset.length()>1 && recID>0){
                if(this._toolbar){
                    this._toolbar.find('#btnPrev').css({'display':'inline-block'});
                    this._toolbar.find('#btnNext').css({'display':'inline-block'});
                    if(this._toolbar.find('#divNav').length===0){
                        $('<div id="divNav2" style="display:inline-block;font-weight:bold;padding:0.8em 1em;text-align:right">Step through filtered subset</div>')
                        .insertBefore(this._toolbar.find('#btnPrev'));
                        
                        $('<div id="divNav" style="display:inline-block;font-weight:bold;padding-top:0.8em;min-width:40px;text-align:center">')
                        .insertBefore(this._toolbar.find('#btnNext'));
                    }
                }
                this._navigateToRec(0); //reload
            }else if(this._toolbar){
                this._toolbar.find('#btnPrev').hide();
                this._toolbar.find('#btnNext').hide();
                this._toolbar.find('#divNav').hide();
                this._toolbar.find('#divNav2').hide();
            }
            
            if(this.options.allowAdminToolbar===false){
               if(this.editFormSummary) this.editFormSummary.remove();//hide();  
               this.editFormSummary = null;
            }else
            //summary tab - specific for records only    
            if(this.editFormSummary && this.editFormSummary.length>0){    
                var layout_opts =  {
                    applyDefaultStyles: true,
                    //togglerContent_open:    '&nbsp;',
                    //togglerContent_closed:  '&nbsp;',
                    north:{
                        size: 60,
                        maxHeight:60,
                        minHeight:60,
                        initHidden:!this.options.edit_structure,
                        contentSelector: '.editStructureHeader', 
                        spacing_open: 3,
                        slidable:false,
                        resizable:false,
                        closable:false
                    },
                    west:{
                        size: this.usrPreferences.structure_width,
                        maxWidth:800,
                        minWidth:340,
                        spacing_open:6,
                        spacing_closed:40,  
                        togglerAlign_open:'center',
                        //togglerAlign_closed:'top',
                        togglerAlign_closed:16,   //top position   
                        togglerLength_closed:80,  //height of toggler button
                        initHidden: !this.options.edit_structure,   //show structure list at once 
                        initClosed: !this.options.edit_structure && (this.usrPreferences.structure_closed!=0),
                        slidable:false,  //otherwise it will be over center and autoclose
                        contentSelector: '.editStructure',   
                        onopen_start : function( ){ 
                            var tog = that.element.find('.ui-layout-toggler-west');
                            tog.removeClass('prominent-cardinal-toggler togglerVertical');
                            tog.find('.heurist-helper2.westTogglerVertical').hide();
                        },
                        onclose_end : function( ){ 
                            var tog = that.element.find('.ui-layout-toggler-west');
                            tog.addClass('prominent-cardinal-toggler togglerVertical');

                            if(tog.find('.heurist-helper2.westTogglerVertical').length > 0){
                                tog.find('.heurist-helper2.westTogglerVertical').show();
                            }else{
                                $('<span class="heurist-helper2 westTogglerVertical" style="width:200px;margin-top:220px;">Navigate / Move / Delete</span>').appendTo(tog);
                            }
                        },
                        togglerContent_open:    '<div class="ui-icon ui-icon-triangle-1-w"></div>',
                        togglerContent_closed:  '<div class="ui-icon ui-icon-carat-2-e"></div>',
                    },
                    east:{
                        size: this.usrPreferences.summary_width,
                        maxWidth:800,
                        spacing_open:6,
                        spacing_closed:40,  
                        togglerAlign_open:'center',
                        //togglerAlign_closed:'top',
                        togglerAlign_closed:16,   //top position   
                        togglerLength_closed:40,  //height of toggler button
                        initClosed:(this.usrPreferences.summary_closed==true || this.usrPreferences.summary_closed=='true'),
                        slidable:false,  //otherwise it will be over center and autoclose
                        contentSelector: '.editFormSummary',   
                        onopen_start : function(){ 
                            var tog = that.editFormPopup.find('.ui-layout-toggler-east');
                            tog.removeClass('prominent-cardinal-toggler togglerVertical');
                            tog.find('.heurist-helper2.eastTogglerVertical').hide();
                        },
                        onclose_end : function(){ 
                            var tog = that.editFormPopup.find('.ui-layout-toggler-east');
                            tog.addClass('prominent-cardinal-toggler togglerVertical');

                            if(tog.find('.heurist-helper2.eastTogglerVertical').length > 0){
                                tog.find('.heurist-helper2.eastTogglerVertical').show();
                            }else{
                                $('<span class="heurist-helper2 eastTogglerVertical" style="width:200px;">'
                                +window.hWin.HR('Record Summary')+'</span>').appendTo(tog);
                            }
                        },
                        togglerContent_open:    '<div class="ui-icon ui-icon-triangle-1-e"></div>',
                        togglerContent_closed:  '<div class="ui-icon ui-icon-carat-2-w"></div>',
                    },
                    /*south:{
                        spacing_open:0,
                        spacing_closed:0,
                        size:'3em',
                        contentSelector: '.editForm-toolbar'
                    },*/
                    center:{
                        minWidth:400,
                        contentSelector: '.editForm',
                        //pane_name, pane_element, pane_state, pane_options, layout_name
                        onresize_end : function(){
                            that.handleTabsResize();                            
                        }    
                    }
                };

                this.editFormPopup.show().layout(layout_opts); //.addClass('ui-heurist-bg-light')
                
                if(this.options.edit_structure){

                    this.editFormPopup.layout().show("north");
                    this.editFormPopup.layout().hide("east");  
                }else if(this.usrPreferences.summary_closed==true || this.usrPreferences.summary_closed=='true'){

                    var tog = that.editFormPopup.find('.ui-layout-toggler-east');
                    tog.addClass('prominent-cardinal-toggler togglerVertical');

                    if(tog.find('.heurist-helper2.eastTogglerVertical').length > 0){
                        tog.find('.heurist-helper2.eastTogglerVertical').show();
                    }else{
                        $('<span class="heurist-helper2 eastTogglerVertical" style="width:200px;">'
                            +window.hWin.HR('Record Summary')+'</span>').appendTo(tog);
                    }
                }
                
                
                //this tabs are open by default
                //if(!this.usrPreferences.summary_tabs) 
                this.usrPreferences.summary_tabs = ['0','1','2','3']; //since 2018-03-01 always open

                //load content for editFormSummary
                if(this.editFormSummary.text()=='....'){
                    this.editFormSummary.empty();

                    var headers = ['Admin','Private','Tags','Linked records','Scratchpad','Discussion']; //,'Dates','Text',
                    for(var idx in headers){
                        var acc = $('<div>').addClass('summary-accordion').appendTo(this.editFormSummary);
                        
                        $('<h3>').text(top.HR(headers[idx])).appendTo(acc);
                        //content
                        $('<div>').attr('data-id', idx).addClass('summary-content').appendTo(acc);
                        
                        acc.accordion({
                            collapsible: idx>0, //admin is always open
                            active: (idx==0 || this.usrPreferences.summary_tabs.indexOf(String(idx))>=0) ,
                            heightStyle: "content",
                            beforeActivate:function(event, ui){
                                if(ui.newPanel.text()==''){
                                    //load content for panel to be activated
                                    that._fillSummaryPanel(ui.newPanel);
                                }
                            }
                        });
                        
                        acc.find('.summary-content').removeClass('ui-widget-content');
                    }

                    // Alternative for closing east panel (record summary)
                    $('<span>', {title: 'Close summary panel'}).css({
                        height: '32px',
                        width: '32px',
                        position: 'absolute',
                        top: '13px',
                        'font-size': '32px',
                        cursor: 'pointer',
                        'z-index': 1000 // above first accordion container
                    }).addClass('closeSummaryPanel ui-icon ui-icon-carat-2-e')
                      .prependTo(this.editFormSummary);

                    this._on(this.editFormSummary.find('.closeSummaryPanel'), {
                        click: () => {
                            this.editFormPopup.layout().close("east");
                        }
                    });
                }
            }
        }//!isOpenAready

        if(this.options.edit_structure && this.options.isdialog && this._as_dialog != null && this.options.parent_dialog != null){ // move popup position
            this._as_dialog.dialog('option', 'position', {my: 'left+25 top+40', at: 'left top', of: this.options.parent_dialog});
        }

        this._initEditForm_step3(recID); 
    },
    
    
    //    
    //
    //
    handleTabsResize: function() {
            if (this._resizeTimer) clearTimeout(this._resizeTimer);
            
            if (true)  //windowHeight != $(window).height() || windowWidth != $(window).width()) 
            {
                var that = this;
                this._resizeTimer = setTimeout(
                    function(){                
                            var ele = that.editForm.find('.ui-tabs');
                            if(ele.length>0){
                                try{
      
                                    for(var i=0; i<ele.length; i++){  //
                                        $(ele[i]).tabs('pagingResize');        
                                        //$(ele[i]).tabs('pagingDestroy');
                                        //$(ele[i]).tabs('paging');
                                    }                              
                                    
                                }catch(ex){
                                }
                            }
                    }, 200);
            }
    },
    
    //
    //
    //
    _onDialogResize: function(){
        this._super();
        this._adjustResultListTop();
        this.saveUiPreferences(); // save UI settings
    },
    
    //
    //
    //
    closeEditDialog:function(){
        
        //save preferences
        var that = this;
        
        if(this.options.edit_mode=='editonly'){
            
            if(this.options.isdialog){
                if(this._as_dialog.dialog('instance')){
                    this._as_dialog.dialog('close');    
                }else{
                    //console.log('dialog is not opened');
                }
                
            }else{
                window.close(this._currentEditRecordset);
            }
            
        }else if(this._edit_dialog && this._edit_dialog.dialog('instance') && this._edit_dialog.dialog('isOpen')){
            this._edit_dialog.dialog('close');
        }
    },
    
    //
    // fill one of summary tab panels
    //
    _fillSummaryPanel: function(panel){
        
        var that = this;
        var sContent = '';
        var idx = Number(panel.attr('data-id'));
        
        var ph_gif = window.hWin.HAPI4.baseURL + 'hclient/assets/16x16.gif';
        
        panel.empty();
        
        //Admin 0, Private 1, Tags 2, Linked 3, Scratchpad 4, Discussion 5
        
        switch(idx){
            case 0:   //admins -------------------------------------------------------
       
               var sAccessGroups = '';
               if(that._getField('rec_NonOwnerVisibility')=='viewable' && that._getField('rec_NonOwnerVisibilityGroups')){
                   sAccessGroups = that._getField('rec_NonOwnerVisibilityGroups');
                   if(!$.isArray(sAccessGroups)){  sAccessGroups = sAccessGroups.split(','); }
                   var cnt = sAccessGroups.length;
                   if(cnt>0){
                       sAccessGroups = ' for '+cnt+' group'+(cnt>1?'s':'');
                   }
               }
            
                var recRecTypeID = that._getField('rec_RecTypeID');
                sContent =  
'<div style="margin:10px 4px;"><div style="padding-bottom:0.5em;display:inline-block;width: 100%;">'

+'<h3 class="truncate rectypeHeader" style="float:left;max-width:400px;margin:0 8px 0 0;">'
                + '<img src="'+ph_gif+'" class="rt-icon" style="vertical-align:top;margin-right: 10px;background-image:url(\''
                + window.hWin.HAPI4.iconBaseURL+recRecTypeID+'\');"/>'
                + $Db.rty(recRecTypeID, 'rty_Name')+'</h3>'
+'<select class="rectypeSelect ui-corner-all ui-widget-content" '
+'style="display:none;z-index: 20;position: absolute;border: 1px solid gray;'  //background:white;
+'top: 5.7em;" size="20"></select><div class="btn-modify non-owner-disable"/></div>'

/* this section is moved on top of editForm 2017-12-21
+'<div style="display:inline-block;float:right;">'   
    +'<div class="btn-config2"/><div class="btn-config"/>'  //buttons
    +'<span class="btn-config3" style="cursor:pointer;display:inline-block;float:right;color:#7D9AAA;padding:2px 4px;">Modify structure</span>'
+'</div>'
+'</div>'
*/

+'<div style="padding-bottom:0.5em;width: 100%;">'
+'<div><label class="small-header">Owner:</label><span id="recOwner">'
    +that._getField('rec_OwnerUGrpID')+'</span><div class="btn-access non-owner-disable"/>'        
+'</div>'
+'<div><label class="small-header">Access:</label><span id="recAccess">'
    + that._getField('rec_NonOwnerVisibility')
    + sAccessGroups  
    +'</span>'
+'</div></div>'

+'<div>'
+'<div class="truncate"><label class="small-header">Added By:</label><span id="recAddedBy">'+that._getField('rec_AddedByUGrpID')+'</span></div>'
+ ((that._getField('rec_Added')=='')?'':
  '<div class="truncate"    ><label class="small-header">Added:</label>'+that._getField('rec_Added')
                +' ('+window.hWin.HEURIST4.util.getTimeForLocalTimeZone(that._getField('rec_Added'))+' local)</div>')
+'<div class="truncate"><label class="small-header">Updated:</label>'+that._getField('rec_Modified')
                +' ('+window.hWin.HEURIST4.util.getTimeForLocalTimeZone(that._getField('rec_Modified'))+' local)</div>'
+'</div>'
+'</div>';



                $(sContent).appendTo(panel);
                
                //resolve user id to name
                window.hWin.HAPI4.SystemMgr.usr_names({UGrpID:that._getField('rec_AddedByUGrpID')},
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            panel.find('#recAddedBy').text(response.data[that._getField('rec_AddedByUGrpID')]);
                        }
                });

                
                
                //activate buttons
                /* moved to top of editForm 2017-12-21
                panel.find('.btn-config2').button({text:false,label:top.HR('Modify record type structure in new window'),
                        icons:{primary:'ui-icon-extlink'}})
                    .addClass('ui-heurist-btn-header1')
                    .css({float: 'right','font-size': '0.8em', height: '18px', 'margin-left':'4px'})
                    .click(function(){
                        that.editRecordTypeOnNewTab();
                    });
                    
                panel.find('.btn-config').button({text:false,label:top.HR('Modify record type structure'),
                        icons:{primary:'ui-icon-gear'}})
                    .addClass('ui-heurist-btn-header1')
                    .css({float: 'right','font-size': '0.8em', height: '18px', 'margin-left':'4px'})
                    .click(function(){that.editRecordType();});
                panel.find('.btn-config3').click(function(){that.editRecordType();});
                */
                    
                var btn_change_rt = panel.find('.btn-modify');                        
                btn_change_rt.button({text:false, label:top.HR('Change record type'),
                        icons:{primary:'ui-icon-triangle-1-s'}})
                    //.addClass('ui-heurist-btn-header1')
                    .css({float: 'left','font-size': '0.8em', height: '14px', width: '14px'})
                    .click(function(){
                         var selRt = panel.find('.rectypeSelect');
                         var selHd = panel.find('.rectypeHeader');
                         if(selRt.is(':visible')){
                             btn_change_rt.button('option',{icons:{primary:'ui-icon-triangle-1-s'}});
                             selRt.hide();
                             //selHd.css({'display':'inline-block'});
                             
                         }else{
                             btn_change_rt.button('option',{icons:{primary:'ui-icon-triangle-1-n'}});
                             selRt.show();
                             //
                             if(selRt.is(':empty')){
                                window.hWin.HEURIST4.ui.createRectypeSelect(selRt.get(0), null, null, true);    
                                selRt.change(function(){
                                    
                                    if(that._getField('rec_RecTypeID')!=selRt.val()){
                                                                         
                                    window.hWin.HEURIST4.msg.showMsgDlg(
                                        '<h4>Changing record type</h4><br>'+
                                        'Data will be re-allocated to appropriate fields, where available. '+
                                        'Not all data may fit in the new record structure, but these data '+
                                        'are retained and shown at the end of the form. No data will be lost, '+
                                        'even when the record is saved.', 
                                        function() {
                                        
                                              that._editing.assignValuesIntoRecord();
                                              var record = that._currentEditRecordset.getFirstRecord();
                                              that._currentEditRecordset.setFld(record, 'rec_RecTypeID', selRt.val());
                                              that._initEditForm_step4(null); //reload form
                                              
                                              that._editing.setModified(true); //forcefully set to modified after rt change
                                              that.onEditFormChange();
                                              
                                              selHd.html(
                        '<img src="'+ph_gif+'"  class="rt-icon" style="vertical-align:top;margin-right: 10px;background-image:url(\''
                        + window.hWin.HAPI4.iconBaseURL+selRt.val()+'\');"/>'
                        + $Db.rty(selRt.val(), 'rty_Name')                                      
                                              );
                                                                                      
                                        },
                                        {title:'Warning',yes:'Proceed',no:'Cancel'});
                                    } 
                                    btn_change_rt.button('option',{icons:{primary:'ui-icon-triangle-1-s'}});
                                    //selRt.val(-1);
                                    selRt.hide();
                                    
                                });
                             }
                             //selRt.val(recRecTypeID);
                         }
                        
                         
                    });
                    

           //
           //
           //   
           function __getUserNames(stype){
               
               if(stype=='access'){
                   sField = 'rec_NonOwnerVisibilityGroups';
                   sPanel = '#recAccess';
               }else{
                   sField = 'rec_OwnerUGrpID';
                   sPanel = '#recOwner';
               }
               
               var ele = that._editing.getFieldByName(sField);
               var vals = ele.editing_input('getValues');
               vals = vals[0];
               
               window.hWin.HAPI4.SystemMgr.usr_names({UGrpID:vals},
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            var txt = [], title = [], cnt = 0;
                            for(var ugr_id in response.data){
                                if(cnt<2){
                                    txt.push(response.data[ugr_id]);    
                                }
                                title.push(response.data[ugr_id]);
                                cnt++;
                            }
                            txt = txt.join(', ');
                            if(cnt>2){
                               txt = txt + '...'; 
                            }
                            panel.find(sPanel).text(txt).attr('title',title.join(', '));
                        }
                });
            
           }
            
                    
                    
            panel.find('.btn-access').button({text:false,label:top.HR('Change ownership and access rights'),
                        icons:{primary:'ui-icon-pencil'}})
                    //.addClass('ui-heurist-btn-header1')
                    .css({float: 'right','margin': '0 0 0.8em 7px', 'font-size': '0.8em', height: '14px', width: '14px'})
                    .click(function(){

           //
           // change ownership
           //             
           function __assignOwnerAccess(context){

               if(context && context.NonOwnerVisibility){
                    
                    var ele = that._editing.getFieldByName('rec_OwnerUGrpID');
                    var vals = ele.editing_input('getValues');
                    
                    if(vals[0]!=context.OwnerUGrpID){
                        if(context.OwnerUGrpID == 'current_user') 
                            context.OwnerUGrpID = window.hWin.HAPI4.user_id();
                        
                        ele.editing_input('setValue',[context.OwnerUGrpID]);
                        ele.editing_input('isChanged', true);
                        
                        //update user name
                        __getUserNames('owner');
                    }

                    ele = that._editing.getFieldByName('rec_NonOwnerVisibility');
                    vals = ele.editing_input('getValues');
                    if(vals[0]!=context.NonOwnerVisibility){
                        ele.editing_input('setValue',[context.NonOwnerVisibility]);
                        ele.editing_input('isChanged', true);
                        panel.find('#recAccess').html(context.NonOwnerVisibility);
                    }
                    
                    //change visibility per group
                    ele = that._editing.getFieldByName('rec_NonOwnerVisibilityGroups');
                    vals = ele.editing_input('getValues');
                    if(vals[0]!=context.NonOwnerVisibilityGroups){
                        //update usrRecPermissions
                        ele.editing_input('setValue',[context.NonOwnerVisibilityGroups]);
                        ele.editing_input('isChanged', true);
                        if(context.NonOwnerVisibility=='viewable') __getUserNames('access');
                    }
                    
                    that.onEditFormChange();
                }
                
        }                        
                        
        //show dialog that changes ownership and view access                   
        window.hWin.HEURIST4.ui.showRecordActionDialog('recordAccess', {
               currentOwner:  that._getField('rec_OwnerUGrpID'),
               currentAccess: that._getField('rec_NonOwnerVisibility'),
               currentAccessGroups: that._getField('rec_NonOwnerVisibilityGroups'),
               scope_types: 'none', onClose: __assignOwnerAccess,
               height:400,
               default_palette_class: 'ui-heurist-populate'
        });
              
                    }); //on edit ownership click
            
            
            __getUserNames('owner');
            if(that._getField('rec_NonOwnerVisibility')=='viewable') __getUserNames('access');
            
  
                break;
                
            case 3:   //find all reverse links
            
                var relations = that._currentEditRecordset.getRelations();    
                
                if(!relations) break;
                
                var direct = relations.direct;
                var reverse = relations.reverse;
                var headers = relations.headers;
                var ele1=null, ele2=null;
                
                //direct relations                            
                var sRel_Ids = [];
                for(var k in direct){
                    if(direct[k]['trmID']>0){ //relation    
                        var targetID = direct[k].targetID;
                        
                        if(!headers[targetID]){
                            //there is not such record in database
                            continue;                                            
                        }
                        
                        sRel_Ids.push(targetID);
                        
                        if(sRel_Ids.length<25){
                            var ele = window.hWin.HEURIST4.ui.createRecordLinkInfo(panel, 
                                {rec_ID: targetID, 
                                 rec_Title: headers[targetID][0], 
                                 rec_RecTypeID: headers[targetID][1], 
                                 relation_recID: direct[k]['relationID'], 
                                 trm_ID: direct[k]['trmID'],
                                 dtl_StartDate: direct[k]['dtl_StartDate'],
                                 dtl_EndDate: direct[k]['dtl_EndDate']
                                }, true);
                            if(!ele1) ele1 = ele;     
                        }
                    }
                }
                //reverse relations
                for(var k in reverse){
                    if(reverse[k]['trmID']>0){ //relation    
                        var sourceID = reverse[k].sourceID;
                        if(!headers[sourceID]){
                            //there is not such record in database
                            continue;                                            
                        }
                        
                        sRel_Ids.push(sourceID);
                        
                        if(sRel_Ids.length<25){
                        
                            var invTermID = window.hWin.HEURIST4.dbs.getInverseTermById(reverse[k]['trmID']);
                            
                            var ele = window.hWin.HEURIST4.ui.createRecordLinkInfo(panel, 
                                {rec_ID: sourceID, 
                                 rec_Title: headers[sourceID][0], 
                                 rec_RecTypeID: headers[sourceID][1], 
                                 relation_recID: reverse[k]['relationID'], 
                                 trm_ID: invTermID,
                                 dtl_StartDate: reverse[k]['dtl_StartDate'],
                                 dtl_EndDate: reverse[k]['dtl_EndDate']
                                }, true);
                            if(!ele1) ele1 = ele;     
                        }
                    }
                }
                if(sRel_Ids.length>25){
                    $('<div><a href="'
                    +window.hWin.HAPI4.baseURL + '?q='
                    +encodeURIComponent('{"related":{"ids":'+that._currentEditID+'}}')
                    +'&db='+window.hWin.HAPI4.database
                    +'&nometadatadisplay=true" target="_blank">more ('+(sRel_Ids.length-25)
                    +'<span class="ui-icon ui-icon-extlink" style="font-size:0.8em;top:2px;right:2px"></span>'
                    +') </a></div>').appendTo(panel);
                }
                
                //reverse links
                var sLink_Ids = [];
                for(var k in reverse){
                    if(!(reverse[k]['trmID']>0)){ //links    
                        var sourceID = reverse[k].sourceID;
                        
                        if(!headers[sourceID]){
                            //there is not such record in database
                            continue;                                            
                        }
                        
                        sLink_Ids.push(sourceID);
                        
                        if(sLink_Ids.length<25){
                        
                            var ele = window.hWin.HEURIST4.ui.createRecordLinkInfo(panel, 
                                {rec_ID: sourceID, 
                                 rec_Title: headers[sourceID][0], 
                                 rec_RecTypeID: headers[sourceID][1]
                                }, true);
                            if(!ele2) ele2 = ele;   
                        }  
                    }
                }
                if(sLink_Ids.length>25){
                    $('<div><a href="'
                    +window.hWin.HAPI4.baseURL + '?q='
                    +encodeURIComponent('{"linked_to":{"ids":'+that._currentEditID+'}}')
                    +'&db='+window.hWin.HAPI4.database
                    +'&nometadatadisplay=true" target="_blank">more ('+(sLink_Ids.length-25)
                    +'<span class="ui-icon ui-icon-extlink" style="font-size:0.8em;top:2px;right:2px"></span>'
                    +') </a></div>').appendTo(panel);
                }
                
                
                //insert headers
                if(sRel_Ids.length>0){
                    $('<div class="detailRowHeader" style="border:none">Related</div>').css('border','none').insertBefore(ele1);
                }
                //prevent wrapping
                //2021-01-21 $(ele1).find('.related_record_title').addClass('truncate').css({'max-width':'44ex'});
                if(sLink_Ids.length>0){
                    var ee2 = $('<div class="detailRowHeader">Linked from</div>').insertBefore(ele2);
                    if(sRel_Ids.length==0){
                        ee2.css('border','none')
                    }
                }
                //prevent wrapping
                //2021-01-21 $(ele2).find('.related_record_title').addClass('truncate').css({'max-width':'44ex'});
                
                
                if(sRel_Ids.length==0 && sLink_Ids.length==0){
                    $('<div class="detailRowHeader">none</div>').css('border','none').appendTo(panel);
                }
                

                panel.css({'font-size':'0.9em','line-height':'1.5em','overflow':'hidden !important', margin:'10px 0'});
                panel.find('.link-div').css({background:'none',border:'none'});
                            
/*            
                window.hWin.HAPI4.RecordMgr.search_related({ids:this._currentEditID}, //direction: -1}, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                                    
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    }
                );
*/                
                break;
                
            case 4:   //scrtachpad
            
                //find field in hEditing
                var ele = that._editing.getFieldByName('rec_ScratchPad');
                ele.editing_input('option',{showclear_button:false, show_header:false});
                ele[0].parentNode.removeChild(ele[0]);                
                ele.css({'display':'block','width':'99%'});
                ele.find('textarea').attr('rows', 10).css('width','90%');
                ele.show().appendTo(panel);
                
                break;
                
            case 1:   //private
            
                if(panel.text()!='') return;
                
                panel.append('<div class="bookmark" style="min-height:2em;padding:4px 2px 4px 0;vertical-align:top"/>'
                +'<div class="reminders truncate" style="min-height:2em;padding:4px 30px 4px 0;border-top: 1px lightgray solid;"/>');
                
                //find bookmarks and reminders
                that._renderSummaryBookmarks(null, panel);
                that._renderSummaryReminders(null, panel);
            
            
                break;
            case 2:   //tags
            
                if(panel.text()!='') return;
                panel.text('requesting....');
            
                var request = {};
                request['a']          = 'search'; //action
                request['entity']     = 'usrTags';
                request['details']    = 'id';
                request['request_id'] = window.hWin.HEURIST4.util.random();
                request['rtl_RecID']  = this._currentEditID;
                
                var that = this;                                                
                
                //at first we have to search tags that are already assigned to current record
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            panel.empty();
                            var recs = (response.data && response.data.records)?response.data.records:[];
                            
                            window.hWin.HEURIST4.ui.showEntityDialog('usrTags', {
                                    refreshtags:true, 
                                    isdialog: false,
                                    container: panel,
                                    select_mode:'select_multi', 
                                    layout_mode: '<div class="recordList"/>',
                                    list_mode: 'compact', //special option for tags
                                    selection_ids: recs, //already selected tags
                                    select_return_mode:'recordset', //ids by default
                                    onselect:function(event, data){
                                        if(data && data.selection){
                                            that._updated_tags_selection = data.selection;
                                            that.onEditFormChange();
                                        }
                                    }
                            });
                        }
                    });
                
            
                break;
            case 5:   //discussion
                if(panel.text()!='') return;
                
                sContent = '<p>Contact Heurist team if you need this function</p>';
                break;
            case 6:   //dates - moved back to admin section (2017-10-31)
                
 $('<div><label class="small-header">Added By:</label><span id="recAddedBy">'+that._getField('rec_AddedByUGrpID')+'</span></div>'
+'<div><label class="small-header">Added:</label>'+that._getField('rec_Added')+'</div>'
+'<div><label class="small-header">Updated:</label>'+that._getField('rec_Modified')+'</div>').appendTo(panel);

            //resolve user id to name
            window.hWin.HAPI4.SystemMgr.usr_names({UGrpID:that._getField('rec_AddedByUGrpID')},
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        panel.find('#recAddedBy').text(response.data[that._getField('rec_AddedByUGrpID')]);
                    }
            });

                break;
            default:
                sContent = '<p>to be implemented</p>';
        }

        if(idx>1 && sContent) $(sContent).appendTo(panel);
        if(idx>0 && idx<6){
            panel.css({'margin-left':'27px'});
        }
        
    },
    
    //
    //
    //
    _renderSummaryReminders: function(recordset, panel){
        
            var that = this, sContent = '',
                pnl = panel.find('.reminders');
                
            pnl.empty().css({'font-size': '0.9em'});
        
            if(recordset==null){
                
                var request = {};
                request['rem_RecID']  = this._currentEditID;
                request['a']          = 'search'; //action
                request['entity']     = 'usrReminders';
                request['details']    = 'name';
                request['request_id'] = window.hWin.HEURIST4.util.random();
                var that = this;                                                
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            var recordset = new hRecordSet(response.data);
                            that._renderSummaryReminders(recordset, panel);
                        }
                    });        
      
                return;
            }
        
            if(recordset.length()==0){
                sContent = '<i>no reminders</i>';
            }else{
                
                var rec = recordset.getFirstRecord();
                var val = recordset.fld(rec, 'rem_ToWorkgroupID');
                if(val){
                    sContent = val;
                }else{
                    val = recordset.fld(rec, 'rem_ToUserID');    
                    if(val) {
                        sContent = val;
                    } else{
                        sContent = recordset.fld(rec, 'rem_ToEmail');    
                    }
                }
                val = recordset.fld(rec, 'rem_Message');
                if(val.length>150){
                    val = val.substring(0,150)+'...';
                }
                sContent = 'Reminder to: '+sContent+' '+val;
                //sContent = 'found :'+recordset.length();
            }
            pnl.append(sContent);

            //append/manage button
            $('<div>').button({label:top.HR('Manage reminders'), text:false,
                icons:{primary:'ui-icon-pencil'}})  //ui-icon-mail
                .css({position:'absolute',right:'13px', height: '18px'})
                .addClass('non-owner-disable')
                .click(function(){
                        window.hWin.HEURIST4.ui.showEntityDialog('usrReminders', {
                                edit_mode: 'editonly',
                                rem_RecID: that._currentEditID,
                                height: 500,
                                default_palette_class: that.options.default_palette_class,
                                onClose:function(){
                                    //refresh
                                    that._renderSummaryReminders(null, panel);
                                }
                        });
                })
             .appendTo(pnl);        
            
    },
    
    //
    //
    //
    _renderSummaryBookmarks: function(recordset, panel){

            var that = this, sContent = '',
                pnl = panel.find('.bookmark');
                
            pnl.empty().css({'font-size': '0.9em'});

            //append/manage button
            $('<div>').button({label:top.HR('Manage bookmark info'), text:false,
                icons:{primary:'ui-icon-pencil'}})  //ui-icon-bookmark
                .addClass('non-owner-disable')
                .css({float: 'right', height: '18px'}) //position:'absolute',right:'13px',
                .click(function(){
                    
                        window.hWin.HEURIST4.ui.showEntityDialog('usrBookmarks', {
                                bkm_RecID: that._currentEditID,
                                height:410,
                                default_palette_class: 'ui-heurist-populate',
                                onClose:function(){
                                    //refresh
                                    that._renderSummaryBookmarks(null, panel);
                                }
                                
                        });
                })
             .appendTo(pnl);        
        
            if(recordset==null){
                
                var request = {};
                request['bkm_RecID']  = this._currentEditID;
                request['a']          = 'search'; //action
                request['entity']     = 'usrBookmarks';
                request['details']    = 'name';
                request['request_id'] = window.hWin.HEURIST4.util.random();
                var that = this;                                                
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            var recordset = new hRecordSet(response.data);
                            that._renderSummaryBookmarks(recordset, panel);
                        }
                    });        
                return;
            }
        
            //rating and password reminder
            if(recordset.length()==0){
                sContent = '<i>not bookmarked (no passwords, rating or notes)</i>';
            }else{
                var rec = recordset.getFirstRecord();
                var val = recordset.fld(rec, 'bkm_Rating');
                sContent = 'Rating: '+((val>0) ?'*'.repeat(val) :''); 
                val = window.hWin.HEURIST4.util.htmlEscape(recordset.fld(rec, 'bkm_PwdReminder'));
                sContent += '<br>&nbsp;&nbsp;&nbsp;Pwd: '+((!window.hWin.HEURIST4.util.isempty(val))?val:''); 
                val = window.hWin.HEURIST4.util.htmlEscape(recordset.fld(rec, 'bkm_Notes'));
                sContent += '<br>&nbsp;Notes: '+((!window.hWin.HEURIST4.util.isempty(val))
                        ?(val.substr(0,500)+(val.length>500?'...':'')):''); 
                
            }
            pnl.append(sContent);
            
    },
    //
    // NOT USED anymore TO REMOVE
    //
    _renderSummaryTags: function(recordset, panel){
        
            var that = this, idx, isnone=true;
            
            panel.empty().css({'font-size': '0.9em'});
            
            $('<div><i style="display:inline-block;">Personal:&nbsp;</i></div>')
                .css({'padding':'2px 4px 16px 4px'})
                .attr('data-id', window.hWin.HAPI4.currentUser['ugr_ID'])
                .hide().appendTo(panel);
            
            //render group divs
            for (var groupID in window.hWin.HAPI4.currentUser.ugr_Groups)
            if(groupID>0){
                var name = window.hWin.HAPI4.sysinfo.db_usergroups[groupID];
                if(!window.hWin.HEURIST4.util.isnull(name)){
                        $('<div><i style="display:inline-block;">'+name+':&nbsp;</i></div>')
                            .css({'padding':'0 2 4 2px'})
                            .attr('data-id', groupID).hide().appendTo(panel);
                }
            }
            
            var records = recordset.getRecords();
            var order = recordset.getOrder();
            var recID, label, groupid, record, grp;
            
            for (idx=0;idx<order.length;idx++){
                recID = order[idx];
                if(recID && records[recID]){
                    record = records[recID];
                    label = recordset.fld(record,'tag_Text');
                    groupid = recordset.fld(record,'tag_UGrpID');
                 
                    grp = panel.find('div[data-id='+groupid+']').show();
                    $('<a href="'
                         + window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&q=tag:'+label
                         + '&nometadatadisplay=true" target="_blank" style="display:inline-block; padding-right:4px">'+label+'</a>')
                         .appendTo(grp);
                   
                   isnone = false;  
                }
            }
            
            
            if(isnone){
                $('<div class="detailRowHeader">none</div>').appendTo(panel);
            }
            
            //append manage button
            $('<div>').button({label:top.HR('Manage record tags'), text:false,
                icons:{primary:'ui-icon-tag'}})
                .addClass('ui-heurist-btn-header1')
                .css({float:'right', height: '18px'})
                .click(function(){
                    
                        /*
                        this.usrPreferences = window.hWin.HAPI4.get_prefs_def('prefs_'+this._entityName, this.defaultPrefs);
                        this.options['width']  = this.usrPreferences['width'];
                        this.options['height'] = this.usrPreferences['height'];
                        */
                    
                        window.hWin.HEURIST4.ui.showEntityDialog('usrTags', {
                                width: 800,
                                height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95,
                                select_mode:'select_multi', 
                                selection_ids:order,
                                select_return_mode:'recordset', //ids by default
                                onselect:function(event, data){
                                    if(data && data.selection){
                                        //assign new set of tags to record
                                        
                                        var request = {};
                                        request['a']       = 'batch'; //batch action
                                        request['entity']  = 'usrTags';
                                        request['mode']    = 'replace';
                                        request['tagIDs']  = data.selection.getOrder();
                                        request['recIDs']  = that._currentEditID;
                                        request['request_id'] = window.hWin.HEURIST4.util.random();
                                        
                                        window.hWin.HAPI4.EntityMgr.doRequest(request, 
                                            function(response){
                                                if(response.status == window.hWin.ResponseStatus.OK){
                                                }
                                            });
                                        //update panel
                                        //that._renderSummaryTags(data.selection, panel);
                                    }
                                }
                        });
                })
             .appendTo(panel);        
                             
    },


    //
    // Open Edit record structure on new tab - NOT USED
    //
    editRecordTypeOnNewTab: function(){

        var that = this;
        
        var smsg = "<p>Changes made to the record type will not become active until you reload this page (hit page reload in your browser).</p>";
        
        if(this._editing.isModified()){
            var smsg = smsg + "<br/>Please SAVE the record first in order not to lose data";
        }
        window.hWin.HEURIST4.msg.showMsgDlg(smsg);

        var url = window.hWin.HAPI4.baseURL + 'admin/adminMenuStandalone.php?db='
            +window.hWin.HAPI4.database
            +'&mode=rectype&rtID='+that._currentEditRecTypeID;
        window.open(url, '_blank');
    },

    //
    //
    //
    editRecordTypeTitle: function(){
        
        var that = this;
        var rty_ID = this._currentEditRecTypeID;
        var maskvalue = $Db.rty(rty_ID, 'rty_TitleMask')

        this.element.css('cursor', 'wait');
        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, null, '<span style="color: white;">Preparing record type title editor...</span>');

        window.hWin.HEURIST4.ui.showRecordActionDialog('rectypeTitleMask',
                {rty_ID:this._currentEditRecTypeID, rty_TitleMask:maskvalue, path: 'widgets/entity/popups/',
                 onClose: function(newvalue){},
                 onInitFinished: function(){ 
                    that.element.css('cursor', ''); 
                    window.hWin.HEURIST4.msg.sendCoverallToBack(that.element); 
                 }
                });
    },

    //
    //
    //
    editRecordTypeAttributes: function(){
        
        var that = this;
        var rty_ID = this._currentEditRecTypeID;
        
        if(this._editing.isModified()){  //2020-12-06 !this.options.edit_structure && 
            
                var sMsg = "Click YES to save changes and modify the record type attributes";
                window.hWin.HEURIST4.msg.showMsgDlg(sMsg, function(){
                    
                        that.saveQuickWithoutValidation( function(){ //save without validation
                            that._editing.initEditForm(null, null); //clear edit form
                            that._initEditForm_step3(that._currentEditID); //reload edit form                       
                            that.editRecordTypeAttributes();
                        } );

                }, {title:'Data has been modified', yes:window.hWin.HR('Yes') ,no:window.hWin.HR('Cancel')});   
                return;                           
        }

        
        var popup_options = {
                select_mode: 'manager',
                edit_mode: 'editonly', //only edit form is visible, list is hidden
                rec_ID: rty_ID,
                selectOnSave: true,
                suppress_edit_structure: true,
                height: 820,
                onClose: function(){
                    //refresh icon, title, mask
                    that._initEditForm_step3(that._currentEditID);
                }
            };
        window.hWin.HEURIST4.ui.showEntityDialog('defRecTypes', popup_options);
        
    },
    
    //
    //
    //
    closeWestPanel: function(){
        this.editFormPopup.layout().close("west");  
    },
    
    //
    //
    //
    editRecordType: function(is_inline){

        var that = this;
        
        if(!this.options.edit_structure && this._editing.isModified()){

            var $dlg = null;

            var sMsg = "Click Save changes to save changes and modify the record structure.<br>"
                        +"Or click Drop changes to continue straight to modifing the record structure.<br><br>"
                        +"If you are unable to save changes or drop changes, click Cancel and open<br>"
                        +"structure modification in main menu Structure > Modify / Extend";

            var btns = {};
            btns[window.hWin.HR('Save changes')] = function(){
                that.saveQuickWithoutValidation( function(){ //save without validation

                    $dlg.dialog('close');

                    that._editing.initEditForm(null, null); //clear edit form
                    that._initEditForm_step3(that._currentEditID); //reload edit form                       
                    that.editRecordType(is_inline);
                } );
            };
            btns[window.hWin.HR('Drop changes')] = function(){

                $dlg.dialog('close');

                that._initEditForm_step3(that._currentEditID); //reload edit form                       
                that.editRecordType(is_inline);
            };
            btns[window.hWin.HR('Cancel')] = function(){
                $dlg.dialog('close');
            };
            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(sMsg, btns, 
                {title:'Data has been modified', yes:window.hWin.HR('Save changes'), ok: window.hWin.HR('Drop changes'), cancel:window.hWin.HR('Cancel')},
                {default_palette_class: 'ui-heurist-populate'});

            return;
        }
        
        if( is_inline ){
            
            this._reloadRtsEditor();
            //show and expand left hand panel 
            var isClosed = (!this.options.edit_structure && this.usrPreferences.structure_closed!=0);
            this.editFormPopup.layout().show('west', !isClosed ); 
            if(isClosed){
                var tog = that.editFormPopup.find('.ui-layout-toggler-west');
                tog.addClass('prominent-cardinal-toggler togglerVertical');

				if(tog.find('.heurist-helper2.westTogglerVertical').length > 0){
                    tog.find('.heurist-helper2.westTogglerVertical').show();
                }else{
                    $('<span class="heurist-helper2 westTogglerVertical" style="font-size:17px;width:200px;margin-top:220px;">Navigate / Move / Delete</span>').appendTo(tog);
                }
            }
                
        }

        
    },
    
    //
    //
    //
    _initEditForm_step3: function(recID, is_insert){
        
        //fill with values
        this._currentEditID = recID;
        
        var that = this;
        
        //clear content of accordion
        if(this.editFormSummary && this.editFormSummary.length>0){
            this.editFormSummary.find('.summary-content').empty();
            //this.editFormSummary.accordion({active: false});
        }

        var record_stub = {};
        if(false && that.options.edit_structure){ //2020-12-06  returns fake record to edit structure only
            record_stub = {"status":"ok","is_insert":true,
                        "data":{"entityName":"Records","count":"1","reccount":1,
                        "fields":["rec_ID","rec_URL","rec_RecTypeID","rec_Title",
                        "rec_OwnerUGrpID","rec_NonOwnerVisibility","rec_Modified","rec_Added","rec_AddedByUGrpID","rec_ScratchPad",
                        "rec_NonOwnerVisibilityGroups"],
                        "fields_detail":[],
                        "records":{"3256":["3256",null,that.options.new_record_params.RecTypeID,"","2","public","2020-08-19 13:53:41",
                        "2020-08-19 13:53:41","2",null,null]},
                        "order":["3256"],
                        "rectypes":[that.options.new_record_params.RecTypeID],
                        //"limit_warning":false,"memory_warning":null,
                        //"relations":{"direct":[],"reverse":[],"headers":{"3256":["","56","2","hidden"]}}},
                        //"request_code":{"script":"record_edit","action":"a"}
                        }};
        }
        
        if(recID==null){
            this._editing.initEditForm(null, null); //clear and hide
        }else if(recID>0){ //edit existing record  - load complete information - full file info, relations, permissions
        
            if(false && that.options.edit_structure){ //2020-12-06
                
                that._initEditForm_step4(record_stub);
                
            }else{
                window.hWin.HAPI4.RecordMgr.search({q: 'ids:'+recID, w: "e", f:"complete", l:1}, 
                            function(response){ response.is_insert = (is_insert==true); 
                                                that._initEditForm_step4(response); });
                
            }
        

        }else if(recID<0){ //add new record
        
            if(!that.options.new_record_params){
                that.options.new_record_params = {};  
            }else{ 
                //short version of new record params (backward capability)
                if(that.options.new_record_params['rt']>0){ 
                    that.options.new_record_params['RecTypeID'] = that.options.new_record_params['rt'];
                    that.options.new_record_params['rt'] = null;
                }
                if(that.options.new_record_params['ro']>=0 || that.options.new_record_params['ro']=='current_user'){
                    that.options.new_record_params['OwnerUGrpID'] = that.options.new_record_params['ro'];    
                    that.options.new_record_params['ro'] = null;
                }
                if(!window.hWin.HEURIST4.util.isempty(that.options.new_record_params['rv'])){
                    that.options.new_record_params['NonOwnerVisibility'] = that.options.new_record_params['rv'];
                    that.options.new_record_params['rv'] = null;
                }
                        
                if(!window.hWin.HEURIST4.util.isempty(that.options.new_record_params['url']))  
                        that.options.new_record_params['URL'] = that.options.new_record_params['url'];
                if(!window.hWin.HEURIST4.util.isempty(that.options.new_record_params['desc'])) 
                        that.options.new_record_params['ScratchPad'] = that.options.new_record_params['desc'];
            }
        
            if(that._currentEditRecTypeID>0){
                that.options.new_record_params['RecTypeID'] = that._currentEditRecTypeID;
            }        
            
            that.options.new_record_params['ID'] = 0;
            that.options.new_record_params['FlagTemporary'] = 1;
            that.options.new_record_params['no_validation'] = 1;
            
            if(that.options.new_record_params['Title'] && window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME']>0){
               that.options.new_record_params['details'] = {};
               that.options.new_record_params['details'][window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME']] = that.options.new_record_params['Title']; 
            }
            
            var is_thumbnail_generation = false;    
            if(that.options.new_record_params['URL'] && window.hWin.HAPI4.sysinfo['dbconst']['DT_THUMBNAIL']>0){
               if(!that.options.new_record_params['details']) that.options.new_record_params['details'] = {};
               that.options.new_record_params['details'][window.hWin.HAPI4.sysinfo['dbconst']['DT_THUMBNAIL']] = 'generate_thumbnail_from_url';
               is_thumbnail_generation = true;
            }
            
            //
            // create new temporary record to be edited
            //
            function __onAddNewRecord( force_proceeed ){
                
                //if new record is relationship - show warning
                if(force_proceeed!==true &&  that.options.edit_structure!==true &&
                    that.options.new_record_params['RecTypeID']==window.hWin.HAPI4.sysinfo['dbconst']['RT_RELATION']){

                    var params = window.hWin.HAPI4.get_prefs_def('prefs_'+that._entityName, that.defaultPrefs);

                    if(params['show_warn_about_relationship']!==false){


                        var $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                            '<p>We do not recommend creating relationship records directly. They are better created through Relationship Marker fields defined within the connected record types.</p>'       
                            +'<p>Go to Design Menu to add Relationship Markers to the record types you wish to connect.</p>'
                            +'<p>Relationship Marker fields have three important advantages:</p><ol>'
                            +'<li>They constrain the types of record which can be connected and the types of relationship to be used in each connection. For instance, Person isAuthorOf Work, Person isChildOf Person, but not Person isAuthorOf Person.</li>'
                            +'<li>They contextualise entry of relationships within the data entry forms streamlining data entry and encouraging well-structured data (including making relationships required and/or singular in certain contexts).</li>'
                            +'<li>They guide the creation of complex queries through "wizards" which use the information provided to structure their pathway. Without them, much of the power of Heurist retrievals is lost; constructed titles, facet filters and custom reports can only use relationships defined in this way.</li></ol>'
                            +'<br><br><label><input type="checkbox"> Do not show this message again</label> (current login session)'                    
                            ,
                            [
                                {text:'Design menu', css:{'margin-right':'200px'}, click: function(){
                                    $dlg.dialog( "close" ); 
                                    that.closeEditDialog();
                                    //open design menu
                                    window.hWin.HAPI4.LayoutMgr.executeCommand('mainMenu', 'menuActionById', 'menu-structure-rectypes');
                                }},
                                {text:'Create relationship record', click: function(){ 
                                    $dlg.dialog( "close" );                    
                                    //__onAddNewRecord( true );
                                }},
                                {text:'Cancel', click: function(){ $dlg.dialog( "close" ); that.closeEditDialog(); }}

                            ],{  title:'Creation of relationship record' }        
                        );

                        var chb = $dlg.find('input[type="checkbox"]').change(function(){
                            var params = window.hWin.HAPI4.get_prefs_def('prefs_'+that._entityName, that.defaultPrefs);
                            params['show_warn_about_relationship'] = false;
                            window.hWin.HAPI4.save_pref('prefs_'+that._entityName, params);     

                        });

                    }                            

                }
                
                
                
                if(that.options.new_record_params['OwnerUGrpID']=='current_user') {
                    that.options.new_record_params.OwnerUGrpID = window.hWin.HAPI4.user_id();
                }
                

                if(false && that.options.edit_structure){ //2020-12-06 returns fake record to edit structure only
/*
                        var response = {"status":"ok","data":{"queryid":null,"pageno":null,"entityName":"Records","count":"1","offset":0,"reccount":1,"tmcount":0,
                        "fields":["rec_ID","rec_URL","rec_RecTypeID","rec_Title","rec_OwnerUGrpID","rec_NonOwnerVisibility","rec_Modified","bkm_PwdReminder","rec_Added","rec_AddedByUGrpID","rec_ScratchPad","bkm_Rating","rec_ThumbnailURL",
                        "rec_NonOwnerVisibilityGroups"],
                        "fields_detail":[],
                        "records":{"3256":["3256",null,that.options.new_record_params.RecTypeID,"","2","public","2020-08-19 13:53:41",null,"2020-08-19 13:53:41","2",null,null,null]},
                        "order":["3256"],
                        "rectypes":[that.options.new_record_params.RecTypeID],
                        //"limit_warning":false,"memory_warning":null,
                        //"relations":{"direct":[],"reverse":[],"headers":{"3256":["","56","2","hidden"]}}},
                        //"request_code":{"script":"record_edit","action":"a"}
                        }};
*/
                      that._initEditForm_step4(record_stub); //it returns full record data
                      that.editRecordType(true);


                }else if(that.options.new_record_params['details']){                     
                    //need to use save because method "add" inserts only header
                    
                    var msg = null;
                    if(is_thumbnail_generation){
                        msg = window.hWin.HR('generating thumbnail');
                    }
                    var dlged = that._getEditDialog();
                    if(dlged) window.hWin.HEURIST4.msg.bringCoverallToFront(dlged,null,msg);
                    
                    window.hWin.HAPI4.RecordMgr.saveRecord( that.options.new_record_params,
                        function(response){ 
                                window.hWin.HEURIST4.msg.sendCoverallToBack();
                            
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    response.is_insert=true; 
                                    that._initEditForm_step3(response.data, true); //it returns new record id only
                                    if(that.options.edit_structure) that.editRecordType(true); //2020-12-06 added
                                } else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                }
                                // for  window.hWin.HAPI4.RecordMgr.addRecord that._initEditForm_step4(response); 
                        });
                }else{
                    window.hWin.HAPI4.RecordMgr.addRecord( that.options.new_record_params,
                        function(response){ 
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    response.is_insert=true; 
                                    that._initEditForm_step4(response); //it returns full record data
                                    if(that.options.edit_structure) that.editRecordType(true); //2020-12-06 added
                                }else{
                                    that.closeDialog();
                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                }
                        });
                }
            }
                    
            
            if(!(that.options.new_record_params['RecTypeID']>0)){
                
                //record type not defined - show popup with rectype selection
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordAdd',{
                    onClose: function(context){
                        if(context && context.RecTypeID>0){
                            
                            that._currentEditRecTypeID = context.RecTypeID;
                            that.options.new_record_params =  context;
                            __onAddNewRecord();    
                        }else{
                             that.closeDialog();
                        }
                            
                    },
                    default_palette_class: 'ui-heurist-populate'
                });

            }else{
                
                //default values for ownership and viewability from preferences
                var add_rec_prefs = window.hWin.HAPI4.get_prefs('record-add-defaults');
                var usr_id = window.hWin.HAPI4.user_id();
                if(!$.isArray(add_rec_prefs) || add_rec_prefs.length<4){
                    add_rec_prefs = [0, usr_id, 'viewable', '']; //rt, owner, access, tags  (default to Current user)
                }
                if(add_rec_prefs.length<5){
                    add_rec_prefs.push(''); //groups visibility
                }
                
                if (that.options.new_record_params.OwnerUGrpID=='current_user') {
                    that.options.new_record_params.OwnerUGrpID = usr_id;
                }else if(that.options.new_record_params.OwnerUGrpID < 0){
                    that.options.new_record_params.OwnerUGrpID = add_rec_prefs[1];    
                } 
                if (!(window.hWin.HAPI4.is_admin() || window.hWin.HAPI4.is_member(that.options.new_record_params.OwnerUGrpID))) {
                    that.options.new_record_params.OwnerUGrpID = usr_id; //default to current user   
                }
                if(window.hWin.HEURIST4.util.isempty(that.options.new_record_params.NonOwnerVisibility)){
                    that.options.new_record_params.NonOwnerVisibility = add_rec_prefs[2];
                }

                if(that.options.new_record_params.NonOwnerVisibility=='viewable' &&
                !that.options.new_record_params.NonOwnerVisibilityGroups || that.options.new_record_params.NonOwnerVisibilityGroups < 0){

                    that.options.new_record_params.NonOwnerVisibilityGroups = add_rec_prefs[4];
                }
                
                __onAddNewRecord();
            }
        }

        return;
    },
    
    //
    // apparently it should be moved to dbs?  - @TODO replace with $Db.rst_links
    //
    __findParentRecordTypes: function(childRecordType){

        var parentRecordTypes = $Db.rst_links().parents[childRecordType];
        /*
        childRecordType = ''+childRecordType; //must be strig otherwise indexOf fails
        
        var all_structs = $Db.rst_idx2();
        for (var rty_ID in all_structs){
            var recset = all_structs[rty_ID];
            recset.each2(function(dty_ID, record){
                
                if(record['rst_CreateChildIfRecPtr']==1){
                    var fieldtype = $Db.dty(dty_ID, 'dty_Type');
                    var constraint = $Db.dty(dty_ID, 'dty_PtrTargetRectypeIDs');
                    if(fieldtype=='resource' && constraint && constraint.split(',').indexOf((childRecordType))>=0){
                            if(parentRecordTypes.indexOf(record['rst_RecTypeID'])<0)  
                                    parentRecordTypes.push(record['rst_RecTypeID']);
                            //return false;
                    }
                }
            });
        }
        */
        return parentRecordTypes;
    },
    
    //
    // get record field structure. It needs to addition of non-standard fields
    //
    _getFakeRectypeField: function(detailTypeID, order){
        
        var dt = $Db.dty(detailTypeID);
        
        //init array 
        var ffr = {};
            
        ffr['rst_DisplayName'] = dt?dt['dty_Name']:'Placeholder';
        ffr['dty_FieldSetRectypeID'] = dt?dt['dty_FieldSetRectypeID'] : 0;
        ffr['rst_FilteredJsonTermIDTree'] = (dt?dt['dty_JsonTermIDTree']:"");
        //ffr['rst_TermIDTreeNonSelectableIDs'] = (dt?dt['dty_TermIDTreeNonSelectableIDs']:"");
        ffr['rst_MaxValues'] = 1;
        ffr['rst_MinValues'] = 0;
        //ffr['rst_CalcFunctionID'] = null; //!!!!
        ffr['rst_DefaultValue'] = null;
        //ffr['rst_DisplayDetailTypeGroupID'] = (dt?dt['dty_DetailTypeGroupID']:"");  //!!!
        ffr['rst_DisplayExtendedDescription'] = (dt?dt['dty_ExtendedDescription']:"");
        ffr['rst_DisplayHelpText'] = (dt?dt['dty_HelpText']:"");
        ffr['rst_DisplayOrder'] = (order>0)?order:999;
        ffr['rst_DisplayWidth'] = 50;
        ffr['rst_LocallyModified'] = 0;
        ffr['rst_Modified'] = 0;
        ffr['rst_NonOwnerVisibility'] = (dt?dt['dty_NonOwnerVisibility']:"viewable");
        ffr['rst_OrderForThumbnailGeneration'] = 0;
        ffr['rst_OriginatingDBID'] = 0;
        ffr['rst_PtrFilteredIDs'] = (dt?dt['dty_PtrTargetRectypeIDs']:"");
        ffr['rst_CreateChildIfRecPtr'] = 0;
        //ffr['rst_RecordMatchOrder'] = 0; //!!!!
        ffr['rst_RequirementType'] = 'optional';
        ffr['rst_Status'] = (dt?dt['dty_Status']:"open");
        ffr['dty_Type'] = (dt?dt['dty_Type']:"freetext");
        
        ffr['dt_ID'] = detailTypeID;
        
        return ffr;
    },
               
    //
    // 
    //     
    _prepareFieldForEditor: function (rfr, rty_ID, dty_ID){                    

            if(!rfr){
                rfr = $Db.rst(rty_ID, dty_ID);
            }else{
                dty_ID = rfr['dt_ID'];
            }
        
            var ffr = window.hWin.HEURIST4.util.cloneJSON(rfr);

            var dt = $Db.dty(dty_ID);

            //ffr['rst_DisplayExtendedDescription'] = (dt?dt['dty_ExtendedDescription']:"");
            
            ffr['dt_ID'] = dty_ID;
            
            if(dt){
                if(window.hWin.HEURIST4.util.isempty(ffr['dty_Type'])){
                    ffr['dty_Type'] = dt['dty_Type'];
                }
                if(window.hWin.HEURIST4.util.isempty(ffr['rst_PtrFilteredIDs'])){
                    ffr['rst_PtrFilteredIDs'] = dt['dty_PtrTargetRectypeIDs'];
                }
                if(window.hWin.HEURIST4.util.isempty(ffr['rst_FilteredJsonTermIDTree'])){
                    ffr['rst_FilteredJsonTermIDTree'] = dt['dty_JsonTermIDTree'];
                }
                if(window.hWin.HEURIST4.util.isempty(ffr['rst_MaxValues'])){
                    ffr['rst_MaxValues'] = 0;
                }
                if(ffr['dty_Type']=='file'){
                    ffr['rst_FieldConfig'] = {"entity":"records", "accept":".png,.jpg,.gif", "size":200};
                }
            }else{
                //console.log(dty_ID+' basefield not fefined');
            }
            
            return ffr;         
    },
    
    //
    // prepare fields and init editing
    //
    _initEditForm_step4: function(response){
        
        var that = this;
        
        if(response==null || response.status == window.hWin.ResponseStatus.OK){
            
            //response==null means reload/refresh edit form
            var allowCreateIndependentChildRecord = false;
            
            if(response){ // && response.length()>0
                that._currentEditRecordset = new hRecordSet(response.data);
                if(that._currentEditRecordset.length()==0){
                    var sMsg = 'Record does not exist in database or has status "hidden" for non owners';
                    window.hWin.HEURIST4.msg.showMsgDlg(sMsg, null, 
                            {ok:'Close', title:'Record not found or hidden'}, 
                                {close:function(){ that.closeEditDialog(); }});
                    return;
                }else{
                    that._isInsert = response.is_insert;     
                }       
                         
                allowCreateIndependentChildRecord = that.options.edit_structure ||
                             (response.allowCreateIndependentChildRecord===true);
            }
            
            var rectypeID = that._getField('rec_RecTypeID');
			
            var activeTabs = [];

            if(this._currentEditRecTypeID == rectypeID){ // check that the previous record and current record are the same type
                var $tab_groups = this.editForm.find('.ui-tabs');

                if($tab_groups.length > 0){ // retain active tab between same record types

                    $.each($tab_groups, function(idx, tab){

                        var $tab_instance = $(tab).tabs('instance');
                        if($tab_instance != undefined){ //
                            activeTabs.push($tab_instance.options.active); // $tab.tabs('option', 'active'); keeps returning tabs object
                        }
                    });
                }
            }

            // check if initial data can be filled in
            var add_fill_data = this._currentEditID == -1 && !window.hWin.HEURIST4.util.isempty(this.options.fill_in_data);

            //pass structure and record details
            that._currentEditID = that._getField('rec_ID');
            that._currentEditRecTypeID = rectypeID;

            //find all parent rectypes
            var parentRtys = this.__findParentRecordTypes(rectypeID);
            
            if(that._isInsert && (!allowCreateIndependentChildRecord) &&!(that.options.parententity>0)){
                //special verification - prevent unparented records
                //IMHO it should be optional
                // 1. if rectype is set as target for one of Parent/child pointer fields 
                // 2 and options.parententity show warning and prevent addition
                if(parentRtys && parentRtys.length>0){
                    
                    var names = [];
                    $(parentRtys).each(function(i,id){
                        names.push($Db.rty(id, 'rty_Name'));
                    });
                    
                    
                    var $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
$Db.rty(rectypeID, 'rty_Name') + ' is defined as a child of <b>'+names.join(', ')
+'</b>.<br><br>To avoid creation of orphan records, you should only create '+$Db.rty(rectypeID, 'rty_Name')
+' records from within a parent  record'
+'<br><br>If you understand the implications and still wish to create an independent, non-child record,<br> check this box <input type="checkbox"> '
+' then click [Create independent record]',

[
 {text:'Create independent record', css:{'margin-right':'200px'}, click: function(){
                            $dlg.dialog( "close" ); 
                            response.allowCreateIndependentChildRecord = true;
                            that._initEditForm_step4(response);   }},
                            
 {text:'Find or create parent record', click: function(){ 
                            $dlg.dialog( "close" );
 
            var popup_options = {
                            select_mode: 'select_single',
                            select_return_mode: 'ids', //'recordset'
                            edit_mode: 'popup',
                            selectOnSave: true, //it means that select popup will be closed after add/edit is completed
                            title: window.hWin.HR('Select of create parent'),
                            rectype_set: parentRtys,
                            pointer_mode: null, //select of create
                            pointer_filter: null,
                            parententity: 0,
                            parentselect: rectypeID,
                            onClose:  function(){ if(!(that.options.parententity>0)) that.closeEditDialog(); },
                            onselect: function(event, data){
                                     if( data.selection && data.selection.length>0 ){
                                        that.options.parententity  = data.selection[0];
                                        that._initEditForm_step4(response);                                        
                                     }else{
                                         that.closeEditDialog();
                                     }
                            }
            };
 
            window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);
 }},
 {text:'Cancel', click: function(){ $dlg.dialog( "close" ); that.closeEditDialog(); }}
 
],{  title:'Child record type' }        
                     );
                     
                          var btn = $dlg.parent().find('button:contains("Create independent record")');
                          var chb = $dlg.find('input[type="checkbox"]').change(function(){
                              window.hWin.HEURIST4.util.setDisabled(btn, !chb.is(':checked') );
                          })
                          window.hWin.HEURIST4.util.setDisabled(btn, true);
                          
                     return;
                    
                }
                
                
            }
    
            
            // fields consists of 
            // 1. fields from record header rec_ID, rec_RecTypeID etc
            // 2. fields from $Db.rst - basefields
            // 3. non - standard fields that are taken from record
            
            // special case for relationship record - assign constraints 
            // for reltype selector and target pointer fields
            var RT_RELATION = window.hWin.HAPI4.sysinfo['dbconst']['RT_RELATION'];
            var DT_RELATION_TYPE, DT_TARGET_RESOURCE;
            if(rectypeID == RT_RELATION && that.options.relmarker_field>0){
                DT_RELATION_TYPE = window.hWin.HAPI4.sysinfo['dbconst']['DT_RELATION_TYPE'];
                
                DT_RESOURCE = window.hWin.HAPI4.sysinfo['dbconst']
                        [that.options.relmarker_is_inward?'DT_PRIMARY_RESOURCE':'DT_TARGET_RESOURCE'];
            }
       
            //prepare db structure from $Db.rst for editing
            var fields = window.hWin.HEURIST4.util.cloneJSON(that.options.entity.fields); //retuns record header field rec_XXXX
            var dt_ID;

            /*
            function __findFieldIdxById(id){
                for(var k in fields){
                    if(fields[k]['dtID']==id){
                        return k;
                    }
                }
                return -1;
            }
            //hide url field
            var fi_url = rectypes.typedefs.commonNamesToIndex['rty_ShowURLOnEditForm'];
            if(rectypes.typedefs[rectypeID].commonFields[fi_url]=='0'){
                fields[__findFieldIdxById('rec_URL')]['rst_Visible'] = false;
            }
            */
            
            // fields - json for editing that describes edit form
            // fields_ids - fields in rt structure (standard fields)
            // s_fields - sorted 
            // field_in_recset - all fields in record 
            
            var rst_details =  $Db.rst(rectypeID);  //array of dty_ID:rst_ID
            var s_fields = [];  //sorted fields including hidden fields from record header 
            var fields_ids = []; //fields in structure
            
            if(window.hWin.HEURIST4.util.isRecordSet(rst_details)){
                rst_details.each2(function(dt_ID, rfr){
                    
                        rfr = window.hWin.HEURIST4.util.cloneJSON(rfr);    
                    
                    
                        if(rectypeID == RT_RELATION && that.options.relmarker_field>0){
                            if(dt_ID==DT_RESOURCE){
                                //constraint for target            
                                rfr['rst_PtrFilteredIDs'] = $Db.dty(that.options.relmarker_field, 'dty_PtrTargetRectypeIDs');
                            }else if(dt_ID==DT_RELATION_TYPE){
                                //constraint for relation vocabulary
                                rfr['rst_FilteredJsonTermIDTree'] = $Db.dty(that.options.relmarker_field, 'dty_JsonTermIDTree');
                            }
                        }
                    
                        rfr['dt_ID'] = dt_ID;
                        s_fields.push(rfr) //array of json
                        fields_ids.push(Number(dt_ID));  //fields in structure
                });
            }

            //----------------
            
            //add non-standard fields that are not in structure
            var field_in_recset = that._currentEditRecordset.getDetailsFieldTypes();

            //add special 2-247 field "Parent Entity"
            //verify that current record type is a child for pointer fields with rst_CreateChildIfRecPtr=1
            
            var DT_WORKFLOW_STAGE = Number(window.hWin.HAPI4.sysinfo['dbconst']['DT_WORKFLOW_STAGE']);
            var DT_PARENT_ENTITY  = Number(window.hWin.HAPI4.sysinfo['dbconst']['DT_PARENT_ENTITY']);
            if( window.hWin.HEURIST4.util.findArrayIndex(DT_PARENT_ENTITY, field_in_recset)<0 && 
                    this.options.parententity>0)    //parent record id is set already (case: this is addition of new child from search record dialog)
                    //|| (parentRtys.length>0 && that._isInsert) ))   //current rectype is referenced as a child and this is ADDITION
            {
                    field_in_recset.push(DT_PARENT_ENTITY);
            }
            
            //Disabled 2018-05-17
            //reasons:
            //they are extremely confusing for the uninitiated (and even for those in the know); 
            //you can't control them easily b/c they are set in another record type; 
            $is_enabled_inward_relationship_fields = false;
/*
            if($is_enabled_inward_relationship_fields){
            
                var addhead = 0;
                //Add inward relationship fields
                //1. scan all other record structures
                //2. find relmarker feidls that targets current rectypes
                //3. add fake field into structure
                var rt, already_added = {};
                for(rt in rectypes.typedefs)
                if(rt>0 && rt!=rectypeID){
                    for(dt_ID in rectypes.typedefs[rt].dtFields)
                    if(dt_ID>0 && rectypes.typedefs[rt].dtFields[dt_ID][fi_type]=='relmarker'){
                        
                        //this field can be already added - in this case we need just extend constraints
                        if(already_added[dt_ID]>=0){
                            s_fields[already_added[dt_ID]][fi_ptrs].push(rt);
                            continue;
                        }
                        
                        var ptr = rectypes.typedefs[rt].dtFields[dt_ID][fi_ptrs];
                        if(window.hWin.HEURIST4.util.isempty(ptr)){ 
                            //skip unconstrined
                            continue;
                        }else{
                            ptr = ptr.split(',')
                            if(window.hWin.HEURIST4.util.findArrayIndex(rectypeID, ptr)<0){
                                continue;
                            }
                        }
                        //this relmarker suits us
                        
                        if(addhead==0){                    
                            var rfr = that._getFakeRectypeField(999999);
                            rfr[fi_name] = 'Inward (reverse) relationships not included in fields above';
                            rfr[fi_help] = 'These relationships target the current record but are not defined '
                                        +'in a relationship marker field for this record type. They do not, '   
                                        +'therefore, display in the relationship marker fields above (if any).';
                            rfr[fi_type] = 'separator';
                            rfr[fi_order] = 1000;
                            s_fields.push(rfr);
                        }
                        addhead++;
                        
                        var rfr = window.hWin.HEURIST4.util.cloneJSON(rectypes.typedefs[rt].dtFields[dt_ID]);
                        rfr['dt_ID'] = dt_ID;
                        rfr[fi_reqtype] = 'optional';
                        rfr[fi_order] = 1000+addhead;
                        rfr[fi_ptrs] = [rt];
                        
                        already_added[dt_ID] = s_fields.length;
                        s_fields.push(rfr);
                            
                    }
                }
            }
*/            
            // 1) Add fields that are in record set (field_in_recset) 
            //    but not in structure (fields_ids) - NON STANDARD FIELDS
            // 2) Disable (readonly) for DT_WORKFLOW_STAGE 2-1080 workflow stage field

            addhead = 0;
            for(var k=0; k<field_in_recset.length; k++){
                //field in recset is not in structure
                if( window.hWin.HEURIST4.util.findArrayIndex(field_in_recset[k],fields_ids)<0)
                { 
                    if(field_in_recset[k]==DT_PARENT_ENTITY){

                        var rfr = that._getFakeRectypeField(DT_PARENT_ENTITY);
                        rfr['rst_DisplayName'] = 'Child record of';
                        rfr['rst_DisplayOrder'] = -1;//top most
                        rfr['rst_DisplayHelpText'] = '';// display no help text for this field
                        if(this.options.parententity>0){
                            rfr['rst_DefaultValue'] = this.options.parententity;  //parent Record ID
                            rfr['rst_Display'] = 'readonly';
                        }
                        
                        if(parentRtys && parentRtys.length>0){
                           rfr['rst_RequirementType'] = 'required';
                           rfr['rst_PtrFilteredIDs'] = parentRtys; //constrained to parent record types overrides dty_PtrTargetRectypeIDs
                           
                           //readonly - if the only value 
                           if(!that._isInsert){
                                record = that._currentEditRecordset.getById(that._currentEditID);
                                var values = that._currentEditRecordset.values(record, DT_PARENT_ENTITY);
                                if(values && values.length==1){
                                    rfr['rst_Display'] = 'readonly';   
                                }
                           }  
                        }else{
                            rfr['rst_RequirementType'] = 'recommended';
                        }
                        
                        s_fields.push(rfr);
                        
                    }else{

                        //ignore
                        //fields that are not in rectype structure
                        if(field_in_recset[k]!=DT_WORKFLOW_STAGE){
                    
                            if(addhead==0){                    
                                //fake header
                                var rfr = that._getFakeRectypeField(9999999);
                                rfr['rst_DisplayName'] = 'Non-standard fields for this record type';
                                rfr['dty_Type'] = 'separator';
                                rfr['rst_DisplayOrder'] = 1100;
                                rfr['rst_DefaultValue'] = 'group_break';
                                s_fields.push(rfr);
                            }
                            addhead++;
                        
                        }
                        
                        var rfr = that._getFakeRectypeField(field_in_recset[k], 1100+addhead);
                        
                        if(field_in_recset[k]==DT_WORKFLOW_STAGE){
                            rfr['rst_Display'] = 'hidden';
                        }
                        
                        s_fields.push(rfr);
                    }
                    
                }
            }//for   
            

            //create UI from rfr 
            
            //sort by order
            s_fields.sort(function(a,b){ return a['rst_DisplayOrder']<b['rst_DisplayOrder']?-1:1});

            var group_fields = null;
            var hasField = false; // check for any fields
            var temp_group_details = [], hasTabs = false; // check if any groupings are set to tabs
            var max_length_fields = []; // freetext, blobktext, and float fields that are set to max width
            var terms_as_buttons = []; // enum fields, for adjusting each button+label's width
            var available_groups = ['group', 'group_break', 'tabs', 'tabs_new', 'accordion', 'accordion_inner', 'expanded', 'expanded_inner'];

            var has_rec_access = window.hWin.HAPI4.has_access(this._getField('rec_OwnerUGrpID'));
            var cur_record = that._currentEditRecordset.getFirstRecord();
            let rty_ConceptCode = $Db.getConceptID('rty', this._currentEditRecTypeID);

            for(var k=0; k<s_fields.length; k++){

                var dtFields = that._prepareFieldForEditor( s_fields[k] );
                var simpleDetails = {};

                if(dtFields['dty_Type']=='separator'){

                    if(group_fields!=null){
                        fields[fields.length-1].children = group_fields;
                    }

                    var dtGroup = {
                        dtID: dtFields['dt_ID'],
                        groupHeader: dtFields['rst_DisplayName'],
                        groupHelpText: dtFields['rst_DisplayHelpText'],
                        groupHidden: false,
                        groupTitleVisible: (dtFields['rst_RequirementType']!=='forbidden'),
                        groupType: available_groups.includes(dtFields['rst_DefaultValue']) ? dtFields['rst_DefaultValue'] : 'group',
                        groupStyle: {},
                        children:[]
                    };
                    fields.push(dtGroup);
                    group_fields = [];

                    if(!hasTabs){
                        if(dtFields['rst_DefaultValue'] == 'tabs' || dtFields['rst_DefaultValue'] == 'tabs_new'){
                            hasTabs = true;
                        }else if(dtGroup['groupTitleVisible'] && dtGroup['dtID'] != 9999999){
                            temp_group_details.push({rst_RecTypeID: that._currentEditRecTypeID, rst_DetailTypeID: dtGroup['dtID'], rst_DefaultValue: 'tabs'});
                        }
                    }
                }else{

                    hasField = true;

                    if(add_fill_data && (dtFields['dty_Type'] == 'freetext' || dtFields['dty_Type'] == 'blocktext') && dtFields['rst_DefaultValue'] == ''
                        && (dtFields['rst_RequirementType'] == 'required' || dtFields['rst_RequirementType'] == 'recommended')){

                        dtFields['rst_DefaultValue'] = this.options.fill_in_data;
                        add_fill_data = false;
                    }

                    let fld_vis = $Db.rst(this._currentEditRecTypeID, dtFields['dt_ID'], 'rst_NonOwnerVisibility'); 
                    //let fld_vis_status = that._currentEditRecordset.getFieldVisibilites(cur_record, dtFields['dt_ID']);
                    let hide_fld = fld_vis == 'hidden' && !has_rec_access;
                    let dty_ConceptCode = $Db.getConceptID('dty', dtFields['dt_ID']);

                    if(hide_fld){
                        continue;
                    }else if(group_fields!=null){
                        group_fields.push({"dtID": dtFields['dt_ID'], "dtFields":dtFields});
                    }else{
                        fields.push({"dtID": dtFields['dt_ID'], "dtFields":dtFields});
                    }

                    if((dtFields['dty_Type'] == 'freetext' || dtFields['dty_Type'] == 'blocktext' || dtFields['dty_Type'] == 'float') 
                        && dtFields['rst_RequirementType'] != 'forbidden' && dtFields['rst_DisplayWidth'] == '0'){ // set field width to max

                        max_length_fields.push(dtFields['dt_ID']);
                    }else if(dtFields['dty_Type'] == 'enum' && dtFields['rst_TermsAsButtons'] == 1){
                        terms_as_buttons.push(dtFields['dt_ID']);
                    }

                    // Concat multi-value page content fields into one
                    if(rty_ConceptCode == '99-52' && dty_ConceptCode == '2-4'){
                        let fld_value = that._currentEditRecordset.values(cur_record, dtFields['dt_ID']); //fld

                        if(fld_value != null && fld_value.length > 1){
                            that._currentEditRecordset.setFld(cur_record, dtFields['dt_ID'], fld_value.join('')); 
                        }
                    }
                }
            }//for s_fields

            //add children to last group
            if(group_fields!=null){
                fields[fields.length-1].children = group_fields;
            }
            
            that._editing.initEditForm(fields, that._currentEditRecordset, that._isInsert);
            
            that._editing.editStructureFlag(this.options.edit_structure===true);
            
            //
            // Applies values on record edit form for individual visibility settings
            //
            that._editing.setFieldsVisibility( that._currentEditRecordset );
            
            that._afterInitEditForm();

            if(this._keepYPos>0){
                this.editForm.scrollTop(this._keepYPos);
                this._keepYPos = 0;
            }else{
                this.editForm.scrollTop(0);
            }
            this.editForm.scrollLeft(0);

            if(activeTabs.length > 0){

                var $tab_groups = this.editForm.find('.ui-tabs');

                if($tab_groups.length > 0){ // retain active tab between same record types

                    $.each($tab_groups, function(idx, tab){

                        var $tab = $(tab);
                        if($tab.tabs('instance') != undefined){ // set active tabs
                            $tab.tabs('option', 'active', activeTabs[idx]);
                        }
                    });
                }
            }
            
            var header_to_tabs_ignore = sessionStorage.getItem('header_to_tabs_ignore');

            header_to_tabs_ignore = (header_to_tabs_ignore != null) ? JSON.parse(header_to_tabs_ignore) : {};

            // Check if there are headers, and whether none of them are tabs - session only
            if(window.hWin.HAPI4.has_access(1) && !hasTabs && temp_group_details && Object.keys(temp_group_details).length > 2
                && this.options.edit_structure == undefined && this.options.rts_editor == undefined){

                if(header_to_tabs_ignore[window.hWin.HAPI4.database] == null 
                    || !header_to_tabs_ignore[window.hWin.HAPI4.database].includes(that._currentEditRecTypeID)){

                    var $dlg;

                    var btns = {};
                    btns[window.hWin.HR('Convert to tabs')] = function(){
                        $dlg.dialog('close');

                        //Convert all headers to tabs
                        var request = {
                            'a': 'save',
                            'entity': 'defRecStructure',
                            'request_id': window.hWin.HEURIST4.util.random(),
                            'fields': temp_group_details
                        };

                        // Update Database Definition
                        window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){

                            if(response.status == window.hWin.ResponseStatus.OK){

                                for(var i = 0; i < temp_group_details.length; i++){ // Update Cache
                                    $Db.rst(temp_group_details[i]['rst_RecTypeID'], temp_group_details[i]['rst_DetailTypeID'], 'rst_DefaultValue', 'tabs');
                                }

                                if(header_to_tabs_ignore[window.hWin.HAPI4.database] != null){ // add to list of ignore, to avoid calling on refresh
                                    header_to_tabs_ignore[window.hWin.HAPI4.database].push(that._currentEditRecTypeID);
                                }else{
                                    header_to_tabs_ignore[window.hWin.HAPI4.database] = [that._currentEditRecTypeID];
                                }

                                sessionStorage.setItem('header_to_tabs_ignore', JSON.stringify(header_to_tabs_ignore));

                                that._initEditForm_step4();

                                return;

                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        });
                    };
                    btns[window.hWin.HR('Leave as-is')] = function(){
                        $dlg.dialog('close');

                        if(header_to_tabs_ignore[window.hWin.HAPI4.database] != null){
                            header_to_tabs_ignore[window.hWin.HAPI4.database].push(that._currentEditRecTypeID);
                        }else{
                            header_to_tabs_ignore[window.hWin.HAPI4.database] = [that._currentEditRecTypeID];
                        }

                        sessionStorage.setItem('header_to_tabs_ignore', JSON.stringify(header_to_tabs_ignore));
                    };

                    $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                        'This form uses multiple sections, but none of them are Tab headers. We strongly<br>'
                        + 'recommend the use of tabs for improved appearance and usability.<br><br>'
                        + 'We can convert all headings to Tabs automatically. If you don\'t like the result you can<br>'
                        + 'convert some or all of them back to simple headers, or you can split the tabs into two<br>'
                        + 'or more groups by editing one of the tabs and converting it to Tab (start new group).<br><br>'
                        + 'The data in the record will not be affected in any way.'
                        , btns, {title: 'Improved form layout', yes: 'Convert to tabs', no: 'Leave as-is'}
                    );
                }
    
            }else if(header_to_tabs_ignore[window.hWin.HAPI4.database] != null 
                && header_to_tabs_ignore[window.hWin.HAPI4.database].includes(that._currentEditRecTypeID)){

                var idx = header_to_tabs_ignore[window.hWin.HAPI4.database].indexOf(that._currentEditID);
                if(idx > -1){
                    header_to_tabs_ignore[window.hWin.HAPI4.database].splice(idx, 1);
                }
            }

            //show rec_URL 
            var ele = that._editing.getFieldByName('rec_URL');
            var hasURLfield = ($Db.rty(rectypeID, 'rty_ShowURLOnEditForm')=='1');
            if(hasURLfield){
                ele.show();

                // special case  - show separator between parent record field and other fields
                // in case there is no header
                var first_set = that.editForm.find('fieldset:first');
                first_set.show();
                var next_ele = first_set.next().next();
                if(!next_ele.hasClass('separator')){
                    first_set.css('border-bottom','1px solid #A4B4CB');
                }
            }

            // display record title field and move child record field (if present) to the top of form
            if(!that.options.edit_structure && !window.hWin.HEURIST4.util.isempty(that._getField('rec_Title'))){
                that.showExtraRecordInfo();
            }

            // Add a divider between the popup controls and the first set of input, 
            // if the first set is not contained within a group and there are groups below these loose inputs
            var first_child = $(this.editForm[0].childNodes[0]);
            if (this.editForm[0].childNodes.length > 2 && first_child.is("fieldset") && first_child.children("div:visible").length != 0){
                first_child.css('border-top', '1px solid #A4B4CB');
            }
            
            //special case for bookmarklet addition - some values are already assigned 
            if(that._isInsert){
                var vals = ele.editing_input('getValues');
                if(vals[0]!=''){
                      //get snapshot of url  
                    
                      that._editing.setModified(true); //forcefully set to modified
                      that.onEditFormChange();
                }
            }
            
            if(that.editFormSummary && that.editFormSummary.length>0) {
                /*@todo that.editFormSummary.accordion({
                    active:0    
                });*/
                //@todo restore previous accodion state
                that.editFormSummary.find('.summary-accordion').each(function(idx,item){
                    if($(item).accordion('instance')){
                        var active = $(item).accordion('option','active');
                        if(active!==false){
                            $(item).accordion({active:0});
                            if($(item).find('.summary-content').is(':empty'))
                                that._fillSummaryPanel($(item).find('.summary-content'));
                        }
                    }
                });
                
            }
            
            //show coverall to prevnt edit
            //1. No enough premission
            //var no_access = that._getField('rec_OwnerUGrpID')!=0 &&  //0 is everyone
            var no_access = !(window.hWin.HAPI4.is_admin() || window.hWin.HAPI4.is_member(that._getField('rec_OwnerUGrpID')));
                            //!window.hWin.HAPI4.is_admin()
            var exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);
            
            //2. Popup for resource field
            var dlged = that._getEditDialog();
            if(dlged && (no_access || (this.options.edit_obstacle && exp_level!=0 ) )){ 
                
                var ele = $('<div><div class="edit-button" style="background:#f48642 !important;margin: 40px auto;width:200px;padding:10px;border-radius:4px;">'
                            +'<h2 style="display:inline-block;color:white">View-only mode</h2>&nbsp;&nbsp;'
                            +'<a href="#" style="color:white">edit</a><span><br>click to dismiss</span></div></div>')
                       .addClass('coverall-div-bare')
                       .css({top:'30px', 'text-align':'center','zIndex':9999999999, height:'auto'}) //, bottom: '40px', 'background':'red'
                       .appendTo(dlged);
                
                var eles = dlged.find('.ui-layout-center');
                if(no_access){
                    eles.css({'background-image': 'url('+window.hWin.HAPI4.baseURL+'hclient/assets/non-editable-watermark.png)'});                    
                }else{
                    eles.css({'background':'lightgray'});    
                    //eles.css({'background':'lightgray'});    
                }
                that._editing.setDisabled(true);
                
                eles = dlged.find('.ui-layout-east > .editFormSummary');
                if(no_access && eles.length>0){
                    eles.css({'background-image': 'url('+window.hWin.HAPI4.baseURL+'hclient/assets/non-editable-watermark.png)'});                    
                }else{
                    eles.css({'background':'lightgray'});    
                }
                //screen for summary
                $('<div>').addClass('coverall-div-bare')
                    .css({top:0,height:'100%',left:35,right:0,'zIndex':9999999999})
                    .appendTo(eles);
                    
                       
                if(no_access){
                    ele.find('a').hide();
                    ele.find('.edit-button').button().click(function(){
                        ele.remove();
                    });
                }else{       
                    //find('a')
                    ele.find('.edit-button').button().click(function(){
                        ele.remove();
                        //restore edit ability 
                        that._editing.setDisabled(false);
                        dlged.find('.ui-layout-center > div').css({'background':'none'});
                        dlged.find('.ui-layout-center').css({'background':'none'});
                        var eles = dlged.find('.ui-layout-east > .editFormSummary')
                        if(eles.length>0){
                            eles.css({'background':'none'});   
                            eles.find('.coverall-div-bare').remove();
                        }
                        //remove screen
                        
                    });
                    ele.find('span').hide(); //how no enough rights
                }
                this.options.edit_obstacle = false;
            } 

            // Add tab paging icons and activation handling
            var eles = this.editForm.find('.ui-tabs');
            for (var i=0; i<eles.length; i++){
                $(eles[i]).attr('data-id','idx'+i).tabs('paging',{
                    nextButton: '<span style="font-size:2em;font-weight:900;line-height:5px;vertical-align: middle">&#187;</span>', // Text displayed for next button.
                    prevButton: '<span style="font-size:2em;font-weight:900;line-height:5px;vertical-align: middle">&#171;</span>' // Text displayed for previous button.
                });

                // onActivate handle
                $(eles[i]).on('tabsactivate', function(event, ui){
                    if(ui.newPanel && ui.newPanel.find('.enum_input:visible').length > 0){ // fix terms as button widths

                        var $input_divs = ui.newPanel.find('.enum_input:visible').parent();

                        $.each($input_divs, function(i, input_div){

                            var $inputdiv = $(input_div);
                            var $input = $inputdiv.find('.enum_input');

                            if($input.first().height()*2 < $inputdiv.height()){
                                $input.css('min-width', '120px');
                            }else{
                                $input.css('min-width', '');
                            }
                        });
                    }
                });
            }

            if(window.hWin.HAPI4.is_admin() && this.options.allowAdminToolbar!==false){

                if(!hasField){ // open modify structure, if able when there are no fields
                    this.editRecordType(true);
                }else if(this.options.edit_structure == undefined && this.options.rts_editor == undefined){ // check for default title mask

                    var title_mask = $Db.rty(that._currentEditRecTypeID, 'rty_TitleMask');
                    var match_result = title_mask.match(/\[([^\]]+)\]/g); // check for fields in title mask

                    if(title_mask == 'record [ID]' || !match_result){

                        var $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                            'You have not yet selected the fields used to create the <b>Constructed title</b><br><br>'

                            +'The <b>Constructed title</b> is like the reference you might find in the bibliography at the end<br>'
                            +'it uses important fields to uniquely identify and summarise the bibliographic reference, or in this case<br>'
                            +'the database record in question.<br><br>'

                            +'<b>Constructed titles</b> are used to represent records when they are listed in search results<br>'
                            +'and as the visible representation of the record referenced in a pointer field or relationship marker.<br>'
                            +'They can also be used in reports and visualisations, searches, sorting or in the constructed title<br>'
                            +'of connected records.<br><br>'

                            +'We strongly recommend putting a little thought into this, as well-designed constructed titles can<br>'
                            +'greatly improve the clarity and ease of use of the database.<br>'
                            +'We recommend you read the <a href="https://heuristref.net/heurist/?db=Heurist_Help_System&website&id=39&pageid=773" target="_blank">help for Constructed titles</a>', 
                            { 'Proceed': function(){ that.editRecordTypeTitle(); $dlg.dialog('close'); } },
                            {title:'Constructed title not yet configured', yes:'Proceed'},
                            {default_palette_class: 'ui-heurist-design'});

                        return;
                    }
                }
            }

            if(max_length_fields.length > 0){ // Set the selected freetext and memo/blocktext fields to 'max' width

                var popup_maxw = this.editForm.width() * ((this.options.rts_editor) ? 0.7 : 0.8);

                for(var i = 0; i < max_length_fields.length; i++){

                    var width = popup_maxw;
                    var field = this._editing.getFieldByName(max_length_fields[i]);

                    field.find('input, textarea').css({'width': width, 'max-width': width});
                }
            }else if(terms_as_buttons && terms_as_buttons.length > 0){ // Set terms as button fields, if more than one line, set width 

                for(var j = 0; j < terms_as_buttons.length; j++){

                    var field = this._editing.getFieldByName(terms_as_buttons[j]);

                    if(field.is(':visible')){

                        var $inputdiv = field.find('.input-div');
                        var $input = $inputdiv.find('.enum_input');

                        if($input.first().height()*2 < $inputdiv.height()){
                            $input.css('min-width', '120px');
                        }
                    }
                }
            }

            if(this.options.fill_in_data){ // force ismodified
                that._editing.setModified(true);
                that.onEditFormChange();
            }
        }else{
            window.hWin.HEURIST4.msg.showMsgErr(response);
        }
    },  //_initEditForm_step4                  
    
    //  -----------------------------------------------------
    //  OVERRIDE
    //  send update request and close popup if edit is in dialog
    //  afteraction - none, close, newrecord or callback function
    //
    _saveEditAndClose: function( fields, afterAction ){

            if(!(this._currentEditID>0)) return;
            
            if(window.hWin.HAPI4.is_callserver_in_progress()) {
                //console.log('prevent repeatative call')
                return;   
            }
        
            var that = this;                                    
        
            if(!fields){
                try{
                    fields = this._getValidatedValues(); 
                }catch(e){
                    fields = null;
                }

                var hasValue = false, hasDtlField = false, hasScript = false;
                var ambig_dates = [];

                let rty_ConceptCode = $Db.getConceptID('rty', this._currentEditRecTypeID);
                //verify max lengtn in 64kB per value
                for (var dtyID in fields){

                    let updated_values = false;
                    if(parseInt(dtyID)>0){
                        
                        let dty_ConceptCode = $Db.getConceptID('dty', dtyID);
                        var dt = $Db.dty(dtyID, 'dty_Type');
                        hasDtlField = true;
                        if(dt=='geo' || dt=='file') continue;
                        
                        var values = fields[dtyID];

                        if(window.hWin.HEURIST4.util.isempty(values)) continue;

                        hasValue = true;
                        if(!$.isArray(values)) values = [values];

                        // Split CMS MenuPage's Page content into several values
                        if(rty_ConceptCode == '99-52' && dty_ConceptCode == '2-4'){

                            let complete_value = values.join(''); //

                            let len = window.hWin.HEURIST4.util.byteLength(complete_value);
                            let len2 = complete_value.length;
                            let lim = (len-len2<200)?64000:32768;

                            if(len > lim){ // split into parts

                                lim = lim - 1000; //reduce for a bit of room
                                let parts_count = Math.ceil(len / lim);
                                let start = 0;
                                let new_values = []; //new Array(parts_count)

                                for (let i = 0; i < parts_count; i ++){
                                    new_values.push(complete_value.substr(start, lim));
                                    start += lim;
                                }

                                values = new_values;
                            }
                        }

                        for (var k=0; k<values.length; k++){
                            
                            var len = window.hWin.HEURIST4.util.byteLength(values[k]);
                            var len2 = values[k].length;
                            var lim = (len-len2<200)?64000:32768;
                            var lim2 = (len-len2<200)?64:32;
                            
                            if(len>lim){ //65535){  32768
                                var sMsg = 'The data in field ' + $Db.rst(that._currentEditRecTypeID, dtyID, 'rst_DisplayName')
                                +' exceeds the maximum size for a field of 64Kbytes.<br>' //lim2
                                +'Note that this does not mean 64K characters, ' //lim2
                                +'as Unicode uses multiple bytes per character.<br>'
                                +'You can store more than 64Kbytes by making the field repeating and splitting the data into two or more values for this field.';
                                window.hWin.HEURIST4.msg.showMsgErr(sMsg);
                                
                                var inpt = this._editing.getFieldByName(dtyID);
                                if(inpt){
                                    inpt.editing_input('showErrorMsg', sMsg);
                                    $(this.editForm.find('input.ui-state-error')[0]).focus();   
                                }
                                return;
                                
                            }
                            if(dtyID!=window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_EXTFILES'] && typeof values[k]==='string')
                            {
                                if(values[k].indexOf('<script')>=0 && values[k].indexOf('</script>')>0){
                                    var inpt = this._editing.getFieldByName(dtyID);
                                    if(inpt) inpt.editing_input('showErrorMsg', '&lt;sctipt&gt; tag not allowed');  
                                    hasScript = true;
                                }
                            }

                            if(fields['no_validation'] == 1){
                                continue;
                            }

                            if(dt == 'date'){

                                if(Temporal.isValidFormat(values[k])){ // check if valid temporal string
                                    continue; // is valid, skip
                                }

                                const value_spaceless = values[k].replaceAll(/\s+/g, ''); // remove all whitespaces

                                const approx_regex = /circa.?|ca.?|approx.?|~/; // circa 1995, ca. 1995, approx 1995, ~1995
                                const has_range = /[à|.|to|\-|,]/; // range separators
                                const range_regex = /\d+|[à|.|to|\-|,]+/g; // 1990-1995, 1990to1995, 1990..1995, 1990,1995 (spaces removed first)
                                const has_named_month = /(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)/ig; // e.g. 12Jan1995

                                let matches = values[k].match(approx_regex);
                                if(!window.hWin.HEURIST4.util.isempty(matches) && matches.length > 0){ // approx value, setup simple date with circa value

                                    let date_val = values[k].replace(approx_regex, '');

                                    try{

                                        let t_date = new Temporal();
                                        date_val = TDate.parse(date_val);

                                        date_val = date_val.toString('yyyy-MM-dd');

                                        t_date.setType('s');
                                        t_date.addObjForString('DAT', date_val);
                                        t_date.addObjForString("CIR", "1");
                                        values[k] = t_date.toString();

                                        updated_values = true;

                                        continue; // done next value
                                    } catch(e) {
                                        if(e.indexOf('ambiguous') >= 0){
                                            ambig_dates.push({dtyid: dtyID, org_value: values[k], index: k, type: 'approx'});
                                            continue;
                                        }
                                    }

                                    continue;
                                }

                                matches = [...value_spaceless.matchAll(range_regex)];
                                if(!window.hWin.HEURIST4.util.isempty(matches) && matches.length > 0 
                                        && has_range.test(value_spaceless) && (value_spaceless.split('-')<3) ){

                                    is_ambig = false;

                                    const sep_match_index = Math.floor(matches.length / 2);
                                    const sep = matches[sep_match_index][0];
                                    const sep_index = matches[sep_match_index]['index'];
                                    
                                    let TPQ = value_spaceless.slice(0, sep_index);
                                    let TAQ = value_spaceless.slice(sep_index + sep.length);

                                    if(TPQ.length >= 4 && TAQ.length >= 4 && has_range.test(sep)){ // quick conversion to range needs same length dates, and at least full years

                                        try {
                                            
                                            let t_TPQ = TDate.parse(TPQ);
                                            TPQ = t_TPQ.toString('yyyy-MM-dd');
                                        } catch(e) {
                                            if(e.indexOf('ambiguous') > 0){
                                                is_ambig = true;
                                            }
                                        }

                                        try {
                                            
                                            let t_TAQ = TDate.parse(TAQ);
                                            TAQ = t_TAQ.toString('yyyy-MM-dd');
                                        } catch(e) {
                                            if(e.indexOf('ambiguous') >= 0){
                                                is_ambig = true;
                                            }
                                        }

                                        if(is_ambig){
                                            ambig_dates.push({dtyid: dtyID, org_value: values[k], value: {'TPQ': TPQ, 'TAQ': TAQ}, index: k, type: 'range'});
                                            continue;
                                        }

                                        if(new Date(TPQ).getTime() >= new Date(TAQ).getTime()){
                                            let temp = TPQ;
                                            TPQ = TAQ;
                                            TAQ = temp;
                                        }                                

                                        let temporal = new Temporal();
                                        temporal.setType('p');
                                        temporal.addObjForString("TPQ", TPQ); // Earliest value
                                        temporal.addObjForString("TAQ", TAQ); // Latest value
                                        temporal.addObjForString("COM", values[k]); // Insert original value as comment

                                        let results = Temporal.checkValidity(temporal);
                                        if(results[0]){ // is valid
                                            values[k] = temporal.toString();
                                            updated_values = true;
                                        } // else, not handled

                                        continue;
                                    }
                                }

                                matches = [...values[k].matchAll(has_named_month)];
                                if(!window.hWin.HEURIST4.util.isempty(matches) && matches.length == 1){

                                    let month = matches[0][0];
                                    let month_index = matches[0]['index'];
                                    let date_val = values[k].slice(0, month_index) + ' ' + month + ' ' + values[k].slice(month_index+month.length);

                                    try {
                                        date_val = TDate.parse(date_val).toString('yyyy-MM-dd');

                                        if(date_val.length == 4){ // unknown
                                            continue;
                                        }

                                        values[k] = date_val;
                                        updated_values = true;
                                    } catch(e) {
                                        if(e.indexOf('ambiguous') > 0){
                                            ambig_dates.push({dtyid: dtyID, org_value: values[k], index: k, type: 'simple'});
                                            continue;
                                        }
                                    }
                                }

                                // Check for initial ambiguity
                                try {
                                    let tDate = TDate.parse(values[k]);
                                    let date_val = tDate.toString('yyyy-MM-dd');
                                    let format = tDate.getDateFormat();

                                    if((date_val.length == 4 && values[k].length <= 4) || date_val.length == values[k].length){
                                        
                                        if(format == 'dmy' && tDate.getDay() < 13 && tDate.getMonth() < 13){ // only uses 'mdy' when first number has to be month
                                            throw 'ambiguous date';
                                        }

                                        continue;
                                    }
                                } catch(e) {
                                    if(e.indexOf('ambiguous') >= 0){
                                        ambig_dates.push({dtyid: dtyID, org_value: values[k], index: k, type: 'simple'});
                                        continue;
                                    }
                                }
                            }
                        }

                        // Update values
                        if(updated_values){
                            fields[dtyID] = values; // update field value

                            let $ele = that._editing.getFieldByName(dtyID);
                            if($ele && $ele.length > 0) $ele.editing_input('setValue', values); // update field element editing_inputs
                        }
                    }
                }//verify max size
                
                if(fields != null && !hasDtlField){
                    window.hWin.HEURIST4.msg.showMsgFlash("There are no details to save", 1500);
                    return;
                }else if(fields != null && !hasValue){
                    window.hWin.HEURIST4.msg.showMsgFlash("Please enter a value into any field to save the record", 1500);
                    return;
                }else if(fields != null && hasScript){
                    window.hWin.HEURIST4.msg.showMsgFlash("Some fields have &lt;sctipt&gt; tag. It is not allowed in database", 1500);
                    return;
                }else if(fields != null && ambig_dates.length > 0){
                    that._handleAmbiguousDates(ambig_dates);
                    return;
                }
                
                //assign workflow stage field 2-9453
                if(fields!=null && this._swf_rules.length>0){
                    var swf_mode = this.element.find('.sel_workflow_stages').val();
                    if(swf_mode=='on' || (swf_mode=='new' && this._isInsert)){
                        
                        var opts_swf_stages = '';
                        var dtyID = window.hWin.HAPI4.sysinfo['dbconst']['DT_WORKFLOW_STAGE']; //$Db.getLocalID('dty', '2-1080'); //workflow stage field
                        var curr_stage = fields[dtyID];
                        
                        //TRM_SWF_IMPORT should we disable it?

                        for (var i=0; i<this._swf_rules.length; i++){
                            var is_disabled = '';
                            if(this._swf_rules[i]['swf_StageRestrictedTo']){
                                var grps = this._swf_rules[i]['swf_StageRestrictedTo'];
                                if(!window.hWin.HAPI4.is_member(grps)){
                                    is_disabled = ' disabled';
                                }
                            }
                            var is_selected = (this._swf_rules[i]['swf_Stage']==curr_stage)?' selected':'';
                            
                            opts_swf_stages = opts_swf_stages 
                                + '<option value="'+this._swf_rules[i]['swf_Stage']+'"'
                                +is_disabled+is_selected+'>'
                                +window.hWin.HEURIST4.util.htmlEscape($Db.trm(this._swf_rules[i]['swf_Stage'],'trm_Label'))
                                +'</option>';
                        }
                        
                        var $dlg;
                        var btns = {};

                        btns[window.hWin.HR('Save')] = function(){
                            fields[dtyID] = $dlg.find('#dlg-prompt-value').val();
                            that._saveEditAndClose( fields, afterAction );

                            $dlg.dialog('close');
                        };
                        btns[window.hWin.HR('Cancel')] = function(){
                            swf_mode = $dlg.find('.sel_workflow_stages').val();
                            that.element.find('.sel_workflow_stages').val(swf_mode);

                            $dlg.dialog('close');
                        };

                        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                        '<div class="heurist-helper3">This setting will determine actions to be taken such as visibility settings, marking for publication or email notifications</div>'
                        +'<p><label>Workflow stage: </label><select id="dlg-prompt-value" class="text ui-corner-all">'
                            + opts_swf_stages
                        +'</select>&nbsp;&nbsp;<button id="btn_advance">Advance</button></p><br><br>', btns, 
                        {title: window.hWin.HR('Set workflow stage'), yes: window.hWin.HR('Save'), no: window.hWin.HR('Cancel')},
                        {default_palette_class: this.options.default_palette_class}); //'ui-heurist-populate'

                        var $ele = this.element.find('.div_workflow_stages');

                        if($ele.length == 1){
                            $ele.clone(true, true)
                                .appendTo($dlg);

                            $dlg.find('.sel_workflow_stages').val(swf_mode);
                        }

                        $dlg.find('#btn_advance').button({icon:'ui-icon-caret-1-e',iconPosition:'end'})
                                .css('font-size','0.9em')
                        .click(function(){  //select next
                                $dlg.find('#dlg-prompt-value')[0].selectedIndex++;    
                                if($dlg.find('#dlg-prompt-value')[0].selectedIndex<0){
                                      $dlg.find('#dlg-prompt-value')[0].selectedIndex=0;            
                                }
                        });

                        return;
                    }
                } //END assign workflow stage field 2-9453
                
            }
            
            if(fields==null) return; //validation failed

            //assign new set of tags to record
            if($.isArray(that._updated_tags_selection)){
                var request2 = {};
                request2['a']          = 'batch'; //batch action
                request2['entity']     = 'usrTags';
                request2['tagIDs']  = that._updated_tags_selection;
                request2['recIDs']  = that._currentEditID;
                request2['mode']    = 'replace';
                that._updated_tags_selection = null;
                
                window.hWin.HAPI4.EntityMgr.doRequest(request2, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            
                            that._saveEditAndClose( fields, afterAction );
                        }
                    });
                return;
            }
            
            //
            let rec_URL = fields['rec_URL'],
                rec_OwnerUGrpID = fields['rec_OwnerUGrpID'],
                rec_NonOwnerVisibility = fields['rec_NonOwnerVisibility'],
                rec_NonOwnerVisibilityGroups = fields['rec_NonOwnerVisibilityGroups'],
                rec_ScratchPad = fields['rec_ScratchPad'];
            //unset header fields                
            for (var key in fields){
                if( (!(parseInt(key)>0)) && (key.indexOf('rec_')==0) )
                {
                    fields[key] = null;
                    delete fields[key];
                }
            }
            
            //
            // get individual visibility setting per field 
            // See rst_NonOwnerVisibility=pending and dtl_HideFromPublic=1
            //
            var fields_visibility = this._editing.getFieldsVisibility(); 
            
            var request = {ID: this._currentEditID, 
                           RecTypeID: this._currentEditRecTypeID, 
                           URL: rec_URL,
                           OwnerUGrpID: rec_OwnerUGrpID,
                           NonOwnerVisibility: rec_NonOwnerVisibility,
                           NonOwnerVisibilityGroups: rec_NonOwnerVisibilityGroups,
                           ScratchPad: rec_ScratchPad,
                           details: fields, //it will be encoded in encodeRequest
                           details_visibility: fields_visibility}; //{dty_ID:[1,1,0,0,1],.....  } 
            
            if(fields['no_validation']){
                request['no_validation'] = 1;
            }
       
            //keep current overflow position
            this._keepYPos = this.editForm.scrollTop();
        
            that.onEditFormChange(true); //forcefully hide all "save" buttons
            
            var dlged = that._getEditDialog();
            if(dlged) window.hWin.HEURIST4.msg.bringCoverallToFront(dlged);
    
            window.hWin.HAPI4.RecordMgr.saveRecord(request, 
                    function(response){
                        
                        window.hWin.HEURIST4.msg.sendCoverallToBack();
                        
                        if(response.status == window.hWin.ResponseStatus.OK){
                            
                            that._editing.setModified(false); //reset modified flag after save
                            
                            //var recID = ''+response.data[0];
                            var rec_Title = response.rec_Title;
                            
                            var saved_record = that._currentEditRecordset.getFirstRecord();
                            that._currentEditRecordset.setFld(saved_record, 'rec_Title', rec_Title);
                            var DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'];
                            if(DT_NAME>0 && fields && fields[DT_NAME]){
                                that._currentEditRecordset.setFld(saved_record, DT_NAME, fields[DT_NAME]);    
                            }
                            

                            //that._afterSaveEventHandler( recID, fields);
                            //
                            if(that.options.selectOnSave==true){
                                that._additionWasPerformed = true;
                            }
                            
                            if($.isFunction(afterAction)){
                               
                               afterAction.call(); 
                                
                            }else if(afterAction=='close'){

                                that._currentEditID = null;
                                /*A123  remarked since 
                                 triggered in onClose event */
                                if(that.options.selectOnSave==true){
                                    that.options.select_mode = 'select_single'
                                    that.selectedRecords(that._currentEditRecordset); //it calls that._selectAndClose();
                                }else{
                                    that.closeEditDialog();               
                                }
                                    
                                
                            }else if(afterAction=='newrecord'){
                                that._initEditForm_step3(-1);
                            }else{
                                //reload after save
                                that._initEditForm_step3(that._currentEditID)
                            }
                            
                            window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Record has been saved'));
                            
                        }else{
                            that.onEditFormChange(); //restore save buttons visibility
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
    },   
    
    //
    //
    //
    onEditFormChange:function(changed_element){
        
        var that = this;
		
        var force_hide = (changed_element===true); //hide save buttons
        
        var mode = 'hidden';
        if(force_hide!==true){
            var isChanged = this._editing.isModified() || this._updated_tags_selection!=null;
            mode = isChanged?'visible':'hidden';
            
            if(isChanged && changed_element){  // && changed_element.options
                
                //special case for tiled image map source - if file is mbtiles - assign tiler url 
                if(changed_element.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_FILE_RESOURCE'] && 
                   changed_element.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_IMAGE_SOURCE']){
                       
                      //check extension - only mbtiles allowed
                      var val = changed_element.getValues();
                      if(val && val.length>0 && !window.hWin.HEURIST4.util.isempty(val[0])){
                            var ext = window.hWin.HEURIST4.util.getFileExtension(val[0]['ulf_OrigFileName']);
                            if(ext=='mbtiles'){
                                var ulf_ID = val[0]['ulf_ID'];
                                var url =  window.hWin.HAPI4.baseURL + '`mbtiles`.php?/' + window.hWin.HAPI4.database + '/ulf_'+ulf_ID;
                                this._editing.setFieldValueByName(window.hWin.HAPI4.sysinfo['dbconst']['DT_SERVICE_URL'], url);
                                this._editing.setFieldValueByName(window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_IMAGE_LAYER_SCHEMA'], 'zoomify'); //2-550
                                this._editing.setFieldValueByName(window.hWin.HAPI4.sysinfo['dbconst']['DT_MIME_TYPE'], 'image/png'); //2-540
                            }
                      }
                      
                }else if(changed_element.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_SERVICE_URL'] && 
                            changed_element.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_TILED_IMAGE_SOURCE']){

                      var val = changed_element.getValues();
                      if(val && val.length>0 && !window.hWin.HEURIST4.util.isempty(val[0])){
                          
                            var mimetype = val[0]['ulf_MimeExt'];
                            if(mimetype=='image/jpg') mimetype=='image/jpeg';
                            var ele = this._editing.getInputs( window.hWin.HAPI4.sysinfo['dbconst']['DT_MIME_TYPE'] );
                            if(ele.length>0){
                                ele = $(ele[0]);
                                var idx = ele.find('option:contains("'+mimetype+'")').index();
                                ele[0].selectedIndex = idx;
                                if( ele.hSelect('instance')!==undefined) ele.hSelect('refresh');
                            }
                      } 
                      
                }else if(changed_element.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_DATA_SOURCE'])
                {
                    //
                    //get name and bbox from map source and assign to map layer fields
                    //
                    var val = changed_element.getValues();
                    if(val && val.length>0 && !window.hWin.HEURIST4.util.isempty( val[0] )){
                        var _recID = val[0];
                        var dtId_Name = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'];
                        var dtId_Geo = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'];
                        var sName = '', sGeo = '';
                        //find values
                        var that = this;
                        var ele = that._editing.getFieldByName( dtId_Name );
                        if(ele){
                            var vals = ele.editing_input('getValues');
                            sName = vals[0];
                        }
                        ele = that._editing.getFieldByName( dtId_Geo );
                        if(ele){
                            var vals = ele.editing_input('getValues');
                            sGeo = vals[0];
                        }
                        if(window.hWin.HEURIST4.util.isempty(sName) ||
                           window.hWin.HEURIST4.util.isempty(sGeo)){
                               
                           //search for values    
                           window.hWin.HAPI4.RecordMgr.search({q: 'ids:'+_recID, w: "e", f:"complete", l:1}, 
                                function(response){ 
                               
                                    if(response!=null && response.status == window.hWin.ResponseStatus.OK){
                                        var recset = new hRecordSet(response.data);
                                        var rec = recset.getFirstRecord();
                                        if(window.hWin.HEURIST4.util.isempty(sName)){
                                            var val = recset.fld(rec, dtId_Name);    
                                            that._editing.setFieldValueByName(dtId_Name, val);
                                        }
                                        
                                        if(window.hWin.HEURIST4.util.isempty(sGeo)){
                                            var val = recset.fld(rec, dtId_Geo);    
                                            that._editing.setFieldValueByName(dtId_Geo, val);
                                        }                                                
                                    }
                                
                                });
                        }
                    }
                    
                }else if(changed_element.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_LAYER'])
                {
                    //
                    // calculate summary extent of all layers and assign to map document extent
                    //
                    var recIds = changed_element.getValues();
                    if(recIds && recIds.length>0 && recIds[0]>0){
                            //mapdocument extent
                            var that = this;
                            var dtId_Geo = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'];
                            var ele = that._editing.getFieldByName( dtId_Geo );
                            if(ele){
								
                                function templateimport_link() {
                                    var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
                                    $dlg.dialog( "close" );

                                    that.closeEditDialog();
                                    // Open Design->Browse Templates
                                    window.hWin.HAPI4.LayoutMgr.executeCommand('mainMenu', 'menuActionById', 'menu-structure-import');

                                    return;
                                }								
								
                                var mapdoc_extent = null;
                                
                                var vals = ele.editing_input('getValues');
                                if(vals[0]) mapdoc_extent = window.hWin.HEURIST4.geo.getWktBoundingBox(vals);
                            
                                //search for values    
                                window.hWin.HAPI4.RecordMgr.search({q: 'ids:'+recIds.join(','), w: "e", f:dtId_Geo}, 
                                    function(response){ 

                                        if(response!=null && response.status == window.hWin.ResponseStatus.OK){
                                            var summary_ext = [];
                                            var recset = new hRecordSet(response.data);
                                            recset.each(function(recID, rec){
                                                var layer_extent2 = recset.fld(rec, dtId_Geo);
                                                var layer_extent = window.hWin.HEURIST4.geo.getWktBoundingBox([layer_extent2]);
                                                                    //recset.getFieldGeoValue(rec, dtId_Geo)); 
                                                if(layer_extent){
                                                    summary_ext.push( layer_extent );
                                                }
                                            });
                                            summary_ext = window.hWin.HEURIST4.geo.mergeBoundingBox(summary_ext);
                                            summary_ext = window.hWin.HEURIST4.geo.boundingBoxToWKT(summary_ext);
                                            if(summary_ext){
                                                that._editing.setFieldValueByName(dtId_Geo, 'pl '+summary_ext);
                                                /*if(mapdoc_extent)
                                                {
                                                    if(!window.hWin.HEURIST4.geo.isEqualBoundingBox(mapdoc_extent, summary_ext))
                                                    {
                                                    }
                                                }else{
                                                    
                                                }*/ 
                                            }
                                        }

                                });
                            
                            }else{
                                
                                window.hWin.HAPI4.SystemMgr.checkPresenceOfRectype('3-1019', 2,
                                                        'Map document record must have Bounding Box field!',
                                        function(){
                                            
                                                that.saveQuickWithoutValidation( function(){ //save without validation
                                                    that._editing.initEditForm(null, null); //clear edit form
                                                    that._initEditForm_step3(that._currentEditID); //reload edit form                       
                                                } );
                                        },
                                        true //force refresh
                                    );
                                
                            }
                    }
                    
                }else{
                    //if this is parent-child pointer AUTOSAVE
                    var parententity = changed_element.f('rst_CreateChildIfRecPtr');                
                    if(parententity==1){
                        //get values without validation
                        var fields = this._editing.getValues(false);
                        var that = this;
                        fields['no_validation'] = 1; //do not validate required fields
                        this._saveEditAndClose( fields, function(){ //save without validation
                            that._editing.setModified(true); //restore flag after autosave
                            that.onEditFormChange();
                        });
                        return;                    
                    }
                }
            }
        }
        
        //show/hide save buttons
        if(this._toolbar){
            var ele = this._toolbar;
            /*ele.find('#btnRecCancel').css('visibility', mode);
            ele.find('#btnRecSaveAndNew').css('visibility', mode);
            ele.find('#btnRecSave').css('visibility', mode);
            ele.find('#btnRecSaveAndClose').css('visibility', mode);*/
            
            //window.hWin.HEURIST4.util.setDisabled(ele.find('#btnRecDuplicate'), (mode=='hidden'));
            //window.hWin.HEURIST4.util.setDisabled(ele.find('#btnRecSaveAndNew'), (mode=='hidden'));
            
            window.hWin.HEURIST4.util.setDisabled(ele.find('#btnRecCancel'), (mode=='hidden'));
            window.hWin.HEURIST4.util.setDisabled(ele.find('#btnRecSaveAndClose'), (mode=='hidden'));
            
            //window.hWin.HEURIST4.util.setDisabled(ele.find('#btnRecSave'), (mode=='hidden'));
            
            //save buton is always enabled - just greyout in nonchanged state
            if(mode=='hidden'){
                ele.find('#btnRecSave').css({opacity: '.35'});  //addClass('ui-state-disabled'); 
            }else{
                ele.find('#btnRecSave').css({opacity: '1'}); //.removeClass('ui-state-disabled'); // ui-button-disabled
            }
        
        }

    },
    
    //
    // 1. show-hide optional fields
    // 2. add menu button on top of screen
    // 3. add record title at the top
    // 4. init cms open edit listener 
    // 5. init rts_editor action buttons 
    //    
    _afterInitEditForm: function(){

        var that = this;
        
        var ishelp_on = (this.usrPreferences['help_on']==true || this.usrPreferences['help_on']=='true');
        var isfields_on = this.usrPreferences['optfields']==true || this.usrPreferences['optfields']=='true';
        var btn_css = {'font-weight': 'bold', color:'#7D9AAA', background:'none' }; //#ecf1fb

        var swf_rules_mode = 'on';
        if(this.usrPreferences['swf_rules_mode'] && this.usrPreferences['swf_rules_mode'][this._currentEditRecTypeID]){
            swf_rules_mode = this.usrPreferences['swf_rules_mode'][this._currentEditRecTypeID];
        }
        
        // Icon
        var ph_gif = window.hWin.HAPI4.baseURL + 'hclient/assets/16x16.gif';
        var rt_icon = window.hWin.HAPI4.iconBaseURL+this._currentEditRecTypeID;

        if( this.element.find('.chb_opt_fields').length==0 )
        {  //not inited yet

            this._swf_rules = $Db.getSwfByRectype(this._currentEditRecTypeID);
            if(this._swf_rules){
                this._swf_rules.sort(function(a,b){  
                        return (Number(a['swf_Order'])<Number(b['swf_Order'])?-1:1);
                });
            }
        
            $('<div style="display:table;min-width:575px;width:100%">'
             +'<div style="display:table-cell;text-align:left;padding:10px 0px 5px 15px;">'

                +'<div style="padding-right:50px;display:inline-block" class="rt-info-header">'
                    +'<img src="'+ph_gif
                        +'" width=36 height=36 class="rt-icon" style="padding:2px;background-size: 28px 28px;vertical-align:middle;margin: 2px 10px 2px 4px;'
                        +'background-image:url(\'' + rt_icon + '\');"/>'
                    + '<span style="display:inline-block;vertical-align:middle;font-size:larger;font-weight:bold;max-width:25ex;" '
                    + 'class="truncate" title="'+ $Db.rty(this._currentEditRecTypeID,'rty_Name') +'">'
                        + $Db.rty(this._currentEditRecTypeID,'rty_Name')
                    + '</span>'
                +'</div>'

                +'<div style="padding-right:20px;padding-left:5px;display:inline-block">'
                    +'<span class="btn-edit-rt2 btns-admin-only" style="font-size:larger"></span>'
                    +'<span class="btn-edit-rt-back" style="font-size:larger;display:none">Close</span>' //Back to Whole Form
                +'</div>'
             
                +'<div style="display:inline-block;padding-left:20px">'
                    +'<label><input type="checkbox" class="chb_show_help" '
                        +(ishelp_on?'checked':'')+'/>Show help</label><span style="display:inline-block;width:40px"></span>'
                    +'<label class="lbl_opt_fields"><input type="checkbox" class="chb_opt_fields" '
                        +(isfields_on?'checked':'')+'/>Optional fields</label>'
                    +'<span style="display:inline-block;width:40px"></span>'
                    +'<span class="div_workflow_stages"><label>Workflow stage popup: </label>'
                    +'<select class="sel_workflow_stages">'
                        +'<option value="new">New records only</option>'
                        +'<option value="on">New and existing records</option>'
                        +'<option value="off">OFF</option></select></span>'
                +'</div>'

                +'<div style="padding:10px 50px 0px 0px;float:right">'
                    +'<span class="btn-edit-rt btns-admin-only">Attributes</span>'
                    +'<span class="btn-edit-rt-titlemask btns-admin-only">Edit title mask</span>'
                    +'<span class="btn-edit-rt-template btns-admin-only">Template</span>'
                    +'<span class="btn-bugreport">Bug report</span>'
                +'</div>'
                +'<div class="btn-lookup-values" style="padding-top:10px;padding-left: 24px;">'
                    //+'<span style="font-size:larger" title="Lookup external service">Lookup value</span>'
                +'</div>'
                
             +'</div></div>').insertBefore(this.editForm.first('fieldset'));

            if(window.hWin.HAPI4.is_admin() && this.options.allowAdminToolbar!==false)
            {
                
                this.element.find('.btns-admin-only').show();

                this.element.find('.btn-edit-rt').button({icon:'ui-icon-pencil'}).css(btn_css).css({'padding':'4.5px'})
                        .click(function(){that.editRecordTypeAttributes();}); //was editRecordType(false)
                
                var btn = this.element.find('.btn-edit-rt2');        
                if(this.options.edit_structure){
                    
                    var cont = this.element.find('.editStructureHeader').css({overflow:'hidden'});
                    btn.hide();

                    /*  2021-01-02
                    btn.button({icon:'ui-icon-pencil',label:'Edit attributes<br>(name, icon ...)'})
                            .css(btn_css)
                            .width(150).show()
                            .click(function(){that.editRecordTypeAttributes(true);});

                    var btn2 = btn.clone().css({'float':'left','margin':'2px 20px',background:'#DAD0E4'})
                                .click(function(){that.editRecordTypeAttributes(true);})
                                .appendTo(cont);    
                    btn2.find('.ui-button-icon')
                            .css({'font-size':'25px','float':'left',width:'25px',height:'25px','margin-top':'0px'});
                    */
                    
                    $('<div style="float:left;">'
                        +'<span style="margin-top: 15px;display: inline-block;font-size: 12px;">Fields for: </span>'
                        +'<h1 style="float:right;margin:10px;">'
                        +$Db.rty(this._currentEditRecTypeID,'rty_Name')+'</h1></div>')
                        .appendTo(cont);      
                    
                    $('<span>').addClass('heurist-helper3').css({'float':'left','margin': '10px'}).html(
                        'This is an empty record. Test data entry as you develop the structure. '
                        +'<br>If you want to retain the data entered, hit [Save data], otherwise [Close]')  
                            .appendTo(cont);      
                
                    this.element.find('.btn-edit-rt').hide(); //Attributes button next to Edit title mask
                }else{
                    btn.button({icon:'ui-icon-gear',label:'<span style="display:inline-block;margin-top:5px;">Modify structure</span>'})
                            .css(btn_css)
                            .width(130)
                            .click(function(){that.editRecordType(true);});
                }                        
                        
                btn.find('.ui-button-icon')
                            .css({'font-size':'25px','float':'left',width:'25px',height:'25px','margin-top':'0px'});
                        
                this.element.find('.btn-edit-rt-titlemask').button({icon:'ui-icon-tags'})  //marker, pencil, tags
                        .css(btn_css).click(function(){that.editRecordTypeTitle();});
                
                this.element.find('.btn-edit-rt-template').button({icon:'ui-icon-arrowthickstop-1-s'})
                        .css(btn_css).click(function(){
                            window.hWin.HEURIST4.ui.showRecordActionDialog('recordTemplate'
                                    ,{recordType:that._currentEditRecTypeID,
                                      default_palette_class: 'ui-heurist-design'});});
                
            }else{
                this.element.find('.btns-admin-only').hide();
            }
            
            //lookup external values
            //there is 3d party service for lookup values
            this._setupExternalLookups();           
            
            //bug report
            this.element.find('.btn-bugreport').button({icon:'ui-icon-bug'})
                .css(btn_css).click(function(){ window.hWin.HEURIST4.ui.showEntityDialog('sysBugreport'); });
                
                
            this.element.find('.chb_show_help') //.attr('checked', ishelp_on)
                        .change(function( event){
                            var ishelp_on = $(event.target).is(':checked');
                            that.usrPreferences['help_on'] = ishelp_on;
                            window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, $(that.element));
                        });
            
            
            this.element.find('.chb_opt_fields') //.attr('checked', isfields_on)
                        .change(function( event){
                            var isfields_on = $(event.target).is(':checked');
                            that.usrPreferences['optfields'] = isfields_on;
                            //that.saveUiPreferences();
                            $(that.element).find('div.optional').parent('div[data-dtid]').css({'display': (isfields_on?'table':'none')} ); 
                            $(that.element).find('div.optional_hint').css({'display': (isfields_on?'none':'block')} ); 
                            
                            that._showHideEmptyFieldGroups();
                        });
                       
            
            if(this._swf_rules.length>0){           
                       
                this.element.find('.sel_workflow_stages').val(swf_rules_mode);
                            
                this.element.find('.sel_workflow_stages')
                .change(function( event ){
                    if(!that.usrPreferences['swf_rules_mode']) that.usrPreferences['swf_rules_mode'] = {};
                    that.usrPreferences['swf_rules_mode'][that._currentEditRecTypeID] = $(event.target).val();
                });
            
            }else{
                this.element.find('.div_workflow_stages').hide();
            }
            
        }
        else{
            this.element.find('.chb_opt_fields').prop('checked', isfields_on);
            this.element.find('.chb_show_help').prop('checked', ishelp_on);
        }

        //add resizing buttons to dialog title bar
        if(this._as_dialog){

            $('<span>', {id: 'btn_Fullscreen'}).appendTo(this._as_dialog.parent().find('.ui-dialog-titlebar'));
            $('<span>', {id: 'btn_Standard'}).appendTo(this._as_dialog.parent().find('.ui-dialog-titlebar'));

            this._as_dialog.parent().find('#btn_Fullscreen').button({label:window.hWin.HR('Fullscreen')}).css({
                margin: '-0.9em 0 0 0',
                right: '12.5em',
                top: '45%',
                position: 'absolute',
                background: 'none',
                color: 'white'
            }).click((e) => {
                that._setDialogSize(true);
            });

            this._as_dialog.parent().find('#btn_Standard').button({label:window.hWin.HR('Standard')}).css({
                margin: '-0.9em 0 0 0',
                right: '5.5em',
                top: '45%',
                position: 'absolute',
                background: 'none',
                color: 'white'
            }).click((e) => {
                that._setDialogSize(false);
            });

            this._as_dialog.parent().css('box-shadow', '2px 3px 10px #00000080');
        }

        //add record title at the top ======================
        
            //this.editFormPopup.css('top',0);
            
        this.editHeader = this.element.find('.editHeader');

        var sheader = '<div style="text-align:left;min-height:25px;display:inline-flex;align-items:center;" class="edit-record-title">';  ///class="ui-heurist-header2" 
        
        if(this.options.edit_structure){
            
            sheader = sheader  
            +'<span style="padding-right:10px;">'
            +'Modifying record structure for</span>'
            +'<h3 style="max-width:900px;margin:0;">'
            +$Db.rty(this._currentEditRecTypeID,'rty_Name')
            +'&nbsp;&nbsp;( '+this._currentEditRecTypeID+' / '+$Db.getConceptID('rty',this._currentEditRecTypeID)+' )'
            +'</h3>';
            
        }else{
            
            //define header - retype name and record title
            sheader = sheader
                    + '<span style="display:inline-block;vertical-align:middle">'
                        + $Db.rty(this._currentEditRecTypeID,'rty_Name')
                    + '</span>';

            if(!this._isInsert){
                sheader = sheader
                    + '&nbsp;<span style="padding:0 20px;">ID: '+this._currentEditID
                    + '</span><h3 style="max-width:900px;margin:0;" class="truncate">'
                    + window.hWin.HEURIST4.util.stripTags(this._getField('rec_Title'),'u, i, b, strong')+'</h3>';
            }
        }
        sheader = sheader + '</div>';

        if(this._as_dialog){
                
            var ele = this._as_dialog.parent().find('.ui-dialog-titlebar')
                .addClass('ui-heurist-header');

            if(this.options.edit_structure){
                this._as_dialog.parent().addClass('ui-heurist-design');
            }else{
                this._as_dialog.parent().addClass('ui-heurist-populate'); //was explore
            }
            

            
            ele.find('.ui-dialog-title').hide();
            ele.find('.edit-record-title').remove(); //remove previous
            ele.append(sheader);
                
            this.editFormPopup.css('top',0);   
            if(this.editHeader) this.editHeader.hide();
        }else{
            
            this.element.addClass('ui-heurist-populate'); //explore
            this.editHeader.addClass('ui-heurist-header');
            //remove previous
            this.editHeader.find('.edit-record-title').remove();
            $(sheader).appendTo(this.editHeader);
            
            this.editHeader.css('height',30);
            this.editFormPopup.css('top',34);   
        }
        
        if(!this._as_dialog){
            this.element.addClass('manageRecords');                
            
            if(this.options.entity.helpContent){
                var helpURL = window.hWin.HRes( this.options.entity.helpContent )+' #content';
                window.hWin.HEURIST4.ui.initDialogHintButtons(this.element, '.ui-heurist-header2', //where to put button
                             helpURL);    
            }
        }
        
        //need refresh layout to init overflows(scrollbars)        
        if(this.editFormSummary && this.editFormSummary.length>0){
             this.editFormPopup.layout().resizeAll();
        }
        
        if(this.element.find('.btn-modify_structure').length>0){
            this.element.find('.btn-modify_structure').click(function(){that.editRecordType(true);});
        }


        //show-hide optional fields 
        $(this.element).find('div.optional').parent().css({'display': (isfields_on?'table':'none')} ); 
        $(this.element).find('div.optional_hint').css({'display': (isfields_on?'none':'block')} ); 
        
        //init cms open edit listener
        $(this.element).find('span[data-cms-edit="1"]').click(function(event){
            that._saveEditAndClose(null, function(){
                that.closeEditDialog();
                window.hWin.HEURIST4.ui.showEditCMSwin( {record_id:that._currentEditID,  
                    field_id:$(event.target).attr('data-cms-field')} );
            });
        });

//Artem: move it to browseRecords - avoid open select record popup if record can not be saved
        if(false && window.hWin.HEURIST4.util.isempty(that._getField('rec_Title'))){
            this._on($(this.element).find('.child_rec_fld'), {
                click: (event) => {
    
                    if(window.hWin.HEURIST4.util.isempty(that._getField('rec_Title'))){ // Save record first without validation, only if this is a new record

                        var fields = that._editing.getValues(false);
                        fields['no_validation'] = 1; //do not validate required fields
                        that._saveEditAndClose( fields, null);
                    }
                }
            });
        }

        //5. init rts_editor action buttons 
        if(this.options.rts_editor){
            //var that = this;
            $(this.element).find('div[data-dtid]').each(function(idx, item){
                var dtId = parseInt($(item).attr('data-dtid'));
                if(dtId>0){
                    that._createRtsEditButton(dtId, item);

                    if(dtId != 9999999 && $Db.rst(that._currentEditRecTypeID, dtId) === null){ // non-standard field, add 'Add to record structure' button
                        that._createNonStandardField(dtId, $(item).find('div.input-cell'));
                    }
                }
            });
            //add action button for accordion panels
            $(this.element).find('div.tab-separator-helper').each(function(idx, item){
                var dtId = parseInt($(item).attr('separator-dtid'));
                if(dtId>0){
                    that._createRtsEditButton(dtId, item);
                }
            });
            
            
            //extend separator help left padding
            $(this.element).find('.separator-helper').css({'padding-left':'52px'});
            
            //init back button - if there is opened rts editor
            var btn = this.element.find('.btn-edit-rt-back');
            
                
            if(btn){
                if(that.options.edit_structure){
                    btn.hide();
                }else{
                    btn.button({icon:'ui-icon-gear-crossed'}).show()
                        .one('click', function(){
                            that.editFormPopup.layout().hide('west');
                            that.options.rts_editor = null;
                            that.reloadEditForm( true );
                        });
                    if(btn_css) btn.css(btn_css);
                }
            }
            if(!this.options.edit_structure){
                this.element.find('.btn-edit-rt2').hide();
                this.element.find('.rt-info-header').hide();
            }
            
            //switch on optional fields and disable checckbox
            this.element.find('.chb_opt_fields').prop('checked',true).attr('disabled', true).change();
            
            //show the attributes and title mask dialog
            this.element.find('.btn-edit-rt').show();
            this.element.find('.btn-edit-rt-titlemask').show();			
			
            //hide message about forbidden fields
            $(this.element).find('.hidden_field_warning').hide();
            
            //show forbidden fields as disabled - except gearwheel
            var ele_fb = $(this.element).find('div.forbidden');
            ele_fb.css({'opacity':'0.3'});   //header
            ele_fb.next().css({'opacity':'0.3'}); //repeat btn 
            var ele_id = ele_fb.next().next().css({'opacity':'0.3'}); //input-cell 
            //ele_id.find('input').css('border','1px dotted red');
            ele_id.find('input,textarea,button,.ui-selectmenu-button').css('border','1px dotted red');
            
            //reduce width of header
            $(this.element).find('.separator').css({width: '80%', display: 'inline-block'});
            $(this.element).find('.separator-hidden').css({width: '80%', display: 'inline-block'});
            
            //display message at bottom
            $('<div id="mod-struct-help" style="font-size:1.2em;margin-top:30px;">Use the gearwheel <span class="ui-icon ui-icon-gear"></span> to add/edit fields and headings</div>')
            .appendTo(this.editForm.last('.editForm.recordEditor'));			
            
            //hide swf mode selector
            this.element.find('.div_workflow_stages').hide();            
			
            //if record type has been changed - reload rts_editor
            this._reloadRtsEditor();

            // add '+' button to end of tabs, creates new tab header
            this._addNewTabButton();

            // Highlight current focus in tree structure
            this._on(this.editForm.find('div[data-dtid] input, div[data-dtid] select, div[data-dtid] textarea'), {
                focus: (event) => { // Remove previous node focus
                    let $target_parent = $(event.target).parents('div[data-dtid]:first');
                    if($target_parent.length == 1){
                        that.options.rts_editor.manageDefRecStructure('highlightNode', $target_parent.attr('data-dtid'));
                    }
                },
                blur: (event) => { // Remove node focus

                    let $target_ele = $(event.target);
                    let unselect_only = false;
                    if($target_ele.is('select')){ // Check if selectmenu is still visible
                        let $sel = $target_ele.parent().find('select.enum-selector-main');
                        if($sel.length == 1){
                            let id = $sel.attr('id');
                            if($sel.parent().find('#'+id+'-menu').is(':visible')){
                                setTimeout(($ele)=>{ $ele.blur(); }, 100, $target_ele);
                                return;
                            }
                            that.options.rts_editor.manageDefRecStructure('highlightNode', $target_ele.parents('div[data-dtid]:first').attr('data-dtid'), true);
                            return;
                        }
                    }

                    that.options.rts_editor.manageDefRecStructure('highlightNode', null);
                }
            });
        }else{

            $(this.element).find('.separator-hidden').hide();

            this.element.find('.rt-info-header').show();
            this.element.find('.btn-edit-rt2').show();
            this.element.find('.btn-edit-rt-back').hide();
            this.element.find('.chb_opt_fields').attr('disabled', false);
            this.element.find('.lbl_opt_fields').show();
            this.element.find('.sel_workflow_stages').attr('disabled', false);
            this.element.find('.lbl_workflow_stages').show();
            
            this.element.find('.btn-edit-rt').hide();
            this.element.find('.btn-edit-rt-titlemask').hide();  
            
            $(this.element).find('div.forbidden').parent().css({'display':'none'} ); 

            if(this._swf_rules.length>0){
                this.element.find('.div_workflow_stages').show();
            }
            
            
            //to save space - hide all fieldsets without visible fields
            this._showHideEmptyFieldGroups();

        }

        
        window.hWin.HEURIST4.ui.applyCompetencyLevel(-1, this.editForm);
        //show-hide help text below fields - it overrides comptency level
        window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, $(this.element));
        
        this._afterInitEditForm_restoreGroupStatus();
        
        //
        //
        //        
        this.onEditFormChange();
        
        window.hWin.HAPI4.SystemMgr.user_log('editRec', this._currentEditID); // log action
        
        if($.isFunction(this.options.onInitEditForm)){
            this.options.onInitEditForm.call();
        }
        
                    
    },//END _afterInitEditForm
    
    //
    //
    //
    _reloadRtsEditor: function(){            

            if(this.options.rts_editor){
                 if(this.options.rts_editor.manageDefRecStructure('option','rty_ID')==this._currentEditRecTypeID){
                     return;
                 }
            } 

            var $structure_editor = this.element.find('.editStructure');
            $structure_editor.children().remove();
            var rts_edit_container = $('<div>').appendTo($structure_editor);
            //show left layout panel, hide summary panel
            var popup_options = {
                isdialog: false,
                container: rts_edit_container,
                select_mode: 'manager',
                rty_ID: this._currentEditRecTypeID,
                rec_ID_sample: this._currentEditID,
                external_preview: this.element   //send this widget to use as preview
            };
            this.options.rts_editor = rts_edit_container;
            
            window.hWin.HEURIST4.ui.showEntityDialog('DefRecStructure', popup_options); 
    },
    
    //
    //
    //
    showOptionalFieds: function(isShow){
        this.element.find('.chb_opt_fields').prop('checked', isShow).change();
    },
    
    //
    //to save space - hide all fieldsets without visible fields
    //
    _showHideEmptyFieldGroups: function(){
        
        this.editForm.find('fieldset').each(function(idx,item){
            
            //ignore accordion and tabs
            if($(item).attr('role')!=='tabpanel'){
            
                if($(item).children('div:visible').length>0){
                    $(item).show();
                }else{
                    $(item).hide();
                    $(item).children('div').each(function(){
                        if($(this).css('display')!='none'){
                            $(item).show();
                            return false;
                        }
                    });
                }
            }
        });
        
        //if show optional is off and all fields in section between headers are invisible
        var isfields_on = this.usrPreferences['optfields']==true || this.usrPreferences['optfields']=='true';
        if(!isfields_on){
            var sep = null; //current separator(header)
            //var need_show_hint = false;
        
            this.editForm.children().each(function(){
                
                //$(this).is('fieldset')
                
                if($(this).is('h4.separator')){
                    sep = $(this);
                }else if($(this).is('fieldset') && sep!=null){
                    
                    //!$(this).is(':visible') && 
                    if($(this).children(':visible').length==0){ //none visible
                         
                        //fieldset may have invisible fields: optional or forbidden
                        var need_show_hint = ($(this).find('div > div.optional').length>0);
                    
                        //if all fields are hidden and there are optional
                        if(need_show_hint){
                            $('<span class="show_optional_hint" style="padding-left: 184px;">'
                            +'This section contains optional fields.</span>')
                                .insertAfter(sep.next());   //insert after helper
                        }
                    }
                    sep = null;
                }
                
            });
        }else{
            this.editForm.find('span.show_optional_hint').remove();
        }
        
    },
    
    //
    //
    //
    getUiPreferences: function(){
        this.usrPreferences = window.hWin.HAPI4.get_prefs_def('prefs_'+this._entityName, this.defaultPrefs);
        if(this.usrPreferences.width<600) this.usrPreferences.width=600;
        if(this.usrPreferences.height<300) this.usrPreferences.height=300;
        if (this.usrPreferences.width>this.defaultPrefs.width) this.usrPreferences.width=this.defaultPrefs.width;
        if (this.usrPreferences.height>this.defaultPrefs.height) this.usrPreferences.height=this.defaultPrefs.height;
        return this.usrPreferences;
    },
    
    //
    // save width,heigth and summary tab prefs
    //
    saveUiPreferences: function(){
        
        var that = this;
        
        var dwidth = this.defaultPrefs['width'],
            dheight = this.defaultPrefs['height'],
            activeTabs = [],
            help_on = true,
            optfields = true;
            
        var params = this.getUiPreferences();    
            
        if(that.editFormSummary && that.editFormSummary.length>0){
            
                that.editFormSummary.find('.summary-accordion').each(function(idx,item){
                    var active = $(item).accordion('option','active');
                    if(active!==false){
                        activeTabs.push(String(idx));
                    }
                            
                });

                var myLayout = that.editFormPopup.layout();                
                
                params.summary_closed = myLayout.state.east.isClosed;;
                params.summary_width = myLayout.state.east.size;
        }
        if(that.options.rts_editor){
                var myLayout = that.editFormPopup.layout();                
                params.structure_width = myLayout.state.west.size;
                params.structure_closed = myLayout.state.west.isClosed?1:0;
        }
        
        help_on = that.element.find('.chb_show_help').is(':checked');
        optfields = that.element.find('.chb_opt_fields').is(':checked');
            
        
        if(this.options.edit_mode=='editonly'){
            if(that.options.isdialog){
                dwidth  = that._as_dialog.dialog('option','width');
                dheight = that._as_dialog.dialog('option','height');
                
                var cnt = $('div.ui-dialog[posid^="edit'+this._entityName+'"]').length;
                if(cnt==1){ //save position
                    var dlged = that._as_dialog.parent('.ui-dialog');
                    params['top'] = parseInt(dlged.css('top'),10);
                    params['left'] = parseInt(dlged.css('left'), 10);
                }
                
            }else{
                //dwidth  = window.innerWidth+20;
                //dheight = window.innerHeight+46;
            }      
                  
        }else                
        if(that._edit_dialog && that._edit_dialog.dialog('isOpen')){
            dwidth  = that._edit_dialog.dialog('option','width'); 
            dheight = that._edit_dialog.dialog('option','height');
        } 

        if(!(this.options.forced_Width>0)){
            params.width = dwidth;    
        }
        params.height = dheight;
        params.help_on = help_on;
        params.optfields = optfields;
        params.summary_tabs = activeTabs;

        window.hWin.HAPI4.save_pref('prefs_'+this._entityName, params);
        
        return true;
    },

    //
    // Set popup height + width to value based on current window
    //
    _setDialogSize: function(is_full = false){

        let width = window.hWin.innerWidth * 0.8;
        let height = window.hWin.innerHeight * 0.8;

        if(is_full){
            width = window.hWin.innerWidth * 0.95;
            height = window.hWin.innerHeight * 0.95;
        }

        // Update width + height, and reset dialog's position to center
        if(this._as_dialog){
            this._as_dialog.dialog('option', 'width', width);
            this._as_dialog.dialog('option', 'height', height);
            this._as_dialog.dialog('option', 'position', {my: 'center', at: 'center', of: window.hWin});
        }else if(this._edit_dialog){
            this._edit_dialog.dialog('option', 'width', width);
            this._edit_dialog.dialog('option', 'height', height);
            this._edit_dialog.dialog('option', 'position', {my: 'center', at: 'center', of: window.hWin});
        }else{
            return;
        }

        //this._onDialogResize();
        this.editFormPopup.layout().resizeAll(); // resize layout
    },

    //
    // Add plus symbol to end of each tab group, 
    // when clicked (activated) begins process of adding new tab to group
    //
    _addNewTabButton: function(){

        if(this._currentEditRecTypeID <= 0){
            return;
        }

        var that = this;

        var $tabs = this.editForm.find('div.ui-tabs[data-group-dtid]');

        if($tabs.length > 0){

            $tabs.each(function(idx, group){

                var $group = $(group);
                var $tabs = $group.find('ul[role="tablist"]');
                var last_dtid = $group.find('fieldset:last-child div[data-dtid]:last-child').attr('data-dtid');

                var $empty_cont = $('<div>').uniqueId();
                var $new_tab = $('<li>').addClass('add_new_tab').append('<a href="#'+ $empty_cont.attr('id') +'"></a>').appendTo($tabs);

                $('<span>')
                        .attr({'data-dty_ID': last_dtid, 'title': 'Click to add new tab'})
                        .addClass('ui-icon ui-icon-circlesmall-plus')
                        .css({'font-size': '275%', 'height': '20px', 'width': '20px', 'top': '8px'})
                        .clone()
                        .appendTo($new_tab.find('a'));

                $group.tabs('refresh');

                $new_tab.css({'background': '#e9e9e9', border: 'none'});

                that._on($group, {
                    'tabsbeforeactivate': function(event, ui){

                        if(event.originalEvent && ui.newTab.hasClass('add_new_tab') && event.originalEvent.type == 'click'){

                            var dt_id = ui.newTab.find('span[data-dty_ID]').attr('data-dty_ID');

                            if(dt_id == null) return false;

                            that.options.rts_editor.manageDefRecStructure('addNewSeparator', dt_id);

                            return false;
                        }else if(ui.newTab.hasClass('add_new_tab')){
                            return false;
                        }
                    }
                });
            });
        }
    },
	
    //
	// Display the record title field (cannot be edited here),
	// and, if applicable, the child record fields at the top of the form
	//
    showExtraRecordInfo: function(){

        var that = this;
        var parententity = Number(window.hWin.HAPI4.sysinfo['dbconst']['DT_PARENT_ENTITY']);

        // add new separate fieldset at the start
        var top_fieldset = $('<fieldset>').insertBefore(this.editForm.find('fieldset:first')); //.css({'background-color': '#d1e7e7', 'margin-bottom': '10px', 'padding-left': '10px'})

        var admin_override = window.hWin.HAPI4.is_admin() && !window.hWin.HAPI4.is_member(that._getField('rec_OwnerUGrpID'));

        if(admin_override){ // check if user is not owner, but is admin 

            top_fieldset.append(
                '<div style="display: table; margin-bottom: 7px">'
                    + '<div class="header" style="vertical-align: top; display: table-cell; font-size: 1.3em; font-weight: bold; width: auto;">'
                        + '<span style="color: red; font-weight: bold;">You don\'t own this record</span> (you can edit it because you are a database administrator)'
                    + '</div>'
                + '</div>');
        }

        var title_maxwidth = (top_fieldset.width() != 0) ? top_fieldset.width() : top_fieldset.parent().width();
        title_maxwidth = title_maxwidth * 0.85 - (this.options.rts_editor ? 45 : 17);

        // Display record title
        var ele = this._editing.getFieldByName('rec_Title').show().editing_input('setDisabled', true);

        // remove opacity change and set background to lighter background
        var cur_styling = ele.find('input').attr('style');
        var cur_title = this._getField('rec_Title').replace(/[\r\n]+/g, ' ');
        ele.find('input')
            .replaceWith('<div style="'+cur_styling+'background-color:#e3f0f0!important;font-size:13px;padding:3px;max-width:'+title_maxwidth+'px;width:'+title_maxwidth+'px;" class="truncate"'
                        + ' title="A title constructed from one or more fields, which is used to identify records when displayed in search results.">'+ cur_title +'</div>');

        // change label to required version, and add help icon
        ele.find('div.header')
            .attr('title', 'A title constructed from one or more fields, which is used to identify records when displayed in search results.')
            .addClass('recommended')
            .css('vertical-align', '');

        ele.find('div.header > label').text('Constructed title');

        // add gear icon that opens title mask editor
        if(window.hWin.HAPI4.is_admin() && this.options.allowAdminToolbar!==false){

            var $gear_icon = $('<span>')
                              .addClass('ui-icon ui-icon-gear')
                              .css({'color': 'rgb(125, 154, 170)', 'min-width': '22px', 'cursor': 'pointer'})
                              .attr('title', 'Open Title Mask Editor')
                              .click(function(e) { that.editRecordTypeTitle(); });

            ele.find('span.editint-inout-repeat-button').find('ui-icon').remove(); // remove repeat button
            ele.find('span.editint-inout-repeat-button').append($gear_icon); // add gear icon (edit title mask)
            ele.find('span.btn_input_clear').remove(); // remove clear button
        }

        // move rec_title field to new fieldset
        top_fieldset.append(ele);

        // check for child record field, move to new fieldset if any
        var childrec_field = this.editForm.find('div[data-dtid="'+parententity+'"]');
        if(childrec_field.length == 1){

            // Header changes
            childrec_field.find('div.header').css({'font-size': '12px'}).addClass('recommended');

            // Append to top
            top_fieldset.append(childrec_field);
        }

        var url_field = this._editing.getFieldByName('rec_URL');
        if(url_field.length == 1){
            url_field.find('div.header').css({'font-size': '12px'}).addClass('recommended');

            // Append to top
            top_fieldset.append(url_field);
        }
    },
	
    //
    // Popup that requests user to replace ambiguous dates 
    //
    _handleAmbiguousDates: function(ambiguous_dates){

        var that = this;

        if(!ambiguous_dates || ambiguous_dates.length == 0){

            // Run save again
            //that._saveEditAndClose(null, 'close');

            return;
        }

        let $dlg = null;
        let cur_date = ambiguous_dates.splice(0, 1)[0]; //unshift()

        let btns = {};
        btns[window.HR('Save')] = function(){

            let value = '';
            let com = $dlg.find('#COM').val();
            let t_date = new Temporal();
            let is_temporal = false;

            if(!window.hWin.HEURIST4.util.isempty(com)){
                t_date.addObjForString("COM", com);
                is_temporal = true;
            }

            switch (cur_date.type) {
                case 'simple':
                case 'approx':
                    
                    let date = $dlg.find('#DAT').val();
                    let approx = $dlg.find('#CIR').is(':checked');

                    if(window.hWin.HEURIST4.util.isempty(date)){
                        value = '';
                    }else if(approx){

                        t_date.setType('s');
                        t_date.addObjForString('DAT', date);
                        t_date.addObjForString("CIR", "1");

                        value = t_date.toString();
                    }else{

                        if(is_temporal){
                            t_date.setType('s');
                            t_date.addObjForString('DAT', date);

                            value = t_date.toString();
                        }else{
                            value = date;
                        }
                    }
                    break;

                case 'range':

                    let early = $dlg.find('#TPQ');
                    let late = $dlg.find('#TAQ');

                    if(window.hWin.HEURIST4.util.isempty(early) && window.hWin.HEURIST4.util.isempty(late)){
                        value = '';
                    }else if(!window.hWin.HEURIST4.util.isempty(early) && !window.hWin.HEURIST4.util.isempty(late)){

                        t_date.setType('p');
                        t_date.addObjForString('TPQ', early);
                        t_date.addObjForString('TAQ', late);

                        value = t_date.toString();
                    }else{ // treat as simple date

                        let date = !window.hWin.HEURIST4.util.isempty(early) ? early : late;
                        if(is_temporal){
                            t_date.setType('s');
                            t_date.addObjForString('DAT', date);

                            value = t_date.toString();
                        }else{
                            value = date;
                        }
                    }
                    break;
                default:
                    value = '';
                    break;
            }

            let $ele = that._editing.getFieldByName(cur_date.dtyid);
            if($ele.length > 0){

                let vals = $ele.editing_input('getValues');

                if(!window.hWin.HEURIST4.util.isempty(value)){
                    vals[cur_date.index] = value;
                }else if(vals.length > 1){ // remove
                    vals.splice(cur_date.index, 1);
                }else{
                    vals = [''];
                }

                $ele.editing_input('setValue', vals);
            }

            $dlg.dialog('close');

            that._handleAmbiguousDates(ambiguous_dates);
        };
        btns[window.HR('Close')] = function(){
            $dlg.dialog('close');
        }

        let labels = {
            title: window.HR('Fix ambiguous dates'),
            yes: window.HR('Close'),
            no: window.HR('Save')
        };

        let options = {
            'default_palette_class': 'ui-heurist-explore',
            'dialogId': 'fixing_ambig_dates'
        };

        let fld_name = $Db.rst(that._currentEditRecTypeID, cur_date.dtyid, 'rst_DisplayName');

        let content = '<div>'
                        + '<span>'
                            + 'For the field <strong>' + fld_name + '</strong><br>'
                            + 'We cannot unambiguously interpret what you mean by <strong>' + cur_date.org_value + '</strong>'
                        + '</span><br><br>';

        if(cur_date.type){

            switch (cur_date.type) {
                case 'simple':
                case 'approx':

                    let is_checked = cur_date.type == 'approx' ? 'checked="checked"' : '';
                    content += '<label> Please re-type as year only, yyyy-mm or yyyy-mm-dd <input type="text" id="DAT"></label><br>';
                    content += '<label> Is approximate? <input type="checkbox" id="CIR" ' + is_checked + '></label><br>';

                    break;
                case 'range':

                    let early = cur_date.value.TPQ ? cur_date.value.TPQ : '';
                    let late = cur_date.value.TAQ ? cur_date.value.TAQ : '';
                    content += '<span>Please re-type all dates as year only, yyyy-mm or yyyy-mm-dd</span><br>';
                    content += '<span>This date was determined to be a simple range. However, if this is an error leave either field empty</span><br><br>';
                    content += '<span style="display:inline-block; min-widht:100px;">Earliest estimate</span> <input type="text" id="TPQ" value="' + early + '"></label><br><br>';
                    content += '<label><span style="display:inline-block; min-widht:100px;">Latest estimate</span> <input type="text" id="TAQ" value="' + late + '"></label>';
                default:
                    that._handleAmbiguousDates(ambiguous_dates);
                    break;
            }
        }

        content += '<br><br><span style="vertical-align: top;">If you wish to qualify the date with a short note, enter it here </span>'
                +  '<textarea cols="30" rows="5" id="COM" style="resize: none"></textarea>';
                +  '</div>';

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(content, btns, labels, options);
    },
	
    //
    // Setup external lookup buttons, placed at top of popup/window
    //
    _setupExternalLookups: function(){

        var that = this;

        var notfound = true;
        var lookup_div = this.element.find('.btn-lookup-values');
        lookup_div.empty();
        var service_config = window.hWin.HEURIST4.util.isJSON(window.hWin.HAPI4.sysinfo['service_config']);

        if(service_config!==false){
            
            window.hWin.HAPI4.sysinfo['service_config'] = service_config;
            
            let lbl_text = "Lookup External Sources: ";

            var $ext_lookup_cont = $('<div>')
                .html(lbl_text)
                .css({'font-size': 'small'})
                .appendTo(lookup_div); 
            
            let $config_lookups = $('<span>', {title: 'Configure external lookups'})
                .addClass('ui-icon ui-icon-gear')
                .css({padding: '0 10px 0 5px', color: 'rgb(125, 154, 170)', cursor: 'pointer'})
                .appendTo($ext_lookup_cont);

            this._on($config_lookups, {
                click: () => {

                    window.hWin.HEURIST4.ui.showRecordActionDialog('lookupConfig', {
                        service_config: window.hWin.HAPI4.sysinfo['service_config'],
                        onClose: function(){
                            that._setupExternalLookups();
                        },
                        classes:{
                            "ui-dialog": "ui-heurist-design",
                            "ui-dialog-titlebar": "ui-heurist-design"
                        },
                        title: 'Lookup service configuration',
                        path: 'widgets/lookup/'
                    });
                }
            });

            for(var srvname in service_config){

                var cfg = service_config[srvname];    
                
                if(cfg.rty_ID == this._currentEditRecTypeID){
                    notfound = false;                    
                    
                    var btn = $('<div>')
                        .button({label:cfg.label?cfg.label:('Lookup '+cfg.service) })
                        .attr('data-cfg', srvname).css({'font-size': 'inherit', // 'padding-right':'4px',
                            border: '1px solid', 'font-weight': 'bold', 'margin-right': '5px'})
                        .appendTo($ext_lookup_cont);
                    
                    this._on(btn, {click:
                        function(event){ 
                            
                            var srvname = $(event.target).attr('data-cfg');

                            var cfg = window.hWin.HAPI4.sysinfo['service_config'][srvname];
                            var dialog_name = cfg.dialog;
                            var service_name = cfg.service;

                            if(dialog_name == 'recordLookup' || dialog_name == 'lookupTCL'){
                                dialog_name = 'lookupTLC';
                            }else if(dialog_name == 'recordLookupBnFLibrary' || dialog_name == 'lookupBnFLibrary'){
                                dialog_name = 'lookupBnFLibrary_bib';
                            }else if(dialog_name.includes('recordLookup')){
                                dialog_name = dialog_name.replace('recordLookup', 'lookup');
                            }

                            var dlg_opts = { 
                                mapping: cfg, 
                                edit_fields: this._editing.getValues(true),
                                edit_record: this._currentEditRecordset,
                                path: 'widgets/lookup/',
                                onClose:function(recset){
                                    that._handleLookupResponse(recset, srvname);
                                }
                            };

                            if(service_name == 'ESTC_editions' || service_name == 'ESTC_works' || service_name == 'ESTC'){

                                var req = {
                                    a: 'check_allow_estc',
                                    db: window.hWin.HAPI4.database,
                                    ver: service_name
                                };
                                window.hWin.HAPI4.SystemMgr.check_allow_estc(req, function(response){
                                    if(response.status == window.hWin.ResponseStatus.OK){
                                        window.hWin.HEURIST4.ui.showRecordActionDialog(dialog_name, dlg_opts);
                                    }else{
                                        window.hWin.HEURIST4.msg.showMsgErr(response);
                                        return false;
                                    }
                                });
                            }else{
                                window.hWin.HEURIST4.ui.showRecordActionDialog(dialog_name, dlg_opts);
                            }
                        }
                    });
                }
            }
        }
        if(notfound){
            lookup_div.hide();    
        }else{
            lookup_div.show();    
        }
    },

    //
    //
    //
    _handleLookupResponse: function(recset, service){

        var that = this;
        const cfg = window.hWin.HAPI4.sysinfo['service_config'][service];

        if(!recset || window.hWin.HEURIST4.util.isempty(recset)){
            return;
        }

        if( window.hWin.HEURIST4.util.isRecordSet(recset) ){
        
            var rec = recset.getFirstRecord();
            // loop all fields in selected values
            // find field in edit form
            // assign value
            var fields = recset.getFields();
            for(var k=2; k<fields.length; k++){
                var dt_id = cfg.fields[fields[k]];
                if(dt_id>0)
                {
                    var newval = recset.fld(rec, fields[k]);
                    newval = window.hWin.HEURIST4.util.isnull(newval)?'':newval;
                    that._editing.setFieldValueByName( dt_id, newval );
                    //var ele_input = that._editing.getFieldByName(dt_id );
                }
            }
        }else{
            //lookup dialog returns pairs - dtyID=>value
            var dtyIds = Object.keys(recset);

            var assigned_fields = []; // list of fields assigned

            that.term_values = []; // list of label values for enum/term fields
            that.file_values = {}; // list of external urls for file fields
            that.resource_values = []; // list of search values for recpointer fields

            if(!window.hWin.HEURIST4.util.isempty(recset['ext_url'])){
                that.resource_values.push({url: recset['ext_url'], type: 'ext'});
            }else if(!window.hWin.HEURIST4.util.isempty(recset['heurist_url'])){
                that.resource_values.push({url: recset['heurist_url'], type: 'heurist'});
            }else{
                that.resource_values.push({});
            }

            for(var k=0; k<dtyIds.length; k++){

                var dt_id = dtyIds[k];

                if(dt_id>0){

                    var newval = recset[dt_id];
                    var type = $Db.dty(dt_id, 'dty_Type');

                    if(type == 'resource' || type == 'enum'){

                        var completed = []; // completed recpointers/terms

                        if(window.hWin.HEURIST4.util.isArray(newval)){

                            for(var i = 0; i < newval.length; i++){

                                if(Number.isInteger(+newval[i])){ // is fine
                                    completed.push(newval[i]);
                                    continue;
                                }

                                // needs additional handling
                                if(type == 'resource'){
                                    that.resource_values.push({fld_id: dt_id, values: newval[i]});
                                }else{

                                    var vocab_id = $Db.dty(dt_id, 'dty_JsonTermIDTree');

                                    if(window.hWin.HEURIST4.util.isArray(newval[i])){
                                        for(var j = 0; j < newval[i].length; j++){

                                            var trm_id = $Db.getTermByLabel(vocab_id, newval[i][j]);

                                            if(trm_id == null){
                                                that.term_values.push([dt_id, newval[i][j]]);
                                            }else{
                                                completed.push(trm_id);
                                            }
                                        }
                                    }else{
                                        var trm_id = $Db.getTermByLabel(vocab_id, newval[i]);

                                        if(trm_id == null){
                                            that.term_values.push([dt_id, newval[i]]);
                                        }else{
                                            completed.push(trm_id);
                                        }
                                    }
                                }
                            }
                        }else{

                            if(Number.isInteger(+newval)){
                                completed.push(newval);
                            }else{

                                if(type == 'resource'){
                                    that.resource_values.push({fld_id: dt_id, values: newval});
                                }else{
                                    
                                    var vocab_id = $Db.dty(dt_id, 'dty_JsonTermIDTree');
                                    var trm_id = $Db.getTermByLabel(vocab_id, newval);

                                    if(trm_id == null){
                                        that.term_values.push([dt_id, newval]);
                                    }else{
                                        completed.push(trm_id);
                                    }
                                }
                            }
                        }

                        if(completed.length > 0){
                            that._editing.setFieldValueByName(dt_id, completed);

                            var fieldname = $Db.rst(that._currentEditRecTypeID, dt_id, 'rst_DisplayName');
                            if(!assigned_fields.includes(fieldname)) { assigned_fields.push(fieldname); }
                        }
                    }else if(type == 'file'){ // need to create new remote file records

                        if(!Object.hasOwnProperty(dt_id, that.file_values)){
                            that.file_values[dt_id] = [];
                        }
                        if(window.hWin.HEURIST4.util.isArray(newval)){
                            that.file_values[dt_id] = that.file_values[dt_id].concat(newval);
                        }else{
                            that.file_values[dt_id].push(newval);
                        }
                    }else{
                        if(!$.isArray(newval)){
                            newval = window.hWin.HEURIST4.util.isnull(newval)?'':newval;
                            newval = [newval];
                        }

                        that._editing.setFieldValueByName( dt_id, newval );

                        var fieldname = $Db.rst(that._currentEditRecTypeID, dt_id, 'rst_DisplayName');
                        if(!assigned_fields.includes(fieldname)) { assigned_fields.push(fieldname); }
                    } 
                }else if(dt_id == 'BnF_ID' && cfg.options.dump_record == true){ // retrieve record from BnF and place in record scratch pad

                    let value = recset['BnF_ID'];

                    if(window.hWin.HEURIST4.util.isempty(value)){ // missing | no value
                        continue;
                    }

                    let req_url = 'http://catalogue.bnf.fr/api/SRU?version=1.2&operation=searchRetrieve&recordSchema=unimarcxchange&maximumRecords=1&startRecord=1&query=(';                                                        
                    let fld_name = cfg.service == 'bnfLibrary' ? 'bib.recordid' : 'aut.recordid';

                    req_url += encodeURIComponent(fld_name + ' all ' + value) + ')';

                    let req = {
                        service: req_url,
                        serviceType: 'bnf_recdump'
                    };

                    window.hWin.HAPI4.RecordMgr.lookup_external_service(req, (response) => {
                        if(window.hWin.HEURIST4.util.isJSON(response)){
                            response = window.hWin.HEURIST4.util.isJSON(response);
                            if(response.record != null){

                                let scratchpad_txt = response.record + '\r\n\r\n';//JSON.stringify(response.record, null, 4);

                                let fld_id = cfg.options.dump_field;
                                if(parseInt(fld_id) == NaN || fld_id < 1 || !$Db.rst(that._currentEditRecTypeID, fld_id) || $Db.dty(fld_id, 'dty_Type') != 'blocktext'){
                                    fld_id = 'rec_ScratchPad';   
                                }
                                let $fld = that._editing.getFieldByName(fld_id);

                                if(fld_id == 'rec_ScratchPad'){

                                    if(!window.hWin.HEURIST4.util.isempty($fld.text())){ // if content exists; prepend and add breaks before existing content
                                        scratchpad_txt += '\r\n\r\n' + $fld.text();
                                    }
    
                                    $fld.editing_input('setValue',[scratchpad_txt]);
                                    $fld.editing_input('isChanged', true);

                                    that.editFormPopup.layout().open("east"); // expand panel
                                    
                                    let $acc_ele = $(that.editFormSummary.find('.summary-accordion').get(4));
                                    if($acc_ele.accordion('instance') != undefined){ // expand accordion
                                        $acc_ele.accordion('option', 'active', 0);
                                    }
                                }else{

                                    let existing_vals = $fld.editing_input('getValues');
                                    if(existing_vals[0] != ''){
                                        existing_vals.push([scratchpad_txt]);
                                    }
                                    $fld.editing_input('setValue',[scratchpad_txt]);
                                }
                            }
                        }
                    });
                }
            }

            that.processTermFields(assigned_fields, {});
        }
    },
	
	//
    // Process term field values from External Lookups, that aren't integers (ids)
    //
    // Param:
    //  completed_fields (array): array of field names that have already been assigned
    //  new_terms (object): contains the values for enum fields to assign after handling all terms {dt_id: [trm_id, ...]}
    //      key => field id (dt_id), value => array of term id(s) ([trm_id1, trm_id2, ...])
    //
    processTermFields: function(completed_fields, new_terms){

        var that = this;

        if(new_terms == null) { new_terms = {}; }

        if(that.term_values.length == 0 && Object.keys(new_terms).length == 0){ // no terms to handle
            this.processFileFields(completed_fields);
            return;
        }else if(that.term_values.length == 0){ // all terms handled

            if(new_terms['refresh']){ // check whether local cache needs updating

                delete new_terms['refresh'];

                window.hWin.HAPI4.EntityMgr.refreshEntityData(['defTerms'], function(success){

                    if(success){

                        for(var fld_id in new_terms){ // pass term ids to respective fields
                            that._editing.setFieldValueByName(fld_id, new_terms[fld_id]);

                            var fieldname = $Db.rst(that._currentEditRecTypeID, fld_id, 'rst_DisplayName');
                            if(!completed_fields.includes(fieldname)) { completed_fields.push(fieldname); }
                        }
                        that.processFileFields(completed_fields);
                    }
                });
            }else{ // no cache updating needed

                for(var fld_id in new_terms){
                    that._editing.setFieldValueByName(fld_id, new_terms[fld_id]);

                    var fieldname = $Db.rst(that._currentEditRecTypeID, fld_id, 'rst_DisplayName');
                    if(!completed_fields.includes(fieldname)) { completed_fields.push(fieldname); }
                }
                this.processFileFields(completed_fields);
            }

            return;
        }
        var cur_term = this.term_values.shift(); // 0 => dt_id, 1 => term label
        var vocab_id = $Db.dty(cur_term[0], 'dty_JsonTermIDTree');
        var field_name = $Db.rst(this._currentEditRecTypeID, cur_term[0], 'rst_DisplayName');

        // add current field (dt_id) to new_terms, and retain any existing values
        if(!new_terms.hasOwnProperty(cur_term[0])){
            var existing_val = this._editing.getValue(cur_term[0]);
            new_terms[cur_term[0]] = (existing_val == null || window.hWin.HEURIST4.util.isempty(existing_val[0])) ? [] : existing_val;
        }

        var $dlg;

        // Term Dlg - Content
        var msg = 'You are creating a new term for the <strong>' + field_name + '</strong> field.<br><br>'
                + 'New Term: <input type="text" id="new_term_label" value="'+ cur_term[1] +'"> <br><br>'
                + 'Please correct the term above, as required, before clicking Insert term<br>(you can type an existing term or a correction)';

        // Term Dlg - Button
        var btn = {};
        btn['Insert term'] = function(){

            var new_label = $dlg.find('input#new_term_label').val();
            var term_Ids = $Db.trm_TreeData(vocab_id, 'set');
            var savedTerm = false;

            // Check if entered term exists within vocab
            for(var i=0; i<term_Ids.length; i++){

                var trm_Label = $Db.trm(term_Ids[i], 'trm_Label').toLowerCase();

                if(new_label.toLowerCase() == trm_Label){

                    new_terms[cur_term[0]].push(term_Ids[i]);

                    $dlg.dialog('close');

                    break;
                }
            }

            if(savedTerm){
                that.processTermFields(completed_fields, new_terms);
            }else{ // Create new term

                var request = {
                    'a': 'save',
                    'entity': 'defTerms',
                    'request_id': window.hWin.HEURIST4.util.random(),
                    'fields': {'trm_ID': -1, 'trm_Label': new_label, 'trm_ParentTermID': vocab_id},
                    'isfull': 0
                };

                window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){

                    if(response.status == window.hWin.ResponseStatus.OK){

                        new_terms[cur_term[0]].push(response.data[0]); // response.data[0] == new term id

                        $dlg.dialog('close');

                        new_terms['refresh'] = true;
                        that.processTermFields(completed_fields, new_terms);
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
            }
        };
        btn['Skip'] = function(){

            new_terms[cur_term[0]].push('');

            $dlg.dialog('close');
            that.processTermFields(completed_fields, new_terms);
        };

        // Create dlg
        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btn, {title: 'Unknown term', yes: 'Insert term', no: 'Skip'}, {default_palette_class: 'ui-heurist-design'});
    },

	//
    // Process file field values from External Lookups, handles remote resources only
    // 
    // Param:
    //  completed_fields (array): array of field names that have already been assigned
    //
    processFileFields: function(completed_fields){

        var that = this;

        if(Object.keys(this.file_values).length == 0){
            this.processResourceFields(completed_fields);
            return;
        }

        var request = {
            'a': 'batch',
            'entity': 'recUploadedFiles',
            'request_id': window.hWin.HEURIST4.util.random(),
            'regExternalFiles': JSON.stringify(this.file_values)
        };

        window.hWin.HEURIST4.msg.bringCoverallToFront(that._getEditDialog(), null, '<span style="color: white;">Processing file fields...</span>');

        window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack();
            if(response.status == window.hWin.ResponseStatus.OK){

                let file_data = response.data;

                let invalid_file = '';
                let error_save = '';
                let error_id = '';

                for(let fld_id in file_data){

                    let cur_file = file_data[fld_id];
                    if(cur_file['err_save']){
                        error_save += '<br>' + cur_file['err_save'].join('<br>');
                        delete cur_file['err_save'];
                    }
                    if(cur_file['err_id']){
                        error_id += '<br>' + cur_file['err_id'].join('<br>');
                        delete cur_file['err_id'];
                    }
                    if(cur_file['err_invalid']){
                        for(let f_id in cur_file['err_invalid']){
                            invalid_file += '<br>' + f_id + ' => ' + cur_file['err_invalid'][f_id];
                        }
                        delete cur_file['err_invalid'];
                    }

                    that._editing.setFieldValueByName(fld_id, cur_file);

                    let fieldname = $Db.rst(that._currentEditRecTypeID, fld_id, 'rst_DisplayName');
                    if(!completed_fields.includes(fieldname)) { completed_fields.push(fieldname); }
                }

                if(invalid_file != '' || error_save != '' || error_id != ''){ // Display failed files
                    let msg = 'The following URLs could not be processed.<br><br>';

                    if(invalid_file != ''){
                        msg += '<strong>Invalid URLs:</strong><br>' + invalid_file + '<br>';
                    }
                    if(error_save != ''){
                        msg += '<strong>URLs failed during save:</strong><br>' + error_save + '<br>';
                    }
                    if(error_id != ''){
                        msg += '<strong>Unable to retrieve File details:</strong><br>' + invalid_file + '<br>';
                    }

                    let $err_dlg;
                    $err_dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, {'Ok': () => { 
                        $err_dlg.dialog('close'); 
                        that.processResourceFields(completed_fields);
                    }}, {title: 'File saving error'}, {default_palette_class: 'ui-heurist-explore'});
                }else{
                    that.processResourceFields(completed_fields)
                }
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    },

	//
    // Process record pointer fields values from External Lookups, that aren't integers (ids)
    // 
    // Param:
    //  completed_fields (array): array of field names that have already been assigned
    //
    processResourceFields: function(completed_fields){

        var that = this;

        // Misc, styling for 'table cells'
        var field_style = 'display: table-cell;padding: 7px 3px;max-width: 125px;min-width: 125px;';
        var val_style = 'display: table-cell;padding: 7px 3px;max-width: 300px;min-width: 300px;';

        // Construct Dialog for user handling of recpointer fields
        var $dlg;
        var url = '';
        var completed = '';

        var hasValue = (that.resource_values != null && that.resource_values.length > 1);

        if(hasValue && Object.keys(that.resource_values[0]).length == 2){ // contains only the external record link
            if(that.resource_values[0]['type'] == 'ext'){ // external link
                url = '<a href="' + that.resource_values[0]['url'] + '" target="_blank">View external record <span style="font-size:10px;" class="ui-icon ui-icon-extlink" /></a><br><br>';
            }
        }

        // Add already assigned fields
        if(completed_fields.length > 0){
            completed = 'The following fields have been inserted:<br><ul style="list-style: none;">';

            for(var j = 0; j < completed_fields.length; j++){
                completed += '<li>' + completed_fields[j] + '</li>';
            }

            completed += '</ul><br>';
        }

        var msg = url + completed

        if(hasValue){ // Display full popup

            // Recpointer Dlg - Main Content
            msg += 'Values remaining to be attributed, click a row to assign a value'
                + '<br><br>'
                + '<div id="wrapper" style="max-height: 250px;overflow: auto;">'
                + '<div style="display: table">'
                + '<div style="display: table-row">'
                    + '<div style="'+ field_style +'">Field</div><div style="'+ val_style +'">Value</div>'
                + '</div>';

            // Other variables
            var field_values = {};
            var todo_count = 0;

            // Add each row of record pointers
            for(var i = 1; i < that.resource_values.length; i++){

                var cur_details = that.resource_values[i];
                let fld_id = cur_details['fld_id'];
                let value = cur_details['values'];
                let search = value;
                if(window.hWin.HEURIST4.util.isObject(value)){
                    search = value['search'];
                    value = value['value'];
                }

                var field_name = $Db.rst(this._currentEditRecTypeID, fld_id, 'rst_DisplayName');

                msg += '<div style="display: table-row;color: black;opacity: 1;" class="recordDiv" data-value="" '
                            +'data-dtid="'+ fld_id +'" data-index="'+ todo_count +'" data-search="'+ search +'">'
                        + '<div style="'+ field_style +'" class="truncate" title="'+ field_name +'">'+ field_name +'</div>'
                        + '<div style="'+ val_style +'" class="truncate" title="'+ value +'">'+ value +'</div>'
                    + '</div>';

                var existing_val = this._editing.getValue(fld_id);
                field_values[fld_id] = (existing_val == null || window.hWin.HEURIST4.util.isempty(existing_val[0])) ? [] : existing_val;

                todo_count ++;
            }

            msg += '</div></div>'; // close div.table and div#wrapper

            // Recpointer Dlg - Button
            var btn = {};
            btn['Close'] = function(){
                // Close Recpointer Dlg
                if(complete_count < todo_count){
                    window.hWin.HEURIST4.msg.showMsgDlg((todo_count - complete_count) + ' record pointer field values have not been inserted.<br>Do you want to omit them?', 
                        () => { $dlg.dialog('close'); }, {title: 'Task incomplete'}, {default_palette_class: 'ui-heurist-populate'});
                }else{
                    $dlg.dialog('close');
                }
            }

            // Create Recpointer Dlg
            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btn, {title: 'Record pointer fields to set', yes: 'Close'}, {
                default_palette_class: 'ui-heurist-populate', 
                modal: true, 
                dialogId: 'lookup_RecPointers', 
                position: {my: "right-50 top+45", at: "right top", of: this._getEditDialog()}
            });

            var complete_count = 0;

            // onClick - 'Record' Row within the Recpointer Dlg
            $dlg.find('.recordDiv').on('click', function(event){

                var $ele = $(event.target);
                if(!$ele.hasClass('recordDiv')){ // check that the correct element is retrieved
                    $ele = $ele.parents('.recordDiv');
                }

                $ele.css('background', 'rgba(65, 105, 225, 0.8)'); // highlight selection

                // Retrieve important details
                var dt_id = $ele.attr('data-dtid');
                var search = $ele.attr('data-search');
                var ptr_rectypes = $Db.dty(dt_id, 'dty_PtrTargetRectypeIDs');
                var index = Number($ele.attr('data-index'));

                var init_value = $($ele.find('div')[1]).text();

                // Show dialog for selecting/creating a record
                var opts = {
                    select_mode: 'select_single',
                    select_return_mode: 'recordset', // or ids
                    edit_mode: 'popup',
                    selectOnSave: true,
                    title: 'Select or create a record for the ' + $Db.rst(that._currentEditRecTypeID, dt_id, 'rst_DisplayName') + ' field',
                    rectype_set: ptr_rectypes, // string of rectype_ids separated by commas (e.g. '1,2,3')
                    pointer_mode: 'both', // or browseonly or addonly
                    pointer_filter: null, // Heurist query for initial search
                    parententity: 0,

                    init_filter: search,
                    fill_data: init_value,

                    height: 700,
                    width: 600,
                    modal: true,

                    // Process selected record set
                    onselect: function(event, data){

                        $ele.css('background', ''); // remove higlight

                        if(!data || !data.selection) return;

                        var recset = data.selection;

                        var record = recset.getFirstRecord();
                        var rec_ID = recset.fld(record, 'rec_ID')
                        var rec_Title = recset.fld(record, 'rec_Title');

                        if(!rec_ID || rec_ID <= 0 || rec_Title=='') return;

                        field_values[dt_id].push(rec_ID);

                        that._editing.setFieldValueByName(dt_id, field_values[dt_id]);

                        // Update information
                        $ele.attr('data-value', rec_ID);
                        $($ele.find('div')[1]).attr('title', rec_Title).text(rec_Title);
                        window.hWin.HEURIST4.util.setDisabled($ele, true); // set to disabled
                        $ele.css('background', 'gray');

                        complete_count ++;

                        if(complete_count >= todo_count){
                            // Close Recpointer Dlg
                            $dlg.dialog('close');
                        }else{

                            var $n_ele = $ele.parent().find('div[data-index="'+ (index+1) +'"]');

                            if($n_ele.length == 1 && !$n_ele.hasClass('ui-state-disabled')){

                                $n_ele.click();
                            }
                        }
                    },

                    beforeClose: function(event, ui){
                        if(!$ele.hasClass('ui-state-disabled')){ // remove highlighting
                            $ele.css('background', '');
                        }
                    }
                };

                window.hWin.HEURIST4.ui.showEntityDialog('records', opts);
            });
        }else{// Display reduced version for 2 seconds

            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, null, 
                {title: 'Field mapping completed', ok: 'Close'}, 
                {default_palette_class: 'ui-heurist-populate', position: {my: "right-50 top+45", at: "right top", of: this._getEditDialog()}}
            );

            setTimeout(function(){ $dlg.dialog('close'); }, 2000);
        }
    },

    focusField: function(field_id){

        var $ele = this._editing.getFieldByName(field_id);
        let isSeparator = false;

        if(!$ele || $ele.length == 0){ // assume separator
            isSeparator = true;
            $ele = this.editForm.find('fieldset[data-dtid='+field_id+']');

            if($ele.length == 0){ // try simple divider
                $ele = this.editForm.find('div[data-dtid='+ field_id +']');
            }
        }

        if($ele.length == 0){
            return;
        }

        if($ele.parents('.ui-tabs').length > 0){ // Tabs

            let index = 0;
            if(!$ele.is('fieldset')){
                index = $ele.parents('fieldset:first').attr('data-tabindex');
            }else{
                index = $ele.attr('data-tabindex');
            }
            $ele.parents('.ui-tabs').tabs('option', 'active', index);
        }else if($ele.parents('.ui-accordion').length > 0){ // Accordion

            let accordion_content = $ele.parents('.ui-accordion-content');
            if(!accordion_content.is(':visible')){
                let id = accordion_content.attr('aria-labelledby');
                $.each($ele.parents('.ui-accordion:first').find('.ui-accordion-header'), function(idx, item){ // Find corresponding header
                    if($(item).attr('id') == id){
                        $ele.parents('.ui-accordion:first').accordion('option', 'active', idx);
                        return false;
                    }
                });
            }
        }

        this.editForm.animate({scrollTop: $ele.offset().top}, 1000); // scrollIntoView()

        if(!isSeparator){
            $ele.editing_input('focus');
        }
    }
});
