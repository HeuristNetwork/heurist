/**
 * dataViewer.js - Self-loading jQuery adapter for HeuristModuleData
 *
 * Registers the legacy widget immediately, then loads its non-module class
 * dependencies in order before constructing HeuristModuleData.
 *
 * @project     Heurist academic knowledge management system
 * @package     hclient.modules.data
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 */
(function() {
    'use strict';

    var heuristBaseUrl = window.hWin && window.hWin.HAPI4
        ? window.hWin.HAPI4.baseURL : null;
    var modulesBaseUrl = String(heuristBaseUrl).replace(/\/?$/, '/') + 'hclient/modules/';
    if (!modulesBaseUrl) throw new Error('Cannot resolve Heurist modules URL');

    function loadScript(relativeUrl, globalName) {
        if (globalName && window[globalName]) return Promise.resolve();
        var url = modulesBaseUrl + relativeUrl;
        window.heuristModuleScriptPromises = window.heuristModuleScriptPromises || {};
        if (window.heuristModuleScriptPromises[url]) {
            return window.heuristModuleScriptPromises[url];
        }
        window.heuristModuleScriptPromises[url] = new Promise(function(resolve, reject) {
            var script = document.createElement('script');
            script.src = url;
            script.async = false;
            script.onload = function() {
                if (!globalName || window[globalName]) resolve();
                else reject(new Error(url + ' did not define window.' + globalName));
            };
            script.onerror = function() { reject(new Error('Cannot load ' + url)); };
            document.head.appendChild(script);
        });
        return window.heuristModuleScriptPromises[url];
    }

    function loadDependencies() {
        return loadScript('core/HeuristModuleViewer.js', 'HeuristModuleViewer')
            .then(function() {
                return loadScript('core/HeuristModuleRecordset.js',
                    'HeuristModuleRecordset');
            })
            .then(function() {
                return loadScript('data/HeuristModuleData.js', 'HeuristModuleData');
            });
    }

    $.widget('heurist.dataViewer', {
        options: {},

        _create: function() {
            var that = this;
            this._module = null;
            this._moduleDestroyed = false;
            this._moduleLoadError = null;
            this._modulePromise = loadDependencies().then(function() {
                if (that._moduleDestroyed) return null;
                that._module = new window.HeuristModuleData(that.element, that.options);
                return that._module;
            }).catch(function(error) {
                that._moduleLoadError = error;
                that._reportLoadError(error);
                return null;
            });
        },

        _setOption: function(key, value) {
            this._super(key, value);
            if (this._module) this._module.setOption(key, value);
        },

        _callModule: function(method, args) {
            var that = this;
            return this._modulePromise.then(function(module) {
                if (!module) throw that._moduleLoadError
                    || new Error('dataViewer was destroyed');
                return module[method].apply(module, args || []);
            });
        },

        setQuery: function(query, options) {
            return this._callModule('setQuery', [query, options]);
        },
        setRecordSet: function(recordset, options) {
            return this._callModule('setRecordSet', [recordset, options]);
        },
        setSelection: function(recordIds, options) {
            return this._callModule('setSelection', [recordIds, options]);
        },
        clearSelection: function() { return this._callModule('clearSelection'); },
        openConfiguration: function(options) {
            return this._callModule('openConfiguration', [options]);
        },
        setDataset: function(datasetId, options) {
            return this._callModule('setDataset', [datasetId, options]);
        },
        refresh: function() { return this._callModule('refresh'); },
        resize: function() { return this._callModule('resize'); },
        getDataApi: function() {
            return this._module ? this._module.getDataApi() : null;
        },
        getModuleApi: function() {
            return this._module ? this._module.getModuleApi() : null;
        },
        isReady: function() { return this._module ? this._module.isReady() : false; },

        _reportLoadError: function(error) {
            var msg = window.hWin && window.hWin.HEURIST4 && window.hWin.HEURIST4.msg;
            if (msg && typeof msg.showMsgErr === 'function') msg.showMsgErr(error.message);
            else if (window.console) console.error('dataViewer:', error);
        },

        _destroy: function() {
            this._moduleDestroyed = true;
            var module = this._module;
            this._module = null;
            return module ? module.destroy() : Promise.resolve();
        }
    });
}());
