<?php

// get_definitions.php  - RETURNS HEURIST DEFINITIONS (RECTYPE, DETAILS ETC.)
// AS SQL DATA ROWS READY FOR INSERT STATEMENT PROCESSING
// Ian Johnson 2 March 2010

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');


// As a precaution, this currently requires an admin user for the source heurist installation.
// It should require no login at all, or an automatic guest login


$lim=10; //LIMITED FOR TESTING ONLY , REMOVE LIMIT STATEMENTS


if (!is_logged_in()) {
	    header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?instance='.HEURIST_INSTANCE);
	    return;
        }

if (! is_admin()) {
    print "<html><body><p>You do not have sufficient privileges to access this page</p><p><a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1&amp;instance=".HEURIST_INSTANCE."'>Log out</a></p></body></html>";
    return;
}

require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/config/.ht_stdefs');


// Deals with all the database connections stuff

    mysql_connection_db_select(DATABASE);

$version = 1; // Output format version number. This will be read by the crosswalk generator

// File headers to explain what the listing represents

    print "-- Heurist Definitions Exchange File - Full export\n";
    print "-- Version: $version";print "\n";


// ------------------------------------------------------------------------------------------
// RECORD TYPES (this will be repeated for each of the tables)

    print "\n-- RECORD TYPES";print "\n";
    print "-- rty_ID,rty_Name,rty_OrderInGroup,rty_Description,rty_RecTypeGroupID,rty_TitleMask,rty_CanonicalTitleMask,rty_Plural\n";
    $query = "select * from defRecTypes limit $lim";
    $res = mysql_query($query);
    $fmt = 'recTypes';

    print "\n> Start\n";
    while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "> End\n";


// ------------------------------------------------------------------------------------------
// RECORD DETAIL TYPES

    print "\n\n\n-- RECORD DETAIL TYPES";print "\n";
    print "-- dty_ID,dty_Name,dty_Description,dty_Type,dty_Prompt,dty_Help,dty_PtrConstraints\n";
    $query = "select * from defDetailTypes limit $lim";
    $res = mysql_query($query);
    $fmt = 'detailTypes';

    print "\n> Start\n";
    while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "> End\n";


// ------------------------------------------------------------------------------------------
// RECORD DETAIL REQUIREMENTS

    print "\n\n\n-- RECORD DETAIL REQUIREMENTS";print "\n";
    print "-- rst_ID,rst_RecTypeID,rst_DetailTypeID,rst_RequirementType,rst_DisplayName,rst_DisplayDescription,rst_DisplayPrompt,rst_DisplayHelp,rst_MaxValues,rst_DisplayOrder,rst_DisplayWidth,rst_DefaultValue,rst_RecordMatchOrder\n";
    $query = "select * from defRecStructure limit $lim";
    $res = mysql_query($query);
    $fmt = 'detailRequirements';

    print "\n> Start\n";
    while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "> End\n";


// ------------------------------------------------------------------------------------------
// Detail Type TERMS

    print "\n\n\n-- TERMS";print "\n";
    print "-- trm_ID,trm_VocabID,trm_Label,trm_InverseTermID\n";
    $query = "select * from defTerms limit $lim";
    $res = mysql_query($query);
    $fmt = 'defTerms';

    print "\n> Start\n";
    while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "> End\n";

// ------------------------------------------------------------------------------------------
// VOCABULARIES

    print "\n\n\n-- VOCABULARIES";print "\n";
    print "-- vcb_ID,vcb_Name,vcb_Description,vcb_RefURL,vcb_Added,vcb_Modified\n";
    $query = "select * from defVocabularies limit $lim";
    $res = mysql_query($query);
    $fmt = 'defVocabularies';

    print "\n> Start\n";
    while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "> End\n";


// ------------------------------------------------------------------------------------------
// RELATIONSHIP CONSTRAINTS

    print "\n\n\n-- RELATIONSHIP CONSTRAINTS";print "\n";
    print "-- rcs_ID,rcs_DetailtypeID,rcs_SourceRectypeID,rcs_TargetRectypeID,rcs_TermIDs,rcs_VocabID,rcs_Description,rcs_TermLimit,rcs_RelationshipsLimit\n";
    $query = "select * from rel_constraints limit $lim";
    $res = mysql_query($query);
    $fmt = 'relConstraints';

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

      case 'recTypes': // Data from the defRecTypes table
        print "($row[rty_ID],'$row[rty_Name]',$row[rty_OrderInGroup], `$row[rty_Description]`,$row[rty_RecTypeGroupID],
        `$row[rty_TitleMask]`,`$row[rty_CanonicalTitleMask]`,`$row[rty_Plural]`),\n";
         break;

      case 'detailTypes': // Data from the recDetails table
        print "($row[dty_ID],`$row[dty_Name]`,`$row[dty_Description]`,$row[dty_Type],`$row[dty_Prompt]`,`$row[dty_Help]`,$row[dty_PtrConstraints]),\n";
        break;

      case 'detailRequirements': // Data from the defRecStructure table
        print "($row[rst_ID],`$row[rst_RecTypeID]`,`$row[rst_DetailTypeID]`,`$row[rst_RequirementType]`,`$row[rst_DisplayName]`,`$row[rst_DisplayDescription]`,
        `$row[rst_DisplayPrompt]`,`$row[rst_DisplayHelp]`,`$row[rst_MaxValues]`,`$row[rst_DisplayOrder]`,`$row[rst_DisplayWidth]`,`$row[rst_DefaultValue]`,`$row[rst_RecordMatchOrder]`),\n";
        break;

      case 'defTerms': // Data from the rec_details_lookup table
        print "($row[trm_ID],`$row[trm_VocabID]`,`$row[trm_Label]`,`$row[trm_InverseTermID]`),\n";
        break;

      case 'defVocabularies': // Data from Vocabularies table
        print "($row[vcb_ID],`$row[vcb_Name]`,`$row[vcb_Description]`,`$row[vcb_RefURL]`,`$row[vcb_Added]`,`$row[vcb_Modified]`),\n";
        break;

      case 'relConstraints': // Data from rel_constraints table
        print "($row[rcs_ID],`$row[rcs_DetailtypeID]`,`$row[rcs_SourceRectypeID]`,`$row[rcs_TargetRectypeID]`,`$row[rcs_TermIDs]`,
        `$row[rcs_VocabID]`,`$row[rcs_Description]`,`$row[rcs_TermLimit]`,`$row[rcs_RelationshipsLimit]`),\n";
        break;

      // Additional case statements here for additional tables if required

      } // end Switch


} // end function print_row

// END OF FILE
?>
