<?php
	/**
	* File: processAction.php Import a record type, with all its Record Structure, Field types, and terms, or crosswalk it with an existing record type
	* Juan Adriaanse 13 Apr 2011
	* @copyright 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo Show the import log once the importing is done, so user can see what happened, and change things where desired
	* @todo If an error occurres, delete everything that has been imported
	**/


require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

$targetDBName = @$_GET["importingTargetDBName"];
$sourceTempDBName = @$_GET["tempSourceDBName"];
$importRtyID = @$_GET["importRtyID"];
$sourceDBID = @$_GET["sourceDBID"];
$currentDate = date("d-m");
$error = false;
$importLog = array();

mysql_connection_db_insert($targetDBName);
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

function crosswalk() {
/*	$res = mysql_query("insert into `defCrosswalk` (`crw_SourcedbID`, `crw_SourceCode`, `crw_DefType`, `crw_LocalCode`) values ('".$_GET["crwSourceDBID"]."','".$_GET["importRtyID"]."','rectype','".$_GET["crwLocalCode"]."')");
	if(!mysql_error()) {
		echo "Successfully crosswalked rectypes (IDs: " . $_GET["importRtyID"] . " and " . $_GET["crwLocalCode"] . ")";
	} else {
		echo "Error: " . mysql_error();
	}
*/}

function import() {
	global $error, $importLog, $sourceTempDBName, $targetDBName, $sourceDBID, $importRtyID;
	//error_log("Import params are  ".print_r($_GET,true));
	if( !$sourceTempDBName || $sourceTempDBName === "" || !$targetDBName || $targetDBName === "" ||
		!$sourceDBID || !is_numeric($sourceDBID)|| !$importRtyID || !is_numeric($importRtyID)) {
		makeLogEntry("importParameters", -1, "One or more required import parameters not supplied or correct form ( ".
					"importingDBName={name of target DB} sourceDBID={reg number of source DB or 0} ".
					"importRtyID={numeric ID of rectype} tempDBName={temp db name where source DB type data are held}");
	$error = true;
	}
	importRectype($importRtyID);
	if(!$error) {
		$statusMsg = "";
		if(sizeof($importLog) > 0) {
			foreach($importLog as $logLine) {
				echo $statusMsg . $logLine[2] . ". Local ID is: " . $logLine[1] . "<br />";
			}
		}
		echo "<br />";
	} else if (substr(mysql_error(), 0, 9) == "Duplicate") {
		echo "prompt";
	} else {	//general error condition
		if (mysql_error()) {
			$statusMsg = "Error: " . mysql_error() . "<br />";
		} else  {
			$statusMsg = "Error:<br />";
		}
		if(sizeof($importLog) > 0) {
			foreach($importLog as $logLine) {
				if($logLine[1] == -1) {
					$statusMsg .= $logLine[2] . "<br />";
				}
			}
			$statusMsg .= "An error occurred trying to import the record type";
		}
		// TODO: Delete all information that has already been imported (retrieve from $importLog)
		echo $statusMsg;
	}
}

function importDetailType($importDty) {
	global $error, $importLog, $sourceTempDBName, $targetDBName, $sourceDBID;
	static $importDtyGroupID;

	if (!$importDtyGroupID) {
		// Create new group with todays date, which all detailtypes that the recordtype uses will be added to
		$dtyGroup = mysql_query("select dtg_ID from ".$targetDBName.".defDetailTypeGroups where dtg_Name = 'Imported'");
		if(mysql_num_rows($dtyGroup) == 0) {
			mysql_query("INSERT INTO ".$targetDBName.".defDetailTypeGroups VALUES ('','Imported', '999', 'This group contains all detailtypes that were imported from external databases')");
			// Write the insert action to $logEntry, and set $error to true if one occurred
			if(mysql_error()) {
				$error = true;
				makeLogEntry("detailtypeGroup", -1, mysql_error());
			} else {
				$importDtyGroupID = mysql_insert_id();
				makeLogEntry("detailtypeGroup", $newDtyGroupID, "New detail type group was created for this import");
			}
		} else {
			$row = mysql_fetch_row($dtyGroup);
			$importDtyGroupID = $row[0];
		}
	}

	if(!$error && @$importDty['dty_JsonTermIDTree'] && $importDty['dty_JsonTermIDTree'] != '') {
		// term tree exist so need to translate to new ids
		$importDty['dty_JsonTermIDTree'] =  translateTermIDs($importDty['dty_JsonTermIDTree']);
	}

	if(!$error && @$importDty['dty_TermIDTreeNonSelectableIDs'] && $importDty['dty_TermIDTreeNonSelectableIDs'] != '') {
		// term non selectable list exist so need to translate to new ids
		$importDty['dty_TermIDTreeNonSelectableIDs'] =  translateTermIDs($importDty['dty_TermIDTreeNonSelectableIDs']);
	}

	if(!$error && @$importDty['dty_PtrTargetRectypeIDs'] && $importDty['dty_PtrTargetRectypeIDs'] != '') {
		// Target Rectype list exist so need to translate to new ids
		$importDty['dty_PtrTargetRectypeIDs'] =  translateRtyIDs($importDty['dty_PtrTargetRectypeIDs']);
	}

	if(!$error && @$importDty['dty_FieldSetRectypeID'] && $importDty['dty_FieldSetRectypeID'] != '') {
		// dty represents a base rectype so need to translate to local id
		$importDty['dty_FieldSetRectypeID'] =  translateRtyIDs("".$importDty['dty_FieldSetRectypeID']);
	}


	if (!$error) {
		// Check wether the name is already in use. If so, add a number as suffix and find a name that is unused
		$detailTypeSuffix = 2;
		while(mysql_num_rows(mysql_query("select * from ".$targetDBName.".defDetailTypes where dty_Name = '".$importDty["dty_Name"]."'")) != 0) {
			$importDty["dty_Name"] = $importDty["dty_Name"] . $detailTypeSuffix;
			makeLogEntry("detailtype", 0, "Detailtype Name used in source DB already exist in target DB. Added suffix: ".$detailTypeSuffix);
			$detailTypeSuffix++;
		}

		// Change some detailtype fields to make it suitable for the new DB, and insert
		$importDtyID = $importDty["dty_ID"];
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
			makeLogEntry("detailtype", $importDtyID, mysql_error());
			break;
		} else {
			makeLogEntry("detailtype", mysql_insert_id(), "New detailtype imported");
		}
	}
}

// function that translates all rectype ids in the passed string to there local/imported value
function tranlateRtyIDs($strRtyIDs) {
	global $error, $importLog, $sourceTempDBName, $targetDBName, $sourceDBID;
	$outputRtyIDs = array();
	$rtyIDs = explode(",",$strRtyIDs);
	foreach($rtyIDs as $importRtyID) {
	// Get recordtype data that has to be imported
		$res = mysql_query("select * from ".$sourceTempDBName.".defRecTypes where rty_ID = ".$importRtyID);
		if(mysql_num_rows($res) == 0) {
			$error = true;
			makeLogEntry("rectype", $importRtyID, "Rectype was not found in source database, please contact owner of $sourceTempDBName");
			return null; // missing rectype in importing
		} else {
			$importRty = mysql_fetch_assoc($res);
			//error_log("Import entity is  ".print_r($importRty,true));
		}

		// check if rectype already imported, if so return the local id.
		if(!$error && $importRty) {
			if($importRty["rty_OriginatingDBID"] == 0 || $importRty["rty_OriginatingDBID"] == "") {
					$importRty["rty_OriginatingDBID"] = $sourceDBID;
					$importRty["rty_IDInOriginatingDB"] = $importRtyID;
					$importRty["rty_NameInOriginatingDB"] = $importRty["rty_Name"];
			}
			//lookup rty in target DB
		}
	}

}

function importRectype($importRtyID) {
	global $error, $importLog, $sourceTempDBName, $targetDBName, $sourceDBID;
	static $importRtyGroupID;
	// Get recordtype data that has to be imported
	$res = mysql_query("select * from ".$sourceTempDBName.".defRecTypes where rty_ID = ".$importRtyID);
	if(mysql_num_rows($res) == 0) {
		$error = true;
		makeLogEntry("rectype", $importRtyID, "Rectype was not found in $sourceTempDBName");
	} else {
		$importRty = mysql_fetch_assoc($res);
		//error_log("Import entity is  ".print_r($importRty,true));
	}

	// check if rectype already imported, if so return the local id.
	if(!$error && $importRty) {
		$origRtyID = $importRty["rty_ID"];
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
	}

	// Get Imported  rectypeGroupID
	if(!$error && !$importRtyGroupID) {
	// Finded 'Imported' rectype group or create it if it doesn't exist
		$rtyGroup = mysql_query("select rtg_ID from ".$targetDBName.".defRecTypeGroups where rtg_Name = 'Imported'");
	if(mysql_num_rows($rtyGroup) == 0) {
			mysql_query("INSERT INTO ".$targetDBName.".defRecTypeGroups VALUES ('','Imported','functionalgroup' , '999', 'This group contains all record types that were imported from external databases')");
		// Write the insert action to $logEntry, and set $error to true if one occurred
		if(mysql_error()) {
			$error = true;
			makeLogEntry("rectypeGroup", -1, mysql_error());
		} else {
				$importRtyGroupID = mysql_insert_id();
				makeLogEntry("rectypeGroup", $importRtyGroupID, "'Import' record type group was created");
		}
	} else {
		$row = mysql_fetch_row($rtyGroup);
			$importRtyGroupID = $row[0];
			makeLogEntry("rectypeGroup", $importRtyGroupID, "'Import' record type group was found");
		}
	}

	if(!$error) {
		// get rectype Fields and check they are not already imported
		$recStructuresByDtyID = array();
		// get the rectypes structure
		$resRecStruct = mysql_query("select * from ".$sourceTempDBName.".defRecStructure where rst_RecTypeID = ".$importRtyID);
		while($recFieldDef = mysql_fetch_assoc($resRecStruct)) {
			$recStructuresByDtyID[$recFieldDef['rst_DetailTypeID']] = $recFieldDef;
			// If this recstructure field has originating DBID 0, set it to the DBID from the DB it is being imported from
			if($rtsFieldDef["rst_OriginatingDBID"] == 0 || $rtsFieldDef["rst_OriginatingDBID"] == "") {
				$rtsFieldDef["rst_OriginatingDBID"] = $sourceDBID;
				$rtsFieldDef["rst_IDInOriginatingDB"] = $rtsFieldDef["rst_ID"];
			}
			// check that field don't  already exist
			$resRstExist = mysql_query("select rst_ID from ".$targetDBName.".defRecStructure ".
							"where rst_OriginatingDBID = ".$recFieldDef["rst_OriginatingDBID"].
							" AND rst_IDInOriginatingDB = ".$recFieldDef["rst_IDInOriginatingDB"]);
			if ( mysql_num_rows($resRstExist)) {
				makeLogEntry("rectypeStructure", $importRtyID, "Error: found existing rectype structure \"".$recFieldDef["rst_DisplayName"]."\"");
				$error = true;
		}
		}


		if(!$error) {	//import rectype
			// Change some recordtype fields to make it suitable for the new DB
			$importRty["rty_ID"] = "";
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
			makeLogEntry("rectype", -1, mysql_error());
		} else {
			$importedRecTypeID = mysql_insert_id();
				makeLogEntry("rectype", $importedRecTypeID, "Successfully imported recordtype with name: \"".$importRty["rty_Name"]."\"");
			}
		}

		if(!$error) {
			// Import the structure for the recordtype imported
			foreach ( $recStructuresByDtyID as $dtyID => $rtsFieldDef) {
				// get import detailType for this field
				$importDty = mysql_fetch_assoc(mysql_query("select * from ".$sourceTempDBName.".defDetailTypes where dty_ID = $dtyID"));

				// If detailtype has originating DBID 0, set it to the DBID from the DB it is being imported from
				if($importDty["dty_OriginatingDBID"] == 0 || $importDty["dty_OriginatingDBID"] == "") {
					$importDty["dty_OriginatingDBID"] = $sourceDBID;
					$importDty["dty_IDInOriginatingDB"] = $importDty['dty_ID'];
					$importDty["rty_NameInOriginatingDB"] = $importDty['dty_Name'];
				}

				// Check to see if the detailType for this field exist in the target DB
				$existingDty = mysql_query("select * from ".$targetDBName.".defDetailTypes where dty_OriginatingDBID = ".$importDty["dty_OriginatingDBID"]." AND dty_IDInOriginatingDB = ".$importDty["dty_IDInOriginatingDB"]);

				// Detailtype is not in target DB so import it
				if(mysql_num_rows($existingDty) == 0) {
					$rtsFieldDef["rst_DetailTypeID"] = importDetailType($importDty);
				}

				if(!$error && @$rtsFieldDef['rst_FilteredJsonTermIDTree'] && $rtsFieldDef['rst_FilteredJsonTermIDTree'] != '') {
					// term tree exist so need to translate to new ids
					$rtsFieldDef['rst_FilteredJsonTermIDTree'] =  translateTermIDs($rtsFieldDef['rst_FilteredJsonTermIDTree']);
				}

				if(!$error && @$rtsFieldDef['rst_TermIDTreeNonSelectableIDs'] && $rtsFieldDef['rst_TermIDTreeNonSelectableIDs'] != '') {
					// term non selectable list exist so need to translate to new ids
					$rtsFieldDef['rst_TermIDTreeNonSelectableIDs'] =  translateTermIDs($rtsFieldDef['rst_TermIDTreeNonSelectableIDs']);
				}

				if(!$error && @$rtsFieldDef['rst_PtrFilteredIDs'] && $rtsFieldDef['rst_PtrFilteredIDs'] != '') {
					// Target Rectype list exist so need to translate to new ids
					$rtsFieldDef['rst_PtrFilteredIDs'] =  translateRtyIDs($rtsFieldDef['rst_PtrFilteredIDs']);
				}

				if(!$error) {
					// Adjust values of the field structure for the imported recordtype
					$importRstID = $rtsFieldDef["rst_ID"];
					unset($rstFieldDef["rst_ID"]);
					$rtsFieldDef["rst_RecTypeID"] = $importedRecTypeID;
					$rtsFieldDef["rst_DisplayName"] = mysql_escape_string($rtsFieldDef["rst_DisplayName"]);
					$rtsFieldDef["rst_DisplayHelpText"] = mysql_escape_string($rtsFieldDef["rst_DisplayHelpText"]);
					$rtsFieldDef["rst_DisplayExtendedDescription"] = mysql_escape_string($rtsFieldDef["rst_DisplayExtendedDescription"]);
					$rtsFieldDef["rst_DefaultValue"] = mysql_escape_string($rtsFieldDef["rst_DefaultValue"]);
					$rtsFieldDef["rst_DisplayHelpText"] = mysql_escape_string($rtsFieldDef["rst_DisplayHelpText"]);
					// Import the field structure for the imported recordtype
					mysql_query("INSERT INTO ".$targetDBName.".defRecStructure (".implode(", ",array_keys($rtsFieldDef)).") VALUES ('".implode("', '",array_values($recStructuresByDtyID))."')");
				// Write the insert action to $logEntry, and set $error to true if one occurred
				if(mysql_error()) {
					$error = true;
						makeLogEntry("defRecStructure", $importRstID, mysql_error());
						break;
				} else {
						makeLogEntry("defRecStructure",  mysql_insert_id(), "New defRecStructure field imported");
					}
				}
				}
			}
					}
}


// function that translates all term ids in the passed string to there local/imported value
function tranlateTermIDs($formattedStringOfTermIDs) {

						// Import terms
	if($importDty["dty_JsonTermIDTree"] != "") {
							$terms = json_decode($detailType["dty_JsonTermIDTree"], true);
							$termIDs = array_keys($terms);
							// Go through every term that the detailtype uses
							foreach($termIDs as $termID) {
			$term = mysql_fetch_assoc(mysql_query("select * from ".$sourceTempDBName.".defTerms where trm_ID = ".$termID));
								if($term) {
									// If term has originating DBID 0, set it to the DBID from the DB it is being imported from
									if($term["trm_OriginatingDBID"] == 0 || $term["trm_OriginatingDBID"] == "") {
										$term["trm_OriginatingDBID"] = $_GET["crwSourceDBID"];
					$term["trm_IDInOriginatingDB"] = $_GET["importRtyID"];
									}
				// Check wether this term is already imported
				$lookupTrm = mysql_query("select * from ".$targetDBName.".defTerms where trm_OriginatingDBID = ".$term["trm_OriginatingDBID"]." AND trm_IDInOriginatingDB = ".$term["trm_IDInOriginatingDB"]);
									// Term is not owned
									if(mysql_num_rows($lookupTrm) == 0) {
										// Recursively import all parents in importTermParents, and save the parentID, as the local ID might be different from the original
										$newParentID = importTermParents($term);
										$inverseTermItsInverseTermID = "";

										if($term["trm_InverseTermId"] == $term["trm_ID"]) {
											$inverseSelf = true;
										} else { $inverseSelf = false; }

										// If the term has an inverse, look it up, and check wether it is already owned in local DB
										if(($term["trm_InverseTermId"] != "") && ($term["trm_InverseTermId"]) && !($inverseSelf)) {
						$inverseTerm = mysql_fetch_array(mysql_query("select * from ".$sourceTempDBName.".defTerms where trm_ID = ".$term["trm_InverseTermId"]), MYSQL_ASSOC);
						$lookupInverseTrm = mysql_query("select * from ".$targetDBName.".defTerms where trm_OriginatingDBID = ".$inverseTerm["trm_OriginatingDBID"]." AND trm_IDInOriginatingDB = ".$inverseTerm["trm_IDInOriginatingDB"]);
											// Inverse term is not owned
											if(mysql_num_rows($lookupInverseTrm) == 0) {
												// Import all the parents of the inverse term, and save the parent ID
												$newInverseTermParentID = importTermParents($inverseTerm);
												$inverseTerm["trm_ID"] = "";
												// Set the parentID for the inverseTerm which parents have just been imported to the (new) local ID
												if($newInverseTermParentID) {
													$inverseTerm["trm_ParentTermID"] = $newInverseTermParentID;
												}
												// Save the inverse it's inverse ID, for in case the inverse is not symmetric, then import the inverse term
												$inverseTermItsInverseTermID = $inverseTerm["trm_InverseTermId"];
												unset($inverseTerm["trm_InverseTermId"]);
												$inverseTerm["trm_Label"] = mysql_escape_string($inverseTerm["trm_Label"]);
												$inverseTerm["trm_NameInOriginatingDB"] = mysql_escape_string($inverseTerm["trm_NameInOriginatingDB"]);
												$inverseTerm["trm_Description"] = mysql_escape_string($inverseTerm["trm_Description"]);
							mysql_query("INSERT INTO ".$targetDBName.".defTerms (".implode(", ",array_keys($inverseTerm)).") VALUES ('".implode("', '",array_values($inverseTerm))."')");
												// Write the insert action to $logEntry, and set $error to true if one occurred
												if(mysql_error()) {
													$error = true;
													makeLogEntry("term", -1, mysql_error());
													break;
												} else {
													$inverseTrmID = mysql_insert_id();
													makeLogEntry("term", $inverseTrmID, "New term imported");
												}
												// If else, then the inverse term was already owned. Change the inverse ID to the local ID of the inverse
											} else {
												$localInverse = mysql_fetch_row($lookupInverseTrm);
												$inverseTrmID = $localInverse["trm_ID"];
											}
											$term["trm_InverseTermId"] = $inverseTrmID;
										} else {
											unset($term["trm_InverseTermId"]);
										}
										$remoteDBTermID = $term["trm_ID"];
										$term["trm_ID"] = "";
										// If the term has parents, set the parentID to the either newly imported parentID, or the existing parentID
										if($newParentID) {
											$term["trm_ParentTermID"] = $newParentID;
										}
										// Import the term
										$term["trm_Label"] = mysql_escape_string($term["trm_Label"]);
										$term["trm_Description"] = mysql_escape_string($term["trm_Description"]);
										$term["trm_NameInOriginatingDB"] = mysql_escape_string($term["trm_NameInOriginatingDB"]);
					mysql_query("INSERT INTO ".$targetDBName.".defTerms (".implode(", ",array_keys($term)).") VALUES ('".implode("', '",array_values($term))."')");
										// Write the insert action to $logEntry, and set $error to true if one occurred
										if(mysql_error()) {
											$error = true;
											makeLogEntry("term", -1, mysql_error());
											break;
										} else {
											$newTermID = mysql_insert_id();
											makeLogEntry("term", $newTermID, "New term imported");
										}
										// The inverse points to itself, so set it to the term's new ID
										if($inverseSelf) {
						mysql_query("UPDATE ".$targetDBName.".defTerms SET trm_InverseTermId=".$newTermID." where trm_ID=".$newTermID);
										}
										if($inverseTermItsInverseTermID != "" && $term["trm_InverseTermId"] != "") {
											// If the inverse is symmetrical, set the inverse of the earlier inserter term to the last inserted ID
											if($remoteDBTermID == $inverseTermItsInverseTermID) {
							mysql_query("UPDATE ".$targetDBName.".defTerms SET trm_InverseTermId=".$newTermID." where trm_ID=".$inverseTrmID);
											} else {
							$inverseTerm = mysql_fetch_array(mysql_query("select * from ".$sourceTempDBName.".defTerms where trm_ID = ".$inverseTermItsInverseTermID), MYSQL_ASSOC);
							$lookupInverseTrm = mysql_query("select * from ".$targetDBName.".defTerms where trm_OriginatingDBID = ".$inverseTerm["trm_OriginatingDBID"]." AND trm_IDInOriginatingDB = ".$inverseTerm["trm_IDInOriginatingDB"]);
												// Inverse term is not owned, fetch the inverseTerm, and it's parents (etcetera) recursively with fetchInverseTerm()
												if(mysql_num_rows($lookupInverseTrm) == 0) {
													$inverseTermItsInverseTermID = fetchInverseTerm($inverseTerm);
								mysql_query("UPDATE ".$targetDBName.".defTerms SET trm_InverseTermId=".$inverseTermItsInverseTermID." where trm_ID=".$inverseTrmID);
												} else {
													$localInverseTerm = mysql_fetch_row($lookupInverseTrm);
								mysql_query("UPDATE ".$targetDBName.".defTerms SET trm_InverseTermId=".$localInverseTerm["trm_ID"]." where trm_ID=".$inverseTrmID);
												}
											}
										}
									} else {
									}
								}
								$remoteDBTermID = null;
								$newTermID = null;
								$inverseTermItsInverseTermID = null;
								$inverseTrmID = null;
								$localInverse = null;
								$newInverseTermParentID = null;
								$lookupInverseTrm = null;
								$newParentID = null;
								$lookupTrm = null;
							}
						}

}

function importTermParents($term) {
	global $error, $importLog, $targetDBName;
	$sourceTempDBName = $_GET["tempDBName"];
	$importTerms = array();
	$parentFound = false;

	// While $term has a parentID which is not 0 or "", look if it's already in the local DB, if not, add to $importTerms.
	// If a $term or it's parent is already in the local DB, stop looking, as that means all of it's parents have been imported before
	while(($term["trm_ParentTermID"] != "") && ($term["trm_ParentTermID"] != 0) && (!$parentFound)) {
		$parentTerm = mysql_fetch_array(mysql_query("select * from ".$sourceTempDBName.".defTerms where trm_ID = ".$term["trm_ParentTermID"]), MYSQL_ASSOC);

		if($parentTerm["trm_OriginatingDBID"] == 0 || $parentTerm["trm_OriginatingDBID"] == "") {
			$parentTerm["trm_OriginatingDBID"] = $_GET["crwSourceDBID"];
			$parentTerm["trm_IDInOriginatingDB"] = $_GET["importRtyID"];
		}
		$lookupTrm = mysql_query("select * from ".$targetDBName.".defTerms where trm_OriginatingDBID = ".$parentTerm["trm_OriginatingDBID"]." AND trm_IDInOriginatingDB = ".$parentTerm["trm_IDInOriginatingDB"]);

		if(mysql_num_rows($lookupTrm) == 0) {
			array_push($importTerms, $parentTerm);
			$term = $parentTerm;
		} else {
			$parentFound = true;
		}
	}
	// If it found a parent in the local DB, save it's local ID, and add it to the child
	$newLastTermID = null;
	if($parentFound) {
		$topParent = mysql_fetch_array($lookupTrm);
		$newLastTermID = $topParent["trm_ID"];
	}
	// Go through list of terms that need to be imported
	while(sizeof($importTerms) > 0) {
		$lastTerm = array_pop($importTerms);
		// Set parentID to previously imported ID, locally found parent, or unset if it's a root term
		if(!$newLastTermID) {
			unset($lastTerm['trm_ParentTermID']);
		} else {
			$lastTerm["trm_ParentTermID"] = $newLastTermID;
		}
		// Save inverseID to set after import, and possibly import that inverse term first if it's not in the local DB yet
		$newLastInverseID = $lastTerm["trm_InverseTermId"];
		$inverseTermItsInverseTermID = "";
		$inverseSelf = false;
		if($newLastInverseID == $lastTerm["trm_ID"]) {
			$inverseSelf = true;
		} else if(!$newLastInverseID || ($newLastInverseID == "") || ($newLastInverseID == 0)) {
			unset($lastTerm["trm_InverseTermId"]);
			// Term has an inverse, and it does not point to itself
		} else {
			$inverseTerm = mysql_fetch_array(mysql_query("select * from ".$sourceTempDBName.".defTerms where trm_ID = ".$newLastInverseID), MYSQL_ASSOC);
			$lookupInverseTrm = mysql_query("select * from ".$targetDBName.".defTerms where trm_OriginatingDBID = ".$inverseTerm["trm_OriginatingDBID"]." AND trm_IDInOriginatingDB = ".$inverseTerm["trm_IDInOriginatingDB"]);
			// Inverse term is not in local DB yet
			if(mysql_num_rows($lookupInverseTrm) == 0) {
				// Get the inverse term it's parents, and set it's parentID
				$newInverseTermParentID = importTermParents($inverseTerm);
				$inverseTerm["trm_ID"] = "";
				$inverseTerm["trm_ParentTermID"] = $newInverseTermParentID;
				$inverseTermItsInverseTermID = $inverseTerm["trm_InverseTermId"];
				unset($inverseTerm["trm_InverseTermId"]);
				$inverseTerm["trm_Label"] = mysql_escape_string($inverseTerm["trm_Label"]);
				$inverseTerm["trm_NameInOriginatingDB"] = mysql_escape_string($inverseTerm["trm_NameInOriginatingDB"]);
				$inverseTerm["trm_Description"] = mysql_escape_string($inverseTerm["trm_Description"]);
				// Insert the inverse term, with temporary unset inverseID, and the right updated parentID
				mysql_query("INSERT INTO ".$targetDBName.".defTerms (".implode(", ",array_keys($inverseTerm)).") VALUES ('".implode("', '",array_values($inverseTerm))."')");
				// Write the insert action to $logEntry, and set $error to true if one occurred
				if(mysql_error()) {
					$error = true;
					makeLogEntry("term", -1, mysql_error());
					break;
				} else {
					$inverseTrmID = mysql_insert_id();
					makeLogEntry("term", $inverseTrmID, "New parent term imported");
				}
			} else {
				$localInverse = mysql_fetch_row($lookupInverseTrm);
				$inverseTrmID = $localInverse["trm_ID"];
			}
			$lastTerm["trm_InverseTermId"] = $inverseTrmID;
		}
		$remoteDBTermID = $lastTerm["trm_ID"];
		$lastTerm["trm_ID"] = "";
		$lastTerm["trm_Label"] = mysql_escape_string($lastTerm["trm_Label"]);
		$lastTerm["trm_NameInOriginatingDB"] = mysql_escape_string($lastTerm["trm_NameInOriginatingDB"]);
		$lastTerm["trm_Description"] = mysql_escape_string($lastTerm["trm_Description"]);
		// Insert the term with the right values for parentID and inverseID
		mysql_query("INSERT INTO ".$targetDBName.".defTerms (".implode(", ",array_keys($lastTerm)).") VALUES ('".implode("', '",array_values($lastTerm))."')");
		// Write the insert action to $logEntry, and set $error to true if one occurred
		if(mysql_error()) {
			$error = true;
			makeLogEntry("term", -1, mysql_error());
			break;
		} else {
			$newLastTermID = mysql_insert_id();
			makeLogEntry("term", $newLastTermID, "New parent term imported");
		}
		// Inverse ID points to itself, so set to it's own local ID
		if($inverseSelf) {
			mysql_query("UPDATE ".$targetDBName.".defTerms SET trm_InverseTermId=".$newLastTermID." where trm_ID=".$newLastTermID);
		}
		// If the inverseTerm had an inverse term had an inverseID
		if($inverseTermItsInverseTermID != "" && $term["trm_InverseTermId"] != "") {
			// If the inverse is symetrical, set the inverse of the earlier inserter term to the last inserted ID
			if($remoteDBTermID == $inverseTermItsInverseTermID) {
				mysql_query("UPDATE ".$targetDBName.".defTerms SET trm_InverseTermId=".$newLastTermID." where trm_ID=".$inverseTrmID);
			} else {
				$inverseTerm = mysql_fetch_array(mysql_query("select * from ".$sourceTempDBName.".defTerms where trm_ID = ".$inverseTermItsInverseTermID), MYSQL_ASSOC);
				$lookupInverseTrm = mysql_query("select * from ".$targetDBName.".defTerms where trm_OriginatingDBID = ".$inverseTerm["trm_OriginatingDBID"]." AND trm_IDInOriginatingDB = ".$inverseTerm["trm_IDInOriginatingDB"]);
				// Inverse term is not owned, does not point to itself, and is not symmetrical
				if(mysql_num_rows($lookupInverseTrm) == 0) {
					// Fetch the inverse it's inverseTerm, and all its parents recursively in fetchInverseTerm()
					$inverseTermItsInverseTermID = fetchInverseTerm($inverseTerm);
					mysql_query("UPDATE ".$targetDBName.".defTerms SET trm_InverseTermId=".$inverseTermItsInverseTermID." where trm_ID=".$inverseTrmID);
				} else {
					$localInverseTerm = mysql_fetch_row($lookupInverseTrm);
					mysql_query("UPDATE ".$targetDBName.".defTerms SET trm_InverseTermId=".$localInverseTerm["trm_ID"]." where trm_ID=".$inverseTrmID);
				}
			}
		}
	}
	return $newLastTermID;
}

function fetchInverseTerm($term) {
	global $error, $importLog, $targetDBName;
	$sourceTempDBName = $_GET["tempDBName"];
	// Get all the term's parents, and set the ID to the parentID
	$parentTermID = importTermParents($term);
	$term["trm_ID"] = "";
	if($parentTermID) {
		$term["trm_ParentTermID"] = $parentTermID;
	} else {
		unset($term["trm_ParentTermID"]);
	}
	$term["trm_Label"] = mysql_escape_string($term["trm_Label"]);
	$term["trm_NameInOriginatingDB"] = mysql_escape_string($term["trm_NameInOriginatingDB"]);
	$term["trm_Description"] = mysql_escape_string($term["trm_Description"]);
	// If $term does not have an inverse, unset the inverseID field, and insert the term
	if(($term["trm_InverseTermId"] == "") || !($term["trm_InverseTermId"])) {
		unset($term["trm_InverseTermId"]);
		mysql_query("INSERT INTO ".$targetDBName.".defTerms (".implode(", ",array_keys($term)).") VALUES ('".implode("', '",array_values($term))."')");
		if(mysql_error()) {
			$error = true;
			makeLogEntry("term", -1, mysql_error());
			break;
		} else {
			$newInvID = mysql_insert_id();
			makeLogEntry("term", $inverseTrmID, "New inverse term imported");
		}
		return $newInvID;
		// The term has an inverse
	} else {
		$inverseTerm = mysql_fetch_array(mysql_query("select * from ".$sourceTempDBName.".defTerms where trm_ID = ".$newLastInverseID), MYSQL_ASSOC);
		$lookupInverseTrm = mysql_query("select * from ".$targetDBName.".defTerms where trm_OriginatingDBID = ".$inverseTerm["trm_OriginatingDBID"]." AND trm_IDInOriginatingDB = ".$inverseTerm["trm_IDInOriginatingDB"]);
		// The inverse term is not in the local DB
		if(mysql_num_rows($lookupInverseTrm) == 0) {
			// Fetch the term it's inverseTerm and all it's parents, recursively in fetchInverseTerm()
			$inverseTrmID = fetchInverseTerm($inverseTerm);
			$term["trm_InverseTermId"] = $inverseTrmID;
			// Insert the inverseTerm
			mysql_query("INSERT INTO ".$targetDBName.".defTerms (".implode(", ",array_keys($term)).") VALUES ('".implode("', '",array_values($term))."')");
			// Write the insert action to $logEntry, and set $error to true if one occurred
			if(mysql_error()) {
				$error = true;
				makeLogEntry("term", -1, mysql_error());
				break;
			} else {
				$newInvID = mysql_insert_id();
				makeLogEntry("term", $inverseTrmID, "New inverse term imported");
			}
			return $newInvID;
			// The inverse term is in the local DB, and therefore it's inverse and parents as well. Set the right inverseID, and insert the term
		} else {
			$localInverse = mysql_fetch_row($lookupInverseTrm);
			$inverseTrmID = $localInverse["trm_ID"];
			$term["trm_InverseTermId"] = $inverseTrmID;
			mysql_query("INSERT INTO ".$targetDBName.".defTerms (".implode(", ",array_keys($term)).") VALUES ('".implode("', '",array_values($term))."')");
			// Write the insert action to $logEntry, and set $error to true if one occurred
			if(mysql_error()) {
				$error = true;
				makeLogEntry("term", -1, mysql_error());
				break;
			} else {
				$newInvID = mysql_insert_id();
				makeLogEntry("term", $inverseTrmID, "New inverse term imported");
			}
			return $newInvID;
		}
	}
}

function makeLogEntry( $name = "unknown", $id = "", $msg = "no message" ) {
	global $importLog;
	array_push($importLog, array($name, $id, $msg));
}
// Checks wether passed $sourceTempDBName contains 'temp_', and if so, deletes the database
function dropDB() {
	$sourceTempDBName = $_GET["tempDBName"];
	$isTempDB = strpos($sourceTempDBName, "temp_");
	if($isTempDB !== false) {
		mysql_query("drop database ".$sourceTempDBName);
		if(!mysql_error()) {
			echo "Temporary database was sucessfully deleted";
			return true;
		} else {
			echo "Error: Something went wrong deleting the temporary database";
			return false;
		}
	} else {
		echo "Error: cannot delete a non-temporary database";
		return false;
	}
}
?>
