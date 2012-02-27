<?php

/**
 * saveURLasFile
 * Downloads file from given URL and saves in uploaded-images.
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
require_once(dirname(__FILE__)."/../../records/files/uploadFile.php");

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
		getError("unauthorised HAPI user");
	}
}
*/

if(@$_REQUEST['url']){
	$sURL = $_REQUEST['url']; //url to be thumbnailed
	$res = generate_thumbnail($sURL, true);
	print json_format($res);
	exit;
}
//
// main function
//
function generate_thumbnail($sURL, $needConnect){

	if (! is_logged_in()) {
		return getError("no logged-in user");
	}

	$res = array();
	//get picture from service
	//"http://www.sitepoint.com/forums/image.php?u=106816&dateline=1312480118";
	$remote_path =  str_replace("[URL]", $sURL, WEBSITE_THUMBNAIL_SERVICE);
	$heurist_path = tempnam(HEURIST_UPLOAD_DIR, "_temp_"); // . $file_id;

	//error_log("22222 WE ARE HERE! ".$remote_path."   ".$heurist_path);

	$filesize = save_image($remote_path, $heurist_path);

	//error_log(">>>>>SAVED SIZE=".$filesize);

	if($filesize>0){

		//check the dimension of returned thumbanil in case it less than 50 - consider it as error
		if(strpos($remote_path, substr(WEBSITE_THUMBNAIL_SERVICE,0,24))==0){

			$image_info = getimagesize($heurist_path);
			if($image_info[1]<50){
				//remove temp file
				unlink($heurist_path);
				return getError("Thumbnail generator service can't create the image for specified URL");
			}
		}

		$fileID = upload_file("snapshot.jpg", "jpg", $heurist_path, null, $filesize, $sURL, $needConnect);
error_log("2222>>>>>>>>>>>".HEURIST_DBNAME);
		if (is_numeric($fileID)) {
			$res = get_uploaded_file_info($fileID, true, $needConnect);
		}
		else {
			$res = getError("File upload was interrupted. ".$fileID);
		}

	//error_log("22222 FILE ID=".$fileID);
	}else{
		$res = getError("Can't download image");
	}

	return $res;
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
	if (defined("HEURIST_HTTP_PROXY")) {
		curl_setopt($ch, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
	}

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

//
//
function getError($message) {
	//mysql_query("rollback");
	return array("error" => addslashes($message));
}
?>
