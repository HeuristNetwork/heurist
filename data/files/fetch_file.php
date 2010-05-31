<?php

require_once(dirname(__FILE__)."/../../common/connect/cred.php");
require_once(dirname(__FILE__)."/../../common/connect/db.php");

mysql_connection_db_select(DATABASE);


// May be best to avoid the possibility of somebody harvesting file_id=1, 2, 3, ...
// so the files are indexed by the SHA-1 hash of the concatenation of the file_id and a random integer.

if (! @$_REQUEST['file_id']) return;

$res = mysql_query('select * from files where file_nonce = "' . addslashes($_REQUEST['file_id']) . '"');
if (mysql_num_rows($res) != 1) return;

$file = mysql_fetch_assoc($res);
if ($file['file_mimetype'])
	header('Content-type: ' . $file['file_mimetype']);
else
	header('Content-type: binary/download');

$filename = UPLOAD_PATH . $file['file_path'] . '/' . $file['file_id'];
$filename = str_replace('/../', '/', $filename);
readfile($filename);

?>
