<?php

/**
* Database structure / administration page
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

define('MANAGER_REQUIRED',1);

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
require_once(dirname(__FILE__)."/initPage.php");
?>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/databaseAdmin.js"></script>

<script type="text/javascript">
    var editing;

    // Callback function on initialization
    function onPageInit(success){
        if(success){

            var databaseAdmin = new hDatabaseAdmin();

            var $container = $("<div>").appendTo($("body"));
        }
    }
</script>
<style>
</style>
</head>
<body class="ui-widget-content">
    <div style="width:280px;top:0;bottom:0;left:0;position:absolute;">
        <div class="accordion_pnl" style="margin-top:21px">
            <h3><span class="ui-icon ui-iconalign ui-icon-database"></span>DATABASE</h3>
            <div>
                <ul>
                    <li style="padding-left:5px;"><a href="common/connect/getListOfDatabases.php?v=4" name="auto-popup" class="portrait h3link"
                        onClick="{return false;}" data-nologin="1"
                        title="Open and login to another Heurist database - current database remains open">
                        Open database</a>
                    </li>

                    <li style="padding-left:5px;"><a href="admin/setup/dbcreate/createNewDB.php" name="auto-popup" class="large h3link"
                        onClick="{return false;}"
                        title="Create a new database on the current server - essential structure elements are populated automatically">
                        New database</a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="accordion_pnl">
            <h3 id="divStructure"><span class="ui-icon ui-iconalign ui-icon-structure"></span>STRUCTURE</h3>
            <div>
                <ul>
                    <!-- database name is appended automatically by auto-popup -->

                    <li style="padding-left:5px;">
                        <a href="admin/structure/rectypes/manageRectypes.php" name="auto-popup" class="verylarge h3link refresh_structure "
                            onClick="{return false;}" id="linkEditRectypes"
                            title="Add new / modify existing record types - general characteristics, data fields and rules which compose a record">
                            Manage record types / fields</a>
                    </li>

                    <li style="padding-left:5px;">
                        <a href="admin/structure/import/selectDBForImport.php" name="auto-popup" class="verylarge h3link refresh_structure"
                            onClick="{return false;}"
                            title="Selectively import record types, fields, terms and connected record types from other Heurist databases">
                            Acquire from databases</a>
                    </li>

                    <!-- Remarked temporarely 2016-05-11
                    <li style="padding-left:5px;">
                    <a href="admin/structure/import/annotatedTemplate.php" name="auto-popup" class="verylarge h3link refresh_structure"
                    onClick="{return false;}"
                    title="Browse documented record type templates on HeuristNetwork.org and selectively import into the current database">
                    Acquire from templates</a>
                    </li>
                    -->

                    <!-- Removed Ian 13 feb 16: this function is likely to confuse, better to have less in front page menus
                    <li style="padding-left:5px;">
                    <a href="admin/structure/fields/manageDetailTypes.php" name="auto-popup" class="verylarge h3link refresh_structure"
                    onClick="{return false;}"
                    title="Browse and edit the base field definitions referenced by record types (often shared by multiple record types)">
                    Manage base field types</a>
                    </li>
                    -->

                    <li  style="padding-left:5px;">
                        <a id= "manage_terms" href="admin/structure/terms/editTerms.php?treetype=<?php echo'terms'?>" name="auto-popup" class="verylarge h3link refresh_structure info_link"

                            title="Browse and edit the terms used for relationship types and for other enumerated (term list) fields" onclick= "{return false;}">
                            Manage terms</a>
                    </li>

                    <!-- Adding Manage relation types menu -->
                    <li  style="padding-left:5px;">
                        <a  href="admin/structure/terms/editTerms.php?treetype=<?php echo'relationships'?>" name="auto-popup" class="verylarge h3link refresh_structure info_link"

                            title="Browse and edit the relationship types"  onclick= "{return false;}">
                            Manage relation types</a>
                    </li>

                    <li style="padding-left:5px;">
                        <a href="hclient/framecontent/databaseSummary.php" name="auto-popup" class="large h3link"
                            onClick="{return false;}"  xid="menulink-database-summary"
                            title="Take a look at the internal connections between record types in this database">
                            Visualise structure</a>
                    </li>

                    <li style="padding-left:5px;">
                        <a href="admin/verification/listDatabaseErrors.php" name="auto-popup" class="verylarge h3link"
                            onClick="{return false;}"
                            title="Find errors in database structure (invalid record type, field and term codes) and records with incorrect structure or inconsistent values (invalid pointer, missed data etc)">
                            Verify structure and data</a>
                    </li>

                    <li style="padding-left:5px;" id="menu-database-refresh">
                        <a href="#" id="menulink-database-refresh"
                            onClick="{return false;}" data-nologin="1"
                            title="Clear and reload Heurist's internal working memory in your browser. Use this to correct dropdowns etc. if recent additions and changes do not show.">
                            Refresh</a>
                    </li>

                    <!--
                    <li style="padding-left:5px;"><a href="javascript:void(0)"
                    onClick="{/*window.hWin.HEURIST4.util.reloadStrcuture()*/;}"
                    title="Click to refresh the internal working memory - use to resynchronise if newly added structure elements do not show up" >
                    Refresh</a>
                    </li>
                    -->
                </ul>
            </div>
        </div>
        <div class="accordion_pnl">
            <h3 class="top-menu-only">ANALYSE</h3>
            <div>
                <ul>
                    <li style="padding-left:5px;" class="top-menu-only">
                        <a href="viewers/crosstab/crosstabs.php" name="auto-popup" class="verylarge currentquery h3link"
                            title="Tabulate one, two or three variables for the current query, with optional percentages, row and column totals" >
                            Crosstabs analysis</a>
                    </li>
                </ul>
            </div>
        </div>
        <div class="accordion_pnl">
            <h3>ADMINISTRATION</h3>
            <div>
                <ul>
                    <li class="admin-only" style="padding-left:5px;">
                        <a  href="#" id="menulink-database-admin"
                            onclick="{window.open(window.hWin.HAPI4.basePathV3+'admin/adminMenu.php?db='+window.HAPI4.database, '_self'); return false;}"
                            title="Full set of database administration functions, utilities and special project extensions">
                            Full database administration, <br />utilities &amp; special functions</a>
                    </li>
                </ul>
            </div>
        </div>

    </div>
    <div style="left:281px;right:0;top:0;bottom:20;position:absolute;overflow:auto;padding:20px 10px 0 0;">
        <iframe id="frame_container">
        </iframe>
    </div>

</body>
</html>
