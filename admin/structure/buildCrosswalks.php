<?php

	/*<!--
	* buildCrosswalks.php, Gets definitions from a specified installation of Heurist, 18-02-2011, by Juan Adriaanse
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
	?>
	<link rel="stylesheet" type="text/css" href="../../external/yui/2.8.2r1/build/fonts/fonts-min.css" />
	<html>
	<head>
<!--
	Row1 = column titles
	Row2 = rectypes without approximate matches (I gave them a green background)
	Row3 = rectype with approximate matches, so not safe to import without being sure
-->
	<style type="text/css">

	.Row1 {
		display: block;
	}

	.Row2 {
		background-color: #aebeff;
		display: block;
	}

	.Row3 {
		background-color: #cbdeff;
		display: block;
	}

	.Cell1 {
		float: none;
		width: 35%;
		display: inline-block;
	}

	.Cell2 {
		text-align: center;
		width: 5%;
		display: inline-block;
	}

	.Cell2-2 {
		text-align: center;
		display: inline-block;
		width: 5%;
		background-color: #ffb6b9;
	}

	.Cell3 {
		text-align: center;
		width: 15%;
		display: inline-block;
	}

	.Cell4 {
		width: 35%;
		display: inline-block;
	}

	.Cell5 {
		text-align: center;
		float: right;
		width: 7%;
		display: inline-block;
	}
	
	.tooltip {
	    position:absolute;
	    z-index:999;
	    left:-9999px;
	    background-color:#dedede;
	    padding:5px;
	    border:1px solid #fff;
	    width:30%;
	}

	.tooltip p {
	    margin:0;
	    padding:0;
	    color:#fff;
	    background-color:#222;
	    padding:2px 7px;
	}

	</style>
	<title>Heurist Definitions Importer / Crosswalk builder</title>
	</head>

	<body>
	
	<h1>Heurist</h1>
	<h2>Crosswalk</h2>
	
	<script type="text/javascript">
		var tempRecTypes = new Array();
		var crwSourceDBID = "";
		var tempDBName = "";
	</script>
	
	<?php

	// ------Administrative stuff ------------------------------------------------------------------------------------

	// Verify credentials of the user and check that they are an administrator, check no-one else is trying to
	// change the definitions at the same time


	// Requires admin user, access to definitions though get_definitions is open
	if (! is_admin()) {
		print "<html><body><p>You do not have sufficient privileges to access this page</p><p><a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME."'>Log out</a></p></body></html>";
		return;
	}

	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
	require_once(dirname(__FILE__).'/../../common/t1000/.ht_stdefs');
	require_once(dirname(__FILE__).'/../../common/t1000/t1000.php');

	$definitions_being_modified = FALSE;
	$server_offline = FALSE;
	$errorCreatingTables = FALSE;
	$localDBName = "" . DATABASE;
	$rowNumber = 0;
	$colorBlue = true;

	// Deals with all the database connections stuff
	mysql_connection_db_insert(DATABASE);

	// Create new temp database with timestamp
	$dateAndTime = date("dmygisa");
	$tempDBName = "hdb_temp_" . $dateAndTime;
	$tempDBName = "hdb_temp_temp"; // TO DO: Remove when done testing
	mysql_query("CREATE DATABASE `" . $tempDBName . "`");
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


// TO DO: Change three fields below to information about the database you will be importing from
	$source_db_id='1';
	$source_db_name='HeuristScholar Reference Database';
	$source_url="http://heuristscholar.org/h3-ja/admin/structure/getDBStructure.php?db=Reference&prefix=HeuristSystem_";

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
		error_log("$error ($code)" . " url = ". $_REQUEST['reg_url']);
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
			echo "Heurist reference database could not be accessed. Using 'fallbackDefinitionsIfOffline.txt' to create database instead.<br />";
		}
		else { // Cancel buildCrosswalk process as no data can be received
			echo "Source database could not be accessed.";
			die("Source database could not be accessed.");
		}
	}
	else {
		echo "Data succesfully retrieved from Heurist references database.<br /><br />";
	}

// Splits receiver data into data sets for one table
	$splittedData = split("> Start", $data);
	$tableNumber = 1;
	function getNextDataSet($splittedData) {
		global $tableNumber;
		$splittedData2 = split("> End", $splittedData[$tableNumber]);
		$i = 1;
		$size = strlen($splittedData2[0]);
		$testData = $splittedData2[0];
		if(!($testData[$size - $i] == ",")) {
			while((($size - $i) > 0) && (($testData[$size - $i]) != ",")) {
				if($i == 10) {
					$i = 0;
					break;
				}
				$i++;
			}
		}
		$splittedData3 = substr($splittedData2[0],0,-$i);
		$tableNumber++;
		return $splittedData3;
	}

	$dataSet = getNextDataSet($splittedData);

// TO DO: Make sure all values are written correctly (especially the NULL values)

// ------ Copy source DB definitions to temporary local tables ---------------------------------------------------

// BEGIN Importing to following data to temp table: defRecTypes
	$query = "CREATE TABLE IF NOT EXISTS `defRecTypes` (
	`rty_ID` smallint(5) unsigned NOT NULL,
	`rty_Name` varchar(63) NOT NULL,
	`rty_OrderInGroup` tinyint(3) unsigned default '0',
	`rty_Description` varchar(5000) NOT NULL,
	`rty_TitleMask` varchar(500) NOT NULL default '[title]',
	`rty_CanonicalTitleMask` varchar(500) default 160,
	`rty_Plural` varchar(63) default NULL,
	`rty_Status` enum('Reserved', 'Approved', 'Pending', 'Open') NOT NULL default 'Open',
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

	if(($dataSet == "") && !mysql_error()) {
	} else {
		$query = "INSERT INTO `defRecTypes` (`rty_ID`, `rty_Name`, `rty_OrderInGroup`, `rty_Description`, `rty_TitleMask`, `rty_CanonicalTitleMask`, `rty_Plural`, `rty_Status`, `rty_OriginatingDBID`, `rty_NameInOriginatingDB`, `rty_IDInOriginatingDB`, `rty_ShowInLists`, `rty_RecTypeGroupIDs`, `rty_FlagAsFieldset`, `rty_ReferenceURL`, `rty_AlternativeRecEditor`, `rty_Type`) VALUES" . $dataSet;
		mysql_query($query);
		if(mysql_error()) {
			echo "Error inserting data: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
	}
// END Imported first set of data to temp table: defRectypes

// BEGIN Importing to following data to temp table: defDetailTypes
	$resultSet =  getNextDataSet($splittedData);

	$query = "CREATE TABLE IF NOT EXISTS `defDetailTypes` (
	`dty_ID` smallint(5) unsigned NOT NULL,
	`dty_Name` varchar(255) NOT NULL,
	`dty_Documentation` varchar(5000) default 'Please document the nature of this detail type (field)) ...',
	`dty_Type` enum('freetext','blocktext','integer','date','year','relmarker','boolean','enum','resource','float','file','geo','separator','calculated', 'fieldsetmarker') NOT NULL,
	`dty_HelpText` varchar(255) NOT NULL default 'Please provide a short explanation for the user ...',
	`dty_ExtendedDescription` varchar(5000) default 'Please provide an extended description for display on rollover ...',
	`dty_Status` enum('Reserved','Approved','Pending','Open') NOT NULL default 'Open',
	`dty_OriginatingDBID` smallint(5) unsigned,
	`dty_NameInOriginatingDB` varchar(255),
	`dty_IDInOriginatingDB` smallint(5) unsigned,
	`dty_DetailTypeGroupID` tinyint(3) unsigned NOT NULL default '1',
	`dty_OrderInGroup` tinyint(3) unsigned default '0',
	`dty_PtrTargetRectypeIDs` varchar(63),
	`dty_JsonTermIDTree` varchar(500) default NULL,
	`dty_HeaderTermIDs` varchar(255) default NULL,
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

	if(($resultSet == "") && !mysql_error()) {
	} else {
		$query = "INSERT INTO `defDetailTypes` (`dty_ID`, `dty_Name`, `dty_Documentation`, `dty_Type`, `dty_HelpText`, `dty_ExtendedDescription`, `dty_Status`, `dty_OriginatingDBID`, `dty_NameInOriginatingDB`, `dty_IDInOriginatingDB`, `dty_DetailTypeGroupID`, `dty_OrderInGroup`, `dty_PtrTargetRectypeIDs`, `dty_JsonTermIDTree`, `dty_HeaderTermIDs`, `dty_FieldSetRecTypeID`, `dty_ShowInLists`) VALUES" . $resultSet;
		mysql_query($query);
		if(mysql_error()) {
			echo "Error inserting data: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
	}
// END Imported first set of data to temp table: defDetailTypes

// BEGIN Importing to following data to temp table: defRecStructure
	$resultSet =  getNextDataSet($splittedData);

	$query = "CREATE TABLE IF NOT EXISTS `defRecStructure` (
	`rst_ID` smallint(5) unsigned NOT NULL,
	`rst_RecTypeID` smallint(5) unsigned NOT NULL,
	`rst_DetailTypeID` smallint(5) unsigned NOT NULL,
	`rst_DisplayName` varchar(255) NOT NULL default 'Please enter a prompt ...',
	`rst_DisplayHelpText` varchar(255),
	`rst_DisplayExtendedDescription` varchar(5000),
	`rst_DisplayOrder` smallint(3) unsigned NOT NULL default '999',
	`rst_DisplayWidth` tinyint(3) unsigned NOT NULL default '50',
	`rst_DefaultValue` varchar(63),
	`rst_RecordMatchOrder` tinyint(1) unsigned NOT NULL default '0',
	`rst_CalcFunctionID` tinyint(3) unsigned,
	`rst_RequirementType` enum('Required', 'Recommended', 'Optional', 'Forbidden') NOT NULL default 'Optional',
	`rst_Status` enum('Reserved', 'Approved', 'Pending', 'Open') NOT NULL default 'Open',
	`rst_MayModify` enum('Locked', 'Discouraged', 'Open') NOT NULL default 'Open',
	`rst_OriginatingDBID` smallint(5) unsigned,
	`rst_IDInOriginatingDB` smallint(5) unsigned,
	`rst_MaxValues` tinyint(3) unsigned NOT NULL default '0',
	`rst_MinValues` tinyint(3) unsigned NOT NULL default '1',
	`rst_DisplayDetailTypeGroupID` tinyint(3) unsigned,
	`rst_EnumFilteredIDs` varchar(250),
	`rst_PtrFilteredIDs` varchar(250),
	`rst_OrderForThumbnailGeneration` tinyint(3) unsigned,
	PRIMARY KEY  (`rst_ID`),
	UNIQUE KEY `bdr_rectype` (`rst_RecTypeID`,`rst_DetailTypeID`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1";
	mysql_query($query);
	if(mysql_error()) {
		echo "Error creating table: " . mysql_error() . "<br />";
		$errorCreatingTables = TRUE;
	}

	if(($resultSet == "") && !mysql_error()) {
	} else {
		$query = "INSERT INTO `defRecStructure` (`rst_ID`, `rst_RecTypeID`, `rst_DetailTypeID`, `rst_DisplayName`, `rst_DisplayHelpText`, `rst_DisplayExtendedDescription`, `rst_DisplayOrder`, `rst_DisplayWidth`, `rst_DefaultValue`, `rst_RecordMatchOrder`, `rst_CalcFunctionID`, `rst_RequirementType`, `rst_Status`, `rst_MayModify`, `rst_OriginatingDBID`, `rst_IDInOriginatingDB`, `rst_MaxValues`, `rst_MinValues`, `rst_DisplayDetailTypeGroupID`, `rst_EnumFilteredIDs`, `rst_PtrFilteredIDs`, `rst_OrderForThumbnailGeneration`) VALUES " . $resultSet;
		mysql_query($query);
		if(mysql_error()) {
			echo "Error inserting data: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
	}
// END Imported first set of data to temp table: defRecStructure

// BEGIN Importing to following data to temp table: defTerms
	$resultSet =  getNextDataSet($splittedData);

	$query = "CREATE TABLE IF NOT EXISTS `defTerms` (
	`trm_ID` mediumint(8) unsigned NOT NULL,
	`trm_Label` varchar(63) NOT NULL,
	`trm_InverseTermId` mediumint(8) unsigned,
	`trm_Description` varchar(500),
	`trm_Status` enum('reserved','approved','pending','open') NOT NULL default 'open',
	`trm_OriginatingDBID` smallint(5) unsigned,
	`trm_AddedByImport` tinyint(1) unsigned NOT NULL default '0',
	`trm_IsLocalExtension` tinyint(1) unsigned NOT NULL default '0',
	`trm_NameInOriginatingDB` varchar(63),
	`trm_IDInOriginatingDB` smallint(5) unsigned,
	`trm_ParentTermID` mediumint(8) unsigned default NULL,
	`trm_Domain` enum('enum', 'reltype', 'reltypevocab', 'enumvocab') NOT NULL default 'enum',
	`trm_ChildCount` tinyint(3) NOT NULL default '0',
	`trm_ParentTermVocabID` mediumint(8) unsigned default NULL,
	`trm_Depth` tinyint(1) unsigned NOT NULL default '1',
	`trm_OntID` smallint(5) unsigned NOT NULL default '0',
	PRIMARY KEY  (`trm_ID`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1";
	mysql_query($query);
	if(mysql_error()) {
		echo "Error creating table: " . mysql_error() . "<br />";
		$errorCreatingTables = TRUE;
	}

	if(($resultSet == "") && !mysql_error()) {
	} else {
		$query = "INSERT INTO `defTerms` (`trm_ID`, `trm_Label`, `trm_InverseTermId`, `trm_Description`, `trm_Status`, `trm_OriginatingDBID`, `trm_AddedByImport`, `trm_IsLocalExtension`, `trm_NameInOriginatingDB`, `trm_IDInOriginatingDB`, `trm_ParentTermID`, `trm_Domain`, `trm_ChildCount`, `trm_ParentTermVocabID`, `trm_Depth`, `trm_OntID`) VALUES " . $resultSet;
		mysql_query($query);
		if(mysql_error()) {
			echo "Error inserting data: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
	}
// END Imported first set of data to temp table: defTerms

// BEGIN Importing to following data to temp table: defOntologies
	$resultSet =  getNextDataSet($splittedData);

	$query = "CREATE TABLE IF NOT EXISTS `defOntologies` (
	`ont_ID` smallint(5) unsigned NOT NULL,
	`ont_ShortName` varchar(128) NOT NULL,
	`ont_FullName` varchar(128) NOT NULL,
	`ont_Description` varchar(1000),
	`ont_RefURI` varchar(250),
	`ont_Status` enum('Reserved','Approved','Pending','Open') NOT NULL default 'Open',
	`ont_OriginatingDBID` smallint(5) unsigned,
	`ont_NameInOriginatingDB` varchar(64),
	`ont_IDInOriginatingDB` smallint(5) unsigned,
	`ont_Added` date default NULL,
	`ont_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
	PRIMARY KEY  (`ont_ID`),
	UNIQUE KEY `ont_ShortName` (`ont_ShortName`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1";
	mysql_query($query);
	if(mysql_error()) {
		echo "Error creating table: " . mysql_error() . "<br />";
		$errorCreatingTables = TRUE;
	}

	if(($resultSet == "") && !mysql_error()) {
	} else {
		$query = "INSERT INTO `defOntologies` (`ont_ID`,`ont_ShortName`,`ont_FullName`,`ont_Description`,`ont_RefURI`,`ont_Status`,`ont_OriginatingDBID`,`ont_NameInOriginatingDB`,`ont_IDInOriginatingDB`,`ont_Added`,`ont_Modified`) VALUES " . $resultSet;
		mysql_query($query);
		if(mysql_error()) {
			echo "Error inserting data: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
	}
// END Imported first set of data to temp table: defOntologies

// BEGIN Importing to following data to temp table: defRelationshipConstraints
	$resultSet =  getNextDataSet($splittedData);

	$query = "CREATE TABLE `defRelationshipConstraints` (
	`rcs_ID` smallint(5) unsigned NOT NULL auto_increment,
	`rcs_TermID` mediumint(8) unsigned default NULL,
	`rcs_SourceRectypeID` smallint(5) unsigned default NULL,
	`rcs_TargetRectypeID` smallint(5) unsigned default NULL,
	`rcs_Description` varchar(1000) default 'Please describe ...',
	`rcs_Status` enum('Reserved','Approved','Pending','Open') NOT NULL default 'Open',
	`rcs_OriginatingDBID` smallint(5) unsigned default NULL,
	`rcs_TermLimit` tinyint(3) unsigned default '0',
	`rcs_IDInOriginatingDB` smallint(5) unsigned default NULL,
	PRIMARY KEY  (`rcs_ID`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1";
	mysql_query($query);
	if(mysql_error()) {
		echo "Error creating table: " . mysql_error() . "<br />";
		$errorCreatingTables = TRUE;
	}

	if(($resultSet == "") && !mysql_error()) {
	} else {
		$query = "INSERT INTO `defRelationshipConstraints` (`rcs_ID`,`rcs_TermID`,`rcs_SourceRectypeID`,`rcs_TargetRectypeID`,`rcs_Description`,`rcs_Status`,`rcs_OriginatingDBID`,`rcs_TermLimit`,`rcs_IDInOriginatingDB`) VALUES " . $resultSet;
		mysql_query($query);
		if(mysql_error()) {
			echo "Error inserting data: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
	}
// END Imported first set of data to temp table: defRelationshipConstraints

// BEGIN Importing to following data to temp table: defFileExtToMimetype
	$resultSet =  getNextDataSet($splittedData);

	$query = "CREATE TABLE `defFileExtToMimetype` (
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
	if(($resultSet == "") && !mysql_error()) {
	} else {
		$query = "INSERT INTO `defFileExtToMimetype` (`fxm_Extension`,`fxm_MimeType`,`fxm_OpenNewWindow`,`fxm_IconFileName`,`fxm_FiletypeName`,`fxm_ImagePlaceholder`) VALUES " . $resultSet;
		mysql_query($query);
		if(mysql_error()) {
			echo "Error inserting data: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
	}
// END Imported first set of data to temp table: defFileExtToMimetype

// BEGIN Importing to following data to temp table: defRecTypeGroups
	$resultSet =  getNextDataSet($splittedData);

	$query = "CREATE TABLE `defRecTypeGroups` (
	`rtg_ID` tinyint(3) unsigned NOT NULL,
	`rtg_Name` varchar(40) NOT NULL,
	`rtg_Order` tinyint(3) unsigned zerofill NOT NULL default '255',
	`rtg_Description` varchar(250) default NULL,
	`rtg_Domain` enum('FunctionalGroup', 'ModelView') NOT NULL default 'FunctionalGroup',
	PRIMARY KEY  (`rtg_ID`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8";
	mysql_query($query);
	if(mysql_error()) {
		echo "Error creating table: " . mysql_error() . "<br />";
		$errorCreatingTables = TRUE;
	}

	if(($resultSet == "") && !mysql_error()) {
	} else {
		$query = "INSERT INTO `defRecTypeGroups` (`rtg_ID`,`rtg_Name`,`rtg_Order`,`rtg_Description`,`rtg_Domain`) VALUES " . $resultSet;
		mysql_query($query);
		if(mysql_error()) {
			echo "Error inserting data: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
	}
// END Imported first set of data to temp table: defRecTypeGroups

// BEGIN Importing to following data to temp table: defDetailTypeGroups
	$resultSet =  getNextDataSet($splittedData);

	$query = "CREATE TABLE `defDetailTypeGroups` (
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
	if(($resultSet == "") && !mysql_error()) {
	} else {
		$query = "INSERT INTO `defDetailTypeGroups` (`dtg_ID`,`dtg_Name`,`dtg_Order`,`dtg_Description`) VALUES " . $resultSet;
		mysql_query($query);
		if(mysql_error()) {
			echo "Error inserting data: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
	}
// END Imported first set of data to temp table: defDetailTypeGroups

// BEGIN Importing to following data to temp table: defTranslations
	$resultSet =  getNextDataSet($splittedData);

	$query = "CREATE TABLE `defTranslations` (
	`trn_ID` int(10) unsigned NOT NULL,
	`trn_Source` enum('rty_Name','dty_Name','ont_ShortName','vcb_Name','trm_Label','rst_DisplayName','rtg_Name','dtl_Value') NOT NULL,
	`trn_Code` smallint(5) unsigned NOT NULL,
	`trn_LanguageID` tinyint(3) NOT NULL,
	`trn_Translation` varchar(250) NOT NULL,
	PRIMARY KEY  (`trn_ID`),
	UNIQUE KEY `trn_SourceCodeLanguageKey` (`trn_Source`,`trn_Code`,`trn_LanguageID`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8";
	mysql_query($query);
	if(mysql_error()) {
		echo "Error creating table: " . mysql_error() . "<br />";
		$errorCreatingTables = TRUE;
	}
	if(($resultSet == "") && !mysql_error()) {
	} else {
		$query = "INSERT INTO `defTranslations` (`trn_ID`,`trn_Source`,`trn_Code`,`trn_LanguageID`,`trn_Translation`) VALUES " . $resultSet;
		mysql_query($query);
		if(mysql_error()) {
			echo "Error inserting data: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
	}
// END Imported first set of data to temp table: defTranslations

// BEGIN Importing to following data to temp table: defCalcFunctions
	$resultSet =  getNextDataSet($splittedData);

	$query = "CREATE TABLE `defCalcFunctions` (
	`cfn_ID` smallint(3) unsigned NOT NULL,
	`cfn_Domain` enum('CalcFieldString','PluginPHP') NOT NULL default 'CalcFieldString',
	`cfn_FunctionSpecification` text NOT NULL,
	PRIMARY KEY  (`cfn_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
	mysql_query($query);
	if(mysql_error()) {
		echo "Error creating table: " . mysql_error() . "<br />";
		$errorCreatingTables = TRUE;
	}

	if(($resultSet == "") && !mysql_error()) {
	} else {
		$query = "INSERT INTO `defCalcFunctions` (`cfn_ID`,`cfn_Domain`,`cfn_FunctionSpecification`) VALUES " . $resultSet;
		mysql_query($query);
		if(mysql_error()) {
			echo "Error inserting data: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
	}
// END Imported first set of data to temp table: defCalcFunctions

// BEGIN Importing to following data to temp table: defCrosswalk
	$resultSet =  getNextDataSet($splittedData);

	$query = "CREATE TABLE `defCrosswalk` (
	`crw_ID` mediumint(8) unsigned NOT NULL,
	`crw_SourcedbID` mediumint(8) unsigned NOT NULL,
	`crw_SourceCode` smallint(5) unsigned NOT NULL,
	`crw_DefType` enum('rectype','constraint','detailtype','recstructure','ontology','vocabulary','term') NOT NULL,
	`crw_LocalCode` smallint(5) unsigned NOT NULL,
	`crw_Modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`crw_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
	mysql_query($query);
	if(mysql_error()) {
		echo "Error creating table: " . mysql_error() . "<br />";
		$errorCreatingTables = TRUE;
	}

	if(($resultSet == "") && !mysql_error()) {
	} else {
		$query = "INSERT INTO `defCrosswalk` (`crw_ID`,`crw_SourcedbID`,`crw_SourceCode`,`crw_DefType`,`crw_LocalCode`,`crw_Modified`) VALUES " . $resultSet;
		mysql_query($query);
		if(mysql_error()) {
			echo "Error inserting data: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
	}
// END Imported first set of data to temp table: defCrosswalk

// BEGIN Importing to following data to temp table: defLanguage
	$resultSet =  getNextDataSet($splittedData);

	$query = "CREATE TABLE `defLanguage` (
	`lng_ID` smallint(5) unsigned NOT NULL,
	`lng_Name` varchar(63) NOT NULL,
	`lng_Notes` varchar(1000) default NULL,
	`lng_ISO639` char(2) default NULL,
	`lng_NISOZ3953` char(3) default NULL,
	PRIMARY KEY  (`lng_ID`)
	) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8";
	mysql_query($query);
	if(mysql_error()) {
		echo "Error creating table: " . mysql_error() . "<br />";
		$errorCreatingTables = TRUE;
	}

	if(($resultSet == "") && !mysql_error()) {
	} else {
		$query = "INSERT INTO `defLanguage` (`lng_ID`,`lng_Name`,`lng_Notes`,`lng_ISO639`,`lng_NISOZ3953`) VALUES " . $resultSet;
		mysql_query($query);
		if(mysql_error()) {
			echo "Error inserting data: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
	}
// END Imported first set of data to temp table: defLanguage

// BEGIN Importing to following data to temp table: defURLPrefixes
	$resultSet =  getNextDataSet($splittedData);

	$query = "CREATE TABLE `defURLPrefixes` (
	`urp_ID` smallint(5) unsigned NOT NULL,
	`urp_Prefix` varchar(250) NOT NULL,
	PRIMARY KEY  (`urp_ID`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8";
	mysql_query($query);
	if(mysql_error()) {
		echo "Error creating table: " . mysql_error() . "<br />";
		$errorCreatingTables = TRUE;
	}

	if(($resultSet == "") && !mysql_error()) {
	} else {
		$query = "INSERT INTO `defURLPrefixes` (`urp_ID`,`urp_Prefix`) VALUES " . $resultSet;
		mysql_query($query);
		if(mysql_error()) {
			echo "Error inserting data: " . mysql_error() . "<br />";
			$errorCreatingTables = TRUE;
		}
	}
// END Imported first set of data to temp table: defURLPrefixes

// Done creating all tables into temp database.

	if($errorCreatingTables) { // An error occurred while trying to create one (or more) of the tables, or inserting data into them
		echo "<br />An error occurred trying to create temporary tables.<br />";
		echo "Importing process will now be canceled.";
		mysql_query("DROP DATABASE `" . $tempDBName . "`"); // Delete temp database, and therefore cancelled entire process
		return;
	}
	else {
		echo "All temporary tables were created successfully. Select which rectypes you would like to import below:<br /><br />";
		createCrosswalkTable();
	}

	// Show records which are not already in the importing database. Check by comparing the OriginatingDBID and IDInOriginatingDB.
	function createCrosswalkTable() {
		global $rowNumber;
		global $allLocalRecTypes;
		global $colorBlue;
		echo "<strong><div id=\"statusText\"></div></strong>";
		echo "<div class=\"Row1\" id=\"crosswalkTable\" border='1'>";
		echo "<div class=\"Cell1\" ><strong>Downloaded rectype</strong></div>";
		echo "<div class=\"Cell2\" ><strong>Import</strong></div>";
		echo "<div class=\"Cell3\"><strong>Approximate matches</strong></div>";
		echo "<div class=\"Cell4\"><strong>Select match</strong></div>";
		echo "<div class=\"Cell5\"><strong>Crosswalk</strong></div>";
		echo "</div>";
		global $source_db_id, $localDBName, $tempDBName;
		$tempRecTypes = mysql_query("select rty_OriginatingDBID, rty_IDInOriginatingDB, rty_ID, rty_Name from $tempDBName" . ".defRecTypes");
		$rowNumber = 0;
		if($tempRecTypes) {
			while($row = mysql_fetch_row($tempRecTypes)) {
				$OriginatingDBID = $row[0];
				$IDInOriginatingDB = $row[1];
				$IDInTempDB = $row[2];
				$nameInTempDB = mysql_real_escape_string($row[3]);
				if($row[0] == "0") {
					$OriginatingDBID = $source_db_id;
					$IDInOriginatingDB = $row[2];
				}
				$identicalMatches = mysql_query("select rty_OriginatingDBID from $localDBName" . ".defRecTypes where rty_OriginatingDBID = $OriginatingDBID AND rty_IDInOriginatingDB = $IDInOriginatingDB"); // Compare OriginatingDBID and IDInOriginatingDB from temp database with importing one
				if(mysql_num_rows($identicalMatches) == 0) { // These RecTypes with are not in the importing database
				
					// Create a javascript (multidimensional) array, containing all unique rectypes, with their local ID and defType
					echo '<script type="text/javascript">';
					echo 'tempRecTypes[' . $rowNumber . '] = new Array(2);';
					echo 'tempRecTypes[' . $rowNumber . '][0] = "' . $row[2] . '";';
					echo 'tempRecTypes[' . $rowNumber . '][1] = "' . $row[3] . '";'; // TO DO: Change to $row[4] and add rty_Type after rty_Name in the query to $tempRecTypes, once the reference database has been updates to latest version of H3 structure
					echo 'crwSourceDBID = ' . $source_db_id . ';';
					echo "tempDBName = '" . $tempDBName . "';";
					echo '</script>';
				
					$approxMatches = mysql_query("select rty_ID, rty_Name from $localDBName" . ".defRecTypes where (rty_Name like '%$nameInTempDB%')"); // TO DO: Edit this line to look for approximate matches
					$numberOfRows = mysql_num_rows($approxMatches);
					if($numberOfRows == 0) {
						if($colorBlue) {
							echo "<div class=\"Row2\">";
						} else {
							echo "<div class=\"Row3\">";
						}
						echo "<div class=\"Cell1\">". $row[3] ."</div>";
						echo "<div class=\"Cell2\"><button onclick=\"importRecType(this, " . $rowNumber . ")\">Import</button></div>";
						echo "<div class=\"Cell3\">-</div>";
						echo "<div class=\"Cell4\"><select id=\"selectNumber" . $rowNumber . "\">";
						echo "</select> <a href=\"javascript:void(0)\" id=\"showAll\" onClick=\"showAllRecTypes('selectNumber" . $rowNumber . "', this)\">(show all rectypes)</a></div>";
						echo "<div class=\"Cell5\"><button onclick=\"addToCrosswalk(this, " . $rowNumber . ")\">Match</button></div>";
						echo "</div>";
					}
					else {
						$nonMatches = mysql_query("select rty_ID, rty_Name from $localDBName" . ".defRecTypes where (rty_Name not like '%$nameInTempDB%')");
					// TO DO: Exactly matching name red instead of yellow?
						if($colorBlue) {
							echo "<div class=\"Row2\">";
						} else {
							echo "<div class=\"Row3\">";
						}
						echo "<div class=\"Cell1\">". $row[3] ."</div>";
						echo "<div class=\"Cell2-2\"><button onclick=\"importRecType(this, " . $rowNumber . ")\">Import</button></div>";
						echo "<div class=\"Cell3\">$numberOfRows <a title=\"";
						$approxMatchesArray = array();
						$matchesIndex = 0;
							$newRecTypeInfo = mysql_query("select rst_ID from $tempDBName".".defRecStructure where rst_RecTypeID =" . $row[2]);
							echo $row[3] . " - Details: " . mysql_num_rows($newRecTypeInfo) . "<br /><br /><br />";
							while($info = mysql_fetch_row($approxMatches)) { // Details that appear in the tooltip, when mouseover approximate matches
								$recTypeDetails = mysql_query("select rst_ID from $localDBName".".defRecStructure where rst_RecTypeID = " . $info[0]);
								echo $info[1] . " - Details: " . mysql_num_rows($recTypeDetails) . "<br />";
								$approxMatchesArray[$matchesIndex][0] = $info[0];
								$approxMatchesArray[$matchesIndex][1] = $info[1];
								$matchesIndex++;
							}
/* 							Hier informatie! */
						echo "\">(i)</a></div>";
						echo "<div class=\"Cell4\"><select id=\"selectNumber" . $rowNumber . "\">";
						echo "<optgroup label=\"Approximate Matches\">";
						$tempIndex = 0;
						while($tempIndex < $matchesIndex) {
							echo "<option value=\"" . $approxMatchesArray[$tempIndex][0] . "\">" . $approxMatchesArray[$tempIndex][1] . "</option>";
							$tempIndex++;
						}
						echo "</optgroup>";
						echo "<optgroup label=\"No Matches\">";
						while($row3 = mysql_fetch_row($nonMatches)) {
							echo "<option value=\"" . $row3[0] . "\">" . $row3[1] . "</option>";
						}
						echo "</optgroup></select></div>";
						echo "<div class=\"Cell5\"><button onclick=\"addToCrosswalk(this, " . $rowNumber . ")\">Match</button></div>";
						echo "</div>";
					}
					if($colorBlue) {
						$colorBlue = false;
					} else {
						$colorBlue = true;
					}
					$rowNumber++;
				}
			}
		}
	}
	// TO DO: Replace this line.
	$res = mysql_query("delete from sysLocks where lck_ID='1'"); // Remove sysLock

	$allLocalRecTypes = mysql_query("select rty_ID, rty_Name from $localDBName" . ".defRecTypes");
?>

	<script type="text/javascript">
	// Create a javascript (multidimensional) array with every rectype in the local database
	var allRecTypes = new Array();
	<?php
	if($allLocalRecTypes) {
		$count = 0;
		while ($recType = mysql_fetch_row($allLocalRecTypes)) {
			echo 'allRecTypes[' . $count . '] = new Array(2);';
			echo 'allRecTypes[' . $count . '][0] = "' . $recType[0] . '";';
			echo 'allRecTypes[' . $count . '][1] = "' . $recType[1] . '";';
			$count++;
		}
	}
	?>

	// Change dropdown menu to show every rectype in local database, in case the matching table is not in the 'approximate' dropdown menu
	function showAllRecTypes(dropdownName, text) {
		var dropdownmenu = document.getElementById(dropdownName);

		dropdownmenu.options.length = 0;
		var recTypeNumber = 0;
		while (recTypeNumber < allRecTypes.length-1) {
			var optn = document.createElement("option");
			optn.value = allRecTypes[recTypeNumber][0];
			optn.text = allRecTypes[recTypeNumber][1];
			dropdownmenu.options.add(optn);
			recTypeNumber++;
		}
		text.innerHTML="";
	}
	
	// Import rectype from source database, on the row where the button was pressed (rowNumber). row can change when deleting table rows
	function importRecType(row, rowNumber) {
		crwSourceCode = tempRecTypes[rowNumber][0];
		processAction(row, "import");
	}
	
	// These will be set in addToCrosswalk() and importRecType(), so processAction() can use them
	var crwSourceCode = "";
	var crwDefType = "";
	var crwLocalCode = "";
	// Add entry to crosswalk table on localdatabase. Al information is retrieved from the row where the button was pressed (rowNumber)
	function addToCrosswalk(row, rowNumber) {
		var dropdownName = "selectNumber" + rowNumber;
		var dropdownmenu = document.getElementById(dropdownName);

		crwLocalCode = dropdownmenu.value;
		if(crwLocalCode == "none" || crwLocalCode == "") {
			alert('Please select a rectype when matching something with the newly downloaded rectype.');
		}
		else {
			crwSourceCode = tempRecTypes[rowNumber][0];
			crwDefType = tempRecTypes[rowNumber][1];
	  		processAction(row, "crosswalk");
		}
	}
	
	var replaceRecTypeName = "";
	function changeDuplicateEntryName(row) {
		var newRecTypeName = prompt("An entry with the exact same name already exist.\n\nPlease enter a new name for this rectype, or cancel to stop importing it.","");
		if(newRecTypeName == null || newRecTypeName == "") {
			document.getElementById("statusText").style.color = "red";
			document.getElementById("statusText").innerHTML="You have to enter a valid new name to import a new rectype with an existing name.";
		}
		else {
			replaceRecTypeName = newRecTypeName;
			processAction(row, "import");
		}
	}

	function processAction(row, action) {
		var xmlhttp;
		if (action.length == 0) {
			document.getElementById("statusText").innerHTML="";
			return;
		}
		if (window.XMLHttpRequest) { // code for IE7+, Firefox, Chrome, Opera, Safari
			xmlhttp = new XMLHttpRequest();
		}
		else { // code for IE6, IE5
			xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
		}
		xmlhttp.onreadystatechange=function() {
			if (xmlhttp.readyState==4 && xmlhttp.status==200) {

				var response = xmlhttp.responseText;
				if(response.substring(0,6) == "prompt") {
					changeDuplicateEntryName(row);
				}
				else if(response.substring(0,5) == "Error") {
					document.getElementById("statusText").style.color = "red";
					document.getElementById("statusText").innerHTML=response;
				}
				else {
					document.getElementById("statusText").style.color = "green";
					document.getElementById("statusText").innerHTML=response;
					row.parentNode.parentNode.parentNode.removeChild(row.parentNode.parentNode);
/* 					document.getElementById('crosswalkTable').deleteRow(rowIndex); */
				}

			}
			else {
			}
		}
		xmlhttp.open("GET","processAction.php?action="+action+"&tempDBName="+tempDBName+"&crwSourceDBID="+crwSourceDBID+"&crwSourceCode="+crwSourceCode+"&crwDefType="+crwDefType+"&crwLocalCode="+crwLocalCode+"&replaceRecTypeName="+replaceRecTypeName,true);
		xmlhttp.send();
		}
	</script>

	<script type="text/javascript" src="http://code.jquery.com/jquery-latest.js"></script>
	<script type="text/javascript">
		function showTooltip(target_items, name) {
			$(target_items).each(function(i) {
			$("body").append("<div class='"+name+"' id='"+name+i+"'><p>"+$(this).attr('title')+"</p></div>");
			var my_tooltip = $("#"+name+i);
	
			if($(this).attr("title") != "" && $(this).attr("title") != "undefined" ) {
	
			$(this).removeAttr("title").mouseover(function() {
						my_tooltip.css({opacity:0.8, display:"none"}).fadeIn(50);
			}).mousemove(function(kmouse) {
					var border_top = $(window).scrollTop();
					var border_right = $(window).width();
					var left_pos;
					var top_pos;
					var offset = 15;
					if(border_right - (offset *2) >= my_tooltip.width() + kmouse.pageX) {
						left_pos = kmouse.pageX+offset;
						} else {
						left_pos = border_right-my_tooltip.width()-offset;
						}
	
					if(border_top + (offset *2)>= kmouse.pageY - my_tooltip.height()) {
						top_pos = border_top +offset;
						} else{
						top_pos = kmouse.pageY-my_tooltip.height()-offset;
						}
	
					my_tooltip.css({ left:left_pos, top:top_pos });
			}).mouseout(function(){
					my_tooltip.css({left:"-9999px"});
			});
	
			}
	
		});
	}
	
	$(document).ready(function(){
		 showTooltip("a","tooltip");
	});
	</script>
	
	</body>

	</html>