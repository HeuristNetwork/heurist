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
* processAction.php
* Import a record type, with all its Record Structure, Field types, and terms, or crosswalk it with an existing record type
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
* @todo Show the import log once the importing is done, so user can see what happened, and change things where desired
* @todo If an error occurres, delete everything that has been imported
*/


require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');


if (! is_admin()) {
	echo "Error: You do not have sufficient privileges for this action";
	exit();
}

$targetDBName = @$_GET["importingTargetDBName"];
$tempDBName = @$_GET["tempDBName"];
$sourceDBName = @$_GET["sourceDBName"];
$importRtyID = @$_GET["importRtyID"];
$sourceDBID = @$_GET["sourceDBID"];
$importRefdRectypes = @$_GET["noRecursion"] && $_GET["noRecursion"] == 1 ? false:true;
$importVocabs = (@$_GET["importVocabs"] == 1);
$strictImport = @$_GET["strict"] && $_GET["strict"] == 1 ? true:false;
$currentDate = date("d-m");
$error = false;
$importLog = array();

mysql_connection_insert($targetDBName);


switch($_GET["action"]) {
	case "crosswalk":
		crosswalk();
		break;
	case "import":
		import();
		break;
	case "drop":
		dropDB();
		break;
	default:
		echo "Error: Unknown action received";
}
exit();

function crosswalk() {
/*	$res = mysql_query("insert into `defCrosswalk` (`crw_SourcedbID`, `crw_SourceCode`, `crw_DefType`, `crw_LocalCode`) values ('".$_GET["crwSourceDBID"]."','".$_GET["importRtyID"]."','rectype','".$_GET["crwLocalCode"]."')");
	if(!mysql_error()) {
		echo "Successfully crosswalked rectypes (IDs: " . $_GET["importRtyID"] . " and " . $_GET["crwLocalCode"] . ")";
	} else {
		echo "Error: " . mysql_error();
	}
*/}

function import() {
	global $error, $importLog, $tempDBName, $sourceDBName, $targetDBName, $sourceDBID, $importRtyID;
	$error = false;
	$importLog = array();
	if( !$tempDBName || $tempDBName === "" || !$targetDBName || $targetDBName === "" ||
		!$sourceDBID || !is_numeric($sourceDBID)|| !$importRtyID || !is_numeric($importRtyID)) {
		makeLogEntry("importParameters", -1, "One or more required import parameters not supplied or incorrect form ( ".
					"importingDBName={name of target DB} sourceDBID={reg number of source DB or 0} ".
					"importRtyID={numeric ID of rectype} tempDBName={temp db name where source DB type data are held}");
		$error = true;
	}

	if(!$error) {
		mysql_query("start transaction");
		$startedTransaction = true;
		// Get recordtype data that has to be imported
		$res = mysql_query("select * from ".$tempDBName.".defRecTypes where rty_ID = ".$importRtyID);
		if(mysql_num_rows($res) == 0) {
			$error = true;
			makeLogEntry("Record type", $importRtyID, " was not found in local, temporary copy of, source database ($sourceDBName)");
		} else {
			$importRty = mysql_fetch_assoc($res);
			/*****DEBUG****///error_log("Import entity is  ".print_r($importRty,true));
		}
		// check if rectype already imported, if so return the local id.
		if(!$error && $importRty) {
			$origRtyName = $importRty["rty_Name"];
			$replacementName = @$_GET["replaceRecTypeName"];
			if($replacementName && $replacementName != "") {
				$importRty["rty_Name"] = $replacementName;
				$importRty["rty_Plural"] = ""; //TODO  need better way of determining the plural
			}
			if($importRty["rty_OriginatingDBID"] == 0 || $importRty["rty_OriginatingDBID"] == "") {
				$importRty["rty_OriginatingDBID"] = $sourceDBID;
				$importRty["rty_IDInOriginatingDB"] = $importRtyID;
				$importRty["rty_NameInOriginatingDB"] = $origRtyName;
			}
			//lookup rty in target DB
			$resRtyExist = mysql_query("select rty_ID from ".$targetDBName.".defRecTypes ".
							"where rty_OriginatingDBID = ".$importRty["rty_OriginatingDBID"].
							" AND rty_IDInOriginatingDB = ".$importRty["rty_IDInOriginatingDB"]);
			// Rectype is not in target DB so import it
			if(mysql_num_rows($resRtyExist) > 0 ) {
				$localRtyID = mysql_fetch_array($resRtyExist,MYSQL_NUM);
				$localRtyID = $localRtyID[0];
				makeLogEntry("Record type", $importRtyID, " exists in $targetDBName as ID = $localRtyID");
			}else{
				$localRtyID = importRectype($importRty);
			}
		}
	}
	// successful import
	if(!$error) {
		if ($startedTransaction) mysql_query("commit");
		$statusMsg = "";
		if(sizeof($importLog) > 0) {
			foreach($importLog as $logLine) {
				echo  $logLine[0].(intval($logLine[1])<0?"":" #".$logLine[1]." ").$logLine[2] . "<br />";
			}
		}
		echo "Successfully imported record type '".$importRty["rty_Name"]."' from ".$sourceDBName."<br />";
		echo "<br />";
		return $localRtyID;
	// duplicate record found
	} else if (substr(mysql_error(), 0, 9) == "Duplicate") {
		if ($startedTransaction) mysql_query("rollback");
		echo "prompt";
	//general error condition
	} else {
		if ($startedTransaction) mysql_query("rollback");
		if (mysql_error()) {
			$statusMsg = "MySQL error: " . mysql_error() . "<br />";
		} else  {
			$statusMsg = "Error:<br />";
		}
		if(sizeof($importLog) > 0) {
			foreach($importLog as $logLine) {
				$statusMsg .= $logLine[0].(intval($logLine[1])<0?"":" #".$logLine[1]." ").$logLine[2] . "<br />";
			}
			$statusMsg .= "Changes rolled back, nothing was imported";
		}
		// TODO: Delete all information that has already been imported (retrieve from $importLog)
		echo $statusMsg;
	}
}

function importDetailType($importDty) {
	global $error, $importLog, $tempDBName, $targetDBName, $sourceDBID;
	static $importDtyGroupID;
	$importDtyID = $importDty["dty_ID"];
/*****DEBUG****///error_log(" in import dty $importDtyID");
	if (!$importDtyGroupID) {
		// Create new group with todays date, which all detailtypes that the recordtype uses will be added to
		$dtyGroup = mysql_query("select dtg_ID from ".$targetDBName.".defDetailTypeGroups where dtg_Name = 'Imported'");
		if(mysql_num_rows($dtyGroup) == 0) {
			mysql_query("INSERT INTO ".$targetDBName.".defDetailTypeGroups ".
						"(dtg_Name,dtg_Order, dtg_Description) ".
						"VALUES ('Imported', '999',".
								" 'This group contains all detailtypes that were imported from external databases')");
			// Write the insert action to $logEntry, and set $error to true if one occurred
			if(mysql_error()) {
				$error = true;
				makeLogEntry("<b>Error</b> Creating Field-type Group", -1, ". Unable to find field type group 'Imported' - ".mysql_error());
			} else {
				$importDtyGroupID = mysql_insert_id();
				makeLogEntry("Creating Field-type Group", -1, " 'Imported' as #$importDtyGroupID");
			}
		} else {
			$row = mysql_fetch_row($dtyGroup);
			$importDtyGroupID = $row[0];
			makeLogEntry("Using Field-type Group", -1, " 'Imported' as #$importDtyGroupID");
		}
	}
/*****DEBUG****///error_log("import dty $importDtyID 1".($error?"error":""));

	if(!$error && @$importDty['dty_JsonTermIDTree'] && $importDty['dty_JsonTermIDTree'] != '') {
		// term tree exist so need to translate to new ids
		$importDty['dty_JsonTermIDTree'] =  translateTermIDs($importDty['dty_JsonTermIDTree'],"term tree"," detailType '".$importDty["dty_Name"]."' ID = $importDtyID");
	}
/*****DEBUG****///error_log("import dty $importDtyID 2".($error?"error":""));

	if(!$error && @$importDty['dty_TermIDTreeNonSelectableIDs'] && $importDty['dty_TermIDTreeNonSelectableIDs'] != '') {
		// term non selectable list exist so need to translate to new ids
		$importDty['dty_TermIDTreeNonSelectableIDs'] =  translateTermIDs($importDty['dty_TermIDTreeNonSelectableIDs'],"non-selectable"," detailType '".$importDty["dty_Name"]."' ID = $importDtyID");
	}
/*****DEBUG****///error_log("import dty $importDtyID 3".($error?"error":""));
	if(!$error && @$importDty['dty_PtrTargetRectypeIDs'] && $importDty['dty_PtrTargetRectypeIDs'] != '') {
		// Target Rectype list exist so need to translate to new ids
		$importDty['dty_PtrTargetRectypeIDs'] =  translateRtyIDs($importDty['dty_PtrTargetRectypeIDs'],'pointers',$importDty["dty_ID"]);
	}
/*****DEBUG****///error_log("import dty $importDtyID 4".($error?"error":""));

	if(!$error && @$importDty['dty_FieldSetRectypeID'] && $importDty['dty_FieldSetRectypeID'] != '') {
		// dty represents a base rectype so need to translate to local id
		$importDty['dty_FieldSetRectypeID'] =  translateRtyIDs("".$importDty['dty_FieldSetRectypeID'],'fieldsets',$importDty["dty_ID"]);
	}
/*****DEBUG****///error_log("import dty $importDtyID 5".($error?"error":""));


	if (!$error) {
		// Check wether the name is already in use. If so, add a number as suffix and find a name that is unused
		$detailTypeSuffix = 2;
		while(mysql_num_rows(mysql_query("select * from ".$targetDBName.".defDetailTypes where dty_Name = '".$importDty["dty_Name"]."'")) != 0) {
			makeLogEntry("Importing Field-type", $importDtyID, " '".$importDty["dty_Name"]."'. as '".$importDty["dty_Name"].$detailTypeSuffix)."'";
			$importDty["dty_Name"] = $importDty["dty_Name"] . $detailTypeSuffix;
			$detailTypeSuffix++;
		}

		// Change some detailtype fields to make it suitable for the new DB, and insert
		unset($importDty["dty_ID"]);
		$importDty["dty_DetailTypeGroupID"] = $importDtyGroupID;
		$importDty["dty_Name"] = mysql_real_escape_string($importDty["dty_Name"]);
		$importDty["dty_Documentation"] = mysql_real_escape_string($importDty["dty_Documentation"]);
		$importDty["dty_HelpText"] = mysql_real_escape_string($importDty["dty_HelpText"]);
		$importDty["dty_ExtendedDescription"] = mysql_real_escape_string($importDty["dty_ExtendedDescription"]);
		$importDty["dty_NameInOriginatingDB"] = mysql_real_escape_string($importDty["dty_NameInOriginatingDB"]);
		mysql_query("INSERT INTO ".$targetDBName.".defDetailTypes (".implode(", ",array_keys($importDty)).") VALUES ('".implode("', '",array_values($importDty))."')");
		// Write the insert action to $logEntry, and set $error to true if one occurred
		if(mysql_error()) {
			$error = true;
			makeLogEntry("<b>Error</b> Importing Field-type", $importDtyID, "MySQL error importing field type - ".mysql_error());
			break;
		} else {
			$importedDtyID = mysql_insert_id();
			makeLogEntry("Importing Field-type", $importDtyID, " '".$importDty["dty_Name"]."' as #$importedDtyID");
			return $importedDtyID;
		}
	}
}

// function that translates all rectype ids in the passed string to there local/imported value
function translateRtyIDs($strRtyIDs, $contextString, $forDtyID) {
	global $error, $importLog, $tempDBName, $sourceDBName, $targetDBName, $sourceDBID, $importRefdRectypes, $strictImport;
	if (!$strRtyIDs) {
		return "";
	}
/*****DEBUG****///error_log("translate rtyIDs $strRtyIDs");

	$outputRtyIDs = array();
	$rtyIDs = explode(",",$strRtyIDs);
	foreach($rtyIDs as $importRtyID) {
	// Get recordtype data that has to be imported
		$res = mysql_query("select * from ".$tempDBName.".defRecTypes where rty_ID = ".$importRtyID);
		if(mysql_num_rows($res) == 0) {
			makeLogEntry("<b>Warning</b> unrecognized Record-type", $importRtyID,
			" referenced by $contextString in field type #$forDtyID. value ignored");
			if ($strictImport){
				$error = true;
			}else{
				continue;
			}
			//IJ req DON't BOMB OUT return null; // missing rectype in importing DB
		} else {// get retypeID from temp copy of remoteDB's structure
			$importRty = mysql_fetch_assoc($res);
/*****DEBUG****///error_log("tran srcRTY  =  ".print_r($importRty,true));
		}

		// check if rectype already imported, if so return the local id.
		if(!$error && $importRty) {
			// change to global ID for lookup
			if(!$importRty["rty_OriginatingDBID"] || $importRty["rty_OriginatingDBID"] == 0 || $importRty["rty_OriginatingDBID"] == "") {
					$importRty["rty_OriginatingDBID"] = $sourceDBID;
					$importRty["rty_IDInOriginatingDB"] = $importRtyID;
					$importRty["rty_NameInOriginatingDB"] = $importRty["rty_Name"];
			}
			//lookup by conceptID rty in target DB
			$resRtyExist = mysql_query("select rty_ID from ".$targetDBName.".defRecTypes ".
							"where rty_OriginatingDBID = ".$importRty["rty_OriginatingDBID"].
							" AND rty_IDInOriginatingDB = ".$importRty["rty_IDInOriginatingDB"]);
			// Rectype is not in target DB so import it
			if(mysql_num_rows($resRtyExist) == 0 ) {
/*****DEBUG****///error_log("translateRtyIDS import rtyID - ".$importRty['rty_ID']);
				if ($importRefdRectypes) {
					$localRtyID = importRectype($importRty);
					$msg = "as #$localRtyID";
				}else{
					$msg = "Referenced ID ($localRtyID) not found. Not importing - 'no Recursion' is set!";
				}
			} else {
				$row = mysql_fetch_row($resRtyExist);
				$localRtyID = $row[0];
				$msg = " as #".$localRtyID; //found matching rectype entry in $targetDBName rectype ID =
			}
/*****DEBUG****///error_log($msgCat." $importRtyID to local ID $localRtyID ");
			if (!$error){
				if (!$importRefdRectypes) {
					makeLogEntry("Importing Record-type",$importRtyID, $msg);
				}else{
					makeLogEntry("Importing Record-type",$importRtyID, " referenced by $contextString in field type '$forDtyID'".$msg);
					copyRectypeIcon($sourceDBName, $importRtyID, $localRtyID);
					array_push($outputRtyIDs, $localRtyID); // store the local ID in output array
				}
			}
		}
		if ($error) {
			break;
		}
	}
	return implode(",", $outputRtyIDs); // return comma separated list of local RtyIDs
}

function importRectype($importRty) {
	global $error, $importLog, $tempDBName, $sourceDBName, $targetDBName, $sourceDBID;
	//was static $importRtyGroupID;
	$importRtyID = $importRty['rty_ID'];
/*****DEBUG****///error_log("import rtyID $importRtyID to  $targetDBName DB");

	// Get Imported  rectypeGroupID
	if(!$error){ // && !$importRtyGroupID) {

		//find group in source
		$query = "select * from ".$tempDBName.".defRecTypeGroups where rtg_ID = ".$importRty['rty_RecTypeGroupID'];

		$res = mysql_query($query);
		if(mysql_num_rows($res) == 0) {
			makeLogEntry("<b>Error</b> Creating Record-type Group", -1, " Can not find group #".$importRty['rty_RecTypeGroupID']);
			$error = true;
		}else{
			$rtyGroup_src = mysql_fetch_assoc($res);
			//find group by name in target
			$rtyGroup = mysql_query("select rtg_ID from ".$targetDBName.".defRecTypeGroups where rtg_Name = '".$rtyGroup_src['rtg_Name']."'");
			if(mysql_num_rows($rtyGroup) == 0) { //not found
				//add new one
				mysql_query("INSERT INTO ".$targetDBName.".defRecTypeGroups ".
						"(rtg_Name,rtg_Domain,rtg_Order, rtg_Description) ".
						"VALUES ('".$rtyGroup_src['rtg_Name']."','".$rtyGroup_src['rtg_Domain']."' , '".$rtyGroup_src['rtg_Order']."',".
								" '".$rtyGroup_src['rtg_Description']."')");

				if(mysql_error()) {
					$error = true;
					makeLogEntry("<b>Error</b> Creating Record-type Group", -1, ". Could not add record type group '".$rtyGroup_src['rtg_Name']."' - ".mysql_error());
				} else {
					$importRtyGroupID = mysql_insert_id();
					makeLogEntry("Creating Record-type Group", -1, " '".$rtyGroup_src['rtg_Name']."' as #$importRtyGroupID");
				}
			}else{
				$row = mysql_fetch_row($rtyGroup);
				$importRtyGroupID = $row[0];
				makeLogEntry("Using Record-type Group", -1, " '".$rtyGroup_src['rtg_Name']."' as #$importRtyGroupID");
			}
		}

/* ARTEM: old way with Imported group
		// Finded 'Imported' rectype group or create it if it doesn't exist
		$rtyGroup = mysql_query("select rtg_ID from ".$targetDBName.".defRecTypeGroups where rtg_Name = 'Imported'");

		if(mysql_num_rows($rtyGroup) == 0) {
			//not exist
			mysql_query("INSERT INTO ".$targetDBName.".defRecTypeGroups ".
						"(rtg_Name,rtg_Domain,rtg_Order, rtg_Description) ".
						"VALUES ('Imported','functionalgroup' , '999',".
								" 'This group contains all record types that were imported from external databases')");
		// Write the insert action to $logEntry, and set $error to true if one occurred

			if(mysql_error()) {
				$error = true;
				makeLogEntry("<b>Error</b> Creating Record-type Group", -1, ". Could not find record type group 'Imported' - ".mysql_error());
			} else {
				$importRtyGroupID = mysql_insert_id();
				makeLogEntry("Creating Record-type Group", -1, " 'Imported' as #$importRtyGroupID");
			}
		} else {

			$row = mysql_fetch_row($rtyGroup);
			$importRtyGroupID = $row[0];
			makeLogEntry("Using Record-type Group", -1, " 'Imported' as #$importRtyGroupID");
		}
*/
	}


/*****DEBUG****///error_log("import rty 3a");

	if(!$error) {
/*****DEBUG****///error_log("import rty 3aa");
		// get rectype Fields and check they are not already imported
		$recStructuresByDtyID = array();
		// get the rectypes structure
		$resRecStruct = mysql_query("select * from ".$tempDBName.".defRecStructure where rst_RecTypeID = ".$importRtyID);
		while($rtsFieldDef = mysql_fetch_assoc($resRecStruct)) {
			$importFieldDtyID = $rtsFieldDef['rst_DetailTypeID'];
			$recStructuresByDtyID[$importFieldDtyID] = $rtsFieldDef;

			// If this recstructure field has originating DBID 0 it's an original concept
			// need to set the origin DBID to the DB it is being imported from
			if($rtsFieldDef["rst_OriginatingDBID"] == 0 || $rtsFieldDef["rst_OriginatingDBID"] == "") {
				$rtsFieldDef["rst_OriginatingDBID"] = $sourceDBID;

				$rtsFieldDef["rst_IDInOriginatingDB"] = $rtsFieldDef["rst_ID"];
			}
			// check that field doesn't already exist
			$resRstExist = mysql_query("select rst_ID from ".$targetDBName.".defRecStructure ".
							"where rst_OriginatingDBID = ".$rtsFieldDef["rst_OriginatingDBID"].
							" AND rst_IDInOriginatingDB = ".$rtsFieldDef["rst_IDInOriginatingDB"]);
			if ( mysql_num_rows($resRstExist)) {
				makeLogEntry("<b>Error</b> Importing Field", $rtsFieldDef["rst_ID"], " '".$rtsFieldDef["rst_DisplayName"]."' already exists detail type #$importFieldDtyID rectype #$importRtyID");
				makeLogEntry("", -1, ". Originating DBID = ".$rtsFieldDef["rst_OriginatingDBID"]." Originating Field #".$rtsFieldDef["rst_IDInOriginatingDB"]);
//				$error = true;
			}
		}

/*****DEBUG****///error_log("import rty 3b");

		if(!$error) {	//import rectype
/*****DEBUG****///error_log("import rty 3bb");
			$recTypeSuffix = 2;
			while(mysql_num_rows(mysql_query("select * from ".$targetDBName.".defRecTypes where rty_Name = '".$importRty["rty_Name"]."'")) != 0) {
				$importRty["rty_Name"] = $importRty["rty_Name"] . $recTypeSuffix;
				makeLogEntry("Record type",$importRtyID, "Record type name used in the source DB already exist in the target DB($targetDBName) but with different concept code. Added suffix: ".$recTypeSuffix);
				$recTypeSuffix++;
			}

			// Change some recordtype fields to make it suitable for the new DB
			unset($importRty["rty_ID"]);
			$importRty["rty_RecTypeGroupID"] = $importRtyGroupID;
			$importRty["rty_Name"] = mysql_escape_string($importRty["rty_Name"]);
			$importRty["rty_Description"] = mysql_escape_string($importRty["rty_Description"]);
			$importRty["rty_Plural"] = mysql_escape_string($importRty["rty_Plural"]);
			$importRty["rty_NameInOriginatingDB"] = mysql_escape_string($importRty["rty_NameInOriginatingDB"]);
			$importRty["rty_ReferenceURL"] = mysql_escape_string($importRty["rty_ReferenceURL"]);
			$importRty["rty_AlternativeRecEditor"] = mysql_escape_string($importRty["rty_AlternativeRecEditor"]);

			// Insert recordtype
			mysql_query("INSERT INTO ".$targetDBName.".defRecTypes ".
						"(".implode(", ",array_keys($importRty)).") VALUES ".
						"('".implode("', '",array_values($importRty))."')");
			// Write the insert action to $logEntry, and set $error to true if one occurred
			if(mysql_error()) {
				$error = true;
/*****DEBUG****///error_log("import rty $importRtyID 3bbb  ". mysql_error());
				makeLogEntry("Importing Record-type", $importRtyID, "MySQL error importing record type - ".mysql_error());
			} else {
				$importedRecTypeID = mysql_insert_id();
				makeLogEntry("Importing Record-type", $importRtyID, " '".$importRty["rty_Name"]."' as #$importedRecTypeID");

				copyRectypeIcon($sourceDBName, $importRtyID, $importedRecTypeID);
			}
		}

		if(!$error) {
			// Import the structure for the recordtype imported
			foreach ( $recStructuresByDtyID as $dtyID => $rtsFieldDef) {
				// get import detailType for this field
				 $resDTY= mysql_query("select * from ".$tempDBName.".defDetailTypes where dty_ID = $dtyID");
				if(mysql_num_rows($resDTY) == 0) {
					$error = true;
/*****DEBUG****///error_log("import rty $importRtyID 3cc  dtyID = $dtyID not in source db ");
					makeLogEntry("<b>Error</b> Importing Field-type", $dtyID,
					" '".$rtsFieldDef['rst_DisplayName']."' for record type #".$rtsFieldDef['rst_RecTypeID']." not found in the source db. Please contact owner of $sourceDBName");
					return null; // missing detatiltype in importing DB
				} else {
					$importDty = mysql_fetch_assoc($resDTY);
					/*****DEBUG****///error_log("Import dty is  ".print_r($importDty,true));
				}

				// If detailtype has originating DBID 0, set it to the DBID from the DB it is being imported from
				if(!$importDty["dty_OriginatingDBID"] || $importDty["dty_OriginatingDBID"] == 0 || $importDty["dty_OriginatingDBID"] == "") {
					$importDty["dty_OriginatingDBID"] = $sourceDBID;
					$importDty["dty_IDInOriginatingDB"] = $importDty['dty_ID'];
					$importDty["dty_NameInOriginatingDB"] = $importDty['dty_Name'];
				}

				// Check to see if the detailType for this field exist in the target DB
				$resExistingDty = mysql_query("select dty_ID from ".$targetDBName.".defDetailTypes ".
										"where dty_OriginatingDBID = ".$importDty["dty_OriginatingDBID"].
										" AND dty_IDInOriginatingDB = ".$importDty["dty_IDInOriginatingDB"]);

				// Detailtype is not in target DB so import it
				if(mysql_num_rows($resExistingDty) == 0) {
/*****DEBUG****///error_log("import rty $importRtyID 4a  dtyID = ".$importDty['dty_ID']);
					$rtsFieldDef["rst_DetailTypeID"] = importDetailType($importDty);
/*****DEBUG****///error_log("import rty $importRtyID 4b  dtyID = ".$importDty['dty_ID']."->".$rtsFieldDef["rst_DetailTypeID"]);
				} else {
					$existingDtyID = mysql_fetch_array($resExistingDty);
					$rtsFieldDef["rst_DetailTypeID"] = $existingDtyID[0];
/*****DEBUG****///error_log("import rty $importRtyID 5  dtyID = ".$importDty['dty_ID']."=".$rtsFieldDef["rst_DetailTypeID"]);
				}

				if(!$error && @$rtsFieldDef['rst_FilteredJsonTermIDTree'] && $rtsFieldDef['rst_FilteredJsonTermIDTree'] != '') {
/*****DEBUG****///error_log("import rty $importRtyID 6  dtyID = ".$importDty['dty_ID']."->".$rtsFieldDef["rst_DetailTypeID"]." (".$rtsFieldDef['rst_FilteredJsonTermIDTree'].")");
					// term tree exist so need to translate to new ids
					$rtsFieldDef['rst_FilteredJsonTermIDTree'] =  translateTermIDs($rtsFieldDef['rst_FilteredJsonTermIDTree'],"filtered term tree"," field '".$rtsFieldDef['rst_DisplayName']."' detailTypeID = $dtyID in rectype '".$importRty["rty_Name"]."'");
				}

				if(!$error && @$rtsFieldDef['rst_TermIDTreeNonSelectableIDs'] && $rtsFieldDef['rst_TermIDTreeNonSelectableIDs'] != '') {
/*****DEBUG****///error_log("import rty $importRtyID 7  dtyID = ".$importDty['dty_ID']."->".$rtsFieldDef["rst_DetailTypeID"]);
					// term non selectable list exist so need to translate to new ids
/*****DEBUG****///error_log("non selectable = ". print_r($rtsFieldDef['rst_TermIDTreeNonSelectableIDs'],true));
					$rtsFieldDef['rst_TermIDTreeNonSelectableIDs'] = translateTermIDs($rtsFieldDef['rst_TermIDTreeNonSelectableIDs'],"non-selectable"," field '".$rtsFieldDef['rst_DisplayName']."' detailTypeID = $dtyID in rectype '".$importRty["rty_Name"]."'");
				}

				if(!$error && @$rtsFieldDef['rst_PtrFilteredIDs'] && $rtsFieldDef['rst_PtrFilteredIDs'] != '') {
/*****DEBUG****///error_log("import rty $importRtyID 8  dtyID = ".$importDty['dty_ID']."->".$rtsFieldDef["rst_PtrFilteredIDs"]);
					// Target Rectype list exist so need to translate to new ids
					$rtsFieldDef['rst_PtrFilteredIDs'] =  translateRtyIDs($rtsFieldDef['rst_PtrFilteredIDs'],'filtered pointers',$importDty["dty_ID"]);
				}
/*****DEBUG****///error_log("import rty $importRtyID 9  dtyID = ".$importDty['dty_ID']."->".$rtsFieldDef["rst_DetailTypeID"]);

				if(!$error) {
/*****DEBUG****///error_log("import rty $importRtyID 10  dtyID = ".$importDty['dty_ID']."->".$rtsFieldDef["rst_DetailTypeID"]);
					// Adjust values of the field structure for the imported recordtype
					$importRstID = $rtsFieldDef["rst_ID"];
					unset($rtsFieldDef["rst_ID"]);
					$rtsFieldDef["rst_RecTypeID"] = $importedRecTypeID;
					$rtsFieldDef["rst_DisplayName"] = mysql_escape_string($rtsFieldDef["rst_DisplayName"]);
					$rtsFieldDef["rst_DisplayHelpText"] = mysql_escape_string($rtsFieldDef["rst_DisplayHelpText"]);
					$rtsFieldDef["rst_DisplayExtendedDescription"] = mysql_escape_string($rtsFieldDef["rst_DisplayExtendedDescription"]);
					$rtsFieldDef["rst_DefaultValue"] = mysql_escape_string($rtsFieldDef["rst_DefaultValue"]);
					$rtsFieldDef["rst_DisplayHelpText"] = mysql_escape_string($rtsFieldDef["rst_DisplayHelpText"]);
					// Import the field structure for the imported recordtype
					mysql_query("INSERT INTO ".$targetDBName.".defRecStructure (".implode(", ",array_keys($rtsFieldDef)).") VALUES ('".implode("', '",array_values($rtsFieldDef))."')");
					// Write the insert action to $logEntry, and set $error to true if one occurred
					if(mysql_error()) {
						$error = true;
						makeLogEntry("<b>Error</b> Importing Field", $importRstID, " '".$rtsFieldDef["rst_DisplayName"]."' for record type '".$importRty["rty_Name"]."' - ".mysql_error());
						break;
					} else {
						makeLogEntry("Importing Field", mysql_insert_id(), " '".$rtsFieldDef["rst_DisplayName"]."' for record type '".$importRty["rty_Name"]."'");
					}
				}
				if ($error) {
					break;
				}
			}
			if (!$error) {
				return $importedRecTypeID;
			}
		}
		return null;
	}
}

//
// Copy record type icon from source to destination database
//
function copyRectypeIcon($sourceDBName, $importRtyID, $importedRecTypeID){

	$filename = HEURIST_DOCUMENT_ROOT."/HEURIST_FILESTORE/".$sourceDBName."/rectype-icons/".$importRtyID.".png";// BUG this is not always true and what about cross server

	if(file_exists($filename)){

		$target_filename = HEURIST_ICON_DIR.$importedRecTypeID.".png";

		if(file_exists($target_filename)){
			unlink($target_filename);
		}

		if (!copy($filename, $target_filename)) {
			makeLogEntry("<b>Warning</b> Importing Record-type", $importRtyID, " Can't copy icon ".$filename." to ".$target_filename);
		}
	}else{
		makeLogEntry("<b>Warning</b> Importing Record-type", $importRtyID, " icon does not exist");
	}
}

//
//
//
function getCompleteVocabulary($termId){
    global $tempDBName;
    
    $query = "select a.trm_ID as pID, b.trm_ID as cID, b.trm_ChildCount as cCnt
                from ".$tempDBName.".defTerms a
                    left join ".$tempDBName.".defTerms b on a.trm_ID = b.trm_ParentTermID
                where a.trm_ID = ".$termId;
                
    $res = mysql_query($query);    
    $terms = array();
   
    array_push($terms, $termId);

    // create array of parent => child arrays
    while ($row = mysql_fetch_assoc($res)) {
    
        if(!in_array($row['cID'], $terms)){
            array_push($terms, $row['cID']);
        }
        if($row['cCnt']>0){
            $terms = array_unique( array_merge( $terms , getCompleteVocabulary($row['cID']) ) );
        }
    }
    
    return $terms;
    
}
//
//
//
function getTopMostParentTerm($termId){
    global $tempDBName;

    $query = "select trm_ParentTermID from ".$tempDBName.".defTerms where trm_ID = ".$termId;

    $parentId = mysql_fetch_array(mysql_query($query));
    if($parentId && @$parentId[0]){
        return getTopMostParentTerm($parentId[0]);
    }else{
        return $termId;
    }
}

// function that translates all term ids in the passed string to there local/imported value
function translateTermIDs($formattedStringOfTermIDs, $contextString, $forEntryString) {
	global $error, $importLog, $tempDBName, $targetDBName, $sourceDBID,$importVocabs;
	if (!$formattedStringOfTermIDs || $formattedStringOfTermIDs == "") {
		return "";
	}
	makeLogEntry("Term Translation", -1, "Translating $contextString terms $formattedStringOfTermIDs for $forEntryString");
	$retJSonTermIDs = $formattedStringOfTermIDs;
    
    if("term tree"==$contextString){ //ARTEM: new way

        //new way    
        if(is_numeric($retJSonTermIDs)){ //this is vocabulary - take all children terms
            $termIDs = getCompleteVocabulary($retJSonTermIDs);
            
        }else{
            $temp = preg_replace("/[\{\}\",]/","",$formattedStringOfTermIDs);
            if (strrpos($temp,":") == strlen($temp)-1) {
                $temp = substr($temp,0, strlen($temp)-1);
            }
            $termIDs = explode(":",$temp);
            //$termTree = json_decode($retJSonTermIDs);
            if($importVocabs){
                $allterms = array();
                foreach ($termIDs as $importTermID){
                    if(!in_array($importTermID, $allterms)){
                        $parentID = getTopMostParentTerm($importTermID);                    
                        if(!in_array($parentID, $allterms)){
                            $allterms = array_unique( array_merge($allterms, getCompleteVocabulary($parentID)));
                        }
                    }
                }
                $termIDs = $allterms;
            }
        }
        
    }else{
    
	    if (strpos($retJSonTermIDs,"{")!== false) {
    /*****DEBUG****///error_log( "term tree string = ". $formattedStringOfTermIDs);
		    $temp = preg_replace("/[\{\}\",]/","",$formattedStringOfTermIDs);
		    if (strrpos($temp,":") == strlen($temp)-1) {
			    $temp = substr($temp,0, strlen($temp)-1);
		    }
		    $termIDs = explode(":",$temp);
	    } else {
    /*****DEBUG****///error_log( "term array string = ". $formattedStringOfTermIDs);
		    $temp = preg_replace("/[\[\]\"]/","",$formattedStringOfTermIDs);
		    $termIDs = explode(",",$temp);
	    }
    
    }


	// Import terms
	foreach ($termIDs as $importTermID) {
		// importTerm
		$translatedTermID = importTermID($importTermID);
		// check that the term imported correctly
		if ($translatedTermID == ""){
			return "";
		}
		//replace termID in string
		$retJSonTermIDs = preg_replace("/\"".$importTermID."\"/","\"".$translatedTermID."\"",$retJSonTermIDs);
	}
	// TODO: update the ChildCounts
	makeLogEntry("Term string", '', "Translated $formattedStringOfTermIDs to $retJSonTermIDs.");

	return $retJSonTermIDs;
}

function importTermID($importTermID) {
	global $error, $importLog, $tempDBName, $targetDBName, $sourceDBID;
    
/*****DEBUG****///error_log( "import termID = ". $importTermID);
	if (!$importTermID){
		return "";
	}
	//the source term we want to import
	$term = mysql_fetch_assoc(mysql_query("select * from ".$tempDBName.".defTerms where trm_ID = ".$importTermID));
	if(!$term || @$term['trm_ID'] != $importTermID) {
		// log the problem and return an empty string
		$error = true;
		makeLogEntry("<b>Error</b> Importing Term", $importTermID, " doesn't exist in source database");
		if(mysql_error()) {
			makeLogEntry("<b>Error</b> Importing Term", $importTermID, "SQL error importing term - ".mysql_error());
		}
		return "";
	} else {
		// If term has originating DBID 0, set it to the DBID from the DB it is being imported from
		if($term["trm_OriginatingDBID"] == 0 || $term["trm_OriginatingDBID"] == "") {
			$term["trm_OriginatingDBID"] = $sourceDBID;
			$term["trm_IDInOriginatingDB"] = $importTermID;
		}
		// Check wether this term is already imported
		$resExistingTrm = mysql_query("select trm_ID from ".$targetDBName.".defTerms ".
								"where trm_OriginatingDBID = ".$term["trm_OriginatingDBID"].
								" AND trm_IDInOriginatingDB = ".$term["trm_IDInOriginatingDB"]);
		// Term is in target DB so return translated term ID
		if(mysql_num_rows($resExistingTrm) > 0) {
			$existingTermID = mysql_fetch_array($resExistingTrm);
/*****DEBUG****///error_log( " existing term  = ". print_r($existingTermID,true));
			return $existingTermID[0];
		} else {
			// If parent term exist import  it first and use the save the parentID
			$sourceParentTermID = $term["trm_ParentTermID"];
			if (($sourceParentTermID != "") && ($sourceParentTermID != 0)) {
				$localParentTermID = importTermID($sourceParentTermID);
				// TODO: check that the term imported correctly
				if (($localParentTermID == "") || ($localParentTermID == 0)) {
					makeLogEntry("<b>Error</b> Importing Term", $sourceParentTermID, ". Error importing parent term for term ID =$importTermID .");
					return "";
				}else{
					$term["trm_ParentTermID"] = $localParentTermID;
					makeLogEntry("Importing Term", $sourceParentTermID, "as #$localParentTermID");
				}
			} else {
				unset($term["trm_ParentTermID"]);
			}
			$selfInverse = false;
			if($term["trm_InverseTermId"] == $term["trm_ID"]) {
				$selfInverse = true;
			}
			$inverseSourceTrmID = $term["trm_InverseTermId"];

			// Import the term
			unset($term["trm_ID"]);
			unset($term["trm_ChildCount"]);
			unset($term["trm_InverseTermId"]);
			$term["trm_AddedByImport"] = 1;
			$term["trm_Label"] = mysql_escape_string($term["trm_Label"]);
			$term["trm_Description"] = mysql_escape_string($term["trm_Description"]);
			$term["trm_NameInOriginatingDB"] = mysql_escape_string($term["trm_NameInOriginatingDB"]);
			mysql_query("INSERT INTO ".$targetDBName.".defTerms (".implode(", ",array_keys($term)).") ".
													"VALUES ('".implode("', '",array_values($term))."')");
			// Write the insert action to $logEntry, and set $error to true if one occurred
			if(mysql_error()) {
				$error = true;
				makeLogEntry("<b>Error</b> Importing Term", $importTermID, "MySQL error importing term -".mysql_error());
				return "";
			} else {
				$newTermID = mysql_insert_id();
				makeLogEntry("Importing Term", $importTermID, "as #$newTermID");
			}

			//handle inverseTerm if there is one
			if( $inverseSourceTrmID && $inverseSourceTrmID != "") {
				if($selfInverse) {
					$localInverseTermID = $newTermID;
				} else {
					$localInverseTermID = importTermID($inverseSourceTrmID);
				}
			// If there is an inverse term then update this term with it's local ID
				mysql_query("UPDATE ".$targetDBName.".defTerms SET trm_InverseTermId=".$localInverseTermID." where trm_ID=".$newTermID);
			}
			return $newTermID;
		}
	}
}

function makeLogEntry( $name = "unknown", $id = "", $msg = "no message" ) {
	global $importLog;
	array_push($importLog, array($name, $id, $msg));
}

// Checks whether passed $tempDBName contains 'temp_', and if so, deletes the database
function dropDB() {

	echo "<html><head><link rel=stylesheet href='../../common/css/global.css'></head>";
	echo "<body class='popup'><div style='text-align:center;font-weight:bold;font-size:1.3em;padding-top:10px'>";

	$message = "";
	$res = true;
	$tempDBName = $_GET["tempDBName"];
	$isTempDB = strpos($tempDBName, "temp_");
	if($isTempDB !== false) {
		mysql_query("drop database ".$tempDBName);
		if(!mysql_error()) {
			$message = "Temporary database was sucessfully deleted";
		} else {
			$message = "Error: Something went wrong deleting the temporary database";
			$res = false;
		}
	} else {
		$message = "Error: cannot delete a non-temporary database";
		$res = false;
	}

	echo $message."</div>";

	if($res){
		echo "<script>setTimeout(function(){window.close();}, 1500);</script>";
	}else{
		echo "<script>setTimeout(function(){
					if(window.frameElement.parentElement){
						var ele = window.frameElement.parentElement.parentElement;
                		var xy = top.HEURIST.util.suggestedPopupPlacement(300, 100);
                		ele.style.left = xy.x + 'px';
                		ele.style.top = xy.y + 'px';
                	}
				}, 200);</script>";
	}
	echo "</body></html>";

}
?>

