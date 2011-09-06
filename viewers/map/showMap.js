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
var Hul = top.HEURIST.util,
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

	var _className = "ShowMap";

	/**
	* Initialization
	*
	* Reads GET parameters and requests for map data from server
	*/
	function _updateMap(context) {

		//converts Heurist.tmap structure to timemap structure
		HEURIST.tmap = context;

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
					shape = {point:{lat: geoobj.geo.y, lon: geoobj.geo.x}};
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
					var url1 = top.HEURIST.basePath;
					url1 += "records/files/downloadFile.php?db=" + top.HEURIST.database.name + "&ulf_ID="+geoobj.fileid;
					kmls.push(url1);

				}else if(geoobj.type === "kml"){
					var url2 = top.HEURIST.basePath;
					url2 += "viewers/map/getKMLfromRecord.php?db=" + top.HEURIST.database.name + "&recID="+geoobj.recid;
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
						if(true){ //Temporal.isValidFormat(record.start)){
							try  {
								var temporal = new Temporal(record.start);

								if(temporal){

								var s = temporal.toString();
								var dt = temporal.getTDate('PDB');
								if(!dt) dt = temporal.getTDate('TPQ');
								if(!dt) dt = temporal.getTDate('DAT');
								record.start = (dt)?dt.toString():"";

								dt = temporal.getTDate('PDE');
								if(!dt) dt = temporal.getTDate('TAQ');
								record.end = (dt)?dt.toString():record.end;

									hastime = (record.start!=="");
									if(hastime){
										rec_withtime++;
									}
								}
							}catch(e){
							}

						}

						if(!isempty || hastime){

							item = {
							start: record.start,
							title: record.title,
							options:{
								description: record.description,
								theme: "purple",
								url: record.URL,
								recid: record.bibID,
								rectype: record.rectype,
								thumb:  record.thumb_file_id
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

		var datasets = [];

		if(items.length>0){
			datasets.push({ type: "basic",	options: { items: items }});
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

		setLayout(((context.cntWithGeo+HEURIST.tmap.layers.length)>0), (rec_withtime>0))

		initMapping(); //from mapping.js
    }


	function _onSelectionChange(eventType, argList) {
		if (parent.document.getElementById("m3").className == "yui-hidden") {
			return false;
		}else {
				if (eventType == "heurist-selectionchange"){
					top.HEURIST.search.mapSelected3();
				}
		}
	}

	var layout, _ismap, _istime;

	function setLayout(ismap, istime){

		if(_ismap===ismap && _istime===istime ||
			(!ismap && !istime) )
		{
			return;
		}
		_ismap=ismap;
		_istime=istime;

		var units;
		if(ismap && istime){
				units = [
	                { position: 'center', body: 'mapcontainer'},
	                { position: 'bottom', header: 'TimeLine', height: 150,
	                	resize: true, body: 'timelinecontainer', gutter: '5px', collapse: true}
	            ];
		}else if(ismap){
				units = [
	                { position: 'center', body: 'mapcontainer'}
	                	];
		}else if(istime){
				units = [
	                { position: 'center', body: 'timelinecontainer'}
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

	    setLayout(true, true);
	    /*
	    var lunit = layout.getUnitById('timelinecontainer');
 		Event.on('timelinecontainer', 'endResize', function() {
	            Alert('!!!!');
	    });
        */

		squery = location.search;
		_reload(squery);

		if (top.HEURIST) {
			top.HEURIST.registerEvent(that,"heurist-selectionchange", _onSelectionChange);
		}
	}
	function _reload(squery) {
				var baseurl = top.HEURIST.basePath + "viewers/map/showMap.php";
				var callback = _updateMap;
				var params = squery;
				top.HEURIST.util.getJsonData(baseurl, callback, squery);
	}


	//public members
	var that = {

			reload:  function (squery){
				_reload(squery);
			},

			baseURL:  function (){
				return top.HEURIST.basePath;
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