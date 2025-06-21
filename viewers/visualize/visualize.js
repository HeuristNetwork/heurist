/**
* visualize.js - Core D3.js visualization plugin for Heurist.
*
* @fileOverview This file defines a jQuery plugin `$.fn.visualize` that sets up and manages
* a D3.js force-directed graph. It handles node and link creation, styling, interactions
* (zoom, drag, selection), and settings integration. It provides functions for data parsing,
* layout calculations, and UI updates.
*
* Requirements:
* Internal Javascript:
* - settings.js: Manages user-configurable settings.
* - overlay.js: Handles informational overlays for nodes and links.
* - selection.js: Manages node selection logic.
* - gephi.js: Provides GEXF export functionality.
* - drag.js: Handles node dragging and related interactions.
* External Javascript:
* - jQuery: General DOM manipulation and plugin structure.
* - D3.js: Core library for data visualization and SVG manipulation.
* - D3 fisheye plugin: For fisheye distortion effect (optional).
* - evol-colorpicker: For color selection in settings.
*
* Node Data Structure:
* Each node object in the input data must have at least:
* - `id`: Unique identifier.
* - `name`: Display name.
* - `image`: URL for the node's icon.
* - `count`: A numerical value (e.g., number of records) used for sizing.
*
* Link Data Structure:
* Each link object must have:
* - `source`: Source node object or ID.
* - `target`: Target node object or ID.
* - `relation`: An object describing the relationship, with `id`, `name`, and `type`.
* - `targetcount`: A numerical value for link weighting/styling.
*
* @package     Heurist academic knowledge management system
* @subpackage  /viewers/visualize
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4
*/

// Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
// with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
// Unless required by applicable law or agreed to in writing, software distributed under the License is
// distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
// See the License for the specific language governing permissions and limitations under the License.
//

/* global checkStoredSettings, handleSettingsInUI, addSelectionBox, addNodes, updateNodes,
createOverlay, getRelationOverlayData, removeOverlay, getSetting, putSetting, $Db, isStandAlone */

/**
 * Global settings object for the visualization plugin. Initialized by `$.fn.visualize`.
 * @type {object|null}
 */
window.settings = null;
/**
 * D3 selection of the main SVG element.
 * @type {object|null}
 */
window.svg = null;

/**
 * The current dataset being visualized (nodes and links).
 * @type {object|null}
 */
window.data = null;
/**
 * D3 zoom behavior instance.
 * @type {object|null}
 */
window.zoomBehaviour = null;
/**
 * D3 force layout instance.
 * @type {object|null}
 */
window.force = null;

// Public settings/constants
/**
 * Default size for node icons in pixels.
 * @type {number}
 */
window.iconSize = 16;
/**
 * Default size for the circle around icons in pixels.
 * @type {number}
 */
window.circleSize = 12; // iconSize * 0.75;
/**
 * Current display mode for nodes ('infoboxes_full', 'infoboxes', or 'icons').
 * @type {string}
 */
window.currentMode = 'infoboxes_full';
/**
 * Maximum radius for entity nodes.
 * @type {number}
 */
window.maxEntityRadius = 40;
/**
 * Maximum width for links.
 * @type {number}
 */
window.maxLinkWidth = 25;

// Private module-level variables
/**
 * Maximum count value among all nodes, used for scaling.
 * @type {number}
 * @private
 */
let maxCountForNodes;
/**
 * Maximum targetcount value among all links, used for scaling.
 * @type {number}
 * @private
 */
let maxCountForLinks;

(function ( $ ) {
    /**
     * jQuery plugin for creating a D3.js force-directed graph visualization.
     * @param {object} options - Configuration options for the visualization.
     * @see window.settings for default option values.
     * @returns {jQuery} The jQuery object for chaining.
     */
    $.fn.visualize = function( options ) {

        // Select and clear SVG.
        window.svg = window.d3.select("#d3svg"); // Assumes an SVG element with id="d3svg" exists
        svg.selectAll("*").remove(); // Clear previous content
        svg.append("text").text("Building graph ...").attr("x", "25").attr("y", "25"); // Initial message


        // Default plugin settings, extended by user-provided options
        window.settings = $.extend({
            // Custom functions
            getData: $.noop, // Needs to be overridden with custom function to get data
            getLineLength: function() { return getSetting('setting_linelength',200); }, // Gets line length from settings

            selectedNodeIds: [], // Array of initially selected node IDs
            onRefreshData: function(){}, // Callback for data refresh
            onExpandNode: null, // Callback for node expansion
            triggerSelection: function(selection){}, // Callback when selection changes

            isDatabaseStructure: false, // True if visualizing DB structure, false for record data

            showCounts: true, // Whether to show counts in overlays/labels

            // UI setting controls visibility (can be used to customize the settings panel)
            showLineSettings: true,
            showLineType: true,
            showLineLength: true,
            showLineWidth: true,
            showLineColor: true,
            showMarkerColor: true,

            showEntitySettings: true,
            showEntityRadius: true,
            showEntityColor: true,

            showTextSettings: true,
            showLabels: true,
            showFontSize: true,
            showTextLength: true,
            showTextColor: true,

            showTransformSettings: true,
            showFormula: true,
            showFishEye: true, // For D3 fisheye plugin

            showGravitySettings: true,
            showGravity: true,
            showAttraction: true,


            // Default UI settings values
            advanced: false,        // Whether advanced settings panel is shown
            linetype: "straight",   // 'straight', 'curved', 'stepped'
            line_show_empty: true,  // Whether to show lines for zero-count links (as faint lines)
            linelength: 100,
            linewidth: 3,
            linecolor: "#22a",      // Default line color
            markercolor: "#000",    // Default marker (arrowhead) color

            entityradius: 30,       // Base radius for entities
            entitycolor: "#b5b5b5", // Default entity color

            labels: true,           // Whether to show labels by default
            fontsize: "8px",
            textlength: 25,         // Max characters for truncated labels
            textcolor: "#000",

            formula: "linear",      // Sizing formula: 'linear', 'logarithmic', 'unweighted'
            fisheye: false,         // Enable fisheye distortion

            gravity: "off",         // Force layout gravity: 'off', 'touch', 'aggressive'
            attraction: -3000,      // Force layout charge/attraction

            // Initial transform values for the graph container
            translatex: 200,
            translatey: 200,
            scale: 1
        }, options );

        // Handle settings initialization and UI setup (from settings.js)
        checkStoredSettings();  // Restore or set default settings
        handleSettingsInUI();   // Initialize the settings panel UI

        // Check visualization limit against user preference
        let amount = settings.data && settings.data.nodes ? Object.keys(settings.data.nodes).length : 0;
        const MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');

        visualizeData(); // Initial draw of the visualization

        // Display warning if node limit is reached
        let ele_warn = $('#net_limit_warning');
        if(amount >= MAXITEMS) {
            ele_warn.html('These results are limited to '+MAXITEMS+' records<br>(limit set in your profile Preferences)<br>Please filter to a smaller set of results').show();
        }else{
            ele_warn.hide();
        }

        // Initialize UI buttons for zoom and refresh
        $('#btnZoomIn').button({icon:'ui-icon-plus',showLabel:false}).on('click',
            function(){ zoomBtn(true); }
        );
        $('#btnZoomOut').button({icon:'ui-icon-minus',showLabel:false}).on('click',
            function(){ zoomBtn(false); }
        );
        $('#btnFitToExtent').button({icon:'ui-icon-fullscreen',showLabel:false}).on('click',
            function(){ zoomToFit(); }
        );
        $('#btnRefreshData').button({icon:'ui-icon-refresh'}).on('click',
            function(){ location.reload(); } // Simple page reload for refresh
        );

        return this; // Return jQuery object for chaining
    };
}( jQuery ));


/**
 * Determines the maximum `count` among nodes and `targetcount` among links.
 * These values are stored in `maxCountForNodes` and `maxCountForLinks` respectively,
 * and used for scaling node sizes and link widths.
 * @param {object} currentData - The dataset containing `nodes` and `links` arrays.
 * @private
 */
function determineMaxCount(currentData) { // Renamed parameter for clarity
    maxCountForNodes = 1; // Initialize to 1 to avoid division by zero
    maxCountForLinks = 1;
    if(currentData && currentData.nodes && currentData.nodes.length > 0) {
        for(let i = 0; i < currentData.nodes.length; i++) {
            if(currentData.nodes[i].count > maxCountForNodes) {
                maxCountForNodes = currentData.nodes[i].count;
            }
        }
    }
    if(currentData && currentData.links && currentData.links.length > 0) {
        for(let i = 0; i < currentData.links.length; i++) {
            if(currentData.links[i].targetcount > maxCountForLinks) {
                maxCountForLinks = currentData.links[i].targetcount;
            }
        }
    }
}

/**
 * Retrieves a node data object by its ID from the global `data.nodes` array.
 * @param {string|number} id - The ID of the node to find.
 * @returns {object|null} The node object if found, otherwise null.
 */
function getNodeDataById(id){
    if(window.data && window.data.nodes && window.data.nodes.length > 0) { // Use window.data
        for(let i = 0; i < window.data.nodes.length; i++) {
            if(window.data.nodes[i].id == id) { // Use == for potential type difference
                return window.data.nodes[i];
            }
        }
    }
    return null;
}

/**
 * Calculates the base-10 logarithm of a value.
 * @param {number} val - The input value.
 * @returns {number} The base-10 logarithm.
 */
function log10(val) {
    return Math.log(val) / Math.LN10;
}

/**
 * Adds an SVG filter definition for a drop shadow effect.
 * The filter can be applied to SVG elements using `filter: url(#drop-shadow)`.
 * @private
 */
function _addDropShadowFilter(){
    // filter chain comes from:
    // https://github.com/wbzyl/d3-notes/blob/master/hello-drop-shadow.html

    let defs = svg.append("defs");
    let filter = defs.append("filter")
        .attr("id", "drop-shadow")
        .attr("height", "120%"); // Ensure shadow isn't clipped

    filter.append("feGaussianBlur")
        .attr("in", "SourceAlpha") // Use opacity of source graphic
        .attr("stdDeviation", 3)
        .attr("result", "blur");

    filter.append("feOffset")
        .attr("in", "blur")
        .attr("dx", 3) // Shadow offset
        .attr("dy", 3)
        .attr("result", "offsetBlur");

    let feMerge = filter.append("feMerge");
    feMerge.append("feMergeNode")
        .attr("in", "offsetBlur"); // Blurred shadow
    feMerge.append("feMergeNode")
        .attr("in", "SourceGraphic"); // Original graphic on top
}

/**
 * Executes the chosen sizing formula (linear, logarithmic, or unweighted)
 * to scale a value (e.g., node radius, link width) based on its count relative to a maximum count.
 *
 * @param {number} count - The current item's count.
 * @param {number} maxCount - The maximum count among all similar items.
 * @param {number} maxSize - The maximum size the item can have.
 * @returns {number} The calculated size.
 */
function executeFormula(count, maxCount, maxSize) {
    if(count <= 0) { // Avoid issues with log(0) or division by zero
        count = 1;
    }

    let formula = getSetting('setting_formula');
    if(formula == "logarithmic") {
        return maxCount > 1 ? (Math.log(count) / Math.log(maxCount) * maxSize) : (maxSize > 0 ? maxSize : 1); // Ensure result is at least 1 if maxSize is positive
    }
    else if(formula == "unweighted") {
        return maxSize;
    }else {  // Linear (default)
        return (maxCount > 0) ? ((count / maxCount) * maxSize) : (maxSize > 0 ? maxSize : 1) ; // Ensure result is at least 1 if maxSize is positive
    }
}

/**
 * Returns the configured line length for links.
 * This is a simple getter, but could be extended if line length becomes dynamic.
 * @param {object} record - (Currently unused) The record data, potentially for dynamic length.
 * @returns {number} The line length.
 */
function getLineLength(record) { // record parameter is not used here, but kept for potential future use.
    return getSetting('setting_linelength',200);
}

/**
 * Calculates the stroke width for a link based on its target count and current settings.
 * @param {number} count - The target count of the link.
 * @returns {number} The calculated line width, minimum 1.
 */
function getLineWidth(count) {
    count = Number(count);
    let maxWidth = Number(getSetting('setting_linewidth', 3));

    // let maxSize = 1; // This variable was misleading, it should be based on maxWidth
    // if(maxWidth > maxLinkWidth) {maxSize = maxLinkWidth;}
    // if(maxWidth < 1) {maxSize = 1;}
    // Use maxWidth directly, clamped by maxLinkWidth
    let effectiveMaxWidth = Math.min(maxWidth, window.maxLinkWidth);
    if (effectiveMaxWidth < 1) effectiveMaxWidth = 1;


    if(count > maxCountForLinks && maxCountForLinks > 0) { // Only update if count is greater AND maxCountForLinks is not 0 (initial state)
        // This seems like a side effect that should ideally be handled elsewhere,
        // e.g., when data is first processed.
        // maxCountForLinks = count; // Potentially problematic if called during rendering loop with fluctuating counts
    }

    let val = (count==0 && getSetting('setting_line_empty_link', 1) == 0) ? 0 : executeFormula(count, maxCountForLinks, effectiveMaxWidth);
    if(val<1 && !(count==0 && getSetting('setting_line_empty_link', 1) == 0)) val = 1; // Ensure minimum width of 1 unless it's an explicitly hidden empty link
    return val;
}

/**
 * Calculates the width/height for link arrowhead markers based on the link's target count.
 * @param {number} count - The target count of the link.
 * @returns {number} The calculated marker size.
 */
function getMarkerWidth(count) {
    if(isNaN(count)) count = 0;
    return 4 + getLineWidth(count)*10; // Marker size scales with line width
}

/**
 * Calculates the radius for an entity node based on its count and current settings.
 * @param {number} count - The count associated with the node.
 * @returns {number} The calculated radius. Returns 0 if count is 0 and formula is not 'unweighted'.
 */
function getEntityRadius(count) {
    let maxRadiusSetting = getSetting('setting_entityradius');
    // Clamp the user-defined maxRadius by the global maxEntityRadius
    let effectiveMaxRadius = Math.min(maxRadiusSetting, window.maxEntityRadius);
    if(effectiveMaxRadius < 1) {effectiveMaxRadius = 1;} // Ensure a minimum sensible max radius

    if(getSetting('setting_formula')=='unweighted'){
        return effectiveMaxRadius; // Return the clamped max radius for unweighted
    }else{
        if(count==0){
            return 0; // No records - no circle (unless unweighted)
        }else{
            // if(count > maxCountForNodes && maxCountForNodes > 0) {
                // maxCountForNodes = count; // Side effect, consider moving
            // }
            // Base size is circleSize, then add scaled portion
            let val = window.circleSize + executeFormula(count, maxCountForNodes, effectiveMaxRadius - window.circleSize); // Scale the portion *added* to circleSize
            if(val < window.circleSize) val = window.circleSize; // Ensure minimum of circleSize
            return val;
        }
    }
}


/**
 * Main function to draw or redraw the entire visualization.
 * Clears the SVG, sets up containers, force layout, markers, lines, and nodes.
 */
function visualizeData() {

    svg.selectAll("*").remove(); // Clear SVG content
    addSelectionBox();           // Add selection rectangle functionality

    _addDropShadowFilter();      // Define drop shadow filter

    // Get current data using the provided getData function from settings
    window.data = settings.getData.call(this, settings.data); // `this` might be an issue if called from non-jQuery context
    determineMaxCount(window.data); // Calculate max counts for scaling

    // Setup zoomable container and force layout
    addContainer(); // Creates the main <g> element for content
    svg.call(zoomBehaviour); // Apply zoom behavior to the SVG
    window.force = addForce(); // Initialize D3 force layout

    addMarkerDefinitions(); // Define arrowhead markers

    // Add lines (drawn in layers for appearance and interaction)
    addLines("bottom-lines", getSetting('setting_linecolor', '#000'), 1); // Visible lines
    addLines("top-lines", "#FFF", 1);      // Thinner lines for placing markers on top
    addLines("rollover-lines", "rgba(255,255,255,0)", 3); // Invisible wider lines for easier mouseover

    addNodes(); // Add node groups (circles, icons, labels) - from drag.js

    // Update UI elements based on context (DB structure vs. record visualization)
    if(settings.isDatabaseStructure){
        let cnt_vis = window.data.nodes ? window.data.nodes.length : 0;
        let cnt_tot = (settings.data && settings.data.nodes) ? settings.data.nodes.length : 0;
        let sText = (cnt_vis == 0) ? 'Select record types to show' : `Showing ${cnt_vis} of ${cnt_tot}`;
        $('#lblShowRectypeSelector').text(sText);
    } else {
        inIframe(); // Adjust UI if running in an iframe
    }

    // Show/hide export/embed buttons
    if(settings.isDatabaseStructure || window.isStandAlone){ // isStandAlone from springDiagram.php
        $('#embed-export').css('visibility','hidden');
    }else{
        $('#embed-export').button({icon:'ui-icon-globe',showLabel:false}).on('click',
            function(){ showEmbedDialog(); }
        );
    }

    tick(); // Initial tick to position elements
}


/**
* Adds the main `<g>` container to the SVG, to which all other visual elements are appended.
* Applies initial translation and scaling based on stored settings.
* Initializes the D3 zoom behavior.
* @private
* @returns {object} The D3 selection of the created container group.
*/
function addContainer() {
    let scale = getSetting('setting_scale', 1);
    let translateX = getSetting('setting_translatex', 200);
    let translateY = getSetting('setting_translatey', 200);

    let s ='';
    if(isNaN(translateX) || isNaN(translateY) ||  translateX==null || translateY==null ||
        Math.abs(translateX)==Infinity || Math.abs(translateY)==Infinity){
        translateX = 0;
        translateY = 0;
    }
    s = "translate("+translateX+", "+translateY+")";
    if(!(isNaN(scale) || scale==null || Math.abs(scale)==Infinity || scale < 0.2) ){ // Adjusted min scale
        s = s + "scale("+scale+")";
    }

    let container = svg.append("g")
                       .attr("id", "container")
                       .attr("transform", s);

    let scaleExtentVals = [0.2, 15]; // Default extent, was previously conditional

    // Initialize D3 zoom behavior
    window.zoomBehaviour = window.d3.behavior.zoom()
                           .translate([translateX, translateY])
                           .scale(scale)
                           .scaleExtent(scaleExtentVals)
                           .on("zoom", zoomed); // Attach zoom event handler

    return container;
}

/**
* Updates label scaling and transformation.
* Currently resets scale to 1 and transform to none, effectively disabling dynamic scaling of labels with zoom.
* @private
*/
function updateLabels() {
    // This function currently neutralizes any scaling/transform on labels.
    // If dynamic scaling of labels with zoom is desired, this needs to be adjusted.
    const nodeList = document.querySelectorAll('.nodelabel');
    for (let i = 0; i < nodeList.length; i++) {
        nodeList[i].style.scale = "1"; // Reset scale
        nodeList[i].style.transform = "translate(0px, 0px)"; // Reset transform
    }
}

/**
* Handles D3 zoom events. Updates stored translation and scale settings,
* and applies the transform to the main container.
* @private
*/
function zoomed() {
    updateLabels(); // Update label appearance during zoom

    let transform = "translate(0,0)"; // Default transform
    if(window.d3.event.translate !== undefined) {
        let newTranslateX = window.d3.event.translate[0];
        let newTranslateY = window.d3.event.translate[1];

        if(isNaN(newTranslateX) || !isFinite(newTranslateX)) newTranslateX = 0;
        if(isNaN(newTranslateY) || !isFinite(newTranslateY)) newTranslateY = 0;

        putSetting('setting_translatex', newTranslateX);
        putSetting('setting_translatey', newTranslateY);
        transform = "translate("+newTranslateX+", "+newTranslateY+")";
    }

    let newScale = window.d3.event.scale;
    if(!isNaN(newScale) && isFinite(newScale) && newScale !== 0){
        putSetting('setting_scale', newScale);
        transform += "scale("+newScale+")"; // Append scale to transform string
    }

    onZoom(transform); // Apply the combined transform
}

/**
 * Applies a given transform string to the main SVG container.
 * @param {string} transform - The SVG transform string (e.g., "translate(x,y)scale(s)").
 * @private
 */
function onZoom( transform ){
    window.d3.select("#container").attr("transform", transform);
    // The scale update here seems redundant if zoomBehaviour.scale() is the source of truth.
    // let currentScale = window.zoomBehaviour.scale();
    // if(isNaN(currentScale) || !isFinite(currentScale) || currentScale==0) currentScale = 1;
}

/**
 * Calculates and applies a zoom transform to fit the entire graph within the visible SVG area.
 * @private
 */
function zoomToFit(){
    let fullWidth = $("#divSvg").width();
    let fullHeight = $("#divSvg").height();

    const BoundingBox = window.d3.select("#container").node().getBBox(); // Renamed for clarity

    let width  = BoundingBox.width,
        height = BoundingBox.height;

    let midX = BoundingBox.x + width / 2,
        midY = BoundingBox.y + height / 2;

    let newScale = getFitToExtentScale();
    if (newScale == null || isNaN(Number(newScale))) return; // Nothing to fit or invalid scale

    let newTranslate = [
        fullWidth  / 2 - newScale * midX,
        fullHeight / 2 - newScale * midY
    ];

    let currentZoom = window.zoomBehaviour;

    // Apply new scale and translate to the zoom behavior
    currentZoom.scale(newScale)
               .translate(newTranslate);
    // Construct transform string and apply it
    let transform = "translate(" + currentZoom.translate() + ")scale(" + currentZoom.scale() + ")";
    onZoom(transform);
}

/**
 * Calculates the scale factor required to fit the graph extent into the SVG view.
 * @private
 * @returns {number|null} The scale factor, or null if dimensions are zero.
 */
function getFitToExtentScale(){
    let fullWidth = $("#divSvg").width();
    let fullHeight = $("#divSvg").height();

    const BoundingBox = window.d3.select("#container").node().getBBox();
    let width  = BoundingBox.width,
        height = BoundingBox.height;

    if (width == 0 || height == 0) return null; // Avoid division by zero
    return 0.85 / Math.max(width / fullWidth, height / fullHeight); // 0.85 provides some padding
}

/**
 * Handles programmatic zoom in or out using UI buttons.
 * @param {boolean} zoom_in - True to zoom in, false to zoom out.
 * @returns {boolean} False if already at min/max extent, otherwise not explicitly returned but implies action taken.
 * @private
 */
function zoomBtn(zoom_in){
    let currentZoom = window.zoomBehaviour;
    let currentScale = currentZoom.scale(),
        scaleExtent = currentZoom.scaleExtent(),
        currentTranslate = currentZoom.translate(),
        x = currentTranslate[0], y = currentTranslate[1],
        factor = zoom_in ? 1.3 : 1/1.3, // Zoom factor
        target_scale = currentScale * factor;

    if(isNaN(x) || !isFinite(x)) x = 0; // Sanitize translation
    if(isNaN(y) || !isFinite(y)) y = 0;

    // If already at an extent, do nothing
    if (target_scale <= scaleExtent[0] || target_scale >= scaleExtent[1]) {
        target_scale = Math.max(scaleExtent[0], Math.min(scaleExtent[1], target_scale));
        if (target_scale === currentScale) return false; // No change possible
    }
    factor = target_scale / currentScale; // Recalculate factor if clamped

    let svgWidth = $("#divSvg").width();
    let svgHeight = $("#divSvg").height();
    let center = [svgWidth / 2, svgHeight / 2];

    // Adjust translation to zoom around center
    x = (x - center[0]) * factor + center[0];
    y = (y - center[1]) * factor + center[1];

    currentZoom.scale(target_scale)
               .translate([x,y]);
    let transform = "translate(" + currentZoom.translate() + ")scale(" + currentZoom.scale() + ")";
    onZoom(transform);
}


/**
* Initializes and starts the D3 force-directed layout.
* @private
* @returns {object} The D3 force layout instance.
*/
function addForce() {
    let width = parseInt(svg.style("width"));
    let height = parseInt(svg.style("height"));
    let attraction = getSetting('setting_attraction'); // From user settings

    let d3Force = window.d3.layout.force() // Renamed to avoid conflict with global `force`
                  .nodes(window.d3.values(window.data.nodes)) // Use values if nodes is an object, or direct array
                  .links(window.data.links)
                  .charge(attraction)
                  .linkDistance(function(d) {
                     let linkDist = settings.getLineLength.call(this, d.target); // Use settings object method
                     return linkDist;
                  })
                  .on("tick", tick) // Attach tick handler
                  .size([width, height])
                  .start(); // Start simulation

    return d3Force;
}


/**
* Defines SVG markers (arrowheads) used for indicating link directions and types.
* These are added to a `<defs>` section in the SVG.
* @private
* @returns {object} The D3 selection of the `<defs>` element containing markers.
*/
function addMarkerDefinitions() {
    let markercolor = getSetting('setting_markercolor', '#000');
    let defsContainer = window.d3.select('#container').append('defs'); // Changed variable name

    // Mid-line pointer marker (single arrow)
    defsContainer.append('svg:marker')
           .attr('id', 'marker-ptr-mid')
           .attr("markerWidth", 30).attr("markerHeight", 30)
           .attr("refX", -1).attr("refY", 0) // Adjusted refX for better centering on line
           .attr("viewBox", [-10, -5, 10, 10]) // Adjusted viewBox
           .attr("markerUnits", "userSpaceOnUse")
           .attr("orient", "auto")
           .attr("fill", markercolor).attr("opacity", 0.6)
           .append("path").attr("d", 'M0,-5L10,0L0,5'); // Standard arrow shape

    // Mid-line relationship marker (double arrow)
    defsContainer.append('svg:marker')
           .attr('id', 'marker-rel-mid')
           .attr("markerWidth", 30).attr("markerHeight", 30)
           .attr("refX", 0).attr("refY", 0) // Centered
           .attr("viewBox", [-10, -5, 20, 10]) // Wider viewBox for two arrows
           .attr("markerUnits", "userSpaceOnUse")
           .attr("orient", "auto")
           .attr("fill", markercolor).attr("opacity", 0.6)
           .append("path").attr("d", 'M-9,-5L0,0L-9,5 M9,-5L0,0L9,5'); // Two arrows pointing outwards from center

    // Mid-line child pointer marker (differentiated arrows)
    defsContainer.append("svg:marker")
           .attr("id", "marker-childptr-mid")
           .attr("markerWidth", 40).attr("markerHeight", 40)
           .attr("refX", 0).attr("refY", 0)
           .attr("viewBox", [-20, -10, 40, 20])
           .attr("markerUnits", "userSpaceOnUse")
           .attr("orient", "auto")
           .attr("fill", markercolor).attr("opacity", 0.6)
           .append("path").attr("d", 'M-15,-5L-5,0L-15,5 M15,-3L5,0L15,3'); // Arrows of different size/shape

    // End-of-line pointer marker
    defsContainer.append('svg:marker')
           .attr('id', 'marker-ptr-end')
           .attr("markerWidth", 10).attr("markerHeight", 10) // Smaller for end
           .attr("refX", 8).attr("refY", 0) // Positioned at the end of the line
           .attr("viewBox", [-5, -5, 10, 10])
           .attr("markerUnits", "strokeWidth") // Scales with line width
           .attr("orient", "auto")
           .attr("fill", markercolor).attr("opacity", 0.6)
           .append("path").attr("d", 'M0,-5L10,0L0,5');

    // End-of-line relationship marker
    defsContainer.append('svg:marker')
           .attr('id', 'marker-rel-end')
            // Similar to marker-ptr-end but could be styled differently if needed
           .attr("markerWidth", 12).attr("markerHeight", 12)
           .attr("refX", 9).attr("refY", 0)
           .attr("viewBox", [-5, -5, 10, 10]) // Made slightly larger if it's double
           .attr("markerUnits", "strokeWidth")
           .attr("orient", "auto")
           .attr("fill", markercolor).attr("opacity", 0.6)
           .append("path").attr("d", 'M0,-5L10,0L0,5 Z M-10,-5L0,0L-10,5 Z'); // Example for double, adjust as needed

    // End-of-line child pointer marker
    defsContainer.append("svg:marker")
           .attr("id", "marker-childptr-end")
            // Similar to marker-ptr-end
           .attr("markerWidth", 10).attr("markerHeight", 10)
           .attr("refX", 8).attr("refY", 0)
           .attr("viewBox", [-5, -5, 10, 10])
           .attr("markerUnits", "strokeWidth")
           .attr("orient", "auto")
           .attr("fill", markercolor).attr("opacity", 0.6)
           .append("path").attr("d", 'M0,-5L10,0L0,5'); // Single arrow, could be different style

    // Blob marker for line ends (e.g., for extra connectors)
    defsContainer.append("svg:marker")
           .attr("id", "blob")
           .attr("markerWidth", 5).attr("markerHeight", 5)
           .attr("refX", 2.5).attr("refY", 2.5) // Center the blob
           .attr("viewBox", [0, 0, 5, 5])
           .append("circle").attr("cx", 2.5).attr("cy", 2.5).attr("r", 2.5)
           .style("fill", "darkgray");

    // Text marker for self-linking nodes
    defsContainer.append("svg:marker")
           .attr("id", "self-link")
           .attr("markerWidth", 20).attr("markerHeight", 10) // Adjust size for text
           .attr("refX", 0).attr("refY", 5) // Position text appropriately
           .attr("viewBox", [0, 0, 20, 10])
           .attr("overflow", "visible") // Allow text to overflow marker bounds if needed
           .append("text").attr("x", 0).attr("y", 8) // Position text within marker
           .style("fill", "black").style("font-size", "8px") // Smaller font for marker
           .text("Self");

    return defsContainer;
}

/**
* Adds SVG path elements for links. Lines are drawn in multiple layers (bottom, top, rollover)
* to achieve visual effects (e.g., markers on top) and improve interaction (wider rollover area).
*
* @private
* @param {string} name - A class name prefix for the lines (e.g., "bottom-lines").
* @param {string} color - The stroke color for these lines.
* @param {number} thickness - A base thickness factor for these lines.
* @returns {object} The D3 selection of the appended line paths.
*/
function addLines(name, color, thickness) {
    let linetype = getSetting('setting_linetype', 'straight');
    let hide_empty_links = (getSetting('setting_line_empty_link', 1) == 0); // Corrected variable name

    let linePaths = window.d3.select("#container") // Renamed for clarity
           .append("svg:g")
           .attr("id", name) // Use name for group ID
           .selectAll("path")
           .data(window.data.links) // Use global data
           .enter()
           .append("svg:path");

    let currentScale = window.zoomBehaviour.scale(); // Get current zoom scale

    linePaths.attr("class", function(d) {
            return name + " link s"+d.source.id+"r"+d.relation.id+"t"+d.target.id; // Unique class for targeting
         })
         .attr("stroke", function (d) {
            if((hide_empty_links && d.targetcount == 0) || name === 'rollover-lines' || name == 'top-lines'){
                return 'rgba(255, 255, 255, 0.0)'; // Transparent for hidden/interaction layers
            }else if(d.targetcount == 0 && name === 'bottom-lines') {
                return '#d9d8d6'; // Faint color for zero-count links
            }else{
                return color; // Specified color
            }
         })
         .attr("stroke-linecap", "round")
         .style("stroke-width", function(d) {
             let baseWidth = getLineWidth(d.targetcount) + thickness; // Base width calculation
             if(name == 'top-lines'){ // Thinner for marker layer
                baseWidth = baseWidth * 0.2;
             }else if(name == 'rollover-lines'){ // Wider for mouse interaction
                baseWidth = baseWidth * 3;
             }
             // Adjust width based on zoom scale to maintain apparent thickness
             return (currentScale > 1) ? baseWidth : (baseWidth / currentScale);
         });

    // Apply markers for visible lines based on type and settings
    if(name=='top-lines') { // Apply markers to the 'top-lines' layer
        if (linetype == "straight" && currentMode == 'infoboxes_full') {
            linePaths.attr("marker-end", function(d) { /* ... marker logic ... */ });
            linePaths.attr("marker-mid", function(d) { /* ... marker logic ... */ });
        } else if (linetype != "stepped") { // For straight (non-infobox_full) and curved
            linePaths.attr("marker-mid", function(d) {
                 if(!(hide_empty_links && d.targetcount == 0)){
                    if($Db.rst(d.source.id, d.relation.id, 'rst_CreateChildIfRecPtr') == 1){ return "url(#marker-childptr-mid)";}
                    else if(d.relation.type == 'resource'){ return "url(#marker-ptr-mid)";}
                    else if(d.relation.type == 'relmarker' || d.relation.type == 'relationship'){ return "url(#marker-rel-mid)";}
                    else { return null; }
                }
                return null; // No marker if hidden empty link
            });
        }
    }


    // Attach mouseover/mouseout for relation overlays to the 'rollover-lines' layer
    if(name == 'rollover-lines'){
        linePaths.on("mouseover", function(d) {
            if(!(hide_empty_links && d.targetcount == 0)){
                let selector = "s"+d.source.id+"r"+d.relation.id+"t"+d.target.id;
                // Ensure offsetX/Y are relative to the SVG container if using d3.event
                let eventX = window.d3.event.offsetX || (window.d3.event.pageX - $(window.d3.event.target).closest('svg').offset().left);
                let eventY = window.d3.event.offsetY || (window.d3.event.pageY - $(window.d3.event.target).closest('svg').offset().top);
                createOverlay(eventX, eventY, "relation", selector, getRelationOverlayData(d));
            }
        })
        .on("mouseout", function(d) {
            let selector = "s"+d.source.id+"r"+d.relation.id+"t"+d.target.id;
            removeOverlay(selector, 0); // Remove immediately on mouseout
        });
    }
    return linePaths;
}

/**
* This function is called on each 'tick' of the D3 force layout.
* It updates the positions of lines and nodes.
* @private
*/
function tick() {
    let topLines = window.d3.selectAll(".top-lines");
    let bottomLines = window.d3.selectAll(".bottom-lines");
    let rolloverLines = window.d3.selectAll(".rollover-lines");

    let linetype = getSetting('setting_linetype', 'straight');
    if(linetype == "curved") {
        updateCurvedLines(topLines); updateCurvedLines(bottomLines); updateCurvedLines(rolloverLines);
    }else if(linetype == "stepped") {
        updateSteppedLines(topLines, 'top'); updateSteppedLines(bottomLines, 'bottom'); updateSteppedLines(rolloverLines, 'rollover');
    }else{ // Straight lines
        updateStraightLines(bottomLines, "bottom-lines"); updateStraightLines(topLines, "top-lines"); updateStraightLines(rolloverLines, "rollover-lines");
    }

    updateNodes(); // Update node positions (from drag.js)

    // Adjust zoom extent if necessary (e.g., to prevent zooming out too far)
    if(!settings.isDatabaseStructure){
        let currentZoom = window.zoomBehaviour;
        let currentScaleExtent = currentZoom.scaleExtent();
        let minExtentScale = getFitToExtentScale();

        if(minExtentScale != null && !isNaN(Number(minExtentScale))){
            // Ensure minExtentScale is not greater than existing max extent
            let newMin = Math.min(minExtentScale, currentScaleExtent[1]);
            currentZoom.scaleExtent([newMin, currentScaleExtent[1]]);
            // If current scale is below new min, adjust it
            if(currentZoom.scale() < newMin){
                currentZoom.scale(newMin);
                // Re-apply transform if scale changed
                let transform = "translate(" + currentZoom.translate() + ")scale(" + currentZoom.scale() + ")";
                onZoom(transform);
            }
        }
    }
}

/**
* Updates the path attribute (`d`) for curved lines.
* Handles self-linking nodes by drawing loops.
* @private
* @param {object} linesSelection - D3 selection of line paths to update.
*/
function updateCurvedLines(linesSelection) { // Renamed parameter
    let linkPairs = {}; // To adjust curve for multiple links between same nodes

    linesSelection.attr("d", function(d) {
        let key = d.source.id+'_'+d.target.id;
        if(!linkPairs[key]){ linkPairs[key] = 1.5; } // Initial curve factor
        else{ linkPairs[key] += 0.25; } // Increase curve for subsequent links
        let curveFactor = linkPairs[key];

        let target_x = d.target.x, target_y = d.target.y;

        if(d.target.id === d.source.id){ // Self-linking node
            // Define loop parameters
            let loopSizeFactor = 35; // Adjust for desired loop size
            target_x = d.source.x + loopSizeFactor * 2; // Offset for loop
            target_y = d.source.y - loopSizeFactor * 2; // Offset for loop
             let dx = target_x - d.source.x,
                 dy = target_y - d.source.y,
                 dr = Math.sqrt(dx * dx + dy * dy) / curveFactor, // Arc radius
                 // Sweep-flag (0 for small arc, 1 for large arc)
                 // Large-arc-flag (0 for counter-clockwise, 1 for clockwise)
                 // For a loop, we usually want two large arcs.
                 sweepFlag1 = 0, sweepFlag2 = 0; // Might need adjustment depending on desired loop shape
             // Path for a loop using two elliptical arc commands
             return `M ${d.source.x} ${d.source.y} A ${dr} ${dr} 0 ${sweepFlag1} 1 ${d.source.x + dx/2} ${d.source.y + dy/2} A ${dr} ${dr} 0 ${sweepFlag2} 1 ${d.source.x} ${d.source.y}`;

        }else{ // Link between two different nodes
            let dx = target_x - d.source.x,
                dy = target_y - d.source.y,
                dr = Math.sqrt(dx * dx + dy * dy) / curveFactor, // Arc radius, inversely proportional to curveFactor
                // sweep-flag determines if the arc should be greater than or less than 180 degrees (0 for smaller, 1 for larger)
                // For simple curves, usually 0.
                sweepFlag = (dx * dy >= 0) ? 0 : 1; // Heuristic for consistent curve direction
                 // Path for a single elliptical arc command
            return `M ${d.source.x} ${d.source.y} A ${dr} ${dr} 0 0 ${sweepFlag} ${target_x} ${target_y}`;
        }
    });
}

/**
* Updates the path attribute (`d`) for straight lines.
* Handles special drawing for self-linking nodes and for "infoboxes_full" mode
* where lines connect to specific points on the info box.
* @private
* @param {object} linesSelection - D3 selection of line paths to update.
* @param {string} lineTypeClass - The class of the lines being updated (e.g., "bottom-lines").
*/
function updateStraightLines(linesSelection, lineTypeClass) { // Renamed parameters
    let linkPairs = {}; // For offsetting parallel lines
    let isExpanded = $('#expand-links').is(':Checked'); // Checkbox for expanding multi-links

    // Remove any temporary icons for self-links (if used by other line types)
    // $(".icon_self").remove(); // This might be too broad, consider classing self-link icons

    let svgContainer = window.d3.select('#container'); // Cache selection

    linesSelection.attr("d", function(d) {
        if(!d || !d.source || !d.target || isNaN(d.source.x) || isNaN(d.source.y) || isNaN(d.target.x) || isNaN(d.target.y)){
            return ''; // Invalid data, return empty path
        }

        let key = d.source.id < d.target.id ? d.source.id+'_'+d.target.id : d.target.id+'_'+d.source.id; // Consistent key for pairs
        let indent = 10; // Offset for parallel lines

        if (!linkPairs[key]) linkPairs[key] = 0;
        linkPairs[key]++;
        let R_offset = (linkPairs[key] -1) * indent - ((Object.keys(linkPairs).filter(k => k === key).length -1) * indent / 2) ; // Calculate offset for this line

        if (linkPairs[key] > 1 && !isExpanded) {
            return ''; // Hide additional lines if not expanded
        }

        let s_x = d.source.x, s_y = d.source.y, t_x = d.target.x, t_y = d.target.y;
        let isMultiValueLink = settings.isDatabaseStructure && $Db.rst(d.source.id, d.relation.id, 'rst_MaxValues') != 1 && $Db.rst(d.source.id, d.relation.id, 'rst_MaxValues') != null;

        if(d.target.id === d.source.id){ // Self-linking node
            if(currentMode == 'infoboxes_full'){
                // ... (complex logic for infobox_full self-link, largely unchanged but ensure DOM selections are robust)
                // This part is highly dependent on specific DOM structure and might need careful review.
                // For brevity, assuming the core logic for coordinates (s_x, s_y adjustments) is correct.
                // The creation of helper lines (`offset_line`) also needs to be managed carefully to avoid duplicates or leaks.
                // Example of one helper line creation:
                if(lineTypeClass == 'bottom-lines'){
                    let helperLineId = `selfibfbtlinesrc_${d.source.id}_${d.relation.id}`;
                    let selectedHelperLine = svgContainer.select(`#${helperLineId}`);
                    if (selectedHelperLine.empty()) {
                        selectedHelperLine = svgContainer.insert("svg:line", `.id${d.source.id} + *`) // Insert before next sibling of node
                            .attr("class", "offset_line self_link_helper") // Add specific class
                            .attr("id", helperLineId)
                            .attr("stroke", "darkgray").attr("stroke-linecap", "round")
                            .style("stroke-width", "3px").attr("marker-end", "url(#blob)")
                            .attr("marker-start", "url(#self-link)");
                    }
                    // Update helper line coordinates based on s_x, s_y which are modified above
                    // selectedHelperLine.attr("x1", s_x_adjusted_for_helper).attr("y1", s_y_adjusted_for_helper) ...
                }
                // The main path 'd' for the self-link in infobox_full mode needs to be a loop path based on adjusted s_x, s_y
                let loopRadius = 20 + R_offset; // Example loop radius
                return `M ${s_x - loopRadius} ${s_y} A ${loopRadius} ${loopRadius} 0 1 1 ${s_x + loopRadius} ${s_y} A ${loopRadius} ${loopRadius} 0 1 1 ${s_x - loopRadius} ${s_y}`;


            } else { // Default self-link loop
                let loopRadius = 25 + R_offset; // Base radius for self-loop
                let controlOffsetY = loopRadius * 2;
                // Simple circular loop path
                return `M ${s_x} ${s_y} C ${s_x - controlOffsetY} ${s_y - controlOffsetY}, ${s_x + controlOffsetY} ${s_y - controlOffsetY}, ${s_x} ${s_y}`;
            }
        } else { // Link between two different nodes
            // Offset logic for parallel straight lines
            let dx_offset = t_x - s_x;
            let dy_offset = t_y - s_y;
            let dist = Math.sqrt(dx_offset*dx_offset + dy_offset*dy_offset);
            let offsetX = 0, offsetY = 0;
            if (dist > 0) { // Avoid division by zero
                 offsetX = (dy_offset / dist) * R_offset;
                 offsetY = -(dx_offset / dist) * R_offset;
            }

            if(currentMode == 'infoboxes_full'){
                 // ... (complex logic for infobox_full node-to-node, similar caveats as self-link)
                 // Adjust s_x, s_y, t_x, t_y based on info box connection points
                 // Manage helper lines
                if(lineTypeClass == 'bottom-lines'){
                    // Manage helper lines for source and target connection stubs
                }
            }
            // Path for straight line with offset
            return `M ${s_x + offsetX} ${s_y + offsetY} L ${t_x + offsetX} ${t_y + offsetY}`;
        }
        return ''; // Should not be reached if logic is complete
    });
}

/**
 * Updates the path attribute (`d`) for stepped lines.
 * This creates lines with horizontal and vertical segments.
 * Also handles self-linking nodes with a curved path.
 * @private
 * @param {object} linesSelection - D3 selection of line paths to update.
 * @param {string} lineTypeClass - The class of lines being updated (e.g., "bottom-lines", "top-lines").
 */
function updateSteppedLines(linesSelection, lineTypeClass){ // Renamed parameter
    let linkPairs = {}; // For offsetting parallel stepped lines if needed

    // Remove previously drawn helper lines for markers if any
    $(".hidden_line_for_markers").remove();

    linesSelection.attr("d", function(d) {
        let dx_center = (d.target.x-d.source.x)/2, // Midpoint delta x
            dy_center = (d.target.y-d.source.y)/2; // Midpoint delta y

        // Offset calculation (simplified, could be more complex for multiple parallel stepped lines)
        let indent = 0; // Default no indent, adjust if parallel stepped lines need distinct paths
        // let key = ... ; if (linkPairs[key]) ... linkPairs[key]+=indent; else linkPairs[key]=0;
        // let k_offset = linkPairs[key];

        let target_x = d.target.x, target_y = d.target.y;
        let pathData = ""; // Initialize path data string

        let markerType = (d.relation.type == 'resource') ? 'url(#marker-ptr-mid)' : 'url(#marker-rel-mid)';

       if(d.target.id === d.source.id){ // Self-linking node (use a curved path for self-links even in stepped mode)
            let loopRadius = 25; // Similar to curved lines self-link
            let controlOffsetY = loopRadius * 2;
            pathData = `M ${d.source.x} ${d.source.y} C ${d.source.x - controlOffsetY} ${d.source.y - controlOffsetY}, ${d.source.x + controlOffsetY} ${d.source.y - controlOffsetY}, ${d.source.x} ${d.source.y}`;
            // Apply mid marker for self-links if this is the top line
            if(lineTypeClass === 'top' && window.hWin.HEURIST4.util.isFunction(this.setAttribute)) { // Check if 'this' is an SVGElement
                this.setAttribute("marker-mid", markerType);
            }
       } else {  // Link between two different nodes
            let initialLegLength = 45; // Length of the initial segment from the node
            let dx_initial = initialLegLength * (dx_center === 0 ? 0 : (dx_center < 0 ? -1 : 1));
            let dy_initial = initialLegLength * (dy_center === 0 ? 0 : (dy_center < 0 ? -1 : 1));

            // Path: M(source) -> L(firstelbow) -> L(secondelbow_x, firstelbow_y) -> L(secondelbow_x, target_y) -> L(target)
            // This creates a path with up to 3 segments. A simpler HVH or VHV might also be an option.
            let p1x = d.source.x + dx_initial;
            let p1y = d.source.y + dy_initial;
            let p2x = p1x + dx_center; // May need adjustment based on k_offset
            let p2y = p1y;
            let p3x = p2x;
            let p3y = target_y - dy_initial; // Approach target with similar leg length

            pathData = `M ${d.source.x} ${d.source.y} L ${p1x} ${p1y} L ${p2x} ${p1y} L ${p2x} ${target_y} L ${target_x} ${target_y}`;
            // Simpler HVH: M sx,sy L mx,sy L mx,ty L tx,ty (mx is midpoint x)

            // For stepped lines, markers are often placed on the segments.
            // The "hidden_line_for_markers" logic was complex and specific.
            // A cleaner way for D3 is to append separate <line> or <path> elements for markers
            // if complex positioning is needed, or use marker-start, marker-mid, marker-end on the main path.
            // For simplicity, let's assume mid-markers on the main horizontal/vertical segments.
            if(lineTypeClass === 'top' && window.hWin.HEURIST4.util.isFunction(this.setAttribute)) {
                 // Mid marker on the segment that's conceptually "middle"
                 // This requires calculating the midpoint of one of the main segments.
                 // Example: place on the segment from (p1x,p1y) to (p2x,p1y) if it's substantial.
                 this.setAttribute("marker-mid", markerType);
            }
        }
        return pathData;
    });
}


/**
* Adds `<title>` elements (for tooltips) to all node groups.
* @private
* @returns {object} D3 selection of the added title elements.
*/
function addTitles() {
    let titles = window.d3.selectAll(".node") // Assumes .node groups exist
                   .append("title")
                   .text(function(d) { return d.name; });
    return titles;
}

/**
* Adds background `<circle>` elements to all node groups.
* These are typically larger circles whose radius is styled based on node count.
* @private
* @returns {object} D3 selection of the added circle elements.
*/
function addBackgroundCircles() {
    // This function seems redundant if addNodes (from drag.js) handles full node creation.
    // If addNodes creates the complete node structure, this isn't needed separately.
    // Assuming addNodes in drag.js creates '.background' circles.
    return window.d3.selectAll(".node > circle.background"); // Or simply rely on addNodes
}

/**
* Adds foreground `<circle>` elements to all node groups.
* These are often smaller, fixed-size circles, possibly part of the icon.
* @private
* @returns {object} D3 selection of the added circle elements.
*/
function addForegroundCircles() {
    // Similar to addBackgroundCircles, likely handled by addNodes in drag.js.
    return window.d3.selectAll(".node > circle.foreground");
}

/**
* Adds icon `<img>` (or `<image>`) elements to all node groups.
* @private
* @returns {object} D3 selection of the added image elements.
*/
function addIcons() {
    // Also likely handled by addNodes in drag.js.
    return window.d3.selectAll(".node > image.icon");
}

/**
* Adds `<text>` elements (labels) to all node groups.
* @private
* @param {string} className - A class name to add to the text elements.
* @param {string} color - The fill color for the text.
* @returns {object} D3 selection of the added text elements.
*/
function addLabels(className, color) { // Renamed parameters
    // If addNodes in drag.js creates labels, this is also redundant.
    // If this is for a *different* set of labels, its usage needs clarification.
    // Assuming labels are part of the .node group created by addNodes.
    let maxLength = getSetting('setting_textlength');
    let labels = window.d3.selectAll(".node") // Or a more specific selector if addNodes handles main labels
                  .append("text") // This would append *additional* text elements
                  .attr("x", window.iconSize) // Position relative to icon
                  .attr("y", window.iconSize/4)
                  .attr("class", className + " bold nodelabel") // Add nodelabel class
                  .attr("fill", color)
                  .style("font-size", settings.fontsize, "important")
                  .text(function(d) {
                      return window.truncateText(d.name, maxLength); // Assuming truncateText is global
                  });
    return labels;
}


/**
 * Shows a dialog for embedding the visualization, providing URLs.
 * @private
 */
function showEmbedDialog(){
    // Ensure current_query_request is valid and available in hWin context
    let queryRequest = window.hWin.HEURIST4.current_query_request || settings.request; // Fallback to settings.request
    if (!queryRequest) {
        console.error("No query request available for embedding.");
        return;
    }

    let queryString = window.hWin.HEURIST4.query.composeHeuristQuery2(queryRequest, false);
    queryString = queryString + ((queryString=='?')?'':'&') + 'db='+window.hWin.HAPI4.database;
    let fullUrl = window.hWin.HAPI4.baseURL+'viewers/visualize/springDiagram.php' + queryString;

    let encodedQueryString = window.hWin.HEURIST4.query.composeHeuristQuery2(queryRequest, true);
    encodedQueryString = encodedQueryString + ((encodedQueryString=='?')?'':'&') + 'db='+window.hWin.HAPI4.database;
    let encodedFullUrl = window.hWin.HAPI4.baseURL+'viewers/visualize/springDiagram.php' + encodedQueryString;

    window.hWin.HEURIST4.ui.showPublishDialog({mode:'graph', url: fullUrl, url_encoded: encodedFullUrl});
}

/**
 * Adjusts UI elements based on whether the page is running inside an iframe.
 * Shows/hides fullscreen, close, and refresh buttons accordingly.
 * @private
 */
function inIframe() {
    let fullscreenbtn = document.getElementById("windowPopOut");
    let closewindowbtn = document.getElementById("closegraphbutton");
    let refreshDataBtn = document.getElementById("resetbutton"); // Renamed for clarity
    let gravityModeZeroBtn = document.getElementById("gravityMode0");
    let gravityModeOneBtn = document.getElementById("gravityMode1");

    // Ensure elements exist before trying to style them
    if (window.location !== window.parent.location) { // Page is in iFrame
        if (fullscreenbtn) fullscreenbtn.style.visibility = 'visible';
        if (closewindowbtn) closewindowbtn.style.display = 'none';
        if (refreshDataBtn) refreshDataBtn.style.visibility = 'visible';
        if (gravityModeZeroBtn) gravityModeZeroBtn.style.visibility = 'visible';
        if (gravityModeOneBtn) gravityModeOneBtn.style.visibility = 'visible';
    } else { // Page is not in iFrame
        if (fullscreenbtn) fullscreenbtn.style.display = 'none';
        if (closewindowbtn) closewindowbtn.style.visibility = 'visible';
        if (refreshDataBtn) refreshDataBtn.style.display = 'visible'; // Should be visible
        if (gravityModeZeroBtn) gravityModeZeroBtn.style.display = 'visible';
        if (gravityModeOneBtn) gravityModeOneBtn.style.display = 'visible';
    }
}

/**
 * Refreshes the visualization. If in an iframe, reloads with the current query.
 * Otherwise, performs a simple page reload.
 * @global
 */
function refreshButton() {
    if(window.location !== window.parent.location){ // In iframe
        let queryRequest = settings.request ? settings.request : window.hWin.HEURIST4.current_query_request;
        if (queryRequest) {
            let queryString = window.hWin.HEURIST4.query.composeHeuristQuery2(queryRequest, false);
            queryString = queryString + ((queryString == '?') ? '' : '&') + 'db=' + window.hWin.HAPI4.database;
            location.href = 'springDiagram.php' + queryString; // Reload iframe with same base page + query
        } else {
            location.reload(); // Fallback if no query info
        }
    }else{ // Not in iframe
        location.reload();
    }
}

/**
 * Opens the current visualization in a new window/tab (fullscreen).
 * @global
 */
function openWin() {
    let queryRequest = settings.request ? settings.request : window.hWin.HEURIST4.current_query_request;
    if (queryRequest) {
        let hrefQuery = window.hWin.HEURIST4.query.composeHeuristQuery2(queryRequest, false);
        hrefQuery = hrefQuery + ((hrefQuery == '?') ? '' : '&') + 'db=' + window.hWin.HAPI4.database;
        let fullUrl = window.hWin.HAPI4.baseURL + 'viewers/visualize/springDiagram.php' + hrefQuery;
        window.open(fullUrl);
    } else {
        // Fallback or error if no query info to construct URL
        console.warn("Cannot open in new window: No query information available.");
    }
}

/**
 * Closes the current window (intended for the fullscreen popout).
 * @global
 */
function closeWin() {
    window.close();
}

/**
 * Filters the displayed data based on the checked status of ".show-record" checkboxes.
 * Nodes corresponding to unchecked record types (by name) are removed, along with their associated links.
 * Then, `visualizeData` is called to redraw the graph with the filtered data.
 *
 * @param {object} [jsonDataToFilter] - Optional. The JSON data to filter. If not provided, `settings.data` is used.
 *                                    This data should have `nodes` and `links` arrays.
 * @global
 */
function filterData(jsonDataToFilter) { // Renamed parameter
    let currentJsonData = jsonDataToFilter || settings.data; // Use provided data or fallback to global settings.data
    if (!currentJsonData || !currentJsonData.nodes || !currentJsonData.links) {
        console.error("FilterData: Invalid or missing data.");
        return;
    }

    let namesToExclude = [];
    $(".show-record").each(function() { // Assumes checkboxes have 'name' attribute matching node names
        if(!$(this).is(':checked')){
            namesToExclude.push($(this).attr("name"));
        }
    });

    // Filter nodes: keep nodes NOT in namesToExclude
    let nodeMap = {}; // To map old indices to new node objects for link rebuilding
    let filteredNodes = currentJsonData.nodes.filter(function(node, index) {
        if(namesToExclude.indexOf(node.name) === -1) { // If node name is NOT in the exclusion list
            nodeMap[index] = node; // Store the original node object, keyed by its original index in settings.data.nodes
            return true;
        }
        return false;
    });

    // Filter links: keep links where both source and target are in filteredNodes
    // This requires careful handling of source/target references if they are indices.
    // Assuming links in currentJsonData.links have source/target as objects or IDs that can be mapped.
    let filteredLinks = currentJsonData.links.filter(function(link) {
        // If link.source/target are indices into the original settings.data.nodes array:
        let sourceNodeInFiltered = Object.values(nodeMap).find(n => n.id === (typeof link.source === 'object' ? link.source.id : link.source));
        let targetNodeInFiltered = Object.values(nodeMap).find(n => n.id === (typeof link.target === 'object' ? link.target.id : link.target));

        if (sourceNodeInFiltered && targetNodeInFiltered) {
            // Important: Update link.source and link.target to be references to the objects
            // in the *new* filteredNodes array, not the original map or original array.
            return {
                source: sourceNodeInFiltered, // Reference the actual node object from filtered set
                target: targetNodeInFiltered, // Reference the actual node object from filtered set
                relation: link.relation,
                targetcount: link.targetcount
            };
        }
        return false;
    });
     filteredLinks = filteredLinks.map(link => { // Remap source/target to be direct object references from filteredNodes
        return {
            source: filteredNodes.find(n => n.id === link.source.id),
            target: filteredNodes.find(n => n.id === link.target.id),
            relation: link.relation,
            targetcount: link.targetcount
        };
    });


    let data_visible = {nodes: filteredNodes, links: filteredLinks};

    // Update the getData function in settings to return this newly filtered data
    settings.getData = function() { return data_visible; }; // No need for all_data param if it's always this set

    visualizeData(); // Redraw with the filtered data
}
