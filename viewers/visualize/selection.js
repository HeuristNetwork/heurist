
/**
* selection.js: Functions to select nodes in the visualisation
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/* global svg, currentMode, settings, getEntityRadius, getRecordOverlayData, createOverlay
   getSetting, zoomBehaviour, selectionColor, selectionMode */

// Functions to select nodes in the visualisation

/*************************************** NODE SELECTION ******************************/

window.selectionColor = "#bee4f8";
window.selectionMode = 'single'; 

let foregroundColor = '#fff';
let rightClicked = false;
let selectionBox = {};
let positions = {};


/**
* Adds a selectionBox to the svg
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
    svg.on("contextmenu", function() { window.d3.event.preventDefault(); });
    svg.on("mousedown", onMouseDown);
    svg.on("mousemove", onMouseMove);
    svg.on("mouseup", onMouseUp);
}

/**
* Updates the foreground and background circles of all nodes
* @param fgColor New foreground color
* @param bgColor New background color
*/
function updateCircles(selector, fgColor, bgColor) {
    if(!fgColor){
        fgColor = foregroundColor; 
    }
    let nodes = window.d3.selectAll(selector);
    nodes.select(".foreground").style("fill", fgColor);
    nodes.select(".background").style("fill", bgColor);
}

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

    let idx = dataColour.rty_ID - 1;
    if(idx > 0 && idx < colours.length){
        return colours[idx];
    }
}

/**
* Updates the foreground and background rectangles of all nodes
* @param fgColor New foreground color
* @param bgColor New background color
*/
function updateRectangles(selector, colour) {
    let nodes = window.d3.selectAll(selector);
    nodes.select('rect.info-mode-full').style('fill', colour);
    nodes.select('rect.info-mode').style('fill', colour);
}

/**
* Handler when a record node has been clicked
* @param event D3 event
* @param data  Visualisation data object
* @param node  Clicked node
*/
function onRecordNodeClick(event, data, node) {
    let needSelect = true;
    let recID = ""+data.id;
    
    // Selected node id's
    if(settings.selectedNodeIds == null) {
        settings.selectedNodeIds = [];    
    }

    // Clicked with ctrl key?
    let bgColor = getSetting('setting_entitycolor');
    if(event.ctrlKey){
        // Select multiple
        let idx = settings.selectedNodeIds.indexOf(recID);
        if (idx > -1) {
            // Deslect all others
            needSelect = false;
            updateCircles(".node", foregroundColor, bgColor);
            settings.selectedNodeIds.splice(idx, 1);
        }
    }else{
        // Select single, deselect all
        updateCircles(".node", foregroundColor, bgColor);
        settings.selectedNodeIds = [];
    }            
    
    // Select new nodes
    if(needSelect){
        data.selected = true;
        settings.selectedNodeIds.push(recID);
        
        // Update circles and show overlay
        updateCircles(node, selectionColor, selectionColor);
        //was shown on mouse click event.offsetX, event.offsetY, now in center of node
        //was createOverlay(event.offsetX, event.offsetY, "record", "id"+recID, getRecordOverlayData(data));  
        
        let nodePos = $(node).offset();
        
        const r = getEntityRadius(data.count);
        
        const dx = event.x - event.offsetX; 
        const dy = event.y - event.offsetY; 

        createOverlay(Math.round(nodePos.left-dx+r), Math.round(nodePos.top-dy+r), "record", "id"+recID, getRecordOverlayData(data));    }
    
    // Trigger selection
    if(settings.triggerSelection){
       settings.triggerSelection.call(this, settings.selectedNodeIds); 
    }  
}

/**
* Changes the circle color of the nodes in the selectedNodeIds array
* 
* @param selectedNodeIds
*/
function visualizeSelection(selectedNodeIds) {
    settings.selectedNodeIds = selectedNodeIds; // Update settings object

    if(currentMode == 'icons'){
        updateCircles(".node", foregroundColor, getSetting('setting_entitycolor')); // Deselect all
    }else if(selectedNodeIds && selectedNodeIds.length>0){
        updateRectangles(".node", getSetting('setting_entitycolor'));
    }else{
        updateRectangles(".node", foregroundColor);
    }

    // Select new nodes
    if(selectedNodeIds && selectedNodeIds.length>0){
        for(let i=0; i<selectedNodeIds.length; i++){
            let selector = ".id"+selectedNodeIds[i];

            if(currentMode == 'icons'){
                updateCircles(selector, selectionColor, selectionColor);
            }else{
                updateRectangles(selector, selectionColor);
            }
        }
    }
}

/************************************** SELECTION BOX **********************************/

/** Prevents the context menu from showing on right click */
function preventMenu(event) {
    event.preventDefault();
}

function closeRectypeSelector(){     
    if(settings.isDatabaseStructure){
        $($('body.popup div.layout-container')[0]).layout().close('west');
    }
}

/**
* Function called on mouse down:
* Deselects all selected nodes 
* 
*/
function onMouseDown() {
    closeRectypeSelector();

    rightClicked = (selectionMode=='multi');
    if(rightClicked) {
        window.d3.event.preventDefault();
        svg.on(".zoom", null);

        // X-position
        positions.x1 = window.d3.event.offsetX; 
        positions.clickX1 = window.d3.event.x;
       
        // Y-position 
        positions.y1 = window.d3.event.offsetY; 
        positions.clickY1 = window.d3.event.y;
        
        // Deselect all nodes
        const bgColor = getSetting('setting_entitycolor');
        updateCircles(".node", foregroundColor, bgColor);
    }
}

/**
* Function called on mouse move:
* Updates the position and size of the selectionBox
* 
*/
function onMouseMove() {
    if(rightClicked) {
        // X-positions
        positions.x2 = window.d3.event.offsetX;
        positions.clickX2 = window.d3.event.x;

        if(positions.x1 < positions.x2) {
            selectionBox.attr("x", positions.x1);   
        }else{
            selectionBox.attr("x", positions.x2);
        }
        selectionBox.attr("width", Math.abs(positions.x2-positions.x1));
        
        
        // Y-positions
        positions.y2 = window.d3.event.offsetY;
        positions.clickY2 = window.d3.event.y;
        
        if(positions.y1 < positions.y2) {
            selectionBox.attr("y", positions.y1);   
        }else{
            selectionBox.attr("y", positions.y2);
        }
        selectionBox.attr("height", Math.abs(positions.y2-positions.y1));
        selectionBox.style("display", "block");
    }
}

/**
* Function called on mouse up:
* Hides the selectionBox
* Selects the circles inside the selectionBox
*/
function onMouseUp() {
    if(rightClicked) {
        rightClicked = false;
        selectionBox.style("display", "none"); 

        // Calculate which nodes are in the selection box
        window.d3.selectAll(".node").each(function(d, i) {
            let selector = ".node.id"+d.id;
            let nodePos = $(selector).offset();
            
            // X in selection box?
            if((nodePos.left >= positions.clickX1 && nodePos.left <= positions.clickX2) ||
               (nodePos.left <= positions.clickX1 && nodePos.left >= positions.clickX2)) {
                // Y in selection box?
                if((nodePos.top >= positions.clickY1 && nodePos.top <= positions.clickY2) ||
                   (nodePos.top <= positions.clickY1 && nodePos.top >= positions.clickY2)) {    
                       // Node is in selection box
                       updateCircles(selector, selectionColor, selectionColor);      
                }
            }
        
        });
    }else{
        // Remove all selections
        const bgColor = getSetting('setting_entitycolor');
        updateCircles(".node", foregroundColor, bgColor);
    }
    svg.call(zoomBehaviour);
}