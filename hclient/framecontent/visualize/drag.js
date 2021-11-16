
/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

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
     
   var entitycolor = getSetting(setting_entitycolor);
      
   // Details for each node            
   nodes.each(function(d, i) {
        // Restore location data
        var record = getSetting(d.id);
        if(record) {
//console.log('restore id '+d.id+'   '+record);            
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

        var  node = d3.select(this);
        
        var icon_display = currentMode=='icons' ? 'initial' : 'none';
        
        //add infobox
        createOverlay(0, 0, "record", "id"+d.id, d, node);
        
        //add outer circle
        node.append("circle")
            .attr("r", function(d) {
                return getEntityRadius(d.count);
            })
            .attr("class", "background icon-background")
            .style({'fill-opacity': '0.5', 'display': icon_display})
            .attr("fill", entitycolor);        
        
        //add internal circle
        node.append("circle")
            .attr("r", circleSize)
            .attr("class", 'foreground icon-foreground')
            .style({"stroke": "#ddd", 'display': icon_display})
            .style("stroke-opacity", function(d) {
                if(d.selected == true) {
                    return 1;
                }
                return .25;
            });

        //add icon
        node.append("svg:image")
            .attr("class", "icon node-icon") 
            .attr("xlink:href", function(d) {
                if(d.image){
                    return d.image;
                }else{
                    return '';
                }
            })
            .attr("x", -iconSize/2)
            .attr("y", -iconSize/2)
            .attr("height", iconSize)
            .attr("width", iconSize)
            .on("mouseover", function(d) {
                if(drag_link_source_id!=null){
                    drag_link_target_id = d.id;
                    drag_link_line.attr("stroke","#00ff00");  //green
                }
            })
            .on("mouseout", function(d) {
                if(drag_link_source_id!=null){
                setTimeout(function(){
                    drag_link_target_id = null;
                    if(drag_link_line) drag_link_line.attr("stroke","#ff0000");  //red
                },200);
                }
            })
            .style('display', icon_display);
                           
        var gravity = getSetting(setting_gravity);
        
        // Attributes
        node  //d3.select(this)
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
             
              closeRectypeSelector();
              // Check if it's not a click after dragging
              if(!d3.event.defaultPrevented) {
                  // Remove all overlays and create a record overlay for selected node
                  //tempXXX
                  //removeOverlays();
                  //onRecordNodeClick(d3.event, d, ".node.id"+d.id);
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
            
            /* Update the location in localstorage
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
            */
        }   
    });

    // Update nodes & lines
    tick();                                                          

}

/** Called when a dragging event ends */
function dragend(d, i) {
    
    // Update nodes & lines
    var gravity = getSetting(setting_gravity);
    d.fixed = ( gravity !== "aggressive");
//console.log("Fixed: ", d.fixed);
    
    
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
    
//console.log("save pos "+d.id+'  '+JSON.stringify(obj));    
    
    // Check if force may resume
    if(gravity !== "off") {
        force.resume(); 
    }
/*    setTimeout(function(){    //tick();
        d3.select("#container").attr("transform","scale(1)");
    },500); */
}