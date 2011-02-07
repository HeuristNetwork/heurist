<?php

/*<!--
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/


require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

mysql_connection_db_select(DATABASE);


// May be best to avoid the possibility of somebody harvesting ulf_ID=1, 2, 3, ...
// so the files are indexed by the SHA-1 hash of the concatenation of the ulf_ID and a random integer.

if (! @$_REQUEST['ulf_ID']) return;

$res = mysql_query('select * from recUploadedFiles where ulf_ObfuscatedFileID = "' . addslashes($_REQUEST['ulf_ID']) . '"');
if (mysql_num_rows($res) != 1) return;

$file = mysql_fetch_assoc($res);

$mimeExt = '';
if ($file['ulf_MimeExt']) {
	$mimeExt = $file['ulf_MimeExt'];
} else {
	preg_match('/\\.([^.]+)$/', $file["ulf_OrigFileName"], $matches);	//find the extention
	$mimeExt = $matches[1];
}
if ($mimeExt) {
	$mres = mysql_query("select * from defFileExtToMimetype where fxm_Extension = '$mimeExt'");
}
$mimeType = mysql_fetch_assoc($mres);

if (@$mimeType['fxm_MimeType']) {
	header('Content-type: ' .$mimeType['fxm_MimeType']);
}else{
	header('Content-type: binary/download');
}

$filename = HEURIST_UPLOAD_PATH . $file['ulf_ID'];
//error_log("filename = $filename and mime = ".$mimeType['fxm_MimeType']. " mysqlerr = ".mysql_error());
//error_log("filename = ".$filename);
$filename = str_replace('/../', '/', $filename);
$filename = str_replace('//', '/', $filename);
//error_log("filename = ".$filename);
readfile($filename);

?>
