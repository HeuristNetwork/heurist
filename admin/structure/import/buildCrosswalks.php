<?php

/**
* buildCrosswalks.php, Gets definitions from a specified installation of Heurist and writes them
* either to a new DB, or temp DB
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

// crosswalk_builder.php  - gets definitions from a specified installation of Heurist
// Processes them into local definitions, allows the administrator to import definitions
// and stores equivalences in the def_crosswalks table.

// started: Ian Johnson 3 March 2010. Revised Ian Johnson 26 sep 2010 14.15 to new table/field names
// and added selection of definitions to be imported and crosswalk builder, plus instructions and pseudocode.
// 4 Aug 2011, changed to import table structures from blankDBStructure.sql and to
// include crosswalking during creation of a new database
// Added usrSavedStructures 24 Jun 2015

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

require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../../records/files/fileUtils.php');
require_once(dirname(__FILE__).'/../../../common/php/dbUtils.php');
require_once(dirname(__FILE__).'/../../../common/php/utilsMail.php');
require_once(dirname(__FILE__).'/../../../hserver/dbaccess/utils_db_load_script.php');


// ------Administrative stuff ------------------------------------------------------------------------------------

// Verify credentials of the user and check that they are an administrator, check no-one else is trying to
// change the definitions at the same time

if(!isset($isNewDB)) { $isNewDB = false; }
$isExistingDB = !$isNewDB; // for clarity

// Requires admin user if updating current database, even though get_definitions is open
// If it's processing the SQL file for a new database it does not
if ($isExistingDB) {
    if(isForAdminOnly("to get structure elements from another database")){
        return;
    }
}

require_once(dirname(__FILE__).'/../../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__)."/../../../common/php/utilsMail.php");

global $errorCreatingTables;
global $errorCreatingReason;
$errorCreatingTables = FALSE;

global $dbname;
global $tempDBName;

if($isNewDB) { // For new database, insert coreDefinitions.txt directly into tables, no temp database required
    $tempDBName =$newname;
    $dbname = $newname;
} // New database
else { // existing database needs temporary database to store data read and allow selection

    $dbname = DATABASE;
    $isNewDB = false;
    $tempDBName = "temp_".$dbname;
    // Deals with all the database connections stuff
    mysql_connection_insert(DATABASE);
} // existing database

// * IMPORTANT *
// If database format is changed, update version info, include files, sql files for new dbs etc.
// see comprehensive list in admin/describe/getDBStructureAsSQL.php


// -----Check not locked by admin -------------------------------

// THIS SECTION SHOULD BE ABSTRACTED AS A FUNCTION IN ONE OF THE LIBRARIES, perhaps in cred.php?

// ???? we should now mark the target (current) database for administrator access to avoid two administrators
// working on this at the same time. But need to provide a means of removing lock in case the
// connection is lost, eg. heartbeat on subsequent pages or a specific 'remove admin lock' link (easier)

// Check if someone else is already modifying database definitions, if so: stop

if($isNewDB){
    $definitions_filename = $templateFileName;

    if(!file_exists($definitions_filename)){
        $errorCreatingTables = true;
        $errorCreatingReason = 'Template file '.$templateFileName.' not found';
        $isCreateNew = true;
        echo ($definitions_filename." not found</br>");
        return false;
    }
}

if ($isExistingDB) {
    $res = mysql_query("select lck_UGrpID from sysLocks where lck_Action='buildcrosswalks'");
    // 6/9/11 $res is not being recognised as a valid MySQL result, and always returns false.
    // This appear to be identical to example in help. So the following test is not being processed
    // and the lock is ignored. The query works in MySQL
    // TODO: get this locking mechanism to work

    if (($res && mysql_num_rows($res)>0)) { // SQL OK and there is a lock record
        // error log says supplied argument is not a valid MySQL result resources
        @$row = mysql_fetch_array($res);
        if ( @$row && $row[0] != 0 && $row[0] != get_user_id()){
            echo "Definitions are already being modified or SQL failure on lock check.";
            header('Location: ' . HEURIST_BASE_URL . 'common/html/msgLockedByAdmin.html'); // put up informative failure message
            die("Definitions are already being modified.<p> If this is not the case, you will need to remove the locks on the database.<br>Use Utilities > Clear database locks (administrators only)");
        }
    } // detect lock and shuffle out

    // Mark database definitions as being modified by administrator
    mysql_connection_insert(DATABASE);
    $query = "insert into sysLocks (lck_UGrpID, lck_Action) VALUES (".(function_exists('get_user_id') ? get_user_id(): 0).", 'buildcrosswalks')";
    $res = mysql_query($query); // create sysLock

    // Create the Heurist structure for the temp database, using a shortened version of the new database template

    db_drop($tempDBName, false);

    if(!db_create($tempDBName) ||
    !db_script($tempDBName, HEURIST_DIR."admin/setup/dbcreate/blankDBStructureDefinitionsOnly.sql") ){
        unlockDatabase();
        exit();
    }
} // existing database

mysql_connection_insert($tempDBName); // Use temp database

// ------Find and set the source database-----------------------------------------------------------------------

// Query heurist.sydney.edu.au Heurist_Master_Index database to find the URL of the installation
// that you want to use as the source.

if($isNewDB) { // minimal definitions from coreDefinitions.txt - returns same format as getDBStructureAsSQL

    $file = fopen($definitions_filename, "r");
    $output = "";
    while(!feof($file)) {
        $output = $output . fgets($file, 4096);
    }
    fclose($file);
    $data = $output;
}

else

{ // Request data from source database using getDBStructureAsSQL.php

    //  Set information about the database you will be importing from
    global $source_db_id;
    if(!isset($_REQUEST["dbID"]) || $_REQUEST["dbID"] == 0) {

        unlockDatabase();
        die("Request for database structure to import does not specify a database - please advise the Heurist team through a bug report");

    } else {
        // Setup query to the exchange format feed from the selected database
        $source_db_id = $_REQUEST["dbID"];
        $source_db_name = $_REQUEST["dbName"];
        $source_db_prefix = @$_REQUEST["dbPrefix"] && @$_REQUEST["dbPrefix"] != "" ? @$_REQUEST["dbPrefix"] : null;

        
        if(checkSmtp()){
            $email_text = 'Read database #'.$source_db_id.' "'.$source_db_name.'" as template from "'.HEURIST_DBNAME.'" at '.HEURIST_SERVER_URL;
            $rv = sendEmail(HEURIST_MAIL_TO_INFO, "Read database as template", $email_text, null);
        }
        

        $regurl = $_REQUEST['dbURL'];

        // TODO: Remove this temporary fudge once Heurist_Master_Index updated to remove all references to H3
        if($regurl=='http://heurist.sydney.edu.au/h3/'){  //change the registered url on our server to new one
            $regurl = 'http://heurist.sydney.edu.au/heurist/';
        }

        // This is the correct URL for vsn 3.1.8 and above, March 2014, with a hiccup in latter half of 2015
        // when H3 code moved to a /migrated subdirectory, corrected just before Chrristmas 2015
        $source_url = $regurl."admin/describe/getDBStructureAsSQL.php?plain=1&db=".$source_db_name.(@$source_db_prefix?"&prefix=".$source_db_prefix:"");
    }

// http://heurist.sydney.edu.au/h4-ao/admin/describe/getDBStructureAsSQL.php?db=johns_Esparoutis_rentals

    //TODO: why is the second parameter 60? It's specified as bypasProxy = true
    $data = loadRemoteURLContentSpecial($source_url); // get the structure data

//error_log('result '.$data);//substr($data, 0, 500));
    mysql_connection_insert($tempDBName); // Use temp database


    // TODO: is this check for the word 'unable' really a good check of failure???
    //       why are we not testing for success in the form of a recognisable signature?
    if (!$data || substr($data, 0, 6) == "unable") { // failed
        unlockDatabase();
        if (!$data) {
            $msg="URL appears to be inaccessible (timeout); if the URL shown works in a web browser, your server may require a web proxy setting";
        } else {
            $msg="Server appears to be returning incorrect data, first 25 characters: ".substr($data, 0, 25)." ...";
        }
        die("<br>Source database <b> $source_db_id : $source_db_prefix$source_db_name </b>gave incorrect response<p>".
            "URL to structure service, tried: ".
            "<br /><a href=$source_url target=_blank>$source_url</a> <p>$msg");
    }

} // getting data from source database for import of definitions to an existing database

// Split received data into data sets for one table defined by >>StartData>> and >>EndData>> markers.

$startToken = ">>StartData>>"; // also defined in getDBStructureAsSQL.php

if(!strpos($data, $startToken)){

    if($isNewDB){
        die("<br>The data in Core Definitions file ( $definitions_filename ) did not correspond with the expected format. ".
        "<p/>Please advise Heurist team. The first few lines from this line are shown below :<xmp>".substr($data,1,2000)."</xmp>");
    }else{
        die("<br>The data returned from the structure script on the selected database ( <a href=$source_url>$source_url</a> ) did not correspond with the expected format. ".
        "<p/>Please advise Heurist team. The first few lines returned are shown below :<xmp>".substr($data,1,2000)."</xmp>");
    }
}

$endOfFile = ">>EndOfFile>>";
if(!$isNewDB && !strpos($data, $endOfFile)){

    die("<br>The data returned from the structure script on the selected database ( <a href=$source_url>$source_url</a> ) is not completed. It is not possible to detect end of file token. ".
        "<p/>Please advise Heurist team.");
}

$splittedData = explode($startToken, $data);
$tableNumber =1;

if ($isExistingDB)
{

    preg_match("/Database Version:\s*(\d+)\.(\d+)(?:\.(\d+))*/",$data,$sourceDBVersion);
    // $sourceDBVersion[0] = version string, 1, 2, 3 = ,major, minor, sub versions

    preg_match("/Vsn:\s*(\d+)\.(\d+)(?:\.(\d+))*/","Vsn: ".HEURIST_DBVERSION,$thisDBVersion); // $sourceDBVersion[0] = version string, 1, 2, 3 = ,major, minor, sub versions

    // we ignore following test if creating a new database, because the current database version is irrelevant,
    // the definition files determine the version created.
    if (!($sourceDBVersion[1] == $thisDBVersion[1] && $sourceDBVersion[2] == $thisDBVersion[2])) {
        
        $sMsg = 'You are running a version of Heurist which expects database version '.HEURIST_DBVERSION
            .', but the data read from the selected database '.$source_db_name.' is in database version '.implode('.',$sourceDBVersion);
        if($sourceDBVersion[1] < $thisDBVersion[1] || 
            ($sourceDBVersion[1]==$thisDBVersion[1] && $sourceDBVersion[2]<$thisDBVersion[2]))
        {
            $sMsg = $sMsg.'. Please contact support at HeuristNetwork dot org or ask the database owner to update their database '
            .'(this will normally happen automatically if they use the same version of Heurist as you are using to access their database).';
        }else{
            $sMsg = $sMsg.'. Please contact the system administrator to update the copy of Heurist on this server.';
        }
        
        echo '<p>'.$sMsg.'</p>';
        
        //unlock
        unlockDatabase();
        exit();
    }
}

function getNextDataSet($splittedData) { // returns and removes the first set of data between markers from $splitteddata
    global $tableNumber;
    $endToken = ">>EndData>>"; // also defined in getDBStructureAsSQL.php
    if(!$tableNumber) {
        $tableNumber = 1;
    }

//error_log(">>>>>DATA ".count($splittedData)."  ".$tableNumber." ".$splittedData[$tableNumber]);

    // TODO: this is a horrible approach to splitting out the data. Should be rewritten.
    // Works, so for the moment, "if it ain't broke, don't fix it ..."
    if(count($splittedData) > $tableNumber) { // what the hell does this do? fortunately it is always true!
        $splittedData2 = explode($endToken, $splittedData[$tableNumber]);
        $i = 1;
        $size = strlen($splittedData2[0]);
        $testData = $splittedData2[0];
        if(!($testData[$size - $i] == ")")) {
            while((($size - $i) > 0) && (($testData[$size - $i]) != ")")) {
                if($i == 10) {
                    $i = -1;
                    break;
                }
                $i++;
            }
        }
        if($i != -1) {
            $i--;
            $splittedData3 = substr($splittedData2[0],0,-$i);
        }
        $tableNumber++;
        return $splittedData3;
    } else {
        return null;
    }
} // getNextDataSet

// Do the splits and place in arrays
// Note, these MUST be in the same order as getDBStructureAsSQL

$recTypeGroups = getNextDataSet($splittedData);
$detailTypeGroups = getNextDataSet($splittedData);
$ontologies = getNextDataSet($splittedData);
$terms = getNextDataSet($splittedData);
$recTypes = getNextDataSet($splittedData);
$detailTypes = getNextDataSet($splittedData);
$recStructure = getNextDataSet($splittedData);
$relationshipConstraints = getNextDataSet($splittedData);
$fileExtToMimetype = getNextDataSet($splittedData);
$translations = getNextDataSet($splittedData);
$savedSearches = getNextDataSet($splittedData);

// we are not extracting defCalcFunctions, defCrosswalk, defLanguage, defURLPrefixes, users, groups and tags
// add later if needed

// insert the arrays into the corresonding tables (new db) or temp tables (existing)
$query = "SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'";
mysql_query($query);

$errorCreatingReason = '';

processRecTypeGroups($recTypeGroups);
processDetailTypeGroups($detailTypeGroups);
processOntologies($ontologies);
processTerms($terms);
processRecTypes($recTypes);
processDetailTypes($detailTypes);
processRecStructure($recStructure);
processRelationshipConstraints($relationshipConstraints);
processFileExtToMimetype($fileExtToMimetype);
processTranslations($translations);
processSavedSearches($savedSearches);
$query = "SET SESSION sql_mode=''";
mysql_query($query);

// TODO: Make sure all values are written correctly (especially the NULL values)

// ------ Functions to write source DB definitions to local tables ---------------------------------------------------

// These insert statements updated by Ian ~12/8/11

// NOTE: It is ESSENTIAL that the insert statements here correspond in fields and in order with the
//       tables being written out by getDBStructureAsSQL. Some tables are not processed: (defCalcFunctions,
//       defCrosswalk, defLanguages, sysIdentification and UGrps and tags)


function processRecTypes($dataSet) {
    global $errorCreatingTables, $errorCreatingReason;

    if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
        include HEURIST_DIR."admin/structure/crosswalk/defRecTypesFields.inc";
        // Note re paths: it seems the relative path is ../../structure/crosswalk/ because it is relative to the calling
        // script (createNewDB.php). This can be problematic if buildCrosswalks is called from different levels in the tree
        $query = "INSERT INTO `defRecTypes` ($flds) VALUES" . $dataSet;
        mysql_query($query);
        if(mysql_error()) {
            $errorCreatingReason = $errorCreatingReason."RECTYPES Error inserting data: " . mysql_error() . "<br/>FIELDS:$flds<br/><br/>VALUES:$dataSet";
            $errorCreatingTables = TRUE;
            echo $errorCreatingReason;
        }

//$r = mysql_query("SELECT DATABASE()") or die(mysql_error());
//error_log("DATABASE IS ".mysql_result($r,0));

    } // END Imported first set of data to temp table: defRectypes
} // processRecTypes


function processDetailTypes($dataSet) {
    global $errorCreatingTables, $errorCreatingReason;
    if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
        include HEURIST_DIR."admin/structure/crosswalk/defDetailTypesFields.inc";
        $query = "INSERT INTO `defDetailTypes` ($flds) VALUES" . $dataSet;
        mysql_query($query);
        if(mysql_error()) {
            $errorCreatingReason = $errorCreatingReason."DETAILTYPES Error inserting data: " . mysql_error() . "<br/>";
            $errorCreatingTables = TRUE;
            echo $errorCreatingReason;
        }
    } // END Imported first set of data to temp table: defDetailTypes
} // processDetailTypes



function processRecStructure($dataSet) {
    global $errorCreatingTables, $errorCreatingReason;
    if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
        include HEURIST_DIR."admin/structure/crosswalk/defRecStructureFields.inc";
        $query = "INSERT INTO `defRecStructure` ($flds) VALUES " . $dataSet;
        mysql_query($query);
        if(mysql_error()) {
            $errorCreatingReason = $errorCreatingReason."RECSTRUCTURE Error inserting data: " . mysql_error() . "<br/>";
            $errorCreatingTables = TRUE;
            echo $errorCreatingReason;
        }
    } // END Imported first set of data to temp table: defRecStructure
} // processRecStructure



function processTerms($dataSet) {
    global $errorCreatingTables,$errorCreatingReason;
    if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
        include HEURIST_DIR."admin/structure/crosswalk/defTermsFields.inc";
        $query = "SET FOREIGN_KEY_CHECKS = 0;";
        mysql_query($query);
        $query = "INSERT INTO `defTerms` ($flds) VALUES " . $dataSet;
        mysql_query($query);
        if(mysql_error()) {
            $errorCreatingReason = $errorCreatingReason."TERMS Error inserting data: " . mysql_error() . "<br/>";//.$query."</br>";
            $errorCreatingTables = TRUE;
            echo $errorCreatingReason;
        }
        $query = "SET FOREIGN_KEY_CHECKS = 1;";
        mysql_query($query);
    } // END Imported first set of data to temp table: defTerms
} // processTerms

function processOntologies($dataSet) {
    global $errorCreatingTables,$errorCreatingReason;
    if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
        include HEURIST_DIR."admin/structure/crosswalk/defOntologiesFields.inc";
        $query = "INSERT INTO `defOntologies` ($flds) VALUES " . $dataSet;
        mysql_query($query);
        if(mysql_error()) {
            $errorCreatingReason = $errorCreatingReason."ONTOLOGIES Error inserting data: " . mysql_error() . "<br/>";
            $errorCreatingTables = TRUE;
            echo $errorCreatingReason;
        }
    } // END Imported first set of data to temp table: defOntologies
} // processOntologies



function processRelationshipConstraints($dataSet) {
    global $errorCreatingTables, $errorCreatingReason;
    if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
        include HEURIST_DIR."admin/structure/crosswalk/defRelationshipConstraintsFields.inc";
        $query = "INSERT INTO `defRelationshipConstraints` ($flds) VALUES " . $dataSet;
        mysql_query($query);
        if(mysql_error()) {
            $errorCreatingReason = $errorCreatingReason."RELATIONSHIPCONSTRAINTS Error inserting data: " . mysql_error() . "<br/>";
            $errorCreatingTables = TRUE;
            echo $errorCreatingReason;
        }
    } // END Imported first set of data to temp table: defRelationshipConstraints
} // processRelationshipConstraints



function processFileExtToMimetype($dataSet) {
    global $errorCreatingTables, $errorCreatingReason;
    if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
        include HEURIST_DIR."admin/structure/crosswalk/defFileExtToMimetypeFields.inc";
        $query = "INSERT INTO `defFileExtToMimetype` ($flds) VALUES " . $dataSet;
        mysql_query($query);
        if(mysql_error()) {
            $errorCreatingReason = $errorCreatingReason."FILEEXTTOMIMETYPE Error inserting data: " . mysql_error() . "<br/>";
            $errorCreatingTables = TRUE;
            echo $errorCreatingReason;
        }
    } // END Imported first set of data to temp table: defFileExtToMimetype
} //processFileExtToMimetype



function processRecTypeGroups($dataSet) {
    global $errorCreatingTables, $errorCreatingReason;

    if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
        include HEURIST_DIR."admin/structure/crosswalk/defRecTypeGroupsFields.inc";
        $query = "INSERT INTO `defRecTypeGroups` ($flds) VALUES " . $dataSet;
        mysql_query($query);
        if(mysql_error()) {
            $errorCreatingReason = $errorCreatingReason."RECTYPEGROUPS Error inserting data: " . mysql_error() . "<br/>";
            $errorCreatingTables = TRUE;
            echo $errorCreatingReason;
        }
    } // END Imported first set of data to temp table: defRecTypeGroups
} // processRectypeGroups



function processDetailTypeGroups($dataSet) {
    global $errorCreatingTables, $errorCreatingReason;
    if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
        include HEURIST_DIR."admin/structure/crosswalk/defDetailTypeGroupsFields.inc";
        $query = "INSERT INTO `defDetailTypeGroups` ($flds) VALUES " . $dataSet;
        mysql_query($query);
        if(mysql_error()) {
            $errorCreatingReason = $errorCreatingReason."DETAILTYPEGROUPS Error inserting data: " . mysql_error() . "<br/><br/>" . $dataSet . "<br/>";
            $errorCreatingTables = TRUE;
            echo $errorCreatingReason;
        }
    } // END Imported first set of data to temp table: defDetailTypeGroups
} // processDetailTypeGroups

function processSavedSearches($dataSet) {
    global $errorCreatingTables, $errorCreatingReason;

    if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
        include HEURIST_DIR."admin/structure/crosswalk/usrSavedSearchesFields.inc";
        $query = "INSERT INTO `usrSavedSearches` ($flds) VALUES " . $dataSet;
        mysql_query($query);
        if(mysql_error()) {
            $errorCreatingReason = $errorCreatingReason."SAVEDSEARCHES Error inserting data: " . mysql_error() . "<br/><br/>" . $dataSet . "<br/>";
            $errorCreatingTables = TRUE;
            echo $errorCreatingReason;
        }
    } // END Imported first set of data to temp table: usrSavedSearches
} // processSavedSearches

function processTranslations($dataSet) {
    global $errorCreatingTables, $errorCreatingReason;
    if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
        include HEURIST_DIR."admin/structure/crosswalk/defTranslationsFields.inc";
        $query = "INSERT INTO `defTranslations` ($flds) VALUES " . $dataSet;
        mysql_query($query);
        if(mysql_error()) {
            $errorCreatingReason = $errorCreatingReason."TRANSLATIONS Error inserting data: " . mysql_error() . "<br/>";
            $errorCreatingTables = TRUE;
            echo $errorCreatingReason;
        }
    } // END Imported first set of data to temp table: defTranslations
} // processTranslations

function unlockDatabase($isdroptemp=true) {
    global $tempDBName;
    if($isdroptemp && $tempDBName){
        db_drop($tempDBName, false);
    }
    mysql_connection_insert(DATABASE); // Use logged into DB
    $res = mysql_query("delete from sysLocks where lck_Action='buildcrosswalks'"); // Remove sysLock
}


// Done inserting data into all tables in temp database (or actual database if new database)

// If this spits out errors with unknown columns, look to see if createDefinitionsTablesOnly.sql
// has been brought up to date with the structure of blankDBStructure.sql

if($errorCreatingTables) { // An error occurred while trying to create one (or more) of the tables, or inserting data into them
    if($isNewDB) {
        echo "<br /><strong>An error occurred trying to insert data into the new database.</strong><br />";
    } else {
        echo "<br /><strong>An error occurred trying to insert the downloaded data into the temporary database.</strong><br />";
    }
    echo "This may be due to a database version mismatch, please advise the Heurist development team<br>";
    
    if(checkSmtp()){
        $email_title = 'Error on insert of structure definitions into '
                        .($isNewDB?' new database ':'temporary database for ').HEURIST_DBNAME;
        $email_text = "Database: ".HEURIST_DBNAME
                      ."\n\nAction: ".($isNewDB?'New database':'Filling temporary database for import definitions')
                      ."\n\nError Message: ".str_replace('<br/>',"\n",$errorCreatingReason);
        sendEmail(HEURIST_MAIL_TO_BUG, $email_title, $email_text, null);
    }
    
    unlockDatabase();
    return;
} else if(!$isNewDB){ // do crosswalking for exisitn database, no action for new database
    require_once("createCrosswalkTable.php"); // offer user choice of fields to import
    unlockDatabase(false);
}
?>
