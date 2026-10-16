/**
 * @file utilsCollection.js
 * @brief Utilities for managing a user's browser-local record collection.
 * @fileOverview This file provides utility functions under the
 * `window.hWin.HEURIST4.collection` namespace for managing a collection of
 * record IDs. The collection is stored in browser localStorage separately for
 * each Heurist database. Functions include adding, removing, clearing, showing,
 * saving and loading the collection. Changes trigger the `ON_REC_COLLECT` event.
 * @project     Heurist academic knowledge management system
 *
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

// Init only once.
if (!window.hWin.HEURIST4.collection)
{
    window.hWin.HEURIST4.collection = {

        _collection: null,
        _storagePrefix: 'heurist-record-collection:',

        /**
         * Returns the localStorage key for a database.
         * @param {string} [database] Heurist database name.
         * @returns {string} Storage key.
         */
        _getStorageKey: function(database){
            database = database || window.hWin.HAPI4.database || '';
            return this._storagePrefix + database;
        },

        /**
         * Loads and validates the collection stored for a database.
         * @param {string} [database] Heurist database name.
         * @returns {Array<string>} Stored record IDs.
         */
        _loadCollection: function(database){
            let collection = [];

            try{
                let storedValue = window.localStorage.getItem(this._getStorageKey(database));
                if(storedValue){
                    let storedCollection = JSON.parse(storedValue);
                    if(Array.isArray(storedCollection)){
                        collection = storedCollection
                            .map(function(id){ return String(id); })
                            .filter(function(id){ return /^\d+$/.test(id); });
                    }
                }
            }catch(error){
                // localStorage may be unavailable or contain invalid legacy data.
                collection = [];
            }

            return Array.from(new Set(collection));
        },

        /**
         * Stores the current collection for a database.
         * @param {string} [database] Heurist database name.
         * @returns {boolean} True when stored successfully.
         */
        _storeCollection: function(database){
            try{
                window.localStorage.setItem(
                    this._getStorageKey(database),
                    JSON.stringify(this._collection || [])
                );
                return true;
            }catch(error){
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: 'Unable to save the record collection in browser local storage.',
                    error: error
                });
                return false;
            }
        },

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
                window.hWin.HEURIST4.msg.showMsg(window.hWin.HR('collection_limit') + limit,
                    {default_palette_class:'ui-heurist-explore'});
            }else{
                return recIDs_list;
            }
            return null;
        },

        /**
         * Adds records to the user's collection.
         * @param {string|Array<string|number>} [recIDs] Record IDs to add.
         * @param {HRecordSet|null} [_selection] Selection used when recIDs is omitted.
         * @returns {void}
         */
        collectionAdd: function(recIDs, _selection){
            if(!recIDs){
                let recIDs_list = this.getSelectionIds(_selection,
                    window.hWin.HR('collection_select_hint'));
                if(window.hWin.HEURIST4.util.isempty(recIDs_list)) return;
                recIDs = recIDs_list.join(',');
            }
            this.collectionUpdate({db:window.hWin.HAPI4.database, add:recIDs});
        },

        /**
         * Removes records from the user's collection.
         * @param {string|Array<string|number>} [recIDs] Record IDs to remove.
         * @param {HRecordSet|null} [_selection] Selection used when recIDs is omitted.
         * @returns {void}
         */
        collectionDel: function(recIDs, _selection){
            if(!recIDs){
                let recIDs_list = this.getSelectionIds(_selection,
                    window.hWin.HR('collection_select_hint2'));
                if(window.hWin.HEURIST4.util.isempty(recIDs_list)) return;
                recIDs = recIDs_list.join(',');
            }
            this.collectionUpdate({db:window.hWin.HAPI4.database, remove:recIDs});
        },

        /**
         * Clears all records from the user's collection.
         * @returns {void}
         */
        collectionClear: function(){
            this.collectionUpdate({db:window.hWin.HAPI4.database, clear:1});
        },

        /**
         * Opens a new window/tab displaying the records currently in the user's collection.
         * @returns {void}
         */
        collectionShow: function(){
            if(!window.hWin.HEURIST4.util.isempty(this._collection)){
                let url = window.hWin.HAPI4.baseURL + '?db=' + window.hWin.HAPI4.database + '&q=ids:'
                    + this._collection.join(',');
                if(url.length>2083){
                    window.hWin.HEURIST4.msg.showMsgDlg(
                        window.hWin.HR('collection_url_hint'), null, window.hWin.HR('Warning'),
                        {default_palette_class:'ui-heurist-explore'}
                    );
                }else{
                    window.open(url, '_blank');
                }
            }
        },

        /**
         * Saves the current collection as a saved search/filter.
         * @returns {void}
         */
        collectionSave: function(){
            if(!window.hWin.HEURIST4.util.isempty(this._collection)){
                let widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('svs_list');
                if(widget){
                    widget.svs_list('editSavedSearch', 'saved', null, null,
                        'ids:' + this._collection.join(','));
                }
            }
        },

        /**
         * Loads or modifies the browser-local collection and triggers ON_REC_COLLECT.
         * The params signature is retained for compatibility with existing callers.
         * @param {Object} [params] Database and action parameters: add, remove or clear.
         * @returns {void}
         */
        collectionUpdate: function(params){
            params = params || {db:window.hWin.HAPI4.database, fetch:1};

            let database = params.db || window.hWin.HAPI4.database;
            let collection = this._loadCollection(database);
            let hasChanges = false;

            if(params.clear !== undefined){
                collection = [];
                hasChanges = true;
            }else{
                if(params.add !== undefined){
                    let addIDs = (Array.isArray(params.add) ? params.add : String(params.add).split(','))
                        .map(function(id){ return String(id).trim(); })
                        .filter(function(id){ return /^\d+$/.test(id); });
                    collection = Array.from(new Set(collection.concat(addIDs)));
                    hasChanges = true;
                }

                if(params.remove !== undefined){
                    let removeIDs = new Set(
                        (Array.isArray(params.remove) ? params.remove : String(params.remove).split(','))
                            .map(function(id){ return String(id).trim(); })
                            .filter(function(id){ return /^\d+$/.test(id); })
                    );
                    collection = collection.filter(function(id){ return !removeIDs.has(id); });
                    hasChanges = true;
                }
            }

            this._collection = collection;

            if(hasChanges && !this._storeCollection(database)){
                this._collection = this._loadCollection(database);
            }

            $(window.hWin.document).trigger(
                window.hWin.HAPI4.Event.ON_REC_COLLECT,
                {collection:this._collection}
            );
        }
    };
}
