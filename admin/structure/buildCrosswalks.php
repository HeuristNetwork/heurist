<?php
	/*<!--
	* buildCrosswalks.php, Gets definitions from a specified installation of Heurist and writes them either to a new DB, or temp DB, 18-02-2011, by Juan Adriaanse
	* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	-->*/

	// crosswalk_builder.php  - gets definitions from a specified installation of Heurist
	// Processes them into local definitions, allows the administrator to import definitions
	// and stores equivalences in the def_crosswalks table.

	// started: Ian Johnson 3 March 2010. Revised Ian Johnson 26 sep 2010 14.15 to new table/field names
	// and added selection of definitions to be imported and crosswalk builder, plus instructions and pseudocode.


	// Notes and directions:

	// This version simply imports definitions. It does not look for existing similar definitions and does not
	// allow any sort of combination of definitions. In a smarter next version we might add the ability
	// to show similar record types (based on fuzzy name matching and/or identification of original source
	// as being the same) next to each of the import candidates, so people will be less inclined to import
	// several very similar record type definitions.

	// The same could be done for detail types, allowing the admin to re-use an existing detail type rather than
	// creating a new one, but they will have to be of the same type eg. text, numeric, date etc. and there
	// could be a problem where vocabs and constraints are involved since the existing vocabs might not have all
	// the enum values required by the constraint.

	// Once this version is up and running, we need either a variant, or to add the capability to this one, of
	// matching and writing the crosswalk for record types, detail types, vacabularies and enums (not for constraints)
	// without importing new definitions, in other words just setting up the crosswalk to be able to send queries
	// and/or download data from another instance.

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

	if (!is_logged_in()) {
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}

	// ------Administrative stuff ------------------------------------------------------------------------------------

	// Verify credentials of the user and check that they are an administrator, check no-one else is trying to
	// change the definitions at the same time


	// Requires admin user, access to definitions though get_definitions is open
	if (!is_admin()) {
		print "<html><body><p>You do not have sufficient privileges to access this page</p><p><a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME."'>Log out</a></p></body></html>";
		return;
	}

	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
	require_once(dirname(__FILE__).'/../../common/t1000/.ht_stdefs');
	require_once(dirname(__FILE__).'/../../common/t1000/t1000.php');

	$definitions_being_modified = FALSE;
	$server_offline = FALSE;
	global $errorCreatingTables;
	$errorCreatingTables = FALSE;

	// Deals with all the database connections stuff
	mysql_connection_db_insert(DATABASE);

	global $dbname;
	// Create new temp database with timestamp
	global $tempDBName;
	if(!isset($isNewDB)) { $isNewDB = false; }
	if(!$isNewDB) {
		$dbname = DATABASE;
		$isNewDB = false;
		$dateAndTime = date("dmygisa");
		$tempDBName = "hdb_temp_" . $dateAndTime;
		mysql_query("CREATE DATABASE `" . $tempDBName . "`");
	} else {
		$tempDBName = "`".$newname."`";
		$dbname = $newname;
	}
	mysql_connection_db_insert($tempDBName); // Use new database

	$version = 1; // Definitions exchange format version number. Update in get_definitions and here

	// -----Check not locked by admin -------------------------------

	// THIS SECTION SHOULD BE ABSTRACTED AS A FUNCTION IN ONE OF THE LIBRARIES, perhaps in cred.php?

	// ???? we should now mark the target (current)database for administrator access to avoid two administrators
	// working on this at the same time. But need to provide a means of removing lock in case the
	// connection is lost, eg. heartbeat on subsequent pages or a specific 'remove admin lock' link (easier)

	// Check if someone else is already modifying database definitions, if so: stop.
	$res = mysql_query("select lck_UGrpID from sysLocks where lck_ID=1");
	if($res) {
		if (mysql_num_rows($res)>0) {
			echo "Definitions are already being modified.";
			$definitions_being_modified = TRUE; // database definitions are being modified by administrator
		}
	}

	if ($definitions_being_modified) {
		// "Another administrator is modifying the definitions"
		// "If this is not the case, "(or appears to be same user)" use 'Remove lock on database definition modification' from the administration page"
		// "Click [continue] to return to the administration page"
		header('Location: ' . BASE_PATH . 'admin/index.php'); // return to the adminstration page
		die("Definitions are already being modified.");
	}

	// Mark database definitons as being modified by adminstrator
	$definitions_being_modified=TRUE;
	$query = "insert into sysLocks (lck_ID, lck_UGrpID, lck_Action) VALUES ('1', '0', 'BuildCrosswalks')";

	$res = mysql_query($query); // create sysLock

	// ------Find and set the source database-----------------------------------------------------------------------

	// ??? Query reference.heuristscholar.org to find the URL of the installation you want to use as source
	// or perhaps simply enter the ID if you know it (initially we can use this to query db #1 for testing)
	// The query should be based on DOAP metadata and keywords which Steven is due to set up in the reference database

	// ???? This does not really need to be done now, we can live with entering specific reference numbers
	// ???? Add a temporary popup form requesting a Heurist database ID and query Heurist reference database
	// ???? for the database title and the URL to the database
	// ???? the URL should be set from the search results on the reference database


// TODO: Change three fields below to information about the database you will be importing from
	global $source_db_id;
	if(!isset($_REQUEST["dbID"]) || $_REQUEST["dbID"] == 0) {
		$source_db_id = '1';
		$source_db_name = 'HeuristScholar Reference Database';
		$source_url = "http://heuristscholar.org/h3-ja/admin/structure/getDBStructure.php?db=Reference2B";
	} else {
		$source_db_id = $_REQUEST["dbID"];
		$source_db_name = $_REQUEST["dbName"];
		error_log($source_db_name);
		$source_url = $_REQUEST["dbURL"]."admin/structure/getDBStructure.php?db=".$source_db_name;
	}

// Request data from source database
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_COOKIEFILE, '/dev/null');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);	//return curl_exec output as string
	curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
	curl_setopt($ch, CURLOPT_NOBODY, 0);
	curl_setopt($ch, CURLOPT_HEADER, 0);	//don't include header in output
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);	// follow server header redirects
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);	// don't verify peer cert
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);	  // timeout after ten seconds
	curl_setopt($ch, CURLOPT_MAXREDIRS, 5);	   // no more than 5 redirections
	curl_setopt($ch, CURLOPT_URL,$source_url);
	$data = curl_exec($ch);
	$error = curl_error($ch);
	if ($error || !$data || substr($data, 0, 6) == "unable") {
		$code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		$server_offline = TRUE;
	}

// Did not receive any data from server, therefore considers it being offline
	if($server_offline) {
		if($source_db_id == '1') { // Source database is references database: use fallBackDefinitionsIfOffline.txt
			$file = fopen("../setup/fallbackDefinitionsIfOffline.txt", "r");
			while(!feof($file)) {
				$output = $output . fgets($file, 4096);
			}
			fclose($file);
			$data = $output;
			echo "Heurist reference database could not be accessed. Using 'fallbackDefinitionsIfOffline.txt' to create database instead.<br /><br />";
		}
		else { // Cancel buildCrosswalk process as no data can be received
			die("Source database could not be accessed.");
		}
	}
	else { // Data successfully retrieved from remote server
	}

// Splits receiver data into data sets for one table
	$splittedData = split("> Start", $data);
	$tableNumber;
	function getNextDataSet($splittedData) {
		global $tableNumber;
		if(!$tableNumber) {
			$tableNumber = 1;
		}
		if(sizeof($splittedData) > $tableNumber) {
			$splittedData2 = split("> End", $splittedData[$tableNumber]);
			$i = 1;
			$size = strlen($splittedData2[0]);
			$testData = $splittedData2[0];
			if(!($testData[$size - $i] == ")")) {
				while((($size - $i) > 0) && (($testData[$size - $i]) != ")")) {
					if($i == 10) {
						$i = -1;
						break;
					}
					$i++;
				}
			}
			if($i != -1) {
				$i--;
				$splittedData3 = substr($splittedData2[0],0,-$i);
			}
			$tableNumber++;
			return $splittedData3;
		} else {
			return null;
		}
	}

	$recTypes = getNextDataSet($splittedData);
	$detailTypes = getNextDataSet($splittedData);
	$recStructure = getNextDataSet($splittedData);
	$terms = getNextDataSet($splittedData);
	$ontologies = getNextDataSet($splittedData);
	$relationshipConstraints = getNextDataSet($splittedData);
	$fileExtToMimetype = getNextDataSet($splittedData);
	$recTypeGroups = getNextDataSet($splittedData);
	$detailTypeGroups = getNextDataSet($splittedData);
	$translations = getNextDataSet($splittedData);
	
	$query = "SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'";
	mysql_query($query);
	processRecTypes($recTypes);
	processTerms($terms);
	processOntologies($ontologies);
	processRelationshipConstraints($relationshipConstraints);
	processFileExtToMimetype($fileExtToMimetype);
	processRecTypeGroups($recTypeGroups);
	processDetailTypeGroups($detailTypeGroups);
	processDetailTypes($detailTypes);
	processRecStructure($recStructure);
	processTranslations($translations);
	$query = "SET SESSION sql_mode=''";
	mysql_query($query);

// TODO: Make sure all values are written correctly (especially the NULL values)

// ------ Copy source DB definitions to temporary local tables ---------------------------------------------------

// BEGIN Importing to following data to temp table: defRecTypes
	function processRecTypes($dataSet) {
		global $errorCreatingTables;
		$query = "CREATE TABLE IF NOT EXISTS `defRecTypes` (
		`rty_ID` smallint(5) unsigned NOT NULL,
		`rty_Name` varchar(63) NOT NULL,
		`rty_OrderInGroup` tinyint(3) unsigned default '0',
		`rty_Description` varchar(5000) NOT NULL,
		`rty_TitleMask` varchar(500) NOT NULL default '[title]',
		`rty_CanonicalTitleMask` varchar(500) default 160,
		`rty_Plural` varchar(63) default NULL,
		`rty_Status` enum('reserved', 'approved', 'pending', 'open') NOT NULL default 'open',
		`rty_OriginatingDBID` smallint(5) unsigned default NULL,
		`rty_NameInOriginatingDB` varchar(63) default NULL,
		`rty_IDInOriginatingDB` smallint(5) unsigned default NULL,
		`rty_ShowInLists` tinyint(1) unsigned NOT NULL default '1',
		`rty_RecTypeGroupIDs` tinyint(3) NOT NULL default 1,
		`rty_FlagAsFieldset` tinyint(1) unsigned NOT NULL default '0',
		`rty_ReferenceURL` varchar(250) default NULL,
		`rty_AlternativeRecEditor` varchar(63) default NULL,
		`rty_Type` enum('normal', 'relationship', 'dummy') NOT NULL default 'normal',
		PRIMARY KEY  (`rty_ID`)
		) ENGINE=InnoDB  DEFAULT CHARSET=latin1";
		mysql_query($query);
		if(mysql_error()) {
			echo "Error creating table: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
	
		if(($dataSet == "") || (strlen($dataSet) <= 2)) {
		} else {
			$query = "INSERT INTO `defRecTypes` (`rty_ID`, `rty_Name`, `rty_OrderInGroup`, `rty_Description`, `rty_TitleMask`, `rty_CanonicalTitleMask`, `rty_Plural`, `rty_Status`, `rty_OriginatingDBID`, `rty_NameInOriginatingDB`, `rty_IDInOriginatingDB`, `rty_ShowInLists`, `rty_RecTypeGroupIDs`, `rty_FlagAsFieldset`, `rty_ReferenceURL`, `rty_AlternativeRecEditor`, `rty_Type`) VALUES" . $dataSet;
			mysql_query($query);
			if(mysql_error()) {
				echo "RECTYPES Error inserting data: " . mysql_error() . "<br />";
				$errorCreatingTables = TRUE;
			}
		}
	}
// END Imported first set of data to temp table: defRectypes

// BEGIN Importing to following data to temp table: defDetailTypes
	function processDetailTypes($resultSet) {
		global $errorCreatingTables;
	
		$query = "CREATE TABLE IF NOT EXISTS `defDetailTypes` (
		`dty_ID` smallint(5) unsigned NOT NULL,
		`dty_Name` varchar(255) NOT NULL,
		`dty_Documentation` varchar(5000) default 'Please document the nature of this detail type (field)) ...',
		`dty_Type` enum('freetext','blocktext','integer','date','year','relmarker','boolean','enum','relationtype','resource','float','file','geo','separator','calculated', 'fieldsetmarker') NOT NULL,
		`dty_HelpText` varchar(255) NOT NULL default 'Please provide a short explanation for the user ...',
		`dty_ExtendedDescription` varchar(5000) default 'Please provide an extended description for display on rollover ...',
		`dty_Status` enum('reserved','approved','pending','open') NOT NULL default 'open',
		`dty_OriginatingDBID` smallint(5) unsigned,
		`dty_NameInOriginatingDB` varchar(255),
		`dty_IDInOriginatingDB` smallint(5) unsigned,
		`dty_DetailTypeGroupID` tinyint(3) unsigned NOT NULL default '1',
		`dty_OrderInGroup` tinyint(3) unsigned default '0',
		`dty_JsonTermIDTree` varchar(5000) default NULL,
		`dty_TermIDTreeNonSelectableIDs` varchar(1000) default NULL,
		`dty_PtrTargetRectypeIDs` varchar(63),
		`dty_FieldSetRecTypeID` smallint(5) unsigned default NULL,
		`dty_ShowInLists` tinyint(1) unsigned NOT NULL default '1',
		PRIMARY KEY  (`dty_ID`),
		UNIQUE KEY `bdt_name` (`dty_Name`)
		) ENGINE=InnoDB  DEFAULT CHARSET=latin1";
		mysql_query($query);
		if(mysql_error()) {
			echo "Error creating table: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
	
		if(($resultSet == "") || (strlen($resultSet) <= 2)) {
		} else {
			$query = "INSERT INTO `defDetailTypes` (`dty_ID`, `dty_Name`, `dty_Documentation`, `dty_Type`, `dty_HelpText`, `dty_ExtendedDescription`, `dty_Status`, `dty_OriginatingDBID`, `dty_NameInOriginatingDB`, `dty_IDInOriginatingDB`, `dty_DetailTypeGroupID`, `dty_OrderInGroup`, `dty_JsonTermIDTree`, `dty_TermIDTreeNonSelectableIDs`, `dty_PtrTargetRectypeIDs`, `dty_FieldSetRecTypeID`, `dty_ShowInLists`) VALUES" . $resultSet;
			mysql_query($query);
			if(mysql_error()) {
				echo "DETAILTYPES Error inserting data: " . mysql_error() . "<br />";
				$errorCreatingTables = TRUE;
			}
		}
	}
// END Imported first set of data to temp table: defDetailTypes

// BEGIN Importing to following data to temp table: defRecStructure
	function processRecStructure($resultSet) {
		global $errorCreatingTables;
	
		$query = "CREATE TABLE IF NOT EXISTS `defRecStructure` (
		`rst_ID` smallint(5) unsigned NOT NULL,
		`rst_RecTypeID` smallint(5) unsigned NOT NULL,
		`rst_DetailTypeID` smallint(5) unsigned NOT NULL,
		`rst_DisplayName` varchar(255) NOT NULL default 'Please enter a prompt ...',
		`rst_DisplayHelpText` varchar(255),
		`rst_DisplayExtendedDescription` varchar(5000),
		`rst_DisplayOrder` smallint(3) unsigned zerofill NOT NULL default '999',
		`rst_DisplayWidth` tinyint(3) unsigned NOT NULL default '50',
		`rst_DefaultValue` varchar(63),
		`rst_RecordMatchOrder` tinyint(1) unsigned NOT NULL default '0',
		`rst_CalcFunctionID` tinyint(3) unsigned,
		`rst_RequirementType` enum('required', 'recommended', 'optional', 'forbidden') NOT NULL default 'optional',
		`rst_Status` enum('reserved', 'approved', 'pending', 'open') NOT NULL default 'open',
		`rst_MayModify` enum('locked', 'discouraged', 'open') NOT NULL default 'open',
		`rst_OriginatingDBID` smallint(5) unsigned,
		`rst_IDInOriginatingDB` smallint(5) unsigned,
		`rst_MaxValues` tinyint(3) unsigned NOT NULL default '0',
		`rst_MinValues` tinyint(3) unsigned NOT NULL default '1',
		`rst_DisplayDetailTypeGroupID` tinyint(3) unsigned,
		`rst_FilteredJsonTermIDTree` varchar(500),
		`rst_PtrFilteredIDs` varchar(250),
		`rst_OrderForThumbnailGeneration` tinyint(3) unsigned,
		`rst_TermIDTreeNonSelectableIDs` varchar(255),
		PRIMARY KEY  (`rst_ID`),
		UNIQUE KEY `bdr_rectype` (`rst_RecTypeID`,`rst_DetailTypeID`)
		) ENGINE=InnoDB  DEFAULT CHARSET=latin1";
		mysql_query($query);
		if(mysql_error()) {
			echo "Error creating table: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
	
		if(($resultSet == "") || (strlen($resultSet) <= 2)) {
		} else {
			$query = "INSERT INTO `defRecStructure` (`rst_ID`, `rst_RecTypeID`, `rst_DetailTypeID`, `rst_DisplayName`, `rst_DisplayHelpText`, `rst_DisplayExtendedDescription`, `rst_DisplayOrder`, `rst_DisplayWidth`, `rst_DefaultValue`, `rst_RecordMatchOrder`, `rst_CalcFunctionID`, `rst_RequirementType`, `rst_Status`, `rst_MayModify`, `rst_OriginatingDBID`, `rst_IDInOriginatingDB`, `rst_MaxValues`, `rst_MinValues`, `rst_DisplayDetailTypeGroupID`, `rst_FilteredJsonTermIDTree`, `rst_PtrFilteredIDs`, `rst_OrderForThumbnailGeneration`, `rst_TermIDTreeNonSelectableIDs`) VALUES " . $resultSet;
			mysql_query($query);
			if(mysql_error()) {
				echo "RECSTRUCTURE Error inserting data: " . mysql_error() . "<br />";
				$errorCreatingTables = TRUE;
			}
		}
	}
// END Imported first set of data to temp table: defRecStructure

// BEGIN Importing to following data to temp table: defTerms
	function processTerms($resultSet) {
		global $errorCreatingTables;
	
		$query = "CREATE TABLE IF NOT EXISTS `defTerms` (
		`trm_ID` mediumint(8) unsigned NOT NULL,
		`trm_Label` varchar(63) NOT NULL,
		`trm_InverseTermId` mediumint(8) unsigned,
		`trm_Description` varchar(500),
		`trm_Status` enum('reserved','approved','pending','open') NOT NULL default 'open',
		`trm_OriginatingDBID` mediumint(8) unsigned,
		`trm_NameInOriginatingDB` varchar(63),
		`trm_IDInOriginatingDB` mediumint(8) unsigned,
		`trm_AddedByImport` tinyint(1) unsigned NOT NULL default '0',
		`trm_IsLocalExtension` tinyint(1) unsigned NOT NULL default '0',
		`trm_Domain` enum('enum', 'relation') NOT NULL default 'enum',
		`trm_OntID` smallint(5) unsigned NOT NULL default '0',
		`trm_ChildCount` tinyint(3) NOT NULL default '0',
		`trm_ParentTermID` int(10) unsigned default NULL,
		`trm_Depth` tinyint(1) unsigned NOT NULL default '1',
		PRIMARY KEY  (`trm_ID`)
		) ENGINE=InnoDB  DEFAULT CHARSET=latin1";
		mysql_query($query);
		if(mysql_error()) {
			echo "Error creating table: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
	
		if(($resultSet == "") || (strlen($resultSet) <= 2)) {
		} else {
			
			$query = "SET FOREIGN_KEY_CHECKS = 0;";
			mysql_query($query);

			$query = "INSERT INTO `defTerms` (`trm_ID`, `trm_Label`, `trm_InverseTermId`, `trm_Description`, `trm_Status`, `trm_OriginatingDBID`, `trm_NameInOriginatingDB`, `trm_IDInOriginatingDB`, `trm_AddedByImport`, `trm_IsLocalExtension`, `trm_Domain`, `trm_OntID`, `trm_ChildCount`, `trm_ParentTermID`, `trm_Depth`) VALUES " . $resultSet;
			mysql_query($query);
			if(mysql_error()) {
				echo "TERMS Error inserting data: " . mysql_error() . "<br />";
				$errorCreatingTables = TRUE;
			}
			
			$query = "SET FOREIGN_KEY_CHECKS = 1;";
			mysql_query($query);
		}
	}
// END Imported first set of data to temp table: defTerms

// BEGIN Importing to following data to temp table: defOntologies
	function processOntologies($resultSet) {
		global $errorCreatingTables;
	
		$query = "CREATE TABLE IF NOT EXISTS `defOntologies` (
		`ont_ID` smallint(5) unsigned NOT NULL,
		`ont_ShortName` varchar(128) NOT NULL,
		`ont_FullName` varchar(128) NOT NULL,
		`ont_Description` varchar(1000),
		`ont_RefURI` varchar(250),
		`ont_Status` enum('reserved','approved','pending','open') NOT NULL default 'open',
		`ont_OriginatingDBID` smallint(5) unsigned,
		`ont_NameInOriginatingDB` varchar(64),
		`ont_IDInOriginatingDB` smallint(5) unsigned,
		`ont_Order` tinyint(3) unsigned Zerofill default 255,
		PRIMARY KEY  (`ont_ID`),
		UNIQUE KEY `ont_ShortName` (`ont_ShortName`)
		) ENGINE=InnoDB  DEFAULT CHARSET=latin1";
		mysql_query($query);
		if(mysql_error()) {
			echo "Error creating table: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
	
		if(($resultSet == "") || (strlen($resultSet) <= 2)) {
		} else {
			$query = "INSERT INTO `defOntologies` (`ont_ID`,`ont_ShortName`,`ont_FullName`,`ont_Description`,`ont_RefURI`,`ont_Status`,`ont_OriginatingDBID`,`ont_NameInOriginatingDB`,`ont_IDInOriginatingDB`,`ont_Order`) VALUES " . $resultSet;
			mysql_query($query);
			if(mysql_error()) {
				echo "ONTOLOGIES Error inserting data: " . mysql_error() . "<br />";
				$errorCreatingTables = TRUE;
			}
		}
	}
// END Imported first set of data to temp table: defOntologies

// BEGIN Importing to following data to temp table: defRelationshipConstraints
	function processRelationshipConstraints($resultSet) {
		global $errorCreatingTables;
	
		$query = "CREATE TABLE IF NOT EXISTS `defRelationshipConstraints` (
		`rcs_ID` smallint(5) unsigned NOT NULL auto_increment,
		`rcs_SourceRectypeID` smallint(5) unsigned default NULL,
		`rcs_TargetRectypeID` smallint(5) unsigned default NULL,
		`rcs_Description` varchar(1000) default 'Please describe ...',
		`rcs_Status` enum('reserved','approved','pending','open') NOT NULL default 'open',
		`rcs_OriginatingDBID` mediumint(8) unsigned default NULL,
		`rcs_IDInOriginatingDB` smallint(5) unsigned default NULL,
		`rcs_TermID` int(10) unsigned default NULL,
		`rcs_TermLimit` tinyint(3) unsigned default '0',
		PRIMARY KEY  (`rcs_ID`)
		) ENGINE=InnoDB  DEFAULT CHARSET=latin1";
		mysql_query($query);
		if(mysql_error()) {
			echo "Error creating table: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
	
		if(($resultSet == "") || (strlen($resultSet) <= 2)) {
		} else {
			$query = "INSERT INTO `defRelationshipConstraints` (`rcs_ID`,`rcs_SourceRectypeID`,`rcs_TargetRectypeID`,`rcs_Description`,`rcs_Status`,`rcs_OriginatingDBID`,`rcs_IDInOriginatingDB`,`rcs_TermID`,`rcs_TermLimit`) VALUES " . $resultSet;
			mysql_query($query);
			if(mysql_error()) {
				echo "RELATIONSHIPCONSTRAINTS Error inserting data: " . mysql_error() . "<br />";
				$errorCreatingTables = TRUE;
			}
		}
	}
// END Imported first set of data to temp table: defRelationshipConstraints

// BEGIN Importing to following data to temp table: defFileExtToMimetype
	function processFileExtToMimetype($resultSet) {
		global $errorCreatingTables;
	
		$query = "CREATE TABLE IF NOT EXISTS `defFileExtToMimetype` (
		`fxm_Extension` varchar(10) NOT NULL,
		`fxm_MimeType` varchar(100) NOT NULL,
		`fxm_OpenNewWindow` tinyint(1) unsigned NOT NULL default '0',
		`fxm_IconFileName` varchar(31) default NULL,
		`fxm_FiletypeName` varchar(31) default NULL,
		`fxm_ImagePlaceholder` varchar(63) default NULL,
		PRIMARY KEY  (`fxm_Extension`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8";
		mysql_query($query);
		if(mysql_error()) {
			echo "Error creating table: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
		if(($resultSet == "") || (strlen($resultSet) <= 2)) {
		} else {
			$query = "INSERT INTO `defFileExtToMimetype` (`fxm_Extension`,`fxm_MimeType`,`fxm_OpenNewWindow`,`fxm_IconFileName`,`fxm_FiletypeName`,`fxm_ImagePlaceholder`) VALUES " . $resultSet;
			mysql_query($query);
			if(mysql_error()) {
				echo "FILEEXTTOMIMETYPE Error inserting data: " . mysql_error() . "<br />";
				$errorCreatingTables = TRUE;
			}
		}
	}
// END Imported first set of data to temp table: defFileExtToMimetype

// BEGIN Importing to following data to temp table: defRecTypeGroups
	function processRecTypeGroups($resultSet) {
		global $errorCreatingTables;
	
		$query = "CREATE TABLE IF NOT EXISTS `defRecTypeGroups` (
		`rtg_ID` tinyint(3) unsigned NOT NULL,
		`rtg_Name` varchar(40) NOT NULL,
		`rtg_Domain` enum('functionalgroup', 'modelview') NOT NULL default 'Functionalgroup',
		`rtg_Order` tinyint(3) unsigned zerofill NOT NULL default '255',
		`rtg_Description` varchar(250) default NULL,
		PRIMARY KEY  (`rtg_ID`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8";
		mysql_query($query);
		if(mysql_error()) {
			echo "Error creating table: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
	
		if(($resultSet == "") || (strlen($resultSet) <= 2)) {
		} else {
			$query = "INSERT INTO `defRecTypeGroups` (`rtg_ID`,`rtg_Name`,`rtg_Domain`,`rtg_Order`,`rtg_Description`) VALUES " . $resultSet;
			mysql_query($query);
			if(mysql_error()) {
				echo "RECTYPEGROUPS Error inserting data: " . mysql_error() . "<br />";
				$errorCreatingTables = TRUE;
			}
		}
	}
// END Imported first set of data to temp table: defRecTypeGroups

// BEGIN Importing to following data to temp table: defDetailTypeGroups
	function processDetailTypeGroups($resultSet) {
		global $errorCreatingTables;
	
		$query = "CREATE TABLE IF NOT EXISTS `defDetailTypeGroups` (
		`dtg_ID` tinyint(3) unsigned NOT NULL,
		`dtg_Name` varchar(63) NOT NULL,
		`dtg_Order` tinyint(3) unsigned zerofill NOT NULL default '255',
		`dtg_Description` varchar(255) NOT NULL,
		PRIMARY KEY  (`dtg_ID`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8";
		mysql_query($query);
		if(mysql_error()) {
			echo "Error creating table: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
		if(($resultSet == "") || (strlen($resultSet) <= 2)) {
		} else {
			$query = "INSERT INTO `defDetailTypeGroups` (`dtg_ID`,`dtg_Name`,`dtg_Order`,`dtg_Description`) VALUES " . $resultSet;
			mysql_query($query);
			if(mysql_error()) {
				echo "DETAILTYPEGROUPS Error inserting data: " . mysql_error() . "<br /><br />" . $resultSet . "<br />";
				$errorCreatingTables = TRUE;
			}
		}
	}
// END Imported first set of data to temp table: defDetailTypeGroups

// BEGIN Importing to following data to temp table: defTranslations
	function processTranslations($resultSet) {
		global $errorCreatingTables;
	
		$query = "CREATE TABLE IF NOT EXISTS `defTranslations` (
		`trn_ID` int(10) unsigned NOT NULL,
		`trn_Source` enum('rty_Name','dty_Name','ont_ShortName','vcb_Name','trm_Label','rst_DisplayName','rtg_Name','dtl_Value') NOT NULL,
		`trn_Code` smallint(5) unsigned NOT NULL,
		`trn_LanguageCode3` char(3) NOT NULL,
		`trn_Translation` varchar(63) NOT NULL,
		PRIMARY KEY  (`trn_ID`),
		UNIQUE KEY `trn_SourceCodeLanguageKey` (`trn_Source`,`trn_Code`,`trn_LanguageCode3`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8";
		mysql_query($query);
		if(mysql_error()) {
			echo "Error creating table: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
		if(($resultSet == "") || (strlen($resultSet) <= 2)) {
		} else {
			$query = "INSERT INTO `defTranslations` (`trn_ID`,`trn_Source`,`trn_Code`,`trn_LanguageCode3`,`trn_Translation`) VALUES " . $resultSet;
			mysql_query($query);
			if(mysql_error()) {
				echo "TRANSLATIONS Error inserting data: " . mysql_error() . "<br />";
				$errorCreatingTables = TRUE;
			}
		}
	}
// END Imported first set of data to temp table: defTranslations

// Done creating all tables into temp database.

	if($errorCreatingTables) { // An error occurred while trying to create one (or more) of the tables, or inserting data into them
		if($isNewDB) {
			echo "<br /><strong>An error occurred trying to insert data into the new database.</strong><br />";
		} else {
			echo "<br /><strong>An error occurred trying to insert the downloaded data into the temporary database.</strong><br />";
		}
		mysql_query("DROP DATABASE `" . $tempDBName . "`"); // Delete temp database, and therefore cancelled entire process
		return;
	}
	else {
		if($isNewDB) {
			// Don't createCrosswalkTable
		} else {
			require_once("createCrosswalkTable.php");
		}
	}

	// TODO: Replace this line.
	$res = mysql_query("delete from sysLocks where lck_ID='1'"); // Remove sysLock
?>