/**
* @todo for mapping+timeline
* + 1) timeline integration
*           todo update layout, check popup on timeline, apply symbology for timeline
* + 2) symbology: global,mapdocument,layer,item 
* + 3) legend - add/remove,show/hide layers, set symbology (dialog - see Path and Marker options), save result as layer record
* 4) thematic mapping
* 5) map document  - get list, get content by id (as geojson), load layers (as FeatureCollection)
* 6) tiled and untiled images 
* 7) kml, shapefiles, gpx
* + 8) simplification complex paths
* 9) bookmarks
* 10) geolocation (search)
* 11) print, publish, preferences
* 
* mapping base widget
*
* It manipulates with map component (google or leaflet is implemented in descendants)
* 
* See  map_control.js for UI, timeline.js for Vis timeline and map_document.js for map document
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
* setSelection
* setVisibility - show hide shapes(layers in leaflet) on map by rec_ID
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
        oninit: null
        
    },
    
    //reference to google or leaflet map
    
    nativemap: null,
    vistimeline: null,
    mapcontrol: null,

    main_layer: null, //current search layer
    all_layers: [], //aray of all loaded geojson layers
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
                that.setSelection(selected_rec_ids); //highlight on map
                if($.isFunction(that.options.onselect)){ //trigger global event
                    that.options.onselect.call(that, selected_rec_ids);
                }
            },                
            onfilter: function(show_rec_ids, hide_rec_ids){
                
                that.setVisibility(show_rec_ids, true);
                that.setVisibility(hide_rec_ids, false);
            }});

        //4. INIT MANAGER
        this.mapcontrol = L.control.manager({ position: 'topright' }).addTo( this.nativemap );
        
        this.mapcontrol = new hMapManager(this.mapcontrol._container, this.nativemap, this.element);
        
        
        this.nativemap.setView([51.505, -0.09], 13);

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
        // $(this.mapcontrol).mapmanager('loadBaseMap', 0);
        //Load default map doument
        //this.mapcontrol = new hMapManager(this.mapcontrol, this.nativemap);
        
    },
    
    //
    //
    //
    resetStyle: function(affected_layer) {

        if(!affected_layer) affected_layer = this.main_layer;
        if(!affected_layer) return;

        var that = this;
        
        //create icons (@todo for all themes and recctypes)
        var style = affected_layer.options.default_style;
        
        //update markers only if style has been changed
        //var marker_style = null;
        var myIcon = new L.Icon.Default(); //L.icon( L.Icon.Default.prototype.options );//Default;
        
        var myIconRectypes = {};
        
        //default values        
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
        
        function __applyMarkerStyle(layer, parent_layer, feature){
            
            //var feature = layer.feature;    
            if(layer instanceof L.LayerGroup){
                
                layer.eachLayer( function(child_layer){__applyMarkerStyle(child_layer, layer, feature);} );
                
            }else{

                var setIcon = myIcon;
                                        
                if(style.iconType=='rectype' ){
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
            
                if(layer instanceof L.Marker){
                    if(style.iconType=='circle'){
                        //change to circleMarker
                        var new_layer = L.circleMarker(layer.getLatLng(), style);    
                        new_layer.feature = layer.feature;
                        parent_layer.addLayer(new_layer);
                        parent_layer.removeLayer(layer);
                    }else{
                        layer.setIcon(setIcon);    
                    }
                }else if(layer instanceof L.CircleMarker){
                    if(style.iconType!='circle'){
                        
                        var new_layer = L.marker(layer.getLatLng(), {icon:setIcon});
                        new_layer.feature = layer.feature;
                        parent_layer.addLayer(new_layer);
                        parent_layer.removeLayer(layer);
                    }else{
                        layer.setRadius(style.iconSize);                    
                    }
                }
            }
        }

        affected_layer.eachLayer( function(child_layer){ 
                __applyMarkerStyle(child_layer, affected_layer, child_layer.feature);
        } );
        
        /*define markers
        layer.options.pointToLayer(function(geoJsonPoint, latlng) {
            
                var style = layer.options.default_style;
                
                if(style.iconType=='circle'){
                    
                    return L.circleMarker(latlng, style);
                    
                }else if(style.iconType=='rectype'){
                    
                }else{
                    return L.marker(latlng, marker_style);
                }
        });
        */        


        affected_layer.setStyle(function(feature) {
            if(that.selected_rec_ids.indexOf( feature.properties.rec_ID )>=0){
                return {color: "#ff0000"};
            }else{
                //either specific style from json or common layer style
                return feature.style || feature.default_style;
            }
        });


    },
    
    //
    // data - recordset, query or json
    //
    addDataset: function(data, dataset_name) 
    {
        var curr_request = null;
        
        if( (typeof data.isA == "function") && data.isA("hRecordSet") ){
                
                var recset = data;
                
                if(recset.length()<2001){ //limit query by id otherwise use current query
                    curr_request = { w: 'all', q:'ids:'+recset.getIds().join(',') };
                }else{
                    curr_request = recset.getRequest();
                }            
            
        }else if( window.hWin.HEURIST4.util.isObject(data)&& data['q']) {
            
             curr_request = data;
        }


        var that = this;
        
        if(curr_request!=null){
        
            curr_request = {
                        q: curr_request.q,
                        rules: curr_request.rules,
                        w: curr_request.w,
                        simplify: true,
                        format:'geojson'};
            //perform search        
            window.hWin.HAPI4.RecordMgr.search_new(curr_request,
                function(response){
                    
                    if( window.hWin.HEURIST4.util.isGeoJSON(response, true) ){
                        that.addDataset(response, dataset_name);
                    }else {
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }

                }
            );                     
                        
        }else if (window.hWin.HEURIST4.util.isGeoJSON(data, true)){
        
/*            
var geojsonMarkerOptions = {
    radius: 8,
    fillColor: "#ff7800",
    color: "#000",
    weight: 1,
    opacity: 1,
    fillOpacity: 0.8
};
*/            
          if(this.main_layer && this.main_layer.options.layer_name == 'Current query'){
             this.main_layer.remove(); 
          }
          
          if (data.length==0){
             if(this.mapcontrol)
                    this.mapcontrol.removeEntry('search', { layer_name: 'Current query' }); 
              
          }else{

              /* 
              Styling
              each layer has  options.default_style
              each feature can have feature.style from geojson it overrides layer's style
              */          
              //set default style for result set
              this.main_layer = L.geoJSON(data, {
                  default_style: {color: "#00b0f0"}
                  , layer_name: 'Current query'
                  //The onEachFeature option is a function that gets called on each feature before adding it to a GeoJSON layer. A common reason to use this option is to attach a popup to features when they are clicked.
                  , onEachFeature: function(feature, layer) {

                      feature.default_style = layer.options.default_style;

                      layer.on('click', function (event) {
                          //console.log('onclick');                
                          that.vistimeline.timeline('setSelection', [feature.properties.rec_ID]);

                          that.setSelection([feature.properties.rec_ID]);
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
                      // does this feature have a property named popupContent?
                      //if (feature.properties && feature.properties.rec_Title) {
                      //    layer.bindPopup(feature.properties.rec_Title);
                      //}
                  }

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

              this.resetStyle();

              if(this.main_popup==null) this.main_popup = L.popup();

              //init timeline
              var dataset_name = this.main_layer._leaflet_id;
              var timeline_data = [],
              timeline_groups = [{ id:dataset_name, content: 'Current query'}];

              var titem, k, ts, iconImg;

              //convert data to timeline format
              this.main_layer.eachLayer(function(layer){

                  var feature = layer.feature;

                  if (feature.when && window.hWin.HEURIST4.util.isArrayNotEmpty(feature.when.timespans) ) {

                      for(k=0; k<feature.when.timespans.length; k++){
                          ts = feature.when.timespans[k];

                          iconImg = window.hWin.HAPI4.iconBaseURL + feature.properties.rec_RecTypeID + '.png';

                          titem = {
                              id: dataset_name+'-'+feature.properties.rec_ID+'-'+k, //unique id
                              group: dataset_name,
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

                          timeline_data.push(titem); 
                      }
                  }
              });
              this.vistimeline.timeline('timelineRefresh', timeline_data, timeline_groups);

              /*
              this.main_layer.on('click', function () {
              this.setStyle({
              color: 'green'
              });
              });
              if(!$.isFunction(this.mapcontrol)){    
              this.mapcontrol = new hMapManager(this.mapcontrol, this.nativemap);
              }
              */     

              //name:'Current query'   
              this.mapcontrol.redefineContent('search', {
                  layer_id: this.main_layer._leaflet_id,
                  layer_name: this.main_layer.options.layer_name });    

          }

        }
        
    },
        
    //
    //
    //    
    setSelection: function( _selection, is_external ){
        var that = this;
        
        if(this.main_layer){
            this.selected_rec_ids = (window.hWin.HEURIST4.util.isArrayNotEmpty(_selection)) ?_selection:[];
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
          
            //this.main_layer.remove();
            //this.main_layer.addTo( this.nativemap );
            if (is_external===true) { //call from external source (app_timemap)
                this.vistimeline.timeline('setSelection', this.selected_rec_ids);
            }
        }
    },
    
    //
    //  apply visibility for given set of recIds
    // _selection - true - apply all layers, or array of rec_IDs
    //
    setVisibility: function( _selection, is_visible ){
        
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
        
    }
    
});
