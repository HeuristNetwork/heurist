<?php

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

mysql_connection_db_select(DATABASE);


// May be best to avoid the possibility of somebody harvesting ulf_ID=1, 2, 3, ...
// so the files are indexed by the SHA-1 hash of the concatenation of the ulf_ID and a random integer.

if (! @$_REQUEST['ulf_ID']) return;

$res = mysql_query('select * from recUploadedFiles where ulf_ObfuscatedFileID = "' . addslashes($_REQUEST['ulf_ID']) . '"');
if (mysql_num_rows($res) != 1) return;

$file = mysql_fetch_assoc($res);
if ($file['file_mimetype'])
	header('Content-type: ' . $file['file_mimetype']);
else
	header('Content-type: binary/download');

$filename = UPLOAD_PATH . $file['file_path'] . '/' . $file['ulf_ID'];
//error_log("filename = ".$filename);
$filename = str_replace('/../', '/', $filename);
$filename = str_replace('//', '/', $filename);
//error_log("filename = ".$filename);
readfile($filename);

?>
