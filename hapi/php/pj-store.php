<?php

require_once("modules/cred.php");
require_once("modules/db.php");

mysql_connection_db_overwrite("hapi");

mysql_query("start transaction");

if (! is_logged_in()) {
/*****
 Experimental change: non-logged-in users can still store (non cross session) stuff
 2008/03/25 - tfm

	jsonError("no logged-in user");
*/
	$_REQUEST["crossSession"] = false;
}


$_REQUEST = json_decode(@$_POST["data"]?  $_POST["data"] : base64_decode(@$_GET["data"]), true);



$location = @$_REQUEST["crossDomain"]? "*" : ($baseURL? $baseURL : "heuristscholar.org");
$varName = $_REQUEST["name"];
$value = $_REQUEST["value"];

if (preg_match("/^([a-zA-Z0-9_]+)((?:[.][a-zA-Z0-9_]+)+)$/", $varName, $matches)) {
	$topLevelVarName = $matches[1];
	$innerVarPath = $matches[2];

	if (@$_REQUEST["crossSession"]) {
		// cross-session values are stored in the database
		$res = mysql_query("select * from hapi_pj_party where pj_location='" . addslashes($location) . "'" .
		                                                " and pj_instance='" . HEURIST_INSTANCE . "'" .
		                                                " and pj_user_id=" . get_user_id() .
		                                                " and pj_varname='" . addslashes($topLevelVarName) . "'");
		$topObject = mysql_fetch_assoc($res);
		$topObject = json_decode($topObject["pj_value"], 1);
		if (! is_array($topObject)) $topObject = array();

		setInnerValue($topObject, $innerVarPath, $value);

		mysql_query("replace hapi_pj_party (pj_location, pj_instance, pj_user_id, pj_varname, pj_value)
		                            values ('".addslashes($location)."',
		                                     '".HEURIST_INSTANCE."',
		                                     ".get_user_id().",
		                                     '".addslashes($topLevelVarName)."',
		                                     '".addslashes(json_encode($topObject))."')");
	}
	else {
		// non cross-session values are stored in $_SESSION
		session_start();

		if (! $_SESSION["pj-party"]) {
			$_SESSION["pj-party"] = array();
		}
		if (! is_array(@$_SESSION["pj-party"][$topLevelVarName])) {
			$_SESSION["pj-party"][$topLevelVarName] = array();
		}
		$topObject = &$_SESSION["pj-party"][$topLevelVarName];

		setInnerValue($topObject, $innerVarPath, $value);
	}
}
else if (preg_match("/^([a-zA-Z0-9_]+)$/", $varName)) {
	if (@$_REQUEST["crossSession"]) {
		mysql_query("replace hapi_pj_party (pj_location, pj_instance, pj_user_id, pj_varname, pj_value)
		                            values ('".addslashes($location)."',
		                                     '".HEURIST_INSTANCE."',
		                                     ".get_user_id().",
		                                     '".addslashes($varName)."',
		                                     '".addslashes(json_encode($value))."')");
	}
	else {
		session_start();
		if (! is_array(@$_SESSION["pj-party"])) { $_SESSION["pj-party"] = array(); }
		$_SESSION["pj-party"][$varName] = $value;
	}
}
else {
	jsonError("invalid variable name");
}


mysql_query("commit");

print json_encode(array("success" => true));


function setInnerValue(&$obj, $innerVar, $value) {
	if (substr($innerVar, 0, 1) != ".") {
		jsonError("invalid variable name");
	}

	$nextIndex = strpos($innerVar, ".", 1);
	if ($nextIndex !== FALSE) {
		// recurse
		$nextVarName = substr($innerVar, 1, $nextIndex-1);

		if (! is_array(@$obj[$nextVarName])) { $obj[$nextVarName] = array(); }
		setInnerValue($obj[$nextVarName], substr($innerVar, $nextIndex), $value);
	}
	else {
		$obj[substr($innerVar, 1)] = $value;
	}
}


function jsonError($message) {
	mysql_query("rollback");
	print "{\"error\":\"" . addslashes($message) . "\"}";
	exit(0);
}

?>
