<?php
    /**
    * registerDB.php - Registers the current database with HeuristScholar.org/db=HeuristSystem_Index , stores
    * metadata in the index database, sets registration code in sysIdentification table. Juan Adriaanse 26 May 2011.
    * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
    * @link: http://HeuristScholar.org
    * @license http://www.gnu.org/licenses/gpl-3.0.txt
    * @package Heurist academic knowledge management system
    * @todo
    **/
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

if (!is_logged_in()) {
    header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
    return;
}

// User must be system administrator or admin of the owners group for this database
if (!is_admin()) {
    print "<html><body><p>You must be logged in as system administrator to register a database</p><p><a href=" .
        HEURIST_URL_BASE . "common/connect/login.php?logout=1&amp;db=" . HEURIST_DBNAME .
        "'>Log out</a></p></body></html>";
    return;
}
?>
<link rel=stylesheet href="../../common/css/global.css">

<html>
	<head>
		<meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
		<title>Register DB to Heurist Index Server</title>
	</head>
	<div class="banner"><h2>Database registration</h2></div>
	<div id="page-inner" style="overflow:auto">

	<body class="popup">
		<div id="registerDBForm">
		<form action="registerDB.php" method="POST" name="NewDBRegistration">
			Enter a short description for your database.<br /><br />
			<input type="text" maxlength="64" size="25" name="dbDescription">
			<input type="submit" name="submit" value="Register" style="font-weight: bold;" onClick="registerDB()" >
		</form>
		</div>
<?php
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

mysql_connection_db_insert(DATABASE); // Connect to the current database

// Check if already registered and exit if so, otherwise request registration from Heurist Index database

$res = mysql_query("select sys_dbRegisteredID, sys_dbName, sys_dbDescription, sys_OwnerGroupID from sysIdentification where `sys_ID`='1'");

if (!$res) { // Problem reading current registration ID
    $msg = "Unable to read database identification record, your database might be incorrectly set up. \n" .
    "Please contact <a href=mailto:info@heuristscholar.org>Heurist developers</a> for advice.";
    echo $msg . "<br />";
    return;
}

$row = mysql_fetch_row($res); // Get system information for current database
$DBID = $row[0];
$dbName = $row[1];
$dbDescription = $row[2];
$ownerGrpID = $row[3];

// Look up owner group sysadmin password from sysUGrps table
$res = mysql_query("select ugr_eMail from sysUGrps where `ugr_ID`='$ownerGrpID'");
if(mysql_num_rows($res) == 0) {
	echo "Non-critical warning: Unable to read database owners group email, not currently supporting deferred users database";
	return;
}

$row = mysql_fetch_row($res);
$ownerGrpEmail = $row[0]; // Get owner group email address from UGrps table

// Check if database has already been registered
if (isset($DBID) && ($DBID != 0)) { // already registered
	echo '<script type="text/javascript">';
	echo 'document.getElementById("registerDBForm").style.display = "none";';
	echo '</script>';
    echo "Your database is already registered with ID: " . $DBID . "<br />";
    echo "The database description is: ". $dbDescription . "<br /><br />";
} else {
	echo '<script type="text/javascript">';
	echo 'document.getElementById("registerDBForm").style.display = "block";';
	echo '</script>';
}

function registerDatabase() {
	$heuristDBname = rawurlencode(HEURIST_DBNAME);
	global $DBID, $dbName, $ownerGrpID, $ownerGrpEmail, $dbDescription;
	$serverURL = HEURIST_BASE_URL . "?db=" . $heuristDBname;

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_COOKIEFILE, '/dev/null');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);    //return curl_exec output as string
	curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
	curl_setopt($ch, CURLOPT_NOBODY, 0);
	curl_setopt($ch, CURLOPT_HEADER, 0);    //don't include header in output
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);    // follow server header redirects
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);    // don't verify peer cert
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);    // timeout after ten seconds
	curl_setopt($ch, CURLOPT_MAXREDIRS, 5);    // no more than 5 redirections
	$ownerGrpEmail = rawurlencode($ownerGrpEmail);
	$dbDescriptionEncoded = rawurlencode($dbDescription);
	$reg_url =  HEURIST_BASE_URL . "admin/setup/getNextDBRegistrationID.php" . // TODO: Change to HEURIST_INDEX_BASE_URL
				"?serverURL=" . $serverURL . "&dbReg=" . $heuristDBname . 
				"&dbTitle=" . $dbDescriptionEncoded . "&ownerGrpEmail=".$ownerGrpEmail;
	curl_setopt($ch, CURLOPT_URL,$reg_url);
	$data = curl_exec($ch);
	$error = curl_error($ch);
	if ($error) {
		$code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		echo $error . " (" . $code . ")";
    } else {
		$DBID = intval($data);
    }
    if ($DBID == 0) { // Unable to allocate a new database identifier
		$decodedData = explode(',', $data);
		$errorMsg = $decodedData[0];
        $msg = "Problem allocating a database identifier from the Heurist index.\n" .
        "Please contact <a href=mailto:info@heuristscholar.org>Heurist developers</a> for advice";
        return;
    } else if($DBID == -1) {
	    $res = mysql_query("update sysIdentification set `sys_dbDescription`='$dbDescription' where `sys_ID`='1'");
		echo "Database description succesfully changed to: " . $dbDescription;
    } else { // We have got a new dbID, set the assigned dbID in sysIdentification
		$res = mysql_query("update sysIdentification set `sys_dbRegisteredID`='$DBID', `sys_dbDescription`='$dbDescription' where `sys_ID`='1'");
		if($res) {
			echo "Registration successful, database ID allocated is " . $DBID . "<br /><br />";
			echo "Your database description is: " . $dbDescription . "<br />";
			echo "If you want to change the description, you can go back to the registration page to do so.";
		} else {
			$msg = "Unable to write database identification record, your database might be incorrectly set up<br />Please contact <a href=mailto:info@heuristscholar.org>Heurist developers</a> for advice";
			echo '<script type="text/javascript">';
			echo 'document.getElementById("changeDescriptionForm").style.display = "none";';
			echo '</script>';
			echo $msg;
			return;
		}
    }
}
?>
<div id="changeDescriptionForm">
	<form action="registerDB.php" method="POST" name="NewDBRegistration">
		<input type="text" maxlength="64" size="25" name="dbDescription">
		<input type="submit" name="submitDescriptionChange" value="Change description" style="font-weight: bold;" onClick="changeDescription()" >
	</form>
</div>

<script type="text/javascript">
	function registerDB() {
		document.getElementById("registerDBForm").style.display = "none";
	}
	function changeDescription() {
		document.getElementById("changeDescriptionForm").style.display = "none";
	}
	document.getElementById("changeDescriptionForm").style.display = "none";
</script>
<?php
if(isset($_POST['dbDescription'])) {
	if(strlen($_POST['dbDescription']) > 3 && strlen($_POST['dbDescription']) < 1022) {
		$dbDescription = $_POST['dbDescription'];
		echo '<script type="text/javascript">';
		echo 'document.getElementById("registerDBForm").style.display = "none";';
		echo '</script>';
		registerDatabase();
	} else {
		echo "The database description should be at least 4 characters, and at most 1021 characters long.";
	}
}
if (isset($DBID) && ($DBID != 0) && !isset($_POST['dbDescription'])) {
	echo '<script type="text/javascript">';
	echo 'document.getElementById("changeDescriptionForm").style.display = "block";';
	echo '</script>';
}
?>
</body>
</html>