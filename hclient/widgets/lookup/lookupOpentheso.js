/**
 * lookupOpentheso.js - Search Opentheso thesaurus records.
 *
 * @fileOverview
 * This file defines the `heurist.lookupOpentheso` jQuery UI widget.
 * This widget provides an interface for searching concepts within thesauruses
 * hosted on Opentheso servers. It dynamically populates dropdowns for selecting
 * the Opentheso server, the specific thesaurus, language, and collections (groups)
 * to refine the search.
 *
 * The widget:
 *  1. Loads its UI from `lookupOpentheso.html`.
 *  2. Fetches a list of available Opentheso servers via a Heurist proxy endpoint (`opentheso_get_servers`).
 *  3. Dynamically fetches thesauruses for the selected server (`opentheso_get_thesauruses`).
 *  4. Dynamically fetches collections/groups for the selected thesaurus (`opentheso_get_collections`).
 *  5. Constructs search queries for the Opentheso API based on user input and selections.
 *  6. Displays search results (concepts) in a list.
 *  7. Allows users to select concepts for mapping to Heurist fields, with special handling
 *     for multilingual labels and preparing data for enum or text fields.
 *
 * @package     Heurist academic knowledge management system
 * @subpackage  hclient\widgets\lookup
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Brandon McKay   <blmckay13@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since       6.0
 */

/**
 * Widget for searching concepts in Opentheso instances.
 * It features a dynamic UI where users first select an Opentheso server,
 * then a thesaurus, and optionally language and collections to narrow down their search.
 *
 * @widget heurist.lookupOpentheso
 * @extends heurist.lookupBase
 */
$.widget( "heurist.lookupOpentheso", $.heurist.lookupBase, {

    /**
     * Default options for the Opentheso lookup widget.
     * @memberof heurist.lookupOpentheso
     * @instance
     * @property {Object} options
     * @property {number} [options.height=750] - The height of the dialog.
     * @property {number} [options.width=700] - The width of the dialog.
     * @property {string} [options.title="Search Opentheso records"] - The title of the dialog.
     * @property {string} [options.htmlContent='lookupOpentheso.html'] - The HTML content file for the dialog.
     */
    options: {

        height: 750,
        width:  700,

        title:  "Search Opentheso records",

        htmlContent: 'lookupOpentheso.html'
    },

    /**
     * The base URL for the selected Opentheso API. This is dynamically set in `_doSearch`
     * based on the server selected by the user.
     * @memberof heurist.lookupOpentheso
     * @instance
     * @type {string}
     */
    baseURL: '',

    /**
     * The service name identifier for this lookup type.
     * @memberof heurist.lookupOpentheso
     * @instance
     * @type {string}
     */
    serviceName: 'opentheso',

    /**
     * Caches information about available Opentheso servers.
     * Keys are server identifiers (e.g., shortnames), values are objects
     * like `{ title: "Server Hostname", uri: "https://server.url/" }`.
     * Populated in `_initControls`.
     * @memberof heurist.lookupOpentheso
     * @instance
     * @private
     * @type {Object<string, {title: string, uri: string}>}
     */
    _servers: {},

    /**
     * Caches the list of thesauruses available on each server.
     * Structure: `{ server_id: [{key: "thesaurus_id", title: "Thesaurus Name"}, ...], ... }`
     * Populated by `_updateThesaurusList`.
     * @memberof heurist.lookupOpentheso
     * @instance
     * @private
     * @type {Object<string, Array<Object>>}
     */
    _thesauruses: {},

    /**
     * Caches the list of collections (groups) available for each thesaurus on each server.
     * Structure: `{ server_id: { thesaurus_id: [{key: "group_id", title: "Group Name"}, ...], ... }, ... }`
     * Populated by `_updateThesaurusList` (partially) and `_updateCollections`.
     * @memberof heurist.lookupOpentheso
     * @instance
     * @private
     * @type {Object<string, Object<string, Array<Object>>>}
     */
    _collections: {},

    /**
     * jQuery references to key select elements in the UI for easy access.
     * Populated in `_initControls`.
     * @memberof heurist.lookupOpentheso
     * @instance
     * @private
     * @type {Object<string, ?jQuery>}
     * @property {?jQuery} server - The dropdown for selecting the Opentheso server.
     * @property {?jQuery} theso - The dropdown for selecting the thesaurus.
     * @property {?jQuery} lang - The dropdown for selecting the language.
     * @property {?jQuery} group - The multi-select for choosing collections/groups.
     */
    _sel_elements: {
        'server': null,
        'theso': null,
        'lang': null,
        'group': null
    },

    /**
     * Flag to indicate if the collections/groups dropdown needs to be refreshed from the server.
     * Set to `true` when the thesaurus selection changes or if initial population failed.
     * @memberof heurist.lookupOpentheso
     * @instance
     * @private
     * @type {boolean}
     */
    _refreshCollections: false,

    /**
     * Initializes UI controls for the Opentheso lookup widget.
     * - Applies specific CSS styling.
     * - Caches jQuery selectors for server, thesaurus, language, and group dropdowns in `this._sel_elements`.
     * - Fetches the list of available Opentheso servers via HAPI (`opentheso_get_servers`).
     *   - Populates the server dropdown (`#inpt_server`).
     *   - Attaches a change handler to the server dropdown to call `_displayThesauruses`.
     *   - Calls `_updateThesauruses` to fetch thesauruses for the initially selected/default server.
     * - Sets up change handlers for thesaurus and group dropdowns to update dependent lists.
     * - Initializes language dropdown using `HEURIST4.ui.createLanguageSelect`.
     * - Sets up click handlers for refresh buttons (thesauruses, groups) and a cancel button for group selection.
     * - Calls the parent widget's `_initControls`.
     *
     * @memberof heurist.lookupOpentheso
     * @instance
     * @private
     * @override
     * @returns {void|*} The result of `this._super()`.
     */
    _initControls: function(){

        let that = this;

        // Extra field styling
        this.element.find('#frm-search .header').css({width: '125px', 'min-width': '125px', display: 'inline-block'});

        this._sel_elements = {
            server: this.element.find('#inpt_server'),
            theso: this.element.find('#inpt_theso'),
            lang: this.element.find('#inpt_lang'),
            group: this.element.find('#inpt_group')
        };

        // ----- SERVER SELECT -----
        let request = {
            serviceType: 'opentheso',
            service: 'opentheso_get_servers'
        };

        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack(that.element);

            if(response.status && response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            that._servers = response.data;
            let options = [];
            for(const server in that._servers){

                let url = new URL(that._servers[server]);
                that._servers[server] = { title: url.hostname, uri: url.href };
                options.push({key: server, title: that._servers[server]['title']});

                that._collections[server] = {};
            }
            window.hWin.HEURIST4.ui.fillSelector(that._sel_elements['server'][0], options);
            window.hWin.HEURIST4.ui.initHSelect(that._sel_elements['server'], false, null, {
                onSelectMenu: () => { that._displayThesauruses(); }
            });

            if(that._sel_elements['server'].hSelect('instance') !== undefined){
                that._sel_elements['server'].hSelect('widget').css('width', '170px');
            }

            that._updateThesauruses();
        });

        this._on(this._sel_elements['server'], {
            change: this._displayThesauruses
        });

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, null, '<span style="color: white;">Retrieving servers...</span>');

        // ----- THESO SELECT -----
        this._on(this._sel_elements['theso'], {
            change: function(){
                this._refreshCollections = true;
                this._displayCollections();
            }
        });

        // ----- GROUP SELECT -----
        this._on(this._sel_elements['group'], {
            change: function(){
                this.element.find('#btn_cnlGroups').show();
            }
        });

        // ----- LANGUAGE SELECT -----
        window.hWin.HEURIST4.ui.createLanguageSelect(this._sel_elements['lang'], [{key: '', title: 'select a language...'}]);

        // ----- REFRESH BUTTONS -----
        this._on(this.element.find('#btn_refTheso').button({showLabel: false, icon: 'ui-icon-refresh'}), {
            click: function(){
                this._updateThesauruses(true);
            }
        });
        this._on(this.element.find('#btn_refGroups').button({showLabel: false, icon: 'ui-icon-refresh'}), {
            click: function(){
                this._updateCollections(true);
            }
        });

        // ----- CANCEL BUTTON -----
        this._on(this.element.find('#btn_cnlGroups').button({showLabel: false, icon: 'ui-icon-cancel'}).hide(), {
            click: function(){
                this._sel_elements['group'].val([]);
                this.element.find('#btn_cnlGroups').hide();
            }
        })

        return this._super();
    },

    /**
     * Fetches the list of thesauruses for the currently selected Opentheso server.
     * This can be triggered on initial load or when the refresh button is clicked.
     *
     * - Retrieves the selected server ID from `this._sel_elements['server']`.
     * - Makes a HAPI call to `lookup_external_service` with service `opentheso_get_thesauruses`.
     *   - `params.servers`: Set to the current server ID if `is_refresh` is true, otherwise null (to fetch all initially).
     *   - `params.refresh`: Set to 1 if `is_refresh` is true, otherwise 0.
     * - On success, calls `_updateThesaurusList` to process the response and then `_displayThesauruses` to update the UI.
     * - Shows a loading coverall during the request.
     *
     * @memberof heurist.lookupOpentheso
     * @instance
     * @private
     * @param {boolean} [is_refresh=false] - If `true`, forces a refresh from the Opentheso server for the current selection.
     *                                       If `false`, might use cached data or fetch all initially.
     * @returns {void}
     */
    _updateThesauruses: function(is_refresh = false){

        let that = this;

        let ser_id = this._sel_elements['server'].val();

        let request = {
            service: 'opentheso_get_thesauruses',
            serviceType: 'opentheso',
            params: {
                servers: is_refresh ? ser_id : null,
                refresh: is_refresh ? 1 : 0
            }
        };

        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack(that.element);

            if(response.status && response.status != window.hWin.ResponseStatus.OK){
                // display error and show the textbox instead
                window.hWin.HEURIST4.msg.showMsgErr(response);

                return;
            }

            that._updateThesaurusList(response, is_refresh);

            that._displayThesauruses();
        });

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, null, '<span style="color: white;">Retrieving thesauruses...</span>');
    },

    /**
     * Processes the response from `_updateThesauruses` and updates the internal
     * cache of thesauruses (`this._thesauruses`) and their collections (`this._collections`).
     *
     * Iterates through each server defined in `this._servers`:
     * - Extracts thesaurus data for the current server from the `response`.
     * - For each thesaurus, creates an option object `{key: thesaurus_id, title: thesaurus_name}`.
     * - Initializes/updates the entry for this server and thesaurus in `this._collections`.
     * - If thesaurus groups (collections) are provided in the response, populates them.
     * - Sets `this._refreshCollections` if groups are empty and it's not a forced refresh,
     *   to indicate that collections might need to be fetched separately.
     * - Stores the processed thesaurus options in `this._thesauruses[server]`.
     *
     * @memberof heurist.lookupOpentheso
     * @instance
     * @private
     * @param {Object} response - The JSON response from the `opentheso_get_thesauruses` HAPI call.
     *                            Expected to be an object where keys are server IDs and values are
     *                            arrays/objects of thesaurus information.
     * @param {boolean} is_refresh - Indicates if the update was due to a forced refresh.
     * @returns {void}
     */
    _updateThesaurusList: function(response, is_refresh){

        for(const server in this._servers){

            const theso = Object.hasOwn(response, server) ? response[server] : [];
            let options = [];

            for(const key in theso){
                options.push({key: key, title: theso[key]['name']});

                this._collections[server][key] = [];

                if(theso[key]['groups'].length <= 0){
                    this._refreshCollections = !is_refresh;
                    continue;
                }

                for(const g_key in theso[key]['groups']){
                    this._collections[server][key].push({key: g_key, title: theso[key]['groups'][g_key]});
                }
            }

            this._thesauruses[server] = options;
        }
    },
    
    /**
     * Populates the thesaurus dropdown (`#inpt_theso`) based on the currently selected server
     * and the cached thesaurus data in `this._thesauruses`.
     * It also triggers an update of the collections dropdown.
     *
     * - Clears and resets the thesaurus dropdown.
     * - Retrieves the selected server ID.
     * - Gets the list of thesaurus options for that server from `this._thesauruses`.
     * - If no thesauruses are available, adds a "No thesauruses available" option.
     * - Populates the dropdown using `HEURIST4.ui.fillSelector` and initializes it with `initHSelect`.
     * - Triggers a 'change' event on the thesaurus dropdown to update the collections list.
     *
     * @memberof heurist.lookupOpentheso
     * @instance
     * @private
     * @returns {void}
     */
    _displayThesauruses: function(){

        if(!this._sel_elements?.['theso']){ // Ensure the thesaurus select element is cached
            return;
        }

        this._sel_elements['theso'].empty(); // remove previous options
        if(this._sel_elements['theso'].hSelect('instance') !== undefined){
            this._sel_elements['theso'].hSelect('destroy');
        }

        let server = this._sel_elements['server'].val();
        let options = this._thesauruses[server];

        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(options)){
            options = [{key: '', title: 'No thesauruses available'}];
        }

        window.hWin.HEURIST4.ui.fillSelector(this._sel_elements['theso'][0], options);
        window.hWin.HEURIST4.ui.initHSelect(this._sel_elements['theso'], true);

        this._sel_elements['theso'].trigger('change');
    },

    /**
     * Fetches the list of collections (groups) for the currently selected Opentheso server and thesaurus.
     * This can be triggered on thesaurus change or when the refresh button for collections is clicked.
     *
     * - Retrieves selected server ID and thesaurus ID. If thesaurus ID is empty, returns.
     * - Makes a HAPI call to `lookup_external_service` with service `opentheso_get_collections`.
     *   - `params.server`: Selected server ID.
     *   - `params.thesaurus`: Selected thesaurus ID.
     *   - `params.refresh`: Set to 1 if `is_refresh` is true, otherwise 0.
     * - On success:
     *   - Sets `this._refreshCollections = false`.
     *   - Processes the `response.groups` into an array of option objects.
     *   - Caches these options in `this._collections[ser_id][th_id]`.
     *   - Calls `_displayCollections` to update the UI.
     * - Shows a loading coverall during the request.
     *
     * @memberof heurist.lookupOpentheso
     * @instance
     * @private
     * @param {boolean} [is_refresh=false] - If `true`, forces a refresh from the Opentheso server.
     * @returns {void}
     */
    _updateCollections: function(is_refresh = false){

        let that = this;

        let ser_id = this._sel_elements['server'].val();
        let th_id = this._sel_elements['theso'].val();

        if(window.hWin.HEURIST4.util.isempty(th_id)){
            return;
        }

        let request = {
            service: 'opentheso_get_collections', // requested metadata
            serviceType: 'opentheso', // requesting service
            params: {
                server: ser_id,
                thesaurus: th_id,
                refresh: is_refresh === true ? 1 : 0
            }
        };

        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack(that.element);
            that._refreshCollections = false;

            if(response.status && response.status != window.hWin.ResponseStatus.OK){
                // display error and show the textbox instead
                window.hWin.HEURIST4.msg.showMsgErr(response);

                return;
            }

            // Process group response
            let options = [];
            for(const group_id in response.groups){
                options.push({key: group_id, title: response.groups[group_id]});
            }
            that._collections[ser_id][th_id] = options; // cache collection details

            that._displayCollections();
        });

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, null, '<span style="color: white;">Retrieving available collections...</span>');
    },

    /**
     * Populates the collections/groups multi-select list (`#inpt_group`) based on the
     * currently selected server and thesaurus, using cached data in `this._collections`.
     * If collections are not cached and `this._refreshCollections` is true, it triggers
     * `_updateCollections` to fetch them first.
     *
     * - Clears the group multi-select list.
     * - Retrieves selected server and thesaurus IDs.
     * - Gets the list of collection options from `this._collections`.
     * - If no collections are cached and `_refreshCollections` is true, calls `_updateCollections(true)` and returns.
     * - If no collections are available (even after potential refresh attempt), adds a "No groups available" option.
     * - Populates the multi-select using `HEURIST4.ui.fillSelector` and initializes it with `initHSelect`.
     * - Sets the `size` attribute of the multi-select.
     *
     * @memberof heurist.lookupOpentheso
     * @instance
     * @private
     * @returns {void}
     */
    _displayCollections: function(){

        if(!this._sel_elements?.['group']){ // Ensure the group select element is cached
            return;
        }

        this._sel_elements['group'].empty(); // remove previous options

        let server = this._sel_elements['server'].val();
        let theso = this._sel_elements['theso'].val();
        let options = this._collections[server][theso];

        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(options)){

            if(this._refreshCollections){ // update groups
                this._updateCollections(true);
                return;
            }

            options = [{key: '', title: 'No groups available'}];
        }
        this._refreshCollections = false;

        window.hWin.HEURIST4.ui.fillSelector(this._sel_elements['group'][0], options);
        window.hWin.HEURIST4.ui.initHSelect(this._sel_elements['group'], true);

        let length = options.length > 0 ? options.length : 3;
        this._sel_elements['group'].attr('size', length);

        this._sel_elements['group'].find('option').css('padding', '5px 10px');
    },

    /**
     * Renders a single concept record in the result list for Opentheso search results.
     * This method overrides the parent's `_rendererResultList`.
     * It constructs a display string (`recTitle`) by concatenating 'term_label',
     * 'term_desc', and 'term_uri' (as a link).
     *
     * @memberof heurist.lookupOpentheso
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
         * It HTML escapes the value and wraps it in a div with a specified width for truncation.
         * If `fldname` is 'term_uri', it formats the value as an anchor tag.
         * @param {string} fldname - The name of the field.
         * @param {number} width - The display width for the field in 'ex' units. If 0, no div wrapper.
         * @returns {string} HTML string for the formatted field.
         */
        function fld(fldname, width){
            let s = recordset.fld(record, fldname);
            s = window.hWin.HEURIST4.util.htmlEscape(s || '');
            let title = s;

            if(fldname == 'term_uri'){
                s = `<a href="${s}" target="_blank" rel="noopener"> view here </a>`;
                title = 'View record';
            }
            return width > 0 ? `<div style="display:inline-block;width:${width}ex" class="truncate" title="${title}">${s}</div>` : s;
        }
        
        // Construct the composite title string for the record display
        const recTitle = fld('term_label', 25) + fld('term_desc', 70) + fld('term_uri', 10); 
        recordset.setFld(record, 'rec_Title', recTitle); // Set the formatted title

        return this._super(recordset, record); // Call parent's renderer
    },

    /**
     * Processes the user's selection from the Opentheso result list.
     * This method is called when the main action button (e.g., "Select") is clicked.
     *
     * 1. Retrieves the selected record.
     * 2. Initializes `res` object and adds `ext_url` using 'term_uri'.
     * 3. Calls `this.prepareValues` to map standard fields.
     * 4. Special handling for 'term_label': If it contains translations (detected as an array
     *    where the second element is an object), it flattens these translations into the main
     *    `res[label_dty_ID]` array.
     * 5. Special handling for 'term_field' (a field intended to store the full concept data):
     *    - Retrieves the target Heurist field's type (`dty_Type`).
     *    - Constructs a `value` object containing `label`, `desc`, `code`, `uri`, and `translations`
     *      from the selected Opentheso record.
     *    - If `dty_Type` is 'blocktext', stringifies the `value` object.
     *    - If `dty_Type` is not 'enum' or 'blocktext', uses only `value.label`.
     *    - Assigns this processed `value` to `res[term_field_dty_ID]`.
     * 6. Calls `this.closingAction` to pass the prepared `res` object back.
     *
     * @memberof heurist.lookupOpentheso
     * @instance
     * @override
     * @returns {void}
     */
    doAction: function(){

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, null, '<span style="color: white;">Preparing values for record editor...</span>');

        // get selected recordset
        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){
            return;
        }

        let res = {};
        res['ext_url'] = recset.fld(record, 'term_uri'); // add Opentheso link

        res = this.prepareValues(recset, record, res);

        // Account for label translations
        let label_dty_ID = this.options.mapping.fields['term_label'];
        if(label_dty_ID > 0
            && window.hWin.HEURIST4.util.isArrayNotEmpty(res[label_dty_ID])
            && res[label_dty_ID].length == 2 && window.hWin.HEURIST4.util.isObject(res[label_dty_ID][1])){

                res[label_dty_ID].push(...Object.values(res[label_dty_ID][1]));
                res[label_dty_ID].splice(1, 1);
        }

        // Setup value for insertion into enum field
        let term_field_dty_ID = this.options.mapping.fields['term_field'];
        if(term_field_dty_ID > 0){

            let type = $Db.dty(term_field_dty_ID, 'dty_Type');
            let value = {
                label: recset.fld(record, 'term_label'),
                desc: recset.fld(record, 'term_desc'),
                code: recset.fld(record, 'term_code'),
                uri: recset.fld(record, 'term_uri'),
                translations: recset.fld(record, 'term_translations')
            }

            value = type == 'blocktext' ? JSON.stringify(value) : value;
            value = type != 'enum' && type != 'blocktext' ? value['label'] : value;

            res[term_field_dty_ID] = [value];
        }

        this.closingAction(res);
    },

    /**
     * Constructs the search query for the selected Opentheso API and executes the search.
     *
     * - Sets `this.baseURL` using the URI of the selected server from `this._servers`.
     * - Retrieves selected thesaurus ID, search term, group(s), and language from UI elements.
     * - Validates that a thesaurus and search term are provided.
     * - Constructs the API endpoint path: `concept/THESAURUS_ID/search?`.
     * - Populates `params` object:
     *   - `q`: Search term.
     *   - `lang`: 2-character language code (if selected).
     *   - `group`: Comma-separated list of selected group IDs (if any).
     * - Calls `this._super(params, request_options)` to execute the search.
     *   - `request_options.preferred_lang`: Passes the selected language (or 'fr' default)
     *     to the Heurist proxy, which might use it for content negotiation if the API supports it
     *     or if the proxy itself handles language variations.
     *
     * @memberof heurist.lookupOpentheso
     * @instance
     * @private
     * @override
     * @returns {void}
     */
    _doSearch: function(){

        this.baseURL = this._servers[this._sel_elements['server'].val()]['uri']; // Set base URL for selected server
        let params = {};
        let th_id = this._sel_elements['theso'].val();

        let search = this.element.find('#inpt_search').val();
        let grouping = this.element.find('#inpt_group').val(); // This is a multi-select, returns array
        let language = this._sel_elements['lang'].val();

        if(window.hWin.HEURIST4.util.isempty(th_id)){
            window.hWin.HEURIST4.msg.showMsgFlash('A thesaurus must be selected...', 2000);
            return;
        }
        if(window.hWin.HEURIST4.util.isempty(search)){
            window.hWin.HEURIST4.msg.showMsgFlash('Please enter a value in the search field...', 2000);
            return;
        }

        // Construct endpoint path and query parameters
        this.baseURL += `concept/${th_id}/search?`;
        params['q'] = search;

        if(!window.hWin.HEURIST4.util.isempty(language)){
            language = window.hWin.HAPI4.sysinfo.common_languages[language]['a2']; // Get 2-char code
            params['lang'] = language;
        }
        if(!window.hWin.HEURIST4.util.isempty(grouping) && Array.isArray(grouping) && !window.hWin.HEURIST4.util.isempty(grouping[0])){
            params['group'] = grouping.join(','); // Join multiple group IDs
        }

        // Pass preferred language to proxy in case it's useful for XML parsing or content negotiation
        this._super(params, {
            preferred_lang: window.hWin.HEURIST4.util.isempty(language) || language.length != 2 ? 'fr' : language
        });
    },

    /**
     * Processes the search results received from the Opentheso API.
     * The API is expected to return a direct array of concept objects.
     * This method overrides the parent `_onSearchResult`.
     *
     * - Parses `json_data` (which should be the array of concepts).
     * - If data is invalid or empty, calls `this._super(false or null)`.
     * - Defines fields for HRecordSet: 'rec_ID', 'rec_RecTypeID', and then the expected
     *   properties from an Opentheso concept object: 'term_label', 'term_desc',
     *   'term_code', 'term_uri', 'term_translations'.
     * - Iterates through the `json_data` array (each item is a concept `record`):
     *   - Assigns a sequential local `recID`.
     *   - Creates a `values` array for the HRecordSet row, mapping concept properties
     *     to the defined fields using `Object.values(record)`.
     *   - Stores the record in `res_records` and `res_orders`.
     * - Constructs the final result object or `false`.
     * - Calls `this._super(res)` to display results.
     *
     * @memberof heurist.lookupOpentheso
     * @instance
     * @private
     * @override
     * @param {Array<Object>|string} json_data - The JSON response from the Opentheso search,
     *                                         expected to be an array of concept objects.
     *                                         Can also be an error string/object from HAPI.
     * @returns {void}
     */
    _onSearchResult: function(json_data){

        json_data = window.hWin.HEURIST4.util.isJSON(json_data); // Ensure JS object/array

        if(!json_data || !Array.isArray(json_data) || json_data.length === 0){
            return this._super(json_data && Array.isArray(json_data) && json_data.length === 0 ? null : false);
        }

        let res_records = {}, res_orders = [];

        // Define fields for the HRecordSet, matching order of properties in Opentheso concept objects
        let fields = ['rec_ID', 'rec_RecTypeID'];
        // These must match the order of properties returned by the Opentheso API for each concept
        fields = fields.concat(['term_label', 'term_desc', 'term_code', 'term_uri', 'term_translations']);
        
        let i = 1; // Local record ID counter
        for(const record of json_data){ // API returns an array of concept objects
            let recID = i++;
            // Assuming `Object.values(record)` provides values in the same order as defined in `fields` above
            let values = [recID, this.options.mapping.rty_ID, ...Object.values(record)];

            res_orders.push(recID);
            res_records[recID] = values;
        }

        let res = res_orders.length > 0 ? {fields: fields, order: res_orders, records: res_records} : false;
        this._super(res); // Pass to parent for display
    }
});