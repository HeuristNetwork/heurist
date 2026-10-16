/**
 * lookupESTC_works.js - Widget for looking up ESTC Work records.
 *
 * @fileOverview
 * This file defines the `heurist.lookupESTC_works` jQuery UI widget.
 * It specializes `heurist.lookupESTC` for searching and processing ESTC
 * "Work" records (Heurist Record Type 49) from the
 * 'ESTC_Helsinki_Bibliographic_Metadata' database.
 *
 * The widget:
 *  1. Loads HTML content from `lookupESTC_works.html`.
 *  2. Allows users to define search parameters to find Work records.
 *  3. Displays found records in a list.
 *  4. When a user selects a record, `doAction` is triggered to:
 *     a. Fetch full details if necessary (`_getRecordDetails`).
 *     b. Map selected record data to corresponding Heurist fields based on `this.options.mapping`.
 *     c. Identify linked term data (specifically 'helsinkiIdAssignation').
 *     d. Call `_getTerms` to fetch data for these linked terms, preparing them
 *        for Heurist's term selection popups.
 *
 * @project     Heurist academic knowledge management system
 *
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Darshan Nagavara   <darshan@intersect.org.au>
 * @author      Brandon McKay   <blmckay13@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since       6.0
 */

/**
 * Widget for looking up ESTC Work records from the Heurist ESTC Helsinki Bibliographic Metadata database.
 * It provides specific mappings for search parameters and result fields relevant to ESTC Works.
 *
 * @widget heurist.lookupESTC_works
 * @augments heurist.lookupESTC
 */
$.widget("heurist.lookupESTC_works", $.heurist.lookupESTC, {

    /**
     * Defines the mapping from logical field names (used in `doAction`) to
     * the actual field indices/keys in the ESTC Work record data returned by HAPI.
     * This allows consistent access to ESTC data fields.
     *
     * Each object in the array has:
     * - `field_name`: The logical name for the data field (e.g., 'title', 'helsinkiId').
     * - `index`: The corresponding field key or index in the raw ESTC record data
     *            (typically a Heurist field ID (dty_ID) within the ESTC database structure).
     *
     * @memberof heurist.lookupESTC_works
     * @instance
     * @type {Array<Object>}
     */
    return_mapping: [
        {field_name: 'title', index: 1},                    // Field ID for Work Title
        {field_name: 'extendedTitle', index: 276},          // Field ID for Extended Title
        {field_name: 'projectId', index: 271},              // Field ID for Project ID
        {field_name: 'helsinkiTitle', index: 273},          // Field ID for Helsinki Title
        {field_name: 'helsinkiId', index: 272},              // Field ID for Helsinki ID
        {field_name: 'helsinkiIdAssignation', index: 298},   // Field ID for Helsinki ID Assignation (Term)
        {field_name: 'helsinkiRawData', index: 236}         // Field ID for Helsinki Raw Data
    ],

    /**
     * Defines the mapping from UI input field placeholders (e.g., `__work_name__`)
     * to the query parameters for searching ESTC Work records (Heurist Record Type 49).
     * The keys are HAPI query parameters (e.g., 't' for record type, 'f:1' for field ID 1).
     * Values containing placeholders (like `__placeholder__`) will have these placeholders
     * replaced by the actual values from the UI input fields during query construction in `_doSearch`.
     *
     * Structure:
     * - `t: '49'`: Specifies searching within Record Type 49 (Works).
     * - `'f:FIELD_ID': '__placeholder__'` : Maps a UI input to a specific Heurist field search.
     * - `'sortby': 'f:__sort_by_field__'` : Specifies the sort order.
     *
     * @memberof heurist.lookupESTC_works
     * @instance
     * @type {Object}
     */
    search_mapping: {
        t: '49', // Target record type: ESTC Work records
        'f:1': '__work_name__',         // Search in field ID 1 (Title) using value from UI element 'work_name'
        'f:271': '__project_id__',      // Search in field ID 271 (Project ID)
        'f:273': '__helsinki_name__',   // Search in field ID 273 (Helsinki Title)
        'f:272': '__helsinki_id__',     // Search in field ID 272 (Helsinki ID)
        'sortby': 'f:__sort_by_field__' // Sort results by specified field
    },

    /**
     * Initializes UI controls specific to the ESTC Works lookup widget.
     * This method overrides the parent `_initControls`.
     * It positions the search button container relative to the header fieldset.
     * Calls the parent widget's `_initControls` method for common control setup.
     *
     * @memberof heurist.lookupESTC_works
     * @instance
     * @private
     * @override
     * @returns {void|*} The result of `this._super()`.
     */
    _initControls: function () {

        // Position the search button group relative to the header
        this._$('#btnStartSearch').parent().parent().position({
            my: 'left center',
            at: 'right center',
            of: '#ent_header > fieldset'
        });

        return this._super();
    },

    /**
     * Processes the user's selection of an ESTC Work record from the search results.
     * This method is called when the main action button (e.g., "Select") is clicked.
     *
     * 1. Retrieves the selected record. If none, returns.
     * 2. If the selected record `record.d` (details) is not populated (meaning only header info was fetched),
     *    it calls `this._getRecordDetails(recset, record)` to fetch the full details and then re-invokes `doAction` (implicitly).
     * 3. Initializes an empty `dlg_response` object to hold data to be passed back.
     * 4. Iterates through the fields defined in `this.options.mapping.fields` (the mapping from the lookup configuration UI):
     *    For each field, it uses `this._mapValues` (which uses `this.return_mapping`) to extract the
     *    corresponding data from the selected ESTC Work record and stores it in `dlg_response` keyed by Heurist dty_ID.
     * 5. Identifies a placeholder ID for the 'helsinkiIdAssignation' term (field ID 298) if present in the record details.
     * 6. Calls `this._getTerms` with the `dlg_response` and the identified term ID. `_getTerms` will
     *    fetch actual term data and update `dlg_response` to prepare for Heurist's term selection popups.
     *    Unlike ESTC Editions, this widget does not explicitly call `_getRecPointers` as Works records in this context
     *    primarily link to terms rather than other complex records via this specific action.
     *
     * @memberof heurist.lookupESTC_works
     * @instance
     * @override
     * @returns {void}
     */
    doAction: function(){

        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){
            return;
        }

        let dlg_response = {};
        let fields = Object.keys(this.options.mapping.fields); // mapped fields names, to access fields of rec

        let details = record.d;

        if(!details){
            this._getRecordDetails(recset, record);
            return;
        }

        for(const fld_Name of fields){

            let dty_ID = this.options.mapping.fields[fld_Name];
            if(dty_ID < 1 || !$Db.dty(dty_ID)){
                continue;
            }

            dlg_response[dty_ID] = this._mapValues(fld_Name, recset, record);
        }

        let term_id = '';

        if(Object.hasOwn(details, 298)){
            term_id = details[298][0];
        }
        
        this._getTerms(dlg_response, term_id);
    }
});