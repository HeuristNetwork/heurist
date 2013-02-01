<?php

/**
 * It seems this is old way of upload file for isEarlyWebkit (ref in editRecord.js only)
 * It is utilized only for old version. (for early webkit)
 * See hapi/php/saveFile.php
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
require_once(dirname(__FILE__)."/../../records/file/uploadFile.php");

if (! is_logged_in()) return;

mysql_connection_overwrite(DATABASE);


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

$upload = $_FILES["file"];// saw NOTE!! this must be the same as the input type=file name (see above)

								//$upload["type"]
$fileID = upload_file($upload["name"], null, $upload["tmp_name"], $upload["error"], $upload["size"], null, false);

if (is_numeric($fileID)) {
	if ($bibID  &&  $bdtID) {
		mysql_query("update Records set rec_Modified=now() where rec_ID=$bibID");
		mysql_query("insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_UploadedFileID) values ($bibID, $bdtID, $fileID)");
	}

	$file = get_uploaded_file_info($fileID, false, false);

	print json_format($file);

/*({ file: {	// saw TODO:  update this to include url or nonce and thumbURL
	id: "<?= $file["ulf_ID"] ?>",
	origName: "<?= slash($file["ulf_OrigFileName"]) ?>",
	date: "<?= slash($file["ulf_Added"]) ?>",
	mimeType: "<?= slash($file["ulf_MimeExt"]) ?>",
	nonce: "<?= slash($file["ulf_ObfuscatedFileID"]) ?>",
	fileSize: "<?= slash($file["ulf_FileSizeKB"]) ?>",
	description: "<?= slash($file["ulf_Description"]) ?>"
} })*/

} else if ($fileID){
	print "({ file: { origName: \"" . slash($_FILES["file"]["name"]) . "\" }, error: $fileID })";
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