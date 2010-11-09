<?php

/* load some very basic HEURIST objects into top.HEURIST */

// session_cache_limiter("private");

define("SAVE_URI", "disabled");

// using ob_gzhandler makes this stuff up on IE6-
ini_set("zlib.output_compression_level", 5);
//ob_start('ob_gzhandler');


require_once(dirname(__FILE__)."/../connect/cred.php");
require_once(dirname(__FILE__)."/../connect/db.php");
require_once("requirements-overrides.php");

mysql_connection_db_select(DATABASE);

header("Content-type: text/javascript");


$res = mysql_query("select max(datestamp) from last_update where common = 1");
$lastModified = mysql_fetch_row($res);
$lastModified = strtotime($lastModified[0]);

// not changed since last requested so return
if (strtotime(@$_SERVER["HTTP_IF_MODIFIED_SINCE"]) > $lastModified) {
  header('HTTP/1.1 304 Not Modified');
  exit();
}


// This is the best place I can think of to stick this stuff --kj, 2008-07-21
print "top.HEURIST.instance = {};\n";
print "top.HEURIST.instance.name = " . json_format(HEURIST_INSTANCE) . ";\n";
print "top.HEURIST.instance.prefix = " . json_format(HEURIST_INSTANCE_PREFIX) . ";\n";
print "top.HEURIST.instance.exploreURL = " . json_format(EXPLORE_URL) . ";\n";
print "if (!top.HEURIST.basePath) top.HEURIST.basePath = ".json_format(HEURIST_SITE_PATH) . ";\n";
print "if (!top.HEURIST.baseURL) top.HEURIST.baseURL = ".json_format(HEURIST_URL_BASE) . ";\n";

/* Reftypes are an array of names sorted alphabetically, and lists of
   primary (bibliographic) and other reftypes, also sorted alphbetically */
print "top.HEURIST.reftypes = {};\n";

$names = array();
$res = mysql_query("select rt_id, rt_name from active_rec_types left join rec_types on rt_id=art_id where rt_id order by rt_name");
while ($row = mysql_fetch_assoc($res)) {
	$names[$row["rt_id"]] = $row["rt_name"];
}
print "top.HEURIST.reftypes.names = " . json_format($names) . ";\n\n";

$plurals = array();
$res = mysql_query("select rt_id, rt_plural from active_rec_types left join rec_types on rt_id=art_id where rt_id");
while ($row = mysql_fetch_assoc($res)) {
	$plurals[$row["rt_id"]] = $row["rt_plural"];
}
print "top.HEURIST.reftypes.pluralNames = " . json_format($plurals) . ";\n\n";

$primary = array();
$res = mysql_query("select rt_id from active_rec_types left join rec_types on rt_id=art_id where rt_id and rt_primary order by rt_name");
while ($row = mysql_fetch_assoc($res)) {
	array_push($primary, intval($row["rt_id"]));
}
print "top.HEURIST.reftypes.primary = " . json_format($primary) . ";\n\n";

$other = array();
$res = mysql_query("select rt_id from active_rec_types left join rec_types on rt_id=art_id where rt_id and ! rt_primary order by rt_name");
while ($row = mysql_fetch_assoc($res)) {
	array_push($other, intval($row["rt_id"]));
}
print "top.HEURIST.reftypes.other = " . json_format($other) . ";\n\n\n";


/* bibDetailRequirements contains colNames valuesByReftypeID,
 * which contains
 */

$colNames = array("rdr_name", "rdr_prompt", "rdr_default", "rdr_required", "rdr_repeatable", "rdr_size", "rdr_match");
$rec_types = mysql__select_array("rec_detail_requirements", "distinct rdr_rec_type", "1 order by rdr_rec_type");

print "\ntop.HEURIST.bibDetailRequirements = {\n";
print "\tcolNames: [ \"" . join("\", \"", $colNames) . "\" ],\n";
print "\tvaluesByReftypeID: {\n";

$first = true;
foreach ($rec_types as $rec_type) {
	if (! $first) print "\n\t\t},\n";
	$first = false;
	print "\t\t\"".slash($rec_type)."\": {\n";

	$first_rdr = true;
	foreach (getRecordRequirements($rec_type) as $rdr_rdt_id => $rdr) {
		if (! $first_rdr) print ",\n";
		$first_rdr = false;
		unset($rdr["rdr_rec_type"]);
		unset($rdr["rdr_rdt_id"]);
		unset($rdr["rdr_order"]);
		print "\t\t\t\"" . $rdr_rdt_id . "\": [ \"" . join("\", \"", array_map("slash", $rdr)) . "\" ]";
	}
}
print "\n\t\t}\n\t},\n";

print "\torderByReftypeID: {\n";

$first = true;
foreach ($rec_types as $rec_type) {
	if (! $first) print ",\n";
	$first = false;
	print "\t\t\"".slash($rec_type)."\": [";

	$first_rdr = true;
	foreach (getRecordRequirements($rec_type) as $rdr_rdt_id => $rdr) {
		if (! $first_rdr) print ",";
		$first_rdr = false;
		print $rdr_rdt_id;
	}
	print "]";
}
print "\n\t}\n};\n";



/* bibDetailTypes */

$colNames = array("rdt_id", "rdt_name", "rdt_type", "rdt_constrain_rec_type");
$res = mysql_query("select " . join(", ", $colNames) . " from rec_detail_types order by rdt_id");

$bdt = array();
$first = true;
print "\ntop.HEURIST.bibDetailTypes = {\n";
print "\tcolNames: [ \"" . join("\", \"", $colNames) . "\" ],\n";
print "\tvaluesByBibDetailTypeID: {\n";
while ($row = mysql_fetch_assoc($res)) {
	if (! $first) print ",\n";
	$first = false;
	print "\t\t\"".slash($row["rdt_id"])."\": [ \"";
	print join("\", \"", array_map("slash", $row)) . "\" ]";
}
print "\n\t}\n};\n";


/*
| rdl_id     | smallint(6) | NO   | PRI | NULL    | auto_increment |
| rdl_rdt_id | smallint(6) | YES  | MUL | NULL    |                |
| rdl_value  | varchar(63) | YES  |     | NULL    |                |
| rdl_ont_id | smallint(6) | YES  | MUL | NULL    |                |
*/
$res = mysql_query("select rdl_id,rdl_rdt_id, rdl_value, rdl_ont_id from active_rec_detail_lookups left join rec_detail_lookups on rdl_id = ardl_id order by rdl_rdt_id,rdl_ont_id, rdl_value");
print "\ntop.HEURIST.bibDetailLookups = {\n";
$first = true;
$prev_rdt_id = -1;
while ($row = mysql_fetch_assoc($res)) {
	if (!$row["rdl_rdt_id"]) continue;	// detail type id in not valid so skip it
	if ($prev_rdt_id != $row["rdl_rdt_id"]) {	/* new rdt_id */
		$prev_ont_id = -1;
		if (! $first) {
			print " ]},\n\t\"" . $row["rdl_rdt_id"] . "\": {\n\t\t\"". $row['rdl_ont_id'] . "\": [ ";
		}else{
			print "\t\"" . $row["rdl_rdt_id"] . "\": {\n\t\t\"". $row['rdl_ont_id'] . "\": [ ";
		}
		$first = false;
	} else {
		if ( $prev_ont_id == $row['rdl_ont_id']) { // another id value pair for the same ontology
			print ", ";
		}else{	// a new ontology set for this detail type
			print " ],\n\t\t\"". $row['rdl_ont_id'] . "\": [ ";
		}
	}
	print "[ ".$row["rdl_id"].", \"" . slash($row["rdl_value"]) . "\" ]";
	$prev_rdt_id = $row["rdl_rdt_id"];
	$prev_ont_id = $row["rdl_ont_id"];
}
print " ]\n\t}\n};\n";


/*
| rdl_id     | smallint(6) | NO   | PRI | NULL    | auto_increment |
| rdl_rdt_id | smallint(6) | YES  | MUL | NULL    |                |
| rdl_value  | varchar(63) | YES  |     | NULL    |                |
| rdl_ont_id | smallint(6) | YES  | MUL | NULL    |                |
*/
$res = mysql_query("select ont_id,ont_name from ontologies where 1 order by ont_id");
print "\ntop.HEURIST.ontologyLookup = {\n";
$first = true;
while ($row = mysql_fetch_assoc($res)) {
	if (! $first) {
		print " ,\n\t\"" . $row["ont_id"] . "\" : \"". $row['ont_name'] . "\" ";
	}else{
		print "\t\"" . $row["ont_id"] . "\" : \"". $row['ont_name'] . "\" ";
	}
	$first = false;
}
print "\n};\n";?>

top.HEURIST.ratings = {"0": "not rated",
						"1": "*",
						"2": "**",
						"3": "***",
						"4": "****",
						"5": "*****"
};

<?php
	$workgroups = array();
	$workgroupIDs = array();
	$workgroupsLength = 0;

	$res = mysql_query("select grp_id, grp_name, grp_description, grp_url, count(ug_user_id) as members
						  from ".USERS_DATABASE.".Groups
					 left join ".USERS_DATABASE.".UserGroups on ug_group_id = grp_id
					 left join ".USERS_DATABASE.".Users on Id = ug_user_id
						 where grp_type != 'Usergroup'
						   and (ug_user_id is null  or  Active = 'Y')
					  group by grp_id order by grp_name");
	while ($row = mysql_fetch_assoc($res)) {
		$workgroups[$row["grp_id"]] = array(
			"name" => $row["grp_name"],
			"description" => $row["grp_description"],
			"url" => $row["grp_url"],
			"memberCount" => $row["members"]
		);
		$workgroupIDs[$row["grp_name"]] = $row["grp_id"];

		$workgroupsLength = max($workgroupsLength, intval($row["grp_id"])+1);
	}
	$workgroups["length"] = $workgroupsLength;

	$res = mysql_query("select ug_group_id, concat(firstname,' ',lastname) as name, EMail
						  from ".USERS_DATABASE.".Groups
					 left join ".USERS_DATABASE.".UserGroups on ug_group_id = grp_id
					 left join ".USERS_DATABASE.".Users on Id = ug_user_id
						 where grp_type != 'Usergroup'
						   and ug_role = 'admin'
						   and Active = 'Y'
					  order by ug_group_id, lastname, firstname");
	$grp_id = 0;
	while ($row = mysql_fetch_assoc($res)) {
		if ($grp_id == 0   ||  $grp_id != $row["ug_group_id"]) {
			if ($workgroups[$row["ug_group_id"]])
				$workgroups[$row["ug_group_id"]]["admins"] = array();
		}
		$grp_id = $row["ug_group_id"];

		if ($workgroups[$grp_id])
			array_push($workgroups[$grp_id]["admins"], array("name" => $row["name"], "email" => $row["EMail"]));
	}

	print "top.HEURIST.workgroups = " . json_format($workgroups) . ";\n";
	print "top.HEURIST.workgroupIDs = " . json_format($workgroupIDs) . ";\n";
	print "\n";

?>

top.HEURIST.fireEvent(window, "heurist-obj-common-loaded");

<?php
ob_end_flush();
?>
