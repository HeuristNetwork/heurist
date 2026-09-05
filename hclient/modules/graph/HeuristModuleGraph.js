/**
 * @file HeuristModuleGraph.js
 * @brief Host-side adapter for the independent heurist-graph application.
 * @project     Heurist academic knowledge management system
 * @package     hclient.modules.graph
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2024 onwards Heurist Network
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @since       8.0
 */

const HEURIST_MODULE_GRAPH_DEFAULTS = {
    presentationMode: 'iframe',
    graphApplicationUrl: null,
    database: window.hWin && window.hWin.HAPI4 ? window.hWin.HAPI4.database : null,
    apiBaseUrl: window.hWin && window.hWin.HAPI4 ? window.hWin.HAPI4.baseURL + 'api' : null,
    rules: [],
    fields: ['rec_Title', 'rec_RecTypeID'],
    query: null,
    selection: null,
    eventbased: true,
    search_realm: null,
    onready: null,
    onselect: null,
    onerror: null
};

/** Hosts heurist-graph in an iframe and bridges record queries and selection. */
class HeuristModuleGraph extends HeuristModuleRecordset {
    constructor(element, options) {
        super(element, $.extend(true, {}, HEURIST_MODULE_GRAPH_DEFAULTS, options || {}));
        this._create();
    }

    _create() {
        this._moduleApi = null;
        this._moduleFrame = null;
        this._isReady = false;
        this._isDestroyed = false;
        this._pendingOperations = [];
        this._readyTimer = 0;
        this._resizeTimer = 0;
        this._resizeObserver = null;
        this._graphBootstrap = null;
        this._bindHostEvents();
        this._bindShowEvent();
        this._observeResize();
        this._ensureIframeWhenVisible();
    }

    _createIframe() {
        if (this._moduleFrame || this._isDestroyed) return;
        const url = this.options.graphApplicationUrl || this._defaultApplicationUrl();
        if (!url) {
            this._reportError(new Error('graphApplicationUrl is not defined'), 'initialize');
            return;
        }
        this._graphBootstrap = this._buildBootstrap();
        this._moduleFrame = $('<iframe>').attr({
            title: 'Heurist graph', frameborder: '0'
        }).css({width: '100%', height: '100%', border: 0, display: 'block'}).appendTo(this.element);
        this._installIframeBridge();
        this._moduleFrame.on('load.heuristModuleGraph', function() {
            this._installIframeBridge();
            this._moduleApi = null;
            this._isReady = false;
            this._waitForGraphApi();
        }.bind(this));
        this._moduleFrame.attr('src', url);
    }

    _defaultApplicationUrl() {
        const hapi = window.hWin && window.hWin.HAPI4;
        return hapi ? hapi.baseURL + 'hclient/modules/graph/graphViewer.html' : null;
    }

    _installIframeBridge() {
        const frame = this._moduleFrame && this._moduleFrame[0];
        if (!frame) return;
        const that = this;
        frame.heuristGraphHost = {
            getConfiguration: function() { return $.extend(true, {}, that._graphBootstrap || that._buildBootstrap()); },
            updateState: function(state) { that.options.state = state == null ? null : $.extend(true, {}, state); },
            editRecord: function(recordId) { return that._openRecordEdit(recordId); },
            viewRecord: function(recordId) { return that._openRecordView(recordId); },
            addRecord: function(recordTypeId) { return that._addRecordEdit(recordTypeId); },
            doSearch: function(request) { return that._doSearch(request); },
            onSelection: function(recordIds) { return that._invokeCallback('onselect', recordIds); }
        };
    }

    _buildBootstrap() {
        const hapi = window.hWin && window.hWin.HAPI4;
        return {
            runtime: {
                database: this.options.database || (hapi && hapi.database),
                apiBaseUrl: this.options.apiBaseUrl || (hapi && hapi.baseURL + 'api'),
                baseUrl: hapi ? hapi.baseURL : null,
                source: this.element.attr('id') || null,
                searchRealm: this.options.search_realm || null
            },
            settings: {rules: this.options.rules, fields: this.options.fields},
            source: {
                query: this._normalizeQuery(this.options.query),
                selection: this._normalizeRecordIds(this.options.selection)
            }
        };
    }

    _waitForGraphApi() {
        const that = this;
        let attempts = 0;
        if (this._readyTimer) clearInterval(this._readyTimer);
        this._readyTimer = setInterval(function() {
            if (that._isDestroyed) { clearInterval(that._readyTimer); return; }
            attempts++;
            const frameWindow = that._moduleFrame && that._moduleFrame[0].contentWindow;
            const api = frameWindow && frameWindow.heuristGraph;
            if (api) {
                clearInterval(that._readyTimer);
                that._readyTimer = 0;
                that._completeGraphInitialization(api);
            } else if (attempts >= 300) {
                clearInterval(that._readyTimer);
                that._readyTimer = 0;
                that._reportError(new Error('heurist-graph did not expose window.heuristGraph'), 'initialize');
            }
        }, 100);
    }

    _completeGraphInitialization(api) {
        const that = this;
        Promise.resolve(api.ready ? api.ready() : api).then(function() {
            if (that._isDestroyed) return;
            that._moduleApi = api;
            that._isReady = true;
            api.addEventListener?.('heurist-graph-selection-changed', function(event) {
                that._invokeCallback('onselect', event.detail.recordIds);
            });
            that._invokeCallback('onready', api);
            return that._flushPendingOperations();
        }).catch(function(error) { that._reportError(error, 'initialize'); });
    }

    setQuery(query, options) {
        this.options.query = this._normalizeQuery(query);
        return this._enqueueOrRun('_setQueryNow', [this.options.query, options || {}]);
    }

    _setQueryNow(query) {
        return this._moduleApi.load({query, rules: this.options.rules});
    }

    setSelection(recordIds, options) {
        this.options.selection = this._normalizeRecordIds(recordIds);
        return this._enqueueOrRun('_setSelectionNow', [this.options.selection, options || {}]);
    }

    _setSelectionNow(recordIds) {
        return this._moduleApi.setSelection(recordIds);
    }

    expandNode(recordId) {
        return this._enqueueOrRun('_expandNodeNow', [recordId]);
    }

    _expandNodeNow(recordId) {
        return this._moduleApi.expandNode(recordId);
    }

    refresh() {
        return this.setQuery(this.options.query);
    }

    getGraphApi() { return this._moduleApi; }
    getModuleApi() { return this._moduleApi; }
    isReady() { return this._isReady; }

    _destroy() {
        this._isDestroyed = true;
        if (this._readyTimer) clearInterval(this._readyTimer);
        if (this._resizeTimer) clearTimeout(this._resizeTimer);
        this._resizeObserver?.disconnect();
        this._unbindHostEvents();
        const api = this._moduleApi;
        this._moduleApi = null;
        return api ? api.destroy() : Promise.resolve();
    }
}

window.HeuristModuleGraph = HeuristModuleGraph;
