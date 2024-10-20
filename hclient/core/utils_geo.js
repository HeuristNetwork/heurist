/**
* WKT and GeiJSON utility functions
*
* @see editing_input.js, mapDraw.js, recordset.js
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
/* global parseWKT, stringifyWKT */

/*
getWktBoundingBox
wktValueToShapes
prepareGeoJSON  json to timemap
wktValueToDescription

parseWKTCoordinates

simplePointsToWKT -  coordinate pairs to WKT

parseWorldFile -  worldfile data to bbox

*/

if (!window.hWin.HEURIST4){
    window.hWin.HEURIST4 = {};
}
//init only once
if (!window.hWin.HEURIST4.geo) 
{

window.hWin.HEURIST4.geo = {
    
    //
    // prepare geojson  - get array of timemap shapes and summary extent
    // need for
    // 1. recordset.toTimemap convert wkt to timemap shapes (in parseWKTCoordinates) and draw on main map 
    // 2. mapDraw._loadWKT convert wkt to shapes for further load as separate overlays to edit
    // 3. get type and number of shapes with extent to get human readable description in wktValueToDescription
    //
    prepareGeoJSON: function(mdata, resdata, _format){

        if (typeof(mdata) === "string" && !window.hWin.HEURIST4.util.isempty(mdata)){
            try{
                mdata = JSON.parse(mdata);
            }catch(e){
                mdata = null;
            }
        }
        if(window.hWin.HEURIST4.util.isnull(mdata) || $.isEmptyObject(mdata)){
           
            return {};            
        }

        //FeatureCollection.features[feature]        
        //feature.geometry.type  , coordinates
        //GeometryCollection.geometries[{type: ,coordinates: },...]

        
        if(window.hWin.HEURIST4.util.isnull(resdata)){
            if( _format=='google' ){
                resdata = {Point:[],Polyline:[],Polygon:[],_extent:{xmin:Number.POSITIVE_INFINITY,xmax:Number.NEGATIVE_INFINITY,
                                ymin:Number.POSITIVE_INFINITY,ymax:Number.NEGATIVE_INFINITY}};
            }else{
                resdata = [];    
            }
        }
        
        if(mdata.type == 'FeatureCollection'){
            let k = 0;
            for (k=0; k<mdata.features.length; k++){
                resdata = window.hWin.HEURIST4.geo.prepareGeoJSON(mdata.features[k], resdata, _format); //another collection or feature
            }
        }else{
            
            let ftypes = ['Point','MultiPoint','LineString','MultiLineString','Polygon','MultiPolygon','GeometryCollection'];
                        
            //-----------------------------------------
            function __loadGeoJSON_primitive(geometry){

                if(!$.isEmptyObject(geometry))
                {
                    if(geometry.type=="GeometryCollection"){
                        let l;
                        for (l=0; l<geometry.geometries.length; l++){
                            __loadGeoJSON_primitive(geometry.geometries[l]); //another collection or feature
                        }
                    }else{

                        function _isvalid_pnt(pnt){
                                let isValid = (Array.isArray(pnt) && pnt.length==2 && 
                                    window.hWin.HEURIST4.util.isNumber(pnt[0]) && window.hWin.HEURIST4.util.isNumber(pnt[1]));
                                   
                                if(isValid && resdata._extent){
                                    if(pnt[0]<resdata._extent.xmin) resdata._extent.xmin = pnt[0];
                                    if(pnt[0]>resdata._extent.xmax) resdata._extent.xmax = pnt[0];
                                    if(pnt[1]<resdata._extent.ymin) resdata._extent.ymin = pnt[1];
                                    if(pnt[1]>resdata._extent.ymax) resdata._extent.ymax = pnt[1];
                                }
                                return isValid;    
                        }
                        //for google
                        function __extractCoords(shapes, coords, typeCode){

                            if(_isvalid_pnt(coords)){ //Marker
                                shapes.push( {lat:coords[1], lng:coords[0]} );
                            }else if(_isvalid_pnt(coords[0])){
                                //  !isNaN(Number(coords[0])) && !isNaN(Number(coords[1])) ){ //this is point
                                let shape = [];
                                coords.forEach((pnt)=>{
                                    if(_isvalid_pnt(pnt)){
                                        shape.push({lat:pnt[1], lng:pnt[0]});    
                                    }
                                });
                                
                                if(typeCode=='MultiPoint'){
                                    shapes = shape;
                                }else{
                                    shapes.push(shape);    
                                }
                            }else{
                                let n;
                                for (n=0; n<coords.length; n++){
                                    if(Array.isArray(coords[n]))
                                        shapes = __extractCoords(shapes, coords[n], typeCode);
                                }
                            }
                            return shapes;
                        }
                        //for timemap
                        function __extractCoords2(shapes, coords, typeCode){

                            if(_isvalid_pnt(coords)){ //Marker
                            
                                shapes.push( {point:{
                                        lat: Math.round(coords[1] * 1000000) / 1000000,  
                                        lon: Math.round(coords[0] * 1000000) / 1000000}} );
                                
                            }else if(_isvalid_pnt(coords[0])){
                                //  !isNaN(Number(coords[0])) && !isNaN(Number(coords[1])) ){ //this is point
                                let shape = [];
                                for (let m=0; m<coords.length; m++){
                                    const pnt = coords[m];
                                    if(_isvalid_pnt(pnt)){
                                        if(typeCode=='point'){
                                            shape.push({point:{lat:pnt[1], lon:pnt[0]}});
                                        }else{
                                            shape.push({lat:pnt[1], lon:pnt[0]});
                                        }
                                    }
                                }
                                if(typeCode=='point'){
                                    shapes = shape;
                                }else{
                                    let r = {};
                                    r[typeCode] = shape;
                                    shapes.push(r);
                                }
                            }else{
                                //multi
                                let n;
                                for (n=0; n<coords.length; n++){
                                    if(Array.isArray(coords[n]))
                                        shapes = __extractCoords2(shapes, coords[n], typeCode);
                                }
                            }
                            return shapes;
                        }

                        if( _format=='google' ){
                            
                            let shapes = __extractCoords([], geometry.coordinates, geometry.type)
                                                
                            if(shapes.length>0){

                                if( geometry.type=="Point" || 
                                    geometry.type=="MultiPoint")
                                {   
                                    if(Array.isArray(shapes))
                                    for (let n=0; n<shapes.length; n++){
                                        resdata['Point'].push( [shapes[n]] );
                                    }
                                   
                                }else if(geometry.type=="LineString" ||
                                    geometry.type=="MultiLineString")
                                {   
                                    resdata['Polyline'] = resdata['Polyline'].concat( shapes );
                                }else if(geometry.type=="Polygon"||
                                        geometry.type=="MultiPolygon")
                                {
                                    resdata['Polygon'] = resdata['Polygon'].concat( shapes );
                                }       
                                    
                            }
                        
                        }else{
                        
                            let typeCode;
                            if( geometry.type=="Point" || 
                                geometry.type=="MultiPoint")
                            {
                                typeCode = 'point';
                            }else if(geometry.type=="LineString" ||
                                geometry.type=="MultiLineString")
                            {
                                typeCode = 'polyline';
                            }else if(geometry.type=="Polygon"||
                                    geometry.type=="MultiPolygon")
                            {
                                typeCode = 'polygon';
                            }     

                            let shapes = __extractCoords2([], geometry.coordinates, typeCode);
                            if(shapes.length>0){
                                resdata = resdata.concat(shapes);
                            }
                        }
                        

                    }
                }

            }
            //-----------------------------------------
        
            if(mdata.type == 'Feature' && !$.isEmptyObject(mdata.geometry)){
                __loadGeoJSON_primitive(mdata.geometry);
            }else if (mdata.type && ftypes.indexOf(mdata.type)>=0){                      
                __loadGeoJSON_primitive(mdata);
            }
        }
        return resdata;

    },
    

    /**
    * convert wkt to
    * format - 0 timemap, 1 google
    *
    * @todo 2 - kml
    * @todo 3 - OpenLayers
    */
    parseWKTCoordinates: function(type, wkt, format, google) {
    
        if(format==1 && typeof google.maps.LatLng != "function") {
            return null;
        }
        
        let gjson =  parseWKT(wkt);    //wkt to json see wellknown.js
        
        let bounds = null, southWest, northEast,
        shape  = null,
        points = []; //google points

        if(gjson && gjson.coordinates){

            switch (type) {
                case "p":
                case "point":
                {
                    const x0 = gjson.coordinates[0],
                          y0 = gjson.coordinates[1];
                    
                    if(format==0){
                        shape = { point:{lat: y0, lon:x0 } };
                    }else{
                        const point = new google.maps.LatLng(y0, x0);
                        points.push(point);
                        bounds = new google.maps.LatLngBounds(
                            new google.maps.LatLng(y0 - 0.5, x0 - 0.5),
                            new google.maps.LatLng(y0 + 0.5, x0 + 0.5));
                    }
                    
                    

                    break;
                }
                case "c":  //circle
                case "circle":  //circle

                    if(format==0){ //@todo use geodesy-master to calculate distance

                        const x0 = gjson.coordinates[0][0],
                              y0 = gjson.coordinates[0][1];
                        let radius = gjson.coordinates[1][0] - gjson.coordinates[0][0];
                        if(radius==0)
                          radius = gjson.coordinates[1][1] - gjson.coordinates[0][1];

                        shape = [];
                        for (let i=0; i <= 40; ++i) {
                            const x = x0 + radius * Math.cos(i * 2*Math.PI / 40),
                                  y = y0 + radius * Math.sin(i * 2*Math.PI / 40);
                            shape.push({lat: y, lon: x});
                        }
                        shape = {polygon:shape};
                        /*
                        bounds = new google.maps.LatLngBounds(
                            new google.maps.LatLng(y0 - radius, x0 - radius),
                            new google.maps.LatLng(y0 + radius, x0 + radius));
                         */
                        
                    }else{
                        /* ARTEM TODO
                        var centre = new google.maps.LatLng(parseFloat(matches[2]), parseFloat(matches[1]));
                        var oncircle = new google.maps.LatLng(parseFloat(matches[2]), parseFloat(matches[3]));
                        setstartMarker(centre);
                        createcircle(oncircle);

                       
                        */
                    }

                    break;

                case "l":  ///polyline
                case "path":
                case "polyline":
                case "r":  //rectangle
                case "rect":
                case "pl": //polygon
                case "polygon":
                {    
                    let shapes = [];
                    let minLat = 9999, maxLat = -9999, minLng = 9999, maxLng = -9999;
                    let len2 = gjson.coordinates.length;
                    for (let j=0; j < len2; ++j) {
                        let len = gjson.coordinates[j].length;
                        for (let i=0; i < len; ++i) {
                            
                            let placemark = gjson.coordinates[j][i];
                            if(!Array.isArray(placemark)){
                                placemark = gjson.coordinates;
                                len2 = 0;
                                len = 0;
                            }
                            if(Array.isArray(placemark) && placemark.length==2 && 
                                !Array.isArray(placemark[0])){
                                placemark = gjson.coordinates[j];
                                len = 0;
                            }
                            shape = [];    
                            
                            for (let k=0; k < placemark.length; ++k) {
                                const point = {lat:placemark[k][1], 
                                             lon:placemark[k][0]};

                                if(format==0){
                                    shape.push(point);
                                }else{
                                    points.push(new google.maps.LatLng(points.lat, points.lon));
                                }
                                
                                if (point.lat < minLat) minLat = point.lat;
                                if (point.lat > maxLat) maxLat = point.lat;
                                if (point.lon < minLng) minLng = point.lon;
                                if (point.lon > maxLng) maxLng = point.lon;
                            }//for coords
                            
                            if(format==0){
                                shape = (type=="l" || type=="polyline")?{polyline:shape}:{polygon:shape};
                                shapes.push(shape);    
                            }
                            
                        }    
                    }
                    if(shapes.length==1) shape = shapes[0]
                    else shape = shapes;

                    if(!format==0){
                        southWest = new google.maps.LatLng(minLat, minLng);
                        northEast = new google.maps.LatLng(maxLat, maxLng);
                        bounds = new google.maps.LatLngBounds(southWest, northEast);
                    }
                    
                }        
            }//switch

        }
        
        if(format==0){
            return shape;
        }else{
            return {bounds:bounds, points:points};
        }        
        
    
    },
    
    //
    // geodata = _recordset.getFieldGeoValue(_record, window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT']);           
    // WKT to Shapes to bbox array
    //    
    getWktBoundingBox: function(geodata){
      
         if(geodata && geodata[0]){
            
            let shape =  null; 
            if($.isPlainObject(geodata[0]) && geodata[0].wkt){
                shape = window.hWin.HEURIST4.geo.wktValueToShapes( geodata[0].wkt, geodata[0].geotype, 'google' );
            }else{
                shape = window.hWin.HEURIST4.geo.wktValueToShapes( geodata[0], null, 'google' );
            }
             
            if(shape && shape._extent){
                let extent = shape._extent;
                return [[extent.ymin,extent.xmin],[extent.ymax,extent.xmax]];
            }
         }else{
             return null;
         }
        
    },

    //
    //
    //
    mergeBoundingBox: function(extents){
        
        let isset = false;
        let minLat = 9999, maxLat = -9999, minLng = 9999, maxLng = -9999;
        $(extents).each(function(idx, item){
            
            let isValid = (Array.isArray(item) && item.length==2 && 
                window.hWin.HEURIST4.util.isNumber(item[0][0]) && window.hWin.HEURIST4.util.isNumber(item[0][1]));
               
            
            if(isValid){
                if (item[0][0] < minLat) minLat = item[0][0];
                if (item[1][0] > maxLat) maxLat = item[1][0];
                if (item[0][1] < minLng) minLng = item[0][1];
                if (item[1][1] > maxLng) maxLng = item[1][1];
                isset = true;
            }
        });
        
        return isset ?[[minLat, minLng],[maxLat, maxLng]] :null;
    },

    //
    //
    //
    boundingBoxToWKT: function(extent){

        let isValid = (Array.isArray(extent) && extent.length==2 && 
                window.hWin.HEURIST4.util.isNumber(extent[0][0]) && window.hWin.HEURIST4.util.isNumber(extent[0][1]));
                //&& Math.abs(extent[0][1])<=360.0 && Math.abs(extent[0][0])<=90.0
                
        if(isValid){
            let geojson = {type:'Feature', geometry:{type:'Polygon', 
                    coordinates:
            [[[extent[0][1],extent[0][0]],
              [extent[0][1],extent[1][0]],
              [extent[1][1],extent[1][0]],
              [extent[1][1],extent[0][0]],
              [extent[0][1],extent[0][0]]
            ]]}};                        
            
            return stringifyWKT( geojson );
        }else{
            return null;
        }
    },
    
    isEqualBoundingBox: function(ext1, ext2){
        return false;    
    },
    
    //
    // Convert mapdocument bookamark string to bbox
    //    
    getHeuristBookmarkBoundingBox: function(geodata){
      
         if(geodata){
            //Name, Min Longitude,Max Longitude, Min Latitude, Max Latitude
            let vals = geodata.split(',') 
            if(vals.length>4){
                //extent.ymin,extent.xmin],[extent.ymax,extent.xmax
                return [[vals[3], vals[1]],[vals[4], vals[2]]];
            }
         }
         return null;
    },
    
    //
    //  _format - google or timemap
    //    
    wktValueToShapes:function(wkt, typeCode, _format){

        if(window.hWin.HEURIST4.util.isempty(typeCode)){

            let matches = wkt.match(/\??(\S{1,2})\s+(.*)/);
            if (! matches) {
                return;
            }
            
            if(matches.length>2){
                typeCode = matches[1];
                wkt = matches[2];
            }else{
                wkt = matches[1];
            }
        }   
        
        let gjson =  parseWKT(wkt);    //wkt to json see  wellknown.js  
      
        let resdata;
        
        //special case to support old format
        if(typeCode=='c' || typeCode=='circle'){
            
            let x0 = gjson.coordinates[0][0];
            let y0 = gjson.coordinates[0][1];
            let radius = gjson.coordinates[1][0] - gjson.coordinates[0][0];
            if(radius==0)
              radius = gjson.coordinates[1][1] - gjson.coordinates[0][1];

            let shape = [],
                shape2 = [];
            for (let i=0; i <= 40; ++i) {
                let x = x0 + radius * Math.cos(i * 2*Math.PI / 40);
                let y = y0 + radius * Math.sin(i * 2*Math.PI / 40);
                shape.push({lat: y, lng: x}); // for google

                shape2.push({lat: y, lon: x}); // for timemap
            }
                        
           if(_format=='google'){             
                let ext = {xmin:x0-radius,xmax:x0+radius,ymin:y0-radius,ymax:y0+radius}
                resdata = {Point:[],Polyline:[shape],Polygon:[],_extent:ext};
            }else{
                resdata = [ {polygon:shape2} ];    
            }
                        
        }else{
                resdata = window.hWin.HEURIST4.geo.prepareGeoJSON(gjson, null, _format);
        }
    
        return resdata; 
    },

    //
    //
    //
    getParsedWkt: function(wkt, checkWkt=false){

        if(checkWkt){
            let matches = wkt.match(/\??(\S{1,2})\s+(.*)/);

            if(!matches){
                return '';
            }

            if(matches.length > 2){
                wkt = matches[2];
            }else{
                wkt = matches[1];
            }
        }

        return parseWKT(wkt); //see wellknown.js
    },

    //
    //
    //
    wktValueToDescription:function(wkt, simple_polygon = false){

        let decPoints = 7; //5
        let matches = wkt.match(/\??(\S{1,2})\s+(.*)/);
        if (! matches) {
            return { type:'', summary:''};
        }
        let typeCode = '';
        if(matches.length>2){
            typeCode = matches[1];
            wkt = matches[2];
        }else{
            wkt = matches[1];
        }

        let gjson = window.hWin.HEURIST4.geo.getParsedWkt(wkt, false);
        let resdata = window.hWin.HEURIST4.geo.prepareGeoJSON(gjson, null, 'google');

        if($.isEmptyObject(resdata)){
            return { type:'', summary:''};
        }

        if(resdata.Point.length==1 && resdata.Polyline.length==0 && resdata.Polygon.length==0){
            
                let point = resdata.Point[0][0];
            
                return { type: "Point", summary: point.lng.toFixed(decPoints)+", "+point.lat.toFixed(decPoints) };
            
        }else if(resdata.Point.length==0 && resdata.Polyline.length==1 && resdata.Polygon.length==0){
            
                let path = resdata.Polyline[0];
                let point1 = path.shift();
                let point2 = path.pop();
                return { type: "Path", summary: "X,Y ("+ 
                            point1.lng.toFixed(decPoints)+","+point1.lat.toFixed(decPoints)
                            +") - ("+
                            point2.lng.toFixed(decPoints)+","+point2.lat.toFixed(decPoints)+")" };
            
        }else if (resdata.Point.length>0 || resdata.Polyline.length>0 || resdata.Polygon.length>0){
            
            let type = '';
            if(resdata.Point.length==0 && resdata.Polyline.length==0 && resdata.Polygon.length==1){
                     if (typeCode == "r") type = "Rectangle";
                        else if (typeCode == "c") type = "Circle";
                            else type = "Polygon";
            }else{
                if(resdata.Point.length>0) type = resdata.Point.length+' point'+((resdata.Point.length>1)?'s':'')+', ';
                if(resdata.Polyline.length>0) type = type + resdata.Polyline.length+' path'+((resdata.Polyline.length>1)?'s':'')+', ';
                if(resdata.Polygon.length>0) type = type + resdata.Polygon.length+' polygon'+((resdata.Polygon.length>1)?'s':'')+', ';
                type = 'Collection (' + type.substring(0,type.length-2)+')';
            }
            
            let extent = resdata._extent;
            let summary = "X "+extent.xmin.toFixed(decPoints)+","+extent.xmax.toFixed(decPoints)
                        +" Y "+extent.ymin.toFixed(decPoints)+","+extent.ymax.toFixed(decPoints);
            if(type == 'Polygon' && !simple_polygon){
                decPoints = extent.xmin > 180 || extent.xmax > 180 || extent.xmin < -180 || extent.xmax < -180
                            || extent.ymin > 90 || extent.ymax > 90 || extent.ymin < -90 || extent.ymax < -90 ? 0 : decPoints;

                let point_count = 0;
                for(let i = 0; i < gjson.coordinates.length; i ++){
                    point_count += gjson.coordinates[i].length;
                }
                summary = 'n=' + point_count + ' (' + summary + ')';
            }

            return { type: type, summary: summary};
            
        }else{
            return { type:'', summary:''};
        }
    },

    //
    //
    //
    wktValueToDescription_old:function(wkt){
        
        // parse a well-known-text value and return the standard description (type + summary)
        let matches = wkt.match(/^(p|c|r|pl|l) (?:point|polygon|linestring)\s?\(?\(([-0-9.+, ]+?)\)/i);
        if(matches && matches.length>1){
            
        let typeCode = matches[1];

        let pointPairs = matches[2].split(/,/);
        let X = [], Y = [];
        for (let i=0; i < pointPairs.length; ++i) {
            let point = pointPairs[i].split(/\s+/);
            X.push(parseFloat(point[0]));
            Y.push(parseFloat(point[1]));
        }

        if (typeCode == "p") {
            return { type: "Point", summary: X[0].toFixed(5)+", "+Y[0].toFixed(5) };
        }
        else if (typeCode == "l") {
            return { type: "Path", summary: "X,Y ("+ X.shift().toFixed(5)+","+Y.shift().toFixed(5)+") - ("+X.pop().toFixed(5)+","+Y.pop().toFixed(5)+")" };
        }
        else {
            X.sort((a, b) => a - b);
            Y.sort((a, b) => a - b);

            let type = "Unknown";
            if (typeCode == "pl") type = "Polygon";
            else if (typeCode == "r") type = "Rectangle";
                else if (typeCode == "c") type = "Circle";
                    else if (typeCode == "l") type = "Path";

            let minX = X[0];
            let minY = Y[0];
            let maxX = X.pop();
            let maxY = Y.pop();
            return { type: type, summary: "X "+minX.toFixed(5)+","+maxX.toFixed(5)+" Y "+minY.toFixed(5)+","+maxY.toFixed(5) };
        }
        }else{
            return {type:'',summary:''};
        }
        
    },
    

    /*
    0 X pixel width
    1
    2
    3 Y pixel width
    4 topleft pixel X
    5 topleft pixel Y
    */    
    parseWorldFile: function (data, image_width, image_height){
        if(data){
            let lines = data.split('\r\n');
            if(!(lines && lines.length>5)) lines = data.split('\n');
        
            if(lines && lines.length>5){
                let nums = [];
                for(let i=0; i<lines.length; i++){
                    if(window.hWin.HEURIST4.util.isNumber(lines[i])){
                        nums.push( parseFloat(lines[i]) );
                    }
                }
                if(nums.length>5){
/*                    
(W-E)/(width pixels)
0
0
(N-S)/(width pixels)
West+.5*abs((W-E)/(width pixels))
North-.5*abs((N-S)/(height pixels))
*/
                    //num[3] is always negative
                    let xmin = nums[4] - 0.5 * nums[0];
                    let ymax = nums[5] + 0.5 * nums[3];
                    let xmax = xmin + nums[0] * image_width;
                    let ymin = ymax + nums[3] * image_height;
                    
                    return window.hWin.HEURIST4.geo.boundingBoxToWKT([[ymin,xmin],[ymax,xmax]]);
                }
            }
            
        }
        return null;
    }
    
}
}