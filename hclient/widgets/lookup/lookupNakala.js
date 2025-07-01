/**
 * lookupNakala.js - Search Nakala public records.
 *
 * @fileOverview
 * This file defines the `heurist.lookupNakala` jQuery UI widget.
 * This widget provides an interface for searching public records from the
 * Nakala API (api.nakala.fr/search). It allows users to construct search queries
 * with various filters (general query, license, year, type) and displays the
 * results in a list, from which users can select records to map data to
 * Heurist fields.
 *
 * The widget dynamically populates filter dropdowns (type, license, year)
 * by fetching metadata from a Nakala helper endpoint (`nakala_get_metadata`)
 * via the Heurist proxy.
 *
 * @project     Heurist academic knowledge management system
 * @package  hclient\widgets\lookup
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Brandon McKay   <blmckay13@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since       6.0
 */

/**
 * Widget for searching public records from the Nakala service.
 * It allows users to filter by various criteria and select records for data mapping.
 *
 * @widget heurist.lookupNakala
 * @extends heurist.lookupBase
 */
$.widget( "heurist.lookupNakala", $.heurist.lookupBase, {

    /**
     * Default options for the Nakala lookup widget.
     * @memberof heurist.lookupNakala
     * @instance
     * @property {Object} options
     * @property {number} [options.height=700] - The height of the dialog.
     * @property {number} [options.width=850] - The width of the dialog.
     * @property {string} [options.title="Search the publically available Nakala records"] - The title of the dialog.
     * @property {string} [options.htmlContent='lookupNakala.html'] - The HTML content file for the dialog.
     */
    options: {

        height: 700,
        width:  850,

        title:  "Search the publically available Nakala records",

        htmlContent: 'lookupNakala.html'
    },

    /**
     * Initializes UI controls for the Nakala lookup widget.
     * - Applies specific CSS styling to header and button container elements.
     * - Fetches metadata (types, licenses, years) from a Nakala helper endpoint
     *   via `HAPI4.RecordMgr.lookup_external_service` (service `nakala_get_metadata`).
     * - Populates dropdowns for Type (`#inpt_type`), License (`#inpt_license`),
     *   and Year (`#inpt_year`) with the fetched metadata.
     * - Hides dropdowns if their respective metadata is not available.
     * - Calls the parent widget's `_initControls` method.
     *
     * @memberof heurist.lookupNakala
     * @instance
     * @private
     * @override
     * @returns {void|*} The result of `this._super()`.
     */
    _initControls: function(){

        let that = this;

        // Extra field styling
        this.element.find('#search_container > div > div > .header.recommended').css({width:'120px', 'min-width':'120px', display: 'inline-block'});
        this.element.find('#search_container > div > div > .header.optional').css({width:'60px', 'min-width':'60px', display: 'inline-block'});
        this.element.find('#btn_container').position({my: 'left bottom', at: 'right bottom', of: '#search_container'});

        let request = {
            serviceType: 'nakala',
            service: 'nakala_get_metadata' // file types used by Nakala
        };
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, (data) => {

            data = window.hWin.HEURIST4.util.isJSON(data);

            if(data.status && data.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(data);
                return;
            }

            let $select = that.element.find('#inpt_type');
            if(Object.hasOwn(data,'types')){
                $.each(data['types'], (idx, type) => {
                    window.hWin.HEURIST4.ui.addoption($select[0], type[1], type[0]);
                });
                window.hWin.HEURIST4.ui.initHSelect($select, false);
            }else{
                $select.hide();
                that.element.find('[for="inpt_type"]').hide();
            }

            $select = that.element.find('#inpt_license');
            if(Object.hasOwn(data,'licenses')){
                $.each(data['licenses'], (idx, license) => {
                    window.hWin.HEURIST4.ui.addoption($select[0], license, license);
                });
                window.hWin.HEURIST4.ui.initHSelect($select, false);
            }else{
                $select.hide();
                that.element.find('[for="inpt_license"]').hide();
            }

            $select = that.element.find('#inpt_year');
            if(Object.hasOwn(data,'years')){
                $.each(data['years'], (idx, year) => {
                    window.hWin.HEURIST4.ui.addoption($select[0], year, year);
                });
                window.hWin.HEURIST4.ui.initHSelect($select, false);
            }else{
                $select.hide();
                that.element.find('[for="inpt_year"]').hide();
            }
        });

        return this._super();
    },

    /**
     * Renders a single record in the result list for Nakala search results.
     * This method overrides the parent's `_rendererResultList`.
     * It constructs a display string (`recTitle`) by concatenating 'author', 'date',
     * 'title', and 'rec_url' (link to the Nakala record).
     *
     * @memberof heurist.lookupNakala
     * @instance
     * @private
     * @override
     * @param {HRecordSet} recordset - The complete HRecordSet object.
     * @param {Array} record - The individual record (row) from the recordset.
     * @returns {string} The HTML string for the rendered record, generated by the parent's `_rendererResultList`.
     */
    _rendererResultList: function(recordset, record){

        /**
         * Inner helper function to format a field's value for display.
         * It handles empty values, converts objects to string arrays, joins arrays,
         * HTML escapes the value, and wraps it in a div with a specified width for truncation.
         * If `fldname` is 'rec_url', it formats the value as an anchor tag.
         * @param {string} fldname - The name of the field to retrieve from the record.
         * @param {number} width - The display width for the field in 'ex' units. If 0, no div wrapper.
         * @returns {string} HTML string for the formatted field.
         */
        function fld(fldname, width){
            let s = recordset.fld(record, fldname);

            if(window.hWin.HEURIST4.util.isempty(s) && s !== ''){ // Handle various empty states
                s = '';
            }

            s = window.hWin.HEURIST4.util.isObject(s) ? Object.values(s) : s; // Convert object to array of values
            s = Array.isArray(s) ? s.join('; ') : s; // Join array elements with semicolon

            let title = window.hWin.HEURIST4.util.htmlEscape(s || ''); // Tooltip is the escaped full string

            if(fldname == 'rec_url'){ // Special formatting for the record URL
                s = `<a href="${s}" target="_blank" rel="noopener"> view record </a>`;
                title = 'View Nakala record';
            }
            
            if(width > 0){ // Apply truncation styling if width is specified
                s = `<div style="display:inline-block;width:${width}ex" class="truncate" title="${title}">${s}</div>`;
            }
            return s;
        }

        // Construct the composite title string for the record display
        const recTitle = fld('author', 40) + fld('date', 12) + fld('title', 85) + fld('rec_url', 12);
        recordset.setFld(record, 'rec_Title', recTitle); // Set the formatted title back into the recordset

        return this._super(recordset, record); // Call parent's renderer
    },

    /**
     * Processes the user's selection from the Nakala result list.
     * This method overrides the parent `doAction`.
     * It simply calls the parent's `doAction` method, passing 'rec_url' as the
     * field name to be potentially extracted as `ext_url` in the response object.
     * Other field mapping is handled by the generic `prepareValues` in the base class.
     *
     * @memberof heurist.lookupNakala
     * @instance
     * @override
     * @returns {void}
     */
    doAction: function(){
        this._super('rec_url'); // Pass 'rec_url' to be used as potential external link
    },

    /**
     * Constructs the search query for the Nakala API and executes the search
     * via the Heurist proxy.
     *
     * - Base URL: `https://api.nakala.fr/search?q=`
     * - Appends the general query term from `#inpt_any`.
     * - Constructs a filter query (`fq`) string:
     *   - `scope=datas` (to exclude collections).
     *   - Appends license, year (processed by `getYear`), and type filters if selected.
     *     Type values are prefixed with `http://purl.org/coar/resource_type/` if not already a URL.
     * - If no general query term and no filters are set, a message prompts the user.
     * - Appends `size` parameter based on `#rec_limit` input.
     * - Makes a HAPI call to `RecordMgr.lookup_external_service` with the constructed URL
     *   and `serviceType: 'nakala'`.
     * - Calls `_onSearchResult` with the response.
     *
     * @memberof heurist.lookupNakala
     * @instance
     * @private
     * @override
     * @returns {void}
     */
    _doSearch: function(){

        let that = this;

        // Construct base url for external request
        let sURL = 'https://api.nakala.fr/search?q='; // base URL for Nakala request
        let filter_query = 'scope=datas'; // no collections
        
        // Construct query portion of url
        // any field
        if(this.element.find('#inpt_any').val()!=''){
            sURL += encodeURIComponent(this.element.find('#inpt_any').val());
        }

        if(this.element.find('#inpt_license').val() != 'all'){
            filter_query += `;license=${this.element.find('#inpt_license').val()}`;
        }
        if(this.element.find('#inpt_year').val() != 'all'){

            let years = this.getYear();
            filter_query += `;year=${years}`;
        }
        if(this.element.find('#inpt_type').val() != 'all'){

            let type = this.element.find('#inpt_type').val();

            if(type.indexOf('http') === -1){
                type = `http://purl.org/coar/resource_type/${type}`;
            }

            filter_query += `;type=${type}`;
        }

        if(filter_query != ''){
            sURL += `&fq=${encodeURIComponent(filter_query)}`;
        }

        // Check that something has been entered
        if(this.element.find('#inpt_any').val()=='' && filter_query == ''){
            window.hWin.HEURIST4.msg.showMsgFlash('Please enter a value in the search field or select a filter...', 1000);
            return;
        }

        let maxRecords = $('#rec_limit').val(); // limit number of returned records
        maxRecords = (!maxRecords || maxRecords <= 0) ? 20 : maxRecords;
        sURL += `&size=${maxRecords}`;

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent()); // show loading cover

        // for record_lookup.php
        let request = {
            service: sURL, // request url
            serviceType: 'nakala' // requesting service, otherwise the request will result in an error
        };

        // calls /heurist/hserv/controller/record_lookup.php
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack(); // hide loading cover

            response = window.hWin.HEURIST4.util.isJSON(response);

            if(Object.hasOwn(response, 'status') && response.status != window.hWin.ResponseStatus.OK){ // Error return
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }

            that._onSearchResult(response);
        });
    },

    /**
     * Processes the search results received from the Nakala API.
     * This method overrides the parent `_onSearchResult`.
     *
     * - Parses `json_data`.
     * - If data is invalid or no records found (`json_data.records`), calls `this._super(false or null)`.
     * - Prepares field list: 'rec_ID', 'rec_RecTypeID', fields from `this.options.mapping.fields`, and 'rec_url'.
     * - Iterates through `json_data.records`:
     *   - Creates a `values` array for each record, starting with local `recID` and target `rty_ID`.
     *   - Appends values for each mapped field from the Nakala record.
     *   - Stores the `values` array in `res_records` and `recID` in `res_orders`.
     * - If total results (`json_data.count`) exceed `maxRecords`, shows a warning dialog.
     * - Constructs the final result object or `false`.
     * - Calls `this._super(res)` to display results.
     *
     * @memberof heurist.lookupNakala
     * @instance
     * @private
     * @override
     * @param {Object|string} json_data - The JSON response from the Nakala search.
     *                                  Expected to have a `records` property (object/dictionary)
     *                                  and a `count` property.
     * @returns {void}
     */
    _onSearchResult: function(json_data){

        let maxRecords = $('#rec_limit').val(); // Get max records from UI
        maxRecords = (!maxRecords || maxRecords <= 0) ? 20 : maxRecords; // Default to 20

        json_data = window.hWin.HEURIST4.util.isJSON(json_data); // Ensure JS object

        // Handle cases with no or invalid data
        if(!json_data || !Object.hasOwn(json_data, 'records') || Object.keys(json_data.records).length == 0){
            this._super(Object.keys(json_data.records || {}).length == 0 ? null : false); // Pass null for no records, false for error
            return;
        }

        let res_records = {}, res_orders = [];

        // Prepare fields for HRecordSet: base fields + mapped fields + 'rec_url'
        let fields = ['rec_ID', 'rec_RecTypeID'];
        let map_flds = Object.keys(this.options.mapping.fields).concat('rec_url'); // Source fields from Nakala
        fields = fields.concat(map_flds); // All fields for the HRecordSet

        // Parse Nakala records into HRecordSet structure
        for(const recID in json_data.records){ // Nakala returns records as an object, keys are Nakala IDs
            let record = json_data.records[recID];
            let values = [recID, this.options.mapping.rty_ID]; // Use Nakala ID as local rec_ID for display

            for(const fld_Name of map_flds){ // Populate values based on defined mapping
                values.push(record[fld_Name]);
            }

            res_orders.push(recID); // Order by Nakala ID
            res_records[recID] = values;
        }

        // Warn if more results are available than shown
        if(json_data.count > maxRecords){
            window.hWin.HEURIST4.msg.showMsgDlg(
                `There are ${json_data.count} records satisfying these criteria, only the first ${maxRecords} are shown.<br>Please narrow your search.`
            );
        }

        let res = res_orders.length > 0 ? {fields: fields, order: res_orders, records: res_records} : false;
        this._super(res); // Pass to parent for display
    },

    /**
     * Formats the year value obtained from the `#inpt_year` UI element for use in the Nakala API query.
     * If the input string contains multiple years (e.g., "2000 2001" or "2000,2001"),
     * it ensures they are comma-separated without spaces (e.g., "2000,2001").
     *
     * @memberof heurist.lookupNakala
     * @instance
     * @private
     * @returns {string} The formatted year string for the API query.
     */
    getYear: function(){

        let years = this.element.find('#inpt_year').val();
        if(years.length > 4){
            if(years.indexOf(',') === -1 && years.indexOf(' ') === -1){
                years = years.replace(/.{4}/g, '$&,');
            }
            if(years.indexOf(',') === -1){
                years = years.replaceAll(' ', ',');
            }
            if(years.indexOf(', ') !== -1){
                years = years.replaceAll(', ', ',');
            }
        }

        return years;
    }
});