/**
* showMap.js
*
* dependent mappping.js
*
* @version 2011.0615
* @author: Artem Osmakov
*
* @copyright (C) 2005-2011 University of Sydney Digital Innovation Unit.
* @link: http://HeuristScholar.org
* @license http://www.gnu.org/licenses/gpl-3.0.txt
* @package Heurist academic knowledge management system
* @todo
**/

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
		_isUseAllRecords = true,
		squery_all,
		squery_sel,
		currentQuery,
		systemAllLayers; //all image layers in the system

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

				datasets.push({theme: "blue", type: "kml",
					options: {
						url: _url // KML file to load
					}
				});
			}//for
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
			url: "http://acl.arts.usyd.edu.au/dos/maps/1854_woolcott-clarke/Layer_NewLayer/",
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

		initMapping(); //from mapping.js
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

		/**/
		for (ind in HEURIST.tmap.geoObjects) {
			if(!Hul.isnull(ind))
			{
				geoobj = HEURIST.tmap.geoObjects[ind];

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
					var url1 = HRST.basePath;
					url1 += "records/files/downloadFile.php?db=" + HRST.database.name + "&ulf_ID="+geoobj.fileid;
					kmls.push(url1);

				}else if(geoobj.type === "kml"){ //kml content is stored as field value
					var url2 = HRST.basePath;
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
										var dt = temporal.getTDate('PDB');
										if(!dt) dt = temporal.getTDate('TPQ');
										if(!dt) dt = temporal.getTDate('DAT');
										record.start = (dt)?dt.toString():"";
										hastime = (record.start!=="");
									}
								}
								if(record.end && record.end.search(/VER=/)){
									temporal = new Temporal(record.end);
									if(temporal){
										var dt = temporal.getTDate('PDE');
										if(!dt) dt = temporal.getTDate('TAQ');
										if(!dt) dt = temporal.getTDate('DAT');
										record.end = (dt)?dt.toString():"";
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
									url: record.url,
									recid: record.recID,
									rectype: record.rectype,
									thumb: record.thumb_url,
									icon: record.icon_url
								}
							};

							if(!Hul.isnull(record.end)){
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


	/** ARTEM - todo
	*
	* 1. convert php result to set of datasets and layers
	*
	*
	*
	*/

	/**
	*  name - name of dataset (main, related 1,2,3)
	*  phpres - result of showMap.php
	*  returns - object of 2 arrays (dataset and layers) and counts of objects with coordinates and timeenabled
	*/
	function getDatasets(name, phpres){

	}

	function removeDatasets(name){

	}

	function removeLayers(name){

	}

	// merge given mapdata with the current RelBrowser.Mapping.mapdata
	function mergeLayers( mapdata ){

	}



/* outdated - to remove
	function _onSelectionChange(eventType, argList) {
		if (parent.document.getElementById("m3").className == "yui-hidden") {
			return false;
		}else {
			if ( (!_isUseAllRecords && eventType == "heurist-selectionchange")
						 || eventType == "heurist-recordset-loaded")
			{
				HRST.search.mapSelected3();
			}
		}
	}
*/

	var layout, _ismap, _istime, _hidetoolbar = false;

	function setLayout(ismap, istime){

		if(_ismap===ismap && _istime===istime )  //|| (!ismap && !istime)
		{
			return;
		}
		_ismap=ismap;
		_istime=istime;

		var toolbar = { position: 'top', body: 'toolbarcontainer', height:(_hidetoolbar?0:25),
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
	}


	/**
	* Initialization
	*
	* Reads GET parameters and requests for map data from server
	*/
	function _init() {


		if(HRST.displayPreferences){
			_isUseAllRecords = (top.HEURIST.displayPreferences["showSelectedOnlyOnMapAndSmarty"]=="all");
			//ian's request document.getElementById('rbMapUseAllRecords').checked = _isUseAllRecords;
			document.getElementById('rbMapUseSelectedRecords').checked = !_isUseAllRecords;
		}else{
			_isUseAllRecords = true;
			//hide toolbar
			_hidetoolbar = true;
		}


		setLayout(true, true);

		/*
		var lunit = layout.getUnitById('timelinecontainer');
		Event.on('timelinecontainer', 'endResize', function() {
		Alert('!!!!');
		});
		*/

		var s1 = location.search;
		if(s1=="" || s1=="?null" || s1=="?noquery"){
			 s1 = null;
			 squery_all = top.HEURIST.currentQuery_all;
			 squery_sel = top.HEURIST.currentQuery_sel;
		}else{
			squery_all = _isUseAllRecords?s1:null;
			squery_sel = _isUseAllRecords?null:s1;
		}
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

			var baseurl = HRST.basePath + "viewers/map/showMap.php";
			var callback = _updateMap;
			var params =  currentQuery;
			if(params!=null){
				HRST.util.getJsonData(baseurl, callback, params);
			}
		}
		if (!currentQuery) {
			document.getElementById("mapreportcontainer").innerHTML = "<div class='wrap'><div id='errorMsg'><span>No Records Selected</span></div></div>";
			setLayout(false, false);
		}

	}

	/**
	* fill list of layers
	*/
	function _updateLayersList(context){

		systemAllLayers = context.layers;
		var elem = document.getElementById('cbLayers'),
			s = "<option value='-1'>none</option>";

		for (ind in systemAllLayers) {
			if(!Hul.isnull(ind)){

				var sTitle = systemAllLayers[ind].title;
				if(Hul.isempty(sTitle)){
					sTitle = context.records[systemAllLayers[ind].rec_ID].title;
				}
				if(Hul.isempty(sTitle)){
					sTitle = 'Undefined title. Rec#'+systemAllLayers[ind].rec_ID;
				}

				s = s + "<option value='" + ind + "'>"+ sTitle +"</option>";
			}
		}

		elem.innerHTML = s;
	}

	/**
	*
	*/
	function _loadLayer(event){

		var val = Number(event.target.value);

		if(isNaN(val) || val < 0){
			RelBrowser.Mapping.addLayers([]);
		}else{
			RelBrowser.Mapping.addLayers([systemAllLayers[val]]);
		}

	}


	/**
	* mode: 0 - both, 1 -image layers, 2 - kml
	*/
	function _load_layers(mode) {

			var baseurl = HRST.basePath + "viewers/map/showMap.php";
			var callback = _updateLayersList;
			var params =  "ver=1&layers="+mode+"&db="+HRST.database.name;
			HRST.util.getJsonData(baseurl, callback, params);
	}


	//
	function _getQuery(){
		return _isUseAllRecords ?squery_all:squery_sel;
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
			return HRST.basePath;
		},

		getQuery: function(){
			return _getQuery();
		},

		setQuery: function(q_all, q_sel){
			if(q_all) squery_all = q_all;
			squery_sel = q_sel;
		},

		isUseAllRecords: function(){
			return _isUseAllRecords;
		},

		isEmpty: function(){
			return !(_ismap || _istime);
		},

		setUseAllRecords: function(val){
			if(val.target){
				val = !val.target.checked; // (val.target.value == "0");
			}
			var isChanged = _isUseAllRecords != val;
			_isUseAllRecords = val;
			if (isChanged) { // && _isUseAllRecords
				_reload();
			}

			if(document.getElementById('rbMapUseSelectedRecords')){
				top.HEURIST.util.setDisplayPreference("showSelectedOnlyOnMapAndSmarty", _isUseAllRecords?"all":"selected");
				//ian's request document.getElementById('rbMapUseAllRecords').checked = _isUseAllRecords;
				document.getElementById('rbMapUseSelectedRecords').checked = !_isUseAllRecords;
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