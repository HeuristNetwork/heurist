/**
* selection.js - Functions to select nodes in the visualisation
*
* @fileOverview This file contains functions related to node selection in the D3
* visualization. It handles single and multiple node selection (via click and Ctrl+click),
* selection via a draggable selection box (lassoing), and updating the visual
* appearance of selected/deselected nodes.
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4
*/

/* global svg, currentMode, settings, getEntityRadius, getRecordOverlayData, createOverlay,
   getSetting, zoomBehaviour */

/**
 * @description Functions and variables related to node selection in the visualization.
 */

/**
 * Color used to highlight selected nodes.
 * @type {string}
 */
window.selectionColor = "#bee4f8";
/**
 * Current selection mode ('single' or 'multi').
 * @type {string}
 */
window.selectionMode = 'single';

/**
 * Default foreground color for unselected nodes.
 * @type {string}
 * @private
 */
let foregroundColor = '#fff';
/**
 * Flag indicating if a right-click (or multi-select trigger) is active.
 * @type {boolean}
 * @private
 */
let rightClicked = false;
/**
 * D3 selection of the selection box rectangle.
 * @type {object}
 * @private
 */
let selectionBox = {};
/**
 * Stores coordinates for the selection box.
 * @type {{x1: number, clickX1: number, y1: number, clickY1: number, x2: number, clickX2: number, y2: number, clickY2: number}}
 * @private
 */
let positions = {};


/**
* Adds a draggable selection box (rectangle) to the SVG canvas
* and attaches mouse event listeners for selection.
*/
function addSelectionBox() {
    // Selection element
    let selector = svg.append("g")
                      .attr("class", "selector");

    selectionBox = selector.append("rect")
                           .attr("id", "selection")
                           .attr("x", 0)
                           .attr("y", 0);

    // Mouse listeners
    svg.on("contextmenu", function() { window.d3.event.preventDefault(); }); // Prevent default context menu
    svg.on("mousedown", onMouseDown);
    svg.on("mousemove", onMouseMove);
    svg.on("mouseup", onMouseUp);
}

/**
* Updates the fill color of foreground and background circles of specified nodes.
* @param {string} selector - A D3 selector string for the nodes to update.
* @param {string} [fgColor=foregroundColor] - The new foreground color. Defaults to the global `foregroundColor`.
* @param {boolean} isSelection - Is the node being selected
*/
function updateCircles(selector, fgColor, isSelection) {
    if(!fgColor){
        fgColor = foregroundColor;
    }

    let handleForeground = !settings.minimal ? fgColor : (d) => {

        let settings = getSetting(`setting_styling_nodes${d.rty_ID}`, {fillColour: foregroundColor});

        return isSelection ? fgColor : settings['fillColour'];
    };

    let bgColor = getSetting('setting_entitycolor');
    bgColor = isSelection ? window.selectionColor : bgColor;

    let nodes = window.d3.selectAll(selector);
    nodes.select(".foreground").style("fill", handleForeground);
    nodes.select(".background").style("fill", bgColor);
}

/**
 * Determines a color for a node based on its record type ID.
 * Uses a predefined array of colors.
 * @param {object} dataColour - The node data object, expected to have an `rty_ID`.
 * @returns {string|undefined} The color string if a mapping exists, otherwise undefined.
 */
function determineColour(dataColour) {

    //In the array below there are currently 100 colours which will match up to all 100 unique node type ID's
    const colours = ['#FFEBEE', '#FFCDD2', '#EF9A9A', '#E57373', '#EF5350', '#FCE4EC', '#F8BBD0', '#F48FB1', '#F06292', '#EC407A',
                     '#FF8A80', '#E1BEE7', '#CE93D8', '#BA68C8','#AB47BC', '#EDE7F6', '#D1C4E9', '#B39DDB','#9575CD', '#7E57C2',
                     '#E8EAF6', '#C5CAE9','#9FA8DA', '#7986CB', '#5C6BC0', '#E3F2FD','#BBDEFB', '#90CAF9', '#64B5F6', '#42A5F5',
                     '#E1F5FE', '#B3E5FC', '#81D4FA', '#4FC3F7','#29B6F6', '#E0F7FA', '#B2EBF2', '#80DEEA','#4DD0E1', '#26C6DA',
                     '#E0F2F1', '#B2DFDB','#80CBC4', '#4DB6AC', '#26A69A', '#E8F5E9','#C8E6C9', '#A5D6A7', '#81C784', '#66BB6A',
                     '#F1F8E9','#F3E5F5', '#DCEDC8', '#C5E1A5', '#AED581','#9CCC65', '#F9FBE7', '#F0F4C3', '#E6EE9C','#DCE775',
                     '#D4E157', '#FFFDE7', '#FFF9C4','#FFF59D', '#FFF176', '#FFEE58', '#fff8e1','#ffecb3', '#ffe082', '#ffd54f',
                     '#ffca28', '#FFF3E0', '#FFE0B2', '#FFCC80', '#FFB74D','#FFA726', '#FBE9E7', '#FFCCBC', '#FFAB91','#FF8A65',
                     '#FF7043', '#EFEBE9', '#D7CCC8','#BCAAA4', '#A1887F', '#8D6E63', '#FAFAFA','#F5F5F5', '#EEEEEE', '#E0E0E0',
                     '#BDBDBD', '#ECEFF1', '#CFD8DC', '#B0BEC5', '#90A4AE','#78909C',  '#FF80AB', '#EA80FC', '#B388FF', '#8C9EFF'];

    const colourLength = colours.length;
    let idx = dataColour.rty_ID - 1;
    idx = idx >= colourLength ? idx % colourLength : idx;
    idx = idx < 0 ? Math.random() * colourLength : idx;

    return colours[idx];
}

/**
* Updates the fill color of info-mode rectangles associated with specified nodes.
* Used when `currentMode` is not 'icons'.
* @param {string} selector - A D3 selector string for the nodes whose rectangles to update.
* @param {string} colour - The new fill color.
*/
function updateRectangles(selector, colour) {
    let nodes = window.d3.selectAll(selector);
    nodes.select('rect.info-mode-full').style('fill', colour);
    nodes.select('rect.info-mode').style('fill', colour);
}

/**
* Handles click events on record nodes for selection.
* Supports single click selection and Ctrl+click for multi-selection.
* Updates the visual state of nodes and their overlays.
*
* @param {Event} event - The D3 event object.
* @param {object} data - The D3 data object for the clicked node.
* @param {SVGElement} node - The D3 selection of the clicked node group element.
*/
function onRecordNodeClick(event, data, node) {
    let needSelect = true;
    let recID = ""+data.id;

    // Selected node id's
    if(settings.selectedNodeIds == null) {
        settings.selectedNodeIds = [];
    }

    // Clicked with ctrl key?
    if(event.ctrlKey){
        // Select multiple
        let idx = settings.selectedNodeIds.indexOf(recID);
        if (idx > -1) {
            // Deselect if already selected
            needSelect = false;
            //NOTE - need test IT WAS ".node"
            updateCircles(".node.id"+recID, foregroundColor, false); // Deselect this specific node
            settings.selectedNodeIds.splice(idx, 1);
        }
    }else{
        // Select single, deselect all others
        updateCircles(".node", foregroundColor, false); // Deselect all
        settings.selectedNodeIds = [];
    }

    // Select new node if needed
    if(needSelect){
        data.selected = true; // Mark data object as selected
        settings.selectedNodeIds.push(recID);

        // Update circles and show overlay
        updateCircles(node, window.selectionColor, true);

        let nodePos = $(node).offset();
        const r = getEntityRadius(data.count);
        const dx = event.x - event.offsetX; // Offset of SVG container
        const dy = event.y - event.offsetY; // Offset of SVG container

        // Create overlay near the node
        createOverlay(Math.round(nodePos.left-dx+r), Math.round(nodePos.top-dy+r), "record", "id"+recID, getRecordOverlayData(data));
    }

    // Trigger external selection callback if defined
    if(settings.triggerSelection){
       settings.triggerSelection.call(this, settings.selectedNodeIds);
    }
}

/**
* Updates the visual representation of selected nodes based on an array of node IDs.
* This is often called externally to programmatically set the selection.
*
* @param {Array<string|number>} selectedNodeIds - An array of IDs of nodes to be selected.
*/
function visualizeSelection(selectedNodeIds) {

    if(!settings){
        return;
    }

    settings.selectedNodeIds = selectedNodeIds; // Update settings object

    // Deselect all first
    if(currentMode == 'icons'){
        updateCircles(".node", foregroundColor, false);
    }else{
        updateRectangles(".node", foregroundColor); // Assuming default color for unselected rectangles
    }

    // Select new nodes
    if(selectedNodeIds && selectedNodeIds.length>0){
        for(let i=0; i<selectedNodeIds.length; i++){
            let selector = ".id"+selectedNodeIds[i];

            if(currentMode == 'icons'){
                updateCircles(selector, window.selectionColor, true);
            }else{
                updateRectangles(selector, window.selectionColor);
            }
        }
    }
}


/**
 * Prevents the default context menu from appearing.
 * Used to enable right-click for selection box.
 * @param {Event} event - The contextmenu event.
 * @private
 */
function preventMenu(event) {
    event.preventDefault();
}

/**
 * Closes the record type selector panel if it's open and in database structure mode.
 * @private
 */
function closeRectypeSelector(){
    if(settings.isDatabaseStructure){
        // Assumes a specific layout structure for the popup
        $($('body.popup div.layout-container')[0]).layout().close('west');
    }
}

/**
* Handles the mousedown event on the SVG canvas.
* Initiates the selection box drawing if `selectionMode` is 'multi'.
* Deselects all nodes.
* @private
*/
function onMouseDown() {
    closeRectypeSelector();

    rightClicked = (window.selectionMode=='multi' && window.d3.event.button === 2); // Check for right mouse button if multi-select mode
    if(rightClicked) {
        window.d3.event.preventDefault(); // Prevent context menu
        svg.on(".zoom", null); // Disable zoom during selection box drag

        // Store initial mouse positions
        positions.x1 = window.d3.event.offsetX;
        positions.clickX1 = window.d3.event.x; // Screen X

        positions.y1 = window.d3.event.offsetY;
        positions.clickY1 = window.d3.event.y; // Screen Y

        // Deselect all nodes visually
        updateCircles(".node", foregroundColor, false);
        settings.selectedNodeIds = []; // Clear logical selection
    }
}

/**
* Handles the mousemove event on the SVG canvas.
* Updates the size and position of the selection box if dragging is active.
* @private
*/
function onMouseMove() {
    if(rightClicked) { // Only if selection box dragging is active
        // Update current mouse positions
        positions.x2 = window.d3.event.offsetX;
        positions.clickX2 = window.d3.event.x;

        // Adjust selection box rectangle
        if(positions.x1 < positions.x2) {
            selectionBox.attr("x", positions.x1);
        }else{
            selectionBox.attr("x", positions.x2);
        }
        selectionBox.attr("width", Math.abs(positions.x2-positions.x1));


        positions.y2 = window.d3.event.offsetY;
        positions.clickY2 = window.d3.event.y;

        if(positions.y1 < positions.y2) {
            selectionBox.attr("y", positions.y1);
        }else{
            selectionBox.attr("y", positions.y2);
        }
        selectionBox.attr("height", Math.abs(positions.y2-positions.y1));
        selectionBox.style("display", "block"); // Make it visible
    }
}

/**
* Handles the mouseup event on the SVG canvas.
* Finalizes the selection box operation, identifies nodes within the box,
* and updates their selection state. Re-enables zoom.
* @private
*/
function onMouseUp() {
    if(rightClicked) { // If selection box was active
        rightClicked = false;
        selectionBox.style("display", "none"); // Hide selection box

        settings.selectedNodeIds = []; // Reset selected IDs for the new selection

        // Determine which nodes are inside the selection box
        window.d3.selectAll(".node").each(function(d, i) {
            let selector = ".node.id"+d.id;
            let nodeElement = $(selector)[0]; // Get the DOM element
            if (!nodeElement) return;

            // Get node position relative to the document
            let nodePos = nodeElement.getBoundingClientRect();

            // Check if node is within the screen coordinates of the selection box
            const minX = Math.min(positions.clickX1, positions.clickX2);
            const maxX = Math.max(positions.clickX1, positions.clickX2);
            const minY = Math.min(positions.clickY1, positions.clickY2);
            const maxY = Math.max(positions.clickY1, positions.clickY2);

            // Check if the center of the node is within the selection box
            // This might need adjustment based on how nodePos is calculated and what part of the node should be "in"
            const nodeCenterX = nodePos.left + nodePos.width / 2;
            const nodeCenterY = nodePos.top + nodePos.height / 2;

            if (nodeCenterX >= minX && nodeCenterX <= maxX && nodeCenterY >= minY && nodeCenterY <= maxY) {
               updateCircles(selector, window.selectionColor, true);
               settings.selectedNodeIds.push(""+d.id);
            }
        });

        if(settings.triggerSelection){
            settings.triggerSelection.call(this, settings.selectedNodeIds);
        }

    } else if (window.d3.event.button === 0 && !window.d3.event.ctrlKey && !$(window.d3.event.target).closest('.node').length) {
        // Left click on empty space (not on a node) and not Ctrl key
        updateCircles(".node", foregroundColor, false); // Deselect all
        settings.selectedNodeIds = [];
        if(settings.triggerSelection){
            settings.triggerSelection.call(this, settings.selectedNodeIds);
        }
    }
    svg.call(zoomBehaviour); // Re-enable zoom
}