/**
 * @file hRecordSearch.js
 * @brief Provides a wrapper for record searching, supporting callbacks and global events.
 * @fileOverview This file defines HRecordSearch, a factory function that creates a search manager for
 * Heurist records. It wraps HAPI4.RecordMgr.search and offers different search execution modes:
 * direct callback-based searches (_doSearchWithCallback), event-driven searches (_doSearch)
 * that update global state (HAPI4.currentRecordset) and trigger ON_REC_SEARCHSTART / ON_REC_SEARCHFINISH
 * events, and rule application to existing result sets (_doApplyRules). The search manager primarily
 * focuses on fetching record IDs, with complex rule processing handled on the server. It also manages
 * internal query request states and document contexts for event triggering.
 * @package Heurist academic knowledge management system
 * @subpackage hclient\core
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @note Completely revised for Heurist version 4
 * @since 4.0
 */

/**
 * @constructor HRecordSearch
 * @description Manages record searching operations within the Heurist system.
 * This constructor function returns an object that acts as a search manager.
 * It wraps `HAPI4.RecordMgr.search` to provide different ways of handling search execution:
 * - With a direct callback (`doSearchWithCallback`).
 * - By triggering global events (`doSearch`) for broader application integration,
 *   updating `HAPI4.currentRecordset`.
 * It primarily searches for record IDs, with rule processing handled server-side.
 *
 * Key functionalities include initiating searches, processing results, applying rules to
 * existing result sets, and managing search state.
 *
 * @see HAPI
 * @see HRecordSet
 * @see HAPI4.RecordMgr.search
 * @returns {Object} An instance-like object with methods for record searching.
 */
function HRecordSearch() {
     const _className = "HRecordSearch",
         _version   = "0.4";
     /**
      * @private
      * @type {Object<string, Object>|null}
      * @description Stores active query requests, keyed by a unique request ID. Used to manage ongoing searches.
      */
     let _query_request = null;
     /**
      * @private
      * @type {Object<string, Document>|null}
      * @description Stores the document context (owner document) for each active query, keyed by request ID.
      * This is used to trigger events (`ON_REC_SEARCHSTART`, `ON_REC_SEARCHFINISH`) in the correct document context.
      */
     let _owner_doc = null;
         
    function _doSearchWithCallback( request, callback ){
        
        window.hWin.HAPI4.RecordMgr.search(request,
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    if(response.data  && response.data.memory_warning){
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: response.data.memory_warning,
                            error_title: 'Query results too large'
                        });
                    }
                    // Wrap the raw data in an HRecordSet object
                    callback( HRecordSet(response.data) );
                }else{
                    callback( null ); // Indicate failure or empty result due to error
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            }
        );
    }

    function _doSearch( originator, request ){
        
            let owner_element_id, owner_doc_context; // Renamed owner_doc to avoid conflict with global _owner_doc
        
            if(originator){
                if(originator.document){ // Originator is an object like {document: doc, element: $el}
                    owner_doc_context = originator.document;
                    owner_element_id = originator.element ? originator.element.attr('id') : 'main_doc';
                }else{ // Originator is assumed to be a Document
                    owner_doc_context = originator;
                    owner_element_id = 'main_doc';
                }
            }else{ // No originator specified
                owner_doc_context = null;
                owner_element_id = null;
            }
    
            if(request == null) return; // Do nothing if request is null

            // Ensure a unique ID for the request if not already present
            if( window.hWin.HEURIST4.util.isnull(request.id) ) {
                request.id = window.hWin.HEURIST4.util.random();
            }
            
            // Standardize request parameters for this search type
            request.source = owner_element_id; // Source element ID for tracking or context
            request.limit = 100000;           // High limit to fetch all relevant IDs
            request.needall = 1;              // Indicates all results are needed (disables pagination for this ID fetch)
            request.detail = 'ids';           // Specifies that only record IDs should be returned

            // Initialize global stores if they are null
            if(_query_request == null){
                _query_request = {};
                _owner_doc = {}; // This is the module-level _owner_doc, not owner_doc_context
            }
            // Store the request and its owner document context
            _query_request[request.id] = request;
            _owner_doc[request.id] = owner_doc_context;
        
            // Legacy global variables update - TODO: Refactor parts relying on these globals (e.g., smarty, diagram)
            if(window.hWin.HEURIST4.util.isempty(request.search_realm)){
                window.hWin.HEURIST4.current_query_request = jQuery.extend(true, {}, request); // Deep clone
            }

            window.hWin.HAPI4.currentRecordset = null; // Reset current recordset

            // Trigger ON_REC_SEARCHSTART event in the originator's document context
            if(!window.hWin.HEURIST4.util.isnull(owner_doc_context)){
                $(owner_doc_context).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART, [ request ]);
            }

            // Perform the actual search using RecordMgr
            window.hWin.HAPI4.RecordMgr.search(request, function(response){
                    _onSearchResult(response); // Pass server response to the result handler
            });
    }
    
    function _onSearchResult(response){
            let recordset = null;
            // Ensure the response corresponds to a known query request
            if(_query_request != null && response && response.queryid && _query_request[response.queryid]) {
                
                let qid = response.queryid;
                // Clone the original request for context, preventing modification of the stored request
                let original_request = window.hWin.HEURIST4.util.cloneJSON(_query_request[qid]);

                if(response.status == window.hWin.ResponseStatus.OK){
                        if(response.data  && response.data.memory_warning){
                            window.hWin.HEURIST4.msg.showMsgErr({
                                message: response.data.memory_warning,
                                error_title: 'Query results too large'
                            }); 
                        }
                        recordset = new HRecordSet(response.data); // Create HRecordSet from data
                        recordset.setRequest( original_request );    // Associate original request with the recordset
                }else{
                    // Handle error, show message
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    // Potentially create an empty recordset or ensure recordset is null for the event
                    recordset = new HRecordSet({ records: [], reccount: 0 }); // Send empty recordset on error
                    recordset.setRequest(original_request);
                }
                
                // If no specific search realm, update the global currentRecordset
                if(window.hWin.HEURIST4.util.isempty(original_request.search_realm)){
                    window.hWin.HAPI4.currentRecordset = recordset;
                }
                
                // Trigger ON_REC_SEARCH_FINISH event in the original document context
                if(!window.hWin.HEURIST4.util.isnull(_owner_doc[qid])){ 
                    $(_owner_doc[qid]).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH,
                                {
                                    search_realm: original_request.search_realm,
                                    recordset: recordset,      // The resulting HRecordSet
                                    request: original_request, // The original request object
                                    query: original_request.q  // The original query string
                                });
                }
                
                // Clean up stored request and owner document context
                delete _query_request[qid];
                delete _owner_doc[qid];
            } else if (response && response.status !== window.hWin.ResponseStatus.OK) {
                // If queryid is not found or response is an error without a matching query
                 window.hWin.HEURIST4.msg.showMsgErr(response);
            }
    }
    
    function _searchTerminate(){
        _query_request = null;
        _owner_doc = null;
    }

    function _doApplyRules( originator, rules, rulesonly, search_realm ){
        
        // Check if there's a current recordset with records to apply rules to
        if(window.hWin.HAPI4.currentRecordset &&
           window.hWin.HEURIST4.util.isArrayNotEmpty(window.hWin.HAPI4.currentRecordset.getOrder())){
        
            let request = {
                apply_rules: true, // Signal to server this is a rule application
                q: 'ids:' + window.hWin.HAPI4.currentRecordset.getOrder().join(','), // Query based on current record IDs
                rules: rules,
                rulesonly: rulesonly,
                search_realm: search_realm,
                // Preserve 'w' (search domain: all, bookmark, everything) from original query if available, default to 'a' (all?)
                w: (_query_request && _query_request.w) ? _query_request.w : 'a'
            };
        
            _doSearch( originator, request ); // Execute the new search with applied rules
            return true;
        }else{
            // No current recordset or it's empty, so rules cannot be applied
            return false;
        }
    }
    
    // Public members object
    let that = {

        /**
         * Gets the class name.
         * @returns {string} The class name "HRecordSearch".
         */
        getClass: function () {return _className;},
        /**
         * Checks if the provided string matches the class name.
         * @param {string} strClass - The class name to compare.
         * @returns {boolean} True if `strClass` is "HRecordSearch", false otherwise.
         */
        isA: function (strClass) {return (strClass === _className);},
        /**
         * Gets the version of this search manager.
         * @returns {string} The version number.
         */
        getVersion: function () {return _version;},

        /**
         * Public method to perform a search and handle results with a callback.
         * Wraps `_doSearchWithCallback`.
         * @param {Object} request - The search request object.
         * @param {function(HRecordSet|null): void} callback - Callback for search results.
         * @returns {void}
         */
        doSearchWithCallback: function(request, callback){
            _doSearchWithCallback(request, callback);
        },
        
       /**
        * Public method to perform a search that triggers global events for start and finish.
        * Wraps `_doSearch`. Results are available via `ON_REC_SEARCH_FINISH` event and
        * potentially `HAPI4.currentRecordset`.
        * @param {Document|{document: Document, element: jQuery}} originator - The initiating context.
        * @param {Object} request - The search request object.
        * @returns {void}
        */
        doSearch:function( originator, request ){
            _doSearch( originator, request );
        },
 
        /**
         * Public method to apply rules to the current global result set (`HAPI4.currentRecordset`).
         * Wraps `_doApplyRules`.
         * @param {Document|{document: Document, element: jQuery}} originator - The initiating context.
         * @param {Object} rules - Rules to apply.
         * @param {boolean} rulesonly - If true, only these rules are applied.
         * @param {string} [search_realm] - Optional search realm.
         * @returns {boolean} True if rules were applied, false otherwise.
         */
        doApplyRules:function( originator, rules, rulesonly, search_realm ){
            return _doApplyRules( originator, rules, rulesonly, search_realm );
        },
        
        /**
         * Public method to stop or reset ongoing search activities.
         * Wraps `_searchTerminate`.
         * @returns {void}
         */
        doStop: function(){
            _searchTerminate();
        }
    };

    return that;  // Returns the public interface object
}