<?php

	/*<!--
	* saveStructure.php
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
	"saveRTS",
	"deleteRTS",
	"saveRTG",
	"saveDetailType",
	"saveDT",
	"saveDTG",
	"saveTerms",
	"deleteTerms",
	"deleteDT",
	"deleteRT",
	"deleteRTG",
	"deleteDTG"
	);

	$rtyColumnNames = array(
	"rty_ID"=>"i",
	"rty_Name"=>"s",
	"rty_OrderInGroup"=>"i",
	"rty_Description"=>"s",
	"rty_TitleMask"=>"s",
	"rty_CanonicalTitleMask"=>"s",
	"rty_Plural"=>"s",
	"rty_Status"=>"s",
	"rty_OriginatingDBID"=>"i",
	"rty_NameInOriginatingDB"=>"s",
	"rty_IDInOriginatingDB"=>"i",
	"rty_ShowInLists"=>"i",
	"rty_RecTypeGroupIDs"=>"s",
	"rty_FlagAsFieldset"=>"i",
	"rty_ReferenceURL"=>"s",
	"rty_AlternativeRecEditor"=>"s",
	"rty_Type"=>"s"
	);

	$rstColumnNames = array(
	"rst_ID"=>"i",
	"rst_RecTypeID"=>"i",
	"rst_DetailTypeID"=>"i",
	"rst_DisplayName"=>"s",
	"rst_DisplayHelpText"=>"s",
	"rst_DisplayExtendedDescription"=>"s",
	"rst_DisplayOrder"=>"i",
	"rst_DisplayWidth"=>"i",
	"rst_DefaultValue"=>"s",
	"rst_RecordMatchOrder"=>"i",
	"rst_CalcFunctionID"=>"i",
	"rst_RequirementType"=>"s",
	"rst_Status"=>"s",
	"rst_OriginatingDBID"=>"i",
	"rst_IDInOriginatingDB"=>"i",
	"rst_MaxValues"=>"i",
	"rst_MinValues"=>"i",
	"rst_DisplayDetailTypeGroupID"=>"i",
	"rst_FilteredJsonTermIDTree"=>"s",
	"rst_TermIDTreeNonSelectableIDs"=>"s",
	"rst_PtrFilteredIDs"=>"s",
	"rst_OrderForThumbnailGeneration"=>"i"
	);

	$dtyColumnNames = array(
	"dty_ID"=>"i",
	"dty_Name"=>"s",
	"dty_Documentation"=>"s",
	"dty_Type"=>"s",
	"dty_HelpText"=>"s",
	"dty_ExtendedDescription"=>"s",
	"dty_Status"=>"s",
	"dty_OriginatingDBID"=>"i",
	"dty_NameInOriginatingDB"=>"s",
	"dty_IDInOriginatingDB"=>"i",
	"dty_DetailTypeGroupID"=>"i",
	"dty_OrderInGroup"=>"i",
	"dty_PtrTargetRectypeIDs"=>"s",
	"dty_JsonTermIDTree"=>"s",
	"dty_TermIDTreeNonSelectableIDs"=>"s",
	"dty_FieldSetRectypeID"=>"i",
	"dty_ShowInLists"=>"i"
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

	$trmColumnNames = array(
	"trm_ID"=>"i",
	"trm_Label"=>"s",
	"trm_InverseTermId"=>"i",
	"trm_Description"=>"s",
	"trm_Domain"=>"s",
	"trm_ParentTermID"=>"i"
	);



	mysql_connection_db_overwrite(DATABASE);

	if (!@$_REQUEST['method']) {
	die("invalid call to saveStructure, method parameter is required");
	}else if(!in_array($_REQUEST['method'],$legalMethods)) {
	die("unsupported method call to saveStructure");
	}

	$db = mysqli_connection_overwrite(DATABASE); //artem's

	//decode and unpack data
	$data = json_decode(urldecode(@$_REQUEST['data']), true);


	switch (@$_REQUEST['method']) {


	//{ rectype:
	//			{colNames:{ common:[rty_name,rty_OrderInGroup,.......],
	//						dtFields:[rst_DisplayName, ....]},
	//			defs : {-1:[[common:['newRecType name',56,34],dtFields:{dty_ID:[overide name,76,43], 160:[overide name2,136,22]}],
	//						[common:[...],dtFields:{nnn:[....],...,mmm:[....]}]],
	//					23:{common:[....], dtFields:{nnn:[....],...,mmm:[....]}}}}}
	case 'saveRectype':
	case 'saveRT':
		if (!array_key_exists('rectype',$data) ||
		!array_key_exists('colNames',$data['rectype']) ||
		!array_key_exists('defs',$data['rectype'])) {
			die("invalid data structure sent with saveRectype method call to saveStructure.php");
		}
		$commonNames = $data['rectype']['colNames']['common'];
		//$dtFieldNames = $rtData['rectype']['colNames']['dtFields'];
		$rv = array();
		$rv['result'] = array(); //result

		foreach ($data['rectype']['defs'] as $rtyID => $rt) {
			if ($rtyID == -1) {	// new rectypes
				array_push($rv['result'], createRectypes($commonNames, $rt));
			}else{
				array_push($rv['result'], updateRectype($commonNames, $rtyID, $rt));
			}
		}
		$rv['rectypes'] = getAllRectypeStructures();
		break;

		case 'saveRTS':

		if (!array_key_exists('rectype',$data) ||
		!array_key_exists('colNames',$data['rectype']) ||
		!array_key_exists('defs',$data['rectype']))
		{
			die("invalid data structure sent with saveRectype method call to saveStructure.php");
		}

		//$commonNames = $rtData['rectype']['colNames']['common'];
		$dtFieldNames = $data['rectype']['colNames']['dtFields'];
		$rv = array();
		$rv['result'] = array(); //result

		//actually client sends the definition only for one record type
		foreach ($data['rectype']['defs'] as $rtyID => $rt) {
			array_push($rv['result'], updateRecStructure($dtFieldNames, $rtyID, $rt));
		}
		$rv['rectypes'] = getAllRectypeStructures();
		break;

		case 'deleteRTS':

		$rtyID = @$_REQUEST['rtyID'];
		$dtyID = @$_REQUEST['dtyID'];

		if (!$rtyID || !$dtyID) {
			$rv = array();
			$rv['error'] = "invalid or no IDs sent with deleteReStructure method call to saveStructure.php";
		}else{
			$rv = deleteRecStructure($rtyID, $dtyID);
			if (!array_key_exists('error', $rv)) {
				$rv['rectypes'] = getAllRectypeStructures();
			}
		}
		break;

		case 'deleteRectype':
		case 'deleteRT':

		$rtyID = @$_REQUEST['rtyID'];

		if (!$rtyID) {
			$rv = array();
			$rv['error'] = "invalid or not rectype ID sent with deleteRectype method call to saveStructure.php";
		}else{
			$rv = deleteRecType($rtyID);
			if (!array_key_exists('error',$rv)) {
				$rv['rectypes'] = getAllRectypeStructures();
			}
		}
		break;

		//------------------------------------------------------------
	case 'saveRTG':		// SAVE RECORDTYPE GROUP

		if (!array_key_exists('rectypegroups',$data) ||
		!array_key_exists('colNames',$data['rectypegroups']) ||
		!array_key_exists('defs',$data['rectypegroups'])) {
			die("invalid data structure sent with saveRectype method call to saveStructure.php");
		}
		$colNames = $data['rectypegroups']['colNames'];
		$rv = array();
		foreach ($data['rectypegroups']['defs'] as $rtgID => $rt) {
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

		if (!array_key_exists('dettypegroups',$data) ||
		!array_key_exists('colNames',$data['dettypegroups']) ||
		!array_key_exists('defs',$data['dettypegroups'])) {
			die("invalid data structure sent with saveDettype method call to saveStructure.php");
		}
		$colNames = $data['dettypegroups']['colNames'];
		$rv = array();
		foreach ($data['dettypegroups']['defs'] as $dtgID => $rt) {
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
			die("invalid or not dettype group ID sent with deleteDettype method call to saveStructure.php");
		}
		$rv = deleteDettypeGroup($dtgID);
		if (!array_key_exists('error',$rv)) {
			$rv['detailTypes'] = getAllDetailTypeStructures();
		}
		break;

		//------------------------------------------------------------
	case 'saveDetailType':
	case 'saveDT':

		if (!array_key_exists('detailtype',$data) ||
		!array_key_exists('colNames',$data['detailtype']) ||
		!array_key_exists('defs',$data['detailtype'])) {
			die("invalid data structure sent with saveDetailType method call to saveStructure.php");
		}
		$commonNames = $data['detailtype']['colNames']['common'];
		$rv = array();
		$rv['result'] = array(); //result

		foreach ($data['detailtype']['defs'] as $dtyID => $dt) {
			if ($dtyID == -1) {	// new detailtypes
				array_push($rv['result'], createDetailTypes($commonNames,$dt));
			}else{
				array_push($rv['result'], updateDetailType($commonNames, $dtyID, $dt)); //array($dtyID =>
			}
		}

		$rv['detailTypes'] = getAllDetailTypeStructures();
		break;


	case 'deleteDetailType':
	case 'deleteDT':
		$dtyID = @$_REQUEST['dtyID'];

		if (!$dtyID) {
			$rv = array();
			$rv['error'] = "invalid or no detailtype ID sent with deleteDetailType method call to saveStructure.php";
		}else{
		$rv = deleteDetailType($dtyID);
		if (!array_key_exists('error',$rv)) {
				$rv['detailTypes'] = getAllDetailTypeStructures();
			}
		}
		break;

		case 'saveTerms':

		if (!array_key_exists('terms',$data) ||
		!array_key_exists('colNames',$data['terms']) ||
		!array_key_exists('defs',$data['terms'])) {
			die("invalid data structure sent with saveTerms method call to saveStructure.php");
		}
		$colNames = $data['terms']['colNames'];
		$rv = array();
		$rv['result'] = array(); //result

		foreach ($data['terms']['defs'] as $trmID => $dt) {
			$res = updateTerms($colNames, $trmID, $dt);
			array_push($rv['result'], $res);
		}

		// $rv['terms'] = getTerms();
		break;

		case 'deleteTerms':
		$trmID = @$_REQUEST['trmID'];

		if (!trmID) {
			$rv = array();
			$rv['error'] = "invalid or no term ID sent with deleteTerms method call to saveStructure.php";
		}else{
			$rv = deleteTerms($trmID);
			/*if (!array_key_exists('error',$rv)) {
			$rv['detailTypes'] = getAllDetailTypeStructures();
			}*/
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

	/* STEPHEN's
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
	*/

	//
	//
	//
	function createRectypes($commonNames, $rt) {
		global $db, $rtyColumnNames;

		$ret = null;

		if (count($commonNames)) {

			$colNames = join(",",$commonNames);

			$parameters = array("");
			$query = "";
			foreach ($commonNames as $colName) {
				$val = array_shift($rt[0]['common']);
				if($query!="") $query = $query.",";
				$query = $query."?";
				$parameters[0] = $parameters[0].$rtyColumnNames[$colName];
				array_push($parameters, $val);
			}

			$query = "insert into defRecTypes ($colNames) values ($query)";
			$rows = execSQL($db, $query, $parameters, true);

			if ($rows==0) {
				$ret = "error inserting into defRecTypes - ".$msqli->error;
			} else {
				$rtyID = $db->insert_id;
				$ret = -$rtyID;
			}

		}
		if ($ret ==  null) {
			$ret = "no data supplied for inserting rectype";
		}
		return $ret;
	}



	/**
	* deleteRectype - Helper function that delete a rectype from defRecTypes table.if there are no existing records of this type
	* @author Stephen White
	* @param $rtyID rectype ID to delete
	* @return $ret an array of return values for the various data elements created or errors if they occurred
	**/
	function deleteRecType($rtyID) {

	$ret = array();
		$query = "select rec_ID from Records where rec_RecTypeID=$rtyID limit 1";
	$res = mysql_query($query);
	if (mysql_error()) {
			$ret['error'] = "Error finding records of type $rtyID from Records - ".mysql_error();
	} else {
			$dtCount = mysql_num_rows($res);
		if ($recCount) { // there are records existing of this rectype, need to return error and the recIDs
				$ret['error'] = "Error deleting Rectype($rtyID) with existing data Records ($dtCount) not allowed";
			$ret['recIDs'] = array();
			while ($row = mysql_fetch_row($res)) {
				array_push($ret['recIDs'], $row[0]);
			}
		} else { // no records ok to delete this rectype. Not that this should cascade for all dependent definitions
				$query = "delete from defRecTypes where rty_ID = $rtyID";
			$res = mysql_query($query);
			if (mysql_error()) {
					$ret['error'] = "DB error deleting of rectype $rtyID from defRecTypes - ".mysql_error();
			} else {
					$ret['result'] = $rtyID;
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

	/* STEPHEN's
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
	}*/

	//
	function updateRectype($commonNames, $rtyID, $rt) {

		global $db, $rtyColumnNames;

		$ret = null;

		$db->query("select rty_ID from defRecTypes where rty_ID = $rtyID");

		if ($db->affected_rows<1){
			$ret = "invalid rty_ID ($rtyID) passed in data to updateRectype";
		}

		$query = "";
		if (count($commonNames)) {

			$parameters = array(""); //list of field date types
			foreach ($commonNames as $colName) {

				$val = array_shift($rt['common']);

				//error_log(">>>>>>>>>>>>>>> $colName  val=".$val);

				if (array_key_exists($colName, $rtyColumnNames)) {
					//array_push($ret['error'], "$colName is not a valid column name for defDetailTypes val= $val was not used");

					if($query!="") $query = $query.",";
					$query = $query."$colName = ?";

					$parameters[0] = $parameters[0].$rtyColumnNames[$colName]; //take datatype from array
					array_push($parameters, $val);
				}
			}
			//
			if($query!=""){
				$query = "update defRecTypes set ".$query." where rty_ID = $rtyID";

				//error_log(">>>>>>>>>>>>>>>".$query."   params=".join(",",$parameters)."<<<<<<<<<<<<<<<");

				$res = execSQL($db, $query, $parameters, true);
				if(!is_numeric($res)){
					$ret = "error updating $rtyID in updateRectype - ".$res;
					//}else if ($rows==0) {
					//	$ret = "error updating $rtyID in updateRectype - ".$msqli->error;
					} else {
					$ret = $rtyID;
				}
			}
		}

		if ($ret == null) {
			$ret = "no data supplied for updating rectype - $rtyID";
		}
		return $ret;

	}

	//================================
	//
	// update structure for record type
	//
	function updateRecStructure( $dtFieldNames , $rtyID, $rt) {

		global $db, $rstColumnNames;

		$ret = array(); //result
		$ret[$rtyID] = array();

		$db->query("select rty_ID from defRecTypes where rty_ID = $rtyID");

		if ($db->affected_rows<1){
			array_push($ret, "invalid rty_ID ($rtyID) passed in data to updateRectype");
		}

		if (count($dtFieldNames) && count($rt['dtFields']))
		{

			foreach ($rt['dtFields'] as $dtyID => $fieldVals)
			{

				//$ret['dtFields'][$dtyID] = array();

				$db->query("select rst_RecTypeID from defRecStructure where rst_RecTypeID = $rtyID and rst_DetailTypeID = $dtyID");

				$isInsert = ($db->affected_rows<1);

				//$fieldNames = "rst_RecTypeID,rst_DetailTypeID,".join(",",$dtFieldNames);

				$query = "";
				$fieldNames = "";
				$parameters = array(""); //list of field date types
				foreach ($dtFieldNames as $colName) {

					$val = array_shift($fieldVals);

					if (array_key_exists($colName, $rstColumnNames)) {
						//array_push($ret['error'], "$colName is not a valid column name for defDetailTypes val= $val was not used");

						if($isInsert){
							if($query!="") $query = $query.",";
							$fieldNames = $fieldNames.", $colName";
							$query = $query."?";
						}else{
							if($query!="") $query = $query.",";
							$query = $query."$colName = ?";
						}

						$parameters[0] = $parameters[0].$rstColumnNames[$colName]; //take datatype from array
						array_push($parameters, $val);
					}
				}//for columns

				if($query!=""){
					if($isInsert){
						$query = "insert into defRecStructure (rst_RecTypeID, rst_DetailTypeID $fieldNames) values ($rtyID, $dtyID,".$query.")";
					}else{
						$query = "update defRecStructure set ".$query." where rst_RecTypeID = $rtyID and rst_DetailTypeID = $dtyID";
					}

					$rows = execSQL($db, $query, $parameters, true);

					if ($rows==0) {
						$oper = (($isInsert)?"inserting":"updating");
						array_push($ret[$rtyID], "error ".$oper." field type ".$dtyID." for record type ".$rtyID." in updateRecStructure - ".$msqli->error);
					} else {
						array_push($ret[$rtyID], $dtyID);
					}
				}
			}//for each dt
			} //if column names

		if (count($ret[$rtyID])==0) {
			array_push($ret[$rtyID], "no data supplied for updating record structure - $rtyID");
		}

		return $ret;
	}

	//================================
	//
	// update structure for record type
	//
	function deleteRecStructure($rtyID, $dtyID) {
		global $db;

		$db->query("delete from defRecStructure where rst_RecTypeID = $rtyID and rst_DetailTypeID = $dtyID limit 1");

		$rv = array();

		if ($db->affected_rows<1){
			$rv['error'] = "error delting entry in defRecStructure for record type #$rtyID and field type #$dtyID";
		}else{
			$rv['result'] = $dtyID;
		}
		return $rv;
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
				$parameters = array(""); //list of field date types
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
					$ret['error'] = "error inserting into defRecTypeGroups - ".$msqli->error;
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
			$parameters = array(""); //list of field date types
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
				$ret['error'] = "error updating $colName in updateRectypeGroup - ".$msqli->error;
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
				$parameters = array(""); //list of field date types
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
					$ret['error'] = "error inserting into defDetailTypeGroups - ".$msqli->error;
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
			$parameters = array(""); //list of field date types
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
				$ret['error'] = "error updating $colName in updateDettypeGroup - ".$msqli->error;
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

	// -------------------------------  DETAILS ---------------------------------------
	/**
	* createDetailTypes - Helper function that inserts a new detailTypes into defDetailTypes table
	* @author Stephen White
	* @param $commonNames an array valid column names in the defDetailTypes table which match the order of data in the $dt param
	* @param $dt a structured array of data which can contain the column names and data for one or more detailTypes
	* @return $ret an array of return values for the various data elements created or errors if they occurred
	**/

	function createDetailTypes($commonNames,$dt) {
		global $db, $dtyColumnNames;

		$ret = null;

	if (count($commonNames)) {


		$colNames = join(",",$commonNames);

			$parameters = array(""); //list of field date types
			$query = "";
			foreach ($commonNames as $colName) {
				$val = array_shift($dt['common']);
				if($query!="") $query = $query.",";
				$query = $query."?";
				$parameters[0] = $parameters[0].$dtyColumnNames[$colName]; //take datatype from array
				array_push($parameters, $val);
			}

			$query = "insert into defDetailTypes ($colNames) values ($query)";
			$rows = execSQL($db, $query, $parameters, true);

			if ($rows==0) {
				$ret = "error inserting into defDetailTypes - ".$msqli->error;
			} else {
				$dtyID = $db->insert_id;
				$ret = -$dtyID;
			}

		}
		if ($ret ==  null) {
			$ret = "no data supplied for inserting dettype";
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

	$ret = array();
	$query = "select dtl_ID from recDetails where dtl_DetailTypeID =$dtyID";
	$res = mysql_query($query);
	if (mysql_error()) {
			$ret['error'] = "Error finding records of type $dtyID from recDetails - ".mysql_error();
	} else {
		$dtCount = mysql_num_rows($res);
			if ($dtCount) { // there are records existing of this rectype, need to return error and the recIDs
				$ret['error'] = "Error deleting detailType($dtyID) with existing data recDetails ($dtCount) not allowed";
			$ret['dtlIDs'] = array();
			while ($row = mysql_fetch_row($res)) {
				array_push($ret['dtlIDs'], $row[0]);
			}
		} else { // no records ok to delete this rectype. Not that this should cascade for all dependent definitions
				$query = "delete from defDetailTypes where dty_ID = $dtyID";
			$res = mysql_query($query);
			if (mysql_error()) {
					$ret['error'] = "DB error deleting of detailtype $dtyID from defDetailTypes - ".mysql_error();
			} else {
					$ret['result'] = $dtyID;
			}
		}
	}
	return $ret;
	}

	//
	function updateDetailType($commonNames,$dtyID,$dt) {

		global $db, $dtyColumnNames;

		$ret = null;

		$db->query("select dty_ID from defDetailTypes where dty_ID = $dtyID");

		if ($db->affected_rows<1){
			$ret = "invalid dty_ID ($dtyID) passed in data to updateDetailType";
	}

		$query = "";
	if (count($commonNames)) {

			$vals = $dt['common'];
			$parameters = array(""); //list of field date types
		foreach ($commonNames as $colName) {

			$val = array_shift($vals);

				if (array_key_exists($colName, $dtyColumnNames)) {
					//array_push($ret['error'], "$colName is not a valid column name for defDetailTypes val= $val was not used");

					if($query!="") $query = $query.",";
					$query = $query."$colName = ?";

					$parameters[0] = $parameters[0].$dtyColumnNames[$colName]; //take datatype from array
					array_push($parameters, $val);
				}
			}
			//
			if($query!=""){
				$query = "update defDetailTypes set ".$query." where dty_ID = $dtyID";

				$rows = execSQL($db, $query, $parameters, true);
				if ($rows==0) {
					$ret = "error updating $dtyID in updateDetailType - ".$msqli->error;
				} else {
					$ret = $dtyID;
				}
			}
		}

		if ($ret == null) {
			$ret = "no data supplied for updating dettype - $dtyID";
		}
		return $ret;
	}

	//========================================

	//================================
	/**
	* update terms
	*
	* @param $coldNames - array of field names
	* @param $trmID - term id, in case new term this is string
	* @param $values - array of values
	* @return $ret - if success this is ID of term, if failure - error string
	*/
	function updateTerms( $colNames, $trmID, $values) {

		global $db, $trmColumnNames;

		$ret = null;

		if (count($colNames) && count($values))
		{
			$isInsert = (!is_numeric($trmID) && (strrpos($trmID, "-")>0));

			$query = "";
			$fieldNames = "";
			$parameters = array("");
			$fieldNames = join(",",$colNames);

			foreach ($colNames as $colName) {

				$val = array_shift($values);

				if (array_key_exists($colName, $trmColumnNames)) {
					//array_push($ret['error'], "$colName is not a valid column name for defDetailTypes val= $val was not used");

					if($query!="") $query = $query.",";

					if($isInsert){
						$query = $query."?";
						//if($fieldNames!="") $fieldNames=$fieldNames.",";
						//$fieldNames = $fieldNames.$colName;
						}else{
						$query = $query."$colName = ?";
					}

					$parameters[0] = $parameters[0].$trmColumnNames[$colName]; //take datatype from array
					array_push($parameters, $val);

				}
			}//for columns

			if($query!=""){
				if($isInsert){
					$query = "insert into defTerms (".$fieldNames.") values (".$query.")";
				}else{
					$query = "update defTerms set ".$query." where trm_ID = $trmID";
				}

				$rows = execSQL($db, $query, $parameters, true);

				if ($rows==0) {
					$oper = (($isInsert)?"inserting":"updating");
					$ret = "error $oper term# $trmID in updateTerms - ".$msqli->error;
			} else {
					if($isInsert){
						$trmID = $db->insert_id;
			}

					$ret = $trmID;
		}
	}
		} //if column names

		if ($ret==null){
			$ret = "no data supplied for updating record structure - $trmID";
	}

	return $ret;
	}

	/**
	* recursive function
	* @param $ret -- array of child
	* @param $trmID - term id to be find all children
	*/
	function getTermsChilds($ret, $trmID) {

		$query = "select trm_ID from defTerms where trm_ParentTermID = $trmID";
		$res = mysql_query($query);
		while ($row = mysql_fetch_row($res)) {
			$child_trmID = $row[0];
			$ret = getTermsChilds($ret, $child_trmID);
			array_push($ret, $child_trmID);
		}
		return $ret;
	}

	/**
	* deletes the term with given ID and all its children
	* before deletetion it verifies that this term or any of its children is refered in defDetailTypes dty_JsonTermIDTree
	*
	* @todo - need to check inverseid or it will error by foreign key constraint?
	*/
	function deleteTerms($trmID) {

		error_log(">>>>>>>>>>>>>>>>>HERE ");

		$ret = array();

		$children = array();
		//find all children
		$children = getTermsChilds($children, $trmID);
		array_push($children, $trmID);

		error_log(">>>>>>>>>>>>>>>>>".join(",",$children));

		//find possible entries in defDetailTypes dty_JsonTermIDTree
		foreach ($children as $termID) {
			$query = "select dty_ID from defDetailTypes where (FIND_IN_SET($termID, dty_JsonTermIDTree)>0)";
			$res = mysql_query($query);
			if (mysql_error()) {
				$ret['error'] = "Error finding detail types for $termID in deleteTerm - ".mysql_error();
				break;
			}else{
				$dtCount = mysql_num_rows($res);
				if ($dtCount>0) { // there are records existing of this rectype, need to return error and the recIDs
					$ret['error'] = "Error deleting term# $trmID. ".(($trmID==$termID)?"It":"Its child term # $termID")." is reffered in ($dtCount) Field type(s)";
					$ret['dtyIDs'] = array();
					while ($row = mysql_fetch_row($res)) {
						array_push($ret['dtyIDs'], $row[0]);
					}
					break;
				}
			}
			//need to check inverseid or it will error by foreign key constraint?


		}//foreach

		//all is clear - delete the term
		if(!array_key_exists("error", $ret)){
			//$query = "delete from defTerms where trm_ID in (".join(",",$children).")";
			//$query = "delete from defTerms where trm_ID = $trmID";

			foreach ($children as $termID) {
				$query = "delete from defTerms where trm_ID = $termID";
				$res = mysql_query($query);
				if (mysql_error()) {
					$ret['error'] = "DB error deleting of term $termID from defTerms - ".mysql_error();
					break;
				}
			}

			if(!array_key_exists("error", $ret)){
				$ret['result'] = $children;
			}

			/*
			$res = mysql_query($query);
			if (mysql_error()) {
			$ret['error'] = "DB error deleting of term $trmID and its children from defTerms - ".mysql_error();
			} else {
			$ret['result'] = $children;
			}
			*/
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
			$result = $mysqli->error;
			if($result == ""){
		   		$result = $mysqli->affected_rows;
			}
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
