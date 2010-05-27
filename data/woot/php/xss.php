<?php

$legalMethods = array(
	"load-woot",
	"save-woot",
	"search-woots",
	"fetch"
);

function outputAsRedirect($text) {
	global $baseURL, $rxlen;

	$val = base64_encode($text);

	header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past

	if (strlen($val) > 10000 || ($rxlen  &&  strlen($val) > $rxlen)) {
		$token = sprintf("data%08x%08x", rand(), rand());

		session_start();
		$_SESSION[$token] = $text;

		header("Location: " . $baseURL . "blank.html#token=" . $token);
	}
	else {
		header("Location: " . $baseURL . "blank.html#data=" . urlencode($val));
	}

	return "";
}

function outputAsScript($text) {
	global $callback;

	preg_match_all('/.{1,1024}(?:[\x0-\x7F]|[\xC0-\xDF][\x80-\xBF]|[\xE0-\xEF][\x80-\xBF][\x80-\xBF])/', $text, $matches, PREG_PATTERN_ORDER);
	$bits = $matches[0];

	$output = $callback . "(";
	$output .= json_encode($bits[0]);
	for ($i=1; $i < count($bits); ++$i) {
		$output .= "\n+ " . json_encode($bits[$i]);
	}
	$output .= ");\n";

	return $output;
}


$rxlen = intval(@$_REQUEST["rxlen"]);
$callback = @$_REQUEST["cb"];
if ($callback  &&  preg_match('/^cb[0-9]+$/', $callback)) {
	ob_start("outputAsScript");
} else {
	ob_start("outputAsRedirect");
}

$method = @$_REQUEST["method"];
$key = @$_REQUEST["key"];

require_once(dirname(__FILE__)."/../../../common/connect/db.php");
require_once(dirname(__FILE__)."/../../../common/connect/cred.php");
require_once("auth.php");

if (! ($auth = get_location($key))) {
	print "{\"error\":\"unknown API key\"}";
	return;
}
// error_log(print_r($auth, 1));
$baseURL = $auth["hl_location"];

define_constants($auth["hl_instance"]);


if (! @$method  ||  ! in_array($method, $legalMethods)) {
	print "{\"error\":\"unknown method\"}";
	return;
}

define('USING-XSS', 1);

require_once("$method.php");

ob_end_flush();

?>
