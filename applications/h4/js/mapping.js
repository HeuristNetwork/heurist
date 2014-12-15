/**
* Class to work with OS Timemap,js. It allows initialization of mapping and timeline controls and fills data for these controls
* 
* @param _map - id of map element container 
* @param _timeline - id of timeline element
* @param _basePath - need to specify path to timemap assets (images)
* @returns {Object}
* @see editing_input.js
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


function hMapping(_map, _timeline, _basePath) {
    var _className = "Mapping",
    _version   = "0.4";

    var mapdiv_id = null,
    timelinediv_id = null,
    basePath = '',
    mapdata = [];

    var gmap = null,   // background gmap - gmap or other
    tmap = null,  // timemap object

    defaultZoom = 2,
    keepMinDate = null,
    keepMaxDate = null,
    keepMinNaxDate = true,

    timeZoomSteps =  window["Timeline"]?[
        { pixelsPerInterval: 200,  unit: Timeline.DateTime.DAY },
        { pixelsPerInterval: 100,  unit: Timeline.DateTime.DAY },
        { pixelsPerInterval:  50,  unit: Timeline.DateTime.DAY },
        { pixelsPerInterval: 400,  unit: Timeline.DateTime.MONTH },
        { pixelsPerInterval: 200,  unit: Timeline.DateTime.MONTH },
        { pixelsPerInterval: 100,  unit: Timeline.DateTime.MONTH },
        { pixelsPerInterval:  50,  unit: Timeline.DateTime.MONTH },
        { pixelsPerInterval: 200,  unit: Timeline.DateTime.YEAR },
        { pixelsPerInterval: 100,  unit: Timeline.DateTime.YEAR },
        { pixelsPerInterval:  50,  unit: Timeline.DateTime.YEAR },
        { pixelsPerInterval: 200,  unit: Timeline.DateTime.DECADE },
        { pixelsPerInterval: 100,  unit: Timeline.DateTime.DECADE },
        { pixelsPerInterval:  50,  unit: Timeline.DateTime.DECADE },
        { pixelsPerInterval: 200,  unit: Timeline.DateTime.CENTURY },
        { pixelsPerInterval: 100,  unit: Timeline.DateTime.CENTURY },
        { pixelsPerInterval:  50,  unit: Timeline.DateTime.CENTURY },
        { pixelsPerInterval: 200,  unit: Timeline.DateTime.MILLENNIUM },
        { pixelsPerInterval: 100,  unit: Timeline.DateTime.MILLENNIUM },
        { pixelsPerInterval:  50,  unit: Timeline.DateTime.MILLENNIUM }/*,
        { pixelsPerInterval: 200,  unit: Timeline.DateTime.EPOCH },
        { pixelsPerInterval: 100,  unit: Timeline.DateTime.EPOCH },
        { pixelsPerInterval:  50,  unit: Timeline.DateTime.EPOCH },
        { pixelsPerInterval: 200,  unit: Timeline.DateTime.ERA },
        { pixelsPerInterval: 100,  unit: Timeline.DateTime.ERA },
        { pixelsPerInterval:  50,  unit: Timeline.DateTime.ERA }*/
    ]:[],

    customMapTypes = [];


    /**
    * Initialization
    */
    function _init(_map, _timeline, _basePath) {
        mapdiv_id = _map;
        timelinediv_id = _timeline;
        basePath = _basePath;

        /*if(_mapdata){
        _load(_mapdata);
        }*/
    }

    /**
    * Load timemap datasets 
    * @see hRecordSet.toTimemap()
    * 
    * @param _mapdata
    */
    function _load(_mapdata, _selection, _onSelectEventListener){
        mapdata = _mapdata || [];
        
        // TimeMap theme
        var customIcon = basePath + "assets/star-red.png"
        var customTheme = new TimeMapTheme({
            "color": "#0000FF",
            "icon": customIcon,
            "iconSize": [16,16],
            "iconShadow": null,
            "iconAnchor":[9,17]
        });
        var tl_theme = Timeline.ClassicTheme.create();
        tl_theme.autoWidth = true;
        tl_theme.mouseWheel = "default";//"zoom";
        /*TODO tl_theme.event.bubble.bodyStyler = function(elem){
        $(elem).addClass("popup_body");
        };*/
        tl_theme.event.track.offset = 1.4;

        // Initialize TimeMap
        tmap = TimeMap.init({
            mapId: mapdiv_id, // Id of gmap div element (required)
            timelineId: timelinediv_id, // Id of timeline div element (required)
            datasets: mapdata, //.timemap || mapdata,
            
            options: {
                mapZoom: defaultZoom,
                theme: customTheme,
                showMapCtrl: true,
                //ART 201302 useMarkerCluster: (mapdata.count_mapobjects<300),
                // TODO onlyTimeline: false, //TODO (mapdata.count_mapobjects<1),
                /*
                mapZoom: 1, //default zoom
                centerMapOnItems: bounds ? false : true,
                showMapCtrl: false,
                showMapTypeCtrl: false,
                mapZoom: RelBrowser.Mapping.defaultZoom,
                centerMapOnItems: bounds ? false : true,
                mapType: M.customMapTypes[0] || mxn.Mapstraction.ROAD,
                //!!! theme: TimeMapTheme.create("blue", { eventIconPath: RelBrowser.baseURL + "timemap.2.0/images/" }),
                openInfoWindow: mini ? function () { return false; } : RelBrowser.Mapping.openInfoWindowHandler
                openInfoWindow: RelBrowser.Mapping.openInfoWindowHandler,
                */
                eventIconPath: basePath + "ext/timemap.js/2.0.1/images/"
                // TODO openInfoWindow: _onOpenInfoWindow
            },

            bandInfo: [
                /*{
                theme: tl_theme2,
                showEventText: false,
                intervalUnit: M.timeZoomSteps[M.initTimeZoomIndex-1].unit,
                intervalPixels: M.timeZoomSteps[M.initTimeZoomIndex-1].pixelsPerInterval,
                zoomIndex: M.initTimeZoomIndex-1,
                zoomSteps: M.timeZoomSteps,
                trackHeight:    0.2,
                trackGap:       0.1,
                width: "30px",
                layout:'overview'
                },*/
                {
                    theme: tl_theme,
                    align: "Top",
                    showEventText: true,
                    intervalUnit: timeZoomSteps[timeZoomSteps.length - 1].unit,
                    intervalPixels: timeZoomSteps[timeZoomSteps.length - 1].pixelsPerInterval,
                    zoomIndex: timeZoomSteps.length - 1,
                    zoomSteps: timeZoomSteps,
                    trackHeight: 1.3,
                    trackGap:    0.2,
                    width: "100%"
                }
            ],
            dataLoadedFunction: _onDataLoaded
            }, tmap);

        // Add controls if the map is not initialized yet

            var mapOptions = {
                panControl: true,
                zoomControl: true,
                mapTypeControl: true,
                scaleControl: true,     
                overviewMapControl: true,
                rotateControl: true,
                scrollwheel: true,
                mapTypeControlOptions: {
                  mapTypeIds: ["terrain","roadmap","hybrid","satellite","tile"]
                }
            };
        if(!gmap){ 
           tmap.map.addControls(mapOptions);
        }
            tmap.getNativeMap().setOptions(mapOptions);

        gmap = tmap.map; //background gmap - gmap or other - needed for direct access  
        addMapOverlay(tmap.getNativeMap());
    }

    function _onDataLoaded(_tmap){
        tmap = _tmap;
        _renderTimelineZoom();
        _zoomTimeLineToAll();
    }


    function _onOpenInfoWindow(_item) {

    }

    function _renderTimelineZoom(){
        // Zoomline div styling
        var $div = $("#timeline-zoom");
        if ($div.length > 0) { return; } //already defined

        $div = $("<div>").css({
            'position': 'absolute',
            'top': '16px',
            'left': '3px',
            'z-index': '2000',
            'width': '20px',
            'visibility': 'visible'}).appendTo( $("#"+timelinediv_id) );

        var zoom, x;

        /*
        * internal
        */
        zoom = function (zoomIn) {

            var band = tmap.timeline.getBand(0);
            x = ($(tmap.tElement).width() / 2) - band.getViewOffset();

            if (!band._zoomSteps) {
                band._zoomSteps = timeZoomSteps;
            }

            //artem: timelne 1.2 has some problems with zoom - so we took it from v2.0

            ether_zoom = function(_band, ether, zoomIn) {
                var netIntervalChange = 0;
                var currentZoomIndex = _band._zoomIndex;
                var newZoomIndex = currentZoomIndex;

                if (zoomIn && (currentZoomIndex > 0)) {
                    newZoomIndex = currentZoomIndex - 1;
                }

                if (!zoomIn && (currentZoomIndex < (_band._zoomSteps.length - 1))) {
                    newZoomIndex = currentZoomIndex + 1;
                }

                _band._zoomIndex = newZoomIndex;
                ether._interval  = Timeline.DateTime.gregorianUnitLengths[_band._zoomSteps[newZoomIndex].unit];
                ether._pixelsPerInterval = _band._zoomSteps[newZoomIndex].pixelsPerInterval;
                netIntervalChange = _band._zoomSteps[newZoomIndex].unit -
                _band._zoomSteps[currentZoomIndex].unit;
                return netIntervalChange;
            };

            // zoom disabled
            // shift the x value by our offset
            x += band._viewOffset;

            var zoomDate = band._ether.pixelOffsetToDate(x);
            var netIntervalChange = ether_zoom(band, band.getEther(), zoomIn);
            if (netIntervalChange != 0) {
                band._etherPainter._unit += netIntervalChange;
            }

            // shift our zoom date to the far left
            band._moveEther(Math.round(-band._ether.dateToPixelOffset(zoomDate)));
            // then shift it back to where the mouse was
            band._moveEther(x);


            /*
            band.zoom(zoomIn, x);*/
            tmap.timeline.paint();
        }; //end internal zoom function

        // Controls
        $( "<div>", {title:'Zoom In'} )
        .button({icons: {
            primary: "ui-icon-zoomin"
            },text:false})
        .click(function () {
            zoom(true);
        })
        .appendTo($div);
        $( "<div>", {title:'Show All'} )
        .button({icons: {
            primary: "ui-icon-carat-2-e-w"
            },text:false})
        .click(function () {
            _zoomTimeLineToAll();
        })
        .appendTo($div);
        $( "<div>", {title:'Zoom Out'} )
        .button({icons: {
            primary: "ui-icon-zoomout"
            },text:false})
        .click(function () {
            zoom(false);
        })
        .appendTo($div);


        //save last known interval to restore it on case of window resize
        function __keepTimelineInterval(){
            if(keepMinNaxDate){
                var band = tmap.timeline.getBand(0);
                keepMinDate = band.getMinVisibleDate();
                keepMaxDate = band.getMaxVisibleDate();// getCenterVisibleDate()
            }
        }

        tmap.timeline.autoWidth = false;
        tmap.timeline.getBand(0).addOnScrollListener(  __keepTimelineInterval );
    }

    /**
    *
    */
    function _zoomTimeLineToAll(){
        //$("#timeline").css("height", "99%");
        
        // Time mid point
        function __timeMidPoint(start, end) {
            var d, diff;
            d = new Date();
            diff = end.getTime() - start.getTime();
            d.setTime(start.getTime() + diff/2);
            return d;
        }
        
        // Find time scale
        function __findTimeScale(start, end, scales, timelineWidth) {
            var diff, span, unitLength, intervals, i;
            s = new Date();
            e = new Date();
            diff = end.getTime() - start.getTime();
            span = diff * 1.1;    // pad by 5% each end
            for (i = 0; i < scales.length; ++i) {
                unitLength = Timeline.DateTime.gregorianUnitLengths[scales[i].unit];
                intervals = timelineWidth / scales[i].pixelsPerInterval;
                if (span / unitLength <= intervals) {
                    return i;
                }
            }
            return i;
        }
        
        // Change scale
        function __changeScale(bandIndex, zoomIndex) {

            if(zoomIndex<0) {
                zoomIndex=0;
            } else if (zoomIndex>=timeZoomSteps.length){
                zoomIndex = timeZoomSteps.length-1;
            }
            var band, interval;
            band = tmap.timeline.getBand(bandIndex);

            if(band){
                interval = timeZoomSteps[zoomIndex].unit;
                band._zoomIndex = zoomIndex;
                band.getEther()._pixelsPerInterval = timeZoomSteps[zoomIndex].pixelsPerInterval;
                band.getEther()._interval = Timeline.DateTime.gregorianUnitLengths[interval];
                band.getEtherPainter()._unit = interval;
            }

        }

        var start, end, zoomIndex, eventSource, d = new Date();
        eventSource = tmap.timeline.getBand(0).getEventSource();

        if (eventSource.getCount() > 0) {
            start = eventSource.getEarliestDate();
            end = eventSource.getLatestDate();
            d = __timeMidPoint(start, end);

            zoomIndex = __findTimeScale(start, end, timeZoomSteps, ($(tmap.tElement).width()));

            //changeScale(1, zoomIndex, tm); //WAS + zoomIndex+3
            __changeScale(0, zoomIndex);
        }
        tmap.timeline.getBand(0).setCenterVisibleDate(d);
        tmap.timeline.layout();
    }


    /**
    *  Keeps timeline zoom 
    */
    function _onWinResize(){

        if(tmap && keepMinDate && keepMinDate){
            keepMinNaxDate = false;
            setTimeout(function (){

                var band = tmap.timeline.getBand(0);
                band.setMinVisibleDate(keepMinDate);
                band.setMaxVisibleDate(keepMaxDate);
                tmap.timeline.layout();

                keepMinNaxDate = true;
                }, 1000);
        }
    }

    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        load: function(_mapdata, _selection, _onSelectEventListener){
            _load(_mapdata, _selection, _onSelectEventListener);
        },
        
        showSelection: function(_selection){
            
        },

        onWinResize: function(){
            _onWinResize();
            if(gmap){ //fix google map bug
                gmap.resizeTo(0,0)
            }
        }

    }

    _init(_map, _timeline, _basePath);
    return that;  //returns object
}