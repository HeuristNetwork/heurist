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

//error_log("in saveFile baseURL = ".HEURIST_URL_BASE );
if (! defined("USING-XSS")) {
	function outputAsRedirect($text) {
		$val = base64_encode($text);
		header("Location: ".HEURIST_URL_BASE."/#data=" . $val);
		return "";
	}
	ob_start("outputAsRedirect");

	if ($_POST["heurist-sessionid"] != $_COOKIE["heurist-sessionid"]) {
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
$fileID = upload_file($upload["name"], $upload["type"], $upload["tmp_name"], $upload["error"], $upload["size"], $_REQUEST["description"]);

if ($fileID) {
	$res = mysql_query("select * from recUploadedFiles where ulf_ID = $fileID");
	$file = mysql_fetch_assoc($res);
	$thumbnailURL = HEURIST_URL_BASE."common/php/resizeImage.php?instance=".HEURIST_INSTANCE."&ulf_ID=" . $file["ulf_ObfuscatedFileID"];
	$URL = HEURIST_URL_BASE."records/files/downloadFile.php/" . urlencode($file["ulf_OrigFileName"]) . "?instance=".HEURIST_INSTANCE."&ulf_ID=" . $file["ulf_ObfuscatedFileID"];
error_log("url = ". $URL);
	print json_format(array("file" => array(
		$file["ulf_ID"], $file["ulf_OrigFileName"], $file["ulf_FileSizeKB"], $file["file_mimetype"], $URL, $thumbnailURL, $file["ulf_Description"]
	)));
	mysql_query("commit");
}
else if ($_FILES["file"]["error"]) {
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

function upload_file($name, $type, $tmp_name, $error, $size, $description) {
	/* Check that the uploaded file has a sane name / size / no errors etc,
	 * enter an appropriate record in the recUploadedFiles table,
	 * save it to disk,
	 * and return the ulf_ID for that record.
	 * This will be zero if anything went pear-shaped along the way.
	 */
error_log("in saveFile upload_file  name = ". $name. " type = ". $type. " error = ". $error. " size = " . $size . " uploadPath = ". UPLOAD_PATH );

	if ($size <= 0  ||  $error) { error_log("size is $size, error is $error"); return 0; }

	/* clean up the provided file name -- these characters shouldn't make it through anyway */
	$name = str_replace("\0", '', $name);
	$name = str_replace('\\', '/', $name);
	$name = preg_replace('!.*/!', '', $name);

	$mimetype = null;
	$mimetypeExt = null;
	if (preg_match('/\\.([^.]+)$/', $name, $matches)) {
		$extension = $matches[1];
		$res = mysql_query('select * from defFileExtToMimetype where fxm_Extension = "'.addslashes($extension).'"');
		if (mysql_num_rows($res) == 1) {
			$mimetype = mysql_fetch_assoc($res);
			$mimetypeExt = $mimetype['fxm_Extension'];
		}
	}

	$path = ''; /* can change this to something more complicated later on, to prevent crowding the upload directory
				 the path MUST start and NOT END with a slash so that  "UPLOAD_PATH . $path . '/' .$file_id" is valid */

	if ($size && $size < 1024) {
		$file_size = 1;
	}else{
		$file_size = round($size / 1024);
	}

	$res = mysql__insert('recUploadedFiles', array(	'ulf_OrigFileName' => $name,
													'ulf_UploaderUGrpID' => get_user_id(),
													'ulf_Added' => date('Y-m-d H:i:s'),
													'ulf_MimeExt ' => $mimetypeExt,
													'ulf_FileSizeKB' => $file_size,
													'ulf_Description' => $description? $description : NULL));
	if (! $res) { error_log("error inserting: " . mysql_error()); return 0; }
	$file_id = mysql_insert_id();
	mysql_query('update recUploadedFiles set ulf_ObfuscatedFileID = "' . addslashes(sha1($file_id.'.'.rand())) . '" where ulf_ID = ' . $file_id);
		/* nonce is a random value used to download the file */

	if (move_uploaded_file($tmp_name, UPLOAD_PATH . $path . $file_id)) {
		return $file_id;
	} else {
		/* something messed up ... make a note of it and move on */
		error_log("upload_file: <$name> / <$tmp_name> couldn't be saved as <" . UPLOAD_PATH . $path . $file_id . ">");
		mysql_query('delete from recUploadedFiles where ulf_ID = ' . $file_id);
		return 0;
	}
}

?>
