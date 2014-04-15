<?php

/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* saveStructure.php. This file accepts request to update the system structural definitions -
* rectypes, detailtypes, terms and constraints. It returns the entire structure for the affected area
* in order to update top.HEURIST
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
	require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
	require_once(dirname(__FILE__).'/../../common/php/utilsTitleMask.php');
	require_once(dirname(__FILE__).'/../../records/edit/deleteRecordInfo.php');
	require_once(dirname(__FILE__).'/../../common/php/imageLibrary.php');


	if (! is_logged_in()) {
		header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}

	if (! is_admin()) {
		header('Content-type: text/javascript');
		$rv = array();
		$rv['error'] = "Sorry, you need to be a database owner to be able to modify the database structure";
		print json_format($rv);
		return;
	}

	$legalMethods = array(
		"saveRectype",
		"saveRT",
		"saveRTS",
		"deleteRTS",
		"saveRTC",
		"deleteRTC",
		"saveRTG",
		"saveDetailType",
		"saveDT",
		"saveDTG",
		"saveTerms",
		"deleteTerms",
		"deleteDT",
		"deleteRT",
		"deleteRTG",
		"deleteDTG",
		"checkDTusage"
	);

	$rtyColumnNames = array(
		"rty_ID"=>"i",
		"rty_Name"=>"s",
		"rty_OrderInGroup"=>"i",
		"rty_Description"=>"s",
		"rty_TitleMask"=>"s",
		"rty_CanonicalTitleMask"=>"s", //not used anymore
		"rty_Plural"=>"s",
		"rty_Status"=>"s",
		"rty_OriginatingDBID"=>"i",
		"rty_NameInOriginatingDB"=>"s",
		"rty_IDInOriginatingDB"=>"i",
		"rty_NonOwnerVisibility"=>"s",
		"rty_ShowInLists"=>"i",
		"rty_RecTypeGroupID"=>"i",
		"rty_RecTypeModelsIDs"=>"s",
		"rty_FlagAsFieldset"=>"i",
		"rty_ReferenceURL"=>"s",
		"rty_AlternativeRecEditor"=>"s",
		"rty_Type"=>"s",
		"rty_ShowURLOnEditForm" =>"i",
		"rty_ShowDescriptionOnEditForm" =>"i",
		"rty_Modified"=>"i",
		"rty_LocallyModified"=>"i"
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
		"rst_NonOwnerVisibility"=>"s",
		"rst_Status"=>"s",
		"rst_MayModify"=>"s",
		"rst_OriginatingDBID"=>"i",
		"rst_IDInOriginatingDB"=>"i",
		"rst_MaxValues"=>"i",
		"rst_MinValues"=>"i",
		"rst_DisplayDetailTypeGroupID"=>"i",
		"rst_FilteredJsonTermIDTree"=>"s",
		"rst_PtrFilteredIDs"=>"s",
		"rst_OrderForThumbnailGeneration"=>"i",
		"rst_TermIDTreeNonSelectableIDs"=>"s",
		"rst_Modified"=>"i",
		"rst_LocallyModified"=>"i"
	);

	$rcsColumnNames = array(
		"rcs_ID"=>"i",
		"rcs_SourceRectypeID"=>"i",
		"rcs_TargetRectypeID"=>"i",
		"rcs_Description"=>"s",
		"rcs_TermID"=>"i",
		"rcs_TermLimit"=>"i",
		"rcs_Modified"=>"i",
		"rcs_LocallyModified"=>"i"
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
		"dty_PtrTargetRectypeIDs"=>"s",
		"dty_FieldSetRectypeID"=>"i",
		"dty_ShowInLists"=>"i",
		"dty_NonOwnerVisibility"=>"s",
		"dty_Modified"=>"i",
		"dty_LocallyModified"=>"i",
		"dty_EntryMask"=>"s"
	);

	//field names and types for defRecTypeGroups
	$rtgColumnNames = array(
		"rtg_ID"=>"i",
		"rtg_Name"=>"s",
		"rtg_Domain"=>"s",
		"rtg_Order"=>"i",
		"rtg_Description"=>"s",
		"rtg_Modified"=>"i"
	);
	$dtgColumnNames = array(
		"dtg_ID"=>"i",
		"dtg_Name"=>"s",
		"dtg_Order"=>"i",
		"dtg_Description"=>"s",
		"dtg_Modified"=>"i"
	);

	$trmColumnNames = array(
		"trm_ID"=>"i",
		"trm_Label"=>"s",
		"trm_InverseTermId"=>"i",
		"trm_Description"=>"s",
		"trm_Status"=>"s",
		"trm_OriginatingDBID"=>"i",
		"trm_NameInOriginatingDB"=>"s",
		"trm_IDInOriginatingDB"=>"i",
		"trm_AddedByImport"=>"i",
		"trm_IsLocalExtension"=>"i",
		"trm_Domain"=>"s",
		"trm_OntID"=>"i",
		"trm_ChildCount"=>"i",
		"trm_ParentTermID"=>"i",
		"trm_Depth"=>"i",
		"trm_Modified"=>"i",
		"trm_LocallyModified"=>"i",
		"trm_Code"=>"s"
	);

	$method = null;

	/*****DEBUG****///error_log(">>>".print_r($_REQUEST, true));

	$method = @$_REQUEST['method'];
	if (!$method) {
		//die("invalid call to saveStructure, 'method' parameter is required");
		$method = null;
	}else if(!in_array($_REQUEST['method'],$legalMethods)) {
		//die("unsupported method call to saveStructure");
		$method = null;
	}

	if($method)
	{
		header('Content-type: text/javascript');

		mysql_connection_overwrite(DATABASE);
		$db = mysqli_connection_overwrite(DATABASE); //artem's

		//decode and unpack data
		$data = null;
		if(@$_REQUEST['data']){
			$data = json_decode(urldecode(@$_REQUEST['data']), true);
		}


		switch ($method) {

			//{ rectype:
			//			{colNames:{ common:[rty_name,rty_OrderInGroup,.......],
			//						dtFields:[rst_DisplayName, ....]},
			//			defs : {-1:[[common:['newRecType name',56,34],dtFields:{dty_ID:[overide name,76,43], 160:[overide name2,136,22]}],
			//						[common:[...],dtFields:{nnn:[....],...,mmm:[....]}]],
			//					23:{common:[....], dtFields:{nnn:[....],...,mmm:[....]}}}}}

			case 'saveRectype':

			case 'saveRT': // Record type
				if (!array_key_exists('rectype',$data) ||
					!array_key_exists('colNames',$data['rectype']) ||
					!array_key_exists('defs',$data['rectype'])) {
					die("Error: invalid data structure sent with saveRectype method call to saveStructure.php");
				}
				$commonNames = $data['rectype']['colNames']['common'];
				//$dtFieldNames = $rtData['rectype']['colNames']['dtFields'];
				$rv = array();
				$rv['result'] = array(); //result
				$definit = @$_REQUEST['definit'];
                
				foreach ($data['rectype']['defs'] as $rtyID => $rt) {
					if ($rtyID == -1) {	// new rectypes
						array_push($rv['result'], createRectypes($commonNames, $rt, ($definit=="1")));
					}else{
						array_push($rv['result'], updateRectype($commonNames, $rtyID, $rt));
					}
				}
				$rv['rectypes'] = getAllRectypeStructures();
				break;

			case 'saveRTS': // Record type structure

				/*****DEBUG****///DEBUG error_log(">>>>>>>".print_r($data,true));
				if (!array_key_exists('rectype',$data) ||
					!array_key_exists('colNames',$data['rectype']) ||
					!array_key_exists('defs',$data['rectype']))
				{
					die("Error: invalid data structure sent with updateRecStructure method call to saveStructure.php");
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
				$rv['detailTypes'] = getAllDetailTypeStructures();
				break;

			case 'deleteRTS':

				$rtyID = @$_REQUEST['rtyID'];
				$dtyID = @$_REQUEST['dtyID'];

				if (!$rtyID || !$dtyID) {
					$rv = array();
					$rv['error'] = "Error: No IDs or invalid IDs sent with deleteRecStructure method call to saveStructure.php";
				}else{
					$rv = deleteRecStructure($rtyID, $dtyID);
					if (!array_key_exists('error', $rv)) {
						$rv['rectypes'] = getAllRectypeStructures();
						$rv['detailTypes'] = getAllDetailTypeStructures();
					}
				}
				break;

			case 'saveRTC': //Constraints

				$srcID = @$_REQUEST['srcID'];
				$trgID = @$_REQUEST['trgID'];
				$terms_todel = @$_REQUEST['del'];

				$rv = array();

				if (!$srcID && !$trgID) {

					$rv['error'] = "Error: No record type IDs or invalid IDs sent with deleteRelConstraint method call to saveStructure.php";

				}else{
					//$colNames = $data['colNames'];  //['defs']
					$rv['result'] = array(); //result

					for ($ind=0; $ind<count($data); $ind++) {
						array_push($rv['result'], updateRelConstraint($srcID, $trgID, $data[$ind]  ));
					}
					if($terms_todel){
						array_push($rv['result'], deleteRelConstraint($srcID, $trgID, $terms_todel));
					}

					$rv['constraints'] = getAllRectypeConstraint();//getAllRectypeStructures();

				}
				break;


			case 'deleteRTC': //Constraints

				$srcID = @$_REQUEST['srcID'];
				$trgID = @$_REQUEST['trgID'];
				$trmID = @$_REQUEST['trmID'];

				if (!$srcID && !$trgID) {
					$rv = array();
					$rv['error'] = "Error: No record type IDs or invalid IDs sent with deleteRelConstraint method call to saveStructure.php";
				}else{
					$rv = deleteRelConstraint($srcID, $trgID, $trmID);
					if (!array_key_exists('error', $rv)) {
						$rv['constraints'] = getAllRectypeConstraint();//getAllRectypeStructures();
					}
				}
				break;

			case 'deleteRectype':
			case 'deleteRT':

				$rtyID = @$_REQUEST['rtyID'];

				if (!$rtyID) {
					$rv = array();
					$rv['error'] = "Error: No IDs or invalid IDs sent with deleteRectype method call to saveStructure.php";
				}else{
					$rv = deleteRecType($rtyID);
					if (!array_key_exists('error',$rv)) {
						$rv['rectypes'] = getAllRectypeStructures();
					}
				}
				break;

				//------------------------------------------------------------

			case 'saveRTG':	// Record type group

				if (!array_key_exists('rectypegroups',$data) ||
					!array_key_exists('colNames',$data['rectypegroups']) ||
					!array_key_exists('defs',$data['rectypegroups'])) {
					die("Error: invalid data structure sent with saveRectypeGroup method call to saveStructure.php");
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
					die("Error: invalid or no record type group ID sent with deleteRectypeGroup method call to saveStructure.php");
				}
				$rv = deleteRectypeGroup($rtgID);
				if (!array_key_exists('error',$rv)) {
					$rv['rectypes'] = getAllRectypeStructures();
				}
				break;

			case 'saveDTG':	// Field (detail) type group

				if (!array_key_exists('dettypegroups',$data) ||
					!array_key_exists('colNames',$data['dettypegroups']) ||
					!array_key_exists('defs',$data['dettypegroups'])) {
					die("Error: invalid data structure sent with saveDetailType method call to saveStructure.php");
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
					die("Error: invalid or no detail type group ID sent with deleteDetailType method call to saveStructure.php");
				}
				$rv = deleteDettypeGroup($dtgID);
				if (!array_key_exists('error',$rv)) {
					$rv['detailTypes'] = getAllDetailTypeStructures();
				}
				break;

				//------------------------------------------------------------

			case 'saveDetailType': // Field (detail) types
			case 'saveDT':

				if (!array_key_exists('detailtype',$data) ||
					!array_key_exists('colNames',$data['detailtype']) ||
					!array_key_exists('defs',$data['detailtype'])) {
					die("Error: invalid data structure sent with saveDetailType method call to saveStructure.php");
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

			case 'checkDTusage': //used in editRecStructure to prevent detail type delete


				$rtyID = @$_REQUEST['rtyID'];
				$dtyID = @$_REQUEST['dtyID'];

				$rv = findTitleMaskEntries($rtyID, $dtyID);

				break;

			case 'deleteDetailType':
			case 'deleteDT':
				$dtyID = @$_REQUEST['dtyID'];

				if (!$dtyID) {
					$rv = array();
					$rv['error'] = "Error: No IDs or invalid IDs sent with deleteDetailType method call to saveStructure.php";
				}else{
					$rv = deleteDetailType($dtyID);
					if (!array_key_exists('error',$rv)) {
						$rv['detailTypes'] = getAllDetailTypeStructures();
					}
				}
				break;

				//------------------------------------------------------------

			case 'saveTerms': // Terms

				if (!array_key_exists('terms',$data) ||
					!array_key_exists('colNames',$data['terms']) ||
					!array_key_exists('defs',$data['terms'])) {
					die("Error: invalid data structure sent with saveTerms method call to saveStructure.php");
				}
				$colNames = $data['terms']['colNames'];
				$rv = array();
				$rv['result'] = array(); //result

				foreach ($data['terms']['defs'] as $trmID => $dt) {
					$res = updateTerms($colNames, $trmID, $dt, null);
					array_push($rv['result'], $res);
				}

				// slows down the performance, but we need the updated terms because Ian wishes to update terms
				// while selecting terms while editing the field type
				$rv['terms'] = getTerms();
				break;

			case 'deleteTerms':
				$trmID = @$_REQUEST['trmID'];

				if (!$trmID) {
					$rv = array();
					$rv['error'] = "Error: No IDs or invalid IDs sent with deleteTerms method call to saveStructure.php";
				}else{
					$rv = array();
					$ret = deleteTerms($trmID);
					$rv['result'] = $ret;
					$rv['terms'] = getTerms();
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

	}//$method!=null

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
    
    function addParam($parameters, $type, $val){
        $parameters[0] = $parameters[0].$type;
        if($type=="s" && $val!=null){
            $val = trim($val);
        }
        array_push($parameters, $val);
        return $parameters;
    }

	//
	// add new record type
	//
	function createRectypes($commonNames, $rt, $isAddDefaultSetOfFields) {
		global $db, $rtyColumnNames;

		$ret = null;

		if (count($commonNames)) {

			$colNames = join(",",$commonNames);

			$parameters = array("");
			$titleMask = null;
			$query = "";
            
			foreach ($commonNames as $colName) {
				$val = array_shift($rt[0]['common']);

                //keep value of text title mask to create canonical one
                if($colName == "rty_TitleMask"){
                    $titleMask = $val;
                }

				    if($query!="") {
                        $query = $query.",";
                    }
				    $query = $query."?";
                    $parameters = addParam($parameters, $rtyColumnNames[$colName], $val);
			}

			$query = "insert into defRecTypes ($colNames) values ($query)";
            
			$rows = execSQL($db, $query, $parameters, true);

			if($rows == "1062"){
				$ret =  "Record type with specified name already exists in the database, please use the existing record type\nThis type may be hidden - turn it on through Database Designer view > Record types";
			}else if ($rows==0 || is_string($rows) ) {
				$ret = "SQL error inserting data into table defRecTypes: ".$rows;
			} else {
				$rtyID = $db->insert_id;
				$ret = -$rtyID;
				if($isAddDefaultSetOfFields){
					//add default set of detail types
					addDefaultFieldForNewRecordType($rtyID);
				}

                //create canonical title mask
                updateTitleMask($rtyID, $titleMask);

				//create icon and thumbnail
				getRectypeIconURL($rtyID);
				getRectypeThumbURL($rtyID);

			}

		}
		if ($ret ==  null) {
			$ret = "no data supplied for inserting record type";
		}
		return $ret;
	}



	/**
	* deleteRectype - Helper function that delete a rectype from defRecTypes table.if there are no existing records of this type
	*
	* @author Stephen White
	* @param $rtyID rectype ID to delete
	* @return $ret an array of return values for the various data elements created or errors if they occurred
	**/
	function deleteRecType($rtyID) {

		$ret = array();
		$query = "select rec_ID from Records where rec_RecTypeID=$rtyID and rec_FlagTemporary=0 limit 1";
		$res = mysql_query($query);
		if (mysql_error()) {
			$ret['error'] = "SQL error finding records of type $rtyID in the Records table: ".mysql_error();
		} else {
			$recCount = mysql_num_rows($res);
			if ($recCount) { // there are records existing of this rectype, need to return error and the recIDs
				$ret['error'] = "You cannot delete record type $rtyID as it has existing data records";  //$recCount
				$ret['recIDs'] = array();
				while ($row = mysql_fetch_row($res)) {
					array_push($ret['recIDs'], $row[0]);
				}
			} else { // no records ok to delete this rectype. Not that this should cascade for all dependent definitions

				//delete temporary records
				$query = "select rec_ID from Records where rec_RecTypeID=$rtyID and rec_FlagTemporary=1";
				$res = mysql_query($query);
				while ($row = mysql_fetch_row($res)) {
					deleteRecord($row[0]);
				}

				$query = "delete from defRecTypes where rty_ID = $rtyID";
				$res = mysql_query($query);
				if (mysql_error()) {
					$ret['error'] = "SQL error deleting record type $rtyID from defRecTypes table: ".mysql_error();
				} else {

					$icon_filename = HEURIST_ICON_DIR.$rtyID.".png"; //BUG what about thumb??
					if(file_exists($icon_filename)){
						unlink($icon_filename);
					}

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

		$res = $db->query("select rty_OriginatingDBID from defRecTypes where rty_ID = $rtyID");

		if ($res->num_rows<1){ //$db->affected_rows<1){
			$ret = "invalid rty_ID ($rtyID) passed in data to updateRectype";
			return $ret;
		}

		//		$row = $res->fetch_object();
		//		$query = "rty_LocallyModified=".(($row->rty_OriginatingDBID>0)?"1":"0").",";

		/*****DEBUG****///error_log(">>>>>>>>>>>>>>> ".is_array($rt['common']));
		/*****DEBUG****///error_log(">>>>>>>>>>>>>>> ".$rt['common'].length);
		$query="";

		if (count($commonNames)) {

			$parameters = array(""); //list of field date types
			foreach ($commonNames as $colName) {

				$val = array_shift($rt[0]['common']);

				if (array_key_exists($colName, $rtyColumnNames)) {
					//array_push($ret['error'], "$colName is not a valid column name for defDetailTypes val= $val was not used");

					/*****DEBUG****///error_log(">>>>>>>>>>>>>>> $colName  val=".$val);

					if($query!="") $query = $query.",";
					$query = $query."$colName = ?";

                    //since 28-June-2013 - title mask and canonical are the same @todo remove canonical at all
					if($colName == "rty_TitleMask"){
                        //array_push($parameters, ""); //empty title mask - store only canonical!
error_log("UPDATE TITLE MASK >>>>".$val);                        
						$val = titlemask_make($val, $rtyID, 1, null, _ERR_REP_SILENT); //make canonical
error_log("2.UPDATE TITLE MASK >>>>".$val);                        
                    }
                    
                    $parameters = addParam($parameters, $rtyColumnNames[$colName], $val);
                    
				}
			}

			//
			if($query!=""){

				$query = $query.", rty_LocallyModified=IF(rty_OriginatingDBID>0,1,0)";
				$query = "update defRecTypes set ".$query." where rty_ID = $rtyID";

				/*****DEBUG****///error_log(">>>>>>>>>>>>>>>".$query."   params=".join(",",$parameters)."<<<<<<<<<<<<<<<");

				$res = execSQL($db, $query, $parameters, true);
				if($rows == "1062"){
					$ret =  "Record type with specified name already exists in the database, please use the existing record type";
				}else if(!is_numeric($res)){
					$ret = "SQL error updating record type $rtyID in updateRectype: ".$res;
					//}else if ($rows==0) {
					//	$ret = "error updating $rtyID in updateRectype - ".$msqli->error;
				} else {
					$ret = $rtyID;
				}
			}
		}

		if ($ret == null) {
			$ret = "no data supplied for updating record type - $rtyID";
		}
		return $ret;

	}

	//
	// conerts titlemask to concept codes
	//
	function updateTitleMask($rtyID, $mask) {
		global $db;

		$ret = 0;
		if($mask){
				$val = titlemask_make($mask, $rtyID, 1, null, _ERR_REP_SILENT); //make coded
                $parameters = addParam($parameters, "s", $val);
                
                /* DEPRECATED
				$colName = "rty_CanonicalTitleMask";
				$parameters[0] = "ss";//$parameters[0].$rtyColumnNames[$colName];
				array_push($parameters, $val);
                rty_CanonicalTitleMask = ?,
                */

				$query = "update defRecTypes set rty_TitleMask = ? where rty_ID = $rtyID";

				$res = execSQL($db, $query, $parameters, true);
				if(!is_numeric($res)){
					$ret = "SQL error updating record type $rtyID in updateRectype: ".$res;
				}
		}
		return $ret;
	}

	//
	// used in editRecStructure to prevent detail type delete
	//
	function findTitleMaskEntries($rtyID, $dtyID) {

		global $db;

        $dtyID = getDetailTypeConceptID($dtyID);

		$ret = array();

		$query = "select rty_ID, rty_Name from defRecTypes where "
        ."((rty_TitleMask LIKE '%[{$dtyID}]%') OR "
        ."(rty_TitleMask LIKE '%.{$dtyID}]%') OR"
        ."(rty_TitleMask LIKE '%[{$dtyID}.%') OR"
        ."(rty_TitleMask LIKE '%.{$dtyID}.%'))";

		if($rtyID){
			$query .= " AND (rty_ID=".$rtyID.")";
		}

		$res = $db->query($query);

		if ($res->num_rows>0){ //$db->affected_rows<1){
			while($row = $res->fetch_object()){
				$ret[$row->rty_ID] = $row->rty_Name;
			}
		}

		return $ret;
	}


	//
	//
	//
	function getInitRty($ri, $di, $dt, $dtid, $defvals){

		$dt = $dt[$dtid]['commonFields'];

		$arr_target = array();

		$arr_target[$ri['rst_DisplayName']] = $dt[$di['dty_Name']];
		$arr_target[$ri['rst_DisplayHelpText']] = $dt[$di['dty_HelpText']];
		$arr_target[$ri['rst_DisplayExtendedDescription']] = $dt[$di['dty_ExtendedDescription']];
		$arr_target[$ri['rst_DefaultValue']] = "";
		$arr_target[$ri['rst_RequirementType']] = $defvals[0];
		$arr_target[$ri['rst_MaxValues']] = "1";
		$arr_target[$ri['rst_MinValues']] = $defvals[1]; //0 -repeatable, 1-single
		$arr_target[$ri['rst_DisplayWidth']] = $defvals[2];
		$arr_target[$ri['rst_RecordMatchOrder']] = "0";
		$arr_target[$ri['rst_DisplayOrder']] = "null";
		$arr_target[$ri['rst_DisplayDetailTypeGroupID']] = "1";
		$arr_target[$ri['rst_FilteredJsonTermIDTree']] = null;
		$arr_target[$ri['rst_PtrFilteredIDs']] = null;
		$arr_target[$ri['rst_TermIDTreeNonSelectableIDs']] = null;
		$arr_target[$ri['rst_CalcFunctionID']] = null;
		$arr_target[$ri['rst_Status']] = "open";
		$arr_target[$ri['rst_OrderForThumbnailGeneration']] = null;
		$arr_target[$ri['rst_NonOwnerVisibility']] = "viewable";
		//$arr_target[$ri['dty_TermIDTreeNonSelectableIDs']] = "null";
		//$arr_target[$ri['dty_FieldSetRectypeID']] = "null";

		 ksort($arr_target);

		return $arr_target;
	}

	//
	//
	//
	function addDefaultFieldForNewRecordType($rtyID)
	{

			$dt = getAllDetailTypeStructures();
			$dt = $dt['typedefs'];

			$rv = getAllRectypeStructures();
			$dtFieldNames = $rv['typedefs']['dtFieldNames'];

			$di = $dt['fieldNamesToIndex'];
			$ri = $rv['typedefs']['dtFieldNamesToIndex'];

			$data = array();
			$data['dtFields'] = array(
			DT_NAME => getInitRty($ri, $di, $dt, DT_NAME, array('required',1,40)),
			DT_CREATOR => getInitRty($ri, $di, $dt, DT_CREATOR, array('optional',0,40)),
			DT_SHORT_SUMMARY => getInitRty($ri, $di, $dt, DT_SHORT_SUMMARY, array('recommended',1,60)),
			DT_THUMBNAIL => getInitRty($ri, $di, $dt, DT_THUMBNAIL, array('recommended',1,60)),
			DT_GEO_OBJECT => getInitRty($ri, $di, $dt, DT_GEO_OBJECT, array('recommended',1,40))
			// DT_START_DATE => getInitRty($ri, $di, $dt, DT_START_DATE, array('recommended',1,40)),
			// DT_END_DATE => getInitRty($ri, $di, $dt, DT_END_DATE, array('recommended',1,40))
			);

			updateRecStructure($dtFieldNames, $rtyID, $data);
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
			return $ret;
		}

		$query2 = "";

		if (count($dtFieldNames) && count($rt['dtFields']))
		{

			$wasLocallyModified = false;

			foreach ($rt['dtFields'] as $dtyID => $fieldVals)
			{
				//$ret['dtFields'][$dtyID] = array();
				$fieldNames = "";
				$parameters = array(""); //list of field date types


				$res = $db->query("select rst_OriginatingDBID from defRecStructure where rst_RecTypeID = $rtyID and rst_DetailTypeID = $dtyID");

				/*****DEBUG****///error_log("2>>>".$db->affected_rows."  ".$res->num_rows);

				$isInsert = ($db->affected_rows<1);
				if($isInsert){
					$fieldNames = $fieldNames.", rst_LocallyModified";
					$query2 = "9";
				}else{
					$row = $res->fetch_object();
					$query2 = "rst_LocallyModified=".(($row->rst_OriginatingDBID>0)?"1":"0");
					$wasLocallyModified = ($wasLocallyModified || ($row->rst_OriginatingDBID>0));
				}

				//$fieldNames = "rst_RecTypeID,rst_DetailTypeID,".join(",",$dtFieldNames);

				$query = $query2;
				foreach ($dtFieldNames as $colName) {

					$val = array_shift($fieldVals);

					/*****DEBUG****///error_log(">>".$dtyID."   ".$colName."=".$val);

					if (array_key_exists($colName, $rstColumnNames) && $colName!="rst_LocallyModified") {
						//array_push($ret['error'], "$colName is not a valid column name for defDetailTypes val= $val was not used");

						if($isInsert){
							if($query!="") $query = $query.",";
							$fieldNames = $fieldNames.", $colName";
							$query = $query."?";
						}else{
							if($query!="") $query = $query.",";
							$query = $query."$colName = ?";
						}

						//special beviour
						if($colName=='rst_MaxValues' && $val==0){
							$val = null;
						}

                        $parameters = addParam($parameters, $rstColumnNames[$colName], $val);
					}
				}//for columns

				if($query!=""){
					if($isInsert){
						$query = "insert into defRecStructure (rst_RecTypeID, rst_DetailTypeID $fieldNames) values ($rtyID, $dtyID,".$query.")";
					}else{
						$query = "update defRecStructure set ".$query." where rst_RecTypeID = $rtyID and rst_DetailTypeID = $dtyID";
					}

					/*****DEBUG****///error_log(">>>3.".$query);

					$rows = execSQL($db, $query, $parameters, true);

					if ($rows==0 || is_string($rows) ) {
						$oper = (($isInsert)?"inserting":"updating");
						array_push($ret[$rtyID], "Error on ".$oper." field type ".$dtyID." for record type ".$rtyID." in updateRecStructure: ".$rows);
					} else {
						array_push($ret[$rtyID], $dtyID);
					}
				}
			}//for each dt

			if($wasLocallyModified){
				$query = "update defRecTypes set rty_LocallyModified=1  where rty_ID = $rtyID";
				execSQL($db, $query, $parameters, true);
			}

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
		/*****DEBUG****///error_log(">>>>>>>>>>>>>>>".$db->affected_rows);
		/*****DEBUG****///error_log(">>>Error=".$mysqli->error);
		if(isset($mysqli) && $mysqli->error!=""){
			$rv['error'] = "SQL error deleting entry in defRecStructure for record type $rtyID and field type $dtyID: ".$mysqli->error;
		}else if ($db->affected_rows<1){
			$rv['error'] = "Error - no rows affected - deleting entry in defRecStructure for record type $rtyID and field type $dtyID";
		}else{
			$rv['result'] = $dtyID;
		}
		return $rv;
	}

	/**
	* createRectypeGroups - Helper function that inserts a new rectypegroup into defRecTypeGroups table
	*
	* @author Artem Osmakov
	* @param $columnNames an array valid column names in the defRecTypeGroups table which match the order of data in the $rt param
	* @param $rt array of data
	* @return $ret an array of return values for the various data elements created or errors if they occurred
	**/

	function createRectypeGroups($columnNames, $rt) {
		global $db, $rtgColumnNames;

		$rtg_Name = null;
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
                    $parameters = addParam($parameters, $rtgColumnNames[$colName], $val);

					if($colName=='rtg_Name'){
						$rtg_Name = $val;
					}
				}

				if($rtg_Name){
					$db->query("select rtg_ID from defRecTypeGroups where rtg_Name = '$rtg_Name'");
					if ($db->affected_rows==1){
						$ret['error'] = "There is already group with name '$rtg_Name'";
						return $ret;
					}
				}


				$query = "insert into defRecTypeGroups ($colNames) values ($query)";

				$rows = execSQL($db, $query, $parameters, true);

				if ($rows==0 || is_string($rows) ) {
					$ret['error'] = "SQL error inserting data into defRecTypeGroups: ".$rows;
				} else {
					$rtgID = $db->insert_id;
					$ret['result'] = $rtgID;
					//array_push($ret['common'], "$rtgID");
				}
			}
		}
		if (!@$ret['result'] && !@$ret['error']) {
			$ret['error'] = "Error: no data supplied for insertion into record type";
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
			return array("error" => "Error: invalid record type group ID (rtg_ID) $rtgID passed in data to updateRectypeGroup");
		}

		$ret = array();
		$query = "";
		$rtg_Name = null;
		if (count($columnNames)) {

			$vals = $rt;
			$parameters = array(""); //list of field date types
			foreach ($columnNames as $colName) {
				$val = array_shift($vals);

				if (array_key_exists($colName, $rtgColumnNames)) {
					//array_push($ret['error'], array('wrongname'=>"$colName is not a valid column name for defRecTypeGroups val= $val was not used"));

					if($query!="") $query = $query.",";
					$query = $query."$colName = ?";

                    $parameters = addParam($parameters, $rtgColumnNames[$colName], $val);

					if($colName=='rtg_Name'){
						$rtg_Name = $val;
					}
				}
			}
			//

			if($rtg_Name){
				$res = $db->query("select rtg_ID from defRecTypeGroups where rtg_Name = '$rtg_Name' and rtg_ID != $rtgID");
				if ($db->affected_rows==1){
					$ret['error'] = "There is already group with name '$rtg_Name'";
					return $ret;
				}
			}


			if($query!=""){
				$query = "update defRecTypeGroups set ".$query." where rtg_ID = $rtgID";

				$rows = execSQL($db, $query, $parameters, true);
				if ($rows==0 || is_string($rows) ) {
					$ret['error'] = "SQL error updating $colName in updateRectypeGroup: ".$rows;
				} else {
					$ret['result'] = $rtgID;
				}
			}
		}
		if (!@$ret['result'] && !@$ret['error']) {
			$ret['error'] = "Error: no data supplied for updating record type group $rtgID in defRecTypeGroups table";
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
		$query = "select rty_ID from defRecTypes where rty_RecTypeGroupID =$rtgID";
		$res = mysql_query($query);
		if (mysql_error()) {
			$ret['error'] = "Error finding record types for group $rtgID in defRecTypes table: ".mysql_error();
		} else {
			$recCount = mysql_num_rows($res);
			if ($recCount) { // there are rectypes existing of this group, need to return error and the recIDs
				$ret['error'] = "You cannot delete group $rtgID as there are $recCount record types in this group";
				$ret['rtyIDs'] = array();
				while ($row = mysql_fetch_row($res)) {
					array_push($ret['rtyIDs'], $row[0]);
				}
			} else { // no rectypes belong this group -  ok to delete this group.
				// Not that this should cascade for all dependent definitions
				$query = "delete from defRecTypeGroups where rtg_ID=$rtgID";
				$res = mysql_query($query);
				if (mysql_error()) {
					$ret['error'] = "Database error deleting record types group $rtgID from defRecTypeGroups table: ".mysql_error();
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

		$dtg_Name = null;
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
                    $parameters = addParam($parameters, $dtgColumnNames[$colName], $val);

					if($colName=='dtg_Name'){
						$dtg_Name = $val;
					}
				}

				if($dtg_Name){
					$db->query("select dtg_ID from defDetailTypeGroups where dtg_Name = '$dtg_Name'");
					if ($db->affected_rows==1){
						$ret['error'] = "There is already group with name '$dtg_Name'";
						return $ret;
					}
				}


				$query = "insert into defDetailTypeGroups ($colNames) values ($query)";

				$rows = execSQL($db, $query, $parameters, true);

				if ($rows==0 || is_string($rows) ) {
					$ret['error'] = "SQL error inserting data into defDetailTypeGroups table: ".$rows;
				} else {
					$dtgID = $db->insert_id;
					$ret['result'] = $dtgID;
					//array_push($ret['common'], "$rtgID");
				}
			}
		}
		if (!@$ret['result'] && !@$ret['error']) {
			$ret['error'] = "Error: no data supplied for insertion of detail (field) type";
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
			return array("error" => "Error: looking for invalid field type group ID (dtg_ID) $dtgID in defDetailTypeGroups table");
		}

		$ret = array();
		$dtg_Name = null;
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

                    $parameters = addParam($parameters, $dtgColumnNames[$colName], $val);

					if($colName=='dtg_Name'){
						$dtg_Name = $val;
					}
				}
			}
			//

			if($dtg_Name){
				$db->query("select dtg_ID from defDetailTypeGroups where dtg_Name = '$dtg_Name' and dtg_ID!=$dtgID");
				if ($db->affected_rows==1){
					$ret['error'] = "There is already group with name '$dtg_Name'";
					return $ret;
				}
			}


			if($query!=""){
				$query = "update defDetailTypeGroups set ".$query." where dtg_ID = $dtgID";

				$rows = execSQL($db, $query, $parameters, true);
				if ($rows==0 || is_string($rows) ) {
					$ret['error'] = "SQL error updating $colName in updateDettypeGroup: ".$rows;
				} else {
					$ret['result'] = $dtgID;
				}
			}
		}
		if (!@$ret['result'] && !@$ret['error']) {
			$ret['error'] = "Error: no data supplied for updating field type group $dtgID in defDetailTypeGroups table";
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
			$ret['error'] = "Error: unable to find detail types in group $dtgID in the defDetailTypes table: ".mysql_error();
		} else {
			$recCount = mysql_num_rows($res);
			if ($recCount) { // there are rectypes existing of this group, need to return error and the recIDs
				$ret['error'] = "You cannot delete field types group $dtgID because it contains $recCount field types";
				$ret['dtyIDs'] = array();
				while ($row = mysql_fetch_row($res)) {
					array_push($ret['dtyIDs'], $row[0]);
				}
			} else { // no rectypes belong this group -  ok to delete this group.
				// Not that this should cascade for all dependent definitions
				$query = "delete from defDetailTypeGroups where dtg_ID=$dtgID";
				$res = mysql_query($query);
				if (mysql_error()) {
					$ret['error'] = "SQL error deleting field type group $dtgID from defRecTypeGroups table:".mysql_error();
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
                $parameters = addParam($parameters, $dtyColumnNames[$colName], $val);
			}

			$query = "insert into defDetailTypes ($colNames) values ($query)";

			$rows = execSQL($db, $query, $parameters, true);

			if($rows == "1062"){
				$ret =  "Field type with specified name already exists in the database, please use the existing field type.\nThe field may be hidden - turn it on through Database Designer view > Manage Field Types";
			}else  if ($rows==0 || is_string($rows) ) {
				$ret = "Error inserting data into defDetailTypes table: ".$rows;
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
			$ret['error'] = "SQL error: unable to retrieve fields of type $dtyID from recDetails table: ".mysql_error();
		} else {
			$dtCount = mysql_num_rows($res);
			if ($dtCount) { // there are records existing of this rectype, need to return error and the recIDs
				$ret['error'] = "You cannot delete field type $dtyID as it is used $dtCount times in the data";
				$ret['dtlIDs'] = array();
				while ($row = mysql_fetch_row($res)) {
					array_push($ret['dtlIDs'], $row[0]);
				}
			} else { // no records ok to delete this rectype. Not that this should cascade for all dependent definitions
				$query = "delete from defDetailTypes where dty_ID = $dtyID";
				$res = mysql_query($query);
				if (mysql_error()) {
					$ret['error'] = "SQL error deleting field type $dtyID from defDetailTypes table: ".mysql_error();
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

		$res = $db->query("select dty_OriginatingDBID from defDetailTypes where dty_ID = $dtyID");

		if ($res->num_rows<1){ //$db->affected_rows<1){
			$ret = "invalid dty_ID ($dtyID) passed in data to updateDetailType";
			return $ret;
		}
		//$row = $res->fetch_object();
		//$query = "dty_LocallyModified=".(($row->dty_OriginatingDBID>0)?"1":"0").",";
		$query = "";

		if (count($commonNames)) {

			$vals = $dt['common'];
			$parameters = array(""); //list of field date types
			foreach ($commonNames as $colName)
			{

				$val = array_shift($vals); //take next value

				if (array_key_exists($colName, $dtyColumnNames)) {
					//array_push($ret['error'], "$colName is not a valid column name for defDetailTypes val= $val was not used");

					if($query!="") $query = $query.",";
					$query = $query."$colName = ?";

                    $parameters = addParam($parameters, $dtyColumnNames[$colName], $val);
				}
			}//for
			//
			if($query!=""){

				$query = $query.", dty_LocallyModified=IF(dty_OriginatingDBID>0,1,0)";
				$query = "update defDetailTypes set ".$query." where dty_ID = $dtyID";

				$rows = execSQL($db, $query, $parameters, true);
				if($rows == "1062"){
					$ret =  "Field type with specified name already exists in the database, please use the existing field type";
				}else if ($rows==0 || is_string($rows) ) {
//error_log($query);error_log($rows);
					$ret = "AAA SQL error updating field type $dtyID in updateDetailType: ".htmlspecialchars($query)."  ".$parameters[1]."  ".@$parameters[2];//.$db->error;
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
	function updateTerms( $colNames, $trmID, $values, $ext_db) {

		global $db, $trmColumnNames;

		if($ext_db==null){
			$ext_db = $db;
		}

		$ret = null;

		if (count($colNames) && count($values))
		{
			$isInsert = (!is_numeric($trmID) && (strrpos($trmID, "-")>0));

			$query = "";
			$fieldNames = "";
			$parameters = array("");
			$fieldNames = join(",",$colNames);

            $ch_parent_id = null;
            $ch_code = null;
            $ch_label = null;
            $inverse_termid = null;


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

                    if($colName=="trm_ParentTermID"){
                        $ch_parent_id = $val;
                    }else if($colName=="trm_Code"){
                        $ch_code = $val;
                    }else if($colName=="trm_Label"){
                        $ch_label = $val;
                    }else if($colName=="trm_InverseTermId"){
                        if($val=="") $val=null;
                        $inverse_termid = $val;
                    }

                    $parameters = addParam($parameters, $trmColumnNames[$colName], $val);
				}
			}//for columns

            //check label and code duplication for the same level
            if($ch_code || $ch_label){
                if($ch_parent_id){
                    $ch_parent_id = "trm_ParentTermID=".$ch_parent_id;
                }else{
                    $ch_parent_id = "(trm_ParentTermID is null or trm_ParentTermID=0)";
                }

                $dupquery = "select trm_ID from defTerms where ".$ch_parent_id;

                if(!$isInsert){
                    $dupquery .= " and (trm_ID <>".$trmID.")";
                }
                $dupquery .= " and (";
                if($ch_code){
                    $dupquery .= "(trm_Code = '".mysql_real_escape_string($ch_code)."')";
                }
                if($ch_label){
                    if($ch_code){
                        $dupquery .= " or ";
                    }
                    $dupquery .= "(trm_Label = '".mysql_real_escape_string($ch_label)."')";
                }
                $dupquery .= ")";

                $res = mysql_query($dupquery);
                if (mysql_error()) {
                    $ret = "SQL error checking duplication values in terms: ".mysql_error();
                } else {
                    $recCount = mysql_num_rows($res);
                    if($recCount>0){
                        $ret = "Duplication of label or code is not allowed for same level terms";
                    }
                }

            }


            //insert, update
			if(!$ret && $query!=""){
				if($isInsert){
					$query = "insert into defTerms (".$fieldNames.") values (".$query.")";
				}else{
					$query = $query.", trm_LocallyModified=IF(trm_OriginatingDBID>0,1,0)";
					$query = "update defTerms set ".$query." where trm_ID = $trmID";
				}

				$rows = execSQL($ext_db, $query, $parameters, true);

				if ($rows==0 || is_string($rows) ) {
					$oper = (($isInsert)?"inserting":"updating");
					$ret = "$inverse_termid   SQL error $oper term $trmID in updateTerms: ".$rows;
				} else {
					if($isInsert){
						$trmID = $ext_db->insert_id;
					}
                    
                    if($inverse_termid!=null){
                        $query = "update defTerms set trm_InverseTermId=$trmID where trm_ID=$inverse_termid";    
                        execSQL($ext_db, $query, null, true);
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

		/*****DEBUG****///		error_log(">>>>>>>>>>>>>>>>>HERE ");

		$ret = array();

		$children = array();
		//find all children
		$children = getTermsChilds($children, $trmID);
		array_push($children, $trmID);

		/*****DEBUG****///		error_log(">>>>>>>>>>>>>>>>>".join(",",$children));

		//find possible entries in defDetailTypes dty_JsonTermIDTree
		foreach ($children as $termID) {
			$query = "select dty_ID from defDetailTypes where (FIND_IN_SET($termID, dty_JsonTermIDTree)>0)";
			$res = mysql_query($query);
			if (mysql_error()) {
				$ret['error'] = "SQL error in deleteTerms retreiving feild types which use term $termID: ".mysql_error();
				break;
			}else{
				$dtCount = mysql_num_rows($res);
				if ($dtCount>0) { // there are records existing of this rectype, need to return error and the recIDs
					$ret['error'] = "You cannot delete term $trmID. ".(($trmID==$termID)?"It":"Its child term $termID")." is referenced in $dtCount field type(s)";
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
				/*****DEBUG****///error_log(">>>>>>>>>>>>>>>>>".$res."   ".mysql_error());
				if (mysql_error()) {
					$ret['error'] = "SQL error deleting term $termID from defTerms table: ".mysql_error();
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

	/**
	* put your comment there...
	*
	* @param mixed $rcons - array that contains data for on record in defRelationshipConstraints
	*/
	function updateRelConstraint($srcID, $trgID, $terms){

		global $db, $rcsColumnNames;

		$ret = null;

		if($terms==null){
			$terms = array("null", '', "null", '');
		}

		if(intval($terms[2])<1){ //if($terms[2]==null || $terms[2]=="" || $terms[2]=="0"){
			$terms[2] = "null";
		}

		$where = " where ";

 		if(intval($srcID)<1){
			$srcID = "null";
			$where = $where." rcs_SourceRectypeID is null";
 		}else{
			$where = $where." rcs_SourceRectypeID=".$srcID;
 		}
 		if(intval($trgID)<1){
			$trgID = "null";
			$where = $where." and rcs_TargetRectypeID is null";
 		}else{
			$where = $where." and rcs_TargetRectypeID=".$trgID;
 		}

		if(intval($terms[0])<1){ // $terms[0]==null || $terms[0]==""){
			$terms[0]=="null";
			$where = $where." and rcs_TermID is null";
		}else{
			$where = $where." and rcs_TermID=".$terms[0];;
		}

		$query = "select rcs_ID from defRelationshipConstraints ".$where;

		$res = $db->query($query);

		$parameters = array("s",$terms[3]); //notes will be parameter
		$query = "";

		if ($res==null || $res->num_rows<1){ //$db->affected_rows<1){
			//insert
			$query = "insert into defRelationshipConstraints(rcs_SourceRectypeID, rcs_TargetRectypeID, rcs_Description, rcs_TermID, rcs_TermLimit) values (".
						$srcID.",".$trgID.",?,".$terms[0].",".$terms[2].")";

		}else{
			//update
			$query = "update defRelationshipConstraints set rcs_Description=?, rcs_TermID=".$terms[0].", rcs_TermLimit=".$terms[2].$where;
		}

		$rows = execSQL($db, $query, $parameters, true);
		if ($rows==0 || is_string($rows) ) {
				$ret = "SQL error in updateRelConstraint: ".$query; //$db->error;
		} else {
				$ret = array($srcID, $trgID, $terms);
		}

		return $ret;
	}

	/**
	* Delete constraints
	*/
	function deleteRelConstraint($srcID, $trgID, $trmID){

		$ret = array();
		$query = "delete from defRelationshipConstraints where ";

 		if(intval($srcID)<1){
			$srcID = "null";
			$query = $query." rcs_SourceRectypeID is null";
 		}else{
			$query = $query." rcs_SourceRectypeID=".$srcID;
 		}
 		if(intval($trgID)<1){
			$trgID = "null";
			$query = $query." and rcs_TargetRectypeID is null";
 		}else{
			$query = $query." and rcs_TargetRectypeID=".$trgID;
 		}

		if ( strpos($trmID,",")>0 ) {
			$query = $query." and rcs_TermID in ($trmID)";
		}else if(intval($trmID)<1){
			$query = $query." and rcs_TermID is null";
		}else{
			$query = $query." and rcs_TermID=$trmID";
		}

		$res = mysql_query($query);
		if (mysql_error()) {
			$ret['error'] = "SQL error deleting constraint ($srcID, $trgID, $trmID) from defRelationshipConstraints table: ".mysql_error();
		} else {
			$ret['result'] = "ok";
		}
		return $ret;
	}
?>
