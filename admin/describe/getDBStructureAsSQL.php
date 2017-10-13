<?php

/**
* getDBStructure.php: returns database definitions (rectypes, details etc.)
* as SQL statements ready for INSERT processing
*
* @param includeUgrps=1 will output user and group information in addition to definitions
* @param approvedDefsOnly=1 will only output Reserved and Approved definitions
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
define('SKIP_VERSIONCHECK2', 1);

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

// Normally jsut outputs definitions, this will include users/groups
$includeUgrps=@$_REQUEST["includeUgrps"];	// returns null if not set

$approvedDefsOnly=@$_REQUEST["approvedDefsOnly"];	// returns null if not set

$isHTML=(@$_REQUEST["plain"]!=1); //no html
// TO DO: filter for reserved and approved definitions only if this is set


// Deals with all the database connections stuff
if("hdb_".$_REQUEST["db"]!=DATABASE){
    mysql_connection_select("hdb_".@$_REQUEST["db"]);
}else{
    mysql_connection_select(DATABASE);
}


if(mysql_error()) {
    die("Could not get database structure from given database source, MySQL error - unable to connect to database.");
}

$query = "select * from sysIdentification";
$res = mysql_query($query);
if($res){
    $row = mysql_fetch_assoc($res);
    $db_version = $row['sys_dbVersion'].'.'.$row['sys_dbSubVersion'].'.'.$row['sys_dbSubSubVersion'];
}else{
    $db_version = HEURIST_DBVERSION; 
}



//$r = mysql_query("SELECT DATABASE()") or die(mysql_error());
//error_log("DATABASE IS ".mysql_result($r,0));    

// TODO: use HEURIST_DBVERSION TO SET THE VERSION HERE

// * IMPORTANT *
// Update the following when database FORMAT is changed:

//      Version info in common/config/initialise.php
//      admin/setup/dbcreate/blankDBStructure.sql - dump structure of hdb_Heurist_Core_Definitions database
//         and insert where indicated in file
//      admin/setup/dbcreate/blankDBStructureDefinitionsOnly.sql - copy blankDBStructure.sql and delete
//         non-definitional tables for temp db creation speed
//      admin/setup/dbcreate/coreDefinitions.txt (get this from the admin interface listing in SQL exchange format)
//      admin/setup/dbcreate/coreDefinitionsHuNI.txt (get this from the admin interface listing in SQL exchange format)
//      admin/setup/dbcreate/coreDefinitionsFAIMS.txt (get this from the admin interface listing in SQL exchange format)

// List of fields in the include files in admin/structure/crosswalk, used for getDBStructure and insertions
// print statements at the end of getDBStructure.php, must match the include files IN ORDER

// File headers to explain what the listing represents
// HTML is a fudge to make it readable in a browser, very useful for debug and cut/paste to coreDefinitions.txt
// rather inelegant from an IT perspective. Should probably be replaced with a more secure format
if($isHTML){
print "<html><head>";
print '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
print "</head><body>\n";
}
print "-- Heurist Definitions Exchange File  generated: ".date("d M Y @ H:i")."<br>\n";
print "-- Installation = " . HEURIST_BASE_URL. "<br>\n";
print "-- Database = " . HEURIST_DBNAME . "<br>\n";
print "-- Program Version: ".HEURIST_VERSION."<br>\n";
print "-- Database Version: ".$db_version; // ** Do not change format of this line ** !!! it is checked to make sure vesions match
if($isHTML) print "<br><br>\n";
// Now output each of the definition tables as data for an insert statement. The headings are merely for documentation
// Each block of data is between a >>StartData>> and >>EndData>> markers
// This could perhaps be done more elegantly as JSON structures, but SQL inserts help to point up errors in fields

$startToken = ">>StartData>>";
$endToken = ">>EndData>>";
$endofFileToken = ">>EndOfFile>>";

// ------------------------------------------------------------------------------------------
// defRecTypeGroups

print "\n\n\n-- RECORD TYPE GROUPS";print "\n";
if($isHTML) print "<p>";
include HEURIST_DIR.'admin/structure/crosswalk/defRecTypeGroupsFields.inc'; // sets value of $flds
print "-- $flds \n";
$query = "select $flds from defRecTypeGroups";
$res = mysql_query($query);
$fmt = 'defRecTypeGroups';   // update format if fields added

if($isHTML) print "<p>";
print "\n$startToken\n";
while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
print "$endToken\n";
if($isHTML) print "<p>&nbsp;<p>&nbsp;<p>";

// ------------------------------------------------------------------------------------------
// defDetailTypeGroups

print "\n\n\n-- DETAIL TYPE GROUPS";print "\n";
if($isHTML) print "<p>";
include HEURIST_DIR.'admin/structure/crosswalk/defDetailTypeGroupsFields.inc'; // sets value of $flds
print "-- $flds \n";
$query = "select $flds from defDetailTypeGroups";
$res = mysql_query($query);
$fmt = 'defDetailTypeGroups'; // update format if fields added

if($isHTML) print "<p>";
print "\n$startToken\n";
while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
print "$endToken\n";
if($isHTML) print "<p>&nbsp;<p>&nbsp;<p>";

// ------------------------------------------------------------------------------------------
// Detail Type ONTOLOGIES
print "\n\n\n-- ONTOLOGIES";print "\n";
if($isHTML) print "<p>";
include HEURIST_DIR.'admin/structure/crosswalk/defOntologiesFields.inc'; // sets value of $flds
print "-- $flds \n";
$query = "select $flds from defOntologies";

$res = mysql_query($query);
$fmt = 'defOntologies'; // update format if fields added

if($isHTML) print "<p>";
print "\n$startToken\n";
while ($row = mysql_fetch_assoc($res)) { 
    @print_row($row, $fmt); 
}
print "$endToken\n";
if($isHTML) print "<p>&nbsp;<p>&nbsp;<p>";

// ------------------------------------------------------------------------------------------
// Detail Type TERMS

print "\n\n\n-- TERMS";print "\n";
if($isHTML) print "<p>";
include HEURIST_DIR.'admin/structure/crosswalk/defTermsFields.inc'; // sets value of $flds
print "-- $flds \n";
$query = "select $flds from defTerms";
$res = mysql_query($query);
$fmt = 'defTerms';  // update format if fields added

if($isHTML) print "<p>";
print "\n$startToken\n";
while ($row = mysql_fetch_assoc($res)) {   @print_row($row, $fmt); }
print "$endToken\n";
if($isHTML) print "<p>&nbsp;<p>&nbsp;<p>";


// ------------------------------------------------------------------------------------------
// RECORD TYPES (this will be repeated for each of the tables)

print "\n-- RECORD TYPES";print "\n";
if($isHTML) print "<p>";
include HEURIST_DIR.'admin/structure/crosswalk/defRecTypesFields.inc'; // sets value of $flds
print "-- $flds \n";
$query = "select $flds from defRecTypes";
$res = mysql_query($query);
$fmt = 'defRecTypes'; // update format if fields added

if($isHTML) print "<p>";
print "\n$startToken\n";
while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
print "\n$endToken\n";
if($isHTML) print "<p>&nbsp;<p>&nbsp;<p>";


// ------------------------------------------------------------------------------------------
// DETAIL TYPES

print "\n\n\n-- DETAIL TYPES";print "\n";
if($isHTML) print "<p>";
include HEURIST_DIR.'admin/structure/crosswalk/defDetailTypesFields.inc'; // sets value of $flds
print "-- $flds \n";
$query = "select $flds from defDetailTypes";
$res = mysql_query($query);
$fmt = 'defDetailTypes';  // update format if fields added

if($isHTML) print "<p>";
print "\n$startToken\n";
while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
print "\n$endToken\n";
if($isHTML) print "<p>&nbsp;<p>&nbsp;<p>";


// ------------------------------------------------------------------------------------------
// RECORD STRUCTURE

print "\n\n\n-- RECORD STRUCTURE";print "\n";
if($isHTML) print "<p>";
include HEURIST_DIR.'admin/structure/crosswalk/defRecStructureFields.inc'; // sets value of $flds
print "-- $flds \n";
$query = "select $flds from defRecStructure";
$res = mysql_query($query);
$fmt = 'defRecStructure'; // update format if fields added

if($isHTML) print "<p>";
print "\n$startToken\n";
while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
print "\n$endToken\n";
if($isHTML) print "<p>&nbsp;<p>&nbsp;<p>";

// ------------------------------------------------------------------------------------------
// RELATIONSHIP CONSTRAINTS

print "\n\n\n-- RELATIONSHIP CONSTRAINTS";print "\n";
if($isHTML) print "<p>";
include HEURIST_DIR.'admin/structure/crosswalk/defRelationshipConstraintsFields.inc'; // sets value of $flds
print "-- $flds \n";
$query = "select $flds from defRelationshipConstraints";
$res = mysql_query($query);
$fmt = 'defRelationshipConstraints'; // update format if fields added

if($isHTML) print "<p>";
print "\n$startToken\n";
while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
print "\n$endToken\n";
if($isHTML) print "<p>&nbsp;<p>&nbsp;<p>";

// ------------------------------------------------------------------------------------------
// defFileExtToMimetype

print "\n\n\n-- FILE EXTENSIONS TO MIME TYPES";print "\n";
if($isHTML) print "<p>";
include HEURIST_DIR.'admin/structure/crosswalk/defFileExtToMimetypeFields.inc'; // sets value of $flds
print "-- $flds \n";
$query = "select $flds from defFileExtToMimetype";
$res = mysql_query($query);
$fmt = 'defFileExtToMimetype'; // update format if fields added

if($isHTML) print "<p>";
print "\n$startToken\n";
while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
print "\n$endToken\n";
if($isHTML) print "<p>&nbsp;<p>&nbsp;<p>";

// ------------------------------------------------------------------------------------------
// defTranslations

print "\n\n\n-- Definitions translations";print "\n";
if($isHTML) print "<p>";
include HEURIST_DIR.'admin/structure/crosswalk/defTranslationsFields.inc'; // sets value of $flds
print "-- $flds \n";
$query = "select $flds from defTranslations where trn_Source in
('rty_Name', 'dty_Name', 'ont_ShortName', 'vcb_Name', 'trm_Label', 'rst_DisplayName', 'rtg_Name')";
// filters to only definition (not data) translations - add others as required
$res = mysql_query($query);
$fmt = 'defTranslations'; // update format if fields added

if($isHTML) print "<p>";
print "\n$startToken\n";
while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
print "\n$endToken\n";
if($isHTML) print "<p>&nbsp;<p>&nbsp;<p>";

// ------------------------------------------------------------------------------------------
// usrSavedSearches  (added 24/6/2015)

print "\n\n\n-- SAVED SEARCHES";print "\n";
if($isHTML) print "<p>";
include HEURIST_DIR.'admin/structure/crosswalk/usrSavedSearchesFields.inc'; // sets value of $flds
print "-- $flds \n";
$query = "select $flds from usrSavedSearches";
$res = mysql_query($query);
$fmt = 'usrSavedSearches'; // update format if fields added

if($isHTML) print "<p>";
print "\n$startToken\n";
while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
print "$endToken\n";
if($isHTML) print "<p>&nbsp;<p>&nbsp;<p>";



// As at June 2015, we are not extracting further data below this when creating new database
// Add later if required


// ------------------------------------------------------------------------------------------
// defCalcFunctions

print "\n\n\n-- DEF CALC FUNCTIONS";print "\n";
if($isHTML) print "<p>";
include HEURIST_DIR.'admin/structure/crosswalk/defCalcFunctionsFields.inc'; // sets value of $flds
print "-- $flds \n";
$query = "select $flds from defCalcFunctions";
$res = mysql_query($query);
$fmt = 'defCalcFunctions'; // update format if fields added

if($isHTML) print "<p>";
print "\n$startToken\n";
while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
print "\n$endToken\n";
if($isHTML) print "<p>&nbsp;<p>&nbsp;<p>";

// ------------------------------------------------------------------------------------------
// defCrosswalk

print "\n\n\n-- DEF CROSSWALK";print "\n";
if($isHTML) print "<p>";
include HEURIST_DIR.'admin/structure/crosswalk/defCrosswalkFields.inc'; // sets value of $flds
print "-- $flds \n";
$query = "select $flds from defCrosswalk";
$res = mysql_query($query);
$fmt = 'defCrosswalk'; // update format if fields added

if($isHTML) print "<p>";
print "\n$startToken\n";
while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
print "\n$endToken\n";
if($isHTML) print "<p>&nbsp;<p>&nbsp;<p>";

// ------------------------------------------------------------------------------------------
// defLanguages

print "\n\n\n-- DEF LANGUAGE";print "\n";
if($isHTML) print "<p>";
include HEURIST_DIR.'admin/structure/crosswalk/defLanguagesFields.inc'; // sets value of $flds
print "-- $flds \n";
$query = "select $flds from defLanguages";
$res = mysql_query($query);
$fmt = 'defLanguages';  // update format if fields added

if($isHTML) print "<p>";
print "\n$startToken\n";
while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
print "\n$endToken\n";
if($isHTML) print "<p>&nbsp;<p>&nbsp;<p>";

// ------------------------------------------------------------------------------------------
// defURLPrefixes

print "\n\n\n-- DEF URL PREFIXES";print "\n";
if($isHTML) print "<p>";
include HEURIST_DIR.'admin/structure/crosswalk/defURLPrefixesFields.inc'; // sets value of $flds
print "-- $flds \n";
$query = "select $flds from defURLPrefixes";
$res = mysql_query($query);
$fmt = 'defURLPrefixes';  // update format if fields added

if($isHTML) print "<p>";
print "\n$startToken\n";
while ($row = mysql_fetch_assoc($res)) { @print_row($row, $fmt); }
print "\n$endToken\n";
if($isHTML) print "<p>&nbsp;<p>&nbsp;<p>";

// ------------------------------------------------------------------------------------------
// Output the following only if parameter switch set and user is an admin

if (!$includeUgrps) {
    print "\n$endofFileToken\n";
    if($isHTML){
        print '</body></html>';
    }
    return;
}

if (! is_admin()) {
    print "<html><body><p>You do not have sufficient privileges to list users</p><p><a href=".HEURIST_BASE_URL.">Return to Heurist</a></p></body></html>";
    return;
}
// ------------------------------------------------------------------------------------------
// sysUGrps

print "\n\n\n-- Users and Groups";print "\n";
if($isHTML) print "<p>";
include HEURIST_DIR.'admin/structure/crosswalk/sysUGrpsFields.inc'; // sets value of $flds
print "-- $flds \n";
$query = "select $flds from sysUGrps";
$res = mysql_query($query);
$fmt = 'sysUGrps'; // update format if fields added

if($isHTML) print "<p>";
print "\n$startToken\n";
while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
print "\n$endToken\n";
if($isHTML) print "<p>&nbsp;<p>&nbsp;<p>";

// ------------------------------------------------------------------------------------------
// sysUsrGrpLinks

print "\n\n\n-- Users to Group membership and roles";print "\n";
if($isHTML) print "<p>";
include HEURIST_DIR.'admin/structure/crosswalk/sysUsrGrpLinksFields.inc'; // sets value of $flds
print "-- $flds \n";
$query = "select $flds from sysUsrGrpLinks";
$res = mysql_query($query);
$fmt = 'sysUsrGrpLinks'; // update format if fields added

if($isHTML) print "<p>";
print "\n$startToken\n";
while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
print "\n$endToken\n";
if($isHTML) print "<p>&nbsp;<p>&nbsp;<p>";

// ------------------------------------------------------------------------------------------
// usrHyperlinkFilters

print "\n\n\n-- User's hyperlink filters";print "\n";
if($isHTML) print "<p>";
include HEURIST_DIR.'admin/structure/crosswalk/usrHyperlinkFiltersFields.inc'; // sets value of $flds
print "-- $flds \n";
$query = "select $flds from usrHyperlinkFilters";
$res = mysql_query($query);
$fmt = 'usrHyperlinkFilters'; // update format if fields added

if($isHTML) print "<p>";
print "\n$startToken\n";
while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
print "\n$endToken\n";
if($isHTML) print "<p>&nbsp;<p>&nbsp;<p>";

// ------------------------------------------------------------------------------------------
// usrTags

print "\n\n\n-- User's tags";print "\n";
if($isHTML) print "<p>";
include HEURIST_DIR.'admin/structure/crosswalk/usrTagsFields.inc'; // sets value of $flds
print "-- $flds \n";
$query = "select $flds from UsrTags";
$res = mysql_query($query);
$fmt = 'UsrTags';  // update format if fields added

if($isHTML) print "<p>";
print "\n$startToken\n";
while ($row = mysql_fetch_assoc($res)) { print_row($row, $fmt); }
print "\n$endToken\n";
if($isHTML) print "<p>&nbsp;<p>&nbsp;<p>";

// --------------------------------------------------------------------------------------
print "\n$endofFileToken\n";
if($isHTML){
    print '</body></html>';
}

function print_row($row,$fmt) {

    // Prints a formatted representation of the data retreived for one row in the query
    // Make sure that the query you passed in generates the fields you want to print
    // Specify fields with $row[fieldname] or $row['fieldname'] (in most cases the quotes
    // are unecessary although perhaps syntactically proper)

    // *** If the order in these prints does not corresponmd with the order in the include files listing fields,
    // data will load in the wrong fields and the crosswalking function will fail unpredictably ***

    switch ($fmt) {  // select the output formatting according to the table

        case 'defRecTypes': // Data from the defRecTypes table
            if($row['rty_ID'] != 0) {
                $rty_Name = mysql_real_escape_string($row['rty_Name']); // escapes RTF-8 characters
                $rty_Description = mysql_real_escape_string($row['rty_Description']);
                $rty_TitleMask = mysql_real_escape_string($row['rty_TitleMask']);
                $rty_CanonicalTitleMask = mysql_real_escape_string($row['rty_CanonicalTitleMask']);
                $rty_Plural = mysql_real_escape_string($row['rty_Plural']);
                $rty_NameInOriginatingDB = mysql_real_escape_string($row['rty_NameInOriginatingDB']);
                $rty_RecTypeGroupID = mysql_real_escape_string($row['rty_RecTypeGroupID']);
                $rty_ReferenceURL = mysql_real_escape_string($row['rty_ReferenceURL']);
                $rty_AlternativeRecEditor = mysql_real_escape_string($row['rty_AlternativeRecEditor']);
                
                $rty_NameInOriginatingDB = $row['rty_NameInOriginatingDB']?mysql_real_escape_string($row['rty_NameInOriginatingDB']):$rty_Name;
                $rty_IDInOriginatingDB = $row['rty_IDInOriginatingDB'];
                $rty_OriginatingDBID = $row['rty_OriginatingDBID']>0?$row['rty_OriginatingDBID']:HEURIST_DBID;
                if(HEURIST_DBID>0 && !($rty_IDInOriginatingDB>0)){
                    $rty_IDInOriginatingDB = $row['rty_ID']; 
                    $rty_OriginatingDBID = HEURIST_DBID; 
                }
                
                print "('$row[rty_ID]','$rty_Name','$row[rty_OrderInGroup]','$rty_Description','$rty_TitleMask',
                '$rty_CanonicalTitleMask','$rty_Plural','$row[rty_Status]',
                '$rty_OriginatingDBID','$rty_NameInOriginatingDB','$rty_IDInOriginatingDB',
                '$row[rty_NonOwnerVisibility]','$row[rty_ShowInLists]','$rty_RecTypeGroupID','$row[rty_RecTypeModelIDs]',
                '$row[rty_FlagAsFieldset]','$rty_ReferenceURL','$rty_AlternativeRecEditor','$row[rty_Type]',
                '$row[rty_ShowURLOnEditForm]','$row[rty_ShowDescriptionOnEditForm]','$row[rty_LocallyModified]'),";
            }
            break;

        case 'defDetailTypes': // Data from the recDetails table
        
            $dty_FieldSetRecTypeID = $row['dty_FieldSetRecTypeID']>0?$row['dty_FieldSetRecTypeID']:0;
        
            $dty_Name = mysql_real_escape_string($row['dty_Name']);
            $dty_Documentation = mysql_real_escape_string($row['dty_Documentation']);
            $dty_HelpText = mysql_real_escape_string($row['dty_HelpText']);
            $dty_ExtendedDescription = mysql_real_escape_string($row['dty_ExtendedDescription']);
            $dty_EntryMask = mysql_real_escape_string($row['dty_EntryMask']);
            $dty_JsonTermIDTree = mysql_real_escape_string($row['dty_JsonTermIDTree']);
            $dty_TermIDTreeNonSelectableIDs = mysql_real_escape_string($row['dty_TermIDTreeNonSelectableIDs']);
            $dty_PtrTargetRectypeIDs = mysql_real_escape_string($row['dty_PtrTargetRectypeIDs']);
            $dty_SemanticReferenceURL = mysql_real_escape_string($row['dty_SemanticReferenceURL']);
            
            $dty_NameInOriginatingDB = $row['dty_NameInOriginatingDB']?mysql_real_escape_string($row['dty_NameInOriginatingDB']):$dty_Name;
            $dty_IDInOriginatingDB = $row['dty_IDInOriginatingDB'];
            $dty_OriginatingDBID = $row['dty_OriginatingDBID']>0?$row['dty_OriginatingDBID']:HEURIST_DBID;
            if(HEURIST_DBID>0 && !($dty_IDInOriginatingDB>0)){
                $dty_IDInOriginatingDB = $row['dty_ID']; 
                $dty_OriginatingDBID = HEURIST_DBID; 
            }
            
            $dty_SemanticReferenceURL =  mysql_real_escape_string($row['dty_PtrTargetRectypeIDs']);
            print "('$row[dty_ID]','$dty_Name','$dty_Documentation','$row[dty_Type]','$dty_HelpText',
            '$dty_ExtendedDescription','$dty_EntryMask','$row[dty_Status]','$dty_OriginatingDBID',
            '$dty_NameInOriginatingDB','$dty_IDInOriginatingDB','$row[dty_DetailTypeGroupID]',
            '$row[dty_OrderInGroup]','$dty_JsonTermIDTree','$dty_TermIDTreeNonSelectableIDs',
            '$dty_PtrTargetRectypeIDs',$dty_FieldSetRecTypeID,'$row[dty_ShowInLists]','$row[dty_NonOwnerVisibility]',
            '$row[dty_LocallyModified]','$dty_SemanticReferenceURL'),";
            break;

        case 'defRecStructure': // Data from the defRecStructure table
            $rst_DisplayName = mysql_real_escape_string($row['rst_DisplayName']);
            $rst_DisplayHelpText = mysql_real_escape_string($row['rst_DisplayHelpText']);
            $rst_DisplayExtendedDescription = mysql_real_escape_string($row['rst_DisplayExtendedDescription']);
            $rst_DefaultValue = mysql_real_escape_string($row['rst_DefaultValue']);
            $rst_FilteredJsonTermIDTree = mysql_real_escape_string($row['rst_FilteredJsonTermIDTree']);
            $rst_TermIDTreeNonSelectableIDs = mysql_real_escape_string($row['rst_TermIDTreeNonSelectableIDs']);
            $rst_PtrFilteredIDs = mysql_real_escape_string($row['rst_PtrFilteredIDs']);
            $rst_CalcFieldMask = mysql_real_escape_string($row['rst_CalcFieldMask']);
            $rst_EntryMask = mysql_real_escape_string($row['rst_EntryMask']);
            print "('$row[rst_ID]',
            '$row[rst_RecTypeID]',
            '$row[rst_DetailTypeID]',
            '$rst_DisplayName',
            '$rst_DisplayHelpText',
            '$rst_DisplayExtendedDescription',
            '$row[rst_DisplayOrder]',
            '$row[rst_DisplayWidth]',
            '$row[rst_DisplayHeight]',
            '$rst_DefaultValue',
            '$row[rst_RecordMatchOrder]',
            '$row[rst_CalcFunctionID]',
            '$rst_CalcFieldMask',
            '$row[rst_RequirementType]',
            '$row[rst_NonOwnerVisibility]',
            '$row[rst_Status]',
            '$row[rst_MayModify]',
            '$row[rst_OriginatingDBID]',
            '$row[rst_IDInOriginatingDB]',
            '$row[rst_MaxValues]',
            '$row[rst_MinValues]',
            '$row[rst_InitialRepeats]',
            '$row[rst_DisplayDetailTypeGroupID]',
            '$rst_FilteredJsonTermIDTree',
            '$rst_PtrFilteredIDs',
            '$row[rst_CreateChildIfRecPtr]',
            '$row[rst_OrderForThumbnailGeneration]',
            '$rst_TermIDTreeNonSelectableIDs',
            '$row[rst_ShowDetailCertainty]',
            '$row[rst_ShowDetailAnnotation]',
            '$row[rst_NumericLargestValueUsed]',
            '$rst_EntryMask',
            '$row[rst_LocallyModified]'),";
            break;

        case 'defTerms': // Data from the rec_details_lookup table
            $trm_Label = mysql_real_escape_string($row['trm_Label']);
            $trm_Description = mysql_real_escape_string($row['trm_Description']);
            $trm_SemanticReferenceURL = mysql_real_escape_string($row['trm_SemanticReferenceURL']);
            $trm_IllustrationURL = mysql_real_escape_string($row['trm_IllustrationURL']);
            
            $trm_NameInOriginatingDB = $row['trm_NameInOriginatingDB']?mysql_real_escape_string($row['trm_NameInOriginatingDB']):$trm_Label;
            $trm_IDInOriginatingDB = $row['trm_IDInOriginatingDB'];
            $trm_OriginatingDBID = $row['trm_OriginatingDBID']>0?$row['trm_OriginatingDBID']:HEURIST_DBID;
            if(HEURIST_DBID>0 && !($trm_IDInOriginatingDB>0)){
                $trm_IDInOriginatingDB = $row['trm_ID']; 
                $trm_OriginatingDBID = HEURIST_DBID; 
            }
            
            print "('$row[trm_ID]','$trm_Label','$row[trm_InverseTermId]',
            '$trm_Description','$row[trm_Status]',
            '$trm_OriginatingDBID','$trm_NameInOriginatingDB','$trm_IDInOriginatingDB',
            '$row[trm_AddedByImport]','$row[trm_IsLocalExtension]','$row[trm_Domain]','$row[trm_OntID]',
            '$row[trm_ChildCount]','$row[trm_ParentTermID]','$row[trm_Depth]','$row[trm_Modified]','$row[trm_LocallyModified]',
            '$row[trm_Code]','$trm_SemanticReferenceURL','$trm_IllustrationURL'),";
            // WARNING! This needs to be updated in sync with new db structure to be added for DB Version 1.2.0 for FAIMS compatibility
            // '$row[trm_ReferenceURL]','$row[trm_IllustrationURL]'),"; // for db version 1.2.0 @ 1/10/13
            break;

        case 'defOntologies': // Data from Ontologies table
            $ont_ShortName = mysql_real_escape_string($row['ont_ShortName']);
            $ont_FullName = mysql_real_escape_string($row['ont_FullName']);
            $ont_Description = mysql_real_escape_string($row['ont_Description']);
            $ont_RefURI = mysql_real_escape_string($row['ont_RefURI']);
            $ont_NameInOriginatingDB = mysql_real_escape_string($row['ont_NameInOriginatingDB']);
            print "('$row[ont_ID]','$ont_ShortName','$ont_FullName','$ont_Description',
            '$ont_RefURI','$row[ont_Status]',
            '$row[ont_OriginatingDBID]','$ont_NameInOriginatingDB','$row[ont_IDInOriginatingDB]',
            '$row[ont_Order]','$row[ont_LocallyModified]'),";
            break;

        case 'defRelationshipConstraints': // Data from relationship constraints table
            $rcs_Description = mysql_real_escape_string($row['rcs_Description']);
            print "('$row[rcs_ID]','$row[rcs_SourceRectypeID]','$row[rcs_TargetRectypeID]','$rcs_Description',
            '$row[rcs_RelationshipsLimit]','$row[rcs_Status]',
            '$row[rcs_OriginatingDB]','$row[rcs_IDInOriginatingDB]',
            '$row[rcs_TermID]','$row[rcs_TermLimit]','$row[rcs_LocallyModified]'),";
            break;

        case 'defFileExtToMimetype': // Data from field extension to mimetype table
            $fxm_Extension = mysql_real_escape_string($row['fxm_Extension']);
            $fxm_MimeType = mysql_real_escape_string($row['fxm_MimeType']);
            $fxm_IconFileName = mysql_real_escape_string($row['fxm_IconFileName']);
            $fxm_FiletypeName = mysql_real_escape_string($row['fxm_FiletypeName']);
            $fxm_ImagePlaceholder = mysql_real_escape_string($row['fxm_ImagePlaceholder']);
            print "('$fxm_Extension','$fxm_MimeType',
            '$row[fxm_OpenNewWindow]','$fxm_IconFileName','$fxm_FiletypeName','$fxm_ImagePlaceholder'),";
            break;

        case 'defRecTypeGroups': // Data from record type classes table
            $rtg_Name = mysql_real_escape_string($row['rtg_Name']);
            $rtg_Description = mysql_real_escape_string($row['rtg_Description']);
            print "('$row[rtg_ID]','$rtg_Name','$row[rtg_Domain]','$row[rtg_Order]','$rtg_Description'),";
            break;

        case 'defDetailTypeGroups': // Data from detail type classes table
            $dtg_Name = mysql_real_escape_string($row['dtg_Name']);
            $dtg_Description = mysql_real_escape_string($row['dtg_Description']);
            print "('$row[dtg_ID]','$dtg_Name','$row[dtg_Order]','$dtg_Description'),";
            break;

        case 'defTranslations':
            $trn_Translation = mysql_real_escape_string($row['trn_Translation']);
            print "('$row[trn_ID]','$row[trn_Source]','$row[trn_Code]','$row[trn_LanguageCode3]','$trn_Translation'),";
            break;

        case 'usrSavedSearches':
            $svs_Name = mysql_real_escape_string($row['svs_Name']);
            $svs_Query = mysql_real_escape_string($row['svs_Query']);
            print "('$row[svs_ID]','$svs_Name','$row[svs_Added]','$row[svs_Modified]',
                    '$svs_Query','$row[svs_UGrpID]','$row[svs_ExclusiveXSL]'),";
            break;

        case 'defCalcFunctions':
            $cfn_FunctionSpecification = mysql_real_escape_string($row['cfn_FunctionSpecification']);
            print "('$row[cfn_ID]','$row[cfn_Domain]','$cfn_FunctionSpecification'),";
            break;

        case 'defCrosswalk':
            print "('$row[crw_ID]',
            '$row[crw_SourcedbID]','$row[crw_SourceCode]',
            '$row[crw_DefType]','$row[crw_LocalCode]',
            '$row[crw_Modified]'),";
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

            // Note: these have not yet (Sep 2011) been implemented in buildCrosswalks.php as they are not really needed

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
            $ugr_TargetEmailAddresses = mysql_real_escape_string($row['ugr_TargetEmailAddresses']);
            $ugr_URLs = mysql_real_escape_string($row['ugr_URLs']);
            print "('$row[ugr_ID]','$row[ugr_Type]','$ugr_Name','$ugr_LongName','$ugr_Description',
            '$ugr_Password','$ugr_eMail','$ugr_FirstName','$ugr_LastName','$ugr_Department',
            '$ugr_Organisation','$ugr_City','$ugr_State','$ugr_Postcode','$ugr_Interests',
            '$row[ugr_Enabled]','$row[ugr_LastLoginTime]','$row[ugr_MinHyperlinkWords]','$row[ugr_LoginCount]',
            '$row[ugr_IsModelUser]','$ugr_IncomingEmailAddresses','$ugr_TargetEmailAddresses','$ugr_URLs','$row[ugr_FlagJT]'),";
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

    if ($_REQUEST['pretty']) {
        print"<br>";
    }

} // end function print_row
?>
