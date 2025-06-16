/**
 * @file lookupBase.js
 * @brief Base widget for all lookup widgets in Heurist.
 *
 * @fileOverview
 * This file defines the `heurist.lookupBase` jQuery UI widget.
 * This widget provides a base for all lookup widgets in the Heurist system.
 * It handles the common functionality for searching external services,
 * displaying results, and mapping selected data to Heurist record fields.
 *
 * @package     Heurist academic knowledge management system
 * @subpackage  hclient\widgets\lookup
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Brandon McKay <blmckay13@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since       6.0
 */

/* global stringifyMultiWKT */

/**
 * Base widget for all lookup widgets.
 * Inherits from `$.heurist.recordAction`.
 *
 * @widget heurist.lookupBase
 * @extends $.heurist.recordAction
 */
$.widget( "heurist.lookupBase", $.heurist.recordAction, {

    /**
     * Default options for the widget.
     * These options can be overridden during widget initialization.
     * Options from `$.heurist.recordAction` are also available.
     * @memberof heurist.lookupBase
     * @instance
     * @property {Object} options
     * @property {number} [options.height=700] - The height of the dialog.
     * @property {number} [options.width=800] - The width of the dialog.
     * @property {boolean} [options.modal=true] - Whether the dialog is modal.
     * @property {string} [options.title="External lookup"] - The title of the dialog.
     * @property {string} [options.htmlContent='lookupBase.html'] - The HTML content file for the dialog, located in `hclient/widgets/lookup`.
     * @property {?string} [options.helpContent=null] - The help content file for the dialog, located in `documentation/context_help`.
     * @property {?Object} options.mapping - Configuration from `record_lookup_config.json`. Defines how external data fields map to Heurist fields.
     *   @property {number} options.mapping.rty_ID - The Heurist record type ID for the mapping.
     *   @property {string} options.mapping.service_id - The ID of the external service.
     *   @property {Object} options.mapping.fields - An object mapping external field names to Heurist data type IDs (dty_ID).
     * @property {?Object} options.edit_fields - Realtime values from the edit form fields.
     * @property {boolean|HRecordSet} [options.edit_record=false] - The recordset of the current record being edited, or `false` if not applicable.
     * @property {boolean} [options.add_new_record=false] - If `true`, creates a new record upon selection.
     * @property {Object} options.resultList - Options for the result list widget.
     *   @property {string} [options.resultList.recordDivEvenClass='recordDiv_blue'] - CSS class for even record divs.
     *   @property {boolean} [options.resultList.eventbased=false] - If `false`, the result list will not listen to global events.
     *   @property {boolean} [options.resultList.multiselect=false] - If `false`, allows only one record to be selected.
     *   @property {string} [options.resultList.select_mode='select_single'] - Selection mode. Typically 'select_single'.
     *   @property {string} [options.resultList.selectbutton_label='select!!'] - Label for the select button (not currently used).
     *   @property {string} [options.resultList.view_mode='list'] - Default view mode for the result list (e.g., 'list', 'icon', 'thumb').
     *   @property {boolean} [options.resultList.show_viewmode=false] - Whether to show view mode selection controls.
     *   @property {number} [options.resultList.pagesize=20] - Number of records to display per page.
     *   @property {string} [options.resultList.entityName='Lookups'] - Name of the entity being listed (e.g., "Lookups", "Records").
     *   @property {string} [options.resultList.empty_remark='<div style="padding:1em 0 1em 0">No records match the search</div>'] - HTML displayed when no results are found.
     *   @property {?Array<Object>} [options.resultList.action_buttons] - Additional action buttons to display within each record row. Handler is `resultlistonaction`.
     */
    options: {

        height: 700,
        width:  800,
        modal:  true,
        
        title:  "External lookup",
        
        htmlContent: 'lookupBase.html', // in hclient/widgets/lookup folder
        helpContent: null, // in documentation/context_help folder

        mapping: null, // configuration from record_lookup_config.json
        edit_fields: null, // realtime values from edit form fields
        edit_record: false, // recordset of the current record being edited

        add_new_record: false, // if true it creates new record on selection

        resultList: {

            recordDivEvenClass: 'recordDiv_blue',
            eventbased: false,  //do not listent global events

            multiselect: false, // allow only one record to be selected
            select_mode: 'select_single', // only accept one record for selection
            selectbutton_label: 'select!!', // not used

            view_mode: 'list', // result list viewing mode [list, icon, thumb]
            show_viewmode: false,
            pagesize: 20, // number of records to display per page,

            entityName: 'Lookups',

            empty_remark: '<div style="padding:1em 0 1em 0">No records match the search</div>' // For empty results

            //action_buttons - additional buttons within each record row, handler is resultlistonaction
        }
    },

    /**
     * The base URL for the external lookup service.
     * @memberof heurist.lookupBase
     * @instance
     * @type {string}
     */
    baseURL: '',

    /**
     * The name of the external service.
     * @memberof heurist.lookupBase
     * @instance
     * @type {string}
     */
    serviceName: '',

    /**
     * Reference to the jQuery UI resultList widget instance.
     * Used for displaying search results.
     * @memberof heurist.lookupBase
     * @instance
     * @type {?jQuery}
     */
    recordList: null,

    /**
     * Label for the main action button in the dialog (e.g., "Select").
     * @memberof heurist.lookupBase
     * @instance
     * @type {string}
     */
    action_button_label: 'Select',

    /**
     * jQuery object representing the search button(s).
     * @memberof heurist.lookupBase
     * @instance
     * @type {?jQuery}
     */
    search_buttons: null,

    /**
     * Selector used to find the search button(s) in the dialog.
     * @memberof heurist.lookupBase
     * @instance
     * @type {string}
     */
    search_button_selector: '#btnStartSearch',

    /**
     * jQuery object representing the button for saving extra settings/options.
     * @memberof heurist.lookupBase
     * @instance
     * @type {?jQuery}
     */
    save_settings: null,

    /**
     * Flag to control dialog closing behavior. If true, skips saving additional mapping and closes the dialog.
     * @memberof heurist.lookupBase
     * @instance
     * @private
     * @type {boolean}
     */
    _forceClose: true,

    /**
     * jQuery object representing the tabs container, used to separate query and results sections.
     * @memberof heurist.lookupBase
     * @instance
     * @type {?jQuery}
     */
    tabs_container: null,

    /**
     * Index of the tab that displays the result list.
     * @memberof heurist.lookupBase
     * @instance
     * @type {number}
     */
    results_tab: 1,

    /**
     * Object to manage timeouts for certain actions.
     * @memberof heurist.lookupBase
     * @instance
     * @property {?number} timeout.action_timeout - Timeout ID for actions like mapping values.
     * @property {string} timeout.field_name - Name of the field associated with the timeout.
     * @property {string|Object|Array} timeout.value - Value associated with the timeout.
     * @type {Object}
     */
    timeout: {
        action_timeout: null, // Timeout for certain actions
        field_name: '',
        value: ''
    },

    /**
     * Initializes the widget. Calls the parent's `_init` method.
     * Note: HTML and dialog are not yet loaded at this stage.
     * @memberof heurist.lookupBase
     * @instance
     * @private
     */
    _init: function(){
        this._super(); // whatever you do before this bare in mind that the html and dialog haven't been loaded yet
    },

    /**
     * Initialises various UI elements; buttons, selects, result list, etc.
     * This method is called after the dialog HTML is loaded.
     * It sets up the result list, tabs, search buttons, save settings button,
     * and event handlers for user interactions.
     * @memberof heurist.lookupBase
     * @instance
     * @private
     * @returns {void} Calls `this._super()`.
     */
    _initControls: function(){

        // Init record list
        this.recordList = this.element.find('#div_result');
        if(this.recordList.length > 0){

            let context = this;

            // Record render function, is called on resultList updateResultSet
            this.options.resultList['renderer'] = (recordset, record) => { return context._rendererResultList(recordset, record); };

            this.recordList.resultList(this.options.resultList);
            this.recordList.resultList('option', 'pagesize', this.options.resultList.pagesize); // so the pagesize doesn't get set to a different value

            // Init select & double click events for result list
            this._on( this.recordList, {
                resultlistonselect: function(event, selected_recs){
                    window.hWin.HEURIST4.util.setDisabled(
                        this.element.parents('.ui-dialog').find('.btnDoAction'),
                        selected_recs && selected_recs.length() < 1
                    );
                },
                resultlistondblclick: function(event, selected_recs){
                    if(selected_recs && selected_recs.length()==1){
                        this.doAction();
                    }
                }
                /**
                 * resultlistonpagerender - on loading the result list
                 * resultlistonaction - on clicking an action button within the record row (action buttons are defined in option action_buttons)
                 */
            });
        }

        // Init tabs
        if(this.element.find('#tabs-cont').length > 0){
            this.tabs_container = this.element.find('#tabs-cont').tabs();
        }

        // Init search button(s)
        this.search_buttons = this.element.find(this.search_button_selector);
        if(this.search_buttons.length > 0){

            // Action button styling
            this.search_buttons.addClass("ui-button-action").button();

            // Handling for 'Search' button
            this._on(this.search_buttons, {
                click: this._doSearch
            });

            // Set search button status based on the existence of input
            this._on(this.element.find('input, select, .search-input'), {
                keyup: () => {
                    let $inputs_with_value = this.element.find('input, select, .search-input').filter((idx, ele) => { 
                        return !window.hWin.HEURIST4.util.isempty($(ele).val());
                    });
                    window.hWin.HEURIST4.util.setDisabled(this.search_buttons, $inputs_with_value.length == 0);
                },
                change: () => {
                    let $inputs_with_value = this.element.find('input, select, .search-input').filter((idx, ele) => { 
                        return !window.hWin.HEURIST4.util.isempty($(ele).val());
                    });
                    window.hWin.HEURIST4.util.setDisabled(this.search_buttons, $inputs_with_value.length == 0);
                }
            });
            window.hWin.HEURIST4.util.setDisabled(this.search_buttons, true);
        }

        // Init save settings button
        this.save_settings = this.element.find('#save-settings');
        if(this.save_settings.length > 0){

            this.save_settings.button();

            this._on(this.save_settings, {
                click: () => {
                    this._saveExtraSettings(true, false);
                }
            });
        }

        // For capturing the 'Enter' key while typing
        this._on(this.element.find('input.search_on_enter'), {
            keypress: this.startSearchOnEnterPress
        });

        // Save extra settings before exiting
        this._on(this._as_dialog, {
            dialogbeforeclose: () => {
                if(!this._forceClose){
                    this._forceClose = true;
                    this._saveExtraSettings(true, true);
                    return false;
                }
            }
        });

        // Setup settings tab
        this._setupSettings();

        // By default action button is disabled
        window.hWin.HEURIST4.util.setDisabled(this.element.parents('.ui-dialog').find('.btnDoAction'), true);

        return this._super();
    },

    /**
     * Handles the 'Enter' key press event on input fields with the class `search_on_enter`.
     * If 'Enter' is pressed, it prevents the default action, stops event propagation,
     * and triggers the `_doSearch` method.
     *
     * @memberof heurist.lookupBase
     * @instance
     * @private
     * @param {jQuery.Event} e - The keypress event object.
     */
    startSearchOnEnterPress: function(e){
        if(e.key === 'Enter' || e.key === 'NumpadEnter'){
            window.hWin.HEURIST4.util.stopEvent(e);
            e.preventDefault();
            this._doSearch();
        }
    },

    /**
     * Gets and customizes the dialog buttons for the bottom bar.
     * This method overrides the `_getActionButtons` from `$.heurist.recordAction`.
     * It retrieves the default buttons (typically "Cancel" and "Go") and
     * customizes the "Go" button's text using `this.action_button_label`.
     *
     * The button array structure is typically:
     * - `buttons[0]`: Cancel/Close button (closes the dialog).
     * - `buttons[1]`: Go/Select button (calls `doAction`).
     *
     * @memberof heurist.lookupBase
     * @instance
     * @private
     * @returns {Array<Object>} An array of dialog button objects, each with properties like `text` and `click`.
     * @see $.heurist.recordAction#_getActionButtons
     */
    _getActionButtons: function(){

        let buttons = this._super(); // setup and retrieve default dialog buttons

        buttons[1].text = window.hWin.HR(this.action_button_label);

        return buttons;
    },

    /**
     * Renders the HTML representation for a single record in the result list.
     * This function is typically passed as a callback to the `resultList` widget.
     * It constructs HTML displaying the record's icon, title, and ID.
     *
     * @memberof heurist.lookupBase
     * @instance
     * @private
     * @param {HRecordSet} recordset - The complete record set containing the record.
     * @param {Array} record - The individual record object (row) from the recordset to be rendered.
     *                         This is an array where indices correspond to field positions.
     * @returns {string} HTML string representing the formatted record for display.
     */
    _rendererResultList: function(recordset, record){

        const rec_ID = recordset.fld(record, 'rec_ID');
        const rec_Title = recordset.fld(record, 'rec_Title');
        const rec_RecTypeID = recordset.fld(record, 'rec_RecTypeID');
        const rec_Icon = window.hWin.HAPI4.iconBaseURL + rec_RecTypeID;

        const thumb = `<div class="recTypeThumb" style="background-image: url(&quot;${rec_Icon}&version=thumb&quot;);"></div>`;

        const html = `<div class="recordDiv" id="rd${rec_ID}" recid="${rec_ID}" rectype="${rec_RecTypeID}">`
                    + thumb
                    + '<div class="recordIcons">'
                        + `<img src="${window.hWin.HAPI4.baseURL}hclient/assets/16x16.gif" class="rt-icon" `
                        +   `style="background-image: url(&quot;${rec_Icon}&quot;)" />`
                    + '</div>'
                    + rec_Title
                + '</div>';

        return html;
    },

    /**
     * Processes the user's selection from the result list.
     * This method is typically called when the main action button (e.g., "Select") is clicked.
     * It retrieves the selected record, prepares the values for Heurist mapping using `prepareValues`,
     * and then calls `closingAction` to pass the data back and close the dialog.
     *
     * @memberof heurist.lookupBase
     * @instance
     * @param {string} [url_fld=''] - The field key from the selected record that contains an external URL, if applicable.
     *                                If provided and the field exists, its value will be added to the `ext_url` property
     *                                of the response object.
     * @returns {void}
     */
    doAction: function(url_fld = ''){

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){
            return;
        }

        let res = {};

        if(!window.hWin.HEURIST4.util.isempty(url_fld) && recset.getFields().indexOf(url_fld) !== -1){
            res['ext_url'] = recset.fld(record, url_fld);
        }

        res = this.prepareValues(recset, record, res);

        this.closingAction(res);
    },

    /**
     * Prepares the selected record's field values for Heurist mapping.
     * It iterates over the fields defined in `this.options.mapping.fields`,
     * retrieves the corresponding values from the selected record,
     * processes them using `prepareValue`, and organizes them into a
     * response object. The response object maps Heurist data type IDs (dty_ID)
     * to their respective values.
     *
     * For multi-value fields, the dty_ID will map to an array of values.
     *
     * To trigger a record pointer selection/creation popup in Heurist,
     * a value for a record pointer field should be an array `[dty_ID, default_searching_value]`.
     *
     * An external URL can be included in the response by adding an `ext_url` property
     * to `dlg_response`. This URL will be displayed in the record pointer guiding popup.
     * Example: `dlg_response['ext_url'] = '<a href="www.example.com" target="_blank">Link to Example</a>';`
     *
     * @memberof heurist.lookupBase
     * @instance
     * @param {HRecordSet} recordset - The HRecordSet containing the selected record.
     * @param {Array} record - The selected record object (row) from the recordset.
     * @param {Object} [dlg_response={}] - An initial response object to which mapped values will be added.
     * @param {Object} [extra_settings={}] - Additional settings that might be used by `prepareValue` (e.g., for term code checking).
     * @returns {Object} An object containing the mapped field values, where keys are dty_IDs and
     *                   values are the corresponding processed data.
     */
    prepareValues: function(recordset, record, dlg_response = {}, extra_settings = {}){

        if(!window.hWin.HEURIST4.util.isObject(dlg_response)){
            dlg_response = {};
        }

        let map_flds = Object.keys(this.options.mapping.fields); // mapped fields names, to access fields of rec

        // Assign individual field values, here you would perform any additional processing for selected values (example. get ids for vocabulrary/terms and record pointers)
        for(let fld_Name of map_flds){

            let dty_ID = this.options.mapping.fields[fld_Name];
            if(dty_ID < 1){
                continue;
            }

            let values = recordset.fld(record, fld_Name);

            this.prepareValue(values, dty_ID, extra_settings);

            // Check that values is valid, add to response object
            if(window.hWin.HEURIST4.util.isempty(values)){
                continue;
            }
            if(!Object.hasOwn(dlg_response, dty_ID)){
                dlg_response[dty_ID] = [];
            }
            dlg_response[dty_ID] = dlg_response[dty_ID].concat(values);
        }

        return dlg_response;
    },

    /**
     * Processes a single field's value(s) based on its Heurist field type (dty_Type).
     * This method is called by `prepareValues` for each mapped field.
     * It converts the input `values` to an array, and then, based on the `dty_Type`
     * (e.g., 'enum', 'resource', 'relmarker'), it may perform specific processing:
     * - For 'enum' (vocabulary/term) fields: It can check term codes using `_getTermByCode` if `extra_settings.check_term_codes` is provided.
     * - For 'resource' (record pointer) or 'relmarker' (relationship marker) fields: It processes values using `_processRecordFields`.
     * - For other field types: It processes values using `_processValues` (typically trimming strings).
     *
     * The `values` parameter is modified in place by the processing methods.
     *
     * @memberof heurist.lookupBase
     * @instance
     * @param {string|Object|Array<*>} values - The value or values to be processed for the field.
     *                                        This will be converted into an array.
     * @param {number} dty_ID - The Heurist data type ID of the field. Used to determine the field type.
     * @param {Object} extra_settings - Additional settings that may influence processing (e.g., `check_term_codes` for 'enum' fields).
     * @returns {void}
     */
    prepareValue: function(values, dty_ID, extra_settings){

        values = this.valueToArray(values);

        if(window.hWin.HEURIST4.util.isempty(values)){
            return;
        }

        const field_type = $Db.dty(dty_ID, 'dty_Type');

        switch(field_type){
            case 'enum':
                // Match term labels with val, need to return the term's id to properly save its value
                if(Object.hasOwn(extra_settings, 'check_term_codes')){
                    this._getTermByCode(extra_settings.check_term_codes, dty_ID, values);
                }
                break;

            case 'resource':
            case 'relmarker':
                this._processRecordFields(values);
                break;

            default:
                this._processValues(values);
                break;
        }
    },

    /**
     * Performs final actions before closing the lookup dialog.
     * This includes clearing any active timeout (`this.timeout.action_timeout`),
     * ensuring the `dlg_response` is an object, optionally filtering out empty
     * values from the response, hiding the loading coverall, and finally,
     * setting `this._context_on_close` to the `dlg_response` and closing the dialog.
     *
     * @memberof heurist.lookupBase
     * @instance
     * @param {Object|boolean} dlg_response - The object containing mapped values to be returned to the calling context.
     *                                      If `false`, an empty object is typically returned.
     * @param {boolean} [check_for_empty=true] - If `true`, iterates through the `dlg_response` properties.
     *                                         If a property's value is an array, it filters out empty values from that array.
     *                                         It also ensures all property values are arrays.
     * @returns {void}
     */
    closingAction: function(dlg_response, check_for_empty = true){

        if(this.timeout.action_timeout){
            clearTimeout(this.timeout.action_timeout); // clear timeout
        }

        if(dlg_response !== false && window.hWin.HEURIST4.util.isempty(dlg_response)){
            dlg_response = {};
        }

        if(check_for_empty){

            for(const ID in dlg_response){

                if(!Array.isArray(dlg_response[ID])){
                    dlg_response[ID] = [dlg_response[ID]];
                    continue;
                }

                dlg_response[ID] = dlg_response[ID].filter((value) => !window.hWin.HEURIST4.util.isempty(value));
            }
        }

        window.hWin.HEURIST4.msg.sendCoverallToBack(true);

        // Pass mapped values back and close dialog
        this._context_on_close = dlg_response;
        this._as_dialog.dialog('close');
    },

    /**
     * Displays the search results using the Heurist `resultList` widget.
     * It handles different states of the `data` received:
     * - If `data` is invalid or indicates an error, it clears the result list and shows an error message.
     * - If `data` is `null` (e.g. no results from a non-erroring query), it clears the result list.
     * - If `data` is valid, it creates an `HRecordSet` (either from raw data or if `is_record_set` is true)
     *   and updates the `resultList` widget with this recordset.
     * It also ensures the results tab is active if tabs are used.
     *
     * @memberof heurist.lookupBase
     * @instance
     * @private
     * @param {Object|boolean|null} data - The data to display.
     *                                   - If `is_record_set` is `false`, `data` should be an object with `fields`, `order`, and `records` properties.
     *                                   - If `is_record_set` is `true`, `data` should be an object suitable for `new HRecordSet(data)`.
     *                                   - Can be `false` or `null` to indicate errors or no data.
     * @param {boolean} [is_record_set=false] - Whether the provided `data` is already in `HRecordSet` constructor format
     *                                          (typically a response from a Heurist API endpoint).
     * @returns {void}
     */
    _onSearchResult: function(data, is_record_set=false){

        this.recordList.show();

        let invalid_data = data === false 
            || !window.hWin.HEURIST4.util.isObject(data) 
            || (!is_record_set && (!Object.hasOwn(data, 'fields') || !Object.hasOwn(data, 'order') || !Object.hasOwn(data, 'records')));

        if(invalid_data && data !== null){

            this.recordList.resultList('updateResultSet', null);
            window.hWin.HEURIST4.msg.showMsgErr({
                message: 'Service did not return data in an appropriate format',
                error_title: 'No valid data'
            });

            return;
        }else if(!data){
            this.recordList.resultList('updateResultSet', null);
            return;
        }

        let res_recordset = null;
        if(!is_record_set){

            let fields = data.fields;
            let orders = data.order;
            let records = data.records;

            res_recordset = new HRecordSet({
                count: orders.length,
                offset: 0,
                fields: fields,
                rectypes: [this.options.mapping.rty_ID],
                records: records,
                order: orders,
                mapenabled: true
            });
        }else{
            res_recordset = new HRecordSet(data);
        }

        this.recordList.resultList('updateResultSet', res_recordset);

        if(this.tabs_container && this.tabs_container.tabs('instance') !== undefined){
            this.tabs_container.tabs('option', 'active', this.results_tab);
        }
    },

    /**
     * Placeholder method for loading extra settings or options.
     * This method is intended to be overridden by specific lookup widgets
     * if they need to load or initialize any specific settings in the UI,
     * particularly within a settings tab.
     *
     * @memberof heurist.lookupBase
     * @instance
     * @private
     * @returns {void}
     */
    _setupSettings: function(){
        return;
    },

    /**
     * Saves extra settings for the lookup service.
     * These settings are typically specific to the external service being used
     * and are stored in the `sysIdentification` record's `sys_ExternalReferenceLookups` field
     * as a JSON string.
     *
     * If `settings` is provided and is an object, it updates the service configuration
     * for the current `service_id` (from `this.options.mapping.service_id`) and saves it.
     * After a successful save, it updates `window.hWin.HAPI4.sysinfo['service_config']`
     * and `this.options.mapping` with the new configuration.
     *
     * If `close_dlg` is true, the dialog will be closed after the operation,
     * regardless of whether settings were actually saved (e.g., if `settings` was not provided).
     *
     * @memberof heurist.lookupBase
     * @instance
     * @private
     * @param {Object|boolean} [settings] - An object containing the extra settings to be saved for the service.
     *                                   If `false` or not an object, no settings are saved, but the dialog might still close if `close_dlg` is true.
     * @param {boolean} [close_dlg=false] - Whether to close the dialog after attempting to save settings (or if no settings were provided to save).
     * @returns {void}
     */
    _saveExtraSettings: function(settings, close_dlg = false){

        let that = this;

        let services = window.hWin.HEURIST4.util.isJSON(window.hWin.HAPI4.sysinfo['service_config']);

        if(services !== false && window.hWin.HEURIST4.util.isObject(settings)){

            let service_id = this.options.mapping.service_id;
            services[service_id]['options'] = settings;

            let fields = {
                'sys_ID': 1,
                'sys_ExternalReferenceLookups': JSON.stringify(services)
            };
    
            // Update sysIdentification record
            let request = {
                'a': 'save',
                'entity': 'sysIdentification',
                'request_id': window.hWin.HEURIST4.util.random(),
                'isfull': 0,
                'fields': fields
            };
    
            window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){
    
                if(response.status != window.hWin.ResponseStatus.OK){
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    return;
                }

                window.hWin.HAPI4.sysinfo['service_config'] = window.hWin.HEURIST4.util.cloneJSON(services); // update global copy

                if(close_dlg === true){
                    that._saveExtraSettings(false, true);
                    return;
                }

                that.options.mapping = window.hWin.HEURIST4.util.cloneJSON(services[service_id]);
                window.hWin.HEURIST4.msg.showMsgFlash('Extra lookup settings saved...', 3000);
            });

            return;
        }

        if(close_dlg === true){
            this._forceClose = true;
            this._as_dialog.dialog('close');
        }
    },

    /**
     * Creates a new Heurist record.
     * This method is typically used when `this.options.add_new_record` is true and a selection
     * from the lookup should result in a new record being created in Heurist.
     * It sends a 'save' request to `HAPI4.RecordMgr.saveRecord` with the provided
     * record type ID and details.
     * After a successful save, it closes the lookup dialog.
     *
     * @memberof heurist.lookupBase
     * @instance
     * @private
     * @param {number} rec_RecTypeID - The Heurist record type ID (rty_ID) for the new record.
     * @param {Object} details - An object containing the field details for the new record.
     *                           The keys should be dty_IDs and values the data to be saved.
     * @returns {void}
     */
    _addNewRecord: function(rec_RecTypeID, details){

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        let that = this;

        let request = {
            a: 'save',
            ID: 0,
            RecTypeID: rec_RecTypeID,
            details: details
        };

        window.hWin.HAPI4.RecordMgr.saveRecord(request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack();

            if(response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            // ... Complete final tasks, then
            that._as_dialog.dialog('close'); // close dialog
        });
    },

    /**
     * Retrieves the user's selection(s) from the `resultList` widget.
     *
     * @memberof heurist.lookupBase
     * @instance
     * @private
     * @param {boolean} [get_first=true] - If `true`, the method will return both the full selected recordset
     *                                     and the first record from that set. If `false`, it will only return
     *                                     the full selected recordset (the second element in the returned array will be `null`).
     * @returns {Array<HRecordSet|Array|null>} An array containing two elements:
     *                                       - `[0]`: The `HRecordSet` of selected records. `null` if no selection or error.
     *                                       - `[1]`: The first record (as an array/object) from the selected set if `get_first` is true. `null` otherwise or if no selection.
     *                                       If no records are selected, it shows a flash message and returns `[null, null]`.
     */
    _getSelection: function(get_first = true){

        // get selected recordset
        let recset = this.recordList.resultList('getSelected', false);
        if(!recset || recset.length() < 0){
            window.hWin.HEURIST4.msg.showMsgFlash('Please make a selection first...', 3000);
            return [null, null];
        }

        if(!get_first){
            return [recset, null];
        }

        let record = recset.getFirstRecord(); // get selected record

        return [recset, record];
    },

    /**
     * Converts term codes (numbers) in an array of values to their corresponding term IDs.
     * This method is used for 'enum' (vocabulary/term) fields to ensure that
     * numeric codes from an external source are mapped to the correct Heurist term IDs.
     *
     * It iterates through the `values` array. If a value is a number (or a string representing a number),
     * it attempts to find an existing term in the specified vocabulary (`vocab_ID`) that has this number as its code.
     * If a matching term is found, the value in the array is replaced with the term's ID.
     *
     * If `vocab_ID` is not provided or invalid, it tries to get a backup vocabulary ID
     * from the field's definition (`$Db.dty(dty_ID, 'dty_JsonTermIDTree')`).
     *
     * The `values` array is modified in place.
     *
     * @memberof heurist.lookupBase
     * @instance
     * @private
     * @param {number} vocab_ID - The ID of the vocabulary to check against.
     * @param {number} dty_ID - The data type ID of the field; used to get a backup vocabulary ID if `vocab_ID` is invalid.
     * @param {Array<string|number>} values - An array of values to process. Numeric values or numeric strings are checked as term codes.
     * @returns {Array<string|number>} The modified `values` array, where term codes may have been replaced by term IDs.
     */
    _getTermByCode: function(vocab_ID, dty_ID, values){

        if(!vocab_ID || !$Db.trm(vocab_ID)){
            vocab_ID = $Db.dty(dty_ID, 'dty_JsonTermIDTree');
        }

        if(vocab_ID < 1){
            return values;
        }

        for(const idx in values){

            if(!Number.isInteger(+values[idx])){
                continue;
            }

            let existing_term = $Db.getTermByCode(vocab_ID, +values[idx]);
            if(existing_term !== null){
                values[idx] = existing_term;
            }
        }

        return values;
    },

    /**
     * Processes values intended for record pointer ('resource') or relationship marker ('relmarker') fields.
     * It iterates through the input `values` array and prepares them for Heurist:
     * - If a value is not a number, it's wrapped in an object: `{value: originalValue, search: originalValue, relation: null}`.
     *   This structure is suitable for triggering a search popup in Heurist if the record pointer doesn't exist.
     * - If a value is a number and greater than zero, it's assumed to be a direct Heurist record ID and is kept as is.
     * - Other numeric values (e.g., 0 or negative) are effectively filtered out as they don't result in a push to `new_values`.
     *
     * @memberof heurist.lookupBase
     * @instance
     * @private
     * @param {Array<*>} values - An array of values to be processed.
     * @returns {Array<number|Object>} A new array containing processed values suitable for record pointer or relationship marker fields.
     *                                 Record IDs are numbers, and values that need to trigger a search are objects.
     */
    _processRecordFields: function(values){

        let new_values = [];

        for(const idx in values){

            if(!window.hWin.HEURIST4.util.isNumber(values[idx])){
                new_values.push({value: values[idx], search: values[idx], relation: null});
                continue;
            }

            if(parseInt(values[idx]) > 0){
                new_values.push(values[idx]);
            }
        }

        return new_values;
    },

    /**
     * Performs generic processing on an array of values.
     * It iterates through the `values` array:
     * - Trims leading and trailing whitespace if the value is a string.
     * - Removes the value from the array if it becomes empty after trimming (or was initially empty).
     *
     * The `values` array is modified in place.
     *
     * @memberof heurist.lookupBase
     * @instance
     * @private
     * @param {Array<*>} values - An array of values to process.
     * @returns {void}
     */
    _processValues: function(values){

        for(const idx in values){

            let value = values[idx];
            value = typeof value === 'string' ? value.trim() : value;

            !window.hWin.HEURIST4.util.isempty(value) || values.splice(idx, 1);
        }
    },

    /**
     * Sets up a timeout to handle potential issues during the value mapping process.
     * If the mapping process (which might involve asynchronous operations or complex transformations
     * for certain lookup types, e.g., BnF Bib lookup) takes too long, this timeout
     * will trigger an error message.
     *
     * The timeout is set to 20 seconds. If it elapses, it:
     * - Hides the loading coverall.
     * - Constructs an error message detailing the problematic field and value.
     * - Displays the error message using `window.hWin.HEURIST4.msg.showMsgErr`.
     *
     * The timeout ID is stored in `this.timeout.action_timeout`.
     * `this.timeout.field_name` and `this.timeout.value` should be set before or when
     * this function is called to provide context in the error message.
     * This function is typically called before a potentially long mapping operation.
     * The timeout should be cleared using `clearTimeout(this.timeout.action_timeout)`
     * if the operation completes successfully within the time limit (see `closingAction`).
     *
     * @memberof heurist.lookupBase
     * @instance
     * @returns {void}
     */
    setupTimeout: function(){

        let that = this;

        this.timeout.action_timeout = setTimeout(function(){

            window.hWin.HEURIST4.msg.sendCoverallToBack();

            let field = that.timeout.field_name;
            let value = that.timeout.value;
            let dty_ID = that.options.mapping.fields[field];

            if(Array.isArray(value) || window.hWin.HEURIST4.util.isObject(value)){
                value = JSON.stringify(value);
            }

            window.hWin.HEURIST4.msg.showMsgErr({
                message: 'An error has occurred with mapping values to their respective fields,<br>'
                        + 'please report this by using the bug reporter under Help at the top right of the main screen or,<br>'
                        + 'via email directly to support@heuristnetwork.org so we can fix this quickly.<br><br>'
                        + 'Invalid field details:<br>'
                        + `Response field - "${field}"<br>`
                        + `Record field - "${$Db.rst(that.options.mapping.rty_ID, dty_ID, 'rst_DisplayName')}" (<em>${$Db.dty(dty_ID, 'dty_Type')}</em>)<br>`
                        + `Value to insert - "${value}"<br>`,
                error_title: 'Saving selection canceled',
                status: window.hWin.ResponseStatus.UNKNOWN_ERROR
            });
        }, 20000); // set timeout to 20 seconds
    },

    /**
     * Checks if the number of results (`result_size`) exceeds a specified maximum (`max_size`).
     * If it does, a dialog message is displayed to the user, informing them that
     * only the first `max_size` records are shown and advising them to narrow their search.
     *
     * @memberof heurist.lookupBase
     * @instance
     * @param {number} result_size - The total number of results obtained from the search.
     * @param {number} max_size - The maximum number of results that are displayed or processed.
     * @returns {void}
     */
    checkResultSize: function(result_size, max_size){

        if(result_size > max_size){
            window.hWin.HEURIST4.msg.showMsgDlg(
                `There are ${result_size} records satisfying these criteria, only the first ${max_size} are shown.<br>Please narrow your search.`
            );
        }
    },

    /**
     * Converts a given country name or existing code to a standardized two-letter country code.
     * This method primarily handles specific cases where country names or codes might be
     * inconsistent or require mapping to a standard ISO 3166-1 alpha-2 code.
     *
     * It first checks if the input `country_code` is empty and returns an empty string if so.
     * It then attempts to retrieve term information (label and code) if the input is a term ID.
     *
     * A switch statement handles known special cases:
     * - 'Iran' -> 'IR'
     * - 'Kyrgistan' (misspelling of Kyrgyzstan) -> 'KG'
     * - 'Syria' -> 'SY'
     * - 'Taiwan' -> 'TW'
     * - 'UAE' (United Arab Emirates) -> 'AE'
     * - 'UK' (United Kingdom) -> 'GB'
     * - 'USA' (United States of America) -> 'US'
     * - 'Vietnam' -> 'VN'
     *
     * If no special case matches, the original (potentially term-derived) code is returned.
     *
     * @memberof heurist.lookupBase
     * @instance
     * @private
     * @param {string|number} country_code - The country name, existing code, or term ID to be converted.
     * @returns {string} The standardized two-letter country code, or the original code if no mapping is applied,
     *                   or an empty string if the input is empty.
     */
    _getCountryCode: function(country_code){

        if(window.hWin.HEURIST4.util.isempty(country_code)){
            return '';
        }

        let term_label = $Db.trm(country_code, 'trm_Label');
        let _countryCode = $Db.trm(country_code, 'trm_Code');

        if(typeof term_label === 'object' || typeof _countryCode === 'object'){
            return '';
        }

        switch (term_label) {
            case 'Iran':
                _countryCode = 'IR';
                break;
            case 'Kyrgistan': // Kyrgzstan
                _countryCode = 'KG';
                break;
            case 'Syria':
                _countryCode = 'SY';
                break;
            case 'Taiwan':
                _countryCode = 'TW';
                break;
            case 'UAE':
                _countryCode = 'AE';
                break;
            case 'UK':
                _countryCode = 'GB';
                break;
            case 'USA':
                _countryCode = 'US';
                break;
            case 'Vietnam':
                _countryCode = 'VN';
                break;
            default:
                break;
        }

        return _countryCode;
    },

    /**
     * Retrieves a value from a nested object structure using an array of keys (path parts).
     * This function traverses the `value` object according to the sequence of keys in `fld_Names`.
     *
     * Example:
     * `fld_Names = ['location', 'address', 'street']`
     * `value = { location: { address: { street: 'Main St' } } }`
     * Result: `'Main St'`
     *
     * If at any point a key does not exist or the current `value` becomes undefined,
     * the traversal stops.
     * There's a special case: if a part is 'count' and the path up to that point is invalid,
     * it returns 0 (intended for getting counts of potentially non-existent arrays).
     *
     * @memberof heurist.lookupBase
     * @instance
     * @param {Array<string>} fld_Names - An array of strings representing the path (sequence of keys) to the desired value.
     * @param {Object|Array<*>} value - The object or array from which to retrieve the nested value.
     * @returns {*} The retrieved nested value. If the path is invalid or any intermediate key
     *              is not found, it returns the value at the point of failure (which might be `undefined`).
     *              Returns `0` if 'count' is accessed on an invalid path. Returns the original `value` if it's initially empty.
     */
    getValueByParts: function(fld_Names, value){

        if(window.hWin.HEURIST4.util.isempty(value)){
            return value;
        }

        for(const part of fld_Names){
            if(value && !window.hWin.HEURIST4.util.isempty(value[part])){
                value = value[part];
            }else if(part == 'count'){
                value = 0;
            }
        }

        return value;
    },

    /**
     * Translates a GeoJSON geometry object into a Well-Known Text (WKT) string,
     * prefixed with a Heurist geometry type code.
     *
     * The input `value` is expected to be a GeoJSON geometry object (e.g., Point, Polygon).
     * It's first wrapped into a GeoJSON Feature object: `{type: 'Feature', geometry: value}`.
     * Then, `stringifyMultiWKT` (a global function) is used to convert this Feature to a WKT string.
     *
     * Based on the WKT string content, a Heurist-specific type code is prepended:
     * - 'm ' (multi): If WKT contains 'GEOMETRYCOLLECTION' or 'MULTI'.
     * - 'l ' (line): If WKT contains 'LINESTRING' (and not multi).
     * - 'pl' (polygon): If WKT contains 'POLYGON' (and not multi or line).
     * - 'p ' (point): Default for other geometry types (e.g., Point).
     *
     * If the resulting WKT string is empty, an empty string is returned.
     *
     * @memberof heurist.lookupBase
     * @instance
     * @param {Object} value - A GeoJSON geometry object (e.g., `{ type: "Point", coordinates: [lon, lat] }`).
     * @returns {string} The WKT string representation of the geometry, prefixed with a Heurist type code (e.g., "p POINT(10 20)").
     *                   Returns an empty string if the WKT conversion results in an empty string.
     */
    createGeoFeature: function(value){

        value = {type: 'Feature', geometry: value};

        let wkt = stringifyMultiWKT(value);    

        if(window.hWin.HEURIST4.util.isempty(wkt)){
            return '';
        }

        let typeCode = 'm';
        if(wkt.indexOf('GEOMETRYCOLLECTION')<0 && wkt.indexOf('MULTI')<0){
            if(wkt.indexOf('LINESTRING')>=0){
                typeCode = 'l';
            }else if(wkt.indexOf('POLYGON')>=0){
                typeCode = 'pl';
            }else {
                typeCode = 'p';
            }
        }

        return `${typeCode} ${wkt}`;
    },

    /**
     * Constructs a WKT POINT string from longitude and latitude values,
     * prefixed with the Heurist point type code 'p '.
     *
     * If either `long` or `lat` is empty (as determined by `window.hWin.HEURIST4.util.isempty`),
     * an empty string is returned.
     *
     * @memberof heurist.lookupBase
     * @instance
     * @param {number|string} long - The longitude value.
     * @param {number|string} lat - The latitude value.
     * @returns {string} A WKT POINT string formatted as "p POINT(longitude latitude)" (e.g., "p POINT(151.2093 -33.8688)"),
     *                   or an empty string if either longitude or latitude is empty.
     */
    constructLocation: function(long, lat){

        return !window.hWin.HEURIST4.util.isempty(long) && !window.hWin.HEURIST4.util.isempty(lat)
                ? `p POINT(${long} ${lat})`
                : '';
    },

    /**
     * Extracts a start or end time from a GeoJSON-T `when` object's `timespans` array.
     *
     * The `fld_Names` array is expected to indicate the desired part of the timespan.
     * Specifically, `fld_Names[0]` should start with 'when' (e.g., 'when', 'when.start', 'when.end'),
     * and `fld_Names[2]` should indicate 'start' or 'end'.
     *
     * If these conditions are met and the `value` object has a `timespans` array,
     * it attempts to extract:
     * - `value.timespans[0].start` if `fld_Names[2]` starts with 'start'.
     * - `value.timespans[0].end` if `fld_Names[2]` starts with 'end'.
     *
     * If the conditions are not met or the structure is not as expected, the original `value` is returned.
     *
     * @memberof heurist.lookupBase
     * @instance
     * @param {Array<string>} fld_Names - An array of field name parts, typically derived from splitting a complex field name.
     *                                  Expected structure: `['when', <anything>, 'start'|'end', ...]`
     * @param {Object} value - The GeoJSON-T `when` object, expected to have a `timespans` property
     *                         if start/end times are to be extracted.
     *                         Example: `{ timespans: [{ start: "2023-01-01", end: "2023-12-31" }] }`
     * @returns {string|Object} The extracted start or end time string if successful, otherwise the original `value` object.
     */
    getTimespan: function(fld_Names, value){

        if(!fld_Names[0].startsWith('when') || window.hWin.HEURIST4.util.isempty(value)){
            return value;
        }

        if(fld_Names[2].startsWith('start') && value['timespans']){
            value = value['timespans'][0]['start'];
        }else if(fld_Names[2].startsWith('end') && value['timespans']){
            value = value['timespans'][0]['end'];
        }

        return value;
    },

    /**
     * Performs the search operation by sending a request to the Heurist server-side lookup handler.
     * It constructs the full URL for the external service using `this.baseURL` and the provided `parameters`.
     * If `parameters` are empty or result in an empty query string, it shows an error and returns.
     *
     * The actual request to the external service is proxied through a Heurist PHP script
     * (`/heurist/hserv/controller/record_lookup.php`) via `window.hWin.HAPI4.RecordMgr.lookup_external_service`.
     *
     * Before sending the request, it shows a loading coverall.
     * On receiving a response:
     * - It hides the loading coverall.
     * - Parses the response as JSON.
     * - If the response indicates an error (e.g., `response.status != window.hWin.ResponseStatus.OK`), it shows an error message.
     * - If the response is not valid JSON or an unexpected format, it shows an error message.
     * - On a successful and valid response, it calls `this._handleSearchResult(response)` to process and display the results.
     *
     * @memberof heurist.lookupBase
     * @instance
     * @private
     * @param {Object} parameters - An object containing key-value pairs to be converted into URL query parameters for the external service.
     * @param {Object} [request={}] - Additional parameters to be passed to the `lookup_external_service` HAPI call.
     *                                This can be used to customize the request to the Heurist proxy.
     *                                It's extended with `service` (the full external URL) and `serviceType` (`this.serviceName`).
     * @returns {void}
     */
    _doSearch: function(parameters, request = {}){

        let that = this;

        // Prepare URL
        let full_url = this.baseURL;
        if(!full_url.endsWith('?') && !full_url.endsWith('&')){
            full_url += full_url.indexOf('?') === false ? '?' : '&';
        }

        // Process + Add parameters
        let has_params = false;
        if(!window.hWin.HEURIST4.util.isempty(parameters) && window.hWin.HEURIST4.util.isObject(parameters)){
            let params = {};
            for(let key in parameters){
                if(Object.hasOwn(parameters, key) && !window.hWin.HEURIST4.util.isempty(parameters[key])){
                    params[key] = parameters[key];
                }
            }
            has_params = Object.keys(params).length > 0;
            full_url += has_params ? new URLSearchParams(params).toString() : '';
        }

        if(!has_params){
            window.hWin.HEURIST4.msg.showMsgErr({
                status: window.hWin.ResponseStatus.ACTION_BLOCKED,
                message: 'Please enter a value into one of the input fields'
            });
            return;
        }

        request = $.extend({
            service: full_url,
            serviceType: this.serviceName
        }, request);

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element);

        // calls /heurist/hserv/controller/record_lookup.php
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, (response) => {

            window.hWin.HEURIST4.msg.sendCoverallToBack(); // hide loading cover

            response = window.hWin.HEURIST4.util.isJSON(response);

            if(Object.hasOwn(response, 'status') && response.status != window.hWin.ResponseStatus.OK){ // Error return
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }else if(!response){
                window.hWin.HEURIST4.msg.showMsgErr({
                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR,
                    message: 'Service data was returned in an unhandled format.'
                });
                return;
            }

            that._handleSearchResult(response);

        });
    },

    /**
     * Handles the response from the server-side search (`_doSearch`).
     * In this base implementation, it directly calls `this._onSearchResult(response)`
     * to process and display the results.
     *
     * This method can be overridden by specific lookup widgets if they need
     * to perform intermediate processing on the response before displaying it.
     *
     * @memberof heurist.lookupBase
     * @instance
     * @private
     * @param {Object} response - The JSON response object received from the Heurist server-side lookup handler.
     * @returns {void}
     */
    _handleSearchResult: function(response){
        this._onSearchResult(response);
    },

    /**
     * Transforms a given input value into an array and filters out any empty values.
     * This utility method is used to ensure that values being prepared for Heurist fields,
     * especially multi-value fields, are consistently in array format.
     *
     * - If `values` is an object, it's converted to an array of its values (`Object.values(values)`).
     * - If `values` is not an array (e.g., a string or number), it's wrapped in a single-element array.
     *   Empty initial values (as per `window.hWin.HEURIST4.util.isempty`) result in an array with an empty string `['']`,
     *   which is then filtered.
     * - Finally, it filters the resulting array to remove any values that are considered empty
     *   by `window.hWin.HEURIST4.util.isempty`.
     *
     * @memberof heurist.lookupBase
     * @instance
     * @param {string|Object|Array<*>} values - The input value(s) to be transformed.
     * @returns {Array<*>} An array of values, with empty values removed.
     */
    valueToArray: function(values){

        if(window.hWin.HEURIST4.util.isObject(values)){
            values = Object.values(values);
        }
        if(!Array.isArray(values)){
            values = window.hWin.HEURIST4.util.isempty(values) ? '' : values;
            values = [values];
        }

        values = values.filter((val) => !window.hWin.HEURIST4.util.isempty(val));

        return values;
    }
});