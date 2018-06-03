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
                        <a  href="admin/describe/getDBStructureAsXML.php" name="auto-popup"  class="h3link"
                            target="_blank"
                            title="Lists the record type and field definitions in XML format (HML - Heurist Markup Language)">
                           Structure (XML)
                            <img src="<?php echo PDIR;?>common/images/external_link_16x16.gif"
                                    width="12" height="12" border="0">
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
                                <img src="<?php echo PDIR;?>common/images/external_link_16x16.gif"
                                    width="12" height="12" border="0" alt="Search showing bookmarks without tags">
                        </a>
                    </li>
                    <li>
                        <a href="" name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            title="">
                            
                        </a>
                    </li>
                    <li>
                        <a href="" name="auto-popup" class="h3link"
                            onClick="{return false;}"
                            title="">
                            
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
