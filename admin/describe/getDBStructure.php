<?php

	/*,!--
	* getDBStructure.php - returns database definitions (rectypes, details etc.)
	*                      as SQL statements ready for INSERT processing
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
    $includeUgrps=@$_REQUEST["includeUgrps"];  // returns null if not set

	$approvedDefsOnly=@$_REQUEST["approvedDefsOnly"];  // returns null if not set
	// TO DO: filter for reserved and approved definitions only if this is set


	// Deals with all the database connections stuff

	mysql_connection_db_select(DATABASE);

    $version = 1.0; // Output format version number. This will be read by the crosswalk generator

    // TO DO: Remove HTML tags, this is a fudge to make it readable in a browser fro debuggging

	// File headers to explain what the listing represents

    print "<html><head></head><body><h3>";

	print "-- Heurist Definitions Exchange File Vsn $version - Full export\n";
    print "<br>";  // TO DO: This is a fudge to make it readable in a browser

    print "-- Installation = " . HEURIST_BASE_URL . ";   db = " . HEURIST_DBNAME . " ;    Format Version = $version";print "\n";
    print "<br><br></h3>";  


	// ------------------------------------------------------------------------------------------
	// RECORD TYPES (this will be repeated for each of the tables)

	print "\n-- RECORD TYPES";print "\n";
    print "<p>";
    print "-- rty_ID, rty_Name, rty_OrderInGroup, rty_Description, rty_RecTypeGroupID, 
    rty_TitleMask, rty_CanonicalTitleMask, rty_Plural, rty_Status, rty_VisibleToEndUser, 
    rty_OriginatingDBid, rty_NameInOriginatingdb, rty_IDinOriginatingDB, rty_ReferenceURL\n";
	$query = "select * from defRecTypes";
	$res = mysql_query($query);
	$fmt = 'defRecTypes';

    print "<p>";
	print "\n> Start\n";
    print "<br>";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "<br>";
	print "> End\n";
    print "<p>&nbsp;<p>&nbsp;<p>";


	// ------------------------------------------------------------------------------------------
	// DETAIL TYPES

	print "\n\n\n-- DETAIL TYPES";print "\n";
    print "<p>";
    print "-- dty_ID, dty_Name, dty_Description, dty_Type, dty_Prompt, dty_Help, dty_Status, dty_VisibleToEndUser,
	dty_OriginatingDBid, dty_NameInOriginatingdb, dty_IDinOriginatingDB, dty_PtrTargetRectypeIDs, dty_EnumVocabIDs, dty_EnumTermIDs\n";
	$query = "select * from defDetailTypes";
	$res = mysql_query($query);
	$fmt = 'defDetailTypes';

    print "<p>";
	print "\n> Start\n";
    print "<br>";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "<br>";
	print "> End\n";
    print "<p>&nbsp;<p>&nbsp;<p>";


	// ------------------------------------------------------------------------------------------
	// RECORD STRUCTURE

	print "\n\n\n-- RECORD STRUCTURE";print "\n";
    print "<p>";
    print "-- rst_ID, rst_RecTypeID, rst_DetailTypeID, rst_DisplayName, rst_DisplayDescription, rst_DisplayPrompt, rst_DisplayHelp, 
    rst_DisplayOrder, rst_DisplayWidth, rst_DefaultValue, rst_RecordMatchOrder, rst_RequirementType, rst_Status, rst_MayModify, 
    rst_OriginatingDBid, rst_IDinOriginatingDB, rst_MaxValues, rst_MinValues, 
	rst_EnumConstraintIDs, rst_PtrConstraintIDs, rst_ThumbnailFromDetailTypeID\n";
	$query = "select * from defRecStructure";
	$res = mysql_query($query);
	$fmt = 'defRecStructure';

    print "<p>";
	print "\n> Start\n";
    print "<br>";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "<br>";
	print "> End\n";
    print "<p>&nbsp;<p>&nbsp;<p>";


	// ------------------------------------------------------------------------------------------
	// Detail Type TERMS

	print "\n\n\n-- TERMS";print "\n";
    print "<p>";
    print "-- trm_ID, trm_Label, trm_InverseTermId, trm_VocabID, trm_Description, trm_Status, 
    trm_OriginatingDBid, trm_NameInOriginatingdb, trm_IDinOriginatingDB,  trm_AddedByImport, trm_LocalExtension\n";
	$query = "select * from defTerms";
	$res = mysql_query($query);
	$fmt = 'defTerms';

    print "<p>";
	print "\n> Start\n";
    print "<br>";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "<br>";
	print "> End\n";
    print "<p>&nbsp;<p>&nbsp;<p>";

	// ------------------------------------------------------------------------------------------
	// VOCABULARIES

	print "\n\n\n-- VOCABULARIES";print "\n";
    print "<p>";
    print "-- vcb_ID, vcb_Name, vcb_Description, vcb_RefURL, vcb_Added, vcb_Modified, vcb_Status, 
    vcb_OriginatingDBid, vcb_NameInOriginatingdb, vcb_IDinOriginatingDB,  vcb_OntID\n";
	$query = "select * from defVocabularies";
	$res = mysql_query($query);
	$fmt = 'defVocabularies';

    print "<p>";
	print "\n> Start\n";
    print "<br>";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "<br>";
	print "> End\n";
    print "<p>&nbsp;<p>&nbsp;<p>";


	// ------------------------------------------------------------------------------------------
	// Detail Type ONTOLOGIES

	print "\n\n\n-- ONTOLOGIES";print "\n";
    print "<p>";
    print "-- ont_ID, ont_ShortName, ont_FullName, ont_Description, ont_RefURI, ont_Status, 
    ont_OriginatingDBid, ont_NameInOriginatingdb, ont_IDinOriginatingDB, ont_Added, ont_Modified\n";
	$query = "select * from defOntologies";
	$res = mysql_query($query);
	$fmt = 'defOntologies';

    print "<p>";
	print "\n> Start\n";
    print "<br>";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "<br>";
	print "> End\n";
    print "<p>&nbsp;<p>&nbsp;<p>";

	// ------------------------------------------------------------------------------------------
	// RELATIONSHIP CONSTRAINTS

	print "\n\n\n-- RELATIONSHIP CONSTRAINTS";print "\n";
    print "<p>";
    print "-- rcs_ID, rcs_DetailtypeID, rcs_SourceRectypeID, rcs_TargetRectypeID, rcs_VocabSubset, 
    rcs_VocabID, rcs_Description, rcs_Order, rcs_RelationshipsLimit, rcs_Status, 
    rcs_OriginatingDBid, rcs_IDinOriginatingDB, rcs_TermLimit\n";
	$query = "select * from defRelationshipConstraints";
	$res = mysql_query($query);
	$fmt = 'defRelationshipConstraints';

    print "<p>";
	print "\n> Start\n";
    print "<br>";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "<br>";
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
    print "<br>";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "<br>";
	print "> End\n";
    print "<p>&nbsp;<p>&nbsp;<p>";

	// ------------------------------------------------------------------------------------------
	// defRecTypeGroups

	print "\n\n\n-- RECORD TYPE CLASSES";print "\n";
    print "<p>";
	print "-- rtg_ID, rtg_Name, rtg_Description, rtg_Order\n";
	$query = "select * from defRecTypeGroups";
	$res = mysql_query($query);
	$fmt = 'defRecTypeGroups';

    print "<p>";
    print "\n> Start\n";
    print "<br>";
    while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "<br>";
    print "> End\n";
    print "<p>&nbsp;<p>&nbsp;<p>";

    // ------------------------------------------------------------------------------------------
	// defDetailTypeGroups

	print "\n\n\n-- DETAIL TYPE CLASSES";print "\n";
    print "<p>";
	print "-- dtg_ID, dtg_Name, dtg_Description, dtg_Order\n";
	$query = "select * from defDetailTypeGroups";
    $res = mysql_query($query);
	$fmt = 'defDetailTypeGroups';

    print "<p>";
    print "\n> Start\n";
    print "<br>";
    while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "<br>";
    print "> End\n";
    print "<p>&nbsp;<p>&nbsp;<p>";


    // ------------------------------------------------------------------------------------------
    // defTranslations

    print "\n\n\n-- Definitions translations";print "\n";
    print "<p>";
    print "-- trn_ID , trn_Source, trn_Code, trn_Language, trn_Translation\n";
    $query = "select * from defTranslations where trn_Source in 
	('rty_Name', 'dty_Name', 'ont_ShortName', 'vcb_Name', 'trm_Label', 'rst_DisplayName', 'rtg_Name')";
    // filters to only definition (not data) translations - add others as required
    $res = mysql_query($query);
    $fmt = 'defTranslations';

    print "<p>";
    print "\n> Start\n";
    print "<br>";
    while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "<br>";
    print "> End\n";
    print "<p>&nbsp;<p>&nbsp;<p>";


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
    print "<br>";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "<br>";
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
    print "<br>";
    while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "<br>";
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
    print "<br>";
    while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "<br>";
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
    print "<br>";
    while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "<br>";
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
			print "($row[rty_ID],'$row[rty_Name]',$row[rty_OrderInGroup], `$row[rty_Description]`,$row[rty_RecTypeGroupID],
			`$row[rty_TitleMask]`,`$row[rty_CanonicalTitleMask]`,`$row[rty_Plural]`,`$row[rty_Status]`,
			`$row[rty_ShowInPulldowns]`,`$row[rty_OriginatingDB]`,`$row[rty_ReferenceURL]`),\n";
			break;

			case 'defDetailTypes': // Data from the recDetails table
			print "($row[dty_ID],`$row[dty_Name]`,`$row[dty_Description]`,$row[dty_Type],`$row[dty_Prompt]`,
			`$row[dty_Help]`,`$row[dty_Status]`,`$row[dty_OriginatingDB]`,`$row[dty_PtrTargetRectypeIDs]`,
			`$row[dty_EnumVocabIDs]`,`$row[dty_EnumTermIDs]`),\n";
			break;

			case 'defRecStructure': // Data from the defRecStructure table
			print "($row[rst_ID],`$row[rst_RecTypeID]`,`$row[rst_DetailTypeID]`,`$row[rst_DisplayName]`,
			`$row[rst_Description]`,`$row[rst_DisplayPrompt]`,`$row[rst_DisplayHelp]`,
			`$row[rst_DisplayOrder]`,`$row[rst_DisplayWidth]`,`$row[rst_DefaultValue]`,
			`$row[rst_RecordMatchOrder]`),`$row[rst_RequirementType]`),`$row[rst_Status]`),
			`$row[rst_OriginatingDB]`),`$row[rst_MaxValues]`),`$row[rst_MinValues]`),
			`$row[rst_EnumConstraintIDs]`),`$row[rst_PtrConstraintIDs]`),
			`$row[rst_ThumbnailFromDetailTypeID]`),\n";

			break;

			case 'defTerms': // Data from the rec_details_lookup table
			print "($row[trm_ID],`$row[trm_Label]`,`$row[trm_InverseTermID]`,`$row[trm_VocabID]`,`$row[trm_Description]`,
			`$row[trm_Status]`,`$row[trm_OriginatingDB]`,`$row[trm_AddedByImport]`,`$row[trm_LocalExtension]`),\n";
			break;

			case 'defVocabularies': // Data from Vocabularies table
			print "($row[vcb_ID],`$row[vcb_Name]`,`$row[vcb_Description]`,`$row[vcb_RefURL]`,`$row[vcb_Added]`,`$row[vcb_Modified]`,
			`$row[vcb_Status]`,`$row[vcb_OriginatingDB]`,`$row[vcb_OntID]`),\n";
			break;

			case 'defOntologies': // Data from Ontologies table
			print "($row[ont_ID],`$row[ont_ShortName]`,`$row[ont_FullName]`,`$row[ont_Description]`,`$row[ont_RefURI]`),\n";
			break;

			case 'defRelationshipConstraints': // Data from relationship constraints table
			print "($row[rcs_ID],`$row[rcs_DetailtypeID]`,`$row[rcs_SourceRectypeID]`,`$row[rcs_TargetRectypeID]`,`$row[rcs_VocabSubset]`,`$row[rcs_VocabID]`,
			`$row[rcs_Description]`,`$row[rcs_Order]`,`$row[rcs_RelationshipsLimit]`,`$row[rcs_Status]`,`$row[rcs_OriginatingDB]`,`$row[rcs_TermLimit]`),\n";
			break;

			case 'defFileExtToMimetype': // Data from fiel extension to mimetype table
			print "($row[fxm_Extension],`$row[fxm_MimeType]`,`$row[fxm_OpenNewWindow]`,`$row[fxm_IconFileName]`,`$row[fxm_FiletypeName]`,`$row[fxm_ImagePlaceholder]`),\n";
			break;

			case 'defRecTypeGroups': // Data from record type classes table
			print "($row[rtg_ID],`$row[rtg_Name]`,`$row[rtg_Description]`,`$row[rtg_Order]`),\n";
			break;

			case 'defDetailTypeGroups': // Data from detail type classes table
			print "($row[dtg_ID],`$row[dtg_Name]`,`$row[dtg_Description]`,`$row[dtg_Order]`),\n";
			break;

        case 'defEnumVocabs': // Data from enum vocabs
        print "($row[env_ID],`$row[env_RecTypeID]`,`$row[env_DetailTypeID]`,`$row[env_VocabID]`,`$row[env_VocabSubset]`),\n";
        break;

        case 'defEnumVocabOverride': // Data from enum vocab overrides table
        print "($row[evo_ID],`$row[evo_DetailTypeID]`,`$row[evo_TermIDs]`,`$row[evo_Description]`),\n";
        break;

        case 'defTranslations': // Data from enum vocab overrides table
        print "($row[trn_ID],`$row[trn_Source]`,`$row[trn_Code]`,`$row[trn_Language]`,`$row[trn_Translation]`),\n";
        break;

        case 'sysUGrps': // User details - data from sysUGrps table
        print "($row[ugr_ID],`$row[ugr_Type]`,`$row[ugr_Name]`,`$row[ugr_LongName]`,`$row[ugr_Description]`,
        `$row[ugr_Password]`,`$row[ugr_eMail]`,`$row[ugr_FirstName]`,`$row[ugr_LastName]`,`$row[ugr_Department]`,
        `$row[ugr_Organisation]`,`$row[ugr_City]`,`$row[ugr_State]`,`$row[ugr_Postcode]`,`$row[ugr_Interests]`,
        `$row[ugr_Enabled]`,`$row[ugr_LastLoginTime]`,`$row[ugr_MinHyperlinkWords]`,`$row[ugr_LoginCount]`,
        `$row[ugr_IsModelUser]`,`$row[ugr_IncomingEmailAddresses]`,`$row[ugr_URLs]`,`$row[ugr_FlagJT]`),\n";
        break;
   
        case 'sysUsrGrpLinks': // user's membershiop and role in groups'
        print "($row[ugl_ID],`$row[ugl_UserID]`,`$row[ugl_GroupID]`,`$row[ugl_Role]`),\n";
        break;

        case 'usrHyperlinkFilters': // User's hyperlink filter strings'
        print "($row[hyf_String],`$row[hyf_UGrpId]`),\n";
        break;

        case 'UsrTags': // User's tagging values'
        print "($row[tag_ID],`$row[tag_UGrpID]`,`$row[tag_Text]`,`$row[tag_Description]`,`$row[tag_AddedByImport]`),\n";
        break;

			// Additional case statements here for additional tables if required

		} // end Switch

        print "<br>";  // TO DO: This is a fudge to make it readable in a browser

	} // end function print_row

	// END OF FILE
?>
