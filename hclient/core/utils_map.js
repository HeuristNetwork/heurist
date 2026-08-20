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

    /** Complete a map symbol using the supplied/default base values. */
    map.prepareMapSymbol = function(style, def_style){
        if(!def_style){
            def_style = {iconType:'rectype', color:'#ff0000', fillColor:'#ff0000', weight:3, opacity:1,
                    dashArray:'', fillOpacity:0.2, iconSize:18, stroke:true, fill:true};
        }
        if(!style) style = {};
        if(!style.iconType || style.iconType=='default') style.iconType = def_style.iconType;
        if(!(style.iconType=='url' && typeof style.iconSize == 'string' && style.iconSize.indexOf(',')>0)){
            style.iconSize = (style.iconSize>0) ? parseInt(style.iconSize) : def_style.iconSize;
        }
        style.color = style.color ? style.color : def_style.color;
        style.fillColor = style.fillColor ? style.fillColor : def_style.fillColor;
        style.weight = (H.util.isNumber(style.weight) && style.weight>=0) ? style.weight : def_style.weight;
        style.opacity = (H.util.isNumber(style.opacity) && style.opacity>=0) ? style.opacity : def_style.opacity;
        style.fillOpacity = (H.util.isNumber(style.fillOpacity) && style.fillOpacity>=0) ? style.fillOpacity : def_style.fillOpacity;
        style.fill = H.util.isnull(style.fill) ? def_style.fill : style.fill;
        style.fill = H.util.istrue(style.fill);
        style.stroke = H.util.isnull(style.stroke) ? def_style.stroke : style.stroke;
        style.stroke = H.util.istrue(style.stroke);
        if(style.stroke){
            style.dashArray = H.util.isnull(style.dashArray) ? def_style.dashArray : style.dashArray;
        }
        if(!style.iconFont && def_style.iconFont) style.iconFont = def_style.iconFont;
        if(style.fillOpacity>1) style.fillOpacity = style.fillOpacity/100;
        if(style.opacity>1) style.opacity = style.opacity/100;
        return style;
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
                });
            }});
    };

    function loadScript(url){
        return new Promise(function(resolve, reject){
            $.getScript(url).done(resolve).fail(function(jqxhr, settings, exception){
                reject(new Error('Unable to load '+url+(exception ? ': '+exception : '')));
            });
        });
    }

    ui.showEditSymbologyDialog = function(current_value, mode_edit, callback, cancelCallback){
        if(typeof editSymbology !== 'undefined' && H.util.isFunction(editSymbology)){
            return editSymbology(current_value, mode_edit, callback, cancelCallback);
        }
        const url = window.hWin.HAPI4.baseURL+'hclient/widgets/editing/mapSymbolEditor.js';
        return loadScript(url).then(function(){
            if(typeof editSymbology === 'undefined' || !H.util.isFunction(editSymbology)){
                throw new Error('mapSymbolEditor.js did not register editSymbology');
            }
            return editSymbology(current_value, mode_edit, callback, cancelCallback);
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
