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
	if (! is_logged_in()) {
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}
	if (! is_admin()) {
		print "<html><body><p>FieldHelper synchronisation requires you to be an adminstrator of the database owners group</p><p>".
		"<a href=".HEURIST_URL_BASE.">Return to Heurist</a></p></body></html>";
		return;
	}
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


		// ----FORM 1 - SELECT THE SOURCE DATABASE --------------------------------------------------------------------------------

		if(!array_key_exists('mode', $_REQUEST)) {

			print "<form name='selectdb' action='synchroniseWithFieldHelper.php' method='get'>";
			print "<input name='mode' value='2' type='hidden'>"; // calls the form to select mappings, step 2
			print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";

			// Find out which folders to parse for XML manifests
			$query1 = "SELECT sys_MediaFolders from sysIdentification where sys_ID=1";
			$res1 = mysql_query($query1);
			if (!$res1 || mysql_num_rows($res1) == 0) {
				die ("<p><b>Sorry, unable to read the sysIdentification from the current databsae. Possibly wrong database format, please consult Heurist team");
			}
			$row1 = mysql_fetch_assoc($res1);
			$mediaFolders=$row1['sys_MediaFolders'];
			print "<p><b>Media folders for harvesting:</b> $mediaFolders<p>";
			$dirs=explode(',',$mediaFolders); // get an array of folders

			print  "<p><a href='../../admin/setup/editSysIdentificationAdvanced.php?db=".HEURIST_DBNAME."' target='_blank'>".
			"<img src='../../common/images/external_link_16x16.gif'>Set media folders</a><p>";

			if (count($mediaFolders) == 0) {
				die ("<p><b>It seems that there are no media folders specified for this database</b>");
			}

			print "<input name='media' value='$mediaFolders' type='hidden'>";
			print "<input type='submit' value='Continue' />";
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
			harvest($mediaFolders);
		}


		// ---- HARVESTING AND OTHER FUNCTIONS -----------------------------------------------------------------

		function harvest($mediaFolders) {

			print "<p>Now harvesting FieldHelper metadata into <b> ". HEURIST_DBNAME. "</b><br> ";

			$dir = strtok($mediaFolders,',;'); // gets first path

			print "<p>This function is under development @ 18 Nov 2011, please check back soon<p>";

			/*
			while ($dir) {
				while (<<subdirectoriesloop here>>) {
					print "<br><b>$dir/fieldhelper.xml</b><br>";

					if (!file_exists('$dir/fieldhelper.xml')) {
						// WHY does it require a negation? without negation it warns if the file is there!!!
						// does nothing when it encoutners a missing file, does not print warning

						// Read in XML manifest for this subdirectory
						$xml = simplexml_load_file("$dir/fieldhelper.xml"); // read in the XML manifest

						// todo: Now need to do the real business of extracting the metadfata and creating records
						// print_r($xml);

						// loop through items (fiels) in manifest, extract fielname and otehr metadata, create record, write metadata
						foreach ($xml->children('items') as $items) { // there should only be one lsit of items
							foreach ($items->children('item') as $item) { // loop through all items (=files in the manifest)
    							print "item filename = ".$item['filename'];
							} // loop through item
						} // loop through items

						} else {
							print "<b>WARNING</b> Unable to find $dir/fieldhelper.xml manifest file, directory ignored";
						}

				} // subdirectory loop

				$dir = strtok(',;'); // gets the next path

			} // while there are still specified media directories to process

			*/


		} // harvest function

	?>

	</body>
</html>
