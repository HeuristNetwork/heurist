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
require_once(dirname(__FILE__)."/../../common/php/getRecordInfoLibrary.php");

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
	$res = mysql_query("select usr.ugr_ID, usr.ugr_Name, concat(if(usr.ugr_FirstName is null,'Fred',usr.ugr_FirstName),' ',if(usr.ugr_LastName is null,'Nerks',usr.ugr_LastName)) as Realname from ".USERS_DATABASE.".sysUGrps usr, ".USERS_DATABASE.".sysUsrGrpLinks
	                     where ugl_GroupID=2 and ugl_UserID=usr.ugr_ID and usr.ugr_Enabled='y' and !usr.ugr_IsModelUser");
//usr.ugr_FirstName is not null and usr.ugr_LastName is not null and
} else {
	$res = mysql_query("select usr.ugr_ID, usr.ugr_Name, concat(if(usr.ugr_FirstName is null,'Fred',usr.ugr_FirstName),' ',if(usr.ugr_LastName is null,'Nerks',usr.ugr_LastName)) as Realname from ".USERS_DATABASE.".sysUGrps usr
	                     where usr.ugr_Enabled='y' and !usr.ugr_IsModelUser");
//usr.ugr_FirstName is not null and usr.ugr_LastName is not null and
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
					dty_JsonTermIDTree, dty_TermIDTreeNonSelectableIDs, dty_ExtendedDescription, dty_DetailTypeGroupID,
					dty_FieldSetRecTypeID, dty_ShowInLists, dty_NonOwnerVisibility
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
		//calculate the list of terms for this detail type
		if (!$row[6]){
			$row[4] = array( array("0","No Terms for $row[1]"));
		}else{
			preg_match_all("/\d+/",$row[6],$matches);
			$trmIDs = $matches[0];
			if ($row[7]){
				preg_match_all("/\d+/",$row[7],$hdrTermIDs);
				if ($hdrTermIDs[0] && is_array($hdrTermIDs[0])) {
					$trmIDS = array_diff($trmIDs,$hdrTermIDs[0]);	//remove disabled Terms
				}
			}
			$trmIDs = join(",",$trmIDs);
			if ($trmIDs) {
				$resTerm = mysql_query("select trm_ID,trm_Label from defTerms ".
										"where trm_ID in ($trmIDs) and trm_Domain = 'enum'");
				if (mysql_num_rows($resTerm) != count($matches[0])){
					$row[4] = array( array("0","Invalid Terms for $row[1]"));
				}else{
		$row[4] = array();
					while($trmSet = mysql_fetch_row($resTerm)) {
						array_push($row[4],$trmSet);
					}
				}
			}
		}
		break;

		case "relationtype":
		//calculate the list of terms for this detail type
		if (!$row[6]){
			$row[4] = array( array("0","No Terms for $row[1]"));
		}else{
			preg_match_all("/\d+/",$row[6],$matches);
			$trmIDs = $matches[0];
			if ($row[7]){
				preg_match_all("/\d+/",$row[7],$hdrTermIDs);
				if ($hdrTermIDs[0] && is_array($hdrTermIDs[0])) {
					$trmIDS = array_diff($trmIDs,$hdrTermIDs[0]);	//remove disabled Terms
				}
			}
			$trmIDs = join(",",$trmIDs);
			if ($trmIDs) {
				$resTerm = mysql_query("select trm.trm_ID,trm.trm_Label,inv.trm_ID as invID, inv.trm_Label as invLabel ".
										"from defTerms trm left join defTerms inv on trm.trm_InverseTermID = inv.trm_ID ".
										"where trm.trm_ID in ($trmIDs) and trm.trm_Domain = 'relation' ");
				if (mysql_num_rows($resTerm) != count($matches[0])){
					$row[4] = array( array("0","Invalid Terms for $row[1]"));
				}else{
					$row[4] = array();
					while($trmSet = mysql_fetch_row($resTerm)) {
						array_push($row[4],$trmSet);
					}
				}
		}
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
		case "urlinclude":
        $row[3] = "urlinclude";
		break;
	    default:
		$row[3] = "literal";
	}
	array_push($detailTypes, $row);
}

// detailRequirements is an array of [recordTypeID, detailTypeID, requiremence, repeatable, name, prompt, match, size, order, default] values
$detailRequirements = array();
$rec_types = mysql__select_array("defRecTypes","distinct rty_ID", "1 order by rty_ID");
//$rec_types = mysql__select_array("defRecStructure left join defDetailType on dty_ID = rst_DetailTypeID",
//									"distinct rst_RecTypeID", "1 order by rst_RecTypeID");
		// rdr = [ rst_DetailTypeID => [
			// 0-rst_DisplayName
			// 1-rst_DisplayHelpText
			// 2-rst_DisplayExtendedDescription
			// 3-rst_DefaultValue
			// 4-rst_RequirementType
			// 5-rst_MaxValues
			// 6-rst_MinValues
			// 7-rst_DisplayWidth
			// 8-rst_RecordMatchOrder
			// 9-rst_DisplayOrder
			//10-rst_DisplayDetailTypeGroupID
			//11-rst_FilteredJsonTermIDTree
			//12-rst_PtrFilteredIDs
			//13-rst_TermIDTreeNonSelectableIDs
			//14-rst_CalcFunctionID
			//15-rst_Status
			//16-rst_OrderForThumbnailGeneration
			//17-dty_TermIDTreeNonSelectableIDs
			//18-dty_FieldSetRectypeID
			//19-rst_NonOwnerVisibility]....]


		//rst_DisplayName, rst_DisplayHelpText, rst_DisplayExtendedDescription,
		// rst_DefaultValue, rst_RequirementType, rst_MaxValues, rst_MinValues, rst_DisplayWidth, rst_RecordMatchOrder,
		// rst_DisplayOrder, rst_DisplayDetailTypeGroupID, rst_FilteredJsonTermIDTree, rst_PtrFilteredIDs,
		// rst_TermIDTreeNonSelectableIDs, rst_CalcFunctionID, rst_Status, rst_OrderForThumbnailGeneration,
		// dty_TermIDTreeNonSelectableIDs, dty_FieldSetRectypeID, rst_NonOwnerVisibility] ...]
//error_log(print_r($rec_types,true));
foreach ($rec_types as $rec_type) {
	foreach (getRectypeFields($rec_type) as $dtyID => $rdr) {
		// saw TODO need to represent the trm ids  and rectype pointer ids that are valid for this rectype.detailtype.
		array_push($detailRequirements, array(
			$rec_type,			// 0-recTypeID
			$dtyID,				// 1-detailTypeID
			$rdr[4],			// 2-RequirementType
			intval($rdr[5]),	// 3-MaxValue
			$rdr[0],			// 4-Name
			$rdr[1],			// 5-HelpText
			intval($rdr[8]),	// 6-Match Order
			intval($rdr[7]),	// 7-DisplayWidth
			intval($rdr[9]),	// 8-Display Order
			$rdr[2],			// 9-Extended Description
			$rdr[3],			//10-Default Value
			intval($rdr[6]),	//11-MinValue
			$rdr[10],			//12-DetailGroupID
			$rdr[11],			//13-Filtered Enum Term IDs
			$rdr[13],			//14-Extended Disabled Term IDs
			$rdr[17],			//15-Detail Type Disabled Term IDs
			$rdr[12],			//16-Filtered Pointer Constraint Rectype IDs
			$rdr[14],			//17-Calc Function ID
			$rdr[16],			//18-Thumbnail selection Order
			$rdr[15],			//19-Status
			$rdr[19]));			//20-Non-Owner Visibility
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
