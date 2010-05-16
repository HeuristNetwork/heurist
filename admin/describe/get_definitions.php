<?php

// get_definitions.php  - RETURNS HEURIST DEFINITIONS (RECTYPE, DETAILS ETC.) 
// AS SQL DATA ROWS READY FOR INSERT STATEMENT PROCESSING
// Ian Johnson 2 March 2010

require_once('../php/modules/cred.php');


// As a precaution, this currently requires an admin user for the source heurist installation.
// It should require no login at all, or an automatic guest login


$lim=10; //LIMITED FOR TESTING ONLY , REMOVE LIMIT STATEMENTS


if (!is_logged_in()) {
	    header('Location: ' . BASE_PATH . 'login.php');
	    return;
        }
        
if (! is_admin()) {
    print "<html><body><p>You do not have sufficient privileges to access this page</p><p><a href=../php/login.php?logout=1>Log out</a></p></body></html>";
    return;   
}

require_once('../php/modules/db.php');
require_once('../legacy/.ht_stdefs');


// Deals with all the database connections stuff

    mysql_connection_db_select(DATABASE);

$version = 1; // Output format version number. This will be read by the crosswalk generator
    
// File headers to explain what the listing represents

    print "-- Heurist Definitions Exchange File - Full export\n";
    print "-- Version: $version";print "\n";
 
  
// ------------------------------------------------------------------------------------------
// RECORD TYPES (this will be repeated for each of the tables)

    print "\n-- RECORD TYPES";print "\n";
    print "-- rt_id,rt_name,rt_order,rt_notes,rt_primary,rt_list_order,rt_category,rt_title_mask,rt_canonical_title_mask,rt_plural\n";
    $query = "select * from rec_types limit $lim";
    $res = mysql_query($query);
    $fmt = 'rectypes';

    print "\n> Start\n";
    while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "> End\n";
    
 
// ------------------------------------------------------------------------------------------
// RECORD DETAIL TYPES

    print "\n\n\n-- RECORD DETAIL TYPES";print "\n";
    print "-- rdt_id,rdt_name,rdt_description,rdt_type,rdt_prompt,rdt_help,rdt_constrain_rec_type\n";
    $query = "select * from rec_detail_types limit $lim";
    $res = mysql_query($query);
    $fmt = 'recdetailtypes';
    
    print "\n> Start\n";
    while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "> End\n";
    
 
// ------------------------------------------------------------------------------------------
// RECORD DETAIL REQUIREMENTS

    print "\n\n\n-- RECORD DETAIL REQUIREMENTS";print "\n";
    print "-- rdr_id,rdr_rec_type,rdr_rdt_id,rdr_required,rdr_name,rdr_description,rdr_prompt,rdr_help,rdr_repeatable,rdr_order,rdr_size,rdr_default,rdr_match\n";
    $query = "select * from rec_detail_requirements limit $lim";
    $res = mysql_query($query);
    $fmt = 'recdetailrequirements';
    
    print "\n> Start\n";
    while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "> End\n";
    

// ------------------------------------------------------------------------------------------
// RECORD DETAIL LOOKUPS

    print "\n\n\n-- RECORD DETAIL LOOKUPS";print "\n";
    print "-- rdl_id,rdl_rdt_id,rdl_value,rdl_related_rdl_id\n";
    $query = "select * from rec_detail_lookups limit $lim";
    $res = mysql_query($query);
    $fmt = 'recdetaillookups';
    
    print "\n> Start\n";
    while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "> End\n";
                                                                                                                                       
// ------------------------------------------------------------------------------------------
// ONTOLOGIES

    print "\n\n\n-- ONTOLOGIES";print "\n";
    print "-- ont_id,ont_name,ont_description,ont_refurl,ont_added,ont_modified\n";
    $query = "select * from ontologies limit $lim";
    $res = mysql_query($query);
    $fmt = 'ontologies';
    
    print "\n> Start\n";
    while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
    print "> End\n";
    
 
// ------------------------------------------------------------------------------------------
// RELATIONSHIP CONSTRAINTS

    print "\n\n\n-- RELATIONSHIP CONSTRAINTS";print "\n";
    print "-- rcon_id,rcon_rdt_id,rcon_source_rtd_id,rcon_target_rdt_id,rcon_rdl_ids,rcon_ont_id,rcon_description\n";
    $query = "select * from rel_constraints limit $lim";
    $res = mysql_query($query);
    $fmt = 'rel_constraints';
    
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

      case 'rectypes': // Data from the rec_types table
        print "($row[rt_id],'$row[rt_name]',$row[rt_order], `$row[rt_notes]`,$row[rt_primary],$row[rt_list_order], 
        `$row[rt_category]`,`$row[rt_title_mask]`,`$row[rt_canonical_title_mask]`,`$row[rt_plural]`),\n";
         break;
         
      case 'recdetailtypes': // Data from the rec_details table
        print "($row[rdt_id],`$row[rdt_name]`,`$row[rdt_description]`,$row[rdt_type],`$row[rdt_prompt]`,`$row[rdt_help]`,$row[rdt_constrain_rec_type]),\n";
        break;

      case 'recdetailrequirements': // Data from the rec_detail_requirements table
        print "($row[rdr_id],`$row[rdr_rec_type]`,`$row[rdr_rdt_id]`,`$row[rdr_required]`,`$row[rdr_name]`,`$row[rdr_description]`,
        `$row[rdr_prompt]`,`$row[rdr_help]`,`$row[rdr_repeatable]`,`$row[rdr_order]`,`$row[rdr_size]`,`$row[rdr_default]`,`$row[rdr_match]`),\n";
        break;
      
      case 'recdetaillookups': // Data from the rec_details_lookup table
        print "($row[rdl_id],`$row[rdl_rdt_id]`,`$row[rdl_value]`,`$row[rdl_related_rdl_id]`),\n";
        break;
      
      case 'ontologies': // Data from Ontologies table
        print "($row[ont_id],`$row[ont_name]`,`$row[ont_description]`,`$row[ont_refurl]`,`$row[ont_added]`,`$row[ont_modified]`),\n";
        break;
      
      case 'rel_constraints': // Data from rel_constraints table
        print "($row[rcon_id],`$row[rcon_rdt_id]`,`$row[rcon_source_rt_id]`,`$row[rcon_target_rt_id]`,`$row[rcon_rdl_ids]`,
        `$row[rcon_ont_id]`,`$row[rcon_description]`),\n";
        break;
      
      // Additional case statements here for additional tables if required

      } // end Switch


} // end function print_row
                  
// END OF FILE
?>

