/*
* Copyright (C) 2005-2016 University of Sydney
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
* Googlemap loader
*
* @author      Kim Jackson
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Viewers/Map
* @deprecated
*/


var map;
var iconsByrectype = [];
var onclickfn;
var allMarkers = [];
var markerGroups = {};

function loadMap(options) {
	if (! options) options = {};

	top.HEURIST.clusterClickHandlers = [];
	top.HEURIST.dispatchClusterClick = function (index){
		if (typeof top.HEURIST.clusterClickHandlers[index] == "function"){
			top.HEURIST.clusterClickHandlers[index]();
		}
	}

	map = new GMap2(document.getElementById("map"));

	map.enableDoubleClickZoom();
	map.enableContinuousZoom();
	map.enableScrollWheelZoom();

	if (options["compact"]) {
		map.addControl(new GSmallZoomControl());
		map.addControl(new GMapTypeControl(true));
	} else {
		map.addControl(new GLargeMapControl());
		map.addControl(new GMapTypeControl());
	}

	if (options["onclick"])
		onclickfn = options["onclick"];

	var startView = calculateGoodMapView(options["startobjects"]);

	map.setCenter(startView.latlng, startView.zoom);
	map.addMapType(G_PHYSICAL_MAP);
	map.setMapType(G_PHYSICAL_MAP);



	/* add objects to the map */

	var baseIcon = new GIcon();
	baseIcon.image = "../../common/images/questionmark.gif"; // image moved up one level
	baseIcon.shadow = "../../common/images/shadow.png"; // image moved up one level
	baseIcon.iconAnchor = new GPoint(16, 31);
	baseIcon.infoWindowAnchor = new GPoint(16,16);
	baseIcon.iconSize = new GSize(31, 31);
	baseIcon.shadowSize = new GSize(30, 18);


	for (var i in HEURIST.tmap.geoObjects) {
		var geo = HEURIST.tmap.geoObjects[i];
		var record = HEURIST.tmap.records[geo.bibID];

		var highlight = false;
		if (options["highlight"]) {
			for (var h in options["highlight"]) {
				if (geo.bibID ==  options["highlight"][h]) highlight = true;
			}
		}

		if (! iconsByrectype[record.rectype]) {
			iconsByrectype[record.rectype] = new GIcon(baseIcon);
			//iconsByrectype[record.rectype].image = top.HEURIST.iconDir + record.rectype + ".png";
			//iconsByrectype[record.rectype].image = "../../common/images/pointerMapWhite.png";
			iconsByrectype[record.rectype].image = "../../common/images/31x31.gif";
		}

		var marker = null;
		var polygon = null;
        
        //  symbology for polgons etc. on map is set in mapping.js using timemap.js fields
		switch (geo.type) {
			case "point":
				var y = Math.round(geo.geo.y * 1000000)/1000000, x = Math.round(geo.geo.x * 1000000)/1000000;
				if (!markerGroups[y]) {
					markerGroups[y] = {};
				}
				if (!markerGroups[y][x]) {
					markerGroups[y][x] = {};
				}
				if (!markerGroups[y][x][record.rectype]) {
					markerGroups[y][x][[record.rectype]] = { records : [] };
				}
				markerGroups[y][x][record.rectype].records.push(record);
			break;

			case "circle":
			var points = [];
			for (var i=0; i <= 40; ++i) {
				var x = geo.geo.x + geo.geo.radius * Math.cos(i * 2*Math.PI / 40);
				var y = geo.geo.y + geo.geo.radius * Math.sin(i * 2*Math.PI / 40);
				points.push(new GLatLng(y, x));
			}
			if (highlight) {
				polygon = new GPolygon(points, "#ff0000", 1, 0.5, "#ffaaaa", 0.3);
			} else {
				polygon = new GPolygon(points, "#0000ff", 1, 0.5, "#aaaaff", 0.3);
			}

			break;

			case "rect":
			var points = [];
			points.push(new GLatLng(geo.geo.y0, geo.geo.x0));
			points.push(new GLatLng(geo.geo.y0, geo.geo.x1));
			points.push(new GLatLng(geo.geo.y1, geo.geo.x1));
			points.push(new GLatLng(geo.geo.y1, geo.geo.x0));
			points.push(new GLatLng(geo.geo.y0, geo.geo.x0));
			if (highlight) {
				polygon = new GPolygon(points, "#ff0000", 1, 0.5, "#ffaaaa", 0.3);
			} else {
				polygon = new GPolygon(points, "#0000ff", 1, 0.5, "#aaaaff", 0.3);
			}

			break;

			case "polygon":
			var points = [];
			for (var i=0; i < geo.geo.points.length; ++i)
				points.push(new GLatLng(geo.geo.points[i].y, geo.geo.points[i].x));
			points.push(new GLatLng(geo.geo.points[0].y, geo.geo.points[0].x));
			if (highlight) {
				polygon = new GPolygon(points, "#ff0000", 1, 0.5, "#ffaaaa", 0.3);
			} else {
				polygon = new GPolygon(points, "#0000ff", 1, 0.5, "#aaaaff", 0.3);
			}
			break;

			case "path":
			if (highlight) {
				polygon = new GPolyline.fromEncoded({ color: "#ff0000", weight: 3, opacity: 0.8, points: geo.geo.points, zoomFactor: 3, levels: geo.geo.levels, numLevels: 21 });
			} else {
				polygon = new GPolyline.fromEncoded({ color: "#0000ff", weight: 3, opacity: 0.8, points: geo.geo.points, zoomFactor: 3, levels: geo.geo.levels, numLevels: 21 });
			}
			break;
		}

		if (polygon) {
			polygon.record = record;
			if (! record.overlays) record.overlays = [];
			record.overlays.push(polygon);
			map.addOverlay(polygon);
			GEvent.addListener(polygon, "click", polygonClick);
		}

	} // end of for Geo
// run through marker groups to generate base icons
	for (var y in markerGroups){
		for (var x in markerGroups[y]) {
			var marker = getHtmlMarker(y, x, [markerGroups[y][x]], 1, {id:allMarkers.length});
			allMarkers.push(marker);
		}
	}

    //the following line encapsulates the core changes made by Steve White - this modified call allows
    //an additional, optional parameter which is a call back to create the cluster marker.
    //normally one would make the following call:
    //var cluster=new ClusterMarker(map, { markers:allMarkers} );
    //you'd get the standard cluster marker - slightly customisable by basically standard
    //with the additional call you can now do all sorts of data dependent presentation as HTML which is used to build the marker


	var cluster=new ClusterMarker(map, { markers:allMarkers, makeClusterMarker: makeHTMLClusterMarker} );


	cluster.fitMapToMarkers();

}

//this is the callout function for the cluster marker library (modified by Steve White - see internal comments)

function makeHTMLClusterMarker( location, markerIndices, clusterClickHandler) {
	//run through the markerIndices and combine all the RecsByType into an array, then call getHtmlMarker

	// setup a ditspatch table for the clusterMarkers for the click event.
	var handlerIndex = top.HEURIST.clusterClickHandlers.length;
	top.HEURIST.clusterClickHandlers.push(clusterClickHandler);
	var numMarkers = 0;
	var arrayRecsByTypeObjs = [];
	for (var i =0; i < markerIndices.length; i++){
		numMarkers ++;
		arrayRecsByTypeObjs.push(allMarkers[markerIndices[i]].recsByType);
	}
	return getHtmlMarker(location.lat(),location.lng(),arrayRecsByTypeObjs,numMarkers,{clickHandlerIndex : handlerIndex});
}


//this code is fairly specific to the Heurist data model but could easily be addapted to another data model
//it looks for a location, an array of data objects associated with the location, the number of markers represented (anything more than one is assumed to be a cluster),
//and some options for future use - e.g. double click on marker and zoom in to extent that will explode the cluster into single items


function getHtmlMarker(y,x,arrayRecsByTypeObjs,numMarkers, options) {


		var markerLoc = new GLatLng(y, x); // location for this marker
		var numTypes = 0;
		var typeOrder = [];
		var recsByType = {};
		var recIDs = [];
		var recIDsByType = {};

		// combine all record sets with the same type
		// NOTE: these maybe be from different marker record sets as when called from cluster manager
		for (var set = 0; set < arrayRecsByTypeObjs.length; set++) {
			for (var type in arrayRecsByTypeObjs[set]) {
				if (!recsByType[type]){ // add new rec id records
					numTypes++;
					typeOrder.push(type);
					recsByType[type] = { recIdMap : {}, records : [] };
					recIDsByType[type] = [];
				}
				for(var i = 0; i < arrayRecsByTypeObjs[set][type].records.length; i++) {
					var record = arrayRecsByTypeObjs[set][type].records[i];
					if (! recsByType[type].recIdMap[record.bibID]){ // new record
						recsByType[type].recIdMap[record.bibID] = 1;
						recIDsByType[type].push(record.bibID);
						recIDs.push(record.bibID);
						recsByType[type].records.push(record);
					}
				}
			}
		}

		var showRecsURL = top.HEURIST.baseURL + "?w=all&amp;q=ids:" + recIDs.join(",") + "&amp;db=" + top.HEURIST.database.name;

		function compareNumbers(a, b) {
			return a - b
		}
		typeOrder = typeOrder.sort(compareNumbers);
		//get base part html using numMarkers >1 is cluster with numMarkers markers
		var useDefaultHandler = (typeof options.clickHandlerIndex != "undefined"? false :true);
		var indicatorHTML =	(useDefaultHandler ? "<a href=\""+showRecsURL+"\" target=\"_blank\">" :
												"<a href=\"\" onclick=\"top.HEURIST.dispatchClusterClick("+options.clickHandlerIndex+"); return false;\">")+
							"<div class=\"indicator "+
							(numMarkers == 1 ? "single" :
								(numMarkers <3 ? "small" :
									(numMarkers < 7 ? "medium" : "large"))) + "\">" +
							(numMarkers > 1 ? "<div class=\"numMarkers\">" +numMarkers + "</div>": "") +"</div></a>";

		var iconHeight = 30;
		var markerHeight = (numMarkers == 1 ? 16 :
								(numMarkers <3 ? 24 :
									(numMarkers < 7 ? 32 : 40)))+iconHeight;

		function getURLforRecords(Records,recType){
			var url = top.HEURIST.baseURL;
			if (Records.length == 1 && Records[0]['thumb_file_id']){
				//get thumb
				url += "common/php/resize_image.php?db=" + top.HEURIST.database.name + "&amp;file_id=" + Records[0]['thumb_file_id'];
			}else {
				//get recType image
				url += top.HEURIST.iconDir + "map-icons/map_" + recType + ".png";//TODO:  ask Artem if this is a bug. Shouldn't this be site relative?
			}
			return url;
		}

		function getHTMLforIcon(Records,recType){
			var recNum = Records.length;
			var showRecsTypeURL = top.HEURIST.baseURL + "?w=all&amp;q=ids:" + recIDsByType[recType].join(",") + "&amp;db=" + top.HEURIST.database.name;
			var html = "<a href=\""+showRecsTypeURL+"\" target=\"_blank\"><div class=\"icon\" style=\"background-image:url("+getURLforRecords(Records,recType) +")\" onmouseover=\"this.firstChild.style.display='block'\" onmouseout=\"this.firstChild.style.display='none'\">";
			// create info div
			html += "<div class=\"recInfo\" style=\"display:none\">";
			if (recNum > 1) {
				html += "<b>" + recNum + " " + top.HEURIST.rectypes.pluralNames[recType] +"</b><br/>";
			}else{
				html += "<b>" + top.HEURIST.rectypes.names[recType] +"</b><br/>";
			}
			for(var i = 0; i < recNum; i++){
				html += "<p>" + Records[i].title +"</p>";
			}
			html += "</div>";
			// create ref count div
			if (recNum > 1) {
				html += "<div class=\"refCount\"> " + recNum + "</div>";
			}
			html += "</div></a>";
			return html;
		}

		//the following section (plus getHTMLforIcon) is essentially sum total of the visual language one wishes to use
		//this could be modified to be called from a separte location that could be swapped for different visualisations
		var markerClass = "geomarker";
		var markerIcon = new GIcon();
		var markerHTML = "<div class=\"marker\" ";
		switch (numTypes){
			case 1:
				markerHTML += "style=\"width:24px;\">"
				+ getHTMLforIcon(recsByType[typeOrder[0]].records,typeOrder[0])
				+ "</div>"
				+ indicatorHTML;
				markerIcon.iconSize = new GSize(24, markerHeight);
				markerIcon.iconAnchor = new GPoint(12, markerHeight);
				break;
			case 2:
				markerHTML += "style=\"width:49px;\">"
				+ getHTMLforIcon(recsByType[typeOrder[0]].records,typeOrder[0]) + "<div class=\"spacer\"></div>"// insert spacer
				+ getHTMLforIcon(recsByType[typeOrder[1]].records,typeOrder[1])
				+ "</div>"
				+ indicatorHTML;
				markerIcon.iconSize = new GSize(48, markerHeight);
				markerIcon.iconAnchor = new GPoint(24, markerHeight);
				markerClass = "geomarker50";
				break;
			case 3:
				markerHTML += "style=\"width:74px;\">"
				+ getHTMLforIcon(recsByType[typeOrder[0]].records,typeOrder[0]) + "<div class=\"spacer\"></div>"// insert spacer
				+ getHTMLforIcon(recsByType[typeOrder[1]].records,typeOrder[1]) + "<div class=\"spacer\"></div>"// insert spacer
				+ getHTMLforIcon(recsByType[typeOrder[2]].records,typeOrder[2])
				+ "</div>"
				+ indicatorHTML;
				markerIcon.iconSize = new GSize(72, markerHeight);
				markerIcon.iconAnchor = new GPoint(36, markerHeight);
				markerClass = "geomarker76";
				break;
			default: // 13/11/2011 - Artem says this file is no longer used, directory below was incorrectly stated 'map-icons', did not exist
				markerHTML += "style=\"width:24px;\">"
							+ "<div class=\"icon\" style=\"background-image:url("+top.HEURIST.iconDir+ "maps-icons/map_multiRecords.png\")\">"
							+ "<div class=\"refCount\"> " + recIDs.length + "</div></div>"
				+ "</div>"
				+ indicatorHTML;
				markerIcon.iconSize = new GSize(24, markerHeight);
				break;
		}

		//Creates custom label HTML based on number types
		var labelMarker = new ELabel(markerLoc,markerHTML,markerClass,GSize(200,20),100,true);
		labelMarker.recsByType = recsByType;
		labelMarker.setIcon(markerIcon);
		if (options.id) {
			labelMarker.id = options.id;
		}
		if (options.clickHandler) {
			labelMarker.indicatorClick = options.clickHandler;
		}
		return labelMarker
}

function loadXMLDocFromFile(filename)
{
	var xmlDoc=null;
	var errorMsg = "Error loading " + filename + " using XMLHttpRequest";
	var d;
	try {
		d = new XMLHttpRequest();
	} catch (trymicrosoft) {
		try {
			d = new ActiveXObject("Msxml2.XMLHTTP");
		} catch (othermicrosoft) {
			try {
				d = new ActiveXObject("Microsoft.XMLHTTP");
			} catch (failed) {
				d = false;
			}
		}
	}
	if (d) {
		try{
			d.open("GET", filename, false);
			d.send("");
			xmlDoc=d.responseXML;
		}catch(e){
			alert(errorMsg + " Hint : " + e);
		}
	}else{
		alert("Your browser doesn't process XSL. Unable to view content.");
	}
	return xmlDoc;
}


function calculateGoodMapView(geoObjects) {
	var minX, minY, maxX, maxY;
	if (! geoObjects) geoObjects = HEURIST.tmap.geoObjects;
	for (var i=0; i < geoObjects.length; ++i) {
		var geo = geoObjects[i];
		var bbox = (geo.type == "point")? { n: geo.geo.y, s: geo.geo.y, w: geo.geo.x, e: geo.geo.x } : geo.geo.bounds;

		if (i > 0) {
			if (bbox.w < minX) minX = bbox.w;
			if (bbox.e > maxX) maxX = bbox.e;
			if (bbox.s < minY) minY = bbox.s;
			if (bbox.n > maxY) maxY = bbox.n;
		}
		else {
			minX = bbox.w;
			maxX = bbox.e;
			minY = bbox.s;
			maxY = bbox.n;
		}
	}

	var centre = new GLatLng(0.5 * (minY + maxY), 0.5 * (minX + maxX));
	if (maxX-minX < 0.000001  &&  maxY-minY < 0.000001) {       // single point, or very close points
		var sw = new GLatLng(minY - 0.1, minX - 0.1);
		var ne = new GLatLng(maxY + 0.1, maxX + 0.1);
	}
	else {
		var sw = new GLatLng(minY - 0.1*(maxY - minY), minX - 0.1*(maxX - minX));
		var ne = new GLatLng(maxY + 0.1*(maxY - minY), maxX + 0.1*(maxX - minX));
	}
	var zoom = map.getBoundsZoomLevel(new GLatLngBounds(sw, ne));

	return { latlng: centre, zoom: zoom };
}

function markerClick() {
	var record = this.record;

	if (onclickfn) {
		onclickfn(record, this, this.getPoint());
	} else {
		var html = "<b>" + record.title + "&nbsp;&nbsp;&nbsp;</b>";
		if (record.description) html += "<p style='height: 128px; overflow: auto;'>" + record.description + "</p>";
		html += "<p>edit:&nbsp;<a target=_new href='../../records/view/viewRecord.php?recID=" + record.bibID + "'>heurist record #" + record.bibID + "</a></p>";
		if (record.URL) html += "<p>url:&nbsp;&nbsp;&nbsp;<a target=_new href='" + record.URL + "'>" + record.URL + "</a></p>";
		this.openInfoWindowHtml(html);
	}
}

function polygonClick(point) {
	var record = this.record;
	if (onclickfn) {
		onclickfn(record, this, point);
	} else {
		var html = "<b>" + record.title + "&nbsp;&nbsp;&nbsp;</b>";
		if (record.description) html += "<p style='height: 128px; overflow: auto;'>" + record.description + "</p>";
		html += "<p>edit:&nbsp;<a target=_new href='../../records/view/viewRecord.php?recID=" + record.bibID + "'>heurist record #" + record.bibID + "</a></p>";
		if (record.URL) html += "<p>url:&nbsp;&nbsp;&nbsp;<a target=_new href='" + record.URL + "'>" + record.URL + "</a></p>";
		map.openInfoWindowHtml(point, html);
	}
}

var iconNumber = 0;
var legendIcons;
function getIcon(record) {
	if (! window.heurist_useLabelledMapIcons) {
		return iconsByrectype[record.rectype];
	}
	if (! legendIcons) {
		var protoLegendImgs = document.getElementsByTagName("img");
		var legendImgs = [];
		legendIcons = {};

		var mainLegendImg;
		for (var i=0; i < protoLegendImgs.length; ++i) {
			if (protoLegendImgs[i].className === "main-map-icon") {
				mainLegendImg = protoLegendImgs[i];
			}
			else if (protoLegendImgs[i].className === "map-icons") {
				legendImgs.push(protoLegendImgs[i]);
			}
		}

		var baseIcon = new GIcon();
			baseIcon.image = url;
			baseIcon.shadow = "../../common/iamges/maps-icons/circle-shadow.png";
			baseIcon.iconAnchor = new GPoint(10, 10);
			baseIcon.iconWindowAnchor = new GPoint(10, 10);
			baseIcon.iconSize = new GSize(20, 20);
			baseIcon.shadowSize = new GSize(29, 21);

		if (mainLegendImg) {
			var icon = new GIcon(baseIcon);
			icon.image = "../../common/iamges/maps-icons/circleStar.png";
			var bibID = (mainLegendImg.id + "").replace(/icon-/, "");
			legendIcons[bibID] = icon;
			icon.associatedLegendImage = mainLegendImg;

			mainLegendImg.style.backgroundImage = "url(" + icon.image + ")";
		}

		for (i=0; i < legendImgs.length; ++i) {
			var iconLetter;
			if (i < 26) {
				iconLetter = String.fromCharCode(0x41 + i);
			}
			else {
				iconLetter = "Dot";
			}
			var url = "../../common/iamges/maps-icons/circle" + iconLetter + ".png";

			var icon = new GIcon(baseIcon);
			icon.image = url;

			var bibID = (legendImgs[i].id + "").replace(/icon-/, "");

			legendIcons[ bibID ] = icon;
			legendImgs[i].style.backgroundImage = "url(" + url + ")";
		}
	}

	if (legendIcons[ record.bibID ]) {
		if (legendIcons[record.bibID].associatedLegendImage) {
			legendIcons[record.bibID].associatedLegendImage.style.display = "";
		}
		return legendIcons[ record.bibID ];
	}
	else {
		return iconsByrectype[record.rectype];
	}
}

function addPointMarker(latlng, record) {
	var marker = new GMarker(latlng, getIcon(record));
	if (! record.overlays) { record.overlays = []; }
	record.overlays.push(marker);
	map.addOverlay(marker);
}
