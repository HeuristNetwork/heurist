<?php

/**
 * Returns kml for given record id. It searches detail with type 221 or 551
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo - only one kml per record, perhaps need to return the combination of kml
 * **/

?>

<?php
header('Content-type: text/xml; charset=utf-8');

require_once(dirname(__FILE__).'/../../common/config/manageInstancesDeprecated.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

define("KML_DETAIL_TYPE", 551);
define("FILE_DETAIL_TYPE", 221);

mysql_connection_select(DATABASE);

$res = mysql_query("select dtl_Value from recDetails where dtl_RecID = " . intval($_REQUEST["id"]) . " and dtl_DetailTypeID = " . KML_DETAIL_TYPE);
if (mysql_num_rows($res)) {
	$kml = mysql_fetch_array($res);
	print $kml[0];
} else {
	$res = mysql_query("select ulf_ID from recDetails left join recUploadedFiles on ulf_ID = dtl_UploadedFileID where dtl_RecID = " . intval($_REQUEST["id"]) . " and dtl_DetailTypeID = " . FILE_DETAIL_TYPE);
	$file_id = mysql_fetch_array($res);
	$file_id = $file_id[0];
	print file_get_contents(UPLOAD_PATH . "/" . $file_id);
}

?>
