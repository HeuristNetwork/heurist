<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php

	// get_definitions.php  - RETURNS HEURIST DEFINITIONS (RECTYPE, DETAILS ETC.)
	// AS SQL DATA ROWS READY FOR INSERT STATEMENT PROCESSING
	// Ian Johnson 2 March 2010 updated to Vsn 3 13/1/2011

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');


	$lim=20; //LIMITED FOR TESTING ONLY , REMOVE LIMIT STATEMENTS


	// We need to bypass any login, make sure there is nothing in the login which is needed
	// if (!is_logged_in()) {
	//    header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?instance='.HEURIST_INSTANCE);
	//    return;
	//}

	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
	require_once(dirname(__FILE__).'/../../common/config/.ht_stdefs');

	// Deals with all the database connections stuff

	mysql_connection_db_select(DATABASE);

	$version = 1; // Output format version number. This will be read by the crosswalk generator

	// File headers to explain what the listing represents

	print "-- Heurist Definitions Exchange File Vsn $version - Full export\n";
	print "-- Version: $version";print "\n";


	// ------------------------------------------------------------------------------------------
	// RECORD TYPES (this will be repeated for each of the tables)

	print "\n-- RECORD TYPES";print "\n";
	print "-- rty_ID, rty_Name, rty_OrderInGroup, rty_Description, rty_RecTypeGroupID, rty_TitleMask, rty_CanonicalTitleMask, rty_Plural, rty_Status, rty_ShowInPulldowns, rty_OriginatingDB, rty_ReferenceURL\n";
	$query = "select * from defRecTypes limit $lim";
	$res = mysql_query($query);
	$fmt = 'defRecTypes';

	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
	print "> End\n";


	// ------------------------------------------------------------------------------------------
	// DETAIL TYPES

	print "\n\n\n-- DETAIL TYPES";print "\n";
	print "-- dty_ID, dty_Name, dty_Description, dty_Type, dty_Prompt, dty_Help, dty_Status, dty_OriginatingDB, dty_PtrConstraints, dty_NativeVocabID\n";
	$query = "select * from defDetailTypes limit $lim";
	$res = mysql_query($query);
	$fmt = 'defDetailTypes';

	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
	print "> End\n";


	// ------------------------------------------------------------------------------------------
	// RECORD STRUCTURE

	print "\n\n\n-- RECORD STRUCTURE";print "\n";
	print "-- rst_ID, rst_RecTypeID, rst_DetailTypeID, rst_DisplayName, rst_DisplayDescription, rst_DisplayPrompt, rst_DisplayHelp, rst_DisplayOrder, rst_DisplayWidth, rst_DefaultValue, rst_RecordMatchOrder, rst_RequirementType, rst_Status, rst_OriginatingDB, rst_MaxValues, rst_MinValues, rst_VocabConstraints, rst_PtrConstraints, rst_ThumbnailFromDetailTypeID\n";
	$query = "select * from defRecStructure limit $lim";
	$res = mysql_query($query);
	$fmt = 'defRecStructure';

	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
	print "> End\n";


	// ------------------------------------------------------------------------------------------
	// Detail Type TERMS

	print "\n\n\n-- TERMS";print "\n";
	print "-- trm_ID, trm_Label, trm_InverseTermId, trm_VocabID, trm_Description, trm_Status, trm_OriginatingDB, trm_AddedByImport, trm_LocalExtension\n";
	$query = "select * from defTerms limit $lim";
	$res = mysql_query($query);
	$fmt = 'defTerms';

	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
	print "> End\n";

	// ------------------------------------------------------------------------------------------
	// VOCABULARIES

	print "\n\n\n-- VOCABULARIES";print "\n";
	print "-- vcb_ID, vcb_Name, vcb_Description, vcb_RefURL, vcb_Added, vcb_Modified, vcb_Status, vcb_OriginatingDB, vcb_OntID\n";
	$query = "select * from defVocabularies limit $lim";
	$res = mysql_query($query);
	$fmt = 'defVocabularies';

	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
	print "> End\n";


	// ------------------------------------------------------------------------------------------
	// Detail Type ONTOLOGIES

	print "\n\n\n-- ONTOLOGIES";print "\n";
	print "-- ont_ID, ont_ShortName, ont_FullName, ont_Description, ont_RefURI\n";
	$query = "select * from defOntologies limit $lim";
	$res = mysql_query($query);
	$fmt = 'defOntologies';

	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
	print "> End\n";

	// ------------------------------------------------------------------------------------------
	// RELATIONSHIP CONSTRAINTS

	print "\n\n\n-- RELATIONSHIP CONSTRAINTS";print "\n";
	print "-- rcs_ID, rcs_DetailtypeID, rcs_SourceRectypeID, rcs_TargetRectypeID, rcs_VocabConstraint, rcs_VocabID, rcs_Description, rcs_Order, rcs_RelationshipsLimit, rcs_Status, rcs_OriginatingDB, rcs_TermLimit\n";
	$query = "select * from defRelationshipConstraints limit $lim";
	$res = mysql_query($query);
	$fmt = 'defRelationshipConstraints';

	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
	print "> End\n";

	// ------------------------------------------------------------------------------------------
	// defFileExtToMimetype

	print "\n\n\n-- FILE EXTENSIONS TO MIME TYPES";print "\n";
	print "-- fxm_Extension, fxm_MimeType, fxm_OpenNewWindow, fxm_IconFileName, fxm_FiletypeName, fxm_ImagePlaceholder\n";
	$query = "select * from defFileExtToMimetype limit $lim";
	$res = mysql_query($query);
	$fmt = 'defFileExtToMimetype';

	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
	print "> End\n";

	// ------------------------------------------------------------------------------------------
	// defRecTypeGroups

	print "\n\n\n-- RECORD TYPE CLASSES";print "\n";
	print "-- rtg_ID, rtg_Name, rtg_Order\n";
	$query = "select * from defRecTypeGroups limit $lim";
	$res = mysql_query($query);
	$fmt = 'defRecTypeGroups';

	print "\n> Start\n";
	while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
	print "> End\n";


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
			print "($row[dty_ID],`$row[dty_Name]`,`$row[dty_Description]`,$row[dty_Type],`$row[dty_Prompt]`,`$row[dty_Help]`,
			,`$row[dty_Status]`,`$row[dty_OriginatingDB]`,`$row[dty_PtrConstraints]`,`$row[dty_NativeVocabID]`),\n";
			break;

			case 'defRecStructure': // Data from the defRecStructure table
			print "($row[rst_ID],`$row[rst_RecTypeID]`,`$row[rst_DetailTypeID]`,`$row[rst_DisplayName]`,`$row[rst_Description]`,
			`$row[rst_DisplayPrompt]`,`$row[rst_DisplayHelp]`,`$row[rst_DisplayOrder]`,`$row[rst_DisplayWidth]`,
			`$row[rst_DefaultValue]`,`$row[rst_RecordMatchOrder]`),`$row[rst_RequirementType]`),`$row[rst_Status]`),`$row[rst_OriginatingDB]`),
			`$row[rst_MaxValues]`),`$row[rst_MinValues]`),`$row[rst_VocabConstraints]`),`$row[rst_PtrConstraints]`),`$row[rst_ThumbnailFromDetailTypeID]`),\n";

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
			print "($row[rcs_ID],`$row[rcs_DetailtypeID]`,`$row[rcs_SourceRectypeID]`,`$row[rcs_TargetRectypeID]`,`$row[rcs_VocabConstraint]`,`$row[rcs_VocabID]`,
			`$row[rcs_Description]`,`$row[rcs_Order]`,`$row[rcs_RelationshipsLimit]`,`$row[rcs_Status]`,`$row[rcs_OriginatingDB]`,`$row[rcs_TermLimit]`),\n";
			break;

			case 'defFileExtToMimetype': // Data from fiel extension to mimetype table
			print "($row[fxm_Extension],`$row[fxm_MimeType]`,`$row[fxm_OpenNewWindow]`,`$row[fxm_IconFileName]`,`$row[fxm_FiletypeName]`,`$row[fxm_ImagePlaceholder]`),\n";
			break;

			case 'defRecTypeGroups': // Data from record type classes table
			print "($row[rtg_ID],`$row[rtg_Name]`,`$row[rtg_Order]`),\n";
			break;

			// Additional case statements here for additional tables if required

		} // end Switch


	} // end function print_row

	// END OF FILE
?>
