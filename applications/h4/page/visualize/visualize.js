/**
* Visualisation plugin
* Requirements:
* 
* Internal Javascript:
* - settings.js
* - overlay.js
* - gephi.js
* - visualize.js
* 
* External Javascript:
* - jQuery          http://jquery.com/
* - D3              http://d3js.org/
* - D3 fisheye      https://github.com/d3/d3-plugins/tree/master/fisheye
* - Colpick         http://colpick.com/plugin
*
* Objects must have at least the following properties:
* - id
* - name
* - image
* - count
* 
* Available settings and their default values:
* - linetype: "straight",
* - linelength: 100,
* - linewidth: 15,
* - linecolor: "#22a",
* - markercolor: "#000",
* 
* - entityradius: 30,
* - entitycolor: "#b5b5b5",
* 
* - labels: true,
* - fontsize: "8px",
* - textlength: 60,
* - textcolor: "#000",
* 
* - formula: "linear",
* - fisheye: false,
* 
* - gravity: "off",
* - attraction: -3000,
* 
* - translatex: 0,
* - translatey: 0,
* - scale: 1
*/

var settings;   // Plugin settings object
var svg;        // The SVG where the visualisation will be executed on
(function ( $ ) {
    // jQuery extension
    $.fn.visualize = function( options ) {
        // Select and clear SVG.
        svg = d3.select("#d3svg");
        svg.selectAll("*").remove();
        svg.append("text").text("Processing...").attr("x", "25").attr("y", "25");   
        
        // Default plugin settings
        settings = $.extend({
            // Custom functions
            getData: $.noop(), // Needs to be overriden with custom function
            getLineLength: function() { return getSetting(setting_linelength); },
            
            selectedNodeIds: [],
            triggerSelection: function(selection){}, 
            
            showCounts: true,
            limit: 2000,
            
            // UI setting controls
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
            showFishEye: true,
            
            showGravitySettings: true,
            showGravity: true,
            showAttraction: true,
            
            
            // UI default settings
            linetype: "straight",
            linelength: 100,
            linewidth: 15,
            linecolor: "#22a",
            markercolor: "#000",
            
            entityradius: 30,
            entitycolor: "#b5b5b5",
            
            labels: true,
            fontsize: "8px",
            textlength: 60,
            textcolor: "#000",
            
            formula: "linear",
            fisheye: false,
            
            gravity: "off",
            attraction: -3000,
            
            translatex: 0,
            translatey: 0,
            scale: 1
        }, options );
 
        // Handle settings (settings.js)
        checkStoredSettings();
        handleSettingsInUI();

        // Check visualisation limit
        var amount = Object.keys(settings.data.nodes).length;
        if(amount > settings.limit) {
             $("#d3svg").html('<text x="25" y="25" fill="black">Sorry, the visualisation limit is set at ' +settings.limit+ ' records, you are trying to visualize ' +amount+ ' records</text>');  
             return; 
        }else{
            visualizeData();    
        }
 
        return this;
    };
}( jQuery ));
    

/*******************************START OF VISUALISATION HELPER FUNCTIONS*******************************/
var maxCount; 
function determineMaxCount(data) {
    maxCount = 1;
    if(data && data.nodes.length > 0) {
        for(var i = 0; i < data.nodes.length; i++) {
            //console.log(data.nodes[i]);
            if(data.nodes[i].count > maxCount) {
                maxCount = data.nodes[i].count;
            } 
        }
    }
    console.log("Max count: " + maxCount);
}

/** Calculates log base 10 */
function log10(val) {
    return Math.log(val) / Math.LN10;
}

/** Executes the chosen formula with a chosen count & max size */

function executeFormula(count, maxSize) {
    // Avoid minus infinity and wrong calculations etc.
    if(count <= 0) {
        count = 1;
    }
    
    if(count > maxCount) {
        maxCount = count;
    }
    
    //console.log("Count: " + count + ", max count: " + maxCount + ", max Size: " + maxSize);
    var formula = getSetting(setting_formula);
    if(formula == "logarithmic") { // Log                                                           
        return Math.log(count) / Math.log(maxCount) * maxSize;
    }
    else if(formula == "unweighted") { // Unweighted
        return 2;                                          
    }else {  // Linear
        return (count/maxCount) * maxSize; 
    }       
}

/** Returns the line length */
function getLineLength(record) {
    console.log("Default line length function");
    return getSetting(setting_linelength);
}

/** Calculates the line width that should be used */
function getLineWidth(count) {
    var maxWidth = getSetting(setting_linewidth);                                                                     
    return 1.5 + (executeFormula(count, maxWidth) / 2);
}            

/** Calculates the marker width that should be used */
function getMarkerWidth(count) {
    return 4 + getLineWidth(count)*2;
}

/** Calculates the entity raadius that should be used */
var iconSize = 16; // The icon size
var circleSize = iconSize * 0.75; // Circle around icon size
function getEntityRadius(count) {
    var maxRadius = getSetting(setting_entityradius);
    return circleSize + executeFormula(count, maxRadius) - 1;
}

/***********************************START OF VISUALISATION FUNCIONS***********************************/
/** Visualizes the data */ 
function visualizeData() {
    // SVG data  
    var width = parseInt(svg.style("width"));
    var height = parseInt(svg.style("height"));
    svg.selectAll("*").remove();
    
    // Record data
    var data = settings.getData.call(this, settings.data);
    console.log("RECORD DATA");
    console.log(data); 
   
    // Line settings
    var linetype = getSetting(setting_linetype);
    var linelength = getSetting(setting_linelength);
    var linecolor = getSetting(setting_linecolor);
    var markercolor = getSetting(setting_markercolor);
    
    // Entity
    var entitycolor = getSetting(setting_entitycolor);
    
    // Text
    var fontsize = getSetting(setting_fontsize);
    var textcolor = getSetting(setting_textcolor);

    // Gravity settings
    var gravity = getSetting(setting_gravity);
    var attraction = getSetting(setting_attraction);
    var fisheyeEnabled = getSetting(setting_fisheye);
    
    // Zoom settings
    var scale = getSetting(setting_scale);
    var translateX = getSetting(setting_translatex);
    var translateY = getSetting(setting_translatey);
    console.log("Scale: " + scale + ", translateX " + translateX + ", translateY " + translateY);

    // Append zoomable container       
    var container = svg.append("g")
                       .attr("id", "the-container")
                       .attr("transform", "translate("+translateX+", "+translateY+")scale("+scale+")");
                       
    // Adding zoom
    var zoomBehaviour = d3.behavior.zoom()
                          .translate([translateX, translateY])
                          .scale(scale)
                          .scaleExtent([0.05, 10])
                          .on("zoom", zoomed);
     
    /** Updates the container after a zoom event */    
    var rightClicked = false;         
    function zoomed() { 
        // Translate
        //console.log("Zoomed!", d3.event.translate);   
        if(d3.event.translate !== undefined) {
            if(!isNaN(d3.event.translate[0])) {           
                putSetting(setting_translatex, d3.event.translate[0]); 
            }
            if(!isNaN(d3.event.translate[1])) {    
                putSetting(setting_translatey, d3.event.translate[1]);
            }
            //console.log("translateX " + getSetting(setting_translatex) + ", translateY " + getSetting(setting_translatey));
        }
        // Scale
        //console.log(d3.event.scale);
        if(!isNaN(d3.event.scale)) {
            putSetting(setting_scale, d3.event.scale);
        }   
        
        // Transform  
        var transform = "translate("+d3.event.translate+")scale("+d3.event.scale+")";
        //console.log("ZOOMED --> " + transform);
        container.attr("transform", transform);
        updateOverlays();
    }  
   svg.call(zoomBehaviour); 

    // Creating D3 force
    determineMaxCount(data);
    var force = d3.layout.force()
                         .nodes(d3.values(data.nodes))
                         .links(data.links)
                         .charge(attraction)        // Using the attraction setting
                         .linkDistance(function(d) {         
                             return settings.getLineLength.call(this, d.target);
                         })  // Using the linelength setting 
                         .on("tick", function(d, i) {
                             // Determine what function to call on force.drag
                             if(linetype == "curved") { 
                                 return curvedTick();
                             }else{
                                 return straightTick();
                             }
                         })
                         .size([width, height])
                         .start();  
                   
    // Adding marker definitions
    container.append("defs")
             .selectAll("marker")
             .data(force.links())
             .enter()
             .append("svg:marker")    
             .attr("id", function(d) {
                return "marker-s"+d.source.id+"r"+d.relation.id+"t"+d.target.id;
             })
             .attr("markerWidth", function(d) {    
                return getMarkerWidth(d.targetcount);             
             })
             .attr("markerHeight", function(d) {
                return getMarkerWidth(d.targetcount);
             })
             .attr("refX", function(d) {
                 // Move markers to display a pointer on a straight line
                 if(linetype=="straight" && d.relation.pointer) {
                     return linelength*-0.2;
                 }
                 return -1;
             })
             .attr("refY", 0)
             .attr("viewBox", "0 -5 10 10")
             .attr("markerUnits", "userSpaceOnUse")
             .attr("orient", "auto")
             .attr("fill", markercolor) // Using the markercolor setting
             .attr("opacity", "0.6")
             .append("path")                                                      
             .attr("d", "M0,-5L10,0L0,5");
    
    /** Add lines */        
    function addLines(clazz) {
        // Add the chosen lines [using the linetype setting]  
        var lines;
        if(linetype == "curved") {
            // Add curved lines
             lines = container.append("svg:g")
                              .selectAll("path")
                              .data(force.links())
                              .enter()
                              .append("svg:path");
        }else{
            // Add straight lines
            lines = container.append("svg:g")
                             .selectAll("polyline.link")
                             .data(force.links())
                             .enter()
                             .append("svg:polyline");
        }    
         
        // Adding shared attributes
        lines.attr("class", function(d) {
                return clazz + " link s"+d.source.id+"r"+d.relation.id+"t"+d.target.id;
             }) 
             .attr("marker-mid", function(d) {
                return "url(#marker-s"+d.source.id+"r"+d.relation.id+"t"+d.target.id+")";
             })
             .style("stroke-dasharray", (function(d) {
                 if(d.targetcount == 0) {
                    return "3, 3"; 
                 } 
             })) 
             .on("click", function(d) {
                 // Close all overlays and create a line overlay
                 removeOverlays();
                 var selector = "s"+d.source.id+"r"+d.relation.id+"t"+d.target.id;
                 createOverlay(d3.event.offsetX, d3.event.offsetY, "relation", selector, getRelationOverlayData(d));  
             });
             
        return lines;
    }
    
    // Bottom lines
    var bottomLines = addLines("bottom");
    bottomLines.attr("stroke", linecolor)
               .style("stroke-width", function(d) { 
                    return 0.5 + getLineWidth(d.targetcount);
               });
    
    // Top lines
    var topLines = addLines("top");
    topLines.attr("stroke", "rgba(255, 255, 255, 0.0)")
            .style("stroke-width", function(d) { 
                   return 8.5 + getLineWidth(d.targetcount);
            });
    
    // Check what methods to call on drag
    var node_drag = d3.behavior.drag()
                               .on("dragstart", dragstart)
                               .on("drag", dragmove)
                               .on("dragend", dragend);
                           
    /** Called when a dragging event starts */
    function dragstart(d, i) {
        d3.event.sourceEvent.stopPropagation();
            
        if(gravity !== "aggressive") {    
            force.stop(); // Stop force from auto positioning
            if(gravity === "touch") {
                svg.selectAll(".node").attr("fixed", function(d, i) {
                    d.fixed = false;
                    return false;
                });
            }
        }
        
        d3.select(this).select(".foreground").style("fill", "#bee4f8");
        d3.select(this).select(".background").style("fill", "#bee4f8");
    }

    /** Caled when a dragging move event occurs */
    function dragmove(d, i) {  
        // Update all selected nodes
        svg.selectAll(".node").each(function(d, i) {
            var color = d3.select(this).select(".foreground").style("fill");//.attr("fill"); 
            console.log("color: " + color);
            if(color == "rgb(190, 228, 248)") {
                console.log("SELECTED NODE");
                
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
        if(linetype == "curved") { 
             curvedTick();
        }else{
             straightTick();
        }
        
        // Update overlay
        updateOverlays(); 
    }
    
    /** Called when a dragging event ends */
    function dragend(d, i) {
         if(gravity !== "aggressive") { 
            d.fixed = true; // Node may not be auto positioned
        }
        
        // Update nodes & lines 
        if(linetype == "curved") { 
            curvedTick();
        }else{
            straightTick();
        } 
        
        // Check if force may resume
        if(gravity !== "off") {
            force.resume(); 
        } 
    }
         
    // Defining the nodes
    var node = container.selectAll(".node")
                  .data(force.nodes())
                  .enter().append("g")
                  .attr("class", function(d) {
                      return "node id" + d.id;
                  }) 
                  .attr("transform", "translate(10, 10)")
                  .attr("x", function(d) {
                      // Setting 'x' based on localstorage
                      var record = getSetting(d.id);
                      if(record) {
                          var obj = JSON.parse(record);
                          if("x" in obj) { // Check if attribute is valid
                              d.x = obj.x;
                              return obj.x;
                          }
                      }    
                  })
                  .attr("y", function(d) {
                      // Setting 'y' based on localstorage
                      var record = getSetting(d.id);
                      if(record) {
                          var obj = JSON.parse(record);
                          if("y" in obj) { // Check if attribute is valid
                              d.y = obj.y;
                              return obj.y;  
                          }
                      }      
                  })
                  .attr("px", function(d) {
                      // Setting 'px' based on localstorage
                      var record = getSetting(d.id);
                      if(record) {
                         var obj = JSON.parse(record);
                         if("px" in obj) { // Check if attribute is valid
                             d.px = obj.px;
                             return obj.px;
                         }
                      }         
                  })
                  .attr("py", function(d) {
                      // Setting 'py' based on localstorage
                      var record = getSetting(d.id);
                      if(record) {
                          var obj = JSON.parse(record);
                          if("py" in obj) { // Check if attribute is valid
                              d.py = obj.py; 
                              return obj.py;
                          }
                      }    
                  })
                  .attr("fixed", function(d) {
                      // Setting 'fixed' based on localstorage
                      var record = getSetting(d.id);
                      if(record && gravity !== "aggressive") { 
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
                           _recordNodeOnClick(d3.event, d, this);
                       }
                  })
                  .call(node_drag);
                  
    // Title
    var titles = node.append("title")
                     .text(function(d) {
                         return d.name;
                     });

    // Adding the background circles to the nodes
    var bgcircle = node.append("circle")
                       .attr("r", function(d) {
                            //console.log("COUNT for " + d.name + ": " + d.count);
                            return getEntityRadius(d.count);
                       })
                       .attr("class", "background")
                       .attr("fill", entitycolor);

    // Adding the foreground circles to the nodes
    var fgcircle = node.append("circle")
                       .attr("r", circleSize)
                       .attr("class", "foreground")
                       .style("fill", "#fff")
                       .style("stroke", "#ddd")
                       .style("stroke-opacity", function(d) {
                           if(d.selected == true) {
                               return 1;
                           }
                           return .25;
                       });
   
    // Adding icons to the nodes 
    var icon = node.append("svg:image")
                   .attr("class", "icon")
                   .attr("xlink:href", function(d) {
                        return d.image;
                   })
                   .attr("x", iconSize/-2)
                   .attr("y", iconSize/-2)
                   .attr("height", iconSize)
                   .attr("width", iconSize);
                   
    // Add labels?
    if(getSetting(setting_labels) == "true") {
        // Adding shadow text to the nodes 
        var maxLength = getSetting(setting_textlength);
        var shadowtext = node.append("text")
                             .attr("x", iconSize)
                             .attr("y", iconSize/4)
                             .attr("class", "shadow bold")
                             .style("font-size", fontsize, "important")
                             .text(function(d) {
                                 return truncateText(d.name, maxLength);
                             });
            
        // Adding normal text to the nodes 
        var fronttext = node.append("text")
                            .attr("x", iconSize)
                            .attr("y", iconSize/4)
                            .attr("class", "namelabel bold")
                            .attr("fill", textcolor)
                            .style("font-size", fontsize, "important")
                            .text(function(d) {
                                return truncateText(d.name, maxLength);
                            });
    }

    // Selection element
    var selector = svg.append("g")
                      .attr("class", "selector");        
    var selection = selector.append("rect")
                            .attr("id", "selection")
                            .attr("x", 0)
                            .attr("y", 0);
    
    var positions = {};
    
    svg.on("contextmenu", function() {
        d3.event.preventDefault();
    });
    
    svg.on("mousedown", function() {
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
            var color = getSetting(setting_entitycolor);
            d3.selectAll(".node").select(".foreground").style("fill", "#fff");
            d3.selectAll(".node").select(".background").style("fill", color);
        }
    });

    svg.on("mousemove", function() {
        if(rightClicked) {
            // X-positions
            positions.x2 = d3.event.offsetX;
            positions.clickX2 = d3.event.x;

            if(positions.x1 < positions.x2) {
                selection.attr("x", positions.x1);   
            }else{
                selection.attr("x", positions.x2);
            }
            selection.attr("width", Math.abs(positions.x2-positions.x1));
            
            
            // Y-positions
            positions.y2 = d3.event.offsetY;
            positions.clickY2 = d3.event.y;
            
            if(positions.y1 < positions.y2) {
                selection.attr("y", positions.y1);   
            }else{
                selection.attr("y", positions.y2);
            }
            selection.attr("height", Math.abs(positions.y2-positions.y1));
            selection.style("display", "block");
        }
    });
    
    svg.on("mouseup", function() {
        console.log("Mouse up, rightClicked="+rightClicked);
        if(rightClicked) {
            rightClicked = false;
            selection.style("display", "none"); 
            
            // Container offset
            var transX = parseInt(container.attr("translateX"));
            var transY = parseInt(container.attr("translateY"));
            //console.log("TransX: " + transX + ", transY: " + transY);

            // Select nodes
            d3.selectAll(".node").each(function(d, i) {
                var nodePos = $(".node.id"+d.id).offset();
                
                // X in selection box?
                if((nodePos.left >= positions.clickX1 && nodePos.left <= positions.clickX2) ||
                   (nodePos.left <= positions.clickX1 && nodePos.left >= positions.clickX2)) {
                    // Y in selection box?
                    if((nodePos.top >= positions.clickY1 && nodePos.top <= positions.clickY2) ||
                       (nodePos.top <= positions.clickY1 && nodePos.left >= positions.clickY2)) {    
                           // Node is in selection box
                           console.log(d.name + " is in selection box!");
                           d3.select(this).select(".background").style("fill", "#bee4f8");
                           d3.select(this).select(".foreground").style("fill", "#bee4f8");         
                    }
                }
            
            });
        }else{
            // Remove all selections
            var color = getSetting(setting_entitycolor);
            d3.selectAll(".node").select(".background").style("fill", color);
            d3.selectAll(".node").select(".foreground").style("fill", "#fff");
        }
        svg.call(zoomBehaviour); 
    });
    
    // Fish eye check
    if(fisheyeEnabled == "true") {
        // Create fish eye
        var fisheye = d3.fisheye.circular()
                                .radius(250);
                                
        // Listen to mouse movements                         
        svg.on("mousemove", function() {
            force.stop();
            fisheye.focus(d3.mouse(this));
            
            node.each(function(d) { 
                d.fisheye = fisheye(d); 
            })
            .attr("x", function(d) {
                return d.fisheye.x;
            })
            .attr("y", function(d) {
                return d.fisheye.y;
            })
            .attr("transform", function(d) {
                return "translate(" + d.fisheye.x + "," + d.fisheye.y + ")"; 
            });
            
            // Resize background circles
            bgcircle.each(function(d) {
                d.fisheye = fisheye(d);
            })
            .attr("r", function(d) {
                return getEntityRadius(d.count)*d.fisheye.z;
            });
            
            // Resize foreground circles
            fgcircle.each(function(d) {
                d.fisheye = fisheye(d);
            })
            .attr("r", function(d) {
                return circleSize*d.fisheye.z;
            });
            
            // Resize icons
            icon.each(function(d) {
                d.fisheye = fisheye(d);
            })
            .attr("x", function(d) {
                return (iconSize/-2) * d.fisheye.z;
            })
            .attr("y", function(d) {
                return (iconSize/-2) * d.fisheye.z;
            })
            .attr("height", function(d) {
                return iconSize * d.fisheye.z;
            })
            .attr("width", function(d) {
                return iconSize * d.fisheye.z;
            });
            
            // Shadow text
            shadowtext.each(function(d) {
                d.fisheye = fisheye(d);
            })
            .attr("y", function(d) {
                return -iconSize*d.fisheye.z;   
            })
            .style("font-size", function(d) {
                return 10*d.fisheye.z + "px";
            }, "important");
            
            // Front text
            fronttext.each(function(d) {
                d.fisheye = fisheye(d);
            })
            .attr("y", function(d) {
                return -iconSize*d.fisheye.z;   
            })
            .style("font-size", function(d) {
                return 10*d.fisheye.z + "px";
            }, "important");
            
            // Lines
            if(linetype == "curved") {
                // Curved
                //force.stop();
                curvedFisheye(topLines);
                curvedFisheye(bottomLines);
                
                /** Applies fish eye effect to curved lines */
                function curvedFisheye(lines) {
                    lines.each(function(d) {
                        d.fisheye = fisheye(d);
                    })
                    .attr("d", function(d) {
                        var dx = d.target.fisheye.x - d.source.fisheye.x,
                            dy = d.target.fisheye.y - d.source.fisheye.y,
                            dr = Math.sqrt(dx * dx + dy * dy)/1.5,
                            mx = d.source.fisheye.x + dx,
                            my = d.source.fisheye.y + dy;
                            
                        return [
                          "M",d.source.fisheye.x,d.source.fisheye.y,
                          "A",dr,dr,0,0,1,mx,my,
                          "A",dr,dr,0,0,1,d.target.fisheye.x,d.target.fisheye.y
                        ].join(" ");
                    });  
                }
                
            }else{
                /** Applies fish eye effect to straight lines */
                function __straightFisheye(lines) {
                    lines.attr("points", function(d) {
                       return d.source.fisheye.x + "," + d.source.fisheye.y + " " +
                              (d.source.fisheye.x +(d.target.fisheye.x-d.source.fisheye.x)/2) + "," + 
                              (d.source.fisheye.y +(d.target.fisheye.y-d.source.fisheye.y)/2) + " " +  
                              d.target.fisheye.x + "," + d.target.fisheye.y;
                    });
                }
                
                // Straight
                __straightFisheye(topLines);
                __straightFisheye(bottomLines);
            }
        }); 
    }    
    
    // Get everything into positon
    if(gravity == "off") {
        for(var i=0; i<100; i++) {
            force.tick();
        }
        node.attr("fixed", true);
        force.stop(); 
    }
    
    /** Tick handler for curved lines */
    function curvedTick() {
        updateCurvedLines(bottomLines);
        updateCurvedLines(topLines);
    }
    
    /** Updates curved lines */
    function updateCurvedLines(lines) {
        // Calculate the curved segments
        lines.attr("d", function(d) {
            var dx = d.target.x - d.source.x,
                dy = d.target.y - d.source.y,
                dr = Math.sqrt(dx * dx + dy * dy)/1.5,
                mx = d.source.x + dx,
                my = d.source.y + dy;
                
            return [
              "M",d.source.x,d.source.y,
              "A",dr,dr,0,0,1,mx,my,
              "A",dr,dr,0,0,1,d.target.x,d.target.y
            ].join(" ");
        });

        // Update node locations
        updateNodes();
    }
    
    /** Tick handler for straight lines */  
    function straightTick() {
        updateStraightLines(bottomLines);
        updateStraightLines(topLines);
    }
    
    /** Updates straight lines */                   
    function updateStraightLines(lines) {
        // Calculate the straight points
        lines.attr("points", function(d) {
           return d.source.x + "," + d.source.y + " " +
                  (d.source.x +(d.target.x-d.source.x)/2) + "," + 
                  (d.source.y +(d.target.y-d.source.y)/2) + " " +  
                  d.target.x + "," + d.target.y;
        });
        // Update node locations
        updateNodes();
    }
    
    /** Updates node locations */
    function updateNodes() {
        node.attr("transform", function(d) { 
            // Store new position
            var obj = {px: d.px, py: d.py, x: d.x, y: d.y};
            putSetting(d.id, JSON.stringify(obj));
            return "translate(" + d.x + "," + d.y + ")"; 
            
        });
    }
    
    /******************************** SELECTION *******************************/
    function _recordNodeOnClick(event, data, node) {
        var color = getSetting(setting_entitycolor);
        var needSelect = true;
        
        if(settings.selectedNodeIds==null) settings.selectedNodeIds = [];
        
        var recID = ""+data.id;
    
       // getLineLength
        if(event.ctrlKey){  //this.options.multiselect && 
            
            var idx = settings.selectedNodeIds.indexOf(recID);
            if (idx > -1) {
                //deselect all others
                d3.select(node).select(".foreground").style("fill", "#fff");
                d3.select(node).select(".background").style("fill", color);
                needSelect = false;
                
                settings.selectedNodeIds.splice(idx, 1);
            }
        }else{
            //deselect all
            var allnodes = container.selectAll(".node");
            allnodes.select(".foreground").style("fill", "#fff");
            allnodes.select(".background").style("fill", color);
            settings.selectedNodeIds = [];
        }            
        
        if(needSelect){
            //select new
            data.selected = true;
            d3.select(node).select(".foreground").style("fill", "#bee4f8");
            d3.select(node).select(".background").style("fill", "#bee4f8");
            createOverlay(event.offsetX, event.offsetY, "record", "id"+recID, getRecordOverlayData(data));  
            settings.selectedNodeIds.push(recID);
        }
        

        settings.triggerSelection.call(this, settings.selectedNodeIds);
        //if(this.options.isapplication){
        //    $(this.document).trigger( top.HAPI4.Event.ON_REC_SELECT, {selection:selected, source:'d3svg'} );
        //}
        //this._trigger( "onselect", event, selected ); 
    }

} //end visualizeData



// Visualizes selected nodes
function visualizeSelection(selectedNodeIds) {

    settings.selectedNodeIds=selectedNodeIds;
    //deselect all others
    d3.selectAll(".node").select(".foreground").style("fill", "#fff");
    d3.selectAll(".node").select(".background").style("fill", "#fff");
    
    if(selectedNodeIds && selectedNodeIds.length>0){
        //select new ones
        for(var i=0; i<selectedNodeIds.length; i++){
            var node= d3.select(".id"+selectedNodeIds[i]);
            node.select(".foreground").style("fill", "#bee4f8");   
            node.select(".background").style("fill", "#bee4f8");     
        }
    }            
}

$(document).ready(function(e) {
    // Cursor
    $("#d3svg").css("cursor", "url('" +$("#drag").attr("src")+ "') 8 8, pointer");
    $(".mouse-icon").click(function(e) {
        // Indicate the icon is selected
        $(".mouse-icon").removeClass("selected");
        $(this).addClass("selected"); 
        
        // Update cursor
        var src = $(this).attr("src");
        console.log(src);
        $("#d3svg").css("cursor", "url('"+src+"') 8 8, pointer");
    });    
});
