/*
* Copyright (C) 2005-2014 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* Heurist Database Summary Helper 
*
* @author      Jan Jaap de Groot    <jjedegroot@gmail.com>    
* @copyright   (C) 2005-2014 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

/** Attempts to grab the first value after searching for a tag*/
function getValue(element, query) {
    var results = element.getElementsByTagName(query);
    if(results && results.length > 0) {
        return results[0];
    }
    return null;
}

/** Returns the ID value of a record */
function getID(record) {
    return getValue(record, "rec_ID").textContent;                
}

/** Returns the name value of a record */
function getName(record) {
    return getValue(record, "rec_Name").textContent;                
}

/** Returns the count value of a record */
function getCount(record) {
    return getValue(record, "rec_Count").textContent;                
}

/** Returns the image value of a record */
function getImage(record) {
    return getValue(record, "rec_Image").textContent;               
}

/** Returns an object containing the id, name, count and image of a record */
function getInfo(record) {
    var id = getID(record);
    var name = getName(record);
    var count = getCount(record);
    var image = getImage(record);
    var type = record.namespaceURI;
    return {"id": id, "name": name, "count": count, "image": image, "type": type};
}

/** Returns the relation value of a record */
function getRelations(record) {       
    return getValue(record, "rec_Relations");           
}

/** Returns the unconstrained value of a record */
function isUnconstrained(record) {
    var value = getValue(record, "rel_Unconstrained");
    if(value && value === "true") {
        return true;
    }
    return false;
}    


/** Converts the raw XML data to D3 usable nodes and links */
function convertData() {
    // Holds a list of unique nodes
    // Has attributes 'id', 'name', 'count', 'image' and 'type'
    var nodes = {};
    
    // Relationships between the nodes. 
    // Has attributes 'source' and 'target'
    var links = [];
    
    // A string array containing names of nodes to filter.
    var filter = getFilter();
    
    // Going through all Records with namespace 'rootrecord'
    var roots = xml.documentElement.getElementsByTagNameNS("rootrecord", "Record");
    for(var i = 0; i < roots.length; i++) {
        // Retrieve info about this record
        var root = roots[i];
        var rootInfo = getInfo(root);
        //console.log(rootInfo);
        
        // Check if we should filter this record
        var index = $.inArray(rootInfo.name, filter);
        if(index == -1 && rootInfo.count > 0) {
            // Going through all linked relation Records with namespace 'relationrecord'
            var relations = root.getElementsByTagNameNS("relationrecord", "Record");
            if(relations && relations.length > 0) {
                for(var j = 0; j < relations.length; j++ ){
                    // Get record info 
                    var relation = relations[j];
                    var relationInfo = getInfo(relation);
                    //console.log(relationInfo);
         
                    // Unconstrained check
                    var constrained = isUnconstrained(relation);
                    //console.log(constrained);
                    
                    // Check types
                    var types = relation.getElementsByTagName("rel_Name");
                    //console.log(types);
                    if(types && types.length > 0) {
                        for(var k = 0; k < types.length; k++) {
                            var type = types[k].textContent;
                            //console.log(type);
                        }
                    }
                    
                    // Going through all linked usage Records with namespace 'usagerecord'
                    var usages = relation.getElementsByTagNameNS("usagerecord", "Record");
                    if(usages && usages.length > 0) {
                        for(var k = 0; k < usages.length; k++) {
                            // Get record info 
                            var usage = usages[k];
                            var usageInfo = getInfo(usage);
                            //console.log(usageInfo);
                            
                            // Check if we should filter this record
                            var index = $.inArray(usageInfo.name, filter); 
                            if(index == -1 && usageInfo.count > 0) {
                                // Construct a link; add root record info
                                var link = {};
                                if(!(rootInfo.name in nodes)) {
                                    nodes[rootInfo.name] = rootInfo;
                                    link["source"] = rootInfo;
                                }else{
                                    link["source"] = nodes[rootInfo.name];
                                }
                                
                                // Link construction; add usage record info
                                if(!(usageInfo.name in nodes)) { // Check if a node with this name has been added already 
                                    nodes[usageInfo.name] = usageInfo; // It has not; add it to the list of nodes
                                    link["target"] = usageInfo;    // Set the target of the root link to this reoord
                                }else{ // Node with this name exists already, use that record 
                                    link["target"] = nodes[usageInfo.name]; 
                                }
                                
                                // Add link to array
                                link["pointer"] = relationInfo.name;
                                links.push(link);
                            }else{
                                // Display root node anyways
                                if(!(rootInfo.name in nodes)) {
                                    nodes[rootInfo.name] = rootInfo;
                                }
                            }
                        }
                    }
                }
             }
        } 
    }
    
    // Construct a data object and return it.
    var obj = {"nodes": nodes, "links": links};
    console.log("Data has been transformed to D3 format:");
    console.log(obj);
    return obj;
}








/** Calculates the line width based on count */
function getLineWidth(count) {
    var width = Math.log(count);
    if(width > 1) {
        width = Math.sqrt(width);
    }
    return Math.round(width);
}


/** Called when the path has been hovered */
function pathHovered(path) {
    //alert("Path hovered!");
    //console.log(path);
    var pointer = path.getAttribute("pointer");
    //console.log("Pointer: " + pointer);
}


/** Visualizes the data */ 
var iconSize = 16; // The icon size 
var offset = 10;   // Line offsets
function visualizeData() {
    // SVG data    
    var width = $("svg").width();     // Determine the SVG width
    var height = $("svg").height();   // Determine the SVG height    
    $("svg").empty();                 // Remove all old elements
    var svg = d3.select("svg");       // Select the SVG with D3                 
    var data = convertData();         // Convert XML to usable D3 data
    
    // Color settings
    var linecolor = getLineColor();
    var markercolor = getMarkerColor();
    var countcolor = getCountColor();
    var textcolor = getTextColor();
    
    // Line settings
    var linetype = getLineType();
    var linethickness = getLineThickness();
    var linelength = getLineLength();
   
    // Gravity settings
    var gravity = getGravity(); 
    var attraction = getAttraction();
 
    // Creating D3 force
    var force = d3.layout.force()
                         .nodes(d3.values(data.nodes))
                         .links(data.links)
                         .charge(attraction)        // Using the attraction setting
                         .linkDistance(linelength)  // Using the linelength setting 
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
    svg.append("defs").selectAll("marker")
                      .data(force.links())
                      .enter()
                      .append("svg:marker")    
                      .attr("id", function(d) {
                            return "marker" + d.source.id;
                      })
                      .attr("markerWidth", function(d) {
                          return 4 + getLineWidth(d.source.count);
                      })
                      .attr("markerHeight", function(d) {
                          return 4 + getLineWidth(d.source.count);
                      })
                      .attr("refX", -1)
                      .attr("refY", 0)
                      .attr("viewBox", "0 -5 10 10")
                      .attr("markerUnits", "userSpaceOnUse")
                      .attr("orient", "auto")
                      .attr("fill", function(d) {
                          // Using the markercolor setting to determine the color
                          if(markercolor) {
                              return markercolor;
                          }
                          return "#000";
                      })
                      .attr("opacity", "0.6")
                      .append("path")
                      .attr("d", "M0,-5L10,0L0,5");

    // Add the chosen lines [using the linetype setting]  
    var lines;
    if(linetype == "curved") {
        // Add curved lines
         lines = svg.append("svg:g").selectAll("path")
                                    .data(force.links())
                                    .enter()
                                    .append("svg:path");
    }else{
        // Add straight lines
        lines = svg.append("svg:g").selectAll("polyline.link")
                                   .data(force.links())
                                   .enter()
                                   .append("svg:polyline");
    }     
    // Adding shared attributes
    lines.attr("class", "link") 
         .attr("stroke", function(d) { 
             // Using the linecolor setting to determine the color   
             if(linecolor) {
                 return linecolor;
             }else{
                 return "#999";
             }
         })
         .attr("pointer", function(d, i) {
            return d.pointer;
         })
         .attr("marker-mid", function(d) {
            return "url(#marker" + d.source.id + ")";
         })
         .style("stroke-width", function(d) { 
            return 0.5 + getLineWidth(d.source.count)/2;
         })
         .attr("onmouseover", function(d, i) {
            return "pathHovered(this)";
         });
         
    // Check what methods to call on drag
     var node_drag;
     if(gravity === "on") {
         // Default method
         node_drag = force.drag;
     }else{
         // Custom methods
         node_drag = d3.behavior.drag()
                                .on("dragstart", dragstart)
                                .on("drag", dragmove)
                                .on("dragend", dragend);
     }
                               
    /** Called when a dragging event starts */
    function dragstart(d, i) {
        force.stop() // Stop force from auto positioning
    }

    /** Caled when a dragging move event occurs */
    function dragmove(d, i) {
        // Update locations
        d.px += d3.event.dx;
        d.py += d3.event.dy;
        d.x += d3.event.dx;
        d.y += d3.event.dy;  
        
        // Update nodes & lines                                                           
        if(linetype == "curved") { 
             curvedTick();
        }else{
             straightTick();
        }
    }

    /** Called when a dragging event ends */
    function dragend(d, i) {
         d.fixed = true; // Node may not be auto positioned
         
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
    var node = svg.selectAll(".node")
                  .data(force.nodes())
                  .enter().append("g")
                  .attr("class", function(d, i) {
                      return "node " + d.type;
                  }) 
                  .attr("transform", "translate(100, 100)")
                  .call(node_drag);
    
    // Adding the background circles to the nodes
    node.append("circle")
        .attr("r", function(d) {
            return iconSize*0.75 + getLineWidth(d.count)*3
        })
        .attr("class", "background")
        .attr("fill", function(d) {
            // Using the countcolor setting to determine the color
            if(countcolor) {
                 return countcolor;
            }
            return "gray";
        });

    // Adding the foreground circles to the nodes
    node.append("circle")
        .attr("r", iconSize*0.75)
        .attr("class", "around foreground");
   
    // Adding icons to the nodes 
    node.append("svg:image") 
        .attr("xlink:href", function(d) {
            return d.image;
        })
        .attr("x", iconSize/-2)
        .attr("y", iconSize/-2)
        .attr("height", iconSize)
        .attr("width", iconSize);

    // Adding shadow text to the nodes 
    node.append("text")
        .attr("x", 0)
        .attr("y", -iconSize)
        .attr("class", "shadow")
        .text(function(d) { 
            return d.name; 
        })
        
    // Adding normal text to the nodes 
    node.append("text")
        .attr("x", 0)
        .attr("y", -iconSize)
        .attr("class", "namelabel")
        .attr("fill", function(d) {
            if(textcolor) {
                return textcolor;
            }
            return "#a00";
        })
        .text(function(d) { 
            return d.name; 
        });
    
    /** Tick handler for curved lines */
    function curvedTick() {
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
        node.attr("transform", function(d) { 
            return "translate(" + d.x + "," + d.y + ")"; 
        });
    }
    
    /** Tick handler for straight lines */                           
    function straightTick() {
        // Calculate the straight points
        lines.attr("points", function(d) {
           return d.source.x + "," + d.source.y + " " +
                  (d.source.x +(d.target.x-d.source.x)/2) + "," + (d.source.y +(d.target.y-d.source.y)/2) + " " +  
                  d.target.x + "," + d.target.y;
        });

        // Update node locations
        node.attr("transform", function(d) { 
            return "translate(" + d.x + "," + d.y + ")"; 
        });
    }
    
}










/** Returns a String array containing names of records to filter */
function getFilter() {
    var names = [];   
    $(".show-record").each(function() {
        var checked = $(this).is(':checked'); 
        if(!checked) {
            var name = $(this).attr("name");
            names.push(name);
        }
    });
    return names;
}

/** Returns the line color setting */
function getLineColor() {
    return localStorage.getItem("linecolor");
}

/** Returns the marker color setting */
function getMarkerColor() {
    return localStorage.getItem("markercolor");
}

/** Returns the count color setting */
function getCountColor() {
    return localStorage.getItem("countcolor");
}

/** Returns the text color setting */
function getTextColor() {
    return localStorage.getItem("textcolor");
}

/** Returns the line type setting */
function getLineType() {
    return localStorage.getItem("linetype");
}

/** Returns the line thickness setting */
function getLineThickness() {
    return localStorage.getItem("linethickness");
}

/** Returns the line length setting */
function getLineLength() {
    return localStorage.getItem("linelength");
}

/** Returns the attraction strength setting */
function getAttraction() {
    return localStorage.getItem("attraction");
}

/** Returns the gravity setting */
function getGravity() {
    return localStorage.getItem("gravity");
}

/** Body is done with loading */
var xml;
$(document).ready(function() {
    /** LINE COLOR SETTING */
    // Set line color setting in UI
    var linecolor = getLineColor(); 
    if(linecolor) {
        $("#linecolor").css("background-color", linecolor);
    }
    
    // Listen to 'line color' selection changes
    $('#linecolor').colpick({
        layout: 'hex',
        onSubmit: function(hsb, hex, rgb, el) {
            var color = "#"+hex; 
            
            localStorage.setItem("linecolor", color);
            $(".link").attr("stroke", color);
    
            $(el).css('background-color', color);
            $(el).colpickHide();
        }
    });
    
    /** MARKER COLOR SETTING */
    // Set marker color in UI
    var markercolor = getMarkerColor();
    if(markercolor) {
        $("#markercolor").css("background-color", markercolor);
    }
    
    // Listen to 'marker color' selection changes
    $('#markercolor').colpick({
        layout: 'hex',
        onSubmit: function(hsb, hex, rgb, el) {
            var color = "#"+hex; 
            
            localStorage.setItem("markercolor", color);
            $("marker").attr("fill", color);
            
            $(el).css('background-color', color);
            $(el).colpickHide();
        }
    });
    
    /** COUNT COLOR SETTING */
    // Set count color in UI
    var countcolor = getCountColor();
    if(countcolor) {
         $("#countcolor").css("background-color", countcolor);
    }
    
    // Listen to 'count color' selection changes
    $('#countcolor').colpick({
        layout: 'hex',
        onSubmit: function(hsb, hex, rgb, el) {
            var color = "#"+hex; 
            
            localStorage.setItem("countcolor", color);
            $(".background").attr("fill", color);
            
            $(el).css('background-color', color);
            $(el).colpickHide();
        }
    });
    
    /** TEXT COLOR SETTING */
    // Set text color in UI
    var textcolor = getTextColor();
    if(textcolor) {
         $("#textcolor").css("background-color", textcolor);
    }
    
    // Listen to 'count color' selection changes
    $('#textcolor').colpick({
        layout: 'hex',
        onSubmit: function(hsb, hex, rgb, el) {
            var color = "#"+hex; 
            
            localStorage.setItem("textcolor", color);
            $(".namelabel").attr("fill", color);
            
            $(el).css('background-color', color);
            $(el).colpickHide();
        }
    });
    
    /** LINE TYPE SETTING */
    // Set line type setting in UI
    var linetype = getLineType();
    if(linetype) {
        $("#linetype option[value='" +linetype+ "']").attr("selected", true);
    }
    
    // Listens to linetype selection changes
    $("#linetype").change(function(e) {
        localStorage.setItem("linetype", $("#linetype").val());
        visualizeData();
    });
    
    /** LINE THICKNESS SETTING */
    // Set line thickness setting in UI
    var linethickness = getLineThickness();
    if(linethickness) {
        $("#linethickness option[value='" +linethickness+ "']").attr("selected", true);
    }
    
    // Listen to linethickness selection changes
    $("#linethickness").change(function(e) {
        localStorage.setItem("linethickness", $(this).val());
        visualizeData();
    });
    
    /** LINE LENGTH SETTING */
    // Set line length setting in UI
    var linelength = getLineLength();
    if(linelength) {
        $("#linelength").val(linelength); 
    }
    
    // Listen to linelength changes
    $("#linelength").change(function() {
        localStorage.setItem("linelength", $(this).val());
        visualizeData();
    });
    
    /** GRAVITY SETTING */
    // Set gravity setting in UI
    var gravity = getGravity();
    if(gravity) {                      
        $("#gravity option[value='" +gravity+ "']").attr("selected", true);
    }

    // Listen to gravity changes
    $("#gravity").change(function() {
        localStorage.setItem("gravity",  $(this).val());
        visualizeData();
    });
    
    /** ATTRACTION SETTING */
    // Set attraction setting in UI
    var attraction = getAttraction();
    if(attraction) {
        $("#attraction").val(attraction);
    }
    
    // Listen to attraction changes
    $("#attraction").change(function() {
        localStorage.setItem("attraction", $(this).val());
        visualizeData();
    });
    
    /** RECORD FILTERING */
    // Listen to 'show-record' checkbox changes  
    $(".show-record").change(function(e) {
        visualizeData();  
    });

    // Listen to the 'show-all' checkbox 
    $("#show-all").change(function() {
        var checked = $(this).is(':checked');  
        $(".show-record").prop("checked", checked);
        visualizeData();
    });
    
    
    
    
    /** BUILDING THE VISUALISATION */
    var url = "../admin/describe/getRectypeRelationsAsXML.php" + window.location.search;
    console.log("Loading XML data from: " + url);
    d3.xml(url, "application/xml", function(error, xmlData) {
        // If there is an error, show it to the user
        if(error) {
            return alert("Unable to load data: \"" + error.statusText +"\"");
        }
        
        // Now visualize it.
        console.log("XML succesfully loaded from server:");
        console.log(xmlData);
        xml = xmlData;
        visualizeData();
    });

});