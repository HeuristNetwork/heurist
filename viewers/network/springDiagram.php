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
                overflow: scroll;
                
            }
        </style>
        
        <script type="text/javascript" src="../../external/jquery/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
    </head>
    
    <body>
        <?php echo "Spring diagram"; ?>
        <br /><br />
        
        <?php echo "Database" . DATABASE ?>
        <br /><br />
        
        <?php echo $_SERVER['QUERY_STRING']; ?>
        <br /><br />
        
        <?php echo "Current time: " . time(); ?>
        <br /><br />
        
        <script>
            $(document).ready(function() {
                $("body").append("<h2>RESULTS</h2>");
                try {
                    var results = top.HEURIST.search.results;
                    if(results) {
                        console.log(results);  
                        
                        for(var key in results.recSet) {
                            // Display ID and depth
                            var depth = results.recSet[key].depth;
                            var html = "<div><h3>Key: " + key + ", depth: " + depth + "</h3>";
                            
                             // Loop through all properties of the record
                            var record = results.recSet[key].record;
                            for(var id in record) {
                                html += "<br />ID: " + id + ", value: " + record[id];
                            }
                            
                            html += "</div>";
                            $("body").append(html);           
                            
                        }
                    }   
                } catch(error) {
                    $("body").append("<h3>Error occured</h3><br /><i>" + error.message + "</i>");        
                }
            });
                                       
              
        </script>
    </body>
    
</html>