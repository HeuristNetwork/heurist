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
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov <osmakov@gmail.com>
 * @since       7.0
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
}

window.HeuristModuleViewer = HeuristModuleViewer;
