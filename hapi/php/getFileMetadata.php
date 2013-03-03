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

?>

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

session_cache_limiter('no-cache');
define('SAVE_URI', 'disabled');
define('SEARCH_VERSION', 1);

define('RESULT_COUNT_LIMIT', 20);

require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

mysql_connection_select(DATABASE);


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
		$fileNameQuery = "and ulf_OrigFileName = '" . addslashes($_REQUEST["file-name"]) . "'";
	}
	else if ($hasBracket === false) {
		// an asterisk is used -- use LIKE
		$fileName = str_replace(array("\\", "\"", "'", "%", "_", "*", "?"), array("\\\\", "\\\"", "\\'", "\\%", "\\_", "%", "_"), $_REQUEST["file-name"]);
		$fileNameQuery = "and ulf_OrigFileName like '$fileName'";
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

		$fileNameQuery = "and ulf_OrigFileName regexp '$glob'";
	}
}
if (@$_REQUEST["file-description"]) {
	$fileDescriptionSpec = addslashes($_REQUEST["file-description"]);
	$fileDescriptionQuery = "and match (ulf_Description) against ('$fileDescriptionSpec')";
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

	$fileQuery = "match (ulf_Description) against ('" . addslashes($_REQUEST["file-any"]) . "')";
	foreach ($words as $word) {
		$fileQuery .= " or ulf_OrigFileName like '%" . addslashes($word) . "%'";
	}
}
if (! (@$fileNameQuery  ||  @$fileDescriptionQuery  ||  @$fileTypeQuery  ||  @$fileQuery)) {
	print "{\"error\":\"invalid file search specification\"}";
	return;
}

if (@$fileQuery) {
	$query = "select * from recUploadedFiles where ($fileQuery) $fileTypeQuery limit " . RESULT_COUNT_LIMIT;
}
else {
	$query = "select * from recUploadedFiles where 1 $fileNameQuery $fileDescriptionQuery $fileTypeQuery limit " . RESULT_COUNT_LIMIT;
}

$res = mysql_query($query);
$files = array();
while ($file = mysql_fetch_assoc($res)) {
//	$thumbnailURL = "http://".HEURIST_SESSION_DB_PREFIX."heuristscholar.org/heurist/php/resizeImage.php?ulf_ID=" . $file["ulf_ObfuscatedFileID"];
	$thumbnailURL = HEURIST_BASE_URL."common/php/resizeImage.php?db=".HEURIST_DBNAME."&ulf_ID=" . $file["ulf_ObfuscatedFileID"];
//	$URL = "http://".HEURIST_SESSION_DB_PREFIX."heuristscholar.org/heurist/php/downloadFile.php/" . urlencode($file["ulf_OrigFileName"]) . "?ulf_ID=" . $file["ulf_ObfuscatedFileID"];
	$URL = HEURIST_BASE_URL."records/files/downloadFile.php/" . urlencode($file["ulf_OrigFileName"]) . "?db=".HEURIST_DBNAME."&ulf_ID=" . $file["ulf_ObfuscatedFileID"];
	array_push($files, array(
		$file["ulf_ID"], $file["ulf_OrigFileName"], $file["ulf_FileSizeKB"], $file["file_mimetype"], $URL, $thumbnailURL, $file["ulf_Description"]
	));
}

print json_format(array("files" => $files));

?>
