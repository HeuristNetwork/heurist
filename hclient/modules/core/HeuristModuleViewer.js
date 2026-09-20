/**
 * HeuristModuleViewer.js - Base class for hosted Heurist client modules
 *
 * Owns host-independent state and behaviour shared by iframe-based viewers.
 * Module-specific subclasses provide iframe creation, event bridging and API
 * operations while legacy jQuery widgets remain thin lifecycle adapters.
 *
 * @project     Heurist academic knowledge management system
 * @package     hclient.modules
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2026 Heurist Network Association. All rights reserved.
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov <osmakov@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since       8.0
 */

/** Base host class for iframe-based Heurist client modules. */
class HeuristModuleViewer {

    constructor(element, options) {
        this.element = element && element.jquery ? element : $(element);
        if (!this.element.length) {
            throw new Error('HeuristModuleViewer requires a host element');
        }
        this.document = this.element[0].ownerDocument || document;
        this.options = options || {};
    }

    /** Bind the legacy host notification that a hidden viewer became visible. */
    _bindShowEvent() {
        var that = this;
        this.element.on('myOnShowEvent.heuristModuleViewer', function(event) {
            if (event.target.id === that.element.attr('id')) {
                var created = that._ensureIframeWhenVisible();
                if (typeof that._applyPendingHostQueryWhenVisible === 'function') {
                    that._applyPendingHostQueryWhenVisible(created);
                }
                if (that._getModuleFrame()) that.resize();
            }
        });
    }

    /** Debounce resize requests received from the host layout. */
    _scheduleResize(delay) {
        var that = this;
        if (this._resizeTimer) clearTimeout(this._resizeTimer);
        this._resizeTimer = setTimeout(function() {
            that._resizeTimer = 0;
            if (!that._getModuleFrame()) that._ensureIframeWhenVisible();
            that.resize();
        }, delay || 100);
    }

    /** Return true only when the viewer occupies a visible host area. */
    _isWidgetVisible() {
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
    }

    /** Create the module iframe only after the host widget becomes visible. */
    _ensureIframeWhenVisible() {
        if (this._isDestroyed || this._getModuleFrame() || !this._isWidgetVisible()) {
            return false;
        }
        this._createIframe();
        return true;
    }

    /** Return the iframe owned by the concrete module class. */
    _getModuleFrame() {
        return this._moduleFrame || this._mapFrame || null;
    }

    /** Check whether a host event belongs to this viewer's search realm. */
    _isSameRealm(data) {
        var util = window.hWin && window.hWin.HEURIST4 &&
            window.hWin.HEURIST4.util;
        var eventRealm = data ? data.search_realm : null;
        var eventRealmIsEmpty = util && typeof util.isempty === 'function'
            ? util.isempty(eventRealm)
            : eventRealm == null || eventRealm === '';

        return (!this.options.search_realm && eventRealmIsEmpty) ||
            (this.options.search_realm &&
                this.options.search_realm === eventRealm);
    }

    /** Return the public API supplied by the hosted module. */
    getModuleApi() {
        return this._mapApi || this._moduleApi || null;
    }

    /** Return whether the hosted module API completed initialization. */
    isReady() {
        return this._isReady === true;
    }

    /** Notify the hosted module that its available viewport has changed. */
    resize() {
        var api = this.getModuleApi();
        if (!this._isReady || !api) return Promise.resolve(false);
        if (this.options.viewerMode === 'configuration') return Promise.resolve(true);
        if (typeof api.resize === 'function') return Promise.resolve(api.resize());
        if (typeof api.invalidateSize === 'function') {
            return Promise.resolve(api.invalidateSize());
        }
        return Promise.resolve(false);
    }

    /** Queue calls made before iframe initialization and preserve call order. */
    _enqueueOrRun(methodName, args) {
        var that = this;
        if (this._isReady && this.getModuleApi()) {
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
    }

    /** Execute queued operations sequentially after module initialization. */
    _flushPendingOperations() {
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
    }

    /** Normalize record IDs while retaining their original order. */
    _normalizeRecordIds(recordIds) {
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
    }

    /** Invoke an option callback using the host element as its context. */
    _invokeCallback(name) {
        var callback = this.options[name];
        if (typeof callback !== 'function') return undefined;
        var args = Array.prototype.slice.call(arguments, 1);
        return callback.apply(this.element[0], args);
    }

    /** Report a normalized module error to a callback or the Heurist host UI. */
    _reportError(error, operation, detail) {
        var normalized = error instanceof Error ? error : new Error(
            error && error.message ? error.message : String(error || 'Unknown module error')
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
            console.error('HeuristModuleViewer:', normalized);
        }
    }

    /** Observe host size changes without coupling subclasses to ResizeObserver. */
    _observeResize() {
        var that = this;
        if (typeof ResizeObserver === 'function') {
            this._resizeObserver = new ResizeObserver(function() {
                if (!that._getModuleFrame()) {
                    that._ensureIframeWhenVisible();
                    if (!that._getModuleFrame()) return;
                }
                if (that._resizeTimer) clearTimeout(that._resizeTimer);
                that._resizeTimer = setTimeout(function() {
                    that._resizeTimer = 0;
                    that.resize();
                }, 100);
            });
            this._resizeObserver.observe(this.element[0]);
        }
    }

    /** Update one runtime option without coupling the class to jQuery UI. */
    setOption(name, value) {
        this.options[name] = value;
        return this;
    }

    // Legacy Heurist editors remain entirely in the parent application. Hosted
    // modules pass/receive JSON only and never touch HAPI4 directly. Shared here
    // (rather than per-host) so every host class opens the same dialogs and
    // persists the same way; a prior per-host duplicate silently dropped the
    // thematic-editor branch and record persistence for one host.

    /** Open the legacy Heurist symbology/thematic editor and optionally persist DT_SYMBOLOGY. */
    _editSymbology(value, options) {
        var that = this;
        if (typeof this.options.onEditSymbology === 'function') {
            return Promise.resolve(this.options.onEditSymbology(value, options));
        }

        var current = that._normalizeSymbologyValue(value) || {};
        var recordId = Number(options && options.recordId);
        var persist = options && options.persist === true;
        var thematic = options && options.thematic === true;
        var mapUtil = window.hWin && window.hWin.HEURIST4 && window.hWin.HEURIST4.map;
        var configuredDefault = that._moduleBootstrap && that._moduleBootstrap.settings
            && that._moduleBootstrap.settings.config && that._moduleBootstrap.settings.config.defaults
            ? that._moduleBootstrap.settings.config.defaults.symbology : null;
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
                        Number.isInteger(Number(options && options.editorMode))
                            ? Number(options.editorMode) : (recordId > 0 ? 1 : 0),
                        accept,
                        cancel,
                        parentSymbol
                    );
                }
            } catch (error) {
                reject(error);
            }
        });
    }

    _normalizeSymbologyValue(value) {
        if (value && typeof value === 'object') return $.extend(true, {}, value);
        if (typeof value !== 'string' || !value.trim()) return null;
        try {
            var parsed = JSON.parse(value);
            return parsed && typeof parsed === 'object' ? parsed : null;
        } catch (ignore) {
            return null;
        }
    }

    _getSymbologyFieldId() {
        var hapi = window.hWin && window.hWin.HAPI4;
        var value = hapi && hapi.sysinfo && hapi.sysinfo.dbconst
            ? hapi.sysinfo.dbconst.DT_SYMBOLOGY : null;
        if (!(Number(value) > 0) && window.hWin && Number(window.hWin.DT_SYMBOLOGY) > 0) {
            value = window.hWin.DT_SYMBOLOGY;
        }
        return Number(value) > 0 ? Number(value) : null;
    }

    /** Persist an edited symbology value onto the record's DT_SYMBOLOGY field. */
    _saveLayerSymbology(recordId, value) {
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
    }

    /** Load the Map module class on demand so extent editing works without a live map instance. */
    _loadHeuristModuleMapClass() {
        if (window.HeuristModuleMap) return Promise.resolve(window.HeuristModuleMap);
        var hapi = window.hWin && window.hWin.HAPI4;
        var modulesBaseUrl = hapi && hapi.baseURL
            ? String(hapi.baseURL).replace(/\/?$/, '/') + 'hclient/modules/' : null;
        if (!modulesBaseUrl) return Promise.reject(new Error('Cannot resolve Heurist modules URL'));

        function loadScript(relativeUrl, globalName) {
            if (window[globalName]) return Promise.resolve();
            var url = modulesBaseUrl + relativeUrl;
            window.heuristModuleScriptPromises = window.heuristModuleScriptPromises || {};
            if (window.heuristModuleScriptPromises[url]) return window.heuristModuleScriptPromises[url];
            window.heuristModuleScriptPromises[url] = new Promise(function(resolve, reject) {
                var script = document.createElement('script');
                script.src = url;
                script.async = false;
                script.onload = function() {
                    window[globalName] ? resolve() : reject(new Error(url + ' did not define window.' + globalName));
                };
                script.onerror = function() { reject(new Error('Cannot load ' + url)); };
                document.head.appendChild(script);
            });
            return window.heuristModuleScriptPromises[url];
        }

        return loadScript('core/HeuristModuleRecordset.js', 'HeuristModuleRecordset')
            .then(function() { return loadScript('map/HeuristModuleMap.js', 'HeuristModuleMap'); })
            .then(function() { return window.HeuristModuleMap; });
    }

    /** Open the legacy Heurist extent-drawing editor by hosting a nested map instance. */
    _editExtent(bounds) {
        var that = this;
        if (typeof this.options.onEditExtent === 'function') {
            return Promise.resolve(this.options.onEditExtent(bounds, {}));
        }

        return this._loadHeuristModuleMapClass().then(function(HeuristModuleMapClass) {
            var $container = $('<div>')
                .css({ position: 'relative', width: '100%', height: '100%', overflow: 'hidden' })
                .appendTo('body');
            var editor = null;
            var settled = false;

            return new Promise(function(resolve) {
                function finish(value) {
                    if (settled) return;
                    settled = true;
                    Promise.resolve(editor && editor.destroy ? editor.destroy() : null).finally(function() {
                        if ($container.hasClass('ui-dialog-content')) $container.dialog('destroy');
                        $container.remove();
                        resolve(value || null);
                    });
                }

                $container.dialog({
                    title: 'Define map extent',
                    modal: true,
                    width: Math.min(1000, Math.max(700, $(window).width() - 80)),
                    height: Math.min(750, Math.max(520, $(window).height() - 80)),
                    resizable: true,
                    close: function() { finish(null); }
                });

                editor = new HeuristModuleMapClass($container, {
                    presentationMode: 'iframe',
                    viewerMode: 'draw',
                    configurationMode: 'preferences',
                    runtimeMode: typeof that._getRuntimeMode === 'function' ? that._getRuntimeMode() : 'main',
                    database: that.options.database,
                    apiBaseUrl: that.options.apiBaseUrl,
                    mapApplicationUrl: that.options.mapApplicationUrl,
                    accessToken: that.options.accessToken,
                    requestHeaders: that.options.requestHeaders,
                    baseMapProviderOptions: that.options.baseMapProviderOptions,
                    heuristModuleSettings: that.options.heuristModuleSettings,
                    eventbased: false,
                    drawParameters: {
                        mode: 'rectangle',
                        geojson: boundsToRectangle(bounds),
                        allowMultiple: false
                    },
                    ondrawfinish: function(result) { finish(rectangleBounds(result && result.geojson)); },
                    ondrawcancel: function() { finish(null); }
                });
            });
        });
    }
}

/** Convert a {west,south,east,north} bounds object into a rectangle GeoJSON geometry. */
function boundsToRectangle(bounds) {
    if (!bounds) return null;
    var west = Number(bounds.west), south = Number(bounds.south);
    var east = Number(bounds.east), north = Number(bounds.north);
    if (![west, south, east, north].every(Number.isFinite)) return null;
    return { type: 'Polygon', coordinates: [[[west, south], [east, south], [east, north], [west, north], [west, south]]] };
}

/** Derive a {west,south,east,north} bounds object from an arbitrary GeoJSON value. */
function rectangleBounds(geojson) {
    var points = [];
    function collect(value) {
        if (!Array.isArray(value)) return;
        if (value.length >= 2 && Number.isFinite(Number(value[0])) && Number.isFinite(Number(value[1]))) {
            points.push([Number(value[0]), Number(value[1])]);
        } else value.forEach(collect);
    }
    function collectGeoJson(value) {
        if (!value || typeof value !== 'object') return;
        if (value.type === 'Feature') {
            collectGeoJson(value.geometry);
        } else if (value.type === 'FeatureCollection') {
            (value.features || []).forEach(collectGeoJson);
        } else if (value.type === 'GeometryCollection') {
            (value.geometries || []).forEach(collectGeoJson);
        } else {
            collect(value.coordinates);
        }
    }
    collectGeoJson(geojson);
    if (!points.length) return null;
    return {
        west: Math.min.apply(null, points.map(function(point) { return point[0]; })),
        south: Math.min.apply(null, points.map(function(point) { return point[1]; })),
        east: Math.max.apply(null, points.map(function(point) { return point[0]; })),
        north: Math.max.apply(null, points.map(function(point) { return point[1]; }))
    };
}

window.HeuristModuleViewer = HeuristModuleViewer;
