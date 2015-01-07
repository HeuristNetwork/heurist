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
    
    require_once(dirname(__FILE__).'/../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../common/php/dbMySqlWrappers.php');
    require_once(dirname(__FILE__).'/parseQueryToSQL.php');
    require_once(dirname(__FILE__).'/../common/php/getRecordInfoLibrary.php');

    mysql_connection_select(DATABASE);

    $searchType = BOTH;
    $args = array();
    $publicOnly = false;
?>

<html>

    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Database Summary</title>
        
        <!-- Heurist -->
        <link rel="stylesheet" type="text/css" href="../common/css/global.css">
        <style>       
            #container {
                height: 100%;
            }
            
            table {
                table-layout: auto;
                border-color: black;   
                border-collapse: collapse;       
            }
            
            td, th {
                vertical-align: top;
                margin: 0px;
                padding: 2px 1px;
            }
            
            .show {
                display: none;
            }
            
            .empty-row {
                border: none !important;
                padding:8px;
                border-spacing: 0px;
            }
            
            .records td, .records th {
                border: 1px solid black;
            }
            
            .count-divider {
                border-top: 3px solid black;
            }

            .row:hover {
                background-color: #CCCCCC;
            }

            a:hover, input:hover {
                text-decoration: none;
                cursor: pointer;
            }
            
            #visualisation {
                width: 100%;
                height: 100%;
                border-left: 1px dashed black;
            }
            
            #table-header {
                margin-top: 10px;
                margin-bottom: 5px;
                margin-right: 0px;
                margin-left: 5px;
                padding: 0px;
                font-size: 14px !important;
            }
            
            #expand {
               float: right;
               margin-right: 5px; 
            }
        </style>
        
        <!-- jQuery -->
        <script type="text/javascript" src="../external/jquery/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
        
        
        <!-- D3 -->
        <script type="text/javascript" src="../external/d3/d3.js"></script>
        <script type="text/javascript" src="../external/d3/fisheye.js"></script>
        
        <!-- Colpick -->
        <script type="text/javascript" src="../external/colpick/colpick.js"></script>
        <link rel="stylesheet" type="text/css" href="../external/colpick/colpick.css">
        
        <!-- Visualize plugin --> 
        <script type="text/javascript" src="../applications/h4/page/visualize/visualize.js"></script>
        <link rel="stylesheet" type="text/css" href="../applications/h4/page/visualize/visualize.css">
        
        <!-- On Row Click -->
        <script>
            function onrowclick(rt_ID, innewtab){
                var query = "?w=all&ver=1&db=<?=HEURIST_DBNAME?>&q=t:"+rt_ID;
                if(innewtab){
                    window.open("search.html?"+query, "_blank");
                    return false;
                }else{
                    top.HEURIST.search.executeQuery(query);
                    window.close();
                }
            }
        </script>
    </head>

    <body class="popup">
        <table id="container" border="0" cellspacing="0" cellpadding="2"  align="center">
            <tr>
                <td id="visualisation-details" style="width: 350px">
                    <!-- Record count table -->
                    <!-- also provides navigation to search for a record type and on/off controls for record types in visualisation -->
                    <div>
                        <h3 id="table-header">Record types (entities)</h3>
                        <button id="expand">Expand &#10142;</button>
                    </div>
                    <table id="records" class="records" cellpadding="4" cellspacing="1">

                        <tr>
                            <th width="40">ID</th>
                            <th class="space">Icon</th>
                            <th class="space" width="200">Record&nbsp;type</th>
                            <th class="space">Link</th>
                            <th class="space">Count</th>
                            <th class="space show">Show <input type='checkbox' id="show-all"></th>
                        </tr>

                        <?php
                            /** RETRIEVING RECORDS WITH CONNECTIONS */
                            // Building query
                            $query = "SELECT r.rec_RecTypeID as id, d.rty_Name as title, count(*) as count FROM Records r INNER JOIN defRecTypes d ON r.rec_RectypeID=d.rty_ID GROUP BY id ORDER BY count DESC, title ASC;";
                            // Put record types & counts in the table
                            $res = mysql_query($query);
                            $count = 0;
                            while ($row = mysql_fetch_row($res)) { // each loop is a complete table row
                                $rt_ID = $row[0];
                                $title = htmlspecialchars($row[1]);
                                
                                // ID
                                echo "<tr class='row'>";
                                echo "<td align='center'>$rt_ID</td>";

                                // Image
                                $rectypeImg = "style='background-image:url(".HEURIST_ICON_URL.$rt_ID.".png)'";
                                $img = "<img src='../common/images/16x16.gif' title='".$title. "' ".$rectypeImg." class='rft' />";
                                echo "<td align='center'>$img</td>";

                                // Type
                                echo "<td style='padding-left: 5px; padding-right: 5px'>"
                                     ."<a href='#' title='Open search for this record type in current page' onclick='onrowclick($rt_ID, false)' class='dotted-link'>"
                                     .$title.
                                     "</a></td>";

                                // Link
                                echo "<td align='center'><a href='#' title='Open search for this record type in new page' onclick='onrowclick($rt_ID, true)' class='external-link'>&nbsp;</a></td>";

                                // Count
                                echo "<td align='center'>" .$row[2]. "</td>";

                                // Show
                                if($count < 10) {
                                    echo "<td align='center' class='show'><input type='checkbox' class='show-record' name='" .$title. "' checked='checked'></td>";
                                }else{
                                    echo "<td align='center' class='show'><input type='checkbox' class='show-record' name='" .$title. "'></td>";
                                }
                                echo "</tr>";
                                $count++;

                            }//end while
                            
                            // Empty space
                            echo "<tr class='empty-row'><td class='empty-row'></tr>";
                            
                            /** RETRIEVING RECORDS WITH NO CONNECTIONS */
                            $query = "SELECT rty_ID as id, rty_Name as title FROM defRecTypes WHERE rty_ID NOT IN (SELECT DISTINCT rec_recTypeID FROM Records) ORDER BY title ASC;";
                            $res = mysql_query($query);
                            $count = 0;
                            while ($row = mysql_fetch_row($res)) { // each loop is a complete table row
                                echo "<tr>";
            
                                // ID
                                $rt_ID = $row[0];
                                echo "<td align='center'>" .$rt_ID. "</td>";
                                
                                // Image  
                                $title = $row[1];
                                $rectypeImg = "style='background-image:url(".HEURIST_ICON_URL.$rt_ID.".png)'";
                                $img = "<img src='../common/images/16x16.gif' title='".htmlspecialchars($rectypeTitle). "' ".$rectypeImg." class='rft' />";
                                echo "<td align='center'>$img</td>";
                                
                                // Type
                                echo "<td style='padding-left: 5px; padding-right: 5px'>"
                                     ."<a href='#' title='Open search for this record type in current page' onclick='onrowclick($rt_ID, false)' class='dotted-link'>"
                                     .$title.
                                     "</a></td>";

                                // Link
                                echo "<td align='center'><a href='#' title='Open search for this record type in new page' onclick='onrowclick($rt_ID, true)' class='external-link'>&nbsp;</a></td>";
                                
                                // Count
                                echo "<td align='center'>0</td>";
                                
                                // Show
                                echo "<td align='center' class='show'><input type='checkbox' class='show-record' name='" .$title. "'></td>";
                                
                                echo "</tr>";
                                $count++;
                            }//endwhile
                        ?>

                    </table>

                </td>

                <!-- D3 visualisation -->
                <td id="visualisation-column" style="display:none">
                    <?php include "../applications/h4/page/visualize/visualize.html"; ?>
                </td>
            </tr>
        </table>

    
        <script>
           $("#expand").click(function(e) {
                // Show visualisation elements
                $(this).remove();
                $(".show").slideToggle(500);
                $("#visualisation-column").slideToggle(500);

                // VISUALISATION CALL
                var url = "../applications/h4/php/api/rectype_relations.php" + window.location.search; // H4 API call
                console.log("Loading data from: " + url);
                d3.json(url, function(error, json) {
                    // Error check
                    if(error) {
                        return alert("Error loading JSON data: " + error.message);
                    }
                   
                    // Data loaded successfully!
                    console.log("JSON Loaded");
                    console.log(json);   
                    
                    /** RECORD FILTERING */
                    // Set filtering settings in UI
                    $(".show-record").each(function() {
                        var name = $(this).attr("name");
                        var record = localStorage.getItem(name);
                        if(record) {
                            // Update checked attribute
                            var obj = JSON.parse(record);
                            if("checked" in obj) {
                                $(this).prop("checked", obj.checked);
                            }
                        }
                    });
                    
                    // Listen to 'show-record' checkbox changes  
                    $(".show-record").change(function(e) {
                        // Update record field 'checked' value in localstorage
                        var name = $(this).attr("name");
                        var record = localStorage.getItem(name);
                        var obj;
                        if(record === null) {
                            obj = {};  
                        }else{                                   
                            obj = JSON.parse(record);
                        }
                        
                        // Set 'checked' attribute and store it
                        obj.checked = $(this).prop('checked');
                        localStorage.setItem(name, JSON.stringify(obj));
                        
                        // Update visualisation
                        visualizeData();
                    });

                    // Listen to the 'show-all' checkbox 
                    $("#show-all").change(function() {
                        // Change all check boxes
                        var checked = $(this).prop('checked');
                        $(".show-record").prop("checked", checked);   
                        
                        // Update localstorage
                        $(".show-record").each(function(e) {
                            var name = $(this).attr("name");
                            var record = localStorage.getItem(name);
                            var obj;
                            if(record === null) {
                                obj = {};  
                            }else{                                   
                                obj = JSON.parse(record);
                            }
                            
                            // Set 'checked' attribute and store it
                            obj.checked = checked;
                            localStorage.setItem(name, JSON.stringify(obj));
                        });
                        
                        visualizeData();
                    });
                    
                    
                    /** VISUALIZING */
                    // Parses the data
                    function getData(data) {
                        // Build name filter
                        var names = [];   
                        $(".show-record").each(function() {
                            var checked = $(this).prop('checked'); 
                            if(checked == false) {
                                var name = $(this).attr("name");
                                names.push(name);
                            }
                        }); 

                        // Filter nodes
                        var map = {};
                        var size = 0;
                        var nodes = data.nodes.filter(function(d, i) {
                            if($.inArray(d.name, names) == -1) {
                                map[i] = d;
                                return true;
                            }
                            return false;
                        });

                        // Filter links
                        var links = [];
                        data.links.filter(function(d) {
                            if(map.hasOwnProperty(d.source) && map.hasOwnProperty(d.target)) {
                                var link = {source: map[d.source], target: map[d.target], relation: d.relation, targetcount: d.targetcount};
                                links.push(link);
                            }
                        })
                    
                        // Return filtered data             
                        return {nodes: nodes, links: links}
                    }
                    
                    // Visualizes the data
                    function visualizeData() {
                        // Call plugin
                        console.log("Calling plugin!");
                        $("#visualisation").visualize({
                            data: json,
                            getData: function(data) { return getData(data); },
                            linelength: 200
                        });   
                    }

                    visualizeData();
      
                });
           });
      
        </script>
    </body>

</html>