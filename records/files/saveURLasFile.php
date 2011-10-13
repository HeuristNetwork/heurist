<?php

/**
 * saveURLasFile
 * Dowbloads file from given URL and saves in uploaded-images.
 * Registers it in recUploadedFiles table
 *
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
/*
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
*/

if (! is_logged_in()) {
	jsonError("no logged-in user");
	exit;
}

$sURL = $_REQUEST['url'];

//get picture from service

//"http://www.sitepoint.com/forums/image.php?u=106816&dateline=1312480118";
$remote_path = "http://immediatenet.com/t/m?Size=1024x768&URL=".$sURL;
$heurist_path = tempnam(HEURIST_UPLOAD_DIR, "_temp_"); // . $file_id;

//error_log("22222 WE ARE HERE! ".$remote_path."   ".$heurist_path);

$filesize = save_image($remote_path, $heurist_path);

//error_log(">>>>>SAVED SIZE=".$filesize);

if($filesize>0){

mysql_connection_db_overwrite(DATABASE);

mysql_query("start transaction");

$fileID = upload_file("snapshot", "image/jpeg", $heurist_path, null, $filesize, $sURL);

if ($fileID) {
	$res = mysql_query("select * from recUploadedFiles where ulf_ID = $fileID");
	$file = mysql_fetch_assoc($res);
	//$thumbnailURL = HEURIST_URL_BASE."common/php/resizeImage.php?db=".HEURIST_DBNAME."&ulf_ID=" . $file["ulf_ObfuscatedFileID"];
	$URL = HEURIST_URL_BASE."records/files/downloadFile.php/" . urlencode($file["ulf_OrigFileName"]) . "?db=".HEURIST_DBNAME."&ulf_ID=" . $file["ulf_ObfuscatedFileID"];
//error_log("url = ". $URL);
	print json_format(array("file" => array(	// file[0] => id , file [1] => origFileName, etc...
		$file["ulf_ID"], $file["ulf_OrigFileName"], $file["ulf_FileSizeKB"], $file["ulf_MimeExt"], $URL, $URL, $file["ulf_Description"]
	)));
	mysql_query("commit");
}
else {
	jsonError("file upload was interrupted");
}

//error_log("22222 FILE ID=".$fileID);
}else{
	jsonError("Can't download image");
}


//***** END OF OUTPUT *****/
function save_image3($inPath, $outPath){
	file_put_contents($outPath, file_get_contents($inPath));
	return filesize($outPath);
}

function save_image($inPath, $outPath)
{ //Download images from remote server

	$ch = curl_init($inPath);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);	// follow server header redirects
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);	// don't verify peer cert
	curl_setopt($ch, CURLOPT_TIMEOUT, 100);	// timeout after ten seconds
	curl_setopt($ch, CURLOPT_MAXREDIRS, 5);	// no more than 5 redirections
	curl_setopt($ch, CURLOPT_PROXY, 'www-cache.usyd.edu.au:8080');

    $rawdata=curl_exec($ch);

    curl_close ($ch);
    if(file_exists($outPath)){
        unlink($outPath);
    }
    $fp = fopen($outPath,'x');
    fwrite($fp, $rawdata);
    fclose($fp);

return filesize($outPath);
}



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
//error_log("in saveFile upload_file  name = ". $name. " type = ". $type. " error = ". $error. " size = " . $size . " uploadDir = ". HEURIST_UPLOAD_DIR );

	if ($size <= 0  ||  $error) { error_log("size is $size, error is $error"); return 0; }

	/* clean up the provided file name -- these characters shouldn't make it through anyway */
	$name = str_replace("\0", '', $name);
	$name = str_replace('\\', '/', $name);
	$name = preg_replace('!.*/!', '', $name);

	$mimetype = null;
	$mimetypeExt = null;
	if($type){
		$mimetype = $type;
		$mimetypeExt = "jpg"; //hardcoded!!!!
	}else if (preg_match('/\\.([^.]+)$/', $name, $matches)) {
		$extension = $matches[1];
		$res = mysql_query('select * from defFileExtToMimetype where fxm_Extension = "'.addslashes($extension).'"');
		if (mysql_num_rows($res) == 1) {
			$mimetype = mysql_fetch_assoc($res);
			$mimetypeExt = $mimetype['fxm_Extension'];
		}
	}


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
	if (! $res) { error_log("error inserting file upload info: " . mysql_error()); return 0; }
	$file_id = mysql_insert_id();
	mysql_query('update recUploadedFiles set ulf_ObfuscatedFileID = "' . addslashes(sha1($file_id.'.'.rand())) . '" where ulf_ID = ' . $file_id);
		/* nonce is a random value used to download the file */

	if(copy($tmp_name, HEURIST_UPLOAD_DIR . "/" . $file_id)) {
		unlink($tmp_name);
		return $file_id;
	} else {
		// something messed up ... make a note of it and move on
		error_log("upload_file: <$name> / <$tmp_name> couldn't be saved as <" . HEURIST_UPLOAD_DIR . "/" . $file_id . ">");
		mysql_query('delete from recUploadedFiles where ulf_ID = ' . $file_id);
		return 0;
	}

}

?>
