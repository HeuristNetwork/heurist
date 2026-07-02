/**
 * lookupESTC.js - Base widget for ESTC (English Short Title Catalogue) lookups.
 *
 * @fileOverview
 * This file defines the `heurist.lookupESTC` jQuery UI widget.
 * This widget serves as a base for looking up records from an ESTC
 * (English Short Title Catalogue) database, specifically targeting the
 * "ESTC Helsinki Bibliographic Metadata" database instance within Heurist.
 * It can be configured to search for "Works" (if `options.mapping.service` is 'ESTC_works')
 * or "Editions" (otherwise, typically 'ESTC' or 'ESTC_editions', historically also 'LRC18C').
 *
 * The widget handles:
 * - Dynamically loading different HTML content based on whether it's for works or editions.
 * - Constructing search queries for the Heurist HAPI based on user input and predefined mappings.
 * - Displaying search results.
 * - Importing selected ESTC records into the current Heurist database, including handling
 *   linked records (record pointers) and terms.
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
 * Base widget for ESTC (English Short Title Catalogue) lookups.
 * Inherits from `$.heurist.lookupBase`.
 *
 * This widget provides functionality to search and import records from a
 * Heurist-hosted ESTC database (specifically 'ESTC_Helsinki_Bibliographic_Metadata').
 * It adapts its behavior and UI based on whether it's configured for searching
 * "Works" (`options.mapping.service == 'ESTC_works'`) or "Editions".
 *
 * Key features include dynamic HTML loading, construction of search queries
 * using `search_mapping`, result rendering, and a multi-step import process
 * (`_getRecordDetails`, `_importRecords`, `_getRecPointers`, `_getTerms`)
 * to handle data mapping and creation of linked entities.
 *
 * @widget heurist.lookupESTC
 * @augments heurist.lookupBase
 */
$.widget("heurist.lookupESTC", $.heurist.lookupBase, {

    /**
     * Default options for the ESTC lookup widget.
     * @memberof heurist.lookupESTC
     * @instance
     * @property {Object} options
     * @property {number} [options.height=540] - The height of the dialog.
     * @property {number} [options.width=880] - The width of the dialog.
     * @property {string} [options.title='Lookup ESTC Helsinki Bibliographic Metadata values for Heurist record'] - The title of the dialog.
     * @property {string} options.htmlContent - The name of the HTML file for the dialog's content.
     *                                        This is dynamically set in `_init` to 'lookupLRC18C.html' (for editions)
     *                                        or 'lookupESTC_works.html' (for works).
     * @property {Object} options.mapping - Configuration from `LookupConfigs.json`.
     *   @property {string} options.mapping.service - The specific ESTC service type (e.g., 'ESTC_works', 'ESTC').
     */
    options: {

        height: 540,
        width: 880,

        title: 'Lookup ESTC Helsinki Bibliographic Metadata values for Heurist record',

        htmlContent: ''
    },

    /**
     * Flag indicating whether the current lookup is for ESTC "Works".
     * `true` if `this.options.mapping.service` is 'ESTC_works', `false` otherwise (for "Editions").
     * This flag controls which HTML content is loaded and influences other behaviors.
     * @memberof heurist.lookupESTC
     * @instance
     * @private
     * @type {boolean}
     */
    _is_works: false,

    /**
     * Defines how UI input field placeholders map to query parameters for the ESTC search.
     * Keys are target query parameter names, values are either direct strings or objects
     * specifying the placeholder pattern.
     * Example: `{'search_term': 'FIELDNAME:__placeholder__ OR OTHERFIELD:__placeholder__*'}`
     * This property should be defined by specific widgets inheriting from `lookupESTC`
     * (e.g., `lookupESTC_works`, `lookupLRC18C`).
     * @memberof heurist.lookupESTC
     * @instance
     * @type {Object}
     */
    search_mapping: {},

    /**
     * Defines how fields from the ESTC search result map to display or data extraction.
     * Each element is an object like `{field_name: 'targetName', index: 'sourceFieldName'}`.
     * This property should be defined by specific widgets inheriting from `lookupESTC`.
     * @memberof heurist.lookupESTC
     * @instance
     * @type {Array<Object>}
     */
    return_mapping: [],

    /**
     * Initializes the ESTC lookup widget.
     * - Sets `this._is_works` based on `this.options.mapping.service`.
     * - Dynamically sets `this.options.htmlContent` to 'lookupLRC18C.html' (for editions/default)
     *   or 'lookupESTC_works.html' (for works).
     * - Calls the parent widget's `_init` method.
     *
     * @memberof heurist.lookupESTC
     * @instance
     * @private
     * @override
     * @returns {void|*} The result of `this._super()`.
     */
    _init: function(){

        this._is_works = this.options.mapping.service == 'ESTC_works';

        this.options.htmlContent = !this._is_works ? 'lookupLRC18C.html' : 'lookupESTC_works.html';

        return this._super();
    },

    /**
     * Initializes UI controls specific to the ESTC lookup widget.
     * - Adjusts the width of header elements within fieldsets based on `this._is_works`.
     * - Extends `this.options.resultList` to set a custom `empty_remark`.
     * - Calls `_populateBookFormats` to fill the book format dropdown if present.
     * - Calls the parent widget's `_initControls` method.
     *
     * @memberof heurist.lookupESTC
     * @instance
     * @private
     * @override
     * @returns {void|*} The result of `this._super()`.
     */
    _initControls: function(){

        let px = this._is_works ? 100 : 80; // Adjust styling based on work/edition mode
        this._$('fieldset > div > .header').css({width: `${px}px`, 'min-width': `${px}px`});

        this.options.resultList = $.extend(this.options.resultList, {
            empty_remark: '<div style="padding:1em 0 1em 0">Nothing found</div>'
        });

        this._populateBookFormats();

        return this._super();
    },

    /**
     * Renders a single record in the result list for ESTC lookups.
     * This method overrides the parent's `_rendererResultList`.
     * - Sets a default `rec_RecTypeID` to 1 for the displayed record (likely a generic type for display purposes).
     * - Wraps the existing `rec_Title` in a `div` with specific styling to adjust its positioning.
     * - Calls the parent widget's `_rendererResultList` method with the modified record.
     *
     * @memberof heurist.lookupESTC
     * @instance
     * @private
     * @override
     * @param {HRecordSet} recordset - The complete record set.
     * @param {Array} record - The individual record (row) to be rendered.
     * @returns {string} The HTML string for the rendered record, generated by the parent's `_rendererResultList`.
     */
    _rendererResultList: function (recordset, record) {

        recordset.setFld(record, 'rec_RecTypeID', 1); // Set a default display record type

        const rec_Title = recordset.fld(record, 'rec_Title');
        // Wrap title for custom styling in the result list
        recordset.setFld(record, 'rec_Title', `<div class="recordTitle" style="left:30px;right:2px">${rec_Title}</div>`);

        return this._super(recordset, record); // Call parent renderer
    },

    /**
     * Populates the "Book Format" dropdown (`#select_bf`) with terms retrieved
     * from the ESTC Helsinki Bibliographic Metadata database.
     * This is typically called during `_initControls`.
     *
     * It makes a HAPI call to `lookupService` with parameters to fetch
     * terms that are children of term ID 5430 (assumed to be the parent term for book formats).
     * The retrieved terms (ID and Label) are then used to populate the dropdown.
     *
     * @memberof heurist.lookupESTC
     * @instance
     * @private
     * @returns {void}
     */
    _populateBookFormats: function(){

        if(this._$('#select_bf').length == 0){ // Check if the dropdown exists
            return;
        }

        //Populate Bookformat dropdown on lookup page
        let request = {
            serviceType: 'ESTC',
            db:'ESTC_Helsinki_Bibliographic_Metadata',
            a: 'search',
            entity: 'defTerms',
            details: 'list',
            request_id: this.$H.random(),
            trm_ParentTermID: 5430
        };

        let selBf = this._$('#select_bf').empty();
        this.$Hui.addoption(selBf[0], 0, 'select...'); //first option

        this.HAPI.RecordMgr.lookupService(request, (response) => {

            response = that.$H.isJSON(response);

            if(response.status != window.hWin.ResponseStatus.OK){
                return;
            }

            let recordset = new HRecordSet(response.data);
            recordset.each2((trm_ID, term) => {
                that.$Hui.addoption(selBf[0], trm_ID, term['trm_Label']);
            });
        });
    },

    /**
     * Retrieves detailed information for a specific ESTC record.
     * This is called when full details are needed for a record that was initially
     * loaded with only header/summary information. This is an optimization to avoid
     * fetching all details for all records in a large result set.
     *
     * It makes a HAPI call to `lookupService` targeting the
     * 'ESTC_Helsinki_Bibliographic_Metadata' database, requesting 'detail' for the given `sel_Rec_ID`.
     *
     * On successful response:
     * - Parses the response and creates an `HRecordSet`.
     * - Retrieves the first (and expected only) record from the set.
     * - If the record data is missing, an error is shown.
     * - Otherwise, the detailed record data is added/updated in the main result list's
     *   recordset (`this.recordList.resultList('getRecordSet')`).
     * - Calls `this.doAction()` to proceed with processing the now fully detailed record.
     *
     * @memberof heurist.lookupESTC
     * @instance
     * @private
     * @param {HRecordSet} recset - The current (potentially partial) recordset from the result list.
     *                            (Note: This parameter seems unused in the current implementation in favor of `that.recordList.resultList('getRecordSet')`).
     * @param {Array} record - The specific record (row from `recset`) for which to fetch details.
     *                       `recset.fld(record, 'rec_ID')` is used to get the ID.
     * @returns {void}
     */
    _getRecordDetails: function(recset, record){

        let that = this;

        let sel_Rec_ID = recset.fld(record, 'rec_ID'); 
        let query_request = { 
            serviceType: 'ESTC',
            org_db: this.HAPI.database,
            db: 'ESTC_Helsinki_Bibliographic_Metadata',
            q: `ids:${sel_Rec_ID}`, 
            detail: 'detail'
        };
        
        this.$Hmsg.bringCoverallToFront(this._as_dialog.parent());

        this.HAPI.RecordMgr.lookupService(query_request, function(response){
            
            that.$Hmsg.sendCoverallToBack();
            
            response = that.$H.isJSON(response);

            if(response.status != window.hWin.ResponseStatus.OK){
                that.$Hmsg.showMsgErr(response);
                return;
            }

            let recordset = new HRecordSet(response.data);
            let record = recordset.getFirstRecord();
            if(!record?.d){
                that.$Hmsg.showMsgErr({
                    message: 'We are having trouble performing your request on the ESTC server. '
                            +`Impossible obtain details for selected record ${sel_Rec_ID}`,
                    error_title: 'Issues with ESTC server',
                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                });
                return;
            }

            // Update record + recordset
            let recset = that.recordList.resultList('getRecordSet');
            recset.addRecord2(sel_Rec_ID, record);

            that.doAction();
        });
    },

    /**
     * Imports selected records from the ESTC database into the current Heurist database.
     * This method orchestrates the import process using a specific HAPI action.
     *
     * - Sets `this.mapping_defs['import_vocabularies']` based on whether vocabularies have been previously synced.
     * - Converts `rec_IDs` (can be an array or comma-separated string) to a comma-separated string.
     * - Constructs a request object for `HAPI4.RecordMgr.lookupService`:
     *   - `serviceType: 'ESTC'`
     *   - `action: 'import_records'`
     *   - `source_db: 'ESTC_Helsinki_Bibliographic_Metadata'`
     *   - `org_db` and `db`: Current Heurist database.
     *   - `q`: Query string like `ids:REC_IDS`.
     *   - `rules`: Predefined rules for linking (e.g., for specific record types).
     *   - `mapping`: `this.mapping_defs` (should be defined by the inheriting widget).
     * - On successful response:
     *   - Calls `_reportResults` to display a summary of the import.
     *   - Sets `window.hWin.HEURIST4.dbs.vocabs_already_synched = true`.
     *   - Calls `closingAction` to pass back the ID of the first imported record (mapped to `target_dty_ID`) and close the dialog.
     *
     * @memberof heurist.lookupESTC
     * @instance
     * @private
     * @param {Array<string|number>|string} rec_IDs - An array or comma-separated string of ESTC record IDs to import.
     * @returns {void}
     */
    _importRecords: function(rec_IDs){

        let that = this;

        //avoid sync on every request
        this.mapping_defs['import_vocabularies'] = window.hWin.HEURIST4.dbs.vocabs_already_synched ? 0 : 1;

        rec_IDs = Array.isArray(rec_IDs) ? rec_IDs.join(',') : rec_IDs;

        let request = { 
            serviceType: 'ESTC',
            action: 'import_records',
            source_db: 'ESTC_Helsinki_Bibliographic_Metadata',
            org_db: this.HAPI.database,
            db: this.HAPI.database,
            q: `ids:${rec_IDs}`,
            rules: '[{"query":"t:10 linkedfrom:30-15"},{"query":"t:12 linkedfrom:30-259"},{"query":"t:49 linkedfrom:30-284"}]',
            mapping: this.mapping_defs,
            id: this.$H.random()
        };

        this.HAPI.RecordMgr.lookupService(request, function( response ){

            response = that.$H.isJSON(response);

            if(Object.hasOwn(response, 'status') && response.status != window.hWin.ResponseStatus.OK){
                that.$Hmsg.sendCoverallToBack();
                that.$Hmsg.showMsgErr(response);
                return;
            }

            let target_dty_ID = that.options.mapping.fields['properties.edition']

            let ids = response.data.ids; //all
            that._reportResults(ids, response.data);

            window.hWin.HEURIST4.dbs.vocabs_already_synched = true;

            that.closingAction({[target_dty_ID]: ids[0]});
        });
    },

    /**
     * Fetches header information for specified ESTC records, typically to populate record pointer fields.
     * This is part of the data preparation process when a user selects an ESTC record,
     * and that record has fields that point to other ESTC records.
     *
     * - If `rec_IDs` is empty, it proceeds directly to `_getTerms`.
     * - Makes a HAPI call to `lookupService` to get 'header' details for the given `rec_IDs`
     *   from 'ESTC_Helsinki_Bibliographic_Metadata'.
     * - On success:
     *   - Iterates through the `dlg_response` (the object being prepared for the main form).
     *   - For each property in `dlg_response`, if its value (which might be an array) contains a placeholder ID
     *     that matches one of the fetched `rec_IDs`, it replaces that placeholder ID with the `rec_Title`
     *     of the fetched ESTC record using `assignValue`. This prepares the value for Heurist's
     *     record pointer selection/creation popup.
     *   - Adds a `heurist_url` to `dlg_response` pointing to a Heurist search for the fetched `rec_IDs`.
     * - If `term_ID` is provided, calls `_getTerms` to fetch term details.
     * - Otherwise, calls `closingAction` to finalize and close the dialog.
     *
     * @memberof heurist.lookupESTC
     * @instance
     * @private
     * @param {Object} dlg_response - The response object being built to pass back to the main form.
     *                              Its properties may contain placeholder IDs for linked records.
     * @param {Array<string|number>|string} rec_IDs - ESTC record IDs to fetch header information for.
     * @param {number|Array<number>|string} term_ID - Term ID(s) to fetch next, or empty if none.
     * @returns {void}
     */
    _getRecPointers: function(dlg_response, rec_IDs, term_ID){

        let that = this;

        if(this.$H.isempty(rec_IDs)){
            this._getTerms(dlg_response, term_ID);
            return;
        }

        rec_IDs = Array.isArray(rec_IDs) ? rec_IDs.join(',') : rec_IDs;

        let query_request = { 
            serviceType: 'ESTC',
            org_db: this.HAPI.database,
            db: 'ESTC_Helsinki_Bibliographic_Metadata',
            q: `ids:"${rec_IDs}"`, 
            detail: 'header' 
        };
        
        this.$Hmsg.bringCoverallToFront(this._as_dialog.parent());

        this.HAPI.RecordMgr.lookupService(query_request, function(response){

            that.$Hmsg.sendCoverallToBack();
            response = that.$H.isJSON(response);

            if(response.status != window.hWin.ResponseStatus.OK){
                that.$Hmsg.showMsgErr(response);
                return;
            }

            let recordset = new HRecordSet(response.data);
            recordset.each2(function(id, record){
                for(const i in dlg_response){

                    dlg_response[i] = Array.isArray(dlg_response[i]) ? dlg_response[i] : [dlg_response[i]];

                    if(that.assignValue(dlg_response[i], id, record['rec_Title'])){
                        break;
                    }
                }
            });

            dlg_response['heurist_url'] = `https://heuristAU.net/heurist/?db=ESTC_Helsinki_Bibliographic_Metadata&w=a&q=ids:${rec_IDs}`;

            if(that.$H.isempty(term_ID)){
                that.closingAction(dlg_response);
                return;
            }

            that._getTerms(dlg_response, term_ID);
        });
    },

    /**
     * Fetches details for specified ESTC terms (from 'defTerms' entity).
     * This is part of the data preparation process, similar to `_getRecPointers`,
     * but for vocabulary terms.
     *
     * - If `term_ID` is empty, it calls `closingAction` to finalize.
     * - Makes a HAPI call to `lookupService` to get 'list' details (name, etc.)
     *   for the given `term_ID`(s) from the 'defTerms' entity in 'ESTC_Helsinki_Bibliographic_Metadata'.
     * - On success:
     *   - Iterates through `dlg_response`.
     *   - If a property in `dlg_response` contains a placeholder ID matching one of the fetched `term_ID`s,
     *     it replaces that placeholder with an object containing `label`, `desc`, `code`, and `uri`
     *     of the fetched term, using `assignValue`. This prepares the value for Heurist's
     *     term selection/creation popup.
     * - Calls `closingAction` to finalize and close the dialog.
     *
     * @memberof heurist.lookupESTC
     * @instance
     * @private
     * @param {Object} dlg_response - The response object being built. Its properties may contain placeholder term IDs.
     * @param {number|Array<number>|string} term_ID - Term ID(s) to fetch details for.
     * @returns {void}
     */
    _getTerms: function(dlg_response, term_ID){

        let that = this;

        if(this.$H.isempty(term_ID)){
            this.closingAction(dlg_response);
            return;
        }

        let request = {
            serviceType: 'ESTC',
            db: 'ESTC_Helsinki_Bibliographic_Metadata',
            a: 'search',
            entity: 'defTerms',
            details: 'list', //name
            request_id: this.$H.random(),
            trm_ID: term_ID
        };

        this.$Hmsg.bringCoverallToFront(this._as_dialog.parent());
        
        this.HAPI.RecordMgr.lookupService(request, function(response){

            that.$Hmsg.sendCoverallToBack();
            response = that.$H.isJSON(response);

            if(response.status != window.hWin.ResponseStatus.OK){
                that.$Hmsg.showMsgErr(response);
                return;
            }

            let recordset = new HRecordSet(response.data);
            recordset.each2(function(id, record){
                for(const i in dlg_response){

                    dlg_response[i] = Array.isArray(dlg_response[i]) ? dlg_response[i] : [dlg_response[i]];

                    let assigned_label = that.assignValue(dlg_response[i], id, {
                        label: record['trm_Label'],
                        desc: record['trm_Description'],
                        code: record['trm_Code'],
                        uri: record['trm_SemanticReferenceURL']
                    });

                    if(assigned_label){
                        break;
                    }
                }
            });

            that.closingAction(dlg_response);
        });
    },

    /**
     * Replaces a placeholder ID within an array of values with actual data (name or object).
     * This is a utility function used by `_getRecPointers` and `_getTerms` to update
     * arrays in `dlg_response` that hold placeholder IDs for linked records or terms.
     * When a placeholder ID matches `to_replace`, it's substituted with `replace_value`.
     *
     * Example: If `values = [123, 456]`, `to_replace = 123`, `replace_value = "Record Title"`,
     * then `values` becomes `["Record Title", 456]`.
     *
     * The `values` array is modified in place.
     *
     * @memberof heurist.lookupESTC
     * @instance
     * @param {Array<*>} values - An array of values, potentially containing placeholder IDs.
     * @param {string|number} to_replace - The placeholder ID to find and replace.
     * @param {string|Object} replace_value - The actual value (e.g., a record title or a term object)
     *                                      to substitute for the placeholder ID.
     * @returns {boolean} `true` if a replacement was made, `false` otherwise.
     */
    assignValue: function(values, to_replace, replace_value){

        let replaced_value = false;

        for(let idx = 0; idx < values.length; idx++){

            if(values[idx] == to_replace){

                values[idx] = replace_value;
                replaced_value = true;
                break;
            }
        }

        return replaced_value;
    },

    /**
     * Displays a report dialog summarizing the results of an ESTC record import operation.
     *
     * - Gathers counts of imported, existing, and ignored records from the `data` object.
     * - Constructs an HTML message detailing these counts.
     * - Fetches header information (titles) for all involved record IDs (newly imported and existing)
     *   from the 'ESTC_Helsinki_Bibliographic_Metadata' database to make the report more readable.
     * - Appends lists of imported records and already existing records (with their titles) to the message.
     * - Shows the complete report using `window.hWin.HEURIST4.msg.showMsgDlg`.
     *
     * @memberof heurist.lookupESTC
     * @instance
     * @private
     * @param {Array<string|number>|string} rec_IDs - An array or comma-separated string of all record IDs
     *                                             initially selected or attempted for import.
     * @param {Object} data - An object containing import statistics from the HAPI response:
     *   @param {number} data.count_imported - Number of records successfully imported.
     *   @param {number} data.cnt_exist - Number of records that already existed.
     *   @param {number} data.count_ignored - Number of records ignored (e.g., due to mapping issues).
     *   @param {Array<string|number>} data.ids - IDs of all records processed (including newly created).
     *   @param {Array<string|number>} data.exists - IDs of records that already existed.
     * @returns {void}
     */
    _reportResults: function(rec_IDs, data){

        let that = this;
        const cnt = data.count_imported;
        const cnt_ex = data.cnt_exist;
        const cnt_i = data.count_ignored;
        const ids = data.ids; //all

        let ids_ex = data.exists; //skipped
        if(!this.$H.isArrayNotEmpty(ids_ex)) ids_ex = [];

        const imported_extra = cnt > 1 ? 's are' : ' is';
        const existed_extra = cnt_ex > 1 ? 's are' : ' is';
        const skipped_extra = cnt_i > 1 ? 's are' : ' is';

        const sIgnored = cnt_i > 0 
            ? `${cnt_i} record${skipped_extra} skipped. Either record type is not set in mapping or is missing from this database` : '';

        rec_IDs = !Array.isArray(rec_IDs) ? rec_IDs.split(',') : rec_IDs;
        rec_IDs = rec_IDs.concat(ids_ex);

        rec_IDs = rec_IDs.filter((rec_ID) => !this.$H.isempty(rec_ID) && rec_ID > 0);

        let query_request = { 
            serviceType: 'ESTC',
            org_db: this.HAPI.database,
            db: 'ESTC_Helsinki_Bibliographic_Metadata',
            q: `ids:"${rec_IDs.join(',')}"`, 
            w: 'a',
            detail: 'header' 
        };

        //find record titles
        this.HAPI.RecordMgr.lookupService(query_request, (response) => {

            that.$Hmsg.sendCoverallToBack();
            response = that.$H.isJSON(response);

            if(Object.hasOwn(response, 'status') && response.status != window.hWin.ResponseStatus.OK){
                return;
            }
            
            let sImported = '', sExisted = '';

            let recordset = new HRecordSet(response.data);

            for(const rec_ID of ids){
                let rec = recordset.getById(rec_ID);
                sImported += ids_ex.indexOf(rec_ID) < 0 ? `<li>${rec_ID}: ${recordset.fld(rec, 'rec_Title')}</li>` : '';
            }
            sImported = cnt > 0 ? `<ul>${sImported}</ul>` : 'None';
            sImported = `${cnt} record${imported_extra} imported:<br>${sImported}`;

            for(const rec_ID of ids_ex){
                let rec = recordset.getById(rec_ID);
                sExisted += `<li>${rec_ID}: ${recordset.fld(rec, 'rec_Title')}</li>`;
            }
            sExisted = cnt_ex > 0 ? `${cnt_ex} record${existed_extra} already in database<br><ul>${sExisted}</ul>` : 'None';

            that.$Hmsg.showMsgDlg(`<p>Lookup has been completed.</p>${sImported}${sExisted}${sIgnored}`);
        });
    },

    /**
     * Constructs and executes a search query against the ESTC Helsinki Bibliographic Metadata database.
     * It uses `this.search_mapping` (which should be defined by the inheriting widget,
     * e.g., `lookupESTC_works` or `lookupLRC18C`) to build the query object.
     * Placeholders in `this.search_mapping` (e.g., `__placeholder__`) are replaced with
     * values from corresponding input fields in the widget's form.
     *
     * - If the constructed query object has too few criteria (<= 2, specific to ESTC logic),
     *   a flash message prompts the user for more criteria.
     * - A HAPI call (`HAPI4.RecordMgr.lookupService`) is made with:
     *   - `serviceType: 'ESTC'`
     *   - `db: 'ESTC_Helsinki_Bibliographic_Metadata'`
     *   - `q: query` (the constructed query object)
     *   - `limit: 1000`
     *   - `detail: 'header'` (to fetch summary information initially)
     * - On success:
     *   - If the number of results (`response.data.count`) exceeds the number retrieved (`response.data.reccount`),
     *     a dialog informs the user.
     *   - Calls `this._onSearchResult(response)` to process and display results.
     * - Shows/hides a loading coverall during the operation.
     *
     * @memberof heurist.lookupESTC
     * @instance
     * @private
     * @returns {void}
     */
    _doSearch: function(){

        let that = this;

        let query = {};
        for(const field in this.search_mapping){

            let value_field = this.$H.cloneJSON(this.search_mapping[field]);
            let actual_field = typeof value_field === 'string' ? value_field : Object.values(value_field)[1];

            let placeholder = actual_field.match(/__([a-zA-Z_]{7,14})__/);
            if(!placeholder){
                query[field] = value_field;
                continue;
            }

            let value = this._$(`#${placeholder[1]}`).val();
            if(this.$H.isempty(value) || value == 0){
                continue;
            }

            if(actual_field === value_field){
                query[field] = value_field.replace(placeholder[0], value);
            }else{
                let replacing = Object.keys(value_field)[1];
                value_field[replacing] = actual_field.replace(placeholder[0], value);
                query[field] = value_field;
            }
        }

        if(Object.keys(query).length <= 2){
            this.$Hmsg.showMsgFlash('Please specify some criteria to narrow down the search...', 1000);
            return;
        }

        this.$Hmsg.bringCoverallToFront(this._as_dialog.parent());

        let query_request = { 
            serviceType: 'ESTC',
            org_db: this.HAPI.database,
            db: 'ESTC_Helsinki_Bibliographic_Metadata',
            q: query, 
            limit: 1000,
            detail: 'header' 
        };

        this.HAPI.RecordMgr.lookupService(query_request, function(response){

            that.$Hmsg.sendCoverallToBack();
            response = that.$H.isJSON(response);

            if(Object.hasOwn(response, 'status') && response.status != window.hWin.ResponseStatus.OK){
                that.$Hmsg.showMsgErr(response);
                return;
            }

            if(response.data.count>response.data.reccount){
                that.$Hmsg.showMsgDlg(`Your request generated ${response.data.count} results. `
                + `Only first ${response.data.reccount} have been retrieved. `
                + 'You may specify more restrictive criteria to narrow the result.');
                response.data.count = response.data.reccount;
            }

            that._onSearchResult(response);
        });
    },

    /**
     * Handles the search results received from the ESTC database (`_doSearch`).
     * This method overrides the parent `_onSearchResult`.
     *
     * It ensures that `response.data` exists (if not, assumes `response` itself is the data).
     * Then, it calls the parent widget's `_onSearchResult` method, passing `response.data`
     * and `true` for `is_record_set` (indicating the data is already in a format
     * suitable for `HRecordSet` or will be directly processed by the parent's list rendering).
     *
     * @memberof heurist.lookupESTC
     * @instance
     * @private
     * @override
     * @param {Object} response - The JSON response object from the HAPI call made in `_doSearch`.
     *                          Expected to contain a `data` property with the actual search results.
     * @returns {void}
     */
    _onSearchResult: function (response) {
        if(!response.data){ // Ensure data property exists, otherwise use the response itself
            response.data = response;
        }
        // Call parent's _onSearchResult, indicating data is a ready-to-use record set
        this._super(response.data, true);
    },

    /**
     * Retrieves a specific value from a record based on a predefined mapping.
     * This method uses `this.return_mapping` (which should be defined by the inheriting widget)
     * to find the source field name (`index`) corresponding to the requested `field_name`.
     * It then fetches the value of that source field from the provided `record` within the `recordset`.
     *
     * `this.return_mapping` is expected to be an array of objects, where each object has:
     *  - `field_name`: The target logical field name requested.
     *  - `index`: The actual field name (key) in the source ESTC record data.
     *
     * Example: If `this.return_mapping = [{field_name: 'title', index: 'TitleField_ESTC'}]`,
     * calling `_mapValues('title', recordset, record)` would return the value of `recordset.fld(record, 'TitleField_ESTC')`.
     *
     * @memberof heurist.lookupESTC
     * @instance
     * @private
     * @param {string} field_name - The logical name of the field whose value is to be retrieved.
     * @param {HRecordSet} recordset - The HRecordSet containing the record.
     * @param {Array} record - The specific record (row) from which to extract the value.
     * @returns {string|*} The value of the mapped field from the record. Returns an empty string
     *                     if the `field_name` is not found in `this.return_mapping` or if the
     *                     retrieved value is empty. Otherwise, returns the field's value.
     */
    _mapValues: function(field_name, recordset, record){

        const field = this.return_mapping.find((field) => field.field_name === field_name);

        if(!field){
            return '';
        }

        let value = '';

        value = recordset.fld(record, field.index);

        return !this.$H.isempty(value) ? value : '';
    }
});