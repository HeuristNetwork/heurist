<?php

/*<!--
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/


header("Content-Type: text/javascript");
define('SAVE_URI', 'disabled');

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
require_once(dirname(__FILE__)."/../../common/php/getRecordStructure.php");

/* removed hapi keys  saw 17/1/11
require_once("validateKeyedAccess.php");
if (! @$_REQUEST["key"]) {
	print 'alert("No Heurist API key specified");';
	return;
}

if (! ($loc = get_location($_REQUEST["key"]))) {
	print 'alert("Unknown Heurist API key");';
	return;
}
define_constants($loc["hl_instance"]);
*/

mysql_connection_db_select(DATABASE);


if (defined('HEURIST_USER_GROUP_ID')) {
	$res = mysql_query("select usr.ugr_ID, usr.ugr_Name, concat(usr.ugr_FirstName,' ',usr.ugr_LastName) as Realname from ".USERS_DATABASE.".sysUGrps usr, ".USERS_DATABASE.".sysUsrGrpLinks
	                     where ugl_GroupID=2 and ugl_UserID=usr.ugr_ID and usr.ugr_Enabled='y' and usr.ugr_FirstName is not null and usr.ugr_LastName is not null and !usr.ugr_IsModelUser");
} else {
	$res = mysql_query("select usr.ugr_ID, usr.ugr_Name, concat(usr.ugr_FirstName,' ',usr.ugr_LastName) as Realname from ".USERS_DATABASE.".sysUGrps usr
	                     where usr.ugr_Enabled='y' and usr.ugr_FirstName is not null and usr.ugr_LastName is not null and !usr.ugr_IsModelUser");
}

$users = array();
while ($row = mysql_fetch_row($res)) { array_push($users, $row); }

$res = mysql_query("select distinct grp.ugr_ID, grp.ugr_Name, grp.ugr_LongName, grp.ugr_Description, grp.ugr_URLs
					from ".USERS_DATABASE.".sysUGrps grp, ".USERS_DATABASE.".sysUsrGrpLinks
					where ugl_GroupID=grp.ugr_ID");
$workgroups = array();
while ($row = mysql_fetch_row($res)) { array_push($workgroups, $row); }
$res = mysql_query("select rty_ID, rty_Name, rty_CanonicalTitleMask from defRecTypes ");
$recordTypes = array();
while ($row = mysql_fetch_row($res)) array_push($recordTypes, $row);

$res = mysql_query("select dty_ID, dty_Name, dty_HelpText, dty_Type, NULL as enums, dty_PtrTargetRectypeIDs,
					dty_EnumVocabIDs, dty_EnumTermIDs, dty_ExtendedDescription, dty_DetailTypeGroupID, dty_ShowInPulldowns
					from defDetailTypes");
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
		$row[3] = "enumeration";
		$whereClause = $row[6]?"A.trm_VocabID in(" . $row[6].")" : ""; // get all terms in vocab
		$whereClause .= $row[7] && $whereClause ?" or " : "";
		$whereClause .= $row[7] ? "A.trm_ID in(" . $row[7].")" : "";// get all listed terms

		$lres = mysql_query("select vcb_Name,A.trm_Label, B.trm_Label
								from defTerms A left join defTerms B on A.trm_ID=B.trm_InverseTermID
									left join defVocabularies on A.trm_VocabID = vcb_ID
								where (" . $whereClause.") and A.trm_Label is not null
								order by vcb_Name, A.trm_Label");
		$row[4] = array();

		while ($trm = mysql_fetch_row($lres)){
			if (! array_key_exists($trm[0],$row[4])) { // create vocab array
				$row[4][$trm[0]] = array();
		}
			array_push($row[4][$trm[0]], array("".$trm[1].($trm[2]?",".$trm[2]:"")));
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
}

// detailRequirements is an array of [recordTypeID, detailTypeID, requiremence, repeatable, name, prompt, match, size, order, default] values
$detailRequirements = array();
$rec_types = mysql__select_array("defRecStructure", "distinct rst_RecTypeID", "1 order by rst_RecTypeID");
		// rdr = [ rst_DetailTypeID => [ rst_RecTypeID, rst_DetailTypeID, rst_DisplayName, rst_DisplayHelpText, rst_DisplayExtendedDescription,
		// rst_DefaultValue, rst_RequirementType, rst_MaxValues, rst_MinValues, rst_DisplayWidth, rst_RecordMatchOrder,
		// rst_DisplayOrder, rst_DisplayDetailTypeGroupID, rst_EnumFilteredIDs, rst_PtrFilteredIDs, rst_CalcFunctionID, rst_PriorityForThumbnail] ...]

foreach ($rec_types as $rec_type) {
	foreach (getRecordRequirements($rec_type) as $rdr) {
		array_push($detailRequirements, array(
			$rdr["rst_RecTypeID"],
			$rdr["rst_DetailTypeID"],
			$rdr["rst_RequirementType"],
			intval($rdr["rst_MaxValues"]),
			$rdr["rst_DisplayName"],
			$rdr["rst_DisplayHelpText"],
			intval($rdr["rst_RecordMatchOrder"]),
			intval($rdr["rst_DisplayWidth"]),
			intval($rdr["rst_DisplayOrder"]),
			$rdr["rst_DefaultValue"],
			$rdr["rst_DisplayExtendedDescription"],
			$rdr["rst_MinValues"],
			$rdr["rst_DisplayDetailTypeGroupID"],
			$rdr["rst_EnumFilteredIDs"],
			$rdr["rst_PtrFilteredIDs"],
			$rdr["rst_CalcFunctionID"],
			$rdr["rst_PriorityForThumbnail"]
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
