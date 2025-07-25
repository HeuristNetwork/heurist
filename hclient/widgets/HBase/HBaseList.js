/**
 * @file HBaseList.js
 * @brief template widget to represent set of record
 * @fileOverview
 * @project     Heurist academic knowledge management system
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @since       7.0
 */
import './HBaseWidget.js';

/**
 * @class HBaseList
 * @augments {HBaseWidget}
 * @memberof Widgets.UI
 * @description template widget to represent set of record
 * @param {object} options - Configuration options for the widget.
 */
$.widget( 'heurist.HBaseList', $.heurist.HBaseWidget, {

    /**
     * @memberof Widgets.UI.HBaseList
     * @type {object}
     * @property {string} entityType - The type of entity.
     * @property {string} searchDomain - The search domain.
     * @property {string} searchInitial - The initial search query.
     * @property {HRecordSet} recordSet - The initial recordset.
     * @property {boolean} placeholderInitBlank - Whether the initial placeholder is blank.
     * @property {string} placeholderInit - The initial placeholder.
     * @property {string} placeholderInitDef - The default initial placeholder.
     */
    options: {
        entityType: 'rec', //'rec' by default

        searchDomain: null,     // unique id to distiguish search/select events 
        searchInitial: null,    // initial search query
        
        recordSet:null,         // initial recordset
        
        placeholderInitBlank: false,
        placeholderInit: null,
        placeholderInitDef: 'Initial set is empty',
    },
    
    _needLoadContent: false,
    _needLoadCss: false,
    
    recordSet:null,   // HRecordSet
    recordSetSelected:[], // array of selected ids
    recordSetSubset:null, // HRecordSet - filtered and sorted locally
    
    _events:null,
    
    /**
     * @private
     * @memberof Widgets.UI.HBaseList
     * @description Initializes the widget.
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
    
    /**
     * @private
     * @memberof Widgets.UI.HBaseList
     * @description Destroys the widget.
     */
    _destroy: function(){
        
        this._super();
        
        if(this._events){
            $(this.document).off(this._events);
        }
    },
    
    /**
     * @private
     * @memberof Widgets.UI.HBaseList
     * @description Use it a) to add event listeners for subelements of this widget
     * b) perform some default actions (intial search for example)
     */
    _initControls:function(){
    
        this._super();
        
        if(this.options.recordSet){
            this.setRecordSet(this.options.recordSet);
        }else if(this.options.searchInitial)
        {
            this.doSearch(this.options.searchInitial);
        }else if(!this.options.placeholderInitBlank){
            this.renderMessage(this.options.placeholderInit || this.options.placeholderInitDef);
        }
    },

    /**
     * @memberof Widgets.UI.HBaseList
     * @description Removes generated elements
     */
    clearContent: function(){
        this.element.innerHTML = '';
        this.setSelection(null); //clear selection
    },

    /**
     * @memberof Widgets.UI.HBaseList
     * @description Sets new recordset to this record list
     * @param {HRecordSet} recordset - The new recordset.
     */
    setRecordSet: function( recordset ){

        if(!this._initCompleted) return;

        this.clearContent();
        this.recordSet = recordset;
        
        this.renderConent();
    },
    
    /**
     * @memberof Widgets.UI.HBaseList
     * @description Search for initial search or on search domain event
     * @param {string} query - The search query.
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

    /**
     * @private
     * @memberof Widgets.UI.HBaseList
     * @description Response handler for search request
     * @param {string} query - The search query.
     * @param {object} response - The response from the server.
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
    
    /**
     * @memberof Widgets.UI.HBaseList
     * @description Adds notification/placeholder message (init, error or for empty result)
     * @param {string} msg - The message to display.
     */
    renderMessage: function(msg){
    
        this.clearContent();
        
        let $emptyres = $('<div>')
        .css('merge','auto')
        .html(msg)
        .appendTo(this.element);
        
    },
    
    /**
     * @memberof Widgets.UI.HBaseList
     * @description Renders the content.
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
    * @memberof Widgets.UI.HBaseList
    * @description return HRecordSet or array of ids of selected records
    * @param {boolean} idsonly - If true, returns only the IDs of the selected records.
    * @returns {HRecordSet|Array|null} The selected records.
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
    * @memberof Widgets.UI.HBaseList
    * @description selection - HRecordSet or array of record Ids or 'all'
    *
    * @param {HRecordSet|Array|string} selection - record ids
    */
    setSelection: function(selection){
        //get ids that are in current recordset    
        this.recordSetSelected = window.hWin.HAPI4.getSelection(selection, true, this.recordSet);
        if(this.recordSetSelected==null){
            this.recordSetSelected = [];
        }
    },

    /**
     * @memberof Widgets.UI.HBaseList
     * @description Show/hide loading amimation
     * @param {boolean} show - If true, shows the loading animation.
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
    
    
    /**
     * @private
     * @memberof Widgets.UI.HBaseList
     * @description Handles events.
     * @param {Event} e - The event object.
     * @param {object} data - The event data.
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
    
    /**
     * @memberof Widgets.UI.HBaseList
     * @description Handles the start of a search.
     * @param {object} data - The event data.
     */
    onSearchStart(data){
        
            this.clearContent();

            if(data.reset || this.$H.isempty(data.q)){
                //fake restart
                this.renderMessage('initial');
            }else{
                this.triggerLoadAnimation(true);
            }
    },

    /**
     * @memberof Widgets.UI.HBaseList
     * @description Handles the end of a search.
     * @param {object} data - The event data.
     */
    onSearchFinish(data){
        
            this.triggerLoadAnimation(false);
            
            this.setRecordSet(data.recordset);
            
            if(data.recordset==null && !this.$H.isempty(data.empty_remark)){
                //custom message
                this.renderMessage(data.empty_remark);
            }
    },

    /**
     * @memberof Widgets.UI.HBaseList
     * @description Handles the selection of a record.
     * @param {object} data - The event data.
     */
    onSelect(data){
    

            if(!data.selection || data.reset){ //clear selection
                this.setSelection(null);
            }else{
                this.setSelection(data.selection);        
            }
    },

    
});
