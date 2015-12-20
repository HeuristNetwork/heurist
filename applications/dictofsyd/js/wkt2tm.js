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
* Converts wkt to TimeMap item
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/

/**
* Converts wkt to TimeMap item
*
* @param recID
* @param recName
* @param wkt
*/
function getTimeMapItem(recID, recType, recName, startDate, endDate, type, wkt){ //}, infoHTML){


		var	item = {
					start: (startDate || ''),
					end: (endDate && endDate!=startDate)?endDate:'',
					placemarks:[],
					title: recName,
								options:{

									description: recName,
									//url: (record.url ? "'"+record.url+"' target='_blank'"  :"'javascript:void(0);'"), //for timemap popup
									//link: record.url,  //for timeline popup
									recid: recID,
									//rectype: record.rectype,
									//thumb: record.thumb_url,
									icon: RelBrowser.baseURL + "images/16x16pngs/icon16-"+recType.toLowerCase()+".png",  //installDir+
									start: (startDate || ''),
									end: (endDate && endDate!=startDate)?endDate:''
									//,infoHTML: (infoHTML || ''),
								}
					};

		var shape = parseCoordinates(type, wkt, 0);
		if(shape){
			item.placemarks.push(shape);
		}
		return item;
}

//
// extract coordinates from parameters
// format - 0 timemap, 1 google,  2 - kml (todo)
//
function parseCoordinates(type, wkt, format) {

	var matches = null;

	switch (type) {
		case "p":
		case "point":
		    matches = wkt.match(/POINT\((\S+)\s+(\S+)\)/i);
			break;
		case "r":  //rectangle
		case "rect":
		    matches = wkt.match(/POLYGON\(\((\S+)\s+(\S+),\s*(\S+)\s+(\S+),\s*(\S+)\s+(\S+),\s*(\S+)\s+(\S+),\s*\S+\s+\S+\)\)/i);
			break;

		case "c":  //circle
		case "circle":
		    matches = wkt.match(/LINESTRING\((\S+)\s+(\S+),\s*(\S+)\s+\S+,\s*\S+\s+\S+,\s*\S+\s+\S+\)/i);
			break;

		case "l":  //polyline
		case "polyline":
		case "path":
		    matches = wkt.match(/LINESTRING\((.+)\)/i);
		    if (matches){
		    	matches = matches[1].match(/\S+\s+\S+(?:,|$)/g);
			}
			break;

		case "pl": //polygon
		case "polygon":
		    matches = wkt.match(/POLYGON\(\((.+)\)\)/i);
		    if (matches) {
	    		matches = matches[1].match(/\S+\s+\S+(?:,|$)/g);
			}
			break;
	}


	var bounds, southWest, northEast,
		shape  = null,
		points = []; //google points

	if(matches && matches.length>0){

	switch (type) {
		case "p":
		case "point":

			if(format==0){
				shape = { point:{lat: parseFloat(matches[2]), lon:parseFloat(matches[1]) } };
			}else{
		    	point = new google.maps.LatLng(parseFloat(matches[2]), parseFloat(matches[1]));

		    	bounds = new google.maps.LatLngBounds(
                            new google.maps.LatLng(point.lat() - 0.5, point.lng() - 0.5),
		                    new google.maps.LatLng(point.lat() + 0.5, point.lng() + 0.5));
		    	points.push(point);
			}

		break;

		case "r":  //rectangle
		case "rect":

		    if(matches.length<6){
		    	matches.push(matches[3]);
		    	matches.push(matches[4]);
			}

			var x0 = parseFloat(matches[0]);
			var y0 = parseFloat(matches[2]);
			var x1 = parseFloat(matches[5]);
			var y1 = parseFloat(matches[6]);

			if(format==0){
				shape  = [
					{lat: y0, lon: x0},
					{lat: y0, lon: x1},
					{lat: y1, lon: x1},
					{lat: y1, lon: x0},
					];

					shape = {polygon:shape};
			}else{

			    southWest = new google.maps.LatLng(y0, x0);
			    northEast = new google.maps.LatLng(y1, x1);
			    bounds = new google.maps.LatLngBounds(southWest, northEast);

			    points.push(southWest, new google.maps.LatLng(y0, x1), northEast, new google.maps.LatLng(y1, x0));
			}

		break;

		case "c":  //circle
		case "circle":  //circle

			if(format==0){

					var x0 = parseFloat(matches[1]);
					var y0 = parseFloat(matches[2]);
					var radius = parseFloat(matches[3]) - parseFloat(matches[1]);

					shape = [];
					for (var i=0; i <= 40; ++i) {
						var x = x0 + radius * Math.cos(i * 2*Math.PI / 40);
						var y = y0 + radius * Math.sin(i * 2*Math.PI / 40);
						shape.push({lat: y, lon: x});
					}
					shape = {polygon:shape};

			}else{
/* ARTEM TODO
		    	var centre = new google.maps.LatLng(parseFloat(matches[2]), parseFloat(matches[1]));
		    	var oncircle = new google.maps.LatLng(parseFloat(matches[2]), parseFloat(matches[3]));
			    setstartMarker(centre);
			    createcircle(oncircle);

			    //bounds = circle.getBounds();
*/
			}

		break;

		case "l":  ///polyline
		case "path":
		case "polyline":

		case "pl": //polygon
		case "polygon":

			shape = [];

			var j;
		    var minLat = 9999, maxLat = -9999, minLng = 9999, maxLng = -9999;
		    for (j=0; j < matches.length; ++j) {
			    var match_matches = matches[j].match(/(\S+)\s+(\S+)(?:,|$)/);

			    var point = {lat:parseFloat(match_matches[2]), lon:parseFloat(match_matches[1])};

				if(format==0){
			    	shape.push(point);
				}else{
			    	points.push(new google.maps.LatLng(points.lat, points.lon));
				}

			    if (point.lat < minLat) minLat = point.lat;
			    if (point.lat > maxLat) maxLat = point.lat;
			    if (point.lon < minLng) minLng = point.lon;
			    if (point.lon > maxLng) maxLng = point.lon;
		    }

			if(format==0){
				shape = (type=="l" || type=="polyline")?{polyline:shape}:{polygon:shape};
			}else{
		    	southWest = new google.maps.LatLng(minLat, minLng);
		    	northEast = new google.maps.LatLng(maxLat, maxLng);
		    	bounds = new google.maps.LatLngBounds(southWest, northEast);
			}
	}

	}

	return (format==0)?shape:{bounds:bounds, points:points};
}
