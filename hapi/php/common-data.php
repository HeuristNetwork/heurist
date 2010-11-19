<?php

header("Content-Type: text/javascript");
define('SAVE_URI', 'disabled');

require_once(dirname(__FILE__)."/../../common/connect/cred.php");
require_once(dirname(__FILE__)."/../../common/connect/db.php");
require_once("auth.php");
require_once(dirname(__FILE__)."/../../common/php/requirements-overrides.php");

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
	$res = mysql_query("select usr.Id, ugr_Name, concat(usr.ugr_FirstName,' ',usr.ugr_LastName) as Realname from ".USERS_DATABASE.".sysUGrps usr, ".USERS_DATABASE.".sysUsrGrpLinks
	                     where ugl_GroupID=2 and ugl_UserID=Id and usr.ugr_Enabled='Y' and usr.ugr_FirstName is not null and usr.ugr_LastName is not null and !usr.ugr_IsModelUser");
} else {
	$res = mysql_query("select usr.Id, ugr_Name, concat(usr.ugr_FirstName,' ',usr.ugr_LastName) as Realname from ".USERS_DATABASE.".sysUGrps usr
	                     where usr.ugr_Enabled='Y' and usr.ugr_FirstName is not null and usr.ugr_LastName is not null and !usr.ugr_IsModelUser");
}

$users = array();
while ($row = mysql_fetch_row($res)) { array_push($users, $row); }

$res = mysql_query("select distinct grp.ugr_ID, grp.ugr_Name, grp.ugr_LongName, grp.ugr_Description, grp.ugr_URLs
					from ".USERS_DATABASE.".sysUGrps grp, ".USERS_DATABASE.".sysUsrGrpLinks
					where ugl_GroupID=grp.ugr_ID");
$workgroups = array();
while ($row = mysql_fetch_row($res)) { array_push($workgroups, $row); }
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
	"ratings" => array("0"=>"not rated",
						"1"=> "*",
						"2"=>"**",
						"3"=>"***",
						"4"=>"****",
						"5"=>"*****"),
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
