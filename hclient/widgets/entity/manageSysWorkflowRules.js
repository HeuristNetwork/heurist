/**
* @file manageSysWorkflowRules.js
* @brief Manages System Workflow Rule entities.
* @fileOverview Provides a UI for administrators to define and manage system workflow rules. This includes specifying triggers, conditions, and actions for automated processes within Heurist.
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4.0
*/



//
// there is no search, select mode for reminders - only edit
//
/**
 * @widget heurist.manageSysWorkflowRules
 * @brief Widget for managing System Workflow Rules.
 * @augments $.heurist.manageEntity
 * @description This widget provides an interface for administrators to define and manage
 * system workflow rules. These rules automate processes based on triggers, conditions, and actions.
 * The widget behavior can vary significantly based on `edit_mode` and `select_mode`.
 *
 * @property {number} [edit_height=600] Default height for the edit dialog of a workflow rule.
 * @property {string} default_palette_class Default CSS class for theming, typically 'ui-heurist-design'.
 * @property {boolean} [innerTitle=false] If true, allows for an inner title within the widget.
 * @property {boolean} [use_cache=true] If true, client-side caching is used for data.
 * @property {string} edit_mode Controls how editing is handled. Can be 'editonly' (directly opens editor for a specific or new rule),
 *                              'popup' (opens editor in a dialog), or other modes inherited from `manageEntity`.
 * @property {string} select_mode Influences selection behavior, e.g., 'manager' for standard list management.
 * @property {string} layout_mode Defines the overall layout. If `edit_mode` is 'editonly', this is also set to 'editonly'.
 * @property {number} width Default width of the widget, conditionally set (e.g., to 790 if 'editonly').
 * @property {number} height Default height of the widget, conditionally set (e.g., to 600 if 'editonly' and not otherwise specified).
 * @property {?function} beforeClose Custom function to call before closing the dialog, conditionally set.
 * @property {boolean} [list_header=false] If true, shows a header for the record list (when not in 'editonly' mode).
 * @property {?boolean} isFrontUI If true, indicates the widget is used in a front-end UI context, potentially altering styling and behavior.
 * @property {?number} swf_ID If `edit_mode` is 'editonly' and this ID is provided, the widget directly loads this specific workflow rule for editing.
 */
$.widget( "heurist.manageSysWorkflowRules", $.heurist.manageEntity, {
   
    _entityName:'sysWorkflowRules',
    
    is_first: true,

    options: {
        edit_height: 600
    },
    
    //keep to refresh after modifications
    _keepRequest:null,
    
    /**
     * @brief Initializes the widget.
     * @override
     * @memberof heurist.manageSysWorkflowRules
     * Sets default options for palette class, layout mode, dimensions, caching, and other
     * configurations. It tailors the widget's behavior based on `edit_mode` and `select_mode`.
     * Also sets up an event listener for `ON_STRUCTURE_CHANGE` to reload data if workflow definitions change.
     */
    _init: function() {
        
        if(!this.options.default_palette_class){
            this.options.default_palette_class = 'ui-heurist-design';    
        }
        
        this.options.innerTitle = false;
        this.options.use_cache = true;
        
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

        let that = this;

        if(this.options.isFrontUI){
            
            this.searchForm.css({padding:'10px 5px 0 10px'});
            
            //window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, {'background-color':'#fff', opacity:1});   
        
            if(this.options.select_mode=='manager'){ //adjust table widths
                window.hWin.HAPI4.addEventListener(this, window.hWin.HAPI4.Event.ON_WINDOW_RESIZE, 
                    function(){
                        if(that.recordList && that.recordList.resultList('instance')){
                            that.recordList.resultList('applyViewMode','list', true);
                            that.recordList.resultList('refreshPage');
                        }
                    });
            }    
        }        
        
        
        //refresh list        
        $(window.hWin.document).on(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            function(e, data) { 
                if(!data || 
                   (data.source != that.uuid && data.type == 'swf'))
                {
                    that._loadData();
                }
            });
        
    },
    
    
    /**
     * @brief Loads or updates the list of workflow rules.
     * @memberof heurist.manageSysWorkflowRules
     * @param {boolean} [is_first] Indicates if this is the first time data is being loaded.
     * Updates the record list with workflow rules from the local cache (`$Db.swf()`).
     * If not the first load and the search form is initialized, it triggers a new search.
     */
    _loadData: function( is_first ){
        
            this.updateRecordList(null, {recordset:$Db.swf()});
            if(!is_first && this.searchForm.searchDefRecTypes('instance')){ //is_first!==true && 
                this.searchForm.searchSysWorkflowRules('startSearch');
            }
    },
    
    /**
     * @brief Initializes the controls for the widget.
     * @override
     * @memberof heurist.manageSysWorkflowRules
     * @returns {boolean} False if the parent `_initControls` fails, otherwise true.
     * If in 'editonly' mode, it loads a specific rule (if `options.swf_ID` is provided) or a new rule form.
     * Otherwise, it initializes the search form (`searchSysWorkflowRules`), configures the record list
     * for sortable list view, and sets up event listeners for search actions (filter, add, add set, edit vocabulary).
     */
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }
      
        let that = this;

        if(this.options.edit_mode=='editonly'){
            //load rules
            if(this.options.swf_ID>0){
                    let request = {};
                    request['swf_ID']  = this.options.rem_RecID; // Should likely be this.options.swf_ID
                    request['a']          = 'search'; //action
                    request['entity']     = this.options.entity.entityName;
                    request['details']    = 'full';
                    request['request_id'] = window.hWin.HEURIST4.util.random();
                    
                    window.hWin.HAPI4.EntityMgr.doRequest(request, 
                        function(response){
                            if(response.status == window.hWin.ResponseStatus.OK){
                                let recset = new HRecordSet(response.data);
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
        }
        else{
            this.searchForm.searchSysWorkflowRules(this.options);
            
            
            let iheight = 12;
            this.searchForm.css({'height':iheight+'em',padding:'10px'});
            this.recordList.css({'top':iheight+0.5+'em'});
            
            this.recordList.resultList({
                show_toolbar:false,
                view_mode: 'list',
                sortable: true,
                onSortStop: function(){
                    
                    let recordset = this.getRecordSet();
                    window.hWin.HEURIST4.dbs.applyOrder(recordset, 'swf', function(res){
                       
                    });
                    
                }
            });

            this.recordList.find('.div-result-list-content').css({'display':'table','width':'99%'});
            
            let vocab_id = $Db.getLocalID('trm', '2-9453');
            
            this._on( this.searchForm, {
                "searchsysworkflowrulesonfilter": this.filterRecordList,
                "searchsysworkflowrulesonresult": this.updateRecordList,
                "searchsysworkflowrulesonadd": function() {

                        let recset = this.recordList.resultList('getRecordSet');
                    
                        if(recset.length()==0){
                            this._addRuleSet();
                        }else{
                            let rty_ID = this.searchForm.searchSysWorkflowRules('getSelectedRty');
                            let terms = $Db.trm_TreeData(vocab_id, 'set');
                            let _swf_rules = $Db.getSwfByRectype(rty_ID);
                            if(_swf_rules.length<terms.length){
                                this._onActionListener(null, 'add');    
                            }else{
                                window.hWin.HEURIST4.msg.showMsgFlash('This record type has full set of stages');
                            }
                        }
                },
                "searchsysworkflowrulesonaddset": function() {
                        this._addRuleSet();
                },
                "searchsysworkflowrulesonvocabedit": function() {

                    let options = {
                        height:800, width:1300,
                        selection_on_init: vocab_id,
                        innerTitle: false,
                        innerCommonHeader: $('<div>'
                            +'<span style="margin-left:260px"><b>Editing Workflow Vocabulary</b></span>'
                            +'</div>'),
                        onInitFinished: function(){
                            let that2 = this;
                            setTimeout(function(){
                                that2.vocabularies_div.manageDefTerms('selectVocabulary', vocab_id);
                            },500);
                        },
                        onClose: function(){
                            that.searchForm.searchSysWorkflowRules('startSearch');
                        }
                    };
                    
                    window.hWin.HEURIST4.ui.showEntityDialog('defTerms', options);
                }
            });
        }


        return true;
    },
    
    /**
     * @brief Filters the record list based on search criteria.
     * @override
     * @memberof heurist.manageSysWorkflowRules
     * @param {Event} event The event object.
     * @param {object} request The search request object.
     * @returns {HRecordSet|null} The filtered recordset, or null if no results.
     * If it's the first time filtering, it calls `_loadData`.
     * Applies the filter request to the cached recordset and updates the displayed list.
     * It also updates the state of buttons in the search form based on whether results are found.
     */
    filterRecordList: function(event, request){

        if( this.is_first ){
            this.is_first = false;
            this._loadData(true);
        }

        let results = this._super(event, request);
        
        this.searchForm.searchSysWorkflowRules('setButton', (results==null || results.length()==0));
        this.searchForm.searchSysWorkflowRules('refreshRectypeList');

        if(results==null || results.length()==0){ //count_total
        
            /*            
            if(this.options.select_mode=='manager'){
                
                let sMsg;
                let s_all = this.element.find('#chb_show_all_groups').is(':checked');
                if(!s_all){
                    sMsg = '<div style="margin-top:1em;">There are no record types defined in this group.'
                            +'<br><br>Please drag record types from other groups or add new<br>record types to this group.</div>';   
                }else{
                    sMsg = '<div style="padding: 10px">'
                            +'<h3 class="not-found" style="color:red;">Filter/s are active (see above)</h3><br>'
                            +'<h3 class="not-found" style="color:teal">No entities match the filter criteria</h3>'
                            +'</div>';
                }
                this.recordList.resultList('option','empty_remark', sMsg);
                this.recordList.resultList('renderMessage', sMsg);
            }
            */
        }
        
        
    },
    
    /**
     * @brief Adds a complete set of default workflow rules for a selected record type.
     * @memberof heurist.manageSysWorkflowRules
     * Retrieves the currently selected record type from the search form.
     * Sends a batch request to the server (operation 'add_rule_set') to create
     * a default set of workflow rules for that record type.
     * On success, refreshes the workflow rule cache (`$Db.swf()`) and re-triggers a search.
     */
    _addRuleSet: function(){
        
        let that = this;
        let rty_ID = this.searchForm.searchSysWorkflowRules('getSelectedRty');
        let request = {a:'batch', entity:this.options.entity.entityName, 
            operation: 'add_rule_set',
            rty_ID:rty_ID};
        if(rty_ID>0)
        window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    window.hWin.HAPI4.EntityMgr.refreshEntityData('swf', function(success){
                        if(success){
                            that.is_first = true;
                            that.searchForm.searchSysWorkflowRules('startSearch');
                        }    
                    });
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
    },
    
    
//----------------------------------------------------------------------------------    
    /**
     * @brief Retrieves and validates form values before saving a workflow rule.
     * @override
     * @memberof heurist.manageSysWorkflowRules
     * @returns {?object} The validated field values, or null if validation fails.
     * Calls the parent `_getValidatedValues`. It then performs specific transformations for
     * visibility and ownership fields:
     * - `swf_Visibility` (from UI dropdown: 'null', 'hidden', 'viewable', 'public') is mapped to `swf_SetVisibility`.
     * - `swf_SetOwnership` is adjusted if its UI value is 'null'.
     * This ensures the data is in the correct format for server-side processing.
     */
    _getValidatedValues: function(){
        
        let fields = this._super();
        
        if(fields!=null){
            
            if(fields['swf_Visibility']=='null'){
                    fields['swf_SetVisibility'] = '';
            }else if(fields['swf_Visibility']!='hidden'){ //viewable or public
                    fields['swf_SetVisibility'] = fields['swf_Visibility'];    
            }else if(fields['swf_SetVisibility']==''){ //hidden
                    fields['swf_SetVisibility'] = 'hidden';
            }
            
            if(fields['swf_SetOwnership'] == 'null'){
                fields['swf_SetOwnership'] = '';
            }
            /*
            if(fields['swf_Ownership']=='null'){
                    fields['swf_SetOwnership'] = '';
            }else if(fields['swf_Ownership']=='0'){ //viewable or public
                    fields['swf_SetOwnership'] = '0';    
            }
            */
            
        }
        
        return fields;
    },

    /**
     * @brief Saves the workflow rule and handles follow-up actions.
     * @override
     * @memberof heurist.manageSysWorkflowRules
     * @param {?object} fields Field values to save. If null, values are retrieved from the form.
     * @param {string|function} afteraction Action to perform after saving.
     * If in 'editonly' mode and an `swf_ID` is provided, ensures this ID is part of the saved data.
     * Calls the parent `_saveEditAndClose`.
     */
    _saveEditAndClose: function( fields, afteraction ){

        //assign record id    
        if(this.options.edit_mode=='editonly' && this.options.swf_ID>0){
            let ele2 = this._editing.getFieldByName('swf_ID');
            ele2.editing_input('setValue', this.options.swf_ID );
        }
                                                                                  
        this._super();
    },
    
    /**
     * @brief Handles events after a workflow rule is saved.
     * @override
     * @memberof heurist.manageSysWorkflowRules
     * @param {number} recID The ID of the saved rule.
     * @param {object} fieldvalues The saved field values.
     * Calls the parent `_afterSaveEventHandler`. Updates the local cache (`$Db.swf()`)
     * with the new/updated rule. Refreshes the global SWF cache.
     * If in 'editonly' mode, closes the dialog. Otherwise, updates the search form's
     * selected record type to reflect the context of the saved rule.
     */
    _afterSaveEventHandler: function( recID, fieldvalues ){

        this._super( recID, fieldvalues );
        
        $Db.swf().setRecord(recID, fieldvalues); // Update local cache with saved values.

        window.hWin.HAPI4.EntityMgr.refreshEntityData('swf', () => { // Refresh global swf cache
            
            if(this.options.edit_mode=='editonly'){
                this.closeDialog(true); // Close if in 'editonly' mode
            }else{
                // If in list mode, update the selected record type in the search form
                // to potentially refresh the list for that record type.
                this.searchForm.searchSysWorkflowRules('option', 'rty_ID', fieldvalues['swf_RecTypeID']);                    
            }
        });

    },

    /**
     * @brief Handles the deletion of a workflow rule, with a confirmation prompt.
     * @override
     * @memberof heurist.manageSysWorkflowRules
     * @param {boolean} [unconditionally=false] If true, deletes without confirmation.
     * If `unconditionally` is false (the default), it shows a confirmation dialog.
     * If confirmed, or if `unconditionally` is true, it calls the parent's `_deleteAndClose` method.
     */
    _deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this._super(); 
        }else{
            let that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this rule?', function(){ that._deleteAndClose(true); },
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
    },
    
    /**
     * @brief Handles events after a workflow rule is deleted.
     * @override
     * @memberof heurist.manageSysWorkflowRules
     * @param {number} recID The ID of the deleted rule.
     * Calls the parent's `_afterDeleteEventHandler`
     * Removes the rule from the local cache (`$Db.swf()`) and triggers a search refresh.
     */
    _afterDeleteEventHandler: function(recID){
        this._super(recID);
        $Db.swf().removeRecord(recID);
        this.searchForm.searchSysWorkflowRules('startSearch');
    },

    
    /**
     * @brief Performs actions after the edit form for a workflow rule is initialized.
     * @override
     * @memberof heurist.manageSysWorkflowRules
     * Calls the parent's `_afterInitEditForm`.
     * If adding a new rule (`_currentEditID < 0`):
     *   - Pre-fills `swf_RecTypeID` with the currently selected record type from the search form.
     *   - Disables already selected stages in the `swf_Stage` dropdown to prevent duplicate rules for the same stage.
     * If editing an existing rule:
     *   - Disables `swf_RecTypeID` and `swf_Stage` fields as they shouldn't be changed post-creation.
     * Sets up logic for the `swf_Visibility` and `swf_SetVisibility` fields to work together
     * (e.g., showing `swf_SetVisibility` only when `swf_Visibility` is 'hidden').
     * Initializes the "Record Email Field" selector (`_setupRecordEmailField`).
     * Adds help text with field substitution placeholders for the `swf_EmailText` field.
     */
    _afterInitEditForm: function(){

        this._super();
        
        let that = this;
        
        if(this.options.edit_mode=='editonly' || this.options.edit_mode=='popup'){
            
            if(that._currentEditID<0){
                const rty_ID = this.searchForm.searchSysWorkflowRules('getSelectedRty');
                that._editing.setFieldValueByName('swf_RecTypeID', rty_ID, false);
                
                //disable already selected stages
                let _swf_rules = $Db.getSwfByRectype(rty_ID);
                let ele = that._editing.getFieldByName('swf_Stage').editing_input('getInputs');
                ele = ele[0];
                for(let i=0; i<_swf_rules.length; i++){
                    ele.find('option[value='+_swf_rules[i]['swf_Stage']+']').attr('disabled',true);
                }
                ele.hSelect('refresh');
                
            }else{
                //disable rectype and stage for edit
                that._editing.getFieldByName('swf_RecTypeID').editing_input('setDisabled', true);
                that._editing.getFieldByName('swf_Stage').editing_input('setDisabled', true);
            }
            

            function __onChangeVisType(is_first){ 
                let ele = that._editing.getFieldByName('swf_Visibility');
                let ele1 = that._editing.getFieldByName('swf_SetVisibility');
                
                let res = ele.editing_input('getValues'); 
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

            let ele = that._editing.getFieldByName('swf_Visibility');
            let ele1 = that._editing.getFieldByName('swf_SetVisibility');        
            let res = ele1.editing_input('getValues'); 
            
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


            let rty_ID = this.searchForm.searchSysWorkflowRules('getSelectedRty');
            let has_FreetextFld = rty_ID > 0;
            let list_Fields = {
                title: 'Record title',
                stage: 'Workflow stage',
                user: 'Modifying user',
                link_v: 'Record viewer link',
                link_e: 'Record editor link',
                url: 'Record url'
            };
            !has_FreetextFld || $Db.rst(rty_ID).each2((rst_ID, record) => {
                switch ($Db.dty(rst_ID, 'dty_Type')) {
                    case 'freetext':
                        has_FreetextFld = true;
                        list_Fields[rst_ID] = record['rst_DisplayName'];
                        break;

                    case 'blocktext':
                    case 'file':
                    case 'resource':
                    case 'date':
                    case 'enum':
                    case 'float':
                    case 'integer':
                        list_Fields[rst_ID] = record['rst_DisplayName'];
                        break;

                    default:
                        break;
                }
            });

            // Hide and replace input with checkbox & dropdown combo
            this._setupRecordEmailField(has_FreetextFld);

            ele = that._editing.getFieldByName('swf_EmailText');
            let $extra_help = $('<div>', {style: 'cursor: default;'})
                .html('Field subsitutions can be performed by enclosing the field ID within hash (#) symbols<span class="lnk_Flds">see the list here</span>');

            ele.find('.heurist-helper1').append($extra_help);

            let $txt_emailtext = ele.find('textarea');
            let $help_link = ele.find('.lnk_Flds').css({
                'text-decoration': 'underline',
                cursor: 'pointer',
                display: 'block',
                color: 'blue'
            });

            let list = '<div style="cursor: default;">List of available fields:<br><br>';
            
            function createFieldList(list_Fields){

                let list = '';
                let shared_styles = 'display: inline-block; vertical-align: -0.2em;';

                for(const dty_ID in list_Fields){

                    let id = Number.isInteger(dty_ID) ? `ID #${dty_ID}` : dty_ID;
                    let type = Number.isInteger(dty_ID) ? $Db.dty(dty_ID, 'dty_Type') : 'freetext';

                    list += `<span style="display: inline-block; padding-bottom: 7.5px;">
                        <button class="ui-icon ui-icon-plus" data-dtyid="${dty_ID}" title="Add field code to message"></button>
                        <span style="${shared_styles} width: 150px; padding-left: 5px;" class="truncate" title="${list_Fields[dty_ID]}">${list_Fields[dty_ID]}</span> 
                        <span style="${shared_styles} width: 65px;" class="truncate" title="${id}">(${id})</span> 
                        <span style="${shared_styles} width: 65px;" class="truncate">[ ${type} ]</span>
                    </span><br>`;
                }

                return list;
            }

            list += createFieldList(list_Fields);

            list += '</div>';

            let $dlg_fields, dialog_opened = false;

            this._on($help_link, {
                click: () => {

                    if(dialog_opened){
                        $dlg_fields.dialog('moveToTop');
                        return;
                    }

                    let interval = null;

                    $dlg_fields = window.hWin.HEURIST4.msg.showMsgDlg(list, null,
                        {title: 'Field insert', ok: window.hWin.HR('Close')},
                        {dialogId: 'dlg-field-insert', modal: false, default_palette_class: 'ui-heurist-design', 
                            position: {
                                my: 'right-12.5 center', at: 'left center', of: this._edit_dialog
                            },
                            close: () => {
                                dialog_opened = false;
                                $dlg_fields.remove();
                                clearInterval(interval);
                            }
                        }
                    );

                    $dlg_fields.find('button').button({icon: 'ui-icon-plus'}).on('click', (event) => {
                        let cursor_pos = $txt_emailtext[0].selectionStart;
                        let value = $txt_emailtext.val();
                        let insert = `#${$(event.target).attr('data-dtyid')}#`;

                        value = `${value.slice(0, cursor_pos)}${insert}${value.slice(cursor_pos)}`;
                        $txt_emailtext.val(value).trigger('change');
                    });

                    interval = setInterval(() => {
                        if(!this._edit_dialog
                        || this._edit_dialog.dialog('instance') === undefined
                        || !this._edit_dialog.dialog('isOpen')){

                            if($dlg_fields?.length > 0 && $dlg_fields.dialog('instance') !== undefined){
                                $dlg_fields.dialog('close');
                            }

                            clearInterval(interval);
                        }
                    }, 500);
                }
            });
        }
    },

    /**
     * @brief Renders the header for the workflow rule list.
     * @override
     * @memberof heurist.manageSysWorkflowRules
     * @returns {string} HTML string for the list header.
     * Defines column headers: 'Stage', 'Restricted to', 'Ownership', 'Visibility', 'Notification'.
     */
    _recordListHeaderRenderer:function(){
        
        function __cell(colname, width){
          //return '<div style="display:table-cell;width:'+width+'ex">'+colname+'</div>';            
          return '<div style="width:'+width+'ex">'+colname+'</div>';            
        }
        
        //return '<div style="display:table;height:2em;width:99%;font-size:0.9em">'
        return __cell('Stage',24)+__cell('Restricted to',24)+__cell('Ownership',24)
                    +__cell('Visibility',25)+__cell('Notification',25);
                    
    },
    
    //----------------------
    /**
     * @brief Renders a single workflow rule item in the list.
     * @override
     * @memberof heurist.manageSysWorkflowRules
     * @param {HRecordSet} recordset The recordset containing the item.
     * @param {object} record The specific record object for the item to render.
     * @returns {string} HTML string representing the rule item.
     * Formats the display of a rule, showing its stage, restrictions, ownership changes,
     * visibility settings, and email notification details. Includes edit/delete buttons
     * if in 'manager' select mode and 'popup' edit mode.
     */
    _recordListItemRenderer:function(recordset, record){

        let that = this;

        function fld(fldname, def){

            let extra_val = '';
            if(fldname == 'swf_SendEmail'){
                
                let rty_ID = that.searchForm.searchSysWorkflowRules('getSelectedRty');
                let emails = recordset.fld(record, 'swf_EmailList');
                let field = recordset.fld(record, 'swf_RecEmailField');

                if(emails){
                    extra_val += `${emails.split(',').join('<br>')}`;
                }

                if(field){
                    extra_val += `${extra_val === '' ? '' : '<br>'}Values from: ${$Db.rst(rty_ID, field, 'rst_DisplayName')}`;
                }
            }

            let val = recordset.fld(record, fldname);
            if(val){
                if(fldname=='swf_Stage'){
                    val = $Db.trm(val,'trm_Label');
                }else{
                    if(fldname=='swf_SetVisibility' && (val=='viewable' || val=='public' || val=='hidden')){
                        return val;
                    }

                    let names = [];
                    $.each(val.split(','), function(i,item){
                        let name = window.hWin.HAPI4.sysinfo.db_usergroups[item];
                        if(!name && window.hWin.HEURIST4.allUsersCache){
                            let idx = window.hWin.HEURIST4.allUsersCache.findIndex((user) => {
                                return user.id == item;
                            });

                            if(idx >= 0){
                                name = window.hWin.HEURIST4.allUsersCache[idx].name;
                            }
                        }
                        if(name) names.push(window.hWin.HEURIST4.util.htmlEscape(name));
                    });
                    return `${names.join('<br>')}${extra_val === '' ? '' : '<br>'}${extra_val}`;
                }
            }else if(extra_val){
                val = extra_val;
            }else{
                val = def;
            }
            return window.hWin.HEURIST4.util.htmlEscape(val);
        }
        function fld2(val, col_width){
            let swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = 'width:'+col_width;
            }
            let title = val.replaceAll('<br>', "\n");
            return `<div class="truncate" style="display:inline-block;${swidth}" title="${title}">${val}</div>`;
        }

        //rem_ID,rem_RecID,rem_OwnerUGrpID,rem_ToWorkgroupID,rem_ToUserID,rem_ToEmail,rem_Message,rem_StartDate,rem_Freq,rem_RecTitle
        //rem_ToWorkgroupName
        //rem_ToUserName


        let recID   = recordset.fld(record,'swf_ID');

        let s_restrict = fld('swf_StageRestrictedTo','no restrictions'),
            s_ownership = fld('swf_SetOwnership','no changes'),
            s_visibility = fld('swf_SetVisibility','no changes'),
            s_email = fld('swf_SendEmail','no notification'),
            s_stage = fld('swf_Stage');

        let html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'
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

    },

    /**
     * @brief Sets up the "Record Email Field" selector in the workflow rule edit form.
     * @memberof heurist.manageSysWorkflowRules
     * @param {boolean} has_freetext Indicates if the current record type has any freetext fields.
     * This method enhances the `swf_RecEmailField` (which stores the ID of a freetext field).
     * It hides the original input and replaces it with a checkbox to enable/disable email field selection,
     * and an hSelect dropdown populated with all freetext fields available in the current record type
     * (selected via the search form). This allows users to choose a field whose content will be
     * used as the recipient email address for notifications.
     */
    _setupRecordEmailField: function(has_freetext){

        let rty_ID = this.searchForm.searchSysWorkflowRules('getSelectedRty');
        let ele = this._editing.getFieldByName('swf_RecEmailField'); // The hidden input storing the selected field ID

        if(!has_freetext){ // If the current record type has no freetext fields
            ele.hide(); // Hide the email field selector entirely
            return;
        }
        
        let $input = ele.find('input'); // The actual hidden input element

        // Checkbox to enable/disable selecting a field
        let $chk_Enabled = $('<input>', {
            type: 'checkbox',
            class: 'chkbx_EnableFld'
        }).insertAfter($input);

        // Select dropdown for choosing the freetext field
        let $sel_Field = $('<select>', {
            class: 'sel_RecField'
        }).insertAfter($chk_Enabled);

        // Event handler for the enable/disable checkbox
        this._on($chk_Enabled, {
            change: () => {
                window.hWin.HEURIST4.util.setDisabled($sel_Field, !$chk_Enabled.is(':checked')); // Enable/disable dropdown
                if(!$chk_Enabled.is(':checked')){
                    $input.val('').trigger('change'); // Clear hidden input if disabled
                }else if(!window.hWin.HEURIST4.util.isempty($sel_Field.val())){
                    $input.val($sel_Field.val()).trigger('change'); // Set hidden input if enabled and a field is selected
                }
            }
        });

        // Populate the dropdown with freetext fields from the current record type
        window.hWin.HEURIST4.ui.createRectypeDetailSelect($sel_Field[0], rty_ID, ['freetext'], // Filter for freetext type
            [ {key: '', title: window.hWin.HR('Select field...')} ], { // Default option
                useHtmlSelect: false, // Use hSelect
                selectedValue: $input.val(), // Pre-select if a value is already stored
                eventHandlers: {
                    onSelectMenu: (event) => { // When a field is selected from dropdown
                        let new_fld = $chk_Enabled.is(':checked') ? $sel_Field.val() : '';
                        $input.val(new_fld).trigger('change'); // Update hidden input
                    }
                }
            }
        );

        // Default to 'email' field (dty_ConceptID '1317-242') if available and nothing is selected
        let def_value = $Db.getLocalID('dty', '1317-242');
        if($sel_Field.val() !== ''){ // If a field is already selected (e.g., editing existing rule)
            $chk_Enabled.prop('checked', true); // Check the enable checkbox
        }else if($sel_Field.find(`option[value="${def_value}"]`).length == 1){ // If 'email' field exists
            $sel_Field.val(def_value).hSelect('refresh'); // Select it by default
        }

        window.hWin.HEURIST4.util.setDisabled($sel_Field, !$chk_Enabled.is(':checked')); // Set initial disabled state of dropdown

        $input.hide(); // Hide the original text input, as it's now managed by the checkbox and dropdown
    }

});
