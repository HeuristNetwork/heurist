<?php

/*
* Copyright (C) 2005-2015 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* It seems this is old way of upload file for isEarlyWebkit (ref in editRecord.js only)
* It is utilized only for old version. (for early webkit)
* See hapi/php/saveFile.php
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Records/Util
* @todo        Need to phase this out as it's for unsupported browsers.
*/


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
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <link rel=stylesheet href="<?=HEURIST_SITE_PATH?>common/css/global.css">
</head>    
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
    <meta http-equiv="content-type" content="text/html; charset=utf-8">

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