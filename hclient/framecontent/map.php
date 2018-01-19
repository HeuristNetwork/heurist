<?php

/**
* Mapping page. It can be loaded in app_timemap widget or launched as standalone page
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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

//&callback=initMap" async defer  for gmap
//<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?libraries=drawing"></script>
//
require_once(dirname(__FILE__)."/initPage.php");
?>
<script type="text/javascript" src="<?php echo PDIR;?>ext/layout/jquery.layout-latest.js"></script>

<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDtYPxWrA7CP50Gr9LKu_2F08M6eI8cVjk&libraries=drawing,geometry"></script>
<script src="https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/markerclusterer.js"></script>
<!-- Timemap -->
<!-- <script type="text/javascript">Timeline_urlPrefix = RelBrowser.baseURL+"js/timemap.js/2.0.1/lib/";</script -->
  
<script type="text/javascript" src="<?php echo PDIR;?>ext/timemap.js/2.0.1/lib/mxn/mxn.js?(googlev3)"></script>
<!-- script type="text/javascript" src="<?php echo PDIR;?>ext/timemap.js/2.0.1/lib/timeline-2.3.0.js"></script -->

<script type="text/javascript" src="<?php echo PDIR;?>common/js/temporalObjectLibrary.js"></script>
<!-- timeline -->

<!--
// WARNING: CHANGES MADE TO vis.js
// These changes are not in our repository
// line:285 remove margin for item's label
// line:345,15753, 15860, 16108 correct orientation for bottom order
// line: 13607 getDataRangeHeurist function
// line: 13594 catch exception of datetime convertation
//
-->
<script type="text/javascript" src="<?php echo PDIR;?>ext/vis/dist/vis.js"></script>
<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>ext/vis/dist/vis.css" />

<script type="text/javascript" src="<?php echo PDIR;?>ext/timemap.js/2.0.1/src/timemap2.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>ext/timemap.js/2.0.1/src/param.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>ext/timemap.js/2.0.1/src/loaders/xml.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>ext/timemap.js/2.0.1/src/loaders/kml.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>ext/timemap.js/2.0.1/src/manipulation2.js"></script>

<!-- Shape file converting -->
<script type="text/javascript" src="<?php echo PDIR;?>ext/shapefile/stream.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>ext/shapefile/shapefile.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>ext/shapefile/dbf.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>ext/js/evol.colorpicker.js" charset="utf-8"></script>
<link href="<?php echo PDIR;?>ext/js/evol.colorpicker.css" rel="stylesheet" type="text/css">

<!-- Mapping -->
<script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/map.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/map_overlay.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/map_bubble.js"></script>


<style>
    .ui-map-document { background-image: url('<?php echo PDIR;?>hclient/assets/mapdocument.png') !important;}
    .ui-map-layer { background-image: url('<?php echo PDIR;?>hclient/assets/maplayer.png') !important;}

    /*   
    .vis-item-overflow{
    position:absolute;
    }
    .vis-item-overflow .vis-item{
    left:0px;
    top:0px;
    height:100%;
    border:none;
    }
    .vis-item-overflow .vis-item-content{
    position:relative;
    left: 4px;
    }
    .vis-item-overflow .vis-selected{
    z-index:2;
    background-color:#bee4f8 !important;
    border:none;
    }
    .vis-item.vis-selected{
    background-color:#bee4f8 !important;
    }
    */

    .vis-panel.vis-left, .vis-time-axis.vis-foreground{
        background-color:#DDDDDD;
    }
    .vis-item-content{
        width: 10em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .vis-item-bbox{
        background-color:rgb(142, 169, 185);
    }
</style>

<!-- Initializing -->

<script type="text/javascript">

    var mapping, menu_datasets, btn_datasets;
    
    // Callback function on page initialization - see initPage.php
    function onPageInit(success){

        if(!success) return;
        
        var lt = window.hWin.HAPI4.sysinfo['layout'];
        if(lt=='boro'){
                $("head").append($('<link rel="stylesheet" type="text/css" href="'
                    +window.hWin.HAPI4.baseURL+'hclient/widgets/boro/beyond1914.css?t='+(new Date().getTime())+'">'));
        }        

        // Layout options
        var layout_opts =  {
            applyDefaultStyles: true,
            togglerContent_open:    '<div class="ui-icon"></div>',
            togglerContent_closed:  '<div class="ui-icon"></div>',
            onresize_end: function(){
                //global console.log('resize end');
            }
            
        };

        // Setting layout
        layout_opts.center__minHeight = 30;
        layout_opts.center__minWidth = 200;
        layout_opts.north__size = 30;
        layout_opts.north__spacing_open = 0;
        var th = Math.floor($('#mapping').height*0.2);
        layout_opts.south__size = th>200?200:th;
        layout_opts.south__spacing_open = 7;
        layout_opts.south__spacing_closed = 7;
        layout_opts.south__onresize_end = function() {
            if(mapping) mapping.setTimelineMinheight();
            //console.log('timeline resize end');
            _adjustLegendHeight();
        };

        var _options = {};

        if(window.hWin.HEURIST4.util.getUrlParameter('notimeline', location.search)){
            layout_opts.south__size = 0;
            layout_opts.south__spacing_open = 0;
            layout_opts.south__spacing_closed = 0;
        }else if(window.hWin.HEURIST4.util.getUrlParameter('nomap', location.search)){
            _options['mapVisible'] = false;
            layout_opts.center__minHeight = 0;
            layout_opts.south__spacing_open = 0;
        }
        if(window.hWin.HEURIST4.util.getUrlParameter('noheader', location.search) || 
           window.hWin.HEURIST4.util.getUrlParameter('header', location.search)=='off'){
            layout_opts.north__size = 0;
        }
        if(window.hWin.HEURIST4.util.getUrlParameter('legend', location.search)=='off'){
            _options['legendVisible'] = false;
        }

        var mylayout = $('#mapping').layout(layout_opts);
        
        // Mapping data
        var mapdata = [];
        mapping = new hMapping("map", "timeline", _options, mylayout);

        
        var q = window.hWin.HEURIST4.util.getUrlParameter('q', location.search);
        
        //t:26 f:85:3313  f:1:building
        // Perform database query if possible (for standalone mode - when map.php is separate page)
        if( !window.hWin.HEURIST4.util.isempty(q) )
        {
            var rules = window.hWin.HEURIST4.util.getUrlParameter('rules', location.search);
            
            if(!window.hWin.HEURIST4.util.isempty(rules)){
                try{
                    rules = JSON.parse(rules);
                }catch(ex){
                    rules = null;    
                }
            }else{
                rules = null;
            }
            
            window.hWin.HAPI4.RecordMgr.search({q: q, rules:rules, w: "a", detail:(rules?'detail':'timemap'), l:3000},
                function(response){
                    if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                        //console.log("onMapInit response");
                        //console.log(response);

                        // Show info on map    @todo reimplement as map init callback IMPORTANT!!!!
                        var recset = new hRecordSet(response.data);
                       
                        //var mapdataset = recset.toTimemap();
                        //mapping.load([mapdataset]);
                        
                        mapping.load( null, //mapdataset,
                            null,  //array of record ids                                               
                            null,    //map document on load
                            function(selected){  //callback if something selected on map
                            },
                            function(){ //callback function on native map init completion
                                var params = {id:'main', recordset:recset, title:'Current query' };
                                mapping.map_control.addRecordsetLayer(params);
                            }
                        );                        
                                    

                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                }
            );
        } else {
            var mapdocument = window.hWin.HEURIST4.util.getUrlParameter('mapdocument', location.search);
            if(mapdocument>0){
                mapping.load(null, null, mapdocument);//load with map document
            }else{
                mapping.load();//load empty map    
            }
            
        }

        //init popup for timeline  ART14072015
        $( document ).bubble({
            items: ".timeline-event-icon" //deprecated since
        });
        $( document ).bubble({
            items: ".vis-item,.vis-item-overflow"
        });

        $( window ).resize(function() {
            var w = $(this).width();
            if (w < 400) {
                $("#mapSelectorBtn").button({showLabel:false}).width(20);
            }else{
                $("#mapSelectorBtn").button({showLabel:true}).width(100);
            }
            
            mapping.onWinResize();
        });

        $( "#timeline" ).mousedown(function(){
            $( document ).bubble("closeAll");
        });

        //init buttons
        $("#mapToolbarDiv").css('background','none');

        $("#btnExportKML").button().click(exportKML);

        $("#btnPrint").button({showLabel:false, icon: "ui-icon-print"})
        .click(mapping.printMap);

        $("#btnEmbed").button({showLabel:false, icon: "ui-icon-globe-b"})
        .click(showEmbedDialog);

        $('#btn_help').button({icon: "ui-icon-help", showLabel:false}).on('click', 3, function(){
            var $helper = $("#helper");
            if($helper.dialog( "isOpen" )){
                $helper.dialog( "close" );
            }else{
                $helper.dialog( "open" );
            }
        });
        $( "#helper" ).load(window.hWin.HAPI4.baseURL+'context_help/mapping_overview.html #content');
        //$( "#helper" ).find('p').css('padding','10px');
        $( "#helper" ).dialog({
            autoOpen: false, //(window.hWin.HAPI4.get_prefs('help_on')=='1'),
            width:'90%', height:570,
            position: { my: "right top", at: "right bottom", of: $('#btn_help') },
            show: {
                effect: "slide",
                direction : 'right',
                duration: 1000
            },
            hide: {
                effect: "slide",
                direction : 'right',
                duration: 1000
            }
        });

        <?php
        if(true){ // defined('RT_MAP_LAYER') && RT_MAP_LAYER>0 && defined('RT_MAP_DOCUMENT') && RT_MAP_DOCUMENT>0){

            function checkRt($rt){
                global $rtDefines;
                if(defined($rt) && constant($rt)>0){
                    return constant($rt);
                }else{
                    if(@$rtDefines[$rt]){

                        switch ($rt) {
                            case "RT_TILED_IMAGE_SOURCE":
                                $msg = "Map image file (tiled)";
                                break;
                            case "RT_KML_SOURCE":
                                $msg = "KML";
                                break;
                            case "RT_SHP_SOURCE":
                                $msg = "Shapefile";
                                break;
                            case "RT_GEOTIFF_SOURCE":
                                $msg = "Map image file (non-tiled)";
                                break;
                            case "RT_QUERY_SOURCE":
                                $msg = "Mappable query";
                                break;
                            case "RT_MAP_DOCUMENT":
                                $msg = "Map Document";
                                break;
                            case "RT_MAP_LAYER":
                                $msg = "Heurist Map Layer";
                                break;
                        }

                        return "(".$msg.", Concept ".$rtDefines[$rt][0]."-".$rtDefines[$rt][1].")";
                    }else{
                        return "";
                    }
                }
            }


            $items = '';
            $items = $items.'<li rtid="'.checkRt('RT_KML_SOURCE').'"><a href="#">KML</a></li>';
            $items = $items.'<li rtid="'.checkRt('RT_SHP_SOURCE').'"><a href="#">SHP</a></li>';
            $items = $items.'<li rtid="'.checkRt('RT_GEOTIFF_SOURCE').'"><a href="#">GeoTiff</a></li>';
            $items = $items.'<li rtid="'.checkRt('RT_TILED_IMAGE_SOURCE').'"><a href="#">Tiled image</a></li>';
            $items = $items.'<li rtid="'.checkRt('RT_QUERY_SOURCE').'"><a href="#">Query layer</a></li>';
            ?>
            $("#btnMapRefresh").button({ showLabel:false, icon:"ui-icon-arrowrefresh-1-e" })
            .click( refreshMapDocument );
            $("#btnMapNew").button({ showLabel:false, icon: "ui-map-document" })
            .click( function(){ addNewRecord('<?=checkRt('RT_MAP_DOCUMENT')?>');} )
            .append('<span class="ui-icon ui-icon-plus" style="margin-left:0px;margin-top:-2px" />');
            $("#btnMapEdit").button({showLabel:false, icon: "ui-icon-pencil"})
            .click( mapEdit );
            $("#btnMapLayer").button({showLabel:false, icon: "ui-map-layer"})
            .click(function(){ addNewRecord('<?=checkRt('RT_MAP_LAYER')?>');})
            .append('<span class="ui-icon ui-icon-plus" style="margin-left:0px;margin-top:-2px" />');

            menu_datasets = $('<ul><?=$items?></ul>')
            .addClass('menu-or-popup')
            .css('position','absolute')
            .appendTo( $('body') )
            .menu({
                select: function( event, ui ) {
                    var mode = ui.item.attr('rtid');
                    addNewRecord(mode);
            }})
            .hide();

            
            function __drodown_mapDataSources(e) {
                $('.menu-or-popup').hide(); //hide other
                var $menu_layers = $( menu_datasets )
                .show()
                .position({my: "right top", at: "right bottom", of: btn_datasets });
                $( document ).one( "click", function() { $menu_layers.hide(); });
                return false;
            }
            
            btn_datasets = $("#btnMapDataSourceArrow").button({showLabel:false, icon: 'ui-icon-triangle-1-s'})
                .css({'padding':'0.4em', 'max-width':'12px'})
                .click(__drodown_mapDataSources);
            $("#btnMapDataSource").button({showLabel:false,icon: "ui-icon-bars"}).click(__drodown_mapDataSources);


            $("#mapToolbar").controlgroup();
            <?php } else { ?>
            $("#mapSelector").hide();
            $("#mapToolbar").hide();
            <?php
        }
        ?>


    }
    
    function _adjustLegendHeight() {
        
        setTimeout(function(){
            var legend = document.getElementById('map_legend');
            var ch = $("#map_legend .content").height()+65;

            var nt = parseInt($(legend).css('bottom'), 10);
            var mh = $('#map').height();
            
            var is_collapsed = ($(legend).find('#collapse').text() == "+");
            
            if(is_collapsed===true){
                $(legend).css('top', mh-nt-50);   
            }else{
            
            if(mh-nt-ch < 70){
                $(legend).css('top', 50);
            }else{
                $(legend).css('top', mh-nt-ch);        
            }
        
            }
            
//console.log('lh='+(mh-nt-ch)+'  mh='+mh+'  ch='+ch);            
            
            $(legend).css('bottom', nt);
            
        },500);
    }
    

    function showEmbedDialog(){

        var query = window.hWin.HEURIST4.util.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, false);
        query = query + ((query=='?')?'':'&') + 'db='+window.hWin.HAPI4.database;
        var url = window.hWin.HAPI4.baseURL+'hclient/framecontent/map.php' + query;

        //document.getElementById("linkTimeline").href = url;

        document.getElementById("code-textbox3").value = url;
        
        
        document.getElementById("code-textbox").value = '<iframe src=\'' + url +
        '\' width="800" height="650" frameborder="0"></iframe>';

        //document.getElementById("linkKml").href = url_kml;

        //encode
        query = window.hWin.HEURIST4.util.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, true);
        query = query + ((query=='?')?'':'&') + 'db='+window.hWin.HAPI4.database;
        url = window.hWin.HAPI4.baseURL+'hclient/framecontent/map.php' + query;
        document.getElementById("code-textbox2").value = '<iframe src=\'' + url +
        '\' width="800" height="650" frameborder="0"></iframe>';
        
        window.hWin.HEURIST4.msg.showElementAsDialog({
            element: document.getElementById('map-embed-dialog'),
            height: 420,
            width: 700,
            title: window.hWin.HR('Publish Map')
        });

        /*        
        var $dlg = $("#embed-dialog");
        $dlg.dialog({
            autoOpen: true,
            height: 320,
            width: 700,
            modal: true,
            resizable: false,
            title: window.hWin.HR('Publish Map')
        });
        */
    }

    function exportKML(){

        var query = window.hWin.HEURIST4.util.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, false);
        if(query=='?'){
            window.hWin.HEURIST4.msg.showMsgDlg("Define filter and apply to database");
        }else{
            query = query + '&a=1&depth=1&db='+window.hWin.HAPI4.database;
            var url_kml = window.hWin.HAPI4.baseURL+"export/xml/kml.php" + query;

            var win = window.open(url_kml, "_new");
        }
    }
    
    function refreshMapDocument(){
        var recID = $("#mapSelectorBtn").attr('mapdoc-selected');
        mapping.map_control.loadMapDocumentById(recID, true);
    }

    function mapEdit(){
        var recID = $("#mapSelectorBtn").attr('mapdoc-selected');
        if(recID>0){
            editRecord(parseInt(recID), window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT']);
        }
    }
    function editRecord(recID, rt){
            window.hWin.HEURIST4.ui.openRecordEdit(recID, null, 
                {new_record_params:{rt:rt},
                 selectOnSave:true,
                 onselect:function(event, data){
                    if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                        if(rt==window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT']){
                            var recordset = data.selection;
                            var record = recordset.getFirstRecord();
                            var recID = recordset.fld(record,'rec_ID');
                            //reload mapdocument list and load added mapdoc
                            mapping.map_control.loadMapDocuments(recID);
                        }
                    }
                 }});
    }
    function addNewRecord(rt){

        if(parseInt(rt)>0){
            editRecord(-1, rt);
        }else{
            window.hWin.HEURIST4.msg.showMsgDlg(
                "The required record type "+rt+" has not been defined.<br><br>"+
                "Please download from the HeuristReferenceSet database using Manage &gt; Structure &gt; Browse templates.",
                {'Show Mapping Help':function() {  $("#helper").dialog( "open" ); var $dlg = window.hWin.HEURIST4.msg.getMsgDlg(); $dlg.dialog( "close" ) },
                    'Close':function() { var $dlg = window.hWin.HEURIST4.msg.getMsgDlg(); $dlg.dialog( "close" ) }
                }
                , "Missing record type"
            );


        }
    }

</script>

</head>

<!-- HTML -->

<body>
    <div id="mapping" style="min-height:1px;height:100%; width:100%;cursor:progress">
        <!-- Map -->
        <div class="ui-layout-center">
            <div id="map" style="width:100%; height:100%">Mapping</div>
            <div id="map_empty_message" style="width:100%; height:100%;margin:7px;display: none;">There are no spatial objects to plot on map</div>
        </div>

        <!-- Toolbar -->
        <div class="ui-layout-north" id="mapToolbarDiv" style="display: block !important; height: 30px; z-index:999;">

            <span id="map-settingup-message" style="padding-left:1em;line-height:2em">
                Setting up map ...
            </span>

            <!-- Map document selector -->
            
            <div id="mapSelector" class="map-inited" style="float:left;">
                <label id="map-doc-select-lbl"><i>Map document:</i></label>
                <button id="mapSelectorBtn"></button> 
            </div>
            <div id="mapToolbar" class="map-inited" style="float:left;display:none">
                <button id="btnMapRefresh" xxxdisabled="disabled" title="Refresh/reload current Map Document">Refresh current map</button>
                <button id="btnMapEdit" xxxdisabled="disabled" title="Edit current Map Document record (Select the desired map in the dropdown)">Edit current map</button>
                <button id="btnMapNew" title="Create new Map Document - a record that describes map features and defines what layers will be visible (will be included)">New map document</button>
                <button id="btnMapLayer" title="Create new Map Layer - a record that describes map layer behaviour (visibility, color scheme) and refers to particular geodata source">New Map Layer</button>
                <button id="btnMapDataSource" title="Define new Map geodata source. It may be either raster (Tiled image, geoTiff) or vector (shp, kml) data">New Data Source</button>
                <button id="btnMapDataSourceArrow" title="Define new Map geodata source. It may be either raster (Tiled image, geoTiff) or vector (shp, kml) data">New Data Source</button>
            </div>

            <div style="position: absolute; right: 0px; top:0px;display:none" class="map-inited">
                <button id="btnPrint">Print</button>
                <button id="btnEmbed">Embed</button>
                <button id="btn_help">Help</button>
            </div>

            <!-- TODO: Explain, use or remove -->
            <!-- Menu -->
            <!--<select id="menu" style="position: fixed; right: 10">
            <option value="-1" selected="selected" disabled="disabled">Menu</option>
            <option>Google Map/Timeline</option>
            <option>Google Earth</option>
            <option>Embed Map Code</option> style="position: fixed; right: 40"
            </select>-->

            <!-- Legend overlay -->
            <div id="map_legend" style="background-color: rgba(200, 200, 200, 0.7); color:black; padding:8px; overflow-y:auto; display:none;">
                <div id="map_extents"  style="font-size: 0.9em;display:inline-block;visibility:hidden;padding-bottom:1em;">Zoom to:&nbsp;
                        <select id="selMapBookmarks" style="font-size:1.0em;"></select>
                </div>
                <div style="float:right;">
                    <span style="font-size: 1.25em">&nbsp;</span>
                    <span id="collapse" style="font-size: 1.25em; float:right; padding: 0px 5px; cursor: pointer">-</span>
                </div>
                <div class="content"></div>
            </div>
            <div id="map_limit_warning" style="border-radius:6px;background-color: rgb(172, 231, 255);font-weight:bold; color:red; padding:8px; display:none;">
            </div>
        </div>

        <!-- Timeline -->
        <div class="ui-layout-south">
            <div id="timeline" style="width:100%;height:100%;overflow-y:auto;"></div>
            <div id="timeline_toolbar" style="position:absolute;top:1;left:1;height:20px;"></div>
        </div>
    </div>
    <div id="map-embed-dialog" style="display:none">
        <p>Embed this Google Map (plus timeline) in your own web page:</p>
        <p style="padding:1em 0 1em 0;font-size:0.9em">Copy the following html code into your page where you want to place the map, or use the URL on its own. The map will be generated live from the database using the current search criteria whenever the map is loaded. Use the web-safe version if the readable version does not work</p>
        <label style="font-size:0.9em">Readable code:</label>
        <textarea id="code-textbox" onclick="select(); if (window.clipboardData) clipboardData.setData('Text', value);" style="border: 1px dotted gray; padding: 3px; margin: 2; font-family: times; width: 100%; height: 60px;" readonly=""></textarea>
        <label style="font-size:0.9em">Web-safe code:</label>
        <textarea id="code-textbox2" onclick="select(); if (window.clipboardData) clipboardData.setData('Text', value);" style="border: 1px dotted gray; padding: 3px; margin: 2; font-family: times; width: 100%; height: 40px;" readonly=""></textarea>
        <label style="font-size:0.9em">URL:</label>
        <textarea id="code-textbox3" onclick="select(); if (window.clipboardData) clipboardData.setData('Text', value);" style="border: 1px dotted gray; padding: 3px; margin: 2; font-family: times; width: 100%; height: 40px;" readonly=""></textarea>
        
        <p style="padding-top:1em">Note: records will only appear on the map if they include geographic objects. You may get an empty or sparsely populated map if the search results do not contain map data. Records must have public status - private records will not appear on the map, which could therefore be empty. </p>
        <p style="padding-top:1em"><button id="btnExportKML">Create KML for Google Earth</button></p>
    </div>

    <div id="helper" title="Mapping Overview">
    </div>

    <div id="layer-edit-dialog"  style="display:none" class="ui-heurist-bg-light">
        <fieldset>
            <div>
                <!-- What would you like to call<br>the new map layer -->
                <div class="header"><label for="layer_name">Name for map layer:</label></div>
                <input type="text" id="layer_name" class="text ui-widget-content ui-corner-all" />
            </div>
            <div>
                <div class="header"><label for="layer_color">Color:</label></div>
                <input id="layer_color"/>
            </div>
        </fieldset>
        <div class="messages"></div>
    </div>
</body>
</html>
