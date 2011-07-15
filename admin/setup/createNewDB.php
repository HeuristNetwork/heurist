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
global $done; // Prevents the makeDatabase() script from running twice
$done = false;

if(isset($_POST['dbname'])) {
	makeDatabase();
}

function makeDatabase() {
	global $newDBName, $isNewDB, $done;
	if(!$done) {
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

		$hasDash = strpos($newname, "-");
		if($hasDash) {
			echo "<strong>Only letters, numbers and underscores (_) are allowed in the database name.</strong>";
			return false;
		}

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
				mysql_connection_db_insert(DATABASE);
				$query = mysql_query("SELECT ugr_LongName, ugr_FirstName, ugr_LastName, ugr_eMail, ugr_Name, ugr_Password, ugr_Department, ugr_Organisation, ugr_City, ugr_State, ugr_Postcode, ugr_Interests FROM sysUGrps WHERE ugr_ID=".get_user_id());
				$details = mysql_fetch_row($query);
				$longName = mysql_escape_string($details[0]);
				$firstName = mysql_escape_string($details[1]);
				$lastName = mysql_escape_string($details[2]);
				$eMail = mysql_escape_string($details[3]);
				$name = mysql_escape_string($details[4]);
				$password = mysql_escape_string($details[5]);
				$department = mysql_escape_string($details[6]);
				$organisation = mysql_escape_string($details[7]);
				$city = mysql_escape_string($details[8]);
				$state = mysql_escape_string($details[9]);
				$postcode = mysql_escape_string($details[10]);
				$interests = mysql_escape_string($details[11]);
				
				// errorCreatingTables is set to true by buildCrosswalks if an error occurred
				if(!$errorCreatingTables) {
					// Add procedures and triggers
					$cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -D$newname < addProceduresTriggers.sql";
					$output2 = exec($cmdline . ' 2>&1', $output, $res2);
					
					// No errors occurred, show succes message
					if($res2 == 0) {
						$cmdline = "mkdir -m a=rwx ../../../uploaded-heurist-files/".$newDBName;
						$output2 = exec($cmdline . ' 2>&1', $output, $res2);
						if($res2 != 0) {
							echo "<strong>An error occurred creating the uploaded files folder. Please manually create a folder in /uploaded-heurist-files/ with the name '".$newDBName."', and give it full read, write and execute rights:</strong><br />";
							echo($output2);
							echo "<br /><br /><hr /><br />";
						}
						mysql_connection_db_insert($newname);
						mysql_query('UPDATE sysUGrps SET ugr_LongName="'.$longName.'", ugr_FirstName="'.$firstName.'", ugr_LastName="'.$lastName.'", ugr_eMail="'.$eMail.'", ugr_Name="'.$name.'", ugr_Password="'.$password.'", ugr_Department="'.$department.'", ugr_Organisation="'.$organisation.'", ugr_City="'.$city.'", ugr_State="'.$state.'", ugr_Postcode="'.$postcode.'", ugr_interests="'.$interests.'" WHERE ugr_ID=2');
						echo "New database '<strong>" . $newname . "</strong>' was created successfully. It is accessible at this URL: <a href=\"".HEURIST_URL_BASE."?db=".$newDBName."\" title=\"\" target=\"_new\">".HEURIST_URL_BASE."?db=".$newDBName."</a>.<br /><br />";
						echo "Please visit the <a href='".HEURIST_URL_BASE."admin/adminMenu.php?db=".$newDBName."' title='' target=\"_new\">administration page</a>, to set up your new database.<br /><br />";
						echo "<strong>Note:</strong> The account you are logged in with at this moment, has been copied to your new database. This account's name and e-mail address will be shown as feedback details accross Heurist. If you do not wish to get e-mails on that address, you can change this and more by going to 'Access > Manage users/groups' on the administration page.<br /><br />";
						echo "<strong>Admin username:</strong> ".$name."<br />";
						echo "<strong>Admin password:</strong> &#60;<i>same as account currently logged in to</i>&#62;";
						echo '<script type="text/javascript">document.getElementById("createDBForm").style.display = "none";</script>';
						return false;
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
							$cmdline = "mkdir -m a=rwx ../../../uploaded-heurist-files/".$newDBName;
							$output2 = exec($cmdline . ' 2>&1', $output, $res2);
							if($res2 != 0) {
								echo "<strong>An error occurred creating the uploaded files folder. Please manually create a folder in /uploaded-heurist-files/ with the name '".$newDBName."', and give it full read, write and execute rights:</strong><br />";
								echo($output2);
								echo "<br /><br /><hr /><br />";
							}
							mysql_connection_db_insert($newname);
							mysql_query('UPDATE sysUGrps SET ugr_LongName="'.$longName.'", ugr_FirstName="'.$firstName.'", ugr_LastName="'.$lastName.'", ugr_eMail="'.$eMail.'", ugr_Name="'.$name.'", ugr_Password="'.$password.'", ugr_Department="'.$department.'", ugr_Organisation="'.$organisation.'", ugr_City="'.$city.'", ugr_State="'.$state.'", ugr_Postcode="'.$postcode.'", ugr_interests="'.$interests.'" WHERE ugr_ID=2');
							echo "New database '" . $newname . "' was created, but a warning was given:<br />";
							echo $output2 . "<br /><br />";
							echo "The new database is accessible at this URL: <a href=\"".HEURIST_URL_BASE."?db=".$newDBName."\" title=\"\" target=\"_new\">".HEURIST_URL_BASE."?db=".$newDBName."</a>.<br /><br />";
							echo "Please visit the <a href='".HEURIST_URL_BASE."admin/adminMenu.php?db=".$newDBName."' title='' target=\"_new\">administration page</a>, to set up your new database.<br /><br />";
							echo "<strong>Note:</strong> The account you are logged in with at this moment, has been copied to your new database. This account's name and e-mail address will be shown as feedback details accross Heurist. If you do not wish to get e-mails on that address, you can change this and more by going to 'Access > Manage users/groups' on the administration page.<br /><br />";
							echo "<strong>Admin username:</strong> ".$name."<br />";
							echo "<strong>Admin password:</strong> &#60;<i>same as account currently logged in to</i>&#62;";
							echo '<script type="text/javascript">document.getElementById("createDBForm").style.display = "none";</script>';
							return false;
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
					echo "Misuse of shell builtins (according to Bash documentation). Could be due to invalid DB name. Only letters, numbers, and underscores (_) are allowed.";
				} else {
					echo $output2;
				}
				$cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e'drop database `$newname`'";
				exec($cmdline . ' 2>&1', $output, $res2);
				$done = true;
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
}
?>
<script type="text/javascript">
	function makeDB() {
		document.getElementById("loading").style.display = "block";
		<?php makeDatabase(); ?>
	}
</script>
</div>