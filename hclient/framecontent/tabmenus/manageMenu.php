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
if(!defined('PDIR')) define('PDIR','../../../');

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
require_once(dirname(__FILE__)."/../initPage.php");
?>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/tabmenus/manageMenu.js"></script>

<script type="text/javascript">
    var editing;

    // Callback function on initialization
    function onPageInit(success){
        if(success){

            var manageMenu = new hmanageMenu();

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
                    <li><a href="common/connect/getListOfDatabases.php?v=4" name="auto-popup" class="portrait h3link"
                        onClick="{return false;}" data-nologin="1" data-logaction="dbOpen"
                        title="Open and login to another Heurist database - current database remains open">
                        Open</a>
                    </li>

                    <li><a href="admin/setup/dbcreate/createNewDB.php" name="auto-popup" class="large h3link"
                        onClick="{return false;}" data-logaction="dbNew"
                        title="Create a new database on the current server - essential structure elements are populated automatically">
                        New</a>
                    </li>
                    
                    <li class="admin-only">
                        <a href="admin/setup/dbproperties/editSysIdentificationAll.php" name="auto-popup" class="portrait h3link"
                            onclick= "{return false;}" data-logaction="dbProperties"
                            title="Edit the internal metadata describing the database and set some global behaviours. Recommended to provide a self-documenting database">
                            Properties</a>
                    </li>
                    
                    <li class="admin-only">
                        <a href="admin/setup/dbproperties/registerDB.php" name="auto-popup" class="portrait h3link"
                            onclick= "{return false;}" data-logaction="dbRegister"
                            title="Register this database with the Heurist Master Index - this makes the structure (but not data) available for import by other databases">
                            Register</a>
                    </li>

<!--                    
                    <li>---- H4 ----</li>                    
                    
                    <li><a href="#" id="menulink-database-browse"
                        data-nologin="1" data-logaction="dbOpen"
                        title="Open and login to another Heurist database - current database remains open">
                        Open</a>
                    </li>
                    
                    <li><a href="#" id="menulink-database-properties"
                        data-nologin="1" data-logaction="dbProperties"
                        title="Edit the internal metadata describing the database and set some global behaviours. Recommended to provide a self-documenting database">
                        Properties</a>
                    </li>
-->                    
                </ul>
            </div>
        </div>

        <div class="accordion_pnl">
            <h3 id="divStructure"><span class="ui-icon ui-iconalign ui-icon-structure"></span>STRUCTURE</h3>
            <div>
                <ul>
                    <!-- database name is appended automatically by auto-popup -->

                    <li>
                        <a href="admin/structure/rectypes/manageRectypes.php" name="auto-popup" class="verylarge h3link refresh_structure "
                            onClick="{return false;}" id="linkEditRectypes" data-logaction="stManage"
                            title="Add new and modify existing record types - general characteristics, data fields and the rules which compose a record">
                            Modify</a>
                    </li>

                    <li>
                        <a href="hclient/framecontent/visualize/databaseSummary.php" name="auto-popup" class="large h3link"
                            data-logaction="stVis"
                            onClick="{return false;}"  id="linkDatabaseSummary"
                            title="Visualise the internal connections between record types in the database and add connections (record pointers and relationshi markers) between them">
                            Visualise</a>
                    </li>
                    
                    <li>
                        <a href="admin/structure/import/selectDBForImport.php" name="auto-popup" class="verylarge h3link refresh_structure"
                            onClick="{return false;}" data-logaction="stAcquire"
                            title="Selectively import record types, fields, terms and connected record types from other Heurist databases">
                            Browse templates</a>
                    </li>

                    <!-- Remarked temporarely 2016-05-11
                    <li>
                    <a href="admin/structure/import/annotatedTemplate.php" name="auto-popup" class="verylarge h3link refresh_structure"
                    onClick="{return false;}"
                    title="Browse documented record type templates on HeuristNetwork.org and selectively import into the current database">
                    Acquire from templates</a>
                    </li>
                    -->

                    <!-- Removed Ian 13 feb 16: this function is likely to confuse, better to have less in front page menus
                    <li>
                    <a href="admin/structure/fields/manageDetailTypes.php" name="auto-popup" class="verylarge h3link refresh_structure"
                    onClick="{return false;}"
                    title="Browse and edit the base field definitions referenced by record types (often shared by multiple record types)">
                    Manage base field types</a>
                    </li>
                    -->

                    <li>
                        <a id= "manage_terms" href="admin/structure/terms/editTerms.php?treetype=enum" 
                            data-logaction="stTerms"
                            name="auto-popup" class="verylarge h3link refresh_structure info_link"

                            title="Browse and edit the terms used for relationship types and for other enumerated (term list) fields" onclick= "{return false;}">
                            Manage terms</a>
                    </li>

                    <!-- Adding Manage relation types menu -->
                    <li>
                        <a  href="admin/structure/terms/editTerms.php?treetype=relation" name="auto-popup" class="verylarge h3link refresh_structure info_link"
                            data-logaction="stRelations"
                            title="Browse and edit the relationship types"  onclick= "{return false;}">
                            Manage relationship types</a>
                    </li>

                    <li>
                        <a href="admin/verification/recalcTitlesAllRecords.php" name="auto-popup" class="verylarge h3link"
                            data-logaction="stRebuildTitles"
                            onClick="{return false;}"
                            title="Rebuilds the constructed record titles listed in search results, for all records">
                            Rebuild record titles</a>
                    </li>
                    
                    <li>
                        <a href="admin/verification/listDatabaseErrorsInit.php" name="auto-popup" class="verylarge h3link"
                            data-logaction="stVerify"
                            onClick="{return false;}"
                            title="Find errors in database structure (invalid record type, field and term codes) and records with incorrect structure or inconsistent values (invalid pointer, missed data etc)">
                            Verify</a>
                    </li>

                    <li id="menu-database-refresh">
                        <a href="#" id="menulink-database-refresh"
                            data-logaction="stRefresh"
                            onClick="{return false;}" data-nologin="1"
                            title="Clear and reload Heurist's internal working memory in your browser. Use this to correct dropdowns etc. if recent additions and changes do not show.">
                            Refresh</a>
                    </li>
                    
                    <!--
                    <li><a href="javascript:void(0)"
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
                    <li class="top-menu-only">
                        <a href="viewers/crosstab/crosstabs.php" name="auto-popup" class="verylarge currentquery h3link"
                            title="Tabulate one, two or three variables for the current query, with optional percentages, row and column totals" >
                            Crosstabs analysis</a>
                    </li>
                </ul>
            </div>
        </div>
        <div class="accordion_pnl">
            <h3><span class="ui-icon ui-iconalign ui-icon-gears"></span>ADMINISTRATION</h3>
            <div>
                <ul>
                    <li class="admin-only">
                        <a  href="#" id="menulink-database-admin"
                            data-logaction="adminFull"
                            onclick="{window.open(window.hWin.HAPI4.baseURL+'admin/adminMenuStandalone.php?db='+window.hWin.HAPI4.database, '_blank'); return false;}"
                            title="Full set of database administration functions, utilities and special project extensions">
                            Advanced functions &amp; utilities</a>
                    </li>
                    <li class="admin-only">
                        <a href="export/dbbackup/exportMyDataPopup.php?inframe=1" name="auto-popup" class="portrait h3link"
                            data-logaction="adminArchive"
                            onclick= "{return false;}"
                            title="Writes all the data in the database as SQL and XML files, plus all attached files, schema and documentation, to a ZIP file which you can download from a hyperlink">
                            Create data archive package</a>
                    </li>

                                        <li>
                        <a href="applications/faims/exportFAIMS.php" name="auto-popup" class="verylarge h3link"
                            data-logaction="stFAIMS"
                            onClick="{return false;}"
                            title="Create FAIMS module / tablet application structure from the current Heurist database structure. No data is exported">
                            Build tablet app</a>
                    </li>

                    <!--
                        TEMPORARILY REMOVED. TODO: SOMETHING IS WRONG 3/7/2014 WITH THE EXPORT FUNCTION,
                        IT OPENS UP THE EXPORT MODULE FORM AND WON'T CLOSE THE RECORD TYPE SELECTION POPUP
                    <li class="admin-only">
                        <a href="applications/faims/exportTDar.php" name="auto-popup" class="portrait h3link"
                            onclick= "{return false;}"
                            title="Export the current database as tables, files and metadata directly into a specified tDAR repository">
                            Export to tDAR repository</a>
                    </li>
                    -->
                    
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
