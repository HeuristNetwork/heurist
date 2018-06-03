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
<script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/tabmenus/adminMenu.js"></script>

<script type="text/javascript">
    var editing;

    // Callback function on initialization
    function onPageInit(success){
        if(success){
            var manageMenu = new hadminMenu();
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
                    <li><a href="#" id="menulink-database-browse"
                        data-nologin="1" data-logaction="dbOpen"
                        title="Open and login to another Heurist database - current database remains open">
                        Clone</a>
                    </li>
                    

                    <li><a href="admin/setup/dbcreate/createNewDB.php" name="auto-popup" class="large h3link"
                        onClick="{return false;}" data-logaction="dbNew"
                        title="Delete entire database">
                        Delete</a>
                    </li>
                    
                    <li><a href="#" id="menulink-database-properties"
                        data-nologin="1" data-logaction="dbProperties"
                        title="Delete all records">
                        Empty</a>
                    </li>
                    
                    <li class="admin-only">
                        <a href="admin/setup/dbproperties/registerDB.php" name="auto-popup" class="portrait h3link"
                            onclick= "{return false;}" data-logaction="dbRegister"
                            title="Register this database with the Heurist Master Index - this makes the structure (but not data) available for import by other databases">
                         Rollback</a>
                    </li>

<!--                    

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
                        <a href="admin/structure/fields/manageDetailTypes.php" name="auto-popup" class="h3link"
                            onClick="{return false;}" id="linkEditRectypes" data-logaction="stManage"
                            title="Browse and edit the base field definitions referenced by record types (often shared by multiple record types)">
                            Manage base field types</a>
                    </li>

                    <li>
                        <a href="admin/describe/listRectypeRelations.php?action=simple" name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            title="Display/print a listing of the record types and their simple fields (text, numeric etc.), including usage counts">
                            Simple fields schema</a>
                    </li>
                    <li>
                        <a href="admin/describe/listRectypeRelations.php?action=relations" name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            title="Display/print a listing of the record types and their pointer and relationship fields, including usage counts">
                            Relationships schema</a>
                    </li>
                    <li>
                        <a href="admin/describe/listRectypeRelations.php?action=all" name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            title="Display/print a listing of the record types and all fields, including usage counts">
                            Combined schema</a>
                    </li>

                    <li>
                        <a  href="admin/describe/getDBStructureAsXML.php" name="auto-popup" class="h3link"
                            target="_blank" onClick="{return false;}"
                            title="Lists the record type and field definitions in XML format (HML - Heurist Markup Language)">
                           Structure (XML)</a>
                    </li>

                    <li>
                        <a href="admin/describe/getDBStructureAsSQL.php?pretty=1" name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            title="Lists the record type and field definitions in an SQL-related computer-readable form (deprecated 2014)">
                            Structure (SQL)</a>
                    </li>
                    
                    <li><a href="#" id="menulink-mimetypes"
                        data-nologin="1" 
                        title="Define the relationship between file extension and mime type for uploaded and externally referenced files">
                        Define mime types</a>
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
            <h3><span class="ui-icon ui-iconalign ui-icon-gears"></span>UTILITIES</h3>
            <div>
                <ul>
                    <li class="admin-only">
                        <a  href="#" id="menulink-database-admin"
                            data-logaction="adminFull"
                            onclick="{window.open(window.hWin.HAPI4.baseURL+'admin/adminMenuStandalone.php?db='+window.hWin.HAPI4.database, '_blank'); return false;}"
                            title="Full set of database administration functions, utilities and special project extensions">
                            Verify title masks</a>
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
    <div id="frame_container_div" 
        style="left:281px;right:0;top:0;bottom:20;position:absolute;overflow:auto;padding:20px 10px 0 0;">
        <iframe id="frame_container">
        </iframe>
    </div>

</body>
</html>
