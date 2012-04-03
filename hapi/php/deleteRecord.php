<?php

/**
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

function mysql_query_($x) {
	print $x . "\n";
	$res = mysql_query($x);
if (mysql_error()) { print "ERROR: " . mysql_error() . "\n"; }
	if (preg_match("/^select/i", $x)) { print mysql_num_rows($res) . " SELECTED\n \n"; }
	else if (preg_match("/^update/i", $x)) { print mysql_affected_rows() . " UPDATED\n \n"; }
	else if (preg_match("/^delete/i", $x)) { print mysql_affected_rows() . " DELETED\n \n"; }
	else if (preg_match("/^insert/i", $x)) { print mysql_affected_rows() . " INSERTED\n \n"; }
	return $res;
}

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
require_once(dirname(__FILE__)."/../../records/edit/deleteRecordInfo.php");


if (! is_logged_in()) {
	jsonError("no logged-in user");
}

$_REQUEST = json_decode(@$_POST["data"]?  $_POST["data"] : base64_decode(@$_GET["data"]), true);

mysql_connection_db_overwrite(DATABASE);

mysql_query("start transaction");

$out = deleteRecord(@$_REQUEST["id"]);

if( array_key_exists("error", $out) ){
	jsonError($out["error"]);
}

mysql_query("commit");

print json_format($out);

function jsonError($message) {
	mysql_query("rollback");
	print "{\"error\":\"" . addslashes($message) . "\"}";
	exit(0);
}
?>
