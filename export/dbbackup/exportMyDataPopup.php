<?php

/**
* exportMyDataPopup.php: Set up the export of some or all data as an HML or zip file
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
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


define('MANAGER_REQUIRED', 1);   
define('PDIR','../../');  //need for proper path to js and css    

set_time_limit(0); //no limit


require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');
require_once(dirname(__FILE__).'/../../hsapi/utilities/utils_file.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_files.php');
require_once(dirname(__FILE__).'/../../external/php/Mysqldump.php');


$folder = HEURIST_FILESTORE_DIR.'backup/'.HEURIST_DBNAME;
$folder_sql = HEURIST_FILESTORE_DIR.'backup/'.HEURIST_DBNAME.'_sql';
$progress_flag = HEURIST_FILESTORE_DIR.'backup/inprogress.info';

$mode = @$_REQUEST['mode'];

// Download the dumped data as a zip file
if($mode=='2' && file_exists($folder.".zip") ){
    downloadFile('application/zip', $folder.".zip"); //see db_files.php
    exit();
}else if($mode=='3' && file_exists($folder_sql.".zip")){
    downloadFile('application/zip', $folder_sql.".zip", HEURIST_DBNAME.".zip");
    exit();
}

?>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Create data archive package</title>

        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-ui.js"></script>

        <!-- CSS -->
        <?php include dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php'; ?>
        
        <script type=text/javascript>
            $(document).ready(function() {
                $('input[type="submit"]').button();
                $('input[type="button"]').button();
            });
        </script>
    </head>
    <body class="popup ui-heurist-admin">
        
        <?php
        //<div class="banner"  style="padding-left:3px"><h2>Complete data archive package</h2></div>    

        $please_advise = "<br>Please consult with your system administrator for a resolution.";

        if(!$mode) {
            ?>

            <h3 class="ui-heurist-title">This function is available to database adminstrators only (therefore you are a database administrator!)</h3>
            <p>The data will be exported as a fully self-documenting HML (Heurist XML) file, as a complete MySQL SQL data dump, 
            as textual and wordprocessor descriptions of the structure of Heurist and of your database, and as a directory of 
            attached files (image files, video, XML, maps, documents etc.) which have been uploaded or indexed in situ.
            </p>
            <p>The MySQL dump will contain the complete database which can be reloaded on any up-to-date MySQL database server.
            </p>
            <p>The output of the process will be made available as a link to a downloadable zip file
            <br>but is also available to the system adminstrator as a file in the backup directory in the database.
            </p>
            <h3 class="ui-heurist-title">Warning</h3>
            <p>Zipping databases with large numbers of images or very large files such as high 
            <br>resolution maps or video may bog down the server and the zip file may be too big to download.
            <br>In that case you may need to ask your sysadmin to give you the files separately on a USB drive.</p>
            
            <p>Attached files may be omitted by unchecking the first checkbox. 
            <br>This may also be useful for databases with lots of attached files which are already backed up elsewhere.
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
                <div class="input-row" style="padding-top:10px">
                    <label>
                        <input type="checkbox" name="includeresources" value="1" checked>
                        Include attached (uploaded) files eg. images (essential for full database archive).
                    </label> 
                </div>

                <div class="input-row">
                    <label title="Adds fully self-documenting HML (Heurist XML) file">
                        <input type="checkbox" name="include_hml" value="1" checked>
                        Include HML (not required for transfer to a new server, but recommended for long-term archive)
                    </label>
                </div>
                
                <div class="input-row" style="display:none;">
                    <label 
                        title="Adds documents describing Heurist structure and data formats - check this box if the output is for long-term archiving">
                        <input type="checkbox" name="include_docs" value="1" checked>
                        Include background documentation for archiving
                    </label>
                </div>
                
                <div class="input-row" style="display: none;">
                    <label>
                        <input type="checkbox" name="allrecs" value="1" checked>
                        Include resources from other users (everything to which I have access)
                    </label>
                </div>

                <div id="buttons" class="actionButtons" style="padding-top:10px;text-align:left">
                    <input type="button" value="Export" style="margin-right: 20px;" class="ui-button-action"
onClick="{ $('<div>Preparing archive file for download...</div>').addClass('coverall-div').css({'zIndex':60000,'padding':'30px 0 0 30px','font-size':'1.2em','opacity':0.8,'color':'white'}).appendTo('body'); document.getElementById('buttons').style.visibility = 'hidden';  document.forms[0].submit(); }">
<?php if(@$_REQUEST['inframe']!=1) { ?>                    
                    <input type="button" value="Cancel" onClick="window.close();">
<?php } ?>                    
                </div>
            </form>
            <?php

        }else{
            
            //flag that backup in progress
            $action = 'exportDB';
            if(!isActionInProgress($action, 2)){
                exit("It appears that backup operation has been started already. Please try this function later");        
            }
            
            /*
            if(file_exists($progress_flag)){
               print 'It appears that backup opearation has been started already. Please try this function later'; 
               if(file_exists($progress_flag)) unlink($progress_flag);
               exit();
            }
            $fp = fopen($progress_flag,'w');
            fwrite($fp, '1');
            fclose($fp);            
            */
            
            $separate_sql_zip = true;

            // 
            if(file_exists($folder)){
                echo_flush2("<br>Clear folder ".$folder."<br>");
                //clean folder
                $res = folderDelete2($folder, true);
                if(!$res){
                    print 'It appears that backup opearation has been started already. Please try this function later'; 
                    if(file_exists($progress_flag)) unlink($progress_flag);
                    exit();
                }
            }
            if (!folderCreate($folder, true)) {
                if(file_exists($progress_flag)) unlink($progress_flag);
                
                $message = 'Failed to create folder '.$folder.'<br/> in which to create the backup. Please consult your sysadmin.';                
                report_message($message, true);
                exit();
            }

            // Just SQL dump
            if(file_exists($folder_sql)){
                $res = folderDelete2($folder_sql, true);
                if(!$res){
                    print 'It appears that backup opearation has been started already. Please try this function later';
                    if(file_exists($progress_flag)) unlink($progress_flag);
                    exit();
                }
            }
            if(!folderCreate($folder_sql, true)){
                $separate_sql_zip = false; // hide option
            }

            ?>
            <div id="divProgress" style="cursor:wait;width:50%;height:100%;margin:0 auto;position:relative;z-index:999999;background:url(../../hclient/assets/loading-animation-white.gif)  no-repeat center center"></div>
            <div style="position:absolute;top:70;left:10;right:20">
            <?php
            
            $folders_to_copy = null;
            
            //copy resource folders
            if(@$_REQUEST['include_docs']=='1'){
                $folders_to_copy = folderSubs(HEURIST_FILESTORE_DIR, array('backup', 'scratch', 'file_uploads', 'filethumbs'));
                
                //limited set
                //$folders_to_copy = $system->getSystemFolders( 1 );

                echo_flush2("<br><br>Exporting system folders<br>");
            }
            
            if(@$_REQUEST['includeresources']=='1'){
                if($folders_to_copy==null) $folders_to_copy = array();    
                $folders_to_copy[] = HEURIST_FILES_DIR;
                $folders_to_copy[] = HEURIST_THUMB_DIR;
                
                $copy_files_in_root = true; //copy all files within database folder
            }else{
                $copy_files_in_root = false;
            }
            if($folders_to_copy==null){
                $folders_to_copy = array('no copy folders');   
            }
            
                
           if(@$_REQUEST['include_docs']=='1' || @$_REQUEST['includeresources']=='1'){     
               folderRecurseCopy( HEURIST_FILESTORE_DIR, $folder, $folders_to_copy, null, $copy_files_in_root);
           }
            
            if(@$_REQUEST['include_docs']=='1'){// 2016-10-25  
                echo_flush2('Copy context_help folder<br>');                
                folderRecurseCopy( HEURIST_DIR.'context_help/', $folder.'/context_help/', null);
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

            echo_flush2("Exporting database as HML (Heurist Markup Language = XML)<br>(may take several minutes for large databases)<br>");

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

            echo_flush2("Exporting database definitions as readable text<br>");

            $url = HEURIST_BASE_URL . "admin/describe/getDBStructureAsSQL.php?db=".HEURIST_DBNAME."&pretty=1";
            saveURLasFile($url, $folder."/Database_Structure.txt"); //save to HEURIST_FILESTORE_DIR.'backup/'.HEURIST_DBNAME

            echo_flush2("Exporting database definitions as XML<br>");
            
            $url = HEURIST_BASE_URL . "admin/describe/getDBStructureAsXML.php?db=".HEURIST_DBNAME;
            saveURLasFile($url, $folder."/Database_Structure.xml"); //save to HEURIST_FILESTORE_DIR.'backup/'.HEURIST_DBNAME


            if($system->is_admin()){
                // Do an SQL dump of the whole database
                echo_flush2("Exporting SQL dump of the whole database (several minutes for large databases)<br>");

                try{
                    $dump = new Mysqldump( HEURIST_DBNAME_FULL, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD, HEURIST_DBSERVER_NAME, 'mysql', 
                    array('skip-triggers' => true,  'add-drop-trigger' => false));
/*                    
                            array(
                            'add-drop-table' => true,
                            'single-transaction' => true,
                            'skip-triggers' => false,
                            'add-drop-trigger' => true,
                            'databases' => true,
                            'add-drop-database' => true));
*/                    
                    $dump->start($folder."/".HEURIST_DBNAME."_MySQL_Database_Dump.sql");

                    if($separate_sql_zip){ // copy sql dump to separate directory
                        $separate_sql_zip = fileCopy($folder."/".HEURIST_DBNAME."_MySQL_Database_Dump.sql", $folder_sql."/".HEURIST_DBNAME."_MySQL_Database_Dump.sql");
                    }
                } catch (Exception $e) {
                    if(file_exists($progress_flag)) unlink($progress_flag);
                    print '</div><script>document.getElementById("divProgress").style.display="none";</script>';
                    die ("Sorry, unable to generate MySQL database dump.".$e->getMessage().$please_advise);
                }

            }

            // remove old mysql dump - specifically the ones named HEURIST_DBNAME_FULL.sql
            if(file_exists($folder.'/'.HEURIST_DBNAME_FULL.'.sql')) unlink($folder.'/'.HEURIST_DBNAME_FULL.'.sql');

            // Create a zipfile of the definitions and data which have been dumped to disk
            $destination = $folder.'.zip'; // Complete archive
            if(file_exists($destination)) unlink($destination);
            
            $res = createZipArchive($folder, null, $destination, true);

            $res_sql = false;
            if($separate_sql_zip){

                $destination_sql = $folder.'_sql.zip'; // SQL dump only
                if(file_exists($destination_sql)) unlink($destination_sql);

                $res_sql = createZipArchive($folder.'_sql', null, $destination_sql, true);
            }
            
            /* command line version
            $cmdline = "zip -r ".$folder.".zip *"; //archive everything within folder, keep folder strcuture
            $res1 = 0;
            $output1 = exec($cmdline, $output, $res1);
            if ($res1 != 0 ) {
                print  ("<p class='error'>Exec error code $res1: Unable to create zip file $folder.zip>&nbsp;$please_advise<br>");
                print  print_r($output,true).'</p>';
            */
            if(!$res){
                print "Directory may be non-writeable or zip function is not installed on server (error code 127) - please consult system adminstrator";
            } else {
?>                
<p>Your data have been backed up in <?php echo $folder;?></p>
<br><br><div class='lbl_form'></div>
    <a href="exportMyDataPopup.php/<?php echo HEURIST_DBNAME;?>.zip?db=<?php echo HEURIST_DBNAME;?>&mode=2"
        target="_blank" style="color:blue; font-size:1.2em">Click here to download your data as a zip archive</a>

<?php if($res_sql){ ?>
    <br><br>
    <a href="exportMyDataPopup.php/<?php echo HEURIST_DBNAME;?>.zip?db=<?php echo HEURIST_DBNAME;?>&mode=3"
        target="_blank" style="color:blue; font-size:1.2em">Click here to download the SQL zip file only</a> 
    <span class="heurist-helper1">(for db transfer on tiered servers)</span>
<?php } ?>

<span class="heurist-helper1">
<br><br>Note: If this file fails to download properly (eg. "Failed … file incomplete") the file is too large to download. Please ask your system administrator (<?php echo HEURIST_MAIL_TO_ADMIN; ?>) to send it to you via a large file transfer service</span>        
<?php                
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

<?php
function report_message($message, $is_error){
?>
        <div class="ui-corner-all ui-widget-content" style="text-align:left; width:70%; min-width:220px; margin:0px auto; padding: 0.5em;">

            <div class="logo" style="background-color:#2e3e50;width:100%"></div>

            <div class="<?php echo ($is_error)?'ui-state-error':''; ?>" 
                style="width:90%;margin:auto;margin-top:10px;padding:10px;">
                <span class="ui-icon <?php echo ($is_error)?'ui-icon-alert':'ui-icon-info'; ?>" 
                      style="float: left; margin-right:.3em;font-weight:bold"></span>
                <?php echo $message;?>
            </div>
        </div>
    </body>    
</html>
<?php
}
?>