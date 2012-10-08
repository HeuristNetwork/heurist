<?php
/* resizeImage.php
 *
 * Thumbnail service
 * It loads thumbnail from recUploadedFiles.ulf_Thumbnail
 * Or creates new thumbnail and stores it in this field in case this is standard thumnail request
 *
 * parameters
 * w, h or maxw, maxh - size of thumbnail, if they are omitted - it returns standard thumbnail 100x100
 * ulf_ID - file ID from recUploadedFiles, if defined and standard thumbnail - thumbnail is stored in table
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 *
 */

 header('Content-type: image/png');

require_once(dirname(__FILE__).'/../connect/applyCredentials.php');
require_once(dirname(__FILE__).'/dbMySqlWrappers.php');
require_once(dirname(__FILE__)."/../../records/files/uploadFile.php");


if (! @$_REQUEST['w']  &&  ! @$_REQUEST['h']  &&  ! @$_REQUEST['maxw']  &&  ! @$_REQUEST['maxh']) {
	$standard_thumb = true;
	$x = 100;
	$y = 100;
	$no_enlarge = false;
} else {
	$standard_thumb = false;
	$x = @$_REQUEST['w'] ? intval($_REQUEST['w']) : 0;
	$y = @$_REQUEST['h'] ? intval($_REQUEST['h']) : 0;
	$x = @$_REQUEST['maxw'] ? intval($_REQUEST['maxw']) : $x;
	$y = @$_REQUEST['maxh'] ? intval($_REQUEST['maxh']) : $y;
	$no_enlarge = @$_REQUEST['maxw']  ||  @$_REQUEST['maxh'];
}

$img = null;

mysql_connection_db_overwrite(DATABASE);
mysql_query('set character set binary');

if (array_key_exists('ulf_ID', $_REQUEST))
{
	$thumbnail_file = HEURIST_THUMB_DIR."ulf_".$_REQUEST['ulf_ID'].".png";
	/* if we here we create file. See uploadFile, there we check the existence of file
	if($standard_thumb && file_exists($thumbnail_file)){
		header("Location: ".HEURIST_THUMB_URL_BASE."ulf_".$_REQUEST['ulf_ID'].".png");
		return;
	}*/


	$res = mysql_query('select * from recUploadedFiles where ulf_ObfuscatedFileID = "' . addslashes($_REQUEST['ulf_ID']) . '"');
	if (mysql_num_rows($res) != 1) return;
	$file = mysql_fetch_assoc($res);

	if ($standard_thumb  &&  $file['ulf_Thumbnail']) {

		//save as file
		$img = imagecreatefromstring($file['ulf_Thumbnail']);
		imagepng($img, $thumbnail_file);

		// thumbnail exists
		echo $file['ulf_Thumbnail'];
		return;
	}
/*****DEBUG****///error_log(">>>>>>>>>".$file['ulf_Parameters']);
	$fileparams = parseParameters($file['ulf_Parameters']);
	$type_media	 = (array_key_exists('mediatype', $fileparams)) ?$fileparams['mediatype']:null;
	$type_source = (array_key_exists('source', $fileparams)) ?$fileparams['source']:null;

	if($type_source==null || $type_source=='heurist') {
		if ($file['ulf_FileName']) {
			$filename = $file['ulf_FilePath'].$file['ulf_FileName']; // post 18/11/11 proper file path and name
		} else {
			$filename = HEURIST_UPLOAD_DIR . $file['ulf_ID']; // pre 18/11/11 - bare numbers as names, just use file ID
		}
		$filename = str_replace('/../', '/', $filename);
	}

	if (isset($filename) && file_exists($filename)){

		if ($file['ulf_FileName']) {
			$filename = $file['ulf_FilePath'].$file['ulf_FileName']; // post 18/11/11 proper file path and name
		} else {
			$filename = HEURIST_UPLOAD_DIR . $file['ulf_ID']; // pre 18/11/11 - bare numbers as names, just use file ID
		}
		$filename = str_replace('/../', '/', $filename);

		$mimeExt = '';
		if ($file['ulf_MimeExt']) {
			$mimeExt = $file['ulf_MimeExt'];
		} else {
			preg_match('/\\.([^.]+)$/', $file["ulf_OrigFileName"], $matches);	//find the extention
			$mimeExt = $matches[1];
		}

	/*****DEBUG****///error_log("filename = $filename and mime = $mimeExt");

		switch($mimeExt) {
		case 'image/jpeg':
		case 'jpeg':
		case 'jpg':
			$img = imagecreatefromjpeg($filename);
			break;
		case 'image/gif':
		case 'gif':
			$img = imagecreatefromgif($filename);
			break;
		case 'image/png':
		case 'png':
			$img = imagecreatefrompng($filename);
			break;
		default:
			$desc = '***' . strtoupper(preg_replace('/.*[.]/', '', $file['ulf_OrigFileName'])) . ' file';
			$img = make_file_image($desc); //from string
			break;
		}

	}else if($file['ulf_ExternalFileReference']){

		 if($type_media=='image'){ //$type_source=='generic' &&
		 		//@todo for image services (panoramio, flikr) take thumbnails directly
		 		$img = get_remote_image($file['ulf_ExternalFileReference']);
		}else if($type_source=='youtube'){
				$url = $file['ulf_ExternalFileReference'];
				$youtubeid = preg_replace('/^[^v]+v.(.{11}).*/' , '$1', $url);
				$img = get_remote_image("http://img.youtube.com/vi/".$youtubeid."/0.jpg"); //get thumbnail
		}
	}

}
else if (array_key_exists('file_url', $_REQUEST))   //get thumbnail for any URL
{

	$img = get_remote_image($_REQUEST['file_url']);

}


if (!$img) {
	header('Location: ../images/100x100-check.gif');
	return;
}


// calculate image size
// note - we never change the aspect ratio of the image!
$orig_x = imagesx($img);
$orig_y = imagesy($img);

/*****DEBUG****///error_log(" image orig size x= $orig_x  y=$orig_y");
$rx = $x / $orig_x;
$ry = $y / $orig_y;

$scale = $rx ? $ry ? min($rx, $ry) : $rx : $ry;

if ($no_enlarge  &&  $scale > 1) {
	$scale = 1;
}

$new_x = ceil($orig_x * $scale);
$new_y = ceil($orig_y * $scale);
/*****DEBUG****///DEBUG error_log(" image new size x= $new_x  y=$new_y");

$img_resized = imagecreatetruecolor($new_x, $new_y)  or die;
imagecopyresampled($img_resized, $img, 0, 0, 0, 0, $new_x, $new_y, $orig_x, $orig_y)  or die;

if ($standard_thumb  &&  @$file) {
	$resized_file = $thumbnail_file;
}else{
	$resized_file = tempnam('/tmp', 'resized');
}

imagepng($img_resized, $resized_file);
imagedestroy($img);
imagedestroy($img_resized);

$resized = file_get_contents($resized_file);

if ($standard_thumb  &&  @$file) {
	// store to database
	mysql_query('update recUploadedFiles set ulf_Thumbnail = "' . addslashes($resized) . '" where ulf_ID = ' . $file['ulf_ID']);
}else{
	unlink($resized_file);
}

// output to browser
echo $resized;

/**
* Creates image from given string
*
* @param mixed $desc - text to be inserted into resulted image
* @return resource - image with the given text
*/
function make_file_image($desc) {
	$desc = preg_replace('/\\s+/', ' ', $desc);

	$font = 3; $fw = imagefontwidth($font); $fh = imagefontheight($font);
	$desc_lines = explode("\n", wordwrap($desc, intval(100/$fw)-1, "\n", false));
	$longlines = false;
	if (count($desc_lines) > intval(100/$fh)) {
		$longlines = true;
	} else {
		foreach ($desc_lines as $line) {
			if (strlen($line) >= intval(100/$fw)) {
				$longlines = true;
				break;
			}
		}
	}
	if ($longlines) {
		$font = 1; $fw = imagefontwidth($font); $fh = imagefontheight($font);
		$desc_lines = explode("\n", wordwrap($desc, intval(100/$fw)-1, "\n", true));
	}


	$im = imagecreate(100, 100);
	$white = imagecolorallocate($im, 255, 255, 255);
	$grey = imagecolorallocate($im, 160, 160, 160);
	$black = imagecolorallocate($im, 0, 0, 0);
	imagefilledrectangle($im, 0, 0, 100, 100, $white);

	//imageline($im, 35, 25, 65, 75, $grey);
	imageline($im, 33, 25, 33, 71, $grey);
	imageline($im, 33, 25, 62, 25, $grey);
	imageline($im, 62, 25, 67, 30, $grey);
	imageline($im, 67, 30, 62, 30, $grey);
	imageline($im, 62, 30, 62, 25, $grey);
	imageline($im, 67, 30, 67, 71, $grey);
	imageline($im, 67, 71, 33, 71, $grey);

	$y = intval((100 - count($desc_lines)*$fh) / 2);
	foreach ($desc_lines as $line) {
		$x = intval((100 - strlen($line)*$fw) / 2);
		imagestring($im, $font, $x, $y, $line, $black);
		$y += $fh;
	}

	return $im;
}

/**
* download image from given url
*
* @param mixed $remote_url
* @return resource
*/
function get_remote_image($remote_url){

	$img = null;

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_COOKIEFILE, '/dev/null');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);	//return the output as a string from curl_exec
	curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
	curl_setopt($ch, CURLOPT_NOBODY, 0);
	curl_setopt($ch, CURLOPT_HEADER, 0);	//don't include header in output
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);	// follow server header redirects
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);	// don't verify peer cert
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);	// timeout after ten seconds
	curl_setopt($ch, CURLOPT_MAXREDIRS, 5);	// no more than 5 redirections
	if (defined("HEURIST_HTTP_PROXY")) {
		curl_setopt($ch, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
	}

	curl_setopt($ch, CURLOPT_URL, $remote_url);
	$data = curl_exec($ch);

	$error = curl_error($ch);
	if ($error) {
		$code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		error_log("$error ($code)" . " url = ". $remote_url);
	} else {
		$img = imagecreatefromstring($data);
	}

	return $img;
}
?>
