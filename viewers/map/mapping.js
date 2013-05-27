/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* mapping util
*
* @author      Kim Jackson
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Viewers/Map
*/
if (typeof mxn.LatLonPoint == "function") {

	//art
	if (! window.RelBrowser) { RelBrowser = {}; }

	//v1 FIXME: make JSLint-happy

	RelBrowser.Mapping = {

		map: null,  //background map - gmap or other
		tmap: null,
		marker_1:null, marker_2:null, //for debug

		defaultCenter: new mxn.LatLonPoint(-33.888, 151.19),
		defaultZoom: 14,

		timeZoomSteps : window["Timeline"] ? [
            { pixelsPerInterval:  20,  unit: Timeline.DateTime.MINUTE },
            { pixelsPerInterval:  50,  unit: Timeline.DateTime.HOUR },
			{ pixelsPerInterval: 200,  unit: Timeline.DateTime.DAY },
			{ pixelsPerInterval: 100,  unit: Timeline.DateTime.DAY },
			{ pixelsPerInterval:  50,  unit: Timeline.DateTime.DAY },
			{ pixelsPerInterval: 400,  unit: Timeline.DateTime.MONTH },
			{ pixelsPerInterval: 200,  unit: Timeline.DateTime.MONTH },
			{ pixelsPerInterval: 100,  unit: Timeline.DateTime.MONTH },
			{ pixelsPerInterval:  50,  unit: Timeline.DateTime.MONTH },
			{ pixelsPerInterval: 200,  unit: Timeline.DateTime.YEAR },
			{ pixelsPerInterval: 100,  unit: Timeline.DateTime.YEAR },
			{ pixelsPerInterval:  50,  unit: Timeline.DateTime.YEAR },
			{ pixelsPerInterval: 200,  unit: Timeline.DateTime.DECADE },
			{ pixelsPerInterval: 100,  unit: Timeline.DateTime.DECADE },
			{ pixelsPerInterval:  50,  unit: Timeline.DateTime.DECADE },
			{ pixelsPerInterval: 200,  unit: Timeline.DateTime.CENTURY },
			{ pixelsPerInterval: 100,  unit: Timeline.DateTime.CENTURY },
			{ pixelsPerInterval:  50,  unit: Timeline.DateTime.CENTURY },
			{ pixelsPerInterval: 200,  unit: Timeline.DateTime.MILLENNIUM },
			{ pixelsPerInterval: 100,  unit: Timeline.DateTime.MILLENNIUM },
			{ pixelsPerInterval:  50,  unit: Timeline.DateTime.MILLENNIUM }/*,
			{ pixelsPerInterval: 200,  unit: Timeline.DateTime.EPOCH },
			{ pixelsPerInterval: 100,  unit: Timeline.DateTime.EPOCH },
			{ pixelsPerInterval:  50,  unit: Timeline.DateTime.EPOCH },
			{ pixelsPerInterval: 200,  unit: Timeline.DateTime.ERA },
			{ pixelsPerInterval: 100,  unit: Timeline.DateTime.ERA },
			{ pixelsPerInterval:  50,  unit: Timeline.DateTime.ERA }*/
		] : [],

		customMapTypes: [],

		// definition of map types control (street, aerial, relief etc)
	addCustomMapTypeControl: function () {
/*
			var CustomMapTypeControl = function () {}, M = RelBrowser.Mapping;

			if (M.customMapTypes.length < 1) { return; }

			CustomMapTypeControl.prototype = new GControl(false);

			CustomMapTypeControl.prototype.getDefaultPosition = function () {
				return new GControlPosition(G_ANCHOR_TOP_RIGHT, new GSize(7, 40));
			};

			CustomMapTypeControl.prototype.initialize = function (map) {
				var i, $container, $cb, that = this;

				$container = $("<div/>");

				for (i = 0; i < M.customMapTypes.length; ++i) {
					(function (m) {
						var $a = $("<a href='#'>" + m.getName() + "</a>")
							.click(function () {
								M.map.setMapType(m);
								return false;
							})
							$div = $("<div/>");
							$a.appendTo($div.appendTo($container));

						GEvent.addListener(map, "maptypechanged", function () {
							that._setSelected($a, (map.getCurrentMapType() === m));
						});
						that._setSelected($a, (map.getCurrentMapType() === m));
						that._setLinkDivStyle($div);
					})(M.customMapTypes[i]);
				}

			$container.append("<hr/>");

			$cb = $("<input type='checkbox'/>")
				.change(function () {
					that._setOpacity(this.checked ? 0.7 : 1);
				});
			if ($.browser.msie) { // yuck
				$cb.click(function () {
					this.blur();
					this.focus();
				});
			}
			$label = $("<label/>").append($cb).append(" Transparent");
			$div = $("<div/>").append($label);
			$div.appendTo($container);
			this._setCheckboxStyle($cb);
			this._setLabelStyle($label);
			this._setContainerStyle($container);
			$container.appendTo(map.getContainer());
			return $container.get(0);
		};

		CustomMapTypeControl.prototype._setContainerStyle = function ($div) {
			$div.css("width", "178px");
			$div.css("background-color", "white");
			$div.css("border", "1px solid black");
			$div.css("padding", "2px 10px");
			$div.css("font-family", "Arial,sans-serif");
			$div.css("font-size", "12px");
		};

		CustomMapTypeControl.prototype._setLinkDivStyle = function ($link) {
			$div.css("padding", "0 0 3px 0");
		};

		CustomMapTypeControl.prototype._setCheckboxStyle = function ($cb) {
			$cb.css("vertical-align", "middle");
		};

		CustomMapTypeControl.prototype._setLabelStyle = function ($div) {
			$div.css("font-size", "11px");
			$div.css("cursor", "pointer");
		};

		CustomMapTypeControl.prototype._setSelected = function ($a, selected) {
			$a.css("font-weight", selected ? "bold" : "");
		};

		CustomMapTypeControl.prototype._setOpacity = function (opacity) {
			var i, mapType, M = RelBrowser.Mapping;
			for (i = 0; i < M.customMapTypes.length; ++i) {
				M.customMapTypes[i].getTileLayers()[1].opacity = opacity;
			}
			mapType = M.map.getCurrentMapType();
			M.map.setMapType(G_NORMAL_MAP);
			M.map.setMapType(mapType);
		};

		RelBrowser.Mapping.map.addControl(new CustomMapTypeControl());
*/
		RelBrowser.Mapping.map.addControls({ pan:true, zoom:'large', overview: false, scale:true, map_type:true});

	}, //end of addCustomMapTypeControl

	//to fix gmap bug after tab switching
	checkResize: function(){
		RelBrowser.Mapping.map.resizeTo(0,0);
	},

	changeScale: function (bandIndex, zoomIndex, tm) {
			var M = RelBrowser.Mapping;
			if(!tm) tm = M.tmap;

			if(zoomIndex<0) {
				zoomIndex=0;
			} else if (zoomIndex>=M.timeZoomSteps.length){
				zoomIndex = M.timeZoomSteps.length-1;
			}
				var band, interval;
				band = tm.timeline.getBand(bandIndex);

                if(band){
						interval = M.timeZoomSteps[zoomIndex].unit;
						band._zoomIndex = zoomIndex;
						band.getEther()._pixelsPerInterval = M.timeZoomSteps[zoomIndex].pixelsPerInterval;
						band.getEther()._interval = Timeline.DateTime.gregorianUnitLengths[interval];
						band.getEtherPainter()._unit = interval;
                }

	},

    zoomTimeLineToAll: function(tm){
            var M = RelBrowser.Mapping;
            if(!tm) tm = M.tmap;

            //$("#timeline").css("height", "99%");

            var start, end, zoomIndex, eventSource, d = new Date();
            eventSource = tm.timeline.getBand(0).getEventSource();

            if (eventSource.getCount() > 0) {
                start = eventSource.getEarliestDate();
                end = eventSource.getLatestDate();
                d = M.timeMidPoint(start, end);

                zoomIndex = M.findTimeScale(start, end, M.timeZoomSteps, ($(tm.tElement).width()));

                M.changeScale(1, zoomIndex, tm); //WAS + zoomIndex+3
                M.changeScale(0, zoomIndex, tm);
            }
            tm.timeline.getBand(0).setCenterVisibleDate(d);
            tm.timeline.layout();
    },

	keepMinDate:null,
	keepMaxDate:null,
	keepMinNaxDate:true,

	onWinResize:function(){
		var M = RelBrowser.Mapping;
		if(M.tmap && M.keepMinDate && M.keepMinDate){
			M.keepMinNaxDate = false;
			setTimeout(function (){
                //$("#timeline").css("height", "99%");
				var tm = RelBrowser.Mapping.tmap;
				var band = tm.timeline.getBand(0);
				band.setMinVisibleDate(M.keepMinDate);
				band.setMaxVisibleDate(M.keepMaxDate);// getCenterVisibleDate()
				tm.timeline.layout();

				//debug var ele = document.getElementById('lblBackground');
				//debug ele.innerHTML = "Restored "+M.keepMinDate+" "+M.keepMaxDate;
				M.keepMinNaxDate = true;
			}, 1000);
		}
	},

	/**
	*
	*/
	renderTimelineZoom: function (tm) {

		var $div = $("#divZoomOut");
		if ($div.length > 0) { return; } //already defined

		$div = $("#timeline-zoom");
		if ($div.length < 1) { return; }

		var M = RelBrowser.Mapping;
		if(!tm) tm = M.tmap;

		var zoom, x;

		/*
		* internal
		*/
		zoom = function (zoomIn) {

			var band = M.tmap.timeline.getBand(0);
			x = ($(M.tmap.tElement).width() / 2) - band.getViewOffset();

			if (!band._zoomSteps) {
				band._zoomSteps = M.timeZoomSteps;
			}

//artem: timelne 1.2 has some problems with zoom - so we took it from v2.0

				ether_zoom = function(_band, ether, zoomIn) {
					var netIntervalChange = 0;
					var currentZoomIndex = _band._zoomIndex;
					var newZoomIndex = currentZoomIndex;

					if (zoomIn && (currentZoomIndex > 0)) {
						newZoomIndex = currentZoomIndex - 1;
					}

					if (!zoomIn && (currentZoomIndex < (_band._zoomSteps.length - 1))) {
						newZoomIndex = currentZoomIndex + 1;
					}

					_band._zoomIndex = newZoomIndex;
					ether._interval  = Timeline.DateTime.gregorianUnitLengths[_band._zoomSteps[newZoomIndex].unit];
					ether._pixelsPerInterval = _band._zoomSteps[newZoomIndex].pixelsPerInterval;
					netIntervalChange = _band._zoomSteps[newZoomIndex].unit -
										_band._zoomSteps[currentZoomIndex].unit;
					return netIntervalChange;
				};

				// zoom disabled
				// shift the x value by our offset
				x += band._viewOffset;

				var zoomDate = band._ether.pixelOffsetToDate(x);
				var netIntervalChange = ether_zoom(band, band.getEther(), zoomIn);
				if (netIntervalChange != 0) {
					band._etherPainter._unit += netIntervalChange;
				}

				// shift our zoom date to the far left
				band._moveEther(Math.round(-band._ether.dateToPixelOffset(zoomDate)));
				// then shift it back to where the mouse was
				band._moveEther(x);


			/*
			band.zoom(zoomIn, x);*/
			M.tmap.timeline.paint();
		}; //end internal zoom function


		$("<div id='divZoomIn' title='Zoom In'><img src='"+RelBrowser.baseURL+"common/images/zoom_plus.png'></img></div>")
			.click(function () {
				zoom(true);
			})
			.appendTo($div);
        $("<div id='divZoomAll' title='Show All'><img src='"+RelBrowser.baseURL+"common/images/zoom_all.png'></img></div>")
            .click(function () {
                M.zoomTimeLineToAll();
            })
            .appendTo($div);
		$("<div  id='divZoomOut' title='Zoom Out'><img src='"+RelBrowser.baseURL+"common/images/zoom_minus.png'></img></div>")
			.click(function () {
				zoom(false);
			})
			.appendTo($div);


		//save last known interval to restore it on case of window resize
		function __keepTimelineInterval(){
			if(M.keepMinNaxDate){
				var band = M.tmap.timeline.getBand(0);
				M.keepMinDate = band.getMinVisibleDate();
				M.keepMaxDate = band.getMaxVisibleDate();// getCenterVisibleDate()

				//debug var ele = document.getElementById('lblBackground');
				//debug ele.innerHTML = "keep "+M.keepMinDate;
			}
		}

		tm.timeline.autoWidth = false;
		tm.timeline.getBand(0).addOnScrollListener(	__keepTimelineInterval );
	},

	/**
	* information window
	*/
	openInfoWindowHandler: function () {
			var $preview, content;

			content = $(this).data("info");
			if (! content) {
				// grab the preview content
				if (this.dataset.opts.preview) {
					if ($("#preview-" + this.dataset.opts.preview + " .balloon-middle").length < 1) {
						$("#preview-" + this.dataset.opts.preview).load(
							RelBrowser.baseURL + "preview/" + this.dataset.opts.preview,
							this.openInfoWindowHandler
						);
						return;
					}
					$preview = $("#preview-" + this.dataset.opts.preview + " .balloon-middle").clone();
					$preview.removeClass("balloon-middle").addClass("map-balloon");
					$(".balloon-content .clearfix", $preview).before(
						"<p><a href='" + this.dataset.opts.target + "'>more &raquo;</a></p>"
					);
					content = $preview.get(0);
				} else {
					content = this.dataset.getTitle();
				}
				$(this).data("info", content);
			}

			// open window
			if (this.getType() == "marker") {
				this.placemark.setInfoBubble(content);
				this.placemark.openBubble();
				//this.placemark.openInfoWindow(content);
			} else {
				this.map.openBubble(this.getInfoPoint(), content); //????
				//this.map.openInfoWindow(this.getInfoPoint(), content);
			}
			// custom functions will need to set this as well
			this.selected = true;
	},

	/**
	*
	*/
	initMap: function (map_div_id) {
			var matches, coords, points, l, i, lnglat, bounds = null, M = RelBrowser.Mapping;

			if (M.mapdata.focus) {
				matches = M.mapdata.focus.match(/^POLYGON\(\((.*)\)\)$/)
				if (matches) {
					coords = matches[1].split(",");
					points = [];
					l = coords.length;
					for (i = 0; i < l; ++i) {
						lnglat = coords[i].split(" ");
						if (lnglat.length === 2) {
							points.push(new mxn.LatLonPoint(lnglat[1], lnglat[0]));
						}
					}
					if (points.length > 0) {
						bounds = new mxn.BoundingBox(points[0].latConv(), points[0].lonConv(),
													 points[0].latConv(), points[0].lonConv());
						l = points.length;
						for (i = 1; i < l; ++i) {
							bounds.extend(points[i]);
						}
					}
				}
			}

			if (window["Timeline"]) { //there is Timeline object
				M.initMapAndTimeline(map_div_id, bounds); //init timemap+timeline and fill with the custom data
			} else {
				//init empty map (for digitizer)
				// wihout timeline without simile timemap - only mapstraction map
				// without custom data (except background image layer)
				M.map = new mxn.Mapstraction($("#"+map_div_id)[0]);

				M.map.setMapType(M.customMapTypes[0] || mxn.Mapstraction.ROAD);

				if (bounds) { //zoom or bounds defined in config options
					M.map.setCenterAndZoom(bounds.getCenter(), M.map.getZoomLevelForBoundingBox(bounds));
				} else {
					M.map.setCenterAndZoom(M.defaultCenter, M.defaultZoom);
				}
			}

			//tiled images
			var errors = M.addLayers(M.mapdata.layers, (M.mapdata.timemap.length>0)?0:1 );


			//???? M.map.setUIToDefault();
			M.addCustomMapTypeControl();

			return errors;
	}, //end initMap

	/**
	* init timemap+timeline and fill with the custom data
	*/
	initMapAndTimeline: function (map_div_id, bounds)
	{
			var tl_theme, tl_theme2, M = RelBrowser.Mapping;

			//there is bug in timeline - it looks _history_.html in wrong place
			SimileAjax.History.enabled = false;

			// modify timeline theme
			tl_theme = Timeline.ClassicTheme.create();
			tl_theme.autoWidth = true;
			tl_theme.mouseWheel = "default";//"zoom";
			tl_theme.event.bubble.bodyStyler = function(elem){
				$(elem).addClass("popup_body");
			};
            tl_theme.event.track.offset = 20;
			//tl_theme.event.instant.icon = Timeline.urlPrefix + "images/dull-blue-circle.png";
			//tl_theme.event.bubble.maxHeight = 0;
			//tl_theme.event.bubble.width = 320;

			//overview timeline theme
			tl_theme2 = Timeline.ClassicTheme.create();
			tl_theme2.autoWidth = true;
			tl_theme2.ether.backgroundColors = [ "#D1CECA", "#e3c5a6", "#E8E8F4", "#D0D0E8"];
			//tl_theme2.event.tape.height = 3;

			//after loading - zoom to extent
			var __onDataLoaded = function (tm) {
				// find centre date, choose scale to show entire dataset
				var M = RelBrowser.Mapping;

                M.zoomTimeLineToAll(tm);

				// finally, draw the zoom control for timeline
				M.renderTimelineZoom(tm);

				if(M.mapdata.count_mapobjects>0){
				if (bounds) { //extent was defined as given parameter
					tm.map.setCenterAndZoom(bounds.getCenter(), tm.map.getZoomLevelForBoundingBox(bounds));
				} else {
					/*
					var mapables = 0, markers = 0;
					for (var i = 0; i < tm.datasets.ds0.items.length; i++) {
						var type = tm.datasets.ds0.items[i].getType();
						if ( type == "none") {
							continue;
						} else if (type  == "marker" || type == "array"){
							markers++;
						}
						mapables++
					}*/
					for (i = 0; i < M.mapdata.layers.length; ++i) {
						var layer = M.mapdata.layers[i];
						var layerbnd = layer.extent = M.getImageLayerExtent(layer['extent']);
						if(layerbnd){
							//markers++;
							if(tm.mapBounds){
								tm.mapBounds.extend(layerbnd.getSouthWest());
								tm.mapBounds.extend(layerbnd.getNorthEast());
							}else{
								tm.mapBounds = layerbnd;
							}
						}
					}


					//if (mapables == 1 && markers == 1){	tm.map.setZoom(tm.opts.mapZoom);  markers>0 &&
					if (tm.mapBounds && tm.mapBounds.sw){
						tm.map.setCenterAndZoom(tm.mapBounds.getCenter(), tm.map.getZoomLevelForBoundingBox(tm.mapBounds));
					}else{
						tm.map.setZoom(tm.opts.mapZoom);
					}

				}
				}


			};//end __onDataLoaded

			var myLatlng = new mxn.LatLonPoint(-33.87, 151.20);
	//				     mapZoom: 13,
	// 					 mapCenter: myLatlng,

			var basePath = RelBrowser.baseURL,
				db = RelBrowser.database,
				iconPath = RelBrowser.iconBaseURL;

			var rectypeImg = "style='background-image:url(" + iconPath + "{{rectype}}.png)'";

			var editLinkIcon = "<div id='rec_edit_link' style='display:inline-block;' class='logged-in-only'><a href='"+
							basePath+ "records/edit/editRecord.html?recID={{recid}}&db="+ db +
							 "' target='_blank' title='Click to edit record'><img src='"+
							 basePath + "common/images/edit-pencil.png'/></a></div>";

			var newSearchWindow = "<div style='float:right;'><a href='"+basePath+"search/search.html?q=ids:{{recid}}&db=" + db +
							"' target='_blank' title='Open in new window' class='externalLink'>view</a></div>"

			//info window template for map (for timeline we use default window with small modifications)
			var template = '<div style="max-width: 300px;padding-top:5px;">'+
'<div style="position: static;"><img src="{{thumb}}" class="timeline-event-bubble-image">'+
'<div class="timeline-event-bubble-title"><a href={{url}}>{{title}}</a></div>'+
'<div class="popup_body">{{description}}</div>'+
'<div class="timeline-event-bubble-time">{{start}}</div>'+
'<div class="timeline-event-bubble-time">{{end}}</div>'+
			'<div style="width:100%">'+
				'<div style="display:inline-block;">'+
					'<img src="'+basePath+'common/images/16x16.gif" '+rectypeImg+' class="rft">'+
				'</div>'+
				'<div style="float:right;padding-right:10px;" id="recordID">'+
						editLinkIcon +
						newSearchWindow +
				'</div>'+
'</div></div>';

			var customIcon = basePath + "common/images/H3-favicon.png";// "heuristicon.png"; //TODO!!!! - deault marker

    		var customTheme = new TimeMapTheme({
        		"color": "#0000FF",
        		"icon": customIcon,
        		"iconSize": [16,16],
        		"iconShadow": null,
        		"iconAnchor":[9,17]
    		});

			M.tmap = TimeMap.init({
				mapId: map_div_id, // Id of map div element (required)
				timelineId: "timeline", // Id of timeline div element (required)
				datasets: M.mapdata.timemap,
				options: {
					mapZoom: 2,
					useMarkerCluster: false, //(M.mapdata.count_mapobjects<300),
					onlyTimeline: (M.mapdata.count_mapobjects<1),
					infoTemplate: template,
					theme: customTheme,
					/*
					mapZoom: 1, //default zoom
					centerMapOnItems: bounds ? false : true,
					showMapCtrl: false,
					showMapTypeCtrl: false,
					mapZoom: RelBrowser.Mapping.defaultZoom,
					centerMapOnItems: bounds ? false : true,
					mapType: M.customMapTypes[0] || mxn.Mapstraction.ROAD,
					eventIconPath: RelBrowser.baseURL + "timemap.2.0/images/",
					//!!! theme: TimeMapTheme.create("blue", { eventIconPath: RelBrowser.baseURL + "timemap.2.0/images/" }),
					openInfoWindow: mini ? function () { return false; } : RelBrowser.Mapping.openInfoWindowHandler
					openInfoWindow: RelBrowser.Mapping.openInfoWindowHandler,
					*/
					eventIconPath: RelBrowser.baseURL + "external/timemap.2.0/images/"
				},
/*
		bandIntervals:[
            M.timeZoomSteps[M.initTimeZoomIndex].unit,
            M.timeZoomSteps[M.initTimeZoomIndex-4].unit
		] Timeline.createBandInfo(
*/

			bandInfo: [
					/* #302s
                    {
					theme: tl_theme2,
					showEventText: false,
					intervalUnit: M.timeZoomSteps[M.initTimeZoomIndex].unit,
					intervalPixels: M.timeZoomSteps[M.initTimeZoomIndex].pixelsPerInterval,
					zoomIndex: M.initTimeZoomIndex-1,
					zoomSteps: M.timeZoomSteps,
					trackHeight:    0.01,
					trackGap:       0.01,
					width: "30px",
					layout:'overview'
					},*/
					{
					theme: tl_theme,
                    align: "Top",
					showEventText: true,
					intervalUnit: M.timeZoomSteps[M.initTimeZoomIndex].unit,
					intervalPixels: M.timeZoomSteps[M.initTimeZoomIndex].pixelsPerInterval,
					zoomIndex: M.initTimeZoomIndex,
					zoomSteps: M.timeZoomSteps,
					trackHeight:    1.2,
					width: "100%"
					}
					],
				dataLoadedFunction: __onDataLoaded
			}, M.tmap);

			M.map = M.tmap.map; //background map - gmap or other

	},// end initMapAndTimeline

	/**
	* helper function for "virtaul earth"  image layer tile calculation
	*/
	tileToQuadKey: function (x, y, zoom) {
			var i, mask, cell, quad = "";
			for (i = zoom; i > 0; i--) {
				mask = 1 << (i - 1);
				cell = 0;
				if ((x & mask) != 0) cell++;
				if ((y & mask) != 0) cell += 2;
				quad += cell;
			}
			return quad;
	},

	/**
	* add image layer or kml overlay as map background
	*
	* zoom_mode
	* 0 - no zoom
	* 1 - zoom to image layers
	* 2 - extend current map bounds to include extent of these layers
	*/
	addLayers: function (layers, zoom_mode) {

			var i, M = RelBrowser.Mapping,
				errors = "",
				bounds = null;

			if(M.map){
				M.map.removeAllTileLayers();
			}else{
				return;
			}

			function _isempty(obj){
				return ( (typeof obj==="undefined") || (obj===null) || (obj==="") || (obj==="null") );
			}
			// verifies url consistency
			function _checkURL(str) {
				var v = new RegExp();
				v.compile("^((ht|f)tp(s?)\:\/\/|~/|/)?[A-Za-z0-9-_]+\\.[A-Za-z0-9-_%&\?\/.=]+$");
				//		^(http:\/\/www.|https:\/\/www.|ftp:\/\/www.|www.){1}([0-9A-Za-z]+\.)
				if (!v.test(str)) {
					return false;
				}
				return true;
			}//end internal _checkURL

			for (i = 0; i < layers.length; ++i) {
				(function (layer) { //execute this function for each layer in given array

				if (layer.type === "kmlfile")
				{
					M.map.addOverlay(layer.url, (zoom_mode==1));
				}else{

					var tile_url;

					if(!_isempty(layer['error'])){ //server side error messages

						errors = errors + layer['error'];

					}else if(_isempty(layer.url)){ //check is redundant - we validate it on server

						errors = errors + "URL is not defined for image layer. Rec#"+layer.rec_ID;

					}else if(!_checkURL(layer.url)){ //check is redundant - we validate it on server

						errors = errors + "URL is not valid for image layer. Rec#"+layer.rec_ID;

					}else if (layer.type === "virtual earth") {
						tile_url = function (a,b) {
							var res = layer.url + M.tileToQuadKey(a.x,a.y,b) + (layer.mime_type == "image/png" ? ".png" : ".gif");
							return res;
						};
					} else if (layer.type === "maptiler") {
						if(layer.url.charAt(layer.url.length-1)!=="/"){
							layer.url = layer.url + "/";
						}
						//internal function to obtain tile URL
						tile_url =	function(tile,zoom) {
				              if ((zoom < 1) /*|| (zoom > 19)*/ || (zoom < layer.min_zoom) || (zoom > layer.max_zoom)) {
				                  return "http://www.maptiler.org/img/none.png";
				              }
				              var ymax = 1 << zoom;
				              var y = ymax - tile.y -1;

				              var pnt  = M.map.fromPixelToLatLng(tile.x-64, tile.y-64, zoom);
				              var pnt2 = M.map.fromPixelToLatLng(tile.x+64, tile.y+64, zoom);
				              var tileBounds = new mxn.BoundingBox(pnt2.lat, pnt.lng, pnt.lat, pnt2.lng);

				              if (layer.extent==null || layer.extent.intersects(tileBounds)) {  // layer.extent.contains(pnt)){ // true) {
			              			var surl = layer.url+zoom+"/"+tile.x+"/"+y+".png";
			                  		return surl;
				              } else {
			                  		return "http://www.maptiler.org/img/none.png";
				              }
			          	  }; //end of internal function to obtain tile URL
					} else {
						errors = errors + "Map type is not defined properly for image layer. It should be virtual earth or maptiler. Rec#"+layer.rec_ID;//+"\n";
					}

					//it tile_url is defined - add this layer to mapstraction
					if(tile_url){
						layer.min_zoom = new Number(layer.min_zoom);
						layer.max_zoom = new Number(layer.max_zoom);
						if(isNaN(layer.max_zoom)) {
							layer.max_zoom = 19;
						}
						if(isNaN(layer.min_zoom)) {
							layer.min_zoom = layer.max_zoom - 8;
						}
						if(layer.min_zoom>layer.max_zoom){layer.min_zoom = layer.max_zoom;}
						if(layer.min_zoom<1){ layer.min_zoom = 1; }
						//if(layer.max_zoom>19){ layer.max_zoom = 19; }

						M.map.addTileLayer(tile_url, 0.75, layer.rec_ID, layer.min_zoom, layer.max_zoom, true);
						//layer.min_zoom, layer.max_zoom, true);
            //M.map.getMap().mapTypes["roadmap"].maxZoom = layer.max_zoom;
						layer.extent = M.getImageLayerExtent(layer['extent']);
/* DEBUG
if(layer.extent){
var gmap = M.map.maps.googlev3;
if(M.marker_1) M.marker_1.setMap(null);
if(M.marker_2) M.marker_2.setMap(null);

var point = new google.maps.LatLng(layer.extent.sw.lat, layer.extent.sw.lng);
M.marker_1 = new google.maps.Marker({
position: point,
map: gmap});
point = new google.maps.LatLng(layer.extent.ne.lat, layer.extent.ne.lng);
M.marker_2 = new google.maps.Marker({
position: point,
map: gmap});
}
*/
						if(zoom_mode>0){
							var layerbnd = layer.extent;
							if(layerbnd){
								if(bounds==null){
									bounds = layerbnd;
								}else{
									bounds.extend(layerbnd.getSouthWest());
									bounds.extend(layerbnd.getNorthEast());
								}
							}
						}

					}
				}
				})(layers[i]);
			}//for


			if(bounds!=null && zoom_mode>0){

				/*if(zoom_mode==2){ // extend current map bounds to include extent of these layers
					var bounds2 = M.map.getBounds();
					bounds.extend(bounds2.getSouthWest());
					bounds.extend(bounds2.getNorthEast());
				}*/
				var _zoom = M.map.getZoomLevelForBoundingBox(bounds);
				setTimeout(function(){
						//M.map.setCenterAndZoom(bounds.getCenter(), _zoom);
						}, 1000);
				//M.map.setCenterAndZoom(bounds.getCenter(), _zoom);
			}

			return errors;

	},  //end addLayers

	getImageLayerExtent: function(extent){
		if(extent && typeof(extent)=='string'){
			var coords = extent.split(',');
			return new mxn.BoundingBox(coords[1], coords[0], coords[3], coords[2]);
		}else if (typeof(extent)=='mxn.BoundingBox') {
			return extent;
		}else{
			return null;
		}
	},

	timeMidPoint: function (start, end) {
			var d, diff;
			d = new Date();
			diff = end.getTime() - start.getTime();
			d.setTime(start.getTime() + diff/2);
			return d;
	},

	findTimeScale: function (start, end, scales, timelineWidth) {
			var diff, span, unitLength, intervals, i;
			s = new Date();
			e = new Date();
			diff = end.getTime() - start.getTime();
			span = diff * 1.1;	// pad by 5% each end
			for (i = 0; i < scales.length; ++i) {
				unitLength = Timeline.DateTime.gregorianUnitLengths[scales[i].unit];
				intervals = timelineWidth / scales[i].pixelsPerInterval;
				if (span / unitLength <= intervals) {
					return i;
				}
			}
			return i;
	}
};

	// default to 100px per century
	RelBrowser.Mapping.initTimeZoomIndex = RelBrowser.Mapping.timeZoomSteps.length - 1;

	// returns error string in case wrong image layer definition
	function initMapping(map_div_id) {
		return RelBrowser.Mapping.initMap(map_div_id);
		/*
		var mini, $img, M = RelBrowser.Mapping;

		mini = M.mapdata.mini || false;
		$img = $("img.entity-picture");
		if ($img.length > 0  &&  $img.width() === 0) {
			$img.load(function () { M.initMap(mini); });
			return '';
		} else {
			return M.initMap(mini);
		}
		*/
	}

}
