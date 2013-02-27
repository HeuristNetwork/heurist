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

	/*****DEBUG****///error_log("in saveFile baseURL = ".HEURIST_BASE_URL );
	if (! defined("USING-XSS")) {
		function outputAsRedirect($text) {
			$val = base64_encode($text);
			header("Location: ".HEURIST_BASE_URL."/#data=" . $val);
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

    if ( $_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST) &&  empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0 ){

        jsonError("File is too large. ".$_SERVER['CONTENT_LENGTH']." bytes exceeds the limit of ".ini_get('post_max_size').
        ". Please get system administrator to increase the file size limits or load your large files on a video server or other suitable web service and use the URL to reference the file here");

    }else{
        $upload = @$_FILES["file"];

        if($upload){

            /*****DEBUG****///error_log("upload file info - ". print_r($_FILES["file"],true));

	        mysql_connection_overwrite(DATABASE);

	        mysql_query("start transaction");
        //POST Content-Length of 103399974 bytes exceeds the limit of 29360128 bytes in Unknown on line

	        //$upload["type"]
	        $fileID = upload_file($upload["name"], null, $upload["tmp_name"], $upload["error"], $upload["size"], $_REQUEST["description"], false);

	        if (is_numeric($fileID)) {

		        $file = get_uploaded_file_info($fileID, false);
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
        }else{
                jsonError("File data are not posted to server side");
        }
    }

	//***** END OF OUTPUT *****/


	function jsonError($message) {
		mysql_query("rollback");
		print "{\"error\":\"" . addslashes($message) . "\"}";
	}


?>
