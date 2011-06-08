<?php
	/**
	* File: createNewDB.php Create a new database by copying populateBlankDB.sql
	* Juan Adriaanse 10 Apr 2011
	* @copyright 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo Log person in as dbAdmin for new database, so they can go to the 'user administration page', without being redirected
	**/

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

	/* TODO: THIS MAY CAUSE PROBLEMS FOR THE INITIALISER OF THE SYSTEM SINCE THEY WILL NOT BE LOGGED INTO A DATABASE
	WE MIGHT HAVE TO FUDGE IT BY GIVING THEM A NOTIONAL LOGIN TO hdb_HeuristSystem or bypass this first time around */

	if(!is_logged_in()) {
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
		return;
	}
	if(!is_admin()) {
		print "<html><body><p>You need to be a system administrator to create a new database</p><p><a href=".HEURIST_URL_BASE.">Return to Heurist</a></p></body></html>";
		return;
	}
?>
<link rel=stylesheet href="../../common/css/global.css">

<html>
	<head>
		<meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
		<title>Create New Heurist Database</title>
	</head>
	<div class="banner"><h2>Create new Heurist database</h2></div>
	<div id="page-inner" style="overflow:auto">

	<body class="popup">
		<div id="createDBForm">
		<form action="createNewDB.php" method="POST" name="NewDBName">
			Enter a name for the new database. The prefix "<?= HEURIST_DB_PREFIX ?>" will be prepended before creating the database.<br /><br />
			<input type="text" maxlength="64" size="25" name="dbname">

			<input type="submit" name="submit" value="Create database" style="font-weight: bold;" onClick="makeDB()" >
			<br /><br /><div id="loading" style="display:none"><img src="loading.gif" width="16" height="16" /> <strong>&nbspCreating database, please wait...</strong></div>
		</form>
		</div>
	</body>
</html>

<?php
$newDBName = "";
$isNewDB = false; // Used by buildCrosswalks
global $errorCreatingTables; // Set to true by buildCrosswalks if error occurred

if(isset($_POST['dbname'])) {
	makeDatabase();
}

function makeDatabase() {
	global $newDBName, $isNewDB;
	$error = false;
	if(isset($_POST['dbname'])) {
		if(ADMIN_DBUSERNAME == "") {
			if(ADMIN_DBUSERPSWD == "") {
				echo "Admin username and password have not been set. Please do so before trying to create a new database.";
				echo '<script type="javascript/text">document.getElementById("loading").style.display = "none";</script>';
				return;
			}
			echo "Admin username has not been set. Please do so before trying to create a new database.";
			echo '<script type="javascript/text">document.getElementById("loading").style.display = "none";</script>';
			return;
		}
		if(ADMIN_DBUSERPSWD == "") {
			echo "Admin password has not been set. Please do so before trying to create a new database.";
			echo '<script type="javascript/text">document.getElementById("loading").style.display = "none";</script>';
			return;
		}
		// Create the new blank database
		$newDBName = $_POST['dbname'];
		$newname = HEURIST_DB_PREFIX . $newDBName; // all databases have common prefix

		$cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e'create database `$newname`'";
		$output1 = exec($cmdline . ' 2>&1', $output, $res1);
		
		// Test if creation was succesful
		if($res1 == 0) {
			// Create the Heurist structure for the newly created database, using the template SQL file
			$cmdline="mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -D$newname < populateBlankDB.sql";
			$output2 = exec($cmdline . ' 2>&1', $output, $res2);

			if($res2 == 0) {
				// Run buildCrosswalks to import all references data into the new DB, with newDB as true so it will skip the actual crosswalking
				$isNewDB = true;
				require_once('../structure/buildCrosswalks.php');
				
				// errorCreatingTables is set to true by buildCrosswalks if an error occurred
				if(!$errorCreatingTables) {
					// Add procedures and triggers
					$cmdline="mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -D$newname < addProceduresTriggers.sql";
					$output2 = exec($cmdline . ' 2>&1', $output, $res2);
					
					// No errors occurred, show succes message
					if($res2 == 0) {
						echo "New database '" . $newname . "' was created successfully. It is accessible at this URL: <a href=\"".HEURIST_URL_BASE."?db=".$newDBName."\" title=\"\">".HEURIST_URL_BASE."?db=".$newDBName."</a>.<br /><br />";
						echo "A default administrative user '<strong>dbAdmin</strong>' with password '<strong>none</strong>' has been created.<br /><br />";
						echo "You should change the password for this user immediately to something more secure, and add personal logins. Please visit the <a href='".HEURIST_URL_BASE."admin/adminMenu.php?db=".$newDBName."' title=''>user administration page</a>.";
						echo '<script type="text/javascript">document.getElementById("createDBForm").style.display = "none";</script>';
					}
				}
			}
			// If somewhere occurred a warning, proceed, but change feedback. If an error occurred: cancel, and delete erroneously created database
			if($res2 != 0) {
				$errorOrWarning = split(" ", $output2);
				if($errorOrWarning[0] == "Warning") {

					// Run buildCrosswalks to import all references data into the new DB, with newDB as true so it will skip the actual crosswalking
					$isNewDB = true;
					require_once('../structure/buildCrosswalks.php');

					if(!$errorCreatingTables) {
						$cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -D$newname < addProceduresTriggers.sql";
						$output2 = exec($cmdline . ' 2>&1', $output, $res2);
						
						// No errors occurred, one or more warnings did. Show succes message
						if($res2 == 0) {
							echo "New database '" . $newname . "' was created, but a warning was given:<br />";
							echo $output2 . "<br /><br />";
							echo "The new database is accessible at this URL: <a href=\"".HEURIST_URL_BASE."?db=".$newDBName."\" title=\"\">".HEURIST_URL_BASE."?db=".$newDBName."</a>.<br /><br />";
							echo "A default administrative user '<strong>dbAdmin</strong>' with password '<strong>none</strong>' has been created.<br /><br />";
							echo "You should change the password for this user immediately to something more secure, and add personal logins. Please visit the <a href='".HEURIST_URL_BASE."admin/adminMenu.php?db=".$newDBName."' title=''>user administration page</a>.";
							echo '<script type="text/javascript">document.getElementById("createDBForm").style.display = "none";</script>';
						} else {
							$error = true;
						}
					}
				}
				else {
					$error = true;
				}
			}
			// An error occurred somewhere. Show error message, and delete erroneously created database
			if($error) {
				echo '<script type="javascript/text">document.getElementById("loading").style.display = "none";</script>';
				echo "<strong>The database was not created. An error occurred during the creation process:</strong><br />";
				// Doesn't give output when errorcode is 2, so print general message.
				if($res2 == 2) {
					echo "Misuse of shell builtins (according to Bash documentation). Could be due to invalid DB name.";
				} else {
					echo $output2;
				}
				$cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e'drop database `$newname`'";
				exec($cmdline . ' 2>&1', $output, $res2);
			}
		} else { // An error occurred when creating the database (first step). Catch existing name error for more user friendly feedback, else, show error message
			$sqlErrorCode = split(" ", $output1);
			if($sqlErrorCode[1] == "1007") {
				echo "<strong>A database with that name already exists.</strong>";
			} else {
				$errorOrWarning2 = split(" ", $output1);
				if($errorOrWarning2[0] == "Warning") {
					echo "<strong>The database was not created. An warning was given trying to create the database:</strong><br />";
				} else {
					echo "<strong>An error occurred trying to create the database:</strong><br />";
				}
				echo $output1;
				echo '<script type="javascript/text">document.getElementById("loading").style.display = "none";</script>';
			}
		}
	}
}
?>
<script type="text/javascript">
	function makeDB() {
		document.getElementById("loading").style.display = "block";
		<?php makeDatabase(); ?>
	}
</script>
</div>