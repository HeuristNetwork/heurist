/**
* mapDraw2.js
* 
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
/* global google, Utm, HMapLayer, stringifyWKT */


function hMappingDraw(_mapdiv_id, _initial_wkt) {
    const _className = "MappingDraw",
    _version   = "0.4";

    let mapdiv_id = null;

    let drawingManager;
    let selectedShape;
    let colors = ['#1E90FF', '#FF1493', '#32CD32', '#FF8C00', '#4B0082'];
    let selectedColor;
    let colorButtons = {};
    let gmap;
    let initial_wkt = null;
    let deleteMenu;
    let overlays = []; //all objects on map
    let geocoder = null;
    let map_viewpoints = []; //saved in user preferences (session) map viewpoints (bounds)
    let map_overlays = [];   //array of kml, tiled and images layers (from db)
    let _current_overlay = null;

    function clearSelection() {
        if (selectedShape) {
            if(selectedShape.type != google.maps.drawing.OverlayType.MARKER) selectedShape.setEditable(false);
            selectedShape = null;
        }
        $('#coords1').val('');
    }
    
    function showCoordsHint(){
        
        let type = drawingManager.getDrawingMode();
        if (selectedShape!=null) {
            type = selectedShape.type;
        }
        let sPrompt = '';
        if(type==google.maps.drawing.OverlayType.MARKER){
            sPrompt = 'Marker: enter coordinates as: lat long';
        }else if(type==google.maps.drawing.OverlayType.CIRCLE){
            sPrompt = 'Circle: enter coordinates as: lat-center long-center radius-km';
        }else if(type==google.maps.drawing.OverlayType.RECTANGLE){
            sPrompt = 'Rectangle: enter coordinates as: lat-min long-min lat-max long-max';
        }else if(type==google.maps.drawing.OverlayType.POLYLINE || type==google.maps.drawing.OverlayType.POLYGON){
            sPrompt = (type==google.maps.drawing.OverlayType.POLYGON?'Polygon':'Polyline')
                +': enter coordinates as sequence of lat long separated by space';
        }
        $('#coords_hint').html(sPrompt);
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
            for(let i in overlays){
                if(overlays[i]==selectedShape){
                    overlays.splice(i,1);
                    break;
                }
            }  

            selectedShape.setMap(null);
            clearSelection();
        }
    }

    function _deleteAllShapes( leaveOne ) {
        clearSelection();
        while(overlays[0]){
            if(leaveOne===true && overlays.length==1){
                break;
            }
            overlays.pop().setMap(null);
        }          
    }

    //
    // start color methods ------------------------------------------------------
    //
    function selectColor(color) {
        selectedColor = color;
        for (let i = 0; i < colors.length; ++i) {
            let currColor = colors[i];
            colorButtons[currColor].style.border = currColor == color ? '2px solid #789' : '2px solid #fff';
        }

        // Retrieves the current options from the drawing manager and replaces the
        // stroke or fill color as appropriate.
        let polylineOptions = drawingManager.get('polylineOptions');
        polylineOptions.strokeColor = color;
        drawingManager.set('polylineOptions', polylineOptions);

        let rectangleOptions = drawingManager.get('rectangleOptions');
        rectangleOptions.fillColor = color;
        drawingManager.set('rectangleOptions', rectangleOptions);

        let circleOptions = drawingManager.get('circleOptions');
        circleOptions.fillColor = color;
        drawingManager.set('circleOptions', circleOptions);

        let polygonOptions = drawingManager.get('polygonOptions');
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
        let button = document.createElement('span');
        button.className = 'color-button';
        button.style.backgroundColor = color;
        google.maps.event.addDomListener(button, 'click', function() {
            selectColor(color);
            setSelectedShapeColor(color);
        });

        return button;
    }

    function buildColorPalette() {
        let colorPalette = document.getElementById('color-palette');
        for (let i = 0; i < colors.length; ++i) {
            let currColor = colors[i];
            let colorButton = makeColorButton(currColor);
            colorPalette.appendChild(colorButton);
            colorButtons[currColor] = colorButton;
        }
        selectColor(colors[0]);
    }
    //
    // END color methods ------------------------------------------------------
    //

    //
    // fill list of coordinates
    //
    function _fillCoordinates(shape){
        if(shape!=null){
            let sCoords = '';
            if(shape.type==google.maps.drawing.OverlayType.POLYGON || 
                shape.type==google.maps.drawing.OverlayType.POLYLINE) {

                processPoints(shape.getPath(), function(latLng){
                    sCoords = sCoords+formatPnt(latLng)+'\n';
                    }, shape);
            }else if(shape.type==google.maps.drawing.OverlayType.RECTANGLE){

                let bnd = shape.getBounds();
                sCoords = formatPnt(bnd.getSouthWest()) + '\n' + formatPnt(bnd.getNorthEast());  

            }else if(shape.type==google.maps.drawing.OverlayType.CIRCLE){

                let radius = shape.getRadius();
                if(radius>0)
                    sCoords = formatPnt(shape.getCenter())+'\nr='+radius.toFixed(2);

            }else if(shape.type==google.maps.drawing.OverlayType.MARKER){

                sCoords = formatPnt(shape.getPosition());
            }
            $('#coords1').val(sCoords);
        }
    }

    //
    // get wkt for current shape
    //
    function _getWKT(shape){

        let res = {type:null, wkt:null};

        if(shape!=null){

            if(shape.type==google.maps.drawing.OverlayType.POLYGON) {

                let aCoords = [];
                processPoints(shape.getPath(), function(latLng){
                    aCoords.push(formatPntWKT(latLng));
                    }, shape);

                aCoords.push(aCoords[0]); //add lst point otherwise WKT fails

                res.type = 'pl';
                res.wkt = "POLYGON ((" + aCoords.join(",") + "))";

            }else if(shape.type==google.maps.drawing.OverlayType.POLYLINE){

                let aCoords = [];
                processPoints(shape.getPath(), function(latLng){
                    aCoords.push(formatPntWKT(latLng));
                    }, shape);

                res.type = 'l';
                res.wkt = "LINESTRING (" + aCoords.join(",") + ")";

            }else if(shape.type==google.maps.drawing.OverlayType.RECTANGLE){

                let bnd = shape.getBounds();
                let aCoords = [];
                let sw = bnd.getSouthWest();
                let nw = bnd.getNorthEast();

                aCoords.push(formatPntWKT(sw));  
                aCoords.push(formatPntWKT( new google.maps.LatLng(nw.lat(), sw.lng()) ));  
                aCoords.push(formatPntWKT(nw));  
                aCoords.push(formatPntWKT( new google.maps.LatLng(sw.lat(), nw.lng()) ));  
                aCoords.push(formatPntWKT(sw));  

                res.type = "r";
                res.wkt = "POLYGON ((" + aCoords.join(",") + "))";

            }else if(shape.type==google.maps.drawing.OverlayType.CIRCLE){

                /*
                value = "LINESTRING ("+lng+" "+lat+","+r(bounds.getNorthEast().lng())+" "+lat+","+
                r(bounds.getSouthWest().lng())+" "+r(bounds.getSouthWest().lat())+","+
                r(bounds.getSouthWest().lng())+" "+r(bounds.getNorthEast().lat())+
                ")";*/

                let bnd = shape.getBounds();

                // Actualy we need ony 2 points to detect center and radius - however we add 2 more points for correct bounds
                let aCoords = [];
                aCoords.push(formatPntWKT(shape.getCenter()));
                aCoords.push(formatPntWKT( new google.maps.LatLng( shape.getCenter().lat(), bnd.getNorthEast().lng()  ) ));
                aCoords.push(formatPntWKT( bnd.getSouthWest() ));
                aCoords.push(formatPntWKT( bnd.getNorthEast() ));

                res.type = 'c';
                res.wkt = "LINESTRING (" + aCoords.join(",") + ")";

            }else if(shape.type==google.maps.drawing.OverlayType.MARKER){

                res.type = 'p';
                res.wkt = "POINT ("+formatPntWKT(shape.getPosition())+")";

            }
        }
        return res;
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
        let thePath = shape.getPath();

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
        
        if(!$('#cbAllowMulti').is(':checked')) _deleteAllShapes();
        
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
        if(!$('#cbAllowMulti').is(':checked')) _deleteAllShapes();
        
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
    function _init(_mapdiv_id, _initial_wkt) {

        _initUIcontrols();

        mapdiv_id = _mapdiv_id;
        initial_wkt = _initial_wkt;

        _init_DeleteMenu();
 
        let map = new google.maps.Map(document.getElementById(mapdiv_id), {
            mapId: "cb51443861458fd0", // Map ID is required for advanced markers.
            zoom: 2,
            center: new google.maps.LatLng(31.2890625, 5), //22.344, 114.048),
            disableDefaultUI: true,
            zoomControl: true,
            zoomControlOptions: {
                position: google.maps.ControlPosition.RIGHT_TOP
            },
            mapTypeId: google.maps.MapTypeId.TERRAIN,
            mapTypeControl: true,
            mapTypeControlOptions: {
                //style: google.maps.MapTypeControlStyle.DROPDOWN_MENU,
                mapTypeIds: ['terrain', 'roadmap','satellite','hybrid'],                
                position: google.maps.ControlPosition.LEFT_TOP
            }            
        });

        // deal with initial tile loading
        let loadListener = google.maps.event.addListener(map, 'tilesloaded', function(){
            google.maps.event.removeListener( loadListener );
            _onMapInited();
        });
        
        
        gmap = map;
    } 

    //
    //
    //
    function formatPnt(pnt, d){
        if(isNaN(d)) d = 7;
        let lat = pnt.lat();
        lat = lat.toFixed(d);
        let lng = pnt.lng();
        lng = lng.toFixed(d);
        return lat + ' ' + lng;               
    }
    function formatPntWKT(pnt, d){
        if(isNaN(d)) d = 7;
        let lat = pnt.lat();
        lat = lat.toFixed(d);
        let lng = pnt.lng();
        lng = lng.toFixed(d);
        return lng + ' ' + lat;               
    }
    //
    //
    //
    function _startGeocodingSearch(){

        if(geocoder==null){
            geocoder = new google.maps.Geocoder();
        }

        let address = document.getElementById("input_search").value;

        geocoder.geocode( { 'address': address}, function(results, status) {

            if (status == google.maps.GeocoderStatus.OK) {

                if(results[0].geometry.viewport){
                    gmap.fitBounds(results[0].geometry.viewport);
                }else{
                    gmap.setCenter(results[0].geometry.location);
                }  
                /*let marker = new google.maps.Marker({
                map: map,
                position: results[0].geometry.location
                });*/
            } else {
                alert(window.hWin.HR("Sorry, Google Maps reports " + status 
                    + ". Could not find the specified place name. <br><br>Please check spelling."));
            }
        });        
    }

    //
    //
    //
    function _zoomToSelection() {

        let bounds = null;
        let shape = selectedShape;
        if(shape!=null){
            if(shape.type==google.maps.drawing.OverlayType.POLYGON || 
                shape.type==google.maps.drawing.OverlayType.POLYLINE) {

                bounds = new google.maps.LatLngBounds();
                //map.data.forEach(function(feature) {
                //    processPoints(feature.getGeometry(), bounds.extend, bounds);
                //});
                processPoints(shape.getPath(), bounds.extend, bounds);

            }else if(shape.type==google.maps.drawing.OverlayType.RECTANGLE ||
                shape.type==google.maps.drawing.OverlayType.CIRCLE)
            {

                bounds = shape.getBounds();

            }else if(shape.type==google.maps.drawing.OverlayType.MARKER){
                
                gmap.setCenter(shape.getPosition()); 
                gmap.setZoom(14);  
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

        let type = drawingManager.getDrawingMode();

        if (selectedShape!=null) {
            type = selectedShape.type;
        }
        if(type==null){
            alert(window.hWin.HR('Select shape on map first or define drawing mode'));
            return;
        }

        let sCoords = $("#coords1").val();
        _parseManualEntry(sCoords, type);
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

        let lat1 = latLong.lat.toRad();
        let lon1 = latLong.lng.toRad();

        let lat2 = Math.asin(Math.sin(lat1) * Math.cos(dist) +
            Math.cos(lat1) * Math.sin(dist) * Math.cos(brng));

        let lon2 = lon1 + Math.atan2(Math.sin(brng) * Math.sin(dist) *
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
        let R = 6371000; // earth's radius in meters
        let dLat = (latLong2.lat-latLong1.lat).toRad(); //* Math.PI / 180;
        let dLon = (latLong2.lng-latLong1.lng).toRad(); //* Math.PI / 180;
        let a = Math.sin(dLat/2) * Math.sin(dLat/2) +
        Math.cos(latLong1.lat.toRad() ) * Math.cos(latLong2.lat.toRad() ) *
        Math.sin(dLon/2) * Math.sin(dLon/2);
        let c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        let d = R * c;
        return d;
    }    

    //
    //
    //        
    function _parseManualEntry(sCoords, type, UTMzone){

        let s = sCoords.replace(/[\b\t\n\v\f\r]/g, ' '); //remove invisible service chars
        s = s.replace("  "," ").trim();
        let arc = s.split(" ");  
        let coords = []; //Array of LatLngLiteral
        
        let islat = false, k;
        let hemisphere = 'N';        
        
        if(window.hWin.HEURIST4.util.isnull(UTMzone)){
            
            let allInteger = true, allOutWGS = true;
            //check for UTM - assume they are integer and at least several are more than 180
            for (let k=0; k<arc.length; k++){
                
                if(k==2 && type==google.maps.drawing.OverlayType.CIRCLE) continue;
            
                let crd = Number(arc[k]);
                if(isNaN(crd)){
                    alert(arc[k]+" is not number value");
                    return null;
                }
                allInteger = allInteger && (crd==parseInt(arc[k]));
                allOutWGS = allOutWGS &&  ((islat && Math.abs(crd)>90) || Math.abs(crd)>180);
                if (!(allOutWGS && allInteger)) break;
                
                islat = !islat;
            }
            if(allInteger || allOutWGS){ //offer to convert UTM to LatLong

                let $ddlg, buttons = {};
                buttons['Yes, UTM'] = function(){ 
                    
                    let UTMzone = $ddlg.find('#dlg-prompt-value').val();
                    if(!window.hWin.HEURIST4.util.isempty(UTMzone)){
                            let re = /s|n/gi;
                            let zone = parseInt(UTMzone.replace(re,''));
                            if(isNaN(zone) || zone<1 || zone>60){
                                setTimeout('alert("UTM zone must be within range 1-60");',500);
                                return false;
                                //38N 572978.70 5709317.22 
                                //east 572980.08 5709317.24
                                //574976.85  5706301.55
                            }
                    }else{
                        UTMzone = 0;
                    }
                    setTimeout(function(){_parseManualEntry(sCoords, type, UTMzone);},500);
                    $ddlg.dialog('close'); 
                }; 
                buttons['No'] = function(){ $ddlg.dialog('close'); 
                    setTimeout(function(){_parseManualEntry(sCoords, type, 0);},500);
                };
                
                $ddlg = window.hWin.HEURIST4.msg.showMsgDlg( 
    '<p>We have detected coordinate values in the import '
    +'which we assume to be UTM coordinates/grid references.</p><br>'
    +'<p>Heurist will only import coordinates from one UTM zone at a time. Please '
    +'split into separate files if you have more than one UTM zone in your data.</p><br>'
    +'<p>UTM zone (1-60) and Hemisphere (N or S) for these data: <input id="dlg-prompt-value"></p>',
        buttons, 'UTM coordinates?');
        
                 return;                       
            }
            UTMzone = 0;
        }else if(UTMzone!=0) {
            //parse UTMzone must be 1-60 N or S
            if(UTMzone.toLowerCase().indexOf('s')>=0){
                hemisphere = 'S';
            }
            let re = /s|n/gi;
            UTMzone = parseInt(UTMzone.replace(re,''));
            if(isNaN(UTMzone) || UTMzone<1 || UTMzone>60){
                setTimeout("alert('UTM zone must be within range 1-60')",500);
                return;
                //38N 572978.70 5709317.22 
            }
        }
        
        //verify and gather coordintes
        islat = true;
        for (k=0; k<arc.length; k++){

            //special case for circle
            if(k==2 && type==google.maps.drawing.OverlayType.CIRCLE && arc[k].indexOf("r=")==0){
                let d = Number(arc[k].substr(2));
                if(isNaN(d)){
                    alert(arc[k]+" is wrong radius value");
                    return null;
                }

                let resc = _destinationPoint(coords[0], 90, d);
                if(resc!=null){
                    coords.push({radius:d});
                }else{
                    alert("Cannot create circle");
                    return null;
                }
                break;
            }

            let crd = Number(arc[k]);
            if(isNaN(crd)){
                alert(arc[k]+" is not number value");
                return null;
            }else if(UTMzone==0 && !islat && Math.abs(crd)>180){
                alert(arc[k]+" is an invalid longitude value");
                return null;
            }else if(UTMzone==0 && islat && Math.abs(crd)>90){
                alert(arc[k]+" is an invalid latitude value");
                return null;
            }
            islat = !islat;
            if(islat){
                if(UTMzone>0){
                    let easting = Number(arc[k-1]);
                    let northing = crd;
                    
                    let utm = new Utm( UTMzone, hemisphere, easting, northing );
                    let latlon = utm.toLatLonE();
                    coords.push({lat:latlon.lat, lng:latlon.lon});    
                }else{
                    coords.push({lat:Number(arc[k-1]), lng:crd});    
                }
            }
                
        }//for

        let res = _loadShape(coords, type);
        if(res && selectedShape!=null){
            _zoomToSelection();
        }
        
        return coords;
    }

    //coords  Array of LatLngLiteral
    function _loadShape(coords, type){

        let res = false;  

        if (coords && coords.length>0){
            if(type==google.maps.drawing.OverlayType.POLYGON){
                if(coords.length<3){
                    alert("Not enough coordinates for rectangle. Need at least 3 pairs");
                }else{
                    if (selectedShape) {
                        selectedShape.setPath(coords);
                    }else{
                        let opts = drawingManager.get('polygonOptions');
                        opts.paths = coords;

                        let newShape = new google.maps.Polygon(opts);
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
                        let opts = drawingManager.get('polylineOptions');
                        opts.path = coords;

                        let newShape = new google.maps.Polyline(opts);
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
                    let newShape;
                    for(let k=0; k<coords.length; k++){
                        let opts = drawingManager.get('markerOptions');
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

                    let bounds = {south:coords[0].lat, west:coords[0].lng,  north:coords[1].lat, east:coords[1].lng };

                    if (selectedShape) {
                        selectedShape.setBounds( bounds );
                    }else{
                        let opts = drawingManager.get('rectangleOptions');
                        opts.bounds = bounds;
                        let newShape = new google.maps.Rectangle(opts);
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

                    let radius = (coords[1].radius>0)
                    ?coords[1].radius
                    :_getDistance(coords[0], coords[1]) ;

                    if (selectedShape) {
                        selectedShape.setCenter( coords[0] );
                        selectedShape.setRadius( radius );
                    }else{
                        let opts = drawingManager.get('circleOptions');
                        opts.center = coords[0];
                        opts.radius = radius;
                        let newShape = new google.maps.Circle(opts);
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
    // show GeoJSON as a set of separate overlays  - replace with window.hWin.HEURIST4.geo.prepareGeoJSON
    //
    function _loadGeoJSON(mdata){

        if (typeof(mdata) === "string" && !window.hWin.HEURIST4.util.isempty(mdata)){
            try{
                mdata = JSON.parse(mdata);
                //mdata = JSON.parse( mdata );
            }catch(e){
                //Not well formed JSON provided. Property names be quoted with double-quote characters
                mdata = null;
            }
        }
        if(window.hWin.HEURIST4.util.isnull(mdata) || $.isEmptyObject(mdata)){
            alert('Incorrect GeoJSON provided');
            return;            
        }

        //FeatureCollection.features[feature]        
        //feature.geometry.type  , coordinates
        //GeometryCollection.geometries[{type: ,coordinates: },...]

        
        let ftypes = ['Point','MultiPoint','LineString','MultiLineString','Polygon','MultiPolygon'];
        
        if(mdata.type == 'FeatureCollection'){
            for (let k=0; k<mdata.features.length; k++){
                _loadGeoJSON(mdata.features[k]); //another collection or feature
            }
        }else{
            
            function __loadGeoJSON_primitive(geometry){

                if($.isEmptyObject(geometry)) return;

                if(geometry.type=="GeometryCollection"){
                    for (let l=0; l<geometry.geometries.length; l++){
                        __loadGeoJSON_primitive(geometry.geometries[l]); //another collection or feature
                    }
                }else{

                    function _extractCoords(shapes, coords){

                        function _isvalid_pnt(pnt){
                            return (Array.isArray(pnt) && pnt.length==2 && 
                                window.hWin.HEURIST4.util.isNumber(pnt[0]) && window.hWin.HEURIST4.util.isNumber(pnt[1]) &&
                                Math.abs(pnt[0])<=180.0 && Math.abs(pnt[1])<=90.0);
                        }

                        if(_isvalid_pnt(coords)){ //Marker
                            shapes.push([{lat:coords[1], lng:coords[0]}]);
                        }else if(_isvalid_pnt(coords[0])){
                            //  !isNaN(Number(coords[0])) && !isNaN(Number(coords[1])) ){ //this is point
                            let shape = [];
                            for (let m=0; m<coords.length; m++){
                                let pnt = coords[m];
                                if(_isvalid_pnt(pnt)){
                                    shape.push({lat:pnt[1], lng:pnt[0]});
                                }
                            }
                            shapes.push(shape);
                        }else{
                            for (let n=0; n<coords.length; n++){
                                if(Array.isArray(coords[n]))
                                    _extractCoords(shapes, coords[n]);
                            }
                        }
                    }

                    let shapes = [];
                    _extractCoords(shapes, geometry.coordinates);

                    if(shapes.length>0){

                        let type = null;

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
                            let i;
                            for (i=0; i<shapes.length; i++){
                                clearSelection();          
                                _loadShape(shapes[i], type);   
                            }
                        }
                    }

                }

            }
        
            if(mdata.type == 'Feature' && !$.isEmptyObject(mdata.geometry)){
                __loadGeoJSON_primitive(mdata.geometry)
            }else if (mdata.type && ftypes.indexOf(mdata.type)>=0){                      
                __loadGeoJSON_primitive(mdata)
            }
        }

    }

    //
    // construct geojson object from all overlays
    //
    function _getGeoJSON(){

        let points=[], polylines=[], polygones=[];
        for (let k=0; k<overlays.length; k++){

            let shape = overlays[k];

            if(shape!=null){
                let coords = [];
                if(shape.type==google.maps.drawing.OverlayType.POLYGON){

                    processPoints(shape.getPath(), function(latLng){
                        coords.push([latLng.lng(), latLng.lat()]);
                        }, shape);
                    
                    if(coords[coords.length-1][0]!=coords[0][0] && coords[coords.length-1][1]!=coords[0][1])    {
                        coords.push(coords[0]); //add lst point otherwise WKT fails                        
                    }
                    polygones.push(coords);

                }else if (shape.type==google.maps.drawing.OverlayType.POLYLINE) {

                    processPoints(shape.getPath(), function(latLng){
                        coords.push([latLng.lng(), latLng.lat()]);
                        }, shape);
                    polylines.push(coords);

                }else if(shape.type==google.maps.drawing.OverlayType.RECTANGLE){

                    let bnd = shape.getBounds();
                    let pnt1 = bnd.getSouthWest(),
                    pnt2 = bnd.getNorthEast();  

                    coords = [[pnt1.lng(), pnt1.lat()],[pnt1.lng(), pnt2.lat()],
                        [pnt2.lng(), pnt2.lat()],[pnt2.lng(), pnt1.lat()], [pnt1.lng(), pnt1.lat()]];

                    polygones.push(coords);

                }else if(shape.type==google.maps.drawing.OverlayType.CIRCLE){

                    let latLng = shape.getCenter()
                    let radius = shape.getRadius();

                    let degreeStep = 360 / 40;

                    for(let i = 0; i < 40; i++){
                        let gpos = google.maps.geometry.spherical.computeOffset(latLng, radius, degreeStep * i);
                        coords.push([gpos.lng(), gpos.lat()]);
                    };    
                    coords.push(coords[0])                    
                    /*
                    for (let i=0; i <= 40; ++i) {
                    let x = latLng.lng() + radius * Math.cos(i * 2*Math.PI / 40);
                    let y = latLng.lat() + radius * Math.sin(i * 2*Math.PI / 40);
                    coords.push([x, y]);
                    }                        
                    */                       
                    polygones.push(coords);

                }else if(shape.type==google.maps.drawing.OverlayType.MARKER){

                    let latLng = shape.getPosition();
                    coords.push([latLng.lng(), latLng.lat()]);
                    points.push(coords);

                }
            }
        }//for overlays

        let geometries = [];    

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
        
        let res = {};
        if(geometries.length>0){

            //avoid FeatureCollection - stringifyWKT does not support it
            /*
            res = { type: "FeatureCollection", features: [{ type: "Feature", geometry: {},
                "properties": {}
            }]};
            */

            res = { type: "Feature", geometry: {},
                "properties": {}
            };
            
            if(geometries.length==1){
                //res.features[0].geometry = geometries[0];
                res = geometries[0];
            }else{
                //features[0].
                res.geometry = { type: "GeometryCollection", geometries:geometries };
            }

        }
        return res;
    }    

    //
    // init UI controls
    //
    function _initUIcontrols(){

        //geocoding ------------------------------------------------------               
        $('#btn_search_start')
        .button({label: window.hWin.HR("Start search"), text:false, icons: {
            secondary: "ui-icon-search"
        }})
        .click(_startGeocodingSearch);

        $('#input_search')
        .on('keypress',
            function(e){
                let code = (e.keyCode ? e.keyCode : e.which);
                if (code == 13) {
                    window.hWin.HEURIST4.util.stopEvent(e);
                    e.preventDefault();
                    _startGeocodingSearch();
                }
        });          

        //clear/delete buttons --------------------------------------------
        $('#delete-button').button().click(_deleteSelectedShape);
        $('#delete-all-button').button().click(_deleteAllShapes);

        
        //get overlay layers (image,tiled,kml) ------------------------------------
        let $sel_overlays = $('#sel_overlays');

        $sel_overlays.change(function(){
            let rec_ID = $sel_overlays.val();
            _addOverlay(rec_ID);
            window.hWin.HAPI4.save_pref('map_overlay_sel', rec_ID);                
        });
        
        //get save bounds (viewpoints) ------------------------------------
        let $sel_viepoints = $('#sel_viewpoints');

        map_viewpoints = window.hWin.HAPI4.get_prefs('map_viewpoints');


        //fill sel_viewpoints with bounds
        if($.isEmptyObject(map_viewpoints) || map_viewpoints.length<1){
            map_viewpoints = [];
            map_viewpoints.push({key:'', title:window.hWin.HR("none defined")});
        }else{
            //add to begin
            map_viewpoints.unshift({key:'', title:window.hWin.HR("select...")});
        }
        
        $sel_viepoints.empty();
        window.hWin.HEURIST4.ui.createSelector( $sel_viepoints.get(0), map_viewpoints);

        $sel_viepoints.click(function(){
            let bounds = $(this).val();
            if(bounds!=''){
                //get LatLngBounds from urlvalue lat_lo,lng_lo,lat_hi,lng_hi
                bounds = bounds.split(',');
                gmap.fitBounds({south:Number(bounds[0]), west:Number(bounds[1]),
                    north:Number(bounds[2]), east:Number(bounds[3]) }, -1);

                //now we always keep last extent window.hWin.HAPI4.save_pref('map_viewpoints_sel', $(this).find('option:selected').text());                
                
                //gmap.fitBounds(new LatLngBounds(new LatLng(Number(bounds[1]), Number(bounds[2]))
                //    , new LatLng(Number(bounds[3]), Number(bounds[0])) );
            }
        });

        $('#btn_viewpoint_delete')
        .button({label: window.hWin.HR("Delete selected extent"), showLabel:false, icon:"ui-icon-close"})
        .css({'font-size':'0.9em'})
        .on('click', function(){
            let selval = $sel_viepoints.val();
            if(selval!=''){
                // remove from preferences
                $.each(map_viewpoints, function(index, item){
                    if(item['key']==selval){
                        map_viewpoints.splice(index,1);
                        return false;
                    }
                });
                window.hWin.HAPI4.save_pref('map_viewpoints', map_viewpoints.slice(1));

                // remove from selector
                $sel_viepoints.find('option:selected').remove(); 
                if(map_viewpoints.length==1){
                    $sel_viepoints.empty();
                    window.hWin.HEURIST4.ui.addoption( $sel_viepoints.get(0), 
                        '', window.hWin.HR('none defined'));
                }
            }
        });

        $('#btn_viewpoint_save')
        .button({label: window.hWin.HR("Save extent")})
        .on('click', function(){
            window.hWin.HEURIST4.msg.showPrompt('Name for extent', function(location_name){
                if(!window.hWin.HEURIST4.util.isempty(location_name) && location_name!='none defined'){
                    //save into preferences 
                    if($.isEmptyObject(map_viewpoints) || map_viewpoints.length<2){
                        map_viewpoints = [{key:'',title:'select...'}];   
                    }
                    let not_found = true;
                    $.each(map_viewpoints, function(idx, item){
                        if(item.title == location_name){ //we already have such name
                            map_viewpoints[idx].key = gmap.getBounds().toUrlValue();
                            not_found = false;
                            return false;
                        }
                    });
                    if(not_found){
                        map_viewpoints.push({key:gmap.getBounds().toUrlValue(), title:location_name});
                        window.hWin.HAPI4.save_pref('map_viewpoints', map_viewpoints.slice(1));
                    }
                    
                    //now we always keep last extent  window.hWin.HAPI4.save_pref('map_viewpoints_sel', location_name);
                    
                    // and add to selector
                    $sel_viepoints.empty();
                    window.hWin.HEURIST4.ui.createSelector( $sel_viepoints.get(0), map_viewpoints);
                    //window.hWin.HEURIST4.ui.addoption( $sel_viepoints.get(0), gmap.getBounds().toUrlValue(), location_name);

                }
            }, {title:'Save map extent',yes:'Save',no:"Cancel"});
        });

        // apply coordinates
        $('#apply-coords-button').button().click(_applyCoordsForSelectedShape);

        $('#load-geometry-button').button().on('click', function(){

            let titleYes = window.hWin.HR('Yes'),
            titleNo = window.hWin.HR('No'),
            buttons = {};
            
            let $dlg;

            buttons[titleYes] = function() {
                _loadGeoJSON( $dlg.find('#geodata_textarea').val() );
                $dlg.dialog( "close" );
            };
            buttons[titleNo] = function() {
                $dlg.dialog( "close" );
            };

            $dlg = window.hWin.HEURIST4.msg.showElementAsDialog({window:top, 
                element: document.getElementById( "get-set-coordinates" ),
                resizable:false,
                width:690, height:400,
                title:window.hWin.HR('Paste or upload geo data'),
                buttons:buttons    
            });

        });

        $('#get-geometry-button').button().on('click', function(){

            $('#geodata_textarea').val(JSON.stringify(_getGeoJSON()));    

            let $dlg = window.hWin.HEURIST4.msg.showElementAsDialog({window:top, 
                element: document.getElementById( "get-set-coordinates" ),
                resizable: false,
                width:690, height:400,
                title:window.hWin.HR('Copy the result')
            });
        });
        
        /*
        window.onbeforeunload = function(){
            concole.log('ave extent');
            _saveExtentOnExit();  
        }
        */ 
        
        $('#save-button').button().on('click', function(){
            
            if(!$('#cbAllowMulti').is(':checked') && overlays.length>1){
                
                let $ddlg, buttons = {};
                buttons['Continue'] = function(){ 
                    _deleteAllShapes( true );  
                    $ddlg.dialog('close'); 
                    $('#save-button').trigger('click');
                }; 
                buttons['Cancel'] = function(){ $ddlg.dialog('close'); };
                
                $ddlg = window.hWin.HEURIST4.msg.showMsgDlg( 
    'There are '+overlays.length+' objects on map. Either check "Allow multiple objects" or only one shape will be saved',
                buttons, 'Notice');
                
                return;
            } 
            
            let gjson = _getGeoJSON();
            if($.isEmptyObject(gjson)){
                window.hWin.HEURIST4.msg.showMsgDlg('You have to draw a shape');    
            }else{
                let res = stringifyWKT(gjson);
                _saveExtentOnExit();
                
                //type code is not required for new code. this is for backward capability
                let typeCode = 'm';
                if(res.indexOf('GEOMETRYCOLLECTION')<0 && res.indexOf('MULTI')<0){
                    if(res.indexOf('LINESTRING')>=0){
                        typeCode = 'l';
                    }else if(res.indexOf('POLYGON')>=0){
                        typeCode = 'pl';
                    }else {
                        typeCode = 'p';
                    }
                    
                }
                window.close({type:typeCode, wkt:res});    
            }
            
            /*OLD WAY
            if(selectedShape==null){
                window.hWin.HEURIST4.msg.showMsgDlg('You have to select a shape');
            }else{
                let res = _getWKT(selectedShape);
                _saveExtentOnExit();
                window.close(res);    
            }
            */
        });
        $('#cancel-button').button().on('click', function(){
            _saveExtentOnExit();
            window.close();
        });
    }          
    

    //
    //
    //
    function _loadSavedExtentOnInit(){
        let bounds = window.hWin.HAPI4.get_prefs('map_viewpoint_last');
        if(!window.hWin.HEURIST4.util.isempty(bounds)){          
                bounds = bounds.split(',');
                gmap.fitBounds({south:Number(bounds[0]), west:Number(bounds[1]),
                    north:Number(bounds[2]), east:Number(bounds[3]) }, -1);
        }
    }
    
    //
    //
    //
    function _saveExtentOnExit(){
        window.hWin.HAPI4.save_pref('map_viewpoint_last', gmap.getBounds().toUrlValue());
    }

    function _onMapInited(){

        let polyOptions = {
            strokeWeight: 0,
            fillOpacity: 0.45,
            editable: true
        };
        // Creates a drawing manager attached to the map that allows the user to draw
        // markers, lines, and shapes.
        drawingManager = new google.maps.drawing.DrawingManager({
            drawingMode: google.maps.drawing.OverlayType.MARKER, //google.maps.drawing.OverlayType.POLYGON,
            drawingControlOptions: {
                position: google.maps.ControlPosition.TOP_RIGHT //TOP_CENTER
                //drawingModes: ['marker', 'circle', 'polygon', 'polyline', 'rectangle']
            },            
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
            let newShape = e.overlay;
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
        google.maps.event.addListener(drawingManager, 'drawingmode_changed', function(){
            clearSelection();   
            showCoordsHint();
        }); 
        google.maps.event.addListener(gmap, 'click', clearSelection);
        google.maps.event.addListener(gmap, 'mousemove', function (event) {
            let pnt = event.latLng;
            $('#coords2').text(formatPnt(pnt,5));
        });        

        buildColorPalette();

        if(!window.hWin.HEURIST4.util.isempty(initial_wkt)){
            setTimeout(function(){
                    _loadWKT(initial_wkt);
                }, 1000);
        }else{
            _loadSavedExtentOnInit();  //load last extent of previous session
        }

        if(!$.isEmptyObject(map_viewpoints)){

            /* now we always keep last extent 
            let map_viewpoints_sel = window.hWin.HAPI4.get_prefs('map_viewpoints_sel');

            let $sel_viepoints = $('#sel_viewpoints');
            let not_found = true;
            if(map_viewpoints_sel){
                $.each(map_viewpoints, function(idx, item){
                    if(item.title == map_viewpoints_sel){
                        $sel_viepoints.val(item.key);
                        not_found = false;
                        return false;
                    }
                });
            }
            if(not_found){
                $sel_viepoints.find('option:last-child').attr('selected', 'selected');
            }
            $sel_viepoints.change();
            */
        }    
        
        
        
        //load overlays from server        
        let rts2 = [window.hWin.HAPI4.sysinfo['dbconst']['RT_TILED_IMAGE_SOURCE'],
                   //window.hWin.HAPI4.sysinfo['dbconst']['RT_GEOTIFF_SOURCE'],
                   window.hWin.HAPI4.sysinfo['dbconst']['RT_KML_SOURCE']];
        let rts = [];
        for(let k=0; k<rts2.length-1; k++)
        if(rts2[k]>0){
            rts.push(rts2[k]);
        }
        
        if(rts.length>0){
        
            let request = { q: {"t":rts.join(',')},
                w: 'a',
                detail: 'header',
                source: 'sel_overlays'};

            //perform search
            window.hWin.HAPI4.RecordMgr.search(request, function(response){

                if(response.status == window.hWin.ResponseStatus.OK){
                    let resdata = new HRecordSet(response.data);

                        map_overlays = [];

                        if(resdata.length()>0){
                           map_overlays.push({key:0, title:'select...'}); 
                        }
                        
                        let idx, records = resdata.getRecords();
                        for(idx in records){
                            if(idx)
                            {
                                let record = records[idx];
                                let recID  = resdata.fld(record, 'rec_ID'),
                                recName = resdata.fld(record, 'rec_Title');

                                map_overlays.push({key:recID, title:recName});
                            }
                        }//for
                        
                       
                        let $sel_overlays = $('#sel_overlays');
                        window.hWin.HEURIST4.ui.createSelector( $sel_overlays.get(0),
                            $.isEmptyObject(map_overlays)?window.hWin.HR('none defined'): map_overlays);

                       let map_overlay_sel = window.hWin.HAPI4.get_prefs('map_overlay_sel');     
                       if(map_overlay_sel>0) {
                           $sel_overlays.val(map_overlay_sel);   
                           $sel_overlays.change();
                       }
                            
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });        

                
        }
    }

    //
    // extract coordinates from WKT
    //
    function _loadWKT(wkt) {

        if (! wkt) {
            wkt = decodeURIComponent(document.location.search);
        }
        
        /*let matches = wkt.match(/\??(\S{1,2})\s+(.*)/);
        if (! matches) {
            return;
        }
        let type = matches[1];
        let value = matches[2];*/
        
        let resdata = window.hWin.HEURIST4.geo.wktValueToShapes( wkt, null, 'google' );
                  
        let type = google.maps.drawing.OverlayType.MARKER;
        for (let i=0; i<resdata.Point.length; i++){
            let shape = resdata.Point[i];
            selectedShape = null;//to avoid clear
            _loadShape(shape, type);   
        }
        type = google.maps.drawing.OverlayType.POLYLINE;
        for (let i=0; i<resdata.Polyline.length; i++){
            let shape = resdata.Polyline[i];
            selectedShape = null; //to avoid clear
            _loadShape(shape, type);   
        }
        type = google.maps.drawing.OverlayType.POLYGON;
        for (let i=0; i<resdata.Polygon.length; i++){
            let shape = resdata.Polygon[i];
            selectedShape = null; //to avoid clear
            _loadShape(shape, type);   
        }
        
        //zoom to full extent
        let sw = new google.maps.LatLng(resdata._extent.ymin, resdata._extent.xmin);
        let ne = new google.maps.LatLng(resdata._extent.ymax, resdata._extent.xmax);
        let bounds = new google.maps.LatLngBounds(sw, ne);
        if( Math.abs(ne.lat()-sw.lat())<0.06 && Math.abs(ne.lng()-sw.lng())<0.06 ){
            gmap.setCenter(bounds.getCenter()); 
            gmap.setZoom(14);      
        }else{
            gmap.fitBounds(bounds);
        }
        /*
        let gjson =  parseWKT(value);    //wkt to json
        
        _loadGeoJSON( gjson );  ///!!!!!
        
        //zoom to last loaded shape        
        if(selectedShape!=null){
            _zoomToSelection();
        }
        */
    }
    
    function _loadWKT_old(val) {

        if (! val) {
            val = decodeURIComponent(document.location.search);
        }

        let matches = val.match(/\??(\S{1,2})\s+(.*)/);
        if (! matches) {
            return;
        }
        let type = matches[1];
        let value = matches[2];
        let mode = null;
        let sCoords = '';

        switch (type) {
            case "p":
                mode = google.maps.drawing.OverlayType.MARKER
                matches = value.match(/POINT\s?\((\S{1,25})\s+(\S{1,25})\)/i);
                sCoords = matches[2]+' '+matches[1];
                break;
            case "r":  //rectangle
                mode = google.maps.drawing.OverlayType.RECTANGLE
                matches = value.match(/POLYGON\s?\(\((\S+)\s+(\S+),\s*(\S+)\s+(\S+),\s*(\S+)\s+(\S+),\s*(\S+)\s+(\S+),\s*\S+\s+\S+\)\)/i);

                if(matches.length<6){
                    matches.push(matches[3]);
                    matches.push(matches[4]);
                }
                sCoords = sCoords + parseFloat(matches[2])+' '+parseFloat(matches[1])+' '+parseFloat(matches[6])+' '+parseFloat(matches[5]);

                break;

            case "c":  //circle
            {
                mode = google.maps.drawing.OverlayType.CIRCLE
                matches = value.match(/LINESTRING\s?\((\S{1,25})\s+(\S{1,25}),\s*(\S{1,25})\s+\S{1,25},\s*\S{1,25}\s+\S{1,25},\s*\S{1,25}\s+\S{1,25}\)/i);

                let radius = _getDistance({lat:parseFloat(matches[2]), lng:parseFloat(matches[1])}, 
                    {lat:parseFloat(matches[2]), lng:parseFloat(matches[3])}) ;

                sCoords = parseFloat(matches[2])+'  '+parseFloat(matches[1])+'\nr='+radius.toFixed(2);


                break;
            }
            case "l":  ///polyline
                mode = google.maps.drawing.OverlayType.POLYLINE
                matches = value.match(/LINESTRING\s?\((.+)\)/i);
                if (matches){
                    matches = matches[1].match(/\S{1,25}\s+\S{1,25}(?:,|$)/g);

                    for (let j=0; j < matches.length; ++j) {
                        let match_matches = matches[j].match(/(\S{1,25})\s+(\S{1,25})(?:,|$)/);
                        sCoords = sCoords + parseFloat(match_matches[2]) + ' ' + parseFloat(match_matches[1]) + '\n';
                    }

                }
                break;

            case "pl": //polygon
                mode = google.maps.drawing.OverlayType.POLYGON
                matches = value.match(/POLYGON\s?\(\((.+)\)\)/i);
                if (matches) {
                    matches = matches[1].match(/\S+\s+\S+(?:,|$)/g);

                    for (let j=0; j < matches.length; ++j) {
                        let match_matches = matches[j].match(/(\S{1,25})\s+(\S{1,25})(?:,|$)/);
                        sCoords = sCoords + parseFloat(match_matches[2]) + ' ' + parseFloat(match_matches[1]) + '\n';
                    }
                }
                break;
            default:
                return;
        }

        //_removeOverlay();
        
        drawingManager.setDrawingMode(mode);
        $("#coords1").val(sCoords);
        _applyCoordsForSelectedShape();
    }     
    
    //
    //
    //
    function _addOverlay(rec_ID){
        
        _removeOverlay();  //remove previous
        
        if(!(rec_ID>0)) return;
        
        let that = this;
        
        /*
        let request = {'a': 'search',
            'entity': 'Records',
            'details': 'full',
            'rec_ID': rec_ID,
            'request_id': window.hWin.HEURIST4.util.random()
        }
        window.hWin.HAPI4.EntityMgr.doRequest(request, 
        */
        let request = { q: {"ids":rec_ID},
            w: 'a',
            detail: 'detail',
            source: 'sel_overlays'};

        //perform search
        window.hWin.HAPI4.RecordMgr.search(request, function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    let recordset = new HRecordSet(response.data);
                    let record = recordset.getFirstRecord();
                    _current_overlay = new HMapLayer({gmap:gmap, recordset:recordset});
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
        });
        
    }

    //
    //
    //     
    function _removeOverlay(){
        if(_current_overlay){
            try {
                if(_current_overlay['removeLayer']){  // hasOwnProperty
                    _current_overlay.removeLayer();
                }
                _current_overlay = null;
            } catch(err) {
                console.error(err);
            }
        }
        
    }
    
    //public members
    let that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},
        loadWKT: function(wkt){
            _deleteAllShapes();
            _loadWKT(wkt);
        },
        clearAll:function(){
            _deleteAllShapes();
        }
    }

    _init(_mapdiv_id, _initial_wkt);
    return that;  //returns object
}
//-------------------------------------------------------------------------

/**
* A menu that lets a user delete a selected vertex of a path.
* @constructor
*/
function DeleteMenu() {
    this.div_ = document.createElement('div');
    this.div_.className = 'delete-menu';
    this.div_.innerHTML = 'Delete';

    let menu = this;
    google.maps.event.addDomListener(this.div_, 'click', function() {
        menu.removeVertex();
    });
}

//
//
//
function _init_DeleteMenu(){

DeleteMenu.prototype = new google.maps.OverlayView();

DeleteMenu.prototype.onAdd = function() {
    let deleteMenu = this;
    let map = this.getMap();
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
    let position = this.get('position');
    let projection = this.getProjection();

    if (!position || !projection) {
        return;
    }

    let point = projection.fromLatLngToDivPixel(position);
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
    let path = this.get('path');
    let vertex = this.get('vertex');

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

}