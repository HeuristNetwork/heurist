<?php

header("Content-Type: text/javascript");
define('SAVE_URI', 'disabled');

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
require_once("validateKeyedAccess.php");
require_once(dirname(__FILE__)."/../../common/php/getRecordStructure.php");

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
	$res = mysql_query("select usr.ugr_ID, usr.ugr_Name, concat(usr.ugr_FirstName,' ',usr.ugr_LastName) as Realname from ".USERS_DATABASE.".sysUGrps usr, ".USERS_DATABASE.".sysUsrGrpLinks
	                     where ugl_GroupID=2 and ugl_UserID=usr.ugr_ID and usr.ugr_Enabled='Y' and usr.ugr_FirstName is not null and usr.ugr_LastName is not null and !usr.ugr_IsModelUser");
} else {
	$res = mysql_query("select usr.ugr_ID, usr.ugr_Name, concat(usr.ugr_FirstName,' ',usr.ugr_LastName) as Realname from ".USERS_DATABASE.".sysUGrps usr
	                     where usr.ugr_Enabled='Y' and usr.ugr_FirstName is not null and usr.ugr_LastName is not null and !usr.ugr_IsModelUser");
}

$users = array();
while ($row = mysql_fetch_row($res)) { array_push($users, $row); }

$res = mysql_query("select distinct grp.ugr_ID, grp.ugr_Name, grp.ugr_LongName, grp.ugr_Description, grp.ugr_URLs
					from ".USERS_DATABASE.".sysUGrps grp, ".USERS_DATABASE.".sysUsrGrpLinks
					where ugl_GroupID=grp.ugr_ID");
$workgroups = array();
while ($row = mysql_fetch_row($res)) { array_push($workgroups, $row); }
$res = mysql_query("select rty_ID, rty_Name, rty_CanonicalTitleMask from active_rec_types inner join defRecTypes on rty_ID=art_id");
$recordTypes = array();
while ($row = mysql_fetch_row($res)) array_push($recordTypes, $row);

$res = mysql_query("select dty_ID, dty_Name, dty_Prompt, dty_Type, NULL as enums, dty_PtrConstraints, dty_NativeVocabID from defDetailTypes");
$detailTypes = array();
$detailTypesById = array();
while ($row = mysql_fetch_row($res)) {
	switch ($row[3]) {	// determine variety from dty_Type
		// these ones thoughtfully have the same name for their variety as they do for their dty_Type
	    case "date":
	    case "file":
		break;

	    case "resource":
		$row[3] = "reference";
		break;

	    case "enum":
		$row[3] = "enumeration";	// saw FIXME TODO  need to change this to account for overrides
		$lres = mysql_query("select A.trm_Label, B.trm_Label from defTerms A left join defTerms B on A.trm_ID=B.trm_InverseTermID where A.trm_VocabID=" . intval($row[6])." and A.trm_Label is not null");
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
$rec_types = mysql__select_array("defRecStructure", "distinct rst_RecTypeID", "1 order by rst_RecTypeID");
foreach ($rec_types as $rec_type) {
	foreach (getRecordRequirements($rec_type) as $rdr) {
		array_push($detailRequirements, array(
			$rdr["rst_RecTypeID"],
			$rdr["rst_DetailTypeID"],
			$rdr["rdr_required"],
			intval($rdr["rst_Repeats"]),
			($rdr["rst_NameInForm"] != $detailTypesById[$rdr["rst_DetailTypeID"]][1] ? $rdr["rst_NameInForm"] : null),
			($rdr["rst_Prompt"] != $detailTypesById[$rdr["rst_DetailTypeID"]][2] ? $rdr["rst_Prompt"]: null),
			intval($rdr["rst_RecordMatchOrder"]),
			intval($rdr["rst_DisplayWidth"]),
			intval($rdr["rst_OrderInForm"]),
			$rdr["rst_DefaultValue"]
		));
	}
}


$commonData = array(
	"users" => $users,
	"workgroups" => $workgroups,
	"ratings" => array("0"=>"not rated",
						"1"=>"*",
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
