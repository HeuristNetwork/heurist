/**
* Main class for Heurist 
*   it stores major config info
*   local db definitions
*   and provides methods to call server side 
*
* Constructor:
* @param _db - database name, if omit it takes from url parameter
* @param _oninit - callback function, obtain parameter true if initialization is successeful
* @returns hAPI Object
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/* global ActionHandler, HSystemMgr */

/*

Properties:
    baseURL
    baseURL_pro
    iconBaseURL - url for record type icon (rty_ID to be added)
    database - current database name
    sysinfo
    is_publish_mode - false if Heurist is inited via main index.php and layout is not from the set of application (DH, EN, WebSearch)

Localization routines (assigned to window.hWin)

    HR  returns localized string
    HRA = localize all elements with class slocale for given element
    HRes = returns url or loads content for localized resource
    HRJ = returns localized value for json (options in widget)

LayoutMgr   HLayout object (@todo replace to new version from CMS)

Classes for server interaction

    SystemMgr - user credentials and system utilities
    RecordMgr - Records SCRUD actions    
    RecordSearch - wrapper for RecordMgr.search method
    EntityMgr - SCRUD for database defenitions and user/groups

*/
function hAPI(_db, _oninit, _baseURL) { //, _currentUser
    const _className = "HAPI",
        _version = "0.4";
    /**
     * @private
     * @type {string|null}
     * @description The name of the current Heurist database. Also publicly available via `that.database`.
     */
    let _database = null;

    /**
     * @private
     * @type {string|null}
     * @description The current language/region code (ISO639-2 alpha-3, uppercase, e.g., 'ENG', 'FRE').
     */
    let _region = null;
    /**
     * @private
     * @type {Object<string, Object<string, string>>}
     * @description Stores loaded localization resources, keyed by region code (e.g., _regional['ENG']['greeting'] = 'Hello').
     */
    let _regional = {};

    /**
     * @private
     * @type {{ugr_ID: number, ugr_FullName: string}}
     * @description A default user object representing a guest user.
     */
    let _guestUser = { ugr_ID: 0, ugr_FullName: 'Guest' };
    /**
     * @private
     * @type {Array<{obj: Object, event_type: string, callback: Function}>}
     * @description An array to store event listeners, especially for inter-frame communication.
     */
    let _listeners = [];
    /**
     * @private
     * @type {boolean}
     * @description Flag indicating if a `_callserver` AJAX request is currently in progress.
     */
    let _is_callserver_in_progress = false;
    /**
     * @private
     * @type {number}
     * @description Timestamp of the last check for database cache relevance. Used to throttle relevance checks.
     */
    let _last_check_dbcache_relevance = 0;

    /**
     * @private
     * @type {boolean}
     * @description Flag to enable or disable debug parameters in server requests (e.g., DBGSESSID).
     */
    let _use_debug = true; // Set to false in production environments if appropriate
        
    /**
     * @private
     * @type {ActionHandler|null}
     * @description Instance of the ActionHandler class for managing UI actions.
     */
    let actionHandler = null; // Will be initialized in _init
                

    /**
    * Initializes the HAPI object. This private function is called by the `hAPI` constructor.
    * Key responsibilities include:
    * 1. Setting up manager instances (`SystemMgr`, `LayoutMgr`, `RecordSearch`).
    * 2. Determining the current database name from parameters or URL.
    * 3. Detecting the base URL for server communication.
    * 4. Initializing localization settings (defaulting to English).
    * 5. Setting up a global resize event listener.
    * 6. Fetching initial system information (`sys_info`) which includes user details,
    *    database settings, and server base URLs.
    * 7. Initializing the `ActionHandler` after system info is loaded.
    * 8. Setting the application locale based on URL parameters or user preferences.
    *
    * @private
    * @param {string} [db_name_param] - The database name. If omitted, it's taken from the URL parameter 'db'.
    * @param {function(boolean): void} oninit_callback - Callback function executed after initialization.
    *        Receives `true` if initialization was successful (database context established), `false` otherwise.
    * @param {string} [baseURL_param] - Optional base URL, primarily for embedded mode where client and server locations might differ.
    * @returns {void}
    */
    function _init(db_name_param, oninit_callback, baseURL_param) {

        that.SystemMgr = new HSystemMgr(that); // Initialize SystemManager first as it's used by sys_info

        //@todo - take  database from URL
        if (_db) {
            _database = _db;
        } else {
            _database = window.hWin.HEURIST4.util.getUrlParameter('db');
        }

        detectBaseURL();
        
        that.database = _database;

        if (!window.hWin.HR) {
            window.hWin.HR = that.setLocale('ENG');
        }

        if (!window.hWin.HEURIST4.util.isFunction(that.fancybox)) {
            that.fancybox = $.fn.fancybox; //to call from iframes
        }

        // layout and configuration arrays are defined (from layout_default.js)    
       
        if (typeof HLayout !== 'undefined' && window.hWin.HEURIST4.util.isFunction(HLayout)
            && typeof window.hWin.cfg_widgets !== 'undefined' && typeof window.hWin.cfg_layouts !== 'undefined') {
            that.LayoutMgr = new HLayout(); //old layout manager
        }
        if (typeof HLayoutMgr !== 'undefined' && window.hWin.HEURIST4.util.isFunction(HLayoutMgr)
            && typeof window.hWin.cfg_widgets !== 'undefined') {
            that.layoutMgr = new HLayoutMgr(); //new layout manager
        }
        if (typeof HRecordSearch !== 'undefined' && window.hWin.HEURIST4.util.isFunction(HRecordSearch)) {
            that.RecordSearch = new HRecordSearch();
        }

        if (!window.onresize) {
            that._delayOnResize = 0;
            function __trigger() {
                window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_WINDOW_RESIZE);
            };
            window.onresize = function () {
                if (that._delayOnResize) clearTimeout(that._delayOnResize);
                that._delayOnResize = setTimeout(__trigger, 1000);
            }
        }

        that.dbSettings = {};

        // Get current user if logged in, and global database settings
        // see usr_info.php sysinfo method  and then system->getCurrentUserAndSysInfo
        if (that.database) {
            
            that.SystemMgr.sys_info(function (success) {
                if (success) {
                    that.baseURL = window.hWin.HAPI4.sysinfo['baseURL'];
                    that.baseURL_pro = window.hWin.HAPI4.sysinfo['baseURL_pro'];
                    // @TODO: rename to rtyIconURL 
                    that.iconBaseURL = that.baseURL + '?db=' + _database + '&icon=';
                    
                    //loads list of actions                 window.hWin.document
                    if(typeof ActionHandler !== 'undefined'){
                        (async () => {
                            that.actionHandler = new ActionHandler();
                            await that.actionHandler.loadActionsFromFile();
                        })();                        
                    }
                    
                    
                    let lang = window.hWin.HEURIST4.util.getUrlParameter('lang');
                    if (lang) {
                        //save in preferences
                        window.hWin.HAPI4.save_pref('layout_language', lang);
                    } else {
                        lang = window.hWin.HAPI4.get_prefs_def('layout_language', 'ENG');
                    }
                    window.hWin.HR = that.setLocale(lang); //change current locale
                    window.hWin.HRA = that.HRA; //localize all elements with class slocale for given element
                    window.hWin.HRes = that.HRes; //returns url or content for localized resource (help, documentation)
                    window.hWin.HRJ = that.HRJ; // returns localized value for json (options in widget)

                }
                _oninit(success);
            });
        } else if (_oninit) {
                _oninit(false);
        }
    }
    
    // we obtain correct values from server side 
    // see window.hWin.HAPI4.sysinfo['baseURL']; and window.hWin.HAPI4.sysinfo['baseURL_pro'];
    //
    // finds and assign  installDir   baseURL    baseURL_pro
    // 
    function detectBaseURL(){
         let installDir = '';

        if(window.hWin.location.host.indexOf('.huma-num.fr')>0 && window.hWin.location.host!=='heurist.huma-num.fr'){
            installDir = '/heurist/';
        }else{

            let script_name = window.hWin.location.pathname;
            if(script_name.endsWith('/web') || script_name.endsWith('/website')) script_name = script_name + '/'; //add last slash

            //actions for redirection https://hist/heurist/[dbname]/web/
            if(script_name.search(/\/([A-Za-z0-9_]+)\/(website|web|hml|tpl|view|edit|adm|test)\/.*/)>=0){
                installDir = script_name.replace(/\/([A-Za-z0-9_]+)\/(website|web|hml|tpl|view|edit|adm|test)\/.*/, '')+'/';
                if(installDir=='/') installDir = '/heurist/';/* to change back to '/heurist/'; */
            }else{
                //removed top folders: applications|common|search|records|
                installDir = script_name.replace(/(((\?|admin|documentation|export|hapi|hclient|hserv|import|startup|test|redirects|viewers|help|ext|external)\/.*)|(index.*|test.php))/, ""); // Upddate in utils_host.php also
            }
        }

        that.installDir = installDir; //to detect development or production version 
        if (!_baseURL_param) _baseURL_param = window.hWin.location.protocol + '//' + window.hWin.location.host + installDir;
        that.baseURL = _baseURL_param;
        
        // Detect production version; if current installDir is not '/heurist/',
        // construct a baseURL_pro pointing to a '/heurist/' path at the same domain.
        if (installDir && !installDir.endsWith('/heurist/')) {
            let proInstallDir = installDir.substring(0, installDir.lastIndexOf('/', installDir.length - 2) + 1) + 'heurist/';
            // Example: if installDir is /myapp/dev/heurist-dev/, proInstallDir might become /myapp/heurist/
            // This logic might need adjustment based on actual deployment structures.
            // A more robust way might be server-provided URLs if structures are highly variable.
            // The original logic was:
            // installDir = installDir.split('/');
            // let i = installDir.length-1;
            // while(i>0 && installDir[i]=='') i--;
            // installDir[i] = 'heurist'; // This assumes 'heurist' is always the production folder name
            // installDir = installDir.join('/');
            that.baseURL_pro = window.hWin.location.protocol + '//' + window.hWin.location.host + proInstallDir;
        } else {
            that.baseURL_pro = _baseURL_param; // If already /heurist/ or structure is simple, pro is same as base
        }        
    }
    
    /**
     * Recursively counts the number of keys in an object, including nested objects.
     * This is used to estimate the size of a request object to prevent exceeding server-side input variable limits.
     *
     * @private
     * @param {Object} data - The object whose keys are to be counted.
     * @param {number} [level=0] - Current recursion level (internal use).
     * @returns {number} The total count of keys in the object and its sub-objects.
     */
    function _getKeyCount(data, level) {
        level = level || 0;
        let _key_count = 0;
        for (let k in data) {
            if (Object.hasOwn(data, k)) { // Consider only own properties
                 _key_count++;
                if(typeof data[k] === 'object' && data[k] !== null){ // Recurse for objects
                    _key_count = _key_count + _getKeyCount(data[k], level + 1);
                }
            }
        }
        return _key_count;
    }

    /**
     * @callback callserverCallback
     * @description Callback function for handling responses from server AJAX calls.
     * @param {Object} response - The server response object.
     * @param {string} response.status - A status code from `window.hWin.ResponseStatus` indicating the outcome of the request.
     * @param {string} [response.message] - An optional message from the server, often present in case of errors or warnings.
     * @param {Object|Array|*} [response.data] - The data payload returned by the server. Its structure depends on the request made.
     * @param {Object} [response.request_code] - An object containing `{script, action}` of the original request, added client-side for context.
     * @param {*} [response.context] - Optional context passed through from the original request.
     */
    
    /**
     * @typedef {Object} HeuristRequest
     * @description Represents a generic request object sent to the Heurist server.
     * Specific properties will vary based on the action and controller.
     * @property {string} a - The specific action to be performed by the server-side controller.
     * @property {string} [db] - The name of the database context for the action. If not provided, defaults to the current HAPI database.
     * @property {string} [DBGSESSID] - Debug session ID, automatically added if `_use_debug` is true.
     */

    /**
     * Internal core function for making AJAX POST requests to Heurist server controllers.
     * Handles request preparation (e.g., adding database context, debug parameters),
     * AJAX call setup, response/error handling, and a cache relevance check mechanism.
     *
     * @private
     * @param {string} controllerName - The name of the PHP controller script in `hserv/controller/` (e.g., 'usr_info', 'record_edit').
     * @param {HeuristRequest} request - The request object containing parameters for the server.
     * @param {callserverCallback} callback - Function to be called with the server's response.
     * @param {number} [timeout_ms=0] - Optional timeout in milliseconds for the AJAX request. Default is jQuery's default (often 0, meaning no timeout).
     *                                A common Heurist value used is 120000 (120 seconds).
     * @returns {void}
     */
    function _callserver(controllerName, request, callback, timeout_ms=0) {

        _is_callserver_in_progress = true;
        
        // Cache relevance check: If not a critical/frequent action and enough time has passed,
        // check if client-side entity definitions are up-to-date before proceeding.
        if(window.hWin.HAPI4 && controllerName !=='entityScrud' && controllerName !=='usr_info' && !request.remote
            && (new Date().getTime()) - _last_check_dbcache_relevance > 7000){ // 7 seconds throttle
            _last_check_dbcache_relevance = new Date().getTime();
            
            // Avoid check if structure editor or Design panel is active, as these might be modifying definitions
            const can_check_relevance = $('div.defRecStructure').length === 0 &&
                                     $('.ui-menu6-section.ui-heurist-design').css('display') !== 'block';

            if(can_check_relevance) {
                window.hWin.HAPI4.EntityMgr.relevanceEntityData(function(){ // Assuming relevanceEntityData handles its own callback logic
                    _callserver(controllerName, request, callback, timeout_ms); // Re-call after relevance check
                });
                return; // Exit current call, will be re-issued by relevanceEntityData callback
            }
        }
        
        // Ensure database context is in the request
        if (!request.db) {
            request.db = _database;
        }
        // Remove 'notes' property to reduce traffic, if present (legacy or specific use?)
        if (request.notes) {
            request.notes = null;
        }
        // Default controller is 'index' if none specified (though controllerName is usually explicit)
        if(!controllerName){
            controllerName = 'index';
        }

        // Add debug session ID if _use_debug is true
        request.DBGSESSID = (_use_debug) ? '425944380594800002;d=1,p=0,c=1' : '425944380594800002;d=0,p=0,c=0';

        let url = that.baseURL + "hserv/controller/" + controllerName + ".php";
        
        // Check for excessive input variables (potential server-side limit)
        let input_var_count = _getKeyCount(request);
        if(input_var_count > 999){ // PHP's max_input_vars default is often 1000
            const error_message = `Request to ${controllerName} (action: ${request.a}) may exceed max_input_vars. Count: ${input_var_count}.`;
            if(that.baseURL.includes('127.0.0.1') || that.baseURL.includes('localhost')){ // Alert only in dev environments
                alert(error_message);
            }
            console.error(error_message, request);
        }

        const request_context_for_callback = { script: controllerName, action: request.a };

        let ajax_options = {
            url: url,
            type: "POST",
            data: request,
            dataType: "json",
            cache: false,
            xhrFields: {
                withCredentials: true // Important for session management
            },
            error: function (jqXHR, textStatus, errorThrown) {
                _is_callserver_in_progress = false;
                let response = (jqXHR.responseJSON && jqXHR.responseJSON.status) ?
                               jqXHR.responseJSON :
                               window.hWin.HEURIST4.util.interpretServerError(jqXHR, url, request_context_for_callback);
                if (window.hWin.HEURIST4.util.isFunction(callback)) {
                    callback(response);
                }
            },
            success: function (response, textStatus, jqXHR) {
                _is_callserver_in_progress = false;
                if (window.hWin.HEURIST4.util.isFunction(callback)) {
                    if ($.isPlainObject(response)) { // Ensure response is an object before adding properties
                        response.request_code = request_context_for_callback; // Add context for the callback
                    }
                    callback(response);
                }
                // Legacy check for forcing sys_info refresh - consider centralizing this logic if still needed
                // if(response && response.status == window.hWin.ResponseStatus.OK && response.force_refresh_sys_info) {
                // that.SystemMgr.sys_info(function(success){ /* handle success/failure of forced refresh */ });
                // }
            },
            fail: function (jqXHR, textStatus, errorThrown) { // Note: .fail is part of deferreds, error is for $.ajax
                _is_callserver_in_progress = false;
                let response = window.hWin.HEURIST4.util.interpretServerError(jqXHR, url, request_context_for_callback);
                if (window.hWin.HEURIST4.util.isFunction(callback)) {
                    callback(response);
                }
            }
        };
        
        if(timeout_ms > 0){
            ajax_options['timeout'] = timeout_ms;
        }
        
        $.ajax(ajax_options);
    }

    /**
     * Processes a server response after a record update action (e.g., save, delete).
     * It clears relevant records from the `browseRecordCache` if `response.affectedRty` is present,
     * and then triggers the `HAPI4.Event.ON_REC_UPDATE` global event.
     * Finally, it calls the provided callback function with the original response.
     *
     * @private
     * @param {Object} response - The server response object.
     * @param {string} response.status - The status code of the response (e.g., from `window.hWin.ResponseStatus`).
     * @param {string|Array<string|number>} [response.affectedRty] - A comma-separated string or an array of record type IDs
     *        that were affected by the action. This is used to invalidate specific parts of the cache.
     * @param {function(Object):void} [callback] - An optional callback function to be called after processing the event and cache.
     * @returns {void}
     */
    function _triggerRecordUpdateEvent(response, callback) {
        if (response && response.status == window.hWin.ResponseStatus.OK) {
            // $Db is an alias for window.hWin.HEURIST4.dbs, typically defined in hclient/core/utils_dbs.js
            if (typeof $Db !== 'undefined' && $Db) $Db.needUpdateRtyCount = 1; // Signal that record type counts might need update

            if (response.affectedRty && window.hWin.HEURIST4.browseRecordTargets) {
                    // Clear affected record types from the browse record cache
                    let rtys_to_clear = [];
                    if (Array.isArray(response.affectedRty)) {
                        rtys_to_clear = response.affectedRty;
                    } else if (typeof response.affectedRty === 'string') {
                        rtys_to_clear = response.affectedRty.split(',');
                    } else { // Assume single ID if not array or string
                        rtys_to_clear = [response.affectedRty];
                    }
                    rtys_to_clear.push('any'); // Always clear 'any' category as well

                    $.each(rtys_to_clear, function (i, id) {
                        const stringId = String(id); // Ensure ID is a string for object key access
                        if (window.hWin.HEURIST4.browseRecordTargets[stringId]) {
                            $.each(window.hWin.HEURIST4.browseRecordTargets[stringId], function (j, cacheKey) {
                                if (window.hWin.HEURIST4.browseRecordCache && window.hWin.HEURIST4.browseRecordCache[cacheKey]) {
                                    delete window.hWin.HEURIST4.browseRecordCache[cacheKey]; // Remove from cache
                                }
                            });
                            delete window.hWin.HEURIST4.browseRecordTargets[stringId]; // Remove target entry
                        }
                    });
            }
            window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_REC_UPDATE); // Trigger global record update event
        }
        if (window.hWin.HEURIST4.util.isFunction(callback)) {
            callback(response); // Call the original callback
        }
    }


    /**
    * Manages record-related operations such as creation, saving, deletion, duplication,
    * access control, batch updates, and various search functionalities.
    * It serves as an interface to server-side controllers like `record_edit.php`,
    * `record_batch.php`, and `record_search.php`.
    *
    * @constructor HRecordMgr
    * @returns {Object} An object with methods for record management.
    */
    function HRecordMgr() {

        /**
         * @typedef {Object} HeuristRecord
         * @description Represents the data structure for a Heurist record, typically used in save operations.
         * @property {number} id - The record ID. For new records, this might be a temporary client-side ID or absent.
         * @property {number} RecTypeID - The ID of the record type.
         * @property {number} OwnerUGrpID - The user/group ID of the record owner.
         * @property {string|number} NonOwnerVisibility - Visibility setting for non-owners (e.g., 'P', 'R'). Specific values depend on system config.
         * @property {boolean} [AddedByImport] - Flag indicating if the record was added via an import process.
         * @property {string} [url] - A primary URL associated with the record.
         * @property {boolean} [FlagTemporary] - Flag indicating if the record is temporary (e.g., newly created on client, not yet saved).
         * @property {HeuristDetails} details - An object containing the record's detailed information.
         */

        /**
         * @typedef {Object<string, Object<string, string|number|Object>>} HeuristDetails
         * @description An object where each key is a detail type identifier (e.g., "t1" for a name field, "d23" for a custom field)
         * and the value is another object. This inner object's keys are detail instance IDs (or new temporary IDs like "n1", "n2")
         * and values are the actual data for that detail instance.
         * Example: `details: { "t1": { "12345": "Record Title" }, "d50": { "n1": "Some custom data" } }`
         */

        let that = {

            /**
            * Creates a new temporary record on the server.
            *
            * @param {HeuristRequest} request - The request object.
            * @param {string|number} [request.rt] - Optional: The record type ID for the new record.
            * @param {string|number} [request.ro] - Optional: The owner user/group ID for the new record.
            * @param {string|number} [request.rv] - Optional: The visibility setting for the new record.
            * @param {callserverCallback} callback - Callback function. The response `data` is expected to be an `HRecordSet`-compatible object
            *                                      for the newly created temporary record.
            * @returns {void}
            */
            addRecord: function (request, callback) {
                if (request) {
                    request.a = 'a'; // 'a' for add action
                } else {
                    request = { a: 'a' };
                }
                _callserver('record_edit', request, callback);
            },

            /**
             * Saves a record's data to the server. If the record is new (temporary), this action makes it permanent.
             * Details and visibility information are encoded before sending.
             * Triggers `ON_REC_UPDATE` event via `_triggerRecordUpdateEvent` on success.
             *
             * @param {HeuristRequest} request - The request object. Action `a` will be set to 's' (save).
             * @param {HeuristRecord} request.record - The record data to be saved. See {@link HeuristRecord}.
             * @param {callserverCallback} callback - Callback function. The response `data` may contain updated record information or status.
             * @returns {void}
             */
            saveRecord: function (request, callback) {
                if (request) request.a = 's'; // 's' for save action
                
                // Determine encoding type from sysinfo, default to JSON (type 3)
                let encode_type = (window.hWin.HAPI4.sysinfo && window.hWin.HAPI4.sysinfo['need_encode'])
                                  ? window.hWin.HAPI4.sysinfo['need_encode']
                                  : 3;

                // Encode 'details' and 'details_visibility' fields of the record object within the request.
                // Assumes request.record contains these fields.
                if (request.record) { // Check if request.record exists
                    let dataToEncode = { details: request.record.details, details_visibility: request.record.details_visibility };
                    window.hWin.HEURIST4.util.encodeRequest(dataToEncode, ['details','details_visibility'], encode_type);
                    request.record.details = dataToEncode.details; // Assign encoded data back
                    request.record.details_visibility = dataToEncode.details_visibility;
                }
                
                _callserver('record_edit', request, function (response) { _triggerRecordUpdateEvent(response, callback); });
            },

            /**
             * Saves multiple records in a batch operation.
             * Triggers `ON_REC_UPDATE` event via `_triggerRecordUpdateEvent` on success.
             * 
             * @param {HeuristRequest} request - The request object. Action `a` will be set to 'batch_save'.
             * @param {Array<HeuristRecord>} request.records - An array of record objects (see {@link HeuristRecord}) to be saved.
             * @param {callserverCallback} callback - Callback function. The response `data` typically includes IDs of saved/updated records.
             * @returns {void}
             */
            batchSaveRecords: function (request, callback) {
                if (request) request.a = 'batch_save';
                // Note: Encoding for each record in request.records might be needed here if not handled server-side for batch.
                // The current `encodeRequest` utility might need adaptation for an array of records.
                _callserver('record_edit', request, function (response) { _triggerRecordUpdateEvent(response, callback); });
            },

            /**
             * Duplicates an existing record on the server.
             * Triggers `ON_REC_UPDATE` event via `_triggerRecordUpdateEvent` on success.
             *
             * @param {HeuristRequest} request - The request object.
             * @param {number} request.id - The ID of the record to duplicate.
             * @param {callserverCallback} callback - Callback function. Response `data` typically contains info about the new duplicated record.
             * @returns {void}
             */
            duplicate: function (request, callback) {
                if (request) request.a = 'duplicate';
                _callserver('record_edit', request, function (response) { _triggerRecordUpdateEvent(response, callback); });
            },

            /**
             * Sets ownership and visibility for one or more records.
             *
             * @param {HeuristRequest} request - The request object.
             * @param {Array<number>} request.ids - An array of record IDs to update.
             * @param {number} request.OwnerUGrpID - The user/group ID to set as the new owner.
             * @param {string|number} request.NonOwnerVisibility - The visibility setting to apply.
             * @param {callserverCallback} callback - Callback function.
             * @returns {void}
             */
            access: function (request, callback) {
                if (request) request.a = 'access';
                _callserver('record_edit', request, callback);
            },

            /**
             * Increments the value of a specified numeric detail field for a given record type.
             * This is often used for generating sequential numbers or counters.
             *
             * @param {number} rtyID - The record type ID for which the detail field's value is to be incremented.
             * @param {number} dtyID - The detail type ID of the numeric field to increment.
             * @param {callserverCallback} callback - Callback function. Response `data` contains the new incremented value.
             * @returns {void}
             */
            increment: function (rtyID, dtyID, callback) {
                let request = { a: 'increment', rtyID: rtyID, dtyID: dtyID };
                _callserver('record_edit', request, callback);
            },

            /**
            * Deletes one or more records from the database.
            * Triggers `ON_REC_UPDATE` event via `_triggerRecordUpdateEvent` on success.
            *
            * @param {HeuristRequest} request - The request object. Action `a` will be set to 'd' (delete).
            * @param {Array<number>} request.ids - An array of record IDs to be deleted.
            * @param {callserverCallback} callback - Callback function.
            * @returns {void}
            */
            remove: function (request, callback) {
                if (request) request.a = 'd'; // 'd' for delete
                _callserver('record_edit', request, function (response) { _triggerRecordUpdateEvent(response, callback); });
            },

            /**
            * Performs batch updates (add, replace, or delete) on details of multiple records.
            *
            * @param {HeuristRequest} request - The request object.
            * @param {('add'|'replace'|'delete')} request.a - The batch action to perform on details.
            * @param {Array<number>} request.recIDs - An array of record IDs to be processed.
            * @param {number} [request.rtyID] - Optional: Filter records by this record type ID.
            * @param {number} request.dtyID  - The detail type ID of the field to be modified.
            * @param {*} [request.val] - The value to add/replace (used when `request.a` is 'add' or 'replace').
            *                              Can be a string, number, or object depending on the detail type.
            * @param {string} [request.geo] - Geo-spatial value (e.g., WKT) if adding/replacing a geo field.
            * @param {number} [request.ulfID] - Uploaded file ID if adding/replacing a file link.
            * @param {*} [request.sVal] - Search value: The existing value to find for 'replace' or 'delete' operations.
            * @param {*} [request.rVal] - Replace value: The new value to set (must be provided if `request.a` is 'replace').
            * @param {0|1} [request.tag=0] - If `1`, adds a system tag to mark processed records.
            * @param {callserverCallback} callback - Callback function.
            * @returns {void}
            */
            batch_details: function (request, callback) {
                // Ensure values that might contain special characters are encoded
                window.hWin.HEURIST4.util.encodeRequest(request, ['rVal', 'val']);
                _callserver('record_batch', request, function (response) { _triggerRecordUpdateEvent(response, callback); });
            },

            //@TODO - need to implement queue for record_search, otherwise sometimes we get conflict on simultaneous requests            

            /**
            * Performs a record search. This version of search is typically used when global events
            * (`ON_REC_SEARCHSTART`, `ON_REC_SEARCH_FINISH`) need to be triggered, and it often interacts
            * with global state like `HAPI4.currentRecordset`.
            * For direct searches without global event side effects, `HRecordSearch.doSearchWithCallback` or `HRecordMgr.search2` might be preferred.
            *
            * @param {HeuristRequest} request - The search request object.
            * @param {string} request.q - The query string.
            * @param {('a'|'b')} [request.w='a'] - Search domain: 'a' for all records, 'b' for bookmarks.
            * @param {string} [request.f] - Optional comma-separated list of fields/details to fetch (e.g., 'detail,map,structure,tags').
            * @param {number} [request.limit] - Number of records to return per page/batch.
            * @param {number} [request.o] - Offset for pagination.
            * @param {string} [request.verify_credentials] - Internal flag, should not be set by callers directly.
            * @param {callserverCallback|Document} callback - If a function, it's treated as a direct callback.
            *        If a Document object, global events `ON_REC_SEARCHSTART` and `ON_REC_SEARCH_FINISH` are triggered on this document.
            *        The response data for the `ON_REC_SEARCH_FINISH` event will be an `HRecordSet`.
            * @returns {void}
            */
            search: function (request, callback) {
                // If not in publish mode and credentials haven't been marked as 'ok' for this request, verify them first.
                if (!window.hWin.HAPI4.is_publish_mode && request['verify_credentials'] !== 'ok') {
                    window.hWin.HAPI4.SystemMgr.verify_credentials(function () {
                        request['verify_credentials'] = 'ok'; // Mark as verified for this request flow
                        that.search(request, callback);    // Re-call search after verification
                    }, 0); // '0' typically means check if logged in
                    return;
                }

                // Clean up internal verification flag
                if (request['verify_credentials']) {
                    delete request['verify_credentials'];
                }

                // Handle cases where 'callback' is actually a document for event triggering
                if (!window.hWin.HEURIST4.util.isFunction(callback)) {
                    let targetDocument = callback; // Assume 'callback' is a document object

                    if (!request.increment || window.hWin.HEURIST4.util.isnull(request.id)) {
                        request.id = window.hWin.HEURIST4.util.random(); // Assign a random ID if needed
                    }

                    if (targetDocument && typeof targetDocument.trigger === 'function' && !request.increment) {
                        targetDocument.trigger(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART, [request]);
                    }

                    // Replace the document object with an actual callback function for _callserver
                    callback = function (response) {
                        let resdata = null;
                        if (response.status == window.hWin.ResponseStatus.OK) {
                            resdata = new HRecordSet(response.data);
                        } else {
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                        if (targetDocument && typeof targetDocument.trigger === 'function') {
                            // Ensure resdata is at least an empty HRecordSet for consistency if it's null
                            if (resdata === null) resdata = new HRecordSet({ records: [], reccount: 0 });
                            targetDocument.trigger(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH, { resultset: resdata, request: request });
                        }
                    };
                }

                window.hWin.HEURIST4.util.encodeRequest(request, ['q']); // Encode query string
                _callserver('record_search', request, callback);
            },

            /**
            * Performs a standard record search without triggering global search events.
            * This is a more direct way to search if event side-effects are not needed.
            *
            * @param {HeuristRequest} request - The search request object (see {@link HRecordMgr#search} for common properties like `q`, `w`, `f`, `limit`, `o`).
            * @param {callserverCallback} callback - Callback function to handle the server response.
            * @returns {void}
            */
            search2: function (request, callback) {
                window.hWin.HEURIST4.util.encodeRequest(request, ['q']);
                _callserver('record_search', request, callback);
            },
            
            /**
            * Performs a search and prepares results in a specific output format (e.g., for export or specialized views).
            * Uses the 'record_output' server controller.
            *
            * @param {HeuristRequest} request - The request object, defining the query and desired output format.
            * @param {string} request.format - The desired output format (e.g., 'csv', 'json', 'xml').
            * @param {callserverCallback} callback - Callback function. Response `data` contains the formatted results.
            * @returns {void}
            */
            search_new: function (request, callback) {
                _callserver('record_output', request, callback);
            },

            /**
            * Looks up information from an external service.
            * Uses the 'record_lookup' server controller.
            *
            * @param {HeuristRequest} request - The request object, specifying the service and query parameters.
            * @param {string} request.service - Identifier for the external service to query.
            * @param {callserverCallback} callback - Callback function. Response `data` contains results from the external service.
            * @returns {void}
            */
            lookup_external_service: function (request, callback) {
                _callserver('record_lookup', request, callback);
            },

            /**
            * Loads KML data associated with a record and converts it to GeoJSON format.
            * The KML can be a file linked to the record or an embedded KML snippet.
            *
            * @param {HeuristRequest} request - The request object.
            * @param {number} request.recID - The ID of the record containing the KML data.
            * @param {boolean} [request.simplify] - If true, attempts to simplify the geometry to reduce points.
            * @param {callserverCallback} callback - Callback function. Response `data` contains the GeoJSON.
            * @returns {void}
            */
            load_kml_as_geojson: function (request, callback) {
                request['format'] = 'geojson';
                _callserver('record_map_source', request, callback);
            },

            /**
            * Loads SHP (Shapefile) data associated with a record and converts it to GeoJSON format.
            *
            * @param {HeuristRequest} request - The request object.
            * @param {number} request.recID - The ID of the record containing the link to the SHP file.
            * @param {boolean} [request.simplify] - If true, attempts to simplify the geometry.
            * @param {callserverCallback} callback - Callback function. Response `data` contains the GeoJSON.
            * @returns {void}
            */
            load_shp_as_geojson: function (request, callback) {
                request['format'] = 'geojson'; // or 'wkt' (Well-Known Text)
                request['api'] = 0; // Indicates it's not an API request (internal flag for server)
                _callserver('record_shp', request, callback);
            },

            /**
            * Searches for relationships among a given set of records.
            *
            * @param {HeuristRequest} request - The request object. Action `a` will be set to 'related'.
            * @param {Array<number>|string} request.ids - An array or comma-separated string of record IDs to analyze for relationships.
            * @param {callserverCallback|Document} callback - If a function, direct callback. If Document, triggers events.
            *        Response `data` contains information about the relationships found.
            * @returns {void}
            */
            search_related: function (request, callback) {
                if (request && !request.a) request.a = 'related';
                _callserver('record_search', request, callback);
            },

            /**
            * Retrieves aggregated data for specified record types and detail types.
            * This can include min/max values, distinct value counts, or matching counts based on criteria.
            *
            * @param {HeuristRequest} request - The request object specifying aggregation parameters.
            * @param {string|number} request.rt - Record type ID(s).
            * @param {string|number} request.dt - Detail type ID(s).
            * @param {string} request.agg - Type of aggregation (e.g., 'min', 'max', 'count', 'distinct').
            * @param {callserverCallback} callback - Callback function. Response `data` contains the aggregated results.
            * @returns {void}
            */
            get_aggregations: function (request, callback) {
                _callserver('record_search', request, callback);
            },

            /**
            * Retrieves facet data for faceted search implementations.
            * Facets are typically counts of records matching certain criteria for different fields.
            *
            * @param {HeuristRequest} request - The request object. Action `a` will be set to 'getfacets'.
            * @param {string} [request.q] - The base query string to apply faceting to.
            * @param {string} [request.count_query] - Potentially a separate query for counts.
            * @param {Array<Object>} request.facets - Configuration for the facets to retrieve.
            * @param {callserverCallback} callback - Callback function. Response `data` contains the facet counts.
            * @returns {void}
            */
            get_facets: function (request, callback) {
                if (request && !request.a) request.a = 'getfacets';
                window.hWin.HEURIST4.util.encodeRequest(request, ['q', 'count_query']);
                _callserver('record_search', request, callback);
            },

            /**
            * Retrieves data for generating a date histogram, typically for visualizing record distribution over time.
            *
            * @param {HeuristRequest} request - The request object. Action `a` will be set to 'gethistogramdata'.
            * @param {string|number} request.rtyID - Record type ID to get histogram data for.
            * @param {string|number} request.dtyID - Detail type ID (must be a date field) to use for the histogram.
            * @param {callserverCallback} callback - Callback function. Response `data` contains histogram intervals and counts.
            * @returns {void}
            */
            get_date_histogram_data: function (request, callback) {
                if (request && !request.a) request.a = 'gethistogramdata';
                _callserver('record_search', request, callback);
            },

            /**
            * Retrieves record IDs that match specified criteria based on detail fields and record types.
            *
            * @param {HeuristRequest} request - The request object. Action `a` will be set to 'getrecordids'.
            * @param {Array<number>|string} request.rtyIDs - Record type ID(s) to filter by.
            * @param {Object} request.match - Criteria for matching detail fields.
            * @param {callserverCallback} callback - Callback function. Response `data` contains an array of matching record IDs.
            * @returns {void}
            */
            get_record_ids: function (request, callback) {
                if (request && !request.a) request.a = 'getrecordids';
                _callserver('record_search', request, callback);
            },

            /**
            * Retrieves full information for a particular record.
            * @todo This method's controller `record_get` might not exist or might be part of another controller.
            *       Verify server-side implementation.
            * @param {HeuristRequest} request - The request object.
            * @param {number} request.id - The ID of the record to retrieve.
            * @param {callserverCallback} callback - Callback function.
            * @returns {void}
            */
            get: function (request, callback) {
                _callserver('record_get', request, callback); // Ensure 'record_get' controller exists and handles this.
            }
        }
        return that;
    }

    /**
    * Manages entities within Heurist, primarily focusing on database definitions
    * (like record types, detail types, terms) and user/group information.
    * It handles loading entity configurations, fetching and caching entity data,
    * and providing methods for SCReUD-like (Search, Config, Read, Update, Delete) operations
    * via the `entityScrud.php` server controller and specific entity controllers (e.g., `dbSysUsers.php`).
    *
    * @constructor HEntityMgr
    * @returns {Object} An object with methods for entity management.
    */
    function HEntityMgr() {
        /**
         * @private
         * @type {Object<string, Object>}
         * @description Cache for entity configurations, keyed by entity name.
         */
        let entity_configs = {};
        /**
         * @private
         * @type {Object<string, HRecordSet>}
         * @description Cache for entity data (collections of records/items), keyed by entity name.
         *              Each entry is an `HRecordSet` instance.
         */
        let entity_data = {};
        /**
         * @private
         * @type {number}
         * @description Timestamp of the last known database definition update on the client.
         *              Used for cache relevance checks with the server.
         */
        let entity_timestamp = 0;
        /**
         * @private
         * @type {number|null}
         * @description Timeout ID for a message shown during entity data refresh.
         */
        let _msgOnRefreshEntityData = 0;

        let that = {

            /**
             * Loads the configuration for a specific entity type (e.g., 'defRecTypes', 'sysUsers').
             * Configuration includes fields, display properties, etc.
             * Results are cached. If 'records' entity is requested, it uses `window.hWin.entityRecordCfg`.
             *
             * @param {string} entityName - The name of the entity for which to load the configuration.
             * @param {function(Object): void} callback - Function called with the entity configuration object.
             * @returns {Object|undefined} The entity configuration object if already cached, otherwise undefined (async load).
             */
            getEntityConfig: function (entityName, callback) {
                if (entity_configs[entityName]) { // Check cache first
                    if (window.hWin.HEURIST4.util.isFunction(callback)) {
                        callback(entity_configs[entityName]);
                    }
                    return entity_configs[entityName];
                } else if (entityName === 'records' && window.hWin.entityRecordCfg) { // Special case for 'records'
                     entity_configs[entityName] = window.hWin.entityRecordCfg;
                     if (window.hWin.HEURIST4.util.isFunction(callback)) {
                        callback(entity_configs[entityName]);
                    }
                    return entity_configs[entityName];
                } else {
                    // Fetch from server
                    _callserver('entityScrud', { a: 'config', 'entity': entityName, 'locale': window.hWin.HAPI4.getLocale() },
                        function (response) {
                            if (response.status == window.hWin.ResponseStatus.OK && response.data) {
                                entity_configs[response.data.entityName] = response.data;
                                window.hWin.HAPI4.EntityMgr.resolveFields(response.data.entityName); // Post-process to find key/title fields
                                if (window.hWin.HEURIST4.util.isFunction(callback)) {
                                   callback(entity_configs[response.data.entityName]);
                                }
                            } else {
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                                if (window.hWin.HEURIST4.util.isFunction(callback)) {
                                   callback(null); // Indicate failure
                                }
                            }
                        }
                    );
                }
                return undefined; // Async operation
            },

            /**
             * Clears cached data for a specific entity, forcing a reload on the next request.
             *
             * @param {string} entityName - The name of the entity whose cache should be cleared.
             * @returns {void}
             */
            clearEntityData: function (entityName) {
                if (entity_data[entityName] && !$.isEmptyObject(entity_data[entityName])) { // Check if not already empty
                    entity_data[entityName] = {}; // Effectively clears HRecordSet or marks for reload
                }
            },

            /**
             * Clears all cached entity data or data for a specific entity.
             * This forces a full reload of definitions from the server on subsequent requests.
             *
             * @param {string} [entityName] - Optional. If provided, clears data only for this entity.
             *                                Otherwise, clears all cached entity data.
             * @returns {void}
             */
            emptyEntityData: function (entityName) {
                if (entityName && typeof entityName === 'string') {
                    entity_data[entityName] = {};
                } else { // No entityName or invalid, clear all
                    entity_data = {};
                    entity_configs = {}; // Also clear configs if clearing all data
                    entity_timestamp = 0; // Reset timestamp
                }
            },

            /**
             * Resolves and stores the key field and title field for an entity based on its configuration.
             * Modifies the cached entity configuration (`entity_configs[entityName]`) by adding
             * `keyField` and `titleField` properties.
             *
             * @private
             * @param {string} entityName - The name of the entity whose configuration is to be processed.
             * @returns {void}
             */
            resolveFields: function (entityName) {
                let entity_cfg = entity_configs[entityName];
                if (entity_cfg && entity_cfg.fields) { // Ensure config and fields array exist
                    function __findFieldsRecursive(fields) { // Renamed for clarity
                        for (let field_key in fields) { // Iterate over field properties/indices
                            if (Object.hasOwn(fields, field_key)) {
                                const field = fields[field_key];
                                if (field.children && Array.isArray(field.children)) { // Check if it's a group with children
                                    __findFieldsRecursive(field.children);
                                } else {
                                    if (field.keyField === true) {
                                        entity_cfg.keyField = field.dtID;
                                    }
                                    if (field.titleField === true) {
                                        entity_cfg.titleField = field.dtID;
                                    }
                                }
                            }
                        }
                    }
                    __findFieldsRecursive(entity_cfg.fields);
                }
            },

            /**
             * Creates an index of record structure definitions (`defRecStructure`) for quick lookup.
             * The index is structured by record type ID (`rty_ID`) and then by detail type ID (`dty_ID`).
             * Stores the resulting index in `entity_data['rst_Index']`.
             * This is typically called after `defRecStructure` data is loaded or updated.
             *
             * @returns {void}
             */
            createRstIndex: function () {
                let rst_index = {};
                let recset = entity_data['defRecStructure'];

                if (!recset || !window.hWin.HEURIST4.util.isRecordSet(recset)) {
                    console.warn("createRstIndex: defRecStructure is not loaded or not a RecordSet.");
                    return;
                }

                recset.each2(function (rst_ID_from_key, record_data) { // rst_ID_from_key is the primary key of defRecStructure table
                    let rty_ID = record_data['rst_RecTypeID'];
                    let dty_ID = record_data['rst_DetailTypeID']; // This is the field/detail ID itself

                    if (!rst_index[rty_ID]) rst_index[rty_ID] = {};
                    // Store the record_data using dty_ID as key, ensure it's not overwritten if multiple rst_IDs map to same rty/dty pair (should not happen in valid config)
                    if (!rst_index[rty_ID][dty_ID]) {
                        // Add rst_ID (primary key of the structure definition) to the record data for reference if needed.
                        // However, the original code uses dty_ID as rst_ID here, which might be intentional if dty_ID is unique key in this context.
                        // Clarifying: rst_ID is the primary key of the defRecStructure. dty_ID is the ID of the detail type.
                        // The original code stores the 'record' (which is record_data) using dty_ID.
                        // It also adds a property 'rst_ID' to record_data with value of dty_ID, which is confusing.
                        // Let's assume record_data already has its own unique ID (rst_ID_from_key).
                        let field_definition_copy = { ...record_data }; // Shallow copy
                        // field_definition_copy.original_rst_ID = rst_ID_from_key; // Preserve original PK if needed
                        rst_index[rty_ID][dty_ID] = field_definition_copy;
                    }
                });

                // Convert each rty_ID's collection of fields into an HRecordSet for consistent API
                for (let rty_ID_key in rst_index) {
                    if (Object.hasOwn(rst_index, rty_ID_key)) {
                        let fields_for_rty = rst_index[rty_ID_key];
                        let field_ids_order = Object.keys(fields_for_rty);
                        rst_index[rty_ID_key] = new HRecordSet({
                            entityName: 'defRecStructure_fieldsForRTY_' + rty_ID_key, // More descriptive name
                            records: fields_for_rty, // Object where keys are dty_IDs
                            order: field_ids_order,  // Array of dty_IDs
                            count: field_ids_order.length,
                            keyField: 'dty_ID' // Assuming dty_ID is the key within this subset
                        });
                    }
                }
                entity_data['rst_Index'] = rst_index;
                // Note: The comment "see $Db.rst_links" implies further processing or usage elsewhere.
            },

            /**
             * Checks if the client-side cached database definitions are still relevant (up-to-date)
             * by comparing the local `entity_timestamp` with the server.
             * If definitions are outdated or a check is forced, it calls `refreshEntityData`.
             *
             * @param {function(HEntityMgr, boolean): void} callback - Called after the check.
             *        Receives the HEntityMgr instance and a boolean indicating if data is up-to-date (or refresh succeeded).
             * @param {function(Object): void} [errorCallback=null] - Optional callback for errors during refresh.
             * @returns {void}
             */
            relevanceEntityData: function (callback, errorCallback = null) {
                if(entity_timestamp > 0){ // Only refresh if we have a previous timestamp
                    window.hWin.HAPI4.EntityMgr.refreshEntityData('relevance', callback, errorCallback);
                } else if (window.hWin.HEURIST4.util.isFunction(callback)) {
                    // No timestamp means definitions likely never loaded or cleared; treat as "up-to-date" until first load.
                    // Or, could force a full load here: refreshEntityData('all', callback, errorCallback)
                    callback(this, true);
                }
            },
            
            /**
             * Refreshes entity data from the server. Can refresh all definitions, or check relevance.
             * Shows a temporary "Database definitions refresh" message.
             *
             * @param {string} entityNameToRefresh - Entity name to refresh. Can be 'all', 'relevance', or a specific entity name.
             *                                     'relevance' checks timestamp and refreshes all if outdated.
             *                                     'all' forces refresh of all core definitions.
             * @param {function(HEntityMgr, boolean): void} callback - Called after refresh. Receives HEntityMgr and success flag.
             * @param {function(Object): void} [errorCallback=null] - Optional error callback.
             * @returns {void}
             */
            refreshEntityData: function (entityNameToRefresh, callback, errorCallback = null) {
                let params = { a: 'structure', 'details': 'full'}; // Default action and detail level
                params['entity'] = entityNameToRefresh; // e.g., 'all', 'relevance', 'defRecTypes'
                params['timestamp'] = entity_timestamp; // Client's current definition timestamp

                const s_time = new Date().getTime() / 1000; // For logging load time

                // Clear previous message timeout and set a new one
                if(_msgOnRefreshEntityData) clearTimeout(_msgOnRefreshEntityData);
                _msgOnRefreshEntityData = setTimeout(function(){
                    if (window.hWin.HEURIST4 && window.hWin.HEURIST4.msg) { // Ensure msg utility is available
                        window.hWin.HEURIST4.msg.showMsgFlash('Database definitions refresh', false, null,
                            { position: {my: 'left+100 top+100', at: 'left top', of: $(document)} });
                    }
                }, 1000); // Show message if refresh takes more than 1 second
                 
                _callserver('entityScrud', params,
                    function (response) {
                        if(_msgOnRefreshEntityData) clearTimeout(_msgOnRefreshEntityData);
                        _msgOnRefreshEntityData = 0;
                        if (window.hWin.HEURIST4 && window.hWin.HEURIST4.msg) window.hWin.HEURIST4.msg.closeMsgFlash();
                        
                        let success = false;
                        if (response && response['uptodate']) { // Server indicates client definitions are up-to-date
                            entity_timestamp = response['uptodate']; // Update timestamp
                            success = true;
                        } else if (response && response.status == window.hWin.ResponseStatus.OK) {
                            // Data received, process it
                            const fin_time = new Date().getTime() / 1000;
                            console.log(`Heurist definitions '${entityNameToRefresh}' loaded: ${(fin_time-s_time).toFixed(3)} sec`);
                            
                            // Data might be in response.data or directly in response if 'defRecTypes' is a key (older format?)
                            const dbdefs = (response['defRecTypes'] ? response : (response['data'] ? response['data'] : null));
                            if (dbdefs) {
                                for (let en_name in dbdefs) { // entityName here is key from dbdefs
                                    if (Object.hasOwn(dbdefs, en_name)) {
                                         // Pass dbdefs itself which contains multiple entities, or dbdefs[en_name] if it's structured per entity
                                        window.hWin.HAPI4.EntityMgr.setEntityData(en_name, dbdefs);
                                    }
                                }
                                success = true;
                            } else {
                                console.warn("refreshEntityData: No definition data in response for", entityNameToRefresh, response);
                            }
                        } else { // Error or unexpected response
                            console.error('Error refreshing entity data:', response);
                        }

                        if (window.hWin.HEURIST4.util.isFunction(callback)) {
                             callback(this, success); // Pass HEntityMgr instance and success status
                        }
                        if (!success && window.hWin.HEURIST4.util.isFunction(errorCallback)) {
                            errorCallback.call(this, response);
                        } else if (!success) {
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    }
                );
            },

            /**
             * Retrieves data for a specific entity. Data is fetched from the server if not cached or if `force_reload` is true.
             * Applicable for entities with a relatively small number of records (e.g., < ~1500).
             *
             * @param {string} entityName - The name of the entity (e.g., 'defRecTypes', 'sysUsers').
             *                              Can also be an array of names or "all" (though "all" might be handled by `refreshEntityData`).
             * @param {boolean} [force_reload=false] - If true, fetches data from server even if already cached.
             * @param {function(HRecordSet|null): void} callback - Function called with the `HRecordSet` for the entity, or null on error.
             * @returns {HRecordSet|undefined} The `HRecordSet` if data is already cached and callback is not provided.
             *                                 Otherwise, undefined (as data is fetched asynchronously).
             */
            getEntityData: function (entityName, force_reload, callback) {
                if (($.isEmptyObject(entity_data[entityName]) || force_reload === true) && typeof entityName === 'string') {
                    let detailLevel = 'list'; // Default detail level
                    if (entityName === 'defRecStructure' /*|| entityName === 'defTerms'*/) { // defTerms can be large
                        detailLevel = 'full';
                    }
                    
                    _callserver('entityScrud', { a: 'search', 'entity': entityName, 'details': detailLevel },
                        function (response) {
                            if (response.status == window.hWin.ResponseStatus.OK && response.data) {
                                entity_data[response.data.entityName] = new HRecordSet(response.data);
                                if (response.data.entityName === 'defRecStructure') {
                                    window.hWin.HAPI4.EntityMgr.createRstIndex(); // Create index after loading structure
                                }
                                if (window.hWin.HEURIST4.util.isFunction(callback)) {
                                    callback(entity_data[response.data.entityName]);
                                }
                            } else {
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                                if (window.hWin.HEURIST4.util.isFunction(callback)) {
                                    callback(null); // Indicate error
                                }
                            }
                        }
                    );
                } else if (entity_data[entityName]) { // Data is in cache
                    if (window.hWin.HEURIST4.util.isFunction(callback)) {
                        callback(entity_data[entityName]);
                    } else {
                        return entity_data[entityName]; // Synchronous return if no callback
                    }
                } else if (window.hWin.HEURIST4.util.isFunction(callback)){
                     // Entity name might be invalid or not yet loaded, and no force_reload
                     callback(null);
                }
                return undefined; // Async path or error
            },

            /**
             * Directly accesses cached entity data without fetching from the server.
             *
             * @param {string} entityName - The name of the entity.
             * @returns {HRecordSet|undefined} The cached `HRecordSet` if available, otherwise undefined.
             */
            getEntityData2: function (entityName) {
                return entity_data[entityName];
            },

            /**
             * Sets or updates cached data for an entity.
             * If `entityName` is 'timestamp', it updates the client's `entity_timestamp`.
             * If `data` is an `HRecordSet`, it's stored directly. Otherwise, `data` is assumed
             * to be a server response object, and an `HRecordSet` is created from `data[entityName]`.
             * Special handling for 'defRecStructure' (calls `createRstIndex`) and 'defTerms' (stores links/icons).
             * Also updates entity configuration if present in `data[entityName]['config']`.
             *
             * @param {string} entityName - The name of the entity, or 'timestamp'.
             * @param {HRecordSet|Object} data - The data to set. Can be an `HRecordSet` instance or a raw server response object
             *                                   containing entity data and potentially configuration.
             * @returns {void}
             */
            setEntityData: function (entityName, data) {
                if(entityName === 'timestamp' && data && typeof data[entityName] === 'number'){
                    entity_timestamp = data[entityName]; // Update client's definition timestamp
                } else if (window.hWin.HEURIST4.util.isRecordSet(data)) {
                    entity_data[entityName] = data; // Store pre-existing HRecordSet
                } else if (data && data[entityName]) { // Assume data is a server response structure
                    entity_data[entityName] = new HRecordSet(data[entityName]);

                    if (entityName === 'defRecStructure') {
                        window.hWin.HAPI4.EntityMgr.createRstIndex();
                    } else if (entityName === 'defTerms') {
                        // Store associated links and icons if provided with terms data
                        if (data[entityName]['trm_Links']) entity_data['trm_Links'] = data[entityName]['trm_Links'];
                        if (data[entityName]['trm_Icons']) entity_data['trm_Icons'] = data[entityName]['trm_Icons'] ?? [];
                    }

                    // If configuration is bundled with data, update cached config
                    if (data[entityName]['config']) {
                        entity_configs[entityName] = data[entityName]['config'];
                        window.hWin.HAPI4.EntityMgr.resolveFields(entityName); // Re-resolve key/title fields
                    }
                } else {
                     console.warn("setEntityData: Invalid data format for entity", entityName, data);
                }
            },

            /**
             * Makes a generic request to the `entityScrud.php` controller.
             *
             * @param {HeuristRequest} request - The request object. Must include `entity` and action `a`.
             * @param {string} request.entity - The name of the target entity.
             * @param {callserverCallback} callback - Function to handle the server response.
             * @returns {void}
             */
            doRequest: function (request, callback) {
                if (!request || !request.entity || !request.a) {
                    console.error("HEntityMgr.doRequest: 'entity' and 'a' (action) are required in the request.", request);
                    if (typeof callback === 'function') {
                        callback({status: window.hWin.ResponseStatus.INVALID_REQUEST, message: "Missing entity or action in request."});
                    }
                    return;
                }
                request['request_id'] = window.hWin.HEURIST4.util.random(); // Add unique ID for tracking
                _callserver('entityScrud', request, callback);
            },

            /**
             * Retrieves display titles for a list of record IDs of a given entity type.
             * Attempts to use cached data first; otherwise, fetches from the server.
             *
             * @param {string} entityName - The name of the entity.
             * @param {Array<number|string>|number|string} recIDs - A single ID or an array of record IDs.
             * @param {function(Array<string>): void} callback - Function called with an array of titles corresponding to `recIDs`.
             *                                                If an ID is not found or an error occurs, the original ID might be returned in its place.
             * @returns {void}
             */
            getTitlesByIds: function (entityName, recIDs, callback) {
                let titles = [];
                const idsToFetch = [];
                const originalIds = Array.isArray(recIDs) ? recIDs : [recIDs]; // Ensure array

                if (entity_data[entityName] && entity_configs[entityName] && entity_configs[entityName].titleField) {
                    const ecfg = entity_configs[entityName];
                    const edata = entity_data[entityName];
                    const title_map = {}; // To map original ID to its fetched title

                    for (const id of originalIds) {
                        const record = edata.getById(id);
                        if (record && record[ecfg.titleField] !== undefined) {
                            title_map[id] = record[ecfg.titleField];
                        } else {
                            idsToFetch.push(id); // Not found in cache or title field missing
                            title_map[id] = String(id); // Default to ID if not found after fetch
                        }
                    }

                    if (idsToFetch.length === 0) { // All found in cache
                        titles = originalIds.map(id => title_map[id]);
                        if (typeof callback === 'function') callback.call(this, titles);
                        return;
                    }
                } else if (!entity_configs[entityName]) { // Config not loaded, load it first
                    window.hWin.HAPI4.EntityMgr.getEntityConfig(entityName, () => {
                        // Retry after config is loaded (this will now hit the cache or fetch if still needed)
                        window.hWin.HAPI4.EntityMgr.getTitlesByIds(entityName, recIDs, callback);
                    });
                    return;
                } else { // Data not loaded for entity, mark all for fetch
                     idsToFetch.push(...originalIds);
                }

                // Fetch titles for IDs not found in cache
                if (idsToFetch.length > 0) {
                    let request = {
                        recID: idsToFetch, // Server expects 'recID' for list of IDs
                        a: 'title',
                        entity: entityName,
                        request_id: window.hWin.HEURIST4.util.random()
                    };
                    window.hWin.HAPI4.EntityMgr.doRequest(request, (response) => {
                        const fetched_titles_map = {};
                        if (response.status === window.hWin.ResponseStatus.OK && response.data) {
                            // Assuming response.data is an object mapping ID to Title for fetched IDs
                            Object.assign(fetched_titles_map, response.data);
                        }
                        // Reconstruct titles array in original order, using fetched titles or defaults
                        titles = originalIds.map(id => fetched_titles_map[id] || String(id));
                        if (typeof callback === 'function') callback.call(this, titles);
                    });
                }
            },

            /**
             * Retrieves translations for definitions (e.g., record type names, field labels)
             * from the `defTranslations` entity (or equivalent mechanism).
             * Results are cached.
             *
             * @param {string} entityName - The primary entity for which translations are sought (e.g., 'defRecTypes', 'defDetailTypes').
             * @param {string} key - A key to identify the specific set of translations, often related to `entityName` (e.g., 'defRecTypes_Translation').
             *                       If `_Translation` suffix is missing, it's appended.
             * @param {Array<number|string>|string} [recIDs] - Optional. Specific record IDs within `entityName` for which to fetch translations.
             *                                              If 'all' or empty, fetches all available translations for the entity/key.
             * @param {function(HRecordSet|null): void} callback - Function called with an `HRecordSet` of translations, or null on error.
             * @returns {void}
             */
            getTranslatedDefs: function(entityName, key, recIDs, callback){
                if (!key.includes('_Translation')) { // Ensure key has suffix
                    key += '_Translation';
                }

                if (entity_data[key] && window.hWin.HEURIST4.util.isRecordSet(entity_data[key])) { // Check cache
                    if (typeof callback === 'function') callback.call(this, entity_data[key]);
                    return;
                }

                let request = {
                    'a': 'batch', // Action for fetching batch translations
                    'entity': entityName, // The primary entity type
                    'get_translations': (window.hWin.HEURIST4.util.isempty(recIDs) || recIDs === 'all') ? 'all' : recIDs,
                    'request_id': window.hWin.HEURIST4.util.random()
                };

                window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){ 
                    if(response.status == window.hWin.ResponseStatus.OK && response.data){
                        let recordset = new HRecordSet(response.data);
                        window.hWin.HAPI4.EntityMgr.setEntityData(key, recordset); // Cache the translations
                        if (typeof callback === 'function') callback.call(this, recordset);
                    } else {
                        if (typeof callback === 'function') callback.call(this, null); // Error or no data
                    }
                });
            }
        };
        return that;
    }


    //public members
    /**
     * @lends hAPI.prototype
     */
    let that = {
        /**
         * The base URL for the Heurist application server.
         * @type {string}
         */
        baseURL: '',
        /**
         * The base URL for accessing record type icons. Typically constructed as `baseURL + '?db=<dbname>&icon='`.
         * @type {string}
         */
        iconBaseURL: '',
        /**
         * The name of the currently active Heurist database.
         * @type {string}
         */
        database: '',
        /**
         * An object holding information about the currently logged-in user.
         * Defaults to a guest user object if no user is logged in.
         * @type {Object}
         * @property {number} ugr_ID - The user/group ID.
         * @property {string} ugr_FullName - The full name of the user.
         * @property {Object} [ugr_Permissions] - Object detailing user permissions.
         * @property {Object} [ugr_Groups] - Object detailing group memberships.
         * @property {Object} [ugr_Preferences] - User-specific preferences.
         */
        currentUser: _guestUser,
        /**
         * Flag indicating if Heurist is running in a "publish mode" (e.g., as an embedded component or public website).
         * When true, certain development/administrative features might be disabled.
         * @type {boolean}
         */
        is_publish_mode: false,
        /**
         * An object containing system-wide information and settings for the current database,
         * such as server version, database version, default language, list of user groups, etc.
         * Loaded by `SystemMgr.sys_info()`.
         * @type {Object}
         */
        sysinfo: {},

        /**
         * @property {Object} Event - Defines a set of global event names used within the Heurist application
         *                           for communication between different components and managers.
         *                           Listeners can be attached to `window.hWin.document` for these events.
         * @property {string} Event.ON_CREDENTIALS - Triggered when user credentials change (login, logout, role change).
         * @property {string} Event.ON_REC_SEARCHSTART - Triggered before a record search operation starts.
         * @property {string} Event.ON_REC_SEARCH_FINISH - Triggered when a record search operation finishes. Event data includes results.
         * @property {string} Event.ON_CUSTOM_EVENT - A generic event for custom inter-widget communication.
         * @property {string} Event.ON_REC_UPDATE - Triggered after a record is saved, deleted, or significantly updated.
         * @property {string} Event.ON_REC_SELECT - Triggered when records are selected (e.g., in a list).
         * @property {string} Event.ON_REC_STATUS - Triggered for record status changes.
         * @property {string} Event.ON_REC_COLLECT - Triggered when records are added to or removed from a collection.
         * @property {string} Event.ON_LAYOUT_RESIZE - Triggered when a specific layout component is resized.
         * @property {string} Event.ON_WINDOW_RESIZE - Triggered when the browser window is resized (debounced).
         * @property {string} Event.ON_SYSTEM_INITED - Triggered when the HAPI system has completed its initial setup.
         * @property {string} Event.ON_STRUCTURE_CHANGE - Triggered when database structure definitions (e.g., record types, fields) change.
         * @property {string} Event.ON_PREFERENCES_CHANGE - Triggered when user preferences are changed.
         */
        Event: {
            ON_CREDENTIALS: 'ON_CREDENTIALS',
            ON_REC_SEARCHSTART: "ON_REC_SEARCHSTART",
            ON_REC_SEARCH_FINISH: "ON_REC_SEARCH_FINISH",
            ON_CUSTOM_EVENT: "ON_CUSTOM_EVENT",
            ON_REC_UPDATE: "ON_REC_UPDATE",
            ON_REC_SELECT: "ON_REC_SELECT",
            ON_REC_STATUS: "ON_REC_STATUS",
            ON_REC_COLLECT: "ON_REC_COLLECT",
            ON_LAYOUT_RESIZE: "ON_LAYOUT_RESIZE",
            ON_WINDOW_RESIZE: "ON_WINDOW_RESIZE",
            ON_SYSTEM_INITED: "ON_SYSTEM_INITED",
            ON_STRUCTURE_CHANGE: 'ON_STRUCTURE_CHANGE',
            ON_PREFERENCES_CHANGE: 'ON_PREFERENCES_CHANGE',
        },

        /**
        * Sets the current user object within HAPI.
        * If the provided user object indicates disabled permissions, the current user defaults to a guest user.
        * Optionally enables an idle timer to verify credentials if the user is inactive (currently disabled by `ENABLE_VERIFY_IDLE`).
        * Triggers `ON_REC_UPDATE` if the user has changed, as this might affect record visibility or editable status.
        *
        * @param {Object|null} user - The user object obtained from the server (typically via `SystemMgr.sys_info` or login).
        *                             Contains properties like `ugr_ID`, `ugr_FullName`, `ugr_Permissions`, `ugr_Groups`.
        *                             If null, sets to guest user.
        * @returns {void}
        */
        setCurrentUser: function (user) {
            let isChanged = (that.currentUser?.ugr_ID !== user?.ugr_ID); // Check if user ID actually changed

            if (user && user['ugr_Permissions'] && user['ugr_Permissions']['disabled'] === false) {
                that.currentUser = user;
            } else {
                that.currentUser = { ..._guestUser }; // Use a copy of guestUser
            }

            const ENABLE_VERIFY_IDLE = false; // This feature is currently disabled in the source
            if (ENABLE_VERIFY_IDLE) {
                if (that.currentUser['ugr_ID'] > 0) { // If a non-guest user is set
                    if (!window.hWin.HAPI4.is_publish_mode && window.hWin.HEURIST4?.ui) {
                        window.hWin.HEURIST4.ui.onInactiveStart(5000, function () {  // Example: 5 seconds for testing; original 300000 (5 mins)
                            window.hWin.HAPI4.SystemMgr.verify_credentials(function () {
                                window.hWin.HEURIST4.ui.onInactiveReset();
                            }, 0);
                        });
                    }
                } else if (window.hWin.HEURIST4?.ui) { // User is guest or no user
                    window.hWin.HEURIST4.ui.onInactiveReset(true); // Terminate idle checker
                }
            }

            // If user changed and $Db utility is available, signal potential need to update record type counts
            // and trigger a general record update event.
            if (typeof $Db !== 'undefined' && $Db && isChanged) {
                $Db.needUpdateRtyCount = 1;
                window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_REC_UPDATE);
            }
        },

        /**
         * Removes a group from the current user's group list (`currentUser.ugr_Groups`)
         * and optionally from the system-wide list of user groups (`sysinfo.db_usergroups`) if `isfinal` is true.
         *
         * @param {string|number} groupID - The ID of the group to remove.
         * @param {boolean} isfinal - If true, also removes the group from `sysinfo.db_usergroups`.
         * @returns {void}
         */
        currentUserRemoveGroup: function (groupID, isfinal) {
            const groupIDStr = String(groupID); // Ensure string key for object access
            if (window.hWin.HAPI4.currentUser && window.hWin.HAPI4.currentUser['ugr_Groups'] && window.hWin.HAPI4.currentUser['ugr_Groups'][groupIDStr]) {
                delete window.hWin.HAPI4.currentUser['ugr_Groups'][groupIDStr];
            }
            if (isfinal && window.hWin.HAPI4.sysinfo && window.hWin.HAPI4.sysinfo.db_usergroups && window.hWin.HAPI4.sysinfo.db_usergroups[groupIDStr]) {
                delete window.hWin.HAPI4.sysinfo.db_usergroups[groupIDStr];
            }
        },
        
        /**
         * Checks if the current user is a guest user.
         * A guest user is determined by the `guest_user` flag within their permissions.
         *
         * @returns {boolean} True if the current user is a guest, false otherwise.
         */
        is_guest_user: function(){
             return !!(window.hWin.HAPI4.currentUser &&
                    window.hWin.HAPI4.currentUser['ugr_Permissions'] && 
                    window.hWin.HAPI4.currentUser['ugr_Permissions']['guest_user']);
        },
        
        /**
        * Checks if the current user is a database administrator.
        * A database admin is an admin of the group designated as "Database Managers" (ID from `sysinfo.db_managers_groupid`).
        *
        * @returns {boolean} True if the current user is a database admin, false otherwise.
        */
        is_admin: function () {
            return window.hWin.HAPI4.has_access(window.hWin.HAPI4.sysinfo.db_managers_groupid);
        },
        
        /**
        * Checks if the current user is a member of one or more specified groups.
        * If `ugs` is 0 or null, it's considered true (e.g., accessible by anyone/everyone).
        *
        * @param {number|string|Array<number|string>} ugs - A single group ID, a comma-separated string of group IDs,
        *        or an array of group IDs.
        * @returns {boolean} True if the current user is a member of any of the specified groups (or if `ugs` implies public access),
        *                    or if the user's ID matches one of the `ugs`. False otherwise.
        */
        is_member: function (ugs) {
            if (ugs == 0 || ugs == null) { // Group 0 or null often means public/everyone
                return true;
            }

            let groups_to_check;
            if (typeof ugs === 'number' || (typeof ugs === 'string' && !String(ugs).includes(','))) {
                groups_to_check = [String(ugs)];
            } else {
                groups_to_check = Array.isArray(ugs) ? ugs.map(String) : String(ugs).split(',').map(s => s.trim());
            }

            for (const group_id of groups_to_check) {
                if (group_id == "0" || // Public access
                    (that.currentUser && that.currentUser['ugr_ID'] == group_id) || // User is the group (for user-specific resources)
                    (that.currentUser && that.currentUser['ugr_Groups'] && that.currentUser['ugr_Groups'][group_id])) { // User is member of group
                    return true;
                }
            }
            return false;
        },

        /**
        * Determines the type of the current user based on their permissions and roles.
        * 
        * @returns {('owner'|'admin'|'manager'|'user'|'guest'|'visitor')} A string representing the user type.
        *          - `owner`: User ID is 2 (database owner).
        *          - `admin`: User is an admin (as per `is_admin()`).
        *          - `manager`: User is a member of the database managers group.
        *          - `user`: A logged-in user not fitting other specific roles.
        *          - `guest`: A logged-in user with guest permissions.
        *          - `visitor`: Not logged in or user ID is 0.
        */
        getUserType: function(){
            let userType = 'visitor'; // Default for non-logged-in users

            if (that.currentUser && that.currentUser['ugr_ID'] > 0) { // User is logged in
                if( that.has_access(2) ){ // User ID 2 is typically the database owner
                   userType = 'owner';
                } else if( window.hWin.HAPI4.is_admin() ){ // Admin of the "Database Managers" group
                   userType = 'admin';
                } else if( window.hWin.HAPI4.is_member(window.hWin.HAPI4.sysinfo.db_managers_groupid) ){ // Member of "Database Managers"
                   userType = 'manager';
                } else if(  window.hWin.HAPI4.is_guest_user() ){ // Has specific guest permissions
                   userType = 'guest';
                } else { // General logged-in user
                   userType = 'user';
                }
            }
            return userType;
        },
        
        /**
        * Checks if the current user meets a specified access level.
        *
        * @param {number|string} requiredLevel - The required access level.
        *   - `NaN` or `<1`: User must be logged in (i.e., `currentUser.ugr_ID > 0`). This is the default if no valid number provided.
        *   - `1`: User must be a database admin (admin of group specified by `sysinfo.db_managers_groupid`).
        *          Note: The original code directly checks against group ID 1. This implementation uses `sysinfo.db_managers_groupid`.
        *   - `2`: User must be the database owner (i.e., `currentUser.ugr_ID == 2`).
        *   - `n` (other positive number): User must be an admin of the group with ID `n`.
        * @returns {boolean} True if the current user satisfies the required access level, false otherwise.
        */
        has_access: function (requiredLevel) {
            const numRequiredLevel = Number(requiredLevel);

            if (isNaN(numRequiredLevel) || numRequiredLevel < 1) { // Check if logged in
                return (that.currentUser && that.currentUser['ugr_ID'] > 0);
            }
            if (!that.currentUser || that.currentUser['ugr_ID'] === 0) return false; // Not logged in, cannot satisfy levels >= 1

            if (numRequiredLevel === 2 && that.currentUser['ugr_ID'] === 2) return true; // DB Owner
            if (numRequiredLevel === window.hWin.HAPI4.sysinfo.db_managers_groupid &&
                that.currentUser['ugr_Groups'] &&
                that.currentUser['ugr_Groups'][numRequiredLevel] === "admin") return true; // Admin of DB Managers group

            // Admin of a specific group 'n'
            return (that.currentUser['ugr_Groups'] && that.currentUser['ugr_Groups'][numRequiredLevel] === "admin");
        },

        /**
        * Retrieves user preferences. If a specific preference `name` is provided, returns its value.
        * Otherwise, returns the entire preferences object.
        * Includes default preferences if none are set for the user.
        * Adjusts certain preference values (e.g., `search_detail_limit`, `search_result_pagesize`) to be within system-defined bounds.
        *
        * @param {string} [name] - The name of the preference to retrieve. If omitted, all preferences are returned.
        * @returns {Object|string|number|boolean|null} The value of the specified preference,
        *          or the entire preferences object if `name` is omitted. Returns `null` or default if a named pref is not found.
        */
        get_prefs: function (name) {
            if (!that.currentUser['ugr_Preferences']) { // Initialize with defaults if not present
                that.currentUser['ugr_Preferences'] = {
                    layout_language: 'en', // Default language
                    search_result_pagesize: 100,
                    search_detail_limit: 500,
                    userCompetencyLevel: 2, // Default competency level (e.g., 'intermediate' or numeric)
                    userFontSize: 12, // Default font size in pixels
                    deriveMapLocation: true, // Whether to derive map locations automatically
                    help_on: true, // Whether help tips are enabled
                    optfields: true, // Whether optional fields are shown by default
                    mapcluster_on: true, // Whether map clustering is enabled
                    searchQueryInBrowser: true // Whether to reflect search query in browser URL/history
                };
            }

            if (window.hWin.HEURIST4.util.isempty(name)) {
                return that.currentUser['ugr_Preferences']; // Return all preferences
            } else {
                let pref_value = null; // Default if preference not found
                if(that.currentUser['ugr_Preferences'] && Object.hasOwn(that.currentUser['ugr_Preferences'], name)){
                    pref_value = that.currentUser['ugr_Preferences'][name];
                }
                
                // Apply constraints/defaults for specific preferences
                if (name === 'search_detail_limit') {
                    const num_val = parseInt(pref_value, 10) || 0; // Use 0 if not a number
                    return Math.min(Math.max(num_val, 500), 5000); // Constrain between 500 and 5000
                } else if (name === 'search_result_pagesize') {
                    const num_val = parseInt(pref_value, 10) || 0;
                    return Math.min(Math.max(num_val, 100), 5000); // Constrain between 100 and 5000
                }
                return pref_value;
            }
        },

        /**
         * Retrieves a user preference value, returning a default value if the preference is not set or is empty.
         *
         * @param {string} name - The name of the preference to retrieve.
         * @param {*} defvalue - The default value to return if the preference is not found or is empty.
         * @returns {*} The preference value or `defvalue`.
         */
        get_prefs_def: function (name, defvalue) {
            let res = window.hWin.HAPI4.get_prefs(name);
            if (window.hWin.HEURIST4.util.isempty(res)) { // Checks for null, undefined, empty string, empty array/object
                res = defvalue;
            }
            // Specific handling for userCompetencyLevel if it might be stored as a non-numeric string
            if (name === 'userCompetencyLevel' && isNaN(Number(res))) {
                res = defvalue;
            }
            return res;
        },

        /**
         * Saves a user preference to the server and updates the local cache (`currentUser.ugr_Preferences`).
         * If the `value` is an array and `limit` is provided, the array is truncated to the limit,
         * and new items are unshifted into the existing preference list (if it's also a list), maintaining uniqueness.
         *
         * @param {string} name - The name of the preference to save.
         * @param {*} value - The value of the preference.
         * @param {number} [limit] - Optional. If `value` is an array, limits the number of items stored.
         *                         New items are added to the beginning, and the list is truncated to this limit.
         * @returns {void}
         */
        save_pref: function (name, value, limit) {
            let final_value = value;
            if (Array.isArray(value) && limit > 0) {
                let current_pref_string = window.hWin.HAPI4.get_prefs(name);
                let current_array = (typeof current_pref_string === 'string' && current_pref_string) ? current_pref_string.split(',') : [];

                // Add new items to the beginning, ensuring uniqueness
                const new_items = value.slice(0, limit);
                new_items.reverse().forEach(item => { // Add new items to the front, effectively
                    const item_str = String(item);
                    const index = current_array.indexOf(item_str);
                    if (index > -1) {
                        current_array.splice(index, 1); // Remove existing to move to front
                    }
                    current_array.unshift(item_str);
                });
                final_value = current_array.slice(0, limit).join(','); // Truncate and join
            }

            let request = {};
            request[name] = final_value;

            window.hWin.HAPI4.SystemMgr.save_prefs(request,
                function (response) {
                    if (response.status == window.hWin.ResponseStatus.OK) {
                        if (!that.currentUser['ugr_Preferences']) that.currentUser['ugr_Preferences'] = {};
                        that.currentUser['ugr_Preferences'][name] = final_value; // Update local cache
                    }
                    // Optionally handle error response here
                }
            );
        },

        /**
         * Triggers a global event on `window.hWin.document` and also calls any listeners
         * registered via `addEventListener` for that event type (for inter-frame communication).
         *
         * @param {string} eventType - The type of event to trigger (e.g., from `HAPI4.Event`).
         * @param {*} [data] - Optional data to pass with the event.
         * @returns {void}
         */
        triggerEvent: function (eventType, data) {
            $(window.hWin.document).trigger(eventType, data);

            // Call listeners in other frames/contexts
            for (let i = 0; i < _listeners.length; i++) {
                if (_listeners[i].event_type == eventType) {
                    try {
                        _listeners[i].callback.call(_listeners[i].obj, data);
                    } catch (e) {
                        console.error("Error in HAPI event listener:", e, "Event:", eventType, "Listener Object:", _listeners[i].obj);
                    }
                }
            }
        },

        /**
         * Adds an event listener, primarily for inter-frame communication or components
         * that cannot directly use jQuery's event system on `window.hWin.document`.
         *
         * @param {Object} object_context - The `this` context for the callback when invoked.
         * @param {string} event_type - The event type to listen for (from `HAPI4.Event`).
         * @param {function(*): void} callback - The function to call when the event is triggered. Receives event data as argument.
         * @returns {void}
         */
        addEventListener: function (object_context, event_type, callback) {
            _listeners.push({ obj: object_context, event_type: event_type, callback: callback });
        },

        /**
         * Removes an event listener previously added with `addEventListener`.
         *
         * @param {Object} object_context - The `this` context of the listener to remove.
         * @param {string} event_type - The event type of the listener to remove.
         * @returns {void}
         */
        removeEventListener: function (object_context, event_type) {
            for (let i = _listeners.length - 1; i >= 0; i--) { // Iterate backwards for safe removal
                if (_listeners[i].event_type == event_type && _listeners[i].obj == object_context) {
                    _listeners.splice(i, 1);
                    // return; // Remove only the first match, or continue to remove all matches
                }
            }
        },

        /**
         * Gets the ID of the current user.
         * @returns {number} The `ugr_ID` of the current user (0 for guest).
         */
        user_id: function () {
            return that.currentUser ? that.currentUser['ugr_ID'] : 0;
        },

        /**
         * Stores the main result set from the last primary search operation.
         * Typically populated by `HRecordSearch.doSearch` when no `search_realm` is specified.
         * Used for operations like applying rules to an existing set or accessing results globally.
         * @type {HRecordSet|null}
         */
        currentRecordset: null,

        /**
         * An array storing the IDs of records currently selected by the user,
         * for example, in a result list. Used for batch operations or context-sensitive actions.
         * @type {Array<number|string>}
         */
        currentRecordsetSelection: [],


        /** Gets the class name "HAPI". @returns {string} */
        getClass: function () { return _className; },
        /** Checks if the given string is "HAPI". @param {string} strClass - Class name to check. @returns {boolean} */
        isA: function (strClass) { return (strClass === _className); },
        /** Gets the version of HAPI. @returns {string} */
        getVersion: function () { return _version; },


        /** @type {HSystemMgr|null} Instance of HSystemMgr for system and user operations. */
        SystemMgr: null,
        /** @type {HRecordMgr|null} Instance of HRecordMgr for record operations. */
        RecordMgr: new HRecordMgr(), // Instantiated directly
        /** @type {HEntityMgr|null} Instance of HEntityMgr for entity definition and data operations. */
        EntityMgr: new HEntityMgr(), // Instantiated directly
        /** @type {HRecordSearch|null} Instance of HRecordSearch for managing record searches. Initialized in `_init`. */
        RecordSearch: null,
        /** @type {HLayout|null} Instance of the legacy HLayout manager. Initialized in `_init`. */
        LayoutMgr: null, // Legacy layout manager
        /** @type {HLayoutMgr|null} Instance of the new HLayoutMgr manager. Initialized in `_init`. */
        layoutMgr: null, // New layout manager
        
        /**
         * Public wrapper for the private `_callserver` AJAX function.
         * @param {string} controllerName - Server controller script name.
         * @param {HeuristRequest} request - Request object.
         * @param {callserverCallback} callback - Callback function.
         * @param {number} [timeout_ms=0] - Optional request timeout.
         * @returns {void}
         */
        callserver: function(controllerName, request, callback, timeout_ms=0){
            _callserver(controllerName, request, callback, timeout_ms);
        },
        
        /**
         * Retrieves the 3-letter ISO 639-2 language code (uppercase) for a given 2-letter or 3-letter code.
         * If the input `lang` is 'def' or not found, it returns a default language code
         * (either the current `_region` if set, or 'ENG').
         * Relies on `that.sysinfo.common_languages` for the mapping.
         *
         * @param {string} lang - The input language code (2-letter, 3-letter, or 'def').
         * @param {string} [def_lang='ENG'] - The default language code to return if `lang` cannot be resolved.
         * @returns {string} The resolved 3-letter uppercase language code.
         */
        getLangCode3: function(lang, def_lang = 'ENG'){ // Provide default for def_lang
            if(lang && typeof lang === 'string' && lang.toLowerCase() !== 'def'){
                lang = lang.toLowerCase();
                if(lang.length === 2){
                    if (that.sysinfo && that.sysinfo.common_languages) {
                        for(let code3 in that.sysinfo.common_languages){
                            if(Object.hasOwn(that.sysinfo.common_languages, code3) &&
                               that.sysinfo.common_languages[code3]['a2'] === lang){
                                return code3.toUpperCase();
                            }
                        }
                    }
                } else if(lang.length === 3){
                    const upperLang = lang.toUpperCase();
                    if(that.sysinfo && that.sysinfo.common_languages && that.sysinfo.common_languages[upperLang]){
                          return upperLang;
                    }
                }
            }
            // Default language resolution
            return (_region ? _region : def_lang).toUpperCase();
        },
        
        /**
         * Retrieves a translated string from a collection of values, based on the specified language.
         * The `values` can be an object keyed by language codes (e.g., `{"ENG": "Hello", "FRE": "Bonjour"}`)
         * or an array where language might be embedded in strings (e.g., `["ENG:Hello", "FRE:Bonjour"]`).
         * It handles 2-letter and 3-letter language codes and can fall back to a default value or the first available value.
         *
         * @param {Object<string, string>|Array<string>|string} values - The collection of string values.
         *        - If an object, keys are lang codes (e.g., 'ENG', 'fre', 'en', 'FR'), values are translations.
         *        - If an array, strings can be prefixed with lang codes (e.g., "ENG:Hello", "fr:Bonjour").
         *        - If a single string, it's returned directly if no language processing applies.
         * @param {string} [lang_code] - The desired language code (2 or 3 letters, or 'def').
         *                             If omitted, or if a translation for the specified `lang_code` is not found,
         *                             it attempts to find a default or the first available non-prefixed string.
         * @returns {string} The translated string, or a default/first available string if not found. Returns empty string if input is empty.
         */
        getTranslation: function(values, lang_code){
            if (window.hWin.HEURIST4.util.isempty(values)) return '';
            if (typeof values === 'string' && (!lang_code || lang_code === 'def')) return values; // Simple case

            const target_lang_3 = lang_code ? window.hWin.HAPI4.getLangCode3(lang_code) : (_region || 'ENG');
            const target_lang_2 = (that.sysinfo && that.sysinfo.common_languages && that.sysinfo.common_languages[target_lang_3]) ?
                                  that.sysinfo.common_languages[target_lang_3]['a2'] : null;

            let default_val = '';

            if ($.isPlainObject(values)) {
                if (Object.hasOwn(values, target_lang_3)) return values[target_lang_3];
                if (target_lang_2 && Object.hasOwn(values, target_lang_2)) return values[target_lang_2];
                if (Object.hasOwn(values, target_lang_3.toLowerCase())) return values[target_lang_3.toLowerCase()];
                if (target_lang_2 && Object.hasOwn(values, target_lang_2.toLowerCase())) return values[target_lang_2.toLowerCase()];
                // Fallback to first value if specific lang not found
                const keys = Object.keys(values);
                return keys.length > 0 ? values[keys[0]] : '';
            } else if (Array.isArray(values)) {
                for (const val of values) {
                    if (val == null) continue;
                    let current_val_str = String(val);
                    let val_orig = current_val_str;
                    let tag_to_remove = null;

                    if(current_val_str.indexOf('<p')===0 || current_val_str.indexOf('<span')===0){
                        tag_to_remove = current_val_str.indexOf('<p')===0?'</p>':'</span>';
                        current_val_str = window.hWin.HEURIST4.util.stripTags(current_val_str);
                    }
                    const content_to_return = () => (tag_to_remove == null) ? current_val_str.substring(current_val_str.indexOf(':') + 1).trim()
                                                                         : window.hWin.HEURIST4.util.stripFirstElement(val_orig);

                    if(current_val_str.length > 4 && current_val_str.substring(3,4) === ':'){ // Check for 3-letter lang prefix
                        if(current_val_str.substring(0,3).toUpperCase() === target_lang_3){
                            return content_to_return();
                        }
                    } else if(target_lang_2 && current_val_str.length > 3 && current_val_str.substring(2,3) === ':'){ // Check for 2-letter lang prefix
                        if(current_val_str.substring(0,2).toUpperCase() === target_lang_2.toUpperCase()){
                           return content_to_return();
                        }
                    } else { // No prefix, potential default value
                        if (default_val === '') default_val = val_orig; // Take first non-prefixed as default
                        if (lang_code === 'def' || !lang_code) break; // If 'def' or no lang specified, use first non-prefixed
                    }
                }
                return default_val; // Return collected default or empty if nothing matched
            }
            return String(values); // Fallback for single string or other types
        },

        /**
         * Gets the current locale (3-letter language code, e.g., 'ENG') being used by HAPI.
         * @returns {string|null} The current region/language code, or null if not set.
         */
        getLocale: function () {
            return _region;
        },

        /**
        * Sets the current application locale (language) and loads the corresponding localization resources.
        * It updates `_region` and `_regional` cache.
        * Returns a new localization function `HR` bound to the new locale.
        *
        * @param {string} region_code - The language code to set (e.g., 'ENG', 'fre', 'en').
        *                               It will be resolved to a 3-letter uppercase code.
        * @returns {function(string): string} A new `HR` function that localizes strings for the set region.
        */
        setLocale: function (region_code) {
            const resolved_region = that.getLangCode3(region_code, 'ENG'); // Resolve to 3-letter uppercase, default ENG

            // Load locale file only if not already loaded and if it's ENG or listed as available
            const is_english = resolved_region === 'ENG';
            const is_available_locale = that.sysinfo && that.sysinfo.localization_files &&
                                      that.sysinfo.localization_files.includes(resolved_region.toLowerCase());

            if (!_regional[resolved_region] && (is_english || is_available_locale)) {
                _region = resolved_region; // Set current region for HAPI instance
                
                // Synchronous AJAX is generally discouraged, but might be used here for simplicity if HR must be immediately usable.
                // Consider async loading and a callback/promise if this causes issues.
                $.ajax({
                    url: that.baseURL + `hclient/assets/localization/localization_${_region.toLowerCase()}.txt`,
                    async: false, // Ensure HR is usable immediately after setLocale (original behavior implies this)
                    success: function(response){
                        const lines = response.split("\n");
                        _regional[_region] = {}; // Initialize/clear for this region
                        let current_key_for_multiline = ''; // Handles multi-line translations
                        lines.forEach((line)=>{
                            const trimmed_line = line.trim();
                            if(trimmed_line !== '' && !trimmed_line.startsWith('//')){ // Ignore empty lines and comments
                                if(trimmed_line.startsWith('#')){
                                    const parts = trimmed_line.substring(1).split('#', 2); // Split by first '#' after initial
                                    if (parts.length === 2) {
                                        current_key_for_multiline = parts[0].trim();
                                        _regional[_region][current_key_for_multiline] = parts[1].trim();
                                    } else if (parts.length === 1 && parts[0].trim() !== '') { // Key without value, implies multiline start
                                        current_key_for_multiline = parts[0].trim();
                                        _regional[_region][current_key_for_multiline] = ''; // Initialize empty
                                    }
                                } else if (current_key_for_multiline && _regional[_region]) { // Append to previous key if multi-line
                                    _regional[_region][current_key_for_multiline] += "\n" + trimmed_line;
                                }
                            }    
                        });
                    },
                    error: function() {
                        console.warn(`Localization file for ${_region} not found or failed to load. Falling back.`);
                        if (_region !== 'ENG' && !_regional['ENG']) { // Attempt to load English if current failed and ENG not loaded
                            that.setLocale('ENG'); // This could lead to recursion if ENG also fails.
                        }
                    }
                });
            } else if (is_english && !_regional['ENG']) {
                 // If ENG is requested but not loaded (e.g. _regional was cleared), try to load it.
                 // This case might be redundant if initial load always gets ENG or if sysinfo is not ready.
            }
             _region = resolved_region; // Ensure _region is set even if file not loaded (will use fallback in HR)


            /**
             * @global HR
             * @description Localizes a given resource key string to the currently set language.
             * Falls back to English if the key is not found in the current language,
             * and finally returns the original key if no translation is found.
             * This function is typically assigned to `window.hWin.HR`.
             * @param {string} resource_key - The key of the string to localize.
             * @returns {string} The localized string, or the original key if not found.
             */
            return function HR(resource_key) {
                if (window.hWin.HEURIST4.util.isempty(resource_key)) {
                    return '';
                }
                const key_trimmed = String(resource_key).trim();

                if (_regional[_region] && Object.hasOwn(_regional[_region], key_trimmed)) {
                    return _regional[_region][key_trimmed];
                } else if (_region !== 'ENG' && _regional['ENG'] && Object.hasOwn(_regional['ENG'], key_trimmed)) { // Fallback to English
                    return _regional['ENG'][key_trimmed];
                } else { // Fallback to the key itself
                    return resource_key;
                }
            };
        },

        /**
        * @global HRA
        * @description Localizes text content and title attributes of DOM elements within a given container.
        * It targets elements with the class `slocale` for their text content,
        * and elements with the attribute `slocale-title` for their `title` attribute.
        * Uses the currently active `window.hWin.HR` localization function.
        *
        * @param {jQuery|HTMLElement} element_container - The container element whose descendants are to be localized.
        * @param {string} [target_lang_code] - Optional. If provided, attempts to set the locale to this language
        *                                     before localizing. Uses current HAPI locale otherwise.
        * @returns {void}
        */
        HRA: function (element_container, target_lang_code) {
            if (element_container) {
                let currentHR = window.hWin.HR; // Use current HR by default
                if(target_lang_code){
                    // Note: setLocale returns a new HR function. If HRA is meant to use a temporary locale
                    // for this call only, this is fine. If it should permanently change hWin.HR, that's a side effect.
                    currentHR = that.setLocale(target_lang_code);
                }
                
                $(element_container).find('.slocale').each(function () {
                    const $item = $(this);
                    $item.html(currentHR($item.text()));
                });

                $(element_container).find('[slocale-title]').each(function () {
                    const $item = $(this);
                    $item.attr('title', currentHR($item.attr('slocale-title')));
                });
            }
        },

        /**
         * @global HRJ
         * @description Retrieves a localized value from a JSON object (typically widget options).
         * It looks for a language-suffixed key (e.g., `optionName:FRE`) first, using the provided `lang_code`
         * or the current HAPI locale. If not found, it returns the value of the base `name` key.
         *
         * @param {string} name - The base name of the option/property.
         * @param {Object} options_object - The object containing options, possibly with localized versions.
         * @param {string} [lang_code] - The language code to use for localization. Defaults to current HAPI locale.
         * @returns {*} The localized value if found, otherwise the default value from the base `name`.
         */
        HRJ: function (name, options_object, lang_code) {
            const default_value = options_object[name];
            const target_lang = that.getLangCode3(lang_code, _region); // Resolve to 3-letter uppercase
            const localized_key = name + ':' + target_lang; // Standard Heurist suffixed key

            if (options_object && Object.hasOwn(options_object, localized_key)) {
                return options_object[localized_key];
            }
            return default_value;
        },

        /**
         * @global HRes
         * @description Returns a URL for a localized resource (e.g., help file, HTML snippet)
         * or loads its content directly into a target DOM element.
         * The URL is constructed using `HAPI4.baseURL`, current locale, and the resource name.
         *
         * @param {string} resource_name - The name of the resource (e.g., 'helpTopic', 'mySnippet.html').
         *                                If no extension, '.html' might be assumed by server or this logic.
         * @param {jQuery|HTMLElement} [target_element] - Optional. If provided, the content of the localized resource
         *                                             is loaded into this element using jQuery's `.load()`.
         * @returns {string|void} If `target_element` is not provided, returns the constructed URL to the localized resource.
         *                        Otherwise, returns nothing (void) as content is loaded asynchronously.
         */
        HRes: function (resource_name, target_element) {
            // Construct URL: baseURL?lang=<current_locale>&asset=<resource_name>
            // The server is expected to handle mapping 'asset=<name>' to the correct file path.
            const resource_url = window.hWin.HAPI4.baseURL + '?lang=' + (_region || 'ENG') + '&asset=' + resource_name;
        
            if (target_element) {
                $(target_element).load(resource_url, function(response, status, xhr) {
                    if (status === "error") {
                        console.error(`HRes: Error loading content for '${resource_name}' from ${resource_url}: ${xhr.status} ${xhr.statusText}`);
                        // Optionally, display an error in target_element
                        // $(target_element).html(`<p class="error">Failed to load resource: ${resource_name}</p>`);
                    }
                });
            } else {
                return resource_url; // Return the URL if no target element
            }
        },

        /**
        * Retrieves a subset of records from a given recordset or the global `currentRecordset`.
        *
        * @param {('all'|Array<number|string>|HRecordSet)} selection - Defines which records to get.
        *        - `'all'`: Returns all records from the source recordset.
        *        - `Array<number|string>`: An array of record IDs to select.
        *        - `HRecordSet`: If an `HRecordSet` instance is passed, it's processed based on `needIds`.
        * @param {boolean} [needIds=false] - If true, returns an array of record IDs.
        *                                   If false (default), returns an `HRecordSet` instance (or subset).
        * @param {HRecordSet} [source_recordset=this.currentRecordset] - The source `HRecordSet` to operate on.
        *                                   Defaults to `this.currentRecordset`.
        * @returns {HRecordSet|Array<number|string>|null}
        *          - An `HRecordSet` if `needIds` is false.
        *          - An array of record IDs if `needIds` is true.
        *          - `null` if the source recordset is unavailable or the selection is empty/invalid.
        * @todo Clarify behavior if `selection` is an `HRecordSet` and `needIds` is false (return original or copy?).
        *       The "array of recordtype" case mentioned in original comment is not implemented.
        */
        getSelection: function (selection, needIds, source_recordset) {
            const target_rs = source_recordset || this.currentRecordset;

            if (selection === "all") {
                if (target_rs) {
                    return needIds ? target_rs.getIds() : target_rs;
                } else {
                    return null; // No source recordset to get all from
                }
            }

            if (selection) {
                if (window.hWin.HEURIST4.util.isRecordSet(selection)) { // If selection itself is an HRecordSet
                    if (selection.length() > 0) {
                        return needIds ? selection.getIds() : selection;
                    }
                } else if (Array.isArray(selection)) { // selection is an array of IDs
                    if (selection.length === 0) return needIds ? [] : new HRecordSet({records:[], reccount:0}); // Empty selection
                    return needIds ? selection : (target_rs ? target_rs.getSubSetByIds(selection) : null);
                }
            }
            // If selection is invalid or leads to no records
            return needIds ? [] : null;
        },

        /**
         * Constructs the URL for an entity's image (e.g., record type icon, term icon).
         *
         * @param {string} [entityName='rty'] - The name of the entity type (e.g., 'rty' for record types, 'trm' for terms).
         *                                 Defaults to 'rty' if not provided, implying record type icons.
         * @param {number|string} recID - The ID of the entity instance (e.g., record type ID, term ID).
         * @param {('thumb'|'icon'|string)} [version] - Optional version or size of the image (e.g., 'thumb', 'icon').
         * @param {0|1|2|3|'check'} [def=0] - Behavior for default/fallback image if the specific image is not found:
         *        - `0`: Return empty placeholder (server might return transparent pixel).
         *        - `1`: Return "add image" placeholder.
         *        - `2`: Return default icon/thumbnail for the entity type.
         *        - `3` or `'check'`: Check for existence (server might return special status or small image).
         * @param {string} [database_name] - Optional. The database name. Defaults to current HAPI database.
         * @param {boolean} [add_random_nocache=false] - If true, appends a random number as a query parameter to prevent caching.
         * @returns {string} The fully constructed URL for the image.
         */
        getImageUrl: function (entityName, recID, version, def, database_name, add_random_nocache = false) {
            const db_context = database_name || window.hWin.HAPI4.database;
            const entity_param = entityName ? `&entity=${entityName}` : '&entity=rty'; // Default to 'rty'

            return window.hWin.HAPI4.baseURL
                + '?db=' + db_context
                + entity_param
                + '&icon=' + recID // 'icon' parameter here seems to be the ID of the item
                + (version ? ('&version=' + version) : '')
                + ((def !== undefined) ? ('&def=' + def) : '') // Allow def=0
                + (add_random_nocache ? `&r_=${window.hWin.HEURIST4.util.random()}` : ''); // Use r_ for short random param
        },

        /**
         * Checks for the existence or metadata of an image file.
         * For entities other than 'Records', it uses `getImageUrl` with `def='check'`.
         * For 'Records' (which usually implies uploaded files/`ulf_ID`), it queries `fileDownload.php` with `mode='metaonly'`.
         *
         * @param {string} entityName - The entity name (e.g., 'rty', 'Records').
         * @param {number|string} itemID - The ID of the item (record type ID, term ID, or uploaded file ID if entityName is 'Records').
         * @param {string} [version] - Optional image version (e.g., 'thumb'), used if not entityName 'Records'.
         * @param {function(Object):void} callback - Callback function. The response structure depends on the endpoint hit.
         *        For `fileDownload.php`, it might be metadata. For `getImageUrl` with 'check', it might be a minimal response indicating existence.
         * @returns {void}
         */
        checkImage: function (entityName, itemID, version, callback) {
            if (entityName === 'Records') { // Typically refers to uploaded files (ULF)
                let request = {
                    db: window.hWin.HAPI4.database,
                    file: itemID,  // itemID here is ulf_ID
                    mode: 'metaonly'  // Request only metadata (like width, height, existence)
                };
                window.hWin.HEURIST4.util.sendRequest(window.hWin.HAPI4.baseURL + 'hserv/controller/fileDownload.php',
                    request, null, callback);
            } else { // For other entities like 'rty', 'trm'
                let checkURL = window.hWin.HAPI4.getImageUrl(entityName, itemID, version, 'check'); // 'check' def parameter
                window.hWin.HEURIST4.util.sendRequest(checkURL, null, null, callback);
            }
        },

        /**
         * Performs an import action using the `importController`.
         * Triggers `ON_REC_UPDATE` via `_triggerRecordUpdateEvent` on successful import.
         *
         * @param {HeuristRequest} request - The import request object, specifying source, format, and other import parameters.
         * @param {callserverCallback} callback - Callback function to handle the server response from the import operation.
         * @returns {void}
         */
        doImportAction: function (request, callback) {
            _callserver('importController', request,
                function (response) {
                    _triggerRecordUpdateEvent(response, callback); // Trigger update events after import
                });
        },

        /**
        * Checks if a `_callserver` AJAX request is currently in progress.
        * @returns {boolean} True if a request is in progress, false otherwise.
        */
        is_callserver_in_progress: function () {
            return _is_callserver_in_progress;
        }
    };

    _init(_db, _oninit, _baseURL);
    return that;  //returns object
}