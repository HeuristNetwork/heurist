<?php

/*
* Copyright (C) 2005-2013 University of Sydney
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
* databaseSummary.php
* request aggregation query for all records grouped by record type 
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Jan Jaap de Groot    <jjedegroot@gmail.com>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
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
        <style>
            /** Heurist table */
            #container {
                width: 100%;
                height: 100%;
                overflow: scroll;
            }
            
            table.tbcount {    
                table-layout: auto;
 
                border-width: 0 0 1px 1px;
                border-spacing: 0;
                border-collapse: collapse;
                border-style: solid;
            }
            
            td, th {
                vertical-align: top;
                margin: 0px; 
                padding: 2px; 
            }
            
            .tbcount td, .tbcount th {
                border-width: 1px 1px 0px 0px; 
                border-style: solid;  
            }

            .row:hover {
                background-color: #CCCCCC;
            } 

            a:hover, input:hover {
                text-decoration: none;
                cursor: pointer;
            }

            /** D3 */
            .svg-cell {
                border-left: 1px dotted black;
            }
            
            svg {
                width: 100%;
                height: 90%;   
            }
            
            /** Move records around */
            g:hover {
                cursor: move;
            }
            
            /** Lines between records */
            .link {
              fill: none;
              stroke: #999;   
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
            text {
                font-weight: bold;
                text-anchor: middle;
                fill: #a00;
                font: 10px sans-serif;
            }
            
            text.shadow {
                 stroke: #fff;
                 stroke-width: 3px;
                 stroke-opacity: .8;
            }
            
            /** Settings */
            #settings {
                width: 100%;
                height: 10%;
                border-top: 1px dotted black;
                padding: 3px;
            }
        </style>
        
        <script type="text/javascript" src="../external/jquery/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
        <script type="text/javascript" src="../external/d3/d3.js"></script> 
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
                <td width="25%">
                    <!-- Record count table -->
                    <table class="tbcount" cellpadding="4" cellspacing="1">
                         <tr>
                            <th>ID</th>
                            <th>Icon</th>
                            <th style="max-width: 100px">Record type</th>
                            <th>Link</th>
                            <th>Count</th>
                            <th>Show <input type='checkbox' id="show-all"></th>
                        </tr>
                        
                        <?php
                            // Put record types & counts in the table
                            $res = mysql_query($query);
                            $count = 0;
                            while ($row = mysql_fetch_row($res)) {
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
                                echo "<td>".$title."</td>";
                                
                                // Links
                                $links =  "<a href='#' title='Open in current page' onclick='onrowclick($rt_ID, false)' class='internal-link'>&nbsp;</a>";
                                $links .= "<a href='#' title='Open in new page' onclick='onrowclick($rt_ID, true)' class='external-link'>&nbsp;</a>";
                                echo "<td>$links</td>";
                                
                                // Count
                                echo "<td align='center'>" .$row[1]. "</td>";
                                
                                // Show
                                if($count < 10) {
                                    echo "<td align='center'><input type='checkbox' class='show-record' name='" .$title. "' checked></td></tr>";
                                }else{
                                    echo "<td align='center'><input type='checkbox' class='show-record' name='" .$title. "'></td></tr>";
                                }
                                $count++;
                                
                            }//end while
                        ?>
                    </table>
                </td>

                <!-- D3 visualisation -->
                <td class="svg-cell">
                    <svg>    
                        <defs>
                            <marker id="marker" markerWidth="8" markerHeight="8" refx="10" refy="0"
                                    viewBox="0 -5 10 10" markerUnits="userSpaceOnUse" orient="auto">
                                <path d="M0,-5L10,0L0,5"></path>
                            </marker> 
                        </defs>    
                    </svg>
                    
                    <div id="settings">
                        <b>Settings</b>
                        
                        <!-- Line type -->
                        <div class="setting">
                            <i>Line type:</i>
                            <select id="linetype">
                                <option value="straight">straight</option>
                                <option value="curved">curved</option>
                            </select>
                        </div>
                        
                        

                    </div>
                </td>
            </tr>
        </table>
        <script type="text/javascript" src="databaseSummary.js"></script>
    </body>

</html>