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


	if (! is_admin()) {
		print "<html><body><p><h2>Request disallowed</h2>You must log in as an administrator of the database owners group to delete a database</p>".
		"<p><a href=".HEURIST_URL_BASE."?db=$dbname>Return to Heurist</a></p></body></html>";
		return;
	}

	$dbname = $_REQUEST['db'];

	if(!array_key_exists('mode', $_REQUEST)) {
		print "<html>";
		print "<head><meta content='text/html; charset=ISO-8859-1' http-equiv='content-type'>";
		print "<title>Delete Current Heurist Database</title>";
		print "<link rel='stylesheet' type='text/css' href='../../common/css/global.css'>";
		print "<link rel='stylesheet' type='text/css' href='../../common/css/admin.css'>";
		print "</head>";
		print "<body class='popup'>";
		print "<div class='banner'><h2>Delete Current Heurist database</h2></div>";
		print "<div id='page-inner' style='overflow:auto'>";
		print "<h2>!!!! DANGER- !!!! </h2>";
		print "<h1>DELETION OF CURRENT DATABASE</h1>";
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
			$cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e'drop database ".HEURIST_DB_PREFIX."$dbname'";
			$output2 = exec($cmdline . ' 2>&1', $output, $res2);
			if ($res2 != 0 ) {
				echo ("<h2>Warning:</h2> Unable to delete <b>".HEURIST_DB_PREFIX.$dbname."</b>");
				echo ("<p>Check that the database still exists. Consult Heurist helpdesk if needed<br>");
				echo($output2);
			} else {
				$uploadPath = HEURIST_UPLOAD_ROOT.$dbname;
				print "Database <b>$dbname</b> has been deleted";
				print "<p>Associated files are stored in <b>$uploadPath</b>. <p>These could be used by another program.".
				"To avoid danger of unintended data loss, these have not been deleted. Please delete this folder manually.".
				"<p><a href=".HEURIST_URL_BASE.">Return to Heurist</a>";
				// IT IS ALTOGETHER TOO RISKY TO DELETE DIRECTORIES WITH "rm -Rf .$uploadPath"
				// if something stuffs up it could delete every data directory for all databases on the system
				} 
		} else { // didn't request properly
			print "<p><h2>Request disallowed</h2>Incorrect challenge words entered. Database $dbname was not deleted. ".
			"<p><a href=".HEURIST_URL_BASE."?db=$dbname>Return to Heurist</a>";
		}
	}

?>





