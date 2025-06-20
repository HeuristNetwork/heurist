/**
 * @file utilsCollection.js
 * @brief Utilities for managing a user's session-based record collection.
 * @fileOverview This file provides a set of utility functions under the `window.hWin.HEURIST4.collection`
 * namespace for managing a user's collection of records. The collection is typically stored in the
 * user's session on the server. Functions include adding records to the collection, removing records,
 * clearing the collection, showing the collection (which opens a new window with a search for the
 * collected record IDs), saving the collection as a saved search/filter, and updating the collection
 * state by communicating with the `collectionController.php` on the server. It also triggers the
 * `ON_REC_COLLECT` event when the collection changes.
 * @package Heurist academic knowledge management system
 * @subpackage hclient\core
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 4.0
 */

if (!window.hWin.HEURIST4){
    window.hWin.HEURIST4 = {};
}
//init only once
if (!window.hWin.HEURIST4.collection) 
{
    window.hWin.HEURIST4.collection = {

        _collection: null,
        _collectionURL: 'hserv/controller/collectionController.php',

        /**
         * Retrieves a list of record IDs from a selection, shows a message if empty or limit exceeded.
         * @param {HRecordSet|null} _selection - The HRecordSet object representing the current selection.
         * @param {string} [msg] - Message to show if no records are selected.
         * @param {number} [limit] - Maximum number of IDs allowed. If exceeded, a message is shown.
         * @returns {Array<string>|null} An array of record IDs, or null if no selection or limit exceeded.
         */
        getSelectionIds: function(_selection, msg, limit){
            let recIDs_list = [];
            if (_selection!=null) {
                recIDs_list = _selection.getIds();
            }

            if (recIDs_list.length == 0 && !window.hWin.HEURIST4.util.isempty(msg)) {
                window.hWin.HEURIST4.msg.showMsg(msg, {default_palette_class:'ui-heurist-explore'});
                return null;
            }else if (limit>0 && recIDs_list.length > limit) {
                window.hWin.HEURIST4.msg.showMsg( window.hWin.HR('collection_limit') + limit, {default_palette_class:'ui-heurist-explore'});
                // Should ideally return null or handle the limit exceeded case more explicitly here
            }else{
                return recIDs_list;
            }
            return null; // Explicitly return null if limit exceeded and message shown
        },

        /**
         * Adds records to the user's collection.
         * Records can be specified by a list of IDs or taken from the current selection.
         * @param {string|Array<string|number>} [recIDs] - A comma-separated string or an array of record IDs to add.
         *                                                If not provided, uses `_selection`.
         * @param {HRecordSet|null} [_selection] - The HRecordSet from which to get record IDs if `recIDs` is not provided.
         * @returns {void}
         */
        collectionAdd: function(recIDs, _selection){
            if(!recIDs){
                let recIDs_list = window.hWin.HEURIST4.collection.getSelectionIds(_selection, 
                    window.hWin.HR('collection_select_hint'));
                if(window.hWin.HEURIST4.util.isempty(recIDs_list)) return;
                recIDs = recIDs_list.join(',')
            }
            let params = {db:window.hWin.HAPI4.database, fetch:1, add:recIDs};
            window.hWin.HEURIST4.collection.collectionUpdate(params);
        },

        /**
         * Removes records from the user's collection.
         * Records can be specified by a list of IDs or taken from the current selection.
         * @param {string|Array<string|number>} [recIDs] - A comma-separated string or an array of record IDs to remove.
         *                                                If not provided, uses `_selection`.
         * @param {HRecordSet|null} [_selection] - The HRecordSet from which to get record IDs if `recIDs` is not provided.
         * @returns {void}
         */
        collectionDel: function(recIDs, _selection){
            if(!recIDs){
                let recIDs_list = this.getSelectionIds(_selection,
                    window.hWin.HR('collection_select_hint2'));
                if(window.hWin.HEURIST4.util.isempty(recIDs_list)) return;
                recIDs = recIDs_list.join(',')
            }
            let params = {db:window.hWin.HAPI4.database, fetch:1, remove:recIDs};
            window.hWin.HEURIST4.collection.collectionUpdate(params);
        },

        /**
         * Clears all records from the user's collection.
         * @returns {void}
         */
        collectionClear: function(){
            let params = {db:window.hWin.HAPI4.database, clear:1};
            window.hWin.HEURIST4.collection.collectionUpdate(params);
        },

        /**
         * Opens a new window/tab displaying the records currently in the user's collection.
         * Warns if the generated URL might be too long.
         * @returns {void}
         */
        collectionShow: function(){
            if(!window.hWin.HEURIST4.util.isempty(window.hWin.HEURIST4.collection._collection)){
                let url = window.hWin.HAPI4.baseURL + "?db=" + window.hWin.HAPI4.database + "&q=ids:"
                    +window.hWin.HEURIST4.collection._collection.join(',')+'&nometadatadisplay=true';
                if(url.length>2083){ // URL length limit, common for IE
                    window.hWin.HEURIST4.msg.showMsgDlg(
                        window.hWin.HR('collection_url_hint'), null, window.hWin.HR('Warning'), {default_palette_class:'ui-heurist-explore'}
                    );
                }else{
                    window.open(url, '_blank');
                }
            }
        },

        /**
         * Saves the current collection of records as a new saved search/filter.
         * Uses the 'svs_list' widget to handle the save operation.
         * @returns {void}
         */
        collectionSave: function(){
            if(!window.hWin.HEURIST4.util.isempty(window.hWin.HEURIST4.collection._collection)){
                let widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('svs_list');
                if(widget){
                    widget.svs_list('editSavedSearch', 'saved', null, null, 'ids:'
                        +window.hWin.HEURIST4.collection._collection.join(","));
                }
            }
        },

        /**
         * Updates the collection on the server and then updates the local `_collection` state.
         * Triggers the `ON_REC_COLLECT` event.
         * @param {Object} params - Parameters to send to the collection controller.
         *                          Should include `db` and action (e.g., `add`, `remove`, `clear`, `fetch`).
         * @returns {void}
         */
        collectionUpdate: function(params){
            // Internal callback, JSDoc not needed as per rules
            function __collectionOnUpdate(that, results) {
                if(!window.hWin.HEURIST4.util.isnull(results)){
                    if(results.status == window.hWin.ResponseStatus.UNKNOWN_ERROR){
                        window.hWin.HEURIST4.msg.showMsgErr(results);
                    }else{
                        window.hWin.HEURIST4.collection._collection = window.hWin.HEURIST4.util.isempty(results.ids)?[]:results.ids;
                        $(window.hWin.document).trigger( window.hWin.HAPI4.Event.ON_REC_COLLECT, 
                            {collection:window.hWin.HEURIST4.collection._collection} );
                    }
                }
            }
            if(!params){
                params = {db:window.hWin.HAPI4.database, fetch:1};
            }
            window.hWin.HEURIST4.util.sendRequest(window.hWin.HAPI4.baseURL + window.hWin.HEURIST4.collection._collectionURL, 
                params, this, 
                __collectionOnUpdate);
        }
    }
}
