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

/** Retrieving XML relationship data */
var url = "../admin/describe/getRectypeRelationsAsXML.php" + window.location.search;
console.log("Loading XML from: " + url);
var data;
d3.xml(url, "application/xml", function(error, xml) {
    if(error) {
        return alert("Unable to load data, error message: \"" + error.statusText +"\"");
    }
    
    // Now visualize it.
    console.log(xml);
    data = xml;
    visualizeData();
});

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

/** Called when the path has been hovered */
function pathHovered(path) {
    //alert("Path hovered!");
    console.log(path);
    var pointer = path.getAttribute("pointer");
    console.log("Pointer: " + pointer);
}

/** Visualizes the data */ 
function visualizeData() {
    // Holds our nodes
    var nodes = {};
    // Holds the node relations
    var links = [];
    // Records to filter
    var filter = getFilter();
    
    // Going through all Records with namespace 'rootrecord'
    var roots = data.documentElement.getElementsByTagNameNS("rootrecord", "Record");
    console.log(roots);
    for(var i = 0; i < roots.length; i++) {
        var root = roots[i];
        console.log("ROOT");
        console.log(root);
        
        // Get record info
        var rootInfo = getInfo(root);
        console.log(rootInfo);
        
        // Check if we should filter this record
        var index = $.inArray(rootInfo.name, filter);
        console.log("INDEX of " + rootInfo.name + ":" + index); 
        if(index == -1) {
             // Root record check in node list 
            var rootLink = {};
            if(!(rootInfo.name in nodes)) {
                nodes[rootInfo.name] = rootInfo;
                rootLink["target"] = rootInfo;
            }else{
                rootLink["target"] = nodes[rootInfo.name];
            }
         
            // Get through all linked relation Records with namespace 'relationrecord'
            var relations = root.getElementsByTagNameNS("relationrecord", "Record");
            console.log("RELATION");
            console.log(relations);
            if(relations && relations.length > 0) {
                for(var j = 0; j < relations.length; j++ ){
                    var relation = relations[j];
                    console.log(relation);
                   
                    // Get record info 
                    var relationInfo = getInfo(relation);
                    console.log(relationInfo);
         
                    // Relation information for this relation record
                    /*
                    var relationLink = {};
                    if(!(relationInfo.name in nodes)) { // Check if a node with this name has been added already
                         nodes[relationInfo.name] = relationInfo; // It has not; add it to the list of nodes
                         rootLink["target"] = relationInfo;       // Set the target of the root link to this reoord
                         relationLink["source"] = relationInfo;   // Set the source of the relation link to this record
                    }else{ // Node with this name exists already, use that record 
                        rootLink["target"] = nodes[relationInfo.name];     // Set the target of the root link to the existing record
                        relationLink["source"] = nodes[relationInfo.name]; // Set the source of the relation link to the existing record
                    }
                    links.push(rootLink); // Add the root link to the list of links
                    */
                    
                    // Unconstrained check
                    var constrained = isUnconstrained(relation);
                    console.log(constrained);
                    
                    // Check types
                    var types = relation.getElementsByTagName("rel_Name");
                    console.log(types);
                    if(types && types.length > 0) {
                        for(var k = 0; k < types.length; k++) {
                            var type = types[k].textContent;
                            console.log(type);
                        }
                    }
                    
                    // Going through all linked usage Records with namespace 'usagerecord'
                    var usages = relation.getElementsByTagNameNS("usagerecord", "Record");
                    console.log("USAGE"); 
                    console.log(usages);
                    if(usages && usages.length > 0) {
                        for(var k = 0; k < usages.length; k++) {
                            var usage = usages[k];
                            console.log(usage);
                            
                            // Get record info 
                            var usageInfo = getInfo(usage);
                            console.log(usageInfo);
                            
                            // Check if we should filter this record
                            var index = $.inArray(usageInfo.name, filter);
                            console.log("INDEX of " + usageInfo.name + ":" + index); 
                            if(index == -1) {
                                 // Check if we need to add this record to list of nodes
                                /*
                                if(!(usageInfo.name in nodes)) { // Check if a node with this name has been added already 
                                     nodes[usageInfo.name] = usageInfo;  // It has not; add it to the list of nodes
                                     relationLink["target"] = usageInfo; // Set the target of the relation link to this reoord
                                }else{ // Node with this name exists already, use that record 
                                    relationLink["target"] = nodes[usageInfo.name]; 
                                }
                                links.push(relationLink); // Add the relation link to the list of links
                                */
                                if(!(usageInfo.name in nodes)) { // Check if a node with this name has been added already 
                                    nodes[usageInfo.name] = usageInfo; // It has not; add it to the list of nodes
                                    rootLink["source"] = usageInfo;    // Set the target of the root link to this reoord
                                }else{ // Node with this name exists already, use that record 
                                    rootLink["source"] = nodes[usageInfo.name]; 
                                }
                                links.push(rootLink); // Add the root link to the list of links
                            }
                        }
                    }
                }
             }
        } 
    }
    console.log("NODES BEFORE");
    console.log(nodes);
    console.log("LINKS BEFORE");
    console.log(links);
    
    // svg details
    var width = $("svg").width();     // Determine the SVG width
    var height = $("svg").height();   // Determine the SVG height
    $("svg").empty();                 // Remove all child elements
    var svg = d3.select("svg");       // Select the SVG with D3                 
    var iconSize = 16;                // The icon size 
    var offset = 10;                  // Line offsets
    
    // create force.
    var force = d3.layout.force()
                         .nodes(d3.values(nodes))
                         .links(links)
                         .size([width, height])
                         .linkDistance(125)
                         .charge(-700)
                         .on("tick", tick)
                         .start();

    // build the arrow.
    svg.append("svg:defs").selectAll("marker")
                          .data(["end"])
                          .enter().append("svg:marker")
                          .attr("id", String)
                          .attr("viewBox", "0 -5 10 10")
                          .attr("refX", 10)
                          .attr("refY", 0)
                          .attr("markerWidth", 6)
                          .attr("markerHeight", 6)
                          .attr("orient", "auto")
                          .append("svg:path")
                          .attr("d", "M0,-5L10,0L0,5");

    // add the links and the arrows
    var path = svg.append("svg:g").selectAll("path")
                                  .data(force.links())
                                  .enter().append("svg:path")
                                  .attr("class", "link")
                                  .attr("marker-end", "url(#end)")
                                  .attr("pointer", function (d, i) {
                                        return "TestName";
                                  })
                                  .attr("onmouseover", function(d, i) {
                                        console.log("ON MOUSE OVER");
                                        console.log(d);
                                        return "pathHovered(this)";
                                  });

    // define the nodes
    var node = svg.selectAll(".node")
                  .data(force.nodes())
                  .enter().append("g")
                  .attr("class", function(d, i) {
                      return "node " + d.type;
                  })
                  .call(force.drag);

    // add the nodes
    node.append("circle")
        .attr("r", iconSize*0.75)
        .attr("class", function(d, i) {
            console.log("CLASS ATTRIBUTE");
            console.log(d);
            //return d.type;
            return "around";
        });
   
    // add icons  
    node.append("svg:image") 
        .attr("xlink:href", function(d) {
            return d.image;
        })
        .attr("x", iconSize/-2)
        .attr("y", iconSize/-2)
        .attr("height", iconSize)
        .attr("width", iconSize);

    // add the text 
    node.append("text")
        .attr("x", 0)
        .attr("y", iconSize*-1)
        .attr("class", "header")
        .text(function(d) { 
            return d.name; 
        })

    // add the curvy lines
    function tick() {
        path.attr("d", function(d) {
            // Calculating curve
            var dx = d.target.x - d.source.x,
                dy = d.target.y - d.source.y,
                dr = Math.sqrt(dx * dx + dy * dy);
                
            // Line locations   
            var startX = d.source.x,
                startY = d.source.y,
                endX = d.target.x,
                endY = d.target.y;
            
            // Calculcate line offets based on direction; 
            // This is needed because to make sure the lines stay out of the circles
            if(endX >= startX) { // EAST
                if(endY >= startY) { // SOUTH
                    // SOUTH EAST
                    startX += offset;
                    startY += offset/2;
                    endX -= offset/2;
                    endY -= offset;
                }else{ // NORTH
                    // NORTH EAST
                    startX += offset;
                    startY -= offset/2;
                    endX -= offset/2;
                    endY += offset; 
                }
            }else{ // EAST
                if(endY >= startY) { // SOUTH
                    // SOUTH WEST;
                    startX -= offset;
                    startY += offset/2;
                    endX += offset/2;
                    endY -= offset;
                }else{ // NORTH
                    // NORTH WEST
                    startX -= offset;
                    startY -= offset/2;
                    endX += offset/2;
                    endY += offset;
                }
            }
                
            return "M" + 
                startX + "," + 
                startY + "A" + 
                dr + "," + dr + " 0 0,1 " + 
                endX + "," + 
                endY;
        });

        node.attr("transform", function(d) { 
            return "translate(" + d.x + "," + d.y + ")"; 
        });
    }
    
    console.log("NODES AFTER");
    console.log(nodes);
    console.log("LINKS AFTER");
    console.log(links);

}

/** Returns a String array containing names of records to filter */
function getFilter() {
    var names = [];
    $("input[type='checkbox']").each(function() {
        var checked = $(this).is(':checked'); 
        console.log("CHECKED: " + checked);
        if(!checked) {
            var name = $(this).attr("name");
            names.push(name);
        }
    });
    console.log("FILTER NAMES");
    console.log(names);
    return names;
}

/** Listen to checkbox changes */  
$("input[type='checkbox']").change(function(e) {
    visualizeData();  
});