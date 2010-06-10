<?php

header("Content-Type: text/javascript");
define('SAVE_URI', 'disabled');

require_once(dirname(__FILE__)."/../../common/connect/cred.php");
require_once(dirname(__FILE__)."/../../common/connect/db.php");
require_once("auth.php");
require_once(dirname(__FILE__)."/../../common/lib/requirements-overrides.php");

if (! @$_REQUEST["key"]) {
	print 'alert("No Heurist API key specified");';
	return;
}

if (! ($loc = get_location($_REQUEST["key"]))) {
	print 'alert("Unknown Heurist API key");';
	return;
}
define_constants($loc["hl_instance"]);

mysql_connection_db_select(DATABASE);


if (defined('HEURIST_USER_GROUP_ID')) {
	$res = mysql_query("select Id, Username, Realname from ".USERS_DATABASE.".Users, ".USERS_DATABASE.".UserGroups
	                     where ug_group_id=2 and ug_user_id=Id and Active='Y' and firstname is not null and lastname is not null and !IsModelUser");
} else {
	$res = mysql_query("select Id, Username, Realname from ".USERS_DATABASE.".Users
	                     where Active='Y' and firstname is not null and lastname is not null and !IsModelUser");
}

$users = array();
while ($row = mysql_fetch_row($res)) { array_push($users, $row); }

$res = mysql_query("select distinct grp_id, grp_name, grp_longname, grp_description, grp_url from ".USERS_DATABASE.".Groups, ".USERS_DATABASE.".UserGroups where ug_group_id=grp_id");
$workgroups = array();
while ($row = mysql_fetch_row($res)) { array_push($workgroups, $row); }

$res = mysql_query("select rc_id as id, rc_label as CONTENT, ri_label as INTEREST, rq_label as QUALITY
                      from ratings_content, ratings_interest, ratings_quality where rc_id = ri_id and ri_id = rq_id");
$ratings = array("CONTENT" => array(), "INTEREST" => array(), "QUALITY" => array());
while ($row = mysql_fetch_assoc($res)) {
	$id = $row["id"];
	$ratings["CONTENT"][$id] = $row["CONTENT"];
	$ratings["INTEREST"][$id] = $row["INTEREST"];
	$ratings["QUALITY"][$id] = $row["QUALITY"];
}

$res = mysql_query("select rt_id, rt_name, rt_canonical_title_mask from active_rec_types inner join rec_types on rt_id=art_id");
$recordTypes = array();
while ($row = mysql_fetch_row($res)) array_push($recordTypes, $row);

$res = mysql_query("select rdt_id, rdt_name, rdt_prompt, rdt_type, NULL as enums, rdt_constrain_rec_type from rec_detail_types");
$detailTypes = array();
$detailTypesById = array();
while ($row = mysql_fetch_row($res)) {
	switch ($row[3]) {	// determine variety from rdt_type
		// these ones thoughtfully have the same name for their variety as they do for their rdt_type
	    case "date":
	    case "file":
		break;

	    case "resource":
		$row[3] = "reference";
		break;

	    case "enum":
		$row[3] = "enumeration";
		$lres = mysql_query("select A.rdl_value, B.rdl_value from active_rec_detail_lookups left join rec_detail_lookups A on ardl_id=A.rdl_id left join rec_detail_lookups B on A.rdl_id=B.rdl_related_rdl_id where A.rdl_rdt_id=" . intval($row[0])." and A.rdl_value is not null");
		$row[4] = array();

		$rdl = mysql_fetch_row($lres);
		if ($rdl[1]) {	// two columns: the related value is present for this lookup type
			array_push($row[4], $rdl);
			while ($rdl = mysql_fetch_row($lres)) array_push($row[4], $rdl);
		}
		else if ($rdl) {		// no value in the second column, omit it
			array_push($row[4], $rdl[0]);
			while ($rdl = mysql_fetch_row($lres)) array_push($row[4], $rdl[0]);
		}
		break;

	    case "geo":
		$row[3] = "geographic";
		break;

		case "boolean":
		$row[3] = "boolean";
		break;

		case "blocktext":
        $row[3] = "blocktext";
		break;

	    default:
		$row[3] = "literal";
	}
	array_push($detailTypes, $row);
	$detailTypesById[$row[0]] = $row;
}

// detailRequirements is an array of [recordTypeID, detailTypeID, requiremence, repeatable, name, prompt, match, size, order, default] values
$detailRequirements = array();
$rec_types = mysql__select_array("rec_detail_requirements", "distinct rdr_rec_type", "1 order by rdr_rec_type");
foreach ($rec_types as $rec_type) {
	foreach (getRecordRequirements($rec_type) as $rdr) {
		array_push($detailRequirements, array(
			$rdr["rdr_rec_type"],
			$rdr["rdr_rdt_id"],
			$rdr["rdr_required"],
			intval($rdr["rdr_repeatable"]),
			($rdr["rdr_name"] != $detailTypesById[$rdr["rdr_rdt_id"]][1] ? $rdr["rdr_name"] : null),
			($rdr["rdr_prompt"] != $detailTypesById[$rdr["rdr_rdt_id"]][2] ? $rdr["rdr_prompt"]: null),
			intval($rdr["rdr_match"]),
			intval($rdr["rdr_size"]),
			intval($rdr["rdr_order"]),
			$rdr["rdr_default"]
		));
	}
}


$commonData = array(
	"users" => $users,
	"workgroups" => $workgroups,
	"ratings" => $ratings,
	"recordTypes" => $recordTypes,
	"detailTypes" => $detailTypes,
	"detailRequirements" =>$detailRequirements
);

if (! @$_REQUEST["json"]) {
	print "var HAPI_commonData = ";
}
print json_encode($commonData);
if (! @$_REQUEST["json"]) {
	print ";\n";
}

?>
