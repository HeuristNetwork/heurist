<?php

/*<!--
 * saveStructure.php   TODO
 * This file accepts request to update the system structural
 * definitions rectypes, detailtypes, terms and constraints.
 * it returns the entire structure for tthe affected area inorder
 * to update top.HEURIST
 * create by Stephen A. White on 17/03/2011
 * @copyright (C) 2005-2011 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 -->*/


require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');


if (! is_logged_in()) {
	//ARTEM header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
	//ARTEM return;
}

if (! is_admin()) {
	//ARTEM print "<html><body><p>You do not have sufficient privileges to access this page</p><p><a href=".HEURIST_URL_BASE.">Return to Heurist</a></p></body></html>";
	//ARTEM return;
}

header('Content-type: text/javascript');

$legalMethods = array(
	"saveRectype",
	"saveRT",
	"saveRTG",
	"saveDetailType",
	"saveDT",
	"saveDTG",
	"deleteRT",
	"deleteRTG",
	"deleteDTG"
);

$rtyColumnNames = array(
"rty_ID",
"rty_Name",
"rty_OrderInGroup",
"rty_Description",
"rty_TitleMask",
"rty_CanonicalTitleMask",
"rty_Plural",
"rty_Status",
"rty_OriginatingDBID",
"rty_NameInOriginatingDB",
"rty_IDInOriginatingDB",
"rty_ShowInLists",
"rty_RecTypeGroupIDs",
"rty_FlagAsFieldset",
"rty_ReferenceURL",
"rty_AlternativeRecEditor",
"rty_Type"
);

$rstColumnNames = array(
"rst_ID",
"rst_RecTypeID",
"rst_DetailTypeID",
"rst_DisplayName",
"rst_DisplayHelpText",
"rst_DisplayExtendedDescription",
"rst_DisplayOrder",
"rst_DisplayWidth",
"rst_DefaultValue",
"rst_RecordMatchOrder",
"rst_CalcFunctionID",
"rst_RequirementType",
"rst_Status",
"rst_OriginatingDBID",
"rst_IDInOriginatingDB",
"rst_MaxValues",
"rst_MinValues",
"rst_DisplayDetailTypeGroupID",
"rst_FilteredJsonTermIDTree",
"rst_TermIDTreeNonSelectableIDs",
"rst_PtrFilteredIDs",
"rst_OrderForThumbnailGeneration"
);

$dtyColumnNames = array(
"dty_ID",
"dty_Name",
"dty_Documentation",
"dty_Type",
"dty_HelpText",
"dty_ExtendedDescription",
"dty_Status",
"dty_OriginatingDBID",
"dty_NameInOriginatingDB",
"dty_IDInOriginatingDB",
"dty_DetailTypeGroupID",
"dty_OrderInGroup",
"dty_PtrTargetRectypeIDs",
"dty_JsonTermIDTree",
"dty_TermIDTreeNonSelectableIDs",
"dty_FieldSetRectypeID",
"dty_ShowInLists"
);

//field names and types for defRecTypeGroups
$rtgColumnNames = array(
"rtg_Name"=>"s",
"rtg_Description"=>"s",
);
$dtgColumnNames = array(
"dtg_Name"=>"s",
"dtg_Description"=>"s",
);



mysql_connection_db_overwrite(DATABASE);

if (!@$_REQUEST['method']) {
	die("invalid call to saveStructure, method parameter is required");
}else if(!in_array($_REQUEST['method'],$legalMethods)) {
	die("unsupported method call to saveStructure");
}

$db = mysqli_connection_overwrite(DATABASE); //artem's


switch (@$_REQUEST['method']) {


	//{ rectype:
	//			{colNames:{ common:[rty_name,rty_OrderInGroup,.......],
	//						dtFields:[rst_DisplayName, ....]},
	//			defs : {-1:[[common:['newRecType name',56,34],dtFields:{dty_ID:[overide name,76,43], 160:[overide name2,136,22]}],
	//						[common:[...],dtFields:{nnn:[....],...,mmm:[....]}]],
	//					23:{common:[....], dtFields:{nnn:[....],...,mmm:[....]}}}}}
	case 'saveRectype':
	case 'saveRT':
		$srtData = @$_REQUEST['data'];
		$rtData = json_decode($srtData, true);
		if (!array_key_exists('rectype',$rtData) ||
			!array_key_exists('colNames',$rtData['rectype']) ||
			!array_key_exists('defs',$rtData['rectype'])) {
			die("invalid data structure sent with saveRectype method call to saveStructure.php");
		}
		$commonNames = $rtData['rectype']['colNames']['common'];
		$dtFieldNames = $rtData['rectype']['colNames']['dtFields'];
		$rv = array();
		foreach ($rtData['rectype']['defs'] as $rtyID => $rt) {
			if ($rtyID == -1) {	// new rectypes
				array_push($rv, createRectypes($commonNames,$dtFieldNames,$rt));
			}else{
				array_push($rv, array($rtyID => updateRectype($commonNames,$dtFieldNames,$rtyID,$rt)));
			}
		}
		$rv['rectypes'] = getAllRectypeStructures();
		break;

	case 'saveRTG':		// SAVE RECORDTYPE GROUP

		$srtData = @$_REQUEST['data'];
		$rtData = json_decode($srtData, true);
		if (!array_key_exists('rectypegroups',$rtData) ||
			!array_key_exists('colNames',$rtData['rectypegroups']) ||
			!array_key_exists('defs',$rtData['rectypegroups'])) {
			die("invalid data structure sent with saveRectype method call to saveStructure.php");
		}
		$colNames = $rtData['rectypegroups']['colNames'];
		$rv = array();
		foreach ($rtData['rectypegroups']['defs'] as $rtgID => $rt) {
			if ($rtgID == -1) {	// new rectype group
				array_push($rv, createRectypeGroups($colNames, $rt));
			}else{
				array_push($rv, updateRectypeGroup($colNames, $rtgID, $rt));
			}
		}
		if (!array_key_exists('error',$rv)) {
				$rv['rectypes'] = getAllRectypeStructures();
		}

		break;

	case 'deleteRTG':

		$rtgID = @$_REQUEST['rtgID'];
		if (!$rtgID) {
			die("invalid or not rectype sent with deleteRectypeGroup method call to saveStructure.php");
		}
		$rv = deleteRectypeGroup($rtgID);
		if (!array_key_exists('error',$rv)) {
			$rv['rectypes'] = getAllRectypeStructures();
		}
		break;

	case 'saveDTG':		// SAVE DET TYPE GROUP

		$srtData = @$_REQUEST['data'];
		$rtData = json_decode($srtData, true);
		if (!array_key_exists('dettypegroups',$rtData) ||
			!array_key_exists('colNames',$rtData['dettypegroups']) ||
			!array_key_exists('defs',$rtData['dettypegroups'])) {
			die("invalid data structure sent with saveDettype method call to saveStructure.php");
		}
		$colNames = $rtData['dettypegroups']['colNames'];
		$rv = array();
		foreach ($rtData['dettypegroups']['defs'] as $dtgID => $rt) {
			if ($dtgID == -1) {	// new dettype group
				array_push($rv, createDettypeGroups($colNames, $rt));
			}else{
				array_push($rv, updateDettypeGroup($colNames, $dtgID, $rt));
			}
		}
		if (!array_key_exists('error',$rv)) {
				$rv['detailTypes'] = getAllDetailTypeStructures();
		}

		break;

	case 'deleteDTG':

		$dtgID = @$_REQUEST['dtgID'];
		if (!$dtgID) {
			die("invalid or not dettype sent with deleteDettype method call to saveStructure.php");
		}
		$rv = deleteDettypeGroup($dtgID);
		if (!array_key_exists('error',$rv)) {
			$rv['detailTypes'] = getAllDetailTypeStructures();
		}
		break;

	case 'deleteRectype':
	case 'deleteRT':
		$rtyID = @$_REQUEST['rtyID'];
		if (!$rtyID) {
			die("invalid or not rectype sent with deleteRectype method call to saveStructure.php");
		}
		$rv = deleteRectype($rtyID);
		if (!array_key_exists('error',$rv)) {
			$rv['rectypes'] = getAllRectypeStructures();
		}
		break;

	case 'saveDetailType':
	case 'saveDT':
		$sdtData = @$_REQUEST['data'];
		$dtData = json_decode($sdtData, true);

		if (!array_key_exists('detailtype',$dtData) ||
			!array_key_exists('colNames',$dtData['detailtype']) ||
			!array_key_exists('defs',$dtData['detailtype'])) {
			die("invalid data structure sent with saveDetailType method call to saveStructure.php");
		}
		$commonNames = $dtData['detailtype']['colNames']['common'];
		$rv = array();
		foreach ($dtData['detailtype']['defs'] as $dtyID => $dt) {
			if ($dtyID == -1) {	// new detailtypes
				array_push($rv,createDetailTypes($commonNames,$dt));
			}else{
				array_push($rv,array($dtyID => updateDetailType($commonNames,$dtyID,$dt)));
			}
		}
		$rv['detailTypes'] = getAllDetailTypeStructures();
		break;

	case 'deleteDetailType':
	case 'deleteDT':
		$dtyID = @$_REQUEST['dtyID'];
		if (!$dtyID) {
			die("invalid or no detailtype sent with deleteDetailType method call to saveStructure.php");
		}
		$rv = deleteDetailType($dtyID);
		if (!array_key_exists('error',$rv)) {
			$rv['detailtypes'] = getAllDetailTypeStructures();
		}
		break;

}
$db->close();

print json_format($rv);
/*
if (@$rv) {
	print json_format($rv);
}*/

exit();

/* END OF LOGIC */

/**
* createRectypes - Helper function that inserts a new rectype into defRecTypes table.and use the rty_ID to insert any
* fields into the defRecStructure table
* @author Stephen White
* @param $commonNames an array valid column names in the defRecTypes table which match the order of data in the $rt param
* @param $dtFieldNames an array valid column names in the defRecStructure table
* @param $rt astructured array of which can contain the column names and data for one or more rectypes with fields
* @return $ret an array of return values for the various data elements created or errors if they occurred
**/


function createRectypes($commonNames,$dtFieldNames,$rt) {
global $rtyColumnNames,$rstColumnNames;
	$ret = array();
	if (count($commonNames)) {
		$ret['common'] =array();
		$colNames = join(",",$commonNames);
		foreach ( $rt as $newRT) {
			$colValues = join(",", $newRT['common']);
			$query = "insert into defRecTypes ($colNames) values ($colValues)";
			$res = mysql_query($query);
			if (mysql_error()) {
				array_push($ret['common'],array('error'=>"error inserting into defRecTypes - ".mysql_error()));
			} else {
				$rtyID = mysql_insert_id();
				array_push($ret['common'], "insert $rtyID ok");
			}
			//check dtFieldsNames for updating fields for this rectype
			if ($rtyID && count($dtFieldNames) && count($newRT['dtFields'])) {
				$ret['dtFields'] = array();
				foreach ($newRT['dtFields'] as $dtyID => $fieldVals) {
					$ret['dtFields'][$dtyID] = array();
					$fieldNames = "rst_RecTypeID,rst_DetailTypeID,".join(",",$dtFieldNames);
					$fieldValues = "$rtyID,$dtyID,".join(",",$fieldVals);
					$query = "insert into defRecStructure ($fieldNames) values ($fieldValues)";
					mysql_query($query);
					if (mysql_error()) {
						array_push($ret['dtFields'][$dtyID],array('error'=>"error inserting fields for $dtyID defRecStructure - ".mysql_error()));
					} else {
						array_push($ret['dtFields'][$dtyID], "insert $dty ok");
					}
				}
			}
		}
	}
	if (!@$ret['common'] && !@$ret['dtFields']) {
		$ret = array("error"=>"no data supplied for inserting rectype");
	}
	return $ret;
}

/**
* deleteRectype - Helper function that delete a rectype from defRecTypes table.if there are no existing records of this type
* @author Stephen White
* @param $rtyID rectype ID to delete
* @return $ret an array of return values for the various data elements created or errors if they occurred
**/

function deleteRectype($rtyID) {
global $rtyColumnNames,$rstColumnNames;
	$ret = array();
	$query = "select rec_ID from Records where rec_RecTypeID =$rtyID";
	$res = mysql_query($query);
	if (mysql_error()) {
		$ret['error'] = "error finding records of type $rtyID from Records - ".mysql_error();
	} else {
		$recCount = mysql_num_rows($res);
		if ($recCount) { // there are records existing of this rectype, need to return error and the recIDs
			$ret['error'] = "error deleting Rectype($rtyID) with existing data Records ($recCount) not allowed";
			$ret['recIDs'] = array();
			while ($row = mysql_fetch_row($res)) {
				array_push($ret['recIDs'], $row[0]);
			}
		} else { // no records ok to delete this rectype. Not that this should cascade for all dependent definitions
			$query = "delete from defRecTypes where rty_ID =$rtyID  limit = 1";
			$res = mysql_query($query);
			if (mysql_error()) {
				$ret['error'] = "db error deleting of rectype $rtyID from defRecTypes - ".mysql_error();
			} else {
				$ret['success'] = "rectype $rtyID deleted from defRecTypes";
			}
		}
	}
	return $ret;
}

/**
* updateRectype - Helper function that updates rectypes in the defRecTypes table.and updates or inserts any
* fields into the defRecStructure table for the given rtyID
* @author Stephen White
* @param $commonNames an array valid column names in the defRecTypes table which match the order of data in the $rt param
* @param $dtFieldNames an array valid column names in the defRecStructure table
* @param $rtyID id of the rectype to update
* @param $rt a structured array of which can contain the column names and data for one or more rectypes with fields
* @return $ret an array of return values for the various data elements created or errors if they occurred
**/

function updateRectype($commonNames,$dtFieldNames,$rtyID,$rt) {
global $rtyColumnNames,$rstColumnNames;

	$res = mysql_query("select * from defRecTypes where rty_ID = $rtyID");
	if ( !mysql_num_rows($res)){
		return array("error" => "invalid rty_ID ($rtyID) passed in data to updateRectype");
	}
	//$row = mysql_fetch_assoc($res);	// saw TODO: get row and add error checking code
	//check commonNames for updating the rectype commen data

	$ret = array();
	if (count($commonNames)) {
		$ret['common'] =array();
		$vals = $rt['common'];
		foreach ($commonNames as $colName) {
			$val = array_shift($vals);
			if (!in_array($colName,$rtyColumnNames)) {
				array_push($ret['common'],array('error'=>"$colName is not a valid column name for defRecTypes val= $val was not used"));
				continue;
			}
			$query = "update defRecTypes set $colName = '$val' where rty_ID = $rtyID";
			mysql_query($query);
			if (mysql_error()) {
				array_push($ret['common'],array('error'=>"error updating $colName in defRecTypes - ".mysql_error()));
			} else {
				array_push($ret['common'], "ok");
			}
		}
	}

	//check dtFieldsNames for updating fields for this rectype
	if (count($dtFieldNames) && count($rt['dtFields'])) {
		$ret['dtFields'] = array();
		foreach ($rt['dtFields'] as $dtyID => $fieldVals) {
			$ret['dtFields'][$dtyID] = array();
			$res = mysql_query("select * from defRecStructure where rst_RecTypeID = $rtyID and rst_DetailTypeID = $dtyID");
			if ( !mysql_num_rows($res)){ // not an update lets try to insert
				$fieldNames = "rst_RecTypeID,rst_DetailTypeID,".join(",",$dtFieldNames);
				$fieldValues = "$rtyID,$dtyID,".join(",",$fieldVals);
				$query = "insert into defRecStructure ($fieldNames) values ($fieldValues)";
				mysql_query($query);
				if (mysql_error()) {
					array_push($ret['dtFields'][$dtyID],array('error'=>"error inserting fields for $dtyID defRecStructure - ".mysql_error()));
				} else {
					array_push($ret['dtFields'][$dtyID], "insert $dty ok");
				}
			} else { // update field
				foreach ($dtFieldNames as $colName) {
					$val = array_shift($fieldVals);
					if (!in_array($colName,$rstColumnNames)) {
						array_push($ret['dtFields'][$dtyID],array('error'=>"$colName is not a valid column name for defRecStructure val= $val was not used"));
						continue;
					}
					$query = "update defRecStructure set $colName = '$val' where rst_RecTypeID = $rtyID and rst_DetailTypeID = $dtyID";
					mysql_query($query);
					if (mysql_error()) {
						array_push($ret['dtFields'][$dtyID],array('error'=>"error updating $colName in field $dtyID defRecStructure - ".mysql_error()));
					} else {
						array_push($ret['dtFields'][$dtyID], "ok");
					}
				}
			}
		}
	}
	if (!@$ret['common'] && !@$ret['dtFields']) {
		$ret = array("error"=>"no data supplied for updating rectype - $rtyID");
	}
	return $ret;
}


/**
* createRectypeGroups - Helper function that inserts a new rectypegroup into defRecTypeGroups table
* @author Artem Osmakov
* @param $columnNames an array valid column names in the defRecTypeGroups table which match the order of data in the $rt param
* @param $rt array of data
* @return $ret an array of return values for the various data elements created or errors if they occurred
**/

function createRectypeGroups($columnNames, $rt) {
	global $db, $rtgColumnNames;

	$ret = array();
	if (count($columnNames)) {

		$colNames = join(",",$columnNames);
		foreach ( $rt as $newRT) {

			$colValues = $newRT['values'];
			$parameters = array();
			array_push($parameters, ""); //list of field date types
			$query = "";
			foreach ($columnNames as $colName) {
				$val = array_shift($colValues);
				if($query!="") $query = $query.",";
				$query = $query."?";
				$parameters[0] = $parameters[0].$rtgColumnNames[$colName]; //take datatype from array
				array_push($parameters, $val);
			}

			$query = "insert into defRecTypeGroups ($colNames) values ($query)";

			$rows = execSQL($db, $query, $parameters, true);

			if ($rows==0) {
				$ret['error'] = "error inserting into defRecTypeGroups - ".mysql_error();
			} else {
				$rtgID = $db->insert_id;
				$ret['result'] = $rtgID;
				//array_push($ret['common'], "$rtgID");
			}
		}
	}
	if (!@$ret['result'] && !@$ret['error']) {
		$ret['error'] = "no data supplied for inserting rectype";
	}


	return $ret;
}


/**
* updateRectypeGroup - Helper function that updates group in the deleteRectypeGroup table
* @author Artem Osmakov
* @param $columnNames an array valid column names in the deleteRectypeGroups table which match the order of data in the $rt param
* @param $rtgID id of the group to update
* @param $rt - data
* @return $ret an array of return values for the various data elements created or errors if they occurred
**/

function updateRectypeGroup($columnNames, $rtgID, $rt) {
	global $db, $rtgColumnNames;

	$db->query("select * from defRecTypeGroups where rtg_ID = $rtgID");

	if ($db->affected_rows<1){
		return array("error" => "invalid rtg_ID ($rtgID) passed in data to updateRectypeGroup");
	}

	$ret = array();
	$query = "";
	if (count($columnNames)) {

		$vals = $rt;
		$parameters = array();
		array_push($parameters, ""); //list of field date types
		foreach ($columnNames as $colName) {
			$val = array_shift($vals);

			if (array_key_exists($colName, $rtgColumnNames)) {
					//array_push($ret['error'], array('wrongname'=>"$colName is not a valid column name for defRecTypeGroups val= $val was not used"));

					if($query!="") $query = $query.",";
					$query = $query."$colName = ?";

					$parameters[0] = $parameters[0].$rtgColumnNames[$colName]; //take datatype from array
					array_push($parameters, $val);
			}
		}
		//

		if($query!=""){
			$query = "update defRecTypeGroups set ".$query." where rtg_ID = $rtgID";

			$rows = execSQL($db, $query, $parameters, true);
			if ($rows==0) {
				$ret['error'] = "error updating $colName in updateRectypeGroup - ".$db->error;
			} else {
				$ret['result'] = $rtgID;
			}
		}
	}
	if (!@$ret['result'] && !@$ret['error']) {
		$ret['error'] = "no data supplied for updating rectype - $rtgID";
	}

	return $ret;
}

/**
* deleteRectypeGroup - Helper function that delete a group from defRecTypeGroups table.if there are no existing defRectype of this group
* @author Artem Osmakov
* @param $rtgID rectype group ID to delete
* @return $ret an array of return values for the various data elements created or errors if they occurred
**/
function deleteRectypeGroup($rtgID) {

	$ret = array();
	$query = "select rty_ID from defRecTypes where rty_RecTypeGroupIDs =$rtgID";
	$res = mysql_query($query);
	if (mysql_error()) {
		$ret['error'] = "error finding record type of group $rtgID from defRecTypes - ".mysql_error();
	} else {
		$recCount = mysql_num_rows($res);
		if ($recCount) { // there are rectypes existing of this group, need to return error and the recIDs
			$ret['error'] = "error deleting Group ($rtgID) with existing recordTypes ($recCount) not allowed";
			$ret['rtyIDs'] = array();
			while ($row = mysql_fetch_row($res)) {
				array_push($ret['rtyIDs'], $row[0]);
			}
		} else { // no rectypes belong this group -  ok to delete this group.
				// Not that this should cascade for all dependent definitions
			$query = "delete from defRecTypeGroups where rtg_ID=$rtgID";
			$res = mysql_query($query);
			if (mysql_error()) {
				$ret['error'] = "db error deleting of rectype $rtgID from defRecTypeGroups - ".mysql_error();
			} else {
				$ret['result'] = $rtgID;
			}
		}
	}
	return $ret;
}


/**
* createDettypeGroups - Helper function that inserts a new dettypegroup into defDetailTypeGroups table
* @author Artem Osmakov
* @param $columnNames an array valid column names in the defDetailTypeGroups table which match the order of data in the $rt param
* @param $rt array of data
* @return $ret an array of return values for the various data elements created or errors if they occurred
**/

function createDettypeGroups($columnNames, $rt) {
	global $db, $dtgColumnNames;

	$ret = array();
	if (count($columnNames)) {

		$colNames = join(",",$columnNames);
		foreach ( $rt as $newRT) {

			$colValues = $newRT['values'];
			$parameters = array();
			array_push($parameters, ""); //list of field date types
			$query = "";
			foreach ($columnNames as $colName) {
				$val = array_shift($colValues);
				if($query!="") $query = $query.",";
				$query = $query."?";
				$parameters[0] = $parameters[0].$dtgColumnNames[$colName]; //take datatype from array
				array_push($parameters, $val);
			}

			$query = "insert into defDetailTypeGroups ($colNames) values ($query)";

			$rows = execSQL($db, $query, $parameters, true);

			if ($rows==0) {
				$ret['error'] = "error inserting into defDetailTypeGroups - ".mysql_error();
			} else {
				$dtgID = $db->insert_id;
				$ret['result'] = $dtgID;
				//array_push($ret['common'], "$rtgID");
			}
		}
	}
	if (!@$ret['result'] && !@$ret['error']) {
		$ret['error'] = "no data supplied for inserting dettype";
	}


	return $ret;
}


/**
* updateDettypeGroup - Helper function that updates group in the defDetailTypeGroups table
* @author Artem Osmakov
* @param $columnNames an array valid column names in the defDetailTypeGroups table which match the order of data in the $rt param
* @param $dtgID id of the group to update
* @param $rt - data
* @return $ret an array of return values for the various data elements created or errors if they occurred
**/

function updateDettypeGroup($columnNames, $dtgID, $rt) {
	global $db, $dtgColumnNames;

	$db->query("select * from defDetailTypeGroups where dtg_ID = $dtgID");

	if ($db->affected_rows<1){
		return array("error" => "invalid dtg_ID ($dtgID) passed in data to defDetailTypeGroups");
	}

	$ret = array();
	$query = "";
	if (count($columnNames)) {

		$vals = $rt;
		$parameters = array();
		array_push($parameters, ""); //list of field date types
		foreach ($columnNames as $colName) {
			$val = array_shift($vals);

			if (array_key_exists($colName, $dtgColumnNames)) {
					//array_push($ret['error'], array('wrongname'=>"$colName is not a valid column name for defDetailTypeGroups val= $val was not used"));

					if($query!="") $query = $query.",";
					$query = $query."$colName = ?";

					$parameters[0] = $parameters[0].$dtgColumnNames[$colName]; //take datatype from array
					array_push($parameters, $val);
			}
		}
		//

		if($query!=""){
			$query = "update defDetailTypeGroups set ".$query." where dtg_ID = $dtgID";

			$rows = execSQL($db, $query, $parameters, true);
			if ($rows==0) {
				$ret['error'] = "error updating $colName in updateDettypeGroup - ".$db->error;
			} else {
				$ret['result'] = $dtgID;
			}
		}
	}
	if (!@$ret['result'] && !@$ret['error']) {
		$ret['error'] = "no data supplied for updating dettype - $dtgID";
	}

	return $ret;
}

/**
* deleteDettypeGroup - Helper function that delete a group from defDetailTypeGroups table.if there are no existing defRectype of this group
* @author Artem Osmakov
* @param $rtgID rectype group ID to delete
* @return $ret an array of return values for the various data elements created or errors if they occurred
**/
function deleteDettypeGroup($dtgID) {

	$ret = array();
	$query = "select dty_ID from defDetailTypes where dty_DetailTypeGroupID =$dtgID";
	$res = mysql_query($query);
	if (mysql_error()) {
		$ret['error'] = "error finding record type of group $dtgID from defDetailTypes - ".mysql_error();
	} else {
		$recCount = mysql_num_rows($res);
		if ($recCount) { // there are rectypes existing of this group, need to return error and the recIDs
			$ret['error'] = "error deleting Group ($dtgID) with existing detailTypes ($recCount) not allowed";
			$ret['dtyIDs'] = array();
			while ($row = mysql_fetch_row($res)) {
				array_push($ret['dtyIDs'], $row[0]);
			}
		} else { // no rectypes belong this group -  ok to delete this group.
				// Not that this should cascade for all dependent definitions
			$query = "delete from defDetailTypeGroups where dtg_ID=$dtgID";
			$res = mysql_query($query);
			if (mysql_error()) {
				$ret['error'] = "db error deleting of rectype $dtgID from defRecTypeGroups - ".mysql_error();
			} else {
				$ret['result'] = $dtgID;
			}
		}
	}
	return $ret;
}


/**
* createDetailTypes - Helper function that inserts a new detailTypes into defDetailTypes table
* @author Stephen White
* @param $commonNames an array valid column names in the defDetailTypes table which match the order of data in the $dt param
* @param $dt a structured array of data which can contain the column names and data for one or more detailTypes
* @return $ret an array of return values for the various data elements created or errors if they occurred
**/

function createDetailTypes($commonNames,$dt) {
global $dtyColumnNames;
	$ret = array();
	if (count($commonNames)) {
		$ret['common'] = array();
		$colNames = join(",",$commonNames);
error_log("dt = ".print_r($dt,true));
			$dtValues = array();
			foreach($dt['common'] as $dtVal) {
				array_push($dtValues, "'".mysql_escape_string($dtVal)."'");
			}
			$colValues = join(",", $dtValues);
			$query = "insert into defDetailTypes ($colNames) values ($colValues)";
			$res = mysql_query($query);
			if (mysql_error()) {
				array_push($ret['common'],array('error'=>"error inserting into defDetailTypes - ".mysql_error()));
			} else {
				$dtyID = mysql_insert_id();
				$ret['common'][$dtyID] = array();
				array_push($ret['common'][$dtyID], "ok");
			}
		}
	if (!@$ret['common']) {
		$ret = array("error"=>"no data supplied for inserting rectype");
	}
	return $ret;
}

/**
* updateDetailType - Helper function that updates detailTypes in the defDetailTypes table.
* @author Stephen White
* @param $commonNames an array valid column names in the defDetailTypes table which match the order of data in the $dt param
* @param $dtyID id of the rectype to update
* @param $dt a structured array of which can contain the column names and data for one or more detailTypes with fields
* @return $ret an array of return values for the various data elements created or errors if they occurred
**/

/**
* deleteDetailType - Helper function that deletes a detailtype from defDetailTypes table.if there are no existing details of this type
* @author Stephen White
* @param $dtyID detailtype ID to delete
* @return $ret an array of return values for the various data elements created or errors if they occurred
**/

function deleteDetailType($dtyID) {
global $rtyColumnNames,$rstColumnNames;
	$ret = array();
	$query = "select dtl_ID from recDetails where dtl_DetailTypeID =$dtyID";
	$res = mysql_query($query);
	if (mysql_error()) {
		$ret['error'] = "error finding records of type $dtyID from recDetails - ".mysql_error();
	} else {
		$dtCount = mysql_num_rows($res);
		if ($recCount) { // there are records existing of this rectype, need to return error and the recIDs
			$ret['error'] = "error deleting detailType($dtyID) with existing data recDetails ($dtCount) not allowed";
			$ret['dtlIDs'] = array();
			while ($row = mysql_fetch_row($res)) {
				array_push($ret['dtlIDs'], $row[0]);
			}
		} else { // no records ok to delete this rectype. Not that this should cascade for all dependent definitions
			$query = "delete from defDetailTypes where dty_ID =$dtyID  limit = 1";
			$res = mysql_query($query);
			if (mysql_error()) {
				$ret['error'] = "db error deleting of detailtype $dtyID from defDetailTypes - ".mysql_error();
			} else {
				$ret['success'] = "detailtype $dtyID deleted from defDetailTypes";
			}
		}
	}
	return $ret;
}

function updateDetailType($commonNames,$dtyID,$dt) {
global $dtyColumnNames;
	$res = mysql_query("select * from defDetailTypes where dty_ID = $dtyID");
	if ( !mysql_num_rows($res)){
		return array("error" => "invalid dty_ID ($dtyID) passed in data to updateDetailType");
	}
	//$row = mysql_fetch_assoc($res);	// saw TODO: get row and add error checking code
	//check commonNames for updating the rectype commen data
	$ret = array();
	if (count($commonNames)) {
		$ret['common'] =array();
		$vals = $dt['common'];

		foreach ($commonNames as $colName) {
			$val = array_shift($vals);
			$val = mysql_escape_string($val);
			if (!in_array($colName,$dtyColumnNames)) {
				array_push($ret['common'],array('error'=>"$colName is not a valid column name for defDetailTypes val= $val was not used"));
				continue;
			}

			$query = "update defDetailTypes set $colName = '$val' where dty_ID = $dtyID";

			mysql_query($query);
			if (mysql_error()) {
				array_push($ret['common'],array('error'=>"error updating $colName in defDetailTypes - ".mysql_error()));
			} else {
				array_push($ret['common'], "$colName ok");
			}
		}
	}
	if (!@$ret['common'] ) {
		$ret = array("error"=>"no data supplied for updating detailtype - $dtyID");
	}
	return $ret;
}

//ARTEM uses mysqli
/*
$sql = Statement to execute;
$parameters = array of type and values of the parameters (if any)
$close = true to close $stmt (in inserts) false to return an array with the values;
*/
function execSQL($mysqli, $sql, $params, $close){

		   $stmt = $mysqli->prepare($sql) or die ("Failed to prepared the statement!");

		   call_user_func_array(array($stmt, 'bind_param'), refValues($params));

		   $stmt->execute();

		   if($close){
		   		$result = $mysqli->affected_rows;
		   } else {
		   		$meta = $stmt->result_metadata();

				while ( $field = $meta->fetch_field() ) {
					$parameters[] = &$row[$field->name];
				}

				call_user_func_array(array($stmt, 'bind_result'), refValues($parameters));

			   	while ( $stmt->fetch() ) {
					$x = array();
					foreach( $row as $key => $val ) {
							$x[$key] = $val;
						}
					$results[] = $x;
				}

				$result = $results;
			}

		   	$stmt->close();

		   	return  $result;
}

function refValues($arr){
		if (strnatcmp(phpversion(),'5.3') >= 0) //Reference is required for PHP 5.3+
		{
			$refs = array();
			foreach($arr as $key => $value)
				$refs[$key] = &$arr[$key];
			return $refs;
        }
		return $arr;
}
?>
