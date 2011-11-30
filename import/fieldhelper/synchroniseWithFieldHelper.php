<?php

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
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}
	if (! is_admin()) {
		print "<html><body><p>FieldHelper synchronisation requires you to be an adminstrator of the database owners group</p><p>".
		"<a href=".HEURIST_URL_BASE.">Return to Heurist</a></p></body></html>";
		return;
	}


$titleDT = (defined('DT_TITLE')?DT_TITLE:0);
$geoDT = (defined('DT_GEO_OBJECT')?DT_GEO_OBJECT:0);
$fileDT = (defined('DT_ASSOCIATED_FILE')?DT_ASSOCIATED_FILE:0);
$startdateDT = (defined('DT_START_DATE')?DT_START_DATE:0);
$enddateDT = (defined('DT_END_DATE')?DT_END_DATE:0);
$descriptionDT = (defined('DT_NOTES')?DT_NOTES:0);

$fieldhelper_to_heurist_map = array(
	"heurist_id" => "recordId",
	"Name0" => $titleDT,
	"Annotation" => $descriptionDT,
	"DateTimeOriginal" => $startdateDT,
	"filename" => "file",
	"latitude" => "lat",
	"longitude" => "lon"
);

$rep_counter = null;
$rep_issues = null;
$currfile = null;

?>
<html>
	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title>Heurist - FieldHelper synchronisation</title>
	</head>


	<?php

		mysql_connection_db_overwrite(DATABASE);
		if(mysql_error()) {
			die("Sorry, could not connect to the database (mysql_connection_db_overwrite error)");
		}

		print "<h2>Heurist - FieldHelper synchronisation</h2>";
		print "<h2>FOR  ADVANCED USERS ONLY</h2>";
		print "This script reads FieldHelper XML manifest files from the folders (and their descendants) listed in the sysIdentification record ";
		print "and writes the metadata as records into the current logged-in database, with pointers back to the files described by the manifest. ";
		print "The current database can already contain data, new records are appended.";

		print "<p> At this time, synchronisation is a one-shot, one way import, from FieldHelper to Heurist".
		"<br>Later this function will do two-way synchronsiation";
		print "<br>";


		// ----FORM 1 - CHECK MEDIA FOLDERS --------------------------------------------------------------------------------

		if(!array_key_exists('mode', $_REQUEST)) {

			// Find out which folders to parse for XML manifests
			$query1 = "SELECT sys_MediaFolders from sysIdentification where sys_ID=1";
			$res1 = mysql_query($query1);
			if (!$res1 || mysql_num_rows($res1) == 0) {
				die ("<p><b>Sorry, unable to read the sysIdentification from the current databsae. Possibly wrong database format, please consult Heurist team");
			}

			$row1 = $row = mysql_fetch_row($res1);
			$mediaFolders = $row1[0];
			$dirs = explode(';', $mediaFolders); // get an array of folders

			if ($mediaFolders=="" || count($dirs) == 0) {
				print ("<p><b>It seems that there are no media folders specified for this database</b>");
			}else{
				print "<p><b>Media folders for harvesting:</b> $mediaFolders<p>";
			}
			print  "<p><a href='../../admin/setup/editSysIdentificationAdvanced.php?db=".HEURIST_DBNAME."' target='_blank'>".
			"<img src='../../common/images/external_link_16x16.gif'>Set media folders</a><p>";


			if (!($mediaFolders=="" || count($dirs) == 0)) {
				print "<form name='selectdb' action='synchroniseWithFieldHelper.php' method='get'>";
				print "<input name='mode' value='2' type='hidden'>"; // calls the form to select mappings, step 2
				print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
				print "<input name='media' value='$mediaFolders' type='hidden'>";
				print "<input type='submit' value='Continue' />";
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

			$rep_counter = 0;
			$rep_issues = "";

			harvest($dirs);

			print "<div>Syncronization completed<div>";
			print "<div>Imported: $rep_counter records<div>";
			if($rep_issues!=""){
			    print "<div>Problems: $rep_issues <div>";
			}
		}

    // ---- HARVESTING AND OTHER FUNCTIONS -----------------------------------------------------------------

    function harvest($dirs) {

    	global $rep_counter, $rep_issues;

		foreach ($dirs as $dir){
			if(file_exists($dir) && is_dir($dir))
			{
				$files = scandir($dir);
				if($files && count($files)>0)
				{
					$subdirs = array();

					foreach ($files as $filename){

//error_log("1>>>>".is_dir($filename)."  ".$filename);

						if(!($filename=="." || $filename=="..")){
							if(is_dir($dir.$filename)){
								array_push($subdirs, $dir.$filename."/");
							}else if($filename == "fieldhelper.xml"){
								$rep_counter = $rep_counter + harvest_in_dir($dir);
							}
						}
					}

					if(count($subdirs)>0){
						harvest($subdirs);
					}
				}
			}else{
				$rep_issues = $rep_issues."<br/>Directory $dir not found.";
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
    function harvest_in_dir($dir) {

	    global $rep_issues, $fieldhelper_to_heurist_map,
		    $geoDT, $fileDT;

		$rep_processed = 0;

	    $manifest_file = $dir."fieldhelper.xml";

error_log(">>>>>>in dir: ".$manifest_file);

	    //read fieldhelpe.xml
	    if(is_readable($manifest_file)){

    	//check write permission
    	if(is_writable($manifest_file)){

		    $fh_data = simplexml_load_file($manifest_file);
		    $not_found = true;

		    foreach ($fh_data->children() as $f_gen){
			if($f_gen->getName()=="items"){
			    $not_found = false;
			    $rep_ignored = 0;

			    foreach ($f_gen->children() as $f_item){

				$recordId	 = null;
				$recordType  = RT_MEDIA_RECORD; //media by default
				$recordURL   = null;
				$recordNotes = null;
				$el_heuristid = null;
				$lat = null;
				$lon = null;
				$filename = null;
				$details = array();
				$file_id = null;

				foreach ($f_item->children() as $el){  //$key=>$value

					$content = "$el";
					$key = $el->getName();
					$value = $content;
					/*foreach ($el as $key=>$value2){
					$value = $value2;
					break;
					}*/

					if(array_key_exists($key,
						$fieldhelper_to_heurist_map)){

						$key = $fieldhelper_to_heurist_map[$key];

						if($key=="file"){
							$filename = $dir.$value;

						}else if($key=="lat"){

							$lat = floatval($value);

						}else if($key=="lon"){

							$lon = floatval($value);

						}else if($key=="recordId"){
							$recordId = $value;
							$el_heuristid = $el;
						}else if(intval($key)>0) {
							//add to details
							$details["t:".$key] = array("1"=>$value);
						}

					}
				}//for item


			    if($recordId==null){ //import only new

				if($filename){

					$currfile = $filename;

				    //add-update the uploaded file
				    $file_id = register_file($filename, null, false);
				    if(is_numeric($file_id)){
						$details["t:".$fileDT] = array("1"=>$file_id);
				    }else{
						$rep_issues = $rep_issues."<br/>Can't register file:".$currfile.". ".$file_id;
						$file_id = null;
					}
				}

				if(!$file_id){
				    continue; //add with valid file only
				}

				if(is_numeric($lat) && is_numeric($lon) && ($lat!=0 || $lon!=0) ){
				    $details["t:".$geoDT] = array("1"=>"p POINT($lon $lat)");
				}

//error_log(">>>>>>details: ".print_r($details, true));

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

				//update xml
				if($recordId==null){
					$f_item->addChild("heurist_id", $out["bibID"]);
				}else{
					$el_heuristid["heurist_id"] = $out["bibID"];
				}

					$rep_processed++;

				}else{
					$rep_ignored++;
				}

				}//for items
			}//if has items
			}//for

		    if($not_found){
				$rep_issues=$rep_issues."<br>folder $dir cotains corrupted or empty manifest file";
		    }else{
				if($rep_ignored>0){
			    	$rep_issues=$rep_issues."<br> $rep_ignored entries are ignored for ".$dir;
				}

				//save modified xml (with updated heurist_id tags
				$fh_data->formatOutput = true;
				$fh_data->saveXML($manifest_file);
		    }


		}else{
	    	$rep_issues = $rep_issues."<br/> Manifest is not writable in ".$dir;
		}
		}else{
	    	$rep_issues=$rep_issues."<br> manifest is not exist or not readable in ".$dir;
		}

		return $rep_processed;
    }
?>
	</body>
</html>