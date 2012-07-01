<?php

/*<!--
 * getKMLfromRecord.php
 *
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/


require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

mysql_connection_db_select(DATABASE);

if (! @$_REQUEST['recID'] && !defined('DT_KML')) return;

$query = "SELECT dtl_Value FROM recDetails WHERE dtl_DetailTypeID=".DT_KML." and dtl_RecID=".$_REQUEST['recID'];

/*****DEBUG****///error_log(">>>>>>>>>>>".$query);

$res = mysql_query($query);
//"'.addslashes($_REQUEST['recID']).'"');

if (mysql_num_rows($res) != 1) return;

$mres = mysql_query("select * from defFileExtToMimetype where fxm_Extension = 'kml'");
$mimeType = mysql_fetch_assoc($mres);

if (@$mimeType['fxm_MimeType']) {
	header('Content-type: ' .$mimeType['fxm_MimeType']);
}else{
	header('Content-type: binary/download');
}

$row = mysql_fetch_row($res);

/*****DEBUG****///error_log(">>>>>>>>>>>".$row[0]);

print $row[0];
?>
