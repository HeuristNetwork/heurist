<?php

    /**
    * Standalone mapping page (for development purposes )
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

    // System manager
    require_once(dirname(__FILE__)."/../php/System.php");
    $system = new System();

    // Database check
    if(@$_REQUEST['db']){
        if(! $system->init(@$_REQUEST['db']) ){
            //@todo - redirect to error page
            print_r($system->getError(),true);
            exit();
        }
    }else{
        header('Location: /../php/databases.php');
        exit();
    }
?>

<html>
    <!-- HEAD -->
    <head>
        <title><?=HEURIST_TITLE ?></title>

        <link rel=icon href="../favicon.ico" type="image/x-icon">
        <link rel="shortcut icon" href="../favicon.ico" type="image/x-icon">

        <meta http-equiv="content-type" content="text/html; charset=utf-8">

        <link rel="stylesheet" type="text/css" href="../ext/font-awesome/css/font-awesome.min.css" />
        <!-- Styles
        <link rel="stylesheet" type="text/css" href="../ext/jquery-ui-1.10.2/themes/base/jquery-ui.css" /> -->
        <link rel="stylesheet" type="text/css" href="../style3.css" />

        <!-- jQuery -->
        <script type="text/javascript" src="../ext/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
        <script type="text/javascript" src="../ext/jquery-ui-1.10.2/ui/jquery-ui.js"></script>
        <script type="text/javascript" src="../ext/layout/jquery.layout-latest.js"></script>

        <!-- Timemap -->
        <!-- <script type="text/javascript">Timeline_urlPrefix = RelBrowser.baseURL+"js/timemap.js/2.0.1/lib/";</script -->
        <script type="text/javascript" src="../ext/timemap.js/2.0.1/lib/mxn/mxn.js?(googlev3)"></script>
        <!-- script type="text/javascript" src="../ext/timemap.js/2.0.1/lib/timeline-2.3.0.js"></script -->

        <script type="text/javascript" src="../h3/common/js/temporalObjectLibrary.js"></script>
        <script type="text/javascript" src="../ext/vis/dist/vis.js"></script>
        <link rel="stylesheet" type="text/css" href="../ext/vis/dist/vis.css" />
        
        <script type="text/javascript" src="../ext/timemap.js/2.0.1/src/timemap2.js"></script>
        <script type="text/javascript" src="../ext/timemap.js/2.0.1/src/param.js"></script>
        <script type="text/javascript" src="../ext/timemap.js/2.0.1/src/loaders/xml.js"></script>
        <script type="text/javascript" src="../ext/timemap.js/2.0.1/src/loaders/kml.js"></script>
        <script type="text/javascript" src="../ext/timemap.js/2.0.1/src/manipulation2.js"></script>

        <!-- Shape file converting -->
        <script type="text/javascript" src="../ext/shapefile/stream.js"></script>
        <script type="text/javascript" src="../ext/shapefile/shapefile.js"></script>
        <script type="text/javascript" src="../ext/shapefile/dbf.js"></script>

        <!-- Mapping -->
        <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?sensor=false&libraries=drawing"></script>
        <!-- <script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script> -->
        <script type="text/javascript" src="../js/mapping.js"></script>
        <script type="text/javascript" src="../js/map_overlay.js"></script>
        <script type="text/javascript" src="../js/bubble.js"></script>
        <script type="text/javascript" src="../js/recordset.js"></script>
        <script type="text/javascript" src="../js/utils.js"></script>
        <script type="text/javascript" src="../js/hapi.js"></script>


        <style>
            #helper p, #helper h2, #helper h4{
                padding:10px;
            }
            .ui-map-document { background-image: url('../assets/267.png') !important;}
            .ui-map-layer { background-image: url('../assets/268.png') !important;}

            .vis-item{
                background-color:lightgray;
            }
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
            .vis-time-axis.vis-foreground{
                background-color:#DDDDDD;
            }
            .vis-item-content{
                width: 10em;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        </style>

        <!-- Initializing -->

<?php
    if(defined('RT_MAP_DOCUMENT') && RT_MAP_DOCUMENT>0){
        //HEURIST_ICON_URL.RT_MAP_DOCUMENT.'.png'
        //HEURIST_ICON_URL.RT_MAP_LAYER.'.png'
    }
?>

        <script type="text/javascript">

            var mapping, menu_datasets, btn_datasets;

            // Standalone check
            $(document).ready(function() {
                if(!top.HAPI4){
                    // In case of standalone page
                    top.HAPI4 = new hAPI('<?=$_REQUEST['db']?>', onHapiInit);//, < ?=json_encode($system->getCurrentUser())? > );
                }else{
                    // Not standalone, use HAPI from parent window
                    onHapiInit(true);
                }
            });

            // Callback function on hAPI initialization
            function onHapiInit(success)
            {
                if(success) // Successfully initialized system
                {
                    var prefs = top.HAPI4.get_prefs();
                    if(!top.HR){
                        //loads localization
                        top.HR = top.HAPI4.setLocale(prefs['layout_language']);
                    }
                    if(prefs['layout_theme'] && !(prefs['layout_theme']=="heurist" || prefs['layout_theme']=="base")){
                        cssLink = $('<link rel="stylesheet" type="text/css" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.2/themes/'+
                            prefs['layout_theme']+'/jquery-ui.css" />');
                    }else{
                        //default BASE theme
                        cssLink = $('<link rel="stylesheet" type="text/css" href="../ext/jquery-ui-1.10.2/themes/'+prefs['layout_theme']+'/jquery-ui.css" />');
                    }
                    $("head").append(cssLink);

                    if(!top.HEURIST4.rectypes){
                        top.HAPI4.SystemMgr.get_defs({rectypes:'all', terms:'all', detailtypes:'all', mode:2}, function(response){
                            if(response.status == top.HAPI4.ResponseStatus.OK){
                                top.HEURIST4.rectypes = response.data.rectypes;
                                top.HEURIST4.terms = response.data.terms;
                                top.HEURIST4.detailtypes = response.data.detailtypes;

                                onMapInit();
                            }else{
                                top.HEURIST4.util.showMsgErr('Can not obtain database definitions');
                            }
                        });
                    }else{
                        onMapInit();
                    }

                }else{
                    top.HEURIST4.util.showMsgErr('Can not init HAPI');
                }
            }

            // Callback function on map initialization
            function onMapInit(){
                // Layout options
                var layout_opts =  {
                    applyDefaultStyles: true,
                    togglerContent_open:    '<div class="ui-icon"></div>',
                    togglerContent_closed:  '<div class="ui-icon"></div>'
                };

                // Setting layout
                layout_opts.center__minHeight = 100;
                layout_opts.center__minWidth = 200;
                layout_opts.north__size = 30;
                layout_opts.north__spacing_open = 0;
                var th = Math.floor($('#mapping').height*0.2);
                layout_opts.south__size = th>200?200:th;
                layout_opts.south__spacing_open = 7;
                layout_opts.south__spacing_closed = 7;

<?php if(@$_REQUEST['notimeline']){ ?>
                layout_opts.south__size = 0;
                layout_opts.south__spacing_open = 0;
                layout_opts.south__spacing_closed = 0;
<?php }
      if(@$_REQUEST['noheader']){ ?>
                layout_opts.north__size = 0;
<?php } ?>


                var mylayout = $('#mapping').layout(layout_opts);



                // Mapping data
                var mapdata = [];
                mapping = new hMapping("map", "timeline", top.HAPI4.basePath, mylayout);
                var q = '<?=@$_REQUEST['q']?$_REQUEST['q']:""?>';

                //t:26 f:85:3313  f:1:building
                // Perform database query if possible
                if( !top.HEURIST4.util.isempty(q) )
                {
                    top.HAPI4.RecordMgr.search({q: q, w: "all", f:"map", l:1000},
                        function(response){
                            if(response.status == top.HAPI4.ResponseStatus.OK){
                                console.log("onMapInit response");
                                console.log(response);

                                // Show info on map
                                var recset = new hRecordSet(response.data);
                                var mapdataset = recset.toTimemap();
                                mapping.load(mapdataset);

                            }else{
                                alert(response.message);
                            }
                        }
                    );
                }



                //init popup for timeline  ART14072015
                $( document ).bubble({
                            items: ".timeline-event-icon"
                });
                $( document ).bubble({
                            items: ".vis-item"
                });

                $( window ).resize(function() {
                            var w = $(this).width();
                            if (w < 400) {
                                $("#map-doc-select").hide();
                                $("#map-doc-select-lbl").hide();
                            }else{
                                $("#map-doc-select").show();
                                if (w < 490) {
                                    $("#map-doc-select-lbl").hide();
                                }else{
                                    $("#map-doc-select-lbl").show();
                                }
                            }
                    });

                $( "#timeline" ).mousedown(function(){
                    $( document ).bubble("closeAll");
                });

                //init buttons
                $("#mapToolbarDiv").css('background','none');

                $("#btnExportKML").button().click(exportKML);

                $("#btnPrint").button({text:false, icons: {
                            primary: "icon-print"
                 }})
                 .click(mapping.printMap);

                $("#btnEmbed").button({text:false, icons: {
                            primary: "icon-gear"
                 }})
                 .click(showEmbedDialog);

                $("#btnEmbed").button({text:false, icons: {
                            primary: "icon-gear"
                 }})
                 .click(showEmbedDialog);

                 $('#btn_help').button({icons: { primary: "ui-icon-help" }, text:false}).on('click', 3, function(){
                        var $helper = $("#helper");
                        if($helper.dialog( "isOpen" )){
                            $helper.dialog( "close" );
                        }else{
                            $helper.dialog( "open" );
                        }
                 });
                 $( "#helper" ).load(top.HAPI4.basePathOld+'context_help/mapping_overview.html #content');
                 //$( "#helper" ).find('p').css('padding','10px');
                 $( "#helper" ).dialog({
                    autoOpen: (top.HAPI4.get_prefs('help_on')=='1'), width:'90%', height:570,
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
                                case "RT_TILED_IMAGE_LAYER":
                                    $msg = "Map image file (tiled)";
                                    break;
                                case "RT_KML_LAYER":
                                    $msg = "KML";
                                    break;
                                case "RT_SHP_LAYER":
                                    $msg = "Shapefile";
                                    break;
                                case "RT_GEOTIFF_LAYER":
                                    $msg = "Map image file (non-tiled)";
                                    break;
                                case "RT_QUERY_LAYER":
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
                $items = $items.'<li rtid="'.checkRt('RT_KML_LAYER').'"><a href="#">KML</a></li>';
                $items = $items.'<li rtid="'.checkRt('RT_SHP_LAYER').'"><a href="#">SHP</a></li>';
                $items = $items.'<li rtid="'.checkRt('RT_GEOTIFF_LAYER').'"><a href="#">GeoTiff</a></li>';
                $items = $items.'<li rtid="'.checkRt('RT_TILED_IMAGE_LAYER').'"><a href="#">Tiled image</a></li>';
                $items = $items.'<li rtid="'.checkRt('RT_QUERY_LAYER').'"><a href="#">Query layer</a></li>';
?>
                $("#btnMapNew").button({ text:false,
                         icons: {primary: "ui-map-document" }})
                        .click( function(){ addNewRecord('<?=checkRt('RT_MAP_DOCUMENT')?>');} )
                        .append('<span class="ui-icon ui-icon-plus" style="margin-left:0px;margin-top:-2px" />');
                $("#btnMapEdit").button({text:false,
                        icons: {primary: "ui-icon-pencil"}})
                        .click(mapEdit);
                $("#btnMapLayer").button({text:false,
                        icons: {primary: "ui-map-layer"}})
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

                btn_datasets = $("#btnMapDataSource").button({text:false,
                        icons: {
                            primary: "icon-reorder",
                            secondary: "ui-icon-triangle-1-s"}});

                btn_datasets.click( function(e) {
                                $('.menu-or-popup').hide(); //hide other
                                var $menu_layers = $( menu_datasets )
                                .show()
                                .position({my: "right top", at: "right bottom", of: btn_datasets });
                                $( document ).one( "click", function() { $menu_layers.hide(); });
                                return false;
                                });


                 $("#mapToolbar").buttonset();
<?php } else { ?>
                 $("#mapSelector").hide();
                 $("#mapToolbar").hide();
<?php
     }
?>


            }

            function showEmbedDialog(){

                 var query = top.HEURIST4.util.composeHeuristQuery2(top.HEURIST4.current_query_request);
                 query = query + ((query=='?')?'':'&') + 'db='+top.HAPI4.database;
                 var url = top.HAPI4.basePath+'page/mapping.php' + query;

                 //document.getElementById("linkTimeline").href = url;

                 document.getElementById("code-textbox").value = '<iframe src="' + url +
                                             '" width="800" height="650" frameborder="0"></iframe>';

                 //document.getElementById("linkKml").href = url_kml;

                 var $dlg = $("#embed-dialog");

                 $dlg.dialog({
                    autoOpen: true,
                    height: 320,
                    width: 700,
                    modal: true,
                    resizable: false,
                    title: top.HR('Publish Map')
                 });
            }

            function exportKML(){

                 var query = top.HEURIST4.util.composeHeuristQuery2(top.HEURIST4.current_query_request);
                 if(query=='?'){
                     Hul.showMsgDlg("Define query and perform search");
                 }else{
                     query = query + '&a=1&depth=1&db='+top.HAPI4.database;
                     var url_kml = top.HAPI4.basePathOld+"export/xml/kml.php" + query;

                     var win = window.open(url_kml, "_new");
                 }
            }

            function mapEdit(){
                var recID = $("#map-doc-select").val();
                if(recID>0){
                    window.open(top.HAPI4.basePathOld + "records/edit/editRecord.html?db="+top.HAPI4.database+"&recID="+recID, "_new");
                }
            }
            function addNewRecord(rt){


                if(parseInt(rt)>0){
                    window.open(top.HAPI4.basePathOld + 'records/add/addRecord.php?addref=1&db='+top.HAPI4.database+'&rec_rectype='+rt);
                }else{
                    top.HEURIST4.util.showMsgDlg(
                        "The required record type "+rt+" has not been defined.<br><br>"+
                        "Please download from the H3ReferenceSet database using Database > Import Structure.",
                        {'Show Mapping Help':function() {  $("#helper").dialog( "open" ); var $dlg = top.HEURIST4.util.getMsgDlg(); $dlg.dialog( "close" ) },
                         'Close':function() { var $dlg = top.HEURIST4.util.getMsgDlg(); $dlg.dialog( "close" ) }
                        }
                        , "Missing record type"
                    );


                }
            }


        </script>

    </head>

    <!-- HTML -->
    <body>
        <div id="mapping" style="height:100%; width:100%;">
            <!-- Map -->
            <div class="ui-layout-center">
                <div id="map" style="width:100%; height:100%">Mapping</div>
            </div>

            <!-- Toolbar -->
            <div class="ui-layout-north" id="mapToolbarDiv" style="display: block !important; height: 30px important; z-index:999;">
                <!-- Map document selector -->
                <span id="mapSelector">
                <label id="map-doc-select-lbl"><i>Map document:</i></label>
                <select id="map-doc-select" class="text ui-widget-content ui-corner-all">
                    <option value="-1" selected="selected">none available</option>
                </select>
                </span>
                <span id="mapToolbar">
                <button id="btnMapEdit" disabled="disabled" title="Edit current Map Document record (Select the desired map in the dropdown)">Edit current map</button>
                <button id="btnMapNew" title="Create new Map Document - a record that describes map features and defines what layers will be visible (will be included)">New map document</button>
                <button id="btnMapLayer" title="Create new Map Layer - a record that describes map layer behaviour (visibility, color scheme) and refers to particular geodata source">New Map Layer</button>
                <button id="btnMapDataSource" title="Define new Map geodata source. It may be either raster (Tiled image, geoTiff) or vector (shp, kml) data">New Data Source</button>
                </span>

                <div style="position: absolute; right: 0px; top:0px" class="ui-buttonset">
                <button id="btnPrint">Print</button>
                <button id="btnEmbed">Embed</button>
                <button id="btn_help">Help</button>
                </div>

                <!-- Menu -->
                <!--<select id="menu" style="position: fixed; right: 10">
                    <option value="-1" selected="selected" disabled="disabled">Menu</option>
                    <option>Google Map/Timeline</option>
                    <option>Google Earth</option>
                    <option>Embed Map Code</option>         style="position: fixed; right: 40"
                </select>-->

                <!-- Legend -->
                <div id="legend" style="background-color: rgba(200, 200, 200, 0.7); color:black; padding:8px; display:none;">
                    <span style="font-size: 1.25em">Legend</span>
                    <span id="collapse" style="font-size: 1.25em; float:right; padding: 0px 5px; cursor: pointer">-</span>
                    <div class="content"></div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="ui-layout-south">
                <div id="timeline" style="width:100%;height:100%;overflow-y:auto;"></div>
                <div id="timeline_toolbar" style="position:absolute;top:1;left:1;height:20px;"></div>
            </div>
        </div>
        <div id="embed-dialog" style="display:none">
            <p>Embed this Google Map (plus timeline) in your own web page:</p>
            <p style="padding:1em 0 1em 0">Copy the following html code into your page where you want to place the map, or use the URL on its own. The map will be generated live from the database using the current search criteria whenever the map is loaded. </p>
            <textarea id="code-textbox" onclick="select(); if (window.clipboardData) clipboardData.setData('Text', value);" style="border: 1px dotted gray; padding: 3px; margin: 2; font-family: times; width: 100%; height: 60px;" readonly=""></textarea>
            <p style="padding-top:1em">Note: records will only appear on the map if they include geographic objects. You may get an empty or sparsely populated map if the search results do not contain map data. Records must have public status. </p>
            <p style="padding-top:1em"><button id="btnExportKML">Create KML for Google Earth</button></p>
        </div>

        <div id="helper" title="Mapping Overview">
        </div>
    </body>
</html>