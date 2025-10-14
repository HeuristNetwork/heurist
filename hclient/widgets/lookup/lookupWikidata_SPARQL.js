/**
 * lookupWikidata_SPARQL.js - Search Wikidata via custom SPARQL queries.
 *
 * @fileOverview
 * This file defines the `heurist.lookupWikidata_SPARQL` jQuery UI widget.
 * This widget provides a specialized interface for users to execute custom
 * SPARQL queries against the Wikidata Query Service (query.wikidata.org).
 *
 * Key features include:
 *  - A CodeMirror editor for writing and editing SPARQL queries.
 *  - Dynamic generation of a UI for mapping variables from the SPARQL query results
 *    to Heurist record fields (`_getFieldMapping`).
 *  - Options for dumping the raw SPARQL result for a selected item into a specified
 *    Heurist field (e.g., ScratchPad or a blocktext field).
 *  - Processing of SPARQL JSON results, including handling of literal language tags
 *    and auto-detection of a URI field for external linking.
 *  - Custom rendering of results based on the variables returned by the SPARQL query.
 *
 * The widget allows for flexible data retrieval from Wikidata, tailored by the user's
 * SPARQL query, and subsequent mapping of that data into a Heurist record.
 *
 * @project     Heurist academic knowledge management system
 *
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Brandon McKay   <blmckay13@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since       6.0
 */

/* global EditorCodeMirror */
/**
 * Widget for executing custom SPARQL queries against Wikidata and mapping results.
 * It provides a CodeMirror editor for SPARQL input and a dynamic interface for
 * mapping result variables to Heurist fields.
 *
 * @widget heurist.lookupWikidata_SPARQL
 * @augments heurist.lookupBase
 */
$.widget("heurist.lookupWikidata_SPARQL", $.heurist.lookupBase, {

    /**
     * Default options for the Wikidata SPARQL lookup widget.
     * @memberof heurist.lookupWikidata_SPARQL
     * @instance
     * @property {Object} options
     * @property {string} [options.htmlContent='lookupWikidata_SPARQL.html'] - The HTML content file for the dialog.
     * @property {Object} options.mapping - Configuration from `LookupConfigs.json`.
     *   @property {number} options.mapping.rty_ID - Target Heurist record type ID for field mapping dropdowns.
     *   @property {Object} [options.mapping.options] - Saved options for this service instance, including
     *                                                 `dump_record`, `dump_field`, and `SPARQL_field_map`.
     */
    options: {
        htmlContent: 'lookupWikidata_SPARQL.html'
    },

    /**
     * The base URL for the Wikidata SPARQL Query Service.
     * @memberof heurist.lookupWikidata_SPARQL
     * @instance
     * @type {string}
     */
    baseURL: 'https://query.wikidata.org/sparql?',

    /**
     * The service name identifier for this lookup type.
     * @memberof heurist.lookupWikidata_SPARQL
     * @instance
     * @type {string}
     */
    serviceName: 'wikidata_SPARQL',

    /**
     * Stores the user-defined mapping between SPARQL result variables (keys)
     * and Heurist Detail Type IDs (values). This mapping is configured via the
     * `_getFieldMapping` dialog and saved in `options.mapping.options.SPARQL_field_map`.
     * Example: `{"itemLabel": 101, "description": 102}`
     * @memberof heurist.lookupWikidata_SPARQL
     * @instance
     * @private
     * @type {Object<string, number>}
     */
    _fields: {},

    /**
     * An array of variable names (column headers) returned by the most recent
     * successful SPARQL query. Determined from `response.data.head.vars` in `_onSearchResult`.
     * Used for dynamic rendering and field mapping UI.
     * @memberof heurist.lookupWikidata_SPARQL
     * @instance
     * @private
     * @type {Array<string>}
     */
    result_fields: [],

    /**
     * The name of the first SPARQL result variable that was identified as containing a URI.
     * This is auto-detected in `_onSearchResult` and used as the `ext_url` if a record is selected.
     * @memberof heurist.lookupWikidata_SPARQL
     * @instance
     * @private
     * @type {string}
     */
    url_field: '',

    /**
     * Initializes UI controls for the Wikidata SPARQL lookup widget.
     * - Sets up a dropdown (`#rty_flds`) for selecting a Heurist field to dump raw SPARQL results into,
     *   filtered for 'blocktext' type fields from the target record type (`this.options.mapping.rty_ID`).
     * - Initializes a CodeMirror editor instance for the SPARQL query input textarea (`#sparql-input`)
     *   if `EditorCodeMirror` function is available.
     * - Sets up a click handler for the field mapping textarea (`#label_mapping`) to open the
     *   `_getFieldMapping` dialog.
     * - Calls the parent widget's `_initControls`.
     *
     * @memberof heurist.lookupWikidata_SPARQL
     * @instance
     * @private
     * @override
     * @returns {void|*} The result of `this._super()`.
     */
    _initControls: function(){

        let $select = this._$('#rty_flds');
        let top_opt = [{key: '', title: 'select a field...', disabled: true, selected: true, hidden: true}];
        let sel_options = {
            useHtmlSelect: false
        };
        this.$Hui.createRectypeDetailSelect($select[0], this.options.mapping.rty_ID, ['blocktext'], top_opt, sel_options);

        if(this.$H.isFunction(EditorCodeMirror)){
            this.codeEditor = new EditorCodeMirror(this._$('textarea#sparql-input'), {mode: 'sparql', lineNumbers: true});
            this.codeEditor.showEditor();
        }

        this._on(this._$('textarea#label_mapping'), {
            click: () => { this._getFieldMapping(); }
        });

        return this._super();
    },

    /**
     * Sets up the "Settings" tab or section of the widget, populating UI elements
     * with saved or default values for SPARQL field mapping and record dumping options.
     * It retrieves these settings from `this.options.mapping.options`.
     *
     * - Initializes options for `dump_record` (checkbox), `dump_field` (target field for dump),
     *   and `SPARQL_field_map` (the JSON string defining SPARQL variable to Heurist field mappings).
     * - Populates the "Dump raw record" checkbox (`#dump_record`).
     * - Sets the selected dump field: either 'rec_ScratchPad' radio button or the specific
     *   field selected in the `#rty_flds` dropdown.
     * - Populates `this._fields` with the parsed `SPARQL_field_map` (handling if it's an array or object).
     * - Displays the stringified `SPARQL_field_map` in the `#label_mapping` textarea.
     *
     * @memberof heurist.lookupWikidata_SPARQL
     * @instance
     * @private
     * @override
     * @returns {void}
     */
    _setupSettings: function(){

        // Merge default settings with saved options from this.options.mapping.options
        let options = $.extend({}, {
            dump_record: false,
            dump_field: "rec_ScratchPad", // Default dump target
            SPARQL_field_map: {}          // Default empty field map
        }, this.options.mapping?.options);

        // Set "Dump raw record" checkbox state
        this._$('#dump_record').prop('checked', options.dump_record);

        // Set dump field selection
        let $dumpFieldSelect = this._$('#rty_flds'); // Dropdown for specific field dump
        if(options.dump_field == 'rec_ScratchPad'){
            this._$('input[name="dump_field"][value="rec_ScratchPad"]').prop('checked', true);
            $dumpFieldSelect.val(''); // Clear selection in dropdown if ScratchPad is chosen
        }else{
            this._$('input[name="dump_field"][value="dty_ID"]').prop('checked', true); // Check specific field radio
            $dumpFieldSelect.val(options.dump_field); // Set dropdown to saved field
        }

        // Load and display SPARQL field mapping
        // Handles if the saved map is an array (possibly from older config) or an object
        this._fields = Array.isArray(options.SPARQL_field_map) && options.SPARQL_field_map.length > 0
                       ? options.SPARQL_field_map[0]
                       : options.SPARQL_field_map;
        this._fields = this._fields ?? {}; // Ensure _fields is an object
        this._$('textarea#label_mapping').val(JSON.stringify(this._fields)); // Display map as JSON string
    },

    /**
     * Saves extra settings, specifically the SPARQL field map (`this._fields`)
     * and record dump preferences, by calling the parent widget's `_saveExtraSettings`.
     *
     * - If `settings` parameter is not `null` (usually means settings should be gathered,
     *   though the default `false` also triggers gathering in this implementation):
     *   - Retrieves current record dump settings using `_getRecDumpSetting`.
     *   - Constructs a `settings` object containing `SPARQL_field_map`, `dump_record`, and `dump_field`.
     * - Calls `this._super(settings, close_dlg)` to pass these settings to the base class
     *   for actual saving (typically to `this.options.mapping.options` and then to the database).
     *
     * @memberof heurist.lookupWikidata_SPARQL
     * @instance
     * @private
     * @override
     * @param {Object|boolean|null} [settings=false] - If not `null`, settings are gathered/updated.
     *                                                 The base class handles the actual saving mechanism.
     * @param {boolean} [close_dlg=false] - Whether to close the dialog after saving.
     * @returns {void}
     */
    _saveExtraSettings: function(settings = false, close_dlg = false){

        if(settings !== null){ // If settings are to be gathered or updated
            const rec_dump_settings = this._getRecDumpSetting(); // Get current dump preferences

            settings = {
                SPARQL_field_map: this._fields,       // Current field mapping
                dump_record: rec_dump_settings[0],    // Dump enabled/disabled
                dump_field: rec_dump_settings[1]      // Target dump field
            };
        }

        this._super(settings, close_dlg); // Call parent to handle saving
    },

    /**
     * Renders a single row in the result list for SPARQL query results.
     * The rendering is dynamic based on the variables returned by the SPARQL query (`this.result_fields`).
     * Each variable becomes a "column" in the displayed row.
     *
     * - Calculates a dynamic width for each column based on the dialog width and number of result fields.
     * - Iterates through `this.result_fields` (obtained from the SPARQL query response headers).
     * - For each field:
     *   - Retrieves the value from the current `record` in the `recordset`.
     *   - Performs basic cleaning: removes language/datatype prefixes like "en:" or "xsd:" from the value if present.
     *   - Appends a `div` with truncation styling for the field's value to the row HTML.
     *
     * @memberof heurist.lookupWikidata_SPARQL
     * @instance
     * @private
     * @override
     * @param {HRecordSet} recordset - The HRecordSet object containing the SPARQL query results.
     * @param {Array} record - The individual record (row) from the recordset to be rendered.
     * @returns {string} The HTML string for the rendered result row.
     */
    _rendererResultList: function(recordset, record){

        let width = this._as_dialog.width() / this.result_fields.length;
        width = width - 15 < 50 ? 50 : width - 15;

        const rec_ID = recordset.fld(record, 'rec_ID');

        let row = `<div class="recordDiv" id="rd${rec_ID}" recid="${rec_ID}">`;

        for(const field of this.result_fields){

            let value = recordset.fld(record, field);

            if(/^\w{2,3}:/.exec(value)){
                let parts = value.split(':');
                parts.shift();
                value = parts.join(':');
            }

            row += `<div class="truncate" style="width: ${width}px; display: inline-block;" title="${value}">${value}</div>`;
        }

        return `${row}</div>`;
    },

    /**
     * Creates and displays a dialog for mapping SPARQL query result variables
     * to Heurist record fields (Detail Types).
     * The dialog allows users to dynamically add, remove, and define mappings.
     * Existing mappings are loaded from `this._fields`.
     *
     * The dialog consists of rows, each representing a mapping:
     * - An input field for the SPARQL result variable name.
     * - A dropdown (`select.sparql_field_select`) to choose a Heurist field (dty_ID).
     * - A remove button for the row.
     *
     * Features:
     * - **Dynamic Row Addition**: Users can add new mapping rows.
     * - **Heurist Field Dropdowns**: Populated using `HEURIST4.ui.createRectypeDetailSelect`,
     *   filtered for compatible field types ('freetext', 'blocktext', 'term', 'resource', 'relmarker', 'geo').
     * - **Auto-Suggestion for SPARQL Variables**: When an input field for a SPARQL variable
     *   gets focus, a dropdown list of unmapped variables from `this.result_fields` appears.
     * - **Saving Mappings**: On "Update mapping", `this._fields` is updated with the UI values,
     *   and the `#label_mapping` textarea is updated with the JSON string of these mappings.
     *
     * @memberof heurist.lookupWikidata_SPARQL
     * @instance
     * @private
     * @param {boolean} [closingAction=false] - If `true`, calls `this.doAction(true)` after
     *                                          the mapping is successfully updated.
     * @returns {void}
     */
    _getFieldMapping: function(closingAction = false){

        /**
         * Populates/Re-populates Heurist field select dropdowns in the mapping dialog.
         * @param {jQuery} selects - jQuery object representing the select elements to populate.
         */
        let fillDropdown = (selects) => {

            const rtyID = this.options.mapping.rty_ID;

            selects.each((idx, select) => {

                let value = select.getAttribute('data-value');

                this.$Hui.createRectypeDetailSelect(select, rtyID,
                    ['freetext', 'blocktext', 'term', 'resource', 'relmarker', 'geo'], null,
                    {useHtmlSelect: false, selectedValue: value}
                );
            });
        };

        /**
         * Sets up auto-suggestion functionality for SPARQL variable name input fields.
         * When an input field receives focus and is empty, a list of unmapped SPARQL
         * result variables (`this.result_fields`) is displayed as clickable suggestions.
         * @param {jQuery} inputs - jQuery object representing the text input fields for SPARQL variable names.
         */
        let setupAutoFill = (inputs) => {
            this._on(inputs, {
                focus: (event) => { // On focus, if input is empty and results are available
                    let $input = $(event.target);
                    if(this.result_fields.length == 0 || $input.val() !== ''){
                        return; // No suggestions if no results or input already has value
                    }

                    // Get already mapped fields to offer only unmapped ones
                    let mapped_fields = [];
                    $dlg.find('input[type="text"]').each((idx, el) => {
                        if(!this.$H.isempty(el.value)){
                            mapped_fields.push(el.value);
                        }
                    });

                    let missing_fields = this.result_fields.filter((field) => !mapped_fields.includes(field));
                    if (missing_fields.length === 0) return; // No unmapped fields to suggest

                    // Build HTML for suggestion list
                    let missing_list_html = missing_fields.reduce(
                        (list, field) => {
                            let row = `<div style="padding: 5px; width: 14em; max-width: 14em; cursor: pointer; border-bottom: 1px solid black;" class="suggestion truncate" title="${field}">${field}</div>`;
                            return `${list}${row}`;
                        }, '');

                    let $suggestions = $('<div>', {
                        style: 'height: 10em; max-height: 10em; width: 16em; overflow-y: auto; position: absolute; background-color: #e0dfe0; padding: 5px; border-bottom: 1px solid black; z-index: 1001;', // Ensure z-index is high enough
                        class: 'suggestion_list',
                        html: `List of result fields not mapped:<br>${missing_list_html}`
                    });

                    $dlg.find('.suggestion_list').remove(); // Remove any existing suggestion list
                    $dlg.append($suggestions); // Add new list to dialog

                    $suggestions.position({ // Position it below the input field
                        my: 'left top', at: 'left bottom', of: $input
                    });

                    // Handle click on a suggestion
                    this._on($suggestions.find('.suggestion'), {
                        click: (ev) => {
                            let title = $(ev.target).text().trim();
                            $input.val(title); // Fill input with suggestion
                            $suggestions.remove(); // Remove suggestion list
                        }
                    });
                },
                blur: () => { // On blur, remove suggestion list after a short delay (to allow click)
                    setTimeout(() => { if($dlg && $dlg.length > 0){ $dlg.find('.suggestion_list').remove();} }, 150);
                }
            });
        };

        /**
         * Sets up click handlers for "remove row" buttons in the mapping dialog.
         * @param {jQuery} buttons - jQuery object representing the remove buttons (typically spans with close icon).
         */
        let setupRemoveRow = (buttons) => {
            this._on(buttons, {
                click: (event) => {
                    $(event.target).closest('.sparql_field_row').remove(); // Remove the parent row
                }
            });
        };

        let $dlg; // Will hold the jQuery dialog object
        let content = `<div style="padding: 10px;">
            <div style="padding: 10px 5px;">
                <span style="width: 16em; display: inline-block; font-weight: bold; padding-left: 1em;">Field label</span>
                <span style="padding-right: 1em; font-size: 1.5em; cursor: default;">⇒</span>
                <span style="width: 16em; display: inline-block; font-weight: bold; padding-left: 1em;">Record field</span>
            </div>`;

        for(const fld_id in this._fields){
            content += `<div style="padding: 10px 5px;" class="sparql_field_row">
                <input type="text" value="${fld_id}" size="25" style="margin-right: 15px; padding: 3.5px;" class="input ui-widget-content">
                <span style="padding-right: 1em; font-size: 1.5em; cursor: default;">&rArr;</span>
                <select class="sparql_field_select" data-value="${this._fields[fld_id]}"></select>
                <span style="margin-left: 0.75em; cursor: pointer;" class="ui-icon ui-icon-close" title="Remove mapping"></span>
            </div>`;
        }

        content += '<div class="sparql_field_add" style="cursor: pointer; display: inline-block;"><span class="ui-icon ui-icon-plus"></span> Add new field</div>';

        content += '</div>';

        let btns = {};
        btns[window.hWin.HR('Update mapping')] = () => {

            this._fields = {};

            $.each($dlg.find('.sparql_field_row'), (idx, row) => {

                row = $(row);

                let field_name = row.find('input').val();
                let field_dty = row.find('select').val();

                if(this.$H.isempty(field_name) || this.$H.isempty(field_dty)){
                    return;
                }

                this._fields[field_name] = field_dty;
            });

            this._$('textarea#label_mapping').val(JSON.stringify(this._fields));

            $dlg.dialog('close');

            if(closingAction){
                this.doAction();
            }
        };
        btns[window.hWin.HR('Cancel')] = () => {
            $dlg.dialog('close');
        };

        $dlg = this.$Hmsg.showMsgDlg(content, btns,
            {title: 'SPARQL field mappings', yes: window.hWin.HR('Update mapping'), no: window.hWin.HR('Cancel')},
            {dialogId: 'SPARQL_mappings', default_palette_class: 'ui-heurist-design', width: 600, height: 600}
        );

        this._on($dlg.find('.sparql_field_add'), {
            click: () => {

                let $div = $('<div>', {
                    style: 'padding: 10px 5px;',
                    class: 'sparql_field_row',
                    html: `<input type="text" size="25" style="margin-right: 15px; padding: 3.5px;" class="input ui-widget-content">
                    <span style="padding-right: 1em; font-size: 1.5em; cursor: default;">&rArr;</span>
                    <select class="sparql_field_select"></select>
                    <span style="margin-left: 0.75em;" class="ui-icon ui-icon-close"></span>`
                }).insertBefore($dlg.find('.sparql_field_add'));

                fillDropdown($div.find('.sparql_field_select'));
                setupAutoFill($div.find('input[type="text"]'));
                setupRemoveRow($div.find('.ui-icon-close'));
            }
        });

        fillDropdown($dlg.find('.sparql_field_select'));
        setupAutoFill($dlg.find('input[type="text"]'));
        setupRemoveRow($dlg.find('.ui-icon-close'));
    },

    /**
     * Processes the user's selection from the SPARQL query result list.
     * This method is called when the main action button (e.g., "Select") is clicked.
     *
     * - Shows a loading coverall.
     * - **Field Mapping Check**: If `skipFieldMapping` is `true` (default initial call)
     *   and `this._fields` (the SPARQL variable to Heurist field map) is empty,
     *   it shows a message and calls `this._getFieldMapping(true)` to force the user
     *   to define mappings. `doAction` will be re-called by `_getFieldMapping` upon completion.
     * - If field mapping is present:
     *   - Retrieves the selected record from the result list.
     *   - Initializes a response object `res`.
     *   - If `this.url_field` (auto-detected URI field from SPARQL results) is set and exists
     *     in the selected record, its value is added as `res['ext_url']`.
     *   - Sets `this.options.mapping.fields = this._fields` to use the current SPARQL-to-Heurist
     *     field mappings for the `prepareValues` call.
     *   - Calls `this.prepareValues(recset, record, res)` to populate `res` with mapped data.
     *   - **Record Dumping**: If record dumping is enabled (`this.options.mapping.options.dump_record`):
     *     - Retrieves the target dump field (`dump_field`).
     *     - Removes the placeholder `rec_ID` (index 0) from the `record` array as it's internal to display.
     *     - If `dump_field` is 'rec_ScratchPad', assigns the remaining `record` array to `res['rec_ScratchPad']`.
     *     - Otherwise, if `dump_field` is a specific Heurist field ID, appends the `record` array
     *       to `res[dump_field]` (or initializes `res[dump_field]` as an array if it doesn't exist).
     *   - Calls `this.closingAction(res)` to pass the prepared data back.
     *
     * @memberof heurist.lookupWikidata_SPARQL
     * @instance
     * @override
     * @returns {void}
     */
    doAction: function(){
        
        this.$Hmsg.bringCoverallToFront(this._as_dialog.parent()); // Show loading indicator

        // If called directly and no fields are mapped, force user to map fields first.
        if(typeof this._fields !== 'object' || Object.keys(this._fields).length === 0 || this.result_fields.find((field) => this._fields.hasOwn(field)) === undefined){
            this.$Hmsg.showMsgFlash('Please map at least one field to return...', 3000);
            this._getFieldMapping(true); // Open mapping dialog, then call doAction()
            return;
        }

        // Proceed if mapping exists or if called from _getFieldMapping
        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){ // No selection
            this.$Hmsg.sendCoverallToBack();
            return;
        }

        let res = {}; // Initialize response object

        // Set external URL if a URI field was auto-detected from SPARQL results
        if(!this.$H.isempty(this.url_field) && recset.getFields().includes(this.url_field)){
            res['ext_url'] = recset.fld(record, this.url_field);
        }

        // Use the current SPARQL variable -> Heurist dty_ID mapping for prepareValues
        this.options.mapping.fields = Object.fromEntries(Object.entries(this._fields).filter(([label, field]) => this.result_fields.indexOf(label) >= 0));
        res = this.prepareValues(recset, record, res); // Map values based on this._fields

        // Handle dumping of raw record data if enabled
        if(this.options.mapping?.options?.dump_record){
            let dump_field = this.options.mapping.options.dump_field;
            // The 'record' array from resultList has rec_ID (local index) at [0].
            // For dumping, we want the actual SPARQL result values.
            let sparql_result_values = record.slice(1); // Remove placeholder rec_ID

            if(dump_field === 'rec_ScratchPad'){
                res['rec_ScratchPad'] = sparql_result_values; // Dump array of values
            }else if(this.$H.isPositiveInt(dump_field)){
                // Dump field is a detail type
                if(Object.hasOwn(res, dump_field)){
                    res[dump_field] = this.valueToArray(res[dump_field]);
                    res[dump_field].push(sparql_result_values.join(' | '));
                }else{
                    res[dump_field] = [sparql_result_values.join(' | ')];
                }
            }
        }

        this.closingAction(res); // Pass data and close
    },

    /**
     * Executes the SPARQL query entered by the user.
     *
     * - Retrieves the SPARQL query string from the CodeMirror editor (`#sparql-input`).
     * - Validates that the query is not empty and contains a `SELECT` clause.
     * - Resets `this.url_field` (auto-detected URI field).
     * - Calls the parent widget's `_doSearch` method, passing the SPARQL query
     *   as the `query` parameter. The parent method will send this to the
     *   Wikidata SPARQL endpoint via the Heurist proxy.
     *
     * @memberof heurist.lookupWikidata_SPARQL
     * @instance
     * @private
     * @override
     * @returns {void}
     */
    _doSearch: function(){

        let sparql = this._$('textarea#sparql-input').val().trim();

        if(this.$H.isempty(sparql)){
            this.$Hmsg.showMsgFlash('Please enter something to query...', 3000);
            return;
        }else if(sparql.toLowerCase().indexOf('select') === -1){ // Basic check for SELECT clause
            this.$Hmsg.showMsgFlash('No fields will be returned, please add a "SELECT" section', 3000);
            return;
        }

        this.url_field = ''; // Reset auto-detected URL field

        this._super({query: sparql}); // Pass SPARQL query to parent for execution
    },

    /**
     * Processes the JSON results received from a Wikidata SPARQL query.
     * This method overrides the parent `_onSearchResult`.
     *
     * The response from Wikidata is expected in a specific JSON format:
     * `response.data.head.vars` contains the array of variable names (columns).
     * `response.data.results.bindings` is an array of result objects, where each object
     * maps variable names to their values (which are themselves objects with `type` and `value`).
     *
     * - If no bindings (results) are found, calls `this._super(false)` to clear/indicate no results.
     * - Sets `this.result_fields` to the array of variable names from `response.data.head.vars`.
     * - Prepares `fields` for HRecordSet: 'rec_ID' (local index) followed by all `this.result_fields`.
     * - Iterates through `response.data.results.bindings`:
     *   - Uses the array index as a local `rec_ID`.
     *   - For each SPARQL variable in `this.result_fields`:
     *     - If `this.url_field` is not yet set and the current variable's value is a URI (`type: 'uri'`),
     *       sets `this.url_field` to this variable name.
     *     - If the value is a literal with a language tag (`type: 'literal'`, `xml:lang` exists),
     *       prepends the 3-letter language code (e.g., "eng:") to the value if it's not 'MUL' (multiple/undetermined).
     *     - Pushes the `value` property of the binding to the current Heurist record row.
     *   - Stores the constructed row in `records` and the index in `order`.
     * - Constructs the final result object for the parent's display mechanism.
     * - Calls `this._super(res)` to display the results.
     *
     * @memberof heurist.lookupWikidata_SPARQL
     * @instance
     * @private
     * @override
     * @param {Object} response - The JSON response object from the Heurist proxy, containing
     *                            the parsed results of the Wikidata SPARQL query.
     * @returns {void}
     */
    _onSearchResult: function(response){
        
        // Check for empty or invalid results
        if(!response?.data?.results?.bindings || response.data.results.bindings.length === 0){
            this._super(false); // Indicate no results or error to parent
            return;
        }

        this.result_fields = response.data.head.vars; // Store SPARQL result variable names

        // Prepare fields for HRecordSet: local rec_ID + SPARQL variables
        let fields = ['rec_ID', ...this.result_fields];
        let records = {}; // To store {rec_ID: [value1, value2, ...]}
        let order = [];   // To store [rec_ID1, rec_ID2, ...]

        for(const rec_ID_idx in response.data.results.bindings){ // Iterate through each result binding

            let local_rec_ID = parseInt(rec_ID_idx); // Use array index as local ID for display
            let wikidata_binding = response.data.results.bindings[rec_ID_idx]; // Current SPARQL result object

            let record_values = this._processSPARQLRow(wikidata_binding);
            record_values.unshift(local_rec_ID); // Start Heurist row with local ID

            records[local_rec_ID] = record_values;
            order.push(local_rec_ID);
        }

        // Prepare final result object for the parent's display mechanism
        let res = order.length > 0 ? {fields: fields, order: order, records: records} : false;
        this._super(res); // Pass to parent to render in resultList
    },

    /**
     * Process the data from a SPARQL result row, effectively turning it into an array
     *
     * @param {Object} row - A row from the SPARQL resutls
     * @returns {Array<*>} Heurist record ready data for display
     */
    _processSPARQLRow: function(row){

        if(this.$H.isempty(this.result_fields)){
            return [];
        }

        let values = [];

        for(const field_var of this.result_fields){ // Iterate through each SPARQL variable
            let cell_data = row[field_var];
            let cell_value = cell_data ? cell_data['value'] : ''; // Default to empty string if no value

            // Auto-detect the first URI field to use as a potential external link
            if(this.url_field === '' && cell_data && cell_data['type'] === 'uri'){
                this.url_field = field_var;
            } else if (cell_data && cell_data['type'] === 'literal' && Object.hasOwn(cell_data, 'xml:lang')){
                // Prepend language code if literal has a language tag
                let language = window.hWin.HAPI4.getLangCode3(cell_data['xml:lang'], 'MUL');
                cell_value = language !== 'MUL' ? `${language}:${cell_value}` : cell_value;
            }
            values.push(cell_value);
        }

        return values;
    }
});