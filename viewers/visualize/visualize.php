<?php
/**
* visualize.php - Provides the HTML structure and toolbar for the network diagram visualization.
*
* @fileOverview This PHP file generates the main HTML layout for the D3.js network visualization.
* It includes a toolbar with controls for interaction modes (select, zoom), view modes (icons, info),
* gravity, data refresh, and advanced settings (node/link appearance, labels, export).
* The content of the toolbar and some behaviors differ based on whether it's displaying
* the database structure (`$isDatabaseStructure == 1`) or a recordset.
* It also includes the SVG container where the graph is rendered and a hidden div for node information display.
*
* @package     Heurist academic knowledge management system
* @subpackage  /viewers/visualize
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Jan-Jaap de Groot
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4
*/

// Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
// with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
// Unless required by applicable law or agreed to in writing, software distributed under the License is
// distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
// See the License for the specific language governing permissions and limitations under the License.
//

// The $isDatabaseStructure variable is expected to be set before this file is included.
// It determines which version of the toolbar and layout is rendered.
if(@$isDatabaseStructure == 1){ // Toolbar and layout for Database Structure Visualization
?>
<!-- Network diagram HTML for Database Structure -->
<div class="ent_header" style="height:4em;border:none;display:table-row;top:5px;"> <?php // Outer container for toolbar ?>

   <div id="toolbar" style="display:none;"> <?php // Toolbar, initially hidden, shown by JS ?>

        <div id="setSelectMode" class="toolbar-section" style="min-width:40px"> <?php // Selection and Zoom controls ?>
            <button id="btnSingleSelect" name="selectMode" value="single" title="Select and drag single nodes">Select and drag nodes</button>
            <button id="btnMultipleSelect" name="selectMode" value="multi" title="Select multiple nodes using a selection box (right-click and drag)">Select multiple nodes</button>

            <span style="display:inline-block;width:15px;"></span> <?php // Spacer ?>

            <button id="btnZoomIn" style="width:20px;" title="Zoom In">Zoom In</button>
            <button id="btnZoomOut" style="width:20px;" title="Zoom Out">Zoom Out</button>
            <button id="btnFitToExtent" style="width:20px;" title="Fit graph to view">Fit to extent</button>
        </div>

        <div id="setViewMode" class="toolbar-section" style="min-width:40px;padding-left:15px;"> <?php // View mode controls ?>
            <button id="btnViewModeIcon" name="viewMode" value="icons" title="Icon view">Icons</button> <?php // Renamed name attribute for clarity ?>
            <button id="btnViewModeInfo" name="viewMode" value="infoboxes" title="Basic info box view">Info</button>
            <button id="btnViewModeFull" name="viewMode" value="infoboxes_full" title="Full info box with links view">Info + Links</button>
        </div>

        <div id="setDivGravity" class="toolbar-section" style="border-left: solid 1px gray; min-width:90px;"> <?php // Gravity controls ?>
            <div id="setGravityMode">
                <span class="ui-controlgroup-label" style="border:none;background:none;">Gravity:</span>
                <button id="gravityMode0" name="gravityMode" value="off" title="Turn off gravity (nodes stay where dragged)">Off</button>
                <button id="gravityMode1" name="gravityMode" value="touch" title="Apply gravity when nodes are interacted with">On</button>
            </div>
        </div>

        <div id="divRefresh" class="toolbar-section" style="min-width: 45px;"> <?php // Refresh button ?>
            <button id="btnRefreshData" title="Refresh graph data">Refresh data</button>
        </div>

        <div id="setAdvancedMode" class="toolbar-section" style="border-left: solid 1px gray;padding-left:15px;min-height:22px;vertical-align:middle;font-size:0.8em; cursor:pointer;"> <?php // Toggle for advanced settings ?>
                <span class="ui-icon ui-icon-triangle-1-w advanced" title="Hide advanced functions" style="height: 22px; display:none;">&nbsp;</span> <?php // Icon shown when advanced is open ?>
                <a href="#">Advanced functions&nbsp;<span class="ui-icon ui-icon-triangle-1-e" style="display: inline-block;vertical-align: middle;height: 22px;"></span></a> <?php // Link to toggle ?>
        </div>

        <?php // Advanced settings sections, initially hidden if 'advanced' setting is false ?>
        <div id="setDivNodes" class="toolbar-section advanced" style="display:none;padding-left:15px; min-width:190px;"> <?php // Node appearance settings ?>
            <span>Nodes:</span>
            <div class="colorblock" style="margin-top:-4px;width:auto;" title="Color of nodes">
                <input id="entityColor" style="width:0px;border:none;"/> <?php // Placeholder for color picker input ?>
            </div>

            <div id="setNodesMode" style="display:inline-block;" title="Node sizing formula">
                <button id="nodesMode0" name="nodesMode" value="linear" title="Radius of nodes changes linearly">Lin</button>
                <button id="nodesMode1" name="nodesMode" value="logarithmic" title="Radius of nodes changes logarithmically">Log</button>
                <button id="nodesMode2" name="nodesMode" value="unweighted" checked="checked" title="Radius of nodes has fixed size">Fixed</button>
            </div>
            <input id="nodesRadius" class="number-input small" type="number" min="12" max="45" step="1" title="Base node radius"/>
        </div>

        <div id="setDivLinks" class="toolbar-section advanced" style="display:none;border-left: solid 1px gray; min-width:214px;padding-right: 10px;"> <?php // Basic Link settings ?>
            <span>Links:</span>
            <label><input id="linksEmpty" title="Show empty links (zero count) as faint lines" type="checkbox"/>&nbsp;Empty</label>
            <label><input id="expand-links" title="Expand all links between two nodes (if multiple exist)" type="checkbox"/>&nbsp;Expand</label>
        </div>

        <div id="setDivLinks2" class="toolbar-section advanced" style="display:none;border-left: solid 1px gray; min-width:214px;padding-right: 10px;"> <?php // Advanced Link appearance settings ?>
            <span>Links:</span>
            <div id="linksPathColor" class="colorblock" title="Color of links" style="margin-left:10px;">/<input id="linksPathColor_inpt" style="width:0px;border:none;"/></div>
            <div id="linksMarkerColor" class="colorblock" title="Color of markers/arrows on links"></div>
            <div style="display:inline-block;width:0;"><input id="linksMarkerColor_inpt" style="width:0px;border:none;"/></div>

            <div id="setLinksMode" style="display:inline-block;" title="Link shape">
                <button id="linksMode0" name="linksMode" value="straight" title="Straight links">Straight</button>
                <button id="linksMode1" name="linksMode" value="curved" checked="checked" title="Curved links">Curved</button>
                <button id="linksMode2" name="linksMode" value="stepped" title="Stepped links (orthogonal)">Stepped</button>
            </div>

            <input id="linksLength" title="Target Links Length" class="number-input medium" type="number" min="1" step="25" />
            x
            <input id="linksWidth" title="Base Links Thickness" class="number-input small" type="number" min="1" max="25" step="1" />
        </div>

        <div id="setDivLabels" class="toolbar-section advanced" style="display:none;border-left: solid 1px gray; padding-left: 10px; min-width:140px;"> <?php // Label settings ?>
            <span>Labels:</span>
            <input type="checkbox" id="textOnOff" value="on" title="Show labels for icon view" style="margin: 5px;"/>
            <div id="textColor" class="xcolorblock" title="Text color">Text</div> <?php // Placeholder for text color picker ?>

            <input id="textLength" title="Max Label Length (characters)" class="number-input medium" type="number" min="10" max="250" step="10" /> <?php // Adjusted min ?>
            x
            <input id="fontSize" title="Font Size (pixels)" class="number-input small" type="number" min="8" max="25" step="1" />
        </div>

        <div id="setDivExport" class="toolbar-section advanced" style="display:none;border-left: solid 1px gray; padding-left:10px;text-align:right;min-width:50px;"> <?php // Export options ?>
            <button type="button" id="gephi-export" onclick="getGephiFormat()" title="Export graph to GEXF format for Gephi">GEPHI</button>
            <button type="button" id="embed-export" title="Get embed code for this visualization">Embed</button>
        </div>

    </div> <?php // end of toolbar ?>
</div> <?php // end of ent_header ?>

<div id="divSvg" class="ent_content_full" style="top:4.5em;overflow:hidden;"> <?php // Main content area for SVG ?>
    <svg id="d3svg" class="fullscreen"> <?php // SVG canvas, class for potential fullscreen styling ?>
        <text x="25" y="25" fill="black">Building graph ...</text> <?php // Initial loading message ?>
    </svg>

    <div id="net_limit_warning" style="z-index:2000;position:absolute;top:0;right:0;border-radius:6px;background-color: rgb(172, 231, 255);font-weight:bold;color:red; padding:8px; display:none;">
    </div> <?php // Warning message for item limit ?>
</div>

<?php
} else { // Toolbar and layout for Recordset Visualization (embedded in search results, etc.)
?>
<div id="toolbar" class="split_bar"> <?php // Simplified toolbar using dropdowns ?>
    <ul class="split_bar">
        <li class="dropdown1" style="position:absolute;left:0px;top:10px;"> <?php // Changed div to li for ul parent ?>
            <span id="nodecontrolbox" class="toolbar-dropdown-trigger">Node Control</span>
            <div class="dropdown-content1">
                <div>
                    <span>Select Mode: </span>
                    <button id="btnSingleSelect" name="selectMode" value="single" title="Select and drag single nodes">Select and drag nodes</button>
            <button id="btnMultipleSelect" name="selectMode" value="multi" title="Select multiple nodes by dragging a selection box (usually right-click + drag)">Select multiple nodes</button>
                </div>

                <div>
                    <span style="border:none;background:none;">Gravity:</span>
                    <div id="setGravityMode" style="padding-left:10px;">
                        <button id="gravityMode0" name="gravityMode" value="off" title="Turn off gravity">Off</button>
                        <button id="gravityMode1" name="gravityMode" value="touch" title="Apply gravity on interaction">On</button>
                    </div>
                </div>
            </div>
        </li> <?php // end dropdown1 ?>
    </ul>

    <ul class="split_bar">
        <li class="dropdown2" style="position: absolute;left: 130px;top:10px; "> <?php // Changed div to li ?>
            <span id="linkcontrolbox" class="toolbar-dropdown-trigger">Link Control</span>  <?php // Changed id for clarity ?>
            <div class="dropdown-content2">
                <div>
                    <span>Links:</span>
                    <label><input id="linksEmpty" title="Show empty links (zero count) as faint lines" type="checkbox" />&nbsp;Empty</label>
                    <label><input id="expand-links" title="Expand all links between two nodes" type="checkbox" />&nbsp;Expand</label>
                </div>
                <div>
                    <br/> <?php // Spacer ?>
                    <span title="Node sizing formula">Node Size Formula:</span>
                    <button id="nodesMode0" name="nodesMode" value="linear" title="Radius of nodes changes linearly">Lin</button>
                    <button id="nodesMode1" name="nodesMode" value="logarithmic" title="Radius of nodes changes logarithmically">Log</button>
                    <button id="nodesMode2" name="nodesMode" value="unweighted" checked="checked" title="Radius of nodes has fixed size">Fixed</button>
                    <input id="nodesRadius" class="number-input small" type="number" min="12" max="45" step="1" title="Base node radius" />
                </div>

            </div> <?php // end dropdown-content2 ?>
        </li> <?php // end dropdown2 ?>
    </ul>

    <ul class="split_bar">
        <li class="dropdown3" style="position: absolute;left: 250px;top:10px;"> <?php // Changed div to li ?>
            <span id="graphcontrolbox" class="toolbar-dropdown-trigger">Graph Control</span> <?php // Changed id for clarity ?>
            <div class="dropdown-content3">
                <div>
                    <button id="resetbutton" name="reset" onclick="refreshButton();" title="Refresh graph data and layout">Refresh Data</button>
                    <button type="button" id="windowPopOut" onclick="openWin();" title="Open graph in a new fullscreen window">Open Fullscreen</button>
                    <button type="button" id="closegraphbutton" onclick="window.close();" title="Close this fullscreen graph window">Close Fullscreen</button>
                </div>
                <div>
                    <span id="viewnode" class="ui-controlgroup-label" style="border:none;background:none;">View Mode:
                    </span>
                    <button id="btnViewModeIcon" name="viewMode" value="icons" title="Icon view">Icons</button> <?php // Corrected name attribute ?>
                    <button id="btnViewModeInfo" name="viewMode" value="infoboxes" title="Basic info box view">Info</button>
                    <button id="btnViewModeFull" name="viewMode" value="infoboxes_full" title="Full info box with links view">Info + Links</button>
                </div>

                <div>
                    <span class="ui-controlgroup-label" style="border:none;background:none;">Set Zoom: </span>
                    <button id="btnZoomIn" style="width:20px;" title="Zoom In">Zoom In</button>
                    <button id="btnZoomOut" style="width:20px;" title="Zoom Out">Zoom Out</button>
                    <button id="btnFitToExtent" style="width:20px;" title="Fit graph to view">Fit to extent</button>
                </div>

                <div id="setDivExport">
                    <span class="ui-controlgroup-label" style="border:none;background:none;">Export:</span>
                    <button type="button" id="gephi-export" onclick="getGephiFormat()" title="Export to GEXF for Gephi">GEPHI</button>
                    <button type="button" id="embed-export" title="Get embed code">Embed</button>
                </div>
            </div>

            <?php // Labels settings are part of advanced in the other view, here they are hidden by default but could be shown ?>
            <div id="setDivLabels" style="display:none; padding-left: 10px; min-width:140px;"> <?php // Hidden by default ?>
                <span>Labels:</span>
                <input type="checkbox" id="textOnOff" value="on" title="Show labels for icon view" style="margin: 5px;"/>
                <div id="textColor" class="xcolorblock" title="Text color">Text</div> <?php // Placeholder ?>

                <input id="textLength" title="Max Label Length" class="number-input medium" type="number" min="10" max="250" step="10" />
                x
                <input id="fontSize" title="Font Size" class="number-input small" type="number" min="8" max="25" step="1" /> <?php // Note: This div is display:none by default ?>
            </div>
        </li> <?php // end dropdown3 ?>
    </ul>
</div> <?php // end toolbar for recordset ?>

<div id="divSvg" class="ent_content_full" style="top:4.5em;overflow:hidden;"> <?php // Main content area for SVG ?>
    <svg id="d3svg" class="fullscreen" style="width: 100%; height: 100%;">
        <text x="25" y="25" fill="black">Building graph ...</text> <?php // Initial loading message ?>
    </svg>

    <div id="net_limit_warning" style="z-index:2000;position:absolute;top:0;right:0;border-radius:6px;background-color: rgb(172, 231, 255);font-weight:bold;color:red; padding:8px; display:none;">
    </div> <?php // Warning message for item limit ?>
</div>

<?php
}
?> <?php // End of conditional rendering ?>

<?php // Common elements for both modes ?>
<!-- HTML for information box shown when a node is clicked (initially hidden) -->
<div id="infoDiv" style="display:none; position:absolute; border:1px solid #ccc; background-color:white; padding:5px; z-index:1000;">
    <button id="btnCtrlNewtab" class="iframeControls" onclick="handleNodeAction('tab')" title="Open in new tab"><span class="ui-icon ui-icon-newwin"></span></button>
    <button id="btnCtrlPopup" class="iframeControls" onclick="handleNodeAction('popup')" title="Open in popup"><span class="ui-icon ui-icon-comment"></span></button>
    <button id="btnCtrlClose" class="iframeControls" onclick="handleNodeAction('close')" title="Close record viewer/info"><span class="ui-icon ui-icon-close"></span></button>
    <iframe title id="infoIframe"></iframe>
    <div id="infoBox" style="padding: 10px;"></div>
</div>

<div id="embed-dialog" style="display:none">
     <p>Embed this Network Diagram in your own web page. Enclose within &lt;code&gt; &lt;/code&gt;
 for Wordpress sites (the use of &lt;code&gt; may need to be enabled for your site).</p>
     <p style="padding:1em 0 1em 0;font-size:0.9em">Copy the following html code into your page where you want to place the graph, or use the URL on its own. The graph will be generated live from the database using the current search criteria whenever the graph is loaded. Use the web-safe version if the readable version does not work</p>
     <label style="font-size:0.9em" for="code-textbox">Readable code:</label>
     <textarea id="code-textbox" onclick="select(); if (window.clipboardData) clipboardData.setData('Text', value);" style="border: 1px dotted gray; padding: 3px; margin: 2; font-family: times; width: 100%; height: 60px;" readonly=""></textarea>
     <label style="font-size:0.9em" for="code-textbox2">Web-safe code:</label>
     <textarea id="code-textbox2" onclick="select(); if (window.clipboardData) clipboardData.setData('Text', value);" style="     border: 1px dotted gray; padding: 3px; margin: 2; font-family: times; width: 100%; height: 40px;" readonly=""></textarea>
</div>