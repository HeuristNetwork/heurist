/**
* @file manageRecords.js
* @brief Manages Record entities.
* @fileOverview Provides a comprehensive UI for creating, viewing, editing, listing, and managing Heurist Record entities. This is a central widget for interacting with record data and includes features for handling different record types, structures, and associated data like files and comments.
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4.0
*/



/* global Temporal,TDate,temporalToHumanReadableString */

/**
 * @widget heurist.manageRecords
 * @brief Widget for managing Heurist Record entities.
 * @augments $.heurist.manageEntity
 *
 * @property {?number} rec_ID The ID of the specific record to be managed or displayed. If negative or 0, typically indicates a new record.
 * @property {?number} rty_ID The Record Type ID. Used when creating a new record to specify its type, or can be used to filter views.
 * @property {?boolean} edit_structure If true, the widget opens with the record structure editor visible, allowing for simultaneous editing of record data and its underlying structure.
 * @property {?boolean} show_toolbar Controls the visibility of the main toolbar associated with record management actions. (Implicitly true by default).
 * @property {?boolean} show_statusbar Controls the visibility of the status bar, which might display information about the current record or operation. (Implicitly true by default).
 * @property {?(object|boolean)} fixed_search If an object, defines a fixed search query; the search form will be hidden. If true, implies a predefined fixed search.
 * @property {?(string[]|string)} rectype_set A list (array of numbers or comma-separated string) of Record Type IDs to filter the available record types, typically used in selection modes.
 * @property {?boolean} selectOnSave Special mode, often true when the record editor is opened from a selection popup (e.g., choosing a record for a pointer field), affecting behavior on save.
 * @property {?boolean} allowAdminToolbar If false, administrative tools like "Modify Structure" or "Edit title mask" are hidden.
 * @property {?object} rts_editor A reference to an instance of the `manageDefRecStructure` widget, used when `edit_structure` is true.
 * @property {?number} parententity If this record is being created or viewed as a child in a parent-child relationship, this holds the ID of the parent record.
 * @property {?number} relmarker_field If a relationship record is being edited as accessed via a relationship marker field from a source or target record, this holds the ID of that marker field (a DetailTypeID).
 * @property {?boolean} relmarker_is_inward Indicates the direction of the relationship if `relmarker_field` is present (true if inward, false if outward).
 * @property {?object} fill_in_data An object containing initial values to populate fields when creating a new record. Keys are DetailTypeIDs or field concept codes.
 * @property {object} usrPreferences User-specific preferences for the widget's UI, such as panel widths, visibility of optional fields, and help text. Loaded from user settings.
 * @property {object} defaultPrefs Default UI preferences for the widget, used if no user-specific preferences are found. Includes default dimensions and settings for optional fields and help.
 */
$.widget( "heurist.manageRecords", $.heurist.manageEntity, {
   
    //_entityName:'records',
    
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
    _duplicatedRecord: false, // current record was created via the 'Dupe' button
    
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
    
    _showCustomJsWarningOnce: true, //semaphore

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
    lookup_record_link: null,
    term_values: [],
    file_values: {},
    resource_values: [],
    relmarker_values: [],
    
    //to refresh icon after structure edit
    _icon_timer_suffix: ('&t='+window.hWin.HEURIST4.util.random()),

    // Record history
    _record_history: null,
    _check_history: true, // check for record history

    // Record type information
    _source_db: {
        id: 0, // database ID
        url: '' // URL to source
    },
    _source_def: null, // source def from source db

    _autoResizeCheckbox: null,

    /**
     * @brief Initializes the manageRecords widget.
     * @override
     * @memberof heurist.manageRecords
     * This method sets up the widget options, including entity configuration, layout mode,
     * and dimensions. It calls the parent `_init` method and then performs specific
     * initializations for record management, such as adjusting UI elements based on
     * edit mode and setting up event listeners for keyboard shortcuts (Ctrl+S/Cmd+S for save).
     * It also initializes the RTS (Record Type Structure) actions menu if applicable.
     */
    _init: function() {
        
        this.options.entity = window.hWin.entityRecordCfg;
        
        if(!this.options.default_palette_class){
            this.options.default_palette_class = 'ui-heurist-populate';  
            //since 2021-05-05 there is no difference this.options.edit_structure?'ui-heurist-design':'ui-heurist-populate';   
        }
        
        if(this.options.layout_mode=='short'){
                this.options.layout_mode = //slightly modified 'short' layout
                        '<div class="ent_wrapper editor">'
                            +'<div class="ent_wrapper">'
                                +    '<div class="ent_header searchForm" style="height:auto"></div>'     
                                +    '<div class="ent_content_full recordList"></div>'
                            +'</div>'

                            + '<div class="editFormDialog ent_wrapper editor">'
                                    + '<div class="ui-layout-west"><div class="editStructure">..</div></div>' //container for rts_editor
                                    + '<div class="ui-layout-center"><div class="editForm">..</div></div>'
                                    + '<div class="ui-layout-east"><div class="editFormSummary">....</div></div>'
                                    //+ '<div class="ui-layout-south><div class="editForm-toolbar"></div></div>'
                            +'</div>'
                        +'</div>';
        }
        
        this.options.use_cache = false;
       
       

        //for selection mode set some options
        if(this.options.select_mode!='manager'){
            this.options.width = (isNaN(this.options.width) || this.options.width<640)?640:this.options.width;      
        }else{
            this.options.width = 1200;                    
        }
        
        this.options.editClassName = 'recordEditor';
        
        this._super();
        
       

        let hasSearchForm = (this.searchForm && this.searchForm.length>0);
        
        if(this.options.edit_mode=='inline' || this.options.edit_mode=='editonly'){
            // for manager - inline mode means that only editor is visible and we have to search init exterally
            // see recordEdit.php
            if(hasSearchForm) this.searchForm.parent().css({width:'0px'});    
            this.editFormPopup.css({left:0}).show();
            
            let $dlg = this._getEditDialog(true);
            if($dlg){
                $dlg.parent().addClass(this.options.default_palette_class);
            }
            
        }else{
            //for select mode we never will have inline mode
            if(hasSearchForm) this.searchForm.parent().width('100%');
            this.editFormPopup.css({left:0}).hide(); 
        }
        
        //-----------------
        if(hasSearchForm && this.searchForm.is(':visible')){
            
            let rt_list = this.options.rectype_set;
            if(!window.hWin.HEURIST4.util.isempty(rt_list)){
                if(!Array.isArray(rt_list)){
                    rt_list = rt_list.split(',');
                }
            }            
            
            this._adjustResultListTop();
            
        }else{
            this.recordList.css('top', 0);    
        }
        
        //  this.recordList.css('top', sh+'em');    
        
        let that = this;
        
        jQuery(document).on('keydown',function(event) {
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
        const is_create_actions_buttons = true;
        if(is_create_actions_buttons){ // || this.options.rts_editor

            const plusIcon = '<span class="ui-icon ui-icon-plus" style="font-size: 1em;margin-right: 1em;vertical-align: -5px;"></span>';

            this.rts_actions_menu = 
            $('<div class="rts-editor-actions" style="display: none;">'

                +`<div data-action="edit" class="rts-editor-action" style="margin-bottom: 5px;">
                    <span class="ui-icon ui-icon-pencil" title="Edit" style="margin-right: 0.5em;"></span>
                    edit field definition
                </div>`

                +'<div style="font-style: italic;font-size: 0.85em;font-weight: bold;padding: 5px 0px 2.5px 5px;">insert below current position</div>'

                +`<div data-action="field" class="rts-editor-action" title="Add a new field to this record type">
                    ${plusIcon}field
                </div>`

                +`<div data-action="connector" class="rts-editor-action" title="Add a new connecting field to this record type">
                    ${plusIcon}connection
                </div>`

                +`<div data-action="block" class="rts-editor-action" title="Add a new group/separator">
                    ${plusIcon}tab / heading
                </div>`

                +`<div data-action="explain" class="rts-editor-action" title="Add a new explanation separator">
                    ${plusIcon}explanatory text
                </div>`

                +`<div data-action="sub-record" class="rts-editor-action" title="Create a sub-record" class="admin-only">
                    ${plusIcon}sub-record
                </div>`

                +`<div class="edit_rts_sel" class="rts-editor-action" title="Requirement type">
                    <select class="edit_rts s_reqtype">
                        <option>required</option><option>recommended</option>
                        <option>optional</option><option value="forbidden">hidden</option>
                    </select>
                </div>`

                +`<div class="edit_rts_sel" title="Repeatability">
                    <select class="edit_rts s_repeat">
                        <option value="1">single</option><option value="0">repeatable</option>
                        <option value="2">limited 2</option><option value="3">limited 3</option>
                        <option value="5">limited 5</option><option value="10">limited 10</option>
                    </select>
                </div>`

                +`<div class="edit_rts_sel s_width" title="Width of field">
                    Width: 
                    <select class="edit_rts s_width" style="display:none">
                        <option>5</option><option>10</option><option>20</option><option>30</option>
                        <option>40</option><option>50</option><option>60</option><option>80</option>
                        <option>100</option><option>120</option><option>150</option><option>200</option>
                        <option>250</option><option value="0">Max</option>
                    </select>
                </div>`

                +'<span class="edit_rts_btn" style="top:24px;left:80px;position:absolute;background:lightblue;display:none" '
                +' data-apply="1" title="Save changes for field properties">Apply</span>'
                +'<span class="edit_rts_btn" style="top:24px;left:130px;position:absolute;background:lightblue;display:none">'
                +'Cancel</span>'
            
            +'</div>')
            .appendTo(this.element);

            if(!window.hWin.HAPI4.is_admin()){
                this.rts_actions_menu.find('.admin-only').hide();
            }

            window.hWin.HEURIST4.ui.initHSelect(this.rts_actions_menu.find('select.s_reqtype'),
                                            false, {'max-width':'100px','font-size':'0.9em', padding:0});
            window.hWin.HEURIST4.ui.initHSelect(this.rts_actions_menu.find('select.s_repeat'),
                                            false, {'max-width':'100px','font-size':'0.9em', padding:0});
            window.hWin.HEURIST4.ui.initHSelect(this.rts_actions_menu.find('select.s_width'),
                                            false, {'max-width':'60px','font-size':'0.9em', padding:0});

            //save/cancel rts buttons
            this.edit_rts_apply = this.rts_actions_menu.find('.edit_rts_btn').button();
            this._on( this.edit_rts_apply, {
                click: function(e){
                    if($(e.target).attr('data-apply')){

                        let dtId = this.rts_actions_menu.attr('data-did');
                    
                        let fields = {
                            rst_RecTypeID: this._currentEditRecTypeID,
                            rst_DetailTypeID: dtId,
                            rst_MaxValues: this.rts_actions_menu.find('select.s_repeat').val(), 
                            rst_DisplayWidth: this.rts_actions_menu.find('select.s_width').val(), 
                            rst_RequirementType: this.rts_actions_menu.find('select.s_reqtype').val()};

                        let request = {
                            'a'          : 'save',
                            'entity'     : 'defRecStructure',
                            'request_id' : window.hWin.HEURIST4.util.random(),
                            'fields'     : fields                     
                        };
                            
                        let dlged = that._getEditDialog();
                        if(dlged) window.hWin.HEURIST4.msg.bringCoverallToFront(dlged);
                        
                        window.hWin.HAPI4.EntityMgr.doRequest(request, 
                            function(response){
                                
                                window.hWin.HEURIST4.msg.sendCoverallToBack();
                                
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    
                                    //update db definitions and tree    
                                    that.options.rts_editor.manageDefRecStructure(
                                        'refreshRecset_Definition_TreeNodeItem', dtId, fields);
                                    
                                    //recreate edit field
                                    let dtFields = that._prepareFieldForEditor( null, that._currentEditRecTypeID, dtId );
                                    let inpt = that._editing.getFieldByName(dtId);
                                    inpt.editing_input('option', {dtFields:dtFields, recreate:true} );
                                    that._createRtsEditButton(dtId, $(that.element).find('div[data-dtid="'+dtId+'"]') );
                                    
                                    if(fields['rst_RequirementType']=='forbidden'){
                                        inpt.find('.header').css({'opacity':'0.3'});   //header
                                        inpt.find('.header').next().css({'opacity':'0.3'}); //repeat btn 
                                        inpt.find('input,textarea,button,.ui-selectmenu-button').css('border','1px dotted red');
                                    }
                                    
                                    /* Hide Help Text if 'Show Help' isn't checked, help text is shown by default */
                                    if(!that.element.find('.chb_show_help').is(':checked')){
                                        $('.heurist-helper1').hide();
                                    }
                                    
                                    if((dtFields['dty_Type'] == 'freetext' || dtFields['dty_Type'] == 'blocktext' || dtFields['dty_Type'] == 'float') 
                                            && dtFields['rst_DisplayWidth'] == 0){

                                        let width = that.editForm.width() * ((that.options.rts_editor) ? 0.7 : 0.8);

                                        inpt.find('input, textarea').css({'min-width': width, width: width});
                                    }
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                }
                            });
                        
                    }
                    this.hideRtsMenu();
                }
            });

            //prevent exit on select events                    
            this._on( this.rts_actions_menu.find('.edit_rts'), {
                mouseover : function(){ this._rts_selector_flag = true; 
                                clearTimeout(this._menuTimeoutId); },
                mouseleave : function(){
                                this._rts_selector_flag = false; },
                change: function(event){
                        this._rts_changed_flag = true;
                        $(this.edit_rts_apply[0]).trigger('click');
                       
                }                
            });
            
            this._on( this.rts_actions_menu, {
                mouseover : function(){ 
                        if(this._menuTimeoutId>0){ clearTimeout(this._menuTimeoutId); } this._menuTimeoutId=0;},
                mouseleave : function(){ 
                    
                    if(this._rts_selector_flag || this._rts_changed_flag) return;
                    
                    //do not hide if dropdown is opened
                    if($('.ui-selectmenu-menu.ui-selectmenu-open:hover').length>0) return; 
                    
                    that._menuTimeoutId = setTimeout(function() {
                        that.hideRtsMenu();
                        if(that.options.rts_editor){
                            that.options.rts_editor.manageDefRecStructure('highlightNode', null);
                        }
                    }, 100);  
                },
                click: function(event){
                        let trg = $(event.target);                     
                        if(trg.parents('.ui-selectmenu-button').length>0) return;
                    
                        if(this._rts_selector_flag || this._rts_changed_flag) return;
                        let dt_id = this.rts_actions_menu.attr('data-did');
                        
                        this.hideRtsMenu();
                        
                        let ele = $(event.target);
                        let action = ele.attr('data-action');
                        if(!action) action = ele.parent().attr('data-action');

                        if(['field', 'block', 'edit', 'sub-record', 'explain', 'connector'].indexOf(action) < 0){
                            return;
                        }

                        function __modifyStructureAction(){
                            
                            that._editing.setModified(0);
                            let rec_dlg = that.options.isdialog && that._as_dialog != null ? that._as_dialog.parent() : null; 

                            if(action=='field'){

                                that.options.rts_editor.manageDefRecStructure('showBaseFieldEditor', -1, dt_id, null, rec_dlg);
                            }else if(action=='block'){

                                window.hWin.HAPI4.EntityMgr.getEntityConfig('defRecStructure', function(response){

                                    let fields = response.fields;

                                    for(const idx in fields){

                                        if(fields[idx]['dtID'] != 'rst_SeparatorType'){
                                            continue;
                                        }

                                        let fieldConfig = fields[idx]['dtFields']['rst_FieldConfig'];

                                        let $dlg;
                                        let msg = '<label>Choose separator type:</label><div style="margin: 10px 0px 0px 10px"><select id="sep_type"></select></div>';

                                        let btns = {};
                                        btns[window.HR('Insert')] = () => {

                                            let sep_type = $dlg.find('#sep_type').val(); // get value first

                                            $dlg.dialog('close'); // then close dialog

                                            that.options.rts_editor.manageDefRecStructure('addNewSeparator', dt_id, sep_type);
                                        };
                                        btns[window.HR('Cancel')] = () => { $dlg.dialog('close'); };

                                        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, {title: 'Inserting separator'}, 
                                                {default_palette_class: 'ui-heurist-design', height: 465, width: 285, dialogId: 'add-new-separator'});

                                        let $select = $dlg.find('#sep_type');

                                        window.hWin.HEURIST4.ui.createSelector($select[0], fieldConfig);
                                        window.hWin.HEURIST4.ui.initHSelect($select, false);

                                        if($select.hSelect('instance') !== undefined){

                                            $select.hSelect('option', 'position', {
                                                my: 'left top',
                                                at: 'left top',
                                                of: $select.hSelect('widget')
                                            });

                                            $select.hSelect('open');

                                            let $menu = $select.hSelect('menuWidget');

                                            that._on($select, {
                                                'hselectchange': function(event, ui){ // highlight selection

                                                    const idx = ui.item.index;

                                                    $menu.find('li > div').removeClass('ui-heurist-header');
                                                    $($menu.find('li > div')[idx]).addClass('ui-heurist-header');
                                                },
                                                'hselectclose': function(){ // keep open
                                                    $select.hSelect('open');
                                                }
                                            });

                                            $($menu.find('li > div')[1]).addClass('ui-heurist-header'); // highlight first option
                                            $menu.css('max-height', '30em');
                                        }

                                        // Changes to dialog buttons
                                        $($dlg.parent().find('.ui-dialog-buttonset button')[0]).css({
                                            'margin-right': '20px',
                                            'font-weight': 'bold'
                                        });
                                        $($dlg.parent().find('.ui-dialog-buttonset button')[1]).css({
                                            'margin-right': '10px'
                                        });

                                        break;
                                    }
                                });
                                
                            }else if(action=='edit'){

                                that.options.rts_editor.manageDefRecStructure('editField', dt_id);
                            }else if(action=='sub-record'){

                                that.options.rts_editor.manageDefRecStructure('showBaseFieldEditor', -1, dt_id, null, rec_dlg, true);
                            }else if(action=='explain'){

                                that.options.rts_editor.manageDefRecStructure('addExplanationSeparator', dt_id);
                            }else if(action=='connector'){

                                that.options.rts_editor.manageDefRecStructure('showBaseFieldEditor', -1, dt_id, null, rec_dlg, false, true);
                            }
                        }
                        
                        //save record silently
                        that.saveQuickWithoutValidation( __modifyStructureAction );
                            
                    }
            });
                    
        }
    },

    /**
     * @brief Cleans up the widget, removing event listeners.
     * @memberof heurist.manageRecords
     * @override
     */
    _destroy: function(){
        window.hWin.HAPI4.removeEventListener(this, window.hWin.HAPI4.Event.ON_WINDOW_RESIZE);
        this._super();
    },

    /**
     * @brief Hides the RTS (Record Type Structure) actions menu.
     * @memberof heurist.manageRecords
     * Closes any open hSelect dropdowns within the RTS actions menu and then hides the menu itself.
     */
    hideRtsMenu: function(){
            this.rts_actions_menu.find('.edit_rts').hSelect('close');
            this.rts_actions_menu.hide(); 
    },
    
    /**
     * @brief Saves the current record data quickly without performing full validation.
     * @memberof heurist.manageRecords
     * @param {function} [_callback] Optional callback function to execute after the save operation.
     * If the record has been modified, it retrieves the field values (marking them for no_validation)
     * and calls `_saveEditAndClose`. If a callback is provided, it's passed to `_saveEditAndClose`.
     * If the record is not modified but a callback is provided, the callback is executed directly.
     */
    saveQuickWithoutValidation: function( _callback ){
      
        if(this._editing.isModified()){ //2020-12-06 !this.options.edit_structure &&   
            let fields = this._editing.getValues(false);
            fields['no_validation'] = 1; //do not validate required fields
            this._saveEditAndClose( fields, _callback);           
        }else if(window.hWin.HEURIST4.util.isFunction(_callback)){
            _callback();
        }
        
    },
    
    
    //
    /**
     * @brief Creates and attaches a "gear" button to a field element for RTS (Record Type Structure) editing actions.
     * @memberof heurist.manageRecords
     * @param {number} dtId The DetailTypeID of the field.
     * @param {jQuery} div_ele The jQuery element representing the field's container in the form.
     * This button, when hovered, reveals the `rts_actions_menu` allowing users to edit field properties,
     * insert new fields/blocks, etc., if RTS editing is active. It populates the menu with current
     * field properties like requirement type, repeatability, and display width.
     */
    _createRtsEditButton : function(dtId, div_ele){  
        
        let that = this;
                      
        let rst_fields = (dtId != null) ? $Db.rst(that._currentEditRecTypeID, dtId) : null;
        if(rst_fields){
            
            let sep_id = $(div_ele).attr('separator-dtid');

            let ele = $(`<div${sep_id > 0 ? ` data-dtid="${sep_id}"` : ''}><span class="ui-icon ui-icon-gear"></span></div>`)
                .css({display: sep_id > 0 ? 'inline-block' : 'table-cell','vertical-align': 'top','min-width': '32px',cursor: 'pointer','padding-top': '0.4em'});

            if(sep_id>0){
                ele.insertBefore($(div_ele));    
            }else{
                ele.prependTo($(div_ele));        
            }

            that._on(ele,{mouseover:function(event){
                clearTimeout(that._menuTimeoutId);
                let el = $(event.target);

                let dtId = el.attr('data-dtid');
                if(!(dtId>0)) dtId = el.parents('div[data-dtid]').attr('data-dtid');

                let rst_fields = $Db.rst(that._currentEditRecTypeID, dtId);
                
                let dt_type = $Db.dty(dtId, 'dty_Type');
                if(dt_type=='separator'){

                    that.rts_actions_menu.find('.edit_rts_sel').hide();
                }else{

                    that.rts_actions_menu.find('.edit_rts_sel').show();
                    that.rts_actions_menu.find('select.s_reqtype').val(rst_fields['rst_RequirementType']).hSelect('refresh');
                    let v = rst_fields['rst_MaxValues'];
                    that.rts_actions_menu.find('select.s_repeat').val(v!=null && v>=0?v:0).hSelect('refresh');
                    let prev_v = 5;
                    if(dt_type=='freetext' || dt_type=='blocktext' || dt_type=='float'){
                        that.rts_actions_menu.find('div.s_width').show();
						prev_v = 0;
                    }else{
                        that.rts_actions_menu.find('div.s_width').hide()    
                    }
                    v = Number(rst_fields['rst_DisplayWidth']);
                    if(isNaN(v) || window.hWin.HEURIST4.util.isempty(v)) v=100;
                   
                    that.rts_actions_menu.find('select.s_width > option').each(function(i,item){
                        if(Number($(item).val())>v){
                            v = prev_v;
                            return false;
                        }
                        prev_v = Number($(item).val());
                    });
                    if(prev_v<v) v = prev_v;
                    
                    that.rts_actions_menu.find('select.s_width').val(v).hSelect('refresh');
                }

                that.rts_actions_menu.find('.edit_rts').hSelect('close');
                
                that.rts_actions_menu.find('.edit_rts_btn').hide();
                that._rts_changed_flag = false;
                let positionElement = el.hasClass('ui-icon') ? el : el.find('.ui-icon').first();
                that.rts_actions_menu
                    .attr('data-did', dtId)
                    .show()
                    .position({ my:'left top+10', at:'left bottom', of: positionElement});

                that.options.rts_editor.manageDefRecStructure('highlightNode', dtId);

                }, mouseout: function(event){
                    that._menuTimeoutId = setTimeout(function() {
                        that.hideRtsMenu();
                        if(that.options.rts_editor){
                            that.options.rts_editor.manageDefRecStructure('highlightNode', null); 
                        }
                    }, 100);
            }});
        }else{
            //placeholder
            $('<div>').css({'display':'table-cell','min-width':'32px'})
            .prependTo($(div_ele));    
        }
    },
    
    //
    /**
     * @brief Creates an "add to structure" button for a non-standard field present in the record data but not in the current Record Type Structure.
     * @memberof heurist.manageRecords
     * @param {number} dtId The DetailTypeID of the non-standard field.
     * @param {jQuery} div_ele The jQuery element where the button should be inserted (typically the field's input cell).
     * Clicking this button adds the non-standard field to the current record type's structure,
     * making it a standard field. It determines the display order and other default properties for the new structure element.
     * This is relevant when `options.rts_editor` is active.
     */
    _createNonStandardField: function(dtId, div_ele){

        let that = this;
        const parententity = Number(window.hWin.HAPI4.sysinfo['dbconst']['DT_PARENT_ENTITY']);
        const workflowstage = Number(window.hWin.HAPI4.sysinfo['dbconst']['DT_WORKFLOW_STAGE']);

        if(dtId == null || $Db.rst(this._currentEditRecTypeID, dtId) != null 
            || $Db.dty(dtId, 'dty_Type') == 'separator' 
            || dtId == parententity || dtId == workflowstage){
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
                    if(f['rst_DisplayOrder'] >= display_order){
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
                    rst_DisplayHeight: '3',
                    rst_SemanticReferenceURL: $Db.dty(dtid,'dty_SemanticReferenceURL'),
                    rst_TermsAsButtons: 0
                };

                let request = {
                    'a'          : 'save',
                    'entity'     : 'defRecStructure',
                    'request_id' : window.hWin.HEURIST4.util.random(),
                    'fields'     : fields
                };

                let $dlg = that._getEditDialog();
                if($dlg) window.hWin.HEURIST4.msg.bringCoverallToFront($dlg);

                window.hWin.HAPI4.EntityMgr.doRequest(request, (response) => {

                    if(response.status == window.hWin.ResponseStatus.OK){
                        window.hWin.HAPI4.EntityMgr.refreshEntityData(['defRecStructure'], function(){
                            that.reloadEditForm(true); // reload record editor
                            that._reloadRtsEditor(true); // reload structure editor
                            window.hWin.HEURIST4.msg.sendCoverallToBack();
                            window.hWin.HEURIST4.msg.showMsgFlash(
                                window.hWin.HR('Field added to record type structure'), 1500);
                        });
                    }else{
                        window.hWin.HEURIST4.msg.sendCoverallToBack();
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
            }
        });
    },
    
    //  
    /**
     * @brief Initializes the main controls of the widget after the entity configuration is loaded.
     * @override
     * @memberof heurist.manageRecords
     * @returns {boolean} False if the parent `_super()` call fails, otherwise true.
     * This method sets up user interface preferences, initializes the search form (`searchRecords`),
     * configures the record list (`resultList`) including its renderer and action listeners,
     * and adjusts the layout based on the widget's mode (e.g., manager vs. select).
     * It also binds event listeners for search actions and results.
     */
    _initControls: function() {

        this.getUiPreferences();

        let reset_to_defs = !this.options.resultList || !this.options.resultList.searchfull;
        
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
                    this.options.selectOnSave = true;
                    this.addEditRecord(-1);
                },
                "searchrecordsonlinkscount": function( event, data ){
                    this.recordList.resultList({
                        aggregate_values: data.links_count,
                        aggregate_link: data.links_query,
                        aggregate_pretext: 'n='
                    });
                },
                
        });
        }
                

        this.editForm.css({'overflow-y':'auto !important', 'overflow-x':'hidden', 'padding':'5px'});
         
        return true;
    },
    
    //
    /**
     * @brief Adjusts the top position of the record list based on the visibility and height of the search form.
     * @memberof heurist.manageRecords
     * If the search form is present and visible, the record list's `top` CSS property is set to the
     * search form's height. Otherwise, it's set to 0. This ensures the list is positioned correctly
     * below the search form.
     */
    _adjustResultListTop: function(){
         if(this.searchForm && this.searchForm.length>0 && this.searchForm.is(':visible')){
             this.recordList.css('top', this.searchForm.height());
         }else{
             this.recordList.css('top', 0); 
         }
        
    },
    
    //
    /**
     * @brief Handles actions triggered from the record list or other UI elements.
     * @override
     * @memberof heurist.manageRecords
     * @param {Event} event The event object.
     * @param {object|string} action The action to perform. If an object, it may contain `recID` and `action` properties.
     * @returns {boolean} True if the action was handled, false otherwise.
     * This method extends the parent's `_onActionListener`. It specifically handles actions like
     * 'edit_ext' (edit record in a new window/tab) and 'save_quick' (quick save without validation).
     */
    _onActionListener:function(event, action){    
            let res = this._super(event, action)
            if(!res){
                
                 let recID = 0;
                 if(action && action.action){
                     recID =  action.recID;
                     action = action.action;
                 }
                
                if(action=='edit_ext' && recID>0){
                    let url = window.hWin.HAPI4.baseURL + "?fmt=edit&db="+window.hWin.HAPI4.database+"&recID="+recID;
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
    /**
     * @brief Initializes properties for the edit dialog, particularly when in 'editonly' mode.
     * @override
     * @memberof heurist.manageRecords
     * This method is called before the edit dialog is created or shown. It restores UI preferences
     * like width and height from `usrPreferences` if the widget is in 'editonly' mode.
     * It ensures that if a `forced_Width` is provided in options, it takes precedence.
     */
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
    /**
     * @brief Navigates to the previous or next record in the current list.
     * @memberof heurist.manageRecords
     * @param {number} dest A number indicating direction: -1 for previous, 1 for next.
     * If the current record has been modified, it prompts the user to save or drop changes
     * before navigating. Updates the navigation display (e.g., "record X of Y").
     */
    _navigateToRec: function(dest){
        if(this._currentEditID>0){
                let recset = this.recordList.resultList('getRecordSet');
                let order  = recset.getOrder();
                let idx = order.indexOf(Number(this._currentEditID));
                idx = idx + dest;
                if(idx>=0 && idx<order.length){
                    
                    let newRecID = order[idx];
                    let that = this;
                    
                    if(this._editing.isModified()){
                        
                        let $__dlg = window.hWin.HEURIST4.msg.showMsgDlg(
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
                            {title:'Confirm'}, {dialogId: 'warn-navigate', height: 154});
                           
                         let dlged = that._as_dialog.parent('.ui-dialog');   
                         $__dlg.parent('.ui-dialog').css({
                                top: dlged.position().top+dlged.height()-200,
                                left:that._toolbar.find('.btnPrev').position().left});    
                    }else if(this._toolbar) {
                        this._toolbar.find('.divNav').html( (idx+1)+'/'+order.length);
                        
                        window.hWin.HEURIST4.util.setDisabled(this._toolbar.find('.btnPrev'), (idx==0));
                        window.hWin.HEURIST4.util.setDisabled(this._toolbar.find('.btnNext'), (idx+1==order.length));
                        
                        if(dest!=0){
                            this.addEditRecord(newRecID);
                        }
                    }
                }
        }
    },    
    //override some editing methods
    
    /**
     * @brief Gets the configuration for buttons to be displayed in the edit dialog.
     * @override
     * @memberof heurist.manageRecords
     * @returns {object[]} An array of button configuration objects.
     * The buttons change based on the widget's state, such as `options.selectOnSave`,
     * `options.edit_structure`, or normal record editing mode. Buttons can include
     * Save, Save + Close, Close, Drop Changes, Dupe, New, and navigation (Prev/Next).
     */
    _getEditDialogButtons: function(){
        
            let that = this;        
            let btns = [];

                if(this.options.selectOnSave==true){
                    btns = [               
                                
                        {text:window.hWin.HR('Save'), class:'btnRecSave',
                              accesskey:"S",
                              css:{'font-weight':'bold'},
                              click: function() { that._saveEditAndClose( null, 'none' ); }},
                        {text:window.hWin.HR('Save + Close'), class:'btnRecSaveAndClose',
                              css:{'margin-left':'0.5em'},
                              click: function() { that._saveEditAndClose( null, 'close' ); }},
                        {text:window.hWin.HR('Close'), 
                              css:{'margin-left':'0.5em'},
                              click: function() { 
                                    that.options.select_mode = 'select_single'
                                    that.selectedRecords(that._currentEditRecordset);
                                  
                              }},
                        {text:window.hWin.HR('Drop Changes'), class:'btnRecCancel', 
                              css:{'margin-left':'3em'},
                              click: function() { that._initEditForm_step3(that._currentEditID) }},  //reload edit form
                              
                              
                              ];    
                }else if(this.options.edit_structure==true){
                    // Check if rectype has fields
                    btns = [ 
                        {text:window.hWin.HR('Save'), class:'btnRecSave', //AndClose
                          css:{'margin-left':'0.5em'},
                          click: function() { that._saveEditAndClose( null, 'none' ); }}, //'close'
                        {text:window.hWin.HR('Close'), 
                          click: function() { 
                            let recset = $Db.rst(that._currentEditRecTypeID);
                            let hasField = false;
                            let btns = {};
                            let $dlg;

                            if(that.options.rts_editor && that.options.rts_editor.manageDefRecStructure('checkIfEditing')){

                                btns[window.hWin.HR('Save changes')] = () => {
                                    that.element.find('.btnRecSaveAndClose_rts').trigger('click');
                                    $dlg.dialog('close');
                                    setTimeout(() => { that.closeEditDialog() }, 2000);
                                };
                                btns[window.hWin.HR('Close without saving')] = () => {
                                    $dlg.dialog('close');
                                    that.closeEditDialog();
                                };

                                $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                                    'You have un-saved structure changes which will be lost if not saved<br>Would you like to save your changes before closing?',
                                    btns,
                                    {title: 'Un-saved structure changes', no: window.hWin.HR('Close without saving'), yes: window.hWin.HR('Save changes')},
                                    {default_palette_class: 'ui-heurist-design'}
                                );

                                return;
                            }

                            if(!window.hWin.HEURIST4.util.isempty(recset)){

                                recset.each2(function(id, f){
                                    if($Db.dty(f.rst_ID, 'dty_Type') == 'separator'){ return; }
                                    hasField = true;
                                });
                            }

                            if(!hasField){ // check if any fields have been added to rectype

                                btns[window.hWin.HR('Continue editing')] = function(){
                                    $dlg.dialog('close');
                                };
                                btns[window.hWin.HR('Exit with no fields')] = function(){
                                    $dlg.dialog('close');
                                    that.closeEditDialog();
                                };

                                $dlg = window.hWin.HEURIST4.msg.showMsgDlg('You need to define fields to make this record type usable.', 
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
                
                    btns = [

                        {showLabel:false, icon:'ui-icon-circle-triangle-w',title:window.hWin.HR('Previous'),
                              css:{'display':'none','margin-right':'0.5em',}, class:'btnPrev',
                              click: function() { that._navigateToRec(-1); }},
                        {showLabel:false, icon:'ui-icon-circle-triangle-e',iconPosition:'end',title:window.hWin.HR('Next'),
                              css:{'display':'none','margin-left':'0.5em','margin-right':'1.5em'}, class:'btnNext',
                              click: function() { that._navigateToRec(1); }},
                              
                        {text:window.hWin.HR('Dupe'), class:'btnRecDuplicate',
                            css:{'margin-left':'1em'},
                            click: function(event) { 

                                function duplicate_record(){ 
                                    let btn = $(event.target);                        
                                    btn.hide();

                                    let dlged = that._getEditDialog();
                                    if(dlged) window.hWin.HEURIST4.msg.bringCoverallToFront(dlged);

                                    window.hWin.HAPI4.RecordMgr.duplicate({id: that._currentEditID}, 
                                        function(response){

                                            window.hWin.HEURIST4.msg.sendCoverallToBack();

                                            btn.css('display','inline-block');  //restore button visibility
                                            if(response.status == window.hWin.ResponseStatus.OK){

                                                window.hWin.HEURIST4.msg.showMsgFlash(
                                                    window.hWin.HR('Record has been duplicated'), 3000);

                                                const new_recID = ''+response.data.added;
                                                that._duplicatedRecord = that._currentEditID;

                                                that._initEditForm_step3(new_recID);

                                                let dlged = that._getEditDialog();
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
                        {text:window.hWin.HR('New'), class:'btnRecSaveAndNew',
                              css:{'margin-left':'0.5em','margin-right':'10em'},
                              click: function() { 

                                    let isChanged = that._editing.isModified() || that._updated_tags_selection!=null;
                                    if(isChanged){
                                        that._saveEditAndClose( null, 'newrecord' );      
                                    }else{
                                        that._initEditForm_step3(-1);
                                    }
                              }},
                              
                        {text:window.hWin.HR('Save'), class:'btnRecSave',
                              accesskey:"S",
                              css:{'font-weight':'bold'},
                              click: function() { that._saveEditAndClose( null, 'none' ); }},
                        {text:window.hWin.HR('Save + Close'), class:'btnRecSaveAndClose',
                              css:{'margin-left':'0.5em'},
                              click: function() { that._saveEditAndClose( null, 'close' ); }},
                        {text:window.hWin.HR('Close'), 
                              css:{'margin-left':'0.5em'},
                              click: function() { 
                                  that.closeEditDialog(); 
                              }},
                        {text:window.hWin.HR('Drop Changes'), class:'btnRecCancel', 
                              css:{'margin-left':'3em'},
                              click: function() { that._initEditForm_step3(that._currentEditID) }},  //reload edit form
                              
                              ];
                }
            
            return btns;
    },
    
    //
    /**
     * @brief First step in initializing the edit form, handling popup mode.
     * @override
     * @memberof heurist.manageRecords
     * @param {number} recID The ID of the record to edit, or a negative value for a new record.
     * If `options.edit_mode` is 'popup', this method may open the record editor in a new
     * widget instance (`HEURIST4.ui.openRecordEdit`) rather than inline. It prepares
     * options for the popup, including callbacks for selection if in `selectOnSave` mode.
     * Otherwise, it calls the parent's `_super` method.
     */
    _initEditForm_step1: function(recID){

        this.element.attr('data-recid', recID);
        if(this.options.edit_mode=='popup'){

            let query = null, popup_options={};
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
                    let that = this;
                    popup_options['onselect'] = function(event, data){
                            if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                
                                that._trigger( "onselect", null, {
                                    selection: (that.options.select_return_mode=='recordset') ?data.selection :data.selection.getIds()});
                                that.closeDialog();
                            }
                    };
                    
                }
            }else{
                let recset = this.recordList.resultList('getRecordSet');
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
    /**
     * @brief Second step in initializing the edit form, setting up the dialog and layout.
     * @override
     * @memberof heurist.manageRecords
     * @param {number} recID The ID of the record.
     * This method sets up the edit dialog if not already open, including its buttons
     * and title. It configures the layout (e.g., west panel for structure, east for summary)
     * using `jquery.layout` and initializes the summary panel accordion. It also handles
     * navigation buttons (Prev/Next) if applicable.
     */
    _initEditForm_step2: function(recID){
    
        if(recID==null || this.options.edit_mode=='none') return;
        
        let isOpenAready = false;
        if(this.options.edit_mode=='popup'){
            if(this._edit_dialog && this._edit_dialog.dialog('instance')){
                isOpenAready = this._edit_dialog.dialog('isOpen');
            }
        } else if(this.options.edit_mode=='inline') { //inline 
            isOpenAready = !this.editFormToolbar.is(':empty');
        }

        if(!isOpenAready){            
    
            let that = this; 
            this._currentEditID = (recID<0)?0:recID;
            
            if(this.options.edit_mode=='popup'){
                //OLD WAY - NOT USED
                this.editForm.css({'top': 0});

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
               
                    
                //help and tips buttons on dialog header
                this._edit_dialog.addClass('manageRecords'); //need for special behaviour in applyCompetencyLevel
                
                if(this.options.helpContent){
                    let helpURL = window.hWin.HRes( this.options.entity.helpContent )+' #content';
                    window.hWin.HEURIST4.ui.initDialogHintButtons(this._edit_dialog, null, helpURL, false);    
                }
        
                this._toolbar = this._edit_dialog.parent().find('.ui-dialog-buttonpane');
        
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
                    
                    let btn_array = this._getEditDialogButtons();
                    
                    this._toolbar = this.editFormToolbar;
                    this.editFormToolbar.empty();
                    let btn_div = $('<div>').addClass('ui-dialog-buttonset').appendTo(this._toolbar);
                    for(let idx in btn_array){
                        this._defineActionButton2(btn_array[idx], btn_div);
                    }
                }
            }
            
            if(this._toolbar){
                this._toolbar.find('.ui-dialog-buttonset').css({'width':'100%','text-align':'right'});
            
                if(this._toolbar.find('.divNav3').length===0){
                       
                        $('<div class="divNav3" style="font-weight:bold;display:inline-block;text-align:right">Save then</div>')
                            .insertBefore(this._toolbar.find('.btnRecDuplicate'));
                }
            }
            
            
            let recset = this.recordList && this.recordList.resultList('instance') && this.recordList.resultList('getRecordSet');
            if(recset && recset.length()>1 && recID>0){
                if(this._toolbar){
                    this._toolbar.find('.btnPrev').css({'display':'inline-block','height':'2.1em','background':'#f2f2f2'});
                    this._toolbar.find('.btnNext').css({'display':'inline-block','height':'2.1em','background':'#f2f2f2'});
                    if(this._toolbar.find('.divNav').length===0){
                        $('<div class="divNav2" style="display:inline-block;font-weight:bold;padding:0.8em 1em;text-align:right">Step through filtered subset</div>')
                        .insertBefore(this._toolbar.find('.btnPrev'));
                        
                        $('<div class="divNav" style="display:inline-block;font-weight:bold;padding-top:0.8em;min-width:40px;text-align:center">')
                        .insertBefore(this._toolbar.find('.btnNext'));
                    }
                }
                this._navigateToRec(0); //reload
            }else if(this._toolbar){
                this._toolbar.find('.btnPrev').hide();
                this._toolbar.find('.btnNext').hide();
                this._toolbar.find('.divNav').hide();
                this._toolbar.find('.divNav2').hide();
            }
            
            if(this.options.allowAdminToolbar===false){
               if(this.editFormSummary) this.editFormSummary.remove();//hide();  
               this.editFormSummary = null;
            }else
            //summary tab - specific for records only    
            if(this.editFormSummary && this.editFormSummary.length>0){
                let layout_opts =  {
                    applyDefaultStyles: true,
                    enableCursorHotkey: false,
                    //togglerContent_open:    '&nbsp;',
                    //togglerContent_closed:  '&nbsp;',
                    north:{
                        size: 40,
                        maxHeight: 40,
                        minHeight: 40,
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
                        initClosed: !this.options.edit_structure && !this.options.rts_editor,
                        slidable:false,  //otherwise it will be over center and autoclose
                        contentSelector: '.editStructure',   
                        onopen_start : function( ){ 
                            let tog = that.element.find('.ui-layout-toggler-west');
                            tog.removeClass('prominent-cardinal-toggler togglerVertical');
                            tog.find('.heurist-helper2.westTogglerVertical').hide();
                        },
                        onclose_end : function( ){ 
                            let tog = that.element.find('.ui-layout-toggler-west');
                            tog.addClass('prominent-cardinal-toggler togglerVertical');

                            if(tog.find('.heurist-helper2.westTogglerVertical').length > 0){
                                tog.find('.heurist-helper2.westTogglerVertical').show();
                            }else{
                                $('<span class="heurist-helper2 westTogglerVertical" style="width:200px;margin-top:220px;">Navigate / Move / Delete</span>').appendTo(tog);
                            }
                        },
                        onresize_end: function(){
                            that.options.rts_editor.manageDefRecStructure('updateFieldUsage');
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
                            let tog = that.editFormPopup.find('.ui-layout-toggler-east');
                            tog.removeClass('prominent-cardinal-toggler togglerVertical');
                            tog.find('.heurist-helper2.eastTogglerVertical').hide();
                        },
                        onclose_end : function(){ 
                            let tog = that.editFormPopup.find('.ui-layout-toggler-east');
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

                    let tog = that.editFormPopup.find('.ui-layout-toggler-east');
                    tog.addClass('prominent-cardinal-toggler togglerVertical');

                    if(tog.find('.heurist-helper2.eastTogglerVertical').length > 0){
                        tog.find('.heurist-helper2.eastTogglerVertical').show();
                    }else{
                        $('<span class="heurist-helper2 eastTogglerVertical" style="width:200px;">'
                            +window.hWin.HR('Record Summary')+'</span>').appendTo(tog);
                    }
                }
                
                
                //this tabs are open by default
                this.usrPreferences.summary_tabs = ['0','1','2','3']; //since 2018-03-01 always open

                //load content for editFormSummary
                if(this.editFormSummary.text()=='....'){
                    this.editFormSummary.empty();

                    let headers = ['Admin','Private','Tags','Linked records','Scratchpad','Discussion','History']; //,'Dates','Text',
                    
                    if(window.hWin.HAPI4.is_guest_user()){
                         headers = ['Admin','Linked records'];  
                    }
                    
                    for(let idx in headers){
                        let acc = $('<div>').addClass('summary-accordion').appendTo(this.editFormSummary);
                        
                        $('<h3>').text(window.hWin.HR(headers[idx])).appendTo(acc);
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
                        left: '0px',
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
    /**
     * @brief Handles resizing of tabbed content within the edit form.
     * @memberof heurist.manageRecords
     * This method is typically called when the dialog or a layout panel is resized.
     * It uses a timer to debounce resize events and then calls the `pagingResize`
     * method on any tab instances to adjust their layout.
     */
    handleTabsResize: function() {
            if (this._resizeTimer) {
                clearTimeout(this._resizeTimer);
            }
            
                let that = this;
                this._resizeTimer = setTimeout(
                    function(){                
                            let ele = that.editForm.find('.ui-tabs');
                            if(ele.length>0){
                                try{
      
                                    for(let i=0; i<ele.length; i++){  //
                                        if($(ele[i]).tabs('instance')){
                                            $(ele[i]).tabs('pagingResize');    
                                        }
                                    }                              
                                }catch{
                                    console.error('cannot resize tab');
                                }
                            }
                    }, 200);
            
    },
    
    //
    /**
     * @brief Handles the resize event of the dialog.
     * @override
     * @memberof heurist.manageRecords
     * Calls the parent's `_onDialogResize`, adjusts the result list's top position,
     * and saves UI preferences.
     */
    _onDialogResize: function(){
        this._super();
        this._adjustResultListTop();
        this.saveUiPreferences(); // save UI settings
    },
    
    //
    /**
     * @brief Closes the edit dialog.
     * @override
     * @memberof heurist.manageRecords
     * Handles closing the dialog based on its mode (`editonly` or standard popup).
     * It ensures that UI preferences are saved before closing if `options.edit_mode` is 'editonly'.
     */
    closeEditDialog:function(){
        
        //save preferences
        
        if(this.options.edit_mode=='editonly'){
            
            if(this.options.isdialog){
                if(this._as_dialog.dialog('instance')){
                    this._as_dialog.dialog('close');    
                }
                
            }else{
                window.close(this._currentEditRecordset);
            }
            
        }else if(this._edit_dialog && this._edit_dialog.dialog('instance') && this._edit_dialog.dialog('isOpen')){
            this._edit_dialog.dialog('close');
        }
    },
    
    //
    /**
     * @brief Fills the content of a specific panel within the summary accordion.
     * @memberof heurist.manageRecords
     * @param {jQuery} panel The jQuery object representing the panel to fill.
     * The content depends on the panel's `data-id`, which corresponds to different
     * summary sections like Admin, Private (bookmarks/reminders), Tags, Linked records, etc.
     * It dynamically loads and renders the relevant information.
     */
    _fillSummaryPanel: function(panel){
        
        let that = this;
        let sContent = '';
        let idx = Number(panel.attr('data-id'));
        
        let ph_gif = window.hWin.HAPI4.baseURL + 'hclient/assets/16x16.gif';
        
        panel.empty();
        
        let is_guest_user = window.hWin.HAPI4.is_guest_user();
        if(idx==1 && is_guest_user){
            idx = 3;
        }
        
        //Admin 0, Private 1, Tags 2, Linked 3, Scratchpad 4, Discussion 5
        
        switch(idx){
            case 0:   //admins -------------------------------------------------------
            {
               let sAccessGroups = '';
               if(that._getField('rec_NonOwnerVisibility')=='viewable' && that._getField('rec_NonOwnerVisibilityGroups')){
                   sAccessGroups = that._getField('rec_NonOwnerVisibilityGroups');
                   if(!Array.isArray(sAccessGroups)){  sAccessGroups = sAccessGroups.split(','); }
                   let cnt = sAccessGroups.length;
                   if(cnt>0){
                       sAccessGroups = ' for '+cnt+' group'+(cnt>1?'s':'');
                   }
               }
            
                const recRecTypeID = that._getField('rec_RecTypeID');
                const recRecTypeIcon = window.hWin.HAPI4.iconBaseURL+recRecTypeID+that._icon_timer_suffix;
                
                sContent =  
'<div style="margin:10px 4px;"><div style="padding-bottom:0.5em;display:inline-block;width: 100%;">'

+'<h3 class="truncate rectypeHeader" style="float:left;max-width:400px;margin:0 8px 0 0;">'
                + '<img src="'+ph_gif+'" class="rt-icon" style="vertical-align:top;margin-right: 10px;background-image:url(\''
                + recRecTypeIcon +'\');"/>'
                + $Db.rty(recRecTypeID, 'rty_Name')+'</h3>'
+'<select class="rectypeSelect ui-corner-all ui-widget-content" '
+'style="display:none;z-index: 20;position: absolute;border: 1px solid gray;' 
+'top: 5.7em;" size="20"></select><div class="btn-modify non-owner-disable btns-noguest-only"></div></div>'

/* this section is moved on top of editForm 2017-12-21
+'<div style="display:inline-block;float:right;">'   
    +'<div class="btn-config2"></div><div class="btn-config"></div>'  //buttons
    +'<span class="btn-config3" style="cursor:pointer;display:inline-block;float:right;color:#7D9AAA;padding:2px 4px;">Modify structure</span>'
+'</div>'
+'</div>'
*/

+'<div style="padding-bottom:0.5em;width: 100%;">'
+'<div><label class="small-header">Owner:</label><span id="recOwner">'
    +that._getField('rec_OwnerUGrpID')+'</span><div class="btn-access non-owner-disable btns-noguest-only"></div>'        
+'</div>'
+'<div><label class="small-header">Access:</label><span id="recAccess">'
    + that._getField('rec_NonOwnerVisibility')
    + sAccessGroups  
    +'</span>'
+'</div></div>'

+'<div>'
+'<div class="truncate"><label class="small-header">Added By:</label><span class="recAddedBy">'+that._getField('rec_AddedByUGrpID')+'</span></div>'
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
                            panel.find('.recAddedBy').text(response.data[that._getField('rec_AddedByUGrpID')]);
                        }
                });

                
                
                //activate buttons
                /* moved to top of editForm 2017-12-21
                panel.find('.btn-config2').button({showLabel:false,label:window.hWin.HR('Modify record type structure in new window'),
                        icon:'ui-icon-extlink'})
                    .addClass('ui-heurist-btn-header1')
                    .css({float: 'right','font-size': '0.8em', height: '18px', 'margin-left':'4px'})
                    .on('click', function(){
                        that.editRecordTypeOnNewTab();
                    });
                    
                panel.find('.btn-config').button({showLabel:false,label:window.hWin.HR('Modify record type structure'),
                        icon:'ui-icon-gear'})
                    .addClass('ui-heurist-btn-header1')
                    .css({float: 'right','font-size': '0.8em', height: '18px', 'margin-left':'4px'})
                    .on('click', function(){that.editRecordType();});
                panel.find('.btn-config3').on('click', function(){that.editRecordType();});
                */
                    
                let btn_change_rt = panel.find('.btn-modify');                        
                btn_change_rt.button({showLabel:false, label:window.hWin.HR('Change record type'),
                        icon:'ui-icon-triangle-1-s'})
                    //.addClass('ui-heurist-btn-header1')
                    .css({float: 'left','font-size': '0.8em', height: '14px', width: '14px'})
                    .on('click', function(){
                         let selRt = panel.find('.rectypeSelect');
                         let selHd = panel.find('.rectypeHeader');
                         if(selRt.is(':visible')){
                             btn_change_rt.button('option',{icon:'ui-icon-triangle-1-s'});
                             selRt.hide();
                            
                             
                         }else{
                             btn_change_rt.button('option',{icon:'ui-icon-triangle-1-n'});
                             selRt.show();
                             //
                             if(selRt.is(':empty')){
                                window.hWin.HEURIST4.ui.createRectypeSelect(selRt.get(0), null, null, true);    
                                selRt.on('change',function(){
                                    
                                    if(that._getField('rec_RecTypeID')!=selRt.val()){
                                                                         
                                    window.hWin.HEURIST4.msg.showMsgDlg(
                                        '<h4>Changing record type</h4><br>'+
                                        'Data will be re-allocated to appropriate fields, where available. '+
                                        'Not all data may fit in the new record structure, but these data '+
                                        'are retained and shown at the end of the form. No data will be lost, '+
                                        'even when the record is saved.', 
                                        function() {
                                        
                                              that._editing.assignValuesIntoRecord();
                                              let record = that._currentEditRecordset.getFirstRecord();
                                              that._currentEditRecordset.setFld(record, 'rec_RecTypeID', selRt.val());
                                              that._initEditForm_step4(null); //reload form
                                              
                                              that._editing.setModified(true); //forcefully set to modified after rt change
                                              that.onEditFormChange();
                                              
                                              selHd.html(
                        '<img src="'+ph_gif+'"  class="rt-icon" style="vertical-align:top;margin-right: 10px;background-image:url(\''
                        + window.hWin.HAPI4.iconBaseURL+selRt.val()+that._icon_timer_suffix+'\');"/>'
                        + $Db.rty(selRt.val(), 'rty_Name')                                      
                                              );
                                                                                      
                                        },
                                        {title:'Warning',yes:'Proceed',no:'Cancel'});
                                    } 
                                    btn_change_rt.button('option',{icon:'ui-icon-triangle-1-s'});
                                   
                                    selRt.hide();
                                    
                                });
                             }
                            
                         }
                        
                         
                    });
                    

           function __getEditFieldValue(sField){
               let ele = that._editing.getFieldByName(sField);
               if(ele && ele.editing_input('instance')){
                   let vals = ele.editing_input('getValues');
                   return vals[0];
               }
               return '';
           }
                    
           //
           //
           //   
           function __getUserNames(stype){
               
               let sField, sPanel; 
               if(stype=='access'){
                   sField = 'rec_NonOwnerVisibilityGroups';
                   sPanel = '#recAccess';
               }else{
                   sField = 'rec_OwnerUGrpID';
                   sPanel = '#recOwner';
               }
               
               let vals = __getEditFieldValue(sField);

               window.hWin.HAPI4.SystemMgr.usr_names({UGrpID:vals, context:sPanel},
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            let txt = [], title = [], cnt = 0;
                            for(let ugr_id in response.data){
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
                            let sPanel = response.context;
                            panel.find(sPanel).text(txt).attr('title',title.join(', '));
                        }
                });
            
           }
            
                    
                    
            panel.find('.btn-access').button({showLabel:false,label:window.hWin.HR('Change ownership and access rights'),
                        icon:'ui-icon-pencil'})
                    //.addClass('ui-heurist-btn-header1')
                    .css({float: 'right','margin': '0 0 0.8em 7px', 'font-size': '0.8em', height: '14px', width: '14px'})
                    .on('click', function(){

           //
           // change ownership
           //             
           function __assignOwnerAccess(context){

               if(context && context.NonOwnerVisibility){
                    
                    let val = __getEditFieldValue('rec_OwnerUGrpID');
                    if(val!=context.OwnerUGrpID){
                        if(context.OwnerUGrpID == 'current_user') 
                            context.OwnerUGrpID = window.hWin.HAPI4.user_id();
                        
                        let ele = that._editing.getFieldByName('rec_OwnerUGrpID');
                        ele.editing_input('setValue',[context.OwnerUGrpID]);
                        ele.editing_input('isChanged', true);
                        
                        //update user name
                        __getUserNames('owner');
                    }

                    
                    val = __getEditFieldValue('rec_NonOwnerVisibility');
                    if(val!=context.NonOwnerVisibility){
                        let ele = that._editing.getFieldByName('rec_NonOwnerVisibility');
                        ele.editing_input('setValue',[context.NonOwnerVisibility]);
                        ele.editing_input('isChanged', true);
                        panel.find('#recAccess').html(context.NonOwnerVisibility);
                    }
                    
                    //change visibility per group
                    val = __getEditFieldValue('rec_NonOwnerVisibilityGroups');
                    if(val!=context.NonOwnerVisibilityGroups){
                        //update usrRecPermissions
                        let ele = that._editing.getFieldByName('rec_NonOwnerVisibilityGroups');
                        ele.editing_input('setValue',[context.NonOwnerVisibilityGroups]);
                        ele.editing_input('isChanged', true);
                        if(context.NonOwnerVisibility=='viewable') __getUserNames('access');
                    }
                    
                    that.onEditFormChange();
                }
                
        }                        
                        
        //show dialog that changes ownership and view access                   
        window.hWin.HEURIST4.ui.showRecordActionDialog('recordAccess', {
               currentOwner:  __getEditFieldValue('rec_OwnerUGrpID'),
               currentAccess: __getEditFieldValue('rec_NonOwnerVisibility'),
               currentAccessGroups: __getEditFieldValue('rec_NonOwnerVisibilityGroups'),
               scope_types: 'none', onClose: __assignOwnerAccess,
               height:400, width: 540,
               default_palette_class: 'ui-heurist-populate'
        });
              
                    }); //on edit ownership click
            
            
            __getUserNames('owner');
            if(that._getField('rec_NonOwnerVisibility')=='viewable') __getUserNames('access');
            
  
                break;
            }    
            case 3:   //find all reverse links
            {
                let relations = that._currentEditRecordset.getRelations();    
                
                if(!relations) break;
                
                let direct = relations.direct;
                let reverse = relations.reverse;
                let headers = relations.headers;
                let ele1=null, ele2=null;
                
                //direct relations                            
                let sRel_Ids = [];
                for(let k in direct){
                    if(direct[k]['trmID']>0){ //relation    
                        let targetID = direct[k].targetID;
                        
                        if(!headers[targetID]){
                            //there is not such record in database
                            continue;                                            
                        }
                        
                        sRel_Ids.push(targetID);
                        
                        if(sRel_Ids.length<25){
                            let ele = window.hWin.HEURIST4.ui.createRecordLinkInfo(panel, 
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
                for(let k in reverse){
                    if(reverse[k]['trmID']>0){ //relation    
                        const sourceID = reverse[k].sourceID;
                        if(!headers[sourceID]){
                            //there is not such record in database
                            continue;                                            
                        }
                        
                        sRel_Ids.push(sourceID);
                        
                        if(sRel_Ids.length<25){
                        
                            let invTermID = window.hWin.HEURIST4.dbs.getInverseTermById(reverse[k]['trmID']);
                            
                            let ele = window.hWin.HEURIST4.ui.createRecordLinkInfo(panel, 
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
                    +'" target="_blank">more ('+(sRel_Ids.length-25)
                    +'<span class="ui-icon ui-icon-extlink" style="font-size:0.8em;top:2px;right:2px"></span>'
                    +') </a></div>').appendTo(panel);
                }
                
                //reverse links
                let sLink_Ids = [];
                for(let k in reverse){
                    if(!(reverse[k]['trmID']>0)){ //links    
                        const sourceID = reverse[k].sourceID;
                        
                        if(!headers[sourceID]){
                            //there is not such record in database
                            continue;                                            
                        }
                        
                        sLink_Ids.push(sourceID);
                        
                        if(sLink_Ids.length<25){
                        
                            let ele = window.hWin.HEURIST4.ui.createRecordLinkInfo(panel, 
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
                    +'" target="_blank">more ('+(sLink_Ids.length-25)
                    +'<span class="ui-icon ui-icon-extlink" style="font-size:0.8em;top:2px;right:2px"></span>'
                    +') </a></div>').appendTo(panel);
                }
                
                
                //insert headers
                if(sRel_Ids.length>0){
                    $('<div class="detailRowHeader" style="border:none">Related</div>').css('border','none').insertBefore(ele1);
                }
                //prevent wrapping
               
                if(sLink_Ids.length>0){
                    let ee2 = $('<div class="detailRowHeader">Linked from</div>').insertBefore(ele2);
                    if(sRel_Ids.length==0){
                        ee2.css('border','none')
                    }
                }
                //prevent wrapping
               
                
                
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
            }    
            case 4:   //scrtachpad
            {
                //find field in HEditing
                let ele = that._editing.getFieldByName('rec_ScratchPad');
                ele.editing_input('option',{showclear_button:false, show_header:false});
                ele[0].parentNode.removeChild(ele[0]);                
                ele.css({'display':'block','width':'99%'});
                ele.find('textarea').attr('rows', 10).css('width','90%');
                ele.show().appendTo(panel);
                
                break;
            }    
            case 1:   //private
            {
                if(panel.text()!='') return;
                
                panel.append('<div class="bookmark" style="min-height:2em;padding:4px 2px 4px 0;vertical-align:top"></div>'
                +'<div class="reminders truncate" style="min-height:2em;padding:4px 30px 4px 0;border-top: 1px lightgray solid;"></div>');
                
                //find bookmarks and reminders
                that._renderSummaryBookmarks(null, panel);
                that._renderSummaryReminders(null, panel);
            
            
                break;
            }
            case 2:   //tags
            {
                if(panel.text()!='') return;
                panel.text('requesting....');
            
                let request = {};
                request['a']          = 'search'; //action
                request['entity']     = 'usrTags';
                request['details']    = 'id';
                request['request_id'] = window.hWin.HEURIST4.util.random();
                request['rtl_RecID']  = this._currentEditID;
                
                let that = this;                                                
                
                //at first we have to search tags that are already assigned to current record
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            panel.empty();
                            let recs = (response.data && response.data.records)?response.data.records:[];
                            window.hWin.HEURIST4.ui.showEntityDialog('usrTags', {
                                    refreshtags:true, 
                                    isdialog: false,
                                    container: panel,
                                    select_mode:'select_multi', 
                                    layout_mode: '<div class="recordList"></div>',
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
            }
            case 5:   //discussion
            {
                if(panel.text()!='') return;
                
                sContent = '<p>Contact Heurist team if you need this function</p>';
                break;
            }
            case 6:   //dates - moved back to admin section (2017-10-31)
            {    
                if(panel.text()!='') return;

                sContent = '<div id="record-history">Click the <a href="#">history button</a> to retrieve this record\'s history</div>';

                $(sContent).appendTo(panel);

                this._on($(sContent).find('a'), {
                    click: function(){
                        this._getRecordHistory();
                    }
                });

                break;
            }
            default:
                sContent = '<p>to be implemented</p>';
        }

        if(idx>1 && idx!=6 && sContent) $(sContent).appendTo(panel);
        if(idx>0 && idx<7){
            panel.css({'margin-left':'27px'});
        }
        
        if(is_guest_user){
            this.element.find('.btns-noguest-only').hide();
        }
        
    },
    
    //
    /**
     * @brief Renders the reminders section in the summary panel.
     * @memberof heurist.manageRecords
     * @param {?HRecordSet} recordset Optional: A recordset of reminders. If null, reminders are fetched.
     * @param {jQuery} panel The parent panel (typically the 'Private' summary section).
     * Displays reminders associated with the current record and provides a button
     * to manage them (opening the `usrReminders` dialog).
     */
    _renderSummaryReminders: function(recordset, panel){
        
            let that = this, sContent = '',
                pnl = panel.find('.reminders');
                
            pnl.empty().css({'font-size': '0.9em'});
        
            if(recordset==null){
                
                let request = {};
                request['rem_RecID']  = this._currentEditID;
                request['a']          = 'search'; //action
                request['entity']     = 'usrReminders';
                request['details']    = 'name';
                request['request_id'] = window.hWin.HEURIST4.util.random();
                                                             
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            let recordset = new HRecordSet(response.data);
                            that._renderSummaryReminders(recordset, panel);
                        }
                    });        
      
                return;
            }
        
            if(recordset.length()==0){
                sContent = '<i>no reminders</i>';
            }else{
                
                let rec = recordset.getFirstRecord();
                let val = recordset.fld(rec, 'rem_ToWorkgroupID');
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
               
            }
            pnl.append(sContent);

            //append/manage button
            $('<div>').button({label:window.hWin.HR('Manage reminders'), showLabel:false,
                icon:'ui-icon-pencil'})  //ui-icon-mail
                .css({position:'absolute',right:'13px', height: '18px'})
                .addClass('non-owner-disable')
                .on('click', function(){
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
    /**
     * @brief Renders the bookmarks section in the summary panel.
     * @memberof heurist.manageRecords
     * @param {?HRecordSet} recordset Optional: A recordset of bookmarks. If null, bookmarks are fetched.
     * @param {jQuery} panel The parent panel (typically the 'Private' summary section).
     * Displays bookmark information (rating, password reminder, notes) for the current
     * record and provides a button to manage them (opening the `usrBookmarks` dialog).
     */
    _renderSummaryBookmarks: function(recordset, panel){

            let that = this, sContent = '',
                pnl = panel.find('.bookmark');
                
            pnl.empty().css({'font-size': '0.9em'});

            //append/manage button
            $('<div>').button({label:window.hWin.HR('Manage bookmark info'), showLabel:false,
                icon:'ui-icon-pencil'})  //ui-icon-bookmark
                .addClass('non-owner-disable')
                .css({float: 'right', height: '18px'}) //position:'absolute',right:'13px',
                .on('click', function(){
                    
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
                
                let request = {};
                request['bkm_RecID']  = this._currentEditID;
                request['a']          = 'search'; //action
                request['entity']     = 'usrBookmarks';
                request['details']    = 'name';
                request['request_id'] = window.hWin.HEURIST4.util.random();
                                                             
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            let recordset = new HRecordSet(response.data);
                            that._renderSummaryBookmarks(recordset, panel);
                        }
                    });        
                return;
            }
        
            //rating and password reminder
            if(recordset.length()==0){
                sContent = '<i>not bookmarked (no passwords, rating or notes)</i>';
            }else{
                let rec = recordset.getFirstRecord();
                let val = recordset.fld(rec, 'bkm_Rating');
                sContent = 'Rating: '+((val>0) ?'*'.repeat(val) :''); 
                val = window.hWin.HEURIST4.util.htmlEscape(recordset.fld(rec, 'bkm_PwdReminder'));
                sContent += '<br>&nbsp;&nbsp;&nbsp;Pwd: '+((!window.hWin.HEURIST4.util.isempty(val))?val:''); 
                val = window.hWin.HEURIST4.util.htmlEscape(recordset.fld(rec, 'bkm_Notes'));
                sContent += '<br>&nbsp;Notes: '+((!window.hWin.HEURIST4.util.isempty(val))
                        ?(val.slice(0,500)+(val.length>500?'...':'')):''); 
                
            }
            pnl.append(sContent);
            
    },
    //
    /**
     * @brief Renders the tags section in the summary panel.
     * @deprecated This method is marked as "NOT USED anymore TO REMOVE".
     * @memberof heurist.manageRecords
     * @param {HRecordSet} recordset A recordset of tags.
     * @param {jQuery} panel The panel to render tags into.
     * Displays tags grouped by user/group and provides a button to manage them.
     */
    _renderSummaryTags: function(recordset, panel){
        
            let that = this, idx, isnone=true;
            
            panel.empty().css({'font-size': '0.9em'});
            
            $('<div><i style="display:inline-block;">Personal:&nbsp;</i></div>')
                .css({'padding':'2px 4px 16px 4px'})
                .attr('data-id', window.hWin.HAPI4.currentUser['ugr_ID'])
                .hide().appendTo(panel);
            
            //render group divs
            for (let groupID in window.hWin.HAPI4.currentUser.ugr_Groups)
            if(groupID>0){
                let name = window.hWin.HAPI4.sysinfo.db_usergroups[groupID];
                if(!window.hWin.HEURIST4.util.isnull(name)){
                        $('<div><i style="display:inline-block;">'+name+':&nbsp;</i></div>')
                            .css({'padding':'0 2 4 2px'})
                            .attr('data-id', groupID).hide().appendTo(panel);
                }
            }
            
            let records = recordset.getRecords();
            let order = recordset.getOrder();
            let recID, label, groupid, record, grp;
            
            for (idx=0;idx<order.length;idx++){
                recID = order[idx];
                if(recID && records[recID]){
                    record = records[recID];
                    label = recordset.fld(record,'tag_Text');
                    groupid = recordset.fld(record,'tag_UGrpID');
                 
                    grp = panel.find('div[data-id='+groupid+']').show();
                    $('<a href="'
                         + window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&q=tag:'+label
                         + '" target="_blank" style="display:inline-block; padding-right:4px">'+label+'</a>')
                         .appendTo(grp);
                   
                   isnone = false;  
                }
            }
            
            
            if(isnone){
                $('<div class="detailRowHeader">none</div>').appendTo(panel);
            }
            
            //append manage button
            $('<div>').button({label:window.hWin.HR('Manage record tags'), showLabel:false,
                icon:'ui-icon-tag'})
                .addClass('ui-heurist-btn-header1')
                .css({float:'right', height: '18px'})
                .on('click', function(){

                    window.hWin.HEURIST4.ui.showEntityDialog('usrTags', {
                        width: 800,
                        height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95,
                        select_mode:'select_multi', 
                        selection_ids:order,
                        select_return_mode:'recordset', //ids by default
                        onselect:function(event, data){
                            if(data && data.selection){
                                //assign new set of tags to record
                                
                                let request = {};
                                request['a']       = 'batch'; //batch action
                                request['entity']  = 'usrTags';
                                request['mode']    = 'replace';
                                request['tagIDs']  = data.selection.getOrder();
                                request['recIDs']  = that._currentEditID;
                                request['request_id'] = window.hWin.HEURIST4.util.random();
                                
                                window.hWin.HAPI4.EntityMgr.doRequest(request, null);
                                //update panel
                                
                            }
                        }
                    });
                })
             .appendTo(panel);        
                             
    },


    //
    /**
     * @brief Opens the record type structure editor in a new browser tab.
     * @deprecated This method is marked as "NOT USED".
     * @memberof heurist.manageRecords
     * Warns the user about needing to reload the page to see changes and prompts
     * to save the current record if modified.
     */
    editRecordTypeOnNewTab: function(){

        let that = this;
        
        let smsg = "<p>Changes made to the record type will not become active until you reload this page (hit page reload in your browser).</p>";
        
        if(this._editing.isModified()){
            smsg = smsg + "<br>Please SAVE the record first in order not to lose data";
        }
        window.hWin.HEURIST4.msg.showMsgDlg(smsg);

        let url = window.hWin.HAPI4.baseURL + 'admin/adminMenuStandalone.php?db='
            +window.hWin.HAPI4.database
            +'&mode=rectype&rtID='+that._currentEditRecTypeID;
        window.open(url, '_blank');
    },

    //
    /**
     * @brief Opens the dialog for editing the record type's title mask.
     * @memberof heurist.manageRecords
     * It uses `HEURIST4.ui.showRecordActionDialog` to display the 'rectypeTitleMask' dialog.
     */
    editRecordTypeTitle: function(){
        
        let that = this;
        let rty_ID = this._currentEditRecTypeID;
        let maskvalue = $Db.rty(rty_ID, 'rty_TitleMask')

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
    /**
     * @brief Opens the dialog for editing the attributes of the current record type.
     * @memberof heurist.manageRecords
     * If the current record has unsaved changes, it prompts the user to save them first.
     * Then, it displays the `defRecTypes` entity dialog for the current record type.
     * After the dialog closes, it re-initializes the edit form to reflect any changes.
     */
    editRecordTypeAttributes: function(){
        
        let that = this;
        let rty_ID = this._currentEditRecTypeID;
        
        if(this._editing.isModified()){  //2020-12-06 !this.options.edit_structure && 
            
                let sMsg = "Click YES to save changes and modify the record type attributes";
                window.hWin.HEURIST4.msg.showMsgDlg(sMsg, function(){
                    
                        that.saveQuickWithoutValidation( function(){ //save without validation
                            that._editing.initEditForm(null, null); //clear edit form
                            that._initEditForm_step3(that._currentEditID); //reload edit form                       
                            that.editRecordTypeAttributes();
                        } );

                }, {title:'Data has been modified', yes:window.hWin.HR('Yes') ,no:window.hWin.HR('Cancel')});   
                return;                           
        }

        
        let popup_options = {
                select_mode: 'manager',
                edit_mode: 'editonly', //only edit form is visible, list is hidden
                rec_ID: rty_ID,
                selectOnSave: true,
                suppress_edit_structure: true,
                height: 820,
                onClose: function(){
                    //refresh icon, title, mask
                    that._icon_timer_suffix = ('&t='+window.hWin.HEURIST4.util.random());
                    
                    that._initEditForm_step3(that._currentEditID);
                }
            };
        window.hWin.HEURIST4.ui.showEntityDialog('defRecTypes', popup_options);
        
    },
    
    //
    /**
     * @brief Closes the west panel of the layout (typically the structure editor).
     * @memberof heurist.manageRecords
     */
    closeWestPanel: function(){
        this.editFormPopup.layout().close("west");  
    },
    
    /**
     * @brief Initiates editing of the record type structure, either inline or by switching view.
     * @memberof heurist.manageRecords
     * @param {boolean} [is_inline] If true, attempts to enable inline structure editing.
     * If the current record has unsaved data changes and `options.edit_structure` is false,
     * it prompts the user to save or drop changes.
     * If `is_inline` is true, it reloads the RTS editor and shows the west layout panel.
     */
    editRecordType: function(is_inline){

        let that = this;
        
        if(!this.options.edit_structure && this._editing.isModified()){

            let $dlg = null;

            let sMsg = "Click Save changes to save changes and modify the record structure.<br>"
                        +"Or click Drop changes to modify the record structure without saving edits.<br>";

            let btns = {};
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
            let isClosed = (!this.options.edit_structure && !this.options.rts_editor);
            this.editFormPopup.layout().show('west', !isClosed ); 
            if(isClosed){
                let tog = that.editFormPopup.find('.ui-layout-toggler-west');
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
    /**
     * @brief Third step in initializing the edit form: loads record data or prepares for new record.
     * @override
     * @memberof heurist.manageRecords
     * @param {number} recID The ID of the record, or negative for a new record.
     * @param {boolean} [is_insert] True if this is a new record insertion.
     * Clears summary panel content. If `recID` is positive, fetches full record data.
     * If `recID` is negative, prepares for a new record, potentially prompting for
     * record type selection or handling predefined `new_record_params`. It also
     * handles special logic for child records and relationship records.
     */
    _initEditForm_step3: function(recID, is_insert){
        
        //fill with values
        this._currentEditID = recID;
        this._check_history = true; // reset history check
        this._source_db = { id: 0, url: '' };
        this._source_def = null;

        let that = this;
        
        //clear content of accordion
        if(this.editFormSummary && this.editFormSummary.length>0){
            this.editFormSummary.find('.summary-content').empty();
           
        }

        if(recID==null){
            this._editing.initEditForm(null, null); //clear and hide
        }else if(recID>0){ //edit existing record  - load complete information - full file info, relations, permissions
        
            window.hWin.HAPI4.RecordMgr.search({q: 'ids:'+recID, w: "e", f:"complete", l:1, checkFields: 1}, 
                            function(response){ response.is_insert = (is_insert==true); 
                                                that._initEditForm_step4(response); });

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
            
            let is_thumbnail_generation = false;    
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

                    let params = window.hWin.HAPI4.get_prefs_def('prefs_'+that._entityName, that.defaultPrefs);

                    if(params['show_warn_about_relationship']!==false){


                        let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
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
                                    window.hWin.HAPI4.actionHandler.executeActionById('menu-structure-rectypes');
                                }},
                                {text:'Create relationship record', click: function(){ 
                                    $dlg.dialog( "close" );                    
                                   
                                }},
                                {text:'Cancel', click: function(){ $dlg.dialog( "close" ); that.closeEditDialog(); }}

                            ],{  title:'Creation of relationship record' }        
                        );

                        $dlg.find('input[type="checkbox"]').on('change', function(){
                            let params = window.hWin.HAPI4.get_prefs_def('prefs_'+that._entityName, that.defaultPrefs);
                            params['show_warn_about_relationship'] = false;
                            window.hWin.HAPI4.save_pref('prefs_'+that._entityName, params);     

                        });

                    }                            

                }
                
                
                
                if(that.options.new_record_params['OwnerUGrpID']=='current_user') {
                    that.options.new_record_params.OwnerUGrpID = window.hWin.HAPI4.user_id();
                }

                if(that.options.new_record_params['details']){                     
                    //need to use save because method "add" inserts only header
                    
                    let msg = null;
                    if(is_thumbnail_generation){
                        msg = window.hWin.HR('generating thumbnail');
                    }
                    let dlged = that._getEditDialog();
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
                let add_rec_prefs = window.hWin.HAPI4.get_prefs('record-add-defaults');
                let usr_id = window.hWin.HAPI4.user_id();
                if(!Array.isArray(add_rec_prefs) || add_rec_prefs.length<4){
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
                    if(window.hWin.HAPI4.is_guest_user()){
                        //guest user can add new record to arbitrary group '+that.options.new_record_params.OwnerUGrpID
                    } else{
                        //specified ownership is not applicabel for current user - set to current user
                        that.options.new_record_params.OwnerUGrpID = usr_id;    
                    }
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
    /**
     * @brief Finds parent record types for a given child record type.
     * @memberof heurist.manageRecords
     * @param {number} childRecordType The ID of the child record type.
     * @returns {number[]|undefined} An array of parent record type IDs, or undefined if none.
     * Uses `$Db.rst_links().parents` to determine which record types can be parents
     * of the specified `childRecordType`.
     * @todo Consider moving to a more global database utility service/object.
     */
    __findParentRecordTypes: function(childRecordType){

        let parentRecordTypes = $Db.rst_links().parents[childRecordType];
        
        return parentRecordTypes;
    },
    
    //
    /**
     * @brief Creates a "fake" record type structure field object for a given DetailTypeID.
     * @memberof heurist.manageRecords
     * @param {number} detailTypeID The DetailTypeID for which to create the fake field structure.
     * @param {number|string} [order] The display order for this fake field.
     * @returns {object} An object mimicking a record type structure field definition.
     * This is used to represent fields that are present in a record's data but not
     * formally part of its defined record type structure (non-standard fields).
     * It populates the fake field with default properties based on the global DetailType definition.
     */
    _getFakeRectypeField: function(detailTypeID, order){
        
        let dt = $Db.dty(detailTypeID);
        
        //init array 
        let ffr = {};
            
        ffr['rst_DisplayName'] = dt?dt['dty_Name']:'Placeholder';
        ffr['dty_FieldSetRectypeID'] = dt?dt['dty_FieldSetRectypeID'] : 0;
        ffr['rst_FilteredJsonTermIDTree'] = (dt?dt['dty_JsonTermIDTree']:"");
       
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
    /**
     * @brief Prepares a field's structural definition for use in the record editor.
     * @memberof heurist.manageRecords
     * @param {?object} rfr The initial record field structure object, if available from `$Db.rst()`.
     * @param {number} rty_ID The Record Type ID.
     * @param {number} dty_ID The DetailTypeID (field ID).
     * @returns {object} A consolidated field definition object suitable for the editor.
     * This method takes a base field definition (rfr) or fetches it using rty_ID and dty_ID.
     * It then merges it with global DetailType properties (`$Db.dty()`) to ensure all necessary
     * attributes (like `dty_Type`, `rst_PtrFilteredIDs`, `rst_FilteredJsonTermIDTree`, `rst_FieldConfig`)
     * are present for the editing input widgets.
     */
    _prepareFieldForEditor: function (rfr, rty_ID, dty_ID){                    

            if(!rfr){
                rfr = $Db.rst(rty_ID, dty_ID);
            }else{
                dty_ID = rfr['dt_ID'];
            }
        
            let ffr = window.hWin.HEURIST4.util.cloneJSON(rfr);

            let dt = $Db.dty(dty_ID);

           
            
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
            }
            
            return ffr;         
    },
    
    //
    /**
     * @brief Fourth and final step of edit form initialization: prepares field structures and initializes the HEditing widget.
     * @override
     * @memberof heurist.manageRecords
     * @param {?object} response The server response object containing record data (if editing an existing record) or new record template.
     * This is a critical method that:
     * 1. Sets the `_currentEditRecordset` and `_isInsert` flag.
     * 2. Determines the `_currentEditRecTypeID`.
     * 3. Gathers all fields for the form: standard fields from `$Db.rst()`, non-standard fields present in the data, and special fields like "Parent Entity" or "Workflow Stage".
     * 4. Handles constraints for relationship records (e.g., filtering target resources and relation types).
     * 5. Sorts fields by display order.
     * 6. Organizes fields into groups (tabs, accordions) based on separator fields.
     * 7. Initializes the `HEditing` widget with the prepared field structure and record data.
     * 8. Calls `_afterInitEditForm` for post-initialization UI adjustments.
     * 9. Manages UI states like scroll position, active tabs, and warnings for new relationship records or missing title masks.
     * 10. Sets up display for "view-only" mode if user lacks edit permissions.
     */
    _initEditForm_step4: function(response){
        
        let that = this;
        
        if(response==null || response.status == window.hWin.ResponseStatus.OK){
            
            //response==null means reload/refresh edit form
            let allowCreateIndependentChildRecord = false;
            
            if(response){ // && response.length()>0
                that._currentEditRecordset = new HRecordSet(response.data);
                if(that._currentEditRecordset.length()==0){
                    let sMsg = 'Record does not exist in database or has status "hidden" for non owners';
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
            
            let rectypeID = that._getField('rec_RecTypeID');
			
            let activeTabs = [];

            if(this._currentEditRecTypeID == rectypeID){ // check that the previous record and current record are the same type
                let $tab_groups = this.editForm.find('.ui-tabs');

                if($tab_groups.length > 0){ // retain active tab between same record types

                    $.each($tab_groups, function(idx, tab){

                        let $tab_instance = $(tab).tabs('instance');
                        if($tab_instance != undefined){ //
                            activeTabs.push($tab_instance.options.active); // $tab.tabs('option', 'active'); keeps returning tabs object
                        }
                    });
                }
            }

            // check if initial data can be filled in
            let add_fill_data = this._currentEditID == -1 && !window.hWin.HEURIST4.util.isempty(this.options.fill_in_data);

            //pass structure and record details
            that._currentEditID = that._getField('rec_ID');
            that._currentEditRecTypeID = rectypeID;

            //find all parent rectypes
            let parentRtys = this.__findParentRecordTypes(rectypeID);
            
            if(that._isInsert && (!allowCreateIndependentChildRecord) &&!(that.options.parententity>0)){
                //special verification - prevent unparented records
                //IMHO it should be optional
                // 1. if rectype is set as target for one of Parent/child pointer fields 
                // 2 and options.parententity show warning and prevent addition
                if(parentRtys && parentRtys.length>0){
                    
                    let names = [];
                    $(parentRtys).each(function(i,id){
                        names.push($Db.rty(id, 'rty_Name'));
                    });
                    
                    
                    let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
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
 
            let popup_options = {
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
                     
                          let btn = $dlg.parent().find('button:contains("Create independent record")');
                          let chb = $dlg.find('input[type="checkbox"]').on('change', function(){
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
            const RT_RELATION = window.hWin.HAPI4.sysinfo['dbconst']['RT_RELATION'];
            let DT_RELATION_TYPE, DT_RESOURCE;
            if(rectypeID == RT_RELATION && that.options.relmarker_field>0){
                DT_RELATION_TYPE = window.hWin.HAPI4.sysinfo['dbconst']['DT_RELATION_TYPE'];
                
                DT_RESOURCE = window.hWin.HAPI4.sysinfo['dbconst'][that.options.relmarker_is_inward?'DT_PRIMARY_RESOURCE':'DT_TARGET_RESOURCE'];
            }
       
            //prepare db structure from $Db.rst for editing
            let fields = window.hWin.HEURIST4.util.cloneJSON(that.options.entity.fields); //retuns record header field rec_XXXX

            /*
            function __findFieldIdxById(id){
                for(let k in fields){
                    if(fields[k]['dtID']==id){
                        return k;
                    }
                }
                return -1;
            }
            //hide url field
            let fi_url = rectypes.typedefs.commonNamesToIndex['rty_ShowURLOnEditForm'];
            if(rectypes.typedefs[rectypeID].commonFields[fi_url]=='0'){
                fields[__findFieldIdxById('rec_URL')]['rst_Visible'] = false;
            }
            */
            
            // fields - json for editing that describes edit form
            // fields_ids - fields in rt structure (standard fields)
            // s_fields - sorted 
            // field_in_recset - all fields in record 

            let rst_details =  $Db.rst(rectypeID);  //array of dty_ID:rst_ID
            let s_fields = [];  //sorted fields including hidden fields from record header 
            let fields_ids = []; //fields in structure
            
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
            let field_in_recset = that._currentEditRecordset.getDetailsFieldTypes();

            //add special 2-247 field "Parent Entity"
            //verify that current record type is a child for pointer fields with rst_CreateChildIfRecPtr=1
            
            const DT_WORKFLOW_STAGE = Number(window.hWin.HAPI4.sysinfo['dbconst']['DT_WORKFLOW_STAGE']);
            const DT_PARENT_ENTITY  = Number(window.hWin.HAPI4.sysinfo['dbconst']['DT_PARENT_ENTITY']);
            if( window.hWin.HEURIST4.util.findArrayIndex(DT_PARENT_ENTITY, field_in_recset)<0 && 
                    this.options.parententity>0)    //parent record id is set already (case: this is addition of new child from search record dialog)
                    //|| (parentRtys.length>0 && that._isInsert) ))   //current rectype is referenced as a child and this is ADDITION
            {
                    field_in_recset.push(DT_PARENT_ENTITY);
            }
            if(window.hWin.HEURIST4.util.findArrayIndex(DT_WORKFLOW_STAGE, field_in_recset)<0 &&
                $Db.getSwfByRectype(this._currentEditRecTypeID).length > 0){

                field_in_recset.push(DT_WORKFLOW_STAGE);
            }
            
            //Disabled 2018-05-17
            //reasons:
            //they are extremely confusing for the uninitiated (and even for those in the know); 
            //you can't control them easily b/c they are set in another record type; 
          
            // 1) Add fields that are in record set (field_in_recset) 
            //    but not in structure (fields_ids) - NON STANDARD FIELDS
            // 2) Disable (readonly) for DT_WORKFLOW_STAGE 2-1080 workflow stage field

            let addhead = 0;
            for(let k=0; k<field_in_recset.length; k++){
                //field in recset is not in structure
                if( window.hWin.HEURIST4.util.findArrayIndex(field_in_recset[k],fields_ids)<0)
                { 
                    let record = that._currentEditRecordset.getById(that._currentEditID);
                    if(field_in_recset[k]==DT_PARENT_ENTITY){

                        let rfr = that._getFakeRectypeField(DT_PARENT_ENTITY);
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
                                let values = that._currentEditRecordset.values(record, DT_PARENT_ENTITY);
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
                                let rfr = that._getFakeRectypeField(9999999);
                                rfr['rst_DisplayName'] = 'Non-standard fields for this record type';
                                rfr['dty_Type'] = 'separator';
                                rfr['rst_DisplayOrder'] = 1100;
                                rfr['rst_DefaultValue'] = 'group_break';
                                s_fields.push(rfr);
                            }
                            addhead++;
                        
                        }
                        
                        let rfr = that._getFakeRectypeField(field_in_recset[k], 1100+addhead);
                        
                        if(field_in_recset[k]==DT_WORKFLOW_STAGE){
                            rfr['rst_Display'] = 'readonly';
                            rfr['rst_DisplayOrder'] = 0; // will be moved to bottom of record editor

                            if(that._currentEditRecordset.values(record, DT_WORKFLOW_STAGE) == null){
                                let first_stage = [ '' ];
                                that._currentEditRecordset.setFld(record, DT_WORKFLOW_STAGE, first_stage);
                            }
                        }
                        
                        s_fields.push(rfr);
                    }
                    
                }
            }//for   
            

            //create UI from rfr 
            
            //sort by order
            s_fields.sort(function(a,b){ return a['rst_DisplayOrder']<b['rst_DisplayOrder']?-1:1});

            let group_fields = null;
            let hasField = false; // check for any fields
            let temp_group_details = [], hasTabs = false; // check if any groupings are set to tabs
            let max_length_fields = []; // freetext, blobktext, and float fields that are set to max width
            let terms_as_buttons = []; // enum fields, for adjusting each button+label's width
            let check_for_errors = []; // fields that could have additional errors (e.g. date fields that haven't been indexed into recDetailsDateIndex) 
            let available_groups = ['group', 'group_break', 'tabs', 'tabs_new', 'accordion', 'accordion_inner', 'expanded', 'expanded_inner', 'explanation', 'explanation_break'];

            let has_rec_access = window.hWin.HAPI4.has_access(this._getField('rec_OwnerUGrpID'));
            let cur_record = that._currentEditRecordset.getFirstRecord();
            let rty_ConceptCode = $Db.getConceptID('rty', this._currentEditRecTypeID);

            let $temp = $('<div>').appendTo(this.editForm);
            $temp.css({
                'display': 'inline-block',
                'width': '1ch',
                'visibility': 'hidden'
            });
            let px_width = $temp.width();
            let px_max = px_width * 50 + 50;// Max of 50 characters
            let new_struct_width = this.usrPreferences.structure_width;
            let char_count = 0;
            $temp.remove();

            for(let k=0; k<s_fields.length; k++){

                let dtFields = that._prepareFieldForEditor( s_fields[k] );

                if(dtFields['dty_Type']=='separator'){

                    if(group_fields!=null){
                        fields[fields.length-1].children = group_fields;
                    }

                    let dtGroup = {
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

                    if(dtFields['dty_Type'] == 'date'){
                        check_for_errors.push(dtFields['dt_ID']);
                    }
                }

                if(new_struct_width < px_max && char_count < dtFields['rst_DisplayName'].length){
                    new_struct_width = (dtFields['rst_DisplayName'].length > 50 ? 50 : dtFields['rst_DisplayName'].length) * px_width + 50;
                    char_count = dtFields['rst_DisplayName'].length;
                }
            }//for s_fields

            //adjust west panel size
            if(this.usrPreferences.structure_width <= new_struct_width && Object.hasOwn(this.editFormPopup.layout(), 'west')){
                this.editFormPopup.layout().sizePane('west', new_struct_width);
                this.usrPreferences.structure_width = new_struct_width;
            }

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

                let $tab_groups = this.editForm.find('.ui-tabs');

                if($tab_groups.length > 0){ // retain active tab between same record types

                    $.each($tab_groups, function(idx, tab){

                        let $tab = $(tab);
                        if($tab.tabs('instance') != undefined){ // set active tabs
                            $tab.tabs('option', 'active', activeTabs[idx]);
                        }
                    });
                }
            }
            
            let header_to_tabs_ignore = sessionStorage.getItem('header_to_tabs_ignore');

            header_to_tabs_ignore = (header_to_tabs_ignore != null) ? JSON.parse(header_to_tabs_ignore) : {};

            // Check if there are headers, and whether none of them are tabs - session only
            if(window.hWin.HAPI4.has_access(1) && !hasTabs && temp_group_details && Object.keys(temp_group_details).length > 2
                && this.options.edit_structure == undefined && this.options.rts_editor == undefined){

                if(header_to_tabs_ignore[window.hWin.HAPI4.database] == null 
                    || !header_to_tabs_ignore[window.hWin.HAPI4.database].includes(that._currentEditRecTypeID)){

                    let $dlg;

                    let btns = {};
                    btns[window.hWin.HR('Convert to tabs')] = function(){
                        $dlg.dialog('close');

                        //Convert all headers to tabs
                        let request = {
                            'a': 'save',
                            'entity': 'defRecStructure',
                            'request_id': window.hWin.HEURIST4.util.random(),
                            'fields': temp_group_details
                        };

                        // Update Database Definition
                        window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){

                            if(response.status == window.hWin.ResponseStatus.OK){

                                for(let i = 0; i < temp_group_details.length; i++){ // Update Cache
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

                let idx = header_to_tabs_ignore[window.hWin.HAPI4.database].indexOf(that._currentEditID);
                if(idx > -1){
                    header_to_tabs_ignore[window.hWin.HAPI4.database].splice(idx, 1);
                }
            }

            //show rec_URL 
            let ele = that._editing.getFieldByName('rec_URL');
            let hasURLfield = ($Db.rty(rectypeID, 'rty_ShowURLOnEditForm')=='1');
            if(hasURLfield && ele?.length == 1){
                ele.show();

                // special case  - show separator between parent record field and other fields
                // in case there is no header
                let first_set = that.editForm.find('fieldset:first');
                first_set.show();
                let next_ele = first_set.next().next();
                if(!next_ele.hasClass('separator')){
                    first_set.css('border-bottom','1px solid #A4B4CB');
                }
            }

            // display record title field, move child record field (if present) to the top of form and move non-standard workflow stage field to end of popup
            this.showExtraRecordInfo();
            
            // Add a divider between the popup controls and the first set of input, 
            // if the first set is not contained within a group and there are groups below these loose inputs
            let first_child = this.editForm.children('fieldset#receditor-top');
            let last_fieldset = this.editForm.children('fieldset#receditor-bottom');
            if (this.editForm[0].childNodes.length > 2 && first_child.length > 0 && first_child.children("div:visible").length > 0){
                first_child.css('border-top', '1px solid #A4B4CB');
            }
            if (last_fieldset.length > 0 && last_fieldset.children("div:visible").length > 0){
                last_fieldset.css('border-bottom', '1px solid #A4B4CB');
            }
            
            //special case for bookmarklet addition - some values are already assigned 
            if(that._isInsert){
                let vals = ele.editing_input('getValues');
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
                        let active = $(item).accordion('option','active');
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
            //let no_access = that._getField('rec_OwnerUGrpID')!=0 &&  //0 is everyone
            let no_access = !(window.hWin.HAPI4.is_admin() || window.hWin.HAPI4.is_member(that._getField('rec_OwnerUGrpID')));
                            //!window.hWin.HAPI4.is_admin()
            if(window.hWin.HAPI4.is_guest_user()){
                no_access = !that._isInsert && 
                            that._getField('rec_OwnerUGrpID') != window.hWin.HAPI4.user_id();
            }
           
                        
                            
            let exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);
            
            //2. Popup for resource (record pointer) field
            let dlged = that._getEditDialog();
            if(dlged && (no_access || (this.options.edit_obstacle && exp_level!=0 ) )){ 
                
                let ele = $('<div><div class="edit-button" style="background:#f48642 !important;margin: 40px auto;width:200px;padding:10px;border-radius:4px;">'
                            +'<h2 style="display:inline-block;color:white">View-only mode</h2>&nbsp;&nbsp;'
                            +'<a href="#" class="btns-noguest-only" style="color:white">edit</a><span><br>click to dismiss</span></div></div>')
                       .addClass('coverall-div-bare')
                       .css({top:'30px', 'text-align':'center','zIndex':9999999999, height:'auto'}) //, bottom: '40px', 'background':'red'
                       .appendTo(dlged);
                
                let eles = dlged.find('.ui-layout-center');
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
                    ele.find('.edit-button').button().on('click', function(){
                        ele.remove();
                    });
                }else{       
                    //find('a')
                    ele.find('.edit-button').button().on('click', function(){
                        ele.remove();
                        //restore edit ability 
                        that._editing.setDisabled(false);
                        dlged.find('.ui-layout-center > div').css({'background':'none'});
                        dlged.find('.ui-layout-center').css({'background':'none'});
                        let eles = dlged.find('.ui-layout-east > .editFormSummary')
                        if(eles.length>0){
                            eles.css({'background':'none'});   
                            eles.find('.coverall-div-bare').remove();
                        }
                        //remove screen
                        
                    });
                    ele.find('span').hide(); //have no enough rights
                }
                this.options.edit_obstacle = false;
            } 

            // Add tab paging icons and activation handling
            let eles = this.editForm.find('.ui-tabs');
            for (let i=0; i<eles.length; i++){
                $(eles[i]).attr('data-id','idx'+i).tabs('paging',{
                    nextButton: '<span style="font-size:2em;font-weight:900;line-height:5px;vertical-align: middle">&#187;</span>', // Text displayed for next button.
                    prevButton: '<span style="font-size:2em;font-weight:900;line-height:5px;vertical-align: middle">&#171;</span>' // Text displayed for previous button.
                });

                // onActivate handle
                $(eles[i]).on('tabsactivate', function(event, ui){
                    if(ui.newPanel && ui.newPanel.find('.enum_input:visible').length > 0){ // fix terms as button widths

                        let $input_divs = ui.newPanel.find('.enum_input:visible').parent();

                        $.each($input_divs, function(i, input_div){

                            let $inputdiv = $(input_div);
                            let $input = $inputdiv.find('.enum_input');

                            if($input.first().height()*2 < $inputdiv.height()){
                                $input.css('min-width', '120px');
                            }else{
                                $input.css('min-width', '');
                            }
                        });
                    }else if(ui.newPanel && 
                            !window.hWin.HEURIST4.util.isnull( that.options.rts_editor ) && 
                            ui.newPanel.find('div[data-dtid]:first').length > 0)
                    {
                        let dty_id = ui.newPanel.find('div[data-dtid]:first').attr('data-dtid');
                        that.options.rts_editor.manageDefRecStructure('highlightNode', dty_id);
                    }
                });
            }

            if(window.hWin.HAPI4.is_admin() && this.options.allowAdminToolbar!==false){

                if(!hasField){ // open modify structure, if able when there are no fields
                    this.editRecordType(true);
                }else if(window.hWin.HEURIST4.util.isnull(this.options.edit_structure) && 
                         window.hWin.HEURIST4.util.isnull(this.options.rts_editor))
                { // check for default title mask

                    let title_mask = $Db.rty(that._currentEditRecTypeID, 'rty_TitleMask');
                    let match_result = title_mask?title_mask.match(/\[([^\]]+)\]/g):null; // check for fields in title mask

                    if(title_mask == 'record [ID]' || !match_result){

                        let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
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
                            +'We recommend you read the <a href="'
                                +window.hWin.HAPI4.sysinfo.referenceServerURL
                                +'?db='+window.hWin.HAPI4.sysinfo.referenceServerHelpDatabase
                                +'&website=39&pageid=773" target="_blank">help for Constructed titles</a>', 
                            { 'Proceed': function(){ that.editRecordTypeTitle(); $dlg.dialog('close'); } },
                            {title:'Constructed title not yet configured', yes:'Proceed'},
                            {default_palette_class: 'ui-heurist-design'});

                        return;
                    }
                }
            }

            if(max_length_fields.length > 0){ // Set the selected freetext and memo/blocktext fields to 'max' width

                let popup_maxw = this.editForm.width() * ((this.options.rts_editor) ? 0.7 : 0.75) - 40;

                for(let i = 0; i < max_length_fields.length; i++){

                    let width = popup_maxw;
                    let field = this._editing.getFieldByName(max_length_fields[i]);

                    field.find('input, textarea').css({'width': width, 'max-width': width});
                }
            }
            if(terms_as_buttons && terms_as_buttons.length > 0){ // Set terms as button fields, if more than one line, set width 

                for(let j = 0; j < terms_as_buttons.length; j++){

                    let field = this._editing.getFieldByName(terms_as_buttons[j]);

                    if(field.is(':visible')){

                        let $inputdiv = field.find('.input-div');
                        let $input = $inputdiv.find('.enum_input');

                        if($input.first().height()*2 < $inputdiv.height()){
                            $input.css('min-width', '120px');
                        }
                    }
                }
            }
            if(check_for_errors.length > 0){ // Add extra error details about field values
                that._editing.displayValueErrors(check_for_errors);
            }
            if(this.editForm.find('.showAllImages').length > 0){

                let showAllImagesPrefs = window.hWin.HAPI4.get_prefs_def('edit_record_showAllRecords', []);
                showAllImagesPrefs = window.hWin.HEURIST4.util.isJSON(showAllImagesPrefs);

                if(Array.isArray(showAllImagesPrefs)){

                    for(const keyValue of showAllImagesPrefs){

                        const dtyID = keyValue.split('.')[1];
                        if(!window.hWin.HEURIST4.util.isPositiveInt(dtyID) || keyValue.indexOf(`${this._currentEditRecTypeID}.`) < 0){
                            continue;
                        }

                        this.editForm.find(`div[data-dtid="${dtyID}"] div.image_input`).trigger('click').show();
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
    /**
     * @brief Saves the record and then performs a follow-up action.
     * @override
     * @memberof heurist.manageRecords
     * @param {?object} fields Field values to save. If null, values are taken from the form.
     * @param {string|function} afterAction Action to perform after saving: 'none', 'close', 'newrecord', 'close_rst', or a callback function.
     * This method handles the entire save process:
     * 1. Prevents concurrent save attempts.
     * 2. If `fields` is not provided, it calls `_getValidatedValues()` to retrieve and validate form data.
     * 3. Performs various checks: data presence, script tag injection, field value size limits.
     * 4. Handles ambiguous date conversions and entry mask validations.
     * 5. Manages workflow stage selection if applicable.
     * 6. Updates tags if `_updated_tags_selection` is set.
     * 7. Constructs the save request object, including record ID, type, header fields (URL, owner, visibility), detail fields, and field visibility settings.
     * 8. Calls `window.hWin.HAPI4.RecordMgr.saveRecord` to send the data to the server.
     * 9. On successful save, updates internal state, shows a success message, and calls `_afterSaveHandler` to execute the `afterAction`.
     * 10. On failure, re-enables save buttons and shows an error message.
     */
    _saveEditAndClose: function( fields, afterAction ){

        if(!(this._currentEditID>0)) return;

        if(window.hWin.HAPI4.is_callserver_in_progress()) {
            //prevent repeatative call
            return;   
        }

        if(this.options.rts_editor && this.options.rts_editor.manageDefRecStructure('checkIfEditing')){
            window.hWin.HEURIST4.msg.showMsgFlash('You must save or discard your strucutral changes before saving the record', 2000);
            return;
        }

        let that = this;                                    
    
        if(!fields){
            try{
                fields = this._getValidatedValues(); 
            }catch{
                fields = null;
            }

            let hasCustomJsOrCss = false, hasScriptTag = false;
            
            let hasValue = false, hasDtlField = false;
            let ambig_dates = [], invalid_entries = {};

            let rty_ConceptCode = $Db.getConceptID('rty', this._currentEditRecTypeID);
            //verify max lengtn in 64kB per value
            for (let dtyID in fields){

                let updated_values = false;
                if(parseInt(dtyID)>0){
                    
                    let dty_ConceptCode = $Db.getConceptID('dty', dtyID);
                    let dt = $Db.dty(dtyID, 'dty_Type');
                    hasDtlField = true;
                    if(dt=='geo' || dt=='file') continue;
                    
                    let values = fields[dtyID];

                    if(window.hWin.HEURIST4.util.isempty(values)) continue;

                    hasValue = true;
                    if(!Array.isArray(values)) values = [values];

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

                    for (let k=0; k<values.length; k++){
                        
                        let len = window.hWin.HEURIST4.util.byteLength(values[k]);
                        let len2 = values[k].length;
                        let lim = (len-len2<200)?64000:32768;
                        
                        if(len>lim){ //65535){  32768
                            let sMsg = `The data in field ${$Db.rst(that._currentEditRecTypeID, dtyID, 'rst_DisplayName')}`
                            +' exceeds the maximum size for a field of 64Kbytes.<br>' //lim2
                            +'Note that this does not mean 64K characters, ' //lim2
                            +'as Unicode uses multiple bytes per character.<br>'
                            +'You can store more than 64Kbytes by making the field repeating and splitting the data into two or more values for this field.';
                            window.hWin.HEURIST4.msg.showMsgErr({
                                message: sMsg,
                                error_title: 'Field value too large'
                            });
                            
                            let inpt = this._editing.getFieldByName(dtyID);
                            if(inpt){
                                inpt.editing_input('showErrorMsg', sMsg);
                                $(this.editForm.find('input.ui-state-error')[0]).trigger('focus');   
                            }
                            return;
                            
                        }
                        if(dtyID!=window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_EXTFILES'] && typeof values[k]==='string')
                        {
                            let sval = (values[k]).toLowerCase();
                            if(sval.indexOf('<script')>=0 && sval.indexOf('</script>')>0){
                                let inpt = this._editing.getFieldByName(dtyID);
                                if(inpt) inpt.editing_input('showErrorMsg', '&lt;sctipt&gt; tag not allowed');  
                                hasScriptTag = true;
                            }
                        }
                        if(!hasCustomJsOrCss && typeof values[k]==='string' && rty_ConceptCode == '99-51' &&
                                    (dtyID==window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_EXTFILES'] ||
                                    dtyID==window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_SCRIPT'] ||
                                    dtyID==window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_CSS'])  ){
                            hasCustomJsOrCss = true;         
                        }

                        if(fields['no_validation'] == 1){
                            continue;
                        }

                        let entry_mask = $Db.rst(that._currentEditRecTypeID, dtyID, 'rst_EntryMask');

                        if(dt == 'date'){

                            if(Temporal.isValidFormat(values[k])){ // check if valid temporal string
                                continue; // is valid, skip
                            }

                            const value_spaceless = values[k].replaceAll(/\s+/g, ''); // remove all whitespaces

                            const approx_regex = /circa.?|ca.?|approx.?|~/; // circa 1995, ca. 1995, approx 1995, ~1995
                            const has_range = /[à|.|to|\-|,]/; // range separators
                            const range_regex = /\d+|[à|.|to|\-|,]+/g; // 1990-1995, 1990to1995, 1990..1995, 1990,1995 (spaces removed first)
                            const has_named_month = /(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)/ig; // e.g. 12Jan1995

                            // Approximate date
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

                            // Ranged dates
                            matches = [...value_spaceless.matchAll(range_regex)];
                            if(!window.hWin.HEURIST4.util.isempty(matches) && matches.length > 0 
                                    && has_range.test(value_spaceless) && (value_spaceless.split('-')<3) ){

                                let is_ambig = false;

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

                            // Named month
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

                            // Date + time value - replace '-' between date and time with ' T '
                            matches = [...values[k].matchAll(/-\s?\d{1,2}:/g)];
                            if(matches.length == 1){

                                let new_value = matches[0][0];
                                new_value = 'T' + new_value.substring(1);
                                values[k] = values[k].replace(matches[0][0], new_value);

                                updated_values = true;
                                continue;
                            }

                            // Check for simple ambiguity or carbon year
                            let ttype = 'simple';
                            try {

                                let value = values[k];
                                ttype = value.slice(-2).toLowerCase() == 'bp' ? 'carbon' : ttype;
                                value = ttype == 'carbon' ? value.slice(0, -2) : value;

                                let tDate = TDate.parse(value);
                                let date_val = tDate.toString('yyyy-MM-dd');
                                let format = tDate.getDateFormat();

                                if(ttype == 'carbon'){

                                    if(date_val.length == 4 && value.length <= 4){

                                        let t_date = new Temporal();
                                        date_val = TDate.parse(date_val);

                                        date_val = date_val.toString('yyyy-MM-dd');

                                        t_date.setType('c');
                                        t_date.addObjForString('BPD', date_val);

                                        values[k] = t_date.toString(); // toJSON()
                                        updated_values = true;
                                    }
                                }else if((date_val.length == 4 && values[k].length <= 4) || date_val.length == values[k].length){

                                    if(format == 'dmy' && tDate.getDay() < 13 && tDate.getMonth() < 13){ // only uses 'mdy' when first number has to be month
                                        throw 'ambiguous date';
                                    }

                                    continue;
                                }
                            } catch(e) {
                                if(e.indexOf('ambiguous') >= 0){
                                    ambig_dates.push({dtyid: dtyID, org_value: values[k], index: k, type: ttype});
                                    continue;
                                }
                            }
                        }else if(entry_mask){

                            let output = $Db.rst_RunEntryMask(entry_mask, values[k]); // don't update value here, this is handled in recordModify::updateMaskFields
                            if(output.indexOf(values[k]) === -1){
                                if(!Object.hasOwn(invalid_entries, dtyID)){
                                    invalid_entries[dtyID] = [];
                                }
                                invalid_entries[dtyID].push({value: values[k], index: k, error: output});
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
            }//for verify max size
            
            if(fields != null && !hasDtlField){
                window.hWin.HEURIST4.msg.showMsgFlash("There are no details to save", 1500);
                return;
            }else if(fields != null && !hasValue){
                window.hWin.HEURIST4.msg.showMsgFlash("Please enter a value into any field to save the record", 1500);
                return;
            }else if(fields != null && hasScriptTag){
                window.hWin.HEURIST4.msg.showMsgFlash("Some fields have &lt;sctipt&gt; tag. It is not allowed in database", 1500);
                return;
            }else if(fields != null && ambig_dates.length > 0){
                that._handleAmbiguousDates(ambig_dates);
                return;
            }else if(fields != null && Object.keys(invalid_entries).length > 0){
                that._handleInvalidEntry(invalid_entries);
                return;
            }
            
            //show warning for disabled javascript
            if(that._showCustomJsWarningOnce &&
                !window.hWin.HAPI4.sysinfo['custom_js_allowed'] && hasCustomJsOrCss)
            {
                that._showCustomJsWarningOnce = false;
                window.hWin.HEURIST4.msg.showMsg(
'<h4>Website programming blocked</h4>'
+'<p>Heurist blocks user-supplied Javascript by default (and strips out most user-defined CSS other than simple font changes) for security reasons.</p>'
+'<p>In order to have your Javascript (and CSS) recognised, please ask your server administrator to authorise this for your database by adding it to js_in_database_authorised.txt</p>');
            }
            
            
            //assign workflow stage field 2-9453
            if(fields!=null && this._swf_rules.length>0){
                let swf_mode = this.element.find('.sel_workflow_stages').val();
                if(swf_mode=='on' || (swf_mode=='new' && this._isInsert)){
                    
                    this._showSwfPopup(fields, afterAction);
                    return;
                }
            } //END assign workflow stage field 2-9453
            
        }
        
        if(fields==null) return; //validation failed

        //assign new set of tags to record
        if(Array.isArray(that._updated_tags_selection)){
            let request2 = {};
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
        
        let rec_URL = fields['rec_URL'],
            rec_OwnerUGrpID = fields['rec_OwnerUGrpID'],
            rec_NonOwnerVisibility = fields['rec_NonOwnerVisibility'],
            rec_NonOwnerVisibilityGroups = fields['rec_NonOwnerVisibilityGroups'],
            rec_ScratchPad = fields['rec_ScratchPad'];
        // Unset header fields to avoid accidental overriding                
        for (let key in fields){
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
        let fields_visibility = this._editing.getFieldsVisibility(); 

        let request = {ID: this._currentEditID, 
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
        
        let dlged = that._getEditDialog();
        if(dlged) window.hWin.HEURIST4.msg.bringCoverallToFront(dlged);

        window.hWin.HAPI4.RecordMgr.saveRecord(request, 
            function(response){
                
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                
                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    that._editing.setModified(false); //reset modified flag after save
                    
                    const rec_Title = response.rec_Title;
                    
                    let saved_record = that._currentEditRecordset.getFirstRecord();
                    that._currentEditRecordset.setFld(saved_record, 'rec_Title', rec_Title);
                    const DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'];
                    if(DT_NAME>0 && fields && fields[DT_NAME]){
                        that._currentEditRecordset.setFld(saved_record, DT_NAME, fields[DT_NAME]);    
                    }

                    //
                    if(that.options.selectOnSave==true){
                        that._additionWasPerformed = true;
                    }

                    window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Record has been saved'));

                    that._afterSaveHandler(response, afterAction);
                    
                }else{
                    that.onEditFormChange(); //restore save buttons visibility
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            }
        );
    },

    _afterSaveHandler: function(response, afterAction){

        if(!Object.hasOwn(response, 'issues')){ // No issues reported from server-side save

            if(window.hWin.HEURIST4.util.isFunction(afterAction)){ // Callback function provided

                afterAction.call();

            }else if(afterAction=='close'){ // Close dialog/editor

                this._currentEditID = null;
                /*A123  remarked since
                triggered in onClose event */
                if(this.options.selectOnSave==true){
                    this.options.select_mode = 'select_single';
                    this.selectedRecords(this._currentEditRecordset);
                }else{
                    this.closeEditDialog();
                }

            }else if(afterAction=='newrecord'){
                this._initEditForm_step3(-1);
            }else if(afterAction=='close_rst'){
                this._switchToDataMode(true);
            }else{
                //reload after save
                this._initEditForm_step3(this._currentEditID);
            }

            return;
        }

        // Message not needed right now as only one issue is handled, probably separate issues into tabs within the message dialog

        let has_msg = false;
        let headers = `The following issues were found when saving the record.<br>
        Note: <strong>The record has been saved</strong>, these issues are just considered minor problems or nuisance<br><br>
        <div class="issues-tabs">
            <ul>`;
        let contents = '';
        let handlers = {};

        let parent = null;
        let readded_parents = null;

        let skippedCalcPref = window.hWin.HAPI4.get_prefs_def('skippedCalcWarning', {});
        const oneHour = 3600000;
        const currentTime = Date.now();

        for(const issue_type in response.issues){

            const issues = response.issues[issue_type];
            const id = `issue-${issue_type}`;
            let msg = '';

            if(window.hWin.HEURIST4.util.isempty(issues) || (typeof issues === 'object' && Object.keys(issues).length == 0)){
                continue;
            }else if(issue_type === 'skippedCalcFields'){

                if(!Object.hasOwn(skippedCalcPref, this._currentEditRecTypeID) || (skippedCalcPref[this._currentEditRecTypeID] + oneHour) <= currentTime){
                    skippedCalcPref[this._currentEditRecTypeID] = currentTime;
                }else{
                    continue;
                }
            }

            switch(issue_type){

                case 'parents':

                    parent = Object.keys(issues)[0];
                    issues[parent]['restored'] = [{
                        field: issues[parent]['field'],
                        type: this._currentEditRecTypeID,
                        id: this._currentEditID,
                        title: response.rec_Title
                    }];

                    readded_parents = window.hWin.HEURIST4.msg.prepareParentRecordMsg(response.issues['parents']);
                    if(typeof readded_parents === 'object'){

                        handlers = $.extend(handlers, readded_parents.handlers);

                        headers += `<li><a href="#${id}">${readded_parents.title}</a></li>`;

                        contents += `<div id="${id}" style="margin: 7.5px 10px;">${readded_parents.message}</div>`;

                        has_msg = true;
                    }

                    break;

                case 'entryMask':

                    // { dty_ID =>  [{value, reason}, ...], ... }
                    for(const dty_ID in issues){

                        msg += `<div>
                        <strong style="display: block; padding-bottom: 7.5px;">${$Db.rst(this._currentEditRecTypeID, dty_ID, 'rst_DisplayName')}</strong>`;

                        let value_issues = issues[dty_ID];
                        for(const issue of value_issues){
                            msg += `<span class="truncate" title="${issue.value}" style="display: inline-block; width: 150px; max-width: 150px;">${issue.value}</span>
                                    <span>Error: ${issue.reason}</span><br>`;
                        }

                        msg += '</div><hr style="margin: 15px 0px;">';
                    }

                    if(!window.hWin.HEURIST4.util.isempty(msg)){

                        headers += `<li><a href="#${id}">Failed entry masks</a></li>`;

                        contents += `<div id="${id}" style="margin: 7.5px 10px;">${msg}</div>`;

                        has_msg = true;
                    }

                    break;

                case 'skippedCalcFields':

                    headers += `<li><a href="#${id}">Calculated fields</a></li>`;
                    contents += `<div id="${id}" style="margin: 7.5px 10px;">${issues}</div>`;

                    has_msg = true;
                
                    break;

                case 'languages':{

                    if(issues?.added && Object.keys(issues.added).length > 0){

                        let multiple = Object.keys(issues.added).length > 1;
                        let languages = Object.entries(issues.added).reduce((arr, lang) => arr.concat(lang[1].name), []);
                        msg = multiple ?
                            `The following languages have been added to the allowed list: <strong>${languages.join(', ')}</strong>.` :
                            `The language <strong>${languages[0]}</strong> was added to the allowed list.`;

                        msg += `<br><br>This will allow the language(s) to appear in language selectors and to work within this database's websites.<br><br>
                        You can edit the list of allowed languages (and their order) at 
                        <span id="lnk_Props" style="text-decoration: underline; cursor: pointer;">
                            "Design" > "Properties"
                        </span> > "List of allowed languages to be used for record data and CMS websites".<br><hr><br>`;

                        $.extend(window.hWin.HAPI4.sysinfo.common_languages, issues.added);
                        $.extend(handlers, {
                            '#lnk_Props': () => window.hWin.HAPI4.SystemMgr.verify_credentials(() => window.hWin.HEURIST4.ui.showEntityDialog('sysIdentification'), 1)
                        });
                    }

                    if(issues?.unknown && Object.keys(issues.unknown).length > 0){

                        let unknown = Object.values(issues.unknown).join('<br>');
                        msg += `<div style="padding: 10px 2em;">${unknown}</div><br>
                        Please edit the language vocabulary used and add the ISO 639-2 codes (e.g. EN, FR, ...)<br>
                        as a standard code for each of the language terms selected<br><hr><br>`;
                    }

                    if(issues?.website && Object.keys(issues.website).length > 0){

                        let webLangs = Object.entries(issues.skipped).reduce((string, lang) => `${string}<br>${lang[0]}: ${lang[1]}`, '');
                        msg += `The following languages have been set for CMS website usage but are not setup for database usage:<strong>${webLangs}</strong><br>
                        While these languages will be listed as options on CMS websites, Heurist will instead use English.<br><br>
                        To allow the usage for these languages, please request a database administrator to add them to the list found at 
                        "Design" > "Properties" > "List of allowed languages to be used for record data and CMS websites".<br><hr><br>`;
                    }

                    if(issues?.skipped && Object.keys(issues.skipped).length > 0){

                        let skipped = Object.entries(issues.skipped).reduce((string, lang) => `${string}<br>${lang[0]}: ${lang[1]}`, '');
                        msg += `The following languages were found in record data that are not currently allowed:<strong>${skipped}</strong><br><br>
                        These values have been saved and are accessible but will only be partially supported.<br><br>
                        Please ask a database administrator to add the above languages to the list of database languages, 
                        found at "Design" > "Properties" > "List of allowed languages to be used for record data and CMS websites".<br>`;
                    }

                    if(!window.hWin.HEURIST4.util.isempty(msg)){
    
                        headers += `<li><a href="#${id}">Languages</a></li>`;
    
                        contents += `<div id="${id}" style="margin: 7.5px 10px;">${msg}</div>`;
    
                        has_msg = true;
                    }
    
                    break;
                }

                default:
                    break;
            }
        }

        if(has_msg){

            headers += '</ul>';

            let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(headers + contents + '</div>', null, {title: 'Record issues'}, {default_palette_class: 'ui-heurist-populate', dialogId: 'record-issues'});

            for(const selector in handlers){
                $dlg.find(selector).on('click', handlers[selector]);
            }

            $dlg.find('.issues-tabs').tabs();
        }

        skippedCalcPref = !window.hWin.HEURIST4.util.isObject(skippedCalcPref) ? {} : skippedCalcPref;
        window.hWin.HAPI4.save_pref('skippedCalcWarning', skippedCalcPref);

        delete response.issues;
        return this._afterSaveHandler(response, afterAction);
    },
    
    /**
     * @brief Handles changes in the edit form, enabling/disabling save buttons.
     * @override
     * @memberof heurist.manageRecords
     * @param {HEditing_input|boolean} [changed_element] The input element that changed, or true to force hide save buttons.
     * This method is called when a field value changes in the `HEditing` widget.
     * It updates the visibility/state of save-related buttons based on whether the form
     * has modifications (`_editing.isModified()` or `_updated_tags_selection` is set).
     * It also includes special logic for certain field types, like auto-populating
     * related fields for Tiled Image Map Source or Map Layer records, or auto-saving
     * if a parent-child pointer field is modified.
     */
    onEditFormChange:function(changed_element){
        
        let that = this;
		
        let force_hide = (changed_element===true); //hide save buttons
        
        let mode = 'hidden';
        if(force_hide!==true){ // If not forcing hide, check for modifications
            let isChanged = this._editing.isModified() || this._updated_tags_selection!=null;
            mode = isChanged?'visible':'hidden';
            
            if(isChanged && changed_element){  // && changed_element.options
                
                //special case for tiled image map source - if file is mbtiles - assign tiler url 
                if(changed_element.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_FILE_RESOURCE'] && 
                   changed_element.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_IMAGE_SOURCE']){
                       
                      //check extension - only mbtiles allowed
                      const val = changed_element.getValues();
                      if(val && val.length>0 && !window.hWin.HEURIST4.util.isempty(val[0])){
                            let ext = window.hWin.HEURIST4.util.getFileExtension(val[0]['ulf_OrigFileName']);
                            if(ext=='mbtiles'){
                                const ulf_ID = val[0]['ulf_ID'];
                                const url =  window.hWin.HAPI4.baseURL + '`mbtiles`.php?/' + window.hWin.HAPI4.database + '/ulf_'+ulf_ID;
                                this._editing.setFieldValueByName(window.hWin.HAPI4.sysinfo['dbconst']['DT_SERVICE_URL'], url);
                                this._editing.setFieldValueByName(window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_IMAGE_LAYER_SCHEMA'], 'zoomify'); //2-550
                                this._editing.setFieldValueByName(window.hWin.HAPI4.sysinfo['dbconst']['DT_MIME_TYPE'], 'image/png'); //2-540
                            }
                      }
                      
                }else if(changed_element.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_SERVICE_URL'] && 
                            changed_element.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_TILED_IMAGE_SOURCE']){

                      const val = changed_element.getValues();
                      if(val && val.length>0 && !window.hWin.HEURIST4.util.isempty(val[0])){
                          
                            let mimetype = val[0]['ulf_MimeExt'];
                            if(mimetype=='image/jpg'){ mimetype='image/jpeg'; }
                            let ele = this._editing.getInputs( window.hWin.HAPI4.sysinfo['dbconst']['DT_MIME_TYPE'] );
                            if(ele.length>0){
                                ele = $(ele[0]);
                                let idx = ele.find('option:contains("'+mimetype+'")').index();
                                ele[0].selectedIndex = idx;
                                if( ele.hSelect('instance')!==undefined) ele.hSelect('refresh');
                            }
                      } 
                      
                }else if(changed_element.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_DATA_SOURCE'])
                {
                    //
                    //get name and bbox from map source and assign to map layer fields
                    //
                    const val = changed_element.getValues();
                    if(val && val.length>0 && !window.hWin.HEURIST4.util.isempty( val[0] )){
                        const _recID = val[0];
                        const dtId_Name = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'];
                        const dtId_Geo = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'];
                        let sName = '', sGeo = '';
                        //find values
                        
                        let ele = that._editing.getFieldByName( dtId_Name );
                        if(ele){
                            const vals = ele.editing_input('getValues');
                            sName = vals[0];
                        }
                        ele = that._editing.getFieldByName( dtId_Geo );
                        if(ele){
                            const vals = ele.editing_input('getValues');
                            sGeo = vals[0];
                        }
                        if(window.hWin.HEURIST4.util.isempty(sName) ||
                           window.hWin.HEURIST4.util.isempty(sGeo)){
                               
                           //search for values    
                           window.hWin.HAPI4.RecordMgr.search({q: 'ids:'+_recID, w: "e", f:"complete", l:1}, 
                                function(response){ 
                               
                                    if(response!=null && response.status == window.hWin.ResponseStatus.OK){
                                        let recset = new HRecordSet(response.data);
                                        let rec = recset.getFirstRecord();
                                        if(window.hWin.HEURIST4.util.isempty(sName)){
                                            const val = recset.fld(rec, dtId_Name);    
                                            that._editing.setFieldValueByName(dtId_Name, val);
                                        }
                                        
                                        if(window.hWin.HEURIST4.util.isempty(sGeo)){
                                            const val = recset.fld(rec, dtId_Geo);    
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
                    let recIds = changed_element.getValues();
                    if(recIds && recIds.length>0 && recIds[0]>0){
                            //mapdocument extent
                            
                            const dtId_Geo = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'];
                            let ele = that._editing.getFieldByName( dtId_Geo );
                            if(ele){
								
                                /*
                                function templateimport_link() {
                                    let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
                                    $dlg.dialog( "close" );

                                    that.closeEditDialog();
                                    // Open Design->Browse Templates
                                    window.hWin.HAPI4.actionHandler.executeActionById('menu-structure-import');

                                    return;
                                }                                
                                const vals = ele.editing_input('getValues');
                                let mapdoc_extent;
                                if(vals[0]) {
                                    mapdoc_extent = window.hWin.HEURIST4.geo.getWktBoundingBox(vals);
                                }
                                */
                            
                                //search for values    
                                window.hWin.HAPI4.RecordMgr.search({q: 'ids:'+recIds.join(','), w: "e", f:dtId_Geo}, 
                                    function(response){ 

                                        if(response!=null && response.status == window.hWin.ResponseStatus.OK){
                                            let summary_ext = [];
                                            let recset = new HRecordSet(response.data);
                                            recset.each(function(recID, rec){
                                                let layer_extent2 = recset.fld(rec, dtId_Geo);
                                                let layer_extent = window.hWin.HEURIST4.geo.getWktBoundingBox([layer_extent2]);
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
                                        function(isCancel){
                                                if(isCancel) return;
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
                    let parententity = changed_element.f('rst_CreateChildIfRecPtr');                
                    if(parententity==1){
                        //get values without validation
                        let fields = this._editing.getValues(false);
                        
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
            let ele = this._toolbar;
            /*ele.find('.btnRecCancel').css('visibility', mode);
            ele.find('.btnRecSaveAndNew').css('visibility', mode);
            ele.find('.btnRecSave').css('visibility', mode);
            ele.find('.btnRecSaveAndClose').css('visibility', mode);*/
            window.hWin.HEURIST4.util.setDisabled(ele.find('.btnRecCancel'), (mode=='hidden'));
            window.hWin.HEURIST4.util.setDisabled(ele.find('.btnRecSaveAndClose'), (mode=='hidden'));
            
            
            
            //save buton is always enabled - just greyout in nonchanged state
            if(mode=='hidden'){
                ele.find('.btnRecSave').css({opacity: '.35'});  //addClass('ui-state-disabled'); 
            }else{
                ele.find('.btnRecSave').css({opacity: '1'}); //.removeClass('ui-state-disabled'); // ui-button-disabled
            }
        
        }

    },
    
    //
    // 1. show-hide optional fields
    // 2. add menu button on top of screen (_configureHeaderControls)
    // 3. add record title at the top (_displayRecordTitle)
    // 4. init cms open edit listener (conditionally)
    // 5. init rts_editor action buttons (_setupRtsEditingFeatures)
    /**
     * @brief Performs UI adjustments and sets up controls after the HEditing form is initialized.
     * @override
     * @memberof heurist.manageRecords
     * This method is responsible for a variety of UI setup tasks post-HEditing initialization:
     * 1. Sets up header controls: "Show Help", "Optional Fields", "Workflow Stage" selector, "Toggle Record Visibility",
     *    "Modify Structure", "Attributes", "Update Structure", "History", "Template", and "Ticket" buttons.
     * 2. Displays the record title and type information in the dialog header or a dedicated header div.
     * 3. Manages the visibility and state of these controls based on user permissions and widget options (e.g., `edit_structure`, `allowAdminToolbar`).
     * 4. Initializes functionality for the RTS (Record Type Structure) editor integration if active, including "gear" icons on fields.
     * 5. Applies user competency levels to the form and handles help text visibility.
     * 6. Restores group (accordion/tab) states.
     * 7. Logs the record edit action.
     * 8. Calls `onEditFormChange()` to set initial save button states.
     */
    _afterInitEditForm: function(){

        let that = this;
        
        let ishelp_on = (this.usrPreferences['help_on']==true || this.usrPreferences['help_on']=='true');
        let isfields_on = this.usrPreferences['optfields']==true || this.usrPreferences['optfields']=='true';
        let btn_css = {'font-weight': 'bold', color:'#7D9AAA', background:'none', padding: '4.5px' }; //#ecf1fb

        let swf_rules_mode = 'on';
        if(this.usrPreferences['swf_rules_mode'] && this.usrPreferences['swf_rules_mode'][this._currentEditRecTypeID]){
            swf_rules_mode = this.usrPreferences['swf_rules_mode'][this._currentEditRecTypeID];
        }
        
        // Icon
        let ph_gif = window.hWin.HAPI4.baseURL + 'hclient/assets/16x16.gif';
        let rt_name = $Db.rty(this._currentEditRecTypeID,'rty_Name');

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

                +'<div style="padding-right:25px;display:inline-block" class="rt-info-header">'
                    +'<img src="'+ph_gif
                        +'" width=36 height=36 class="rt-icon" style="padding:2px;background-size: 28px 28px;vertical-align:middle;margin: 2px 10px 2px 4px;"/>'
                    + '<span style="display:inline-block;vertical-align:middle;font-size:larger;font-weight:bold;max-width:25ex;" '
                    + 'class="truncate" title="'+ rt_name +'">'
                        + rt_name
                    + '</span>'
                +'</div>'

                +'<div style="padding-right:20px;padding-left:5px;display:inline-block">'
                    +'<span class="btn-edit-rt2 btns-admin-only" style="font-size:larger"></span>'
                    +'<span class="btn-edit-rt-back" style="font-size:larger;display:none">Close (&rarr; Data editing)</span>' //Back to Whole Form
                +'</div>'
             
                +'<div style="display:inline-block;padding-left:5px">'
                    +'<label><input type="checkbox" class="chb_show_help" '
                        +(ishelp_on?'checked':'')+'/>Show help</label>'
                    +'<span class="gap" style="display:inline-block;width:15px"></span>'
                    +'<label class="lbl_opt_fields"><input type="checkbox" class="chb_opt_fields" '
                        +(isfields_on?'checked':'')+'/>Optional fields</label>'
                    +'<span class="gap" style="display:inline-block;width:15px"></span>'
                    +'<span class="div_workflow_stages btns-noguest-only"><label>Workflow stage popup: </label>'
                        +'<select class="sel_workflow_stages">'
                            +'<option value="new">New records only</option>'
                            +'<option value="on">New and existing records</option>'
                            +'<option value="off">OFF</option>'
                        +'</select>'
                        +'<button id="show_workflow_stages">show</button>'
                    +'</span>'
                    +'<span id="rec_visibility" style="padding-left: 5px;" class="btns-noguest-only">'
                        +'<span id="icon_rec_visibility" class="ui-icon"></span>'
                        +'<span id="toggle_rec_visibility"></span>'
                    +'</span>'
                +'</div>'

                +'<div style="padding:10px 50px 0px 0px;float:right">'
                    +'<span class="btn-edit-rt btns-admin-only">Attributes</span>'
                    +'<span class="btn-update-struct btns-admin-only">Update structure from source</span>'
                    +'<span class="btn-refresh-struct btns-admin-only">Refresh structure</span>'
                    +'<span class="btn-rec-history btns-admin-only">History</span>'
                    +'<span class="btn-edit-rt-template btns-admin-only">Template</span>'
                    +'<span class="btn-bugreport">Create ticket</span>'
                +'</div>'
                +'<div class="btn-lookup-values" style="padding-top:10px;padding-left: 24px;">'
                    //+'<span style="font-size:larger" title="Lookup external service">Lookup value</span>'
                +'</div>'
                
             +'</div></div>').insertBefore(this.editForm.first('fieldset'));

            this.element.find('.btn-edit-rt').button({icon:'ui-icon-pencil'});
            this.element.find('.btn-update-struct').button({icon:'ui-icon-pencil'});
            
            if(window.hWin.HAPI4.is_admin() && this.options.allowAdminToolbar!==false)
            {
                
                this.element.find('.btns-admin-only').show();

                this.element.find('.btn-edit-rt').css(btn_css)
                        .on('click', function(){that.editRecordTypeAttributes();}); //was editRecordType(false)

                this.element.find('.btn-update-struct').css(btn_css)
                        .on('click', function(){that._updateStructureFromSource(false);}); // update record structure from source
                
                let btn = this.element.find('.btn-edit-rt2');        

                let cont = this.element.find('.editStructureHeader').css({overflow:'hidden', padding: '0px 10px'});

                $('<div>', {
                    style: 'float: left;',
                    html: `<span style="margin-top: 15px;display: inline-block;font-size: 12px;">Modify structure: </span>
                    <h1 style="float:right;margin:10px;">${$Db.rty(this._currentEditRecTypeID,'rty_Name')}</h1>`
                }).appendTo(cont);      

                $('<span>', {
                    class: 'heurist-helper3',
                    style: 'float: left; margin: 10px;font-size: 1em;',
                    html: 'This is an empty record. Test data entry as you develop the structure.<br>If you want to retain the data entered, hit [Save data], otherwise [Close]'
                }).appendTo(cont);

                this.element.find('.btn-edit-rt').hide(); //Attributes button next to Edit title mask
                btn.button({icon:'ui-icon-gear',label:'<span style="display:inline-block;margin-top:5px;">Modify structure</span>'})
                        .css(btn_css)
                        .width(130)
                        .on('click', function(){that.editRecordType(true);});

                btn.find('.ui-button-icon')
                            .css({'font-size':'25px','float':'left',width:'25px',height:'25px','margin-top':'0px'});

                this.element.find('.btn-edit-rt-template').button({icon:'ui-icon-arrowthickstop-1-s'})
                        .css(btn_css).on('click', function(){
                            window.hWin.HEURIST4.ui.showRecordActionDialog('recordTemplate'
                                    ,{recordType:that._currentEditRecTypeID,
                                      default_palette_class: 'ui-heurist-design'});});

                this.element.find('.btn-refresh-struct').button({icon: 'ui-icon-refresh'})
                        .css(btn_css).on('click', () => {
                            window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, null, '<span style="color: white;">Refreshing database cache...</span>');
                            window.hWin.HAPI4.EntityMgr.refreshEntityData('rty,trm,dty,rst', () => {
                                window.hWin.HEURIST4.msg.sendCoverallToBack();
                                that._initEditForm_step3(that._currentEditID);
                            });
                        });
                if(this._currentEditID){

                    this.element.find('.btn-rec-history').button({icon:'ui-icon-clock'})
                            .css(btn_css).on('click', function(){
                                that._getRecordHistory();
                            });
                }else{
                    this.element.find('.btn-rec-history').hide();
                }
            }else{
                this.element.find('.btns-admin-only').hide();
            }
            
            //lookup external values
            //there is 3d party service for lookup values
            this._setupExternalLookups();           
            
            // ticket
            this.element.find('.btn-bugreport').button({icon:'ui-icon-bug'})
                .css(btn_css).on('click', function(){ window.hWin.HEURIST4.ui.showEntityDialog('sysBugreport'); });
                
                
            this.element.find('.chb_show_help') //.attr('checked', ishelp_on)
                        .on('change', function( event){
                            let ishelp_on = $(event.target).is(':checked');
                            that.usrPreferences['help_on'] = ishelp_on;
                            window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, $(that.element));
                        });
            
            
            this.element.find('.chb_opt_fields') //.attr('checked', isfields_on)
                        .on('change', function( event){
                            let isfields_on = $(event.target).is(':checked');
                            that.usrPreferences['optfields'] = isfields_on;
                           
                            $(that.element).find('div.optional').parent('div[data-dtid]').css({'display': (isfields_on?'table':'none')} ); 
                            $(that.element).find('div.optional_hint').css({'display': (isfields_on?'none':'block')} ); 
                            
                            that._showHideEmptyFieldGroups();
                        });
                       
            
            if(this._swf_rules.length>0){           
                       
                this.element.find('.sel_workflow_stages').val(swf_rules_mode);
                            
                this.element.find('.sel_workflow_stages')
                .on('change', function( event ){
                    if(!that.usrPreferences['swf_rules_mode']) that.usrPreferences['swf_rules_mode'] = {};
                    that.usrPreferences['swf_rules_mode'][that._currentEditRecTypeID] = $(event.target).val();
                });
            
                this.element.find('#show_workflow_stages').button().css('margin-left', '5px').on('click', function(){
                    that._showSwfPopup();
                });
            
            }else{
                this.element.find('.div_workflow_stages').hide();
            }
            
        }
        else{
            this.element.find('.chb_opt_fields').prop('checked', isfields_on);
            this.element.find('.chb_show_help').prop('checked', ishelp_on);

           
            this.element.find('.rt-info-header span').text(rt_name).attr('title', rt_name);

            window.hWin.HEURIST4.util.setDisabled(this.element.find('.btn-rec-history'), false); // reset get history button
        }

        // Toggle record visibility button
        if(window.hWin.HAPI4.is_admin() || window.hWin.HAPI4.is_member(this._getField('rec_OwnerUGrpID'))){

            let is_public = this._getField('rec_NonOwnerVisibility') == 'public';
            this.element.find('#icon_rec_visibility').removeClass('ui-icon-eye-crossed ui-icon-eye-open')

            this._on(this.element.find('#toggle_rec_visibility').button(), {
                click: this._toggleRecordVisibility
            });
            
            this._updateRecToggleButton(is_public);
        }else{
            this._off(this.element.find('#toggle_rec_visibility'));
        }

        //add resizing buttons to dialog title bar
        if(this._as_dialog && this._as_dialog.dialog('instance')){

            let $dlg = this._as_dialog.dialog('widget');
            let dlg_header = $dlg.find('.ui-dialog-titlebar');
            
            if(!this._autoResizeCheckbox){

                $('<span>', {
                    class: 'btn_Fullscreen',
                    style: 'margin: -0.9em 0px 0px;right: 26.5em;top: 45%;position: absolute;background: none;color: white;'
                })
                .button({label:window.hWin.HR('Fullscreen')})
                .appendTo(dlg_header);

                $('<span>', {
                    class: 'btn_Standard',
                    style: 'margin: -0.9em 0px 0px;right: 18.5em;top: 45%;position: absolute;background: none;color: white;'
                })
                .button({label:window.hWin.HR('Standard')})
                .appendTo(dlg_header);

                $('<span>', {
                    class: 'btn_Small',
                    style: 'margin: -0.9em 0px 0px;right: 12.25em;top: 45%;position: absolute;background: none;color: white;'
                })
                .button({label:window.hWin.HR('Small')})
                .appendTo(dlg_header);

                this._on(dlg_header.find('.btn_Fullscreen'), {
                    click: () => {
                        this._setDialogSize(false, 'full');
                    }
                });

                this._on(dlg_header.find('.btn_Standard'), {
                    click: () => {
                        this._setDialogSize(false, 'standard');
                    }
                });

                this._on(dlg_header.find('.btn_Small'), {
                    click: () => {
                        this._setDialogSize(false, 'small');
                    }
                });

                let autoResizePopup = this.usrPreferences.autoResizePopup;
                let $span = $('<span>', {
                    html: `<input type="checkbox" class="autoResizePopup" ${autoResizePopup ? 'checked="checked"' : ''} /> autoresize`,
                    style: 'position: absolute;top: 25%;right: 4em;font-weight: normal;cursor: pointer;',
                    title: 'Scale the form to use as much as possible of the window without losing the buttons.\nIf off, buttons may be hidden if the screen or form is resized.'
                }).appendTo(dlg_header);
                this._autoResizeCheckbox = $span.find('input');

                this._on($span, {
                    click: (e) => {

                        if(e.target.tagName === 'SPAN'){

                            this._autoResizeCheckbox.prop('checked', !this._autoResizeCheckbox.prop('checked'));
                            this._autoResizeCheckbox.trigger('change');
                        }
                    }
                });

                this._on(this._autoResizeCheckbox, {
                    change: () => {
                        this.saveUiPreferences();
                    }
                });
            }

            $dlg.css('box-shadow', '2px 3px 10px #00000080');

            window.hWin.HAPI4.addEventListener(this, window.hWin.HAPI4.Event.ON_WINDOW_RESIZE, () => this._setDialogSize(true, 'full'));
        }

        //add record title at the top ======================
        
           
            
        this.editHeader = this.element.find('.editHeader');

        let sheader = '<div style="text-align:left;min-height:25px;display:inline-flex;align-items:center;" class="edit-record-title">';  ///class="ui-heurist-header2" 
        
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
                    + window.hWin.HEURIST4.util.stripTags(this._getField('rec_Title'),'u, i, b, strong, em')+'</h3>';
            }
        }
        sheader = sheader + '</div>';

        if(this._as_dialog){
                
            let ele = this._as_dialog.parent().find('.ui-dialog-titlebar')
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
                let helpURL = window.hWin.HRes( this.options.entity.helpContent )+' #content';
                window.hWin.HEURIST4.ui.initDialogHintButtons(this.element, '.ui-heurist-header2', //where to put button
                             helpURL);    
            }
        }
        
        //need refresh layout to init overflows(scrollbars)        
        if(this.editFormSummary && this.editFormSummary.length>0){
             this.editFormPopup.layout().resizeAll();
        }
        
        if(this.element.find('.btn-modify_structure').length>0){
            this.element.find('.btn-modify_structure').on('click', function(){that.editRecordType(true);});
        }


        //show-hide optional fields 
        $(this.element).find('div.optional').parent().css({'display': (isfields_on?'table':'none')} ); 
        $(this.element).find('div.optional_hint').css({'display': (isfields_on?'none':'block')} ); 
        
        //open cms content editor - not used
        $(this.element).find('span[data-cms-edit="1"]').on('click', function(event){
            that._saveEditAndClose(null, function(){
                that.closeEditDialog();
                window.hWin.HEURIST4.ui.showEditCMSwin( {record_id:that._currentEditID,  
                    field_id:$(event.target).attr('data-cms-field')} );
            });
        });

        //5. init rts_editor action buttons 
        if(this.options.rts_editor){

            $(this.element).find('div[data-dtid]').each(function(idx, item){
                let dtId = parseInt($(item).attr('data-dtid'));
                if(dtId>0){
                    that._createRtsEditButton(dtId, item);

                    if(dtId != 9999999 && $Db.rst(that._currentEditRecTypeID, dtId) === null){ // non-standard field, add 'Add to record structure' button
                        that._createNonStandardField(dtId, $(item).find('div.input-cell'));
                    }
                }
            });
            //add action button for accordion panels
            $(this.element).find('div.tab-separator-helper').each(function(idx, item){
                let dtId = parseInt($(item).attr('separator-dtid'));
                if(dtId>0){
                    that._createRtsEditButton(dtId, item);
                }
            });
            
            
            //extend separator help left padding
            $(this.element).find('.separator-helper:not(.explanation-separator-helper)').css({'padding-left':'52px'});
            
            //init back button - if there is opened rts editor
            let btn_close_editor = this.element.find('.btn-edit-rt-back');
                
            if(btn_close_editor.length > 0){

                btn_close_editor.off('click');
                btn_close_editor.button({icon:'ui-icon-gear-crossed'}).show()
                    .on('click', () => {this._switchToDataMode(false)});
                if(btn_css) btn_close_editor.css(btn_css);

                // Flash button
                btn_close_editor.fadeIn(100).fadeOut(100).effect('highlight', {color: '#307D96'}, 1000);
            }
            this.element.find('.btn-edit-rt2').hide();

            //switch on optional fields, disable checckbox and hide
            this.element.find('.chb_opt_fields').prop('checked',true).attr('disabled', true).trigger('change');
            this.element.find('.lbl_opt_fields').hide();
            			
            //hide message about forbidden fields
            $(this.element).find('.hidden_field_warning').hide();
            
            //show forbidden fields as disabled - except gearwheel
            let ele_fb = $(this.element).find('div.forbidden');
            ele_fb.css({'opacity':'0.3'});   //header
            ele_fb.next().css({'opacity':'0.3'}); //repeat btn 
            let ele_id = ele_fb.next().next().css({'opacity':'0.3'}); //input-cell 
           
            ele_id.find('input,textarea,button,.ui-selectmenu-button').css('border','1px dotted red');
            
            //display message at bottom
            $('<div id="mod-struct-help" style="font-size:1.2em;margin-top:30px;">Use the gearwheel <span class="ui-icon ui-icon-gear"></span> to add/edit fields and headings</div>')
            .appendTo(this.editForm.last('.editForm.recordEditor'));			
            
            //hide swf mode selector
            this.element.find('.div_workflow_stages').hide();            

            //hide additional markup
            this.element.find('.gap').hide();
			
            //if record type has been changed - reload rts_editor
            this._reloadRtsEditor();

            // add '+' button to end of tabs, creates new tab header
            this._addNewTabButton();

            // Hide 'toggle record visibility'
            if(this.element.find('#rec_visibility').length > 0){
                this.element.find('#rec_visibility').hide();
            }

            //show the attributes button
            let parentEle = this.element.find('.chb_show_help').parent().parent();
            this.element.find('.btn-edit-rt')
                        .show()
                        .button('option', 'label', 'Edit record type attributes')
                        .css('position', 'absolute');
            if(parentEle.length){            
                    this.element.find('.btn-edit-rt').position({
                            my: 'left+20 center', at: 'right center', of: parentEle
                        });
            }
            
            this.element.find('.btn-update-struct')
                        .css('position', 'absolute');

            if(this._source_db.id == 0){
                this._checkStructureFromSource();
            }

            // Highlight current focus in tree structure
            this._on(this.editForm.find('div[data-dtid] input, div[data-dtid] select, div[data-dtid] textarea'), {
                focus: (event) => { // Remove previous node focus
                    let $target_parent = $(event.target).parents('div[data-dtid]:first');
                    if($target_parent.length == 1){
                        that.options.rts_editor.manageDefRecStructure('highlightNode', $target_parent.attr('data-dtid'));
                    }
                },
                blur: (event) => { // Remove node focus
                
                    if(!(that.options.rts_editor 
                        && typeof that.options.rts_editor.manageDefRecStructure === 'function'
                        && that.options.rts_editor.manageDefRecStructure('instance'))){
                        return;
                    }

                    let $target_ele = $(event.target);

                    if($target_ele.is('select')){ // Check if selectmenu is still visible
                        let $sel = $target_ele.parent().find('select.enum-selector-main');
                        if($sel.length == 1){
                            let id = $sel.attr('id');
                            if($sel.parent().find('#'+id+'-menu').is(':visible')){
                                setTimeout(($ele)=>{ $ele.trigger('blur'); }, 100, $target_ele);
                                return;
                            }
                            that.options.rts_editor.manageDefRecStructure('highlightNode', $target_ele.parents('div[data-dtid]:first').attr('data-dtid'), true);
                            return;
                        }
                    }

                    that.options.rts_editor.manageDefRecStructure('highlightNode', null);
                }
            });

            let recTitle = this._getField('rec_Title');
            if(window.hWin.HEURIST4.util.isempty(recTitle) || this.options.edit_structure){
                this.editFormPopup.layout().show('north');
            }
        }else{

            $(this.element).find('.separator-hidden').hide();

            this.element.find('.btn-edit-rt2').show();
            this.element.find('.btn-edit-rt-back').hide();
            this.element.find('.chb_opt_fields').attr('disabled', false);
            this.element.find('.lbl_opt_fields').show();
            this.element.find('.sel_workflow_stages').attr('disabled', false);
            this.element.find('.lbl_workflow_stages').show();

            this.element.find('.btn-edit-rt')
                        .hide()
                        .button('option', 'label', 'Attributes')
                        .css({top: '', left: '', position: ''});

            this.element.find('.btn-update-struct').hide();

            $(this.element).find('div.forbidden').parent().css({'display':'none'} ); 

            if(this._swf_rules.length>0){
                this.element.find('.div_workflow_stages').show();
            }
            if(this.element.find('#rec_visibility').length > 0){
                this.element.find('#rec_visibility').show();
            }

            this.element.find('.gap').show();

            //to save space - hide all fieldsets without visible fields
            this._showHideEmptyFieldGroups();

            this.editFormPopup.layout().hide('north');
        }

                
                
        if(window.hWin.HAPI4.is_guest_user()){
            this.element.find('.btns-noguest-only').hide();
        }
        
        
        window.hWin.HEURIST4.ui.applyCompetencyLevel(-1, this.editForm);
        //show-hide help text below fields - it overrides comptency level
        window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, $(this.element));
        
        this._afterInitEditForm_restoreGroupStatus();

        // Change gap size for the repeat button container, to keep form elements inline with each other
        let $all_repeat_gaps = this.editForm.find('.editint-inout-repeat-container:has(.editint-inout-repeat-button.ui-icon-circlesmall-plus)');
        if($all_repeat_gaps.length > 0){

            let is_both_icons = $all_repeat_gaps.has('.editint-inout-repeat-button.ui-icon-translate');
            let width = $($all_repeat_gaps[0]).width();
            width = !width || width <= 0 ? 30 : width;

            if(is_both_icons.length > 0){
                width = width + 10; // add 10 pixels
            }

            this.editForm.find('.editint-inout-repeat-container').css('min-width', width);
        }
        
        //update record type icon
        let rt_icon = window.hWin.HAPI4.iconBaseURL+this._currentEditRecTypeID+this._icon_timer_suffix;

        this.element.find('.rt-info-header img.rt-icon').css('background-image',`url('${rt_icon}')`);
        if(this.editFormSummary){
            this.editFormSummary.find('.summary-accordion').first().find('img.rt-icon').css('background-image',`url('${rt_icon}')`);    
        }
        
        //
        //
        //        
        this.onEditFormChange();
        
        window.hWin.HAPI4.SystemMgr.user_log('editRec', {ids: this._currentEditID, count: 1}); // log action
        
        if(window.hWin.HEURIST4.util.isFunction(this.options.onInitEditForm)){
            this.options.onInitEditForm.call();
        }
        
                    
    },//END _afterInitEditForm
    
    //
    /**
     * @brief Updates the "Toggle Record Visibility" button's appearance and tooltip.
     * @memberof heurist.manageRecords
     * @param {boolean} is_public True if the record is currently public, false otherwise.
     * Changes the button's icon (eye-open/eye-crossed) and label ("Hide from public"/"Make record public")
     * to reflect the current visibility state.
     */
    _updateRecToggleButton: function(is_public){
            if(is_public){
                this.element.find('#icon_rec_visibility').removeClass('ui-icon-eye-crossed').addClass('ui-icon-eye-open');
                this.element.find('#toggle_rec_visibility')
                            .attr('title', 'This record is VISIBLE TO THE PUBLIC. Click to set it as hidden from the public')
                            .button('option', 'label', window.hWin.HR('Hide from public'));
            }else{
                this.element.find('#icon_rec_visibility').removeClass('ui-icon-eye-open').addClass('ui-icon-eye-crossed');
                this.element.find('#toggle_rec_visibility')
                            .attr('title', 'This record is HIDDEN FROM PUBLIC VIEW. Click to make it visible to the public')
                            .button('option', 'label', window.hWin.HR('Make record public'));
            }
    },
    
    //
    /**
     * @brief Reloads the Record Type Structure (RTS) editor.
     * @memberof heurist.manageRecords
     * @param {boolean} [force_reload=false] If true, forces a reload even if the editor seems current.
     * This is typically called when the record type changes or when RTS editing is explicitly invoked.
     * It clears the existing RTS editor container and re-initializes the `manageDefRecStructure`
     * widget within it for the current `_currentEditRecTypeID`.
     */
    _reloadRtsEditor: function(force_reload = false){            

            if(!force_reload && this.options.rts_editor 
                    && window.hWin.HEURIST4.util.isFunction(this.options.rts_editor.manageDefRecStructure)
                    && this.options.rts_editor.manageDefRecStructure('instance') 
                    && this.options.rts_editor.manageDefRecStructure('option','rty_ID')==this._currentEditRecTypeID)
            {
                    return;
            } 

            let $structure_editor = this.element.find('.editStructure');
            $structure_editor.children().remove();
            let rts_edit_container = $('<div>').appendTo($structure_editor);
            //show left layout panel, hide summary panel
            let popup_options = {
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
    /**
     * @brief Shows or hides optional fields in the form.
     * @memberof heurist.manageRecords
     * @param {boolean} isShow True to show optional fields, false to hide them.
     * This method programmatically checks or unchecks the "Optional fields" checkbox
     * and triggers its change event to apply the visibility change.
     */
    showOptionalFields: function(isShow){
        this.element.find('.chb_opt_fields').prop('checked', isShow).trigger('change');
    },
    
    /**
     * @brief Shows or hides field groups (fieldsets) based on whether they contain visible fields.
     * @memberof heurist.manageRecords
     * Iterates through fieldsets in the edit form. If a fieldset (not part of a tab or accordion)
     * contains no visible child elements (fields), it's hidden to save space.
     * If optional fields are hidden and a section contains only optional fields, a hint
     * "This section contains optional fields" may be displayed.
     */
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
        let isfields_on = this.usrPreferences['optfields']==true || this.usrPreferences['optfields']=='true';
        if(!isfields_on){
            let sep = null; //current separator(header)
        
            this.editForm.children().each(function(){
                
                //$(this).is('fieldset')
                
                if($(this).is('h4.separator')){
                    sep = $(this);
                }else if($(this).is('fieldset') && sep!=null){
                    
                    //!$(this).is(':visible') && 
                    if($(this).children(':visible').length==0){ //none visible
                         
                        //fieldset may have invisible fields: optional or forbidden
                        let need_show_hint = ($(this).find('div > div.optional').length>0);
                    
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
    /**
     * @brief Retrieves UI preferences for the manageRecords widget.
     * @memberof heurist.manageRecords
     * @returns {object} The user preferences object.
     * Loads preferences using `window.hWin.HAPI4.get_prefs_def` with the key
     * 'prefs_records' and `this.defaultPrefs` as defaults. It also applies
     * minimum and maximum constraints to width and height.
     */
    getUiPreferences: function(){
        this.usrPreferences = window.hWin.HAPI4.get_prefs_def('prefs_'+this._entityName, this.defaultPrefs);
        if(this.usrPreferences.width<600) this.usrPreferences.width=600;
        if(this.usrPreferences.height<300) this.usrPreferences.height=300;
        if (this.usrPreferences.width>this.defaultPrefs.width) this.usrPreferences.width=this.defaultPrefs.width;
        if (this.usrPreferences.height>this.defaultPrefs.height) this.usrPreferences.height=this.defaultPrefs.height;
        if(!Object.hasOwn(this.usrPreferences, 'autoResizePopup')) this.usrPreferences.autoResizePopup = true;
        return this.usrPreferences;
    },
    
    //
    /**
     * @brief Saves the current UI preferences for the manageRecords widget.
     * @memberof heurist.manageRecords
     * @returns {boolean} Always returns true.
     * Collects current UI state: dialog dimensions (width, height, position if applicable),
     * summary panel state (closed, width, active tabs), structure panel state (width, closed),
     * and visibility of help text and optional fields. Saves these using
     * `window.hWin.HAPI4.save_pref` with the key 'prefs_records'.
     */
    saveUiPreferences: function(){
        
        let that = this;
        
        let dwidth = this.defaultPrefs['width'],
            dheight = this.defaultPrefs['height'],
            activeTabs = [],
            help_on = true,
            optfields = true;
            
        let params = this.getUiPreferences();    
            
        if(that.editFormSummary && that.editFormSummary.length>0){
            
                that.editFormSummary.find('.summary-accordion').each(function(idx,item){
                    let active = $(item).accordion('option','active');
                    if(active!==false){
                        activeTabs.push(String(idx));
                    }
                });

                let myLayout = that.editFormPopup.layout();                
                
                params.summary_closed = myLayout.state.east.isClosed;;
                params.summary_width = myLayout.state.east.size;
        }
        if(that.options.rts_editor){
                let myLayout = that.editFormPopup.layout();                
                params.structure_width = myLayout.state.west.size;
                params.structure_closed = myLayout.state.west.isClosed?1:0;
        }
        
        help_on = that.element.find('.chb_show_help').is(':checked');
        optfields = that.element.find('.chb_opt_fields').is(':checked');
            
        
        if(this.options.edit_mode=='editonly'){
            if(that.options.isdialog){
                dwidth  = that._as_dialog.dialog('option','width');
                dheight = that._as_dialog.dialog('option','height');
                
                let cnt = $('div.ui-dialog[posid^="edit'+this._entityName+'"]').length;
                if(cnt==1){ //save position
                    let dlged = that._as_dialog.parent('.ui-dialog');
                    params['top'] = parseInt(dlged.css('top'),10);
                    params['left'] = parseInt(dlged.css('left'), 10);
                }
                
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

        params.autoResizePopup = this._autoResizeCheckbox ? this._autoResizeCheckbox.is(':checked') : params.autoResizePopup;

        window.hWin.HAPI4.save_pref('prefs_'+this._entityName, params);
        
        return true;
    },

    //
    /**
     * @brief Sets the size of the edit dialog (popup).
     * @memberof heurist.manageRecords
     * @param {boolean} [isAutoResize=false] If true, first checks that the user wants auto resizing.
     * @param {string} [size='standard'] Which of the preset sizes should be used. {'standard', 'full', 'small'}
     * Adjusts the width and height of the `_as_dialog` or `_edit_dialog` and centers it.
     * Then, triggers a resize of the internal layout.
     */
    _setDialogSize: function(isAutoResize = false, size = 'standard'){

        if(isAutoResize && !this._autoResizeCheckbox?.is(':checked') || !this._as_dialog && !this._edit_dialog){
            return;
        }

        let width = window.hWin.innerWidth * 0.8;
        let height = window.hWin.innerHeight * 0.8;

        if(size === 'full'){
            width = window.hWin.innerWidth * 0.95;
            height = window.hWin.innerHeight * 0.95;
        }else if(size === 'small'){
            width = window.hWin.innerWidth * 0.75;
            height = window.hWin.innerHeight * 0.75;
        }

        // Update width + height, and reset dialog's position to center
        if(this._as_dialog?.dialog('instance') !== undefined){
            this._as_dialog.dialog('option', 'width', width);
            this._as_dialog.dialog('option', 'height', height);
            this._as_dialog.dialog('option', 'position', {my: 'center', at: 'center', of: window.hWin});
        }else if(this._edit_dialog?.dialog('instance') !== undefined){
            this._edit_dialog.dialog('option', 'width', width);
            this._edit_dialog.dialog('option', 'height', height);
            this._edit_dialog.dialog('option', 'position', {my: 'center', at: 'center', of: window.hWin});
        }

        this.editFormPopup.layout().resizeAll(); // resize layout
    },

    //
    /**
     * @brief Adds a "+" button to tab groups for creating new tabs.
     * @memberof heurist.manageRecords
     * This function is active when the RTS (Record Type Structure) editor is enabled.
     * It appends a special "add new tab" control to each `ul[role="tablist"]` within
     * tabbed groups. Clicking this control triggers an action in the RTS editor
     * (`manageDefRecStructure('addNewSeparator', dt_id)`) to insert a new tab separator.
     */
    _addNewTabButton: function(){

        if(this._currentEditRecTypeID <= 0){
            return;
        }

        let that = this;

        let $tabs = this.editForm.find('div.ui-tabs[data-group-dtid]');

        if($tabs.length > 0){

            $tabs.each(function(idx, group){

                let $group = $(group);
                let $tabs = $group.find('ul[role="tablist"]');
                let last_dtid = $group.find('fieldset.ui-tabs-panel:last-child div[data-dtid]').last().attr('data-dtid');

                let $empty_cont = $('<div>').uniqueId();
                let $new_tab = $('<li>').addClass('add_new_tab').append('<a href="#'+ $empty_cont.attr('id') +'"></a>').appendTo($tabs);

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

                            let dt_id = ui.newTab.find('span[data-dty_ID]').attr('data-dty_ID');

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
	/**
	 * @brief Displays extra record information at the top and bottom of the record editor form.
	 * @memberof heurist.manageRecords
     * This method rearranges and styles several key pieces of record information:
     * - **Top of form**:
     *   - Displays an "admin override" message if the current user is an admin but not the record owner.
     *   - Shows the "Constructed Title" field (rec_Title), making it read-only and adding a gear icon for admins to edit the title mask.
     *   - Moves the "Child record of" field (DT_PARENT_ENTITY), if present and not part of the formal structure, to this top section.
     *   - Moves the "Record URL" field (rec_URL), if set to be shown on the edit form, to this top section.
     *   - If the record was just duplicated, shows a temporary message indicating this.
     * - **Bottom of form**:
     *   - Moves the "Workflow Stage" field (DT_WORKFLOW_STAGE), if present and not part of the formal structure, to a fieldset at the bottom.
     * It creates dedicated fieldsets (`#receditor-top`, `#receditor-bottom`) for these elements.
	 */
    showExtraRecordInfo: function(){

        const that = this;
        const parententity = Number(window.hWin.HAPI4.sysinfo['dbconst']['DT_PARENT_ENTITY']);
        const workflow_stage = Number(window.hWin.HAPI4.sysinfo['dbconst']['DT_WORKFLOW_STAGE']);

        // Move workflow stage dropdown to end of container (above 'Missing fields' help text)
        if(this._swf_rules.length > 0 && $Db.rst(this._currentEditRecTypeID, workflow_stage) === null){
            let $ele = this._editing.getFieldByName(workflow_stage);
            let $bottom_fieldset = $('<fieldset>', {id: 'receditor-bottom'}).insertBefore(this.editForm.find('.optional_hint').prev());

            $bottom_fieldset.append($ele);
        }

        // add new separate fieldset at the start
        let $top_fieldset = $('<fieldset>', {id: 'receditor-top'}).insertBefore(this.editForm.find('fieldset:first'));
        let top_width = $top_fieldset.parents('.editForm.recordEditor').width();
        top_width -= 130;

        $top_fieldset.css('min-width', top_width + 'px');

        const admin_override = window.hWin.HAPI4.is_admin() && !window.hWin.HAPI4.is_member(that._getField('rec_OwnerUGrpID'));

        if(admin_override){ // check if user is not owner, but is admin 

            $top_fieldset.append(
                '<div style="display: table; margin-bottom: 7px">'
                    + '<div class="header" style="vertical-align: top; display: table-cell; font-size: 1.3em; font-weight: bold; width: auto;">'
                        + '<span style="color: red; font-weight: bold;">You don\'t own this record</span> (you can edit it because you are a database administrator)'
                    + '</div>'
                + '</div>');
        }

        let title_maxwidth = ($top_fieldset.css('min-width') != 0) ? $top_fieldset.css('min-width') : $top_fieldset.parent().width();
        title_maxwidth = parseFloat(title_maxwidth) * 0.9 - (this.options.rts_editor ? 60 : 50);

        // Display record title
        let $title_field = this._editing.getFieldByName('rec_Title');
        if($title_field){
            $title_field = $title_field.show().editing_input('setDisabled', true);

            // remove opacity change and set background to lighter background
            let cur_styling = $title_field.find('input').attr('style');
            let cur_title = this._getField('rec_Title');
            let empty_title = window.hWin.HEURIST4.util.isempty(cur_title);

            cur_title = empty_title ? '&lt;not yet set&gt;'
                            : cur_title.replace(/[\r\n]+/g, ' ');

            cur_title = empty_title ? cur_title : window.hWin.HEURIST4.util.stripTags(cur_title,'u, i, b, strong, em');
            const cur_title_title = window.hWin.HEURIST4.util.stripTags(cur_title, false); //remove all tags

            $title_field.find('input')
                        .replaceWith(`<div style="${cur_styling}background-color:#e3f0f0!important;font-size:13px;padding:3px;max-width:${title_maxwidth}px;width:${title_maxwidth}px;cursor:default;"`
                            + ` class="truncate" title="${cur_title_title}">${cur_title}</div>`);

            // change label to required version, and add help icon
            $title_field.find('div.header')
                        .attr('title', 'A title constructed from one or more fields, which is used to identify records when displayed in search results.')
                        .addClass('recommended')
                        .css('vertical-align', '');

            $title_field.find('div.header > label').text('Constructed title');

        }
        
        // add gear icon that opens title mask editor
        if(window.hWin.HAPI4.is_admin() && this.options.allowAdminToolbar!==false){

            let $gear_icon = $('<span>')
                              .addClass('ui-icon ui-icon-gear')
                              .css({'color': 'rgb(125, 154, 170)', 'min-width': '22px', 'cursor': 'pointer'})
                              .attr('title', 'Open Title Mask Editor')
                              .on('click', function(e) { that.editRecordTypeTitle(); });
            if($title_field){
                $title_field.find('span.editint-inout-repeat-button').find('ui-icon').remove(); // remove repeat button
                $title_field.find('span.editint-inout-repeat-button').append($gear_icon); // add gear icon (edit title mask)
                $title_field.find('span.btn_input_clear').remove(); // remove clear button
            }
        }

        // move rec_title field to new fieldset
        $top_fieldset.append($title_field);

        // check for child record field, move to new fieldset if any
        let $childrec_field = this.editForm.find('div[data-dtid="'+parententity+'"]');
        if($childrec_field.length == 1 && !$Db.rst(this._currentEditRecTypeID, parententity)){

            // Header changes
            $childrec_field.find('div.header').css({'font-size': '12px'}).addClass('recommended');

            // Append to top
            $top_fieldset.append($childrec_field);
        }

        // check for url field, move to new fieldset if set to display
        let $url_field = this._editing.getFieldByName('rec_URL');
        if($url_field?.length == 1){

            $url_field.find('div.header').css({'font-size': '12px'}).addClass('recommended');

            // Append to top
            $top_fieldset.append($url_field);
        }

        if(this._duplicatedRecord && this._duplicatedRecord > 0){ // add message about being a dupe record

            let $dupe_ele = $('<div>', {style: 'font-size: 1.5em; color: red; cursor: default; padding-left: 35px;'})
                .text(`This is a duplicate of record ${this._duplicatedRecord} - edit as required`);

            // Small button to remove dupe message
            $('<span>', {class: 'smallbutton ui-icon ui-icon-circlesmall-close', style: 'cursor: pointer', title: 'Remove message'})
                .appendTo($dupe_ele);

            $top_fieldset.append($dupe_ele);

            this._on($dupe_ele.find('span'), {
                click: (event) => {

                    this._off($(event.target), 'click'); // remove click event

                    $(event.target).parent().remove(); // remove element
                }
            })

            this._duplicatedRecord = false;
        }
    },
	
    /**
     * @brief Handles ambiguous date entries by presenting a dialog to the user for clarification.
     * @memberof heurist.manageRecords
     * @param {object[]} ambiguous_dates An array of objects, each representing an ambiguous date encountered during validation.
     * Each object contains `dtyid`, `org_value` (original input), `index` (if multi-value field), and `type` (simple, approx, carbon, range).
     * Iteratively shows a dialog for each ambiguous date, allowing the user to:
     * - Re-enter the date in a standard format (YYYY-MM-DD).
     * - Specify if it's approximate (circa) or a B.P. (radiocarbon) date.
     * - For ranges, clarify the start (TPQ) and end (TAQ) dates.
     * - Add a comment to the temporal value.
     * Upon saving from the dialog, the clarified date (potentially as a Temporal string) updates the corresponding field value.
     * Continues until all ambiguous dates are processed or the user cancels.
     */
    _handleAmbiguousDates: function(ambiguous_dates){

        let that = this;

        if(!ambiguous_dates || ambiguous_dates.length == 0){
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
                case 'carbon':
                {    
                    let date = $dlg.find('#DAT').val();
                    let approx = $dlg.find('#CIR').is(':checked');
                    let is_carbon = $dlg.find('#BP').is(':checked');

                    if(window.hWin.HEURIST4.util.isempty(date)){
                        value = '';
                    }else if(approx){

                        t_date.setType('s');
                        t_date.addObjForString('DAT', date);
                        t_date.addObjForString("CIR", "1");

                        value = t_date.toString();
                    }else if(is_carbon){

                        t_date.setType('c');
                        t_date.addObjForString('BPD', date);

                        value = t_date.toString(); // toJSON
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
                }
                case 'range':
                {
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
                }
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

            let ttype = cur_date.type;
            let date_help = 'Please re-type '+ (ttype == 'range' ? 'all dates' : '') +'as year only, yyyy-mm or yyyy-mm-dd ';

            switch (ttype) {
                case 'simple':
                case 'approx':
                case 'carbon':
                {
                    let is_checked = ttype == 'approx' ? 'checked="checked"' : '';
                   

                    content += `<label> ${date_help} <input type="text" id="DAT"></label><br>`;

                    if(ttype == 'carbon'){
                        content += '<label> Is a radiometric date? (Before Present (1950) Date) <input type="checkbox" id="BP" checked="checked"></label><br>';
                       
                    }else{
                        content += '<label> Is approximate? <input type="checkbox" id="CIR" '+ is_checked +'></label><br>';
                    }

                    break;
                }
                case 'range':
                {
                    let early = cur_date.value.TPQ ? cur_date.value.TPQ : '';
                    let late = cur_date.value.TAQ ? cur_date.value.TAQ : '';
                    content += `<span> ${date_help} </span><br>`;
                    content += '<span>This date was determined to be a simple range. However, if this is an error leave either field empty</span><br><br>';
                    content += '<span style="display:inline-block; min-widht:100px;">Earliest estimate</span> <input type="text" id="TPQ" value="' + early + '"></label><br><br>';
                    content += '<label><span style="display:inline-block; min-widht:100px;">Latest estimate</span> <input type="text" id="TAQ" value="' + late + '"></label>';

                    break;
                }
                default:
                    that._handleAmbiguousDates(ambiguous_dates);
                    break;
            }
        }

        content += '<br><br><span style="vertical-align: top;">If you wish to qualify the date with a short note, enter it here </span>'
                +  '<textarea cols="30" rows="5" id="COM" style="resize: none"></textarea>'
                +  '</div>';

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(content, btns, labels, options);
    },

    _handleInvalidEntry: function(invalid_entries){

        if(!invalid_entries || invalid_entries.length == 0){
            return;
        }

        let msg = '';
        let value_style = 'display: inline-block; width: 150px; max-width: 150px;';

        for(const dty_ID in invalid_entries){ // Iterate through fields with invalid entries

            const fld_Name = $Db.rst(this._currentEditRecTypeID, dty_ID, 'rst_DisplayName');
            let values = invalid_entries[dty_ID];

            msg += `<div style="padding-bottom: 5px;"><strong>${fld_Name}</strong>:</div>`;

            for(const value of values){
                msg += `<span class="truncate" title="${value.value}" style="${value_style}">${value.value}</span>&nbsp;&nbsp;&rarr;&nbsp;&nbsp;
                <span title="${value.error}">${value.error}</span><br>`;
            }

            msg += '<hr style="margin: 15px 0px;">';
        }

        window.hWin.HEURIST4.msg.showMsgDlg(msg, null, {title: 'Invalid values for entry masks'}, {default_palette_class: 'ui-heurist-populte'});
    },

    _showSwfPopup: function(fields, _callback){

        const that = this;

        let swf_mode = this.element.find('.sel_workflow_stages').val();
        let opts_swf_stages = '';
        const dtyID = window.hWin.HAPI4.sysinfo['dbconst']['DT_WORKFLOW_STAGE']; //$Db.getLocalID('dty', '2-1080'); //workflow stage field

        if(!fields){
            fields = this._editing.getValues(false);
        }

        let curr_stage = fields[dtyID];

        //TRM_SWF_IMPORT should we disable it?

        for (let i = 0; i < this._swf_rules.length; i++){
            let is_disabled = '';
            if(this._swf_rules[i]['swf_StageRestrictedTo']){
                const grps = this._swf_rules[i]['swf_StageRestrictedTo'];
                if(!window.hWin.HAPI4.is_member(grps)){
                    is_disabled = ' disabled';
                }
            }
            const is_selected = (this._swf_rules[i]['swf_Stage']==curr_stage)?' selected':'';
            
            opts_swf_stages = opts_swf_stages 
                + '<option value="'+this._swf_rules[i]['swf_Stage']+'"'
                +is_disabled+is_selected+'>'
                +window.hWin.HEURIST4.util.htmlEscape($Db.trm(this._swf_rules[i]['swf_Stage'],'trm_Label'))
                +'</option>';
        }

        let $ele = this.element.find('.div_workflow_stages');

        let $dlg;
        let btns = {};

        btns[window.HR('Continue')] = function(){ // "Continue" button handler

            fields[dtyID] = $dlg.find('.sel_current_stage').val(); // Get selected stage
            that._saveEditAndClose( fields, _callback ); // Proceed with saving

            $dlg.dialog('close');
        };
        btns[window.HR('Cancel')] = function(){ // "Cancel" button handler
            $dlg.dialog('close');
        };

        // Show dialog
        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
        '<div class="heurist-helper3">Changing this setting will determine actions to be taken such as visibility settings, marking for publication or email notifications</div>'
        +'<p style="margin: 20px 0px 0px"><label>Workflow stage: </label><select class="text ui-corner-all sel_current_stage">'
            + opts_swf_stages // Options for stages
        +'</select>&nbsp;&nbsp;<button id="btn_advance">Advance</button></p>', btns, 
        {title: window.hWin.HR('Set workflow stage'), yes: window.hWin.HR('Continue'), no: window.hWin.HR('Cancel')},
        {default_palette_class: this.options.default_palette_class});

        if($ele.length == 1){ // If existing workflow stage UI elements are present in the main form
            $ele.clone(true, true) // Clone them into the dialog
                .insertAfter($dlg.find('div'));

            $dlg.find('button#show_workflow_stages').remove(); // Remove redundant "show" button from cloned UI

            $dlg.find('.sel_workflow_stages') // Style and bind change event for the "popup mode" selector
                .css({
                    display: 'inline-block',
                    margin: '10px 0px 0px'
                })
                .val(swf_mode);
            $dlg.find('.sel_workflow_stages').on('change', function(){
                swf_mode = $dlg.find('.sel_workflow_stages').val();
                that.element.find('.sel_workflow_stages').val(swf_mode).trigger('change'); // Sync with main form
            });
        }

        let $swf_popup_mode_selector = $dlg.find('.sel_workflow_stages'); // Selector for "New records only", "New and existing", "OFF"
        let $stage_selector = $dlg.find('.sel_current_stage'); // Selector for actual workflow stages

        // "Advance" button to select next stage
        $dlg.find('#btn_advance').button({icon:'ui-icon-caret-1-e',iconPosition:'end'})
                .css('font-size','0.9em')
        .on('click', function(){
            $stage_selector[0].selectedIndex++;
            if($stage_selector[0].selectedIndex < 0){
                $stage_selector[0].selectedIndex = 0;
            }
            $stage_selector.trigger('change');
        });

        $dlg.parent().find('.ui-dialog-buttonpane .ui-button').css('margin-right', '20px');

        // Change "Continue" button label to "Save change" if a stage or mode is actually changed
        let initial_popup_mode = $swf_popup_mode_selector.val();
        let initial_stage = $stage_selector.val();
        let $save_btn = $($dlg.parent().find('.ui-dialog-buttonpane .ui-button')[0]); // The "Continue" button

        $dlg.find('select').on('change', () => {
            let changed = $stage_selector.val() != initial_stage || $swf_popup_mode_selector.val() != initial_popup_mode;
            $save_btn.button('option', 'label', window.hWin.HR(changed ? 'Save change' : 'Continue'));
        });
    },
	
    /**
     * @brief Sets up buttons for external lookup services at the top of the record editor.
     * @memberof heurist.manageRecords
     * Reads service configurations from `window.hWin.HAPI4.sysinfo['service_config']`.
     * For each configured service relevant to the current record type (`cfg.rty_ID`),
     * it creates a button. Clicking a service button triggers its respective lookup dialog
     * (e.g., `lookupTLC`, `lookupBnFLibrary_bib`).
     * Also adds a gear icon to configure these lookup services.
     * If no relevant services are found, the lookup area is hidden.
     */
    _setupExternalLookups: function(){

        let that = this;

        let notfound = true;
        let lookup_div = this.element.find('.btn-lookup-values');
        lookup_div.empty();
        let service_config = window.hWin.HEURIST4.util.isJSON(window.hWin.HAPI4.sysinfo['service_config']);

        if(service_config!==false){
            
            window.hWin.HAPI4.sysinfo['service_config'] = service_config;
            
            let lbl_text = "Lookup External Sources: ";

            let $ext_lookup_cont = $('<div>')
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

            //creates button for every lookup service    
            for(let srvname in service_config){

                let cfg = service_config[srvname];    
                
                if(cfg.rty_ID == this._currentEditRecTypeID){
                    notfound = false;                    
                    
                    let btn = $('<div>')
                        .button({label:cfg.label?cfg.label:('Lookup '+cfg.service) })
                        .attr('data-cfg', srvname).css({'font-size': 'inherit', // 'padding-right':'4px',
                            border: '1px solid', 'font-weight': 'bold', 'margin-right': '5px'})
                        .appendTo($ext_lookup_cont);
                    
                    this._on(btn, {click:
                        function(event){ 
                            
                            let srvname = $(event.target).attr('data-cfg');

                            let cfg = window.hWin.HAPI4.sysinfo['service_config'][srvname];
                            let dialog_name = cfg.dialog;
                            let service_name = cfg.service;

                            if(dialog_name == 'recordLookup' || dialog_name == 'lookupTCL'){
                                dialog_name = 'lookupTLC';
                            }else if(dialog_name == 'recordLookupBnFLibrary' || dialog_name == 'lookupBnFLibrary'){
                                dialog_name = 'lookupBnFLibrary_bib';
                            }else if(dialog_name.includes('recordLookup')){
                                dialog_name = dialog_name.replace('recordLookup', 'lookup');
                            }

                            let dlg_opts = { 
                                mapping: cfg, 
                                edit_fields: this._editing.getValues(true),
                                edit_record: this._currentEditRecordset,
                                path: 'widgets/lookup/',
                                onClose:function(recset){
                                    that._handleLookupResponse(recset, srvname);
                                }
                            };

                            if(service_name == 'ESTC_editions' || service_name == 'ESTC_works' || service_name == 'ESTC'){
                                this._handleESTCLookup(dialog_name, dlg_opts);
                            }else{
                                this._loadParentLookup(dialog_name, dlg_opts)
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
    /**
     * @brief Handles the response from an external lookup service dialog.
     * @memberof heurist.manageRecords
     * @param {?object|HRecordSet} recset The data returned from the lookup. This can be an HRecordSet or a plain object mapping field IDs to values.
     * @param {string} service The name of the service that returned the data (key from `service_config`).
     * This method processes the `recset` to populate fields in the current record's edit form.
     * - If `recset` is an `HRecordSet`, it maps fields based on `cfg.fields`.
     * - If `recset` is an object, it iterates through its keys (DetailTypeIDs).
     *   - For 'resource', 'enum', 'relmarker' types, it prepares values for further processing (e.g., term creation, resource selection).
     *   - For 'file' types, it collects URLs for remote file registration.
     *   - For other types, it directly sets field values.
     *   - Handles special cases like dumping BnF record XML to scratchpad.
     * After initial processing, it calls `processTermFields` to handle any pending term creations/selections.
     */
    _handleLookupResponse: function(recset, service){

        let that = this;
        const cfg = window.hWin.HAPI4.sysinfo['service_config'][service];

        if(!recset || window.hWin.HEURIST4.util.isempty(recset)){
            return;
        }

        if( window.hWin.HEURIST4.util.isRecordSet(recset) ){
        
            let rec = recset.getFirstRecord();
            // loop all fields in selected values
            // find field in edit form
            // assign value
            let fields = recset.getFields();
            for(let k=2; k<fields.length; k++){
                const dt_id = cfg.fields[fields[k]];
                if(dt_id>0)
                {
                    let newval = recset.fld(rec, fields[k]);
                    newval = window.hWin.HEURIST4.util.isnull(newval)?'':newval;
                    that._editing.setFieldValueByName( dt_id, newval );
                }
            }
        }else{
            //lookup dialog returns pairs - dtyID=>value
            let dtyIds = Object.keys(recset);

            let assigned_fields = []; // list of fields assigned

            that.lookup_record_link = null; // link to records from current lookup
            that.term_values = []; // list of label values for enum/term fields
            that.file_values = {}; // list of external urls for file fields
            that.resource_values = []; // list of search values for recpointer fields
            that.relmarker_values = []; // list of search values and term values for relationship marker fields

            if(!window.hWin.HEURIST4.util.isempty(recset['ext_url'])){
                that.lookup_record_link = {url: recset['ext_url'], type: 'ext'};
            }else if(!window.hWin.HEURIST4.util.isempty(recset['heurist_url'])){
                that.lookup_record_link = {url: recset['heurist_url'], type: 'heurist'};
            }else{
                that.lookup_record_link = null;
            }

            for(let k=0; k<dtyIds.length; k++){

                const dt_id = dtyIds[k];

                if(dt_id>0){

                    let newval = recset[dt_id];
                    let type = $Db.dty(dt_id, 'dty_Type');

                    if(type == 'resource' || type == 'enum' || type == 'relmarker'){

                        let completed = []; // completed recpointers/terms

                        if(!Array.isArray(newval)){
                            newval = [newval];
                        }

                        for(let i = 0; i < newval.length; i++){

                            // needs additional handling
                            if(type == 'resource'){
                                that.resource_values.push({fld_id: dt_id, values: newval[i]});
                            }else if(type == 'relmarker'){
                                that.relmarker_values.push({fld_id: dt_id, values: newval[i]});
                            }else{

                                let vocab_id = $Db.dty(dt_id, 'dty_JsonTermIDTree');

                                if(Number.isInteger(+newval[i]) && $Db.trm_InVocab(vocab_id, newval[i])){ // check if 'id' is in vocabulary
                                    completed.push(newval[i]);
                                    continue;
                                }

                                if(Array.isArray(newval[i])){
                                    for(let j = 0; j < newval[i].length; j++){

                                        let label = window.hWin.HEURIST4.util.isObject(newval[i][j]) ? newval[i][j]['label'] : newval[i][j];
                                        let trm_id = $Db.getTermByLabel(vocab_id, label);

                                        if(trm_id == null){
                                            that.term_values.push([dt_id, newval[i][j]]);
                                        }else{
                                            completed.push(trm_id);
                                        }
                                    }
                                }else{

                                    let label = window.hWin.HEURIST4.util.isObject(newval[i]) ? newval[i]['label'] : newval[i];
                                    let trm_id = $Db.getTermByLabel(vocab_id, label);

                                    if(trm_id == null){
                                        that.term_values.push([dt_id, newval[i]]);
                                    }else{
                                        completed.push(trm_id);
                                    }
                                }
                            }
                        }

                        if(completed.length > 0){
                            that._editing.setFieldValueByName(dt_id, completed);

                            const fieldname = $Db.rst(that._currentEditRecTypeID, dt_id, 'rst_DisplayName');
                            if(!assigned_fields.includes(fieldname)) { assigned_fields.push(fieldname); }
                        }
                    }else if(type == 'file'){ // need to create new remote file records

                        if(!Object.hasOwn(that.file_values, dt_id)){
                            that.file_values[dt_id] = [];
                        }
                        if(Array.isArray(newval)){
                            that.file_values[dt_id] = that.file_values[dt_id].concat(newval);
                        }else{
                            that.file_values[dt_id].push(newval);
                        }
                    }else{
                        if(!Array.isArray(newval)){
                            newval = window.hWin.HEURIST4.util.isnull(newval)?'':newval;
                            newval = [newval];
                        }

                        that._editing.setFieldValueByName( dt_id, newval );

                        const fieldname = $Db.rst(that._currentEditRecTypeID, dt_id, 'rst_DisplayName');
                        if(!assigned_fields.includes(fieldname)) { assigned_fields.push(fieldname); }
                    } 
                }else if(dt_id == 'BnF_ID' && cfg.options.dump_record == true){ // retrieve record from BnF and place in record scratch pad

                    let value = recset['BnF_ID'];

                    if(window.hWin.HEURIST4.util.isempty(value)){ // missing | no value
                        continue;
                    }

                    let req_url = 'https://catalogue.bnf.fr/api/SRU?version=1.2&operation=searchRetrieve&recordSchema=unimarcxchange&maximumRecords=1&startRecord=1&query=(';                                                        
                    let fld_name = cfg.service == 'bnfLibrary' ? 'bib.recordid' : 'aut.recordid';

                    req_url += encodeURIComponent(fld_name + ' all ' + value) + ')';

                    let req = {
                        service: req_url,
                        serviceType: 'bnf_recdump'
                    };

                    window.hWin.HAPI4.RecordMgr.lookupService(req, (response) => {
                        if(window.hWin.HEURIST4.util.isJSON(response)){
                            response = window.hWin.HEURIST4.util.isJSON(response);
                            if(response.record != null){

                                let scratchpad_txt = response.record + '\r\n\r\n';

                                let fld_id = cfg.options.dump_field;
                                if(isNaN(parseInt(fld_id)) || fld_id < 1 || !$Db.rst(that._currentEditRecTypeID, fld_id) || $Db.dty(fld_id, 'dty_Type') != 'blocktext'){
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

            that.processTermFields(assigned_fields, {}); // order of operations is: Terms, Files, Record pointers, Relationship markers
        }
    },
	
    /**
     * @brief Processes term (enum/vocabulary) field values obtained from external lookups.
     * @memberof heurist.manageRecords
     * @param {string[]} completed_fields An array of field display names that have already been successfully populated. This array is updated by this method.
     * @param {object} new_terms An object to accumulate new term IDs to be set on fields. Keys are DetailTypeIDs, values are arrays of term IDs.
     * This method iteratively processes `that.term_values` (populated by `_handleLookupResponse`).
     * For each term value that isn't a direct term ID:
     * - It checks if a term with the given label (or details) already exists in the target vocabulary.
     * - If not, it presents a dialog (`handle-terms`) to the user, allowing them to:
     *   - Select an existing term from the vocabulary.
     *   - Create a new term with the provided label, description, code, and URI.
     *   - Optionally import translations if provided by the lookup service.
     * - Once a term ID is resolved (either existing or newly created), it's added to `new_terms`.
     * - After all terms in `that.term_values` are processed, it updates the corresponding fields in the edit form
     *   with the collected term IDs. If new terms were created, it may refresh the local term cache (`defTerms`).
     * Finally, it calls `processFileFields` to continue with file field processing.
     */
    processTermFields: function(completed_fields, new_terms){

        let that = this;

        if(new_terms == null) { new_terms = {}; }

        if(that.term_values.length == 0 && Object.keys(new_terms).length == 0){ // no terms to handle
            this.processFileFields(completed_fields);
            return;
        }else if(that.term_values.length == 0){ // all terms handled

            function passBackToFields(){

                for(let fld_id in new_terms){ // pass term ids to respective fields

                    let values = new_terms[fld_id];
                    values = Array.isArray(values) ? values : [values];
                    values = values.filter(n => !window.hWin.HEURIST4.util.isempty(n));

                    if(values.length == 0){
                        continue;
                    }

                    that._editing.setFieldValueByName(fld_id, values);

                    let fieldname = $Db.rst(that._currentEditRecTypeID, fld_id, 'rst_DisplayName');
                    if(!completed_fields.includes(fieldname)) { completed_fields.push(fieldname); }
                }
                that.processFileFields(completed_fields);
            }

            if(new_terms['refresh']){ // check whether local cache needs updating

                delete new_terms['refresh'];

                window.hWin.HAPI4.EntityMgr.refreshEntityData(['defTerms'], function(success){

                    if(success){
                        passBackToFields();
                    }
                });
            }else{ // no cache updating needed
                passBackToFields();
            }

            return;
        }

        let cur_term = this.term_values.shift(); // 0 => dt_id, 1 => term label/term details

        const dtyID = cur_term[0];
        let trm_details = cur_term[1];

        if(!window.hWin.HEURIST4.util.isObject(trm_details)){
            trm_details = {
                label: cur_term[1],
                desc: '',
                code: '',
                uri: '',
                translations: []
            };
        }

        let org_label = trm_details['label'];

        let vocab_id = $Db.dty(dtyID, 'dty_JsonTermIDTree');
        let field_name = $Db.rst(this._currentEditRecTypeID, dtyID, 'rst_DisplayName');

        // add current field (dt_id) to new_terms, and retain any existing values
        if(!Object.hasOwn(new_terms, dtyID)){
            let existing_val = this._editing.getValue(dtyID);
            new_terms[dtyID] = window.hWin.HEURIST4.util.isempty(existing_val[0]) ? [] : existing_val;
        }

        let $dlg;

        // Term Dlg - Content
        let msg = 'Use an existing term: <select id="existing_term"></select><br>'
                + '<div style="margin: 10px 25px;">OR</div>'
                + `Create a new term for field: ${field_name} (vocab: ${$Db.trm(vocab_id, 'trm_Label')})<br><br>`;

        let lbl_len = org_label.length < 20 ? 20 : org_label.length;
        lbl_len = lbl_len > 50 ? 50 : lbl_len;
        let code_len = trm_details['code'].length < 20 ? 20 : trm_details['code'].length;
        code_len = code_len > 50 ? 50 : code_len;
        let uri_len = trm_details['uri'].length < 20 ? 20 : trm_details['uri'].length;
        uri_len = uri_len > 50 ? 50 : uri_len;

        msg += '<fieldset>'
            + '<div>'
                + `<div class="header"><label>Label: </label></div> <input type="text" id="new_term_label" value="${org_label}" size="${lbl_len}">`
            + '</div><br>'
            + '<div>'
                + `<div class="header" style="vertical-align: top;"><label>Description: </label></div> <textarea cols="50" rows="2" id="new_term_desc">${trm_details['desc']}</textarea>`
            + '</div><br>'
            + '<div>'
                + `<div class="header"><label>Code: </label></div> <input type="text" id="new_term_code" value="${trm_details['code']}" size="${code_len}">`
            + '</div><br>'
            + '<div>'
                + `<div class="header"><label>Semantic URI: </label></div> <input type="text" id="new_term_uri" value="${trm_details['uri']}" size="${uri_len}">`
            + '</div>'
        + '</fieldset>';

        if(trm_details['translations'] && Object.keys(trm_details['translations']).length > 0){
            msg += '<br><label>Import label translations from source? <input type="checkbox" id="import_translations" checked="checked"></label><br>';
        }

        msg += '<br>Please correct the above details, as required, before clicking Insert new term';

        // Term Dlg - Button
        let btn = {};
        btn['Insert new term'] = function(){

            let new_label = $dlg.find('input#new_term_label').val();
            let trm_ID = $Db.getTermByLabel(vocab_id, new_label);

            if(trm_ID > 0){

                new_terms[dtyID].push(trm_ID);
                $dlg.dialog('close');

                that.processTermFields(completed_fields, new_terms);

                return;
            }

            let new_record = {
                trm_ID: -1,
                trm_ParentTermID: vocab_id
            };
            let labels = new_label;

            if(Object.keys(trm_details['translations']).length > 0 && $dlg.find('#import_translations').is(':checked')){
                labels = [labels, ...Object.values(trm_details['translations'])];
            }

            new_record['trm_Label'] = labels;

            new_record['trm_Description'] =  $dlg.find('#new_term_desc').text();
            new_record['trm_Code'] =  $dlg.find('#new_term_code').val();
            new_record['trm_SemanticReferenceURL'] =  $dlg.find('#new_term_uri').val();

            let request = {
                a: 'save',
                entity: 'defTerms',
                request_id: window.hWin.HEURIST4.util.random(),
                fields: new_record,
                isfull: 0
            };

            window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){

                if(response.status == window.hWin.ResponseStatus.OK){

                    new_terms[dtyID].push(response.data[0]); // response.data[0] == new term id

                    $dlg.dialog('close');

                    new_terms['refresh'] = true;
                    that.processTermFields(completed_fields, new_terms);
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
        };
        btn['Skip'] = function(){
            $dlg.dialog('close');
            that.processTermFields(completed_fields, new_terms);
        };

        // Create dlg
        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btn, {title: 'Unknown term', yes: 'Insert new term', no: 'Skip'}, {default_palette_class: 'ui-heurist-design', dialogId: 'handle-terms'});

        $dlg.find('.header').css({
            width: '85px', 
            'min-width': '85px'
        });

        window.hWin.HEURIST4.ui.createTermSelect($dlg.find('#existing_term')[0], {vocab_id: vocab_id, topOptions: 'select a term...', useHtmlSelect: false, eventHandlers: {
            onSelectMenu: function(){
                let $sel = $dlg.find('#existing_term');

                if($sel.val() !== ''){

                    let trm_label = $Db.trm($sel.val(), 'trm_Label');

                    window.hWin.HEURIST4.msg.showMsgDlg(`Are you sure you wish to use ${trm_label} in place of ${org_label}?`, 
                    function(){
                        new_terms[dtyID].push($sel.val());
                        $dlg.dialog('close');
                        that.processTermFields(completed_fields, new_terms);
                    }, {title: 'Use existing term'}, {default_palette_class: 'ui-heurist-design'});
                }
            }
        }});
    },

	//
    // Process file field values from External Lookups, handles remote resources only
    /**
     * @brief Processes file field values obtained from external lookups, specifically for remote files.
     * @memberof heurist.manageRecords
     * @param {string[]} completed_fields An array of field display names that have already been successfully populated. This array is updated by this method.
     * This method takes file URLs stored in `that.file_values` (populated by `_handleLookupResponse`).
     * It makes a batch request to the server (`regExternalFiles` action on `recUploadedFiles` entity)
     * to register these external URLs as Heurist file records.
     * - On success, it updates the corresponding file fields in the edit form with the new file record IDs/details.
     * - If any errors occur during registration (invalid URL, save error), it displays them to the user.
     * Finally, it calls `processResourceFields` to handle record pointer and relationship marker fields.
     */
    processFileFields: function(completed_fields){

        let that = this;

        if(Object.keys(this.file_values).length == 0){
            this.processResourceFields(completed_fields);
            return;
        }

        let request = {
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
    // Process record pointer and relationship fields values from External Lookups, that aren't integers (ids)
    /**
     * @brief Processes record pointer and relationship marker field values obtained from external lookups.
     * @memberof heurist.manageRecords
     * @param {string[]} completed_fields An array of field display names that have already been successfully populated.
     * This method handles values stored in `that.resource_values` (for record pointers) and
     * `that.relmarker_values` (for relationship markers), which were populated by `_handleLookupResponse`.
     * It presents a dialog (`lookup_RecPointers`) to the user listing the fields and their proposed string values
     * that need to be resolved into actual Heurist record IDs.
     * - For each listed item, the user can click to open a record selection dialog (`showEntityDialog('records')`),
     *   pre-filtered with the search string from the lookup.
     * - The user selects or creates the target record.
     * - For record pointers, the selected record ID is directly assigned to the field.
     * - For relationship markers, it additionally captures the relationship type (if provided by the lookup)
     *   and uses the `editing_input('setup_Relmarker_Target')` mechanism to create the relationship.
     * Once all items are processed or the user closes the dialog, it displays a summary of successfully mapped fields.
     */
    processResourceFields: function(completed_fields){

        let that = this;

        // Misc, styling for 'table cells'
        const field_style = 'display: table-cell;padding: 7px 3px;max-width: 125px;min-width: 125px;';
        const val_style = 'display: table-cell;padding: 7px 3px;max-width: 300px;min-width: 300px;';

        // Construct Dialog for user handling of record related fields
        let $dlg;
        let completed = '';

        // Add already assigned fields
        if(completed_fields.length > 0){
            completed = 'The following fields have been inserted:<br><ul style="list-style: none;">';

            for(let j = 0; j < completed_fields.length; j++){
                completed += '<li>' + completed_fields[j] + '</li>';
            }

            completed += '</ul><br>';
        }

        const hasValue = (that.resource_values != null && that.resource_values.length > 0) || (that.relmarker_values != null && that.relmarker_values.length > 0);

        if(!hasValue){

            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(completed, null, 
                {title: 'Field mapping completed', ok: 'Close'}, 
                {default_palette_class: 'ui-heurist-populate', position: {my: "right-50 top+45", at: "right top", of: this._getEditDialog()}}
            );

            setTimeout(function(){ $dlg.dialog('close'); }, 2000);

            return;
        }

        const type = (that.resource_values != null && that.resource_values.length > 0) ? 'resource' : 'relmarker';

        function __nextProcess(){

            $dlg.dialog('close');

            if(type=='resource'){
                that.resource_values = null;
            }else{
                that.relmarker_values = null;
            }

            that.processResourceFields(completed_fields);
        }

        // Control variables
        let field_values = {};
        let todo_count = 0;
        let complete_count = 0;

        let msg = completed;
        let btn = {};

        if(that.lookup_record_link !== null){ // external link to lookup record
            msg = '<a href="' + that.lookup_record_link['url'] + '" target="_blank">View external record <span style="font-size:10px;" class="ui-icon ui-icon-extlink" ></span></a><br><br>' + msg;
        }

        // Dlg - Main Content
        msg += 'Values remaining to be attributed, click a row to assign a value'
            + '<br><br>'
            + '<div id="wrapper" style="max-height: 250px;overflow: auto;">'
            + '<div style="display: table">'
            + '<div style="display: table-row">'
                + '<div style="'+ field_style +'">Field</div>'
                + '<div style="'+ val_style +'">Value</div>'
                + (type == 'resource' ? '' : '<div style="'+ field_style +'">Relation</div>')
            + '</div>';

        // Add details
        if(type == 'resource'){

            // Add each row of record pointers
            for(const cur_details of that.resource_values){

                let fld_id = cur_details['fld_id'];
                let value = cur_details['values'];
                let search = value;

                if(window.hWin.HEURIST4.util.isObject(value)){
                    search = value['search'];
                    value = value['value'];
                }

                let field_name = $Db.rst(this._currentEditRecTypeID, fld_id, 'rst_DisplayName');

                msg += '<div style="display: table-row;color: black;opacity: 1;" class="recordDiv" data-value="" '
                            +'data-dtid="'+ fld_id +'" data-index="'+ todo_count +'" data-search="'+ search +'">'
                        + '<div style="'+ field_style +'" class="truncate" title="'+ field_name +'">'+ field_name +'</div>'
                        + '<div style="'+ val_style +'" class="truncate" title="'+ value +'">'+ value +'</div>'
                    + '</div>';

                let existing_val = this._editing.getValue(fld_id);
                field_values[fld_id] = window.hWin.HEURIST4.util.isempty(existing_val) ? [] : [existing_val];

                todo_count ++;
            }

        }else{

            // Add each row of relationship markers
            for(const cur_details of that.relmarker_values){

                let fld_id = cur_details['fld_id'];
                let value = cur_details['values'];
                let search = value;
                let relation = 'none provided';

                if(window.hWin.HEURIST4.util.isObject(value)){
                    search = window.hWin.HEURIST4.util.isempty(value['search']) ? value['value'] : value['search'];
                    relation = window.hWin.HEURIST4.util.isempty(value['relation']) ? relation : value['relation'];
                    value = value['value'];
                }

                let field_name = $Db.rst(this._currentEditRecTypeID, fld_id, 'rst_DisplayName');

                msg += '<div style="display: table-row;color: black;opacity: 1;" class="recordDiv" data-value="" '
                            +'data-dtid="'+ fld_id +'" data-index="'+ todo_count +'" data-search="'+ search +'" data-rel="'+ relation +'">'
                        + '<div style="'+ field_style +'" class="truncate" title="'+ field_name +'">'+ field_name +'</div>'
                        + '<div style="'+ val_style +'" class="truncate" title="'+ value +'">'+ value +'</div>'
                        + '<div style="'+ field_style +'" class="truncate" title="'+ relation +'">'+ relation +'</div>'
                    + '</div>';

                todo_count ++;
            }

        }

        if(todo_count == 0){
            __nextProcess();
            return;
        }

        msg += '</div></div>'; // close div.table and div#wrapper

        // Dlg - Close button
        btn['Close'] = function(){
            // Close dlg
            if(complete_count < todo_count){
                window.hWin.HEURIST4.msg.showMsgDlg(`${todo_count - complete_count} ${type=='resource' ? 'record pointer' : 'relationship marker'} field values have not been inserted.<br>Do you want to omit them?`, 
                    __nextProcess, {title: 'Task incomplete'}, {default_palette_class: 'ui-heurist-populate'});
            }else{
                __nextProcess();
            }
        }

        // Create dlg
        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btn, {title: `${type=='resource' ? 'Record pointer' : 'Relationship marker'} fields to set`, yes: 'Close'}, {
            default_palette_class: 'ui-heurist-populate', 
            modal: true, 
            dialogId: 'lookup_RecPointers', 
            position: {my: "right-50 top+45", at: "right top", of: this._getEditDialog()}
        });

        // onClick - 'Record' Row within the dlg
        $dlg.find('.recordDiv').on('click', function(event){

            let $ele = $(event.target);
            if(!$ele.hasClass('recordDiv')){ // check that the correct element is retrieved
                $ele = $ele.parents('.recordDiv');
            }

            $ele.css('background', 'rgba(65, 105, 225, 0.8)'); // highlight selection

            // Retrieve important details
            let dt_id = $ele.attr('data-dtid');
            let search = $ele.attr('data-search');
            let ptr_rectypes = $Db.dty(dt_id, 'dty_PtrTargetRectypeIDs');
            let index = Number($ele.attr('data-index'));

            let init_value = $($ele.find('div')[1]).text();

            // Show dialog for selecting/creating a record
            let opts = {
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

                    if(!data || !data.selection) return;

                    let recset = data.selection;

                    let record = recset.getFirstRecord();
                    let rec_ID = recset.fld(record, 'rec_ID')
                    let rec_Title = recset.fld(record, 'rec_Title');

                    if(!rec_ID || rec_ID <= 0 || rec_Title=='') return;

                    if(type=='resource'){
                        field_values[dt_id].push(rec_ID);
                        that._editing.setFieldValueByName(dt_id, field_values[dt_id]);
                    }else{

                        let fld = that._editing.getFieldByName(dt_id);
                        let rel = $ele.attr('data-rel') ? $ele.attr('data-rel') : '';
                        fld.editing_input('setup_Relmarker_Target', rec_ID, rel, (context) => {

                            $ele.css('background', ''); // remove higlight

                            if(context && context.count>0){

                                // Update information
                                $ele.attr('data-value', rec_ID);
                                $($ele.find('div')[1]).attr('title', rec_Title).text(rec_Title);
                                window.hWin.HEURIST4.util.setDisabled($ele, true); // set to disabled
                                $ele.css('background', 'gray');

                                complete_count ++;

                                if(complete_count >= todo_count){
                                    // Close dlg
                                    __nextProcess();
                                }else if(type=='resource'){

                                    let $n_ele = $ele.parent().find('div[data-index="'+ (index+1) +'"]');

                                    if($n_ele.length == 1 && !$n_ele.hasClass('ui-state-disabled')){
                                        $n_ele.trigger('click');
                                    }
                                }
                            }

                        }); // prepare relationship creation

                        $(fld.find('button.rel_link')[0]).trigger('click'); // click invisible button, only the first just in case

                        return;
                    }

                    $ele.css('background', ''); // remove higlight

                    // Update information
                    $ele.attr('data-value', rec_ID);
                    $($ele.find('div')[1]).attr('title', rec_Title).text(rec_Title);
                    window.hWin.HEURIST4.util.setDisabled($ele, true); // set to disabled
                    $ele.css('background', 'gray');

                    complete_count ++;

                    if(complete_count >= todo_count){
                        // Close dlg
                        __nextProcess();
                    }else if(type=='resource'){

                        let $n_ele = $ele.parent().find('div[data-index="'+ (index+1) +'"]');

                        if($n_ele.length == 1 && !$n_ele.hasClass('ui-state-disabled')){
                            $n_ele.trigger('click');
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
    },

    /**
     * @brief Focuses a specific field in the edit form.
     * @memberof heurist.manageRecords
     * @param {number} field_id The DetailTypeID of the field to focus.
     * Scrolls the form to bring the field into view and then attempts to focus its input element.
     * Handles fields within tabs or accordions by activating the respective container first.
     */    
    focusField: function(field_id){

        let $ele = this._editing.getFieldByName(field_id);
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

        this.editForm.animate({scrollTop: $ele.position().top}, 1000);

        if(!isSeparator){
            $ele.editing_input('focus');
        }
    },

    /**
     * @brief Toggles the public visibility of the current record.
     * @memberof heurist.manageRecords
     * Reads the current 'rec_NonOwnerVisibility' value. If 'public', changes to 'viewable',
     * and vice-versa. Sends an access update request to `HAPI4.RecordMgr.access`.
     * On success, updates the field in the form, the toggle button state, and the summary panel.
     * This action is restricted to administrators or the record owner.
     */
    _toggleRecordVisibility: function(){
        const that = this;
                       
        let ele = this._editing.getFieldByName('rec_NonOwnerVisibility');
        let vals = ele.editing_input('getValues');

        let new_visibility = vals[0] == 'public' ? 'viewable' : 'public';
        let current_owner = this._getField('rec_OwnerUGrpID');
        let allowed = window.hWin.HAPI4.is_admin() || window.hWin.HAPI4.is_member(current_owner);

        if(!allowed){
            return;
        }
        
        
        let request = {
            request_id : window.hWin.HEURIST4.util.random(),
            ids  : this._currentEditID,
            OwnerUGrpID: current_owner,
            NonOwnerVisibility: new_visibility
        };

        window.hWin.HAPI4.RecordMgr.access(request, 
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    //update in edit form
                    that._editing.setFieldValueByName('rec_NonOwnerVisibility', new_visibility, false);
                    //update button caption
                    that._updateRecToggleButton(new_visibility=='public');
                    //update value in record summary
                    that.editFormSummary.find('#recAccess').text(new_visibility=='viewable'?window.hWin.HR('Everyone'):new_visibility);
                    
                    window.hWin.HEURIST4.msg.showMsgFlash(
                            window.hWin.HR('Record visibility has been changed'), 1500);
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
        });
    },

    _getRecordHistory: function(){

        if(!this._check_history){
            window.hWin.HEURIST4.msg.showMsgFlash('No edits found on for this record...', 3000);
            window.hWin.HEURIST4.util.setDisabled(this.element.find('.btn-rec-history'), true);
            return;
        }

        const that = this;
        const rectype = this._getField('rec_RecTypeID');

        let request = {
            entity: 'sysArchive',
            a: 'batch',
            get_record_history: 1,
            rec_ID: this._currentEditID
        };

        window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){

            if(response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            that._record_history = response.data.history;
            
            if(window.hWin.HEURIST4.util.isempty(that._record_history)){
                that._check_history = false;
                that._getRecordHistory();
                return;
            }

            let rst_fields = [];
            let fld_name_css = "font-size: 14px;";
            let smaller_text_css = "font-size: 10px;";
            let row_css = "cursor: default; display: grid; grid-template-columns: 25px 45px 15px 15px 125px 20px 60%; align-items: center;";
            let container_css = "margin: 10px 5px;";

            $Db.rst(rectype).each2(function(dty_ID, rst){

                if($Db.dty(dty_ID, 'dty_Type') == 'separator') return;

                let name = rst['rst_DisplayName'];
                let rec = {
                    id: dty_ID,
                    name: name,
                    t_name: name.length > 25 ? name.substring(0, 25) + '...' : name,
                    order: rst['rst_DisplayOrder']
                };
                
                rst_fields.push(rec);
            });

            rst_fields.sort( (a,b) => a.order < b.order ? -1 : 1 );

            let rec_ids = {};

            let content = "";
            for(const field of rst_fields){

                let fld = that._editing.getFieldByName(field.id);
                let type = $Db.dty(field.id, 'dty_Type');

                let cur_values = fld.editing_input('getValues');
                let fld_history = that._record_history[field.id];

                if(window.hWin.HEURIST4.util.isempty(fld_history)) continue;
                
                for(let fld_idx in cur_values){

                    if(fld_history[fld_idx].length < 2) continue; // no history to display, skip

                    let history_head = '';
                    let history_log = '';
                    let cur_value = cur_values[fld_idx];

                    if(type == 'resource' || type == 'relmarker'){
                        rec_ids[`${field.id}-${fld_idx}-0`] = fld_history[fld_idx][0].arc_Value;
                        cur_value = '';
                    }else if(type == 'freetext' || type == 'blocktext'){ // remove html
                        cur_value = window.hWin.HEURIST4.util.stripTags(cur_value, 'u, i, b, strong, em');
                    }else if(type == 'date'){ // translate value
                        cur_value = temporalToHumanReadableString(cur_value);
                    }else if(type == 'enum'){ // grab label from dropdown
                        cur_value = fld_history[fld_idx][0].arc_Value;
                    }else if(type == 'file'){ // set value to filename / url
                        cur_value = !window.hWin.HEURIST4.util.isempty(cur_value.ulf_OrigFileName) ? 
                                        cur_value.ulf_OrigFileName : cur_value.ulf_ExternalFileReference;
                    }

                    history_head = `<div id="${field.id}-${fld_idx}" style="padding-bottom: 5px;">`
                                    + `<strong title="${field.name}" style="${fld_name_css}">${field.t_name}</strong>`
                                 + `</div>`;

                    for(let idx = 0; idx < fld_history[fld_idx].length; idx++){
    
                        let cur_history = fld_history[fld_idx][idx];
                        let prev_value = cur_history.arc_Value;

                        if(type == 'resource' || type == 'relmarker'){
                            rec_ids[`${field.id}-${fld_idx}-${idx}`] = cur_history.arc_Value;
                            prev_value = '';
                        }else if(type == 'freetext' || type == 'blocktext'){
                            prev_value = window.hWin.HEURIST4.util.stripTags(prev_value, 'u, i, b, strong, em');
                        }
    
                        let date_stamp = cur_history['arc_TimeOfChange'];
                        date_stamp = window.hWin.HEURIST4.util.isempty(date_stamp) ? '...' : TDate.parse(date_stamp).toString('H:m d  MMMM y');

                        let is_revert = cur_history.arc_Action == 'revert';
                        let chkbx_attr = !is_revert ? 
                                            'disabled="disabled" class="ui-state-disabled"' : `name="revert-change" value="${field.id}-${fld_idx}-${idx}"`;
                        let chkbx_style = `cursor: pointer;${!is_revert ? 'visibility: hidden;' : ''}`

                        history_log += `<div id="${field.id}-${fld_idx}-${idx}" style="${row_css}" ${is_revert ? 'class="record_history_value"' : ''}>`
                                        + '<span>'
                                            + `<input type="checkbox" ${chkbx_attr} style="${chkbx_style}"> `
                                        + '</span>'
                                        + `<span>${cur_history.arc_Action}</span> `
                                        + `<span style="${smaller_text_css}">${cur_history.arc_ChangedByUGrpID}</span> <span style="${smaller_text_css}">@</span>`
                                        + `<span class="record_history_datestamp" style="${smaller_text_css}">${date_stamp}</span> <span style="${smaller_text_css}">&nbsp;>> </span>`
                                        + `<span class="truncate" data-idx="${field.id}-${fld_idx}-${idx}" title="${prev_value}">${prev_value}</span>`
                                    + `</div>`;
                    }

                    if(!window.hWin.HEURIST4.util.isempty(history_log)){
                        content += `<div data-dtyid="${field.id}" style="${container_css}">${history_head}${history_log}</div>`;
                    }
                }
            }

            // String of users
            let users = `<div style="${container_css}">Users:&nbsp;&nbsp;`;
            for(let id in response.data.users){
                users += `${id} = ${response.data.users[id]}&nbsp;&nbsp;&nbsp;&nbsp;`;
            }
            users += '</div>';

            if(!window.hWin.HEURIST4.util.isempty(content)){

                let $acc_ele = $(that.editFormSummary.find('.summary-accordion').get(6));

                content = `Check values to be restored, then click <button id="btn-history-revert" style="margin: 0px 10px">Revert changes</button> or <button id="btn-history-cancel" style="margin: 0px 10px">Cancel</button>`
                        + '<br><label for="record_history_setby_group">Bulk check by modification date <input type="checkbox" id="record_history_setby_group" /></label>'
                        + users
                        + content;
                $acc_ele.children('div').html(content);

                that._on($acc_ele.find('#btn-history-revert').button(), {
                    click: function(){
                        that._revertRecordHistory();
                    }
                });
                that._on($acc_ele.find('#btn-history-cancel').button(), {
                    click: function(){
                        $acc_ele.find('input[type="checkbox"][name="revert-change"]').prop('checked', false);
                    }
                });
                that._on($acc_ele.find('#record_history_setby_group'), {
                    click: function(){
                        $acc_ele.find(`input[name="revert-change"]`).prop('checked', false); // reset checks
                    }
                });
                // Check checkbox on clicking row
                that._on($acc_ele.find('div.record_history_value'), {
                    click: function(event){

                        let $ele = $(event.target);
                        let org_target_checkbox = $ele.is('input[type="checkbox"]');
                        if(!$ele.hasClass('record_history_value') && !org_target_checkbox){
                            $ele = $ele.closest('.record_history_value');
                        }

                        $ele = org_target_checkbox || $ele.is('input[type="checkbox"]') ? $ele : $ele.find('input[type="checkbox"]');
                        if($ele.length == 0){
                            return;
                        }

                        let val = $ele.val().split('-'); // get base of value
                        let date = that._record_history[val[0]][val[1]][val[2]]['arc_TimeOfChange'];

                        val.pop();

                        let new_status = $ele.is(':checked');
                        new_status = org_target_checkbox ? new_status : !new_status; // invert status if the checkbox was not clicked

                        if($acc_ele.find('#record_history_setby_group').is(':checked')){

                            date = TDate.parse(date).toString('H:m d  MMMM y');
                            let $dates = $acc_ele.find(`.record_history_datestamp:contains("${date}")`);

                            $dates.each((idx, date) => {
                                let $parent = $(date).closest('.record_history_value');
                                if($parent.length == 0){
                                    return;
                                }

                                $parent.parent().find('input[type="checkbox"]').prop('checked', false);

                                $ele = $ele.add($parent.find('input[type="checkbox"]'));
                            });
                        }

                        val = `${val.join('-')}-`;

                        $acc_ele.find(`input[value^="${val}"]`).prop('checked', false); // remove all selections for this field

                        $ele.prop('checked', new_status); // now, set clicked row's check status
                    }
                });

                that.editFormPopup.layout().open('east'); // Expand layout panel

                // Expand accordion header
                if($acc_ele.accordion('instance') != undefined){
                    $acc_ele.accordion('option', 'active', 0);
                }

                // Enlarge layout panel
                let width = $(document).width() * 0.4;
                if(that.editFormPopup.layout().state['east']['outerWidth'] < width){
                    that.editFormPopup.layout().sizePane('east', width);
                }
            }else{
                that._check_history = false;
                that._getRecordHistory();
            }

            if(Object.keys(rec_ids).length > 0){

                let $acc_ele = $(that.editFormSummary.find('.summary-accordion').get(6));

                for(const fld_idx in rec_ids){

                    let $ele = $acc_ele.find(`span[data-idx="${fld_idx}"]`);
                    if($ele.length < 1){
                        continue;
                    }

                    window.hWin.HEURIST4.ui.createRecordLinkInfo($ele, rec_ids[fld_idx], false);
                    if($ele.find('.btn-edit').length > 0){
                        $ele.find('.btn-edit').parent().css('padding-left', '');
                        $ele.find('.btn-edit').remove();
                    }
                }
            }
        });
    },

    _revertRecordHistory: function(){

        const that = this;
        const rectype = this._getField('rec_RecTypeID');

        let $checked_options = that.editFormPopup.find('input[type="checkbox"][name="revert-change"]:checked');

        if($checked_options.length <= 0){
            return;
        }

        let changes = {};
        let changes_txt = '';
        let header_css = "display: inline-block; margin: 5px;";
        let row_css = "cursor: default; display: grid; grid-template-columns: 250px 20px 250px; align-items: center; margin-bottom: 5px;";

        $checked_options.each((idx, ele) => {

            let value = $(ele).val();
            if(value.indexOf('-') === -1){
                return;
            }
            let [dty_ID, fld_idx, arc_idx] = value.split('-');
            if(window.hWin.HEURIST4.util.isempty(dty_ID) || window.hWin.HEURIST4.util.isempty(fld_idx) || window.hWin.HEURIST4.util.isempty(arc_idx)){
                return;
            }

            let archive_row = that._record_history[dty_ID][fld_idx][arc_idx];
            let existing_val = that._record_history[dty_ID][fld_idx][0]['arc_Value'];

            if(!Object.hasOwn(changes, dty_ID)){
                changes[dty_ID] = {};

                if(changes_txt !== ''){ // add line break
                    changes_txt += '<hr style="margin-top: 10px;">';
                }

                let fld_name = $Db.rst(rectype, dty_ID, 'rst_DisplayName');
                changes_txt += `<strong style="${header_css}">${fld_name}</strong>`;
            }

            // prep values for display
            let escaped_val = window.hWin.HEURIST4.util.htmlEscape(existing_val);
            let old_val = window.hWin.HEURIST4.util.htmlEscape(archive_row['arc_Value']);

            changes_txt += `<div style="${row_css}">`
                + `<span class="truncate" title="${existing_val}">${escaped_val}</span>`
                + `<span>&nbsp;>> </span>`
                + `<span class="truncate" title="${archive_row['arc_Value']}">${old_val}</span>`
            + `</div>`;

            changes[dty_ID][fld_idx] = archive_row['arc_ID'];
        });

        let $dlg;
        let msg = `Continuing will make the following changes:<br><br>${changes_txt}`;

        let btns = {};
        btns[window.HR('Proceed')] = function(){

            // Revert changes
            $dlg.dialog('close');

            let request = {
                entity: 'sysArchive',
                a: 'batch',
                rec_ID: that._currentEditID,
                revert_record_history: 1,
                revisions: changes
            };

            window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, null, '<span style="color: white;">Reverting record changes...</span>');

            window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){

                window.hWin.HEURIST4.msg.sendCoverallToBack();

                if(response.status != window.hWin.ResponseStatus.OK){
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    return;
                }

                if(window.hWin.HEURIST4.util.isObject(response.data) && response.data.errors.length > 0){

                    window.hWin.HEURIST4.msg.showMsgDlg(`The following issues were encountered:<br><br>${response.data.errors.join('<br>')}<br><br>`, null, null, {
                        close: function(){
                            that._initEditForm_step3(that._currentEditID);
                        }
                    });

                }else{
                    window.hWin.HEURIST4.msg.showMsgFlash('Record values successfully reverted, reloading record...', 3000);
                    setTimeout(() => { that._initEditForm_step3(that._currentEditID); }, 2000); // trigger reload
                }

            });
        };
        btns[window.HR('Cancel')] = function(){
            // Cancel
            $dlg.dialog('close');
        };

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, 
            {title: 'Revert record changes', yes: window.HR('Proceed'), no: window.HR('Cancel')}, {default_palette_class: 'ui-heurist-populate', maxHeight: 800}
        );
    },

    /**
     * Check source database for any structure updates
     */
    _checkStructureFromSource: function(org_DBID = -1){

        const that = this;

        if(this._source_db.id == -1 || !window.hWin.HAPI4.is_admin()){ // was unable to get source
            return;
        }

        const rty_ID = this._currentEditRecTypeID;
        const current_fields = $Db.rst(rty_ID)?.getRecords(); // current fields in rectype
        const rty_ConceptCode = $Db.getConceptID('rty', rty_ID); // complete concept code
        org_DBID = org_DBID == -1 ? $Db.rty(rty_ID, 'rty_OriginatingDBID') : org_DBID; // database ID for source
        const org_ID = $Db.rty(rty_ID, 'rty_IDInOriginatingDB'); // ID in source database

        if(!window.hWin.HEURIST4.util.isNumber(org_DBID) ||
            org_DBID == window.hWin.HAPI4.sysinfo.db_registeredid ||
            org_DBID == 0){
            // Couldn't determine the source, source is self or an un-registered database

            this.element.find('.btn-update-struct').hide();
            this._source_db.id = -1;

            return;
        }
        
        const NEXT_ATTEMPT = org_DBID == 2 ? 0 : 2; // attempt to also check core definitions

        if(this._source_db.id == 0){ // retrieve source details

            let request = {
                remote: 'master',
                detail: 'header',
                q: `ids:${parseInt(org_DBID)}`
            };

            window.hWin.HAPI4.RecordMgr.search(request, (response) => {

                if(response.status != window.hWin.ResponseStatus.OK){
                    
                    that._checkStructureFromSource(NEXT_ATTEMPT);
                    return;
                }

                let recset = new HRecordSet(response.data);

                if(recset.length() == 0){
                    that._checkStructureFromSource(NEXT_ATTEMPT);
                    return;
                }

                let record = recset.getFirstRecord();

                that._source_db.id = recset.fld(record, 'rec_ID');
                that._source_db.url = recset.fld(record, 'rec_URL');

                that._checkStructureFromSource(that._source_db.id);
            });

            return;
        }

        let rty_request = {
            rectypes: this._source_db.id == $Db.rty(rty_ID, 'rty_OriginatingDBID') ? org_ID : rty_ConceptCode,
            mode: 2,
            remote: this._source_db.url
        };

        window.hWin.HAPI4.SystemMgr.get_defs(rty_request, (response) => {

            if(response.status != window.hWin.ResponseStatus.OK ||
                !response?.data?.rectypes?.names ||
                Object.keys(response?.data?.rectypes?.names).length == 0){

                
                that._source_db.id = 0;
                that._checkStructureFromSource(NEXT_ATTEMPT);
                return;
            }

            that._source_def = response.data.rectypes;

            let dty_cc_idx = that._source_def.typedefs.dtFieldNamesToIndex.dty_ConceptID;

            const rty_IDs = Object.keys(that._source_def.typedefs);
            let source_rty_id = 0;

            for(let i = 0; i < rty_IDs.length; i ++){

                const curr_id = rty_IDs[i];
                
                if(curr_id>0 && that._source_def.typedefs[curr_id]?.commonFields?.rty_cc_idx == rty_ConceptCode){
                    source_rty_id = curr_id;
                    break;
                }
            }

            if(source_rty_id < 1){
                that._source_db.id = 0;
                that._source_def = null;
                that._checkStructureFromSource(NEXT_ATTEMPT);
                return;
            }
                    
            let missing_field = false;

            for(let dty_ID in that._source_def.typedefs[source_rty_id].dtFields){

                let concept_code = that._source_def.typedefs[source_rty_id].dtFields[dty_ID][dty_cc_idx];
                let local_code = $Db.getLocalID('dty', concept_code);

                if(local_code == 0 || !Object.hasOwn(current_fields, local_code)){
                    missing_field = true;
                    break;
                }
            }

            // Toggle visibility of button
            missing_field ? that.element.find('.btn-update-struct').show() : that.element.find('.btn-update-struct').hide();
            that.element.find('.btn-update-struct').position({
                my: 'left+10 center', at: 'right center', of: that.element.find('.btn-edit-rt')
            })
        });
    },

    _updateStructureFromSource: function(confirmed = false){

        const that = this;

        if(this._source_db.id < 1){
            window.hWin.HEURIST4.msg.showMsgFlash('Source database couldn\'t be found...', 2000);
            return;
        }

        if(!confirmed){ // confirm request

            let msg = 'Do you wish to proceed with the structure updating? This will import the following:<br><br>'
                    + '<ul>'
                        + '<li>any fields and vocabularies that are not yet in the database, new fields will be placed at the end.</li>'
                        + '<li>any unrecognised record types (and their fields and vocabularies) connected to the selected record type.</li>'
                        + '<li>'
                            + 'additional fields and vocabularies defined for record types already in your database which are connected<br>'
                            + 'to any of the record types above (the fields will be added to the end of the record type and may be removed or<br>'
                            + 'customised as desired; they will have no effect on existing data).'
                        + '</li>'
                    + '</ul>';

            let lbl = {title: 'Update record structure', yes: 'Proceed', no: 'Cancel'};
            window.hWin.HEURIST4.msg.showMsgDlg(msg, () => { that._updateStructureFromSource(true); }, lbl, {default_palette_class: 'ui-heurist-design'});

            return;
        }

        const rty_ConceptCode = $Db.getConceptID('rty', this._currentEditRecTypeID);

        this.element.find('.btn-update-struct').hide();
        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, null, "<span>Updating record structure...</span>");

        // Update structure
        window.hWin.HAPI4.SystemMgr.import_definitions(this._source_db.id, rty_ConceptCode, 'rectype', false, true,
            (response) => {

                window.hWin.HEURIST4.msg.sendCoverallToBack();

                if(response.status != window.hWin.ResponseStatus.OK){
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    return;
                }

                if(!response.report && !response.extra){
                    window.hWin.HEURIST4.msg.showMsgFlash('No changes have been made...', 2000);
                    return;
                }

                // Refresh entity
                window.hWin.HAPI4.EntityMgr.refreshEntityData('rty,trm,dty,rst', () => {

                    let msg = '';
                    let reports = response.report;

                    // Record types
                    let names = [];
                    let add_to_list = false;
                    for(const idx in reports.updated){
                        let id = reports.updated[idx];
                        let name = $Db.rty(id, 'rty_Name');

                        if(name){
                            names.push(`${id}: ${name}`);
                            add_to_list = true;
                        }
                    }
                    if(add_to_list){
                        msg += `Updated record types:<br>${names.join('<br>')}<br><br>`;
                        add_to_list = false;
                    }

                    names = [];
                    for(const idx in reports.added){
                        let id = reports.added[idx];
                        let name = $Db.rty(id, 'rty_Name');

                        if(name){
                            names.push(`${id}: ${name}`);
                            add_to_list = true;
                        }
                    }
                    if(add_to_list){
                        msg += `Added record types:<br>${names.join('<br>')}<br>`;
                        add_to_list = false;
                    }

                    if(reports.extra){ // get additional results

                        let list = '';
                        // Detail types
                        names = [];
                        for(const idx in reports.extra.detailtypes.added){
                            let id = reports.extra.detailtypes.added[idx];
                            let name = $Db.dty(id, 'dty_Name');

                            if(name){
                                names.push(`${id}: ${name}`);
                                add_to_list = true;
                            }
                        }
                        if(add_to_list){
                            list += `Added base fields:<br>${names.join('<br>')}<br><hr><br>`;
                            add_to_list = false;
                        }

                        names = [];
                        for(const idx in reports.extra.detailtypes.updated){
                            let id = reports.extra.detailtypes.updated[idx];
                            let name = $Db.dty(id, 'dty_Name');

                            if(name){
                                names.push(`${id}: ${name}`);
                                add_to_list = true;
                            }
                        }
                        if(add_to_list){
                            list += `Upated base fields:<br>${names.join('<br>')}<br><hr><br>`;
                            add_to_list = false;
                        }

                        // Terms
                        names = [];
                        for(const idx in reports.extra.terms.added){
                            let id = reports.extra.terms.added[idx];
                            let name = $Db.trm(id, 'trm_Label');

                            if(name){
                                names.push(`${id}: ${name}`);
                                add_to_list = true;
                            }
                        }
                        if(add_to_list){
                            list += `Added terms:<br>${names.join('<br>')}<br><hr><br>`;
                            add_to_list = false;
                        }

                        names = [];
                        for(const idx in reports.extra.terms.updated){
                            let id = reports.extra.terms.updated[idx];
                            let name = $Db.trm(id, 'trm_Label');

                            if(name){
                                names.push(`${id}: ${name}`);
                                add_to_list = true;
                            }
                        }
                        if(add_to_list){
                            list += `Upated terms:<br>${names.join('<br>')}<br><hr><br>`;
                            add_to_list = false;
                        }

                        if(list !== ''){
                            msg += `<br><hr><br>${list}`;
                        }
                    }

                    if(msg !== ''){
                        window.hWin.HEURIST4.msg.showMsgDlg(msg, () => {
                            that._initEditForm_step3(that._currentEditID);
                        }, {title: 'Structure updated'}, {default_palette_class: 'ui-heurist-design'});
                        return;
                    }

                    that._initEditForm_step3(that._currentEditID); // refresh record editor
                });

                return;
            }
        );
    },

    _handleESTCLookup: function(dialog_name, dlg_opts){

        let that = this;

        let req = {
            a: 'check_allow_estc',
            db: window.hWin.HAPI4.database,
            ver: dlg_opts.mapping.service
        };

        window.hWin.HAPI4.SystemMgr.check_allow_estc(req, function(response){

            if(response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return false;
            }

            that._loadParentLookup(dialog_name, dlg_opts);
        });
    },

    /**
     * Load base widget and any other possible parent widget
     *  ESTC <= ESTC_editions, ESTC_works and LRC18C
     *  Geonames <= GN and GN_postalCode
     *
     * @param {string} lookup_name - lookup/service name
     * @param {json} dialog_options - dialog options; title, modal, width, height, etc...
     */
    _loadParentLookup: function(lookup_name, dialog_options){

        let that = this;

        if(!window.hWin.HEURIST4.util.isFunction($('body')['lookupBase'])){
            $.getScript(`${window.hWin.HAPI4.baseURL}hclient/widgets/lookup/lookupBase.js`, () => {
                that._loadParentLookup(lookup_name, dialog_options);
            }).fail(() => {
                window.hWin.HEURIST4.msg.showMsgErr({
                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR,
                    error_title: 'Failed to load lookup base',
                    message: `Heurist failed to load the base lookup script needed for all external lookups.`
                });
            });
            return;
        }

        let is_estc = lookup_name.indexOf('ESTC') > -1 || lookup_name.indexOf('LRC18C') > -1;

        if(lookup_name.indexOf('GN') === -1 && lookup_name.indexOf('BnF') === -1 && !is_estc){
            window.hWin.HEURIST4.ui.showRecordActionDialog(lookup_name, dialog_options);
            return;
        }

        let parent = lookup_name.indexOf('GN') > -1 ? 'lookupGeonames' : '';
        parent = lookup_name.indexOf('BnF') > -1 ? 'lookupBnF' : parent;
        parent = is_estc ? 'lookupESTC' : parent;

        if(window.hWin.HEURIST4.util.isFunction($('body')[parent])){
            window.hWin.HEURIST4.ui.showRecordActionDialog(lookup_name, dialog_options);
            return;
        }

        $.getScript(`${window.hWin.HAPI4.baseURL}hclient/widgets/lookup/${parent}.js`, () => {
            window.hWin.HEURIST4.ui.showRecordActionDialog(lookup_name, dialog_options);
        }).fail((jqxhr, settings, exception) => {
            window.hWin.HEURIST4.msg.showMsgErr({
                status: window.hWin.ResponseStatus.UNKNOWN_ERROR,
                error_title: 'Failed to load parent widget',
                message: `Heurist failed to load the necessary scripts for the external lookup ${lookup_name}.`
            });
        });
    },

    /**
     * Switch record editor back to data entry mode, closing the structure tree and any field definition editing
     *
     * @param {boolean} force_close - whether to ignore any changes when switching
     */
    _switchToDataMode: function(force_close = false){

        let that = this;

        let struct_changes = this.options.rts_editor?this.options.rts_editor.manageDefRecStructure('checkIfEditing'):false;
        let data_changes = this._editing.isModified();

        if(!force_close && (struct_changes || data_changes)){

            let $dlg;
            let msg = (struct_changes
                        ? 'You have un-saved structure changes which will be lost if not saved'
                        : 'You have un-saved changes in your record data which will be lost if not saved')
                    + '.<br>Would you like to save them before returning the data mode?';

            let btns = {};
            btns[window.hWin.HR('Save changes')] = () => {
                $dlg.dialog('close');

                if(struct_changes){
                    that.element.find('.btnRecSaveAndClose_rts').trigger('click');
                    setTimeout(() => {that._switchToDataMode(true);}, 2500);
                }else{
                    that._saveEditAndClose(null, 'close_rst');
                }
            };
            btns[window.hWin.HR('Switch without saving')] = () => {
                $dlg.dialog('close');
                that._switchToDataMode(true);
            };

            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns,
                {title: `Un-saved ${struct_changes ? 'structure' : 'data'} changes`, yes: window.hWin.HR('Save changes'), no: window.hWin.HR('Switch without saving')},
                {default_palette_class: 'ui-heurist-design'}
            );

            return;
        }

        this.editFormPopup.layout().hide('west');
        this.options.rts_editor = null;
        this.reloadEditForm( true );
    }
});        