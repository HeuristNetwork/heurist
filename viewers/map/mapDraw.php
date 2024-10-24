<?php

    /**
    * Map digitizing tool - LEAFLET based
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

define('PDIR','../../');//need for proper path to js and css
require_once dirname(__FILE__).'/../../hclient/framecontent/initPage.php';
?>

<link rel="stylesheet" href="<?php echo PDIR;?>external/leaflet.plugins/geocoder/Control.Geocoder.css" />
<?php
if(true || $_SERVER["SERVER_NAME"]=='localhost'||$_SERVER["SERVER_NAME"]=='127.0.0.1'){
?>
    <link rel="stylesheet" href="<?php echo PDIR;?>external/leaflet/leaflet.css"/>
    <script type="text/javascript" src="<?php echo PDIR;?>external/leaflet/leaflet.js"></script>
    <script src="<?php echo PDIR;?>external/leaflet.plugins/geocoder/Control.Geocoder.js"></script>
<?php
}else{
?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <!-- link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" /-->
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
<?php
}
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dom-to-image/2.6.0/dom-to-image.js" integrity="sha384-w7CXHFrOIXSmCXYnTQGa05l+TdEU+qxHtOaPqyTojic8o3wGz3rfRGuNNOKU0uDW" crossorigin="anonymous"></script>
<!-- leaflet plugins -->
<script type="text/javascript" src="<?php echo PDIR;?>external/leaflet.plugins/leaflet-iiif.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/leaflet.plugins/leaflet-providers.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/leaflet.plugins/bookmarks/Leaflet.Bookmarks.min.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/leaflet.plugins/draw/leaflet.draw-src.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/leaflet.plugins/leaflet.circle.topolygon-src.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/js/cheapRuler.js" charset="utf-8"></script>
<link type="text/css" rel="stylesheet" href="<?php echo PDIR;?>external/leaflet.plugins/bookmarks/leaflet.bookmarks.css">
<link type="text/css" rel="stylesheet" href="<?php echo PDIR;?>external/leaflet.plugins/draw/leaflet.draw.css">

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/accessTokens.php"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_color.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapping.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapManager.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapDocument.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapLayer2.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>external/js/geodesy-master/vector3d.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/js/geodesy-master/latlon-ellipsoidal.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/js/geodesy-master/utm.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/js/geodesy-master/dms.js"></script>

        <script type="text/javascript" src="mapDraw.js"></script>

        <!-- Initializing -->

        <script type="text/javascript">

            var mapping, is_map_inited=false, initial_wkt, tool_option, imageurl, imageOverlay=null,
                need_screenshot = false, is_geofilter=false,
                menu_datasets, btn_datasets, zoom_with_delay = true,
                sMsgDigizeSearch = 'Digitise search area as a rectangle or polygon';

            // Callback function on map initialization
            function onPageInit(success){

                if(!success) {return;}

                /* init helper (see utils.js)
                window.hWin.HEURIST4.ui.initHelper( $('#btn_help'),
                            'Mapping Drawing Overview',
                            window.hWin.HRes('mapping_drawing #content'));
                */

                handleApiReady();
            } //onPageInit

            function handleApiReady(){

                var layout_params = {};
                layout_params['notimeline'] = '1';
                layout_params['nocluster'] = '1'

                layout_params['controls'] = 'legend,bookmark,geocoder,draw';
                layout_params['legend'] = 'basemaps,mapdocs,off';

                initial_wkt = window.hWin.HEURIST4.util.getUrlParameter('wkt', location.search);

                mapping = $('#map_container').mapping({
                    element_map: '#map_digitizer',
                    layout_params: layout_params,
                    oninit: onMapInit,
                    ondraw_addstart: onMapDrawAdd,
                    ondraw_editstart: onMapDraw,
                    ondrawend:  onMapDraw,
                    drawMode: 'full' //(is_geofilter?'filter':'full')
                });

                //initialize buttons
                $('.save-button').button().on({click:getWktAndClose});

                $('#view-button').button().on('click', function(){
                       mapping.mapping('getSetMapBounds', true);
                });
                $('#style-button').button().on({click:function(){
                        mapping.mapping( 'drawSetStyle');
                }});
                $('#delete-all-button').button().on({click:function(){
                       mapping.mapping( 'drawClearAll' );
                }});
                $('.cancel-button').button().on({click:function(){
                       window.close();
                }});
                // paste geojson -> map
                $('#load-geometry-button').button().on('click', function(){

                    var titleYes = window.hWin.HR('Yes'),
                    titleNo = window.hWin.HR('No'),
                    buttons = {};

                    var $dlg;

                    buttons[titleYes] = function() {

                        let geodata = $dlg.find('#geodata_textarea').val();

                        if(!window.hWin.HEURIST4.util.isJSON(geodata)){

                            geodata = geodata.toUpperCase();// ensure captialised
                            geodata = geodata.replace(/\s\s+/g, ' ').trim();// remove double spacing
                            geodata = geodata.replace(/\s*\(\s*/g, '(');// remove space before+after left bracket
                            geodata = geodata.replace(/\s*\)/g, ')');// remove space before right bracket

                            if(geodata.indexOf('POINT') >= 0){
                                geodata = geodata.replace(/\,\s*/g, ' ');// replace comma | comma+space with a single space
                            }

                            $dlg.find('#geodata_textarea').val(geodata);
                        }

                        mapping.mapping( 'drawLoadGeometry', geodata);
                        $dlg.dialog( "close" );
                    };
                    buttons[titleNo] = function() {
                        $dlg.dialog( "close" );
                    };
                    $('#get-coordinates-helper').hide();
                    $('#set-coordinates-helper').show();
                    $('#geodata_textarea').css({top:'6em'});

                    $dlg = window.hWin.HEURIST4.msg.showElementAsDialog({window:top,
                        element: document.getElementById( "get-set-coordinates" ),
                        resizable:false,
                        width:690, height:400,
                        title:window.hWin.HR('Paste or upload geo data'),
                        buttons:buttons
                    });

                });

                $("#fix-x-coords").button().on('click', function(){

                });
                // load from map -> geojson
                $('#get-geometry-button').button().on('click', function(){

                    $('#get-coordinates-helper').show();
                    $('#set-coordinates-helper').hide();
                    $('#geodata_textarea').css({top:'4em'});

                    $('#get-coord-wkt').trigger('change');

                    var $dlg;

                    var titleYes = window.hWin.HR('[Add to Map]');
                    var button={};
                    button[titleYes] = function() {

                        let geodata = $dlg.find('#geodata_textarea').val();
                        if(!window.hWin.HEURIST4.util.isJSON(geodata)){

                            geodata = geodata.toUpperCase();// ensure captialised
                            geodata = geodata.replace(/\s\s+/g, ' ').trim();// remove double spacing
                            geodata = geodata.replace(/\s*\(\s*/g, '(');// remove space before+after left bracket
                            geodata = geodata.replace(/\s*\)/g, ')');// remove space before right bracket

                            if(geodata.indexOf('POINT') >= 0){
                                geodata = geodata.replace(/\,\s*/g, ' ');// replace comma | comma+space with a single space
                            }

                            $dlg.find('#geodata_textarea').val(geodata);
                        }

                        mapping.mapping( 'drawLoadGeometry', geodata);
                        $dlg.dialog( "close" );
                    };

                    $dlg = window.hWin.HEURIST4.msg.showElementAsDialog({window:top,
                        element: document.getElementById( "get-set-coordinates" ),
                        resizable: false,
                        width:690, height:400,
                        title:window.hWin.HR('Copy the result'),
                        buttons:button

                    });
                });

                $('input[name="get-coord-format"]').on({change:function(e){

                       var is_checked = $(e.target).is(':checked');
                       var el_name = $(e.target).attr('id');
                       var el_text = $(e.target).parents('#get-set-coordinates').find('#geodata_textarea');

                       if(el_name=='get-coord-wkt' && is_checked){

                            var res = mapping.mapping( 'drawGetWkt', 'messsage');
                            el_text.val(res);

                       }else{
                            var res = mapping.mapping( 'drawGetJson' );
                            if(window.hWin.HEURIST4.util.isGeoJSON(res)){
                                el_text.val(JSON.stringify(res));
                            }else{
                                el_text.val('');
                            }
                       }
                }});

            }

            //
            //
            //
            function getWktAndClose(){

                    $('.rightpanel').hide();

                    var res = mapping.mapping( 'drawGetWkt', false );

                    if( res!==false ){
                        //type code is not required for new code. this is for backward capability
                        var typeCode = 'm';
                        if(res.indexOf('GEOMETRYCOLLECTION')<0 && res.indexOf('MULTI')<0){
                            if(res.indexOf('LINESTRING')>=0){
                                typeCode = 'l';
                            }else if(res.indexOf('POLYGON')>=0){
                                typeCode = 'pl';
                            }else {
                                typeCode = 'p';
                            }
                        }

                        if( need_screenshot ){

                            window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));

                            mapping.mapping( 'drawZoomTo', (event) => {

                                function filterNode(node) {
                                    if (node instanceof Text) {
                                        return true;
                                    }
                                    return [
                                        "div",
                                        "span",
                                        "p",
                                        "i",
                                        "img",
                                        "svg",
                                        "g",
                                        "path"
                                        /*
                                        "strong",
                                        "main",
                                        "aside",
                                        "article",
                                        "pre",
                                        "code",
                                        "time",
                                        "address",
                                        "header",
                                        "footer"
                                        */
                                    ].includes(node.tagName.toLowerCase()) || /^h[123456]$/i.test(node.tagName);
                                }

                                try{
                                    domtoimage
                                    .toPng(document.getElementById('map_digitizer'),{
                                        filter: filterNode
                                    })
                                    .then(function (dataUrl) {
                                        window.hWin.HEURIST4.msg.sendCoverallToBack();
                                        window.close({type:typeCode, wkt:res, imgData:dataUrl});
                                    })
                                    .catch(function(error){
                                        window.hWin.HEURIST4.msg.sendCoverallToBack();
                                        window.close({type:typeCode, wkt:res});
                                    });
                                }catch(e){
                                    window.hWin.HEURIST4.msg.sendCoverallToBack();
                                    window.close({type:typeCode, wkt:res});
                                }

                            } );

                        }else{
                            window.close({type:typeCode, wkt:res});
                        }

                        return;

                    }
                    else if(is_geofilter){
                        window.hWin.HEURIST4.msg.showMsgFlash(sMsgDigizeSearch, 2000);
                    }else{
                        window.hWin.HEURIST4.msg.showMsgFlash('You have to draw a shape', 2000);
                    }

                    //restore controls
                    if(is_geofilter || imageurl){
                        $('#rightpanel').hide();
                        $('#spatial_filter').show();
                        $('#cbAllowMulti').prop('checked', false);
                    }else{
                        $('#rightpanel').show();
                        $('#spatial_filter').hide();
                    }
            }

            //
            //
            //
            function refreshImageOverlay( is_reset ){
                if(imageurl){

                    var image_extent =  mapping.mapping('drawGetBounds');

                    if(image_extent==null){
                        if(imageOverlay!=null){
                            imageOverlay.remove();
                            imageOverlay = null;
                        }
                    }else{
                        if(imageOverlay==null){
                            //mapping.mapping('drawSetStyleTransparent');
                            imageOverlay = mapping.mapping('addImage2', imageurl, image_extent);
                            imageOverlay.setOpacity(0.5);
                        }else{
                            if(is_reset){
                                imageOverlay.setUrl( imageurl );
                            }
                            imageOverlay.setBounds(image_extent);
                        }
                    }

                }else if(imageOverlay!=null){
                    imageOverlay.remove();
                    imageOverlay = null;
                }
            }

            //
            // called from showDialog
            //
            function assignParameters(params){

                //temp zoom_with_delay = false;

                if(params && params['wkt']){
                    initial_wkt = params['wkt'];
                }else{
                    initial_wkt = null;
                }

                if(params && params['imageurl']){
                    imageurl = params['imageurl'];
                }else{
                    imageurl = null;
                }

                if(params && params['tool_option']){ // Bounding Box for Bookmarks
                    tool_option = params['tool_option'];
                    window.hWin.HEURIST4.msg.showMsgFlash('Please digitise a bounding box with the rectangle tool.', 3000);
                }else{
                    tool_option = null;
                }

                is_geofilter = params && params['geofilter'];
                need_screenshot = params && params['need_screenshot'];

                if(is_map_inited){
                    onMapInit();
                }
            }

            //
            //
            //
            function onMapInit(){

                if(is_geofilter || imageurl){
                    $('#rightpanel').hide();
                    $('#spatial_filter').show();
                    $('#map_container').css({top:'40px',right:'0px'});
                    $('#cbAllowMulti').prop('checked', false);

                    if(imageurl){
                        $('#spatial_filter .save-button').button({label:'Apply image extent'});
                    }else{
                        $('#spatial_filter .save-button').button({label:'Apply search extent'});
                    }

                }else{
                    $('#rightpanel').show();
                    $('#spatial_filter').hide();
                    $('#map_container').css({top:'0px',right:'200px'});
                }

                //define draw controls for particular needs
                var mode = 'full';
                if(imageurl) mode = 'image'
                else if (is_geofilter) mode = 'filter';
                mapping.mapping( 'drawSetControls', mode );

                if(!window.hWin.HEURIST4.util.isempty(tool_option) && tool_option != null){ // check if only one type of drawing tool is allowed
                    mapping.mapping( 'drawSetControls', tool_option);
                }



                if( !window.hWin.HEURIST4.util.isempty(initial_wkt) && initial_wkt!='undefined' ){ //params && params['wkt']

                    if(initial_wkt.indexOf('GEOMETRYCOLLECTION')>=0 || initial_wkt.indexOf('MULTI')>=0){
                        $('#cbAllowMulti').prop('checked',true);
                    }

                    mapping.mapping('drawLoadWKT', initial_wkt, false);

                    if(zoom_with_delay){
                        setTimeout(function(){
                                mapping.mapping( 'drawZoomTo' );

                                if(initial_wkt.indexOf('POINT') >= 0){ // Zoom out to avoid a de-loaded base map
                                    mapping.mapping('setMapZoom', 5, true);// userAgent.indexOf('Firefox') >= 0 ? 10 : 5
                                }

                                refreshImageOverlay( true );
                        }, 2000);
                    }

                    $('.leaflet-control-geocoder').removeClass('leaflet-control-geocoder-expanded');// hide search box
                }else{

                    $('.leaflet-control-geocoder').addClass('leaflet-control-geocoder-expanded');// expand search box

                    $('#cbAllowMulti').prop('checked',false);
                    mapping.mapping( 'drawClearAll' );

                    //zoom to saved extent
                    if(zoom_with_delay){
                        setTimeout(function(){ mapping.mapping('getSetMapBounds', false);}, 2000);
                    }

                    if(is_geofilter){
                        window.hWin.HEURIST4.msg.showMsgFlash(sMsgDigizeSearch, 2000);
                    }
                }

                var that = this;

                if(!window.hWin.HEURIST4.util.isnull(this.map_geocoder)){

                    this.map_geocoder.on('markgeocode', function(e){ // Add map marker on top of search mark
                        e.target._map.eachLayer(function(layer){
                            if(layer._icon){
                                var map = {};
                                map['layer'] = layer;

                                onMapDrawAdd();//clears other markers if multiple objects are not allowed

                                // Draw marker on top of geocoder result, if point tool is available
                                var marker_ele = $('.leaflet-draw-draw-marker');
                                if(marker_ele.length == 1 && marker_ele.is(':visible')){
                                    that.drawnItems.addLayer(layer);
                                    layer.editing.enable();
                                }

                                that.options.ondrawend.call(that, map);
                            }
                        });
                    });

                    }
                $('.leaflet-control-geocoder-form > input').trigger('focus');

                is_map_inited = true;
            }

            //
            // users adds new draw item
            // It clears other markers if multiple objects are not allowed
            //
            function onMapDrawAdd(e){
                if(!$('#cbAllowMulti').is(':checked')){
                    mapping.mapping( 'drawClearAll' );
                }
            }

            //
            // event listener on start and end of draw
            //
            function onMapDraw(e){
                var res = mapping.mapping( 'drawGetJson',  e);
                if(window.hWin.HEURIST4.util.isGeoJSON(res)){
                    $('#coords1').text(JSON.stringify(res));
                    refreshImageOverlay( false );//repos
                }else{
                    $('#coords1').text('');
                }

            }


        </script>
        <style type="text/css">
            #map_container {
                position: absolute;
                top: 0px;
                left: 0px;
                right: 200px;
                bottom: 0px;
                background-color: #ffffff;
            }
            #map_digitizer {
                height:100%;
                width:100%;
            }


            .color-button {
                width: 14px;
                height: 14px;
                font-size: 0;
                margin: 2px;
                float: left;
                cursor: pointer;
            }

          .delete-menu {
            position: absolute;
            background: white;
            padding: 3px;
            color: #666;
            font-weight: bold;
            border: 1px solid #999;
            font-family: sans-serif;
            font-size: 12px;
            box-shadow: 1px 3px 3px rgba(0, 0, 0, .3);
            margin-top: -10px;
            margin-left: 10px;
            cursor: pointer;
          }
          .delete-menu:hover {
            background: #eee;
          }

            .toppanel{
                text-align:center;
                position: absolute;
                top: 0px;
                height: 32px;
                right: 0px;
                left: 0px;
            }
            .rightpanel{
                text-align:center;
                position: absolute;
                top: 50px;
                width: 196px;
                right: 0px;
                bottom: 0px;
            }
            .rightpanel > div{
                width:100%;
                padding:0.2em;
            }
            .rightpanel > div > button{
                width:12em;
            }
            #coords1 {
                padding: 5px;
                font-weight: normal;
                width:190px;
                min-height:115px;
                border:1px solid #000000;
                font-size:0.9em;
                text-align:left;
            }
            .leaflet-attribution-flag{
                visibility:hidden;
                display:none !important;
            }
        </style>

    </head>

    <!-- HTML -->
    <body style="overflow:hidden">
        <div style="height:100%; width:100%;">

            <div id="map_container">
                <div id="map_digitizer">...</div>
            </div>

            <div id="rightpanel" class="rightpanel">
                <!--
                <label style="display:inline-block;">Draw color:</label>
                <div style="width:auto !important;display:inline-block;height: 14px" id="color-palette"></div>
                -->
                <div>
                    <label><input type="checkbox" id="cbAllowMulti">Allow multiple objects</label><br><br>
                    <button id="style-button">Set style</button>
                </div>
                <div>
                    <button id="load-geometry-button">Add Geometry</button>
                </div>
                <div>
                    <button id="get-geometry-button">Get Geometry</button>
                </div>

                <div style="padding-top:20px">
                    <button id="delete-all-button">Clear all</button>
                </div>
                <div style="display:none">
                    <button id="delete-button">Clear Selected</button>
                </div>
                <div>
                    <button class="cancel-button">Cancel</button>
                </div>

                <div style="padding-top:20px">
                    <button id="view-button">Remember view</button>
                </div>
                <div style="padding-top:20px">
                    <button class="save-button ui-button-action" style="font-weight:bold;font-size:1.1em">Save</button>
                </div>

                <div style="position:absolute;bottom:5;text-align:left;padding:10px;">
                    Add markers by selecting a drawing tool on the left<br><br>
                    Specific coordinates can be entered using Add Geometry
                </div>


                <div style="bottom:30;position: absolute;height:160px;display:none">
                    <div id="coords_hint" style="padding:0 4px 2px 4px"></div>
                    <textarea id="coords1">Click on the map. The code for the selected shape you create will be presented here.</textarea>
                    <button id="apply-coords-button" style="margin-top:10px">Apply Coordinates</button>
                </div>
            </div>

            <div id="spatial_filter" class="toppanel" style="display:none">
                <div style="padding-right:20px;display:inline-block">
                    <button class="save-button" style="font-weight:bold;font-size:1.05em"> Apply search extent</button>
                </div>
                <div style="padding-right:20px;display:inline-block">
                    <button class="cancel-button">Cancel</button>
                </div>
                <div style="display:none">
                    Digitise search area as a rectangle or polygon by selecting a drawing tool on the left
                </div>
            </div>
        </div>

        <div id="get-set-coordinates" style="display: none;">
            <!--
            -->
            <div>
            <div id="set-coordinates-helper" style="display:inline-block;width:80%">
                <span>Paste geo data as Simple points (X,Y or X Y), GeoJSON or WKT</span>
                <div class="heurist-helper1" style="padding:5px 0">
                    WKT:  POINT(x y)   LINESTRING(x1 y1, x2 y2, x3 y3)   POLYGON((x1 y1, x2 y2, x3 y3)) see
                    <a href="https://en.wikipedia.org/wiki/Well-known_text_representation_of_geometry" target="_blank" rel="noopener">wikipedia</a> for more.<br>
                    Coordinates in decimal lat/long or UTM (x/easting then y/northing). Easting in W hemisphere starts at -180, northing in S Hemisphere starts at -90.<br>
                </div>
            </div>
            <div id="get-coordinates-helper" style="display:inline-block;width:80%">
                <span>Select format: </span>
                <label><input type="radio" name="get-coord-format" id="get-coord-json" checked="true">GeoJSON</label>
                <label><input type="radio" name="get-coord-format" id="get-coord-wkt">WKT</label>
            </div>
            <div style="float:right;width:140px;display:none;">
                <input value="360" type="number" size="4" style="width:4em"/>&nbsp;<button id="fix-x-coords">Fix Long</button>
            </div>
            </div>
            <textarea cols="" rows="" id="geodata_textarea"
                style="position:absolute;top:4em;bottom:0;width:97%;resize:none"></textarea>
        </div>

    </body>
</html>
