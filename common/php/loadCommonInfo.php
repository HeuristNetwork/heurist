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


require_once(dirname(__FILE__)."/../connect/applyCredentials.php");
require_once("dbMySqlWrappers.php");
require_once("getRecordStructure.php");

mysql_connection_db_select(DATABASE);

header("Content-type: text/javascript");

// for all tables used in common obj find the lastest update date and if it's not great than the last request
// signal requester
$res = mysql_query("select max(tlu_DateStamp) from sysTableLastUpdated where tlu_CommonObj = 1");
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
$res = mysql_query("select rty_ID, rty_Name from defRecTypes where rty_ID order by rty_Name");
while ($row = mysql_fetch_assoc($res)) {
	$names[$row["rty_ID"]] = $row["rty_Name"];
}
print "top.HEURIST.reftypes.names = " . json_format($names) . ";\n\n";

$plurals = array();
$res = mysql_query("select rty_ID, rty_Plural from defRecTypes where rty_ID");
while ($row = mysql_fetch_assoc($res)) {
	$plurals[$row["rty_ID"]] = $row["rty_Plural"];
}
print "top.HEURIST.reftypes.pluralNames = " . json_format($plurals) . ";\n\n";

$primary = array();
$res = mysql_query("select rty_ID from defRecTypes where rty_ID and rty_RecTypeGroupID = 1 order by rty_Name");
while ($row = mysql_fetch_assoc($res)) {
	array_push($primary, intval($row["rty_ID"]));
}
print "top.HEURIST.reftypes.primary = " . json_format($primary) . ";\n\n";
	// saw FIXME TODO change this to create an array by RecTypeGroup.
$other = array();
$res = mysql_query("select rty_ID from defRecTypes where rty_ID and rty_RecTypeGroupID > 1 order by rty_Name");
while ($row = mysql_fetch_assoc($res)) {
	array_push($other, intval($row["rty_ID"]));
}
print "top.HEURIST.reftypes.other = " . json_format($other) . ";\n\n\n";


/* bibDetailRequirements contains colNames valuesByReftypeID,
 * which contains
 */

$colNames = array("rst_DisplayName", "rst_DisplayPrompt", "rst_DefaultValue", "rst_RequirementType", "rst_MaxValues", "rst_MinValues", "rst_DisplayWidth", "rst_RecordMatchOrder");
$rec_types = mysql__select_array("defRecStructure", "distinct rst_RecTypeID", "1 order by rst_RecTypeID");

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
		unset($rdr["rst_RecTypeID"]);
		unset($rdr["rst_DetailTypeID"]);
		unset($rdr["rst_DisplayOrder"]);
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
$colNames = array("dty_ID", "dty_Name", "dty_Type", "dty_Prompt", "dty_Help", "dty_PtrConstraints", "dty_NativeVocabID");
$res = mysql_query("select " . join(", ", $colNames) . " from defDetailTypes order by dty_ID");

$bdt = array();
$first = true;
print "\ntop.HEURIST.bibDetailTypes = {\n";
print "\tcolNames: [ \"" . join("\", \"", $colNames) . "\" ],\n";
print "\tvaluesByBibDetailTypeID: {\n";
while ($row = mysql_fetch_assoc($res)) {
	if (! $first) print ",\n";
	$first = false;
	print "\t\t\"".slash($row["dty_ID"])."\": [ \"";
	print join("\", \"", array_map("slash", $row)) . "\" ]";
}
print "\n\t}\n};\n";


/*
| trm_ID     | smallint(6) | NO   | PRI | NULL    | auto_increment |
| trm_VocabID | smallint(6) | YES  | MUL | NULL    |                |
| trm_Label  | varchar(63) | YES  |     | NULL    |                |
*/
// saw FIXME TODO make sure that VocabID actually exist in Vocab table
$res = mysql_query("select trm_ID,trm_VocabID, trm_Label from defTerms order by trm_VocabID, trm_Label");
print "\ntop.HEURIST.bibDetailLookups = {\n";
$first = true;
$prev_vcb_id = -1;
while ($row = mysql_fetch_assoc($res)) {
	if (!$row["trm_VocabID"]) continue;	// vocab id in not valid so skip it
	if ($prev_vcb_id != $row["trm_VocabID"]) {	/* new vcb_ID */
		if (! $first) {
			print "] ,\n\t\"" . $row["trm_VocabID"] . "\": [";
		}else{
			print "\t\"" . $row["trm_VocabID"] . "\": [";
		$first = false;
		}
	}else {
		print ", ";
	}
	print "[ ".$row["trm_ID"].", \"" . slash($row["trm_Label"]) . "\" ]";
	$prev_vcb_id = $row["trm_VocabID"];
}
print "] \n};\n";


/*
*/
$res = mysql_query("select vcb_ID,vcb_Name from defVocabularies where 1 order by vcb_ID");
print "\ntop.HEURIST.vocabularyLookup = {\n";
$first = true;
while ($row = mysql_fetch_assoc($res)) {
	if (! $first) {
		print " ,\n\t\"" . $row["vcb_ID"] . "\" : \"". $row['vcb_Name'] . "\" ";
	}else{
		print "\t\"" . $row["vcb_ID"] . "\" : \"". $row['vcb_Name'] . "\" ";
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

	$res = mysql_query("select grp.ugr_ID as grpID, grp.ugr_Name as grpName, grp.ugr_Description as description, grp.ugr_URLs as URL, count(ugl_UserID) as members
						  from ".USERS_DATABASE.".sysUGrps grp
					 left join ".USERS_DATABASE.".sysUsrGrpLinks on ugl_GroupID = grp.ugr_ID
					 left join ".USERS_DATABASE.".sysUGrps b on b.ugr_ID = ugl_UserID
						 where grp.ugr_Type != 'User'
						   and b.ugr_Enabled  = 'Y'
					  group by grp.ugr_ID order by grp.ugr_Name");
	while ($row = mysql_fetch_assoc($res)) {
		$workgroups[$row["grpID"]] = array(
			"name" => $row["grpName"],
			"description" => $row["description"],
			"url" => $row["URL"],
			"memberCount" => $row["members"]
		);
		$workgroupIDs[$row["grpName"]] = $row["grpID"];

		$workgroupsLength = max($workgroupsLength, intval($row["grpID"])+1);
	}
	$workgroups["length"] = $workgroupsLength;

	$res = mysql_query("select ugl_GroupID, concat(b.ugr_FirstName,' ',b.ugr_LastName) as name, b.ugr_eMail
						  from ".USERS_DATABASE.".sysUGrps grp
					 left join ".USERS_DATABASE.".sysUsrGrpLinks on ugl_GroupID = grp.ugr_ID
					 left join ".USERS_DATABASE.".sysUGrps b on b.ugr_ID = ugl_UserID
						 where grp.ugr_Type != 'User'
						   and ugl_Role = 'admin'
						   and b.ugr_Enabled  = 'Y'
					  order by ugl_GroupID, b.ugr_LastName, b.ugr_FirstName");
	$grp_id = 0;
	while ($row = mysql_fetch_assoc($res)) {
		if ($grp_id == 0   ||  $grp_id != $row["ugl_GroupID"]) {
			if ($workgroups[$row["ugl_GroupID"]])
				$workgroups[$row["ugl_GroupID"]]["admins"] = array();
		}
		$grp_id = $row["ugl_GroupID"];

		if ($workgroups[$grp_id])
			array_push($workgroups[$grp_id]["admins"], array("name" => $row["name"], "email" => $row["ugr_eMail"]));
	}

	print "top.HEURIST.workgroups = " . json_format($workgroups) . ";\n";
	print "top.HEURIST.workgroupIDs = " . json_format($workgroupIDs) . ";\n";
	print "\n";

?>

top.HEURIST.fireEvent(window, "heurist-obj-common-loaded");

<?php
ob_end_flush();
?>
