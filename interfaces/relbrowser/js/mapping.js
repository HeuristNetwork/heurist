
var zoomSteps = [
	{ pixelsPerInterval: 100,  unit: Timeline.DateTime.HOUR },	// hours are weird
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
	{ pixelsPerInterval: 200,  unit: Timeline.DateTime.MILLENNIUM},
	{ pixelsPerInterval: 100,  unit: Timeline.DateTime.MILLENNIUM}
	// default zoomIndex
];
var initZoomIndex = zoomSteps.length - 1;

var mapTypes = [
	G_NORMAL_MAP,
	G_SATELLITE_MAP,
	G_PHYSICAL_MAP,
];


function renderMapTypeList() {
	var div = document.getElementById("map-types");
	if (! div) return;
	div.innerHTML = "<h4>Available map layers:</h4>";

	for (var m in mapTypes) {
		(function(m) {
			$("<a href='#' class='map-type'>" + mapTypes[m].getName()  + "</a>")
				.click(function() {
					$(".map-type.selected").removeClass("selected");
					$(this).addClass("selected");
					window.tmap.map.setMapType(mapTypes[m]);
					return false;
				})
				.appendTo($("<div>").appendTo(div));
		})(m);
	}

	if (mapTypes.length > 3) {
		$("<input type='checkbox'></input>")
			.change(function() {
				var opacity = this.checked ? 0.7 : 1;
				for (var i = 3; i < mapTypes.length; ++i) {
					mapTypes[i].getTileLayers()[1].opacity = opacity;
				}
				var mapType = tmap.map.getCurrentMapType();
				tmap.map.setMapType(mapTypes[0]);
				tmap.map.setMapType(mapType);
			})
			.appendTo($("<div>").appendTo(div)).after(" transparent");
	}
}

function renderTimelineZoom() {
	var div = document.getElementById("timeline-zoom");
	if (! div) return;

	var zoom = function(zoomIn) {
		var band = tmap.timeline.getBand(0);
		var x = ($(tmap.tElement).width() / 2) - band.getViewOffset();
		band.zoom(zoomIn, x);
		tmap.timeline.paint();
	};

	$("<a href='#'>narrower time range</a> - ")
		.click(function() {
			zoom(true);
			return false;
		})
		.appendTo($("<div>").appendTo(div));
	$("<a href='#'>wider time range</a>")
		.click(function() {
			zoom(false);
			return false;
		})
		.appendTo($("<div>").appendTo(div));
}

function initTMap(mini) {
	SimileAjax.History.enabled = false;

	if (window.mapdata["layers"]) {
		addLayers();
	}

	renderTimelineZoom();
	renderMapTypeList();

	// modify timeline theme
	var tl_theme = Timeline.ClassicTheme.create();
	//tl_theme.mouseWheel = "zoom";
	tl_theme.autoWidth = true;

	// modify preset timemap themes
	var opts = { eventIconPath: "http://heuristscholar.org/timemap.js/images/" };
	TimeMapDataset.themes = {
		'red': TimeMapDataset.redTheme(opts),
		'blue': TimeMapDataset.blueTheme(opts),
		'green': TimeMapDataset.greenTheme(opts),
		'ltblue': TimeMapDataset.ltblueTheme(opts),
		'orange': TimeMapDataset.orangeTheme(opts),
		'yellow': TimeMapDataset.yellowTheme(opts),
		'purple': TimeMapDataset.purpleTheme(opts)
	};

	var onDataLoaded = function(tm) {
		// find centre date, choose scale to show entire dataset
		var d = new Date();
		var eventSource = tm.timeline.getBand(0).getEventSource();
		if (eventSource.getCount() > 0) {
			var start = eventSource.getEarliestDate();
			var end = eventSource.getLatestDate();
			d = midPoint(start, end);

			var zoomIndex = findScale(start, end, zoomSteps, ($(tm.tElement).width()));

			var changeScale = function(bandIndex) {
				var band = tm.timeline.getBand(bandIndex);
				var interval = zoomSteps[zoomIndex].unit;
				band._zoomIndex = zoomIndex;
				band.getEther()._pixelsPerInterval = zoomSteps[zoomIndex].pixelsPerInterval;
				band.getEther()._interval = Timeline.DateTime.gregorianUnitLengths[interval];
				band.getEtherPainter()._unit = interval;
			};
			changeScale(0);
			changeScale(1);
		}
		tm.timeline.getBand(0).setCenterVisibleDate(d);
		tm.timeline.layout();
	};

	window.tmap = TimeMap.init({
		mapId: "map", // Id of map div element (required)
		timelineId: "timeline", // Id of timeline div element (required)
		datasets: window.mapdata.timemap,
		options: {
			showMapCtrl: ! mini,
			showMapTypeCtrl: false,
			mapTypes: mapTypes,
			mapType: mapTypes.length > 3 ? mapTypes[3] : mapTypes[0],
			theme: TimeMapDataset.themes.blue
		},
		bandInfo: [ {
			theme: tl_theme,
			showEventText: true,
			intervalUnit: zoomSteps[initZoomIndex].unit,
			intervalPixels: zoomSteps[initZoomIndex].pixelsPerInterval,
            zoomIndex: initZoomIndex,
            zoomSteps: zoomSteps,
			}, {
			overview: true,
			theme: tl_theme,
			showEventText: false,
			intervalUnit: zoomSteps[initZoomIndex].unit,
			intervalPixels: zoomSteps[initZoomIndex].pixelsPerInterval,
			trackHeight: 10,
			trackGap: 0.2
		} ],
		dataLoadedFunction: onDataLoaded
	});

	if (mini) {
		tmap.map.addControl(new GSmallMapControl());
	}
}

function tileToQuadKey (x, y, zoom) {
	var i, mask, cell, quad = "";
	for (i = zoom; i > 0; i--) {
		mask = 1 << (i - 1);
		cell = 0;
		if ((x & mask) != 0) cell++;
		if ((y & mask) != 0) cell += 2;
		quad += cell;
	}
	return quad;
}

function addLayers() {
	for (var i = 0; i < window.mapdata.layers.length; ++i) {
		(function(layer) {

			var newLayer = new GTileLayer(new GCopyrightCollection(""),layer.min_zoom, layer.max_zoom);
			// tileToQuadKey only for "virtual earth" maps!
			newLayer.getTileUrl = function (a,b) {
				return layer.url + tileToQuadKey(a.x,a.y,b) + (layer.mime_type == "image/png" ? ".png" : ".gif");
			};
			newLayer.getCopyright = function(a,b) { return layer.copyright; };
			newLayer.isPng = function() { return layer.mime_type == "image/png"; };
			newLayer.getOpacity = function() { return this.opacity; };
			newLayer.opacity = 1;

			var newMapType = new GMapType([G_NORMAL_MAP.getTileLayers()[0], newLayer], G_NORMAL_MAP.getProjection(), layer.title);

			mapTypes.push(newMapType);

		})(window.mapdata.layers[i]);
	}
}

function midPoint(start, end) {
	var d, diff;
	d = new Date();
	diff = end.getTime() - start.getTime();
	d.setTime(start.getTime() + diff/2);
	return d;
}

function findScale(start, end, scales, timelineWidth) {
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
