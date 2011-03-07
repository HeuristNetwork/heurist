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
