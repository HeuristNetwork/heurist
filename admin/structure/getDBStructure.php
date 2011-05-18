<?php

	/*,!--
	* getDBStructure.php - returns database definitions (rectypes, details etc.)
	*						as SQL statements ready for INSERT processing
	* Ian Johnson 2 March 2010 updated to Vsn 3 13/1/2011
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
	* @param includeUgrps=1 will output user and group information in addition to definitions	 
	* @param approvedDefsOnly=1 will only output Reserved and Approved definitions
 * @todo
	-->*/

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');


	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

	// Normally jsut outputs definitions, this will include users/groups
	$includeUgrps=@$_REQUEST["includeUgrps"];	// returns null if not set

	$approvedDefsOnly=@$_REQUEST["approvedDefsOnly"];	// returns null if not set
	// TO DO: filter for reserved and approved definitions only if this is set


	// Deals with all the database connections stuff

	mysql_connection_db_select(DATABASE);
	if(mysql_error()) {
		die("Could not get database structure from given database source.");
	}

	$version = 1.0; // Output format version number. This will be read by the crosswalk generator

	// TO DO: Remove HTML tags, this is a fudge to make it readable in a browser fro debuggging

	// File headers to explain what the listing represents

	print "<html><head></head><body><h3>";

	print "-- Heurist Definitions Exchange File Vsn $version - Full export\n";
	print "<br>";	// TO DO: This is a fudge to make it readable in a browser

	print "-- Installation = " . HEURIST_BASE_URL . ";	 db = " . HEURIST_DBNAME . " ;		 Format Version = $version";print "\n";
	print "<br><br></h3>";

	// ------------------------------------------------------------------------------------------
	// RECORD TYPES (this will be repeated for each of the tables)

	print "\n-- RECORD TYPES";print "\n";
	print "<p>";
	print "-- rty_ID, rty_Name, rty_OrderInGroup, rty_Description, rty_TitleMask,
			rty_CanonicalTitleMask, rty_Plural, rty_Status, rty_OriginatingDBID, rty_NameInOriginatingDB,
			rty_IDInOriginatingDB, rty_ShowInLists, rty_RecTypeGroupIDs, rty_FlagAsFieldset, rty_ReferenceURL,
			rty_AlternativeRecEditor, rty_Type\n";
	$query = "select * from defRecTypes";
	$res = mysql_query($query);
	$fmt = 'defRecTypes';

	print "<p>";
	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
	print "> End\n";
	print "<p>&nbsp;<p>&nbsp;<p>";


	// ------------------------------------------------------------------------------------------
	// DETAIL TYPES

	print "\n\n\n-- DETAIL TYPES";print "\n";
	print "<p>";
	print "-- dty_ID, dty_Name, dty_Documentation, dty_Type, dty_HelpText, dty_ExtendedDescription, dty_Status,
			dty_OriginatingDBID, dty_NameInOriginatingDB, dty_IDInOriginatingDB, dty_DetailTypeGroupID,
			dty_OrderInGroup, dty_PtrTargetRectypeIDs, dty_JsonTermIDTree, dty_TermIDTreeNonSelectableIDs,
			dty_FieldSetRecTypeID, dty_ShowInLists,\n";
	$query = "select * from defDetailTypes";
	$res = mysql_query($query);
	$fmt = 'defDetailTypes';

	print "<p>";
	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
	print "> End\n";
	print "<p>&nbsp;<p>&nbsp;<p>";


	// ------------------------------------------------------------------------------------------
	// RECORD STRUCTURE

	print "\n\n\n-- RECORD STRUCTURE";print "\n";
	print "<p>";
	print "-- rst_ID, rst_RecTypeID, rst_DetailTypeID, rst_DisplayName, rst_DisplayHelpText, rst_DisplayExtendedDescription,
			rst_DisplayOrder, rst_DisplayWidth, rst_DefaultValue, rst_RecordMatchOrder, rst_CalcFunctionID,
			rst_RequirementType, rst_Status, rst_OriginatingDBID, rst_IDInOriginatingDB, rst_MaxValues, rst_MinValues,
			rst_DisplayDetailTypeGroupID, rst_FilteredJsonTermIDTree, rst_TermIDTreeNonSelectableIDs, rst_PtrFilteredIDs, rst_OrderForThumbnailGeneration\n";
	$query = "select * from defRecStructure";
	$res = mysql_query($query);
	$fmt = 'defRecStructure';

	print "<p>";
	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
	print "> End\n";
	print "<p>&nbsp;<p>&nbsp;<p>";


	// ------------------------------------------------------------------------------------------
	// Detail Type TERMS

	print "\n\n\n-- TERMS";print "\n";
	print "<p>";
	print "-- trm_ID, trm_Label, trm_InverseTermId, trm_Description, trm_Status, trm_OriginatingDBID,
			trm_AddedByImport, trm_IsLocalExtension, trm_NameInOriginatingDB, trm_IDInOriginatingDB,
			trm_ParentTermID, trm_Domain, trm_ChildCount, trm_Depth, trm_OntID\n";
	$query = "select * from defTerms";
	$res = mysql_query($query);
	$fmt = 'defTerms';

	print "<p>";
	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
	print "> End\n";
	print "<p>&nbsp;<p>&nbsp;<p>";

	// ------------------------------------------------------------------------------------------
	// Detail Type ONTOLOGIES

	print "\n\n\n-- ONTOLOGIES";print "\n";
	print "<p>";
	print "-- ont_ID, ont_ShortName, ont_FullName, ont_Description, ont_RefURI, ont_Status, 
	ont_OriginatingDBID, ont_NameInOriginatingDB, ont_IDInOriginatingDB, ont_Added, ont_Modified\n";
	$query = "select * from defOntologies";
	$res = mysql_query($query);
	$fmt = 'defOntologies';

	print "<p>";
	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
	print "> End\n";
	print "<p>&nbsp;<p>&nbsp;<p>";

	// ------------------------------------------------------------------------------------------
	// RELATIONSHIP CONSTRAINTS

	print "\n\n\n-- RELATIONSHIP CONSTRAINTS";print "\n";
	print "<p>";
	print "-- rcs_ID, rcs_TermID, rcs_SourceRectypeID, rcs_TargetRectypeID, rcs_Description, rcs_Status,
			rcs_OriginatingDBID, rcs_TermLimit, rcs_IDInOriginatingDB\n";
	$query = "select * from defRelationshipConstraints";
	$res = mysql_query($query);
	$fmt = 'defRelationshipConstraints';

	print "<p>";
	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
	print "> End\n";
	print "<p>&nbsp;<p>&nbsp;<p>";

	// ------------------------------------------------------------------------------------------
	// defFileExtToMimetype

	print "\n\n\n-- FILE EXTENSIONS TO MIME TYPES";print "\n";
	print "<p>";
	print "-- fxm_Extension, fxm_MimeType, fxm_OpenNewWindow, fxm_IconFileName, fxm_FiletypeName, fxm_ImagePlaceholder\n";
	$query = "select * from defFileExtToMimetype";
	$res = mysql_query($query);
	$fmt = 'defFileExtToMimetype';

	print "<p>";
	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
	print "> End\n";
	print "<p>&nbsp;<p>&nbsp;<p>";

	// ------------------------------------------------------------------------------------------
	// defRecTypeGroups
	
	print "\n\n\n-- RECORD TYPE GROUPS";print "\n";
	print "<p>";
	print "-- rtg_ID, rtg_Name, rtg_Domain, rtg_Order, rtg_Description\n";
	$query = "select * from defRecTypeGroups";
	$res = mysql_query($query);
	$fmt = 'defRecTypeGroups';

	print "<p>";
	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
	print "> End\n";
	print "<p>&nbsp;<p>&nbsp;<p>";

	// ------------------------------------------------------------------------------------------
	// defDetailTypeGroups

	print "\n\n\n-- DETAIL TYPE GROUPS";print "\n";
	print "<p>";
	print "-- dtg_ID, dtg_Name, dtg_Description, dtg_Order\n";
	$query = "select * from defDetailTypeGroups";
	$res = mysql_query($query);
	$fmt = 'defDetailTypeGroups';

	print "<p>";
	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
	print "> End\n";
	print "<p>&nbsp;<p>&nbsp;<p>";


	// ------------------------------------------------------------------------------------------
	// defTranslations

	print "\n\n\n-- Definitions translations";print "\n";
	print "<p>";
	print "-- trn_ID , trn_Source, trn_Code, trn_LanguageID, trn_Translation\n";
	$query = "select * from defTranslations where trn_Source in 
	('rty_Name', 'dty_Name', 'ont_ShortName', 'vcb_Name', 'trm_Label', 'rst_DisplayName', 'rtg_Name')";
	// filters to only definition (not data) translations - add others as required
	$res = mysql_query($query);
	$fmt = 'defTranslations';

	print "<p>";
	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
	print "> End\n";
	print "<p>&nbsp;<p>&nbsp;<p>";

	// ------------------------------------------------------------------------------------------
	// defCalcFunctions

	print "\n\n\n-- DEF CALC FUNCTIONS";print "\n";
	print "<p>";
	print "-- cfn_ID, cfn_Domain, cfn_FunctionSpecification\n";
	$query = "select * from defCalcFunctions";
	$res = mysql_query($query);
	$fmt = 'defCalcFunctions';

	print "<p>";
	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
	print "> End\n";
	print "<p>&nbsp;<p>&nbsp;<p>";

	// ------------------------------------------------------------------------------------------
	// defCrosswalk

	print "\n\n\n-- DEF CROSSWALK";print "\n";
	print "<p>";
	print "-- crw_ID, crw_SourcedbID, crw_SourceCode, crw_DefType, crw_LocalCode, crw_Modified\n";
	$query = "select * from defCrosswalk";
	$res = mysql_query($query);
	$fmt = 'defCrosswalk';

	print "<p>";
	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
	print "> End\n";
	print "<p>&nbsp;<p>&nbsp;<p>";

	// ------------------------------------------------------------------------------------------
	// defLanguages

	print "\n\n\n-- DEF LANGUAGE";print "\n";
	print "<p>";
	print "-- lng_ID, lng_Name, lng_Notes, lng_ISO639, lng_NISOZ3953\n";
	$query = "select * from defLanguages";
	$res = mysql_query($query);
	$fmt = 'defLanguages';

	print "<p>";
	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
	print "> End\n";
	print "<p>&nbsp;<p>&nbsp;<p>";

	// ------------------------------------------------------------------------------------------
	// defURLPrefixes

	print "\n\n\n-- DEF URL PREFIXES";print "\n";
	print "<p>";
	print "-- urp_ID, urp_Prefix\n";
	$query = "select * from defURLPrefixes";
	$res = mysql_query($query);
	$fmt = 'defURLPrefixes';

	print "<p>";
	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
	print "> End\n";
	print "<p>&nbsp;<p>&nbsp;<p>";

	// ------------------------------------------------------------------------------------------
	// Output the following only if parameter switch set and user is an admin

	if (!$includeUgrps) {return;}

	if (! is_admin()) {
		print "<html><body><p>You do not have sufficient privileges to list users</p><p><a href=".HEURIST_URL_BASE.">Return to Heurist</a></p></body></html>";
		return;	 
	}
	// ------------------------------------------------------------------------------------------
	// sysUGrps

	print "\n\n\n-- Users and Groups";print "\n";
	print "<p>";
	print "-- ugr_ID, ugr_Type, ugr_Name, ugr_LongName, ugr_Description, ugr_Password, ugr_eMail, 
		ugr_FirstName, ugr_LastName, ugr_Department, ugr_Organisation, ugr_City, ugr_State, ugr_Postcode, 
		ugr_Interests, ugr_Enabled, ugr_LastLoginTime, ugr_MinHyperlinkWords, ugr_LoginCount, ugr_IsModelUser, 
		ugr_IncomingEmailAddresses, ugr_URLs, ugr_FlagJT\n";
	$query = "select * from sysUGrps";
	$res = mysql_query($query);
	$fmt = 'sysUGrps';

	print "<p>";
	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
	print "> End\n";
	print "<p>&nbsp;<p>&nbsp;<p>";

	// ------------------------------------------------------------------------------------------
	// sysUsrGrpLinks

	print "\n\n\n-- Users to Group membership and roles";print "\n";
	print "<p>";
	print "-- ugl_ID,ugl_UserID,ugl_GroupID,ugl_Role\n";
	$query = "select * from sysUsrGrpLinks";
	$res = mysql_query($query);
	$fmt = 'sysUsrGrpLinks';

	print "<p>";
	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
	print "> End\n";
	print "<p>&nbsp;<p>&nbsp;<p>";

	// ------------------------------------------------------------------------------------------
	// usrHyperlinkFilters

	print "\n\n\n-- User's hyperlink filters";print "\n";
	print "<p>";
	print "-- hyf_String,hyf_UGrpId\n";
	$query = "select * from usrHyperlinkFilters";
	$res = mysql_query($query);
	$fmt = 'usrHyperlinkFilters';

	print "<p>";
	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
	print "> End\n";
	print "<p>&nbsp;<p>&nbsp;<p>";

	// ------------------------------------------------------------------------------------------
	// usrTags

	print "\n\n\n-- User's tags";print "\n";
	print "<p>";
	print "-- tag_ID,tag_UGrpID,tag_Text,tag_Description,tag_AddedByImport\n";
	$query = "select * from UsrTags";
	$res = mysql_query($query);
	$fmt = 'UsrTags';

	print "<p>";
	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
	print "> End\n";
	print "<p>&nbsp;<p>&nbsp;<p>";




	// --------------------------------------------------------------------------------------

	function print_row($row,$fmt) {

		// Prints a formatted representation of the data retreived for one row in the query
		// Make sure that the query you passed in generates the fields you want to print
		// Specify fields with $row[fieldname] or $row['fieldname'] (in most cases the quotes
		// are unecessary although perhaps syntactically proper)

		switch ($fmt) {  // select the output formatting according to the table

			case 'defRecTypes': // Data from the defRecTypes table
			if($row['rty_ID'] != 0) {
			$rty_Name = mysql_real_escape_string($row['rty_Name']); // escapes RTF-8 characters
			$rty_Description = mysql_real_escape_string($row['rty_Description']);
			$rty_TitleMask = mysql_real_escape_string($row['rty_TitleMask']);
			$rty_CanonicalTitleMask = mysql_real_escape_string($row['rty_CanonicalTitleMask']);
			$rty_Plural = mysql_real_escape_string($row['rty_Plural']);
			$rty_NameInOriginatingDB = mysql_real_escape_string($row['rty_NameInOriginatingDB']);
			$rty_RecTypeGroupIDs = mysql_real_escape_string($row['rty_RecTypeGroupIDs']);
			$rty_ReferenceURL = mysql_real_escape_string($row['rty_ReferenceURL']);
			$rty_AlternativeRecEditor = mysql_real_escape_string($row['rty_AlternativeRecEditor']);
			print "('$row[rty_ID]','$rty_Name','$row[rty_OrderInGroup]','$rty_Description','$rty_TitleMask',
			'$rty_CanonicalTitleMask','$rty_Plural','$row[rty_Status]',
			'$row[rty_OriginatingDBID]','$rty_NameInOriginatingDB','$row[rty_IDInOriginatingDB]',
			'$row[rty_ShowInLists]','$rty_RecTypeGroupIDs','$row[rty_FlagAsFieldset]','$rty_ReferenceURL',
			'$rty_AlternativeRecEditor','$row[rty_Type]'),";
			}
			break;

			case 'defDetailTypes': // Data from the recDetails table
			$dty_Name = mysql_real_escape_string($row['dty_Name']);
			$dty_Documentation = mysql_real_escape_string($row['dty_Documentation']);
			$dty_HelpText = mysql_real_escape_string($row['dty_HelpText']);
			$dty_ExtendedDescription = mysql_real_escape_string($row['dty_ExtendedDescription']);
			$dty_NameInOriginatingDB = mysql_real_escape_string($row['dty_NameInOriginatingDB']);
			$dty_JsonTermIDTree = mysql_real_escape_string($row['dty_JsonTermIDTree']);
			$dty_TermIDTreeNonSelectableIDs = mysql_real_escape_string($row['dty_TermIDTreeNonSelectableIDs']);			
			$dty_PtrTargetRectypeIDs = mysql_real_escape_string($row['dty_PtrTargetRectypeIDs']);
			print "('$row[dty_ID]','$dty_Name','$dty_Documentation','$row[dty_Type]','$dty_HelpText',
			'$dty_ExtendedDescription','$row[dty_Status]','$row[dty_OriginatingDBID]',
			'$dty_NameInOriginatingDB','$row[dty_IDInOriginatingDB]','$row[dty_DetailTypeGroupID]',
			'$row[dty_OrderInGroup]','$dty_JsonTermIDTree','$dty_TermIDTreeNonSelectableIDs',
			'$dty_PtrTargetRectypeIDs','$row[dty_FieldSetRecTypeID]','$row[dty_ShowInLists]'),";
			break;

			case 'defRecStructure': // Data from the defRecStructure table
			$rst_DisplayName = mysql_real_escape_string($row['rst_DisplayName']);
			$rst_DisplayHelpText = mysql_real_escape_string($row['rst_DisplayHelpText']);
			$rst_DisplayExtendedDescription = mysql_real_escape_string($row['rst_DisplayExtendedDescription']);
			$rst_DefaultValue = mysql_real_escape_string($row['rst_DefaultValue']);
			$rst_FilteredJsonTermIDTree = mysql_real_escape_string($row['rst_FilteredJsonTermIDTree']);
			$rst_TermIDTreeNonSelectableIDs = mysql_real_escape_string($row['rst_TermIDTreeNonSelectableIDs']);
			$rst_PtrFilteredIDs = mysql_real_escape_string($row['rst_PtrFilteredIDs']);
			print "('$row[rst_ID]','$row[rst_RecTypeID]','$row[rst_DetailTypeID]','$rst_DisplayName',
			'$rst_DisplayHelpText','$rst_DisplayExtendedDescription','$row[rst_DisplayOrder]',
			'$row[rst_DisplayWidth]','$rst_DefaultValue',
			'$row[rst_RecordMatchOrder]','$row[rst_CalcFunctionID]','$row[rst_RequirementType]',
			'$row[rst_Status]','$row[rst_MayModify]','$row[rst_OriginatingDBID]','$row[rst_IDInOriginatingDB]',
			'$row[rst_MaxValues]','$row[rst_MinValues]','$row[rst_DisplayDetailTypeGroupID]','$rst_FilteredJsonTermIDTree',
			'$rst_PtrFilteredIDs','$row[rst_OrderForThumbnailGeneration]','$rst_TermIDTreeNonSelectableIDs'),";
			break;

			case 'defTerms': // Data from the rec_details_lookup table
			$trm_Label = mysql_real_escape_string($row['trm_Label']);
			$trm_Description = mysql_real_escape_string($row['trm_Description']);
			$trm_NameInOriginatingDB = mysql_real_escape_string($row['trm_NameInOriginatingDB']);
			print "('$row[trm_ID]','$trm_Label','$row[trm_InverseTermId]','$trm_Description',
			'$row[trm_Status]','$row[trm_OriginatingDBID]','$trm_NameInOriginatingDB','$row[trm_IDInOriginatingDB]',
			'$row[trm_AddedByImport]','$row[trm_IsLocalExtension]','$row[trm_Domain]','$row[trm_OntID]',
			'$row[trm_ChildCount]','$row[trm_ParentTermID]','$row[trm_Depth]'),";
			break;

			case 'defOntologies': // Data from Ontologies table
			$ont_ShortName = mysql_real_escape_string($row['ont_ShortName']);
			$ont_FullName = mysql_real_escape_string($row['ont_FullName']);
			$ont_Description = mysql_real_escape_string($row['ont_Description']);
			$ont_RefURI = mysql_real_escape_string($row['ont_RefURI']);
			$ont_NameInOriginatingDB = mysql_real_escape_string($row['ont_NameInOriginatingDB']);
			print "('$row[ont_ID]','$ont_ShortName','$ont_FullName','$ont_Description','$ont_RefURI',
			'$row[ont_Status]','$row[ont_OriginatingDBID]','$ont_NameInOriginatingDB',
			'$row[ont_IDInOriginatingDB]','$row[ont_Order]'),";
			break;

			case 'defRelationshipConstraints': // Data from relationship constraints table
			$rcs_Description = mysql_real_escape_string($row['rcs_Description']);
			print "('$row[rcs_ID]','$row[rcs_SourceRectypeID]','$row[rcs_TargetRectypeID]',
			'$rcs_Description','$row[rcs_Status]','$row[rcs_OriginatingDB]','$row[rcs_IDInOriginatingDB]',
			'$row[rcs_TermID]','$row[rcs_TermLimit]'),";
			break;

			case 'defFileExtToMimetype': // Data from field extension to mimetype table
			$fxm_Extension = mysql_real_escape_string($row['fxm_Extension']);
			$fxm_MimeType = mysql_real_escape_string($row['fxm_MimeType']);
			$fxm_IconFileName = mysql_real_escape_string($row['fxm_IconFileName']);
			$fxm_FiletypeName = mysql_real_escape_string($row['fxm_FiletypeName']);
			$fxm_ImagePlaceholder = mysql_real_escape_string($row['fxm_ImagePlaceholder']);
			print "('$fxm_Extension','$fxm_MimeType','$row[fxm_OpenNewWindow]',
			'$fxm_IconFileName','$fxm_FiletypeName','$fxm_ImagePlaceholder'),";
			break;

			case 'defRecTypeGroups': // Data from record type classes table
			$rtg_Name = mysql_real_escape_string($row['rtg_Name']);
			$rtg_Description = mysql_real_escape_string($row['rtg_Description']);
			print "('$row[rtg_ID]','$rtg_Name','$row[rtg_Domain]','$row[rtg_Order]','$rtg_Description'),";
			break;

			case 'defDetailTypeGroups': // Data from detail type classes table
			$dtg_Name = mysql_real_escape_string($row['dtg_Name']);
			$dtg_Description = mysql_real_escape_string($row['dtg_Description']);
			print "('$row[dtg_ID]','$row[dtg_Name]','$row[dtg_Order]','$row[dtg_Description]'),";
			break;
			
			case 'defTranslations':
			$trn_Translation = mysql_real_escape_string($row['trn_Translation']);
			print "('$row[trn_ID]','$row[trn_Source]','$row[trn_Code]','$row[trn_LanguageCode3]','$trn_Translation'),";
			break;
			
			case 'defCalcFunctions':
			$cfn_FunctionSpecification = mysql_real_escape_string($row['cfn_FunctionSpecification']);
			print "('$row[cfn_ID]','$row[cfn_Domain]','$cfn_FunctionSpecification'),";
			break;
			
			case 'defCrosswalk':
			print "('$row[crw_ID]','$row[crw_SourcedbID]','$row[crw_SourceCode]',
			'$row[crw_DefType]','$row[crw_LocalCode]','$row[crw_Modified]'),";
			break;
			
			case 'defLanguages':
			$lng_Name = mysql_real_escape_string($row['lng_Name']);
			$lng_Notes = mysql_real_escape_string($row['lng_Notes']);
			print "('$row[NISOZ3953]','$row[lng_ISO639]','$lng_Name','$lng_Notes'),";
			break;
			
			case 'defURLPrefixes':
			$urp_Prefix = mysql_real_escape_string($row['urp_Prefix']);
			print "('$row[urp_ID]','$urp_Prefix'),";
			break;

			case 'sysUGrps': // User details - data from sysUGrps table
			$ugr_Name = mysql_real_escape_string($row['ugr_Name']);
			$ugr_LongName = mysql_real_escape_string($row['ugr_LongName']);
			$ugr_Description = mysql_real_escape_string($row['ugr_Description']);
			$ugr_Password = mysql_real_escape_string($row['ugr_Password']);
			$ugr_eMail = mysql_real_escape_string($row['ugr_eMail']);
			$ugr_FirstName = mysql_real_escape_string($row['ugr_FirstName']);
			$ugr_LastName = mysql_real_escape_string($row['ugr_LastName']);
			$ugr_Departement = mysql_real_escape_string($row['ugr_Departement']);
			$ugr_Organisation = mysql_real_escape_string($row['ugr_Organisation']);
			$ugr_City = mysql_real_escape_string($row['ugr_City']);
			$ugr_State = mysql_real_escape_string($row['ugr_State']);
			$ugr_Postcode = mysql_real_escape_string($row['ugr_Postcode']);
			$ugr_Interests = mysql_real_escape_string($row['ugr_Interests']);
			$ugr_IncomingEmailAddresses = mysql_real_escape_string($row['ugr_IncomingEmailAddresses']);
			$ugr_URLs = mysql_real_escape_string($row['ugr_URLs']);
			print "('$row[ugr_ID]','$row[ugr_Type]','$ugr_Name','$ugr_LongName','$ugr_Description',
			'$ugr_Password','$ugr_eMail','$ugr_FirstName','$ugr_LastName','$ugr_Department',
			'$ugr_Organisation','$ugr_City','$ugr_State','$ugr_Postcode','$ugr_Interests',
			'$row[ugr_Enabled]','$row[ugr_LastLoginTime]','$row[ugr_MinHyperlinkWords]','$row[ugr_LoginCount]',
			'$row[ugr_IsModelUser]','$ugr_IncomingEmailAddresses','$ugr_URLs','$row[ugr_FlagJT]'),";
			break;
	
			case 'sysUsrGrpLinks': // user's membership and role in groups'
			print "('$row[ugl_ID]','$row[ugl_UserID]','$row[ugl_GroupID]','$row[ugl_Role]'),";
			break;
	
			case 'usrHyperlinkFilters': // User's hyperlink filter strings'
			$hyf_String = mysql_real_escape_string($row['hyf_String']);
			print "('$hyf_String','$row[hyf_UGrpID]'),";
			break;
	
			case 'UsrTags': // User's tagging values'
			$tag_Text = mysql_real_escape_string($row['tag_Text']);
			$tag_Description = mysql_real_escape_string($row['tag_Description']);
			print "('$row[tag_ID]','$row[tag_UGrpID]','$tag_Text','$tag_Description','$row[tag_AddedByImport]'),";
			break;

			// Additional case statements here for additional tables if required

		} // end Switch

	} // end function print_row
?>