<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php

/* load some very basic HEURIST objects into top.HEURIST */

// session_cache_limiter("private");

define("SAVE_URI", "disabled");

// using ob_gzhandler makes this stuff up on IE6-
ini_set("zlib.output_compression_level", 5);
//ob_start('ob_gzhandler');


require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

mysql_connection_db_select(DATABASE);

header("Content-type: text/javascript");


$res = mysql_query("select tlu_DateStamp from sysTableLastUpdated where tlu_TableName = 'defRecTypes'");
$lastModified = mysql_fetch_row($res);
$lastModified = strtotime($lastModified[0]);

if (strtotime(@$_SERVER["HTTP_IF_MODIFIED_SINCE"]) > $lastModified) {
  header('HTTP/1.1 304 Not Modified');
  exit();
}

print "HEURIST_rectypes = {};\n\n";

$names = array();
$res = mysql_query("select rty_ID, rty_Name from defRecTypes order by rty_Name");
while ($row = mysql_fetch_assoc($res)) {
	$names[$row["rty_ID"]] = $row["rty_Name"];
}
print "HEURIST_rectypes.names = " . json_format($names) . ";\n\n";

$groups = array();
$res = mysql_query("select * from defRecTypeGroups where 1 order by rtg_Order, rtg_Name");
while ($row = mysql_fetch_assoc($res)) {
	$groups[$row["rtg_ID"]] = $row["rtg_Name"];
}
print "HEURIST_ref.groupNamesInDisplayOrder = " . json_format($groups) . ";\n\n";

$typesByGroup = array();
$res = mysql_query("select rtg_ID,rty_ID
						from defRecTypes left join defRecTypeGroups on rtg_ID = rty_RecTypeGroupID
						where 1 order by rtg_Order, rtg_Name, rty_OrderInGroup, rty_Name");
while ($row = mysql_fetch_assoc($res)) {
	if (!array_key_exists($row['rtg_ID'],$typesByGroup)){
		$typesByGroup[$row['rtg_ID']] = array();
	}
	array_push($typesByGroup[$row['rtg_ID']], intval($row["rty_ID"]));
}
print "HEURIST_rectypes.typesByGroup = " . json_format($typesByGroup) . ";\n\n";

print "if (window.HEURIST_rectypesOnload) HEURIST_rectypesOnload();\n\n";

ob_end_flush();
?>
