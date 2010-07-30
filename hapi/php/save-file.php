<?php

require_once(dirname(__FILE__)."/../../common/connect/cred.php");
require_once(dirname(__FILE__)."/../../common/connect/db.php");

error_log("in save-file baseURL = ".HEURIST_URL_BASE );
if (! defined("USING-XSS")) {
	function outputAsRedirect($text) {
		$val = base64_encode($text);
		header("Location: ".HEURIST_URL_BASE."/#data=" . $val);
		return "";
	}
	ob_start("outputAsRedirect");

	if ($_POST["heurist-sessionid"] != $_COOKIE["heurist-sessionid"]) {
		// save-file is only available through xss.php, or if heurist-sessionid is known (presumably only our scripts will know this)
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
	$res = mysql_query("select * from files where file_id = $fileID");
	$file = mysql_fetch_assoc($res);
	$thumbnailURL = "http://".HEURIST_INSTANCE_PREFIX.HEURIST_SERVER_NAME.HEURIST_SITE_PATH."common/lib/resize_image.php?file_id=" . $file["file_nonce"];
	$URL = "http://".HEURIST_INSTANCE_PREFIX.HEURIST_SERVER_NAME.HEURIST_SITE_PATH."data/files/fetch_file.php/" . urlencode($file["file_orig_name"]) . "?file_id=" . $file["file_nonce"];
error_log("url = ". $URL);
	print json_format(array("file" => array(
		$file["file_id"], $file["file_orig_name"], $file["file_size"], $file["file_mimetype"], $URL, $thumbnailURL, $file["file_description"]
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
	 * enter an appropriate record in the files table,
	 * save it to disk,
	 * and return the file_id for that record.
	 * This will be zero if anything went pear-shaped along the way.
	 */
error_log("in save-file upload_file  name = ". $name. " type = ". $type. " error = ". $error. " size = " . $size . " uploadPath = ". UPLOAD_PATH );

	if ($size <= 0  ||  $error) { error_log("size is $size, error is $error"); return 0; }

	/* clean up the provided file name -- these characters shouldn't make it through anyway */
	$name = str_replace("\0", '', $name);
	$name = str_replace('\\', '/', $name);
	$name = preg_replace('!.*/!', '', $name);

	$mimetype = null;
	$file_description = null;
	if (preg_match('/\\.([^.]+)$/', $name, $matches)) {
		$extension = $matches[1];
		$res = mysql_query('select * from file_to_mimetype where extension = "'.addslashes($extension).'"');
		if (mysql_num_rows($res) == 1) {
			$mimetype = mysql_fetch_assoc($res);
			$file_description = $mimetype['file_description'];
			$mimetype = $mimetype['mime_type'];
		}
	}

	$path = '';	/* can change this to something more complicated later on, to prevent crowding the upload directory */

	$file_size = '';
	if ($size < 1000) $file_size = $size . ' bytes';
	else if ($size < 1000000) $file_size = (round($size / 102.4)/10) . ' kb';
	else $file_size = (round($size / (1024*102.4))/10) . ' Mb';

	$res = mysql__insert('files', array('file_orig_name' => $name, 'file_path' => '', 'file_user_id' => get_user_id(),
	                                    'file_date' => date('Y-m-d H:i:s'),
	                                    'file_typedescription' => $file_description, 'file_mimetype' => $mimetype,
	                                    'file_size' => $file_size,
	                                    'file_description' => $description? $description : NULL));
	if (! $res) { error_log("error inserting: " . mysql_error()); return 0; }
	$file_id = mysql_insert_id();
	mysql_query('update files set file_nonce = "' . addslashes(sha1($file_id.'.'.rand())) . '" where file_id = ' . $file_id);
		/* nonce is a random value used to download the file */

	if (move_uploaded_file($tmp_name, UPLOAD_PATH . $path . '/' . $file_id)) {
		return $file_id;
	} else {
		/* something messed up ... make a note of it and move on */
		error_log("upload_file: <$name> / <$tmp_name> couldn't be saved as <" . UPLOAD_PATH . $path . '/' . $file_id . ">");
		mysql_query('delete from files where file_id = ' . $file_id);
		return 0;
	}
}

?>
