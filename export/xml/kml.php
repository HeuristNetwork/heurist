<?php
header('Content-type: text/xml; charset=utf-8');

require_once(dirname(__FILE__).'/../../common/config/heurist-instances.php');
require_once(dirname(__FILE__).'/../../common/connect/db.php');

define("KML_DETAIL_TYPE", 551);
define("FILE_DETAIL_TYPE", 221);

mysql_connection_select(DATABASE);

$res = mysql_query("select rd_val from rec_details where rd_rec_id = " . intval($_REQUEST["id"]) . " and rd_type = " . KML_DETAIL_TYPE);
if (mysql_num_rows($res)) {
	$kml = mysql_fetch_array($res);
	print $kml[0];
} else {
	$res = mysql_query("select file_id from rec_details left join files on file_id = rd_file_id where rd_rec_id = " . intval($_REQUEST["id"]) . " and rd_type = " . FILE_DETAIL_TYPE);
	$file_id = mysql_fetch_array($res);
	$file_id = $file_id[0];
	print file_get_contents(UPLOAD_PATH . "/" . $file_id);
}

?>
