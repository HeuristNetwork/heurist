/**
 * @file utils_geo.js
 * @brief Provides utility functions for handling WKT (Well-Known Text) and GeoJSON data formats.
 * @fileOverview This file contains a collection of functions under the `HEURIST4.geo` namespace
 * designed to parse, convert, and manipulate geospatial data. Key functionalities include:
 * - Converting WKT to GeoJSON and vice-versa (using external `parseWKT` and `stringifyWKT`).
 * - Preparing GeoJSON data for different mapping libraries (e.g., Google Maps, Timemap.js).
 * - Extracting bounding boxes from WKT or GeoJSON data.
 * - Merging multiple bounding boxes.
 * - Generating human-readable descriptions from WKT values.
 * - Parsing WorldFile data to extract georeferencing information.
 * These utilities are used in various parts of Heurist that deal with maps and geographic data.
 * @see editing_input.js
 * @see mapDraw.js
 * @see recordset.js
 *
 * @package Heurist academic knowledge management system
 * @subpackage hclient\core
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 4.0
 */

/* global parseWKT, stringifyWKT */

if (!window.hWin.HEURIST4){
    window.hWin.HEURIST4 = {};
}
//init only once
if (!window.hWin.HEURIST4.geo) 
{

/**
 * @namespace HEURIST4.geo
 * @memberof HEURIST4
 * @description Provides a collection of utility functions for working with geospatial data,
 * primarily focusing on WKT (Well-Known Text) and GeoJSON formats. This includes parsing,
 * conversion between formats, preparing data for map display, calculating bounding boxes,
 * and generating textual descriptions of geographic features.
 */
window.hWin.HEURIST4.geo = {
    
    /**
     * Parses GeoJSON data and prepares it for use with mapping libraries, optionally calculating the extent.
     * This function can handle FeatureCollection, Feature, and GeometryCollection types, recursively
     * processing nested structures. It transforms coordinates into a format suitable for
     * Google Maps or a generic array format (e.g., for Timemap.js).
     *
     * Internal helper functions `__loadGeoJSON_primitive`, `_isvalid_pnt`, `__extractCoords`, `__extractCoords2`
     * handle the detailed processing of individual geometry types and coordinate transformations.
     *
     * @param {Object|string} mdata - The GeoJSON data to process. Can be a GeoJSON object or a JSON string.
     * @param {Object|Array} [resdata] - An optional initial results object/array to accumulate processed shapes.
     *                                   If `_format` is 'google', this should be an object like:
     *                                   `{Point:[], Polyline:[], Polygon:[], _extent:{xmin,xmax,ymin,ymax}}`.
     *                                   Otherwise, it's an array of shape objects.
     * @param {string} [_format] - The target format. 'google' for Google Maps specific structure,
     *                             otherwise a generic array of shapes is produced. If 'google',
     *                             `resdata._extent` will be populated.
     * @returns {Object|Array} The processed shape data. If `_format` is 'google', returns an object
     *                         with Point, Polyline, Polygon arrays and an _extent object.
     *                         Otherwise, returns an array of shape objects. Returns an empty object
     *                         if input `mdata` is null, empty, or invalid JSON.
     */
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
     * Converts a WKT (Well-Known Text) string to a shape object suitable for Timemap.js or Google Maps.
     * It uses an external `parseWKT` function (from wellknown.js) to convert WKT to GeoJSON first,
     * then processes the GeoJSON coordinates.
     *
     * @param {string} type - The geometry type code (e.g., 'p' for point, 'l' for polyline, 'pl' for polygon, 'c' for circle).
     * @param {string} wkt - The WKT string representing the geometry.
     * @param {number} format - The desired output format:
     *                          - 0 for Timemap.js shape object.
     *                          - 1 for Google Maps (requires `google.maps.LatLng` to be available).
     * @param {Object} [google] - The Google Maps API object (only used if format is 1, to access `google.maps.LatLng` and `google.maps.LatLngBounds`).
     * @returns {Object|Array|null} The shape data.
     *                            - If format is 0 (Timemap): Returns a Timemap shape object (e.g., `{point:{lat,lon}}`, `{polyline:[{lat,lon},...]}`, `{polygon:[{lat,lon},...]}`) or an array of such objects.
     *                            - If format is 1 (Google): Returns an object `{bounds:google.maps.LatLngBounds, points:Array<google.maps.LatLng>}`.
     *                            Returns `null` if `format` is 1 and `google.maps.LatLng` is not available, or if WKT parsing fails.
     * @todo For format 0 (Timemap) and type 'circle', implement proper radius calculation using geodesy library instead of approximation.
     * @todo For format 1 (Google) and type 'circle', the implementation for creating a Google Maps circle is noted as a TODO (ARTEM TODO).
     * @todo Consider support for KML (format 2) and OpenLayers (format 3) as originally placeholder-commented.
     */
    parseWKTCoordinates: function(type, wkt, format, google) {
    
        if(format==1 && typeof google.maps.LatLng != "function") { // Ensure google object and maps.LatLng are available for format 1
            return null;
        }
        
        let gjson =  parseWKT(wkt); //wkt to json via wellknown.js
        
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
    
    /**
     * Calculates the bounding box for a given WKT geometry.
     * The input `geodata` is expected to be an array where the first element
     * can be an object `{wkt: "...", geotype: "..."}` or just a WKT string.
     * It internally uses `wktValueToShapes` to parse the WKT and determine the extent.
     *
     * @param {Array<Object|string>} geodata - An array containing the WKT data.
     *                                         Typically `[{wkt: "WKT_STRING", geotype: "TYPE_CODE"}]`
     *                                         or `["WKT_STRING"]`.
     * @returns {Array<Array<number>>|null} The bounding box as `[[ymin, xmin], [ymax, xmax]]`,
     *                                      or `null` if geodata is invalid or extent cannot be determined.
     */
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

    /**
     * Merges an array of bounding boxes into a single bounding box that encompasses all of them.
     * Each bounding box in the input array should be in the format `[[ymin, xmin], [ymax, xmax]]`.
     * Invalid bounding boxes in the input array are skipped.
     *
     * @param {Array<Array<Array<number>>>} extents - An array of bounding box extents.
     *                                                Each extent is `[[ymin, xmin], [ymax, xmax]]`.
     * @returns {Array<Array<number>>|null} The merged bounding box as `[[ymin, xmin], [ymax, xmax]]`,
     *                                      or `null` if no valid extents were provided.
     */
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

    /**
     * Converts a bounding box array into a WKT Polygon string.
     * The input extent is expected to be in the format `[[ymin, xmin], [ymax, xmax]]`.
     *
     * @param {Array<Array<number>>} extent - The bounding box extent `[[ymin, xmin], [ymax, xmax]]`.
     * @returns {string|null} A WKT Polygon string representing the bounding box,
     *                        or `null` if the input extent is invalid.
     */
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
    
    /**
     * Checks if two bounding boxes are equal.
     * Note: This function currently always returns `false` and needs to be implemented.
     *
     * @param {Array<Array<number>>} ext1 - The first bounding box `[[ymin, xmin], [ymax, xmax]]`.
     * @param {Array<Array<number>>} ext2 - The second bounding box `[[ymin, xmin], [ymax, xmax]]`.
     * @returns {boolean} True if the bounding boxes are considered equal, otherwise false.
     * @todo Implement the actual logic for comparing bounding boxes.
     */
    isEqualBoundingBox: function(ext1, ext2){
        return false;    
    },
    
    /**
     * Converts a Heurist map bookmark string into a bounding box array.
     * The bookmark string is expected to be in the format "Name,MinLongitude,MaxLongitude,MinLatitude,MaxLatitude".
     *
     * @param {string} geodata - The Heurist map bookmark string.
     * @returns {Array<Array<number>>|null} The bounding box as `[[ymin, xmin], [ymax, xmax]]`,
     *                                      or `null` if the input string is not in the expected format.
     */
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
    
    /**
     * Converts a WKT (Well-Known Text) value, which may include a Heurist type prefix (e.g., "p ", "l "),
     * into an array of shapes suitable for Google Maps or Timemap.js.
     * It uses an external `parseWKT` (from wellknown.js) and the internal `prepareGeoJSON` function.
     * Handles a special case for 'circle' type by approximating it as a polygon.
     *
     * @param {string} wkt - The WKT string, optionally prefixed with a type code (e.g., "p (POINT(...))").
     * @param {string} [typeCode] - The geometry type code (e.g., 'p', 'l', 'c'). If empty or null,
     *                              the function attempts to extract it from the `wkt` string.
     * @param {string} [_format] - The target format: 'google' for Google Maps specific structure,
     *                             otherwise a generic array of shapes (for Timemap.js).
     *                             If 'google', the output will include an `_extent` property.
     * @returns {Object|Array|undefined} The processed shape data.
     *                                 - If `_format` is 'google': An object `{Point:[], Polyline:[], Polygon:[], _extent:{...}}`.
     *                                 - Otherwise: An array of shape objects.
     *                                 Returns `undefined` if the WKT string (after stripping type code) is invalid or cannot be parsed.
     */
    wktValueToShapes:function(wkt, typeCode, _format){

        if(window.hWin.HEURIST4.util.isempty(typeCode)){

            let matches = wkt.match(/\??(\S{1,2})\s+(.*)/);
            if (! matches) {
                return; // Return undefined if no match
            }
            
            if(matches.length>2){
                typeCode = matches[1];
                wkt = matches[2];
            }else{
                wkt = matches[1];
            }
        }   
        
        let gjson =  parseWKT(wkt);    //wkt to json via wellknown.js
      
        let resdata;
        
        //special case to support old format for circles
        if(typeCode=='c' || typeCode=='circle'){
            if (!gjson || !gjson.coordinates || !Array.isArray(gjson.coordinates[0]) || gjson.coordinates[0].length < 2 || !Array.isArray(gjson.coordinates[1]) || gjson.coordinates[1].length < 2) {
                return; // Invalid circle WKT representation
            }
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

    /**
     * Parses a WKT (Well-Known Text) string into a GeoJSON object.
     * Optionally, it can check for and strip a Heurist-specific type prefix (e.g., "p ", "l ")
     * from the WKT string before parsing. Uses the external `parseWKT` function (from wellknown.js).
     *
     * @param {string} wkt - The WKT string to parse.
     * @param {boolean} [checkWkt=false] - If true, attempts to identify and strip a Heurist type prefix
     *                                     (e.g., "p ", "l ") from the `wkt` string before parsing.
     * @returns {Object|string} The parsed GeoJSON object, or an empty string if `checkWkt` is true
     *                          and no valid WKT string is found after attempting to strip a prefix.
     *                          If `parseWKT` itself fails, its behavior will dictate the return (e.g., it might throw an error or return null).
     */
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

        return parseWKT(wkt); // see wellknown.js for parsing implementation
    },

    /**
     * Generates a human-readable description (type and summary) for a WKT (Well-Known Text) value.
     * It attempts to extract a Heurist type prefix, then parses the WKT to GeoJSON,
     * and uses `prepareGeoJSON` to analyze the geometry.
     * Provides different summary formats for points, paths, and collections/polygons (including extent).
     *
     * @param {string} wkt - The WKT string, optionally prefixed with a Heurist type code (e.g., "p POINT(...)").
     * @param {boolean} [simple_polygon=false] - If true and the type is Polygon, a simpler summary is generated
     *                                           (omitting point count and detailed extent coordinates for very large/small values).
     * @returns {{type: string, summary: string}} An object containing the determined `type` (e.g., "Point", "Path", "Polygon", "Collection (...)")
     *                                           and a `summary` string. Returns `{type:'', summary:''}` if parsing fails or geometry is empty.
     */
    wktValueToDescription:function(wkt, simple_polygon = false){

        let decPoints = 7;
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

    /**
     * Generates a human-readable description (type and summary) for a WKT value using an older parsing method.
     * This version uses regular expressions to directly parse common WKT patterns (Point, Path, Polygon, Rectangle, Circle)
     * and their coordinates, then calculates a summary.
     * It is generally less robust than `wktValueToDescription` which uses GeoJSON conversion.
     *
     * @param {string} wkt - The WKT string, expected to start with a type code (p, c, r, pl, l)
     *                       followed by the geometry definition.
     * @returns {{type: string, summary: string}} An object containing the determined `type`
     *                                           (e.g., "Point", "Path", "Polygon") and a `summary` string.
     *                                           Returns `{type:'', summary:''}` if parsing fails.
     * @deprecated Consider using the more robust `wktValueToDescription` function instead.
     */
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
    

    /**
     * Parses the content of a World File and calculates the bounding box WKT for the corresponding image.
     * A World File typically contains 6 lines representing affine transformation parameters:
     * Line 1: X-component of the pixel width (A)
     * Line 2: Y-component of the pixel width (D)
     * Line 3: X-component of the pixel height (B)
     * Line 4: Y-component of the pixel height (E)
     * Line 5: X-coordinate of the center of the upper-left pixel (C)
     * Line 6: Y-coordinate of the center of the upper-left pixel (F)
     * This function uses these parameters along with image dimensions to compute the geographic extent.
     *
     * @param {string} data - The content of the World File as a string.
     * @param {number} image_width - The width of the corresponding image in pixels.
     * @param {number} image_height - The height of the corresponding image in pixels.
     * @returns {string|null} A WKT Polygon string representing the calculated bounding box,
     *                        or `null` if the World File data is invalid or insufficient.
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