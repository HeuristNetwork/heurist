/**
 * lookupBnFLibrary_aut.js - Search BnF authoritative records.
 *
 * @fileOverview
 * This file defines the `heurist.lookupBnFLibrary_aut` jQuery UI widget.
 * This widget specializes `heurist.lookupBnF` for searching authoritative
 * records from the Bibliothèque nationale de France (BnF) SRU API.
 *
 * It configures the specific HTML content (`lookupBnFLibrary_aut.html`),
 * sets the BnF SRU API base URL, and provides the logic for constructing
 * search queries based on user input from the form fields defined in the HTML.
 * It also customizes the rendering of results and the handling of selected data.
 *
 * Key functionalities include:
 * - Loading the content of `lookupBnFLibrary_aut.html`.
 * - Performing an API call to the BnF SRU API using user input.
 * - Displaying results within a Heurist result list.
 * - Handling record selection and preparing data for Heurist mapping,
 *   including prompting for record pointer creation/selection and term correction.
 *
 * @project     Heurist academic knowledge management system
 *
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Brandon McKay <blmckay13@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since       6.0
 */

/**
 * Widget for searching BnF (Bibliothèque nationale de France) authoritative records.
 * Inherits from `$.heurist.lookupBnF`.
 *
 * This widget targets the BnF SRU API for authoritative records, using a specific
 * set of UI elements defined in `lookupBnFLibrary_aut.html`. It constructs
 * complex SRU queries based on user input and processes the XML results.
 *
 * @widget heurist.lookupBnFLibrary_aut
 * @augments heurist.lookupBnF
 */
$.widget( "heurist.lookupBnFLibrary_aut", $.heurist.lookupBnF, {

    /**
     * Default options for the widget.
     * @memberof heurist.lookupBnFLibrary_aut
     * @instance
     * @property {Object} options
     * @property {number} [options.height=750] - The height of the dialog.
     * @property {number} [options.width=530] - The width of the dialog.
     * @property {string} [options.title="Search the Bibliothèque nationale de France's authoritative records"] - The title of the dialog.
     * @property {string} [options.htmlContent='lookupBnFLibrary_aut.html'] - The HTML content file for the dialog.
     */
    options: {
    
        height: 750,
        width:  530,
        
        title:  "Search the Bibliothèque nationale de France's authoritative records",
        
        htmlContent: 'lookupBnFLibrary_aut.html'
    },

    /**
     * The base URL for the BnF SRU API.
     * @memberof heurist.lookupBnFLibrary_aut
     * @instance
     * @type {string}
     */
    baseURL: 'https://catalogue.bnf.fr/api/SRU?',

    /**
     * The service name identifier for this lookup type.
     * @memberof heurist.lookupBnFLibrary_aut
     * @instance
     * @type {string}
     */
    serviceName: 'bnflibrary_aut',

    /**
     * Initializes UI controls.
     * Applies specific CSS styling to header and form field elements within the widget.
     * Calls the parent widget's `_initControls` method.
     *
     * @memberof heurist.lookupBnFLibrary_aut
     * @instance
     * @private
     * @override
     * @returns {void} Calls `this._super()`.
     */
    _initControls: function(){

        // Extra field styling
        this.element.find('.header.recommended').css({width: '100px', 'min-width': '100px', display: 'inline-block'}).addClass('truncate');
        this.element.find('.bnf_form_field').css({display:'inline-block', 'margin-top': '2.5px'});

        return this._super();
    },

    /**
     * Sets up the "Additional Settings" tab with default values specific to BnF authoritative lookups.
     * It calls the parent's `_setupSettings` method, providing default options
     * to enable record dumping (`dump_record: true`) and set the default dump field
     * to ScratchPad (`dump_field: 'rec_ScratchPad'`).
     *
     * @memberof heurist.lookupBnFLibrary_aut
     * @instance
     * @private
     * @override
     * @returns {void}
     */
    _setupSettings: function(){

        this._super({
            dump_record: true,
            dump_field: 'rec_ScratchPad'
        });
    },

    /**
     * Saves extra settings, typically related to record dumping.
     * It retrieves the current record dump settings using `_getRecDumpSetting`.
     * If `settings` parameter is not `false` (meaning settings should be actively gathered),
     * it constructs a settings object with `dump_record` and `dump_field` values.
     * Then, it calls the parent's `_saveExtraSettings` method with the gathered
     * or passed-through settings and the `close_dlg` flag.
     *
     * @memberof heurist.lookupBnFLibrary_aut
     * @instance
     * @private
     * @override
     * @param {Object|boolean} [settings=false] - If `true`, settings are gathered from UI.
     *                                           If an object, these settings are used.
     *                                           If `false`, existing/default settings might be saved by parent or dialog might just close.
     * @param {boolean} [close_dlg=false] - Whether to close the dialog after saving.
     * @returns {void}
     */
    _saveExtraSettings: function(settings = false, close_dlg = false){

        const rec_dump_settings = this._getRecDumpSetting();

        if(settings !== false){
            settings = {
                dump_record: rec_dump_settings[0],
                dump_field: rec_dump_settings[1]
            };
        }

        this._super(settings, close_dlg);
    },

    /**
     * Renders a single record in the result list for BnF authoritative records.
     * This method overrides the parent's `_rendererResultList` to provide custom formatting.
     * It constructs a display string (`recTitle`) by concatenating several fields from the record,
     * each formatted by the inner `fld` function. The `fld` function, in turn, uses `getFieldWidth`
     * to dynamically adjust column widths based on the `authority_type` and field name.
     *
     * The displayed fields typically include 'name', 'location', 'years_active', 'role', and 'auturl'.
     * The 'auturl' field is rendered as a hyperlink.
     * The concatenated string is then set as the 'rec_Title' for the record before calling
     * the parent's `_rendererResultList`.
     *
     * @memberof heurist.lookupBnFLibrary_aut
     * @instance
     * @private
     * @override
     * @param {HRecordSet} recordset - The complete record set.
     * @param {Array} record - The individual record (row) to be rendered.
     * @returns {string} The HTML string for the rendered record, generated by the parent's `_rendererResultList`.
     */
    _rendererResultList: function(recordset, record){

        /**
         * Calculates the display width (in 'ex' units) for a field based on authority type and field name.
         * This is an inner helper function for `_rendererResultList`.
         * @param {number} def_width - Default width.
         * @param {string|number} type - The authority type code (e.g., '200', '210').
         * @param {string} fld_name - The name of the field (e.g., 'name', 'location').
         * @returns {number} The calculated width for the field.
         */
        function getFieldWidth(def_width, type, fld_name){

            let width = def_width;

            switch(type){

                case '215':
                case '216':
                case '240':
                case '250':
                    width = fld_name == 'name' ? 75 : 0;
                    break;

                case '200':
                    width = fld_name == 'location' ? 0 : width;
                    width = fld_name == 'name' ? 50 : width;
                    break;

                case '210':
                    width = fld_name == 'years_active' ? 0 : width;
                    width = fld_name == 'name' ? 40 : width;
                    width = fld_name == 'location' ? 20 : width;

                    break;

                default:
                    break;
            }

            return width;
        }

        /**
         * Formats a field's value for display in the result list.
         * It retrieves the field value, HTML escapes it, and wraps it in a div with a specific width.
         * Special formatting is applied for 'auturl' (link), 'years_active'/'location' (parentheses),
         * and 'role' (square brackets).
         * This is an inner helper function for `_rendererResultList`.
         * @param {string} fldname - The name of the field in the recordset.
         * @param {number} width - The default width for the field.
         * @returns {string} HTML string for the formatted field.
         */
        function fld(fldname, width){

            let s = recordset.fld(record, fldname);
            let authority_type = recordset.fld(record, 'authority_type');

            s = window.hWin.HEURIST4.util.htmlEscape(s || '');

            let title = s;

            if(fldname == 'auturl'){
                s = `<a href="${s}" target="_blank"> view here </a>`;
                title = 'View authoritative record';
            }

            width = getFieldWidth(width, authority_type);
            if(s != ''){
                s = fldname == 'years_active' || fldname == 'location' ? `( ${s} )` : s;
                s = fldname == 'role' ? `[ ${s} ]` : s;
            }

            return `<div style="display:inline-block;width:${width}ex" class="truncate" title="${title}">${s}</div>`;
        }

        const recTitle = fld('name', 35) + fld('location', 15) + fld('years_active', 10) + fld('role', 15) + fld('auturl', 10);
        recordset.setFld(record, 'rec_Title', recTitle);

        return this._super(recordset, record);
    },

    /**
     * Processes the user's selection from the result list.
     * This method overrides the parent `doAction` method.
     * It retrieves the selected record, extracts the 'BnF_ID' and 'auturl' (external URL),
     * prepares other values using `prepareValues` (with `check_term_codes: true`),
     * and then calls `closingAction` to pass the data back.
     *
     * @memberof heurist.lookupBnFLibrary_aut
     * @instance
     * @override
     * @returns {void}
     */
    doAction: function(){

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element);

        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){
            return;
        }

        let res = {};
        res['BnF_ID'] = recset.fld(record, 'BnF_ID'); // add BnF ID
        res['ext_url'] = recset.fld(record, 'auturl'); // add BnF URL

        res = this.prepareValues(recset, record, res, {check_term_codes: true});

        this.closingAction(res);
    },

    /**
     * Constructs and performs the search query for BnF authoritative records.
     * This method overrides the parent `_doSearch` method.
     *
     * It gathers values from various input fields in the form (defined in `lookupBnFLibrary_aut.html`),
     * including general search terms, access point, type, ISNI, ISNI date, domain, and record ID.
     *
     * The core logic involves:
     * 1. Setting up base SRU parameters: `version`, `operation`, `recordSchema`, `maximumRecords`, `startRecord`.
     *    - `maximumRecords` is taken from the `#rec_limit` input or defaults to 20.
     * 2. Checking if any search fields have values. If not, a message is shown, and the function returns.
     * 3. Constructing the `query` parameter for the SRU request. This is a CQL (Contextual Query Language) string.
     *    - Each populated search field contributes a clause to the query (e.g., `aut.anywhere all "search term"`).
     *    - Clauses are combined using boolean operators (AND, OR, NOT) selected by the user via corresponding dropdowns (e.g., `#inpt_any_logic`).
     *    - The search index (e.g., `aut.anywhere`, `aut.accesspoint`) and relation (e.g., `all`, `any`, `adj` selected via `#inpt_any_link`) are included.
     * 4. The constructed CQL query string is enclosed in parentheses.
     * 5. The `query` is added to the `params` object.
     * 6. Finally, `this._super(params)` is called to execute the search using the base widget's search mechanism.
     *
     * Example query part: `(aut.anywhere all "term1" and aut.type all "Personne physique")`
     *
     * Available search fields and their corresponding SRU indexes (prefixed with `aut.` for authoritative):
     * - Any field: `aut.anywhere`
     * - Access point: `aut.accesspoint`
     * - Type: `aut.type`
     * - ISNI: `aut.isni`
     * - ISNI Date: `aut.isnidate`
     * - Domain: `aut.domain`
     * - Record ID: `aut.recordid`
     *
     * @memberof heurist.lookupBnFLibrary_aut
     * @instance
     * @private
     * @override
     * @returns {void}
     */
    _doSearch: function(){

        const maxRecords = $('#rec_limit').val(); // limit number of returned records
        let params = {
            version: '1.2',
            operation: 'searchRetrieve',
            recordSchema: 'unimarcxchange', // XML structure for record details
            maximumRecords: !maxRecords || maxRecords <= 0 ? 20 : maxRecords, // Max records from API (default: 100)
            startRecord: 1 // Starting point for batch searches (API default: 1)
        };

        // Check if input fields have values
        let accesspointHasValue = this.element.find('#inpt_accesspoint').val() != '';
        let typeHasValue = this.element.find('#inpt_type').val() != '';
        let isniHasValue = this.element.find('#inpt_isni').val() != '';
        let isnidateHasValue = this.element.find('#inpt_isnidate').val() != '';
        let domainHasValue = this.element.find('#inpt_domain').val() != '';
        let recidHasValue = this.element.find('#inpt_recordid').val() != '';

        // Filter for any text input fields (excluding type inputs) that have a value
        let has_filter = this.element.find('input.text:not(type)').filter((idx, input) => {
            return !window.hWin.HEURIST4.util.isempty($(input).val());
        });

        // Check that something has been entered
        if(has_filter.length == 0){
            window.hWin.HEURIST4.msg.showMsgFlash('Please enter a value in any of the search fields...', 1000);
            return;
        }
        
        // Construct query portion of url (CQL query)
        let query = '(';
        let last_logic = ''; // Stores the last boolean operator used
        
        /* 
         * Additional search fields can be found here [catalogue.bnf.fr/api/test.do], note: ONLY the authoritative fields can be added here (fields starting with 'aut.')
         * if you wish to query bibliographic records (fields starting with 'bib.'), we suggest the alternative BnF lookup available (lookupBnFLibrary_bib)
         * 
         * each field name and search value are separated by a relationship, common ones are: [all, any, adj]
         * 
         * also separating each field query is a boolean logic [and, or, not]
         */        

        // Build query from each input field that has a value
        // Each field has a value input, a link type selector (all, any, adj), and a logic operator selector (AND, OR, NOT)

        // Any field (aut.anywhere)
        if(this.element.find('#inpt_any').val()!=''){
            last_logic = ` ${this.element.find('#inpt_any_logic').val()} `;
            query += `aut.anywhere ${this.element.find('#inpt_any_link').val()} "${this.element.find('#inpt_any').val()}"${last_logic}`;
        }

        // Access point field (aut.accesspoint)
        if(accesspointHasValue){
            last_logic = ` ${this.element.find('#inpt_accesspoint_logic').val()} `;
            query += `aut.accesspoint ${this.element.find('#inpt_accesspoint_link').val()} "${this.element.find('#inpt_accesspoint').val()}"${last_logic}`;
        }

        // Type field (aut.type)
        if(typeHasValue){
            last_logic = ` ${this.element.find('#inpt_type_logic').val()} `;
            query += `aut.type ${this.element.find('#inpt_type_link').val()} "${this.element.find('#inpt_type').val()}"${last_logic}`;
        }

        // ISNI field (aut.isni)
        if(isniHasValue){
            last_logic = ` ${this.element.find('#inpt_isni_logic').val()} `;
            query += `aut.isni ${this.element.find('#inpt_isni_link').val()} "${this.element.find('#inpt_isni').val()}"${last_logic}`;
        }

        // ISNI date field (aut.isnidate)
        if(isnidateHasValue){
            last_logic = ` ${this.element.find('#inpt_isnidate_logic').val()} `;
            query += `aut.isnidate ${this.element.find('#inpt_isnidate_link').val()} "${this.element.find('#inpt_isnidate').val()}"${last_logic}`;
        }

        // Domain field (aut.domain)
        if(domainHasValue){
            last_logic = ` ${this.element.find('#inpt_domain_logic').val()} `;
            query += `aut.domain ${this.element.find('#inpt_domain_link').val()} "${this.element.find('#inpt_domain').val()}"${last_logic}`;
        }

        // Record ID field (aut.recordid) - this is typically the last field, so no logic operator follows
        if(recidHasValue){
            last_logic = ''; // No boolean operator after the last term
            query += `aut.recordid ${this.element.find('#inpt_recordid_link').val()} "${this.element.find('#inpt_recordid').val()}"`;
        }

        // Remove last trailing logic operator if it exists
        if(!window.hWin.HEURIST4.util.isempty(last_logic)){
            let regex = new RegExp(`${last_logic}$`);
            query = query.replace(regex, '');
        }

        query += ')'; // Close the CQL query parenthesis
        params['query'] = query; // Add the constructed query to SRU parameters

        this._super(params); // Call the parent's _doSearch method with the prepared parameters
    }
});