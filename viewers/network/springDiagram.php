<?php
    /**
    * sprintDiagram.php: Renders multiple levels of search results 
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
    * @author      Jan Jaap de Groot    <jjedegroot@gmail.com>
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

    require_once (dirname(__FILE__) . '/../../common/connect/applyCredentials.php');
    require_once (dirname(__FILE__) . '/../../common/php/getRecordInfoLibrary.php');

    //mysql_connection_select(DATABASE);
?>

<html>

    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Spring Diagram</title>

        <link rel="stylesheet" type="text/css" href="../../common/css/global.css">
        <style>
            html, body {
                background-color: #fff;
                overflow-x: scroll;
            }
        </style>
        
         <!-- jQuery -->
        <script type="text/javascript" src="../../external/jquery/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
        
        <!-- D3 -->
        <script type="text/javascript" src="../../external/d3/d3.js"></script>
        <script type="text/javascript" src="../../external/d3/fisheye.js"></script>
        
        <!-- Colpick -->
        <script type="text/javascript" src="../../external/colpick/colpick.js"></script>
        <link rel="stylesheet" type="text/css" href="../../external/colpick/colpick.css">
        
        <!-- Visualize plugin --> 
        <script type="text/javascript" src="../../common/js/visualize.js"></script>
        <link rel="stylesheet" type="text/css" href="../../common/css/visualize.css">                            
    </head>
    
    <body>
        <!-- Visualize HTML -->
        <?php include "../../common/html/visualize.html"; ?>
        
        <script>
            /**
            * Explaning top.HEURIST.search.results
            * This is the object itself:
            * 
            * Object {recSet: Object, infoByDepth: Array[3], recSetCount: 227, totalQueryResultRecordCount: "203", querySid: ""…}
            * infoByDepth: Array[3]
            * params: Object
            * querySid: ""
            * recSet: Object
            * recSetCount: 227    
            * totalQueryResultRecordCount: "203"
            * __proto__: Object
            * 
            * 
            * springDiagram.php uses the recSet object:
            * recSet: Object
            * 1: Object
            * 2: Object
            * 5: Object
            * 
            * We then loop through all the recSet keys.
            * 
            * Details of an object inside the recSet:
            * 1: Object
            * depth: 0
            * record: Array[12]
            * relLinks: Object
            * revPtrLinks: Object
            * revRelLinks: Object
            * __proto__: Object
            * 
            * We use the record array to construct nodes.
            * 
            * We use the revPtrLinks to construct the links. Details:
            * 
            * revPtrLinks: Object
            * byInvDtlType: Object
            * byRecIDs: Object
            * __proto__: Object
            * 
            * We use the byRecIDs object. Details:
            * byRecIDs: Object
            * 10: Array[1]
            * 48: Array[1] 
            * 
            * We then loop through all the byRecIDs keys.
            * 10: Array[1]
            * 0: "5"
            * length: 1
            * 
            * This data is used to construct the links.
            * 
            */
            $(document).ready(function() {
                try {    
                    // Determinating selected ID's
                    var selectedIDs = [];
                    var recIDs = top.HEURIST.search.getSelectedRecIDs(); 
                    if(recIDs) {
                        for(var key in recIDs) {
                            if(!isNaN(key)) {
                                selectedIDs.push(recIDs[key]);       
                            }
                        }
                    }
                    console.log("SELECTED IDs");
                    console.log(selectedIDs);
 
                    // Results
                    var results = top.HEURIST.search.results;
                    if(results) {
                        console.log("RESULTS");
                        console.log(results);      

                        var nodes = {};
                        var links = [];
  
                        // Building nodes
                        for(var id in results.recSet) {
                            // Get details
                            var record = results.recSet[id].record;
                            var depth = results.recSet[id].depth;
                            var name = record["5"];  
                            var group = record["4"];
                            var image = top.HEURIST.iconBaseURL + group + ".png";
                            var selected = selectedIDs.indexOf(id.toString()) > -1;
   
                            // Construct node
                            var node = {id: parseInt(id), name: name, image: image, count: 1, depth: depth, selected: selected};
                            nodes[id] = node;    
                            console.log("Node #" + id);    
                        }
                        
                        /**
                        * Finds links in a revPtrLinks or revRelLinks object
                        */
                        function findLinks(source, object) {
                            var recIDs = object.byRecIDs;
                            for(var recID in recIDs) {
                                console.log("ID " +id+ " points to recID: " + recID);
                                var target = nodes[recID];
                                if(target !== undefined) {
                                    var ids = recIDs[recID];
                                    console.log("RELATION ID's");
                                    console.log(ids);
                                    if(ids !== undefined && ids.length > 0) {
                                        for(var i = 0; i < ids.length; i++) {
                                            // Define relation    
                                            console.log("Relation #" + i + " ID: " + ids[i]);        
                                            var relation = nodes[ids[i]];
                                            if(relation === undefined) {
                                                relation = {id: ids[i], name: "Unknown", image: "unknown.png", count: 1};
                                            }
                                            
                                            // Construct a link
                                            var link = {source: source, relation: relation, target: target};
                                            console.log("LINK");
                                            console.log(link);   
                                            links.push(link);  
                                        } 
                                    }  
                                }
                            }
                        }

                        // Go through all records
                        for(var id in results.recSet) {
                            console.log("RecSet["+id+"]:");
                            console.log(results.recSet[id]);
                            var source = nodes[id];
                            
                            // Determine links
                            if(source !== undefined) {
                                 // Get ptrLinks
                                var ptr = results.recSet[id].ptrLinks;
                                console.log("Ptr for recSet["+id+"]");
                                console.log(ptr);
                                if(source !== undefined && ptr !== undefined) {
                                    //var recIDs = ptr.byRecIDs;
                                    findLinks(source, ptr);
                                }
         
                                // Get relLinks
                                var rel = results.recSet[id].relLinks;
                                console.log("Rel for recSet["+id+"]");
                                console.log(ptr);
                                if(rel !== undefined) {
                                    findLinks(source, rel);
                                }
                            }
                        }
   
                        // Time to visualize
                        var data = {nodes: nodes, links: links};
                        console.log("DATA");
                        console.log(data);

                        // Parses the data
                        function getData(data) {
                            console.log("GET DATA");
                            return data;
                        }
                        
                        // Calculates the line length
                        function getLineLength(record) {
                            var length = getSetting(setting_linelength);
                            if(record !== undefined && record.hasOwnProperty("depth")) {
                                length = length / (record.depth+1);
                            }
                            return length;
                        }
                        
                        // Call plugin
                        console.log("Calling plugin!");
                        $("#visualisation").visualize({
                            data: data,
                            getData: function(data) { return getData(data); },
                            getLineLength: function(record) { return getLineLength(record); },
                            
                            showEntitySettings: false,
                            showLineWidth: false,
                            showFormula: false
                        });  
                      
                    }   
                } catch(error) {
                    $("body").append("<h3>Error occured</h3><br /><i>" + error.message + "</i>");        
                }
            });
  
        </script>

    </body>
    
</html>