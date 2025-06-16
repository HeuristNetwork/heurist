/**
 * lookupBnFLibrary_bib.js - Search BnF bibliographic records.
 *
 * @fileOverview
 * This file defines the `heurist.lookupBnFLibrary_bib` jQuery UI widget.
 * This widget specializes `heurist.lookupBnF` for searching bibliographic
 * records from the Bibliothèque nationale de France (BnF) SRU API.
 *
 * It configures the specific HTML content (`lookupBnFLibrary_bib.html`),
 * sets the BnF SRU API base URL for bibliographic searches, and provides
 * the logic for constructing search queries based on user input from the
 * form fields defined in its corresponding HTML. It includes detailed methods
 * for rendering results, extracting and processing complex data like author
 * and publisher information, and handling the mapping of this data to
 * Heurist fields.
 *
 * Key functionalities include:
 * - Loading the content of `lookupBnFLibrary_bib.html`.
 * - Performing an API call to the BnF SRU API using user input for bibliographic data.
 * - Displaying results within a Heurist result list with custom rendering.
 * - Extracting and processing author, publisher, and language information from the results.
 * - Handling record selection and preparing data for Heurist mapping,
 *   including prompting for record pointer creation/selection and term correction.
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

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
 * @namespace heurist
 */

/**
 * Widget for searching BnF (Bibliothèque nationale de France) bibliographic records.
 * Inherits from `$.heurist.lookupBnF`.
 *
 * This widget targets the BnF SRU API for bibliographic records, using a specific
 * set of UI elements defined in `lookupBnFLibrary_bib.html`. It constructs
 * SRU queries based on user input (e.g., title, author, record ID) and processes
 * the XML results, with particular attention to extracting structured author,
 * publisher, and language data.
 *
 * @class heurist.lookupBnFLibrary_bib
 * @memberof heurist
 * @augments heurist.lookupBnF
 */
$.widget( "heurist.lookupBnFLibrary_bib", $.heurist.lookupBnF, {

    /**
     * Default options for the widget.
     * @memberof heurist.lookupBnFLibrary_bib
     * @instance
     * @property {Object} options
     * @property {number} [options.height=700] - The height of the dialog.
     * @property {number} [options.width=800] - The width of the dialog.
     * @property {string} [options.title="Search the Bibliothèque nationale de France's bibliographic records"] - The title of the dialog.
     * @property {string} [options.htmlContent='lookupBnFLibrary_bib.html'] - The HTML content file for the dialog (implicitly set by parent `lookupBnF` based on service).
     */
    options: {
    
        height: 700,
        width:  800,
        
        title:  "Search the Bibliothèque nationale de France's bibliographic records"
    },

    /**
     * The base URL for the BnF SRU API.
     * @memberof heurist.lookupBnFLibrary_bib
     * @instance
     * @type {string}
     */
    baseURL: 'https://catalogue.bnf.fr/api/SRU?',

    /**
     * The service name identifier for this lookup type.
     * @memberof heurist.lookupBnFLibrary_bib
     * @instance
     * @type {string}
     */
    serviceName: 'bnflibrary_bib',

    /**
     * Initializes UI controls.
     * Applies specific CSS styling to header and form field elements within the widget.
     * Calls the parent widget's `_initControls` method.
     *
     * @memberof heurist.lookupBnFLibrary_bib
     * @instance
     * @private
     * @override
     * @returns {void} Calls `this._super()`.
     */
    _initControls: function(){

        // Extra field styling
        this.element.find('.header.recommended').css({width: '100px', 'min-width': '100px', display: 'inline-block'});
        this.element.find('.bnf_form_field').css({display:'inline-block', 'margin-top': '7.5px'});

        return this._super();
    },

    /**
     * Sets up the "Additional Settings" tab with default values specific to BnF bibliographic lookups.
     * It calls the parent's `_setupSettings` method, providing default options for:
     * - `author_codes`: Defaulting to an empty string. This might be used for filtering authors by role codes.
     * - `dump_record`: Defaulting to `true` to enable record dumping.
     * - `dump_field`: Defaulting to 'rec_ScratchPad' as the target for record dumping.
     *
     * @memberof heurist.lookupBnFLibrary_bib
     * @instance
     * @private
     * @override
     * @returns {void}
     */
    _setupSettings: function(){

        this._super({
            author_codes: '', // Default for author role codes setting
            dump_record: true,
            dump_field: 'rec_ScratchPad'
        });
    },

    /**
     * Retrieves author and contributor role codes from the settings UI.
     * It reads text from `#author_codes` (and potentially `#contributor-codes` if implemented),
     * extracts numeric sequences (assumed to be role codes), and returns them as comma-separated strings.
     *
     * Note: Contributor codes are currently commented out in the original code.
     *
     * @memberof heurist.lookupBnFLibrary_bib
     * @instance
     * @private
     * @returns {Array<string, string>} An array containing two strings:
     *          - The first string is the comma-separated list of author role codes.
     *          - The second string is the comma-separated list of contributor role codes (currently always empty).
     */
    _getRoleCodes: function(){

        let author_codes = this.element.find('#author_codes').text();
        let contributor_codes = ''; //this.element.find('#contributor-codes').text() // Currently not used
        const regex = /\d+/g; // Regular expression to match numeric codes

        // Extract and join numeric codes for authors
        author_codes = regex.test(author_codes) ? author_codes.match(regex).join(',') : '';

        return [ author_codes, contributor_codes ];
    },

    /**
     * Saves extra settings, including author role codes and record dump preferences.
     * It retrieves author codes using `_getRoleCodes` and record dump settings via `_getRecDumpSetting`.
     * If the `settings` parameter is not `null` (indicating settings should be actively gathered or are provided),
     * it constructs a settings object with `author_codes`, `dump_record`, and `dump_field`.
     * Then, it calls the parent's `_saveExtraSettings` method.
     *
     * @memberof heurist.lookupBnFLibrary_bib
     * @instance
     * @private
     * @override
     * @param {Object|boolean|null} [settings=false] - If `true` or an object, settings are gathered/used.
     *                                                If `null`, parent might save defaults or just close.
     *                                                If `false` (default), settings are gathered.
     * @param {boolean} [close_dlg=false] - Whether to close the dialog after saving.
     * @returns {void}
     */
    _saveExtraSettings: function(settings = false, close_dlg = false){

        const codes = this._getRoleCodes();
        const rec_dump_settings = this._getRecDumpSetting();

        // Original code uses `settings !== null`. If settings is explicitly false, it should still gather.
        // Assuming settings=false means gather, settings=null means do not gather and let parent handle.
        // For safety, aligning with original logic: if settings is not strictly null, then prepare them.
        if(settings !== null){
            settings = {
                author_codes: codes[0],
                dump_record: rec_dump_settings[0],
                dump_field: rec_dump_settings[1]
            };
        }

        this._super(settings, close_dlg);
    },

    /**
     * Renders a single record in the result list for BnF bibliographic records.
     * This method overrides the parent's `_rendererResultList` to provide custom formatting
     * for bibliographic data, particularly for authors and publishers.
     *
     * It constructs a display string (`recTitle`) by concatenating several fields:
     * 'author', 'publisher', 'date', 'title', and 'biburl'.
     * The 'author' and 'publisher' fields are processed by `getAuthorHTML` and `getPublisherHTML` respectively.
     * The 'biburl' is rendered as a hyperlink.
     * The concatenated string is then set as the 'rec_Title' for the record before calling
     * the parent's `_rendererResultList`.
     *
     * @memberof heurist.lookupBnFLibrary_bib
     * @instance
     * @private
     * @override
     * @param {HRecordSet} recordset - The complete record set.
     * @param {Array} record - The individual record (row) to be rendered.
     * @returns {string} The HTML string for the rendered record, generated by the parent's `_rendererResultList`.
     */
    _rendererResultList: function(recordset, record){

        let that = this; // Reference to the widget instance for use in inner function

        /**
         * Formats a field's value for display in the result list.
         * It handles specific formatting for 'author', 'publisher', and 'biburl' fields.
         * Other fields are HTML escaped and joined if they are arrays.
         * This is an inner helper function for `_rendererResultList`.
         *
         * @param {string} fldname - The name of the field in the recordset (e.g., 'author', 'title').
         * @param {number} width - The display width for the field in 'ex' units. If 0, no div wrapper is used.
         * @returns {string} HTML string for the formatted field.
         */
        function fld(fldname, width){

            let s = recordset.fld(record, fldname); // Get the raw field value

            // Special handling for complex fields
            if(fldname == 'author'){
                s = that.getAuthorHTML(recordset, record); // Process author data
            }else if(fldname == 'publisher'){
                s = that.getPublisherHTML(s); // Process publisher data
            }else{
                // For simple fields, join if array, then HTML escape
                s = Array.isArray(s) ? s.join('; ') : s;
                s = window.hWin.HEURIST4.util.htmlEscape(s || '');
            }

            let title = s; // Use the processed string for the tooltip by default

            // Format 'biburl' as a hyperlink
            if(fldname == 'biburl'){
                s = `<a href="${s}" target="_blank" rel="noopener"> view here </a>`;
                title = 'View bibliographic record'; // Custom tooltip for the link
            }

            // Wrap in a div with specified width for truncation, if width > 0
            return width > 0 ? `<div style="display:inline-block;width:${width}ex" class="truncate" title="${title}">${s}</div>` : s;
        }

        // Construct the composite title string for the record
        const recTitle = fld('author', 25) + fld('publisher', 20) + fld('date', 10) + fld('title', 70) + fld('biburl', 12);
        recordset.setFld(record, 'rec_Title', recTitle); // Set the formatted title back into the recordset

        return this._super(recordset, record); // Call parent renderer
    },

    /**
     * Generates an HTML string representation for author and contributor data of a record.
     * It retrieves 'author' and 'contributor' fields from the record. If contributors exist,
     * they are merged with authors.
     *
     * Each author/contributor object (which may contain 'firstname', 'surname', 'active', 'id', 'role')
     * is processed by `_extractAuthorValue` (in display mode) to get a string representation.
     * These strings are then concatenated, separated by semicolons.
     *
     * @memberof heurist.lookupBnFLibrary_bib
     * @instance
     * @param {HRecordSet} recordset - The full recordset.
     * @param {Array} record - The specific record (row) being processed.
     * @returns {string} An HTML formatted string of author and contributor names and details,
     *                   or "No provided creator" if no author/contributor data is found.
     */
    getAuthorHTML: function(recordset, record){

        let value = recordset.fld(record, 'author');

        let contributor = recordset.fld(record, 'contributor');
        if(contributor !== undefined){
            value = window.hWin.HEURIST4.util.isempty(value) ? contributor : {...contributor, ...value};
        }

        if(window.hWin.HEURIST4.util.isempty(value)){
            return 'No provided creator';
        }

        let creator_val = '';

        for(let idx in value){

            let cur_obj = value[idx];
            let cur_string = cur_obj;

            if(window.hWin.HEURIST4.util.isObject(cur_obj)){
                cur_string = this._extractAuthorValue(cur_obj, false);
            }

            creator_val += !cur_string || typeof cur_string !== 'string' ? 'Missing author; ' : `${cur_string}; `;
        }

        return creator_val;
    },

    /**
     * Extracts and formats author information from an author object.
     * This method can return either a simple display string or a structured array
     * for search/mapping purposes, based on the `returning_search` flag.
     *
     * The input `cur_value` is an object expected to contain properties like:
     * `firstname`, `surname`, `active` (e.g., activity dates), `id` (BnF ID), `role`.
     *
     * If `returning_search` is `false` (default, for display):
     *   Constructs a string like: "Firstname Surname [active dates]".
     *
     * If `returning_search` is `true`:
     *   Returns an array: `[displayValue, searchValue, role]`
     *   - `displayValue`: "Firstname Surname [active dates] (id: BnF_ID)"
     *   - `searchValue`: "Firstname Surname" (used for searching in Heurist)
     *   - `role`: The author's role code.
     *
     * @memberof heurist.lookupBnFLibrary_bib
     * @instance
     * @private
     * @param {Object} cur_value - The author object containing details like firstname, surname, active dates, ID, and role.
     * @param {boolean} [returning_search=false] - If `true`, returns a structured array for mapping.
     *                                           If `false`, returns a formatted string for display.
     * @returns {string|Array<string, string, string|null>} A formatted string for display,
     *          or an array `[displayValue, searchValue, role]` for mapping.
     *          Returns the original `cur_value` if it's empty.
     */
    _extractAuthorValue: function(cur_value, returning_search = false){

        if(window.hWin.HEURIST4.util.isempty(cur_value)){
            return cur_value;
        }

        let value = '', search = '';

        value = cur_value['firstname'] ?? '';

        if(!window.hWin.HEURIST4.util.isempty(cur_value['surname'])){
            value = !window.hWin.HEURIST4.util.isempty(value) ? `${value} ${cur_value['surname']}` : cur_value['surname'];
        }

        search = value;

        if(!window.hWin.HEURIST4.util.isempty(cur_value['active'])){
            value += ` [${cur_value['active']}]`;
        }

        if(!returning_search){
            return value;
        }

        value = !window.hWin.HEURIST4.util.isempty(value) ? `${value} (id: ${cur_value['id']})` : `id: ${cur_value['id']}`;

        let role = !window.hWin.HEURIST4.util.isempty(value) ? cur_value['role'] : '';

        return [value, search, role];
    },

    /**
     * Generates an HTML string representation for publisher data.
     * The input `value` is expected to be an array of publisher objects or strings.
     *
     * Each publisher item is processed:
     * - If it's an object, `_extractPublisherValue` is called to get a display string.
     * - If it's a string, it's used directly.
     * These strings are then concatenated, separated by semicolons.
     *
     * @memberof heurist.lookupBnFLibrary_bib
     * @instance
     * @param {Array<Object|string>} value - An array of publisher data. Each element can be an object
     *                                     with 'name' and 'location' properties, or a simple string.
     * @returns {string} An HTML formatted string of publisher names and locations,
     *                   or "No provided publisher" if the input value is empty.
     */
    getPublisherHTML: function(value){

        if(window.hWin.HEURIST4.util.isempty(value)){
            return 'No provided publisher';
        }

        let pub_val = '';

        for(let idx in value){

            let cur_obj = value[idx];
            let cur_string = cur_obj;

            if(window.hWin.HEURIST4.util.isObject(cur_obj)){
                [cur_string] = this._extractPublisherValue(cur_obj);
            }

            pub_val += !cur_string || typeof cur_string !== 'string' ? 'Missing author; ' : `${cur_string}; `;
        }

        return pub_val;
    },

    /**
     * Extracts and formats publisher information from a publisher object.
     * The input `cur_value` is an object expected to contain `name` and `location` properties.
     *
     * It constructs a display string `value` (e.g., "Publisher Name Publisher Location")
     * and a `search` string (typically just the publisher's name).
     *
     * If `returning_search` is `true`:
     *   Returns an array `[displayValue, searchValue]`.
     * If `returning_search` is `false` (default):
     *   Returns an array `[displayValue, null]`. (The second element is null as search value might not be needed for pure display).
     *
     * @memberof heurist.lookupBnFLibrary_bib
     * @instance
     * @private
     * @param {Object} cur_value - The publisher object, expected to have `name` and `location` properties.
     * @param {boolean} [returning_search=false] - If `true`, the `search` part of the returned array
     *                                           will contain the publisher name. Otherwise, it's `null`.
     * @returns {Array<string, string|null>} An array where:
     *          - The first element is the formatted display string (e.g., "Name Location").
     *          - The second element is the search string (publisher name) if `returning_search` is true, otherwise `null`.
     */
    _extractPublisherValue: function(cur_value, returning_search = false){

        let value = '', search = '';

        if(!window.hWin.HEURIST4.util.isempty(cur_value['name'])){
            value = cur_value['name'];
            search = value;
        }
        if(!window.hWin.HEURIST4.util.isempty(cur_value['location'])){
            value = (value != '') ? `${value} ${cur_value['location']}` : cur_value['location'];
            search = (search != '') ? search : value;
        }

        return returning_search ? [value, search] : [value, null];
    },

    /**
     * Processes the user's selection from the result list for bibliographic records.
     * This method overrides the parent `doAction` method and is the primary handler
     * when a user selects a record and clicks the main action button.
     *
     * Steps:
     * 1. Shows a loading coverall.
     * 2. Retrieves the selected record using `_getSelection`. If no record is selected, it returns.
     * 3. Sets up a timeout using `setupTimeout` to handle potential delays in processing.
     * 4. Initializes an empty response object `res`.
     * 5. Adds 'BnF_ID' (from `recset.fld(record, 'BnF_ID')`) and 'ext_url' (from `recset.fld(record, 'biburl')`) to `res`.
     * 6. Iterates over the mapped fields defined in `this.options.mapping.fields`:
     *    a. Sets `this.timeout.field_name` and `this.timeout.value` for error reporting.
     *    b. Retrieves the Heurist dty_ID and the raw value from the selected record.
     *    c. Converts the raw value to an array using `valueToArray`.
     *    d. Based on `fld_Name` (the key from the mapping configuration), it calls specialized processing functions:
     *       - 'author', 'contributor': `getAuthorValues(val, field_type)`
     *       - 'publisher': `getPublisherValues(val, field_type)`
     *       - 'language': `getLanguageValues(val)`
     *       - Default: `prepareValue(val, dty_ID, {check_term_codes: true})` (which calls `_super` and then `_getTermByCode`)
     *    e. If processed `val` is not empty, it's added to the `res` object, mapped by `dty_ID`.
     * 7. Calls `closingAction(res)` to pass the processed data back and close the dialog.
     *
     * @memberof heurist.lookupBnFLibrary_bib
     * @instance
     * @override
     * @returns {void}
     */
    doAction: function(){

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element);

        // get selected recordset
        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){
            return;
        }

        this.setupTimeout();

        let res = {};
        let map_flds = Object.keys(this.options.mapping.fields); // mapped fields names, to access fields of rec

        res['BnF_ID'] = recset.fld(record, 'BnF_ID'); // add BnF ID
        res['ext_url'] = recset.fld(record, 'biburl'); // add BnF URL

        // Assign individual field values, here you would perform any additional processing for selected values (example. get ids for vocabulrary/terms and record pointers)
        for(const fld_Name of map_flds){

            this.timeout.field_name = fld_Name;
            let dty_ID = this.options.mapping.fields[fld_Name];
            if(dty_ID < 1){
                continue;
            }

            let val = recset.fld(record, fld_Name);
            this.timeout.value = val;

            let field_type = $Db.dty(dty_ID, 'dty_Type');

            val = this.valueToArray(val);

            if(window.hWin.HEURIST4.util.isempty(val)){
                continue;
            }

            switch(fld_Name){
                case 'author': // special treatment for author fields
                case 'contributor':

                    this.getAuthorValues(val, field_type);
                    break;
                case 'publisher':

                    this.getPublisherValues(val, field_type);
                    break;
                case 'language': // handle if language equals '###'

                    this.getLanguageValues(val);
                    break;
                default:

                    this.prepareValue(val, dty_ID, {check_term_codes: true});
                    break;
            }

            // Check that val and id are valid, add to response object
            if(window.hWin.HEURIST4.util.isempty(val)){
                continue;
            }
            if(!Object.hasOwn(res, dty_ID)){
                res[dty_ID] = [];
            }
            res[dty_ID] = res[dty_ID].concat(val);
        }

        this.closingAction(res);
    },

    /**
     * Prepares a field's value(s) for returning to the record editor, with specific handling for 'enum' types.
     * This method overrides the parent `prepareValue` method.
     *
     * It first calls the parent's `prepareValue` method (`this._super`) to perform
     * general value processing (like converting to array, handling record fields, trimming).
     *
     * Then, if the field type (`dty_Type`) for the given `dty_ID` is 'enum' (a vocabulary/term field),
     * it calls `this._getTermByCode(null, dty_ID, values)` to attempt to match term labels or codes
     * in the `values` array to their corresponding Heurist term IDs. This ensures that enum values
     * are stored as term IDs rather than raw strings if a match is found.
     *
     * The `values` array is modified in place.
     *
     * @memberof heurist.lookupBnFLibrary_bib
     * @instance
     * @override
     * @param {Array<*>} values - An array of values for the field, typically already processed into an array by `doAction`.
     * @param {number} dty_ID - The Heurist data type ID of the field.
     * @param {Object} settings - Extra settings that might have been passed (e.g., `check_term_codes`),
     *                            though `check_term_codes` is explicitly passed as `true` in the default call from `doAction`.
     * @returns {void}
     */
    prepareValue: function(values, dty_ID, settings){

        this._super(values, dty_ID, settings);

        // Match term labels with val, need to return the term's id to properly save its value
        if($Db.dty(dty_ID, 'dty_Type') == 'enum'){
            this._getTermByCode(null, dty_ID, values);
        }
    },

    /**
     * Processes an array of author values, transforming them based on the target Heurist field type.
     * This method is called by `doAction` for 'author' and 'contributor' fields.
     *
     * It iterates through the `values` array. Each `cur_val` in the array is expected to be
     * an author object from the BnF data.
     *
     * For each author object:
     * 1. `_extractAuthorValue(cur_val, true)` is called to get a structured array: `[displayValue, searchValue, role]`.
     * 2. If the extracted `value` (displayValue) is empty, an array, or an object (unexpected), it's skipped.
     * 3. Based on `field_type` (Heurist dty_Type):
     *    - If 'resource' or 'relmarker': The value at `values[idx]` is replaced with an object
     *      `{value: displayValue, search: searchValue, relation: role}`. This structure is
     *      suitable for Heurist record pointer or relationship marker fields, allowing Heurist
     *      to either link to an existing record or prompt for creation/selection using `searchValue`.
     *    - Otherwise (e.g., for text fields): The value at `values[idx]` is replaced with just `displayValue`.
     *
     * The `values` array is modified in place.
     *
     * @memberof heurist.lookupBnFLibrary_bib
     * @instance
     * @param {Array<Object>} values - An array of author objects to be processed.
     * @param {string} field_type - The Heurist dty_Type of the target field (e.g., 'resource', 'relmarker', 'text').
     * @returns {void}
     */
    getAuthorValues: function(values, field_type){

        for(const idx in values){

            const cur_val = values[idx];
            let is_object = window.hWin.HEURIST4.util.isObject(cur_val);
            
            let value = cur_val;
            let search = cur_val;
            let role = '';

            if(is_object){
                [value, search, role] = this._extractAuthorValue(cur_val, true);
            }

            if(window.hWin.HEURIST4.util.isempty(value) || Array.isArray(value) || window.hWin.HEURIST4.util.isObject(value)){
                continue;
            }

            values[idx] = field_type == 'resource' || field_type == 'relmarker' ? {value: value, search: search, relation: role} : value;
        }
    },

    /**
     * Processes an array of publisher values, transforming them based on the target Heurist field type.
     * This method is called by `doAction` for 'publisher' fields.
     *
     * It iterates through the `values` array. Each `cur_val` in the array is expected to be
     * a publisher object from the BnF data.
     *
     * For each publisher object:
     * 1. `_extractPublisherValue(cur_val, true)` is called to get `[displayValue, searchValue]`.
     * 2. If the extracted `value` (displayValue) is empty, an array, or an object, it's skipped.
     * 3. Based on `field_type` (Heurist dty_Type):
     *    - If 'resource': `values[idx]` becomes `{value: displayValue, search: searchValue}`.
     *    - If 'relmarker': `values[idx]` becomes `{value: displayValue, search: searchValue, relation: ''}` (empty relation).
     *    - Otherwise: `values[idx]` becomes `displayValue`.
     *
     * The `values` array is modified in place.
     *
     * @memberof heurist.lookupBnFLibrary_bib
     * @instance
     * @param {Array<Object>} values - An array of publisher objects to be processed.
     * @param {string} field_type - The Heurist dty_Type of the target field.
     * @returns {void}
     */
    getPublisherValues: function(values, field_type){

        for(const idx in values){

            let value = '';
            let search = '';
            const cur_val = values[idx];

            if(window.hWin.HEURIST4.util.isObject(cur_val)){
                [value, search] = this._extractPublisherValue(cur_val, true);
            }

            if(window.hWin.HEURIST4.util.isempty(value) || Array.isArray(value) || window.hWin.HEURIST4.util.isObject(value)){
                continue;
            }

            if(field_type == 'resource'){
                values[idx] = {value: value, search: search};
            }else if(field_type == 'relmarker'){
                values[idx] = {value: value, search: search, relation: ''};
            }else{
                values[idx] = value;
            }
        }
    },

    /**
     * Normalizes language string values, primarily to replace common BnF "unknown" language codes
     * with the string "unknown".
     * This method is called by `doAction` for 'language' fields.
     *
     * It iterates through the `values` array. For each language string:
     * - If the value is empty, '###', or 'und' (common BnF codes for undetermined/unknown),
     *   it's replaced with the string "unknown".
     * - Otherwise, the original value is kept.
     *
     * The `values` array is modified in place.
     *
     * @memberof heurist.lookupBnFLibrary_bib
     * @instance
     * @param {Array<string>} values - An array of language strings to be normalized.
     * @returns {void}
     */
    getLanguageValues: function(values){

        for(const idx in values){
            values[idx] = window.hWin.HEURIST4.util.isempty(values[idx])
                        || values[idx] == '###'
                        || values[idx] == 'und'
                        ? 'unknown' : values[idx];
        }
    },

    /**
     * Constructs and performs the search query for BnF bibliographic records.
     * This method overrides the parent `_doSearch` method.
     *
     * It gathers values from various input fields in the form (defined in `lookupBnFLibrary_bib.html`),
     * including general search terms ('anywhere'), title, author, and record ID.
     *
     * The core logic involves:
     * 1. Setting up base SRU parameters: `version`, `operation`, `recordSchema`, `maximumRecords`, `startRecord`.
     *    - `maximumRecords` is taken from the `#rec_limit` input or defaults to 20.
     * 2. Checking if any search fields have values. If not, a message is shown, and the function returns.
     * 3. Constructing the `query` parameter for the SRU request. This is a CQL (Contextual Query Language) string.
     *    - Each populated search field contributes a clause to the query (e.g., `bib.anywhere all "search term"`).
     *    - Clauses are combined using boolean operators (AND, OR, NOT) selected by the user via corresponding dropdowns (e.g., `#inpt_any_logic`).
     *    - The search index (e.g., `bib.anywhere`, `bib.title`, `bib.author`) and relation (e.g., `all`, `any`, `adj` selected via `#inpt_any_link`) are included.
     * 4. The constructed CQL query string is enclosed in parentheses.
     * 5. The `query` is added to the `params` object.
     * 6. Author role codes are retrieved using `_getRoleCodes()`. The first element (author codes) is passed as an additional
     *    parameter `author_codes` in the `request` object to the parent's `_doSearch` method. This can be used by the
     *    server-side proxy to further process or filter results based on author roles.
     * 7. Finally, `this._super(params, {author_codes: codes[0]})` is called to execute the search.
     *
     * Example query part: `(bib.title all "Le Petit Prince" and bib.author all "Saint-Exupéry")`
     *
     * Available search fields and their corresponding SRU indexes (prefixed with `bib.` for bibliographic):
     * - Any field: `bib.anywhere`
     * - Title: `bib.title`
     * - Author: `bib.author`
     * - Record ID: `bib.recordid`
     *
     * @memberof heurist.lookupBnFLibrary_bib
     * @instance
     * @private
     * @override
     * @returns {void}
     */
    _doSearch: function(){

        let maxRecords = this.element.find('#rec_limit').val(); // Limit number of returned records
        let params = {
            version: '1.2',
            operation: 'searchRetrieve',
            recordSchema: 'unimarcxchange', // XML structure for record details
            maximumRecords: !maxRecords || maxRecords <= 0 ? 20 : maxRecords, // Max records from API (default: 100)
            startRecord: 1 // Starting point for batch searches (API default: 1)
        };

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

        // Check which input fields have values
        let titleHasValue = this.element.find('#inpt_title').val() != '';
        let authorHasValue = this.element.find('#inpt_author').val() != '';
        let recidHasValue = this.element.find('#inpt_recordid').val() != '';

        // Build query from each input field that has a value
        // Each field has a value input, a link type selector (all, any, adj),
        // and a logic operator selector (AND, OR, NOT)

        // Any field (bib.anywhere)
        if(this.element.find('#inpt_any').val()!=''){
            last_logic = ` ${this.element.find('#inpt_any_logic').val()} `;
            query += `bib.anywhere ${this.element.find('#inpt_any_link').val()} "${this.element.find('#inpt_any').val()}"${last_logic}`;
        }

        // Work title field (bib.title)
        if(titleHasValue){
            last_logic = ` ${this.element.find('#inpt_title_logic').val()} `;
            query += `bib.title ${this.element.find('#inpt_title_link').val()} "${this.element.find('#inpt_title').val()}"${last_logic}`;
        }

        // Author field (bib.author)
        if(authorHasValue){
            last_logic = ` ${this.element.find('#inpt_author_logic').val()} `;
            query += `bib.author ${this.element.find('#inpt_author_link').val()} "${this.element.find('#inpt_author').val()}"${last_logic}`;
        }

        // Record ID field (bib.recordid) - this is typically the last field, so no logic operator follows
        if(recidHasValue){
            last_logic = ''; // No boolean operator after the last term
            query += `bib.recordid ${this.element.find('#inpt_recordid_link').val()} "${this.element.find('#inpt_recordid').val()}"`;
        }

        // Remove last trailing logic operator if it exists
        if(!window.hWin.HEURIST4.util.isempty(last_logic)){
            let regex = new RegExp(`${last_logic}$`);
            query = query.replace(regex, '');
        }

        query += ')'; // Close the CQL query parenthesis
        params['query'] = query; // Add the constructed query to SRU parameters

        const codes = this._getRoleCodes(); // Get author/contributor role codes from settings

        // Call the parent's _doSearch method with prepared SRU parameters and additional author_codes for server-side processing
        this._super(params, {author_codes: codes[0]});
    }
});