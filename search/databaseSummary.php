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

    // Building query
    $query = REQUEST_to_query("select rec_RecTypeID, count(*) as count ", $searchType, $args, null, $publicOnly);
    $query = substr($query, 0, strpos($query, "order by"));
    $query .= " group by rec_RecTypeID order by count DESC";

    $rtStructs = getAllRectypeStructures();
?>

<html>

    <head>

        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Database Summary</title>

        <link rel="stylesheet" type="text/css" href="../common/css/global.css">
        <link rel="stylesheet" type="text/css" href="../external/d3/colpick.css">
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
            #records {
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
            
            #records td, #records th {
                border: 1px solid black;
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
                float: left;
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
                max-width: 45px;
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
                cursor: move;
            }

            /** Lines between records */
            .link {
                fill: none;    
                stroke-opacity: .6;
                pointer-events: all;
            }

            .link:hover {
                cursor: help;
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
                font: 10px sans-serif;
            }

            text.shadow {
                stroke: #fff;
                stroke-width: 3px;
                stroke-opacity: .8;
            }
            
            /** Overlay */
            #overlay {
                pointer-events: none;
            }
            .semi-transparant {
                fill: #fff;
                fill-opacity: 0.8;
                stroke: #000;
                stroke-width: 3;
                stroke-opacity: 1.0;
            }
        </style>

        <script type="text/javascript" src="../external/jquery/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
        <script type="text/javascript" src="../external/d3/d3.js"></script>
        <script type="text/javascript" src="../external/d3/colpick.js"></script>
        <script type="text/javascript" src="../external/d3/fisheye.js"></script>
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
        <!-- Holds the record count table left (as small as possible), and the SVG visualisation on the right (as big as possible) -->

        <table id="container" width="100%" border="0" cellspacing="0" cellpadding="2">
            <tr>
                <td width="350">
                    <!-- Record count table -->
                    <!-- also provides navigation to search for a record type and on/off controls for record types in visualisation -->
                    <h3> Record types (entities)</h3><br />
                    <table id="records" cellpadding="4" cellspacing="1">

                        <tr>
                            <th width="40">ID</th>
                            <th class="space">Icon</th>
                            <th class="space" width="200">Record&nbsp;type</th>
                            <th class="space">Link</th>
                            <th class="space">Count</th>
                            <th class="space">Show <input type='checkbox' id="show-all"></th>
                        </tr>

                        <?php
                            // Put record types & counts in the table
                            $res = mysql_query($query);
                            $count = 0;
                            while ($row = mysql_fetch_row($res)) { // each loop is a complete table row
                                // ID
                                $rt_ID = $row[0];
                                echo "<tr class='row'>";
                                echo "<td align='center'>$rt_ID</td>";

                                // Image
                                $rectypeImg = "style='background-image:url(".HEURIST_ICON_SITE_PATH.$rt_ID.".png)'";
                                $img = "<img src='../common/images/16x16.gif' title='".htmlspecialchars($rectypeTitle). "' ".$rectypeImg." class='rft' />";
                                echo "<td align='center'>$img</td>";

                                // Title
                                $rectypeTitle = $rtStructs['names'][$rt_ID];
                                $title = htmlspecialchars($rectypeTitle);
                                echo "<td style='padding-left: 5px; padding-right: 5px'>".$title."</td>";

                                // Links
                                $links =  "<a href='#' title='Open search for this record type in current page'".
                                "onclick='onrowclick($rt_ID, false)' class='internal-link'>&nbsp;</a>";
                                $links .= "<a href='#' title='Open search for this record type in new page'".
                                "onclick='onrowclick($rt_ID, true)' class='external-link'>&nbsp;</a>";
                                echo "<td>$links</td>";

                                // Count
                                echo "<td align='center'>" .$row[1]. "</td>";

                                // Show
                                if($count < 10) {
                                    echo "<td align='center'><input type='checkbox' class='show-record' name='" .$title. "' checked></td>";
                                }else{
                                    echo "<td align='center'><input type='checkbox' class='show-record' name='" .$title. "'></td>";
                                }
                                echo "</tr>";
                                $count++;

                            }//end while
                        ?>

                    </table>

                </td>

                <!-- D3 visualisation -->
                <td>
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
                                    
                                    <!-- Line thickness -->
                                    <i>Thickness</i>
                                    <select class="middle" id="linethickness">
                                        <option value="linear">linear</option>
                                        <option value="naturallog">natural log</option>
                                        <option value="logbase10">log base 10</option>
                                    </select>

                                   <!-- Line length --> 
                                    <i>Length</i>
                                    <input id="linelength" class="small" type="number" min="1" value="200"/>
                                </div>
                                
                                <!-- GRAVITY SETTINGS -->
                                <div class="settings space">
                                    <b>Gravity:</b>
                                    <!-- Gravity setting -->
                                    <select class="middle" id="gravity">
                                        <option value="on">on</option>
                                        <option value="touch">touch</option>
                                        <option value="off">off</option>
                                    </select>
                                    
                                     <!-- Attraction strength -->
                                    <i>Attraction</i>
                                    <input id="attraction" class="small" type="number" value="-700"/>
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

                </td>
            </tr>
        </table>

        <script type="text/javascript" src="databaseSummary.js"></script>

    </body>

</html>