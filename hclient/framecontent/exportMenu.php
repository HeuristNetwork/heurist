<?php

/**
* exportMenu,php: Export tab containing menu of export formats
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

define('LOGIN_REQUIRED',1);

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
require_once(dirname(__FILE__)."/initPage.php");
?>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/exportMenu.js"></script>

<script type="text/javascript">
    var editing;

    // Callback function on initialization
    function onPageInit(success){
        if(success){

            var exportMenu = new hexportMenu();

            var $container = $("<div>").appendTo($("body"));
        }
    }            
</script>
</head>

<body style="background-color:white">
    <div style="width:480px;top:0;bottom:0;left:0;position:absolute;padding:10px;font-size:1.2em">

        <h2>Text file export</h2>

        <br><br>

        <div id="menu-export-csv" style="padding-left:5px;">
            <button class="export-button">CSV</button>
            <a href="#" oncontextmenu="return false;" 
                data-logaction="exp_CSV"
                data-action="menu-export-csv"
                title="Export records as delimited text (comma/tab), applying record type">
                Comma or tab-separated text file</a>
        </div>

        <div id="menu-export-hml-resultset" style="padding-left:5px;">
            <button class="export-button">HML</button>
            <a href="#" oncontextmenu="return false;" 
                data-logaction="exp_HML"
                data-action="menu-export-hml-resultset"
                title="Generate HML (Heurist XML format) for current set of search results (current query + expansion)">
                HML <span class="ui-icon ui-icon-extlink" style="display:inline-block;vertical-align:text-bottom"></span></a>
        </div>

        <div id="menu-export-hml-multifile" style="padding-left:5px;">
            <button class="export-button">XML</button>
            <a href="#" oncontextmenu="return false;" 
                data-logaction="exp_XMLHuNI"
                data-action="menu-export-hml-multifile"
                title="Generate HML (Heurist XML format) for current set of search results (current query) with one record per file, plus manifest">
                HuNI harvestable (file-per-record) <span class="ui-icon ui-icon-extlink" style="display:inline-block;vertical-align:text-bottom"></span></a>
        </div>

        <div id="menu-export-json-multifile" style="padding-left:5px;">
            <button class="export-button">JSON</button>
            <a href="#" oncontextmenu="return false;" 
                data-logaction="exp_JSON"
                data-action="menu-export-json-multifile"
                title="Generate JSON for current set of search results (current query) with one record per file, plus manifest">
                JSON<span class="ui-icon ui-icon-extlink" style="display:inline-block;vertical-align:text-bottom"></span></a>
           <label>&nbsp;&nbsp;Include concept codes and names of definitions <input type="checkbox" id="extendedJSON" checked/></label>     
        </div>

        <div id="menu-export-geojson-multifile" style="padding-left:5px;">
            <button class="export-button">GeoJSON</button>
            <a href="#" oncontextmenu="return false;" 
                data-logaction="exp_GeoJSON"
                data-action="menu-export-geojson-multifile"
                title="Generate GeoJSON-T for current set of search results (current query)">
                GeoJSON<span class="ui-icon ui-icon-extlink" style="display:inline-block;vertical-align:text-bottom"></span></a>
        </div>
        
        <div id="menu-export-kml" style="padding-left:5px;">
            <button class="export-button">KML</button>
            <a href="#" oncontextmenu="return false;" 
                data-logaction="exp_KML"
                data-action="menu-export-kml"
                title="Generate KML for current set of search results (current query + expansion)">
                KML <span class="ui-icon ui-icon-extlink" style="display:inline-block;vertical-align:text-bottom"></span></a>
        </div>

        <div id="menu-export-gephi" style="padding-left:5px;">
            <button class="export-button">GEPHI</button>
            <a href="#" oncontextmenu="return false;" 
                data-logaction="exp_GEPHI"
                data-action="menu-export-gephi"
                title="Generate GEPHI for current set of search results (current query + expansion)">
                GEPHI <span class="ui-icon ui-icon-extlink" style="display:inline-block;vertical-align:text-bottom"></span></a>
        </div>

        <!--

        <ul id="menu_container" style="margin-top:10px;padding:2px">

        <li>EXPORT RESULTS SET</li>

        <li class="admin-only" style="padding-left:5px;">
        <a href="export/delimited/exportDelimitedForRectype.html" name="auto-popup" class="fixed"
        title="Export records as delimited text (comma/tab), applying record type and additional Heurist search filter as required">
        CSV</a>
        </li>

        <li id="menu-export-hml-resultset" style="padding-left:5px;">
        <a href="#"
        title="Generate HML (Heurist XML format) for current set of search results (current query + expansion)">
        HML</a>
        </li>

        <li id="menu-export-kml" style="padding-left:5px;">
        <a href="#"
        title="Generate KML for current set of search results (current query + expansion)">
        KML</a>
        </li>

        <li  id="menu-export-rss" style="padding-left:5px;">
        <a href="#"
        title="Generate RSS feed for current set of search results (current query + expansion)">
        RSS</a>
        </li>

        <li  id="menu-export-atom" style="padding-left:5px;">
        <a href="#"
        title="Generate Atom feed current set of search results (currrent query + expansion)">
        Atom</a>
        </li>

        <hr/>

        <li>HML</li>


        <li id="menu-export-hml-selected" style="padding-left:5px;">
        <a href="#"
        title="Generate HML (Heurist XML format) for currently selected records only">
        Selected records</a>
        </li>

        <li id="menu-export-hml-plusrelated" style="padding-left:5px;">
        <a href="#"
        title="Generate HML (Heurist XML format) for current selection and related records">
        Selected with related records</a>
        </li>

        <li id="menu-export-hml-multifile" style="padding-left:5px;">
        <a href="#"
        title="Generate HML (Heurist XML format) for current set of search results (current query) with one record per file, plus manifest">
        One file per record</a>
        </li>

        <hr/>


        </ul>
        -->
        
        <div class="heurist-prompt" style="padding:6px">
            Allow popup window in your browser preferences
        </div>
    </div>
</body>
</html>
