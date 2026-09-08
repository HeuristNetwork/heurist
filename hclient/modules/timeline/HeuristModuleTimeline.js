/**
 * HeuristModuleTimeline.js - Host class for the heurist-timeline application.
 *
 * Hosts heurist-timeline in an iframe and translates Heurist current-result,
 * record selection and multi-context state to its serializable public API.
 */
const HEURIST_MODULE_TIMELINE_DEFAULTS = {
    presentationMode: 'iframe',
    viewerMode: 'timeline',
    configurationMode: 'preferences',
    runtimeMode: null,
    configurationValue: null,
    database: window.hWin && window.hWin.HAPI4 ? window.hWin.HAPI4.database : null,
    apiBaseUrl: window.hWin && window.hWin.HAPI4 ? window.hWin.HAPI4.baseURL + 'api' : null,
    timelineApplicationUrl: window.hWin && window.hWin.HAPI4
        ? window.hWin.HAPI4.baseURL + 'hclient/modules/timeline/timelineViewer.html' : null,
    accessToken: null,
    requestHeaders: null,
    heuristModuleSettings: null,
    heuristModuleState: null,
    contexts: null,
    query: null,
    timefields: null,
    fields: null,
    recordset: null,
    selection: null,
    eventbased: true,
    search_realm: null,
    onready: null,
    onselect: null,
    onrangechange: null,
    onerror: null
};

class HeuristModuleTimeline extends HeuristModuleRecordset {
    constructor(element, options) {
        super(element, $.extend(true, {}, HEURIST_MODULE_TIMELINE_DEFAULTS, options || {}));
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
        this._timelineEventHandlers = {};
        this._suppressSelectionSync = false;
        this.element.addClass('heurist-timeline-viewer').css({position:'relative', overflow:'hidden'});
        this._frameContainer = $('<div>').addClass('heurist-timeline-viewer-frame-container')
            .css({position:'absolute', inset:0}).appendTo(this.element);
        if (this.options.presentationMode !== 'iframe') {
            this._reportError(new Error('Only iframe presentationMode is implemented by timelineViewer'), 'initialize');
            return;
        }
        this._bindHostEvents();
        this._bindShowEvent();
        this._observeResize();
        this._ensureIframeWhenVisible();
    }

    _createIframe() {
        if (this._moduleFrame || this._isDestroyed) return;
        if (!this.options.timelineApplicationUrl) {
            this._reportError(new Error('timelineApplicationUrl is not defined'), 'initialize'); return;
        }
        this._moduleBootstrap = this._buildBootstrap();
        this._moduleFrame = $('<iframe>').addClass('heurist-timeline-viewer-frame')
            .attr({title:'Heurist timeline', frameborder:'0'})
            .css({width:'100%',height:'100%',border:0,display:'block'}).appendTo(this._frameContainer);
        this._installIframeBridge();
        this._moduleFrame.on('load.heuristModuleTimeline', function() {
            this._installIframeBridge(); this._moduleApi=null; this._isReady=false; this._waitForTimelineApi();
        }.bind(this));
        this._moduleFrame.attr('src', this.options.timelineApplicationUrl);
    }

    _installIframeBridge() {
        var frame=this._moduleFrame && this._moduleFrame[0]; if(!frame) return;
        var that=this;
        frame.heuristTimelineHost = {
            getConfiguration:function(){ return that._getConfiguration ? that._getConfiguration() : that.options.heuristModuleSettings || {}; },
            updateSettings:function(settings){ that.options.heuristModuleSettings=$.extend(true,{},settings||{}); return true; },
            updateState:function(state){ that.options.heuristModuleState=$.extend(true,{},state||{}); return true; }
        };
    }

    _buildBootstrap() {
        var hapi=window.hWin && window.hWin.HAPI4;
        var lang=hapi ? hapi.getLangCode3(hapi.get_prefs_def('layout_language','eng'),'eng') : 'eng';
        var runtimeMode=this.options.runtimeMode;
        if (['main','website','standalone'].indexOf(runtimeMode)<0) runtimeMode=this.options.configurationMode==='website'?'website':'main';
        var contexts=this._normalizeContexts(this.options.contexts);
        if (!contexts.length && this.options.query) contexts.push({
            id:'current', title:'Current result', query:this._normalizeQuery(this.options.query),
            timefields:this.options.timefields || null, fields:this._normalizeFields(this.options.fields)
        });
        return {
            runtime:{ viewerMode:'timeline', configurationMode:this.options.configurationMode, runtimeMode:runtimeMode,
                language:lang, database:this.options.database || (hapi&&hapi.database), apiBaseUrl:this.options.apiBaseUrl || (hapi&&hapi.baseURL+'api'),
                accessToken:this.options.accessToken||null, requestHeaders:$.extend({},this.options.requestHeaders||{}),
                baseUrl:hapi?hapi.baseURL:null, searchRealm:this.options.search_realm||null, source:this.element.attr('id')||null },
            settings:$.extend(true,{},this.options.heuristModuleSettings || this.options.configurationValue || {}),
            state:this.options.heuristModuleState==null?null:$.extend(true,{},this.options.heuristModuleState),
            source:{ contexts:contexts, selection:this._normalizeRecordIds(this.options.selection) }
        };
    }

    _waitForTimelineApi() {
        var that=this, attempts=0; if(this._readyTimer) clearInterval(this._readyTimer);
        this._readyTimer=setInterval(function(){
            if(that._isDestroyed){clearInterval(that._readyTimer);return;} attempts++;
            try{
                var frameWindow=that._moduleFrame&&that._moduleFrame[0].contentWindow;
                var api=frameWindow&&frameWindow.heuristTimeline;
                if(api){ clearInterval(that._readyTimer);that._readyTimer=0;that._completeInitialization(api); }
                else if(attempts>=300){clearInterval(that._readyTimer);that._readyTimer=0;that._reportError(new Error('heurist-timeline did not expose window.heuristTimeline'),'initialize');}
            }catch(error){clearInterval(that._readyTimer);that._readyTimer=0;that._reportError(error,'initialize');}
        },100);
    }

    _completeInitialization(api) {
        var that=this;
        Promise.resolve(typeof api.ready==='function'?api.ready():api).then(function(){
            if(that._isDestroyed)return; that._moduleApi=api;that._isReady=true;that._bindTimelineEvents();that._invokeCallback('onready',api);return that._flushPendingOperations();
        }).catch(function(error){that._reportError(error,'initialize');});
    }

    _normalizeFields(fields) {
        if (!fields) return []; if(Array.isArray(fields)) return fields.slice(); if(typeof fields==='string') return fields.split(',').map(function(v){return $.trim(v);}).filter(Boolean); return [];
    }
    _normalizeContexts(contexts) {
        var that=this;
        return (Array.isArray(contexts)?contexts:[]).map(function(c,i){
            c=c||{}; return { id:String(c.id==null?'context-'+(i+1):c.id), title:String(c.title||('Band '+(i+1))),
                query:that._normalizeQuery(c.query), ids:Array.isArray(c.ids)?that._normalizeRecordIds(c.ids):null,
                timefields:c.timefields==null?null:c.timefields, fields:that._normalizeFields(c.fields), visible:c.visible!==false,
                options:$.extend(true,{},c.options||{}) };
        }).filter(function(c){return c.query || (c.ids&&c.ids.length);});
    }

    _setQueryNow(query, options) {
        this._currentQuery=query; this.options.query=query;
        if(!this._moduleApi||typeof this._moduleApi.setQuery!=='function') return Promise.reject(new Error('heurist-timeline does not implement setQuery'));
        return Promise.resolve(this._moduleApi.setQuery(query, $.extend({}, options||{}, {timefields:(options&&options.timefields)??this.options.timefields, fields:(options&&options.fields)||this.options.fields||[]})));
    }
    setContexts(contexts, options) { this.options.contexts=this._normalizeContexts(contexts); return this._enqueueOrRun('_setContextsNow',[this.options.contexts,options||{}]); }
    _setContextsNow(contexts, options) { if(!this._moduleApi||typeof this._moduleApi.setContexts!=='function') return Promise.reject(new Error('heurist-timeline does not implement setContexts')); return Promise.resolve(this._moduleApi.setContexts(contexts,options||{})); }
    addContext(context) { return this._enqueueOrRun('_addContextNow',[context]); }
    _addContextNow(context) { return Promise.resolve(this._moduleApi.addContext(context)); }
    removeContext(id) { return this._enqueueOrRun('_removeContextNow',[id]); }
    _removeContextNow(id) { return Promise.resolve(this._moduleApi.removeContext(id)); }
    setSelection(recordIds, options) { this.options.selection=this._normalizeRecordIds(recordIds); return this._enqueueOrRun('_setSelectionNow',[this.options.selection,options||{}]); }
    _setSelectionNow(ids, options) { var that=this; this._suppressSelectionSync=true; return Promise.resolve(this._moduleApi.setSelection(ids,options||{})).finally(function(){that._suppressSelectionSync=false;}); }
    refresh() { return this._isReady&&this._moduleApi&&this._moduleApi.refresh?Promise.resolve(this._moduleApi.refresh()):Promise.resolve(false); }
    zoomToAll(){ return this._enqueueOrRun('_zoomToAllNow',[]); }
    _zoomToAllNow(){ return Promise.resolve(this._moduleApi.zoomToAll()); }
    zoomToSelection(){ return this._enqueueOrRun('_zoomToSelectionNow',[]); }
    _zoomToSelectionNow(){ return Promise.resolve(this._moduleApi.zoomToSelection()); }

    _bindTimelineEvents() {
        if(!this._moduleApi||typeof this._moduleApi.addEventListener!=='function') return;
        var that=this;
        this._timelineEventHandlers.selection=function(event){
            if(that._suppressSelectionSync)return; var ids=that._normalizeRecordIds(event.detail&&(event.detail.recordIds||event.detail.selection)); that.options.selection=ids;
            var hapi=window.hWin&&window.hWin.HAPI4;
            if(that.options.eventbased&&hapi&&hapi.Event) $(that.document).trigger(hapi.Event.ON_REC_SELECT,{selection:ids,source:that.element.attr('id'),search_realm:that.options.search_realm,reset:ids.length===0});
            that._invokeCallback('onselect',ids,event.detail||{});
        };
        this._timelineEventHandlers.range=function(event){that._invokeCallback('onrangechange',event.detail||{});};
        this._timelineEventHandlers.error=function(event){var d=event.detail||{};that._reportError(d.error||d,d.operation||'timeline-event',d);};
        this._moduleApi.addEventListener('heurist-timeline-selection-changed',this._timelineEventHandlers.selection);
        this._moduleApi.addEventListener('heurist-timeline-range-changed',this._timelineEventHandlers.range);
        this._moduleApi.addEventListener('heurist-timeline-error',this._timelineEventHandlers.error);
    }
    _unbindTimelineEvents() {
        if(!this._moduleApi||typeof this._moduleApi.removeEventListener!=='function')return;
        var h=this._timelineEventHandlers;
        if(h.selection)this._moduleApi.removeEventListener('heurist-timeline-selection-changed',h.selection);
        if(h.range)this._moduleApi.removeEventListener('heurist-timeline-range-changed',h.range);
        if(h.error)this._moduleApi.removeEventListener('heurist-timeline-error',h.error);
        this._timelineEventHandlers={};
    }
    destroy() {
        var that=this;this._isDestroyed=true;this._isReady=false;if(this._readyTimer)clearInterval(this._readyTimer);if(this._resizeTimer)clearTimeout(this._resizeTimer);if(this._resizeObserver)this._resizeObserver.disconnect();
        this.element.off('.timelineViewer .heuristModuleViewer');this._unbindHostEvents();this._unbindTimelineEvents();this._pendingOperations.splice(0).forEach(function(op){op.reject(new Error('timelineViewer was destroyed'));});
        var promise=this._moduleApi&&typeof this._moduleApi.destroy==='function'?Promise.resolve(this._moduleApi.destroy()).catch(function(error){that._reportError(error,'destroy');}):Promise.resolve();
        this._moduleApi=null;if(this._moduleFrame)this._moduleFrame.remove();if(this._frameContainer)this._frameContainer.remove();this.element.removeClass('heurist-timeline-viewer');return promise;
    }
    getTimelineApi(){ return this.getModuleApi(); }
}
HeuristModuleTimeline.defaults=$.extend(true,{},HEURIST_MODULE_TIMELINE_DEFAULTS);
window.HeuristModuleTimeline=HeuristModuleTimeline;
