var tours;

/**
* Tours - class for
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2012.0831
*/
function Tours(_mainurl, _cachedtours) {

	//private members
	var _className = "Tours",
		_tours = [],
		_error = null;

	//@todo - take these constants to configuration file
	var RT_TOUR = "22",
		RT_TOUR_STOP = "23",
		RT_TOUR_CONNECTION = "25";

	var DT_NAME = "2-1",
		DT_SHORT_SUMMARY = "2-3",
		DT_EXTENDED_DESCRIPTION = "2-4",
		DT_RESOURCE = "2-13",  //assets
		DT_GEO_OBJECT = "2-28",
		DT_THUMBNAIL = "2-39",
		//@todo change to concept ID
		DT_PARENT_TOUR = "57",
		DT_CONN_FIRST_STOP = "58",
		DT_CONN_FIRST_STOP = "58",
		DT_CONN_LAST_STOP = "59",
		DT_CONN_ORDER = "60";


	function _init(){

		_error = null;

		if(!utils.isempty(_cachedtours)){
			try {
				_tours = JSON.parse(_cachedtours);
			} catch (e) {
				_error = "Can't parse list of tours from cache. Open 'Cache' page and reseed it";
			}
			return;
		}

		if(utils.isempty(url_mainlist)){
			_error = "Main request url is not defined in configuration file on server side. Please contact development team";
			return;
		}


		var xmlDoc = utils.getcontent(_mainurl, true);

		if(utils.isnull(xmlDoc)){
			_error = "It is not possible to obtain the list of tours from server side. Please check your network connection and try later. If issue persists, please contact development team";
			return;
		}


		var  records = xmlDoc.getElementsByTagName("records")[0].childNodes;   //getAttribute("lang"); nodeValue
		var i, record, conceptID, recobj, tour_parent;

		_tours = [];

		for (i=0; i<records.length; i++){
			record = records[i];
			if (record.nodeName == "record"){
				if (record.getAttribute("depth")==0) { //tours

					recobj= _parseRecord(record);
                    recobj.has_geo = false;
					recobj.stops = [];
					recobj.connections = [];
					_tours.push(recobj);

				}else if (record.getAttribute("depth")==1) { //stops and connections

					var type = record.getElementsByTagName("type")[0];
					conceptID = type.getAttribute("id"); //@todo change to conceptID

					if(conceptID == RT_TOUR_STOP){

						recobj= _parseRecord(record);
						//find and to parent tour
						tour_parent = _getTourById(recobj.parent_tour);
						//recobj.media = "";
						//recobj.entries = "";
						tour_parent.stops.push(recobj);
                        
                        tour_parent.has_geo = tour_parent.has_geo || !utils.isempty(recobj.geo);

					}else if(conceptID == RT_TOUR_CONNECTION){
						recobj= _parseRecord(record);
						//get stop refernces
						recobj.order = _getDetail(record, DT_CONN_ORDER);
						recobj.stop_id_first = _getDetail(record, DT_CONN_FIRST_STOP);
						recobj.stop_id_last  = _getDetail(record, DT_CONN_LAST_STOP);

						//find and to parent tour
						tour_parent = _getTourById(recobj.parent_tour);
						tour_parent.connections.push(recobj);
					}

				}
			}
		}//for tours


		if(_tours.length==0){
			_error = "None tour obtained from server side. Please contact development team";
			return;
		}


		function ___checkStop(stops, newstop){
			return (newstop && (stops.length==0 || newstop.id!=stops[stops.length-1].id));
		}

		//preparation: sort connections by order, get bbox for tour
		var k, j, stops = [], connection, bounds;
		for (k=0; k<_tours.length; k++){
			if(_tours[k].connections.length>0){

				_tours[k].connections.sort(function(a,b){return a.order - b.order; });
				var stops = [];
                bounds = null;
				//sort points
				for (j=0; j<_tours[k].connections.length; j++){
                    
                    connection = _tours[k].connections[j];
					var s1 = _getStopById(_tours[k], connection.stop_id_first);
					var s2 = _getStopById(_tours[k], connection.stop_id_last);
					if(___checkStop(stops, s1)) { stops.push(s1); }
					if(___checkStop(stops, s2)) { stops.push(s2); }
                    
                    var s = "";
                    if(connection.geo != null){
                        s = connection.geo.substring(11, connection.geo.length-1);
                    }
                    if(!utils.isnull(s1) && !utils.isnull(s1.geo)){
                        s = s1.geo.substring(6, s1.geo.length-1)+((s!="")?',':'')+s;
                    }       
                    if(!utils.isnull(s2) && !utils.isnull(s2.geo) && s!=""){
                        s = s+','+s2.geo.substring(6, s2.geo.length-1);
                    
                        //POINT(151.224167 -33.873542)
                        //LINESTRING(151.2238887 -33.8735601,151.2242531 -33.8734037,151.2248648 -33.8731545,151.2250525 -33.8722128)
                        s = "LINESTRING("+s+")";
                        connection.geo = s;
                        var geom = geom = new OpenLayers.Geometry.fromWKT(s);
                        
                        if(bounds==null){
                            bounds = geom.getBounds();    
                        }else{
                            bounds.extend(geom.getBounds());
                        }
                    }
                    
				}
                
                _tours[k].bounds = bounds;
				_tours[k].stops = stops;
			}
		}

	}

	/**
	*
	*/
	function _parseRecord(record)
	{
		var k, details, detail, record, conceptID;

		var id = record.getElementsByTagName("id")[0].textContent,
			url = record.getElementsByTagName("url"),
			title='',
			description='',
			thumbnail='',
			geo=null,
			parent_tour = null,
			assets = [];

		if(utils.isempty(url)){
			url = url[0].textContent;
		}

		details  = record.getElementsByTagName("detail");

		for (k=0; k<details.length; k++){
			detail  = details[k];
			if(detail.nodeName == "detail"){

				conceptID = detail.getAttribute("conceptID");
				if(conceptID==DT_NAME){
					title = detail.textContent;
				}else if(conceptID==DT_SHORT_SUMMARY && utils.isempty(description) ){
					description = detail.textContent; //nodeValue;
				}else if(conceptID==DT_EXTENDED_DESCRIPTION){
					description = detail.textContent; //nodeValue;
				}else if(conceptID==DT_GEO_OBJECT){

					var geonode = utils.getnode("geo", false, detail);
					if(geonode){
						geo = utils.getnode("wkt", true, geonode);
					}
					//geo = _parseGeo(detail);
				}else if(conceptID==DT_THUMBNAIL){
					thumbnail = _parseFile(detail, true);
				}else if(conceptID==DT_RESOURCE){
					assets.push(detail.textContent);
				}

				conceptID = detail.getAttribute("id"); //@todo to concept ID
				if(conceptID==DT_PARENT_TOUR){
					parent_tour = detail.textContent;
				}

				//@todo - ASSETS

			}
		}

		return {id:id, title:title, description:description, geo:geo, thumbnail:thumbnail, parent_tour:parent_tour, url:url, assets:assets};
	}

	/**
	*
	*/
	function _getDetail(record, dt_id)
	{
		var k, details, detail, record, conceptID;

		details  = record.getElementsByTagName("detail");

		for (k=0; k<details.length; k++){
			detail  = details[k];
			if(detail.nodeName == "detail"){
				conceptID = detail.getAttribute("id"); //@todo to concept ID
				if(conceptID==dt_id){
					return detail.textContent;
				}
			}
		}

		return null;
	}


	/**
	* convert from wkt - not used since openlayers can read wkt directly
	function _parseGeo(detail){

		var geo = detail.getElementsByTagName("geo")[0],
			coords = [];

		if(geo){

			var wkt = geo.getElementsByTagName("wkt")[0].textContent,
				i, xy;

			if(!utils.isempty(wkt)){
				var type = geo.getElementsByTagName("type")[0].textContent;
				if(type == "point"){
					xy = wkt.match(/POINT[(]([^ ]+) ([^ ]+)[)]/);
					if(xy){
						coords.push( [ parseFloat(xy[1]), parseFloat(xy[2]) ] );
					}
				}else if(type == "path"){
					var pointData = wkt.match(/LINESTRING[(](.+)[)]/);
					if(pointData){
						pointData = pointData[1].split(/,/);
						for (i=0; i < pointData.length; ++i) {
							xy = pointData[i].split(/ /);
							coords.push( [ parseFloat(xy[0]), parseFloat(xy[1]) ] );
						}
					}
				}
			}
		}

		return coords;
	}
	*/

	/**
	*
	*/
	function _parseFile(detail, needthumb){

		var file = detail.getElementsByTagName("file")[0];
		if(file){
			if(needthumb){
				return file.getElementsByTagName("thumbURL")[0].textContent;
			}else{
				return file.getElementsByTagName("url")[0].textContent;
			}
		}

		return "";
	}


	/**
	*
	*/
	function _getTourById(_id){
		var k;
		for (k=0; k<_tours.length; k++){
			if(_tours[k].id==_id){
				return _tours[k];
			}
		}
		return null;
	}

	/**
	*
	*/
	function _getStopById(tour, stopid){
		var k;
		for (k=0; k<tour.stops.length; k++){
			if(tour.stops[k].id==stopid){
				return tour.stops[k];
			}
		}
		return null;
	}

	/**
	*
	*/
	function _getStopIndex(tour, stopid){
		var k;
		for (k=0; k<tour.stops.length; k++){
			if(tour.stops[k].id==stopid){
				return k;
			}
		}
		return -1;
	}

    /**
    * put your comment there...
    * 
    * @param tour
    * @param stopid
    * 
    * @returns {Number}
    */
    function _getConnectionByStartStopId(tour, stopid){
        var k;
        for (k=0; k<tour.connections.length; k++){
            if(tour.connections[k].stop_id_first==stopid){
                return tour.connections[k];
            }
        }
        return null;
    }

    
	/**
	*
	*/
	function _getTourHTML(_id, listno, classname){
		var res = "";
		var tour = _getTourById(_id);
		if(!utils.isnull(tour)){

			res = "<div class='pathwayElement "+classname+"'>"+
					"<div class=\"pathwayInformation\" onClick=\"showAllToursOnMap("+_id+")\">"+
					"<div class=\"thumbnail\"><img id=\"thumbid"+_id+"_"+listno+"\" src=\"about:blank\"  width=\"35\" height=\"35\"></img></div>"+
					"<div class=\"pathName\">"+tour.title+" <span class='pathCount'>" + tour.stops.length + " stops " + (tour.has_geo?"":"NOT MAPPED") + "</span></div>";

			//@todo author and distance
			//res = res +	"<div class=\"pathwayInfo\"><i>By: "+tour.author+" - &plusmn;" + totalDistance + "km</i></div>";

			if(!utils.isempty(tour.description)){
				res = res +	"<div id=\"pathDesc"+_id+"\"class=\"hiddenPathwayDescription\">"+tour.description+"</div></div>"+
				"<div class=\"showDescription\" onClick=\"showPathDesc("+_id+")\"><img src=\"info_icon.png\" width=\"35\" height=\"35\"></img></div>";
			}else{
				res = res +	"</div>";
			}
			res = res +	"</div>";

//{id:id, title:title, description:description, geo:geo, thumbnail:thumbnail, parent_tour:parent_tour, url:url};

		}
		return res;
	}

	/**
	*
	*/
	function _getTourList(position, listno){

		var k, res = '';

		for (k=0; k<_tours.length; k++){
            
            var classname = _isOverlap(_tours[k].bounds, position);
            
            res = res  + _getTourHTML(_tours[k].id, listno, classname);    
            
		}
		res = res + "<script>";
		for (k=0; k<_tours.length; k++){
			if( !utils.isempty(_tours[k].thumbnail) ){
				res = res  + "utils.loadImg(\"thumbid"+_tours[k].id+"_"+listno+"\",\""+_tours[k].thumbnail+"\");";
			}
		}
		res = res + "</script>";
		return res;

	}
    
    function _isOverlap(bounds, position){
        
        if(utils.isnull(position)){  //position not defined all nearby
            return 'nearby';
        }else if(utils.isnull(bounds)){
            return 'remote';                
        }else{
            if(typeof bounds.containsLonLat==="undefined"){
                if(bounds.left && bounds.bottom && bounds.right && bounds.top){
                    bounds =  new OpenLayers.Bounds(bounds.left, bounds.bottom, bounds.right, bounds.top);    
                }else{
                    return 'remote';
                }
            }
            
            var ll = new OpenLayers.LonLat(position.coords.longitude, position.coords.latitude);
            if(bounds.containsLonLat(ll)){
                return 'nearby';
            }
            //get distance 
            var center = bounds.getCenterLonLat();
            var distkm = OpenLayers.Util.distVincenty(ll, center);
            return (distkm<2)?'nearby':'remote';
        }
    }

	//
	//public members
	//
	var that = {

				/*doSave: function(){ _doSave(false); },
				doDelete: function(){ _doDelete(true); },
				doAddChild: function(isRoot){ _doAddChild(isRoot); },*/

				getTourById: function(id){
					return _getTourById(id);
				},

				//return HTML list
				getTourList: function(position, listno){
					return _getTourList(position, listno);
				},

				getTours: function(){
					return _tours;
				},

				getStopById: function(tour, stopid){
					return _getStopById(tour, stopid);
				},

				getStopIndex: function(tour, stopid){
					return _getStopIndex(tour, stopid);
				},
                
                getConnectionByStartStopId: function(tour, stopid){
                    return _getConnectionByStartStopId(tour, stopid);
                },

	 			getError: function(){
	 				return _error;
				},

				getClass: function () {
					return _className;
				},

				isA: function (strClass) {
					return (strClass === _className);
				}

	};

	_init();  // initialize before returning
	return that;

}
