<?php

	/*<!--
	* filename, brief description, date of creation, by whom
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
	// without importing new definitions, in other words jsut setting up the crosswalk to be able to send queries
	// and/or download data from another instance.

	echo "test";
	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

	if (!is_logged_in()) {
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}
    
    ?>
    <html>
    <head>
    <title>Heurist Definitions Importer / Crosswalk builder</title>
    </head>
    
    <body>
    Reading data for crosswalking ...
    </body>
    
    </html>
    
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

	// Deals with all the database connections stuff

	mysql_connection_db_select(DATABASE);

	$version = 1; // Definitions exchange format version number. Update in get_definitions and here

	// -----Check not locked by admin -------------------------------

	// THIS SECTION SHOULD BE ABSTRACTED AS A FUNCTION IN ONE OF THE LIBRARIES, perhaps in cred.php?

	// ???? we should now mark the target (current)database for administrator access to avoid two administrators
	// working on this at the same time. But need to provide a means of removing lock in case the
	// connection is lost, eg. heartbeat on subsequent pages or a specific 'remove admin lock' link (easier)

	// ??? $definitions_being_modified needs to be a global variable, presumably it needs to be stored in
	// the database sys_definitions table record #1 and then updated at the end
	$query = "select sys_definitions_being_modified from sys_definitions where sys_id=1";
	$res = mysql_query($query);
	while ($row = mysql_fetch_assoc($res)) {
		$definitions_being_modified=$row[sys_definitions_being_modified];
	};

	if ($definitions_being_modified) {
		// ???? NEED A WARNING MESSAGE HERE
		// "Another administrator is modifying the definitions"
		// "If this is not the case, use 'Remove lock on database definition modification' from the administration page"
		// "Click [continue] to return to the administration page"
		header('Location: ' . BASE_PATH . 'admin/index.php'); // return to the adminstration page
		return;
	}

	// mark database definitons as being modified by adminstrator
	$definitions_being_modified=TRUE;
	$query = "update sys_definitions set sys_definitions_being_modified='TRUE' where sys_id=1";
	$res = mysql_query($query);


	// ------Find and set the source database-----------------------------------------------------------------------

	// ??? Query reference.heuristscholar.org to find the URL of the installation you want to use as source
	// or perhaps simply enter the ID if you know it (initially we can use this to query db #1 for testing)
	// The query should be based on DOAP metadata and keywords which Steven is due to set up in the reference database

	// ???? This does not really need to be done now, we can live with entering specific reference numbers
	// ???? Add a temporary popup form requesting a Heurist database ID and query Heurist reference database
	// ???? for the database title and the URL to the database
	// ???? the URL should be set from the search results on the reference database

	// THIS WILL DO FINE FOR TESTING
	$source_db_id=1;
	$source_db_name='HeuristScholar Reference Database';
	$source_url="http://reference.heuristscholar.org/admin/describe/get_definitions.php"; // change to new name


	// ------Copy source DB definitions to temporary local tables ---------------------------------------------------

	// RECORD TYPES (this will be repeated for each of the tables)

	// ????: SEND THIS TO mysqL

	DROP TABLE IF EXISTS `temp_rec_types`;
	CREATE TABLE IF NOT EXISTS `temp_rec_types` (
	`rty_ID` tinyint(3) unsigned NOT NULL,
	`rty_Name` varchar(63) NOT NULL,
	`rty_OrderInGroup` tinyint(3) unsigned NOT NULL default '0',
	`rty_Description` blob,
	`rty_RecTypeGroupID` tinyint(1) default NULL,
	`rty_TitleMask` varchar(255) default NULL,
	`rty_CanonicalTitleMask` varchar(255) default NULL,
	`rty_Plural` varchar(63) default NULL,
	PRIMARY KEY  (`rty_ID`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

	INSERT INTO `temp_rec_types` (`rty_ID`, `rty_Name`, `rty_OrderInGroup`, `rty_Description`, `rty_RecTypeGroupID`, `rty_TitleMask`, `rty_CanonicalTitleMask`, `rty_Plural`) VALUES


	// ????: Parse the stream from get_definitions until you get to a line with    > Start
	// then send the stream to MySQL until you get to a line with   > End
	// Need to send a closing semicolon

	// ------------------------------------------------------------
	// RELATIONSHIP CONSTRAINTS

	// ????: SEND THIS TO mysqL

	DROP TABLE IF EXISTS `temp_def_rectype_detailtype_constraints`;
	CREATE TABLE IF NOT EXISTS `temp_def_rectype_detailtype_constraints` (
	`rdtc_id` smallint(6) unsigned NOT NULL,
	`rdtc_is_reserved` Boolean Not Null default False,
	`rdtc_rdt_id` smallint(6) unsigned NOT NULL default '0',
	`rdtc_source_rt_id` smallint(6) unsigned NOT NULL default '0',
	`rdtc_target_rt_id` smallint(6) unsigned NOT NULL default '0',
	`rdtc_rdl_ids` varchar(255) character set utf8 default NULL,
	`rdtc_vocab_id` smallint(6) unsigned NOT NULL,
	`rdtc_description` text character set utf8,
	PRIMARY KEY  (`rdtc_id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

	// ------------------------------------------------------------------------------------------
	// RECORD DETAIL TYPES

	// Steve: SEND THIS TO mysqL

	DROP TABLE IF EXISTS `temp_rec_detail_types`;
	CREATE TABLE IF NOT EXISTS `temp_rec_detail_types` (
	`dty_ID` smallint(6) NOT NULL,
	`dty_Name` varchar(255) default NULL,
	`dty_Documentation` text,
	`dty_Type` enum('freetext','blocktext','integer','date','year','person lookup','boolean','enum','resource','float','file','geo','separator') default NULL,
	`dty_HelpText` varchar(255) default NULL,
	`dty_ExtendedDescription` text,
	`dty_PtrTargetRectypeIDs` smallint(6) default NULL,
	PRIMARY KEY  (`dty_ID`),
	UNIQUE KEY `bdt_name` (`dty_Name`),
	KEY `bdt_type` (`dty_Type`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

	INSERT INTO `temp_rec_detail_types` (`dty_ID`, `dty_Name`, `dty_Documentation`, `dty_Type`,`dty_HelpText`, `dty_ExtendedDescription`, `dty_PtrTargetRectypeIDs`) VALUES

	// ????: Parse the stream from get_definitions until you get to a line with    > Start
	// then send the stream to MySQL until you get to a line with   > End
	// Need to send a closing semicolon


	// ------------------------------------------------------------
	// RECORD DETAIL REQUIREMENTS

	// Steve: SEND THIS TO mysqL

	DROP TABLE IF EXISTS `temp_rec_detail_requirements`;
	CREATE TABLE IF NOT EXISTS `temp_rec_detail_requirements` (
	`rst_ID` smallint(6) NOT NULL,
	`rst_RecTypeID` smallint(5) unsigned NOT NULL default '0',
	`rst_DetailTypeID` smallint(6) NOT NULL default '0',
	`rst_RequirementType` enum('required','recommended','optional','forbidden') NOT NULL default 'optional',
	`rst_DisplayName` varchar(255) default NULL,
	`rst_DisplayDescription` text,
	`rst_DisplayPrompt` text,
	`rst_DisplayHelp` varchar(255) default NULL,
	`rst_MaxValues` tinyint(1) NOT NULL default '0',
	`rst_DisplayOrder` smallint(6) default NULL,
	`rst_DisplayWidth` smallint(6) default NULL,
	`rst_DefaultValue` varchar(255) default NULL,
	`rst_RecordMatchOrder` tinyint(1) NOT NULL,
	PRIMARY KEY  (`rst_ID`),
	UNIQUE KEY `bdr_rectype` (`rst_RecTypeID`,`rst_DetailTypeID`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

	INSERT INTO `temp_rec_detail_requirements` (`rst_ID`, `rst_RecTypeID`, `rst_DetailTypeID`, `rst_RequirementType`, `rst_DisplayName`, `rst_DisplayDescription`, `rst_DisplayPrompt`, `rst_DisplayHelp`, `rst_MaxValues`, `rst_DisplayOrder`, `rst_DisplayWidth`, `rst_DefaultValue`, `rst_RecordMatchOrder`) VALUES

	// ????: Parse the stream from get_definitions until you get to a line with    > Start
	// then send the stream to MySQL until you get to a line with   > End
	// Need to send a closing semicolon

	// ------------------------------------------------------------------------------------------
	// ------------------------------------------------------------
	// RECORD DETAIL TYPES

	// ????: SEND THIS TO mysqL

	DROP TABLE IF EXISTS `temp_def_detailtypes`;
	CREATE TABLE IF NOT EXISTS `temp_def_detailtypes` (
	`dty_id` smallint(6) NOT NULL,
	`dty_is_reserved` Boolean Not Null default False,
	`dty_name` varchar(255) default NULL,
	`dty_Documentation` text,
	`dty_type` enum('freetext','blocktext','integer','date','year','person lookup','boolean','enum','resource','float','file','geo','separator') default NULL,
	`dty_HelpText` varchar(255) default NULL,
	`dty_ExtendedDescription` text,
	`dty_constrain_rec_type` smallint(6) default NULL,
	PRIMARY KEY  (`dty_id`),
	UNIQUE KEY `dty_name` (`dty_name`),
	KEY `dty_type` (`dty_type`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

	// ------------------------------------------------------------------------------------------
	// RECORD DETAIL LOOKUPS

	// Steve: SEND THIS TO mysqL

	DROP TABLE IF EXISTS `temp_rec_detail_lookups`;
	CREATE TABLE IF NOT EXISTS `temp_rec_detail_lookups` (
	`trm_ID` smallint(6) NOT NULL auto_increment,
	`trm_VocabID` smallint(6) default NULL,
	`trm_Label` varchar(63) default NULL,
	`trm_InverseTermID` smallint(6) default NULL,
	PRIMARY KEY  (`trm_ID`),
	UNIQUE KEY `bdl_bdt_id` (`trm_VocabID`,`trm_Label`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

	INSERT INTO `temp_rec_detail_lookups` (`trm_ID`, `trm_VocabID`, `trm_Label`, `trm_InverseTermID`) VALUES


	// ????: Parse the stream from get_definitions until you get to a line with    > Start
	// then send the stream to MySQL until you get to a line with   > End
	// Need to send a closing semicolon

	// ------------------------------------------------------------------------------------------
	// ------------------------------------------------------------------------------------------
	// ONTOLOGIES

	// Steve: SEND THIS TO mysqL

	DROP TABLE IF EXISTS `temp_vocabularies`;
	CREATE TABLE IF NOT EXISTS `temp_vocabularies` (
	`vcb_ID` smallint(6) unsigned NOT NULL,
	`vcb_Name` varchar(64) character set utf8 NOT NULL,
	`vcb_Description` text character set utf8,
	`vcb_RefURL` varchar(128) character set utf8 default NULL,
	`vcb_Added` date default NULL,
	`vcb_Modified` date default NULL,
	PRIMARY KEY  (`vcb_ID`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

	INSERT INTO `temp_vocabularies` (`vcb_ID`, `vcb_Name`, `vcb_Description`, `vcb_RefURL`, `vcb_Added`, `vcb_Modified`) VALUES


	// ????: Parse the stream from get_definitions until you get to a line with    > Start
	// then send the stream to MySQL until you get to a line with   > End
	// Need to send a closing semicolon


	// ------------------------------------------------------------
	// DETAIL enums

	// ????: SEND THIS TO mysqL

	// Steve: SEND THIS TO mysqL

	DROP TABLE IF EXISTS `temp_rel_constraints`;
	CREATE TABLE IF NOT EXISTS `temp_rel_constraints` (
	`rcs_ID` smallint(6) unsigned NOT NULL,
	`rcs_DetailtypeID` smallint(6) unsigned NOT NULL default '0',
	`rcs_SourceRectypeID` smallint(6) unsigned NOT NULL default '0',
	`rcs_TargetRectypeID` smallint(6) unsigned NOT NULL default '0',
	`rcs_TermIDs` varchar(255) character set utf8 default NULL,
	`rcs_VocabID` smallint(6) unsigned NOT NULL,
	`rcs_Description` text varchar(1000) character set utf8 default "Please describe ...",
	`rcs_TermLimit` tinyint(1) unsigned Not Null default '0',
	`rcs_RelationshipsLimit` tinyint(1) unsigned Not Null default '0',
	PRIMARY KEY  (`rcs_ID`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

	INSERT INTO `temp_rel_constraints` (`rcs_ID`, `rcs_DetailtypeID`, `rcs_SourceRectypeID`, `rcs_TargetRectypeID`, `rcs_TermIDs`, `rcs_VocabID`, `rcs_Description`) VALUES


	// ????: Parse the stream from get_definitions until you get to a line with    > Start
	// then send the stream to MySQL until you get to a line with   > End
	// Need to send a closing semicolon



	// ------Select definitions for import, including dependancies ---------------------------------------------------

	// INTERFACE TO ALLOW ADDITION OF DEFINTIONS TO THE CURRENT HEURIST INSTALLATION
	// AND UPDATE OF CROSSWALK_DEFINITIONS TABLE

	// First we select record types. This determines the set of applicable constraints
	// Record types + constraints determine the set of detail types and vocabs/enums required
	// user cannot remove any detail type, vocab or enum selected in this way through this interface
	// if they want to modify things further they will have to do that in the definitions edit function

	$rectypeset = array(); // empty set of record types selected
	$constraintset=array(); // empty set of selected constraints
	$detailtypeset=array(); // empty set of detail types required/selected (req. by rectypes + constraints)
	$vocabset=array(); // empty set of vocabs required/selected (req. by constraints + detail types)
	$enumset=array(); // empty set of enum values required/selected  (req. by vocabs)
	// Note: we don't load partial vocabs for required vocabs because that can get us in a lot of trouble


	// ???? TO ADD: we need to filter out the codes marked as Reserved, as they should either be added to the database at
	// ???? database creation time or should be added automatically by this process before it does anything else


	// ----Step 1: Record types -----------------------------------------------------------

	// Select record types to be transferred.

	// ???? SET THIS UP AS A FORM

	print "\nImporting RECORD TYPES from source db $source_db_id : $source_db_name \n";
	print "\nRecord types already imported from this database are not shown"
	// Query eliminates rectypes from the source database which are already in crosswalk table
	$query = "Select * from temp_def_rectypes order by rt_name where not
	(Select count(*) from def_crosswalk where
	crw_source_db_id=$current_source_db AND
	crw_def_type='rectype' AND
	crw_source_code=temp_def_rectypes.rt_id AND
	NOT rt_is_reserved)"; // assume reserved rectypes already in database
	$res = mysql_query($query);
	while ($row = mysql_fetch_assoc($res))
	{
		// ???? HERE WE NEED A CHECKBOX TO ALLOW USER TO SELECT THE RECORD TYPE,
		print "$row[rt_id],$row[rt_name],$row[rt_category]\n";
	};

	// ???? PROCESS THE FORM SUBMISSION, SET VALUES IN $rectypeset
	$rectypeset = ????? // set of record types to be added

	// ???? Build the set of detail types in $detailtypeset required by the record types chosen
	// Use temp_def_detailtypes_for_rectype.dfr_detailtype_id where dfr_rectype IN $rectypeset


	// ----Step 2: Constraints -----------------------------------------------------------

	// Find any constraints dependant on these record types - that is, any constraint which
	// includes one of the selected record types as either a source or a target record type.
	// Since none of the selected record types yet exists in the current database, we don't
	// have to look for existing crosswalks involving them as they cannot exist in our current database

	$query = "Select * from temp_def_rectype_detailtype_constraints where
	??? rdtc_source_rectype_id NOT IN rectypeset AND
	??? rdtc_target_rectype_id NOT IN rectypeset";

	$res = mysql_query($query);
	while ($row = mysql_fetch_assoc($res))
	{
		// ???? Build the set of detail types ($detailtypeset) required by the constraints for the chosen record types
		// ???? ADD temp_def_rectype_detailtype_constraints.rdtc_detailtype_id
		// ???? ADD the constraint to $constraintset
		// Note: we don't give user the option of not including constraints, they can delete them through admin interface
		};


	// ----Step 3: Detail types -----------------------------------------------------------

	// Display available detail types (those not yet imported from the source database) and allow
	// the user to select the oens they want to import. Those detail types required by the record types,
	// or constraints selected are checked and disabled - too complicated to maintain integrity if we
	// allow the user to start fiddling with the composition of record types and cosntraints in this function
	// they can use the admin interface later to edit the definitions they have imported

	// Note: some of the required detail types may already be in our database. They will not show in the list below

	// ???? SET THIS UP AS A FORM

	print "\nImporting DETAIL (FIELD) TYPES from source db $source_db_id : $source_db_name \n";
	print "\nDetail types already imported from this database are not shown"
	// Query eliminates detailtypes from the source database which are already in crosswalk table
	$query = "Select * from temp_def_detailtypes order by dty_name where not
	(Select count(*) from def_crosswalk where
	crw_source_db_id=$current_source_db AND
	crw_def_type='detailtype' AND
	crw_source_code=temp_def_detailtypes.dty_id AND
	NOT dty_is_reserved)"; // assume reserved detailtypes already in database
	$res = mysql_query($query);
	while ($row = mysql_fetch_assoc($res))
	{
		// ???? HERE WE NEED A CHECKBOX TO ALLOW USER TO SELECT THE DETAIL TYPE
		// ???? ANY DETAILTYPE IN $detailtypeset (ie MENTIONED IN A SELECTED RECORDTYPE OR CONSTRAINT)
		// SHOULD BE CHECKED AND DISABLED
		print "$row[dty_id],$row[dty_name],$row[dty_type],$row[dty_HelpText]\n";
	};

	// ???? PROCESS THE FORM SUBMISSION, ADD ANY ADDITIONAL DETAIL TYPES CHECKED ON THE FORM TO $detailtypeset
	$detailtypeset = ????? // now contains full set of detail types to be added

	// ----Step 4: Vocabularies -----------------------------------------------------------

	// Display available vocabularies (those not yet imported from the source database) and allow
	// the user to select the ones they want to import. Those vocabularies required by the detail types
	// or constraints selected are checked and disabled - too complicated to maintain integrity if we
	// allow the user to start fiddling with the composition of detail types and constraints in this function
	// they can use the admin interface later to edit the definitions they have imported

	// Note: some of the required vocabularies may already be in our database. They will not show in the list below

	// ???? Build the set of vocabularies ($vocabset) required by the detail types and by the constraints
	// Record types don't directly reference vocabularies or enums
	// 1. For constraints:
	//         def_rectype_detailtype_constraints.rdtc_vocab_id where rdtc_detailtype_id IN $detailtypeset
	// 2. For detail types:
	//         distinct def_detail_enums.enum_vocab_id where enum_detailtype_id in $detailtypeset
	//         distinct def_detail_enums.enum_vocab_id where def_detailtypes.dty_use_enums_from_dty_id in $detailtypeset
	//           (dty_use_enums_from_dty_id allows a detail type to 'pretend' to be another)

	// ???? SET THIS UP AS A FORM

	print "\nImporting VOCABULARIES from source db $source_db_id : $source_db_name \n";
	print "\nVocabularies already imported from this database are not shown"
	// Query eliminates vocabularies from the source database which are already in crosswalk table
	$query = "Select * from temp_def_vocabs order by vocab_name where not
	(Select count(*) from def_crosswalk where
	crw_source_db_id=$current_source_db AND
	crw_def_type='vocabulary' AND
	crw_source_code=temp_def_vocabs.vocab_id AND
	NOT vocab_is_reserved)"; // assume reserved vocabtypes already in database
	$res = mysql_query($query);
	while ($row = mysql_fetch_assoc($res))
	{
		// ???? HERE WE NEED A CHECKBOX TO ALLOW USER TO SELECT THE VOCABULARY
		// ???? ANY VOCABULARY IN $vocabset (ie IN A SELECTED CONSTRAINT OR DETAILTYPE) IS CHECKED AND DISABLED
		print "$row[vocab_id],$row[vocab_name],$row[vocab_url]\n";
	};

	// ???? PROCESS THE FORM SUBMISSION, ADD ANY ADDITIONAL VOCABULARIES CHECKED ON THE FORM TO $vocabset
	$vocabset = ????? // now contains full set of vocabularies to be added

	// -----Step 5: Add enum values for our vocabs --------------------

	// Select available enums (those not yet imported from the source database) for the vocabs selected.
	// My first approach was to allow selection. But I think this is unecessary/undesirable/tedious for user
	// (you could find yourself scrolling through and checking hundreds or even thousands of enum values
	// where all you really want is all the ones for the vocabs you're interested in).
	// If you select a vocab you should get the lot. If you don't like it, you can use the admin interface to delete
	// bits of it after the event, when it can be properly controlled.

	// Query eliminates enum values from the source database which are already in crosswalk table
	$query = "Select * from temp_def_enums where not
	(Select count(*) from def_crosswalk where
	crw_source_db_id=$current_source_db AND
	crw_def_type='enum' AND
	crw_source_code=temp_def_enums.enum_id AND
	NOT enum_is_reserved)"; // assume reserved enums already in database
	$res = mysql_query($query);
	while ($row = mysql_fetch_assoc($res))
	{
		if $row[enum_vocab] IN $vocabset // this is an enum from a selected vocabulary
		(ADD $row[enum_id] TO $enumset) AND (ADD $row[enum_inverse_enum_id] to $enumset IF NOT ALREADY THERE);
	};


	// -----Write new definitions into current database -------------------------------------------------------------

	// We now have all the sets of record types, constraints, detail types, vocabularies and enum values
	// stored in arrays waiting to be written into our database
	// When writing the definition table we need to store the codes used for each definition in the current database
	// When writing the crosswalk table we need to write the soruce database code and the current database code

	// ???? Open a transaction

	// Step 6a. Write record types from $rectypeset: add to def_rectypes and crosswalk

	// Step 6b. Write constraints  from $constraintset: add to def_rectype_detailtype_constraints and crosswalk

	// Step 6c. Write detail types from $detailtypeset: add to def_detailtypes and crosswalk
	// Warning: some of the required detail types may already be in our database. They will not show in the
	// list below but may be in $detailtypeset. We need to be careful they are not added twice to crosswalk
	// table (which might in any case result in a duplicate key)

	// Step 6d. Write vocabularies from $vocabset: add to def_vocabularies and crosswalk
	// Warning: some of the required vocabularies may already be in our database. They will not show in the
	// list below but amy be in $vocabset. We need to be careful they are not added twice to crosswalk
	// table (which might in any case result in a duplicate key)

	// Step 6e. Write enum values from $enumset: add to def_enumvalues and crosswalk
	// Warning: some of the required enums may already be in our database. They will not show in the
	// list below but amy be in $enumset. We need to be careful they are not added twice to crosswalk
	// table (which might in any case result in a duplicate key)


	// ???? Complete transaction

	// Give an 'all done'' message

	// TIDY UP TEMPORARY FILES

	DROP TABLE IF EXISTS `temp_def_rectypes`;
	DROP TABLE IF EXISTS `temp_def_rectype_detailtype_constraints`;
	DROP TABLE IF EXISTS `temp_def_detailtypes_for_rectype`;
	DROP TABLE IF EXISTS `temp_def_detailtypes`;
	DROP TABLE IF EXISTS `temp_def_vocabularies`;
	DROP TABLE IF EXISTS `temp_def_detail_enums`;


	// ???? garbage collect temproary arrays

	// Reset flag that system definitions are no longer being modified
	$definitions_being_modified=FALSE;
	$query = "update sys_definitions set sys_definitions_being_modified='FALSE' where sys_id=1";
	$res = mysql_query($query);

	// return to admin interface
	header('Location: ' . BASE_PATH . 'admin/index.php'); // return to the adminstration page
	return;

?>
