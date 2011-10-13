<?php
	/**
	* File: createNewDB.php Create a new database by applying blankDBStructure.sql and coreDefinitions.txt
	* Juan Adriaanse 10 Apr 2011
	* @copyright 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	*
	* Extensively modified 4/8/11 by Ian Johnson to reduce complexity and load new database in
	* a series of files with checks on each stage and cleanup code
	*
	* **/

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

	if(!is_logged_in()) {
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}
	if(!is_admin()) {
		print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap><div id=errorMsg><span>You need to be a system administrator to create a new database</span><p><a href=".HEURIST_URL_BASE." target='_top'>Return to Heurist</a></p></div></div></body></html>";
		return;
	}
?>



<html>
	<head>
		<meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
		<title>Create New Heurist Database</title>
		<link rel="stylesheet" type="text/css" href="../../common/css/global.css">
		<link rel="stylesheet" type="text/css" href="../../common/css/admin.css">
	</head>
	<style>
		.detailType {width:180px !important}
	</style>
	<body class="popup">
		<div class="banner"><h2>Create new Heurist database</h2></div>
		<div id="page-inner" style="overflow:auto">

		<h3>Suggested workflow for new databases:</h3>
		<?php include("includeNewDatabaseWorkflow.html"); ?>
		<div class="separator_row" style="margin:20px 0;"></div>


		<div id="createDBForm">
			<form action="createNewDB.php?db=<?= HEURIST_DBNAME ?>" method="POST" name="NewDBName">
				<p>New databases are created on the current server.<br>
					You will become the owner and administrator of the new database.<br>
					The database will be created with the prefix "<?= HEURIST_DB_PREFIX ?>"
				(all databases created by this installation of the software will have the same prefix).</p>
				<h3>Enter a name for the new database:</h3>
				<div style="margin-left: 40px;">
					<input type="text" maxlength="64" size="25" name="dbname">
					<input type="submit" name="submit" value="Create database" style="font-weight: bold;" >
				</div>
				<br /><br /><div id="loading" style="display:none"><img src="../../common/images/mini-loading.gif" width="16" height="16" /> <strong>&nbspCreating database, please wait...</strong></div>
			</form>
		</div>
	</body>
</html>

<?php
	$newDBName = "";
	$isNewDB = false; // Used by buildCrosswalks to detemine whether to get data from coreDefinitions.txt (for new database)
	// or by querying an existing Heurist database using getDBStructure (for crosswalk)

	global $errorCreatingTables; // Set to true by buildCrosswalks if error occurred
	global $done; // Prevents the makeDatabase() script from running twice
	$done = false; // redundant

	//error_log(" post dbname: $_POST['dbname'] ");  //debug

	if(isset($_POST['dbname'])) {
		makeDatabase(); // this does all the work
	}


	function isInValid($str) {
		return preg_match('[\W]', $str);
	}

	function cleanupNewDB ($newname) { // called in case of failure to remove the partially created database
		global $newDBName, $isNewDB, $done;
		$cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e'drop database `$newname`'";
		$output2=exec($cmdline . ' 2>&1', $output, $res2);
		echo "<br>Database cleanup for $newname, completed<br>&nbsp;<br>";
		echo($output2);
		$done = true;
	} // cleanupNewDB



	function makeDatabase() { // Creates a new database and populates it with triggers, constraints and core definitions
		global $newDBName, $isNewDB, $done;
		$error = false;
		$warning=false;

		if (isset($_POST['dbname'])) {

			// Check that there is a current administrative user  who can be made the owner of the new database
			if(ADMIN_DBUSERNAME == "") {
				if(ADMIN_DBUSERPSWD == "") {
					echo "Admin username and password have not been set. Please do so before trying to create a new database.<br>";
					echo '<script type="javascript/text">document.getElementById("loading").style.display = "none";</script>';
					return;
				}
				echo "Admin username has not been set. Please do so before trying to create a new database.<br>";
				echo '<script type="javascript/text">document.getElementById("loading").style.display = "none";</script>';
				return;
			}
			if(ADMIN_DBUSERPSWD == "") {
				echo "Admin password has not been set. Please do so before trying to create a new database.<br>";
				echo '<script type="javascript/text">document.getElementById("loading").style.display = "none";</script>';
				return;
			} // checking for current administrative user

			// Create a new blank database
			$newDBName = $_POST['dbname'];
			$newname = HEURIST_DB_PREFIX . $newDBName; // all databases have common prefix

			// Avoid illegal chars in db name
			$hasInvalid = isInValid($newname);
			if ($hasInvalid) {
				echo ("Only letters, numbers and underscores (_) are allowed in the database name");
				return false;
			} // rejecting illegal characters in db name

			$cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e\"create database `$newname`\"";
			$output1 = exec($cmdline . ' 2>&1', $output, $res1);
			if ($res1 != 0 ) {
				echo ("Error code $res1 on MySQL exec: Unable to create database $newname<br>&nbsp;<br>");
				echo("\n\n");
				$sqlErrorCode = split(" ", $output);
				if($sqlErrorCode[1] == "1007");
				echo "<strong>A database with that name already exists.</strong>";
				return false;
			}

			// At this point a database exists, so need cleanup if anythign goes wrong later

			// Create the Heurist structure for the newly created database, using the template SQL file
			// This file sets up teh table definitions and inserts a few critical values
			// it does not set referential integrity constraints or triggers
			$cmdline="mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -D$newname < blankDBStructure.sql";
			$output2 = exec($cmdline . ' 2>&1', $output, $res2);

			if ($res2 != 0 ) {
				echo ("Error $res2 on MySQL exec: Unable to load blankDBStructure.sql into database $newname<br>");
				echo ("Please check whether this file is valid; consult Heurist helpdesk if needed<br>&nbsp;<br>");
				echo($output2);
				cleanupNewDB($newname);
				return false;
			}

			// Add referential constraints
			$cmdline="mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -D$newname < addReferentialConstraints.sql";
			$output2 = exec($cmdline . ' 2>&1', $output, $res2);

			if ($res2 != 0 ) {
				echo ("Error $res2 on MySQL exec: Unable to load addReferentialConstraints.sql into database $newname<br>");
				echo ("Please check whether this file is valid; consult Heurist helpdesk if needed<br>&nbsp;<br>");
				echo($output2);
				cleanupNewDB($newname);
				return false;
			}

			// Add procedures and triggers
			$cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -D$newname < addProceduresTriggers.sql";
			$output2 = exec($cmdline . ' 2>&1', $output, $res2);

			if ($res2 != 0 ) {
				echo ("Error $res2 on MySQL exec: Unable to load addProceduresTriggers.sql for database $newname<br>");
				echo ("Please check whether this file is valid; consult Heurist helpdesk if needed<br>&nbsp;<br>");
				echo($output2);
				cleanupNewDB($newname);
				return false;
			}

			// Run buildCrosswalks to import minimal definitions from coreDefinitions.txt into the new DB
			// yes, this is badly structured, but it works - if it ain't broke ...
			$isNewDB = true; // flag of context for buildCrosswalks, tells it to use coreDefinitions.txt
			require_once('../structure/buildCrosswalks.php');

			// errorCreatingTables is set to true by buildCrosswalks if an error occurred
			if($errorCreatingTables) {
				echo ("Error importing core definitions from coreDefinitions.txt for database $newname<br>");
				echo ("Please check whether this file is valid; consult Heurist helpdesk if needed");
				cleanupNewDB($newname);
				return false;
			}

			// Get and clean information for the user creating the database
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

			// The upload directory should be created by the administrator, not hardcoded in here
			//     todo: choose or create upload directory during the creation process
			//     todo: code location of upload directory into sysIdentification, remove from edit form (should not be changed)
			//     todo: might wish to control ownership rather than leaving it to the O/S, although this works well at present
			//     todo:  uploaded-heurist-files should not be hardcoded

			// Create a default upload directory for uploaded files eg multimedia, images etc.
			$cmdline = "mkdir -p -m a=rwx ".HEURIST_UPLOAD_ROOT.$newDBName;
			$output2 = exec($cmdline . ' 2>&1', $output, $res2);
			if ($res2 != 0 ) { // TODO: need better setting and full path info for this error
				// todo: need to properly trap the error and distiguish different versions. Old uplaod directories
				// hanging around could cause problems if upload file IDs are duplicated, so should probably NOT
				// allow their re-use
				echo ("<h2>Warning:</h2> Unable to create uploaded-heurist-files directory for database $newname<br>&nbsp;<br>");
				echo ("This may be because the directory already exists or the parent folder is not writable<br>");
				echo ("Please check/create directory by hand. Consult Heurist helpdesk if needed<br><p>");
				echo($output2);
				// todo: we need to hold the warning here
				// return false; This is only a warning, should keep going
			}

			// Create a default icon upload directory for uploaded icon and thumbnails.
			$iconPath = "".HEURIST_ICON_ROOT.$newDBName."/rectype-icons/thumb";
			$cmdline = "mkdir -p ".$iconPath;
			$output2 = exec($cmdline . ' 2>&1', $output, $res2);
			if ($res2 != 0 ) { // TODO: need better setting and full path info for this error
				// todo: need to properly trap the error and distiguish different versions. Old uplaod directories
				// hanging around could cause problems if upload file IDs are duplicated, so should probably NOT
				// allow their re-use
				echo ("<h2>Warning:</h2> Unable to create icon directory ( $iconPath ) <br>&nbsp;<br>");
				echo ("This may be because the directory already exists or the parent folder is not writable<br>");
				echo ("Please check/create directory by hand. Consult Heurist helpdesk if needed<br><p>");
				echo($output2);
				// todo: we need to hold the warning here
				// return false; This is only a warning, should keep going
				}
			$iconPath = "".HEURIST_ICON_ROOT.$newDBName;
			$cmdline = "chmod -R 774 ".$iconPath;
			$output2 = exec($cmdline . ' 2>&1', $output, $res2);

			// Make the current user the owner and admin of the new database
			mysql_connection_db_insert($newname);
			mysql_query('UPDATE sysUGrps SET ugr_LongName="'.$longName.'", ugr_FirstName="'.$firstName.'",
			ugr_LastName="'.$lastName.'", ugr_eMail="'.$eMail.'", ugr_Name="'.$name.'",
			ugr_Password="'.$password.'", ugr_Department="'.$department.'", ugr_Organisation="'.$organisation.'",
			ugr_City="'.$city.'", ugr_State="'.$state.'", ugr_Postcode="'.$postcode.'",
			ugr_interests="'.$interests.'" WHERE ugr_ID=2');
			// TODO: error check, although this is unlikely to fail
			echo "New database <strong>" . $newname . "</strong> created successfully";

			echo "<p><strong>Admin username:</strong> ".$name."<br />";
			echo "<strong>Admin password:</strong> &#60;<i>same as account currently logged in to</i>&#62;<p>";

			echo '<script type="text/javascript">document.getElementById("createDBForm").style.display = "none";</script>';
			echo "The search page for this database is: <a href=\"".HEURIST_URL_BASE."?db=".$newDBName."\" title=\"\" target=\"_new\">".HEURIST_URL_BASE."?db=".$newDBName."</a>.<br /><br />";

			echo "<p>Please click here: <a href='".HEURIST_URL_BASE."admin/adminMenu.php?db=".$newDBName."' title='' target=\"_new\"><strong>administration page</strong></a>, to configure your new database<br />";


			// TODO: automatically redirect to the new database, maybe, in a new window

			return false;
		} // isset

	} //makedatabase


?>

