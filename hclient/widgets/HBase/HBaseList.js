/**
* HBaseList - template widget for listing of record
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/

/*
* HBaseWidget->HBaseList->HRecordList, HRecordTable, HRecordCards, HRecordMap, HRecordNetwork
*
* setRecordSet - sets new recordset to this record list
* doSearch, _onGetRecords - Search for initial search or on search domain event
* clearContent 
* renderMessage - adds notification/placeholder message (init, error or for empty result)
* renderConent 

* setSelection
* getSelection
* triggerLoadAnimation
* eventHandler
* onSearchStart
* onSearchFinish
* onSelect
*/
import './HBaseWidget.js';

$.widget( 'heurist.HBaseList', $.heurist.HBaseWidget, {

    // default options
    options: {
        entityType: 'rec', //'rec' by default

        searchDomain: null,     // unique id to distiguish search/select events 
        searchInitial: null,    // initial search query
        
        recordSet:null         // initial recordset
    },
    
    _needLoadContent: false,
    _needLoadCss: false,
    
    recordSet:null,   // HRecordSet
    recordSetSelected:[], // array of selected ids
    recordSetSubset:null, // HRecordSet - filtered and sorted locally
    
    _events:null,
    
    /*
    * 
    */
    _init: function(){
        
        this._super();
        
        if(!this.$H.isempty(this.options.searchDomain)){        
            this._events = 
                        this.HAPI.Event.ON_REC_SEARCHSTART
                + ' ' + this.HAPI.Event.ON_REC_SELECT
                + ' ' + this.HAPI.Event.ON_REC_SEARCH_FINISH;

            let that = this;
            $(this.document).on(this._events, (event, data)=>that.eventHandler(event, data) );
        }
    },
    
    /*
    * 
    */
    _destroy: function(){
        
        this._super();
        
        if(this._events){
            $(this.document).off(this._events);
        }
    },
    
    /*
    * Use it a) to add event listeners for subelements of this widget
    *        b) perform some default actions (intial search for example) 
    */
    _initControls:function(){
    
        this._super();
        
        if(this.options.recordSet){
            this.setRecordSet(this.options.recordSet);
        }else if(this.options.searchInitial)
        {
            this.doSearch(this.options.searchInitial);
        }else{
            this.renderMessage('initial');
        }
    },

    //
    // Removes generated elements
    //
    clearContent: function(){
        this.element.innerHTML = '';
        this.setSelection(null); //clear selection
    },

    /*
    * Sets new recordset to this record list
    */
    setRecordSet: function( recordset ){

        if(!this._initCompleted) return;

        this.clearContent();
        this.recordSet = recordset;
        
        this.renderConent();
    },
    
    /*
    * Search for initial search or on search domain event
    */
    doSearch: function( query ){

        let that = this;
      
        //request for records
        if(this.options.entityType=='rec'){
            let request = {q:query, w: 'a', detail: 'ids', needall: 1};

            if(!this.$H.isempty(this.options.searchDomain)){
                request['search_realm'] = this.options.searchDomain;
                request['source'] = this._widgetId; //search_origin
                this.document.trigger(this.HAPI.Event.ON_REC_SEARCHSTART, request);
            }

            this.HAPI.RecordMgr.search2(request, response=>that._onGetRecords(query, response));

        }else{

            const request = {
                'a'          : 'search',
                'entity'     : this.options.entityType,
                'details'    : 'ids'
            };
            this.HAPI.EntityMgr.doRequest(request, response=>that._onGetRecords(query, response));
        }
    },

    /*
    * Response handler for search request
    */
    _onGetRecords: function(query, response){
        
        if(response.status != window.hWin.ResponseStatus.OK){
            window.hWin.HEURIST4.msg.showMsgErr(response);    
            return;
        }

        let recordset = new HRecordSet( response.data );
        this.setRecordSet(recordset);
        
        if(!this.$H.isempty(this.options.searchDomain)){
            $(this.document).trigger(this.HAPI.Event.ON_REC_SEARCH_FINISH, {
                                recordset: this.recordset,
                                search_realm: this.options.searchDomain,
                                source: this._widgetId, //search origin
                                query: query
                            });
        }
    },
    
    /*
    * Adds notification/placeholder message (init, error or for empty result)
    */
    renderMessage: function(msg){
    
        this.clearContent();
        
        let $emptyres = $('<div>')
        .css('merge','auto')
        .html(msg)
        .appendTo(this.element);
        
    },
    
    /*
    *
    */
    renderConent: function(){

        if(this.recordSet==null || this.recordSet.count_total()==0){
            //render placeholder
            this._renderMessage('empty recordset');
        }else{
            this._renderMessage(`recordset${this.recordSet.count_total()} records`);
        }
    },
    
    
    /**
    * return HRecordSet or array of ids of selected records
    */
    getSelection: function( idsonly ){

        if(idsonly){
            return this.recordSetSelected;
        }else if(this.recordSet){
            return this.recordSet.getSubSetByIds(this.recordSetSelected);
        }else{
            return null;
        }
    },

    /**
    * selection - HRecordSet or array of record Ids or 'all'
    *
    * @param selection - record ids
    */
    setSelection: function(selection){
        //get ids that are in current recordset    
        this.recordSetSelected = window.hWin.HAPI4.getSelection(selection, true, this.recordSet);
        if(this.recordSetSelected==null){
            this.recordSetSelected = [];
        }
    },

    /*
    * Show/hide loading amimation
    */    
    triggerLoadAnimation: function(show){
        if(show){
            //this.div_loading.show();
            this.element.css('cursor', 'progress');
        }else{
            //this.div_loading.hide();
            this.element.css('cursor', 'auto');
           
        }
    },
    
    
    /*
    *
    */
    eventHandler: function(e, data){
        
        //accept events from the same domain and from other elements only
        if(!(data && this.options.searchDomain==data.search_realm && this._widgetId!=data.source)){
            return;
        }
        
        if(e.type == this.HAPI.Event.ON_REC_SEARCHSTART)
        {
            this.onSearchStart(data);

        }else if(e.type == this.HAPI.Event.ON_REC_SEARCH_FINISH){
            
            this.onSearchFinish(data);

        }
        else if(e.type == this.HAPI.Event.ON_REC_SELECT){
            
            this.onSelect(data);
        } 
    },
    
    onSearchStart(data){
        
            this.clearContent();

            if(data.reset || this.$H.isempty(data.q)){
                //fake restart
                this.renderMessage('initial');
            }else{
                this.triggerLoadAnimation(true);
            }
    },

    onSearchFinish(data){
        
            this.triggerLoadAnimation(false);
            
            this.setRecordSet(data.recordset);
            
            if(data.recordset==null && !this.$H.isempty(data.empty_remark)){
                //custom message
                this.renderMessage(data.empty_remark);
            }
    },

    onSelect(data){
    

            if(!data.selection || data.reset){ //clear selection
                this.setSelection(null);
            }else{
                this.setSelection(data.selection);        
            }
    },

    
});
