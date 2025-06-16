/**
 * @file lookupConfig.js
 * 
 * @fileOverview
 * This file defines the `heurist.lookupConfig` jQuery UI widget.
 * This widget provides an interface for configuring external record lookup services
 * within the Heurist system. It allows users to map these services to specific
 * Heurist record types and define how data fields from the external service
 * correspond to Heurist record fields.
 *
 * The configuration data is ultimately stored in the `sys_ExternalReferenceLookups`
 * field of the `sysIdentification` record (sys_ID 1). The initial available services
 * and their definitions are typically loaded from a server-side configuration file
 * (e.g., historically `hserv/controller/record_lookup_config.json`, now more likely
 * managed via `HAPI4.sysinfo['services_list']` and `HAPI4.sysinfo['service_config']`).
 *
 * @package     Heurist academic knowledge management system
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Brandon McKay   <blmckay13@gmail.com> (Modifications and updates)
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @version     6.0
 */


/* global stringifyMultiWKT */ 

/**
 * Widget for configuring external lookup services.
 * Inherits from `$.heurist.baseConfig`.
 *
 * This widget allows administrators to:
 * - View available external lookup services.
 * - Assign these services to Heurist record types.
 * - Define a user-friendly label for each configured service instance.
 * - Map fields from the external service to fields within the chosen Heurist record type.
 * - Manage and save these configurations.
 * - Test service connectivity and view example results.
 *
 * @widget heurist.lookupConfig
 * @extends heurist.baseConfig
 */
$.widget("heurist.lookupConfig", $.heurist.baseConfig, {

    /**
     * Default options for the lookupConfig widget.
     * @memberof heurist.lookupConfig
     * @instance
     * @property {Object} options
     * @property {string} [options.title='Lookup services configuration'] - The title displayed for the configuration dialog/panel.
     * @property {string} [options.htmlContent='lookupConfig.html'] - The name of the HTML file that provides the UI structure for this widget.
     * @property {?string} [options.helpContent=null] - Path to a help file or content related to this configuration interface.
     * @property {string} [options.type='service'] - A type identifier, likely used by the parent `baseConfig` for context.
     * @property {Object} [options.service_config={}] - Holds the current service configurations loaded from `HAPI4.sysinfo['service_config']`.
     *                                                This is populated in `_init`.
     */
    options: {
        title: 'Lookup services configuration',
        htmlContent: 'lookupConfig.html',
        helpContent: null,

        type: 'service'
    },

    /**
     * Stores example lookup URLs and service URLs for various external services.
     * Used for providing direct links and for testing service availability.
     * Keys are service identifiers (e.g., 'tlcmap', 'geoName', 'bnfLibrary').
     * Each value is an object with `lookup` (API endpoint for test query) and
     * `service` (main service page URL) properties.
     * @memberof heurist.lookupConfig
     * @instance
     * @private
     * @type {?Object<string, {lookup: string|Object, service: string}>}
     */
    _urls: null,

    /**
     * Stores the response status from checking the availability/allowance of ESTC
     * (English Short Title Catalogue) related lookup services.
     * ESTC lookups might have special conditions or backend dependencies.
     * Properties `ESTC`, `ESTC_works`, `ESTC_editions` hold response objects from HAPI calls.
     * @memberof heurist.lookupConfig
     * @instance
     * @private
     * @type {{ESTC: ?Object, ESTC_works: ?Object, ESTC_editions: ?Object}}
     */
    _estc_response: {
        ESTC: null,
        ESTC_works: null,
        ESTC_editions: null
    },

    /**
     * Stores the list of available lookup service definitions.
     * This is typically populated from `window.hWin.HAPI4.sysinfo['services_list']`
     * which itself is derived from a server-side JSON configuration file defining
     * available external services, their query parameters, expected fields, etc.
     * @memberof heurist.lookupConfig
     * @instance
     * @private
     * @type {?Array<Object>}
     */
    _available_services: null,

    /**
     * Initializes the widget.
     * - Sets up the `_urls` object with predefined URLs for various lookup services.
     * - Retrieves the list of available services from `window.hWin.HAPI4.sysinfo['services_list']`.
     *   If no services are found, an error message is displayed.
     * - Retrieves the current service configurations from `window.hWin.HAPI4.sysinfo['service_config']`
     *   (passed via `this.options.service_config` by `baseConfig`) and ensures it's a valid object.
     * - Calls the parent widget's `_init` method.
     *
     * @memberof heurist.lookupConfig
     * @instance
     * @private
     * @override
     * @returns {void|*} The result of `this._super()` or nothing if initialization fails early.
     */
    _init: function() {

        this._urls = {
            tlcmap: {
                lookup: 'https://tlcmap.org/ghap/search?format=csv&paging=10&fuzzyname=London',
                service: 'https://ghap.tlcmap.org/places?containsname=London&searchausgaz=on&searchncg=on&searchpublicdatasets=on'
            },
            geoName: {
                lookup: `http://api.geonames.org/searchJSON?maxRows=10&q=London`,
                service: 'https://www.geonames.org/search.html?q=London&country='
            },
            postalCodeSearch: {
                lookup: `http://api.geonames.org/postalCodeLookupJSON?maxRows=10&placename=London`,
                service: 'https://www.geonames.org/postalcode-search.html?q=London&country='
            },
            bnfLibrary: {
                lookup: `https://catalogue.bnf.fr/api/SRU?version=1.2&operation=searchRetrieve&maximumRecords=10&startRecord=1&query=${encodeURIComponent('(bib.anywhere any "Vincent")')}`,
                service: 'https://catalogue.bnf.fr/rechercher.do?motRecherche=Vincent&critereRecherche=0&depart=0&facetteModifiee=ok'
            },
            bnfLibraryAut: {
                lookup: `https://catalogue.bnf.fr/api/SRU?version=1.2&operation=searchRetrieve&recordSchema=unimarcxchange&maximumRecords=10&startRecord=1&query=${encodeURIComponent('(aut.anywhere any "Vincent")')}`,
                service: 'https://catalogue.bnf.fr/resultats-auteur.do?nomAuteur=Vincent&filtre=1&pageRech=rau'
            },
            nomisma: {
                lookup: {
                    getMints: 'https://nomisma.org/apis/getMints?id=denarius',
                    getHoards: 'https://nomisma.org/apis/getHoards?id=denarius',
                    getFindspots: 'https://nomisma.org/apis/getFindspots?id=denarius'
                },
                service: 'https://nomisma.org/browse?q=denarius'
            },
            nakala: {
                lookup: 'https://api.nakala.fr/search?q=Literature&fq=scope%3Ddatas&order=relevance&page=1&size=15',
                service: 'https://nakala.fr/search/?q=Literature'
            },
            nakala_author: {
                lookup: 'https://api.nakala.fr/authors/search?q=John&order=asc&page=1&limit=15',
                service: 'https://nakala.fr/'
            },
            opentheso: {
                lookup: 'https://pactols.frantiq.fr/opentheso/openapi/v1/concept/th17/search?q=Fid',
                service: 'https://pactols.frantiq.fr/index.xhtml'
            }
        };
        
        this._available_services = window.hWin.HAPI4.sysinfo['services_list'];
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(this._available_services)){
            window.hWin.HEURIST4.msg.showMsgErr({
                message: 'There are no available services, or the configuration file was not found or is broken',
                error_title: 'No services',
                status: window.hWin.ResponseStatus.ACTION_BLOCKED
            });
            return;
        }

        this.options.service_config = window.hWin.HEURIST4.util.isJSON(this.options.service_config);
        if(!this.options.service_config){ // Invalid value / None
            this.options.service_config = {};    
        }

        return this._super();
    },

    /**
     * Initializes the UI controls for the lookup configuration widget.
     * This method is called after the HTML content (`lookupConfig.html`) is loaded.
     *
     * Key actions:
     * - Calls `updateOldConfigurations` to ensure existing service configurations are compatible with current definitions.
     * - Initializes the record type selector dropdown (`#sel_rectype`) using `HEURIST4.ui.createRectypeSelectNew`.
     * - Sets up an event handler for changes to the record type selector (`_onRectypeChange`).
     * - Sets up an event handler for input changes on the label field (`#inpt_label`) to update status (`_updateStatus`).
     * - Initializes "Add Service" button (`#btnAddService`) and its click handler (`_addNewService`).
     * - Initializes "Apply Configuration" button (`#btnApplyCfg`) and its click handler (`_applyConfig`).
     * - Initializes "Discard Changes" button (`#btnDiscard`) (initially hidden) and its click handler (`_removeConfig`).
     * - Sets up click handlers for example record navigation buttons (`#example_records .ui-icon`) to cycle through test results.
     * - Makes HAPI calls via `HAPI4.SystemMgr.check_allow_estc` to check the availability of ESTC-related services
     *   and stores the responses in `this._estc_response`.
     * - Calls the parent widget's `_initControls` method.
     *
     * @memberof heurist.lookupConfig
     * @instance
     * @private
     * @override
     * @returns {void|*} The result of `this._super()`.
     */
    _initControls:function(){

        let that = this;

        // check that all assigned services contain valid details
        this.updateOldConfigurations();

        //fill record type selector
        this.selectRecordType = this._$('#sel_rectype').css({'list-style-type': 'none'});
        this.selectRecordType = window.hWin.HEURIST4.ui.createRectypeSelectNew(this.selectRecordType.get(0),
            {topOptions:'select record type'});

        // on change handler
        this._on(this.selectRecordType, { change: this._onRectypeChange });

        let ele = this._$('#inpt_label');
        this._on(ele, {input: this._updateStatus });

        ele = this._$('#btnAddService').button({ icon: "ui-icon-plus" }).css('left', '165px');
        this._on(ele, {click: this._addNewService});

        this.btnApply = this._$('#btnApplyCfg').button().css("margin-right", "10px");
        this._on(this.btnApply, {click: this._applyConfig});            

        this.btnDiscard = this._$('#btnDiscard').button().hide();
        this._on(this.btnDiscard, {click: function(){this._removeConfig(null)}});

        this._on(this._$('#example_records .ui-icon'), {
            'click': function(event){

                let idx = that._$('#tbl_matches').attr('data-idx');
                let service = that.selectServiceType.val();
                let max = 0;

                if(Array.isArray(that.example_results[service])){
                    max = that.example_results[service].length - 1;
                }else if(window.hWin.HEURIST4.util.isPlainObject(that.example_results[service])){
                    max = Object.keys(that.example_results[service]).length - 1;
                }

                if($(event.target).hasClass('ui-icon-arrowthick-1-e')){
                    idx = idx == max ? 0 : parseInt(idx) + 1;
                }else{
                    idx = idx == 0 ? max : parseInt(idx) - 1;
                }

                that._$('#current_idx').text(parseInt(idx)+1);
                that._$('#tbl_matches').attr('data-idx', idx);

                that._displayTestResults(service);
            }
        });

        let req = {
            a: 'check_allow_estc',
            db: window.hWin.HAPI4.database,
            ver: 'ESTC'
        };
        window.hWin.HAPI4.SystemMgr.check_allow_estc(req, function(response){
            that._estc_response.ESTC = response;
        });
        req['ver'] = 'ESTC_works';
        window.hWin.HAPI4.SystemMgr.check_allow_estc(req, function(response){
            that._estc_response.ESTC_works = response;
            that._estc_response.ESTC_editions = response;
        });

        return this._super();
    },

    /**
     * Iterates through existing service configurations and updates them to ensure
     * compatibility with current service definitions. This handles potential changes
     * in service definitions over time (e.g., field additions/removals, option changes).
     *
     * For each configured service, it performs:
     * 1. Verification (`_verifyService`): Checks if the service and its associated record type still exist.
     *    If not, the configuration is removed.
     * 2. Information Update (`_updateServiceInfo`): Corrects legacy service IDs or dialog script names.
     * 3. Field Update (`_updateServiceFields`): Adds newly defined fields (as unmapped) and removes fields
     *    that no longer exist in the service definition.
     * 4. Options Update (`_updateServiceOptions`): Adds default options for services like BnF if they are missing.
     *
     * If any changes are made during this process, `saveConfigrations` is called to persist them.
     *
     * @memberof heurist.lookupConfig
     * @instance
     * @returns {void}
     */
    updateOldConfigurations: function(){

        let that = this;
        let has_changes = false;

        for(let key of Object.keys(this.options.service_config)){

            let service_key = key;

            // Verify lookup is still basically valid (record type and service still exists)
            if(!that._verifyService(service_key)){
                delete that.options.service_config[service_key];
                has_changes = true;
                continue;
            }
    
            // Update basic details
            let info_changes = false;
            [info_changes, service_key] = that._updateServiceInfo(service_key);
    
            // Update fields (add and/or remove)
            let field_changes = that._updateServiceFields(service_key);
    
            // Update additional settings/options
            let settings_changes = that._updateServiceOptions(service_key);
    
            has_changes = info_changes || field_changes || settings_changes || has_changes;
        }

        // Update with new changes
        if(has_changes){
            this.saveConfigrations();            
        }
    },

    /**
     * Saves the current state of all lookup service configurations to the database.
     * The configurations (stored in `this.options.service_config`) are stringified
     * and saved into the `sys_ExternalReferenceLookups` field of the `sysIdentification`
     * record (sys_ID 1) via a HAPI `EntityMgr.doRequest` call.
     *
     * On successful save:
     * - Updates the local copy in `window.hWin.HAPI4.sysinfo['service_config']`.
     * - Resets modification flags (`_is_modified`, `_services_modified`).
     * - Disables the main save button for the configuration panel.
     * - Shows a success flash message.
     * On failure, an error message is displayed.
     *
     * Note: The method name "saveConfigrations" contains a typo ("Configrations" instead of "Configurations").
     * This JSDoc reflects the actual method name in the code.
     *
     * @memberof heurist.lookupConfig
     * @instance
     * @returns {void}
     */
    saveConfigrations: function(){

        let that = this;

        let fields = {
            'sys_ID': 1,
            'sys_ExternalReferenceLookups': JSON.stringify(this.options.service_config)
        };

        // Update sysIdentification record
        let request = {
            'a': 'save',
            'entity': 'sysIdentification',
            'request_id': window.hWin.HEURIST4.util.random(),
            'isfull': 0,
            'fields': fields
        };

        window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){

            if(response.status == window.hWin.ResponseStatus.OK){
                window.hWin.HAPI4.sysinfo['service_config'] = window.hWin.HEURIST4.util.cloneJSON(that.options.service_config); // update local copy

                that._is_modified = false;
                that._services_modified = false;

                window.hWin.HEURIST4.util.setDisabled(that.save_btn, !that._services_modified);
                window.hWin.HEURIST4.msg.showMsgFlash('Saved lookup configurations...', 3000);
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    },

    /**
     * Fills the configuration form in the right panel with the details of a selected service configuration.
     *
     * If `service_id` is provided and a configuration exists for it, `cfg0` is loaded from `this.options.service_config[service_id]`.
     *
     * If `cfg0` (a configuration object) is provided or loaded:
     * - Sets `this._current_cfg` to `cfg0`.
     * - Populates service name, description, and label input fields.
     * - Clears and then populates the field mapping table (`#tbl_matches`):
     *   For each field in `cfg0.fields`, a row is created with the external field name and a dropdown for Heurist field selection.
     * - If no fields are defined for the service, a "no fields" message is shown.
     * - Sets the selected service in the service type dropdown (`this.selectServiceType`).
     * - Sets the selected record type in the record type dropdown (`this.selectRecordType`) using the local rty_ID.
     * - Calls `_onRectypeChange` to update field mapping dropdowns based on the selected record type.
     *
     * If `cfg0` is not provided (e.g., when deselecting or creating a new service):
     * - Clears service type, record type, label, and service name fields.
     * - If `service_id` is 'new', sets `this._isNewCfg = true` and `this._current_cfg = {}`.
     * - Otherwise, sets `this._current_cfg = null`.
     *
     * Finally, calls `_updateStatus` to refresh UI states.
     *
     * @memberof heurist.lookupConfig
     * @instance
     * @private
     * @param {?string} service_id - The ID of the service configuration to load (e.g., "bnfLibrary_123").
     *                               If 'new', prepares for a new configuration.
     *                               If null/undefined and `cfg0` is also null, clears the form.
     * @param {?Object} cfg0 - An optional configuration object to load directly.
     *                         If provided, it overrides loading via `service_id`.
     * @returns {void}
     */
    _fillConfigForm: function( service_id, cfg0 ){
        
        if(service_id && this.options.service_config[service_id]){
            cfg0 = this.options.service_config[service_id];
        }

        if( cfg0 ){ // If a configuration object is available

            this._current_cfg = cfg0; // Set as current configuration
            
            // Populate UI fields with configuration data
            this._$('#service_name').html(cfg0.label);
            this._$('#service_description').html(`<strong>${cfg0.service}</strong>: ${cfg0.description}`);
            this._$('#inpt_label').val(cfg0.label);
            
            let tbl = this._$('#tbl_matches'); // Field mapping table
            tbl.empty(); // Clear existing rows

            // Create rows for each field defined in the service configuration
            for(const field in this._current_cfg.fields){
                $(`<tr><td>${field}</td><td><select data-field="${field}"></select></td><td class="lookup_data" data-field="${field}"></td></tr>`).appendTo(tbl);
            }

            // Show message if no fields are available for mapping
            if(this._current_cfg.fields.length == 0){
                this._$(`#no_fields_msg, #no_fields_msg .${this._current_cfg.service}`).show();
            }

            // Get local ID for the record type
            let rty_ID = this._current_cfg.rty_ID > 0 ? $Db.getLocalID('rty',this._current_cfg.rty_ID) : '';
            
            // Set selected service and record type in dropdowns
            if(cfg0.service) {
                this.selectServiceType.val(cfg0.service);
            }
            this.selectRecordType.val( rty_ID );
            this._onRectypeChange(); // Update field mapping dropdowns
        }else{ // If no configuration object (e.g., new or deselected)
            
            // Clear selection in dropdowns and input fields
            this.selectServiceType.val('');
            this.selectRecordType.val('');
            this._$('#inpt_label').val('');
            this._$('#service_name').html('');
            
            if(service_id=='new'){ // If creating a new configuration
                this._isNewCfg = true;
                this._current_cfg = {}; // Initialize empty current config
            }else{ // If deselected or invalid
                this._current_cfg = null;
            }
        }
        
        this._updateStatus(); // Refresh UI element states
    },
    
    /**
     * Updates the UI status, including button states and visibility of form sections,
     * based on the current configuration state (`this._current_cfg`) and modification status.
     *
     * Key actions:
     * - If `this._current_cfg` is null (no service selected/loaded):
     *   - Displays a prompt to select or add a service.
     *   - Hides the main service configuration form (`#service_config`).
     * - If `this._current_cfg` exists:
     *   - Shows the service configuration form.
     *   - Calls `_checkModification` to determine if changes have been made.
     *   - Shows/hides service details and mapping sections based on whether a service type and record type are selected.
     * - Refreshes service type and record type dropdowns using `selectMenuRefresh`.
     * - Shows the "Discard" button.
     * - Enables/disables the "Apply" button (`this.btnApply`) based on `this._is_modified`.
     * - Adds/removes 'ui-button-action' class to "Apply" button based on modification status.
     *
     * @memberof heurist.lookupConfig
     * @instance
     * @private
     * @returns {void}
     */
    _updateStatus: function(){
        
        this._is_modified = false; // Reset modification flag initially

        if(this._current_cfg==null){ // No service configuration selected/loaded
            
            this._$('#service_name').html('<span class="ui-icon ui-icon-arrowthick-1-w"></span>Select a service to edit or click the assign button');
            this._$('#service_config').hide(); // Hide main config form
            
        }else{ // A service configuration is selected/loaded or being created
            this._$('#service_config').show(); // Show main config form

            this._checkModification(); // Check if current form values differ from stored config

            // Show/hide service details based on whether a service is selected
            if(!$.isEmptyObject(this._current_cfg) || this.selectServiceType.val()){
                this._$('.service_details').show();
            }else{
                this._$('.service_details').hide();
                this._$('#example_records').hide(); // Hide example records if no service
            }

            // Show/hide field mapping section based on whether a record type is selected
            if(this.selectRecordType.val()){
                this._$('#service_mapping').show();
                this.btnApply.show();
            }else{
                this._$('#service_mapping').hide();
                this.btnApply.hide();
                this._$('#example_records').hide(); // Hide example records if no record type
            }
        }

        // Refresh custom select menus
        this.selectMenuRefresh(this.selectServiceType);
        this.selectMenuRefresh(this.selectRecordType);

        this.btnDiscard.show(); // Always show discard button when a config attempt is active

        // Enable/disable Apply button based on modification status
        window.hWin.HEURIST4.util.setDisabled(this.btnApply, !this._is_modified);

        if(this._is_modified){
            this.btnApply.addClass('ui-button-action'); // Highlight if modified
        }else{
            this.btnApply.removeClass('ui-button-action');
        }
    },

    /**
     * Checks if the current form values have been modified compared to the loaded configuration (`this._current_cfg`).
     * Sets `this._is_modified` flag accordingly.
     *
     * If `this._current_cfg` is empty or `this._isNewCfg` is true (new configuration):
     * - Shows the "Assign Service" fieldset (`#assign_fieldset`).
     * - Sets `this._is_modified = true`.
     *
     * If an existing configuration is loaded:
     * - Hides the "Assign Service" fieldset.
     * - Shows service details.
     * - Compares current record type and label with stored values.
     * - If not modified by these, calls parent's `_checkModification` (from `baseConfig`)
     *   which might check other common config fields or field mappings.
     *
     * @memberof heurist.lookupConfig
     * @instance
     * @private
     * @returns {void}
     */
    _checkModification: function(){

        if($.isEmptyObject(this._current_cfg) || this._isNewCfg){ // New configuration scenario
            this._$('#assign_fieldset').show(); // Show service selection controls
            this._is_modified = true; // Mark as modified by default for new configs
        }else{ // Existing configuration loaded
            this._$('#assign_fieldset').hide();  // Hide service selection controls
            this._$('.service_details').show(); // Ensure service details are visible

            // Check if basic properties (record type, label) have changed
            this._is_modified = (this._current_cfg.rty_ID != this.selectRecordType.val())
                             || (this._current_cfg.label != this._$('#inpt_label').val());
    
            if(!this._is_modified){ // If not modified by basic properties, check further (e.g., field mappings)
                this._super(); // Calls baseConfig._checkModification or similar
            }
        }
    },

    /**
     * Handles the change of the selected service type from the dropdown (`this.selectServiceType`).
     * It populates the configuration form based on the definition of the newly selected service.
     *
     * Key actions:
     * - Checks if the selected `service_name` is an ESTC service and if it's allowed/available
     *   (using `this._estc_response`). If not allowed, shows an error and returns `false`.
     * - Retrieves the base definition for the `service_name` using `getServiceDefInfo`.
     * - Updates the display of example service URLs (`#a_lookup_url`, `#a_service_url`) based on `this._urls`.
     *   - For 'geoName' and 'postalCodeSearch', the lookup URL is marked as unavailable for direct linking due to security.
     * - Calls `_fillConfigForm(null, cfg0)` to populate the right panel with the structure of the selected service type,
     *   passing `null` for `service_id` (as this is a type change, not loading an existing instance)
     *   and `cfg0` (the base definition of the service).
     *
     * @memberof heurist.lookupConfig
     * @instance
     * @private
     * @param {string} service_name - The identifier of the newly selected service type (e.g., "bnfLibrary", "tlcmap").
     * @returns {boolean|void} `false` if an ESTC service is selected but not available, otherwise nothing.
     */
    _changeService: function( service_name ){

        // Check ESTC service availability
        if((service_name == 'ESTC_editions' || service_name == 'ESTC_works' || service_name == 'ESTC')
         && this._estc_response[service_name].status != window.hWin.ResponseStatus.OK){

            window.hWin.HEURIST4.msg.showMsgErr(this._estc_response[service_name]);
            return false;
        }

        let cfg0 = this.getServiceDefInfo(service_name, false);

        if(this._urls[service_name]){

            let lookup_url = this._urls[service_name].lookup;
            let lookup_label = this._urls[service_name].lookup;
            this._off($('#a_lookup_url'), 'click');
            if(service_name != 'geoName' && service_name != 'postalCodeSearch'){

                this._on($('#a_lookup_url'), {
                    click: function(){

                        let url = this._urls[service_name].lookup;

                        if($.isPlainObject(url)){
                            for(let type in url) {
                                window.open(url[type], '_blank');
                            }
                        }else{
                            window.open(url, '_blank');
                        }
                    }
                });
            }else{

                this._on($('#a_lookup_url'), {
                    click: function(){
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: 'Due to security reasons this url cannot be provided.',
                            error_title: 'Cannot provide URL',
                            status: window.hWin.ResponseStatus.ACTION_BLOCKED
                        });
                        return false;
                    }
                });

                lookup_label = 'Unavailable';
                lookup_url = '#';
            }

            $('#a_service_url').html(this._urls[service_name].service).attr('href', this._urls[service_name].service);
            $('#a_lookup_url').html(lookup_label).attr('href', lookup_url);

            this._$('.service_urls').show();
        }else{
            this._$('.service_urls').hide();
        }
        
        this._fillConfigForm(null, cfg0);
    },

    /**
     * Displays example results from a selected lookup service in the UI.
     * This method is called when navigating example records or after a service type is selected.
     *
     * Key actions:
     * - Hides the example records section (`#example_records`) initially.
     * - Checks if the `service_name` is one of the handled services and if a record type is selected. If not, returns.
     * - If example results for the `service_name` are not already cached in `this.example_results`:
     *   - Retrieves the test lookup URL from `this._urls`.
     *   - Handles special cases for service types (e.g., 'bnfLibrary' -> 'bnflibrary_bib', 'nomisma' triggers `_runTestNomisma`).
     *   - Makes an API call via `HAPI4.RecordMgr.lookup_external_service` to fetch example data.
     *   - Caches the response in `this.example_results[service_name]`.
     *   - Recursively calls `_displayTestResults` to render the fetched data.
     * - If data is cached or fetched:
     *   - Gets the current example index from `$('#tbl_matches').attr('data-idx')`.
     *   - Retrieves the specific example record from `this.example_results[service_name]`.
     *   - Iterates through the field mapping table cells (`.lookup_data`) and populates them
     *     with corresponding data from the example record.
     *     - Handles nested data fields (e.g., `object.property`).
     *     - Special formatting for 'bnfLibrary' author and publisher fields.
     *     - Special formatting for 'tlcmap' and 'nomisma' geometry fields (converts to WKT).
     *   - Updates UI elements for Nomisma type display or other service-specific fluff.
     *   - Shows the example records section.
     *
     * @memberof heurist.lookupConfig
     * @instance
     * @private
     * @param {string} service_name - The identifier of the service for which to display example results.
     * @returns {void}
     */
    _displayTestResults: function(service_name){

        let that = this;

        const handled_services = ['bnfLibrary', 'bnfLibraryAut', 'tlcmap', 'geoName', 'postalCodeSearch', 'nomisma', 'nakala', 'nakala_author', 'opentheso'];

        this._$('#example_records').hide();

        if(handled_services.indexOf(service_name) == -1 || window.hWin.HEURIST4.util.isempty(this.selectRecordType.val())){
            return;
        }

        if(!this.example_results[service_name]){
            // Retrieve data
            let url = this._urls[service_name].lookup;

            let serviceType = service_name;
            let request = {};
            switch (service_name) {
                case 'bnfLibrary':
                    serviceType = 'bnflibrary_bib';
                    break;
                case 'bnfLibraryAut':
                    serviceType = 'bnflibrary_aut';
                    break;
                case 'nomisma':
                    this._runTestNomisma('getMints'); // run all nomisma services
                    url = '';
                    break;
                case 'geoName':
                case 'postalCodeSearch':
                    serviceType = 'geonames';
                    break;
                default:
                    break;
            }

            if(url == ''){
                return;
            }

            request = {
                service: url, // request url
                serviceType: serviceType // requesting service, otherwise no
            };

            window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

                if(response.status && response.status != window.hWin.ResponseStatus.OK){
                    return;
                }

                if(service_name.indexOf('bnfLibrary') != -1){
                    response = response.result;
                }else if(service_name == 'geoName'){
                    response = response.geonames;
                }else if(service_name == 'postalCodeSearch'){
                    response = response.postalcodes;
                }else if(service_name == 'nakala'){
                    response = response.records;
                }

                that.example_results[service_name] = response;

                that._displayTestResults(service_name);
            });

            return;
        }

        // Display data
        let $tbl_cells = this._$('.lookup_data');

        let idx = this._$('#tbl_matches').attr('data-idx');
        let data = this.example_results[service_name] ? this.example_results[service_name][idx] : null;

        if(service_name == 'nakala'){
            let rec_IDs = Object.keys(this.example_results[service_name]);
            idx = rec_IDs[idx];
            data = this.example_results[service_name][idx];
        }

        if(!data){
            return;
        }

        $.each($tbl_cells, function(idx, cell){
            let $cell = $(cell);
            let field = $cell.attr('data-field');
            let value = null;

            if(!field){
                return;
            }

            if(field.indexOf('.') != -1){

                let fld_parts = field.split('.');
                value = data[fld_parts[0]];

                if(window.hWin.HEURIST4.util.isempty(value)){
                    return;
                }

                for(let i = 1; i < fld_parts.length; i++){

                    if(window.hWin.HEURIST4.util.isempty(value[fld_parts[i]]) && !window.hWin.HEURIST4.util.isempty(value[0])){
                        value = value[0];
                    }

                    value = value[fld_parts[i]];

                    if(window.hWin.HEURIST4.util.isempty(value)){
                        break;
                    }
                }

            }else{
                value = data[field];
            }

            if(!value){
                $cell.html('');
                return;
            }

            if(service_name == 'bnfLibrary'){

                if(field == 'author'){

                    let creator_val = '';
    
                    for(let idx in value){
    
                        let cur_string = '';
                        let cur_obj = value[idx];
    
                        if($.isPlainObject(cur_obj)){
                            if(Object.hasOwn(cur_obj,'firstname') && cur_obj['firstname'] != ''){
                                cur_string = cur_obj['firstname'];
                            }
                            if(Object.hasOwn(cur_obj,'surname') && cur_obj['surname'] != ''){
                                cur_string = (cur_string != '') ? `${cur_obj['surname']}, ${cur_string}` : cur_obj['surname'];
                            }
                            if(Object.hasOwn(cur_obj,'active') && cur_obj['active'] != ''){
                                cur_string += ` (${cur_obj['active']})`;
                            }
    
                            if(cur_string == ''){
                                Object.values(cur_obj);
                            }
                        }else{
                            cur_string = cur_obj;
                        }
    
                        if(!cur_string || Array.isArray(cur_string) || $.isPlainObject(cur_string)){
                            creator_val += 'Missing author; ';
                        }else{
                            creator_val += `${cur_string}; `;
                        }
                    }
    
                    value = creator_val;
                }else if(field == 'publisher'){

                    let pub_val = '';
    
                    for(let idx in value){
    
                        let cur_string = '';
                        let cur_obj = value[idx];
    
                        if($.isPlainObject(cur_obj)){
                            if(Object.hasOwn(cur_obj,'name') && cur_obj['name'] != ''){
                                cur_string = cur_obj['name'];
                            }
                            if(Object.hasOwn(cur_obj,'location') && cur_obj['location'] != '' && cur_string == ''){
                                cur_string = cur_obj['location'];
                            }
    
                            if(cur_string == ''){
                                Object.values(cur_obj);
                            }
                        }else{
                            cur_string = cur_obj;
                        }
    
                        if(!cur_string || Array.isArray(cur_string) || $.isPlainObject(cur_string)){
                            pub_val += 'Missing publisher; ';
                        }else{
                            pub_val += `${cur_string}; `;
                        }
                    }
    
                    value = pub_val;
                }
            }else if(service_name == 'tlcmap' || service_name == 'nomisma'){

                if(field == 'geometry'){

                    value = {"type": "Feature", "geometry": value};
                    let wkt = stringifyMultiWKT(value);    
                    if(window.hWin.HEURIST4.util.isempty(wkt)){
                        value = '';
                    }else{
                        let typeCode = 'm';
                        if(wkt.indexOf('GEOMETRYCOLLECTION')<0 && wkt.indexOf('MULTI')<0){
                            if(wkt.indexOf('LINESTRING')>=0){
                                typeCode = 'l';
                            }else if(wkt.indexOf('POLYGON')>=0){
                                typeCode = 'pl';
                            }else {
                                typeCode = 'p';
                            }
                        }
                        value = `${typeCode} ${wkt}`;
                    }
                }
            }

            if($.isPlainObject(value)){
                value = window.hWin.HEURIST4.util.htmlEscape(Object.values(value).join(' '));
            }else if(Array.isArray(value) && value.length >= 1){
                value = window.hWin.HEURIST4.util.htmlEscape(value.join('; '));
            }else{
                value = window.hWin.HEURIST4.util.htmlEscape(value??'');
            }

            if(!window.hWin.HEURIST4.util.isempty(value)){
                $cell.html(`<span style="display: inline-block;">&lArr;</span><span title="${value}" class="truncate">${value}</span>`);
            }
        });

        if(service_name == 'nomisma'){
            let type = this.example_results[service_name][idx]['properties']['type'];
            this._$('#extra_fluff').html(`Currently showing a <strong>${type}</strong> record`);
        }else{
            this._$('#extra_fluff').html('');
        }

        this._$('#example_fluff').text('Search example records: ');
        this._$('#example_records').show();
    },

    /**
     * Fetches example data from the Nomisma API for different types of queries (mints, hoards, findspots).
     * This method is part of the example result display functionality, specifically for 'nomisma' service.
     * It makes sequential API calls for 'getMints', 'getHoards', and 'getFindspots'.
     *
     * - If `type` is empty and results for 'nomisma' are already cached, it calls `_displayTestResults`.
     * - Initializes `this.example_results['nomisma']` as an array if not already present.
     * - If `type` is empty, defaults to 'getMints'.
     * - Validates `type` against `nomismaServices`.
     * - Constructs the API URL (e.g., `https://nomisma.org/apis/getMints?id=denarius`).
     * - Calls `HAPI4.RecordMgr.lookup_external_service`.
     * - On response, if it's valid GeoJSON, extracts up to 5 features and adds them to `this.example_results['nomisma']`.
     * - Recursively calls itself for the next Nomisma service type ('getHoards' after 'getMints', etc.)
     *   until all types are fetched, then calls `_runTestNomisma('')` to trigger display.
     *
     * @memberof heurist.lookupConfig
     * @instance
     * @private
     * @param {string} [type=''] - The type of Nomisma API to query ('getMints', 'getHoards', 'getFindspots').
     *                             If empty, it either starts the sequence or triggers display if data is loaded.
     * @returns {void}
     */
    _runTestNomisma: function(type = ''){

        let that = this;
        let service_name = 'nomisma';
        const nomismaServices = ['getMints', 'getHoards', 'getFindspots']; // Valid Nomisma service types

        // If type is empty and results are already fetched, display them
        if(type == '' && Object.hasOwn(this.example_results, service_name)){
            this._displayTestResults(service_name);
            return;
        }

        // Initialize cache for nomisma results if it doesn't exist
        if(!Object.hasOwn(this.example_results, service_name)){
            this.example_results[service_name] = [];
        }

        type = (type == '') ? 'getMints' : type; // Default to 'getMints' if type is empty

        // Validate the service type
        if(nomismaServices.indexOf(type) == -1){
            window.hWin.HEURIST4.msg.showMsgErr({
                message: `An invalid request was made in attempting to retrieve sample Nomisma records.<br>Attempting to retrieve "${type}"`,
                error_title: 'Invalid Nomisma request',
                status: window.hWin.ResponseStatus.ACTION_BLOCKED
            });
            return;
        }

        let url = `https://nomisma.org/apis/${type}?id=denarius`; // Construct API URL (example uses 'denarius')

        let request = {
            service: url,
            serviceType: service_name // Service type for HAPI proxy
        };

        // Make the API call via Heurist proxy
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

            if(window.hWin.HEURIST4.util.isGeoJSON(response, true)){ // Check if response is valid GeoJSON
                const value = response.features.slice(0, 5); // Take up to 5 features
                that.example_results[service_name].push(...value); // Add to cached results
            }

            // Recursive calls to fetch data for other Nomisma service types
            if(type == 'getMints'){
                that._runTestNomisma('getHoards');
            }else if(type == 'getHoards'){
                that._runTestNomisma('getFindspots');
            }else{ // All types fetched
                that._runTestNomisma(''); // Trigger display of accumulated results
            }
        });
    },

    /**
     * Handles the change event when a new Heurist record type is selected in the configuration form.
     * It updates the field mapping dropdowns (`<select>`) in the `#tbl_matches` table to show
     * compatible fields from the newly selected record type.
     *
     * Key actions:
     * - Retrieves the selected `rty_ID` from `this.selectRecordType`.
     * - Clears existing options and destroys any hSelect instances on the field mapping dropdowns.
     * - If `rty_ID` is valid (greater than 0):
     *   - Iterates through each field mapping dropdown in the table.
     *   - For each dropdown:
     *     - Determines the currently mapped `dty_ID` from `this._current_cfg` or the service definition.
     *     - Handles special `dty_ID` formats (e.g., concept IDs with suffices like `_long`, `_lat`).
     *     - Populates the dropdown with compatible Heurist fields from the selected record type
     *       using `HEURIST4.ui.createRectypeDetailSelect`. Filters for types like 'freetext', 'blocktext', etc.
     *     - Attaches a change handler to update status.
     *   - Shows the service mapping section and the "Apply" button.
     * - If `rty_ID` is not valid (e.g., "select record type" is chosen):
     *   - Hides the service mapping section and "Apply" button.
     * - If it's a new configuration (`this._isNewCfg`) and a label exists, updates the label in the service list.
     * - Calls `_displayTestResults` to refresh example data based on the new context.
     *
     * @memberof heurist.lookupConfig
     * @instance
     * @private
     * @returns {void}
     */
    _onRectypeChange: function(){
     
        let rty_ID = this.selectRecordType.val(); // Get selected Heurist Record Type ID
        
        let tbl = this._$('#tbl_matches'); // The table containing field mappings
        let that = this;
        
        // Clear and reset existing field mapping dropdowns
        $.each(tbl.find('select'), function(i,selObj){
            if($(selObj).hSelect("instance")!=undefined){ // If hSelect is initialized
               that._off($(selObj).hSelect("instance"),'change'); // Remove old change handlers
               $(selObj).hSelect("destroy"); // Destroy hSelect instance
            }
            $(selObj).empty(); // Remove all options
        });

        if(rty_ID>0){ // If a valid record type is selected
            $.each(tbl.find('select'), function(i, ele){ // Iterate through each select element in the mapping table
                
                let field = $(ele).attr('data-field'); // Get the external service field name from data-attribute
                let dty_ID; // Heurist Data Type ID to be selected
              
                // Determine the currently selected dty_ID for this field
                if(!window.hWin.HEURIST4.util.isempty(that._current_cfg)){
                    dty_ID = that._current_cfg.fields[field];
                }else if(!window.hWin.HEURIST4.util.isempty(that.selectServiceType.val())){
                    // Fallback to default from service definition if not in current config (e.g. new config)
                    for(let idx in that._available_services){
                        if(that._available_services[idx].service == that.selectServiceType.val()){ // Match with .service, not val()
                            dty_ID = that._available_services[idx].fields[field];
                        }
                    }
                }

                // Handle special dty_ID formats (e.g., for geo concepts with lat/long parts)
                if (!window.hWin.HEURIST4.util.isempty(dty_ID) && dty_ID.indexOf('-') >= 0){ // concept id - default mapping
                    let extra = '_';
                    if(dty_ID.indexOf('_') > 0){ // e.g., "conceptID_lat"
                        let parts = dty_ID.split('_');
                        dty_ID = parts[0]; // concept id
                        extra = parts[1]; // long | lat
                    }
                    dty_ID = $Db.getLocalID('dty', dty_ID); // Convert concept ID to local dty_ID
                    if(!window.hWin.HEURIST4.util.isempty(dty_ID) && extra != '_'){
                        dty_ID += extra; // Re-append suffix if needed
                    }
                }
                
                // Create/repopulate the dropdown with compatible fields from the selected record type
                let sel = window.hWin.HEURIST4.ui.createRectypeDetailSelect(ele, rty_ID, 
                    ['freetext','blocktext','enum','date','geo','float','year','integer','resource','file','relmarker'], // Allowed Heurist field types
                    '...', // Placeholder option
                    {show_latlong:true, show_dt_name:true, selectedValue:dty_ID} ); // Options
                    
                that._on($(sel), {change:function(){that._updateStatus();}}); // Attach change handler
            });
            
            this._$('#service_mapping').show(); // Show the field mapping section
            this.btnApply.show(); // Show the Apply button
            
        }else{ // If no valid record type is selected
            this._$('#service_mapping').hide(); // Hide field mapping
            this.btnApply.hide(); // Hide Apply button
        }

        // Update label in the service list if it's a new configuration being defined
        if(this._isNewCfg && this._current_cfg.label){
            let s = `${this._current_cfg.label}<span class="ui-icon ui-icon-arrowthick-1-e"></span> ` 
                    +  (rty_ID>0?$Db.rty(rty_ID, 'rty_Name'):'select record type');
            this.serviceList.find('li[data-service-id="new"]').html(s);
        }

        this._displayTestResults(this.selectServiceType.val()); // Refresh example results display
    },

    /**
     * Applies the current configuration changes for a specific service instance.
     * This is typically called when the "Apply" button for a single service configuration is clicked.
     *
     * Key actions:
     * - Retrieves selected record type ID, service name, and label from the form.
     * - Validates that a service and record type are selected, and that `_current_cfg` is not empty.
     * - Collects field mappings from the `#tbl_matches` table into a `fields` object.
     * - Validates that at least one field is mapped if there are fields to map.
     * - Ensures `this.options.service_config` (the global config object) is initialized.
     * - Generates a unique `t_name` (service instance ID) using `service_name` and `rty_ID`.
     * - Sets a default label if none is provided.
     * - If the record type for an existing service configuration was changed, deletes the old configuration entry.
     * - Updates `this._current_cfg` with the new `service_id`, `rty_ID`, `label`, `service` name, and `fields` mappings.
     * - Stores the updated `this._current_cfg` into `this.options.service_config` using `t_name` as the key.
     * - Resets `this._isNewCfg = false`.
     * - Sets `this._services_modified = true` to enable the main "Save All Configurations" button.
     * - Calls `_reloadServiceList` to refresh the list of configured services in the left panel.
     * - Calls `_updateStatus` to refresh UI states.
     *
     * @memberof heurist.lookupConfig
     * @instance
     * @private
     * @returns {void}
     */
    _applyConfig: function(){

        let rty_ID = this.selectRecordType.val();
        let service_name = this.selectServiceType.val();
        let label = this._$('#inpt_label').val();

        // Validate that essential selections are made
        let service_ready = rty_ID>0 && !window.hWin.HEURIST4.util.isempty(service_name);
        if(window.hWin.HEURIST4.util.isempty(this._current_cfg)){
            window.hWin.HEURIST4.msg.showMsgFlash('Select or define new service first');
            return;
        }else if(!service_ready){
            window.hWin.HEURIST4.msg.showMsgFlash('Select a service and a record type to map', 2000);
            return;
        }

        let tbl = this._$('#tbl_matches'); // Field mapping table
        let is_field_mapped = false;
        let fields = {}; // Object to store {external_field_name: heurist_dty_ID}

        // Collect field mappings from the UI
        $.each(tbl.find('select'), function(i, ele){
            let field = $(ele).attr('data-field'); // External service field name
            let dty_ID = $(ele).val(); // Selected Heurist dty_ID
            fields[field] = dty_ID; 
            if(dty_ID > 0) is_field_mapped = true; // Check if at least one field is actually mapped
        });

        // Validate that at least one field is mapped if there are mappable fields
        if(!is_field_mapped && tbl.find('select').length > 0){
            window.hWin.HEURIST4.msg.showMsgFlash('Map at least one field listed', 3000);
            return;
        }

        // Ensure the main service_config object is valid JSON or initialize it
        this.options.service_config = window.hWin.HEURIST4.util.isJSON(this.options.service_config);
        if(!this.options.service_config){
            this.options.service_config = {};    
        } 

        let t_name = `${service_name}_${rty_ID}`; // Unique key for this service instance

        if(window.hWin.HEURIST4.util.isempty(label)){ // Default label if not provided
            label = service_name;
        }

        // If record type has changed for an existing config, remove the old entry
        if(this._current_cfg.service_id && t_name != this._current_cfg.service_id && this.options.service_config[this._current_cfg.service_id]){
            delete this.options.service_config[this._current_cfg.service_id];
        }

        // Update the current configuration object
        this._current_cfg.service_id = t_name;
        this._current_cfg.rty_ID = rty_ID;
        this._current_cfg.label = label;
        this._current_cfg.service = service_name; // Store the base service type
        this._current_cfg.fields = fields; // Store the field mappings

        // Add/update this configuration in the main service_config object
        this.options.service_config[t_name] = this._current_cfg;

        this._isNewCfg = false; // No longer a new configuration

        this._services_modified = true; // Mark that overall configurations have changed
        window.hWin.HEURIST4.util.setDisabled(this.save_btn, !this._services_modified); // Enable main save button

        this._reloadServiceList(); // Refresh the list of configured services
        this._updateStatus(); // Update UI states (e.g., disable Apply button until further changes)
    },

    /**
     * Verifies if a given service configuration is still valid.
     * Checks if the associated record type still exists in the database and
     * if the base service definition is still available in `_available_services`.
     *
     * @memberof heurist.lookupConfig
     * @instance
     * @private
     * @param {string} key - The key of the service configuration in `this.options.service_config` to verify.
     * @returns {boolean} `true` if the configuration is valid, `false` otherwise.
     */
    _verifyService: function(key){

        let config = this.options.service_config[key];
        let def_config = this._available_services.find((service) => service['service'] == config.service);

        if(!def_config || Object.keys(def_config).length == 0){ // Check if base service definition exists
            return false;
        }

        // Check if the associated record type still exists in the database
        return config.rty_ID > 0 && $Db.rty(config.rty_ID) !== null;
    },

    /**
     * Updates legacy or incorrect information within a specific service configuration.
     * This includes:
     * - Ensuring `service` property exists (migrating from `service_name` if necessary).
     * - Ensuring `service_id` (unique key like `serviceName_rtyID`) exists.
     * - Correcting outdated `dialog` script names (e.g., 'recordLookup' to 'lookupTLC').
     * - Ensuring the main configuration key in `this.options.service_config` uses the
     *   correct `service_id` format (e.g., `serviceName_rtyID`) and migrates if it's an old format (e.g., just `serviceName`).
     *
     * Modifies `this.options.service_config[key]` in place.
     *
     * @memberof heurist.lookupConfig
     * @instance
     * @private
     * @param {string} key - The key of the service configuration in `this.options.service_config` to update.
     * @returns {Array<boolean, string>} An array where:
     *          - The first element is `true` if changes were made, `false` otherwise.
     *          - The second element is the (potentially updated) `key` for the configuration.
     */
    _updateServiceInfo: function(key){

        let has_changes = false;

        // Check that the service property has been defined
        if(this.options.service_config[key]['service'] == null){

            // Likely has service_name instead
            if(this.options.service_config[key]['service_name'] != null){
                this.options.service_config[key]['service'] = this.options.service_config[key]['service_name'];
                delete this.options.service_config[key]['service_name'];
            }else{ // invalid configuration, missing a service name
                delete this.options.service_config[key];
            }

            has_changes = true;
        }else if(this.options.service_config[key]['service_name'] != null){
            delete this.options.service_config[key]['service_name'];

            has_changes = true;
        }

        // Check that the service id (serviceName_rtyID) has been defined
        if(this.options.service_config[key]['service_id'] == null){
            this.options.service_config[key]['service_id'] = `${this.options.service_config[key]['service']}_${this.options.service_config[key]['rty_ID']}`;

            has_changes = true;
        }

        // Check that the script name is correct, e.g. replace recordLookup with lookup
        if(this.options.service_config[key]['dialog'] == 'recordLookup' || this.options.service_config[key]['dialog'] == 'lookupTCL'){
            this.options.service_config[key]['dialog'] = 'lookupTLC';

            has_changes = true;
        }else if(this.options.service_config[key]['dialog'] == 'recordLookupBnFLibrary' || this.options.service_config[key]['dialog'] == 'lookupBnFLibrary'){
            this.options.service_config[key]['dialog'] = 'lookupBnFLibrary_bib';

            has_changes = true;
        }else if(this.options.service_config[key]['dialog'].includes('recordLookup')){
            this.options.service_config[key]['dialog'] = this.options.service_config[key]['dialog'].replace('recordLookup', 'lookup');

            has_changes = true;
        }

        // Correct service's key (to allow the service to be assigned to multiple record types)
        if(key.includes("_") === false){

            let new_key = this.options.service_config[key]['service_id'];
            this.options.service_config[new_key] = window.hWin.HEURIST4.util.cloneJSON(this.options.service_config[key]);

            delete this.options.service_config[key];
            key = new_key;

            has_changes = true;
        }

        return [has_changes, key];
    },

    /**
     * Updates the field mappings for a service configuration (`this.options.service_config[key]`)
     * to align with the current definition of the base service in `this._available_services`.
     *
     * - **Adds missing fields:** If the base service definition (`service_details`) contains fields
     *   not present in the current configuration's `fields` object, these are added with a
     *   default mapping of `null` (unmapped).
     * - **Removes obsolete fields:** If the current configuration's `fields` object contains
     *   fields that are no longer part of the base service definition, these are removed.
     *
     * Modifies `this.options.service_config[key].fields` in place.
     *
     * @memberof heurist.lookupConfig
     * @instance
     * @private
     * @param {string} key - The key of the service configuration in `this.options.service_config`.
     * @returns {boolean} `true` if any fields were added or removed, `false` otherwise.
     */
    _updateServiceFields: function(key){

        let has_changes = false;

        // Update configurations (Add missing fields and remove fields no longer handled)
        let n_fields = this.options.service_config[key]['fields'];
        let service_details = this._available_services.find((service) => service['service'] == this.options.service_config[key]['service']);

        // Add missing fields
        for(const fld_name of Object.keys(service_details['fields'])){
            if(Object.hasOwn(n_fields, fld_name)){
                continue;
            }

            n_fields[fld_name] = null;
            has_changes = true;
        }

        // Remove fields
        for(const fld_name of Object.keys(n_fields)){
            if(Object.hasOwn(service_details['fields'], fld_name)){
                continue;
            }

            delete n_fields[fld_name];
            has_changes = true;
        }

        if(has_changes){
            this.options.service_config[key]['fields'] = n_fields;
        }

        return has_changes;
    },

    /**
     * Updates or adds default 'options' (additional settings) for specific service configurations.
     * This is primarily used to ensure that services like 'bnfLibrary' (bibliographic) and
     * 'bnfLibraryAut' (authoritative) have their default options structure for settings like
     * record dumping and author codes if they are missing.
     *
     * Also corrects a minor typo 'dump_receord' to 'dump_record' if found in options.
     *
     * Modifies `this.options.service_config[key].options` in place.
     *
     * @memberof heurist.lookupConfig
     * @instance
     * @private
     * @param {string} key - The key of the service configuration in `this.options.service_config`.
     * @returns {boolean} `true` if any options were added or corrected, `false` otherwise.
     */
    _updateServiceOptions: function(key){

        let has_changes = false;

        // Check for missing settings/additional options
        if(this.options.service_config[key]['service'] == 'bnfLibrary'
        && !Object.hasOwn(this.options.service_config[key], 'options')){
            // add default options

            this.options.service_config[key]['options'] = {
                'author_codes': '', //'contributor_codes': ''
                'dump_record': true,
                'dump_field': 'rec_ScratchPad'
            };
            has_changes = true;

        }else if(this.options.service_config[key]['service'] == "bnfLibraryAut"
             && !Object.hasOwn(this.options.service_config[key], 'options')){
            // add default options
            
            this.options.service_config[key]['options'] = {
                'dump_record': true,
                'dump_field': 'rec_ScratchPad'
            };
            has_changes = true;

        }

        if(Object.hasOwn(this.options.service_config[key], 'options') && Object.hasOwn(this.options.service_config[key]['options'], 'dump_receord')){
            has_changes = true;
            delete this.options.service_config[key]['options']['dump_receord'];
        }

        return has_changes;
    }
});