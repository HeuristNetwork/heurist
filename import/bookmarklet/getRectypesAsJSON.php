<?php

/*
* Copyright (C) 2005-2016 University of Sydney
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
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


/* load some very basic HEURIST objects into top.HEURIST */

// session_cache_limiter("private");
define('ISSERVICE',1);
define("SAVE_URI", "disabled");

// using ob_gzhandler makes this stuff up on IE6-
ini_set("zlib.output_compression_level", 5);
//ob_start('ob_gzhandler');


require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
require_once(dirname(__FILE__)."/../../common/php/getRecordInfoLibrary.php");

mysql_connection_select(DATABASE);

header("Content-type: text/javascript");

$res = mysql_query("select tlu_DateStamp from sysTableLastUpdated where tlu_TableName = 'defRecTypes'");
$lastModified = mysql_fetch_row($res);
$lastModified = strtotime($lastModified[0]);

if (strtotime(@$_SERVER["HTTP_IF_MODIFIED_SINCE"]) > $lastModified) {
  header('HTTP/1.1 304 Not Modified');
  exit();
}

ob_start();

print "HEURIST_rectypes = {};\n\n";

$names = array();
$res = mysql_query("select rty_ID, rty_Name from defRecTypes order by rty_Name");
while ($row = mysql_fetch_assoc($res)) {
	$names[$row["rty_ID"]] = $row["rty_Name"];
}
print "top.HEURIST_rectypes.names = " . json_format($names) . ";\n\n";

$plurals = array();
$res = mysql_query("select rty_ID, rty_Plural from defRecTypes where rty_ID");
while ($row = mysql_fetch_assoc($res)) {
	$plurals[$row["rty_ID"]] = $row["rty_Plural"];
}
print "top.HEURIST_rectypes.pluralNames = " . json_format($plurals) . ";\n\n";

//print "top.HEURIST_rectypes.groupNamesInDisplayOrder = " . json_format(getRectypeGroups()) . ";\n\n";
print "top.HEURIST_rectypes.groups = " . json_format(getRecTypesByGroup()) . ";\n\n";

print "if (window.HEURIST_rectypesOnload) HEURIST_rectypesOnload();\n\n";

ob_end_flush();
?>
