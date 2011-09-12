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

mysql_connection_db_overwrite(DATABASE);


if (! @$_POST["recID"]) {
	$bibID = intval($_GET["recID"]);
	$bdtID = intval($_GET["bdt_id"]);
}
if (@$bibID  &&  @$bdtID) {
?>
<html>
<head>
<link rel=stylesheet href="<?=HEURIST_SITE_PATH?>common/css/global.css">
<body style="padding: 0; margin: 0;">
<form method=post enctype=multipart/form-data style="display: inline; padding: 0; margin: 0;">
<input type=hidden name=recID value=<?= $bibID ?>>
<input type=hidden name=bdt_id value=<?= $bdtID ?>>
<input type=file name=file onchange="form.submit()">
</form>
</body></html>
<?php
	return;
}

$bibID = intval($_POST["recID"]);
$bdtID = intval($_POST["bdt_id"]);
if (! ($bibID && $bdtID)) { print "<html><body style='color: blue;'></body></html>"; return; }


?>
<html>
<head>
<script>
function fireParentSubmitFunction() {
	var parentFrames = parent.document.getElementsByTagName("iframe");
	var frameElement = null;
	for (var i=0; i < parentFrames.length; ++i)
		if (parentFrames[i].contentWindow == window) frameElement = parentFrames[i];

	if (! frameElement) return;

	frameElement.submitFunction(fileDetails);
}

<?php

print "var fileDetails = ";
$uploadFileError = null;
$upload = $_FILES["file"];// saw NOTE!! this must be the same as the input type=file name (see above)
$fileID = upload_file($upload["name"], $upload["type"], $upload["tmp_name"], $upload["error"], $upload["size"]);
if ($fileID) {
	if ($bibID  &&  $bdtID) {
		mysql_query("update Records set rec_Modified=now() where rec_ID=$bibID");
		mysql_query("insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_UploadedFileID) values ($bibID, $bdtID, $fileID)");
	}

	$res = mysql_query("select * from recUploadedFiles where ulf_ID = $fileID");
	$file = mysql_fetch_assoc($res);
?>
({ file: {	// saw TODO:  update this to include url or nonce and thumbURL
	id: "<?= $file["ulf_ID"] ?>",
	origName: "<?= slash($file["ulf_OrigFileName"]) ?>",
	date: "<?= slash($file["ulf_Added"]) ?>",
	mimeType: "<?= slash($file["ulf_MimeExt"]) ?>",
	nonce: "<?= slash($file["ulf_ObfuscatedFileID"]) ?>",
	fileSize: "<?= slash($file["ulf_FileSizeKB"]) ?>",
	description: "<?= slash($file["ulf_Description"]) ?>"
} })
<?php
} else if ($uploadFileError){
	print "({ file: { origName: \"" . slash($_FILES["file"]["name"]) . "\" }, error: $uploadFileError })";
} else {
	if ($_FILES["file"]["error"]) {
		print "({ file: { origName: \"" . slash($_FILES["file"]["name"]) . "\" }, error: \"Uploaded file was too large\" })";
	} else {
		print "({ file: { origName: \"" . slash($_FILES["file"]["name"]) . "\" }, error: \"File upload was interrupted\" })";
	}
}
?>
</script>
</head>
<body onload="fireParentSubmitFunction()"></body>
</html>
<?php

function upload_file($name, $type, $tmp_name, $error, $size) {
global $uploadFileError;
	/* Check that the uploaded file has a sane name / size / no errors etc,
	 * enter an appropriate record in the recUploadedFiles table,
	 * save it to disk,
	 * and return the ulf_ID for that record.
	 * This will be zero if anything went pear-shaped along the way.
	 */
	if ($size <= 0  ||  $error) { error_log("size is $size, error is $error"); return 0; }

	/* clean up the provided file name -- these characters shouldn't make it through anyway */
	$name = str_replace("\0", '', $name);
	$name = str_replace('\\', '/', $name);
	$name = preg_replace('!.*/!', '', $name);

	$mimetype = null;
	$mimetypeExt = null;
	if (preg_match('/\\.([^.]+)$/', $name, $matches)) {	//find the extention
		$extension = $matches[1];
		$res = mysql_query('select * from defFileExtToMimetype where fxm_Extension = "'.addslashes($extension).'"');
		if (mysql_num_rows($res) == 1) {
			$mimetype = mysql_fetch_assoc($res);
			$mimetypeExt = $mimetype['fxm_Extension'];
		}
	}

	$path = '';	/* can change this to something more complicated later on, to prevent crowding the upload directory
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

	if (move_uploaded_file($tmp_name, HEURIST_UPLOAD_PATH . $file_id)) {
		return $file_id;
	} else {
		/* something messed up ... make a note of it and move on */
		error_log("upload_file: <$name> / <$tmp_name> couldn't be saved as <" . HEURIST_UPLOAD_PATH . $file_id . ">");
		$uploadFileError = "upload file: $name couldn't be saved to upload path definied for db = ". HEURIST_DBNAME;
		mysql_query('delete from recUploadedFiles where ulf_ID = ' . $file_id);
		return 0;
	}
}

?>
