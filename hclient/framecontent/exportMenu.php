<?php

/**
* exportMenu,php: Export tab containing menu of export formats
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
require_once 'initPage.php';
?>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/exportMenu.js"></script>

<script type="text/javascript">
    var editing;

    // Callback function on initialization
    function onPageInit(success){
        if(success){
            var exportMenu = new hexportMenu( $('body') );
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

        <h2 style="margin-bottom:0px;">Export result set</h2>
        <div style="font-size:13px;margin-bottom:20px;">
            These functions export the current result set (the subset of records selected by the current search
            / filter), either as a file or through a feed URL which will generate the results on-the-fly for input
            to other software.
        </div>

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
  
           <div class="prompt" id="divWarnAboutReg" style="font-style:italic; color:red; padding-left:110px; width:400px">
                NOTE: This database has not been registered ( Design > Register ) and the file is not therefore importable except into an identically structured database
           </div>
        
        </div>

        <br>
        <div id="menu-export-rdf" class="export-item">
            <button class="export-button">RDF</button>
            <a href="#" oncontextmenu="return false;" 
                data-logaction="exp_RDF"
                data-action="menu-export-rdf"
                title="Generate RDF (Resource Description Framework) for current set of search results (current query)">
                <span class="ui-icon ui-icon-extlink export-popup"></span><span class="export-popup" style="padding-right:10px">feed</span>
                RDF</a>
                &nbsp;&nbsp;<label style="font-size:smaller;">Serialize as: </labels>
                <label style="font-size:smaller;"><input type="radio" name="serial_format" value="rdfxml" checked/>rdfxml</label>&nbsp;
                <label style="font-size:smaller;"><input type="radio" name="serial_format" value="json"/>json</label>&nbsp;
                <label style="font-size:smaller;"><input type="radio" name="serial_format" value="ntriples"/>ntriples</label>&nbsp;
                <label style="font-size:smaller;"><input type="radio" name="serial_format" value="turtle" />turtle</label>
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
                &nbsp;&nbsp;<label style="font-size:smaller;">Details: </labels>
                <label style="font-size:smaller;"><input type="radio" name="detail_mode" value="0"/>No</label>&nbsp;
                <label style="font-size:smaller;"><input type="radio" name="detail_mode" value="1"/>Inline</label>&nbsp;
                <label style="font-size:smaller;"><input type="radio" name="detail_mode" value="2" checked/>Full</label>
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

        <div id="menu-export-iiif" class="export-item">
            <button class="export-button">IIIF</button>
            <a href="#" oncontextmenu="return false;" 
                data-logaction="exp_IIIF"  style="padding-right:30px"
                data-action="menu-export-iiif"
                title="Generate IIIF manifest">
                <span class="ui-icon ui-icon-extlink export-popup"></span><span class="export-popup" style="padding-right:10px">feed</span>
                <span class="ui-icon ui-icon-extlink export-popup mirador"></span><span class="export-popup mirador" style="padding-right:10px">open in Mirador</span>
                IIIF</a>
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
