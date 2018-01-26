<?php

/**
* exportMyDataPopup.php: Set up the export of some or all data as an HML or zip file
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.1.5
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/php/dbUtils.php');
require_once(dirname(__FILE__).'/../../search/parseQueryToSQL.php');
require_once(dirname(__FILE__).'/../../records/files/fileUtils.php');

require_once(dirname(__FILE__).'/../../external/php/Mysqldump.php');

if (! is_logged_in()) {
    header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db=' . HEURIST_DBNAME);
    return;
}

if (isForAdminOnly("to carry out a database content dump - please ask your database owner to do this")){
    return;
}

$username = get_user_username();
$folder = HEURIST_FILESTORE_DIR.'backup/'.HEURIST_DBNAME;
$progress_flag = HEURIST_FILESTORE_DIR.'backup/inprogress.info';

$mode = @$_REQUEST['mode'];

// Download the dumped data as a zip file
if($mode=='2' && file_exists($folder.".zip") ){
    downloadFile('application/zip', $folder.".zip");
    exit();
}

?>

<html>
    <head>
        <title>Export My Data</title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel=stylesheet href="../../common/css/global.css">
        <link rel=stylesheet href="../../common/css/edit.css">
        <link rel=stylesheet href="../../common/css/admin.css">
        <style>
            .input-row, .input-row .input-header-cell, .input-row div.input-cell {vertical-align:middle}
        </style>

    </head>

    <body class="popup" style="padding-top:30px">

        <div class="banner"  style="padding-left:3px"><h2>Complete data archive package</h2></div>    
        <?php

        $please_advise = "<br>Please consult with your system administrator for a resolution.";

        if(!$mode) {
            ?>

            <p><b>This function is available to database adminstrators only (therefore you are a database administrator!).</b>
            </p>
            <p>The data will be exported as a fully self-documenting HML (Heurist XML) file, as a complete MySQL SQL data dump, 
            as textual and wordprocessor descriptions of the structure of Heurist and of your database, and as a directory of 
            attached files (image files, video, XML, maps, documents etc.) which have been uploaded or indexed in situ.
            </p>
            <p>The MySQL dump will contain the complete database which can be reloaded on any up-to-date MySQL database server.
            </p>
            <p>The output of the process will be made available as a link to a downloadable zip file.
            </p>
            <h3>Warning</h3>
            <p>Zipping databases and including lots of images or video may bog down the server and the file may not download
            satisfactorily - in that case it may be better to ask your sysadmin to give you the files separately on a USB drive.</p>
            
            <p>Attached files may be omitted by unchecking the first checkbox. This may also be useful for databases with 
            lots of attached files which are already backed up elsewhere.
            </p>


            <!-- TODO: Need to also include record type icons, report formats etc. This was originally developed to allow individual users to save their
            data out of the sandpit. With shift to individual and small project databases, it is now best to regard this as a database-administrators-only
            function, and to simply zip up the entire database upload directory, dump entire content as HML, and thus maximise reliability and
            minimise maintenance requirements -->

            <form name='f1' action='exportMyDataPopup.php' method='get'>
            
                <input name='db' value='<?=HEURIST_DBNAME?>' type='hidden'>
                <input name='mode' value='1' type='hidden'>
<?php if(@$_REQUEST['inframe']==1) { ?>                    
                <input name='inframe' value='1' type='hidden'>
<?php } ?>                
                <div class="input-row">
                    <div class="input-header-cell">Include attached (uploaded) files eg. images</div> <!--  (output everything as one ZIP file) -->
                    <div class="input-cell"><input type="checkbox" name="includeresources" value="1" checked></div>
                </div>

                <div class="input-row">
                    <div class="input-header-cell" 
                        title="Adds fully self-documenting HML (Heurist XML) file">
                        Include HML</div>
                    <div class="input-cell"><input type="checkbox" name="include_hml" value="1" checked></div>
                </div>
                
                <div class="input-row">
                    <div class="input-header-cell" 
                        title="Adds documents describing Heurist structure and data formats - check this box if the output is for long-term archiving">
                        Include background documentation for archiving</div>
                    <div class="input-cell"><input type="checkbox" name="include_docs" value="1"></div>
                </div>
                
                <div class="input-row" style="display: none;">
                    <div class="input-header-cell">Include resources from other users (everything to which I have access)</div>
                    <div class="input-cell"><input type="checkbox" name="allrecs" value="1" checked></div>
                </div>

                <div id="buttons" class="actionButtons">
                    <input type="submit" value="Export" style="margin-right: 20px; padding-left:5px; padding-right:5px;"
                        onClick="function(event){ event.target.style.visibility = 'hidden'; }">
<?php if(@$_REQUEST['inframe']!=1) { ?>                    
                    <input type="button" value="Cancel"  style="margin-right: 200px; padding-left:5px; padding-right:5px;" onClick="window.close();">
<?php } ?>                    
                </div>
            </form>
            <?php

        }else{
            
            //flag that backup in progress
            if(file_exists($progress_flag)){
               print 'It appears that backup opearation has been started already. Please try this function later'; 
               if(file_exists($progress_flag)) unlink($progress_flag);
               exit();
            }
            
            
            $fp = fopen($progress_flag,'w');
            fwrite($fp, '1');
            fclose($fp);            
            
            
            set_time_limit(0); //no limit

            
            
            if(file_exists($folder)){
                echo_flush("<br>Clear folder ".$folder."<br>");
                //clean folder
                $res = delFolderTree($folder, true);
                if(!$res){
                    print 'It appears that backup opearation has been started already. Please try this function later'; 
                    if(file_exists($progress_flag)) unlink($progress_flag);
                    exit();
                }
            }
            if (!mkdir($folder, 0777, true)) {
                if(file_exists($progress_flag)) unlink($progress_flag);
                die('Failed to create folder '.$folder.'<br/> in which to create the backup. Please consult your sysadmin.');
            }

            ?>
            <div id="divProgress" style="cursor:wait;width:50%;height:100%;margin:0 auto;position:relative;z-index:999999;background:url(../../hclient/assets/loading-animation-white.gif)  no-repeat center center"></div>
            <div style="position:absolute;top:30;left:10;right:20">
            <?php
            
            
            //copy resource folders
            if(@$_REQUEST['include_docs']=='1'){
                $system_folders = array(
                    HEURIST_ICON_DIR,
                    HEURIST_FILESTORE_DIR.'documentation_and_templates/',
                    //HEURIST_FILESTORE_DIR."generated-reports/",
                    HEURIST_FILESTORE_DIR."settings/",
                    HEURIST_FILESTORE_DIR.'term-images/',
                    HEURIST_SMARTY_TEMPLATES_DIR,
                    HEURIST_XSL_TEMPLATES_DIR);
                // 2016-10-25  Don't copy generated-reports, html-output, hml-output, backup, filethumbs 
                // - these are derivative products, which don't therefore need to be archived    
                //if(defined('HEURIST_HTML_DIR')) array_push($system_folders, HEURIST_HTML_DIR);
                //if(defined('HEURIST_HML_DIR')) array_push($system_folders, HEURIST_HML_DIR);

                echo_flush("<br><br>Exporting system folders<br>");
                //echo_flush('<br>'.HEURIST_FILESTORE_DIR.' to '.$folder.'<br>');             
                
                //recurse_copy( HEURIST_FILESTORE_DIR, $folder, $system_folders);
            }else{
                $system_folders = array('no copy folders');
            }
            
            if(@$_REQUEST['includeresources']=='1'){
                $copy_files_in_root = true; //copy all files within database folder
            }else{
                $copy_files_in_root = false;
            }
                
           if(@$_REQUEST['include_docs']=='1' || @$_REQUEST['includeresources']=='1'){     
               recurse_copy( HEURIST_FILESTORE_DIR, $folder, $system_folders, null, $copy_files_in_root);
           }
            
            if(@$_REQUEST['include_docs']=='1'){// 2016-10-25  
                echo_flush('Copy context_help folder<br>');                
                recurse_copy( HEURIST_DIR.'context_help/', $folder.'/context_help/', null);
            }
            

            if(@$_REQUEST['include_hml']=='1'){
            
            //load hml output into string file and save it
            if(@$_REQUEST['allrecs']!="1"){
                $userid = get_user_id();
                $q = "owner:$userid"; //user:$userid OR
                $_REQUEST['depth'] = '5';
            }else{
                $q = "sortby:-m";
                $_REQUEST['depth'] = '0';
            }


            $_REQUEST['w'] = 'all';
            $_REQUEST['a'] = '1';
            $_REQUEST['q'] = $q;
            $_REQUEST['rev'] = 'no'; //do not include reverse pointers
            $_REQUEST['filename'] = $folder."/".HEURIST_DBNAME.".xml";

            echo_flush("Exporting database as HML (Heurist Markup Language = XML)<br>(may take some time for large databases)<br>");

            $to_include = dirname(__FILE__).'/../../export/xml/flathml.php';
            if (is_file($to_include)) {
                include $to_include;
            }
            
            /* OLD WAY. It works but leads to memory overflow for large database
            $content = "";
            
            if (is_file($to_include)) {
                ob_start();
                include $to_include;
                $content = ob_get_contents();
                ob_end_clean();
            }
            
            $file = fopen ($folder."/".HEURIST_DBNAME.".xml", "w");
            if(!$file){
                die("Can't write ".HEURIST_DBNAME."xml file. Please ask sysadmin to check permissions");
            }
            fwrite($file, $content);
            fclose ($file);
            */
            }//export HML
            
            // Export database definitions as readable text

            echo_flush("Exporting database definitions as readable text<br>");

            $url = HEURIST_BASE_URL . "admin/describe/getDBStructureAsSQL.php?db=".HEURIST_DBNAME."&pretty=1";
            saveURLasFile($url, $folder."/Database_Structure.txt");

            echo_flush("Exporting database definitions as XML<br>");
            
            $url = HEURIST_BASE_URL . "admin/describe/getDBStructureAsXML.php?db=".HEURIST_DBNAME;
            saveURLasFile($url, $folder."/Database_Structure.xml");


            if(is_admin()){
                // Do an SQL dump of the whole database
                echo_flush("Exporting SQL dump of the whole database<br>");

                try{
                    $dump = new Mysqldump( DATABASE, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD, HEURIST_DBSERVER_NAME, 'mysql', array('skip-triggers' => true,  'add-drop-trigger' => false));
                    $dump->start($folder."/".HEURIST_DBNAME."_MySQL_Database_Dump.sql");
                } catch (Exception $e) {
                    if(file_exists($progress_flag)) unlink($progress_flag);
                    print '</div><script>document.getElementById("divProgress").style.display="none";</script>';
                    die ("<h2>Error</h2>Unable to generate MySQL database dump.".$e->getMessage().$please_advise);
                }

            }

            //this coode not used anymore - we copy entire content of file_uploads
            if(false && $_REQUEST['includeresources']){
                echo_flush("Exporting resources (indexed/uploaded files)<br>");

                $squery = "select rec_ID, ulf_ID, ulf_FilePath, ulf_FileName, ulf_OrigFileName, ulf_MimeExt ";
                $ourwhere = " and (dtl_RecID=rec_ID) and (ulf_ID = dtl_UploadedFileID) ";
                $detTable = ", recDetails, recUploadedFiles ";
                $params = array();
                $params["q"] = $q;

                $query = prepareQuery($params, $squery, BOTH, $detTable, $ourwhere);

                $mysqli = mysqli_connection_overwrite(DATABASE);

                $res = $mysqli->query($query);
                if (!$res){
                    print "<p class='error'>Failed to obtain list of file resources from MySQL database.$please_advise</p>";
                }else{
                    //loop for records/details
                    while ($row = $res->fetch_row()) {  //$row = $res->fetch_assoc()) {
                        $filename_orig = $row[4];
                        $filename_insys = null;
                        
                        if(@$row[2] || @$row[3]){
                            $filename_insys = @$row[2].@$row[3];
                            $filename_insys = resolveFilePath($filename_insys);
                        }
                        
                        if($filename_insys && $row[1] && $filename_orig){

                            if(file_exists($filename_insys)){
                                //get relative path
                                $imgfolder = $folder.'/resources/';
                                if(!file_exists($imgfolder) && !mkdir($imgfolder, 0777, true)){
                                    print "<p class='error'>'Failed to create folder for file resources: ".$imgfolder."$please_advise</p>";
                                    break;
                                }else{
                                    $filename = $imgfolder.$filename_orig;
                                    //copy file
                                    copy($filename_insys, $filename);
                                }

                            }else{
                                print "<p class='error'>File not found: ".$filename_insys.
                                    "<br>Name indicated includes relative path from database file store directory".
                                    "$please_advise</p>";
                            }

                        }
                    }
                }

            }

            // Create a zipfile of the definitions and data which have been dumped to disk

            if(file_exists($folder.".zip")) unlink($folder.".zip");
            chdir($folder); 
            $cmdline = "zip -r ".$folder.".zip *"; //archive everything within folder, keep folder strcuture

            $res1 = 0;
            $output1 = exec($cmdline, $output, $res1);
            if ($res1 != 0 ) {
                print  ("<p class='error'>Exec error code $res1: Unable to create zip file $folder.zip>&nbsp;$please_advise<br>");
                print  print_r($output,true);
                print  "</p> Directory may be non-writeable or zip function is not installed on server (error code 127) - please consult system adminstrator.";
                print "Your data have been backed up in ".$folder;
                print "<br><br>If you are unable to download from the link below, ask your system administrator to send you the content of this folder";
            } else {
                print "<br><br><div class='lbl_form'></div>".
                "<a href='exportMyDataPopup.php/".HEURIST_DBNAME.".zip?db=".HEURIST_DBNAME."&mode=2".
                "' target='_blank' style='color:blue; font-size:1.2em'>Click here to download your data as a zip archive</a>";
            }

            print '</div><script>document.getElementById("divProgress").style.display="none";</script>';
            if(file_exists($progress_flag)) unlink($progress_flag);
        }

        /*
        function get_include_contents($filename) {
        if (is_file($filename)) {
        ob_start();
        include $filename;
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
        }
        return false;
        }
        */
        ?>

    </body>
</html>

