<?php

    /**
    * databaseSummary.php
    * request aggregation query for all records grouped by record type
    * display table of record types and counts and SVG entity connections schema scaled with counts (built with D3.js)
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
    * @author      Artem Osmakov     <artem.osmakov@sydney.edu.au>
    * @author      Jan Jaap de Groot <jjedegroot@gmail.com>  SVG schema
    * @author      Ian Johnson       <ian.johnson@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     3.2
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */
?>

<html>

    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Database Summary</title>
        
        <!-- D3 -->
        <script type="text/javascript" src="../external/d3/d3.js"></script>
        <script type="text/javascript" src="../external/d3/fisheye.js"></script>
        
        <!-- jQuery -->
        <script type="text/javascript" src="../external/jquery/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
        
        <!-- Colpick -->
        <script type="text/javascript" src="../external/colpick/colpick.js"></script>
        <link rel="stylesheet" type="text/css" href="../external/colpick/colpick.css">
        
        <!-- Plugin -->
        <script type="text/javascript" src="plugin.js"></script>
        
        <style>
            /** Heurist table */
            #container, #settings, #visualisation, svg {
                width: 100%;
                height: 100%;
            }

            h3 {
                padding: 3px;
                margin: 0px;
            }

            /** Table */
            .records {
                overflow: scroll;
                border: none;
            }

            table {
                table-layout: auto;
                border-color: black;   
                border-collapse: collapse;       
            }
            
            td, th {
                vertical-align: top;
                margin: 0px;
                padding: 1px;  
            }
            
            .records td, .records th {
                border: 1px solid black;
            }
            
            .zerocounts {
                border-top: 3px solid black;
            }

            .row:hover {
                background-color: #CCCCCC;
            }

            a:hover, input:hover {
                text-decoration: none;
                cursor: pointer;
            }

            /** Settings */
            .space {
                padding-left: 5px;
                padding-right: 5px;     
            }
            
            div.settings {
                display: inline-block;
            }
            
            div.color {
                cursor: pointer;
                display: inline-block;
                width: 10px;
                height: 10px;
                border: 1px solid black; 
                margin-top: 4px;
            }
            
            #linecolor {
                background-color: #999;
            }

            #markercolor {
                background-color: #000;
            }
            
            #countcolor {
                background-color: #22a;
            }

            .middle {
               vertical-align: middle; 
            }
            
            input.small {
                cursor: pointer;
                max-width: 40px;
                text-align: center;
            }

            
            /** SVG related */
            #visualisation {
                border-left: 1px dashed black;
            }
            
            svg {
                border-top: 1px dashed black;
            }
            
            /** Move records around */
            g:hover {
                cursor: pointer;
            }

            /** Lines between records */
            .link {
                fill: none;    
                stroke-opacity: .6;  
            }
     
            .link:hover {
                cursor: pointer;
            }

            /** Circle around icon */
            circle.around {
                fill: #fff;
                stroke-width: 2px;
                stroke: #000;
            }

            /** Text above circle */
            text.center {
                font-weight: bold;
                text-anchor: middle;   
            }

            text.shadow {
                stroke: #fff;
                stroke-width: 2px;
                stroke-opacity: .8;
            }
            
            /** Overlay */
            #overlay {
                pointer-events: none;
            }
            .semi-transparant {
                fill: #fff;
                fill-opacity: 0.9;
                stroke: #000;
                stroke-width: 1.5;
                stroke-opacity: 1.0;
            }
        </style>
    </head>

    <body class="popup">
        <table id="visualisation" cellpadding="4" cellspacing="1">
            <tr>
                <!-- SETTINGS -->
                <td height="25px" style="vertical-align: middle;">
                    <!-- COLOR SETTINGS -->
                    <div class="settings space">
                         <b>Colours:</b>
                         <!-- Line color -->
                         <i>Lines</i>
                         <div id="linecolor" class="color"></div>
                         
                         <!-- Arrow color -->
                         <i>Arrows</i>
                         <div id="markercolor" class="color"></div>

                          <!-- Record count circle color -->
                          <i>Frequency</i>
                          <div id="countcolor" class="color"></div>
                            
                          <!-- Text color -->
                          <i>Text</i>
                          <div id="textcolor" class="color"></div>
                    </div> 
                    
                    <!-- LINE SETINGS -->           
                    <div class="settings space">
                        <b>Lines:</b>
                        <!-- Line type -->
                        <select class="middle" id="linetype">
                            <option value="straight">straight</option>
                            <option value="curved">curved</option>
                        </select>

                       <!-- Line length --> 
                        <i>Length</i>
                        <input id="linelength" class="small" type="number" min="1" value="200"/>
                        
                        <i>Max width</i>
                        <input id="linewidth" class="small" type="number" min="1" value="10"/>
                    </div>
                    
                    <!-- Entity settings -->
                    <div class="settings space">
                        <b>Entitity:</b>
                        
                        <i>Max radius</i>
                        <input id="entityradius" class="small" type="number" min="1" value="100"/>
                    </div>
                    
                    <!-- Transform settings -->
                    <div class="settings space">
                        <b>Transform:</b>
                        
                        <!-- Formula -->
                        <select class="middle" id="formula">
                            <option value="linear">linear</option>
                            <option value="logarithmic">logarithmic</option>
                            <option value="unweighted">unweighted</option>
                        </select>
                    </div>
                    
                    <!-- GRAVITY SETTINGS -->
                    <div class="settings space">
                        <b>Gravity:</b>
                        <!-- Gravity setting -->
                        <select class="middle" id="gravity">
                            <option value="off">off</option>
                            <option value="touch">touch</option>
                            <option value="aggressive">aggressive</option>
                        </select>
                        
                         <!-- Attraction strength -->
                        <i>Attraction</i>
                        <input id="attraction" class="small" type="number" value="-700"/>
                    </div>
                    
                    <div class="settings space">
                        <b>Fisheye:</b>
                        <input id="fisheye" type="checkbox" name="fisheye">
                    </div>

                    <!-- HELP INFO -->
                    <div class="settings space">
                        <i style="color: #444; font-size: 8px">Click entities or links to get information, drag entities to reposition</i>
                    </div>
                 </td> 
            </tr>

            <tr>
                <td>
                    <!-- SVG -->
                    <svg></svg>
                </td>
            </tr>
        </table>

        
        
        <script>
           
            var url = "../admin/describe/getRectypeRelationsAsXML.php" + window.location.search;
            console.log("Loading data from: " + url);
            d3.xml(url, "application/xml", function(error, xml) {
                // Error check
                if(error) {
                    console.log("Error loading data: " + error.message);
                    return alert("Error loading data: " + error.message);
                }
               
                // Data loaded successfully!
                console.log("XML Loaded!");
                console.log(xml);
                
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
                    return parseInt(getValue(record, "rec_ID").textContent);               
                }

                /** Returns the name value of a record */
                function getName(record) {
                    return getValue(record, "rec_Name").textContent;                
                }

                /** Returns the count value of a record */
                function getCount(record) {
                    return parseInt(getValue(record, "rec_Count").textContent);                
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

                /** Converts the raw XML data to D3 usable nodes and links
                 *  Return example
                 * 
                 * Returns an Object:
                 * Object {nodes: Object, links: Array[19]}
                    * Contains a "nodes" attribute, this is an Object containing more Objects
                    * Contains a "links" attribute, this is an array containg Objects
                 * 
                 * nodes: Object [Each attribute is an Object as well]
                 * Death: Object
                     * count: 232
                     * fixed: true
                     * id: 33
                     * image: "http://heur-db-pro-1.ucc.usyd.edu.au/HEURIST_FILESTORE/BoRO_Backup_13Aug14/rectype-icons/33.png"
                     * index: 6
                     * name: "Death"
                     * px: 110.60620663669448
                     * py: 87.9311955887024
                     * type: "usagerecord"
                     * weight: 4
                     * x: 110.60620663669448
                     * y: 87.9311955887024
                     * __proto__: Object
                 * Digital media item: Object
                 * Educational institution: Object
                 * Eventlet: Object
                 * Military award: Object
                 * etc.
                 * 
                 * 
                 * links: Array[19]
                 * 0: Object
                     * pointer: "BOR entry pdf"
                     * source: Object
                         * count: 2575
                         * fixed: true
                         * id: 10
                         * image: "http://heur-db-pro-1.ucc.usyd.edu.au/HEURIST_FILESTORE/BoRO_Backup_13Aug14/rectype-icons/10.png"
                         * index: 1
                         * name: "Person"
                         * px: 388.425870681868
                         * py: 76.42969215610094
                         * type: "rootrecord"
                         * weight: 13
                         * x: 388.425870681868
                         * y: 76.42969215610094
                         * __proto__: Object
                     * target: Object
                         * count: 4718
                         * fixed: true
                         * id: 5
                         * image: "http://heur-db-pro-1.ucc.usyd.edu.au/HEURIST_FILESTORE/BoRO_Backup_13Aug14/rectype-icons/5.png"
                         * index: 0
                         * name: "Digital media item"
                         * px: 89.0722588084383
                         * py: 201.8612925141735
                         * type: "rootrecord"
                         * weight: 2
                         * x: 89.0722588084383
                         * y: 201.8612925141735
                         * __proto__: Object
                     * __proto__: Object
                 * 1: Object
                 * 2: Object
                 * 3: Object
                 * etc.
                 * 
                 */
                 function getFilter() {
                     return [];
                 }
                var maxCount = 1;
                function getData(xml) {
                    maxCount = 1;
                    
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
                        if(index == -1/* && rootInfo.count > 0*/) {
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
                                            if(index == -1/* && usageInfo.count > 0*/) {
                                                // Construct a link; add root record info
                                                var link = {};
                                                if(!(rootInfo.name in nodes)) {
                                                    nodes[rootInfo.name] = rootInfo;
                                                    link["source"] = rootInfo;
                                                    
                                                     // Count check
                                                    if(rootInfo.count > maxCount) {
                                                        maxCount = rootInfo.count;                                                                     
                                                    }
                                                }else{
                                                    link["source"] = nodes[rootInfo.name];
                                                }

                                                // Link construction; add usage record info
                                                if(!(usageInfo.name in nodes)) { // Check if a node with this name has been added already 
                                                    nodes[usageInfo.name] = usageInfo; // It has not; add it to the list of nodes
                                                    link["target"] = usageInfo;    // Set the target of the root link to this reoord
                                                    
                                                     // Count check
                                                    if(usageInfo.count > maxCount) {
                                                        maxCount = usageInfo.count;
                                                    }
                                                }else{ // Node with this name exists already, use that record 
                                                    link["target"] = nodes[usageInfo.name]; 
                                                }

                                                // Add link to array
                                                link["relation"] = relationInfo;
                                                links.push(link);
                                            }else{
                                                // Display root node anyways
                                                if(!(rootInfo.name in nodes)) {
                                                    nodes[rootInfo.name] = rootInfo;
                                                    
                                                    // Count check
                                                    if(rootInfo.count > maxCount) {
                                                        maxCount = rootInfo.count;
                                                    }
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
                
                console.log("Calling plugin!");
                $("svg").visualize({
                    color: "Orange",
                    data: xml,
                    getData: function(data) { return getData(data); }
                });   
            });
            
            
             
        </script>
    </body>

</html>