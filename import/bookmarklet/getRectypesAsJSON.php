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

print "HEURIST_reftypes = {};\n\n";

$names = array();
$res = mysql_query("select rty_ID, rty_Name from defRecTypes order by rty_Name");
while ($row = mysql_fetch_assoc($res)) {
	$names[$row["rty_ID"]] = $row["rty_Name"];
}
print "HEURIST_reftypes.names = " . json_format($names) . ";\n\n";

$groups = array();
$primary = array();
$other = array();
$res = mysql_query("select distinct rty_ID, grp.ugr_Name, rty_RecTypeGroupID
					  from defRecTypes
				 left join ".USERS_DATABASE.".sysUsrGrpLinks on ugl_UserID=".get_user_id()."
				 left join ".USERS_DATABASE.".sysUGrps grp on grp.ugr_ID=ugl_GroupID
				  order by grp.ugr_Name is null, grp.ugr_Name, rty_RecTypeGroupID, rty_Name");
while ($row = mysql_fetch_assoc($res)) {
	if (@$row["ugr_Name"]) {
		if (! @$groups[$row["ugr_Name"]]) $groups[$row["ugr_Name"]] = array();
		array_push($groups[$row["ugr_Name"]], $row["rty_ID"]);
	}
	if ($row["rty_RecTypeGroupID"] == 1) {
		array_push($primary, $row["rty_ID"]);
	} else {
		array_push($other, $row["rty_ID"]);
	}
}
print "HEURIST_reftypes.groups = " . json_format($groups) . ";\n\n";
print "HEURIST_reftypes.primary = " . json_format($primary) . ";\n\n";
print "HEURIST_reftypes.other = " . json_format($other) . ";\n\n";


print "if (window.HEURIST_reftypesOnload) HEURIST_reftypesOnload();\n\n";

ob_end_flush();
?>
