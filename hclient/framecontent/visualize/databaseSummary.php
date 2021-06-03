<?php

/**
* databaseSummary.php : displays table of record types and counts and SVG entity connections schema scaled with counts (built with D3.js)
* based on an aggregation query for all records grouped by record type
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Jan Jaap de Groot <jjedegroot@gmail.com>  SVG schema
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

if(!defined('PDIR')) define('PDIR','../../../');
require_once(dirname(__FILE__)."/../initPage.php");
?>
        <style>
        
A:visited {
    color: #6A7C99;
    text-decoration: none;
}
A:link {
    color: #6A7C99;
    text-decoration: none;
}        

            #rectypes {
                height: 100%;
            }

            table {
                font-size: 11px;
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
                /*display: none;*/
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
            
            #list_rectypes{
                background: white;
                z-index: 9999;
                top: 5em !important;                
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
                display:none;
            }
        </style>

        <!-- D3 -->
        <script type="text/javascript" src="<?php echo PDIR;?>external/d3/d3.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/d3/fisheye.js"></script>

        <!-- Colpick -->
        <script type="text/javascript" src="<?php echo PDIR;?>external/js/evol.colorpicker.js" charset="utf-8"></script>
        <link href="<?php echo PDIR;?>external/js/evol.colorpicker.css" rel="stylesheet" type="text/css">

        <!-- Visualize plugin -->
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/visualize/settings.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/visualize/overlay.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/visualize/selection.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/visualize/gephi.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/visualize/drag.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/visualize/visualize.js"></script>

        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>hclient/framecontent/visualize/visualize.css">

        <!-- On Row Click -->
        <script>
            function onrowclick(rt_ID, innewtab){
                var query = "w=all&ver=1&db=<?=HEURIST_DBNAME?>&q=t:"+rt_ID;
                if(innewtab){
                    window.open(window.hWin.HAPI4.baseURL+"?"+query, "_blank");
                    return false;
                }else{
       
                    var request = {source: 'dbsummary', w:'a',
                                        q:  't:'+rt_ID};
                    if(window.hWin.HAPI4.sysinfo['layout']=='H4Default'){
                        window.hWin.HAPI4.LayoutMgr.putAppOnTopById('FAP');
                    }
                    window.hWin.HAPI4.SearchMgr.doSearch( $(window.hWin.document), request );                    
                    
                    if(window.hWin.HAPI4.sysinfo['layout']!='H4Default'){
                        window.close();    
                    }
                    
                    return false;
                }
            }
           
           function onPageInit(success){
                   if(!success) return;
                   $("#expand").click();
            }
            
        </script>
        
    </head>

    <body class="popup" style="background-color: #FFF;">
    
        <div class="ent_wrapper">
                <div id="list_rectypes" class="ent_wrapper" style="width:350px;display:none">
                    <div class="ent_header" style="display:none">
                        <h3 id="table-header">Record types (entities)</h3>
                        <button id="expand">Expand &#10142;</button>
                    </div>
                    <div class="ent_content_full" style="padding-left:10px; top:0">
    
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
                        $query = "SELECT d.rty_ID as id, rg.rtg_Name grp, d.rty_Name as title, count(r.rec_ID) as count 
FROM defRecTypes d LEFT OUTER JOIN Records r ON r.rec_RectypeID=d.rty_ID,
defRecTypeGroups rg where rg.rtg_ID=d.rty_RecTypeGroupID 
 GROUP BY id ORDER BY rtg_Order, title ASC";
                        // Put record types & counts in the table
                        $res = $system->get_mysqli()->query($query);
                        $count = 0; 
                        $grp_name = null;
                        $first_grp  = 'first_grp';
                        
                        while($row = $res->fetch_assoc()) { // each loop is a complete table row
                            $rt_ID = $row["id"];
                            $title = htmlspecialchars($row["title"]);
                        
                            if($grp_name!=$row['grp']){
                                if($grp_name!=null) $first_grp = '';
                                $grp_name = $row['grp'];
                                ?>
                        <tr class="row">
                            <td colspan="6" style="padding-left:10px"><h2><?php echo htmlspecialchars($row["grp"]);?></h2></td>
                        </tr>
                                <?php
                            }
                                
                            // ID
                            echo "<tr class='row'>";
                            echo "<td align='center'>$rt_ID</td>";

                            //HAPI4.iconBaseURL
                            // Image
                            $rectypeImg = "style='background-image:url(".HEURIST_ICON_SCRIPT.$rt_ID.")'";
                            $img = "<img src='".PDIR."hclient/assets/16x16.gif' title='".$title. "' ".$rectypeImg." class='rft' />";
                            echo "<td align='center'>$img</td>";

                            // Type
                            echo "<td style='padding-left: 5px; padding-right: 5px'>"
                            ."<a href='#' title='Open search for this record type in current page' onclick='onrowclick($rt_ID, false)' class='dotted-link'>"
                            .$title.
                            "</a></td>";

                            // Link
                            echo "<td align='center'><a href='#' title='Open search for this record type in new page' onclick='onrowclick($rt_ID, true)' class='external-link'>&nbsp;</a></td>";

                            // Count
                            echo "<td align='center'>" .$row["count"]. "</td>";

                            // Show
                            if($row["count"]>0 && $count < 10) {  //this record type has records
                                echo "<td align='center' class='show'><input type='checkbox' class='show-record' name='" .$title. "' checked='checked'></td>";
                                $count++;
                            }else{
                                echo "<td align='center' class='show'><input type='checkbox' class='show-record $first_grp' name='" .$title. "'></td>";
                            }
                            echo "</tr>";
                        }                        
                        
/* OLD JJ code
                        // Building query
                        $query = "SELECT r.rec_RecTypeID as id, d.rty_Name as title, count(*) as count FROM Records r INNER JOIN defRecTypes d ON r.rec_RectypeID=d.rty_ID GROUP BY id ORDER BY count DESC, title ASC;";

                        
                        // Put record types & counts in the table
                        $res = $system->get_mysqli()->query($query);
                        $count = 0;
                        while($row = $res->fetch_assoc()) { // each loop is a complete table row
                            $rt_ID = $row["id"];
                            $title = htmlspecialchars($row["title"]);

                            // ID
                            echo "<tr class='row'>";
                            echo "<td align='center'>$rt_ID</td>";

                            // Image
                            $rectypeImg = "style='background-image:url(".HEURIST_ICON_URL.$rt_ID.".png)'";
                            $img = "<img src='".PDIR."hclient/assets/16x16.gif' title='".$title. "' ".$rectypeImg." class='rft' />";
                            echo "<td align='center'>$img</td>";

                            // Type
                            echo "<td style='padding-left: 5px; padding-right: 5px'>"
                            ."<a href='#' title='Open search for this record type in current page' onclick='onrowclick($rt_ID, false)' class='dotted-link'>"
                            .$title.
                            "</a></td>";

                            // Link
                            echo "<td align='center'><a href='#' title='Open search for this record type in new page' onclick='onrowclick($rt_ID, true)' class='external-link'>&nbsp;</a></td>";

                            // Count
                            echo "<td align='center'>" .$row["count"]. "</td>";

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

                        // RETRIEVING RECORDS WITH NO CONNECTIONS
                        $query = "SELECT rty_ID as id, rty_Name as title FROM defRecTypes WHERE rty_ID NOT IN (SELECT DISTINCT rec_recTypeID FROM Records) ORDER BY title ASC;";
                        $res = $system->get_mysqli()->query($query);
                        $count = 0;
                        while($row = $res->fetch_assoc()) { // each loop is a complete table row
                            echo "<tr>";

                            // ID
                            $rt_ID = $row["id"];
                            $rectypeTitle = $row["title"];
                            echo "<td align='center'>" .$rt_ID. "</td>";

                            // Image
                            $title = $row["title"];
                            $rectypeImg = "style='background-image:url(".HEURIST_ICON_URL.$rt_ID.".png)'";
                            $img = "<img src='".PDIR."hclient/assets/16x16.gif' title='".htmlspecialchars($rectypeTitle). "' ".$rectypeImg." class='rft' />";
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
*/                        
                        ?>

                    </table>
                        
                       
                    </div>
                </div>
                <div class="ent_wrapper" style="left:0px">
                    <?php include dirname(__FILE__).'/visualize.html';?>
                </div>
        </div>    

                    

        <script>
            $("#expand").click(function(e) {
                // Show visualisation elements
                $(this).remove();
                //$(".show").slideToggle(500);
                //$("#visualisation-column").slideToggle(500);

                // VISUALISATION CALL
                var url = window.hWin.HAPI4.baseURL+"hsapi/controller/rectype_relations.php" + window.location.search;
//DEBUG                console.log("Loading data from: " + url);
                d3.json(url, function(error, json_data) {
                    // Error check
                    if(error) {
                        return alert("Error loading JSON data: " + error.message);
                    }

                    // Data loaded successfully!
                    //console.log("JSON Loaded");
                    //console.log(json_data);

                    /** RECORD FILTERING */
                    // Set filtering settings in UI
                    var isfirst_time = false;
                    var at_least_one_marked = false;
                    <?php
                        if($count==0){ //reset setting for empty db (only once)
                    ?>
                            isfirst_time = !(getSetting('hdb_'+window.hWin.HAPI4.database)>0);
                            putSetting('hdb_'+window.hWin.HAPI4.database, 1);
                    <?php  
                        }
                    ?>

                    if(!isfirst_time){
                        //restore setting for non empty db
                        $(".show-record").each(function() {
                            var name = $(this).attr("name");
                            var record = getSetting(name); //@todo - change to recordtype ID
                            if(record>0) {
                                at_least_one_marked = true;   
                                $(this).prop("checked", true);
                            }else{
                                $(this).prop("checked", false);
                            }
                        }
                        );
                    }
                        
                    if(isfirst_time || !at_least_one_marked){
                        $(".first_grp").each(function() {
                            $(this).prop("checked", true);
                            putSetting($(this).attr("name"), 1);
                        });
                    }
                    
                    // Listen to 'show-record' checkbox changes
                    $(".show-record").change(function(e) {
                        // Update record field 'checked' value in localstorage
                        var name = $(this).attr("name");
                        // Set 'checked' attribute and store it
                        putSetting(name, $(this).prop('checked')?1:0);

                        // Update visualisation
                        filterData();
                    });

                    // Listen to the 'show-all' checkbox
                    $("#show-all").change(function() {
                        // Change all check boxes
                        var checked = $(this).prop('checked');
                        $(".show-record").prop("checked", checked);

                        // Update localstorage
                        $(".show-record").each(function(e) {
                            var name = $(this).attr("name");
                            // Set 'checked' attribute and store it
                            putSetting(name, checked?1:0);
                        });

                        filterData();
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
                    function initVisualizeData() {
                        // Call plugin
                        //DEBUG 
                        console.log("Calling plugin!");
                        var data_to_vis = getData(json_data);
                        
                        $("#visualisation").visualize({
                            data: json_data,
                            getData: function(data) { return data_to_vis; },
                            linelength: 200,
                            isDatabaseStructure: true,
                            showCounts: false
                        });
                    }

                    //reset settings for empty database
                    if(!(window.hWin.HAPI4.sysinfo.db_total_records>0)){
                        //localStorage.clear();    
                    }
                    
                    $(window).resize(onVisualizeResize)
                    
                    onVisualizeResize();
                    initVisualizeData();

                });
            });
            
            function onVisualizeResize(){
                
                    var width = $(window).width();
              
                    var is_advanced = getSetting('setting_advanced');
                    
                    var supw = 0;
                    if(width<645 || (is_advanced && width<1055)){
                         supw = 2;
                    }
                    
                    var dbkey = 'db'+window.hWin.HAPI4.database;

                // For the moment - Jan 2017 - it is useful to show the hint at all times.
                // for some reason it disappears after first display even though database is still empty, which was not the
                // intention                           
                //if(getSetting(dbkey)==null){ //new databse - show hint
                    putSetting(dbkey, '1');
                    $('#divSvg').css('top', 8+supw+'em');
                    $('#divHint').show();
                    /*}else{
                        $('#divSvg').css('top', 5+supw+'em');
                        $('#divHint').hide();
                    }*/
            }

        </script>
    </body>

</html>