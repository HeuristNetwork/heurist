/**
 * lookupESTC_editions.js - Widget for looking up ESTC Edition records.
 *
 * @fileOverview
 * This file defines the `heurist.lookupESTC_editions` jQuery UI widget.
 * It specializes `heurist.lookupESTC` for searching and processing ESTC
 * "Edition" records (Heurist Record Type 30) from the
 * 'ESTC_Helsinki_Bibliographic_Metadata' database.
 *
 * The widget:
 *  1. Loads HTML content from `lookupLRC18C.html` (as it's not for "Works").
 *  2. Allows users to define search parameters to find Book/Edition records.
 *  3. Displays found records in a list.
 *  4. When a user selects a record, `doAction` is triggered to:
 *     a. Fetch full details if necessary (`_getRecordDetails`).
 *     b. Map selected record data to corresponding Heurist fields based on `this.options.mapping`.
 *     c. Identify linked entities (Agents/Authors rt:10, Places rt:12, Works rt:49) and terms (Book Format).
 *     d. Call `_getRecPointers` and `_getTerms` to fetch data for these linked entities,
 *        which prepares them for Heurist's record pointer/term selection popups.
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
 * Widget for looking up ESTC Edition records from the Heurist ESTC Helsinki Bibliographic Metadata database.
 * It provides specific mappings for search parameters and result fields relevant to ESTC Editions.
 *
 * @widget heurist.lookupESTC_editions
 * @augments heurist.lookupESTC
 */
$.widget("heurist.lookupESTC_editions", $.heurist.lookupESTC, {

    /**
     * Defines the mapping from logical field names (used in `doAction`) to
     * the actual field indices/keys in the ESTC Edition record data returned by HAPI.
     * This allows consistent access to ESTC data fields.
     *
     * Each object in the array has:
     * - `field_name`: The logical name for the data field (e.g., 'title', 'estcID').
     * - `index`: The corresponding field key or index in the raw ESTC record data.
     *            For example, 'rec_ID' is the Heurist record ID, '1' might be a specific
     *            Heurist field ID (dty_ID) holding the title in the ESTC database structure.
     *
     * @memberof heurist.lookupESTC_editions
     * @instance
     * @type {Array<Object>}
     */
    return_mapping: [
        {field_name: 'originalID', index: 'rec_ID'},        // Heurist rec_ID in the ESTC DB
        {field_name: 'title', index: 1},                    // Field ID for title
        {field_name: 'estcID', index: 254},                 // Field ID for ESTC ID
        {field_name: 'yearFirstVolume', index: 9},          // Field ID for year of first volume
        {field_name: 'yearLastVolume', index: 275},         // Field ID for year of last volume
        {field_name: 'summary', index: 285},                // Field ID for summary
        {field_name: 'extendedTitle', index: 277},          // Field ID for extended title
        {field_name: 'numOfVolumes', index: 137},           // Field ID for number of volumes
        {field_name: 'numOfParts', index: 290},             // Field ID for number of parts
        {field_name: 'imprintDetails', index: 270},         // Field ID for imprint details
        {field_name: 'place', index: 259},                  // Field ID for linked Place record (rt:12)
        {field_name: 'author', index: 15},                  // Field ID for linked Author/Agent record (rt:10)
        {field_name: 'work', index: 284},                   // Field ID for linked Work record (rt:49)
        {field_name: 'bookFormat', index: 256}              // Field ID for Book Format term
    ],

    /**
     * Defines the mapping from UI input field placeholders (e.g., `__edition_name__`)
     * to the query parameters for searching ESTC Edition records (Heurist Record Type 30).
     * The keys are HAPI query parameters (e.g., 't' for record type, 'f:1' for field ID 1).
     * Values containing placeholders (like `__placeholder__`) will have these placeholders
     * replaced by the actual values from the UI input fields during query construction in `_doSearch`.
     *
     * Structure:
     * - `t: '30'`: Specifies searching within Record Type 30 (Editions).
     * - `'f:FIELD_ID': '@__placeholder__'` or `'f:FIELD_ID': '__placeholder__'` : Maps a UI input to a specific Heurist field search.
     *   The `@` prefix might indicate a specific search mode (e.g., exact match).
     * - `'linkedto:FIELD_ID': {t: 'LINKED_REC_TYPE_ID', 'f:LINKED_FIELD_ID': '__placeholder__'}`: Defines a search
     *   on a linked record. For example, searching for an edition by author's name involves
     *   searching Record Type 10 (Agents) via the link in field ID 15 of Record Type 30.
     * - `'sortby': 'f:__sort_by_field__'` : Specifies the sort order.
     *
     * @memberof heurist.lookupESTC_editions
     * @instance
     * @type {Object}
     */
    search_mapping: {
        t: '30', // Target record type: ESTC Edition records
        'f:1': '@__edition_name__', // Search in field ID 1 (Title) using value from UI element identified by 'edition_name'
        'f:9': '__edition_date__', // Search in field ID 9 (Year)
        'linkedto:15': {t: '10', 'f:250': '__edition_author__'}, // Search linked Author (rt:10) via field 15, matching Author's name (field 250)
        'linkedto:284': {t: '49', 'f:272': '__edition_work__'}, // Search linked Work (rt:49) via field 284, matching Work's title (field 272)
        'linkedto:259': {t: '12', title: '__edition_place__'}, // Search linked Place (rt:12) via field 259, matching Place's title
        'f:137': '=__vol_count__', // Exact match for number of volumes (field 137)
        'f:290': '=__vol_parts__', // Exact match for number of parts (field 290)
        'f:256': '__select_bf__', // Search by Book Format term (field 256)
        'f:254': '@__estc_no__', // Search by ESTC Number (field 254)
        'sortby': 'f:__sort_by_field__' // Sort results by specified field
    },

    /**
     * Processes the user's selection of an ESTC Edition record from the search results.
     * This method is called when the main action button (e.g., "Select") is clicked.
     *
     * 1. Retrieves the selected record. If none, returns.
     * 2. If the selected record `record.d` (details) is not populated (meaning only header info was fetched),
     *    it calls `this._getRecordDetails(recset, record)` to fetch the full details and then re-invokes `doAction` (implicitly, as `_getRecordDetails` calls `doAction`).
     * 3. Initializes an empty `dlg_response` object to hold data to be passed back.
     * 4. Iterates through the fields defined in `this.options.mapping.fields` (the mapping from the lookup configuration UI):
     *    For each field, it uses `this._mapValues` (which uses `this.return_mapping`) to extract the
     *    corresponding data from the selected ESTC record and stores it in `dlg_response` keyed by Heurist dty_ID.
     * 5. Identifies placeholder IDs for linked records (Place, Author, Work) and terms (Book Format)
     *    from the `details` of the selected ESTC record.
     * 6. Calls `this._getRecPointers` with the `dlg_response`, the collected record pointer IDs, and the term ID.
     *    `_getRecPointers` (and subsequently `_getTerms`) will fetch actual titles/labels for these IDs
     *    and update `dlg_response` to prepare for Heurist's record pointer/term selection popups.
     *
     * @memberof heurist.lookupESTC_editions
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

        let recpointers = [];
        let term_id = '';

        for(const fld_Name of fields){

            let dty_ID = this.options.mapping.fields[fld_Name];
            if(dty_ID < 1 || !$Db.dty(dty_ID)){
                continue;
            }

            dlg_response[dty_ID] = this._mapValues(fld_Name, recset, record);
        }

        if(Object.hasOwn(details, 259)){ // Place - Rec Pointer
            recpointers.push(details[259]);
        }
        if(Object.hasOwn(details, 15)){ // Author - Rec Pointer
            recpointers.push(details[15]);
        }
        if(Object.hasOwn(details, 284)){ // Works - Rec Pointer
            recpointers.push(details[284]);
        }

        if(Object.hasOwn(details, 256)){ // Book format - Term
            term_id = details[256][0];
        }

        this._getRecPointers(dlg_response, recpointers.join(','), term_id);
    }
});