<?php
	/**
	* registerDB.php - Registers the current database with HeuristScholar.org/db=H3MasterIndex , stores
	* metadata in the index database, sets registration code in sysIdentification table. Juan Adriaanse 26 May 2011.
	* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	**/
	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
	require_once(dirname(__FILE__).'/../../records/files/fileUtils.php');

	if (!is_logged_in()) {
		header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}

	$user_id = get_user_id();

	$sError = null;
	// User must be system administrator or admin of the owners group for this database
	if (!is_admin()) {
		$sError = "You must be logged in as system administrator to register a database";
	}else  if (get_user_id() != 2) {
		$sError = "Only the owner/creator of the database (user #2) may register the database. ".
		"<br/><br/>This user will also own (and be able to edit) the registration record in the heuristscholar.org master index database";
		return;
	}

	mysql_connection_insert(DATABASE); // Connect to the current database (the one being registered)

	// Look up current user email from sysUGrps table in the current database (the one being registered)
	// Registering user must be a real user so that there is an email address and password to attach to the registration record.
	// which rules out using the Database Managers group. Since other users will be unable to login and edit this record, it's better
	// to only allow the creator (user #2) to register the db, to avoid problems down the track knowing who registered it.
	$res = mysql_query("select ugr_eMail, ugr_Password,ugr_Name,ugr_FirstName,ugr_LastName from sysUGrps where `ugr_ID`='$user_id'");
	if(mysql_num_rows($res) == 0) {
		$sError = "Warning<br/><br/>Unable to read your email address from user table. Note: not currently supporting deferred users database";
	}else{

			$row = mysql_fetch_row($res);
			$usrEmail = $row[0]; // Get the current user's email address from UGrps table
			$usrPassword = $row[1];
			$usrName = $row[2];
			$usrFirstName = $row[3];
			$usrLastName = $row[4];

			if(!$usrEmail || !$usrName || !$usrFirstName || !$usrLastName || !$usrPassword){
					$sError = "Warning<br/><br/>You have to specify your full name, login and email in your profile. Note: not currently supporting deferred users database";
			}
	}

	if($sError){
		print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head>".
		"<body><div class=wrap><div id=errorMsg><span>$sError</span>".
		"<p><a href=".HEURIST_BASE_URL."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME.
		" target='_top'>Log out</a></p></div></div></body></html>";
		return;
	}


?>
<link rel="stylesheet" type="text/css" href="../../common/css/global.css">
<link rel="stylesheet" type="text/css" href="../../common/css/edit.css">
<link rel="stylesheet" type="text/css" href="../../common/css/admin.css">

<html>
	<head>
		<meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
		<title>Database Registration</title>
	</head>

	<!-- Database registration form -->

	<body class="popup">
		<div class="banner"><h2>Database Registration</h2></div>
		<div id="page-inner" style="overflow:auto">
			<div id="registerDBForm" class="input-row">
				<form action="registerDB.php" method="POST" name="NewDBRegistration">
					<div class='input-header-cell'>Enter a short description for this database.</div><div class='input-cell'><input type="text" maxlength="1000" size="80" name="dbDescription">
						<input type="submit" name="submit" value="Register" style="font-weight: bold;" onClick="registerDB()" ></div>
				</form>
			</div>

			<?php

				$res = mysql_query("select sys_dbRegisteredID, sys_dbName, sys_dbDescription, sys_OwnerGroupID from sysIdentification where `sys_ID`='1'");

				// Start by hiding the registration/title edit form
				echo '<script type="text/javascript">';
				echo 'document.getElementById("registerDBForm").style.display = "none";';
				echo '</script>';

				if (!$res) { // Problem reading current registration ID
					$msg = "Unable to read database identification record, this database might be incorrectly set up. \n" .
					"Please contact <a href=mailto:info@heuristscholar.org>Heurist developers</a> for advice.";
					echo $msg . "<br />";
					return;
				}

				$row = mysql_fetch_row($res); // Get system information for current database
				$dbID = $row[0];
				$dbName = $row[1];
				$dbDescription = $row[2];
				$ownerGrpID = $row[3];

				/*****DEBUG****///error_log('registerDB.php: current dbid = '.$dbID.'   user ID = '.$user_id.' user email = '.$usrEmail);

				// Check if database has already been registered

				if (isset($dbID) && ($dbID != 0)) { // already registered, display info and link to H3MasterIndex edit
					echo '<script type="text/javascript">';
					echo 'document.getElementById("registerDBForm").style.display = "none";';
					echo '</script>';
					echo "<div class='input-row'><div class='input-header-cell'>Database:</div><div class='input-cell'>".DATABASE." </div></div>";
					echo "<div class='input-row'><div class='input-header-cell'>Already registered with</div><div class='input-cell'><b>ID:</b> " . $dbID . " </div></div>";
					echo "<div class='input-row'><div class='input-header-cell'>Description:</div><div class='input-cell'>". $dbDescription . "</div></div>";
					$url = HEURIST_INDEX_BASE_URL."records/edit/editRecord.html?recID=".$dbID."&db=H3MasterIndex";
					echo "<div class='input-row'><div class='input-header-cell'>Collection metadata:</div><div class='input-cell'>
					<a href=$url target=_blank>Click here to edit</a> (login as person who registered this database - note: use EMAIL ADDRESS as username)
					</div></div>";
				} else { // New registration, display registration form
					echo '<script type="text/javascript">';
					echo 'document.getElementById("registerDBForm").style.display = "block";';
					echo '</script>';
				}

				function registerDatabase() {
					$heuristDBname = rawurlencode(HEURIST_DBNAME);
					global $dbID, $dbName, $ownerGrpID, $indexdb_user_id, $usrEmail, $usrPassword, $usrName, $usrFirstName, $usrLastName, $dbDescription;
					$serverURL = HEURIST_BASE_URL . "?db=" . $heuristDBname;

					$usrEmail = rawurlencode($usrEmail);
					$usrName = rawurlencode($usrName);
					$usrFirstName = rawurlencode($usrFirstName);
					$usrLastName = rawurlencode($usrLastName);
					$usrPassword = rawurlencode($usrPassword);
					$dbDescriptionEncoded = rawurlencode($dbDescription);
					$reg_url =  HEURIST_INDEX_BASE_URL . "admin/setup/getNextDBRegistrationID.php" . // TODO: Change to HEURIST_INDEX_BASE_URL
					"?db=H3MasterIndex&serverURL=" . $serverURL . "&dbReg=" . $heuristDBname . "&dbVer=" . HEURIST_DBVERSION .
					"&dbTitle=" . $dbDescriptionEncoded . "&usrPassword=" . $usrPassword .
					"&usrName=" . $usrName . "&usrFirstName=" . $usrFirstName . "&usrLastName=" . $usrLastName . "&usrEmail=".$usrEmail;

					$data = loadRemoteURLContent($reg_url);

					if ($data) {
						$dbID = intval($data);
					}

					if ($dbID == 0) { // Unable to allocate a new database identifier
						$decodedData = explode(',', $data);
						$errorMsg = $decodedData[0];
						error_log ('registerDB.php had problem allocating a database identifier from the Heurist index, dbID. Error: '.$data);
						$msg = "Problem allocating a database identifier from the Heurist index.\n" .
						"Please contact <a href=mailto:info@heuristscholar.org>Heurist developers</a> for advice";
						echo $msg . "<br />";
						return;
					} else if($dbID == -1) { // old title update function, should no longer be called
						$res = mysql_query("update sysIdentification set `sys_dbDescription`='$dbDescription' where `sys_ID`='1'");
						echo "<div class='input-row'><div class='input-header-cell'>Database description (updated):</div><div class='input-cell'>". $dbDescription."</div></div>";
					} else { // We have got a new dbID, set the assigned dbID in sysIdentification
						$res = mysql_query("update sysIdentification set `sys_dbRegisteredID`='$dbID', `sys_dbDescription`='$dbDescription' where `sys_ID`='1'");
						if($res) {
							echo "<div class='input-row'><div class='input-header-cell'>Database:</div><div class='input-cell'>".DATABASE."</div></div>";
							echo "<div class='input-row'><div class='input-header-cell'>Registration successful, database ID allocated is</div><div class='input-cell'>" . $dbID . "</div></div>";
							echo "<div class='input-row'><div class='input-header-cell'></div><div class='input-cell'>Basic description: " . $dbDescription . "</div></div>";
							$url = HEURIST_INDEX_BASE_URL."records/edit/editRecord.html?recID=".$dbID."&db=H3MasterIndex";
							echo "<div class='input-row'><div class='input-header-cell'>Collection metadata:</div><div class='input-cell'>
							<a href=$url target=_blank>Click here to edit</a> (login - if asked - as yourself) </div></div>";
						?>
						<script> // automatically call H3MasterIndix metadata edit form for this database
							window.open("<?=$url?>",'_blank');
						</script>
						<?
						} else {
							error_log ('Unable to write database identification record, dbID is '.$dbID);
							$msg = "<div class=wrap><div id=errorMsg><span>Unable to write database identification record</span>this database might be incorrectly set up<br />Please contact <a href=mailto:info@heuristscholar.org>Heurist developers</a> for advice</div></div>";
							echo $msg;
							return;
						} // unable to write db identification record
					} // successful new DB ID
				} // registerDatabase()
			?>

			<script type="text/javascript">
				function registerDB() {
					document.getElementById("registerDBForm").style.display = "none";
				}
			</script>


			<?php

				// Do the work of registering the database if a suitable title is set

				if(isset($_POST['dbDescription'])) {
					if(strlen($_POST['dbDescription']) > 3 && strlen($_POST['dbDescription']) < 1022) {
						$dbDescription = $_POST['dbDescription'];
						echo '<script type="text/javascript">';
						echo 'document.getElementById("registerDBForm").style.display = "none";';
						echo '</script>';
						registerDatabase(); // this does all the work
					} else {
						echo "The database description should be at least 4 characters, and at most 1021 characters long.";
					}
				}

			?>

			<!-- Explanation for the user -->

			<div class="separator_row" style="margin:20px 0;"></div>
			<h3>Suggested workflow for new databases:</h3>

			<?php include("includeNewDatabaseWorkflow.html");  ?>
		</div>
	</body>
</html>