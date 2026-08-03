/**
 * mapViewer.js - Heurist Map application wrapper
 *
 * Provides an iframe-based jQuery UI wrapper around the standalone heurist-map
 * application. The widget translates HRecordSet/query/selection state into the
 * stable HeuristMapPublicApi without exposing Leaflet or standalone internals.
 *
 * @project     Heurist academic knowledge management system
 * @package     hclient.widgets.viewers
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov <osmakov@gmail.com>
 * @since       7.0
 */

/**
 * @widget heurist.mapViewer
 * @description Same-origin iframe wrapper for the standalone heurist-map app.
 */
$.widget('heurist.mapViewer', {

    
    options: {
        presentationMode: 'iframe',

        database: window.hWin.HAPI4.database,
        apiBaseUrl: window.hWin.HAPI4.baseURL + 'api',
        mapApplicationUrl: window.hWin.HAPI4.baseURL+'hclient/widgets/viewers/mapViewer.html', 
        //mapApplicationUrl: '/heurist/external/heurist-map/index.html',

        serverUrl: null,
        accessToken: null,
        requestHeaders: null,

        heuristMapOptions: null,
        mapDocument: null,

        query: null,
        recordset: null,
        selection: null,

        eventbased: true,
        search_realm: null,
        current_search_filter: null,

        currentResultsLayer: {
            id: 'current-results',
            title: null,
            visible: true,
            selectable: true
        },

        onready: null,
        onselect: null,
        onerror: null,
        oneditdocument: null,
        oneditlayer: null
    },

    _create: function() {
        this._mapApi = null;
        this._isReady = false;
        this._isDestroyed = false;
        this._pendingOperations = [];
        this._currentQuery = null;
        this._currentLayerCreated = false;
        this._suppressSelectionSync = false;
        this._readyTimer = 0;
        this._resizeTimer = 0;
        this._resizeObserver = null;
        this._instanceId = this._createInstanceId();
        this._mapEventHandlers = {};
        this._events = null;

        this.element
            .addClass('heurist-map-viewer')
            .css({ position: 'relative', overflow: 'hidden' });

        this._frameContainer = $('<div>')
            .addClass('heurist-map-viewer-frame-container')
            .css({ position: 'absolute', inset: 0 })
            .appendTo(this.element);

        if (this.options.presentationMode !== 'iframe') {
            this._reportError(new Error(
                'Only iframe presentationMode is implemented by mapViewer'
            ), 'initialize');
            return;
        }

        this._bindHostEvents();
        this._createIframe();
        this._observeResize();
    },

    /** Bind Heurist application events when event-based synchronization is enabled. */
    _bindHostEvents: function() {
        if (!this.options.eventbased) return;

        var that = this;
        var hapi = window.hWin && window.hWin.HAPI4;
        if (!hapi || !hapi.Event) return;

        this._events = hapi.Event.ON_CREDENTIALS
            + ' ' + hapi.Event.ON_LAYOUT_RESIZE
            + ' ' + hapi.Event.ON_REC_SELECT
            + ' ' + hapi.Event.ON_SYSTEM_INITED
            + ' ' + hapi.Event.ON_REC_SEARCH_FINISH
            + ' ' + hapi.Event.ON_REC_SEARCHSTART;

        $(this.document).on(this._events, function(event, data) {
            if (event.type === hapi.Event.ON_CREDENTIALS) {
                if (that.options.recordset != null && !hapi.has_access()) {
                    that.options.recordset = null;
                    that.options.query = null;
                    that.options.selection = null;
                    that.setQuery(null);
                }

            } else if (event.type === hapi.Event.ON_LAYOUT_RESIZE) {
                that._scheduleResize(400);

            } else if (event.type === hapi.Event.ON_REC_SEARCH_FINISH) {
                if (!((data && data.search_realm === 'mapping_recordset') ||
                    that._isSameRealm(data))) {
                    return;
                }

                var recordset = data && data.recordset ? data.recordset : null;

                if (that.options.current_search_filter && recordset) {
                    var queryUtil = window.hWin.HEURIST4 &&
                        window.hWin.HEURIST4.query;
                    var util = window.hWin.HEURIST4 &&
                        window.hWin.HEURIST4.util;

                    if (queryUtil && typeof queryUtil.mergeHeuristQuery === 'function') {
                        var subQuery = queryUtil.mergeHeuristQuery(
                            recordset.getIds(2000),
                            that.options.current_search_filter
                        );
                        that.setQuery(subQuery);
                    } else {
                        that.setRecordSet(recordset);
                    }
                } else {
                    that.setRecordSet(recordset);
                }

                that.loadanimation(false);

            } else if (event.type === hapi.Event.ON_REC_SEARCHSTART) {
                if (!that._isSameRealm(data)) return;

                that.options.recordset = null;
                that.options.query = null;
                that.options.selection = null;
                that.clearSelection();

                if (data && !data.reset && data.q !== '') {
                    that.loadanimation(true);
                } else {
                    that.setQuery(null);
                    that.loadanimation(false);
                }

            } else if (event.type === hapi.Event.ON_REC_SELECT) {
                if (!that._isSameRealm(data) ||
                    (data && data.source === that.element.attr('id'))) {
                    return;
                }

                if (data && data.reset) {
                    that.clearSelection();
                } else {
                    var selection = hapi.getSelection(data && data.selection, true);
                    that._doVisualizeSelection(selection);
                }

            } else if (event.type === hapi.Event.ON_SYSTEM_INITED) {
                that.refresh();
            }
        });
    },

    /** Check whether a host event belongs to this widget's search realm. */
    _isSameRealm: function(data) {
        var util = window.hWin && window.hWin.HEURIST4 &&
            window.hWin.HEURIST4.util;
        var eventRealm = data ? data.search_realm : null;
        var eventRealmIsEmpty = util && typeof util.isempty === 'function'
            ? util.isempty(eventRealm)
            : eventRealm == null || eventRealm === '';

        return (!this.options.search_realm && eventRealmIsEmpty) ||
            (this.options.search_realm &&
                this.options.search_realm === eventRealm);
    },

    /** Apply a Heurist record selection to the current-results map layer. */
    _doVisualizeSelection: function(selection) {
        return this.setSelection(selection, {
            replace: true,
            zoom: true
        });
    },

    /** Debounce map resize requests from host layout changes. */
    _scheduleResize: function(delay) {
        var that = this;
        if (this._resizeTimer) clearTimeout(this._resizeTimer);
        this._resizeTimer = setTimeout(function() {
            that._resizeTimer = 0;
            that.resize();
        }, delay || 100);
    },

    /** Create the standalone map iframe and register its one-time configuration. */
    _createIframe: function() {
        this._registerIframeConfiguration();

        this._mapFrame = $('<iframe>')
            .addClass('heurist-map-viewer-frame')
            .attr({
                title: 'Heurist map',
                frameborder: '0',
                src: this._buildIframeUrl()
            })
            .css({ width: '100%', height: '100%', border: 0, display: 'block' })
            .appendTo(this._frameContainer);

        this.loadanimation(true);

        this._on(this._mapFrame, {
            load: function() {
                this._waitForMapApi();
            }
        });
    },

    /** Store configuration in a same-origin parent registry for iframe bootstrap. */
    _registerIframeConfiguration: function() {
        window.HEURIST_MAP_INSTANCES = window.HEURIST_MAP_INSTANCES || {};
        window.HEURIST_MAP_INSTANCES[this._instanceId] = {
            heuristMapOptions: this._buildHeuristMapOptions(),
            mapDocument: this.options.mapDocument || null
        };
    },

    /** Build native standalone heurist-map runtime options. */
    _buildHeuristMapOptions: function() {
        var hapi = window.hWin && window.hWin.HAPI4;
        var source = $.extend(true, {}, this.options.heuristMapOptions || {});

        source.database = this.options.database || source.database ||
            (hapi ? hapi.database : null);
        source.apiBaseUrl = this.options.apiBaseUrl || source.apiBaseUrl ||
            (hapi ? hapi.baseURL + 'api' : null);
        source.serverUrl = this.options.serverUrl || source.serverUrl || null;
        source.accessToken = this.options.accessToken || source.accessToken || null;
        source.requestHeaders = $.extend({}, source.requestHeaders || {},
            this.options.requestHeaders || {});

        source.dynamicDocument = $.extend({
            enabled: true,
            id: 'dynamic',
            title: 'Dynamic map',
            initiallyActive: true,
            keepContent: true
        }, source.dynamicDocument || {});

        source.ui = $.extend({
            enabled: true,
            placement: 'overlay',
            position: 'top-right',
            initiallyExpanded: true,
            showCurrentDocument: true,
            showMapDocuments: true,
            showLayers: true,
            showBaseMaps: true
        }, source.ui || {});

        return source;
    },

    /** Return the configured standalone application URL with the registry key. */
    _buildIframeUrl: function() {
        var url = this.options.mapApplicationUrl || '/heurist/external/heurist-map/index.html';
        var separator = url.indexOf('?') === -1 ? '?' : '&';

        return url + separator +
            'hostInstance=' +
            encodeURIComponent(this._instanceId);
    },

    /** Wait until the standalone bootstrap exposes its public API. */
    _waitForMapApi: function() {
        var that = this;
        var attempts = 0;
        var maxAttempts = 100;

        if (this._readyTimer) {
            clearInterval(this._readyTimer);
        }

        function inspect() {
            if (that._isDestroyed) return;

            try {
                var frameWindow = that._mapFrame && that._mapFrame[0].contentWindow;
                var mapApi = frameWindow && frameWindow.heuristMap;
                if (mapApi) {
                    clearInterval(that._readyTimer);
                    that._readyTimer = 0;
                    that._completeInitialization(mapApi);
                    return;
                }
            } catch (error) {
                clearInterval(that._readyTimer);
                that._readyTimer = 0;
                that._reportError(error, 'access-iframe-api');
                return;
            }

            attempts += 1;
            if (attempts >= maxAttempts) {
                clearInterval(that._readyTimer);
                that._readyTimer = 0;
                that._reportError(
                    new Error('heurist-map public API was not created by the iframe'),
                    'wait-for-ready'
                );
            }
        }

        inspect();
        if (!this._mapApi && !this._readyTimer) {
            this._readyTimer = setInterval(inspect, 50);
        }
    },

    /** Finish common initialization after the iframe API becomes available. */
    _completeInitialization: function(mapApi) {
        var that = this;
        this._mapApi = mapApi;

        Promise.resolve(mapApi.ready())
            .then(function() {
                if (that._isDestroyed) return;
                that._bindMapEvents();
                that._isReady = true;

                return that._initializeContent();
            })
            .then(function() {
                if (that._isDestroyed) return;
                return that._flushPendingOperations();
            })
            .then(function() {
                if (that._isDestroyed) return;
                that.resize();
                that.loadanimation(false);
                that._invokeCallback('onready', {
                    mapApi: that._mapApi,
                    widget: that
                });
            })
            .catch(function(error) {
                that._reportError(error, 'initialize-map');
            });
    },

    /** Apply initial document, current result set, and host selection. */
    _initializeContent: function() {
        var that = this;
        var query = this.options.query || this._recordsetToQuery(this.options.recordset);
        var operation;

        if (query) {
            operation = this._activateDynamicDocument().then(function() {
                return that._setQueryNow(query, {});
            });
        } else if (this.options.mapDocument != null) {
            operation = Promise.resolve(
                this._mapApi.activateMapDocument(this.options.mapDocument)
            );
        } else {
            operation = this._activateDynamicDocument();
        }

        return operation.then(function() {
            var selection = that._normalizeRecordIds(that.options.selection);
            return selection.length ? that._setSelectionNow(selection, {}) : null;
        });
    },

    _activateDynamicDocument: function() {
        return Promise.resolve(this._mapApi.activateMapDocument('dynamic'));
    },

    /** Subscribe only to stable public map events. */
    _bindMapEvents: function() {
        var that = this;

        this._mapEventHandlers.selection = function(event) {
            if (that._suppressSelectionSync) return;

            var features = event.detail && event.detail.selection &&
                event.detail.selection.features;
            var recordIds = [];

            if (Array.isArray(features)) {
                recordIds = that._normalizeRecordIds(features.map(function(item) {
                    return item.recordId;
                }));
            }

            that.options.selection = recordIds;

            if (that.options.eventbased) {
                var hapi = window.hWin && window.hWin.HAPI4;
                if (hapi && hapi.Event && hapi.Event.ON_REC_SELECT) {
                    $(that.document).trigger(hapi.Event.ON_REC_SELECT, {
                        selection: recordIds,
                        source: that.element.attr('id'),
                        search_realm: that.options.search_realm,
                        reset: recordIds.length === 0
                    });
                }
            }

            that._invokeCallback('onselect', recordIds, event.detail || {});
        };

        this._mapEventHandlers.error = function(event) {
            var detail = event.detail || {};
            var error = detail.error || detail;
            that._reportError(error, detail.operation || 'map-event', detail);
        };

        this._mapEventHandlers.editDocument = function(event) {
            var detail = event.detail || {};
            if (that._invokeCallback('oneditdocument', detail) !== false) {
                that._openRecordEdit(detail.documentId);
            }
        };

        this._mapEventHandlers.editLayer = function(event) {
            var detail = event.detail || {};
            if (that._invokeCallback('oneditlayer', detail) !== false) {
                that._openRecordEdit(detail.recordId);
            }
        };

        this._mapApi.addEventListener(
            'heurist-map-selection-changed', this._mapEventHandlers.selection
        );
        this._mapApi.addEventListener(
            'heurist-map-error', this._mapEventHandlers.error
        );
        this._mapApi.addEventListener(
            'heurist-map-edit-document-requested', this._mapEventHandlers.editDocument
        );
        this._mapApi.addEventListener(
            'heurist-map-edit-layer-requested', this._mapEventHandlers.editLayer
        );
    },

    _unbindMapEvents: function() {
        if (!this._mapApi) return;
        var handlers = this._mapEventHandlers;
        if (handlers.selection) this._mapApi.removeEventListener(
            'heurist-map-selection-changed', handlers.selection
        );
        if (handlers.error) this._mapApi.removeEventListener(
            'heurist-map-error', handlers.error
        );
        if (handlers.editDocument) this._mapApi.removeEventListener(
            'heurist-map-edit-document-requested', handlers.editDocument
        );
        if (handlers.editLayer) this._mapApi.removeEventListener(
            'heurist-map-edit-layer-requested', handlers.editLayer
        );
        this._mapEventHandlers = {};
        this._events = null;
    },

    /** Set or replace the stable current-results query layer. */
    setQuery: function(query, options) {
        this.options.query = query || null;
        this.options.recordset = null;
        return this._enqueueOrRun('_setQueryNow', [query, options || {}]);
    },

    _setQueryNow: function(query, options) {
        var that = this;
        var layerOptions = this.options.currentResultsLayer || {};
        var layerId = String(layerOptions.id || 'current-results');
        var normalizedQuery = this._normalizeQuery(query);

        this._currentQuery = normalizedQuery;

        if (!normalizedQuery) {
            if (!this._mapApi.getLayer(layerId)) return Promise.resolve(null);
            return Promise.resolve(this._mapApi.clearLayer(layerId));
        }

        return this._activateDynamicDocument().then(function() {
            if (that._mapApi.getLayer(layerId)) {
                that._currentLayerCreated = true;
                return that._mapApi.setQueryForLayer(layerId, normalizedQuery, {
                    reload: true,
                    zoom: options.zoom === true
                });
            }

            return that._mapApi.addQueryLayer(normalizedQuery, {
                id: layerId,
                title: options.title || layerOptions.title || that._currentResultsTitle(),
                visible: layerOptions.visible !== false,
                selectable: layerOptions.selectable !== false,
                zoom: options.zoom === true
            }).then(function(result) {
                that._currentLayerCreated = true;
                return result;
            });
        });
    },

    /** Convert and apply an HRecordSet without leaking it into heurist-map. */
    setRecordSet: function(recordset, options) {
        this.options.recordset = recordset || null;
        this.options.query = null;
        return this._enqueueOrRun('_setQueryNow', [
            this._recordsetToQuery(recordset), options || {}
        ]);
    },

    /** Synchronize a host-side record selection to the current-results layer. */
    setSelection: function(recordIds, options) {
        var ids = this._normalizeRecordIds(recordIds);
        this.options.selection = ids;
        return this._enqueueOrRun('_setSelectionNow', [ids, options || {}]);
    },

    _setSelectionNow: function(recordIds, options) {
        var that = this;
        var layerId = String(
            (this.options.currentResultsLayer || {}).id || 'current-results'
        );
        var ids = this._normalizeRecordIds(recordIds);

        this._suppressSelectionSync = true;
        var operation = ids.length === 0
            ? this._mapApi.clearSelection()
            : this._mapApi.selectRecords(layerId, ids, {
                replace: options.replace !== false,
                zoom: options.zoom === true
            });

        return Promise.resolve(operation).finally(function() {
            that._suppressSelectionSync = false;
        });
    },

    clearSelection: function() {
        return this.setSelection([], {});
    },

    /** Activate a persisted MapDocument. */
    setMapDocument: function(documentId, options) {
        this.options.mapDocument = documentId;
        return this._enqueueOrRun('_setMapDocumentNow', [documentId, options || {}]);
    },

    _setMapDocumentNow: function(documentId, options) {
        if (documentId == null || documentId === '') {
            return this._activateDynamicDocument();
        }
        return Promise.resolve(this._mapApi.activateMapDocument(documentId, options));
    },

    /** Reload the current query layer, or the active persisted document. */
    refresh: function() {
        var that = this;
        return this._enqueueOrRun('_refreshNow', []).then(function(result) {
            that.resize();
            return result;
        });
    },

    _refreshNow: function() {
        var layerId = String(
            (this.options.currentResultsLayer || {}).id || 'current-results'
        );
        if (this._currentQuery) {
            return this._setQueryNow(this._currentQuery, {});
        }

        var active = this._mapApi.getActiveMapDocument();
        if (active && active.id !== 'dynamic') {
            return Promise.resolve(this._mapApi.reloadMapDocument(active.id));
        }

        if (this._mapApi.getLayer(layerId)) {
            return Promise.resolve(this._mapApi.reloadLayer(layerId));
        }
        return Promise.resolve(null);
    },

    resize: function() {
        if (!this._isReady || !this._mapApi) return Promise.resolve(false);
        return Promise.resolve(this._mapApi.invalidateSize());
    },

    getMapApi: function() {
        return this._mapApi;
    },

    isReady: function() {
        return this._isReady;
    },

    /** Queue calls made before iframe initialization and preserve call order. */
    _enqueueOrRun: function(methodName, args) {
        var that = this;
        if (this._isReady && this._mapApi) {
            return Promise.resolve().then(function() {
                return that[methodName].apply(that, args);
            }).catch(function(error) {
                that._reportError(error, methodName);
                throw error;
            });
        }

        return new Promise(function(resolve, reject) {
            that._pendingOperations.push({
                methodName: methodName,
                args: args,
                resolve: resolve,
                reject: reject
            });
        });
    },

    _flushPendingOperations: function() {
        var that = this;
        var queue = this._pendingOperations.splice(0);
        var chain = Promise.resolve();

        queue.forEach(function(operation) {
            chain = chain.then(function() {
                return that[operation.methodName].apply(that, operation.args);
            }).then(operation.resolve, function(error) {
                operation.reject(error);
                that._reportError(error, operation.methodName);
            });
        });

        return chain;
    },

    /** Recover an original query where possible, otherwise create ids: query. */
    _recordsetToQuery: function(recordset) {
        if (!recordset) return null;

        if (typeof recordset === 'string') return this._normalizeQuery(recordset);
        if ($.isPlainObject(recordset) && recordset.q != null) {
            return this._normalizeQuery(recordset.q);
        }

        var request = null;
        var candidates = ['getRequest', 'getRequestParams', 'getSearchRequest'];
        for (var index = 0; index < candidates.length && !request; index++) {
            if (typeof recordset[candidates[index]] === 'function') {
                try {
                    request = recordset[candidates[index]]();
                } catch (ignore) {
                    request = null;
                }
            }
        }

        request = request || recordset.request || recordset._request ||
            recordset.searchRequest || null;
        if (request && request.q != null) return this._normalizeQuery(request.q);

        if (typeof recordset.getIds === 'function') {
            var ids = recordset.getIds();
            if (Array.isArray(ids) && ids.length) {
                return 'ids:' + this._normalizeRecordIds(ids).join(',');
            }
        }

        return null;
    },

    _normalizeQuery: function(query) {
        if (query == null) return null;
        if (typeof query === 'string') {
            query = $.trim(query);
            return query || null;
        }
        if ($.isPlainObject(query)) return $.extend(true, {}, query);
        return query;
    },

    _normalizeRecordIds: function(recordIds) {
        var result = [];
        var seen = {};
        var values = Array.isArray(recordIds) ? recordIds :
            (recordIds == null ? [] : [recordIds]);

        values.forEach(function(value) {
            var id = Number(value);
            if (Number.isInteger(id) && id > 0 && !seen[id]) {
                seen[id] = true;
                result.push(id);
            }
        });
        return result;
    },

    _currentResultsTitle: function() {
        if (window.hWin && window.hWin.HR) {
            return window.hWin.HR('Current results');
        }
        return 'Current results';
    },

    _openRecordEdit: function(recordId) {
        if (!(Number(recordId) > 0)) return false;
        var ui = window.hWin && window.hWin.HEURIST4 && window.hWin.HEURIST4.ui;
        if (!ui || typeof ui.openRecordEdit !== 'function') return false;
        ui.openRecordEdit(Number(recordId), null, { selectOnSave: true });
        return true;
    },

    _invokeCallback: function(name) {
        var callback = this.options[name];
        if (typeof callback !== 'function') return undefined;
        var args = Array.prototype.slice.call(arguments, 1);
        return callback.apply(this.element[0], args);
    },

    _reportError: function(error, operation, detail) {
        var normalized = error instanceof Error ? error : new Error(
            error && error.message ? error.message : String(error || 'Unknown map error')
        );
        normalized.operation = operation;
        normalized.detail = detail || null;

        if (typeof this.options.onerror === 'function') {
            this.options.onerror.call(this.element[0], normalized);
            return;
        }

        var msg = window.hWin && window.hWin.HEURIST4 && window.hWin.HEURIST4.msg;
        if (msg && typeof msg.showMsgErr === 'function') {
            msg.showMsgErr(normalized.message);
        } else if (window.console) {
            console.error('mapViewer:', normalized);
        }
    },

    /** Show or hide the iframe loading indicator. */
    loadanimation: function(show) {
        if (!this._mapFrame) return;

        if (show) {
            this._mapFrame.css(
                'background',
                'url(' + window.hWin.HAPI4.baseURL +
                'hclient/assets/loading-animation-white.gif) no-repeat center center'
            );
        } else {
            this._mapFrame.css('background', 'none');
        }
    },

    _observeResize: function() {
        var that = this;
        if (typeof ResizeObserver === 'function') {
            this._resizeObserver = new ResizeObserver(function() {
                if (that._resizeTimer) clearTimeout(that._resizeTimer);
                that._resizeTimer = setTimeout(function() {
                    that._resizeTimer = 0;
                    that.resize();
                }, 100);
            });
            this._resizeObserver.observe(this.element[0]);
        }
    },

    _createInstanceId: function() {
        return 'heurist-map-' + Date.now() + '-' +
            Math.random().toString(36).slice(2, 10);
    },

    _destroy: function() {
        var that = this;
        this._isDestroyed = true;
        this._isReady = false;

        if (this._readyTimer) clearInterval(this._readyTimer);
        if (this._resizeTimer) clearTimeout(this._resizeTimer);
        if (this._resizeObserver) this._resizeObserver.disconnect();

        delete (window.HEURIST_MAP_INSTANCES || {})[this._instanceId];
        if (this._events) $(this.document).off(this._events);
        this._events = null;
        this._unbindMapEvents();

        this._pendingOperations.splice(0).forEach(function(operation) {
            operation.reject(new Error('mapViewer was destroyed'));
        });

        var destroyPromise = this._mapApi
            ? Promise.resolve(this._mapApi.destroy()).catch(function(error) {
                that._reportError(error, 'destroy');
            })
            : Promise.resolve();

        this._mapApi = null;
        if (this._mapFrame) this._mapFrame.remove();
        if (this._frameContainer) this._frameContainer.remove();
        this.element.removeClass('heurist-map-viewer');

        return destroyPromise;
    }
});
