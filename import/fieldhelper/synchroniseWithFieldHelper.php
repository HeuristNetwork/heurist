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
* brief description of file
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



	/* synchroniseWithFieldHelper.php - currently reads the FieldHelper XML manifests and creates Heurist records
	* Ian Johnson Artem Osmakov 18 Nov 2011
	* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @param includeUgrps=1 will output user and group information in addition to definitions
	* @param approvedDefsOnly=1 will only output Reserved and Approved definitions
	* @todo
	* @todo write Heurist IDs back into FH XML files
	* @todo update existing records from XML files which have changed
	* @todo update XML files from Heurist records which have changed
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
	document.getElementById("progress"+divid).innerHTML = (total==0)?"": (" <div style=\"color:green\"> Processed "+processed+" of "+total+". Added records: "+added+"</div>");
}
</script>

<?php
	if (! is_admin()) {
		print "<p>FieldHelper synchronisation requires you to be an adminstrator of the Database Managers group.</p></body></html>";
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

		mysql_connection_overwrite(DATABASE);
		if(mysql_error()) {
			die("Sorry, could not connect to the database (mysql_connection_overwrite error)");
		}

		//print "<h2>Import Files On Disk / FieldHelepr Manifests</h2>";
		print "<h2>ADVANCED USERS</h2>";
		print "This function reads FieldHelper (http://fieldhelper.org) XML manifest files from the folders (and their descendants) listed in Database > Advanced Properties<br>";
		print "and writes the metadata as records in the current database, with pointers back to the files described by the manifests. <br>";
		print "If no manifests exist, they are created (and can be read by FieldHelper). New files are added to the existing manifests.<br>&nbsp;<br>";
		print "The current database may already contain data; new records are appended, existing records are unaffected.<br>&nbsp;<br>";
		print "Note: the folders to be indexed must be writeable by the PHP system - normally they should be owned by 'nobody'";
		print "</p>";

		$notfound = array();
		foreach ($fieldhelper_to_heurist_map as $key=>$id){
			if(is_numeric($id) && $id==0){
				array_push($notfound, $key);
			}
		}
		if(count($notfound)>0){
			print "<p style='color:brown;'> Warning: There are no fields in this database to hold the following information: <b>".implode(", ",$notfound).
				"</b><br />Note: these fields may appear to be present, but do not have the correct origin codes (source of the field definition) for this function to use them.<br />".
				"We recommend importing the appropriate fields by (re)importing the Digital Media Item record type as follows".
				"<ul><li>Go to Designer View > Essentials > Import Structure<br>&nbsp;</li>".
				"<li>Navigate to the H3CoreDefinitions database<br>&nbsp;</li>".
				"<li>Check 'Show existing record types'</li>&nbsp;".
				"<li>Check 'Click the download icon on the Digital Media Item'</li></ul>".
				"You may proceed without this step, but these fields will not be imported</p>";
		}

		// ----FORM 1 - CHECK MEDIA FOLDERS --------------------------------------------------------------------------------

		if(!array_key_exists('mode', $_REQUEST)) {

			if(HEURIST_DBID==0){ //is not registered

				print "<p style=\"color:red\">Note: Database must be registered to use this function<br>".
				"Register the database using Designer View > Database > Registration - only available to the database creator/owner (user #2)</p>";

			}else{
				// Find out which folders to parse for XML manifests
				$query1 = "SELECT sys_MediaFolders, sys_MediaExtensions from sysIdentification where sys_ID=1";
				$res1 = mysql_query($query1);
				if (!$res1 || mysql_num_rows($res1) == 0) {
					die ("<p><b>Sorry, unable to read the sysIdentification from the current databsae. Possibly wrong database format, please consult Heurist team");
				}

				$row1 = $row = mysql_fetch_row($res1);
				$mediaFolders = $row1[0];
				$dirs = explode(';', $mediaFolders); // get an array of folders

				if($row1[1]==null){
					$mediaExts = "jpg,jpeg,png,gif,doc,docx,mp4";
					//array("jpg", "jpeg", "png", "gif", "doc", "docx", "mp4");
				}else{
					$mediaExts = $row1[1];
					//$mediaExts = explode(',', $row1[1]);
				}

				if ($mediaFolders=="" || count($dirs) == 0) {
					print ("<p><b>It seems that there are no media folders specified for this database</b>");
				}else{
					print "<p><b>Folders to scan :</b> $mediaFolders<p>";
					print "<p><b>Extensions to scan:</b> $mediaExts<p>";
				}
				print  "<p><a href='../../admin/setup/editSysIdentificationAdvanced.php?db=".HEURIST_DBNAME."&popup=1'>".
				"Set media folders</a><p>";


				if (!($mediaFolders=="" || count($dirs) == 0)) {
					print "<form name='selectdb' action='synchroniseWithFieldHelper.php' method='get'>";
					print "<input name='mode' value='2' type='hidden'>"; // calls the form to select mappings, step 2
					print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
					print "<input name='media' value='$mediaFolders' type='hidden'>";
					print "<input name='exts' value='$mediaExts' type='hidden'>";
					print "<input type='submit' value='Continue' />";
				}
			}
			exit;
		}

		// ----FORM 2 - MAPPINGS FORM ----------------------------------------------------------------------------------------

		/*
		if(array_key_exists('mode', $_REQUEST) && $_REQUEST['mode']=='2'){

		$mediaFolders = $_REQUEST['media'];

		print "<form name='mappings' action='synchroniseWithFieldHelper.php' method='post'>";
		print "<input name='mode' value='3' type='hidden'>"; // calls the transfer function
		print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
		print "<input name='media' value='$mediaFolders' type='hidden'>";

		print "Ready to process media folders: <b>$mediaFolders</b><p>";

		print "<input type='submit' value='Import data'><p><hr>\n";
		exit;
		}

		*/

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

			doHarvest($dirs);

			print "<div>Syncronization completed</div>";
			print "<div style=\"color:green\">Total records created: $rep_counter </div>";
			/*if($rep_issues!=""){
			print "<div>Problems: $rep_issues </div>";
			}*/
		}

		// ---- HARVESTING AND OTHER FUNCTIONS -----------------------------------------------------------------

		function doHarvest($dirs) {

			global $rep_counter, $rep_issues;

			foreach ($dirs as $dir){

				if(substr($dir, -1) != '/'){
						$dir .= "/";
				}

                if($dir == HEURIST_UPLOAD_DIR){

                    print "<div style=\"color:red\">It is not possible to scan root upload folder $dir</div>";

                }else if(file_exists($dir) && is_dir($dir))
				{
					$files = scandir($dir);
					if($files && count($files)>0)
					{
						$subdirs = array();

						$isfirst = true;

						foreach ($files as $filename){

							/*****DEBUG****///error_log("1>>>>".is_dir($filename)."  ".$filename);

							if(!($filename=="." || $filename=="..")){
								if(is_dir($dir.$filename)){
									array_push($subdirs, $dir.$filename."/");
								}else if($isfirst){ //if($filename == "fieldhelper.xml"){
									$isfirst = false;
									$rep_counter = $rep_counter + doHarvestInDir($dir);
								}
							}
						}

						if(count($subdirs)>0){
							doHarvest($subdirs);
							flush();
						}
					}
				}else{
					print "<div style=\"color:red\">Folder is not found: $dir</div>";
					//$rep_issues = $rep_issues."<br/>Directory $dir not found.";
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
			error_log("ERROR :".$message);

			$rep_issues = $rep_issues."<br/>Error save record for file:".$currfile.". ".$message;

			//print "{\"error\":\"" . addslashes($message) . "\"}";
			//exit(0);
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
			$rep_processed_dir = 0;
			$rep_ignored = 0;
			$f_items = null; //reference to items element

			$progress_divid++;

			print "<div><b>$dir</b><span id='progress$progress_divid'></span></div>";
			ob_flush();
			flush();

			if(!is_writable($dir)){
				//$rep_issues = "Folder ".$dir." is not writable. Check permissions";
				print "<div style=\"color:red\">Folder is not writeable. Check permissions</div>";
				return 0;
			}

			$manifest_file = $dir."fieldhelper.xml";

			$all_files = scandir($dir);

			/*****DEBUG****///

			if(file_exists($manifest_file)){

				//read fieldhelpe.xml
				if(is_readable($manifest_file)){

					//check write permission
					if(!is_writable($manifest_file)){
						print "<div style=\"color:red\">Manifest is not writable. Check permissions</div>";
						//$rep_issues = $rep_issues."<br/> Manifest is not writable in ".$dir;
						return 0;
					}
				}else{
					print "<div style=\"color:red\">Manifest is not readable. Check permissions</div>";
					//$rep_issues = $rep_issues."<br> manifest is not readable in ".$dir;
					return 0;
				}

				$fh_data = simplexml_load_file($manifest_file);

				if($fh_data==null || is_string($fh_data)){
					print "<div style=\"color:red\">Manifest is corrupted</div>";
					//$rep_issues = "Manifest file is corrupted";
					return 0;
				}

				//MAIN 	LOOP in manifest
				$not_found = true;

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
							$filename_base = null;
							$details = array();
							$file_id = null;
							$old_md5 = null;

							foreach ($f_item->children() as $el){  //$key=>$value

								$content = strval($el);// (string)$el;
								$key = $el->getName();


								$value = $content;
								/*foreach ($el as $key=>$value2){
								$value = $value2;
								break;
								}*/

								if($key == "md5"){

									$old_md5 = $value;

								}else if(array_key_exists($key,
											$fieldhelper_to_heurist_map) && $fieldhelper_to_heurist_map[$key]){

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
												$details["t:".$key3] = array("1"=>$dir);
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
											print "<div style=\"color:#ff8844\">warning $filename_base failed to register, no record created</div>";
											//$rep_issues = $rep_issues."<br/>Can't register file:".$filename.". ".$file_id;
											$file_id = null;
										}

									}else{
										print "<div style=\"color:#ff8844\">warning $filename_base file not found, no record created</div>";
									}
								}

								if(!$file_id){
									continue; //add with valid file only
								}

								if(is_numeric($lat) && is_numeric($lon) && ($lat!=0 || $lon!=0) ){
									$details["t:".$geoDT] = array("1"=>"p POINT($lon $lat)");
								}

								//set title by default
								if (!array_key_exists("t:".$titleDT, $details)){
									$details["t:".$titleDT] = array("1"=>$filename);
									print "<div style=\"color:#ff8844\">warning $filename_base no title</div>";
								}

								$new_md5 = null;
								$key = $fieldhelper_to_heurist_map['md5'];
								if($key>0){
									$new_md5 = md5_file($filename);
									$details["t:".$key] = array("1"=>$new_md5);
								}

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
									print "<div style='color:red'>$filename_base Error: ".implode("; ",$out["error"])."</div>";
								}else{
									if($new_md5==null){
										$new_md5 = md5_file($filename);
									}
									//update xml
									if($recordId==null){
										if($old_md5!=$new_md5){
											print "<div style=\"color:#ff8844\">warning $filename_base checksum differs from value in manifest</div>";
										}
										$f_item->addChild("heurist_id", $out["bibID"]);
										$f_item->addChild("md5", $new_md5);
										$f_item->addChild("filesize", filesize($filename));

									}else{
										$el_heuristid["heurist_id"] = $out["bibID"];
									}

									if (@$out['warning']) {
										print "<div style=\"color:#ff8844\">$filename_base Warning: ".implode("; ",$out["warning"])."</div>";
									}

									$rep_processed++;
								}

							}else{
								$rep_ignored++;
							}

							$cnt_files++;
							if ($cnt_files % 5 == 0) {
								print '<script type="text/javascript">update_counts('.$progress_divid.','.$cnt_files.','.$rep_processed.','.$tot_files.')</script>'."\n";
								ob_flush();
								flush();
							}


						}//for items
					}//if has items
				}//for all children in manifest


				if($not_found){
					print "<div style=\"color:red\">Manifest is either corrupted or empty</div>";
					//$rep_issues=$rep_issues."<br>folder $dir cotains corrupted or empty manifest file";
				}else{
					if($rep_processed>0){
						print "<div>$rep_processed records created</div>";
					}
					if($rep_ignored>0){
						print "<div>$rep_ignored files already indexed</div>";
						//$rep_issues=$rep_issues."<br> $rep_ignored entries in manifest are ignored for ".$dir;
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
<formatOutput>1</formatOutput></fieldhelper>
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

			//for files in folder that are not specified in the directory
			foreach ($all_files as $filename){
				if(!($filename=="." || $filename==".." || is_dir($dir.$filename) || $filename=="fieldhelper.xml")){

					/*****DEBUG****///error_log("2>>>>".is_dir($dir.$filename)."  ".$filename);

					$filename_base = $filename;
					$filename = $dir.$filename;
					$currfile = $filename;
					$flleinfo = pathinfo($filename);
                    $recordNotes = null;

					//checks for allowed extensions
					if(in_array(strtolower($flleinfo['extension']),$mediaExts))
					{

						$details = array();

						$file_id = register_file($filename, null, false);
						if(is_numeric($file_id)){
							$details["t:".$fileDT] = array("1"=>$file_id);

                          //read EXIF data for JPEG images
                          $recordNotes = readEXIF($filename);

						}else{
							print "<div style=\"color:#ff8844\">warning $filename_base failed to register, no record created:  .$file_id</div>";
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
							$details["t:".$key] = array("1"=>$flleinfo['dirname']);
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


						/*****DEBUG****///error_log(">>>>>".filemtime($filename)."  ".date("Y/m/d H:i:s.", filemtime($filename)));

						$f_item = $f_items->addChild("item");
						$f_item->addChild("filename", $flleinfo['basename']);
						$f_item->addChild("nativePath", $filename);
						$f_item->addChild("folder", $flleinfo['dirname']);
						$f_item->addChild("extension", $flleinfo['extension']);
						//$f_item->addChild("DateTime", );
						//$f_item->addChild("DateTimeOriginal", );
						$f_item->addChild("filedate", date("Y/m/d H:i:s.", filemtime($filename)));
						$f_item->addChild("typeContent", "image");
						$f_item->addChild("device", "image");
						$f_item->addChild("duration", "2000");
						$f_item->addChild("original_metadata", "chk");
						$f_item->addChild("Name0", $flleinfo['basename']);
						$f_item->addChild("heurist_id", $out["bibID"]);
						$f_item->addChild("md5", $new_md5);
						$f_item->addChild("filesize", filesize($filename));

						$rep_processed_dir++;


						$cnt_added++;

					}//check ext

					$cnt_files++;

					if ($cnt_files % 5 == 0) {
						print '<script type="text/javascript">update_counts('.$progress_divid.','.$cnt_files.','.$cnt_added.','.$tot_files.')</script>'."\n";
						ob_flush();
						flush();
					}

				}
			}//for files in folder that are not specified in the directory


			if($rep_processed_dir>0){
				print "<div style=\"color:green\">$rep_processed_dir records created (new entries added to manifest)</div>";
			}
			print '<script type="text/javascript">update_counts('.$progress_divid.','.$cnt_files.','.$cnt_added.',0)</script>'."\n";
			ob_flush();
			flush();

			if($rep_processed+$rep_processed_dir>0){
				//save modified xml (with updated heurist_id tags
				$fh_data->formatOutput = true;
				$fh_data->saveXML($manifest_file);
			}

			return $rep_processed+$rep_processed_dir;
		}

/**
* Read EXIF from JPEG files
*
* @param mixed $fielname
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