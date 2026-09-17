/**
 * @file HeuristModuleRecordView.js
 * @brief Host-side adapter for the independent heurist-recordview application.
 * @project     Heurist academic knowledge management system
 * @package     hclient.modules.recordview
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2024 onwards Heurist Network
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @license     This software may be installed and operated only on servers operated by Heurist Network, or with the prior written permission of Heurist Network. No right is granted to copy, distribute, modify, install or operate this software elsewhere.
 * @since       8.0
 */

const HEURIST_MODULE_RECORDVIEW_DEFAULTS = {
    presentationMode: 'iframe',
    recordViewApplicationUrl: null,
    runtimeMode: null,              // main | website | standalone; defaults to 'main'
    database: window.hWin && window.hWin.HAPI4 ? window.hWin.HAPI4.database : null,
    apiBaseUrl: window.hWin && window.hWin.HAPI4 ? window.hWin.HAPI4.baseURL + 'api' : null,
    accessToken: null,
    requestHeaders: null,
    recordId: null,
    selection: null,
    heuristModuleSettings: null,
    heuristModuleState: null,
    eventbased: true,
    search_realm: null,
    onready: null,
    onselect: null,
    onconfiguration: null,
    onerror: null
};

/**
 * Hosts heurist-recordview in an iframe and bridges the shared selection only.
 *
 * Unlike Data/Map/Graph/Timeline, RecordView never runs its own search and
 * never consumes the host's query - it only ever follows the shared record
 * selection - so this extends `HeuristModuleViewer` directly rather than
 * `HeuristModuleRecordset` (whose entire purpose is query/search bridging).
 * `HeuristModuleExplorer` is the only other module using this same base, for
 * the same reason: it doesn't consume an inbound query either.
 */
class HeuristModuleRecordView extends HeuristModuleViewer {
    constructor(element, options) {
        super(element, $.extend(true, {}, HEURIST_MODULE_RECORDVIEW_DEFAULTS, options || {}));
        this._create();
    }

    _create() {
        this._dataEventHandlers = {};
        this._suppressSelectionSync = false;
        this._moduleApi = null;
        this._moduleFrame = null;
        this._isReady = false;
        this._isDestroyed = false;
        this._pendingOperations = [];
        this._readyTimer = 0;
        this._resizeTimer = 0;
        this._resizeObserver = null;
        this._moduleBootstrap = null;
        this._events = null;
        this._bindHostEvents();
        this._bindShowEvent();
        this._observeResize();
        this._ensureIframeWhenVisible();
    }

    /**
     * Bind only the host events RecordView actually needs: selection follows
     * the shared record selection, and layout resize. There is no
     * ON_REC_SEARCHSTART/ON_REC_SEARCH_FINISH handling - RecordView never
     * runs a search of its own and never reacts to one.
     */
    _bindHostEvents() {
        if (!this.options.eventbased) return;

        const that = this;
        const hapi = window.hWin && window.hWin.HAPI4;
        if (!hapi || !hapi.Event) return;

        this._events = hapi.Event.ON_LAYOUT_RESIZE + ' ' + hapi.Event.ON_REC_SELECT;

        $(this.document).on(this._events, function(event, data) {
            if (event.type === hapi.Event.ON_LAYOUT_RESIZE) {
                that._scheduleResize(400);
            } else if (event.type === hapi.Event.ON_REC_SELECT) {
                if (!that._isSameRealm(data) || (data && data.source === that.element.attr('id'))) return;
                if (data && data.reset) {
                    that.clear();
                } else {
                    const ids = hapi.getSelection
                        ? hapi.getSelection(data && data.selection, true)
                        : that._normalizeRecordIds(data && data.selection);
                    that.setSelection(ids, {replace: true});
                }
            }
        });
    }

    /** Unbind the shared host events. Called by `_destroy()`. */
    _unbindHostEvents() {
        if (this._events) $(this.document).off(this._events);
        this._events = null;
    }

    _createIframe() {
        if (this._moduleFrame || this._isDestroyed) return;
        const url = this.options.recordViewApplicationUrl || this._defaultApplicationUrl();
        if (!url) {
            this._reportError(new Error('recordViewApplicationUrl is not defined'), 'initialize');
            return;
        }
        this._moduleBootstrap = this._buildBootstrap();
        this._moduleFrame = $('<iframe>').attr({
            title: 'Heurist record view', frameborder: '0'
        }).css({width: '100%', height: '100%', border: 0, display: 'block'}).appendTo(this.element);
        this._installIframeBridge();
        this._moduleFrame.on('load.heuristModuleRecordView', function() {
            this._installIframeBridge();
            this._unbindDataEvents();
            this._moduleApi = null;
            this._isReady = false;
            this._waitForRecordViewApi();
        }.bind(this));
        this._moduleFrame.attr('src', url);
    }

    _defaultApplicationUrl() {
        const hapi = window.hWin && window.hWin.HAPI4;
        return hapi ? hapi.baseURL + 'hclient/modules/recordview/recordviewViewer.html' : null;
    }

    _installIframeBridge() {
        const frame = this._moduleFrame && this._moduleFrame[0];
        if (!frame) return;
        const that = this;
        frame.heuristRecordviewHost = {
            getConfiguration: function() { return that._getConfiguration(); },
            updateSettings: function(settings) { return that._updateSettings(settings); },
            updateState: function(state) { return that._updateState(state); },
            editRecord: function(recordId) { return that._openRecordEdit(recordId); },
            viewRecord: function(recordId) { return that._openRecordView(recordId); },
            addRecord: function(recordTypeId) { return that._addRecordEdit(recordTypeId); },
            // Used only when the child explicitly navigates to a linked
            // record (RecordViewApplication#navigateToRecord) - never for the
            // passive setSelection() path. See `_dataEventHandlers.selection`.
            onSelection: function(recordIds) { return that._onChildSelection(recordIds); }
        };
    }

    _buildBootstrap() {
        const hapi = window.hWin && window.hWin.HAPI4;
        const lang = hapi
            ? hapi.getLangCode3(hapi.get_prefs_def('layout_language', 'eng'), 'eng')
            : 'eng';
        const runtimeMode = ['main', 'website', 'standalone'].indexOf(this.options.runtimeMode) >= 0
            ? this.options.runtimeMode : 'main';
        return {
            runtime: {
                runtimeMode: runtimeMode,
                language: lang,
                database: this.options.database || (hapi && hapi.database),
                apiBaseUrl: this.options.apiBaseUrl || (hapi && hapi.baseURL + 'api'),
                accessToken: this.options.accessToken || null,
                requestHeaders: $.extend({}, this.options.requestHeaders || {}),
                baseUrl: hapi ? hapi.baseURL : null,
                source: this.element.attr('id') || null,
                searchRealm: this.options.search_realm || null
            },
            settings: $.extend(true, {}, this.options.heuristModuleSettings || {}),
            state: this.options.heuristModuleState == null ? null
                : $.extend(true, {}, this.options.heuristModuleState),
            source: {
                recordId: this.options.recordId || null,
                selection: this._normalizeRecordIds(this.options.selection)
            }
        };
    }

    _waitForRecordViewApi() {
        const that = this;
        let attempts = 0;
        if (this._readyTimer) clearInterval(this._readyTimer);
        this._readyTimer = setInterval(function() {
            if (that._isDestroyed) { clearInterval(that._readyTimer); return; }
            attempts++;
            const frameWindow = that._moduleFrame && that._moduleFrame[0].contentWindow;
            const api = frameWindow && frameWindow.heuristRecordview;
            if (api) {
                clearInterval(that._readyTimer);
                that._readyTimer = 0;
                that._completeInitialization(api);
            } else if (attempts >= 300) {
                clearInterval(that._readyTimer);
                that._readyTimer = 0;
                that._reportError(new Error('heurist-recordview did not expose window.heuristRecordview'), 'initialize');
            }
        }, 100);
    }

    _completeInitialization(api) {
        const that = this;
        Promise.resolve(api.ready ? api.ready() : api).then(function() {
            if (that._isDestroyed) return;
            that._unbindDataEvents();
            that._moduleApi = api;
            that._isReady = true;
            that._bindDataEvents();
            that._invokeCallback('onready', api);
            return that._flushPendingOperations();
        }).catch(function(error) { that._reportError(error, 'initialize'); });
    }

    /** Subscribe to engine-neutral events emitted by the public Record View API. */
    _bindDataEvents() {
        if (!this._moduleApi || typeof this._moduleApi.addEventListener !== 'function') return;
        const that = this;
        this._dataEventHandlers.selection = function(event) {
            if (that._suppressSelectionSync) return;
            const ids = that._normalizeRecordIds(event.detail && event.detail.selection);
            that.options.selection = ids;
            const hapi = window.hWin && window.hWin.HAPI4;
            if (that.options.eventbased && hapi && hapi.Event) {
                $(that.document).trigger(hapi.Event.ON_REC_SELECT, {
                    selection: ids,
                    source: that.element.attr('id'),
                    search_realm: that.options.search_realm,
                    reset: ids.length === 0
                });
            }
            that._invokeCallback('onselect', ids, event.detail || {});
        };
        this._dataEventHandlers.error = function(event) {
            const detail = event.detail || {};
            that._reportError(detail.error || detail, detail.operation || 'recordview-event', detail);
        };
        this._dataEventHandlers.configuration = function(event) {
            that._invokeCallback('onconfiguration', event.detail || {});
        };

        // Fires only from RecordViewApplication#navigateToRecord (explicit
        // in-content navigation to a linked record) - never from the passive
        // setSelection() follower path. See recordview's own architecture
        // notes: selection sync is one-way by default.
        this._moduleApi.addEventListener(
            'heurist-recordview-selection-changed', this._dataEventHandlers.selection);
        this._moduleApi.addEventListener(
            'heurist-recordview-error', this._dataEventHandlers.error);
        this._moduleApi.addEventListener(
            'heurist-recordview-configuration-changed', this._dataEventHandlers.configuration);
    }

    _unbindDataEvents() {
        if (!this._moduleApi || typeof this._moduleApi.removeEventListener !== 'function') return;
        const handlers = this._dataEventHandlers;
        const events = {
            selection: 'heurist-recordview-selection-changed',
            error: 'heurist-recordview-error',
            configuration: 'heurist-recordview-configuration-changed'
        };
        Object.keys(events).forEach(function(key) {
            if (handlers[key]) this._moduleApi.removeEventListener(events[key], handlers[key]);
        }, this);
        this._dataEventHandlers = {};
    }

    /** Echo an explicit child-side navigation onto the host's shared selection channel. */
    _onChildSelection(recordIds) {
        const hapi = window.hWin && window.hWin.HAPI4;
        const normalized = this._normalizeRecordIds(recordIds);
        if (this.options.eventbased && hapi && hapi.Event) {
            $(this.document).trigger(hapi.Event.ON_REC_SELECT, {
                selection: normalized,
                source: this.element.attr('id'),
                search_realm: this.options.search_realm,
                reset: normalized.length === 0
            });
        }
        return normalized;
    }

    /** Directly display one record, bypassing the selection-array policy. */
    setRecord(recordId) {
        this.options.recordId = Number(recordId) || null;
        return this._enqueueOrRun('_setRecordNow', [this.options.recordId]);
    }

    _setRecordNow(recordId) {
        return this._moduleApi.setRecord(recordId);
    }

    /** Apply a new shared selection, choosing the primary record per the child's `selectionMode`. */
    setSelection(recordIds, options) {
        this.options.selection = this._normalizeRecordIds(recordIds);
        return this._enqueueOrRun('_setSelectionNow', [this.options.selection, options || {}]);
    }

    async _setSelectionNow(recordIds, options) {
        this._suppressSelectionSync = true;
        try {
            return await this._moduleApi.setSelection(recordIds, options || {});
        } finally {
            this._suppressSelectionSync = false;
        }
    }

    /** Clear the current selection and displayed record. */
    clear() {
        this.options.recordId = null;
        this.options.selection = [];
        return this._enqueueOrRun('_clearNow', []);
    }

    _clearNow() {
        return this._moduleApi.clear();
    }

    /** Merge partial option overrides (engine/template/selectionMode/...) and re-render. */
    setOptions(options) {
        return this._enqueueOrRun('_setOptionsNow', [options || {}]);
    }

    _setOptionsNow(options) {
        return this._moduleApi.setOptions(options || {});
    }

    getState() { return this._moduleApi ? this._moduleApi.getState() : null; }
    getRecordViewApi() { return this._moduleApi; }
    getModuleApi() { return this._moduleApi; }
    isReady() { return this._isReady; }

    /** Open the standard Heurist record editor for an existing record. */
    _openRecordEdit(recordId) {
        const id = Number(recordId);
        if (!Number.isInteger(id) || id < 1) {
            return Promise.reject(new Error('A valid Heurist record ID is required for editing'));
        }
        const ui = window.hWin && window.hWin.HEURIST4 && window.hWin.HEURIST4.ui;
        if (!ui || typeof ui.openRecordEdit !== 'function') {
            return Promise.reject(new Error('Heurist record editor is not available'));
        }
        return new Promise(function(resolve, reject) {
            let settled = false;
            let saved = false;
            function finish(result) {
                if (settled) return;
                settled = true;
                resolve(result);
            }
            try {
                ui.openRecordEdit(id, null, {
                    selectOnSave: true,
                    onselect: function() {
                        saved = true;
                        finish({saved: true, recordId: id});
                    },
                    onClose: function() {
                        if (!saved) finish({saved: false, recordId: id});
                    }
                });
            } catch (error) {
                reject(error);
            }
        });
    }

    /** Open the legacy Heurist record creator for the requested record type. */
    _addRecordEdit(recordTypeId) {
        const rtID = Number(recordTypeId);
        if (!(rtID > 0)) {
            return Promise.reject(new Error('A valid Heurist record type ID is required for creation'));
        }
        const ui = window.hWin && window.hWin.HEURIST4 && window.hWin.HEURIST4.ui;
        if (!ui || typeof ui.openRecordEdit !== 'function') {
            return Promise.reject(new Error('Heurist record editor is not available'));
        }
        return new Promise(function(resolve, reject) {
            let settled = false;
            let saved = false;
            function finish(result) {
                if (settled) return;
                settled = true;
                resolve(result);
            }
            try {
                ui.openRecordEdit(-1, null, {
                    selectOnSave: true,
                    onselect: function(event, data) {
                        saved = true;
                        const recordset = data && data.selection;
                        const record = recordset && recordset.getFirstRecord ? recordset.getFirstRecord() : null;
                        const recordId = record && recordset.fld ? Number(recordset.fld(record, 'rec_ID')) : null;
                        finish({saved: true, recordId: recordId});
                    },
                    onClose: function() {
                        if (!saved) finish({saved: false, recordId: null});
                    },
                    new_record_params: {rt: rtID}
                });
            } catch (error) {
                reject(error);
            }
        });
    }

    /** Open the standard read-only record card in the parent application. */
    _openRecordView(recordId) {
        const id = Number(recordId);
        if (!Number.isInteger(id) || id < 1) {
            return Promise.reject(new Error('A valid Heurist record ID is required'));
        }
        const hapi = window.hWin && window.hWin.HAPI4;
        if (!hapi || !hapi.baseURL) {
            return Promise.reject(new Error('Heurist record viewer is not available'));
        }
        const url = hapi.baseURL + 'viewers/record/renderRecordData.php?db=' +
            encodeURIComponent(hapi.database) + '&ll=WebSearch&recID=' + id;
        window.hWin.open(url, '_blank', 'noopener');
        return Promise.resolve(true);
    }

    /**
     * Generic iframe-bridge configuration bootstrap, matching the shared
     * shape `HeuristModuleRecordset` uses for Data/Map/Graph/Timeline.
     */
    _getConfiguration() {
        return $.extend(true, {}, this._moduleBootstrap || this._buildBootstrap());
    }

    _updateSettings(settings) {
        const normalized = $.extend(true, {}, settings || {});
        this.options.heuristModuleSettings = normalized;
        this._moduleBootstrap = this._buildBootstrap();
        return $.extend(true, {}, normalized);
    }

    _updateState(state) {
        this.options.heuristModuleState = state == null ? null : $.extend(true, {}, state);
        this._moduleBootstrap = this._buildBootstrap();
    }

    _destroy() {
        this._isDestroyed = true;
        if (this._readyTimer) clearInterval(this._readyTimer);
        if (this._resizeTimer) clearTimeout(this._resizeTimer);
        this._resizeObserver?.disconnect();
        this._unbindHostEvents();
        this._unbindDataEvents();
        const api = this._moduleApi;
        this._moduleApi = null;
        return api ? api.destroy() : Promise.resolve();
    }
}

window.HeuristModuleRecordView = HeuristModuleRecordView;
