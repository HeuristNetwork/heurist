<?php

    /**
    * Map digitizing tool
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2015 University of Sydney
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
        <script type="text/javascript" src="../../ext/layout/jquery.layout-latest.js"></script>

        <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?sensor=false&libraries=drawing"></script>

        <script type="text/javascript" src="mapDraw.js"></script>

        <!-- Initializing -->

        <script type="text/javascript">

            var mapping, menu_datasets, btn_datasets;

            // Callback function on map initialization
            function onPageInit(success){

                if(!success) return;

                // Mapping data
                mapping = new hMappingDraw('map_digitizer');

                // init helper (see utils.js)
                top.HEURIST4.util.initHelper( $('#btn_help'),
                            'Mapping Drawing Overview',
                            '../../context_help/mapping_overview.html #content');

            } //onPageInit

        </script>
        <style type="text/css">
            #map_digitizer {
                position: absolute;
                top: 30px;
                left: 0px;
                right: 135px;
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
            #rightpanel{
                position: absolute;
                top: 30px;
                width: 135px;
                right: 0px;
                bottom: 0px;
            }
            #coords1 {
                padding: 5px;
                font-weight: normal;
                width:130px;
                height:290px;
                border:1px solid #000000;
                font-size:0.9em;
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
        </style>

    </head>

    <!-- HTML -->
    <body>
        <div style="height:100%; width:100%;">

            <div id="mapToolbarDiv" style="height: 30px;padding:0.2em">

                <div class="div-table-cell">
                    <label>Geocode:</label>
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
                <button id="delete-button">Delete Selected</button>
                <button id="delete-all-button">Clear Map</button>

                <textarea id="coords1" cols="2" rows="2">
                        Click on the map. The code for the shape you create will be presented here.
                </textarea>
            </div>
        </div>

    </body>
</html>
