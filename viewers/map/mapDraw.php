<?php

    /**
    * Map digitizing tool
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2019 University of Sydney
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

define('PDIR','../../');  //need for proper path to js and css    
require_once(dirname(__FILE__).'/../../hclient/framecontent/initPage.php');
?>
        <!-- script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCan9ZqKPnKXuzdb2-pmES_FVW2XerN-eE&libraries=drawing,geometry"></script -->

<link rel="stylesheet" href="<?php echo PDIR;?>external/leaflet/geocoder/Control.Geocoder.css" />
<?php
if($_SERVER["SERVER_NAME"]=='localhost'||$_SERVER["SERVER_NAME"]=='127.0.0.1'){
?>
    <link rel="stylesheet" href="<?php echo PDIR;?>external/leaflet/leaflet.css"/>
    <script type="text/javascript" src="<?php echo PDIR;?>external/leaflet/leaflet.js"></script>
    <script src="<?php echo PDIR;?>external/leaflet/geocoder/Control.Geocoder.js"></script>
<?php
}else{
?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.4.0/dist/leaflet.css"
       integrity="sha512-puBpdR0798OZvTTbP4A8Ix/l+A4dHDD0DGqYW6RQ+9jxkRFclaxxQb/SJAWZfWAkuyeQUytO7+7N4QKrDh+drA=="
       crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.4.0/dist/leaflet.js"
       integrity="sha512-QVftwZFqvtRNi0ZyCtsznlKSWOStnDORoefr1enyq5mVL4tmKB3S/EnC3rRJcxCPavG10IcrVGSmPh6Qw5lwrg=="
       crossorigin=""></script>   
    <!-- link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" /-->
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
<?php
}
?>
<!-- leaflet plugins -->
<script src="<?php echo PDIR;?>external/leaflet/leaflet-providers.js"></script>
<script src="<?php echo PDIR;?>external/leaflet/bookmarks/Leaflet.Bookmarks.min.js"></script>
<script src="<?php echo PDIR;?>external/leaflet/draw/leaflet.draw-src.js"></script>
<script src="<?php echo PDIR;?>external/leaflet/leaflet.circle.topolygon-src.js"></script>
<link type="text/css" rel="stylesheet" href="<?php echo PDIR;?>external/leaflet/bookmarks/leaflet.bookmarks.css">
<link type="text/css" rel="stylesheet" href="<?php echo PDIR;?>external/leaflet/draw/leaflet.draw.css">

        
        <script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapping.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapManager.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapDocument.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapLayer2.js"></script>
        <script type="text/javascript" src="mapDraw.js"></script>
        
        <script type="text/javascript" src="<?php echo PDIR;?>external/js/geodesy-master/vector3d.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/js/geodesy-master/latlon-ellipsoidal.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/js/geodesy-master/utm.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/js/geodesy-master/dms.js"></script>        

        <!-- Initializing -->

        <script type="text/javascript">

            var mapping, initial_wkt, menu_datasets, btn_datasets, zoom_with_delay = true;

            // Callback function on map initialization
            function onPageInit(success){
                
                if(!success) return;

                /* init helper (see utils.js)
                window.hWin.HEURIST4.ui.initHelper( $('#btn_help'), 
                            'Mapping Drawing Overview', 
                            '../../context_help/mapping_drawing.html #content');
                */            

                handleApiReady();
/*
                if (typeof window.hWin.google === 'object' && typeof window.hWin.google.maps === 'object') {
console.log('google map api: already loaded')                    
                    handleApiReady();
                }else{                            
console.log('load google map api')                    
                    $.getScript('https://maps.googleapis.com/maps/api/js?key=AIzaSyDtYPxWrA7CP50Gr9LKu_2F08M6eI8cVjk'
                    +'&libraries=drawing,geometry&callback=handleApiReady');                                           
                    //AIzaSyCan9ZqKPnKXuzdb2-pmES_FVW2XerN-eE
                }
*/
            } //onPageInit
            
            function handleApiReady(){
                
                // Mapping data
                //mapping = new hMappingDraw('map_digitizer', initial_wkt);
                
                var layout_params = {};
                layout_params['notimeline'] = '1';
                layout_params['nocluster'] = '1'
            
                layout_params['controls'] = 'legend,bookmark,geocoder,draw';
                layout_params['legend'] = 'basemaps';
                
                initial_wkt = window.hWin.HEURIST4.util.getUrlParameter('wkt', location.search);
        
                mapping = $('#map_container').mapping({
                    element_map: '#map_digitizer',
                    layout_params:layout_params,
                    oninit: onMapInit,
                    ondraw_addstart: onMapDrawAdd,
                    ondraw_editstart: onMapDraw,
                    ondrawend:  onMapDraw
                });                
                
                //initialize buttons
                $('#save-button').button().on({click:function()
                {

                    var res = mapping.mapping( 'drawGetWkt', true);
                    
                    if( !window.hWin.HEURIST4.util.isempty(res) ){    
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
                        window.close({type:typeCode, wkt:res});    
                    }

                }});
                
                $('#style-button').button().on({click:function(){
                        mapping.mapping( 'drawSetStyle');
                }});
                $('#delete-all-button').button().on({click:function(){
                       mapping.mapping( 'drawClearAll' );
                }});
                $('#cancel-button').button().on({click:function(){
                       window.close();
                }});
                // paste geojson -> map
                $('#load-geometry-button').button().click(function(){

                    var titleYes = window.hWin.HR('Yes'),
                    titleNo = window.hWin.HR('No'),
                    buttons = {};
                    
                    var $dlg;

                    buttons[titleYes] = function() {
                        mapping.mapping( 'drawLoadGeometry', $dlg.find('#geodata_textarea').val());
                        $dlg.dialog( "close" );
                    };
                    buttons[titleNo] = function() {
                        $dlg.dialog( "close" );
                    };
                    $('#get-coordinates-helper').hide();
                    $('#set-coordinates-helper').show();

                    $dlg = window.hWin.HEURIST4.msg.showElementAsDialog({window:top, 
                        element: document.getElementById( "get-set-coordinates" ),
                        resizable:false,
                        width:690, height:400,
                        title:window.hWin.HR('Paste or upload geo data'),
                        buttons:buttons    
                    });

                });
                // load from map -> geojson
                $('#get-geometry-button').button().click(function(){
                    
                    $('#get-coordinates-helper').show();
                    $('#set-coordinates-helper').hide();
                    
                    $('#get-coord-wkt').change();
                    
                    var $dlg = window.hWin.HEURIST4.msg.showElementAsDialog({window:top, 
                        element: document.getElementById( "get-set-coordinates" ),
                        resizable: false,
                        width:690, height:400,
                        title:window.hWin.HR('Copy the result')
                
                    });
                });
                
                $('input[name="get-coord-format"]').on({change:function(e){
                        
                       var is_checked = $(e.target).is(':checked');
                       var el_name = $(e.target).attr('id');
console.log(el_name+'  '+is_checked);
                       var el_text = $(e.target).parents('#get-set-coordinates').find('#geodata_textarea');


                       if(el_name=='get-coord-wkt' && is_checked){
                           
                            var res = mapping.mapping( 'drawGetWkt', false);
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
            // called from showDialog
            //
            function assignParameters(params){
                
                zoom_with_delay = false;
                
                if(params && params['wkt']){
                    initial_wkt = params['wkt'];
                }else{
                    initial_wkt = null;
                }
                onMapInit();
            } 
            
            //
            //
            //           
            function onMapInit(){
                
                if(initial_wkt){ //params && params['wkt']
                
                    if(initial_wkt.indexOf('GEOMETRYCOLLECTION')>=0 || initial_wkt.indexOf('MULTI')>=0){
                        $('#cbAllowMulti').prop('checked',true);
                    }
                
                    mapping.mapping( 'drawLoadWKT', initial_wkt, true);
               
//console.log('zoom '+zoom_with_delay);

                    if(zoom_with_delay){
                        setTimeout(function(){ mapping.mapping( 'drawZoomTo' ); }, 3000);
                    }
                    
                    
                }else{
                    $('#cbAllowMulti').prop('checked',false);
                    mapping.mapping( 'drawClearAll' );
                }
            }
            
            //
            // users adds new draw item
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
//console.log('finished');     
                var res = mapping.mapping( 'drawGetJson',  e);
                if(window.hWin.HEURIST4.util.isGeoJSON(res)){
                    $('#coords1').text(JSON.stringify(res));
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
          
            #rightpanel{
                text-align:center;
                position: absolute;
                top: 50px;
                width: 200px;
                right: 0px;
                bottom: 0px;
            }
            #rightpanel > div{
                width:100%;
                padding:0.2em;
            }   
            #rightpanel > div > button{
                width:14em;
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
        </style>

    </head>

    <!-- HTML -->
    <body style="overflow:hidden">
        <div style="height:100%; width:100%;">

            <div id="map_container">
                <div id="map_digitizer">Mapping</div>
            </div>

            <div id="rightpanel">
                <!--
                <label style="display:inline-block;">Draw color:</label>
                <div style="width:auto !important;display:inline-block;height: 14px" id="color-palette"></div>
                -->
                <div>
                    <button id="style-button">Set style</button>
                </div>
                
                <div style="padding-top:20px">
                    <!-- <label>Select shape to draw</label><br>
                    <label>Click to add points</label><br><br> -->
                    <label><input type="checkbox" id="cbAllowMulti">Allow multiple objects</label><br><br>
                    <button id="save-button" style="font-weight:bold">Save</button>
                </div> 
                <div style="padding-top:20px">
                    <button id="delete-all-button">Clear all</button>
                </div> 
                <div style="display:none">
                    <button id="delete-button">Clear Selected</button>
                </div> 
                <div>
                    <button id="cancel-button">Cancel</button>
                </div>
                <div style="padding-top:20px">
                    <button id="load-geometry-button">Add Geometry</button>
                </div> 
                <div>
                    <button id="get-geometry-button">Get Geometry</button>
                </div> 
                
                <div style="bottom:30;position: absolute;height:160px;display:none">
                    <div id="coords_hint" style="padding:0 4px 2px 4px"></div>    
                    <textarea id="coords1">Click on the map. The code for the selected shape you create will be presented here.</textarea>
                    <button id="apply-coords-button" style="margin-top:10px">Apply Coordinates</button>
                </div> 
            </div>            
        </div>
        
        <div id="get-set-coordinates" style="display: none;">
            <!--
            -->
            <div id="set-coordinates-helper">
                <label>Paste geo data in supported format (GeoJSON or WKT)</label>
            </div>
            <div id="get-coordinates-helper">
                <label>Select format: </label>
                <label><input type="radio" name="get-coord-format" id="get-coord-json" checked="true">GeoJSON</label>
                <label><input type="radio" name="get-coord-format" id="get-coord-wkt">WKT</label>
            </div>
            <textarea cols="" rows="" id="geodata_textarea"
                style="position:absolute;top:2em;bottom:0;width:97%;resize:none"></textarea>
        </div>

    </body>
</html>
