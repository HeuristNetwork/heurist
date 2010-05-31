var rBrowser = {};   //container to hold general purpose data for this relationship browser

function Filter(paramArray) {

	/**********private***************/
		var _className = "Filter";
		var _startDate = null;
		var _endDate = null;
		var _recTypeList = [];
		var _recTypeExcludeList = [];
		var _relTypeList = [];
		var _relTypeExcludeList =[];


	/**********public***************/

		var that = {
			setStartDate: function(startDate) { _startDate = startDate;},
			setEndDate: function(endDate) { _endDate = endDate;},
			includeRecType: function(recTypeID) { _recTypeList.push(recTypeID);},
			excludeRecType: function(recTypeID) { _recTypeExcludeList.push(recTypeID);},
			includeRelType: function(relTypeID) { _relTypeList.push(relTypeID);},
			excludeRelType: function(relTypeID) { _relTypeExcludeList.push(relTypeID);},
			isInTimeRange: function(start,end) {
				if (!_startDate || !_endDate) return true; // no time filtering
				if (!start) start = _startDate;
				if (!end) end = _endDate;
				if ((start >= _startDate && start <= _endDate) || (end >= _startDate && end <= _endDate) || (start<_startDate && end > _endDate)) return true;
				return false;
			},
			isRelTypeIncluded: function(relTypeID) {
				if (_relTypeList.length == 0 && _relTypeExcludeList.length == 0) return true;
				if (_relTypeList.length > 0){
					if (relTypeID in (join(_relTypeList,",")))return true;
					else return false;
				}else{
					if (relTypeID in (join(_relTypeExludeList,",")))return true;
					else return false;
				}
			},
			isRecTypeIncluded: function(recTypeID) {
				if (_recTypeList.length == 0 && _recTypeExcludeList.length == 0) return true;
				if (_recTypeList.length > 0){
					if (recTypeID in (join(_recTypeList,",")))return true;
					else return false;
				}else{
					if (recTypeID in (join(_recTypeExludeList,",")))return true;
					else return false;
				}
			},
			getClass: function() { return _className;},
			isA: function(strClass){ if(strClass === _className) return true; return false;}
		};
		return that;
}
rBrowser.workflowIndex = 0;
function calcZOrder (marker,b) {
	if (app.displayManager.getFocus().getTitle() == marker.getTitle()) {
		return 20;
	}
	return  rBrowser.workflowIndex + (marker.offsetIndex?marker.offsetIndex:0);
}

function RecordMarker(record,manager) {
       if (HAPI.isA(record, "HRelationship")) return null;  //FIXME  we need to through an exception here. We want to keep record markers separate from relation markers.

     /**********private***************/
        var _className = "RecordMarker";
        var _hRecord = record;
        var _gMarker =  _createMarker(rBrowser.baseIcon, _hRecord.locations && _hRecord.locations[0] ?_hRecord.locations[0] : temp = new GLatLng(app.defaults.floatingCenter ? app.defaults.floatingCenter[0] : app.map.getCenter().lat(),app.defaults.floatingCenter ? app.defaults.floatingCenter[1] : app.map.getCenter().lng()));
        var _filtered = false;                 // indicates whether the current state is filtered  0 = not filtered
        var _added = false;                // indicates whether the gMarker has been added to the display pallet (an Overlay in GMap2)
        var _manager = manager;            // link to the display manager for this marker, provides access to the map
        var _relMarkers = [];              //relationship markers
        var _depthLevel = 0;               // marker the depth in the relationship tree, used for loop detection when filtering

        function _synchRelations() {
            var len = _relMarkers.length;
            if (len){
                for (var i = 0; i<len; i++){
                    _relMarkers[i].synchPoints();
                }
            }
        }



        function _createMarker(baseIcon, loc){
                var orgType = _hRecord.getDetails(app.orgTypeField);
                var recIcon = new GIcon(baseIcon);
                if (orgType  &&  orgType[0]) {
                    recIcon.image = app.getOrgTypeIconPath(orgType[0], true);
                }else{
                    recIcon.image = "http://heuristscholar.org/relmap-sw/img/reftype/png/" + _hRecord.getRecordType().getID() + ".png";
                }
                return new GMarker(loc,     // if the record doesn't have a location give the marker a temporary
                                   { icon: recIcon ,
                                     title: _hRecord.getTitle(),
                                     zIndexProcess:calcZOrder });
        }

        function _sendHide() {
            var len = _relMarkers.length;
            if (len){
                for (var i = 0; i<len; i++){
                    _relMarkers[i].hide(that);         // pass in this marker so the relation knows which object is telling it to hide
                }
            }
        }

        function _sendShow() {
            var len = _relMarkers.length;
            if (len){
                for (var i = 0; i<len; i++){
                    _relMarkers[i].show(that);
                }
            }
        }

     /**********public***************/

        var that = {
            setZIndexOffset: function(z) {_gMarker.offsetIndex = z},
            getRecord: function() { return _hRecord},
            getID: function() { return _hRecord.getID();},
            getType: function() { return _hRecord.getRecordType().getID();},
            getImage: function() { return _gMarker.getIcon().image;},
            setImage: function(url) { _gMarker.setImage(url);},
            getDepth: function() { return _depthLevel;},
            setDepth: function(level) { _depthLevel = parseInt(level); },
            filter: function(turnOnFilter) { _filtered = turnOnFilter;},
            getLocation: function(){ return _hRecord.locations? _hRecord.locations[0] : null;},
            setDisplayLocation: function(location){
                                     if (location){
                                            var loc = _gMarker.getLatLng();
                                            if (loc.lat() == location.lat() && loc.lng() == location.lng()) return;   //same location we are done
                                     }else{
                                            if (_hRecord.locations){
                                                location =_hRecord.locations[0];
                                             }else{
                                               // assert("Unable to set record -"+ _hRecord.getTitle() + "-to null location");
                                                return;
                                             }
                                     }
                                     _gMarker.setLatLng(location);
                                     if (that.affinityGroupMarker)  that.affinityGroupMarker.onAffinityMarkerMove();
                                     _synchRelations();
            },
            getDisplayLocation: function(){ return _gMarker.getLatLng();},
            hasMarker: function() {return (_gMarker != null);},
            onClickHandler: function() {
                            // if this marker is the affinity marker for a group bind to the group handler.
                            if (that.affinityGroupMarker){
                                that.affinityGroupMarker.onClickHandler();
                            }else{
                                // Callback for GMarker: used with GEvent.bind such that (this) is an HRecord
                                app.chooseRecord(this);
                            }
                },
            isAdded: function() { return _added;},
            hide: function(){
                                if (_gMarker != null && _added){
                                    if (that.clusterGroupMarker) that.clusterGroupMarker.markerVisibilityUpdate(that, false);
                                    if (_gMarker.isHidden()) return;  // stop looping messages
                                    _gMarker.hide();
//                                    if(!_gMarker.isHidden()){
//                                        _manager.map.removeOverlay(_gMarker);
//                                        _added = false;
//                                    }
                                }
//                                _sendHide();
            },
            show: function(){
                                if (_gMarker == null){
                                    if (! _createMarker())return;
                                }
                                if (_filtered) return;
                                if (!_gMarker.isHidden()) return;     // stop looping messages
                                if (!_added){
                                    _manager.map.addOverlay(_gMarker);
                                    _added = true;
                                }
                                _gMarker.show();
                                if (that.clusterGroupMarker) that.clusterGroupMarker.markerVisibilityUpdate(that, true);
                                _sendShow();
            },
            isHidden: function() {
                                if (!_added) return true;
                                return _gMarker.isHidden();
            },
            addRelation: function(relMarker) {
                _relMarkers.push(relMarker);
            },
            applyFilter: function(filter) {
                        // apply filter to all relations
                        var len = _relMarkers.length,
                            i;
                        if (len){
                            for(i=0; i<len; i++){
                                _relMarkers[i].applyFilter(filter);
                            }
                        }
             },
            prepForDelete: function() {
                        //clear the marker from the map
                        _manager.map.removeOverlay(_gMarker);
                        delete _gMarker;
                        //unhook this marker from the record
                        if (_hRecord.marker) {
                            _hRecord.marker = undefined;
                        }
                        if (this.affinityGroupMarker){
                            this.affinityGroupMarker.prepForDelete();
                            delete this.affinityGroupMarker;
                            this.affinityGroupMarker = undefined;
                        }
                        if (this.affinityGroup){
                            this.affinityGroup = undefined;
                        }
                        _relMarkers = [];
            },
            getClass: function() { return _className;},
            isA: function(strClass){ if(strClass === _className) return true; return false;}
        };

        // bind the click event of the marker to choose the record
        GEvent.bind(_gMarker, "click", _hRecord, that.onClickHandler);
        // add a link to the record back to this marker.
        _hRecord.marker = that;
        return that;
}

function RelationMarker(record, manager) {
      if (!HAPI.isA(record, "HRelationship")) {
        //assert(" Must have a relationship record to create a RelationMarker");
        return null;
      }
      if (!record.getPrimaryRecord().getRecord().marker || !record.getSecondaryRecord().getRecord().marker){
        //assert("Both records of a relationship must have markerObjects created before creating the relationMarker.");
        return null;
      }

     /**********private***************/
		var _className = "RelationMarker";
		var _hRelRec = record;
		var _attached = 2;                // denotes which marker is attached (0 - none, 1-primary, 2-secondary:default, 3-both)
		var _displayColor = "#0000CC";
		var _lineWidth = 3;
		var _opacity = 0.5;
		var _manager = manager;            // a link to the display manager for this marker
		var _added = false;                // indicates whether the gPolyline has been added to the display pallet (an Overlay in GMap2)
		var _filtered = false;             // indicates whether the current state is filtered
		var _primRecord =  _hRelRec.getPrimaryRecord().getRecord();
		var _secRecord =  _hRelRec.getSecondaryRecord().getRecord();
		var _primRecMarker = _primRecord ? _primRecord.marker : null;
		var _secRecMarker = _secRecord.marker ? _secRecord.marker : null;
		var _start = function () {
			var temp = _hRelRec.getDetail(app.startDateFieldID);
			if (!temp) {
				temp = app.timeBarRange[0];
			}
			rsMatch= temp.match(/(\d+)/g);  // get the start date of the relationship
			return parseInt(rsMatch[0]) + app.getFractionalYear(rsMatch[1],rsMatch[2])/100;
		}();
		var _end = function () {
			var temp = _hRelRec.getDetail(app.endDateFieldID);
			if (!temp) {
				temp = app.timeBarRange[1];
			}
			reMatch= temp.match(/(\d+)/g);  // get the end date of the relationship
			return parseInt(reMatch[0]) + app.getFractionalYear(reMatch[1],reMatch[2])/100;
		}();

		var _gPolyline = null;

		function _getStyle() {
			return {
				"color" : _displayColor,
				"opacity" : _opacity,
				"width" : _lineWidth
			};
		}

		function _createLine() {  //TODO
			var vertices = _getVertices();
			if ((_primRecMarker.affinityGroupMarker &&
				_secRecMarker.affinityGroup) || (_primRecMarker.affinityGroup &&
				_secRecMarker.affinityGroupMarker)) {
					_displayColor = "#FF0000";
					_lineWidth = 3;
					_opacity = 0.6;
				}
			_gPolyline = new GPolyline(vertices, _displayColor, _lineWidth, _opacity);
			return _gPolyline;
		}

		function _getVertices() {
			// run through the primary and secondary collecting vertices
			if (!_primRecMarker || !_secRecMarker) return null;
			var vertices = [];
			var dLoc = _primRecMarker.getDisplayLocation();
			var loc = _primRecMarker.getLocation();
			vertices.push(dLoc);
			if (loc && !(dLoc.lat()==loc.lat()  && dLoc.lng() == loc.lng())) { //different location
			  if (!(_primRecMarker.affinityGroupMarker && _secRecMarker.affinityGroup)) vertices.push(loc);
			}
			dLoc = _secRecMarker.getDisplayLocation();
			loc = _secRecMarker.getLocation();
			if (loc && !(dLoc.lat()==loc.lat()  && dLoc.lng() == loc.lng())) { //different location
			  if (!(_primRecMarker.affinityGroup && _secRecMarker.affinityGroupMarker)) vertices.push(loc);
			}
			vertices.push(dLoc);
			return vertices;
		}

		function _setLineSytle() {
			// apply current sytle to _gPolyline
			if (_gPolyline == null) return;
			_gPolyline.setStrokeStyle(_getStyle());
		}

	/**********public***************/

		var that = {
			getRelationRecord: function() { return _hRelRec},
			getType: function() { return _hRelRec.getRecordType().getID();},    //this should always be 52
			getRelationType: function() { return _hRelRec.getType();},
			setColor: function(color) {
				_displayColor = color;
				_setLineStyle();
			},
			setOpacity: function(opacity) {
				_opacity = opacity;
				_setLineStyle();
			},
			setLineWidth: function(width) {
				_lineWidth = width;
				_setLineStyle();
			},

			getPrimaryRecord: function() { return _primRecord;},
			getSecondaryRecord: function() { return _secRecord;},
			getPrimaryMarker: function() { return _primRecMarker;},
			getSecondaryMarker: function() { return _secRecMarker;},
			setAttachment : function(mode) { _attached = parseInt(mode);},
			getLocation: function(){ return null;},  //relations don't have a location for now. Perhaps in n-space.
			synchPoints: function() {
			// reset the vertices of the polyline to match the primary and secondary. If a marker has a location
			// different from the display location is has some sort of grouping and should adjust the vertices to reflect
			// the positions  (max of 4 vertices  PrimDisplay - PrimLoc - SecondLoc - SecondDisplay) (case of 2 clusters with related records)

				 var newVertices = _getVertices(),
					origHidden = _gPolyline.isHidden();
					if (_added) _manager.map.removeOverlay(_gPolyline);
					delete _gPolyline;
					_gPolyline = new GPolyline(newVertices, _displayColor, _lineWidth, _opacity);
					if (_added) _manager.map.addOverlay(_gPolyline);
					if (origHidden && !_gPolyline.isHidden()){
						_gPolyline.hide();
					}

/*						len = newVertices.length,
							i,
							oldVertCount = _gPolyline.getVertexCount();
						_gPolyline.hide();

						if (oldVertCount > len) {
							for (i= oldVertCount; i>len; i--){
									_gPolyline.deleteVertex(i);
							}
						}
						for (i=0; i<len; i++){
							_gPolyline.insertVertex(i,newVertices[i]);
						}
*/
			},

			hasMarker: function() {return (_gPolyline != null);},
			isAdded: function() { return _added;},
			hide: function(sendingMarker,changeFiltering){	// the sending marker is designed to allow direction messaging through the relation network
				if (_gPolyline == null) return;
				if (_gPolyline.supportsHide()){
					if(_gPolyline.isHidden()) return;	// This is here to terminate looping hide messages
					_gPolyline.hide();
				} else {
					if(!_added) return;					// This is here to terminate looping hide messages
					_manager.map.removeOverlay(_gPolyline);
					_added = false;
				}
				if (sendingMarker == _primRecMarker) {
					if (changeFiltering) _secRecMarker.filter(true);
					_secRecMarker.hide();
				}else{
					if (sendingMarker == _secRecMarker) {
					if (changeFiltering) _primRecMarker.filter(true);
						_primRecMarker.hide();
					}else{
					   // assert("Relation Marker received a show message from a non relation records");
					}
				}
			},
			show: function(sendingMarker,changeFiltering){
				if (_gPolyline == null && ! _createLine()) {
						return;
				}
				if (_filtered) {
					return;
				}
				if (!_added) {
					_manager.map.addOverlay(_gPolyline);
					_added = true;
				}
				if (_gPolyline.supportsHide()){
					_gPolyline.show();
				}
				if (sendingMarker == _primRecMarker) {
					if (changeFiltering) _secRecMarker.filter(false);
					_secRecMarker.show();
				}else{
					if (sendingMarker == _secRecMarker) {
					if (changeFiltering) _primRecMarker.filter(false);
						_primRecMarker.show();
					}else{
						//assert("Relation Marker received a show message from a non relation records");
					}
				}
			},
            isHidden: function() {
                                if (_gPolyline.supportsHide()){
                                    return _gPolyline.isHidden();
                                }else{
                                    return _added ? false : true ;
                                }
            },
            applyFilter: function(filter) {   //TODO
                        // if relation has a start date and/or end date check that they are within date range else hide and check attached records to hide
                        // check that the record type is in the inclusion set of types else hide and check attached records to hide
                        var attachedRecMarker = _attached == 1 ?   _primRecMarker :  _secRecMarker ;
                        var sendingMarker = _attached == 1 ?  _secRecMarker :   _primRecMarker ;
                        // to prevent looping we only send filtering to a deeper node
                        if (attachedRecMarker.getDepth() < _manager.getMaxDepth()) attachedRecMarker.applyFilter(filter);
                        if (filter.isInTimeRange(_start,_end)) {
                            _filtered = false;
                            this.show(sendingMarker,true);
                        }else{
                            this.hide(sendingMarker,true);
                            _filtered = true;
                        }
             },
            prepForDelete: function() {
                        //clear the marker from the map
                        _manager.map.removeOverlay(_gPolyline);
                        delete _gPolyline;
                        //unhook this marker from the record
                        if (_hRelRec.marker) {
                            _hRelRec.marker = undefined;
                        }
            },
            getClass: function() { return _className;},
            isA: function(strClass){ if(strClass === _className) return true; return false;}
        };
        // add a link to the record back to this marker.
        _hRelRec.marker = that;              // add link to the relation record to this marker
        _primRecMarker.addRelation(that);
        _secRecMarker.addRelation(that);    //need to add these links so the markers can update the relations when they move display location
        return that;
}

function AffinityGroupMarker(affinityMarker,manager) {

       if ( !affinityMarker.isA("RecordMarker")) return null;  //FIXME  we need to through an exception here. We want to keep record markers separate from relation markers.

     /**********private***************/
        var _className = "AffinityGroupMarker";
        var _affinityMarker = affinityMarker;
        var _markers = [];
        var _expanded = false;
        var _dynamicRadius = true;
        var _radius = 30;      //TODO   figure out algrithm for a display radius  default to 30 px
        var _angleOffset = 0;    //TODO  set this up to calc a random number from lat x long
        var _angle = 3.14;       //set to approx. 2xPI
        var _manager = manager;

        function _sendShowAll(){
                var markerCount = _markers.length;
                for (var i=0; i < markerCount; ++i) {
                        _markers[i].show();
                }
        }

        function _calcEtherMarkerPositions(){
                                   //TODO  create algrithm to reset the display positions of the attached markers
                                    // this should also adjust the relationship markers
            var centerLatLng = _affinityMarker.getDisplayLocation();
            var center = _manager.map.fromLatLngToDivPixel(centerLatLng);
            var markerCount = _markers.length;
            if (_dynamicRadius) _radius = Math.max(30, 6 * markerCount);
            angleOffset = centerLatLng.lat() * centerLatLng.lng() + 0.5;
            for (var i=0; i < markerCount; ++i) {
                    angle = angleOffset + i * Math.PI * 2 / (markerCount);
                    point = new GPoint( center.x + _radius*Math.cos(angle), center.y + _radius*Math.sin(angle) );
                    latlng = _manager.map.fromDivPixelToLatLng(point);
                    _markers[i].setDisplayLocation(latlng);
            }
        }
     /**********public***************/

        var that = {
            getAffinityMarker: function() { return _affinityMarker;},
            setDisplayAngle: function(angle) { _angle = angle;},
            setDisplayAngleOffset: function(angleOffset) { _angleOffset = angleOffset;},
            setDisplayRadius: function(radius) {_radius = radius; _dynamicRadius=false;},
            addMarker: function(marker) {  //TODO
                // add marker to the
                _markers.push(marker);
                // set the Ehter Marker Position to AffinityMarker dispaly location
                if (_expanded) {
                    _calcEtherMarkerPositions();
                }else{
                    marker.setDisplayLocation(_affinityMarker.getDisplayLocation());
                }
                // add affinity group link to recordMarker
                marker.affinityGroup = that;   // we use a different name for this object to differentiate between the affinity marker and ether markers
            },
            getLocation: function(){ return _affinityMarker.getLocation();},
            setDisplayLocation: function(location){ _affinityMarker.setDisplayLocation(location);},
            getDisplayLocation: function(){ return _affinityMarker.getDisplayLocation();},
            hide: function(){
                              _affinityMarker.hide();
                              // TODO hide attached markers
            },
            show: function(){
                              _affinityMarker.show();
            },

            expand : function() {
                rBrowser.workflowIndex++;
                _expanded = true;
                this.recalcLayout();
                _sendShowAll();
            },

            collapse : function() {
                if(!_expanded) return;
                rBrowser.workflowIndex--;
                var markerCount = _markers.length,
                    location = _affinityMarker.getLocation();
                if (!location) location = _affinityMarker.getDisplayLocation();
                for (var i=0; i < markerCount; ++i) {
                    _markers[i].setDisplayLocation(location);
                    _markers[i].hide();
                }
                  _expanded = false;
            },

            onAffinityMarkerMove : function() {
                if (_expanded) {
                    this.recalcLayout();
                }
                else {
                    this.collapse();
                }
            },

            onClickHandler : function() {
                if (! _expanded) {
                    this.expand();
                }
                else {
                    this.collapse();
                }
            },

            isHidden: function() { return _affinityMarker.isHidden();},
            applyFilter: function(filter) {
                        // TODO apply filter to all markers

             },
             recalcLayout: function() {
                        //TODO write code to layout the positions of each mark
                       if (_expanded) _calcEtherMarkerPositions();
             },
            prepForDelete: function() {
                  _affinityMarker = null;
                  _markers = [];
            },
            getClass: function() { return _className;},
            isA: function(strClass){ if(strClass === _className) return true; return false;}
        };
        // add a link to the record back to this groupmarker. This changes from the original marker
        _affinityMarker.affinityGroupMarker = that;
       //change the affinity Markers icon to represent affinity group
        var imagePath = _affinityMarker.getImage().match(/(^.*\/)([^\/]*)$/); //separate the path from the filename
        _affinityMarker.setImage( imagePath[1] + "red-" + imagePath[2]);      //prepend "red-" to the filename    //FIXME need to add code to test file existance
        _affinityMarker.setZIndexOffset(10);
        return that;
}

    rBrowser.baseIcon = new GIcon();
    rBrowser.baseIcon.image = "http://heuristscholar.org/heurist/img/circle.png";
    rBrowser.baseIcon.shadow = "http://heuristscholar.org/heurist/img/circle-shadow.png";
    rBrowser.baseIcon.iconAnchor = new GPoint(13, 13);
    rBrowser.baseIcon.infoWindowAnchor = new GPoint(13, 13);
    rBrowser.baseIcon.iconSize = new GSize(26, 26);
    rBrowser.baseIcon.shadowSize = new GSize(48, 28);

    /**
     *  Icon used  for cluster in expanded mode
     *
     *  @property _collapseIcon
     *  @type  GIcon
     *  @private
     */
    rBrowser.collapseIcon = new GIcon(rBrowser.baseIcon);
    //set the foreground image
    rBrowser.collapseIcon.image = "http://" + window.location.host + "/hapi/samples/collapso-circle.gif";

    /**
     *  Icon used  for cluster in collapsed mode
     *
     *  @property _expandIcon
     *  @type  GIcon
     *  @private
     */
    rBrowser.expandIcon = new GIcon(rBrowser.baseIcon);
    //set the foreground image
    rBrowser.expandIcon.image = "http://" + window.location.host + "/hapi/samples/expando-circle.gif";

/******* Cluster Marker *************
 * A cluster marker is a display simplification object where multiple objects in proximity of each other
 * are represented by a single object. A clusterMarker is displayed collapsed or expanded. It acts as a container
 * for other markers.
 *
 * @factory     ClusterMarker
 * @param       location
 * @return      instance of a clusterMarker object
 */

function ClusterMarker(loc,index,manager) {

     /**********private***************/
    var _className = "ClusterMarker";

    var _clusteredMarkers = [];
    var _location = loc;
    var _clusterMarker = new GMarker(loc, { icon: rBrowser.expandIcon, title: "Click to see all markers at this point",zIndexProcess:calcZOrder });
    _clusterMarker.offsetIndex = 5000; //3250 is threshold for overlapped markers use 5000 to ensure cluster are in front of close markers
    var _index = index;
    var _expanded = false;
    var _manager = manager;
    var _added = false;
    var _ignoreCallbacks = false;  // a state variable to lockout callbacks generated by calls to contained markers (show and hide)

    function _sendShowAll(){
            var markerCount = _clusteredMarkers.length;
            for (var i=0; i < markerCount; ++i) {
                    _clusteredMarkers[i].show();
            }
    }

    function _checkForDups() {
        var len = _clusteredMarkers.length,
            i,j,
            dups = {},
            reverseList = "";
        for (i=0; i < len; i++) {
            for (j = i+1; j < len; j++){
                if (_clusteredMarkers[i] === _clusteredMarkers[j]) dups[j] = 1;  //using indexes eleminates multiple recognitions of a dup
            }
        }
        for (index in dups) { reverseList = index + ( reverseList == "" ? "" : ",") + reverseList;}   //FIXME  need to be sure this is sorted
        if (reverseList != ""){
            reverseList = reverseList.split(",");
            for (var k = 0; k < reverseList.length; k++) { _clusteredMarkers.splice(reverseList[k],1);}
        }
    }
    /************  begin public interface **************/
    var that = {
        isExpanded : function() {return _expanded;},

        addMarker: function(marker) {
                        var len = _clusteredMarkers.length,
                            i,
                            exist = false;
                        for (i=0; i < len; i++){
                            if (_clusteredMarkers[i] === marker) {
                                exist = true;
                                break;
                            }
                        }
                        if (!exist){
                            _clusteredMarkers.push(marker);
                            if(_expanded) {
                                recalcLayout();
                            }else{
                                marker.hide();
                            }
                            marker.clusterGroupMarker = that;
                        }
        },

        expand : function() {
            rBrowser.workflowIndex++;
            _expanded = true;
            this.recalcLayout();
            _clusterMarker.setImage(rBrowser.collapseIcon.image);
            _ignoreCallbacks = true;
            _sendShowAll();
            _ignoreCallbacks = false;
        },

        hide: function(){
            _manager.map.removeOverlay(_clusterMarker);
            _added = false;
        },

        show: function(){
            _manager.map.addOverlay(_clusterMarker);
            _added = true;
        },

        collapse : function() {
            rBrowser.workflowIndex--;
            var markerCount = _clusteredMarkers.length;
            _ignoreCallbacks = true;
            for (var i=0; i < markerCount; ++i) {
                if(_clusteredMarkers[i].affinityGroupMarker) _clusteredMarkers[i].affinityGroupMarker.collapse();
                _clusteredMarkers[i].setDisplayLocation(_clusteredMarkers[i].getLocation());        //no need to check location exist, they do since this is a cluster
                _clusteredMarkers[i].hide();
            }
            _ignoreCallbacks = false;
            _clusterMarker.setImage(rBrowser.expandIcon.image);
            _expanded = false;
        },

        onClickHandler : function() {      //FIXME  need to update any state that the display Manager keeps about clusters
            if (! _expanded) {
                this.expand();
            }
            else {
                this.collapse();
            }
        },

        recalcLayout: function() {
            if (!_expanded) return;  // if collapsed we are done

            _checkForDups();   // this is a patch for a bug where duplicate markers are added to the cluster. We remove them here.
            var latlng, point;
            var radius = Math.max(30, 6 * _clusteredMarkers.length);    // approximation in pixels
            var centerLatLng = _clusterMarker.getLatLng();
            var center = _manager.map.fromLatLngToDivPixel(centerLatLng);

            var markerCount = _clusteredMarkers.length;
            var angle,
                angleOffset = centerLatLng.lat() * centerLatLng.lng();
            for (var i=0; i < markerCount; ++i) {

                    angle = angleOffset + i * Math.PI * 2 / markerCount;
                    point = new GPoint( center.x + radius*Math.cos(angle), center.y + radius*Math.sin(angle) );
                    latlng = _manager.map.fromDivPixelToLatLng(point);

                    _clusteredMarkers[i].setDisplayLocation(latlng);
            }

        },

        markerVisibilityUpdate: function( marker, visible) {
            if (_ignoreCallbacks) return;   //NOTE this assumes that there can be no filtering during calls to marker methods
            // one of the markers was affected by filtering.

            _checkForDups();   // this is a patch for a bug where duplicate markers are added to the cluster. We remove them here.

            if ( visible){
               //if only a single marker and the cluster marker has not been added, add it to the map
               if (_clusteredMarkers.length == 1 && !_added) this.show();
               //add the marker to the shown markers
               _clusteredMarkers.push(marker);
               // if the newly added marker is the only one or the cluster is collapsed we have nothing  to do
               if (_clusteredMarkers.length > 1 && _expanded) this.expand();
            }else{
            // if hidden then remove it from markers  then if marker count is 1 - move marker to location and remove cluster marker from map.
               var len =  _clusteredMarkers.length,
                   i,
                   clusterChanged = false;
               for (i=0; i < len; i++){
                    if (_clusteredMarkers[i] === marker) {
                        _clusteredMarkers.splice(i,1);
                        clusterChanged = true;
                        break;
                    }
               }
               //if only a single marker and the cluster marker still added, remove it from the map
               if (_clusteredMarkers.length == 1 ) {
                    if (_added) this.hide();
                    _clusteredMarkers[0].setDisplayLocation( _clusteredMarkers[0].getLocation());
               }else{
                    //reposition the layout
                    if ( _clusteredMarkers.length > 2 && _expanded ) this.expand();
               }
            }
        },

        prepForDelete: function() {
                        //clear the marker from the map
                        _manager.map.removeOverlay(_clusterMarker);
                        delete _clusterMarker;
                        GEvent.removeListener(_clusterMarker.clickListener);
                        _clusteredMarkers = [];
                        _location = undefined;
        },
        getClass: function() { return _className;},

        isA: function(strClass){ if(strClass === _className) return true; return false;}
    };   // end public interface  that

    _clusterMarker.clickListener = GEvent.addListener(_clusterMarker, "click", function() { that.onClickHandler();});

    _manager.map.addOverlay(_clusterMarker);

    return that;
}
