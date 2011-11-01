<?php

	/** File: strraightCopyDB.php Copies an entire databsae verbatim, Ian Johnson 31 Oct 2011
	* @copyright 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	**/

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
	if (! is_logged_in()) {
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}
	if (! is_admin()) {
	print "<html><body><p>You do not have sufficient privileges to access this page</p><p><a href=".HEURIST_URL_BASE.">Return to Heurist</a></p></body></html>";
	return;
	}

	mysql_connection_db_overwrite(DATABASE);
	if(mysql_error()) {
		die("Sorry, could not connect to the database (mysql_connection_db_overwrite error)");
	}
?>

<html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
	<title>Heurist - Straight copy of a database to an identical copy</title>
</head>
<body>
	<script type="text/javascript" src="../../common/js/utilsLoad.js"></script>
	<script type="text/javascript" src="../../common/js/utilsUI.js"></script>
	<script src="../../common/php/loadCommonInfo.php"></script>
	<h2>Heurist Direct Copy</h2>
	This script simply copies the current database to a new one with no changes. New database is identical to the old in all respects including access.<br><p>
<?php


// ---- SPECIFY THE TARGET DATABASE (first pass) -------------------------------------------------------------------

 	if(!array_key_exists('mode', $_REQUEST) || !array_key_exists('targetdbname', $_REQUEST)){
?>

<form name='selectdb' action='straightCopyDatabase.php' method='get'>
<input name='mode' value='2' type='hidden'> <!-- calls the form to select mappings, step 2 -->
<input name='db' value='<?=HEURIST_DBNAME?>' type='hidden'>
<br>Enter name for new database to be created (prefix added automatically): <input type='text' name='targetdbname' />
<input type='submit' value='Go'/>
</form>
</body>
</html>
<?php
		exit;
	}

// ---- PROCESS THE COPY FUNCTION (second pass) --------------------------------------------------------------------

    if(array_key_exists('mode', $_REQUEST) && $_REQUEST['mode']=='2'){
		$targetdbname = $dbPrefix.$_REQUEST['targetdbname'];
		error_log("Target database is $targetdbname");
	 	straightCopyDatabase($targetdbname);
	}


// ---- COPY FUNCTION -----------------------------------------------------------------


function straightCopyDatabase($newname) {
	// Use the file upload directory for this database because we know it should exist and be writable

	$dump_command = "mysqldump -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." ".DATABASE." > ".HEURIST_UPLOAD_DIR."/temporary_db_dump.sql";
	$create_command = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e 'create database $newname'";
	$upload_command = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." $newname < '".HEURIST_UPLOAD_DIR."/temporary_db_dump.sql'";
	$cleanup_command = "rm ".HEURIST_UPLOAD_DIR."/temporary_db_dump.sql"; // cleanup


	exec("$dump_command;$create_command;$upload_command;$cleanup_command",$output,$res);
	// Note: before this had three separate execs, but ended up with truncated SQL file in first step
	// Assume this was due to the execs being spawned as separate processes, hence putting them all in one exec
	// This gives less opportunity for error checking, but for the moment it works

	// TODO: NEED PROPER ERROR CHECKING

	print "<br>Done. New database <b>$newname</b> created (please report if not successful)<br>";
	print "</body></html>";
	exit;
} // straightCopyNewDatabase


?>
