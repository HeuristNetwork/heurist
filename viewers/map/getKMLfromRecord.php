<?php

/*
* Copyright (C) 2005-2013 University of Sydney
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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

?>

<?php

// ARTEM: TO REMOVE

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

mysql_connection_select(DATABASE);

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
