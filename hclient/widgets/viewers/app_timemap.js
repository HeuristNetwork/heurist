/**
* @file        app_timemap.js
* @brief       Heurist Timemap application wrapper for Leaflet mapping.
* @fileOverview This file provides the `heurist.app_timemap` jQuery UI widget.
*              It acts as a controller and wrapper for the main mapping interface
*              (map.php, which uses Leaflet and potentially the SIMILE Timemap library
*              or similar timeline components). It handles loading the map and
*              timeline into an iframe, manages record sets and selections for
*              display, and listens to system events to refresh or update the map
*              content. It supports dynamic loading of map data based on search
*              results and selections within the Heurist interface.
* @package     Heurist academic knowledge management system
* @subpackage  hclient\widgets\viewers
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

/**
 * @widget heurist.app_timemap
 * @description Manages the display of Heurist data on a map and timeline.
 * This widget embeds an iframe containing the mapping interface (map.php),
 * which utilizes Leaflet for map rendering and may include timeline components.
 * It handles data loading, event listening for record selections and searches,
 * and communication with the embedded map/timeline.
 */
$.widget( "heurist.app_timemap", {

    /**
     * @memberof heurist.app_timemap
     * @instance
     * @property {Object} options - Default options for the widget.
     * @property {?HRecordSet|Object} options.recordset - The primary record set to display on the map/timeline.
     *           Can be an HRecordSet instance or an object defining a search query.
     * @property {?Array<number>} options.selection - An array of record IDs to be highlighted or selected.
     * @property {?Array<string>} options.layout - Deprecated. Defines visible components like 'header', 'map', 'timeline'.
     *           Use `layout_params` instead.
     * @property {boolean} [options.eventbased=true] - If true, the widget listens to global Heurist events
     *           (e.g., search results, record selections) to update its display.
     * @property {boolean} [options.tabpanel=false] - If true, indicates the widget is hosted within a tab panel,
     *           adjusting its layout (e.g., top offset).
     * @property {boolean} [options.leaflet=true] - Indicates that Leaflet is the mapping library used in the iframe.
     * @property {?string} options.search_realm - A string identifier to scope event listening. Only events from
     *           the same realm will be processed.
     * @property {?string|number} options.search_initial - An initial query string or saved search ID (svs_ID)
     *           to load data when the widget is first initialized.
     * @property {boolean} [options.init_at_once=false] - If true, loads the base map and initial data immediately
     *           upon widget creation. Useful for published views.
     * @property {?Object} options.layout_params - Parameters passed to the underlying mapping.js to control
     *           its layout and available controls.
     * @property {?number} options.mapdocument - The ID of a map document record to load on initialization.
     * @property {boolean} [options.preserveViewport=false] - If true, attempts to maintain the current map
     *           viewport (zoom and center) when new data is loaded. Resets after each search.
     * @property {boolean} [options.use_cache=false] - If true, attempts to use cached data for subsequent
     *           requests after the initial data load, showing/hiding items instead of full reloads.
     * @property {?function} options.onMapInit - Callback function triggered when the embedded map and
     *           timeline interface (map.php) is fully loaded and initialized.
     * @property {?Object} options.custom_links - An object specifying custom CSS and JavaScript file URLs
     *           to be injected into the map iframe's document.
     * @property {?Object|string} options.current_search_filter - An additional filter (query object or string)
     *           to be applied to the main `recordset` before display.
     * @property {boolean} [options.init_completed=false] - Internal flag set to true when the widget and
     *           its embedded map are fully initialized.
     * @property {boolean} [options.showCurrentResults=true] - If true, a "Current query" entry is shown
     *           in the map's layer/dataset list.
     */
    options: {
        recordset: null,
        selection: null, //list of record ids
        
        layout:null, // ['header','map','timeline'] - old parameters @todo change in layout_default to layout_params
        
        eventbased:true,
        tabpanel:false,  //if true located on tabcontrol need top:30
        
        leaflet: true,
        search_realm:  null,  //accepts search/selection events from elements of the same realm only
        search_initial: null,  //query string or svs_ID for initial search

        init_at_once: false,  //load basemap at once (useful for publish to avoid empty space) 
        
        layout_params:null, //params to be passed to mapping.js
        mapdocument:null,   // map document loaded on map init
        
        //this value reset to false on every search_finish, need to set it to true explicitely before each search
        preserveViewport: false,   //zoom to current search
        //only the first request call the server - all others requests will show/hide items only
        use_cache: false,
        
        onMapInit: null,   //event triggered when map is fully loaded/inited
        
        custom_links: null,  //links to custom css and scripts to be injected into mao iframe
        current_search_filter: null,  //additional filter for current search result
        
        init_completed: false,   //flag to be set to true on full widget initializtion

        showCurrentResults: true // show 'Current query' within Result Sets
    },

    /**
     * @memberof heurist.app_timemap
     * @instance
     * @private
     * @property {?string} _events - A string concatenating Heurist event names that this widget listens to.
     * Initialized in `_create`.
     */
    _events: null,

    /**
     * @memberof heurist.app_timemap
     * @instance
     * @private
     * @property {boolean} recordset_changed - Flag indicating if the main `recordset` has changed
     * and the map display needs to be updated.
     */
    recordset_changed: true,
    
    /**
     * @memberof heurist.app_timemap
     * @instance
     * @private
     * @property {boolean} is_map_inited - Flag indicating if the embedded map (map.php) has been
     * successfully loaded and initialized.
     */
    is_map_inited: false,
    
    /**
     * @memberof heurist.app_timemap
     * @instance
     * @private
     * @property {boolean} map_curr_search_inited - Flag related to whether the current search results
     * have been processed and sent to the map.
     * @todo Clarify exact purpose and lifecycle.
     */
    map_curr_search_inited: false,
    /**
     * @memberof heurist.app_timemap
     * @instance
     * @private
     * @property {boolean} map_cache_got - Flag indicating if map data has been cached.
     * Related to `options.use_cache`.
     */
    map_cache_got: false,

    /**
     * @memberof heurist.app_timemap
     * @instance
     * @private
     * @property {number} map_resize_timer - Timer ID for debouncing map resize events.
     */
    map_resize_timer: 0,
    
    /**
     * @memberof heurist.app_timemap
     * @instance
     * @private
     * @description Widget constructor. Initializes the iframe for the map, sets up event listeners
     * if `options.eventbased` is true, and handles initial loading if `options.init_at_once` is true.
     */
    _create: function() {

        let that = this;

       

        this.framecontent = $('<div>').addClass('frame_container')
        //.css({position:'absolute', top:'2.5em', bottom:0, left:0, right:0,
        //     'background':'url('+window.hWin.HAPI4.baseURL+'assets/loading-animation-white.gif) no-repeat center center'})
        .appendTo( this.element );

        if(this.options.tabpanel){
            this.framecontent.css('top', 30);
        }else if ($(".header"+that.element.attr('id')).length===0){
            this.framecontent.css('top', 0);
        }


        this.mapframe = $( "<iframe>" )
        .attr('id', 'map-frame')
        .css('padding','0px')
        .appendTo( this.framecontent );
          
        this.loadanimation(true);
          
        if(this.options.eventbased){

            this._events = window.hWin.HAPI4.Event.ON_CREDENTIALS
            + ' ' + window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SELECT
            + ' ' + window.hWin.HAPI4.Event.ON_SYSTEM_INITED
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCHSTART;

            $(this.document).on(this._events, function(e, data) {

                if(e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS)
                {
                    if(that.options.recordset != null && !window.hWin.HAPI4.has_access()){ //logout
                        that.recordset_changed = true;
                        that.option("recordset", null);
                        that._refresh();
                    }

                }else if(e.type == window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE){
                    
                    if(that.options.leaflet && that.mapframe[0].contentWindow){
                        let mapping = that.mapframe[0].contentWindow.mapping;
                        if(mapping) {
                            if(that.map_resize_timer>0) clearTimeout(that.map_resize_timer);
                            that.map_resize_timer = setTimeout(function(){
                                that.map_resize_timer = 0;
                                mapping.mapping('invalidateSize');         
                            },400);
                            
                        }
                    }
            
                    
                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){
                    //accept events from the same realm only
                    if(!((data && data.search_realm=='mapping_recordset') || 
                        that._isSameRealm(data))) {
                         return;   
                    }

                    that.recordset_changed = true;
                    that.map_curr_search_inited = false;
                    
                    if(that.options.current_search_filter){
                        //data.recordset
                        let sub_query = window.hWin.HEURIST4.query.mergeHeuristQuery(
                                    data.recordset.getIds(2000), that.options.current_search_filter);
                                    
                        let sub_request = {q: sub_query, w: 'all', detail:'ids', id:window.hWin.HEURIST4.util.random()};
                        that.option("recordset", sub_request); 
                    }else{
                        that.option("recordset", data.recordset); //HRecordSet
                    }
                        
                    that._refresh();
                    that.loadanimation(false);
                        
                    // Search start
                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){

                    //accept events from the same realm only
                    if(!that._isSameRealm(data)) return;
                    
                    that.option("recordset", null);
                    that.option("selection", null);
                    if(data && !data.reset && data.q!='')  {
                        that.loadanimation(true);
                    }else{
                        that.recordset_changed = true;
                        that._refresh();
                    }
                   

                    // Record selection
                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){

                    //accept events from the same realm only
                    if(that._isSameRealm(data) && data.source!=that.element.attr('id')) { //selection happened somewhere else
                        if(data.reset){
                            //clear selection
                            that.option("selection",  null);
                            
                        }else if(data.map_layer_action == 'trigger_visibility'){
                            
                            let sel =  window.hWin.HAPI4.getSelection(data.selection, true);
                        
                            // data.selection is dataset id - show/hide visibility of dataset
                            // new_visiblity - true, false to show/hide entire layer or array of ids to filter out on map
                            that._setLayersVisibility( sel, data.mapdoc_id, data.new_visiblity );
                            
                        }else if(data.map_layer_action == 'download'){
                            //download layer data
                            let sel =  window.hWin.HAPI4.getSelection(data.selection, true);
                            that._downloadLayerData( sel );
                            
                        }else if(data.map_layer_action == 'zoom'){

                            let sel =  window.hWin.HAPI4.getSelection(data.selection, true);
                            that._zoomToLayer(sel);
                            
                        }else{
                            //highlight and zoom
                            that._doVisualizeSelection( window.hWin.HAPI4.getSelection(data.selection, true) );
                        }
                    }
                    
                }else if (e.type == window.hWin.HAPI4.Event.ON_SYSTEM_INITED){
                    that._refresh();

                }

            
            });
        }
       
        // init map on frame load
        this._on( this.mapframe, {
                load: function(){
                    that.loadanimation(false);
                    this.recordset_changed = true;
                    this._refresh();
                }
            }
        );

        this.element.on("myOnShowEvent", function(event){
            if( event.target.id == that.element.attr('id')){
                that._refresh();
            }
        });
        
        if(this.options.init_at_once){
            this._refresh();  
        }

    }, //end _create

    
    //
    //
    //
    /**
     * @memberof heurist.app_timemap
     * @instance
     * @private
     * @description Checks if the provided event data belongs to the same search realm as this widget.
     * @param {Object} data - The event data object, expected to have a `search_realm` property.
     * @returns {boolean} True if realms match or if realms are not defined for comparison, false otherwise.
     */
    _isSameRealm: function(data){
        return (!this.options.search_realm && (!data || window.hWin.HEURIST4.util.isempty(data.search_realm)))
        ||
        (this.options.search_realm && (data && this.options.search_realm==data.search_realm));
    },
    
    /**
     * @memberof heurist.app_timemap
     * @instance
     * @private
     * @description Refreshes the map display. If the map iframe (`mapframe`) is already loaded,
     * it calls `_initmap()`. Otherwise, it constructs the URL for map.php (the iframe source)
     * with appropriate parameters based on widget options (like database, layout, map document ID,
     * initial search query) and sets the iframe's `src` attribute to load it.
     * This function is typically called when the widget becomes visible or when `recordset_changed` is true.
     */
    _refresh: function(){

        if ( this.element.is(':visible') && this.recordset_changed) {  //to avoid reload if recordset is not changed

            if( this.mapframe.attr('src') ){  //frame already loaded
                this._initmap();
            }else {
                //need to load map.php into frame

                this.loadanimation(true);
                
                //adding url parameters to map.php from widget options
              
                let mapdoc = window.hWin.HEURIST4.util.getUrlParameter('mapdocument', window.hWin.location.search);
                if(mapdoc>0){
                    this.options.mapdocument = mapdoc;    
                }
                let url = window.hWin.HAPI4.baseURL + 'viewers/map/map.php?';

                url = url + 'db=' + window.hWin.HAPI4.database;
                
                if(this.options.layout_params){
            
                    if(this.options.layout_params.controls?.indexOf('legend') !== -1){
                        url += '&controls=legend'; // avoid destroying legend controls
                    }

                }else{
                    //init from default_layout
                    if(this.options.layout){
                        //old version
                        if( this.options.layout.indexOf('timeline')<0 )
                            url = url + '&notimeline=1';
                                                    
                        if( this.options.layout.indexOf('header')<0 )
                            url = url + '&noheader=1';
                    }
                    url = url + '&noinit=1'; // map will be inited here (for google only)
                    
                    this.options.published = 0;
                }
                
                //besides layout_params (controls and panels visibility) it passes
                // mapdocument - id of startup mapdocument
                // search_initial - initial query
                // published  - 0|1
                
                if(!window.hWin.HEURIST4.util.isempty(this.options.published)) {
                    url = url + '&published='+this.options.published; 
                } else if(this.options.layout_params && this.options.layout_params.ui_main) {
                    url = url + '&ui_main=1'; 
                }
                if(this.options.mapdocument>0){
                    url = url + '&mapdocument='+this.options.mapdocument; 
                }
                if(this.options.search_initial){
                    url = url + '&q='+encodeURIComponent(this.options.search_initial); 
                }
                url = url + '&widget='+this.element.attr('id');
                
                (this.mapframe).attr('src', url);
                
            }
        }

    },
    
    /**
     * @memberof heurist.app_timemap
     * @instance
     * @private
     * @description Reloads the map iframe. This is typically used when settings that affect
     * the iframe's URL parameters (like `layout_params`) are changed.
     * It shows a flash message, sets `recordset_changed` to true, and clears the iframe's `src`
     * to force a reload on the next `_refresh` cycle.
     */
    _reload_frame: function(){
        if(this.element.is(':visible')){
            
            window.hWin.HEURIST4.msg.showMsgFlash('Reloading map to apply new settings', 2000);
            
            this.recordset_changed = true;
            this.mapframe.attr('src', null);
        }
    },
    
    /**
     * @memberof heurist.app_timemap
     * @instance
     * @private
     * @description Initializes the map by calling `_applyCurrentSearch` if the iframe is loaded.
     * This function is called after map.php is loaded into the iframe or during a `_refresh`
     * if the iframe was already loaded.
     * @param {number} [cnt_call] - Legacy parameter, not actively used.
     */
    _initmap: function( cnt_call ){
        if( !window.hWin.HEURIST4.util.isnull(this.mapframe) && this.mapframe.length > 0){
            this._applyCurrentSearch(); 
        }

    },
    
    /**
     * @memberof heurist.app_timemap
     * @instance
     * @description Callback function executed when the embedded map (map.php) signals that it
     * has fully initialized. This function performs setup tasks that require the map
     * to be ready, such as assigning event listeners for map selections and layer status changes.
     * It also injects custom CSS/JS links if specified in `options.custom_links`.
     * Finally, it calls `_applyCurrentSearch` to load initial data.
     */
    onMapInit: function(){
        
        //execte once - assign listeners
        if(!this.is_map_inited){ 
            
            let that=this;
            
            let mapping = this.mapframe[0].contentWindow.mapping;
            
            //assign listeneres
            mapping.mapping('option', {'layout_params':this.options.layout_params});        

            mapping.mapping('option','onselect',function(selected ) {
                    $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT,
                            { selection:selected, source:that.element.attr('id'), search_realm:that.options.search_realm } );
                });
                
            mapping.mapping('option','onlayerstatus',function( layer_id, status ) {

                    if(layer_id>0)
                    $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_STATUS,
                            { selection:[layer_id], map_layer_status:status,
                              source:that.element.attr('id'), search_realm:that.options.search_realm } );

            });

            this.is_map_inited = true;
            this.options.init_completed = true;

            if(window.hWin.HEURIST4.util.isFunction(this.options.onMapInit)){
                this.options.onMapInit.call();
            }
            
            //call special method to inject custom links (css and javascript) to iframe map document
            if(that.options.custom_links){
                //custom_links - urls to be injected as css and js
                mapping.mapping('injectLinks', that.options.custom_links);
            }
        }
        
        // seach object on maps and timeline for current search
        this._applyCurrentSearch();
    },
    
    /**
     * @memberof heurist.app_timemap
     * @instance
     * @private
     * @description Applies the current search results (defined in `options.recordset`) to the map.
     * If `options.use_cache` is true and data has been cached, it may only update visibility.
     * Otherwise, it calls the `addSearchResult` method of the embedded mapping widget.
     * Sets `recordset_changed` to false after processing.
     */
    _applyCurrentSearch: function(){
    
            if(!this.is_map_inited){
                return;
            }
        
            let that=this;

            if(!that.map_curr_search_inited && that.options.recordset){

                    let mapping = this.mapframe[0].contentWindow.mapping;
                
                    that.map_curr_search_inited = true;
                    
                    if(that.map_cache_got && that.options.use_cache){
                        //do not reload current search since first request loads full dataset - just hide items that are not in current search
                        let _selection = null;
                        if(that.options.recordset=='show_all' || that.options.recordset=='hide_all'){
                            _selection = that.options.recordset;
                            that.options.recordset = null;
                        }else {
                            _selection = Array.isArray(that.options.recordset)
                                    ?that.options.recordset :that.options.recordset.getIds();
                            if(_selection.length==0) _selection = 'hide_all';
                        }
                        mapping.mapping('setVisibilityAndZoom', {mapdoc_id:0, dataset_name:'Current query'}, _selection, true);                            

                        
                    }else if(this.options.showCurrentResults){

                        //add layer to virtual mapdocument
                        mapping.mapping('addSearchResult', that.options.recordset, 
                                {name:window.hWin.HR('Current query'), viewport:that.options.preserveViewport, is_current_search:true});
                        that.options.preserveViewport = false; //restore it before each call if require
                        that.map_cache_got = true; //@todo need to check that search is really performed
                    }
            }
            this.recordset_changed = false;
    },
    
    /**
     * @memberof heurist.app_timemap
     * @instance
     * @description Updates or adds a dataset to the map.
     * @param {HRecordSet|Object} data - The record set or query object for the dataset.
     * @param {string} dataset_name - The name to assign to this dataset in the map interface.
     * @returns {boolean} True if the dataset was successfully passed to the map, false otherwise
     * (e.g., if the map is not yet initialized). If mapping is not ready but `data` contains a query,
     * it sets `options.search_initial` and triggers a refresh.
     */
    updateDataset: function(data, dataset_name){
        let mapping = null;
        if(this.mapframe[0].contentWindow){
            mapping = this.mapframe[0].contentWindow.mapping;
            if(mapping){
                mapping.mapping('addSearchResult', data, {name:dataset_name});    
                
                return true;
            }else if(data['q']){
                //mapping not defined yet - perfrom initial search
                this.options.search_initial = data['q'];
                this.recordset_changed = true;
                this._refresh();
            }
            
        }
        return false;
    },

    /**
     * @memberof heurist.app_timemap
     * @instance
     * @private
     * @description Visualizes the current selection (from `options.selection`) on the map
     * by calling `setFeatureSelection` on the embedded mapping widget.
     * This usually involves highlighting and potentially zooming to the selected features.
     * @param {Array<number>} selection - An array of record IDs to visualize.
     */
    _doVisualizeSelection: function (selection) {

        if(window.hWin.HEURIST4.util.isnull(this.options.recordset)) return;

        this.option("selection", selection);

        if(!this.element.is(':visible')
            || window.hWin.HEURIST4.util.isnull(this.mapframe) || this.mapframe.length < 1){
            return;
        }
        
        if (this.mapframe[0].contentWindow.mapping) {
            let  mapping = this.mapframe[0].contentWindow.mapping;  
            
            mapping.mapping('setFeatureSelection', this.options.selection, true);
        }
    },
    
    /**
     * @memberof heurist.app_timemap
     * @instance
     * @private
     * @description Initiates a download of map data for the selected layer(s).
     * It calls the `getMapData` method on the specific layer object within the embedded map.
     * @param {Array<number>} selection - An array of layer/record IDs for which to download data.
     *                                    Typically contains a single layer ID.
     */
    _downloadLayerData: function (selection) {

        if(window.hWin.HEURIST4.util.isnull(this.options.recordset)) return;

        if(!this.element.is(':visible')
            || window.hWin.HEURIST4.util.isnull(this.mapframe) || this.mapframe.length < 1){
            return;
        }

        if (this.mapframe[0].contentWindow.mapping) {
            let  mapping = this.mapframe[0].contentWindow.mapping;  

            //if layer is visible - select and zoom to record in search results
            let recID = selection[0];
            let layer_rec = mapping.mapping('getMapManager').getLayer( 0, recID );
            (layer_rec['layer']).getMapData();
            
        }        
    },

    /**
     * @memberof heurist.app_timemap
     * @instance
     * @private
     * @description Zooms the map to the extent of the specified layer.
     * @param {Array<number>} selection - An array containing the ID of the layer to zoom to.
     */
    _zoomToLayer: function (selection) {
        
        if (this.mapframe[0].contentWindow.mapping && selection && selection.length>0) {
            let  mapping = this.mapframe[0].contentWindow.mapping;  

            //if layer is visible - select and zoom to record in search results
            let recID = selection[0];
            let layer_rec = mapping.mapping('getMapManager').getLayer( 0, recID );
            if(layer_rec && layer_rec['layer']){
                (layer_rec['layer']).zoomToLayer();    
            }
        }        
    },

    /**
     * @memberof heurist.app_timemap
     * @instance
     * @private
     * @description Sets the visibility of specified layers or items within a map document.
     * @param {Array<number>|string} selection - An array of layer/record IDs, or a string like 'show_all'/'hide_all'.
     * @param {number} mapdoc_ID - The ID of the map document context (0 for current/default).
     * @param {boolean|Array<number>} new_visiblity - True to show, false to hide, or an array of specific
     *                                                IDs to filter by (making only those visible).
     */
    _setLayersVisibility: function (selection, mapdoc_ID, new_visiblity) {

        if(!this.element.is(':visible')
            || window.hWin.HEURIST4.util.isnull(this.mapframe) || this.mapframe.length < 1){
            return;
        }

        if (this.mapframe[0].contentWindow.mapping) {
            let  mapping = this.mapframe[0].contentWindow.mapping;  
            
            if(!(mapdoc_ID>=0)) mapdoc_ID = 0;
            let mapManager = mapping.mapping( 'getMapManager' );
            mapManager.setLayersVisibility(mapdoc_ID, selection, new_visiblity);

            //zoom to visible elements only
            this.zoomToSelection( new_visiblity );
            
        }        
    },

    /**
     * @memberof heurist.app_timemap
     * @instance
     * @private
     * @description Cleans up the widget before it's removed. Unbinds global event listeners
     * and removes the generated iframe and container elements.
     */
    _destroy: function() {

        this.element.off("myOnShowEvent");
        if(this._events)  $(this.document).off(this._events);

        // remove generated elements
        this.mapframe.remove();
        this.framecontent.remove();

    },

    /**
     * @memberof heurist.app_timemap
     * @instance
     * @description Shows or hides a loading animation overlay on the map iframe.
     * @param {boolean} show - True to show the loading animation, false to hide it.
     */
    loadanimation: function(show){
       
        if(show){
            this.mapframe.css('background','url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center');
           
        }else{
           
            this.mapframe.css('background','none');
        }
    },

    /**
    * @memberof heurist.app_timemap
    * @instance
    * @description Public method to trigger a reload of the map iframe.
    * Calls the private `_reload_frame` method.
    */
    reloadMapFrame: function(){
        this._reload_frame();    
    },

    /**
     * @memberof heurist.app_timemap
     * @instance
     * @description Public method to zoom the map to a given selection.
     * @param {Array<number>|Object} selection - The selection to zoom to (e.g., array of record IDs,
     *                                           or an object defining bounds/features).
     * @param {Object} [fly_params] - Optional parameters for "fly to" animation, if supported by the map.
     */
    zoomToSelection:function(selection, fly_params){
        let mapping = this.mapframe[0].contentWindow.mapping;
        if(mapping){
            mapping.mapping('zoomToSelection', selection, fly_params );
        }
    },

    /**
     * @memberof heurist.app_timemap
     * @instance
     * @description Public method to get a reference to the underlying mapping widget instance
     * from the iframe.
     * @returns {?Object} The mapping widget instance, or null if not available.
     */
    getMapping: function(){
        if(this.mapframe[0].contentWindow){
            let map = this.mapframe[0].contentWindow.mapping;
            return map.mapping('instance');
        }else{
            return null;
        }
    },
    
    /**
     * @memberof heurist.app_timemap
     * @instance
     * @description Public method to check if the embedded map is fully initialized.
     * @returns {boolean} True if the map is initialized, false otherwise.
     */
    isMapInited: function(){
        return this.is_map_inited;
    }

});
