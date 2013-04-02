<?php

/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/



/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/



require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

mysql_connection_overwrite("hapi");

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



//$location = @$_REQUEST["crossDomain"]? "*" : ($baseURL? $baseURL : "heuristscholar.org");
$location = @$_REQUEST["crossDomain"]? "*" : ($baseURL? $baseURL : HEURIST_SERVER_NAME);	// TESTTHIS:  repalced heuristscholar.org with host name
$varName = $_REQUEST["name"];
$value = $_REQUEST["value"];

if (preg_match("/^([a-zA-Z0-9_]+)((?:[.][a-zA-Z0-9_]+)+)$/", $varName, $matches)) {
	$topLevelVarName = $matches[1];
	$innerVarPath = $matches[2];

	if (@$_REQUEST["crossSession"]) {
		// cross-session values are stored in the database
		$res = mysql_query("select * from hapi_pj_party where pj_location='" . mysql_real_escape_string($location) . "'" .
		                                                " and pj_instance='" . HEURIST_DBNAME . "'" .
		                                                " and pj_user_id=" . get_user_id() .
		                                                " and pj_varname='" . mysql_real_escape_string($topLevelVarName) . "'");
		$topObject = mysql_fetch_assoc($res);
		$topObject = json_decode($topObject["pj_value"], 1);
		if (! is_array($topObject)) $topObject = array();

		setInnerValue($topObject, $innerVarPath, $value);

		mysql_query("replace hapi_pj_party (pj_location, pj_instance, pj_user_id, pj_varname, pj_value)
		                            values ('".mysql_real_escape_string($location)."',
		                                     '".HEURIST_DBNAME."',
		                                     ".get_user_id().",
		                                     '".mysql_real_escape_string($topLevelVarName)."',
		                                     '".mysql_real_escape_string(json_encode($topObject))."')");
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
		                            values ('".mysql_real_escape_string($location)."',
		                                     '".HEURIST_DBNAME."',
		                                     ".get_user_id().",
		                                     '".mysql_real_escape_string($varName)."',
		                                     '".mysql_real_escape_string(json_encode($value))."')");
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
