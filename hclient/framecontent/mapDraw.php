<?php

    /**
    * Map digitizing tool
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

require_once(dirname(__FILE__)."/initPage.php");
?>
        <script type="text/javascript" src="<?php echo PDIR;?>ext/layout/jquery.layout-latest.js"></script>

        <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?libraries=drawing,geometry"></script>

        <script type="text/javascript" src="mapDraw.js"></script>

        <!-- Initializing -->

        <script type="text/javascript">

            var mapping, menu_datasets, btn_datasets;

            // Callback function on map initialization
            function onPageInit(success){
                
                if(!success) return;

                var initial_wkt = window.hWin.HEURIST4.util.getUrlParameter('wkt', location.search);
                
                // Mapping data
                mapping = new hMappingDraw('map_digitizer', initial_wkt);
                
                // init helper (see utils.js)
                window.hWin.HEURIST4.ui.initHelper( $('#btn_help'), 
                            'Mapping Drawing Overview', 
                            '../../context_help/mapping_drawing.html #content');

            } //onPageInit

        </script>
        <style type="text/css">
            #map_digitizer {
                position: absolute;
                top: 30px;
                left: 0px;
                right: 200px;
                bottom: 0px;
                background-color: #ffffff;
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
                top: 30px;
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
                height:290px;
                border:1px solid #000000;
                font-size:0.9em;
                text-align:left;
            }
        </style>

    </head>

    <!-- HTML -->
    <body style="overflow:hidden">
        <div style="height:100%; width:100%;">

            <div id="mapToolbarDiv" style="height: 30px;padding:0.2em">
                    
                <div class="div-table-cell">
                    <label>Find:</label>
                    <input id="input_search" class="text ui-widget-content ui-corner-all" 
                            style="max-width: 250px; min-width: 10em; width: 250px; margin-right:0.2em"/>
                    <div id="btn_search_start"></div>
                </div>
                <div class="div-table-cell" style="padding-left: 2em;">
                    <label for="sel_viewpoints">Zoom to saved location</label>
                    <select id="sel_viewpoints" class="text ui-widget-content ui-corner-all" style="max-width:200px"></select>
                    <div id="btn_viewpoint_delete"></div>
                    <div id="btn_viewpoint_save"></div>
                </div>
                <div class="div-table-cell" id="coords2" style="padding-left: 1em;">
                </div>
            
                <div style="position: absolute; right: 0.2em; top:0.2em;" class="ui-buttonset map-inited">
                    <button id="btn_help">Help</button>
                </div>
            </div>
            
            <div id="map_digitizer">Mapping</div>

            <div id="rightpanel">

                <div id="color-palette"></div>

                <div style="padding-top:15px">
                    <button id="save-button" style="font-weight:bold">Save</button>
                </div> 
                <div style="padding-top:20px">
                    <button id="cancel-button">Cancel</button>
                </div>
                <div>
                    <button id="delete-button">Delete Selected</button>
                </div> 
                <div>
                    <button id="delete-all-button">Clear Map</button>
                </div> 
                <div>
                    <button id="load-geometry-button">Add Geometry</button>
                </div> 
                <div>
                    <button id="get-geometry-button">Get Geometry</button>
                </div> 
                
                <div style="padding-top:15px">
                    <textarea id="coords1" cols="2" rows="2">
                        Click on the map. The code for the selected shape you create will be presented here.
                    </textarea>
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
            <textarea cols="" rows="" id="geodata-textarea"
                style="position:absolute;top:2em;bottom:0;width:97%;resize:none"></textarea>
        </div>

    </body>
</html>
