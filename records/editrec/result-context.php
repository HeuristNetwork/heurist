<?php

require_once(dirname(__FILE__)."/../../common/connect/cred.php");
require_once(dirname(__FILE__)."/../../common/connect/db.php");

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

if (! @$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']["search-results"][$sid]) {
	print "null";
	return;
}

$results = $_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']["search-results"][$sid];

foreach ($results as $i => $rec_id) {
	if ($rec_id == $_REQUEST["id"]) {
		$context = array("prev" => @$results[$i-1] ? $results[$i-1] : null,
						 "next" => @$results[$i+1] ? $results[$i+1] : null,
						 "pos" => $i + 1,
						 "count" => count($results));
		print json_format($context);
	}
}
