<?php

session_cache_limiter('no-cache');
define('SAVE_URI', 'disabled');
define('SEARCH_VERSION', 1);

define('RESULT_COUNT_LIMIT', 20);

require_once(dirname(__FILE__)."/../../common/connect/db.php");

mysql_connection_db_select(DATABASE);


/* Accept input data via either POST or GET.
 * Data is supplied in a parameter called "data", in JSON format.
 * In the case of a GET request, the JSON is further encoded in base64.
 */
$_REQUEST = json_decode(@$_POST["data"]?  $_POST["data"] : base64_decode(@$_GET["data"]), true);


if (@$_REQUEST["file-name"]) {
	$hasStar = strpos($_REQUEST["file-name"], "*");
	$hasBracket = strpos($_REQUEST["file-name"], "[");
	$hasQuestion = strpos($_REQUEST["file-name"], "?");
	if ($hasStar === false  &&  $hasBracket === false  &&  $hasQuestion === false) {
		// no need for trickiness -- direct string match
		$fileNameQuery = "and file_orig_name = '" . addslashes($_REQUEST["file-name"]) . "'";
	}
	else if ($hasBracket === false) {
		// an asterisk is used -- use LIKE
		$fileName = str_replace(array("\\", "\"", "'", "%", "_", "*", "?"), array("\\\\", "\\\"", "\\'", "\\%", "\\_", "%", "_"), $_REQUEST["file-name"]);
		$fileNameQuery = "and file_orig_name like '$fileName'";
	}
	else {
		// need to do some weird gear -- convert a glob to a regular expression
		//               11111 2222 333333333333 44444444444444444444444444 55555
		preg_match_all('/(\\*)|(\?)|(\\[*?\[\]])|(\[\^?([^\]]*|\\[|\\])*\])|(\[]?([^]]*|\\[|\\])*\])|(.*?)/', $_REQUEST["file-name"], $matches, PREG_SET_ORDER);
		$glob = "^";
		foreach ($matches as $match) {
			if ($match[1]) {	// an asterisk (any number of characters)
				$glob .= ".*";
			}
			else if ($match[2]) {	// a question mark (any one character)
				$glob .= ".";
			}
			else if ($match[3]) {	// an escaped *, ?, [ or ] -- add it verbatim
				$glob .= $match[3];
			}
			else if ($match[4]) {	// a NEGATIVE CHARACTER SET
				$glob .= addslashes($match[4]);
			}
			else if ($match[5]) {	// a POSITIVE CHARACTER SET (can be collapsed into case 3)
				$glob .= addslashes($match[5]);
			}
			else if ($match[6]) {	// regular text -- escape any special characters
				$glob .= preg_replace("/[\\\"'()\[\]{}^$?|+]/", "\\\\$0", $match[6]);
			}
		}
		$glob .= "$";

		$fileNameQuery = "and file_orig_name regexp '$glob'";
	}
}
if (@$_REQUEST["file-description"]) {
	$fileDescriptionSpec = addslashes($_REQUEST["file-description"]);
	$fileDescriptionQuery = "and match (file_description) against ('$fileDescriptionSpec')";
}
if (@$_REQUEST["file-type"]) {
	$fileTypeSpec = $_REQUEST["file-type"];
	if (substr($fileTypeSpec, strlen($fileTypeSpec)-2) == "/*") {
		$fileTypeSpec = str_replace(array("\\", "\"", "'", "%", "_"), array("\\\\", "\\\"", "\\'", "\\%", "\\_"), $fileTypeSpec);
		$fileTypeQuery = "and file_mimetype like '" . addslashes(substr($fileTypeSpec, 0, strlen($fileTypeSpec)-1)) . "%'";
	}
	else {
		$fileTypeQuery = "and file_mimetype = '" . addslashes($_REQUEST["file-type"]) . "'";
	}
}
if (@$_REQUEST["file-any"]) {
	// match against either the filename or the description
	$words = preg_split('/\\s+/', $_REQUEST["file-any"]);

	$fileQuery = "match (file_description) against ('" . addslashes($_REQUEST["file-any"]) . "')";
	foreach ($words as $word) {
		$fileQuery .= " or file_orig_name like '%" . addslashes($word) . "%'";
	}
}
if (! (@$fileNameQuery  ||  @$fileDescriptionQuery  ||  @$fileTypeQuery  ||  @$fileQuery)) {
	print "{\"error\":\"invalid file search specification\"}";
	return;
}

if (@$fileQuery) {
	$query = "select * from files where ($fileQuery) $fileTypeQuery limit " . RESULT_COUNT_LIMIT;
}
else {
	$query = "select * from files where 1 $fileNameQuery $fileDescriptionQuery $fileTypeQuery limit " . RESULT_COUNT_LIMIT;
}

$res = mysql_query($query);
$files = array();
while ($file = mysql_fetch_assoc($res)) {
//	$thumbnailURL = "http://".HEURIST_INSTANCE_PREFIX."heuristscholar.org/heurist/php/resize_image.php?file_id=" . $file["file_nonce"];
	$thumbnailURL = "http://".HEURIST_INSTANCE_PREFIX.HEURIST_SERVER_NAME.HEURIST_SITE_PATH."common/lib/resize_image.php?file_id=" . $file["file_nonce"];
//	$URL = "http://".HEURIST_INSTANCE_PREFIX."heuristscholar.org/heurist/php/fetch_file.php/" . urlencode($file["file_orig_name"]) . "?file_id=" . $file["file_nonce"];
	$URL = "http://".HEURIST_INSTANCE_PREFIX.HEURIST_SERVER_NAME.HEURIST_SITE_PATH."data/files/fetch_file.php/" . urlencode($file["file_orig_name"]) . "?file_id=" . $file["file_nonce"];
	array_push($files, array(
		$file["file_id"], $file["file_orig_name"], $file["file_size"], $file["file_mimetype"], $URL, $thumbnailURL, $file["file_description"]
	));
}

print json_format(array("files" => $files));

?>
