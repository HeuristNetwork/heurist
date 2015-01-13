// Functions to select nodes in the visualisation

/*************************************** NODE SELECTION ******************************/
var foregroundColor = "#fff";
var selectionColor = "#bee4f8";

/**
* Adds a selectionBox to the svg
*/
function addSelectionBox() {
    // Selection element
    var selector = svg.append("g")
                      .attr("class", "selector"); 
                             
    selectionBox = selector.append("rect")
                           .attr("id", "selection")
                           .attr("x", 0)
                           .attr("y", 0);
    
    // Mouse listeners
    svg.on("contextmenu", function() { d3.event.preventDefault(); });
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
    var nodes = d3.selectAll(selector);
    nodes.select(".foreground").style("fill", fgColor);
    nodes.select(".background").style("fill", bgColor);
}

/**
* Handler when a record node has been clicked
* @param event D3 event
* @param data  Visualisation data object
* @param node  Clicked node
*/
function onRecordNodeClick(event, data, node) {
    console.log("On record selection click");
    var needSelect = true;
    var recID = ""+data.id;
    
    // Selected node id's
    if(settings.selectedNodeIds == null) {
        settings.selectedNodeIds = [];    
    }

    // Clicked with ctrl key?
    var bgColor = getSetting(setting_entitycolor);
    if(event.ctrlKey){
        // Select multiple
        var idx = settings.selectedNodeIds.indexOf(recID);
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
        createOverlay(event.offsetX, event.offsetY, "record", "id"+recID, getRecordOverlayData(data));  
    }
    
    // Trigger selection
    settings.triggerSelection.call(this, settings.selectedNodeIds);
}

/**
* Changes the circle color of the nodes in the selectedNodeIds array
* 
* @param selectedNodeIds
*/
function visualizeSelection(selectedNodeIds) {
    settings.selectedNodeIds = selectedNodeIds; // Update settings object
    updateCircles(".node", foregroundColor, getSetting(setting_entitycolor)); // Deselect all

    // Select new nodes
    if(selectedNodeIds && selectedNodeIds.length>0){
        for(var i=0; i<selectedNodeIds.length; i++){
            var selector = ".id"+selectedNodeIds[i];
            updateCircles(selector, selectionColor, selectionColor);    
        }
    }            
}

/************************************** SELECTION BOX **********************************/
var rightClicked = false;
var selectionBox = {};
var positions = {};

/** Prevents the context menu from showing on right click */
function preventMenu(event) {
    event.preventDefault();
}

/**
* Function called on mouse down:
* Deselects all selected nodes 
* 
*/
function onMouseDown() {
    console.log("Mouse down");
    rightClicked = $("#selection").hasClass("selected");
    if(rightClicked) {
        d3.event.preventDefault();
        svg.on(".zoom", null);
        console.log("Unbinded zoom");

        // X-position
        positions.x1 = d3.event.offsetX; 
        positions.clickX1 = d3.event.x;
       
        // Y-position 
        positions.y1 = d3.event.offsetY; 
        positions.clickY1 = d3.event.y;
        
        // Deselect all nodes
        var bgColor = getSetting(setting_entitycolor);
        updateCircles(".node", foregroundColor, bgColor);
    }
}

/**
* Function called on mouse move:
* Updates the position and size of the selectionBox
* 
*/
function onMouseMove() {
    console.log("Mouse moved");
    if(rightClicked) {
        // X-positions
        positions.x2 = d3.event.offsetX;
        positions.clickX2 = d3.event.x;

        if(positions.x1 < positions.x2) {
            selectionBox.attr("x", positions.x1);   
        }else{
            selectionBox.attr("x", positions.x2);
        }
        selectionBox.attr("width", Math.abs(positions.x2-positions.x1));
        
        
        // Y-positions
        positions.y2 = d3.event.offsetY;
        positions.clickY2 = d3.event.y;
        
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
    console.log("Mouse up, rightClicked="+rightClicked);
    if(rightClicked) {
        rightClicked = false;
        selectionBox.style("display", "none"); 

        // Calculate which nodes are in the selection box
        d3.selectAll(".node").each(function(d, i) {
            var selector = ".node.id"+d.id;
            var nodePos = $(selector).offset();
            
            // X in selection box?
            if((nodePos.left >= positions.clickX1 && nodePos.left <= positions.clickX2) ||
               (nodePos.left <= positions.clickX1 && nodePos.left >= positions.clickX2)) {
                // Y in selection box?
                if((nodePos.top >= positions.clickY1 && nodePos.top <= positions.clickY2) ||
                   (nodePos.top <= positions.clickY1 && nodePos.top >= positions.clickY2)) {    
                       // Node is in selection box
                       console.log(d.name + " is in selection box!");
                       updateCircles(selector, selectionColor, selectionColor);      
                }
            }
        
        });
    }else{
        // Remove all selections
        var bgColor = getSetting(setting_entitycolor);
        updateCircles(".node", foregroundColor, bgColor);
    }
    svg.call(zoomBehaviour);
}











// Handle cursor changes
$(document).ready(function(e) {
    // Cursor
    $(".mouse-icon").click(function(e) {
        // Indicate the icon is selected
        $(".mouse-icon").removeClass("selected");
        $(this).addClass("selected"); 
        
        // Update cursor
        var name = $(this).attr("id");
        if(name == "selection") {
            $("#d3svg").css("cursor", "crosshair");    
        }else if(name == "overlay") {
            $("#d3svg").css("cursor", "help");    
        }else{
            $("#d3svg").css("cursor", "default");
        }
    });    
});