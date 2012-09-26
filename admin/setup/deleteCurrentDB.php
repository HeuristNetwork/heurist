<?php
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

	// User must be system administrator or admin of the owners group for this database
	if (!is_admin()) {
		print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap><div id=errorMsg><span>You must be logged in as system administrator to delete a database</span><p><a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME." target='_top'>Log out</a></p></div></div></body></html>";
		return;
	}

	$dbname = $_REQUEST['db'];

	if ($dbname=='H3ExampleDB') {// we require a valid DB incase the user deletes all DBs
				print "<p>Deleteion of H3ExampleDB is not supported through this interface.".
					"<p><a href='".HEURIST_URL_BASE."admin/adminMenu.php?db=H3ExampleDB' >Return to Heurist</a>";
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
				"<p><a href='".HEURIST_URL_BASE."?db=H3ExampleDB' >Return to Heurist</a>";
			}
		} else { // didn't request properly
			print "<p><h2>Request disallowed</h2>Incorrect challenge words entered. Database $dbname was not deleted. ".
			"<p><a href=".HEURIST_URL_BASE."?db=$dbname>Return to Heurist</a>";
		}
	}

?>





