<?php

/*
* Copyright (C) 2005-2015 University of Sydney
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
* upgradeToNamedFiles.php
* copies file with jsut a number as the name to a new file with number + original name
* changing the file name frm 'n' to 00000n-origFileName and updating ulf_FilePath and ulf_FileName to this new path + name
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @param includeUgrps=1 will output user and group information in addition to definitions
* @param approvedDefsOnly=1 will only output Reserved and Approved definitions
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


	// NOTE: todo: This is jsut a utility which will not be needed any more once all databases have been updated


	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
	require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');

	if (! is_logged_in()) {
		header('Location: ' . HEURIST_BASE_URL_V3 . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}
	if (! is_admin()) {
		print "<html><body><p>Requires database owner privilege (admin of the owners group)</p><p><a href=".HEURIST_BASE_URL_V3.">Return to Heurist</a></p></body></html>";
		return;
	}
?>

<html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
	<title>Heurist - File storage updater</title>
</head>
<body>

<?php

mysql_connection_overwrite(DATABASE);
if(mysql_error()) {
	die("Sorry, could not connect to the database (mysql_connection_overwrite error)");
}

print "<h2>Heurist File Storage Updater</h2>";
print "<h2>FOR  ADVANCED USERS ONLY</h2>";
print "This script converts a Heurists vsn 3 database from storing files as simple numbered untyped files to storing them with original file names. ";
print "The files are copied in the HEURIST_FILESTORE directory for the database and given a combination of their ID plus their original name ";
print "The new file name and the storage path are written into the recUploadedFiles table, allowing files from other locations to be added to the table";
print "We have to watch out that the existing code will continue to create files which just have untyped number representations until fixed<br>";
print "</b><p>\n";


// ----FORM 1 - MESSAGE PLUS ANYTHING ELSE WE NEED TO DO UP FRONT --------------------------------------------------------------------------------

if (!array_key_exists('mode', $_REQUEST)){

	print "<form name='selectdb' action='upgradeToNamedFiles.php' method='get'>";
	print "<input name='mode' value='2' type='hidden'>"; // calls the form to select mappings, step 2
	print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
	print "<p>Kill this script if you don't know what it does!<p>";
	print "<input type='submit' value='Continue' />";
	exit;
}

// ----FORM 2 - MAPPINGS FORM ----------------------------------------------------------------------------------------

$dbname = $_REQUEST['db'];

if(array_key_exists('mode', $_REQUEST) && $_REQUEST['mode']=='2'){

	/* This would have been best, but decided to leave in original location to simplify upgrade
	$make_directory = "mkdir " . HEURIST_UPLOAD_ROOT.HEURIST_DBNAME."/attached-files"; // new fiolder for the copied files
	print "<br>Creating new directory with: $make_directory<p>";
	exec("$make_directory" . ' 2>&1', $output, $res1);
	if ($res1 != 0 ) {
	die ("<p>Sorry, unable to create directory");
	}
	*/

	print "<p>About to process the database files ...</p>";

	$query1 = "alter table recUploadedFiles ".
	"ADD ulf_FilePath varchar(1024) default NULL COMMENT 'The path where the uploaded file is stored', ".
	"ADD ulf_FileName varchar(512) default NULL COMMENT 'The filename for the uploaded file'"; // new fields to hold path and updated filename
	$res1 = mysql_query($query1);
	if (!$res1) {
		print ("<p><b>Sorry, unable to alter table structure, assume it has been done already ?? </b>");
	} else {
		print "Updated the database structure, added ulf_FilePath and ulf_FileName<br>";
	};

	$query1 = "SELECT * from recUploadedFiles"; // get a list of all the files
	$res1 = mysql_query($query1);
	if (!$res1 || mysql_num_rows($res1) == 0) {
		die ("<p><b>Sorry, no uploaded files");
	}
	else {
		print "<p>Number of files to process: ".mysql_num_rows($res1)."<br>";
	}

	while ($row1 = mysql_fetch_assoc($res1)) {
		$ulf_ID=$row1['ulf_ID']; // current name of file in the uploaded files directory for thsi database
		$ulf_OrigFileName=$row1['ulf_OrigFileName']; // the original file name

		$newPath = HEURIST_UPLOAD_ROOT.HEURIST_DBNAME."/"; // path to allow multiple directories
		$newName = "ulf_".$ulf_ID."_".$ulf_OrigFileName; // new name with path to allow multiple directories
		$oldName=HEURIST_UPLOAD_ROOT.HEURIST_DBNAME."/".$ulf_ID;

		// TODO: Needs to replace quotes in the file name to avoid future problems, quotes are nasty!

		$copy_file="cp ".$oldName."    '".$newPath.$newName."'"; // quotes solve problem with spaces in file name, but not 's

		print "."; // progress marker
		exec("$copy_file" . ' 2>&1', $output, $res2);
		if ($res2 != 0 ) {
			print "<br>Copying and renaming '$oldName'";
			print "<br>Command: $copy_file";
			print "<br>Unable to copy this file - check to see if the file exists<br>";
		} else { // successful copy, rewrite entry in database
			$queryUpdatePath = "update recUploadedFiles set ulf_FilePath = '$newPath' where ulf_ID = $ulf_ID";
			$queryUpdateName = "update recUploadedFiles set ulf_FileName = '$newName' where ulf_ID = $ulf_ID";
			$res2 = mysql_query($queryUpdatePath);
			$res3 = mysql_query($queryUpdateName);
			if (!$res3) { // one check will do, suirely
				die ("<p><b>Sorry, unable to update the new file name in the recUploaded files table");
			} // failed sql update
		} // successful copy

	} // loop through files in the uploaded files table

	print "<h2>I've finished!</h2>";

} // end of second time around

?>

</body>
</html>
