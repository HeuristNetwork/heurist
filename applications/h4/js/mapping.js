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


function hMapping(_map, _timeline, _basePath, _mylayout) {
    var _className = "Mapping",
    _version   = "0.4";

    var mapdiv_id = null,
    timelinediv_id = null,
    basePath = '',
    mapdata = [],
    selection = [], //array of selected record ids
    mylayout;

    var gmap = null,   // background gmap - gmap or other
    tmap = null,  // timemap object
    drawingManager,     //manager to draw the selection rectnagle
    lastSelectionShape,  

    defaultZoom = 2,
    keepMinDate = null,
    keepMaxDate = null,
    keepMinNaxDate = true,
    
    _onSelectEventListener,
    
    // TimeMap theme
    customTheme = new TimeMapTheme({
            "color": "#0000FF",  //for lines and polygones
            "lineColor": "#0000FF",
            "icon": basePath + "assets/star-red.png",
            "iconSize": [25,25],
            "iconShadow": null,
            "iconAnchor":[9,17]
    }),

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
    function _init(_map, _timeline, _basePath, _mylayout) {
        mapdiv_id = _map;
        timelinediv_id = _timeline;
        basePath = _basePath;
        mylayout = _mylayout;

        /*if(_mapdata){
        _load(_mapdata);
        }*/
    }
    
    
    function _updateLayout(){
        
        if(!mapdata || mapdata.length==0 || mapdata[0].mapenabled==0 && mapdata[0].timeenabled==0){ //empty
               return false;
        }else if(mapdata[0].mapenabled==0){

            $(".ui-layout-north").hide();
            $(".ui-layout-center").hide();
            $(".ui-layout-resizer-south").hide();
            $(".ui-layout-south").show();
            $(".ui-layout-south").css({'width':'100%','height':'100%'});
            //mylayout.hide('center');
            
            
            //mylayout.changeOption('center','minSize',0);
            //$(".ui-layout-resizer-south").css('height',0);
            //mylayout.sizePane('south','100%');

        }else{

            //mylayout.changeOption('center','minSize',300);
            //$(".ui-layout-resizer-south").css('height',7);

            $(".ui-layout-north").show();
            $(".ui-layout-center").show();
            $(".ui-layout-resizer-south").show();
            $(".ui-layout-south").css({'height':'200px'});
            
            if(mapdata[0].timeenabled==0){
                mylayout.hide('south');
            }else {
                mylayout.show('south', true);   
                mylayout.sizePane('south', 200);
            }
            
        } 
        
        return true;
        
    }
    

    /**
    * Load timemap datasets 
    * @see hRecordSet.toTimemap()
    * 
    * @param _mapdata
    */
    function _load(_mapdata, _selection, __onSelectEventListener){

        $( document ).bubble('closeAll');        
        
        _onSelectEventListener = __onSelectEventListener;
        mapdata = _mapdata || [];
        selection = _selection || [];
        
        var tl_theme = Timeline.ClassicTheme.create();
        tl_theme.autoWidth = true;
        tl_theme.mouseWheel = "default";//"zoom";
        /*TODO tl_theme.event.bubble.bodyStyler = function(elem){
        $(elem).addClass("popup_body");
        };*/
        tl_theme.event.track.offset = 1.4;

        //timemap is already inited
        if(tmap && tmap.datasets && tmap.datasets.main){ 
            
                //tmap.deleteDataset("main");
                //var dataset = tmap.createDataset("main");
                var dataset = tmap.datasets.main;
                dataset.clear();
                if(_updateLayout()){
                    dataset.loadItems(mapdata[0].options.items);
                    dataset.show();
                }
                _onDataLoaded(tmap);
                return; 
        }
        
        var hasItems = _updateLayout();
        
        
        //art 020215  
        // NOT USED - since we use _showPopupInfo   
        // TimeMapItem.openInfoWindowBasic = _showPopupInfo;
      
        //there is bug in timeline - it looks _history_.html in wrong place
        SimileAjax.History.enabled = false;
        
        // Initialize TimeMap
        tmap = TimeMap.init({
            mapId: mapdiv_id, // Id of gmap div element (required)
            timelineId: timelinediv_id, // Id of timeline div element (required)
            datasets: mapdata, //.timemap || mapdata,
            
            options: {
                mapZoom: defaultZoom,
                theme: customTheme,
                //showMapCtrl: true,
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
                eventIconPath: top.HAPI4.iconBaseURL //basePath + "ext/timemap.js/2.0.1/images/"
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
                    trackHeight: 2.3,
                    trackGap:    0.2,
                    width: "100%"
                }
            ],
            dataLoadedFunction: _onDataLoaded
            }, tmap);

        
        if(hasItems && (mapdata[0].mapenabled>0)){
            
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
                //tmap.map.addControls(mapOptions);
            }
            tmap.getNativeMap().setOptions(mapOptions);

            gmap = tmap.map; //background gmap - gmap or other - needed for direct access  

            addMapOverlay(tmap.getNativeMap()); //loading the lsit of map documents see map_overlay.js
            
            _initDrawListeners();
            
        }
            
    }
    
    
    function _initDrawListeners(){
        
            var shift_draw = false;
        
            //addd drawing manager to draw rectangle selection tool
            var shapeOptions = {
                strokeWeight: 1,
                strokeOpacity: 1,
                fillOpacity: 0.2,
                editable: false,
                clickable: false,
                strokeColor: '#3399FF',
                fillColor: '#3399FF'
            };            
            
            drawingManager = new google.maps.drawing.DrawingManager({
                drawingMode: null,
                drawingControlOptions: {drawingModes: [google.maps.drawing.OverlayType.POLYGON, google.maps.drawing.OverlayType.RECTANGLE]},
                rectangleOptions: shapeOptions,
                polygonOptions: shapeOptions,
                map: tmap.getNativeMap()
            });            
            
            google.maps.event.addListener(drawingManager, 'overlaycomplete', function(e) {
                
                //clear previous
                if (lastSelectionShape != undefined) {
                    lastSelectionShape.setMap(null);
                }

                // cancel drawing mode
                if (shift_draw == false) { drawingManager.setDrawingMode(null); }

                lastSelectionShape = e.overlay;
                lastSelectionShape.type = e.type;

                _selectItemsInShape();
                
                /*if (lastSelectionShape.type == google.maps.drawing.OverlayType.RECTANGLE) {

                    lastBounds = lastSelectionShape.getBounds();

                    //$('#bounds').html(lastBounds.toString());

                    //mapdata[0].options.items[0].options.recid
                    //mapdata[0].options.items[3].placemarks[0].polyline .lat .lon
                    
                    
                    //new google.maps.LatLng(25.774252, -80.190262),
                    
                    
                    // determine if marker1 is inside bounds:
                    if (lastBounds.contains(m1.getPosition())) {
                        //$('#inside').html('Yup!');
                    } else {
                        //$('#inside').html('Nope...');
                    }

                } else if (lastSelectionShape.type == google.maps.drawing.OverlayType.POLYGON) {

                    //$('#bounds').html('N/A');

                    // determine if marker is inside the polygon:
                    // (refer to: https://developers.google.com/maps/documentation/javascript/reference#poly)
                    if (google.maps.geometry.poly.containsLocation(m1.getPosition(), lastSelectionShape)) {
                        //$('#inside').html('Yup!');
                    } else {
                        //$('#inside').html('Nope...');
                    }

                }*/

            });

            /*var shift_draw = false;

            $(document).bind('keydown', function(e) {
            if(e.keyCode==16 && shift_draw == false){
            map.setOptions({draggable: false, disableDoubleClickZoom: true});
            shift_draw = true; // enable drawing
            drawingManager.setDrawingMode(google.maps.drawing.OverlayType.RECTANGLE);
            }

            });

            $(document).bind('keyup', function(e) {
            if(e.keyCode==16){
            map.setOptions({draggable: true, disableDoubleClickZoom: true});
            shift_draw = false // disable drawing
            drawingManager.setDrawingMode(null);
            }

            });*/

            //clear rectangle on any click or drag on the map
            google.maps.event.addListener(map, 'mousedown', function () {
                if (lastSelectionShape != undefined) {
                    lastSelectionShape.setMap(null);
                }
            });

            google.maps.event.addListener(map, 'drag', function () {
                if (lastSelectionShape != undefined) {
                    lastSelectionShape.setMap(null);
                }
            });
        
    }
    
    function _selectItemsInShape(){
        
        selection = [];
        
        var isRect = (lastSelectionShape.type == google.maps.drawing.OverlayType.RECTANGLE);
        var lastBounds, i;
        if(isRect){
            lastBounds = lastSelectionShape.getBounds();
        }
        
        var dataset = tmap.datasets.main;  //take main dataset
            dataset.each(function(item){ //loop trough all items
                   
                        if(item.placemark){
                            var isOK = false;
                            if(item.placemark.points){ //polygone or polyline
                                for(i=0; i<item.placemark.points.length; i++){
                                    var pnt = item.placemark.points[i];
                                    var pos = new google.maps.LatLng(pnt.lat, pnt.lon);
                                    if(isRect){
                                        isOK = lastBounds.contains( pos );
                                    }else{
                                        isOK = google.maps.geometry.poly.containsLocation(pos, lastSelectionShape);
                                    }
                                    if(isOK) break;
                                }

                            }else{
                                var pos = item.getNativePlacemark().getPosition();
                                if(isRect){
                                    isOK = lastBounds.contains( pos );
                                }else{
                                    isOK = google.maps.geometry.poly.containsLocation(pos, lastSelectionShape);
                                }

                            }
                            if(isOK){
                                selection.push(item.opts.recid);    
                            }


                        }
            });
            
            
        //reset and highlight selection
        _showSelection(true);
        //trigger selection - to highlight on other widgets
        _onSelectEventListener.call(that, selection);
    }
    

    function _onDataLoaded(_tmap){
        tmap = _tmap;
        
        var dataset = tmap.datasets.main;
        dataset.each(function(item){
//art 020215             
                item.opts.openInfoWindow = _onItemSelection;
        });
        
        //highlight selection
        _showSelection(false);
        _renderTimelineZoom();
        _zoomTimeLineToAll();
        
        //fix bug in timeline-2.3.0 - adjust height of timeline-band-0
        var timeline = $("#"+timelinediv_id);
        var tlband0 = timeline.find("#timeline-band-0");
        
        var icons = tlband0.find('.timeline-band-layer-inner').find('.timeline-event-icon');
        
        var ids = icons.map(function() {
                return $(this).position().top; //parseInt(, 10);
                }).get();
        
        var highest =  Math.max.apply(Math, ids) + 26;
        highest = Math.max(timeline.height(), highest);
        
        tlband0.css('height', highest+'px');
        
        //asign tooltips for all 
        
        
    }

    //    
    // Add clicked marker to array of selected
    //
    // item.opts.openInfoWindow -> _onItemSelection  -> _showSelection (highlight marker) -> _showPopupInfo
    //
    function _onItemSelection(  ){
        //that - hMapping
        //this - item (map item)
        
        selection = [this.opts.recid];
        _showSelection(true);
        //trigger selection - to highlight on other widgets
        _onSelectEventListener.call(that, selection);
        //TimeMapItem.openInfoWindowBasic.call(this);        
    }
    
    //
    // highlight markers and show bubble
    // 
    //  item.opts.openInfoWindow -> _onItemSelection  -> _showSelection -> _showPopupInfo
    //
    // isreset - true - remove previous selection
    //
    function _showSelection( isreset ){

        var lastSelectedItem = null;
        var items_to_update = [];
        var items_to_update_data = [];
        var dataset = tmap.datasets.main;  //take main dataset
        
        if ( isreset || (selection && selection.length>0) ){
            
            dataset.each(function(item){ //loop trough all items

                    var idx = selection ?selection.indexOf(item.opts.recid) :-1;
                    
                    var itemdata = {
                    title: ''+item.opts.title,
                    start: item.opts.start,
                    end: item.opts.end,
                    placemarks: item.opts.places,
                    options:item.opts
                      /*{
                        description: item.opts.description,
                        //url: (record.url ? "'"+record.url+"' target='_blank'"  :"'javascript:void(0);'"), //for timemap popup
                        //link: record.url,  //for timeline popup
                        recid: item.opts.recid,
                        rectype: item.opts.rectype,
                        title: (item.opts.title+'+'),
                        //thumb: record.thumb_url,
                        eventIconImage: item.opts.rectype + '.png',
                        icon: top.HAPI4.iconBaseURL + item.opts.rectype + '.png',

                        start: item.opts.start,
                        end: item.opts.end,
                        places: item.opts.places
                        //,infoHTML: (infoHTML || ''),
                            }*/
                        };
                    
                    
                    if(idx>=0){ //this item is selected
                        
                        items_to_update.push(item);
                        
                        //was itemdata  
                        itemdata.options.eventIconImage = item.opts.rectype + 's.png';   //it will have selected record (blue bg)
                        itemdata.options.icon = top.HAPI4.iconBaseURL + itemdata.options.eventIconImage;
                        itemdata.options.color = "#FF0000";
                        itemdata.options.lineColor = "#FF0000";

                        items_to_update_data.push(itemdata);
                        
                        //if(idx == selection.length-1) lastSelectedItem = item;
                        
                    }else{ //clear selection
                        //item.opts.theme
                        //item.changeTheme(customTheme, true); - dont work
                        var usual_icon = item.opts.rectype + 'm.png'; //it will have usual gray bg
                        if(usual_icon != itemdata.options.eventIconImage){

                            items_to_update.push(item);
                            
                            //was itemdata
                            itemdata.options.eventIconImage = usual_icon;
                            itemdata.options.icon = top.HAPI4.iconBaseURL + itemdata.options.eventIconImage;
                            itemdata.options.color = "#0000FF";
                            itemdata.options.lineColor = "#0000FF";
                            
                            items_to_update_data.push(itemdata);
                        }
                    }                
            });
           
            /*
            if(items_to_update_data.length>0){
                dataset.clear();
                dataset.hide();
                dataset.loadItems(items_to_update_data);
                dataset.show();
            }     */
           
            
            if(items_to_update.length>0) {
                var lastRecID = (selection)?selection[selection.length-1]:-1;
                
                var tlband0 = $("#"+timelinediv_id).find("#timeline-band-0");
                var keep_height = tlband0.height();
                
                dataset.hide();
                
                var newitem;
                for (var i=0;i<items_to_update.length;i++){
                        //items_to_update[i].clear();
                        dataset.deleteItem(items_to_update[i]);
                        
                        //dataset.items.push(items_to_update[i]);
                        newitem = dataset.loadItem(items_to_update_data[i]);
                        
                        //art 020215 
                        newitem.opts.openInfoWindow = _onItemSelection;
                        
                        if(lastRecID==newitem.opts.recid){
                            lastSelectedItem = newitem;
                        }
                }
                dataset.show();
                //_zoomTimeLineToAll(); //
                //tmap.timeline.layout();
                
                tlband0.css('height', keep_height+'px');
            }
           
            //item.timeline.layout();
        }
        
        /*var lastRecID = (selection)?selection[selection.length-1]:-1;
        // loop through all items - change openInfoWindow
        if(items_to_update.length>0 || !isreset){
            var k = 0;
            dataset.each(function(item){
                item.opts.openInfoWindow = _onItemSelection;
                //item.showPlacemark();
                if(lastRecID==item.opts.recid){
                        lastSelectedItem = item;
                }
            });
        }*/
        
        if(lastSelectedItem){
            _showPopupInfo.call(lastSelectedItem);
            //TimeMapItem.openInfoWindowBasic.call(lastSelectedItem);
            //lastSelectedItem.openInfoWindow();
        }
    }
    
    //
    //  item.opts.openInfoWindow -> _onItemSelection  -> _showSelection -> _showPopupInfo
    //
    function _showPopupInfo(){
        
        
            //close others
            $( document ).bubble( "closeAll" );
                    
        
            var item = this,
                html = item.getInfoHtml(),
                ds = item.dataset,
                placemark = item.placemark;
                
            if(top.HAPI4.currentRecordset){
                
                var recset = top.HAPI4.currentRecordset;
                var record = top.HAPI4.currentRecordset.getById(item.opts.recid);
                
                function fld(fldname){
                    return recset.fld(record, fldname);
                }

                var recID = fld('rec_ID');
                var rectypeID = fld('rec_RecTypeID');
                var bkm_ID = fld('bkm_ID');
                var recTitle = top.HEURIST4.util.htmlEscape(fld('rec_Title')),
                    startDate   = fld('dtl_StartDate'),
                    endDate     = fld('dtl_EndDate'),
                    description = top.HEURIST4.util.htmlEscape(fld('dtl_Description')),
                    recURL      = fld('rec_URL');
                

                var html_thumb = '';
                if(fld('rec_ThumbnailURL')){
                    html_thumb = fld('rec_ThumbnailURL'); //'<div class="recTypeThumb" style="background-image: url(&quot;'+ fld('rec_ThumbnailURL') + '&quot;);opacity:1"></div>'
                }else{
                    html_thumb =  top.HAPI4.iconBaseURL + 'thumb/th_' + rectypeID + '.png';
                }
                

            html =
'<div style="width:190px;height:130px;overflow:auto;"><img src="'+html_thumb+'" class="timeline-event-bubble-image">'+  // 
'<div style="font-weight:bold">'+(recURL ?("<a href='"+recURL+"' target='_blank'>"+ recTitle + "</a>") :recTitle)+'</div>'+  //class="timeline-event-bubble-title"
'<div class="popup_body">'+ description +'</div>'+
((startDate)?'<div class="timeline-event-bubble-time">'+startDate+'</div>':'')+
((endDate)?'<div class="timeline-event-bubble-time">'+endDate+'</div>':'')+
              '<div style="width:100%">'
            +   '<div style="display:inline-block;">'
            +     '<img src="'+top.HAPI4.basePath+'assets/16x16.gif'+'" class="rt-icon" style="background-image: url(&quot;'+top.HAPI4.iconBaseURL + rectypeID+'.png&quot;);">'
            +     '<img src="'+top.HAPI4.basePath+'assets/13x13.gif" class="'+(bkm_ID?'bookmarked':'unbookmarked')+'">'                
            +   '</div>'
            +   '<div title="Click to edit record" style="float:right;height:16px;width:16px;" class="logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false">'
            //+ ' onclick={event.preventDefault(); window.open("'+(top.HAPI4.basePathOld+'edit/editRecord.html?db='+top.HAPI4.database+'&recID='+recID)+'", "_new");} >'
            +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
            +   '</div>'            
            + '</div>'            
+ '</div>';
                
            }
                
                
            // scroll timeline if necessary
            if (!item.onVisibleTimeline()) {
                ds.timemap.scrollToDate(item.getStart());
            }
            // open window
            if (item.getType() == "marker" && placemark.api) {
                placemark.setInfoBubble(html);
                placemark.openBubble();
                // deselect when window is closed
                item.closeHandler = placemark.closeInfoBubble.addHandler(function() {
                    // deselect
                    ds.timemap.setSelected(undefined);
                    // kill self
                    placemark.closeInfoBubble.removeHandler(item.closeHandler);
                });
            } else {
                if(item.event){    //reference to timeline event
                    
                    var painter = item.timeline.getBand(0).getEventPainter();
                    var marker = painter._eventIdToElmt[item.event.getID()]; //find marker div by internal ID
                    
                    //@todo - need to horizonatal scroll to show marker in viewport
                    
                    
                    
                    //change content
                    $( document ).bubble( "option", "content", html );
                    
                    /*$( document ).bubble({
                            items: ".timeline-event-icon",
                            content:html
                    });*/
                    $( marker ).click();
                    
                }else{
                    //neither map nor time data
                    //show on map center                  
                    // item.map.openBubble(item.getInfoPoint(), html);
                    // item.map.tmBubbleItem = item;
                }
            }
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
    
    function _printMap() {
        
          if(!gmap) return;
        
          var map = gmap;          

          tmap.getNativeMap().setOptions({
                panControl: false,
                zoomControl: false,
                mapTypeControl: false,
                scaleControl: false,     
                overviewMapControl: false,
                rotateControl: false
          });
         
          var popUpAndPrint = function() {
            dataUrl = [];
         
            $('#map canvas').filter(function() {
              dataUrl.push(this.toDataURL("image/png"));
            })
         
            var container = document.getElementById('map'); //map-canvas
            var clone = $(container).clone();
         
            var width = container.clientWidth
            var height = container.clientHeight
         
            $(clone).find('canvas').each(function(i, item) {
              $(item).replaceWith(
                $('<img>')
                  .attr('src', dataUrl[i]))
                  .css('position', 'absolute')
                  .css('left', '0')
                  .css('top', '0')
                  .css('width', width + 'px')
                  .css('height', height + 'px');
            });
         
            var printWindow = window.open('', 'PrintMap',
              'width=' + width + ',height=' + height);
            printWindow.document.writeln($(clone).html());
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.close();
         
            tmap.getNativeMap().setOptions({
                panControl: true,
                zoomControl: true,
                mapTypeControl: true,
                scaleControl: true,     
                overviewMapControl: true,
                rotateControl: true
            });
          };
         
          setTimeout(popUpAndPrint, 500);
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
             selection = _selection || [];
             _showSelection( true );
        },

        onWinResize: function(){
            _onWinResize();
            if(gmap){ //fix google map bug
                gmap.resizeTo(0,0)
            }
        },

        printMap: function(){
             _printMap();
        }
    }

    _init(_map, _timeline, _basePath, _mylayout);
    return that;  //returns object
}