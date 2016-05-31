/**
* Class to work with OS Timemap.js and Vis timeline. 
* It allows initialization of mapping and timeline controls and fills data for these controls
* 
* @param _map - id of map element container 
* @param _timeline - id of timeline element
* @param _basePath - need to specify path to timemap assets (images)
* #param mylayout - layout object that contains map and timeline
* @returns {Object}
* @see editing_input.js
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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


function hMappingDraw(_mapdiv_id) {
    var _className = "MappingDraw",
    _version   = "0.4";

    var mapdiv_id = null;
    
      var drawingManager;
      var selectedShape;
      var colors = ['#1E90FF', '#FF1493', '#32CD32', '#FF8C00', '#4B0082'];
      var selectedColor;
      var colorButtons = {};
      var gmap;
      var deleteMenu;
      var overlays = []; //all objects on map
      var geocoder = null;
      var map_viewpoints = []; //saved in user preferences (session) map viewpoints (bounds)

      function clearSelection() {
        if (selectedShape) {
          if(selectedShape.type != google.maps.drawing.OverlayType.MARKER) selectedShape.setEditable(false);
          selectedShape = null;
        }
        $('#coords1').val('');
      }

      function setSelection(shape) {
        clearSelection();
        selectedShape = shape;
        if(shape.type != google.maps.drawing.OverlayType.MARKER) shape.setEditable(true);
        selectColor(shape.get('fillColor') || shape.get('strokeColor'));
        _fillCoordinates(shape);
      }

      function _deleteSelectedShape() {
        if (selectedShape) {
          for(i in overlays){
              if(overlays[i]==selectedShape){
                    overlays.splice(i,1);
                    break;
              }
          }  
            
          selectedShape.setMap(null);
          clearSelection();
        }
      }

      function _deleteAllShapes() {
          clearSelection();
          while(overlays[0]){
            overlays.pop().setMap(null);
          }          
      }
      
      //
      // start color methods ------------------------------------------------------
      //
      function selectColor(color) {
        selectedColor = color;
        for (var i = 0; i < colors.length; ++i) {
          var currColor = colors[i];
          colorButtons[currColor].style.border = currColor == color ? '2px solid #789' : '2px solid #fff';
        }

        // Retrieves the current options from the drawing manager and replaces the
        // stroke or fill color as appropriate.
        var polylineOptions = drawingManager.get('polylineOptions');
        polylineOptions.strokeColor = color;
        drawingManager.set('polylineOptions', polylineOptions);

        var rectangleOptions = drawingManager.get('rectangleOptions');
        rectangleOptions.fillColor = color;
        drawingManager.set('rectangleOptions', rectangleOptions);

        var circleOptions = drawingManager.get('circleOptions');
        circleOptions.fillColor = color;
        drawingManager.set('circleOptions', circleOptions);

        var polygonOptions = drawingManager.get('polygonOptions');
        polygonOptions.fillColor = color;
        drawingManager.set('polygonOptions', polygonOptions);
      }

      //start color functions
      function setSelectedShapeColor(color) {
        if (selectedShape) {
          if (selectedShape.type == google.maps.drawing.OverlayType.POLYLINE) {
            selectedShape.set('strokeColor', color);
          } else {
            selectedShape.set('fillColor', color);
          }
        }
      }

      function makeColorButton(color) {
        var button = document.createElement('span');
        button.className = 'color-button';
        button.style.backgroundColor = color;
        google.maps.event.addDomListener(button, 'click', function() {
          selectColor(color);
          setSelectedShapeColor(color);
        });

        return button;
      }

       function buildColorPalette() {
         var colorPalette = document.getElementById('color-palette');
         for (var i = 0; i < colors.length; ++i) {
           var currColor = colors[i];
           var colorButton = makeColorButton(currColor);
           colorPalette.appendChild(colorButton);
           colorButtons[currColor] = colorButton;
         }
         selectColor(colors[0]);
       }
      //
      // END color methods ------------------------------------------------------
      //
    function _fillCoordinates(shape){
        if(shape!=null){
            sCoords = '';
            if(shape.type==google.maps.drawing.OverlayType.POLYGON || 
               shape.type==google.maps.drawing.OverlayType.POLYLINE) {
                
                processPoints(shape.getPath(), function(latLng){
                    sCoords = sCoords+formatPnt(latLng)+'\n';
                }, shape);
            }else if(shape.type==google.maps.drawing.OverlayType.RECTANGLE){
                
                var bnd = shape.getBounds();
                sCoords = formatPnt(bnd.getSouthWest()) + '\n' + formatPnt(bnd.getNorthEast());  
                
            }else if(shape.type==google.maps.drawing.OverlayType.CIRCLE){
                
                var radius = shape.getRadius();
                if(radius>0)
                    sCoords = formatPnt(shape.getCenter())+'\nr='+radius.toFixed(2);
                
            }else if(shape.type==google.maps.drawing.OverlayType.MARKER){

                sCoords = formatPnt(shape.getPosition());
                
            }
            $('#coords1').val(sCoords);
        }
    }
    
    function processPoints(geometry, callback, thisArg) {
      if (geometry instanceof google.maps.LatLng) {
        callback.call(thisArg, geometry);
      } else if (geometry instanceof google.maps.Data.Point) {
        callback.call(thisArg, geometry.get());
      } else {
        geometry.getArray().forEach(function(g) {
          processPoints(g, callback, thisArg);
        });
      }
    }    
    
    //
    // on vertex edit
    //
    function _onPathComplete(shape) {

        // complete functions
        var thePath = shape.getPath();

        google.maps.event.addListener(shape, 'rightclick', function(e) {
            // Check if click was on a vertex control point
            if (e.vertex == undefined) {
                return;
            }
            deleteMenu.open(gmap, shape.getPath(), e.vertex);
        });           

        //fill coordinates on vertex edit
        function __fillCoordinates() {
            _fillCoordinates(shape);
        }
        google.maps.event.addListener(thePath, 'set_at', __fillCoordinates);
        google.maps.event.addListener(thePath, 'insert_at', __fillCoordinates);
        google.maps.event.addListener(thePath, 'remove_at', __fillCoordinates);

        _fillCoordinates(shape);
    } 
    //
    // on circle complete
    //   
    function _onCircleComplete(shape) {
        google.maps.event.addListener(shape, 'radius_changed', function(){ _fillCoordinates(shape); });
        google.maps.event.addListener(shape, 'center_changed', function(){ _fillCoordinates(shape); });
        _fillCoordinates(shape);
    }
    //
    //
    //
    function _onRectangleComplete(shape) {
        google.maps.event.addListener(shape, 'bounds_changed', function(){ _fillCoordinates(shape); });
        //google.maps.event.addListener(rectangle, 'dragend', __fillCoordinates);
        _fillCoordinates(shape);
    }
    //
    //
    //
    function _onMarkerAdded(newShape){
        overlays.push(newShape);
        google.maps.event.addListener(newShape, 'rightclick', function(e) {
            deleteMenu.open(gmap, newShape, newShape.getPosition());
        });                     
        google.maps.event.addListener(newShape, 'click', function() {
            setSelection(newShape);
        });
        google.maps.event.addListener(newShape, 'dragend', function(event) {
            setSelection(newShape);
        });
    }
    //
    //
    //
    function _onNonMarkerAdded(newShape){
            overlays.push(newShape);
            drawingManager.setDrawingMode(null);
            setSelection(newShape);
            // Add an event listener that selects the newly-drawn shape when the user
            // mouses down on it.
            google.maps.event.addListener(newShape, 'click', function() {
                setSelection(newShape);
            });
    }

    //
    // init map 
    //
    function _init(_mapdiv_id) {
        
        _initUIcontrols();
        
        mapdiv_id = _mapdiv_id;

        map_viewpoints = top.HAPI4.get_prefs('map_viewpoints');
        
        var map = new google.maps.Map(document.getElementById(mapdiv_id), {
          zoom: 2,
          center: new google.maps.LatLng(0,0), //22.344, 114.048),
          mapTypeId: google.maps.MapTypeId.ROADMAP,
          disableDefaultUI: true,
          zoomControl: true
        });
        
        gmap = map;

        var polyOptions = {
          strokeWeight: 0,
          fillOpacity: 0.45,
          editable: true
        };
        // Creates a drawing manager attached to the map that allows the user to draw
        // markers, lines, and shapes.
        drawingManager = new google.maps.drawing.DrawingManager({
          drawingMode: google.maps.drawing.OverlayType.POLYGON,
          markerOptions: {
            draggable: true
          },
          polylineOptions: {
            editable: true
          },
          rectangleOptions: polyOptions,
          circleOptions: polyOptions,
          polygonOptions: polyOptions,
          map: gmap
        });
        
        deleteMenu = new DeleteMenu();
                
        google.maps.event.addListener(drawingManager, 'polygoncomplete',_onPathComplete);
        google.maps.event.addListener(drawingManager, 'polylinecomplete',_onPathComplete);
        google.maps.event.addListener(drawingManager, 'circlecomplete', _onCircleComplete);        
        google.maps.event.addListener(drawingManager, 'rectanglecomplete', _onRectangleComplete);        

        google.maps.event.addListener(drawingManager, 'overlaycomplete', function(e) {
            var newShape = e.overlay;
            newShape.type = e.type;
            
          if (e.type != google.maps.drawing.OverlayType.MARKER) {
                // Switch back to non-drawing mode after drawing a shape.
                _onNonMarkerAdded(newShape);
          }else{
                _onMarkerAdded(newShape);
                setSelection(newShape);
          }
          //google.maps.drawing.OverlayType.MARKER
        });

        // Clear the current selection when the drawing mode is changed, or when the
        // map is clicked.
        google.maps.event.addListener(drawingManager, 'drawingmode_changed', clearSelection); 
        google.maps.event.addListener(gmap, 'click', clearSelection);
        google.maps.event.addListener(gmap, 'mousemove', function (event) {
                    var pnt = event.latLng;
                    $('#coords2').html(formatPnt(pnt),4);
          });        
        
        buildColorPalette();
    } 
    
    //
    //
    //
    function formatPnt(pnt, d){
            if(isNaN(d)) d = 7;
            var lat = pnt.lat();
            lat = lat.toFixed(d);
            var lng = pnt.lng();
            lng = lng.toFixed(d);
            return lat + " " + lng;               
    }
    //
    //
    //
    function _startGeocodingSearch(){
    
        if(geocoder==null){
            geocoder = new google.maps.Geocoder();
        }
        
        var address = document.getElementById("input_search").value;
        
        geocoder.geocode( { 'address': address}, function(results, status) {
            
          if (status == google.maps.GeocoderStatus.OK) {
              
            if(results[0].geometry.viewport){
                gmap.fitBounds(results[0].geometry.viewport);
            }else{
                gmap.setCenter(results[0].geometry.location);
            }  
            /*var marker = new google.maps.Marker({
                map: map,
                position: results[0].geometry.location
            });*/
          } else {
            alert(top.HR("Geocode was not successful for the following reason: ") + status);
          }
        });        
    }
    
    function _zoomToSelection() {
        
        var bounds = null;
        var shape = selectedShape;
        if(shape!=null){
            if(shape.type==google.maps.drawing.OverlayType.POLYGON || 
               shape.type==google.maps.drawing.OverlayType.POLYLINE) {

                    bounds = new google.maps.LatLngBounds();
                    //map.data.forEach(function(feature) {
                    //    processPoints(feature.getGeometry(), bounds.extend, bounds);
                    //});
                    processPoints(shape.getPath(), bounds.extend, bounds);
                    
            }else if(shape.type==google.maps.drawing.OverlayType.RECTANGLE ||
                     shape.type==google.maps.drawing.OverlayType.CIRCLE){
                
                    bounds = shape.getBounds();
                
            }else if(shape.type==google.maps.drawing.OverlayType.MARKER){
                    gmap.setCenter(shape.getPosition());   
            }
        
        }
        
        if(bounds!=null){
            gmap.fitBounds(bounds);
        }
    }
    
    //
    //
    //
    function _applyCoordsForSelectedShape(){
        
        var type = drawingManager.getDrawingMode();
        
        if (selectedShape!=null) {
            type = selectedShape.type;
        }
        if(type==null){
            alert(top.HR('Select shape on map first or define drawing mode'));
            return;
        }
        
        var sCoords = $("#coords1").val();
        var coords = _parseManualEntry(sCoords, type);
        var res = _loadShape(coords, type);
        if(res && selectedShape!=null){
            _zoomToSelection();
        }
    }
    
    Number.prototype.toRad = function() {
      return this * Math.PI / 180;
    }
    Number.prototype.toDeg = function() {
      return this * 180 / Math.PI;
    }
    //
    // get point within given radius
    //
    function _destinationPoint(latLong, brng, dist) {
      dist = dist / 6371000;
      brng = brng.toRad();

      var lat1 = latLong.lat.toRad();
      var lon1 = latLong.lng.toRad();

      var lat2 = Math.asin(Math.sin(lat1) * Math.cos(dist) +
        Math.cos(lat1) * Math.sin(dist) * Math.cos(brng));

      var lon2 = lon1 + Math.atan2(Math.sin(brng) * Math.sin(dist) *
        Math.cos(lat1),
        Math.cos(dist) - Math.sin(lat1) *
        Math.sin(lat2));

      if (isNaN(lat2) || isNaN(lon2)) return null;

      return {lat:lat2.toDeg(), lng:lon2.toDeg()};
    }
    
    //
    //
    //
    function _getDistance(latLong1, latLong2) {
        var R = 6371000; // earth's radius in meters
        var dLat = (latLong2.lat-latLong1.lat).toRad(); //* Math.PI / 180;
        var dLon = (latLong2.lng-latLong1.lng).toRad(); //* Math.PI / 180;
        var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
        Math.cos(latLong1.lat.toRad() ) * Math.cos(latLong2.lat.toRad() ) *
        Math.sin(dLon/2) * Math.sin(dLon/2);
        var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        var d = R * c;
        return d;
    }    
    
    //
    //
    //        
    function _parseManualEntry(sCoords, type){
        
        var s = sCoords.replace(/[\b\t\n\v\f\r]/g, ' ');
        s = s.replace("  "," ").trim();
        var arc = s.split(" ");  
        var coords = []; //Array of LatLngLiteral
        
        var islat = true, k;
        for (k=0; k<arc.length; k++){
            
            //special case for circle
            if(k==2 && type==google.maps.drawing.OverlayType.CIRCLE && arc[k].indexOf("r=")==0){
              var d = Number(arc[k].substr(2));
              if(isNaN(d)){
                alert(arc[k]+" is wrong radius value");
                return null;
              }

              var resc = _destinationPoint(coords[0], 90, d);
              if(resc!=null){
                    coords.push({radius:d});
              }else{
                    alert("Cannot create circle");
                    return null;
              }
              break;
            }
            
            var crd = Number(arc[k]);
            if(isNaN(crd)){
              alert(arc[k]+" is not number value");
              return null;
            }else if(!islat && Math.abs(crd)>180){
              alert(arc[k]+" is wrong longitude value");
              return null;
            }else if(islat && Math.abs(crd)>90){
              alert(arc[k]+" is wrong latitude value");
              return null;
            }
            islat = !islat;
            if(islat)
                coords.push({lat:Number(arc[k-1]), lng:crd});
        }//for
        
        return coords;
    }
    
    //coords  Array of LatLngLiteral
    function _loadShape(coords, type){

      var res = false;  
    
      if (coords && coords.length>0){
        if(type==google.maps.drawing.OverlayType.POLYGON){
            if(coords.length<3){
                alert("Not enough coordinates for rectangle. Need at least 3 pairs");
            }else{
                if (selectedShape) {
                    selectedShape.setPath(coords);
                }else{
                    var opts = drawingManager.get('polygonOptions');
                    opts.paths = coords;
                    
                    var newShape = new google.maps.Polygon(opts);
                    newShape.setMap(gmap);   
                    newShape.type = google.maps.drawing.OverlayType.POLYGON;
                    
                    _onNonMarkerAdded(newShape);
                }
                _onPathComplete(selectedShape);
                res = true;
            }
        }else if(type==google.maps.drawing.OverlayType.POLYLINE){
            if(coords.length<2){
                alert("Not enough coordinates for path. Need at least 2 pairs");
            }else{
                if (selectedShape) {
                    selectedShape.setPath(coords);
                }else{
                    var opts = drawingManager.get('polylineOptions');
                    opts.path = coords;
                    
                    var newShape = new google.maps.Polyline(opts);
                    newShape.setMap(gmap);   
                    newShape.type = google.maps.drawing.OverlayType.POLYLINE;
                    
                    _onNonMarkerAdded(newShape);
                }
                _onPathComplete(selectedShape);
                res = true;
            }
            
            
        }else if (type==google.maps.drawing.OverlayType.MARKER){
            
                if (selectedShape) {
                    selectedShape.setPosition(coords[0]);
                }else{
                    var k, newShape;
                    for(k=0; k<coords.length; k++){
                        var opts = drawingManager.get('markerOptions');
                        opts.position = coords[k];
                        newShape = new google.maps.Marker(opts);
                        newShape.setMap(gmap);   
                        newShape.type = google.maps.drawing.OverlayType.MARKER;
                        _onMarkerAdded(newShape);
                    }
                    setSelection(newShape);
                }
                res = true;

        }else if (type==google.maps.drawing.OverlayType.RECTANGLE){
            if(coords.length<2){
                alert("Not enough coordinates for rectangle. Need at least 2 pairs");
            }else{
                
                var bounds = {south:coords[0].lat, west:coords[0].lng,  north:coords[1].lat, east:coords[1].lng };

                if (selectedShape) {
                    selectedShape.setBounds( bounds );
                }else{
                    var opts = drawingManager.get('rectangleOptions');
                    opts.bounds = bounds;
                    var newShape = new google.maps.Rectangle(opts);
                    newShape.setMap(gmap);   
                    newShape.type = google.maps.drawing.OverlayType.RECTANGLE;
                    
                    _onNonMarkerAdded(newShape);
                    _onRectangleComplete(newShape);
                }
                res = true;
            }
          
        }else if (type==google.maps.drawing.OverlayType.CIRCLE){

            if(coords.length<2){
                alert("Not enough coordinates for circle. Need at least 2 pairs or center and radius");
            }else{
                
                var radius = (coords[1].radius>0)
                                    ?coords[1].radius
                                    :_getDistance(coords[0], coords[1]) ;

                if (selectedShape) {
                    selectedShape.setCenter( coords[0] );
                    selectedShape.setRadius( radius );
                }else{
                    var opts = drawingManager.get('circleOptions');
                    opts.center = coords[0];
                    opts.radius = radius;
                    var newShape = new google.maps.Circle(opts);
                    newShape.setMap(gmap);   
                    newShape.type = google.maps.drawing.OverlayType.CIRCLE;
                    
                    _onNonMarkerAdded(newShape);
                    _onCircleComplete(newShape);
                }
                res = true;
            }
            
        }
      }        
        
      return res;
    }
    
    //
    // show GeoJSON as a set of separate overlays
    //
    function _loadGeoJSON(mdata){
        
        if (typeof(mdata) === "string" && !top.HEURIST4.util.isempty(mdata)){
            try{
                mdata = $.parseJSON(mdata);
                //mdata = JSON.parse( mdata );
            }catch(e){
                mdata = null;
                console.log('Not well formed JSON provided. Property names be quoted with double-quote characters');
            }
        }
        if(top.HEURIST4.util.isnull(mdata) || $.isEmptyObject(mdata)){
            alert('Wrong GeoJSON provided');
            return;            
        }
        
//FeatureCollection.features[feature]        
//feature.geometry.type  , coordinates
//GeometryCollection.geometries[{type: ,coordinates: },...]
        
        if(mdata.type == 'FeatureCollection'){
            var k = 0;
            for (k=0; k<mdata.features.length; k++){
                 _loadGeoJSON(mdata.features[k]); //another collection or feature
            }
        }else if(mdata.type == 'Feature' && !$.isEmptyObject(mdata.geometry)){
            
            function __loadGeoJSON_primitive(geometry){
            
                if($.isEmptyObject(geometry)){
                
                }else if(geometry.type=="GeometryCollection"){
                    var l;
                    for (l=0; l<geometry.geometries.length; l++){
                         _loadGeoJSON_primitive(geometry.geometries[l]); //another collection or feature
                    }
                }else{
                    
                    function _extractCoords(shapes, coords){
                        
                        function _isvalid_pnt(pnt){
                           return ($.isArray(pnt) && pnt.length==2 && 
                                    $.isNumeric(pnt[0]) && $.isNumeric(pnt[1]) &&
                                    Math.abs(pnt[0])<=180.0 && Math.abs(pnt[1])<=90.0);
                        }
                        
                        if(_isvalid_pnt(coords)){ //Marker
                            shapes.push([{lat:coords[1], lng:coords[0]}]);
                        }else if(_isvalid_pnt(coords[0])){
                            //  !isNaN(Number(coords[0])) && !isNaN(Number(coords[1])) ){ //this is point
                            var shape = [], m;
                            for (m=0; m<coords.length; m++){
                                pnt = coords[m];
                                if(_isvalid_pnt(pnt)){
                                    shape.push({lat:pnt[1], lng:pnt[0]});
                                }
                            }
                            shapes.push(shape);
                        }else{
                            var n;
                            for (n=0; n<coords.length; n++){
                                if($.isArray(coords[n]))
                                    _extractCoords(shapes, coords[n]);
                            }
                        }
                    }
                    
                    var shapes = [];
                    _extractCoords(shapes, geometry.coordinates);
                    
                    if(shapes.length>0){
                    
                        var type = null;
                        
                        if( geometry.type=="Point" || 
                            geometry.type=="MultiPoint"){
                           type = google.maps.drawing.OverlayType.MARKER;
                        }else if(geometry.type=="LineString" ||
                                 geometry.type=="MultiLineString"){
                           type = google.maps.drawing.OverlayType.POLYLINE;
                        }else if(geometry.type=="Polygon"||
                                 geometry.type=="MultiPolygon"){
                           type = google.maps.drawing.OverlayType.POLYGON;
                        }       
                        if(type!=null){
                            var i;
                            for (i=0; i<shapes.length; i++){
                                clearSelection();          
                                _loadShape(shapes[i], type);   
                            }
                        }
                    }
                    
                }
                
            }
            
            __loadGeoJSON_primitive(mdata.geometry)
        }
        
    }

    //
    //
    //
    function _getGeoJSON(){
        
        var k, points=[], polylines=[], polygones=[];
        for (k=0; k<overlays.length; k++){
            
            var shape = overlays[k];
            
            if(shape!=null){
                coords = [];
                if(shape.type==google.maps.drawing.OverlayType.POLYGON){
                    
                    processPoints(shape.getPath(), function(latLng){
                        coords.push([latLng.lng(), latLng.lat()]);
                    }, shape);
                    polygones.push(coords);
                    
                }else if (shape.type==google.maps.drawing.OverlayType.POLYLINE) {
                    
                    processPoints(shape.getPath(), function(latLng){
                        coords.push([latLng.lng(), latLng.lat()]);
                    }, shape);
                    polylines.push(coords);
                    
                }else if(shape.type==google.maps.drawing.OverlayType.RECTANGLE){
                    
                    var bnd = shape.getBounds();
                    var pnt1 = bnd.getSouthWest(),
                        pnt2 = bnd.getNorthEast();  
                    
                    coords = [[pnt1.lng(), pnt1.lat()],[pnt1.lng(), pnt2.lat()],
                                [pnt2.lng(), pnt2.lat()],[pnt2.lng(), pnt1.lat()]];
                    
                    polygones.push(coords);
                    
                }else if(shape.type==google.maps.drawing.OverlayType.CIRCLE){
                    
                        var latLng = shape.getCenter()
                        var radius = shape.getRadius();
                        
                      var degreeStep = 360 / 40;

                      for(var i = 0; i < 40; i++){
                        var gpos = google.maps.geometry.spherical.computeOffset(latLng, radius, degreeStep * i);
                        coords.push([gpos.lng(), gpos.lat()]);
                      };                        
/*
                        for (var i=0; i <= 40; ++i) {
                            var x = latLng.lng() + radius * Math.cos(i * 2*Math.PI / 40);
                            var y = latLng.lat() + radius * Math.sin(i * 2*Math.PI / 40);
                            coords.push([x, y]);
                        }                        
 */                       
                        polygones.push(coords);
                    
                }else if(shape.type==google.maps.drawing.OverlayType.MARKER){

                    var latLng = shape.getPosition();
                    coords.push([latLng.lng(), latLng.lat()]);
                    points.push(coords);
                    
                }
            }
        }//for overlays
        
        var geometries = [];
        
        if(points.length==1){
            geometries.push({type: "Point", coordinates: points[0]});
        }else if(points.length>1){
            geometries.push({type: "MultiPoint", coordinates: points});   
        }
        if(polylines.length==1){
            geometries.push({type: "LineString", coordinates: polylines[0]});
        }else if(polylines.length>1){
            geometries.push({type: "MultiLineString", coordinates: polylines});   
        }
        if(polygones.length==1){
            geometries.push({type: "Polygon", coordinates: polygones });
        }else if(polygones.length>1){
            geometries.push({type: "MultiPolygon", coordinates: polygones});   
        }
        
        var res = { type: "FeatureCollection", features: [{ type: "Feature", geometry: {},
        "properties": {}
        }]};
        
        if(geometries.length==1){
            res.features[0].geometry = geometries[0];
        }else{
            res.features[0].geometry = { type: "GeometryCollection", geometries:geometries };
        }
        
        
        return res;
    }    
    
    //
    // init UI controls
    //
    function _initUIcontrols(){

        //geocoding ------------------------------------------------------               
        $('#btn_search_start')
          .button({label: top.HR("Start search"), text:false, icons: {
                      secondary: "ui-icon-search"
          }})
          .click(_startGeocodingSearch);
          
        $('#input_search')
                    .on('keypress',
                    function(e){
                        var code = (e.keyCode ? e.keyCode : e.which);
                            if (code == 13) {
                                top.HEURIST4.util.stopEvent(e);
                                e.preventDefault();
                                 _startGeocodingSearch();
                            }
                    });          
        
        //clear/delete buttons --------------------------------------------
        $('#delete-button').button().click(_deleteSelectedShape);
        $('#delete-all-button').button().click(_deleteAllShapes);
        
        //get save bounds (viewpoints) ------------------------------------
        var $sel_viepoints = $('#sel_viewpoints');
        
        //fill sel_viewpoints with bounds
        top.HEURIST4.ui.createSelector( $sel_viepoints.get(0), 
            $.isEmptyObject(map_viewpoints)?top.HR('none defined'): map_viewpoints);
            
        $sel_viepoints.change(function(){
           var bounds = $(this).val();
           if(bounds!=''){
               //get LatLngBounds from urlvalue lat_lo,lng_lo,lat_hi,lng_hi
               bounds = bounds.split(',');
               gmap.fitBounds({south:Number(bounds[0]), west:Number(bounds[1]),
                                north:Number(bounds[2]), east:Number(bounds[3]) });
                               
               //gmap.fitBounds(new LatLngBounds(new LatLng(Number(bounds[1]), Number(bounds[2]))
               //    , new LatLng(Number(bounds[3]), Number(bounds[0])) );
           }
        });
        
        $('#btn_viewpoint_delete')
          .button({label: top.HR("Delete selected location"), text:false, icons: {
                      secondary: "ui-icon-close"
          }})
          .click(function(){
              var selval = $sel_viepoints.val();
              if(selval!=''){
                 // remove from preferences
                 $.each(map_viewpoints, function(index, item){
                     if(item['key']==selval){
                          map_viewpoints.splice(index,1);
                          return false;
                     }
                 });
                 top.HAPI4.save_pref('map_viewpoints', map_viewpoints);
                  
                 // remove from selector
                 $sel_viepoints.find('option:selected').remove(); 
                 if($.isEmptyObject(map_viewpoints)){
                    top.HEURIST4.ui.addoption( $sel_viepoints.get(0), 
                                '', top.HR('none defined'));
                 }
              }
          });

        $('#btn_viewpoint_save')
          .button({label: top.HR("Save location")})
          .click(function(){
              top.HEURIST4.msg.showPrompt('Name of location', function(location_name){
                  if(!top.HEURIST4.util.isempty(location_name)){
                      //save into preferences 
                      if($.isEmptyObject(map_viewpoints)){
                            map_viewpoints=[];   
                            $sel_viepoints.empty();
                      }
                      map_viewpoints.push({key:gmap.getBounds().toUrlValue(), title:location_name});
                      top.HAPI4.save_pref('map_viewpoints', map_viewpoints);
                      // and add to selector
                      top.HEURIST4.ui.addoption( $sel_viepoints.get(0), 
                                gmap.getBounds().toUrlValue(), location_name);
                                
                  }
              });
          });
          
        if(!$.isEmptyObject(map_viewpoints)){
            $sel_viepoints.find('option:last-child').attr('selected', 'selected');
            $sel_viepoints.change();
        }     
        
        // apply coordinates
        $('#apply-coords-button').button().click(_applyCoordsForSelectedShape);
        
        $('#load-geometry-button').button().click(function(){

                var titleYes = top.HR('Yes'),
                    titleNo = top.HR('No'),
                    buttons = {};
                
                buttons[titleYes] = function() {
                    _loadGeoJSON( $('#geodata-textarea').val() );
                    $dlg.dialog( "close" );
                };
                buttons[titleNo] = function() {
                    $dlg.dialog( "close" );
                };

                var $dlg = top.HEURIST4.msg.showElementAsDialog({window:top, 
                        element: document.getElementById( "get-set-coordinates" ),
                        'no-resize':true,
                        width:690, height:400,
                        title:top.HR('Paste or upload geo data'),
                        buttons:buttons    
                });
            
        });
        
        $('#get-geometry-button').button().click(function(){
                
                $('#geodata-textarea').val(JSON.stringify(_getGeoJSON()));    
        
                var $dlg = top.HEURIST4.msg.showElementAsDialog({window:top, 
                        element: document.getElementById( "get-set-coordinates" ),
                        'no-resize':true,
                        width:690, height:400,
                        title:top.HR('Copy the result')
                });
        });
    }     
      
      
    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

    }

    _init(_mapdiv_id);
    return that;  //returns object
}

/**
 * A menu that lets a user delete a selected vertex of a path.
 * @constructor
 */
function DeleteMenu() {
  this.div_ = document.createElement('div');
  this.div_.className = 'delete-menu';
  this.div_.innerHTML = 'Delete';

  var menu = this;
  google.maps.event.addDomListener(this.div_, 'click', function() {
    menu.removeVertex();
  });
}
DeleteMenu.prototype = new google.maps.OverlayView();

DeleteMenu.prototype.onAdd = function() {
  var deleteMenu = this;
  var map = this.getMap();
  this.getPanes().floatPane.appendChild(this.div_);

  // mousedown anywhere on the map except on the menu div will close the
  // menu.
  this.divListener_ = google.maps.event.addDomListener(map.getDiv(), 'mousedown', function(e) {
    if (e.target != deleteMenu.div_) {
      deleteMenu.close();
    }
  }, true);
};

DeleteMenu.prototype.onRemove = function() {
  google.maps.event.removeListener(this.divListener_);
  this.div_.parentNode.removeChild(this.div_);

  // clean up
  this.set('position');
  this.set('path');
  this.set('vertex');
};

DeleteMenu.prototype.close = function() {
  this.setMap(null);
};

DeleteMenu.prototype.draw = function() {
  var position = this.get('position');
  var projection = this.getProjection();

  if (!position || !projection) {
    return;
  }

  var point = projection.fromLatLngToDivPixel(position);
  this.div_.style.top = point.y + 'px';
  this.div_.style.left = point.x + 'px';
};

/**
 * Opens the menu at a vertex of a given path.
 */
DeleteMenu.prototype.open = function(map, path, vertex) {
  if(path.type==google.maps.drawing.OverlayType.MARKER){
        this.set('position', vertex);
  }else{
        this.set('position', path.getAt(vertex));
  }
  this.set('path', path);
  this.set('vertex', vertex);
  this.setMap(map);
  this.draw();
};

/**
 * Deletes the vertex from the path.
 */
DeleteMenu.prototype.removeVertex = function() {
  var path = this.get('path');
  var vertex = this.get('vertex');

  if (!path || vertex == undefined) {
    this.close();
    return;
  }
  
  if(path.type==google.maps.drawing.OverlayType.MARKER){
        path.setMap(null);
  }else{
        path.removeAt(vertex);
  }
  this.close();
};
