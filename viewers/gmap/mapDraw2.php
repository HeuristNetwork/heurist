<?php

    /**
    * Map digitizing tool (google map based)
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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

define('PDIR','../../');  //need for proper path to js and css    
require_once(dirname(__FILE__).'/../../hclient/framecontent/initPage.php');
?>
        <script type="text/javascript" src="<?php echo PDIR;?>external/layout/jquery.layout-latest.js"></script>

        <script type="text/javascript" src="mapDraw2.js"></script>
        <script type="text/javascript" src="mapLayer.js"></script>
        
        <script type="text/javascript" src="<?php echo PDIR;?>external/js/geodesy-master/vector3d.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/js/geodesy-master/latlon-ellipsoidal.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/js/geodesy-master/utm.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/js/geodesy-master/dms.js"></script>        
        
        <script type="text/javascript" src="<?php echo PDIR;?>external/js/wellknown.js"></script>

        <!-- Initializing -->

        <script type="text/javascript">

            var mapping, menu_datasets, btn_datasets;

            // Callback function on map initialization
            function onPageInit(success){
                
                if(!success) return;

                // init helper (see utils.js)
                window.hWin.HEURIST4.ui.initHelper( {button:$('#btn_help'), 
                            title:'Mapping Drawing Overview', 
                            url:'../../context_help/mapping_drawing.html #content'});


                if (typeof window.hWin.google === 'object' && typeof window.hWin.google.maps === 'object') {
console.log('google map api: already loaded')                    
                    handleApiReady();
                }else{                            
console.log('load google map api')                    
                    $.getScript('https://maps.googleapis.com/maps/api/js?key=<?php echo $accessToken_GoogleAPI;?>'
                    +'&libraries=drawing,geometry&callback=handleApiReady');                                           
                }

            } //onPageInit
            
            function handleApiReady(){
                var initial_wkt = window.hWin.HEURIST4.util.getUrlParameter('wkt', location.search);
                // Mapping data
                mapping = new hMappingDraw('map_digitizer', initial_wkt);
            }
            
            function assignParameters(params){
                if(params && params['wkt']){
                    var initial_wkt = params['wkt'];
                    
                    mapping.loadWKT(initial_wkt);
                }else{
                    mapping.clearAll();
                }
            }

        </script>
        <style type="text/css">
            #map_container {
                position: absolute;
                top: 50px;
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

            <div id="mapToolbarDiv" style="height:50px;padding:1em 0.2em">
                    
                <div class="div-table-cell">
                    <label>Find:</label>
                    <input id="input_search" class="text ui-widget-content ui-corner-all" 
                            style="max-width: 100px; min-width: 6em; width: 100px; margin-right:0.2em"/>
                    <div id="btn_search_start"></div>
                </div>
                <div class="div-table-cell" style="padding-left: 2em;">
                    <label for="sel_viewpoints">Zoom to extent</label>
                    <select id="sel_viewpoints" class="text ui-widget-content ui-corner-all" style="max-width:200px"></select>
                    <div id="btn_viewpoint_delete"></div>
                    <div id="btn_viewpoint_save"></div>
                </div>
                <div class="div-table-cell" id="coords2" style="padding-left: 1em;">
                </div>
                
                <div class="div-table-cell" style="padding-left: 2em;">
                    <label for="sel_overlays">Background</label>
                    <select id="sel_overlays" class="text ui-widget-content ui-corner-all" style="max-width:120px">
                        <option>none</option>
                    </select>
                </div>
            
                <div style="position: absolute; right: 0.2em; top:1em;" class="map-inited">
                    <button id="btn_help">Help</button>
                </div>
            </div>
            
            <div id="map_container">
                <div id="map_digitizer">Mapping</div>
            </div>

            <div id="rightpanel">

                <label style="display:inline-block;">Draw color:</label>
                <div style="width:auto !important;display:inline-block;height: 14px" id="color-palette"></div>

                
                <div style="padding-top:20px">
                    <label>Select shape to draw</label><br>
                    <label>Click to add points</label><br><br>
                    <label><input type="checkbox" id="cbAllowMulti">Allow multiple objects</label><br><br>
                    <button id="save-button" style="font-weight:bold">Save</button>
                </div> 
                <div style="padding-top:20px">
                    <button id="delete-all-button">Clear all</button>
                </div> 
                <div>
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
                
                <div style="bottom:30;position: absolute;height:160px">
                    <div id="coords_hint" style="padding:0 4px 2px 4px"></div>    
                    <textarea id="coords1">Click on the map. The code for the selected shape you create will be presented here.</textarea>
                    <button id="apply-coords-button" style="margin-top:10px">Apply Coordinates</button>
                </div> 
            </div>            
        </div>
        
        <div id="get-set-coordinates" style="display: none;">
            <!--
            
            
            -->
            <div>
                <label>Paste geo data in supported format (GeoJSON,.....)</label>
            </div>
            <textarea cols="" rows="" id="geodata_textarea"
                style="position:absolute;top:2em;bottom:0;width:97%;resize:none"></textarea>
        </div>

    </body>
</html>
