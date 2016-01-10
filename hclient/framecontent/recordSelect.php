<?php

    /**
    * Search and select record. It is used in relationship editor and pointer selector
    * 
    * It is combination of generic record search by title (maybe it should be separated into widget) abd resultList widget
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
        <script type="text/javascript" src="recordSelect.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultList.js"></script>
        
        <script type="text/javascript">
            // Callback function on map initialization
            function onPageInit(success){
                if(success){
                    var recordSelect = new hRecordSelect(top.HEURIST4.util.getUrlParameter('rectype_set'), window.location.search);
                }
            }
        </script>
    </head>

    <!-- HTML -->
    <body>
    
        <div style="height: 30px; padding:0.2em">
            <div class="div-table-cell">
                <label>Title:</label>
                <input id="input_search" class="text ui-widget-content ui-corner-all" 
                        style="max-width: 250px; min-width: 10em; width: 250px; margin-right:0.2em"/>
            </div>
            <div class="div-table-cell" style="display: none;">
                <div id="btn_search_stop"></div>
            </div>
            <div class="div-table-cell">
                <div id="btn_search_start"></div>
            </div>
            <div class="div-table-cell">
                in
                <select id="sel_rectypes" class="text ui-widget-content ui-corner-all" style="max-width:200px"></select>
            </div>
            
            <div style="padding-left: 1em;">
                <div id="btn_add_record"></div>
            </div>
        </div>
        <div id="recordList" style="position: absolute;top:40px;bottom:1px;width:99%">
        </div>    
    </body>
</html>
