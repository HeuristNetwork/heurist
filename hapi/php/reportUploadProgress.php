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

if (! is_logged_in()) return;

$_REQUEST = json_decode(@$_POST["data"]?  $_POST["data"] : base64_decode(@$_GET["data"]), true);

/* Takes a single parameter -- uploadID
 * and returns data about the upload in progress
 */

if (! $_REQUEST["uploadIDs"]  &&  $_REQUEST["uploadID"])
	$uploadIDs = array($_REQUEST["uploadID"]);
else if ($_REQUEST["uploadIDs"])
	$uploadIDs = $_REQUEST["uploadIDs"];

if (! $uploadIDs) {
	print '{\"error\": "Internal upload error"}';	// ??!
	return;
}

header("Content-type: text/javascript");

$infos = array();
foreach ($uploadIDs as $uploadID) {
	$info = uploadprogress_get_info($uploadID);		// provided by uploadprogress php module

	if ($info === null) {
		$infos["$uploadID"] = array("done" => true);
	}
	else {
		$infos["$uploadID"] = array(
			"bytesTotal" => intval($info["bytes_total"]),
			"bytesUploaded" => intval($info["bytes_uploaded"]),
			"estimatedTimeRemaining" => intval($info["est_sec"]),
			"averageSpeed" => intval($info["speed_average"]),
			"filesUploaded" => intval($info["files_uploaded"])
		);
	}
}

print json_format($infos);
?>
