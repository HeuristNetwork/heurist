/**
* WKT and GeiJSON utility functions
*
* @see editing_input.js, mapDraw.js, recordset.js
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
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

/**
 * @namespace HEURIST4.geo
 * @description Provides utility functions for handling and converting geographic data,
 * primarily between Well-Known Text (WKT), GeoJSON, and formats suitable for
 * mapping libraries like Google Maps and Timemap.js.
 */
window.hWin.HEURIST4.geo = {
    
    /**
     * Processes GeoJSON data (string or object) and converts its geometries into a simpler array of shapes
     * suitable for Timemap.js or an object with arrays of Google Maps LatLng objects, also calculating the overall extent.
     *
     * It can handle GeoJSON `FeatureCollection`, `Feature`, and `GeometryCollection` types, recursively
     * processing their components.
     *
     * @function prepareGeoJSON
     * @memberof HEURIST4.geo
     * @param {string|Object} mdata - The GeoJSON data. Can be a JSON string or a JavaScript object.
     * @param {Object|Array} [resdata] - An optional accumulator object/array for the results.
     *                                  If `_format` is 'google', this should be an object like:
     *                                  `{Point:[],Polyline:[],Polygon:[],_extent:{xmin,xmax,ymin,ymax}}`.
     *                                  Otherwise, it's an array. If not provided, it's initialized based on `_format`.
     * @param {string} [_format] - The desired output format. If 'google', prepares data for Google Maps.
     *                             Otherwise, prepares data for Timemap.js (array of shape objects).
     * @returns {Object|Array} The processed shapes and extent.
     *                         - If `_format` is 'google': Returns an object with `Point`, `Polyline`, `Polygon` arrays
     *                           (containing Google Maps LatLng literals or arrays of such for paths/polygons)
     *                           and an `_extent` object `{xmin, xmax, ymin, ymax}`.
     *                         - Otherwise (Timemap.js format): Returns an array of shape objects, e.g.,
     *                           `{point: {lat, lon}}`, `{polyline: [{lat, lon}, ...]}`, `{polygon: [{lat, lon}, ...]}`.
     *                         Returns an empty object `{}` if `mdata` is null, empty, or invalid JSON.
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
            /**
             * @function __loadGeoJSON_primitive
             * @private
             * @description Internal helper to process a single GeoJSON geometry object.
             * @param {Object} geometry - A GeoJSON geometry object.
             */
            function __loadGeoJSON_primitive(geometry){

                if(!$.isEmptyObject(geometry))
                {
                    if(geometry.type=="GeometryCollection"){
                        let l;
                        for (l=0; l<geometry.geometries.length; l++){
                            __loadGeoJSON_primitive(geometry.geometries[l]); //another collection or feature
                        }
                    }else{

                        /**
                         * @function _isvalid_pnt
                         * @private
                         * @description Validates a coordinate point and updates the extent if `resdata._extent` exists.
                         * @param {Array<number>} pnt - A coordinate pair [longitude, latitude].
                         * @returns {boolean} True if the point is valid, false otherwise.
                         */
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
                        /**
                         * @function __extractCoords
                         * @private
                         * @description Recursively extracts coordinates from a GeoJSON geometry's coordinate array
                         *              and formats them for Google Maps (array of {lat, lng} objects).
                         * @param {Array} shapes - Accumulator for extracted shapes.
                         * @param {Array} coords - GeoJSON coordinates array.
                         * @param {string} typeCode - The GeoJSON geometry type (e.g., 'Point', 'LineString').
                         * @returns {Array} The `shapes` array with added Google Maps formatted coordinates.
                         */
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
                                    shapes = shape; // For MultiPoint, shapes become the array of points directly
                                }else{
                                    shapes.push(shape); // For LineString/Polygon, push the array of points as one shape
                                }
                            }else{
                                let n;
                                for (n=0; n<coords.length; n++){ // Handles MultiLineString, MultiPolygon, Polygon with holes
                                    if(Array.isArray(coords[n]))
                                        shapes = __extractCoords(shapes, coords[n], typeCode);
                                }
                            }
                            return shapes;
                        }
                        //for timemap
                        /**
                         * @function __extractCoords2
                         * @private
                         * @description Recursively extracts coordinates from a GeoJSON geometry's coordinate array
                         *              and formats them for Timemap.js.
                         * @param {Array} shapes - Accumulator for extracted shapes.
                         * @param {Array} coords - GeoJSON coordinates array.
                         * @param {string} typeCode - The simplified type code ('point', 'polyline', 'polygon').
                         * @returns {Array} The `shapes` array with added Timemap.js formatted shape objects.
                         */
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
                                        if(typeCode=='point'){ // For MultiPoint, each point is a separate shape object
                                            shape.push({point:{lat:pnt[1], lon:pnt[0]}});
                                        }else{ // For LineString/Polygon path
                                            shape.push({lat:pnt[1], lon:pnt[0]});
                                        }
                                    }
                                }
                                if(typeCode=='point'){
                                    shapes = shape; // Assign array of point objects directly
                                }else{
                                    let r = {};
                                    r[typeCode] = shape; // {polyline: [...]} or {polygon: [...]}
                                    shapes.push(r);
                                }
                            }else{
                                //multi (MultiLineString, MultiPolygon, Polygon with holes)
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
                                        // Google format expects points to be in an array representing a single "marker"
                                        // For MultiPoint, __extractCoords already returns flat array of points,
                                        // so here we wrap each point in another array to match expected structure for resdata['Point']
                                        if (geometry.type=="MultiPoint" && shapes[n].lat !== undefined) { // it's a single point from MultiPoint
                                            resdata['Point'].push( [shapes[n]] );
                                        } else if (geometry.type=="Point") { // Single point
                                             resdata['Point'].push( [shapes[n]] );
                                        } else { // Should not happen if __extractCoords is correct for MultiPoint
                                            resdata['Point'].push( shapes[n] );
                                        }
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

                        }else{ // Timemap format

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
     * Converts a Well-Known Text (WKT) string into a format suitable for Timemap.js or Google Maps.
     * It uses the external `parseWKT` function (from wellknown.js) to parse the WKT string into GeoJSON structure first.
     *
     * @function parseWKTCoordinates
     * @memberof HEURIST4.geo
     * @param {string} type - A short type code for the WKT geometry (e.g., 'p' for point, 'l' for polyline, 'pl' for polygon, 'c' for circle, 'r' for rectangle).
     *                        These codes map to WKT types like POINT, LINESTRING, POLYGON.
     * @param {string} wkt - The Well-Known Text string representing the geometry (e.g., "POINT(151.2 -33.8)").
     * @param {number} format - The desired output format:
     *  - `0`: Timemap.js format (returns a shape object like `{point: {lat, lon}}` or `{polyline: [{lat,lon},...]}`).
     *  - `1`: Google Maps format (returns an object `{bounds: google.maps.LatLngBounds, points: google.maps.LatLng[]}`).
     * @param {Object} [google] - The Google Maps API object, typically `window.google`. Required if `format` is `1`.
     *                            It expects `google.maps.LatLng` and `google.maps.LatLngBounds` to be available.
     * @returns {Object|null} The converted shape information in the specified format, or `null` if conversion fails
     *                        (e.g., Google Maps API not available when requested, or WKT is invalid).
     * @todo Implement conversion for KML (format 2) and OpenLayers (format 3).
     * @todo For circle in Timemap.js format (format 0), the current implementation approximates a circle with 40 vertices.
     *       A more precise method or reliance on a geodesy library might be preferable.
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

    /**
     * Calculates the bounding box of a WKT geometry.
     * It first converts the WKT to an internal shape representation (using `wktValueToShapes` with 'google' format)
     * and then extracts the `_extent` property from that representation.
     *
     * @function getWktBoundingBox
     * @memberof HEURIST4.geo
     * @param {Array<Object|string>} geodata - An array containing geographic data.
     *                                      Expected to have at least one element.
     *                                      If the first element is an object with `wkt` and `geotype` properties, those are used.
     *                                      Otherwise, the first element itself is assumed to be a WKT string.
     * @returns {Array<Array<number>>|null} A bounding box defined as `[[ymin, xmin], [ymax, xmax]]`,
     *                                      or `null` if input is invalid or extent cannot be determined.
     */
    getWktBoundingBox: function(geodata){

         if(geodata && geodata[0]){

            let shape =  null;
            if($.isPlainObject(geodata[0]) && geodata[0].wkt){ // Heurist specific geo object
                shape = window.hWin.HEURIST4.geo.wktValueToShapes( geodata[0].wkt, geodata[0].geotype, 'google' );
            }else{ // Assumed to be a WKT string directly
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
     *
     * @function mergeBoundingBox
     * @memberof HEURIST4.geo
     * @param {Array<Array<Array<number>>>} extents - An array of bounding boxes. Each bounding box
     *                                                is expected to be in the format `[[ymin, xmin], [ymax, xmax]]`.
     * @returns {Array<Array<number>>|null} The merged bounding box in the same format,
     *                                      or `null` if no valid extents were provided.
     */
    mergeBoundingBox: function(extents){

        let isset = false;
        let minLat = 9999, maxLat = -9999, minLng = 9999, maxLng = -9999;
        $(extents).each(function(idx, item){

            let isValid = (Array.isArray(item) && item.length==2 &&
                           Array.isArray(item[0]) && item[0].length==2 &&
                           Array.isArray(item[1]) && item[1].length==2 &&
                           window.hWin.HEURIST4.util.isNumber(item[0][0]) && // ymin
                           window.hWin.HEURIST4.util.isNumber(item[0][1]) && // xmin
                           window.hWin.HEURIST4.util.isNumber(item[1][0]) && // ymax
                           window.hWin.HEURIST4.util.isNumber(item[1][1]));  // xmax


            if(isValid){
                if (item[0][0] < minLat) minLat = item[0][0]; // ymin
                if (item[1][0] > maxLat) maxLat = item[1][0]; // ymax
                if (item[0][1] < minLng) minLng = item[0][1]; // xmin
                if (item[1][1] > maxLng) maxLng = item[1][1]; // xmax
                isset = true;
            }
        });

        return isset ?[[minLat, minLng],[maxLat, maxLng]] :null;
    },

    /**
     * Converts a bounding box array `[[ymin, xmin], [ymax, xmax]]` into a WKT Polygon string.
     *
     * @function boundingBoxToWKT
     * @memberof HEURIST4.geo
     * @param {Array<Array<number>>} extent - The bounding box array.
     * @returns {string|null} The WKT string for the polygon representing the bounding box,
     *                        or `null` if the input extent is invalid.
     *                        The WKT Polygon coordinates are ordered: (xmin ymin, xmin ymax, xmax ymax, xmax ymin, xmin ymin).
     */
    boundingBoxToWKT: function(extent){

        let isValid = (Array.isArray(extent) && extent.length==2 &&
                       Array.isArray(extent[0]) && extent[0].length==2 &&
                       Array.isArray(extent[1]) && extent[1].length==2 &&
                       window.hWin.HEURIST4.util.isNumber(extent[0][0]) && // ymin
                       window.hWin.HEURIST4.util.isNumber(extent[0][1]) && // xmin
                       window.hWin.HEURIST4.util.isNumber(extent[1][0]) && // ymax
                       window.hWin.HEURIST4.util.isNumber(extent[1][1]));  // xmax
                // Coordinate range checks like Math.abs(extent[0][1])<=180.0 etc. could be added if strict validation is needed.

        if(isValid){
            // WKT Polygon format: POLYGON((lon1 lat1, lon2 lat2, ..., lon1 lat1))
            // Bbox: [[ymin, xmin], [ymax, xmax]]
            // Coordinates for WKT polygon: (xmin ymin, xmin ymax, xmax ymax, xmax ymin, xmin ymin)
            let geojson = {type:'Feature', geometry:{type:'Polygon',
                    coordinates:
            [[[extent[0][1],extent[0][0]], // xmin, ymin
              [extent[0][1],extent[1][0]], // xmin, ymax
              [extent[1][1],extent[1][0]], // xmax, ymax
              [extent[1][1],extent[0][0]], // xmax, ymin
              [extent[0][1],extent[0][0]]  // xmin, ymin (to close the polygon)
            ]]}};

            return stringifyWKT( geojson ); // stringifyWKT is from wellknown.js
        }else{
            return null;
        }
    },

    /**
     * Placeholder function to compare two bounding boxes. Currently always returns false.
     *
     * @function isEqualBoundingBox
     * @memberof HEURIST4.geo
     * @param {Array<Array<number>>} ext1 - The first bounding box.
     * @param {Array<Array<number>>} ext2 - The second bounding box.
     * @returns {boolean} Currently hardcoded to `false`.
     * @todo Implement actual comparison logic if needed.
     */
    isEqualBoundingBox: function(ext1, ext2){
        return false;
    },

    /**
     * Converts a Heurist-specific map document bookmark string into a bounding box array.
     * The bookmark string is expected to be comma-separated: "Name,MinLongitude,MaxLongitude,MinLatitude,MaxLatitude".
     *
     * @function getHeuristBookmarkBoundingBox
     * @memberof HEURIST4.geo
     * @param {string} geodata - The Heurist map bookmark string.
     * @returns {Array<Array<number>>|null} A bounding box `[[ymin, xmin], [ymax, xmax]]` or `null` if parsing fails.
     */
    getHeuristBookmarkBoundingBox: function(geodata){

         if(geodata){
            //Name, Min Longitude,Max Longitude, Min Latitude, Max Latitude
            let vals = geodata.split(',')
            if(vals.length>4){
                // Output format: [[ymin,xmin],[ymax,xmax]]
                // Input vals:    [name, xmin, xmax, ymin, ymax]
                // So, vals[3] is ymin, vals[1] is xmin, vals[4] is ymax, vals[2] is xmax
                const ymin = parseFloat(vals[3]);
                const xmin = parseFloat(vals[1]);
                const ymax = parseFloat(vals[4]);
                const xmax = parseFloat(vals[2]);

                if (!isNaN(ymin) && !isNaN(xmin) && !isNaN(ymax) && !isNaN(xmax)) {
                    return [[ymin, xmin],[ymax, xmax]];
                }
            }
         }
         return null;
    },

    /**
     * Converts a WKT string, optionally prefixed with a Heurist type code (e.g., "p ", "l "),
     * into a shape object/array suitable for Google Maps or Timemap.js.
     * This function first parses the WKT (stripping the prefix if present) into GeoJSON using `parseWKT`,
     * then processes this GeoJSON using `prepareGeoJSON`.
     * Handles a special case for 'circle' type codes by generating a 40-sided polygon.
     *
     * @function wktValueToShapes
     * @memberof HEURIST4.geo
     * @param {string} wkt - The WKT string, possibly with a type prefix (e.g., "p POINT(1 2)").
     * @param {string} [typeCode] - The Heurist type code (e.g., 'p', 'l', 'c', 'r', 'pl').
     *                              If empty or null, the function attempts to extract it from the `wkt` string.
     * @param {string} _format - The desired output format: 'google' or other (for Timemap.js).
     * @returns {Object|Array|undefined} The processed shapes in the specified format.
     *                                   Returns `undefined` if the initial WKT string (after stripping prefix) is empty or invalid.
     *                                   For 'google' format, returns an object: `{Point:[], Polyline:[], Polygon:[], _extent:{}}`.
     *                                   For Timemap.js, returns an array of shape objects.
     */
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
    
    /**
     * Converts a WKT string, optionally prefixed with a Heurist type code (e.g., "p ", "l "),
     * into a shape object/array suitable for Google Maps or Timemap.js.
     * This function first parses the WKT (stripping the prefix if present) into GeoJSON using `parseWKT`,
     * then processes this GeoJSON using `prepareGeoJSON`.
     * Handles a special case for 'circle' type codes by generating a 40-sided polygon.
     *
     * @function wktValueToShapes
     * @memberof HEURIST4.geo
     * @param {string} wkt - The WKT string, possibly with a type prefix (e.g., "p POINT(1 2)").
     * @param {string} [typeCode] - The Heurist type code (e.g., 'p', 'l', 'c', 'r', 'pl').
     *                              If empty or null, the function attempts to extract it from the `wkt` string.
     * @param {string} _format - The desired output format: 'google' or other (for Timemap.js).
     * @returns {Object|Array|undefined} The processed shapes in the specified format.
     *                                   Returns `undefined` if the initial WKT string (after stripping prefix) is empty or invalid.
     *                                   For 'google' format, returns an object: `{Point:[], Polyline:[], Polygon:[], _extent:{}}`.
     *                                   For Timemap.js, returns an array of shape objects.
     */
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

    /**
     * Parses a WKT string into a GeoJSON-like object using the external `parseWKT` function.
     * Optionally, it can first check for and strip a Heurist-specific type prefix from the WKT string
     * (e.g., "p ", "l ") before parsing.
     *
     * @function getParsedWkt
     * @memberof HEURIST4.geo
     * @param {string} wkt - The Well-Known Text string.
     * @param {boolean} [checkWkt=false] - If true, checks for a Heurist type prefix. If found,
     *                                     it parses only the WKT data part; otherwise, it parses the whole string.
     *                                     If false (default), parses the entire `wkt` string as is.
     * @returns {Object|string} The GeoJSON-like object parsed from the WKT string.
     *                          Returns an empty string if `checkWkt` is true and the WKT string doesn't match the expected prefixed format.
     *                          The structure of the returned object depends on the `parseWKT` implementation (from wellknown.js).
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

        return parseWKT(wkt); //see wellknown.js
    },

    /**
     * Generates a human-readable description (type and summary) for a given WKT string.
     * It parses the WKT, converts it to a 'google' format to get points and extent,
     * and then constructs a summary string.
     *
     * @function wktValueToDescription
     * @memberof HEURIST4.geo
     * @param {string} wkt - The Well-Known Text string, possibly with a Heurist type prefix.
     * @param {boolean} [simple_polygon=false] - If true and the type is 'Polygon', the summary will not include
     *                                           the point count and will use a fixed number of decimal places for coordinates.
     *                                           If false (default for polygons), it includes point count and adjusts decimal places for large coordinates.
     * @returns {{type: string, summary: string}} An object containing:
     *          - `type` (string): The determined geometry type (e.g., "Point", "Path", "Polygon", "Collection").
     *          - `summary` (string): A descriptive summary, often including key coordinates or extent.
     *          Returns `{ type:'', summary:''}` if WKT is invalid or empty.
     */
    wktValueToDescription:function(wkt, simple_polygon = false){

        let decPoints = 7; // Default number of decimal points for coordinate display. Can be overridden for large coordinates.
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
     * An older/legacy version for generating a description from a WKT string.
     * This version uses a simpler regex matching for specific WKT types and might not handle
     * all cases or complex WKTs as robustly as `wktValueToDescription`.
     * It typically formats coordinates to 5 decimal places.
     *
     * @function wktValueToDescription_old
     * @memberof HEURIST4.geo
     * @deprecated Prefer {@link HEURIST4.geo.wktValueToDescription} for more comprehensive parsing.
     * @param {string} wkt - The Well-Known Text string. It expects a simple format like "p POINT(...)", "l LINESTRING(...)", etc.
     * @returns {{type: string, summary: string}} An object containing:
     *          - `type` (string): The determined geometry type (e.g., "Point", "Path", "Polygon", "Rectangle", "Circle", "Unknown").
     *          - `summary` (string): A descriptive summary, often including key coordinates or extent, formatted to 5 decimal places.
     *          Returns `{type:'', summary:''}` if the WKT doesn't match the expected patterns.
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
    

    /*
    Expected World File format:
    Line 1: A (pixel size in the x-direction in map units per pixel)
    Line 2: D (rotation term for row)
    Line 3: B (rotation term for column)
    Line 4: E (pixel size in the y-direction in map units, almost always negative)
    Line 5: C (x-coordinate of the center of the upper left pixel)
    Line 6: F (y-coordinate of the center of the upper left pixel)
    */
    /**
     * Parses a World File content to calculate the geographic bounding box of the corresponding image.
     * A World File defines how pixels in a raster image relate to real-world map coordinates.
     *
     * @function parseWorldFile
     * @memberof HEURIST4.geo
     * @param {string} data - The content of the World File, typically 6 lines of numeric values.
     * @param {number} image_width - The width of the corresponding image in pixels.
     * @param {number} image_height - The height of the corresponding image in pixels.
     * @returns {string|null} A WKT Polygon string representing the calculated bounding box of the image,
     *                        or `null` if parsing fails (e.g., invalid data format, not enough numeric values).
     *                        The calculation assumes a non-rotated image (lines 2 and 3, D and B terms, are effectively 0).
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