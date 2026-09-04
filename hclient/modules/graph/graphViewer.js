/**
 * @file graphViewer.js
 * @brief Legacy jQuery adapter for the heurist-graph iframe module.
 * @project     Heurist academic knowledge management system
 * @package     hclient.modules.graph
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2024 onwards Heurist Network
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @since       8.0
 */

(function() {
    'use strict';

    const baseUrl = window.hWin && window.hWin.HAPI4 ? window.hWin.HAPI4.baseURL : null;
    const modulesBaseUrl = baseUrl ? String(baseUrl).replace(/\/?$/, '/') + 'hclient/modules/' : null;
    if (!modulesBaseUrl) throw new Error('Cannot resolve Heurist modules URL');

    function loadScript(relativeUrl, globalName) {
        if (window[globalName]) return Promise.resolve();
        window.heuristModuleScriptPromises = window.heuristModuleScriptPromises || {};
        const url = modulesBaseUrl + relativeUrl;
        if (window.heuristModuleScriptPromises[url]) return window.heuristModuleScriptPromises[url];
        window.heuristModuleScriptPromises[url] = new Promise(function(resolve, reject) {
            const script = document.createElement('script');
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

    $.widget('heurist.graphViewer', {
        options: {},

        _create: function() {
            const that = this;
            this._module = null;
            this._destroyed = false;
            this._modulePromise = loadScript('core/HeuristModuleViewer.js', 'HeuristModuleViewer')
                .then(function() { return loadScript('core/HeuristModuleRecordset.js', 'HeuristModuleRecordset'); })
                .then(function() { return loadScript('graph/HeuristModuleGraph.js', 'HeuristModuleGraph'); })
                .then(function() {
                    if (that._destroyed) return null;
                    that._module = new window.HeuristModuleGraph(that.element, that.options);
                    return that._module;
                })
                .catch(function(error) {
                    that._reportError(error);
                    throw error;
                });
        },

        _callModule: function(method, args) {
            return this._modulePromise.then(function(module) {
                if (!module) throw new Error('graphViewer was destroyed');
                return module[method].apply(module, args || []);
            });
        },

        setQuery: function(query, options) { return this._callModule('setQuery', [query, options]); },
        setSelection: function(ids, options) { return this._callModule('setSelection', [ids, options]); },
        expandNode: function(id) { return this._callModule('expandNode', [id]); },
        refresh: function() { return this._callModule('refresh'); },
        resize: function() { return this._callModule('resize'); },
        getGraphApi: function() { return this._module ? this._module.getGraphApi() : null; },
        getModuleApi: function() { return this._module ? this._module.getModuleApi() : null; },
        isReady: function() { return this._module ? this._module.isReady() : false; },

        _reportError: function(error) {
            const message = error && error.message ? error.message : String(error);
            const hostMessage = window.hWin && window.hWin.HEURIST4 && window.hWin.HEURIST4.msg;
            if (hostMessage && typeof hostMessage.showMsgErr === 'function') hostMessage.showMsgErr(message);
            else if (window.console) console.error('graphViewer:', error);
        },

        _destroy: function() {
            this._destroyed = true;
            const module = this._module;
            this._module = null;
            return module ? module.getModuleApi()?.destroy?.() : Promise.resolve();
        }
    });
}());
