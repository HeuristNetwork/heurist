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

$action = $_GET["action"];
$importingDB = $_GET["importingDB"];

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

mysql_connection_db_insert($importingDB);
switch($action) {
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
	$res = mysql_query("insert into `defCrosswalk` (`crw_SourcedbID`, `crw_SourceCode`, `crw_DefType`, `crw_LocalCode`) values ('".$_GET["crwSourceDBID"]."','".$_GET["crwSourceCode"]."','rectype','".$_GET["crwLocalCode"]."')");
	if(!mysql_error()) {
		echo "Successfully crosswalked rectypes (IDs: " . $_GET["crwSourceCode"] . " and " . $_GET["crwLocalCode"] . ")";
	} else {
		echo "Error: " . mysql_error();
	}
}

$error = false;
$importLog = array();
function import() {
	global $error, $importLog, $importingDB;
	$error = false;
	$importLog = array();
	$tempDBName = $_GET["tempDBName"];
	$currentDate = date("d-m");

    error_log("Temporary SDB name is: ".$tempDBName);

	// Get recordtype data that has to be imported
	$entity = mysql_fetch_array(mysql_query("select * from ".$tempDBName.".defRecTypes where rty_ID = ".$_GET["crwSourceCode"]), MYSQL_ASSOC);

	// Create new group with todays date, which the recordtype will be added to
	$rtyGroup = mysql_query("select rtg_ID from ".$importingDB.".defRecTypeGroups where rtg_Name = 'Imported'");
	if(mysql_num_rows($rtyGroup) == 0) {
		mysql_query("INSERT INTO ".$importingDB.".defRecTypeGroups VALUES ('','Imported','functionalgroup' , '999', 'This group contains all record types that were imported from external databases')");
		// Write the insert action to $logEntry, and set $error to true if one occurred
		if(mysql_error()) {
			$error = true;
			$logEntry = array("rectypeGroup", -1, mysql_error());
			array_push($importLog, $logEntry);
		} else {
			$newRtyGroupID = mysql_insert_id();
			$logEntry = array("rectypeGroup", $newRtyGroupID, "New record type group was created for this import");
			array_push($importLog, $logEntry);
		}
	} else {
		$row = mysql_fetch_row($rtyGroup);
		$newRtyGroupID = $row[0];
	}

	if(!$error) {
		// Change some recordtype fields to make it suitable for the new DB
		$entity["rty_ID"] = "";
		if($_GET["replaceRecTypeName"] != "") {
			$entity["rty_Name"] = $_GET["replaceRecTypeName"];
		}
		if($entity["rty_OriginatingDBID"] == 0 || $entity["rty_OriginatingDBID"] == "") {
			$entity["rty_OriginatingDBID"] = $_GET["crwSourceDBID"];
			$entity["rty_IDInOriginatingDB"] = $_GET["crwSourceCode"];
		}
		$entity["rty_RecTypeGroupID"] = $newRtyGroupID;
		// Insert recordtype
		$entity["rty_Name"] = mysql_escape_string($entity["rty_Name"]);
		$entity["rty_Description"] = mysql_escape_string($entity["rty_Description"]);
		$entity["rty_Plural"] = mysql_escape_string($entity["rty_Plural"]);
		$entity["rty_NameInOriginatingDB"] = mysql_escape_string($entity["rty_NameInOriginatingDB"]);
		$entity["rty_ReferenceURL"] = mysql_escape_string($entity["rty_ReferenceURL"]);
		$entity["rty_AlternativeRecEditor"] = mysql_escape_string($entity["rty_AlternativeRecEditor"]);
		mysql_query("INSERT INTO ".$importingDB.".defRecTypes (".implode(", ",array_keys($entity)).") VALUES ('".implode("', '",array_values($entity))."')");
		// Write the insert action to $logEntry, and set $error to true if one occurred
		if(mysql_error()) {
			$error = true;
			$logEntry = array("rectype", -1, mysql_error());
			array_push($importLog, $logEntry);
		} else {
			$importedRecTypeID = mysql_insert_id();
			$logEntry = array("rectype", $importedRecTypeID, "Successfully imported recordtype with name: \"".$entity["rty_Name"]."\"");
			array_push($importLog, $logEntry);
		}

		if(!$error) {
			// Create new group with todays date, which all detailtypes that the recordtype uses will be added to
			$dtyGroup = mysql_query("select dtg_ID from ".$importingDB.".defDetailTypeGroups where dtg_Name = 'Imported'");
			if(mysql_num_rows($dtyGroup) == 0) {
				mysql_query("INSERT INTO ".$importingDB.".defDetailTypeGroups VALUES ('','Imported', '999', 'This group contains all detailtypes that were imported from external databases')");
				// Write the insert action to $logEntry, and set $error to true if one occurred
				if(mysql_error()) {
					$error = true;
					$logEntry = array("detailtypeGroup", -1, mysql_error());
					array_push($importLog, $logEntry);
				} else {
					$newDtyGroupID = mysql_insert_id();
					$logEntry = array("detailtypeGroup", $newDtyGroupID, "New detail type group was created for this import");
					array_push($importLog, $logEntry);
				}
			} else {
				$row = mysql_fetch_row($dtyGroup);
				$newDtyGroupID = $row[0];
			}
			if(!$error) {
			// Get the structure for the recordtype that will be imported
			$recStructure = mysql_query("select * from ".$tempDBName.".defRecStructure where rst_RecTypeID = ".$_GET["crwSourceCode"]);
				while($recStructureEntity = mysql_fetch_assoc($recStructure)) {
					// Import all detailtypes that the recordtype uses
					$detailType = mysql_fetch_array(mysql_query("select * from ".$tempDBName.".defDetailTypes where dty_ID = ".$recStructureEntity["rst_DetailTypeID"]), MYSQL_ASSOC);

					// If detailtype has originating DBID 0, set it to the DBID from the DB it is being imported from
					if($detailType["dty_OriginatingDBID"] == 0 || $detailType["dty_OriginatingDBID"] == "") {
						$detailType["dty_OriginatingDBID"] = $_GET["crwSourceDBID"];
						$detailType["dty_IDInOriginatingDB"] = $_GET["crwSourceCode"];
					}
					// Check wether this detailtype is already owned
					$lookupDty = mysql_query("select * from ".$importingDB.".defDetailTypes where dty_OriginatingDBID = ".$detailType["dty_OriginatingDBID"]." AND dty_IDInOriginatingDB = ".$detailType["dty_IDInOriginatingDB"]);

					// Detailtype is not owned
					if(mysql_num_rows($lookupDty) == 0) {

						// Check wether the name is already in use. If so, add a number as suffix
						$detailTypeSuffix = 2;
						while(mysql_num_rows(mysql_query("select * from ".$importingDB.".defDetailTypes where dty_Name = '".$detailType["dty_Name"]."'")) != 0) {
							$detailType["dty_Name"] = $detailType["dty_Name"] . $detailTypeSuffix;
							$logEntry = array("detailtype", 0, "Detailtype used in source DB was already in use. Added suffix: ".$detailTypeSuffix);
							array_push($importLog, $logEntry);
							$detailTypeSuffix++;
						}

						// Import terms
						if($detailType["dty_JsonTermIDTree"] != "") {
							$terms = json_decode($detailType["dty_JsonTermIDTree"], true);
							$termIDs = array_keys($terms);
							// Go through every term that the detailtype uses
							foreach($termIDs as $termID) {
								$term = mysql_fetch_array(mysql_query("select * from ".$tempDBName.".defTerms where trm_ID = ".$termID), MYSQL_ASSOC);
								if($term) {
									// If term has originating DBID 0, set it to the DBID from the DB it is being imported from
									if($term["trm_OriginatingDBID"] == 0 || $term["trm_OriginatingDBID"] == "") {
										$term["trm_OriginatingDBID"] = $_GET["crwSourceDBID"];
										$term["trm_IDInOriginatingDB"] = $_GET["crwSourceCode"];
									}
									// Check wether this term is already owned
									$lookupTrm = mysql_query("select * from ".$importingDB.".defTerms where trm_OriginatingDBID = ".$term["trm_OriginatingDBID"]." AND trm_IDInOriginatingDB = ".$term["trm_IDInOriginatingDB"]);
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
											$inverseTerm = mysql_fetch_array(mysql_query("select * from ".$tempDBName.".defTerms where trm_ID = ".$term["trm_InverseTermId"]), MYSQL_ASSOC);
											$lookupInverseTrm = mysql_query("select * from ".$importingDB.".defTerms where trm_OriginatingDBID = ".$inverseTerm["trm_OriginatingDBID"]." AND trm_IDInOriginatingDB = ".$inverseTerm["trm_IDInOriginatingDB"]);
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
												mysql_query("INSERT INTO ".$importingDB.".defTerms (".implode(", ",array_keys($inverseTerm)).") VALUES ('".implode("', '",array_values($inverseTerm))."')");
												// Write the insert action to $logEntry, and set $error to true if one occurred
												if(mysql_error()) {
													$error = true;
													$logEntry = array("term", -1, mysql_error());
													array_push($importLog, $logEntry);
													break;
												} else {
													$inverseTrmID = mysql_insert_id();
													$logEntry = array("term", $inverseTrmID, "New term imported");
													array_push($importLog, $logEntry);
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
										mysql_query("INSERT INTO ".$importingDB.".defTerms (".implode(", ",array_keys($term)).") VALUES ('".implode("', '",array_values($term))."')");
										// Write the insert action to $logEntry, and set $error to true if one occurred
										if(mysql_error()) {
											$error = true;
											$logEntry = array("term", -1, mysql_error());
											array_push($importLog, $logEntry);
											break;
										} else {
											$newTermID = mysql_insert_id();
											$logEntry = array("term", $newTermID, "New term imported");
											array_push($importLog, $logEntry);
										}
										// The inverse points to itself, so set it to the term's new ID
										if($inverseSelf) {
											mysql_query("UPDATE ".$importingDB.".defTerms SET trm_InverseTermId=".$newTermID." where trm_ID=".$newTermID);
										}
										if($inverseTermItsInverseTermID != "" && $term["trm_InverseTermId"] != "") {
											// If the inverse is symmetrical, set the inverse of the earlier inserter term to the last inserted ID
											if($remoteDBTermID == $inverseTermItsInverseTermID) {
												mysql_query("UPDATE ".$importingDB.".defTerms SET trm_InverseTermId=".$newTermID." where trm_ID=".$inverseTrmID);
											} else {
												$inverseTerm = mysql_fetch_array(mysql_query("select * from ".$tempDBName.".defTerms where trm_ID = ".$inverseTermItsInverseTermID), MYSQL_ASSOC);
												$lookupInverseTrm = mysql_query("select * from ".$importingDB.".defTerms where trm_OriginatingDBID = ".$inverseTerm["trm_OriginatingDBID"]." AND trm_IDInOriginatingDB = ".$inverseTerm["trm_IDInOriginatingDB"]);
												// Inverse term is not owned, fetch the inverseTerm, and it's parents (etcetera) recursively with fetchInverseTerm()
												if(mysql_num_rows($lookupInverseTrm) == 0) {
													$inverseTermItsInverseTermID = fetchInverseTerm($inverseTerm);
													mysql_query("UPDATE ".$importingDB.".defTerms SET trm_InverseTermId=".$inverseTermItsInverseTermID." where trm_ID=".$inverseTrmID);
												} else {
													$localInverseTerm = mysql_fetch_row($lookupInverseTrm);
													mysql_query("UPDATE ".$importingDB.".defTerms SET trm_InverseTermId=".$localInverseTerm["trm_ID"]." where trm_ID=".$inverseTrmID);
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

						// Change some detailtype fields to make it suitable for the new DB, and insert
						$detailType["dty_ID"] = "";
						$detailType["dty_DetailTypeGroupID"] = $newDtyGroupID;
						$detailType["dty_Name"] = mysql_escape_string($detailType["dty_Name"]);
						$detailType["dty_Documentation"] = mysql_escape_string($detailType["dty_Documentation"]);
						$detailType["dty_HelpText"] = mysql_escape_string($detailType["dty_HelpText"]);
						$detailType["dty_ExtendedDescription"] = mysql_escape_string($detailType["dty_ExtendedDescription"]);
						$detailType["dty_NameInOriginatingDB"] = mysql_escape_string($detailType["dty_NameInOriginatingDB"]);
						mysql_query("INSERT INTO ".$importingDB.".defDetailTypes (".implode(", ",array_keys($detailType)).") VALUES ('".implode("', '",array_values($detailType))."')");
						// Write the insert action to $logEntry, and set $error to true if one occurred
						if(mysql_error()) {
							$error = true;
							$logEntry = array("detailtype", -1, mysql_error());
							array_push($importLog, $logEntry);
							break;
						} else {
							$lastImportedDetailTypeID = mysql_insert_id();
							$logEntry = array("detailtype", $lastImportedDetailTypeID, "New detailtype imported");
							array_push($importLog, $logEntry);
						}
					} else {
						$row = mysql_fetch_row($lookupDty);
						$lastImportedDetailTypeID = $row[0];
					}

					if(!$error) {
						// If this recstructure entity has originating DBID 0, set it to the DBID from the DB it is being imported from
						if($recStructureEntity["rst_OriginatingDBID"] == 0 || $recStructureEntity["rst_OriginatingDBID"] == "") {
							$recStructureEntity["rst_OriginatingDBID"] = $_GET["crwSourceDBID"];
							$recStructureEntity["rst_IDInOriginatingDB"] = $_GET["crwSourceCode"];
						}
						// Check wether this recstructure entity is already owned
						$lookupRst = mysql_query("select * from ".$importingDB.".defRecStructure where rst_OriginatingDBID = ".$recStructureEntity["rst_OriginatingDBID"]." AND rst_IDInOriginatingDB = ".$recStructureEntity["rst_IDInOriginatingDB"]);

						// Import the structure that the recordtype uses
						if(mysql_num_rows($lookupRst) == 0) {
							$recStructureEntity["rst_ID"] = "";
							$recStructureEntity["rst_RecTypeID"] = $importedRecTypeID;
							$recStructureEntity["rst_DetailTypeID"] = $lastImportedDetailTypeID;
							$recStructureEntity["rst_DisplayName"] = mysql_escape_string($recStructureEntity["rst_DisplayName"]);
							$recStructureEntity["rst_DisplayHelpText"] = mysql_escape_string($recStructureEntity["rst_DisplayHelpText"]);
							$recStructureEntity["rst_DisplayExtendedDescription"] = mysql_escape_string($recStructureEntity["rst_DisplayExtendedDescription"]);
							$recStructureEntity["rst_DefaultValue"] = mysql_escape_string($recStructureEntity["rst_DefaultValue"]);
							$recStructureEntity["rst_DisplayHelpText"] = mysql_escape_string($recStructureEntity["rst_DisplayHelpText"]);
							mysql_query("INSERT INTO ".$importingDB.".defRecStructure (".implode(", ",array_keys($recStructureEntity)).") VALUES ('".implode("', '",array_values($recStructureEntity))."')");
							// Write the insert action to $logEntry, and set $error to true if one occurred
							if(mysql_error()) {
								$error = true;
								$logEntry = array("defRecStructure", -1, mysql_error());
								array_push($importLog, $logEntry);
								break;
							} else {
								$lastRstID = mysql_insert_id();
								$logEntry = array("defRecStructure", $lastRstID, "New defRecStructure imported");
								array_push($importLog, $logEntry);
							}
						}
					}
				}
			}
		}


        // 30/8/11: the problem here is that it falls through with an error such as duplicate record detected, but there
        // is no way of knowing on which table the duplicate was detected or what to do about it. It also appears that it
        // sees the existance of some code as an error rather than simply 'I already have that one' (and it needs to
        // handle codes through concept code mapping

		// Give user feedback
		if(!$error) {
			$statusMsg = "";
/* 			TODO: Can be used as import log */
			if(sizeof($importLog) > 0) {
				foreach($importLog as $logLine) {
/* 					$statusMsg += $statusMsg . $logLine[2] . ". Local ID is: " . $logLine[1] . " <br /> "; */
					echo $statusMsg . $logLine[2] . ". Local ID is: " . $logLine[1] . "<br />";
				}
			}
			echo "<br />";
/* 			$statusMsg = $statusMsg . "Imported successfully (" . $importedRecTypeID . ") (Local name: " . $entity["rty_Name"] . ")"; */
/* 			echo $statusMsg; */
		} else if (substr(mysql_error(), 0, 9) == "Duplicate") {
			echo "prompt";
		} else {
			$statusMsg = "Error:<br />";
			if(sizeof($importLog) > 0) {
				foreach($importLog as $logLine) {
					if($logLine[1] == -1) {
						$statusMsg = $statusMsg . $logLine[2] . "<br />";
					}
				}
				$statusMsg = $statusMsg . "An error occurred trying to import the record type";
			}
			// TODO: Delete all information that has already been imported (retrieve from $importLog)
			echo $statusMsg;
		}
	}
	else {
		echo "Error: " . mysql_error();
	}
}

function importTermParents($term) {
	global $error, $importLog, $importingDB;
	$tempDBName = $_GET["tempDBName"];
	$importTerms = array();
	$parentFound = false;

	// While $term has a parentID which is not 0 or "", look if it's already in the local DB, if not, add to $importTerms.
	// If a $term or it's parent is already in the local DB, stop looking, as that means all of it's parents have been imported before
	while(($term["trm_ParentTermID"] != "") && ($term["trm_ParentTermID"] != 0) && (!$parentFound)) {
		$parentTerm = mysql_fetch_array(mysql_query("select * from ".$tempDBName.".defTerms where trm_ID = ".$term["trm_ParentTermID"]), MYSQL_ASSOC);

		if($parentTerm["trm_OriginatingDBID"] == 0 || $parentTerm["trm_OriginatingDBID"] == "") {
			$parentTerm["trm_OriginatingDBID"] = $_GET["crwSourceDBID"];
			$parentTerm["trm_IDInOriginatingDB"] = $_GET["crwSourceCode"];
		}
		$lookupTrm = mysql_query("select * from ".$importingDB.".defTerms where trm_OriginatingDBID = ".$parentTerm["trm_OriginatingDBID"]." AND trm_IDInOriginatingDB = ".$parentTerm["trm_IDInOriginatingDB"]);

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
			$inverseTerm = mysql_fetch_array(mysql_query("select * from ".$tempDBName.".defTerms where trm_ID = ".$newLastInverseID), MYSQL_ASSOC);
			$lookupInverseTrm = mysql_query("select * from ".$importingDB.".defTerms where trm_OriginatingDBID = ".$inverseTerm["trm_OriginatingDBID"]." AND trm_IDInOriginatingDB = ".$inverseTerm["trm_IDInOriginatingDB"]);
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
				mysql_query("INSERT INTO ".$importingDB.".defTerms (".implode(", ",array_keys($inverseTerm)).") VALUES ('".implode("', '",array_values($inverseTerm))."')");
				// Write the insert action to $logEntry, and set $error to true if one occurred
				if(mysql_error()) {
					$error = true;
					$logEntry = array("term", -1, mysql_error());
					array_push($importLog, $logEntry);
					break;
				} else {
					$inverseTrmID = mysql_insert_id();
					$logEntry = array("term", $inverseTrmID, "New parent term imported");
					array_push($importLog, $logEntry);
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
		mysql_query("INSERT INTO ".$importingDB.".defTerms (".implode(", ",array_keys($lastTerm)).") VALUES ('".implode("', '",array_values($lastTerm))."')");
		// Write the insert action to $logEntry, and set $error to true if one occurred
		if(mysql_error()) {
			$error = true;
			$logEntry = array("term", -1, mysql_error());
			array_push($importLog, $logEntry);
			break;
		} else {
			$newLastTermID = mysql_insert_id();
			$logEntry = array("term", $newLastTermID, "New parent term imported");
			array_push($importLog, $logEntry);
		}
		// Inverse ID points to itself, so set to it's own local ID
		if($inverseSelf) {
			mysql_query("UPDATE ".$importingDB.".defTerms SET trm_InverseTermId=".$newLastTermID." where trm_ID=".$newLastTermID);
		}
		// If the inverseTerm had an inverse term had an inverseID
		if($inverseTermItsInverseTermID != "" && $term["trm_InverseTermId"] != "") {
			// If the inverse is symetrical, set the inverse of the earlier inserter term to the last inserted ID
			if($remoteDBTermID == $inverseTermItsInverseTermID) {
				mysql_query("UPDATE ".$importingDB.".defTerms SET trm_InverseTermId=".$newLastTermID." where trm_ID=".$inverseTrmID);
			} else {
				$inverseTerm = mysql_fetch_array(mysql_query("select * from ".$tempDBName.".defTerms where trm_ID = ".$inverseTermItsInverseTermID), MYSQL_ASSOC);
				$lookupInverseTrm = mysql_query("select * from ".$importingDB.".defTerms where trm_OriginatingDBID = ".$inverseTerm["trm_OriginatingDBID"]." AND trm_IDInOriginatingDB = ".$inverseTerm["trm_IDInOriginatingDB"]);
				// Inverse term is not owned, does not point to itself, and is not symmetrical
				if(mysql_num_rows($lookupInverseTrm) == 0) {
					// Fetch the inverse it's inverseTerm, and all its parents recursively in fetchInverseTerm()
					$inverseTermItsInverseTermID = fetchInverseTerm($inverseTerm);
					mysql_query("UPDATE ".$importingDB.".defTerms SET trm_InverseTermId=".$inverseTermItsInverseTermID." where trm_ID=".$inverseTrmID);
				} else {
					$localInverseTerm = mysql_fetch_row($lookupInverseTrm);
					mysql_query("UPDATE ".$importingDB.".defTerms SET trm_InverseTermId=".$localInverseTerm["trm_ID"]." where trm_ID=".$inverseTrmID);
				}
			}
		}
	}
	return $newLastTermID;
}

function fetchInverseTerm($term) {
	global $error, $importLog, $importingDB;
	$tempDBName = $_GET["tempDBName"];
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
		mysql_query("INSERT INTO ".$importingDB.".defTerms (".implode(", ",array_keys($term)).") VALUES ('".implode("', '",array_values($term))."')");
		if(mysql_error()) {
			$error = true;
			$logEntry = array("term", -1, mysql_error());
			array_push($importLog, $logEntry);
			break;
		} else {
			$newInvID = mysql_insert_id();
			$logEntry = array("term", $inverseTrmID, "New inverse term imported");
			array_push($importLog, $logEntry);
		}
		return $newInvID;
		// The term has an inverse
	} else {
		$inverseTerm = mysql_fetch_array(mysql_query("select * from ".$tempDBName.".defTerms where trm_ID = ".$newLastInverseID), MYSQL_ASSOC);
		$lookupInverseTrm = mysql_query("select * from ".$importingDB.".defTerms where trm_OriginatingDBID = ".$inverseTerm["trm_OriginatingDBID"]." AND trm_IDInOriginatingDB = ".$inverseTerm["trm_IDInOriginatingDB"]);
		// The inverse term is not in the local DB
		if(mysql_num_rows($lookupInverseTrm) == 0) {
			// Fetch the term it's inverseTerm and all it's parents, recursively in fetchInverseTerm()
			$inverseTrmID = fetchInverseTerm($inverseTerm);
			$term["trm_InverseTermId"] = $inverseTrmID;
			// Insert the inverseTerm
			mysql_query("INSERT INTO ".$importingDB.".defTerms (".implode(", ",array_keys($term)).") VALUES ('".implode("', '",array_values($term))."')");
			// Write the insert action to $logEntry, and set $error to true if one occurred
			if(mysql_error()) {
				$error = true;
				$logEntry = array("term", -1, mysql_error());
				array_push($importLog, $logEntry);
				break;
			} else {
				$newInvID = mysql_insert_id();
				$logEntry = array("term", $inverseTrmID, "New inverse term imported");
				array_push($importLog, $logEntry);
			}
			return $newInvID;
			// The inverse term is in the local DB, and therefore it's inverse and parents as well. Set the right inverseID, and insert the term
		} else {
			$localInverse = mysql_fetch_row($lookupInverseTrm);
			$inverseTrmID = $localInverse["trm_ID"];
			$term["trm_InverseTermId"] = $inverseTrmID;
			mysql_query("INSERT INTO ".$importingDB.".defTerms (".implode(", ",array_keys($term)).") VALUES ('".implode("', '",array_values($term))."')");
			// Write the insert action to $logEntry, and set $error to true if one occurred
			if(mysql_error()) {
				$error = true;
				$logEntry = array("term", -1, mysql_error());
				array_push($importLog, $logEntry);
				break;
			} else {
				$newInvID = mysql_insert_id();
				$logEntry = array("term", $inverseTrmID, "New inverse term imported");
				array_push($importLog, $logEntry);
			}
			return $newInvID;
		}
	}
}

// Checks wether passed $tempDBName contains 'temp_', and if so, deletes the database
function dropDB() {
	$tempDBName = $_GET["tempDBName"];
	$isTempDB = strpos($tempDBName, "temp_");
	if($isTempDB !== false) {
		mysql_query("drop database ".$tempDBName);
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