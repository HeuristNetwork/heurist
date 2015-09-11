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
    //mapdata = [],
    selection = [], //array of selected record ids
    mylayout;

    var tmap = null,  // timemap object
    vis_timeline = null, // timeline object
    drawingManager,     //manager to draw the selection rectnagle
    lastSelectionShape,  

    defaultZoom = 2,
    keepMinDate = null,
    keepMaxDate = null,
    keepMinMaxDate = true,
    keppTimelineIconBottomPos = 0,
    
    _onSelectEventListener,
    _startup_mapdocument,
    
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
    
    function _isEmptyDataset(_mapdata){
        return (!top.HEURIST4.util.isArrayNotEmpty(_mapdata) ||  (_mapdata[0].mapenabled==0 && _mapdata[0].timeenabled==0));
    }
    
    /**
    * show/hide timeline
    */
    function _updateLayout(){
        
        var ismap = false, istime = false;
        
        tmap.each(function(dataset){   
            
            if(dataset.mapenabled > 0){
                ismap = true;
            }
            if(dataset.timeenabled > 0){
                istime = true;
            }
        });
        
        /*
        if(!(ismap || istime)) { //empty
               return false;
        }else if(!ismap){ 
            //always show the map - since map content can be loaded by selection of map document

            $(".ui-layout-north").hide();
            $(".ui-layout-center").hide();
            $(".ui-layout-resizer-south").hide();
            $(".ui-layout-south").show();
            $(".ui-layout-south").css({'width':'100%','height':'100%'});
            //mylayout.hide('center');
            
            
            //mylayout.changeOption('center','minSize',0);
            //$(".ui-layout-resizer-south").css('height',0);
            //mylayout.sizePane('south','100%');
        }*/
        

            //mylayout.changeOption('center','minSize',300);
            //$(".ui-layout-resizer-south").css('height',7);
            
            var th = $('#mapping').height(); 
            th = Math.floor(th*0.2);
            th = th>200?200:th;

            $(".ui-layout-north").show();
            $(".ui-layout-center").show();
            $(".ui-layout-resizer-south").show();
            $(".ui-layout-south").css({'height':th+'px'});
            
            if(!istime){
                mylayout.hide('south');
            }else {
                mylayout.show('south', true);   
                mylayout.sizePane('south', th);
                
            }
            
            setTimeout(_updateTimeline, 1000);
    
// _updateTimeline();
         
        
        return true;
        
    }
    
    /**
    * Load timemap datasets 
    * @see hRecordSet.toTimemap()
    * 
    * @param _mapdata
    */
    function _addDataset(_mapdata){
        
        var res = false;
        
        if(top.HEURIST4.util.isArrayNotEmpty(_mapdata)){
        
            var dataset_name = _mapdata[0].id;
            var dataset = tmap.datasets[dataset_name];
            
            if(_isEmptyDataset(_mapdata)){ //show/hide panels
            
                if(dataset)
                    tmap.deleteDataset(dataset_name);
            
            }else{
            
                if(!dataset){ //already exists with such name
                   dataset = tmap.createDataset(dataset_name);
                }
                    
                dataset.clear();

                dataset.mapenabled = _mapdata[0].mapenabled;
                dataset.timeenabled = _mapdata[0].timeenabled;
                dataset.loadItems(_mapdata[0].options.items);
                dataset.each(function(item){
                    item.opts.openInfoWindow = _onItemSelection;
                });
                
                dataset.hide();
                dataset.show();
                
                _loadVisTimeline(_mapdata[0]);
                
                //tmap.showDatasets();
                _updateLayout();
                
                res = true;
            }
        }
        return res;
    }

    function _deleteDataset(dataset_name){
            var dataset = tmap.datasets[dataset_name];
            if(dataset){
                 tmap.deleteDataset(dataset_name);
                 _updateLayout();    
            }
    }
    
    
    function _showDataset(dataset_name, is_show){
        var dataset = tmap.datasets[dataset_name];
        if(dataset){
            if(is_show){
                tmap.showDataset(dataset_name);
            }else{
                tmap.hideDataset(dataset_name);
            }
            
        }
        
    }
    
    function _getUnixTs(item, field, ds){

        var item_id = 0;
        if(item && item['id']){
            item_id = item['id'];
        }else{
            item_id = item;
        }
        if(item_id){
            
            if(!ds) ds = vis_timeline.itemsData;

            var type = {};
            type[field] = 'Moment';
            var val = ds.get(item_id,{fields: [field], type: type });            
            
            if(val!=null && val[field] && val[field]._isAMomentObject){
                //return val[field].toDate().getTime(); //unix
                return val[field].unix()*1000;
            }
        }
        return NaN;
    }
    
    //called once on first timeline load
    function _initVisTimelineToolbar(){
        
        /**
         * Zoom the timeline a given percentage in or out
         * @param {Number} percentage   For example 0.1 (zoom out) or -0.1 (zoom in)
         */
        function __timelineZoom (percentage) {
            var range = vis_timeline.getWindow();
            var interval = range.end - range.start;
           
            vis_timeline.setWindow({
                start: range.start.valueOf() - interval * percentage,
                end:   range.end.valueOf()   + interval * percentage
            });

    //console.log('set2: '+(new Date(range.start.valueOf() - interval * percentage))+' '+(new Date(range.end.valueOf()   + interval * percentage)));        
        }  
        
        function __timelineGetEnd(ds){

            if(!ds) ds = vis_timeline.itemsData;
            
            var item1 = ds.max('end');
            var item2 = ds.max('start');
            var end1 = _getUnixTs(item1, 'end', ds);
            var end2 = _getUnixTs(item2, 'start', ds);
                
            return isNaN(end1)?end2:Math.max(end1, end2);
        }
        
        function __timelineZoomAll(ds){
            
            if(!ds){
                vis_timeline.fit(); //short way
                return;
            }
            
            if(!ds) ds = vis_timeline.itemsData;
            
            //moment = timeline.itemsData.get(item['id'],{fields: ['start'], type: { start: 'Moment' }});        
            var start = _getUnixTs(ds.min('start'), 'start', ds);
            var end = __timelineGetEnd(ds);
            
            var interval = (end - start);
            
            if(isNaN(end) || end-start<1000){
                interval = 1000*60*60*24; //one day            
                end = start;
            }else{
                interval = interval*0.2;
            }
            
            vis_timeline.setWindow({
                start: start - interval,
                end: end + interval
            });        
        }

        function __timelineZoomSelection(){
            
            var sels = vis_timeline.getSelection();
            if(sels && sels['length']>0){
                   //vis_timeline.focus(sels); //short way - not work proprely
                   //return;
                
                   var ds = new vis.DataSet(vis_timeline.itemsData.get(sels));
                   __timelineZoomAll(ds);
            }
            
        }
        
        function __timelineMoveTo(dest){

            var start = _getUnixTs(vis_timeline.itemsData.min('start'), 'start');
            var end = __timelineGetEnd();
            
            var time = (dest=='end') ?end:start;
            
            var range = vis_timeline.getWindow();
            var interval = range.end - range.start;
            
            var delta = interval*0.05;

            if(isNaN(end) || end-start<1000){//single date

                interval = interval/2;
                
                vis_timeline.setWindow({
                    start: start - interval,
                    end:   start + interval
                });
                
            }else if(dest=='end'){
                vis_timeline.setWindow({
                    start: time + delta - interval,
                    end:   time + delta
                });
            }else{
                vis_timeline.setWindow({
                    start: time - delta,
                    end:   time - delta + interval
                });
            }
        }
        function __timelineShowLabels(){
            if($("#btn_timeline_labels").is(':checked')){
                $(".vis-item-content").find("span").show();
            }else{
                $(".vis-item-content").find("span").hide();
            }
        }
        
        var toolbar = $("#timeline_toolbar").zIndex(3).css('font-size','0.8em');

        $("<button>").button({icons: {
            primary: "ui-icon-circle-plus"
            },text:false, label:top.HR("Zoom In")})
            .click(function(){ __timelineZoom(-0.25); })
            .appendTo(toolbar);
        $("<button>").button({icons: {
            primary: "ui-icon-circle-minus"
            },text:false, label:top.HR("Zoom Out")})
            .click(function(){ __timelineZoom(0.5); })
            .appendTo(toolbar);
        $("<button>").button({icons: {
            primary: "ui-icon-arrowthick-2-e-w"
            },text:false, label:top.HR("Zoom to All")})
            .click(function(){ __timelineZoomAll(); })
            .appendTo(toolbar);
        $("<button>").button({icons: {
            primary: "ui-icon-arrowthickstop-1-s"
            },text:false, label:top.HR("Zoom to selection")})
            .click(function(){ __timelineZoomSelection(); })
            .appendTo(toolbar);
        $("<button>").button({icons: {
            primary: "ui-icon-arrowthickstop-1-w"
            },text:false, label:top.HR("Move to Start")})
            .click(function(){ __timelineMoveTo("start"); })
            .appendTo(toolbar);
        $("<button>").button({icons: {
            primary: "ui-icon-arrowthickstop-1-e"
            },text:false, label:top.HR("Move to End")})
            .click(function(){ __timelineMoveTo("end"); })
            .appendTo(toolbar);
            
            
            
        var menu_label_settings = $('<ul><li id="tlm0"><a href="#"><span/>Full label</a></li>'
                        +'<li id="tlm1"><a href="#"><span class="ui-icon ui-icon-check"/>Truncate to bar</a></li>'
                        +'<li id="tlm2"><a href="#"><span/>Fixed length</a></li>'
                        +'<li id="tlm3"><a href="#"><span/>Hide labels</a></li></ul>') 
        .zIndex(9999)
        .addClass('menu-or-popup')
        .css({'position':   'absolute', 'padding':'2px'})
        .appendTo( $('body') )
        .menu({
            select: function( event, ui ) {
                
                var contents = $(".vis-item-content");
                var spinner = $("#timeline_spinner");
                
                menu_label_settings.find('span').removeClass('ui-icon ui-icon-check');
                ui.item.find('span').addClass('ui-icon ui-icon-check');
                
                var mode =  Number(ui.item.attr('id').substr(3));
                
                if(mode==0){
                    $.each(contents, function(i,item){item.style.width = 'auto';});//.css({'width':''});                    
                }else if(mode==2){
                    contents.css({'width': spinner.spinner('value')+'em'});                    
                }
                
                vis_timeline.setOptions({'label_in_bar':(mode==1)}); //, 'label_width': ((mode==0)?'0':'10em') });
                vis_timeline.setOptions({'margin':1});
                
                if(mode==2){
                    spinner.show();
                }else{
                    spinner.hide();
                }
                
                if(mode==3){
                    contents.find("span").hide();
                }else{
                    contents.find("span").show();
                }

                vis_timeline.redraw();

                
        }})
        .hide();
            
        $("<button>").button({icons: {
            primary: "ui-icon-tag",
            secondary: "ui-icon-triangle-1-s"            
            },text:false, label:top.HR("Label settings")})
            .click(function(){  
                $('.menu-or-popup').hide(); //hide other

                var menu = $( menu_label_settings )
                .show()
                .position({my: "right top", at: "right bottom", of: this });
                $( document ).one( "click", function() { menu.hide(); });
                return false;
                
            })
            .appendTo(toolbar);
            
        var spinner = $( "<input>", {id:"timeline_spinner", value:10} ).appendTo(toolbar);
        $("#timeline_spinner").spinner({
              value: 10,  
              spin: function( event, ui ) {
                if ( ui.value > 100 ) {
                  $( this ).spinner( "value", 100 );
                  return false;
                } else if ( ui.value < 5 ) {
                  $( this ).spinner( "value", 5 );
                  return false;
                } else {
                    $(".vis-item-content").css({'width': ui.value+'em'});                    
                    
                }
              }
            }).css('width','2em').hide();            
            
        /*   
        $("<input id='btn_timeline_labels' type='checkbox' checked>").appendTo(toolbar);
        $("<label for='btn_timeline_labels'>Show labels2</label>").appendTo(toolbar);
        $("#btn_timeline_labels").button({icons: {
            primary: "ui-icon-tag"
            },text:false, label:top.HR("Show labels")})
            .click(function(){ __timelineShowLabels(); })
            .appendTo(toolbar);
        */
        
    }
    
    //init visjs timeline
    function _loadVisTimeline( mapdata ){
                
        if(mapdata && mapdata.timeline){
        
        var items = new vis.DataSet( mapdata.timeline.items ); //options.items );
        
        if(vis_timeline==null){
            var ele = document.getElementById(timelinediv_id);
            // Configuration for the Timeline
            var options = {dataAttributes: ['id'], 
                           orientation:'both', //scale on top and bottom
                           selectable:true, multiselect:true, 
                           zoomMax:31536000000*500000,
                           margin:1,
                           minHeight: $(ele).height()
                           };
                        //31536000000 - year
            // Create a Timeline
            vis_timeline = new vis.Timeline(ele, items, options);        
            //on select listener
            vis_timeline.on('select', function(params){
                $( document ).bubble( "option", "content", "" );
                
                selection = params.items;
                var e = params.event;
                e.cancelBubble = true;
                if (e.stopPropagation) e.stopPropagation();
                e.preventDefault();
                _showSelection(true); //show selction on map
                _onSelectEventListener.call(that, selection); //trigger global selection event
            });
            
            //init timeline toolbar
            _initVisTimelineToolbar();
            
        }else{
            vis_timeline.setItems(items);
            //vis_timeline.redraw();
            vis_timeline.fit(); //short way
        }
        
        //if(mapdata.timeenabled>0)
        //    vis_timeline.setVisibleChartRange(mapdata.timeline.start, mapdata.timeline.end);
        }
    }
    
    
    /*
    * Load timemap datasets 
    * @see hRecordSet.toTimemap()
    * 
    * @param _mapdata
    
    function _load_OLD(_mapdata, _selection, __startup_mapdocument, __onSelectEventListener, _callback){

            function __onDataLoaded(_tmap, _notfirst){
                tmap = _tmap;
                
                var dataset = tmap.datasets.main;
                dataset.each(function(item){
        //art 020215             
                        item.opts.openInfoWindow = _onItemSelection;
                });
                
                if(!_isEmptyDataset(_mapdata)){
                    dataset.mapenabled = _mapdata[0].mapenabled;
                    dataset.timeenabled = _mapdata[0].timeenabled;
                }else{
                    dataset.mapenabled = 0;
                    dataset.timeenabled = 0;
                }
                
                //asign tooltips for all 
                
                if(!_notfirst){
                    //first map initialization
                    
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

                    var nativemap = tmap.getNativeMap();
                    nativemap.setOptions(mapOptions);

                    loadMapDocuments(nativemap, _startup_mapdocument); //loading the list of map documents see map_overlay.js
                    
                    _initDrawListeners();
                    
                    if(dataset.mapenabled>0){
                        tmap.datasets.main.hide();
                        tmap.datasets.main.show();
                    }else if (!_startup_mapdocument) { //zoom to whole world
                        var swBound = new google.maps.LatLng(-40, -120);
                        var neBound = new google.maps.LatLng(70, 120);
                        var bounds = new google.maps.LatLngBounds(swBound, neBound); 
                        nativemap.fitBounds(bounds);
                    }

                    
                }
                
                if(_callback){
                    _callback.call();
                }else{
                    _updateLayout();
                    //highlight selection
                    _showSelection(false);
                }
                
            }// __onDataLoaded       
        
        $( document ).bubble('closeAll');  //close all popups      
        
        //asign 2 global for mapping - on select listener and startup map document
        if(__onSelectEventListener) _onSelectEventListener = __onSelectEventListener;
        _startup_mapdocument = __startup_mapdocument;
        
        //mapdata = _mapdata || [];
        selection = _selection || [];
        
        if(window["Timeline"]){
            var tl_theme = Timeline.ClassicTheme.create();
            tl_theme.autoWidth = true;
            tl_theme.mouseWheel = "default";//"zoom";
            tl_theme.event.track.offset = 1.4;
        }

        //timemap is already inited - _mapdata (search result) is loaded to main dataset
        if(tmap && tmap.datasets && tmap.datasets.main){ 
            
                //tmap.deleteDataset("main");
                //var dataset = tmap.createDataset("main");
                var dataset = tmap.datasets.main;
                dataset.clear();
                
                if(!_isEmptyDataset(_mapdata)){
                    dataset.loadItems(_mapdata[0].options.items);
                    dataset.hide();
                    dataset.show();
                    _loadVisTimeline(_mapdata[0]);
                }
                
                
                __onDataLoaded(tmap, true);
                return; 
        }
        
        //var hasItems = _updateLayout();
        
        
        //art 020215  
        // NOT USED - since we use _showPopupInfo   
        // TimeMapItem.openInfoWindowBasic = _showPopupInfo;
      
        //there is bug in timeline - it looks _history_.html in wrong place
        if(window["Timeline"]){
            SimileAjax.History.enabled = false;
        }
        
        // add fake/empty datasets for further use in mapdocument (it is not possible to add datasets dynamically)
        if(!_mapdata){
            _mapdata = [{id: "main", type: "basic", options: { items: [] }}];
        }
        
        //_mapdata.push({id: "dyn1", type: "basic", options: { items: [] }});
        
        
        // Initialize TimeMap
        tmap = TimeMap.init({
            mapId: mapdiv_id, // Id of gmap div element (required)
            timelineId: null, //timelinediv_id, // Id of timeline div element (required)
            datasets: _mapdata, 
            
            options: {
                mapZoom: defaultZoom,
                theme: customTheme,
                //showMapCtrl: true,
                //ART 201302 useMarkerCluster: (mapdata.count_mapobjects<300),
                // TODO onlyTimeline: false, //TODO (mapdata.count_mapobjects<1),
                                                        
                //mapZoom: 1, //default zoom
                //centerMapOnItems: bounds ? false : true,
                //showMapCtrl: false,
                //showMapTypeCtrl: false,
                //mapZoom: RelBrowser.Mapping.defaultZoom,
                //centerMapOnItems: bounds ? false : true,
                //mapType: M.customMapTypes[0] || mxn.Mapstraction.ROAD,
                //!!! theme: TimeMapTheme.create("blue", { eventIconPath: RelBrowser.baseURL + "timemap.2.0/images/" }),
                //openInfoWindow: mini ? function () { return false; } : RelBrowser.Mapping.openInfoWindowHandler
                //openInfoWindow: RelBrowser.Mapping.openInfoWindowHandler,
                
                eventIconPath: top.HAPI4.iconBaseURL //basePath + "ext/timemap.js/2.0.1/images/"
            }
            // ART 14072015   - remove simile timeline
            //, bandInfo: [
            //    {
            //       theme: tl_theme,
            //        align: "Top",
            //        showEventText: true,
            //        intervalUnit: timeZoomSteps[timeZoomSteps.length - 1].unit,
            //       intervalPixels: timeZoomSteps[timeZoomSteps.length - 1].pixelsPerInterval,
            //       zoomIndex: timeZoomSteps.length - 1,
            //        zoomSteps: timeZoomSteps,
            //        trackHeight: 2.3,
            //        trackGap:    0.2,
            //        width: "100%"
            //    }
            //]
            , dataLoadedFunction: __onDataLoaded
            }, tmap);

            
        _loadVisTimeline(_mapdata[0]);
        
        if(true){ //}(hasItems && (_mapdata[0].mapenabled>0)){
            
        }
            
    }
    */

    function _load(_mapdata, _selection, __startup_mapdocument, __onSelectEventListener, _callback){

            function __onDataLoaded(_tmap, _notfirst){
                tmap = _tmap;
                
                if(!_notfirst){
                    //first map initialization
                    
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

                    var nativemap = tmap.getNativeMap();
                    nativemap.setOptions(mapOptions);

                    loadMapDocuments(nativemap, _startup_mapdocument); //loading the list of map documents see map_overlay.js
                    
                    _initDrawListeners();
                    
                    if(false && dataset.mapenabled>0){
                        tmap.datasets.main.hide();
                        tmap.datasets.main.show();
                    }else if (!_startup_mapdocument) { //zoom to whole world
                        var swBound = new google.maps.LatLng(-40, -120);
                        var neBound = new google.maps.LatLng(70, 120);
                        var bounds = new google.maps.LatLngBounds(swBound, neBound); 
                        nativemap.fitBounds(bounds);
                    }
                    
                    $("#map-settingup-message").hide();
                    $(".map-inited").show();
                }
                
                if(_callback){
                    _callback.call();
                }else{
                    _updateLayout();
                    //highlight selection
                    _showSelection(false);
                }
                
            }// __onDataLoaded       
        
        $( document ).bubble('closeAll');  //close all popups      
        
        //asign 2 global for mapping - on select listener and startup map document
        if(__onSelectEventListener) _onSelectEventListener = __onSelectEventListener;
        _startup_mapdocument = __startup_mapdocument;
        
        //mapdata = _mapdata || [];
        selection = _selection || [];
        
        //timemap is already inited
        if(tmap && tmap.datasets){ 
            __onDataLoaded(tmap, true);    
            return;
        }
        
        // add fake/empty datasets for further use in mapdocument (it is not possible to add datasets dynamically)
        if(!_mapdata){
            _mapdata = [{id: "main", type: "basic", options: { items: [] }}];
        }
        
        // Initialize TimeMap
        tmap = TimeMap.init({
            mapId: mapdiv_id, // Id of gmap div element (required)
            timelineId: null, //timelinediv_id, // Id of timeline div element (required)
            datasets: _mapdata, 
            
            options: {
                mapZoom: defaultZoom,
                theme: customTheme,
                eventIconPath: top.HAPI4.iconBaseURL //basePath + "ext/timemap.js/2.0.1/images/"
            }
            , dataLoadedFunction: __onDataLoaded
            }, tmap);
            
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
                drawingControlOptions: {
                    position: google.maps.ControlPosition.TOP_RIGHT, //LEFT_BOTTOM,    
                    drawingModes: [google.maps.drawing.OverlayType.POLYGON, google.maps.drawing.OverlayType.RECTANGLE]
                },
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
            
            //define custom tooltips
            
            $(tmap.getNativeMap().getDiv()).one('mouseover','img[src="https://maps.gstatic.com/mapfiles/drawing.png"]',function(e){

                $(e.delegateTarget).find('img[src="https://maps.gstatic.com/mapfiles/drawing.png"]').each(function(){
                  $(this).closest('div[title]').attr('title',function(){
                     switch(this.title){
                      case 'Stop drawing':
                        return 'Drag the map or select / get information about an object on the map';
                          break;
                      case 'Draw a rectangle':
                        return 'Select objects within a rectangle. Hold down Ctrl to add to current selection';
                          break;
                      case 'Draw a shape':
                        return 'Select objects within a polygon - double click to finish the polygon';
                          break;
                      default:return this.title;  
                     } 

                  });
                });
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
        //trigger global selection event - to highlight on other widgets
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
        var items_to_update = [];       //current item to be deleted
        var items_to_update_data = [];  // items to be added (replacement for previous)
        //var dataset = tmap.datasets.main;  //take main dataset
        
        if ( isreset || (selection && selection.length>0) ){
            
            tmap.each(function(dataset){   
            
                dataset.each(function(item){ //loop trough all items

                        var idx = selection ?selection.indexOf(item.opts.recid) :-1;
                        
                        var itemdata = {
                        datasetid: dataset.id,
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
                            itemdata.options.eventIconImage = item.opts.iconId + 's.png';   //it will have selected record (blue bg)
                            itemdata.options.icon = top.HAPI4.iconBaseURL + itemdata.options.eventIconImage;
                            itemdata.options.color = "#FF0000";
                            itemdata.options.lineColor = "#FF0000";

                            items_to_update_data.push(itemdata);
                            
                            //if(idx == selection.length-1) lastSelectedItem = item;
                            
                        }else{ //clear selection
                            //item.opts.theme
                            //item.changeTheme(customTheme, true); - dont work
                            var usual_icon = item.opts.iconId + 'm.png'; //it will have usual gray bg
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
                var keep_height = (tlband0.length>0)?tlband0.height():0;
                
                //dataset.hide();
                
                var newitem,i,affected_datasets = [];
                for (i=0;i<items_to_update.length;i++){
                        //items_to_update[i].clear();
                        var ds_id = items_to_update_data[i].datasetid;
                        
                        var dataset = tmap.datasets[ ds_id ];
                        
                        dataset.deleteItem(items_to_update[i]);
                        
                        //dataset.items.push(items_to_update[i]);
                        newitem = dataset.loadItem(items_to_update_data[i]);
                        
                        //art 020215 
                        newitem.opts.openInfoWindow = _onItemSelection;
                        
                        if(lastRecID==newitem.opts.recid){
                            lastSelectedItem = newitem;
                        }
                        
                        if(affected_datasets.indexOf(ds_id)<0){
                            affected_datasets.push(ds_id);
                        }
                }
                //dataset.show();
                
                //hide and show the affected datasets - to apply changes
                for (i=0;i<affected_datasets.length;i++){
                    var dataset = tmap.datasets[ affected_datasets[i] ];
                    if(dataset.visible){
                        dataset.hide();
                        dataset.show();
                    }
                }
                
                //_zoomTimeLineToAll(); //
                //tmap.timeline.layout();
                if(tlband0.length>0)
                    tlband0.css('height', keep_height+'px');
            }
           
            //item.timeline.layout();
            
            //select items on timeline
            if(vis_timeline)
                vis_timeline.setSelection( selection );
            
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
        
        
            //close others bubbles
            $( document ).bubble( "closeAll" );
                    
        
            var item = this,
                html = item.getInfoHtml(),
                ds = item.dataset,
                placemark = item.placemark,
                show_bubble_on_map = false;
                
            //find recordset for selected item
            item.dataset.id    
                
                
            if(true){ //top.HAPI4.currentRecordset){
                
                /* 
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
                if(fld('rec_ThumbnailURL')){                             //class="timeline-event-bubble-image" 
                    html_thumb = '<img src="'+fld('rec_ThumbnailURL')+'" style="float:left;padding-bottom:5px;padding-right:5px;">'; //'<div class="recTypeThumb" style="background-image: url(&quot;'+ fld('rec_ThumbnailURL') + '&quot;);opacity:1"></div>'
                }else{
                    html_thumb =  ''; //top.HAPI4.iconBaseURL + 'thumb/th_' + rectypeID + '.png';
                } */
                
            show_bubble_on_map = (item.getType() != "" && placemark.api!=null);
            var ed_html =  '<div style="width:100%;text-align:right;'+(show_bubble_on_map?'':'padding-right:10px;')+'">';
                
                var recID       = item.opts.recid,
                    rectypeID   = item.opts.rectype,
                    bkm_ID      = item.opts.bkmid,
                    recTitle    = top.HEURIST4.util.htmlEscape(item.opts.title),                                              
                    startDate   = item.opts.start,
                    endDate     = item.opts.end,
                    description = top.HEURIST4.util.htmlEscape(item.opts.description),
                    recURL      = item.opts.URL,
                    html_thumb  = item.opts.thumb || '';

            
            if(item.opts.info){
                
                html =  ed_html + item.opts.info + '</div>';
                
            }else{
                
                ed_html =  //'<div style="width:100%;text-align:right;'+(show_bubble_on_map?'':'padding-right:10px;')+'">'  // style="width:100%"
                ed_html
            +   '<div style="display:inline-block;">'
            +     '<img src="'+top.HAPI4.basePath+'assets/16x16.gif'+'" class="rt-icon" style="background-image: url(&quot;'+top.HAPI4.iconBaseURL + rectypeID+'.png&quot;);">'
            +     '<img src="'+top.HAPI4.basePath+'assets/13x13.gif" class="'+(bkm_ID?'bookmarked':'unbookmarked')+'">'                
            +   '</div>'
            +  ((top.HAPI4.currentUser.ugr_ID>0)?
                '<div title="Click to edit record" style="float:right;height:16px;width:16px;" id="btnEditRecordFromBooble" >'
              /*  '<div title="Click to edit record" style="float:right;height:16px;width:16px;" id="btnEditRecordFromBooble" '
            + 'class="logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false">'
            //+ ' onclick={event.preventDefault(); window.open("'+(top.HAPI4.basePathOld+'edit/editRecord.html?db='+top.HAPI4.database+'&recID='+recID)+'", "_new");} >'
            +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'*/
            +   '</div>':'')            
            + '</div>';
                
            html =
ed_html +             
'<div style="min-width:190px;height:130px;overflow-y:auto;">'+  // border:solid red 1px; 
'<div style="font-weight:bold;width:100%;padding-bottom:4px;">'+(recURL ?("<a href='"+recURL+"' target='_blank'>"+ recTitle + "</a>") :recTitle)+'</div>'+  //class="timeline-event-bubble-title"
'<div class="popup_body">'+ html_thumb + description +'</div>'+
((startDate)?'<div class="timeline-event-bubble-time" style="width:170px;white-space: nowrap;overflow: hidden;text-overflow: ellipsis;">'+temporalToHumanReadableString(startDate)+'</div>':'')+
((endDate)?'<div class="timeline-event-bubble-time"  style="width:170px;white-space: nowrap;overflow: hidden;text-overflow: ellipsis;">'+temporalToHumanReadableString(endDate)+'</div>':'')+
'</div>';

            }
                
                /*
.truncate {
  width: 250px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
*/                
                
            }
                
            var marker = null;
            
            // scroll timeline if necessary
            if (!item.onVisibleTimeline()) {    //old
                ds.timemap.scrollToDate(item.getStart());
            }
            if(vis_timeline && item.opts.start){
                var stime = _getUnixTs(item.opts.recid, 'start');
                var range = vis_timeline.getWindow();
                if(stime<range.start || stime>range.end){
                        vis_timeline.moveTo(stime);
                }
                var ele = $("#"+timelinediv_id);
                marker = ele.find("[data-id="+item.opts.recid +"]");
                //horizontal scroll
                if(marker) ele.scrollTop( marker.position().top );
            }
            
            // open window on MAP
            if (show_bubble_on_map) 
            {
                
                if (item.getType() == "marker") {
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
                    item.map.openBubble(item.getInfoPoint(), html);
                    item.map.tmBubbleItem = item;
                }
                
            } else {
                // open window on TIMELINE - replacement native Timeline bubble with our own implementation
                if(vis_timeline && marker){
                    
                    $( document ).bubble( "option", "content", html );
                    
                    //marker.scrollIntoView();
                    setTimeout(function(){ $( marker ).click();}, 500);
                    
                }else if(item.event){    //reference to Simile timeline event
                    
                    var painter = item.timeline.getBand(0).getEventPainter();
                    var marker = painter._eventIdToElmt[item.event.getID()]; //find marker div by internal ID
                    
                    //@todo - need to horizonatal scroll to show marker in viewport
                    
                    
                    
                    //change content 
                    $( document ).bubble( "option", "content", html );
                    
                    /*$( document ).bubble({
                            items: ".timeline-event-icon",
                            content:html
                    });*/
                    //show
                    $( marker ).click();
                    
                }else{
                    //neither map nor time data
                    //show on map center                  
                    // item.map.openBubble(item.getInfoPoint(), html);
                    // item.map.tmBubbleItem = item;
                }
            }
            
            if(top.HAPI4.currentUser.ugr_ID>0){
                $("#btnEditRecordFromBooble")
                    .button({icons: {
                        primary: "ui-icon-pencil"
                        }, text:false})
                     .click(function( event ) {
                event.preventDefault();
                //window.open(top.HAPI4.basePath + "php/recedit.php?db="+top.HAPI4.database+"&q=ids:"+recID, "_blank");
                window.open(top.HAPI4.basePathOld + "records/edit/editRecord.html?db="+top.HAPI4.database+"&recID="+recID, "_new");
                    });
            }

    }
    
    function _renderTimelineZoom(){
        
        return; //ART 14072015
        
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
            if(keepMinMaxDate){
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
        
        return; //ART 14072015
        
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
        
        //fix bug with height of band
        _timeLineFixHeightBug();
    }

    function _updateTimeline(){
          _renderTimelineZoom();
          keppTimelineIconBottomPos = -1;
          _zoomTimeLineToAll();
    }

    function _timeLineFixHeightBug(){
        return; //ART 14072015
                
                //fix bug in timeline-2.3.0 - adjust height of timeline-band-0
                var timeline = $("#"+timelinediv_id);
                var tlband0 = timeline.find("#timeline-band-0");
                var highest = 0;
                
                if(keppTimelineIconBottomPos>=0){
                    highest = keppTimelineIconBottomPos;
                }else{
                    var icons = tlband0.find('.timeline-band-layer-inner').find('.timeline-event-icon');
                
                    //find the most bottom icon
                    var ids = icons.map(function() {
                        return $(this).position().top; //parseInt(, 10);
                        }).get();
                
                    highest =  Math.max.apply(Math, ids) + 26;
                }
                
                highest = Math.max(timeline.height(), highest);
                tlband0.css('height', highest+'px');
                
    }
    
    
    
    

    /**
    *  Keeps timeline zoom 
    */
    function _onWinResize(){

        if(tmap && keepMinDate && keepMinDate && tmap.timeline){
            keepMinMaxDate = false;
            setTimeout(function (){

                var band = tmap.timeline.getBand(0);
                band.setMinVisibleDate(keepMinDate);
                band.setMaxVisibleDate(keepMaxDate);
                tmap.timeline.layout();

                //fix bug with height of band
                _timeLineFixHeightBug();
                
                keepMinMaxDate = true;
                }, 1000);
        }
    }
    
    function _printMap() {
        
          //if(!gmap) return;
          //var map = gmap;          
          
          if(!tmap) return;

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

        load: function(_mapdata, _selection, _startup_mapdocument, _onSelectEventListener, _callback){
            _load(_mapdata, _selection, _startup_mapdocument, _onSelectEventListener, _callback);
        },
        
        // mapdata - recordset converted to timemap dataset
        addDataset: function(_mapdata){
            return _addDataset(_mapdata);
        },
        
        deleteDataset: function(dataset_name){
            _deleteDataset(dataset_name);
        },
        
        showDataset: function(dataset_name, is_show){
            _showDataset(dataset_name, is_show);
        },
        
        showSelection: function(_selection){
             selection = _selection || [];
             _showSelection( true );
        },
        
        // add layer to map 
        addQueryLayer: function(params){
            addQueryLayer(params, -1);    //see map_overlay.js
        },

        // add layer to map 
        addRecordsetLayer: function(params){
            addRecordsetLayer(params, -1);    //see map_overlay.js
        },
        
        loadMapDocumentById: function(recId){
            //_loadMapDocumentById(recId);
            var mapdocs = $("#map-doc-select");
            mapdocs.val(recId).change();
            
        },
        
        
        onWinResize: function(){
            _onWinResize();
            if(tmap && tmap.map){ //fix google map bug
                tmap.map.resizeTo(0,0)
            }
        },

        printMap: function(){
             _printMap();
        },
        
        setTimelineMinheight: function(){
            if(vis_timeline){
                  vis_timeline.setOptions( {minHeight: $("#"+timelinediv_id).height()} ); 
            }
        }
    }

    _init(_map, _timeline, _basePath, _mylayout);
    return that;  //returns object
}
