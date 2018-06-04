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
<script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/tabmenus/tabMenu.js"></script>

<script type="text/javascript">
    var editing;

    // Callback function on initialization
    function onPageInit(success){
        if(success){
            var menu = new hTabMenu();
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
                    <li><a href="setup/dboperations/cloneDatabase.php"  name="auto-popup" class="h3link"
                        title="Clones an identical database from the currrent database with all data, users, attached files, templates etc.">
                        Clone database</a>
                    </li>
                    

                    <li><a href="setup/dboperations/deleteCurrentDB.php" name="auto-popup" class="h3link"
                        onClick="{return false;}"
                        title="Delete the current database completely - cannot be undone, although data is copied to a backup which could be reloaded by a system administrator">
                        Delete entire database</a>
                    </li>
                    
                    <li><a href="admin/setup/dboperations/clearCurrentDB.php" name="auto-popup" class="h3link"
                        onClick="{return false;}"
                        title="Clear all data (records, values, attached files) from the current database. Database structure - record types, fields, terms, constraints - is unaffected">
                        Delete all records</a>
                    </li>
                    
                    <li class="admin-only">
                        <a href="admin/rollback/rollbackRecords.php" name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            title="Selectively roll back the data in the database to a specific date and time">
                         Rollback</a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="accordion_pnl">
            <h3 id="divStructure"><span class="ui-icon ui-iconalign ui-icon-structure"></span>STRUCTURE</h3>
            <div>
                <ul>
                    <!-- database name is appended automatically by auto-popup -->

                    <li style="display:none;">
                        <a href="common/html/msgWelcomeAdmin.html" name="auto-popup" class="h3link active-link"
                            onClick="{return false;}"
                            title="">
                            Welcome</a>
                    </li>
                    
                    <li>
                        <a href="admin/structure/fields/manageDetailTypes.php" name="auto-popup" class="h3link"
                            onClick="{return false;}" data-logaction="stManage"
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
                        <a  href="<?=HEURIST_BASE_URL?>admin/describe/getDBStructureAsXML.php?db=<?=HEURIST_DBNAME?>"
                            target="_blank"
                            title="Lists the record type and field definitions in XML format (HML - Heurist Markup Language)">
                           Structure (XML)
                           </a>
                    </li>

                    <li>
                        <a href="admin/describe/getDBStructureAsSQL.php?pretty=1"  name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            title="Lists the record type and field definitions in an SQL-related computer-readable form (deprecated 2014)">
                            Structure (SQL)</a>
                    </li>
                    
                    <li><a href="#" id="menulink-mimetypes"
                        data-nologin="1" 
                        title="Define the relationship between file extension and mime type for uploaded and externally referenced files">
                        Define mime types</a>
                    </li>

                </ul>
            </div>
        </div>

        <div class="accordion_pnl">
            <h3><span class="ui-icon ui-iconalign ui-icon-gears"></span>UTILITIES</h3>
            <div>
                <ul>
                
                    <li>
                        <a href="admin/verification/listDuplicateRecords.php?fuzziness=10" name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            title="Fuzzy search to identify records which might contain duplicate data">
                            Find duplicate records
                        </a>
                    </li>
                    <li>
                        <a href="admin/verification/rebuildLuceneIndices.php" name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            title="Rebuilds the Lucene indices used by Elastic Search, for all record types - no result if Elastic server not specified in configuration">
                            Rebuild Lucene index
                        </a>
                    </li>
                    <li>
                        <a href="hserver/utilities/recreateRecLinks.php" name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            title="Rebuild the cache of pointers and relationship records, which is used to speed search/filter actions">
                            Rebuild relationships cache
                        </a>
                    </li>
                    <li>
                        <a href="admin/verification/checkRectypeTitleMask.php?check=1" name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            title="Check correctness of each Record Type\'s title mask with respect to field definitions">
                            Verify title masks
                        </a>
                    </li>
                    <li>
                        <a href="admin/verification/checkXHTML.php" name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            title="Check the wysiwyg text fields in records/blog entries for structural errors">
                            Verify wysiwyg texts
                        </a>
                    </li>
                    <li>
                        <a href="verification/checkInvalidChars.php" name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            title="Check the text fields in records entries for invalid characters">
                            Fix invalid characters
                        </a>
                    </li>
                    <li>
                        <a href="admin/verification/listUploadedFilesErrors.php" name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            title="Find errors in database uploaded files">
                            Verify uploaded files 
                        </a>
                    </li>
                    <li>
                        <a href="admin/verification/verifyConceptCodes.php" name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            title="Find duplications of concept codes in all databases">
                            Verify concept codes
                        </a>
                    </li>
                    <li>
                        <a href="<?=HEURIST_BASE_URL?>?w=bookmark&amp;q=-tag&amp;label=Bookmarks without tags&amp;db=<?=HEURIST_DBNAME?>" target="_blank"
                            title="Show bookmarked records which you have not tagged">
                             Bookmarks w/o tags
                        </a>
                    </li>
                    <li>
                        <a href="<?=HEURIST_BASE_URL?>?q=_BROKEN_&amp;w=all&amp;db=<?=HEURIST_DBNAME?>" target="_blank"
                            title="Show records with URLs which point to a non-existant or otherwise faulty address - this only works if nightly verification is turned ON in Database > Properties">
                             Broken URLs
                        </a>
                    </li>
                    <li>
                        <a href="<?=HEURIST_BASE_URL?>?q=_NOTLINKED_&amp;w=all&amp;db=<?=HEURIST_DBNAME?>" target="_blank"
                            title="Show records with no outgoing or incoming pointers (includes records without any relationships)">
                             Records without links
                        </a>
                    </li>
                    
                    <li>
                        <a href="admin/verification/verifyInstallation.php" name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            title="Verifies that all required JS libraries, other components and directories are in expected locations">
                            Verify installation
                        </a>
                    </li>
                    
                    <li>
                        <a href="admin/verification/removeDatabaseLocks.php" name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            title="Remove database locks - use ONLY if you are sure no-one else is accessing adminstrative functions">
                            Clear database locks
                        </a>
                    </li>
                    <li>
                        <a href="admin/describe/dbStatistics.php" name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            title="Size and usage statistics for all Heurist databases on this server">
                           Database usage statistics (all dbs - slow) 
                        </a>
                    </li>
                    <li>
                        <a href="admin/verification/fileLinkingError.php" name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            title="Find uploaded file records which point at non-existent files for all Heurist databases on this server">
                            File linking errors
                        </a>
                    </li>
                    <li>
                        <!-- import/direct/getRecordsFromDB.php -->
                        <a href="#" name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            style="text-decoration:line-through"
                            title="Import records directly from one database to another, mapping record types, fields types and terms">
                            Inter-database transfer
                        </a>
                    </li>
                </ul>
            </div>
        </div>
            
        <div class="accordion_pnl">
            <h3><span class="ui-icon ui-iconalign ui-icon-gears"></span>FAIMS</h3>
            <div>
                <ul>
                
                    <li>
                        <!-- applications/faims/syncFAIMS.php -->
                        <a href="#" name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            style="text-decoration:line-through"
                            title="Import structure and data into the current Heurist database from a FAIMS module tarball or direct from FAIMS server database">
                            Import module + data
                        </a>
                    </li>
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
