<?php

/**
* getDBStructureAsSQL.php: returns database definitions (rectypes, details etc.)
* as SQL statements ready for INSERT processing
*
* @param includeUgrps=1 will output user and group information in addition to definitions
* @param approvedDefsOnly=1 will only output Reserved and Approved definitions
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
require_once dirname(__FILE__).'/../../../hclient/framecontent/initPageMin.php';


global $mysqli, $isHTML, $startToken, $endToken;

// Normally jsut outputs definitions, this will include users/groups
$includeUgrps=@$_REQUEST["includeUgrps"];// returns null if not set

$approvedDefsOnly=@$_REQUEST["approvedDefsOnly"];// returns null if not set

$isHTML = (@$_REQUEST["plain"]!=1);//no html
// TO DO: filter for reserved and approved definitions only if this is set

$mysqli = $system->getMysqli();

$db_version = getDbVersion($mysqli);

define('HEURIST_DBID', $system->settings->get('sys_dbRegisteredID'));
define('EOL',"<br>\n");

// TODO: use HEURIST_DBVERSION TO SET THE VERSION HERE

// * IMPORTANT *
// Update the following when database FORMAT is changed:

//      Version info in common/config/initialise.php
//      admin/setup/dbcreate/blankDBStructure.sql - dump structure of hdb_Heurist_Core_Definitions database
//         and insert where indicated in file
//      admin/setup/dbcreate/coreDefinitions.txt (get this from the admin interface listing in SQL exchange format)
//      admin/setup/dbcreate/coreDefinitionsHuNI.txt (get this from the admin interface listing in SQL exchange format)
//      admin/setup/dbcreate/coreDefinitionsFAIMS.txt (get this from the admin interface listing in SQL exchange format)

// File headers to explain what the listing represents
// HTML is a fudge to make it readable in a browser, very useful for debug and cut/paste to coreDefinitions.txt
// rather inelegant from an IT perspective. Should probably be replaced with a more secure format
if($isHTML){
    print "<html><head>";
    print '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    print "</head><body>\n";
}
print "-- Heurist Definitions Exchange File  generated: ".date("d M Y @ H:i").EOL;
print "-- Installation = " . HEURIST_BASE_URL. EOL;
print "-- Database = " . $system->dbname() . EOL;
print "-- Program Version: ".HEURIST_VERSION.EOL;
print "-- Database Version: ".$db_version; // ** Do not change format of this line ** !!! it is checked to make sure vesions match
if($isHTML) {print "<br><br>\n";}
// Now output each of the definition tables as data for an insert statement. The headings are merely for documentation
// Each block of data is between a >>StartData>> and >>EndData>> markers
// This could perhaps be done more elegantly as JSON structures, but SQL inserts help to point up errors in fields

$startToken = ">>StartData>>";
$endToken = ">>EndData>>";
$endofFileToken = ">>EndOfFile>>";

// ------------------------------------------------------------------------------------------
// defRecTypeGroups

doPrintTable('RECORD TYPE GROUPS','defRecTypeGroups');
// ------------------------------------------------------------------------------------------
// defDetailTypeGroups

doPrintTable('DETAIL TYPE GROUPS','defDetailTypeGroups');

// ------------------------------------------------------------------------------------------
// defVocabularyGroups

doPrintTable('VOCABULARY GROUPS','defVocabularyGroups');

// ------------------------------------------------------------------------------------------
// Detail Type ONTOLOGIES

doPrintTable('ONTOLOGIES','defOntologies');

// ------------------------------------------------------------------------------------------
// Detail Type TERMS

doPrintTable('TERMS','defTerms');

// ------------------------------------------------------------------------------------------
// TERMS Links by reference   - export terms by reference ONLY

doPrintTable('TERMS REFERENCES','defTermsLinks', ', defTerms where trl_TermID=trm_ID AND trl_ParentID!=trm_ParentTermID');

// ------------------------------------------------------------------------------------------
// RECORD TYPES (this will be repeated for each of the tables)

doPrintTable('RECORD TYPES','defRecTypes');

// ------------------------------------------------------------------------------------------
// DETAIL TYPES

doPrintTable('DETAIL TYPES','defDetailTypes');

// ------------------------------------------------------------------------------------------
// RECORD STRUCTURE

doPrintTable('RECORD STRUCTURE','defRecStructure');

// ------------------------------------------------------------------------------------------
// RELATIONSHIP CONSTRAINTS

doPrintTable('RELATIONSHIP CONSTRAINTS','defRelationshipConstraints');

// ------------------------------------------------------------------------------------------
// defFileExtToMimetype

doPrintTable('FILE EXTENSIONS TO MIME TYPES','defFileExtToMimetype');

// ------------------------------------------------------------------------------------------
// defTranslations

doPrintTable('Definitions translations','defTranslations');

// ------------------------------------------------------------------------------------------
// usrSavedSearches  (added 24/6/2015)

doPrintTable('SAVED SEARCHES','usrSavedSearches');


// ------------------------------------------------------------------------------------------
// sysDashboard

doPrintTable('Dashboard entries','sysDashboard');

// As at June 2015, we are not extracting further data below this when creating new database
// Add later if required


// ------------------------------------------------------------------------------------------
// defCalcFunctions

doPrintTable('DEF CALC FUNCTIONS','defCalcFunctions');

// ------------------------------------------------------------------------------------------
// defCrosswalk

doPrintTable('DEF CROSSWALK','defCrosswalk');

// ------------------------------------------------------------------------------------------
// defURLPrefixes

doPrintTable('DEF URL PREFIXES','defURLPrefixes');

// ------------------------------------------------------------------------------------------
// Output the following only if parameter switch set and user is an admin

if (!$includeUgrps) {
    print "\n$endofFileToken\n";
    if($isHTML){
        print '</body></html>';
    }
    return;
}

if (! $system->isAdmin() ) {
    print "<html><body><p>You do not have sufficient privileges to list users</p><p><a href=".HEURIST_BASE_URL.">Return to Heurist</a></p></body></html>";
    return;
}
// ------------------------------------------------------------------------------------------
// sysUGrps

doPrintTable('Users and Groups','sysUGrps');

// ------------------------------------------------------------------------------------------
// sysUsrGrpLinks

doPrintTable('Users to Group membership and roles','sysUsrGrpLinks');

// ------------------------------------------------------------------------------------------
// usrHyperlinkFilters

doPrintTable('User\'s hyperlink filters','usrHyperlinkFilters');

// ------------------------------------------------------------------------------------------
// usrTags

doPrintTable('User\'s tags','usrTags');

// --------------------------------------------------------------------------------------
print "\n$endofFileToken\n";
if($isHTML){
    print '</body></html>';
}

/**
 * Prints the data of a given table as a series of SQL INSERT-like statements.
 *
 * This function fetches all rows from the specified table, formats them as
 * `('value1','value2',...),` and prints them, bracketed by global start/end tokens.
 * It handles HTML escaping for string values and substitutes originating DB IDs
 * and record IDs under certain conditions.
 *
 * @global \mysqli $mysqli The global mysqli database connection object.
 * @global bool $isHTML If true, output includes HTML tags for browser readability.
 * @global string $startToken Token to print before the data block.
 * @global string $endToken Token to print after the data block.
 *
 * @param string $desc A description of the table/data being printed.
 * @param string $tname The name of the database table to export.
 * @param string|null $where (Optional) An additional WHERE clause (including any JOINs)
 *                           to append to the SELECT query.
 * @return void
 */
function doPrintTable($desc, $tname, $where=null)
{
    global $mysqli, $isHTML, $startToken, $endToken;

    print "\n\n\n-- $desc \n";
    if($isHTML) {print "<p>";}

    $flds_list = mysql__select_assoc2($mysqli, 'SHOW COLUMNS FROM '.$tname);
    if($tname=='defTermsLinks'){
        array_shift($flds_list);//remove primary key field
    }
    $flds_names = array_keys($flds_list);
    $flds = '`'.implode('`,`', $flds_names).'`';
    print "-- $flds \n";
    $query = "select $flds from $tname";

    if($where!=null){
        $query = $query.$where;
    }

    $res = $mysqli->query($query);
    if($res){

        if($isHTML) {print "<p>";}
        print "\n$startToken\n";

        //get table prefix
        $id_field = $flds_names[0];
        $prefix = substr($id_field,0,3);
        while ($row = $res->fetch_assoc()) {

            $vals = array();
            foreach($flds_list as $fld => $type){

                if($prefix=='rty' && !($row[$id_field]>0)) {continue;}

                $val = $row[$fld];
                if(strpos($type,'text')!==false || strpos($type,'varchar')!==false){
                    $val = htmlspecialchars($mysqli->real_escape_string($val));
                }elseif(strpos($fld,'OriginatingDBID')!==false){
                    if(!($val>0)){
                        $val = HEURIST_DBID; //if local - show this db reg id
                    }
                }elseif(strpos($fld,'IDInOriginatingDB')!==false){
                    if(HEURIST_DBID>0 && !($val>0)){
                        $val = $row[$id_field];
                    }
                }
                $vals[] = $val;
            }
            print "('".implode("','",$vals)."'),";

            if ($_REQUEST['pretty']) {
                print"<br>";
            }
        }//while
        $res->close();
        print "$endToken\n";
    }else{
        print '-- '.$mysqli->error;
    }


    if($isHTML) {print "<p>&nbsp;<p>&nbsp;<p>";}
}
