<?php
    /**
    * springDiagram.php: Renders multiple levels of search results 
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
?>

<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Spring Diagram</title>

        <!-- Css -->
        <link rel="stylesheet" type="text/css" href="../../../../../common/css/global.css">
        <style>
            body, html {
                background-color: #fff;
            }
        </style>
        
         <!-- jQuery -->
        <script type="text/javascript" src="../../../ext/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
        
        <!-- D3 -->
        <script type="text/javascript" src="../../../ext/d3/d3.js"></script>
        <script type="text/javascript" src="../../../ext/d3/fisheye.js"></script>
        
        <!-- Colpick -->
        <script type="text/javascript" src="../../../ext/colpick/colpick.js"></script>
        <link rel="stylesheet" type="text/css" href="../../../ext/colpick/colpick.css">
        
        <!-- Visualize plugin --> 
        <script type="text/javascript" src="../visualize.js"></script>
        <link rel="stylesheet" type="text/css" href="../visualize.css">   
        
        <!-- Script to parse data and create the visualisation -->
        <script type="text/javascript" src="springDiagram.js"></script>                         
    </head>
    
    <body>
        <!-- Visualize HTML -->
        <?php include "../visualize.html"; ?>
        
        <!-- Visualize data -->
        <script>
            $(document).ready(function() { 
            try {    
                // Get parameters
                var parameters = {};
                location.search.substr(1).split("&").forEach(function(item) {parameters[item.split("=")[0]] = decodeURIComponent(item.split("=")[1])});
                
                // Check if the "data" parameter exists
                if(parameters !== undefined && parameters.hasOwnProperty("ids")) { 
                    // Used as embed, use window.location parameters
                    console.log("Parsing ids from parameters");
                    var ids = parameters.ids;
                    console.log("ID's: " + ids);
                    
                    // Construct data from ID's..
                    alert("Passing ID's in URL is still in development...");
                      
                }else{ 
                     // Used inside search results, use top.HEURIST.search
                    console.log("Parsing recSet from top.HEURIST.search");
                    var data = parseRecSet();  // Parse the Javascript data
                    visualize(data);           // Visualize the data
                }
   
            } catch(error) {
                $("body").append("<h3>Error occured</h3><br /><i>" + error.message + "</i>");        
            }
 
        }); 
        </script>
    </body>
    
</html>