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

/** Maximum current-result set represented as an explicit ids: query. */
const MAP_VIEWER_IDS_QUERY_LIMIT = 5000;

/**
 * @widget heurist.mapViewer
 * @description Same-origin iframe wrapper for the standalone heurist-map app.
 */
$.widget('heurist.mapViewer', {

    
    options: {
        presentationMode: 'iframe',
        viewerMode: 'map',              // map | configuration
        configurationMode: 'preferences',  // preferences | website | publish
        runtimeMode: null,              // main | website | standalone; inferred from configurationMode when omitted
        configurationValue: null,

        database: window.hWin.HAPI4.database,
        apiBaseUrl: window.hWin.HAPI4.baseURL + 'api',
        mapApplicationUrl: window.hWin.HAPI4.baseURL+'hclient/widgets/viewers/mapViewer.html', 
        //mapApplicationUrl: '/heurist/hclient/bundles/heurist-map/index.html',

        accessToken: null,
        requestHeaders: null,
        baseMapProviderOptions: null, // e.g. {MapTilesAPI:{apikey:'...'}}

        heuristMapSettings: null,     // persisted {format,version,options,config}
        heuristMapState: null,        // initial reproducible map state
        mapDocument: null,

        query: null,
        recordset: null, // Compatibility input only; immediately converted and discarded.
        selection: null,

        eventbased: true,
        search_realm: null,
        // LEGACY/UNUSED. Retained only so existing website configurations still load.
        current_search_filter: null,

        currentResultsLayer: {
            id: 'current-results',
            title: null,
            visible: true,
            selectable: true
        },

        onready: null,
        onconfiguration: null,
        oncancelconfiguration: null,
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
        this._mapBootstrap = null;
        this._mapEventHandlers = {};
        this._events = null;

        // Keep HRecordSet strictly at the wrapper boundary. The map runtime and
        // the rest of this widget work only with a query or plain record IDs.
        if (this.options.recordset) {
            var initialRecordset = this.options.recordset;
            this.options.recordset = null;
            if (!this.options.query) {
                this.options.query = this._recordsetToQuery(initialRecordset);
            }
        }

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
        this._bindShowEvent();
        this._observeResize();
        this._ensureIframeWhenVisible();
    },

    /** Initialize the iframe when the Heurist host makes this widget visible. */
    _bindShowEvent: function() {
        var that = this;
        this.element.on('myOnShowEvent.mapViewer', function(event) {
            if (event.target.id === that.element.attr('id')) {
                that._ensureIframeWhenVisible();
                if (that._mapFrame) that.resize();
            }
        });
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
                if (that.options.query != null && !hapi.has_access()) {
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
                var ids = that._recordsetIds(recordset);
                that.setQuery(that._currentResultsQuery(ids, data && data.query));

            } else if (event.type === hapi.Event.ON_REC_SEARCHSTART) {
                if (!that._isSameRealm(data)) return;

                that.options.query = null;
                that.options.selection = null;
                that.clearSelection();

                if (!(data && !data.reset && data.q !== '')) {
                    that.setQuery(null);
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
            if (!that._mapFrame) that._ensureIframeWhenVisible();
            that.resize();
        }, delay || 100);
    },

    /** Return true only when the widget occupies a visible area in the host UI. */
    _isWidgetVisible: function() {
        var element = this.element && this.element[0];
        if (!element || !element.isConnected) return false;
        if (!this.element.is(':visible')) return false;

        var style = window.getComputedStyle ? window.getComputedStyle(element) : null;
        if (style && (style.display === 'none' ||
            style.visibility === 'hidden' || style.visibility === 'collapse')) {
            return false;
        }

        var rect = element.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
    },

    /** Create the iframe only after the host widget becomes visible. */
    _ensureIframeWhenVisible: function() {
        if (this._isDestroyed || this._mapFrame || !this._isWidgetVisible()) return false;
        this._createIframe();
        return true;
    },

    /** Create the standalone map iframe and install the persistent same-origin bridge. */
    _createIframe: function() {
        if (this._mapFrame || this._isDestroyed) return;
        this._mapBootstrap = this._buildHeuristMapBootstrap();

        this._mapFrame = $('<iframe>')
            .addClass('heurist-map-viewer-frame')
            .attr({
                title: 'Heurist map',
                frameborder: '0'
            })
            .css({ width: '100%', height: '100%', border: 0, display: 'block' })
            .appendTo(this._frameContainer);

        // The bridge is stored on the iframe DOM element (owned by the parent),
        // not inside the child window. It therefore survives iframe navigation/reload.
        this._installIframeBridge();

        this._on(this._mapFrame, {
            load: function() {
                // Reassign defensively in case external code replaced the property.
                this._installIframeBridge();
                this._mapApi = null;
                this._isReady = false;
                this._waitForMapApi();
            }
        });

        this._mapFrame.attr('src', this._buildIframeUrl());
    },

    /** Install the direct parent-to-child bridge on the persistent iframe element. */
    _installIframeBridge: function() {
        var frame = this._mapFrame && this._mapFrame[0];
        if (!frame) return;
        var that = this;
        frame.heuristMapHost = {
            getConfiguration: function() {
                return $.extend(true, {}, that._mapBootstrap || that._buildHeuristMapBootstrap());
            },
            updateSettings: function(settings) {
                var normalized = that._cloneMapSettings(settings);
                that._mapBootstrap = that._mapBootstrap || that._buildHeuristMapBootstrap();
                that._mapBootstrap.settings = normalized;
                that.options.heuristMapSettings = $.extend(true, {}, normalized);
                if (that.options.viewerMode === 'configuration') {
                    that.options.configurationValue = $.extend(true, {}, normalized);
                }
                return $.extend(true, {}, normalized);
            },
            updateState: function(state) {
                that._mapBootstrap = that._mapBootstrap || that._buildHeuristMapBootstrap();
                that._mapBootstrap.state = state == null ? null : $.extend(true, {}, state);
                that.options.heuristMapState = state == null ? null : $.extend(true, {}, state);
            },
            // Generic host editing bridge. MapDocument and MapLayer are both ordinary
            // Heurist records, so the child only needs to provide the persisted record ID.
            editRecord: function(recordId) {
                return that._openRecordEdit(recordId);
            },
            // Legacy Heurist editors remain entirely in the parent application.
            // heurist-map passes/receives JSON only and never touches HAPI4 directly.
            editSymbology: function(value, options) {
                return that._editSymbology(value, options || {});
            }
        };
    },

    /** Build the single bootstrap contract consumed by heurist-map. */
    _buildHeuristMapBootstrap: function() {
        var hapi = window.hWin && window.hWin.HAPI4;
        var saved = this._getSavedMapSettings();
        var explicit = this._cloneMapSettings(this.options.heuristMapSettings);
        var runtimeMode = this._getRuntimeMode();

        // Preferences are the base. Explicit widget settings override them once,
        // here in the host wrapper. heurist-map performs no second precedence merge.
        var settings = this._mergeMapSettings(
            saved,
            runtimeMode === 'website' ? this._websiteMapDefaults() : null,
            explicit
        );
        if (this.options.viewerMode === 'configuration' && this.options.configurationValue) {
            settings = this._mergeMapSettings(settings, this.options.configurationValue);
        }
        if (runtimeMode === 'website') {
            // Website maps must never expose host-only configuration actions.
            settings.options.ui.showOptions = false;
            settings.options.ui.showPublish = false;
        }

        var database = this.options.database || (hapi ? hapi.database : null);
        var apiBaseUrl = this.options.apiBaseUrl || (hapi ? hapi.baseURL + 'api' : null);

        return {
            runtime: {
                viewerMode: this.options.viewerMode === 'configuration' ? 'configuration' : 'map',
                configurationMode: this.options.configurationMode || 'preferences',
                runtimeMode: runtimeMode,
                database: database,
                apiBaseUrl: apiBaseUrl,
                accessToken: this.options.accessToken || null,
                requestHeaders: $.extend({}, this.options.requestHeaders || {}),
                baseMapProviderOptions: $.extend(true, {}, this.options.baseMapProviderOptions || {}),
                baseUrl: hapi ? hapi.baseURL : null,
                readonly: false
            },
            settings: settings,
            state: this.options.heuristMapState || null
        };
    },

    /** Resolve host environment independently from the settings envelope format. */
    _getRuntimeMode: function() {
        var mode = String(this.options.runtimeMode || '').toLowerCase();
        if (['main', 'website', 'standalone'].indexOf(mode) >= 0) return mode;
        if (this.options.configurationMode === 'website') return 'website';
        if (this.options.configurationMode === 'publish') return 'standalone';
        return 'main';
    },

    /** Defaults applied after user preferences but before explicit website settings. */
    _websiteMapDefaults: function() {
        return {
            options: {
                ui: {
                    showBaseMaps: false,
                    showOptions: false,
                    showPublish: false
                },
                nativeControls: {
                    bookmark: false,
                    print: false
                }
            }
        };
    },

    /** Read the already-loaded HAPI preference without making another request. */
    _getSavedMapSettings: function() {
        var hapi = window.hWin && window.hWin.HAPI4;
        if (!hapi || typeof hapi.get_prefs !== 'function') return null;
        try {
            var value = hapi.get_prefs('heurist-map');
            if (typeof value === 'string' && value) value = JSON.parse(value);
            return value && typeof value === 'object' ? value : null;
        } catch (error) {
            return null;
        }
    },

    _cloneMapSettings: function(value) {
        if (!value || typeof value !== 'object') return null;
        return {
            format: value.format || 'heurist-map-settings',
            version: Number(value.version) || 1,
            options: $.extend(true, {}, value.options || {}),
            config: $.extend(true, {}, value.config || {})
        };
    },

    _mergeMapSettings: function() {
        var result = { format: 'heurist-map-settings', version: 1, options: {}, config: {} };
        Array.prototype.slice.call(arguments).forEach(function(value) {
            if (!value || typeof value !== 'object') return;
            if (value.options) result.options = $.extend(true, result.options, value.options);
            if (value.config) result.config = $.extend(true, result.config, value.config);
        });
        return result;
    },

    /** Return the configured standalone application URL. */
    _buildIframeUrl: function() {
        return this.options.mapApplicationUrl || '/heurist/hclient/bundles/heurist-map/index.html';
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
                that._isReady = true;

                if (that.options.viewerMode === 'configuration') {
                    that._openConfigurationNow({
                        mode: that.options.configurationMode,
                        value: that.options.configurationValue || that._mapBootstrap?.settings || null
                    }).catch(function(error) {
                        that._reportError(error, 'open-configuration');
                    });
                    return null;
                }

                that._bindMapEvents();
                return that._initializeContent();
            })
            .then(function() {
                if (that._isDestroyed) return;
                return that._flushPendingOperations();
            })
            .then(function() {
                if (that._isDestroyed) return;
                if (that.options.viewerMode !== 'configuration') that.resize();
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
        var query = this.options.query;
        var operation;

        if (this.options.mapDocument != null) {
            operation = Promise.resolve(
                this._mapApi.activateMapDocument(this.options.mapDocument)
            ).then(function() {
                return query ? that._setQueryNow(query, {}) : null;
            });
        } else {
            // heurist-map has already activated the configured default document.
            // Updating the current-results query must never change that activation.
            operation = query ? this._setQueryNow(query, {}) : Promise.resolve(null);
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

        this._mapEventHandlers.documentActivated = function(event) {
            var documentInfo = event.detail && event.detail.document;
            if (documentInfo && String(documentInfo.id) === 'dynamic') {
                that._applyCachedSelectionNow();
            }
        };

        this._mapEventHandlers.layerVisibility = function(event) {
            var detail = event.detail || {};
            var layerId = String((that.options.currentResultsLayer || {}).id || 'current-results');
            if (String(detail.layerId) === layerId && detail.visible === true) {
                that._applyCachedSelectionNow();
            }
        };

        this._mapEventHandlers.layerLoaded = function(event) {
            var layer = event.detail && event.detail.layer;
            var layerId = String((that.options.currentResultsLayer || {}).id || 'current-results');
            if (layer && String(layer.id) === layerId) {
                that._applyCachedSelectionNow();
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
        this._mapApi.addEventListener(
            'heurist-map-document-activated', this._mapEventHandlers.documentActivated
        );
        this._mapApi.addEventListener(
            'heurist-map-layer-visibility-changed', this._mapEventHandlers.layerVisibility
        );
        this._mapApi.addEventListener(
            'heurist-map-layer-loaded', this._mapEventHandlers.layerLoaded
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
        if (handlers.documentActivated) this._mapApi.removeEventListener(
            'heurist-map-document-activated', handlers.documentActivated
        );
        if (handlers.layerVisibility) this._mapApi.removeEventListener(
            'heurist-map-layer-visibility-changed', handlers.layerVisibility
        );
        if (handlers.layerLoaded) this._mapApi.removeEventListener(
            'heurist-map-layer-loaded', handlers.layerLoaded
        );
        this._mapEventHandlers = {};
        this._events = null;
    },

    /** Open the generalized configuration editor through the iframe API. */
    openConfiguration: function(options) {
        return this._enqueueOrRun('_openConfigurationNow', [options || {}]);
    },

    _openConfigurationNow: function(options) {
        var that = this;
        if (!this._mapApi || typeof this._mapApi.openConfigurationDialog !== 'function') {
            return Promise.reject(new Error('Map configuration API is not available'));
        }

        var mode = options.mode || this.options.configurationMode || 'preferences';
        var value = options.value !== undefined
            ? options.value : this.options.configurationValue;
        var configurationOnly = this.options.viewerMode === 'configuration';

        return new Promise(function(resolve) {
            that._mapApi.openConfigurationDialog({
                mode: mode,
                value: value || null,
                onSave: function(settings, context) {
                    that.options.configurationValue = context.serialized;
                    that._mapFrame?.[0]?.heuristMapHost?.updateSettings?.(context.serialized);
                    that._invokeCallback('onconfiguration', context.serialized, context);
                    if (typeof options.onSave === 'function') {
                        options.onSave(context.serialized, context);
                    }

                    // Configuration-only viewer has no MapApplication/Leaflet runtime.
                    // Saving here only returns the serialized settings to the caller;
                    // live application is intentionally handled only by the normal map mode.
                    if (configurationOnly) {
                        resolve(context.serialized);
                        return true;
                    }

                    resolve(context.serialized);
                    return true;
                },
                onCancel: function(settings, context) {
                    that._invokeCallback('oncancelconfiguration', settings, context);
                    if (typeof options.onCancel === 'function') options.onCancel(settings, context);
                    resolve(null);
                }
            });
        });
    },

    /** Set or replace the stable current-results query layer. */
    setQuery: function(query, options) {
        this.options.query = query || null;
        return this._enqueueOrRun('_setQueryNow', [query, options || {}]);
    },

    _setQueryNow: function(query, options) {
        var that = this;
        var layerOptions = this.options.currentResultsLayer || {};
        var layerId = String(layerOptions.id || 'current-results');
        var normalizedQuery = this._normalizeQuery(query);

        this._currentQuery = normalizedQuery;

        var storedLayer = typeof this._mapApi.getDocumentLayer === 'function'
            ? this._mapApi.getDocumentLayer(layerId, 'dynamic')
            : this._mapApi.getLayer(layerId);

        if (!normalizedQuery) {
            this._currentLayerCreated = false;
            if (!storedLayer) return Promise.resolve(null);
            // Remove only the dynamic current-results layer definition. This avoids
            // retaining a stale query while never activating the dynamic document.
            return Promise.resolve(this._mapApi.removeLayer(layerId, { documentId: 'dynamic' }));
        }

        if (storedLayer) {
            this._currentLayerCreated = true;
            return Promise.resolve(this._mapApi.setQueryForLayer(layerId, normalizedQuery, {
                reload: true,
                zoom: options.zoom === true
            }));
        }

        return Promise.resolve(this._mapApi.addQueryLayer(normalizedQuery, {
            id: layerId,
            title: options.title || layerOptions.title || that._currentResultsTitle(),
            visible: layerOptions.visible !== false,
            selectable: layerOptions.selectable !== false,
            zoom: options.zoom === true
        })).then(function(result) {
            that._currentLayerCreated = true;
            return result;
        });
    },

    /** Convert and apply an HRecordSet without leaking it into heurist-map. */
    setRecordSet: function(recordset, options) {
        var query = this._recordsetToQuery(recordset);
        this.options.recordset = null;
        return this.setQuery(query, options || {});
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

        var activeDocument = this._mapApi.getActiveMapDocument();
        if (!activeDocument || String(activeDocument.id) !== 'dynamic') {
            return Promise.resolve(null);
        }

        if (ids.length === 0) {
            this._suppressSelectionSync = true;
            return Promise.resolve(this._mapApi.clearSelection()).finally(function() {
                that._suppressSelectionSync = false;
            });
        }

        var layer = this._mapApi.getLayer(layerId);
        if (!layer || layer.visible === false || layer.loadState !== 'loaded' || layer.selectable === false) {
            return Promise.resolve(null);
        }

        this._suppressSelectionSync = true;
        return Promise.resolve(this._mapApi.selectRecords(layerId, ids, {
            replace: options.replace !== false,
            zoom: options.zoom === true
        })).finally(function() {
            that._suppressSelectionSync = false;
        });
    },

    _applyCachedSelectionNow: function() {
        var that = this;
        var ids = this._normalizeRecordIds(this.options.selection);
        if (!ids.length) return Promise.resolve(null);
        return this._setSelectionNow(ids, { replace: true, zoom: false }).catch(function(error) {
            // A document/layer can change again while an activation or deferred
            // layer load is completing. Treat that race as another deferred sync.
            var active = that._mapApi && that._mapApi.getActiveMapDocument();
            var layerId = String((that.options.currentResultsLayer || {}).id || 'current-results');
            var layer = that._mapApi && that._mapApi.getLayer(layerId);
            if (!active || String(active.id) !== 'dynamic' || !layer || layer.visible === false || layer.loadState !== 'loaded') {
                return null;
            }
            throw error;
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
        if (this.options.viewerMode === 'configuration') return Promise.resolve(true);
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

    /** Convert a compatibility HRecordSet to the current-results query contract. */
    _recordsetToQuery: function(recordset) {
        if (!recordset) return null;

        if (typeof recordset === 'string') return this._normalizeQuery(recordset);
        if ($.isPlainObject(recordset) && recordset.q != null) {
            return this._normalizeQuery(recordset.q);
        }

        var ids = this._recordsetIds(recordset);
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
        var query = request && request.query !== undefined
            ? request.query : request && request.q;
        return this._currentResultsQuery(ids, query);
    },

    /** Extract the ordered IDs at the only boundary where HRecordSet is accepted. */
    _recordsetIds: function(recordset) {
        if (!recordset) return [];
        if (Array.isArray(recordset)) return this._normalizeRecordIds(recordset);
        if (typeof recordset.getOrder === 'function') {
            return this._normalizeRecordIds(recordset.getOrder());
        }
        return [];
    },

    /** Prefer compact explicit IDs; reuse the executed query for large results. */
    _currentResultsQuery: function(recordIds, executedQuery) {
        var ids = this._normalizeRecordIds(recordIds);
        if (!ids.length) return null;
        if (ids.length <= MAP_VIEWER_IDS_QUERY_LIMIT) {
            return 'ids:' + ids.join(',');
        }
        return this._normalizeQuery(executedQuery) || 'ids:' + ids.join(',');
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

    /** Open the legacy Heurist symbology/thematic editor and optionally persist DT_SYMBOLOGY. */
    _editSymbology: function(value, options) {
        var that = this;
        var current = that._normalizeSymbologyValue(value) || {};
        var recordId = Number(options && options.recordId);
        var persist = options && options.persist === true;
        var thematic = options && options.thematic === true;
        var mapUtil = window.hWin && window.hWin.HEURIST4 && window.hWin.HEURIST4.map;
        var configuredDefault = that._mapBootstrap && that._mapBootstrap.settings
            && that._mapBootstrap.settings.config && that._mapBootstrap.settings.config.defaults
            ? that._mapBootstrap.settings.config.defaults.symbology : null;
        var parentSymbol = options && $.isPlainObject(options.parentSymbol)
            ? $.extend(true, {}, options.parentSymbol)
            : (recordId > 0 && mapUtil
                ? (configuredDefault
                    ? mapUtil.normalizeMapSymbol(configuredDefault, mapUtil.DEFAULT_MAP_SYMBOL)
                    : mapUtil.getDefaultMapSymbol())
                : (mapUtil ? mapUtil.DEFAULT_MAP_SYMBOL : null));

        return new Promise(function(resolve, reject) {
            var ui = window.hWin && window.hWin.HEURIST4 && window.hWin.HEURIST4.ui;
            if (!ui) {
                reject(new Error('Heurist symbology editor is not available'));
                return;
            }

            var settled = false;
            function accept(newValue) {
                if (settled || newValue == null) return;
                var parsed = that._normalizeSymbologyValue(newValue);
                if (!parsed) {
                    settled = true;
                    reject(new Error('Symbology editor returned invalid JSON'));
                    return;
                }

                // The mode-0 editor used by MapConfigurationDialog may omit an
                // icon type when "Record type icon" is selected because that
                // value is identical to its parent/default symbol. Website map
                // configuration is a complete value rather than a persisted
                // layer override, so retain the effective type explicitly.
                if (!persist && !thematic && !Object.prototype.hasOwnProperty.call(parsed, 'iconType')) {
                    parsed.iconType = parentSymbol && parentSymbol.iconType
                        ? parentSymbol.iconType : 'rectype';
                }

                // The ordinary editor returns only the base symbol. Preserve any
                // canonical thematic renderers already attached to the layer.
                if (!thematic && Array.isArray(current.thematic) && current.thematic.length) {
                    parsed = {
                        symbol: parsed.symbol && Array.isArray(parsed.thematic)
                            ? parsed.symbol : parsed,
                        thematic: $.extend(true, [], current.thematic)
                    };
                }

                // mapThemesEditor returns the original canonical value on Cancel.
                // Treat an unchanged result as a no-op and do not rewrite the record.
                if (JSON.stringify(parsed) === JSON.stringify(current)) {
                    settled = true;
                    resolve(parsed);
                    return;
                }

                settled = true;
                if (!persist) {
                    resolve(parsed);
                    return;
                }
                that._saveLayerSymbology(recordId, parsed).then(function() {
                    resolve(parsed);
                }).catch(reject);
            }

            function cancel() {
                if (settled) return;
                settled = true;
                resolve(null);
            }

            try {
                if (thematic) {
                    if (typeof ui.showThematicMappingDialog !== 'function') {
                        reject(new Error('Heurist thematic mapping editor is not available'));
                        return;
                    }
                    ui.showThematicMappingDialog({
                        maplayer_query: options && options.query ? options.query : null,
                        symbology: $.extend(true, {}, current),
                        parentSymbol: parentSymbol,
                        onClose: accept
                    });
                    setTimeout(function(){
                        if (typeof ui._raiseMapConfigurationChildDialog === 'function') {
                            ui._raiseMapConfigurationChildDialog();
                        }
                    }, 0);
                } else {
                    if (typeof ui.showEditSymbologyDialog !== 'function') {
                        reject(new Error('Heurist symbology editor is not available'));
                        return;
                    }
                    // Mode 1 is the old map-legend style editor. Configuration/default
                    // symbology has no persisted layer and uses the general mode 0.
                    ui.showEditSymbologyDialog(
                        $.extend(true, {}, current),
                        recordId > 0 ? 1 : 0,
                        accept,
                        cancel,
                        parentSymbol
                    );
                }
            } catch (error) {
                reject(error);
            }
        });
    },

    _normalizeSymbologyValue: function(value) {
        if (value && typeof value === 'object') return $.extend(true, {}, value);
        if (typeof value !== 'string' || !value.trim()) return null;
        try {
            var parsed = JSON.parse(value);
            return parsed && typeof parsed === 'object' ? parsed : null;
        } catch (ignore) {
            return null;
        }
    },

    _getSymbologyFieldId: function() {
        var hapi = window.hWin && window.hWin.HAPI4;
        var value = hapi && hapi.sysinfo && hapi.sysinfo.dbconst
            ? hapi.sysinfo.dbconst.DT_SYMBOLOGY : null;
        if (!(Number(value) > 0) && window.hWin && Number(window.hWin.DT_SYMBOLOGY) > 0) {
            value = window.hWin.DT_SYMBOLOGY;
        }
        return Number(value) > 0 ? Number(value) : null;
    },

    _saveLayerSymbology: function(recordId, value) {
        var id = Number(recordId);
        var dtyID = this._getSymbologyFieldId();
        var hapi = window.hWin && window.hWin.HAPI4;
        if (!(id > 0) || !(dtyID > 0)) {
            return Promise.reject(new Error('Cannot resolve Layer record or DT_SYMBOLOGY'));
        }
        if (!hapi || !hapi.RecordMgr || typeof hapi.RecordMgr.batch_details !== 'function') {
            return Promise.reject(new Error('Heurist RecordMgr.batch_details is not available'));
        }

        return new Promise(function(resolve, reject) {
            hapi.RecordMgr.batch_details({
                a: 'addreplace',
                recIDs: id,
                dtyID: dtyID,
                rVal: JSON.stringify(value)
            }, function(response) {
                if (response && response.status == window.hWin.ResponseStatus.OK) {
                    resolve(response);
                } else {
                    reject(new Error(response && response.message
                        ? response.message : 'Cannot save layer symbology'));
                }
            });
        });
    },

    _openRecordEdit: function(recordId) {
        var id = Number(recordId);
        if (!(id > 0)) {
            return Promise.reject(new Error('A valid Heurist record ID is required for editing'));
        }

        var ui = window.hWin && window.hWin.HEURIST4 && window.hWin.HEURIST4.ui;
        if (!ui || typeof ui.openRecordEdit !== 'function') {
            return Promise.reject(new Error('Heurist record editor is not available'));
        }

        return new Promise(function(resolve, reject) {
            var settled = false;
            var saved = false;

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
                        finish({ saved: true, recordId: id });
                    },
                    onClose: function() {
                        if (!saved) finish({ saved: false, recordId: id });
                    }
                });
            } catch (error) {
                reject(error);
            }
        });
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

    _observeResize: function() {
        var that = this;
        if (typeof ResizeObserver === 'function') {
            this._resizeObserver = new ResizeObserver(function() {
                if (!that._mapFrame) {
                    that._ensureIframeWhenVisible();
                    if (!that._mapFrame) return;
                }
                if (that._resizeTimer) clearTimeout(that._resizeTimer);
                that._resizeTimer = setTimeout(function() {
                    that._resizeTimer = 0;
                    that.resize();
                }, 100);
            });
            this._resizeObserver.observe(this.element[0]);
        }
    },


    _destroy: function() {
        var that = this;
        this._isDestroyed = true;
        this._isReady = false;

        if (this._readyTimer) clearInterval(this._readyTimer);
        if (this._resizeTimer) clearTimeout(this._resizeTimer);
        if (this._resizeObserver) this._resizeObserver.disconnect();
        this.element.off('.mapViewer');

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
