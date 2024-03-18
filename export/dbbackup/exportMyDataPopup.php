<?php

/**
* exportMyDataPopup.php: Set up the export of some or all data as an HML or zip file
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/



define('MANAGER_REQUIRED', 1);   
define('PDIR','../../');  //need for proper path to js and css    

set_time_limit(0); //no limit


require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
require_once dirname(__FILE__).'/../../hserv/utilities/uFile.php';
require_once dirname(__FILE__).'/../../hserv/utilities/uArchive.php';
require_once dirname(__FILE__).'/../../hserv/records/search/recordFile.php';
require_once dirname(__FILE__).'/../../hserv/utilities/dbUtils.php';


define('FOLDER_BACKUP', HEURIST_FILESTORE_DIR.'backup/'.HEURIST_DBNAME);
define('FOLDER_SQL_BACKUP', HEURIST_FILESTORE_DIR.'backup/'.HEURIST_DBNAME.'_sql');
define('FOLDER_HML_BACKUP', HEURIST_FILESTORE_DIR.'backup/'.HEURIST_DBNAME.'_hml');

$mode = @$_REQUEST['mode']; // mode=2 - entire archived folder,  mode=3 - sql dump only, mode=4 - cleanup backup folder, mode=5 - hml file only
$format = array_key_exists('is_zip', $_REQUEST) && $_REQUEST['is_zip'] == 1 ? 'zip' : 'tar';
$mime = $format == 'tar' ? 'application/x-bzip2' : 'application/zip';
$is_repository = array_key_exists('repository', $_REQUEST);

// Download the dumped data as a zip file
if($mode>1){
    
    if($format == 'tar'){
        $format = 'tar.bz2';
    }

    if($mode=='2' && file_exists(FOLDER_BACKUP.'.'.$format) ){ //archived entire folder
        downloadFile($mime, FOLDER_BACKUP.'.'.$format); //see recordFile.php
    }else if($mode=='3' && file_exists(FOLDER_SQL_BACKUP.'.'.$format)){  //archived sql dump
        downloadFile($mime, FOLDER_SQL_BACKUP.'.'.$format);
    }else if($mode=='5' && file_exists(FOLDER_HML_BACKUP.'.'.$format)){  //archived hml file
        downloadFile($mime, FOLDER_HML_BACKUP.'.'.$format);
    }else if($mode=='4'){  //cleanup backup folder on exit
        folderDelete2(HEURIST_FILESTORE_DIR.'backup/', false);
    }
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Create data archive package</title>

        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-ui.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>

        <!-- CSS -->
        <?php include_once dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php'; ?>
        
        <script type=text/javascript>
            var is_repository = <?php echo $is_repository?'true':'false';?>;
            
            $(document).ready(function() {
                $('input[type="submit"]').button();
                $('input[type="button"]').button();
                
                if(!window.hWin.HAPI4){
                    $('#btnClose_1').hide();
                    $('#btnClose_2').hide();
                    
                    if(is_repository){
                        $('body').children().hide();
                        //show warning message: upload to repository is not available 
                        window.location = '<?php echo PDIR.'hclient/framecontent/infoPage.php?error='.rawurlencode('It is possible to perform this operation from Heurist admin interface only');?>';
                    }
                }else if(is_repository){
                    //if($('#sel_repository').length > 0)
                    initRepositorySelector();
                }
                
                
                
            });
            
            //
            // cleanup backup folder on exit
            //
            function closeArchiveWindow(){
                <?php print '$.ajax("'.HEURIST_BASE_URL.'/export/dbbackup/exportMyDataPopup.php?mode=4&db='.HEURIST_DBNAME.'");'; ?>
                window.close();
            }

            //
            // fill repository selector
            //
            function initRepositorySelector(){

                let available = ['Nakala'];
                let $select = $('#sel_repository');

                $.each(available, (idx, repo_name) => {
                    window.hWin.HEURIST4.ui.addoption($select[0], repo_name, repo_name);
                });
                window.hWin.HEURIST4.ui.initHSelect($select, false, {width: '150px', 'margin-left': '5px'}, {
                    onSelectMenu: () => {
                        let value = $select.val();

                        if(value == 'Nakala'){

                            // Check if API Key has been provided
                            if(window.hWin.HEURIST4.util.isempty(window.hWin.HAPI4.sysinfo.nakala_api_key)){
                                $select.val('');
                                if($select.hSelect('instance') !== undefined){
                                    $select.hSelect('refresh');
                                }
                                window.hWin.HEURIST4.msg.showMsgErr('You need to enter your Nakala API Key into Design > Setup > Properties > General Nakala API key, in order to upload to Nakala.');
                                return;
                            }

                            getNakalaLicenses();
                        }
                    }
                });
            }
            
            //
            // fill license selector
            //
            function getNakalaLicenses(){
                let $sel_license = $('#sel_license');

                if($sel_license.attr('data-init') == 'Nakala' && $sel_license.find('option').length > 1){ // already has values
                    return;
                }

                let request = {
                    serviceType: 'nakala',
                    service: 'nakala_get_metadata',
                    type: 'licenses'
                };

                window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));

                window.hWin.HAPI4.RecordMgr.lookup_external_service(request, (data) => {

                    window.hWin.HEURIST4.msg.sendCoverallToBack();
                    data = window.hWin.HEURIST4.util.isJSON(data);

                    if(data.status && data.status != window.hWin.ResponseStatus.OK){
                        window.hWin.HEURIST4.msg.showMsgErr('An error occurred while attempting to retrieve the licenses for Nakala records, however the archiving process can still be completed.<br>'
                                + 'If this problem persists, please contact the Heurist team.');
                        $sel_license.parent().parent().hide();
                        return;
                    }

                    if(data.length > 0){
                        $.each(data, (idx, license) => {
                            window.hWin.HEURIST4.ui.addoption($sel_license[0], license, license);
                        });
                        window.hWin.HEURIST4.ui.initHSelect($sel_license, false, {'margin-left': '21px'});
                        $sel_license.attr('data-init', 'Nakala');
                        $sel_license.parent().parent().show();
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr('An unknown error has occurred while attempting to retrieve the licenses for Nakala records, however the archiving process can still be completed.<br>'
                                + 'If this problem persists, please contact the Heurist team.');
                        $sel_license.parent().parent().hide();
                    }
                });
            }
            

            //
            // on start button click event handler
            //
            function exportArchive(){

                if(is_repository){
                    let repo = $('#sel_repository').val();
                    if(repo == ''){
                        window.hWin.HEURIST4.msg.showMsgFlash('Please select a repository...', 2000);
                        return;
                    }else if(repo == 'Nakala' && window.hWin.HEURIST4.util.isempty(window.hWin.HAPI4.sysinfo.nakala_api_key)){
                        window.hWin.HEURIST4.msg.showMsgErr('You need to enter your Nakala API Key into Design > Setup > Properties > General Nakala API key, in order to upload to Nakala.');
                        return;
                    }
                }
                
                
                if(window.hWin.HAPI4){
                    /*
                    $('<div>Preparing archive file...</div>')
                        .addClass('coverall-div')
                        .css({'zIndex':60000,'padding':'30px 0 0 30px','font-size':'1.2em','opacity':0.8,'color':'white'})
                        .appendTo('body'); 
                    */     
                    //show wait screen
                    window.hWin.HEURIST4.msg.bringCoverallToFront(null, null, 'Preparing archive file...');
                }

                document.getElementById('buttons').style.visibility = 'hidden';
                document.forms[0].submit(); //page reload
            }

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
        <?php if(!$is_repository){?>
            <p>The output of the process will be made available as a link to a downloadable zip file
            <br>but is also available to the system adminstrator as a file in the backup directory in the database.
            </p>
            <h3 class="ui-heurist-title">Warning</h3>
            <p>Zipping databases with large numbers of images or very large files such as high 
            <br>resolution maps or video may bog down the server and the zip file may be too big to download.
            <br>In that case you may need to ask your sysadmin to give you the files separately on a USB drive.</p>
        <?php }else{ ?>
            <p>The output of the process will be zipped and uploaded to the selected repository
            </p>
            <h3 class="ui-heurist-title">Warning</h3>
            <p>Zipping databases and including lots of images or video may bog down the server and the file may not upload to the repository
            - in that case it may be better to ask your sysadmin to give you the files separately on a USB drive and attempt to upload it yourself.</p>
        <?php } ?>
            <p>Attached files may be omitted by unchecking the first checkbox. 
            <br>This may also be useful for databases with lots of attached files which are already backed up elsewhere.
            </p>

            <form name='f1' action='exportMyDataPopup.php' method='get'>
            
                <input name='db' value='<?=HEURIST_DBNAME?>' type='hidden'>
                <input name='mode' value='1' type='hidden'>

                <div class="input-row" style="padding-top:10px">
                    <label>
                        <input type="checkbox" name="includeresources" value="1">
                        Include attached (uploaded) files eg. images (essential for full database archive).
                    </label> 
                </div>

                <div class="input-row">
                    <label title="Adds all tilestacks that have been uploaded to Heurist">
                        <input type="checkbox" name="include_tilestacks" value="1">
                        Include tiled map images - these are typically very large and are probably already available elsewhere
                    </label>
                </div>

                <div class="input-row">
                    <label title="Adds fully self-documenting HML (Heurist XML) file">
                        <input type="checkbox" name="include_hml" value="1">
                        Include HML (not required for transfer to a new server, but recommended for long-term archive)
                    </label>
                </div>

                <div class="input-row">
                    <label title="Export / Upload the archive in Zip format, instead of BZip">
                        <input type="checkbox" name="is_zip" value="1">
                        Use Zip format rather than BZip
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

        <?php if($is_repository){ ?>
                <div class="input-row" style="padding: 20px 0 5px 0;">
                    <label>Select a repository <select id='sel_repository' name='repository'><option value="">select a repository...</option></select></label>
                </div>

                <div class="input-row" style="display: none;padding: 5px 0 10px 0;">
                    <label>Select a license <select id='sel_license' name='license'><option value="">select a license...</option></select></label>
                </div>
        <?php } ?>

                <div id="buttons" class="actionButtons" style="padding-top:10px;text-align:left">
                    <input type="button" value="<?php echo ($is_repository ? 'Export & Upload' : 'Create Archive'); ?>" 
                        style="margin-right: 20px;" class="ui-button-action" onClick="{ exportArchive(); }">
                    <input type="button" id="btnClose_1" value="Cancel" onClick="closeArchiveWindow();">
                </div>
            </form>
            <?php

        }else{
            
            $operation_in_progress = 'It appears that backup operation has been started already. Please try this function later';
            
            //flag that backup in progress
            if(!isActionInProgress('exportDB', 2, HEURIST_DBNAME)){
                report_message($operation_in_progress, false);
            }else{
                echo_flush2("<br>Beginning archive process<br>");
            }
            
            $separate_sql_zip = !$is_repository;

            // 
            if(file_exists(FOLDER_BACKUP)){
                echo_flush2("<br>Clear folder ".FOLDER_BACKUP."<br>");
                //clean folder
                $res = folderDelete2(FOLDER_BACKUP, true);
                if(!$res){
                    report_message($operation_in_progress, false);
                }
            }
            if (!folderCreate(FOLDER_BACKUP, true)) {
                $message = 'Failed to create folder '.FOLDER_BACKUP.'<br> in which to create the backup. Please consult your sysadmin.';            report_message($message, true);
            }

            // Just SQL dump
            if(file_exists(FOLDER_SQL_BACKUP)){
                $res = folderDelete2(FOLDER_SQL_BACKUP, true);
                if(!$res){
                    report_message($operation_in_progress, false);
                }
            }
            if($separate_sql_zip && !folderCreate(FOLDER_SQL_BACKUP, true)){
                $separate_sql_zip = false; // hide option
            }

            if($is_repository && @$_REQUEST['repository']!='Nakala'){
                report_message('The repository ' . $repo . ' is not supported please ' . CONTACT_HEURIST_TEAM, true, false);
            }

            $folders_to_copy = null;
            
            //copy resource folders
            if(@$_REQUEST['include_docs']=='1'){
                $folders_to_copy = folderSubs(HEURIST_FILESTORE_DIR, 
                    array('backup', 'scratch', 'generated-reports', 'file_uploads', 'filethumbs', 'tileserver', 'uploaded_files', 'uploaded_tilestacks', 'rectype-icons', 'term-images', 'webimagecache')); //except these folders - some of them may exist in old databases only
                
                //limited set
                //$folders_to_copy = $system->getSystemFolders( 1 );

                echo_flush2("<br><br>Exporting system folders<br>");
            }
            
            if(@$_REQUEST['includeresources']=='1'){ //uploaded images
                if($folders_to_copy==null) $folders_to_copy = array();    
                $folders_to_copy[] = HEURIST_FILES_DIR;
                $folders_to_copy[] = HEURIST_THUMB_DIR;
                
                $copy_files_in_root = true; //copy all files within database folder
            }else{
                $copy_files_in_root = false;
            }
            if(@$_REQUEST['include_tilestacks']=='1' && defined('HEURIST_TILESTACKS_DIR')){
                if($folders_to_copy==null) $folders_to_copy = array();
                $folders_to_copy[] = HEURIST_TILESTACKS_DIR;
            }
            if($folders_to_copy==null){
                $folders_to_copy = array('no copy folders');   
            }
            
                
           if(@$_REQUEST['include_docs']=='1' || @$_REQUEST['includeresources']=='1'){     
               folderRecurseCopy( HEURIST_FILESTORE_DIR, FOLDER_BACKUP, $folders_to_copy, null, $copy_files_in_root);
           }
            
           if(@$_REQUEST['include_docs']=='1'){// 2016-10-25  
                echo_flush2('Copy context_help folder<br>');                
                folderRecurseCopy( HEURIST_DIR.'context_help/', FOLDER_BACKUP.'/context_help/', null);
           }
           
           
           //remove db.json (database def cache) from entity
           fileDelete(FOLDER_BACKUP.'/entity/db.json');
            

           if(@$_REQUEST['include_hml']=='1'){
            
                //load hml output into string file and save it
                if(@$_REQUEST['allrecs']!="1"){
                    $userid = $system->get_user_id();
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
                $_REQUEST['filename'] = '1'; //FOLDER_BACKUP."/".HEURIST_DBNAME.".xml";
                
                echo_flush2("Exporting database as HML (Heurist Markup Language = XML)<br>(may take several minutes for large databases)<br>");

                $to_include = dirname(__FILE__).'/../../export/xml/flathml.php';
                if (is_file($to_include)) {
                    include_once $to_include;
                }

                if(file_exists(FOLDER_BACKUP.'/'.HEURIST_DBNAME.'.xml') && $separate_hml_zip){
                    $separate_hml_zip = fileCopy(FOLDER_BACKUP.'/'.HEURIST_DBNAME.'.xml', FOLDER_HML_BACKUP."/".HEURIST_DBNAME.".xml");
                }
            
           }//export HML
            
           // Export database definitions as readable text

           echo_flush2("Exporting database definitions as readable text<br>");

           $url = HEURIST_BASE_URL . "hserv/structure/export/getDBStructureAsSQL.php?db=".HEURIST_DBNAME."&pretty=1";
           saveURLasFile($url, FOLDER_BACKUP."/Database_Structure.txt"); //save to HEURIST_FILESTORE_DIR.'backup/'.HEURIST_DBNAME

           echo_flush2("Exporting database definitions as XML<br>");
            
           $url = HEURIST_BASE_URL . "hserv/structure/export/getDBStructureAsXML.php?db=".HEURIST_DBNAME;
           saveURLasFile($url, FOLDER_BACKUP."/Database_Structure.xml"); //save to HEURIST_FILESTORE_DIR.'backup/'.HEURIST_DBNAME


           if($system->is_admin()){
                // Do an SQL dump of the whole database
                echo_flush2("Exporting SQL dump of the whole database (several minutes for large databases)<br>");
                
                
                $database_dumpfile = FOLDER_BACKUP."/".HEURIST_DBNAME."_MySQL_Database_Dump.sql";
                $dump_options = array('skip-triggers' => true,  'quick' =>true, 'add-drop-trigger' => false, 'no-create-db' =>true, 'add-drop-table'=>true);
                
                $res = DbUtils::databaseDump(HEURIST_DBNAME_FULL, $database_dumpfile, $dump_options, false );
                
                if(!$res){
                    print '</div>';
                    report_message("Sorry, unable to generate MySQL database dump. ".$system->getError()['message'].'  '.$please_advise, true, true);
                }

                if($separate_sql_zip){ // copy sql dump to separate directory
                    $separate_sql_zip = fileCopy($database_dumpfile, FOLDER_SQL_BACKUP."/".HEURIST_DBNAME."_MySQL_Database_Dump.sql");
                }
           }

           // remove old mysql dump - specifically the ones named HEURIST_DBNAME_FULL.sql
           if(file_exists(FOLDER_BACKUP.'/'.HEURIST_DBNAME_FULL.'.sql')) {
               unlink(FOLDER_BACKUP.'/'.HEURIST_DBNAME_FULL.'.sql');   
           }

           echo_flush2('<br>Zipping files<br>');

           // Create a zipfile of the definitions and data which have been dumped to disk
           $destination = FOLDER_BACKUP.'.'.$format; // Complete archive
           if(file_exists($destination)){ 
               unlink($destination);
           }
           if($format == 'tar' && file_exists(FOLDER_BACKUP.'.tar.bz2')){
               unlink(FOLDER_BACKUP.'.tar.bz2');
           }

           if($format == 'zip'){
               $res = UArchive::zip(FOLDER_BACKUP, null, $destination, true);
           }else{
               $res = UArchive::createBz2(FOLDER_BACKUP, null, $destination, true);
           }

           if($res===true){ //archive successful

                $res_sql = false;
                if($separate_sql_zip){

                    $destination_sql = FOLDER_BACKUP.'_sql.'.$format; // SQL dump only
                    if(file_exists($destination_sql)){
                        unlink($destination_sql);
                    }
                    if($format == 'tar' && file_exists(FOLDER_BACKUP.'_sql.tar.bz2')){
                        unlink(FOLDER_BACKUP.'_sql.tar.bz2');
                    }

                    if($format == 'zip'){
                        $res_sql = UArchive::zip(FOLDER_BACKUP.'_sql', null, $destination_sql, true);   
                    }else{
                        $res_sql = UArchive::createBz2(FOLDER_BACKUP.'_sql', null, $destination_sql, true);
                    }
                }

                $res_hml = false;
                if($separate_hml_zip){

                    $destination_hml = FOLDER_BACKUP.'_hml.'.$format; // SQL dump only
                    if(file_exists($destination_hml)){
                        unlink($destination_hml);
                    }
                    if($format == 'tar' && file_exists(FOLDER_BACKUP.'_hml.tar.bz2')){
                        unlink(FOLDER_BACKUP.'_hml.tar.bz2');
                    }

                    if($format == 'zip'){
                        $res_hml = UArchive::zip(FOLDER_BACKUP.'_hml', null, $destination_hml, true);
                    }else{
                        $res_hml = UArchive::createBz2(FOLDER_BACKUP.'_hml', null, $destination_hml, true);
                    }
                    
                }
            
                if(!$is_repository) {
                        //success - print two links to download archives
    
        $is_zip = '&is_zip='.($format == 'zip' ? 1 : 0); 
    
        if($format == 'tar'){
            $format = 'tar.bz2';
        }
    ?>                
    <p>Your data have been backed up in <?php echo FOLDER_BACKUP;?></p>
    <br><br><div class='lbl_form'></div>
        <a href="exportMyDataPopup.php/<?php echo HEURIST_DBNAME;?>.<?php echo $format; ?>?mode=2&db=<?php echo HEURIST_DBNAME.$is_zip;?>"
            target="_blank" rel="noopener" style="color:blue; font-size:1.2em">Click here to download your data as a <?php echo $format;?> archive</a>

    <?php 
    if($separate_sql_zip){
        if($res_sql===true){ ?>
        <br><br>
        <a href="exportMyDataPopup.php/<?php echo HEURIST_DBNAME;?>_sql.<?php echo $format; ?>?mode=3&db=<?php echo HEURIST_DBNAME.$is_zip;?>"
            target="_blank" rel="noopener" style="color:blue; font-size:1.2em">Click here to download the SQL <?php echo $format;?> file only</a> 
        <span class="heurist-helper1">(for db transfer on tiered servers)</span>
    <?php }else{ ?>
        <br><br>
        <div>Failed to create standalone SQL dump. <?php echo $res_sql;?></div>
    <?php 
        } 
    }
    if($separate_hml_zip){
        if($res_hml===true){ ?>
        <br><br>
        <a href="exportMyDataPopup.php/<?php echo HEURIST_DBNAME;?>_hml.<?php echo $format; ?>?mode=5&db=<?php echo HEURIST_DBNAME.$is_zip;?>"
            target="_blank" style="color:blue; font-size:1.2em">Click here to download the HML <?php echo $format;?> file only</a>
    <?php }else{ ?>
        <br><br>
        <div>Failed to create / set up a standalone HML file. <?php echo $res_hml;?></div>
    <?php 
        } 
    }
    ?>
    <p class="heurist-helper1">
    Note: If this file fails to download properly (eg. "Failed … file incomplete") the file is too large to download. Please ask your system administrator (<?php echo HEURIST_MAIL_TO_ADMIN; ?>) to send it to you via a large file transfer service
    </p>        
    
                    <input type="button" id="btnClose_2" class="ui-button-action" value="Close" onClick="closeArchiveWindow();">

    <?php                
                }
                else if($is_repository){
                    //upload archive to repository

                    $repo = htmlspecialchars($_REQUEST['repository']);
                    if($format == 'tar'){
                        $format = 'tar.bz2';
                    }

                    echo_flush2('<hr><br>Uploading archive to ' . $repo . '...');

                    // Prepare metadata
                    switch ($repo) {
                        case 'Nakala':
                            // Title, Type, Alt Author, License, Created

                            $date = date('Y-m-d');

                            $params = array();
                            $params['file'] = array(
                                'path' => FOLDER_BACKUP . '.' . $format,
                                'type' => $mime,
                                'name' => HEURIST_DBNAME . '.' . $format
                            );

                            $params['meta']['title'] = array(
                                'value' => 'Archive of ' . HEURIST_DBNAME . ' on ' . $date,
                                'lang' => null,
                                'typeUri' => 'http://www.w3.org/2001/XMLSchema#string',
                                'propertyUri' => 'http://nakala.fr/terms#title'
                            );

                            $usr = $system->getCurrentUser();
                            if(is_array($usr) && count($usr) > 0){
                                $params['meta']['creator'] = array(
                                    'value' => $usr['ugr_FullName'],
                                    'lang' => null,
                                    'typeUri' => 'http://www.w3.org/2001/XMLSchema#string',
                                    'propertyUri' => 'http://purl.org/dc/terms/creator'
                                );
                            }

                            $params['meta']['created'] = array(
                                'value' => $date,
                                'lang' => null,
                                'typeUri' => 'http://www.w3.org/2001/XMLSchema#string',
                                'propertyUri' => 'http://nakala.fr/terms#created'
                            );

                            $params['meta']['type'] = array(
                                'value' => 'http://purl.org/coar/resource_type/c_ddb1',
                                'lang' => null,
                                'typeUri' => 'http://www.w3.org/2001/XMLSchema#anyURI',
                                'propertyUri' => 'http://nakala.fr/terms#type'
                            );

                            if(array_key_exists('license', $_REQUEST) && !empty($_REQUEST['license'])){
                                $params['meta']['license'] = array(
                                    'value' => $_REQUEST['license'],
                                    'lang' => null,
                                    'typeUri' => 'http://www.w3.org/2001/XMLSchema#string',
                                    'propertyUri' => 'http://nakala.fr/terms#license'
                                );
                            }

                            if($system->get_system('sys_NakalaKey')){
                                $params['api_key'] = $system->get_system('sys_NakalaKey');

                                $params['status'] = 'pending'; // keep new record private, so it can be deleted
                                $params['return_type'] = 'editor'; // return link to private record, will require login
        
                                $rtn = uploadFileToNakala($system, $params); //upload database archive
        
                                if($rtn === false){
                                    $rtn = $system->getError()['message'];
                                    echo_flush2('failed<br>');
                                }else{
                                    echo_flush2('finished<br>');
                                    $rtn = 'The uploaded archive is at <a href="' . $rtn . '" target="_blank">' . $rtn . '&nbsp;<span class="ui-icon ui-icon-extlink" /> </a>';
                                }
        
                                echo_flush2('<br>'. $rtn .'<br>');
                            }else{
                                report_message('Your Nakala API key cannot be retrieved, '
                                .'please ensure it has been entered into Design > Setup > Properties > General Nakala API key', true, true);
                            }
                            
                            break;
                        
                        default:
                            report_message('The repository ' . $repo . ' is not supported please ' . CONTACT_HEURIST_TEAM, true, true);
                            break;
                    }
                    
                }//end repository
                
                report_message('', false, true);
            }else
            {
                report_message($res.'<br>Try different archive format otherwise please consult system adminstrator', true, true);
            }
            
        }
        ?>

    </body>
</html>

<?php
function report_message($message, $is_error=true, $need_cleanup=false)
{
    if($need_cleanup){
            if(array_key_exists('repository', $_REQUEST)){
                //cleanup backup after upload to reporsitory
                folderDelete2(HEURIST_FILESTORE_DIR.'backup/', false);
            }else{
                //cleanup temp folders
                folderDelete2(FOLDER_BACKUP, true);
                folderDelete2(FOLDER_SQL_BACKUP, true);
                folderDelete2(FOLDER_HML_BACKUP, true);
            }
            //remove lock file
            isActionInProgress('exportDB', -1, HEURIST_DBNAME);
    }
    if($message){
?>
        <div class="ui-corner-all ui-widget-content" style="text-align:left; width:70%; min-width:220px; margin:0px auto; padding: 0.5em;">

            <!-- <div class="logo" style="background-color:#2e3e50;width:100%"></div> -->

            <div class="<?php echo ($is_error)?'ui-state-error':''; ?>" 
                style="width:90%;margin:auto;margin-top:10px;padding:10px;">
                <span class="ui-icon <?php echo ($is_error)?'ui-icon-alert':'ui-icon-info'; ?>" 
                      style="float: left; margin-right:.3em;font-weight:bold"></span>
                <?php echo $message;?>
            </div>
        </div>
<?php
    }
?>
        <script>if(window.hWin.HEURIST4.msg){ window.hWin.HEURIST4.msg.sendCoverallToBack(true); }</script>
    </body>    
</html>
<?php
    exit;
}
?>