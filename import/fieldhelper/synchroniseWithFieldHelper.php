<?php

/**
* synchroniseWithFieldHelper.php
* Read the FieldHelper XML manifests in directories specified in Advanced Properties,
* and creates Heurist records for indexed files. If there is no manifest, it creates one.
* The list of extensions indexed can also be specified in Advanced Properties, otherwise
* it indexes a range of common file types including most text, image, audio and video formats
*
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2
* @param       includeUgrps=1 will output user and group information in addition to definitions
* @param       approvedDefsOnly=1 will only output Reserved and Approved definitions
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
* @todo        write Heurist IDs back into FH XML files
* @todo        update existing records from XML files which have changed
* @todo        update XML files from Heurist records which have changed
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
require_once(dirname(__FILE__)."/../../common/php/saveRecord.php");
require_once(dirname(__FILE__)."/../../records/files/uploadFile.php");

if (! is_logged_in()) {
    header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
    return;
}
?>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Import Records In Situ / FieldHelepr Manifests</title>
        <link rel=stylesheet href="../../common/css/global.css" media="all">
    </head>
    <body class="popup">

        <script type="text/javascript">

            function update_counts(divid, processed, added, total) {
                document.getElementById("progress"+divid).innerHTML = (total==0)?"": (" <div style=\"color:green\"> Processed "
                    +processed+" of "+total+". Added records: "+added+"</div>");
            }
        </script>

        <?php
        if (! is_admin()) {
            print "<p>FieldHelper synchronisation requires you to be an Administrator of the Database Managers group</p></body></html>";
            return;
        }

        $titleDT = (defined('DT_NAME')?DT_NAME:0);
        $geoDT = (defined('DT_GEO_OBJECT')?DT_GEO_OBJECT:0);
        $fileDT = (defined('DT_FILE_RESOURCE')?DT_FILE_RESOURCE:0);
        $startdateDT = (defined('DT_START_DATE')?DT_START_DATE:0);
        $enddateDT = (defined('DT_END_DATE')?DT_END_DATE:0);
        $descriptionDT = (defined('DT_SHORT_SUMMARY')?DT_SHORT_SUMMARY:0);

        $fieldhelper_to_heurist_map = array(
            "heurist_id" => "recordId",
            "Name0" => $titleDT,
            "Annotation" => $descriptionDT,
            "DateTimeOriginal" => $startdateDT,
            "filename" => "file",
            "latitude" => "lat",
            "longitude" => "lon",

            "file_name" => (defined('DT_FILE_NAME')?DT_FILE_NAME:0),
            "file_path" => (defined('DT_FILE_FOLDER')?DT_FILE_FOLDER:0),
            "extension"  => (defined('DT_FILE_EXT')?DT_FILE_EXT:0),

            "device"  	=> (defined('DT_FILE_DEVICE')?DT_FILE_DEVICE:0),
            "duration"  => (defined('DT_FILE_DURATION')?DT_FILE_DURATION:0),
            "filesize"  => (defined('DT_FILE_SIZE')?DT_FILE_SIZE:0),
            "md5"  		=> (defined('DT_FILE_MD5')?DT_FILE_MD5:0)
        );

        $rep_counter = null;
        $rep_issues = null;
        $currfile = null;
        $mediaExts = null;
        $progress_divid = 0;
        $system_folders = array(HEURIST_THUMB_DIR,
            HEURIST_ICON_DIR,
            HEURIST_FILES_DIR,
            HEURIST_FILESTORE_DIR."backup/",
            HEURIST_FILESTORE_DIR.'documentation_and_templates/',
            HEURIST_FILESTORE_DIR."faims/",
            HEURIST_FILESTORE_DIR."generated-reports/",
            HEURIST_FILESTORE_DIR."scratch/",
            HEURIST_FILESTORE_DIR."settings/",
            HEURIST_FILESTORE_DIR.'term-images/',
            HEURIST_SMARTY_TEMPLATES_DIR,
            HEURIST_XSL_TEMPLATES_DIR);
        if(defined('HEURIST_HTML_DIR')) array_push($system_folders, HEURIST_HTML_DIR);
        if(defined('HEURIST_HML_DIR')) array_push($system_folders, HEURIST_HML_DIR);

        
        mysql_connection_overwrite(DATABASE);
        if(mysql_error()) {
            die("Sorry, could not connect to the database (mysql_connection_overwrite error)");
        }

        ?>
        <h2>ADVANCED USERS</h2>

        <p style="font-weight:bold;font-size:larger;padding:10 0">This function is designed for the indexing of bulk uploaded files (often images)</p>
        
        This function reads FieldHelper (http://fieldhelper.org) XML manifest files from the folders (and their descendants)
        listed in Admin > Database > Advanced Properties and writes the metadata as records in the current database, 
        with pointers back to the files described by the manifests.
        
         <p>
            If no manifests exist, they are created (and can be read by FieldHelper). 
            New files are added to the existing manifests.<br>&nbsp;<br>
            The current database may already contain data; new records are appended, existing records are unaffected.<br>&nbsp;<br>
            Note: the folders to be indexed must be writeable by the PHP system - normally they should be owned by Apache or www-data (as appropriate).
         </p>   
         <p>
            Files will need to be uploaded to the server via direct access to the server or through Import > Multi-file upload.
         </p>
         <?php
        
        $notfound = array();
        foreach ($fieldhelper_to_heurist_map as $key=>$id){
            if(is_numeric($id) && $id==0){
                array_push($notfound, $key);
            }
        }
        if(count($notfound)>0){
            print "<p style='color:brown;'> Warning: There are no fields in this database to hold the following information: <b>".
            implode(", ",$notfound).
            "</b><br />Note: these fields may appear to be present, but do not have the correct origin codes ".
            "(source of the field definition) for this function to use them.".
            "<p>We recommend importing the appropriate fields by (re)importing the Digital Media Item record type as follows".
            "<ul><li>Go to Manage &gt; Structure &gt; Browse templates<br>&nbsp;</li>".
            "<li>Navigate to the HeuristCoreDefinitions database<br>&nbsp;</li>".
            "<li>Check 'Show existing record types'</li>&nbsp;".
            "<li>Check 'Click the download icon on the Digital Media Item'</li></ul>".
            "You may proceed without this step, but these fields will not be imported</p>";
        }

        // ----FORM 1 - CHECK MEDIA FOLDERS --------------------------------------------------------------------------------

        if(!array_key_exists('mode', $_REQUEST)) {

            if(HEURIST_DBID==0){ //is not registered

                print "<p style=\"color:red\">Note: Database must be registered with the Heurist master index to use this function<br>".
                "Register the database using Database administration page > Database > Registration - ".
                "only available to the database creator/owner (user #2)</p>";

            }else{
                // Find out which folders to parse for XML manifests - specified for FieldHelper indexing in Advanced Properties
                $query1 = "SELECT sys_MediaFolders, sys_MediaExtensions from sysIdentification where 1";
                $res1 = mysql_query($query1);
                if (!$res1 || mysql_num_rows($res1) == 0) {
                    die ("<p><b>Sorry, unable to read the sysIdentification from the current database. ".
                        "Possibly wrong database format, please consult Heurist team");
                }

                // Get the set of directories defined in Advanced Properties as FieldHelper indexing directories
                $row1 = $row = mysql_fetch_row($res1);
                $mediaFolders = $row1[0];
                
                if($mediaFolders==null || $mediaFolders==''){
                    $mediaFolders = HEURIST_FILESTORE_DIR;
                }
                $dirs = explode(';', $mediaFolders); // get an array of folders

                //sanitize folder names
                $dirs = array_map('sanitizeFolderName', $dirs);
                $mediaFolders = implode(';', $dirs);
                
                // The defined list of file extensions for FieldHelper indexing.
                if($row1[1]==null){
                    $mediaExts = "jpg,jpeg,sid,png,gif,tif,tiff,bmp,rgb,doc,docx,odt,xsl,xslx,mp3,mp4,mpeg,avi,wmv,wmz,".
                    "aif,aiff,mid,midi,wms,wmd,qt,evo,cda,wav,csv,tsv,tab,txt,rtf,xml,xsl,xslt,hml,kml,shp,".
                    "htm,html,xhtml,ppt,pptx,zip,gzip,tar";
                }else{
                    $mediaExts = $row1[1]; // user gets to define from scratch so they can restrict what's indexed
                }

                if ($mediaFolders=="" || count($dirs) == 0) {
                    $mediaFolders = HEURIST_FILESTORE_DIR; // default to the data folder for this database
                    //print ("<p><b>It seems that there are no media folders specified for this database</b>");
                }
                print "<p><b>Folders to scan :</b> $mediaFolders<p>";
                print "<p><b>Extensions to scan:</b> $mediaExts<p>";

                print  "<p><a href='../../admin/setup/dbproperties/editSysIdentificationAdvanced.php?db=".HEURIST_DBNAME."&popup=1'>".
                "Click here to set media folders (database file directory descendants scanned by default)</a><p>";


                if (!($mediaFolders=="" || count($dirs) == 0)) {
                    print "<form name='selectdb' action='synchroniseWithFieldHelper.php' method='get'>";
                    print "<input name='mode' value='2' type='hidden'>"; // calls the form to select mappings, step 2
                    print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
                    print '<input name="media" value="'.$mediaFolders.'" type="hidden">';
                    print "<input name='exts' value='$mediaExts' type='hidden'>";
                    print "<input type='submit' value='Continue' />";
                }
            }
            exit;
        }

        // ---- visit #3 - PROCESS THE HARVESTING -----------------------------------------------------------------

        if(array_key_exists('mode', $_REQUEST) && $_REQUEST['mode']=='2'){
            $mediaFolders = $_REQUEST['media'];
            print "<p>Now harvesting FieldHelper metadata into <b> ". HEURIST_DBNAME. "</b><br> ";

            $dirs = explode(';', $mediaFolders); // get an array of folders

            $mediaExts = $_REQUEST['exts'];
            $mediaExts = explode(',', $mediaExts);

            $rep_counter = 0;
            $rep_issues = "";
            $progress_divid = 0;

            set_time_limit(0); //no limit

            doHarvest($dirs);

            print "<div>Syncronization completed</div>";
            print "<div style=\"color:green\">Total files processed: $rep_counter </div>";
        }

        // ---- HARVESTING AND OTHER FUNCTIONS -----------------------------------------------------------------
        function sanitizeFolderName($folder) {
            $folder = str_replace("\0", '', $folder);
            $folder = str_replace('\\', '/', $folder);
            if( substr($folder, -1, 1) != '/' )  {
                $folder = $folder.'/';
            }
            return $folder;
        }

        function doHarvest($dirs) {

            global $rep_counter, $rep_issues, $system_folders;

            foreach ($dirs as $dir){

                if($dir=="*"){

                    $dir = HEURIST_FILESTORE_DIR;

                }else{

                    if(substr($dir, -1) != '/'){
                        $dir .= "/";
                    }

                    /* changed to check that folder is in HEURIST_FILESTORE_DIR 
                    if(!file_exists($dir) ){ //probable this is relative
                        $orig = $dir;
                        chdir(HEURIST_FILESTORE_DIR);
                        $dir = realpath($dir);
                        if(!file_exists($dir)){
                            $dir = $orig; //restore
                        }
                    }
                    */
                    $dir = str_replace('\\','/',$dir);
                    if(!( substr($dir, 0, strlen(HEURIST_FILESTORE_DIR)) === HEURIST_FILESTORE_DIR )){
                        $orig = $dir;
                        chdir(HEURIST_FILESTORE_DIR);
                        $dir = realpath($dir);
                        
                        //realpath gives real path on remote file server
                        if(strpos($dir, '/srv/HEURIST_FILESTORE/')===0){
                            $dir = str_replace('/srv/HEURIST_FILESTORE/', HEURIST_UPLOAD_ROOT, $dir);
                        }else
                        if(strpos($dir, '/misc/heur-filestore/')===0){
                            $dir = str_replace('/misc/heur-filestore/', HEURIST_UPLOAD_ROOT, $dir);
                        }
                        
                        $dir = str_replace('\\','/',$dir);     
                        if(!( substr($dir, 0, strlen(HEURIST_FILESTORE_DIR)) === HEURIST_FILESTORE_DIR )){
                            print "<div style=\"color:red\">$orig is ignored. Folder must be in heurist filestore directory.</div>";
                            continue;
                        }
                    }

                    if(substr($dir, -1) != '/'){
                        $dir .= "/";
                    }

                }

                if(in_array($dir, $system_folders)){

                    print "<div style=\"color:red\">Files are not scanned in system folder $dir</div>";

                }else if($dir && file_exists($dir) && is_dir($dir))
                {


                    $files = scandir($dir);
                    if($files && count($files)>0)
                    {
                        $subdirs = array();

                        $isfirst = true;

                        foreach ($files as $filename){

                            if(!($filename=="." || $filename=="..")){
                                if(is_dir($dir.$filename)){
                                    array_push($subdirs, $dir.$filename."/");
                                }else if($isfirst){ //if($filename == "fieldhelper.xml"){
                                    $isfirst = false;
                                    if($dir == HEURIST_FILESTORE_DIR){
                                        print "<div style=\"color:red\">Files are not scanned in root upload folder $dir</div>";
                                    }else{
                                        $rep_counter = $rep_counter + doHarvestInDir($dir);
                                    }
                                }
                            }
                        }

                        if(count($subdirs)>0){
                            doHarvest($subdirs);
                            flush();
                        }
                    }
                }else if ($dir) {
                    print "<div style=\"color:red\">Folder is not found: $dir</div>";
                }
            }
        }

        /**
        * callback from saveRecord
        *
        * @param mixed $message
        */
        function jsonError($message) {
            global $rep_issues, $currfile;
            //mysql_query("rollback");
            $rep_issues = $rep_issues."<br/>Error save record for file:".$currfile.". ".$message;
        }


        /**
        *
        * @global type $rep_counter
        * @global string $rep_issues
        * @global array $fieldhelper_to_heurist_map
        * @param type $dir
        */
        function doHarvestInDir($dir) {

            global $rep_issues, $fieldhelper_to_heurist_map, $mediaExts, $progress_divid,
            $geoDT, $fileDT, $titleDT, $startdateDT, $enddateDT, $descriptionDT;

            $rep_processed = 0;
            $rep_added = 0;
            $rep_updated = 0;
            $rep_processed_dir = 0;
            $rep_ignored = 0;
            $f_items = null; //reference to items element

            $progress_divid++;

            ob_start();
            print "<div><b>$dir</b><span id='progress$progress_divid'></span></div>";
            ob_flush();
            flush();

            if(!is_writable($dir)){
                print "<div style=\"color:red\">Folder is not writeable. Check permissions</div>";
                return 0;
            }

            $manifest_file = $dir."fieldhelper.xml";

            //list of all files in given folder - need to treat new files that are not mentioned in manifest file
            $all_files = scandir($dir);

            if(file_exists($manifest_file)){

                //read fieldhelper.xml
                if(is_readable($manifest_file)){

                    //check write permission
                    if(!is_writable($manifest_file)){
                        print "<div style=\"color:red\">Manifest is not writeable. Check permissions.</div>";
                        return 0;
                    }
                }else{
                    print "<div style=\"color:red\">Manifest is not readable. Check permissions.</div>";
                    return 0;
                }

                $fh_data = simplexml_load_file($manifest_file);

                if($fh_data==null || is_string($fh_data)){
                    print "<div style=\"color:red\">Manifest is corrupted</div>";
                    return 0;
                }

                //MAIN 	LOOP in manifest
                $not_found = true;  //true if manifest is empty

                foreach ($fh_data->children() as $f_gen){
                    if($f_gen->getName()=="items"){

                        $f_items = $f_gen;
                        $not_found = false;

                        $tot_files = count($f_gen->children());
                        $cnt_files = 0;

                        foreach ($f_gen->children() as $f_item){

                            $recordId	 = null;
                            $recordType  = RT_MEDIA_RECORD; //media by default
                            $recordURL   = null;
                            $recordNotes = null;
                            $el_heuristid = null;
                            $lat = null;
                            $lon = null;
                            $filename = null;
                            $filename_base = null;   //filename only
                            $details = array();
                            $file_id = null;
                            $old_md5 = null;

                            foreach ($f_item->children() as $el){  //$key=>$value

                                $content = strval($el);// (string)$el;
                                $key = $el->getName();

                                $value = $content;

                                if($key == "md5"){

                                    $old_md5 = $value;

                                }else if(@$fieldhelper_to_heurist_map[$key]){

                                    $key2 = $fieldhelper_to_heurist_map[$key];

                                    if($key2=="file"){

                                        $filename = $dir.$value;
                                        $filename_base = $value;

                                        $key3 = $fieldhelper_to_heurist_map['file_name'];
                                        if($key3>0){
                                            $details["t:".$key3] = array("1"=>$value);
                                        }
                                        $key3 = $fieldhelper_to_heurist_map['file_path'];
                                        if($key3>0){
                                            
                                            $relative_path = getRelativePath(HEURIST_FILESTORE_DIR, $dir);
                                            $details["t:".$key3] = array("1"=>$relative_path); //change to relative path
                                        }

                                    }else if($key2=="lat"){

                                        $lat = floatval($value);

                                    }else if($key2=="lon"){

                                        $lon = floatval($value);

                                    }else if($key2=="recordId"){
                                        $recordId = $value;
                                        $el_heuristid = $el;
                                    }else if(intval($key2)>0) {
                                        //add to details
                                        $details["t:".$key2] = array("1"=>$value);
                                    }// else field type not defined in this instance

                                }
                            }//for item keys


                            if($filename){
                                //exclude from the list of all files in this folder
                                if(in_array($filename_base, $all_files)){
                                    $ind = array_search($filename_base,$all_files,true);
                                    unset($all_files[$ind]);
                                }
                            }

                            if($recordId!=null){ //veify that this record exists
                                $res = mysql__select_array("Records", "rec_ID", "rec_ID=".$recordId);
                                if (!(is_array($res) && count($res)>0)){
                                    print  "<div>File: <i>$filename_base</i> was indexed as rec# $recordId. ".
                                    "This record was not found. File will be reindexed</div>";
                                    $recordId = null;
                                }
                            }

                            if($recordId==null){ //import only new

                                if($filename){

                                    if(file_exists($filename)){

                                        $currfile = $filename; //assign to global

                                        //add-update the uploaded file
                                        $file_id = register_file($filename, null, false);
                                        if(is_numeric($file_id)){
                                            $details["t:".$fileDT] = array("1"=>$file_id);

                                            //read EXIF data for JPEG images
                                            $recordNotes = readEXIF($filename);

                                        }else{
                                            print "<div>File: <i>$filename_base</i> <span  style=\"color:red\">".
                                            "Error: Failed to register. No record created</span></div>";
                                            $file_id = null;
                                        }

                                    }else{
                                        print "<div>File: <i>$filename_base</i> <span  style=\"color:red\">".
                                        "File is referenced in fieldhelper.xml but was not found on disk.".
                                        "No record was created.</span></div>";
                                    }
                                }

                                if(!$file_id){
                                    continue; //add with valid file only
                                }

                                if(is_numeric($lat) && is_numeric($lon) && ($lat!=0 || $lon!=0) ){
                                    $details["t:".$geoDT] = array("1"=>"p POINT ($lon $lat)");
                                }

                                //set title by default
                                if (!array_key_exists("t:".$titleDT, $details)){
                                    $details["t:".$titleDT] = array("1"=>$filename);
                                    print "<div>File: <i>$filename_base</i> <span  style=\"color:#ff8844\">".
                                    "Warning: there was no title recorded in the XML manifest for this file.".
                                    "Using file path + file name as title.</span></div>";
                                }

                                $new_md5 = null;
                                $key = $fieldhelper_to_heurist_map['md5'];
                                if($key>0){
                                    $new_md5 = md5_file($filename);
                                    $details["t:".$key] = array("1"=>$new_md5);
                                }

                                $out['error']='test';

                                //add-update Heurist record
                                $out = saveRecord($recordId, $recordType,
                                    $recordURL,
                                    $recordNotes,
                                    null, //???get_group_ids(), //group
                                    null, //viewable
                                    null, //bookmark
                                    null, //pnotes
                                    null, //rating
                                    null, //tags
                                    null, //wgtags
                                    $details,
                                    null, //-notify
                                    null, //+notify
                                    null, //-comment
                                    null, //comment
                                    null //+comment
                                );

                                if (@$out['error']) {
                                    print "<div>File: <i>$filename_base</i> <span  style='color:red'>Error: ".
                                    implode("; ",$out["error"])."</span></div>";
                                }else{
                                    if($new_md5==null){
                                        $new_md5 = md5_file($filename);
                                    }
                                    //update xml
                                    if($recordId==null){
                                        if($old_md5!=$new_md5){
                                            print "<div>File: <i>$filename_base</i> <span  style=\"color:#ff8844\">".
                                            "Warning: Checksum differs from value in manifest; ".
                                            "data file may have been changed</span></div>";
                                        }
                                        $f_item->addChild("heurist_id", $out["bibID"]);
                                        $f_item->addChild("md5", $new_md5);
                                        $f_item->addChild("filesize", filesize($filename));


                                        $rep_added++;
                                    }else{
                                        $el_heuristid["heurist_id"] = $out["bibID"];
                                        $rep_updated++;
                                    }

                                    if (@$out['warning']) {
                                        print "<div>File: <i>$filename_base</i> <span  style=\"color:#ff8844\">Warning: ".
                                        implode("; ",$out["warning"])."</span></div>";
                                    }

                                }
                                $rep_processed++;

                            }else{
                                $rep_ignored++;
                            }

                            $cnt_files++;
                            if ($cnt_files % 5 == 0) {
                                ob_start();
                                print '<script type="text/javascript">update_counts('.$progress_divid.','.$cnt_files
                                .','.$rep_processed.','.$tot_files.')</script>'."\n";
                                ob_flush();
                                flush();
                            }


                        }//for items
                    }//if has items
                }//for all children in manifest


                if($not_found){
                    print "<div style=\"color:red\">Manifest is either corrupted or empty</div>";
                }else{
                    if($rep_processed>0){
                        print "<div>$rep_processed files processed. $rep_added added. $rep_updated updated.</div>";
                    }
                    if($rep_ignored>0){
                        print "<div>$rep_ignored files already indexed.</div>";
                    }
                }


            }//manifest does not exists
            else{
                //create empty manifest XML  - TODO!!!!
                $s_manifest = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<fieldhelper version="1">
  <info>
    <AppName>Heurist</AppName>
    <AppVersion>v 3.0.0 2012-01-01</AppVersion>
    <AppCopyright>© ArtEresearch, University of Sydney</AppCopyright>
    <date></date>
  </info>
<formatOutput>1</formatOutput>
</fieldhelper>
XML;
                $fh_data = simplexml_load_string($s_manifest);
            }



            // add new empty items element
            if($f_items==null){
                $f_items = $fh_data->addChild("items");
            }

            $tot_files = count($all_files);
            $cnt_files = 0;
            $cnt_added = 0;

            //for files in folder that are not specified in the manifest file
            foreach ($all_files as $filename){
                if(!($filename=="." || $filename==".." || is_dir($dir.$filename) || $filename=="fieldhelper.xml")){

                    $filename_base = $filename;
                    $filename = $dir.$filename;
                    $currfile = $filename;
                    $flleinfo = pathinfo($filename);
                    $recordNotes = null;

                    //checks for allowed extensions
                    if(in_array(strtolower(@$flleinfo['extension']),$mediaExts))
                    {

                        $details = array();

                        $file_id = register_file($filename, null, false);
                        if(is_numeric($file_id)){
                            $details["t:".$fileDT] = array("1"=>$file_id);

                            //read EXIF data for JPEG images
                            $recordNotes = readEXIF($filename);

                        }else{
                            print "<div>File: <i>$filename_base</i> <span  style=\"color:#ff8844\">".
                            "Warning: failed to register. No record created for:  .$file_id</span></div>";
                            //$rep_issues = $rep_issues."<br/>Can't register file:".$filename.". ".$file_id;
                            $file_id = null;
                            continue;
                        }

                        $details["t:".$titleDT] = array("1"=>$flleinfo['basename']);
                        /* TODO - extract these data from exif
                        $details["t:".$descriptionDT] = array("1"=>$file_id);
                        $details["t:".$startdateDT] = array("1"=>$file_id);
                        $details["t:".$enddateDT] = array("1"=>$file_id);
                        $details["t:".$geoDT] = array("1"=>$file_id);
                        */

                        $new_md5 = md5_file($filename);
                        $key = $fieldhelper_to_heurist_map['md5'];
                        if($key>0){
                            $details["t:".$key] = array("1"=>$new_md5);
                        }
                        $key = $fieldhelper_to_heurist_map['file_name'];
                        if($key>0){
                            $details["t:".$key] = array("1"=>$flleinfo['basename']);
                        }
                        $key = $fieldhelper_to_heurist_map['file_path'];
                        if($key>0){

                            $targetPath = $flleinfo['dirname'];

                            $rel_path = getRelativePath(HEURIST_FILESTORE_DIR, $targetPath); //getRelativePath2($targetPath);
                            $details["t:".$key] = array("1"=>  $rel_path);

                            /*print "<div>".HEURIST_FILESTORE_DIR."</div>";
                            print "<div>file path :".$targetPath."</div>";
                            print "<div>relative path :".strpos($targetPath, HEURIST_FILESTORE_DIR)."--".$rel_path."</div>";
                            print "<div>relative path old :".getRelativePath(HEURIST_FILESTORE_DIR, $targetPath)."<br><br></div>";*/


                        }
                        $key = $fieldhelper_to_heurist_map['extension'];
                        if($key>0){
                            $details["t:".$key] = array("1"=>$flleinfo['extension']);
                        }
                        $key = $fieldhelper_to_heurist_map['filesize'];
                        if($key>0){
                            $details["t:".$key] = array("1"=>filesize($filename));
                        }

                        //add-update Heurist record
                        $out['error'] = 'test2';
                        $out = saveRecord(null, //record ID
                            RT_MEDIA_RECORD, //record type
                            null,  //record URL
                            $recordNotes,  //Notes
                            null, //???get_group_ids(), //group
                            null, //viewable
                            null, //bookmark
                            null, //pnotes
                            null, //rating
                            null, //tags
                            null, //wgtags
                            $details,
                            null, //-notify
                            null, //+notify
                            null, //-comment
                            null, //comment
                            null //+comment
                        );

                        $f_item = $f_items->addChild("item");
                        $f_item->addChild("filename", htmlspecialchars($flleinfo['basename']));
                        $f_item->addChild("nativePath", htmlspecialchars($filename));
                        $f_item->addChild("folder", htmlspecialchars($flleinfo['dirname']));
                        $f_item->addChild("extension", $flleinfo['extension']);
                        //$f_item->addChild("DateTime", );
                        //$f_item->addChild("DateTimeOriginal", );
                        $f_item->addChild("filedate", date("Y/m/d H:i:s.", filemtime($filename)));
                        $f_item->addChild("typeContent", "image");
                        $f_item->addChild("device", "image");
                        $f_item->addChild("duration", "2000");
                        $f_item->addChild("original_metadata", "chk");
                        $f_item->addChild("Name0", htmlspecialchars($flleinfo['basename']));
                        $f_item->addChild("md5", $new_md5);
                        $f_item->addChild("filesize", filesize($filename));


                        if (@$out['error']) {
                            print "<div>Fle: <i>$filename_base</i> <span style='color:red'>Error: ".implode("; ",$out["error"])."</span></div>";
                        }else{
                            $f_item->addChild("heurist_id", $out["bibID"]);
                            $cnt_added++;
                        }

                        $rep_processed_dir++;
                    }//check ext

                    $cnt_files++;

                    if ($cnt_files % 5 == 0) {
                        ob_start();
                        print '<script type="text/javascript">update_counts('.$progress_divid.','.$cnt_files.','.$cnt_added.','.$tot_files.')</script>'."\n";
                        ob_flush();
                        flush();
                    }

                }
            }//for files in folder that are not specified in the directory


            ob_start();
            if($rep_processed_dir>0){
                print "<div style=\"color:green\">$rep_processed_dir processed. $cnt_added records created (new entries added to manifests)</div>";
            }
            print '<script type="text/javascript">update_counts('.$progress_divid.','.$cnt_files.','.$cnt_added.',0)</script>'."\n";
            ob_flush();
            flush();

            if($rep_processed+$rep_processed_dir>0){
                //save modified xml (with updated heurist_id tags)
                $fh_data->formatOutput = true;
                $fh_data->saveXML($manifest_file);
            }

            return $rep_processed+$rep_processed_dir;
        }


        /**
        * Read EXIF from JPEG files
        *
        * @param mixed $filename
        */
        function readEXIF($filename){

            if(function_exists('exif_read_data') && file_exists($filename)){

                $flleinfo = pathinfo($filename);
                $ext = strtolower($flleinfo['extension']);

                if( $ext=="jpeg" || $ext=="jpg" || $ext=="tif" || $ext=="tiff" )
                {

                    $exif = exif_read_data($filename, 'IFD0');

                    if($exif===false){
                        return null;
                    }

                    $exif = exif_read_data($filename, 0, true);
                    return json_encode($exif);
                    /*
                    foreach ($exif as $key => $section) {
                    foreach  ($section as $name => $val) {
                    echo "$key.$name: $val<br />\n";
                    }
                    }*/
                }

            }else{
                return null;
            }

        }
        ?>
    </body>
</html>