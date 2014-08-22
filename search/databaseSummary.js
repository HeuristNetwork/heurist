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
                                    link["target"] = rootInfo;
                                }else{
                                    link["target"] = nodes[rootInfo.name];
                                }
                                
                                // Link construction; add usage record info
                                if(!(usageInfo.name in nodes)) { // Check if a node with this name has been added already 
                                    nodes[usageInfo.name] = usageInfo; // It has not; add it to the list of nodes
                                    link["source"] = usageInfo;    // Set the target of the root link to this reoord
                                }else{ // Node with this name exists already, use that record 
                                    link["source"] = nodes[usageInfo.name]; 
                                }
                                
                                // Add link to array
                                link["pointer"] = relationInfo.name;
                                links.push(link);
                            }
                        }
                    }
                }
             }
        } 
    }
    
    // Construct a data object and return it.
    var data = {"nodes": nodes, "links": links};
    console.log("Data has been transformed to D3 format:");
    console.log(data);
    return data;
}








/** Visualizes the data */ 
function visualizeData() {
    // Variables
    var width = $("svg").width();     // Determine the SVG width
    var height = $("svg").height();   // Determine the SVG height
    $("svg").find("g").remove();      // Remove all old elements
    var svg = d3.select("svg");       // Select the SVG with D3                 
    var iconSize = 16;                // The icon size 
    var offset = 10;                  // Line offsets
    var data = convertData();         // Convert XML to usable D3 data
    
    // Settings
    var linetype = localStorage.getItem("linetype");

    // Creating D3 force
    var force = d3.layout.force()
                         .nodes(d3.values(data.nodes))
                         .links(data.links)
                         .charge(-700) 
                         .linkDistance(200) 
                         .on("tick", function(d, i) {
                             if(linetype == "curved") {
                                 return curvedTick();
                             }else{
                                 return straightTick();
                             }
                         })
                         .size([width, height])
                         .start();

    // Add the chosen lines
    var lines;
    if(linetype == "curved") {
        // Add curved lines
         lines = svg.append("svg:g").selectAll("path")
                                    .data(force.links())
                                    .enter().append("svg:path")
                                    .attr("class", "link")
                                    .attr("marker-mid", "url(#marker)") 
                                    .style("stroke-width", function(d) { 
                                      var width = Math.log(d.source.count);
                                      if(width > 1) {
                                          width = Math.sqrt(width);
                                      }
                                      return 0.5 + width;
                                   })    
                                    .attr("pointer", function(d, i) {
                                        return d.pointer;
                                    })
                                    .attr("onmouseover", function(d, i) {
                                        return "pathHovered(this)";
                                    });
    }else{
        // Add straight lines
        lines = svg.append("svg:g").selectAll("line.link")
                                   .data(force.links())
                                   .enter()
                                   .append("svg:line")
                                   .attr("class", "link")
                                   .attr("pointer", function(d, i) {
                                        return d.pointer;
                                    })
                                   .style("stroke-width", function(d) { 
                                      var width = Math.log(d.source.count);
                                      if(width > 1) {
                                          width = Math.sqrt(width);
                                      }
                                      return 0.5 + width;
                                   })
                                   .attr("x1", function(d) { return d.source.x; })
                                   .attr("y1", function(d) { return d.source.y; })
                                   .attr("x2", function(d) { return d.target.x; })
                                   .attr("y2", function(d) { return d.target.y; });
    }
                       
    // Defining the nodes
    var node = svg.selectAll(".node")
                  .data(force.nodes())
                  .enter().append("g")
                  .attr("class", function(d, i) {
                      return "node " + d.type;
                  }) 
                  .attr("transform", "translate(100, 100)") 
                  .call(force.drag);

    // Adding circles to the nodes
    node.append("circle")
        .attr("r", iconSize*0.75)
        .attr("class", "around");
   
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
        .attr("class", "header")
        .text(function(d) { 
            return d.name; 
        });
        
    
     /** Tick handler for curved lines */
    function curvedTick() {
        // Calculate the segments
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

        // Update node location
        node.attr("transform", function(d) { 
            return "translate(" + d.x + "," + d.y + ")"; 
        });
    }
    
    /** Tick handler for straight lines */                           
    function straightTick() {
        lines.attr("x1", function(d) { return d.source.x; })
             .attr("y1", function(d) { return d.source.y; })
             .attr("x2", function(d) { return d.target.x; })
             .attr("y2", function(d) { return d.target.y; });
 
         node.attr("transform", function(d) { 
            return "translate(" + d.x + "," + d.y + ")"; 
        });
    }
    
}


/** Called when the path has been hovered */
function pathHovered(path) {
    //alert("Path hovered!");
    console.log(path);
    var pointer = path.getAttribute("pointer");
    //console.log("Pointer: " + pointer);
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

/** Listen to 'show-record' checkbox changes */  
$(".show-record").change(function(e) {
    visualizeData();  
});

/** Listen to the 'show-all' checkbox */
$("#show-all").change(function(e) {
    var checked= $(this).is(':checked');
    console.log("CHECKED: " + checked);
    $(".show-record").prop("checked", checked);
    visualizeData();
});





/** Returns the line type setting */
function getLineType() {
    return $("#linetype").val();
}

/** Listens to linetype selection changes */
$("#linetype").change(function(e) {
    localStorage.setItem("linetype", getLineType());
    visualizeData();
});




/** Body is done with loading */
var xml;
$(document).ready(function() {
    /** Updating settings in UI */
    // Line type setting
    var linetype = localStorage.getItem("linetype");
    $("#linetype option[value='" +linetype+ "']").attr("selected", true);
    
    /** Retrieving XML relationship data */
    var url = "../admin/describe/getRectypeRelationsAsXML.php" + window.location.search;
        d3.xml(url, "application/xml", function(error, xmlData) {
        // If there is an error, show it to the user
        if(error) {
            return alert("Unable to load data, error message: \"" + error.statusText +"\"");
        }
        
        // Now visualize it.
        console.log("XML succesfully loaded from server:");
        console.log(xmlData);
        xml = xmlData;
        visualizeData();
    });

});