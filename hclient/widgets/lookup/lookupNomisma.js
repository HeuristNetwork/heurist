/**
 * lookupNomisma.js - Search Nomisma.org records for numismatic concepts.
 *
 * @fileOverview
 * This file defines the `heurist.lookupNomisma` jQuery UI widget.
 * This widget provides an interface for searching records from Nomisma.org,
 * a collaborative project to provide stable digital representations of numismatic concepts.
 * It allows users to search across different Nomisma API endpoints:
 * `getMints`, `getHoards`, and `getFindspots`.
 *
 * The widget:
 *  1. Loads its UI from `lookupNomisma.html`.
 *  2. Constructs API requests to the selected Nomisma endpoint based on user input.
 *  3. Processes the GeoJSON responses from Nomisma.
 *  4. Displays results in a list, converting GeoJSON geometry to WKT (Well-Known Text)
 *     for Heurist geospatial fields if mapped.
 *  5. Allows users to select records for data mapping.
 *
 * Note: The `getRdf` service is mentioned as currently unavailable in the original comments.
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

/* global stringifyMultiWKT */

/**
 * Widget for searching records from Nomisma.org.
 * It supports querying different Nomisma API endpoints like getMints, getHoards, and getFindspots.
 * Results, often in GeoJSON format, are processed and displayed, with an option to convert
 * geometries to WKT for Heurist mapping.
 *
 * @widget heurist.lookupNomisma
 * @augments heurist.lookupBase
 */
$.widget( "heurist.lookupNomisma", $.heurist.lookupBase, {

    /**
     * Default options for the Nomisma lookup widget.
     * @memberof heurist.lookupNomisma
     * @instance
     * @property {Object} options
     * @property {number} [options.height=720] - The height of the dialog.
     * @property {number} [options.width=510] - The width of the dialog.
     * @property {string} [options.title='Search Nomisma database of coins and currency via several options'] - The title of the dialog.
     * @property {string} [options.htmlContent='lookupNomisma.html'] - The HTML content file for the dialog.
     */
    options: {

        height: 720,
        width:  510,

        title:  'Search Nomisma database of coins and currency via several options',

        htmlContent: 'lookupNomisma.html'
    },

    /**
     * The base URL for the Nomisma API. This is dynamically set in `_doSearch`
     * based on the type of search (Mints, Hoards, Findspots).
     * @memberof heurist.lookupNomisma
     * @instance
     * @type {string}
     */
    baseURL: '',

    /**
     * The service name identifier for this lookup type.
     * @memberof heurist.lookupNomisma
     * @instance
     * @type {string}
     */
    serviceName: 'nomisma',

    /**
     * jQuery selector for identifying the search buttons.
     * Used to attach click handlers in the parent `lookupBase`.
     * This widget has multiple search buttons for different Nomisma endpoints.
     * @memberof heurist.lookupNomisma
     * @instance
     * @type {string}
     */
    search_button_selector: '#btnMintSearch, #btnHoardsSearch, #btnFindspotsSearch',

    /**
     * Initializes UI controls for the Nomisma lookup widget.
     * - Applies specific CSS styling to header elements.
     * - Hides the RDF search button (`#btnRdfSearch`) as it's marked unavailable.
     * - Sets a custom `empty_remark` for the result list, providing more context for Nomisma searches.
     * - Calls the parent widget's `_initControls` method.
     *
     * @memberof heurist.lookupNomisma
     * @instance
     * @private
     * @override
     * @returns {void|*} The result of `this._super()`.
     */
    _initControls: function(){

        // Extra field styling
        this.element.find('#search_container > div > div > .header.recommended').css({'min-width':'65px', display: 'inline-block'});

        this.element.find('#btnRdfSearch').hide();

        // Prepare result list options
        this.options.resultList = $.extend(this.options.resultList, {
            empty_remark: '<div style="padding:1em 0 1em 0">No Results Found<br><br>'
                        + 'This result may also be due to a misconfiguration/failed connection to the Nomisma server.<br>'
                        + 'Please advise the Heurist team if this persists with searches which you are sure should return results.</div>' // For empty results
        });

        return this._super();
    },

    /**
     * Renders a single record in the result list for Nomisma search results.
     * This method overrides the parent's `_rendererResultList`.
     * It constructs a display string (`recTitle`) by concatenating several fields from the Nomisma record,
     * with different fields used depending on whether the record type is 'hoard' or something else (e.g., mint, findspot).
     *
     * @memberof heurist.lookupNomisma
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
         * - For 'dates', constructs a composite date range string.
         * - For 'properties.gazetteer_uri', creates a hyperlink.
         * - For 'properties.count', formats it as "(count: N)".
         * - HTML escapes the value and wraps it in a div with a specified width for truncation.
         * @param {string} fldname - The name of the field (can be dot-separated for nested properties).
         * @param {number} width - The display width for the field in 'ex' units. If 0, no div wrapper.
         * @returns {string} HTML string for the formatted field.
         */
        function fld(fldname, width){
            let s;
            if(fldname == 'dates'){ // Construct date range string
                s = `${recordset.fld(record, 'when.timespans.start') || ''} to ${recordset.fld(record, 'when.timespans.end') || ''}`
                        + ` (end date: ${recordset.fld(record, 'properties.closing_date') || ''})`;
            }else{
                s = recordset.fld(record, fldname);
            }

            s = s || ''; // Default to empty string if null/undefined
            let title = s; // Tooltip defaults to the raw (or constructed) value

            if(fldname == 'properties.gazetteer_uri'){
                s = `<a href="${s}" target="_blank" rel="noopener"> view here </a>`;
                title = 'View nomisma record';
            }else if(fldname == 'properties.count'){
                s = `(count: ${s})`;
            }

            return width > 0 ? `<div style="display:inline-block;width:${width}ex" class="truncate" title="${title}">${s}</div>` : s;
        }
        
        let recTitle = '';
        // Different display format based on the type of Nomisma record
        if(fld('properties.type') == 'hoard'){
            recTitle = fld('properties.type', 10) + fld('label', 30) + fld('dates', 35) + fld('properties.gazetteer_uri', 10);
        }else{ // For mints, findspots, etc.
            recTitle = fld('properties.type', 10) + fld('properties.gazetteer_label', 30) + fld('properties.count', 15) + fld('properties.gazetteer_uri', 10); 
        }
        recordset.setFld(record, 'rec_Title', recTitle); // Set the formatted title

        return this._super(recordset, record); // Call parent's renderer
    },

    /**
     * Processes the user's selection from the Nomisma result list.
     * This method is called when the main action button (e.g., "Select") is clicked.
     * It retrieves the selected record set and passes it directly to `this.closingAction`.
     * Unlike other lookups, it doesn't seem to perform detailed field-by-field mapping here;
     * it implies that the entire selected record's data (as processed by `_onSearchResult`)
     * might be returned, or further processing is expected by the calling context.
     *
     * @memberof heurist.lookupNomisma
     * @instance
     * @override
     * @returns {void}
     */
    doAction: function(){

        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){
            return;
        }

        this._super(record[6]);
    },

    /**
     * Constructs the Nomisma API search URL based on the clicked search button
     * and executes the search by calling the parent's `_doSearch` method.
     *
     * - Determines the `search_type` (e.g., 'mint', 'hoard', 'findspots') from the clicked button's value.
     * - If the main search input (`#inpt_any`) is empty, shows a flash message and returns.
     * - Sets `this.baseURL` to the appropriate Nomisma API endpoint based on `search_type`.
     * - Calls `this._super({id: this.element.find('#inpt_any').val()})`. The parent `_doSearch`
     *   (from `lookupBase`) will then construct the full URL using `this.baseURL` and the provided parameters.
     *
     * @memberof heurist.lookupNomisma
     * @instance
     * @private
     * @override
     * @param {jQuery.Event} event - The click event object from the search button.
     *                               The button's value indicates the search type.
     * @returns {void}
     */
    _doSearch: function(event){

        let search_type = $(event.target).val(); // Get search type from button value (e.g., 'mint', 'hoard')

        if(this.element.find('#inpt_any').val()==''){
            window.hWin.HEURIST4.msg.showMsgFlash('Enter value to search...', 500);
            return;
        }

        switch(search_type){
            case 'mint':
                this.baseURL = 'https://nomisma.org/apis/getMints?id=';
                break;
            case 'hoard':
                this.baseURL = 'https://nomisma.org/apis/getHoards?id=';
                break;
            case 'findspots':
                this.baseURL = 'https://nomisma.org/apis/getFindspots?id=';
                break;
            default:
                return;
        }

        this._super({id: this.element.find('#inpt_any').val()});
    },
    
    /**
     * Processes the GeoJSON search results received from the Nomisma API.
     * This method overrides the parent `_onSearchResult`.
     *
     * - Validates if `geojson_data` is valid GeoJSON. If not, calls `this._super(false)`.
     * - Prepares field list for HRecordSet: 'rec_ID', 'rec_RecTypeID', and fields from `this.options.mapping.fields`.
     *   Mapped field names can be dot-separated to access nested properties in the GeoJSON features.
     * - Normalizes input: if `geojson_data.features` doesn't exist, assumes `geojson_data` itself is the features array/object.
     * - Iterates through each `feature` in `geojson_data.features`:
     *   - Assigns a local sequential `recID`.
     *   - Creates a `values` array for the HRecordSet row.
     *   - For each mapped field:
     *     - Extracts the value from the feature, handling nested properties using `getValueByParts`.
     *     - Handles temporal data extraction using `getTimespan`.
     *     - If the target Heurist field is a geospatial type (matches `DT_GEO_OBJECT`) and the value is not empty,
     *       it converts the GeoJSON geometry to WKT format using `createGeoFeature`.
     *     - If WKT conversion results in an empty string, the `hasGeo` flag is set to false, and this feature might be skipped.
     *   - If `hasGeo` is true (or if geospatial mapping wasn't primary), adds the record to `res_records` and `res_orders`.
     * - Constructs the final result object or `false`.
     * - Calls `this._super(res)` to display results.
     *
     * @memberof heurist.lookupNomisma
     * @instance
     * @private
     * @override
     * @param {Object} geojson_data - The GeoJSON response from the Nomisma API.
     * @returns {void}
     */
    _onSearchResult: function(geojson_data){

        if(!window.hWin.HEURIST4.util.isGeoJSON(geojson_data, true)){ // Validate GeoJSON
            this._super(false); // Show error/clear if not valid
            return;
        }

        let res_records = {}, res_orders = [];
        const DT_GEO_OBJECT = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT']; // Heurist ID for geospatial field type

        // Prepare fields for HRecordSet
        let fields = ['rec_ID','rec_RecTypeID']; // Base fields
        let map_flds = Object.keys(this.options.mapping.fields); // Mapped fields from config
        fields = fields.concat(map_flds);

        // Split dot-separated mapped field names for nested property access
        map_flds = map_flds.map((prop) => prop.split('.'));

        // Normalize: Nomisma API returns features in geojson_data.features
        let features_array = geojson_data.features || geojson_data; // Fallback if structure is flatter
        features_array = Array.isArray(features_array) ? features_array : [features_array];

        let i = 0; // Local record ID counter
        for(const feature of features_array){
            let recID = i++;

            let hasGeo = true; // Flag to track if essential geo data is present if mapped
            let values = [recID, this.options.mapping.rty_ID]; // Start with local ID and target rty_ID
            for(const fld_Names of map_flds){

                let val = feature[fld_Names[0]]; // Start with the whole feature object

                // Handle temporal data extraction first if field name suggests it (e.g., "when.timespans.start")
                val = this.getTimespan(fld_Names, val);

                // Then extract potentially nested value
                val = this.getValueByParts(fld_Names, val);

                if(DT_GEO_OBJECT == this.options.mapping.fields[fld_Names] && !window.hWin.HEURIST4.util.isempty(val)){ // looking for geospatial values
                    val = this.createGeoFeature(val);
                    hasGeo = !window.hWin.HEURIST4.util.isempty(val);
                } // else not looking for geospatial values

                values.push(val);    
            }

            if(hasGeo){ // Add record only if essential geo data (if mapped to geo field) is valid
                res_orders.push(recID);
                res_records[recID] = values;    
            }
        }

        let res = res_orders.length > 0 ? {fields: fields, order: res_orders, records: res_records} : false;
        this._super(res); // Pass to parent for display
    }
});