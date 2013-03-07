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


	/**
	* File: deleteCurrentDB.php Deletes the current database (owner group admins only)
	* Ian Johnson 24 Dec 2011
	* @copyright 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	*
	* **/

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

    if(isForAdminOnly("to delete a database")){
        return;
    }

	$dbname = $_REQUEST['db'];

	if ($dbname=='H3ExampleDB') {// we require a valid DB incase the user deletes all DBs
				print "<p>Deleteion of H3ExampleDB is not supported through this interface.".
					"<p><a href='".HEURIST_BASE_URL."admin/adminMenu.php?db=H3ExampleDB' >Return to Heurist</a>";
					return;
	}

	if(!array_key_exists('mode', $_REQUEST)) {
		print "<html>";
		print "<head><meta content='text/html; charset=ISO-8859-1' http-equiv='content-type'>";
		print "<title>Delete Current Heurist Database</title>";
		print "<link rel='stylesheet' type='text/css' href='../../common/css/global.css'>";
		print "<link rel='stylesheet' type='text/css' href='../../common/css/edit.css'>";
		print "<link rel='stylesheet' type='text/css' href='../../common/css/admin.css'>";
		print "</head>";
		print "<body class='popup'>";
		print "<div class='banner'><h2>Delete Current Heurist database</h2></div>";
		print "<div id='page-inner' style='overflow:auto'>";
		print "<h4 style='display:inline-block; margin:0 5px 0 0'><span><img src='../../common/images/url_error.png' /> DANGER <img src='../../common/images/url_error.png' /></span></h4><h1 style='display:inline-block'>DELETION OF CURRENT DATABASE</h1><br>";
		print "<h3>This will PERMANENTLY AND IRREVOCABLY delete the current database: </h3> <b>$dbname</b>";
		print "<form name='deletion' action='deleteCurrentDB.php' method='get'>";
		print "<p>Enter the words DELETE MY DATABASE in all-capitals to confirm that you want to delete the current database".
		"<p>Type the word above to confirm deletion: <input type='input' maxlength='20' size='20' name='del' id='del'>".
		"&nbsp;&nbsp;&nbsp;&nbsp;<input type='submit' value='OK to Delete' style='font-weight: bold;' >".
		"<input name='mode' value='2' type='hidden'>".
		"<input name='db' value='$dbname' type='hidden'>";
		print "</form></body></html>";
	}

	if(array_key_exists('mode', $_REQUEST) && $_REQUEST['mode']=='2') {
		if (array_key_exists('del', $_REQUEST) && $_REQUEST['del']=='DELETE MY DATABASE') {
			print "<p><hr>";
			if ($dbname=='') {
				print "<p>Undefined database name"; // shouldn't get here
			} else {
				// It's too risky to delete data with "rm -Rf .$uploadPath", could end up trashing stuff needed elsewhere, so we move it
				$uploadPath = HEURIST_UPLOAD_ROOT.$dbname; // individual deletio nto avoid risk of unintended disaster with -Rf
				$cmdline = "mv ".$uploadPath." ".HEURIST_UPLOAD_ROOT."deleted_databases";
				$output2 = exec($cmdline . ' 2>&1', $output, $res2);
				if ($res2 != 0 ) {
					echo ("<h2>Warning:</h2> Unable to move <b>$uploadPath</b> to the deleted files folder, perhaps a permissions problem or previously deleted.");
					echo ("<p>Please ask your system adminstrator to delete this folder if it exists.<br>");
					echo($output2);
				}
				$cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e'drop database ".HEURIST_DB_PREFIX."$dbname'";
				$output2 = exec($cmdline . ' 2>&1', $output, $res2); // this is the one we really care about
			}
			if ($res2 != 0 ) {
				echo ("<h2>Warning:</h2> Unable to delete <b>".HEURIST_DB_PREFIX.$dbname."</b>");
				echo ("<p>Check that the database still exists. Consult Heurist helpdesk if needed<br>");
				echo($output2);
			} else {
				print "Database <b>$dbname</b> has been deleted";
				print "<p>Associated files stored in upload subdirectories<b>$uploadPath</b> have ben moved to ".HEURIST_UPLOAD_ROOT."deleted_databases.".
				"<p>If you delete databases with a large volume of data, please ask your system administrator to empty this folder.".
				"<p><a href='".HEURIST_BASE_URL."?db=H3ExampleDB' >Return to Heurist</a>";
			}
		} else { // didn't request properly
			print "<p><h2>Request disallowed</h2>Incorrect challenge words entered. Database $dbname was not deleted. ".
			"<p><a href=".HEURIST_BASE_URL."?db=$dbname>Return to Heurist</a>";
		}
	}

?>





