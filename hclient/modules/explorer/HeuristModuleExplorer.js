/**
 * HeuristModuleExplorer.js - legacy host shell for the modern Heurist Explorer.
 *
 * Explorer deliberately does NOT inherit HeuristModuleRecordset: legacy search
 * and selection events stop at the old UI. The bridge exposes only record edit
 * operations and explicit legacy designer services still needed by new modules.
 */
class HeuristModuleExplorer extends HeuristModuleViewer {

    constructor(element, options) {
        super(element, options || {});
        this._moduleFrame = null;
        this._moduleApi = null;
        this._moduleBootstrap = null;
        this._isReady = false;
        this._isDestroyed = false;
        this._pendingOperations = [];
        this._readyTimer = 0;

        this._bindShowEvent();
        this._observeResize();
        this._ensureIframeWhenVisible();
    }

    _createIframe() {
        var url = this.options.explorerApplicationUrl || this._defaultApplicationUrl();
        if (!url) {
            this._reportError(new Error('explorerApplicationUrl is not defined'), 'initialize');
            return;
        }
        this._moduleBootstrap = this._buildBootstrap();
        this._moduleFrame = $('<iframe>').attr({
            title: 'Heurist Explorer',
            frameborder: '0'
        }).css({ width: '100%', height: '100%', border: 0, display: 'block' }).appendTo(this.element);
        this._installIframeBridge();
        this._moduleFrame.on('load.heuristModuleExplorer', function() {
            this._installIframeBridge();
            this._moduleApi = null;
            this._isReady = false;
            this._waitForExplorerApi();
        }.bind(this));
        this._moduleFrame.attr('src', url);
    }

    _defaultApplicationUrl() {
        var hapi = window.hWin && window.hWin.HAPI4;
        return hapi ? hapi.baseURL + 'hclient/modules/explorer/explorerViewer.html' : null;
    }

    _buildBootstrap() {
        var hapi = window.hWin && window.hWin.HAPI4;
        var lang = hapi && typeof hapi.getLangCode3 === 'function'
            ? hapi.getLangCode3(hapi.get_prefs_def('layout_language', 'eng'), 'eng')
            : 'eng';
        return {
            runtime: {
                runtimeMode: 'main',
                language: lang,
                database: this.options.database || (hapi && hapi.database),
                apiBaseUrl: this.options.apiBaseUrl || (hapi && hapi.baseURL + 'api'),
                baseUrl: hapi ? hapi.baseURL : null,
                accessToken: this.options.accessToken || null,
                requestHeaders: $.extend({}, this.options.requestHeaders || {}),
                moduleUrls: $.extend(true, {}, this.options.moduleUrls || {})
            },
            settings: $.extend(true, {}, this.options.heuristModuleSettings || {}),
            state: this.options.heuristModuleState == null
                ? null : $.extend(true, {}, this.options.heuristModuleState)
        };
    }

    _installIframeBridge() {
        var frame = this._moduleFrame && this._moduleFrame[0];
        if (!frame) return;
        var that = this;
        frame.heuristExplorerHost = {
            getConfiguration: function() {
                return $.extend(true, {}, that._moduleBootstrap || that._buildBootstrap());
            },
            updateSettings: function(settings) {
                that.options.heuristModuleSettings = $.extend(true, {}, settings || {});
                that._moduleBootstrap = that._buildBootstrap();
                return $.extend(true, {}, that.options.heuristModuleSettings);
            },
            updateState: function(state) {
                that.options.heuristModuleState = state == null ? null : $.extend(true, {}, state);
                that._moduleBootstrap = that._buildBootstrap();
            },

            // The only legacy runtime services exposed to Explorer.
            editRecord: function(recordId) { return that._openRecordEdit(recordId); },
            viewRecord: function(recordId) { return that._openRecordView(recordId); },
            addRecord: function(recordTypeId) { return that._addRecordEdit(recordTypeId); },
            editSymbology: function(value, options) { return that._editSymbology(value, options || {}); },
            editExtent: function(value, options) { return that._editExtent(value, options || {}); },
            editRules: function(value, options) { return that._editRules(value, options || {}); },
            describeRules: function(rules) { return that._describeRules(rules); },
            selectFieldset: function(value, options) { return that._selectFieldset(value, options || {}); },
            openSearchBuilder: function(options) { return that._openSearchBuilder(options || {}); }
        };
    }

    _waitForExplorerApi() {
        var that = this;
        var attempts = 0;
        if (this._readyTimer) clearInterval(this._readyTimer);
        this._readyTimer = setInterval(function() {
            if (that._isDestroyed) {
                clearInterval(that._readyTimer);
                that._readyTimer = 0;
                return;
            }
            attempts++;
            var frameWindow = that._moduleFrame && that._moduleFrame[0].contentWindow;
            var api = frameWindow && frameWindow.heuristExplorer;
            if (api) {
                clearInterval(that._readyTimer);
                that._readyTimer = 0;
                that._moduleApi = api;
                Promise.resolve(typeof api.ready === 'function' ? api.ready() : api)
                    .then(function() {
                        that._isReady = true;
                        that._flushPendingOperations();
                        that.resize();
                    })
                    .catch(function(error) { that._reportError(error, 'ready'); });
            } else if (attempts > 200) {
                clearInterval(that._readyTimer);
                that._readyTimer = 0;
                that._reportError(new Error('heurist-explorer did not expose its public API'), 'initialize');
            }
        }, 50);
    }

    setDataSource(source) {
        return this._enqueueOrRun('_setDataSourceNow', [source]);
    }
    _setDataSourceNow(source) { return this._moduleApi.setDataSource(source); }

    setSelection(ids) {
        return this._enqueueOrRun('_setSelectionNow', [ids]);
    }
    _setSelectionNow(ids) { return this._moduleApi.setSelection(ids); }

    applyLayout(layout) {
        return this._enqueueOrRun('_applyLayoutNow', [layout]);
    }
    _applyLayoutNow(layout) { return this._moduleApi.applyLayout(layout); }

    getState() {
        return this._enqueueOrRun('_getStateNow', []);
    }
    _getStateNow() { return this._moduleApi.getState(); }

    _openRecordEdit(recordId) {
        var id = Number(recordId);
        if (!Number.isInteger(id) || id < 1) return Promise.reject(new Error('A valid Heurist record ID is required for editing'));
        var ui = window.hWin && window.hWin.HEURIST4 && window.hWin.HEURIST4.ui;
        if (!ui || typeof ui.openRecordEdit !== 'function') return Promise.reject(new Error('Heurist record editor is not available'));
        return new Promise(function(resolve, reject) {
            var settled = false, saved = false;
            function finish(result) { if (!settled) { settled = true; resolve(result); } }
            try {
                ui.openRecordEdit(id, null, {
                    selectOnSave: false,
                    onselect: function() { saved = true; finish({ saved: true, recordId: id }); },
                    onClose: function() { if (!saved) finish({ saved: false, recordId: id }); }
                });
            } catch (error) { reject(error); }
        });
    }

    _addRecordEdit(recordTypeId) {
        var rtID = Number(recordTypeId);
        if (!(rtID > 0)) return Promise.reject(new Error('A valid Heurist record type ID is required for creation'));
        var ui = window.hWin && window.hWin.HEURIST4 && window.hWin.HEURIST4.ui;
        if (!ui || typeof ui.openRecordEdit !== 'function') return Promise.reject(new Error('Heurist record editor is not available'));
        return new Promise(function(resolve, reject) {
            var settled = false, saved = false;
            function finish(result) { if (!settled) { settled = true; resolve(result); } }
            try {
                ui.openRecordEdit(-1, null, {
                    selectOnSave: false,
                    onselect: function(event, data) {
                        saved = true;
                        var recordset = data && data.selection;
                        var record = recordset && recordset.getFirstRecord ? recordset.getFirstRecord() : null;
                        var recordId = record && recordset.fld ? Number(recordset.fld(record, 'rec_ID')) : null;
                        finish({ saved: true, recordId: recordId });
                    },
                    onClose: function() { if (!saved) finish({ saved: false, recordId: null }); },
                    new_record_params: { rt: rtID }
                });
            } catch (error) { reject(error); }
        });
    }

    _openRecordView(recordId) {
        var id = Number(recordId);
        var hapi = window.hWin && window.hWin.HAPI4;
        if (!Number.isInteger(id) || id < 1 || !hapi || !hapi.baseURL) return Promise.reject(new Error('Heurist record viewer is not available'));
        var url = hapi.baseURL + 'viewers/record/renderRecordData.php?db=' + encodeURIComponent(hapi.database) + '&ll=WebSearch&recID=' + id;
        window.hWin.open(url, '_blank', 'noopener');
        return Promise.resolve(true);
    }

    _editRules(value, options) {
        var ui = window.hWin && window.hWin.HEURIST4 && window.hWin.HEURIST4.ui;
        if (!ui || typeof ui.showRulesBuilderDialog !== 'function') return Promise.reject(new Error('Rule Builder is not available'));
        return Promise.resolve(ui.showRulesBuilderDialog(value, options));
    }

    _describeRules(rules) {
        var ui = window.hWin && window.hWin.HEURIST4 && window.hWin.HEURIST4.ui;
        if (!ui || typeof ui.describeExpansionRule !== 'function') return rules || [];
        return (Array.isArray(rules) ? rules : []).map(function(rule) {
            return Object.assign({}, rule, ui.describeExpansionRule(rule));
        });
    }

    _editSymbology(value, options) {
        if (typeof this.options.onEditSymbology === 'function') return Promise.resolve(this.options.onEditSymbology(value, options));
        var fn = window.hWin && window.hWin.editSymbology;
        if (typeof fn !== 'function') fn = typeof window.editSymbology === 'function' ? window.editSymbology : null;
        if (!fn) return Promise.reject(new Error('Symbology editor is not available'));
        return new Promise(function(resolve) {
            fn(value, options.mode_edit || options.mode || 0, function(result) { resolve(result); }, function() { resolve(null); });
        });
    }

    _editExtent(value, options) {
        if (typeof this.options.onEditExtent === 'function') return Promise.resolve(this.options.onEditExtent(value, options));
        return Promise.reject(new Error('Extent editor is not configured for Explorer'));
    }

    _selectFieldset(value, options) {
        if (typeof this.options.onSelectFieldset === 'function') return Promise.resolve(this.options.onSelectFieldset(value, options));
        return Promise.reject(new Error('Fieldset selector is not configured for Explorer'));
    }

    _openSearchBuilder(options) {
        // SearchBuilder is used only as a designer. It writes the composed query
        // into a temporary input; Explorer validates/executes the returned query.
        if (typeof this.options.onSearchBuilder === 'function') {
            return Promise.resolve(this.options.onSearchBuilder(options));
        }
        var hapi = window.hWin && window.hWin.HAPI4;
        if (!hapi || !hapi.baseURL) return Promise.reject(new Error('Search Builder is not available'));

        return new Promise(function(resolve, reject) {
            var input = $('<input type="hidden">').val(options && options.query ? options.query : '').appendTo('body');
            var open = function() {
                if (typeof window.showSearchBuilder !== 'function') {
                    input.remove();
                    reject(new Error('Search Builder did not load'));
                    return;
                }
                window.showSearchBuilder({
                    is_modal: true,
                    input_element: input,
                    onClose: function() {
                        var value = $.trim(input.val() || '');
                        input.remove();
                        resolve(value || null);
                    }
                });
            };
            if (typeof window.showSearchBuilder === 'function') {
                open();
                return;
            }
            var path = hapi.baseURL + 'hclient/widgets/search/';
            var scripts = [path + 'searchBuilder.js', path + 'searchBuilderItem.js', path + 'searchBuilderSort.js'];
            if (typeof $.getMultiScripts !== 'function') {
                input.remove();
                reject(new Error('Search Builder loader is not available'));
                return;
            }
            $.getMultiScripts(scripts).done(open).fail(function(error) {
                input.remove();
                reject(error instanceof Error ? error : new Error('Cannot load Search Builder'));
            });
        });
    }

    destroy() {
        this._isDestroyed = true;
        if (this._readyTimer) clearInterval(this._readyTimer);
        if (this._resizeObserver) this._resizeObserver.disconnect();
        if (this._resizeTimer) clearTimeout(this._resizeTimer);
        this.element.off('.heuristModuleViewer');
        var api = this._moduleApi;
        this._moduleApi = null;
        this._isReady = false;
        var frame = this._moduleFrame;
        this._moduleFrame = null;
        return Promise.resolve(api && typeof api.destroy === 'function' ? api.destroy() : true)
            .finally(function() { frame && frame.remove(); });
    }
}

window.HeuristModuleExplorer = HeuristModuleExplorer;
