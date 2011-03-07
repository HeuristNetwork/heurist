<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

mysql_connection_db_select("hapi");

if (! is_logged_in()) {
/*
	jsonError("no logged-in user");
*/
	$_REQUEST["crossSession"] = false;
}


$_REQUEST = json_decode(@$_POST["data"]?  $_POST["data"] : base64_decode(@$_GET["data"]), true);

$location = @$_REQUEST["crossDomain"]? "*" : ($baseURL? $baseURL : HEURIST_SERVER_NAME);	// TESTTHIS:  repalced heuristscholar.org with host name
$varName = $_REQUEST["name"];

if (preg_match("/^([a-zA-Z0-9_]+)((?:[.][a-zA-Z0-9_]+)+)$/", $varName, $matches)) {
	$topLevelVarName = $matches[1];
	$innerVarPath = $matches[2];

	if (@$_REQUEST["crossSession"]) {
		// cross-session values are stored in the database
		$res = mysql_query("select * from hapi_pj_party where pj_location='" . addslashes($location) . "'" .
		                                                " and pj_instance='" . HEURIST_DBNAME . "'" .
		                                                " and pj_user_id=" . get_user_id() .
		                                                " and pj_varname='" . addslashes($topLevelVarName) . "'");
		$topObject = mysql_fetch_assoc($res);
		$topObject = json_decode(@$topObject["pj_value"], 1);
	}
	else {
		$topObject = @$_SESSION["pj-party"][$topLevelVarName];
	}

	$value = is_array($topObject)? getInnerValue($topObject, $innerVarPath) : null;
}
else if (preg_match("/^([a-zA-Z0-9_]+)$/", $varName)) {
	if (@$_REQUEST["crossSession"]) {
		$res = mysql_query("select * from hapi_pj_party where pj_location='" . addslashes($location) . "'" .
		                                                " and pj_instance='" . HEURIST_DBNAME . "'" .
		                                                " and pj_user_id=" . get_user_id() .
		                                                " and pj_varname='" . addslashes($varName) . "'");
		$value = mysql_fetch_assoc($res);
		$value = json_decode(@$value["pj_value"], 1);
	}
	else {
		$value = @$_SESSION["pj-party"][$varName];
	}
}
else {
	jsonError("invalid variable name");
}

print json_encode(array("value" => $value));


function getInnerValue(&$obj, $innerVar) {
	if (substr($innerVar, 0, 1) != ".") {
		jsonError("invalid variable name");
	}

	$nextIndex = strpos($innerVar, ".", 1);
	if ($nextIndex !== FALSE) {
		// recurse
		$nextVarName = substr($innerVar, 1, $nextIndex-1);

		if (! is_array(@$obj[$nextVarName])) { return NULL; }
		return getInnerValue($obj[$nextVarName], substr($innerVar, $nextIndex), $value);
	}
	else {
		return $obj[substr($innerVar, 1)];
	}
}


function jsonError($message) {
	mysql_query("rollback");
	print "{\"error\":\"" . addslashes($message) . "\"}";
	exit(0);
}

?>
