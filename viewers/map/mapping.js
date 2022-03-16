/**

@todo Later?
KML is loaded as a single collection (without access to particular feature) Need to use our kml parser to parse and load each kml' placemark individually
Altough our kml parser supports Placemarks only. It means no external links, image overlays and other complex kml features. 
Selector map tool (rectangle and polygon/lasso)
Editor map tool (to replace mapDraw.js based on gmap)
Thematic mapping
 
* options for preferences
* markercluster on/off 
*     showCoverageOnHover, zoomToBoundsOnClick, maxClusterRadius(80px)
* Derive map location of non-geolocalised entities from connected Places
* Map select tools (by click, in rect, in shape)
* Default symbology (for search result)
* Default base layer
* 
* mapping base widget
*
* It manipulates with map component (google or leaflet is implemented in descendants)
*      all native mathods for mapping must be defined here
* 
* mapManager.js is UI with list (tree) of mapdocuments, result sets and base maps
* timeline.js for Vis timeline 
* mapDocument.js maintains map document list and their layers
* mapLayer2.js loads data for particular layer
* 
* 
* options:
* element_layout
* element_map    #map by default
* element_timeline  #timeline by default 
* 
* notimeline - hide timeline (map only)
* nomap - timeline only
* map_rollover - show title of market as tooltip
* map_popup_mode - show map info as standard map control, in popup dialog or supress (standard,dialog,none)
* 
* callback events:
* onselect
* onlayerstatus - arguments datasetid and status (visible,hidden )see mapManager treeview select event
* oninit
* style: default style for current query
* 
* 
* init (former _load)
* printMap
* 
* 
*     
*   openMapDocument - opens given map document
*   loadBaseMap
    addSearchResult - loads geojson data based on heurist query, recordset or json to current search (see addGeoJson)
    addRecordSet - converts recordset to geojson
    addLayerRecords - add layer records to search result mapdocument
    addGeoJson - adds geojson layer to map, apply style and trigger timeline update
    addTileLayer - adds image tile layer to map
    addImageOverlay - adds image overlay to map
    updateTimelineData - add/replace timeline layer_data in this.timeline_items and triggers timelineRefresh
    applyStyle
    getStyle
    
    setFeatureSelection - triggers redraw for path and polygones (assigns styler function)  and creates highlight circles for markers
    setFeatureVisibility - applies visibility for given set of heurist recIds (filter from timeline)
    zoomToSelection
    setLayerVisibility - show hide entire layer
    setVisibilityAndZoom - show susbset n given recordset and zoom 
    
    _onLayerClick - map layer (shape) on click event handler - highlight selection on timeline and map, opens popup
    _clearHighlightedMarkers - removes special "highlight" selection circle markers from map
    setStyleDefaultValues - assigns default values for style (size,color and marker type)
    _createMarkerIcon - creates marker icon for url(image) and fonticon (divicon)
    _stylerFunction - returns style for every path and polygone, either individual feature style of parent layer style.
    _getMarkersByRecordID - returns markers by heurist ids (for filter and highlight)
    
* Events: 
* onInitComplete - triggers options.oninit event handler
* 
* 
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
$.widget( "heurist.mapping", {

    // default options
    options: {
        
        element_layout: null,
        element_map: 'map',
        element_timeline: 'timeline',
 
        //various layout and behaviour settings
        // they are assigned in map_leaflet.php onPageInit from url parameters
        // which in turn can be set in app_timemap.js
        layout_params:{}, 
        
        // callbacks
        onselect: null,
        onlayerstatus:null,
        oninit: null,
        ondraw_addstart:null,
        ondraw_editstart:null,
        ondrawend:null,
        
        isEditAllowed: true,
        isPublished: false,
        
        map_rollover: false,
        map_popup_mode: 'standard',
        
        zoomToPointInKM: 2
    },
    
    /* expremental 
    isHamburgIslamicImpire: false,
    hie_places_with_events: [],
    hie_places_wo_events_style: null,
    */
    
    //reference to google or leaflet map
    
    //main elements
    nativemap: null,     //map container
    vistimeline: null,   //timeline container 
    mapManager: null,    //legend
    
    //record from search results mapdoc
    current_query_layer:null, 
    
    //controls
    map_legend: null,
    map_zoom: null,
    map_bookmark: null,
    map_geocoder: null,
    map_print: null,
    map_publish: null,
    map_draw: null,
    map_help: null,
    map_scale: null,
    
    //popup element
    main_popup: null,
    mapPopUpTemplate: null,
    
    currentDrawMode:'none',  //full,image,filter 
    
    //
    drawnItems: null,
    //default draw style
    map_draw_style: {color: '#3388ff',
             weight: 4,
             opacity: 0.5,
             fill: true, //fill: false for polyline
             fillColor: null, //same as color by default
             fillOpacity: 0.2},
    

    //storages
    all_layers: {},    // array of all loaded TOP layers by leaflet id   
    all_clusters: {},  // markerclusters
    all_markers: {},
    
    timeline_items: {},
    timeline_groups: [], 
    
    nomap: false,
    notimeline: false,
    //current status
    is_timeline_disabled:0,
    is_map_disabled:0, 
    timeline_height: 0,
    
    selected_rec_ids:[],

    myIconRectypes:{},  //storage for rectype icons by color and rectype id

    //  settings
    isMarkerClusterEnabled: true,
    markerClusterGridSize: 50,
    markerClusterMaxZoom: 18,
    markerClusterMaxSpider: 5,
    isEditAllowed: true,
    
    
    //base maps
    basemaplayer_filter: null,
    basemaplayer_name: null,
    basemaplayer: null,
    basemap_providers: [
    {name:'OpenStreetMap'},
    //{name:'OpenPtMap'},
    {name:'OpenTopoMap'},
    {name:'MapBox.StreetMap', options:{accessToken: 'pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ.rJcFIG214AriISLbB6B5aw'}}, //{name:'MapBox.StreetMap', options:{accessToken: 'pk.eyJ1Ijoib3NtYWtvdiIsImEiOiJja3dvaG80ZTYwMzA4Mm9vNGtxZzF2NnB2In0.mmkf1m5-Lr2RgUblOeVtmQ'}},
    {name:'MapBox.Satellite', options:{accessToken: 'pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ.rJcFIG214AriISLbB6B5aw'}},
    {name:'MapBox.Combined', options:{accessToken: 'pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ.rJcFIG214AriISLbB6B5aw'}},
    {name:'MapBox.Relief', options:{accessToken: 'pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ.rJcFIG214AriISLbB6B5aw'}},
    //{name:'MapBox.AncientWorld', options:{accessToken: 'pk.eyJ1Ijoib3NtYWtvdiIsImEiOiJjanV2MWI0Y3Awb3NmM3lxaHI2NWNyYjM0In0.st2ucaGF132oehhrpHfYOw'}},
    {name:'Esri.WorldStreetMap'},
    {name:'Esri.WorldTopoMap'},
    {name:'Esri.WorldImagery'},
    //{name:'Esri.WorldTerrain'},
    {name:'Esri.WorldShadedRelief'},
    {name:'Stamen.Toner'},
    {name:'Stamen.TonerLite'},
    {name:'Stamen.TerrainBackground'}, //terrain w/o labels
    //{name:'Stamen.TopOSMRelief'},    // doesn't work
    //{name:'Stamen.TopOSMFeatures'},  // doesn't work
    //{name:'Stamen.Terrain'},  terrain with labels
    {name:'Stamen.Watercolor'},
    //{name:'OpenWeatherMap'}
    {name:'Esri.NatGeoWorldMap'},
    {name:'Esri.WorldGrayCanvas'},
    {name:'None'}
    ],

    available_maxzooms: [],

    // ---------------    
    // the widget's constructor
    //
    _create: function() {

        var that = this;

        this.element
        // prevent double click to select text
        .disableSelection();

        // Sets up element to apply the ui-state-focus class on focus.
        //this._focusable($element);   

        this._refresh();

    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
    
        var that = this;    
        //1. INIT LAYOUT
        
        // Layout options
        var layout_opts =  {
            applyDefaultStyles: true,
            togglerContent_open:    '<div class="ui-icon"></div>',
            togglerContent_closed:  '<div class="ui-icon"></div>',
            onresize_end: function(){
                //global 
                //if(mapping) mapping.onWinResize();
                that._adjustLegendHeight();
            }
            
        };
        
        is_ui_main = this.options.layout_params && this.options.layout_params['ui_main'];

        // Setting layout
        if(this.options.element_layout)
        {
            layout_opts.center__minHeight = 0;
            layout_opts.center__minWidth = 200;
            layout_opts.north__size = 0;//30;
            layout_opts.north__spacing_open = 0;
            /*
            var th = Math.floor($(this.options.element_layout).height*0.2);
            layout_opts.south__size = th>200?200:th;
            layout_opts.south__spacing_open = 7;
            layout_opts.south__spacing_closed = 12;
            */
            layout_opts.south__onresize_end = function() {
                //if(mapping) mapping.setTimelineMinheight();
                that._adjustLegendHeight();
            };
            
            if(is_ui_main){
                
                window.hWin.HAPI4.LayoutMgr.cardinalPanel('sizePane',
                                    ['east', (top ?  '75%' : window.innerWidth)]);
                
                layout_opts.north__size = 36;
            }

            this.mylayout = $(this.options.element_layout).layout(layout_opts);
        }

        
        //2. INIT MAP
        map_element_id = 'map';
        if(this.options.element_map && this.options.element_map.indexOf('#')==0){
            map_element_id = this.options.element_map.substr(1);
        }
        
        $('#'+map_element_id).css('padding',0); //reset padding otherwise layout set it to 10px
        
        this.nativemap = L.map( map_element_id, {zoomControl:false, tb_del:true} )
            .on('load', function(){ } );

        //init basemap layer        
/*        
        L.tileLayer('https://api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token={accessToken}', {
            attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, <a href="https://    creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
            maxZoom: 18,
            id: 'mapbox.streets',
            accessToken: 'pk.eyJ1Ijoib3NtYWtvdiIsImEiOiJjanV2MWI0Y3Awb3NmM3lxaHI2NWNyYjM0In0.st2ucaGF132oehhrpHfYOw'
        }).addTo( this.nativemap );       
*/

/*
        L.tileLayer('http://127.0.0.1/HEURIST_FILESTORE/osmak_5/{id}/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, <a href="https://    creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>',
            maxZoom: 15,
            id: 'LondonAtlas'
        }).addTo( this.nativemap );       
*/        
        //3. INIT TIMELINE 
        //moved to updateLayout

        //LONDON this.nativemap.setView([51.505, -0.09], 13); //@todo change to bookmarks
        this.nativemap.setView([20, 20], 1); //@todo change to bookmarks
        
        //4. INIT CONTROLS
        if(this.main_popup==null) this.main_popup = L.popup({maxWidth: 'auto'});
        
        //zoom plugin
        this.map_zoom = L.control.zoom({ position: 'topleft' });//.addTo( this.nativemap );
        
        //legend contains mapManager
        this.map_legend = L.control.manager({ position: 'topright' }).addTo( this.nativemap );

        //map scale
        this.map_scale = L.control.scale({ position: 'bottomleft' }).addTo( this.nativemap );
        $(this.map_scale._container).css({'margin-left': '20px', 'margin-bottom': '20px'});

        //content for legend
        this.mapManager = new hMapManager({container:this.map_legend._container, mapwidget:this.element, is_ui_main:is_ui_main});
        
        this.updateLayout();
        
        this._adjustLegendHeight();
        
    },

    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    _setOptions: function( ) {
        
        this._superApply( arguments );
        
        if((arguments[0] && arguments[0]['layout_params']) || arguments['layout_params'] ){
            this.updateLayout();
        }
    },

    /* 
    * private function 
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

    },
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements
        //this.select_rectype.remove();
    },
    
    //-------
    _adjustLegendHeight: function(){
        var ele = $('#'+map_element_id);
        if(this.mapManager) this.mapManager.setHeight(ele.height()-50); //adjust legend height    
    
    
    //invalidateSize
    },
    
    invalidateSize: function(){
        if(this.nativemap) this.nativemap.invalidateSize();
    },
    
    getLeaflet: function(){
        return L;
    },  
    
    //that.onInitComplete();
    //
    // triggers options.oninit event handler
    //    it is invoked on completion of hMapManager initialization - map is inited and all mapdocuments are loaded
    //
    onInitComplete:function(){
//        console.log('onInitComplete');
        
        if($.isFunction(this.options.oninit)){
                this.options.oninit.call(this, this.element);
        }
        
    },
    
    

    //---------------- 
    //
    // returns mapManager object
    //
    getMapManager: function(){
        return this.mapManager;
    },
    
    //--------------------
    
    //
    // css filter for base map
    // 
    setBaseMapFilter: function(cfg){
        if(typeof cfg === 'string'){
            this.basemaplayer_filter = window.hWin.HEURIST4.util.isJSON(cfg);
        }else{
            this.basemaplayer_filter = cfg;    
        }
        if(!this.basemaplayer_filter){
            this.basemaplayer_filter = null;
        }
        
        this.applyBaseMapFilter();
    },
    
    applyBaseMapFilter: function(){
        var filter = '';
        if(this.basemaplayer_filter && $.isPlainObject(this.basemaplayer_filter)){
            $.each(this.basemaplayer_filter, function(key, val){
                filter = filter + key+'('+val+') ';
            });
        }
        $('.leaflet-layer').css('filter', filter);
    },

    getBaseMapFilter: function(){
        return this.basemaplayer_filter;
    },
    
    //
    //
    //
    getBaseMapProviders: function(basemap_id){
        return this.basemap_providers;
    },
    
    //
    // basemap_id index in mapprovider array or provider name
    //
    loadBaseMap: function(basemap_id){
        
        var provider = this.basemap_providers[0];
        if(window.hWin.HEURIST4.util.isNumber(basemap_id) && basemap_id>=0){
            provider = this.basemap_providers[basemap_id];
        }else{
            $(this.basemap_providers).each(function(idx, item){
                if(item['name']==basemap_id){
                    provider = item;
                    return;        
                }
            });
        }
        
        if(this.basemaplayer_name!=provider['name']) {
            
            this.basemaplayer_name = provider['name'];
            
            if(this.basemaplayer!=null){
                this.basemaplayer.remove();
            }

            if(provider['name']!=='None'){
                this.basemaplayer = L.tileLayer.provider(provider['name'], provider['options'] || {})
                    .addTo(this.nativemap);        

                if(this.basemaplayer_filter){
                    this.applyBaseMapFilter();
                }

                var cur_maxZoom = this.nativemap.getMaxZoom();
                var layer_maxZoom = (provider['options'] && provider['options']['maxZoom']) ? provider['options']['maxZoom'] : 18;

                if(cur_maxZoom < layer_maxZoom){
                    this.nativemap.setMaxZoom(layer_maxZoom);

                    var idx = this.available_maxzooms.findIndex(arr => arr[0] == 'basemap');
                    if(idx != -1){ // update max zoom value
                        this.available_maxzooms[idx] = ['basemap', layer_maxZoom];
                    }else{ // add max zoom value
                        this.available_maxzooms.push(['basemap', layer_maxZoom]);
                        this.available_maxzooms.sort((a, b) => b[1] - a[1]);
                    }
                }
            }            
            
        }   
    },
    
    //
    // Adds layer to searchResults mapdoc
    // data - recordset, heurist query or json
    // this method is invoked on global onserachfinish event in app_timemap
    //
    addSearchResult: function(data, dataset_name, preserveViewport ) {
        this.current_query_layer = this.mapManager.addSearchResult( data, dataset_name, preserveViewport );
    },

    //
    // Adds layer to searchResults mapdoc
    // recset - recordset to be converted to geojson
    // it is used in Digital Harlem and Expert Nation where recordset is generated and prepared in custom way on client side
    //
    addRecordSet: function(recset, dataset_name) {
        //it is not publish recordset since it is prepared localy 
        this.current_query_layer = null;
        this.mapManager.addRecordSet( recset, dataset_name );
    },
    
    //
    // adds image tile layer to map
    //
    addTileLayer: function(layer_url, layer_options, dataset_name){
    
        var new_layer;

        var HeuristTilerLayer = L.TileLayer.extend({
                        getBounds: function(){
                            return this.options._extent;  
                        }});
        
        if(layer_options['IIIF']){
        
                HeuristTilerLayer = L.TileLayer.Iiif.extend({
                        getBounds: function(){
                            return this.options._extent;  
                        }});
                
                layer_options['fitBounds'] = false;
                
                new_layer = new HeuristTilerLayer(layer_url, layer_options).addTo(this.nativemap);                
                //new L.tileLayer.iiif
        
        }else
        if(layer_options['BingLayer'])
        {
                var BingLayer = HeuristTilerLayer.extend({
                    getTileUrl: function (tilePoint) {
                        //this._adjustTilePoint(tilePoint);
                        return L.Util.template(this._url, {
                            s: this._getSubdomain(tilePoint),
                            q: this._quadKey(tilePoint.x, tilePoint.y, this._getZoomForUrl())
                        });
                    },
                    _quadKey: function (x, y, z) {
                        var quadKey = [];
                        for (var i = z; i > 0; i--) {
                            var digit = '0';
                            var mask = 1 << (i - 1);
                            if ((x & mask) != 0) {
                                digit++;
                            }
                            if ((y & mask) != 0) {
                                digit++;
                                digit++;
                            }
                            quadKey.push(digit);
                        }
                        return quadKey.join('');
                    }
                });    
                
                if(!layer_options.subdomains) layer_options.subdomains = ['0', '1', '2', '3', '4', '5', '6', '7'];
                
                if(!layer_options.attribution) layer_options.attribution = '&copy; <a href="http://bing.com/maps">Bing Maps</a>';
                
                new_layer = new BingLayer(layer_url, layer_options).addTo(this.nativemap);  
                /*{
                   detectRetina: true
                }*/
                           
        }else if(layer_options['MapTiler'] && layer_url.indexOf('{q}')>0)
        {
                var MapTilerLayer = HeuristTilerLayer.extend({
                    getTileUrl: function (tilePoint) {
                        //this._adjustTilePoint(tilePoint);
                        return L.Util.template(this._url, {
                            s: this._getSubdomain(tilePoint),
                            q: this._maptiler(tilePoint.x, tilePoint.y, this._getZoomForUrl())
                        });
                    },
                    _maptiler: function (x, y, z) {
                        
                        var bound = Math.pow(2, z);
                        var s = ''+z+'/'+x+'/'+(bound - y - 1); 
                     
                        return s;
                    }
                });    
                
            new_layer = new MapTilerLayer(layer_url, layer_options).addTo(this.nativemap);  

            /*            
              layer_url = 'http://127.0.0.1/h5-ao/external/php/tileserver.php?/index.json?/c:/xampp/htdocs/HEURIST_FILESTORE/tileserver/mapa/{z}/{x}/{y}.png';
              new_layer = new HeuristTilerLayer(layer_url,
               layer_options).addTo(this.nativemap);         
            */
                
        }else{
            
            //transparency for jpeg
            if(layer_options['MapTiler'] && layer_options['extension']=='.jpg'){
                layer_options['matchRGBA'] = [ 0,  0,  0, 0  ]; //replace that match
                layer_options['missRGBA'] =  null; //replace that not match
                layer_options['pixelCodes'] = [ [255, 255, 255] ]; //search for
                layer_options['getBounds'] = function(){
                            return this.options._extent;  
                        };
                        
                new_layer = new L.tileLayerPixelFilter(layer_url, layer_options).addTo(this.nativemap);
                
            }else{
                new_layer = new HeuristTilerLayer(layer_url, layer_options).addTo(this.nativemap);             
            }
        }
        
        this.all_layers[new_layer._leaflet_id] = new_layer;
        
        this._updatePanels();
        
        if(layout_opts && layout_opts['maxZoom']){

            var idx = this.available_maxzooms.findIndex(arr => arr[0] == new_layer._leaflet_id);
            if(idx != -1){
                this.available_maxzooms[idx] = [new_layer._leaflet_id, layout_opts['maxZoom']];
            }else{
                this.available_maxzooms.push([new_layer._leaflet_id, layout_opts['maxZoom']]);
                this.available_maxzooms.sort((a, b) => b[1] - a[1]);
            }

            if(this.nativemap.getMaxZoom() < layout_opts['maxZoom']){
                this.nativemap.setMaxZoom(layout_opts['maxZoom']);
            }
        }

        return new_layer._leaflet_id;
    },
    
    //
    // adds image overlay to map
    //
    addImageOverlay: function(image_url, image_extent, dataset_name){
    
        var new_layer = L.imageOverlay(image_url, image_extent).addTo(this.nativemap);
      
        this.all_layers[new_layer._leaflet_id] = new_layer;
        
        this._updatePanels();
        
        return new_layer._leaflet_id;
    },
    
    //
    // adds geojson layer to map
    // returns nativemap id
    // options:
    //      geojson_data
    //      timeline_data
    //      dataset_name
    //      preserveViewport
    //      layer_style
    //      popup_template   - smarty template for popup info
    //      origination_db
    //
    addGeoJson: function(options){
            
            var geojson_data = options.geojson_data,
                timeline_data = options.timeline_data,
                layer_style = options.layer_style,
                popup_template = options.popup_template,
                dataset_name = options.dataset_name,
                preserveViewport = options.preserveViewport;

        if (window.hWin.HEURIST4.util.isGeoJSON(geojson_data, true) || 
            window.hWin.HEURIST4.util.isArrayNotEmpty(timeline_data)){
                
            var that = this;
            
            var new_layer = L.geoJSON(geojson_data, {
                    default_style: null
                    , layer_name: dataset_name
                    , popup_template: popup_template
                    , origination_db: options.origination_db
                    //The onEachFeature option is a function that gets called on each feature before adding it to a GeoJSON layer. A common reason to use this option is to attach a popup to features when they are clicked.
                   /* 
                    , onEachFeature: function(feature, layer) {

                        //each feature may have its own feature.style
                        //if it is not defined then layer's default_style will be used
                        feature.default_style = layer.options.default_style;

                        layer.on('click', function (event) {
                            that.vistimeline.timeline('setSelection', [feature.properties.rec_ID]);

                            that.setFeatureSelection([feature.properties.rec_ID]);
                            if($.isFunction(that.options.onselect)){
                                that.options.onselect.call(that, [feature.properties.rec_ID]);
                            }
                            //open popup
                            var popupURL = window.hWin.HAPI4.baseURL + 'viewers/record/renderRecordData.php?mapPopup=1&recID='
                            +feature.properties.rec_ID+'&db='+window.hWin.HAPI4.database;

                            $.get(popupURL, function(responseTxt, statusTxt, xhr){
                                if(statusTxt == "success"){
                                    that.main_popup.setLatLng(event.latlng)
                                    .setContent(responseTxt) //'<div style="width:99%;">'+responseTxt+'</div>')
                                    .openOn(that.nativemap);
                                }
                            });

                            //that._trigger('onselect', null, [feature.properties.rec_ID]);

                        });
                    }*/

                    /* , style: function(feature) {
                    if(that.selected_rec_ids.indexOf( feature.properties.rec_ID )>=0){
                    return {color: "#ff0000"};
                    }else{
                    //either specific style from json or common layer style
                    return feature.style || feature.default_style;
                    }
                    }
                    */
                    /*
                    pointToLayer: function (feature, latlng) {
                    return L.circleMarker(latlng, geojsonMarkerOptions);
                    },                
                    //The filter option can be used to control the visibility of GeoJSON features
                    filter: function(feature, layer) {
                    return feature.properties.show_on_map;
                    }                
                    */
                })
                /*.bindPopup(function (layer) {
                return layer.feature.properties.rec_Title;
                })*/
                .addTo( this.nativemap );            

             if(that.options.map_rollover){
                new_layer.bindTooltip(function (layer) {
                    if(layer.feature && layer.feature.properties){
                        return layer.feature.properties.rec_Title;
                    }
                })
             }                


            /* not implemented - idea was store template in mapdocument and excute is on _onLayerClick    
            if(popup_template){
                new_layer.eachLayer( function(child_layer){ 
                        child_layer.feature.popup_template = popup_template;
                });
            } 
            */   
            
            this.all_layers[new_layer._leaflet_id] = new_layer;
            
                
            if(!this.notimeline){
                this.updateTimelineData(new_layer._leaflet_id, timeline_data, dataset_name);
            }
            
            this._updatePanels();

            //apply layer ot default style and fill markercluster
            this.applyStyle( new_layer._leaflet_id, layer_style ?layer_style: this.setStyleDefaultValues() ); //{color: "#00b0f0"}

            
            if(!preserveViewport){
                this.zoomToLayer(new_layer._leaflet_id);           
            }
            
            return new_layer._leaflet_id;
        }        

        else{
            return 0;
        }
        
    },

    //
    //
    //
    updateTimelineLayerName: function(layer_id, new_dataset_name){

        if(this.notimeline) return;
        
        var group_idx = this.getTimelineGroup(layer_id);
        if(group_idx>=0){
            this.timeline_groups[group_idx].content = new_dataset_name;
            
            this.vistimeline.timeline('timelineUpdateGroupLabel', this.timeline_groups[group_idx]);
        }
        
    },

    
    //
    // add/replace layer time data to timeline_groups/timeline_items
    //  layer_id - leaflet id
    //  layer_data - timeline data from server
    //
    updateTimelineData: function(layer_id, layer_data, dataset_name){
        
            if(this.notimeline) return;
      
            var titem, k, ts, iconImg;

            if(window.hWin.HEURIST4.util.isArrayNotEmpty(layer_data) ){

                var group_idx = this.getTimelineGroup(layer_id);
                if(group_idx<0){
                    this.timeline_groups.push({ id:layer_id, content:dataset_name });        
                    group_idx = this.timeline_groups.length-1;
                }else{
                    this.timeline_groups[group_idx].content = dataset_name;
                }
                /*if(dataset_name=='Current query' && group_idx>0){ //swap
                    var swap = this.timeline_groups[0];
                    this.timeline_groups[0] = this.timeline_groups[group_idx];
                    this.timeline_groups[group_idx] = swap;
                }*/
                
                this.timeline_items[layer_id] = []; //remove/rest previous data
                
                var that = this;

                $.each(layer_data, function(idx, tdata){

                    iconImg = window.hWin.HAPI4.iconBaseURL + tdata.rec_RecTypeID;

                    ts = tdata.when;

                    for(k=0; k<tdata.when.length; k++){
                        ts = tdata.when[k];
                        
                        titem = {
                            id: layer_id+'-'+tdata.rec_ID+'-'+k, //unique id
                            group: layer_id,
                            content: '<img src="'+iconImg 
                            + '"  align="absmiddle" style="padding-right:3px;" width="12" height="12"/>&nbsp;<span>'
                            + tdata.rec_Title+'</span>',
                            //'<span>'+recName+'</span>',
                            title: tdata.rec_Title,
                            start: ts[0],
                            recID: tdata.rec_ID
                        };

                        if(ts[3] && ts[0]!=ts[3]){
                            titem['end'] = ts[3];
                        }else{
                            titem['type'] = 'point';
                            //titem['title'] = singleFieldName+': '+ dres[0] + '. ' + titem['title'];
                        }

                        that.timeline_items[layer_id].push(titem); 
                    }//for timespans

                });
                
            }else if(!this.removeTimelineGroup(layer_id)){
                // no timeline data - remove entries from timeline_groups and items
                // if no entries no need to redraw
                return;
            }
                
            this.vistimeline.timeline('timelineRefresh', this.timeline_items, this.timeline_groups);          
            
            //this._updatePanels();
    },
    
    //
    //
    //
    getTimelineGroup:function(layer_id){
        
        for (var k=0; k<this.timeline_groups.length; k++){
            if(this.timeline_groups[k].id==layer_id){
                return k;
            }
        }
        return -1;
    },
    
    //
    //
    //
    removeTimelineGroup:function(layer_id){
        var idx = this.getTimelineGroup(layer_id);
        if(idx>=0){
            this.timeline_groups.splice(idx,1);
            this.timeline_items[layer_id] = null;
            delete this.timeline_items[layer_id];
            return true;
        }else{
            return false;
        }
    },
    
    //
    //
    //
    _mergeBounds: function(bounds){

        var res = null;

        for(var i=0; i<bounds.length; i++){
            if(bounds[i]){

                if(!(bounds[i] instanceof L.LatLngBounds)){
                    if($.isArray(bounds[i]) && bounds[i].length>1 ){
                        bounds[i] = L.latLngBounds(bounds[i]);
                    }else{
                        continue;
                    }
                }

                if( bounds[i].isValid() ){
                    if(res==null){
                        res = L.latLngBounds(bounds[i].getSouthWest(), bounds[i].getNorthEast() );
                    }else{
                        res.extend(bounds[i]);
                        //bounds[i].getNorthWest());
                        //res.extend(bounds[i].getSouthEast());
                    } 
                }
            }
        }

        return res;

    },

    //
    // get summary bounds for set of TOP layers
    //
    getBounds: function(layer_ids){
        
        if(!$.isArray(layer_ids)){
            layer_ids = [layer_ids];
        }
        
        var bounds = [];
        
        for(var i=0; i<layer_ids.length; i++){
            
            var layer_id = layer_ids[i];
            
            var affected_layer = this.all_layers[layer_id];
            if(affected_layer){
                var bnd = affected_layer.getBounds()
                bounds.push( bnd );
                
                if(window.hWin.HEURIST4.util.isArrayNotEmpty( this.all_markers[layer_id] ) 
                        && this.all_clusters[layer_id])
                {
                    bnd = this.all_clusters[layer_id].getBounds();
                    bounds.push( bnd );
                }
            }
            
        }
        
        bounds = this._mergeBounds(bounds);
        
        return bounds;
    },
    
    //
    // zoom to TOP layers
    // layer_ids - native ids
    //
    zoomToLayer: function(layer_ids){
        
        var bounds = this.getBounds(layer_ids);
        this.zoomToBounds(bounds);
        
    },

    //
    // get or save map bounds in usr prefs/restore and set map
    // (used to store extent in mapDraw intersession)
    //    
    getSetMapBounds: function(is_set){
        
        if(is_set){
            var bounds = this.nativemap.getBounds();
            window.hWin.HAPI4.save_pref('map_saved_extent', bounds.toBBoxString());
        }else{
            var bounds = window.hWin.HAPI4.get_prefs_def('map_saved_extent', null);
            
            if(bounds){
                //'southwest_lng,southwest_lat,northeast_lng,northeast_lat'
                bounds = bounds.split(',');
                if(bounds.length==4){
                    var corner1 = L.latLng(bounds[1], bounds[0]),
                        corner2 = L.latLng(bounds[3], bounds[2]);
                    bounds = L.latLngBounds(corner1, corner2);            
                    this.zoomToBounds(bounds);
                }
            }
            
        }
        
    },
    
    //
    // zoom map to given bounds
    //
    zoomToBounds: function(bounds, fly_params){
        
            if(bounds && !(bounds instanceof L.LatLngBounds)){
                if($.isArray(bounds) && bounds.length>1 ){
                    bounds = L.latLngBounds(bounds);
                }
            }
                        
            if(bounds && bounds.isValid()){

                var maxZoom = this.nativemap.getMaxZoom();
                if(window.hWin.HEURIST4.util.isObject(fly_params) && fly_params['maxZoom'] && fly_params['maxZoom'] > maxZoom){
                    fly_params['maxZoom'] = maxZoom;
                }

                if(fly_params){
                    
                    if(fly_params===true){
                        fly_params = {animate:true, duration:1.5, maxZoom: maxZoom};
                    }
                    this.nativemap.flyToBounds(bounds, fly_params);
            
                }else{
                    this.nativemap.fitBounds(bounds, {maxZoom: maxZoom});      
                }             
            }
    },
    
    //
    //
    //
    removeLayer: function(layer_id)
    {
        var affected_layer = this.all_layers[layer_id];

        if(affected_layer){
            
            this._clearHighlightedMarkers();
            
            if(this.all_clusters[layer_id]){
                this.all_clusters[layer_id].clearLayers();
                this.all_clusters[layer_id].remove();
                this.all_clusters[layer_id] = null;
                delete this.all_clusters[layer_id];
            }
            if(this.all_markers[layer_id]){
                this.all_markers[layer_id] = null;
                delete this.all_markers[layer_id];
            }
            
            affected_layer.remove();
            this.all_layers[layer_id] = null;
            delete this.all_layers[layer_id];
            
            if(this.removeTimelineGroup(layer_id) && !this.notimeline){
                //update timeline
                this.vistimeline.timeline('timelineRefresh', this.timeline_items, this.timeline_groups);
            }

            this._updatePanels();

            var idx = this.available_maxzooms.findIndex(arr => arr[0] == layer_id);
            if(idx != -1){ // remove from available max zooms
                this.available_maxzooms.splice(idx, 1);
            }

            if(idx == 0){ // check if max fzoom needs updating
                this.nativemap.setMaxZoom(this.available_maxzooms[idx][1]);
            }
        }
    },

    //
    // switch layer entire visibility on off
    //
    setLayerVisibility: function(nativelayer_id, visiblity_set)
    {
        var affected_layer = this.all_layers[nativelayer_id];
        if(affected_layer){
            this._clearHighlightedMarkers();
            
            if(visiblity_set===false){
                //hide all
                affected_layer.remove();
                if(this.all_clusters[nativelayer_id]) this.all_clusters[nativelayer_id].remove();
            }else{
                affected_layer.addTo(this.nativemap);
                if(this.all_clusters[nativelayer_id]) this.all_clusters[nativelayer_id].addTo(this.nativemap);
            }
        }
    },
    
    //
    //
    //
    isLayerVisibile: function(nativelayer_id){
        var affected_layer = this.all_layers[nativelayer_id];
        if(affected_layer){
            return this.nativemap.hasLayer(affected_layer);
        }else{
            return false;
        }
    },
    
    //
    //
    //
    isSomethingOnMap: function(){
        
            var len = Object.keys(this.all_layers).length;
            //all_layers
            for (var layer_id in this.all_layers){
                var layer = this.all_layers[layer_id]
                if(window.hWin.HEURIST4.util.isArrayNotEmpty( this.all_markers[layer_id] ) || this.all_clusters[layer_id]){
                    return true;   
                }else if(layer instanceof L.ImageOverlay || layer instanceof L.TileLayer){
                    return true;   
                }else if ( layer instanceof L.LayerGroup ) {
                    var layers = layer.getLayers();
                    if(layers.length>0) return true;
                }
            }
            
            return false;
            
            /*
            is_found = false;
            this.nativemap.eachLayer(function(layer){
                if(layer instanceof L.ImageOverlay || layer instanceof L.TileLayer){
                    is_found = true;
                    return false;
                }else if (layer instanceof L.Polygon || layer instanceof L.Circle || layer instanceof L.Rectangle){
                    is_found = true;
                    return false;
                }
            });
            return is_found;
            */
            
        
    },

    addImage2: function( imageurl, image_extent ){
        return L.imageOverlay(imageurl, image_extent).addTo(this.nativemap);    
    },
    
    //
    // returns style for layer (defined in layer record or via legend)
    //
    getStyle: function(layer_id) {
        var affected_layer = this.all_layers[layer_id];
        if(!affected_layer) return null;
        var style = window.hWin.HEURIST4.util.isJSON(affected_layer.options.default_style);
        if(!style){ //layer style not defined - get default style
            return this.setStyleDefaultValues({});    
        }else{
            return style;
        }
    },
    //
    // apply style for given layer
    // it takes style from options.default_style, each feature may have its own style that overwrites layer's one
    //
    applyStyle: function(layer_id, newStyle) {
        
        var affected_layer = this.all_layers[layer_id];
        
        if(!affected_layer || affected_layer instanceof L.ImageOverlay || affected_layer instanceof L.TileLayer){
            return; //not applicable for images   
        } 

        this._clearHighlightedMarkers();

        
        var that = this;
        
        //create icons (@todo for all themes and recctypes)
        style = window.hWin.HEURIST4.util.isJSON(newStyle);
        if(!style && affected_layer.options.default_style){
            //new style is not defined and layer already has default one - no need action
            return;
        }
   
        //update markers only if style has been changed
        //var marker_style = null;
        //var myIcon = new L.Icon.Default();
        
        // set default values -------       
        style = this.setStyleDefaultValues( style );
        
        var myIcon = this._createMarkerIcon( style );

        //set default values ------- END
        
        affected_layer.options.default_style = style;
        
        
        if(this.isMarkerClusterEnabled){

            var is_new_markercluster = window.hWin.HEURIST4.util.isnull(this.all_clusters[layer_id]);
            if(is_new_markercluster){
                var opts = {showCoverageOnHover:false, 
                                maxClusterRadius:this.markerClusterGridSize,
                                spiderfyOnMaxZoom: false,
                                zoomToBoundsOnClick: false
                                //disableClusteringAtZoom:this.markerClusterMaxZoom
                };
                                
                if(window.hWin.HAPI4.database=='digital_harlem'){
                    opts['iconCreateFunction'] = function(cluster) {
                        
                        var markers = cluster.getAllChildMarkers();
                        if(markers.length>0){
                            markers = markers[0];
                            return markers.options.icon;
                        }
                    }
                }
                
                this.all_clusters[layer_id] = L.markerClusterGroup(opts);
                

                // a.layer is actually a cluster
                this.all_clusters[layer_id].on('clusterclick', function (a) {
                    //console.log(a);
                    //var clusterZoom = getBoundsZoom(a.layer.getBounds());
                    
                    if(that.nativemap.getZoom()>=that.markerClusterMaxZoom ||
                        that.nativemap.getBoundsZoom(a.layer.getBounds())>=that.markerClusterMaxZoom ){
                        if(a.layer.getAllChildMarkers().length>that.markerClusterMaxSpider){
                            var markers = a.layer.getAllChildMarkers();
                            
                            var latlng = a.layer.getLatLng();
                            var selected_layers = {};
                            var sText = '';
                            
                            //scan all markers in this cluster
                            $.each(markers, function(i, top_layer){    
                                if(top_layer.feature){
                                    selected_layers[top_layer._leaflet_id] = top_layer;
                                    var title = window.hWin.HEURIST4.util.htmlEscape( top_layer.feature.properties.rec_Title );
                                    sText = sText + '<option title="'+ title +'" value="'+top_layer._leaflet_id+'">'+ title +'</option>';
                                }
                            });
                            
                            that._showMultiSelectionPopup(latlng, sText, selected_layers);
                            
                        }else{
                           a.layer.spiderfy(); 
                        }
                    }else{
                        a.layer.zoomToBounds({padding: [20, 20]});
                    }
                });
                
            }else{
                this.all_clusters[layer_id].clearLayers() 
            }
            
        }else{
            is_new_markercluster = window.hWin.HEURIST4.util.isnull(this.all_markers[layer_id]);
        }
            
        if(is_new_markercluster){

            //all markers per top layer            
            this.all_markers[layer_id] = [];
            
            var  that = this;

            //get all markers (fill all_markers) within layer group and apply new style
            function __extractMarkers(layer, parent_layer, feature)
            {
                //var feature = layer.feature;    
                if(layer instanceof L.LayerGroup){
                    layer.eachLayer( function(child_layer){__extractMarkers(child_layer, layer, feature);} );
                    
                }else if(layer instanceof L.Marker || layer instanceof L.CircleMarker){
                    
                    layer.feature = feature;
                    
                    if(that.isMarkerClusterEnabled){
                        parent_layer.removeLayer( layer );  
                        layer.cluster_layer_id = layer_id;
                    }else{
                        //need to store reference to add/remove marker on style change  
                        //CircleMarker <> Marker
                        layer.parent_layer = parent_layer;  
                    }
                    
                    if(that.options.map_rollover){
                        layer.bindTooltip(function (layer) {
                            return layer.feature.properties.rec_Title;
                        })
                    }              
                    
                    /* expremental HARDCODE for HIE
                    if(that.isHamburgIslamicImpire){
                        if( window.hWin.HEURIST4.util.findArrayIndex(layer.feature.properties.rec_ID, that.hie_places_with_events)<0 ){ 
                        //that.hie_places_with_events.indexOf(layer.feature.properties.rec_ID)<0){
                            layer.feature.style = that.hie_places_wo_events_style;
                        }
                    }*/
                    that.all_markers[layer_id].push( layer );    
                }else if(layer instanceof L.Polyline && !(layer instanceof L.Polygon)){
                    var use_style = window.hWin.HEURIST4.util.cloneJSON( style );
                    use_style.fill = false;
                    if(!layer.feature){
                        layer.feature = {properties:feature.properties};
                    } 
                    layer.feature.default_style = use_style;
                }

                layer.on('click', function(e){that._onLayerClick(e)} );
            }

            affected_layer.eachLayer( function(child_layer){ 
                    child_layer.feature.default_style = style;
                    __extractMarkers(child_layer, affected_layer, child_layer.feature);
            } );
            
        }else{
            
            function __childLayers(layer, feature){
                if(layer instanceof L.LayerGroup){
                    layer.eachLayer( function(child_layer){__childLayers(child_layer, feature) } );
                }else if(layer instanceof L.Polyline && !(layer instanceof L.Polygon)){
                    var use_style = window.hWin.HEURIST4.util.cloneJSON( style );
                    use_style.fill = false;
                    if(!layer.feature){
                        layer.feature = {properties: feature.properties};
                    } 
                    layer.feature.default_style = use_style;
                }else{
                    if(!layer.feature){
                        layer.feature = {properties: feature.properties};
                    }
                    layer.feature.default_style = style;        
                }
            }
            
            affected_layer.eachLayer( function(child_layer){__childLayers(child_layer, child_layer.feature) });
        }
        
        //apply marker style
        $(this.all_markers[layer_id]).each(function(idx, layer){
                
                var feature = layer.feature;
                var markerStyle;
                var setIcon;
                
                layer.feature.default_style = style;
                
                if(layer.feature.style){ //indvidual style per record
                    markerStyle = that.setStyleDefaultValues(layer.feature.style);
                    setIcon = that._createMarkerIcon( markerStyle );
                }else{
                    //heurist layer common style
                    markerStyle = style;
                    setIcon = myIcon;
                }

                //define icon for record type                                        
                if(markerStyle.iconType=='rectype' )
                {
                    
                    var rty_ID = feature.properties.rec_RecTypeID;
                    if(that.myIconRectypes[rty_ID+markerStyle.color]){
                        setIcon = that.myIconRectypes[rty_ID+markerStyle.color];
                    }else{
                        var fsize = markerStyle.iconSize;
                        if(markerStyle.color){
                            setIcon = L.divIcon({  
                                html: '<img src="'
                                +window.hWin.HAPI4.iconBaseURL + rty_ID
                                +'" style="width:'+fsize+'px;height:'+fsize+'px;filter:'
                                +hexToFilter(markerStyle.color)+'"/>',
                                iconSize:[fsize, fsize]
                                //iconAnchor:[fsize/2, fsize/4]
                            });
                        }else{
                            setIcon = L.icon({
                                iconUrl: window.hWin.HAPI4.iconBaseURL + rty_ID, 
                                    //+ '&color='+encodeURIComponent(markerStyle.color)
                                    //+ '&bg='+encodeURIComponent('#ffffff')),
                                iconSize: [fsize, fsize]                        
                            });
                        }
                        
                        
                        
                        that.myIconRectypes[rty_ID+markerStyle.color] = setIcon;
                    }
                }
                
                var new_layer = null;
                if(layer instanceof L.Marker){
                    if(markerStyle.iconType=='circle'){
                        //change to circleMarker
                        markerStyle.radius = markerStyle.iconSize/2;
                        new_layer = L.circleMarker(layer.getLatLng(), markerStyle);    
                        new_layer.feature = feature;
                    }else{
                        layer.setIcon(setIcon);    
                        layer.setOpacity( markerStyle.opacity );
                        //if(markerStyle.color)
                        //    layer.valueOf()._icon.style.filter = hexToFilter(markerStyle.color);
                    }

                }else if(layer instanceof L.CircleMarker){
                    if(markerStyle.iconType!='circle'){
                        //change from circle to icon marker
                        new_layer = L.marker(layer.getLatLng(), {icon:setIcon, opacity:markerStyle.opacity });
                        new_layer.feature = feature;
                    }else{
                        markerStyle.radius = markerStyle.iconSize/2;
                        layer.setStyle(markerStyle);
                        //layer.setRadius(markerStyle.iconSize);                    
                    }
                }
                
                if(new_layer!=null){
                    that.all_markers[layer_id][idx] = new_layer; 
                    if(!that.isMarkerClusterEnabled){
                        layer.parent_layer.addLayer(new_layer);
                        layer.parent_layer.removeLayer(layer);
                        layer.remove();
                        layer = null;
                    }
                    new_layer.on('click', function(e){that._onLayerClick(e)});
                 
                    if(that.options.map_rollover){
                        new_layer.bindTooltip(function (layer) {
                            return layer.feature.properties.rec_Title;
                        })
                    }                

                }
            
        });
     
        //add all markers to cluster
        if(this.isMarkerClusterEnabled){
            this.all_clusters[layer_id].addLayers(this.all_markers[layer_id]);                                 
            if(is_new_markercluster){
                this.all_clusters[layer_id].addTo( this.nativemap );
            }
        }

        affected_layer.setStyle(function(feature){ return that._stylerFunction(feature); });
        
    },
    
    //
    // map layer (shape) on click event handler - highlight selection on timeline and map, opens popup
    //
    // content of popup can be retrieved by rec_ID via renderRecordData.php script
    // field rec_Info can have url, content or be calculated field (function)
    // 
    _onLayerClick: function(event){
        
        var layer = (event.target);
        if(layer && layer.feature){


            if(layer.feature.properties.rec_ID>0){
                //find all overlapped polygones under click point
                if(layer instanceof L.Polygon || layer instanceof L.Circle || layer instanceof L.Rectangle){
                    
                        var selected_layers = {};
                        var sText = '';
                        var latlng = event.latlng;
                        
                        //scan all visible layers
                        this.nativemap.eachLayer(function(top_layer){    
                            if(top_layer.feature && //top_layer.feature.properties.rec_ID!=layer.feature.properties.rec_ID && 
                                (top_layer instanceof L.Polygon || top_layer instanceof L.Circle || top_layer instanceof L.Rectangle)){
                                
                                    if(top_layer.contains(latlng)){
                                        selected_layers[top_layer._leaflet_id] = top_layer;
                                        var title = window.hWin.HEURIST4.util.htmlEscape( top_layer.feature.properties.rec_Title );
                                        sText = sText + '<option title="'+title+'" value="'+top_layer._leaflet_id+'">'+title+'</option>';
                                    }
                                    
                            }
                        });
                        
                        var found_cnt = Object.keys(selected_layers).length;
                        
                        if(found_cnt>1){
                            //show popup with selector
                            this._showMultiSelectionPopup(latlng, sText, selected_layers);
                            return;
                        }
                        
                }

            }                
            
            this._onLayerSelect( layer, event.latlng );
            
        }
    },

    //
    //
    //    
    _showMultiSelectionPopup: function(latlng, sText, selected_layers){
        
        var found_cnt = Object.keys(selected_layers).length;        
        
        this.main_popup.setLatLng(latlng)
                        .setContent('<p style="margin:12px;font-style:italic">'
                                +found_cnt+' map objects found here. Select desired: </p>'
                                +'<select size="'+ ((found_cnt>10)?10:found_cnt)
                                +'" style="width:100%;overflow-y: auto;border: none;outline: none; cursor:pointer">'
                                +sText+'</select>') 
                        .openOn(this.nativemap);

        $(this.main_popup.getElement()).css({
            width: '300px'
        })

        var that = this;
            
        var ele = $(this.main_popup._container).find('select');
        ele.on({'change':function(evt){
            var leaflet_id = $(evt.target).val();
            that._onLayerSelect(selected_layers[leaflet_id], latlng);
        },'mousemove':function(evt){
            var leaflet_id = $(evt.target).attr('value');
            if(leaflet_id>0){
                $(evt.target).siblings().removeClass('selected');
                $(evt.target).addClass('selected');
                var layer = selected_layers[leaflet_id];
                that.setFeatureSelection([layer.feature.properties.rec_ID]); //highlight from popup
            }
        }});
    },

    //
    //  highlight and show info popup
    //
    _onLayerSelect: function(layer, latlng){

        var that = this;
        var popupURL;

        function __showPopup(content, latlng){
            
            if(that.options.map_popup_mode=='standard'){
                
                that.main_popup.setLatLng(latlng)
                            .setContent(content)
                            .openOn(that.nativemap);

                var $popup_ele = $(that.main_popup.getElement()); // popup container
                var $content = $popup_ele.find('.leaflet-popup-content'); // content container

                // Default options
                var width = 'auto';
                    height = 'auto',
                    resizable = true,
                    maxw = '',
                    maxh = '94%';

                var behaviour = that.options.layout_params['popup_behaviour'];

                // For CMS websites
                if(behaviour == 'fixed'){
                    width = (that.options.layout_params['popup_width'] != null) ? that.options.layout_params['popup_width'] : width;
                    height = (that.options.layout_params['popup_height'] != null) ? that.options.layout_params['popup_height'] : height;
                }else if(behaviour == 'fixed_width'){
                    width = (that.options.layout_params['popup_width'] != null) ? that.options.layout_params['popup_width'] : width;
                    maxh = (that.options.layout_params['popup_height'] != null) ? that.options.layout_params['popup_height'] : '94%';
                }else if(behaviour == 'scale'){
                    maxw = (that.options.layout_params['popup_width'] != null) ? that.options.layout_params['popup_width'] : '';
                    maxh = (that.options.layout_params['popup_height'] != null) ? that.options.layout_params['popup_height'] : '94%';
                }

                // user preference, session cached only
                if(window.hWin.HEURIST4.leaflet_popup){
                    width = window.hWin.HEURIST4.leaflet_popup.width;
                    height = window.hWin.HEURIST4.leaflet_popup.height;
                }                

                if(that.options.layout_params['popup_resizing'] != null){
                    resizable = that.options.layout_params['popup_resizing'];
                }

                $popup_ele.css({
                    'width': width,
                    'height': height
                });

                $content.css({
                    'max-width': maxw,
                    'max-height': maxh,
                    'overflow': 'auto'
                });

                /*if(!(that.mapPopUpTemplate || layer.options.popup_template) && width == 'auto' && height == 'auto'){
                    $popup_ele.css({'height': '300px'});
                }*/

                that.main_popup.update();

                if(resizable !== 'false' || resizable === false){

                    $popup_ele.find('.leaflet-popup-content-wrapper').resizable({
                        ghost: true,
                        stop: function(event, ui){

                            var dims = ui.size;
                            window.hWin.HEURIST4.leaflet_popup = dims; // cache width and height

                            $popup_ele.css(dims); // update popup's dimensions
                            $popup_ele.find('.leaflet-popup-content-wrapper').css({left: '', top: ''}); // remove top and left changes

                            that.main_popup.update();
                        },
                        handles: 'all'
                    });
                }else if($popup_ele.resizable('instance') !== undefined){
                    $popup_ele.resizable('destroy');
                }
            } else if(that.options.map_popup_mode=='dialog'){
                
                window.hWin.HEURIST4.msg.showMsg(content);
                
            }
        }

        if(layer.feature.properties.rec_ID>0){
            
            if(that.vistimeline) that.vistimeline.timeline('setSelection', [layer.feature.properties.rec_ID]);

            that.setFeatureSelection([layer.feature.properties.rec_ID]); //highlight
            if($.isFunction(that.options.onselect)){
                that.options.onselect.call(that, [layer.feature.properties.rec_ID]);
            }

            var info = layer.feature.properties.rec_Info; //popup info may be already prepared
            if(info){
                if(info.indexOf('http://')==0 || info.indexOf('https://')==0){
                    popupURL =  info; //load content from url
                }
            }else{
                
                if(layer.options.popup_template=='none' || (layer.options.popup_template==null && that.mapPopUpTemplate=='none') ){
                    that.main_popup.closePopup();
                    return;
                }
                //take database from dataset origination database
                var db = layer.options.origination_db!=null
                                ?layer.options.origination_db
                                :window.hWin.HAPI4.database;
                    
                
                if(that.mapPopUpTemplate || layer.options.popup_template){
                    
                    popupURL = window.hWin.HAPI4.baseURL + 'viewers/smarty/showReps.php?snippet=1&publish=1&debug=0&q=ids:'
                            + layer.feature.properties.rec_ID
                            + '&db='+db+'&template='
                            + encodeURIComponent(layer.options.popup_template || that.mapPopUpTemplate);
                }else{
                    
                    popupURL = window.hWin.HAPI4.baseURL + 'viewers/record/renderRecordData.php?recID='
                            +layer.feature.properties.rec_ID
                            +'&db='+db;
                    
                    if(that.options.map_popup_mode=='dialog'){
                        popupURL = popupURL + '&ll=WebSearch';
                    }else{
                        popupURL = popupURL+'&mapPopup=1&ll='+window.hWin.HAPI4.sysinfo['layout'];    
                    }
                    
                    
                }  
            }              
            //open popup
            if(popupURL){
                
                if(that.options.map_popup_mode=='dialog'){
                    
                        var opts = { 
                                is_h6style: true,
                                modal: false,
                                dialogid: 'recordview_popup',    
                                //onmouseover: function(){that._clearTimeouts();},
                                title:window.hWin.HR('Info')}                
                    
                        window.hWin.HEURIST4.msg.showDialog(popupURL, opts);
                        
                }else if(that.options.map_popup_mode!='none'){
                    $.get(popupURL, function(responseTxt, statusTxt, xhr){
                        if(statusTxt == "success"){
                            __showPopup(responseTxt, latlng);
                        }
                    });
                }
            }else{
                __showPopup(info, latlng);
            }
    
        }else{
            // show multiple selection
            var sText = '';    
            for(var key in layer.feature.properties) {
                if(layer.feature.properties.hasOwnProperty(key) && key!='_deleted'){
                       sText = sText 
                        + '<div class="detailRow fieldRow" style="border:none 1px #00ff00;">'
                        + '<div class="detailType">'+key+'</div><div class="detail truncate">'
                        + window.hWin.HEURIST4.util.htmlEscape(layer.feature.properties[key])
                        + '</div></div>';                
                }
            }
            if(sText!=''){
                sText = '<div class="map_popup">' + sText + '</div>';
                __showPopup(sText, latlng);
            }
        }
    },
    
    //
    // remove special "highlight" selection circle markers from map
    //
    _clearHighlightedMarkers: function(){
      
      for(var idx in this.highlightedMarkers){
          this.highlightedMarkers[idx].remove();
      }
      
      this.highlightedMarkers = [];  
    },
    
    //
    // assigns default values for style (size,color and marker type)
    // default values priority: widget options, topmost mapdocument, user preferences
    //
    setStyleDefaultValues: function(style, suppress_prefs){
        
        //take map style from user preferences
        var def_style;
        if(suppress_prefs!==true){
            
            if(this.options.default_style){
                //take default style from widget parameters
                def_style = this.options.default_style;
            }else{
                //take default style from topmost map document
                def_style = this.mapManager.getSymbology();
            }
            
            if(!def_style){
                //otherwise from user preferences
                def_style = window.hWin.HAPI4.get_prefs('map_default_style');
                if(def_style) def_style = window.hWin.HEURIST4.util.isJSON(def_style);
            }
            def_style = this.setStyleDefaultValues(def_style, true);
        }else{
            
            //'#00b0f0' - lighy blue
            def_style = {iconType:'rectype', color:'#ff0000', fillColor:'#ff0000', weight:3, opacity:1, 
                    dashArray: '',
                    fillOpacity:0.2, iconSize:18, stroke:true, fill:true};
        }
        
        if(!style) style = {};
        if(!style.iconType || style.iconType=='default') style.iconType = def_style.iconType;
        style.iconSize = (style.iconSize>0) ?parseInt(style.iconSize) :def_style.iconSize; //((style.iconType=='circle')?9:18);
        style.color = (style.color?style.color:def_style.color);   //light blue
        style.fillColor = (style.fillColor?style.fillColor:def_style.fillColor);   //light blue
        style.weight = style.weight>=0 ?style.weight :def_style.weight;
        style.opacity = style.opacity>=0 ?style.opacity :def_style.opacity;
        style.fillOpacity = style.fillOpacity>=0 ?style.fillOpacity :def_style.fillOpacity;
        
        style.fill = window.hWin.HEURIST4.util.isnull(style.fill)?def_style.fill:style.fill;
        style.fill = window.hWin.HEURIST4.util.istrue(style.fill);
        style.stroke = window.hWin.HEURIST4.util.isnull(style.stroke)?def_style.stroke :style.stroke;
        style.stroke = window.hWin.HEURIST4.util.istrue(style.stroke);

        if(style.stroke){
            style.dashArray = window.hWin.HEURIST4.util.isnull(style.dashArray)?def_style.dashArray:style.dashArray;
        }
        
        if(!style.iconFont && def_style.iconFont){
            style.iconFont = def_style.iconFont;
        }
        
        //opacity accepts values 0~1 so need to 
        if(style.fillOpacity>1){
            style.fillOpacity = style.fillOpacity/100;
        }
        if(style.opacity>1){
            style.opacity = style.opacity/100;
        }
        
        return style;
    },        
    
    //
    // creates marker icon for url(image) and fonticon (divicon)
    //
    _createMarkerIcon: function(style){
        
        var myIcon;
      
        if(style.iconType=='url'){
            
            var fsize = style.iconSize;
            
            myIcon = L.icon({
                iconUrl: style.iconUrl,
                iconSize: [fsize, fsize]
                /*iconAnchor: [22, 94],
                popupAnchor: [-3, -76],
                shadowUrl: 'my-icon-shadow.png',
                shadowSize: [68, 95],
                shadowAnchor: [22, 94]*/
            });      
            //marker_style = {icon:myIcon};
            
        }else if(style.iconType=='iconfont'){
            
            if(!style.iconFont) style.iconFont = 'location';
            
            var cname = (style.iconFont.indexOf('ui-icon-')==0?'':'ui-icon-')+style.iconFont;
            var fsize = style.iconSize;
            var isize = 6+fsize;
            var bgcolor = (style.fillColor0?(';background-color:'+style.fillColor):';background:none');
            
            myIcon = L.divIcon({  
                html: '<div class="ui-icon '+cname+'" style="border:none;font-size:'    //padding:2px;;width:'+isize+'px;
                        +fsize+'px;width:'+fsize+'px;height:'+fsize+'px;color:'+style.color+bgcolor+'"/>',
                iconSize:[fsize, fsize],
                iconAnchor:[fsize/2, fsize/4]
            });
            
            //marker_style = {icon:myIcon};
        }  
        
        return myIcon;       
    },
    
    //
    // returns style for every path and polygone, either individual feature style of parent layer style.
    // for markers style is defined in applyStyle
    //
    _stylerFunction: function(feature){
        
        
        //feature.style - individual style (set in symbology field per record)
        //feature.default_style - style of parent heurist layer (can be changed via legend)
        var use_style = feature.style || feature.default_style;
       
                
        /* expremental HARDCODE for HIE
        if(this.isHamburgIslamicImpire){
            if( window.hWin.HEURIST4.util.findArrayIndex(feature.properties.rec_ID, this.hie_places_with_events)<0 ){ 
            //if(that.hie_places_with_events.indexOf(child_layer.feature.properties.rec_ID)<0){
                use_style = this.hie_places_wo_events_style;
            }
        }
        */
        
        if(feature.geometry && feature.geometry.type=='GeometryCollection'){
            use_style = window.hWin.HEURIST4.util.cloneJSON( use_style );
            use_style.fill = false;
        }
        
       
        //change color for selected features
        if( feature.properties && this.selected_rec_ids.indexOf( feature.properties.rec_ID )>=0){
            use_style = window.hWin.HEURIST4.util.cloneJSON( use_style );
            use_style.color = '#62A7F8'; //'#ffff00';  //yellow for selection
            use_style.fillColor = '#e6fdeb';
            use_style.fillOpacity = 0.3;
        }
        
        return use_style;
        
    },
    
    //
    // highlight and zoom (if external call)
    //
    // triggers redraw for path and polygones (assigns styler function)  and creates highlight circles for markers
    // is_external - true - public call (from app_timemap for example)  - perform zoom
    //    
    setFeatureSelection: function( _selection, is_external ){
        var that = this;
        
        this._clearHighlightedMarkers();
        
        this.selected_rec_ids = (window.hWin.HEURIST4.util.isArrayNotEmpty(_selection)) ?_selection:[];
        
        that.nativemap.eachLayer(function(top_layer){    
            if(top_layer instanceof L.LayerGroup){  //apply only for geojson
                top_layer.setStyle(function(feature) { return that._stylerFunction(feature); });
            }
        });
        
        //find selected markers by id
        this.highlightedMarkers = [];
        var selected_markers = this._getMarkersByRecordID(_selection);
        for(var idx in selected_markers){
            var layer = selected_markers[idx];
            /*if(layer instanceof L.CircleMarker){
                layer.setStyle( {color: '#ffff00'});//function(feature) {that._stylerFunction(feature); });
            }else{*/
                //create special hightlight marker below this one
                var use_style = layer.feature.style || layer.feature.default_style;
                var iconSize = ((use_style && use_style.iconSize>0)?use_style.iconSize:16);
                var radius = iconSize/2+3;
                //iconSize = ((layer instanceof L.CircleMarker) ?(iconSize+2) :(iconSize/2+4));
                
                var new_layer = L.circleMarker(layer.getLatLng(), {color: '#62A7F8'} );   //'rgba(38,60,97,1)' rgba(255,255,0,0.3) yellow circle 
                new_layer.setRadius(radius);
                new_layer.addTo( this.nativemap );
                new_layer.bringToBack();
                this.highlightedMarkers.push(new_layer);
            //}
        }        
          
        //this.main_layer.remove();
        //this.main_layer.addTo( this.nativemap );
        if (is_external===true && !this.notimeline) { //call from external source (app_timemap)
            this.vistimeline.timeline('setSelection', this.selected_rec_ids);
        }
        
        if (is_external===true){
            this.zoomToSelection();        
        }
        
    },
    
    //
    //
    //    
    findLayerByRecID: function(recIDs){
        
        var that = this;
        var res = [];
        
        function __eachLayer(layer, method) {
            for (var i in layer._layers) {
                var res = method.call(this, layer._layers[i]);
                if(res===false){
                    return false;
                }
            }
            return true;
        }
        
        function __validateLayer(layer){
            if (layer instanceof L.Layer && layer.feature && layer.feature.properties &&
                (window.hWin.HEURIST4.util.findArrayIndex(layer.feature.properties.rec_ID, recIDs)>=0)){
                
                res.push(layer);
                if(recIDs.length==1) return false;
            }
            return true;
        }
        
        /*
        __eachLayer(that.nativemap, function(layer){
            
            if(layer instanceof L.LayerGroup)   //geojson only
            {
                var r = __eachLayer(layer, __validateLayer);
                //console.log('>>');
                //if(top_layer.feature) console.log('>'+top_layer.feature.properties.rec_ID);
            } else{
                return __validateLayer(layer);   
            }
            
        });
        */
        
        that.nativemap.eachLayer(function(top_layer){    
            if(top_layer instanceof L.LayerGroup)   //geojson only
            {
                var r = top_layer.eachLayer(function(layer){
                    if (layer instanceof L.Layer && layer.feature && //(!(layer.cluster_layer_id>0)) &&
                        (window.hWin.HEURIST4.util.findArrayIndex(layer.feature.properties.rec_ID, recIDs)>=0)) 
                    {
                        res.push(layer);
                        //if(recIDs.length==1) return false;
                    }
                });
                //if(r===false) return false;
                //console.log('>>');
                //if(top_layer.feature) console.log('>'+top_layer.feature.properties.rec_ID);
            }    
        });
        

        return res;        
    },

    //
    //
    //
    fadeInLayers: function( _selection){
        var layers = this.findLayerByRecID( _selection );

        /*
        $.each(layers,function(i, lyr){
            if(lyr instanceof L.Marker){
                var icon = lyr._icon;
                $(icon).fadeIn(4000);
                //layer.valueOf()._icon
            }});
        */    
        
        var opacity = 0, finalOpacity=1, opacityStep=0.1, delay=200;
        var timer = setTimeout(function changeOpacity() {
            if (opacity < finalOpacity) {
                $.each(layers,function(i, lyr){
                    
                    if(lyr instanceof L.Marker){
                        //var icon = lyr._icon;
                        //$(icon).css('opacity', opacity);
                        lyr.setOpacity( opacity );                        
                    }else{
                        lyr.setStyle({
                            opacity: opacity,
                            fillOpacity: opacity
                        });
                    }
                });
                opacity = opacity + opacityStep
            }

            timer = setTimeout(changeOpacity, delay);
        }, delay);

    },
    
    //
    // get bounds for selection
    //
    zoomToSelection: function( _selection, _fly_params ){
        
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(_selection)){
            _selection  =  this.selected_rec_ids;
        }
        
        var that = this, bounds = [], bnd;

        var useRuler = (_selection.length==1);
        
        that.nativemap.eachLayer(function(top_layer){    
            if(top_layer instanceof L.LayerGroup)   //geojson only
                top_layer.eachLayer(function(layer){
                    if (layer instanceof L.Layer && layer.feature && //(!(layer.cluster_layer_id>0)) &&
                        (window.hWin.HEURIST4.util.findArrayIndex(layer.feature.properties.rec_ID, _selection)>=0)) 
                    {
                        bounds.push( that.getLayerBounds(layer, useRuler) );
                    }
                });
        });

        bounds = this._mergeBounds(bounds);
        
        this.zoomToBounds(bounds, _fly_params);    
        
    },

    
    //
    // returns markers by heurist ids (for filter and highlight)
    //
    _getMarkersByRecordID: function( _selection ){
        
        var selected_markers = [];
        
        if(_selection && _selection.length>0)
        for(var layer_id in this.all_markers) {
            if(this.all_markers.hasOwnProperty(layer_id)){
                    var markers = this.all_markers[layer_id];
                    $(markers).each(function(idx, layer){
                        if (_selection===true || (layer.feature &&
                         window.hWin.HEURIST4.util.findArrayIndex(layer.feature.properties.rec_ID, _selection)>=0)){
                              selected_markers.push( layer );
                              if(selected_markers.length==_selection.length) return false;
                         }
                    });
            }
            if(selected_markers.length==_selection.length) break;
        }        
        
        return selected_markers;
    },
    
    //
    //
    //
    getLayerBounds: function (layer, useRuler){
        
        if(layer instanceof L.Marker || layer instanceof L.CircleMarker){    
            var ll = layer.getLatLng();
            
            //if field 2-925 is set (zoom to point in km) use it
            if(useRuler){
                var ruler = cheapRuler(ll.lat);
                var bbox = ruler.bufferPoint([ll.lng, ll.lat], this.options.zoomToPointInKM);   //0.01          
                //w, s, e, n
                var corner1 = L.latLng(bbox[1], bbox[0]),
                    corner2 = L.latLng(bbox[3], bbox[2]);
                return L.latLngBounds(corner1, corner2);            
            }else{
                //for city 0.002 for country 0.02
                var corner1 = L.latLng(ll.lat-0.02, ll.lng-0.02),
                    corner2 = L.latLng(ll.lat+0.02, ll.lng+0.02);
                return L.latLngBounds(corner1, corner2);            
            }
        }else{
            return layer.getBounds();
        }
    },
    
    //
    // dataset_id -  {mapdoc_id:, dataset_name:, dataset_id:  or native_id}
    //
    setVisibilityAndZoom: function( dataset_id, _selection, need_zoom ){
        
        var check_function = null;

        if(_selection=='show_all'){
            
            check_function = function(rec_ID){return true};
            
        }else if (_selection=='hide_all'){

            check_function = function(rec_ID){return false};
            
        }else if(window.hWin.HEURIST4.util.isArrayNotEmpty(_selection)) {
            check_function = function(rec_ID){
                return (window.hWin.HEURIST4.util.findArrayIndex(rec_ID, _selection)>=0);
            }
        }

        if(check_function!=null){
    
            this._clearHighlightedMarkers();
            
            var _leaflet_id = this.mapManager.getLayerNativeId(dataset_id); //get _leaflet_id by mapdoc and dataset name
            
            //use  window.hWin.HEURIST4.util.findArrayIndex(layer.properties.rec_ID, _selection)
            var that = this, bounds = [];
        
            that.nativemap.eachLayer(function(top_layer){    
                if((top_layer instanceof L.LayerGroup) && (_leaflet_id==0 || _leaflet_id==top_layer._leaflet_id)){
                    top_layer.eachLayer(function(layer){
                          if (layer instanceof L.Layer && layer.feature && (!(layer.cluster_layer_id>0)) &&
                                check_function( layer.feature.properties.rec_ID )
                            ) 
                          {
                                if(!layer._map){
                                    layer.addTo( that.nativemap );   //to show  
                                }
                                bounds.push( that.getLayerBounds(layer) );
                                
                          }else{
                                layer.remove(); //to hide    
                          }
                          
                    });
                }
            });
            
            
            if(this.isMarkerClusterEnabled){
                /*  @todo
                var selected_markers = this._getMarkersByRecordID(_selection);
                for(var idx in selected_markers){

                    var layer = selected_markers[idx];
                    if(layer.cluster_layer_id>0 && that.all_clusters[layer.cluster_layer_id]){
                        
                            if(!that.all_clusters[layer.cluster_layer_id].hasLayer(layer)){
                                that.all_clusters[layer.cluster_layer_id].addLayer(layer);
                            }
                    }else                        
                        that.all_clusters[layer.cluster_layer_id].removeLayer(layer);
                    }
                }
                */
            }

            if(need_zoom!==false){
                bounds = this._mergeBounds(bounds);
                this.zoomToBounds(bounds);
            }
                
        }
        
        
        
    },

    //
    //  applies visibility for given set of heurist recIds (filter from timeline)
    // _selection - true - apply all layers, or array of rec_IDs
    //
    setFeatureVisibility: function( _selection, is_visible ){
        
        if(_selection===true || window.hWin.HEURIST4.util.isArrayNotEmpty(_selection)) {
            
            var vis_val = (is_visible==false)?'none':'block';
            
            this._clearHighlightedMarkers();
            
            //use  window.hWin.HEURIST4.util.findArrayIndex(layer.properties.rec_ID, _selection)
            var that = this;
        
            that.nativemap.eachLayer(function(top_layer){    
                if(top_layer instanceof L.LayerGroup)
                top_layer.eachLayer(function(layer){
                      if (layer instanceof L.Layer && layer.feature && (!(layer.cluster_layer_id>0)) &&
                      ( _selection===true || 
                        window.hWin.HEURIST4.util.findArrayIndex(layer.feature.properties.rec_ID, _selection)>=0)) 
                      {
                          if(is_visible==false){
                                layer.remove();    
                          }else if(layer._map==null){
                                layer.addTo( that.nativemap );    
                          }
                          /*
                          if($.isFunction(layer.getElement)){
                                var ele = layer.getElement();
                                if(ele) ele.style.display = vis_val;
                          }else{
                                    layer.setStyle({display:vis_val});
                          }
                          */
                      }
                });
            });
            
            
            if(this.isMarkerClusterEnabled){
                
                var selected_markers = this._getMarkersByRecordID(_selection);
                for(var idx in selected_markers){

                    var layer = selected_markers[idx];
                    if(layer.cluster_layer_id>0 && that.all_clusters[layer.cluster_layer_id]){
                        if(is_visible==false){
                            that.all_clusters[layer.cluster_layer_id].removeLayer(layer);
                        }else {
                            if(!that.all_clusters[layer.cluster_layer_id].hasLayer(layer)){
                                that.all_clusters[layer.cluster_layer_id].addLayer(layer);
                            }
                        }
                    }
                }
                /*
                for(var layer_id in this.all_markers) {
                    if(this.all_markers.hasOwnProperty(layer_id)){
                            var markers = this.all_markers[layer_id];
                            $(markers).each(function(idx, layer){
                                if (_selection===true || (layer.feature &&
                                 window.hWin.HEURIST4.util.findArrayIndex(layer.feature.properties.rec_ID, _selection)>=0)){
                                     
                                      if(is_visible==false){
                                          that.all_clusters[layer.cluster_layer_id].removeLayer(layer);
                                      }else {
                                            if(!that.all_clusters[layer.cluster_layer_id].hasLayer(layer)){
                                                that.all_clusters[layer.cluster_layer_id].addLayer(layer);
                                            }
                                      }
                                 }
                            });
                    }
                }*/
                
                
            }
            
        }
        
    },
    
    //
    // show/hide layout panels and map controls
    // params: 
    //   nomap, notimeline
    //   controls: [all,none,zoom,bookmark,geocoder,print,publish,legend]
    //   legend: [basemaps,search,mapdocs|onedoc]
    //   basemap: name of initial basemap
    //   basemap_filter: css filter for basemap layer
    //   extent: fixed extent    
    //
    updateLayout: function(){
        
        var params = this.options.layout_params;
        
        function __parseval(val){
            if(val===false || val===true) return val;
            if(!window.hWin.HEURIST4.util.isempty(val)){
                if(typeof val == 'string') val = val.toLowerCase();
                return !(val==0 || val=='off' || val=='no' || val=='n' || val=='false');
            }else{
                return false;
            }
        }
        function __splitval(val){
            
            var res = window.hWin.HEURIST4.util.isJSON(val);
            if(res === false){
            
                res = [];
                if(!$.isArray(val)){
                    if(!val) val = 'all';
                    val = val.toLowerCase();
                    res = val.split(',');
                }
                if(!(res.length>0)) res = val.split(';');
                if(!(res.length>0)) res = val.split('|');
                
                if(res.length==0) res.push['all'];
                
            }
            
            return res;
        }


        this.options.isPublished = (params && __parseval(params['published'])) || !window.hWin.HAPI4.has_access();
        
        //if parameters are not defined - takes default values from user preferences
        if(window.hWin.HEURIST4.util.isempty(params) || !this.options.isPublished){
            //this is not publish take params from preferences
            if(window.hWin.HEURIST4.util.isempty(params)) params = {};
            
            if(window.hWin.HEURIST4.util.isempty(params['map_rollover']))
                params['map_rollover'] = (window.hWin.HAPI4.get_prefs_def('map_rollover', 0)==1);
            if(window.hWin.HEURIST4.util.isempty(params['template']))
                params['template'] = window.hWin.HAPI4.get_prefs_def('map_template', null);
            if(window.hWin.HEURIST4.util.isempty(params['nocluster']))
                params['nocluster'] = (window.hWin.HAPI4.get_prefs_def('mapcluster_on', 0)!=1);
            if(window.hWin.HEURIST4.util.isempty(params['controls']))
                params['controls'] = window.hWin.HAPI4.get_prefs_def('mapcontrols', 'all');
            if(window.hWin.HEURIST4.util.isempty(params['nocluster']))
                params['nocluster'] = (window.hWin.HAPI4.get_prefs_def('mapcluster_on', 1)==0);
            
            params['controls'] = params['controls']+',legend'; //is always visible for non-published maps
            
            this.markerClusterGridSize = parseInt(window.hWin.HAPI4.get_prefs_def('mapcluster_grid', 50));
            this.markerClusterMaxZoom = parseInt(window.hWin.HAPI4.get_prefs_def('mapcluster_zoom', 18));
            this.markerClusterMaxSpider = parseInt(window.hWin.HAPI4.get_prefs_def('mapcluster_spider', 5));
        }
        
        
        //@todo deriveMapLocation
        
        //maxClusterRadius
        this.isMarkerClusterEnabled = !__parseval(params['nocluster']);
        this.options.isEditAllowed = !this.options.isPublished || __parseval(params['editstyle']);
        
        this.options.map_popup_mode = params['popup']; //standard, none, in dialog
        if(!this.options.map_popup_mode) this.options.map_popup_mode = 'standard';
        
        this.options.map_rollover = __parseval(params['map_rollover']);
        this.options.default_style = window.hWin.HEURIST4.util.isJSON(params['style']);

        //special case - till thematic map is not developed - for custom style
        /* expremental 
        this.isHamburgIslamicImpire = (params['search_realm']=='hie_places');
        if(this.isHamburgIslamicImpire){
            this.hie_places_with_events = 
        [122030,121870,121869,121974,121125,132793,121915,121948,121124,121978,122012,121878,121880,121873,121130,122006,121913,121924,121891,122021,121934,131700,132092,121972,121958,132244,121956,121968,121893,121923,121876,122041,121908,121885,132090,121999,122044,121998,121992,121904,122025,121946,121906,131680,121882,121895,131828,132197,121988,121921,121940,121986,122008,121961,132715,122010,122035,132030,121966,121976,121917,121984,121911,132053,132098,121919,121126,121849,122003,121899,121928,132112,121936,121954,121127,132214,121950,121932,132543,131882,121194,132745,122037,122016,121964,121980,121926,121887,121129,122039,122019,122014,121990,121952,132088,121897,132757,121883,121875,121889,131652,133081,131865,121902,121997,131934,121982,121942,121930,131643,132138,132013,121970];
            this.hie_places_wo_events_style =
            {"iconType":"iconfont","iconFont":"location","iconSize":"12","stroke":"1","color":"#4f6128","weight":"0","fillColor":"#4f6128","fillOpacity":"0.1"};
        }
        */
            

        
        //show/hide map or timeline
        this.nomap = __parseval(params['nomap']);
        this.notimeline = __parseval(params['notimeline']);
        
        var layout_opts = {};
        if(this.notimeline){
            layout_opts.south__size = 200;
            layout_opts.south__spacing_open = 0;
            layout_opts.south__spacing_closed = 0;
        }else{
            
            if(!this.vistimeline){
                this.vistimeline = $(this.element).find('.ui-layout-south').timeline({
                    element_timeline: this.options.element_timeline,
                    onselect: function(selected_rec_ids){
                        that.setFeatureSelection(selected_rec_ids); //timeline select - highlight on map
                        if($.isFunction(that.options.onselect)){ //trigger global event
                            that.options.onselect.call(that, selected_rec_ids);
                        }
                    },                
                    onfilter: function(show_rec_ids, hide_rec_ids){
                        
                        that.setFeatureVisibility(show_rec_ids, true);
                        that.setFeatureVisibility(hide_rec_ids, false);
                    }});
            }
            
            
            if(this.options.element_layout){
                var th = Math.floor($(this.options.element_layout).height()*0.2);
                layout_opts.south__size = th>200?200:th;
                
                if(this.nomap){
                    layout_opts.center__minHeight = 0;
                    layout_opts.south__spacing_open = 0;
                    layout_opts.south__spacing_closed = 0;
                }else{
                    layout_opts.south__spacing_open = 7;
                    layout_opts.south__spacing_closed = 12;
                    layout_opts.center__minHeight = 30;
                    layout_opts.center__minWidth = 200;
                }
            }
        }
        
        if(this.options.element_layout){
            
            var is_main_ui = params['ui_main']; //if true show separate toolbar for mp controls
            
            if(is_main_ui){
                layout_opts.north__size = 36;
            }else if(__parseval(params['noheader'])){ //outdated
                layout_opts.north__size = 0;
            }
            
            var mylayout = $(this.options.element_layout).layout(layout_opts);
            if(this.notimeline){
                mylayout.hide('south');
            }
        }
        
    
        //map controls {all,none,zoom,bookmark,geocoder,print,publish,legend}
        var controls = [];
        if(params['controls']!='none'){
            controls = __splitval(params['controls']);
        }
        controls.push('zoom'); //zoom is always visible
        controls.push('addmapdoc'); //add map doc is always visible for "non published" ui
        controls.push('help');
        
        var that = this;
        function __controls(val){
            var is_visible = (controls.indexOf('all')>=0  
                                || controls.indexOf(val)>=0);
            
            if(is_visible){
                
                if(!that['map_'+val])  //not yet created
                {
                    //not yet created
                    if(val=='bookmark'){ //bookmark plugin
                        that.map_bookmark = new L.Control.Bookmarks({ position: 'topleft', 
                            //formPopup:{templateOptions: {submitTextCreate:'add'}},
                            bookmarkTemplate: '<li class="{{ itemClass }}" data-id="{{ data.id }}">' +
                              '<span class="{{ removeClass }}">&times;</span>' +
                              '<span class="{{ nameClass }}">{{ data.name }}</span>' +
                              //'<span class="{{ coordsClass }}">{{ data.coords }}</span>' +
                              '</li>'
                        });
                    }else
                    if(val=='geocoder'){ //geocoder plugin
                        that.map_geocoder = L.Control.geocoder({ position: 'topleft', 
                            geocoder: new L.Control.Geocoder.Google('AIzaSyDtYPxWrA7CP50Gr9LKu_2F08M6eI8cVjk') });
                    }else
                    //print plugin
                    if(val=='print'){
                        that.map_print = L.control.browserPrint({ position: 'topleft' })
                        $(that.map_print).find('.browser-print-mode').css({padding: '2px 10px !important'}); //.v1 .browser-print-mode
                    }else
                    if(val=='publish'){ //publish plugin
                        that.map_publish = L.control.publish({ position: 'topleft', mapwidget:that });
                    }else
                    if(val=='addmapdoc'){ //addmapdoc plugin
                        that.map_addmapdoc = L.control.addmapdoc({ position: 'topleft', mapwidget:that });
                    }else
                    if(val=='help' && $.isFunction(L.control.help)){ //publish plugin
                        that.map_help = L.control.help({ position: 'topleft', mapwidget:that });
                    }else
                    if(val=='draw') //draw plugin
                    {
                        that.drawnItems = L.featureGroup().addTo(that.nativemap);
                        
                        //var is_geofilter = (controls.indexOf('drawfilter')>=0);
                        
                          /*
                        L.Edit.PolyVerticesEdit = L.Edit.PolyVerticesEdit.extend(
                           {
                                    icon: new L.DivIcon({
                                      iconSize: new L.Point(8, 8),
                                      className: 'leaflet-div-icon leaflet-editing-icon',
                                    }),
                                    touchIcon: new L.DivIcon({
                                        iconSize: new L.Point(12, 12), //was 20
                                        className: 'leaflet-div-icon leaflet-editing-icon leaflet-touch-icon'
                                    })
                          });            
                        L.Edit.Poly = L.Edit.Poly.extend(
                           {
                                    icon: new L.DivIcon({
                                      iconSize: new L.Point(8, 8),
                                      className: 'leaflet-div-icon leaflet-editing-icon',
                                    }),
                                    touchIcon: new L.DivIcon({
                                        iconSize: new L.Point(12, 12), //was 20
                                        className: 'leaflet-div-icon leaflet-editing-icon leaflet-touch-icon'
                                    })
                          });            
                        L.Draw.Polyline = L.Draw.Polyline.extend(
                           {
                                    icon: new L.DivIcon({
                                      iconSize: new L.Point(8, 8),
                                      className: 'leaflet-div-icon leaflet-editing-icon',
                                    }),
                                    touchIcon: new L.DivIcon({
                                        iconSize: new L.Point(12, 12), //was 20
                                        className: 'leaflet-div-icon leaflet-editing-icon leaflet-touch-icon'
                                    })
                          });            
                          */
                        that.drawSetControls( that.options.drawMode );
                        
                        that.nativemap.tb_del = new L.EditToolbar.Delete(that.nativemap, {featureGroup: that.drawnItems});
                        that.nativemap.tb_del.enable();

                        /*                        
                        L.Map.addInitHook('addHandler', 'tb_del', L.EditToolbar.Delete, {featureGroup: that.drawnItems});
                        that.nativemap.tb_del.enable();
                        */
                        
                        
                        //adds  new shape to drawnItems
                        that.nativemap.on(L.Draw.Event.CREATED, function (e) {
                            var layer = e.layer;
                            that.drawnItems.addLayer(layer);
                            
                            that.options.ondrawend.call(that, e);
                        });        
                        that.nativemap.on('draw:drawstart', function (e) {
                               if($.isFunction(that.options.ondraw_addstart)){
                                   that.options.ondraw_addstart.call(that, e);
                               }
                        });
                        that.nativemap.on('draw:editstart', function (e) {
                               if($.isFunction(that.options.ondraw_editstart)){
                                   that.options.ondraw_editstart.call(that, e);
                               }
                        });
                        that.nativemap.on('draw:edited', function (e) {
                               if($.isFunction(that.options.ondrawend)){
                                   that.options.ondrawend.call(that, e);
                               }
                        });     
                        
                    }
                    
                }
                
                if(that['map_'+val] && !that['map_'+val]._map) that['map_'+val].addTo(that.nativemap);
                
                if(is_main_ui){
                    var toolbar = $('#mapToolbarDiv');
                    
                    $('.leaflet-control').css({clear:'none','margin-top':'0px'});
                    
                    that._on(toolbar, {click:function(e){
                        if(!$(e.target).hasClass('ui-icon-bookmark')){
                            that.map_bookmark.collapse();    
                        }
                        if(!$(e.target).hasClass('ui-icon-print')){
                            $('.browser-print-mode').hide();
                        }
                    }});
                    
                    if(val=='legend'){
                        
                        toolbar.find('.ui-icon-list').button()
                            .on({click:function(){that.mapManager.toggle();}});
                            
                    }else if(val=='bookmark'){

                        if(that.map_bookmark){
                            
                            toolbar.find('.ui-icon-bookmark').attr('title','Bookmarks')
                                .button()
                                .on({click:function(){
                                    var ele = $('.bookmarks-container');
                                    if(ele.is(':visible')){
                                        that.map_bookmark.collapse();    
                                    }else{
                                        that.map_bookmark.expand();    
                                        //ele.css({top: that.map_bookmark.position().y+10});
                                        //left: $('a.ui-icon-bookmark').position().x});
                                    }
                                    
                                    //position({of:$('a.ui-icon-bookmark'),my:'top left', at:'bottom left' });
                            }});
                                
                                
                            var ele2 = that.map_bookmark.getContainer();
                            $(ele2).css({'margin-top':'10px'});
                            
                            $(that.map_bookmark.getContainer()).css({border:'none',height:'1px !important', padding: '0px', background: 'none'});
                            $('.bookmarks-header').hide();
                        }else{
                            toolbar.find('.ui-icon-bookmark').hide();
                        }
                        
                    }else if(val=='print'){

                        if(that.map_print){
                            toolbar.find('.ui-icon-print').button()
                                .attr('title', window.hWin.HR('Print map'))
                                .on({click:function(){  
                                    $('.browser-print-mode').css('display','inline-block');
                                }});
                            
                            $(that.map_print.getContainer()).css({border:'none',height:'0px !important',
                                                    width:'0px !important','margin-left':'200px'});
                            $('.leaflet-browser-print').hide();
                        }else{
                            toolbar.find('.ui-icon-print').hide();
                        }
                        
                    }else if(val=='publish'){ //publish plugin

                        if(that.map_publish){
                            $(that.map_publish.getContainer()).hide();
                            that._on(
                            toolbar.find('.ui-icon-globe').button()
                                .attr('title', window.hWin.HR('Print map')),
                                {click:function(){  
                                    window.hWin.HEURIST4.ui.showPublishDialog( {mode:'mapquery', mapwidget:this} );
                                }});
                        }else{
                            toolbar.find('.ui-icon-globe').hide();
                        }
                        
                    }else if(val=='help'){ 

                        if(that.map_help){
                            $(that.map_help.getContainer()).hide();
                            
                            window.hWin.HEURIST4.ui.initHelper({ button:toolbar.find('.ui-icon-help').button(),
                                url: window.hWin.HRes('mapping_overview.html #content'),
                                position: { my: 'center center', at: 'center center', 
                                    of: $(window.parent.document).find('#map-frame').parent() } 
                                    , no_init:true} ); //this.element
                        
                        }else{
                            toolbar.find('.ui-icon-help').hide();
                        }
                        
                            
                            
                    }else if(val=='addmapdoc'){ //addmapdoc plugin
                        $(that.map_addmapdoc.getContainer()).hide();
                        
                        toolbar.find('#btn_add_mapdoc')
                            .attr('title', window.hWin.HR('Create new map document'))
                                .html('<span class="ui-icon ui-map-document" style="width:22px;margin:0px;height:22px">'
                                +'<span class="ui-icon ui-icon-plus" style="position:absolute;right:0px;font-size:12px;color:white;text-shadow: 2px 2px gray;bottom:0px" />'
                                +'</span>')                        
                            .button()
                            .on({click:function(){
                                    that.mapManager.createNewMapDocument();
                            }});
                            
                    }else  if(val=='geocoder'){

                        if(that.map_geocoder){
                            $(that.map_geocoder.getContainer()).hide();

                            toolbar.find('.ui-icon-search').button()
                                .attr('title', window.hWin.HR('Search'))
                                .on({click:function(){  
                                    $(that.map_geocoder.getContainer()).show();
                                    that.map_geocoder._expand();
                                }});
                            
                            L.DomEvent.addListener(that.map_geocoder, 'collapse', 
                                function(){
                                    $(that.map_geocoder.getContainer()).hide();
                                }
                                );
                        }else{
                            toolbar.find('.ui-icon-search').hide();
                        }
                            
                    }else  if(val=='zoom'){


                        toolbar.find('.ui-icon-plus').button()
                            .attr('title', window.hWin.HR('Zoom in'))
                            .on({click:function(){  that.nativemap.zoomIn(); }});
                        
                        toolbar.find('.ui-icon-minus').button()
                            .attr('title', window.hWin.HR('Zoom out'))
                            .on({click:function(){  that.nativemap.zoomOut(); }});

                        $(that.map_zoom.getContainer()).hide();
                    }
                    
                }
                
                
            }else if(that['map_'+val]){
                that['map_'+val].remove();
            }

        }
        __controls('legend');
        __controls('zoom');
        __controls('bookmark');
        __controls('geocoder');
        __controls('print');
        //__controls('scale');
        if(controls.indexOf('draw')>=0){
             __controls('draw');   
        }else
        if(!this.options.isPublished){
            __controls('publish');
            __controls('addmapdoc');    
        }

        __controls('help');
        
        
        if(is_main_ui){
            var toolbar = $('#mapToolbarDiv');
            this._on(toolbar.find('#btn_layout_map').button({text:'Map'}),
                {click:function(e){  
                    this.nomap = !this.nomap;
                    if(this.notimeline && this.nomap) this.notimeline = false;
                    this._updatePanels()
                }});
            this._on(toolbar.find('#btn_layout_timeline').button({text:'Timeline'}),
                {click:function(e){  
                    this.notimeline = !this.notimeline;
                    if(this.notimeline && this.nomap) this.nomap = false;
                    this._updatePanels()
                }});
        }
        
            
        //   legend: [basemaps,search,mapdocs|onedoc,off,width]
        this.mapManager.updatePanelVisibility(__splitval(params['legend']));
        
        //$('#map-settingup-message').text('EXPERIMENTAL');
        
        // basemap: name of initial basemap
        if(params['basemap']){

            this.mapManager.loadBaseMap( params['basemap'] );  
            
            this.setBaseMapFilter( params['basemap_filter'] );
        }else{
            this.mapManager.loadBaseMap( 0 );  
        }

        if(params['template']){
            this.mapPopUpTemplate = params['template'];
        }
        
        $('#'+map_element_id).find('#map-loading').empty();
        
        // extent: fixed extent    
    },
    
    
    //
    //
    //
                
    onLayerStatus: function( layer_ID, status ){
        if($.isFunction(this.options.onlayerstatus)){
            this.options.onlayerstatus.call(this, layer_ID, status);
        }
        //this._updatePanels();
    },    
    
    
    /**
    * show/hide panels map and timeline
    */
    _updatePanels: function(){
        
        var no_map_data = !this.isSomethingOnMap(), 
            no_time_data = (this.timeline_groups.length==0);
        
        var toolbar = $('#mapToolbarDiv');
        if(this.nomap){
            toolbar.find('#btn_layout_map').removeClass('ui-state-active');
        }else{
            toolbar.find('#btn_layout_map').addClass('ui-state-active').blur();
        }
        if(this.notimeline){
            toolbar.find('#btn_layout_timeline').removeClass('ui-state-active');
        }else{
            toolbar.find('#btn_layout_timeline').addClass('ui-state-active').blur();
        }

        var is_main_ui = this.options.layout_params && this.options.layout_params['ui_main'];

        var new_1 = this.notimeline || (!is_main_ui && no_time_data);
        var new_2 = this.nomap || (!is_main_ui && no_map_data);
        
        if(this.is_timeline_disabled!==new_1 || this.is_map_disabled!==new_2)        
        {
            //status has been changed - action
            if(this.options.element_layout){
                if(!this.is_timeline_disabled && !this.is_map_disabled){
                    this.timeline_height = $(this.options.element_layout).find('.ui-layout-south').height() + 7;
                    //this.timeline_height = window.hWin.HAPI4.LayoutMgr.cardinalPanel('getSize', ['south','layoutHeight']
                    //    , $(this.options.element_layout) );
                }
            }

            this.is_timeline_disabled = new_1;
            this.is_map_disabled = new_2;
            
            if(this.options.element_layout){
            
                var layout_opts = {};
                var tha, th;
                if(this.is_timeline_disabled){
                    
                    //keep current timeline 
//console.log($(this.options.element_layout).layout().panes.south.height());
                    
                    //hide resize control
                    layout_opts.south__size = 0;
                    layout_opts.south__spacing_open = 0;
                    layout_opts.south__spacing_closed = 0;
                }else {
                    //default height of timeline is 20%
                    tha = $(this.options.element_layout).height();
                    th = Math.floor(tha*0.2);
                    
                    if(this.is_map_disabled){
                        layout_opts.south__size = tha-30;
                        
                        layout_opts.center__minHeight = 0;
                        layout_opts.south__spacing_open = 0;
                        layout_opts.south__spacing_closed = 0;
                    }else{
                        layout_opts.south__size = this.timeline_height>30?this.timeline_height:(th>200?200:th);
                        
                        //show resize control when both map and timeline are visible
                        layout_opts.south__spacing_open = 7;
                        layout_opts.south__spacing_closed = 12;
                        layout_opts.center__minHeight = 30;
                        layout_opts.center__minWidth = 200;
                    }                    
                }
                var mylayout = $(this.options.element_layout).layout(layout_opts);
                
                if(this.is_timeline_disabled){
                    mylayout.hide('south');
                }else{
                    mylayout.show('south');
                    mylayout.sizePane('south', layout_opts.south__size);    
                }
            }
          
          
            //refresh map
            if(!this.is_map_disabled) this.invalidateSize();
              
        }
        
        if(no_map_data){
           //$('#map').hide();
           $('#map_empty_message').show();
        }else{
           $('#map_empty_message').hide();
           $('#map').show();
        }
    },
    
//----------------------- draw routines ----------------------    
    
    //
    //  detects format and calls appropriate method
    // 
    drawLoadGeometry: function(data){

        if (!data) {
            return;   
        }
        
        gjson = window.hWin.HEURIST4.util.isJSON(data);

        if(gjson===false){
            //wkt or simple points
            if(data.indexOf('POINT')>=0 || data.indexOf('LINE')>=0 || data.indexOf('POLY')>=0){
                this.drawLoadWKT(data, false);
            }else {
                this.drawLoadSimplePoints(data); //parses, UTM to LatLng and converts to WKT 
            }         
        }else{
            this.drawLoadJson(gjson, false);
        }
        
        
    },

    //
    // requires mapDraw.js - called from doigitizer only
    //
    drawLoadSimplePoints: function(sCoords, type, UTMzone){
        
        var that = this;
        
        simplePointsToWKT(sCoords, type, UTMzone, function(wkt){ that.drawLoadWKT(wkt, false); });
    },
    
    //
    //  force_clear - remove all current shapes on map
    //
    drawLoadWKT: function(wkt, force_clear){
        
        if (! wkt) {
            //wkt = decodeURIComponent(document.location.search);
            return;
        }
        
        //remove heurist prefix with type
        var typeCode;
        var matches = wkt.match(/\??(\S+)\s+(.*)/);
        if (! matches) {
            return;
        }
        if(matches.length>2){
            typeCode = matches[1];
            if( (['m','pl','l','c','r','p']).indexOf(typeCode)>=0 ){
                wkt = matches[2];    
            }
        }else{
            wkt = matches[1];
        }        
        
        var gjson = parseWKT(wkt); //see wellknown.js
        
        if(gjson && (gjson.coordinates || gjson.geometries)){
            this.drawLoadJson(gjson, force_clear);
        }else if(force_clear){
            this.drawClearAll();
        }else{
            window.hWin.HEURIST4.msg.showMsgFlash('The text entered is not valid WKT'); 
        }        
        
    },
    
//{"type":"LineString","coordinates":[[-0.140634,51.501877],[-0.130785,51.525804],[-0.129325,51.505243],[-0.128982,51.5036]]}}    
    
    //
    // json -> map - adds draw items to map
    //
    drawLoadJson: function( gjson, force_clear ){
        
            gjson = window.hWin.HEURIST4.util.isJSON(gjson);

            if(gjson!==false || force_clear){
                this.drawClearAll();    
            } 
            
            if(gjson===false){
                window.hWin.HEURIST4.msg.showMsgFlash('The text entered is not valid GeoJSON');    
                return;
            }

            var that = this;
            
            var l2 = L.geoJSON(gjson);
            
            function __addDrawItems(lg){
                if(lg instanceof L.LayerGroup){
                    lg.eachLayer(function (layer) {
                        __addDrawItems(layer);    
                    });
                }else{
                    
                    function __addDrawItem(item){
                            that.nativemap.addLayer(item);                        
                            that.drawnItems.addLayer(item);
                            item.editing.enable();
                    }
                    
                    if(lg instanceof L.Polygon){
                        
                        var coords = lg.getLatLngs();
                        function __isRect( coords ){
                                if(coords.length==4){
                                     var l1 = Math.round(coords[0].distanceTo(coords[2]));
                                     var l2 = Math.round(coords[1].distanceTo(coords[3]));
                                     return (l1==l2);
                                }
                                return false;
                        }
                        

                        if(coords.length>0 && coords[0] instanceof L.LatLng ){
                            //simple polygon
                            if(__isRect( coords )){
                                __addDrawItem(new L.Rectangle(coords));
                            }else{
                                coords.push(coords[0]); //add last
                                __addDrawItem(new L.Polygon(coords));
                            }
                        }else{
                            //multipolygon
                            if($.isArray(coords) && coords.length==1) coords = coords[0];
                            if(coords.length>0 && coords[0] instanceof L.LatLng ){
                                if(__isRect( coords )){
                                    __addDrawItem(new L.Rectangle(coords));
                                }else{
                                    coords.push(coords[0]);
                                    __addDrawItem(new L.Polygon(coords));
                                }
                            }else{
                                for(var i=0;i<coords.length;i++){
                                      coords[i].push(coords[i][0]);
                                      __addDrawItem(new L.Polygon(coords[i]));
                                }
                            }
                        }
                        
                        
                    }else if(lg instanceof L.Polyline){
                        var coords = lg.getLatLngs();
                        if(coords.length>0 && coords[0] instanceof L.LatLng ){
                            __addDrawItem(new L.Polyline(coords));
                        }else{
                            for(var i=0;i<coords.length;i++){
                                  __addDrawItem(new L.Polyline(coords[i]));
                            }
                        }
                        
                    }else{ 

                        that.nativemap.addLayer(lg);
                        that.drawnItems.addLayer(lg);
                        lg.editing.enable();
                    }
                        
                }                
            }
            __addDrawItems(l2);
            
            this.drawZoomTo();

    },    

    //
    // zoom to drawn items
    //    
    drawZoomTo: function(){
        
            var bounds = this.drawnItems.getBounds();
            
            this.zoomToBounds(bounds);
            
    },

    //
    // remove all drawn items fromm map
    // 
    drawClearAll: function(){
        if(this.drawnItems) {
            this.drawnItems.eachLayer(function (layer) {
                layer.remove();
            });
            this.drawnItems.clearLayers();
        }    
    },

    //
    // Get draw aa json and convert to WKT string
    //
    drawGetWkt: function( show_warning ){
        
        var res = '', msg = null;
                    
        var gjson = mapping.mapping( 'drawGetJson');
        
        gjson = window.hWin.HEURIST4.util.isJSON(gjson);
                
        if(gjson===false || !window.hWin.HEURIST4.util.isGeoJSON(gjson)){
            msg = 'You have to draw a shape';
        }else{
            var res = stringifyMultiWKT(gjson);
            
            if(window.hWin.HEURIST4.util.isempty(res)){
                msg = 'Cannot convert GeoJSON to WKT. '
                +'Please click "Get Geometry", copy and send GeoJSON to development team';
            }
        }
        
        if(msg!=null){
            if(show_warning===true){
                    window.hWin.HEURIST4.msg.showMsgDlg(msg);    
            }else if(show_warning===false){
                    res = false;
            }else {
                    res = msg;
            }
        }
        
        return res;
    },
    
    //
    // returns current drawn items as geojson
    //
    drawGetJson: function( e ){
    
        var res_gjson = []; //reset
        
        function __to_gjson( layer ){
            
            if(layer instanceof L.Circle){
               //L.Circle.toPolygon(layer, 40, this.nativemap)
               var points = layer.toPolygon(40, this.nativemap);
               lr = L.polygon(points);
               
            /*}else if(layer instanceof L.Rectangle){
               
               var points = layer.toPolygon(40, this.nativemap);
               lr = L.polygon(points);
            */    
            }else{ 
                lr = layer;
            }
            
            var gjson = lr.toGeoJSON(6);
            if(window.hWin.HEURIST4.util.isJSON(gjson)){
                res_gjson.push(gjson);
            }        
        }
        
        if(e){
            var layers = e.layers;
            if(layers){
                layers.eachLayer(__to_gjson);
            }else if(e.layer) {
               __to_gjson(e.layer);
            }
        }else if(this.drawnItems) {
            this.drawnItems.eachLayer(__to_gjson);
        } 
        
        if(res_gjson.length==1){
            res_gjson = res_gjson[0];
        }else if (res_gjson.length>1) {
            res_gjson = {"type": "FeatureCollection", "features": res_gjson};
        }
        
        return res_gjson;
    },
    
    drawGetBounds: function(){
        if(this.drawnItems) {
            return this.drawnItems.getBounds(); 
        }else{
            return null;
        }
    },
    
    //
    // set draw style
    //
    drawSetStyle: function(){

         var that = this;
        
         var current_value = this.map_draw_style;
         window.hWin.HEURIST4.ui.showEditSymbologyDialog(current_value, 2, function(new_style){
            
            that.map_draw_style = new_style; 
             
            that.drawSetStyle2( new_style );
            
         });

    },
                       
    drawSetStyleTransparent: function(){
        
        var current_value = this.map_draw_style;
        
        current_value.fillOpacity = 0;
        
        this.drawSetStyle2( current_value );
        
    },
    
    //
    // apply new style for all drawnItems 
    //
    drawSetStyle2: function(new_style){
        
        var that = this;
        
            that.map_draw.setDrawingOptions({
                polygon: {shapeOptions: new_style},
                rectangle: {shapeOptions:new_style},
                circle: {shapeOptions:new_style}
            });                
            
            var new_style2 = window.hWin.HEURIST4.util.cloneJSON(new_style);
            new_style2.fill = false;
            new_style2.fillColor = null;

            that.map_draw.setDrawingOptions({
                polyline: {shapeOptions:new_style2},
            });

            that.drawnItems.eachLayer(function (layer) {
                if(layer instanceof L.Polygon ){
                      layer.setStyle(new_style);
                }else if(layer instanceof L.Polyline ){
                      layer.setStyle(new_style2);
                }
            });
    },
    
    //
    // mode full - all draw controls
    //      filter - rectangle and polygon only
    //      image  - rectangle only
    //      none
    //
    drawSetControls: function( mode ){
        
        var that = this;
        
        if(this.currentDrawMode == mode) return;

        if(this.currentDrawMode=='image'){
            that.nativemap.off('draw:editmove draw:editmove');
        }
        
        this.currentDrawMode = mode;
        
        if(this.map_draw){
            //remove previous
            this.map_draw.remove();
            this.map_draw = null;
            //this.nativemap.removeControl( this.map_draw ); 
        }
        
        if( mode == 'none' || mode == null ) return;

        //
        // create new control
        // 
        this.map_draw = new L.Control.Draw({
            position: 'topleft',
            edit: {
                featureGroup: that.drawnItems,
                poly: {
                    allowIntersection: false
                }
            },
            draw: {
                polygon: (mode=='image')?false:{
                    allowIntersection: false,
                    showArea: true,
                    shapeOptions: {
                        //color: '#bada55'
                    },
                    drawError: {
                        color: '#e1e100', // Color the shape will turn when intersects
                        message: '<strong>Oh snap!<strong> you can\'t draw that!' // Message that will show when intersect
                    },
                },
                rectangle: {
                    shapeOptions: {
                        clickable: true
                    }
                },                                
                polyline: (mode=='full')?
                        {
                            shapeOptions: {
                                //color: '#f357a1',
                                weight: 4
                            }                                    
                        }:false,
                circle: (mode=='full'),
                circlemarker: false,
                marker: (mode=='full')
            }
        }); 
        
        this.map_draw.addTo( this.nativemap );
        
        if(this.currentDrawMode=='image'){
            
            that.nativemap.on('draw:editmove draw:editresize', function (e) {
                   if($.isFunction(that.options.ondrawend)){
                       that.options.ondrawend.call(that, e);
                   }
            });     
            
            this.drawSetStyleTransparent();
        }
        
    }
    
});
