<?php
    /*<!--
    * buildCrosswalks.php, Gets definitions from a specified installation of Heurist and writes them
    * either to a new DB, or temp DB, 18-02-2011, by Juan Adriaanse
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
    // 4 Aug 2011, changed to import table structures from populateBlankDatabase.sql and to
    // include crosswalking during creation of a new database


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

    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

    if (!is_logged_in()) {
        header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
        return;
    }

    // ------Administrative stuff ------------------------------------------------------------------------------------

    // Verify credentials of the user and check that they are an administrator, check no-one else is trying to
    // change the definitions at the same time


    // Requires admin user, access to definitions though get_definitions is open
    if (! is_admin()) {
        print "<html><head><link rel=stylesheet href='../../common/css/global.css'>
        </head><body><div class=wrap><div id=errorMsg>
        <span>You do not have sufficient privileges to access this page</span><p>
        <a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME.
        " target='_top'>Log out</a></p></div></div></body></html>";
        return;
    }

    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');


    $definitions_being_modified = FALSE;
    $server_offline = FALSE;
    global $errorCreatingTables;
    $errorCreatingTables = FALSE;

    // Deals with all the database connections stuff
    mysql_connection_db_insert(DATABASE);

    global $dbname;

    // Create new temp database with timestamp
    global $tempDBName;
    if(!isset($isNewDB)) { $isNewDB = false; }

    if($isNewDB)
    { // For new database, insert coreDefinitions.txt directly into tables, no temp database required
        $tempDBName = "`".$newname."`";
        $dbname = $newname;
    } Else { // existing database needs temporary database to store data read and allow selection
        $dbname = DATABASE;
        $isNewDB = false;
        $dateAndTime = date("dmygisa");
        $tempDBName = "temp_" . $dateAndTime;
        mysql_query("CREATE DATABASE `" . $tempDBName . "`");
    } // existing database

    // Create the Heurist structure for the temp database, using the new database template SQL file
    if (!$isNewDB) {
        $cmdline="mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD.
            " -D$tempDBName < ../setup/createDefinitionTablesOnly.sql";
        $output2 = exec($cmdline . ' 2>&1', $output, $res2);
        if($res2 != 0) {
            die("MySQL exec code $res2 : Unable to create table structure for new database $tempDBName (failure in executing createDefinitionTablesOnly.sql)");
        }
    }

    mysql_connection_db_insert($tempDBName); // Use temp database

    $dbVersion = 3.1; // Definitions exchange format version number.

    // TODO: use HEURIST_DBVERSION TO SET THE VERSION HERE

    // * IMPORTANT *
    // UPDATE THE FOLLOWING WHEN DATABASE FORMAT IS CHANGED:
    // SQL queries for data (below)
    // Version info in buildCrosswalks.php
    // insert queries in buildCrosswalks.php
    // /admin/setup/createDefinitionTablesOnly.sql
    // /admin/setup/PopulateBlankDatabase.sql


    // -----Check not locked by admin -------------------------------

    // THIS SECTION SHOULD BE ABSTRACTED AS A FUNCTION IN ONE OF THE LIBRARIES, perhaps in cred.php?

    // ???? we should now mark the target (current)database for administrator access to avoid two administrators
    // working on this at the same time. But need to provide a means of removing lock in case the
    // connection is lost, eg. heartbeat on subsequent pages or a specific 'remove admin lock' link (easier)

    // Check if someone else is already modifying database definitions, if so: stop.
    $res = mysql_query("select lck_UGrpID from sysLocks where lck_ID=1");
    if($res) {
        if (mysql_num_rows($res)>0) {
            echo "Definitions are already being modified.";
            $definitions_being_modified = TRUE; // database definitions are being modified by administrator
            }
    }

    if ($definitions_being_modified) {
        // "Another administrator is modifying the definitions"
        // "If this is not the case, "(or appears to be same user)" use 'Remove lock on database definition modification' from the administration page"
        // "Click [continue] to return to the administration page"
        header('Location: ' . BASE_PATH . 'admin/index.php'); // return to the adminstration page
        die("Definitions are already being modified.");
    }

    // Mark database definitons as being modified by adminstrator
    $definitions_being_modified=TRUE;
    $query = "insert into sysLocks (lck_ID, lck_UGrpID, lck_Action) VALUES ('1', '0', 'BuildCrosswalks')";

    $res = mysql_query($query); // create sysLock

    // ------Find and set the source database-----------------------------------------------------------------------

    // Query heuristscholar.org Index database to find the URL of the installation you want to use as source
    // The query should be based on DOAP metadata and keywords which Steven is due to set up in the Index database


    //  Set three fields below to information about the database you will be importing from
    global $source_db_id;
    if(!isset($_REQUEST["dbID"]) || $_REQUEST["dbID"] == 0) {
        $source_db_id = '2'; //MAGIC NUMBER - ID of HeuristSystem_Reference db in Heurist_System_Index database
        $source_db_name = 'Reference';
        $source_db_prefix = 'HeuristSystem_';
        $source_url = "http://heuristscholar.org/h3/admin/structure/getDBStructure.php?prefix=HeuristSystem_&db=Reference";
    } else {
        $source_db_id = $_REQUEST["dbID"];
        $source_db_name = $_REQUEST["dbName"];
        $source_db_prefix = @$_REQUEST["dbPrefix"] && @$_REQUEST["dbPrefix"] != "" ? @$_REQUEST["dbPrefix"] : null;
        error_log($source_db_name);
        $source_url = $_REQUEST["dbURL"]."admin/structure/getDBStructure.php?db=".$source_db_name.(@$source_db_prefix?"&prefix=".$source_db_prefix:"");
    }


    if($isNewDB) { // minimal definitions from coreDefinitions.txt - format same as getDBStructure returns
        $file = fopen("../setup/coreDefinitions.txt", "r");
        while(!feof($file)) {
            $output = $output . fgets($file, 4096);
        }
        fclose($file);
        $data = $output;
        $server_offline = FALSE; //we don't care as we have the data
        } else { // Request data from source database using getDBStructure.php
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_COOKIEFILE, '/dev/null');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);	//return curl_exec output as string
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        curl_setopt($ch, CURLOPT_NOBODY, 0);
        curl_setopt($ch, CURLOPT_HEADER, 0);	//don't include header in output
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);	// follow server header redirects
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);	// don't verify peer cert
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);	  // timeout after ten seconds
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);	   // no more than 5 redirections
        curl_setopt($ch, CURLOPT_URL,$source_url);
        $data = curl_exec($ch);
        $error = curl_error($ch);
        if ($error || !$data || substr($data, 0, 6) == "unable") {
            $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
            $server_offline = TRUE;
        }
    }

    if($server_offline) { // Cancel buildCrosswalk process as no data can be received
        die("Source database $source_db_id:$source_db_prefix$source_db_name could not be accessed at $source_url, server may be offline");
    }

    // Split received data into data sets for one table defined by >>StartData>> and >>EndData>> markers.

    $splittedData = split(">>StartData>>", $data);
    $tableNumber;

    function getNextDataSet($splittedData) {
        global $tableNumber;
        if(!$tableNumber) {
            $tableNumber = 1;
        }
        if(sizeof($splittedData) > $tableNumber) {
            $splittedData2 = split(">>EndData>>", $splittedData[$tableNumber]);
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
    // Note, these MUST be in the same order as getDBStructure
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
    // not extracting defCalcFunctions, users, groups and tags
    error_log(" dt = $detailTypes");
    
    // insert the arrays into the corresonding tables (new db) or temp tables (existing)
    $query = "SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'";
    mysql_query($query);
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
    $query = "SET SESSION sql_mode=''";
    mysql_query($query);

    // TODO: Make sure all values are written correctly (especially the NULL values)

    // ------ Functions to write source DB definitions to local tables ---------------------------------------------------

    // These insert statements updated by Ian ~12/8/11

    // NOTE: It is ESSENTIAL that the insert statemetn here correspond in fields and in order with the
    //       tables being written out by getDBStructure


    function processRecTypes($dataSet) {
        global $errorCreatingTables;
        if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
            include "defRecTypesFields.inc";
            $query = "INSERT INTO `defRecTypes` ($flds) VALUES" . $dataSet;
            mysql_query($query);
            if(mysql_error()) {
                echo "RECTYPES Error inserting data: " . mysql_error() . "<p>FIELDS:$flds<br /><p>VALUES:$dataset<p>";
                $errorCreatingTables = TRUE;
            }
        } // END Imported first set of data to temp table: defRectypes
    } // processRecTypes


    function processDetailTypes($dataSet) {
        global $errorCreatingTables;
        if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
            include "defDetailTypesFields.inc";
            $query = "INSERT INTO `defDetailTypes` ($flds) VALUES" . $dataSet;
            mysql_query($query);
            if(mysql_error()) {
                echo "DETAILTYPES Error inserting data: " . mysql_error() . "<br />";
                $errorCreatingTables = TRUE;
            }
        } // END Imported first set of data to temp table: defDetailTypes
    } // processDetailTypes



    function processRecStructure($dataSet) {
        global $errorCreatingTables;
        if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
            $query = "INSERT INTO `defRecStructure` ($flds) VALUES " . $dataSet;
            mysql_query($query);
            if(mysql_error()) {
                echo "RECSTRUCTURE Error inserting data: " . mysql_error() . "<br />";
                $errorCreatingTables = TRUE;
            }
        } // END Imported first set of data to temp table: defRecStructure
    } // processRecStructure



    function processTerms($dataSet) {
        global $errorCreatingTables;
        if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
            $query = "SET FOREIGN_KEY_CHECKS = 0;";
            mysql_query($query);
            $query = "INSERT INTO `defTerms` ($flds) VALUES " . $dataSet;
            mysql_query($query);
            if(mysql_error()) {
                echo "TERMS Error inserting data: " . mysql_error() . "<br />";
                $errorCreatingTables = TRUE;
            }
            $query = "SET FOREIGN_KEY_CHECKS = 1;";
            mysql_query($query);
        } // END Imported first set of data to temp table: defTerms
    } // processTerms



    function processOntologies($dataSet) {
        global $errorCreatingTables;
        if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
            $query = "INSERT INTO `defOntologies` ($flds) VALUES " . $dataSet;
            mysql_query($query);
            if(mysql_error()) {
                echo "ONTOLOGIES Error inserting data: " . mysql_error() . "<br />";
                $errorCreatingTables = TRUE;
            }
        } // END Imported first set of data to temp table: defOntologies
    } // processOntologies



    function processRelationshipConstraints($dataSet) {
        global $errorCreatingTables;
        if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
            $query = "INSERT INTO `defRelationshipConstraints` ($flds) VALUES " . $dataSet;
            mysql_query($query);
            if(mysql_error()) {
                echo "RELATIONSHIPCONSTRAINTS Error inserting data: " . mysql_error() . "<br />";
                $errorCreatingTables = TRUE;
            }
        } // END Imported first set of data to temp table: defRelationshipConstraints
    } // processRelationshipConstraints



    function processFileExtToMimetype($dataSet) {
        global $errorCreatingTables;
        if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
            $query = "INSERT INTO `defFileExtToMimetype` ($flds) VALUES " . $dataSet;
            mysql_query($query);
            if(mysql_error()) {
                echo "FILEEXTTOMIMETYPE Error inserting data: " . mysql_error() . "<br />";
                $errorCreatingTables = TRUE;
            }
        } // END Imported first set of data to temp table: defFileExtToMimetype
    } //processFileExtToMimetype



    function processRecTypeGroups($dataSet) {
        global $errorCreatingTables;
        if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
            $query = "INSERT INTO `defRecTypeGroups` ($flds) VALUES " . $dataSet;
            mysql_query($query);
            if(mysql_error()) {
                echo "RECTYPEGROUPS Error inserting data: " . mysql_error() . "<br />";
                $errorCreatingTables = TRUE;
            }
        } // END Imported first set of data to temp table: defRecTypeGroups
    } // processRectypeGroups



    function processDetailTypeGroups($dataSet) {
        global $errorCreatingTables;
        if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
            $query = "INSERT INTO `defDetailTypeGroups` ($flds) VALUES " . $dataSet;
            mysql_query($query);
            if(mysql_error()) {
                echo "DETAILTYPEGROUPS Error inserting data: " . mysql_error() . "<br /><br />" . $dataSet . "<br />";
                $errorCreatingTables = TRUE;
            }
        } // END Imported first set of data to temp table: defDetailTypeGroups
    } // processDetailTypeGroups



    function processTranslations($dataSet) {
        global $errorCreatingTables;
        if(!(($dataSet == "") || (strlen($dataSet) <= 2))) { // no action if no data
            $query = "INSERT INTO `defTranslations` ($flds) VALUES " . $dataSet;
            mysql_query($query);
            if(mysql_error()) {
                echo "TRANSLATIONS Error inserting data: " . mysql_error() . "<br />";
                $errorCreatingTables = TRUE;
            }
        } // END Imported first set of data to temp table: defTranslations
    } // processTranslations


    // Done inserting data into all tables in temp database (or actual database if new database).

    if($errorCreatingTables) { // An error occurred while trying to create one (or more) of the tables, or inserting data into them
        if($isNewDB) {
            echo "<br /><strong>An error occurred trying to insert data into the new database.</strong><br />";
        } else {
            echo "<br /><strong>An error occurred trying to insert the downloaded data into the temporary database.</strong><br />";
        }
        mysql_query("DROP DATABASE `" . $tempDBName . "`"); // Delete temp database or incomplete new database
        return;
    } else if(!$isNewDB){
        require_once("createCrosswalkTable.php"); // offer user choice of fields to import
    }

    // TODO: Replace this line with centralised locking methodology
    $res = mysql_query("delete from sysLocks where lck_ID='1'"); // Remove sysLock
?>