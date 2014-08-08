<?php

    /**
    * adminMenu.php : framework for adminstrative interface (designer view)
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
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


    define('ROOTINIT', 1);
    require_once (dirname(__FILE__) . '/../common/connect/applyCredentials.php');

    // TODO: something fishy: above path appears wrong, although phpEd navigates from this to the applyCredentials file
    // However /common/etc. and ../common/etc. - both of which are correct paths and work with phpEd - cause the admin menu to hang.

    if (!is_logged_in()) {
        header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db=' . HEURIST_DBNAME . "&last_uri=" . urlencode(HEURIST_CURRENT_URL));
        //HEURIST_BASE_URL.'admin/adminMenu.php?db='.HEURIST_DBNAME);
        return;
    }

    // Introductory orientation for designer view

    $url = "../common/html/msgWelcomeAdmin.html";

    // Direct access to admin menu (designer view) functions
    if (array_key_exists('mode', $_REQUEST)) {
        $mode = $_REQUEST['mode'];

        if ($mode == "users") {  // Manage Users, used to confirm registrations
            $url = "ugrps/manageUsers.php?db=" . HEURIST_DBNAME;
            if (array_key_exists('recID', $_REQUEST)) {
                $recID = $_REQUEST['recID'];
                $url = $url . "&recID=" . $recID;
            }

        } else if ($mode == "rectype") { // record structure edit, called from record edit mode
            $url = "structure/rectypes/manageRectypes.php?db=" . HEURIST_DBNAME;
            if (array_key_exists('rtID', $_REQUEST)) {
                $rtID = $_REQUEST['rtID'];
                $url = $url . "&rtID=" . $rtID;
            }
        }
    } // end direct access to admin menu (designer view) functions
?>

<html>

    <head>
        <title>Heurist - <?=HEURIST_DBNAME?> Designer View</title>
        <link rel="icon" href="../favicon.ico" type="image/x-icon">
        <link rel="shortcut icon" href="../favicon.ico" type="image/x-icon">
        <link rel="stylesheet" type="text/css" href="../common/css/global.css">
        <link rel="stylesheet" type="text/css" href="../common/css/admin.css">
    </head>

    <body>
        <script src="../external/jquery/jquery-1.5.1.min.js"></script>
        <script src="../external/jquery/jquery-ui-1.8.13.custom.min.js"></script>
        <script type="text/javascript" src="../external/yui/2.8.2r1/build/yahoo/yahoo-min.js"></script>
        <script type="text/javascript" src="../external/yui/2.8.2r1/build/json/json-min.js"></script>
        <script src="../common/js/utilsLoad.js"></script><!-- core HEURIST functions -->
        <script src="../common/js/utilsUI.js"></script><!-- core HEURIST functions -->
        <script src="../common/php/displayPreferences.php"></script> <!-- sets body css classes based on prefs in session -->

        <script>
            top.HEURIST.VERSION = "<?=HEURIST_VERSION?>";
            $(function() {

                var icons = {
                    header: "header",
                    headerSelected: "headerselected"
                };

                $( "#sidebar-inner" ).accordion({
                    collapsible: true,
                    active:false,
                    icons: icons,
                    autoHeight: false
                });
            });
        </script>

        <script type="text/javascript">
            function loadContent(url){
                var recordFrame = document.getElementById("adminFrame");
                recordFrame.src = top.HEURIST.basePath+"common/html/msgLoading.html";
                setTimeout(function(){
                    recordFrame.src = top.HEURIST.basePath+"admin/"+url;
                    },500);
                return false;
            }; // end loadContent
        </script>

        <!-- hapi required for image upload in bugreport -->
        <script src="../common/php/loadHAPI.php"></script>

        <script>
            var bugReportURL = '../export/email/formEmailRecordPopup.html?rectype=bugreport&db=<?=HEURIST_DBNAME?>';
            window.history.pushState("object or string", "Title", location.pathname+'?db=<?=HEURIST_DBNAME?>');
        </script>

        <a id=home-link href="../index.php?db=<?=HEURIST_DBNAME?>">
            <div id=logo title="Click the logo at top left of any Heurist page to return to your default search (User View)"></div>
        </a>

        <div id=version></div>

        <!-- database name = link back to default search (Uaeer View) -->
        <a id="dbSearch-link" href="../index.php?db=<?=HEURIST_DBNAME?>">
            <div id="dbname" ><?=HEURIST_DBNAME?> <span>Designer View</span></div>
        </a>

        <!-- Quicklinks section - top right -->
        <div id="quicklinks" style="top:10px;right:135px">
            <ul id=quicklink-cell>
                <li id="reportBug" class="button white">
                    <a href="#" onClick="top.HEURIST.util.popupURL(top, bugReportURL,{'close-on-blur': false,'no-resize': false, height: 400,width: 740,callback: function (title, bd, bibID) {if (bibID) {window.close(bibID, title);}} });return false;"
                        title="Click to send a bug report or feature request to the Heurist developers" >
                        &nbsp;Bug report</a></li>
                <li class="button white"><a href="javascript:void(0)" onClick="{top.HEURIST.util.reloadStrcuture();}"
                    title="Click to clear and reload the internal working memory of Heurist" >
                    Refresh lists</a></li>
            </ul>
        </div> <!-- end quicklinks -->

        <!-- sidebar - lefthand side -->

        <?
            function menuEntry($separator,$action,$actionFile,$actionLabel) {
                if ($separator == '---') {print '<li class="seperator">';} else {print '<li>';};
                print '<a href="#" onClick="loadContent(' . "'$actionFile'";
                print ')" title="' . $actionLabel . '">' . $action . '</a></li>';
            } // menuEntry
        ?>

        <div id="sidebar">

            <div class="banner" style="padding-left:20px;padding-top:4px;border-bottom:1px solid #696969;">
                <button id="link_admin" onclick="{location.href='../index.php?db=<?=HEURIST_DBNAME?>';}"
                    title="Click to return to the default search page (User View)" class="button"><b>go to USER VIEW</b></button>
            </div>

            <div id="sidebar-inner" style="padding-top: 25px;">

                <!-- <div id="accordion">-->


                <h3><a href="#">ESSENTIALS: </a>
                    <span class="description">Shortcut to the main functions needed to create and design a database</span>
                </h3>
                <div class="adminSection">
                    <ul>
                        <?
                            menuEntry('---','Open database','../common/connect/getListOfDatabases.php',
                                'List the databases on the current server to which you have access '.
                                '(you are identified by the email address attached to your current user login)');

                            menuEntry('','New database','setup/dbcreate/createNewDB.php',
                                'Create a new database on the current server - essential structure elements are populated automatically');
                            menuEntry('','Record types and fields','structure/rectypes/manageRectypes.php?db='.HEURIST_DBNAME,
                                'Add new or modify existing record types, including general characteristics '.
                                'and the data fields and rules which compose a record');

                            menuEntry('','Browse templates (Beta)','structure/import/annotatedTemplate.php?db='.HEURIST_DBNAME,
                                'Browse documented record type templates on HeuristNetwork.org '.
                                'and selectively import them into the current database');

                            menuEntry('','Import structure','structure/import/selectDBForImport.php?db='.HEURIST_DBNAME,
                                'Selectively import structural elements (record types, fields, terms and connected record types) '.
                                'from other Heurist databases into the current database');

                            menuEntry('','Manage workgroups','ugrps/manageGroups.php?db='.HEURIST_DBNAME,
                                'Create new work groups and users and/or assign users to workgroups '.
                                'and set their roles (admin, member) relative to each workgroup');
                            /* WAS: onClick="{loadContent('ugrps/manageGroups.php?db=<?=HEURIST_DBNAME?>');return false;}" */
                        ?>
                    </ul>
                </div>


                <h3><a href="#">DATABASE: </a>
                    <span class="description">Find, copy, delete and set properties of the currrent database</span>
                </h3>
                <div class="adminSection">
                    <ul>
                        <?
                            menuEntry('---','Open database','../common/connect/getListOfDatabases.php',
                                'List the databases on the current server to which you have access '.
                                '(you are identified by the email address attached to your current user login)');

                            menuEntry('','New database','setup/dbcreate/createNewDB.php',
                                'Create a new database on the current server - essential structure elements are populated automatically');
                            menuEntry('---','Clone database','setup/dboperations/cloneDatabase.php?db='.HEURIST_DBNAME,
                                'Clones an identical database from the currrent database with all data, users, attached files, templates etc.');

                            if (is_admin()) {
                                menuEntry('','Delete entire database','setup/dboperations/deleteCurrentDB.php?db='.HEURIST_DBNAME,
                                    'Delete the current database completely - cannot be undone, although data is copied '.
                                    'to a backup which could be reloaded by a sysadmin');

                                menuEntry('','Delete all records','setup/dboperations/clearCurrentDB.php?db='.HEURIST_DBNAME,
                                    'Clear all data (records, values, attached files) from the current database. '.
                                    'Database atructure - record types, fields, terms, constraints - is unaffected');

                                menuEntry('---','Registration','setup/dbproperties/registerDB.php?db='.HEURIST_DBNAME,
                                    'Register this database with the Heurist Master Index at HeuristScholar.org - '.
                                    'this makes the structure (but not data) available for import by other databases');

                                menuEntry('','Properties','setup/dbproperties/editSysIdentification.php?db='.HEURIST_DBNAME,
                                    'Edit the internal metadata describing the database and set some global behaviours. '.
                                    'Recommended to provide a self-documenting database');

                                menuEntry('','Advanced properties','setup/dbproperties/editSysIdentificationAdvanced.php?db='.HEURIST_DBNAME,
                                    'Edit advanced behaviours, including special file paths and parameters '.
                                    'for harvesting email from external servers');
                                // June 2014: TODO: need to fix the triggers on record creation and editing for this function to work.
                                // Warning in fucntion
                                menuEntry('','Rollback','rollback/rollbackRecords.php?db=',
                                    'Selectively roll back the data in the database to a specific date and time)');
                            }
                        ?>
                    </ul>
                </div>


                <h3><a href="#">STRUCTURE: </a>
                    <span class="description">Design data types and data entry rules for the database</span>
                </h3>
                <div class="adminSection">
                    <ul>
                        <?
                            menuEntry('---','Record types and fields','structure/rectypes/manageRectypes.php?db='.HEURIST_DBNAME,
                                'Add new or modify existing record types, including general characteristics '.
                                'and the data fields and rules which compose a record');

                            menuEntry('','Browse templates (Beta)','structure/import/annotatedTemplate.php?db='.HEURIST_DBNAME,
                                'Browse documented record type templates on the Heurist Network site '.
                                'and selectively import them into the current database');

                            menuEntry('','Import structure','structure/import/selectDBForImport.php?db='.HEURIST_DBNAME,
                                'Selectively import structural elements (record types, fields, terms and connected record types) '.
                                'from other Heurist databases into the current database');

                            menuEntry('','Manage field types','structure/fields/manageDetailTypes.php?db='.HEURIST_DBNAME,
                                'Browse and edit the base field definitions referenced by record types (often shared by multiple record types)');
                            menuEntry('','Manage terms','structure/terms/editTerms.php?db='.HEURIST_DBNAME,
                                'Browse and edit the terms used for relationship types and for other enumerated (term list) fields');
                            menuEntry('','Relationship constraints','structure/rectypes/editRectypeConstraints.php?db='.HEURIST_DBNAME,
                                'Define overal constraints on the record types which can be related, including allowable '.
                                'relationship types between specific record types');

                            menuEntry('---','Simple fields schema','describe/listRectypeRelations.php?db='.HEURIST_DBNAME.'&action=simple',
                                'Display/print a listing of the record types and their simple fields (text, numeric etc.), including usage counts');
                            menuEntry('','Relationships schema','describe/listRectypeRelations.php?db='.HEURIST_DBNAME.'&action=relations',
                                'Display/print a listing of the record types and their pointer and relationship fields, including usage counts');
                            menuEntry('','Combined schema','describe/listRectypeRelations.php?db='.HEURIST_DBNAME.'&action=all',
                                'Display/print a listing of the record types and all fields, including usage counts');
                        ?>
                        <li>
                            <a href="describe/getDBStructureAsXML.php?db=<?=HEURIST_DBNAME?>"
                                target="_blank"
                                title="Lists the record type and field definitions in XML format (HML - Heurist Markup Language)">
                                Structure (XML) <img src="../common/images/external_link_16x16.gif"
                                width="12" height="12" border="0" title="XML Structure"
                                </a>
                        </li>
                        <?
                            menuEntry('','Define mime types','structure/mimetypes/manageMimetypes.php?db='.HEURIST_DBNAME,
                                'Define the relationship between file extension and mime type for uploaded and externally referenced files');
                            menuEntry('---','Structure (deprecated)','describe/getDBStructureAsSQL.php?db='.HEURIST_DBNAME.'&amp;pretty=1',
                                'Lists the record type and field definitions in an SQL-related computer-readable form (deprecated 2014)');
                            menuEntry('','XForms (deprecated)','describe/getDBStructureAsXForms.php?db='.HEURIST_DBNAME,
                                'Save selected record types as XForms definitions - this function is not maintained');
                        ?>
                    </ul>
                </div>


                <h3><a href="#">ACCESS: </a>
                    <span class="description">Manage workgroups, users and roles of users within groups (admin, member)</span>
                </h3>
                <div class="adminSection">
                    <ul>
                        <?
                            menuEntry('','Manage workgroups','ugrps/manageGroups.php?db='.HEURIST_DBNAME,
                                'Create new work groups and users and/or assign users to workgroups '.
                                'and set their roles (admin, member) relative to each workgroup');
                            /* WAS: onClick="{loadContent('ugrps/manageGroups.php?db=<?=HEURIST_DBNAME?>');return false;}" */

                            menuEntry('','Workgroup tags','ugrps/editGroupTags.php?db='.HEURIST_DBNAME,
                                'Add and remove workgroup tags (tags belong to workgroups rather than individual users). '.
                                'Workgroup tags can only be added / modified through this function');

                            menuEntry('','Quit workgroup temporarily','ugrps/quitGroupForSession.php?db='.HEURIST_DBNAME,
                                'Quit a workgroup for this session to allow testing of non-group-members view of the database');

                            menuEntry('---','Manage users','ugrps/manageUsers.php?db='.HEURIST_DBNAME.'',
                                'Add and edit database users, including authorization of new users. Use Manage Workgroups '.
                                'to allocate users to workgroups and define roles within workgroups');

                            menuEntry('','Import user','ugrps/getUserFromDB.php?db='.HEURIST_DBNAME,
                                'Import users one at a time from another database on the system, including user name, password '.
                                'and email address - users are NOT assigned to workgroups');
                        ?>
                    </ul>
                </div>


                <h3><a href="#">UTILITIES: </a>
                    <span class="description">Verify and fix database integrity, specialised tools</span>
                </h3>
                <div class="adminSection">
                    <ul>
                        <?
                            // TITLE MASKS
                            menuEntry('','Verify title masks','verification/checkRectypeTitleMask.php?check=1&amp;db='.HEURIST_DBNAME,
                                'Check correctness of each Record Type\'s title mask with respect to field definitions');

                            menuEntry('','Rebuild record titles','verification/recalcTitlesAllRecords.php?db='.HEURIST_DBNAME,
                                'Rebuilds the constructed record titles listed in search results, for all records');
                            // We also have function for specific records and rectypes

                            menuEntry('','Find duplicate records','verification/listDuplicateRecords.php?fuzziness=10&amp;db='.HEURIST_DBNAME,
                                'Fuzzy search to identify records which might contain duplicate data');
                            menuEntry('','',''.HEURIST_DBNAME.'',
                                '');
                            // DATA QUALITY
                            menuEntry('---','Verify data consistency','verification/listDataErrors.php?db='.HEURIST_DBNAME,
                                'Find records with wrong structure and inconsistent values (invalid pointer, missed data etc)');

                            menuEntry('','Verify field definitions','verification/listFieldTypeDefinitionErrors.php?db='.HEURIST_DBNAME,
                                'Find field types with invalid terms or pointer record types');

                            menuEntry('','Verify wysiwyg texts','verification/checkXHTML.php?db='.HEURIST_DBNAME.'',
                                'Check the wysiwyg text fields in records/blog entries for structural errors');

                            menuEntry('','Verify characters in texts','verification/checkInvalidChars.php?db='.HEURIST_DBNAME,
                                'Check the wysiwyg text fields in records/blog entries for invalid characters');

                            menuEntry('','Fix invalid characters','verification/cleanInvalidChars.php?db='.HEURIST_DBNAME,
                                'Attempt to clean up invalid characters in the wysiwyg text fields');
                        ?>
                        <li><a href="../search/search.html??w=bookmark&amp;q=-tag&amp;label=Bookmarks without tags&amp;db=<?=HEURIST_DBNAME?>" target="_blank"
                            title="Find bookmarked records which you have not tagged">
                            Bookmarks without tags</a>
                        </li>
                        <li><a href="../search/search.html?q=_BROKEN_&amp;w=all&amp;db=<?=HEURIST_DBNAME?>" target="_blank"
                            title="Show records with URLs which point to a non-existant or otherwise faulty address">
                            Verify URLs</a>
                        </li>
                        <?
                            // INSTALLATION AND STATS
                            menuEntry('---','Verify installation','verification/verifyInstallation.php?db='.HEURIST_DBNAME.'',
                                'Verifies that all required JS libraries, other components and directories are in expected locations');

                            menuEntry('','Rebuild Lucene<br />(work in progress)','verification/rebuildLuceneIndices.php?db='.HEURIST_DBNAME,
                                'Rebuilds the Lucence indices used by Elastic Search, for all record types');

                            menuEntry('','Clear database locks','verification/removeDatabaseLocks.php?db='.HEURIST_DBNAME,
                                'Remove database locks - use ONLY if you are sure no-one else is accessing adminstrative functions');
                            menuEntry('','Database usage statistics','describe/dbStatistics.php?db='.HEURIST_DBNAME,
                                'Size and usage statistics for all Heurist databases on this server');

                            menuEntry('','Inter-database transfer','../import/direct/getRecordsFromDB.php?db='.HEURIST_DBNAME,
                                'Import records directly from one database to another, mapping record types, fields types and terms');
                            menuEntry('','Schedule Smarty reports','../export/publish/manageReports.html?db='.HEURIST_DBNAME,
                                'Add new / modify existing scheduled Smarty reports');
                        ?>
                    </ul>
                </div>


                <h3><a href="#">FAIMS & HuNI: </a><span class="description">
                    Infrastructure projects - tablet data collection and data aggregation/federated search</span>
                </h3>
                <div class="adminSection">
                    <ul>
                        <?
                            // FAIMS
                            menuEntry('---','About FAIMS','../applications/faims/about.html?db='.HEURIST_DBNAME,
                                'Information about the FAIMS (Federated Archaeological Information Management System) project');
                            menuEntry('','Create module definition','../applications/faims/exportFAIMS.php?db='.HEURIST_DBNAME,
                                'Create FAIMS module / tablet application structure from the current Heurist database structure. No data is exported');
                            /* todo: not yet implemented
                            menuEntry('','Export module + data','../applications/faims/exportFAIMSwithdata.php?db='.HEURIST_DBNAME.'',
                            'Export a FAIMS format tarball (module including data) from the current Heurist database');
                            */
                            menuEntry('','Import module + data','../applications/faims/syncFAIMS.php?db='.HEURIST_DBNAME,
                                'Import structure and data into the current Heurist database from a FAIMS module tarball '.
                                'or direct from FAIMS server database');
                            /* TEMPORARILY REMOVED. TODO: SOMETHING IS WRONG 3/7/2014 WITH THE EXPORT FUNCTION,
                            IT OPENS UP THE EXPORT MODULE FORM AND WON'T CLOSE THE RECORD TYPE SELECTION POPUP
                            menuEntry('','Export to tDAR repository','../applications/faims/exportTDar.php?db='.HEURIST_DBNAME.'',
                            'Export the current database as tables, files and metadata directly into a specified tDAR repository');
                            */
                            // HuNI
                            menuEntry('---','About HuNI','../applications/huni/about.html?db='.HEURIST_DBNAME,
                                'Information about the HuNI (Humanities Networked Infrastructure) project');

                            menuEntry('','Export HML','../export/xml/flathml.php?w=all&q=sortby:rt&a=1&depth=0&file=1&db='.HEURIST_DBNAME,
                                'Export data from the current Heurist database to a HuNI-harvestable XML file');
                        ?>

                        <!-- TODO: Need to make this work here and remove from export menu on the search page
                        <li style="padding-left:25px;"><a href="#" id=result-style-icons-link title="Generate HML for Huni feed"
                        onClick="top.HEURIST.search.exportHML(true,false,true);"
                        title="Export harvestable XML from the current Heurist database to a HuNI-harvestable package of XML files";>
                        Export HML as files</a></li>
                        -->

                    </ul>
                </div>
                <!--</div>-->
                <!-- end accordion -->

            </div> <!-- sidebar-inner -->
        </div> <!-- sidebar -->


        <div id="page">
            <div id="page-inner">
                <div class="contentframe">
                    <iframe width="100%" height="100%" id="adminFrame" name="adminFrame" frameborder="0" src="<?=$url?>"></iframe>
                </div>
            </div>
        </div>

    </body>
</html>