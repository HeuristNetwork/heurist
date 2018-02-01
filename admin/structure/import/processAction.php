<?php

/*
* Copyright (C) 2005-2016 University of Sydney
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
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
* @todo Show the import log once the importing is done, so user can see what happened, and change things where desired
* @todo If an error occurres, delete everything that has been imported
*/


require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../saveStructureLib.php');
require_once(dirname(__FILE__)."/../../../common/php/utilsMail.php");

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
$importedRecTypes = array();

//import field id -> target id - IMPORTANT for proper titlemask conversion
$fields_correspondence = array();

mysql_connection_insert($targetDBName);

$mysqli = mysqli_connection_overwrite($targetDBName); // mysqli for saveStructureLib


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
	global $error, $importLog, $tempDBName, $sourceDBName, $targetDBName, $sourceDBID, $importRtyID, $importedRecTypes;
    $importedRecTypes = array();
	$error = false;
	$importLog = array();
	if( !$tempDBName || $tempDBName === "" || !$targetDBName || $targetDBName === "" ||
		!$sourceDBID || !is_numeric($sourceDBID)|| !$importRtyID || !is_numeric($importRtyID)) {
        $error = true;
		makeLogEntry("importParameters", -1, "One or more required import parameters not supplied or incorrect form ( ".
					"importingDBName={name of target DB} sourceDBID={reg number of source DB or 0} ".
					"importRtyID={numeric ID of record type} tempDBName={temp db name where source DB type data are held}");
	}
   
    
    $startedTransaction = false;

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
            
            $warning = false;
            
            
            if($sourceDBID!=2 &&   //core_definitions
                (($importRty["rty_OriginatingDBID"]==3 
                && in_array($importRty["rty_IDInOriginatingDB"],array(1014,1017,1018,1019,1020,1021))) ||  
                ($importRty["rty_OriginatingDBID"]==2 && $importRty["rty_IDInOriginatingDB"]==11))
             ){
                    $warning = true;
                    makeLogEntry("Record type", $importRtyID,               
"The structure of spatial and temporal record types is critical to effective operation of mapping and timelines. To ensure consistency and the latest versions, please download this record type from the Heurist_Core_Definitions (#2) template database at the top of the list in Manage > Structure > Browse templates.");               
                 
             }else if($sourceDBID!=6 //bibliography
                && $importRty["rty_OriginatingDBID"]==3
                && (in_array($importRty["rty_IDInOriginatingDB"],array(102,103,104,108,111,112,113,115,117,1000,1001)) ||
                    ($importRty["rty_IDInOriginatingDB"]>118 && $importRty["rty_IDInOriginatingDB"]<130))               
             ){
                    $warning = true;
                    makeLogEntry("Record type", $importRtyID,               
"The structure of bibliographic record types is critical to effective operation of Zotero synchronisation and bibliographic output formats. To ensure consistency and the latest versions, please download this record type from the Heurist_Bibliographic (#6) template database at the top of the list in Manage > Structure > Browse templates."); 
                 
             }
             
             if($warning){
                $statusMsg = "Warning:<br/>";
                foreach($importLog as $logLine) {
                    $statusMsg .= $logLine[0].(intval($logLine[1])<0?"":" #".$logLine[1]." ").$logLine[2] . "<br/>";
                }
                echo $statusMsg;
                return;
             }
        }            
            
        if(!$error && $importRty) {
            
            
			//lookup rty in target DB
			$resRtyExist = mysql_query("select rty_ID from ".$targetDBName.".defRecTypes ".
							"where rty_OriginatingDBID = ".$importRty["rty_OriginatingDBID"].
							" AND rty_IDInOriginatingDB = ".$importRty["rty_IDInOriginatingDB"]);
			// Rectype is not in target DB so import it
            $localRtyID = null;
			if(mysql_num_rows($resRtyExist) > 0 ) {
				$localRtyID = mysql_fetch_array($resRtyExist,MYSQL_NUM);
				$localRtyID = $localRtyID[0];
				makeLogEntry("Record type", $importRtyID, " ALREADY EXISTS in $targetDBName as ID = $localRtyID");
			}
            
			$localRtyID = importRectype($importRty, $localRtyID);
            if($localRtyID){
                 array_push($importedRecTypes, $importRty["rty_ID"]);
            }
			
		}
	}
	// successful import
    if(!$error) {

		if ($startedTransaction) mysql_query("commit");

        $mask = $importRty["rty_TitleMask"];

        // note we use special global array $fields_correspondence - for proper conversion of remote id to concept code
        $res = updateTitleMask($localRtyID, $mask);
        if(!is_numeric($res)){
            makeLogEntry("Error convertion title mask", $localRtyID, $res);
        }


		$statusMsg = "";
		if(sizeof($importLog) > 0) {
			foreach($importLog as $logLine) {
				echo  $logLine[0].(intval($logLine[1])<0?"":" #".$logLine[1]." ").$logLine[2] . "<br />";
			}
		}
		echo "Successfully imported record type '".$importRty["rty_Name"]."' from ".$sourceDBName."<br />";
		echo "<br />";
        echo "IMPORTED:".implode(",", $importedRecTypes);
        
        sendReportEmail( $importRty, $localRtyID );

        
		return $localRtyID;
	// duplicate record found
	} else if (substr(mysql_error(), 0, 9) == "Duplicate") {
		if ($startedTransaction) mysql_query("rollback");
		echo "prompt";
	//general error condition
	} else {
		if (isset($startedTransaction) && $startedTransaction) mysql_query("rollback");
		if (mysql_error()) {
			$statusMsg = "MySQL error: " . mysql_error() . "<br/>";
		} else  {
			$statusMsg = "Error:<br/>";
		}
		if(sizeof($importLog) > 0) {
			foreach($importLog as $logLine) {
				$statusMsg .= $logLine[0].(intval($logLine[1])<0?"":" #".$logLine[1]." ").$logLine[2] . "<br/>";
			}
			$statusMsg .= "Changes rolled back, nothing was imported";
		}
		// TODO: Delete all information that has already been imported (retrieve from $importLog)
		echo $statusMsg;
        
        //send error report to buginfo
        if(checkSmtp()){
            $email_title = 'Error on import record type';
            $email_text = 'Rectype: '.$importRty["rty_ID"].' ('.$importRty["rty_OriginatingDBID"].'-'.$importRty["rty_IDInOriginatingDB"].') "'.$importRty["rty_Name"]."\"\n"
                .'From database #'.$sourceDBID.' "'.$sourceDBName.'" to "'.$targetDBName.'" at '.HEURIST_SERVER_URL."\n"
                .'Log: '.str_replace('<br/>',"\n",$statusMsg);
            sendEmail(HEURIST_MAIL_TO_BUG, $email_title, $email_text, null);
        }
	}
}

function sendReportEmail($importRty, $localRtyID){
    global $sourceDBName, $targetDBName, $sourceDBID;
    
    if(checkSmtp()){
        
        $email_text = 'Import record type '
         .$importRty["rty_ID"].' ('.$importRty["rty_OriginatingDBID"].'-'.$importRty["rty_IDInOriginatingDB"].') "'.$importRty["rty_Name"]."\"\n"
         .'From database #'.$sourceDBID.' "'.$sourceDBName.'" to "'.$targetDBName.'" at '.HEURIST_SERVER_URL;    
        
        $rv = sendEmail(HEURIST_MAIL_TO_INFO, "Import recordtype", $email_text, null);
    }

}


function importDetailType($importDty,$rtyGroup_src) {
    global $error, $importLog, $tempDBName, $targetDBName, $sourceDBID, $fields_correspondence;
    static $importDtyGroupID;
    $importDtyID = $importDty["dty_ID"];
    if (!$importDtyGroupID) {
        // Create new group with todays date, which all detailtypes that the recordtype uses will be added to
        $dtyGroup = mysql_query("select dtg_ID from ".$targetDBName.".defDetailTypeGroups where dtg_Name = '".$rtyGroup_src['rtg_Name']."'");
        if(mysql_num_rows($dtyGroup) == 0) {
            mysql_query("INSERT INTO ".$targetDBName.".defDetailTypeGroups ".
                "(dtg_Name,dtg_Order, dtg_Description) ".
                "VALUES ('".$rtyGroup_src['rtg_Name']."','255',".
                " '".$rtyGroup_src['rtg_Description']."')");
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


	if(!$error && @$importDty['dty_JsonTermIDTree'] && $importDty['dty_JsonTermIDTree'] != '') {
		// term tree exist so need to translate to new ids
		$importDty['dty_JsonTermIDTree'] =  translateTermIDs($importDty['dty_JsonTermIDTree'],"term tree"," detailType '".$importDty["dty_Name"]."' ID = $importDtyID");
	}

	if(!$error && @$importDty['dty_TermIDTreeNonSelectableIDs'] && $importDty['dty_TermIDTreeNonSelectableIDs'] != '') {
		// term non selectable list exist so need to translate to new ids
		$importDty['dty_TermIDTreeNonSelectableIDs'] =  translateTermIDs($importDty['dty_TermIDTreeNonSelectableIDs'],"non-selectable"," detailType '".$importDty["dty_Name"]."' ID = $importDtyID");
	}
	if(!$error && @$importDty['dty_PtrTargetRectypeIDs'] && $importDty['dty_PtrTargetRectypeIDs'] != '') {
		// Target Rectype list exist so need to translate to new ids
		$importDty['dty_PtrTargetRectypeIDs'] =  translateRtyIDs($importDty['dty_PtrTargetRectypeIDs'],'pointers',$importDty["dty_ID"]);
	}

	if(!$error && @$importDty['dty_FieldSetRectypeID'] && $importDty['dty_FieldSetRectypeID'] != '') {
		// dty represents a base rectype so need to translate to local id
		$importDty['dty_FieldSetRectypeID'] =  translateRtyIDs("".$importDty['dty_FieldSetRectypeID'],'fieldsets',$importDty["dty_ID"]);
	}


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
        
        if(@$importDty['dty_NonOwnerVisibility']==null || $importDty['dty_NonOwnerVisibility']==''){
            $importDty['dty_NonOwnerVisibility'] = 'viewable';
        }
        
		$query = "INSERT INTO ".$targetDBName.".defDetailTypes (".implode(", ",array_keys($importDty)).") VALUES ('".implode("', '",array_values($importDty))."')";
        mysql_query($query);
		// Write the insert action to $logEntry, and set $error to true if one occurred
		if(mysql_error()) {
			$error = true;
			makeLogEntry("<b>Error</b> Importing Field-type", $importDtyID, "MySQL error importing field type - ".mysql_error());
			return null;
		} else {
			$importedDtyID = mysql_insert_id();
            $fields_correspondence[$importDtyID] =  $importedDtyID; //keep pair source=>target field id for proper titlemask conversion
			makeLogEntry("Importing Field-type", $importDtyID, " '".$importDty["dty_Name"]."' as #$importedDtyID");
			return $importedDtyID;
		}
	}
}

// function that translates all rectype ids in the passed string to there local/imported value
function translateRtyIDs($strRtyIDs, $contextString, $forDtyID) {
	global $error, $importLog, $tempDBName, $sourceDBName, $targetDBName, $sourceDBID, $importRefdRectypes, $strictImport, $importedRecTypes;
	if (!$strRtyIDs) {
		return "";
	}

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
				if ($importRefdRectypes) {
					$localRtyID = importRectype($importRty, null);
                    if($localRtyID){
                        array_push($importedRecTypes, $importRty["rty_ID"]);
                    }
					$msg = "as #$localRtyID";
				}else{
					$msg = "Referenced ID ($localRtyID) not found. Not importing - 'no Recursion' is set!";
				}
			} else {
				$row = mysql_fetch_row($resRtyExist);
				$localRtyID = $row[0];
				$msg = " as #".$localRtyID; //found matching rectype entry in $targetDBName rectype ID =
			}
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

function importRectype($importRty, $alreadyImported) {
    global $error, $importLog, $tempDBName, $sourceDBName, $targetDBName, $sourceDBID,$rtyGroup_src;
    //was static $importRtyGroupID;
    $importRtyID = $importRty['rty_ID'];

	// Get Imported  rectypeGroupID
	if(!$error && !$alreadyImported){ // && !$importRtyGroupID) {

		//find group in source
		$query = "select * from ".$tempDBName.".defRecTypeGroups where rtg_ID = ".$importRty['rty_RecTypeGroupID'];

		$res = mysql_query($query);
		if(mysql_num_rows($res) == 0) {
            $error = true;
			makeLogEntry("<b>Error</b> Creating Record-type Group", -1, " Cannot find group #".$importRty['rty_RecTypeGroupID']);
		}else{
			$rtyGroup_src = mysql_fetch_assoc($res);
			//find group by name in target
			$rtyGroup = mysql_query("select rtg_ID from ".$targetDBName.".defRecTypeGroups where rtg_Name = '".$rtyGroup_src['rtg_Name']."'");
			if(mysql_num_rows($rtyGroup) == 0) { //not found
				//add new one
				
                $rtyGroup_src["rtg_Name"] = mysql_escape_string($rtyGroup_src["rtg_Name"]);
                $rtyGroup_src["rtg_Description"] = mysql_escape_string($rtyGroup_src["rtg_Description"]);
                
                $query = "INSERT INTO ".$targetDBName.".defRecTypeGroups ".
						"(rtg_Name,rtg_Domain,rtg_Order, rtg_Description) ".
						"VALUES ('".$rtyGroup_src['rtg_Name']."','".$rtyGroup_src['rtg_Domain']."' , '".$rtyGroup_src['rtg_Order']."',".
								" '".$rtyGroup_src['rtg_Description']."')";
                mysql_query($query);

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

	}


	if(!$error) {
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
				makeLogEntry("<b>Error</b> Importing Field", $rtsFieldDef["rst_ID"], " '".$rtsFieldDef["rst_DisplayName"].
                    "' already exists detail type #$importFieldDtyID record type #$importRtyID");
				makeLogEntry("", -1, ". Originating DBID = ".$rtsFieldDef["rst_OriginatingDBID"]." Originating Field #".$rtsFieldDef["rst_IDInOriginatingDB"]);
//				$error = true;
			}
		}


		if(!$error) {	//import rectype
        
            if($alreadyImported){
                 $importedRecTypeID =  $alreadyImported;
            }else{
			    $recTypeSuffix = 2;
			    while(mysql_num_rows(mysql_query("select * from ".$targetDBName.".defRecTypes where rty_Name = '".$importRty["rty_Name"]."'")) != 0)
                {
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

                if(@$importRty["rty_NonOwnerVisibility"]==null || $importRty["rty_NonOwnerVisibility"]==''){
                    $importRty["rty_NonOwnerVisibility"] = 'viewable';
                }
                
			    // Insert recordtype
			    $query = "INSERT INTO ".$targetDBName.".defRecTypes ".
						    "(".implode(", ",array_keys($importRty)).") VALUES ".
						    "('".implode("', '",array_values($importRty))."')";
                            
                mysql_query($query);
			    // Write the insert action to $logEntry, and set $error to true if one occurred
			    if(mysql_error()) {
				    $error = true;

				    makeLogEntry("Importing Record-type", $importRtyID, "MySQL error importing record type - ".mysql_error());
			    } else {
				    $importedRecTypeID = mysql_insert_id();
				    makeLogEntry("Importing Record-type", $importRtyID, " '".$importRty["rty_Name"]."' as #$importedRecTypeID");

				    copyRectypeIcon($sourceDBName, $importRtyID, $importedRecTypeID);
			    }
            }
		}

		if(!$error) {
			// Import the structure for the recordtype imported
			foreach ( $recStructuresByDtyID as $dtyID => $rtsFieldDef) {
				// get import detailType for this field
				 $resDTY= mysql_query("select * from ".$tempDBName.".defDetailTypes where dty_ID = $dtyID");
				if(mysql_num_rows($resDTY) == 0) {
					$error = true;

					makeLogEntry("<b>Error</b> Importing Field-type", $dtyID,
					" '".$rtsFieldDef['rst_DisplayName']."' for record type #".$rtsFieldDef['rst_RecTypeID']." not found in the source db. Please contact owner of $sourceDBName");
					return null; // missing detatiltype in importing DB
				} else {
					$importDty = mysql_fetch_assoc($resDTY);
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
                $existingDtyID = null;
                if(mysql_num_rows($resExistingDty) == 0) {
                    $rtsFieldDef["rst_DetailTypeID"] = importDetailType($importDty,$rtyGroup_src);
                    if($rtsFieldDef["rst_DetailTypeID"]==null) return null;
				} else {
					$existingDtyID = mysql_fetch_array($resExistingDty);
					$rtsFieldDef["rst_DetailTypeID"] = $existingDtyID[0];
                    $existingDtyID = $existingDtyID[0];
				}

				if(!$error && @$rtsFieldDef['rst_FilteredJsonTermIDTree'] && $rtsFieldDef['rst_FilteredJsonTermIDTree'] != '') {
					// term tree exist so need to translate to new ids
					$rtsFieldDef['rst_FilteredJsonTermIDTree'] =  translateTermIDs($rtsFieldDef['rst_FilteredJsonTermIDTree'],"filtered term tree"," field '".$rtsFieldDef['rst_DisplayName']."' detailTypeID = $dtyID in rectype '".$importRty["rty_Name"]."'");
				}

				if(!$error && @$rtsFieldDef['rst_TermIDTreeNonSelectableIDs'] && $rtsFieldDef['rst_TermIDTreeNonSelectableIDs'] != '') {
					// term non selectable list exist so need to translate to new ids
					$rtsFieldDef['rst_TermIDTreeNonSelectableIDs'] = translateTermIDs($rtsFieldDef['rst_TermIDTreeNonSelectableIDs'],"non-selectable"," field '".$rtsFieldDef['rst_DisplayName']."' detailTypeID = $dtyID in rectype '".$importRty["rty_Name"]."'");
				}

				if(!$error && @$rtsFieldDef['rst_PtrFilteredIDs'] && $rtsFieldDef['rst_PtrFilteredIDs'] != '') {
					// Target Rectype list exist so need to translate to new ids
					$rtsFieldDef['rst_PtrFilteredIDs'] =  translateRtyIDs($rtsFieldDef['rst_PtrFilteredIDs'],'filtered pointers',$importDty["dty_ID"]);
				}

				if(!$error) {
                    
                    if($existingDtyID!=null){
                        //verify that this field is already in structure
                        $resTargetRecStruct = mysql_query("select rst_ID from ".$targetDBName.
                                ".defRecStructure where rst_RecTypeID = ".$importedRecTypeID." and rst_DetailTypeID = ".$existingDtyID);
                        if(mysql_num_rows($resTargetRecStruct) > 0) {        
                            //this field type is already in rec structure
                            continue;
                        }
                    }
                    
					// Adjust values of the field structure for the imported recordtype
					$importRstID = $rtsFieldDef["rst_ID"];
					unset($rtsFieldDef["rst_ID"]);
					$rtsFieldDef["rst_RecTypeID"] = $importedRecTypeID;
					$rtsFieldDef["rst_DisplayName"] = mysql_escape_string($rtsFieldDef["rst_DisplayName"]);
					$rtsFieldDef["rst_DisplayHelpText"] = mysql_escape_string($rtsFieldDef["rst_DisplayHelpText"]);
					$rtsFieldDef["rst_DisplayExtendedDescription"] = mysql_escape_string($rtsFieldDef["rst_DisplayExtendedDescription"]);
					$rtsFieldDef["rst_DefaultValue"] = mysql_escape_string($rtsFieldDef["rst_DefaultValue"]);
					$rtsFieldDef["rst_DisplayHelpText"] = mysql_escape_string($rtsFieldDef["rst_DisplayHelpText"]);
                    
                    
                    if(@$rtsFieldDef["rst_NonOwnerVisibility"]==null || $rtsFieldDef["rst_NonOwnerVisibility"]==''){
                        $rtsFieldDef["rst_NonOwnerVisibility"] = 'viewable';
                    }

					// Import the field structure for the imported recordtype
					$query = "INSERT INTO ".$targetDBName.".defRecStructure (".implode(", ",array_keys($rtsFieldDef)).") VALUES ('".implode("', '",array_values($rtsFieldDef))."')";
                    mysql_query($query);
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
			}//for fields
			if (!$error) {
				return $importedRecTypeID;
            }
		}
		return null;
	}
}

//
// Copy record type icon and thumbnail from source to destination database
//
function copyRectypeIcon($sourceDBName, $importRtyID, $importedRecTypeID, $thumb=""){

	// BUG TODO: this is not always true, and what about cross server
    $filename = HEURIST_UPLOAD_ROOT.$sourceDBName."/rectype-icons/".$thumb.$importRtyID.".png";

	if(file_exists($filename)){

		$target_filename = HEURIST_ICON_DIR.$thumb.$importedRecTypeID.".png";

		if(file_exists($target_filename)){
			unlink($target_filename);
		}

		if (!copy($filename, $target_filename)) {
			makeLogEntry("<b>Warning</b> Importing Record-type", $importRtyID, " Can't copy ".(($thumb=="")?"icon":"thumbnail")." ".$filename." to ".$target_filename);
		}
	}else{
		makeLogEntry("<b>Warning</b> Importing Record-type", $importRtyID, " ".(($thumb=="")?"icon":"thumbnail")." does not exist");
	}

    if($thumb==""){
        copyRectypeIcon($sourceDBName, $importRtyID, $importedRecTypeID, "thumb/th_");
    }

}

//
//   OLD - trm_ChildCount is not reliable
//
function getCompleteVocabulary_OLD($termId){
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

function getCompleteVocabulary($termID, $parentlist=null) {
    global $tempDBName;
    if(@$parentlist==null) $parentlist = array($termID);
    $offspring = array($termID);
    if ($termID) {
        $res = mysql_query("select * from ".$tempDBName.".defTerms where trm_ParentTermID=$termID");
        if ($res && mysql_num_rows($res)) { //child nodes exist
            while ($row = mysql_fetch_assoc($res)) { // for each child node
                $subTermID = $row['trm_ID'];
                if(array_search($subTermID, $parentlist)===false){
                    array_push($offspring, $subTermID);
                    array_push($parentlist, $subTermID);
                    $offspring = array_unique(array_merge($offspring, getCompleteVocabulary($subTermID, $parentlist)));
                }else{
                    error_log('Database '.$tempDBName.'. Recursion in parent-term hierarchy '.$termID.'  '.$subTermID);
                }
            }
        }
    }
    return $offspring;
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
// if term is not found it will be imported
//
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
		    $temp = preg_replace("/[\{\}\",]/","",$formattedStringOfTermIDs);
		    if (strrpos($temp,":") == strlen($temp)-1) {
			    $temp = substr($temp,0, strlen($temp)-1);
		    }
		    $termIDs = explode(":",$temp);

	    } else {
		    $temp = preg_replace("/[\[\]\"]/","",$formattedStringOfTermIDs);
		    $termIDs = explode(",",$temp);
	    }

    }


	// Import terms
    $isonce = true;
	foreach ($termIDs as $importTermID) {
		// importTerm
		$translatedTermID = importTermID($importTermID);
		// check that the term imported correctly
		if ($translatedTermID == ""){
			return "";
		}

		//replace termID in string
        if(is_numeric($retJSonTermIDs)){
            if($retJSonTermIDs==$importTermID && $isonce){
                $isonce =false;
                $retJSonTermIDs=$translatedTermID;
            }
        }else{
		    $retJSonTermIDs = preg_replace("/\"".$importTermID."\"/","\"".$translatedTermID."\"",$retJSonTermIDs);
        }
	}


	// TODO: update the ChildCounts
	makeLogEntry("Term string", '', "Translated $formattedStringOfTermIDs to $retJSonTermIDs.");

	return $retJSonTermIDs;
}

function importTermID($importTermID) {
	global $error, $importLog, $tempDBName, $targetDBName, $sourceDBID;

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
            if(@$term["trm_Status"]==null) $term["trm_Status"] = "open";
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


	$message = "";
	$res = true;
	$tempDBName = $_GET["tempDBName"];
	$isTempDB = strpos($tempDBName, "temp_");
	if($isTempDB !== false) {
		mysql_query("drop database ".$tempDBName);
		if(mysql_error()) {
			$message = "Warning: Error deleting the temporary database. Please let your sysadmin know so that they are aware of this.";
			$res = false;
		}
	} else {
		$message = "Error: cannot delete a non-temporary database";
		$res = false;
	}

	if($res){
		//echo "<script>setTimeout(function(){window.close();}, 1500);</script>";
        echo "OK";
	}else{
        echo $message;
        /*
        echo "<html><head>";
        echo '<meta http-equiv="content-type" content="text/html; charset=utf-8">';
        echo "<link rel=stylesheet href='../../../common/css/global.css'></head>";
        echo "<body class='popup'><div style='text-align:center;font-weight:bold;font-size:1.3em;padding-top:10px'>";
		echo "<script>setTimeout(function(){
					if(window.frameElement.parentElement){
						var ele = window.frameElement.parentElement.parentElement;
                		var xy = top.HEURIST.util.suggestedPopupPlacement(300, 100);
                		ele.style.left = xy.x + 'px';
                		ele.style.top = xy.y + 'px';
                	}
				}, 200);</script>";
        echo "<div>$message</div>";
        echo "</body></html>";
        */
	}

}
?>

