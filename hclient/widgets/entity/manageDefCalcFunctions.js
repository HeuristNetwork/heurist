/**
* @file manageDefCalcFunctions.js
* @brief Manages Defined Calculated Functions entities.
* @fileOverview Provides a user interface for managing (CRUD operations) Defined Calculated Functions within the Heurist system. This includes listing, creating, editing, and deleting these functions.
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
// there is no search, select mode for Calculations - only edit
//
/**
 * @widget heurist.manageDefCalcFunctions
 * @brief A jQuery UI widget for managing Defined Calculated Functions entities.
 * @augments $.heurist.manageEntity
 * @property {string} [default_palette_class='ui-heurist-admin'] Default palette class for the widget.
 * @property {boolean} [use_cache=false] Whether to use caching.
 * @property {string} [edit_mode='popup'] The edit mode for the widget. Can be 'editonly' or 'popup'.
 * @property {string} [select_mode='manager'] The select mode.
 * @property {string} [layout_mode='editonly'] The layout mode, especially when edit_mode is 'editonly'.
 * @property {number} [width=1000] The width of the widget, adjusted based on edit_mode.
 * @property {number} [height=600] The height of the widget, adjusted based on edit_mode.
 * @property {function} [beforeClose] Callback function executed before the widget closes, especially in 'editonly' mode.
 * @property {boolean} [list_header=true] Whether to show the header for the result list.
 * @property {string} [title='Select formula for calculated field'] The title of the widget, especially in select_mode.
 * @property {number} [edit_height=640] The height of the edit form.
 * @property {number} [edit_width=1200] The width of the edit form.
 * @property {number} [cfn_ID] The ID of the Calculated Function to be edited in 'editonly' mode.
 * @property {number} [rst_RecTypeID] The Record Type ID used in the formula editor context.
 */
$.widget( "heurist.manageDefCalcFunctions", $.heurist.manageEntity, {
   
    _entityName:'defCalcFunctions',
    
    //keep to refresh after modifications
    _keepRequest:null,
    
    /**
     * @brief Initializes the widget. Sets up options based on edit_mode and calls the parent's _init method.
     * @memberof heurist.manageDefCalcFunctions
     * @override
     */
    _init: function() {

        if(!this.options.default_palette_class){
            this.options.default_palette_class = 'ui-heurist-admin';    
        }

        this.options.use_cache = false;

        if(this.options.edit_mode=='editonly'){
            this.options.edit_mode = 'editonly';
            this.options.select_mode = 'manager';
            this.options.layout_mode = 'editonly';
            this.options.width = 1000;
            if(!(this.options.height>0)) this.options.height = 600;
            this.options.beforeClose = function(){}; //to supress default warning

        }else{
            this.options.edit_mode = 'popup'; 
            this.options.list_header = true; //show header for resultList
            if(this.options.select_mode == 'select_single'){
                this.options.width = 790;
                this.options.height = 600;
            }
            this.options.title = "Select formula for calculated field";
        }

        this.options.edit_height = 640;
        this.options.edit_width = 1200;
        

        this._super();
    },
    
    /**
     * @brief Initializes the controls for the widget.
     * @memberof heurist.manageDefCalcFunctions
     * @override
     * @description This method is invoked from _init after loading the entity configuration.
     * It sets up the UI differently based on the 'edit_mode'.
     * If 'editonly', it loads a specific calculation record or prepares for a new one.
     * Otherwise, it sets up the search form and result list.
     * @returns {boolean} False if the parent's _initControls fails, otherwise true.
     */
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }
      
        if(this.options.edit_mode=='editonly'){
            //load calculation record for given record id
            if(this.options.cfn_ID>0){
                    let request = {};
                    request['cfn_ID']  = this.options.cfn_ID;
                    request['a']          = 'search'; //action
                    request['entity']     = this.options.entity.entityName;
                    request['details']    = 'full';
                    request['request_id'] = window.hWin.HEURIST4.util.random();
                    
                    let that = this;                                                
                    
                    window.hWin.HAPI4.EntityMgr.doRequest(request, 
                        function(response){
                            if(response.status == window.hWin.ResponseStatus.OK){
                                let recset = new HRecordSet(response.data);
                                if(recset.length()>0){
                                    that.updateRecordList(null, {recordset:recset});
                                    that.addEditRecord( recset.getOrder()[0] );
                                }
                                else {
                                    //nothing found - add new 
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
            this.searchForm.searchDefCalcFunctions(this.options);

            let iheight = 6;
            this.searchForm.css({'height':iheight+'em',padding:'10px'});
            this.recordList.css({'top':iheight+0.5+'em'});
            
            this.recordList.resultList('option','rendererHeader','');
            this.recordList.resultList('option','show_toolbar',false);
            this.recordList.resultList('option','view_mode','list');

            this.recordList.find('.div-result-list-content').css({'display':'table','width':'99%'});
            
            this._on( this.searchForm, {
                "searchdefcalcfunctionsonresult": this.updateRecordList,
                "searchdefcalcfunctionsonadd": function() { this.addEditRecord(-1); }
            });
            
        }

        return true;
    },
    /**
     * @brief Validates the input fields before saving.
     * @memberof heurist.manageDefCalcFunctions
     * @override
     * @description Extends the parent's _getValidatedValues method to add specific validation for the 'cfn_FunctionSpecification' field.
     * @returns {?object} An object containing the validated field values, or null if validation fails.
     */
    _getValidatedValues: function(){
        
        let fields = this._super();
        
        if(fields!=null){
            //validate that code is defined
            if(!fields['cfn_FunctionSpecification']){
                  window.hWin.HEURIST4.msg.showMsgFlash('You have to define formula code');
                  return null;
            }
        }
        
        return fields;
    },

    /**
     * @brief Saves the edited record and closes the edit form.
     * @memberof heurist.manageDefCalcFunctions
     * @override
     * @description Assigns record ID if in 'editonly' mode and validates record type selection before calling parent's _saveEditAndClose.
     * @param {object} fields The field values to save.
     * @param {string} afteraction The action to perform after saving.
     */
    _saveEditAndClose: function( fields, afteraction ){

        //assign record id    
        if(this.options.edit_mode=='editonly' && this.options.cfn_ID>0){
            let ele2 = this._editing.getFieldByName('cfn_ID');
            ele2.editing_input('setValue', this.options.cfn_ID );
        }

        if(!this.editForm.find('#single_rectype').is(':checked')){
            let rectypes = this._editing.getValue('cfn_RecTypeIDs');

            if(window.hWin.HEURIST4.util.isempty(rectypes)){
                window.hWin.HEURIST4.msg.showMsgErr('Please select additional record types, or uncheck the checkbox above the required field.');
                return;
            }
        }

        this._super();
    },
    
    /**
     * @brief Handles events after a record is saved.
     * @memberof heurist.manageDefCalcFunctions
     * @override
     * @description Overrides the parent's _afterSaveEventHandler. If in 'editonly' mode, it closes the dialog. Otherwise, it updates the record set and refreshes the list.
     * @param {number} recID The ID of the saved record.
     * @param {object} fieldvalues The values of the saved record.
     */
    _afterSaveEventHandler: function( recID, fieldvalues ){
        this._super( recID, fieldvalues );
        
        if(this.options.edit_mode=='editonly'){
            this.closeDialog(true);
        }else{
            this.getRecordSet().setRecord(recID, fieldvalues);    
            this.recordList.resultList('refreshPage');  
        }
    },

    /**
     * @brief Deletes the current record and closes the edit form.
     * @memberof heurist.manageDefCalcFunctions
     * @override
     * @description Prompts for confirmation before deleting, unless 'unconditionally' is true.
     * @param {boolean} unconditionally If true, deletes without confirmation.
     */
    _deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this._super(); 
        }else{
            let that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this field calculation?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
    },
    
    /**
     * @brief Performs actions after the edit form is initialized.
     * @memberof heurist.manageDefCalcFunctions
     * @override
     * @description Sets up UI elements specific to calculated functions, including the formula editor and record type selection logic.
     */
    _afterInitEditForm: function(){

        this._super();

        let $multiRecTypes = this._editing.getFieldByName('cfn_RecTypeIDs');
        let multiRecTypeIDs = this._editing.getValue('cfn_RecTypeIDs');

        if($multiRecTypes){

            let checked = this._currentEditID !== -1 && window.hWin.HEURIST4.util.isempty(multiRecTypeIDs);

            let $multiRtyChkb = $('<input>', {type: 'checkbox', id: 'single_rectype', checked: checked ? 'checked' : false});
            let $container = $('<div>', {
                html: '<div></div><span style="min-width: 40px; display: table-cell;"></span><div style="padding-bottom: 5px;"></div>'
            }).insertBefore($multiRecTypes);
            $container.find('div').last().text('The formula depends only on the record type containing the result').prepend($multiRtyChkb);

            checked ? $multiRecTypes.hide() : $multiRecTypes.show();

            this._on($multiRtyChkb, {
                change: () => {
                    if($multiRtyChkb.is(':checked')){
                        $multiRecTypes.hide();
                    }else{
                        $multiRecTypes.show();
                    }
                }
            });

            $multiRecTypes.find('.header').addClass('required').removeClass('optional');
        }

        this.formulaeditor = $( "<div>" )
                    .addClass('ent_wrapper')
                    .css({'top': '170px'})
                    .appendTo( this.editForm );
                    
        let that = this;

        let cfn_Content = this._editing.getValue('cfn_FunctionSpecification');
        let rty_ID = !window.hWin.HEURIST4.util.isPositiveInt(this.options.rst_RecTypeID) ? null : this.options.rst_RecTypeID;

        let popup_dialog_options = {
                    path: 'widgets/report/', 
                    //default_palette_class: 'ui-heurist-design',
                    keep_instance:false, 
                    
                    isCalcFieldTemplate: true, 
                    is_snippet_editor: true, 
                    
                    //rty_ID:rectypes, 
                    rec_ID: 0,
                    rty_ID: rty_ID,
                    listAllRecTypes: true,
                    
                    template_body: cfn_Content,
                    
                    isdialog: false,
                    container: this.formulaeditor,

                    onChange: function(context){
                        if(!context) return;
                        
                        that._editing.setFieldValueByName2('cfn_FunctionSpecification', context);
                    }

        };
        window.hWin.HEURIST4.ui.showRecordActionDialog('reportEditor', popup_dialog_options);

    },

    /**
     * @brief Renders the header for the result list.
     * @memberof heurist.manageDefCalcFunctions
     * @override
     * @returns {string} HTML string for the list header. Currently returns a span for spacing.
     */
    _recordListHeaderRenderer:function(){
        return '<span style="height:10px;background:none;"></span>'; // add space above result list
        /*
        function __cell(colname, width){
          //return '<div style="display:table-cell;width:'+width+'ex">'+colname+'</div>';            
          return '<div style="width:'+width+'ex">'+colname+'</div>';            
        }
        
        //return '<div style="display:table;height:2em;width:99%;font-size:0.9em">'
        return __cell('Calculation title',120);
        */            
    },
    
    //----------------------
    /**
     * @brief Renders a single item in the result list.
     * @memberof heurist.manageDefCalcFunctions
     * @override
     * @param {HRecordSet} recordset The recordset containing the data.
     * @param {object} record The record object to render.
     * @returns {string} HTML string representing the list item.
     */
    _recordListItemRenderer:function(recordset, record){
        
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, col_width){
            let swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = 'width:'+col_width;
            }
            return '<div class="truncate" style="display:inline-block;'+swidth+'">'+fld(fldname)+'</div>';
        }
        
        let recID   = fld('cfn_ID');
        
        let html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'
                + fld2('cfn_Name','50ex');
        
        // add edit/remove action buttons
        html = html 
                + '<div class="logged-in-only" style="width:90px;display: inline-block">'
                + '<div title="Click to edit calculation" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit"  style="height:16px">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                + '</div>'              
/*
                + '<div title="Click to edit calculation formula" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit-formula"  style="height:16px">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-calculator-b"></span><span class="ui-button-text"></span>'
                + '</div>'
*/                
                +'<div title="Click to delete calculation" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete"  style="height:16px;padding-left:20px">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
                + '</div></div>';
        
        html = html 
            + fld2('cfn_FunctionSpecification','50%')
            + '</div>';

        return html;
        
    },
    
    /**
     * @brief Handles actions triggered by events, extending parent's listener.
     * @memberof heurist.manageDefCalcFunctions
     * @override
     * @description Specifically handles the 'edit-formula' action to open the calculated field editor. Otherwise, calls the parent's _onActionListener.
     * @param {Event} event The event object.
     * @param {object} action The action object, typically containing `action` (string) and `recID` (number).
     */
    _onActionListener: function(event, action){

        if(action && action.action=='edit-formula'){

            window.hWin.HEURIST4.dbs.editCalculatedField(action.recID)
     
        }else{
            this._super( event, action );
        }

    },
    
});
