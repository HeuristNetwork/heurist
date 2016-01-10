<?php

/*
* Copyright (C) 2005-2016 University of Sydney
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
*  File: configureNewInstallation.php Set up required data and databases for a new Heurist installation, Ian Johnson 22 Nov 2011
*
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

	/* This file should be called if Heurist attempts to access a database through index.php
	and there are no Heurist databases, indicating that the installation has not yet been fully configured
    TODO: it is not currently called and has not been tested (Feb 2014) */

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
	<head>
		<title>Heurist New Installation configuration</title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
	</head>
	</body>

	<?php

	    $isNewInstallation = 1;

		require_once(dirname(__FILE__).'../../../common/config/initialise.php');


		print "<h2>Heurist initial setup</h2>";
		print "No Heurist databases were found. Heurist databases are identified by the prefix defined in your (heurist)configIni.php file ".
            "(<b> ".HEURIST_DB_PREFIX." </b>)";
		print "<p>This function will create Heurist_Sandpit which is used to register new users, along with the root directory for uploaded files (<b> ".HEURIST_UPLOAD_ROOT." </b>)";
		print "<p>";

		if(!array_key_exists('mode', $_REQUEST)) {
			print "If there are already Heurist databases with a different prefix, if you want to change the prefix ".
                "or directory for uploads, click [Cancel]";
			print "<hr>";
			print "<form name='one' action='configureNewInstallation.php' method='get'>";
			print "<input name='mode' value='2' type='hidden'>"; // calls step 2
			print "<p>Check information above, then click: <input name='ok' type='submit' value='Continue' />";
			print "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input name='quit' type='submit' value='Cancel' />";
			exit;
		}

		if(array_key_exists('mode', $_REQUEST) && $_REQUEST['mode']=='2' && $_REQUEST['ok']=='Continue') {

			print "<hr><b>Progress:</b>";
			// CREATE ROOT UPLOAD DIRECTORY
			$output1 = exec("ls ".HEURIST_UPLOAD_ROOT.' 2>&1', $output, $res1); // test for directory
			if ($res1 != 0) { // create new directory
				$output1 = exec("mkdir ".HEURIST_UPLOAD_ROOT.' 2>&1', $output, $res1);
				if ($res1 != 0) {
				echo ("<h2>Warning:</h2> Unable to create root upload directory <b>".HEURIST_UPLOAD_ROOT."</b>");
				echo ("<br>Either this is an invalid path or permissions do not permit user 'nobody' to create a directory in this location<br>");
				echo("<br>Reported error: ".$output1);
				echo ("<p>Please create the directory by hand and set ownership/group to nobody/nobody <br>and permissions to rwxrwx--- then rerun Heurist");
				exit;
				} else {
				echo("Created root upload directory <b>".HEURIST_UPLOAD_ROOT."</b>");
				}
			} else {
				print "<p>Directory <b>".HEURIST_UPLOAD_ROOT."</b> already exists, proceeding to next step";
			}


			// CREATE .htaccess CONTROL FILE IN ROOT DIRECTORY
			$output1 = exec("cp .htaccess_model ".HEURIST_UPLOAD_ROOT.'.htaccess 2>&1', $output, $res1);
			if ($res1 != 0) { // unable to copy htaccess file
				echo ("<h2>Warning:</h2> Unable to copy .htaccess file to <b>".HEURIST_UPLOAD_ROOT."</b>");
				echo ("<br>Either this is an invalid path or permissions do not permit user 'nobody' to copy to this location, or it already exists<br>");
				echo("<br>Reported error: ".$output1);
				echo ("<p>Please copy the .htaccess file from /admin/setup or set Apache configuration according to Heurist installation instructions");
				} else {
				echo("<p>Copied .htaccess file to <b>".HEURIST_UPLOAD_ROOT."</b>");
				}

			// CREATE Heurist_Sandpit FROM WHICH USERS CAN START TO GENERATE NEW DATABASES
			$newname=HEURIST_DB_PREFIX.Heurist_Sandpit;
			if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
				$cmdline = "mysql -h".HEURIST_DBSERVER_NAME." -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e\"create database `$newname`\"";
			} else {
				$cmdline = "mysql -h".HEURIST_DBSERVER_NAME." -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e'create database `$newname`'";
			}
			$output1 = exec($cmdline . ' 2>&1', $output, $res1); // create database
			if ($res1 != 0 ) {
				echo("<p>SQL error: $output1");
				$sqlErrorCode = split(" ", $output1);
				if(count($sqlErrorCode) > 1 &&  $sqlErrorCode[1] == "1007") {
					echo "<strong><p>A database with that name already exists. The work of this script is complete.<hr></strong>";
					print "<h2>done ...</h2>";
					print "<p>You can now run Heurist and open the database <a href='../../../index.php?db=Heurist_Sandpit'>Heurist_Sandpit</a> with user name 'guest' + password 'guest' ";
					print "<p>You can create new databases when logged into the example database (DB Admin > Database > New Database).";
					print "<p>Don't forget to change the user name and password as your login information is carried over to any new database you create";
				}
				exit; // unable to create database
				} else {
					echo("<p>Empty database $newname created successfully");
				}

			$cmdline="mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -D$newname < buildExampleDB.sql"; // full mysqldump specification of the db
			$output2 = exec($cmdline . ' 2>&1', $output, $res2);
			if ($res2 != 0 ) {
				echo ("<p>Error $res2 on MySQL exec: Unable to load buildExampleDB.sql into database $newname");
				echo ("<br>Please check whether this file is valid; consult Heurist helpdesk if needed");
				echo($output2);
				exit;
			}
			print "<h2>success ...</h2>";
			print "<p>You can now run Heurist and open the database <a href='../../../index.php?db=Heurist_Sandpit'>Heurist_Sandpit</a> with user name 'guest' + password 'guest' ";
			print "<p>You can create new databases when logged into the example database (DB Admin > Database > New Database).";
			print "<p>Don't forget to change the user name and password as your login information is carried over to any new database you create";
			exit; // successfully completed

		} else {

			print "<h2>Cancelled</h2> Please edit the (heurist)configIni.php file and set the required values, then run Heurist again";
		}

	?>

	</body>
</html>

