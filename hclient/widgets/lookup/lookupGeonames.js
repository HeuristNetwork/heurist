/**
 * lookupGeonames.js - Base widget for GeoNames lookups.
 *
 * @fileOverview
 * This file defines the `heurist.lookupGeonames` jQuery UI widget.
 * It serves as a base class for more specific GeoNames lookup widgets,
 * such as general place name search (`lookupGN`) and postal code search (`lookupGN_postalCode`).
 *
 * This base widget handles:
 * - Dynamic loading of HTML content based on whether the lookup is for postal codes or general search.
 * - Initialization of common UI controls, like a country dropdown populated from a Heurist vocabulary.
 * - Common logic for processing selected GeoNames records (`doAction`), including extracting an external URL.
 * - A utility method (`handleGeoValue`) to potentially clean up latitude/longitude data if it's duplicated
 *   in a combined location field.
 * - A helper (`_prepareMappableFields`) to prepare the list of fields for mapping from GeoNames results.
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

/**
 * Base widget for GeoNames lookups.
 * Provides common functionality for widgets that interact with the GeoNames API.
 * Specific lookup types (e.g., general search, postal code search) should extend this widget.
 *
 * @widget heurist.lookupGeonames
 * @extends heurist.lookupBase
 */
$.widget("heurist.lookupGeonames", $.heurist.lookupBase, {

    /**
     * Default options for the GeoNames lookup widget.
     * @memberof heurist.lookupGeonames
     * @instance
     * @property {Object} options
     * @property {number} [options.height=520] - The height of the dialog.
     * @property {number} [options.width=800] - The width of the dialog.
     * @property {string} [options.title='Lookup values Postal codes for Heurist record']
     *           - Default title. Specific inheriting widgets usually override this.
     * @property {string} options.htmlContent - The name of the HTML file for the dialog's content.
     *                                        This is dynamically set in `_init` based on whether the lookup
     *                                        is for postal codes ('lookupGN_postalCode.html') or general
     *                                        GeoNames search ('lookupGN.html').
     * @property {Object} options.mapping - Configuration from `record_lookup_config.json`.
     *   @property {string} options.mapping.service - The specific GeoNames service type (e.g., 'postalCodeSearch', 'geonames').
     */
    options: {

        height: 520,
        width: 800,

        title: 'Lookup values Postal codes for Heurist record', // Generic title, often overridden

        htmlContent: '' // Dynamically set in _init
    },

    /**
     * Flag indicating whether the current lookup is specifically for postal codes.
     * `true` if `this.options.mapping.service` is 'postalCodeSearch', `false` otherwise.
     * This controls which HTML content is loaded.
     * @memberof heurist.lookupGeonames
     * @instance
     * @private
     * @type {boolean}
     */
    _is_postal_codes: false,

    /**
     * Stores the Heurist vocabulary ID for countries (Term ID '2-509').
     * Used to populate the country selection dropdown.
     * @memberof heurist.lookupGeonames
     * @instance
     * @private
     * @type {number}
     */
    _country_vocab_id: 0,

    /**
     * Initializes the GeoNames lookup widget.
     * - Sets `this._is_postal_codes` based on `this.options.mapping.service`.
     * - Dynamically sets `this.options.htmlContent` to 'lookupGN_postalCode.html' or 'lookupGN.html'.
     * - Calls the parent widget's `_init` method.
     *
     * @memberof heurist.lookupGeonames
     * @instance
     * @private
     * @override
     * @returns {void|*} The result of `this._super()`.
     */
    _init: function(){

        this._is_postal_codes = this.options.mapping.service == 'postalCodeSearch';

        this.options.htmlContent = this._is_postal_codes ? 'lookupGN_postalCode.html' : 'lookupGN.html';

        return this._super();
    },

    /**
     * Initializes UI controls common to GeoNames lookups.
     * - Populates the country dropdown (`#inpt_country`) using terms from the
     *   Heurist vocabulary for countries (ID '2-509', which is `this._country_vocab_id`).
     * - Sets a custom `empty_remark` for the result list.
     * - Calls the parent widget's `_initControls` method.
     *
     * @memberof heurist.lookupGeonames
     * @instance
     * @private
     * @override
     * @returns {void|*} The result of `this._super()`.
     */
    _initControls: function(){

        // Fill countries dropdown
        let ele = this.element.find('#inpt_country');
        this._country_vocab_id = $Db.getLocalID('trm','2-509'); // Vocabulary for countries

        if(this._country_vocab_id > 0){
            window.hWin.HEURIST4.ui.createTermSelect(ele.get(0), {vocab_id:this._country_vocab_id,topOptions:'select...',useHtmlSelect:false});
        }
        // Adjust width of the country select dropdown if hSelect is used
        if(ele.hSelect('instance') != 'undefined'){ // Note: original code has 'undefined' as string
            ele.hSelect('widget').css({'max-width':'30em'});
        }

        // Set custom message for empty result list
        this.options.resultList = $.extend(this.options.resultList, {
            empty_remark: '<div style="padding:1em 0 1em 0">No Locations Found</div>'
        });

        return this._super();
    },

    /**
     * Processes the user's selection from the GeoNames result list.
     * This method is called when the main action button (e.g., "Select") is clicked.
     *
     * 1. Retrieves the selected record using `_getSelection`.
     * 2. Determines the appropriate external link field (`googlemap_link` for postal codes,
     *    `geoname_link` otherwise) and extracts its value into `res['ext_url']`.
     * 3. Calls `this.prepareValues` to map other selected data to Heurist fields,
     *    passing `check_term_codes: this._country_vocab_id` to potentially map country codes to terms.
     * 4. Calls `this.handleGeoValue` to process and potentially clean up latitude/longitude fields.
     * 5. Calls `this.closingAction` to pass the prepared `res` object back and close the dialog.
     *
     * @memberof heurist.lookupGeonames
     * @instance
     * @override
     * @returns {void}
     */
    doAction: function(){

        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){
            return;
        }

        let link_field = this._is_postal_codes ? 'googlemap_link' : 'geoname_link';

        let res = {};
        res['ext_url'] = recset.fld(record, link_field);
        res = this.prepareValues(recset, record, res, {check_term_codes: this._country_vocab_id});

        res = this.handleGeoValue(res);

        // Pass mapped values and close dialog
        this.closingAction(res);
    },

    /**
     * Post-processes the mapped field values (`res`) to handle potential duplication
     * between separate latitude/longitude fields and a combined WKT location field.
     *
     * If the `res` object contains fields with 'lat' and 'long' in their keys (e.g., `dtyID_lat`, `dtyID_long`)
     * AND a corresponding base location field (e.g., `dtyID` for a WKT point), this method
     * attempts to remove redundant latitude and longitude values from their respective arrays
     * if those exact coordinate values are already present within the WKT string(s) of the base location field.
     *
     * This is intended to prevent saving duplicate coordinate information if a user maps, for example,
     * GeoNames `lat` to `MyLatField`, `lng` to `MyLngField`, and also `lat`+`lng` (via `constructLocation`)
     * to `MyWKTPointField`. If `MyWKTPointField` already contains the exact lat/lng, they might be
     * removed from `MyLatField` and `MyLngField` arrays.
     *
     * @memberof heurist.lookupGeonames
     * @instance
     * @param {Object} res - The object containing mapped field values, where keys are dty_IDs.
     *                       Values are typically arrays of strings or numbers.
     * @returns {Object} The modified `res` object with potentially cleaned latitude/longitude arrays.
     */
    handleGeoValue: function(res){

        // Find keys that seem to represent latitude or longitude fields
        let geo_keys = Object.keys(res);
        geo_keys = geo_keys.filter((key) => key.indexOf('lat') > 0 || key.indexOf('long') > 0);

        if(geo_keys.length != 2){ // Expecting one lat and one long field (or suffixed versions)
            return res;            
        }

        // Assume the base location field key can be derived by removing "_lat" or "_long" suffix
        let location_key = geo_keys[0].split('_')[0];

        if(!Object.hasOwn(res, location_key)){ // If the base location field (e.g., WKT field) isn't mapped
            return res;
        }

        let locations = res[location_key]; // Array of WKT strings or similar
        let latitude_arr = res[geo_keys[0]];  // Array of latitude values
        let longitude_arr = res[geo_keys[1]]; // Array of longitude values

        let idx = 0;
        // Iterate while there are corresponding lat/long values to check
        while(idx < latitude_arr.length && idx < longitude_arr.length){
            const lat_val = latitude_arr[idx];
            const long_val = longitude_arr[idx];

            // Check if *any* of the WKT location strings contain both the current lat and long values.
            // This is a simplified check; robustly checking if a WKT point *is* these coordinates would be more complex.
            let found_in_wkt = false;
            for (const loc_str of locations) {
                if (typeof loc_str === 'string' && loc_str.includes(String(lat_val)) && loc_str.includes(String(long_val))) {
                    found_in_wkt = true;
                    break;
                }
            }

            if(found_in_wkt){
                // If found in any WKT string, remove from the separate lat/long arrays
                latitude_arr.splice(idx, 1);
                longitude_arr.splice(idx, 1);
                // Do not increment idx, as the array length changed and next element is now at current idx
                continue;
            }
            idx ++; // Move to next lat/long pair
        }

        // Update the response object with potentially modified arrays
        res[geo_keys[0]] = latitude_arr;
        res[geo_keys[1]] = longitude_arr;

        return res;
    },

    /**
     * Prepares the list of fields to be mapped from the GeoNames service results.
     * It starts with base fields 'rec_ID' and 'rec_RecTypeID'.
     *
     * It ensures that a 'location' field is included in the mapping if individual 'lat' and 'lng'
     * fields are mapped, defaulting the 'location' field to map to the same Heurist dty_ID
     * as 'lng' or 'lat', or to the system constant `DT_GEO_OBJECT` if they are different or not specified.
     * This facilitates the creation of a WKT point string.
     *
     * Finally, if `link_field` (e.g., 'geoname_link' or 'googlemap_link') is provided, it's added
     * to the list of fields to be included in the HRecordSet structure for the result list.
     *
     * @memberof heurist.lookupGeonames
     * @instance
     * @private
     * @param {?string} link_field - An optional additional field name (typically for an external link)
     *                               to include in the prepared field list.
     * @returns {Array<Array<string>, Array<string>>} A tuple where:
     *          - The first element (`map_flds`) is an array of field names from `this.options.mapping.fields`
     *            (potentially with 'location' added). These are the source field names from GeoNames data.
     *          - The second element (`fields`) is an array of all field names to be included in the
     *            HRecordSet (base fields + `map_flds` + optional `link_field`).
     */
    _prepareMappableFields: function(link_field){

        let fields = ['rec_ID', 'rec_RecTypeID']; // Base fields for HRecordSet

        const DT_GEO_OBJECT = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'];
        if(!Object.hasOwn(this.options.mapping.fields, 'location')
        && (Object.hasOwn(this.options.mapping.fields, 'lng') || Object.hasOwn(this.options.mapping.fields, 'lat'))){

            const same_field = this.options.mapping.fields['lng'] == this.options.mapping.fields['lat'];
            const default_fld = this.options.mapping.fields['lng'] || this.options.mapping.fields['lat'] || DT_GEO_OBJECT;
            this.options.mapping.fields['location'] = same_field ? this.options.mapping.fields['lng'] : default_fld;
        }

        let map_flds = Object.keys(this.options.mapping.fields);

        fields = fields.concat(map_flds);

        if(!window.hWin.HEURIST4.util.isempty(link_field)){
            fields = fields.concat('geoname_link');
        }

        return [map_flds, fields];
    }
});
