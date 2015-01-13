// Functions to add nodes and make them draggable

/**
* Appends nodes to the visualisation
*/
function addNodes() {
    // Append nodes
    var nodes = d3.select("#container")
                  .selectAll(".node")
                  .data(data.nodes)
                  .enter()
                  .append("g");
    // Dragging
    var drag = d3.behavior.drag()
                 .on("dragstart", dragstart)
                 .on("drag", dragmove)
                 .on("dragend", dragend);
      
     // Details for each node            
     nodes.each(function(d, i) {
        // Restore location data
        var record = getSetting(d.id);
        if(record) {
            var obj = JSON.parse(record);
            if("x" in obj) {
                d.x = obj.x;
            }
            if("y" in obj) {
                d.y = obj.y;
            }
            if("px" in obj) {
                d.px = obj.px;
            }
            if("py" in obj) {
                d.py = obj.py;
            }
        }
        
        // Attributes
        d3.select(this)
          .attr("class", "node id"+d.id)
          .attr("transform", "translate(10, 10)")
          .attr("x", d.x) 
          .attr("y", d.y)
          .attr("px", d.px)
          .attr("py", d.py)
          .attr("fixed", function(d) {
              if(record && gravity == "off") {
                  d.fixed = true;
                  return true;
              }
              return false;
          })    
         .on("click", function(d) {
              // Check if it's not a click after dragging
              if(!d3.event.defaultPrevented) {
                  // Remove all overlays and create a record overlay
                  removeOverlays();
                  onRecordNodeClick(d3.event, d, ".node.id"+d.id);
              }
         })
         .call(drag);
     });            
     return nodes;
}

/**
* Updates the locations of all nodes
*/
function updateNodes() {
    d3.selectAll(".node").attr("transform", function(d) { 
        // Store new position
        var obj = {px: d.px, py: d.py, x: d.x, y: d.y};
        putSetting(d.id, JSON.stringify(obj));
        return "translate(" + d.x + "," + d.y + ")"; 
    });
}

// Functions to make dragging, moving and zooming possible

/** Called when a dragging event starts */
function dragstart(d, i) {
    d3.event.sourceEvent.stopPropagation();
    force.stop();

    // Fixed node positions?
    var gravity = getSetting(setting_gravity);
    svg.selectAll(".node")
       .attr("fixed", function(d, i) {
            d.fixed = (gravity == "off");
            return d.fixed;
       }); 
    d.fixed = true; 
    
    updateCircles(".node.id"+d.id, selectionColor, selectionColor);
}

/** Caled when a dragging move event occurs */
function dragmove(d, i) {  
    // Update all selected nodes. A node is selected when the .foreground color is 190,228,248
    svg.selectAll(".node").each(function(d, i) {
        var color = d3.select(this).select(".foreground").style("fill");
        if(color == "rgb(190, 228, 248)") {
            // Update locations
            d.px += d3.event.dx;
            d.py += d3.event.dy;
            d.x += d3.event.dx;
            d.y += d3.event.dy;
            
            // Update the location in localstorage
            var record = getSetting(d.id); 
            //console.log("Record", record);
            var obj;
            if(record === null) {
                obj = {}; 
            }else{
                obj = JSON.parse(record);
            }  
            
            // Set attributes 'x' and 'y' and store object
            obj.px = d.px;
            obj.py = d.py;
            obj.x = d.x;
            obj.y = d.y;
            putSetting(d.id, JSON.stringify(obj));
        }   
    });

    // Update nodes & lines
    tick();                                                          

    // Update overlay
    updateOverlays(); 
}

/** Called when a dragging event ends */
function dragend(d, i) {
    // Update nodes & lines
    d.fixed = (getSetting(setting_gravity) !== "aggressive");
    console.log("Fixed: ", d.fixed);
    tick();
    
    // Check if force may resume
    if(gravity !== "off") {
        force.resume(); 
    } 
}