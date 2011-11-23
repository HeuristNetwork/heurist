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
require_once(dirname(__FILE__)."/../../records/files/uploadFile.php");

//error_log("in saveFile baseURL = ".HEURIST_URL_BASE );
if (! defined("USING-XSS")) {
	function outputAsRedirect($text) {
		$val = base64_encode($text);
		header("Location: ".HEURIST_URL_BASE."/#data=" . $val);
		return "";
	}
	ob_start("outputAsRedirect");

	if ($_POST["heurist-sessionid"] != $_COOKIE["heurist-sessionid"]) {	// saw TODO: check that this is ok or should this be the database session?
		// saveFile is only available through dispatcher.php, or if heurist-sessionid is known (presumably only our scripts will know this)
		jsonError("unauthorised HAPI user");
	}
}

if (! is_logged_in()) {
	jsonError("no logged-in user");
}


mysql_connection_db_overwrite(DATABASE);

mysql_query("start transaction");


$upload = $_FILES["file"];
error_log("upload file info - ". print_r($_FILES["file"],true));
									//$upload["type"]
$fileID = upload_file($upload["name"], null, $upload["tmp_name"], $upload["error"], $upload["size"], $_REQUEST["description"], false);

if (is_numeric($fileID)) {

	$file = get_uploaded_file_info($fileID, false, false);
	print json_format($file);

	mysql_query("commit");
}
else if ($fileID) {
	jsonError($fileID);
}else if ($_FILES["file"]["error"]) {
	jsonError("uploaded file was too large");
}
else {
	jsonError("file upload was interrupted");
}

//***** END OF OUTPUT *****/


function jsonError($message) {
	mysql_query("rollback");
	print "{\"error\":\"" . addslashes($message) . "\"}";
}


?>
