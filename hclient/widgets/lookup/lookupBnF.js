/**
 * lookupBnF.js - Base widget for BnF (Bibliothèque nationale de France) lookups.
 *
 * @fileOverview
 * This file defines the `heurist.lookupBnF` jQuery UI widget.
 * This widget specializes the `heurist.lookupBase` widget for performing
 * lookups against the Bibliothèque nationale de France (BnF) services.
 * It handles specific UI and data processing tailored to BnF authorities
 * and bibliographic records.
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
 * Widget for BnF (Bibliothèque nationale de France) lookups.
 * Inherits from `$.heurist.lookupBase`.
 *
 * This widget provides a specialized interface for searching BnF's authorities
 * (`bnfLibraryAut`) or bibliographic (`bnfLibraryBib`) services. It dynamically
 * sets its HTML content based on the service type and includes specific
 * UI controls for BnF search parameters and settings, such as record dump options.
 *
 * @widget heurist.lookupBnF
 * @augments heurist.lookupBase
 */
$.widget("heurist.lookupBnF", $.heurist.lookupBase, {

    /**
     * Options for the BnF lookup widget.
     * @memberof heurist.lookupBnF
     * @instance
     * @property {Object} options
     * @property {string} options.htmlContent - The name of the HTML file used for the dialog's content.
     *                                        This is dynamically set in `_init` based on whether the lookup
     *                                        is for BnF authorities or bibliographic records
     *                                        (e.g., 'lookupBnFLibrary_aut.html' or 'lookupBnFLibrary_bib.html').
     */
    options: {
        htmlContent: ''
    },

    /**
     * Flag indicating whether the current lookup is for BnF authorities.
     * `true` if `this.options.mapping.service` is 'bnfLibraryAut', `false` otherwise.
     * This determines which HTML content is loaded and affects UI elements.
     * @memberof heurist.lookupBnF
     * @instance
     * @private
     * @type {boolean}
     */
    _is_authorities: false,

    /**
     * Initializes the BnF lookup widget.
     * Sets `_is_authorities` based on `this.options.mapping.service`.
     * Dynamically sets `this.options.htmlContent` to either 'lookupBnFLibrary_aut.html'
     * or 'lookupBnFLibrary_bib.html' based on `_is_authorities`.
     * Calls the parent widget's `_init` method.
     *
     * @memberof heurist.lookupBnF
     * @instance
     * @private
     * @override
     * @returns {void} Calls `this._super()`.
     */
    _init: function(){

        this._is_authorities = this.options.mapping.service == 'bnfLibraryAut';

        this.options.htmlContent = this._is_authorities ? 'lookupBnFLibrary_aut.html' : 'lookupBnFLibrary_bib.html';

        return this._super();
    },

    /**
     * Initializes UI controls specific to the BnF lookup widget.
     * This method sets up a record type detail select dropdown (`#rty_flds`)
     * allowing users to choose a field for dumping raw record data.
     * It also sets up an event handler for radio buttons (`input[name="dump_field"]`)
     * that toggle the enabled state of the `#rty_flds` select based on whether
     * the "ScratchPad" option or a specific field dump is chosen.
     * Calls the parent widget's `_initControls` method.
     *
     * @memberof heurist.lookupBnF
     * @instance
     * @private
     * @override
     * @returns {void} Calls `this._super()`.
     */
    _initControls: function(){

        let $select = this.element.find('#rty_flds');
        let top_opt = [{key: '', title: 'select a field...', disabled: true, selected: true, hidden: true}];
        let sel_options = {
            useHtmlSelect: false
        };
        window.hWin.HEURIST4.ui.createRectypeDetailSelect($select[0], this.options.mapping.rty_ID, ['blocktext'], top_opt, sel_options);

        this._on(this.element.find('input[name="dump_field"]'), {
            change: function(){
                let opt = this.element.find('input[name="dump_field"]:checked').val();
                window.hWin.HEURIST4.util.setDisabled(this.element.find('#rty_flds'), opt == 'rec_ScratchPad');
            }
        });

        return this._super();
    },

    /**
     * Sets up the "Additional Settings" tab for the BnF lookup.
     * This method populates settings related to how raw record data from BnF
     * can be dumped into the Heurist record (e.g., into the ScratchPad or a specific text field).
     * It reads existing settings from `this.options.mapping.options` or uses
     * `def_options` if no settings are found (in which case, it triggers a save).
     *
     * Specifically, it configures:
     * - Radio buttons for choosing whether to dump the record (`input[name="dump_record"]`).
     * - Radio buttons for choosing the dump target: ScratchPad or a specific field (`input[name="dump_field"]`).
     * - A select dropdown (`#rty_flds`) for choosing the target field if not ScratchPad.
     * - A textarea for author codes (`#author-codes`) if present in the UI.
     *
     * If initial settings were empty and `def_options` were used, it calls `_saveExtraSettings`
     * to persist these defaults.
     *
     * @memberof heurist.lookupBnF
     * @instance
     * @private
     * @override
     * @param {Object} def_options - Default options to use if no specific options are found in `this.options.mapping.options`.
     *                               This typically includes default values for `dump_record`, `dump_field`, and `author_codes`.
     * @returns {void}
     */
    _setupSettings: function(def_options){

        let options = this.options.mapping?.options;
        let need_save = false;

        if(window.hWin.HEURIST4.util.isempty(options)){
            options = def_options;
            need_save = true;
        }

        if(!window.hWin.HEURIST4.util.isempty(options['dump_record'])){
            this.element.find('input[name="dump_record"]').prop('checked', options['dump_field']);
        }

        if(this.element.find('#author-codes').length > 0 && !window.hWin.HEURIST4.util.isempty(options['author_codes'])){
            this.element.find('#author-codes').text(options['author_codes']);
        }

        if(!window.hWin.HEURIST4.util.isempty(options['dump_field'])){
            const selected = options['dump_field'];

            if(selected === 'rec_ScratchPad'){
                this.element.find('input[name="dump_field"][value="rec_ScratchPad"]').prop('checked', true);
            }else{
                this.element.find('input[name="dump_field"][value="dty_ID"]').prop('checked', true);
                this.element.find('#rty_flds').val(selected);

                if(this.element.find('#rty_flds').hSelect('instance') !== undefined){
                    this.element.find('#rty_flds').hSelect('refresh');
                }
            }

            window.hWin.HEURIST4.util.setDisabled(this.element.find('#rty_flds'), selected == 'rec_ScratchPad');
        }

        if(need_save){
            this._saveExtraSettings();
        }
    },

    /**
     * Processes the search results received from the BnF service and prepares them
     * for display in the `resultList` widget.
     * This method overrides the parent `_onSearchResult`.
     *
     * It performs the following steps:
     * 1. Retrieves the maximum number of records to display from an input field (`#rec_limit`).
     * 2. Parses the input `json_data` (expected to be a JSON string from the BnF service).
     * 3. If `json_data.result` is not present, it calls the parent's `_onSearchResult(false)` to indicate an error or no data.
     * 4. Defines the fields for the `HRecordSet`: 'rec_ID', 'rec_RecTypeID', all mapped fields from `this.options.mapping.fields`, and 'BnF_ID'.
     * 5. Iterates through `json_data.result` (the array of BnF records):
     *    a. Assigns a sequential `recID`.
     *    b. Creates an array of values for the current record, corresponding to the defined fields.
     *    c. Adds the `recID` to `res_orders` and the values array to `res_records`.
     * 6. Calls `this.checkResultSize` to potentially warn the user if the total number of records exceeds `maxRecords`.
     * 7. Constructs the final data object for `_super()` in the format `{fields, order, records}` or `false` if no results.
     * 8. Calls `this._super(res)` to pass the processed data to the base lookup widget for display.
     *
     * @memberof heurist.lookupBnF
     * @instance
     * @private
     * @override
     * @param {string} json_data - The JSON string response from the BnF lookup service.
     *                           It's expected to have a `result` property containing an array of records
     *                           and a `numberOfRecords` property.
     * @returns {void}
     */
    _onSearchResult: function(json_data){

        let maxRecords = this.element.find('#rec_limit').val(); // limit number of returned records
        maxRecords = (!maxRecords || maxRecords <= 0) ? 20 : maxRecords;

        json_data = window.hWin.HEURIST4.util.isJSON(json_data);

        if(!json_data?.result){
            this._super(false);
        }

        let res_records = {}, res_orders = [];

        // Prepare fields for mapping
        // the fields used here are defined within /heurist/hserv/controller/LookupConfigs.json where "service" = bnfLibrary
        let fields = ['rec_ID', 'rec_RecTypeID']; // added for record set
        let map_flds = Object.keys(this.options.mapping.fields);
        fields = fields.concat(map_flds, 'BnF_ID');

        // Parse json to Record Set
        let i = 1;
        for(const record of json_data.result){

            let recID = i++;
            let values = [recID, this.options.mapping.rty_ID];

            // Add current record details, field by field
            for(const fld_Name of map_flds){
                values.push(record[fld_Name]);
            }

            values.push(record['BnF_ID']);

            res_orders.push(recID);
            res_records[recID] = values;
        }

        this.checkResultSize(json_data.numberOfRecords, maxRecords);

        let res = res_orders.length > 0 ? {fields: fields, order: res_orders, records: res_records} : false;
        this._super(res);
    }
});