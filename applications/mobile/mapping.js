
/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

// Use proxy to get same origin URLs for tiles that don't support CORS.
//OpenLayers.ProxyHost = "proxy.cgi?url=";

/**
 * Class: OpenLayers.Layer.OSM.H3LocalProxy
 *
 * Inherits from:
 *  - <OpenLayers.Layer.OSM>
 */
OpenLayers.Layer.OSM.H3LocalProxy = OpenLayers.Class(OpenLayers.Layer.OSM, {
    /**
     * Constructor: OpenLayers.Layer.OSM.MapnikLocalProxy
     *
     * Parameters:
     * name - {String}
     * options - {Object} Hashtable of extra options to tag onto the layer
     */
    initialize: function(name, options) {
        var basePath = utils.getBasePath();
        var url = [
        	basePath+"applications/mobile/proxy.php?z=${z}&x=${x}&y=${y}&r=mapnik"
        ];
        options = OpenLayers.Util.extend({ numZoomLevels: 18 }, options);
        var newArguments = [name, url, options];
        OpenLayers.Layer.OSM.prototype.initialize.apply(this, newArguments);
    },

    CLASS_NAME: "OpenLayers.Layer.OSM.H3LocalProxy"
});

/**
 *
 *
 */
function Map_OpenLayers(div_id, _options)
{

	var _className = "Map_OpenLayers";

	var map, cacheWrite, cacheRead1, cacheRead2, selectCtrl;

	var marker_onclick = null,
		proj_wgs84 = new OpenLayers.Projection("EPSG:4326"),
		proj_map = new OpenLayers.Projection("EPSG:900913"),
		layer_style = OpenLayers.Util.extend({}, OpenLayers.Feature.Vector.style['default']);

	// add UI and behavior
	var status = document.getElementById("status"),
		hits = document.getElementById("hits"),
		cacheHits = 0,
		seeding = false;
        
   var options = _options;


	function _init(){


			layer_style.fillOpacity = 0.2;
			layer_style.graphicOpacity = 1;

			var renderer = OpenLayers.Util.getParameters(window.location.href).renderer;
			renderer = (renderer) ? [renderer] : OpenLayers.Layer.Vector.prototype.renderers;

			if(isNaN(options.minLat) || isNaN(options.maxLat) || isNaN(options.minLng) || isNaN(options.maxLng)){
				options.minLat = -33.88;
				options.maxLat = -33.8538;
				options.minLng = 151.199; //151.196;
				options.maxLng = 151.23; //151.224;
			}
			var maxExtent = new OpenLayers.Bounds(options.minLng,options.minLat,options.maxLng,options.maxLat);
			maxExtent = maxExtent.transform(proj_wgs84, proj_map);

			map = new OpenLayers.Map({
					div: div_id,
					projection: proj_map,
					//projection: "EPSG:3857",
					//projection: "EPSG:4326",
					layers: [
					new OpenLayers.Layer.OSM.H3LocalProxy("OpenStreetMap", {
						eventListeners: {
							tileloaded: updateStatus,
							loadend: detect
						}
					}),
					new OpenLayers.Layer.Vector("Segments",
							{
								style: layer_style,
								renderers: renderer
							}
					),
					new OpenLayers.Layer.Vector("Markers",
							{
								style: layer_style,
								renderers: renderer
							}
					)
			],
				restrictedExtent: maxExtent,
				maxExtent: maxExtent
				//maxScale: 10000
				//center: [0, 0],
				//zoom: 16
			});


			map.zoomToMaxExtent();


			// try cache before loading from remote resource
			cacheRead1 = new OpenLayers.Control.CacheRead({
				eventListeners: {
				activate: function() {
						cacheRead2.deactivate();
					}
				}
			});
			// try loading from remote resource and fall back to cache
			cacheRead2 = new OpenLayers.Control.CacheRead({
				autoActivate: false,
				fetchEvent: "tileerror",
				eventListeners: {
					activate: function() {
						cacheRead1.deactivate();
					}
				}
			});
			cacheWrite = new OpenLayers.Control.CacheWrite({
				imageFormat: "image/jpeg",
				eventListeners: {
					cachefull: function() {
						if (seeding) {
							stopSeeding();
						}
						if(status){
					 			status.innerHTML = "Cache full.";
						}
					}
				}
			});


            /* it works as well
            map.layers[2].events.on({
                'featureselected': function(feature) {alert(feature.id);}
            });*/

            /*works 
            var report = function(e) {
                OpenLayers.Console.log(e.type, e.feature.id);
            };            
            
            var highlightCtrl = new OpenLayers.Control.SelectFeature(map.layers[2], {
                hover: true,
                highlightOnly: true,
                renderIntent: "temporary",
                eventListeners: {
                    beforefeaturehighlighted: report,
                    featurehighlighted: report,
                    featureunhighlighted: report
                }
            });*/
            
            
            if(!options.style_marker_starttour){
                options.style_marker_starttour = {
                     strokeColor : "blue",
                     fillColor   : "blue",
                     graphicName : "triangle",
                     pointRadius : 10,
                     strokeWidth : 2,
                     rotation : 0,
                     strokeLinecap : "butt",
                     fillOpacity : 1
                };
            }
            //close
            var style_marker_select = OpenLayers.Util.extend({}, options.style_marker_starttour);
            style_marker_select.pointRadius = Number(style_marker_select.pointRadius)+2;
            
			selectCtrl = new OpenLayers.Control.SelectFeature(map.layers[2], {
                //multiple: false,
                selectStyle: style_marker_select,
				onSelect: onFeatureSelect
			});
            
            var scaleCtrl = new OpenLayers.Control.ScaleLine();
            

			map.addControls([cacheRead1, cacheRead2, cacheWrite, selectCtrl, scaleCtrl ]); //highlightCtrl
			//
			//map.addControl(new OpenLayers.Control.MousePosition());

            //highlightCtrl.activate();
			selectCtrl.activate();


		/*
			var read = document.getElementById("read");
			read.checked = true;
			read.onclick = toggleRead;
			var write = document.getElementById("write");
			write.checked = false;
			write.onclick = toggleWrite;
			document.getElementById("clear").onclick = clearCache;
			var tileloadstart = document.getElementById("tileloadstart");
			tileloadstart.checked = "checked";
			tileloadstart.onclick = setReadType;
			document.getElementById("tileerror").onclick = setReadType;
			document.getElementById("seed").onclick = startSeeding;
		*/

	}//_init()


	// detect what the browser supports
	function detect(evt) {
		// detection is only done once, so we remove the listener.
		evt.object.events.unregister("loadend", null, detect);
		var tile = map.baseLayer.grid[0][0];
		try {
			var canvasContext = tile.getCanvasContext();
			if (canvasContext) {
				// will throw an exception if CORS image requests are not supported
				canvasContext.canvas.toDataURL();
			} else {
				if(status) status.innerHTML = "Canvas not supported. Try a different browser.";
				}
		} catch(e) {
			// we remove the OSM layer if CORS image requests are not supported.
			map.setBaseLayer(map.layers[1]);
			evt.object.destroy();
		}
	}

	// update the number of cache hits and detect missing CORS support
	function updateStatus(evt) {
		if(status){
			if (window.localStorage) {
				status.innerHTML = localStorage.length + " entries in cache.";
			} else {
				status.innerHTML = "Local storage not supported. Try a different browser.";
			}
		}
		if (evt && evt.tile.url.substr(0, 5) === "data:") {
			cacheHits++;
		}
		if(hits) hits.innerHTML = cacheHits + " cache hits.";
	}

	// turn the cacheRead controls on and off
	function toggleRead() {
		if (!this.checked) {
			cacheRead1.deactivate();
			cacheRead2.deactivate();
		} else {
			setReadType();
		}
	}

	// turn the cacheWrite control on and off
	function toggleWrite() {
		cacheWrite[cacheWrite.active ? "deactivate" : "activate"]();
	}

	// clear all tiles from the cache
	function clearCache() {
		OpenLayers.Control.CacheWrite.clearCache();
		updateStatus();
	}

	// activate the cacheRead control that matches the desired fetch strategy
	function setReadType() {
		if (true){//tileloadstart.checked) {
			cacheRead1.activate();  //try cache first
		} else {
			cacheRead2.activate();
		}
	}

//------------------

	// start seeding the cache
	function startSeeding() {
		var layer = map.baseLayer,
			zoom = map.getZoom();
		seeding = {
			zoom: zoom,
			extent: map.getExtent(),
			center: map.getCenter(),
			cacheWriteActive: cacheWrite.active,
			buffer: layer.buffer,
			layer: layer
		};
		// make sure the next setCenter triggers a load
		map.zoomTo(zoom === layer.numZoomLevels-1 ? zoom - 1 : zoom + 1);
		// turn on cache writing
		cacheWrite.activate();
		// turn off cache reading
		cacheRead1.deactivate();
		cacheRead2.deactivate();

		layer.events.register("loadend", null, seed);

		// start seeding
		map.setCenter(seeding.center, zoom);
	}

	// seed a zoom level based on the extent at the time startSeeding was called
	function seed() {
		var layer = seeding.layer;
		var tileWidth = layer.tileSize.w;
		var nextZoom = map.getZoom() + 1;
		var extentWidth = seeding.extent.getWidth() / map.getResolutionForZoom(nextZoom);
		// adjust the layer's buffer size so we don't have to pan
		layer.buffer = Math.ceil((extentWidth / tileWidth - map.getSize().w / tileWidth) / 2);
		map.zoomIn();
		if (nextZoom === layer.numZoomLevels) {
			stopSeeding();
		}
	}

	// stop seeding (when done or when cache is full)
	function stopSeeding() {
		// we're done - restore previous settings
		seeding.layer.events.unregister("loadend", null, seed);
		seeding.layer.buffer = seeding.buffer;
		map.setCenter(seeding.center, seeding.zoom);
		if (!seeding.cacheWriteActive) {
			cacheWrite.deactivate();
		}
		if (true){ // read.checked) {
			setReadType();
		}
		seeding = false;
	}

//------------------


	function _clearAll(){
		var tourlayer = map.layers[1];
		tourlayer.removeAllFeatures({silent:true});
		tourlayer = map.layers[2];
		tourlayer.removeAllFeatures({silent:true});
	}

	/*
	*
	*/
	function _loadTour(tour){


				/*function __getPoints(coords){

					var points = Array();
					if(!utils.isnull(coords))
					{
						for(var n = 0; n < coords.length; n++) {
							var crd = coords[n];
			 				var pnt = new OpenLayers.Geometry.Point(crd[0], crd[1]);
							points.push(pnt);
						}
					}
					return points;
				}*/

				var style_segment = options.style_segment?options.style_segment:{
					strokeColor: "blue",
					strokeOpacity: 0.8,
					strokeWidth: 4,
					strokeWeight: 4
				};
                
                var style_marker = options.style_marker?options.style_marker:{
                     strokeColor : "blue",
                     fillColor   : "blue",
                     graphicName : "triangle",
                     pointRadius : 7,
                     strokeWidth : 2,
                     rotation : 0,
                     strokeLinecap : "butt",
                     fillOpacity : 1
                };

                var style_marker_starttour = options.style_marker_starttour;
                
				//style_marker = OpenLayers.Util.extend(style_marker, layer_style);


				var k;

				//get current tour

				var connection, stop, mapobj;

				var tourlayer = map.layers[1];
					mapProj = map.getProjectionObject();

				//create segments - polylines
				for(k = 0; k < tour.connections.length; k++) {

					connection = tour.connections[k];
					if(connection.geo == null) continue;

					/*var path = __getPoints(connection.geo);
					if(utils.isnull(path) || path.length<1) continue;
					new OpenLayers.Geometry.LineString(path)*/

					//style_segment.strokeColor = "red"; //(k%2>0)?"#2fbbff":"#1462FE";

					mapobj = new OpenLayers.Feature.Vector(
							new OpenLayers.Geometry.fromWKT(connection.geo),null,style_segment);

					mapobj.geometry = mapobj.geometry.transform(proj_wgs84, mapProj);

					tourlayer.addFeatures([mapobj]);
					//f_connections.push(mapobj);
				}//for connections
				//tourlayer.addFeatures(f_connections);

				tourlayer = map.layers[2];
				var first_stop = null;
				//create stops - markers
				for(k = 0; k < tour.stops.length; k++) {

					stop = tour.stops[k];
					if(stop.geo == null) continue;

					/*var pnts = __getPoints(stop.geo);
					if(utils.isnull(pnts) || pnts.length<1) continue;*/

				 	var style_marker2 = OpenLayers.Util.extend({}, (first_stop==null)?style_marker_starttour:style_marker);

					style_marker2.title = stop.id;

					mapobj = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.fromWKT(stop.geo), null, style_marker2);
					mapobj.id = stop.id;

					mapobj.geometry = mapobj.geometry.transform(proj_wgs84, mapProj);

					tourlayer.addFeatures([mapobj]);
                    
                    if(first_stop==null){
                        first_stop = stop;
                    }
				}//for stops

				_zoomToStop(first_stop);

	}

	/*
	* show all tours
	*/
	function _loadAllTours(seltourid, tours){


                var style_segment = options.style_segment?options.style_segment:{
                    strokeColor: "#1462FE",
                    strokeOpacity: 0.8,
                    strokeWidth: 4,
                    strokeWeight: 4
                };
                        
                var style_bbox = options.style_bbox?options.style_bbox:{
                    strokeColor: "red",
                    strokeOpacity: 0.8,
                    strokeWidth: 2,
                    strokeWeight: 2,
                    fillOpacity: 0
                };
                
                var style_marker_start = options.style_marker_start?options.style_marker_start:{
                     strokeColor : "red", //"#1462FE",
                     fillColor : "red", //"#1462FE",
                     graphicName : "star",
                     pointRadius : 10,
                     strokeWidth : 2,
                     rotation : 0, //45,
                     strokeLinecap : "butt",
                     fillOpacity : 1
                }
        
                //starting point star
				//style_marker_start = OpenLayers.Util.extend(style_marker_start, layer_style);

				var k, i, connection, tour, stop, mapobj;

				var tourlayer = map.layers[1];
					mapProj = map.getProjectionObject(),
                    bounds = null,
                    j = 0;
                    


			for (i =  0; i < tours.length; i++) {
				tour = tours[i];
                bounds = null;
                
                var style_segment2 = OpenLayers.Util.extend({}, style_segment);
                style_segment2.strokeColor = options.colors[j];
                
                var style_bbox2 = OpenLayers.Util.extend({}, style_bbox);
                style_bbox2.strokeColor = options.colors[j];
                
                var style_marker_start2 = OpenLayers.Util.extend({}, style_marker_start);
                style_marker_start2.strokeColor = options.colors[j];
                
                j++;
                if(j>options.colors.length-1){
                   j = 0; 
                }
                
				//create segments - polylines
				for(k = 0; k < tour.connections.length; k++) {

					connection = tour.connections[k];
					if(connection.geo == null) continue;

					/*
					if(tour.id == seltourid){
						style_segment.strokeColor = "#1462FE";
						style_segment.strokeWidth = 4;
					}else{
						style_segment.strokeColor = "#2fbbff";
						style_segment.strokeWidth = 2;
					}*/
                    
                    var geom = new OpenLayers.Geometry.fromWKT(connection.geo);
                    if(bounds==null){
                        bounds = geom.getBounds();    
                    }else{
                        bounds.extend(geom.getBounds());
                    }
                    
                    
                    if(options.show_paths){
					        mapobj = new OpenLayers.Feature.Vector(geom,null,style_segment2);
					        mapobj.geometry = mapobj.geometry.transform(proj_wgs84, mapProj);
					        tourlayer.addFeatures([mapobj]);
                    }
					//f_connections.push(mapobj);
				}//for connections
				//tourlayer.addFeatures(f_connections);               

				//add first STOP
				if(options.show_start && tour.stops.length>0){
					tourlayer = map.layers[2];
					//create stops - markers
					for(k = 0; k < tour.stops.length; k++) {

						stop = tour.stops[k];
						if(stop.geo == null) continue;

						/*var pnts = __getPoints(stop.geo);
						if(utils.isnull(pnts) || pnts.length<1) continue;*/

                        /*
				 		var style_marker2 = OpenLayers.Util.extend({}, style_marker_start);
						if(tour.id == seltourid){
							style_marker2.fillColor = "#1462FE";
							style_marker2.strokeColor = style_marker2.fillColor;
							style_marker2.title = tour.id;
						}*/

						var geom = new OpenLayers.Geometry.fromWKT(stop.geo);
						var pnts = geom.getVertices();

                        if(bounds==null){
                            bounds = geom.getBounds();    
                        }else{
                            bounds.extend(geom.getBounds());
                        }
                        
						mapobj = new OpenLayers.Feature.Vector(pnts[0], null, style_marker_start2);
						mapobj.id = tour.id;

						mapobj.geometry = mapobj.geometry.transform(proj_wgs84, mapProj);


						tourlayer.addFeatures([mapobj]);
						break;
					}//for stops


				}
                
                if(options.show_bbox && bounds!=null){
                    var bboxobj = new OpenLayers.Feature.Vector(bounds.toGeometry(),null,style_bbox2);
                    bboxobj.geometry = bboxobj.geometry.transform(proj_wgs84, mapProj);
                    bboxobj.id = tour.id;
                    tourlayer.addFeatures([bboxobj]);       
                }
			}

	}

	function _zoomToStop(stop){
		if(stop && stop.geo){
				var geom = (new OpenLayers.Geometry.fromWKT(stop.geo)).transform(proj_wgs84, mapProj);
				map.zoomToExtent(geom.getBounds());
                
                var ftrs = map.layers[2].getFeatureById(stop.id); // getFeaturesByAttribute("id",stop.id)[0];
                if(ftrs){
                    selectCtrl.unselectAll();
                    selectCtrl.select(ftrs);
                }
		}
	}


    function onFeatureSelect(feature) {
    	if(marker_onclick){
    		//alert(Number(feature.id));
    		marker_onclick.call(this, Number(feature.id));
		}
     }



	//public methods
	var that = {

				startMapSeed: function(){
					startSeeding();
				},

				updateSize: function(){
					map.updateSize();
				},

				setCallback: function(_marker_onclick){
					marker_onclick = _marker_onclick;
				},


				loadAllTours: function(tourid, tours){
					_loadAllTours(tourid, tours);
				},

				loadTour: function(tour){
					_loadTour(tour);
				},

				zoomToStop: function(stop){
					_zoomToStop(stop);
				},

				clearAll: function(){
					_clearAll();
				},
				getClass: function () {
					return _className;
				},

				isA: function (strClass) {
					return (strClass === _className);
				}

	};

	_init();  // initialize before returning
	return that;
}