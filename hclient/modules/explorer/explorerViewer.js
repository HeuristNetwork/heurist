(function() {
    'use strict';
    var baseUrl = window.hWin && window.hWin.HAPI4 ? window.hWin.HAPI4.baseURL : null;
    var modulesBaseUrl = baseUrl ? String(baseUrl).replace(/\/?$/, '/') + 'hclient/modules/' : null;
    if (!modulesBaseUrl) throw new Error('Cannot resolve Heurist modules URL');

    function loadScript(relativeUrl, globalName) {
        if (window[globalName]) return Promise.resolve();
        window.heuristModuleScriptPromises = window.heuristModuleScriptPromises || {};
        var url = modulesBaseUrl + relativeUrl;
        if (window.heuristModuleScriptPromises[url]) return window.heuristModuleScriptPromises[url];
        window.heuristModuleScriptPromises[url] = new Promise(function(resolve, reject) {
            var script = document.createElement('script');
            script.src = url;
            script.async = false;
            script.onload = function() { window[globalName] ? resolve() : reject(new Error(url + ' did not define window.' + globalName)); };
            script.onerror = function() { reject(new Error('Cannot load ' + url)); };
            document.head.appendChild(script);
        });
        return window.heuristModuleScriptPromises[url];
    }

    $.widget('heurist.explorerViewer', {
        options: {},
        _create: function() {
            var that = this;
            this._module = null;
            this._destroyed = false;
            this._modulePromise = loadScript('core/HeuristModuleViewer.js', 'HeuristModuleViewer')
                .then(function() { return loadScript('explorer/HeuristModuleExplorer.js', 'HeuristModuleExplorer'); })
                .then(function() {
                    if (that._destroyed) return null;
                    that._module = new window.HeuristModuleExplorer(that.element, that.options);
                    return that._module;
                });
        },
        _call: function(method, args) {
            return this._modulePromise.then(function(module) {
                if (!module) throw new Error('explorerViewer was destroyed');
                return module[method].apply(module, args || []);
            });
        },
        setDataSource: function(source) { return this._call('setDataSource', [source]); },
        setSelection: function(ids) { return this._call('setSelection', [ids]); },
        applyLayout: function(layout) { return this._call('applyLayout', [layout]); },
        getState: function() { return this._call('getState'); },
        resize: function() { return this._call('resize'); },
        isReady: function() { return this._module ? this._module.isReady() : false; },
        _destroy: function() { this._destroyed = true; return this._module ? this._module.destroy() : Promise.resolve(); }
    });
}());
