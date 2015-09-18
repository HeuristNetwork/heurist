/*
* Copyright (C) 2005-2015 University of Sydney
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
* Class to work with gmap v.2
*
* @author      Kim Jackson
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/
var G = google.maps;

if (typeof G.LatLngBounds == "function") {
	if (! window.RelBrowser) { window.RelBrowser = {baseURL: "../"}; }

	// FIXME: make JSLint-happy

	window.RelBrowser.Mapping = {

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
			{ pixelsPerInterval: 100,  unit: Timeline.DateTime.CENTURY }
		] : [],

		customMapTypes: [],


		addCustomMapTypeControl: function () {
			var CustomMapTypeControl = function () {}, M = (RelBrowser.Mapping ? RelBrowser.Mapping : DOS.rb.Mapping);

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
				var i, mapType, M = (RelBrowser.Mapping ? RelBrowser.Mapping : DOS.rb.Mapping);
				for (i = 0; i < M.customMapTypes.length; ++i) {
					M.customMapTypes[i].getTileLayers()[1].opacity = opacity;
				}
				mapType = M.map.getCurrentMapType();
				M.map.setMapType(G_NORMAL_MAP);
				M.map.setMapType(mapType);
			};

			RelBrowser.Mapping.map.addControl(new CustomMapTypeControl());
		},


	renderTimelineZoom: function () {
		var band, zoom, x, $div, M = (RelBrowser.Mapping ? RelBrowser.Mapping : DOS.rb.Mapping);

		$div = $("#timeline-zoom");

		if ($div.length < 1) { return; }

		zoom = function (zoomIn) {
			band = M.tmap.timeline.getBand(0);
			x = ($(M.tmap.tElement).width() / 2) - band.getViewOffset();
			band.zoom(zoomIn, x);
			M.tmap.timeline.paint();
		};
		$("<div title='Zoom In'><img src='"+RelBrowser.baseURL+"images/plus.png'></img></div>")
			.click(function () {
				zoom(true);
			})
			.appendTo($div);
		$("<div title='Zoom Out'><img src='"+RelBrowser.baseURL+"images/minus.png'></img></div>")
			.click(function () {
				zoom(false);
			})
			.appendTo($div);
	},


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
				this.placemark.openInfoWindow(content);
			} else {
				this.map.openInfoWindow(this.getInfoPoint(), content);
			}
			// custom functions will need to set this as well
			this.selected = true;
		},


		initMap: function (mini) {
			var matches, coords, points, l, i, lnglat, bounds = null, M = (RelBrowser.Mapping ? RelBrowser.Mapping : DOS.rb.Mapping);

			if (M.mapdata["layers"]) {
				M.addLayers();
			}

			if (M.mapdata.focus) {
				matches = M.mapdata.focus.match(/^POLYGON\(\((.*)\)\)$/)
				if (matches) {
					coords = matches[1].split(",");
					points = [];
					l = coords.length;
					for (i = 0; i < l; ++i) {
						lnglat = coords[i].split(" ");
						if (lnglat.length === 2) {
							points.push(new GLatLng(lnglat[1], lnglat[0]));
						}
					}
					if (points.length > 0) {
						bounds = new GLatLngBounds(points[0], points[0]);
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
				M.map = new GMap2($("#map")[0]);
				if (bounds) {
					M.map.setCenter(bounds.getCenter(), M.map.getBoundsZoomLevel(bounds));
				} else {
					M.map.setCenter(M.defaultCenter, M.defaultZoom);
				}
				M.map.setMapType(M.customMapTypes[0] || G_NORMAL_MAP);
			}

			M.map.setUIToDefault();
			if (! mini) {
				M.addCustomMapTypeControl();
			}
		},


		initTMap: function (mini, bounds) {
			var tl_theme, onDataLoaded, M = (RelBrowser.Mapping ? RelBrowser.Mapping : DOS.rb.Mapping);

			SimileAjax.History.enabled = false;

			// modify timeline theme
			tl_theme = Timeline.ClassicTheme.create();
			tl_theme.autoWidth = true;

			onDataLoaded = function (tm) {
				// find centre date, choose scale to show entire dataset
				var start, end, zoomIndex, changeScale, eventSource, d = new Date();

				eventSource = tm.timeline.getBand(0).getEventSource();
				if (eventSource.getCount() > 0) {
					start = eventSource.getEarliestDate();
					end = eventSource.getLatestDate();
					d = M.timeMidPoint(start, end);

					zoomIndex = M.findTimeScale(start, end, M.timeZoomSteps, ($(tm.tElement).width()));

					changeScale = function (bandIndex) {
						var band, interval;
						band = tm.timeline.getBand(bandIndex),
						interval = M.timeZoomSteps[zoomIndex].unit;
						band._zoomIndex = zoomIndex;
						band.getEther()._pixelsPerInterval = M.timeZoomSteps[zoomIndex].pixelsPerInterval;
						band.getEther()._interval = Timeline.DateTime.gregorianUnitLengths[interval];
						band.getEtherPainter()._unit = interval;
					};
					changeScale(0);
				}
				tm.timeline.getBand(0).setCenterVisibleDate(d);
				tm.timeline.layout();

				// finally, draw the zoom control
				M.renderTimelineZoom();

				if (bounds) {
					tm.map.setCenter(bounds.getCenter(), tm.map.getBoundsZoomLevel(bounds));
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
					if (mapables == 1 && markers == 1){
						tm.map.setZoom(tm.opts.mapZoom);
					} else if (tm.mapBounds){
						tm.map.setCenter(tm.mapBounds.getCenter(), tm.map.getBoundsZoomLevel(tm.mapBounds));
					}
				}

			};

			M.tmap = TimeMap.init({
				mapId: "map", // Id of map div element (required)
				timelineId: "timeline", // Id of timeline div element (required)
				datasets: M.mapdata.timemap,
				options: {
					showMapCtrl: false,
					showMapTypeCtrl: false,
					mapZoom: RelBrowser.Mapping.defaultZoom,
					centerMapOnItems: bounds ? false : true,
					mapType: M.customMapTypes[0] || G_NORMAL_MAP,
					theme: TimeMap.themes.blue({ eventIconPath: RelBrowser.baseURL + "timemap.js/images/" }),
					openInfoWindow: mini ? function () { return false; } : RelBrowser.Mapping.openInfoWindowHandler
				},
				bandInfo: [ {
					theme: tl_theme,
					showEventText: true,
					intervalUnit: M.timeZoomSteps[M.initTimeZoomIndex].unit,
					intervalPixels: M.timeZoomSteps[M.initTimeZoomIndex].pixelsPerInterval,
					zoomIndex: M.initTimeZoomIndex,
					zoomSteps: M.timeZoomSteps,
					width: "100%"
				} ],
				dataLoadedFunction: onDataLoaded
			});

			M.map = M.tmap.map;
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
			var i, M = (RelBrowser.Mapping ? RelBrowser.Mapping : DOS.rb.Mapping);
			for (i = 0; i < M.mapdata.layers.length; ++i) {
				(function (layer) {
					var newLayer, newMapType;
					newLayer = new GTileLayer(new GCopyrightCollection(""), layer.min_zoom, layer.max_zoom);
					if (layer.type === "virtual earth") {
						newLayer.getTileUrl = function (a,b) {
							return layer.url + M.tileToQuadKey(a.x,a.y,b) + (layer.mime_type == "image/png" ? ".png" : ".gif");
						};
					} else if (layer.type === "maptiler") {
						newLayer.getTileUrl = function (a,b) {
							var y = (1 << b) - a.y - 1;
							return layer.url + b + "/" + a.x + "/" + y + (layer.mime_type == "image/png" ? ".png" : ".gif");
						};
					}
					newLayer.getCopyright = function (a,b) { return layer.copyright; };
					newLayer.isPng = function () { return layer.mime_type == "image/png"; };
					newLayer.getOpacity = function () { return this.opacity; };
					newLayer.opacity = 1;

					newMapType = new GMapType([G_NORMAL_MAP.getTileLayers()[0], newLayer], G_NORMAL_MAP.getProjection(), layer.title);

					M.customMapTypes.push(newMapType);

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

	// changed to ensure orderred startup SAW 2/2/12
	initialiseMapping = function () {
		var mini, $img, M = RelBrowser.Mapping;
		DOS.rb = RelBrowser;	// Add to ensure context is passed currently startup is somehow resetting the context.
		mini = M.mapdata.mini || false;
		$img = $("img.entity-picture");
		if ($img.length > 0  &&  $img.width() === 0) {
			$img.load(function () { M.initMap(mini); });
		} else {
			M.initMap(mini);
		}
	};
}
