/**
* @file utils_map.js
* @brief Shared map symbology utilities and map-editor launchers.
*
* Centralizes canonical vector symbology handling and the public HEURIST4.ui map
* editor entry points. Legacy Heurist remains global-script based; this file does
* not introduce ES modules.
*
* @project     Heurist academic knowledge management system
* @package     Core
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

(function(){
    'use strict';

    if(!window.hWin.HEURIST4) window.hWin.HEURIST4 = {};
    if(!window.hWin.HEURIST4.ui) window.hWin.HEURIST4.ui = {};
    if(!window.hWin.HEURIST4.map) window.hWin.HEURIST4.map = {};

    const H = window.hWin.HEURIST4;
    const ui = H.ui;
    const map = H.map;

    // utils_map.js is also loaded by legacy mapping pages that may run inside an
    // iframe. window.hWin points to the main Heurist host window, so re-running
    // this file in a child frame would otherwise overwrite the host's public map
    // editor launchers with functions closed over the child frame's window/$.
    //
    // If the host has already registered the map editor API, it owns this module.
    // Leave the existing host registrations untouched.
    if(window !== window.hWin &&
       typeof ui.showEditSymbologyDialog === 'function' &&
       typeof ui.showThematicMappingDialog === 'function'){
        return;
    }

    /**
     * Split persisted vector symbology into a sparse base symbol and thematic renderers.
     * Reads the canonical object, the legacy array representation, and simple symbols.
     */
    map.splitSymbology = function(value){
        value = H.util.isJSON(value);
        if(!value) return {symbol:null, thematic:[]};

        if($.isPlainObject(value) &&
           (Object.hasOwn(value, 'symbol') || Array.isArray(value.thematic))){
            return {
                symbol: $.isPlainObject(value.symbol) ? value.symbol : null,
                thematic: Array.isArray(value.thematic) ? value.thematic : []
            };
        }

        if(Array.isArray(value)){
            let symbol = null;
            let thematic = [];
            $.each(value, function(i, item){
                if(!$.isPlainObject(item)) return;
                if(Array.isArray(item.fields)){
                    thematic.push(item);
                }else{
                    symbol = $.isPlainObject(item.symbol) ? item.symbol : item;
                }
            });
            return {symbol:symbol, thematic:thematic};
        }

        return $.isPlainObject(value)
            ? {symbol:value, thematic:[]}
            : {symbol:null, thematic:[]};
    };

    /**
     * Build the canonical persisted value. Simple symbology stays a plain object;
     * thematic symbology is {symbol, thematic}.
     */
    map.buildSymbology = function(symbol, thematic){
        symbol = H.util.cloneJSON(symbol || {});
        thematic = Array.isArray(thematic) ? H.util.cloneJSON(thematic) : [];
        return thematic.length>0 ? {symbol:symbol, thematic:thematic} : symbol;
    };

    /**
     * Built-in vector symbology. This is the final inheritance root used when no
     * configured/default value supplies a property. Keep this object canonical:
     * opacity values are fractions in the range 0..1 and iconSize is a diameter.
     */
    map.DEFAULT_MAP_SYMBOL = Object.freeze({
        iconType: 'rectype',
        color: '#ff0000',
        fillColor: '#ff0000',
        weight: 3,
        opacity: 1,
        dashArray: '',
        fillOpacity: 0.2,
        iconSize: 18,
        stroke: true,
        fill: true
    });

    function cloneObject(value){
        return $.isPlainObject(value) ? H.util.cloneJSON(value) : {};
    }

    /** Convert legacy percentage opacity to the canonical Leaflet 0..1 range. */
    map.normalizeOpacity = function(value){
        if(value === '' || value === null || typeof value === 'undefined') return null;
        let number = Number(value);
        if(!Number.isFinite(number)) return null;
        if(number > 1) number = number / 100;
        return Math.max(0, Math.min(1, number));
    };

    /**
     * Return a canonical local symbol without applying inheritance.
     * Radius is accepted only as a legacy input alias for circle diameter and is
     * never retained as an independent semantic property.
     */
    map.canonicalizeMapSymbol = function(symbol){
        symbol = H.util.isJSON(symbol);
        symbol = cloneObject(symbol);

        if(symbol.iconType === 'default' || symbol.iconType === '') delete symbol.iconType;
        if(Array.isArray(symbol.iconSize)){
            symbol.iconSize = symbol.iconSize.length ? symbol.iconSize[0] : '';
        }
        if((symbol.iconSize === '' || symbol.iconSize === null || typeof symbol.iconSize === 'undefined')
                && Number(symbol.radius) > 0){
            symbol.iconSize = Number(symbol.radius) * 2;
        }
        delete symbol.radius;

        if(Object.hasOwn(symbol, 'opacity')){
            const value = map.normalizeOpacity(symbol.opacity);
            if(value === null) delete symbol.opacity;
            else symbol.opacity = value;
        }
        if(Object.hasOwn(symbol, 'fillOpacity')){
            const value = map.normalizeOpacity(symbol.fillOpacity);
            if(value === null) delete symbol.fillOpacity;
            else symbol.fillOpacity = value;
        }

        if(Object.hasOwn(symbol, 'weight') && symbol.weight !== ''){
            const value = Number(symbol.weight);
            if(Number.isFinite(value) && value >= 0) symbol.weight = value;
        }
        if(Object.hasOwn(symbol, 'iconSize') && symbol.iconSize !== ''
                && !(typeof symbol.iconSize === 'string' && symbol.iconSize.indexOf(',') > 0)){
            const value = Number(symbol.iconSize);
            if(Number.isFinite(value) && value >= 0) symbol.iconSize = value;
        }
        if(Object.hasOwn(symbol, 'fill')) symbol.fill = H.util.istrue(symbol.fill);
        if(Object.hasOwn(symbol, 'stroke')) symbol.stroke = H.util.istrue(symbol.stroke);

        return symbol;
    };

    /**
     * Resolve a sparse symbol against its effective parent. The built-in symbol is
     * always the final fallback, therefore the returned symbol is complete.
     */
    map.normalizeMapSymbol = function(symbol, parentSymbol){
        let result = cloneObject(map.DEFAULT_MAP_SYMBOL);
        const parent = map.canonicalizeMapSymbol(parentSymbol);
        const local = map.canonicalizeMapSymbol(symbol);

        $.each(parent, function(key, value){
            if(value !== '' && value !== null && typeof value !== 'undefined') result[key] = value;
        });
        $.each(local, function(key, value){
            if(value !== '' && value !== null && typeof value !== 'undefined') result[key] = value;
        });
        return result;
    };

    function sameMapValue(a, b){
        if(typeof a === 'number' || typeof b === 'number'){
            const na = Number(a), nb = Number(b);
            if(Number.isFinite(na) && Number.isFinite(nb)) return Math.abs(na-nb) < 1e-9;
        }
        return JSON.stringify(a) === JSON.stringify(b);
    }

    /**
     * Return only explicit overrides needed to reproduce effectiveSymbol over its
     * parent. Radius is deliberately ignored; iconSize is the semantic size field.
     */
    map.diffMapSymbol = function(effectiveSymbol, parentSymbol){
        const parent = map.normalizeMapSymbol({}, parentSymbol);
        const effective = map.normalizeMapSymbol(effectiveSymbol, parent);
        const result = {};

        $.each(effective, function(key, value){
            if(key === 'radius') return;
            if(!Object.hasOwn(parent, key) || !sameMapValue(value, parent[key])){
                result[key] = H.util.cloneJSON(value);
            }
        });
        return result;
    };

    /**
     * Add the derived Leaflet circle radius only when iconSize is an explicit saved
     * override. This keeps inheritance sparse while preserving the historic saved
     * radius required by old Leaflet paths.
     */
    map.prepareMapSymbolForSave = function(sparseSymbol, effectiveSymbol){
        const result = map.canonicalizeMapSymbol(sparseSymbol);
        const effective = map.normalizeMapSymbol(effectiveSymbol, map.DEFAULT_MAP_SYMBOL);
        if(effective.iconType === 'circle' && Object.hasOwn(result, 'iconSize')){
            const size = Number(result.iconSize);
            if(Number.isFinite(size) && size >= 0) result.radius = size / 2;
        }
        return result;
    };

    /** Resolve the configured/user default symbology against the built-in symbol. */
    map.getDefaultMapSymbol = function(value){
        if(typeof value === 'undefined'){
            value = window.hWin.HAPI4 && window.hWin.HAPI4.get_prefs
                ? window.hWin.HAPI4.get_prefs('map_default_style') : null;
        }
        value = H.util.isJSON(value) || {};
        return map.normalizeMapSymbol(value, map.DEFAULT_MAP_SYMBOL);
    };

    /** Compatibility name retained for existing old-map callers. */
    map.prepareMapSymbol = function(style, def_style){
        return map.normalizeMapSymbol(style, def_style || map.DEFAULT_MAP_SYMBOL);
    };

    // Preserve the established public name while implementation lives in HEURIST4.map.
    ui.prepareMapSymbol = map.prepareMapSymbol;

    ui.initEditSymbologyControl = function(element, value){
        element.attr('readonly','readonly');
        value = H.util.isJSON(value);
        if(value) element.val(JSON.stringify(value));
        let parent_div = element.parent();
        $('<span>').addClass('smallbutton ui-icon ui-icon-circlesmall-close')
            .attr('tabindex','-1').attr('title','Reset default symbology').appendTo(parent_div)
            .css({'line-height':'20px',cursor:'pointer',outline:'none','outline-style':'none',
                  'box-shadow':'none','border-color':'transparent'})
            .on({click:function(){ H.msg.showMsgDlg('<br>Are you sure?', function(){element.val('');}); }});
        $('<span>open editor</span>', {title:'Open symbology editor'})
            .addClass('smallbutton btn_add_term')
            .css({'line-height':'20px','vertical-align':'top',cursor:'pointer','text-decoration':'underline'})
            .appendTo(parent_div).on({click:function(){
                let current_val = H.util.isJSON(element.val()) || {};
                ui.showEditSymbologyDialog(current_val, 0, function(new_value){
                    element.val(JSON.stringify(new_value));
                }, null, map.DEFAULT_MAP_SYMBOL);
            }});
    };

    function loadScript(url){
        return new Promise(function(resolve, reject){
            $.getScript(url).done(resolve).fail(function(jqxhr, settings, exception){
                reject(new Error('Unable to load '+url+(exception ? ': '+exception : '')));
            });
        });
    }

    ui.showEditSymbologyDialog = function(current_value, mode_edit, callback, cancelCallback, parentSymbol){
        const hasParent = $.isPlainObject(parentSymbol);
        const isGraphMode = mode_edit === 6 || mode_edit === 7;
        let editorValue = current_value;
        let thematic = [];

        // Map inheritance is opt-in at the caller boundary. Graph modes 6/7 and
        // special editors (for example gradient mode 5) retain their original contract.
        let editorMetadata = {};
        if(hasParent && !isGraphMode){
            let semanticValue = H.util.isJSON(current_value);
            semanticValue = semanticValue ? H.util.cloneJSON(semanticValue) : {};
            // legendLabel must never participate in map-symbol inheritance/diff.
            if($.isPlainObject(semanticValue) && !Object.hasOwn(semanticValue, 'symbol')){
                if(Object.hasOwn(semanticValue, 'legendLabel')){
                    editorMetadata.legendLabel = semanticValue.legendLabel;
                    delete semanticValue.legendLabel;
                }
                if(Object.hasOwn(semanticValue, 'maplayer_query')){
                    editorMetadata.maplayer_query = semanticValue.maplayer_query;
                    delete semanticValue.maplayer_query;
                }
            }

            const parts = map.splitSymbology(semanticValue);
            thematic = parts.thematic;
            editorValue = map.normalizeMapSymbol(parts.symbol || {}, parentSymbol);
            $.each(editorMetadata, function(key, value){ editorValue[key] = H.util.cloneJSON(value); });
        }

        function accept(editedValue){
            if(!H.util.isFunction(callback)) return;
            if(!hasParent || isGraphMode){
                callback(editedValue);
                return;
            }
            
            let semanticEdited = H.util.isJSON(editedValue);
            semanticEdited = semanticEdited ? H.util.cloneJSON(semanticEdited) : {};
            let editedLabel;
            if($.isPlainObject(semanticEdited) && !Object.hasOwn(semanticEdited, 'symbol')
                    && Object.hasOwn(semanticEdited, 'legendLabel')){
                editedLabel = semanticEdited.legendLabel;
                delete semanticEdited.legendLabel;
            }
            const editedParts = map.splitSymbology(semanticEdited);
            const effective = editedParts.symbol || semanticEdited || {};
            let sparse = map.diffMapSymbol(effective, parentSymbol);
            sparse = map.prepareMapSymbolForSave(sparse, effective);

            // Keep existing thematic definitions when the ordinary symbol editor is
            // used on a canonical layer value.
            const renderers = thematic.length ? thematic : editedParts.thematic;
            const result = map.buildSymbology(sparse, renderers);
            if(typeof editedLabel !== 'undefined' && $.isPlainObject(result) && !Object.hasOwn(result, 'symbol')){
                result.legendLabel = editedLabel;
            }
            callback(result);
        }

        function openEditor(){
            return editSymbology(editorValue, mode_edit, accept, cancelCallback);
        }

        if(typeof editSymbology !== 'undefined' && H.util.isFunction(editSymbology)){
            return openEditor();
        }
        const url = window.hWin.HAPI4.baseURL+'hclient/widgets/editing/mapSymbolEditor.js';
        return loadScript(url).then(function(){
            if(typeof editSymbology === 'undefined' || !H.util.isFunction(editSymbology)){
                throw new Error('mapSymbolEditor.js did not register editSymbology');
            }
            return openEditor();
        }).catch(function(error){
            H.msg.showMsgErr({message:error.message, error_title:'Symbology editor loading failed'});
            if(H.util.isFunction(cancelCallback)) cancelCallback();
        });
    };

    /**
     * Dynamically load and open the thematic-map editor. This is the only public
     * application entry point for mapThemesEditor.
     */
    ui.showThematicMappingDialog = function(options){
        options = options || {};
        if(options.isdialog!==false) options.isdialog = true;

        const body = $(window.hWin.document).find('body');
        const base = window.hWin.HAPI4.baseURL+'hclient/';
        let chain = Promise.resolve();

        if(!($.heurist && $.heurist.baseAction)){
            chain = chain.then(function(){ return loadScript(base+'widgets/baseAction.js'); });
        }
        if(!($.heurist && $.heurist.recordAction)){
            chain = chain.then(function(){ return loadScript(base+'widgets/record/recordAction.js'); });
        }
        if(!($.heurist && $.heurist.mapThemesEditor)){
            chain = chain.then(function(){ return loadScript(base+'widgets/editing/mapThemesEditor.js'); });
        }

        return chain.then(function(){
            if(!($.heurist && $.heurist.mapThemesEditor)){
                throw new Error('mapThemesEditor.js did not register heurist.mapThemesEditor');
            }

            const userOnInit = options.onInitFinished;
            const editorOptions = $.extend({}, options);
            editorOptions.onInitFinished = function(){
                if(typeof ui._raiseMapConfigurationChildDialog === 'function'){
                    ui._raiseMapConfigurationChildDialog(this.element.dialog('widget'));
                }
                if(H.util.isFunction(userOnInit)) userOnInit.call(this);
            };

            let container = editorOptions.container ? $(editorOptions.container) : $();
            if(container.length===0){
                container = $('<div>')
                    .attr('id','heurist-dialog-mapThemesEditor-'+H.util.random())
                    .appendTo(body);
            }
            editorOptions.container = container;
            container.mapThemesEditor(editorOptions);
            return container;
        }).catch(function(error){
            H.msg.showMsgErr({message:error.message, error_title:'Thematic map editor loading failed'});
            if(H.util.isFunction(options.onClose)) options.onClose(null);
            return null;
        });
    };

    ui.showMapConfigurationDialog = function(options){
        options = options || {};
        let doc_body = $(window.hWin.document).find('body');
        if(ui._heuristMapConfigurationPromise) return ui._heuristMapConfigurationPromise;

        function loadMapViewer(){
            if($.heurist && $.heurist.mapViewer) return Promise.resolve();
            return loadScript(window.hWin.HAPI4.baseURL+'hclient/widgets/viewers/mapViewer.js').then(function(){
                if(!($.heurist && $.heurist.mapViewer)) throw new Error('mapViewer widget was not registered');
            });
        }
        function closeHost(){
            let host = ui._heuristMapConfigurationHost;
            ui._heuristMapConfigurationHost = null;
            ui._raiseMapConfigurationChildDialog = null;
            if(host){
                try{ if(host.mapViewer('instance')) host.mapViewer('destroy'); }catch(ignore){}
                host.remove();
            }
        }
        ui._heuristMapConfigurationPromise = loadMapViewer().then(function(){
            return new Promise(function(resolve, reject){
                let host = $('<div>').addClass('heurist-map-configuration-host')
                    .css({position:'fixed', inset:0, width:'100vw', height:'100vh',
                          'z-index':10000, background:'transparent'}).appendTo(doc_body);
                ui._heuristMapConfigurationHost = host;
                ui._raiseMapConfigurationChildDialog = function(dialog){
                    let baseZ = parseInt(host.css('z-index'),10);
                    if(!(baseZ>0)) baseZ = 10000;
                    let dlg = dialog ? $(dialog).closest('.ui-dialog') : doc_body.find('.ui-dialog:visible').last();
                    let overlay = doc_body.children('.ui-widget-overlay:visible').last();
                    if(overlay.length) overlay.css('z-index',baseZ+10);
                    if(dlg && dlg.length) dlg.css('z-index',baseZ+11);
                };
                host.mapViewer({
                    presentationMode:'iframe', viewerMode:'configuration',
                    configurationMode:options.mode || 'preferences',
                    configurationValue:options.value || null, eventbased:false,
                    onconfiguration:function(settings, context){
                        closeHost();
                        if(H.util.isFunction(options.onSave)) options.onSave(settings, context);
                        resolve(settings);
                    },
                    oncancelconfiguration:function(settings, context){
                        closeHost();
                        if(H.util.isFunction(options.onCancel)) options.onCancel(settings, context);
                        resolve(null);
                    },
                    onerror:function(error){
                        closeHost();
                        if(H.util.isFunction(options.onError)) options.onError(error);
                        reject(error instanceof Error ? error : new Error(error && error.message ? error.message : String(error)));
                    }
                });
                host.find('iframe').css({position:'absolute',top:0,left:0,width:'100%',height:'100%',
                    margin:0,padding:0,border:0,display:'block',background:'transparent'});
            });
        }).finally(function(){ ui._heuristMapConfigurationPromise = null; });
        return ui._heuristMapConfigurationPromise;
    };

    ui.showImgFilterDialog = function(current_value, callback){
        if(typeof imgFilter !== 'undefined' && H.util.isFunction(imgFilter)){
            imgFilter(current_value, callback);
        }else{
            loadScript(window.hWin.HAPI4.baseURL+'hclient/widgets/editing/imgFilter.js').then(function(){
                ui.showImgFilterDialog(current_value, callback);
            });
        }
    };
})();
