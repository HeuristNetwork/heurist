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

require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
mysql_connection_db_insert(DATABASE); // Connect to the current database

echo '<link rel=stylesheet href="../../common/css/global.css">';
echo '<html><body class="popup">';
echo '<div class="banner"><h2>Database registration</h2></div>';
echo '<div id="page-inner" style="overflow:auto">';

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
    $msg = "Your database is already registered with ID: " . $DBID;
    echo $msg . "<br />";
    return;
} else {
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
	$heuristDBname = rawurlencode(HEURIST_DBNAME);
	$ownerGrpEmail = rawurlencode($ownerGrpEmail);
	$reg_url =  HEURIST_BASE_URL . "admin/setup/getNextDBRegistrationID.php" . // TODO: Change to HEURIST_INDEX_BASE_URL
				"?serverURL=" . HEURIST_BASE_URL . "&dbReg=" . $heuristDBname . 
				"&dbTitle=" . $heuristDBname . "&ownerGrpEmail=".$ownerGrpEmail;
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
		echo $msg . "<br /><br />(" . $errorMsg . ")";
        return;
    } else { // We have got a new dbID, set the assigned dbID in sysIdentification
		$res = mysql_query("update sysIdentification set `sys_dbRegisteredID`='$DBID' where `sys_ID`='1'");
		if($res) {
			echo "Registration successful, database ID allocated is " . $DBID;
		} else {
			$msg = "Unable to write database identification record, your database might be incorrectly set up<br />Please contact <a href=mailto:info@heuristscholar.org>Heurist developers</a> for advice";
			echo $msg;
			return;
		}
    }
}
?>
</div>
</body>
</html>