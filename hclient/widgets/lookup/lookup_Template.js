/**
 * @file lookup_Template.js
 * @brief Template for creating new Heurist lookup widgets.
 * 
 * @fileOverview
 * This file serves as a template and example for developers looking to create
 * new lookup widgets that integrate external services with Heurist. It demonstrates
 * the basic structure of a lookup widget, inheriting from `heurist.lookupBase`,
 * and outlines common methods that need to be implemented or overridden.
 *
 * The template illustrates:
 *  - How to define default options for the widget (dialog size, title, HTML content).
 *  - Initialization of UI controls (`_initControls`).
 *  - Custom rendering of results in the Heurist result list (`_rendererResultList`).
 *  - Customizing dialog action buttons (`_getActionButtons`).
 *  - Processing selected data and preparing it for Heurist (`doAction`, `closingAction`).
 *  - Constructing search queries for an external API (`_doSearch`).
 *  - Handling and parsing results from the external API (`_onSearchResult`).
 *  - An example utility function (`removeDupAuthors`).
 *
 * Developers should use this template as a starting point, replacing example
 * logic with service-specific implementations.
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

/**
 * Template widget for creating new lookup services.
 * This widget demonstrates the common structure and methods to override when
 * integrating a new external data source with Heurist. Developers should replace
 * the example implementations with logic specific to the target API and data format.
 *
 * @widget heurist.lookup_Template
 * @extends heurist.lookupBase
 */
$.widget( "heurist.lookup_Template", $.heurist.lookupBase, {

    /**
     * Default options for the template lookup widget.
     * These are example values and should be customized for a specific service.
     * @memberof heurist.lookup_Template
     * @instance
     * @property {Object} options
     * @property {number} [options.height=520] - Example dialog height.
     * @property {number} [options.width=800] - Example dialog width.
     * @property {boolean} [options.modal=true] - Whether the dialog is modal.
     * @property {string} [options.title="Template lookup"] - Example dialog title.
     * @property {string} [options.htmlContent='lookup_Template.html'] - Path to the HTML file for the dialog UI.
     * @property {?Object} options.mapping - Configuration from `record_lookup_config.json` defining
     *                                     field mappings. This is used by `lookupBase.prepareValues`.
     * @property {boolean} [options.add_new_record=false] - If true, `doAction` might trigger creation of new Heurist records.
     * @property {Object} options.resultList - Options for the `resultList` widget.
     *   (See `$.heurist.lookupBase.options.resultList` for more details on sub-properties).
     */
    options: {
    
        height: 520,
        width:  800,
        modal:  true,
        
        title:  "Template lookup",
        
        htmlContent: 'lookup_Template.html',

        mapping: null,
               
        add_new_record: false,

        resultList: {
            recordDivEvenClass: 'recordDiv_blue',
            eventbased: false,
            multiselect: false,
            select_mode: 'select_single',
            selectbutton_label: 'select!!',
            view_mode: 'list',
            show_viewmode: false,
            pagesize: 20,
            entityName: 'Lookups',
            empty_remark: '<div style="padding:1em 0 1em 0">No records match the search</div>'
        }
    },
    
    /**
     * Example: Reference to the jQuery UI resultList widget instance.
     * This is typically initialized in `lookupBase._initControls`.
     * @memberof heurist.lookup_Template
     * @instance
     * @type {?jQuery}
     */
    recordList:null,

    /**
     * Example: Selector for the primary search button(s) in the dialog.
     * Used by `lookupBase._initControls` to attach search handlers.
     * @memberof heurist.lookup_Template
     * @instance
     * @type {string}
     */
    search_button_selector: '#btnStartSearch',

    /**
     * Example `_initControls` method.
     * This method is called after the dialog's HTML content is loaded.
     * Developers should add any specific UI initializations, styling, or event handlers here.
     *
     * - The example demonstrates applying CSS to specific elements.
     * - It notes that `lookupBase._initControls` (called via `this._super()`) initializes
     *   the result list, tabs, and search buttons.
     * - It reminds developers to add the class 'search_on_enter' to input fields
     *   that should trigger search on Enter key press.
     *
     * @memberof heurist.lookup_Template
     * @instance
     * @private
     * @override
     * @returns {void|*} The result of `this._super()`.
     */
    _initControls: function(){

        // Example: Apply specific styling or positioning to elements within this lookup's HTML
        this.element.find('#search_container > div > div > .header.recommended').css({width:'100px', 'min-width':'100px', display: 'inline-block'});
        this.element.find('#btn_container').position({my: 'left top', at: 'right top', of: '#search_container'});              

        // Note: If your HTML includes an element with id 'div_result', the parent _initControls
        // will initialize a resultList widget on it using this.options.resultList.
        // You can add further handlers to this.recordList after calling _super().

        // Note: For inputs that should trigger search on Enter, add class 'search_on_enter'.
        // The handler is already set up in lookupBase.

        let res = this._super(); // Calls parent, sets up resultList, tabs, search button(s)

        return res;
    },

    /**
     * Example `_rendererResultList` method.
     * This function is called by the `resultList` widget for each record to generate its HTML representation.
     * Developers should customize this to display relevant information from the search results.
     * The example demonstrates:
     *  - An inner helper function `fld` to format individual field values.
     *  - Special handling for an 'author' field (parsing a complex object/array).
     *  - Handling for array and object values.
     *  - HTML escaping of values.
     *  - Formatting a 'biburl' field as a hyperlink.
     *  - Constructing a composite `recTitle` from multiple formatted fields.
     *  - Building the final HTML structure for the record row.
     *
     * @memberof heurist.lookup_Template
     * @instance
     * @private
     * @override
     * @param {HRecordSet} recordset - The HRecordSet object containing all result data.
     * @param {Array} record - The current record (row) being rendered, as an array of values.
     * @returns {string} HTML string representing the formatted record row.
     */
    _rendererResultList: function(recordset, record){

        let that = this;

        /**
         * Process author values to string format
         *
         * @param {Array<object|string>} authors - Array of author values to be processed
         * @returns {string} Formatted author values string
         */
        function processAuthors(authors){

            let creator_val = '';

            for(let cur_obj of authors){
                let cur_string = '';
                if(that.$H.isObject(cur_obj)){
                    if(cur_obj.firstname){ cur_string = cur_obj.firstname; }
                    if(cur_obj.surname){ cur_string = (cur_string ? cur_obj.surname + ', ' + cur_string : cur_obj.surname); }
                }else if(String(cur_obj).startsWith('[object')){ // skip objects
                    continue;
                }else{
                    cur_string = String(cur_obj); // Fallback for non-object authors
                }
                creator_val += `${cur_string}; `;
            }

            return creator_val;
        }
        /**
         * Converts objects into readable strings
         *
         * @param {object} obj - Object with values
         * @returns {string} Formatted values string
         */
        function processObject(obj){
            let display_val = '';
            for(let key in obj){
                if(display_val != ''){ display_val += ', '; }
                display_val += obj[key];
            }
            return display_val;
        }
        /**
         * Example inner helper function to format a field for display.
         * @param {string} fldname - The logical name of the field (should match a key in the service's field mapping or a field in the HRecordSet).
         * @param {number} width - Desired display width in 'ex' units for truncation.
         * @returns {string} Formatted HTML string for the field.
         */
        function fld(fldname, width){
            let s = recordset.fld(record, fldname);

            if(fldname == 'author'){ // Example: special handling for author details
                if(!s){ 
                    return '<div style="display:inline-block;width:'+width+'ex" class="truncate"">No provided creator</div>';
                }

                // Assuming s might be an array of author objects
                let authors = Array.isArray(s) ? s : [s];
                let creator_val = processAuthors(authors);

                s = creator_val.replace(/; $/,''); // Clean trailing semicolon
            } else if(Array.isArray(s)){
                s = that.$H.htmlEscape(s.join('; '));
            } else if(that.$H.isObject(s)){
                s = processObject(s);
            } else {
                s = that.$H.htmlEscape(s ? String(s) : '');
            }

            let title = s; // Tooltip is the processed string

            if(fldname == 'biburl'){ // Example: create anchor tag for a URL
                s = `<a href="${s}" target="_blank" rel="noopener"> view here </a>`;
                title = 'View bibliographic record';
            }
            
            if(width > 0){
                s = `<div style="display:inline-block;width:${width}ex" class="truncate" title="${title}">${s}</div>`;
            }
            return s;
        }

        // Example: Generic details (often not needed if rec_Title is well-formed)
        // let recID = fld('rec_ID'); // Usually the internal resultList ID, not the source ID
        // let rectypeID = fld('rec_RecTypeID'); // Usually the mapped rty_ID
        // let recIcon = window.hWin.HAPI4.iconBaseURL + rectypeID;
        // let html_thumb = `<div class="recTypeThumb" style="background-image: url(&quot;${window.hWin.HAPI4.iconBaseURL}&version=thumb&quot;);"></div>`;

        // Construct the main display string for the record row
        let recTitle = fld('author', 50) + fld('date', 7) + fld('title', 75) + fld('biburl', 12); 

        // The parent _rendererResultList in lookupBase expects rec_Title to be set.
        // If not using the generic Heurist icon/thumb display, ensure rec_Title is fully formatted here.
        // recordset.setFld(record, 'rec_Title', recTitle);
        // return this._super(recordset, record); // This would use the Heurist standard row structure

        // For a fully custom row without Heurist standard icon/thumb:
        const rec_ID_val = recordset.fld(record, 'rec_ID'); // This is the internal ID for the row
        const rec_RecTypeID_val = recordset.fld(record, 'rec_RecTypeID');
         let html = `<div class="recordDiv" id="rd${rec_ID_val}" recid="${rec_ID_val}" rectype="${rec_RecTypeID_val}">`
            // + html_thumb // Optional: include if using Heurist standard thumb
            // + '<div class="recordIcons">' // Optional: include for Heurist standard icons
            // +     `<img src="${window.hWin.HAPI4.baseURL}hclient/assets/16x16.gif`
            // +     `" class="rt-icon" style="background-image: url(&quot;${recIcon}&quot;);"/>`
            // + '</div>'
            +  recTitle
            + '</div>';
        return html;
    },

    /**
     * Example `_getActionButtons`.
     * This method allows customization of the dialog's bottom action buttons.
     * It calls `this._super()` to get the default buttons (e.g., "Select", "Cancel" from `lookupBase`)
     * and can then modify them or add new buttons to the returned array.
     *
     * @memberof heurist.lookup_Template
     * @instance
     * @private
     * @override
     * @returns {Array<Object>} Array of button definition objects for the dialog.
     */
    _getActionButtons: function(){
        let res = this._super(); // Get default buttons (e.g., "Select" and "Cancel")
        // Example: Add a new button
        // res.push({
        //     text: "My Custom Button",
        //     click: function() { /* ... */ }
        // });
        return res;
    },

    /**
     * Example `doAction` method.
     * This is the primary method called when the user confirms a selection (e.g., clicks the "Select" button).
     * It should:
     *  1. Retrieve the selected record(s) from the result list.
     *  2. If `this.options.add_new_record` is true, prepare data and call `this._addNewRecord`.
     *  3. Otherwise (default), prepare data for mapping to existing record fields:
     *     - Extract necessary values from the selected record.
     *     - Use `this.prepareValues(recset, record, res, extra_settings)` from `lookupBase`
     *       for standard field mapping based on `this.options.mapping.fields`.
     *     - Perform any service-specific data transformations (e.g., for dates, authors, complex objects).
     *     - Construct the `res` object where keys are Heurist dty_IDs and values are the data to be set.
     *     - Add an `ext_url` property to `res` if an external link for the selected item is available.
     *  4. Call `this.closingAction(res)` to pass the data back.
     *
     * The example below shows a simplified structure based on a BnF-like response,
     * demonstrating how to handle complex fields like 'author' and 'publisher' and
     * how to prepare values for different Heurist field types (text, enum, record pointer, relmarker).
     *
     * @memberof heurist.lookup_Template
     * @instance
     * @override
     * @returns {void}
     */
    doAction: function(){

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        let [recset, record] = this._getSelection(true); // Get the first selected record

        if(!record){ // No record selected or error in _getSelection
            window.hWin.HEURIST4.msg.sendCoverallToBack();
            return;
        }

        // If options.add_new_record is true, this method might behave differently,
        // e.g., by calling this._addNewRecord(rty_ID, data_for_new_record);
        // For this template, we assume it's for mapping to existing fields.

        let res = {}; // This object will hold {dty_ID: value} mappings

        // Example: Set external URL if available in the result.
        // Replace 'biburl' with the actual field name from your service that contains the URL.
        if (recset.getFields().includes('biburl')) {
             res['ext_url'] = recset.fld(record, 'biburl');
        }

        // Use the base prepareValues for standard mapping defined in options.mapping.fields
        // This will iterate through this.options.mapping.fields, get values from `recordset`,
        // and run them through this.prepareValue (which itself calls helper methods in lookupBase
        // like _processRecordFields, _processValues, _getTermByCode).
        // Customize prepareValue in this widget if more specific processing is needed for certain field types.
        res = this.prepareValues(recset, record, res, { /* extra_settings for prepareValue if needed */ });


        // == SERVICE-SPECIFIC DATA TRANSFORMATION EXAMPLES ==
        // The following is a detailed example assuming a complex data structure like BnF's.
        // Adapt or remove this section based on your actual service's response.

        function processAuthor(authors, type){

            let processed_authors = [];

            for(let item of authors){
                let structured_item = {};
                if(typeof item === 'object' && item !== null){
                    // Construct display value and search value from sub-properties
                    structured_item.value = `${item.firstname || ''} ${item.surname || ''}`.trim();
                    if(item.id) structured_item.value += ` (id: ${item.id})`;
                    structured_item.search = `${item.firstname || ''} ${item.surname || ''}`.trim();
                    if(item.role) structured_item.relation = item.role; // For relmarkers
                } else {
                    structured_item.value = structured_item.search = String(item);
                }
                if(type === 'resource' || type === 'relmarker'){
                    processed_authors.push(structured_item);
                } else {
                    processed_authors.push(structured_item.value);
                }
            }

            return processed_authors;
        }
        function processPublisher(publishers, type){

            let processed_publishers = [];

            for(let item of publishers){
                let structured_item = {};
                    if(typeof item === 'object' && item !== null){
                    structured_item.value = `${item.name || ''} ${item.location || ''}`.trim();
                    structured_item.search = item.name || item.location || '';
                } else {
                    structured_item.value = structured_item.search = String(item);
                }
                if(type === 'resource' || type === 'relmarker'){
                    processed_publishers.push(structured_item);
                } else {
                    processed_publishers.push(structured_item.value);
                }
            }

            return processed_publishers;
        }

        let map_flds = Object.keys(this.options.mapping.fields);

        for(let field_name of map_flds){
            let dty_ID = this.options.mapping.fields[field_name];
            if (!dty_ID || dty_ID < 1) continue;

            let val = recset.fld(record, field_name); // Raw value from result list HRecordSet
            if (val == null) continue; // Skip if no value

            let field_type = $Db.dty(dty_ID, 'dty_Type');

            // Ensure val is an array for consistent processing
            if (!Array.isArray(val)) {
                val = [val];
            }

            val = this.valueToArray(val);

            if(field_name === 'author' || field_name === 'contributor'){ // Example: special handling for author/contributor
                // Replace or merge with values from prepareValues
                res[dty_ID] = processAuthor(val, field_type); // This example replaces; merging might be needed

            } else if(field_name === 'publisher'){ // Example: special handling for publisher

                res[dty_ID] = processPublisher(val, field_type);

            } else if (field_type === 'enum') {
                // If prepareValues didn't fully resolve terms (e.g. needs code mapping not just label)
                // add specific term processing here. The example uses _getTermByCode from base.
                // If `this.prepareValues` already handled it (via `extra_settings: {check_term_codes: ...}`),
                // this `else if` might not be strictly needed unless there's more complex logic.
                // For this template, `this.prepareValues` is assumed to handle basic enum mapping.
                // If `val` was already processed by `prepareValues`, `res[dty_ID]` holds it.
                // If you need to re-process or do something different:
                // this._getTermByCode(VOCAB_ID_IF_KNOWN, dty_ID, val); // val would be modified in place
                // processed_vals = val;
                // res[dty_ID] = processed_vals;
                // No change here if prepareValues is sufficient.
            }
            // Add other field-specific transformations as needed...
        }
        // == END OF SERVICE-SPECIFIC DATA TRANSFORMATION EXAMPLES ==

        this.closingAction(res);
    },


    /**
     * Example `closingAction`.
     * This method is called before the dialog closes, typically after `doAction` has prepared data.
     * Its main purpose in `lookupBase` is to ensure the response is an object and then
     * set `this._context_on_close` to this response object before closing the dialog.
     *
     * This template shows a simplified direct call. In a real implementation, you might
     * perform final checks or cleanup on `dlg_response` before calling the parent method.
     *
     * @memberof heurist.lookup_Template
     * @instance
     * @private
     * @override
     * @param {Object} dlg_response - The object containing data to be returned to the calling context.
     */
    closingAction: function(dlg_response){
        // Perform any final modifications to dlg_response if needed.
        // The parent `closingAction` handles setting this._context_on_close and closing.
        this._super(dlg_response); // Ensure to call the parent for standard closing procedure.
    },
    
    /**
     * Example `_doSearch` method.
     * This method should construct the appropriate URL and parameters for the external service API
     * and then call `this._super(params, request_options)` to execute the search via the
     * Heurist proxy (`HAPI4.RecordMgr.lookup_external_service`).
     *
     * - `this.baseURL` should be set to the base API endpoint for the service.
     * - `params` should be an object of query parameters for the API.
     * - `request_options` can be used to pass specific options to the Heurist proxy
     *   (e.g., `{is_XML: true}` if the API returns XML).
     *
     * The example below simulates a search against a BnF-like service.
     *
     * @memberof heurist.lookup_Template
     * @instance
     * @private
     * @override
     * @returns {void}
     */
    _doSearch: function(){

        // Example: Set the specific API endpoint for this lookup
        this.baseURL = 'https://catalogue.bnf.fr/api/SRU?';

        let api_params = { // Parameters specific to the external API
            version: '1.2',
            operation: 'searchRetrieve',
            recordSchema: 'intermarcxchange', // Or other schema as needed
            maximumRecords: 20, // Or from UI input like $('#rec_limit').val()
            startRecord: 1
        };

        // Get search term from UI
        let query_term = this.element.find('#inpt_any').val();
        if(this.$H.isempty(query_term)){
            this.$Hmsg.showMsgFlash('Please enter a value in the search field...', 1000);
            return;
        }
        
        // Construct the API-specific query string
        // Example for SRU/CQL:
        api_params.query = `bib.anywhere all "${query_term}"`;
        // For other APIs, structure `api_params` according to their requirements.

        // Show loading indicator
        this.$Hmsg.bringCoverallToFront(this._as_dialog.parent());

        // Call parent _doSearch (from lookupBase) which handles the HAPI call
        // The first argument to _super should be the API parameters object.
        // The second argument is for options to the Heurist proxy call itself.
        this._super(api_params, { serviceType: 'bnflibrary_bib' /* Or your specific serviceType for HAPI */ });
    },
    
    /**
     * Example `_onSearchResult` method.
     * This method is the callback for the `lookup_external_service` HAPI call made by `_doSearch`.
     * It processes the response from the Heurist proxy (which contains data from the external service).
     *
     * - Parse `json_data` (the response from the proxy).
     * - Transform the data into the structure expected by `HRecordSet` for the `resultList` widget:
     *   - `fields`: An array of field names for the HRecordSet. Must include 'rec_ID' and 'rec_RecTypeID'.
     *   - `records`: An object where keys are local `rec_ID`s and values are arrays of field data.
     *   - `order`: An array of local `rec_ID`s defining the display order.
     * - Handle pagination warnings if applicable (e.g., if `json_data.numberOfRecords` > actual results shown).
     * - Call `this._super(res)` where `res` is the HRecordSet-compatible object, to display results.
     *
     * The example below is tailored for a BnF-like JSON response.
     *
     * @memberof heurist.lookup_Template
     * @instance
     * @private
     * @override
     * @param {Object|string} json_data - The response data from the Heurist proxy, typically a JSON object
     *                                  or a string that needs parsing.
     * @returns {void}
     */
    _onSearchResult: function(json_data){
        this.recordList.show(); // Ensure result list is visible

        json_data = this.$H.isJSON(json_data); // Parse if it's a string

        if (!json_data?.result) { // Check for valid data structure
            this.$Hmsg.showMsgErr({
                message: 'Service did not return data in an appropriate format or no results found.',
                status: window.hWin.ResponseStatus.UNKNOWN_ERROR
            });
            this._super(null); // Clear result list or show empty message
            return;
        }

        let res_records = { /* local_id: [field_values_array] */ };
        let res_orders = [ /* local_id1, local_id2, ... */ ];

        // Define fields for HRecordSet. Must include 'rec_ID', 'rec_RecTypeID'.
        // Other field names should match those you'll extract from `json_data.result` items
        // or those defined in this.options.mapping.fields if you use those as source keys.
        let heurist_fields = ['rec_ID', 'rec_RecTypeID'];
        // Example: Add fields based on your mapping or expected data structure
        // These are logical names you'll use in _rendererResultList's fld() and doAction.
        let service_field_keys = Object.keys(this.options.mapping.fields);
        heurist_fields = heurist_fields.concat(service_field_keys);
        // If your service returns an external URL field not in mapping, add it too:
        // heurist_fields.push('biburl'); // Example

        let current_rec_id = 1;
        for (const source_record of json_data.result) {
            let heurist_record_values = [current_rec_id, this.options.mapping.rty_ID];

            for (const key of service_field_keys) {
                // `key` is the name used in your mapping definition (e.g., "author", "title_proper")
                // `source_record[key]` assumes the keys in your mapping match keys in source_record.
                // Adjust if your mapping keys are different from source_record keys.
                heurist_record_values.push(source_record[key] || '');
            }
            // If you added 'biburl' or other fields to heurist_fields not in mapping:
            // heurist_record_values.push(source_record.url_field_from_API || ''); // Example

            res_records[current_rec_id] = heurist_record_values;
            res_orders.push(current_rec_id);
            current_rec_id++;
        }

        // Example: Handle pagination info from service if available
        let maxRecords = this.element.find('#rec_limit').val() || 20; // Get from UI or default
        if (json_data.numberOfRecords && json_data.numberOfRecords > maxRecords && res_orders.length == maxRecords) {
            this.$Hmsg.showMsgDlg(
                `There are ${json_data.numberOfRecords} records satisfying these criteria, only the first ${maxRecords} are shown.<br>Please narrow your search.`
            );
        }

        let res_for_super = res_orders.length > 0 ? {fields: heurist_fields, order: res_orders, records: res_records} : null;
        this._super(res_for_super); // Pass to lookupBase to update resultList
    },

    /**
     * Example utility function: Removes duplicate authors from a list of records.
     * This is a sample of a helper function specific to this template's example data.
     *
     * @memberof heurist.lookup_Template
     * @instance
     * @private
     * @param {number} author_key_index - The index of the 'author' field in the record's value array.
     * @param {Object} records - The records object (e.g., `res_records`) to process.
     * @returns {Object} The processed records object.
     */
    removeDupAuthors: function(author_key_index, records){
        if(records == null || !this.$H.isObject(records) || this.$H.isempty(author_key_index)){
            return records;
        }

        for(let i in records){
            let author_details_array = records[i][author_key_index];
            if(author_details_array && Array.isArray(author_details_array)){
                // Filter duplicates based on 'id' or composite name
                records[i][author_key_index] = author_details_array.filter(function(val, idx, arr){
                    return idx === arr.findIndex(function(obj){
                        if(val && obj && val.id && obj.id){ // Assuming authors have an 'id'
                            return val.id == obj.id;
                        } else if (val && obj && val.firstname && val.surname && obj.firstname && obj.surname) {
                            // Fallback to name if no ID
                            return val.firstname == obj.firstname && val.surname == obj.surname;
                        }
                        return false; // Or some other logic for non-object authors
                    });
                });
            }
        }
        return records;
    }
});