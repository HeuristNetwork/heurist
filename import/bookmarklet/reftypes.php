<?php

/* load some very basic HEURIST objects into top.HEURIST */

// session_cache_limiter("private");

define("SAVE_URI", "disabled");

// using ob_gzhandler makes this stuff up on IE6-
ini_set("zlib.output_compression_level", 5);
//ob_start('ob_gzhandler');


require_once(dirname(__FILE__)."/../../common/connect/cred.php");
require_once(dirname(__FILE__)."/../../common/connect/db.php");

mysql_connection_db_select(DATABASE);

header("Content-type: text/javascript");


$res = mysql_query("select datestamp from last_update where table_name = 'rec_types'");
$lastModified = mysql_fetch_row($res);
$lastModified = strtotime($lastModified[0]);

if (strtotime(@$_SERVER["HTTP_IF_MODIFIED_SINCE"]) > $lastModified) {
  header('HTTP/1.1 304 Not Modified');
  exit();
}

print "HEURIST_reftypes = {};\n\n";

$names = array();
$res = mysql_query("select rt_id, rt_name from active_rec_types left join rec_types on rt_id=art_id order by rt_name");
while ($row = mysql_fetch_assoc($res)) {
	$names[$row["rt_id"]] = $row["rt_name"];
}
print "HEURIST_reftypes.names = " . json_format($names) . ";\n\n";

$groups = array();
$primary = array();
$other = array();
$res = mysql_query("select distinct rt_id, grp_name, rt_primary
					  from active_rec_types
				 left join rec_types on rt_id=art_id
				 left join ".USERS_DATABASE.".UserGroups on ug_user_id=".get_user_id()."
				 left join rec_detail_requirements_overrides on rdr_rec_type=rt_id
				 left join ".USERS_DATABASE.".Groups on grp_id=ug_group_id and grp_id=rdr_wg_id
				  order by grp_name is null, grp_name, ! rt_primary, rt_name");
while ($row = mysql_fetch_assoc($res)) {
	if (@$row["grp_name"]) {
		if (! @$groups[$row["grp_name"]]) $groups[$row["grp_name"]] = array();
		array_push($groups[$row["grp_name"]], $row["rt_id"]);
	}
	if ($row["rt_primary"]) {
		array_push($primary, $row["rt_id"]);
	} else {
		array_push($other, $row["rt_id"]);
	}
}
print "HEURIST_reftypes.groups = " . json_format($groups) . ";\n\n";
print "HEURIST_reftypes.primary = " . json_format($primary) . ";\n\n";
print "HEURIST_reftypes.other = " . json_format($other) . ";\n\n";


print "if (window.HEURIST_reftypesOnload) HEURIST_reftypesOnload();\n\n";

ob_end_flush();
?>
