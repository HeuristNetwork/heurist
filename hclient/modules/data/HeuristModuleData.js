/**
 * HeuristModuleData.js - Host class for the heurist-data application
 *
 * Hosts heurist-data in an iframe and translates Heurist query, Dataset and
 * selection state to its serializable public API. No table/card/report engine
 * object is exposed to the parent application.
 *
 * @project     Heurist academic knowledge management system
 * @package     hclient.modules
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov <osmakov@gmail.com>
 * @since       7.0
 */

const HEURIST_MODULE_DATA_DEFAULTS = {
    presentationMode: 'iframe',
    viewerMode: 'data',             // data | configuration
    configurationMode: 'preferences',
    runtimeMode: null,              // main | website | standalone
    configurationValue: null,

    database: window.hWin && window.hWin.HAPI4
        ? window.hWin.HAPI4.database : null,
    apiBaseUrl: window.hWin && window.hWin.HAPI4
        ? window.hWin.HAPI4.baseURL + 'api' : null,
    dataApplicationUrl: window.hWin && window.hWin.HAPI4
        ? window.hWin.HAPI4.baseURL + 'hclient/modules/data/dataViewer.html'
        : null,
    accessToken: null,
    requestHeaders: null,

    heuristDataSettings: null,
    heuristDataState: null,
    dataset: null,
    fields: null,
    query: null,
    recordset: null,
    selection: null,

    eventbased: true,
    search_realm: null,

    onready: null,
    onconfiguration: null,
    oncancelconfiguration: null,
    onselect: null,
    onerror: null,
    oneditrecord: null
};

/** Host-side class for the independent heurist-data application. */
class HeuristModuleData extends HeuristModuleRecordset {

    constructor(element, options) {
        super(element, $.extend(true, {}, HEURIST_MODULE_DATA_DEFAULTS, options || {}));
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
        this._dataBootstrap = null;
        this._dataEventHandlers = {};
        this._suppressSelectionSync = false;

        this.element.addClass('heurist-data-viewer').css({
            position: 'relative', overflow: 'hidden'
        });
        this._frameContainer = $('<div>')
            .addClass('heurist-data-viewer-frame-container')
            .css({position: 'absolute', inset: 0})
            .appendTo(this.element);

        if (this.options.presentationMode !== 'iframe') {
            this._reportError(new Error(
                'Only iframe presentationMode is implemented by dataViewer'
            ), 'initialize');
            return;
        }

        this._bindHostEvents();
        this._bindShowEvent();
        this._observeResize();
        this._ensureIframeWhenVisible();
    }

    /** Create the iframe only once the host element has a visible size. */
    _createIframe() {
        if (this._moduleFrame || this._isDestroyed) return;
        if (!this.options.dataApplicationUrl) {
            this._reportError(new Error('dataApplicationUrl is not defined'), 'initialize');
            return;
        }

        this._dataBootstrap = this._buildBootstrap();
        this._moduleFrame = $('<iframe>')
            .addClass('heurist-data-viewer-frame')
            .attr({title: 'Heurist data', frameborder: '0'})
            .css({width: '100%', height: '100%', border: 0, display: 'block'})
            .appendTo(this._frameContainer);

        this._installIframeBridge();
        this._moduleFrame.on('load.heuristModuleData', function() {
            this._installIframeBridge();
            this._moduleApi = null;
            this._isReady = false;
            this._waitForDataApi();
        }.bind(this));
        this._moduleFrame.attr('src', this.options.dataApplicationUrl);
    }

    /** Install the same-origin bootstrap bridge on the persistent iframe node. */
    _installIframeBridge() {
        var frame = this._moduleFrame && this._moduleFrame[0];
        if (!frame) return;
        var that = this;
        frame.heuristDataHost = {
            getConfiguration: function() {
                return $.extend(true, {}, that._dataBootstrap || that._buildBootstrap());
            },
            updateSettings: function(settings) {
                that.options.heuristDataSettings = $.extend(true, {}, settings || {});
                that._dataBootstrap = that._buildBootstrap();
                return $.extend(true, {}, that.options.heuristDataSettings);
            },
            updateState: function(state) {
                that.options.heuristDataState = state == null
                    ? null : $.extend(true, {}, state);
                that._dataBootstrap = that._buildBootstrap();
            },
            editRecord: function(recordId) {
                return that._openRecordEdit(recordId);
            },
            addRecord: function(recordTypeId) {
                return that._addRecordEdit(recordTypeId);
            },
            editFieldset: function(value, options) {
                return that._editFieldset(value, options || {});
            },
            doSearch: function(request) {
                return that._doSearch(request);
            }
        };
    }

    /** Build the serializable launch envelope consumed by heurist-data. */
    _buildBootstrap() {
        var hapi = window.hWin && window.hWin.HAPI4;
            
        const lang = hapi
            ? hapi.getLangCode3(hapi.get_prefs_def('layout_language', 'eng'), 'eng')
            : 'eng';            
            
        var runtimeMode = this.options.runtimeMode;
        if (['main', 'website', 'standalone'].indexOf(runtimeMode) < 0) {
            runtimeMode = this.options.configurationMode === 'website'
                ? 'website' : 'main';
        }
        return {
            runtime: {
                viewerMode: this.options.viewerMode === 'configuration'
                    ? 'configuration' : 'data',
                configurationMode: this.options.configurationMode,
                runtimeMode: runtimeMode,
                language: lang,
                database: this.options.database || (hapi && hapi.database),
                apiBaseUrl: this.options.apiBaseUrl ||
                    (hapi && hapi.baseURL + 'api'),
                accessToken: this.options.accessToken || null,
                requestHeaders: $.extend({}, this.options.requestHeaders || {}),
                baseUrl: hapi ? hapi.baseURL : null,
                searchRealm: this.options.search_realm || null,
                source: this.element.attr('id') || null
            },
            settings: $.extend(true, {}, this.options.heuristDataSettings ||
                this.options.configurationValue || {}),
            state: this.options.heuristDataState == null ? null
                : $.extend(true, {}, this.options.heuristDataState),
            source: {
                datasetId: this._normalizeDatasetId(this.options.dataset),
                query: this._normalizeQuery(this.options.query),
                fields: this._normalizeFields(this.options.fields),
                selection: this._normalizeRecordIds(this.options.selection)
            }
        };
    }

    /** Wait for the child application to publish its stable public API. */
    _waitForDataApi() {
        var that = this;
        var attempts = 0;
        if (this._readyTimer) clearInterval(this._readyTimer);
        this._readyTimer = setInterval(function() {
            if (that._isDestroyed) {
                clearInterval(that._readyTimer);
                return;
            }
            attempts++;
            try {
                var frameWindow = that._moduleFrame && that._moduleFrame[0].contentWindow;
                var api = frameWindow && frameWindow.heuristData;
                if (api) {
                    clearInterval(that._readyTimer);
                    that._readyTimer = 0;
                    that._completeInitialization(api);
                } else if (attempts >= 300) {
                    clearInterval(that._readyTimer);
                    that._readyTimer = 0;
                    that._reportError(new Error(
                        'heurist-data did not expose window.heuristData'
                    ), 'initialize');
                }
            } catch (error) {
                clearInterval(that._readyTimer);
                that._readyTimer = 0;
                that._reportError(error, 'initialize');
            }
        }, 100);
    }

    _completeInitialization(api) {
        var that = this;
        Promise.resolve(typeof api.ready === 'function' ? api.ready() : api)
            .then(function() {
                if (that._isDestroyed) return;
                that._moduleApi = api;
                that._isReady = true;
                that._bindDataEvents();
                that._invokeCallback('onready', api);
                return that._flushPendingOperations();
            })
            .catch(function(error) { that._reportError(error, 'initialize'); });
    }

    /** Switch the module to an explicit/current-results query. */
    _setQueryNow(query, options) {
        // HeuristModuleRecordset has already converted either event format to
        // effectiveQuery. heurist-data now follows its normal server-paged path:
        // DataApplication -> QueryLoader -> RecordDataProvider.load(/records).
        this._currentQuery = query;
        this.options.dataset = null;
        if (!this._moduleApi || typeof this._moduleApi.setQuery !== 'function') {
            return Promise.reject(new Error('heurist-data does not implement setQuery'));
        }
        return Promise.resolve(this._moduleApi.setQuery(query, options || {}));
    }

    /** Activate a persisted Dataset record, or return to current results with null. */
    setDataset(datasetId, options) {
        this.options.dataset = this._normalizeDatasetId(datasetId);
        if (this.options.dataset == null && this._hostQueryPending
            && this._isWidgetVisible()) {
            return this._applyPendingHostQueryWhenVisible(false);
        }
        return this._enqueueOrRun('_setDatasetNow', [
            this.options.dataset, options || {}
        ]);
    }

    _setDatasetNow(datasetId, options) {
        if (datasetId == null) {
            return this._setQueryNow(this.options.query, options);
        }
        if (!this._moduleApi || typeof this._moduleApi.setDataset !== 'function') {
            return Promise.reject(new Error('heurist-data does not implement setDataset'));
        }
        return Promise.resolve(this._moduleApi.setDataset(datasetId, options || {}));
    }

    _normalizeDatasetId(datasetId) {
        if (datasetId == null || datasetId === '') return null;
        var id = Number(datasetId);
        if (!Number.isInteger(id) || id < 1) {
            throw new TypeError('Dataset ID must be a positive integer or null');
        }
        return id;
    }

    _normalizeFields(fields) {
        if (fields == null || fields === '') return [];
        var values = Array.isArray(fields) ? fields : String(fields).split(',');
        return values.map(function(field) {
            return $.isPlainObject(field)
                ? $.extend(true, {}, field)
                : {field: $.trim(String(field))};
        }).filter(function(field) { return field.field || field.code; });
    }

    setSelection(recordIds, options) {
        this.options.selection = this._normalizeRecordIds(recordIds);
        return this._enqueueOrRun('_setSelectionNow', [
            this.options.selection, options || {}
        ]);
    }

    _setSelectionNow(recordIds, options) {
        if (!this._moduleApi || typeof this._moduleApi.setSelection !== 'function') {
            return Promise.reject(new Error('heurist-data does not implement setSelection'));
        }
        this._suppressSelectionSync = true;
        var that = this;
        return Promise.resolve(this._moduleApi.setSelection(recordIds, options || {}))
            .finally(function() { that._suppressSelectionSync = false; });
    }

    clearSelection() {
        this.options.selection = [];
        return this._enqueueOrRun('_clearSelectionNow', []);
    }

    _clearSelectionNow() {
        if (this._moduleApi && typeof this._moduleApi.clearSelection === 'function') {
            return Promise.resolve(this._moduleApi.clearSelection());
        }
        return this._setSelectionNow([], {replace: true});
    }

    openConfiguration(options) {
        return this._enqueueOrRun('_openConfigurationNow', [options || {}]);
    }

    _openConfigurationNow(options) {
        if (!this._moduleApi || typeof this._moduleApi.openConfiguration !== 'function') {
            return Promise.reject(new Error(
                'heurist-data does not implement openConfiguration'
            ));
        }
        return Promise.resolve(this._moduleApi.openConfiguration(options || {}));
    }

    refresh() {
        if (!this._isReady) return Promise.resolve(false);
        if (this._moduleApi && typeof this._moduleApi.refresh === 'function') {
            return Promise.resolve(this._moduleApi.refresh());
        }
        return this.options.dataset
            ? this._setDatasetNow(this.options.dataset, {reload: true})
            : this._setQueryNow(this.options.query, {reload: true});
    }

    /** Subscribe to engine-neutral events emitted by the public data API. */
    _bindDataEvents() {
        if (!this._moduleApi || typeof this._moduleApi.addEventListener !== 'function') return;
        var that = this;
        this._dataEventHandlers.selection = function(event) {
            if (that._suppressSelectionSync) return;
            var ids = that._normalizeRecordIds(
                event.detail && (event.detail.recordIds || event.detail.selection)
            );
            that.options.selection = ids;
            var hapi = window.hWin && window.hWin.HAPI4;
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
            var detail = event.detail || {};
            that._reportError(detail.error || detail, detail.operation || 'data-event', detail);
        };
        this._dataEventHandlers.configuration = function(event) {
            that._invokeCallback('onconfiguration', event.detail || {});
        };
        this._dataEventHandlers.editRecord = function(event) {
            that._openRecordEdit(event.detail && event.detail.recordId);
        };

        this._moduleApi.addEventListener(
            'heurist-data-selection-changed', this._dataEventHandlers.selection);
        this._moduleApi.addEventListener(
            'heurist-data-error', this._dataEventHandlers.error);
        this._moduleApi.addEventListener(
            'heurist-data-configuration-requested', this._dataEventHandlers.configuration);
        this._moduleApi.addEventListener(
            'heurist-data-edit-record-requested', this._dataEventHandlers.editRecord);
    }

    _unbindDataEvents() {
        if (!this._moduleApi || typeof this._moduleApi.removeEventListener !== 'function') return;
        var handlers = this._dataEventHandlers;
        var events = {
            selection: 'heurist-data-selection-changed',
            error: 'heurist-data-error',
            configuration: 'heurist-data-configuration-requested',
            editRecord: 'heurist-data-edit-record-requested'
        };
        Object.keys(events).forEach(function(key) {
            if (handlers[key]) this._moduleApi.removeEventListener(events[key], handlers[key]);
        }, this);
        this._dataEventHandlers = {};
    }

    /** Delegate a saved-filter request to the host application's search engine. */
    _doSearch(request) {
        var hapi = window.hWin && window.hWin.HAPI4;
        if (!hapi || !hapi.RecordSearch ||
            typeof hapi.RecordSearch.doSearch !== 'function') {
            return Promise.reject(new Error(
                'The Heurist record search service is unavailable'
            ));
        }

        var searchRequest = $.extend(true, {}, request || {});
        searchRequest.search_realm = searchRequest.search_realm ||
            this.options.search_realm || null;
        searchRequest.source = searchRequest.source || this.element.attr('id');

        this.options.dataset = null;
        hapi.RecordSearch.doSearch(this, searchRequest);
        return true;
    }

    _openRecordEdit(recordId) {
        var id = Number(recordId);
        if (!Number.isInteger(id) || id < 1) {
            return Promise.reject(new Error(
                'A valid Heurist record ID is required for editing'
            ));
        }
        if (this._invokeCallback('oneditrecord', id) === false) {
            return Promise.resolve(false);
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
        var rtID = Number(recordTypeId);
        if (!(rtID > 0)) {
            return Promise.reject(new Error(
                'A valid Heurist record type ID is required for creation'
            ));
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
                ui.openRecordEdit(-1, null, {
                    selectOnSave: true,
                    onselect: function(event, data) {
                        saved = true;
                        var recordset = data && data.selection;
                        var record = recordset && recordset.getFirstRecord
                            ? recordset.getFirstRecord() : null;
                        var recordId = record && recordset.fld
                            ? Number(recordset.fld(record, 'rec_ID')) : null;
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

    /** Open recordFieldSetEditor in the parent and return its JSON field list. */
    _editFieldset(value, options) {
        var hapi = window.hWin && window.hWin.HAPI4;
        var fields = value;
        if (typeof fields === 'string') {
            try { fields = JSON.parse(fields); } catch (ignore) { fields = []; }
        }
        if ($.isPlainObject(fields) && Array.isArray(fields.fields)) {
            fields = fields.fields;
        }
        if (!Array.isArray(fields)) fields = [];
        fields = $.extend(true, [], fields);

        return new Promise(function(resolve, reject) {
            function openEditor() {
                try {
                    $('<div>').appendTo('body').recordFieldSetEditor({
                        isdialog: true,
                        value: {fields: fields},
                        recordTypeId: Number(options && options.recordTypeId) || null,
                        onClose: function(context) {
                            resolve(context && Array.isArray(context.fields)
                                ? $.extend(true, [], context.fields) : null);
                        }
                    });
                } catch (error) {
                    reject(error);
                }
            }

            if ($.fn.recordFieldSetEditor) {
                openEditor();
            } else if (hapi && hapi.baseURL) {
                $.getScript(hapi.baseURL + 'hclient/widgets/record/recordFieldSetEditor.js')
                    .done(openEditor)
                    .fail(function() {
                        reject(new Error('Unable to load the Heurist field-set editor'));
                    });
            } else {
                reject(new Error('Heurist field-set editor is not available'));
            }
        });
    }

    destroy() {
        var that = this;
        this._isDestroyed = true;
        this._isReady = false;
        if (this._readyTimer) clearInterval(this._readyTimer);
        if (this._resizeTimer) clearTimeout(this._resizeTimer);
        if (this._resizeObserver) this._resizeObserver.disconnect();
        this.element.off('.dataViewer .heuristModuleViewer');
        this._unbindHostEvents();
        this._unbindDataEvents();
        this._pendingOperations.splice(0).forEach(function(operation) {
            operation.reject(new Error('dataViewer was destroyed'));
        });

        var promise = this._moduleApi && typeof this._moduleApi.destroy === 'function'
            ? Promise.resolve(this._moduleApi.destroy()).catch(function(error) {
                that._reportError(error, 'destroy');
            }) : Promise.resolve();
        this._moduleApi = null;
        if (this._moduleFrame) this._moduleFrame.remove();
        if (this._frameContainer) this._frameContainer.remove();
        this.element.removeClass('heurist-data-viewer');
        return promise;
    }

    getDataApi() { return this.getModuleApi(); }
}

HeuristModuleData.defaults = $.extend(true, {}, HEURIST_MODULE_DATA_DEFAULTS);
window.HeuristModuleData = HeuristModuleData;
