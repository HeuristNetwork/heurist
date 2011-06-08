<!--
* selectDBForImport.php, Shows a dropdown list with all registered databases and shows crosswalk table
after selection, 26-05-2011, by Juan Adriaanse
* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
* @link: http://HeuristScholar.org
* @license http://www.gnu.org/licenses/gpl-3.0.txt
* @package Heurist academic knowledge management system
* @todo
-->
<link rel=stylesheet href="../../common/css/global.css">
<html>
<head>
<title>Select database for import</title>
</head>
<body class="popup">

<div class="banner"><h2>Select a database for import</h2></div>
<div id="page-inner" style="overflow:auto">
<select id="registeredDBSelect"></select>
<button id="crosswalkButton" onclick="doCrosswalk()">Crosswalk</button><br />

<form id="crosswalkInfo" action="buildCrosswalks.php" method="POST">
<input id="dbID" name="dbID" type="hidden">
<input id="dbName" name="dbName" type="hidden">
<input id="dbURL" name="dbURL" type="hidden">
</form>

<script type="text/javascript">

<?php
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
	require_once(dirname(__FILE__)."/../../common/config/initialise.php");
	mysql_connection_db_insert(DATABASE); // Connect to the current database

	// Send request to getRegisteredDBs on the master Heurist index server, to get all registered databases and their URLs
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
	$reg_url =  HEURIST_BASE_URL . "admin/structure/getRegisteredDBs.php"; // TODO: Change to HEURIST_INDEX_BASE_URL
	curl_setopt($ch, CURLOPT_URL,$reg_url);
	$data = curl_exec($ch);
	$error = curl_error($ch);
	if(!$error) {
		// If data has been successfully received, write it to a javascript array, leave out own DB if found
		$res = mysql_query("select sys_dbRegisteredID from sysIdentification where `sys_ID`='1'");
		if($res) {
			$row = mysql_fetch_row($res);
			$ownDBID = $row[0];
		} else {
			$ownDBID = 0;
		}
		$data = json_decode($data);
		echo 'var registeredDBSelect = document.getElementById("registeredDBSelect");';
		foreach($data as $registeredDB) {
			if($ownDBID != $registeredDB->rec_ID) {
				echo 'var registeredDB = document.createElement("option");';
				echo 'registeredDB.text = "'.$registeredDB->rec_Title.'";';
				echo 'registeredDB.value = "'.$registeredDB->rec_ID.','.$registeredDB->rec_URL.'";';
				echo 'try {';
				echo 'registeredDBSelect.add(registeredDB, null);'; // Needed for Firefox
				echo '} catch(err) {';
				echo 'registeredDBSelect.add(registeredDB);'; // All other browsers
				echo '}';
			}
		}
	}
?>

// Enter information about the selected database to an invisible form, and submit to the crosswalk page, to start crosswalking
function doCrosswalk() {
	var dbTitle = registeredDBSelect.options[registeredDBSelect.selectedIndex].text;
	var dbIDAndURL = registeredDBSelect.options[registeredDBSelect.selectedIndex].value;
	var dbURLSplit = dbIDAndURL.split(",");
	var dbID = dbURLSplit[0];
	index = 1;
	var dbURL = "";
	while(index < dbURLSplit.length) {
		dbURL += dbURLSplit[index];
		index++;
	}
	document.getElementById("dbID").value = dbID;
	document.getElementById("dbName").value = dbTitle;
	document.getElementById("dbURL").value = dbURL;
	document.forms["crosswalkInfo"].submit();
}

</script>
</div>
</body>
</html>