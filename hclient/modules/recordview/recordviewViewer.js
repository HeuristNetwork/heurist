/**
 * @file recordviewViewer.js
 * @brief Legacy jQuery adapter for the heurist-recordview iframe module.
 * @project     Heurist academic knowledge management system
 * @package     hclient.modules.recordview
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

    $.widget('heurist.recordviewViewer', {
        options: {},

        _create: function() {
            const that = this;
            this._module = null;
            this._destroyed = false;
            // RecordView extends HeuristModuleViewer directly (it never
            // consumes a query/search), so unlike Data/Map/Graph/Timeline it
            // does not need core/HeuristModuleRecordset.js.
            this._modulePromise = loadScript('core/HeuristModuleViewer.js', 'HeuristModuleViewer')
                .then(function() { return loadScript('recordview/HeuristModuleRecordView.js', 'HeuristModuleRecordView'); })
                .then(function() {
                    if (that._destroyed) return null;
                    that._module = new window.HeuristModuleRecordView(that.element, that.options);
                    return that._module;
                })
                .catch(function(error) {
                    that._reportError(error);
                    throw error;
                });
        },

        _callModule: function(method, args) {
            return this._modulePromise.then(function(module) {
                if (!module) throw new Error('recordviewViewer was destroyed');
                return module[method].apply(module, args || []);
            });
        },

        setRecord: function(recordId) { return this._callModule('setRecord', [recordId]); },
        setSelection: function(ids, options) { return this._callModule('setSelection', [ids, options]); },
        clear: function() { return this._callModule('clear'); },
        setOptions: function(options) { return this._callModule('setOptions', [options]); },
        getState: function() { return this._callModule('getState'); },
        refresh: function() { return this._callModule('resize'); },
        resize: function() { return this._callModule('resize'); },
        getRecordViewApi: function() { return this._module ? this._module.getRecordViewApi() : null; },
        getModuleApi: function() { return this._module ? this._module.getModuleApi() : null; },
        isReady: function() { return this._module ? this._module.isReady() : false; },

        _reportError: function(error) {
            const message = error && error.message ? error.message : String(error);
            const hostMessage = window.hWin && window.hWin.HEURIST4 && window.hWin.HEURIST4.msg;
            if (hostMessage && typeof hostMessage.showMsgErr === 'function') hostMessage.showMsgErr(message);
            else if (window.console) console.error('recordviewViewer:', error);
        },

        _destroy: function() {
            this._destroyed = true;
            const module = this._module;
            this._module = null;
            return module ? module.getModuleApi()?.destroy?.() : Promise.resolve();
        }
    });
}());
