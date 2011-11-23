<?php

/**
 *
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

/**
* @param mixed $name - originak file name
* @param mixed $mimetypeExt - ??
* @param mixed $tmp_name - temporary name from FILE array
* @param mixed $error
* @param mixed $size
*
* @return file ID or error message
*/
function upload_file($name, $mimetypeExt, $tmp_name, $error, $size, $description, $needConnect) {

	if (! is_logged_in()) return "Not logged in";

	/* Check that the uploaded file has a sane name / size / no errors etc,
	 * enter an appropriate record in the recUploadedFiles table,
	 * save it to disk,
	 * and return the ulf_ID for that record.
	 * This will be error message if anything went pear-shaped along the way.
	 */
	if ($size <= 0  ||  $error) {
			error_log("size is $size, error is $error");
			return $error;
	}

	/* clean up the provided file name -- these characters shouldn't make it through anyway */
	$name = str_replace("\0", '', $name);
	$name = str_replace('\\', '/', $name);
	$name = preg_replace('!.*/!', '', $name);

	if($needConnect){
		mysql_connection_db_overwrite(DATABASE);
	}

	if($mimetypeExt){ //check extension
		$res = mysql_query('select fxm_Extension from defFileExtToMimetype where fxm_Extension = "'.addslashes($mimetypeExt).'"');
		if (mysql_num_rows($res) == 1) {
			$mimetype = mysql_fetch_assoc($res);
			$mimetypeExt = $mimetype['fxm_Extension'];
		}
	}

	if (!$mimetypeExt && preg_match('/\\.([^.]+)$/', $name, $matches))
	{	//find the extention

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
													'ulf_Description' => $description? $description : NULL,
													'ulf_FilePath' => HEURIST_UPLOAD_DIR)
													);

	if (! $res) {
		error_log("error inserting file upload info: " . mysql_error());
		$uploadFileError = "Error inserting file upload info into database";
		return $uploadFileError;
	}

	$file_id = mysql_insert_id();
	$filename = "ulf_".$file_id."_".$name;
	mysql_query('update recUploadedFiles set ulf_FileName = "'.$filename.
				'", ulf_ObfuscatedFileID = "' . addslashes(sha1($file_id.'.'.rand())) . '" where ulf_ID = ' . $file_id);
		/* nonce is a random value used to download the file */
//error_log(">>>>".$tmp_name."  >>>> ".$filename);
	$pos = strpos($tmp_name, HEURIST_UPLOAD_DIR);
	if( is_numeric($pos) && $pos==0 && copy($tmp_name, HEURIST_UPLOAD_DIR . "/" . $filename) )
	{
		unlink($tmp_name);
		return $file_id;

	} else if ($tmp_name==null || move_uploaded_file($tmp_name, HEURIST_UPLOAD_DIR . "/" . $filename)) {

		return $file_id;
	} else {
		/* something messed up ... make a note of it and move on */
		$uploadFileError = "upload file: $name couldn't be saved to upload path definied for db = ". HEURIST_DBNAME;
		error_log($uploadFileError);
		mysql_query('delete from recUploadedFiles where ulf_ID = ' . $file_id);
		return $uploadFileError;
	}
}

/**
* put your comment there...
*
* @param mixed $fileID
* @param mixed $needConnect
*/
function get_uploaded_file_info($fileID, $isnamedarray, $needConnect)
{

		if($needConnect){
			mysql_connection_db_overwrite(DATABASE);
		}

		$fres = mysql_query(//saw NOTE! these field names match thoses used in HAPI to init an HFile object.
			"select ulf_ID as id,
			        ulf_ObfuscatedFileID as nonce,
			        ulf_OrigFileName as origName,
			        ulf_FileSizeKB as size,
			        fxm_MimeType as type,
			        ulf_Added as date,
			        ulf_Description as description,
			        ulf_MimeExt as ext
			   from recUploadedFiles left join defFileExtToMimetype on ulf_MimeExt = fxm_Extension
			  where ulf_ID = ".intval($fileID));

		$res = array("file" => mysql_fetch_assoc($fres));

		$origName = urlencode($res["file"]["origName"]);
		$res["file"]["URL"] =
			HEURIST_URL_BASE."records/files/downloadFile.php/".$origName."?".
				(defined('HEURIST_DBNAME') ? "db=".HEURIST_DBNAME."&" : "" )."ulf_ID=".$res["file"]["nonce"];
		$res["file"]["thumbURL"] =
			HEURIST_URL_BASE."common/php/resizeImage.php?".
				(defined('HEURIST_DBNAME') ? "db=".HEURIST_DBNAME."&" : "" )."ulf_ID=".$res["file"]["nonce"];

		if(!$isnamedarray){

			$res = array("file" => array(	// file[0] => id , file [1] => origFileName, etc...
					$res["file"]["id"],
					$res["file"]["origName"],
					$res["file"]["size"],
					$res["file"]["ext"],
					$res["file"]["URL"],
					$res["file"]["thumbURL"],
					$res["file"]["description"]
			));

		}
/*
			$res = mysql_query("select * from recUploadedFiles where ulf_ID = $fileID");
			$file = mysql_fetch_assoc($res);
			$origName = urlencode($file["ulf_OrigFileName"]);

			$thumbnailURL = HEURIST_URL_BASE."common/php/resizeImage.php?".
				(defined('HEURIST_DBNAME') ? "db=".HEURIST_DBNAME."&" : "" )."ulf_ID=" . $file["ulf_ObfuscatedFileID"];
			$URL = HEURIST_URL_BASE."records/files/downloadFile.php/".$origName."?".
				(defined('HEURIST_DBNAME') ? "db=".HEURIST_DBNAME."&" : "" )."ulf_ID=" . $file["ulf_ObfuscatedFileID"];
		//error_log("url = ". $URL);
			$res = array("file" => array(	// file[0] => id , file [1] => origFileName, etc...
					$file["ulf_ID"],
					$file["ulf_OrigFileName"],
					$file["ulf_FileSizeKB"],
					$file["ulf_MimeExt"],
					$URL,
					$thumbnailURL,
					$file["ulf_Description"]
			));
		}
*/

	return $res;

}
?>
