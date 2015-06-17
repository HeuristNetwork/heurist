// This application is provided by Kjell Scharning
//  Licensed under the Apache License, Version 2.0;
//  http://www.apache.org/licenses/LICENSE-2.0

// Modification Artem Osmakov

function gob(e){if(typeof(e)=='object')return(e);if(document.getElementById)return(document.getElementById(e));return(eval(e))}

if(top.HAPI){
  top.HAPI.importSymbols(top, this);
}

RelBrowser = {
  pipelineBaseURL: "../"
};

var isReadOnly = false;

var _keepZoom; //art
var systemAllLayers;
var currentBackgroundLayer;

var map;
var polyShape;
var markerShape;
var oldDirMarkers = [];
//var tmpPolyLine;
var drawnShapes = [];
var holeShapes = [];
var startMarker;
var nemarker;
var tinyMarker;
var markers = [];
var midmarkers = [];
//var markerlistener1;
//var markerlistener2;
var rectangle;
var circle;
var southWest;
var northEast;
var centerPoint;
var radiusPoint;
var calc;
var startpoint;
var dirpointstart = null;
var dirpointend = 0;
var dirline;
var waypts = [];
//var waypots = [];
var polyPoints = [];
var pointsArray = [];
var markersArray = [];
var addresssArray = [];
var pointsArrayKml = [];
var markersArrayKml = [];
var toolID = 5;
var codeID = 0;
var shapeId = 0;
var adder = 0;
var plmcur = 0;
var lcur = 0;
var pcur = 0;
//var rcur = 0;
var ccur = 0;
var mcur = 0;
var outerPoints = [];
var holePolyArray = [];
var outerShape;
var anotherhole = false;
//var it;
var outerArray = [];
var innerArray = [];
var innerArrays = [];
var outerArrayKml = [];
var innerArrayKml = [];
var innerArraysKml = [];
var placemarks = [];
//var mylistener;
var editing = false;
var notext = false;
var kmlcode = "";
var javacode = "";
var polylineDecColorCur = "255,0,0";
var polygonDecColorCur = "255,0,0";
var docuname = "My document";
var docudesc = "Content";
var polylinestyles = [];
var polygonstyles = [];
//var rectanglestyles = [];
var circlestyles = [];
var markerstyles = [];
var geocoder; // = new google.maps.Geocoder();
//var startLocation;
var endLocation;
//var dircount;
var dircountstart;
var directionsDisplay;
var directionsService = new google.maps.DirectionsService();
var directionsYes = 0;
var destinations = [];
//var currentDirections = null;
var oldpoint = null;
//var infowindow = new google.maps.InfoWindow({size: new google.maps.Size(150,50)});
var tinyIcon = new google.maps.MarkerImage(
  //ARTEM 'images/marker_20_red.png',
  new google.maps.Size(12,20),
  new google.maps.Point(0,0),
  new google.maps.Point(6,16)
);
/*var tinyShadow = new google.maps.MarkerImage(
'images/marker_20_shadow.png',
new google.maps.Size(22,20),
new google.maps.Point(6,20),
new google.maps.Point(5,1)
);*/
var imageNormal = new google.maps.MarkerImage(
  "images/square.png",
  new google.maps.Size(11, 11),
  new google.maps.Point(0, 0),
  new google.maps.Point(6, 6)
);
var imageHover = new google.maps.MarkerImage(
  "images/square_over.png",
  new google.maps.Size(11, 11),
  new google.maps.Point(0, 0),
  new google.maps.Point(6, 6)
);
var imageNormalMidpoint = new google.maps.MarkerImage(
  "images/square_transparent.png",
  new google.maps.Size(11, 11),
  new google.maps.Point(0, 0),
  new google.maps.Point(6, 6)
);
/*var imageHoverMidpoint = new google.maps.MarkerImage(
"square_transparent_over.png",
new google.maps.Size(11, 11),
new google.maps.Point(0, 0),
new google.maps.Point(6, 6)
);*/
function polystyle() {
  this.name = "Lump";
  this.kmlcolor = "CD0000FF";
  this.kmlfill = "9AFF0000";
  this.color = "#FF0000";
  this.fill = "#0000FF";
  this.width = 2;
  this.lineopac = 0.8;
  this.fillopac = 0.1;
}
function linestyle() {
  this.name = "Path";
  this.kmlcolor = "FF0000FF";
  this.color = "#FF0000";
  this.width = 2;
  this.lineopac = 1;
}
function circstyle() {
  this.name = "Circ";
  this.color = "#FF0000";
  this.fill = "#0000FF";
  this.width = 2;
  this.lineopac = 0.8;
  this.fillopac = 0.1;
}
function markerstyleobject() {
  this.name = "markerstyle";
  this.icon = "http://maps.google.com/intl/en_us/mapfiles/ms/micons/red-dot.png";
}
function placemarkobject() {
  this.name = "NAME";
  this.desc = "YES";
  this.style = "Path";
  this.stylecur = 0;
  this.tess = 1;
  this.alt = "clampToGround";
  this.plmtext = "";
  this.jstext = "";
  this.jscode = [];
  this.kmlcode = [];
  this.poly = "pl";
  this.shape = null;
  this.point = null;
  this.toolID = 1;
  this.hole = 0;
  this.ID = 0;
}
function createplacemarkobject() {
  var thisplacemark = new placemarkobject();
  placemarks.push(thisplacemark);
}
function createpolygonstyleobject() {
  var polygonstyle = new polystyle();
  polygonstyles.push(polygonstyle);
}
function createlinestyleobject() {
  var polylinestyle = new linestyle();
  polylinestyles.push(polylinestyle);
}
function createcirclestyleobject() {
  var cirstyle = new circstyle();
  circlestyles.push(cirstyle);
}
function createmarkerstyleobject() {
  var thisstyle = new markerstyleobject();
  markerstyles.push(thisstyle);
}
function preparePolyline(){
  var polyOptions = {
    path: polyPoints,
    strokeColor: polylinestyles[lcur].color,
    strokeOpacity: polylinestyles[lcur].lineopac,
    strokeWeight: polylinestyles[lcur].width};
  polyShape = new google.maps.Polyline(polyOptions);
  polyShape.setMap(map);
  /*var tmpPolyOptions = {
  strokeColor: polylinestyles[lcur].color,
  strokeOpacity: polylinestyles[lcur].lineopac,
  strokeWeight: polylinestyles[lcur].width
  };
  tmpPolyLine = new google.maps.Polyline(tmpPolyOptions);
  tmpPolyLine.setMap(map);*/
}

function preparePolygon(){
  var polyOptions = {
    path: polyPoints,
    strokeColor: polygonstyles[pcur].color,
    strokeOpacity: polygonstyles[pcur].lineopac,
    strokeWeight: polygonstyles[pcur].width,
    fillColor: polygonstyles[pcur].fill,
    fillOpacity: polygonstyles[pcur].fillopac};
  polyShape = new google.maps.Polygon(polyOptions);
  polyShape.setMap(map);
}
function activateRectangle() {
  rectangle = new google.maps.Rectangle({
      map: map,
      strokeColor: polygonstyles[pcur].color,
      strokeOpacity: polygonstyles[pcur].lineopac,
      strokeWeight: polygonstyles[pcur].width,
      fillColor: polygonstyles[pcur].fill,
      fillOpacity: polygonstyles[pcur].fillopac
  });
}
function activateCircle() {
  circle = new google.maps.Circle({
      map: map,
      fillColor: circlestyles[ccur].fill,
      fillOpacity: circlestyles[ccur].fillopac,
      strokeColor: circlestyles[ccur].color,
      strokeOpacity: circlestyles[ccur].lineopac,
      strokeWeight: circlestyles[ccur].width
  });
}
// creates marker for single point shape
//
function activateMarker() {
  markerShape = new google.maps.Marker({
      map: map,
      icon: markerstyles[mcur].icon,
      draggable: (toolID == 5 && !isReadOnly),
      raiseOnDrag: false
  });
  if(toolID == 5 && !isReadOnly){
    google.maps.event.addListener(markerShape, 'drag', logCoords);
  }
}
function initmap(map_div_id, geovalue){

  var defaultViewString = top.HEURIST.util.getDisplayPreference("gigitiser-view");
  var viewBits;
  if (defaultViewString  &&  (viewBits = defaultViewString.match(/(.*),(.*)@(\d+):(\S+):(.*)?/))) {  //[mkh] (.*)
    if(viewBits[5]){
      currentBackgroundLayer = viewBits[5];
    }
  }


  _load_layers(0);

  geocoder = new google.maps.Geocoder();
  /*
  var latlng = new google.maps.LatLng(-33.887967, 151.190034);
  var myOptions = {
  zoom: 16,
  center: latlng,
  draggableCursor: 'default',
  draggingCursor: 'pointer',
  scaleControl: true,
  mapTypeControl: true,
  mapTypeControlOptions:{style: google.maps.MapTypeControlStyle.DROPDOWN_MENU},
  mapTypeId: google.maps.MapTypeId.ROADMAP,
  streetViewControl: false};
  map = new google.maps.Map(gob('map'),myOptions);
  */

  RelBrowser.Mapping.mapdata = {
    timemap: [],
    layers: [],
    count_mapobjects: 0
  };
  initMapping(map_div_id); //from mapping.js

  map = RelBrowser.Mapping.map.maps.googlev3;

  polyPoints = new google.maps.MVCArray(); // collects coordinates
  createplacemarkobject();
  createlinestyleobject();
  createpolygonstyleobject();
  createcirclestyleobject();
  createmarkerstyleobject();
  preparePolyline(); // create a Polyline object

  google.maps.event.addListener(map, 'click', addLatLng);
  google.maps.event.addListener(map,'zoom_changed',mapzoom);
  map.setOptions({ draggableCursor: 'crosshair' });

  cursorposition(map);

  if(geovalue){

    loadParameters(geovalue);

  } else if (document.location.search.match(/\?edit/)) {
    HAPI.PJ.retrieve("gigitiser_geo_object",
      function(_, val) {
        loadParameters(val);
      }
    );
  } else {
    loadParameters();
  }


  /*  RESTORE SAVED EXTENT */
  if (viewBits) {
    _keepZoom = parseInt(viewBits[3]);
    map.setCenter(new google.maps.LatLng(parseFloat(viewBits[1]) || 0, parseFloat(viewBits[2]) || 0));
    map.setZoom(_keepZoom);
    if(viewBits[4]==google.maps.MapTypeId.HYBRID ||
      viewBits[4]==google.maps.MapTypeId.ROADMAP ||
      viewBits[4]==google.maps.MapTypeId.SATELLITE ||
      viewBits[4]==google.maps.MapTypeId.TERRAIN)
      {
      map.setMapTypeId(viewBits[4]);
    }
  }
  else {
    var pos = new google.maps.LatLng(-33.88889, 151.18956);
    map.setZoom(16);
    map.setCenter(pos);
    if(toolID == 5 && markerShape==null) drawPointMarker(pos);
  }


}

//
// simple viewer
//
function initmap_viewer(map_div_id, geovalue){

  if(map==null){

    RelBrowser.Mapping.mapdata = {
      timemap: [],
      layers: [],
      count_mapobjects: 0
    };
    initMapping(map_div_id); //from mapping.js

    map = RelBrowser.Mapping.map.maps.googlev3;

    polyPoints = new google.maps.MVCArray(); // collects coordinates
    createplacemarkobject();
    createlinestyleobject();
    createpolygonstyleobject();
    createcirclestyleobject();
    createmarkerstyleobject();
    preparePolyline(); // create a Polyline object
  }
  isReadOnly = true;
  loadParameters(geovalue);
}

// Called by initmap, addLatLng, drawRectangle, drawCircle, drawpolywithhole
function cursorposition(mapregion){
  google.maps.event.addListener(mapregion,'mousemove',function(point){
      var LnglatStr7 = point.latLng.lng().toFixed(7) + ', ' + point.latLng.lat().toFixed(7);
      var latLngStr7 = point.latLng.lat().toFixed(7) + ', ' + point.latLng.lng().toFixed(7);
      gob('over').options[0].text = LnglatStr7;
      gob('over').options[1].text = latLngStr7;
  });
}

function addLatLng(event){
  addLatLng2(event.latLng)
}

function addLatLng2(point){

  if(directionsYes == 1) {
    drawDirections(point);
    return;
  }
  if(plmcur != placemarks.length-1) {
    nextshape();
  }

  // Rectangle and circle can't collect points with getPath. solved by letting Polyline collect the points and then erase Polyline
  polyPoints = polyShape.getPath();

  if( polyPoints.length < 2 || (toolID != 3 && toolID != 4) ){
    polyPoints.insertAt(polyPoints.length, point); // or: polyPoints.push(point)
  }

  if(polyPoints.length == 1) {
    startpoint = point;
    placemarks[plmcur].point = startpoint; // stored because it's to be used when the shape is clicked on as a stored shape
    setstartMarker(startpoint);
    if(toolID == 5) {    //point tool
      drawPointMarker(startpoint);
    }
  }
  if(polyPoints.length == 2 && toolID == 3) createrectangle(point);
  if(polyPoints.length == 2 && toolID == 4) createcircle(point);
  if(toolID == 1 || toolID == 2) { // if polyline or polygon
    var stringtobesaved = point.lat().toFixed(7) + ',' + point.lng().toFixed(7);
    var kmlstringtobesaved = point.lng().toFixed(7) + ' ' + point.lat().toFixed(7);
    //Cursor position, when inside polyShape, is registered with this listener
    cursorposition(polyShape);
    if(adder == 0) {
      pointsArray.push(stringtobesaved);
      pointsArrayKml.push(kmlstringtobesaved);
      if(polyPoints.length == 1 && toolID == 2) closethis('polygonstuff');

      logCoords();
    }
    if(adder == 1) {
      outerArray.push(stringtobesaved);
      outerArrayKml.push(kmlstringtobesaved);
    }
    if(adder == 2) {
      innerArray.push(stringtobesaved);
      innerArrayKml.push(kmlstringtobesaved);
    }
  }
}
function setstartMarker(point){

  startMarker = new google.maps.Marker({
      icon: imageNormal,
      position: point,
      map: (isReadOnly)?null:map
  });
  startMarker.setTitle("#" + polyPoints.length);
}
function createrectangle(point) {
  // startMarker i southwest point. now set northeast
  nemarker = new google.maps.Marker({
      icon: imageNormal,
      position: point,
      draggable: true,
      raiseOnDrag: false,
      title: "Draggable",
      map: (isReadOnly)?null:map});
  if(!isReadOnly){
    google.maps.event.addListener(startMarker, 'drag', drawRectangle);
    google.maps.event.addListener(nemarker, 'drag', drawRectangle);
    startMarker.setDraggable(true);
    startMarker.setAnimation(null);
    startMarker.setTitle("Draggable");
  }
  drawRectangle();
}
function drawRectangle() {
  southWest = startMarker.getPosition(); // used in logCode6()
  northEast = nemarker.getPosition(); // used in logCode6()
  var latLngBounds = new google.maps.LatLngBounds(
    southWest,
    northEast
  );

  polyShape.setMap(null); // remove the Polyline that has collected the points
  polyPoints = [];

  rectangle.setBounds(latLngBounds);
  //Cursor position, when inside rectangle, is registered with this listener
  cursorposition(rectangle);
  // the Rectangle was created in activateRectangle(), called from newstart(), which may have been called from setTool()
  var northWest = new google.maps.LatLng(southWest.lat(), northEast.lng());
  var southEast = new google.maps.LatLng(northEast.lat(), southWest.lng());
  polyPoints = [];
  pointsArray = [];
  pointsArrayKml = [];
  polyPoints.push(southWest);
  polyPoints.push(northWest);
  polyPoints.push(northEast);
  polyPoints.push(southEast);
  var stringtobesaved = southWest.lng().toFixed(7)+' '+southWest.lat().toFixed(7);
  pointsArrayKml.push(stringtobesaved);
  stringtobesaved = southWest.lng().toFixed(7)+' '+northEast.lat().toFixed(7);
  pointsArrayKml.push(stringtobesaved);
  stringtobesaved = northEast.lng().toFixed(7)+' '+northEast.lat().toFixed(7);
  pointsArrayKml.push(stringtobesaved);
  stringtobesaved = northEast.lng().toFixed(7)+' '+southWest.lat().toFixed(7);
  pointsArrayKml.push(stringtobesaved);
  stringtobesaved = southWest.lat().toFixed(7)+','+southWest.lng().toFixed(7);
  pointsArray.push(stringtobesaved);
  stringtobesaved = northEast.lat().toFixed(7)+','+southWest.lng().toFixed(7);
  pointsArray.push(stringtobesaved);
  stringtobesaved = northEast.lat().toFixed(7)+','+northEast.lng().toFixed(7);
  pointsArray.push(stringtobesaved);
  stringtobesaved = southWest.lat().toFixed(7)+','+northEast.lng().toFixed(7);
  pointsArray.push(stringtobesaved);
  // Change the rectangle to polygon
  //rectangle.setMap(null);

  preparePolygon();
  logCoords();
  southWest = northEast = null;
}
function createcircle(point) {
  // startMarker is center point. now set radius
  if(nemarker) nemarker.setMap(null);

  nemarker = new google.maps.Marker({
      icon: imageNormal,
      position: point,
      draggable: true,
      raiseOnDrag: false,
      title: "Draggable",
      map: (isReadOnly)?null:map});

  if(!isReadOnly){
    google.maps.event.addListener(startMarker, 'drag', drawCircle);
    google.maps.event.addListener(nemarker, 'drag', drawCircle);
    startMarker.setDraggable(true);
    startMarker.setAnimation(null);
    startMarker.setTitle("Draggable");
  }
  drawCircle();
  polyShape.setMap(null); // remove the Polyline that has collected the points
  polyPoints = [];
}
function drawCircle() {
  centerPoint = startMarker.getPosition();
  radiusPoint = nemarker.getPosition();
  circle.bindTo('center', startMarker, 'position');
  calc = distance(centerPoint.lat(),centerPoint.lng(),radiusPoint.lat(),radiusPoint.lng());
  circle.setRadius(calc);
  //Cursor position, when inside circle, is registered with this listener
  cursorposition(circle);
  logCoords();
}
// calculate distance between two coordinates
function distance(lat1,lon1,lat2,lon2) {
  var R = 6371000; // earth's radius in meters
  var dLat = (lat2-lat1) * Math.PI / 180;
  var dLon = (lon2-lon1) * Math.PI / 180;
  var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
  Math.cos(lat1 * Math.PI / 180 ) * Math.cos(lat2 * Math.PI / 180 ) *
  Math.sin(dLon/2) * Math.sin(dLon/2);
  var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  var d = R * c;
  return d;
}

function drawPointMarker(point) {
  if(startMarker) startMarker.setMap(null);
  if(polyShape) polyShape.setMap(null);
  var id = plmcur;
  placemarks[plmcur].jscode = point.lat().toFixed(7) + ',' + point.lng().toFixed(7);
  placemarks[plmcur].kmlcode = point.lng().toFixed(7) + ',' + point.lat().toFixed(7);
  activateMarker();
  markerShape.setPosition(point);
  /* ARTEM
  var marker = markerShape;
  tinyMarker = new google.maps.Marker({
  position: placemarks[plmcur].point,
  map: map,
  icon: tinyIcon
  });
  google.maps.event.addListener(marker, 'click', function(event){
  plmcur = id;
  markerShape = marker;
  var html = "<b>" + placemarks[plmcur].name + "</b> <br/>" + placemarks[plmcur].desc;
  var infowindow = new google.maps.InfoWindow({
  content: html
  });
  if(tinyMarker) tinyMarker.setMap(null);
  tinyMarker = new google.maps.Marker({
  position: placemarks[plmcur].point,
  map: map,
  icon: tinyIcon
  });
  if(toolID != 5) toolID = gob('toolchoice').value = 5;
  if(codeID == 1)logCode9();
  if(codeID == 2)logCode8();
  infowindow.open(map,marker);
  });
  */

  drawnShapes.push(markerShape);
  logCoords();
}

function drawDirections(point) {
  if(dirpointstart == null) {
    //setDirectionsMarker(point);
    dirpointstart = point;
    setstartMarker(dirpointstart);
    increaseplmcur();
    dirline = plmcur;
    placemarks[dirline].shape = polyShape;
    placemarks[dirline].point = dirpointstart;
    directionsDisplay = new google.maps.DirectionsRenderer({
        suppressMarkers: true,
        preserveViewport: true
    });
  }else{
    if(startMarker) startMarker.setMap(null);
    directionsDisplay.setOptions({polylineOptions: {
          strokeColor: polylinestyles[lcur].color,
          strokeOpacity: polylinestyles[lcur].lineopac,
          strokeWeight: polylinestyles[lcur].width}});
    if(dirpointend == 0) {
      var request = {
        origin: dirpointstart,
        destination: point,
        travelMode: google.maps.DirectionsTravelMode.DRIVING
      };
      oldpoint = point;
      destinations.push(request.destination);
      calcRoute(request);

      dirpointend = 1;
      dircountstart = plmcur+1;
    }else{
      if(oldpoint) waypts.push({location:oldpoint, stopover:true});
      request = {
        origin: dirpointstart,
        destination: point,
        waypoints: waypts,
        travelMode: google.maps.DirectionsTravelMode.DRIVING
      };
      oldpoint = point;
      destinations.push(request.destination);
      calcRoute(request);
    }
  }
}
function calcRoute(request) {
  if(waypts.length == 0) directionsDisplay.setMap(map);
  directionsService.route(request, RenderCustomDirections);
}
function RenderCustomDirections(response, status) {
  if (status == google.maps.DirectionsStatus.OK) {
    directionsDisplay.setDirections(response);
    polyPoints = [];
    pointsArray = [];
    pointsArrayKml = [];
    markersArray = [];
    markersArrayKml = [];
    addresssArray = [];
    var result = directionsDisplay.getDirections().routes[0];
    for(var i = 0; i < result.overview_path.length; i++) {
      polyPoints.push(result.overview_path[i]);
      pointsArray.push(result.overview_path[i].lat().toFixed(7) + ',' + result.overview_path[i].lng().toFixed(7));
      pointsArrayKml.push(result.overview_path[i].lng().toFixed(7) + ' ' + result.overview_path[i].lat().toFixed(7));
    }
    polyShape.setPath(polyPoints);
    endLocation = new Object();
    var legs = response.routes[0].legs;
    for (var i=0;i<legs.length;i++) {
      if (i == 0) {
        createdirMarker(legs[i].start_location,"start",legs[i].start_address,"green");
      } else {
        createdirMarker(legs[i].start_location,"waypoint"+i,legs[i].start_address,"yellow");
      }
      endLocation.latlng = legs[i].end_location;
      endLocation.address = legs[i].end_address;
      markersArray.push(legs[i].start_location.lat().toFixed(7) + ',' + legs[i].start_location.lng().toFixed(7));
      markersArrayKml.push(legs[i].start_location.lng().toFixed(7) + ',' + legs[i].start_location.lat().toFixed(7));
      addresssArray.push(legs[i].start_address);

    }
    createdirMarker(endLocation.latlng,"end",endLocation.address,"red");
    markersArray.push(endLocation.latlng.lat().toFixed(7) + ',' + endLocation.latlng.lng().toFixed(7));
    markersArrayKml.push(endLocation.latlng.lng().toFixed(7) + ',' + endLocation.latlng.lat().toFixed(7));
    addresssArray.push(endLocation.address);
    logCode1a();
  }
  else alert(status);
}
function createdirMarker(latlng, label, html, color) {
  if(tinyMarker) tinyMarker.setMap(null);
  createplacemarkobject();
  plmcur++;
  if(color == "green") {
    if(plmcur != dircountstart) {
      for(var i=dircountstart;i<plmcur;i++) {
        placemarks.pop();
        drawnShapes[drawnShapes.length-1].setMap(null);
        drawnShapes.pop();
      }
      plmcur = dircountstart;
    }
  }
  activateMarker();
  markerShape.setPosition(latlng);
  placemarks[plmcur].jscode = latlng.lat().toFixed(7) + ',' + latlng.lng().toFixed(7);
  placemarks[plmcur].kmlcode = latlng.lng().toFixed(7) + ',' + latlng.lat().toFixed(7);
  placemarks[plmcur].desc = html;
  placemarks[plmcur].point = latlng;
  placemarks[plmcur].style = markerstyles[mcur].name;
  placemarks[plmcur].stylecur = mcur;
  var marker = markerShape;
  var thisshape = plmcur;
  google.maps.event.addListener(marker, 'click', function(event){
      markerShape = marker;
      plmcur = thisshape;
      var html = "<b>" + placemarks[thisshape].name + "</b> <br/>" + placemarks[thisshape].desc;
      var infowindow = new google.maps.InfoWindow({
          content: html
      });
      if(tinyMarker) tinyMarker.setMap(null);
      tinyMarker = new google.maps.Marker({
          position: placemarks[plmcur].point,
          map: map,
          icon: tinyIcon
      });
      if(toolID != 5 && directionsYes == 0) toolID = gob('toolchoice').value = 5;
      logCoords();
      infowindow.open(map,marker);
  });
  drawnShapes.push(markerShape);
}
// Called from button
function undo() {
  drawnShapes[drawnShapes.length-1].setMap(null);
  destinations.pop();
  var point = destinations[destinations.length-1];
  oldpoint = point;
  waypts.pop();
  var request = {
    origin: dirpointstart,
    destination: point,
    waypoints: waypts,
    travelMode: google.maps.DirectionsTravelMode.DRIVING
  };
  calcRoute(request);
}

// Not used
function setDirectionsMarker(point) {
  var image = new google.maps.MarkerImage('images/square.png');
  var marker = new google.maps.Marker({
      position: point,
      map: map,
      icon: image
  });
  /*var shadow = new google.maps.MarkerImage('http://maps.google.com/intl/en_us/mapfiles/ms/micons/msmarker.shadow.png',
  new google.maps.Size(37,32),
  new google.maps.Point(16,0),
  new google.maps.Point(0,32));
  var title = "#" + markers.length;
  var id = plmcur;
  placemarks[plmcur].point = point;
  placemarks[plmcur].coord = point.lng().toFixed(7) + ', ' + point.lat().toFixed(7);*/
  /*var marker = new google.maps.Marker({
  position: point,
  map: map,
  draggable: true,
  icon: image,
  shadow: shadow});*/
  //marker.setTitle(title);
  //markers.push(marker);
}

/**
 *
 */
function setTool(newToolID){
  if(newToolID==toolID) return;

  var oldToolID = toolID;
  toolID = newToolID;

  var el = gob('toolchoice');
  if(el){
    el.value = newToolID;
    if(toolID == 1 || toolID == 2){
      //map.setOptions({ draggableCursor: 'crosshair' });
      showthis('btnDelLastPoint');
      showthis('btnEditPoly');
    }else{
      closethis('btnDelLastPoint');
      closethis('btnEditPoly');
    }
  }

  if(polyPoints.length==0) {
    clearMap();
  }else if(newToolID == 1){
    if(oldToolID==2){ //swtich from polygon
      placemarks[plmcur].style = polylinestyles[polylinestyles.length-1].name;
      placemarks[plmcur].stylecur = polylinestyles.length-1;
      if(polyShape) polyShape.setMap(null);
      preparePolyline(); //if a polygon exists, it will be redrawn as polylines
    }else{
      clearMap();
    }

  } else if(newToolID == 2){ // polygon
    if(oldToolID==1){ //swtich from polyline
      placemarks[plmcur].style = polygonstyles[polygonstyles.length-1].name;
      placemarks[plmcur].stylecur = polygonstyles.length-1;
      if(polyShape) polyShape.setMap(null);
      preparePolygon(); //if a polyline exists, it will be redrawn as a polygon
    }else{
      clearMap();
    }
  }else{
    clearMap();
  }
}

/*
function setTool(){
if(polyPoints.length == 0 && kmlcode == "" && javacode == "") {
newstart();
}else{
if(toolID == 1){ // polyline
// change to polyline draw mode not allowed
if(outerArray.length > 0) { //indicates polygon with hole
polyShape.setMap(null);
newstart();
return;
}
if(rectangle) {
toolID = 3;
nextshape();
toolID = 1;
newstart();
return;
}
if(circle) {
toolID = 4;
nextshape();
toolID = 1;
newstart();
return;
}
if(markerShape) {
toolID = 5;
nextshape();
toolID = 1;
newstart();
return;
}
if(directionsYes == 1) {
toolID = 6;
nextshape();
directionsYes = 0;
toolID = 1;
newstart();
return;
}
placemarks[plmcur].style = polylinestyles[polylinestyles.length-1].name;
placemarks[plmcur].stylecur = polylinestyles.length-1;
if(polyShape) polyShape.setMap(null);
preparePolyline(); //if a polygon exists, it will be redrawn as polylines
logCoords();
}
if(toolID == 2){ // polygon
if(rectangle) {
toolID = 3;
nextshape();
toolID = 2;
newstart();
return;
}
if(circle) {
toolID = 4;
nextshape();
toolID = 2;
newstart();
return;
}
if(markerShape) {
toolID = 5;
nextshape();
toolID = 2;
newstart();
return;
}
if(directionsYes == 1) {
toolID = 6;
nextshape();
directionsYes = 0;
toolID = 2;
newstart();
return;
}
placemarks[plmcur].style = polygonstyles[polygonstyles.length-1].name;
placemarks[plmcur].stylecur = polygonstyles.length-1;
if(polyShape) polyShape.setMap(null);
preparePolygon(); //if a polyline exists, it will be redrawn as a polygon
logCoords();
}
if(toolID == 3 || toolID == 4 || toolID == 5 || toolID == 6){
if(polyShape) polyShape.setMap(null);
if(directionsDisplay) directionsDisplay.setMap(null);
if(circle) circle.setMap(null);
if(rectangle) rectangle.setMap(null);
directionsYes = 0;
newstart();
}
}
}
*/
function setCode(){
  if(polyPoints.length !== 0){
    logCoords();
  }
}
function increaseplmcur() {
  if(placemarks[plmcur].plmtext != "") {
    if(polyShape && directionsYes == 0) {
      placemarks[plmcur].shape = polyShape;
      if(toolID==1 || toolID==2 || toolID==3) addpolyShapelistener();
      createplacemarkobject();
      plmcur = placemarks.length -1;
    }
    if(markerShape) {
      placemarks[plmcur].shape = markerShape;
      createplacemarkobject();
      plmcur = placemarks.length -1;
    }
  }
}
function nextshape() {
  if(editing == true) stopediting();
  /*if(southWest) {
  rectangle.setMap(null);
  southWest = northEast = null;
  preparePolygon();
  }*/
  placemarks[plmcur].shape = polyShape;
  if(plmcur < placemarks.length -1) addpolyShapelistener();
  plmcur = placemarks.length -1;
  increaseplmcur();
  if(directionsYes == 1) {
    plmcur = dirline;
    addpolyShapelistener();
    plmcur = placemarks.length -1;
    toolID = 6;
  }
  if(polyShape) drawnShapes.push(polyShape); // used in clearMap, to have it removed from the map, drawnShapes[i].setMap(null)
  if(outerShape) drawnShapes.push(outerShape);
  if(circle) drawnShapes.push(circle);
  if(directionsDisplay) directionsDisplay.setMap(null);
  if(tinyMarker) drawnShapes.push(tinyMarker);
  polyShape = null;
  outerShape = null;
  rectangle = null;
  circle = null;
  markerShape = null;
  directionsDisplay = null;
  newstart();
}
function addpolyShapelistener() {
  //if(outerPoints.length>>0) return;
  var thisshape = plmcur;
  // In v2 I can give a shape an ID and have that ID revealed, with the map listener, when the shape is clicked on
  // I can't do that in v3. Instead I put a listener on the shape
  google.maps.event.addListener(polyShape,'click',function(point){
      if(tinyMarker) tinyMarker.setMap(null);
      if(startMarker) startMarker.setMap(null);
      directionsYes = 0;
      polyShape = placemarks[thisshape].shape;
      polyPoints = polyShape.getPath();
      plmcur = thisshape;
      tinyMarker = new google.maps.Marker({
          position: placemarks[plmcur].point,
          map: map,
          icon: tinyIcon
      });
      pointsArray = placemarks[plmcur].jscode;
      pointsArrayKml = placemarks[plmcur].kmlcode;
      closethis('polygonstuff');
      if(placemarks[plmcur].poly == "pl") {
        toolID = gob('toolchoice').value = 1;
        lcur = placemarks[plmcur].stylecur;
        logCoords();
      }else{
        toolID = gob('toolchoice').value = 2;
        pcur = placemarks[plmcur].stylecur;
        logCoords();
      }
  });
}
// Clear current Map
function clearMap(){
  if(editing == true) stopediting();
  if(polyShape) polyShape.setMap(null); // polyline or polygon
  if(outerShape) outerShape.setMap(null);
  if(rectangle) rectangle.setMap(null);
  if(circle) circle.setMap(null);
  if(drawnShapes.length > 0) {
    for(var i = 0; i < drawnShapes.length; i++) {
      drawnShapes[i].setMap(null);
    }
  }
  plmcur = 0;
  newstart();
  placemarks = [];
  createplacemarkobject();
  markerShape = null;
}
function newstart() {
  polyPoints = [];
  outerPoints = [];
  pointsArray = [];
  markersArray = [];
  pointsArrayKml = [];
  markersArrayKml = [];
  addresssArray = [];
  outerArray = [];
  innerArray = [];
  outerArrayKml = [];
  innerArrayKml = [];
  holePolyArray = [];
  innerArrays = [];
  innerArraysKml = [];
  waypts = [];
  destinations = [];
  adder = 0;
  directionsYes = 0;
  dirpointend = 0;
  dirpointstart = null;
  oldpoint = null;
  /*ART closethis('polylineoptions');
  closethis('polygonoptions');
  closethis('circleoptions');*/
  if(toolID != 2) closethis('polygonstuff');
  if(directionsDisplay) directionsDisplay.setMap(null);
  if(startMarker) startMarker.setMap(null);
  if(nemarker) nemarker.setMap(null);
  if(tinyMarker) tinyMarker.setMap(null);
  if(toolID == 1) {
    placemarks[plmcur].style = polylinestyles[polylinestyles.length-1].name;
    placemarks[plmcur].stylecur = polylinestyles.length-1;
    preparePolyline();
    polylineintroduction();
  }
  if(toolID == 2){
    //ART showthis('polygonstuff');
    ///ART gob('stepdiv').innerHTML = "Step 0";
    placemarks[plmcur].style = polygonstyles[polygonstyles.length-1].name;
    placemarks[plmcur].stylecur = polygonstyles.length-1;
    preparePolygon();
    polygonintroduction();
  }
  if(toolID == 3) {
    placemarks[plmcur].style = polygonstyles[polygonstyles.length-1].name;
    placemarks[plmcur].stylecur = polygonstyles.length-1;
    preparePolyline(); // use Polyline to collect clicked point
    activateRectangle();
    rectangleintroduction();
  }
  if(toolID == 4) {
    placemarks[plmcur].style = circlestyles[circlestyles.length-1].name;
    placemarks[plmcur].stylecur = circlestyles.length-1;
    preparePolyline(); // use Polyline to collect clicked point
    activateCircle();
    circleintroduction();
  }
  if(toolID == 5) {
    placemarks[plmcur].style = markerstyles[markerstyles.length-1].name;
    placemarks[plmcur].stylecur = markerstyles.length-1;
    preparePolyline();
    markerintroduction();
  }
  if(toolID == 6){
    directionsYes = 1;
    preparePolyline();
    directionsintroduction();
  }
  kmlcode = "";
  javacode = "";
}

function deleteLastPoint(){
  if(directionsYes == 1) {
    if(destinations.length == 1) return;
    undo();
    return;
  }
  if(toolID == 1) {
    if(polyShape) {
      polyPoints = polyShape.getPath();
      if(polyPoints.length > 0) {
        polyPoints.removeAt(polyPoints.length-1);
        pointsArrayKml.pop();
        logCoords();
      }
    }
  }
  if(toolID == 2) {
    if(innerArrayKml.length>0) {
      innerArrayKml.pop();
      polyPoints.removeAt(polyPoints.length-1);
    }
    if(outerArrayKml.length>0 && innerArrayKml.length==0) {
      outerArrayKml.pop();
      //polyPoints.removeAt(polyPoints.length-1);
    }
    if(outerPoints.length===0) {
      if(polyShape) {
        polyPoints = polyShape.getPath();
        if(polyPoints.length > 0) {
          polyPoints.removeAt(polyPoints.length-1);
          pointsArrayKml.pop();
          if(adder == 0) {
            logCoords();
          }
        }
      }
    }
  }
  if(polyPoints.length === 0) nextshape();
}
function counter(num){
  return adder = adder + num;
}
function holecreator(){
  var step = counter(1);
  if(step == 1){
    if(gob('stepdiv').innerHTML == "Finished"){
      adder = 0;
      return;
    }else{
      if(startMarker) startMarker.setMap(null);
      if(polyShape) polyShape.setMap(null);
      polyPoints = [];
      preparePolyline();
      gob('stepdiv').innerHTML = "Step 1";
      gob('coords1').value = 'You may now draw the outer boundary. When finished, click Hole to move on to the next step.'
      +' You do not have to click back onto the start -'
      +' the system will close the shape in the finished polygon.';
    }
  }
  if(step == 2){
    if(anotherhole == false) {
      // outer line is finished, in Polyline draw mode
      polyPoints.insertAt(polyPoints.length, startpoint); // let start and end meet
      outerPoints = polyPoints;
      holePolyArray.push(outerPoints);
      outerShape = polyShape;
    }
    gob('stepdiv').innerHTML = "Step 2";
    gob('coords1').value = 'You may now draw an inner boundary. Click Hole again to see the finished polygon. '+
    'You may draw more than one hole - click Next Hole and draw before you click Hole.';
    if(anotherhole == true) {
      // a hole has been drawn, another is about to be drawn
      if(polyShape && polyPoints.length == 0) {
        polyShape.setMap(null);
        gob('coords1').value = 'Oops! Not programmed yet, but you may continue drawing holes. '+
        'Everything you have created will show up when you click Hole again.';
      }else{
        polyPoints.insertAt(polyPoints.length, startpoint);
        holePolyArray.push(polyPoints);
        if(innerArray.length>0) innerArrays.push(innerArray);
        if(innerArrayKml.length>0) innerArraysKml.push(innerArrayKml);
        holeShapes.push(polyShape);
        innerArray = [];
      }
    }
    polyPoints = [];
    preparePolyline();
    if(startMarker) startMarker.setMap(null);
  }
  if(step == 3){
    if(startMarker) startMarker.setMap(null);
    if(outerShape) outerShape.setMap(null);
    if(polyShape) polyShape.setMap(null);
    if(polyPoints.length>0) holePolyArray.push(polyPoints);
    if(innerArray.length>0) innerArrays.push(innerArray);
    if(innerArrayKml.length>0) innerArraysKml.push(innerArrayKml);
    drawpolywithhole();
    gob('stepdiv').innerHTML = "Finished";
    adder = 0;
    logCoords();
  }
}
function drawpolywithhole() {
  if(holeShapes.length > 0) {
    for(var i = 0; i < holeShapes.length; i++) {
      holeShapes[i].setMap(null);
    }
  }
  var Points = new google.maps.MVCArray(holePolyArray);
  var polyOptions = {
    paths: Points,
    strokeColor: polygonstyles[pcur].color,
    strokeOpacity: polygonstyles[pcur].lineopac,
    strokeWeight: polygonstyles[pcur].width,
    fillColor: polygonstyles[pcur].fill,
    fillOpacity: polygonstyles[pcur].fillopac
  };
  polyShape = new google.maps.Polygon(polyOptions);
  polyShape.setMap(map);
  //Cursor position, when inside polyShape, is registered with this listener
  cursorposition(polyShape);
  anotherhole = false;
  startMarker = new google.maps.Marker({
      position: outerPoints.getAt(0),
      map: map});
  startMarker.setTitle("Polygon with hole");
}
function nexthole() {
  if(gob('stepdiv').innerHTML != "Finished") {
    if(outerPoints.length > 0) {
      adder = 1;
      anotherhole = true;
      drawnShapes.push(polyShape);
      holecreator();
    }
  }
}
function stopediting(){
  editing = false;
  gob('btnEditPoly').value = 'Edit lines';
  for(var i = 0; i < markers.length; i++) {
    markers[i].setMap(null);
  }
  for(var i = 0; i < midmarkers.length; i++) {
    midmarkers[i].setMap(null);
  }
  polyPoints = polyShape.getPath();
  markers = [];
  midmarkers = [];
  if(plmcur != placemarks.length-1) {
    placemarks[plmcur].shape = polyShape;
    drawnShapes.push(polyShape);
    addpolyShapelistener();
  }
  setstartMarker(polyPoints.getAt(0));
}
// the "Edit lines" button has been pressed
function editlines(){
  if(editing == true){
    stopediting();
  }else{
    if(outerArray.length > 0) {
      return;
    }
    polyPoints = polyShape.getPath();
    if(polyPoints.length > 0){

      //ART toolID = gob('toolchoice').value = 1; // editing is set to be possible only in polyline draw mode
      //ART setTool();

      if(startMarker) startMarker.setMap(null);
      /*polyShape.setOptions({
      editable: true
      });*/
      for(var i = 0; i < polyPoints.length; i++) {
        var marker = setmarkers(polyPoints.getAt(i));
        markers.push(marker);
        if(i > 0) {
          var midmarker = setmidmarkers(polyPoints.getAt(i));
          midmarkers.push(midmarker);
        }
      }
      editing = true;
      gob('btnEditPoly').value = 'Stop edit';
    }
  }
}
function setmarkers(point) {
  var marker = new google.maps.Marker({
      position: point,
      map: map,
      icon: imageNormal,
      raiseOnDrag: false,
      draggable: true
  });
  google.maps.event.addListener(marker, "mouseover", function() {
      marker.setIcon(imageHover);
  });
  google.maps.event.addListener(marker, "mouseout", function() {
      marker.setIcon(imageNormal);
  });
  google.maps.event.addListener(marker, "drag", function() {
      for (var i = 0; i < markers.length; i++) {
        if (markers[i] == marker) {
          polyShape.getPath().setAt(i, marker.getPosition());
          movemidmarker(i);
          break;
        }
      }
      polyPoints = polyShape.getPath();
      var stringtobesaved = marker.getPosition().lat().toFixed(7) + ',' + marker.getPosition().lng().toFixed(7);
      var kmlstringtobesaved = marker.getPosition().lng().toFixed(7) + ' ' + marker.getPosition().lat().toFixed(7);
      pointsArray.splice(i,1,stringtobesaved);
      pointsArrayKml.splice(i,1,kmlstringtobesaved);
      logCoords();
  });
  google.maps.event.addListener(marker, "click", function() {
      for (var i = 0; i < markers.length; i++) {
        if (markers[i] == marker && markers.length != 1) {
          marker.setMap(null);
          markers.splice(i, 1);
          polyShape.getPath().removeAt(i);
          removemidmarker(i);
          break;
        }
      }
      polyPoints = polyShape.getPath();
      if(markers.length > 0) {
        pointsArray.splice(i,1);
        pointsArrayKml.splice(i,1);
        logCoords();
      }
  });
  return marker;
}
function setmidmarkers(point) {
  var prevpoint = markers[markers.length-2].getPosition();
  var marker = new google.maps.Marker({
      position: new google.maps.LatLng(
        point.lat() - (0.5 * (point.lat() - prevpoint.lat())),
        point.lng() - (0.5 * (point.lng() - prevpoint.lng()))
      ),
      map: map,
      icon: imageNormalMidpoint,
      raiseOnDrag: false,
      draggable: true
  });
  google.maps.event.addListener(marker, "mouseover", function() {
      marker.setIcon(imageNormal);
  });
  google.maps.event.addListener(marker, "mouseout", function() {
      marker.setIcon(imageNormalMidpoint);
  });
  /*google.maps.event.addListener(marker, "dragstart", function() {
  for (var i = 0; i < midmarkers.length; i++) {
  if (midmarkers[i] == marker) {
  var tmpPath = tmpPolyLine.getPath();
  tmpPath.push(markers[i].getPosition());
  tmpPath.push(midmarkers[i].getPosition());
  tmpPath.push(markers[i+1].getPosition());
  break;
  }
  }
  });
  google.maps.event.addListener(marker, "drag", function() {
  for (var i = 0; i < midmarkers.length; i++) {
  if (midmarkers[i] == marker) {
  tmpPolyLine.getPath().setAt(1, marker.getPosition());
  break;
  }
  }
  });*/
  google.maps.event.addListener(marker, "dragend", function() {
      for (var i = 0; i < midmarkers.length; i++) {
        if (midmarkers[i] == marker) {
          var newpos = marker.getPosition();
          var startMarkerPos = markers[i].getPosition();
          var firstVPos = new google.maps.LatLng(
            newpos.lat() - (0.5 * (newpos.lat() - startMarkerPos.lat())),
            newpos.lng() - (0.5 * (newpos.lng() - startMarkerPos.lng()))
          );
          var endMarkerPos = markers[i+1].getPosition();
          var secondVPos = new google.maps.LatLng(
            newpos.lat() - (0.5 * (newpos.lat() - endMarkerPos.lat())),
            newpos.lng() - (0.5 * (newpos.lng() - endMarkerPos.lng()))
          );
          var newVMarker = setmidmarkers(secondVPos);
          newVMarker.setPosition(secondVPos);//apply the correct position to the midmarker
          var newMarker = setmarkers(newpos);
          markers.splice(i+1, 0, newMarker);
          polyShape.getPath().insertAt(i+1, newpos);
          marker.setPosition(firstVPos);
          midmarkers.splice(i+1, 0, newVMarker);
          /*tmpPolyLine.getPath().removeAt(2);
          tmpPolyLine.getPath().removeAt(1);
          tmpPolyLine.getPath().removeAt(0);
          newpos = null;
          startMarkerPos = null;
          firstVPos = null;
          endMarkerPos = null;
          secondVPos = null;
          newVMarker = null;
          newMarker = null;*/
          break;
        }
      }
      polyPoints = polyShape.getPath();
      var stringtobesaved = newpos.lat().toFixed(7) + ',' + newpos.lng().toFixed(7);
      var kmlstringtobesaved = newpos.lng().toFixed(7) + ' ' + newpos.lat().toFixed(7);
      pointsArray.splice(i+1,0,stringtobesaved);
      pointsArrayKml.splice(i+1,0,kmlstringtobesaved);
      logCoords();
  });
  return marker;
}
function movemidmarker(index) {
  var newpos = markers[index].getPosition();
  if (index != 0) {
    var prevpos = markers[index-1].getPosition();
    midmarkers[index-1].setPosition(new google.maps.LatLng(
        newpos.lat() - (0.5 * (newpos.lat() - prevpos.lat())),
        newpos.lng() - (0.5 * (newpos.lng() - prevpos.lng()))
      ));
    //prevpos = null;
  }
  if (index != markers.length - 1) {
    var nextpos = markers[index+1].getPosition();
    midmarkers[index].setPosition(new google.maps.LatLng(
        newpos.lat() - (0.5 * (newpos.lat() - nextpos.lat())),
        newpos.lng() - (0.5 * (newpos.lng() - nextpos.lng()))
      ));
    //nextpos = null;
  }
  //newpos = null;
  //index = null;
}
function removemidmarker(index) {
  if (markers.length > 0) {//clicked marker has already been deleted
    if (index != markers.length) {
      midmarkers[index].setMap(null);
      midmarkers.splice(index, 1);
    } else {
      midmarkers[index-1].setMap(null);
      midmarkers.splice(index-1, 1);
    }
  }
  if (index != 0 && index != markers.length) {
    var prevpos = markers[index-1].getPosition();
    var newpos = markers[index].getPosition();
    midmarkers[index-1].setPosition(new google.maps.LatLng(
        newpos.lat() - (0.5 * (newpos.lat() - prevpos.lat())),
        newpos.lng() - (0.5 * (newpos.lng() - prevpos.lng()))
      ));
    //prevpos = null;
    //newpos = null;
  }
  //index = null;
}
//fill coords1 with kml output - not used
function showKML() {
  if(codeID != 1) {
    codeID = gob('codechoice').value = 1; // set KML
    setCode();
  }
  gob('coords1').value = kmlheading();
  for (var i = 0; i < placemarks.length; i++) {
    gob('coords1').value += placemarks[i].plmtext;
  }
  gob('coords1').value += kmlend();
}
//
//
//
function searchAddress(address) {
  geocoder.geocode({'address': address}, function(results, status) {
      if (status == google.maps.GeocoderStatus.OK) {
        var pos = results[0].geometry.location;
        map.setCenter(pos);
        //artem if(directionsYes == 1) drawDirections(pos);
        if(toolID == 5 && markerShape==null) drawPointMarker(pos);
      } else {
        alert("Geocode was not successful for the following reason: " + status);
      }
  });
}

function activateDirections() {
  directionsYes = 1;
  directionsintroduction();
}
function closethis(name){
  var el = gob(name);
  if(el){
    el.style.visibility = 'hidden';
  }
}
function showthis(name){
  var el = gob(name);
  if(el){
    el.style.visibility = 'visible';
  }
}
function iconoptions(chosenicon) {
  gob("st2").value = chosenicon;
  gob("currenticon").innerHTML = '<img src="'+chosenicon+'" alt="" />';
}


function mapzoom(){
  var mapZoom = map.getZoom();
  gob("myzoom").value = mapZoom;
}
function mapcenter(){
  var mapCenter = map.getCenter();
  var latLngStr = mapCenter.lat().toFixed(7) + ', ' + mapCenter.lng().toFixed(7);
  gob("centerofmap").value = latLngStr;
}
//
function color_html2kml(color){
  var newcolor ="FFFFFF";
  if(color.length == 7) newcolor = color.substring(5,7)+color.substring(3,5)+color.substring(1,3);
  return newcolor;
}
function color_hex2dec(color) {
  var deccolor = "255,0,0";
  var dec1 = parseInt(color.substring(1,3),16);
  var dec2 = parseInt(color.substring(3,5),16);
  var dec3 = parseInt(color.substring(5,7),16);
  if(color.length == 7) deccolor = dec1+','+dec2+','+dec3;
  return deccolor;
}
function getopacityhex(opa){
  var hexopa = "66";
  if(opa == 0) hexopa = "00";
  if(opa == .0) hexopa = "00";
  if(opa >= .1) hexopa = "1A";
  if(opa >= .2) hexopa = "33";
  if(opa >= .3) hexopa = "4D";
  if(opa >= .4) hexopa = "66";
  if(opa >= .5) hexopa = "80";
  if(opa >= .6) hexopa = "9A";
  if(opa >= .7) hexopa = "B3";
  if(opa >= .8) hexopa = "CD";
  if(opa >= .9) hexopa = "E6";
  if(opa == 1.0) hexopa = "FF";
  if(opa == 1) hexopa = "FF";
  return hexopa;
}
function kmlheading() {
  var heading = "";
  var styleforpolygon = "";
  var styleforrectangle = "";
  var styleforpolyline = "";
  var styleformarker = "";
  var i;
  heading = '<' + '?xml version="1.0" encoding="UTF-8"?>\n' +
  '<kml xmlns="http://www.opengis.net/kml/2.2">\n' +
  '<Document><name>'+docuname+'</name>\n' +
  '<description>'+docudesc+'</description>\n';
  for(i=0;i<polygonstyles.length;i++) {
    styleforpolygon += '<Style id="'+polygonstyles[i].name+'">\n' +
    '<LineStyle><color>'+polygonstyles[i].kmlcolor+'</color><width>'+polygonstyles[i].width+'</width></LineStyle>\n' +
    '<PolyStyle><color>'+polygonstyles[i].kmlfill+'</color></PolyStyle>\n' +
    '</Style>\n';
  }
  for(i=0;i<polylinestyles.length;i++) {
    styleforpolyline += '<Style id="'+polylinestyles[i].name+'">\n' +
    '<LineStyle><color>'+polylinestyles[i].kmlcolor+'</color><width>'+polylinestyles[i].width+'</width></LineStyle>\n' +
    '</Style>\n';
  }
  for(i=0;i<markerstyles.length;i++) {
    styleformarker += '<Style id="'+markerstyles[i].name+'">\n' +
    '<IconStyle><Icon><href>\n'+markerstyles[i].icon+'\n</href></Icon></IconStyle>\n' +
    '</Style>\n';
  }
  return heading+styleforpolygon+styleforpolyline+styleformarker;
}
function kmlend() {
  var ending;
  return ending = '</Document>\n</kml>';
}
/* START LOG FUNCTIONS
// write kml for polyline in text area
function logCode1(){
if (notext === true) return;
var code = ""; // placemarks[plmcur].style = polylinestyles[lcur].name
var kmltext1 = '<Placemark><name>'+placemarks[plmcur].name+'</name>\n' +
'<description>'+placemarks[plmcur].desc+'</description>\n' +
'<styleUrl>#'+placemarks[plmcur].style+'</styleUrl>\n' +
'<LineString>\n<tessellate>'+placemarks[plmcur].tess+'</tessellate>\n' +
'<altitudeMode>'+placemarks[plmcur].alt+'</altitudeMode>\n<coordinates>\n';
for(var i = 0; i < pointsArrayKml.length; i++) {
code += pointsArrayKml[i] + ',0\n';
}
kmltext2 = '</coordinates>\n</LineString>\n</Placemark>\n';
placemarks[plmcur].plmtext = kmlcode = kmltext1+code+kmltext2;
placemarks[plmcur].poly = "pl";
placemarks[plmcur].jscode = pointsArray;
placemarks[plmcur].kmlcode = pointsArrayKml;
gob('coords1').value = kmlheading()+kmltext1+code+kmltext2+kmlend();
}
// write kml for Directions in text area
function logCode1a(){
if (notext === true) return;
gob('coords1').value = "";
var code = "";
//var kmlMarker = "";
//var kmlMarkers = "";
var kmltext1 = '<Placemark><name>'+placemarks[dirline].name+'</name>\n' +
'<description>'+placemarks[dirline].desc+'</description>\n' +
'<styleUrl>#'+placemarks[dirline].style+'</styleUrl>\n' +
'<LineString>\n<tessellate>'+placemarks[dirline].tess+'</tessellate>\n' +
'<altitudeMode>'+placemarks[dirline].alt+'</altitudeMode>\n<coordinates>\n';
if(pointsArrayKml.length != 0) {
for(var i = 0; i < pointsArrayKml.length; i++) {
code += pointsArrayKml[i] + ',0\n';
}
placemarks[dirline].jscode = pointsArray;
placemarks[dirline].kmlcode = pointsArrayKml;
}
kmltext2 = '</coordinates>\n</LineString>\n</Placemark>\n';
placemarks[dirline].plmtext = kmltext1+code+kmltext2;
placemarks[dirline].poly = "pl";
gob('coords1').value = kmlheading()+kmltext1+code+kmltext2;
if(markersArrayKml.length != 0) {
for(i = 0; i < markersArrayKml.length; i++) {
var kmlMarker = "";
var m = dirline + 1;
kmlMarker += '<Placemark><name>'+placemarks[m+i].name+'</name>\n' +
'<description>'+addresssArray[i]+'</description>\n' +
'<styleUrl>#'+markerstyles[mcur].name+'</styleUrl>\n' +
'<Point>\n<coordinates>';
kmlMarker += markersArrayKml[i] + ',0\n';
kmlMarker += '</coordinates>\n</Point>\n</Placemark>\n';
placemarks[m+i].jscode = markersArray[i];
placemarks[m+i].kmlcode = markersArrayKml[i];
placemarks[m+i].plmtext = kmlMarker;
gob('coords1').value += kmlMarker;
}
}
//placemarks[dirline].plmtext = kmlcode = kmltext1+code+kmltext2+kmlMarkers;
gob('coords1').value += kmlend();
}
// write kml for polygon in text area
function logCode2(){
if (notext === true) return;
var code = "";
var kmltext1 = '<Placemark><name>'+placemarks[plmcur].name+'</name>\n' +
'<description>'+placemarks[plmcur].desc+'</description>\n' +
'<styleUrl>#'+placemarks[plmcur].style+'</styleUrl>\n' +
'<Polygon>\n<tessellate>'+placemarks[plmcur].tess+'</tessellate>\n' +
'<altitudeMode>'+placemarks[plmcur].alt+'</altitudeMode>\n' +
'<outerBoundaryIs><LinearRing><coordinates>\n';
if(pointsArrayKml.length != 0) {
for(var i = 0; i < pointsArrayKml.length; i++) {
code += pointsArrayKml[i] + ',0\n';
}
code += pointsArrayKml[0] + ',0\n';
placemarks[plmcur].jscode = pointsArray;
placemarks[plmcur].kmlcode = pointsArrayKml;
}
kmltext2 = '</coordinates></LinearRing></outerBoundaryIs>\n</Polygon>\n</Placemark>\n';
placemarks[plmcur].plmtext = kmlcode = kmltext1+code+kmltext2;
placemarks[plmcur].poly = "pg";
gob('coords1').value = kmlheading()+kmltext1+code+kmltext2+kmlend();
}
// write kml for polygon with hole
function logCode3(){
if (notext === true) return;
var code = "";
var kmltext = '<Placemark><name>'+placemarks[plmcur].name+'</name>\n' +
'<description>'+placemarks[plmcur].desc+'</description>\n' +
'<styleUrl>#'+placemarks[plmcur].style+'</styleUrl>\n' +
'<Polygon>\n<tessellate>'+placemarks[plmcur].tess+'</tessellate>\n' +
'<altitudeMode>'+placemarks[plmcur].alt+'</altitudeMode>\n' +
'<outerBoundaryIs><LinearRing><coordinates>\n';
for(var i = 0; i < outerArrayKml.length; i++) {
kmltext += outerArrayKml[i]+',0\n';
code += outerArrayKml[i]+',0\n';
}
kmltext += outerArrayKml[0]+',0\n';
code += outerArrayKml[0]+',0\n';
placemarks[plmcur].jscode = pointsArray;
placemarks[plmcur].kmlcode = outerArrayKml;
kmltext += '</coordinates></LinearRing></outerBoundaryIs>\n';
for(var m = 0; m < innerArraysKml.length; m++) {
kmltext += '<innerBoundaryIs><LinearRing><coordinates>\n';
for(var i = 0; i < innerArraysKml[m].length; i++) {
kmltext += innerArraysKml[m][i]+',0\n';
}
kmltext += innerArraysKml[m][0]+',0\n';
kmltext += '</coordinates></LinearRing></innerBoundaryIs>\n';
}
kmltext += '</Polygon>\n</Placemark>\n';
placemarks[plmcur].plmtext = kmlcode = kmltext;
gob('coords1').value = kmlheading()+kmltext+kmlend();
}
// write javascript
function logCode4(){
if (notext === true) return;
gob('coords1').value = 'var myCoordinates = [\n';
for(var i=0; i<pointsArray.length; i++){
if(i == pointsArray.length-1){
gob('coords1').value += 'new google.maps.LatLng('+pointsArray[i] + ')\n';
}else{
gob('coords1').value += 'new google.maps.LatLng('+pointsArray[i] + '),\n';
}
}
if(toolID == 1){
gob('coords1').value += '];\n';
var options = 'var polyOptions = {\n'
+'path: myCoordinates,\n'
+'strokeColor: "'+polylinestyles[lcur].color+'",\n'
+'strokeOpacity: '+polylinestyles[lcur].lineopac+',\n'
+'strokeWeight: '+polylinestyles[lcur].width+'\n'
+'}\n';
gob('coords1').value += options;
gob('coords1').value +='var it = new google.maps.Polyline(polyOptions);\n'
+'it.setMap(map);\n';
}
if(toolID == 2){
gob('coords1').value += '];\n';
var options = 'var polyOptions = {\n'
+'path: myCoordinates,\n'
+'strokeColor: "'+polygonstyles[pcur].color+'",\n'
+'strokeOpacity: '+polygonstyles[pcur].lineopac+',\n'
+'strokeWeight: '+polygonstyles[pcur].width+',\n'
+'fillColor: "'+polygonstyles[pcur].fill+'",\n'
+'fillOpacity: '+polygonstyles[pcur].fillopac+'\n'
+'}\n';
gob('coords1').value += options;
gob('coords1').value +='var it = new google.maps.Polygon(polyOptions);\n'
+'it.setMap(map);\n';
}
javacode = gob('coords1').value;
}
// write javascript for polygon with hole
function logCode5() {
if (notext === true) return;
var hstring = "";
gob('coords1').value = 'var outerPoints = [\n';
for(var i=0; i<outerArray.length; i++){
if(i == outerArray.length-1){
gob('coords1').value += 'new google.maps.LatLng('+outerArray[i] + ')\n'; // without trailing comma
}else{
gob('coords1').value += 'new google.maps.LatLng('+outerArray[i] + '),\n';
}
}
gob('coords1').value += '];\n';
for(var m=0; m<innerArrays.length; m++){
gob('coords1').value += 'var innerPoints'+m+' = [\n';
var holestring = 'innerPoints'+m;
if(m<innerArrays.length-1) holestring += ',';
hstring += holestring;
for(i=0; i<innerArrays[m].length; i++){
if(i == innerArrays[m].length-1){
gob('coords1').value += 'new google.maps.LatLng('+innerArrays[m][i] + ')\n';
}else{
gob('coords1').value += 'new google.maps.LatLng('+innerArrays[m][i] + '),\n';
}
}
gob('coords1').value += '];\n';
}
gob('coords1').value += 'var myCoordinates = [outerPoints,'+hstring+'];\n';
gob('coords1').value += 'var polyOptions = {\n'
+'paths: myCoordinates,\n'
+'strokeColor: "'+polygonstyles[pcur].color+'",\n'
+'strokeOpacity: '+polygonstyles[pcur].lineopac+',\n'
+'strokeWeight: '+polygonstyles[pcur].width+',\n'
+'fillColor: "'+polygonstyles[pcur].fill+'",\n'
+'fillOpacity: '+polygonstyles[pcur].fillopac+'\n'
+'};\n'
+'var it = new google.maps.Polygon(polyOptions);\n'
+'it.setMap(map);\n';
javacode = gob('coords1').value;
}
// write javascript or kml for rectangle
function logCode6() {
if (notext === true) return;
//placemarks[plmcur].style = polygonstyles[pcur].name;
if(codeID == 2) { // javascript
gob('coords1').value = 'var rectangle = new google.maps.Rectangle({\n'
+'map: map,\n'
+'fillColor: '+polygonstyles[pcur].fill+',\n'
+'fillOpacity: '+polygonstyles[pcur].fillopac+',\n'
+'strokeColor: '+polygonstyles[pcur].color+',\n'
+'strokeOpacity: '+polygonstyles[pcur].lineopac+',\n'
+'strokeWeight: '+polygonstyles[pcur].width+'\n'
+'});\n';
gob('coords1').value += 'var sWest = new google.maps.LatLng('+southWest.lat().toFixed(7)+','+southWest.lng().toFixed(7)+');\n'
+'var nEast = new google.maps.LatLng('+northEast.lat().toFixed(7)+','+northEast.lng().toFixed(7)+');\n'
+'var bounds = new google.maps.LatLngBounds(sWest,nEast);\n'
+'rectangle.setBounds(bounds);\n';
gob('coords1').value += '\n\\\\ Code for polyline rectangle\n';
gob('coords1').value += 'var myCoordinates = [\n';
gob('coords1').value += southWest.lat().toFixed(7) + ',' + southWest.lng().toFixed(7) + ',\n' +
southWest.lat().toFixed(7) + ',' + northEast.lng().toFixed(7) + ',\n' +
northEast.lat().toFixed(7) + ',' + northEast.lng().toFixed(7) + ',\n' +
northEast.lat().toFixed(7) + ',' + southWest.lng().toFixed(7) + ',\n' +
southWest.lat().toFixed(7) + ',' + southWest.lng().toFixed(7) + '\n';
gob('coords1').value += '];\n';
var options = 'var polyOptions = {\n'
+'path: myCoordinates,\n'
+'strokeColor: "'+polygonstyles[pcur].color+'",\n'
+'strokeOpacity: '+polygonstyles[pcur].lineopac+',\n'
+'strokeWeight: '+polygonstyles[pcur].width+'\n'
+'}\n';
gob('coords1').value += options;
gob('coords1').value +='var it = new google.maps.Polyline(polyOptions);\n'
+'it.setMap(map);\n';
javacode = gob('coords1').value;
}
if(codeID == 1) { // kml
var kmltext = '<Placemark><name>'+placemarks[plmcur].name+'</name>\n' +
'<description>'+placemarks[plmcur].desc+'</description>\n' +
'<styleUrl>#'+placemarks[plmcur].style+'</styleUrl>\n' +
'<Polygon>\n<tessellate>'+placemarks[plmcur].tess+'</tessellate>\n' +
'<altitudeMode>'+placemarks[plmcur].alt+'</altitudeMode>\n' +
'<outerBoundaryIs><LinearRing><coordinates>\n';
kmltext += southWest.lng().toFixed(7) + ',' + southWest.lat().toFixed(7) + ',0\n' +
southWest.lng().toFixed(7) + ',' + northEast.lat().toFixed(7) + ',0\n' +
northEast.lng().toFixed(7) + ',' + northEast.lat().toFixed(7) + ',0\n' +
northEast.lng().toFixed(7) + ',' + southWest.lat().toFixed(7) + ',0\n' +
southWest.lng().toFixed(7) + ',' + southWest.lat().toFixed(7) + ',0\n';
kmltext += '</coordinates></LinearRing></outerBoundaryIs>\n</Polygon>\n</Placemark>\n';
placemarks[plmcur].plmtext = kmlcode = kmltext;
gob('coords1').value = kmlheading()+kmltext+kmlend();
}
}
function logCode7() { // javascript for circle
if (notext === true) return;
//placemarks[plmcur].style = circlestyles[ccur].name;
gob('coords1').value = 'var circle = new google.maps.Circle({\n'
+'map: map,\n'
+'center: new google.maps.LatLng('+centerPoint.lat().toFixed(7)+','+centerPoint.lng().toFixed(7)+'),\n'
+'fillColor: '+circlestyles[ccur].fill+',\n'
+'fillOpacity: '+circlestyles[ccur].fillopac+',\n'
+'strokeColor: '+circlestyles[ccur].color+',\n'
+'strokeOpacity: '+circlestyles[ccur].lineopac+',\n'
+'strokeWeight: '+circlestyles[ccur].width+'\n'
+'});\n';
gob('coords1').value += 'circle.setRadius('+calc+');\n';
javacode = gob('coords1').value;
}
function logCode8(){ //javascript for Marker
if(notext === true) return;
var text = 'var image = \''+markerstyles[mcur].icon+'\';\n'
+'var marker = new google.maps.Marker({\n'
+'position: '+placemarks[plmcur].point+',\n'
+'map: map, //global variable \'map\' from opening function\n'
+'icon: image\n'
+'});\n'
+'//Your content for the infowindow\n'
+'var html = \'<b>'+ placemarks[plmcur].name +'</b> <br/>'+ placemarks[plmcur].desc +'\';';
gob('coords1').value = text;
javacode = gob('coords1').value;
}
function logCode9() { //KML for marker
if(notext === true) return;
gob('coords1').value = "";
var kmlMarkers = "";
kmlMarkers += '<Placemark><name>'+placemarks[plmcur].name+'</name>\n' +
'<description>'+placemarks[plmcur].desc+'</description>\n' +
'<styleUrl>#'+placemarks[plmcur].style+'</styleUrl>\n' +
'<Point>\n<coordinates>';
kmlMarkers += placemarks[plmcur].kmlcode + ',0';
kmlMarkers += '</coordinates>\n</Point>\n</Placemark>\n';
//placemarks[plmcur].poly = "pl";
placemarks[plmcur].plmtext = kmlcode = kmlMarkers;
gob('coords1').value = kmlheading()+kmlMarkers+kmlend();
}
END LOG FUNCTIONS */
function setinfo(text) {
  var el = gob('coords1');
  if(el){
    el.value = text;
  }
}
function directionsintroduction() {
  setinfo('Ready for Directions. Create a route along roads with markers at chosen locations.\n'
    +'Click on the map, or enter an address and click "Search", to place a marker.\n'
    +'Lines will be drawn along roads from marker to marker.\n'
    +'Use "Delete Last Point" if you want to undo.\n'
    +'KML input may be done at any time for markers by clicking on them.\n'
    +'KML input for the line may be done by clicking on it after you have finished '
    +'drawing and clicked "Next shape".');
}

function markerintroduction() {
  setinfo('Ready for Marker. Click on the map, or enter an address and click "Search", to place a marker.\n');
  //+'You may enter your content for the infowindow with "KML input" even if your code choice is Javascript.\n'
  //+'Click "Next shape" before each additional marker.';
}
function polylineintroduction() {
  setinfo('Ready for Polyline. Click on the map. The code for the shape you create will be presented here.\n\n'
    //+'When finished with a shape, click Next shape and draw another shape, if you wish.\n'
    +'\nIf you want to edit a saved polyline or polygon, click on it. Then click Edit lines. '
    +'When editing, you may remove a point with a click on it.\n');
  //+'\nThe complete KML code for what you have created, is always available with Show KML.';
}
function polygonintroduction() {
  setinfo('Ready for Polygon. Click on the map. The code for the shape you create will be presented here. '
    +'The Maps API will automatically "close" any polygons by drawing a stroke connecting the last coordinate back to the '
    +'first coordinate for any given paths.\n'
    //+'\nTo create a polygon with hole(-s), click "Hole" before you start the drawing.\n'
    //+'\nWhen finished with a shape, click Next shape and draw another shape, if you wish.\n'
    +'\nIf you want to edit a saved polyline or polygon, click on it. Then click Edit lines. '
    +'When editing, you may remove a point with a click on it.\n');
  //+'\nThe complete KML code for what you have created, is always available with Show KML.';
}
function rectangleintroduction() {
  setinfo('Ready for Rectangle. Click two times on the map - first for the southwest and '+
    'then for the northeast corner. You may resize and move '+
    'the rectangle with the two draggable markers you then have.\n\n');
  //'The v3 Rectangle is a polygon. But in Javascript code mode an extra code for '+
  //'polyline is presented here in the text area.';
}
function circleintroduction() {
  setinfo('Ready for Circle. Click for center. Then click for radius distance. '+
    'You may resize and move the circle with the two draggable markers you then have.\n\n');
  //'KML code is not available for Circle.';
}

/*
* Artem's additions
*/

//
// extract coordinates from parameters
//
function loadParameters(val) {

  if (! val) {
    val = decodeURIComponent(document.location.search);
  }

  var matches = val.match(/\??(\S+)\s+(.*)/);
  if (! matches) {
    //toolID = 5;
    setTool(5);
    return;
  }
  var type = matches[1];
  var value = matches[2];

  switch (type) {
    case "p":
      matches = value.match(/POINT\((\S+)\s+(\S+)\)/i);
      break;
    case "r":  //rectangle
      matches = value.match(/POLYGON\(\((\S+)\s+(\S+),\s*(\S+)\s+(\S+),\s*(\S+)\s+(\S+),\s*(\S+)\s+(\S+),\s*\S+\s+\S+\)\)/i);
      break;

    case "c":  //circle
      matches = value.match(/LINESTRING\((\S+)\s+(\S+),\s*(\S+)\s+\S+,\s*\S+\s+\S+,\s*\S+\s+\S+\)/i);
      break;

    case "l":  ///polyline
    matches = value.match(/LINESTRING\((.+)\)/i);
    if (matches){
      matches = matches[1].match(/\S+\s+\S+(?:,|$)/g);
    }
    break;

    case "pl": //polygon
    matches = value.match(/POLYGON\(\((.+)\)\)/i);
    if (matches) {
      matches = matches[1].match(/\S+\s+\S+(?:,|$)/g);
    }
    break;
    default:
      setTool(5);
  }

  loadCoordinates(type, matches);
}

Number.prototype.toRad = function() {
  return this * Math.PI / 180;
}
Number.prototype.toDeg = function() {
  return this * 180 / Math.PI;
}
function destinationPoint(lat1, lon1, brng, dist) {
  dist = dist / 6371000;
  brng = brng.toRad();

  lat1 = lat1.toRad();
  lon1 = lon1.toRad();

  var lat2 = Math.asin(Math.sin(lat1) * Math.cos(dist) +
    Math.cos(lat1) * Math.sin(dist) * Math.cos(brng));

  var lon2 = lon1 + Math.atan2(Math.sin(brng) * Math.sin(dist) *
    Math.cos(lat1),
    Math.cos(dist) - Math.sin(lat1) *
    Math.sin(lat2));

  if (isNaN(lat2) || isNaN(lon2)) return null;

  return [lat2.toDeg(), lon2.toDeg()];
}

// apply manually enetered coordinates
function applyManualEntry(){
  var s = gob("coords1").value;
  s = s.replace(/[\b\t\n\v\f\r]/g, ' ');
  s = s.replace("  "," ");

  var arc = s.split(" ");
  var k, type = 5, coords = [''];

  switch (toolID) {
    case 5: //"point":
      type = "p";
      break;
    case 3: //"rectangle":
      type = "r";
      break;
    case 4: //"circle":
      type = "c";
      break;
    case 2: //"polygon":
      type = "pl";
      coords = [];
      break;
    case 1: //"path":
      type = "l";
      coords = [];
  }

  var islng = true;
  for (k=0; k<arc.length; k++){
    if(k==2 && type=="c" && arc[k].indexOf("r=")==0){
      var d = Number(arc[k].substr(2));
      if(isNaN(d)){
        alert(arc[k]+" is wrong radius value");
        return;
      }

      var resc = destinationPoint(coords[2], coords[1], 90, d);

      coords.push(resc[1]); //lng
      coords.push(resc[0]); //lat
      break;
    }
    var crd = Number(arc[k]);
    if(isNaN(crd)){
      alert(arc[k]+" is not number value");
      return;
    }else if(islng && Math.abs(crd)>180){
      alert(arc[k]+" is wrong longitude value");
      return;
    }else if(!islng && Math.abs(crd)>90){
      alert(arc[k]+" is wrong latitude value");
      return;
    }

    if(toolID<3){ //polygon or path
      if(!islng){
        coords.push(arc[k-1]+' '+arc[k]);
      }
    }else{
      coords.push(crd);
    }
    islng = !islng;
  }

  var isok = false;

  if (coords){
    if(type=="r" && coords.length<5){
      alert("Not enough coordinates for rectangle. Need at least 2 pairs");
    }else if(type=="l" && coords.length<2){
      alert("Not enough coordinates for path. Need at least 2 pairs");
    }else if(type=="pl" && coords.length<3){
      alert("Not enough coordinates for polygon. Need at least 3 pairs");
    }else if(type=="p" && coords.length<2){
      alert("Define at least one pair of coordinates for point/marker");
    }else if(type=="c" && coords.length<5){
      alert("For circle define at least one pair of coordinates and radious or 2 pairs of coordinates");
    }else{
      isok = true;
    }
  }
  if(isok){
    loadCoordinates(type, coords);
  }
}

//
//
//
function loadCoordinates(type, matches){

  var bounds,
  __point,
  points = [];


  clearMap();

  if(matches && matches.length>0){

    switch (type) {
      case "p":

        var point = new google.maps.LatLng(parseFloat(matches[2]), parseFloat(matches[1]));
        __point = point;

        setTool(5);
        addLatLng2(point);

        bounds = new google.maps.LatLngBounds(
          new google.maps.LatLng(point.lat() - 0.5, point.lng() - 0.5),
          new google.maps.LatLng(point.lat() + 0.5, point.lng() + 0.5));

        break;

      case "r":  //rectangle

      setTool(3);

      if(matches.length<6){
        matches.push(matches[3]);
        matches.push(matches[4]);
      }

      southWest = new google.maps.LatLng(parseFloat(matches[2]), parseFloat(matches[1]));
      northEast = new google.maps.LatLng(parseFloat(matches[6]), parseFloat(matches[5]));
      bounds = new google.maps.LatLngBounds(southWest, northEast);

      setstartMarker(southWest);
      createrectangle(northEast);

      break;

      case "c":  //circle

        var centre = new google.maps.LatLng(parseFloat(matches[2]), parseFloat(matches[1]));
        var oncircle = new google.maps.LatLng(parseFloat(matches[2]), parseFloat(matches[3]));
        //var radius = google.maps.geometry.spherical.computeDistanceBetween (centre, oncircle);  // (parseFloat(matches[3])-parseFloat(matches[1]));

        setTool(4);
        setstartMarker(centre);
        createcircle(oncircle);

        bounds = circle.getBounds();

        break;

      case "l":  ///polyline

      setTool(1);

      var minLat = 9999, maxLat = -9999, minLng = 9999, maxLng = -9999;
      for (var j=0; j < matches.length; ++j) {
        var match_matches = matches[j].match(/(\S+)\s+(\S+)(?:,|$)/);
        var point = new google.maps.LatLng(parseFloat(match_matches[2]), parseFloat(match_matches[1]));
        points.push(point);
        pointsArrayKml.push(matches[j].replace(",",""));

        if (point.lat() < minLat) minLat = point.lat();
        if (point.lat() > maxLat) maxLat = point.lat();
        if (point.lng() < minLng) minLng = point.lng();
        if (point.lng() > maxLng) maxLng = point.lng();
      }

      polyPoints = points;
      southWest = new google.maps.LatLng(minLat, minLng);
      northEast = new google.maps.LatLng(maxLat, maxLng);
      bounds = new google.maps.LatLngBounds(southWest, northEast);

      preparePolyline();

      break;

      case "pl": //polygon

      setTool(2);

      var minLat = 9999, maxLat = -9999, minLng = 9999, maxLng = -9999;
      for (var j=0; j < matches.length; ++j) {
        var match_matches = matches[j].match(/(\S+)\s+(\S+)(?:,|$)/);
        var point = new google.maps.LatLng(parseFloat(match_matches[2]), parseFloat(match_matches[1]));
        points.push(point);
        pointsArrayKml.push(matches[j].replace(",",""));

        if (point.lat() < minLat) minLat = point.lat();
        if (point.lat() > maxLat) maxLat = point.lat();
        if (point.lng() < minLng) minLng = point.lng();
        if (point.lng() > maxLng) maxLng = point.lng();
      }

      polyPoints = points;
      southWest = new google.maps.LatLng(minLat, minLng);
      northEast = new google.maps.LatLng(maxLat, maxLng);
      bounds = new google.maps.LatLngBounds(southWest, northEast);

      preparePolygon();

      break;
      default:
        setTool(5);
    }

  }

  /* ARTEM todo
  if (typeDescription) {
  document.getElementById("button-use").disabled = false;
  var button = document.getElementById("button-" + typeDescription);
  button.parentNode.parentNode.parentNode.className = "selected";
  }
  */

  if(_keepZoom && __point){
    //in case of "point" zoom to saved zoom
    map.setCenter(__point)
    map.setZoom(_keepZoom);
  }else{
    zoomToBounds(bounds);
  }

  logCoords(null);
}

function zoomToBounds(bounds) {

  map.fitBounds(bounds);
  map.panToBounds(bounds);

  var zoom = map.getZoom();
  if(zoom < 1 || zoom > 14){
    if (zoom < 1) zoom = 1;
    else if (zoom > 14) zoom = 14;
    map.setZoom(zoom);
  }else{
    map.setZoom(zoom-1);
  }
}

//
function logCoords(event){

  var el = gob('coords1');

  if(el && codeID==0){

    var r = function(x) { return Math.round(x*100000)/100000; };
    var value = "";
    if(toolID==5) {//"point":
      var point = markerShape.getPosition();
      value = r(point.lng())+" "+r(point.lat());
    }else if (toolID==4){//"circle":
      type = "c";

      var lat = r(circle.getCenter().lat());
      var lng = r(circle.getCenter().lng());
      var rad = r(circle.getRadius());
      value = lng+" "+lat+" r="+rad;
    }else {
      value = pointsArrayKml.join("\n");
    }

    el.value = value;

    gob('btnSave').disabled = (value!="")?"":"disabled";

  }else{
    /* NOT USED
    if(codeID == 1 && toolID == 1) logCode1();
    if(codeID == 1 && toolID == 2 && outerArray.length == 0) logCode2();
    if(codeID == 1 && toolID == 2 && outerArray.length > 0) logCode3();
    if(codeID == 2 && toolID == 1) logCode4(); // write Google javascript
    if(codeID == 2 && toolID == 2 && outerArray.length == 0) logCode4();
    if(codeID == 2 && toolID == 2 && outerArray.length > 0) logCode5();
    if(toolID == 3) { // rectangle
    if(codeID == 1) logCode2();
    if(codeID == 2) logCode6();
    }
    if(toolID == 4) { // circle
    logCode7()
    }
    if(toolID == 5) { // marker
    if(codeID == 1) logCode9();
    if(codeID == 2) logCode8();
    }
    */
  }
}

/*
* Returns values
*/
function closeWithValue() {
  var type, value;
  var r = function(x) { return Math.round(x*10000000)/10000000; };
  switch (toolID) {
    case 5: //"point":
    type = "p";
    if(markerShape){
      var point = markerShape.getPosition();
      value = "POINT("+r(point.lng())+" "+r(point.lat())+")";
    }else{
      value = null;
    }
    break;

    case 3: //"rectangle":
      type = "r";
      value = "POLYGON((" + pointsArrayKml.join(",") + "," + pointsArrayKml[0] + "))";
      break;

    case 4: //"circle":
      type = "c";

      var lat = r(circle.getCenter().lat());
      var lng = r(circle.getCenter().lng());
      var rad = r(circle.getRadius());

      var bounds = circle.getBounds();

      // I need only 2 points
      value = "LINESTRING("+lng+" "+lat+","+r(bounds.getNorthEast().lng())+" "+lat+","+
      r(bounds.getSouthWest().lng())+" "+r(bounds.getSouthWest().lat())+","+
      r(bounds.getSouthWest().lng())+" "+r(bounds.getNorthEast().lat())+
      ")";

      //value = "LINESTRING("+lng+" "+lat+","+(lng+rad)+" "+lat+","+(lng-rad)+" "+(lat-rad)+","+(lng-rad)+" "+(lat+rad)+")";

      break;

    case 2: //"polygon":
    type = "pl";
    if(pointsArrayKml.length>2){
      value = "POLYGON((" + pointsArrayKml.join(",") + "," + pointsArrayKml[0] + "))";
    }
    break;

    case 1: //"path":
    type = "l";
    if(pointsArrayKml.length>1){
      value = "LINESTRING(" + pointsArrayKml.join(",") + ")";
    }
  }

  if(top.HAPI){
    window.close(type, value);
  }else{
    alert(type+" "+value);
  }
}

/**
 * mode: 0 - both, 1 -image layers, 2 - kml
 */
function _load_layers(mode) {

  var baseurl = top.HEURIST.basePath + "viewers/map/showMap.php";
  var callback = _updateLayersList;
  var params =  "ver=1&layers="+mode+"&db="+top.HEURIST.database.name;
  top.HEURIST.util.getJsonData(baseurl, callback, params);
}

/**
 * fill list of layers
 */
function _updateLayersList(context){

  if(!top.HEURIST.util.isnull(context)) {


    var ind,
    elem = document.getElementById('cbLayers'),
    s = "<option value='-1'>none</option>",
    selIndex = -1;

    systemAllLayers = context.layers;


    for (ind in context.geoObjects) {
      if(!top.HEURIST.util.isnull(ind))
        {
        geoobj = context.geoObjects[ind];
        if(geoobj.type === "kmlfile"){

          var url = top.HEURIST.baseURL;
          url += "records/files/downloadFile.php?db=" + top.HEURIST.database.name + "&ulf_ID="+geoobj.fileid;
          geoobj.url = url;

          systemAllLayers.push(geoobj);
        }
      }
    }


    for (ind in systemAllLayers) {
      if(!top.HEURIST.util.isnull(ind))
        {
        if(top.HEURIST.util.isnull(systemAllLayers[ind].isbackground) || systemAllLayers[ind].isbackground)
          {

          var sTitle = systemAllLayers[ind].title;
          if(top.HEURIST.util.isempty(sTitle)){
            sTitle = context.records[systemAllLayers[ind].rec_ID].title;
          }
          if(top.HEURIST.util.isempty(sTitle)){
            sTitle = 'Undefined title. Rec#'+systemAllLayers[ind].rec_ID;
          }

          s = s + "<option value='" + ind + "'>"+ sTitle +"</option>";

          if( (!top.HEURIST.util.isnull(currentBackgroundLayer)) &&
            systemAllLayers[ind].rec_ID === currentBackgroundLayer){
            selIndex = ind;
          }
        }
      }
    }

    elem.innerHTML = s;

    if(selIndex>=0){
      setTimeout(function() {
          loadLayer2(selIndex);
          elem.selectedIndex = Number(selIndex)+1;
        }, 2000);
    }

  }
}

/**
 *
 */
function loadLayer(event){
  var val = Number(event.target.value);
  loadLayer2(val);
}

function loadLayer2(index){

  if(isNaN(index) || index < 0){
    RelBrowser.Mapping.addLayers([], 0);
  }else{
    currentBackgroundLayer = systemAllLayers[index].rec_ID;
    RelBrowser.Mapping.addLayers([systemAllLayers[index]], 1);
  }
}

function keepExtent() {
  if (!map) return;

  var currentLatLng = map.getCenter();
  var currentZoom = map.getZoom();
  var currentMap = map.getMapTypeId(); //getCurrentMapType().getUrlArg();
  var viewString = currentLatLng.lat() + "," + currentLatLng.lng() + "@" + currentZoom + ":" + currentMap+":"+currentBackgroundLayer;

  top.HEURIST.util.setDisplayPreference("gigitiser-view", viewString);

  var button = document.getElementById("btnSaveExtent");
  button.value = "View saved";
  button.style.color = "red";
  setTimeout(function() { button.value = "Remember view"; button.style.color = ""; }, 2000);
}

