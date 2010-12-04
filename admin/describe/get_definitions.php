<?php

// get_definitions.php  - RETURNS HEURIST DEFINITIONS (RECTYPE, DETAILS ETC.)
// AS SQL DATA ROWS READY FOR INSERT STATEMENT PROCESSING
// Ian Johnson 2 March 2010

require_once(dirname(__FILE__).'/../../common/connect/cred.php');


// As a precaution, this currently requires an admin user for the source heurist installation.
// It should require no login at all, or an automatic guest login


$lim=10; //LIMITED FOR TESTING ONLY , REMOVE LIMIT STATEMENTS


if (!is_logged_in()) {
	    header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
	    return;
        }

if (! is_admin()) {
    print "<html><body><p>You do not have sufficient privileges to access this page</p><p><a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1>Log out</a></p></body></html>";
    return;
}

require_once(dirname(__FILE__).'/../../common/connect/db.php');
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
    print "-- rdr_id,rdr_rec_type,rdr_rdt_id,rdr_required,rdr_name,rdr_description,rdr_prompt,rdr_help,rdr_repeatable,rdr_order,rdr_size,rdr_default,rdr_match\n";
    $query = "select * from rec_detail_requirements limit $lim";
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

      case 'detailTypes': // Data from the rec_details table
        print "($row[dty_ID],`$row[dty_Name]`,`$row[dty_Description]`,$row[dty_Type],`$row[dty_Prompt]`,`$row[dty_Help]`,$row[dty_PtrConstraints]),\n";
        break;

      case 'detailRequirements': // Data from the rec_detail_requirements table
        print "($row[rdr_id],`$row[rdr_rec_type]`,`$row[rdr_rdt_id]`,`$row[rdr_required]`,`$row[rdr_name]`,`$row[rdr_description]`,
        `$row[rdr_prompt]`,`$row[rdr_help]`,`$row[rdr_repeatable]`,`$row[rdr_order]`,`$row[rdr_size]`,`$row[rdr_default]`,`$row[rdr_match]`),\n";
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
