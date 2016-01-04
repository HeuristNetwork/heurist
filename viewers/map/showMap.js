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
* dependent mappping.js
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Viewers/Map
*/

//aliases
var Hul = HRST.util,
Event = YAHOO.util.Event;

/**
* UserEditor - class for pop-up edit group
*
* public methods
*
* save - sends data to server and closes the pop-up window in case of success
* cancel - checks if changes were made, shows warning and closes the window
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2011.0509
*/

function ShowMap() {

	var _className = "ShowMap",
		_sQueryMode = "all",
		//squery_all,		squery_sel,		squery_main,
		currentQuery,
		_menu = null,
		systemAllLayers; //all image layers (2-11) and kml layers (3-1014) in the system with detail type 3-679 true

	/**
	* Initialization
	*
	* Reads GET parameters and requests for map data from server
	*/
	function _updateMap(context) {

		//converts Heurist.tmap structure to timemap structure
		HEURIST.tmap = context;

		var res = prepareDataset(context);

		var items = res.items,
			kmls = res.kmls,
			rec_withtime = res.rec_withtime;


		var datasets = [];

		if(items.length>0){
			datasets.push({ name: "search result", type: "basic",	options: { items: items }});
		}
		if(kmls.length>0){
			var i;
			for(i=0; i<kmls.length; i++){

				var _url = kmls[i];
				rec_withtime++; //it is assumed that kml is timeenabled

				//theme: "blue",
				datasets.push({ type: "kml",
					options: {
						url: _url // KML file to load
					}
				});
			}//for
		}

		if(context.cntWithGeo>0){
			var elem = document.getElementById('cbLayers');
			if(elem){
				if(HEURIST.tmap.layers.length==0 && elem.selectedIndex>0){
					HEURIST.tmap.layers = [systemAllLayers[elem.value]];
				}else{
					elem.selectedIndex = 0;
				}
			}
		}


		RelBrowser.Mapping.mapdata = {
			//					focus: "<xsl:value-of select="detail[@id=230]/geo/wkt"/>",

			/*timemap: [
			{
			type: "basic",
			options: {
			items: [
			{
			start : "2010-01-01",
			point : {
			lat : 43.7717,
			lon : 11.2536
			},
			title : "Marker Placemark",
			options : {
			description: "Just a plain old marker",
			theme: "purple"
			}
			}
			,{
			start : "2010-04-01",
			imagelayer : {
			type: "virtual earth",
			url: "url for map layer service",
			mime_type:"image/png",
			copyright:"",
			min_zoom:1,
			max_zoom:19
			},
			title : "Syndey 1854",
			options : {
			description: "lalala",
			theme: "orange"
			}
			}
			]
			}
			}
			],*/

			timemap: datasets,

			layers: HEURIST.tmap.layers,

			count_mapobjects: (context.cntWithGeo+HEURIST.tmap.layers.length)

		};

		if(context.cntWithGeo+HEURIST.tmap.layers.length+rec_withtime==0){
			document.getElementById("mapreportcontainer").innerHTML = "<div class='wrap'><div id='errorMsg'><span>No map and time data</span></div></div>"+(HRST.external_publish?"<p align='center'>Note: 	There are no records in this view. The URL will only show records to which the viewer has access. Unless you are logged in to the database, you can only see records which are marked as Public visibility</p>":"");
		}

		setLayout(((context.cntWithGeo+HEURIST.tmap.layers.length)>0), (rec_withtime>0));

		var errors = initMapping("map"); //from mapping.js
		_showErrorSign(errors);

		_showMessage();
	}

	/**
	* context - result from showMap.php search
	*
	* returns array of items (for basic dataset) and array of kml
	*/
	function prepareDataset(context){

		var ind = 0,
		k = 0,
		geoobj,
		record,
		item,
		items = [],
		kmls = [],
		rec_withtime = 0;

		var installDir = window.location.protocol+"//"+window.location.host;

		/**/
		for (ind in HEURIST.tmap.geoObjects) {
			if(!Hul.isnull(ind))
			{
				geoobj = HEURIST.tmap.geoObjects[ind];
                //console.log("geoobj type: " + geoobj.type);

				var shape = null,
				isempty = false;

				if(geoobj.type === "point"){
					shape = { point:{lat: geoobj.geo.y, lon: geoobj.geo.x} };
					//item.point =
				}else if(geoobj.type === "rect"){

					//item.polygon
					shape  = [
					{lat: geoobj.geo.y0, lon: geoobj.geo.x0},
					{lat: geoobj.geo.y0, lon: geoobj.geo.x1},
					{lat: geoobj.geo.y1, lon: geoobj.geo.x1},
					{lat: geoobj.geo.y1, lon: geoobj.geo.x0},
					];

					shape = {polygon:shape};

				}else if(geoobj.type === "polygon"){

					//item.polygon
					shape = [];
					k = 0;
					for (k in geoobj.geo.points) {
						if(!Hul.isnull(k)){
							shape.push({lat: geoobj.geo.points[k].y, lon: geoobj.geo.points[k].x});
						}
					}

					shape = {polygon:shape};

				}else if(geoobj.type === "polyline"){

					//item.polyline
					shape = [];
					k = 0;
					for (k in geoobj.geo.points) {
						if(!Hul.isnull(k)){
							shape.push({lat: geoobj.geo.points[k].y, lon: geoobj.geo.points[k].x});
						}
					}

					shape = {polyline:shape};

				}else if(geoobj.type === "circle"){

					shape = [];
					for (var i=0; i <= 40; ++i) {
						var x = geoobj.geo.x + geoobj.geo.radius * Math.cos(i * 2*Math.PI / 40);
						var y = geoobj.geo.y + geoobj.geo.radius * Math.sin(i * 2*Math.PI / 40);
						shape.push({lat: y, lon: x});
					}

					shape = {polygon:shape};
				}else if(geoobj.type === "kmlfile"){
					var url1 = HRST.baseURL_V3;
					url1 += "records/files/downloadFile.php?db=" + HRST.database.name + "&ulf_ID="+geoobj.fileid;
					kmls.push(url1);

				}else if(geoobj.type === "kml"){ //kml content is stored as field value
					var url2 = HRST.baseURL_V3;
					url2 += "viewers/map/getKMLfromRecord.php?db=" + HRST.database.name + "&recID="+geoobj.recid;
					kmls.push(url2);
				}else if(geoobj.type === "none"){
					isempty = true;
				}

				if(shape || isempty){
					//
					//
					//
					record = HEURIST.tmap.records[geoobj.bibID];

					if(!Hul.isnull(record.geoindex)){ //already exists

						item = items[record.geoindex];

					}else{

						var hastime = false;

						// parse temporal object
						if(record.start){ //Temporal.isValidFormat(record.start)){
							try{
								var temporal;
								if(record.start && record.start.search(/VER=/)){
									temporal = new Temporal(record.start);
									if(temporal){
										var dt = temporal.getTDate('PDB');  //probable begin
										if(!dt) dt = temporal.getTDate('TPQ');
										if(!dt) dt = temporal.getTDate('DAT');
										record.start = (dt)?dt.toString("yyyy-MM-ddTHH:mm:ssz"):"";
										hastime = (record.start!=="");
									}
								}
								if(record.end && record.end.search(/VER=/)){
									temporal = new Temporal(record.end);
									if(temporal){
										var dt = temporal.getTDate('PDE'); //probable end
										if(!dt) dt = temporal.getTDate('TAQ');
										if(!dt) dt = temporal.getTDate('DAT');
										record.end = (dt)?dt.toString("yyyy-MM-ddTHH:mm:ssz"):"";
										hastime = (hastime ||(record.end!==""));
									}
								}
								if(hastime){
									rec_withtime++;
								}
							}catch(e){
							}
						}

						if(!isempty || hastime){
							item = {
								start: record.start,
								title: record.title,
								options:{
									//theme: "purple",
									description: record.description,
									url: (record.url ? "'"+record.url+"' target='_blank'"  :"'javascript:void(0);'"), //for timemap popup
									link: record.url,  //for timeline popup
									recid: record.recID,
									rectype: record.rectype,
									thumb: record.thumb_url,
									icon: record.icon_url,  //installDir+
									start: (record.start || ''),
									end: ((Hul.isnull(record.end) || record.end==record.start)?'':record.end)
								}
							};

							if(!Hul.isnull(record.end) && record.end!==record.start){
								item.end = record.end;
							}

							item.placemarks = [];
							record.geoindex = items.length;
							items.push(item);
						}
					}

					if(!isempty){
						item.placemarks.push(shape);
					}

				}
			}
		}

		return {items: items, kmls:kmls, rec_withtime:rec_withtime};
	}

	var layout, _ismap, _istime, _hidetoolbar = false;

	function setLayout(ismap, istime){

		if(_ismap===ismap && _istime===istime )  //|| (!ismap && !istime)
		{
			return;
		}
		_ismap=ismap;
		_istime=istime;

		var toolbar = { position: 'top', body: 'toolbarcontainer', height:(_hidetoolbar?0:30),
						visible: _hidetoolbar,
						resize:false, collapse:false};

		var units;
		if(ismap && istime){
			units = [
			toolbar,
			{ position: 'center', body: 'mapcontainer'},
			{ position: 'bottom', header: 'TimeLine', height: 150,
				resize: true, body: 'timelinecontainer', gutter: '5px', collapse: true}
			];
		} else if(ismap){
			units = [
			toolbar,
			{ position: 'center', body: 'mapcontainer'}
			];
		} else if(istime){
			units = [
			toolbar,
			{ position: 'center', body: 'timelinecontainer'}
			];
		} else {
			units = [
				toolbar,
				{ position: 'center', body: 'mapreportcontainer'}
			 ];
		}

		layout = null;
		layout = new YAHOO.widget.Layout({
			units: units
		});
		layout.render();

		layout.on('resize', RelBrowser.Mapping.onWinResize);
	}


	/**
	* Initialization
	*
	* Reads GET parameters and requests for map data from server
	*/
	function _init() {


		if(HRST.displayPreferences && document.getElementById('rbMapUseSelectedRecords')){
			_sQueryMode = HRST.displayPreferences["showSelectedOnlyOnMapAndSmarty"];
			document.getElementById('rbMapUseSelectedRecords').value = _sQueryMode;
		}else{
			_sQueryMode = "main";
			//hide toolbar
			_hidetoolbar = true;

			HRST.currentQuery_main = location.search;
		}

		if(YAHOO.widget.Menu){ //there is not menu for showMapS.html (publish map)

			if(!_menu){     //search-by-tag-link
				_menu = new YAHOO.widget.Menu("menu_map_publish");
			}

			function _onMenuClick(eventName, eventArgs, subscriptionArg){

				var dest = subscriptionArg[0];
				if(dest=="code"){
					Hul.popupURL(this,'../viewers/map/mapMenu.html',null);
				}else{
					var mode = HRST.displayPreferences["showSelectedOnlyOnMapAndSmarty"];
	 				var currentSearchQuery =
	 					(mode=="selected")
							?HRST.currentQuery_sel 
							    :((mode=="all")?HRST.currentQuery_all:HRST.currentQuery_main);

					if(currentSearchQuery)
					{
						if(dest=="earth"){
							url = HRST.baseURL_V3+"export/xml/kml.php?" + currentSearchQuery;
						}else{
							url = HRST.baseURL_V3+"viewers/map/showMapS.html?" + currentSearchQuery;
						}
						window.open(url, '_blank');
					}
				}
			}

			_menu.addItems([
						{ text: 'Google Map/Timeline', onclick:{ fn: _onMenuClick, obj: ["map"] } },
						{ text: 'Google Earth', onclick:{ fn: _onMenuClick, obj: ["earth"] } },
						{ text: 'Embed Map Code', onclick:{ fn: _onMenuClick, obj: ["code"] } }]);
			_menu.render(this.document.body);
			$("#menu_map_publish").bind("mouseleave",function(){
				_menu.hide();
			});
		}

		setLayout(true, true);

		/*
		var lunit = layout.getUnitById('timelinecontainer');
		Event.on('timelinecontainer', 'endResize', function() {
		Alert('!!!!');
		});
		*/

		/*var s1 = location.search;
		if(s1=="" || s1=="?null" || s1=="?noquery"){
			 s1 = null;
			 squery_all = HRST.currentQuery_all;
			 squery_sel = HRST.currentQuery_sel;
			 squery_main = HRST.currentQuery_main;
		}else{
			squery_all = _sQueryMode=="all"?s1:null;
			squery_sel = _sQueryMode=="selected"?s1:null;
			squery_main = _sQueryMode=="main"?s1:null;
		}*/
		_reload();

		if(HRST.displayPreferences){
			_load_layers(0);
		}

/* outdated - toremove
		if (HRST.search) {
			HRST.registerEvent(that,"heurist-selectionchange", _onSelectionChange);
			HRST.registerEvent(that,"heurist-recordset-loaded", _onSelectionChange);
		}
*/
	}

	function _reload() {

		var newQuery = _getQuery();
		if(currentQuery!=newQuery){ //newQuery!=null &&
			currentQuery = newQuery;

			var baseurl = HRST.baseURL_V3 + "viewers/map/showMap.php";
			var callback = _updateMap;
			var params =  currentQuery;
			if(params!=null){
				Hul.getJsonData(baseurl, callback, params);
			}
		}
		if (!currentQuery) {
			document.getElementById("mapreportcontainer").innerHTML = "<div class='wrap'><div id='errorMsg'><span>No Records Selected</span></div></div>";
			setLayout(false, false);
		}

	}

	var currentBackgroundLayer = "";

	/**
	* fill list of layers   - call for _load_layers
	*/
	function _updateLayersList(context){

        var elem = document.getElementById('cbLayers');
       
        if(elem){    //showMapS.html does not have such feature

		var ind,
			s = "<option value='-1'>none</option>",
			keptLayer = HRST.util.getDisplayPreference("mapbackground"); //read record id

		systemAllLayers = context.layers;

		//add kml as well
		for (ind in context.geoObjects) {
			if(!Hul.isnull(ind))
			{
				geoobj = context.geoObjects[ind];
				if(geoobj.type === "kmlfile"){

					var url = HRST.baseURL_V3;
					url += "records/files/downloadFile.php?db=" + HRST.database.name + "&ulf_ID="+geoobj.fileid;
					geoobj.url = url;

					systemAllLayers.push(geoobj);
				}
			}
		}

		for (ind in systemAllLayers) {
			if(!Hul.isnull(ind)){

				if(Hul.isnull(systemAllLayers[ind].isbackground) || systemAllLayers[ind].isbackground)
				{

					var sTitle = systemAllLayers[ind].title;
					if(Hul.isempty(sTitle)){
						sTitle = context.records[systemAllLayers[ind].rec_ID].title;
					}
					if(Hul.isempty(sTitle)){
						sTitle = 'Undefined title. Rec#'+systemAllLayers[ind].rec_ID;
					}

					var sSel = '';
					if(keptLayer==systemAllLayers[ind].rec_ID){
						sSel = ' selected="selected"';
					}

					s = s + "<option value='" + ind + "' "+sSel+">"+ sTitle +"</option>";

				}
			}
		}

		elem.innerHTML = s;

		if(currentBackgroundLayer!=keptLayer){
			currentBackgroundLayer = keptLayer;
			 _loadLayer(elem);
		}
        
        }
	}

	/**
	* loads background image layer - listener of selector
	*/
	function _loadLayer(obj){

		var val = Number((obj.target) ?obj.target.value:obj.value),
			errors='';

		if(isNaN(val) || val < 0){
			RelBrowser.Mapping.addLayers([], 0);
			if(!Hul.isempty(currentBackgroundLayer)){
				if(HRST.currentQuery_all) HRST.currentQuery_all = HRST.currentQuery_all.replace(","+currentBackgroundLayer,"");
				if(HRST.currentQuery_sel) HRST.currentQuery_sel = HRST.currentQuery_sel.replace(","+currentBackgroundLayer,"");
				if(HRST.currentQuery_main) HRST.currentQuery_main = HRST.currentQuery_main.replace(","+currentBackgroundLayer,"");
			}
			currentBackgroundLayer = '';
		}else{
			errors = RelBrowser.Mapping.addLayers([systemAllLayers[val]], 1); //and zoom to these layers
			//that's required that map panel will be visible in case there are no more other map objects
			if(Hul.isempty(errors)){
				currentBackgroundLayer = systemAllLayers[val].rec_ID;
				if(HRST.currentQuery_all) HRS.currentQuery_all = HRST.currentQuery_all + "," + currentBackgroundLayer;
				if(HRST.currentQuery_sel) HRST.currentQuery_sel = HRST.currentQuery_sel + "," + currentBackgroundLayer;
				if(HRST.currentQuery_main) HRST.currentQuery_main = HRST.currentQuery_main + "," + currentBackgroundLayer;
			}else{
				currentBackgroundLayer = '';
			}
		}
		_showErrorSign(errors);

		if(HRST.displayPreferences){
			HRST.util.setDisplayPreference("mapbackground", currentBackgroundLayer); //save record id of background layer
		}
	}

	/**
	* show/hide error sign with report alert - what's wrong with image layers
	*/
	function _showErrorSign(errors){

		var elem = document.getElementById('errorSign');
		if(elem){
			if(Hul.isempty(errors)){
				elem.innerHTML = '';
			}else{
				elem.innerHTML = '<a href="#" style="color:#ff0000;" onclick="{alert(\''+errors+'\')}">!!!</a>';
			}
		}
	}

	/**
	* show/hide error sign with report alert - what's wrong with image layers
	*/
	function _showMessage(){

		var elem = document.getElementById('messageSign');
		if(elem){
			var msg = "";
			var mapobjects = HEURIST.tmap.cntWithGeo+HEURIST.tmap.layers.length;

			if(mapobjects>0){

				var limit = parseInt(HRST.util.getDisplayPreference("report-output-limit"));
				if (isNaN(limit)) limit = 1000; //def value for dispPreference



				if( (_sQueryMode=="all" && HRST.currentQuery_all_waslimited) ||
					(_sQueryMode=="selected" && HRST.currentQuery_sel_waslimited) ||
					(_sQueryMode=="main" && limit<HRST.totalQueryResultRecordCount))
				{
					msg = "&nbsp;&nbsp;<font color='red'>(result set limited to "+limit+")</font>";
				}

				if(mapobjects>1){
					msg = mapobjects+' objects'+msg;
				}
			}

			elem.innerHTML = msg;
		}
	}


	/**
	* Load list of image layer on init
	* mode: 0 - both, 1 -image layers, 2 - kml
	*/
	function _load_layers(mode) {
            //return; //ARTEM

			var baseurl = HRST.baseURL_V3 + "viewers/map/showMap.php";
			var callback = _updateLayersList;
			var params =  "ver=1&limit=25&layers="+mode+"&db="+HRST.database.name;
			Hul.getJsonData(baseurl, callback, params);
	}


	//
	function _getQuery(){
		if(_sQueryMode=="all"){
			return HRST.currentQuery_all;
		}else if(_sQueryMode=="selected"){
			return HRST.currentQuery_sel;
		}else {
			return HRST.currentQuery_main;
		}
	}

	// NOT USED relRecords - array of queries
	function _updateRelatedRecords(relRecords){

		for(var i=0; i<3; i++){
				//relRecords[i];
		}

	}


	//public members
	var that = {

		processMap:  function (){
			_reload();
		},

		baseURL:  function (){
			return HRST.baseURL_V3;
		},

		getQuery: function(){
			return _getQuery();
		},

		/*setQuery: function(q_all, q_sel, q_main){
			if(q_all) squery_all = q_all;
			squery_sel = q_sel;
			squery_main = q_main;
		},*/

		getQueryMode: function(){
			return _sQueryMode;
		},

		isEmpty: function(){
			return !(_ismap || _istime);
		},

		setQueryMode: function(val){
			if(val.target){
				val = val.target.value; // (val.target.value == "0");
			}
			var isChanged = _sQueryMode != val;
			_sQueryMode = val;
			if (isChanged) {
				_reload();
			}

			if(document.getElementById('rbMapUseSelectedRecords')){
				HRST.util.setDisplayPreference("showSelectedOnlyOnMapAndSmarty", _sQueryMode);
				document.getElementById('rbMapUseSelectedRecords').value = _sQueryMode;
			}
		},

		//load image layer
		loadLayer: function(event){
			return _loadLayer(event);
		},

		updateRelatedRecords: function(relRecords){
			_updateRelatedRecords(relRecords);
		},

		//to fix issue with gmap after tab switching
		checkResize: function(){
			RelBrowser.Mapping.checkResize();
		},

		showMenu: function(){
			if(_menu){
				_menu.cfg.setProperty("context",
					["menuButton", "tl", "bl"]);
				_menu.show();
			}
		},
		hideMenu: function(){
			if(_menu){
				_menu.hide();
			}
		},

		getClass: function () {
			return _className;
		},

		isA: function (strClass) {
			return (strClass === _className);
		}

	};

	_init();
	return that;
}