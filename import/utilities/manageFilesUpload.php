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
    * manageFilesUpload.php
    * Uploading of single/multiple/large data files to <dbname> directories, notably .../scratchspace
    * Intended primarily for uploading data files to be imported, but could also be used for uploading a bunch of images
    * Note that scratch directory should be marked inacessible to ensure dangerous files cannot be uploaded and then executed
    * TODO: address security concern above
    *
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
    * @copyright   (C) 2005-2013 University of Sydney
    * @link        http://Sydney.edu.au/Heurist
    * @version     3.1.0
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @param       includeUgrps=1 will output user and group information in addition to definitions
    * @param       approvedDefsOnly=1 will only output Reserved and Approved definitions
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    * @todo        write Heurist IDs back into FH XML files
    * @todo        update existing records from XML files which have changed
    * @todo        update XML files from Heurist records which have changed
    */

    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

    /* TODO: This function only works for individual files within PHP limit, we need to use bulk/segmented uploader
    Use jQuery File Upload plugin. By making use of Chunked file uploads (with chunks smaller than 4GB), 
    the potential file size is unlimited. 
    */ 
    require_once(dirname(__FILE__)."/../../records/files/uploadFile.php");

    if (! is_logged_in()) {
        header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
        return;
    }

    if (! is_admin()) { // TO DO: Change this to members of database managers
        print "<p>Only members of the Database Managers group may upload files</p></body></html>";
        return;
    }

?>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>File upload manager</title>
        <link rel=stylesheet href="../../common/css/global.css" media="all">
    </head>
    <body class="popup">

        <?php

            // TODO: Doi we need this for anything?
            mysql_connection_overwrite(DATABASE);
            if(mysql_error()) {
                die("Sorry, could not connect to the database (mysql_connection_overwrite error)");
            }

            //print "<h2>Import Files On Disk / FieldHelepr Manifests</h2>";
            print "<h2>FILE MANAGEMENT</h2>";
            print "This function allows members of the database managers group to upload multiple files and/or large files to the database scratch space<br>";
            print "and delete files from that space. Most commonly files will be uploaded prior to importing data from them or running the in situ import of images.<br>";
            print "The function is restricted to database owners/managers to reduce the risk of other users filling the database with unwanted material.<br>";
            print "</p>";


            // ----Visit #1 - CHECK MEDIA FOLDERS --------------------------------------------------------------------------------

            if(!array_key_exists('mode', $_REQUEST)) {

                // Find out which folders are allowable - the default scratch space plus any specified for FieldHelper indexing in Advanced Properties
                $query1 = "SELECT sys_MediaFolders, sys_MediaExtensions from sysIdentification where sys_ID=1";
                $res1 = mysql_query($query1);
                if (!$res1 || mysql_num_rows($res1) == 0) {
                    die ("<p><b>Sorry, unable to read the sysIdentification table from the current databsae. Possibly wrong database format, please consult Heurist team");
                }

                // Get the set of directories defined in Advanced Properties as FieldHelper indexing directories
                // These are the most likely location for bulk upload (of images) and restricting to these directories
                // avoids the risk of clobbering the system's required folders (since most people won't know what they're called)
                $row1 = $row = mysql_fetch_row($res1);
                $mediaFolders = $row1[0];
                $dirs = explode(';', $mediaFolders); // get an array of folders
                // add the scratch directory, which will be the default for upload of material for import
                array_push($dirs,'scratch');

                // The defined list of file extensions for FieldHelper indexing. 
                // For the moment keep this in as a restriction on file types which can be uploaded
                // Unlike indexing, we add the user-defined set to the default set
                $mediaExts = "jpg,jpeg,sid,png,gif,tif,tiff,bmp,rgb,doc,docx,odt,xsl,xslx,mp3,mp4,mpeg,avi,wmv,wmz,aif,aiff,mid,midi,wms,wmd,qt,evo,cda,wav,csv,tsv,tab,txt,rtf,xml,xsl,xslt,hml,kml,shp,htm,html,xhtml,ppt,pptx,zip,gzip,tar"; // default set to allow
                $mediaExts = $mediaExts.$row1[1];
                // TODO: we should eliminate any duplicate extensions which might have been added by the user

                if ($mediaFolders=="" || count($dirs) == 0) {
                    print ("<p><b>If you wish to upload files to a directory other than the scratch space, define the folders in <br />".
                        "Designer View > Database > Advanced Properties > Additional Folders for indexing.<br />You may also add additional file extensions.</b>");
                }else{
                    print "<p><b>Folders available for upload:&nbsp;&nbsp;&nbsp;</b> $mediaFolders<p>";
                    print "<p><b>Allowable extensions for upload:</b> $mediaExts<p>";
                }
                print  "<p><a href='../../admin/setup/dbproperties/editSysIdentificationAdvanced.php?db=".HEURIST_DBNAME."&popup=1'>".
                "Click here to set media/upload folders</a><p>";

                print "<h3> This function is under development (April 2014)</h2>";


                exit;
            } // Visit #1


            // ----visit #2 ----------------------------------------------------------------------------------------

            if(array_key_exists('mode', $_REQUEST) && $_REQUEST['mode']=='2'){

                /* TODO: REPLACE WITH WHATEVER IS NEEDED. DELETE SECTION IF NOT NEEDED
                $mediaFolders = $_REQUEST['media'];
                print "<form name='mappings' action='synchroniseWithFieldHelper.php' method='post'>";
                print "<input name='mode' value='3' type='hidden'>"; // calls the transfer function
                print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
                print "<input name='media' value='$mediaFolders' type='hidden'>";
                print "Ready to process media folders: <b>$mediaFolders</b><p>";
                print "<input type='submit' value='Import data'><p><hr>\n";
                */
                exit;
            }


        ?>
    </body>
</html>