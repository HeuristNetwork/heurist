<?php

/*<!--
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

if (! is_logged_in()) {
	print "null";
	return;
}

if (! @$_REQUEST["s"]  ||  ! @$_REQUEST["id"]) {
	print "null";
	return;
}

$sid = $_REQUEST["s"];

session_start();

if (!@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["search-results"][$sid] ||
	!@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['search-results'][$sid]["infoByDepth"] ||
	!@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['search-results'][$sid]["infoByDepth"][0]) {
	print "null";
	return;
}

$results = $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["search-results"][$sid]["infoByDepth"][0]["recIDs"];

foreach ($results as $i => $rec_id) {
	if ($rec_id == $_REQUEST["id"]) {
		$context = array("prev" => @$results[$i-1] ? $results[$i-1] : null,
						 "next" => @$results[$i+1] ? $results[$i+1] : null,
						 "pos" => $i + 1,
						 "count" => count($results));
		print json_format($context);
	}
}
