if (typeof mxn.LatLonPoint == "function") {

	//art
	if (! window.RelBrowser) { RelBrowser = {}; }

	//v1 FIXME: make JSLint-happy

	RelBrowser.Mapping = {

		map: null,
		tmap: null,

		defaultCenter: new mxn.LatLonPoint(-33.888, 151.19),
		defaultZoom: 14,

		timeZoomSteps : window["Timeline"] ? [
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

	changeScale: function (bandIndex, zoomIndex, tm) {
			var M = RelBrowser.Mapping;
			if(!tm) tm = M.tmap;

			if(zoomIndex<0) {
				zoomIndex=0;
			} else if (zoomIndex>=M.timeZoomSteps.length){
				zoomIndex = M.timeZoomSteps.length-1;
			}
						var band, interval;
						band = tm.timeline.getBand(bandIndex),
						interval = M.timeZoomSteps[zoomIndex].unit;
						band._zoomIndex = zoomIndex;
						band.getEther()._pixelsPerInterval = M.timeZoomSteps[zoomIndex].pixelsPerInterval;
						band.getEther()._interval = Timeline.DateTime.gregorianUnitLengths[interval];
						band.getEtherPainter()._unit = interval;

	},

	renderTimelineZoom: function () {

		var $div = $("#divZoomOut");
		if ($div.length > 0) { return; } //already defined

		$div = $("#timeline-zoom");
		if ($div.length < 1) { return; }

		var zoom, x, M = RelBrowser.Mapping;

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
			var newzoom = band._zoomIndex + (zoomIn?-1:1);
			M.changeScale(1, newzoom+3);
			M.changeScale(0, newzoom);*/

			/*
			band.zoom(zoomIn, x);*/
			M.tmap.timeline.paint();
		};
		$("<div id='divZoomIn' title='Zoom In'><img src='"+RelBrowser.baseURL+"common/images/plus.png'></img></div>")
			.click(function () {
				zoom(true);
			})
			.appendTo($div);
		$("<div  id='divZoomOut' title='Zoom Out'><img src='"+RelBrowser.baseURL+"common/images/minus.png'></img></div>")
			.click(function () {
				zoom(false);
			})
			.appendTo($div);
	},

		// information window
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
		initMap: function (mini) {
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

			if (window["TimeMap"]) {
				M.initTMap(mini, bounds);
			} else {

				M.map = new mxn.Mapstraction($("#map")[0]);
				if (bounds) {
					M.map.setCenterAndZoom(bounds.getCenter(), M.map.getZoomLevelForBoundingBox(bounds));
				} else {
					M.map.setCenterAndZoom(M.defaultCenter, M.defaultZoom);
				}
				M.map.setMapType(M.customMapTypes[0] || mxn.Mapstraction.ROAD);

			}

			//tiled images
			M.map.removeAllTileLayers();
			if (M.mapdata["layers"]) {
				M.addLayers();
			}

			//???? M.map.setUIToDefault();
			if (! mini) {
				M.addCustomMapTypeControl();
			}

		},

		initTMap: function (mini, bounds) {
			var tl_theme, tl_theme2, onDataLoaded, M = RelBrowser.Mapping;

			//???? SimileAjax.History.enabled = false;

			// modify timeline theme
			tl_theme = Timeline.ClassicTheme.create();
			tl_theme.autoWidth = true;
			tl_theme.mouseWheel = "default";//"zoom";
			//tl_theme.event.bubble.maxHeight = 0;
			//tl_theme.event.bubble.width = 320;

			tl_theme2 = Timeline.ClassicTheme.create();
			tl_theme2.autoWidth = true;
			tl_theme2.ether.backgroundColors = [ "#D1CECA", "#e3c5a6", "#E8E8F4", "#D0D0E8"];
			//tl_theme2.event.tape.height = 3;



			onDataLoaded = function (tm) {
				// find centre date, choose scale to show entire dataset
				var start, end, zoomIndex, eventSource, d = new Date();
				var M = RelBrowser.Mapping;

				eventSource = tm.timeline.getBand(0).getEventSource();
				if (eventSource.getCount() > 0) {
					start = eventSource.getEarliestDate();
					end = eventSource.getLatestDate();
					d = M.timeMidPoint(start, end);

					zoomIndex = M.findTimeScale(start, end, M.timeZoomSteps, ($(tm.tElement).width()));

					M.changeScale(1, zoomIndex + 3, tm);
					M.changeScale(0, zoomIndex, tm);
				}
				tm.timeline.getBand(0).setCenterVisibleDate(d);
				tm.timeline.layout();

				// finally, draw the zoom control
				M.renderTimelineZoom();

				if (bounds) {
					tm.map.setCenterAndZoom(bounds.getCenter(), tm.map.getZoomLevelForBoundingBox(bounds));
				} else {
					var mapables = 0, markers = 0;
					for (var i = 0; i < tm.datasets.ds0.items.length; i++) {
						var type = tm.datasets.ds0.items[i].getType();
						if ( type == "none") {
							continue;
						} else if (type  == "marker"){
							markers++;
						}
						mapables++
					}

					//if (mapables == 1 && markers == 1){	tm.map.setZoom(tm.opts.mapZoom);
					if (markers>0 && tm.mapBounds && tm.mapBounds.sw){
						tm.map.setCenterAndZoom(tm.mapBounds.getCenter(), tm.map.getZoomLevelForBoundingBox(tm.mapBounds));
					}else{
						tm.map.setZoom(tm.opts.mapZoom);
					}
				}
			};

			var myLatlng = new mxn.LatLonPoint(-33.87, 151.20);
//				     mapZoom: 13,
// 					 mapCenter: myLatlng,

			M.tmap = TimeMap.init({
				mapId: "map", // Id of map div element (required)
				timelineId: "timeline", // Id of timeline div element (required)
				datasets: M.mapdata.timemap,
				options: {
					mapZoom: 2,
					onlyTimeline: (M.mapdata.count_mapobjects<1),
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
					{
					theme: tl_theme,
					showEventText: true,
					intervalUnit: M.timeZoomSteps[M.initTimeZoomIndex].unit,
					intervalPixels: M.timeZoomSteps[M.initTimeZoomIndex].pixelsPerInterval,
					zoomIndex: M.initTimeZoomIndex,
					zoomSteps: M.timeZoomSteps,
					trackHeight:    1.2,
					width: "80%"
					},
					{
					theme: tl_theme2,
					showEventText: false,
					intervalUnit: M.timeZoomSteps[M.initTimeZoomIndex-1].unit,
					intervalPixels: M.timeZoomSteps[M.initTimeZoomIndex-1].pixelsPerInterval,
					zoomIndex: M.initTimeZoomIndex-1,
					zoomSteps: M.timeZoomSteps,
					trackHeight:    0.2,
					trackGap:       0.1,
					width: "20%",
					layout:'overview'
					}
					],
				dataLoadedFunction: onDataLoaded
			}, M.tmap);

			M.map = M.tmap.map; //background map - gmap or other
		},

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

		addLayers: function () {
			var i, M = RelBrowser.Mapping;
			for (i = 0; i < M.mapdata.layers.length; ++i) {
				(function (layer) {

					var tile_url;
					if (layer.type === "virtual earth") {
						tile_url = function (a,b) {
							var res = layer.url + M.tileToQuadKey(a.x,a.y,b) + (layer.mime_type == "image/png" ? ".png" : ".gif");
							return res;
						};
					} else if (layer.type === "maptiler") {
						if(layer.url.charAt(layer.url.length-1)!=="/"){
							layer.url = layer.url + "/";
						}
						tile_url =	function(tile,zoom) {
			              if ((zoom < 1) || (zoom > 19)) {
			                  return "http://www.maptiler.org/img/none.png";
			              }
			              var ymax = 1 << zoom;
			              var y = ymax - tile.y -1;
			              if (true) { //(mapBounds.intersects(tileBounds)) {
			              		var surl = layer.url+zoom+"/"+tile.x+"/"+y+".png";
			                  return surl;
			              } else {
			                  return "http://www.maptiler.org/img/none.png";
			              }
			          };
					}
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
					if(layer.max_zoom>19){ layer.max_zoom = 19; }

					M.map.addTileLayer(tile_url, 0.75, layer.rec_ID, layer.min_zoom, layer.max_zoom, true);
					//layer.min_zoom, layer.max_zoom, true);
				}


				})(M.mapdata.layers[i]);
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

	function initMapping() {
		var mini, $img, M = RelBrowser.Mapping;
		mini = M.mapdata.mini || false;
		$img = $("img.entity-picture");
		if ($img.length > 0  &&  $img.width() === 0) {
			$img.load(function () { M.initMap(mini); });
		} else {
			M.initMap(mini);
		}
	}
}
