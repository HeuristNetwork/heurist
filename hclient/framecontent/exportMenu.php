<?php

/**
* exportMenu,php: Export tab containing menu of export formats
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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
<style>
.export-button{
    width:100px;
}
.export-popup{
    display:inline-block;
    vertical-align:text-bottom;
    font-size:smaller;
}
.export-item{
    padding:5px;
}
</style>
</head>

<body class="ui-heurist-bg-light" style="font-size:0.8em">
    <div style="top:0;bottom:0;left:0;position:absolute;padding:10px;font-size:1.2em">

        <h2>File export</h2>

        <div id="menu-export-csv" class="export-item">
            <button class="export-button">CSV</button>
            <a href="#" oncontextmenu="return false;" 
                data-logaction="exp_CSV"
                data-action="menu-export-csv"
                title="Export records as delimited text (comma/tab), applying record type">
                Comma or tab-separated text file</a>
        </div>
        <br>
        
        <div id="menu-export-hml-resultset" class="export-item">
            <button class="export-button">XML</button>
            <a href="#" oncontextmenu="return false;" 
                data-logaction="exp_HML"
                data-action="menu-export-hml-resultset"
                title="Generate HML (Heurist XML format) for current set of search results (current query + expansion)">
                <span class="ui-icon ui-icon-extlink export-popup"></span><span class="export-popup" style="padding-right:10px">feed</span> XML (HML schema - Heurist Markup Language)</a>
        </div>

        <div id="menu-export-json" class="export-item">
            <button class="export-button">JSON</button>
            <a href="#" oncontextmenu="return false;" style="padding-right:20px" 
                data-logaction="exp_JSON"
                data-action="menu-export-json"
                title="Generate JSON for current set of search results (current query)">
                <span class="ui-icon ui-icon-extlink export-popup"></span><span class="export-popup" style="padding-right:10px">feed</span>
                JSON</a>
           <label style="font-size:smaller;"><input type="checkbox" id="extendedJSON" checked/>&nbsp;&nbsp;Include concept codes and names</label>  

           <div class="prompt" style="font-style: italic;padding-left:110px;width:400px">
                XML and JSon exported from a registered Heurist database can be imported directly into any other Heurist database even if the structure is different. Use this to migrate selected or complete data, including between different servers.
           </div>
           <div class="prompt" id="divWarnAboutReg" style="font-style: italic;padding-left:110px;width:400px">
                This database has not been registered and that the XML file is not therefore importable except into an identically structured database
           </div>
        
        </div>

        <br>
        <div id="menu-export-geojson" class="export-item">
            <button class="export-button">GeoJSON</button>
            <a href="#" oncontextmenu="return false;" 
                data-logaction="exp_GeoJSON"
                data-action="menu-export-geojson"
                title="Generate GeoJSON-T for current set of search results (current query)">
                <span class="ui-icon ui-icon-extlink export-popup"></span><span class="export-popup" style="padding-right:10px">feed</span>
                GeoJSON</a>
        </div>
        
        <div id="menu-export-kml" class="export-item">
            <button class="export-button">KML</button>
            <a href="#" oncontextmenu="return false;" 
                data-logaction="exp_KML"
                data-action="menu-export-kml"
                title="Generate KML for current set of search results (current query + expansion)">
                <span class="ui-icon ui-icon-extlink export-popup"></span><span class="export-popup" style="padding-right:10px">feed</span>
                KML</a>
        </div>

        <br>
        <div id="menu-export-gephi" class="export-item">
            <button class="export-button">GEPHI</button>
            <a href="#" oncontextmenu="return false;" 
                data-logaction="exp_GEPHI"  style="padding-right:30px"
                data-action="menu-export-gephi"
                title="Generate GEPHI for current set of search results (current query + expansion)">
                <span class="ui-icon ui-icon-extlink export-popup"></span><span class="export-popup" style="padding-right:10px">feed</span>
                GEPHI</a>
                <label style="font-size:smaller;"><input type="checkbox" id="limitGEPHI"/>&nbsp;&nbsp;Limit gephi output with 1000 nodes</label>
        </div>

        <div id="menu-export-hml-multifile" class="export-item">
            <button class="export-button">HuNI</button>
            <a href="#" oncontextmenu="return false;" 
                data-logaction="exp_XMLHuNI"
                data-action="menu-export-hml-multifile"
                title="Generate HML (Heurist XML format) for current set of search results (current query) with one record per file, plus manifest">
                HuNI harvestable XML files (file-per-record)</a>
        </div>

        <div class="heurist-prompt" style="padding:6px">
            Allow popup window in your browser preferences
        </div>
        <br>
    </div>
</body>
</html>
