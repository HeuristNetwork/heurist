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
* Geographic Object Interface (GOI) for Heurist API  v0.1 
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

var GOI = {};

if (! window.HAPI /* jan! */) { throw "hapi.js must be included BEFORE goi.js"; }
HAPI.GOI = GOI;

GOI.getVersion = function() { return "0.1"; };


var HInvalidGeographicValueException = function(text) { HException.call(this, text, "HInvalidGeographicValueException"); };
GOI.InvalidGeographicValueException = HInvalidGeographicValueException;
HAPI.inherit(HInvalidGeographicValueException, HException);


var _r = function(x) { return Math.round(x*10) / 10; };


var HPointValue = function() {
	var x, y;
	var xy;
	var wkt;

	if (arguments.length === 1) {	// assume WKT
		wkt = arguments[0];
		if (!wkt || ! (xy = wkt.match(/POINT[(]([^ ]+) ([^ ]+)[)]/))) {
			throw new HInvalidGeographicValueException("invalid point data");
		}
		x = parseFloat(xy[1]);
		y = parseFloat(xy[2]);
		this.constructor = HPointValue;
	}
	else if (arguments.length === 2) {	// x, y
		x = parseFloat(arguments[0]);
		y = parseFloat(arguments[1]);

		HGeographicValue.call(this, "p", "POINT(" + x + " " + y +")");
	}
	else { throw new HInvalidGeographicValueException("invalid point data"); }

	this.getX = function() { return x; };
	this.getY = function() { return y; };
	this.toString = function() { return "Point: X (" + _r(x) + ") - Y (" + _r(y) + ")"; };
};
HAPI.inherit(HPointValue, HGeographicValue);
HPointValue.getClass = function() { return "HPointValue"; };
GOI.PointValue = HPointValue;

var HBoundsValue = function() {
	var xMin, xMax;
	var yMin, yMax;
	var coords;

	if (arguments.length === 1) {	// assume WKT
		wkt = arguments[0];
		if (! (coords = wkt.match(/POLYGON[(][(]([^ ]+) ([^,]+),([^ ]+) ([^,]+),([^ ]+) ([^,]+),([^ ]+) ([^)]+)[)][)]/))) {
			throw new HInvalidGeographicValueException("invalid rectangle data");
		}
		xMin = parseFloat(coords[1]);
		yMin = parseFloat(coords[2]);
		xMax = parseFloat(coords[5]);
		yMax = parseFloat(coords[6]);
		this.constructor = HBoundsValue;
	}
	else if (arguments.length === 4) {
		xMin = parseFloat(arguments[0]);
		yMin = parseFloat(arguments[1]);
		xMax = parseFloat(arguments[2]);
		yMax = parseFloat(arguments[3]);

		HGeographicValue.call(this, "r", "POLYGON(("+xMin+" "+yMin+","+xMax+" "+yMin+","+xMax+" "+yMax+","+xMin+" "+yMax+","+xMin+" "+yMin+"))");
	}
	else { throw new HInvalidGeographicValueException("invalid rectangle data"); }

	this.getX0 = function() { return xMin; };
	this.getX1 = function() { return xMax; };
	this.getY0 = function() { return yMin; };
	this.getY1 = function() { return yMax; };
	this.toString = function() { return "Rectangle: X (" + _r(xMin) + "," + _r(xMax) + ") - Y (" + _r(yMin) + "," + _r(yMax) + ")"; };
};
HAPI.inherit(HBoundsValue, HGeographicValue);
HBoundsValue.getClass = function() { return "HBoundsValue"; };
GOI.BoundsValue = HBoundsValue;

var HCircleValue = function() {
	var x, y, radius;
	var coords;

	if (arguments.length === 1) {	// assume WKT
		wkt = arguments[0];
		if (! (coords = wkt.match(/LINESTRING[(]([^ ]+) ([^,]+),([^ ]+) /))) {
			throw new HInvalidGeographicValueException("invalid circle data");
		}
		x = parseFloat(coords[1]);
		y = parseFloat(coords[2]);
		radius = parseFloat(coords[3]) - x;
		this.constructor = HCircleValue;
	}
	else if (arguments.length == 3) {	// x, y, radius
		x = parseFloat(arguments[0]);
		y = parseFloat(arguments[1]);
		radius = parseFloat(arguments[2]);

		HGeographicValue.call(this, "c", "LINESTRING("+x+" "+y+","+(x+radius)+" "+y+","+(x-radius)+" "+(y-radius)+","+(x-radius)+" "+(y+radius)+")");
	}

	this.getX = function() { return x; };
	this.getY = function() { return y; };
	this.getRadius = function() { return radius; };
	this.toString = function() { return "Circle: X (" + _r(x-radius)+","+_r(x+radius) + ") - Y (" + _r(y-radius)+","+_r(y+radius)+")"; };
};
HAPI.inherit(HCircleValue, HGeographicValue);
HCircleValue.getClass = function() { return "HCircleValue"; };
GOI.CircleValue = HCircleValue;

var HPolygonValue = function() {
	var points = [];
	var minX, maxX, minY, maxY;

	var xy;
	var wkt, pointData;
	var i;

	if (arguments.length === 1  &&  typeof(arguments[0]) === "string") {	// assume WKT
		wkt = arguments[0];
		if (! (pointData = wkt.match(/POLYGON[(][(](.+)[)][)]/))) {
			throw new HInvalidGeographicValueException("invalid polygon data");
		}
		pointData = pointData[1].split(/,/);
		for (i=0; i < pointData.length; ++i) {
			xy = pointData[i].split(/ /);
			points.push([parseFloat(xy[0]), parseFloat(xy[1])]);
		}
		this.constructor = HPolygonValue;
	}
	else if (arguments.length === 1  &&  typeof(arguments[0]) === "object"  &&  arguments[0].length > 2) {
		if (arguments[0][0].x !== undefined) {
			// arguments[0] is a list of { x; X, y: Y } objects
			for (i=0; i < arguments[0].length; ++i) {
				points.push([ arguments[0][i].x, arguments[0][i].y ]);
			}
		}
		else {
			points = arguments[0].slice(0);
		}
		wkt = [];
		for (i=0; i < points.length; ++i) {
			wkt.push(points[i].join(" "));
		}
		wkt = "POLYGON((" + wkt.join(",") + "))";
		HGeographicValue.call(this, "pl", wkt);
	}
	else { throw new HInvalidGeographicValueException("invalid polygon data"); }

	minX = maxX = points[0][0];
	minY = maxY = points[0][1];
	for (i=1; i < points.length; ++i) {
		if (points[i][0] < minX) { minX = points[i][0]; }
		if (points[i][1] < minY) { minY = points[i][1]; }
		if (points[i][0] > maxX) { maxX = points[i][0]; }
		if (points[i][1] > maxY) { maxY = points[i][1]; }
	}

	// check that the first and last points are the same (to within a margin of error)
	if (Math.abs(points[points.length-1][0] - points[0][0]) > 0.0001  ||  Math.abs(points[points.length-1][1] - points[0][1]) > 0.0001) {
		throw new HInvalidGeographicValueException("invalid polygon data");
	}

	this.getPoints = function() { return points.slice(0); };
	this.toString = function() { return "Polygon: X ("+minX+","+maxX+") - Y ("+minY+","+maxY+")"; };
};
HAPI.inherit(HPolygonValue, HGeographicValue);
HPolygonValue.getClass = function() { return "HPolygonValue"; };
GOI.PolygonValue = HPolygonValue;

var HPathValue = function() {
	var points = [];

	var xy;
	var wkt, pointData;
	var i;

	if (arguments.length === 1  &&  typeof(arguments[0]) === "string") {	// assume WKT
		wkt = arguments[0];
		if (! (pointData = wkt.match(/LINESTRING[(](.+)[)]/))) {
			throw new HInvalidGeographicValueException("invalid path data");
		}
		pointData = pointData[1].split(/,/);
		for (i=0; i < pointData.length; ++i) {
			xy = pointData[i].split(/ /);
			points.push([parseFloat(xy[0]), parseFloat(xy[1])]);
		}
		this.constructor = HPathValue;
	}
	else if (arguments.length === 1  &&  typeof(arguments[0]) === "object"  &&  arguments[0].length > 1) {
		if (arguments[0][0].x !== undefined) {
			// arguments[0] is a list of { x; X, y: Y } objects
			for (i=0; i < arguments[0].length; ++i) {
				points.push([ arguments[0][i].x, arguments[0][i].y ]);
			}
		}
		else {
			points = arguments[0].slice(0);
		}
		wkt = [];
		for (i=0; i < points.length; ++i) {
			wkt.push(points[i].join(" "));
		}
		wkt = "LINESTRING(" + wkt.join(",") + ")";
		HGeographicValue.call(this, "l", wkt);
	}
	else { throw new HInvalidGeographicValueException("invalid path data"); }

	this.getPoints = function() { return points.slice(0); };
	this.toString = function() { return "Path: X,Y ("+_r(points[0][0])+","+_r(points[0][1])+") - ("+_r(points[points.length-1][0])+","+_r(points[points.length-1][1])+")"; };
};
HAPI.inherit(HPathValue, HGeographicValue);
HPathValue.getClass = function() { return "HPathValue"; };
GOI.PathValue = HPathValue;

GOI.Digitiser = function(map) {
	/* PRE */ if (! map  ||  ! map.addOverlay) { throw new HTypeException("Google Map object required"); }
	var _map = map;
	var _dblclickHandle = null;
	var _shape = null;

	this.getMap = function() { return _map; };

	this.digitisePoint = function(onstartCallback) { _digitise(_initPoint, onstartCallback); };
	this.digitiseBounds = function(onstartCallback) { _digitise(_initBounds, onstartCallback); };
	this.digitiseCircle = function(onstartCallback) { _digitise(_initCircle, onstartCallback); };
	this.digitisePolygon = function(onstartCallback) { _digitise(_initPolygon, onstartCallback); };
	this.digitisePath = function(onstartCallback) { _digitise(_initPath, onstartCallback); };

	this.getShape = function() {
		if (! _shape) return null;
		switch (_shape.type) {
			case HGeographicType.POINT:
				return new HPointValue(_shape.position.lng(), _shape.position.lat());
				break;

			case HGeographicType.BOUNDS:
				var west = Math.min(_shape.corner1.lng(), _shape.corner2.lng());
				var east = Math.max(_shape.corner1.lng(), _shape.corner2.lng());
				var north = Math.max(_shape.corner1.lat(), _shape.corner2.lat());
				var south = Math.min(_shape.corner1.lat(), _shape.corner2.lat());
				return new HBoundsValue(west, south, east, north);
				break;

			case HGeographicType.CIRCLE:
				return new HCircleValue(_shape.centre.lng(), _shape.centre.lat(), _shape.radius);
				break;

			case HGeographicType.POLYGON:
				var points = [];
				for (var i=0; i < _shape.vertices.length; ++i)
					points.push( { x: _shape.vertices[i].lng(), y: _shape.vertices[i].lat() } );
				points.push( { x: _shape.vertices[0].lng(), y: _shape.vertices[0].lat() } );	// close the polygon
				return new HPolygonValue(points);
				break;

			case HGeographicType.PATH:
				var points = [];
				for (var i=0; i < _shape.vertices.length; ++i)
					points.push( { x: _shape.vertices[i].lng(), y: _shape.vertices[i].lat() } );
				return new HPathValue(points);
		}
	};

	this.edit = function (geoValue, geoType) {
		if (! geoValue) {
			return;
		}
		if (HAPI.isA(geoValue, "HGeographicValue")) {
			geoType = geoValue.getType();
		}
		_map.clearOverlays();
		switch (geoType) {
		case HGeographicType.POINT:
			_editPoint(geoValue);
			break;
		case HGeographicType.BOUNDS:
			_editBounds(geoValue);
			break;
		case HGeographicType.CIRCLE:
			_editCicle(geoValue);
			break;
		case HGeographicType.POLYGON:
			_editPolygon(geoValue);
			break;
		case HGeographicType.PATH:
			_editPath(geoValue);
		}
	};

	/* private */ function _editPoint (value) {
		var point;
		if (! HAPI.isA(value, "HPointValue")) {
			value = new HPointValue(value);
		}
		point = new GLatLng(value.getY(), value.getX());
		_initPoint(point);
		_map.setCenter(point);
	}

	/* private */ function _editBounds (value) {
		var point1, point2;
		if (! HAPI.isA(value, "HBoundsValue")) {
			value = new HBoundsValue(value);
		}
		point1 = new GLatLng(value.getY0(), value.getX0());
		point2 = new GLatLng(value.getY1(), value.getX1());
		_initBounds(point1, point2);
		_map.setCenter(point1);
	}

	/* private */ function _editCicle (value) {
		var center, radius;
		if (! HAPI.isA(value, "HCircleValue")) {
			value = new HCircleValue(value);
		}
		center = new GLatLng(value.getY(), value.getX());
		radius = value.getRadius();
		_initCircle(center, radius);
		_map.setCenter(center);

	}

	/* private */ function _editPolygon (value) {
		var l, i, points, gpoints = [];
		if (! HAPI.isA(value, "HPolygonValue")) {
			value = new HPolygonValue(value);
		}
		points = value.getPoints();
		l = points.length;
		for (i = 0; i < l; i++) {
			gpoints[i] = new GLatLng(points[i][1], points[i][0]);
		}
		_initPolygon(gpoints);
		map.setCenter(new GLatLng(points[0][1], points[0][0]));
	}

	/* private */ function _editPath (value) {
		var l, i, points, gpoints = [];
		if (! HAPI.isA(value, "HPathValue")) {
			value = new HPathValue(value);
		}
		points = value.getPoints();
		l = points.length;
		for (i = 0; i < l; i++) {
			gpoints[i] = new GLatLng(points[i][1], points[i][0]);
		}
		_initPath(gpoints);
		map.setCenter(new GLatLng(points[0][1], points[0][0]));
	}

	/* private */ function _digitise(dblclickFn, onstartCallback) {
		// remove existing digitise listener
		if (_dblclickHandle) GEvent.removeListener(_dblclickHandle);

		_map.clearOverlays();
		_map.disableDoubleClickZoom();

		_dblclickHandle = GEvent.addListener(_map, "dblclick", function(overlay, point) {
			dblclickFn(point);
			if (onstartCallback) onstartCallback();
			GEvent.removeListener(_dblclickHandle);
			_map.enableDoubleClickZoom();
		} );
	};

	/* private */ function _initPoint(point) {
		_shape = {
			type: HGeographicType.POINT,
			position: point,
			pointMarker: new GMarker(point, { bouncy: true, draggable: true, title: "Drag this marker to move the point" })
		};
		GEvent.addListener(_shape.pointMarker, "dragend", function() {
			_shape.position = _shape.pointMarker.getPoint();
		});
		_map.addOverlay(_shape.pointMarker);
	}

	/* private */ function _initBounds(point, point2) {
		_shape = {
			type: HGeographicType.BOUNDS,
			corner1: new GLatLng(point.lat(), point.lng()),
			corner2: new GLatLng(point.lat() - 1, point.lng() + 1)
		};

		if (! point2) {
			var bounds = _map.getBounds();
			var lat2 = point.lat() - (point.lat() - bounds.getSouthWest().lat()) / 10;
			var lng2 = point.lng() - (point.lng() - bounds.getSouthWest().lng()) / 10;
			_shape.corner2 = new GLatLng(lat2, lng2);
		}
		else {
			_shape.corner2 = new GLatLng(point2.lat(), point2.lng());
		}

		_shape.corner1marker = new GMarker(_shape.corner1, { bouncy: true, draggable: true, title: "Drag this marker to a corner of the bounding-box" });
		_shape.corner2marker = new GMarker(_shape.corner2, { bouncy: true, draggable: true, title: "Drag this marker to a corner of the bounding-box" })

		GEvent.addListener(_shape.corner1marker, "drag", function() {
			_shape.corner1 = _shape.corner1marker.getPoint();
			_drawBounds();
		});
		GEvent.addListener(_shape.corner2marker, "drag", function() {
			_shape.corner2 = _shape.corner2marker.getPoint();
			_drawBounds();
		});

		_map.addOverlay(_shape.corner1marker);
		_map.addOverlay(_shape.corner2marker);

		_drawBounds();
	}

	/* private */ function _initCircle(centre, radius) {
		_shape = {
			type: HGeographicType.CIRCLE,
			centre: centre,
			radius: radius || 1
		};

		if (! radius) {
			var bounds = _map.getBounds();
			_shape.radius = (bounds.getNorthEast().lat() - bounds.getSouthWest().lat()) / 10;
		}

		var centrePos = _shape.centre;
		_shape.centreMarker = new GMarker(centrePos, { bouncy: true, draggable: true, title: "Drag this marker to move the centre of the circle" });
		GEvent.addListener(_shape.centreMarker, "dragend", function() {
			var rpoint = _shape.radiusMarker.getPoint();
			var cpoint = _shape.centreMarker.getPoint();
			var dy = cpoint.lat() - _shape.centre.lat();
			var dx = cpoint.lng() - _shape.centre.lng();

			_shape.centre = cpoint;
			_shape.radiusMarker.setPoint(new GLatLng(rpoint.lat()+dy, rpoint.lng()+dx));
			_drawCircle();
		});
		_map.addOverlay(_shape.centreMarker);

		var radiusIcon = new GIcon();
		radiusIcon.image = "../images/triangle.png";
		radiusIcon.shadow = "../images/triangle-shadow.png";
		radiusIcon.iconSize = new GSize(21, 19);
		radiusIcon.shadowSize = new GSize(42, 19);
		radiusIcon.iconAnchor = new GPoint(11, 18);

		var radiusPos = new GLatLng(centrePos.lat() - _shape.radius/Math.sqrt(2), centrePos.lng() + _shape.radius/Math.sqrt(2));
		_shape.radiusMarker = new GMarker(radiusPos, { icon: radiusIcon, bouncy: false, draggable: true, title: "Drag this marker to set circle radius" });
		GEvent.addListener(_shape.radiusMarker, "drag", function() {
			var rpoint = _shape.radiusMarker.getPoint();
			var dy = rpoint.lat() - _shape.centre.lat();
			var dx = rpoint.lng() - _shape.centre.lng();
			_shape.radius = Math.sqrt(dx*dx + dy*dy);
			_drawCircle();
		});
		_map.addOverlay(_shape.radiusMarker);

		_drawCircle();
	}

	/* private */ function _initPolygon(points, suppressClosingLine) {
		if (points && ! points.length)	// points is a single point
			points = [ points ];

		_shape = {
			type: HGeographicType.POLYGON,
			vertices: [],
			movingVertex: null
		};

		var vtxIcon = _shape.vtxIcon = new GIcon();
		vtxIcon.image = "../images/triangle.png";
		vtxIcon.shadow = "../images/triangle-shadow.png";
		vtxIcon.iconSize = new GSize(21, 19);
		vtxIcon.shadowSize = new GSize(42, 19);
		vtxIcon.iconAnchor = new GPoint(11, 18);

		var addIcon = _shape.addIcon = new GIcon();
		addIcon.image = "../images/triangle-plus.png";
		addIcon.shadow = "../images/triangle-shadow.png";
		addIcon.iconSize = new GSize(21, 19);
		addIcon.shadowSize = new GSize(42, 19);
		addIcon.iconAnchor = new GPoint(11, 18);

		var dotIcon = _shape.dotIcon = new GIcon();
		dotIcon.image = "../images/5x5-dot.png";
		dotIcon.iconSize = new GSize(5, 5);
		dotIcon.iconAnchor = new GPoint(2, 2);

		for (var i=0; i < points.length; ++i) {
			_addVertex(points[i]);
		}
		var dragPoint = suppressClosingLine? points[0] : points[points.length-1];
		_shape.vertexMarker = new GMarker(dragPoint, { bouncy: false, draggable: true, icon: addIcon, title: "Drag this marker to add a new corner to the polygon" });

		GEvent.addListener(_shape.vertexMarker, "drag", function() {
			_shape.movingVertex = _shape.vertexMarker.getPoint();
			_drawPolygon();
		});
		GEvent.addListener(_shape.vertexMarker, "dragend", function() {
			_addVertex(_shape.vertexMarker.getPoint());
			_shape.movingVertex = null;
			_drawPolygon(true);
		});

		_shape.dots = [];
		for (var i=0; i < 4; ++i) {
			var newDot = new GMarker(points[0], { inert: true, icon: dotIcon });
			_shape.dots.push(newDot);
			_map.addOverlay(newDot);
		}
		_map.addOverlay(_shape.vertexMarker);

		_drawPolygon(true);
	}

	/* private */ function _initPath(points) {
		if (points && ! points.length)	// points is a single point
			points = [ points ];

		_shape = {
			type: HGeographicType.PATH,
			vertices: [],
			movingVertex: null
		};

		var startIcon = _shape.vtxIcon = new GIcon();
		startIcon.image = "../images/start-triangle.png";
		startIcon.shadow = "../images/start-triangle-shadow.png";
		startIcon.iconSize = new GSize(27, 29);
		startIcon.shadowSize = new GSize(45, 29);
		startIcon.iconAnchor = new GPoint(14, 28);

		var vtxIcon = _shape.vtxIcon = new GIcon();
		vtxIcon.image = "../images/triangle.png";
		vtxIcon.shadow = "../images/triangle-shadow.png";
		vtxIcon.iconSize = new GSize(21, 19);
		vtxIcon.shadowSize = new GSize(42, 19);
		vtxIcon.iconAnchor = new GPoint(11, 18);

		var addIcon = _shape.addIcon = new GIcon();
		addIcon.image = "../images/triangle-plus.png";
		addIcon.shadow = "../images/triangle-shadow.png";
		addIcon.iconSize = new GSize(21, 19);
		addIcon.shadowSize = new GSize(42, 19);
		addIcon.iconAnchor = new GPoint(11, 18);

		var dotIcon = _shape.dotIcon = new GIcon();
		dotIcon.image = "../images/5x5-dot.png";
		dotIcon.iconSize = new GSize(5, 5);
		dotIcon.iconAnchor = new GPoint(2, 2);

		for (var i=0; i < points.length; ++i) {
			_addVertex(points[i], true, (i==1)? startIcon : null);
		}
		_shape.vertexMarker = new GMarker(points[points.length-1], { bouncy: false, draggable: true, icon: addIcon, title: "Drag this marker to add a new point to the path" });

		GEvent.addListener(_shape.vertexMarker, "drag", function() {
			_shape.movingVertex = _shape.vertexMarker.getPoint();
			_drawPolygon(false, true);
		});
		GEvent.addListener(_shape.vertexMarker, "dragend", function() {
			_addVertex(_shape.vertexMarker.getPoint(), true);
			_shape.movingVertex = null;
			_drawPolygon(true, true);
		});

		_shape.dots = [];
		for (var i=0; i < 4; ++i) {
			var newDot = new GMarker(points[0], { inert: true, icon: dotIcon });
			_shape.dots.push(newDot);
			_map.addOverlay(newDot);
		}
		_map.addOverlay(_shape.vertexMarker);

		_drawPolygon(true, true);
	}

	/* private */ function _addVertex(point, polylinePoint, icon) {
		var index = _shape.vertices.length - 1;
		var prevPoint = _shape.vertices[index];

		_shape.vertices.push(point);

		if (prevPoint) {
			if (! icon) icon = _shape.vtxIcon;
			var newMarker = new GMarker(prevPoint, { bouncy: false, draggable: true, icon: icon, title: "Drag this marker to move a corner of the polygon" });
			GEvent.addListener(newMarker, "dragend", function() {
				_shape.vertices[index] = newMarker.getPoint();
				_drawPolygon(true, polylinePoint);
			});
			_map.addOverlay(newMarker);
		}
	}


	/* private */ function _drawBounds() {
		if (_shape.polylines) {
			for (var i=0; i < _shape.polylines.length; ++i)
				_map.removeOverlay(_shape.polylines[i]);
		}
		_shape.polylines = [];

		var corner1 = _shape.corner1;
		var corner2 = _shape.corner2;
		var points = [];
			points.push(new GLatLng(corner1.lat(), corner1.lng()));
			points.push(new GLatLng(corner2.lat(), corner1.lng()));
			points.push(new GLatLng(corner2.lat(), corner2.lng()));
			points.push(new GLatLng(corner1.lat(), corner2.lng()));
			points.push(new GLatLng(corner1.lat(), corner1.lng()));

		_shape.polylines.push(new GPolyline(points, "#0000FF", 4, 0.8));
		_map.addOverlay(_shape.polylines[0]);
	}

	/* private */ function _drawCircle() {
		if (_shape.polylines) {
			for (var i=0; i < _shape.polylines.length; ++i)
				_map.removeOverlay(_shape.polylines[i]);
		}
		_shape.polylines = [];

		var centre = _shape.centre;
		var radius = _shape.radius;
		var points = [];
		for (var i=0; i < 30; ++i) {
			var P = new GLatLng(centre.lat() + (radius * Math.sin(2 * Math.PI * i / 30)),
								centre.lng() + (radius * Math.cos(2 * Math.PI * i / 30)));
			points.push(P);
		}
		points.push(points[0]);	// close the circle

		_shape.polylines.push(new GPolyline(points, "#0000FF", 4, 0.8));
		_map.addOverlay(_shape.polylines[0]);
	}

	/* private */ function _drawPolygon(fullRedraw, suppressClosingLine) {
		if (_shape.movingVertex) {
			var p0, p1;
			p0 = _shape.vertices[_shape.vertices.length-1];
			p1 = _shape.movingVertex;

			var n = _shape.dots.length;
			for (var i=0; i < n; ++i) {
				var plat = p1.lat()*(i/n) + p0.lat()*((n-i)/n);
				var plng = p1.lng()*(i/n) + p0.lng()*((n-i)/n);
				_shape.dots[i].setPoint(new GLatLng(plat, plng));
			}

			if (! fullRedraw) return;
		}

		if (_shape.polylines) {
			for (var i=0; i < _shape.polylines.length; ++i)
				_map.removeOverlay(_shape.polylines[i]);
		}
		_shape.polylines = [];

		if (! _shape.movingVertex) {
			if (! suppressClosingLine) {
				var points = [];
				points.push(_shape.vertices[_shape.vertices.length-1]);
				points.push(_shape.vertices[0]);
				_shape.polylines.push(new GPolyline(points, "#000040", 4, 0.3));
			}

			for (var i=0; i < _shape.dots.length; ++i)
				_shape.dots[i].setPoint(_shape.vertices[_shape.vertices.length-1]);
		}

		if (_shape.vertices.length > 1) {
			var points = [];
			for (var i=0; i < _shape.vertices.length; ++i)
				points.push(_shape.vertices[i]);
			_shape.polylines.push(new GPolyline(points, "#0000FF", 4, 0.8));
		}

		for (var i=0; i < _shape.polylines.length; ++i)
			_map.addOverlay(_shape.polylines[i]);
	}


}


GOI.google = {
	/* GOI object to Google Maps object conversion */

	getLatLng: function(value) {
		//if (! window.GlatLng) { throw "Google Maps API must be loaded before using GOI.google"; }

		if (HAPI.isA(value, "HPointValue")) {
			return new GLatLng(value.getY(), value.getX());
		}
		else if (HAPI.isA(value, "HBoundsValue")) {
			return new GLatLng(value.getY0(), value.getX0());
		}
		else if (HAPI.isA(value, "HCircleValue")) {
			return new GLatLng(value.getY(), value.getX());
		}
		else if (HAPI.isA(value, "HGeographicValue")) {	// HPolygonValue or HPathValue
			var point = value.getPoints()[0];
			return new GLatLng(point[1], point[0]);
		}
		else {
			throw new HInvalidGeographicValueException("geographic value expected");
		}
	},

	getLatLngs: function(value) {
		var val = [];
		var x0, y0, x1, y1;

		//if (! window.GlatLng) { throw "Google Maps API must be loaded before using GOI.google"; }

		if (HAPI.isA(value, "HPointValue")) {
			return [ new GLatLng(value.getY(), value.getX()) ];
		}
		else if (HAPI.isA(value, "HBoundsValue")) {
			x0 = value.getX0();
			y0 = value.getY0();
			x1 = value.getX1();
			y1 = value.getY1();
			return [
				new GLatLng(y0, x0),
				new GLatLng(y0, x1),
				new GLatLng(y1, x1),
				new GLatLng(y1, x0),
				new GLatLng(y0, x0)
			];
		}
		else if (HAPI.isA(value, "HCircleValue")) {
			for (var i = 0; i < 40; ++i) {
				var x = value.getX() + value.getRadius() * Math.cos(i * 2*Math.PI / 40);
				var y = value.getY() + value.getRadius() * Math.sin(i * 2*Math.PI / 40);
				val.push(new GLatLng(y, x));
			}
			val.push(val[0].copy());
			return val;
		}
		else if (HAPI.isA(value, "HGeographicValue")) {
			var points = value.getPoints();
			for (var i = 0; i < points.length; ++i) {
				val.push(new GLatLng(points[i][1], points[i][0]));
			}
			if (HAPI.isA(value, "HPolygonValue"))
				val.push(val[0].copy());
			return val;
		}
		else {
			throw new HInvalidGeographicValueException("geographic value expected");
		}
	},

	makeMarker: function(pointValue, opts) {
		if (! HAPI.isA(pointValue, "HPointValue")) {
			throw new HInvalidGeographicValueException("HPointValue expected");
		}
		return new GMarker(GOI.google.getLatLng(pointValue), opts);
	},

	makePolyline: function(pathValue, color, weight, opacity, opts) {
		 if (! HAPI.isA(pathValue, "HPathValue")) {
		 	throw new HInvalidGeographicValueException("HPathValue expected");
		 }
		 return new GPolyline(GOI.google.getLatLngs(pathValue), color, weight, opacity, opts);
	},

	makePolygon: function(value, strokeColor, strokeWeight, strokeOpacity, fillColor, fillOpacity, opts) {
		 var latlngs = GOI.google.getLatLngs(value);
		 latlngs.push(latlngs[0]);
		 return new GPolygon(latlngs, strokeColor, strokeWeight, strokeOpacity, fillColor, fillOpacity, opts);
	},

	getBounds: function(overlays, pad) {
		// overlays are either polygons, polylines or points
		var bounds;
		for (var i=0; i < overlays.length; ++i) {
			var overlay = overlays[i];

			if (i == 0) {
				if (overlay.getBounds) {
					bounds = overlay.getBounds();
				}
				else if (overlay.getLatLng) {
					bounds = new GLatLngBounds(overlay.getLatLng(), overlay.getLatLng());
				}
			}
			else {
				if (overlay.getBounds) {
					bounds.extend(overlay.getBounds().getNorthEast());
					bounds.extend(overlay.getBounds().getSouthWest());
				}
				else if (overlay.getLatLng) {
					bounds.extend(overlay.getLatLng());
				}
			}
		}

		if (pad) {
			var swBound = bounds.getSouthWest();
			var neBound = bounds.getNorthEast();
			var minX = swBound.lng();
			var minY = swBound.lat();
			var maxX = neBound.lng();
			var maxY = neBound.lat();
			var centre = bounds.getCenter();
			if (maxX-minX < 0.000001  &&  maxY-minY < 0.000001) {       // single point, or very close points
				var sw = new GLatLng(minY - 7.5, minX - 7.5);
				var ne = new GLatLng(maxY + 7.5, maxX + 7.5);
			}
			else {
				var sw = new GLatLng(minY - 0.1*(maxY - minY), minX - 0.1*(maxX - minX));
				var ne = new GLatLng(maxY + 0.1*(maxY - minY), maxX + 0.1*(maxX - minX));
			}
			bounds = new GLatLngBounds(sw, ne);
		}

		return bounds;
	}


};
