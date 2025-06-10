/**
* Class to perform action on set of records in popup dialog
*
* @param action_type - name of action - used to access help, widget name and method on server side
* @returns {Object}
* @see  hclient/framecontent/record for widgets
* @see  migrated/search/actions
* @see  record_action_help_xxxx in localization.txt for description and help

IT USES
    window.hWin.HAPI4.currentRecordset
    window.hWin.HAPI4.currentRecordsetSelection


*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
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

/*

1) record detail batch update
2) record type change

see    
_createInputElements - create custom input elements specific for particular action
_startAction - start the action

*/

/**
 * Constructor for the hRecordAction object.
 * This object manages the UI and logic for performing batch actions on records,
 * such as adding, replacing, or deleting details, changing record types,
 * and other specialized actions like case conversion or file operations.
 *
 * @param {string} _action_type - The type of action to perform (e.g., 'add_detail', 'replace_detail',
 *                                'delete_detail', 'rectype_change', 'extract_pdf', 'url_to_file',
 *                                'local_to_repository', 'case_conversion', 'nl2br', 'translation', 'reset_thumbs').
 *                                This determines the UI and server-side handling.
 * @param {string|number} [_scope_type] - The initial scope of records to act upon.
 *                                     Can be a string like 'All', 'Current', 'Selected', 'Collected',
 *                                     or a numeric record type ID (rtyID) to target records of a specific type.
 * @param {number} [_field_type] - The initial field type ID (dtyID) to be modified, if applicable to the action.
 * @param {*} [_field_value] - An initial value for the field, if applicable. (Currently seems unused in init).
 * @returns {object} An instance of hRecordAction with public methods.
 */
function hRecordAction(_action_type, _scope_type, _field_type, _field_value) {
    /** @const {string} _className - The name of this class module. */
    const _className = "RecordAction";
    /** @const {string} _version - The version of this class module. */
    const _version   = "0.4";

    /** @type {?jQuery} selectRecordScope - jQuery object for the 'records scope' select dropdown. */
    let selectRecordScope;
    /** @type {?number[]} allSelectedRectypes - Array of record type IDs when the scope involves multiple types from a selection. */
    let allSelectedRectypes;

    /** @type {string} action_type - Stores the current action type. */
    let action_type = _action_type;
    /** @type {string|number} init_scope_type - Stores the initial scope type passed to the constructor. */
    let init_scope_type = _scope_type;
    /** @type {number} init_field_type - Stores the initial field type ID passed to the constructor. */
    let init_field_type = _field_type;
    /** @type {*} init_field_value - Stores the initial field value passed to the constructor. (Currently seems unused). */
    let init_field_value = _field_value;
    //let repositories = ['Nakala']; // list of repositories - RETRIEVED FROM SERVER SIDE - This seems to be outdated or dynamically handled.

    /** @type {boolean} _allow_empty_replace - Flag used internally during replace actions to confirm empty replacements. */
    let _allow_empty_replace = false;
    /** @type {string[]} _default_exceptions - Array of default exceptions for case conversions. Loaded dynamically if needed. */
    let _default_exceptions = [];
    /** @type {boolean} _check_field_repeat - Flag to trigger a check for field repeatability, e.g., for translation action. */
    let _check_field_repeat = false;


    /*
    Workflow Summary:
    - Header describes the action.
    - Selector for records: all, selected, by record type.
    - Widget to enter data specific to the action.
    - Request is made to the server.
    - Results are displayed: given, processed, rejected (rights), error.
    */

    /**
     * Initializes the main UI elements of the record action dialog.
     * Sets the header text based on the action type, initializes buttons (Go, Cancel),
     * sets up the record scope selector, and calls `_fillSelectRecordScope` to populate it.
     * @private
     */
    function _init(){

        //fill header with description
        $('#div_header').html(window.hWin.HR('record_action_'+action_type));
    selector of records: all, selected, by record type
    widget to enter data
    request to server
    results
    given
    processed
    rejected (rights)
    error
    */
    function _init(){
        
        //fill header with description
        $('#div_header').html(window.hWin.HR('record_action_'+action_type));
        
        let btn_start_action = $('#btn-ok').button({label:window.hWin.HR('Go')});
        
        selectRecordScope = $('#sel_record_scope')
        .on('change',
            function(e){
                _onRecordScopeChange();
            }
        );
        btn_start_action.addClass('ui-state-disabled'); //.on('click',_startAction);        
        
        _fillSelectRecordScope();
        
        $('#btn-cancel').button({label:window.hWin.HR('Cancel')}).on('click', function(){window.close();});
    }

    /**
     * Retrieves and populates license options for the Nakala repository.
     * Makes an asynchronous call to HAPI4.RecordMgr.lookup_external_service.
     * Caches results to avoid repeated calls.
     * @private
     */
    function _populateNakalaLicense(){ // Typo: should be _populateNakalaLicense

        let $sel_license = $('#sel_license');

        if($sel_license.attr('data-init') == 'Nakala' && $sel_license.find('option').length > 0){ // already has values
            return;
        }

        let request = {
            serviceType: 'nakala',
            service: 'nakala_get_metadata',
            type: 'licenses'
        };

        window.hWin.HEURIST4.msg.bringCoverallToFront($('body'), null, 'Retrieving available licenses...');

        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, (data) => {

            window.hWin.HEURIST4.msg.sendCoverallToBack();

            data = window.hWin.HEURIST4.util.isJSON(data);

            if(data.status && data.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(data);
                window.close(); // Closes the dialog on error
                return;
            }

            if(data.length > 0){
                $.each(data, (idx, license) => {
                    window.hWin.HEURIST4.ui.addoption($sel_license[0], license, license);
                });

                $sel_license.attr('data-init', 'Nakala'); // Mark as initialized
            }else{
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: 'An unknown error has occurred while attempting to retrieve the licenses for Nakala records.',
                    error_title: 'Unable to retrieve Nakala licenses',
                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                });
                window.close(); // Closes the dialog on error
            }
        });
    }

    /**
     * Populates the 'records scope' select dropdown (#sel_record_scope).
     * Options include "All records", "Current results set", "Collected results set",
     * "Selected results set", and options for each specific record type present in the
     * current result set if no initial scope is specified.
     * Sets the initial selection based on `init_scope_type` and then calls `_onRecordScopeChange`.
     * Also handles UI setup specific to 'rectype_change' and 'reset_thumbs' actions.
     * @private
     */
    function _fillSelectRecordScope(){

        selectRecordScope.empty();

        if(!window.hWin.HAPI4.currentRecordset){ // Ensure currentRecordset exists
            window.hWin.HAPI4.currentRecordset = new HRecordSet({count:"0",offset: 0,reccount: 1,records:[], rectypes:[]});
        }

        let opt, selScope = selectRecordScope.get(0);

        opt = new Option("please select the records to be affected …", "");
        selScope.appendChild(opt);

        let is_initscope_empty = window.hWin.HEURIST4.util.isempty(init_scope_type);
        let inititally_selected = '';
        
        // Populate based on initial scope type or available options
        if(init_scope_type=='all'){
            opt = new Option("All records", "All");
            selScope.appendChild(opt);
            inititally_selected = 'All';
        }else if(init_scope_type>0 && $Db.rty(init_scope_type,'rty_Plural')){ // Specific record type
            opt = new Option($Db.rty(init_scope_type,'rty_Plural'), init_scope_type);
            selScope.appendChild(opt);
            inititally_selected = init_scope_type;
        }else{ // Default options or based on current/selected/collected sets
            if(is_initscope_empty || init_scope_type=='current'){
                opt = new Option("Current results set (count="+ window.hWin.HAPI4.currentRecordset.length()+")", "Current");
                selScope.appendChild(opt);
                inititally_selected = 'Current';
            }
            if((action_type=='rectype_change' || is_initscope_empty || init_scope_type=='collected') &&
                window.hWin.HAPI4?.currentRecordsetCollected?.length > 0)
            {
                opt = new Option("Collected results set (count=" + window.hWin.HAPI4.currentRecordsetCollected.length+")", "Collected");
                selScope.appendChild(opt);
                inititally_selected = 'Collected';
            }
            if((action_type=='rectype_change' || is_initscope_empty || init_scope_type=='selected') &&
                window.hWin.HAPI4?.currentRecordsetSelection?.length > 0)
            {
                opt = new Option("Selected results set (count=" + window.hWin.HAPI4.currentRecordsetSelection.length+")", "Selected");
                selScope.appendChild(opt);
                inititally_selected = 'Selected';
            }

            if(is_initscope_empty){ // If no specific initial scope, list record types from current set
                let rectype_Ids = window.hWin.HAPI4.currentRecordset.getRectypes();
                rectype_Ids.forEach(rty => {
                        let opt = new Option('only: '+$Db.rty(rty,'rty_Plural'), rty);
                        selScope.appendChild(opt);
                });
            }
        }

        selectRecordScope.val(inititally_selected);
        
        _onRecordScopeChange(); // Trigger subsequent UI updates
        
        // Action-specific UI setup
        if(action_type=='rectype_change'){
            $('#div_sel_rectype').show();
            _fillSelectRecordTypes();
        }else if(action_type=='reset_thumbs'){
            $('#cb_add_tags').parent().hide();
        }
    }

    /**
     * Event handler for changes to the 'records scope' select dropdown.
     * Enables or disables the 'Go' button (#btn-ok) based on whether a valid scope is selected.
     * Shows or hides the 'field to modify' selector (#div_sel_fieldtype) and calls
     * `_fillSelectFieldTypes` if the current action requires a field selection.
     * @private
     */
    function _onRecordScopeChange() {
        
        let isdisabled = (selectRecordScope.val()=='');
        
        let ele = $('#btn-ok');
        ele.off('click'); // Remove previous click handler
        if(isdisabled){
            ele.addClass('ui-state-disabled');
        }else{
            ele.removeClass('ui-state-disabled');
            ele.on('click',_startAction); // Add new click handler
        }

        // Show/hide field selector based on action type
        switch(action_type) {
            case 'add_detail':
            case 'replace_detail':
            case 'delete_detail':
            case 'extract_pdf':
            case 'url_to_file':
            case 'local_to_repository':
            case 'case_conversion':
            case 'nl2br':
            case 'translation':
                $('#div_sel_fieldtype').show();
                _fillSelectFieldTypes();
                break;
            default: // For actions like 'rectype_change', 'reset_thumbs'
                $('#div_sel_fieldtype').hide();
        }
    }

    /**
     * Populates the 'convert to record type' select dropdown (#sel_recordtype).
     * Uses `HEURIST4.ui.createRectypeSelect` for standardized dropdown creation.
     * @private
     * @returns {Array} The array of options added to the select element.
     */
    function _fillSelectRecordTypes() {
        let rtSelect = $('#sel_recordtype');
        rtSelect.empty();
        // `createRectypeSelect` likely returns the options array, though it's not captured here.
        return window.hWin.HEURIST4.ui.createRectypeSelect( rtSelect.get(0), null, window.hWin.HR('select record type'), false );
    }

    /**
     * Populates the 'field to modify' select dropdown (#sel_fieldtype).
     * The available field types depend on the selected record scope and the current `action_type`.
     * For example, 'extract_pdf' only allows 'blocktext' fields.
     * Uses `HEURIST4.ui.createRectypeDetailSelect` for standardized dropdown creation.
     * Attaches `_createInputElements` as the onchange handler for this dropdown.
     * @private
     */
    function _fillSelectFieldTypes() {

        let fieldSelect = $('#sel_fieldtype').get(0);
        if(init_scope_type>0 && typeof init_scope_type === 'number' && init_field_type > 0){ // If initial scope is a specific dty_ID (less common)
            window.hWin.HEURIST4.ui.createSelector(fieldSelect, 
                {key:init_field_type, title: $Db.dty(init_field_type, 'dty_Name')}); // Assuming init_field_type is dty_ID
                
        }else{ // Standard case: determine fields based on selected record scope
            let scope_type = selectRecordScope.val();
        
            let rtyIDs = [], dtys = {}, dtyNames = [],dtyNameToID = {},dtyNameToRty={};
            let rtys = {};

            // Determine relevant record type IDs (rtyIDs) based on scope
            if(scope_type=="All"){
                rtyIDs = null; // Show all details from all record types
            }else if(scope_type=="Current"){
                rtyIDs = window.hWin.HAPI4.currentRecordset.getRectypes();
            }else if(scope_type=="Selected" || scope_type=="Collected"){
                rtyIDs = [];
                let rec_IDs = scope_type == 'Selected' ? window.hWin.HAPI4.currentRecordsetSelection : window.hWin.HAPI4.currentRecordsetCollected;
                for(const recID of rec_IDs){
                    const record  = window.hWin.HAPI4.currentRecordset.getById(recID) ;
                    let rty = window.hWin.HAPI4.currentRecordset.fld(record, 'rec_RecTypeID');
                    if (!rtys[rty]){
                        rtys[rty] = 1;
                        rtyIDs.push(rty);
                        // Optimization: if all record types from current set are included, no need to loop further.
                        if(window.hWin.HAPI4.currentRecordset.getRectypes().length > 0 && rtyIDs.length == window.hWin.HAPI4.currentRecordset.getRectypes().length) break;
                    }
                }
                allSelectedRectypes = rtyIDs; // Store for later use if needed
            }else{ // Scope is a specific record type ID
                rtyIDs = [scope_type];
            }

            // Determine allowed field types based on the action_type
            let allowed = Object.keys($Db.baseFieldType); // Start with all base types
            allowed.splice(allowed.indexOf("separator"),1); // Remove non-data types
            allowed.splice(allowed.indexOf("relmarker"),1);
            allowed.splice(allowed.indexOf("file"),1); // Usually handled by specific actions

            if(action_type=='extract_pdf' || action_type=='nl2br'){
                allowed = ['blocktext'];
            }else if(action_type=='url_to_file' || action_type=='local_to_repository'){
                allowed = ['file'];
            }else if(action_type=='case_conversion' || action_type=='translation'){
                allowed = ['freetext','blocktext'];
            }

            window.hWin.HEURIST4.ui.createRectypeDetailSelect(fieldSelect, rtyIDs, allowed, null);
        }

        fieldSelect.onchange = _createInputElements; // When field selection changes, update input elements
        _createInputElements(); // Create initial input elements
    }

    //
    // create editing_input element for selected field type
    // create custom input elements specific for particular action
    //
    function _createInputElements(){

        let $fieldset = $('#div_widget>fieldset');

            let rtyIDs = [], dtys = {}, dtyNames = [],dtyNameToID = {},dtyNameToRty={};
            let rtys = {};

            //get record types
            if(scope_type=="All"){
                rtyIDs = null; //show all details
            }else if(scope_type=="Current"){
                rtyIDs = window.hWin.HAPI4.currentRecordset.getRectypes();
            }else if(scope_type=="Selected" || scope_type=="Collected"){
                rtyIDs = [];

                let rec_IDs = scope_type == 'Selected' ? window.hWin.HAPI4.currentRecordsetSelection : window.hWin.HAPI4.currentRecordsetCollected;

                //loop all selected records
                for(const recID of rec_IDs){

                    let rty_total_count = window.hWin.HAPI4.currentRecordset.getRectypes().length;
                    const record  = window.hWin.HAPI4.currentRecordset.getById(recID) ;
                    let rty = window.hWin.HAPI4.currentRecordset.fld(record, 'rec_RecTypeID');

                    if (!rtys[rty]){
                        rtys[rty] = 1;
                        rtyIDs.push(rty);
                        if(rtyIDs.length==rty_total_count) break;
                    }
                }

                allSelectedRectypes = rtyIDs;
                                                
            }else{
                rtyIDs = [scope_type];
            }

            let allowed = Object.keys($Db.baseFieldType);
            allowed.splice(allowed.indexOf("separator"),1);
            allowed.splice(allowed.indexOf("relmarker"),1);
           
            allowed.splice(allowed.indexOf("file"),1);
            
            if(action_type=='extract_pdf' || action_type=='nl2br'){
                allowed = ['blocktext'];    
            }else if(action_type=='url_to_file' || action_type=='local_to_repository'){
                allowed = ['file'];    
            }else if(action_type=='case_conversion' || action_type=='translation'){
                allowed = ['freetext','blocktext'];
            }

            window.hWin.HEURIST4.ui.createRectypeDetailSelect(fieldSelect, rtyIDs, allowed, null);
        }
        
        fieldSelect.onchange = _createInputElements;
        _createInputElements();
    }
    
    /**
     * Dynamically creates input elements within the '#div_widget>fieldset' container
     * based on the current `action_type` and the selected field type (dtyID from #sel_fieldtype).
     * This function is responsible for constructing the specific UI needed for each action.
     * For example:
     *  - 'add_detail': Creates one input field for the value to add.
     *  - 'replace_detail': Creates fields for "value to find" and "replace with", plus radio buttons for replacement mode.
     *  - 'delete_detail': Creates a field for "value to remove" and radio buttons for deletion mode.
     *  - 'url_to_file': Creates options for URL substring matching and filename matching.
     *  - 'local_to_repository': Creates dropdowns for repository and license (Nakala), and an option to delete local files.
     *  - 'case_conversion': Creates a dropdown for conversion type and textareas for user-defined and default exceptions.
     *  - 'translation': Creates a language selector and options for handling existing translations.
     * It calls `_createInputElement` to generate the actual Heurist `editing_input` widgets.
     * @private
     */
    function _createInputElements(){

        let $fieldset = $('#div_widget>fieldset');
        $fieldset.empty(); // Clear previous inputs

        if(action_type=='add_detail'){
            _createInputElement('fld-1', window.hWin.HR('Value to be added'));
        }else if(action_type=='replace_detail'){                              
            // UI for selecting replace mode (all, whole value, substring)
            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'
                +'<label for="cb_replace_all">Replace all values</label></div>'
                +'<input id="cb_replace_all" name="replace_type" type="radio" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'</div>').appendTo($fieldset);
            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'
                +'<label for="cb_whole_value">Replace complete value</label></div>'
                +'<input id="cb_whole_value" name="replace_type" type="radio" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'</div>').appendTo($fieldset);
            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'
                +'<label for="cb_sub_string">Replace substring</label></div>'
                +'<input id="cb_sub_string" name="replace_type" type="radio" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px" checked="checked">'
                +'</div>').appendTo($fieldset);

            // Event handler for replace_type radio buttons
            $('input[name="replace_type"]').on('change', () => {
                if ($('#cb_replace_all').is(':checked')){
                    $('#cb_add_value').parent().show(); // Option to insert if none exist
                    $('#fld-1').hide(); // Hide "value to find"
                }else{
                    $('#cb_add_value').parent().hide();
                    $('#fld-1').show();    
                }
            });

            // Checkbox for "insert as new value if none exist" (for 'replace all' mode)
            $('<div style="padding: 0.2em; width: 100%; display: none;" class="input">'
                +'<div class="header" style="padding-bottom: 10px;">'
                +'<label for="cb_add_value">Insert as new value,<br><span style="font-size: smaller;">if none exist</span></label></div>'
                +'<input id="cb_add_value" type="checkbox" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'</div>').appendTo($fieldset);
            
            _createInputElement('fld-1', window.hWin.HR('Value to find'));
            _createInputElement('fld-2', window.hWin.HR('Replace with'));
            
        }else if(action_type=='delete_detail'){
            // UI for selecting delete mode (all, substring, whole value)
            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'
                +'<label for="cb_delete_all">Remove all values</label></div>'
                +'<input id="cb_delete_all" name="delete_type" type="radio" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'</div>').appendTo($fieldset);
            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'
                +'<label for="cb_sub_string">Remove search string only</label></div>'
                +'<input id="cb_sub_string" name="delete_type" type="radio" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px" checked="checked">'
                +'</div>').appendTo($fieldset);
            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'
                +'<label for="cb_whole_value">Remove complete value</label></div>'
                +'<input id="cb_whole_value" name="delete_type" type="radio" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'</div>').appendTo($fieldset);

            _createInputElement('fld-1', window.hWin.HR('Remove value matching'));

            // Event handler for delete_type radio buttons
            $('input[name="delete_type"]').on('change', function(){ // Changed from #delete_type to input[name="delete_type"]
                if ($('#cb_delete_all').is(':checked')){
                    $('#fld-1').hide(); // Hide "value matching" field
                }else{
                    $('#fld-1').show();    
                }
            });

        }else if(action_type=='url_to_file'){
            // UI for URL to file action
            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'
                +'<label>URL contains substring</label></div>'
                +'<input id="url_substring" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'</div>').appendTo($fieldset);            
            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'
                +'<label for="cb_match_only">Match file name only</label></div>'
                +'<input id="cb_match_only" type="checkbox" checked class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'<div class="heurist-helper1 style="padding: 0.2em 0px;">Looks for existing uploaded files based solely on name, and uses these rather than fetching a new copy. This will produce unwanted results if the names are re-used eg. in different folders.'
                +'</div></div>').appendTo($fieldset);            
            
        }else if(action_type=='local_to_repository'){ //upload local file to external repository (Nakala)
            // UI for uploading local files to a repository
            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                + '<div class="header" style="padding-right: 16px;"><label for="sel_repository">Repository</label></div>'
                + '<select id="sel_repository" style="max-width:30em"><option value="">select a repository...</option></select>'
            + '</div>').appendTo($fieldset);
            // Placeholder for test server checkbox, initially hidden
            $('<div style="padding: 0.2em; width: 100%;display: none;" class="input">'
                + '<div class="header" style="padding-right: 16px;"><label for="ch_use_test_server">Use test server</label></div>' // Corrected label 'for'
                + '<input type="checkbox" id="ch_use_test_server" class="text ui-widget-content ui-corner-all" style="margin-bottom:10px">'
            + '</div>').appendTo($fieldset);
            // License selector, initially hidden, shown for Nakala
            $('<div style="padding: 0.2em; width: 100%;display: none;" class="input">'
                + '<div class="header" style="padding-right: 16px;"><label for="sel_license">License</label></div>'
                + '<select id="sel_license" style="max-width:30em" data-init="0"></select>'
            + '</div>').appendTo($fieldset);
            // Option to delete local file after successful upload
            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                + '<div class="header" style="padding-right: 16px;"><label for="cb_del_local_file">Delete local file on success </label></div>'
                + '<input id="cb_del_local_file" type="checkbox" class="text ui-widget-content ui-corner-all" style="margin-bottom:10px">'
                + '<div class="heurist-helper1 style="padding: 0.2em 0px;">Delete locally stored file(s) after successfully uploading to repository</div>'
            + '</div>').appendTo($fieldset);

            // Populate repository dropdown
            if($fieldset.find('#sel_repository').length != 0){
                window.hWin.HAPI4.SystemMgr.repositoryAction({'a': 'list', 'include_test': 1}, function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        let repositories = window.hWin.HEURIST4.util.isJSON(response.data);
                        let $sel_repos = $fieldset.find('#sel_repository');
                        for (let i = 0; i < repositories.length; i++) {
                            let repo = repositories[i];
                            window.hWin.HEURIST4.ui.addoption($sel_repos[0], repo[0], repo[1]+' > '+repo[3]); // service_id, service_label > usr_Name
                        }
                        $sel_repos.on('change', () => { // Show license selector for Nakala
                            let repo_val = $sel_repos.val();
                            if(repo_val.indexOf('nakala')===0 || repo_val.indexOf('nakala')===1){ // Check if 'nakala' is at start (index 0 or 1 if test server prefix)
                                $('#sel_license').parent().show();
                                _popuplateNakalaLicense();
                            } else {
                                $('#sel_license').parent().hide();
                            }
                        });
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
            }
        }else if(action_type=='merge_delete_detail'){ //@todo - This action is marked as to-do
            _createInputElement('fld-1', window.hWin.HR('Value to remove'), init_field_value);
            _createInputElement('fld-2', window.hWin.HR('Or repalce it with'));
        }else if(action_type=='case_conversion'){
            // UI for case conversion options and exceptions
            if($('#case_convert_op').length == 0){ // Add conversion type dropdown if not present
                $('<div style="padding: 0.2em; width: 100%;" class="input">'
                    + '<div class="header" style="padding-right: 16px;"><label>Conversion type:</label></div>'
                    + '<select id="case_convert_op" class="ui-widget-content ui-corner-all">'
                        + '<option value="1">Lowercase, capital at start of field, capitalise after fullstop followed by newline or space</option>'
                        + '<option value="2">Lowercase, capitalise start of each word</option>'
                        + '<option value="3">All lowercase</option>'
                        + '<option value="4">All capitals</option>'
                    + '</select>'
                + '</div>').insertAfter('#div_sel_fieldtype');
            }else{
                $('#case_convert_op').parent().show();
            }
            // UI for exceptions (configurable and pre-defined)
            $('<h3 style="margin: 0px;">Exceptions</h3>'
            + `<div style="font-size: 12px;display: block; padding: 10px 0px;">${window.hWin.HR('case_conversion_add')}</div>`
            + '<div style="display: block; padding: 5px 0px;"> OR '
                + '<input id="uploadWidget" type="file" style="display:none;"><button id="uploadFile">Upload file</button> encoding: '
                + '<select id="except_encode" class="ui-widget-content ui-corner-all"></select>'
            + '</div>'
            + '<div style="display: inline-block;padding: 5px 20px 5px 50px;">'
                + '<div style="display: block;"><strong>Configurable</strong></div>'
                + '<textarea id="except_user" rows="25" cols="40"></textarea>'
            + '</div>'
            + '<div style="display: inline-block;padding: 5px 50px 5px 20px;">'
                + '<div style="display: block;"><strong>Pre-defined</strong> <span style="font-size: 10px">(may be temporarily edited)</span></div>'
                + '<textarea id="except_default" rows="25" cols="40"></textarea>'
            + '</div>').appendTo($fieldset);

            window.hWin.HEURIST4.ui.initHSelect($('#case_convert_op')[0], true);
            window.hWin.HEURIST4.ui.createEncodingSelect($('#except_encode'));

            // File upload setup for exceptions list
            let $widget_upload = $('#uploadWidget').hide();
            let $btn_upload = $('#uploadFile').button().on('click', function(e){
                $widget_upload.trigger('click');
            });
            $widget_upload.fileupload({
                url: window.hWin.HAPI4.baseURL +  'hserv/controller/fileUpload.php',
                formData: [ {name:'db', value: window.hWin.HAPI4.database}, 
                            {name:'entity', value:'temp'},
                            {name:'max_file_size', value:1024*1024}],
                autoUpload: true,
                sequentialUploads:true,
                dataType: 'json',
                done: function (e, response) {
                    response = response.result;
                    if(response.status==window.hWin.ResponseStatus.OK){
                        let data = response.data;
                        $.each(data.files, function (index, file) {
                            if(file.error){
                                $('#except_user').val(file.error);
                            }else{
                                let url_get = file.deleteUrl.replace('fileUpload.php','fileGet.php')
                                    +'&encoding='+$('#except_encode').val()+'&db='+window.hWin.HAPI4.database;
                                $('#except_user').load(url_get, null);
                            }
                        });
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr({message: response.message, error_title: 'File upload error', status: response.status});
                    }
                    let inpt = this; // Re-attach click handler to button after upload completes
                    $btn_upload.off('click').on({click: function(){ $(inpt).trigger('click'); }});
                }
            });

            if(_default_exceptions.length > 0){ // Populate default exceptions if available
                $('#except_default').val(_default_exceptions.join('\n'));
            }
            $('#div_widget').css('padding-left', '0px'); // Adjust styling
            
        }else if(action_type=='translation'){
            // UI for translation action
            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                + '<div class="header" style="padding-right: 16px;"><label for="sel_language">'
                + window.hWin.HR('Target language')+'</label></div>'
                + '<select id="sel_language" style="max-width:30em" data-init="0"></select>'
            + '</div>').appendTo($fieldset);
            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                + '<div class="header" style="padding-right: 16px;"><span>Existing translations: </span></div>'
                + '<label><input id="cb_translation_asis" type="radio" name="tr_act" checked class="text ui-widget-content ui-corner-all" style="margin-bottom:10px">as is</label>&nbsp;&nbsp;&nbsp;'
                + '<label><input id="cb_translation_replace" type="radio" name="tr_act" class="text ui-widget-content ui-corner-all" style="margin-bottom:10px">Replace</label>&nbsp;&nbsp;&nbsp;'
                + '<label><input id="cb_translation_delete" type="radio" name="tr_act" class="text ui-widget-content ui-corner-all" style="margin-bottom:10px">Delete</label>'
            + '</div>').appendTo($fieldset);
          
            window.hWin.HEURIST4.ui.createLanguageSelect($fieldset.find('#sel_language'), null, null, true);
            _check_field_repeat = true; // Flag that repeatability check is needed for this action
        }
    }

    /**
     * Creates and initializes a Heurist `editing_input` widget for a specific field.
     * This function determines the appropriate record type (rtyID) based on the selected scope
     * to fetch the correct field definition (dtFields). It then configures and instantiates
     * the `editing_input` widget.
     * Special handling is included for select menus (hSelect) to ensure their dropdowns are positioned correctly.
     *
     * @private
     * @param {string} input_id - The ID to be assigned to the new input container div.
     * @param {string} input_label - The label to be displayed for this input field.
     * @param {*} [init_value=''] - An initial value for the input field.
     */
    function _createInputElement(input_id, input_label, init_value){

        let $fieldset = $('#div_widget>fieldset');
        let dtID = $('#sel_fieldtype').val(); // Get selected field type ID
        
        let rectypeID;

        if(window.hWin.HEURIST4.util.isempty(dtID)) return; // No field selected

        // Determine the relevant record type ID (rtyID)
        let scope_type = selectRecordScope.val();
        if(Number(scope_type)>0){ // Scope is a single record type
            rectypeID = Number(scope_type);
        }else{ // Scope is 'Current', 'Selected', or 'Collected' - find first relevant rtyID
            let rtyIDs_to_check;
            if(scope_type=="Current"){
                rtyIDs_to_check = window.hWin.HAPI4.currentRecordset.getRectypes();
            }else{ // Selected or Collected
                rtyIDs_to_check = allSelectedRectypes; // Use pre-calculated list of rtyIDs from selection
            }
            // Find the first record type in the scope that has the selected field (dtID)
            for (const rty_id of rtyIDs_to_check || []){ // Ensure rtyIDs_to_check is iterable
                if($Db.rst(rty_id, dtID)){
                    rectypeID = rty_id;
                    break;
                }
            }
        }

        if(window.hWin.HEURIST4.util.isempty(rectypeID)) return; // Could not determine a record type for the field

        // Adjust UI for geo fields in delete/replace actions
        let field_type = $Db.dty(dtID, 'dty_Type');
        if(field_type=='geo'){
            $('#cb_delete_all').prop('checked',true).addClass('ui-state-disabled');
            $('#cb_replace_all').prop('checked',true).addClass('ui-state-disabled');
            $('#fld-1').hide(); // Hide the "value to find/remove" field
           if(action_type=='delete_detail') return; // No further input needed for deleting geo fields
        }else{
            $('#cb_delete_all').removeClass('ui-state-disabled');
            $('#cb_replace_all').removeClass('ui-state-disabled');
        }
        
        // Show/hide substring option based on field type
        if(field_type=='freetext' || field_type=='blocktext'){
            $('#cb_sub_string').parent().show();
        }else{
            $('#cb_sub_string').parent().hide();
        }
        
        // Prepare field definition (dtFields) for the editing_input widget
        let dtFields = $Db.rst(rectypeID, dtID); // Get base definition
        if (!dtFields) return; // Should not happen if rectypeID and dtID are valid

        dtFields = JSON.parse(JSON.stringify(dtFields)); // Deep clone to avoid modifying original $Db object

        dtFields['rst_DisplayName'] = input_label;
        dtFields['rst_RequirementType'] = 'optional'; // Batch actions are always optional inputs here
        dtFields['rst_MaxValues'] = 1; // For batch actions, usually one input value is provided
        dtFields['rst_DisplayWidth'] = 50; 
        dtFields['dty_Type'] = $Db.dty(dtID, 'dty_Type'); // Ensure dty_Type is on rst_ level for widget
        dtFields['rst_PtrFilteredIDs'] = $Db.dty(dtID, 'dty_PtrTargetRectypeIDs');
        dtFields['rst_FilteredJsonTermIDTree'] = $Db.dty(dtID, 'dty_JsonTermIDTree');
        dtFields['dtID'] = dtID; // Ensure dtID is available for the widget
        
        if($Db.dty(dtID, 'dty_Type') == 'blocktext'){
            dtFields['rst_DisplayWidth'] = 80;
        }

        // Allow DB Admins to modify fields that are normally locked for modification
        let update_maymodify = dtFields['rst_MayModify'] == 'locked' && window.hWin.HAPI4.is_admin();
        dtFields['rst_MayModify'] = (update_maymodify) ? 'open' : dtFields['rst_MayModify'];
        
        if(window.hWin.HEURIST4.util.isnull(init_value)) init_value = '';

        let ed_options = {
            recID: -1, // Not editing a specific record here
            dtID: dtID,
            values: init_value,
            readonly: false,
            showclear_button: false,
            dtFields:dtFields,
            force_displayheight: (field_type=='blocktext') ? 10 : null // Custom height for blocktext
        };

        let ele = $("<div>").attr('id',input_id).appendTo($fieldset);
        ele.editing_input(ed_options);

        // Special handling for hSelect/selectmenu widgets to ensure dropdown positioning
        if(ele.find('select').length > 0){
            let id = ele.find('select').attr('id');
            let widget_ele, menu_parent;

            if(ele.find('select').hSelect('instance') != undefined){ 
                const selObj = ele.find('select');
                widget_ele = selObj.hSelect('widget');
                menu_parent = selObj.hSelect('menuWidget').parent();
            }else if($('#'+id+'-button').length > 0){
				if(parent.document && $('#'+id+'-menu', parent.document).length > 0){
					widget_ele = $('#'+id+'-button');
					menu_parent = $('#'+id+'-menu', parent.document).parent();
				}else{ // Fallback if menu widget is not found in parent, reinitialize
					$('#'+id+'-button').remove();
					const selObj = window.hWin.HEURIST4.ui.initHSelect(ele.find('select')[0], false);
					widget_ele = selObj.hSelect('widget');
					menu_parent = selObj.hSelect('menuWidget').parent();
				}
            }

            if(widget_ele && menu_parent){ // Adjust dropdown position on click
				widget_ele.on("click", function(e){
                    menu_parent.css('top', widget_ele.offset().top + 54); // Adjust based on current layout/styling
                });
                widget_ele.css({'font-size': '1em'});
            }
        }
    }

    /**
     * Retrieves the value from an `editing_input` widget.
     * @private
     * @param {string} input_id - The ID of the container div for the `editing_input` widget.
     * @returns {*|null} The first value from the widget, or null if no value.
     */
    function getFieldValue(input_id) {
        let sel = $('#'+input_id).editing_input('getValues');
        if(sel && sel.length>0){
            return sel[0];
        }else{
            return null;
        }
    }

    /**
     * Initiates the batch action process.
     * This function is typically called when the 'Go' button is clicked.
     * It first validates that a record scope has been selected.
     * If the results div is visible (meaning a previous action was completed),
     * it resets the UI for a new action.
     *
     * It then constructs a `request` object based on the `action_type` and values
     * from the dynamically created input fields. This includes:
     *  - Setting `request.a` to the specific server-side action (e.g., 'add', 'replace', 'delete').
     *  - Gathering values for new details, search/replace strings, repository info, case conversion options, etc.
     *  - Performing client-side validation (e.g., checking for empty required values).
     *  - Handling confirmations (e.g., for empty replace values, record type changes).
     *
     * Finally, it determines the list of record IDs (`request.recIDs`) based on the selected scope
     * and either calls `_startAction_continue(request)` directly or after a confirmation dialog.
     * @private
     */
    function _startAction(){
        
        if(window.hWin.HEURIST4.util.isempty(selectRecordScope.val())){
            window.hWin.HEURIST4.msg.showMsgInfo('Select records scope to be affected'); // Changed alert to showMsgInfo
            window.hWin.HEURIST4.msg.showMsgInfo('Select records scope to be affected'); // Changed alert to showMsgInfo
            return;
        }

        // If results are currently shown, reset the UI for a new action.
        if ($('#div_result').is(':visible')){
            $('#div_result').hide();
            $('#div_parameters').show();
            $('#btn-ok').button('option','label',window.hWin.HR('Go'));
            selectRecordScope.val('').trigger('change'); // Reset scope and trigger change to refresh UI
            return;
        }

        // Base request object
        let request = { tag: $('#cb_add_tags').is(':checked')?1:0 }; // Include tag option

        // Action-specific parameter gathering
        if(action_type=='reset_thumbs'){
           request['a'] = action_type; 
        } else if(action_type!='rectype_change'){ // For all actions except 'rectype_change' and 'reset_thumbs'
            let dtyID = $('#sel_fieldtype').val();
            if(window.hWin.HEURIST4.util.isempty(dtyID) && action_type!='extract_pdf') { // extract_pdf might not need a field if it works on primary file
                window.hWin.HEURIST4.msg.showMsgInfo('Field is not defined');
                return;
            }
            request['dtyID'] = dtyID;

            // Further parameters based on specific action_type
            if(action_type=='add_detail'){
                request['a'] = 'add';
                request['val'] = getFieldValue('fld-1'); 
                if(window.hWin.HEURIST4.util.isempty(request['val'])){
                    window.hWin.HEURIST4.msg.showMsgInfo('Define value to add');
                    return;
                }
            }else if(action_type=='replace_detail'){
                request['a'] = 'replace';
                if(!$('#cb_replace_all').is(':checked')){
                    request['sVal'] = getFieldValue('fld-1');
                    if(window.hWin.HEURIST4.util.isempty(request['sVal'])){
                        window.hWin.HEURIST4.msg.showMsgInfo('Define value to search');
                        return;
                    }
                    $('#cb_sub_string').is(':checked') ? request['substr'] = 1 : request['wholeval'] = 1;
                }else{
                    request['insert_new_values'] = $('#cb_add_value').is(':checked') ? 1 : 0;
                }
                request['rVal'] = getFieldValue('fld-2');
                // Handle empty replacement value with confirmation
                if(!_allow_empty_replace && window.hWin.HEURIST4.util.isempty(request['rVal'])){
                    let msg_part = request['substr'] == 1 ? '(only the search string is deleted)' : '(the whole value is deleted)';
                    let msg = 'You have not defined a replacement value<br><br>'
                            + `Click "${window.hWin.HR('OK')}" to delete the search string ${msg_part}<br>`
                            + `Click "${window.hWin.HR('Cancel')}" if you want to replace the search string with a new string`;
                    window.hWin.HEURIST4.msg.showMsgDlg(msg, 
                        () => { _allow_empty_replace = true; _startAction(); }, // No return here, _startAction will run again
                        {title: window.hWin.HR('Empty replace value'), yes: window.hWin.HR('OK'), no: window.hWin.HR('Cancel')}, 
                        {default_palette_class: 'ui-heurist-explore'}
                    );
                    return; // Stop current execution, wait for dialog
                }else if(_allow_empty_replace){ // If confirmed, proceed with empty replacement
                    request['replace_empty'] = 1;
                    _allow_empty_replace = false; // Reset flag
                }
            }else if(action_type=='url_to_file'){
                request['a'] = 'url_to_file';
                if($('#cb_match_only').is(':checked')){ request['match_only'] = 1; }
                let url_substring = $('#url_substring').val();
                if(!window.hWin.HEURIST4.util.isempty(url_substring)){ request['url_substring'] = url_substring; }
            }else if(action_type=='local_to_repository'){
                request['a'] = 'local_to_repository';
                request['repository'] = $('#sel_repository').val();
                if($('#cb_del_local_file').is(':checked')){ request['delete_file'] = 1; }
                if(request['repository'].indexOf('nakala')===0 || request['repository'].indexOf('nakala')===1){
                    request['license'] = $('#sel_license').val();
                    if(window.hWin.HEURIST4.util.isempty(request['license'])){
                        window.hWin.HEURIST4.msg.showMsgFlash('Please select a license', 3000);
                        return;
                    }
                    request['use_test_url'] = request['repository'].indexOf('nakala') === 1 ? 1 : 0; // Assuming '1' means test server
                }
            }else if(action_type=='delete_detail'){
                request['a'] = 'delete';
                if(!$('#cb_delete_all').is(':checked')){
                    request['sVal'] = getFieldValue('fld-1');
                    if(window.hWin.HEURIST4.util.isempty(request['sVal'])){
                        window.hWin.HEURIST4.msg.showMsgInfo('Define value to delete');
                        return;
                    }
                    $('#cb_sub_string').is(':checked') ? request['substr'] = 1 : request['wholeval'] = 1;
                }
            }else if(action_type=='extract_pdf' || action_type=='nl2br'){
                request['a'] = action_type;
            }else if(action_type=='case_conversion'){
                request['a'] = action_type;
                request['op'] = $('#case_convert_op').val();
                let except = ($('#except_user').val() + '\n' + $('#except_default').val()).split('\n').filter(Boolean).join('|');
                request['except'] = except;
            }else if(action_type=='translation'){
                request['a'] = action_type;
                request['lang'] = $('#sel_language').val(); 
                if($('#cb_translation_delete').is(':checked')){ request['delete'] = 1; }
                else if($('#cb_translation_replace').is(':checked')){ request['replace'] = 1; }
            }

            // Check for field repeatability if required by the action
            if(_check_field_repeat && _check_field_repeatability()){ // Note: _check_field_repeatability calls _startAction again if user confirms
                return; // Stop current execution, wait for dialog or re-trigger
            }
        }

        // Determine record IDs for the action
        let scope_type = selectRecordScope.val();
        let scope_ids_array;
        if(scope_type=="Selected" || scope_type=="Collected"){
            scope_ids_array = scope_type == 'Selected' ? window.hWin.HAPI4.currentRecordsetSelection : window.hWin.HAPI4.currentRecordsetCollected;
        }else{
            scope_ids_array = window.hWin.HAPI4.currentRecordset.getIds();
            if(scope_type!="Current" && Number(scope_type) > 0){ // If scope is a specific record type ID
                request['rtyID'] = scope_type;
            }
        }
        if (!scope_ids_array || scope_ids_array.length === 0) {
            window.hWin.HEURIST4.msg.showMsgInfo('No records selected for the action.');
            return;
        }
        request['recIDs'] = scope_ids_array.join(',');

        // Handle 'rectype_change' action with confirmation
        if(action_type=='rectype_change'){
            let rtyID_new = $('#sel_recordtype').val();
            if(!(rtyID_new>0)){
                window.hWin.HEURIST4.msg.showMsgInfo('Select new record type');
                return;
            }
            // Check if current scope is a single record type and if it's the same as the new one
            let current_rty_scope = request['rtyID']; // This might be undefined if scope was 'Current', 'All', etc.
            if(current_rty_scope && current_rty_scope == rtyID_new){
                window.hWin.HEURIST4.msg.showMsgInfo('Selected and new record types are the same');
                return;
            }
            
            request['a'] = 'rectype_change';
            request['rtyID_new'] = rtyID_new;
          
            window.hWin.HEURIST4.msg.showMsgDlg(
                'You are about to convert '
                + (current_rty_scope ?('"'+$Db.rty(current_rty_scope,'rty_Name')+'"') : scope_ids_array.length)
                +' records from their original record (entity) type into "'
                + $Db.rty(rtyID_new, 'rty_Name')
                + '" records.  This can result in invalid data for these records.<br><br>Are you sure?',
                function(){_startAction_continue(request);}, // Callback on 'Yes'
                 {title:'Warning',yes:'Proceed',no:'Cancel'});
        }else{
            _startAction_continue(request); // Proceed directly for other actions
        }
    }

    /**
     * Continues the action after initial setup and any confirmations.
     * Makes the actual HAPI call to `RecordMgr.batch_details` to perform the server-side action.
     * Displays a loading indicator during the request and processes the response
     * to show results or errors.
     *
     * The `request` object is expected to be fully formed with properties like:
     * - `a`: The server-side action to perform.
     * - `recIDs`: Comma-separated string of record IDs.
     * - `dtyID`: (if applicable) The detail type ID to modify.
     * - `rtyID`: (if applicable) Filter by this record type ID.
     * - `rtyID_new`: (for 'rectype_change') The new record type ID.
     * - `val`, `sVal`, `rVal`, `op`, `except`, `lang`, `delete`, `replace`, etc., based on the action.
     * - `tag`: 0 or 1, whether to tag affected records.
     *
     * @private
     * @param {object} request - The request object to be sent to the server.
     */
     function _startAction_continue(request)
     {   
        // show hourglass/wait icon
        $('body > div:not(.loading)').hide();
        $('.loading').show();
        
        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
            // Hide loading indicator and show main content
            $('body > div:not(.loading)').show();
            $('body > #ui-datepicker-div').hide(); // Ensure datepicker is hidden
            $('.loading').hide();

            let success = (response.status == window.hWin.ResponseStatus.OK);
            if(success){
                $('#div_parameters').hide(); // Hide input parameters section
                
                // Clean up any detached jQuery UI selectmenu menus
                $('.ui-selectmenu-menu').remove();

                $('#div_result').empty(); // Clear previous results

                let responseData = response['data']; // Server response data
 
                /*
                Server responseData structure example:
                {
                    passed: count,    // count of given rec ids
                    noaccess: count,  // no rights to edit
                    processed: count, // success
                    processed_tag: "tag_name", // if tagging was successful
                    processed_tag_error: {message: "error"}, // if tagging failed
                    undefined: count, // value not found (e.g., no PDF for extract_pdf)
                    limited: count,   // skipped (e.g., single-value field already has value in add_detail)
                    fails: count,     // records that failed processing for other reasons
                    fails_list: [id1, id2], // list of IDs for failed records
                    errors: count,    // sql error on search or update
                    errors_list: {recID: "error message"} // map of record IDs to error messages
                }
                */
                let sResult = ''; // HTML string for results
                for(let key in responseData){
                    // Process main report entries (those without '_' in key and with count > 0)
                    if(key && key.indexOf('_')<0 && responseData[key]>0){
                        const lbl_key = 'record_action_'+key;
                        let lbl = window.hWin.HR(lbl_key); // Try generic localization
                        if(lbl==lbl_key){ // If not translated, try action-specific localization
                            lbl = window.hWin.HR(lbl_key+'_'+action_type);
                        }

                        // Create links for viewing tagged records or recent changes
                        let tag_link = '';
                        if(responseData[key+'_tag']){ // Link to view records with the new tag
                            tag_link = '<span><a href="'+
                            encodeURI(window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                                +'&q=tag:"'+responseData[key+'_tag']+'"')+
                            '&nometadatadisplay=true" target="_blank">view</a></span>';
                        }else if(responseData[key+'_tag_error']){ // Display tagging error
                            tag_link = '<span>'+responseData[key+'_tag_error']['message']+'</span>';
                        }else if(key=="processed" && action_type!='reset_thumbs'){ // Link to view recent changes for processed records
                            tag_link = '<span><a href="'+
                            encodeURI(window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                                +'&q=sortby:-m after:"5 minutes ago"')+
                            '&nometadatadisplay=true" target="_blank">view recent changes</a></span>';
                        }else if(key=='fails' && responseData['fails_list'] && responseData['fails_list'].length>0){ // Link to view failed records
                            tag_link = '<span style="background-color:#ffcccc"><a href="'+
                            encodeURI(window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                                +'&q=ids:'+responseData['fails_list'].join(','))+
                            '&nometadatadisplay=true" target="_blank">view</a></span>';
                        }else if(key == 'limited' && action_type == 'add_detail' && responseData[key] > 0){ // Specific message for 'limited' in 'add_detail'
                            tag_link = `<span style="display: block; font-size: 0.9em; padding: 5px 5px;">
                                            For single value fields which were skipped because they already have a value,<br>
                                            use "Recode > Replace field value" to replace all, or selected, existing values with the new value.
                                        </span>`;
                        }
                        
                        sResult += `<div style="padding:4px"><span>${lbl}</span><span>&nbsp;&nbsp;${responseData[key]}</span>${tag_link}</div>`;
                        
                        // Display detailed errors if any
                        if(key=='errors' && responseData['errors_list']){
                            let error_recids = Object.keys(responseData['errors_list']);
                            if(error_recids.length > 0){
                                sResult += '<div style="max-height:300px;overflow-y:auto;background-color:#ffcccc;padding:5px;margin-left:10px;">'; // Corrected max-height
                                for(let err_recID in responseData['errors_list']){
                                    let error_text = responseData['errors_list'][err_recID];
                                    if(Array.isArray(error_text)){ error_text = error_text.join('<br>'); }
                                    sResult += `Record ID ${err_recID}: ${error_text}<br>`;
                                }
                                sResult += '</div>';   
                            }
                        }
                    }
                }

                // Update UI with results and change button labels
                $('#div_result').html(sResult).css({padding:'10px'}).show();
                $('#btn-ok').button('option','label',window.hWin.HR('New Action'));
                $('#btn-cancel').button('option','label',window.hWin.HR('Close'));

            }else{ // Request failed
                $('#div_result').hide(); // Ensure results div is hidden
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    }

    /**
     * Checks if the field selected for translation is repeatable for the given record type.
     * If the field is single-value, it prompts the user with an option to make the field
     * repeatable before proceeding with the translation. This is to avoid issues with
     * storing multiple translations in a single-value field.
     *
     * This function can be asynchronous due to the confirmation dialog and potential
     * HAPI call to update the field definition.
     *
     * @private
     * @returns {boolean} True if a dialog was shown (halting current execution flow),
     *                    false if the field is already repeatable or not a single record type scope,
     *                    or if no field is selected.
     */
    function _check_field_repeatability(){

        let rty_ID = selectRecordScope.val();
        // Only proceed if scope is a single, numeric record type ID
        if(!window.hWin.HEURIST4.util.isNumber(rty_ID) || !Number.isInteger(Number(rty_ID)) || Number(rty_ID) <= 0){
            return false;
        }

        let dty_ID = $('#sel_fieldtype').val();
        if (!dty_ID || dty_ID <= 0) return false; // No field selected

        // If field's MaxValues is not 1 (i.e., it's already repeatable or has no limit), no check needed.
        if($Db.rst(rty_ID, dty_ID, 'rst_MaxValues') != 1){
            return false;
        }

        // Warn about non-repeatable fields and offer to change it.
        let $dlg = null;
        let msg = `To avoid issues with editing the affected records in the future, we first recommend making the field "${$Db.rst(rty_ID, dty_ID, 'rst_DisplayName')}" repeatable.<br><br>Would you like to make the field repeatable?`;

        let btns = {};
        btns[window.hWin.HR('Yes')] = function(){
            window.hWin.HEURIST4.msg.bringCoverallToFront($('body'), null, 'Updating field definition...');
            let fields = {
                'rst_DetailTypeID': dty_ID,
                'rst_RecTypeID': rty_ID,
                'rst_MaxValues': 0 // 0 means repeatable (no limit)
            };
            let request = {
                a: 'save',
                entity: 'defRecStructure',
                fields: fields,
                request_id: window.hWin.HEURIST4.util.random()
            };
            window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                if ($dlg) $dlg.dialog('close');
                if(response.status != window.hWin.ResponseStatus.OK){
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    return;
                }
                // Refresh structure definitions and then re-attempt the original action
                window.hWin.HAPI4.EntityMgr.refreshEntityData('rst', _startAction);
            });
        };
        btns[window.hWin.HR('No, and continue with recode')] = function(){
            if ($dlg) $dlg.dialog('close');
            _check_field_repeat = false; // User chose not to make it repeatable, proceed with original action
            _startAction();
        };
        btns[window.hWin.HR('Cancel')] = function(){
            if ($dlg) $dlg.dialog('close');
        };

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, {title: 'Field repeatability', yes: window.hWin.HR('Yes'), no: window.hWin.HR('No, and continue with recode'), cancel: window.hWin.HR('Cancel')}, {default_palette_class: 'ui-heurist-design'});
        return true; // Dialog was shown, halting current _startAction flow.
    }

    //public members
    let that = {
        /**
         * Gets the class name of this module.
         * @returns {string} The class name "RecordAction".
         */
        getClass: function () {return _className;},
        /**
         * Checks if the given string matches the class name of this module.
         * @param {string} strClass - The class name to check.
         * @returns {boolean} True if `strClass` is "RecordAction", false otherwise.
         */
        isA: function (strClass) {return (strClass === _className);},
        /**
         * Gets the version of this module.
         * @returns {string} The version number.
         */
        getVersion: function () {return _version;},
    }

    
    _init();
    return that;  //returns object
}
