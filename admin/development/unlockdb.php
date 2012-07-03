<?php

	require_once(dirname(__FILE__).'/../../common/config/initialise.php');
	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

	// Deals with all the database connections stuff

	mysql_connection_db_overwrite(DATABASE);
	if (! is_logged_in()) {
		header("Location: " . HEURIST_URL_BASE . "common/connect/login.php?db=".HEURIST_DBNAME);
		return;
	}

	if (!is_admin()) {
		print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap>".
		"<div id=errorMsg><span>You must be an adminstrator of the owner's group to unlock the database '".HEURIST_DBNAME."'</span>".
		"<p><a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME.
		" target='_top'>Log out</a></p></div></div></body></html>";
		return;
	}
mysql_query("delete from sysLocks where 1");
/*****DEBUG****///error_log("in unlock ".print_r(HEURIST_DBNAME,true));
if (!mysql_error()){
	returnXMLSuccessMsgPage(" Successfully unlocked '".HEURIST_DBNAME."'");
}

returnXMLErrorMsgPage("The was a problem unlocking '".HEURIST_DBNAME."' error - ".mysql_error());

function returnXMLSuccessMsgPage($msg) {
	die("<html><body><success>$msg</success></body></html>");
}

function returnXMLErrorMsgPage($msg) {
	die("<?xml version='1.0' encoding='UTF-8'?>\n<error>$msg</error>");
}

?>
