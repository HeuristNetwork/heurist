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
    * administration menu and framwork.
    *
    * @author      Tom Murtagh
    * @author      Kim Jackson
    * @author      Ian Johnson   <ian.johnson@sydney.edu.au>
    * @author      Stephen White   <stephen.white@sydney.edu.au>
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2013 University of Sydney
    * @link        http://Sydney.edu.au/Heurist
    * @version     3.1.0
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
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
    $url = "../common/html/msgWelcomeAdmin.html";
    if (array_key_exists('mode', $_REQUEST)) {
        $mode = $_REQUEST['mode'];
        if ($mode == "users") {
            //HEURIST_BASE_URL
            $url = "ugrps/manageUsers.php?db=" . HEURIST_DBNAME;
            if (array_key_exists('recID', $_REQUEST)) {
                $recID = $_REQUEST['recID'];
                $url = $url . "&recID=" . $recID;
            }
            //clear
            //window.history.pushState("object or string", "Title", location.pathname+'?db=

        } else if ($mode == "rectype") {
            $url = "structure/rectypes/manageRectypes.php?db=" . HEURIST_DBNAME;
            if (array_key_exists('rtID', $_REQUEST)) {
                $rtID = $_REQUEST['rtID'];
                $url = $url . "&rtID=" . $rtID;
            }
        }
    }
?>
<html>

    <head>
        <title>Heurist - <?=HEURIST_DBNAME?> Admin</title>
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
            };
        </script>
        <!-- hapi required for image upload in bugreport -->
        <script src="../common/php/loadHAPI.php"></script>
        <script>
            var bugReportURL = '../export/email/formEmailRecordPopup.html?rectype=bugreport&db=<?=HEURIST_DBNAME?>';
            window.history.pushState("object or string", "Title", location.pathname+'?db=<?=HEURIST_DBNAME?>');
        </script>
        <a id=home-link href="../index.php?db=<?=HEURIST_DBNAME?>">
            <div id=logo title="Click the logo at top left of any Heurist page to return to your Favourites"></div>
        </a>
        <div id=version></div>
        <!-- database name -->
        <a id="dbSearch-link" href="../index.php?db=<?=HEURIST_DBNAME?>">
            <div id="dbname" ><?=HEURIST_DBNAME?> <span>Designer View</span></div>
        </a>
        <div id="quicklinks" style="top:10px;right:15px">
            <ul id=quicklink-cell>
                <li id="reportBug" class="button white">
                <a href="#" onClick="top.HEURIST.util.popupURL(top, bugReportURL,{'close-on-blur': false,'no-resize': false, height: 400,width: 740,callback: function (title, bd, bibID) {if (bibID) {window.close(bibID, title);}} });return false;" 
                title="Click to send a bug report or feature request" >Bug report</a></li>
                <li class="button white"><a href="javascript:void(0)" onClick="{top.HEURIST.util.reloadStrcuture();}" title="Click to clear the internal working memory of Heurist" >Refresh memory</a></li>
            </ul>
        </div>

        <!-- sidebar -->
        <div id="sidebar">
            <div class="banner" style="padding-left:20px;padding-top:4px;border-bottom:1px solid #696969;">
                <button id="link_admin" onclick="{location.href='../index.php?db=<?=HEURIST_DBNAME?>';}"
                    title="Click to return to the main search page" class="button"><b>go to USER VIEW</b></button>
            </div>

            <div id="sidebar-inner" style="padding-top: 25px;">
                <!-- <div id="accordion">-->

                <h3><a href="#">ESSENTIALS: </a>
                    <span class="description">The main functions needed to create and design a database</span></h3>
                <div class="adminSection">
                    <ul>
                        <li class="seperator"><a href="#" 
                            onClick="loadContent('../common/connect/getListOfDatabases.php')"
                            type="List the databases on the current server to which you have access">
                            Open database</a></li>
                        <li><a href="#" 
                            onClick="loadContent('setup/dbcreate/createNewDB.php')"
                            type="Create a new database with essential structure elements">New database</a></li>
                        <li><a href="#" 
                            onClick="loadContent('structure/rectypes/manageRectypes.php?db=<?=HEURIST_DBNAME?>')"
                            title="Add new / modify existing record types and their use of globally defined fields">
                            Record types / fields</a></li>
                        <li><a href="#" 
                            onClick="loadContent('structure/import/annotatedTemplate.php?db=<?=HEURIST_DBNAME?>')"
                            title="Browse documented record type templates on Heurist Network site and selectively import them">
                            Browse templates (Beta)</a></li>
                        <li><a href="#" 
                            onClick="loadContent('structure/import/selectDBForImport.php?db=<?=HEURIST_DBNAME?>')"
                            title="Selectively import structural elements from other Heurist databases">
                            Import structure</a></li>
                        <li><a href="#"
                            onClick="{loadContent('ugrps/manageGroups.php?db=<?=HEURIST_DBNAME?>');return false;}"
                            title="Assign users to workgroups and set their roles">
                            Manage workgroups</a></li>
                    </ul>
                </div>

                <h3><a href="#">DATABASE: </a>
                    <span class="description">Find, copy, delete and set properties of the database</span></h3>
                <div class="adminSection">
                    <ul>

                        <li class="seperator"><a href="#" 
                            onClick="loadContent('../common/connect/getListOfDatabases.php')"
                            type="List the databases on the current server to which you have access">Open database</a></li>
                        <li><a href="#" 
                            onClick="loadContent('setup/dbcreate/createNewDB.php')"
                            type="Create a new database with essential structure elements">
                            New database</a></li>
                        <li><a href="#" 
                            onClick="loadContent('setup/dboperations/cloneDatabase.php?db=<?=HEURIST_DBNAME?>')"
                            title="Clones a complete database with all data, users, attached files, templates etc.">
                            Clone database</a></li>
                        <?php
                            if (is_admin()) {
                            ?>
                            <li><a href="setup/dboperations/deleteCurrentDB.php?db=<?=HEURIST_DBNAME?>"
                                title="Delete a database completely">
                                Delete entire database</a></li>
                            <li><a href="setup/dboperations/clearCurrentDB.php?db=<?=HEURIST_DBNAME?>"
                                title="Clear all data from the current database, database definitions are unaffected">
                                Delete all records</a></li>
                            <?php
                            }
                        ?>
                        <li class="seperator"><a href="#"  
                            onClick="loadContent('setup/dbproperties/registerDB.php?db=<?=HEURIST_DBNAME?>')"
                            title="Register this database with the Heurist Master Index">
                            Registration</a></li>
                        <li><a href="#" 
                            onClick="loadContent('setup/dbproperties/editSysIdentification.php?db=<?=HEURIST_DBNAME?>')"
                            title="Edit the internal metadata describing the database and set some global behaviours">
                            Properties</a></li>
                        <li><a href="#" 
                            onClick="loadContent('setup/dbproperties/editSysIdentificationAdvanced.php?db=<?=HEURIST_DBNAME?>')"
                            title="Edit advanced behaviours">
                            Advanced properties</a></li>
                        <li><a href="#" 
                            onClick="loadContent('rollback/rollbackRecords.php?db=<?=HEURIST_DBNAME?>')"
                            title="Selectively roll back the data in the database to a specific date and time)">Rollback</a></li>                            
                    </ul>
                </div>

                <h3><a href="#">STRUCTURE: </a>
                    <span class="description">Design data types and data entry rules for the database</span></h3>
                <div class="adminSection">
                    <ul>
                        <li class="seperator"><a href="#" 
                            onClick="loadContent('structure/rectypes/manageRectypes.php?db=<?=HEURIST_DBNAME?>')"
                            title="Add new / modify existing record types and their use of globally defined fields">
                            Record types / fields</a></li>
                        <li><a href="#" 
                            onClick="loadContent('structure/import/selectDBForImport.php?db=<?=HEURIST_DBNAME?>')"
                            title="Selectively import structural elements from other Heurist databases">
                            Import structure</a></li>
                        <li><a href="#" 
                            onClick="loadContent('structure/rectypes/editRectypeConstraints.php?db=<?=HEURIST_DBNAME?>')"
                            title="Define constraints on the record types which can be related, and allowable relationship types">
                            Record constraints</a></li>
                        <li><a href="#" 
                            onClick="loadContent('structure/fields/manageDetailTypes.php?db=<?=HEURIST_DBNAME?>')"
                            title="Direct access to the global field definitions">
                            Manage field types</a></li>
                        <li><a href="#" 
                            onClick="loadContent('structure/terms/editTerms.php?db=<?=HEURIST_DBNAME?>')"
                            title="Define terms used for relationship types and for other enumerated fields">
                            Manage terms</a></li>
                            
                            
                         <li class="seperator">
                            <a href="#" 
                            onClick="loadContent('describe/listRectypeRelations.php?db=<?=HEURIST_DBNAME?>&action=simple')"
                            title="Display/print a listing of the record types and their simple fields (text, numeric etc.), including usage counts">
                            Simple fields schema</a></li>
                         <li><a href="#" 
                            onClick="loadContent('describe/listRectypeRelations.php?db=<?=HEURIST_DBNAME?>&action=relations')"
                            title="Display/print a listing of the record types and their pointers and relationships, including usage counts">
                            Relationships schema</a></li>
                         <li><a href="#" 
                            onClick="loadContent('describe/listRectypeRelations.php?db=<?=HEURIST_DBNAME?>&action=all')"
                            title="Display/print a listing of the record types and all fields, including usage counts">
                            Combined schema</a></li>  
                                                          
                        <li><a href="#" 
                            onClick="loadContent('describe/listRectypeDescriptions.php?db=<?=HEURIST_DBNAME?>')"
                            title="Display/print a formatted view of the database structure">
                            Structure summary</a></li>

                        <!-- Doesn't render correctly
                        <li><a href="#" onClick="loadContent('describe/getDBStructureAsXML.php?db=<?=HEURIST_DBNAME?>&amp;pretty=1')"
                        title="Lists the record type and field definitions in XML format">
                        Structure (XML)</a></li>
                        -->

                        <li class="seperator">
                            <a href="describe/getDBStructureAsXML.php?db=<?=HEURIST_DBNAME?>"
                            target="_blank" 
                            title="Lists the record type and field definitions in XML format">
                            Structure (XML) <img src="../common/images/external_link_16x16.gif" 
                            width="16" height="16" border="0" title="XML Structure"</a></li>
                        <li><a href="#" 
                            onClick="loadContent('describe/getDBStructureAsSQL.php?db=<?=HEURIST_DBNAME?>&amp;pretty=1')"
                            title="Lists the record type and field definitions in a computer-readable form">
                            Structure (deprecated)</a></li>
                        <li><a href="#" 
                            onClick="loadContent('describe/getDBStructureAsXForms.php?db=<?=HEURIST_DBNAME?>')"
                            title="Save the record types as XForms">
                            Structure (XForms)</a></li>
                        <li><a href="#" 
                            onClick="loadContent('structure/mimetypes/manageMimetypes.php?db=<?=HEURIST_DBNAME?>')"
                            title="Define the relationship between file extensions and mime type">
                            Mime types</a></li>
                        <li class="seperator"></li>
                    </ul>
                </div>


                <h3><a href="#">ACCESS: </a><span class="description">Manage database users and workgroup membership</span></h3>
                <div class="adminSection">
                    <ul>
                        <li class="seperator"><a href="#"
                            onClick="{loadContent('ugrps/manageGroups.php?db=<?=HEURIST_DBNAME?>');return false;}"
                            title="Assign users to workgroups and set their roles">
                            Manage workgroups</a></li>
                        <li><a href="#"
                            onClick="loadContent('ugrps/editGroupTags.php?db=<?=HEURIST_DBNAME?>')" 
                            title="Add and remove workgroup tags">Workgroup tags</a></li>
                        <li><a href="#"
                            onClick="loadContent('ugrps/quitGroupForSession.php?db=<?=HEURIST_DBNAME?>')" 
                            title="Quit a workgroup for this session to allow testing of non-group-members view">
                            Quit workgroup temporarily</a></li>
                        <li class="seperator"><a href="#" 
                            onClick="loadContent('ugrps/manageUsers.php?db=<?=HEURIST_DBNAME?>')"
                            title="Add and edit database users and usergroups, including authorization of new users">
                            Manage users</a></li>
                        <li><a href="#"
                            onClick="loadContent('ugrps/getUserFromDB.php?db=<?=HEURIST_DBNAME?>')"
                            title="Import users one at a time from another database on the system">
                            Import a user</a></li>
                    </ul>
                </div>


                <h3><a href="#">UTILITIES: </a><span class="description">Verify and fix database integrity, specialised tools</span></h3>
                <div class="adminSection">
                    <ul>
                        <!-- TITLE MASKS -->
                        <li class="seperator"><a href="#"
                            onClick="loadContent('verification/checkRectypeTitleMask.php?check=1&amp;db=<?=HEURIST_DBNAME?>')"
                            title="Check correctness of each Record Type's title mask with respect to field definitions.">
                            Check Title Masks</a></li>
                        <li><a href="#"
                            onClick="loadContent('verification/recalcTitlesAllRecords.php?db=<?=HEURIST_DBNAME?>')"
                            title="Rebuilds the constructed record titles listed in search results, for all records">
                            Rebuild Titles</a></li>
                        <!-- We also have function for specific records and rectypes</p> -->
                        <li><a href="#"
                            onClick="loadContent('verification/listDuplicateRecords.php?fuzziness=10&amp;db=<?=HEURIST_DBNAME?>')"
                            title="Fuzzy search to identify records which might contain duplicate data">
                            Find Duplicate Records</a></li>
                        <li><a href="../search/search.html?q=_BROKEN_&amp;w=all&amp;db=<?=HEURIST_DBNAME?>" target="_blank"
                            title="Show records with URLs which point to a non-existant or otherwise faulty address">
                            Find Broken URLs</a></li>

                        <!-- DATA QUALITY -->
                        <li class="seperator"><a href="#"
                            onClick="loadContent('verification/listRecordPointerErrors.php?db=<?=HEURIST_DBNAME?>')"
                            title="Find record pointer which point to an incorrect record type or to nothing at all">
                            Check for Invalid Pointers</a></li>
                        <li><a href="#"
                            onClick="loadContent('verification/listFieldTypeDefinitionErrors.php?db=<?=HEURIST_DBNAME?>')"
                            title="Find field types with invalid terms or pointer record types">
                            Check for Invalid Fields</a></li>
                        <li><a href="#"
                            onClick="loadContent('verification/checkXHTML.php?db=<?=HEURIST_DBNAME?>')"
                            title="Check the wysiwyg text fields in records/blog entries for structural errors">
                            Check Wysiwyg Texts</a></li>
                        <li><a href="#"
                            onClick="loadContent('verification/checkInvalidChars.php?db=<?=HEURIST_DBNAME?>')"
                            title="Check the wysiwyg text fields in records/blog entries for invalid characters">
                            Check for Invalid Characters</a></li>
                        <li><a href="#"
                            onClick="loadContent('verification/cleanInvalidChars.php?db=<?=HEURIST_DBNAME?>')"
                            title="Attempt to clean up invalid characters in the wysiwyg text fields">
                            Clean Invalid Characters</a></li>

                        <!-- INSTALLATION AND STATS -->
                        <li class="seperator"><a href="#"
                            onClick="loadContent('verification/verifyInstallation.php?db=<?=HEURIST_DBNAME?>')"
                            title="Verifies that all required JS libraries, other components and directories are in expected locations">
                            Verify Installation</a></li>
                        <li><a href="#"
                            onClick="loadContent('verification/rebuildLuceneIndices.php?db=<?=HEURIST_DBNAME?>')"
                            title="Rebuilds the Lucence indices used by Elastic Search, for all record types">Rebuild Lucene (W.I.P.)</a></li>
                        <li><a href="#"
                            onClick="loadContent('verification/removeDatabaseLocks.php?db=<?=HEURIST_DBNAME?>')"
                            title="Remove database locks - use ONLY if you are sure no-one else is accessing adminstrative functions">
                            Clear Database Locks</a></li>
                        <li><a href="#" onClick="loadContent('describe/dbStatistics.php?db=<?=HEURIST_DBNAME?>')"
                            title="Size and usage statistics for all Heurist databases on this server">Database usage statistics</a></li>
                        <li><a href="#"
                            onClick="loadContent('../import/direct/getRecordsFromDB.php?db=<?=HEURIST_DBNAME?>')"
                            title="Import records directly from one database to another, mapping record types, fields types and terms">
                            Database-to-database Transfer</a></li>

                        <!-- Section for specific maintenance functionality which will be removed later. 
                        Yes, could be run directly, but this makes them easily available-->
                        <!-- AO: remarked on 2013-02-18. This is one-off correction of db format in 2011
                        <li><a href="../import/direct/upgradeToNamedFiles.php?db=<?=HEURIST_DBNAME?>" target="_blank"
                        title="Update the old format bare-number associated file storage to new format path + name"
                        >Upgrade Associated Files Naming</a></li>
                        -->
                        <li class="seperator"><a href="#" onClick="loadContent('../export/publish/manageReports.html?db=<?=HEURIST_DBNAME?>')"
                            title="Add new / modify existing scheduled reports">Schedule Reports</a></li>
                    </ul>
                </div>


                <h3><a href="#">FAIMS & HuNI: </a><span class="description">
                Infrastructure projects - tablet data collection and data aggregation/federated search</span></h3>
                <div class="adminSection">
                    <ul>
                        <li class="seperator"><a href="#"
                            onClick="loadContent('../applications/faims/about.html?db=<?=HEURIST_DBNAME?>')"
                            title="Information about the FAIMS (Federated Archaeological Information Management System) project">
                            About FAIMS</a></li>

                        <li><a href="#"
                            onClick="loadContent('../applications/faims/exportFAIMS.php?db=<?=HEURIST_DBNAME?>')"
                            title="Create FAIMS module / tablet application structure from the current Heurist database structure.".
                            " No data is exported">
                            Create Module (no data)</a></li>
                            <!-- todo: not yet implemented
                            <li><a href="#"
                            onClick="loadContent('../applications/faims/exportFAIMSwithdata.php?db=<?=HEURIST_DBNAME?>')"
                            title="Export a FAIMS format tarball (module including data) from the current Heurist database">
                            Export Module (w. data)</a></li>
                            -->
                        <li><a href="#"
                            onClick="loadContent('../applications/faims/syncFAIMS.php?db=<?=HEURIST_DBNAME?>')"
                            title="Import structure and data into the current Heurist database from a FAIMS module tarball".
                            " or direct from FAIMS server database">
                            Import Module (w. data)</a></li>
                        <li><a href="#"
                            onClick="loadContent('../applications/faims/exportTDar.php?db=<?=HEURIST_DBNAME?>')"
                            title="Export the current database as tables, files and metadata directly into a specified tDAR repository">
                            Export to tDAR repository</a></li>

                        <!-- HuNI -->
                        <li class="seperator"><a href="#"
                            onClick="loadContent('../applications/huni/about.html?db=<?=HEURIST_DBNAME?>')"
                            title="Information about the HuNI (Humanities Networked Infrastructure) project">
                            About HuNI</a></li>
                        <li><a href="#"
                            onClick="loadContent('../export/xml/flathml.php?w=all&q=sortby:rt&a=1&depth=0&file=1&db=<?=HEURIST_DBNAME?>')"
                            title="Export data from the current Heurist database to a HuNI-harvestable package of XML files">
                            Export harvestable XML</a></li>

                    </ul>
                </div>
                <!--</div>-->
                <!-- end accordion -->
            </div>
        </div>
        <!-- end sidebar -->


        <div id="page">
            <div id="page-inner">
                <div class="contentframe">
                    <iframe width="100%" height="100%" id="adminFrame" name="adminFrame" frameborder="0" src="<?=$url?>"></iframe>
                </div>
            </div>
        </div>

    </body>
</html>