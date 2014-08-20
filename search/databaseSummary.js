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

/** D3 */
/*var data = [
    {
        name: 'City',
        columns: [
            'id',
            'country_id',
            'name',
            'population'
        ]
    },
    {
        name: 'Country',
        columns: [
            'id',
            'continent_id',
            'name',
            'population',
            'gpd',
            'average_salary',
            'birth_rate'
        ]
    },
    {
        name: 'Continent',
        columns: [
            'id',
            'name',
            'population'
        ]
    }
];
console.log(data);                    

/** SVG details */
/*var fieldHeight = 30;
var fieldWidth = 150;
var offset = 300;
var svg = d3.select("svg");

/**
* Attaches drag detection to an element
*/
/*var drag = d3.behavior.drag()
             .origin(function() { 
                var t = d3.select(this);
                return {x: t.attr("x"), y: t.attr("y")};
             })
             .on("drag", dragMove);

/**
* Called when an element has been dragged
*/
/*function dragMove(d) {
    console.log(d3.event);
    var x = d3.event.x;
    var y = d3.event.y;
    
    d3.select(this)
      .attr("x", x)
      .attr("y", y)
      .attr("transform", "translate(" +x+ ", " +y+ ")");
}

/**
* Creates a 'g' element for each table
*/
/*var tables = svg.selectAll("g")
                .data(data)
                .enter()
                .append("g")
                .attr("class", "table")
                .attr("x", function(d, i) { 
                    console.log(offset);
                    return (offset + i*fieldWidth*1.05);
                })
                .attr("y", 0)
                .attr("transform", function(d, i) {
                    console.log(offset);
                    return "translate(" + (offset + i*fieldWidth*1.1) + ", 0)"; 
                })
                .call(drag);

/**
* Creates a 'g' element for each cell inside the table
*/
/*var cells = tables.selectAll("g")
                  .data(function(d) {
                        return d3.values(d.columns);
                    })
                  .enter()
                  .append("g")
                  .attr("class", "cell")
                  .attr("transform", function(d, i) {
                        return "translate(0, " + i*fieldHeight + ")"; 
                  });
/**
* Creates a 'rect' element inside a cell
*/
/*cells.append("rect")
     .attr("class", "empty")
     .attr("width", fieldWidth-1)
     .attr("height", fieldHeight-1);

/**
* Creates a 'text' element inside a cell
*/
/*cells.append("text")
     .text(String)
     .attr("class", "center")
     .attr("x", fieldWidth/2)
     .attr("y", fieldHeight/2);

/** Retrieving XML relationship data */
var url = "../admin/describe/getRectypeRelationsAsXML.php" + window.location.search;
console.log("Loading XML from: " + url);
d3.xml(url, "application/xml", function(error, xml) {
    if(error) {
        return alert("Unable to load data, error message: \"" + error.statusText +"\"");
    }
    
    // Now visualize it.
    console.log(xml);
    visualizeData(xml);
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
function visualizeData(xml) {
    // Holds our nodes
    var nodes = {};
    // Holds the node relations
    var links = [];
    
    // Going through all Records with namespace 'rootrecord'
    var roots = xml.documentElement.getElementsByTagNameNS("rootrecord", "Record");
    console.log(roots);
    for(var i = 0; i < roots.length; i++) {
        var root = roots[i];
        console.log("ROOT");
        console.log(root);
        
        // Get record info
        var rootInfo = getInfo(root);
        console.log(rootInfo);
        
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
    console.log("NODES BEFORE");
    console.log(nodes);
    console.log("LINKS BEFORE");
    console.log(links);
    
    // svg details
    var width = $("svg").width();         // Get the SVG width
    var height = $("svg").height();       // Get the SVG height
    var svg = d3.select("svg");           // Select the SVG with D3
    
    // create force.
    var force = d3.layout.force()
                         .nodes(d3.values(nodes))
                         .links(links)
                         .size([width, height])
                         .linkDistance(100)
                         .charge(-500)
                         .on("tick", tick)
                         .start();

    // build the arrow.
    svg.append("svg:defs").selectAll("marker")
                          .data(["end"])
                          .enter().append("svg:marker")
                          .attr("id", String)
                          .attr("viewBox", "0 -5 10 10")
                          .attr("refX", 15)
                          .attr("refY", -1.5)
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
                  .attr("class", "node")
                  .call(force.drag);

    // add the nodes
    node.append("circle")
        .attr("r", 5)
        .attr("class", function(d, i) {
            console.log("CLASS ATTRIBUTE");
            console.log(d);
            return d.type;
        });
    
    // add icons  
    node.append("svg:image") 
        .attr("xlink:href", function(d) {
            if(d.type == "rootrecord") {
                return d.image;
            }
        })
        .attr("height", 16)
        .attr("width", 16);

    // add the text 
    node.append("text")
        .attr("x", 12)
        .attr("dy", ".35em")
        .text(function(d) { 
            return d.name; 
        });

    // add the curvy lines
    function tick() {
        path.attr("d", function(d) {
            //console.log(d);
            var dx = d.target.x - d.source.x,
                dy = d.target.y - d.source.y,
                dr = Math.sqrt(dx * dx + dy * dy);
            return "M" + 
                d.source.x + "," + 
                d.source.y + "A" + 
                dr + "," + dr + " 0 0,1 " + 
                d.target.x + "," + 
                d.target.y;
        });

        node.attr("transform", function(d) { 
            return "translate(" + d.x + "," + d.y + ")"; 
        });
    }
    
    console.log("NODES AFTER");
    console.log(nodes);
    console.log("LINKS AFTER");
    console.log(links);

    return false;
    
    
    
    
    
    // OLDER
    
    var width = $("svg").width();         // Get the SVG width
    var height = $("svg").height();       // Get the SVG height
    var offsetX = 50;                     // Starting x-offset
    var offsetY = 25;                     // Starting y-offset
    var iconSize = 16;                    // The size of the icons
    var spacingX = 125;                   // Spacing in x direction between records
    var spacingY = 75;                    // Spacing in y direction between records
    var items = (width / spacingX) - 1;   // As many items horizontally as possible.
    var svg = d3.select("svg");           // Select the SVG with D3
    
    
    /** Called to give an element drag abilities */
    var drag = d3.behavior.drag()
                 .origin(function() { 
                    var t = d3.select(this);
                    return {x: t.attr("x"), y: t.attr("y")};
                 })
                 .on("drag", dragMove);

    /** Helper method to use when an element has been dragged */
    function dragMove(d) {
        console.log(d3.event);
        var x = d3.event.x;
        var y = d3.event.y;
        
        d3.select(this)
          .attr("x", x)
          .attr("y", y)
          .attr("transform", "translate(" +x+ ", " +y+ ")");
    }
    
    /** Group creation for all records */     
     var count = 0;                                    
     var baseRecords = svg.selectAll("g")
                         //.data(xml.documentElement.getElementsByTagNameNS("rootrecord", "Record"))
                         .data(xml.documentElement.getElementsByTagName("Record"))
                         .enter()
                         .append("g")
                         .attr("class", "record")
                         .attr("id", function(d, i) {
                             console.log("ROOT RECORDS");
                             console.log(d);
                             var namespace = d.namespaceURI;  // Name space of the Recod
                             var id = d.getElementsByTagName("rec_ID")[0].textContent; // Use Records internal ID.
                             return namespace + id; 
                         }) 
                         .attr("visibility", function(d, i) {
                             console.log("VISIBLITY");
                             console.log(d);
                             
                             return "visible";
                         })
                         .attr("x", function(d, i) {
                             return offsetX + i % items * spacingX;
                         })
                         .attr("y", function(d, i) {
                             return offsetY + Math.floor(i / items) * spacingY;
                         })        
                         .attr("transform", function(d, i) {
                             var x = offsetX + i % items * spacingX;    
                             var y = offsetY + Math.floor(i / items) * spacingY; 
                             return "translate(" +x+ "," +y+ ")"; // Calculate location
                         })
                         .call(drag); 
                         
    /** Circle background for the records */              
    var circles = baseRecords.append("circle")      
                           .attr("class", "around")
                           .attr("cx", iconSize/2)
                           .attr("cy", iconSize/2)
                           .attr("r", iconSize);
                           
    /** Icons for the records */              
    var icons = baseRecords.append("svg:image") 
                           .attr("xlink:href", function(d) {
                                return d.getElementsByTagName("rec_Image")[0].textContent; // Grab Record image
                           })
                           .attr("height", iconSize)
                           .attr("width", iconSize);
                           
    /** Names for the records */
    var names = baseRecords.append("text")      
                           .attr("class", "header")
                           .attr("x", iconSize/2)    
                           .attr("y", iconSize*-0.75)
                           .text(function(d, i) {
                               return d.getElementsByTagName("rec_Name")[0].textContent; // Grab Record name
                           });
                           
    
    
    
}