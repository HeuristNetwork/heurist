<?php

$legalMethods = array(
	"load-search"
);


$method = preg_replace('!.*/([-a-z]+)$!', '$1', $_SERVER['PATH_INFO']);
$key = @$_REQUEST["key"];

require_once("modules/db.php");
require_once("modules/cred.php");
require_once("auth.php");

if (! ($auth = get_location($key))) {
	print "{\"error\":\"unknown API key\"}";
	return;
}
$baseURL = $auth["hl_location"];

define_constants($auth["hl_instance"]);


if (! @$method  ||  ! in_array($method, $legalMethods)) {
	print "{\"error\":\"unknown method\"}";
	return;
}

if ($method === "load-search"  &&  ! @$_POST["data"]) {
	$data = array();
	if (@$_REQUEST["q"]) {
		$data["q"] = $_REQUEST["q"];
	}
	if (@$_REQUEST["w"]) {
		$data["w"] = $_REQUEST["w"];
	}
	$_POST["data"] = json_encode($data);
}

require_once("$method.php");

?>
