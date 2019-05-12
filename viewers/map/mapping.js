/**
* @todo for mapping+timeline
* + 1) timeline integration
*           todo update layout, check popup on timeline, apply symbology for timeline
* + 2) symbology: global,mapdocument,layer,item 
* + 3) legend - add/remove,show/hide layers, set symbology (dialog - see Path and Marker options), save result as layer record
* 4) thematic mapping
* + 5) map document  - get list, get content by id (as geojson), load layers (as FeatureCollection)
* + 6) tiled and untiled images 
* 7) kml, shapefiles, gpx
* + 8) simplification complex paths
* + 9) bookmarks
* + 10) geocoding (search)
* 11) print, publish, preferences
* 
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
* workflow for map documents:
* mapManager.defineContent('mapdocs') -> creates mapDocument -> mapDocument loads list of mapdocs -> in callback returns treeData
* mapManager onexpand loads content of particular document mapDocument.openMapDocument(id) 
*                          - it creates mapLayers in turn they are added layers to native map
*            edit ->  mapDocument.editRecord (map doc or layer)
*            add (on DnD or on add layer from search results
*            show/hide entire mapdoc or layer mapDocument.setVisibility(recID)
*            zoomTo mapDocument.zoomTo(recID)
* All references by Heurist recID, native leaflet id is stored in mapLayer
* 
* workflow for search results:
* mapManager.defineContent('search') -> creates fake mapdocument searchResults that will consist of search results
* mapManager.onSearchResult(recordset or query) - searchResults.add(recordset) -> add mapLayer.addRecordsetLayer(recordset)
* 
* 
* 
* options:
* element_layout
* element_map    #map by default
* element_timeline  #timeline by default 
* 
* notimeline - hide timeline (map only)
* nomap - timeline only
* 
* events:
* onselect
* oninit
* 
* 
* init (former _load)
* printMap
* 
* updateLayout - show/hide map and timeline
* 
* methods for dataset ****
* addDataset(geojson - feature collection)  - add dataset to internal storage and call either reload or delete 
*                                             (if exists and new oneempty)
*                                             call refreshVisTimeline loadVisTimeline  
* getDataset(id) get json data for given dataset
* applyDatasetStyle(id, css)  (former _changeDatasetColor)
* 
* reloadDataset - add dataset to map and zoom to it (otionally)
* deleteDataset - remove from map (hide) and optionally from storage and legend, call refreshVisTimeline  
* setDatasetVisibility (former _showDataset) - call either deleteDataset or addDataset
* zoomDataset
* 
*  --
* setFeatureSelection
* setFeatureVisibility - show hide shapes(layers in leaflet) on map by rec_ID
* showPopup - force popup for given record id
* 
* Events: see options.onselect and oninit
* onSelection - 
* onInitComplete
* 
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
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
        
        element_layout: '#mapping',
        element_map: 'map',
        element_timeline: 'timeline',
 
        notimeline: false, /// hide timeline (map only)
        nomap: false, // timeline only
        noheader: false, //hide map toolbar (north of layout)
        
        // callbacks
        onselect: null,
        oninit: null,

        //        
        nativemap: null,
        isMarkerClusterEnabled:true
    },
    
    //reference to google or leaflet map
    
    nativemap: null,
    vistimeline: null,
    mapManager: null,

    all_layers: [],   // array of all loaded geojson layers
    all_clusters: {},  // markerclusters
    all_markers: {},
    
    timeline_items: {},
    timeline_groups: [], 

    main_popup:null,
    selected_rec_ids:[],

    // the widget's constructor
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
//console.log('LAYOUT resize end');
                //if(mapping) mapping.onWinResize();
            }
            
        };

        // Setting layout
        layout_opts.center__minHeight = 30;
        layout_opts.center__minWidth = 200;
        layout_opts.north__size = 30;
        layout_opts.north__spacing_open = 0;
        var th = Math.floor($(this.options.element_layout).height*0.2);
        layout_opts.south__size = th>200?200:th;
        layout_opts.south__spacing_open = 7;
        layout_opts.south__spacing_closed = 12;
        layout_opts.south__onresize_end = function() {
            //if(mapping) mapping.setTimelineMinheight();
            //console.log('timeline resize end');
            //_adjustLegendHeight();
        };

        if(this.options.notimeline){
            layout_opts.south__size = 0;
            layout_opts.south__spacing_open = 0;
            layout_opts.south__spacing_closed = 0;
        }else if(this.options.nomap){
            _options['mapVisible'] = false;
            layout_opts.center__minHeight = 0;
            layout_opts.south__spacing_open = 0;
        }
        if(this.options.noheader){
            layout_opts.north__size = 0;
        }
        /* todo
        if(window.hWin.HEURIST4.util.getUrlParameter('legend', location.search)=='off'){
            _options['legendVisible'] = false;
        }
        */

        this.mylayout = $(this.options.element_layout).layout(layout_opts);
        
        //2. INIT MAP
        if(this.options.element_map && this.options.element_map.indexOf('#')==0){
            map_element_id = this.options.element_map.substr(1);
        }else{
            map_element_id = 'map';    
        }
        
        $('#'+map_element_id).css('padding',0); //reset padding otherwise layout set it to 10px
        
        this.nativemap = L.map( map_element_id )
            .on('load', function(){that.onInitComplete();} );

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
        this.vistimeline = $(this.element).find('.ui-layout-south').timeline({
            element_timeline: this.options.element_timeline,
            onselect: function(selected_rec_ids){
                that.setFeatureSelection(selected_rec_ids); //highlight on map
                if($.isFunction(that.options.onselect)){ //trigger global event
                    that.options.onselect.call(that, selected_rec_ids);
                }
            },                
            onfilter: function(show_rec_ids, hide_rec_ids){
                
                that.setFeatureVisibility(show_rec_ids, true);
                that.setFeatureVisibility(hide_rec_ids, false);
            }});

        //4. INIT MANAGER
        this.mapManager = L.control.manager({ position: 'topright' }).addTo( this.nativemap );
        
        this.mapManager = new hMapManager({container:this.mapManager._container, mapwidget:this.element});
        
        this.nativemap.setView([51.505, -0.09], 13); //@todo change to bookmarks

        if(this.main_popup==null) this.main_popup = L.popup();
        
        //bookmark plugin
        var bkm_control = new L.Control.Bookmarks({ position: 'topleft' }).addTo( this.nativemap );
        
        //geocoder plugin
        L.Control.geocoder({ position: 'topleft' }).addTo( this.nativemap );
        
        //print plugin
        L.control.browserPrint({ position: 'topleft' }).addTo( this.nativemap );
    },

    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    _setOptions: function( ) {
        this._superApply( arguments );
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
    
    onInitComplete:function(){
        console.log('onInitComplete');
        
        //Load default base map
        // $(this.mapManager).mapmanager('loadBaseMap', 0);
        //Load default map doument
        //this.mapManager = new hMapManager(this.mapManager, this.nativemap);
        
    },
    
    //
    // Adds layer to searchResults mapdoc
    // data - recordset, heurist query or json
    // this method is invoked on global onserachfinish event in app_timemap
    //
    addDataset: function(data, dataset_name) {
        
        this.mapManager.addLayerToSearchResults( data, dataset_name );
    },
    
    addTileLayer: function(layer_url, layer_options, dataset_name){
    
        var new_layer;

        var HeuristTilerLayer = L.TileLayer.extend({
                    getBounds: function(){
                        return this.options._extent;  
                    }});

    
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
                           
        }else if(layer_options['MapTiler'])
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
                
        }else{
            new_layer = new HeuristTilerLayer(layer_url, layer_options).addTo(this.nativemap);             
        }
        
        this.all_layers[new_layer._leaflet_id] = new_layer;
        
        return new_layer._leaflet_id;
        
    },
    
    addImageOverlay: function(image_url, image_extent, dataset_name){
    
        var new_layer = L.imageOverlay(image_url, image_extent).addTo(this.nativemap);
      
        this.all_layers[new_layer._leaflet_id] = new_layer;
        
        return new_layer._leaflet_id;
    },
    
    //
    // returns nativemap id
    //
    addGeoJson: function(geojson_data, timeline_data, layer_style, dataset_name){


        if (window.hWin.HEURIST4.util.isGeoJSON(geojson_data, true) || 
            window.hWin.HEURIST4.util.isArrayNotEmpty(timeline_data)){
                
            var that = this;

            var new_layer = L.geoJSON(geojson_data, {
                    default_style: null
                    , layer_name: 'Current query'
                    //The onEachFeature option is a function that gets called on each feature before adding it to a GeoJSON layer. A common reason to use this option is to attach a popup to features when they are clicked.
                   /* 
                    , onEachFeature: function(feature, layer) {

                        //each feature may have its own feature.style
                        //if it is not defined then layer's default_style will be used
                        feature.default_style = layer.options.default_style;

                        layer.on('click', function (event) {
                            //console.log('onclick');                
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


            this.updateTimelineData(new_layer._leaflet_id, timeline_data, dataset_name);

            this.all_layers[new_layer._leaflet_id] = new_layer;
            
            //apply default style and fill markercluster
            this.applyStyle( new_layer._leaflet_id, layer_style ?layer_style:{color: "#00b0f0"});

            
            return new_layer._leaflet_id;
        }        

        else{
            return 0;
        }
        
    },

            /*convert geojson_data to timeline format
            this.main_layer.eachLayer(function(layer){

            var feature = layer.feature;

            if (feature.when && window.hWin.HEURIST4.util.isArrayNotEmpty(feature.when.timespans) ) {

            for(k=0; k<feature.when.timespans.length; k++){
            ts = feature.when.timespans[k];

            iconImg = window.hWin.HAPI4.iconBaseURL + feature.properties.rec_RecTypeID + '.png';

            titem = {
            id: timeline_dataset_name+'-'+feature.properties.rec_ID+'-'+k, //unique id
            group: timeline_dataset_name,
            content: '<img src="'+iconImg 
            +'"  align="absmiddle" style="padding-right:3px;" width="12" height="12"/>&nbsp;<span>'
            +feature.properties.rec_Title+'</span>',
            //'<span>'+recName+'</span>',
            title: feature.properties.rec_Title,
            start: ts[0],
            recID:feature.properties.rec_ID
            };

            if(ts[3] && ts[0]!=ts[3]){
            titem['end'] = ts[3];
            }else{
            titem['type'] = 'point';
            //titem['title'] = singleFieldName+': '+ dres[0] + '. ' + titem['title'];
            }

            timeline_items.push(titem); 
            }
            }
            });*/
    
    //
    //
    //
    updateTimelineData: function(layer_id, layer_data, dataset_name){
      
            var titem, k, ts, iconImg;

            if(window.hWin.HEURIST4.util.isArrayNotEmpty(layer_data) ){

                var group_idx = this.getTimelineGroup(layer_id);
                if(group_idx<0){
                    this.timeline_groups.push({ id:layer_id, content:dataset_name });        
                }else{
                    this.timeline_groups[group_idx].content = dataset_name;
                }
                
                this.timeline_items[layer_id] = [];
                
                var that = this;

                $.each(layer_data, function(idx, tdata){

                    iconImg = window.hWin.HAPI4.iconBaseURL + tdata.rec_RecTypeID + '.png';

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
    zoomToLayer: function(layer_id){
        
        var affected_layer = this.all_layers[layer_id];
        
        if(affected_layer){
            var bounds = affected_layer.getBounds();
            
            if(window.hWin.HEURIST4.util.isArrayNotEmpty( this.all_markers[layer_id] ) && this.all_clusters[layer_id]){
                var bounds2 = this.all_clusters[layer_id].getBounds();
                if(bounds && bounds2){
                    bounds.extend(bounds2.getNorthWest());
                    bounds.extend(bounds2.getSouthEast());
                }else if(bounds2){
                    bounds = bounds2;
                }    
            }
            
            if(bounds) this.nativemap.fitBounds(bounds);
        }
        
    },
    
    //
    //
    //
    removeLayer: function(layer_id)
    {
        var affected_layer = this.all_layers[layer_id];

        if(affected_layer){
            if(this.all_clusters[layer_id]){
                this.all_clusters[layer_id].clearLayers();
                this.all_clusters[layer_id].remove();
                this.all_clusters[layer_id] = null;
            }
            if(this.all_markers[layer_id]){
                this.all_markers[layer_id] = null;
            }
            
            affected_layer.remove();
            this.all_layers[layer_id] = null;
            
            if(this.removeTimelineGroup(layer_id)){
                //update timeline
                this.vistimeline.timeline('timelineRefresh', this.timeline_items, this.timeline_groups);
            }
        }
    },

    //
    //
    //
    setLayerVisibility: function(layer_id, is_visible)
    {
        var affected_layer = this.all_layers[layer_id];
        if(affected_layer){
            if(is_visible===false){
                affected_layer.remove();
                if(this.all_clusters[layer_id]) this.all_clusters[layer_id].remove();
            }else{
                affected_layer.addTo(this.nativemap);
                if(this.all_clusters[layer_id]) this.all_clusters[layer_id].addTo(this.nativemap);
            }
        }
    },
    
    //
    // apply style for given layer
    // it take style from options.default_style, each feature may have its own style that overwrites layer's one
    //
    applyStyle: function(layer_id, newStyle) {

        var affected_layer = this.all_layers[layer_id];
        if(!affected_layer) return;

        var that = this;
        
        //create icons (@todo for all themes and recctypes)
        style = window.hWin.HEURIST4.util.isJSON(newStyle);
        if(!style && affected_layer.options.default_style){
            //new style is not defined and layer already has default one - no need action
            return;
        }
/*        
        if(newStyle){
            affected_layer.options.default_style = newStyle;
            affected_layer.eachLayer(function(layer){
                layer.feature.default_style = newStyle;
            });  
        }else if(affected_layer.options.default_style){
            //new style is not defined and layer already has default one - no need action
            return;
        }
        var style = affected_layer.options.default_style;
*/
        
        //update markers only if style has been changed
        //var marker_style = null;
        var myIcon = new L.Icon.Default(); //L.icon( L.Icon.Default.prototype.options );//Default;
        
        var myIconRectypes = {};
        
        // set default values -------       
        if(!style.iconType) style.iconType ='rectype';
        style.iconSize = (style.iconSize>0) ?parseInt(style.iconSize) :((style.iconType=='circle')?9:18);
        var fcolor = (style.color?style.color:'#0000ff');
        
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
                html: '<div class="ui-icon '+cname+'" style="padding:2px;border:none;font-size:'
                        +fsize+'px;width:'+isize+'px;color:'+fcolor+bgcolor+'"/>',
                iconSize:[isize, isize]
            });
            
            //marker_style = {icon:myIcon};
        }
        //set default values ------- END
        
        affected_layer.options.default_style = style;
        
        
        if(this.options.isMarkerClusterEnabled){

            var is_new_markercluster = window.hWin.HEURIST4.util.isnull(this.all_clusters[layer_id]);
            if(is_new_markercluster){
                this.all_clusters[layer_id] = L.markerClusterGroup({showCoverageOnHover:false});
            }else{
                this.all_clusters[layer_id].clearLayers() 
            }
            
        }else{
            is_new_markercluster = window.hWin.HEURIST4.util.isnull(this.all_markers[layer_id]);
        }
            
        if(is_new_markercluster){
            
            this.all_markers[layer_id] = [];
            
            var  that = this;

            //get all markers within layer group and apply new style
            function __extractMarkers(layer, parent_layer, feature)
            {
                //var feature = layer.feature;    
                if(layer instanceof L.LayerGroup){
                    layer.eachLayer( function(child_layer){__extractMarkers(child_layer, layer, feature);} );
                    
                }else if(layer instanceof L.Marker || layer instanceof L.CircleMarker){
                    
                    layer.feature = feature;
                    
                    if(that.options.isMarkerClusterEnabled){
                        parent_layer.removeLayer( layer );  
                    }else{
                        layer.parent_layer = parent_layer;    
                    }
                    that.all_markers[layer_id].push( layer );
                }

                layer.on('click', function(e){that.onLayerClick(e)} );
            }

            affected_layer.eachLayer( function(child_layer){ 
                    child_layer.feature.default_style = style;
                    __extractMarkers(child_layer, affected_layer, child_layer.feature);
            } );
            
        }else{
            
            affected_layer.eachLayer( function(child_layer){ 
                    child_layer.feature.default_style = style;
            } );
        }
        
        //apply marker style
        $(this.all_markers[layer_id]).each(function(idx, layer){
                
                var setIcon = myIcon;
                var feature = layer.feature;

                //define icon for record type                                        
                if(style.iconType=='rectype' )
                {
                    var rty_ID = feature.properties.rec_RecTypeID;
                    if(myIconRectypes[rty_ID]){
                        setIcon = myIconRectypes[rty_ID];
                    }else{
                        
                        var fsize = style.iconSize;
                        setIcon = L.icon({
                            iconUrl: (window.hWin.HAPI4.iconBaseURL + rty_ID + 's.png&color='+encodeURIComponent(fcolor)),
                            iconSize: [fsize, fsize]                        
                        });
                        myIconRectypes[rty_ID] = setIcon;
                    }
                }
                
                var new_layer = null;
                if(layer instanceof L.Marker){
                    if(style.iconType=='circle'){
                        //change to circleMarker
                        new_layer = L.circleMarker(layer.getLatLng(), style);    
                        new_layer.feature = feature;
                    }else{
                        layer.setIcon(setIcon);    
                    }

                }else if(layer instanceof L.CircleMarker){
                    if(style.iconType!='circle'){
                        //change from circle to icon marker
                        new_layer = L.marker(layer.getLatLng(), {icon:setIcon});
                        new_layer.feature = feature;
                    }else{
                        layer.setRadius(style.iconSize);                    
                    }
                }
                
                if(new_layer!=null){
                    that.all_markers[layer_id][idx] = new_layer; 
                    if(!that.options.isMarkerClusterEnabled){
                        layer.parent_layer.addLayer(new_layer);
                        layer.parent_layer.removeLayer(layer);
                        layer.remove();
                        layer = null;
                    }
                    new_layer.on('click', function(e){that.onLayerClick(e)});
                    
                }
            
        });
     
        //add all markers to cluster
        if(this.options.isMarkerClusterEnabled){
            this.all_clusters[layer_id].addLayers(this.all_markers[layer_id]);                                 
            if(is_new_markercluster){
                this.all_clusters[layer_id].addTo( this.nativemap );
            }
        }

        affected_layer.setStyle(function(feature) {
            return feature.style || feature.default_style;
            /*
            if(that.selected_rec_ids.indexOf( feature.properties.rec_ID )>=0){
                return {color: "#ff0000"};
            }else{
                //either specific style from json or common layer style
                return feature.style || feature.default_style;
            }*/
        });


    },
    
    onLayerClick: function(event){
        
        var layer = (event.target);
        if(layer && layer.feature){
            
            var  that = this;
            //console.log('onclick');                
            that.vistimeline.timeline('setSelection', [layer.feature.properties.rec_ID]);

            that.setFeatureSelection([layer.feature.properties.rec_ID]);
            if($.isFunction(that.options.onselect)){
                that.options.onselect.call(that, [layer.feature.properties.rec_ID]);
            }
            //open popup
            var popupURL = window.hWin.HAPI4.baseURL + 'viewers/record/renderRecordData.php?mapPopup=1&recID='
            +layer.feature.properties.rec_ID+'&db='+window.hWin.HAPI4.database;

            $.get(popupURL, function(responseTxt, statusTxt, xhr){
                if(statusTxt == "success"){
                    that.main_popup.setLatLng(event.latlng)
                    .setContent(responseTxt) //'<div style="width:99%;">'+responseTxt+'</div>')
                    .openOn(that.nativemap);
                }
            });
        
        }
    },
    
        
    //
    //
    //    
    setFeatureSelection: function( _selection, is_external ){
        var that = this;
        
        
        this.selected_rec_ids = (window.hWin.HEURIST4.util.isArrayNotEmpty(_selection)) ?_selection:[];
           
        /* 
            //redraw
            //@todo highlight markers
            this.main_layer.setStyle(function(feature) {
                if(that.selected_rec_ids.indexOf( feature.properties.rec_ID )>=0){
                    return {color: "#ff0000"};
                }else{
                    //either specific style from json or common layer style
                    return feature.style || feature.default_style;
                }
            });
        */
          
        //this.main_layer.remove();
        //this.main_layer.addTo( this.nativemap );
        if (is_external===true) { //call from external source (app_timemap)
            this.vistimeline.timeline('setSelection', this.selected_rec_ids);
        }
        
    },
    
    //
    //  apply visibility for given set of recIds (filter from timeline)
    // _selection - true - apply all layers, or array of rec_IDs
    //
    setFeatureVisibility: function( _selection, is_visible ){
        
        if(_selection===true || window.hWin.HEURIST4.util.isArrayNotEmpty(_selection)) {
        
            var vis_val = (is_visible==false)?'none':'block';
            
            //use  window.hWin.HEURIST4.util.findArrayIndex(layer.properties.rec_ID, _selection)
            var that = this;
        
            that.nativemap.eachLayer(function(top_layer){    
                if(top_layer instanceof L.LayerGroup)
                top_layer.eachLayer(function(layer){
                      if (layer instanceof L.GeoJSON && (_selection===true || _selection.indexOf(layer.feature.properties.rec_ID)>=0)) 
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
            
        }
        
    },
    
    //@todo remove - all actions with layer via this widget
    getNativemap: function(){
        return this.nativemap;
    }
    
});
