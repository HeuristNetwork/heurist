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
            $(document).ready(function() {
                try {    
                    var results = top.HEURIST.search.results;
                    if(results) {
                        console.log(results);
                        console.log(results.recSet);  
                        
                        var data = {};
                        var nodes = [];
                        var links = [];
 
                        
                        // Building nodes
                        for(var id in results.recSet) {
                            // Get details
                            var record = results.recSet[id].record;
                            var depth = results.recSet[id].depth;
                            var name = record["5"];
                            var count = 1;
                            
                            // Construct node
                            var node = {id: id, name: name, count: count, depth: depth};
                            console.log(node);
                            nodes.push(node);        
                        }
                        
                        // Building links
                        /*
                        for(var id in results.recSet) {
                            console.log("RESULT SET");

                            var ptr = results.recSet[id].revPtrLinks;
                            console.log("PTR");
                            console.log(ptr);
                            if(ptr !== undefined) {
                                var ids = ptr.byRecIDs;
                                if(ids.lenth > 0) {
                                    for(var i = 0; i < ids.length; i++) {
                                        var link = {source: id, target: ids[i]};
                                        console.log("LINK");
                                        console.log(link);
                                    }
                                }
                            }
                            
                            var rev = results.recSet[id].revRelLinks;
                            console.log(rev);   
                        }
                        */
                        
                        // Time to visualize
                        console.log(nodes);
                        var data = {nodes: nodes, links: links};
                        
                        // Parses the data
                        function getData(data) {
                            console.log("GET DATA");
                            return data;
                        }
                        
                        // Call plugin
                        console.log("Calling plugin!");
                        $("#visualisation").visualize({
                            data: data,
                            getData: function(data) { return getData(data); }
                        });  
                      
                    }   
                } catch(error) {
                    $("body").append("<h3>Error occured</h3><br /><i>" + error.message + "</i>");        
                }
            });
                                       
              
        </script>
    </body>
    
</html>